<?php
/**
 * Phase 2 P0: sipariş olguları ve iptal uzlaştırma testleri.
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
require_once QRMS_PLUGIN_DIR . 'modules/qr-servis-paneli/includes/class-qrms-sp-veri.php';

echo "\nQR sipariş olguları + iptal uzlaştırma (Phase 2 P0)\n";

/**
 * Analitik yazımlarını ve iptal varlık sorgusunu bellek içinde tutar.
 */
class QRMS_Siparis_Test_Wpdb {
	public $prefix   = 'wp_';
	public $inserts  = array();
	public $queries  = array();
	public $results  = array();
	public $rows     = array();
	public $store    = array();
	public $next_id  = 1;

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
				if ( '%f' === $m[0] ) {
					return (string) (float) $value;
				}

				return "'" . str_replace( "'", "\\'", (string) $value ) . "'";
			},
			$sql
		);
	}

	public function insert( $table, $data, $format = null ) {
		unset( $format );
		$data['id']       = $this->next_id++;
		$this->inserts[]  = $data;
		$this->store[]    = $data;
		$this->queries[]  = 'INSERT ' . $table;
		return 1;
	}

	public function get_var( $sql ) {
		$this->queries[] = $sql;

		if ( false !== strpos( $sql, "event_type = 'order_cancelled'" ) && preg_match( "/order_id = '([^']+)'/", $sql, $eslesme ) ) {
			foreach ( $this->store as $satir ) {
				if ( 'order_cancelled' === ( $satir['event_type'] ?? '' ) && $eslesme[1] === (string) ( $satir['order_id'] ?? '' ) ) {
					return $satir['id'];
				}
			}
			return null;
		}

		return null;
	}

	public function get_results( $sql, $mode = null ) {
		unset( $mode );
		$this->queries[] = $sql;

		if ( ! empty( $this->results ) ) {
			$next = array_shift( $this->results );
			return is_array( $next ) ? $next : array();
		}

		$cikti = array();
		foreach ( $this->store as $satir ) {
			$cikti[] = $satir;
		}
		return $cikti;
	}

	public function get_row( $sql, $mode = null ) {
		unset( $mode );
		$this->queries[] = $sql;
		return array_shift( $this->rows );
	}
}

/**
 * @return QRMS_Siparis_Test_Wpdb
 */
function qrms_siparis_wpdb() {
	$GLOBALS['wpdb'] = new QRMS_Siparis_Test_Wpdb();
	return $GLOBALS['wpdb'];
}

/**
 * Firestore call_oku için hazır service account + token.
 *
 * @param string $proje Proje kimliği.
 * @return void
 */
function qrms_siparis_fs_hazir( $proje = 'test-p0-olgu' ) {
	update_option(
		'qmo_firebase_sa',
		wp_json_encode(
			array(
				'client_email' => 'svc@test.iam.gserviceaccount.com',
				'private_key'  => 'test',
				'project_id'   => $proje,
			)
		)
	);
	set_transient(
		'qmo_gcp_token_' . substr( md5( QMO_Firestore::SCOPE_DATASTORE ), 0, 12 ),
		'test-token',
		3500
	);
}

qrms_test(
	'1. bir order_id + 3 product line → gerçek sipariş sayısı 1',
	function () {
		$oid = 'ord-aaa-1111-2222-3333-444444444444';
		$ozet = QRMS_Siparis_Olgulari::hesapla(
			array(
				array( 'event_type' => 'order_sent', 'order_id' => $oid, 'qty' => 1, 'unit_price' => 10 ),
				array( 'event_type' => 'order_sent', 'order_id' => $oid, 'qty' => 2, 'unit_price' => 5 ),
				array( 'event_type' => 'order_sent', 'order_id' => $oid, 'qty' => 1, 'unit_price' => 8 ),
			)
		);
		qrms_assert_same( 1, $ozet['siparis_sayisi'], 'COUNT DISTINCT order_id' );
		qrms_assert_same( 'kesin', $ozet['kaynak'], 'kaynak etiketi' );
		qrms_assert_true( $ozet['legacy_haric'], 'legacy hariç' );
	}
);

