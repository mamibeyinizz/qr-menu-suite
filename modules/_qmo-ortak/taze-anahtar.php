<?php
/**
 * Önbellek dayanıklı masa oturumu ve nonce tazeleme.
 *
 * SORUN: Tam sayfa önbelleği (LiteSpeed vb.) HIT verdiğinde PHP çalışmaz:
 *   - `qr_masa_token` çerezi yazılmaz (oturum hiç açılmaz),
 *   - HTML'e gömülü nonce'lar sayfanın önbelleğe alındığı ana aittir.
 * Sonuç: sipariş (REST 403 rest_cookie_invalid_nonce), garson/hesap çağrısı,
 * geri bildirim formu ve analitik sessizce düşer.
 *
 * ÇÖZÜM (iki katman):
 *   1) QR girişi (`?masa=`) yanıtı önbelleğe ALINMAZ — çerez her okutmada
 *      gerçekten yazılır.
 *   2) Sayfa bayatsa (`t` damgası eşikten eski) ya da adreste masa varken
 *      sayfa önbellekten gelmişse, `taze-anahtar.js` önbelleğe alınmayan
 *      admin-ajax ucundan oturumu açar/tazeler ve güncel nonce'ları alıp
 *      sayfadaki global yapılandırmalara yazar. Taze sayfada istek ATILMAZ.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/** Bu yaştan (sn) eski sayfa bayat sayılır ve tazelenir. */
if ( ! defined( 'QMO_TAZE_ESIK' ) ) {
	define( 'QMO_TAZE_ESIK', 600 );
}

/* -------------------------------------------------------------------------
 * 1) QR girişi önbelleğe alınmaz
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'qmo_masa_istegi_onbellege_alinmasin' ) ) {
	/**
	 * `?masa=` taşıyan ön yüz isteğini tam sayfa önbelleğinden çıkarır.
	 *
	 * qmo_oturum_init() (init, 1) çerezi bu istekte yazar; yanıt önbelleğe
	 * girerse sonraki okutmalarda çerez hiç yazılmaz.
	 */
	function qmo_masa_istegi_onbellege_alinmasin() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || empty( $_GET['masa'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		// LiteSpeed Cache eklentisi API'si (eklenti yoksa no-op).
		do_action( 'litespeed_control_set_nocache', 'qr-menu-suite: masa oturumu' );

		if ( ! headers_sent() ) {
			// Sunucu düzeyi LSCache, eklenti olmadan da bu başlığa uyar.
			header( 'X-LiteSpeed-Cache-Control: no-cache' );
			nocache_headers();
		}
	}
	add_action( 'init', 'qmo_masa_istegi_onbellege_alinmasin', 0 );
}

/* -------------------------------------------------------------------------
 * 2) Tazeleme ucu
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'qmo_taze_anahtarlar' ) ) {
	/**
	 * Ön yüzün ihtiyaç duyduğu güncel nonce'lar.
	 *
	 * Modüller `qmo_taze_anahtarlar` filtresiyle kendi anahtarlarını
	 * ekleyebilir; burada suite içindeki bilinen tüketiciler var.
	 *
	 * @return array<string,string>
	 */
	function qmo_taze_anahtarlar() {
		$veri = array(
			'qmo'  => wp_create_nonce( QMO_NONCE_ACTION ),
			'rest' => wp_create_nonce( 'wp_rest' ),
			'rma'  => wp_create_nonce( 'rma_ajax_nonce' ),
		);

		if ( class_exists( 'QRMS_Analitik' ) ) {
			$veri['onyuz'] = wp_create_nonce( QRMS_Analitik::NONCE_ONYUZ );
		}

		if ( function_exists( 'qrm_pro_make_ts_token' ) ) {
			$veri['yorumTs']     = qrm_pro_make_ts_token();
			$veri['yorumNonce']  = wp_create_nonce( 'qrm_submit_review' );
			$veri['formNonce']   = wp_create_nonce( 'qrm_submit_custom_form' );
			$veri['yorumYukle']  = wp_create_nonce( 'qrm_load_reviews' );
			$veri['odul']        = wp_create_nonce( 'qrm_reward_request_code' );
		}

		return (array) apply_filters( 'qmo_taze_anahtarlar', $veri );
	}
}

if ( ! function_exists( 'qmo_taze_anahtar_ajax' ) ) {
	/**
	 * admin-ajax: oturumu aç/tazele, güncel nonce'ları döndür.
	 *
	 * Oturum kuralları qmo_oturum_init() ile aynıdır: geçerli `masa` geldiyse
	 * ve oturum yoksa ya da başka masaya aitse yeni oturum açılır; yoksa
	 * mevcut oturumun idle sayacı yenilenir.
	 */
	function qmo_taze_anahtar_ajax() {
		nocache_headers();

		$masa   = isset( $_POST['masa'] ) ? sanitize_title( wp_unslash( $_POST['masa'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce'u yenileyen uç; oturum açma kuralı GET ?masa= ile aynı.
		$mevcut = qmo_oturum();

		if ( '' !== $masa ) {
			// Masa listesi numaralandırma koruması: sayfa girişiyle aynı sayaç.
			$izin = true;
			if ( function_exists( 'qmo_ip_hash' ) && function_exists( 'qmo_sayac_arttir' ) ) {
				$izin = qmo_sayac_arttir( 'qmo_masa_deneme_' . qmo_ip_hash(), MINUTE_IN_SECONDS ) <= 20;
			}

			if ( $izin && qmo_masa_gecerli_mi( $masa ) && ( ! $mevcut || $mevcut['masa'] !== $masa ) ) {
				qmo_cookie_yaz( QMO_Oturum::token_uret( $masa ) );
				$mevcut = qmo_oturum();
			}
		} elseif ( $mevcut ) {
			qmo_cookie_yaz( QMO_Oturum::token_uret( $mevcut['masa'], $mevcut['issued'] ) );
		}

		$veri         = qmo_taze_anahtarlar();
		$veri['masa'] = ( is_array( $mevcut ) && ! empty( $mevcut['masa'] ) ) ? (string) $mevcut['masa'] : '';

		wp_send_json_success( $veri );
	}
	add_action( 'wp_ajax_qmo_taze_anahtar', 'qmo_taze_anahtar_ajax' );
	add_action( 'wp_ajax_nopriv_qmo_taze_anahtar', 'qmo_taze_anahtar_ajax' );
}

/* -------------------------------------------------------------------------
 * 3) İstemci betiği (head, bağımlılıksız, ~1 KB)
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'qmo_taze_anahtar_betigi' ) ) {
	/**
	 * Betiği head'e ekler; `t` sayfanın üretildiği an (önbellekte donar).
	 */
	function qmo_taze_anahtar_betigi() {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_script(
			'qmo-taze-anahtar',
			QRMS_PLUGIN_URL . 'modules/_qmo-ortak/assets/js/taze-anahtar.js',
			array(),
			QRMS_Helpers::asset_version( 'modules/_qmo-ortak/assets/js/taze-anahtar.js' ),
			false
		);

		wp_add_inline_script(
			'qmo-taze-anahtar',
			'window.qmoTazeCfg=' . wp_json_encode(
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					't'       => time(),
					'esik'    => (int) QMO_TAZE_ESIK,
				)
			) . ';',
			'before'
		);
	}
	add_action( 'wp_enqueue_scripts', 'qmo_taze_anahtar_betigi', 1 );
}
