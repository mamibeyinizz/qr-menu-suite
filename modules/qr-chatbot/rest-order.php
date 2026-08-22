<?php
/**
 * REST: POST /wp-json/qrservis/v1/order — müşteri siparişi.
 *
 * Girdi (JSON): { items:[{urunAdi, adet, not}], dil }
 * Masa CLIENT'TAN ALINMAZ; doğrulanmış HMAC oturum cookie'sinden okunur.
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'qmo_rest_order_kaydet' );

/**
 * Rotayı kaydet.
 */
if ( ! function_exists( 'qmo_rest_order_kaydet' ) ) {
	function qmo_rest_order_kaydet() {
		register_rest_route(
			'qrservis/v1',
			'/order',
			array(
				'methods'             => 'POST',
				'callback'            => 'qmo_rest_order',
				// Yetki içeride: HMAC masa oturumu + nonce.
				'permission_callback' => '__return_true',
			)
		);
	}
}

/**
 * Tek bir notu Gemini ile Türkçeye çevir.
 * Hata/timeout → orijinal metin + hata bayrağı.
 *
 * @param string $not Müşteri notu.
 * @param string $dil Notun dili (bilgi amaçlı).
 * @return array{notTr:string,hata:bool}
 */
if ( ! function_exists( 'qmo_not_cevir' ) ) {
	function qmo_not_cevir( $not, $dil ) {
		unset( $dil ); // Dil algılamasına güvenilmez; not boş değilse daima çevrilir.

		$not = trim( (string) $not );
		if ( '' === $not ) {
			return array(
				'notTr' => $not,
				'hata'  => false,
			);
		}

		$api_key = get_option( 'gemini_api_key' );
		if ( ! $api_key ) {
			return array(
				'notTr' => $not,
				'hata'  => true,
			);
		}

		$url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . qmo_gemini_model() . ':generateContent?key=' . rawurlencode( $api_key );
		$body = array(
			'contents'          => array( array( 'parts' => array( array( 'text' => $not ) ) ) ),
			'systemInstruction' => array(
				'parts' => array(
					array(
						'text' => 'Sen bir restoran sipariş notu çevirmenisin. Sana verilen müşteri notunu Türkçeye çevir. SADECE çeviriyi döndür; açıklama, tırnak, ek metin yazma. Not zaten Türkçeyse aynen döndür.',
					),
				),
			),
		);

		$resp = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return array(
				'notTr' => $not,
				'hata'  => true,
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		$txt  = trim( $data['candidates'][0]['content']['parts'][0]['text'] ?? '' );
		if ( '' === $txt ) {
			return array(
				'notTr' => $not,
				'hata'  => true,
			);
		}

		return array(
			'notTr' => $txt,
			'hata'  => false,
		);
	}
}

/**
 * Sipariş ucu.
 *
 * @param WP_REST_Request $req İstek.
 * @return WP_REST_Response
 */
if ( ! function_exists( 'qmo_rest_order' ) ) {
	function qmo_rest_order( WP_REST_Request $req ) {

		// 0) Nonce — cookie tarayıcı tarafından otomatik gönderildiği için
		//    tek başına oturum kontrolü CSRF'e açık kalırdı.
		$nonce = $req->get_header( 'x_wp_nonce' );
		if ( ! $nonce ) {
			$nonce = (string) $req->get_param( '_wpnonce' );
		}
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'kod'     => 'nonce',
					'msg'     => 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyin.',
				),
				403
			);
		}

		// 1) HMAC masa oturumu.
		$sess = qmo_oturum();
		if ( ! $sess ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'kod'     => 'oturum_bitti',
					'msg'     => 'Oturum süreniz doldu. Masadaki QR kodu tekrar okutun.',
				),
				403
			);
		}
		$masa = (string) $sess['masa'];

		// Oturumu tazele (idle sayacı sıfırlansın).
		qmo_cookie_yaz( QMO_Oturum::token_uret( $sess['masa'], $sess['issued'] ) );

		// 2) Girdi + Firestore yazımı (chatbot ucu da aynı işlevi kullanır).
		$sonuc = qmo_siparis_isle( $masa, $req->get_param( 'items' ), $req->get_param( 'dil' ) );

		return new WP_REST_Response(
			array(
				'success' => $sonuc['success'],
				'msg'     => $sonuc['msg'],
			),
			$sonuc['http']
		);
	}
}

/**
 * Siparişi doğrula ve Firestore'a yaz.
 *
 * Hem REST ucu hem chatbot AJAX ucu buraya düşer — böylece sipariş mantığı
 * tek yerde durur ve chatbot'un kendi sitesine HTTP isteği atması gerekmez.
 *
 * ÖNEMLİ: $masa daima doğrulanmış oturumdan gelir, istemciden değil.
 *
 * @param string $masa  Masa slug'ı (doğrulanmış).
 * @param mixed  $items Ürün listesi.
 * @param mixed  $dil   Not dili.
 * @return array{success:bool,msg:string,http:int}
 */