qrms_test(
	'2. aynı order_id altında unit_price * qty toplamı',
	function () {
		$oid = 'ord-bbb-1111-2222-3333-444444444444';
		$ozet = QRMS_Siparis_Olgulari::hesapla(
			array(
				array( 'event_type' => 'order_sent', 'order_id' => $oid, 'qty' => 2, 'unit_price' => 15.5 ),
				array( 'event_type' => 'order_sent', 'order_id' => $oid, 'qty' => 1, 'unit_price' => 4 ),
			)
		);
		qrms_assert_same( 35.0, $ozet['siparis_tutari'], '2*15.5 + 4' );
	}
);

qrms_test(
	'3. iptal edilmiş order_id normal sipariş sayısına dahil değil',
	function () {
		$ozet = QRMS_Siparis_Olgulari::hesapla(
			array(
				array( 'event_type' => 'order_sent', 'order_id' => 'o-ok', 'qty' => 1, 'unit_price' => 10 ),
				array( 'event_type' => 'order_sent', 'order_id' => 'o-iptal', 'qty' => 1, 'unit_price' => 99 ),
				array( 'event_type' => 'order_cancelled', 'order_id' => 'o-iptal' ),
			)
		);
		qrms_assert_same( 1, $ozet['siparis_sayisi'], 'yalnızca iptal edilmemiş' );
		qrms_assert_same( 1, $ozet['iptal_sayisi'], 'iptal ayrı' );
	}
);

qrms_test(
	'4. iptal edilmiş order_id normal sipariş tutarına dahil değil',
	function () {
		$ozet = QRMS_Siparis_Olgulari::hesapla(
			array(
				array( 'event_type' => 'order_sent', 'order_id' => 'o-ok', 'qty' => 2, 'unit_price' => 10 ),
				array( 'event_type' => 'order_sent', 'order_id' => 'o-iptal', 'qty' => 3, 'unit_price' => 50 ),
				array( 'event_type' => 'order_cancelled', 'order_id' => 'o-iptal' ),
			)
		);
		qrms_assert_same( 20.0, $ozet['siparis_tutari'], 'iptal tutarı düşer' );
		qrms_assert_same( 150.0, $ozet['iptal_tutari'], 'iptal tutarı ayrı' );
	}
);

qrms_test(
	'5. aynı cancellation reconciliation iki kez → duplicate order_cancelled yok',
	function () {
		$wpdb = qrms_siparis_wpdb();
		$oid  = 'ord-dup-1111-2222-3333-444444444444';
		$belge = array(
			'id'     => $oid,
			'tip'    => 'siparis',
			'durum'  => 'iptal',
			'masaNo' => 'masa-1',
			'iptalAt' => '2026-09-26T10:00:00Z',
		);

		$bir = QRMS_Siparis_Iptal_Uzlastirma::belgeden_yaz( $oid, $belge );
		$iki = QRMS_Siparis_Iptal_Uzlastirma::belgeden_yaz( $oid, $belge );

		qrms_assert_true( $bir, 'ilk yazım' );
		qrms_assert_true( $iki, 'ikinci çağrı da başarılı (idempotent)' );
		qrms_assert_same( 1, count( $wpdb->inserts ), 'tek insert' );
		qrms_assert_same( 'order_cancelled', $wpdb->inserts[0]['event_type'], 'tip' );
	}
);

