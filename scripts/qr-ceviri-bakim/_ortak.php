<?php
/**
 * QR Çeviri bakım scriptleri — ortak yardımcılar.
 *
 * wp eval-file ile çalıştırılan scriptler tarafından require edilir.
 * Eklentiye gömülmez.
 *
 * @package QRMenu_Ceviri_Bakim
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "Bu dosya doğrudan çalıştırılamaz. wp eval-file kullanın.\n" );
}

/**
 * Metnin başında bir öbekin ardışık tekrar sayısını döndürür.
 *
 * @param string $metin   İncelenecek metin.
 * @param string $parca   Tekrarlanan öbek (ör. post_title).
 * @return int Tekrar sayısı; 0 = başta yok.
 */
function rma_bakim_baslik_tekrar_sayisi( $metin, $parca ) {
	$parca = (string) $parca;
	if ( '' === trim( $parca ) ) {
		return 0;
	}

	$kalan  = (string) $metin;
	$tekrar = 0;
	$uzun   = strlen( $parca );

	while ( strlen( $kalan ) >= $uzun && substr( $kalan, 0, $uzun ) === $parca ) {
		$kalan = substr( $kalan, $uzun );
		++$tekrar;
	}

	return $tekrar;
}

/**
 * Metnin başındaki ardışık öbek tekrarını tespit eder (genel regex).
 *
 * En az 3 karakterlik bir öbek, başta ayrılmadan ≥2 kez tekrarlanıyorsa yakalar.
 *
 * @param string $metin İncelenecek metin.
 * @return array{parca:string,tekrar:int}|null
 */
function rma_bakim_obek_tekrari_tespit( $metin ) {
	$metin = (string) $metin;
	if ( strlen( $metin ) < 6 ) {
		return null;
	}

	if ( ! preg_match( '/^(.{3,}?)\1{1,}/us', $metin, $m ) ) {
		return null;
	}

	$parca  = $m[1];
	$tekrar = (int) floor( strlen( $m[0] ) / strlen( $parca ) );

	if ( $tekrar < 2 ) {
		return null;
	}

	return array(
		'parca'  => $parca,
		'tekrar' => $tekrar,
	);
}

/**
 * Metnin başındaki ardışık başlık tekrarlarını temizler.
 *
 * @param string $metin  Kaynak metin.
 * @param string $baslik Ürün başlığı.
 * @return string
 */
function rma_bakim_baslik_tekrari_temizle( $metin, $baslik ) {
	$baslik = (string) $baslik;
	if ( '' === trim( $baslik ) ) {
		return (string) $metin;
	}

	$temiz = (string) $metin;
	$uzun  = strlen( $baslik );

	while ( strlen( $temiz ) >= $uzun && substr( $temiz, 0, $uzun ) === $baslik ) {
		$temiz = substr( $temiz, $uzun );
	}

	return $temiz;
}

/**
 * WP-CLI veya echo ile satır yaz.
 *
 * @param string $satir Mesaj.
 */
function rma_bakim_log( $satir ) {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::log( $satir );
	} else {
		echo $satir . "\n";
	}
}

/**
 * WP-CLI success veya echo.
 *
 * @param string $mesaj Mesaj.
 */
function rma_bakim_basarili( $mesaj ) {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::success( $mesaj );
	} else {
		echo "OK: {$mesaj}\n";
	}
}

/**
 * --apply argümanı var mı?
 *
 * @return bool
 */
function rma_bakim_uygula_mi() {
	global $args;
	return is_array( $args ) && in_array( '--apply', $args, true );
}
