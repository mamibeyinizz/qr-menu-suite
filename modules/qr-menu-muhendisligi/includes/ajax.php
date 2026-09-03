<?php
/**
 * Menü mühendisliği AJAX uçları.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qrms_mm_ajax_maliyet_kaydet' ) ) {
	/**
	 * Tek ürün maliyetini kaydeder.
	 *
	 * @return void
	 */
	function qrms_mm_ajax_maliyet_kaydet() {
		check_ajax_referer( 'qrms_mm_admin', 'nonce' );

		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'mesaj' => __( 'Yetkiniz yok.', 'qrms' ) ), 403 );
		}

		$id      = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$maliyet = isset( $_POST['maliyet'] ) ? (float) wp_unslash( $_POST['maliyet'] ) : 0;
		$kaynak  = isset( $_POST['kaynak'] ) ? sanitize_key( wp_unslash( $_POST['kaynak'] ) ) : 'manuel';
		$recete  = isset( $_POST['recete'] ) ? json_decode( wp_unslash( $_POST['recete'] ), true ) : array();

		if ( $id <= 0 || 'rma_menu_item' !== get_post_type( $id ) ) {
			wp_send_json_error( array( 'mesaj' => __( 'Geçersiz ürün.', 'qrms' ) ) );
		}

		if ( 'recete' === $kaynak && is_array( $recete ) ) {
			$maliyet = QRMS_MM_Maliyet::receteden_hesapla( $recete );
			QRMS_MM_Maliyet::maliyet_kaydet( $id, $maliyet, 'recete', $recete );
		} else {
			QRMS_MM_Maliyet::maliyet_kaydet( $id, $maliyet, 'manuel' );
		}

		$fiyat = (float) get_post_meta( $id, 'rma_price', true );
		$cm    = $fiyat - $maliyet;
		$marj  = $fiyat > 0 ? ( $cm / $fiyat ) * 100 : 0;

		wp_send_json_success(
			array(
				'maliyet' => $maliyet,
				'cm'      => $cm,
				'marj'    => round( $marj, 1 ),
			)
		);
	}
	add_action( 'wp_ajax_qrms_mm_maliyet_kaydet', 'qrms_mm_ajax_maliyet_kaydet' );
}

if ( ! function_exists( 'qrms_mm_ajax_toplu_maliyet' ) ) {
	/**
	 * Seçili ürünlere yüzde maliyet uygular.
	 *
	 * @return void
	 */
	function qrms_mm_ajax_toplu_maliyet() {
		check_ajax_referer( 'qrms_mm_admin', 'nonce' );

		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'mesaj' => __( 'Yetkiniz yok.', 'qrms' ) ), 403 );
		}

		$ids     = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();
		$yuzde   = isset( $_POST['yuzde'] ) ? (float) wp_unslash( $_POST['yuzde'] ) : 0;
		$guncellenen = 0;

		foreach ( $ids as $id ) {
			if ( $id <= 0 || 'rma_menu_item' !== get_post_type( $id ) ) {
				continue;
			}
			$fiyat = (float) get_post_meta( $id, 'rma_price', true );
			if ( $fiyat <= 0 ) {
				continue;
			}
			$maliyet = round( $fiyat * ( $yuzde / 100 ), 2 );
			QRMS_MM_Maliyet::maliyet_kaydet( $id, $maliyet, 'manuel' );
			$guncellenen++;
		}

		wp_send_json_success( array( 'guncellenen' => $guncellenen ) );
	}
	add_action( 'wp_ajax_qrms_mm_toplu_maliyet', 'qrms_mm_ajax_toplu_maliyet' );
}

if ( ! function_exists( 'qrms_mm_ajax_malzeme_kaydet' ) ) {
	/**
	 * Malzeme fiyatlarını kaydeder.
	 *
	 * @return void
	 */
	function qrms_mm_ajax_malzeme_kaydet() {
		check_ajax_referer( 'qrms_mm_admin', 'nonce' );

		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'mesaj' => __( 'Yetkiniz yok.', 'qrms' ) ), 403 );
		}

		$raw = isset( $_POST['fiyatlar'] ) ? json_decode( wp_unslash( $_POST['fiyatlar'] ), true ) : array();
		if ( ! is_array( $raw ) ) {
			wp_send_json_error( array( 'mesaj' => __( 'Geçersiz veri.', 'qrms' ) ) );
		}

		QRMS_MM_Maliyet::malzeme_fiyatlari_kaydet( $raw );
		wp_send_json_success();
	}
	add_action( 'wp_ajax_qrms_mm_malzeme_kaydet', 'qrms_mm_ajax_malzeme_kaydet' );
}

if ( ! function_exists( 'qrms_mm_ajax_ayarlar_kaydet' ) ) {
	/**
	 * Modül ayarlarını kaydeder.
	 *
	 * @return void
	 */
	function qrms_mm_ajax_ayarlar_kaydet() {
		check_ajax_referer( 'qrms_mm_admin', 'nonce' );

		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'mesaj' => __( 'Yetkiniz yok.', 'qrms' ) ), 403 );
		}

		$ayarlar = array(
			'populerlik_esigi'  => isset( $_POST['populerlik_esigi'] ) ? (float) wp_unslash( $_POST['populerlik_esigi'] ) : 0.70,
			'fire_yuzdesi'      => isset( $_POST['fire_yuzdesi'] ) ? (float) wp_unslash( $_POST['fire_yuzdesi'] ) : 0,
			'kdv_dahil'         => ! empty( $_POST['kdv_dahil'] ) ? 1 : 0,
			'varsayilan_aralik' => isset( $_POST['varsayilan_aralik'] ) ? absint( $_POST['varsayilan_aralik'] ) : 30,
		);

		QRMS_MM_Maliyet::ayarlari_kaydet( $ayarlar );
		wp_send_json_success();
	}
	add_action( 'wp_ajax_qrms_mm_ayarlar_kaydet', 'qrms_mm_ajax_ayarlar_kaydet' );
}
