<?php
/**
 * Combo + Extra + Portion + Cart fiyat zinciri.
 *
 * Sepet formülü (sepet.js): taban (data-fiyat / kampanya) + porsiyon farkı
 * + katalog extra toplamı. Sunucu qmo_siparis_kalem_unit_price aynı
 * bileşenleri RMA_Kampanya + RMA_Porsiyon + RMA_Ekstra kataloğundan
 * yeniden hesaplar; istemci extra tutarına güvenilmez.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-porsiyon.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-ekstra.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-kampanya-db.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-kampanya.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/admin-kombin-meta.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/rest-order.php';

/**
 * Fiyat zinciri test ürünü eker.
 *
 * @param int         $id         Ürün ID.
 * @param string      $taban      rma_price.
 * @param array       $porsiyon   Porsiyon satırları.
 * @param array       $ekstra     Manuel extra satırları.
 * @param string|null $kombin     Kombin paket fiyatı veya null.
 * @return void
 */
function qrms_fiyat_zinciri_urun( $id, $taban, $porsiyon, $ekstra, $kombin = null ) {
	$meta = array(
		'rma_price'             => (string) $taban,
		RMA_Porsiyon::META      => $porsiyon,
		RMA_Ekstra::META_MANUEL => $ekstra,
	);

	if ( null !== $kombin ) {
		$meta['_qmo_is_kombin']    = '1';
		$meta['_qmo_kombin_fiyat'] = (string) $kombin;
	}

	$GLOBALS['qrms_test']['post_meta'][ $id ] = $meta;
}

/**
 * Kampanya belleğini test kaydıyla doldurur.
 *
 * @param array $kampanya Aktif kampanya kaydı.
 * @return void
 */
function qrms_fiyat_zinciri_kampanya( array $kampanya ) {
	$ref  = new ReflectionClass( 'RMA_Kampanya' );
	$prop = $ref->getProperty( 'memo' );
	$prop->setAccessible( true );
	$prop->setValue( null, array( 'ham' => $kampanya, 'aktif' => $kampanya ) );
}

echo "\nFiyat zinciri — extra katalog doğrulama\n";

qrms_test(
	'extra adları kataloğa göre doğrulanır, uydurma ad ve istemci fiyatı düşer',
	function () {
		qrms_fiyat_zinciri_urun(
			12100,
			'100',
			array(),
			array(
				array( 'ad' => 'Peynir', 'fiyat' => 15 ),
				array( 'ad' => 'Sos', 'fiyat' => 10 ),
			)
		);

		$secim = RMA_Ekstra::dogrula_secim(
			12100,
			array(
				array( 'ad' => 'Peynir', 'fiyat' => 0.01 ),
				array( 'ad' => 'Uydurma Extra', 'fiyat' => 1 ),
				'Sos',
				'Peynir',
			)
		);

		qrms_assert_same( 2, count( $secim ), 'yalnızca katalog adları' );
		qrms_assert_same( 'Peynir', $secim[0]['ad'], 'katalog yazımı' );
		qrms_assert_same( 15.0, $secim[0]['fiyat'], 'katalog fiyatı, istemci 0.01 değil' );
		qrms_assert_same( 10.0, $secim[1]['fiyat'], 'sos katalog' );
		qrms_assert_same( 25.0, RMA_Ekstra::secim_toplami( 12100, array( 'Peynir', 'Sos' ) ), 'pey nir+sos' );
	}
);

qrms_test(
	'not önekinden extra adları ayrıştırılır',
	function () {
		$adlar = RMA_Ekstra::nottan_adlar( 'Ekstra: Peynir, Sos — az pişmiş' );
		qrms_assert_same( array( 'Peynir', 'Sos' ), $adlar, 'ömür not + extra öneki' );

		$en = RMA_Ekstra::nottan_adlar( 'Extras: Peynir' );
		qrms_assert_same( array( 'Peynir' ), $en, 'çeviri etiketi iki nokta sonrası' );

		qrms_assert_same( array(), RMA_Ekstra::nottan_adlar( 'az pişmiş' ), 'ömür not extra değil' );
	}
);

echo "\nFiyat zinciri — sunucu birim fiyatı\n";

