<?php
/**
 * Ortak yardımcılar ve sabit modül listesi.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modül slug'ları, görünen isimler ve küçük yardımcı fonksiyonlar.
 *
 * Modül slug -> Türkçe isim eşleşmesi SADECE burada tanımlanır; wizard,
 * admin menüsü ve placeholder sayfaları bu listeyi referans alır.
 */
class QRMS_Helpers {

	/**
	 * Bilinen modül slug'ları (sunucudaki sözleşme ile birebir aynı, sıralı).
	 *
	 * @var string[]
	 */
	const MODULE_SLUGS = array(
		'restoran-menu',
		'yorum-feedback',
		'qr-masa',
		'qr-analiz',
		'qr-galeri',
		'qr-ceviri',
		'qr-chatbot',
		'qr-calisma-saatleri',
		'qr-masa-oturum-guvenligi',
		'qr-acilis-ekrani',
	);

	/**
	 * Modül slug -> görünen (Türkçe) isim eşleşmesi.
	 *
	 * @return array<string,string> Slug => isim.
	 */
	public static function get_modules() {
		return array(
			'restoran-menu'            => __( 'Restoran Menü', 'qrms' ),
			'yorum-feedback'           => __( 'Yorum & Feedback', 'qrms' ),
			'qr-masa'                  => __( 'QR Masa', 'qrms' ),
			'qr-analiz'                => __( 'QR Analiz', 'qrms' ),
			'qr-galeri'                => __( 'QR Galeri', 'qrms' ),
			'qr-ceviri'                => __( 'QR Çeviri', 'qrms' ),
			'qr-chatbot'               => __( 'QR Chatbot', 'qrms' ),
			'qr-calisma-saatleri'      => __( 'QR Çalışma Saatleri', 'qrms' ),
			// Slug değişmedi (lisans sözleşmesi ve kayıtlı option'lar ona bağlı);
			// yalnızca görünen ad sadeleşti.
			'qr-masa-oturum-guvenligi' => __( 'Güvenlik Ayarı', 'qrms' ),
			'qr-acilis-ekrani'         => __( 'Açılış Ekranı', 'qrms' ),
		);
	}

	/**
	 * Modül slug -> kart bilgisi (dashicon + tek satırlık iş tarifi).
	 *
	 * Genel Bakış'taki kart ızgarası bu tabloyu kullanır. İsimlerle aynı
	 * yerde durur ki yeni bir modül eklendiğinde tek dosya güncellensin.
	 *
	 * İkonlar dashicons setinden gelir — emoji DEĞİL; gerekçe için
	 * QRMS_Admin::render_hub() başlığına bakın.
	 *
	 * @return array<string,array{icon:string,desc:string}>
	 */
	public static function get_module_meta() {
		return array(
			'restoran-menu'            => array(
				'icon' => 'dashicons-food',
				'desc' => __( 'Ürünler, kategoriler, fiyatlar ve menünün görünümü.', 'qrms' ),
			),
			'yorum-feedback'           => array(
				'icon' => 'dashicons-testimonial',
				'desc' => __( 'Misafir yorumları, puanlar ve geri bildirim formları.', 'qrms' ),
			),
			'qr-masa'                  => array(
				'icon' => 'dashicons-editor-table',
				'desc' => __( 'Masa QR kodları, oturumlar ve masaya özel bağlantılar.', 'qrms' ),
			),
			'qr-analiz'                => array(
				'icon' => 'dashicons-chart-bar',
				'desc' => __( 'Menü okutmaları, en çok bakılan ürünler ve raporlar.', 'qrms' ),
			),
			'qr-galeri'                => array(
				'icon' => 'dashicons-format-gallery',
				'desc' => __( 'Restoranınızın fotoğraf galerisi ve albümleri.', 'qrms' ),
			),
			'qr-ceviri'                => array(
				'icon' => 'dashicons-translation',
				'desc' => __( 'Menünün yabancı dillerdeki karşılıkları.', 'qrms' ),
			),
			'qr-chatbot'               => array(
				'icon' => 'dashicons-format-chat',
				'desc' => __( 'Misafirin sorularını yanıtlayan sohbet asistanı.', 'qrms' ),
			),
			'qr-calisma-saatleri'      => array(
				'icon' => 'dashicons-clock',
				'desc' => __( 'Açılış–kapanış saatleri ve tatil günleri.', 'qrms' ),
			),
			'qr-masa-oturum-guvenligi' => array(
				'icon' => 'dashicons-lock',
				'desc' => __( 'Masa oturumunun güvenlik limitleri ve kimlik ayarları.', 'qrms' ),
			),
			'qr-acilis-ekrani'         => array(
				'icon' => 'dashicons-visibility',
				'desc' => __( 'Menü açılmadan önce görünen karşılama ekranı.', 'qrms' ),
			),
		);
	}

