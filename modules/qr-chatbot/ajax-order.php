<?php
/**
 * AJAX: gemini_bot_siparis — chatbot üzerinden gelen sipariş.
 *
 * GÜVENLİK: Eskiden bu uç korumasızdı ve siparişi kendi sitesine HTTP
 * isteğiyle iletiyordu. Artık:
 *   - qmo_oturum_zorla() ile nonce + geçerli masa oturumu aranır,
 *   - masa numarası oturumdan okunur (istemci gönderemez),
 *   - sipariş, loopback HTTP isteği olmadan doğrudan qmo_siparis_isle()
 *     ile işlenir.
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_gemini_bot_siparis', 'qmo_ajax_bot_siparis' );
add_action( 'wp_ajax_nopriv_gemini_bot_siparis', 'qmo_ajax_bot_siparis' );

/**
 * Chatbot siparişini işle.
 */
if ( ! function_exists( 'qmo_ajax_bot_siparis' ) ) {
	function qmo_ajax_bot_siparis() {
		$sess = qmo_oturum_zorla();

		$items_raw = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : '';
		$items     = json_decode( $items_raw, true );
		if ( ! is_array( $items ) || empty( $items ) ) {
			wp_send_json_error( array( 'mesaj' => 'Geçersiz sipariş' ), 400 );
		}

		// Chatbot zaten Türkçe ürün adı + Türkçe not üretiyor; dil 'tr'
		// işaretlenir (ekstra çeviri turuna gerek yok).
		$sonuc = qmo_siparis_isle( $sess['masa'], $items, 'tr' );

		if ( $sonuc['success'] ) {
			wp_send_json_success( array( 'mesaj' => 'Siparişiniz iletildi.' ) );
		}
		wp_send_json_error( array( 'mesaj' => $sonuc['msg'] ), $sonuc['http'] );
	}
}
