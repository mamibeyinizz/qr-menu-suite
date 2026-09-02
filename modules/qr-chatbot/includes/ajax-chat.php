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
		$oturum_gerekli = ! function_exists( 'qmo_chatbot_oturum_zorunlu_mu' ) || qmo_chatbot_oturum_zorunlu_mu();
		if ( $oturum_gerekli || ( function_exists( 'qmo_oturum' ) && qmo_oturum() ) ) {
			$sess = qmo_chat_zorla();
		} else {
			qmo_nonce_dogrula();
			$sess = array( 'masa' => '' );
		}

		$api_key = get_option( 'gemini_api_key' );
		if ( empty( $api_key ) ) {
			qmo_log( 'Gemini API anahtarı boş.' );
			wp_send_json_error( qmo_ceviri_chat( __( 'Asistan şu anda yanıt veremiyor, lütfen tekrar deneyin.', 'qrms' ) ) );
		}

		$message = sanitize_text_field( wp_unslash( $_POST['message'] ?? '' ) );
		$message = mb_substr( $message, 0, 1000 );
		if ( '' === $message ) {
			wp_send_json_error( qmo_ceviri_chat( __( 'Hata: Mesaj boş geldi.', 'qrms' ) ) );
		}

		if ( function_exists( 'qmo_chatbot_sinir_kontrol' ) ) {
			qmo_chatbot_sinir_kontrol( $sess );
		}

		if ( function_exists( 'qmo_chatbot_yasakli_mi' ) && qmo_chatbot_yasakli_mi( $message ) ) {
			$uyari = function_exists( 'qmo_chatbot_ayar' ) ? qmo_chatbot_ayar( 'qmo_chatbot_banned_msg' ) : __( 'Bu konuda yardımcı olamam.', 'qrms' );
			$uyari = qmo_ceviri_chat( $uyari );
			qmo_chatbot_gecmis_yaz( $sess, $message, $uyari );
			wp_send_json_success( $uyari );
		}

		// Analitik: yalnızca geçerli (limit/nonce/oturum + dolu mesaj) istek.
		// Hız sınırına takılanlar qmo_chat_zorla içinde biter, buraya gelmez.
		// Mesaj İÇERİĞİ yazılmaz — kişisel veri; yalnızca olay sayılır.
		if ( function_exists( 'qmo_analitik_yaz' ) ) {
			qmo_analitik_yaz(
				array(
					'event_type' => 'chatbot_message',
					'masa_no'    => isset( $sess['masa'] ) ? (string) $sess['masa'] : '',
				)
			);
		}

		$system_prompt = get_option( 'gemini_system_prompt', '' );
		$history_raw   = isset( $_POST['history'] ) ? wp_unslash( $_POST['history'] ) : '';

		$decoded_history = json_decode( $history_raw, true );
		if ( ! is_array( $decoded_history ) ) {
			$decoded_history = array();
		}

		$final_prompt = $system_prompt
			. qmo_chat_garson_talimati()
			. qmo_chat_siparis_talimati()
			. qmo_chat_menu_talimati( $message, $decoded_history )
			. qmo_chat_bilemedi_talimati();

		// Çok adımlı sipariş onay akışının çalışabilmesi için önceki turlar
		// 'contents' dizisinde geçmiş olarak gönderilir; aksi halde AI her
		// mesajda hafızasız başlar ve önceki adımları hatırlayamaz.
		$contents = array();
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
		$contents[] = array(
			'role'  => 'user',
			'parts' => array( array( 'text' => $message ) ),
		);

		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . qmo_gemini_model() . ':generateContent?key=' . rawurlencode( $api_key );

		// Buraya kadarki veritabanı işi (oturum doğrulama, mesaj limiti, ayar
		// okuma) bitti; sıradaki adım 45 saniyeye kadar sürebilen bir Gemini
		// çağrısı. Bağlantı o süre boyunca kullanılmayacağı için bırakılır —
		// yoğun saatte eşzamanlı sohbetler havuzu böyle tüketmez.
		$db_kapali = qmo_db_serbest_birak();

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

		// Yanıtı basmadan önce geri bağlan: wp_send_json_* → wp_die zinciri
		// (ve shutdown kancaları) veritabanına dokunabilir.
		qmo_db_geri_baglan( $db_kapali );

		if ( is_wp_error( $response ) ) {
			qmo_log( 'Gemini bağlantı hatası: ' . $response->get_error_message() );
			wp_send_json_error( qmo_ceviri_chat( __( 'Bağlantı hatası oluştu.', 'qrms' ) ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$cevap = $data['candidates'][0]['content']['parts'][0]['text'];
			qmo_chatbot_gecmis_yaz( $sess, $message, $cevap );
			wp_send_json_success( $cevap );
		} elseif ( isset( $data['error']['message'] ) ) {
			// API anahtarı gibi ayrıntılar müşteriye sızmasın; log'a yaz.
			qmo_log( 'Gemini API hatası: ' . $data['error']['message'] );
			wp_send_json_error( qmo_ceviri_chat( __( 'Asistan şu anda yanıt veremiyor, lütfen tekrar deneyin.', 'qrms' ) ) );
		} elseif ( isset( $data['candidates'][0]['finishReason'] ) ) {
			qmo_log( 'Gemini finishReason: ' . sanitize_text_field( $data['candidates'][0]['finishReason'] ) );
			wp_send_json_error( qmo_ceviri_chat( __( 'Asistan şu anda yanıt veremiyor, lütfen tekrar deneyin.', 'qrms' ) ) );
		} else {
			qmo_log( 'Beklenmeyen Gemini yanıtı. HTTP: ' . wp_remote_retrieve_response_code( $response ) );
			wp_send_json_error( qmo_ceviri_chat( __( 'Asistan şu anda yanıt veremiyor, lütfen tekrar deneyin.', 'qrms' ) ) );
		}
	}
}