if ( ! function_exists( 'qmo_siparis_isle' ) ) {
	function qmo_siparis_isle( $masa, $items, $dil ) {

		// Firebase yapılandırması.
		if ( ! QMO_Firestore::hazir_mi() ) {
			return array(
				'success' => false,
				'msg'     => 'Sipariş sistemi yapılandırılmamış.',
				'http'    => 500,
			);
		}

		// Hız sınırı: masa + IP başına 10 sn'de 1 sipariş.
		if ( ! qmo_hiz_siniri( 'order', $masa, 10 ) ) {
			return array(
				'success' => false,
				'msg'     => 'Siparişiniz alındı, lütfen bekleyin.',
				'http'    => 429,
			);
		}

		// Girdi doğrulama.
		$dil = substr( sanitize_text_field( (string) $dil ), 0, 5 );
		if ( '' === $dil ) {
			$dil = 'tr';
		}

		if ( ! is_array( $items ) || count( $items ) < 1 || count( $items ) > 20 ) {
			return array(
				'success' => false,
				'msg'     => 'Geçersiz sipariş',
				'http'    => 400,
			);
		}

		$temiz = array();
		foreach ( $items as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			$ad   = sanitize_text_field( (string) ( $it['urunAdi'] ?? '' ) );
			$adet = max( 1, min( 20, (int) ( $it['adet'] ?? 1 ) ) );
			$not  = mb_substr( sanitize_textarea_field( (string) ( $it['not'] ?? '' ) ), 0, 200 );
			if ( '' === $ad ) {
				continue;
			}
			$temiz[] = array(
				'urunAdi' => $ad,
				'adet'    => $adet,
				'not'     => $not,
			);
		}
		if ( empty( $temiz ) ) {
			return array(
				'success' => false,
				'msg'     => 'Geçersiz sipariş',
				'http'    => 400,
			);
		}

		// Restoran menü "Tükendi" durumu siparişi burada keser (varsa).
		$engel = apply_filters( 'qmo_siparis_onay_oncesi', null, $temiz );
		if ( is_string( $engel ) && '' !== $engel ) {
			return array(
				'success' => false,
				'msg'     => $engel,
				'http'    => 409,
			);
		}

		// ÖNCE ham notlarla yaz (çeviri yok) — müşteri beklemesin, garson
		// siparişi anında görsün. Çeviri arka planda tamamlanır.
		$fs_items = array();
		foreach ( $temiz as $it ) {
			$fs_items[] = array(
				'mapValue' => array(
					'fields' => array(
						'urunAdi'     => array( 'stringValue' => $it['urunAdi'] ),
						'adet'        => array( 'integerValue' => (string) $it['adet'] ),
						'notOrijinal' => array( 'stringValue' => $it['not'] ),
						'notTr'       => array( 'stringValue' => $it['not'] ),
						'ceviri_hata' => array( 'booleanValue' => false ),
					),
				),
			);
		}

		$res = QMO_Firestore::call_yaz(
			array(
				'branchId'     => array( 'stringValue' => QMO_Firestore::branch_id() ),
				'masaNo'       => array( 'stringValue' => (string) $masa ),
				'tip'          => array( 'stringValue' => 'siparis' ),
				'durum'        => array( 'stringValue' => 'bekliyor' ),
				'onaylayanUid' => array( 'stringValue' => '' ),
				'onaylayanAd'  => array( 'stringValue' => '' ),
				'notDili'      => array( 'stringValue' => $dil ),
				'items'        => array( 'arrayValue' => array( 'values' => $fs_items ) ),
				'createdAt'    => array( 'timestampValue' => gmdate( 'Y-m-d\TH:i:s\Z' ) ),
			)
		);
		if ( is_wp_error( $res ) ) {
			qmo_log( 'Sipariş yazılamadı: ' . $res->get_error_message() );
			return array(
				'success' => false,
				'msg'     => 'Sipariş iletilemedi, lütfen garsona bildirin.',
				'http'    => 500,
			);
		}

		$doc_name = $res['name'];

		// Çevrilecek not var mı?
		$ceviri_gerekli = false;
		foreach ( $temiz as $it ) {
			if ( '' !== $it['not'] ) {
				$ceviri_gerekli = true;
				break;
			}
		}

		// Müşteriye anında yanıt ver; çeviriyi bağlantı kapandıktan sonra yap.
		if ( $ceviri_gerekli && $doc_name ) {
			add_action(
				'shutdown',
				function () use ( $temiz, $dil, $doc_name, $fs_items ) {
					qmo_order_ceviri_tamamla( $temiz, $dil, $doc_name, $fs_items );
				},
				1
			);
		}

		return array(
			'success' => true,
			'msg'     => '',
			'http'    => 200,
		);
	}
}

/**
 * Arka plan çevirisi — siparişin items alanını PATCH ile günceller.
 *
 * @param array  $temiz    Temizlenmiş ürün listesi.
 * @param string $dil      Not dili.
 * @param string $doc_name Firestore doküman yolu.
 * @param array  $fs_items İlk yazılan Firestore item dizisi.
 */
if ( ! function_exists( 'qmo_order_ceviri_tamamla' ) ) {
	function qmo_order_ceviri_tamamla( $temiz, $dil, $doc_name, $fs_items ) {
		// Bağlantıyı kapat ki müşteri beklemesin (destekleyen sunucularda).
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}

		$token = QMO_Firestore::access_token( QMO_Firestore::SCOPE_DATASTORE );
		if ( is_wp_error( $token ) ) {
			return;
		}

		$yeni = array();
		foreach ( $temiz as $idx => $it ) {
			$cev = qmo_not_cevir( $it['not'], $dil );
			$m   = $fs_items[ $idx ]['mapValue']['fields'];

			$m['notTr']       = array( 'stringValue' => $cev['notTr'] );
			$m['ceviri_hata'] = array( 'booleanValue' => (bool) $cev['hata'] );

			$yeni[] = array( 'mapValue' => array( 'fields' => $m ) );
		}

		$patch_url = 'https://firestore.googleapis.com/v1/' . $doc_name . '?updateMask.fieldPaths=items';
		wp_remote_request(
			$patch_url,
			array(
				'method'  => 'PATCH',
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'fields' => array(
							'items' => array( 'arrayValue' => array( 'values' => $yeni ) ),
						),
					)
				),
			)
		);
	}
}
