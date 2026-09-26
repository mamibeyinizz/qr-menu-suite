<?php
/**
 * Phase 4 P0: recommendation attribution testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/class-qmo-oturum.php';
require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/helpers.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/class-db.php';

echo "\nQR recommendation attribution (Phase 4 P0)\n";

/**
 * Recommendation + analitik bellek içi wpdb.
 */
class QRMS_P4_Test_Wpdb {
	public $prefix = 'wp_';
	public $rec_events = array();
	public $store      = array();
	public $inserts    = array();
	public $next_id    = 1;

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

	public function insert( $table, $data, $format = null ) {
		unset( $format );
		$data['id'] = $this->next_id++;
		if ( false !== strpos( $table, 'recommendation_events' ) ) {
			$this->rec_events[] = $data;
		} else {
			$this->inserts[] = $data;
			$this->store[]   = $data;
		}
		return 1;
	}

	public function get_col( $sql ) {
		if ( preg_match_all( "/ref_id = '([^']+)'/", $sql, $m ) && false !== strpos( $sql, 'cart_add' ) ) {
			$out = array();
			foreach ( $this->rec_events as $row ) {
				if ( 'cart_add' === ( $row['event_type'] ?? '' ) && in_array( $row['ref_id'], $m[1], true ) ) {
					$out[] = $row['ref_id'];
				}
			}
			return $out;
		}
		if ( false !== strpos( $sql, 'ref_id IN' ) && false !== strpos( $sql, 'cart_add' ) ) {
			$out = array();
			foreach ( $this->rec_events as $row ) {
				if ( 'cart_add' === ( $row['event_type'] ?? '' ) ) {
					$out[] = $row['ref_id'];
				}
			}
			return array_values( array_unique( $out ) );
		}
		return array();
	}

	public function get_results( $sql, $mode = null ) {
		unset( $mode );
		if ( false !== strpos( $sql, 'recommendation_events' ) && false !== strpos( $sql, 'event_type = \'shown\'' ) ) {
			$out = array();
			foreach ( $this->rec_events as $row ) {
				if ( 'shown' === ( $row['event_type'] ?? '' ) ) {
					$out[] = $row;
				}
			}
			return $out;
		}
		return array();
	}

	public function get_var( $sql ) {
		if ( false !== strpos( $sql, 'order_sent' ) && preg_match( "/session_id = '([^']+)'/", $sql, $sm ) ) {
			preg_match( '/item_id = (\d+)/', $sql, $im );
			preg_match( "/created_at >= '([^']+)'/", $sql, $tm );
			foreach ( $this->store as $row ) {
				if ( 'order_sent' !== ( $row['event_type'] ?? '' ) ) {
					continue;
				}
				if ( $sm[1] === ( $row['session_id'] ?? '' )
					&& (int) $im[1] === (int) ( $row['item_id'] ?? 0 )
					&& ( $row['created_at'] ?? '' ) >= $tm[1] ) {
					return $row['id'];
				}
			}
		}
		if ( false !== strpos( $sql, 'cart_add' ) && false !== strpos( $sql, 'recommendation_events' ) ) {
			preg_match( "/ref_id = '([^']+)'/", $sql, $rm );
			foreach ( $this->rec_events as $row ) {
				if ( 'cart_add' === ( $row['event_type'] ?? '' ) && ( $row['ref_id'] ?? '' ) === $rm[1] ) {
					return $row['id'];
				}
			}
		}
		return null;
	}

	public function get_row( $sql, $mode = null ) {
		unset( $mode );
		if ( false !== strpos( $sql, 'cart_add' ) && preg_match( "/ref_id = '([^']+)'/", $sql, $rm ) ) {
			foreach ( $this->rec_events as $row ) {
				if ( 'cart_add' === ( $row['event_type'] ?? '' ) && $row['ref_id'] === $rm[1] ) {
					return $row;
				}
			}
		}
		return null;
	}
}

/**
 * @return QRMS_P4_Test_Wpdb
 */
function qrms_p4_wpdb() {
	$GLOBALS['wpdb'] = new QRMS_P4_Test_Wpdb();
	update_option( QMO_Chatbot_DB::OPT, QMO_Chatbot_DB::SURUM );
	return $GLOBALS['wpdb'];
}

