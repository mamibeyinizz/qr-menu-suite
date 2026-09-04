<?php
/**
 * Menü mühendisliği CSV dışa aktarma.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qrms_mm_export_csv' ) ) {
	/**
	 * Rapor CSV indirme işleyicisi.
	 *
	 * @return void
	 */
	function qrms_mm_export_csv() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'qrms' ), '', array( 'response' => 403 ) );
		}

		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'qrms_mm_csv' ) ) {
			wp_die( esc_html__( 'Geçersiz istek.', 'qrms' ), '', array( 'response' => 403 ) );
		}

		$bas = isset( $_GET['bas'] ) ? sanitize_text_field( wp_unslash( $_GET['bas'] ) ) : '';
		$bit = isset( $_GET['bit'] ) ? sanitize_text_field( wp_unslash( $_GET['bit'] ) ) : '';
		$kat = isset( $_GET['kategori'] ) ? sanitize_text_field( wp_unslash( $_GET['kategori'] ) ) : '';

		if ( '' === $bas || '' === $bit ) {
			wp_die( esc_html__( 'Tarih aralığı gerekli.', 'qrms' ) );
		}

		$rapor   = QRMS_MM_Maliyet::rapor_hesapla( $bas, $bit, $kat );
		$etiket  = QRMS_MM_Hesap::kutu_etiketleri();
		$dosya   = 'menu-muhendisligi-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $dosya . '"' );
		echo "\xEF\xBB\xBF";

		$baslik = array(
			__( 'Ürün', 'qrms' ),
			__( 'Kategori', 'qrms' ),
			__( 'Fiyat', 'qrms' ),
			__( 'Maliyet', 'qrms' ),
			__( 'Katkı Payı', 'qrms' ),
			__( 'Satış', 'qrms' ),
			__( 'Ciro', 'qrms' ),
			__( 'Toplam Katkı', 'qrms' ),
			__( 'Kutu', 'qrms' ),
			__( 'Aksiyon', 'qrms' ),
		);

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, $baslik, ';' );

		foreach ( $rapor['urunler'] as $u ) {
			fputcsv(
				$out,
				array(
					$u['ad'],
					$u['kategori'],
					number_format( $u['fiyat'], 2, ',', '' ),
					number_format( $u['maliyet'], 2, ',', '' ),
					number_format( $u['cm'], 2, ',', '' ),
					$u['satis'],
					number_format( $u['ciro'], 2, ',', '' ),
					number_format( $u['katki'], 2, ',', '' ),
					$etiket[ $u['kutu'] ] ?? $u['kutu'],
					$u['aksiyon'],
				),
				';'
			);
		}

		fclose( $out );
		exit;
	}
	add_action( 'admin_post_qrms_mm_export_csv', 'qrms_mm_export_csv' );
}
