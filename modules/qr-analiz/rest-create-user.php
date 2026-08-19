<?php
/**
 * REST: POST /wp-json/qrservis/v1/create-user — merkezi kullanıcı oluşturma.
 *
 * SADECE ANA SİTEDE açılır (qrmenuofficial.com). Şube sitelerinde bu uç
 * kayıtlı DEĞİLDİR — QR Menü → Ayarlar → "Bu site ana sitedir" seçeneği
 * işaretlenmedikçe rota hiç register edilmez.
 *
 * Admin/müdür uygulamada "kullanıcı ekle" dediğinde app buraya istek atar.
 * Endpoint çağıranın Firebase ID token'ını doğrular, rolünü kontrol eder
 * (admin her şeyi, müdür sadece kendi şubesine garson), sonra service
 * account ile hem Auth hesabını hem Firestore users dokümanını oluşturur.
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'qmo_rest_create_user_kaydet' );

/**
 * Rotayı kaydet (yalnızca ana sitede).
 */
if ( ! function_exists( 'qmo_rest_create_user_kaydet' ) ) {
	function qmo_rest_create_user_kaydet() {
		if ( ! get_option( 'qmo_ana_site', false ) ) {
			return;
		}
		register_rest_route(
			'qrservis/v1',
			'/create-user',
			array(
				'methods'             => 'POST',
				'callback'            => 'qmo_rest_create_user',
				// Yetki içeride: Firebase ID token + rol kontrolü.
				'permission_callback' => '__return_true',
			)
		);
	}
}

/**
 * Kullanıcı oluşturma ucu.
 *
 * @param WP_REST_Request $req İstek.
 * @return WP_REST_Response
 */
if ( ! function_exists( 'qmo_rest_create_user' ) ) {
	function qmo_rest_create_user( WP_REST_Request $req ) {

		if ( ! QMO_Firestore::hazir_mi() ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'msg'     => 'Firebase yapılandırması eksik.',
				),
				500
			);
		}
		$project = QMO_Firestore::project_id();

		// 1) Çağıranın ID token'ı.
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
					'msg'     => 'Kimlik doğrulama gerekli',
				),
				401
			);
		}

		$caller_uid = QMO_Firestore::id_token_dogrula( $id_token, $project );
		if ( is_wp_error( $caller_uid ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'msg'     => $caller_uid->get_error_message(),
				),
				401
			);
		}

		// 2) Girdi.
		$kullanici_adi = strtolower( trim( (string) $req->get_param( 'kullaniciAdi' ) ) );
		$sifre         = (string) $req->get_param( 'sifre' );
		$rol           = (string) $req->get_param( 'rol' );
		$ad            = sanitize_text_field( (string) $req->get_param( 'ad' ) );
		$branch_id     = sanitize_text_field( (string) $req->get_param( 'branchId' ) );

		// Kullanıcı adı e-posta yerel kısmına gireceği için daraltılmış karakter seti.
		if ( ! preg_match( '/^[a-z0-9._-]{3,64}$/', $kullanici_adi ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'msg'     => 'Kullanıcı adı 3-64 karakter olmalı; sadece harf, rakam, nokta, tire ve alt çizgi kullanılabilir.',
				),
				400
			);
		}
		if ( strlen( $sifre ) < 6 || ! in_array( $rol, array( 'garson', 'mudur' ), true ) || '' === $branch_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'msg'     => 'Geçersiz veri (şifre en az 6 karakter)',
				),
				400
			);
		}

		// 3) Service account token (Identity Toolkit için cloud-platform kapsamı).
		$token = QMO_Firestore::access_token( QMO_Firestore::SCOPE_CLOUD );
		if ( is_wp_error( $token ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'msg'     => $token->get_error_message(),
				),
				500
			);
		}

		// 4) Çağıranın rolü/şubesi.
		$caller = QMO_Firestore::kullanici_doc( $token, $project, $caller_uid );
		if ( is_wp_error( $caller ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'msg'     => 'Yetki bulunamadı',
				),
				403
			);
		}

		// 5) Yetkilendirme.
		$izin = false;
		if ( 'admin' === $caller['rol'] ) {
			// Admin her şubeye garson veya müdür ekleyebilir.
			$izin = true;
		} elseif ( 'mudur' === $caller['rol'] ) {
			// Müdür sadece kendi şubesine garson ekleyebilir.
			$izin = ( 'garson' === $rol && $branch_id === $caller['branchId'] );
		}
		if ( ! $izin ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'msg'     => 'Bu işlem için yetkiniz yok',
				),
				403
			);
		}

		// 6) Auth hesabı oluştur (Identity Toolkit admin).
		$email = $kullanici_adi . '@qrservis.local';
		$acc   = wp_remote_post(
			"https://identitytoolkit.googleapis.com/v1/projects/{$project}/accounts",
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'email'         => $email,
						'password'      => $sifre,
						'emailVerified' => false,
					)
				),
			)
		);
		if ( is_wp_error( $acc ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'msg'     => $acc->get_error_message(),
				),
				500
			);
		}

		$acc_body = json_decode( wp_remote_retrieve_body( $acc ), true );
		if ( 200 !== wp_remote_retrieve_response_code( $acc ) || empty( $acc_body['localId'] ) ) {
			$err = $acc_body['error']['message'] ?? 'Hesap oluşturulamadı';
			if ( false !== stripos( $err, 'EMAIL_EXISTS' ) ) {
				$err = 'Bu kullanıcı adı zaten kullanılıyor';
			}
			return new WP_REST_Response(
				array(
					'success' => false,
					'msg'     => $err,
				),
				400
			);
		}
		$new_uid = $acc_body['localId'];

		// 7) Firestore users/{uid} dokümanı.
		$doc = wp_remote_post(
			"https://firestore.googleapis.com/v1/projects/{$project}/databases/(default)/documents/users?documentId=" . rawurlencode( $new_uid ),
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'fields' => array(
							'kullaniciAdi' => array( 'stringValue' => $kullanici_adi ),
							'rol'          => array( 'stringValue' => $rol ),
							'branchId'     => array( 'stringValue' => $branch_id ),
							'ad'           => array( 'stringValue' => $ad ),
							'fcmToken'     => array( 'stringValue' => '' ),
							'createdAt'    => array( 'timestampValue' => gmdate( 'Y-m-d\TH:i:s\Z' ) ),
						),
					)
				),
			)
		);

		// Firestore yazımı başarısızsa Auth hesabını geri al (yetim kalmasın).
		if ( is_wp_error( $doc ) || wp_remote_retrieve_response_code( $doc ) >= 300 ) {
			wp_remote_post(
				"https://identitytoolkit.googleapis.com/v1/projects/{$project}/accounts:delete",
				array(
					'timeout' => 15,
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( array( 'localId' => $new_uid ) ),
				)
			);
			return new WP_REST_Response(
				array(
					'success' => false,
					'msg'     => 'Kullanıcı kaydı tamamlanamadı, işlem geri alındı.',
				),
				500
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'uid'     => $new_uid,
			),
			200
		);
	}
}
