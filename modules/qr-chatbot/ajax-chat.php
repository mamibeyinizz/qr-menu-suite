<?php
/**
 * AJAX: gemini_chat_req — chatbot mesajı.
 *
 * GÜVENLİK: Bu uç eskiden tamamen korumasızdı. admin-ajax.php herkese
 * açıktır; döngüyle POST atan biri Gemini faturasını sınırsız şişirebilirdi.
 * Artık her istek qmo_chat_zorla() ile başlar:
 *   - nonce doğrulaması,
 *   - geçerli HMAC masa oturumu,
 *   - oturum başına mesaj limiti (varsayılan 25).
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_gemini_chat_req', 'qmo_ajax_chat' );
add_action( 'wp_ajax_nopriv_gemini_chat_req', 'qmo_ajax_chat' );

/**
 * Chatbot isteğini Gemini'ye ilet.
 */
if ( ! function_exists( 'qmo_ajax_chat' ) ) {
	function qmo_ajax_chat() {
		// Oturum + nonce + mesaj limiti. Başarısızsa burada sonlanır.
		qmo_chat_zorla();

		$api_key = get_option( 'gemini_api_key' );
		if ( empty( $api_key ) ) {
			wp_send_json_error( 'Hata: Gemini API anahtarı boş. Ayarlar sayfasından API anahtarını girin.' );
		}

		$message = sanitize_text_field( wp_unslash( $_POST['message'] ?? '' ) );
		$message = mb_substr( $message, 0, 1000 );
		if ( '' === $message ) {
			wp_send_json_error( 'Hata: Mesaj boş geldi.' );
		}

		$system_prompt = get_option( 'gemini_system_prompt', '' );
		$history_raw   = isset( $_POST['history'] ) ? wp_unslash( $_POST['history'] ) : '';

		$final_prompt = $system_prompt
			. qmo_chat_garson_talimati()
			. qmo_chat_siparis_talimati()
			. qmo_chat_menu_talimati();

		// Çok adımlı sipariş onay akışının çalışabilmesi için önceki turlar
		// 'contents' dizisinde geçmiş olarak gönderilir; aksi halde AI her
		// mesajda hafızasız başlar ve önceki adımları hatırlayamaz.
		$contents        = array();
		$decoded_history = json_decode( $history_raw, true );
		if ( is_array( $decoded_history ) ) {
			// Geçmişi sınırla — istemci sınırsız uzun geçmiş göndererek
			// istek başına token maliyetini şişirebilir.
			$decoded_history = array_slice( $decoded_history, -20 );
			foreach ( $decoded_history as $turn ) {
				if ( ! is_array( $turn ) ) {
					continue;
				}
				$role = ( isset( $turn['role'] ) && 'model' === $turn['role'] ) ? 'model' : 'user';
				$text = isset( $turn['parts'][0]['text'] ) ? sanitize_textarea_field( $turn['parts'][0]['text'] ) : '';
				if ( '' === $text ) {
					continue;
				}
				$contents[] = array(
					'role'  => $role,
					'parts' => array( array( 'text' => mb_substr( $text, 0, 4000 ) ) ),
				);
			}
		}
		$contents[] = array(
			'role'  => 'user',
			'parts' => array( array( 'text' => $message ) ),
		);

		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . qmo_gemini_model() . ':generateContent?key=' . rawurlencode( $api_key );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 45,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'contents'          => $contents,
						'systemInstruction' => array( 'parts' => array( array( 'text' => $final_prompt ) ) ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( 'Bağlantı hatası: ' . $response->get_error_message() );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			wp_send_json_success( $data['candidates'][0]['content']['parts'][0]['text'] );
		} elseif ( isset( $data['error']['message'] ) ) {
			// API anahtarı gibi ayrıntılar müşteriye sızmasın; log'a yaz.
			qmo_log( 'Gemini API hatası: ' . $data['error']['message'] );
			wp_send_json_error( 'Asistan şu anda yanıt veremiyor, lütfen tekrar deneyin.' );
		} elseif ( isset( $data['candidates'][0]['finishReason'] ) ) {
			wp_send_json_error( 'Yanıt alınamadı, sebep: ' . sanitize_text_field( $data['candidates'][0]['finishReason'] ) );
		} else {
			qmo_log( 'Beklenmeyen Gemini yanıtı. HTTP: ' . wp_remote_retrieve_response_code( $response ) );
			wp_send_json_error( 'Asistan şu anda yanıt veremiyor, lütfen tekrar deneyin.' );
		}
	}
}

/**
 * Garson/hesap tetikleme talimatı.
 *
 * @return string
 */
