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
 * $api_key ve $model bilinçli olarak dışarıdan alınabilir: bu fonksiyon,
 * veritabanı bağlantısı BIRAKILMIŞ hâlde bir döngü içinde çağrılır
 * (bkz. qmo_order_ceviri_tamamla). O sırada get_option() bir sorgu açmak
 * zorunda kalırsa sessizce false döner ve çeviri sebepsiz yere hataya
 * düşerdi; çağıran ikisini de bağlantı açıkken çözüp buraya geçirir.
 *
 * @param string      $not     Müşteri notu.
 * @param string      $dil     Notun dili (bilgi amaçlı).
 * @param string|null $api_key Önceden çözülmüş Gemini anahtarı.
 * @param string|null $model   Önceden çözülmüş model adı.
 * @return array{notTr:string,hata:bool}
 */
if ( ! function_exists( 'qmo_not_cevir' ) ) {
	function qmo_not_cevir( $not, $dil, $api_key = null, $model = null ) {
		unset( $dil ); // Dil algılamasına güvenilmez; not boş değilse daima çevrilir.

		$not = trim( (string) $not );
		if ( '' === $not ) {
			return array(
				'notTr' => $not,
				'hata'  => false,
			);
		}

		if ( null === $api_key ) {
			$api_key = get_option( 'gemini_api_key' );
		}
		if ( ! $api_key ) {
			return array(
				'notTr' => $not,
				'hata'  => true,
			);
		}

		if ( null === $model || '' === $model ) {
			$model = qmo_gemini_model();
		}

		$url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . rawurlencode( $api_key );
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
					'msg'     => qmo_ceviri_chat( __( 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyin.', 'qrms' ) ),
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
					'msg'     => qmo_ceviri_chat( __( 'Oturum süreniz doldu. Devam etmek için masadaki QR kodu tekrar okutun.', 'qrms' ) ),
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
				'msg'     => qmo_ceviri_chat( __( 'Geçersiz sipariş', 'qrms' ) ),
				'http'    => 400,
			);
		}

		$temiz = array();
		foreach ( $items as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			$ad      = sanitize_text_field( (string) ( $it['urunAdi'] ?? '' ) );
			$adet    = max( 1, min( 20, (int) ( $it['adet'] ?? 1 ) ) );
			$not     = mb_substr( sanitize_textarea_field( (string) ( $it['not'] ?? '' ) ), 0, 200 );
			$item_id = isset( $it['itemId'] ) ? absint( $it['itemId'] ) : ( isset( $it['item_id'] ) ? absint( $it['item_id'] ) : 0 );
			if ( '' === $ad ) {
				continue;
			}
			$temiz[] = array(
				'urunAdi'  => $ad,
				'adet'     => $adet,
				'not'      => $not,
				'item_id'  => $item_id,
			);
		}
		if ( empty( $temiz ) ) {
			return array(
				'success' => false,
				'msg'     => qmo_ceviri_chat( __( 'Geçersiz sipariş', 'qrms' ) ),
				'http'    => 400,
			);
		}

		// Restoran menü "Tükendi" durumu siparişi burada keser (varsa).
		$engel = apply_filters( 'qmo_siparis_onay_oncesi', null, $temiz );
		$mesaj = '';
		$engel_id = 0;
		$engel_ad = '';
		if ( is_array( $engel ) ) {
			$mesaj    = isset( $engel['mesaj'] ) ? (string) $engel['mesaj'] : '';
			$engel_id = isset( $engel['item_id'] ) ? absint( $engel['item_id'] ) : 0;
			$engel_ad = isset( $engel['item_name'] ) ? sanitize_text_field( (string) $engel['item_name'] ) : '';
		} elseif ( is_string( $engel ) && '' !== $engel ) {
			$mesaj = $engel;
		}
		if ( '' !== $mesaj ) {
			qmo_analitik_siparis_engeli_yaz( $masa, $engel_id, $engel_ad );
			return array(
				'success' => false,
				'msg'     => $mesaj,
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

		// Firestore yazımı 15 saniyeye kadar sürebilir; bağlantı o süre
		// boyunca kullanılmayacağı için bırakılır.
		$db_kapali = qmo_db_serbest_birak();

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

		qmo_db_geri_baglan( $db_kapali );

		// Analitik, Firestore sonucuna göre order_sent / order_failed yazar.
		// Insert, bağlantı geri açıldıktan SONRA yapılır: serbest pencerede
		// $wpdb->insert sessizce false dönerdi. Ürün çözümlemesi de buradadır
		// (get_post). Firestore beklenmeden analitik atlanmaz — her deneme
		// bir tipe düşer.
		$olay_tip = is_wp_error( $res ) ? 'order_failed' : 'order_sent';
		qmo_analitik_siparis_yaz( $olay_tip, $masa, $temiz );

		if ( is_wp_error( $res ) ) {
			qmo_log( 'Sipariş yazılamadı: ' . $res->get_error_message() );
			return array(
				'success' => false,
				'msg'     => qmo_ceviri_chat( __( 'Sipariş iletilemedi, lütfen garsona bildirin.', 'qrms' ) ),
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
 * Sipariş kalemlerini analitik satırlarına yazar.
 *
 * Adet kadar satır YAZILMAZ: şemada adet sütunu yoktur ve satırı çoğaltmak
 * "kaç siparişte bu ürün vardı" ile "kaç porsiyon gitti"yi karıştırır.
 * cart_add da tıklama başına bir satırdır; huninin cart_add → order_sent
 * dönüşümü aynı birimde kalır. Her kalem (satır) için bir olay.
 *
 * @param string $tip   order_sent | order_failed.
 * @param string $masa  Masa slug'ı.
 * @param array  $temiz Temizlenmiş kalemler.
 * @return void
 */
if ( ! function_exists( 'qmo_analitik_siparis_yaz' ) ) {
	function qmo_analitik_siparis_yaz( $tip, $masa, $temiz ) {
		if ( ! class_exists( 'QRMS_Analitik' ) ) {
			return;
		}

		$tip = sanitize_key( (string) $tip );

		if ( ! in_array( $tip, array( 'order_sent', 'order_failed' ), true ) ) {
			return;
		}

		foreach ( (array) $temiz as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}

			$id  = isset( $it['item_id'] ) ? absint( $it['item_id'] ) : 0;
			$ad  = isset( $it['urunAdi'] ) ? (string) $it['urunAdi'] : '';
			$alan = $id ? qmo_analitik_urun_alani( $id ) : array();

			if ( empty( $alan ) ) {
				$alan = qmo_analitik_urun_ada_gore( $ad );
			}

			$adet = isset( $it['adet'] ) ? max( 1, min( 999, absint( $it['adet'] ) ) ) : 1;

			qmo_analitik_yaz(
				array(
					'event_type'    => $tip,
					'item_id'       => isset( $alan['item_id'] ) ? (int) $alan['item_id'] : 0,
					'item_name'     => isset( $alan['item_name'] ) && '' !== $alan['item_name'] ? $alan['item_name'] : $ad,
					'category_name' => isset( $alan['category_name'] ) ? $alan['category_name'] : '',
					'masa_no'       => $masa,
					'qty'           => $adet,
				)
			);
		}
	}
}

/**
 * Tükendi filtresinin kestiği siparişi kaydeder.
 *
 * @param string $masa     Masa slug'ı.
 * @param int    $item_id  Engelleyen ürün.
 * @param string $item_ad  Engelleyen ürün adı.
 * @return void
 */
if ( ! function_exists( 'qmo_analitik_siparis_engeli_yaz' ) ) {
	function qmo_analitik_siparis_engeli_yaz( $masa, $item_id, $item_ad ) {
		if ( ! class_exists( 'QRMS_Analitik' ) ) {
			return;
		}

		$item_id = absint( $item_id );
		$alan    = $item_id ? qmo_analitik_urun_alani( $item_id ) : array();

		if ( empty( $alan ) && '' !== (string) $item_ad ) {
			$alan = qmo_analitik_urun_ada_gore( $item_ad );
		}

		qmo_analitik_yaz(
			array(
				'event_type'    => 'order_blocked',
				'item_id'       => isset( $alan['item_id'] ) ? (int) $alan['item_id'] : $item_id,
				'item_name'     => isset( $alan['item_name'] ) && '' !== $alan['item_name'] ? $alan['item_name'] : sanitize_text_field( (string) $item_ad ),
				'category_name' => isset( $alan['category_name'] ) ? $alan['category_name'] : '',
				'masa_no'       => $masa,
			)
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
		// HTTP bağlantısını kapat ki müşteri beklemesin (destekleyen sunucularda).
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}

		// Access token transient'ten okunur (gerekirse yazılır), yani BU adım
		// hâlâ veritabanına ihtiyaç duyar — bu yüzden bırakmadan önce yapılır.
		$token = QMO_Firestore::access_token( QMO_Firestore::SCOPE_DATASTORE );
		if ( is_wp_error( $token ) ) {
			return;
		}

		/*
		 * Buradan sonrası tamamen dış istek: her not için 20 saniyeye kadar bir
		 * Gemini çevirisi (yirmi üründe dakikalar) ve ardından 30 saniyelik
		 * Firestore PATCH'i. Müşteri çoktan yanıtını almış, sayfa kapanmış olsa
		 * bile PHP süreci — ve tuttuğu MySQL bağlantısı — bütün bu süre boyunca
		 * ayakta kalıyordu. Tek bir sorgu bile çalıştırmadan.
		 *
		 * Bağlantı bu yüzden bırakılır; önce döngünün ihtiyaç duyduğu ayarlar
		 * (anahtar ve model) bağlantı AÇIKKEN çözülür, iş bitince de bağlantı
		 * geri açılır — shutdown zincirinin kalanı veritabanına dokunabilir.
		 */
		$api_key = get_option( 'gemini_api_key' );
		$model   = qmo_gemini_model();

		$db_kapali = qmo_db_serbest_birak();

		$yeni = array();
		foreach ( $temiz as $idx => $it ) {
			$cev = qmo_not_cevir( $it['not'], $dil, $api_key, $model );
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

		// shutdown zincirinin geri kalanı (obje önbelleği kapanışı, cron
		// tetikleme vb.) veritabanına dokunabilir; bağlantı geri açılır.
		qmo_db_geri_baglan( $db_kapali );
	}
}
