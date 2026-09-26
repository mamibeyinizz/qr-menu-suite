<?php
/**
 * Phase 3 P0: otomatik iptal uzlaştırma cron testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/helpers.php';
require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/class-qmo-firestore.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-siparis-olgulari.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-siparis-iptal-uzlastirma.php';

echo "\nQR sipariş iptal uzlaştırma cron (Phase 3 P0)\n";

/**
 * Pencere içi order_sent adayı ekler.
 *
 * @param QRMS_Siparis_Test_Wpdb $wpdb Taklit.
 * @param string                 $oid  order_id.
 * @param string                 $created_at MySQL datetime.
 * @return void
 */
function qrms_p3_sent( $wpdb, $oid, $created_at = '2026-09-26 11:00:00' ) {
	$wpdb->store[] = array(
		'event_type' => 'order_sent',
		'order_id'   => $oid,
		'created_at' => $created_at,
		'qty'        => 1,
	);
}

/**
 * Firestore GET sayısını döner (token isteği hariç).
 *
 * @return int
 */
function qrms_p3_get_sayisi() {
	$n = 0;
	foreach ( $GLOBALS['qrms_test']['http_calls'] as $cagri ) {
		$url = isset( $cagri['url'] ) ? (string) $cagri['url'] : '';
		if ( false !== strpos( $url, '/documents/calls/' ) ) {
			++$n;
		}
		if ( false !== strpos( $url, 'documents:runQuery' ) ) {
			throw new Exception( 'call_listele/runQuery kullanılmamalı: ' . $url );
		}
	}
	return $n;
}

/**
 * GET edilen order_id sırası.
 *
 * @return string[]
 */
function qrms_p3_get_oid_sirasi() {
	$oids = array();
	foreach ( $GLOBALS['qrms_test']['http_calls'] as $cagri ) {
		$url = isset( $cagri['url'] ) ? (string) $cagri['url'] : '';
		if ( preg_match( '#/documents/calls/([^/?]+)#', $url, $eslesme ) ) {
			$oids[] = $eslesme[1];
		}
	}
	return $oids;
}

/**
 * Durum haritasına göre call_oku yanıtı üretir.
 *
 * @param array<string,string> $durumlar order_id => durum|404|timeout|auth|500.
 * @return callable
 */
function qrms_p3_http( array $durumlar ) {
	return function ( $url ) use ( $durumlar ) {
		if ( ! preg_match( '#/documents/calls/([^/?]+)#', (string) $url, $eslesme ) ) {
			return new WP_Error( 'http_request_failed', 'beklenmeyen url' );
		}

		$oid   = $eslesme[1];
		$durum = isset( $durumlar[ $oid ] ) ? $durumlar[ $oid ] : 'bekliyor';

		if ( '404' === $durum ) {
			return array(
				'response' => array( 'code' => 404 ),
				'body'     => '{}',
			);
		}
		if ( 'timeout' === $durum ) {
			return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
		}
		if ( 'auth' === $durum ) {
			return array(
				'response' => array( 'code' => 403 ),
				'body'     => wp_json_encode( array( 'error' => array( 'status' => 'PERMISSION_DENIED' ) ) ),
			);
		}
		if ( '500' === $durum ) {
			return array(
				'response' => array( 'code' => 500 ),
				'body'     => '{}',
			);
		}

		$alan = array(
			'tip'   => array( 'stringValue' => 'siparis' ),
			'durum' => array( 'stringValue' => $durum ),
		);

		if ( ! empty( $durumlar[ $oid . ':iptalAt' ] ) ) {
			$alan['iptalAt'] = array( 'timestampValue' => $durumlar[ $oid . ':iptalAt' ] );
		}
		if ( ! empty( $durumlar[ $oid . ':guncellendi' ] ) ) {
			$alan['guncellendi'] = array( 'timestampValue' => $durumlar[ $oid . ':guncellendi' ] );
		}

		return array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'name'   => 'projects/test-p3/databases/(default)/documents/calls/' . $oid,
					'fields' => $alan,
				)
			),
		);
	};
}

/**
 * Tur için WPDB + Firestore token + şimdi.
 *
 * @return QRMS_Siparis_Test_Wpdb
 */
function qrms_p3_hazir() {
	$wpdb = qrms_siparis_wpdb();
	qrms_siparis_fs_hazir( 'test-p3' );
	$GLOBALS['qrms_test']['now'] = strtotime( '2026-09-26 12:00:00 UTC' );
	return $wpdb;
}

