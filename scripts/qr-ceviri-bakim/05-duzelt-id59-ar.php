<?php
/**
 * item_id 59 product title ar düzeltmesi: فسق → فستق
 *
 * Kullanım:
 *   wp eval-file scripts/qr-ceviri-bakim/05-duzelt-id59-ar.php          (dry-run)
 *   wp eval-file scripts/qr-ceviri-bakim/05-duzelt-id59-ar.php -- --apply
 *
 * @package QRMenu_Ceviri_Bakim
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "WordPress yüklü değil.\n" );
}

require __DIR__ . '/_ortak.php';

if ( ! class_exists( 'RMA_Ceviri_Tablo' ) ) {
	exit( "qr-ceviri modülü etkin değil.\n" );
}

$uygula  = rma_bakim_uygula_mi();
$item_id = 59;
$tip     = 'product';
$field   = 'title';
$dil     = 'ar';
$yeni    = 'كنافة بالفستق';

$orijinal = rma_ceviri_guncel_orijinal( $item_id, $tip, $field );
if ( null === $orijinal ) {
	exit( "item_id {$item_id} bulunamadı.\n" );
}

global $wpdb;
$tablo  = RMA_Ceviri_Tablo::tablo();
$mevcut = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT translated_text FROM {$tablo}
		 WHERE item_id = %d AND item_type = %s AND field = %s AND lang_code = %s",
		$item_id,
		$tip,
		$field,
		$dil
	)
);

rma_bakim_log( '=== item_id 59 title ar düzeltmesi ===' );
rma_bakim_log( 'Orijinal TR: ' . $orijinal );
rma_bakim_log( 'Mevcut ar:   ' . ( $mevcut ? $mevcut : '(kayıt yok)' ) );
rma_bakim_log( 'Yeni ar:     ' . $yeni );

if ( $mevcut === $yeni ) {
	rma_bakim_basarili( 'Zaten doğru değerde, değişiklik gerekmedi.' );
	return;
}

if ( $uygula ) {
	RMA_Ceviri_Tablo::upsert( $item_id, $tip, $field, $dil, $orijinal, $yeni );
	if ( function_exists( 'rma_ceviri_onbellek_temizle' ) ) {
		rma_ceviri_onbellek_temizle();
	}
	rma_bakim_basarili( 'Güncellendi.' );
} else {
	rma_bakim_basarili( 'Dry-run tamamlandı. Uygulamak için --apply ekleyin.' );
}
