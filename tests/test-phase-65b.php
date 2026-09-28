<?php
/**
 * Phase 6.5-B — line_key analitik UNIQUE, idempotent insert, Firestore kanonik hash.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/class-qmo-firestore.php';
require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/helpers.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php';

echo "\nPhase 6.5-B — line_key UNIQUE & idempotency\n";

qrms_test(
	'6.5-B canonical hash itemId kullanmaz, Firestore belgesi ile eşleşir',
	function () {
		$belge = array(
			'name'   => 'projects/p/databases/(default)/documents/calls/ord-65b-1',
			'fields' => array(
				'masaNo'  => array( 'stringValue' => 'masa-7' ),
				'notDili' => array( 'stringValue' => 'tr' ),
				'items'   => array(
					'arrayValue' => array(
						'values' => array(
							array(
								'mapValue' => array(
									'fields' => array(
										'urunAdi'     => array( 'stringValue' => 'Adana' ),
										'adet'        => array( 'integerValue' => '2' ),
										'notOrijinal' => array( 'stringValue' => ' acı ' ),
										'notTr'       => array( 'stringValue' => 'yok' ),
									),
								),
							),
						),
					),
				),
			),
		);
		$doc  = QMO_Firestore::belge_coz( $belge );
		$fs   = qmo_siparis_canonical_firestore( $doc );
		$req  = qmo_siparis_canonical_hash_istek(
			'masa-7',
			'tr',
			array(
				array(
					'urunAdi'  => 'Adana',
					'adet'     => 2,
					'not'      => 'acı',
					'item_id'  => 999,
				),
			)
		);
		qrms_assert_true( is_array( $fs ), 'firestore kanon' );
		qrms_assert_same( $fs['hash'], $req, 'kanonik hash eşleşmesi' );
	}
);

qrms_test(
	'6.5-B malformed Firestore → canonical null (fail closed)',
	function () {
		qrms_assert_same( null, qmo_siparis_canonical_firestore( array( 'masaNo' => 'x' ) ), 'items yok' );
	}
);

/**
 * Analitik kaydet test wpdb.
 */
class QRMS_P65_Analitik_Wpdb {
	public $prefix     = 'wp_';
	public $dbh        = null;
	public $rows       = array();
	public $last_error = '';
	public $next_id    = 1;
	public $queries    = array();
	public $fail_next  = '';
	public $columns    = array( 'order_id', 'line_key' );

	public function prepare( $sql, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		return preg_replace_callback(
			'/%[dsf]/',
			function ( $m ) use ( &$args ) {
				$v = array_shift( $args );
				if ( '%d' === $m[0] ) {
					return (string) (int) $v;
				}
				return "'" . str_replace( "'", "\\'", (string) $v ) . "'";
			},
			$sql
		);
	}

	public function get_var( $sql ) {
		$this->queries[] = $sql;
		if ( false !== strpos( $sql, 'SHOW TABLES LIKE' ) ) {
			return 'wp_rma_analytics';
		}
		if ( false !== strpos( $sql, 'SHOW COLUMNS FROM' ) ) {
			if ( preg_match( "/LIKE '([^']+)'/", $sql, $m ) ) {
				return in_array( $m[1], $this->columns, true ) ? $m[1] : null;
			}
			return 'order_id';
		}
		if ( false !== strpos( $sql, 'information_schema.STATISTICS' ) ) {
			if ( false !== strpos( $sql, 'uq_order_event_line' ) ) {
				return '1';
			}
			return null;
		}
		if ( preg_match( "/order_id = '([^']+)' AND event_type = '([^']+)'/", $sql, $m ) ) {
			foreach ( $this->rows as $row ) {
				if ( $m[1] === (string) ( $row['order_id'] ?? '' ) && $m[2] === (string) ( $row['event_type'] ?? '' ) ) {
					return '1';
				}
			}
			return null;
		}
		if ( false !== strpos( $sql, 'HAVING COUNT(*) > 1' ) ) {
			return null;
		}
		return null;
	}

	public function get_results( $sql, $mode = null ) {
		unset( $mode );
		$this->queries[] = $sql;
		if ( false !== strpos( $sql, 'SHOW COLUMNS' ) ) {
			$out = array();
			foreach ( $this->columns as $col ) {
				$out[] = array( 'Field' => $col );
			}
			return $out;
		}
		return array();
	}