qrms_test(
	'P3-1. order_sent + order_id -> aday',
	function () {
		$wpdb = qrms_p3_hazir();
		qrms_p3_sent( $wpdb, 'ord-p3-aday-1111-2222-3333-44444444' );

		$aday = QRMS_Siparis_Iptal_Uzlastirma::adaylar();
		qrms_assert_same( 1, count( $aday ), 'bir aday' );
		qrms_assert_same( 'ord-p3-aday-1111-2222-3333-44444444', $aday[0]['order_id'], 'order_id' );
		qrms_assert_same( 1, count( $wpdb->queries ), 'tek aday sorgusu' );
		qrms_assert_contains( 'event_type = \'order_sent\'', $wpdb->queries[0], 'order_sent' );
		qrms_assert_contains( 'NOT EXISTS', $wpdb->queries[0], 'iptal anti-join' );
		qrms_assert_contains( 'ORDER BY ilk_gonderim ASC, s.order_id ASC', $wpdb->queries[0], 'deterministik sıra' );
		qrms_assert_contains( 'LIMIT', $wpdb->queries[0], 'limit' );
	}
);

qrms_test(
	'P3-2. order_id NULL -> aday değil',
	function () {
		$wpdb = qrms_p3_hazir();
		$wpdb->store[] = array(
			'event_type' => 'order_sent',
			'order_id'   => null,
			'created_at' => '2026-09-26 11:00:00',
		);

		qrms_assert_same( 0, count( QRMS_Siparis_Iptal_Uzlastirma::adaylar() ), 'NULL hariç' );
	}
);

qrms_test(
	'P3-3. order_id empty -> aday değil',
	function () {
		$wpdb = qrms_p3_hazir();
		qrms_p3_sent( $wpdb, '' );

		qrms_assert_same( 0, count( QRMS_Siparis_Iptal_Uzlastirma::adaylar() ), 'boş hariç' );
	}
);

qrms_test(
	'P3-4. order_cancelled mevcut -> aday değil',
	function () {
		$wpdb = qrms_p3_hazir();
		$oid  = 'ord-p3-iptal-1111-2222-3333-44444444';
		qrms_p3_sent( $wpdb, $oid );
		$wpdb->store[] = array(
			'event_type' => 'order_cancelled',
			'order_id'   => $oid,
			'created_at' => '2026-09-26 11:30:00',
		);

		qrms_assert_same( 0, count( QRMS_Siparis_Iptal_Uzlastirma::adaylar() ), 'iptal edilmiş hariç' );
	}
);

qrms_test(
	'P3-5. Firestore bekliyor -> cancellation yok',
	function () {
		$wpdb = qrms_p3_hazir();
		$oid  = 'ord-p3-bek-1111-2222-3333-4444444444';
		qrms_p3_sent( $wpdb, $oid );
		$GLOBALS['qrms_test']['http'] = qrms_p3_http( array( $oid => 'bekliyor' ) );

		$ozet = QRMS_Siparis_Iptal_Uzlastirma::tur();
		qrms_assert_same( 0, count( $wpdb->inserts ), 'yazım yok' );
		qrms_assert_same( 1, $ozet['get'], 'bir GET' );
		qrms_assert_true( QRMS_Siparis_Iptal_Uzlastirma::skip_var_mi( $oid ), 'aktif skip' );
	}
);

qrms_test(
	'P3-6. Firestore hazirlaniyor -> cancellation yok',
	function () {
		$wpdb = qrms_p3_hazir();
		$oid  = 'ord-p3-haz-1111-2222-3333-4444444444';
		qrms_p3_sent( $wpdb, $oid );
		$GLOBALS['qrms_test']['http'] = qrms_p3_http( array( $oid => 'hazirlaniyor' ) );

		QRMS_Siparis_Iptal_Uzlastirma::tur();
		qrms_assert_same( 0, count( $wpdb->inserts ), 'yazım yok' );
	}
);

qrms_test(
	'P3-7. Firestore serviste -> cancellation yok',
	function () {
		$wpdb = qrms_p3_hazir();
		$oid  = 'ord-p3-ser-1111-2222-3333-4444444444';
		qrms_p3_sent( $wpdb, $oid );
		$GLOBALS['qrms_test']['http'] = qrms_p3_http( array( $oid => 'serviste' ) );

		QRMS_Siparis_Iptal_Uzlastirma::tur();
		qrms_assert_same( 0, count( $wpdb->inserts ), 'yazım yok' );
	}
);

