<?php
/**
 * Phase 6.2 — gerçek MariaDB üzerinde production attribution SQL davranışı.
 *
 * Çalıştırma: tests/mariadb/run-attribution-tests.sh
 * veya MariaDB ayakta + QRMS_MARIADB_* ile: php tests/test-attribution-mariadb.php
 *
 * @package QR_Menu_Suite
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mariadb/harness.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/class-db.php';

echo "\nPhase 6.2 — MariaDB attribution SQL (gerçek DB)\n";

if ( ! qrms_mariadb_available() ) {
	echo "\033[31mBLOCKED\033[0m: MariaDB erişilemiyor (QRMS_MARIADB_HOST/PORT veya tests/docker db servisi).\n";
	exit( 2 );
}

$mysqli = qrms_mariadb_connect();
if ( ! $mysqli ) {
	echo "\033[31mBLOCKED\033[0m: mysqli bağlantısı kurulamadı.\n";
	exit( 2 );
}

qrms_mariadb_apply_schema( $mysqli );
$wpdb = new QRMS_MariaDB_Wpdb( $mysqli );
qrms_mariadb_bind_wpdb( $wpdb );

$GLOBALS['qrms_mariadb_explain']        = array();
$GLOBALS['qrms_mariadb_show_create']    = array();
$GLOBALS['qrms_mariadb_attribution_ran'] = false;

$bas_post = '2026-09-27';
$bit_post = '2026-09-27';

/**
 * @param int                  $product_id Ürün (0 = yalnız özet).
 * @param array<string, mixed> $expected   KPI beklentileri.
 * @param string               $label      Etiket.
 * @return void
 */
function qrms_mariadb_assert_attribution( $product_id, array $expected, $label ) {
	$attr = qrms_mariadb_attribution( $GLOBALS['qrms_mariadb_bas'], $GLOBALS['qrms_mariadb_bit'] );
	$GLOBALS['qrms_mariadb_attribution_ran'] = true;
	if ( isset( $expected['ozet'] ) && is_array( $expected['ozet'] ) ) {
		foreach ( $expected['ozet'] as $key => $val ) {
			qrms_assert_same( $val, $attr['ozet'][ $key ] ?? null, $label . ' ozet.' . $key );
		}
	}
	if ( $product_id > 0 ) {
		$u = qrms_mariadb_attribution_urun( $attr, $product_id );
		foreach ( $expected as $key => $val ) {
			if ( 'ozet' === $key ) {
				continue;
			}
			qrms_assert_same( $val, $u[ $key ] ?? null, $label . ' ' . $key );
		}
	}
}

qrms_test(
	'C2-A basit eşleşme (shown → cart_add → order_sent)',
	function () use ( $wpdb, $bas_post, $bit_post ) {
		qrms_mariadb_reset_data( $wpdb->dbh );
		$GLOBALS['qrms_mariadb_bas'] = $bas_post;
		$GLOBALS['qrms_mariadb_bit'] = $bit_post;
		$session = 's_c2_a';
		$ref     = 'c2a11111-2222-3333-4444-555555555555';
		qrms_mariadb_rec_event( $wpdb, $ref, 'shown', 1001, $session, '2026-09-27 10:00:00' );
		qrms_mariadb_rec_event( $wpdb, $ref, QMO_Chatbot_DB::REC_EVENT_CART_ADD, 1001, $session, '2026-09-27 10:05:00' );
		qrms_mariadb_analytics(
			$wpdb,
			array(
				'event_type' => 'order_sent',
				'session_id' => $session,
				'item_id'    => 1001,
				'order_id'   => 'ord-c2-a-1',
				'created_at' => '2026-09-27 10:30:00',
			)
		);
		qrms_mariadb_assert_attribution(
			1001,
			array(
				'atfedilen_birim'         => 1,
				'atfedilen_kalem'         => 1,
				'atfedilen_siparis_tekil' => 1,
			),
			'C2-A'
		);
	}
);

