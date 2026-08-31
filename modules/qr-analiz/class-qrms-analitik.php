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
	const DB_SURUM = '1.1';

	/**
	 * Şema sürümünün tutulduğu option.
	 */
	const DB_OPT = 'qrms_analitik_db_surumu';

	/**
	 * Panel AJAX uçlarının nonce eylemi.
	 */
	const NONCE = 'qrms_analitik';

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
	 * "Masalara Göre" görünümünün kapsadığı gün sayısı.
	 */
	const MASA_GUN = 30;

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

		add_action( 'wp_ajax_qrms_analitik_veri', array( __CLASS__, 'ajax_veri' ) );
		add_action( 'wp_ajax_qrms_analitik_genel', array( __CLASS__, 'ajax_genel' ) );
		add_action( 'wp_ajax_qrms_analitik_urunler', array( __CLASS__, 'ajax_urunler' ) );
		add_action( 'wp_ajax_qrms_analitik_csv', array( __CLASS__, 'ajax_csv' ) );
		add_action( 'wp_ajax_qrms_analitik_temizle', array( __CLASS__, 'ajax_temizle' ) );

		// Saklama süresi dolan ham kayıtları silen günlük görev.
		add_action( self::CRON_TEMIZLIK, array( __CLASS__, 'eski_kayitlari_sil' ) );
		add_action( 'init', array( __CLASS__, 'temizlik_planla' ), 5 );
	}

	/* -----------------------------------------------------------------
	   SAKLAMA POLİTİKASI
	----------------------------------------------------------------- */

	/**
	 * Ham kaydın saklanacağı gün sayısı.
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
		 * @param int $gun Varsayılan saklama süresi.
		 */
		$gun = (int) apply_filters( 'qrms_analitik_saklama_gun', self::SAKLAMA_GUN );

		if ( $gun <= 0 ) {
			return 0;
		}

		return max( 7, $gun );
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
	 * @return int Silinen satır sayısı.
	 */
	public static function eski_kayitlari_sil() {
		global $wpdb;

		$gun = self::saklama_gun();

		if ( 0 === $gun ) {
			return 0;
		}

		if ( ! self::tablo_var_mi() ) {
			return 0;
		}

		$tablo = self::tablo();
		$sinir = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $gun . ' days', self::simdi() ) );

		/**
		 * Bir temizlik turunun süre bütçesi (saniye). 0 = tek parça sil.
		 *
		 * @param int $saniye Varsayılan SAKLAMA_SURE.
		 */
		$butce  = (int) apply_filters( 'qrms_analitik_temizlik_sure', self::SAKLAMA_SURE );
		$baslar = microtime( true );

		$toplam = 0;

		for ( $tur = 0; $tur < self::SAKLAMA_TUR; $tur++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$silinen = (int) $wpdb->query(
				$wpdb->prepare(
					// created_at üzerinde idx_date var; aralık taraması indekslidir.
					"DELETE FROM {$tablo} WHERE created_at < %s LIMIT %d",
					$sinir,
					self::SAKLAMA_PARCA
				)
			);

			$toplam += $silinen;

			// Parça dolmadıysa silinecek bir şey kalmamıştır.
			if ( $silinen < self::SAKLAMA_PARCA ) {
				break;
			}

			if ( $butce <= 0 || ( microtime( true ) - $baslar ) >= $butce ) {
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
				'mesaj'   => __( 'Kayıtları o topluyor; çift sayım olmasın diye bu modül izlemeyi tamamen kapattı. Eski eklenti masa bilgisini yazmadığı için "Masalara Göre" sekmesi de boş kalır. Masa bazlı takibin çalışması için eski eklentiyi devre dışı bırakın.', 'qrms' ),
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
	 * @param array $satir Sütun => değer.
	 * @return void
	 */
	private static function kaydet( array $satir ) {
		global $wpdb;

		$varsayilan = array(
			'event_type'    => '',
			'item_id'       => 0,
			'item_name'     => '',
			'category_name' => '',
			'masa_no'       => self::masa_belirle(),
			'ip_hash'       => self::ip_hash(),
			'created_at'    => current_time( 'mysql' ),
		);

		$satir = array_merge( $varsayilan, $satir );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			self::tablo(),
			$satir,
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
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
			return $sonuc;
		}

		// Aralıkta hiç satır yoksa SUM() NULL döner; (int) hepsini sıfıra indirir.
		foreach ( $sonuc as $anahtar => $varsayilan ) {
			if ( 'pc_tumu' === $anahtar ) {
				continue;
			}

			$sonuc[ $anahtar ] = isset( $satir[ $anahtar ] ) ? (int) $satir[ $anahtar ] : 0;
		}

		return $sonuc;
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
		global $wpdb;

		$tablo     = self::tablo();
		$masa_ek   = self::masa_sql( $masa );
		$baslangic = self::donem_baslangici( 'masalar' );
		$kosul     = $wpdb->prepare( 'created_at >= %s', $baslangic );

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

		$adlar = self::masa_adlari();
		$satir = array();

		foreach ( (array) $ham as $r ) {
			$slug = (string) $r['masa_no'];

			$satir[] = array(
				'masa'  => $slug,
				'label' => self::masa_etiketi( $slug, $adlar ),
				'mv'    => (int) $r['mv'],
				'pc'    => (int) $r['pc'],
				'uv'    => (int) $r['uv'],
				'son'   => (string) $r['son'],
			);
		}

		return $satir;
	}

	/**
	 * Masa slug'ı => görünen ad (qr-masa modülünün tablosundan).
	 *
	 * @return array<string,string>
	 */
	private static function masa_adlari() {
		global $wpdb;

		$tablo = $wpdb->prefix . 'qrm_tables';
		$adlar = wp_cache_get( 'qrms_analitik_masa_adlari', 'qrms' );

		if ( is_array( $adlar ) ) {
			return $adlar;
		}

		$adlar = array();

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
	private static function masa_etiketi( $slug, array $adlar = array() ) {
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
	 * Panel verisi.
	 *
	 * @return void
	 */
	public static function ajax_veri() {
		check_ajax_referer( self::NONCE, 'security' );

		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'mesaj' => __( 'Yetkiniz yok.', 'qrms' ) ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_ajax_referer yukarıda.
		$donem = self::istek_donemi( $_POST );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$masa = self::istek_masasi( $_POST );

		$grafik = ( 'masalar' === $donem )
			? self::masa_verisi( $masa )
			: self::grafik_verisi( $donem, $masa );

		wp_send_json_success(
			array(
				'donem'   => $donem,
				'masa'    => $masa,
				'grafik'  => $grafik,
				'masalar' => self::masa_secenekleri(),
			)
		);
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

		$donem = self::istek_donemi( $ham );
		$masa  = self::istek_masasi( $ham );

		$dosya = 'qr-analitik-' . $donem . ( '' !== $masa ? '-' . $masa : '' ) . '-' . gmdate( 'Ymd-His', self::simdi() ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $dosya . '"' );

		$cikti = fopen( 'php://output', 'w' );

		// Excel'in UTF-8'i tanıması için BOM.
		fwrite( $cikti, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

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

		fclose( $cikti ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * "Ürünler" kategorisinin CSV'si.
	 *
	 * Ekrandaki üç bölümün üçü de tek dosyaya, başlıklarıyla ayrılmış hâlde
	 * yazılır — kullanıcı üç ayrı indirme yapmak zorunda kalmasın. Veri,
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

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $dosya . '"' );

		$cikti = fopen( 'php://output', 'w' );

		// Excel'in UTF-8'i tanıması için BOM.
		fwrite( $cikti, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		fputcsv( $cikti, array( 'Aralık', substr( $aralik['bas'], 0, 10 ), substr( $aralik['bit'], 0, 10 ) ), ';' );
		fputcsv( $cikti, array( 'Masa', '' !== $baglam['masa'] ? $baglam['masa'] : 'Tüm masalar' ), ';' );
		fputcsv( $cikti, array(), ';' );

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

		fclose( $cikti ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
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

		wp_send_json_success( array( 'mesaj' => __( 'Tüm analitik kayıtları silindi.', 'qrms' ) ) );
	}
}
