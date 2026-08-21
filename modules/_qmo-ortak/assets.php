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
			'qmo-chatbot',
			$chatbot . 'js/chatbot.js',
			array(),
			QRMS_Helpers::asset_version( 'modules/qr-chatbot/assets/js/chatbot.js' ),
			true
		);

		// Sepet, buton ve garson/hesap varlıkları bu göçte taşınmadı (kaynak
		// dosyaları henüz bir modüle ait değil). Modülleri geldiğinde kendi
		// klasörlerini göstererek geri açılacak; şimdi kaydedilirlerse var
		// olmayan dosyalara işaret ederlerdi.
		// wp_register_style( 'qmo-buttons', $css . 'buttons.css', array(), $v );
		// wp_register_style( 'qmo-sepet', $css . 'sepet.css', array(), $v );
		// wp_register_style( 'qmo-garson-hesap', $css . 'garson-hesap.css', array(), $v );
		// wp_register_style( 'qmo-debug', $css . 'debug.css', array(), $v );
		// wp_register_script( 'qmo-buttons', $js . 'buttons.js', array(), $v, true );
		// wp_register_script( 'qmo-sepet', $js . 'sepet.js', array(), $v, true );
		// wp_register_script( 'qmo-garson-hesap', $js . 'garson-hesap.js', array(), $v, true );
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
			if ( has_shortcode( $post->post_content, $kisa_kod ) ) {
				qmo_asset_enqueue( $handle );
			}
		}
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

		wp_localize_script(
			$handle,
			'qmoData',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( QMO_NONCE_ACTION ),
				'restOrder' => esc_url_raw( rest_url( 'qrservis/v1/order' ) ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
}