qrms_test(
	'C2-B aynı ref → iki order_sent (mevcut SQL ref sayımı)',
	function () use ( $wpdb, $bas_post, $bit_post ) {
		qrms_mariadb_reset_data( $wpdb->dbh );
		$GLOBALS['qrms_mariadb_bas'] = $bas_post;
		$GLOBALS['qrms_mariadb_bit'] = $bit_post;
		$session = 's_c2_b';
		$ref     = 'c2b11111-2222-3333-4444-555555555555';
		qrms_mariadb_rec_event( $wpdb, $ref, QMO_Chatbot_DB::REC_EVENT_CART_ADD, 1002, $session, '2026-09-27 11:00:00' );
		qrms_mariadb_analytics(
			$wpdb,
			array(
				'event_type' => 'order_sent',
				'session_id' => $session,
				'item_id'    => 1002,
				'order_id'   => 'ord-c2-b-1',
				'created_at' => '2026-09-27 11:10:00',
			)
		);
		qrms_mariadb_analytics(
			$wpdb,
			array(
				'event_type' => 'order_sent',
				'session_id' => $session,
				'item_id'    => 1002,
				'order_id'   => 'ord-c2-b-2',
				'created_at' => '2026-09-27 11:20:00',
			)
		);
		qrms_mariadb_assert_attribution(
			1002,
			array(
				'atfedilen_birim'         => 1,
				'atfedilen_siparis_tekil' => 1,
			),
			'C2-B next-order'
		);
	}
);

qrms_test(
	'C2-C iki ref → iki sipariş',
	function () use ( $wpdb, $bas_post, $bit_post ) {
		qrms_mariadb_reset_data( $wpdb->dbh );
		$GLOBALS['qrms_mariadb_bas'] = $bas_post;
		$GLOBALS['qrms_mariadb_bit'] = $bit_post;
		$ref_a = 'c2caaaa1-2222-3333-4444-555555555555';
		$ref_b = 'c2cbbbb2-2222-3333-4444-555555555555';
		qrms_mariadb_rec_event( $wpdb, $ref_a, QMO_Chatbot_DB::REC_EVENT_CART_ADD, 1003, 's_c2_c_a', '2026-09-27 12:00:00' );
		qrms_mariadb_rec_event( $wpdb, $ref_b, QMO_Chatbot_DB::REC_EVENT_CART_ADD, 1003, 's_c2_c_b', '2026-09-27 12:01:00' );
		qrms_mariadb_analytics(
			$wpdb,
			array(
				'event_type' => 'order_sent',
				'session_id' => 's_c2_c_a',
				'item_id'    => 1003,
				'order_id'   => 'ord-c2-c-1',
				'created_at' => '2026-09-27 12:30:00',
			)
		);
		qrms_mariadb_analytics(
			$wpdb,
			array(
				'event_type' => 'order_sent',
				'session_id' => 's_c2_c_b',
				'item_id'    => 1003,
				'order_id'   => 'ord-c2-c-2',
				'created_at' => '2026-09-27 12:35:00',
			)
		);
		qrms_mariadb_assert_attribution( 1003, array( 'atfedilen_birim' => 2 ), 'C2-C' );
	}
);

qrms_test(
	'C2-D iptal — attribution dışı',
	function () use ( $wpdb, $bas_post, $bit_post ) {
		qrms_mariadb_reset_data( $wpdb->dbh );
		$GLOBALS['qrms_mariadb_bas'] = $bas_post;
		$GLOBALS['qrms_mariadb_bit'] = $bit_post;
		$session = 's_c2_d';
		$ref     = 'c2d11111-2222-3333-4444-555555555555';
		qrms_mariadb_rec_event( $wpdb, $ref, QMO_Chatbot_DB::REC_EVENT_CART_ADD, 1004, $session, '2026-09-27 13:00:00' );
		qrms_mariadb_analytics(
			$wpdb,
			array(
				'event_type' => 'order_sent',
				'session_id' => $session,
				'item_id'    => 1004,
				'order_id'   => 'ord-c2-d-1',
				'created_at' => '2026-09-27 13:10:00',
			)
		);
		qrms_mariadb_analytics(
			$wpdb,
			array(
				'event_type' => 'order_cancelled',
				'session_id' => $session,
				'item_id'    => 1004,
				'order_id'   => 'ord-c2-d-1',
				'created_at' => '2026-09-27 13:20:00',
			)
		);
		qrms_mariadb_assert_attribution(
			1004,
			array(
				'atfedilen_birim'         => 0,
				'atfedilen_kalem'         => 0,
				'atfedilen_siparis_tekil' => 0,
				'atfedilen_tutar'         => 0.0,
			),
			'C2-D'
		);
	}
);

