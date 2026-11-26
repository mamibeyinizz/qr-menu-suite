<?php
/**
 * REST: personel uçları (garson / müdür uygulaması).
 *
 *   POST /wp-json/qrservis/v1/menu         — şubenin menüsü (sürüm kontrollü)
 *   POST /wp-json/qrservis/v1/staff-order  — personel siparişi (otomatik onaylı)
 *
 * Yetki: Firebase ID token → users/{uid} dokümanı. Çağıranın şubesi bu sitenin
 * şubesiyle eşleşmelidir (admin her şubede geçerlidir). İstemcinin gönderdiği
 * rol/şube/fiyat bilgisine güvenilmez.
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'qmo_rest_staff_kaydet' );

if ( ! function_exists( 'qmo_rest_staff_kaydet' ) ) {
	/**
	 * Rotaları kaydet.
	 *
	 * @return void
	 */
	function qmo_rest_staff_kaydet() {
		foreach ( array(
			'/menu'        => 'qmo_rest_staff_menu',
			'/staff-order' => 'qmo_rest_staff_order',
		) as $yol => $cb ) {
			register_rest_route(
				'qrservis/v1',
				$yol,
				array(
					'methods'             => 'POST',
					'callback'            => $cb,
					// Yetki içeride: Firebase ID token + rol + şube kontrolü.
					'permission_callback' => '__return_true',
				)
			);
		}
	}
}

if ( ! function_exists( 'qmo_staff_hata' ) ) {
	/**
	 * Hata cevabı.
	 *
	 * @param string $msg  Mesaj.
	 * @param int    $http Durum kodu.
	 * @return WP_REST_Response
	 */
	function qmo_staff_hata( $msg, $http ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'msg'     => $msg,
			),
			$http
		);
	}
}

if ( ! function_exists( 'qmo_staff_dogrula' ) ) {
	/**
	 * Çağıranı doğrula ve yetkilendir.
	 *
	 * @param WP_REST_Request $req          İstek.
	 * @param string[]        $izinli_roller İzinli birincil roller (admin her zaman geçer).
	 * @param int             $saatlik_tavan IP başına saatlik istek tavanı.
	 * @return array{uid:string,rol:string,branchId:string,ad:string,token:string,project:string}|WP_REST_Response
	 */
	function qmo_staff_dogrula( WP_REST_Request $req, array $izinli_roller, $saatlik_tavan = 600 ) {
		if ( function_exists( 'qmo_ip_hash' ) && function_exists( 'qmo_sayac_arttir' ) ) {
			if ( qmo_sayac_arttir( 'qmo_staff_' . qmo_ip_hash(), HOUR_IN_SECONDS ) > $saatlik_tavan ) {
				return qmo_staff_hata( 'Çok fazla istek. Lütfen daha sonra tekrar deneyin.', 429 );
			}
		}

		if ( ! QMO_Firestore::hazir_mi() ) {
			return qmo_staff_hata( 'Firebase yapılandırması eksik.', 500 );
		}
		$project = QMO_Firestore::project_id();

		$id_token = $req->get_param( 'idToken' );
		if ( ! $id_token ) {
			$auth = $req->get_header( 'authorization' );
			if ( $auth && 0 === stripos( $auth, 'Bearer ' ) ) {
				$id_token = trim( substr( $auth, 7 ) );
			}
		}
		if ( ! $id_token ) {
			return qmo_staff_hata( 'Kimlik doğrulama gerekli', 401 );
		}

		$uid = QMO_Firestore::id_token_dogrula( $id_token, $project );
		if ( is_wp_error( $uid ) ) {
			return qmo_staff_hata( $uid->get_error_message(), 401 );
		}

		$token = QMO_Firestore::access_token( QMO_Firestore::SCOPE_DATASTORE );
		if ( is_wp_error( $token ) ) {
			return qmo_staff_hata( $token->get_error_message(), 500 );
		}

		$kisi = QMO_Firestore::kullanici_doc( $token, $project, $uid );
		if ( is_wp_error( $kisi ) ) {
			return qmo_staff_hata( 'Yetki bulunamadı', 403 );
		}
		if ( empty( $kisi['aktif'] ) ) {
			return qmo_staff_hata( 'Hesap pasif', 403 );
		}

		$admin = ( 'admin' === $kisi['rol'] );
		if ( ! $admin && ! in_array( $kisi['rol'], $izinli_roller, true ) ) {
			return qmo_staff_hata( 'Bu işlem için yetkiniz yok', 403 );
		}

		// Bu sitenin şubesi: yapılandırılmamış site personel işlemi kabul etmez.
		$site_sube = QMO_Firestore::branch_id();
		if ( '' === $site_sube ) {
			return qmo_staff_hata( 'Şube yapılandırması eksik.', 500 );
		}
		if ( ! $admin && $kisi['branchId'] !== $site_sube ) {
			return qmo_staff_hata( 'Bu şube için yetkiniz yok', 403 );
		}

		return array(
			'uid'      => (string) $uid,
			'rol'      => (string) $kisi['rol'],
			'branchId' => $site_sube,
			'ad'       => (string) $kisi['ad'],
			'token'    => $token,
			'project'  => $project,
		);
	}
}

