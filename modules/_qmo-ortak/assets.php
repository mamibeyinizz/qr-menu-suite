<?php
/**
 * Varlık (CSS/JS) kaydı ve koşullu yüklenmesi.
 *
 * Eski kodda tüm CSS ve JS, kısa kod çıktısının içinde <style>/<script>
 * blokları olarak basılıyordu. Artık her şey assets/ altındaki dosyalarda;
 * kısa kodlar yalnızca ihtiyaç duydukları varlığı enqueue eder, böylece
 * sepet CSS'i sepet olmayan sayfalara yüklenmez.
 *
 * Renk gibi ayara bağlı değerler, statik dosyayı bozmamak için CSS
 * değişkeni olarak wp_add_inline_style() ile eklenir.
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tüm ön yüz varlıklarını kaydet (henüz yükleme yok).
 */
if ( ! function_exists( 'qmo_varliklari_kaydet' ) ) {
	function qmo_varliklari_kaydet() {
		static $kayitli = false;
		if ( $kayitli ) {
			return;
		}
		$kayitli = true;

		// Varlıklar artık tek bir assets/ klasöründe değil, ait oldukları modülün
		// altında duruyor; bu yüzden taban adres handle başına ayrılıyor.
		$ortak   = QRMS_PLUGIN_URL . 'modules/_qmo-ortak/assets/';
		$chatbot = QRMS_PLUGIN_URL . 'modules/qr-chatbot/assets/';

		// Sürüm dosya başına hesaplanır; ortak bir $v kullanmak, dosyalardan
		// yalnızca biri değiştiğinde adresin sabit kalmasına ve eski kopyanın
		// sunulmasına yol açardı.
		wp_register_style(
			'qmo-oturum-kutu',
			$ortak . 'css/oturum-kutu.css',
			array(),
			QRMS_Helpers::asset_version( 'modules/_qmo-ortak/assets/css/oturum-kutu.css' )
		);
		wp_register_style(
			'qmo-chatbot',
			$chatbot . 'css/chatbot.css',
			array(),
			QRMS_Helpers::asset_version( 'modules/qr-chatbot/assets/css/chatbot.css' )
		);

		wp_register_script(
			'qmo-chatbot-shared',
			$chatbot . 'js/qmo-chatbot-shared.js',
			array(),
			QRMS_Helpers::asset_version( 'modules/qr-chatbot/assets/js/qmo-chatbot-shared.js' ),
			true
		);
		wp_register_script(
			'qmo-chatbot',
			$chatbot . 'js/chatbot.js',
			array( 'qmo-chatbot-shared' ),
			QRMS_Helpers::asset_version( 'modules/qr-chatbot/assets/js/chatbot.js' ),
			true
		);

		// Buton ve sepet varlıkları qr-chatbot/chatbot.php içinde kaydedilir
		// (dosyalar o modüle aittir). Burada kaydedilirlerse var olmayan
		// ortak bir assets/ yoluna işaret ederlerdi.
	}
}
add_action( 'wp_enqueue_scripts', 'qmo_varliklari_kaydet', 5 );

add_action( 'wp_enqueue_scripts', 'qmo_icerikten_yukle', 6 );

/**
 * Yazı içeriğinde kısa kod varsa varlıkları normal sırada (head) yükle.
 *
 * Kısa kod içinden enqueue etmek de çalışır (WordPress geç eklenen
 * varlıkları footer'a basar) ama içerikte bulabildiğimizde head'e almak
 * daha temiz. Elementor gibi kurucularda kısa kod post_content'te
 * görünmez; orada footer yolu devreye girer.
 */
if ( ! function_exists( 'qmo_icerikten_yukle' ) ) {
	function qmo_icerikten_yukle() {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post || empty( $post->post_content ) ) {
			return;
		}

		// Oturum yoksa kısa kodlar zaten bilgi kutusu basar; ağır varlıkları
		// yüklemeye gerek yok.
		if ( ! qmo_oturum() ) {
			return;
		}

		$eslesme = array(
			'gemini_chatbot'  => 'qmo-chatbot',
			'qmo_sepet'       => 'qmo-sepet',
			'garson_butonu'   => 'qmo-buttons',
			'hesap_iste_butonu' => 'qmo-buttons',
			'ikili_buton'     => 'qmo-buttons',
			'qr_garson_hesap' => 'qmo-garson-hesap',
		);

		foreach ( $eslesme as $kisa_kod => $handle ) {
			if ( ! has_shortcode( $post->post_content, $kisa_kod ) ) {
				continue;
			}
			if ( 'qmo-chatbot' === $handle && function_exists( 'qmo_chatbot_onyuz_yuklensin_mi' ) && ! qmo_chatbot_onyuz_yuklensin_mi() ) {
				continue;
			}
			qmo_asset_enqueue( $handle );
		}
	}
}

