<?php
/**
 * QRMS Analitik — menü görüntüleme / ürün tıklama sayaçları ve masa bazlı takip.
 *
 * Eski bağımsız "RMA Analytics" eklentisinin suite'e taşınmış hâlidir. Veri
 * tabanı tablosu bilinçli olarak aynı bırakıldı ({prefix}rma_analytics): canlı
 * sitelerde birikmiş kayıtlar korunur, üzerine yalnızca `masa_no` sütunu
 * eklenir (dbDelta migration).
 *
 * TAKİP NOKTASI
 * Modül kendi izleme uçlarını AÇMAZ; restoran-menu modülünün zaten var olan
 * iki AJAX ucuna öncelik 5 ile bağlanır:
 *
 *   - `rma_load_items`          → menü görüntüleme  (menu_view)
 *   - `rma_get_product_details` → ürün tıklama      (product_click)
 *
 * Öncelik 5, asıl işleyicilerin (öncelik 10) yanıtı göndermesinden önce
 * çalışmayı garanti eder; wp_send_json_* çağrısı isteği sonlandırdığı için
 * daha geç bir öncelik hiç çalışmazdı.
 *
 * MASA BİLGİSİ
 * Masa slug'ı üç kaynaktan sırayla aranır: isteğin kendi `masa` alanı (menü
 * JS'i ?masa=... parametresini buraya taşır), imzalı masa oturumu çerezi
 * (qr-masa-oturum-guvenligi modülü) ve son çare olarak referer adresindeki
 * `?masa=`. Böylece JS önbellekten eski sürümüyle gelse bile kayıt masasız
 * kalmaz.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'QRMS_Analitik' ) ) {
	return;
}

/**
 * Analitik veri toplama ve raporlama katmanı.
 */
class QRMS_Analitik {

	/**
	 * Şema sürümü. masa_no sütunu 1.1 ile geldi.
	 */
	const DB_SURUM = '1.2';

	/**
	 * Şema sürümünün tutulduğu option.
	 */
	const DB_OPT = 'qrms_analitik_db_surumu';

	/**
	 * Panel AJAX uçlarının nonce eylemi.
	 */
	const NONCE = 'qrms_analitik';

	/**
	 * Ön yüz beacon'ının nonce eylemi (splash / dil / galeri / detay modalı).
	 */
	const NONCE_ONYUZ = 'qrms_analitik_onyuz';

	/**
	 * CSV dışa aktarma nonce eylemi.
	 */
	const NONCE_CSV = 'qrms_analitik_csv';

	/**
	 * Masa sütununun uzunluğu (şemayla aynı tutulur).
	 */
	const MASA_UZUNLUK = 64;

	/**
	 * Panelde desteklenen dönemler.
	 *
	 * @var string[]
	 */
	const DONEMLER = array( 'hourly', 'daily', 'weekly', 'monthly', 'masalar' );

	/**
	 * genel_bakis() istek içi önbelleği (masa filtresine göre).
	 *
	 * @var array<string,array<string,int>>
	 */
	private static $genel_bakis_onbellegi = array();

	/**
	 * "Masalara Göre" görünümünün kapsadığı gün sayısı.
	 */
	const MASA_GUN = 30;

	/**
	 * Yaklaşık sepet oturumunun saat cinsinden penceresi.
	 *
	 * Tabloda oturum sütunu yoktur; ip_hash + masa_no + bu pencere bir
	 * oturum sayılır. SQL ve Sepet & Sipariş ekranı AYNI sabiti kullanır.
	 */
	const OTURUM_SAAT = 2;

	/**
	 * Sepet olay gruplarının istek içi önbelleği (aralık+masa anahtarlı).
	 *
	 * @var array<string,array<int,array<string,mixed>>>
	 */
	private static $sepet_grup_onbellegi = array();

	/**
	 * İzleme kaydı için doğrulanan nonce eylemi.
	 *
	 * Kancalandığı uçların (rma_load_items / rma_get_product_details) menü
	 * modülünde ürettiği nonce ile AYNI olmak zorundadır:
	 * modules/restoran-menu/includes/trait-frontend.php içindeki
	 * wp_create_nonce( 'rma_ajax_nonce' ).
	 */
	const NONCE_TAKIP = 'rma_ajax_nonce';

	/**
	 * Eski kayıtları silen zamanlanmış görevin kanca adı.
	 */
	const CRON_TEMIZLIK = 'qrms_analitik_temizlik';

	/**
	 * Ham analitik kaydının varsayılan saklama süresi (gün).
	 *
	 * Tablo her menü görüntülemesi ve ürün tıklamasında büyür; saklama
	 * politikası olmadan tek temizlik yolu tabloyu tamamen boşaltmaktı
	 * (TRUNCATE), yani yönetici ya tüm geçmişini kaybediyor ya da tabloyu
	 * büyütmeye devam ediyordu.
	 */
	const SAKLAMA_GUN = 90;

	/**
	 * Ham CSV'nin tek seferde belleğe alacağı satır sayısı.
	 */
	const CSV_PARCA = 2000;

	/**
	 * Ham CSV'nin yazacağı azami satır sayısı (aşılırsa dosya sonuna uyarı).
	 */
	const CSV_TAVAN = 200000;

	/**
	 * Silme işleminin tek turda kaldıracağı azami satır sayısı.
	 *
	 * Yıllardır biriken bir tabloda sınırsız DELETE, tabloyu uzun süre
	 * kilitleyip cron isteğini zaman aşımına düşürebilir.
	 */
	const SAKLAMA_PARCA = 5000;

	/**
	 * Bir cron turunun silmeye ayıracağı azami süre (saniye).
	 *
	 * Temizlik eskiden GÜNDE TEK BİR 5000'lik parça siliyordu. Günde 5000'den
	 * fazla olay üreten bir sitede bu, temizliğin BİRİKİME HİÇ YETİŞEMEMESİ
	 * demekti: tablo sınırsız büyüyor, üzerindeki her sorgu giderek yavaşlıyordu.
	 * Artık tur içinde parçalar peş peşe silinir; bütçe dolunca durulur, böylece
	 * ne cron isteği zaman aşımına uğrar ne de tablo uzun süre meşgul edilir.
	 */
	const SAKLAMA_SURE = 10;

	/**
	 * Bir turdaki azami parça sayısı (emniyet freni).
	 *
	 * Süre bütçesi tek başına yeterlidir; bu sınır, sistem saati geriye
	 * atlarsa döngünün sonsuza gitmemesi içindir.
	 */
	const SAKLAMA_TUR = 50;

	/**
	 * Tip bazlı saklama istisnaları (gün).
	 *
	 * Tanımsız tipler saklama_gun() değerini kullanır — varsayılan davranış
	 * değişmez. Sepet olayları her tıklamada (debounce'lu da olsa) yazılır;
	 * 90 gün boyunca tutmak tabloyu şişirir. Sipariş olayları seyrek ve
	 * değerlidir, bu yüzden daha uzun kalır.
	 *
	 * 0 = o tip hiç silinmez.
	 *
	 * @var array<string,int>
	 */
	const SAKLAMA_GUN_TIP = array(
		'cart_add'        => 14,
		'cart_remove'     => 14,
		'splash_view'     => 14,
		'splash_action'   => 30,
		'chatbot_message' => 30,
		'gallery_view'    => 30,
		'lang_switch'     => 30,
		'order_sent'      => 365,
		'order_blocked'   => 365,
		'order_failed'    => 180,
		'review_submit'   => 180,
		'form_submit'     => 180,
		'reward_issued'   => 365,
		'reward_redeemed' => 365,
	);

	/**
	 * Temizliğin tek tek (idx_td ile) tarayacağı bilinen olay tipleri.
	 *
	 * Yeni bir tip yazıldığında buraya eklenmezse, o tip varsayılan süreyle
	 * yine de silinir: döngü haritadaki anahtarları da birleştirir.
	 *
	 * @var string[]
	 */
	const OLAY_TIPLERI = array(
		'menu_view',
		'product_click',
		'cart_add',
		'cart_remove',
		'order_sent',
		'order_failed',
		'order_blocked',
		'waiter_call',
		'bill_request',
		'chatbot_message',
		'lang_switch',
		'splash_view',
		'splash_action',
		'gallery_view',
		'reward_issued',
		'reward_redeemed',
		'review_submit',
		'form_submit',
		'item_detail_open',
	);

	/**
	 * Hook kayıtları.
	 *
	 * @return void
	 */
	public static function init() {
		// Şema kontrolü `init`e bağlıdır, `admin_init`e değil: tablo ilk kez
		// bir ziyaretçi menüyü açtığında da hazır olmalı (admin-ajax istekleri
		// de `init`i tetikler). Sürüm option'ı eşleştiğinde maliyeti tek bir
		// autoload option okumasıdır.
		add_action( 'init', array( __CLASS__, 'sema_kontrol' ), 5 );

		// Eski bağımsız eklenti hâlâ etkinse takibi ona bırak: aksi hâlde her
		// olay iki kez yazılır. Panel yine açılır, veriyi aynı tablodan okur.
		if ( ! self::eski_eklenti_aktif() ) {
			foreach ( array( '', 'nopriv_' ) as $on_ek ) {
				add_action( "wp_ajax_{$on_ek}rma_load_items", array( __CLASS__, 'izle_menu_goruntuleme' ), 5 );
				add_action( "wp_ajax_{$on_ek}rma_get_product_details", array( __CLASS__, 'izle_urun_tiklama' ), 5 );
			}
		}

		add_action( 'wp_ajax_qrms_analitik_genel', array( __CLASS__, 'ajax_genel' ) );
		add_action( 'wp_ajax_qrms_analitik_urunler', array( __CLASS__, 'ajax_urunler' ) );
		add_action( 'wp_ajax_qrms_analitik_masalar', array( __CLASS__, 'ajax_masalar' ) );
		add_action( 'wp_ajax_qrms_analitik_sepet', array( __CLASS__, 'ajax_sepet' ) );
		add_action( 'wp_ajax_qrms_analitik_etkilesim', array( __CLASS__, 'ajax_etkilesim' ) );
		add_action( 'wp_ajax_qrms_analitik_acilis', array( __CLASS__, 'ajax_acilis' ) );
		add_action( 'wp_ajax_qrms_analitik_csv', array( __CLASS__, 'ajax_csv' ) );
		add_action( 'wp_ajax_qrms_analitik_temizle', array( __CLASS__, 'ajax_temizle' ) );
		add_action( 'admin_post_qrms_analitik_saklama', array( __CLASS__, 'saklama_formu' ) );

		// Ön yüz beacon'ı: splash / dil / galeri / detay modalı. qr-analiz
		// yüklüyse (bu sınıfın varlığı) kuyruğa girer; pasifse bu init hiç
		// çalışmaz.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'onyuz_varlik' ) );
		foreach ( array( '', 'nopriv_' ) as $on_ek ) {
			add_action( "wp_ajax_{$on_ek}qrms_analitik_onyuz", array( __CLASS__, 'ajax_onyuz' ) );
		}

		// Saklama süresi dolan ham kayıtları silen günlük görev.
		add_action( self::CRON_TEMIZLIK, array( __CLASS__, 'eski_kayitlari_sil' ) );
		add_action( 'init', array( __CLASS__, 'temizlik_planla' ), 5 );
	}

	/* -----------------------------------------------------------------
	   SAKLAMA POLİTİKASI
	----------------------------------------------------------------- */

	/**
	 * Kullanıcının belirlediği saklama süresinin option adı.
	 *
	 * Yoksa SAKLAMA_GUN geçerlidir; option yalnızca "Veri & Sistem" ekranından
	 * yazılır.
	 */
	const SAKLAMA_OPT = 'qrms_analitik_saklama_gun';

	/**
	 * Ham kaydın saklanacağı gün sayısı.
	 *
	 * Üç katman, bu sırayla: sabit varsayılan → yöneticinin ekrandan
	 * kaydettiği değer → filtre. Filtre EN SONDA kalır ki kodla sabitleyen
	 * kurulumlar (ör. bir mu-plugin) ekrandan gelen değerle ezilmesin; ekran
	 * da bu durumu görünür kılar (bkz. saklama_kilitli_mi).
	 *
	 * 0 döndürmek temizliği kapatır (sınırsız saklama). Alt sınır 7 gündür:
	 * daha kısa bir değer, panelin "son 30 gün" görünümlerini boşaltırdı.
	 *
	 * @return int
	 */
	public static function saklama_gun() {
		/**
		 * Analitik ham kaydının saklama süresi (gün). 0 = temizlik kapalı.
		 *
		 * @param int $gun Kayıtlı (ya da varsayılan) saklama süresi.
		 */
		$gun = (int) apply_filters( 'qrms_analitik_saklama_gun', self::saklama_ayari() );

		if ( $gun <= 0 ) {
			return 0;
		}

		return max( 7, $gun );
	}

	/**
	 * Tip bazlı saklama istisnalarının (filtre uygulanmış) haritası.
	 *
	 * @return array<string,int>
	 */
	public static function saklama_gun_tip_haritasi() {
		/**
		 * Olay tipine göre saklama süresi (gün). Anahtarı olmayan tipler
		 * saklama_gun() kullanır. 0 = o tip silinmez.
		 *
		 * @param array<string,int> $harita Varsayılan istisnalar.
		 */
		$harita = apply_filters( 'qrms_analitik_saklama_gun_tip', self::SAKLAMA_GUN_TIP );

		if ( ! is_array( $harita ) ) {
			return self::SAKLAMA_GUN_TIP;
		}

		$temiz = array();

		foreach ( $harita as $tip => $gun ) {
			$tip = sanitize_key( (string) $tip );

			if ( '' === $tip ) {
				continue;
			}

			$temiz[ $tip ] = (int) $gun;
		}

		return $temiz;
	}

	/**
	 * Bir olay tipinin saklanacağı gün sayısı.
	 *
	 * İstisna yoksa saklama_gun() — mevcut davranış. Global temizlik kapalıysa
	 * (0) tip istisnaları da uygulanmaz: "sınırsız saklama" her tipi kapsar.
	 *
	 * @param string $tip event_type.
	 * @return int
	 */
	public static function saklama_gun_tip( $tip ) {
		$varsayilan = self::saklama_gun();

		if ( 0 === $varsayilan ) {
			return 0;
		}

		$tip    = sanitize_key( (string) $tip );
		$harita = self::saklama_gun_tip_haritasi();

		if ( ! isset( $harita[ $tip ] ) ) {
			return $varsayilan;
		}

		$gun = (int) $harita[ $tip ];

		if ( $gun <= 0 ) {
			return 0;
		}

		// Tip istisnası kasıtlı olarak varsayılanın alt sınırından (7) kısa
		// olabilir: sepet olayları 14 günde yeterince eğilim verir.
		return $gun;
	}

	/**
	 * Ekrandan kaydedilmiş saklama süresi (yoksa sabit varsayılan).
	 *
	 * @return int
	 */
	public static function saklama_ayari() {
		$kayitli = get_option( self::SAKLAMA_OPT, null );

		if ( null === $kayitli || '' === $kayitli ) {
			return self::SAKLAMA_GUN;
		}

		return (int) $kayitli;
	}

	/**
	 * Saklama süresi bir filtreyle KODDAN sabitlenmiş mi?
	 *
	 * Ekran, kaydedilen değerin geçerli olmayacağı bir kurulumda kullanıcıyı
	 * boşuna uğraştırmasın diye bunu sorar: kaydet düğmesi çalışır ama
	 * sonucu değiştirmez, o yüzden uyarı basılır.
	 *
	 * @return bool
	 */
	public static function saklama_kilitli_mi() {
		$ayar = self::saklama_ayari();

		/** This filter is documented in modules/qr-analiz/class-qrms-analitik.php */
		return (int) apply_filters( 'qrms_analitik_saklama_gun', $ayar ) !== $ayar;
	}

	/**
	 * Saklama süresini kaydeder.
	 *
	 * @param int $gun Gün sayısı (0 = temizlik kapalı, aksi hâlde en az 7).
	 * @return int Kaydedilen değer.
	 */
	public static function saklama_kaydet( $gun ) {
		$gun = (int) $gun;
		$gun = $gun <= 0 ? 0 : max( 7, $gun );

		update_option( self::SAKLAMA_OPT, $gun, false );

		return $gun;
	}