qrms_test(
	'C2-E qty — SQL qty kullanmıyor',
	function () use ( $wpdb, $bas_post, $bit_post ) {
		qrms_mariadb_reset_data( $wpdb->dbh );
		$GLOBALS['qrms_mariadb_bas'] = $bas_post;
		$GLOBALS['qrms_mariadb_bit'] = $bit_post;
		$session = 's_c2_e';
		$ref     = 'c2e11111-2222-3333-4444-555555555555';
		qrms_mariadb_rec_event( $wpdb, $ref, QMO_Chatbot_DB::REC_EVENT_CART_ADD, 1005, $session, '2026-09-27 14:00:00' );
		qrms_mariadb_analytics(
			$wpdb,
			array(
				'event_type' => 'order_sent',
				'session_id' => $session,
				'item_id'    => 1005,
				'order_id'   => 'ord-c2-e-1',
				'qty'        => 3,
				'unit_price' => 10,
				'created_at' => '2026-09-27 14:10:00',
			)
		);
		qrms_mariadb_assert_attribution( 1005, array( 'atfedilen_birim' => 1 ), 'C2-E qty cap' );
	}
);

qrms_test(
	'C2-F NULL unit_price',
	function () use ( $wpdb, $bas_post, $bit_post ) {
		qrms_mariadb_reset_data( $wpdb->dbh );
		$GLOBALS['qrms_mariadb_bas'] = $bas_post;
		$GLOBALS['qrms_mariadb_bit'] = $bit_post;
		$session = 's_c2_f';
		$ref     = 'c2f11111-2222-3333-4444-555555555555';
		qrms_mariadb_rec_event( $wpdb, $ref, QMO_Chatbot_DB::REC_EVENT_CART_ADD, 1006, $session, '2026-09-27 15:00:00' );
		qrms_mariadb_analytics(
			$wpdb,
			array(
				'event_type' => 'order_sent',
				'session_id' => $session,
				'item_id'    => 1006,
				'order_id'   => 'ord-c2-f-1',
				'unit_price' => null,
				'created_at' => '2026-09-27 15:10:00',
			)
		);
		qrms_mariadb_assert_attribution(
			1006,
			array(
				'atfedilen_birim' => 1,
				'atfedilen_tutar' => 0.0,
			),
			'C2-F'
		);
	}
);

qrms_test(
	'C2-G zaman — cart_add öncesi order_sent sayılmaz',
	function () use ( $wpdb, $bas_post, $bit_post ) {
		qrms_mariadb_reset_data( $wpdb->dbh );
		$GLOBALS['qrms_mariadb_bas'] = $bas_post;
		$GLOBALS['qrms_mariadb_bit'] = $bit_post;
		$session = 's_c2_g';
		$ref     = 'c2g11111-2222-3333-4444-555555555555';
		qrms_mariadb_rec_event( $wpdb, $ref, QMO_Chatbot_DB::REC_EVENT_CART_ADD, 1007, $session, '2026-09-27 16:05:00' );
		qrms_mariadb_analytics(
			$wpdb,
			array(
				'event_type' => 'order_sent',
				'session_id' => $session,
				'item_id'    => 1007,
				'order_id'   => 'ord-c2-g-early',
				'created_at' => '2026-09-27 16:04:00',
			)
		);
		qrms_mariadb_assert_attribution( 1007, array( 'atfedilen_birim' => 0 ), 'C2-G early' );
		qrms_mariadb_analytics(
			$wpdb,
			array(
				'event_type' => 'order_sent',
				'session_id' => $session,
				'item_id'    => 1007,
				'order_id'   => 'ord-c2-g-late',
				'created_at' => '2026-09-27 16:30:00',
			)
		);
		qrms_mariadb_assert_attribution( 1007, array( 'atfedilen_birim' => 1 ), 'C2-G late' );
	}
);