/**
 * Sohbet geçmişine yazar; bilemediyse ayrı tabloya da işler.
 *
 * @param array  $sess   Oturum.
 * @param string $soru   Ziyaretçi sorusu.
 * @param string $cevap  Bot cevabı.
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_gecmis_yaz' ) ) {
	function qmo_chatbot_gecmis_yaz( $sess, $soru, $cevap ) {
		if ( ! class_exists( 'QMO_Chatbot_DB' ) ) {
			return;
		}

		$oturum = function_exists( 'qmo_chatbot_ziyaretci_anahtar' )
			? qmo_chatbot_ziyaretci_anahtar( is_array( $sess ) ? $sess : array() )
			: '';
		$masa   = isset( $sess['masa'] ) ? (string) $sess['masa'] : '';

		QMO_Chatbot_DB::mesaj_yaz( $oturum, $masa, $soru, $cevap );

		if ( function_exists( 'qmo_chatbot_bilemedi_mi' ) && qmo_chatbot_bilemedi_mi( $cevap ) ) {
			QMO_Chatbot_DB::bilinmeyen_yaz( $soru );
		}
	}
}

/**
 * Bilemediğinde [BILEMEDI] etiketi üretme talimatı.
 *
 * @return string
 */
if ( ! function_exists( 'qmo_chat_bilemedi_talimati' ) ) {
	function qmo_chat_bilemedi_talimati() {
		return "\n\nBİLMEDİĞİN SORULAR: Menü verisinde, restoran bilgelerinde veya bu talimatlarda karşılığı olmayan bir soruyu uydurarak cevaplama. Böyle bir durumda cevabının EN BAŞINA tam olarak [BILEMEDI] yaz, ardından kibarca bilmediğini söyle.";
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
			. "5) Menüde olmayan bir ürün istenirse [SIPARIS] bloğu hiçbir zaman eklenmez, kibarca o ürünün olmadığını söyle.\n"
			. "6) Menü JSON'unda tukendi değeri 1 olan ürünler şu an stokta yoktur. Müşteri bunları isterse [SIPARIS] bloğu KESİNLİKLE üretilmez; kullanıcıya 'Bu ürün şu an tükendi' de.";
	}
}

/**
 * Menü verisi talimatı — öneri havuzu filtrelenmiş ve sıralanmış ürün listesi.
 *
 * @param string $message Kullanıcı mesajı.
 * @param array  $history Sohbet geçmişi (ham dizi).
 * @return string
 */