qrms_test(
	'6. Firestore durum=iptal → order_cancelled üretilebiliyor',
	function () {
		$wpdb = qrms_siparis_wpdb();
		qrms_siparis_fs_hazir();
		$oid = 'ord-fs-1111-2222-3333-444444444444';

		$GLOBALS['qrms_test']['http'] = function ( $url ) use ( $oid ) {
			qrms_assert_contains( '/documents/calls/' . $oid, $url, 'call_oku order_id' );
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						'name'   => 'projects/test-p0-olgu/databases/(default)/documents/calls/' . $oid,
						'fields' => array(
							'tip'   => array( 'stringValue' => 'siparis' ),
							'durum' => array( 'stringValue' => 'iptal' ),
							'masaNo' => array( 'stringValue' => 'masa-7' ),
							'iptalAt' => array( 'timestampValue' => '2026-09-26T11:22:33Z' ),
						),
					)
				),
			);
		};

		$ok = QRMS_Siparis_Iptal_Uzlastirma::uzlastir( $oid );
		qrms_assert_true( $ok, 'uzlaştırıldı' );
		qrms_assert_same( 1, count( $wpdb->inserts ), 'olay yazıldı' );
		qrms_assert_same( 'order_cancelled', $wpdb->inserts[0]['event_type'], 'tip' );
		qrms_assert_same( $oid, $wpdb->inserts[0]['order_id'], 'order_id' );
		qrms_assert_same( 'masa-7', $wpdb->inserts[0]['masa_no'], 'masa' );

		$GLOBALS['qrms_test']['http'] = null;
	}
);

qrms_test(
	'7. iptalAt mevcut → event zamanı olarak kullanılıyor',
	function () {
		$zaman = QRMS_Siparis_Iptal_Uzlastirma::iptal_zamani(
			array(
				'iptalAt'     => '2026-09-26T12:34:56Z',
				'guncellendi' => '2026-09-26T01:00:00Z',
			)
		);
		qrms_assert_same( '2026-09-26 12:34:56', $zaman, 'iptalAt öncelikli' );
	}
);

qrms_test(
	'8. iptalAt yok, guncellendi mevcut → fallback',
	function () {
		$zaman = QRMS_Siparis_Iptal_Uzlastirma::iptal_zamani(
			array(
				'guncellendi' => '2026-09-26T08:15:00Z',
			)
		);
		qrms_assert_same( '2026-09-26 08:15:00', $zaman, 'guncellendi fallback' );

		$GLOBALS['qrms_test']['now'] = strtotime( '2026-09-26 15:00:00 UTC' );
		$gozlem = QRMS_Siparis_Iptal_Uzlastirma::iptal_zamani( array( 'durum' => 'iptal' ) );
		qrms_assert_same( '2026-09-26 15:00:00', $gozlem, 'gözlem anı' );
		unset( $GLOBALS['qrms_test']['now'] );
	}
);

qrms_test(
	'9. order_id olmayan legacy order_sent kesin KPI dışındadır',
	function () {
		$ozet = QRMS_Siparis_Olgulari::hesapla(
			array(
				array( 'event_type' => 'order_sent', 'qty' => 1, 'unit_price' => 40, 'price' => 40 ),
				array( 'event_type' => 'order_sent', 'order_id' => null, 'qty' => 1, 'unit_price' => 12 ),
				array( 'event_type' => 'order_sent', 'order_id' => '', 'qty' => 2, 'unit_price' => 9 ),
				array( 'event_type' => 'order_sent', 'order_id' => 'o-yeni', 'qty' => 1, 'unit_price' => 7 ),
			)
		);
		qrms_assert_same( 1, $ozet['siparis_sayisi'], 'yalnızca kesin order_id' );
		qrms_assert_same( 7.0, $ozet['siparis_tutari'], 'legacy tutar karışmaz' );
	}
);

qrms_test(
	'10. unit_price olmayan legacy kayıt QR sipariş tutarına sessizce dahil edilmez',
	function () {
		$ozet = QRMS_Siparis_Olgulari::hesapla(
			array(
				array( 'event_type' => 'order_sent', 'order_id' => 'o-1', 'qty' => 2, 'unit_price' => 10 ),
				array( 'event_type' => 'order_sent', 'order_id' => 'o-legacy', 'qty' => 3, 'price' => 50 ),
			)
		);
		qrms_assert_same( 2, $ozet['siparis_sayisi'], 'order_id varsa sayıya girer' );
		qrms_assert_same( 20.0, $ozet['siparis_tutari'], 'price unit_price yerine geçmez' );
	}
);