qrms_test(
	'C2-H session izolasyonu',
	function () use ( $wpdb, $bas_post, $bit_post ) {
		qrms_mariadb_reset_data( $wpdb->dbh );
		$GLOBALS['qrms_mariadb_bas'] = $bas_post;
		$GLOBALS['qrms_mariadb_bit'] = $bit_post;
		$ref_a = 'c2haaaa1-2222-3333-4444-555555555555';
		$ref_b = 'c2hbbbb2-2222-3333-4444-555555555555';
		qrms_mariadb_rec_event( $wpdb, $ref_a, QMO_Chatbot_DB::REC_EVENT_CART_ADD, 1008, 's_c2_h_a', '2026-09-27 17:00:00' );
		qrms_mariadb_rec_event( $wpdb, $ref_b, QMO_Chatbot_DB::REC_EVENT_CART_ADD, 1008, 's_c2_h_b', '2026-09-27 17:01:00' );
		qrms_mariadb_analytics(
			$wpdb,
			array(
				'event_type' => 'order_sent',
				'session_id' => 's_c2_h_a',
				'item_id'    => 1008,
				'order_id'   => 'ord-c2-h-1',
				'created_at' => '2026-09-27 17:30:00',
			)
		);
		// ref_b sessionında sipariş yok → yalnızca ref_a sayılır.
		qrms_mariadb_assert_attribution( 1008, array( 'atfedilen_birim' => 1 ), 'C2-H' );
	}
);

// Phase 6.2 C1 — yeni spesifikasyon
qrms_test(
	'C1-1 same ref cannot be consumed twice',
	function () use ( $wpdb, $bas_post, $bit_post ) {
		qrms_mariadb_reset_data( $wpdb->dbh );
		$GLOBALS['qrms_mariadb_bas'] = $bas_post;
		$GLOBALS['qrms_mariadb_bit'] = $bit_post;
		$session = 's_c1_1';
		$ref     = 'c1a11111-2222-3333-4444-555555555555';
		qrms_mariadb_rec_event( $wpdb, $ref, QMO_Chatbot_DB::REC_EVENT_CART_ADD, 2001, $session, '2026-09-27 10:00:00' );
		qrms_mariadb_analytics( $wpdb, array( 'session_id' => $session, 'item_id' => 2001, 'order_id' => 'o-c1-1', 'created_at' => '2026-09-27 10:10:00' ) );
		qrms_mariadb_analytics( $wpdb, array( 'session_id' => $session, 'item_id' => 2001, 'order_id' => 'o-c1-2', 'created_at' => '2026-09-27 10:20:00' ) );
		qrms_mariadb_assert_attribution(
			2001,
			array(
				'atfedilen_birim'         => 1,
				'atfedilen_kalem'         => 1,
				'atfedilen_siparis_tekil' => 1,
			),
			'C1-1'
		);
	}
);

qrms_test(
	'C1-2 sequential refs → sequential orders',
	function () use ( $wpdb, $bas_post, $bit_post ) {
		qrms_mariadb_reset_data( $wpdb->dbh );
		$GLOBALS['qrms_mariadb_bas'] = $bas_post;
		$GLOBALS['qrms_mariadb_bit'] = $bit_post;
		$session = 's_c1_2';
		qrms_mariadb_rec_event( $wpdb, 'c1b11111-2222-3333-4444-555555555555', QMO_Chatbot_DB::REC_EVENT_CART_ADD, 2002, $session, '2026-09-27 11:00:00' );
		qrms_mariadb_analytics( $wpdb, array( 'session_id' => $session, 'item_id' => 2002, 'order_id' => 'o-c1-2a', 'created_at' => '2026-09-27 11:10:00' ) );
		qrms_mariadb_rec_event( $wpdb, 'c1b22222-2222-3333-4444-555555555555', QMO_Chatbot_DB::REC_EVENT_CART_ADD, 2002, $session, '2026-09-27 11:11:00' );
		qrms_mariadb_analytics( $wpdb, array( 'session_id' => $session, 'item_id' => 2002, 'order_id' => 'o-c1-2b', 'created_at' => '2026-09-27 11:20:00' ) );
		qrms_mariadb_assert_attribution( 2002, array( 'atfedilen_birim' => 2, 'atfedilen_siparis_tekil' => 2 ), 'C1-2' );
	}
);

qrms_test(
	'C1-3 qty>ref → attributed_units=1',
	function () use ( $wpdb, $bas_post, $bit_post ) {
		qrms_mariadb_reset_data( $wpdb->dbh );
		$GLOBALS['qrms_mariadb_bas'] = $bas_post;
		$GLOBALS['qrms_mariadb_bit'] = $bit_post;
		$session = 's_c1_3';
		qrms_mariadb_rec_event( $wpdb, 'c1c11111-2222-3333-4444-555555555555', QMO_Chatbot_DB::REC_EVENT_CART_ADD, 2003, $session, '2026-09-27 12:00:00' );
		qrms_mariadb_analytics( $wpdb, array( 'session_id' => $session, 'item_id' => 2003, 'order_id' => 'o-c1-3', 'qty' => 3, 'unit_price' => 5, 'created_at' => '2026-09-27 12:10:00' ) );
		qrms_mariadb_assert_attribution( 2003, array( 'atfedilen_birim' => 1, 'atfedilen_tutar' => 5.0 ), 'C1-3' );
	}
);

