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
 * @param int   $product_id Ürün.
 * @param int   $expected   Beklenen atfedilen (mevcut SQL).
 * @param string $label     Etiket.
 * @return void
 */
function qrms_mariadb_assert_atfedilen( $product_id, $expected, $label ) {
	$map = qrms_mariadb_atfedilen_map( $GLOBALS['qrms_mariadb_bas'], $GLOBALS['qrms_mariadb_bit'] );
	$GLOBALS['qrms_mariadb_attribution_ran'] = true;
	qrms_assert_same( $expected, qrms_mariadb_atfedilen_for_product( $product_id, $map ), $label );
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
		qrms_mariadb_assert_atfedilen( 1001, 1, 'tek ref tek sipariş' );
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
		// COUNT(DISTINCT ref_id): iki sipariş satırı, tek ref → 1 (sızıntı: gerçek sipariş adedi değil).
		qrms_mariadb_assert_atfedilen( 1002, 1, 'iki order_sent tek ref' );
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
		qrms_mariadb_assert_atfedilen( 1003, 2, 'iki ref iki sipariş' );
	}
);

qrms_test(
	'C2-D iptal — order_cancelled SQL filtresinde değil',
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
		qrms_mariadb_assert_atfedilen( 1004, 1, 'iptal atfedilen sayımını düşürmez' );
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
		qrms_mariadb_assert_atfedilen( 1005, 1, 'qty=3 yine 1 ref' );
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
		qrms_mariadb_assert_atfedilen( 1006, 1, 'NULL unit_price join bozmaz' );
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
		qrms_mariadb_assert_atfedilen( 1007, 0, 'cart_add öncesi order' );
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
		qrms_mariadb_assert_atfedilen( 1007, 1, 'cart_add sonrası order' );
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
		qrms_mariadb_assert_atfedilen( 1008, 1, 'yabancı session siparişi ref_b ye gitmez' );
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
$attr_sql = qrms_mariadb_attribution_sql_prepared( $bas_post, $bit_post );
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

echo "\n\033[32mTüm MariaDB attribution testleri geçti\033[0m ({$assertions} doğrulama, 8 senaryo)\n";
exit( 0 );
