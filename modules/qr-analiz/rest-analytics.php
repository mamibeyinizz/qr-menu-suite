<?php
/**
 * REST: POST /wp-json/qrservis/v1/analytics — şube analitiği.
 *
 * Uygulama, müdürün/adminin Firebase ID token'ı ile buraya POST atar;
 * endpoint token'ı doğrular, rolü kontrol eder (admin/müdür), sonra
 * {prefix}rma_analytics tablosundan seçilen tarih aralığı için özet döner.
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'qmo_rest_analytics_kaydet' );

/**
 * Rotayı kaydet.
 */
if ( ! function_exists( 'qmo_rest_analytics_kaydet' ) ) {
	function qmo_rest_analytics_kaydet() {
		register_rest_route(
			'qrservis/v1',
			'/analytics',
			array(
				'methods'             => 'POST',
				'callback'            => 'qmo_rest_analytics',
				// Yetki içeride: Firebase ID token + rol kontrolü.
				'permission_callback' => '__return_true',
			)
		);
	}
}

/**
 * Analitik ucu.
 *
 * @param WP_REST_Request $req İstek.
 * @return WP_REST_Response
 */
if ( ! function_exists( 'qmo_rest_analytics' ) ) {
	function qmo_rest_analytics( WP_REST_Request $req ) {
		global $wpdb;

		if ( ! QMO_Firestore::hazir_mi() ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'msg'     => 'Analitik sistemi yapılandırılmamış.',
				),
				500
			);
		}
		$project = QMO_Firestore::project_id();

		// 1) Çağıranın kimliği.
		$id_token = $req->get_param( 'idToken' );
		if ( ! $id_token ) {
			$auth = $req->get_header( 'authorization' );
			if ( $auth && 0 === stripos( $auth, 'Bearer ' ) ) {
				$id_token = trim( substr( $auth, 7 ) );
			}
		}
		if ( ! $id_token ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'msg'     => 'Kimlik gerekli',
				),
				401
			);
		}

		$uid = QMO_Firestore::id_token_dogrula( $id_token, $project );
		if ( is_wp_error( $uid ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'msg'     => $uid->get_error_message(),
				),
				401
			);
		}

		// 2) Rol kontrolü (sadece admin/müdür).
		$token = QMO_Firestore::access_token( QMO_Firestore::SCOPE_DATASTORE );
		if ( is_wp_error( $token ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'msg'     => $token->get_error_message(),
				),
				500
			);
		}

		$u = QMO_Firestore::kullanici_doc( $token, $project, $uid );
		if ( is_wp_error( $u ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'msg'     => 'Yetki yok',
				),
				403
			);
		}
		if ( ! in_array( $u['rol'], array( 'admin', 'mudur' ), true ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'msg'     => 'Bu veriye erişim yetkiniz yok',
				),
				403
			);
		}
		// Müdür sadece kendi şubesinin sitesine erişebilir.
		if ( 'mudur' === $u['rol'] && $u['branchId'] !== QMO_Firestore::branch_id() ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'msg'     => 'Bu şubeye erişim yok',
				),
				403
			);
		}

		// 3) Tarih aralığı (ISO 'YYYY-MM-DD'); yoksa son 30 gün.
		$baslangic = sanitize_text_field( (string) $req->get_param( 'baslangic' ) );
		$bitis     = sanitize_text_field( (string) $req->get_param( 'bitis' ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $baslangic ) ) {
			$baslangic = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $bitis ) ) {
			$bitis = gmdate( 'Y-m-d' );
		}
		$start = $baslangic . ' 00:00:00';
		$end   = $bitis . ' 23:59:59';

		$t = $wpdb->prefix . 'rma_analytics';

		// Tablo başka bir eklenti/tema tarafından oluşturulur; yoksa boş dön.
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'msg'     => 'Analitik tablosu bulunamadı.',
				),
				404
			);
		}

		// 4) Metrikler.
		$total_views = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t} WHERE event_type='menu_view' AND created_at BETWEEN %s AND %s",
				$start,
				$end
			)
		);
		$unique_visitors = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT ip_hash) FROM {$t} WHERE event_type='menu_view' AND created_at BETWEEN %s AND %s",
				$start,
				$end
			)
		);
		$total_clicks = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t} WHERE event_type='product_click' AND created_at BETWEEN %s AND %s",
				$start,
				$end
			)
		);

		// En çok tıklanan ürünler (top 10) — item_id'ye göre grupla.
		$top = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT MAX(item_name) AS item_name, MAX(category_name) AS category_name, COUNT(*) AS adet
				 FROM {$t}
				 WHERE event_type='product_click' AND item_id>0 AND created_at BETWEEN %s AND %s
				 GROUP BY item_id
				 HAVING MAX(item_name)<>''
				 ORDER BY adet DESC LIMIT 10",
				$start,
				$end
			),
			ARRAY_A
		);

		// En az tıklanan ürünler (top 10, en düşükten).
		$bottom = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT MAX(item_name) AS item_name, MAX(category_name) AS category_name, COUNT(*) AS adet
				 FROM {$t}
				 WHERE event_type='product_click' AND item_id>0 AND created_at BETWEEN %s AND %s
				 GROUP BY item_id
				 HAVING MAX(item_name)<>''
				 ORDER BY adet ASC LIMIT 10",
				$start,
				$end
			),
			ARRAY_A
		);

		// Masa bazlı trafik (menü açılışı sayısı, masaya göre).
		$tables = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT table_slug, COUNT(*) AS adet
				 FROM {$t}
				 WHERE event_type='menu_view' AND table_slug<>'' AND created_at BETWEEN %s AND %s
				 GROUP BY table_slug ORDER BY adet DESC",
				$start,
				$end
			),
			ARRAY_A
		);

		$int_adet = function ( $rows ) {
			foreach ( $rows as &$r ) {
				$r['adet'] = (int) $r['adet'];
			}
			return $rows;
		};

		return new WP_REST_Response(
			array(
				'success'            => true,
				'baslangic'          => $baslangic,
				'bitis'              => $bitis,
				'toplamGoruntulenme' => $total_views,
				'tekilZiyaretci'     => $unique_visitors,
				'toplamTiklama'      => $total_clicks,
				'enCok'              => $int_adet( $top ? $top : array() ),
				'enAz'               => $int_adet( $bottom ? $bottom : array() ),
				'masalar'            => $int_adet( $tables ? $tables : array() ),
			),
			200
		);
	}
}