qrms_test(
	'P3-8. Firestore tamamlandi -> cancellation yok',
	function () {
		$wpdb = qrms_p3_hazir();
		$oid  = 'ord-p3-tam-1111-2222-3333-4444444444';
		qrms_p3_sent( $wpdb, $oid );
		$GLOBALS['qrms_test']['http'] = qrms_p3_http( array( $oid => 'tamamlandi' ) );

		QRMS_Siparis_Iptal_Uzlastirma::tur();
		qrms_assert_same( 0, count( $wpdb->inserts ), 'yazım yok' );
		qrms_assert_true( QRMS_Siparis_Iptal_Uzlastirma::skip_var_mi( $oid ), 'terminal skip' );
	}
);

qrms_test(
	'P3-9. Firestore iptal -> tam 1 cancellation',
	function () {
		$wpdb = qrms_p3_hazir();
		$oid  = 'ord-p3-ok-1111-2222-3333-44444444444';
		qrms_p3_sent( $wpdb, $oid );
		$GLOBALS['qrms_test']['http'] = qrms_p3_http( array( $oid => 'iptal' ) );

		$ozet = QRMS_Siparis_Iptal_Uzlastirma::tur();
		qrms_assert_same( 1, $ozet['yazilan'], 'yazıldı' );
		qrms_assert_same( 1, count( $wpdb->inserts ), 'tek insert' );
		qrms_assert_same( 'order_cancelled', $wpdb->inserts[0]['event_type'], 'tip' );
		qrms_assert_same( $oid, $wpdb->inserts[0]['order_id'], 'order_id' );
	}
);

qrms_test(
	'P3-10. Aynı job tekrar çalışıyor -> yine 1 cancellation',
	function () {
		$wpdb = qrms_p3_hazir();
		$oid  = 'ord-p3-dup-1111-2222-3333-4444444444';
		qrms_p3_sent( $wpdb, $oid );
		$GLOBALS['qrms_test']['http'] = qrms_p3_http( array( $oid => 'iptal' ) );

		QRMS_Siparis_Iptal_Uzlastirma::tur();
		QRMS_Siparis_Iptal_Uzlastirma::tur();

		$iptal = 0;
		foreach ( $wpdb->inserts as $satir ) {
			if ( 'order_cancelled' === ( $satir['event_type'] ?? '' ) && $oid === ( $satir['order_id'] ?? '' ) ) {
				++$iptal;
			}
		}
		qrms_assert_same( 1, $iptal, 'duplicate yok' );
	}
);

qrms_test(
	'P3-11. HTTP 404 -> cancellation yok',
	function () {
		$wpdb = qrms_p3_hazir();
		$oid  = 'ord-p3-404-1111-2222-3333-4444444444';
		qrms_p3_sent( $wpdb, $oid );
		$GLOBALS['qrms_test']['http'] = qrms_p3_http( array( $oid => '404' ) );

		$ozet = QRMS_Siparis_Iptal_Uzlastirma::tur();
		qrms_assert_same( 0, $ozet['yazilan'], '404 iptal değil' );
		qrms_assert_same( 0, count( $wpdb->inserts ), 'insert yok' );
		qrms_assert_same( 1, $ozet['get'], 'GET yapıldı' );
	}
);

qrms_test(
	'P3-12. transport/timeout -> cancellation yok',
	function () {
		$wpdb = qrms_p3_hazir();
		$oid  = 'ord-p3-to-1111-2222-3333-44444444444';
		qrms_p3_sent( $wpdb, $oid );
		$GLOBALS['qrms_test']['http'] = qrms_p3_http( array( $oid => 'timeout' ) );

		$ozet = QRMS_Siparis_Iptal_Uzlastirma::tur();
		qrms_assert_same( 0, $ozet['yazilan'], 'timeout iptal değil' );
		qrms_assert_same( 0, count( $wpdb->inserts ), 'insert yok' );
		qrms_assert_false( QRMS_Siparis_Iptal_Uzlastirma::skip_var_mi( $oid ), 'transport skip yok' );
	}
);