	/**
	 * Bir modülün kart ikonu.
	 *
	 * @param string $slug Modül slug'ı.
	 * @return string Tanımsız slug'da genel ikon döner.
	 */
	public static function get_module_icon( $slug ) {
		$meta = self::get_module_meta();

		return isset( $meta[ $slug ]['icon'] ) ? $meta[ $slug ]['icon'] : 'dashicons-admin-generic';
	}

	/**
	 * Bir modülün kart açıklaması.
	 *
	 * @param string $slug Modül slug'ı.
	 * @return string Tanımsız slug'da boş metin döner (kart açıklamasız basılır).
	 */
	public static function get_module_description( $slug ) {
		$meta = self::get_module_meta();

		return isset( $meta[ $slug ]['desc'] ) ? $meta[ $slug ]['desc'] : '';
	}

	/**
	 * Bir varlık dosyasının önbellek kıran sürüm etiketi.
	 *
	 * NEDEN GEREKLİ: `wp_enqueue_style()`'a sabit `QRMS_VERSION` verildiğinde
	 * dosyanın adresi (`admin.css?ver=1.0.0`) sürüm yükseltilene kadar HİÇ
	 * değişmez. Dosyanın içeriği değişse bile tarayıcı, sunucudaki sayfa
	 * önbelleği ve CDN eski kopyayı sunmaya devam eder — yeni eklenen kurallar
	 * hiç uygulanmaz ve ekran stilsiz görünür. (Hub kart ızgarası tam olarak
	 * böyle kayboldu: `.qrms-hub-*` kuralları dosyaya eklendi ama adres
	 * değişmediği için eski, o kuralları içermeyen kopya sunuluyordu.)
	 *
	 * Sürüm eklenti sürümü + dosyanın son değişiklik zamanıdır: dosya her
	 * değiştiğinde adres kendiliğinden değişir, hiçbir sürüm numarasını elle
	 * yükseltmek gerekmez. Dosya okunamıyorsa eklenti sürümüne düşülür.
	 *
	 * `filemtime()` sonucu istek boyunca saklanır; aynı dosya birden çok yerde
	 * kuyruğa alınsa da disk yalnızca bir kez okunur. (Aynı desen zaten
	 * restoran-menu modülünün kendi kodunda var — bkz.
	 * RMA_Helpers_Trait::asset_version.)
	 *
	 * @param string $relative_path Eklenti köküne göreli yol, ör. 'assets/css/admin.css'.
	 * @return string
	 */
	public static function asset_version( $relative_path ) {
		static $bellek = array();

		$relative_path = ltrim( (string) $relative_path, '/' );

		if ( isset( $bellek[ $relative_path ] ) ) {
			return $bellek[ $relative_path ];
		}

		// is_file() şart: boş ya da dizin gösteren bir yol için is_readable()
		// true döner ve filemtime() KLASÖRÜN zamanını verirdi — o zaman da
		// klasöre her dosya eklendiğinde ilgisiz varlıkların adresi değişirdi.
		$yol   = QRMS_PLUGIN_DIR . $relative_path;
		$zaman = ( '' !== $relative_path && is_file( $yol ) ) ? filemtime( $yol ) : false;

		$bellek[ $relative_path ] = false !== $zaman
			? QRMS_VERSION . '.' . (int) $zaman
			: QRMS_VERSION;

		return $bellek[ $relative_path ];
	}