qrms_test(
	'C1-14 atfedilen_siparis (tekil) ≠ atfedilen_birim',
	function () use ( $wpdb, $bas_post, $bit_post ) {
		qrms_mariadb_reset_data( $wpdb->dbh );
		$GLOBALS['qrms_mariadb_bas'] = $bas_post;
		$GLOBALS['qrms_mariadb_bit'] = $bit_post;
		$session = 's_c1_14';
		qrms_mariadb_rec_event( $wpdb, 'c1n11111-2222-3333-4444-555555555555', QMO_Chatbot_DB::REC_EVENT_CART_ADD, 2015, $session, '2026-09-27 13:00:00' );
		qrms_mariadb_rec_event( $wpdb, 'c1n22222-2222-3333-4444-555555555555', QMO_Chatbot_DB::REC_EVENT_CART_ADD, 2015, $session, '2026-09-27 13:01:00' );
		qrms_mariadb_analytics( $wpdb, array( 'session_id' => $session, 'item_id' => 2015, 'order_id' => 'o-c1-14', 'qty' => 3, 'unit_price' => 10, 'created_at' => '2026-09-27 13:30:00' ) );
		qrms_mariadb_assert_attribution(
			2015,
			array(
				'atfedilen_siparis_tekil' => 1,
				'atfedilen_birim'         => 2,
			),
			'C1-14 engine'
		);
		update_option( QMO_Chatbot_DB::OPT, QMO_Chatbot_DB::SURUM );
		$rapor = QMO_Chatbot_DB::oneri_rapor( $bas_post, $bit_post );
		$satir = null;
		foreach ( $rapor as $row ) {
			if ( (int) $row['urun_id'] === 2015 ) {
				$satir = $row;
				break;
			}
		}
		qrms_assert_same( 1, $satir['atfedilen_siparis'], 'rapor atfedilen_siparis=tekil' );
		qrms_assert_same( 1, $satir['atfedilen_siparis_tekil'], 'rapor tekil alan' );
		qrms_assert_same( 2, $satir['atfedilen_birim'], 'rapor birim' );
		qrms_assert_true( $satir['atfedilen_siparis'] !== $satir['atfedilen_birim'], 'siparis != birim' );
	}
);

qrms_test(
	'C1-4 two refs one order qty=3 → units=2',
	function () use ( $wpdb, $bas_post, $bit_post ) {
		qrms_mariadb_reset_data( $wpdb->dbh );
		$GLOBALS['qrms_mariadb_bas'] = $bas_post;
		$GLOBALS['qrms_mariadb_bit'] = $bit_post;
		$session = 's_c1_4';
		qrms_mariadb_rec_event( $wpdb, 'c1d11111-2222-3333-4444-555555555555', QMO_Chatbot_DB::REC_EVENT_CART_ADD, 2004, $session, '2026-09-27 13:00:00' );
		qrms_mariadb_rec_event( $wpdb, 'c1d22222-2222-3333-4444-555555555555', QMO_Chatbot_DB::REC_EVENT_CART_ADD, 2004, $session, '2026-09-27 13:01:00' );
		qrms_mariadb_analytics( $wpdb, array( 'session_id' => $session, 'item_id' => 2004, 'order_id' => 'o-c1-4', 'qty' => 3, 'unit_price' => 10, 'created_at' => '2026-09-27 13:30:00' ) );
		qrms_mariadb_assert_attribution( 2004, array( 'atfedilen_birim' => 2, 'atfedilen_tutar' => 20.0 ), 'C1-4' );
	}
);