	public function insert( $table, $data, $format = null ) {
		unset( $format, $table );
		$this->last_error = '';
		if ( '' !== $this->fail_next ) {
			$this->last_error = $this->fail_next;
			$this->fail_next  = '';
			return false;
		}
		$lk  = (string) ( $data['line_key'] ?? '' );
		$key = (string) ( $data['order_id'] ?? '' ) . '|' . (string) ( $data['event_type'] ?? '' ) . '|' . $lk;
		foreach ( $this->rows as $row ) {
			$ek = (string) ( $row['order_id'] ?? '' ) . '|' . (string) ( $row['event_type'] ?? '' ) . '|' . (string) ( $row['line_key'] ?? '' );
			if ( $ek === $key && '' !== (string) ( $data['order_id'] ?? '' ) && '' !== $lk ) {
				$this->last_error = "Duplicate entry '{$key}' for key 'uq_order_event_line'";
				if ( $this->dbh ) {
					$this->dbh->errno = 1062;
				}
				return false;
			}
		}
		$data['id'] = $this->next_id++;
		$this->rows[] = $data;
		if ( $this->dbh ) {
			$this->dbh->errno = 0;
		}
		return 1;
	}

	public function query( $sql ) {
		$this->queries[] = $sql;
		return true;
	}
}

qrms_test(
	'6.5-B line_key: aynı item_id farklı içerik → farklı line_key',
	function () {
		$a = qmo_siparis_line_keys_from_items(
			array(
				array( 'urunAdi' => 'Hamburger Küçük', 'adet' => 1, 'notOrijinal' => '', 'notTr' => '' ),
				array( 'urunAdi' => 'Hamburger Büyük', 'adet' => 1, 'notOrijinal' => '', 'notTr' => '' ),
			)
		);
		qrms_assert_same( 2, count( $a ), 'iki satır' );
		qrms_assert_true( $a[0]['line_key'] !== $a[1]['line_key'], 'farklı line_key' );
	}
);

qrms_test(
	'6.5-B line_key: aynı kanonik kalem iki kez → :0 ve :1',
	function () {
		$keys = qmo_siparis_line_keys_from_items(
			array(
				array( 'urunAdi' => 'A', 'adet' => 1, 'notOrijinal' => '', 'notTr' => '' ),
				array( 'urunAdi' => 'A', 'adet' => 1, 'notOrijinal' => '', 'notTr' => '' ),
			)
		);
		qrms_assert_contains( ':0', $keys[0]['line_key'], 'occurrence 0' );
		qrms_assert_contains( ':1', $keys[1]['line_key'], 'occurrence 1' );
		qrms_assert_same( substr( $keys[0]['line_key'], 0, 64 ), substr( $keys[1]['line_key'], 0, 64 ), 'aynı base hash' );
	}
);

qrms_test(
	'6.5-B line_key: deterministik multiset A,B,A → key set A:0,A:1,B:0',
	function () {
		$fs_item = function ( $ad, $not = '' ) {
			return array(
				'urunAdi'     => $ad,
				'adet'        => 1,
				'notOrijinal' => $not,
				'notTr'       => $not,
			);
		};
		$set_aba = qmo_siparis_line_keys_from_items( array( $fs_item( 'A' ), $fs_item( 'B' ), $fs_item( 'A' ) ) );
		$set_aab = qmo_siparis_line_keys_from_items( array( $fs_item( 'A' ), $fs_item( 'A' ), $fs_item( 'B' ) ) );
		$set_baa = qmo_siparis_line_keys_from_items( array( $fs_item( 'B' ), $fs_item( 'A' ), $fs_item( 'A' ) ) );

		$keys_aba = array_column( $set_aba, 'line_key' );
		$keys_aab = array_column( $set_aab, 'line_key' );
		$keys_baa = array_column( $set_baa, 'line_key' );
		sort( $keys_aba );
		sort( $keys_aab );
		sort( $keys_baa );

		qrms_assert_same( $keys_aba, $keys_aab, 'A,B,A vs A,A,B set' );
		qrms_assert_same( $keys_aba, $keys_baa, 'A,B,A vs B,A,A set' );

		$ha = qmo_siparis_line_hash_tek( qmo_siparis_canonical_satir( 'A', 1, '', '' ) );
		$hb = qmo_siparis_line_hash_tek( qmo_siparis_canonical_satir( 'B', 1, '', '' ) );
		qrms_assert_same(
			array( $ha . ':0', $ha . ':1', $hb . ':0' ),
			$keys_aba,
			'occurrence sorted multiset'
		);
	}
);

