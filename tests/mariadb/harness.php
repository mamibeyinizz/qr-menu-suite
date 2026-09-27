<?php
/**
 * MariaDB attribution test harness — bağlantı, şema, fixture, production SQL.
 *
 * @package QR_Menu_Suite
 */

require_once __DIR__ . '/wpdb-mysqli.php';

/**
 * Ortam değişkeninden okur.
 *
 * @param string $key     Anahtar.
 * @param string $default Varsayılan.
 * @return string
 */
function qrms_mariadb_env( $key, $default ) {
	$val = getenv( $key );
	return ( false === $val || '' === $val ) ? $default : $val;
}

/**
 * @return mysqli|null
 */
function qrms_mariadb_connect() {
	$host = qrms_mariadb_env( 'QRMS_MARIADB_HOST', '127.0.0.1' );
	$port = (int) qrms_mariadb_env( 'QRMS_MARIADB_PORT', '13306' );
	$user = qrms_mariadb_env( 'QRMS_MARIADB_USER', 'qrms_test' );
	$pass = qrms_mariadb_env( 'QRMS_MARIADB_PASSWORD', 'qrms_test_pw' );
	$db   = qrms_mariadb_env( 'QRMS_MARIADB_NAME', 'qrms_test' );

	$mysqli = mysqli_init();
	if ( ! $mysqli ) {
		return null;
	}
	$mysqli->options( MYSQLI_OPT_CONNECT_TIMEOUT, 3 );
	if ( ! @$mysqli->real_connect( $host, $user, $pass, $db, $port ) ) {
		return null;
	}
	$mysqli->set_charset( 'utf8mb4' );
	return $mysqli;
}

/**
 * @return bool
 */
function qrms_mariadb_available() {
	$db = qrms_mariadb_connect();
	if ( ! $db ) {
		return false;
	}
	$db->close();
	return true;
}

/**
 * Şemayı uygular (DROP + CREATE).
 *
 * @param mysqli $mysqli Bağlantı.
 * @return void
 * @throws Exception SQL hatası.
 */
function qrms_mariadb_apply_schema( mysqli $mysqli ) {
	$sql = file_get_contents( __DIR__ . '/schema.sql' );
	if ( false === $sql ) {
		throw new Exception( 'schema.sql okunamadı' );
	}
	if ( ! $mysqli->multi_query( $sql ) ) {
		throw new Exception( 'schema: ' . $mysqli->error );
	}
	while ( $mysqli->more_results() ) {
		$mysqli->next_result();
	}
}

/**
 * Test öncesi tabloları boşaltır.
 *
 * @param mysqli $mysqli Bağlantı.
 * @return void
 */
function qrms_mariadb_reset_data( mysqli $mysqli ) {
	$mysqli->query( 'SET FOREIGN_KEY_CHECKS=0' );
	$mysqli->query( 'TRUNCATE TABLE wp_qmo_chatbot_recommendation_events' );
	$mysqli->query( 'TRUNCATE TABLE wp_rma_analytics' );
	$mysqli->query( 'SET FOREIGN_KEY_CHECKS=1' );
}

/**
 * @param QRMS_MariaDB_Wpdb $wpdb wpdb.
 * @return void
 */
function qrms_mariadb_bind_wpdb( QRMS_MariaDB_Wpdb $wpdb ) {
	$GLOBALS['wpdb'] = $wpdb;
	update_option( QMO_Chatbot_DB::OPT, QMO_Chatbot_DB::SURUM );
	update_option( QRMS_Analitik::DB_OPT, QRMS_Analitik::DB_SURUM );
}

/**
 * Production oneri_rapor_atfedilen_siparis() çıktısı.
 *
 * @param string $bas_ymd Y-m-d.
 * @param string $bit_ymd Y-m-d.
 * @return array<int,int> urun_id => atfedilen
 * @throws ReflectionException
 */
function qrms_mariadb_atfedilen_map( $bas_ymd, $bit_ymd ) {
	$bas = sanitize_text_field( $bas_ymd ) . ' 00:00:00';
	$bit = sanitize_text_field( $bit_ymd ) . ' 23:59:59';
	$ref = new ReflectionMethod( 'QMO_Chatbot_DB', 'oneri_rapor_atfedilen_siparis' );
	$ref->setAccessible( true );
	return $ref->invoke( null, $bas, $bit );
}