if ( ! function_exists( 'qmo_staff_menu_yukle' ) ) {
	/**
	 * Menü yükünü kurar (kategoriler + ürünler).
	 *
	 * @return array{kategoriler:array,urunler:array}
	 */
	function qmo_staff_menu_yukle() {
		$terimler = get_terms(
			array(
				'taxonomy'   => 'rma_category',
				'hide_empty' => false,
			)
		);
		$terimler = is_array( $terimler ) ? $terimler : array();

		$sirali = array();
		foreach ( $terimler as $t ) {
			$sirali[] = array(
				'id'       => (int) $t->term_id,
				'ad'       => html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' ),
				'istasyon' => class_exists( 'RMA_Istasyon' )
					? RMA_Istasyon::temizle( get_term_meta( $t->term_id, RMA_Istasyon::TERIM_META, true ) )
					: '',
				'sira'     => (int) get_term_meta( $t->term_id, 'rma_cat_order', true ),
			);
		}
		usort(
			$sirali,
			static function ( $a, $b ) {
				return $a['sira'] <=> $b['sira'];
			}
		);

		$ids = get_posts(
			array(
				'post_type'              => 'rma_menu_item',
				'post_status'            => 'publish',
				'posts_per_page'         => 2000,
				'fields'                 => 'ids',
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
			)
		);

		$urunler = array();
		foreach ( $ids as $id ) {
			$kategori = 0;
			$terms    = get_the_terms( $id, 'rma_category' );
			if ( is_array( $terms ) && ! empty( $terms ) ) {
				$kategori = (int) reset( $terms )->term_id;
			}

			$fiyat = get_post_meta( $id, 'rma_price', true );

			$urunler[] = array(
				'id'         => (int) $id,
				'ad'         => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
				'fiyat'      => '' === (string) $fiyat ? null : round( (float) $fiyat, 2 ),
				'kategoriId' => $kategori,
				'aciklama'   => wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $id ) ),
				'gorsel'     => (string) get_the_post_thumbnail_url( $id, 'medium' ),
				'aktif'      => '0' !== (string) get_post_meta( $id, 'rma_active', true ),
				'tukendi'    => class_exists( 'RMA_Tukendi' ) ? RMA_Tukendi::urun_tukendi( $id ) : false,
				'istasyon'   => class_exists( 'RMA_Istasyon' ) ? RMA_Istasyon::urun_istasyonu( $id ) : 'yok',
			);
		}

		return array(
			'kategoriler' => $sirali,
			'urunler'     => $urunler,
		);
	}
}

