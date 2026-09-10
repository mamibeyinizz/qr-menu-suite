<?php
/**
 * Ürün post_content / post_excerpt alanlarında başlık veya öbek tekrarı taraması.
 *
 * Kullanım: wp eval-file scripts/qr-ceviri-bakim/01-tara-baslik-tekrari.php
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

$bulunan = array();
$tipler  = rma_ceviri_urun_tipleri();

if ( empty( $tipler ) ) {
	rma_bakim_log( 'Ürün post type tanımlı değil.' );
	return;
}

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
		$baslik = (string) $post->post_title;
		$alanlar = array(
			'content' => (string) $post->post_content,
			'excerpt' => (string) $post->post_excerpt,
		);

		foreach ( $alanlar as $alan => $metin ) {
			if ( '' === trim( $metin ) ) {
				continue;
			}

			$tekrar_baslik = rma_bakim_baslik_tekrar_sayisi( $metin, $baslik );
			if ( $tekrar_baslik >= 2 ) {
				$bulunan[] = array(
					'item_id'   => (int) $post->ID,
					'field'     => $alan,
					'tip'       => 'baslik',
					'tekrar'    => $tekrar_baslik,
					'parca'     => $baslik,
					'ornek'     => mb_substr( $metin, 0, 120 ) . '…',
				);
				continue;
			}

			$obek = rma_bakim_obek_tekrari_tespit( $metin );
			if ( null !== $obek && $obek['tekrar'] >= 2 ) {
				$bulunan[] = array(
					'item_id'   => (int) $post->ID,
					'field'     => $alan,
					'tip'       => 'obek',
					'tekrar'    => $obek['tekrar'],
					'parca'     => $obek['parca'],
					'ornek'     => mb_substr( $metin, 0, 120 ) . '…',
				);
			}
		}
	}

	$adet = count( $sorgu->posts );
	++$sayfa;
} while ( $adet === $boyut );

rma_bakim_log( '=== Başlık / öbek tekrarı taraması ===' );
rma_bakim_log( 'Toplam etkilenen kayıt: ' . count( $bulunan ) );
rma_bakim_log( '' );

foreach ( $bulunan as $k ) {
	rma_bakim_log(
		sprintf(
			'item_id=%d field=%s tip=%s tekrar=%d parca="%s" örnek="%s"',
			$k['item_id'],
			$k['field'],
			$k['tip'],
			$k['tekrar'],
			mb_substr( $k['parca'], 0, 60 ),
			$k['ornek']
		)
	);
}

$bilinen = array( 62, 75, 147 );
$bulunan_idler = array_unique( array_column( $bulunan, 'item_id' ) );
$eksik = array_diff( $bilinen, $bulunan_idler );
$fazla = array_diff( $bulunan_idler, $bilinen );

rma_bakim_log( '' );
rma_bakim_log( 'Bilinen ID\'ler (62, 75, 147): ' . ( empty( $eksik ) ? 'hepsi tespit edildi' : 'eksik: ' . implode( ', ', $eksik ) ) );
if ( ! empty( $fazla ) ) {
	rma_bakim_log( 'Ek tespit edilen ID\'ler: ' . implode( ', ', $fazla ) );
}

rma_bakim_basarili( 'Tarama tamamlandı.' );
