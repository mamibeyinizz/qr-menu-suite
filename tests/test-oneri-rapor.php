<?php
/**
 * Öneri Raporu — Phase 6.1 lifecycle + recommendation_events (#269).
 *
 * Stub ortamı Phase 6.2 order-line attribution engine ile uyumlu simülasyon kullanır.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/class-db.php';

echo "\nÖneri Raporu recommendation lifecycle (Phase 6.1)\n";

/**
 * oneri_rapor() için bellek içi wpdb.
 */
class QRMS_Oneri_Rapor_Wpdb {
	public $prefix            = 'wp_';
	public $oneri_log         = array();
	public $rec_events        = array();
	public $analytics_events  = array();
	public $analytics_tablo_var = true;
	public $queries           = array();

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
		unset( $suppress );
		return false;
	}

	public function get_var( $sql ) {
		if ( false !== strpos( $sql, 'SHOW TABLES' ) ) {
			return $this->analytics_tablo_var ? 'wp_rma_analytics' : null;
		}
		return null;
	}

	/**
	 * @param string $sql Sorgu.
	 * @return array<int, string>
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

	/**
	 * Phase 6.2 attribution engine — bellek içi satır çıktısı (MariaDB SQL ile aynı kurallar).
	 *
	 * @param string $bas Alt sınır.
	 * @param string $bit Üst sınır.
	 * @return array<int, array<string, mixed>>
	 */
	private function attribution_engine_lines( $bas, $bit ) {
		$carts = array();
		foreach ( $this->rec_events as $i => $row ) {
			if ( 'cart_add' !== (string) ( $row['event_type'] ?? '' ) ) {
				continue;
			}
			$t = (string) ( $row['created_at'] ?? '' );
			if ( $t < $bas || $t > $bit ) {
				continue;
			}
			$carts[] = array(
				'cart_row_id' => $i,
				'ref_id'      => (string) ( $row['ref_id'] ?? '' ),
				'product_id'  => (int) ( $row['product_id'] ?? 0 ),
				'session_id'  => (string) ( $row['session_id'] ?? '' ),
				'cart_at'     => $t,
			);
		}

		$cancelled = array();
		foreach ( $this->analytics_events as $ev ) {
			if ( 'order_cancelled' === ( $ev['event_type'] ?? '' ) ) {
				$oid = (string) ( $ev['order_id'] ?? '' );
				if ( '' !== $oid ) {
					$cancelled[ $oid ] = true;
				}
			}
		}

		$lines = array();
		foreach ( $this->analytics_events as $i => $ev ) {
			if ( 'order_sent' !== ( $ev['event_type'] ?? '' ) ) {
				continue;
			}
			$oid = (string) ( $ev['order_id'] ?? '' );
			if ( '' === $oid || isset( $cancelled[ $oid ] ) ) {
				continue;
			}
			$t = (string) ( $ev['created_at'] ?? '' );
			if ( $t < $bas ) {
				continue;
			}
			$key = (string) $ev['session_id'] . "\0" . (int) ( $ev['item_id'] ?? 0 ) . "\0" . $oid;
			if ( ! isset( $lines[ $key ] ) ) {
				$lines[ $key ] = array(
					'session_id'  => (string) ( $ev['session_id'] ?? '' ),
					'item_id'     => (int) ( $ev['item_id'] ?? 0 ),
					'order_id'    => $oid,
					'line_at'     => $t,
					'line_row_id' => $i,
					'line_qty'    => (int) ( $ev['qty'] ?? 1 ),
					'unit_price'  => array_key_exists( 'unit_price', $ev ) ? $ev['unit_price'] : null,
				);
			} else {
				$lines[ $key ]['line_qty'] += (int) ( $ev['qty'] ?? 1 );
				if ( $t < $lines[ $key ]['line_at'] ) {
					$lines[ $key ]['line_at']     = $t;
					$lines[ $key ]['line_row_id'] = $i;
					$lines[ $key ]['unit_price']  = array_key_exists( 'unit_price', $ev ) ? $ev['unit_price'] : null;
				}
			}
		}

		$ref_first = array();
		foreach ( $carts as $cart ) {
			$best = null;
			foreach ( $lines as $line ) {
				if ( $line['session_id'] !== $cart['session_id'] || $line['item_id'] !== $cart['product_id'] ) {
					continue;
				}
				if ( $line['line_at'] < $cart['cart_at'] ) {
					continue;
				}
				if ( null === $best
					|| $line['line_at'] < $best['line_at']
					|| ( $line['line_at'] === $best['line_at'] && $line['line_row_id'] < $best['line_row_id'] ) ) {
					$best = $line;
				}
			}
			if ( null !== $best ) {
				$ref_first[] = array_merge( $cart, $best );
			}
		}

		$grouped = array();
		foreach ( $ref_first as $rf ) {
			$gkey = $rf['product_id'] . "\0" . $rf['session_id'] . "\0" . $rf['order_id'] . "\0" . $rf['item_id'];
			if ( ! isset( $grouped[ $gkey ] ) ) {
				$grouped[ $gkey ] = array(
					'product_id' => $rf['product_id'],
					'session_id' => $rf['session_id'],
					'order_id'   => $rf['order_id'],
					'item_id'    => $rf['item_id'],
					'line_qty'   => $rf['line_qty'],
					'unit_price' => $rf['unit_price'],
					'ref_count'  => 0,
				);
			}
			++$grouped[ $gkey ]['ref_count'];
		}

		$out = array();
		foreach ( $grouped as $g ) {
			$units = min( (int) $g['line_qty'], (int) $g['ref_count'] );
			if ( $units < 1 ) {
				continue;
			}
			$price = $g['unit_price'];
			$rev   = ( null === $price || '' === $price ) ? 0.0 : $units * (float) $price;
			$out[] = array(
				'product_id'         => (int) $g['product_id'],
				'session_id'         => (string) $g['session_id'],
				'order_id'           => (string) $g['order_id'],
				'item_id'            => (int) $g['item_id'],
				'attributed_units'   => $units,
				'attributed_revenue' => $rev,
				'unit_price'         => $price,
			);
		}
		return $out;
	}

	public function get_results( $sql, $mode = null ) {
		$this->queries[] = $sql;

		if ( false !== strpos( $sql, 'line_attrib' ) ) {
			list( $bas, $bit ) = $this->tarih_araligi( $sql );
			if ( '' === $bas || '' === $bit ) {
				return array();
			}
			$lines = $this->attribution_engine_lines( $bas, $bit );
			if ( ARRAY_A === $mode ) {
				return $lines;
			}
			$objs = array();
			foreach ( $lines as $line ) {
				$objs[] = (object) $line;
			}
			return $objs;
		}

		list( $bas, $bit ) = $this->tarih_araligi( $sql );
		if ( '' === $bas || '' === $bit ) {
			return array();
		}

		if ( false !== strpos( $sql, 'oneri_log' ) ) {
			$only_gosterildi = false !== strpos( $sql, "durum = 'gosterildi'" );
			$only_siparis    = false !== strpos( $sql, "durum = 'siparis'" );
			$agg             = array();
			foreach ( $this->oneri_log as $row ) {
				$t = (string) ( $row['created_at'] ?? '' );
				if ( $t < $bas || $t > $bit ) {
					continue;
				}
				$uid   = (int) ( $row['urun_id'] ?? 0 );
				$durum = (string) ( $row['durum'] ?? '' );
				if ( $uid < 1 ) {
					continue;
				}
				if ( $only_gosterildi && 'gosterildi' !== $durum ) {
					continue;
				}
				if ( $only_siparis && 'siparis' !== $durum ) {
					continue;
				}
				if ( ! isset( $agg[ $uid ] ) ) {
					$agg[ $uid ] = 0;
				}
				++$agg[ $uid ];
			}
			$out = array();
			foreach ( $agg as $uid => $n ) {
				if ( $only_gosterildi ) {
					$out[] = (object) array(
						'urun_id'    => $uid,
						'gosterildi' => $n,
					);
				} else {
					$out[] = (object) array(
						'urun_id' => $uid,
						'siparis' => $n,
					);
				}
			}
			return $out;
		}

		if ( false !== strpos( $sql, 'recommendation_events' ) && false !== strpos( $sql, "'shown'" ) ) {
			$refs_by_product = array();
			foreach ( $this->rec_events as $row ) {
				if ( 'shown' !== (string) ( $row['event_type'] ?? '' ) ) {
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
					'urun_id'    => $uid,
					'gosterildi' => count( $refs ),
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

$bas_pre = '2026-03-01';
$bit_pre = '2026-03-31';

$cutover = QMO_Chatbot_DB::RECOMMENDATION_REPORT_CUTOVER_DATE;
$bas_post = $cutover;
$bit_post = '2026-10-05';

qrms_test(
	'OR-269-A. legacy gosterildi + recommendation cart_add → sepete=1',
	function () use ( $bas_pre, $bit_pre ) {
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
		$rapor = QMO_Chatbot_DB::oneri_rapor( $bas_pre, $bit_pre );
		$satir = qrms_oneri_rapor_satir( $rapor, 100 );
		qrms_assert_true( is_array( $satir ), 'ürün satırı' );
		qrms_assert_same( 1, $satir['gosterildi'], 'gosterildi' );
		qrms_assert_same( 1, $satir['sepete'], 'sepete' );
	}
);

qrms_test(
	'OR-269-B. aynı ref için duplicate cart_add → sepete=1',
	function () use ( $bas_pre, $bit_pre ) {
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
		$rapor = QMO_Chatbot_DB::oneri_rapor( $bas_pre, $bit_pre );
		$satir = qrms_oneri_rapor_satir( $rapor, 200 );
		qrms_assert_same( 1, $satir['sepete'], 'distinct ref_id' );
	}
);

qrms_test(
	'OR-269-C. iki farklı ref aynı ürün → sepete=2',
	function () use ( $bas_pre, $bit_pre ) {
		$wpdb = qrms_oneri_rapor_wpdb();
		foreach ( array( 'ref-a-1111-2222-3333-444444444444', 'ref-b-1111-2222-3333-555555555555' ) as $ref ) {
			$wpdb->rec_events[] = array(
				'ref_id'     => $ref,
				'event_type' => 'cart_add',
				'product_id' => 300,
				'created_at' => '2026-03-12 09:00:00',
			);
		}
		$rapor = QMO_Chatbot_DB::oneri_rapor( $bas_pre, $bit_pre );
		$satir = qrms_oneri_rapor_satir( $rapor, 300 );
		qrms_assert_same( 2, $satir['sepete'], 'iki ref' );
	}
);

qrms_test(
	'OR-269-D. normal menü cart_add recommendation tablosunda yok → sepete=0',
	function () use ( $bas_pre, $bit_pre ) {
		$wpdb = qrms_oneri_rapor_wpdb();
		$wpdb->oneri_log[] = array(
			'urun_id'    => 400,
			'durum'      => 'gosterildi',
			'created_at' => '2026-03-13 08:00:00',
		);
		$rapor = QMO_Chatbot_DB::oneri_rapor( $bas_pre, $bit_pre );
		$satir = qrms_oneri_rapor_satir( $rapor, 400 );
		qrms_assert_same( 0, $satir['sepete'], 'sepete yok' );
	}
);

qrms_test(
	'OR-269-E. tarih filtresi dışı cart_add rapora girmez',
	function () use ( $bas_pre, $bit_pre ) {
		$wpdb = qrms_oneri_rapor_wpdb();
		$wpdb->rec_events[] = array(
			'ref_id'     => 'cccccccc-dddd-eeee-ffff-000000000000',
			'event_type' => 'cart_add',
			'product_id' => 500,
			'created_at' => '2026-02-28 23:59:59',
		);
		$rapor = QMO_Chatbot_DB::oneri_rapor( $bas_pre, $bit_pre );
		qrms_assert_same( null, qrms_oneri_rapor_satir( $rapor, 500 ), 'ürün yok' );
	}
);

qrms_test(
	'OR-269-F. post-cutover yalnız cart_add değil events shown kullanılır',
	function () use ( $bas_post, $bit_post ) {
		$wpdb = qrms_oneri_rapor_wpdb();
		$wpdb->rec_events[] = array(
			'ref_id'     => 'dddddddd-eeee-ffff-0000-111111111111',
			'event_type' => 'shown',
			'product_id' => 600,
			'created_at' => '2026-09-27 14:00:00',
		);
		$wpdb->rec_events[] = array(
			'ref_id'     => 'dddddddd-eeee-ffff-0000-111111111111',
			'event_type' => 'cart_add',
			'product_id' => 600,
			'created_at' => '2026-09-27 14:05:00',
		);
		$rapor = QMO_Chatbot_DB::oneri_rapor( $bas_post, $bit_post );
		$satir = qrms_oneri_rapor_satir( $rapor, 600 );
		qrms_assert_same( 1, $satir['gosterildi'], 'events shown' );
		qrms_assert_same( 0, $satir['dogrudan_chatbot_siparis'], 'bot ayrı' );
		qrms_assert_same( 1, $satir['sepete'], 'sepete' );
	}
);

qrms_test(
	'OR-269-G. farklı product_id doğru satıra',
	function () use ( $bas_pre, $bit_pre ) {
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
		$rapor = QMO_Chatbot_DB::oneri_rapor( $bas_pre, $bit_pre );
		qrms_assert_same( 1, qrms_oneri_rapor_satir( $rapor, 701 )['sepete'], '701' );
		qrms_assert_same( 1, qrms_oneri_rapor_satir( $rapor, 702 )['sepete'], '702' );
	}
);

qrms_test(
	'OR-269-H. legacy bot sipariş ayrı; dönüşüm atfedilen/gosterildi',
	function () use ( $bas_pre, $bit_pre ) {
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
		$rapor = QMO_Chatbot_DB::oneri_rapor( $bas_pre, $bit_pre );
		$satir = qrms_oneri_rapor_satir( $rapor, 800 );
		qrms_assert_same( 2, $satir['gosterildi'], 'gosterildi' );
		qrms_assert_same( 1, $satir['dogrudan_chatbot_siparis'], 'bot siparis' );
		qrms_assert_same( 0, $satir['sepete'], 'legacy sepete yok sayılır' );
		qrms_assert_same( 0, $satir['atfedilen_siparis'], 'atfedilen yok' );
		qrms_assert_same( 0.0, $satir['donusum_orani'], 'dönüşüm atfedilen/gosterildi' );
	}
);

qrms_test(
	'OR-269-I. oneri_rapor toplu SELECT; ref başına gozlemsel helper yok',
	function () use ( $bas_pre, $bit_pre ) {
		$wpdb = qrms_oneri_rapor_wpdb();
		QMO_Chatbot_DB::oneri_rapor( $bas_pre, $bit_pre );
		$get = 0;
		foreach ( $wpdb->queries as $sql ) {
			if ( preg_match( '/SELECT/i', $sql ) ) {
				++$get;
			}
		}
		qrms_assert_true( $get >= 2 && $get <= 8, 'sabit sayıda toplu sorgu' );
		$php   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/class-db.php' );
		$start = strpos( $php, 'public static function oneri_rapor' );
		$end   = strpos( $php, 'public static function recommendation_events_eski_sil', $start );
		qrms_assert_true( false !== $start && false !== $end, 'oneri_rapor gövdesi' );
		$govde = substr( $php, $start, $end - $start );
		qrms_assert_contains( 'recommendation_attribution_hesapla', $php, 'order-line attribution engine' );
		qrms_assert_false(
			false !== strpos( $govde, 'recommendation_gozlemsel_order_sent_var_mi' ),
			'N+1 helper döngüsü yok'
		);
	}
);

// Phase 6.1 CASE 1–20
qrms_test(
	'P61-C1. legacy shown only pre-cutover',
	function () use ( $bas_pre, $bit_pre ) {
		$wpdb = qrms_oneri_rapor_wpdb();
		$wpdb->oneri_log[] = array(
			'urun_id'    => 9001,
			'durum'      => 'gosterildi',
			'created_at' => '2026-03-05 10:00:00',
		);
		$satir = qrms_oneri_rapor_satir( QMO_Chatbot_DB::oneri_rapor( $bas_pre, $bit_pre ), 9001 );
		qrms_assert_same( 1, $satir['gosterildi'], 'legacy shown' );
	}
);

qrms_test(
	'P61-C2. new shown only post-cutover',
	function () use ( $bas_post, $bit_post ) {
		$wpdb = qrms_oneri_rapor_wpdb();
		$wpdb->rec_events[] = array(
			'ref_id'     => 'p61-c2-1111-2222-3333-444444444444',
			'event_type' => 'shown',
			'product_id' => 9002,
			'created_at' => '2026-09-28 09:00:00',
		);
		$satir = qrms_oneri_rapor_satir( QMO_Chatbot_DB::oneri_rapor( $bas_post, $bit_post ), 9002 );
		qrms_assert_same( 1, $satir['gosterildi'], 'event shown' );
	}
);

qrms_test(
	'P61-C3. cutover gününde legacy gosterildi rapora girmez',
	function () {
		$wpdb = qrms_oneri_rapor_wpdb();
		$gun  = QMO_Chatbot_DB::RECOMMENDATION_REPORT_CUTOVER_DATE;
		$wpdb->oneri_log[] = array(
			'urun_id'    => 9003,
			'durum'      => 'gosterildi',
			'created_at' => $gun . ' 08:00:00',
		);
		$wpdb->rec_events[] = array(
			'ref_id'     => 'p61-c3-1111-2222-3333-444444444444',
			'event_type' => 'shown',
			'product_id' => 9003,
			'created_at' => $gun . ' 09:00:00',
		);
		$satir = qrms_oneri_rapor_satir( QMO_Chatbot_DB::oneri_rapor( $gun, $gun ), 9003 );
		qrms_assert_same( 1, $satir['gosterildi'], 'yalnız events shown' );
	}
);

qrms_test(
	'P61-C6. mixed range legacy + events shown toplam',
	function () {
		$wpdb = qrms_oneri_rapor_wpdb();
		$wpdb->oneri_log[] = array(
			'urun_id'    => 9006,
			'durum'      => 'gosterildi',
			'created_at' => '2026-09-20 10:00:00',
		);
		$wpdb->rec_events[] = array(
			'ref_id'     => 'p61-c6-1111-2222-3333-444444444444',
			'event_type' => 'shown',
			'product_id' => 9006,
			'created_at' => '2026-09-28 10:00:00',
		);
		$satir = qrms_oneri_rapor_satir( QMO_Chatbot_DB::oneri_rapor( '2026-09-20', '2026-09-30' ), 9006 );
		qrms_assert_same( 2, $satir['gosterildi'], 'segment toplamı' );
	}
);

qrms_test(
	'P61-C9. cart_add + order_sent aynı session/ürün → atfedilen=1',
	function () use ( $bas_post, $bit_post ) {
		$wpdb    = qrms_oneri_rapor_wpdb();
		$session = 's_test_p61_c9';
		$ref     = 'p61-c9-1111-2222-3333-444444444444';
		$wpdb->rec_events[] = array(
			'ref_id'     => $ref,
			'event_type' => 'shown',
			'product_id' => 9009,
			'session_id' => $session,
			'created_at' => '2026-09-27 10:00:00',
		);
		$wpdb->rec_events[] = array(
			'ref_id'     => $ref,
			'event_type' => 'cart_add',
			'product_id' => 9009,
			'session_id' => $session,
			'created_at' => '2026-09-27 10:05:00',
		);
		$wpdb->analytics_events[] = array(
			'event_type' => 'order_sent',
			'session_id' => $session,
			'item_id'    => 9009,
			'order_id'   => 'p61-c9-order',
			'created_at' => '2026-09-27 10:30:00',
		);
		$satir = qrms_oneri_rapor_satir( QMO_Chatbot_DB::oneri_rapor( $bas_post, $bit_post ), 9009 );
		qrms_assert_same( 1, $satir['atfedilen_siparis'], 'atfedilen' );
	}
);

qrms_test(
	'P61-C10. farklı product → atfedilen=0',
	function () use ( $bas_post, $bit_post ) {
		$wpdb    = qrms_oneri_rapor_wpdb();
		$session = 's_test_p61_c10';
		$ref     = 'p61-c10-111-2222-3333-444444444444';
		$wpdb->rec_events[] = array(
			'ref_id'     => $ref,
			'event_type' => 'cart_add',
			'product_id' => 9010,
			'session_id' => $session,
			'created_at' => '2026-09-27 11:00:00',
		);
		$wpdb->analytics_events[] = array(
			'event_type' => 'order_sent',
			'session_id' => $session,
			'item_id'    => 9999,
			'created_at' => '2026-09-27 11:30:00',
		);
		$satir = qrms_oneri_rapor_satir( QMO_Chatbot_DB::oneri_rapor( $bas_post, $bit_post ), 9010 );
		qrms_assert_same( 0, $satir['atfedilen_siparis'], 'ürün eşleşmez' );
	}
);

qrms_test(
	'P61-C11. farklı session → atfedilen=0',
	function () use ( $bas_post, $bit_post ) {
		$wpdb = qrms_oneri_rapor_wpdb();
		$ref  = 'p61-c11-111-2222-3333-444444444444';
		$wpdb->rec_events[] = array(
			'ref_id'     => $ref,
			'event_type' => 'cart_add',
			'product_id' => 9011,
			'session_id' => 's_a',
			'created_at' => '2026-09-27 12:00:00',
		);
		$wpdb->analytics_events[] = array(
			'event_type' => 'order_sent',
			'session_id' => 's_b',
			'item_id'    => 9011,
			'created_at' => '2026-09-27 12:30:00',
		);
		$satir = qrms_oneri_rapor_satir( QMO_Chatbot_DB::oneri_rapor( $bas_post, $bit_post ), 9011 );
		qrms_assert_same( 0, $satir['atfedilen_siparis'], 'session eşleşmez' );
	}
);

qrms_test(
	'P61-C12. legacy siparis bot KPI; atfedilen değil',
	function () use ( $bas_pre, $bit_pre ) {
		$wpdb = qrms_oneri_rapor_wpdb();
		$wpdb->oneri_log[] = array(
			'urun_id'    => 9012,
			'durum'      => 'gosterildi',
			'created_at' => '2026-03-01 10:00:00',
		);
		$wpdb->oneri_log[] = array(
			'urun_id'    => 9012,
			'durum'      => 'siparis',
			'created_at' => '2026-03-01 11:00:00',
		);
		$satir = qrms_oneri_rapor_satir( QMO_Chatbot_DB::oneri_rapor( $bas_pre, $bit_pre ), 9012 );
		qrms_assert_same( 1, $satir['dogrudan_chatbot_siparis'], 'bot' );
		qrms_assert_same( 0, $satir['atfedilen_siparis'], 'atfedilen ayrı' );
	}
);

qrms_test(
	'P61-C13. duplicate shown ref → tek gösterim',
	function () use ( $bas_post, $bit_post ) {
		$wpdb = qrms_oneri_rapor_wpdb();
		$ref  = 'p61-c13-111-2222-3333-444444444444';
		foreach ( array( '2026-09-27 10:00:00', '2026-09-27 10:01:00' ) as $t ) {
			$wpdb->rec_events[] = array(
				'ref_id'     => $ref,
				'event_type' => 'shown',
				'product_id' => 9013,
				'created_at' => $t,
			);
		}
		$satir = qrms_oneri_rapor_satir( QMO_Chatbot_DB::oneri_rapor( $bas_post, $bit_post ), 9013 );
		qrms_assert_same( 1, $satir['gosterildi'], 'distinct ref' );
	}
);

qrms_test(
	'P61-C17. conversion 100 shown / 8 atfedilen → 8%',
	function () {
		$wpdb = qrms_oneri_rapor_wpdb();
		for ( $i = 0; $i < 100; $i++ ) {
			$wpdb->rec_events[] = array(
				'ref_id'     => sprintf( 'p61-c17-%02d-2222-3333-444444444444', $i ),
				'event_type' => 'shown',
				'product_id' => 9017,
				'created_at' => '2026-09-27 08:00:00',
			);
		}
		for ( $i = 0; $i < 8; $i++ ) {
			$session = 's_c17_' . $i;
			$ref     = sprintf( 'p61-c17b-%02d-2222-3333-444444444444', $i );
			$wpdb->rec_events[] = array(
				'ref_id'     => $ref,
				'event_type' => 'cart_add',
				'product_id' => 9017,
				'session_id' => $session,
				'created_at' => '2026-09-27 09:00:00',
			);
			$wpdb->analytics_events[] = array(
				'event_type' => 'order_sent',
				'session_id' => $session,
				'item_id'    => 9017,
				'order_id'   => 'p61-c17-' . $i,
				'created_at' => '2026-09-27 09:30:00',
			);
		}
		$satir = qrms_oneri_rapor_satir( QMO_Chatbot_DB::oneri_rapor( '2026-09-27', '2026-09-27' ), 9017 );
		qrms_assert_same( 100, $satir['gosterildi'], 'shown' );
		qrms_assert_same( 8, $satir['atfedilen_siparis'], 'atfedilen' );
		qrms_assert_same( 0.08, $satir['donusum_orani'], '8% oran' );
	}
);

qrms_test(
	'P61-C18. shown=0 güvenli dönüşüm',
	function () use ( $bas_post, $bit_post ) {
		$wpdb = qrms_oneri_rapor_wpdb();
		$wpdb->rec_events[] = array(
			'ref_id'     => 'p61-c18-111-2222-3333-444444444444',
			'event_type' => 'cart_add',
			'product_id' => 9018,
			'created_at' => '2026-09-28 10:00:00',
		);
		$satir = qrms_oneri_rapor_satir( QMO_Chatbot_DB::oneri_rapor( $bas_post, $bit_post ), 9018 );
		qrms_assert_same( 0, $satir['gosterildi'], 'shown yok' );
		qrms_assert_same( 0.0, $satir['donusum_orani'], 'bölme yok' );
	}
);

qrms_test(
	'P61-C19. mixed range toplam dönüşüm',
	function () {
		$wpdb = qrms_oneri_rapor_wpdb();
		$wpdb->oneri_log[] = array(
			'urun_id'    => 9019,
			'durum'      => 'gosterildi',
			'created_at' => '2026-09-24 10:00:00',
		);
		$ref = 'p61-c19-111-2222-3333-444444444444';
		$wpdb->rec_events[] = array(
			'ref_id'     => $ref,
			'event_type' => 'shown',
			'product_id' => 9019,
			'created_at' => '2026-09-28 10:00:00',
		);
		$wpdb->rec_events[] = array(
			'ref_id'     => $ref,
			'event_type' => 'cart_add',
			'product_id' => 9019,
			'session_id' => 's_c19',
			'created_at' => '2026-09-28 10:05:00',
		);
		$wpdb->analytics_events[] = array(
			'event_type' => 'order_sent',
			'session_id' => 's_c19',
			'item_id'    => 9019,
			'order_id'   => 'p61-c19-order',
			'created_at' => '2026-09-28 10:30:00',
		);
		$satir = qrms_oneri_rapor_satir( QMO_Chatbot_DB::oneri_rapor( '2026-09-20', '2026-09-30' ), 9019 );
		qrms_assert_same( 2, $satir['gosterildi'], '1 legacy + 1 event' );
		qrms_assert_same( 1, $satir['atfedilen_siparis'], 'atfedilen' );
		qrms_assert_same( 0.5, $satir['donusum_orani'], '1/2' );
	}
);

qrms_test(
	'P61 cutover constant',
	function () {
		qrms_assert_same( '2026-09-26', QMO_Chatbot_DB::RECOMMENDATION_REPORT_CUTOVER_DATE, 'cutover' );
	}
);