qrms_test(
	'C1-5 three refs order qty=2 → units=2',
	function () use ( $wpdb, $bas_post, $bit_post ) {
		qrms_mariadb_reset_data( $wpdb->dbh );
		$GLOBALS['qrms_mariadb_bas'] = $bas_post;
		$GLOBALS['qrms_mariadb_bit'] = $bit_post;
		$session = 's_c1_5';
		foreach ( array( 'c1e11111-2222-3333-4444-555555555555', 'c1e22222-2222-3333-4444-555555555555', 'c1e33333-2222-3333-4444-555555555555' ) as $i => $ref ) {
			qrms_mariadb_rec_event( $wpdb, $ref, QMO_Chatbot_DB::REC_EVENT_CART_ADD, 2005, $session, '2026-09-27 14:0' . $i . ':00' );
		}
		qrms_mariadb_analytics( $wpdb, array( 'session_id' => $session, 'item_id' => 2005, 'order_id' => 'o-c1-5', 'qty' => 2, 'created_at' => '2026-09-27 14:30:00' ) );
		qrms_mariadb_assert_attribution( 2005, array( 'atfedilen_birim' => 2 ), 'C1-5' );
	}
);

qrms_test(
	'C1-6 cancelled order → zero KPI',
	function () use ( $wpdb, $bas_post, $bit_post ) {
		qrms_mariadb_reset_data( $wpdb->dbh );
		$GLOBALS['qrms_mariadb_bas'] = $bas_post;
		$GLOBALS['qrms_mariadb_bit'] = $bit_post;
		$session = 's_c1_6';
		qrms_mariadb_rec_event( $wpdb, 'c1f11111-2222-3333-4444-555555555555', QMO_Chatbot_DB::REC_EVENT_CART_ADD, 2006, $session, '2026-09-27 15:00:00' );
		qrms_mariadb_analytics( $wpdb, array( 'session_id' => $session, 'item_id' => 2006, 'order_id' => 'o-c1-6', 'created_at' => '2026-09-27 15:10:00' ) );
		qrms_mariadb_analytics( $wpdb, array( 'event_type' => 'order_cancelled', 'session_id' => $session, 'item_id' => 2006, 'order_id' => 'o-c1-6', 'created_at' => '2026-09-27 15:20:00' ) );
		qrms_mariadb_assert_attribution(
			2006,
			array(
				'atfedilen_birim'         => 0,
				'atfedilen_kalem'         => 0,
				'atfedilen_siparis_tekil' => 0,
				'atfedilen_tutar'         => 0.0,
			),
			'C1-6'
		);
	}
);

qrms_test(
	'C1-7 NULL unit_price → revenue 0 units 1',
	function () use ( $wpdb, $bas_post, $bit_post ) {
		qrms_mariadb_reset_data( $wpdb->dbh );
		$GLOBALS['qrms_mariadb_bas'] = $bas_post;
		$GLOBALS['qrms_mariadb_bit'] = $bit_post;
		$session = 's_c1_7';
		qrms_mariadb_rec_event( $wpdb, 'c1g11111-2222-3333-4444-555555555555', QMO_Chatbot_DB::REC_EVENT_CART_ADD, 2007, $session, '2026-09-27 16:00:00' );
		qrms_mariadb_analytics( $wpdb, array( 'session_id' => $session, 'item_id' => 2007, 'order_id' => 'o-c1-7', 'qty' => 2, 'unit_price' => null, 'created_at' => '2026-09-27 16:10:00' ) );
		qrms_mariadb_assert_attribution(
			2007,
			array(
				'atfedilen_birim'         => 1,
				'atfedilen_kalem'         => 1,
				'atfedilen_siparis_tekil' => 1,
				'atfedilen_tutar'         => 0.0,
			),
			'C1-7'
		);
	}
);

qrms_test(
	'C1-8 one order two lines → tekil sipariş 1',
	function () use ( $wpdb, $bas_post, $bit_post ) {
		qrms_mariadb_reset_data( $wpdb->dbh );
		$GLOBALS['qrms_mariadb_bas'] = $bas_post;
		$GLOBALS['qrms_mariadb_bit'] = $bit_post;
		$session = 's_c1_8';
		qrms_mariadb_rec_event( $wpdb, 'c1h11111-2222-3333-4444-555555555555', QMO_Chatbot_DB::REC_EVENT_CART_ADD, 2008, $session, '2026-09-27 17:00:00' );
		qrms_mariadb_rec_event( $wpdb, 'c1h22222-2222-3333-4444-555555555555', QMO_Chatbot_DB::REC_EVENT_CART_ADD, 2009, $session, '2026-09-27 17:01:00' );
		qrms_mariadb_analytics( $wpdb, array( 'session_id' => $session, 'item_id' => 2008, 'order_id' => 'o-c1-8', 'created_at' => '2026-09-27 17:30:00' ) );
		qrms_mariadb_analytics( $wpdb, array( 'session_id' => $session, 'item_id' => 2009, 'order_id' => 'o-c1-8', 'created_at' => '2026-09-27 17:30:05' ) );
		qrms_mariadb_assert_attribution(
			0,
			array(
				'ozet' => array(
					'atfedilen_siparis_tekil' => 1,
					'atfedilen_kalem'         => 2,
					'atfedilen_birim'         => 2,
				),
			),
			'C1-8'
		);
	}
);

