<?php
/**
 * Servis paneli AJAX uçları.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qrms_sp_ajax_liste' ) ) {
	/**
	 * Çağrı listesini döner.
	 *
	 * @return void
	 */
	function qrms_sp_ajax_liste() {
		check_ajax_referer( 'qrms_sp_panel', 'nonce' );

		if ( ! QRMS_SP_Rol::yetkili_mi() ) {
			wp_send_json_error( array( 'mesaj' => __( 'Yetkiniz yok.', 'qrms' ) ), 403 );
		}

		$since = isset( $_POST['since'] ) ? sanitize_text_field( wp_unslash( $_POST['since'] ) ) : '';
		$son   = isset( $_POST['son_gorulen'] ) ? sanitize_text_field( wp_unslash( $_POST['son_gorulen'] ) ) : '';

		$liste = QRMS_SP_Veri::liste( $since ? $since : gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '-24 hours' ) ), 100 );

		if ( is_wp_error( $liste ) ) {
			wp_send_json_error( array( 'mesaj' => $liste->get_error_message() ) );
		}

		$ayarlar = QRMS_SP_Veri::ayarlar();
		$tipler  = $ayarlar['tipler'];

		$filtre = array();
		foreach ( $liste as $row ) {
			if ( ! in_array( $row['tip'], $tipler, true ) ) {
				continue;
			}
			if ( '' !== $son && $row['createdAt'] <= $son && $row['guncellendi'] <= $son ) {
				continue;
			}
			$filtre[] = $row;
		}

		wp_send_json_success( array( 'kayitlar' => $filtre ) );
	}
	add_action( 'wp_ajax_qrms_sp_liste', 'qrms_sp_ajax_liste' );
}

if ( ! function_exists( 'qrms_sp_ajax_durum' ) ) {
	/**
	 * Durum günceller.
	 *
	 * @return void
	 */
	function qrms_sp_ajax_durum() {
		check_ajax_referer( 'qrms_sp_panel', 'nonce' );

		if ( ! QRMS_SP_Rol::yetkili_mi() ) {
			wp_send_json_error( array( 'mesaj' => __( 'Yetkiniz yok.', 'qrms' ) ), 403 );
		}

		$id     = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
		$durum  = isset( $_POST['durum'] ) ? sanitize_key( wp_unslash( $_POST['durum'] ) ) : '';
		$mevcut = isset( $_POST['mevcut'] ) ? sanitize_key( wp_unslash( $_POST['mevcut'] ) ) : '';

		if ( '' === $id || '' === $durum ) {
			wp_send_json_error( array( 'mesaj' => __( 'Eksik parametre.', 'qrms' ) ) );
		}

		$res = QRMS_SP_Veri::durum_guncelle( $id, $durum, $mevcut );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'mesaj' => $res->get_error_message() ) );
		}

		wp_send_json_success();
	}
	add_action( 'wp_ajax_qrms_sp_durum', 'qrms_sp_ajax_durum' );
}

if ( ! function_exists( 'qrms_sp_ajax_ayarlar' ) ) {
	/**
	 * Ayarları kaydeder (yönetici).
	 *
	 * @return void
	 */
	function qrms_sp_ajax_ayarlar() {
		check_ajax_referer( 'qrms_sp_ayarlar', 'nonce' );

		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'mesaj' => __( 'Yetkiniz yok.', 'qrms' ) ), 403 );
		}

		$tipler = isset( $_POST['tipler'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['tipler'] ) ) : array();

		QRMS_SP_Veri::ayarlari_kaydet(
			array(
				'ses_acik'           => ! empty( $_POST['ses_acik'] ) ? 1 : 0,
				'esik_sari'          => isset( $_POST['esik_sari'] ) ? absint( $_POST['esik_sari'] ) : 3,
				'esik_kirmizi'       => isset( $_POST['esik_kirmizi'] ) ? absint( $_POST['esik_kirmizi'] ) : 7,
				'otomatik_tamam'     => isset( $_POST['otomatik_tamam'] ) ? absint( $_POST['otomatik_tamam'] ) : 120,
				'yenileme_araligi'   => isset( $_POST['yenileme_araligi'] ) ? absint( $_POST['yenileme_araligi'] ) : 5,
				'tipler'             => $tipler,
			)
		);

		wp_send_json_success();
	}
	add_action( 'wp_ajax_qrms_sp_ayarlar_kaydet', 'qrms_sp_ajax_ayarlar' );
}
