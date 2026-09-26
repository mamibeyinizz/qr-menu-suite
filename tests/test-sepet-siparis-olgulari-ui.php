<?php
/**
 * Phase 5.1 — Sepet ekranına QRMS_Siparis_Olgulari entegrasyonu.
 *
 * @package QR_Menu_Suite
 */

/**
 * Sepet verisi için standart aralık.
 *
 * @return array{bas:string,bit:string,gun:int}
 */
function qrms_sepet_ui_aralik() {
	return array(
		'bas' => '2026-03-10 00:00:00',
		'bit' => '2026-03-10 23:59:59',
		'gun' => 1,
	);
}

/**
 * qrms_analitik_sepet_verisi için üç sıralı DB yanıtı hazırlar.
 *
 * @param array<int,array<string,mixed>> $gruplar sepet_olay_gruplari satırları.
 * @param array<string,mixed>            $huni    huni_ozeti satırı.
 * @param array<int,array<string,mixed>> $kesin   ozet() sent satırları.
 * @return QRMS_Sayan_Wpdb
 */
function qrms_sepet_ui_wpdb( array $gruplar, array $huni, array $kesin ) {
	$wpdb = qrms_sayan_wpdb();

	QRMS_Analitik::sepet_onbellegini_temizle();
	qrms_analitik_onbellek_sifirla();

	$wpdb->results[] = $gruplar;
	$wpdb->rows[]    = $huni;
	$wpdb->results[] = $kesin;

	return $wpdb;
}

qrms_test(
	'Phase 5.1-A: tek order_sent satırı → siparis_tutari 200',
	function () {
		$oid = 'ord-ui-a-1111-2222-3333-444444444444';
		qrms_sepet_ui_wpdb(
			array(),
			array(
				'view'   => 0,
				'click'  => 0,
				'cart'   => 0,
				'orders' => 1,
			),
			array(
				array(
					'order_id'       => $oid,
					'event_type'     => 'order_sent',
					'qty'            => 2,
					'unit_price'     => 100,
					'iptal_order_id' => null,
				),
			)
		);

		$veri = qrms_analitik_sepet_verisi( qrms_sepet_ui_aralik(), '' );
		$olgu = $veri['siparis_olgulari'];

		qrms_assert_same( 1, $olgu['siparis_sayisi'], 'siparis_sayisi' );
		qrms_assert_same( 200.0, $olgu['siparis_tutari'], 'siparis_tutari' );
		qrms_assert_same( 'kesin', $olgu['kaynak'], 'kaynak' );
		qrms_assert_true( $olgu['legacy_haric'], 'legacy_haric' );
	}
);

qrms_test(
	'Phase 5.1-B: aynı order_id iki satır → sayı 1, tutar 250',
	function () {
		$oid = 'ord-ui-b-1111-2222-3333-444444444444';
		qrms_sepet_ui_wpdb(
			array(),
			array(
				'view'   => 0,
				'click'  => 0,
				'cart'   => 0,
				'orders' => 1,
			),
			array(
				array(
					'order_id'       => $oid,
					'event_type'     => 'order_sent',
					'qty'            => 2,
					'unit_price'     => 100,
					'iptal_order_id' => null,
				),
				array(
					'order_id'       => $oid,
					'event_type'     => 'order_sent',
					'qty'            => 1,
					'unit_price'     => 50,
					'iptal_order_id' => null,
				),
			)
		);

		$veri = qrms_analitik_sepet_verisi( qrms_sepet_ui_aralik(), '' );
		$olgu = $veri['siparis_olgulari'];

		qrms_assert_same( 1, $olgu['siparis_sayisi'], 'tek sipariş' );
		qrms_assert_same( 250.0, $olgu['siparis_tutari'], '2*100 + 50' );
	}
);

qrms_test(
	'Phase 5.1-C/I: legacy ciro korunur, kesin boş kalır',
	function () {
		qrms_sepet_ui_wpdb(
			array(
				array(
					'ip_hash'       => 'ip1',
					'masa_no'       => 'masa-9',
					'pencere'       => '2026-03-10 12',
					'event_type'    => 'order_sent',
					'item_id'       => 5,
					'item_name'     => 'Legacy',
					'category_name' => 'Ana',
					'adet'          => 1,
					'gercek_adet'   => 1,
					'ciro'          => 88.0,
					'ilk'           => '2026-03-10 12:00:00',
					'son'           => '2026-03-10 12:00:00',
				),
			),
			array(
				'view'   => 0,
				'click'  => 0,
				'cart'   => 0,
				'orders' => 1,
			),
			array()
		);

		$veri = qrms_analitik_sepet_verisi( qrms_sepet_ui_aralik(), '' );

		qrms_assert_same( 88.0, $veri['ozet']['ciro'], 'legacy ciro değişmez' );
		qrms_assert_same( 0, $veri['siparis_olgulari']['siparis_sayisi'], 'kesin sayı' );
		qrms_assert_same( 0.0, $veri['siparis_olgulari']['siparis_tutari'], 'kesin tutar' );
	}
);

qrms_test(
	'Phase 5.1-D: unit_price eksik → Siparis_Olgulari davranışı',
	function () {
		$ozet = QRMS_Siparis_Olgulari::hesapla(
			array(
				array(
					'event_type' => 'order_sent',
					'order_id'   => 'o-ui-d',
					'qty'        => 2,
					'price'      => 99,
				),
			)
		);

		qrms_assert_same( 1, $ozet['siparis_sayisi'], 'order_id sayılır' );
		qrms_assert_same( 0.0, $ozet['siparis_tutari'], 'tutar 0' );
	}
);

