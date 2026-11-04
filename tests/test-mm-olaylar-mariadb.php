<?php
/**
 * Menü mühendisliği olaylar() — gerçek MariaDB/$wpdb üzerinde iptal davranışı (S1).
 *
 * Çalıştırma: tests/mariadb/run-attribution-tests.sh (ya da MariaDB ayakta +
 * QRMS_MARIADB_* ile: php tests/test-mm-olaylar-mariadb.php)
 *
 * @package QR_Menu_Suite
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mariadb/harness.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/class-db.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-menu-muhendisligi/includes/class-qrms-mm-maliyet.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-menu-muhendisligi/includes/class-qrms-mm-rapor.php';

echo "\nMenü Mühendisliği olaylar() — MariaDB (iptal edilen sipariş satış sayılmaz)\n";

if ( ! qrms_mariadb_available() ) {
	echo "\033[31mBLOCKED\033[0m: MariaDB erişilemiyor.\n";
	exit( 2 );
}

$mysqli = qrms_mariadb_connect();
qrms_mariadb_apply_schema( $mysqli );
$wpdb = new QRMS_MariaDB_Wpdb( $mysqli );
qrms_mariadb_bind_wpdb( $wpdb );

// Aralık: 2026-09-28 12:00 - 30 gün; tüm satırlar 2026-09-27.
$GLOBALS['qrms_test']['now'] = strtotime( '2026-09-28 12:00:00 UTC' );

// Ürün 101: A (2, iptalsiz) + B (3, İPTAL) + C (1, order_id'siz eski satır)
// Ürün 102: yalnızca iptal edilmiş sipariş (4)
// Ürün 103: iptalsiz (5) ve cart_add / product_click
qrms_mariadb_analytics( $wpdb, array( 'item_id' => 101, 'qty' => 2, 'order_id' => 'QA-TEST-A', 'line_key' => 'l1' ) );
qrms_mariadb_analytics( $wpdb, array( 'item_id' => 101, 'qty' => 3, 'order_id' => 'QA-TEST-B', 'line_key' => 'l1' ) );
qrms_mariadb_analytics( $wpdb, array( 'item_id' => 102, 'qty' => 4, 'order_id' => 'QA-TEST-B', 'line_key' => 'l2' ) );
qrms_mariadb_analytics( $wpdb, array( 'event_type' => 'order_cancelled', 'item_id' => 0, 'qty' => 1, 'order_id' => 'QA-TEST-B', 'line_key' => '' ) );
qrms_mariadb_analytics( $wpdb, array( 'item_id' => 101, 'qty' => 1, 'order_id' => null, 'line_key' => null ) );
qrms_mariadb_analytics( $wpdb, array( 'item_id' => 103, 'qty' => 5, 'order_id' => 'QA-TEST-C', 'line_key' => 'l1' ) );
qrms_mariadb_analytics( $wpdb, array( 'event_type' => 'cart_add', 'item_id' => 103, 'qty' => 1 ) );
qrms_mariadb_analytics( $wpdb, array( 'event_type' => 'product_click', 'item_id' => 103, 'qty' => 1 ) );
// Aralık dışı iptal edilmemiş satır (30 günden eski) sayılmamalı.
qrms_mariadb_analytics( $wpdb, array( 'item_id' => 103, 'qty' => 9, 'order_id' => 'QA-TEST-OLD', 'line_key' => 'l1', 'created_at' => '2026-07-01 12:00:00' ) );

$metot = new ReflectionMethod( 'QRMS_MM_Rapor', 'olaylar' );
$say   = $metot->invoke( null, 30 );

echo '  olaylar(30) ham çıktı: ' . wp_json_encode( $say ) . "\n";

qrms_test(
	'olaylar(): iptal edilen siparişin satırları satışa yazılmaz',
	function () use ( $say ) {
		qrms_assert_same( 3, $say[101]['satis'], '101 = A(2) + order_id\'siz(1); B(3) iptal' );
		qrms_assert_false( isset( $say[102] ) && $say[102]['satis'] > 0, '102 yalnız iptalli sipariş -> satış 0' );
	}
);

qrms_test(
	'olaylar(): iptalsiz sipariş, sepet ve tıklama aynen sayılır; aralık dışı sayılmaz',
	function () use ( $say ) {
		qrms_assert_same( 5, $say[103]['satis'], 'iptalsiz sipariş sayıldı (eski 9 adet aralık dışı)' );
		qrms_assert_same( 1, $say[103]['sepet'], 'cart_add etkilenmedi' );
		qrms_assert_same( 1, $say[103]['tik'], 'product_click etkilenmedi' );
	}
);

qrms_test(
	'olaylar(): order_id\'siz eski satır iptalden etkilenmez',
	function () use ( $say ) {
		// 101'in 1 adedi order_id'siz satırdan gelir (3 = 2 + 1).
		qrms_assert_same( 3, $say[101]['satis'], 'NULL order_id satırı sayıldı' );
	}
);

qrms_test(
	'olaylar(): EXPLAIN — iptal alt sorgusu indeks kullanır',
	function () use ( $mysqli ) {
		$tablo = 'wp_rma_analytics';
		$sql   = "EXPLAIN SELECT item_id, SUM(qty) FROM {$tablo} t WHERE created_at >= '2026-08-29 12:00:00' AND item_id > 0 AND event_type IN ('order_sent','cart_add','product_click') AND ( event_type <> 'order_sent' OR NOT EXISTS ( SELECT 1 FROM {$tablo} c WHERE c.event_type = 'order_cancelled' AND c.order_id = t.order_id ) ) GROUP BY item_id";
		$res   = $mysqli->query( $sql );
		$alt   = null;
		while ( $res && ( $r = $res->fetch_assoc() ) ) {
			if ( 'c' === $r['table'] ) {
				$alt = $r;
			}
		}
		echo '  EXPLAIN alt sorgu: ' . wp_json_encode( $alt ) . "\n";
		qrms_assert_true( null !== $alt && ! empty( $alt['key'] ), 'alt sorgu indeks kullanıyor (ALL değil)' );
	}
);

$mysqli->close();

if ( ! empty( $GLOBALS['qrms_failures'] ) ) {
	echo "\n\033[31m" . count( $GLOBALS['qrms_failures'] ) . " test başarısız\033[0m ({$GLOBALS['qrms_assertions']} doğrulama)\n";
	exit( 1 );
}

echo "\n\033[32mTüm olaylar() MariaDB testleri geçti\033[0m ({$GLOBALS['qrms_assertions']} doğrulama)\n";
exit( 0 );
