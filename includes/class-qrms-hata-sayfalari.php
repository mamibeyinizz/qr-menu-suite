<?php
/**
 * Eklentinin markalı hata sayfaları.
 *
 * WordPress 404 durumunu (silinmiş veya yanlış slug) aktif temanın çıplak
 * şablonuna bırakmaz: `template_redirect` aşamasında yakalar, kilit ekranı
 * ile aynı koyu + altın belgede basar ve isteği sonlandırır. Böylece tema
 * ne olursa olsun restoran menüsü kendi görsel dilini korur.
 *
 * Paylaşılan `render()` başka bir akıştan da (ör. oturumsuz wp-admin
 * koruması) aynı belgeyi basabilir.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Markalı 404 / hata belgesi.
 */
class QRMS_Hata_Sayfalari {

	/**
	 * Stil dosyasının eklenti köküne göreli yolu.
	 */
	const CSS_YOLU = 'assets/css/hata-sayfalari.css';

	/**
	 * Hook kayıtları.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ) );
	}

	/**
	 * `template_redirect` — 404 ise markalı sayfayı basıp isteği keser.
	 *
	 * Admin / AJAX / REST / cron atlanır; desen `qmo_masa_dogrulama()` ile
	 * aynıdır. Bu uçlar HTML belge beklemez.
	 *
	 * @return void
	 */
	public static function template_redirect() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( ! is_404() ) {
			return;
		}

		self::render();
	}

	/**
	 * Markalı hata belgesini basar ve isteği sonlandırır.
	 *
	 * @param string $baslik Sayfa başlığı. Boşsa varsayılan 404 başlığı.
	 * @param string $mesaj  Gövde metni. Boşsa varsayılan 404 açıklaması.
	 * @return void
	 */
	public static function render( $baslik = '', $mesaj = '' ) {
		status_header( 404 );
		nocache_headers();
		echo self::html( $baslik, $mesaj ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- html() kaçırır.
		exit;
	}

	/**
	 * Bağımsız HTML belgesini üretir (isteği sonlandırmaz).
	 *
	 * @param string $baslik Sayfa başlığı.
	 * @param string $mesaj  Gövde metni.
	 * @return string
	 */
	public static function html( $baslik = '', $mesaj = '' ) {
		$baslik = '' !== (string) $baslik
			? (string) $baslik
			: __( 'Bulunamadı', 'qrms' );
		$mesaj = '' !== (string) $mesaj
			? (string) $mesaj
			: __( 'Aradığınız sayfa bulunamadı veya kaldırılmış olabilir.', 'qrms' );

		$dil = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'language' ) : '';
		if ( '' === $dil ) {
			$dil = 'tr';
		}

		$rtl = function_exists( 'is_rtl' ) && is_rtl();
		$css = QRMS_PLUGIN_URL . self::CSS_YOLU . '?ver=' . QRMS_Helpers::asset_version( self::CSS_YOLU );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( $dil ); ?>"<?php echo $rtl ? ' dir="rtl"' : ''; ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $baslik ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( $css ); ?>">
</head>
<body>
	<div class="qrms-hata-kart">
		<div class="qrms-hata-ikon" aria-hidden="true">404</div>
		<h1><?php echo esc_html( $baslik ); ?></h1>
		<p><?php echo esc_html( $mesaj ); ?></p>
	</div>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}
}