if ( ! function_exists( 'qmo_chat_menu_talimati' ) ) {
	function qmo_chat_menu_talimati( $message = '', $history = array() ) {
		$havuz = qmo_chat_oneri_havuzu_hazirla( $message, $history );
		if ( empty( $havuz['urunler'] ) ) {
			return '';
		}

		$menu_json = wp_json_encode( $havuz['urunler'], JSON_UNESCAPED_UNICODE );
		if ( ! $menu_json ) {
			return '';
		}

		$metin  = "\n\nAşağıda restoranın güncel menü verisi JSON formatında verilmiştir. Ürün, fiyat, kategori ve içerik ile ilgili sorularda öncelikli olarak bu veriyi kullan, bu veride olmayan bir ürün hakkında soru sorulursa net bir dille böyle bir ürün bulunmadığını belirt:";
		$metin .= "\n\nÖNERİ KURALI: Yalnızca aşağıdaki listede yer alan ürünleri önerebilirsin; listede olmayan bir ürünü uydurarak önerme.";

		if ( ! empty( $havuz['cross_sell'] ) ) {
			$metin .= "\n\nBu ürünle iyi giden ürünler: " . implode( ', ', $havuz['cross_sell'] ) . '.';
		}

		return $metin . "\n" . $menu_json;
	}
}

/**
 * Öneri havuzunu filtreler, sıralar ve cross-sell bilgisini üretir.
 *
 * @param string $message Kullanıcı mesajı.
 * @param array  $history Sohbet geçmişi.
 * @return array{urunler:array<int,array<string,string>>,cross_sell:string[]}
 */
if ( ! function_exists( 'qmo_chat_oneri_havuzu_hazirla' ) ) {
	function qmo_chat_oneri_havuzu_hazirla( $message, $history ) {
		$katalog = qmo_chat_oneri_urun_katalogu();
		if ( empty( $katalog ) ) {
			return array(
				'urunler'    => array(),
				'cross_sell' => array(),
			);
		}

		$uygun = array();
		foreach ( $katalog as $id => $urun ) {
			if ( ! qmo_chat_oneri_havuza_uygun_mu( $id, $urun ) ) {
				continue;
			}
			$uygun[ $id ] = $urun;
		}

		$kaynak_id   = qmo_chat_oneri_kaynak_urun_bul( $message, $history, $katalog );
		$cross_ids   = array();
		$cross_isim  = array();

		if ( $kaynak_id > 0 && class_exists( 'QMO_Chatbot_DB' ) ) {
			$kurallar = QMO_Chatbot_DB::kurallari_getir( $kaynak_id );
			foreach ( $kurallar as $kural ) {
				$hedef = (int) $kural->hedef_urun;
				if ( $hedef < 1 || ! isset( $uygun[ $hedef ] ) ) {
					continue;
				}
				if ( in_array( $hedef, $cross_ids, true ) ) {
					continue;
				}
				$cross_ids[]  = $hedef;
				$cross_isim[] = $uygun[ $hedef ]['urunAdi'];
			}
		}

		$sonra = array();
		foreach ( $uygun as $id => $urun ) {
			if ( in_array( (int) $id, $cross_ids, true ) ) {
				continue;
			}
			$sonra[ $id ] = $urun;
		}

		uasort(
			$sonra,
			function ( $a, $b ) {
				$fa = isset( $a['agirlik'] ) ? (int) $a['agirlik'] : 0;
				$fb = isset( $b['agirlik'] ) ? (int) $b['agirlik'] : 0;
				if ( $fa === $fb ) {
					return strcmp( $a['urunAdi'], $b['urunAdi'] );
				}
				return ( $fa > $fb ) ? -1 : 1;
			}
		);

		$sirali = array();
		foreach ( $cross_ids as $cid ) {
			if ( isset( $uygun[ $cid ] ) ) {
				$sirali[] = qmo_chat_oneri_json_satir( $uygun[ $cid ] );
			}
		}
		foreach ( $sonra as $urun ) {
			$sirali[] = qmo_chat_oneri_json_satir( $urun );
		}

		return array(
			'urunler'    => $sirali,
			'cross_sell' => $cross_isim,
		);
	}
}

/**
 * Menü ürün kataloğu (ID indeksli, dahili sıralama alanlarıyla).
 *
 * @return array<int,array<string,mixed>>
 */
if ( ! function_exists( 'qmo_chat_oneri_urun_katalogu' ) ) {
	function qmo_chat_oneri_urun_katalogu() {
		if ( post_type_exists( 'rma_menu_item' ) ) {
			return qmo_chat_oneri_katalog_cpt();
		}

		return qmo_chat_oneri_katalog_json();
	}
}