qrms_test(
	'C1-9 duplicate analytics rows same line → single kalem',
	function () use ( $wpdb, $bas_post, $bit_post ) {
		qrms_mariadb_reset_data( $wpdb->dbh );
		$GLOBALS['qrms_mariadb_bas'] = $bas_post;
		$GLOBALS['qrms_mariadb_bit'] = $bit_post;
		$session = 's_c1_9';
		qrms_mariadb_rec_event( $wpdb, 'c1i11111-2222-3333-4444-555555555555', QMO_Chatbot_DB::REC_EVENT_CART_ADD, 2010, $session, '2026-09-27 18:00:00' );
		qrms_mariadb_analytics( $wpdb, array( 'session_id' => $session, 'item_id' => 2010, 'order_id' => 'o-c1-9', 'qty' => 1, 'created_at' => '2026-09-27 18:10:00' ) );
		qrms_mariadb_analytics( $wpdb, array( 'session_id' => $session, 'item_id' => 2010, 'order_id' => 'o-c1-9', 'qty' => 2, 'created_at' => '2026-09-27 18:10:01' ) );
		qrms_mariadb_assert_attribution( 2010, array( 'atfedilen_kalem' => 1, 'atfedilen_birim' => 1 ), 'C1-9 agg qty=3 cap 1 ref' );
	}
);

qrms_test(
	'C1-10 session isolation',
	function () use ( $wpdb, $bas_post, $bit_post ) {
		qrms_mariadb_reset_data( $wpdb->dbh );
		$GLOBALS['qrms_mariadb_bas'] = $bas_post;
		$GLOBALS['qrms_mariadb_bit'] = $bit_post;
		qrms_mariadb_rec_event( $wpdb, 'c1j11111-2222-3333-4444-555555555555', QMO_Chatbot_DB::REC_EVENT_CART_ADD, 2011, 's_c1_10a', '2026-09-27 19:00:00' );
		qrms_mariadb_analytics( $wpdb, array( 'session_id' => 's_c1_10b', 'item_id' => 2011, 'order_id' => 'o-c1-10', 'created_at' => '2026-09-27 19:10:00' ) );
		qrms_mariadb_assert_attribution( 2011, array( 'atfedilen_birim' => 0 ), 'C1-10' );
	}
);

qrms_test(
	'C1-11 pre-cart qty=3 → units=1',
	function () use ( $wpdb, $bas_post, $bit_post ) {
		qrms_mariadb_reset_data( $wpdb->dbh );
		$GLOBALS['qrms_mariadb_bas'] = $bas_post;
		$GLOBALS['qrms_mariadb_bit'] = $bit_post;
		$session = 's_c1_11';
		qrms_mariadb_rec_event( $wpdb, 'c1k11111-2222-3333-4444-555555555555', QMO_Chatbot_DB::REC_EVENT_CART_ADD, 2012, $session, '2026-09-27 20:00:00' );
		qrms_mariadb_analytics( $wpdb, array( 'session_id' => $session, 'item_id' => 2012, 'order_id' => 'o-c1-11', 'qty' => 3, 'created_at' => '2026-09-27 20:10:00' ) );
		qrms_mariadb_assert_attribution( 2012, array( 'atfedilen_birim' => 1 ), 'C1-11' );
	}
);

qrms_test(
	'C1-12 order before recommendation',
	function () use ( $wpdb, $bas_post, $bit_post ) {
		qrms_mariadb_reset_data( $wpdb->dbh );
		$GLOBALS['qrms_mariadb_bas'] = $bas_post;
		$GLOBALS['qrms_mariadb_bit'] = $bit_post;
		$session = 's_c1_12';
		qrms_mariadb_analytics( $wpdb, array( 'session_id' => $session, 'item_id' => 2013, 'order_id' => 'o-c1-12', 'created_at' => '2026-09-27 21:00:00' ) );
		qrms_mariadb_rec_event( $wpdb, 'c1l11111-2222-3333-4444-555555555555', QMO_Chatbot_DB::REC_EVENT_CART_ADD, 2013, $session, '2026-09-27 21:05:00' );
		qrms_mariadb_assert_attribution( 2013, array( 'atfedilen_birim' => 0 ), 'C1-12' );
	}
);