function qrms_p4_session() {
	return 's_' . md5( 'masa-a_1700000000' );
}

qrms_test(
	'P4-1. recommendation shown event oluşuyor',
	function () {
		$wpdb = qrms_p4_wpdb();
		$sid  = qrms_p4_session();
		qrms_assert_true(
			QMO_Chatbot_DB::recommendation_event_ekle( 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', QMO_Chatbot_DB::REC_EVENT_SHOWN, 42, $sid, 'ai' ),
			'shown insert'
		);
		qrms_assert_same( 1, count( $wpdb->rec_events ), 'bir olay' );
		qrms_assert_same( 'shown', $wpdb->rec_events[0]['event_type'], 'tip' );
	}
);

qrms_test(
	'P4-2. ref_id server-generated (wp_generate_uuid4)',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/class-db.php' );
		qrms_assert_contains( 'wp_generate_uuid4', $php, 'uuid üretimi' );
		$ref = QMO_Chatbot_DB::recommendation_ref_uret();
		qrms_assert_same( 36, strlen( $ref ), '36 karakter' );
	}
);

qrms_test(
	'P4-3. SSE kart yolu ref_id (ajax-chat + chatbot.js)',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/ajax-chat.php' );
		$js  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/chatbot.js' );
		qrms_assert_contains( "kart['ref_id']", $php, 'sunucu ref_id' );
		qrms_assert_contains( 'qmo_chat_recommendation_yanit_kartlari', $php, 'SSE urunler' );
		qrms_assert_contains( 'urun.ref_id', $js, 'kart ref ile analitik' );
		qrms_assert_contains( 'ref_id', $js, 'ref_id alanı' );
	}
);

qrms_test(
	'P4-4. validated chatbot cart_add attribution',
	function () {
		$wpdb = qrms_p4_wpdb();
		$sid  = qrms_p4_session();
		$ref  = '11111111-2222-3333-4444-555555555555';
		QMO_Chatbot_DB::recommendation_event_ekle( $ref, QMO_Chatbot_DB::REC_EVENT_SHOWN, 10, $sid, 'kural' );
		QMO_Chatbot_DB::recommendation_sepet_olaylari_isle(
			array(
				array(
					'tip'      => 'cart_add',
					'item_id'  => 10,
					'ref_id'   => $ref,
				),
			),
			$sid
		);
		$cart = 0;
		foreach ( $wpdb->rec_events as $row ) {
			if ( 'cart_add' === $row['event_type'] && $ref === $row['ref_id'] ) {
				++$cart;
			}
		}
		qrms_assert_same( 1, $cart, 'cart_add event' );
	}
);

qrms_test(
	'P4-5. normal menu cart_add recommendation attribution yok (oneri_durum kaldırıldı)',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/ajax-sepet-analitik.php' );
		qrms_assert_false( false !== strpos( $php, 'qmo_chatbot_oneri_durum_sessiz' ), 'legacy mutate yok' );
		$wpdb = qrms_p4_wpdb();
		$sid  = qrms_p4_session();
		QMO_Chatbot_DB::recommendation_sepet_olaylari_isle(
			array( array( 'tip' => 'cart_add', 'item_id' => 10 ) ),
			$sid
		);
		qrms_assert_same( 0, count( $wpdb->rec_events ), 'ref yok → event yok' );
	}
);

qrms_test(
	'P4-6. yanlış product_id + ref_id → attribution yok',
	function () {
		$wpdb = qrms_p4_wpdb();
		$sid  = qrms_p4_session();
		$ref  = '22222222-3333-4444-5555-666666666666';
		QMO_Chatbot_DB::recommendation_event_ekle( $ref, QMO_Chatbot_DB::REC_EVENT_SHOWN, 10, $sid, 'ai' );
		QMO_Chatbot_DB::recommendation_sepet_olaylari_isle(
			array(
				array(
					'tip'     => 'cart_add',
					'item_id' => 99,
					'ref_id'  => $ref,
				),
			),
			$sid
		);
		qrms_assert_same( 1, count( $wpdb->rec_events ), 'yalnızca shown' );
	}
);