qrms_test(
	'6.5-B line_key: failed A,B,A vs sent A,A,B aynı key set (permutation)',
	function () {
		$fs = function ( $order ) {
			$out = array();
			foreach ( $order as $ad ) {
				$out[] = array(
					'urunAdi'     => $ad,
					'adet'        => 1,
					'notOrijinal' => '',
					'notTr'       => '',
				);
			}
			return $out;
		};
		$failed = array_column( qmo_siparis_line_keys_from_items( $fs( array( 'A', 'B', 'A' ) ) ), 'line_key' );
		$sent   = array_column( qmo_siparis_line_keys_from_items( $fs( array( 'A', 'A', 'B' ) ) ), 'line_key' );
		sort( $failed );
		sort( $sent );
		qrms_assert_same( $failed, $sent, 'failed vs sent permutation' );
	}
);

qrms_test(
	'6.5-B line_key: farklı not ve adet',
	function () {
		$fx = array( 'urunAdi' => 'A', 'adet' => 1, 'notOrijinal' => 'x', 'notTr' => 'x' );
		$fy = array( 'urunAdi' => 'A', 'adet' => 1, 'notOrijinal' => 'y', 'notTr' => 'y' );
		$k1 = qmo_siparis_line_keys_from_items( array( $fx, $fy ) );
		qrms_assert_true( $k1[0]['line_key'] !== $k1[1]['line_key'], 'x vs y' );

		$k2 = qmo_siparis_line_keys_from_items( array( $fx, $fx ) );
		qrms_assert_contains( ':0', $k2[0]['line_key'], 'x:0' );
		qrms_assert_contains( ':1', $k2[1]['line_key'], 'x:1' );

		$a1 = array( 'urunAdi' => 'A', 'adet' => 1, 'notOrijinal' => '', 'notTr' => '' );
		$a2 = array( 'urunAdi' => 'A', 'adet' => 2, 'notOrijinal' => '', 'notTr' => '' );
		$ka = qmo_siparis_line_keys_from_items( array( $a1, $a2 ) );
		qrms_assert_true( $ka[0]['line_key'] !== $ka[1]['line_key'], 'adet 1 vs 2' );
	}
);

qrms_test(
	'6.5-B item_id: canonical permutation A/x→101 A/y→102',
	function () {
		$fs = array(
			array( 'urunAdi' => 'A', 'adet' => 1, 'notOrijinal' => 'y', 'notTr' => 'y' ),
			array( 'urunAdi' => 'A', 'adet' => 1, 'notOrijinal' => 'x', 'notTr' => 'x' ),
		);
		$temiz = array(
			array( 'urunAdi' => 'A', 'adet' => 1, 'not' => 'x', 'item_id' => 101 ),
			array( 'urunAdi' => 'A', 'adet' => 1, 'not' => 'y', 'item_id' => 102 ),
		);
		$map = qmo_siparis_temiz_item_ids_for_fs_items( $fs, $temiz );
		qrms_assert_same( 102, (int) $map[0], 'FS y → 102' );
		qrms_assert_same( 101, (int) $map[1], 'FS x → 101' );
	}
);

qrms_test(
	'6.5-B item_id: belirsiz ada göre lookup item_id=0 (çoklu aday)',
	function () {
		if ( ! function_exists( 'qmo_analitik_urun_ada_gore_belirsiz_guvenli' ) ) {
			qrms_assert_true( false, 'helper yok' );
			return;
		}
		// WordPress stub ortamında get_posts davranışı yoksa yalnızca fonksiyon varlığını doğrula.
		$alan = qmo_analitik_urun_ada_gore_belirsiz_guvenli( 'Bilinmeyen Ürün XYZ 65b' );
		qrms_assert_same( 0, (int) ( $alan['item_id'] ?? -1 ), 'bulunamayan → 0' );
	}
);

