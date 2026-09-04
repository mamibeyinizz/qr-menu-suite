<?php
/**
 * Menü Mühendisliği AJAX uçları.
 *
 * İkisi de yalnızca yönetim tarafındadır (`wp_ajax_` öneki; `nopriv`
 * karşılığı bilerek YOKTUR) ve hem nonce hem yetenek doğrular.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_qrms_mm_maliyet', 'qrms_mm_ajax_maliyet' );
add_action( 'wp_ajax_qrms_mm_recete', 'qrms_mm_ajax_recete' );

/**
 * İsteği doğrular ve ürün kimliğini döner.
 *
 * @return int Geçerli ürün kimliği (geçersizse çıkış yapılır).
 */
function qrms_mm_ajax_dogrula() {
	check_ajax_referer( 'qrms_mm', 'nonce' );

	if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
		wp_send_json_error( array( 'msg' => __( 'Yetkiniz yok.', 'qrms' ) ), 403 );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce yukarıda.
	$id = isset( $_POST['urun'] ) ? absint( $_POST['urun'] ) : 0;

	if ( ! $id || QRMS_MM_Maliyet::CPT !== get_post_type( $id ) ) {
		wp_send_json_error( array( 'msg' => __( 'Ürün bulunamadı.', 'qrms' ) ), 400 );
	}

	return $id;
}

/**
 * Ürünün güncel katkı payı ve marjını döner.
 *
 * @param int $id Ürün.
 * @return array
 */
function qrms_mm_ajax_yanit( $id ) {
	$fiyat   = QRMS_MM_Maliyet::fiyat( $id );
	$maliyet = QRMS_MM_Maliyet::maliyet( $id );

	if ( null === $fiyat || null === $maliyet ) {
		return array(
			'maliyet' => null === $maliyet ? '' : $maliyet,
			'katki'   => '—',
			'marj'    => '—',
		);
	}

	$katki = $fiyat - $maliyet;

	return array(
		'maliyet' => $maliyet,
		'katki'   => qrms_mm_para( $katki ),
		'marj'    => $fiyat > 0 ? qrms_mm_yuzde( ( $katki / $fiyat ) * 100 ) : '—',
	);
}

/**
 * Satır içi maliyet kaydı.
 *
 * @return void
 */
function qrms_mm_ajax_maliyet() {
	$id = qrms_mm_ajax_dogrula();

	// Reçeteli üründe maliyet elle değiştirilemez: alan zaten salt okunur,
	// istek doğrudan gönderilirse burada reddedilir.
	if ( 'recete' === QRMS_MM_Maliyet::kaynak( $id ) ) {
		wp_send_json_error( array( 'msg' => __( 'Bu ürünün maliyeti reçeteden hesaplanıyor.', 'qrms' ) ), 400 );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce dogrula() içinde.
	$ham = isset( $_POST['maliyet'] ) ? sanitize_text_field( wp_unslash( $_POST['maliyet'] ) ) : '';

	QRMS_MM_Maliyet::maliyet_yaz( $id, '' === trim( $ham ) ? '' : $ham );

	wp_send_json_success( qrms_mm_ajax_yanit( $id ) );
}

/**
 * Reçete kaydı ve maliyet hesabı.
 *
 * @return void
 */
function qrms_mm_ajax_recete() {
	$id = qrms_mm_ajax_dogrula();

	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON aşağıda çözülür, recete_temizle() alan alan temizler.
	$ham = isset( $_POST['recete'] ) ? wp_unslash( $_POST['recete'] ) : '';

	$satirlar = is_string( $ham ) && '' !== $ham ? json_decode( $ham, true ) : array();

	if ( ! is_array( $satirlar ) ) {
		$satirlar = array();
	}

	// Bir üründe 60'tan fazla malzeme satırı gerçekçi değil; kötü niyetli
	// istek meta'yı şişirmesin.
	$satirlar = array_slice( $satirlar, 0, 60 );

	QRMS_MM_Maliyet::recete_yaz( $id, $satirlar );

	$yanit             = qrms_mm_ajax_yanit( $id );
	$yanit['receteli'] = ( 'recete' === QRMS_MM_Maliyet::kaynak( $id ) );

	wp_send_json_success( $yanit );
}
