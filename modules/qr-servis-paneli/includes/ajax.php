<?php
/**
 * Servis Paneli AJAX uçları.
 *
 * Yalnızca oturum açmış personel içindir (`wp_ajax_`; `nopriv` karşılığı
 * bilerek YOKTUR). İkisi de nonce + yetenek doğrular.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_qrms_sp_liste', 'qrms_sp_ajax_liste' );
add_action( 'wp_ajax_qrms_sp_durum', 'qrms_sp_ajax_durum' );

/**
 * Ortak doğrulama.
 *
 * @return void
 */
function qrms_sp_ajax_dogrula() {
	check_ajax_referer( 'qrms_sp', 'nonce' );

	if ( ! current_user_can( QRMS_SP_Rol::YETENEK ) ) {
		wp_send_json_error( array( 'msg' => __( 'Yetkiniz yok.', 'qrms' ) ), 403 );
	}
}

/**
 * Panelin kayıt listesi.
 *
 * @return void
 */
function qrms_sp_ajax_liste() {
	qrms_sp_ajax_dogrula();

	$kayitlar = QRMS_SP_Veri::kayitlar();

	if ( is_wp_error( $kayitlar ) ) {
		wp_send_json_error(
			array(
				'msg'  => $kayitlar->get_error_message(),
				'kod'  => $kayitlar->get_error_code(),
			),
			503
		);
	}

	$ayar = QRMS_SP_Veri::ayarlar();

	wp_send_json_success(
		array(
			'kayitlar'   => $kayitlar,
			'sunucuSaat' => time(),
			'pencere'    => (int) $ayar['tamam_penceresi'] * HOUR_IN_SECONDS,
		)
	);
}

/**
 * Durum değişikliği.
 *
 * @return void
 */
function qrms_sp_ajax_durum() {
	qrms_sp_ajax_dogrula();

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce dogrula() içinde.
	$id   = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
	$eski = isset( $_POST['eski'] ) ? sanitize_key( wp_unslash( $_POST['eski'] ) ) : '';
	$yeni = isset( $_POST['yeni'] ) ? sanitize_key( wp_unslash( $_POST['yeni'] ) ) : '';
	// phpcs:enable

	if ( '' === $id ) {
		wp_send_json_error( array( 'msg' => __( 'Kayıt bulunamadı.', 'qrms' ) ), 400 );
	}

	$sonuc = QRMS_SP_Veri::durum_degistir( $id, $eski, $yeni );

	if ( is_wp_error( $sonuc ) ) {
		wp_send_json_error( array( 'msg' => $sonuc->get_error_message() ), 400 );
	}

	wp_send_json_success( array( 'durum' => $yeni ) );
}
