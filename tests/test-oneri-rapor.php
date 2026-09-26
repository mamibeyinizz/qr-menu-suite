<?php
/**
 * Öneri Raporu — sepete metriği recommendation_events kaynağı (Issue #269).
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/class-db.php';

echo "\nÖneri Raporu recommendation cart_add (#269)\n";

/**
 * oneri_rapor() için bellek içi wpdb.
 */
class QRMS_Oneri_Rapor_Wpdb {
	public $prefix     = 'wp_';
	public $oneri_log  = array();
	public $rec_events = array();
	public $queries    = array();

	public function prepare( $sql, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		return preg_replace_callback(
			'/%[dsf]/',
			function ( $m ) use ( &$args ) {
				$value = array_shift( $args );
				if ( '%d' === $m[0] ) {
					return (string) (int) $value;
				}
				return "'" . str_replace( "'", "\\'", (string) $value ) . "'";
			},
			$sql
		);
	}

	public function suppress_errors( $suppress = true ) {
		return false;
	}

	/**
	 * @param string $sql Sorgu.
	 * @return array<int, object>
	 */
	private function tarih_araligi( $sql ) {
		if ( ! preg_match( "/created_at >= '([^']+)'/", $sql, $bas ) ) {
			return array( '', '' );
		}
		if ( ! preg_match( "/created_at <= '([^']+)'/", $sql, $bit ) ) {
			return array( '', '' );
		}
		return array( $bas[1], $bit[1] );
	}

	public function get_results( $sql, $mode = null ) {
		unset( $mode );
		$this->queries[] = $sql;

		list( $bas, $bit ) = $this->tarih_araligi( $sql );
		if ( '' === $bas || '' === $bit ) {
			return array();
		}

		if ( false !== strpos( $sql, 'oneri_log' ) ) {
			$agg = array();
			foreach ( $this->oneri_log as $row ) {
				$t = (string) ( $row['created_at'] ?? '' );
				if ( $t < $bas || $t > $bit ) {
					continue;
				}
				$uid = (int) ( $row['urun_id'] ?? 0 );
				if ( $uid < 1 ) {
					continue;
				}
				if ( ! isset( $agg[ $uid ] ) ) {
					$agg[ $uid ] = array( 'gosterildi' => 0, 'siparis' => 0 );
				}
				$durum = (string) ( $row['durum'] ?? '' );
				if ( 'gosterildi' === $durum ) {
					++$agg[ $uid ]['gosterildi'];
				} elseif ( 'siparis' === $durum ) {
					++$agg[ $uid ]['siparis'];
				}
			}
			$out = array();
			foreach ( $agg as $uid => $m ) {
				$out[] = (object) array(
					'urun_id'    => $uid,
					'gosterildi' => $m['gosterildi'],
					'siparis'    => $m['siparis'],
				);
			}
			return $out;
		}

		if ( false !== strpos( $sql, 'recommendation_events' ) && false !== strpos( $sql, 'cart_add' ) ) {
			$refs_by_product = array();
			foreach ( $this->rec_events as $row ) {
				if ( 'cart_add' !== (string) ( $row['event_type'] ?? '' ) ) {
					continue;
				}
				$t = (string) ( $row['created_at'] ?? '' );
				if ( $t < $bas || $t > $bit ) {
					continue;
				}
				$uid = (int) ( $row['product_id'] ?? 0 );
				$ref = (string) ( $row['ref_id'] ?? '' );
				if ( $uid < 1 || '' === $ref ) {
					continue;
				}
				if ( ! isset( $refs_by_product[ $uid ] ) ) {
					$refs_by_product[ $uid ] = array();
				}
				$refs_by_product[ $uid ][ $ref ] = true;
			}
			$out = array();
			foreach ( $refs_by_product as $uid => $refs ) {
				$out[] = (object) array(
					'urun_id' => $uid,
					'sepete'  => count( $refs ),
				);
			}
			return $out;
		}

		return array();
	}
}

/**
 * @return QRMS_Oneri_Rapor_Wpdb
 */
function qrms_oneri_rapor_wpdb() {
	$GLOBALS['wpdb'] = new QRMS_Oneri_Rapor_Wpdb();
	update_option( QMO_Chatbot_DB::OPT, QMO_Chatbot_DB::SURUM );
	return $GLOBALS['wpdb'];
}