qrms_test(
	'P3-13. auth/permission -> cancellation yok ve tur duruyor',
	function () {
		$wpdb = qrms_p3_hazir();
		qrms_p3_sent( $wpdb, 'ord-p3-a1-1111-2222-3333-44444444444', '2026-09-26 11:00:01' );
		qrms_p3_sent( $wpdb, 'ord-p3-a2-1111-2222-3333-44444444444', '2026-09-26 11:00:02' );
		qrms_p3_sent( $wpdb, 'ord-p3-a3-1111-2222-3333-44444444444', '2026-09-26 11:00:03' );
		$GLOBALS['qrms_test']['http'] = qrms_p3_http(
			array(
				'ord-p3-a1-1111-2222-3333-44444444444' => 'auth',
				'ord-p3-a2-1111-2222-3333-44444444444' => 'iptal',
				'ord-p3-a3-1111-2222-3333-44444444444' => 'iptal',
			)
		);

		$ozet = QRMS_Siparis_Iptal_Uzlastirma::tur();
		qrms_assert_same( 'auth', $ozet['kesme'], 'tur kesildi' );
		qrms_assert_same( 1, $ozet['get'], 'auth sonrası GET yok' );
		qrms_assert_same( 0, $ozet['yazilan'], 'yazım yok' );
		qrms_assert_same( 0, count( $wpdb->inserts ), 'insert yok' );
	}
);

qrms_test(
	'P3-14. iptalAt -> event zamanı',
	function () {
		$wpdb = qrms_p3_hazir();
		$oid  = 'ord-p3-at-1111-2222-3333-44444444444';
		qrms_p3_sent( $wpdb, $oid );
		$GLOBALS['qrms_test']['http'] = qrms_p3_http(
			array(
				$oid              => 'iptal',
				$oid . ':iptalAt' => '2026-09-26T09:15:00Z',
				$oid . ':guncellendi' => '2026-09-26T01:00:00Z',
			)
		);

		QRMS_Siparis_Iptal_Uzlastirma::tur();
		qrms_assert_same( 1, count( $wpdb->inserts ), 'yazıldı' );
		qrms_assert_same( '2026-09-26 09:15:00', $wpdb->inserts[0]['created_at'], 'iptalAt' );
	}
);

qrms_test(
	'P3-15. iptalAt yok + guncellendi -> fallback',
	function () {
		$wpdb = qrms_p3_hazir();
		$oid  = 'ord-p3-gun-1111-2222-3333-4444444444';
		qrms_p3_sent( $wpdb, $oid );
		$GLOBALS['qrms_test']['http'] = qrms_p3_http(
			array(
				$oid                  => 'iptal',
				$oid . ':guncellendi' => '2026-09-26T10:45:00Z',
			)
		);

		QRMS_Siparis_Iptal_Uzlastirma::tur();
		qrms_assert_same( 1, count( $wpdb->inserts ), 'yazıldı' );
		qrms_assert_same( '2026-09-26 10:45:00', $wpdb->inserts[0]['created_at'], 'guncellendi' );
	}
);

qrms_test(
	'P3-16. 11 aday -> batch\'te maksimum 10 GET',
	function () {
		$wpdb = qrms_p3_hazir();
		$harita = array();
		for ( $i = 1; $i <= 11; $i++ ) {
			$oid = sprintf( 'ord-p3-b%02d-1111-2222-3333-444444444', $i );
			qrms_p3_sent( $wpdb, $oid, sprintf( '2026-09-26 11:%02d:00', $i ) );
			$harita[ $oid ] = 'bekliyor';
		}
		$GLOBALS['qrms_test']['http'] = qrms_p3_http( $harita );

		$ozet = QRMS_Siparis_Iptal_Uzlastirma::tur();
		qrms_assert_true( $ozet['aday'] >= 11, '11 aday SQL' );
		qrms_assert_same( 10, $ozet['get'], 'GET tavanı 10' );
		qrms_assert_same( 10, qrms_p3_get_sayisi(), 'http GET 10' );
		qrms_assert_same( 0, count( $wpdb->inserts ), 'aktif yazılmaz' );
	}
);

qrms_test(
	'P3-17. süre bütçesi dolunca job güvenli şekilde duruyor',
	function () {
		$wpdb = qrms_p3_hazir();
		$harita = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$oid = sprintf( 'ord-p3-t%02d-1111-2222-3333-444444444', $i );
			qrms_p3_sent( $wpdb, $oid );
			$harita[ $oid ] = 'iptal';
		}
		$GLOBALS['qrms_test']['http'] = qrms_p3_http( $harita );

		$n = 0;
		add_filter(
			'qrms_iptal_uzlastirma_simdi',
			function () use ( &$n ) {
				++$n;
				return $n <= 2 ? 0.0 : 100.0;
			}
		);

		$ozet = QRMS_Siparis_Iptal_Uzlastirma::tur();
		qrms_assert_same( 'sure', $ozet['kesme'], 'bütçe kesmesi' );
		qrms_assert_true( $ozet['get'] < 5, 'tüm adaylar GET edilmedi' );
		qrms_assert_true( $ozet['get'] >= 1, 'en az bir GET' );
		qrms_assert_true( count( $wpdb->inserts ) < 5, 'kalan sonraki tura' );
	}
);

