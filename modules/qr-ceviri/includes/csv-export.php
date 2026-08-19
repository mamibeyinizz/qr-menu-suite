<?php
/**
 * Çeviri CSV'sini dışa aktarma.
 *
 * Sütunlar:
 *   item_id | item_type | field | original_text | original_hash | en | de | …
 *
 * Mevcut çeviriler ilgili dil sütununa dolu gelir; böylece kısmi güncelleme
 * yapılabilir, her seferinde sıfırdan çeviri gerekmez.
 *
 * admin-post.php + nonce + current_user_can deseni QR Menu Official'daki
 * menu-data-upload.php ile aynı.
 *
 * @package QRMenu_Ceviri
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_post_rma_ceviri_export', 'rma_ceviri_csv_disa_aktar' );

/**
 * CSV'yi üret ve indirt.
 */
if ( ! function_exists( 'rma_ceviri_csv_disa_aktar' ) ) {
	function rma_ceviri_csv_disa_aktar() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Yetkiniz yok.' );
		}
		check_admin_referer( 'rma_ceviri_export_action', 'rma_ceviri_export_nonce' );

		$ayrac = ( isset( $_POST['ayrac'] ) && ',' === $_POST['ayrac'] ) ? ',' : ';';

		// Elementor sayfa seçimi bu formdan geliyor; bir sonraki dışa aktarımda
		// hatırlansın diye seçeneğe de yazılıyor.
		$secili = isset( $_POST['elementor_sayfalar'] )
			? array_map( 'intval', (array) $_POST['elementor_sayfalar'] )
			: array();
		update_option( 'rma_ceviri_elementor_sayfalar', $secili, false );

		$diller = rma_ceviri_hedef_diller();
		$mevcut = RMA_Ceviri_Tablo::export_haritasi();

		// Uzun menülerde tarama zaman alabilir.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header(
			'Content-Disposition: attachment; filename=qr-ceviri-' . gmdate( 'Y-m-d-Hi' ) . '.csv'
		);

		// Excel'in UTF-8'i doğru açması için BOM.
		echo "\xEF\xBB\xBF";

		$cikti = fopen( 'php://output', 'w' );

		fputcsv(
			$cikti,
			array_merge(
				array( 'item_id', 'item_type', 'field', 'original_text', 'original_hash' ),
				$diller
			),
			$ayrac,
			'"',
			'\\'
		);

		foreach ( rma_ceviri_kaynak_satirlari() as $satir ) {
			$anahtar = rma_ceviri_anahtar( $satir['item_type'], $satir['item_id'], $satir['field'] );

			$hucreler = array(
				$satir['item_id'],
				$satir['item_type'],
				$satir['field'],
				$satir['original'],
				md5( $satir['original'] ),
			);

			foreach ( $diller as $dil ) {
				$hucreler[] = isset( $mevcut[ $anahtar ][ $dil ] ) ? $mevcut[ $anahtar ][ $dil ] : '';
			}

			fputcsv( $cikti, $hucreler, $ayrac, '"', '\\' );
		}

		fclose( $cikti );
		exit;
	}
}