/**
 * @param array<int, array<string, mixed>> $rapor oneri_rapor çıktısı.
 * @param int                              $urun_id Ürün kimliği.
 * @return array<string, mixed>|null
 */
function qrms_oneri_rapor_satir( array $rapor, $urun_id ) {
	foreach ( $rapor as $satir ) {
		if ( (int) $satir['urun_id'] === (int) $urun_id ) {
			return $satir;
		}
	}
	return null;
}

$bas = '2026-03-01';
$bit = '2026-03-31';

qrms_test(
	'OR-269-A. legacy gosterildi + recommendation cart_add → sepete=1',
	function () use ( $bas, $bit ) {
		$wpdb = qrms_oneri_rapor_wpdb();
		$wpdb->oneri_log[]  = array(
			'urun_id'    => 100,
			'durum'      => 'gosterildi',
			'created_at' => '2026-03-10 12:00:00',
		);
		$wpdb->rec_events[] = array(
			'ref_id'     => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
			'event_type' => 'cart_add',
			'product_id' => 100,
			'created_at' => '2026-03-10 12:05:00',
		);
		$rapor = QMO_Chatbot_DB::oneri_rapor( $bas, $bit );
		$satir = qrms_oneri_rapor_satir( $rapor, 100 );
		qrms_assert_true( is_array( $satir ), 'ürün satırı' );
		qrms_assert_same( 1, $satir['gosterildi'], 'gosterildi' );
		qrms_assert_same( 1, $satir['sepete'], 'sepete' );
	}
);

qrms_test(
	'OR-269-B. aynı ref için duplicate cart_add → sepete=1',
	function () use ( $bas, $bit ) {
		$wpdb = qrms_oneri_rapor_wpdb();
		$ref  = '11111111-2222-3333-4444-555555555555';
		$wpdb->rec_events[] = array(
			'ref_id'     => $ref,
			'event_type' => 'cart_add',
			'product_id' => 200,
			'created_at' => '2026-03-11 10:00:00',
		);
		$wpdb->rec_events[] = array(
			'ref_id'     => $ref,
			'event_type' => 'cart_add',
			'product_id' => 200,
			'created_at' => '2026-03-11 10:01:00',
		);
		$rapor = QMO_Chatbot_DB::oneri_rapor( $bas, $bit );
		$satir = qrms_oneri_rapor_satir( $rapor, 200 );
		qrms_assert_same( 1, $satir['sepete'], 'distinct ref_id' );
	}
);

qrms_test(
	'OR-269-C. iki farklı ref aynı ürün → sepete=2',
	function () use ( $bas, $bit ) {
		$wpdb = qrms_oneri_rapor_wpdb();
		foreach ( array( 'ref-a-1111-2222-3333-444444444444', 'ref-b-1111-2222-3333-555555555555' ) as $ref ) {
			$wpdb->rec_events[] = array(
				'ref_id'     => $ref,
				'event_type' => 'cart_add',
				'product_id' => 300,
				'created_at' => '2026-03-12 09:00:00',
			);
		}
		$rapor = QMO_Chatbot_DB::oneri_rapor( $bas, $bit );
		$satir = qrms_oneri_rapor_satir( $rapor, 300 );
		qrms_assert_same( 2, $satir['sepete'], 'iki ref' );
	}
);

qrms_test(
	'OR-269-D. normal menü cart_add recommendation tablosunda yok → sepete=0',
	function () use ( $bas, $bit ) {
		$wpdb = qrms_oneri_rapor_wpdb();
		$wpdb->oneri_log[] = array(
			'urun_id'    => 400,
			'durum'      => 'gosterildi',
			'created_at' => '2026-03-13 08:00:00',
		);
		$rapor = QMO_Chatbot_DB::oneri_rapor( $bas, $bit );
		$satir = qrms_oneri_rapor_satir( $rapor, 400 );
		qrms_assert_same( 0, $satir['sepete'], 'sepete yok' );
	}
);

qrms_test(
	'OR-269-E. tarih filtresi dışı cart_add rapora girmez',
	function () use ( $bas, $bit ) {
		$wpdb = qrms_oneri_rapor_wpdb();
		$wpdb->rec_events[] = array(
			'ref_id'     => 'cccccccc-dddd-eeee-ffff-000000000000',
			'event_type' => 'cart_add',
			'product_id' => 500,
			'created_at' => '2026-02-28 23:59:59',
		);
		$rapor = QMO_Chatbot_DB::oneri_rapor( $bas, $bit );
		qrms_assert_same( null, qrms_oneri_rapor_satir( $rapor, 500 ), 'ürün yok' );
	}
);