qrms_test(
	'Phase 5.1-E: iptal → siparis_tutari düşer, iptal metrikleri dolu',
	function () {
		$oid = 'ord-ui-e-1111-2222-3333-444444444444';
		qrms_sepet_ui_wpdb(
			array(),
			array(
				'view'   => 0,
				'click'  => 0,
				'cart'   => 0,
				'orders' => 0,
			),
			array(
				array(
					'order_id'       => $oid,
					'event_type'     => 'order_sent',
					'qty'            => 1,
					'unit_price'     => 100,
					'iptal_order_id' => $oid,
				),
			)
		);

		$veri = qrms_analitik_sepet_verisi( qrms_sepet_ui_aralik(), '' );
		$olgu = $veri['siparis_olgulari'];

		qrms_assert_same( 0, $olgu['siparis_sayisi'], 'iptal düşüldü' );
		qrms_assert_same( 0.0, $olgu['siparis_tutari'], 'aktif tutar 0' );
		qrms_assert_same( 1, $olgu['iptal_sayisi'], 'iptal sayısı' );
		qrms_assert_same( 100.0, $olgu['iptal_tutari'], 'iptal tutarı' );
	}
);

qrms_test(
	'Phase 5.1-F/G: masa ve tarih ozet sorgusuna taşınır',
	function () {
		$wpdb = qrms_sepet_ui_wpdb(
			array(),
			array(
				'view'   => 0,
				'click'  => 0,
				'cart'   => 0,
				'orders' => 0,
			),
			array()
		);

		qrms_analitik_sepet_verisi( qrms_sepet_ui_aralik(), 'masa-z' );

		qrms_assert_contains( "s.created_at BETWEEN '2026-03-10 00:00:00' AND '2026-03-10 23:59:59'", $wpdb->queries[2], 'tarih aralığı' );
		qrms_assert_contains( "s.masa_no = 'masa-z'", $wpdb->queries[2], 'masa filtresi' );
	}
);

qrms_test(
	'Phase 5.1-H: boş veri → bos_ozet benzeri',
	function () {
		qrms_sepet_ui_wpdb(
			array(),
			array(
				'view'   => 0,
				'click'  => 0,
				'cart'   => 0,
				'orders' => 0,
			),
			array()
		);

		$veri = qrms_analitik_sepet_verisi( qrms_sepet_ui_aralik(), '' );
		$bek  = QRMS_Siparis_Olgulari::bos_ozet();

		qrms_assert_same( $bek['siparis_sayisi'], $veri['siparis_olgulari']['siparis_sayisi'], 'sayı' );
		qrms_assert_same( $bek['siparis_tutari'], $veri['siparis_olgulari']['siparis_tutari'], 'tutar' );
		qrms_assert_same( $bek['kaynak'], $veri['siparis_olgulari']['kaynak'], 'kaynak' );
	}
);

qrms_test(
	'Phase 5.1-J: ort_sepet_tutari legacy hesapla yolunda değişmez',
	function () {
		$gruplar = array(
			array(
				'ip_hash'       => 'ip2',
				'masa_no'       => '',
				'pencere'       => '2026-03-10 14',
				'event_type'    => 'order_sent',
				'item_id'       => 1,
				'item_name'     => 'A',
				'category_name' => 'B',
				'adet'          => 2,
				'gercek_adet'   => 2,
				'ciro'          => 60.0,
			),
		);

		$once = qrms_analitik_sepet_hesapla( $gruplar, 1, 0 );

		qrms_sepet_ui_wpdb(
			$gruplar,
			array(
				'view'   => 0,
				'click'  => 0,
				'cart'   => 0,
				'orders' => 1,
			),
			array(
				array(
					'order_id'       => 'ord-ui-j',
					'event_type'     => 'order_sent',
					'qty'            => 1,
					'unit_price'     => 999,
					'iptal_order_id' => null,
				),
			)
		);

		$veri = qrms_analitik_sepet_verisi( qrms_sepet_ui_aralik(), '' );

		qrms_assert_same( $once['ozet']['ciro'], $veri['ozet']['ciro'], 'ciro' );
		qrms_assert_same( $once['ozet']['ort_sepet_tutari'], $veri['ozet']['ort_sepet_tutari'], 'aov' );
		qrms_assert_same( 999.0, $veri['siparis_olgulari']['siparis_tutari'], 'kesin ayrı' );
	}
);

qrms_test(
	'Phase 5.1-K: istek içi önbellek ozet sorgusunu tekrarlamaz',
	function () {
		$wpdb = qrms_sepet_ui_wpdb(
			array(),
			array(
				'view'   => 0,
				'click'  => 0,
				'cart'   => 0,
				'orders' => 0,
			),
			array()
		);

		$aralik = qrms_sepet_ui_aralik();
		qrms_analitik_sepet_verisi( $aralik, '' );
		$sorgu_sayisi = count( $wpdb->queries );
		qrms_analitik_sepet_verisi( $aralik, '' );

		qrms_assert_same( $sorgu_sayisi, count( $wpdb->queries ), 'ikinci çağrı ek sorgu açmaz' );
		qrms_assert_same( 1, $wpdb->kac_kez( 'order_cancelled' ), 'tek kesin ozet sorgusu' );
	}
);

qrms_test(
	'Phase 5.1-L: duplicate order SQL yok — yalnızca mevcut ozet()',
	function () {
		$sepet = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/sepet-sayfasi.php' );
		qrms_assert_same( 1, substr_count( $sepet, 'QRMS_Siparis_Olgulari::ozet(' ), 'tek ozet çağrısı' );
		qrms_assert_false( false !== strpos( $sepet, 'COUNT(DISTINCT order_id' ), 'sepet-sayfasi içinde ek aggregate yok' );
	}
);