qrms_test(
	'P4-7. yanlış session → attribution yok',
	function () {
		$wpdb = qrms_p4_wpdb();
		$sid  = qrms_p4_session();
		$ref  = '33333333-4444-5555-6666-777777777777';
		QMO_Chatbot_DB::recommendation_event_ekle( $ref, QMO_Chatbot_DB::REC_EVENT_SHOWN, 10, $sid, 'ai' );
		QMO_Chatbot_DB::recommendation_sepet_olaylari_isle(
			array(
				array(
					'tip'     => 'cart_add',
					'item_id' => 10,
					'ref_id'  => $ref,
				),
			),
			's_' . md5( 'baska_oturum' )
		);
		qrms_assert_same( 1, count( $wpdb->rec_events ), 'shown only' );
	}
);

qrms_test(
	'P4-8. iki recommendation A/B — tıklanan ref attribution',
	function () {
		$wpdb = qrms_p4_wpdb();
		$sid  = qrms_p4_session();
		$ref_a = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
		$ref_b = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
		QMO_Chatbot_DB::recommendation_event_ekle( $ref_a, QMO_Chatbot_DB::REC_EVENT_SHOWN, 10, $sid, 'ai' );
		QMO_Chatbot_DB::recommendation_event_ekle( $ref_b, QMO_Chatbot_DB::REC_EVENT_SHOWN, 10, $sid, 'ai' );
		QMO_Chatbot_DB::recommendation_sepet_olaylari_isle(
			array(
				array(
					'tip'     => 'cart_add',
					'item_id' => 10,
					'ref_id'  => $ref_a,
				),
			),
			$sid
		);
		$cart_refs = array();
		foreach ( $wpdb->rec_events as $row ) {
			if ( 'cart_add' === $row['event_type'] ) {
				$cart_refs[] = $row['ref_id'];
			}
		}
		qrms_assert_same( array( $ref_a ), $cart_refs, 'yalnızca A' );
	}
);

qrms_test(
	'P4-9. observational order_sent join',
	function () {
		$wpdb = qrms_p4_wpdb();
		$sid  = qrms_p4_session();
		$ref  = '44444444-5555-6666-7777-888888888888';
		QMO_Chatbot_DB::recommendation_event_ekle( $ref, QMO_Chatbot_DB::REC_EVENT_SHOWN, 10, $sid, 'ai' );
		QMO_Chatbot_DB::recommendation_sepet_olaylari_isle(
			array(
				array(
					'tip'     => 'cart_add',
					'item_id' => 10,
					'ref_id'  => $ref,
				),
			),
			$sid
		);
		$cart_at = '';
		foreach ( $wpdb->rec_events as $row ) {
			if ( 'cart_add' === $row['event_type'] ) {
				$cart_at = $row['created_at'];
			}
		}
		$wpdb->store[] = array(
			'id'         => 1,
			'event_type' => 'order_sent',
			'session_id' => $sid,
			'item_id'    => 10,
			'created_at' => $cart_at,
		);
		qrms_assert_true( QMO_Chatbot_DB::recommendation_gozlemsel_order_sent_var_mi( $ref ), 'join' );
	}
);

qrms_test(
	'P4-10. cart_add yok → observational order yok',
	function () {
		$wpdb = qrms_p4_wpdb();
		$sid  = qrms_p4_session();
		$ref  = '55555555-6666-7777-8888-999999999999';
		QMO_Chatbot_DB::recommendation_event_ekle( $ref, QMO_Chatbot_DB::REC_EVENT_SHOWN, 10, $sid, 'ai' );
		$wpdb->store[] = array(
			'id'         => 1,
			'event_type' => 'order_sent',
			'session_id' => $sid,
			'item_id'    => 10,
			'created_at' => '2026-09-26 12:00:00',
		);
		qrms_assert_false( QMO_Chatbot_DB::recommendation_gozlemsel_order_sent_var_mi( $ref ), 'cart yok' );
	}
);

