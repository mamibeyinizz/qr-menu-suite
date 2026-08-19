<?php
/**
 * Masa doğrulama ve isteğe bağlı sayfa kilidi.
 *
 * Davranış:
 *  - Ana sayfa HERKESE AÇIK kalır. Menü zaten ana sayfada (/?masa=masa-31)
 *    yayınlandığı için sayfa bazlı kilit varsayılan olarak KAPALIDIR.
 *  - Adreste ?masa=X varsa ve bu slug qrm_tables'ta kayıtlı değilse, sahte
 *    QR üretilmiş demektir — kilit ekranı gösterilir.
 *  - Ayrıca ayrı bir menü sayfası kullanan siteler için, korunacak sayfa
 *    slug'ları QR Menü → Oturum Ayarları'ndan tanımlanabilir.
 *
 * Chatbot / sepet gibi bileşenler SAYFA bazlı değil OTURUM bazlı korunur;
 * geçerli oturum yoksa kısa kodlar bilgi kutusu basar (bkz. shortcode-*.php).
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sayfa bazlı kilidin uygulanacağı slug listesi.
 *
 * @return string[]
 */
if ( ! function_exists( 'qmo_korumali_sluglar' ) ) {
	function qmo_korumali_sluglar() {
		$ham     = (string) get_option( 'qmo_korumali_sayfalar', '' );
		$sluglar = array_filter( array_map( 'sanitize_title', explode( ',', $ham ) ) );
		/**
		 * Korunan sayfa slug'larını değiştir.
		 *
		 * @param string[] $sluglar Slug listesi.
		 */
		return apply_filters( 'qmo_korumali_sluglar', $sluglar );
	}
}

/**
 * Bu istek, sayfa bazlı kilidin uygulanacağı bir sayfa mı?
 *
 * @return bool
 */
if ( ! function_exists( 'qmo_korumali_sayfa_mi' ) ) {
	function qmo_korumali_sayfa_mi() {
		// Ana sayfa hiçbir koşulda kilitlenmez.
		if ( is_front_page() || is_home() ) {
			return false;
		}
		if ( ! is_page() ) {
			return false;
		}

		$sluglar = qmo_korumali_sluglar();
		if ( empty( $sluglar ) ) {
			return false;
		}

		$post = get_queried_object();
		if ( ! $post || empty( $post->post_name ) ) {
			return false;
		}

		return in_array( $post->post_name, $sluglar, true );
	}
}

/**
 * Kilit ekranı — koyu + altın. İsteği sonlandırır.
 *
 * @param string $mesaj Gösterilecek açıklama.
 */
if ( ! function_exists( 'qmo_kilit_ekrani' ) ) {
	function qmo_kilit_ekrani( $mesaj ) {
		status_header( 403 );
		nocache_headers();
		?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Oturum Gerekli</title>
<link rel="stylesheet" href="<?php echo esc_url( QRMS_PLUGIN_URL . 'modules/qr-masa-oturum-guvenligi/assets/css/kilit.css?ver=' . QRMS_VERSION ); ?>">
</head>
<body>
	<div class="qmo-kilit-kart">
		<div class="qmo-kilit-ikon">🔒</div>
		<h1>Oturum Gerekli</h1>
		<p><?php echo esc_html( $mesaj ); ?></p>
	</div>
</body>
</html>
		<?php
		exit;
	}
}

if ( ! defined( 'QRSERVIS_KILIT_YUKLENDI' ) ) {
	define( 'QRSERVIS_KILIT_YUKLENDI', true );

	add_action( 'template_redirect', 'qmo_masa_dogrulama', 1 );

	/**
	 * Masa doğrulama denetimi.
	 */
	function qmo_masa_dogrulama() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		// Yöneticiler kilitlenmez (masaları yönetirken sitede gezerler).
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return;
		}

		// 1) Adreste masa parametresi varsa slug gerçekten kayıtlı olmalı.
		$gelen_masa = isset( $_GET['masa'] ) ? sanitize_title( wp_unslash( $_GET['masa'] ) ) : '';
		if ( '' !== $gelen_masa ) {
			if ( ! qmo_masa_gecerli_mi( $gelen_masa ) ) {
				qmo_kilit_ekrani( 'Bu masa için geçerli bir QR kod bulunamadı. Lütfen masanızdaki QR kodu okutun.' );
			}
			return; // Geçerli QR — oturum init aşamasında zaten açıldı.
		}

		// 2) Sayfa bazlı kilit (varsayılan: kapalı).
		if ( ! qmo_korumali_sayfa_mi() ) {
			return;
		}

		if ( ! qmo_oturum() ) {
			qmo_kilit_ekrani( 'Oturumunuz sona erdi. Devam etmek için masadaki QR kodu tekrar okutun.' );
		}
		// Not: idle sayacı qmo_oturum_init() içinde her sayfa gezinmesinde tazelenir.
	}
}

/* -------------------------------------------------------------------------
 * Geriye dönük uyumluluk
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'qrservis_korumali_sayfa_mi' ) ) {
	/**
	 * @deprecated qmo_korumali_sayfa_mi() kullanın.
	 * @return bool
	 */
	function qrservis_korumali_sayfa_mi() {
		return qmo_korumali_sayfa_mi();
	}
}

if ( ! function_exists( 'qrservis_kilit_ekrani' ) ) {
	/**
	 * @deprecated qmo_kilit_ekrani() kullanın.
	 * @param string $mesaj Mesaj.
	 */
	function qrservis_kilit_ekrani( $mesaj ) {
		qmo_kilit_ekrani( $mesaj );
	}
}