qrms_test(
	'OR-269-F. legacy log yok, sadece recommendation cart_add → gosterildi=0 sepete=1',
	function () use ( $bas, $bit ) {
		$wpdb = qrms_oneri_rapor_wpdb();
		$wpdb->rec_events[] = array(
			'ref_id'     => 'dddddddd-eeee-ffff-0000-111111111111',
			'event_type' => 'cart_add',
			'product_id' => 600,
			'created_at' => '2026-03-15 14:00:00',
		);
		$rapor = QMO_Chatbot_DB::oneri_rapor( $bas, $bit );
		$satir = qrms_oneri_rapor_satir( $rapor, 600 );
		qrms_assert_same( 0, $satir['gosterildi'], 'gosterildi' );
		qrms_assert_same( 0, $satir['siparis'], 'siparis' );
		qrms_assert_same( 1, $satir['sepete'], 'sepete' );
	}
);

qrms_test(
	'OR-269-G. farklı product_id doğru satıra',
	function () use ( $bas, $bit ) {
		$wpdb = qrms_oneri_rapor_wpdb();
		$wpdb->rec_events[] = array(
			'ref_id'     => 'eeeeeeee-ffff-0000-1111-222222222222',
			'event_type' => 'cart_add',
			'product_id' => 701,
			'created_at' => '2026-03-16 11:00:00',
		);
		$wpdb->rec_events[] = array(
			'ref_id'     => 'ffffffff-0000-1111-2222-333333333333',
			'event_type' => 'cart_add',
			'product_id' => 702,
			'created_at' => '2026-03-16 11:30:00',
		);
		$rapor = QMO_Chatbot_DB::oneri_rapor( $bas, $bit );
		qrms_assert_same( 1, qrms_oneri_rapor_satir( $rapor, 701 )['sepete'], '701' );
		qrms_assert_same( 1, qrms_oneri_rapor_satir( $rapor, 702 )['sepete'], '702' );
	}
);

qrms_test(
	'OR-269-H. legacy gosterildi ve siparis korunur; legacy sepete sayılmaz',
	function () use ( $bas, $bit ) {
		$wpdb = qrms_oneri_rapor_wpdb();
		$wpdb->oneri_log[] = array(
			'urun_id'    => 800,
			'durum'      => 'gosterildi',
			'created_at' => '2026-03-17 10:00:00',
		);
		$wpdb->oneri_log[] = array(
			'urun_id'    => 800,
			'durum'      => 'gosterildi',
			'created_at' => '2026-03-17 11:00:00',
		);
		$wpdb->oneri_log[] = array(
			'urun_id'    => 800,
			'durum'      => 'siparis',
			'created_at' => '2026-03-17 12:00:00',
		);
		$wpdb->oneri_log[] = array(
			'urun_id'    => 800,
			'durum'      => 'sepete',
			'created_at' => '2026-03-17 10:30:00',
		);
		$rapor = QMO_Chatbot_DB::oneri_rapor( $bas, $bit );
		$satir = qrms_oneri_rapor_satir( $rapor, 800 );
		qrms_assert_same( 2, $satir['gosterildi'], 'gosterildi' );
		qrms_assert_same( 1, $satir['siparis'], 'siparis' );
		qrms_assert_same( 0, $satir['sepete'], 'legacy sepete yok sayılır' );
		qrms_assert_same( 0.5, $satir['donusum_orani'], 'dönüşüm siparis/gosterildi' );
	}
);

qrms_test(
	'OR-269-I. oneri_rapor iki toplu sorgu (N+1 yok)',
	function () use ( $bas, $bit ) {
		$wpdb = qrms_oneri_rapor_wpdb();
		QMO_Chatbot_DB::oneri_rapor( $bas, $bit );
		$get = 0;
		foreach ( $wpdb->queries as $sql ) {
			if ( false !== strpos( $sql, 'get_results' ) ) {
				continue;
			}
			if ( preg_match( '/SELECT/i', $sql ) ) {
				++$get;
			}
		}
		qrms_assert_same( 2, $get, 'legacy + recommendation' );
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/class-db.php' );
		qrms_assert_contains( 'COUNT(DISTINCT ref_id)', $php, 'distinct ref_id' );
		qrms_assert_contains( 'recommendation_events_tablosu', $php, 'events tablosu' );
	}
);
