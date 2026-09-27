<?php
/**
 * recommendation_events 90 gün saklama — Phase 6.1.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/class-db.php';

echo "\nRecommendation events retention (Phase 6.1)\n";

/**
 * Retention test wpdb.
 */
class QRMS_Rec_Retention_Wpdb {
	public $prefix       = 'wp_';
	public $rec_events   = array();
	public $deleted_sql  = array();

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

	public function query( $sql ) {
		$this->deleted_sql[] = $sql;
		if ( ! preg_match( "/event_type = '([^']+)'/", $sql, $tm ) ) {
			return 0;
		}
		if ( ! preg_match( "/created_at < '([^']+)'/", $sql, $sm ) ) {
			return 0;
		}
		$tip   = $tm[1];
		$sinir = $sm[1];
		$sil   = 0;
		foreach ( $this->rec_events as $i => $row ) {
			if ( (string) ( $row['event_type'] ?? '' ) !== $tip ) {
				continue;
			}
			if ( (string) ( $row['created_at'] ?? '' ) >= $sinir ) {
				continue;
			}
			unset( $this->rec_events[ $i ] );
			++$sil;
			if ( $sil >= 5000 ) {
				break;
			}
		}
		$this->rec_events = array_values( $this->rec_events );
		return $sil;
	}
}

/**
 * @return QRMS_Rec_Retention_Wpdb
 */
function qrms_rec_retention_wpdb() {
	$GLOBALS['wpdb'] = new QRMS_Rec_Retention_Wpdb();
	update_option( QMO_Chatbot_DB::OPT, QMO_Chatbot_DB::SURUM );
	return $GLOBALS['wpdb'];
}

qrms_test(
	'P61-C14. 90+ gün eski shown/cart_add silinir',
	function () {
		$wpdb = qrms_rec_retention_wpdb();
		$wpdb->rec_events[] = array(
			'ref_id'     => 'old-shown-ref',
			'event_type' => 'shown',
			'created_at' => gmdate( 'Y-m-d H:i:s', time() - ( 100 * DAY_IN_SECONDS ) ),
		);
		$wpdb->rec_events[] = array(
			'ref_id'     => 'old-cart-ref',
			'event_type' => 'cart_add',
			'created_at' => gmdate( 'Y-m-d H:i:s', time() - ( 100 * DAY_IN_SECONDS ) ),
		);
		$silinen = QMO_Chatbot_DB::recommendation_events_eski_sil( 90 );
		qrms_assert_same( 2, $silinen, 'iki satır' );
		qrms_assert_same( 0, count( $wpdb->rec_events ), 'tablo boş' );
	}
);

qrms_test(
	'P61-C15. taze events korunur',
	function () {
		$wpdb = qrms_rec_retention_wpdb();
		$wpdb->rec_events[] = array(
			'ref_id'     => 'fresh-shown',
			'event_type' => 'shown',
			'created_at' => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
		);
		QMO_Chatbot_DB::recommendation_events_eski_sil( 90 );
		qrms_assert_same( 1, count( $wpdb->rec_events ), 'taze kaldı' );
	}
);

qrms_test(
	'P61-C16. cleanup ikinci tur idempotent',
	function () {
		$wpdb = qrms_rec_retention_wpdb();
		$wpdb->rec_events[] = array(
			'ref_id'     => 'old2',
			'event_type' => 'shown',
			'created_at' => gmdate( 'Y-m-d H:i:s', time() - ( 120 * DAY_IN_SECONDS ) ),
		);
		qrms_assert_same( 1, QMO_Chatbot_DB::recommendation_events_eski_sil( 90 ), 'ilk' );
		qrms_assert_same( 0, QMO_Chatbot_DB::recommendation_events_eski_sil( 90 ), 'ikinci' );
	}
);

qrms_test(
	'P61 retention qrms analitik temizlik entegrasyonu',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php' );
		qrms_assert_contains( 'recommendation_events_temizligi', $php, 'hook' );
		qrms_assert_contains( 'recommendation_events_eski_sil', $php, 'çağrı' );
	}
);