qrms_test(
	'temel 100, porsiyon, extra ve birleşimler katalogdan hesaplanır',
	function () {
		RMA_Kampanya::bellegi_bosalt();
		qrms_fiyat_zinciri_urun(
			12101,
			'100',
			array(
				array( 'ad' => 'Küçük', 'fark' => 0 ),
				array( 'ad' => 'Orta', 'fark' => 20 ),
				array( 'ad' => 'Büyük', 'fark' => 40 ),
			),
			array(
				array( 'ad' => 'Peynir', 'fiyat' => 15 ),
				array( 'ad' => 'Sos', 'fiyat' => 10 ),
			)
		);

		qrms_assert_same( 100.0, qmo_siparis_kalem_unit_price( 12101, 'Burger' ), 'temel' );
		qrms_assert_same( 100.0, qmo_siparis_kalem_unit_price( 12101, 'Burger (Küçük)' ), 'küçük +0' );
		qrms_assert_same( 120.0, qmo_siparis_kalem_unit_price( 12101, 'Burger (Orta)' ), 'orta +20' );
		qrms_assert_same( 140.0, qmo_siparis_kalem_unit_price( 12101, 'Burger (Büyük)' ), 'büyük +40' );

		qrms_assert_same( 115.0, qmo_siparis_kalem_unit_price( 12101, 'Burger', array( 'Peynir' ) ), 'pey nir' );
		qrms_assert_same( 110.0, qmo_siparis_kalem_unit_price( 12101, 'Burger', array( 'Sos' ) ), 'sos' );
		qrms_assert_same( 125.0, qmo_siparis_kalem_unit_price( 12101, 'Burger', array( 'Peynir', 'Sos' ) ), 'iki extra' );

		$birim = qmo_siparis_kalem_unit_price( 12101, 'Burger (Büyük)', array( 'Peynir', 'Sos' ) );
		qrms_assert_same( 165.0, $birim, '100+40+15+10' );
		qrms_assert_same( 330.0, $birim * 2, 'adet × 2' );
		qrms_assert_same( 495.0, $birim * 3, 'adet × 3' );
	}
);

qrms_test(
	'uydurma extra ve istemci extra tutarı sunucu fiyatını düşürmez',
	function () {
		qrms_fiyat_zinciri_urun(
			12102,
			'100',
			array( array( 'ad' => 'Büyük', 'fark' => 40 ) ),
			array( array( 'ad' => 'Peynir', 'fiyat' => 15 ) )
		);

		$ham = array(
			array( 'ad' => 'Peynir', 'fiyat' => 0.01 ),
			array( 'ad' => 'Hediye Extra', 'fiyat' => -50 ),
		);

		qrms_assert_same(
			155.0,
			qmo_siparis_kalem_unit_price( 12102, 'Burger (Büyük)', $ham ),
			'katalog 15, sahte extra yok'
		);
	}
);

qrms_test(
	'JSON extra adları ile not öneki birleşir, fiyat yine katalogdan gelir',
	function () {
		qrms_fiyat_zinciri_urun(
			12103,
			'100',
			array( array( 'ad' => 'Büyük', 'fark' => 40 ) ),
			array(
				array( 'ad' => 'Peynir', 'fiyat' => 15 ),
				array( 'ad' => 'Sos', 'fiyat' => 10 ),
			)
		);

		$kalem = array(
			'urunAdi'   => 'Burger (Büyük)',
			'not'       => 'Ekstra: Peynir — acısız',
			'ekstralar' => array( array( 'ad' => 'Sos', 'fiyat' => 999 ) ),
		);

		$adlar = qmo_siparis_ekstra_adlari( $kalem );
		qrms_assert_true( in_array( 'Peynir', $adlar, true ), 'nottan peynir' );
		qrms_assert_true( in_array( 'Sos', $adlar, true ), 'JSON sos' );
		qrms_assert_same( 165.0, qmo_siparis_kalem_unit_price( 12103, $kalem['urunAdi'], $adlar ), '100+40+15+10' );
	}
);

echo "\nFiyat zinciri — combo (sabit paket fiyatı)\n";