/**
 * @param int $product_id Ürün.
 * @param array<int,int> $map atfedilen map.
 * @return int
 */
function qrms_mariadb_atfedilen_for_product( $product_id, array $map ) {
	return (int) ( $map[ (int) $product_id ] ?? 0 );
}

/**
 * @param QRMS_MariaDB_Wpdb $wpdb wpdb.
 * @param string            $ref_id Ref.
 * @param string            $event_type shown|cart_add.
 * @param int               $product_id Ürün.
 * @param string            $session_id Oturum.
 * @param string            $created_at Zaman.
 * @return void
 */
function qrms_mariadb_rec_event( QRMS_MariaDB_Wpdb $wpdb, $ref_id, $event_type, $product_id, $session_id, $created_at ) {
	$wpdb->insert(
		$wpdb->prefix . 'qmo_chatbot_recommendation_events',
		array(
			'ref_id'     => $ref_id,
			'event_type' => $event_type,
			'product_id' => (int) $product_id,
			'session_id' => $session_id,
			'source'     => 'ai',
			'created_at' => $created_at,
		)
	);
}

/**
 * @param QRMS_MariaDB_Wpdb $wpdb wpdb.
 * @param array<string, mixed> $row Analitik satırı.
 * @return void
 */
function qrms_mariadb_analytics( QRMS_MariaDB_Wpdb $wpdb, array $row ) {
	$data = array_merge(
		array(
			'event_type'    => 'order_sent',
			'item_id'       => 0,
			'item_name'     => '',
			'category_name' => '',
			'qty'           => 1,
			'price'         => 0,
			'unit_price'    => null,
			'masa_no'       => '',
			'order_id'      => null,
			'session_id'    => '',
			'reason'        => null,
			'ip_hash'       => '',
			'created_at'    => '2026-09-27 12:00:00',
		),
		$row
	);
	$wpdb->insert( $wpdb->prefix . 'rma_analytics', $data );
}

/**
 * Production attribution JOIN SQL (EXPLAIN için).
 *
 * @param string $bas_ymd Başlangıç günü.
 * @param string $bit_ymd Bitiş günü.
 * @return string
 */
function qrms_mariadb_attribution_sql_prepared( $bas_ymd, $bit_ymd ) {
	global $wpdb;
	$bas = $bas_ymd . ' 00:00:00';
	$bit = $bit_ymd . ' 23:59:59';
	$tablo_rec = $wpdb->prefix . 'qmo_chatbot_recommendation_events';
	$analitik  = $wpdb->prefix . 'rma_analytics';
	return $wpdb->prepare(
		"SELECT cart.product_id AS urun_id, COUNT(DISTINCT cart.ref_id) AS atfedilen
		FROM {$tablo_rec} cart
		INNER JOIN {$analitik} o
		  ON o.event_type = 'order_sent'
		 AND o.session_id = cart.session_id
		 AND o.item_id = cart.product_id
		 AND o.created_at >= cart.created_at
		 AND o.created_at >= %s
		WHERE cart.event_type = %s
		  AND cart.created_at >= %s AND cart.created_at <= %s
		GROUP BY cart.product_id",
		$bas,
		QMO_Chatbot_DB::REC_EVENT_CART_ADD,
		$bas,
		$bit
	);
}

/**
 * @param mysqli $mysqli Bağlantı.
 * @param string $sql    SQL.
 * @return array<int, array<string, mixed>>
 */
function qrms_mariadb_explain_rows( mysqli $mysqli, $sql ) {
	$rows  = array();
	$query = 'EXPLAIN ' . $sql;
	$res   = $mysqli->query( $query );
	if ( false === $res ) {
		return array();
	}
	while ( $row = $res->fetch_assoc() ) {
		$rows[] = $row;
	}
	$res->free();
	return $rows;
}

/**
 * @param mysqli $mysqli Bağlantı.
 * @param string $table  Tablo adı.
 * @return string
 */
function qrms_mariadb_show_create( mysqli $mysqli, $table ) {
	$res = $mysqli->query( 'SHOW CREATE TABLE `' . str_replace( '`', '``', $table ) . '`' );
	if ( false === $res ) {
		return '';
	}
	$row = $res->fetch_assoc();
	$res->free();
	return isset( $row['Create Table'] ) ? (string) $row['Create Table'] : '';
}
