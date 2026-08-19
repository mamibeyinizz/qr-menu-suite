<?php
/**
 * AJAX: garson_cagir / hesap_iste / qrservis_call — garson ve hesap çağrısı.
 *
 * GÜVENLİK:
 *   - Masa numarası ARTIK $_POST['masa_no'] değerinden alınmıyor. Eskiden
 *     istemci istediği masa numarasını gönderebiliyor, başka masaya çağrı
 *     düşürebiliyordu. Şimdi doğrulanmış oturum cookie'sinden okunuyor.
 *   - Her istek nonce + geçerli oturum ister.
 *   - IP + masa bazlı hız sınırı: aynı çağrı 60 saniyede bir.
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_garson_cagir', 'qmo_ajax_garson_cagir' );
add_action( 'wp_ajax_nopriv_garson_cagir', 'qmo_ajax_garson_cagir' );

add_action( 'wp_ajax_hesap_iste', 'qmo_ajax_hesap_iste' );
add_action( 'wp_ajax_nopriv_hesap_iste', 'qmo_ajax_hesap_iste' );

// [qr_garson_hesap] kısa kodunun kullandığı birleşik uç.
add_action( 'wp_ajax_qrservis_call', 'qmo_ajax_cagri' );
add_action( 'wp_ajax_nopriv_qrservis_call', 'qmo_ajax_cagri' );

/**
 * Garson çağır.
 */
if ( ! function_exists( 'qmo_ajax_garson_cagir' ) ) {
	function qmo_ajax_garson_cagir() {
		qmo_cagri_gonder( 'garson' );
	}
}

/**
 * Hesap iste.
 */
if ( ! function_exists( 'qmo_ajax_hesap_iste' ) ) {
	function qmo_ajax_hesap_iste() {
		qmo_cagri_gonder( 'hesap' );
	}
}

/**
 * Tip parametresi alan birleşik uç.
 */
if ( ! function_exists( 'qmo_ajax_cagri' ) ) {
	function qmo_ajax_cagri() {
		// Nonce her koşulda önce doğrulanır.
		qmo_nonce_dogrula();

		$tip = isset( $_POST['tip'] ) ? sanitize_key( wp_unslash( $_POST['tip'] ) ) : '';
		if ( ! in_array( $tip, array( 'garson', 'hesap' ), true ) ) {
			wp_send_json_error( array( 'msg' => 'Geçersiz istek' ), 400 );
		}
		qmo_cagri_gonder( $tip );
	}
}

/**
 * Çağrıyı doğrula, sınırla ve Firestore'a yaz.
 *
 * @param string $tip 'garson' | 'hesap'.
 */
if ( ! function_exists( 'qmo_cagri_gonder' ) ) {
	function qmo_cagri_gonder( $tip ) {
		// Nonce + geçerli masa oturumu. Başarısızsa burada sonlanır.
		$sess = qmo_oturum_zorla();

		// Masa DAİMA oturumdan; istemcinin gönderdiği masa_no yok sayılır.
		$masa = $sess['masa'];

		if ( ! QMO_Firestore::hazir_mi() ) {
			wp_send_json_error( array( 'msg' => 'Çağrı sistemi şu anda kullanılamıyor.' ), 503 );
		}

		// Hız sınırı: aynı masa + aynı IP, 60 saniyede bir çağrı.
		$saniye = (int) apply_filters( 'qmo_cagri_bekleme', 60, $tip );
		if ( ! qmo_hiz_siniri( 'cagri_' . $tip, $masa, $saniye ) ) {
			wp_send_json_error( array( 'msg' => 'Çağrınız iletildi, lütfen bekleyin.' ), 429 );
		}

		$sonuc = QMO_Firestore::cagri_olustur( $masa, $tip );
		if ( is_wp_error( $sonuc ) ) {
			qmo_log( 'Çağrı yazılamadı: ' . $sonuc->get_error_message() );
			wp_send_json_error( array( 'msg' => 'Çağrı iletilemedi, lütfen tekrar deneyin.' ), 500 );
		}

		wp_send_json_success(
			array(
				'msg'   => 'İletildi',
				'mesaj' => 'Talep alındı.',
			)
		);
	}
}
