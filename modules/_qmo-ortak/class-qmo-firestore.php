<?php
/**
 * QMO Firestore — Google service account ile Firestore erişimi.
 *
 * GÜVENLİK NOTU
 * -------------
 * Service account JSON'ı KOD İÇİNE GÖMÜLMEZ. Eski WPCode snippet'lerinde
 * özel anahtar düz metin olarak dosyanın içindeydi; anahtarı gören herkes
 * Firebase projesine tam yetkiyle erişebiliyordu. Artık şu sırayla aranır:
 *
 *   1. wp-config.php içindeki QMO_FIREBASE_SA_JSON sabiti  (ÖNERİLEN)
 *   2. qmo_firebase_sa option'ı (Ayarlar sayfasından yapıştırılır)
 *   3. Eski qrservis_service_account_json() fonksiyonu (hâlâ aktif bir
 *      snippet varsa geçiş dönemi için okunur)
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QMO_Firestore' ) ) {

	/**
	 * Firestore / Identity Toolkit istemcisi.
	 */
	class QMO_Firestore {

		/** Firestore okuma-yazma kapsamı. */
		const SCOPE_DATASTORE = 'https://www.googleapis.com/auth/datastore';

		/** Hem Firestore hem Identity Toolkit (kullanıcı oluşturma) kapsamı. */
		const SCOPE_CLOUD = 'https://www.googleapis.com/auth/cloud-platform';

		/**
		 * Service account JSON metni.
		 *
		 * @return string
		 */
		public static function sa_json() {
			if ( defined( 'QMO_FIREBASE_SA_JSON' ) && QMO_FIREBASE_SA_JSON ) {
				return (string) QMO_FIREBASE_SA_JSON;
			}
			$opt = get_option( 'qmo_firebase_sa', '' );
			if ( $opt ) {
				return (string) $opt;
			}
			if ( function_exists( 'qrservis_service_account_json' ) ) {
				return (string) qrservis_service_account_json();
			}
			return '';
		}

		/**
		 * Çözümlenmiş service account dizisi.
		 *
		 * @return array
		 */
		public static function sa() {
			$json = self::sa_json();
			if ( '' === $json ) {
				return array();
			}
			$d = json_decode( $json, true );
			return is_array( $d ) ? $d : array();
		}

		/**
		 * Yapılandırma tamam mı?
		 *
		 * @return bool
		 */
		public static function hazir_mi() {
			$sa = self::sa();
			return ! empty( $sa['client_email'] ) && ! empty( $sa['private_key'] );
		}

		/**
		 * Firebase proje kimliği.
		 *
		 * @return string
		 */
		public static function project_id() {
			$sa = self::sa();
			return ! empty( $sa['project_id'] ) ? (string) $sa['project_id'] : '';
		}

		/**
		 * Bu sitenin şube kimliği.
		 *
		 * @return string
		 */
		public static function branch_id() {
			// Eski snippet'ler sabitle tanımlıyordu; varsa ona öncelik ver.
			if ( defined( 'QRSERVIS_BRANCH_ID' ) && QRSERVIS_BRANCH_ID ) {
				return (string) QRSERVIS_BRANCH_ID;
			}
			return (string) get_option( 'qmo_branch_id', '' );
		}

		/**
		 * base64url kodlama.
		 *
		 * @param string $data Veri.
		 * @return string
		 */
		public static function b64url_encode( $data ) {
			return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
		}

		/**
		 * base64url çözme.
		 *
		 * @param string $data Veri.
		 * @return string
		 */
		public static function b64url_decode( $data ) {
			$pad = strlen( $data ) % 4;
			if ( $pad ) {
				$data .= str_repeat( '=', 4 - $pad );
			}
			return base64_decode( strtr( $data, '-_', '+/' ) );
		}

		/**
		 * Service account ile OAuth2 access token üret (JWT bearer akışı).
		 * Token ~1 saat geçerli; kapsam başına 3500 sn cache'lenir.
		 *
		 * @param string $scope İstenen OAuth kapsamı.
		 * @return string|WP_Error
		 */
		public static function access_token( $scope = self::SCOPE_DATASTORE ) {
			$cache_key = 'qmo_gcp_token_' . substr( md5( $scope ), 0, 12 );
			$cached    = get_transient( $cache_key );
			if ( $cached ) {
				return $cached;
			}

			$sa = self::sa();
			if ( empty( $sa['client_email'] ) || empty( $sa['private_key'] ) ) {
				return new WP_Error( 'sa', 'Firebase service account tanımlı değil. QR Menü → Ayarlar bölümünden ekleyin.' );
			}

			$now   = time();
			$input = self::b64url_encode( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) ) . '.' .
				self::b64url_encode(
					wp_json_encode(
						array(
							'iss'   => $sa['client_email'],
							'scope' => $scope,
							'aud'   => 'https://oauth2.googleapis.com/token',
							'iat'   => $now,
							'exp'   => $now + 3600,
						)
					)
				);

			$sig = '';
			if ( ! openssl_sign( $input, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256 ) ) {
				return new WP_Error( 'sign', 'JWT imzalanamadı (private_key hatalı olabilir).' );
			}

			$resp = wp_remote_post(
				'https://oauth2.googleapis.com/token',
				array(
					'timeout' => 15,
					'body'    => array(
						'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
						'assertion'  => $input . '.' . self::b64url_encode( $sig ),
					),
				)
			);
			if ( is_wp_error( $resp ) ) {
				return $resp;
			}

			$data = json_decode( wp_remote_retrieve_body( $resp ), true );
			if ( empty( $data['access_token'] ) ) {
				return new WP_Error( 'token', 'Access token alınamadı.' );
			}

			set_transient( $cache_key, $data['access_token'], 3500 );
			return $data['access_token'];
		}

		/**
		 * Çağıranın Firebase ID token'ını doğrula → uid.
		 *
		 * @param string $id_token   İstemciden gelen ID token.
		 * @param string $project_id Beklenen proje kimliği.
		 * @return string|WP_Error
		 */
		public static function id_token_dogrula( $id_token, $project_id ) {
			$parts = explode( '.', (string) $id_token );
			if ( 3 !== count( $parts ) ) {
				return new WP_Error( 'token', 'Token biçimi hatalı' );
			}

			$header  = json_decode( self::b64url_decode( $parts[0] ), true );
			$payload = json_decode( self::b64url_decode( $parts[1] ), true );
			if ( empty( $header['kid'] ) || empty( $payload ) ) {
				return new WP_Error( 'token', 'Token çözülemedi' );
			}

			// Google public sertifikaları (6 saat cache).
			$certs = get_transient( 'qrservis_secure_certs' );
			if ( ! $certs ) {
				$r = wp_remote_get(
					'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com',
					array( 'timeout' => 15 )
				);
				if ( is_wp_error( $r ) ) {
					return $r;
				}
				$certs = json_decode( wp_remote_retrieve_body( $r ), true );
				if ( ! $certs ) {
					return new WP_Error( 'certs', 'Sertifikalar alınamadı' );
				}
				set_transient( 'qrservis_secure_certs', $certs, 6 * HOUR_IN_SECONDS );
			}
			if ( empty( $certs[ $header['kid'] ] ) ) {
				return new WP_Error( 'token', 'Geçersiz anahtar' );
			}

			$ok = openssl_verify(
				$parts[0] . '.' . $parts[1],
				self::b64url_decode( $parts[2] ),
				$certs[ $header['kid'] ],
				OPENSSL_ALGO_SHA256
			);
			if ( 1 !== $ok ) {
				return new WP_Error( 'token', 'İmza doğrulanamadı' );
			}
			if ( ( $payload['aud'] ?? '' ) !== $project_id ) {
				return new WP_Error( 'token', 'aud uyuşmuyor' );
			}
			if ( ( $payload['iss'] ?? '' ) !== 'https://securetoken.google.com/' . $project_id ) {
				return new WP_Error( 'token', 'iss uyuşmuyor' );
			}
			if ( ( $payload['exp'] ?? 0 ) < time() ) {
				return new WP_Error( 'token', 'Token süresi dolmuş' );
			}
			if ( empty( $payload['sub'] ) ) {
				return new WP_Error( 'token', 'uid yok' );
			}

			return $payload['sub'];
		}

		/**
		 * Firestore users/{uid} dokümanını oku.
		 *
		 * @param string $token   Access token.
		 * @param string $project Proje kimliği.
		 * @param string $uid     Kullanıcı kimliği.
		 * @return array{rol:string,branchId:string}|WP_Error
		 */
		public static function kullanici_doc( $token, $project, $uid ) {
			$uid  = rawurlencode( $uid );
			$url  = "https://firestore.googleapis.com/v1/projects/{$project}/databases/(default)/documents/users/{$uid}";
			$resp = wp_remote_get(
				$url,
				array(
					'timeout' => 15,
					'headers' => array( 'Authorization' => 'Bearer ' . $token ),
				)
			);
			if ( is_wp_error( $resp ) ) {
				return $resp;
			}
			if ( 200 !== wp_remote_retrieve_response_code( $resp ) ) {
				return new WP_Error( 'nodoc', 'Kullanıcı kaydı bulunamadı' );
			}
			$d = json_decode( wp_remote_retrieve_body( $resp ), true );
			$f = $d['fields'] ?? array();
			return array(
				'rol'      => $f['rol']['stringValue'] ?? '',
				'branchId' => $f['branchId']['stringValue'] ?? '',
			);
		}

		/**
		 * calls koleksiyonuna doküman yaz.
		 *
		 * @param array $fields Firestore alan dizisi.
		 * @return array{name:string}|WP_Error Oluşan dokümanın adı.
		 */
		public static function call_yaz( array $fields ) {
			$token = self::access_token( self::SCOPE_DATASTORE );
			if ( is_wp_error( $token ) ) {
				return $token;
			}

			$project = self::project_id();
			if ( '' === $project ) {
				return new WP_Error( 'proje', 'Firebase proje kimliği bulunamadı.' );
			}

			$url  = "https://firestore.googleapis.com/v1/projects/{$project}/databases/(default)/documents/calls";
			$resp = wp_remote_post(
				$url,
				array(
					'timeout' => 15,
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( array( 'fields' => $fields ) ),
				)
			);
			if ( is_wp_error( $resp ) ) {
				return $resp;
			}

			$code = wp_remote_retrieve_response_code( $resp );
			if ( $code < 200 || $code >= 300 ) {
				return new WP_Error( 'firestore', 'Firestore hatası (' . $code . ')' );
			}

			$created = json_decode( wp_remote_retrieve_body( $resp ), true );
			return array( 'name' => $created['name'] ?? '' );
		}

		/**
		 * Garson / hesap çağrısı oluştur.
		 *
		 * @param string $masa Masa slug'ı (doğrulanmış oturumdan gelir).
		 * @param string $tip  'garson' | 'hesap'.
		 * @return true|WP_Error
		 */
		public static function cagri_olustur( $masa, $tip ) {
			$res = self::call_yaz(
				array(
					'branchId'     => array( 'stringValue' => self::branch_id() ),
					'masaNo'       => array( 'stringValue' => (string) $masa ),
					'tip'          => array( 'stringValue' => (string) $tip ),
					'durum'        => array( 'stringValue' => 'bekliyor' ),
					'onaylayanUid' => array( 'stringValue' => '' ),
					'onaylayanAd'  => array( 'stringValue' => '' ),
					'createdAt'    => array( 'timestampValue' => gmdate( 'Y-m-d\TH:i:s\Z' ) ),
				)
			);
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			return true;
		}
	}
}

/* -------------------------------------------------------------------------
 * Geriye dönük uyumluluk — eski snippet fonksiyon adları
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'qrservis_get_access_token' ) ) {
	/**
	 * @deprecated QMO_Firestore::access_token() kullanın.
	 * @return string|WP_Error
	 */
	function qrservis_get_access_token() {
		return QMO_Firestore::access_token( QMO_Firestore::SCOPE_DATASTORE );
	}
}

if ( ! function_exists( 'qrservis_firestore_create_call' ) ) {
	/**
	 * @deprecated QMO_Firestore::cagri_olustur() kullanın.
	 * @param string $masa Masa slug'ı.
	 * @param string $tip  Çağrı tipi.
	 * @return true|WP_Error
	 */
	function qrservis_firestore_create_call( $masa, $tip ) {
		return QMO_Firestore::cagri_olustur( $masa, $tip );
	}
}