/**
 * rma_menu_item CPT'sinden katalog oluşturur.
 *
 * @return array<int,array<string,mixed>>
 */
if ( ! function_exists( 'qmo_chat_oneri_katalog_cpt' ) ) {
	function qmo_chat_oneri_katalog_cpt() {
		$posts = get_posts(
			array(
				'post_type'              => 'rma_menu_item',
				'post_status'            => 'publish',
				'posts_per_page'         => 500,
				'orderby'                => 'menu_order',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
			)
		);

		$katalog = array();
		foreach ( $posts as $post ) {
			$fiyat = function_exists( 'rma_get_effective_price' )
				? rma_get_effective_price( $post->ID )
				: get_post_meta( $post->ID, 'rma_price', true );
			$kat   = wp_get_post_terms( $post->ID, 'rma_category', array( 'fields' => 'names' ) );
			$agir  = (int) get_post_meta( $post->ID, '_qmo_oneri_agirlik', true );
			if ( $agir < 1 ) {
				$agir = 50;
			}

			$katalog[ (int) $post->ID ] = array(
				'id'       => (int) $post->ID,
				'urunAdi'  => $post->post_title,
				'kategori' => ( is_array( $kat ) && $kat ) ? $kat[0] : '',
				'aciklama' => wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : $post->post_content ),
				'fiyat'    => is_numeric( $fiyat ) ? (string) $fiyat : (string) $fiyat,
				'dahil'    => get_post_meta( $post->ID, '_qmo_oneri_dahil', true ),
				'agirlik'  => max( 0, min( 100, $agir ) ),
				'tukendi'  => qmo_chat_oneri_tukendi_mi( $post->ID ),
			);
		}

		return $katalog;
	}
}

/**
 * Kayıtlı menü JSON'ından katalog oluşturur (CPT yoksa yedek).
 *
 * @return array<int,array<string,mixed>>
 */
if ( ! function_exists( 'qmo_chat_oneri_katalog_json' ) ) {
	function qmo_chat_oneri_katalog_json() {
		$menu_json = get_option( 'gemini_menu_json_data', '' );
		if ( '' === $menu_json ) {
			return array();
		}

		$ham = json_decode( $menu_json, true );
		if ( ! is_array( $ham ) ) {
			return array();
		}

		$katalog = array();
		$sira    = 0;
		foreach ( $ham as $satir ) {
			if ( ! is_array( $satir ) || empty( $satir['urunAdi'] ) ) {
				continue;
			}
			++$sira;
			$ad    = (string) $satir['urunAdi'];
			$post  = get_page_by_title( $ad, OBJECT, 'rma_menu_item' );
			$pid   = ( $post && isset( $post->ID ) ) ? (int) $post->ID : ( 100000 + $sira );
			$agir  = $post ? (int) get_post_meta( $post->ID, '_qmo_oneri_agirlik', true ) : 50;
			$dahil = $post ? get_post_meta( $post->ID, '_qmo_oneri_dahil', true ) : '';

			if ( $agir < 1 ) {
				$agir = 50;
			}

			$katalog[ $pid ] = array(
				'id'       => $pid,
				'urunAdi'  => $ad,
				'kategori' => isset( $satir['kategori'] ) ? (string) $satir['kategori'] : '',
				'aciklama' => isset( $satir['aciklama'] ) ? (string) $satir['aciklama'] : '',
				'fiyat'    => isset( $satir['fiyat'] ) ? (string) $satir['fiyat'] : '',
				'dahil'    => $dahil,
				'agirlik'  => max( 0, min( 100, $agir ) ),
				'tukendi'  => ! empty( $satir['tukendi'] ) ? 1 : ( $post ? (int) qmo_chat_oneri_tukendi_mi( $post->ID ) : 0 ),
			);
		}

		return $katalog;
	}
}

/**
 * Ürün öneri havuzuna dahil edilebilir mi?
 *
 * @param int                  $id   Ürün kimliği.
 * @param array<string,mixed>  $urun Katalog satırı.
 * @return bool
 */