qrms_test(
	'11. aynı order_id için birden fazla order_cancelled yazılmaya çalışılırsa tekil kalır',
	function () {
		$wpdb = qrms_siparis_wpdb();
		$oid  = 'ord-tek-1111-2222-3333-444444444444';
		$belge = array(
			'tip'   => 'siparis',
			'durum' => 'iptal',
		);

		QRMS_Siparis_Iptal_Uzlastirma::belgeden_yaz( $oid, $belge );
		QRMS_Siparis_Iptal_Uzlastirma::uzlastir( $oid, $belge );
		QRMS_Siparis_Iptal_Uzlastirma::belgeden_yaz( $oid, $belge );

		$iptal_yazim = 0;
		foreach ( $wpdb->inserts as $satir ) {
			if ( 'order_cancelled' === ( $satir['event_type'] ?? '' ) && $oid === ( $satir['order_id'] ?? '' ) ) {
				++$iptal_yazim;
			}
		}
		qrms_assert_same( 1, $iptal_yazim, 'tekil iptal olayı' );
		qrms_assert_true( QRMS_Siparis_Iptal_Uzlastirma::iptal_olayi_var_mi( $oid ), 'varlık sorgusu' );
	}
);

qrms_test(
	'12. Phase 1 order contract ve Firestore 409/transport yolları duruyor',
	function () {
		$rest = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/rest-order.php' );
		qrms_assert_contains( 'wp_generate_uuid4()', $rest, 'order_id' );
		qrms_assert_contains( 'firestore_conflict', $rest, '409' );
		qrms_assert_contains( 'firestore_transport', $rest, 'transport' );
		qrms_assert_contains( 'QMO_Firestore::call_oku( $order_id )', $rest, 'call_oku' );
		qrms_assert_contains( 'unit_price', $rest, 'unit_price yazımı' );
		qrms_assert_contains( "'reason'", $rest, 'reason' );

		$sema = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php' );
		qrms_assert_contains( "const DB_SURUM = '1.4'", $sema, 'şema 1.4' );
		qrms_assert_contains( 'order_cancelled', $sema, 'iptal tipi şemada' );

		$fs = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/class-qmo-firestore.php' );
		qrms_assert_contains( 'firestore_conflict', $fs, '409 ayrımı' );
		qrms_assert_contains( 'documentId=', $fs, 'custom document ID' );
	}
);

qrms_test(
	'kesin ozet sorgusu tek taramadır: SELECT * / N+1 / listing yok',
	function () {
		$wpdb            = qrms_sayan_wpdb();
		$wpdb->results[] = array(
			array(
				'order_id'       => 'o-1',
				'event_type'     => 'order_sent',
				'qty'            => 2,
				'unit_price'     => 10,
				'iptal_order_id' => null,
			),
		);

		$ozet = QRMS_Siparis_Olgulari::ozet( '2026-09-01 00:00:00', '2026-09-30 23:59:59', 'masa-4' );

		qrms_assert_same( 1, count( $wpdb->queries ), 'tek sorgu' );
		qrms_assert_contains( 'event_type = \'order_sent\'', $wpdb->queries[0], 'sent taraması' );
		qrms_assert_contains( 'order_cancelled', $wpdb->queries[0], 'iptal join' );
		qrms_assert_contains( 'unit_price', $wpdb->queries[0], 'unit_price' );
		qrms_assert_contains( 's.created_at BETWEEN', $wpdb->queries[0], 'idx_td aralığı' );
		qrms_assert_contains( "s.masa_no = 'masa-4'", $wpdb->queries[0], 'masa filtresi' );
		qrms_assert_contains( 's.order_id IS NOT NULL', $wpdb->queries[0], 'legacy order_id hariç' );
		qrms_assert_false( false !== strpos( $wpdb->queries[0], 'SELECT *' ), 'SELECT * yok' );
		qrms_assert_false( false !== strpos( $wpdb->queries[0], 'call_listele' ), 'listeleme yok' );
		qrms_assert_same( 1, $ozet['siparis_sayisi'], 'join normalize + hesapla' );
		qrms_assert_same( 20.0, $ozet['siparis_tutari'], '2*10' );
	}
);