/**
 * Aynı dosyayı gösteren handle'ların TEK karşılığı.
 *
 * Bazı kısa kodlar tarihsel olarak kendi handle adlarıyla çağrılıyor
 * ([qr_garson_hesap] -> qmo-garson-hesap) ama arkalarındaki CSS/JS dosyası
 * qmo-buttons ile birebir aynı. WordPress iki farklı handle'ı iki farklı
 * varlık saydığı için, ikisi de aynı sayfada enqueue edilirse dosya iki kez
 * basılır ve JS iki kez çalışır. Handle'lar burada tek bir kanonik ada
 * indirgenir; takma ad kaydı (kaynaksız, qmo-buttons'a bağımlı) yalnızca
 * doğrudan wp_enqueue_script() çağıran üçüncü taraf kod için durur.
 *
 * Saf fonksiyon; testlerde doğrudan doğrulanır.
 *
 * @param string $handle Ham handle.
 * @return string Kanonik handle.
 */
if ( ! function_exists( 'qmo_asset_kanonik_handle' ) ) {
	function qmo_asset_kanonik_handle( $handle ) {
		$takma_adlar = array(
			'qmo-garson-hesap' => 'qmo-buttons',
		);

		return isset( $takma_adlar[ $handle ] ) ? $takma_adlar[ $handle ] : $handle;
	}
}

/**
 * Kısa kodların ihtiyaç duyduğu varlığı yükler.
 * Kayıt yapılmamışsa önce kaydeder (kısa kod erken çalışabilir).
 *
 * @param string $handle Varlık adı ('qmo-sepet' vb.).
 */
if ( ! function_exists( 'qmo_asset_enqueue' ) ) {
	function qmo_asset_enqueue( $handle ) {
		qmo_varliklari_kaydet();

		// Aynı dosyanın ikinci bir handle üzerinden tekrar yüklenmesini
		// engeller: buttons.js iki kez çalışırsa olay dinleyicileri iki kez
		// bağlanır ve tek tıkta iki AJAX isteği gider.
		$handle = qmo_asset_kanonik_handle( $handle );

		if ( 'qmo-chatbot' === $handle && function_exists( 'qmo_chatbot_onyuz_yuklensin_mi' ) && ! qmo_chatbot_onyuz_yuklensin_mi() ) {
			return;
		}

		if ( wp_style_is( $handle, 'registered' ) ) {
			wp_enqueue_style( $handle );

			// Chatbot renkleri CSS değişkeni olarak stilin yanına eklenir.
			// qmo_chatbot_degiskenleri() qr-chatbot modülünde tanımlıdır; bu
			// dal yalnızca 'qmo-chatbot' enqueue edildiğinde çalışır ve o da
			// tek bir yerden gelir: [gemini_chatbot] kısa kodu. Kısa kod da
			// aynı modülle kaydolduğundan (has_shortcode() kayıtlı olmayan
			// etiket için false döner) modül yüklü değilken buraya girilmez.
			// Tek noktadan yapılır: stil head'de mi footer'da mı basılacak
			// belli olmadığı için, enqueue ile aynı anda eklenmesi şart.
			if ( 'qmo-chatbot' === $handle ) {
				static $renk_eklendi = false;
				if ( ! $renk_eklendi ) {
					$renk_eklendi = true;
					wp_add_inline_style( 'qmo-chatbot', qmo_chatbot_degiskenleri() );
				}
			}
		}
		if ( wp_script_is( $handle, 'registered' ) ) {
			wp_enqueue_script( $handle );
			qmo_js_verisi_ekle( $handle );
		}
	}
}

/**
 * Script'e ortak veriyi (ajax adresi, nonce) bir kez ilet.
 *
 * @param string $handle Script adı.
 */
if ( ! function_exists( 'qmo_js_verisi_ekle' ) ) {
	function qmo_js_verisi_ekle( $handle ) {
		static $eklendi = array();
		if ( isset( $eklendi[ $handle ] ) ) {
			return;
		}
		$eklendi[ $handle ] = true;

		$veri = array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( QMO_NONCE_ACTION ),
			'restOrder' => esc_url_raw( rest_url( 'qrservis/v1/order' ) ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
		);

		// Post-render JS metinleri. sepet.js kendi qmoSepet.i18n nesnesini
		// shortcode-sepet.php'de localize eder; qmoData'ya karışmaz.
		if ( in_array( $handle, array( 'qmo-chatbot', 'qmo-buttons' ), true )
			&& function_exists( 'qmo_chat_js_metinleri' ) ) {
			$i18n = qmo_chat_js_metinleri();
			if ( $i18n ) {
				$veri['i18n'] = $i18n;
			}
		}

		// Konuşma geçmişini sayfa yenilemesinde geri yükleyebilmek için
		// chatbot.js'e HASSAS OLMAYAN bir oturum anahtarı verilir (zaten
		// oneri/mesaj tablolarında oturum_id olarak saklanan aynı türetilmiş
		// değer — ham oturum cookie'si httponly'dir, JS'e hiç gitmez).
		if ( 'qmo-chatbot' === $handle && function_exists( 'qmo_chatbot_ziyaretci_anahtar' ) ) {
			$sess                     = function_exists( 'qmo_oturum' ) ? qmo_oturum() : false;
			$veri['oturumAnahtari']   = qmo_chatbot_ziyaretci_anahtar( $sess ? $sess : array() );
		}

		wp_localize_script( $handle, 'qmoData', $veri );
	}
}