if ( ! function_exists( 'qmo_chat_oneri_havuza_uygun_mu' ) ) {
	function qmo_chat_oneri_havuza_uygun_mu( $id, $urun ) {
		$dahil = isset( $urun['dahil'] ) ? (string) $urun['dahil'] : '';
		if ( '0' === $dahil ) {
			return false;
		}

		if ( ! empty( $urun['tukendi'] ) ) {
			return false;
		}

		// Ürün bazlı servis saati meta'sı repoda yok. qrms_cs_is_open_at()
		// yalnızca restoran düzeyinde çalışır; ürün kısıtı için kullanılmaz.

		return true;
	}
}

/**
 * Ürün tükendi mi?
 *
 * @param int $post_id Ürün kimliği.
 * @return bool
 */
if ( ! function_exists( 'qmo_chat_oneri_tukendi_mi' ) ) {
	function qmo_chat_oneri_tukendi_mi( $post_id ) {
		if ( function_exists( 'rma_urun_tukendi' ) ) {
			return rma_urun_tukendi( $post_id );
		}
		if ( class_exists( 'RMA_Tukendi' ) ) {
			return RMA_Tukendi::urun_tukendi( $post_id );
		}

		return '1' === (string) get_post_meta( $post_id, '_rma_tukendi', true );
	}
}

/**
 * Prompt JSON satırı (ağırlık ve dahili alanlar çıkarılır).
 *
 * @param array<string,mixed> $urun Katalog satırı.
 * @return array<string,string>
 */
if ( ! function_exists( 'qmo_chat_oneri_json_satir' ) ) {
	function qmo_chat_oneri_json_satir( $urun ) {
		return array(
			'kategori' => isset( $urun['kategori'] ) ? (string) $urun['kategori'] : '',
			'urunAdi'  => isset( $urun['urunAdi'] ) ? (string) $urun['urunAdi'] : '',
			'aciklama' => isset( $urun['aciklama'] ) ? (string) $urun['aciklama'] : '',
			'fiyat'    => isset( $urun['fiyat'] ) ? (string) $urun['fiyat'] : '',
		);
	}
}

/**
 * Sohbet metninde geçen kaynak ürün kimliğini bulur.
 *
 * @param string               $message Kullanıcı mesajı.
 * @param array                $history Geçmiş.
 * @param array<int,array>     $katalog Ürün kataloğu.
 * @return int
 */
if ( ! function_exists( 'qmo_chat_oneri_kaynak_urun_bul' ) ) {
	function qmo_chat_oneri_kaynak_urun_bul( $message, $history, $katalog ) {
		$parcalar = array( (string) $message );
		foreach ( (array) $history as $turn ) {
			if ( ! is_array( $turn ) ) {
				continue;
			}
			$metin = isset( $turn['parts'][0]['text'] ) ? (string) $turn['parts'][0]['text'] : '';
			if ( '' !== $metin ) {
				$parcalar[] = $metin;
			}
		}

		$birlesik = qmo_chat_oneri_metin_normalize( implode( ' ', $parcalar ) );
		if ( '' === $birlesik ) {
			return 0;
		}

		$adaylar = array();
		foreach ( $katalog as $id => $urun ) {
			$ad = isset( $urun['urunAdi'] ) ? qmo_chat_oneri_metin_normalize( $urun['urunAdi'] ) : '';
			if ( '' === $ad ) {
				continue;
			}
			if ( false !== mb_strpos( $birlesik, $ad, 0, 'UTF-8' ) ) {
				$adaylar[] = array(
					'id'  => (int) $id,
					'len' => mb_strlen( $ad, 'UTF-8' ),
				);
			}
		}

		if ( empty( $adaylar ) ) {
			return 0;
		}

		usort(
			$adaylar,
			function ( $a, $b ) {
				return $b['len'] - $a['len'];
			}
		);

		return (int) $adaylar[0]['id'];
	}
}

/**
 * Ürün adı eşleştirmesi için metin sadeleştirme.
 *
 * @param string $metin Ham metin.
 * @return string
 */
if ( ! function_exists( 'qmo_chat_oneri_metin_normalize' ) ) {
	function qmo_chat_oneri_metin_normalize( $metin ) {
		if ( class_exists( 'RMA_Tukendi' ) ) {
			return RMA_Tukendi::ad_normalize( $metin );
		}

		$metin = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $metin ), 'UTF-8' ) : strtolower( trim( (string) $metin ) );
		return preg_replace( '/\s+/u', ' ', $metin );
	}
}