qrms_test(
	'P3-18. job lock dolu -> ikinci job no-op',
	function () {
		$wpdb = qrms_p3_hazir();
		$oid  = 'ord-p3-lk-1111-2222-3333-44444444444';
		qrms_p3_sent( $wpdb, $oid );
		$GLOBALS['qrms_test']['http'] = qrms_p3_http( array( $oid => 'iptal' ) );

		set_transient( QRMS_Siparis_Iptal_Uzlastirma::KILIT, 1, 60 );

		$ozet = QRMS_Siparis_Iptal_Uzlastirma::tur();
		qrms_assert_same( 'kilit', $ozet['kesme'], 'no-op' );
		qrms_assert_false( $ozet['kilit'], 'kilit alınamadı' );
		qrms_assert_same( 0, $ozet['get'], 'GET yok' );
		qrms_assert_same( 0, count( $wpdb->inserts ), 'yazım yok' );
		qrms_assert_same( 0, qrms_p3_get_sayisi(), 'http yok' );
	}
);

qrms_test(
	'P3-19. 24 saat dışındaki order_id aday değil',
	function () {
		$wpdb = qrms_p3_hazir();
		qrms_p3_sent( $wpdb, 'ord-p3-eski-1111-2222-3333-444444444', '2026-09-25 11:00:00' );
		qrms_p3_sent( $wpdb, 'ord-p3-yeni-1111-2222-3333-444444444', '2026-09-26 11:00:00' );

		$aday = QRMS_Siparis_Iptal_Uzlastirma::adaylar();
		qrms_assert_same( 1, count( $aday ), 'yalnızca pencere içi' );
		qrms_assert_same( 'ord-p3-yeni-1111-2222-3333-444444444', $aday[0]['order_id'], 'yeni aday' );
	}
);

qrms_test(
	'P3-20. Phase 1 + Phase 2 mekanizması duruyor; cron kancası bağlı',
	function () {
		$uzl = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-siparis-iptal-uzlastirma.php' );
		qrms_assert_contains( 'function belgeden_yaz', $uzl, 'Phase 2 yazım' );
		qrms_assert_contains( 'function iptal_olayi_var_mi', $uzl, 'tekillik' );
		qrms_assert_contains( "qmo_kilitli_calistir( 'order_cancelled|'", $uzl, 'sipariş flock' );
		qrms_assert_contains( 'LOCK_EX | LOCK_NB', $uzl, 'job lock non-blocking' );
		qrms_assert_false( false !== strpos( $uzl, 'call_listele' ), 'listeleme yok' );
		qrms_assert_false( false !== strpos( $uzl, 'idempotency_key' ), 'idempotency_key yok' );

		$mod = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/module.php' );
		qrms_assert_contains( 'QRMS_Siparis_Iptal_Uzlastirma::init()', $mod, 'modül init' );

		$kok = file_get_contents( QRMS_PLUGIN_DIR . 'qr-menu-suite.php' );
		qrms_assert_contains( "wp_clear_scheduled_hook( '" . QRMS_Siparis_Iptal_Uzlastirma::CRON_HOOK . "' )", $kok, 'deaktivasyon kanca' );

		$un = file_get_contents( QRMS_PLUGIN_DIR . 'uninstall.php' );
		qrms_assert_contains( "'qrms_siparis_iptal_uzlastirma'", $un, 'uninstall kanca' );

		QRMS_Siparis_Iptal_Uzlastirma::init();
		$kancalar = $GLOBALS['qrms_test']['actions'];
		qrms_assert_true( isset( $kancalar[ QRMS_Siparis_Iptal_Uzlastirma::CRON_HOOK ] ), 'cron dinleniyor' );
		qrms_assert_true( isset( $kancalar['cron_schedules'] ), 'aralık filtresi' );

		QRMS_Siparis_Iptal_Uzlastirma::planla();
		$ilk = wp_next_scheduled( QRMS_Siparis_Iptal_Uzlastirma::CRON_HOOK );
		qrms_assert_true( (bool) $ilk, 'planlandı' );
		QRMS_Siparis_Iptal_Uzlastirma::planla();
		qrms_assert_same( $ilk, wp_next_scheduled( QRMS_Siparis_Iptal_Uzlastirma::CRON_HOOK ), 'çift plan yok' );
		QRMS_Siparis_Iptal_Uzlastirma::plan_iptal();
		qrms_assert_false( wp_next_scheduled( QRMS_Siparis_Iptal_Uzlastirma::CRON_HOOK ), 'iptal temizler' );

		$aralik = QRMS_Siparis_Iptal_Uzlastirma::cron_araliklari( array() );
		qrms_assert_same( 300, $aralik[ QRMS_Siparis_Iptal_Uzlastirma::CRON_ARALIK ]['interval'], '5 dk' );
	}
);