	/**
	 * Tablonun boyut/kapsam bilgisi — TRANSIENT ile önbelleklenir.
	 *
	 * Satır sayısı ve disk boyutu her sayfa açılışında sorulacak şeyler
	 * değildir: COUNT(*) büyük bir tabloda tam tarama, information_schema ise
	 * kimi kurulumlarda gözle görülür biçimde yavaştır. Bir saatlik önbellek
	 * bu ekran için fazlasıyla tazedir; kayıt silindiğinde önbellek zaten
	 * düşürülür (bkz. ajax_temizle).
	 *
	 * @param bool $yenile true ise önbellek atlanır.
	 * @return array{var:bool,satir:int,boyut:int,ilk:string,guncel:bool}
	 */
	public static function tablo_istatistikleri( $yenile = false ) {
		global $wpdb;

		$anahtar = 'qrms_analitik_tablo_istat';

		if ( ! $yenile ) {
			$onbellek = get_transient( $anahtar );

			if ( is_array( $onbellek ) ) {
				return $onbellek;
			}
		}

		$istat = array(
			'var'    => false,
			'satir'  => 0,
			'boyut'  => 0,
			'ilk'    => '',
			'guncel' => ( self::DB_SURUM === get_option( self::DB_OPT ) ),
		);

		if ( ! self::tablo_var_mi() ) {
			set_transient( $anahtar, $istat, HOUR_IN_SECONDS );

			return $istat;
		}

		$istat['var'] = true;
		$tablo        = self::tablo();

		// Satır sayısı ve en eski kayıt aynı taramadan çıkar; ikisi için ayrı
		// sorgu açmanın anlamı yok.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$satir = $wpdb->get_row( "SELECT COUNT(*) AS satir, MIN(created_at) AS ilk FROM {$tablo}", ARRAY_A );

		if ( is_array( $satir ) ) {
			$istat['satir'] = (int) $satir['satir'];
			$istat['ilk']   = (string) $satir['ilk'];
		}

		// Disk boyutu yaklaşıktır: InnoDB'nin bildirdiği veri + indeks
		// uzunluğu, silinen satırların bıraktığı boşluğu da içerir.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$boyut = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT DATA_LENGTH + INDEX_LENGTH FROM information_schema.TABLES
				 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
				$tablo
			)
		);

		$istat['boyut'] = (int) $boyut;

		set_transient( $anahtar, $istat, HOUR_IN_SECONDS );

		return $istat;
	}

	/**
	 * Tablo istatistiği önbelleğini düşürür.
	 *
	 * @return void
	 */
	public static function istatistik_onbellegini_temizle() {
		delete_transient( 'qrms_analitik_tablo_istat' );
	}

	/**
	 * Günlük temizlik görevini (yoksa) planlar.
	 *
	 * @return void
	 */
	public static function temizlik_planla() {
		if ( wp_next_scheduled( self::CRON_TEMIZLIK ) ) {
			return;
		}

		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_TEMIZLIK );
	}

	/**
	 * Planlanmış temizlik görevini kaldırır (eklenti devre dışı bırakılırken).
	 *
	 * @return void
	 */
	public static function temizlik_iptal() {
		wp_clear_scheduled_hook( self::CRON_TEMIZLIK );
	}

	/**
	 * Saklama süresi dolan kayıtları siler.
	 *
	 * TRUNCATE'ten farkı: yalnızca eskiyen satırlar gider, yakın geçmişin
	 * raporları olduğu gibi kalır. Tek turda en fazla SAKLAMA_PARCA satır
	 * silinir; kalanı ertesi gün (ya da elle tetiklendiğinde) temizlenir.
	 *
	 * Tip bazlı istisnalar idx_td (event_type, created_at) üzerinden, tip
	 * başına ayrı DELETE ile uygulanır. Böylece sepet olayları 14 günde
	 * giderken sipariş satırları 365 güne kadar kalır; tek bir
	 * `created_at < varsayılan` silmesi uzun ömürlü tipleri de götürmez.
	 *
	 * @return int Silinen satır sayısı.
	 */
	public static function eski_kayitlari_sil() {
		global $wpdb;

		$varsayilan = self::saklama_gun();

		if ( 0 === $varsayilan ) {
			return 0;
		}

		if ( ! self::tablo_var_mi() ) {
			return 0;
		}

		$tablo  = self::tablo();
		$tipler = array_unique( array_merge( self::OLAY_TIPLERI, array_keys( self::saklama_gun_tip_haritasi() ) ) );

		/**
		 * Bir temizlik turunun süre bütçesi (saniye). 0 = tek parça sil.
		 *
		 * @param int $saniye Varsayılan SAKLAMA_SURE.
		 */
		$butce  = (int) apply_filters( 'qrms_analitik_temizlik_sure', self::SAKLAMA_SURE );
		$baslar = microtime( true );

		$toplam = 0;
		$tur    = 0;

		foreach ( $tipler as $tip ) {
			$tip = sanitize_key( (string) $tip );

			if ( '' === $tip ) {
				continue;
			}

			$gun = self::saklama_gun_tip( $tip );

			if ( 0 === $gun ) {
				continue;
			}

			$sinir = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $gun . ' days', self::simdi() ) );

			while ( $tur < self::SAKLAMA_TUR ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$silinen = (int) $wpdb->query(
					$wpdb->prepare(
						// idx_td (event_type, created_at): eşitlik + aralık.
						"DELETE FROM {$tablo} WHERE event_type = %s AND created_at < %s LIMIT %d",
						$tip,
						$sinir,
						self::SAKLAMA_PARCA
					)
				);

				++$tur;
				$toplam += $silinen;

				// Parça dolmadıysa bu tipte silinecek bir şey kalmamıştır.
				if ( $silinen < self::SAKLAMA_PARCA ) {
					break;
				}

				if ( $butce <= 0 || ( microtime( true ) - $baslar ) >= $butce ) {
					return $toplam;
				}
			}

			if ( $tur >= self::SAKLAMA_TUR ) {
				break;
			}
		}

		return $toplam;
	}

	/**
	 * Eski bağımsız RMA Analytics eklentisi devrede mi?
	 *
	 * @return bool
	 */
	public static function eski_eklenti_aktif() {
		return class_exists( 'RMA_Analytics' );
	}

	/**
	 * Eski eklentinin `active_plugins` içindeki dosya yolu ('' = bulunamadı).
	 *
	 * eski_eklenti_aktif() yalnızca "bir yerde RMA_Analytics sınıfı var" der;
	 * kullanıcıya "şunu kapat" diyebilmek için hangi EKLENTİNİN tanımladığını
	 * bilmek gerekir. Sınıfın dosyası Reflection ile bulunur, plugin_basename()
	 * ile eklenti klasörüne indirgenir ve aktif eklenti listesiyle eşleştirilir.
	 *
	 * @return string Ör. "rma-analytics/rma-analytics.php".
	 */
	public static function eski_eklenti_dosyasi() {
		if ( ! self::eski_eklenti_aktif() ) {
			return '';
		}

		try {
			$sinif = new ReflectionClass( 'RMA_Analytics' );
			$yol   = (string) $sinif->getFileName();
		} catch ( ReflectionException $e ) {
			return '';
		}

		if ( '' === $yol ) {
			return '';
		}

		return self::eklenti_dosyasini_bul(
			plugin_basename( $yol ),
			(array) get_option( 'active_plugins', array() )
		);
	}

	/**
	 * Bir kaynak dosyasının ait olduğu aktif eklentinin giriş dosyası.
	 *
	 * Sınıfın bulunduğu dosya eklentinin GİRİŞ dosyası olmak zorunda değildir
	 * (ör. "rma-analytics/includes/class-analytics.php"); devre dışı bırakma
	 * bağlantısı ise girişi ister. Eşleştirme klasör adı üzerinden yapılır,
	 * tek dosyalık eklentiler için de tam yol karşılaştırılır.
	 *
	 * Saf fonksiyon (WordPress'e bağımlılığı yok), bu yüzden doğrudan test edilir.
	 *
	 * @param string   $goreli Eklentiler klasörüne göreli dosya yolu.
	 * @param string[] $aktif  active_plugins listesi.
	 * @return string Eşleşen giriş dosyası ya da boş string.
	 */
	public static function eklenti_dosyasini_bul( $goreli, array $aktif ) {
		$goreli = ltrim( str_replace( '\\', '/', (string) $goreli ), '/' );

		if ( '' === $goreli ) {
			return '';
		}

		$klasor = strtok( $goreli, '/' );

		foreach ( $aktif as $eklenti ) {
			$eklenti = (string) $eklenti;

			// Tek dosyalık eklenti: giriş dosyasının kendisi.
			if ( $eklenti === $goreli ) {
				return $eklenti;
			}

			// Klasörlü eklenti: aynı klasörden gelen her dosya ona aittir.
			if ( false !== strpos( $eklenti, '/' ) && strtok( $eklenti, '/' ) === $klasor ) {
				return $eklenti;
			}
		}

		return '';
	}

	/**
	 * Eski eklentinin görünen adı (bulunamazsa genel bir etiket).
	 *
	 * @return string
	 */
	public static function eski_eklenti_adi() {
		$dosya = self::eski_eklenti_dosyasi();

		if ( '' === $dosya ) {
			return __( 'RMA Analytics', 'qrms' );
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$veri = get_plugin_data( WP_PLUGIN_DIR . '/' . $dosya, false, false );
		$ad   = isset( $veri['Name'] ) ? (string) $veri['Name'] : '';

		return '' !== $ad ? $ad : __( 'RMA Analytics', 'qrms' );
	}

	/**
	 * Eski eklentiyi tek tıkla devre dışı bırakan adres ('' = üretilemedi).
	 *
	 * WordPress'in Eklentiler ekranındaki bağlantının aynısıdır: aynı action,
	 * aynı nonce. Yetkisi olmayan kullanıcıya hiç gösterilmez.
	 *
	 * @return string
	 */
	public static function eski_eklenti_kapat_url() {
		$dosya = self::eski_eklenti_dosyasi();

		if ( '' === $dosya || ! current_user_can( 'activate_plugins' ) ) {
			return '';
		}

		return wp_nonce_url(
			admin_url( 'plugins.php?action=deactivate&plugin=' . rawurlencode( $dosya ) . '&plugin_status=all' ),
			'deactivate-plugin_' . $dosya
		);
	}

	/* -----------------------------------------------------------------
	   TEŞHİS
	----------------------------------------------------------------- */

	/**
	 * Panelin "neden veri yok?" kutusunu besleyen bulgular.
	 *
	 * Sorun yoksa BOŞ dizi döner ve kutu hiç basılmaz. Sıra önem sırasıdır:
	 * ilk madde, kullanıcının önce çözmesi gereken şeydir.
	 *
	 * @return array<int,array{tip:string,baslik:string,mesaj:string,url:string,etiket:string}>
	 */
	public static function teshis() {
		global $wpdb;

		$bulgu = array();

		// 1) Eski eklenti izlemeyi tamamen durduruyor — diğer her şeyden önce.
		if ( self::eski_eklenti_aktif() ) {
			$bulgu[] = array(
				'tip'     => 'kritik',
				'baslik'  => sprintf(
					/* translators: %s: eklenti adı. */
					__( '"%s" eklentisi hâlâ etkin', 'qrms' ),
					self::eski_eklenti_adi()
				),
				'mesaj'   => __( 'Kayıtları o topluyor; çift sayım olmasın diye bu modül izlemeyi tamamen kapattı. Eski eklenti masa bilgisini yazmadığı için Masalar kategorisi de boş kalır. Masa bazlı takibin çalışması için eski eklentiyi devre dışı bırakın.', 'qrms' ),
				'url'     => self::eski_eklenti_kapat_url(),
				'etiket'  => __( 'Eski eklentiyi devre dışı bırak', 'qrms' ),
			);

			return $bulgu;
		}

		// 2) Tablo yoksa hiçbir sorgu çalışmaz.
		$tablo = self::tablo();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tablo ) ) ) {
			$bulgu[] = array(
				'tip'    => 'kritik',
				'baslik' => __( 'Analitik tablosu veritabanında yok', 'qrms' ),
				'mesaj'  => __( 'Tablo kurulamamış olabilir (veritabanı kullanıcısının tablo oluşturma yetkisi yoksa bu olur). Genel Ayarlar sayfasından lisansı yeniden doğrulayın; sorun sürerse hosting sağlayıcınıza danışın.', 'qrms' ),
				'url'    => admin_url( 'admin.php?page=' . QRMS_Admin::SETTINGS_SLUG ),
				'etiket' => __( 'Genel Ayarlar', 'qrms' ),
			);

			return $bulgu;
		}

		// İki sayaç aynı taramadan çıkar; ayrı ayrı sorulmasına gerek yok.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sayim = $wpdb->get_row(
			"SELECT COUNT(*) AS toplam, SUM(masa_no <> '') AS masali FROM {$tablo}",
			ARRAY_A
		);

		$toplam = is_array( $sayim ) ? (int) $sayim['toplam'] : 0;
		$masali = is_array( $sayim ) ? (int) $sayim['masali'] : 0;

		// 3) Hiç kayıt yok: izleme çalışıyor ama menü henüz açılmamış.
		if ( 0 === $toplam ) {
			$bulgu[] = array(
				'tip'    => 'bilgi',
				'baslik' => __( 'Henüz hiç kayıt yok', 'qrms' ),
				'mesaj'  => __( 'İzleme açık ama tabloya bir şey yazılmamış. Menü sayfanızı ön yüzden bir kez açın; ilk görüntüleme kaydı oluştuktan sonra bu sayfayı yenileyin.', 'qrms' ),
				'url'    => home_url( '/' ),
				'etiket' => __( 'Menüyü aç', 'qrms' ),
			);

			return $bulgu;
		}

		// 4) Kayıt var ama hiçbirinde masa yok. En sık sebep: QR adreslerindeki
		//    masa QR Masa modülünde KAYITLI DEĞİL. masa_belirle() kayıtsız
		//    slug'ı reddeder ve olay masasız yazılır — sessizce.
		if ( 0 === $masali ) {
			$kayitli = count( self::masa_adlari() );

			$bulgu[] = array(
				'tip'    => 'uyari',
				'baslik' => __( 'Kayıtların hiçbirinde masa bilgisi yok', 'qrms' ),
				'mesaj'  => 0 === $kayitli
					? __( 'QR Masa modülünde hiç masa tanımlı değil. Adreste ?masa=… olsa bile, kayıtlı olmayan bir masa güvenlik gereği yok sayılır ve hareket "Masasız" olarak yazılır. Önce masalarınızı oluşturun, sonra QR kodlarını o masalardan üretin.', 'qrms' )
					: __( 'Masalarınız tanımlı ama gelen hareketlerin hiçbiri bir masaya bağlanamadı. Müşterilerin okuttuğu QR kodların adresinde ?masa=… parametresi olduğundan ve slug\'ın QR Masa\'daki masayla birebir aynı yazıldığından emin olun.', 'qrms' ),
				'url'    => QRMS_Admin::get_module_page_url( 'qr-masa' ),
				'etiket' => __( 'QR Masa\'yı aç', 'qrms' ),
			);
		}

		return $bulgu;
	}

	/* -----------------------------------------------------------------
	   ŞEMA
	----------------------------------------------------------------- */

	/**
	 * Olay tablosunun tam adı.
	 *
	 * @return string
	 */
	public static function tablo() {
		global $wpdb;

		return $wpdb->prefix . 'rma_analytics';
	}

	/**
	 * Olay tablosu veritabanında var mı?
	 *
	 * Tablo, veritabanı kullanıcısının CREATE yetkisi yoksa hiç oluşmamış
	 * olabilir; temizlik görevi o kurulumlarda sessizce hiçbir şey yapmalı.
	 *
	 * @return bool
	 */
	public static function tablo_var_mi() {
		global $wpdb;

		$tablo = self::tablo();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tablo ) );
	}

	/**
	 * Şema güncel değilse dbDelta çalıştırır.
	 *
	 * Sürüm option'ı eşleştiğinde tek bir option okumasına iner; her istekte
	 * DESCRIBE çalıştırılmaz.
	 *
	 * @return void
	 */
	public static function sema_kontrol() {
		if ( self::DB_SURUM === get_option( self::DB_OPT ) ) {
			return;
		}

		self::tablo_kur();
		update_option( self::DB_OPT, self::DB_SURUM, false );
	}

	/**
	 * Tabloyu oluşturur veya eksik sütunlarını tamamlar.
	 *
	 * ÖNEMLİ: sorgu "CREATE TABLE IF NOT EXISTS" DEĞİLDİR. dbDelta tablo adını
	 * `CREATE TABLE ([^ ]*)` kalıbıyla okur; "IF NOT EXISTS" yazıldığında tablo
	 * adını "IF" sanır, mevcut şemayla karşılaştırma yapamaz ve masa_no sütunu
	 * eski kurulumlara hiç eklenmezdi.
	 *
	 * @return void
	 */
	public static function tablo_kur() {
		global $wpdb;

		$tablo   = self::tablo();
		$collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta(
			"CREATE TABLE {$tablo} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event_type varchar(30) NOT NULL,
				item_id bigint(20) unsigned NOT NULL DEFAULT 0,
				item_name varchar(255) NOT NULL DEFAULT '',
				category_name varchar(255) NOT NULL DEFAULT '',
				qty smallint(5) unsigned NOT NULL DEFAULT 1,
				masa_no varchar(64) NOT NULL DEFAULT '',
				ip_hash varchar(32) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY idx_type (event_type),
				KEY idx_item (item_id),
				KEY idx_date (created_at),
				KEY idx_td (event_type,created_at),
				KEY idx_masa (masa_no),
				KEY idx_masa_td (masa_no,event_type,created_at)
			) {$collate};"
		);
	}

	/* -----------------------------------------------------------------
	   TAKİP
	----------------------------------------------------------------- */

	/**
	 * Ziyaretçi IP'sinin tuzlanmış kısa hash'i.
	 *
	 * Ham IP hiçbir zaman saklanmaz. Hash biçimi eski eklentiyle birebir aynı
	 * tutuldu; böylece geçiş öncesi ve sonrası kayıtlar aynı ziyaretçi için
	 * aynı değeri üretir ve "tekil ziyaretçi" sayıları bölünmez.
	 *
	 * @return string
	 */
	private static function ip_hash() {
		$ham = '';

		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $anahtar ) {
			if ( ! empty( $_SERVER[ $anahtar ] ) ) {
				$ham = sanitize_text_field( wp_unslash( $_SERVER[ $anahtar ] ) );
				break;
			}
		}

		$ip   = trim( explode( ',', $ham )[0] );
		$tuz  = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'qrms_analitik';

		return substr( hash( 'sha256', $ip . $tuz ), 0, 32 );
	}

	/**
	 * Slug'ı normalize eder ve sütun uzunluğuna sığdırır.
	 *
	 * @param mixed $ham Ham değer.
	 * @return string
	 */
	private static function masa_temizle( $ham ) {
		if ( ! is_scalar( $ham ) ) {
			return '';
		}

		return substr( sanitize_title( (string) $ham ), 0, self::MASA_UZUNLUK );
	}

	/**
	 * Masa slug'ı gerçekten kayıtlı mı?
	 *
	 * qr-masa modülü kurulu değilse doğrulayacak bir kaynak yoktur; o durumda
	 * slug olduğu gibi kabul edilir (sanitize_title'dan geçmiştir).
	 *
	 * @param string $masa Masa slug'ı.
	 * @return bool
	 */
	private static function masa_gecerli( $masa ) {
		if ( '' === $masa ) {
			return false;
		}

		if ( function_exists( 'qmo_masa_gecerli_mi' ) ) {
			return (bool) qmo_masa_gecerli_mi( $masa );
		}

		return true;
	}

	/**
	 * İsteğin ait olduğu masa slug'ı (bulunamazsa boş string).
	 *
	 * Sıra bilinçlidir: istekle gelen değer en tazesidir, imzalı çerez en
	 * güvenilirdir, referer ise yalnızca ikisi de yoksa devreye girer.
	 *
	 * @return string
	 */
	private static function masa_belirle() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- İzleme yalnızca okur; nonce kontrolünü asıl uç yapar.
		$istek = isset( $_POST['masa'] ) ? self::masa_temizle( wp_unslash( $_POST['masa'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( '' !== $istek && self::masa_gecerli( $istek ) ) {
			return $istek;
		}

		if ( function_exists( 'qmo_oturum' ) ) {
			$oturum = qmo_oturum();

			if ( is_array( $oturum ) && ! empty( $oturum['masa'] ) ) {
				return self::masa_temizle( $oturum['masa'] );
			}
		}

		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';

		if ( '' !== $referer ) {
			$sorgu = wp_parse_url( $referer, PHP_URL_QUERY );

			if ( is_string( $sorgu ) && '' !== $sorgu ) {
				$parcalar = array();
				wp_parse_str( $sorgu, $parcalar );

				if ( ! empty( $parcalar['masa'] ) ) {
					$aday = self::masa_temizle( $parcalar['masa'] );

					if ( self::masa_gecerli( $aday ) ) {
						return $aday;
					}
				}
			}
		}

		return '';
	}

	/**
	 * Bir olayı tabloya yazar.
	 *
	 * TEK yazım yolu: chatbot sipariş/sepet/çağrı uçları da burayı çağırır;
	 * $wpdb->insert başka yerde açılmaz. Sınıf yalnızca qr-analiz lisansta
	 * aktifken yüklendiği için çağıran `class_exists( 'QRMS_Analitik' )`
	 * ile bakar — yoksa sessizce hiç gelinmez.
	 *
	 * Insert başarısız olursa yutulur: analitik, sipariş/sepet akışını
	 * kesmesin ve kullanıcıya hata basılmasın.
	 *
	 * @param array $satir Sütun => değer.
	 * @return void
	 */
	public static function kaydet( array $satir ) {
		global $wpdb;

		try {
			$varsayilan = array(
				'event_type'    => '',
				'item_id'       => 0,
				'item_name'     => '',
				'category_name' => '',
				// Sipariş kalemlerinin ADEDİ. Diğer olaylarda 1 kalır;
				// menü mühendisliği raporu satış adedini buradan okur
				// (adetsiz hesaplanan popülerlik yanlış sonuç verir).
				'qty'           => 1,
				'masa_no'       => self::masa_belirle(),
				'ip_hash'       => self::ip_hash(),
				'created_at'    => current_time( 'mysql' ),
			);

			$satir = array_merge( $varsayilan, $satir );

			if ( isset( $satir['event_type'] ) ) {
				$satir['event_type'] = substr( sanitize_key( (string) $satir['event_type'] ), 0, 30 );
			}

			if ( isset( $satir['item_id'] ) ) {
				$satir['item_id'] = absint( $satir['item_id'] );
			}

			if ( isset( $satir['item_name'] ) ) {
				$satir['item_name'] = substr( sanitize_text_field( (string) $satir['item_name'] ), 0, 255 );
			}

			if ( isset( $satir['category_name'] ) ) {
				$satir['category_name'] = substr( sanitize_text_field( (string) $satir['category_name'] ), 0, 255 );
			}

			if ( isset( $satir['qty'] ) ) {
				// smallint unsigned: 1–999 aralığına sıkıştırılır. Sıfır ya da
				// negatif adet raporda ürünü görünmez yapardı.
				$satir['qty'] = max( 1, min( 999, absint( $satir['qty'] ) ) );
			}

			if ( isset( $satir['masa_no'] ) ) {
				$satir['masa_no'] = self::masa_temizle( $satir['masa_no'] );
			}

			/*
			 * Biçim dizisi $varsayilan'ın ANAHTAR SIRASINI izler
			 * (array_merge sırayı korur): event_type, item_id, item_name,
			 * category_name, qty, masa_no, ip_hash, created_at.
			 */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert(
				self::tablo(),
				$satir,
				array( '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
			);
		} catch ( Exception $e ) {
			return;
		}
	}

	/**
	 * İstek gerçekten menü arayüzünden mi geliyor?
	 *
	 * Eskiden yalnızca "security alanı boş mu?" diye bakılıyordu; alanın
	 * DEĞERİ hiç doğrulanmadığı için `security=x` göndermek yeterliydi ve
	 * tabloya kimlik doğrulaması olmadan sınırsız satır eklenebiliyordu.
	 * Artık nonce gerçekten doğrulanır.
	 *
	 * Doğrulama başarısızsa istek REDDEDİLMEZ, yalnızca kayıt atlanır: asıl
	 * uçlar (rma_load_items / rma_get_product_details) nonce'u bilinçli olarak
	 * "yumuşak" kontrol ediyor, çünkü önbelleklenmiş bir sayfada nonce
	 * eskimiş olabilir. Menünün çalışmaya devam etmesi, o menü açılışının
	 * sayılmasından önemlidir.
	 *
	 * Saf bir istek incelemesidir ($wpdb'ye dokunmaz), bu yüzden doğrudan
	 * test edilir.
	 *
	 * @return bool
	 */
	public static function izleme_gecerli_mi() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : '';

		return '' !== $nonce && (bool) wp_verify_nonce( $nonce, self::NONCE_TAKIP );
	}

	/**
	 * Menü listesi isteği: bir görüntüleme kaydı.
	 *
	 * @return void
	 */
	public static function izle_menu_goruntuleme() {
		if ( ! self::izleme_gecerli_mi() ) {
			return;
		}

		self::kaydet( array( 'event_type' => 'menu_view' ) );
	}

	/**
	 * Ürün detayı isteği: bir tıklama kaydı.
	 *
	 * Sessiz ön yüklemeler (prefetch=1) gerçek bir tıklama değildir; menü JS'i
	 * komşu kartları arka planda ısıtırken bu uca uğrar ve sayılırsa her ürün
	 * kendiliğinden popülerleşirdi.
	 *
	 * @return void
	 */
	public static function izle_urun_tiklama() {
		if ( ! self::izleme_gecerli_mi() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['prefetch'] ) ) {
			return;
		}

		$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! $id ) {
			return;
		}

		$terimler = wp_get_post_terms( $id, 'rma_category' );
		$kategori = ( ! is_wp_error( $terimler ) && ! empty( $terimler ) ) ? (string) $terimler[0]->name : '';

		self::kaydet(
			array(
				'event_type'    => 'product_click',
				'item_id'       => $id,
				'item_name'     => (string) get_the_title( $id ),
				'category_name' => $kategori,
			)
		);
	}

	/* -----------------------------------------------------------------
	   ÖN YÜZ BEACON (splash / dil / galeri / detay modalı)
	----------------------------------------------------------------- */

	/**
	 * Beacon'ın kabul ettiği olaylar.
	 *
	 * `modul` doluysa o modül lisansta pasifken yazım atlanır (splash
	 * JS'i zaten yüklenmez; bu ikinci kilit, elle POST'u da keser).
	 * `item_name` bir dizi ise istemcinin gönderdiği ad o listede
	 * olmak zorundadır — serbest metin kabul edilmez (PII / enjeksiyon).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function onyuz_olay_kurallari() {
		$diller = array( 'tr', 'en', 'ar', 'de', 'fr', 'ru' );

		if ( function_exists( 'qrmenu_get_langs' ) ) {
			$katalog = qrmenu_get_langs();

			if ( is_array( $katalog ) && ! empty( $katalog ) ) {
				$diller = array_map( 'strval', array_keys( $katalog ) );
			}
		}

		return array(
			'lang_switch'      => array(
				'modul'     => 'qr-ceviri',
				'item_name' => $diller,
				'hiz'       => 1,
			),
			'splash_view'      => array(
				'modul' => 'qr-acilis-ekrani',
				'hiz'   => 10,
			),
			'splash_action'    => array(
				'modul'     => 'qr-acilis-ekrani',
				'item_name' => array( 'menu', 'atla', 'wifi', 'sosyal', 'odeme', 'rezervasyon', 'yorum' ),
				'hiz'       => 1,
			),
			'gallery_view'     => array(
				'modul' => 'qr-galeri',
				'hiz'   => 10,
			),
			'item_detail_open' => array(
				'modul'   => '',
				'item_id' => true,
				'hiz'     => 0,
			),
		);
	}

	/**
	 * Ön yüz beacon betiği.
	 *
	 * Küçük bir dosya; qr-analiz aktifken her ön yüz sayfasında durur ki
	 * splash / dil seçici / galeri onu var diye baksın. Pasifse bu sınıf
	 * yüklenmez, betik de kuyruğa girmez.
	 *
	 * @return void
	 */
	public static function onyuz_varlik() {
		if ( is_admin() ) {
			return;
		}

		$handle = 'qrms-analitik-onyuz';

		wp_enqueue_script(
			$handle,
			QRMS_PLUGIN_URL . 'modules/qr-analiz/assets/js/analitik-onyuz.js',
			array(),
			QRMS_Helpers::asset_version( 'modules/qr-analiz/assets/js/analitik-onyuz.js' ),
			true
		);

		wp_localize_script(
			$handle,
			'qrmsAnalitikOnyuzCfg',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ONYUZ ),
			)
		);
	}

	/**
	 * Beacon AJAX ucu.
	 *
	 * Yanıt her zaman success'tir (akışı kesmemek için). Yazılamayan olay
	 * atlanır; kullanıcıya hata basılmaz. Masa POST'tan okunmaz.
	 *
	 * @return void
	 */
	public static function ajax_onyuz() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ONYUZ ) ) {
			wp_send_json_success();
		}

		$tip     = isset( $_POST['tip'] ) ? sanitize_key( wp_unslash( $_POST['tip'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$kurallar = self::onyuz_olay_kurallari();

		if ( '' === $tip || ! isset( $kurallar[ $tip ] ) ) {
			wp_send_json_success();
		}

		$kural = $kurallar[ $tip ];

		if ( ! empty( $kural['modul'] ) && class_exists( 'QRMS_Module_Loader' ) && ! QRMS_Module_Loader::is_module_active( $kural['modul'] ) ) {
			wp_send_json_success();
		}

		$masa = self::masa_onyuz();

		if ( function_exists( 'qmo_hiz_siniri' ) ) {
			$saniye = isset( $kural['hiz'] ) ? (int) $kural['hiz'] : 2;

			if ( $saniye > 0 && ! qmo_hiz_siniri( 'an_' . $tip, $masa, $saniye ) ) {
				wp_send_json_success();
			}
		}

		$satir = array(
			'event_type' => $tip,
			'masa_no'    => $masa,
		);

		if ( isset( $kural['item_name'] ) && is_array( $kural['item_name'] ) ) {
			$ad = isset( $_POST['item_name'] ) ? sanitize_text_field( wp_unslash( $_POST['item_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$ad = strtolower( $ad );
			$eslesen = '';

			foreach ( $kural['item_name'] as $izinli ) {
				if ( strtolower( (string) $izinli ) === $ad ) {
					$eslesen = (string) $izinli;
					break;
				}
			}

			if ( '' === $eslesen ) {
				wp_send_json_success();
			}

			$satir['item_name'] = $eslesen;
		}

		if ( ! empty( $kural['item_id'] ) ) {
			$id   = isset( $_POST['item_id'] ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$alan = function_exists( 'qmo_analitik_urun_alani' ) ? qmo_analitik_urun_alani( $id ) : array();

			if ( empty( $alan ) ) {
				wp_send_json_success();
			}

			$satir['item_id']       = $alan['item_id'];
			$satir['item_name']     = $alan['item_name'];
			$satir['category_name'] = $alan['category_name'];
		}

		self::kaydet( $satir );
		wp_send_json_success();
	}

	/**
	 * Beacon için masa: oturum çerezi, yoksa referer. POST['masa'] yok sayılır.
	 *
	 * Sepet ucuyla aynı gerekçe: istemci başka masaya olay yazamasın.
	 *
	 * @return string
	 */
	private static function masa_onyuz() {
		if ( function_exists( 'qmo_oturum' ) ) {
			$oturum = qmo_oturum();

			if ( is_array( $oturum ) && ! empty( $oturum['masa'] ) ) {
				$masa = self::masa_temizle( $oturum['masa'] );

				if ( self::masa_gecerli( $masa ) ) {
					return $masa;
				}
			}
		}

		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';

		if ( '' !== $referer ) {
			$sorgu = wp_parse_url( $referer, PHP_URL_QUERY );

			if ( is_string( $sorgu ) && '' !== $sorgu ) {
				$parcalar = array();
				wp_parse_str( $sorgu, $parcalar );

				if ( ! empty( $parcalar['masa'] ) ) {
					$aday = self::masa_temizle( $parcalar['masa'] );

					if ( self::masa_gecerli( $aday ) ) {
						return $aday;
					}
				}
			}
		}

		return '';
	}

	/* -----------------------------------------------------------------
	   SORGU YARDIMCILARI
	----------------------------------------------------------------- */

	/**
	 * Masa filtresinin SQL parçası (hazır biçimde, boşsa boş string).
	 *
	 * Parça $wpdb->prepare ile ÜRETİLİR ve sorguya olduğu gibi eklenir; bütün
	 * sorgu ikinci kez prepare'den geçirilmez (aksi hâlde DATE_FORMAT içindeki
	 * % işaretleri kaçırılmak zorunda kalırdı).
	 *
	 * @param string $masa Masa slug'ı.
	 * @return string
	 */
	private static function masa_sql( $masa ) {
		global $wpdb;

		if ( '' === $masa ) {
			return '';
		}

		return $wpdb->prepare( ' AND masa_no = %s', $masa );
	}

	/**
	 * Dönemin başlangıç zaman damgası (MySQL biçiminde).
	 *
	 * @param string $donem Dönem anahtarı.
	 * @return string
	 */
	private static function donem_baslangici( $donem ) {
		switch ( $donem ) {
			case 'hourly':
				return current_time( 'Y-m-d' ) . ' 00:00:00';

			case 'weekly':
				// 12 TAM ISO haftası: en eski kova pazartesiden başlar. Sabit
				// "-83 gün" bugün haftanın ortasındaysa en eski haftayı yarım
				// keserdi ve o haftanın sayıları olduğundan düşük görünürdü.
				return gmdate( 'Y-m-d', self::hafta_basi( strtotime( '-11 weeks', self::simdi() ) ) ) . ' 00:00:00';

			case 'monthly':
				return gmdate( 'Y-m-01', strtotime( '-11 months', self::simdi() ) ) . ' 00:00:00';

			case 'masalar':
				return gmdate( 'Y-m-d', strtotime( '-' . ( self::MASA_GUN - 1 ) . ' days', self::simdi() ) ) . ' 00:00:00';

			case 'daily':
			default:
				return gmdate( 'Y-m-d', strtotime( '-29 days', self::simdi() ) ) . ' 00:00:00';
		}
	}

	/**
	 * Verilen anın içinde bulunduğu ISO haftasının pazartesi 00:00'ı.
	 *
	 * MySQL tarafında haftalar YEARWEEK(created_at,1) ile gruplanır; o mod da
	 * ISO-8601'dir (hafta pazartesi başlar). PHP'nin "monday this week" ifadesi
	 * aynı tanımı kullanır, böylece iki taraf aynı kovayı gösterir.
	 *
	 * @param int $ts Unix zaman damgası.
	 * @return int
	 */
	private static function hafta_basi( $ts ) {
		return (int) strtotime( 'monday this week', (int) $ts );
	}

	/**
	 * Sitenin yerel saatiyle "şimdi" (unix damgası).
	 *
	 * Kayıtlar current_time('mysql') ile yazıldığı için tarih aralıkları da
	 * yerel saatle hesaplanmalıdır.
	 *
	 * @return int
	 */
	private static function simdi() {
		return (int) current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
	}

	/* -----------------------------------------------------------------
	   RAPOR SORGULARI
	----------------------------------------------------------------- */

	/**
	 * Üst kartların verisi.
	 *
	 * @param string $masa Masa filtresi (boş = tüm masalar).
	 * @return array<string,int>
	 */
	public static function genel_bakis( $masa = '' ) {
		$cache_key = (string) $masa;
		if ( isset( self::$genel_bakis_onbellegi[ $cache_key ] ) ) {
			return self::$genel_bakis_onbellegi[ $cache_key ];
		}

		global $wpdb;

		$tablo   = self::tablo();
		$masa_ek = self::masa_sql( $masa );
		$bugun   = current_time( 'Y-m-d' );

		$aralik = $wpdb->prepare( 'created_at BETWEEN %s AND %s', $bugun . ' 00:00:00', $bugun . ' 23:59:59' );
		$hafta  = $wpdb->prepare( 'created_at >= %s', gmdate( 'Y-m-d', strtotime( '-6 days', self::simdi() ) ) . ' 00:00:00' );
		$ay     = $wpdb->prepare( 'created_at >= %s', gmdate( 'Y-m-01', self::simdi() ) . ' 00:00:00' );

		/*
		 * SEKİZ ayrı COUNT sorgusu yerine İKİ sorgu — ve ikisi de indeksli.
		 *
		 * Ara bir sürümde bunlar tek sorguya indirilmişti; bağlantı sayısı
		 * açısından doğruydu ama SORGU SÜRESİ açısından geriye gidişti: WHERE
		 * kalmayınca MySQL 90 günlük tablonun tamamını satır satır taramak
		 * zorunda kalıyordu. Oysa sekiz kovadan yedisi tarih sınırlıdır ve
		 * idx_date / idx_td üzerinden dar bir aralıkla karşılanabilir.
		 *
		 * Bölünme bu yüzden şöyle:
		 *   1) Tarihli yedi kova, ortak alt sınırı olan TEK bir aralık
		 *      taramasında toplanır (koşullar SUM/COUNT DISTINCT + CASE'e taşınır).
		 *   2) Tarih sınırı olmayan tek kova (pc_tumu) ayrı kalır; idx_type
		 *      üzerinden index-only sayım yapar, satırlara hiç inmez.
		 *
		 * Alt sınır min(ay başı, hafta başı)'dır: ayın ilk günlerinde "son 7
		 * gün" penceresi önceki aya taşar, sabit olarak ay başı alınsaydı o
		 * günler sayımdan düşerdi.
		 */
		$alt_sinir = min(
			gmdate( 'Y-m-01', self::simdi() ),
			gmdate( 'Y-m-d', strtotime( '-6 days', self::simdi() ) )
		) . ' 00:00:00';

		$sinir_kosul = $wpdb->prepare( 'created_at >= %s', $alt_sinir );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$satir = $wpdb->get_row(
			"SELECT
				SUM(event_type='menu_view'     AND {$aralik}) AS mv_bugun,
				SUM(event_type='menu_view'     AND {$hafta})  AS mv_hafta,
				SUM(event_type='menu_view'     AND {$ay})     AS mv_ay,
				SUM(event_type='product_click' AND {$aralik}) AS pc_bugun,
				SUM(event_type='product_click' AND {$hafta})  AS pc_hafta,
				COUNT(DISTINCT CASE WHEN event_type='menu_view' AND {$aralik} THEN ip_hash END) AS uv_bugun,
				COUNT(DISTINCT CASE WHEN masa_no <> '' AND {$aralik} THEN masa_no END)          AS masa_gun
			 FROM {$tablo}
			 WHERE {$sinir_kosul}{$masa_ek}",
			ARRAY_A
		);

		$pc_tumu = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$tablo} WHERE event_type='product_click'{$masa_ek}"
		);
		// phpcs:enable

		$sonuc = array(
			'mv_bugun' => 0,
			'mv_hafta' => 0,
			'mv_ay'    => 0,
			'pc_bugun' => 0,
			'pc_hafta' => 0,
			'pc_tumu'  => $pc_tumu,
			'uv_bugun' => 0,
			'masa_gun' => 0,
		);

		if ( ! is_array( $satir ) ) {
			self::$genel_bakis_onbellegi[ $cache_key ] = $sonuc;
			return $sonuc;
		}

		// Aralıkta hiç satır yoksa SUM() NULL döner; (int) hepsini sıfıra indirir.
		foreach ( $sonuc as $anahtar => $varsayilan ) {
			if ( 'pc_tumu' === $anahtar ) {
				continue;
			}

			$sonuc[ $anahtar ] = isset( $satir[ $anahtar ] ) ? (int) $satir[ $anahtar ] : 0;
		}

		self::$genel_bakis_onbellegi[ $cache_key ] = $sonuc;
		return $sonuc;
	}

	/**
	 * genel_bakis() istek içi önbelleğini boşaltır.
	 *
	 * Testler aynı süreçte farklı $wpdb cevapları besler; üretimde istek
	 * bitince süreç de biter, bu metodun çağrılmasına gerek yoktur.
	 *
	 * @return void
	 */
	public static function genel_bakis_onbellegini_temizle() {
		self::$genel_bakis_onbellegi = array();
		self::$sepet_grup_onbellegi  = array();
	}

	/**
	 * Sepet olay gruplarının istek içi önbelleğini boşaltır.
	 *
	 * @return void
	 */
	public static function sepet_onbellegini_temizle() {
		self::$sepet_grup_onbellegi = array();
	}

	/**
	 * KEYFİ ARALIK ÖZETİ — Genel Bakış kategorisinin dört kartı.
	 *
	 * genel_bakis() sabit kovalar (bugün/hafta/ay) döndürür ve hub'ın özet
	 * şeridini besler; burada ise aralığın uçlarını çağıran belirler.
	 *
	 * TEK SORGU, TEK ARALIK TARAMASI. Yedi sayaç ayrı ayrı sorulsaydı yedi
	 * tarama olurdu; oysa hepsi aynı [önceki_bas .. bit] penceresinden çıkar.
	 * Koşullar bu yüzden WHERE'den SUM/CASE içine taşınır — genel_bakis()'teki
	 * bölünmenin (bkz. o metodun uzun yorumu) aynı mantığı. Pencere kapalı ve
	 * alt sınırlı olduğu için MySQL created_at üzerindeki idx_date'i (masa
	 * filtresi varsa idx_masa_td'yi) aralık taraması olarak kullanabilir;
	 * WHERE'siz bir sorgu tabloyu satır satır tarardı.
	 *
	 * Önceki pencere karşılaştırma içindir: "bugün 120 okutma" tek başına iyi
	 * mi kötü mü söylemez, "düne göre %18 artış" söyler.
	 *
	 * @param string $bas         Aralık başlangıcı (MySQL biçimi).
	 * @param string $bit         Aralık bitişi (MySQL biçimi).
	 * @param string $onceki_bas  Karşılaştırma penceresinin başlangıcı.
	 * @param string $masa        Masa filtresi (boş = tüm masalar).
	 * @return array<string,int>
	 */
	public static function aralik_ozeti( $bas, $bit, $onceki_bas, $masa = '' ) {
		global $wpdb;

		$tablo   = self::tablo();
		$masa_ek = self::masa_sql( $masa );

		$pencere = $wpdb->prepare( 'created_at BETWEEN %s AND %s', $onceki_bas, $bit );
		$simdiki = $wpdb->prepare( 'created_at >= %s', $bas );
		$eski    = $wpdb->prepare( 'created_at < %s', $bas );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$satir = $wpdb->get_row(
			"SELECT
				SUM(event_type='menu_view'     AND {$simdiki}) AS mv,
				SUM(event_type='product_click' AND {$simdiki}) AS pc,
				COUNT(DISTINCT CASE WHEN event_type='menu_view' AND {$simdiki} THEN ip_hash END) AS uv,
				COUNT(DISTINCT CASE WHEN masa_no <> ''         AND {$simdiki} THEN masa_no END) AS masa_sayisi,
				SUM(event_type='menu_view'     AND {$eski})    AS mv_onceki,
				SUM(event_type='product_click' AND {$eski})    AS pc_onceki,
				COUNT(DISTINCT CASE WHEN event_type='menu_view' AND {$eski} THEN ip_hash END) AS uv_onceki
			 FROM {$tablo}
			 WHERE {$pencere}{$masa_ek}",
			ARRAY_A
		);
		// phpcs:enable

		$sonuc = array(
			'mv'          => 0,
			'pc'          => 0,
			'uv'          => 0,
			'masa_sayisi' => 0,
			'mv_onceki'   => 0,
			'pc_onceki'   => 0,
			'uv_onceki'   => 0,
		);

		if ( ! is_array( $satir ) ) {
			return $sonuc;
		}

		// Pencerede hiç satır yoksa SUM() NULL döner; (int) hepsini sıfırlar.
		foreach ( $sonuc as $anahtar => $varsayilan ) {
			$sonuc[ $anahtar ] = isset( $satir[ $anahtar ] ) ? (int) $satir[ $anahtar ] : 0;
		}

		return $sonuc;
	}

	/**
	 * Grafik/tablo satırları: saatlik, günlük, haftalık, aylık — KEYFİ aralık.
	 *
	 * Aralığın uçlarını çağıran verir, kırılım yalnızca GRUPLAMAYI belirler.
	 * Her dalda tek bir gruplu sorgu çalışır ve sonuç sıfır doldurulur: yalnızca
	 * veri olan kovalar döndürülürse sessiz geçen bir gün/hafta grafikten
	 * tamamen kaybolur ve x ekseni kesintisizmiş gibi görünürdü.
	 *
	 * @param string $kirilim Gruplama: hourly | daily | weekly | monthly.
	 * @param string $bas     Aralık başlangıcı (MySQL biçimi).
	 * @param string $bit     Aralık bitişi (MySQL biçimi).
	 * @param string $masa    Masa filtresi.
	 * @return array<int,array<string,mixed>>
	 */
	public static function grafik_araligi( $kirilim, $bas, $bit, $masa = '' ) {
		global $wpdb;

		$tablo   = self::tablo();
		$masa_ek = self::masa_sql( $masa );

		$toplam = "SUM(event_type='menu_view') AS mv,
			SUM(event_type='product_click') AS pc,
			COUNT(DISTINCT CASE WHEN event_type='menu_view' THEN ip_hash END) AS uv";

		// Aralık KAPALI ve iki uçtan sınırlı: idx_date (masa filtresi varsa
		// idx_masa_td) aralık taraması olarak kullanılabilir.
		$kosul = $wpdb->prepare( 'created_at BETWEEN %s AND %s', $bas, $bit );

		$bas_ts = (int) strtotime( $bas );
		$bit_ts = (int) strtotime( $bit );

		switch ( $kirilim ) {
			case 'hourly':
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$ham = $wpdb->get_results(
					"SELECT HOUR(created_at) AS k, {$toplam} FROM {$tablo}
					 WHERE {$kosul}{$masa_ek}
					 GROUP BY HOUR(created_at) ORDER BY HOUR(created_at)",
					ARRAY_A
				);

				$harita = self::haritala( $ham );
				$satir  = array();

				for ( $s = 0; $s < 24; $s++ ) {
					$satir[] = self::satir( sprintf( '%02d:00', $s ), $harita, $s );
				}

				return $satir;

			case 'weekly':
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$ham = $wpdb->get_results(
					"SELECT YEARWEEK(created_at,1) AS k, {$toplam} FROM {$tablo}
					 WHERE {$kosul}{$masa_ek}
					 GROUP BY YEARWEEK(created_at,1) ORDER BY YEARWEEK(created_at,1)",
					ARRAY_A
				);

				$harita = self::haritala( $ham );
				$satir  = array();

				for ( $ts = self::hafta_basi( $bas_ts ); $ts <= $bit_ts; $ts = strtotime( '+1 week', $ts ) ) {
					$satir[] = self::satir(
						date_i18n( 'j M', $ts ) . '–' . date_i18n( 'j M', strtotime( '+6 days', $ts ) ),
						$harita,
						gmdate( 'oW', $ts )
					);
				}

				return $satir;

			case 'monthly':
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$ham = $wpdb->get_results(
					"SELECT DATE_FORMAT(created_at,'%Y-%m') AS k, {$toplam} FROM {$tablo}
					 WHERE {$kosul}{$masa_ek}
					 GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY DATE_FORMAT(created_at,'%Y-%m')",
					ARRAY_A
				);

				$harita = self::haritala( $ham );
				$satir  = array();

				for ( $ts = (int) strtotime( gmdate( 'Y-m-01', $bas_ts ) ); $ts <= $bit_ts; $ts = strtotime( '+1 month', $ts ) ) {
					$satir[] = self::satir( date_i18n( 'M Y', $ts ), $harita, gmdate( 'Y-m', $ts ) );
				}

				return $satir;

			case 'daily':
			default:
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$ham = $wpdb->get_results(
					"SELECT DATE(created_at) AS k, {$toplam} FROM {$tablo}
					 WHERE {$kosul}{$masa_ek}
					 GROUP BY DATE(created_at) ORDER BY DATE(created_at)",
					ARRAY_A
				);

				$harita = self::haritala( $ham );
				$satir  = array();

				for ( $ts = $bas_ts; $ts <= $bit_ts; $ts = strtotime( '+1 day', $ts ) ) {
					$satir[] = self::satir( date_i18n( 'j M', $ts ), $harita, gmdate( 'Y-m-d', $ts ) );
				}

				return $satir;
		}
	}

	/**
	 * Grafik/tablo satırları — ESKİ, sabit pencereli imza.
	 *
	 * Dönem anahtarı kendi penceresini de taşır (saatlik=bugün, günlük=son 30
	 * gün, haftalık=son 12 hafta, aylık=son 12 ay). Sorgular ve sıfır doldurma
	 * grafik_araligi() içinde tek yerde durur; burada yalnızca pencere seçilir.
	 *
	 * @param string $donem Dönem anahtarı.
	 * @param string $masa  Masa filtresi.
	 * @return array<int,array<string,mixed>>
	 */
	public static function grafik_verisi( $donem, $masa = '' ) {
		$kirilim = in_array( $donem, array( 'hourly', 'weekly', 'monthly' ), true ) ? $donem : 'daily';
		$bit     = current_time( 'Y-m-d' ) . ' 23:59:59';

		return self::grafik_araligi( $kirilim, self::donem_baslangici( $donem ), $bit, $masa );
	}

	/**
	 * Sorgu sonucunu "k" sütunu anahtar olacak biçimde diziye çevirir.
	 *
	 * @param mixed $ham Sorgu sonucu.
	 * @return array<string,array>
	 */
	private static function haritala( $ham ) {
		$harita = array();

		foreach ( (array) $ham as $r ) {
			$harita[ (string) $r['k'] ] = $r;
		}

		return $harita;
	}

	/**
	 * Haritadan tek bir grafik satırı üretir (kayıt yoksa sıfırlarla).
	 *
	 * @param string $etiket  Görünen etiket.
	 * @param array  $harita  haritala() çıktısı.
	 * @param mixed  $anahtar Aranan anahtar.
	 * @return array<string,mixed>
	 */
	private static function satir( $etiket, array $harita, $anahtar ) {
		$k = (string) $anahtar;

		return array(
			'label' => $etiket,
			'mv'    => isset( $harita[ $k ] ) ? (int) $harita[ $k ]['mv'] : 0,
			'pc'    => isset( $harita[ $k ] ) ? (int) $harita[ $k ]['pc'] : 0,
			'uv'    => isset( $harita[ $k ] ) ? (int) $harita[ $k ]['uv'] : 0,
		);
	}

	/**
	 * "Masalara Göre" satırları: son 30 günde hangi masadan kaç hareket geldi.
	 *
	 * @param string $masa Masa filtresi (boş = tüm masalar).
	 * @return array<int,array<string,mixed>>
	 */
	public static function masa_verisi( $masa = '' ) {
		$bit = current_time( 'Y-m-d' ) . ' 23:59:59';

		$satir = array();

		foreach ( self::masa_sayaclari( self::donem_baslangici( 'masalar' ), $bit, $masa ) as $slug => $r ) {
			$satir[] = array(
				'masa'  => $slug,
				'label' => self::masa_etiketi( $slug, self::masa_adlari() ),
				'mv'    => $r['mv'],
				'pc'    => $r['pc'],
				'uv'    => $r['uv'],
				'son'   => $r['son'],
			);
		}

		return $satir;
	}

	/**
	 * Masa başına ham sayaçlar (masa_no => satır) — KEYFİ aralık.
	 *
	 * "Masalar" kategorisi iki kaynağı birleştirir: kayıtlı masalar (qr-masa
	 * modülünün tablosu) ve bu sayaçlar. Masa başına ayrı sorgu N+1 demek
	 * olurdu; burada TEK bir GROUP BY ile hepsi çekilir, eşleştirme PHP
	 * tarafında yapılır (bkz. masalar-sayfasi.php).
	 *
	 * Anahtar masa_no'dur; boş anahtar ('') masasız — yani doğrudan, QR
	 * okutmadan gelen — hareketleri temsil eder ve listeden DÜŞÜRÜLMEZ.
	 *
	 * Sıralama toplam harekete göre azalandır: çağıranın yeniden sıralaması
	 * gerekmediği sürece liste anlamlı bir düzende gelir.
	 *
	 * @param string $bas  Aralık başlangıcı (MySQL biçimi).
	 * @param string $bit  Aralık bitişi (MySQL biçimi).
	 * @param string $masa Masa filtresi (boş = tüm masalar).
	 * @return array<string,array{mv:int,pc:int,uv:int,son:string}>
	 */
	public static function masa_sayaclari( $bas, $bit, $masa = '' ) {
		global $wpdb;

		$tablo   = self::tablo();
		$masa_ek = self::masa_sql( $masa );

		// Aralık iki uçtan sınırlı; masa filtresi varsa idx_masa_td
		// (masa_no, event_type, created_at) doğrudan kullanılabilir.
		$kosul = $wpdb->prepare( 'created_at BETWEEN %s AND %s', $bas, $bit );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ham = $wpdb->get_results(
			"SELECT masa_no,
				SUM(event_type='menu_view') AS mv,
				SUM(event_type='product_click') AS pc,
				COUNT(DISTINCT CASE WHEN event_type='menu_view' THEN ip_hash END) AS uv,
				MAX(created_at) AS son
			 FROM {$tablo}
			 WHERE {$kosul}{$masa_ek}
			 GROUP BY masa_no
			 ORDER BY (SUM(event_type='menu_view') + SUM(event_type='product_click')) DESC",
			ARRAY_A
		);

		$sayac = array();

		foreach ( (array) $ham as $r ) {
			$sayac[ (string) $r['masa_no'] ] = array(
				'mv'  => (int) $r['mv'],
				'pc'  => (int) $r['pc'],
				'uv'  => (int) $r['uv'],
				'son' => (string) $r['son'],
			);
		}

		return $sayac;
	}

	/**
	 * Masa slug'ı => görünen ad (qr-masa modülünün kayıtlarından).
	 *
	 * @return array<string,string>
	 */
	public static function masa_adlari() {
		global $wpdb;

		$adlar = wp_cache_get( 'qrms_analitik_masa_adlari', 'qrms' );

		if ( is_array( $adlar ) ) {
			return $adlar;
		}

		$adlar = array();

		// Kayıtlı masaların TEK KAYNAĞI qr-masa modülüdür; tabloyu burada
		// ikinci kez tanımlamak, ileride şeması değiştiğinde iki yerin
		// birbirinden habersiz kalması demek olurdu. Modül kurulu değilse
		// (ya da tablosu yoksa) doğrudan sorguya düşülür: analitik ekranı,
		// masa modülü olmayan bir kurulumda da çalışmalı.
		if ( class_exists( 'QMO_Masalar' ) && QMO_Masalar::tablo_var_mi() ) {
			foreach ( (array) QMO_Masalar::hepsi() as $masa ) {
				$adlar[ (string) $masa->table_slug ] = (string) $masa->table_name;
			}

			wp_cache_set( 'qrms_analitik_masa_adlari', $adlar, 'qrms', 300 );

			return $adlar;
		}

		$tablo = $wpdb->prefix . 'qrm_tables';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$var = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tablo ) );

		if ( $var ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$satirlar = $wpdb->get_results( "SELECT table_slug, table_name FROM {$tablo}", ARRAY_A );

			foreach ( (array) $satirlar as $r ) {
				$adlar[ (string) $r['table_slug'] ] = (string) $r['table_name'];
			}
		}

		wp_cache_set( 'qrms_analitik_masa_adlari', $adlar, 'qrms', 300 );

		return $adlar;
	}

	/**
	 * Masanın panelde görünen adı.
	 *
	 * @param string $slug  Masa slug'ı ('' = masasız kayıt).
	 * @param array  $adlar masa_adlari() çıktısı.
	 * @return string
	 */
	public static function masa_etiketi( $slug, array $adlar = array() ) {
		if ( '' === $slug ) {
			return __( 'Masasız (doğrudan erişim)', 'qrms' );
		}

		if ( isset( $adlar[ $slug ] ) && '' !== $adlar[ $slug ] ) {
			return $adlar[ $slug ];
		}

		return $slug;
	}

	/**
	 * Filtre listesindeki masalar: kayıtlı masalar + veride görünen masalar.
	 *
	 * @return array<int,array{slug:string,label:string}>
	 */
	public static function masa_secenekleri() {
		global $wpdb;

		$adlar = self::masa_adlari();

		// Tablo hiç kurulamamış olabilir (CREATE yetkisi yoksa); o zaman
		// yalnızca kayıtlı masalar listelenir, sorgu hiç çalıştırılmaz.
		if ( ! self::tablo_var_mi() ) {
			$gorulen = array();
		} else {
			$tablo = self::tablo();

			// idx_masa üzerinden index-only tarama: satırlara inilmez.
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$gorulen = $wpdb->get_col( "SELECT DISTINCT masa_no FROM {$tablo} WHERE masa_no <> ''" );
		}

		$sluglar = array_unique( array_merge( array_keys( $adlar ), array_map( 'strval', (array) $gorulen ) ) );
		sort( $sluglar, SORT_NATURAL );

		$secenek = array();

		foreach ( $sluglar as $slug ) {
			if ( '' === $slug ) {
				continue;
			}

			$secenek[] = array(
				'slug'  => $slug,
				'label' => self::masa_etiketi( $slug, $adlar ),
			);
		}

		return $secenek;
	}

	/**
	 * En çok tıklanan ürünler.
	 *
	 * @param string $donem Dönem anahtarı.
	 * @param string $masa  Masa filtresi.
	 * @param int    $limit Azami satır.
	 * @return array<int,array<string,mixed>>
	 */
	public static function en_cok_tiklananlar( $donem, $masa = '', $limit = 30 ) {
		$bit = current_time( 'Y-m-d' ) . ' 23:59:59';

		return self::urun_siralamasi( self::donem_baslangici( $donem ), $bit, $masa, $limit );
	}

	/**
	 * Ürün sıralaması — KEYFİ aralık.
	 *
	 * @param string $bas   Aralık başlangıcı (MySQL biçimi).
	 * @param string $bit   Aralık bitişi (MySQL biçimi).
	 * @param string $masa  Masa filtresi.
	 * @param int    $limit Azami satır.
	 * @return array<int,array<string,mixed>>
	 */
	public static function urun_siralamasi( $bas, $bit, $masa = '', $limit = 30 ) {
		global $wpdb;

		$tablo   = self::tablo();
		$masa_ek = self::masa_sql( $masa );

		// Aralık iki uçtan sınırlı: idx_td (event_type, created_at) aralık
		// taraması olarak kullanılabilir.
		$kosul = $wpdb->prepare( 'created_at BETWEEN %s AND %s', $bas, $bit );
		$sinir = $wpdb->prepare( 'LIMIT %d', max( 1, (int) $limit ) );

		/*
		 * Gruplama YALNIZCA item_id ile yapılır. Ada ve kategoriye göre de
		 * gruplanırsa, adı düzeltilen ya da başka kategoriye taşınan bir ürün
		 * iki ayrı satıra bölünür: tıklamaları paylaşır, listede iki kez
		 * görünür ve ikisi de olduğundan aşağıda sıralanır.
		 *
		 * Ad ve kategori için EN SON kaydedilen değer alınır: created_at
		 * DATETIME'ı 19 karakterlik sabit genişliktedir, bu yüzden
		 * CONCAT(created_at, ad) üzerinde MAX() en yeni satırı verir ve
		 * SUBSTRING(...,20) tarih önekini kırpar. (Kayıt anındaki ad saklanır
		 * ki silinmiş ürünler de listede görünmeye devam etsin.)
		 */
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$satir = $wpdb->get_results(
			"SELECT item_id,
				SUBSTRING(MAX(CONCAT(created_at, item_name)), 20) AS item_name,
				SUBSTRING(MAX(CONCAT(created_at, category_name)), 20) AS category_name,
				COUNT(*) AS toplam,
				COUNT(DISTINCT ip_hash) AS tekil,
				COUNT(DISTINCT NULLIF(masa_no,'')) AS masa_sayisi,
				MAX(created_at) AS son
			 FROM {$tablo}
			 WHERE event_type='product_click' AND item_id > 0 AND {$kosul}{$masa_ek}
			 GROUP BY item_id
			 ORDER BY toplam DESC
			 {$sinir}",
			ARRAY_A
		);

		return is_array( $satir ) ? $satir : array();
	}

	/**
	 * Ürün başına ham tıklama sayaçları (item_id => satır).
	 *
	 * "En az tıklanan ürünler" listesi iki KAYNAĞI birleştirir: yayınlanmış
	 * ürünler (CPT) ve tıklama sayıları (analitik tablosu). Ürün başına ayrı
	 * sorgu N+1 demek olurdu; burada TEK bir GROUP BY ile bütün sayaçlar
	 * çekilir, eşleştirme PHP tarafında yapılır (bkz. urunler-sayfasi.php).
	 *
	 * Sıralama ve limit YOKTUR: hiç tıklanmamış ürünler bu sonuçta zaten
	 * bulunmaz, onları CPT tarafı getirir.
	 *
	 * @param string $bas  Aralık başlangıcı (MySQL biçimi).
	 * @param string $bit  Aralık bitişi (MySQL biçimi).
	 * @param string $masa Masa filtresi.
	 * @return array<int,array{toplam:int,tekil:int,son:string}>
	 */
	public static function urun_tiklama_sayaclari( $bas, $bit, $masa = '' ) {
		global $wpdb;

		$tablo   = self::tablo();
		$masa_ek = self::masa_sql( $masa );
		$kosul   = $wpdb->prepare( 'created_at BETWEEN %s AND %s', $bas, $bit );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ham = $wpdb->get_results(
			"SELECT item_id,
				COUNT(*) AS toplam,
				COUNT(DISTINCT ip_hash) AS tekil,
				MAX(created_at) AS son
			 FROM {$tablo}
			 WHERE event_type='product_click' AND item_id > 0 AND {$kosul}{$masa_ek}
			 GROUP BY item_id",
			ARRAY_A
		);

		$sayac = array();

		foreach ( (array) $ham as $r ) {
			$sayac[ (int) $r['item_id'] ] = array(
				'toplam' => (int) $r['toplam'],
				'tekil'  => (int) $r['tekil'],
				'son'    => (string) $r['son'],
			);
		}

		return $sayac;
	}

	/**
	 * Kategori dağılımı — seçili aralıkta hangi kategori kaç tıklama aldı.
	 *
	 * KATEGORİ ADI TAKSONOMİDEN DEĞİL, KAYITTAN gelir: tıklama anındaki ad
	 * saklanır (bkz. izle_urun_tiklama). Kategori sonradan yeniden
	 * adlandırıldıysa eski kayıtlar eski adla durur ve iki ayrı satır olarak
	 * görünür. Bu bir veri gerçeğidir; sorguda "düzeltilmeye" çalışılmaz
	 * (hangi eski adın hangi yeni ada karşılık geldiğini söyleyen bir kayıt
	 * yok, tahmin etmek sayıları sessizce yanlışlardı). Arayüz bunun yerine
	 * artık var olmayan adları işaretler — bkz. urunler-sayfasi.php.
	 *
	 * BOŞ ADLAR AYRIŞTIRILIR, TOPLANMAZ. category_name yalnızca product_click
	 * olayında doldurulur; menu_view'da her zaman boştur, bu yüzden sorgu
	 * zaten yalnızca product_click'e bakar. Buna rağmen boş kalan kayıtlar
	 * vardır: ürünün tıklama anında hiç rma_category terimi yoktu ya da kayıt
	 * eski bağımsız eklentiden kalmadır. Bunları "Kategorisiz" adlı bir
	 * satırda toplamak, sıralamada gerçek kategorilerle yarışan HAYALİ bir
	 * kategori üretirdi; bu yüzden listeden çıkarılır ama sayısı ayrıca
	 * döndürülür — kullanıcı "toplam ile liste neden tutmuyor" diye
	 * sormasın.
	 *
	 * @param string $bas   Aralık başlangıcı (MySQL biçimi).
	 * @param string $bit   Aralık bitişi (MySQL biçimi).
	 * @param string $masa  Masa filtresi.
	 * @param int    $limit Azami satır.
	 * @return array{satirlar:array<int,array<string,mixed>>,kategorisiz:int}
	 */
	public static function kategori_dagilimi( $bas, $bit, $masa = '', $limit = 50 ) {
		global $wpdb;

		$tablo   = self::tablo();
		$masa_ek = self::masa_sql( $masa );
		$kosul   = $wpdb->prepare( 'created_at BETWEEN %s AND %s', $bas, $bit );
		$sinir   = $wpdb->prepare( 'LIMIT %d', max( 1, (int) $limit ) );

		// İki sayaç da aynı aralık taramasından çıkar: kategorili satırlar
		// gruplanırken kategorisizler ayrı bir sorguya gerek kalmadan
		// sayılabilsin diye toplam ayrıca istenir.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$satirlar = $wpdb->get_results(
			"SELECT category_name,
				COUNT(*) AS toplam,
				COUNT(DISTINCT item_id) AS urun_sayisi,
				COUNT(DISTINCT ip_hash) AS tekil
			 FROM {$tablo}
			 WHERE event_type='product_click' AND category_name <> '' AND {$kosul}{$masa_ek}
			 GROUP BY category_name
			 ORDER BY toplam DESC
			 {$sinir}",
			ARRAY_A
		);

		$kategorisiz = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$tablo}
			 WHERE event_type='product_click' AND category_name = '' AND {$kosul}{$masa_ek}"
		);
		// phpcs:enable

		$sonuc = array();

		foreach ( (array) $satirlar as $r ) {
			$sonuc[] = array(
				'kategori'    => (string) $r['category_name'],
				'toplam'      => (int) $r['toplam'],
				'urun_sayisi' => (int) $r['urun_sayisi'],
				'tekil'       => (int) $r['tekil'],
			);
		}

		return array(
			'satirlar'    => $sonuc,
			'kategorisiz' => $kategorisiz,
		);
	}

	/**
	 * Sepet/sipariş olaylarının oturum+tip+ürün grupları — TEK sorgu.
	 *
	 * WHERE event_type IN (...) AND created_at BETWEEN ... idx_td'yi
	 * (masa filtresi varken idx_masa_td'yi) aralık taraması olarak kullanır.
	 * Oturum kimliği SQL'de üretilir: ip_hash + masa_no + 2 saatlik pencere.
	 * Eşleştirme (terk, dönüşüm) PHP tarafındadır; bu metot N+1 üretemez.
	 *
	 * @param string $bas  Aralık başlangıcı (MySQL biçimi).
	 * @param string $bit  Aralık bitişi (MySQL biçimi).
	 * @param string $masa Masa filtresi.
	 * @return array<int,array<string,mixed>>
	 */
	public static function sepet_olay_gruplari( $bas, $bit, $masa = '' ) {
		$anahtar = $bas . '|' . $bit . '|' . (string) $masa;

		if ( isset( self::$sepet_grup_onbellegi[ $anahtar ] ) ) {
			return self::$sepet_grup_onbellegi[ $anahtar ];
		}

		global $wpdb;

		$tablo   = self::tablo();
		$masa_ek = self::masa_sql( $masa );
		$kosul   = $wpdb->prepare( 'created_at BETWEEN %s AND %s', $bas, $bit );
		$saat    = max( 1, (int) self::OTURUM_SAAT );

		$pencere = "CONCAT(DATE_FORMAT(created_at, '%Y-%m-%d '), LPAD(FLOOR(HOUR(created_at) / {$saat}) * {$saat}, 2, '0'))";

		/*
		 * Beş olay tipi idx_td'nin ilk sütununda eşitliktir; IN + BETWEEN
		 * aralık taramasına izin verir. Yeni bir indeks gerekmedi.
		 */
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ham = $wpdb->get_results(
			"SELECT ip_hash, masa_no,
				{$pencere} AS pencere,
				event_type,
				item_id,
				SUBSTRING(MAX(CONCAT(created_at, item_name)), 20) AS item_name,
				SUBSTRING(MAX(CONCAT(created_at, category_name)), 20) AS category_name,
				COUNT(*) AS adet,
				MIN(created_at) AS ilk,
				MAX(created_at) AS son
			 FROM {$tablo}
			 WHERE event_type IN ('cart_add','cart_remove','order_sent','order_failed','order_blocked')
			   AND {$kosul}{$masa_ek}
			 GROUP BY ip_hash, masa_no, {$pencere}, event_type, item_id",
			ARRAY_A
		);

		$sonuc = array();

		foreach ( (array) $ham as $r ) {
			$sonuc[] = array(
				'ip_hash'       => isset( $r['ip_hash'] ) ? (string) $r['ip_hash'] : '',
				'masa_no'       => isset( $r['masa_no'] ) ? (string) $r['masa_no'] : '',
				'pencere'       => isset( $r['pencere'] ) ? (string) $r['pencere'] : '',
				'event_type'    => isset( $r['event_type'] ) ? (string) $r['event_type'] : '',
				'item_id'       => isset( $r['item_id'] ) ? (int) $r['item_id'] : 0,
				'item_name'     => isset( $r['item_name'] ) ? (string) $r['item_name'] : '',
				'category_name' => isset( $r['category_name'] ) ? (string) $r['category_name'] : '',
				'adet'          => isset( $r['adet'] ) ? (int) $r['adet'] : 0,
				'ilk'           => isset( $r['ilk'] ) ? (string) $r['ilk'] : '',
				'son'           => isset( $r['son'] ) ? (string) $r['son'] : '',
			);
		}

		self::$sepet_grup_onbellegi[ $anahtar ] = $sonuc;

		return $sonuc;
	}

	/**
	 * Verilen olay tiplerinin (tip, item_name) sayaçları — TEK sorgu.
	 *
	 * WHERE event_type IN (...) AND created_at BETWEEN idx_td (masa
	 * filtresi varken idx_masa_td) aralık taramasıdır. item_name kırılımı
	 * (dil kodu, splash eylemi, form adı) aynı taramadan çıkar; ikinci
	 * indeks gerekmez.
	 *
	 * @param string[] $tipler İzinli event_type listesi (çağıran sabitler).
	 * @param string   $bas    Aralık başlangıcı.
	 * @param string   $bit    Aralık bitişi.
	 * @param string   $masa   Masa filtresi.
	 * @return array<int,array{event_type:string,item_name:string,adet:int}>
	 */
	public static function olay_sayaclari( array $tipler, $bas, $bit, $masa = '' ) {
		global $wpdb;

		$temiz = array();

		foreach ( $tipler as $tip ) {
			$tip = sanitize_key( (string) $tip );

			if ( '' !== $tip ) {
				$temiz[] = $tip;
			}
		}

		$temiz = array_values( array_unique( $temiz ) );

		if ( empty( $temiz ) ) {
			return array();
		}

		$tablo   = self::tablo();
		$masa_ek = self::masa_sql( $masa );
		$kosul   = $wpdb->prepare( 'created_at BETWEEN %s AND %s', $bas, $bit );
		$in = "'" . implode( "','", $temiz ) . "'";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ham = $wpdb->get_results(
			"SELECT event_type, item_name, COUNT(*) AS adet
			 FROM {$tablo}
			 WHERE event_type IN ({$in}) AND {$kosul}{$masa_ek}
			 GROUP BY event_type, item_name",
			ARRAY_A
		);

		$sonuc = array();

		foreach ( (array) $ham as $r ) {
			$sonuc[] = array(
				'event_type' => isset( $r['event_type'] ) ? (string) $r['event_type'] : '',
				'item_name'  => isset( $r['item_name'] ) ? (string) $r['item_name'] : '',
				'adet'       => isset( $r['adet'] ) ? (int) $r['adet'] : 0,
			);
		}

		return $sonuc;
	}

	/**
	 * Tek bir olay tipinin zaman kırılımı (saatlik/günlük/haftalık/aylık).
	 *
	 * grafik_araligi() menü+tıklama kovalarını doldurur; chatbot zaman
	 * dağılımı tek tipe bakar. Aynı idx_td aralık taraması, sıfır kovalar
	 * PHP'de doldurulur.
	 *
	 * @param string $tip     event_type.
	 * @param string $kirilim hourly|daily|weekly|monthly.
	 * @param string $bas     Aralık başlangıcı.
	 * @param string $bit     Aralık bitişi.
	 * @param string $masa    Masa filtresi.
	 * @return array<int,array{etiket:string,adet:int}>
	 */
	public static function olay_grafik( $tip, $kirilim, $bas, $bit, $masa = '' ) {
		global $wpdb;

		$tip = sanitize_key( (string) $tip );

		if ( '' === $tip ) {
			return array();
		}

		$tablo   = self::tablo();
		$masa_ek = self::masa_sql( $masa );
		$kosul   = $wpdb->prepare( 'event_type = %s AND created_at BETWEEN %s AND %s', $tip, $bas, $bit );

		$bas_ts = (int) strtotime( $bas );
		$bit_ts = (int) strtotime( $bit );

		switch ( $kirilim ) {
			case 'hourly':
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$ham = $wpdb->get_results(
					"SELECT HOUR(created_at) AS k, COUNT(*) AS adet FROM {$tablo}
					 WHERE {$kosul}{$masa_ek}
					 GROUP BY HOUR(created_at)",
					ARRAY_A
				);

				$harita = array();

				foreach ( (array) $ham as $r ) {
					$harita[ (int) $r['k'] ] = (int) $r['adet'];
				}

				$satir = array();

				for ( $s = 0; $s < 24; $s++ ) {
					$satir[] = array(
						'etiket' => sprintf( '%02d:00', $s ),
						'adet'   => isset( $harita[ $s ] ) ? $harita[ $s ] : 0,
					);
				}

				return $satir;

			case 'weekly':
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$ham = $wpdb->get_results(
					"SELECT YEARWEEK(created_at,1) AS k, COUNT(*) AS adet FROM {$tablo}
					 WHERE {$kosul}{$masa_ek}
					 GROUP BY YEARWEEK(created_at,1)",
					ARRAY_A
				);

				$harita = array();

				foreach ( (array) $ham as $r ) {
					$harita[ (string) $r['k'] ] = (int) $r['adet'];
				}

				$satir = array();

				for ( $ts = self::hafta_basi( $bas_ts ); $ts <= $bit_ts; $ts = strtotime( '+1 week', $ts ) ) {
					$anahtar = gmdate( 'oW', $ts );
					$satir[] = array(
						'etiket' => date_i18n( 'j M', $ts ) . '–' . date_i18n( 'j M', strtotime( '+6 days', $ts ) ),
						'adet'   => isset( $harita[ $anahtar ] ) ? $harita[ $anahtar ] : 0,
					);
				}

				return $satir;

			case 'monthly':
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$ham = $wpdb->get_results(
					"SELECT DATE_FORMAT(created_at, '%Y-%m') AS k, COUNT(*) AS adet FROM {$tablo}
					 WHERE {$kosul}{$masa_ek}
					 GROUP BY DATE_FORMAT(created_at, '%Y-%m')",
					ARRAY_A
				);

				$harita = array();

				foreach ( (array) $ham as $r ) {
					$harita[ (string) $r['k'] ] = (int) $r['adet'];
				}

				$satir = array();

				for ( $ts = strtotime( gmdate( 'Y-m-01', $bas_ts ) ); $ts <= $bit_ts; $ts = strtotime( '+1 month', $ts ) ) {
					$anahtar = gmdate( 'Y-m', $ts );
					$satir[] = array(
						'etiket' => date_i18n( 'M Y', $ts ),
						'adet'   => isset( $harita[ $anahtar ] ) ? $harita[ $anahtar ] : 0,
					);
				}

				return $satir;

			default:
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$ham = $wpdb->get_results(
					"SELECT DATE(created_at) AS k, COUNT(*) AS adet FROM {$tablo}
					 WHERE {$kosul}{$masa_ek}
					 GROUP BY DATE(created_at)",
					ARRAY_A
				);

				$harita = array();

				foreach ( (array) $ham as $r ) {
					$harita[ (string) $r['k'] ] = (int) $r['adet'];
				}

				$satir = array();

				for ( $ts = strtotime( gmdate( 'Y-m-d', $bas_ts ) ); $ts <= $bit_ts; $ts = strtotime( '+1 day', $ts ) ) {
					$anahtar = gmdate( 'Y-m-d', $ts );
					$satir[] = array(
						'etiket' => date_i18n( 'j M', $ts ),
						'adet'   => isset( $harita[ $anahtar ] ) ? $harita[ $anahtar ] : 0,
					);
				}

				return $satir;
		}
	}

	/* -----------------------------------------------------------------
	   AJAX UÇLARI
	----------------------------------------------------------------- */

	/**
	 * İsteğin dönem parametresi.
	 *
	 * @param array $kaynak $_POST veya $_GET.
	 * @return string
	 */
	private static function istek_donemi( array $kaynak ) {
		$donem = isset( $kaynak['period'] ) ? sanitize_key( wp_unslash( $kaynak['period'] ) ) : 'hourly';

		return in_array( $donem, self::DONEMLER, true ) ? $donem : 'hourly';
	}

	/**
	 * İsteğin masa filtresi.
	 *
	 * @param array $kaynak $_POST veya $_GET.
	 * @return string
	 */
	private static function istek_masasi( array $kaynak ) {
		return isset( $kaynak['masa'] ) ? self::masa_temizle( wp_unslash( $kaynak['masa'] ) ) : '';
	}

	/**
	 * Ürünler kategorisinin verisi.
	 *
	 * Yalnızca BU kategorinin sorguları çalışır: özet kartları, zaman grafiği
	 * ve masa kesiti burada hiç sorulmaz.
	 *
	 * @return void
	 */
	public static function ajax_urunler() {
		check_ajax_referer( self::NONCE, 'security' );

		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'mesaj' => __( 'Yetkiniz yok.', 'qrms' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- check_ajax_referer yukarıda.
		$ham = (array) wp_unslash( $_POST );
		// phpcs:enable

		$baglam = QRMS_Analitik_Filtre::coz( $ham );
		$aralik = QRMS_Analitik_Filtre::aralik( $ham );
		$sayfa  = isset( $ham['usayfa'] ) ? max( 1, (int) $ham['usayfa'] ) : 1;

		wp_send_json_success(
			array_merge(
				array(
					'donem' => $baglam['donem'],
					'masa'  => $baglam['masa'],
				),
				qrms_analitik_urun_verisi( $aralik, $baglam['masa'], $sayfa )
			)
		);
	}

	/**
	 * Genel Bakış kategorisinin verisi: özet kartları + zaman grafiği.
	 *
	 * ajax_veri()'den ayrıdır ve YALNIZCA bu kategorinin sorgularını çalıştırır
	 * (masa listesi ve ürün sıralaması burada hiç sorulmaz). Filtrenin
	 * çözümlenmesi tek yerdedir: istekten gelen ham değerler
	 * QRMS_Analitik_Filtre'ye verilir, aralık ve kırılım oradan döner.
	 *
	 * @return void
	 */
	public static function ajax_genel() {
		check_ajax_referer( self::NONCE, 'security' );

		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'mesaj' => __( 'Yetkiniz yok.', 'qrms' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- check_ajax_referer yukarıda.
		$ham = (array) wp_unslash( $_POST );
		// phpcs:enable

		$baglam  = QRMS_Analitik_Filtre::coz( $ham );
		$aralik  = QRMS_Analitik_Filtre::aralik( $ham );
		$kirilim = QRMS_Analitik_Filtre::kirilim( $ham );
		$onceki  = QRMS_Analitik_Filtre::onceki_baslangic( $ham );

		wp_send_json_success(
			array(
				'donem'      => $baglam['donem'],
				'masa'       => $baglam['masa'],
				'kirilim'    => $kirilim,
				'kirilimlar' => QRMS_Analitik_Filtre::kirilimlar( $aralik['gun'] ),
				'gun'        => $aralik['gun'],
				'ozet'       => self::aralik_ozeti( $aralik['bas'], $aralik['bit'], $onceki, $baglam['masa'] ),
				'grafik'     => self::grafik_araligi( $kirilim, $aralik['bas'], $aralik['bit'], $baglam['masa'] ),
			)
		);
	}

	/**
	 * Masalar kategorisinin verisi.
	 *
	 * @return void
	 */
	public static function ajax_masalar() {
		check_ajax_referer( self::NONCE, 'security' );

		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'mesaj' => __( 'Yetkiniz yok.', 'qrms' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- check_ajax_referer yukarıda.
		$ham = (array) wp_unslash( $_POST );
		// phpcs:enable

		$baglam = QRMS_Analitik_Filtre::coz( $ham );
		$aralik = QRMS_Analitik_Filtre::aralik( $ham );

		wp_send_json_success(
			array_merge(
				array(
					'donem' => $baglam['donem'],
					'masa'  => $baglam['masa'],
				),
				qrms_analitik_masa_verisi( $aralik, $baglam['masa'] )
			)
		);
	}

	/**
	 * Sepet & Sipariş kategorisinin verisi.
	 *
	 * @return void
	 */
	public static function ajax_sepet() {
		check_ajax_referer( self::NONCE, 'security' );

		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'mesaj' => __( 'Yetkiniz yok.', 'qrms' ) ), 403 );
		}

		if ( function_exists( 'qrms_analitik_sepet_lisansli' ) && ! qrms_analitik_sepet_lisansli() ) {
			wp_send_json_error( array( 'mesaj' => __( 'Chatbot Asistan bu lisansta kapalı.', 'qrms' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- check_ajax_referer yukarıda.
		$ham = (array) wp_unslash( $_POST );
		// phpcs:enable

		$baglam = QRMS_Analitik_Filtre::coz( $ham );
		$aralik = QRMS_Analitik_Filtre::aralik( $ham );

		wp_send_json_success(
			array_merge(
				array(
					'donem' => $baglam['donem'],
					'masa'  => $baglam['masa'],
				),
				qrms_analitik_sepet_verisi( $aralik, $baglam['masa'] )
			)
		);
	}

	/**
	 * Müşteri Etkileşimi kategorisinin verisi.
	 *
	 * @return void
	 */
	public static function ajax_etkilesim() {
		check_ajax_referer( self::NONCE, 'security' );

		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'mesaj' => __( 'Yetkiniz yok.', 'qrms' ) ), 403 );
		}

		if ( function_exists( 'qrms_analitik_etkilesim_lisansli' ) && ! qrms_analitik_etkilesim_lisansli() ) {
			wp_send_json_error( array( 'mesaj' => __( 'Bu kategori bu lisansta kapalı.', 'qrms' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- check_ajax_referer yukarıda.
		$ham = (array) wp_unslash( $_POST );
		// phpcs:enable

		$baglam  = QRMS_Analitik_Filtre::coz( $ham );
		$aralik  = QRMS_Analitik_Filtre::aralik( $ham );
		$kirilim = QRMS_Analitik_Filtre::kirilim( $ham );

		wp_send_json_success(
			array_merge(
				array(
					'donem'   => $baglam['donem'],
					'masa'    => $baglam['masa'],
					'kirilim' => $kirilim,
				),
				qrms_analitik_etkilesim_verisi( $aralik, $baglam['masa'], $kirilim )
			)
		);
	}

	/**
	 * Açılış Ekranı kategorisinin verisi.
	 *
	 * @return void
	 */
	public static function ajax_acilis() {
		check_ajax_referer( self::NONCE, 'security' );

		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'mesaj' => __( 'Yetkiniz yok.', 'qrms' ) ), 403 );
		}

		if ( function_exists( 'qrms_analitik_acilis_lisansli' ) && ! qrms_analitik_acilis_lisansli() ) {
			wp_send_json_error( array( 'mesaj' => __( 'Açılış Ekranı bu lisansta kapalı.', 'qrms' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- check_ajax_referer yukarıda.
		$ham = (array) wp_unslash( $_POST );
		// phpcs:enable

		$baglam = QRMS_Analitik_Filtre::coz( $ham );
		$aralik = QRMS_Analitik_Filtre::aralik( $ham );

		wp_send_json_success(
			array_merge(
				array(
					'donem' => $baglam['donem'],
					'masa'  => $baglam['masa'],
				),
				qrms_analitik_acilis_verisi( $aralik, $baglam['masa'] )
			)
		);
	}

	/**
	 * CSV dışa aktarma.
	 *
	 * "Masalara Göre" görünümünde masa özeti, diğer dönemlerde ürün listesi
	 * indirilir — ekranda ne görünüyorsa o.
	 *
	 * @return void
	 */
	public static function ajax_csv() {
		check_ajax_referer( self::NONCE_CSV, 'security' );

		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'qrms' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- check_ajax_referer yukarıda.
		$ham = (array) wp_unslash( $_GET );

		// KATEGORİ BAZLI İNDİRME. Her kategori sayfası kendi verisini indirir;
		// parametre verilmezse eski (dönem bazlı) davranış aynen sürer —
		// klasik panelin CSV düğmesi ve dış bağlantılar kırılmaz.
		$kategori = isset( $ham['kategori'] ) ? sanitize_key( $ham['kategori'] ) : '';

		if ( 'urunler' === $kategori ) {
			self::csv_urunler( $ham );
			return;
		}

		if ( 'masalar' === $kategori ) {
			self::csv_masalar( $ham );
			return;
		}

		if ( 'ham' === $kategori ) {
			self::csv_ham( $ham );
			return;
		}

		if ( 'sepet' === $kategori ) {
			self::csv_sepet( $ham );
			return;
		}

		if ( 'etkilesim' === $kategori ) {
			self::csv_etkilesim( $ham );
			return;
		}

		if ( 'acilis' === $kategori ) {
			self::csv_acilis( $ham );
			return;
		}

		$donem = self::istek_donemi( $ham );
		$masa  = self::istek_masasi( $ham );

		$dosya = 'qr-analitik-' . $donem . ( '' !== $masa ? '-' . $masa : '' ) . '-' . gmdate( 'Ymd-His', self::simdi() ) . '.csv';

		$cikti = self::csv_ac( $dosya );

		if ( 'masalar' === $donem ) {
			fputcsv( $cikti, array( 'Masa', 'Masa Kodu', 'Menü Görüntüleme', 'Ürün Tıklama', 'Tekil Ziyaretçi', 'Son Hareket' ), ';' );

			foreach ( self::masa_verisi( $masa ) as $satir ) {
				fputcsv(
					$cikti,
					array( $satir['label'], $satir['masa'], $satir['mv'], $satir['pc'], $satir['uv'], $satir['son'] ),
					';'
				);
			}
		} else {
			fputcsv( $cikti, array( 'Sıra', 'Ürün ID', 'Ürün Adı', 'Kategori', 'Toplam Tıklama', 'Tekil Tıklama', 'Masa Sayısı', 'Son Tıklama' ), ';' );

			foreach ( self::en_cok_tiklananlar( $donem, $masa, 10000 ) as $i => $satir ) {
				fputcsv(
					$cikti,
					array(
						$i + 1,
						$satir['item_id'],
						$satir['item_name'],
						$satir['category_name'],
						$satir['toplam'],
						$satir['tekil'],
						$satir['masa_sayisi'],
						$satir['son'],
					),
					';'
				);
			}
		}

		self::csv_bitir( $cikti );
	}

	/**
	 * CSV indirmesinin ortak iskeleti: başlık, BOM, çıktı akışı.
	 *
	 * Kategori üreticileri yalnızca satırları yazar. Çeviri CSV'si ayrı
	 * şemadır, bu yardımcılara girmez.
	 *
	 * @param string $dosya İndirme dosya adı (.csv dahil).
	 * @return resource
	 */
	private static function csv_ac( $dosya ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $dosya . '"' );

		$cikti = fopen( 'php://output', 'w' );

		fwrite( $cikti, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		return $cikti;
	}

	/**
	 * Aralık ve (isteğe bağlı) masa satırları.
	 *
	 * @param resource             $cikti  csv_ac çıktısı.
	 * @param array{bas:string,bit:string} $aralik Filtre aralığı.
	 * @param array{masa?:string}  $baglam Filtre bağlamı.
	 * @param bool                 $masa   Masa satırı yazılsın mı.
	 * @return void
	 */
	private static function csv_ustbilgi( $cikti, array $aralik, array $baglam, $masa = true ) {
		fputcsv( $cikti, array( 'Aralık', substr( $aralik['bas'], 0, 10 ), substr( $aralik['bit'], 0, 10 ) ), ';' );

		if ( $masa ) {
			$slug = isset( $baglam['masa'] ) ? (string) $baglam['masa'] : '';
			fputcsv( $cikti, array( 'Masa', '' !== $slug ? $slug : 'Tüm masalar' ), ';' );
		}

		fputcsv( $cikti, array(), ';' );
	}

	/**
	 * Akışı kapatır ve isteği bitirir.
	 *
	 * @param resource $cikti csv_ac çıktısı.
	 * @return void
	 */
	private static function csv_bitir( $cikti ) {
		fclose( $cikti ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * "Ürünler" kategorisinin CSV'si.
	 *
	 * Ekrandaki bölümler tek dosyaya, başlıklarıyla ayrılmış hâlde
	 * yazılır — kullanıcı ayrı indirme yapmak zorunda kalmasın. Veri,
	 * ekranı besleyen fonksiyonun AYNISINDAN gelir (qrms_analitik_urun_verisi):
	 * indirilen dosya ekranda görünenle birebir aynıdır.
	 *
	 * @param array $ham İsteğin ham query arg'ları.
	 * @return void
	 */
	private static function csv_urunler( array $ham ) {
		$baglam = QRMS_Analitik_Filtre::coz( $ham );
		$aralik = QRMS_Analitik_Filtre::aralik( $ham );

		// Limit 0: CSV sayfalanmaz, bütün ürünler iner.
		$veri = qrms_analitik_urun_verisi( $aralik, $baglam['masa'], 1, 0 );

		$dosya = 'qr-analitik-urunler-' . $baglam['donem']
			. ( '' !== $baglam['masa'] ? '-' . $baglam['masa'] : '' )
			. '-' . gmdate( 'Ymd-His', self::simdi() ) . '.csv';

		$cikti = self::csv_ac( $dosya );

		self::csv_ustbilgi( $cikti, $aralik, $baglam );

		fputcsv( $cikti, array( 'EN ÇOK TIKLANAN ÜRÜNLER' ), ';' );
		fputcsv( $cikti, array( 'Sıra', 'Ürün ID', 'Ürün Adı', 'Kategori', 'Toplam Tıklama', 'Tekil Tıklama', 'Masa Sayısı', 'Son Tıklama' ), ';' );

		foreach ( $veri['encok'] as $i => $satir ) {
			fputcsv(
				$cikti,
				array(
					$i + 1,
					$satir['item_id'],
					$satir['item_name'],
					$satir['category_name'],
					$satir['toplam'],
					$satir['tekil'],
					$satir['masa_sayisi'],
					$satir['son'],
				),
				';'
			);
		}

		fputcsv( $cikti, array(), ';' );
		fputcsv( $cikti, array( 'EN AZ TIKLANAN ÜRÜNLER' ), ';' );
		fputcsv( $cikti, array( 'Ürün ID', 'Ürün Adı', 'Kategori', 'Durum', 'Toplam Tıklama', 'Tekil Tıklama', 'Son Tıklama' ), ';' );

		foreach ( $veri['enaz'] as $satir ) {
			fputcsv(
				$cikti,
				array(
					$satir['id'],
					$satir['ad'],
					$satir['kategori'],
					$satir['tukendi'] ? 'Tükendi' : 'Stokta',
					$satir['toplam'],
					$satir['tekil'],
					$satir['son'],
				),
				';'
			);
		}

		fputcsv( $cikti, array(), ';' );
		fputcsv( $cikti, array( 'KATEGORİ DAĞILIMI' ), ';' );
		fputcsv( $cikti, array( 'Kategori', 'Durum', 'Toplam Tıklama', 'Tekil Tıklama', 'Ürün Sayısı' ), ';' );

		foreach ( $veri['kategoriler'] as $satir ) {
			fputcsv(
				$cikti,
				array(
					$satir['kategori'],
					! empty( $satir['eski_ad'] ) ? 'Eski ad' : '',
					$satir['toplam'],
					$satir['tekil'],
					$satir['urun_sayisi'],
				),
				';'
			);
		}

		if ( $veri['kategorisiz'] > 0 ) {
			fputcsv( $cikti, array( 'Kategorisi kaydedilmemiş tıklama', '', $veri['kategorisiz'], '', '' ), ';' );
		}

		$detay = isset( $veri['detay'] ) ? $veri['detay'] : array();
		fputcsv( $cikti, array(), ';' );
		fputcsv( $cikti, array( 'DETAY MODALI AÇILMA ORANI' ), ';' );
		fputcsv( $cikti, array( 'Detay açılışı', isset( $detay['open'] ) ? $detay['open'] : 0 ), ';' );
		fputcsv( $cikti, array( 'Ürün tıklaması', isset( $detay['click'] ) ? $detay['click'] : 0 ), ';' );
		fputcsv( $cikti, array( 'Açılma oranı %', isset( $detay['oran'] ) ? $detay['oran'] : 0 ), ';' );

		self::csv_bitir( $cikti );
	}

	/**
	 * HAM OLAY KAYDI CSV'si — seçili aralıktaki bütün satırlar.
	 *
	 * Diğer indirmeler özet tablolarıdır; bu, tablonun kendisidir. Bu yüzden
	 * tek fark performans değil, BELLEKTİR: milyonlarca satırlık bir tabloyu
	 * get_results ile diziye almak PHP'nin bellek sınırını aşar ve indirme
	 * yarıda ölür.
	 *
	 * Çözüm iki katmanlı:
	 *   1. AKIŞ — satırlar CSV_PARCA'lık dilimler hâlinde çekilir, her dilim
	 *      yazılıp bellekten düşer. Sayfalama OFFSET ile değil id > son_id ile
	 *      yapılır: OFFSET büyüdükçe MySQL atlanan satırları da okumak zorunda
	 *      kalır, id üzerinden ilerlemek PRIMARY KEY taramasıdır.
	 *   2. TAVAN — yine de sınırsız bir indirme (ve sınırsız bir istek süresi)
	 *      bırakılmaz: CSV_TAVAN satırda durulur ve dosyanın SONUNA bir uyarı
	 *      satırı yazılır. Sessizce kesmek, eksik veriyi tam sanmaktan
	 *      kötüdür.
	 *
	 * @param array $ham İsteğin ham query arg'ları.
	 * @return void
	 */
	private static function csv_ham( array $ham ) {
		global $wpdb;

		$baglam = QRMS_Analitik_Filtre::coz( $ham );
		$aralik = QRMS_Analitik_Filtre::aralik( $ham );
		$masa   = $baglam['masa'];

		$dosya = 'qr-analitik-ham-' . $baglam['donem']
			. ( '' !== $masa ? '-' . $masa : '' )
			. '-' . gmdate( 'Ymd-His', self::simdi() ) . '.csv';

		$cikti = self::csv_ac( $dosya );

		fputcsv( $cikti, array( 'Olay', 'Ürün ID', 'Ürün Adı', 'Kategori', 'Masa', 'Tarih' ), ';' );

		if ( ! self::tablo_var_mi() ) {
			self::csv_bitir( $cikti );
		}

		$tablo   = self::tablo();
		$masa_ek = self::masa_sql( $masa );
		$kosul   = $wpdb->prepare( 'created_at BETWEEN %s AND %s', $aralik['bas'], $aralik['bit'] );

		$son_id  = 0;
		$yazilan = 0;
		$kesildi = false;

		while ( $yazilan < self::CSV_TAVAN ) {
			$parca = min( self::CSV_PARCA, self::CSV_TAVAN - $yazilan );
			$sinir = $wpdb->prepare( 'id > %d', $son_id );
			$adet  = $wpdb->prepare( 'LIMIT %d', $parca );

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$satirlar = $wpdb->get_results(
				"SELECT id, event_type, item_id, item_name, category_name, masa_no, created_at
				 FROM {$tablo}
				 WHERE {$sinir} AND {$kosul}{$masa_ek}
				 ORDER BY id ASC
				 {$adet}",
				ARRAY_A
			);

			if ( empty( $satirlar ) ) {
				break;
			}

			foreach ( $satirlar as $satir ) {
				fputcsv(
					$cikti,
					array(
						$satir['event_type'],
						$satir['item_id'],
						$satir['item_name'],
						$satir['category_name'],
						$satir['masa_no'],
						$satir['created_at'],
					),
					';'
				);

				$son_id = (int) $satir['id'];
				++$yazilan;
			}

			// Dilim yazıldı: tampon boşaltılıp bellek serbest bırakılır.
			unset( $satirlar );
			flush();

			if ( $yazilan >= self::CSV_TAVAN ) {
				$kesildi = true;
				break;
			}
		}

		if ( $kesildi ) {
			fputcsv( $cikti, array(), ';' );
			fputcsv(
				$cikti,
				array(
					sprintf(
						/* translators: %s: satır sayısı. */
						__( 'UYARI: Dosya %s satırda kesildi. Daha dar bir tarih aralığı seçip yeniden indirin.', 'qrms' ),
						number_format_i18n( self::CSV_TAVAN )
					),
				),
				';'
			);
		}

		self::csv_bitir( $cikti );
	}

	/**
	 * "Masalar" kategorisinin CSV'si: masa listesi + grup toplamları.
	 *
	 * Ekranı besleyen fonksiyonun AYNISINDAN gelir; indirilen dosya ekranda
	 * görünenle birebir aynıdır. Hiç okutulmamış masalar da 0 ile inerler —
	 * asıl aranan satırlar onlardır.
	 *
	 * @param array $ham İsteğin ham query arg'ları.
	 * @return void
	 */
	private static function csv_masalar( array $ham ) {
		$baglam = QRMS_Analitik_Filtre::coz( $ham );
		$aralik = QRMS_Analitik_Filtre::aralik( $ham );
		$veri   = qrms_analitik_masa_verisi( $aralik, $baglam['masa'] );

		$dosya = 'qr-analitik-masalar-' . $baglam['donem'] . '-' . gmdate( 'Ymd-His', self::simdi() ) . '.csv';

		$cikti = self::csv_ac( $dosya );

		self::csv_ustbilgi( $cikti, $aralik, $baglam, false );

		fputcsv( $cikti, array( 'MASALAR' ), ';' );
		fputcsv( $cikti, array( 'Masa', 'Masa Kodu', 'Grup', 'Durum', 'Menü Okutma', 'Ürün Tıklama', 'Tekil Ziyaretçi', 'Son Hareket' ), ';' );

		foreach ( $veri['satirlar'] as $satir ) {
			$durum = __( 'Kayıtlı', 'qrms' );

			if ( 'kayitsiz' === $satir['durum'] ) {
				$durum = __( 'Kayıtlı olmayan masa', 'qrms' );
			} elseif ( 'masasiz' === $satir['durum'] ) {
				$durum = __( 'Masasız erişim', 'qrms' );
			} elseif ( 0 === $satir['mv'] + $satir['pc'] ) {
				$durum = __( 'Hiç okutulmadı', 'qrms' );
			}

			fputcsv(
				$cikti,
				array(
					$satir['label'],
					$satir['masa'],
					$satir['grup'],
					$durum,
					$satir['mv'],
					$satir['pc'],
					$satir['uv'],
					$satir['son'],
				),
				';'
			);
		}

		fputcsv( $cikti, array(), ';' );
		fputcsv( $cikti, array( 'MASA GRUPLARI' ), ';' );
		fputcsv( $cikti, array( 'Grup', 'Masa Sayısı', 'Hiç Okutulmayan', 'Menü Okutma', 'Ürün Tıklama', 'Tekil Ziyaretçi' ), ';' );

		foreach ( $veri['gruplar'] as $grup ) {
			fputcsv(
				$cikti,
				array(
					$grup['grup'],
					$grup['masa'],
					$grup['sessiz'],
					$grup['mv'],
					$grup['pc'],
					$grup['uv'],
				),
				';'
			);
		}

		self::csv_bitir( $cikti );
	}

	/**
	 * "Sepet & Sipariş" kategorisinin CSV'si.
	 *
	 * Ekrandaki tabloların birleşimi; Sistem sayfasındaki ham/ürün/masa
	 * indirmelerinden ayrı bir kategori anahtarı kullanır (`sepet`).
	 *
	 * @param array $ham İsteğin ham query arg'ları.
	 * @return void
	 */
	private static function csv_sepet( array $ham ) {
		if ( function_exists( 'qrms_analitik_sepet_lisansli' ) && ! qrms_analitik_sepet_lisansli() ) {
			wp_die( esc_html__( 'Chatbot Asistan bu lisansta kapalı.', 'qrms' ) );
		}

		$baglam = QRMS_Analitik_Filtre::coz( $ham );
		$aralik = QRMS_Analitik_Filtre::aralik( $ham );
		$veri   = qrms_analitik_sepet_verisi( $aralik, $baglam['masa'], 0 );

		$dosya = 'qr-analitik-sepet-' . $baglam['donem']
			. ( '' !== $baglam['masa'] ? '-' . $baglam['masa'] : '' )
			. '-' . gmdate( 'Ymd-His', self::simdi() ) . '.csv';

		$cikti = self::csv_ac( $dosya );

		self::csv_ustbilgi( $cikti, $aralik, $baglam );

		$ozet = $veri['ozet'];
		fputcsv( $cikti, array( 'ÖZET' ), ';' );
		fputcsv( $cikti, array( 'Sepete ekleme (olay)', $ozet['cart_add'] ), ';' );
		fputcsv( $cikti, array( 'Sepete ekleme (tekil ürün)', $ozet['cart_add_urun'] ), ';' );
		fputcsv( $cikti, array( 'Gönderilen sipariş (oturum)', $ozet['order_sent'] ), ';' );
		fputcsv( $cikti, array( 'Terk edilen sepet (oturum)', $ozet['terk'] ), ';' );
		fputcsv( $cikti, array( 'Terk oranı %', $ozet['terk_oran'] ), ';' );
		fputcsv( $cikti, array( 'Engellenen sipariş', $ozet['blocked'] ), ';' );
		fputcsv( $cikti, array( 'Başarısız sipariş (oturum)', $ozet['failed'] ), ';' );
		fputcsv( $cikti, array(), ';' );

		fputcsv( $cikti, array( 'SEPETE EKLENİP GÖNDERİLMEYEN ÜRÜNLER' ), ';' );
		fputcsv( $cikti, array( 'Ürün ID', 'Ürün Adı', 'Kategori', 'Terk (oturum)', 'Ekleme (olay)' ), ';' );

		foreach ( $veri['terk_urun'] as $satir ) {
			fputcsv(
				$cikti,
				array( $satir['id'], $satir['ad'], $satir['kategori'], $satir['terk'], $satir['ekleme'] ),
				';'
			);
		}

		fputcsv( $cikti, array(), ';' );
		fputcsv( $cikti, array( 'SEPETTEN ÇIKARILAN ÜRÜNLER' ), ';' );
		fputcsv( $cikti, array( 'Ürün ID', 'Ürün Adı', 'Kategori', 'Çıkarma', 'Ekleme', 'Ekleme/Çıkarma' ), ';' );

		foreach ( $veri['cikarilan'] as $satir ) {
			fputcsv(
				$cikti,
				array(
					$satir['id'],
					$satir['ad'],
					$satir['kategori'],
					$satir['cikarma'],
					$satir['ekleme'],
					null === $satir['oran'] ? '' : $satir['oran'],
				),
				';'
			);
		}

		fputcsv( $cikti, array(), ';' );
		fputcsv( $cikti, array( 'ENGELLENEN SİPARİŞLER' ), ';' );
		fputcsv( $cikti, array( 'Ürün ID', 'Ürün Adı', 'Kategori', 'Kaçırılan sipariş' ), ';' );

		foreach ( $veri['engellenen'] as $satir ) {
			fputcsv(
				$cikti,
				array( $satir['id'], $satir['ad'], $satir['kategori'], $satir['siparis'] ),
				';'
			);
		}

		if ( $ozet['failed'] > 0 ) {
			fputcsv( $cikti, array(), ';' );
			fputcsv( $cikti, array( 'SİPARİŞ HATALARI' ), ';' );
			fputcsv( $cikti, array( 'Zaman', 'Sipariş (oturum)' ), ';' );

			foreach ( $veri['hatalar'] as $satir ) {
				fputcsv( $cikti, array( $satir['label'], $satir['sayi'] ), ';' );
			}
		}

		self::csv_bitir( $cikti );
	}

	/**
	 * "Müşteri Etkileşimi" kategorisinin CSV'si.
	 *
	 * @param array $ham İsteğin ham query arg'ları.
	 * @return void
	 */
	private static function csv_etkilesim( array $ham ) {
		if ( function_exists( 'qrms_analitik_etkilesim_lisansli' ) && ! qrms_analitik_etkilesim_lisansli() ) {
			wp_die( esc_html__( 'Bu kategori bu lisansta kapalı.', 'qrms' ) );
		}

		$baglam  = QRMS_Analitik_Filtre::coz( $ham );
		$aralik  = QRMS_Analitik_Filtre::aralik( $ham );
		$kirilim = QRMS_Analitik_Filtre::kirilim( $ham );
		$veri    = qrms_analitik_etkilesim_verisi( $aralik, $baglam['masa'], $kirilim );

		$dosya = 'qr-analitik-etkilesim-' . $baglam['donem']
			. ( '' !== $baglam['masa'] ? '-' . $baglam['masa'] : '' )
			. '-' . gmdate( 'Ymd-His', self::simdi() ) . '.csv';

		$cikti = self::csv_ac( $dosya );

		self::csv_ustbilgi( $cikti, $aralik, $baglam );

		$ozet = $veri['ozet'];
		fputcsv( $cikti, array( 'ÖZET' ), ';' );
		fputcsv( $cikti, array( 'Chatbot mesajı', $ozet['chatbot'] ), ';' );
		fputcsv( $cikti, array( 'Yorum gönderimi', $ozet['review'] ), ';' );
		fputcsv( $cikti, array( 'Form gönderimi', $ozet['form'] ), ';' );
		fputcsv( $cikti, array( 'Üretilen ödül kodu', $ozet['reward_issued'] ), ';' );
		fputcsv( $cikti, array( 'Kullanılan ödül kodu', $ozet['reward_redeemed'] ), ';' );
		fputcsv( $cikti, array( 'Ödül dönüşüm %', $ozet['reward_oran'] ), ';' );
		fputcsv( $cikti, array( 'Dil değişimi', $ozet['lang'] ), ';' );
		fputcsv( $cikti, array( 'Galeri görüntüleme', $ozet['gallery'] ), ';' );
		fputcsv( $cikti, array(), ';' );

		fputcsv( $cikti, array( 'FORM GÖNDERİMİ' ), ';' );
		fputcsv( $cikti, array( 'Form', 'Gönderim' ), ';' );

		foreach ( $veri['formlar'] as $satir ) {
			fputcsv( $cikti, array( $satir['ad'], $satir['adet'] ), ';' );
		}

		fputcsv( $cikti, array(), ';' );
		fputcsv( $cikti, array( 'DİL DAĞILIMI' ), ';' );
		fputcsv( $cikti, array( 'Dil kodu', 'Dil', 'Seçim', 'Pay %' ), ';' );

		foreach ( $veri['diller'] as $satir ) {
			fputcsv( $cikti, array( $satir['kod'], $satir['ad'], $satir['adet'], $satir['pay'] ), ';' );
		}

		self::csv_bitir( $cikti );
	}

	/**
	 * "Açılış Ekranı" kategorisinin CSV'si.
	 *
	 * @param array $ham İsteğin ham query arg'ları.
	 * @return void
	 */
	private static function csv_acilis( array $ham ) {
		if ( function_exists( 'qrms_analitik_acilis_lisansli' ) && ! qrms_analitik_acilis_lisansli() ) {
			wp_die( esc_html__( 'Açılış Ekranı bu lisansta kapalı.', 'qrms' ) );
		}

		$baglam = QRMS_Analitik_Filtre::coz( $ham );
		$aralik = QRMS_Analitik_Filtre::aralik( $ham );
		$veri   = qrms_analitik_acilis_verisi( $aralik, $baglam['masa'] );

		$dosya = 'qr-analitik-acilis-' . $baglam['donem']
			. ( '' !== $baglam['masa'] ? '-' . $baglam['masa'] : '' )
			. '-' . gmdate( 'Ymd-His', self::simdi() ) . '.csv';

		$cikti = self::csv_ac( $dosya );

		self::csv_ustbilgi( $cikti, $aralik, $baglam );

		$ozet = $veri['ozet'];
		fputcsv( $cikti, array( 'ÖZET' ), ';' );
		fputcsv( $cikti, array( 'Gösterim', $ozet['view'] ), ';' );
		fputcsv( $cikti, array( 'Menüye geçiş', $ozet['menu'] ), ';' );
		fputcsv( $cikti, array( 'Menüye geçiş %', $ozet['menu_oran'] ), ';' );
		fputcsv( $cikti, array( 'Atlanma', $ozet['atla'] ), ';' );
		fputcsv( $cikti, array( 'Atlanma %', $ozet['atla_oran'] ), ';' );
		fputcsv( $cikti, array(), ';' );

		fputcsv( $cikti, array( 'BUTON TIKLAMALARI' ), ';' );
		fputcsv( $cikti, array( 'Buton', 'Kod', 'Tıklama', 'Pay %' ), ';' );

		foreach ( $veri['butonlar'] as $satir ) {
			fputcsv( $cikti, array( $satir['ad'], $satir['kod'], $satir['adet'], $satir['pay'] ), ';' );
		}

		self::csv_bitir( $cikti );
	}

	/**
	 * "Veri & Sistem" ekranındaki saklama süresi formunu işler.
	 *
	 * Yıkıcı olmayan ama etkisi kalıcı bir ayardır (kısaltmak, bir sonraki
	 * cron turunda veri siler): nonce ve yetki kontrolü silme akışıyla aynı
	 * sıkılıktadır.
	 *
	 * @return void
	 */
	public static function saklama_formu() {
		check_admin_referer( 'qrms_analitik_saklama' );

		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'qrms' ), '', array( 'response' => 403 ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer yukarıda.
		$gun = isset( $_POST['saklama_gun'] ) ? (int) $_POST['saklama_gun'] : self::SAKLAMA_GUN;

		self::saklama_kaydet( $gun );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'qrms-an-sistem',
					'saklama_msg'  => 'kaydedildi',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Kayıtları siler. Masa filtresi doluysa yalnızca o masanın kayıtları.
	 *
	 * @return void
	 */
	public static function ajax_temizle() {
		check_ajax_referer( self::NONCE, 'security' );

		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'mesaj' => __( 'Yetkiniz yok.', 'qrms' ) ), 403 );
		}

		global $wpdb;

		$tablo = self::tablo();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_ajax_referer yukarıda.
		$masa = self::istek_masasi( $_POST );

		if ( '' !== $masa ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $tablo, array( 'masa_no' => $masa ), array( '%s' ) );

			self::istatistik_onbellegini_temizle();

			wp_send_json_success(
				array(
					'mesaj' => sprintf(
						/* translators: %s: masa adı. */
						__( '%s masasının analitik kayıtları silindi.', 'qrms' ),
						self::masa_etiketi( $masa, self::masa_adlari() )
					),
				)
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "TRUNCATE TABLE {$tablo}" );

		self::istatistik_onbellegini_temizle();

		wp_send_json_success( array( 'mesaj' => __( 'Tüm analitik kayıtları silindi.', 'qrms' ) ) );
	}
}