if ( ! function_exists( 'qmo_chat_garson_talimati' ) ) {
	function qmo_chat_garson_talimati() {
		return "\n\nKURAL: Eğer kullanıcı garson çağırmak, personel istemek veya 'garson bakar mısın', 'bakar mısınız' gibi taleplerde bulunursa, cevabın en başına kesinlikle '[CALL_WAITER]' metnini ekle.\n"
			. "Eğer kullanıcı hesap ödemek, hesap istemek, 'hesap verir misiniz', 'hesap alabilir miyim' gibi bir talepte bulunursa cevabın en başına kesinlikle '[CALL_BILL]' metnini ekle.";
	}
}

/**
 * Çok adımlı sipariş onay akışı talimatı.
 *
 * @return string
 */
if ( ! function_exists( 'qmo_chat_siparis_talimati' ) ) {
	function qmo_chat_siparis_talimati() {
		return "\n\nSİPARİŞ KURALI (ÇOK ADIMLI ONAY AKIŞI): Sipariş vermek asla tek adımda gerçekleşmez, konuşma geçmişini dikkate alarak aşağıdaki akışı izle. Sabit bir soru-cevap turu sayısı YOK, konuşmanın doğal akışına sen karar ver — ama şu kural HER ZAMAN sabit kalır: kullanıcıdan AÇIK ONAY gelmeden [SIPARIS] bloğu KESİNLİKLE üretilmez.\n"
			. "1) Kullanıcı bir ürün istediğinde (örnek: '2 adana kebap istiyorum'): [SIPARIS] bloğu ÜRETME. Bunun yerine ürünün adını, menü verisinde varsa kısa açıklamasını ve fiyatını belirt, ardından menüdeki GERÇEK bir ürünü doğal bir çapraz satış önerisi olarak sun (örnek: 'Yanında ayran veya bir salata ister misiniz?'). Uydurma ürün veya fiyat önerme, sadece menü verisinde gerçekten var olanları kullan.\n"
			. "2) Kullanıcı çapraz satış önerisine cevap verdikten veya başka ürün ekleyip eklemeyeceğini belirttikten sonra: [SIPARIS] bloğu HÂLÂ ÜRETME. Bu adımda siparişin özetini (ürünler, adetler, toplam TL tutarı) yaz ve net bir onay sorusu sor (örnek: 'Onaylarsanız hemen siparişi mutfağa/garsona ileteceğim, onaylıyor musunuz?').\n"
			. "3) Kullanıcı açıkça onay verdiğinde (örnek: 'evet', 'onaylıyorum', 'tamam gönder', 'olur' gibi doğal onay ifadeleri) — SADECE bu turda, cevabının EN BAŞINA şu formatta bir blok ekle: [SIPARIS]json[/SIPARIS]. Buradaki json şu dizidir: [{\"urunAdi\":\"...\",\"adet\":1,\"not\":\"...\"}]. Kurallar: urunAdi MUTLAKA menüdeki ürünün TÜRKÇE adı olsun (kullanıcı başka dilde yazsa bile menüdeki Türkçe karşılığını yaz). adet tam sayı olsun. not alanına müşterinin özel isteğini (soğansız, acısız, az pişmiş vb.) MUTLAKA TÜRKÇE yaz; özel istek yoksa not boş string olsun. Sipariş bloğundan sonra kullanıcıya kendi dilinde kısa bir onay mesajı yaz (örnek: siparişin mutfağa iletildi).\n"
			. "4) Kullanıcı henüz onay vermeden başka bir şey sorarsa, ürün eklemek/çıkarmak isterse konuşmaya doğal şekilde devam et, esnek kal.\n"
			. '5) Menüde olmayan bir ürün istenirse [SIPARIS] bloğu hiçbir zaman eklenmez, kibarca o ürünün olmadığını söyle.';
	}
}

/**
 * Menü verisi talimatı (Ayarlar → Menü Verisi'nden yüklenen JSON).
 *
 * @return string
 */
if ( ! function_exists( 'qmo_chat_menu_talimati' ) ) {
	function qmo_chat_menu_talimati() {
		$menu_json = get_option( 'gemini_menu_json_data', '' );
		if ( empty( $menu_json ) ) {
			return '';
		}
		return "\n\nAşağıda restoranın güncel menü verisi JSON formatında verilmiştir. Ürün, fiyat, kategori ve içerik ile ilgili sorularda öncelikli olarak bu veriyi kullan, bu veride olmayan bir ürün hakkında soru sorulursa net bir dille böyle bir ürün bulunmadığını belirt:\n" . $menu_json;
	}
}
