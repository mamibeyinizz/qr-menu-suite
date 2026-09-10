<?php
/**
 * Ürün content/excerpt alanlarındaki başlık tekrarını temizler.
 *
 * Kullanım:
 *   wp eval-file scripts/qr-ceviri-bakim/02-temizle-baslik-tekrari.php          (dry-run)
 *   wp eval-file scripts/qr-ceviri-bakim/02-temizle-baslik-tekrari.php -- --apply
 *
 * @package QRMenu_Ceviri_Bakim
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "WordPress yüklü değil.\n" );
}

require __DIR__ . '/_ortak.php';

if ( ! function_exists( 'rma_ceviri_urun_tipleri' ) ) {
	exit( "qr-menu-suite / qr-ceviri modülü etkin değil.\n" );
}

$uygula = rma_bakim_uygula_mi();
$duzeltilen = 0;
$tipler     = rma_ceviri_urun_tipleri();

rma_bakim_log( $uygula ? '=== UYGULA modu ===' : '=== DRY-RUN modu (--apply ile yazılır) ===' );

$sayfa = 1;
$boyut = 200;

do {
	$sorgu = new WP_Query(
		array(
			'post_type'           => $tipler,
			'post_status'         => array( 'publish', 'draft', 'private' ),
			'posts_per_page'      => $boyut,
			'paged'               => $sayfa,
			'orderby'             => 'ID',
			'order'               => 'ASC',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		)
	);

	if ( empty( $sorgu->posts ) ) {
		break;
	}

	foreach ( $sorgu->posts as $post ) {
		$baslik  = (string) $post->post_title;
		$guncelle = array();

		foreach ( array( 'post_content', 'post_excerpt' ) as $kolon ) {
			$eski = (string) $post->$kolon;
			if ( '' === trim( $eski ) ) {
				continue;
			}

			$tekrar = rma_bakim_baslik_tekrar_sayisi( $eski, $baslik );
			if ( $tekrar < 2 ) {
				$obek = rma_bakim_obek_tekrari_tespit( $eski );
				if ( null === $obek || $obek['tekrar'] < 2 ) {
					continue;
				}
				// Öbek tekrarı: baştaki tüm tekrarları kaldır.
				$yeni = $eski;
				$parca = $obek['parca'];
				$uzun  = strlen( $parca );
				while ( strlen( $yeni ) >= $uzun && substr( $yeni, 0, $uzun ) === $parca ) {
					$yeni = substr( $yeni, $uzun );
				}
			} else {
				$yeni = rma_bakim_baslik_tekrari_temizle( $eski, $baslik );
			}

			if ( $yeni === $eski ) {
				continue;
			}

			$alan = ( 'post_content' === $kolon ) ? 'content' : 'excerpt';
			rma_bakim_log(
				sprintf(
					'item_id=%d field=%s: "%s…" → "%s…"',
					$post->ID,
					$alan,
					mb_substr( $eski, 0, 50 ),
					mb_substr( $yeni, 0, 50 )
				)
			);

			$guncelle[ $kolon ] = $yeni;
		}

		if ( empty( $guncelle ) ) {
			continue;
		}

		if ( $uygula ) {
			wp_update_post(
				array_merge(
					array( 'ID' => $post->ID ),
					$guncelle
				)
			);
		}

		++$duzeltilen;
	}

	$adet = count( $sorgu->posts );
	++$sayfa;
} while ( $adet === $boyut );

if ( $uygula && $duzeltilen > 0 && function_exists( 'rma_ceviri_onbellek_temizle' ) ) {
	rma_ceviri_onbellek_temizle();
}

rma_bakim_basarili(
	sprintf(
		'%d ürün %s.',
		$duzeltilen,
		$uygula ? 'güncellendi' : 'dry-run ile listelendi'
	)
);