if ( ! function_exists( 'qmo_rest_staff_menu' ) ) {
	/**
	 * Menü ucu. `surum` aynıysa yalnızca {degisti:false} döner.
	 *
	 * @param WP_REST_Request $req İstek.
	 * @return WP_REST_Response
	 */
	function qmo_rest_staff_menu( WP_REST_Request $req ) {
		$k = qmo_staff_dogrula( $req, array( 'mudur', 'garson' ) );
		if ( $k instanceof WP_REST_Response ) {
			return $k;
		}

		if ( ! post_type_exists( 'rma_menu_item' ) ) {
			return qmo_staff_hata( 'Menü modülü bu sitede etkin değil.', 404 );
		}

		// 15 sn'lik kısa önbellek: 45 sn'de bir yoklayan çok cihaz aynı yükü
		// tekrar kurmasın. Yönetim ucu yazınca önbellek silinir.
		$veri = get_transient( 'qmo_staff_menu_v1' );
		if ( ! is_array( $veri ) ) {
			$veri = qmo_staff_menu_yukle();
			set_transient( 'qmo_staff_menu_v1', $veri, 15 );
		}

		$surum = substr( md5( wp_json_encode( $veri ) ), 0, 12 );

		$istemci = (string) $req->get_param( 'surum' );
		if ( '' !== $istemci && hash_equals( $surum, $istemci ) ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'degisti' => false,
					'surum'   => $surum,
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'success'     => true,
				'degisti'     => true,
				'surum'       => $surum,
				'kategoriler' => $veri['kategoriler'],
				'urunler'     => $veri['urunler'],
			),
			200
		);
	}
}

if ( ! function_exists( 'qmo_rest_staff_order' ) ) {
	/**
	 * Personel siparişi.
	 *
	 * Gövde: { idToken, masaNo, anahtar (UUID v4), items:[{itemId, adet, not}], aciklama? }
	 * Fiyat ve ad sunucuda menüden çözülür; istemci fiyatı kullanılmaz.
	 *
	 * @param WP_REST_Request $req İstek.
	 * @return WP_REST_Response
	 */
	function qmo_rest_staff_order( WP_REST_Request $req ) {
		$k = qmo_staff_dogrula( $req, array( 'mudur', 'garson' ), 300 );
		if ( $k instanceof WP_REST_Response ) {
			return $k;
		}

		$masa = trim( (string) $req->get_param( 'masaNo' ) );
		if ( '' === $masa || mb_strlen( $masa ) > 32 || ! preg_match( '/^[\p{L}\p{N} ._-]+$/u', $masa ) ) {
			return qmo_staff_hata( 'Geçersiz masa', 400 );
		}

		$anahtar = trim( (string) $req->get_param( 'anahtar' ) );
		if ( '' === $anahtar ) {
			return qmo_staff_hata( 'Geçersiz sipariş', 400 );
		}

		$ham = $req->get_param( 'items' );
		if ( ! is_array( $ham ) ) {
			return qmo_staff_hata( 'Geçersiz sipariş', 400 );
		}

		// Yalnızca itemId + adet + not alınır; ad boş bırakılır ve sunucu menüden doldurur.
		$items = array();
		foreach ( $ham as $it ) {
			if ( ! is_array( $it ) || empty( $it['itemId'] ) ) {
				return qmo_staff_hata( 'Geçersiz sipariş', 400 );
			}
			$id = absint( $it['itemId'] );
			if ( $id < 1 || 'rma_menu_item' !== get_post_type( $id ) ) {
				return qmo_staff_hata( 'Menüde bulunmayan bir ürün var; menüyü yenileyin.', 400 );
			}
			$items[] = array(
				'itemId'  => $id,
				'urunAdi' => get_the_title( $id ),
				'adet'    => isset( $it['adet'] ) ? (int) $it['adet'] : 1,
				'not'     => isset( $it['not'] ) ? (string) $it['not'] : '',
			);
		}

		$sube = QMO_Firestore::sube_ayarlari( $k['token'], $k['project'], $k['branchId'] );
		if ( is_wp_error( $sube ) ) {
			return qmo_staff_hata( 'Şube ayarları okunamadı.', 502 );
		}

		$ek = array(
			'personel' => true,
			'uid'      => $k['uid'],
			'ad'       => '' !== $k['ad'] ? $k['ad'] : 'Personel',
			'not'      => mb_substr( sanitize_textarea_field( (string) $req->get_param( 'aciklama' ) ), 0, 300 ),
			'sube'     => $sube,
		);

		$sonuc = qmo_siparis_isle( $masa, $items, 'tr', '', $anahtar, $ek );

		$http = isset( $sonuc['http'] ) ? (int) $sonuc['http'] : ( ! empty( $sonuc['success'] ) ? 200 : 400 );

		return new WP_REST_Response( $sonuc, $http );
	}
}