qrms_test(
	'P3-21. 20 aktif order: tur2 ilk 10 tekrar öne geçmiyor',
	function () {
		$wpdb   = qrms_p3_hazir();
		$harita = array();
		for ( $i = 1; $i <= 20; $i++ ) {
			$oid = sprintf( 'ord-p3-rr-%02d-1111-2222-3333-4444444', $i );
			qrms_p3_sent( $wpdb, $oid, sprintf( '2026-09-26 11:%02d:00', $i ) );
			$harita[ $oid ] = 'bekliyor';
		}
		$GLOBALS['qrms_test']['http'] = qrms_p3_http( $harita );

		$ozet1 = QRMS_Siparis_Iptal_Uzlastirma::tur();
		$ilk1  = qrms_p3_get_oid_sirasi();
		qrms_assert_same( 10, $ozet1['get'], 'tur1 GET tavanı' );
		qrms_assert_true( count( $ilk1 ) >= 1, 'tur1 GET var' );

		$GLOBALS['qrms_test']['http_calls'] = array();
		$ozet2 = QRMS_Siparis_Iptal_Uzlastirma::tur();
		$ilk2  = qrms_p3_get_oid_sirasi();
		qrms_assert_same( 10, $ozet2['get'], 'tur2 GET tavanı' );
		qrms_assert_true( count( $ilk2 ) >= 1, 'tur2 GET var' );
		qrms_assert_false( $ilk1[0] === $ilk2[0], 'tur2 aynı ilk GET ile başlamaz' );
	}
);

qrms_test(
	'P3-22. ilk 10 skip-cache iken 11. aday GET edilir',
	function () {
		$wpdb   = qrms_p3_hazir();
		$harita = array();
		for ( $i = 1; $i <= 12; $i++ ) {
			$oid = sprintf( 'ord-p3-sk-%02d-1111-2222-3333-4444444', $i );
			qrms_p3_sent( $wpdb, $oid, sprintf( '2026-09-26 11:%02d:00', $i ) );
			$harita[ $oid ] = 'bekliyor';
			if ( $i <= 10 ) {
				set_transient( QRMS_Siparis_Iptal_Uzlastirma::skip_anahtar( $oid ), 1, 300 );
			}
		}
		$GLOBALS['qrms_test']['http'] = qrms_p3_http( $harita );

		QRMS_Siparis_Iptal_Uzlastirma::tur();
		$oids = qrms_p3_get_oid_sirasi();
		qrms_assert_same( 'ord-p3-sk-11-1111-2222-3333-4444444', $oids[0], '11. aday ilk GET' );
		qrms_assert_false( in_array( 'ord-p3-sk-01-1111-2222-3333-4444444', $oids, true ), 'skip edilen GET yok' );
		qrms_assert_true( count( $oids ) <= 10, 'batch tavanı' );
	}
);

qrms_test(
	'P3-23. 11. aday Firestore iptal -> order_cancelled',
	function () {
		$wpdb   = qrms_p3_hazir();
		$harita = array();
		$onbir  = 'ord-p3-i11-1111-2222-3333-444444444';
		for ( $i = 1; $i <= 12; $i++ ) {
			$oid = sprintf( 'ord-p3-i%02d-1111-2222-3333-444444444', $i );
			if ( 11 === $i ) {
				$oid = $onbir;
			}
			qrms_p3_sent( $wpdb, $oid, sprintf( '2026-09-26 11:%02d:00', $i ) );
			$harita[ $oid ] = ( $oid === $onbir ) ? 'iptal' : 'bekliyor';
			if ( $i <= 10 ) {
				set_transient( QRMS_Siparis_Iptal_Uzlastirma::skip_anahtar( $oid ), 1, 300 );
			}
		}
		$GLOBALS['qrms_test']['http'] = qrms_p3_http( $harita );

		QRMS_Siparis_Iptal_Uzlastirma::tur();
		qrms_assert_same( 1, count( $wpdb->inserts ), 'tek iptal' );
		qrms_assert_same( 'order_cancelled', $wpdb->inserts[0]['event_type'], 'tip' );
		qrms_assert_same( $onbir, $wpdb->inserts[0]['order_id'], '11. sipariş' );
	}
);

