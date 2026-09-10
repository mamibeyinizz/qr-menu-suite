<?php
/**
 * rma_translations tablosunda aynı (item_id, item_type, field) için
 * iki farklı dilin çevirisi birebir aynı olan satırları listeler.
 *
 * Kullanım: wp eval-file scripts/qr-ceviri-bakim/03-tara-sirali-kopya.php
 *
 * @package QRMenu_Ceviri_Bakim
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "WordPress yüklü değil.\n" );
}

require __DIR__ . '/_ortak.php';

if ( ! class_exists( 'RMA_Ceviri_Tablo' ) || ! RMA_Ceviri_Tablo::tablo_var_mi() ) {
	exit( "rma_translations tablosu bulunamadı.\n" );
}

global $wpdb;
$tablo = RMA_Ceviri_Tablo::tablo();

$satirlar = $wpdb->get_results(
	"SELECT
		t1.item_id,
		t1.item_type,
		t1.field,
		t1.lang_code AS dil1,
		t2.lang_code AS dil2,
		t1.original_text,
		t1.translated_text
	FROM {$tablo} t1
	INNER JOIN {$tablo} t2
		ON t1.item_id = t2.item_id
		AND t1.item_type = t2.item_type
		AND t1.field = t2.field
		AND t1.lang_code < t2.lang_code
		AND t1.translated_text = t2.translated_text
		AND t1.translated_text <> ''
	ORDER BY t1.item_type, t1.item_id, t1.field, t1.lang_code, t2.lang_code",
	ARRAY_A
);

rma_bakim_log( '=== Sıralı kopya taraması (iki dil, birebir aynı çeviri) ===' );
rma_bakim_log( 'Toplam çift: ' . count( $satirlar ) );
rma_bakim_log( '' );

$en_it = 0;
$ar_ru = 0;

foreach ( $satirlar as $s ) {
	$dil_cift = $s['dil1'] . '/' . $s['dil2'];
	if ( ( 'en' === $s['dil1'] && 'it' === $s['dil2'] ) || ( 'en' === $s['dil2'] && 'it' === $s['dil1'] ) ) {
		++$en_it;
	}
	if ( ( 'ar' === $s['dil1'] && 'ru' === $s['dil2'] ) || ( 'ar' === $s['dil2'] && 'ru' === $s['dil1'] ) ) {
		++$ar_ru;
	}

	$orijinal_kisa = mb_substr( $s['original_text'], 0, 60 );
	$ceviri_kisa   = mb_substr( $s['translated_text'], 0, 60 );

	rma_bakim_log(
		sprintf(
			'%s item_id=%s field=%s dil=%s orijinal="%s" ceviri="%s"',
			$s['item_type'],
			$s['item_id'],
			$s['field'],
			$dil_cift,
			$orijinal_kisa,
			$ceviri_kisa
		)
	);
}

rma_bakim_log( '' );
rma_bakim_log( "en/it eşleşme sayısı: {$en_it}" );
rma_bakim_log( "ar/ru eşleşme sayısı: {$ar_ru}" );

rma_bakim_basarili( 'Rapor tamamlandı.' );
