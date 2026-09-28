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

	// WordPress wpdb ile aynı: PHP 8.1+ varsayılanı (ERROR|STRICT) exception fırlatır;
	// production 1062 idempotency yolu false + errno bekler.
	mysqli_report( MYSQLI_REPORT_OFF );

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
	$mysqli->query( 'TRUNCATE TABLE wp_qmo_chatbot_oneri_log' );
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
 * Production attribution engine (gerçek SQL).
 *
 * @param string $bas_ymd Y-m-d.
 * @param string $bit_ymd Y-m-d.
 * @return array<string,mixed>
 */
function qrms_mariadb_attribution( $bas_ymd, $bit_ymd ) {
	$bas = sanitize_text_field( $bas_ymd ) . ' 00:00:00';
	$bit = sanitize_text_field( $bit_ymd ) . ' 23:59:59';
	return QMO_Chatbot_DB::recommendation_attribution_hesapla( $bas, $bit );
}

/**
 * @param array<string,mixed> $attr Engine çıktısı.
 * @param int                 $product_id Ürün.
 * @return array<string,mixed>
 */
function qrms_mariadb_attribution_urun( array $attr, $product_id ) {
	$urunler = isset( $attr['urunler'] ) && is_array( $attr['urunler'] ) ? $attr['urunler'] : array();
	$pid     = (int) $product_id;
	if ( $pid < 1 || ! isset( $urunler[ $pid ] ) || ! is_array( $urunler[ $pid ] ) ) {
		return array(
			'atfedilen_siparis_tekil' => 0,
			'atfedilen_kalem'         => 0,
			'atfedilen_birim'         => 0,
			'atfedilen_tutar'         => 0.0,
		);
	}
	return $urunler[ $pid ];
}

/**
 * Son çalıştırılan attribution SQL (EXPLAIN için).
 *
 * @return string
 */
function qrms_mariadb_last_attribution_sql() {
	global $wpdb;
	if ( ! isset( $wpdb->queries ) || ! is_array( $wpdb->queries ) ) {
		return '';
	}
	foreach ( array_reverse( $wpdb->queries ) as $sql ) {
		if ( false !== strpos( (string) $sql, 'line_attrib' ) ) {
			return (string) $sql;
		}
	}
	return '';
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

/**
 * information_schema üzerinden indeks var mı?
 *
 * @param mysqli $mysqli     Bağlantı.
 * @param string $table      Tablo adı (prefix dahil).
 * @param string $index_name Indeks adı.
 * @return bool
 */
function qrms_mariadb_index_exists( mysqli $mysqli, $table, $index_name ) {
	$table      = preg_replace( '/[^a-z0-9_]/i', '', (string) $table );
	$index_name = preg_replace( '/[^a-z0-9_]/i', '', (string) $index_name );
	if ( '' === $table || '' === $index_name ) {
		return false;
	}
	$sql = "SELECT 1 FROM information_schema.STATISTICS
		WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1";
	$stmt = $mysqli->prepare( $sql );
	if ( ! $stmt ) {
		return false;
	}
	$stmt->bind_param( 'ss', $table, $index_name );
	$stmt->execute();
	$res = $stmt->get_result();
	$ok  = $res && null !== $res->fetch_row();
	if ( $res ) {
		$res->free();
	}
	$stmt->close();
	return $ok;
}

/**
 * Indeks sütunlarını SEQ_IN_INDEX sırasıyla döndürür.
 *
 * @param mysqli $mysqli     Bağlantı.
 * @param string $table      Tablo.
 * @param string $index_name Indeks.
 * @return array<int, string>
 */
function qrms_mariadb_index_columns( mysqli $mysqli, $table, $index_name ) {
	$table      = preg_replace( '/[^a-z0-9_]/i', '', (string) $table );
	$index_name = preg_replace( '/[^a-z0-9_]/i', '', (string) $index_name );
	$cols       = array();
	if ( '' === $table || '' === $index_name ) {
		return $cols;
	}
	$sql = "SELECT COLUMN_NAME FROM information_schema.STATISTICS
		WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
		ORDER BY SEQ_IN_INDEX ASC";
	$stmt = $mysqli->prepare( $sql );
	if ( ! $stmt ) {
		return $cols;
	}
	$stmt->bind_param( 'ss', $table, $index_name );
	$stmt->execute();
	$res = $stmt->get_result();
	if ( $res ) {
		while ( $row = $res->fetch_assoc() ) {
			$cols[] = (string) $row['COLUMN_NAME'];
		}
		$res->free();
	}
	$stmt->close();
	return $cols;
}

/**
 * Production öncesi durum: uq_order_event_item (order_id, event_type, item_id).
 * Mevcut uq_order_event_line kaldırılır (varsa).
 *
 * @param mysqli $mysqli Bağlantı.
 * @return void
 * @throws Exception ALTER hatası.
 */
function qrms_mariadb_analytics_legacy_unique_state( mysqli $mysqli ) {
	$table = 'wp_rma_analytics';
	if ( qrms_mariadb_index_exists( $mysqli, $table, QRMS_Analitik::UQ_ORDER_EVENT_LINE ) ) {
		if ( ! $mysqli->query( 'ALTER TABLE `' . $table . '` DROP INDEX `' . QRMS_Analitik::UQ_ORDER_EVENT_LINE . '`' ) ) {
			throw new Exception( 'legacy sim: drop line index: ' . $mysqli->error );
		}
	}
	if ( ! qrms_mariadb_index_exists( $mysqli, $table, QRMS_Analitik::UQ_ORDER_EVENT_ITEM ) ) {
		if ( ! $mysqli->query(
			'ALTER TABLE `' . $table . '` ADD UNIQUE KEY `' . QRMS_Analitik::UQ_ORDER_EVENT_ITEM . '` (order_id, event_type, item_id)'
		) ) {
			throw new Exception( 'legacy sim: add item index: ' . $mysqli->error );
		}
	}
}
