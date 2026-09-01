<?php
/**
 * QR Chatbot — yönetim AJAX uçları.
 *
 * Hub aç/kapa, geçmiş silme, cevaplanamayan soru eylemleri.
 * Her uç current_user_can + nonce kontrolü yapar.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_qmo_chatbot_toggle', 'qmo_chatbot_ajax_toggle' );
add_action( 'wp_ajax_qmo_chatbot_gecmis_sil', 'qmo_chatbot_ajax_gecmis_sil' );
add_action( 'wp_ajax_qmo_chatbot_gecmis_toplu_sil', 'qmo_chatbot_ajax_gecmis_toplu_sil' );
add_action( 'wp_ajax_qmo_chatbot_oturum_getir', 'qmo_chatbot_ajax_oturum_getir' );
add_action( 'wp_ajax_qmo_chatbot_bilinmeyen_coz', 'qmo_chatbot_ajax_bilinmeyen_coz' );
add_action( 'wp_ajax_qmo_chatbot_bilinmeyen_soruya', 'qmo_chatbot_ajax_bilinmeyen_soruya' );

/**
 * Hub anahtarı — tek tıkla kaydet.
 *
 * @return void
 */
function qmo_chatbot_ajax_toggle() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'mesaj' => 'Yetkiniz yok.' ), 403 );
	}
	check_ajax_referer( 'qmo_chatbot_toggle', 'nonce' );

	$aktif = isset( $_POST['aktif'] ) && 'yes' === sanitize_key( wp_unslash( $_POST['aktif'] ) );
	update_option( QMO_CHATBOT_OPT_AKTIF, $aktif ? 'yes' : 'no' );

	wp_send_json_success(
		array(
			'aktif' => $aktif ? 'yes' : 'no',
			'mesaj' => $aktif ? 'Sohbet asistanı açık.' : 'Sohbet asistanı kapalı.',
		)
	);
}

/**
 * Tek geçmiş kaydı sil.
 *
 * @return void
 */
function qmo_chatbot_ajax_gecmis_sil() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'mesaj' => 'Yetkiniz yok.' ), 403 );
	}
	check_ajax_referer( 'qmo_chatbot_gecmis', 'nonce' );

	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	if ( $id < 1 ) {
		wp_send_json_error( array( 'mesaj' => 'Kayıt bulunamadı.' ) );
	}

	QMO_Chatbot_DB::mesaj_sil( $id );
	wp_send_json_success( array( 'mesaj' => 'Kayıt silindi.' ) );
}

/**
 * Toplu geçmiş silme.
 *
 * @return void
 */
function qmo_chatbot_ajax_gecmis_toplu_sil() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'mesaj' => 'Yetkiniz yok.' ), 403 );
	}
	check_ajax_referer( 'qmo_chatbot_gecmis', 'nonce' );

	$ids = isset( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : array();
	$silinen = QMO_Chatbot_DB::mesaj_toplu_sil( $ids );
	wp_send_json_success( array( 'mesaj' => $silinen . ' kayıt silindi.', 'adet' => $silinen ) );
}

/**
 * Bir oturumun tam yazışması.
 *
 * @return void
 */
function qmo_chatbot_ajax_oturum_getir() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'mesaj' => 'Yetkiniz yok.' ), 403 );
	}
	check_ajax_referer( 'qmo_chatbot_gecmis', 'nonce' );

	$oturum = isset( $_POST['oturum_id'] ) ? sanitize_text_field( wp_unslash( $_POST['oturum_id'] ) ) : '';
	$satirlar = QMO_Chatbot_DB::oturum_yazismasi( $oturum );
	$cikti    = array();

	foreach ( $satirlar as $satir ) {
		$cikti[] = array(
			'id'         => (int) $satir->id,
			'soru'       => $satir->soru,
			'cevap'      => $satir->cevap,
			'masa_no'    => $satir->masa_no,
			'created_at' => $satir->created_at,
		);
	}

	wp_send_json_success( array( 'satirlar' => $cikti ) );
}

/**
 * Cevaplanamayan soruyu çözüldü işaretle.
 *
 * @return void
 */
function qmo_chatbot_ajax_bilinmeyen_coz() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'mesaj' => 'Yetkiniz yok.' ), 403 );
	}
	check_ajax_referer( 'qmo_chatbot_bilinmeyen', 'nonce' );

	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	if ( $id < 1 ) {
		wp_send_json_error( array( 'mesaj' => 'Kayıt bulunamadı.' ) );
	}

	QMO_Chatbot_DB::bilinmeyen_coz( $id );
	wp_send_json_success( array( 'mesaj' => 'Çözüldü olarak işaretlendi.' ) );
}

/**
 * Cevaplanamayan soruyu hazır sorulara ekle.
 *
 * @return void
 */
function qmo_chatbot_ajax_bilinmeyen_soruya() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'mesaj' => 'Yetkiniz yok.' ), 403 );
	}
	check_ajax_referer( 'qmo_chatbot_bilinmeyen', 'nonce' );

	$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	$row = QMO_Chatbot_DB::bilinmeyen_al( $id );
	if ( ! $row ) {
		wp_send_json_error( array( 'mesaj' => 'Kayıt bulunamadı.' ) );
	}

	$liste   = qmo_chatbot_sorulari_oku();
	$liste[] = array(
		'id'       => 'u' . $id,
		'label'    => $row->soru,
		'question' => $row->soru,
		'enabled'  => 1,
	);
	update_option( 'qmo_chatbot_quick_replies', $liste );
	QMO_Chatbot_DB::bilinmeyen_coz( $id );

	wp_send_json_success( array( 'mesaj' => 'Hazır sorulara eklendi.' ) );
}