qrms_test(
	'P4-11. recommendation X order product Y → yok',
	function () {
		$wpdb = qrms_p4_wpdb();
		$sid  = qrms_p4_session();
		$ref  = '66666666-7777-8888-9999-aaaaaaaaaaaa';
		QMO_Chatbot_DB::recommendation_event_ekle( $ref, QMO_Chatbot_DB::REC_EVENT_SHOWN, 10, $sid, 'ai' );
		QMO_Chatbot_DB::recommendation_sepet_olaylari_isle(
			array(
				array(
					'tip'     => 'cart_add',
					'item_id' => 10,
					'ref_id'  => $ref,
				),
			),
			$sid
		);
		$cart_at = '2026-09-26 11:00:00';
		foreach ( $wpdb->rec_events as $row ) {
			if ( 'cart_add' === $row['event_type'] ) {
				$cart_at = $row['created_at'];
			}
		}
		$wpdb->store[] = array(
			'id'         => 1,
			'event_type' => 'order_sent',
			'session_id' => $sid,
			'item_id'    => 99,
			'created_at' => $cart_at,
		);
		qrms_assert_false( QMO_Chatbot_DB::recommendation_gozlemsel_order_sent_var_mi( $ref ), 'ürün farklı' );
	}
);

qrms_test(
	'P4-12. legacy oneri_log API duruyor',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/class-db.php' );
		qrms_assert_contains( 'function oneri_logla', $php, 'legacy insert' );
		qrms_assert_contains( 'function oneri_durum_guncelle', $php, 'legacy update' );
	}
);

qrms_test(
	'P4-13. sepet analitik hâlâ qmo_analitik_yaz kullanıyor',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/ajax-sepet-analitik.php' );
		qrms_assert_contains( 'qmo_analitik_yaz', $php, 'cart analytics' );
		qrms_assert_contains( 'wp_send_json_success', $php, 'best-effort success' );
	}
);

qrms_test(
	'P4-14. cart_add canonical session_id yazılıyor',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/ajax-sepet-analitik.php' );
		qrms_assert_contains( 'qmo_masa_session_id', $php, 'canonical session' );
		qrms_assert_contains( "\$kayit['session_id'] = \$session_id", $php, 'analytics alanı' );
	}
);

qrms_test(
	'P4-15. i_ session recommendation event reddediliyor',
	function () {
		$wpdb = qrms_p4_wpdb();
		qrms_assert_false(
			QMO_Chatbot_DB::recommendation_event_ekle( '77777777-8888-9999-aaaa-bbbbbbbbbbbb', QMO_Chatbot_DB::REC_EVENT_SHOWN, 10, 'i_' . md5( '1.2.3.4' ), 'ai' ),
			'i_ reddi'
		);
		qrms_assert_same( 0, count( $wpdb->rec_events ), 'insert yok' );
	}
);

qrms_test(
	'P4-16. sahte ref_id reddediliyor',
	function () {
		$wpdb = qrms_p4_wpdb();
		$sid  = qrms_p4_session();
		QMO_Chatbot_DB::recommendation_sepet_olaylari_isle(
			array(
				array(
					'tip'     => 'cart_add',
					'item_id' => 10,
					'ref_id'  => 'fake-ref-not-in-db-123456789012345',
				),
			),
			$sid
		);
		qrms_assert_same( 0, count( $wpdb->rec_events ), 'shown yok → cart yok' );
	}
);

qrms_test(
	'P4-17. Phase 4 dosyaları yüklü',
	function () {
		qrms_assert_true( class_exists( 'QMO_Chatbot_DB' ), 'DB sınıfı' );
		qrms_assert_true( method_exists( 'QMO_Chatbot_DB', 'recommendation_events_tablosu' ), 'tablo metodu' );
	}
);

qrms_test(
	'P4-18. bot sipariş yolu Phase 4 sepet olayına ref eklemez',
	function () {
		$order = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/ajax-order.php' );
		qrms_assert_contains( 'qmo_chatbot_oneri_durum_sessiz', $order, 'legacy bot sipariş korunur' );
		qrms_assert_false( false !== strpos( $order, 'recommendation_event' ), 'Phase4 event bot yolunda yok' );
		$analitik = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/ajax-sepet-analitik.php' );
		qrms_assert_contains( 'recommendation_sepet_olaylari_isle', $analitik, 'yalnız sepet olay' );
	}
);
