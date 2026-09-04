<?php
/**
 * Menü mühendisliği raporunun CSV çıktısı.
 *
 * Excel'in Türkçe yerelinde noktalı virgül ayraç bekler ve UTF-8'i BOM
 * olmadan tanımaz; ikisi de burada karşılanır, aksi hâlde dosya tek sütunda
 * ve bozuk karakterlerle açılır.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_qrms_mm_csv', 'qrms_mm_csv_indir' );

/**
 * CSV indirme adresi.
 *
 * @param array $args Rapor parametreleri.
 * @return string
 */
function qrms_mm_csv_url( array $args ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action'   => 'qrms_mm_csv',
				'gun'      => $args['gun'],
				'kategori' => $args['kategori'],
			),
			admin_url( 'admin-post.php' )
		),
		'qrms_mm_csv'
	);
}

/**
 * CSV'yi basar ve çıkar.
 *
 * @return void
 */
function qrms_mm_csv_indir() {
	if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'qrms' ) );
	}

	check_admin_referer( 'qrms_mm_csv' );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce yukarıda.
	$args  = QRMS_MM_Rapor::parametreler( wp_unslash( $_GET ) );
	$rapor = QRMS_MM_Rapor::rapor( $args );
	$adlar = QRMS_MM_Hesap::kutular();

	$dosya = sprintf( 'menu-muhendisligi-%dgun-%s.csv', $args['gun'], gmdate( 'Y-m-d' ) );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="' . $dosya . '"' );

	$cikti = fopen( 'php://output', 'w' );

	// UTF-8 BOM.
	fwrite( $cikti, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

	$basliklar = array(
		__( 'Ürün', 'qrms' ),
		__( 'Kategori', 'qrms' ),
		__( 'Fiyat', 'qrms' ),
		__( 'Maliyet', 'qrms' ),
		__( 'Katkı payı', 'qrms' ),
		__( 'Marj %', 'qrms' ),
		__( 'Adet', 'qrms' ),
		__( 'Menü payı %', 'qrms' ),
		__( 'Ciro', 'qrms' ),
		__( 'Toplam katkı', 'qrms' ),
		__( 'Kutu', 'qrms' ),
		__( 'Aksiyon', 'qrms' ),
	);

	fputcsv( $cikti, $basliklar, ';' );

	foreach ( $rapor['urunler'] as $urun ) {
		fputcsv(
			$cikti,
			array(
				$urun['item_name'],
				$urun['category_name'],
				qrms_mm_csv_sayi( $urun['fiyat'] ),
				qrms_mm_csv_sayi( $urun['maliyet'] ),
				qrms_mm_csv_sayi( $urun['katki'] ),
				qrms_mm_csv_sayi( $urun['marj'] ),
				$urun['adet'],
				qrms_mm_csv_sayi( $urun['menu_payi'] ),
				qrms_mm_csv_sayi( $urun['ciro'] ),
				qrms_mm_csv_sayi( $urun['toplam_katki'] ),
				$adlar[ $urun['kutu'] ],
				$urun['aksiyon'],
			),
			';'
		);
	}

	// Rapora giremeyenler de dosyaya girer: eksiği olan ürünü CSV'de
	// görmemek "bu ürün yok" izlenimi verirdi.
	foreach ( $rapor['eksik'] as $urun ) {
		fputcsv(
			$cikti,
			array(
				$urun['item_name'],
				$urun['category_name'],
				'', '', '', '', '', '', '', '',
				__( 'Hesaplanamadı', 'qrms' ),
				$urun['sebep'],
			),
			';'
		);
	}

	fclose( $cikti ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	exit;
}

/**
 * Sayıyı Excel'in Türkçe yerelinin beklediği biçime çevirir.
 *
 * @param float $deger Değer.
 * @return string
 */
function qrms_mm_csv_sayi( $deger ) {
	return str_replace( '.', ',', (string) round( (float) $deger, 2 ) );
}