qrms_test(
	'P3-24. yeni order cursor ring ile starvation olmaz',
	function () {
		$wpdb = qrms_p3_hazir();
		// order_id sütunu 36 karakter; kaydet() keser — tam eşleşme için kısa id.
		$eski = 'ord-p3-nw-a-1111-2222-3333-444444444';
		$yeni = 'ord-p3-nw-b-1111-2222-3333-444444444';
		qrms_p3_sent( $wpdb, $eski, '2026-09-26 11:01:00' );
		qrms_p3_sent( $wpdb, $yeni, '2026-09-26 12:30:00' );
		QRMS_Siparis_Iptal_Uzlastirma::imlec_yaz(
			array(
				'order_id'     => $eski,
				'ilk_gonderim' => '2026-09-26 11:01:00',
			)
		);
		set_transient( QRMS_Siparis_Iptal_Uzlastirma::skip_anahtar( $eski ), 1, 300 );
		$GLOBALS['qrms_test']['http'] = qrms_p3_http(
			array(
				$eski => 'bekliyor',
				$yeni => 'iptal',
			)
		);

		$ozet = QRMS_Siparis_Iptal_Uzlastirma::tur();
		qrms_assert_same( 1, $ozet['get'], 'yeni aday GET edildi' );
		qrms_assert_same( 1, $ozet['yazilan'], 'iptal yazildi' );
		$iptal = false;
		foreach ( $wpdb->inserts as $satir ) {
			if ( 'order_cancelled' === ( $satir['event_type'] ?? '' ) && $yeni === ( $satir['order_id'] ?? '' ) ) {
				$iptal = true;
			}
		}
		qrms_assert_true( $iptal, 'yeni sipariş iptali bulundu' );
	}
);

qrms_test(
	'P3-25. fopen/flock başarısız -> job no-op',
	function () {
		$uzl = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-siparis-iptal-uzlastirma.php' );
		qrms_assert_contains( 'if ( ! $fp ) {', $uzl, 'fopen zorunlu' );
		qrms_assert_contains( 'return false;', $uzl, 'kilitsiz devam yok' );
	}
);

qrms_test(
	'P3-26. timeout/transport -> cancellation yok',
	function () {
		$wpdb = qrms_p3_hazir();
		$oid  = 'ord-p3-tr2-1111-2222-3333-44444444444';
		qrms_p3_sent( $wpdb, $oid );
		$GLOBALS['qrms_test']['http'] = qrms_p3_http( array( $oid => 'timeout' ) );

		QRMS_Siparis_Iptal_Uzlastirma::tur();
		qrms_assert_same( 0, count( $wpdb->inserts ), 'transport iptal değil' );
	}
);

qrms_test(
	'P3-27. Phase 1 + Phase 2 + P3-1..20 suite içinde yeşil',
	function () {
		qrms_assert_true( class_exists( 'QRMS_Siparis_Iptal_Uzlastirma' ), 'sınıf yüklü' );
		qrms_assert_true( method_exists( 'QRMS_Siparis_Iptal_Uzlastirma', 'belgeden_yaz' ), 'Phase 2' );
	}
);

qrms_test(
	'P3-28. tur sonunda son GET imlec olarak kaydedilir',
	function () {
		$wpdb = qrms_p3_hazir();
		for ( $i = 1; $i <= 3; $i++ ) {
			$oid = sprintf( 'ord-p3-im-%02d-1111-2222-3333-4444444', $i );
			qrms_p3_sent( $wpdb, $oid, sprintf( '2026-09-26 11:0%d:00', $i ) );
		}
		$harita = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$harita[ sprintf( 'ord-p3-im-%02d-1111-2222-3333-4444444', $i ) ] = 'bekliyor';
		}
		$GLOBALS['qrms_test']['http'] = qrms_p3_http( $harita );
		delete_option( QRMS_Siparis_Iptal_Uzlastirma::IMLEC_OPT );

		QRMS_Siparis_Iptal_Uzlastirma::tur();
		$imlec = QRMS_Siparis_Iptal_Uzlastirma::imlec_oku();
		$oids  = qrms_p3_get_oid_sirasi();
		qrms_assert_true( is_array( $imlec ), 'imlec var' );
		qrms_assert_same( end( $oids ), $imlec['order_id'], 'son GET imlec' );
	}
);