	/**
	 * Tek bir modülün görünen ismini döndürür.
	 *
	 * @param string $slug Modül slug'ı.
	 * @return string Bilinen bir slug değilse slug'ın kendisi döner.
	 */
	public static function get_module_name( $slug ) {
		$modules = self::get_modules();

		return isset( $modules[ $slug ] ) ? $modules[ $slug ] : (string) $slug;
	}

	/**
	 * Slug bilinen modüllerden biri mi?
	 *
	 * @param string $slug Modül slug'ı.
	 * @return bool
	 */
	public static function is_valid_module( $slug ) {
		return in_array( $slug, self::MODULE_SLUGS, true );
	}

	/**
	 * Gelen listeyi bilinen slug'lara indirger ve sabit sıraya sokar.
	 *
	 * @param mixed $modules Sunucudan veya option'dan gelen ham liste.
	 * @return string[] Temizlenmiş slug listesi.
	 */
	public static function sanitize_module_list( $modules ) {
		if ( ! is_array( $modules ) ) {
			return array();
		}

		$clean = array();

		foreach ( $modules as $slug ) {
			if ( ! is_scalar( $slug ) ) {
				continue;
			}

			$slug = sanitize_key( (string) $slug );

			if ( self::is_valid_module( $slug ) && ! in_array( $slug, $clean, true ) ) {
				$clean[] = $slug;
			}
		}

		// Sabit modül sırasını koru.
		return array_values( array_intersect( self::MODULE_SLUGS, $clean ) );
	}

	/**
	 * Lisans durumunun okunabilir Türkçe karşılığı.
	 *
	 * @param string $status qrms_last_status değeri.
	 * @return string
	 */
	public static function get_status_label( $status ) {
		$labels = array(
			'active'          => __( 'Aktif', 'qrms' ),
			'inactive'        => __( 'Pasif', 'qrms' ),
			'domain_mismatch' => __( 'Alan adı uyuşmuyor', 'qrms' ),
			'invalid'         => __( 'Geçersiz anahtar', 'qrms' ),
			'unreachable'     => __( 'Sunucuya ulaşılamıyor', 'qrms' ),
		);

		if ( isset( $labels[ $status ] ) ) {
			return $labels[ $status ];
		}

		return __( 'Bilinmiyor', 'qrms' );
	}

	/**
	 * Lisans durumuna karşılık gelen kullanıcı mesajı (wizard ve ayarlar ekranı).
	 *
	 * @param string $status qrms_last_status değeri.
	 * @return string
	 */
	public static function get_status_message( $status ) {
		switch ( $status ) {
			case 'active':
				return __( 'Lisansınız doğrulandı, tanımlı modülleriniz aktif edildi.', 'qrms' );

			case 'invalid':
				return __( 'Geçersiz API anahtarı. Lütfen kontrol edip tekrar deneyin.', 'qrms' );

			case 'inactive':
				return __( 'Bu lisans şu anda pasif durumda. Lütfen sağlayıcınızla iletişime geçin.', 'qrms' );

			case 'domain_mismatch':
				return __( 'Bu API anahtarı başka bir alan adına kayıtlı. Lütfen doğru anahtarı kullandığınızdan emin olun.', 'qrms' );

			case 'unreachable':
			default:
				return __( 'Sunucuya bağlanılamadı. İnternet bağlantınızı kontrol edip tekrar deneyin.', 'qrms' );
		}
	}

	/**
	 * Bu WordPress kurulumunun lisans sunucusuna bildirdiği alan adı.
	 *
	 * @return string Şema ve www olmadan host adı (ör. "restoran.com").
	 */
	public static function get_site_domain() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( ! $host ) {
			$host = home_url();
		}

		return preg_replace( '/^www\./i', '', strtolower( (string) $host ) );
	}

	/**
	 * Zaman damgasını sitenin tarih/saat formatıyla biçimlendirir.
	 *
	 * @param int $timestamp Unix zaman damgası.
	 * @return string Boş/0 ise tire döner.
	 */
	public static function format_datetime( $timestamp ) {
		$timestamp = (int) $timestamp;

		if ( $timestamp <= 0 ) {
			return '—';
		}

		return wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			$timestamp
		);
	}
}