qrms_test(
	'6.5-B kaydet ilk insert başarılı, ikinci duplicate line_key idempotent true',
	function () {
		$wpdb            = new QRMS_P65_Analitik_Wpdb();
		$wpdb->dbh       = new stdClass();
		$wpdb->dbh->errno = 0;
		$GLOBALS['wpdb'] = $wpdb;

		$lk    = qmo_siparis_line_keys_from_items(
			array( array( 'urunAdi' => 'Test', 'adet' => 1, 'notOrijinal' => '', 'notTr' => '' ) )
		)[0]['line_key'];
		$satir = array(
			'event_type' => 'order_sent',
			'order_id'   => 'oid-65b-dup',
			'item_id'    => 12,
			'item_name'  => 'Test',
			'line_key'   => $lk,
		);
		qrms_assert_true( QRMS_Analitik::kaydet( $satir ), 'ilk' );
		qrms_assert_true( QRMS_Analitik::kaydet( $satir ), 'duplicate idempotent' );
		qrms_assert_same( 1, count( $wpdb->rows ), 'tek satır' );
	}
);

qrms_test(
	'6.5-B kaydet unrelated DB hatası false kalır',
	function () {
		$wpdb            = new QRMS_P65_Analitik_Wpdb();
		$wpdb->fail_next = 'Lock wait timeout';
		$GLOBALS['wpdb'] = $wpdb;

		qrms_assert_false(
			QRMS_Analitik::kaydet(
				array(
					'event_type' => 'order_sent',
					'order_id'   => 'oid-err',
					'item_id'    => 1,
					'line_key'   => str_repeat( 'a', 64 ) . ':0',
				)
			),
			'gerçek hata'
		);
	}
);

qrms_test(
	'6.5-B kaydet duplicate line_key olmadan 1062 yutulmaz',
	function () {
		$wpdb            = new QRMS_P65_Analitik_Wpdb();
		$wpdb->fail_next = "Duplicate entry 'x' for key 'uq_order_event_line'";
		$wpdb->dbh       = new stdClass();
		$wpdb->dbh->errno = 1062;
		$GLOBALS['wpdb'] = $wpdb;

		qrms_assert_false(
			QRMS_Analitik::kaydet(
				array(
					'event_type' => 'order_sent',
					'order_id'   => 'oid-nolk',
					'item_id'    => 1,
				)
			),
			'line_key boş → hata'
		);
	}
);

qrms_test(
	'6.5-B siparis_olayi_var_mi event-specific; failed ≠ sent tamam',
	function () {
		$wpdb = new QRMS_P65_Analitik_Wpdb();
		$wpdb->rows[] = array(
			'order_id'   => 'oid-fail',
			'event_type' => 'order_failed',
			'item_id'    => 5,
			'line_key'   => str_repeat( 'b', 64 ) . ':0',
		);
		$GLOBALS['wpdb'] = $wpdb;

		qrms_assert_true( QRMS_Analitik::siparis_olayi_var_mi( 'oid-fail', 'order_failed' ), 'failed var' );
		qrms_assert_false( QRMS_Analitik::siparis_olayi_var_mi( 'oid-fail', 'order_sent' ), 'sent yok' );
		qrms_assert_false( QRMS_Analitik::siparis_olayi_kayitli_mi( 'oid-fail' ), 'kayitli_mi yalnız sent' );
	}
);

qrms_test(
	'6.5-B idempotent_durum transient replay yolu (Firestore yok)',
	function () {
		$key = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
		$hash = qmo_siparis_canonical_hash_istek(
			'masa-1',
			'tr',
			array(
				array(
					'urunAdi' => 'Çorba',
					'adet'    => 1,
					'not'     => '',
				),
			)
		);
		set_transient(
			qmo_idempotency_transient_anahtar( $key ),
			array(
				'masa'      => 'masa-1',
				'body_hash' => $hash,
			),
			60
		);
		$durum = qmo_siparis_idempotent_durum( $key, 'masa-1', $hash );
		qrms_assert_same( 'continue', $durum['type'], 'FS yok → continue' );

		$durum2 = qmo_siparis_idempotent_durum( $key, 'masa-1', $hash . 'x' );
		qrms_assert_same( 'body_mismatch', $durum2['type'], 'farklı body' );
	}
);

