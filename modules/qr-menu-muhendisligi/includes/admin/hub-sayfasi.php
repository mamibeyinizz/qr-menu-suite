<?php
/**
 * Menü Mühendisliği hub ekranı.
 *
 * Dört kart + üç özet kutusu. Özet kutuları raporun kendisini açmadan
 * "işler yolunda mı" sorusunu cevaplar; en önemlisi maliyet kapsamıdır —
 * maliyeti girilmemiş ürün matrise hiç giremez.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hub'ı basar.
 *
 * @return void
 */
function qrms_mm_hub() {
	$sayfalar = qrms_mm_sayfalar();
	$kartlar  = array();

	foreach ( $sayfalar as $slug => $sayfa ) {
		$kartlar[] = array(
			'url'   => add_query_arg( 'page', $slug, admin_url( 'admin.php' ) ),
			'title' => $sayfa['title'],
			'desc'  => $sayfa['desc'],
			'icon'  => $sayfa['icon'],
		);
	}

	QRMS_Admin::render_hub(
		array(
			'title'  => __( 'Menü Mühendisliği', 'qrms' ),
			'intro'  => __( 'Menünüzün hangi ürünü para kazandırıyor, hangisi kaybettiriyor? Önce maliyetleri girin, sonra raporu açın.', 'qrms' ),
			'accent' => '#7c5cff',
			'stats'  => qrms_mm_hub_ozet(),
			'cards'  => $kartlar,
		)
	);
}

/**
 * Hub özet kutuları.
 *
 * @return array
 */
function qrms_mm_hub_ozet() {
	$urunler = QRMS_MM_Rapor::urunler();
	$toplam  = count( $urunler );
	$dolu    = 0;

	foreach ( $urunler as $urun ) {
		if ( null !== $urun['maliyet'] && null !== $urun['fiyat'] ) {
			++$dolu;
		}
	}

	$maliyet_url = add_query_arg(
		array(
			'page'  => QRMS_MM_MALIYET_SAYFA,
			'eksik' => 1,
		),
		admin_url( 'admin.php' )
	);

	$ozet = array(
		array(
			'label'  => __( 'Maliyeti girilmiş ürün', 'qrms' ),
			'value'  => sprintf( '%d / %d', $dolu, $toplam ),
			'url'    => $maliyet_url,
			'accent' => $dolu === $toplam && $toplam > 0 ? '#1f9d55' : '#e08a1e',
			'class'  => $dolu < $toplam ? 'qrms-hub-stat-alert' : '',
		),
	);

	// Rapor yalnızca maliyet girilmişse anlamlı; hiç yoksa boşuna sorgu açma.
	if ( 0 === $dolu ) {
		return $ozet;
	}

	$rapor = QRMS_MM_Rapor::rapor( QRMS_MM_Rapor::parametreler( array() ) );

	$ozet[] = array(
		'label'  => __( 'Dönem katkı payı', 'qrms' ),
		'value'  => qrms_mm_para( $rapor['ozet']['toplam_katki'] ),
		'url'    => add_query_arg( 'page', QRMS_MM_RAPOR_SAYFA, admin_url( 'admin.php' ) ),
		'accent' => '#1f9d55',
	);

	$ozet[] = array(
		'label'  => __( 'Kayıp fırsat', 'qrms' ),
		'value'  => qrms_mm_para( $rapor['ozet']['kayip_firsat'] ),
		'url'    => add_query_arg( 'page', QRMS_MM_RAPOR_SAYFA, admin_url( 'admin.php' ) ),
		'accent' => '#c0392b',
		'class'  => $rapor['ozet']['kayip_firsat'] > 0 ? 'qrms-hub-stat-alert' : '',
	);

	return $ozet;
}