qrms_test(
	'bekliyor belgesi iptal olayı yazmaz; garson tipi yazılmaz',
	function () {
		$wpdb = qrms_siparis_wpdb();
		qrms_assert_false(
			QRMS_Siparis_Iptal_Uzlastirma::belgeden_yaz(
				'ord-bek',
				array( 'tip' => 'siparis', 'durum' => 'bekliyor' )
			),
			'bekliyor'
		);
		qrms_assert_false(
			QRMS_Siparis_Iptal_Uzlastirma::belgeden_yaz(
				'ord-garson',
				array( 'tip' => 'garson', 'durum' => 'iptal' )
			),
			'garson'
		);
		qrms_assert_same( 0, count( $wpdb->inserts ), 'yazım yok' );
	}
);

qrms_test(
	'servis paneli iptal geçişinden sonra uzlaştırma call_oku kullanır',
	function () {
		$wpdb = qrms_siparis_wpdb();
		qrms_siparis_fs_hazir( 'test-p0-panel' );
		$oid = 'ord-panel-1111-2222-3333-444444444444';
		$n   = 0;

		$GLOBALS['qrms_test']['http'] = function ( $url, $args = array() ) use ( $oid, &$n ) {
			++$n;
			$method = isset( $args['method'] ) ? strtoupper( (string) $args['method'] ) : 'GET';

			if ( 'PATCH' === $method ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '{}',
				);
			}

			$durum = 1 === $n ? 'bekliyor' : 'iptal';
			$alan  = array(
				'tip'   => array( 'stringValue' => 'siparis' ),
				'durum' => array( 'stringValue' => $durum ),
				'masaNo' => array( 'stringValue' => 'masa-2' ),
			);
			if ( 'iptal' === $durum ) {
				$alan['iptalAt'] = array( 'timestampValue' => '2026-09-26T09:00:00Z' );
			}

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						'name'   => 'projects/test-p0-panel/databases/(default)/documents/calls/' . $oid,
						'fields' => $alan,
					)
				),
			);
		};

		$sonuc = QRMS_SP_Veri::durum_degistir( $oid, 'bekliyor', 'iptal' );
		qrms_assert_true( true === $sonuc, 'panel iptali başarılı' );
		qrms_assert_true( $n >= 2, 'call_oku + PATCH (+ uzlaştırma GET)' );
		qrms_assert_same( 1, count( $wpdb->inserts ), 'tek order_cancelled' );
		qrms_assert_same( '2026-09-26 09:00:00', $wpdb->inserts[0]['created_at'], 'iptalAt' );

		$GLOBALS['qrms_test']['http'] = null;
	}
);

qrms_test(
	'sepet approximate sorgusu bu fazda değişmedi',
	function () {
		$sepet = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/sepet-sayfasi.php' );
		$an    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php' );
		qrms_assert_contains( "event_type IN ('cart_add','cart_remove','order_sent','order_failed','order_blocked')", $an, 'sepet grupları aynı' );
		qrms_assert_contains( 'SUM(qty * price) AS ciro', $an, 'eski ciro sorgusu duruyor' );
		qrms_assert_contains( 'Yaklaşık kimlik', $sepet, 'approximate oturum notu' );
		qrms_assert_false(
			false !== strpos( $sepet, 'QRMS_Siparis_Olgulari' ),
			'sepet UI olguları karıştırmaz'
		);
	}
);