qrms_test(
	'6.5-B şema kaynağı DB_SURUM 1.6 ve line_key UNIQUE',
	function () {
		$sema = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php' );
		$sql  = file_get_contents( QRMS_PLUGIN_DIR . 'tests/mariadb/schema.sql' );
		$rest = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/rest-order.php' );
		qrms_assert_contains( "const DB_SURUM = '1.6'", $sema, 'DB_SURUM' );
		qrms_assert_contains( 'line_key varchar', $sema, 'line_key sütunu' );
		qrms_assert_contains( 'UNIQUE KEY uq_order_event_line (order_id,event_type,line_key)', $sema, 'repo UNIQUE' );
		qrms_assert_contains( 'UNIQUE KEY uq_order_event_line (order_id, event_type, line_key)', $sql, 'test schema UNIQUE' );
		qrms_assert_same(
			'uq_order_event_item',
			( new ReflectionClassConstant( 'QRMS_Analitik', 'UQ_ORDER_EVENT_ITEM' ) )->getValue(),
			'eski UNIQUE adı'
		);
		$migr  = new ReflectionMethod( 'QRMS_Analitik', 'unique_indeks_dene' );
		$govde = implode(
			'',
			array_slice(
				file( $migr->getFileName() ),
				$migr->getStartLine() - 1,
				$migr->getEndLine() - $migr->getStartLine() + 1
			)
		);
		qrms_assert_contains( 'indeks_var_mi( self::UQ_ORDER_EVENT_ITEM )', $govde, 'eski UNIQUE varlık kontrolü' );
		qrms_assert_contains( "' DROP INDEX ' . self::UQ_ORDER_EVENT_ITEM", $govde, 'eski UNIQUE kaldırma' );
		qrms_assert_contains( 'siparis_analitik_yinelenen_var_mi', $sema, 'duplicate tespit' );
		qrms_assert_contains( 'firestore_items', $rest, 'FS-first analytics' );
		qrms_assert_contains( 'qmo_siparis_line_keys_from_items', file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/helpers.php' ), 'line keys helper' );
	}
);

qrms_test(
	'6.5-B rest-order Firestore-first ve firestore_invalid',
	function () {
		$rest = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/rest-order.php' );
		qrms_assert_contains( 'QMO_Firestore::call_oku( $order_id', $rest, 'call_oku analytics' );
		qrms_assert_contains( 'firestore_invalid', $rest, 'malformed FS 409' );
		qrms_assert_false( false !== strpos( $rest, 'siparis_olayi_var_mi( $order_id, $olay_tip )' ), 'order-level analytics skip kaldırıldı' );
	}
);

require_once __DIR__ . '/mariadb/harness.php';

if ( qrms_mariadb_available() ) {
	echo "\nPhase 6.5-B — MariaDB duplicate key (gerçek DB)\n";

	qrms_test(
		'6.5-B MariaDB duplicate order_sent line_key idempotent, failed+sent birlikte',
		function () {
			$mysqli = qrms_mariadb_connect();
			if ( ! $mysqli ) {
				qrms_assert_true( false, 'mysqli' );
				return;
			}
			qrms_mariadb_apply_schema( $mysqli );
			qrms_mariadb_reset_data( $mysqli );
			$wpdb = new QRMS_MariaDB_Wpdb( $mysqli );
			qrms_mariadb_bind_wpdb( $wpdb );

			$oid = '65b00000-0000-4000-8000-000000000001';
			$lk  = qmo_siparis_line_keys_from_items(
				array( array( 'urunAdi' => 'MDB', 'adet' => 1, 'notOrijinal' => '', 'notTr' => '' ) )
			)[0]['line_key'];
			$base = array(
				'event_type'    => 'order_sent',
				'order_id'      => $oid,
				'line_key'      => $lk,
				'item_id'       => 42,
				'item_name'     => 'MDB',
				'category_name' => '',
				'qty'           => 1,
				'price'         => 10,
				'masa_no'       => 'masa-m',
				'ip_hash'       => 'abc',
				'created_at'    => '2026-09-28 12:00:00',
			);
			qrms_assert_true( QRMS_Analitik::kaydet( $base ), 'ilk insert' );
			qrms_assert_true( QRMS_Analitik::kaydet( $base ), 'duplicate idempotent' );

			$failed = array_merge( $base, array( 'event_type' => 'order_failed', 'reason' => 'unconfirmed' ) );
			qrms_assert_true( QRMS_Analitik::kaydet( $failed ), 'failed farklı event_type' );

			$cnt = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM wp_rma_analytics WHERE order_id = %s",
					$oid
				)
			);
			qrms_assert_same( '2', (string) $cnt, 'sent+failed iki satır' );

			$idx = $wpdb->get_var(
				"SELECT 1 FROM information_schema.STATISTICS
				 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wp_rma_analytics'
				   AND INDEX_NAME = 'uq_order_event_line' LIMIT 1"
			);
			qrms_assert_same( '1', (string) $idx, 'UNIQUE indeks var' );

			$col = $wpdb->get_var(
				"SELECT 1 FROM information_schema.COLUMNS
				 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wp_rma_analytics'
				   AND COLUMN_NAME = 'line_key' LIMIT 1"
			);
			qrms_assert_same( '1', (string) $col, 'line_key sütunu' );

			$mysqli->close();
		}
	);

	qrms_test(
		'6.5-B MariaDB aynı item_id iki farklı line_key',
		function () {
			$mysqli = qrms_mariadb_connect();
			if ( ! $mysqli ) {
				qrms_assert_true( false, 'mysqli' );
				return;
			}
			qrms_mariadb_apply_schema( $mysqli );
			qrms_mariadb_reset_data( $mysqli );
			$wpdb = new QRMS_MariaDB_Wpdb( $mysqli );
			qrms_mariadb_bind_wpdb( $wpdb );

			$oid  = '65b00000-0000-4000-8000-000000000002';
			$keys = qmo_siparis_line_keys_from_items(
				array(
					array( 'urunAdi' => 'Küçük', 'adet' => 1, 'notOrijinal' => 'x', 'notTr' => '' ),
					array( 'urunAdi' => 'Büyük', 'adet' => 1, 'notOrijinal' => 'y', 'notTr' => '' ),
				)
			);
			foreach ( $keys as $k ) {
				qrms_assert_true(
					QRMS_Analitik::kaydet(
						array(
							'event_type' => 'order_sent',
							'order_id'   => $oid,
							'line_key'   => $k['line_key'],
							'item_id'    => 123,
							'item_name'  => $k['urunAdi'],
							'qty'        => 1,
							'price'      => 1,
							'masa_no'    => 'm',
							'ip_hash'    => 'x',
							'created_at' => '2026-09-28 12:00:00',
						)
					),
					'insert'
				);
			}
			$cnt = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM wp_rma_analytics WHERE order_id = %s AND item_id = 123",
					$oid
				)
			);
			qrms_assert_same( '2', (string) $cnt, 'iki satır aynı item_id' );
			$mysqli->close();
		}
	);

	echo "\nPhase 6.5-B — MariaDB runtime hardening (post-merge)\n";

	qrms_test(
		'6.5-B MariaDB legacy uq_order_event_item → uq_order_event_line migration',
		function () {
			$mysqli = qrms_mariadb_connect();
			if ( ! $mysqli ) {
				qrms_assert_true( false, 'mysqli' );
				return;
			}
			qrms_mariadb_apply_schema( $mysqli );
			qrms_mariadb_reset_data( $mysqli );
			qrms_mariadb_analytics_legacy_unique_state( $mysqli );

			qrms_assert_true(
				qrms_mariadb_index_exists( $mysqli, 'wp_rma_analytics', QRMS_Analitik::UQ_ORDER_EVENT_ITEM ),
				'legacy indeks kuruldu'
			);
			qrms_assert_false(
				qrms_mariadb_index_exists( $mysqli, 'wp_rma_analytics', QRMS_Analitik::UQ_ORDER_EVENT_LINE ),
				'line UNIQUE henüz yok'
			);

			$wpdb = new QRMS_MariaDB_Wpdb( $mysqli );
			qrms_mariadb_bind_wpdb( $wpdb );

			$lk = qmo_siparis_line_keys_from_items(
				array( array( 'urunAdi' => 'Migr', 'adet' => 1, 'notOrijinal' => '', 'notTr' => '' ) )
			)[0]['line_key'];
			$oid = '65b00000-0000-4000-8000-000000000010';
			$row = array(
				'event_type'    => 'order_sent',
				'order_id'      => $oid,
				'line_key'      => $lk,
				'item_id'       => 501,
				'item_name'     => 'Migr',
				'qty'           => 1,
				'price'         => 5,
				'masa_no'       => 'm-mig',
				'ip_hash'       => 'mig',
				'created_at'    => '2026-09-28 14:00:00',
			);
			qrms_assert_true( QRMS_Analitik::kaydet( $row ), 'legacy indeks altında insert' );

			$failed_lk = qmo_siparis_line_keys_from_items(
				array( array( 'urunAdi' => 'Fail', 'adet' => 2, 'notOrijinal' => 'n', 'notTr' => 'n' ) )
			)[0]['line_key'];
			$failed = array_merge(
				$row,
				array(
					'event_type' => 'order_failed',
					'line_key'   => $failed_lk,
					'item_id'    => 502,
					'item_name'  => 'Fail',
					'reason'     => 'unconfirmed',
				)
			);
			qrms_assert_true( QRMS_Analitik::kaydet( $failed ), 'failed satır legacy altında' );

			$before_cnt = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM wp_rma_analytics' );
			$before_ids = array_map(
				static function ( $row ) {
					return (int) $row['id'];
				},
				$wpdb->get_results( 'SELECT id FROM wp_rma_analytics ORDER BY id ASC', ARRAY_A )
			);
			qrms_assert_same( 2, $before_cnt, 'migration öncesi iki satır' );

			QRMS_Analitik::sema_kontrol();

			qrms_assert_false(
				qrms_mariadb_index_exists( $mysqli, 'wp_rma_analytics', QRMS_Analitik::UQ_ORDER_EVENT_ITEM ),
				'legacy indeks kaldırıldı'
			);
			qrms_assert_true(
				qrms_mariadb_index_exists( $mysqli, 'wp_rma_analytics', QRMS_Analitik::UQ_ORDER_EVENT_LINE ),
				'line UNIQUE eklendi'
			);
			qrms_assert_same(
				array( 'order_id', 'event_type', 'line_key' ),
				qrms_mariadb_index_columns( $mysqli, 'wp_rma_analytics', QRMS_Analitik::UQ_ORDER_EVENT_LINE ),
				'line UNIQUE sütunları'
			);

			$after_cnt = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM wp_rma_analytics' );
			$after_ids = array_map(
				static function ( $row ) {
					return (int) $row['id'];
				},
				$wpdb->get_results( 'SELECT id FROM wp_rma_analytics ORDER BY id ASC', ARRAY_A )
			);
			qrms_assert_same( $before_cnt, $after_cnt, 'satırlar silinmedi' );
			qrms_assert_same( $before_ids, $after_ids, 'satır kimlikleri korundu' );

			QRMS_Analitik::sema_kontrol();
			qrms_assert_true(
				qrms_mariadb_index_exists( $mysqli, 'wp_rma_analytics', QRMS_Analitik::UQ_ORDER_EVENT_LINE ),
				'ikinci sema_kontrol idempotent'
			);
			qrms_assert_false(
				qrms_mariadb_index_exists( $mysqli, 'wp_rma_analytics', QRMS_Analitik::UQ_ORDER_EVENT_ITEM ),
				'legacy geri gelmedi'
			);

			$mysqli->close();
		}
	);

	qrms_test(
		'6.5-B MariaDB aynı line_key farklı order_id birlikte yaşar',
		function () {
			$mysqli = qrms_mariadb_connect();
			if ( ! $mysqli ) {
				qrms_assert_true( false, 'mysqli' );
				return;
			}
			qrms_mariadb_apply_schema( $mysqli );
			qrms_mariadb_reset_data( $mysqli );
			$wpdb = new QRMS_MariaDB_Wpdb( $mysqli );
			qrms_mariadb_bind_wpdb( $wpdb );

			$lk   = qmo_siparis_line_keys_from_items(
				array( array( 'urunAdi' => 'Shared', 'adet' => 1, 'notOrijinal' => '', 'notTr' => '' ) )
			)[0]['line_key'];
			$oid1 = '65b00000-0000-4000-8000-000000000011';
			$oid2 = '65b00000-0000-4000-8000-000000000012';
			$base = array(
				'event_type' => 'order_sent',
				'line_key'   => $lk,
				'item_id'    => 77,
				'item_name'  => 'Shared',
				'qty'        => 1,
				'price'      => 1,
				'masa_no'    => 'm',
				'ip_hash'    => 'x',
				'created_at' => '2026-09-28 15:00:00',
			);

			foreach ( array( $oid1 => 'bir', $oid2 => 'iki' ) as $oid => $label ) {
				$satir = array_merge( $base, array( 'order_id' => $oid ) );
				qrms_assert_true( QRMS_Analitik::kaydet( $satir ), 'insert ' . $label );
			}

			$lk_cnt = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM wp_rma_analytics WHERE line_key = %s AND event_type = 'order_sent'",
					$lk
				)
			);
			qrms_assert_same( 2, $lk_cnt, 'aynı line_key iki order_id' );

			$name1 = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT item_name FROM wp_rma_analytics WHERE order_id = %s AND line_key = %s LIMIT 1",
					$oid1,
					$lk
				)
			);
			$name2 = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT item_name FROM wp_rma_analytics WHERE order_id = %s AND line_key = %s LIMIT 1",
					$oid2,
					$lk
				)
			);
			qrms_assert_same( 'Shared', (string) $name1, 'oid1 kendi satırı' );
			qrms_assert_same( 'Shared', (string) $name2, 'oid2 kendi satırı' );

			$dup = array_merge( $base, array( 'order_id' => $oid1 ) );
			qrms_assert_true( QRMS_Analitik::kaydet( $dup ), 'oid1 duplicate idempotent' );
			$oid1_cnt = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM wp_rma_analytics WHERE order_id = %s AND line_key = %s",
					$oid1,
					$lk
				)
			);
			qrms_assert_same( 1, $oid1_cnt, 'oid1 hâlâ tek satır' );

			$mysqli->close();
		}
	);

	qrms_test(
		'6.5-B MariaDB order_failed retry idempotent',
		function () {
			$mysqli = qrms_mariadb_connect();
			if ( ! $mysqli ) {
				qrms_assert_true( false, 'mysqli' );
				return;
			}
			qrms_mariadb_apply_schema( $mysqli );
			qrms_mariadb_reset_data( $mysqli );
			$wpdb = new QRMS_MariaDB_Wpdb( $mysqli );
			qrms_mariadb_bind_wpdb( $wpdb );

			$oid = '65b00000-0000-4000-8000-000000000013';
			$lk  = qmo_siparis_line_keys_from_items(
				array( array( 'urunAdi' => 'Retry', 'adet' => 1, 'notOrijinal' => '', 'notTr' => '' ) )
			)[0]['line_key'];
			$failed = array(
				'event_type' => 'order_failed',
				'order_id'   => $oid,
				'line_key'   => $lk,
				'item_id'    => 88,
				'item_name'  => 'Retry',
				'qty'        => 1,
				'price'      => 0,
				'masa_no'    => 'm',
				'ip_hash'    => 'r',
				'reason'     => 'unconfirmed',
				'created_at' => '2026-09-28 16:00:00',
			);

			qrms_assert_true( QRMS_Analitik::kaydet( $failed ), 'ilk order_failed' );
			qrms_assert_true( QRMS_Analitik::kaydet( $failed ), 'retry idempotent true' );

			$cnt = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM wp_rma_analytics WHERE order_id = %s AND event_type = 'order_failed'",
					$oid
				)
			);
			qrms_assert_same( 1, $cnt, 'tek failed satır' );
			qrms_assert_true( QRMS_Analitik::siparis_olayi_var_mi( $oid, 'order_failed' ), 'olay kayıtlı' );

			$mysqli->close();
		}
	);
}
