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
add_action( 'wp_ajax_qmo_chatbot_oneri_urun_kaydet', 'qmo_chatbot_ajax_oneri_urun_kaydet' );
add_action( 'wp_ajax_qmo_chatbot_oneri_kural_ekle', 'qmo_chatbot_ajax_oneri_kural_ekle' );
add_action( 'wp_ajax_qmo_chatbot_oneri_kural_sil', 'qmo_chatbot_ajax_oneri_kural_sil' );
add_action( 'wp_ajax_qmo_chatbot_oneri_kural_toggle', 'qmo_chatbot_ajax_oneri_kural_toggle' );

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

/**
 * Öneri havuzu ürün meta alanlarını toplu günceller.
 *
 * @return void
 */
function qmo_chatbot_ajax_oneri_urun_kaydet() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'mesaj' => 'Yetkiniz yok.' ), 403 );
	}
	check_ajax_referer( 'qmo_chatbot_oneri', 'nonce' );

	$ham = isset( $_POST['urunler'] ) ? (array) wp_unslash( $_POST['urunler'] ) : array();
	if ( empty( $ham ) ) {
		wp_send_json_error( array( 'mesaj' => 'Kaydedilecek ürün yok.' ) );
	}

	$adet = 0;
	foreach ( $ham as $satir ) {
		if ( ! is_array( $satir ) ) {
			continue;
		}
		$id = isset( $satir['id'] ) ? absint( $satir['id'] ) : 0;
		if ( $id < 1 || 'rma_menu_item' !== get_post_type( $id ) ) {
			continue;
		}
		$dahil   = ! empty( $satir['dahil'] ) ? 1 : 0;
		$agirlik = isset( $satir['agirlik'] ) ? max( 0, min( 100, absint( $satir['agirlik'] ) ) ) : 50;

		update_post_meta( $id, '_qmo_oneri_dahil', $dahil );
		update_post_meta( $id, '_qmo_oneri_agirlik', $agirlik );
		++$adet;
	}

	if ( $adet < 1 ) {
		wp_send_json_error( array( 'mesaj' => 'Geçerli ürün bulunamadı.' ) );
	}

	wp_send_json_success( array( 'mesaj' => $adet . ' ürün güncellendi.', 'adet' => $adet ) );
}

/**
 * Cross-sell öneri kuralı ekler.
 *
 * @return void
 */
function qmo_chatbot_ajax_oneri_kural_ekle() {
	global $wpdb;

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'mesaj' => 'Yetkiniz yok.' ), 403 );
	}
	check_ajax_referer( 'qmo_chatbot_oneri', 'nonce' );

	$kaynak  = isset( $_POST['kaynak'] ) ? absint( $_POST['kaynak'] ) : 0;
	$hedef   = isset( $_POST['hedef'] ) ? absint( $_POST['hedef'] ) : 0;
	$agirlik = isset( $_POST['agirlik'] ) ? absint( $_POST['agirlik'] ) : 50;

	if ( $kaynak < 1 || $hedef < 1 ) {
		wp_send_json_error( array( 'mesaj' => 'Kaynak ve hedef ürün seçin.' ) );
	}
	if ( $kaynak === $hedef ) {
		wp_send_json_error( array( 'mesaj' => 'Kaynak ve hedef ürün aynı olamaz.' ) );
	}
	if ( 'rma_menu_item' !== get_post_type( $kaynak ) || 'rma_menu_item' !== get_post_type( $hedef ) ) {
		wp_send_json_error( array( 'mesaj' => 'Geçersiz ürün.' ) );
	}

	if ( ! QMO_Chatbot_DB::kural_ekle( $kaynak, $hedef, $agirlik ) ) {
		wp_send_json_error( array( 'mesaj' => 'Kural kaydedilemedi.' ) );
	}

	$tablo = QMO_Chatbot_DB::oneri_kural_tablosu();
	$kural = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$tablo} WHERE kaynak_urun = %d AND hedef_urun = %d LIMIT 1",
			$kaynak,
			$hedef
		)
	);

	wp_send_json_success(
		array(
			'mesaj' => 'Kural eklendi.',
			'kural' => array(
				'id'          => $kural ? (int) $kural->id : 0,
				'kaynak_urun' => $kaynak,
				'hedef_urun'  => $hedef,
				'agirlik'     => $kural ? (int) $kural->agirlik : max( 0, min( 100, $agirlik ) ),
				'aktif'       => $kural ? (int) $kural->aktif : 1,
			),
		)
	);
}

/**
 * Öneri kuralını siler.
 *
 * @return void
 */
function qmo_chatbot_ajax_oneri_kural_sil() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'mesaj' => 'Yetkiniz yok.' ), 403 );
	}
	check_ajax_referer( 'qmo_chatbot_oneri', 'nonce' );

	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	if ( $id < 1 ) {
		wp_send_json_error( array( 'mesaj' => 'Kayıt bulunamadı.' ) );
	}

	if ( ! QMO_Chatbot_DB::kural_sil( $id ) ) {
		wp_send_json_error( array( 'mesaj' => 'Kural silinemedi.' ) );
	}

	wp_send_json_success( array( 'mesaj' => 'Kural silindi.' ) );
}

/**
 * Öneri kuralının aktif/pasif durumunu değiştirir.
 *
 * @return void
 */
function qmo_chatbot_ajax_oneri_kural_toggle() {
	global $wpdb;

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'mesaj' => 'Yetkiniz yok.' ), 403 );
	}
	check_ajax_referer( 'qmo_chatbot_oneri', 'nonce' );

	$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	$aktif = isset( $_POST['aktif'] ) && '1' === sanitize_key( wp_unslash( $_POST['aktif'] ) ) ? 1 : 0;

	if ( $id < 1 ) {
		wp_send_json_error( array( 'mesaj' => 'Kayıt bulunamadı.' ) );
	}

	$tablo = QMO_Chatbot_DB::oneri_kural_tablosu();
	$sonuc = $wpdb->update(
		$tablo,
		array( 'aktif' => $aktif ),
		array( 'id' => $id ),
		array( '%d' ),
		array( '%d' )
	);

	if ( false === $sonuc ) {
		wp_send_json_error( array( 'mesaj' => 'Durum güncellenemedi.' ) );
	}

	wp_send_json_success( array( 'mesaj' => 'Durum güncellendi.', 'aktif' => $aktif ) );
}