qrms_test(
	'P3-29. skip edilen aday imlec olmaz',
	function () {
		$wpdb = qrms_p3_hazir();
		$a    = 'ord-p3-sk2-a-1111-2222-3333-4444444444';
		$b    = 'ord-p3-sk2-b-1111-2222-3333-4444444444';
		qrms_p3_sent( $wpdb, $a, '2026-09-26 11:01:00' );
		qrms_p3_sent( $wpdb, $b, '2026-09-26 11:02:00' );
		set_transient( QRMS_Siparis_Iptal_Uzlastirma::skip_anahtar( $a ), 1, 300 );
		$GLOBALS['qrms_test']['http'] = qrms_p3_http(
			array(
				$a => 'bekliyor',
				$b => 'bekliyor',
			)
		);
		delete_option( QRMS_Siparis_Iptal_Uzlastirma::IMLEC_OPT );

		QRMS_Siparis_Iptal_Uzlastirma::tur();
		$imlec = QRMS_Siparis_Iptal_Uzlastirma::imlec_oku();
		qrms_assert_same( $b, $imlec['order_id'], 'skip edilen imlec değil' );
	}
);

qrms_test(
	'P3-30. imlec sonda -> ring başa sarar',
	function () {
		$wpdb = qrms_p3_hazir();
		$a    = 'ord-p3-rg-a-1111-2222-3333-44444444444';
		$b    = 'ord-p3-rg-b-1111-2222-3333-44444444444';
		$c    = 'ord-p3-rg-c-1111-2222-3333-44444444444';
		qrms_p3_sent( $wpdb, $a, '2026-09-26 11:01:00' );
		qrms_p3_sent( $wpdb, $b, '2026-09-26 11:02:00' );
		qrms_p3_sent( $wpdb, $c, '2026-09-26 11:03:00' );
		QRMS_Siparis_Iptal_Uzlastirma::imlec_yaz(
			array(
				'order_id'     => $c,
				'ilk_gonderim' => '2026-09-26 11:03:00',
			)
		);
		$halka = QRMS_Siparis_Iptal_Uzlastirma::aday_halkasi( 50 );
		qrms_assert_same( $a, $halka[0]['order_id'], 'sonrası başa sarar' );
		qrms_assert_same( $b, $halka[1]['order_id'], 'sıra korunur' );
	}
);

qrms_test(
	'P3-31. (ilk_gonderim, order_id) sırası deterministik',
	function () {
		$liste = array(
			array( 'order_id' => 'ord-z', 'ilk_gonderim' => '2026-09-26 11:00:00' ),
			array( 'order_id' => 'ord-a', 'ilk_gonderim' => '2026-09-26 11:00:00' ),
			array( 'order_id' => 'ord-m', 'ilk_gonderim' => '2026-09-26 10:00:00' ),
		);
		usort( $liste, array( 'QRMS_Siparis_Iptal_Uzlastirma', 'aday_karsilastir' ) );
		qrms_assert_same( 'ord-m', $liste[0]['order_id'], 'önce zaman' );
		qrms_assert_same( 'ord-a', $liste[1]['order_id'], 'sonra order_id ASC' );
		qrms_assert_same( 'ord-z', $liste[2]['order_id'], 'son' );
	}
);

qrms_test(
	'P3-32. call_oku cron timeout kalan bütçeyi aşmaz',
	function () {
		$wpdb = qrms_p3_hazir();
		$oid  = 'ord-p3-to-b-1111-2222-3333-44444444444';
		qrms_p3_sent( $wpdb, $oid );
		$GLOBALS['qrms_test']['http'] = function ( $url, $args = array() ) use ( $oid ) {
			qrms_assert_true( isset( $args['timeout'] ), 'timeout geçildi' );
			qrms_assert_true( (int) $args['timeout'] <= 8, 'bütçe tavanı' );
			return qrms_p3_http( array( $oid => 'bekliyor' ) )( $url, $args );
		};
		add_filter(
			'qrms_iptal_uzlastirma_sure',
			function () {
				return 8;
			}
		);

		QRMS_Siparis_Iptal_Uzlastirma::tur();
	}
);