qrms_test(
	'C1-13 same timestamp cart and order (>=)',
	function () use ( $wpdb, $bas_post, $bit_post ) {
		qrms_mariadb_reset_data( $wpdb->dbh );
		$GLOBALS['qrms_mariadb_bas'] = $bas_post;
		$GLOBALS['qrms_mariadb_bit'] = $bit_post;
		$session = 's_c1_13';
		$t       = '2026-09-27 22:00:00';
		qrms_mariadb_rec_event( $wpdb, 'c1m11111-2222-3333-4444-555555555555', QMO_Chatbot_DB::REC_EVENT_CART_ADD, 2014, $session, $t );
		qrms_mariadb_analytics( $wpdb, array( 'session_id' => $session, 'item_id' => 2014, 'order_id' => 'o-c1-13', 'created_at' => $t ) );
		qrms_mariadb_assert_attribution( 2014, array( 'atfedilen_birim' => 1 ), 'C1-13' );
	}
);

// EXPLAIN + SHOW CREATE (fixture: C2-A benzeri minimal veri).
qrms_mariadb_reset_data( $mysqli );
qrms_mariadb_rec_event( $wpdb, 'c2expl11-2222-3333-4444-555555555555', QMO_Chatbot_DB::REC_EVENT_CART_ADD, 1001, 's_expl', '2026-09-27 10:05:00' );
qrms_mariadb_analytics(
	$wpdb,
	array(
		'event_type' => 'order_sent',
		'session_id' => 's_expl',
		'item_id'    => 1001,
		'order_id'   => 'ord-expl',
		'created_at' => '2026-09-27 10:30:00',
	)
);
qrms_mariadb_attribution( $bas_post, $bit_post );
$attr_sql = qrms_mariadb_last_attribution_sql();
$GLOBALS['qrms_mariadb_explain']['traditional'] = qrms_mariadb_explain_rows( $mysqli, $attr_sql );
$json_res = $mysqli->query( 'EXPLAIN FORMAT=JSON ' . $attr_sql );
if ( $json_res ) {
	$row = $json_res->fetch_row();
	$json_res->free();
	$GLOBALS['qrms_mariadb_explain']['json'] = $row ? $row[0] : '';
}
$GLOBALS['qrms_mariadb_show_create']['wp_rma_analytics']                  = qrms_mariadb_show_create( $mysqli, 'wp_rma_analytics' );
$GLOBALS['qrms_mariadb_show_create']['wp_qmo_chatbot_recommendation_events'] = qrms_mariadb_show_create( $mysqli, 'wp_qmo_chatbot_recommendation_events' );

$mysqli->close();

$failures = $GLOBALS['qrms_failures'];
$assertions = $GLOBALS['qrms_assertions'];

echo "\n--- MariaDB diagnostics ---\n";
echo 'Attribution SQL çalıştı: ' . ( $GLOBALS['qrms_mariadb_attribution_ran'] ? 'yes' : 'no' ) . "\n";
echo 'EXPLAIN satır sayısı: ' . count( $GLOBALS['qrms_mariadb_explain']['traditional'] ?? array() ) . "\n";
if ( ! empty( $GLOBALS['qrms_mariadb_explain']['traditional'] ) ) {
	foreach ( $GLOBALS['qrms_mariadb_explain']['traditional'] as $i => $row ) {
		echo '  EXPLAIN[' . $i . '] table=' . ( $row['table'] ?? '' ) . ' type=' . ( $row['type'] ?? '' ) . ' key=' . ( $row['key'] ?? '' ) . ' rows=' . ( $row['rows'] ?? '' ) . ' Extra=' . ( $row['Extra'] ?? '' ) . "\n";
	}
}

if ( ! empty( $failures ) ) {
	echo "\n\033[31m" . count( $failures ) . " test başarısız\033[0m ({$assertions} doğrulama)\n";
	exit( 1 );
}

echo "\n\033[32mTüm MariaDB attribution testleri geçti\033[0m ({$assertions} doğrulama)\n";
exit( 0 );
