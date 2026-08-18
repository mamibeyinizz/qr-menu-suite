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
			'qr-masa-oturum-guvenligi' => __( 'QR Masa Oturum Güvenliği', 'qrms' ),
		);
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