qrms_test(
	'kombin tabanı bileşen toplamı değil _qmo_kombin_fiyat paketidir',
	function () {
		RMA_Kampanya::bellegi_bosalt();
		qrms_fiyat_zinciri_urun(
			12104,
			'100',
			array(
				array( 'ad' => 'Orta', 'fark' => 20 ),
				array( 'ad' => 'Büyük', 'fark' => 40 ),
			),
			array(
				array( 'ad' => 'Peynir', 'fiyat' => 15 ),
				array( 'ad' => 'Sos', 'fiyat' => 10 ),
			),
			'180'
		);

		$taban = RMA_Kampanya::taban_fiyat( 12104 );
		qrms_assert_true( $taban['kombin'], 'kombin ürün' );
		qrms_assert_same( '180', $taban['ham'], 'paket fiyatı' );

		qrms_assert_same( 180.0, qmo_siparis_kalem_unit_price( 12104, 'Menü' ), 'yalnız paket' );
		qrms_assert_same( 220.0, qmo_siparis_kalem_unit_price( 12104, 'Menü (Büyük)' ), 'paket + büyük' );
		qrms_assert_same(
			245.0,
			qmo_siparis_kalem_unit_price( 12104, 'Menü (Büyük)', array( 'Peynir', 'Sos' ) ),
			'180+40+15+10'
		);
	}
);

echo "\nFiyat zinciri — kampanya tabanı extra/porsiyonu ezmez\n";

qrms_test(
	'yüzde indirim tabana uygulanır, porsiyon ve extra üzerine eklenir',
	function () {
		RMA_Kampanya::bellegi_bosalt();
		qrms_fiyat_zinciri_urun(
			12105,
			'100',
			array( array( 'ad' => 'Büyük', 'fark' => 40 ) ),
			array(
				array( 'ad' => 'Peynir', 'fiyat' => 15 ),
				array( 'ad' => 'Sos', 'fiyat' => 10 ),
			)
		);

		qrms_fiyat_zinciri_kampanya(
			array(
				'id'         => 9,
				'status'     => 'active',
				'scope_type' => 'all',
				'calc_type'  => 'percent',
				'direction'  => 'decrease',
				'amount'     => 15,
				'rounding'   => 'none',
			)
		);

		$bilgi = RMA_Kampanya::fiyat_bilgisi( 12105 );
		qrms_assert_true( $bilgi['aktif'], 'kampanya görünür' );
		qrms_assert_same( 85.0, $bilgi['yeni'], '%15 → 85' );

		qrms_assert_same( 85.0, qmo_siparis_kalem_unit_price( 12105, 'Burger' ), 'kampanyalı taban' );
		qrms_assert_same(
			150.0,
			qmo_siparis_kalem_unit_price( 12105, 'Burger (Büyük)', array( 'Peynir', 'Sos' ) ),
			'85+40+15+10, extra ezilmez'
		);

		RMA_Kampanya::bellegi_bosalt();
	}
);

echo "\nFiyat zinciri — sepet imzası ve istemci sözleşmesi\n";

qrms_test(
	'aynı ürün farklı porsiyon/extra imzaları ayrı satırdır',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' );

		qrms_assert_contains( "( pid || ad ) + '::' + ( porsiyon || '' ) + '::' + e", $js, 'imza pid::porsiyon::extra' );
		qrms_assert_contains( 'taban + porsiyon.fark + ekstraToplami( ekstralar )', $js, 'sepet formülü' );
		qrms_assert_contains( 't += x.adet * x.fiyat', $js, 'satır toplamı adet × birim' );
		qrms_assert_contains( 'ekstralar: extraAdlar', $js, 'sipariş extra ad listesi' );
		qrms_assert_false(
			(bool) preg_match( '/return \{ urunAdi: ad, adet: x\.adet, not: not\.slice\( 0, 200 \), itemId: x\.pid \|\| 0 \}/', $js ),
			'eski fiyatsız extra-ad-siz gövde yok'
		);
		qrms_assert_false( false !== strpos( $js, 'ekstralar: x.ekstralar' ), 'extra tutarı siparişe gitmez' );
	}
);

qrms_test(
	'sipariş ucu extra adlarını temiz kaleme yazar, fiyat alanını okumaz',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/rest-order.php' );

		qrms_assert_contains( "RMA_Ekstra::secim_toplami( \$item_id, \$ekstra_adlari )", $php, 'katalog extra toplamı' );
		qrms_assert_contains( 'qmo_siparis_ekstra_adlari', $php, 'JSON + not birleşimi' );
		qrms_assert_contains( "'ekstralar'  => \$ekstra_adlari", $php, 'temiz kalemde extra adları' );
		qrms_assert_false( false !== strpos( $php, '$it[\'fiyat\']' ), 'istemci fiyat alanı okunmaz' );
		qrms_assert_false( false !== strpos( $php, '$it["fiyat"]' ), 'istemci fiyat tırnaklı okunmaz' );
	}
);
