<?php
/**
 * Sepet kısa kodu ve QR Chatbot hub testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/class-qmo-oturum.php';
require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/helpers.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-sepet.php';

echo "\nQMO Sepet — masa kısıtı ve modal seçicileri\n";

qrms_test(
	'module.php init shortcode-sepet.php\'yi require eder',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/module.php' );

		qrms_assert_contains(
			"require_once __DIR__ . '/includes/shortcode-sepet.php'",
			$php,
			'init zincirinde require var'
		);
		qrms_assert_true(
			file_exists( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-sepet.php' ),
			'dosya yolu gerçek'
		);
	}
);

qrms_test(
	'sepet JS/CSS kayıtlı handle ile enqueue edilir',
	function () {
		$kayit = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/chatbot.php' );
		$kisa  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-sepet.php' );

		qrms_assert_contains( "wp_register_style( 'qmo-sepet'", $kayit, 'CSS kaydı' );
		qrms_assert_contains( "wp_register_script( 'qmo-sepet'", $kayit, 'JS kaydı' );
		qrms_assert_contains( 'css/sepet.css', $kayit, 'CSS yolu' );
		qrms_assert_contains( 'js/sepet.js', $kayit, 'JS yolu' );
		qrms_assert_contains( "qmo_asset_enqueue( 'qmo-sepet' )", $kisa, 'kısa kod render\'da enqueue' );
		qrms_assert_contains( 'ajax-sepet-analitik.php', $kayit, 'sepet analitik AJAX ucu yüklenir' );
		qrms_assert_true(
			file_exists( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/ajax-sepet-analitik.php' ),
			'analitik AJAX dosyası durur'
		);
		qrms_assert_true(
			file_exists( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' ),
			'sepet.js durur'
		);
		qrms_assert_true(
			file_exists( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/css/sepet.css' ),
			'sepet.css durur'
		);
	}
);

qrms_test(
	'sepet analitik ucu nonce, oturum masası ve hız sınırı kullanır',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/ajax-sepet-analitik.php' );

		qrms_assert_contains( 'qmo_nonce_dogrula()', $php, 'nonce' );
		qrms_assert_contains( 'qmo_oturum()', $php, 'masa oturumdan' );
		qrms_assert_contains( "qmo_hiz_siniri( 'sepet_olay'", $php, 'hız sınırı' );
		qrms_assert_contains( "class_exists( 'QRMS_Analitik' )", $php, 'analitik yoksa no-op' );
		qrms_assert_contains( 'qmo_analitik_urun_alani', $php, 'yayın ürünü doğrulanır' );
		qrms_assert_contains( 'qmo_analitik_yaz', $php, 'kaydet köprüsü' );
		qrms_assert_false( false !== strpos( $php, "\$_POST['masa" ), 'istemci masasına güvenilmez' );
	}
);

qrms_test(
	'sepet JS dış kapsayıcıyı qrms-detail-* ile arar, içerik class\'larına dokunmaz',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' );

		qrms_assert_contains( "body.closest( '.qrms-detail-box, .rma-modal-box' )", $js, 'görsel kutusu yeni + eski dış class' );
		qrms_assert_contains( "kutu.querySelector( '.rma-modal-img' )", $js, 'görsel class\'ı AJAX içeriğinde durur' );
		qrms_assert_contains( "'.qrms-detail-overlay, .qrms-detail-box, .rma-modal-overlay, .rma-modal-box, .rma-modal-body'", $js, 'modalAcikMi yeni dış + duran iç class' );
		qrms_assert_contains( "'.rma-modal-body:not([data-qmo])'", $js, 'enjeksiyon hedefi hâlâ rma-modal-body' );
		qrms_assert_contains( "'.rma-modal-title'", $js, 'başlık class\'ı durur' );
		qrms_assert_contains( "'.rma-modal-price'", $js, 'fiyat class\'ı durur' );
		qrms_assert_contains( "'.rma-price-new, .qmo-kombin-new-price'", $js, 'yalnızca güncel fiyat span\'i' );
		qrms_assert_contains( 'fiyatMetni', $js, 'kampanyalı fiyatta eski+yeni birleşmez' );
		qrms_assert_contains( "'.rma-card, .qrms-vitrin-card, .qmo-slider-product'", $js, 'vitrin/slider kartı da modal yakalar' );
		qrms_assert_contains( 'qmoSepet.endpoint', $js, 'sipariş qmoSepet.endpoint üzerinden gider' );
		qrms_assert_contains( 'qmo_sepet_olay', $js, 'sepet analitik ucu' );
		qrms_assert_contains( 'analitikKuyrukla', $js, 'sepet olayları kuyruklanır' );
		qrms_assert_contains( 'ANALITIK_PENCERE', $js, 'debounce penceresi' );
		qrms_assert_contains( 'body.getAttribute( \'data-id\' )', $js, 'ürün kimliği modal data-id' );
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-sepet.php' );
		qrms_assert_contains( 'qrservis/v1/order', $php, 'REST adresi shortcode\'da üretilir' );
		qrms_assert_contains( "'analitik'", $php, 'analitik bayrağı localize edilir' );
		qrms_assert_contains( 'QMO_NONCE_ACTION', $php, 'sepet analitik nonce\'u sipariş/AJAX deseni' );
	}
);

qrms_test(
	'sepet kampanyalı/kombin fiyatta yalnızca güncel tutarı okur',
	function () {
		$js     = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' );
		$kamp   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-kampanya.php' );
		$ajax   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-ajax.php' );
		$kombin = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/admin-kombin-meta.php' );

		qrms_assert_contains( 'class="rma-price-old"', $kamp, 'üstü çizili eski fiyat span\'i' );
		qrms_assert_contains( "'rma-price-new '", $kamp, 'güncel kampanya fiyatı span\'i' );
		qrms_assert_contains( 'class="rma-modal-price"', $ajax, 'modal fiyat kapsayıcısı' );
		qrms_assert_contains( 'qmo-kombin-old-price', $kombin, 'kombin eski fiyat' );
		qrms_assert_contains( 'qmo-kombin-new-price', $kombin, 'kombin yeni fiyat' );
		qrms_assert_contains( 'fiyatMetni( fiyatEl )', $js, 'parse kapsayıcının güncel span\'inden' );
		qrms_assert_false(
			false !== strpos( $js, 'fiyatEl ? fiyatEl.textContent' ),
			'kapsayıcının tüm metni (eski+yeni) parse edilmez'
		);
	}
);

qrms_test(
	'sepet TL yazımı kuruşu korur, tam sayıda ondalığı gizler',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' );

		qrms_assert_contains( 'function fiyatYazi', $js, 'TL biçimleyici' );
		qrms_assert_contains( 'function fiyatGoster', $js, 'çeviri kalıbı' );
		qrms_assert_contains( 'rma_ceviri_fiyat_ayraclar()', $js, 'PHP ayraç tablosuyla aynı' );
		qrms_assert_contains( 'fiyatGoster( x.fiyat * x.adet )', $js, 'satır fiyatı' );
		qrms_assert_contains( 'barTot.textContent = fiyatGoster( t )', $js, 'çubuk toplamı' );
		qrms_assert_contains( 'tot.textContent = fiyatGoster( t )', $js, 'çekmece toplamı' );
		qrms_assert_contains( "metin.slice( -3 ) === sifir", $js, 'tam sayıda ,00 gizlenir' );
		qrms_assert_contains( 'parseFloat( t )', $js, 'fiyat parseFloat' );
		qrms_assert_false( false !== strpos( $js, 'toFixed( 0 )' ), 'toFixed(0) boşluklu yok' );
		qrms_assert_false( false !== strpos( $js, 'toFixed(0)' ), 'toFixed(0) yok' );
	}
);

qrms_test(
	'AJAX ürün detayı hâlâ rma-modal-body ve rma-modal-img basar',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-ajax.php' );
		$js  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/rma-detail-modal.js' );

		qrms_assert_contains( 'class="rma-modal-body"', $php, 'iç HTML kökünde rma-modal-body' );
		qrms_assert_contains( 'data-id="%d"', $php, 'ürün kimliği modal gövdede' );
		qrms_assert_contains( 'class="rma-modal-img"', $php, 'img class\'ı rma-modal-img' );
		qrms_assert_contains( "inner.innerHTML = html", $js, 'içerik qrms-detail-inner\'a enjekte edilir' );
		qrms_assert_contains( "overlay.className = 'qrms-detail-overlay'", $js, 'dış overlay yeni class' );
		qrms_assert_contains( "'<div class=\"qrms-detail-box\">'", $js, 'dış kutu yeni class' );
	}
);

qrms_test(
	'masa oturumu yokken ve yönetici değilken sepet boş döner',
	function () {
		$GLOBALS['qrms_test']['can']       = false;
		$GLOBALS['qrms_test']['logged_in'] = false;
		unset( $_COOKIE[ QMO_Oturum::COOKIE ] );

		qrms_assert_false( qmo_sepet_izinli_mi(), 'izin yok' );
		qrms_assert_same( '', qmo_sepet_shortcode(), 'çıktı boş' );
	}
);

qrms_test(
	'yönetici masa parametresi olmadan da sepeti görür',
	function () {
		$GLOBALS['qrms_test']['can']       = true;
		$GLOBALS['qrms_test']['logged_in'] = true;
		unset( $_COOKIE[ QMO_Oturum::COOKIE ] );

		qrms_assert_true( qmo_sepet_izinli_mi(), 'admin muaf' );
	}
);

qrms_test(
	'geçerli masa oturumu olan müşteri sepeti görür',
	function () {
		$GLOBALS['qrms_test']['can']       = false;
		$GLOBALS['qrms_test']['logged_in'] = false;
		$GLOBALS['qrms_test']['options'][ QMO_Oturum::OPT_KEY ] = 'test-hmac-anahtari-sepet-icin-yeterince-uzun';

		$token = QMO_Oturum::token_uret( 'masa-31' );
		$_COOKIE[ QMO_Oturum::COOKIE ] = $token;

		qrms_assert_true( qmo_sepet_izinli_mi(), 'oturum geçerli' );
		qrms_assert_true( false !== qmo_oturum(), 'qmo_oturum HMAC çerezini okur' );
		qrms_assert_same( 'masa-31', qmo_oturum()['masa'], 'masa slug\'ı korunur' );
	}
);

qrms_test(
	'sepet anahtarı varsayılan kapalıdır (opt-in)',
	function () {
		qrms_assert_false( qmo_sepet_aktif_mi(), 'option yokken kapalı' );
		qrms_assert_same( '<menu/>', qmo_sepet_menuye_ekle( '<menu/>' ), 'kapalıyken menü çıktısı değişmez' );
	}
);

qrms_test(
	'anahtar açık + masa oturumu yokken menü altına sepet basılmaz',
	function () {
		$GLOBALS['qrms_test']['can']       = false;
		$GLOBALS['qrms_test']['logged_in'] = false;
		unset( $_COOKIE[ QMO_Oturum::COOKIE ] );
		update_option( 'qmo_sepet_aktif', 1 );

		qrms_assert_true( qmo_sepet_aktif_mi(), 'anahtar açık' );
		qrms_assert_false( qmo_sepet_izinli_mi(), 'oturum yok' );
		qrms_assert_same( '<menu/>', qmo_sepet_menuye_ekle( '<menu/>' ), 'izin yokken enjeksiyon boş ekler' );
	}
);

qrms_test(
	'anahtar açık + masa oturumu varken menü altına sepet eklenir',
	function () {
		$GLOBALS['qrms_test']['can']       = false;
		$GLOBALS['qrms_test']['logged_in'] = false;
		$GLOBALS['qrms_test']['options'][ QMO_Oturum::OPT_KEY ] = 'test-hmac-anahtari-sepet-icin-yeterince-uzun';
		update_option( 'qmo_sepet_aktif', 1 );

		$token = QMO_Oturum::token_uret( 'masa-12' );
		$_COOKIE[ QMO_Oturum::COOKIE ] = $token;

		$html = qmo_sepet_menuye_ekle( '<div class="rma-wrap"></div>' );

		qrms_assert_contains( '<div class="rma-wrap"></div>', $html, 'menü durur' );
		qrms_assert_contains( 'id="qmo-sepet-root"', $html, 'sepet menünün altında' );
		qrms_assert_true(
			strpos( $html, 'id="qmo-sepet-root"' ) > strpos( $html, 'rma-wrap' ),
			'sepet menüden sonra gelir'
		);
	}
);

qrms_test(
	'aynı istekte sepet HTML\'i yalnızca bir kez basılır',
	function () {
		$GLOBALS['qrms_test']['can']       = true;
		$GLOBALS['qrms_test']['logged_in'] = true;

		$ilk = qmo_sepet_shortcode();
		$iki = qmo_sepet_shortcode();
		$uc  = qmo_sepet_menuye_ekle( '[elle]' );

		qrms_assert_contains( 'id="qmo-sepet-root"', $ilk, 'ilk çağrı basar' );
		qrms_assert_same( 1, substr_count( $ilk, 'id="qmo-sepet-root"' ), 'kök tekildir' );
		qrms_assert_same( '', $iki, 'ikinci kısa kod boş' );
		qrms_assert_same( '[elle]', $uc, 'otomatik enjeksiyon ikinci kopyayı eklemez' );
	}
);

qrms_test(
	'anahtar kapalıyken elle [qmo_sepet] hâlâ render edilir',
	function () {
		$GLOBALS['qrms_test']['can']       = true;
		$GLOBALS['qrms_test']['logged_in'] = true;
		update_option( 'qmo_sepet_aktif', 0 );

		qrms_assert_false( qmo_sepet_aktif_mi(), 'anahtar kapalı' );
		qrms_assert_same( '<menu/>', qmo_sepet_menuye_ekle( '<menu/>' ), 'otomatik ekleme yok' );
		qrms_assert_contains( 'id="qmo-sepet-root"', qmo_sepet_shortcode(), 'elle kısa kod çalışır' );
	}
);

qrms_test(
	'Diğer Ayarlar sayfasında sepet anahtarı ve kaydetme ucu vardır',
	function () {
		$sayfa = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-admin-pages.php' );
		$boot  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' );
		$on    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-frontend.php' );
		$kisa  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-sepet.php' );
		$css   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/admin-ui.css' );

		qrms_assert_contains( 'Sepet ile Sipariş', $sayfa, 'bölüm başlığı' );
		qrms_assert_contains( 'Sepet ile siparişi etkinleştir', $sayfa, 'anahtar etiketi' );
		qrms_assert_contains( "id=\"rma-sepet-siparis\"", $sayfa, 'bölüm çapası' );
		qrms_assert_contains( "href=\"#rma-sepet-siparis\"", $sayfa, 'nav bağlantısı' );
		qrms_assert_contains( 'handle_sepet_ayar_save', $sayfa, 'kaydetme metodu' );
		qrms_assert_contains( "check_admin_referer( 'qmo_sepet_ayar_kaydet' )", $sayfa, 'nonce' );
		qrms_assert_contains( 'QRMS_Admin::CAPABILITY', $sayfa, 'yetki kontrolü' );
		qrms_assert_contains( "update_option( 'qmo_sepet_aktif'", $sayfa, 'option kaydı' );
		qrms_assert_contains( "admin_post_qmo_sepet_ayar_kaydet", $boot, 'admin-post ucu' );
		qrms_assert_contains( "function_exists( 'qmo_sepet_menuye_ekle' )", $on, 'menü render enjeksiyonu' );
		qrms_assert_contains( 'qmo_sepet_menuye_ekle', $on, 'enjeksiyon çağrısı' );
		qrms_assert_contains( 'add_shortcode( \'qmo_sepet\'', $kisa, 'elle kısa kod durur' );
		qrms_assert_contains( 'qmo_sepet_istekte_basildi', $kisa, 'çift-render bayrağı' );
		qrms_assert_contains( '.rma-admin .rma-switch', $css, 'ayar sayfası anahtar stili' );
	}
);

/* ---------------------------------------------------------------------------
 * 8d. QR Chatbot — kart tabanlı hub ve gizli alt sayfalar
 * ------------------------------------------------------------------------ */

require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/admin/admin-sayfa.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/module.php';

echo "\nQR Chatbot hub\n";

qrms_test(
	'alt sayfalar tek kaynakta, hub grupları ve slug\'lar tanımlı',
	function () {
		$pages = qmo_chatbot_sayfalar();

		qrms_assert_same(
			array(
				'qrms-chatbot-bot-identity',
				'qrms-chatbot-appearance',
				'qrms-chatbot-quick-replies',
				'qrms-chatbot-visibility',
				'qrms-chatbot-gemini',
				'qrms-chatbot-ai-behavior',
				'qrms-chatbot-firebase',
				'qrms-chatbot-ana-site',
				'qrms-chatbot-history',
				'qrms-chatbot-unanswered',
			),
			array_keys( $pages ),
			'sayfa listesi'
		);

		foreach ( $pages as $slug => $page ) {
			foreach ( array( 'title', 'render', 'desc', 'icon', 'group' ) as $key ) {
				qrms_assert_true( ! empty( $page[ $key ] ), $slug . ' -> ' . $key . ' dolu' );
			}
			qrms_assert_same( 0, strpos( $page['icon'], 'dashicons-' ), $slug . ' ikonu dashicon' );
			qrms_assert_true( is_callable( $page['render'] ), $slug . ' callback çağrılabilir' );
		}

		qrms_assert_same( 'Bot', $pages['qrms-chatbot-bot-identity']['group'], 'Bot grubu' );
		qrms_assert_same( 'Bot', $pages['qrms-chatbot-appearance']['group'], 'Görünüm Bot grubunda' );
		qrms_assert_same( 'Bot', $pages['qrms-chatbot-quick-replies']['group'], 'Hazır sorular Bot grubunda' );
		qrms_assert_same( 'Bot', $pages['qrms-chatbot-visibility']['group'], 'Görünürlük Bot grubunda' );
		qrms_assert_same( 'Yapay Zeka', $pages['qrms-chatbot-gemini']['group'], 'Gemini grubu' );
		qrms_assert_same( 'Yapay Zeka', $pages['qrms-chatbot-ai-behavior']['group'], 'Davranış grubu' );
		qrms_assert_same( 'Entegrasyon', $pages['qrms-chatbot-firebase']['group'], 'Firebase grubu' );
		qrms_assert_same( 'Entegrasyon', $pages['qrms-chatbot-ana-site']['group'], 'Ana site grubu' );
		qrms_assert_same( 'Yönetim', $pages['qrms-chatbot-history']['group'], 'Geçmiş Yönetim grubunda' );
		qrms_assert_same( 'Yönetim', $pages['qrms-chatbot-unanswered']['group'], 'Cevaplanamayan Yönetim grubunda' );
	}
);

qrms_test(
	'hub kart ızgarası form içermez, bölüm başlıkları ve kartlar basılır',
	function () {
		ob_start();
		qmo_chatbot_ayar_sayfasi();
		$html = ob_get_clean();

		qrms_assert_contains( 'class="rma-hub"', $html, 'Restoran Menü kapsülü' );
		qrms_assert_contains( 'QR Chatbot', $html, 'hub başlığı' );
		qrms_assert_contains( 'Gemini destekli masa asistanı', $html, 'açıklama' );
		qrms_assert_contains( '[gemini_chatbot]', $html, 'kısa kod' );
		qrms_assert_contains( 'qrms-hub-group-title', $html, 'bölüm başlığı' );
		qrms_assert_contains( 'qrms-hub-group-title">Bot', $html, 'Bot bölümü' );
		qrms_assert_contains( 'Yapay Zeka', $html, 'Yapay Zeka bölümü' );
		qrms_assert_contains( 'Entegrasyon', $html, 'Entegrasyon bölümü' );
		qrms_assert_contains( 'page=qrms-chatbot-bot-identity', $html, 'Bot Kimliği kartı' );
		qrms_assert_contains( 'page=qrms-chatbot-appearance', $html, 'Görünüm kartı' );
		qrms_assert_contains( 'page=qrms-chatbot-quick-replies', $html, 'Hazır Sorular kartı' );
		qrms_assert_contains( 'page=qrms-chatbot-visibility', $html, 'Görünürlük kartı' );
		qrms_assert_contains( 'page=qrms-chatbot-gemini', $html, 'Gemini kartı' );
		qrms_assert_contains( 'page=qrms-chatbot-ai-behavior', $html, 'Davranış kartı' );
		qrms_assert_contains( 'page=qrms-chatbot-firebase', $html, 'Firebase kartı' );
		qrms_assert_contains( 'page=qrms-chatbot-ana-site', $html, 'Ana site kartı' );
		qrms_assert_contains( 'page=qrms-chatbot-history', $html, 'Sohbet Geçmişi kartı' );
		qrms_assert_contains( 'page=qrms-chatbot-unanswered', $html, 'Cevaplanamayan kartı' );
		qrms_assert_contains( 'Sohbet Asistanı', $html, 'ana anahtar' );
		qrms_assert_contains( 'qmo-cb-hub-switch', $html, 'AJAX anahtar' );
		qrms_assert_contains( 'Yönetim', $html, 'Yönetim bölümü' );
		qrms_assert_contains( '✗ Henüz yapılandırılmadı', $html, 'Firebase uyarı rozeti' );
		qrms_assert_false( false !== strpos( $html, '<form' ), 'hub form basmaz' );
		qrms_assert_false( false !== strpos( $html, 'nav-tab' ), 'eski sekmeler yok' );
	}
);

qrms_test(
	'eski ?tab= yer imleri ilgili alt sayfaya yönlenir',
	function () {
		$_GET['tab'] = 'gorunum';
		try {
			qmo_chatbot_ayar_sayfasi();
			qrms_assert_true( false, 'yönlendirme beklenirdi' );
		} catch ( QRMS_Test_Redirect $e ) {
			qrms_assert_contains( 'page=qrms-chatbot-appearance', $e->getMessage(), 'görünüm sayfası' );
		}

		$_GET['tab'] = 'yapayzeka';
		try {
			qmo_chatbot_ayar_sayfasi();
			qrms_assert_true( false, 'yönlendirme beklenirdi' );
		} catch ( QRMS_Test_Redirect $e ) {
			qrms_assert_contains( 'page=qrms-chatbot-ai-behavior', $e->getMessage(), 'davranış sayfası' );
		}
	}
);

qrms_test(
	'modül aktifken alt sayfalar gizli kayıtlıdır, option adları durur',
	function () {
		$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] = array(
			qrms_submenu_satiri( 'Chatbot Asistan', QRMS_Admin::get_module_page_slug( 'qr-chatbot' ) ),
		);

		qrms_module_qr_chatbot_admin_menu();

		$sluglar = array_map(
			function ( $item ) {
				return $item['slug'];
			},
			$GLOBALS['qrms_test']['submenus']
		);

		qrms_assert_same( array_keys( qmo_chatbot_sayfalar() ), $sluglar, 'kaydedilen sayfalar' );
		qrms_assert_true( QRMS_Admin::is_module_subpage( 'qrms-chatbot-bot-identity' ), 'kayıt defterinde' );
		qrms_assert_same( QRMS_Admin::MENU_SLUG, $GLOBALS['qrms_test']['submenus'][0]['parent'], 'üst menü' );

		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/admin/admin-sayfa.php' );
		foreach ( array(
			'gemini_api_key',
			'qmo_gemini_model',
			'gemini_bot_name',
			'gemini_welcome_text',
			'gemini_placeholder_text',
			'gemini_show_toggle_text',
			'gemini_bot_icon',
			'gemini_icon_size',
			'gemini_border_radius',
			'gemini_system_prompt',
			'gemini_menu_json_data',
			'qmo_branch_id',
			'qmo_firebase_sa',
			'qmo_ana_site',
			'QMO_FIREBASE_SA_JSON',
		) as $alan ) {
			qrms_assert_contains( $alan, $php, $alan . ' durur' );
		}

		$modul = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/module.php' );
		qrms_assert_contains( 'modules/restoran-menu/assets/css/hub.css', $modul, 'hub.css kuyruğa alınır' );
		qrms_assert_contains( "array( 'qrms-admin' )", $modul, 'ortak admin.css sonrası yüklenir' );
	}
);

qrms_test(
	'modül lisansta aktif değilken chatbot alt sayfası kaydedilmez',
	function () {
		qrms_module_qr_chatbot_admin_menu();
		qrms_assert_same( array(), $GLOBALS['qrms_test']['submenus'], 'kayıt yok' );
	}
);

qrms_test(
	'Bot Kimliği sayfasında geri bağlantısı, nonce ve Kaydet vardır',
	function () {
		ob_start();
		qmo_chatbot_sayfa_bot_kimligi();
		$html = ob_get_clean();

		qrms_assert_contains( 'qrms-back-link', $html, 'geri bağlantısı' );
		qrms_assert_contains( 'QR Chatbot', $html, 'geri metni' );
		qrms_assert_contains( 'name="gemini_bot_name"', $html, 'bot adı alanı' );
		qrms_assert_contains( 'name="gemini_welcome_text"', $html, 'karşılama' );
		qrms_assert_contains( 'name="gemini_placeholder_text"', $html, 'placeholder' );
		qrms_assert_contains( 'name="gemini_show_toggle_text"', $html, 'toggle' );
		qrms_assert_contains( 'name="gemini_bot_icon"', $html, 'ikon' );
		qrms_assert_contains( 'qmo_chatbot_nonce', $html, 'nonce' );
		qrms_assert_contains( 'Kaydet', $html, 'Kaydet' );
		qrms_assert_contains( "submit_button( 'Kaydet', 'primary', 'qmo_chatbot_kaydet' )", file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/admin/admin-sayfa.php' ), 'Kaydet düğmesi adı' );
		qrms_assert_false( false !== strpos( $html, 'name="gemini_api_key"' ), 'API anahtarı bu sayfada değil' );
	}
);

qrms_test(
	'Firebase sayfasında güvenlik açıklaması durur, ana site kutusu yoktur',
	function () {
		ob_start();
		qmo_chatbot_sayfa_firebase();
		$html = ob_get_clean();

		qrms_assert_contains( 'name="qmo_branch_id"', $html, 'şube kimliği' );
		qrms_assert_contains( 'name="qmo_firebase_sa"', $html, 'service account' );
		qrms_assert_contains( 'QMO_FIREBASE_SA_JSON', $html, 'wp-config önerisi' );
		qrms_assert_contains( '✗ Henüz yapılandırılmadı', $html, 'uyarı rozeti' );
		qrms_assert_contains( 'action="options.php"', $html, 'options.php' );
		qrms_assert_true( in_array( 'qmo_firebase_grup', $GLOBALS['qrms_test']['settings_fields'], true ), 'settings_fields grubu' );
		qrms_assert_false( false !== strpos( $html, 'Bu site ana site mi?' ), 'ana site kutusu bu sayfada değil' );
	}
);

require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/color-defaults.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/class-ayarlar.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/class-db.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-chatbot.php';
require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/assets.php';

qrms_test(
	'üç ana renkten diğerleri türetilir ve yazı zemin üzerinde okunur',
	function () {
		$renkler = qmo_chatbot_renkleri_turetilsin( '#8a2be2', '#f8fafc', '#333333' );

		foreach ( array( 'gemini_header_bg_color', 'gemini_user_msg_color', 'gemini_bot_msg_color', 'gemini_send_btn_bg_color', 'gemini_border_color' ) as $alan ) {
			qrms_assert_true( isset( $renkler[ $alan ] ), $alan . ' türetilir' );
			qrms_assert_true( (bool) sanitize_hex_color( $renkler[ $alan ] ), $alan . ' geçerli renk' );
		}

		qrms_assert_same( '#8a2be2', $renkler['gemini_main_color'], 'ana renk korunur' );
		qrms_assert_same( '#f8fafc', $renkler['gemini_chat_bg_color'], 'zemin korunur' );
		$fark = abs( qmo_chatbot_parlaklik( $renkler['gemini_text_color'] ) - qmo_chatbot_parlaklik( $renkler['gemini_chat_bg_color'] ) );
		qrms_assert_true( $fark >= 0.35, 'yazı zemin üzerinde okunur' );
	}
);

qrms_test(
	'hub anahtarı AJAX ucu yetki ve nonce ister',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/ajax-admin.php' );
		qrms_assert_contains( "wp_ajax_qmo_chatbot_toggle", $php, 'toggle eylemi' );
		qrms_assert_contains( "check_ajax_referer( 'qmo_chatbot_toggle'", $php, 'toggle nonce' );
		qrms_assert_contains( "current_user_can( 'manage_options' )", $php, 'yetki' );
		qrms_assert_contains( 'QMO_CHATBOT_OPT_AKTIF', $php, 'aktif option' );
	}
);

qrms_test(
	'sohbet tabloları dbDelta ile indexli kurulur',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/class-db.php' );
		qrms_assert_contains( 'qmo_chatbot_mesajlar', $php, 'mesaj tablosu' );
		qrms_assert_contains( 'qmo_chatbot_bilinmeyen', $php, 'bilinmeyen tablosu' );
		qrms_assert_contains( 'dbDelta', $php, 'dbDelta' );
		qrms_assert_contains( 'KEY idx_created', $php, 'tarih index' );
		qrms_assert_contains( 'KEY idx_masa_created', $php, 'masa index' );
		qrms_assert_contains( 'KEY idx_oturum', $php, 'oturum index' );
		qrms_assert_contains( 'UNIQUE KEY idx_soru_norm', $php, 'soru norm unique' );
		qrms_assert_contains( 'KEY idx_resolved_tekrar', $php, 'tekrar index' );
	}
);

qrms_test(
	'asistan kapalıysa ön yüz varlıkları yüklenmez',
	function () {
		update_option( 'qmo_chatbot_aktif', 'no' );
		qrms_assert_false( qmo_chatbot_onyuz_yuklensin_mi(), 'yükleme kapalı' );
		qrms_assert_same( '', qmo_chatbot_shortcode(), 'kısa kod boş' );

		$varlik = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/assets.php' );
		qrms_assert_contains( 'qmo_chatbot_onyuz_yuklensin_mi', $varlik, 'içerik tarayıcı atlar' );
		qrms_assert_contains( "'qmo-chatbot' === \$handle", $varlik, 'enqueue da atlar' );
	}
);

qrms_test(
	'chatbot otomatik enjeksiyon varsayılan açık; oturum yokken footer sessiz',
	function () {
		$GLOBALS['qrms_test']['logged_in'] = true;
		unset( $_COOKIE[ QMO_Oturum::COOKIE ] );

		qrms_assert_true( qmo_chatbot_otomatik_goster_mi(), 'varsayılan açık' );
		qrms_assert_true( qmo_chatbot_on_yuz_istegi_mi(), 'normal ön yüz isteği' );
		qrms_assert_false( qmo_chatbot_otomatik_basilmali_mi(), 'oturum yokken otomatik basılmaz' );
		qrms_assert_same( '', qmo_chatbot_html_uret( 'footer' ), 'footer modunda QR uyarısı yok' );
	}
);

qrms_test(
	'otomatik enjeksiyon kapalıyken yalnızca kısa kod çalışır',
	function () {
		update_option( 'qmo_chatbot_auto_inject', 'no' );
		$GLOBALS['qrms_test']['logged_in'] = true;
		$GLOBALS['qrms_test']['options'][ QMO_Oturum::OPT_KEY ] = 'test-hmac-anahtari-sepet-icin-yeterince-uzun';
		$token = QMO_Oturum::token_uret( 'masa-55' );
		$_COOKIE[ QMO_Oturum::COOKIE ] = $token;

		qrms_assert_false( qmo_chatbot_otomatik_goster_mi(), 'anahtar kapalı' );
		qrms_assert_false( qmo_chatbot_otomatik_basilmali_mi(), 'footer atlanır' );
		qrms_assert_contains( 'gemini-shortcode-container', qmo_chatbot_shortcode(), 'elle kısa kod çalışır' );
	}
);

qrms_test(
	'aynı istekte chatbot HTML\'i yalnızca bir kez basılır',
	function () {
		$GLOBALS['qrms_test']['logged_in'] = true;
		$GLOBALS['qrms_test']['options'][ QMO_Oturum::OPT_KEY ] = 'test-hmac-anahtari-sepet-icin-yeterince-uzun';
		$token = QMO_Oturum::token_uret( 'masa-77' );
		$_COOKIE[ QMO_Oturum::COOKIE ] = $token;

		$ilk = qmo_chatbot_shortcode();
		$iki = qmo_chatbot_shortcode();

		qrms_assert_contains( 'gemini-shortcode-container', $ilk, 'ilk kısa kod basar' );
		qrms_assert_same( '', $iki, 'ikinci kısa kod boş' );
		qrms_assert_false( qmo_chatbot_otomatik_basilmali_mi(), 'footer bayrağı görüp atlar' );
	}
);

qrms_test(
	'admin, 404, login ve Elementor önizlemede otomatik enjeksiyon yok',
	function () {
		$GLOBALS['qrms_test']['logged_in'] = true;
		$GLOBALS['qrms_test']['options'][ QMO_Oturum::OPT_KEY ] = 'test-hmac-anahtari-sepet-icin-yeterince-uzun';
		$token = QMO_Oturum::token_uret( 'masa-99' );
		$_COOKIE[ QMO_Oturum::COOKIE ] = $token;

		qrms_assert_true( qmo_chatbot_otomatik_basilmali_mi(), 'normal istekte basılır' );

		$GLOBALS['qrms_test']['is_admin'] = true;
		qrms_assert_false( qmo_chatbot_on_yuz_istegi_mi(), 'admin' );
		$GLOBALS['qrms_test']['is_admin'] = false;

		$GLOBALS['qrms_test']['is_404'] = true;
		qrms_assert_false( qmo_chatbot_on_yuz_istegi_mi(), '404' );
		$GLOBALS['qrms_test']['is_404'] = false;

		$GLOBALS['qrms_test']['is_login'] = true;
		qrms_assert_false( qmo_chatbot_on_yuz_istegi_mi(), 'login' );
		$GLOBALS['qrms_test']['is_login'] = false;

		$_GET['elementor-preview'] = '1';
		qrms_assert_false( qmo_chatbot_on_yuz_istegi_mi(), 'elementor önizleme' );
		unset( $_GET['elementor-preview'] );
	}
);

qrms_test(
	'Görünürlük sayfasında otomatik gösterim anahtarı vardır',
	function () {
		ob_start();
		qmo_chatbot_sayfa_gorunurluk();
		$html = ob_get_clean();

		qrms_assert_contains( 'name="qmo_chatbot_auto_inject"', $html, 'form alanı' );
		qrms_assert_contains( 'tüm sayfalarda otomatik göster', $html, 'etiket' );
		qrms_assert_contains( 'wp_footer', $html, 'açıklama' );

		$boot = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/chatbot.php' );
		qrms_assert_contains( "add_action( 'wp_footer', 'qmo_chatbot_footer_bas'", $boot, 'footer kancası' );
		qrms_assert_contains( 'qmo_chatbot_istekte_basildi', file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-chatbot.php' ), 'çift basım bayrağı' );
	}
);

qrms_test(
	'Görünüm ve Bot Kimliği Türkçe etiket kullanır, eski name durur',
	function () {
		$kimlik  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/admin/admin-sayfa.php' );
		$gorunum = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/admin/sayfa-gorunum.php' );

		qrms_assert_contains( 'Kutu içi ipucu metni', $kimlik, 'ipucu etiketi' );
		qrms_assert_contains( 'Açma butonu yazısı', $kimlik, 'açma butonu' );
		qrms_assert_contains( 'Köşe yumuşaklığı', $gorunum, 'köşe' );
		qrms_assert_contains( 'name="gemini_icon_size"', $gorunum, 'eski boyut alanı' );
		qrms_assert_contains( 'name="gemini_border_radius"', $gorunum, 'eski köşe alanı' );
		qrms_assert_contains( "'gemini_header_bg_color'", $gorunum, 'gelişmiş renk name' );
		qrms_assert_contains( 'data-color-key', $gorunum, 'renk alan name döngüsü' );
		qrms_assert_false( false !== strpos( $gorunum, 'Toggle' ), 'Toggle yok' );
		qrms_assert_false( false !== strpos( $gorunum, 'border radius' ), 'border radius yok' );
		qrms_assert_contains( 'Hazır Şablonlar', $gorunum, 'şablonlar' );
		qrms_assert_contains( 'qmo_renk_sablonlari', $gorunum, 'mevcut şablon kaynağı' );
		$sablon = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/color-defaults.php' );
		qrms_assert_contains( 'Royal Violet & Gold', $sablon, 'royal' );
		qrms_assert_contains( 'Emerald Noir', $sablon, 'emerald' );
		qrms_assert_contains( 'Rose Blush', $sablon, 'rose' );
		qrms_assert_contains( 'Dark Mode', $sablon, 'dark' );
		qrms_assert_contains( 'Gelişmiş renk ayarları', $gorunum, 'gelişmiş' );
		qrms_assert_contains( 'qmo_chatbot_welcome_btn', $gorunum, 'karşılama butonu alanı' );
		qrms_assert_contains( "'qmo_chatbot_welcome_btn'", file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/class-ayarlar.php' ), 'karşılama varsayılanı' );
		qrms_assert_contains( 'Yukarı-aşağı süzülme', $gorunum, 'süzülme hareketi' );
		qrms_assert_contains( 'gemini-chat-overlay', $gorunum, 'önizleme penceresi ön yüz sınıfları' );
		qrms_assert_contains( 'gemini-chat-toggle-btn', $gorunum, 'önizleme ikonu ön yüz sınıfı' );
		qrms_assert_contains( 'qmo_chatbot_ikon_svg', $gorunum, 'ortak ikon kaynağı' );

		$ikonlar = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/class-ayarlar.php' );
		qrms_assert_contains( 'viewBox="0 0 24 24"', $ikonlar, '24 viewBox' );
		qrms_assert_contains( 'stroke="currentColor"', $ikonlar, 'currentColor stroke' );
		qrms_assert_contains( "function qmo_chatbot_ikon_svg", $ikonlar, 'ortak svg fonksiyonu' );

		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/css/chatbot.css' );
		qrms_assert_contains( 'gm-attn-float', $css, 'süzülme animasyonu' );
		qrms_assert_contains( 'prefers-reduced-motion', $css, 'hareket azaltma' );
		qrms_assert_contains( '.gm-attn-core', $css, 'rozet kaymasın diye çekirdek' );

		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/admin-chatbot.js' );
		qrms_assert_contains( 'function render()', $js, 'tek render' );
		qrms_assert_contains( '--gm-header-bg', $js, 'ön yüz CSS değişkeni' );

		$mod = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/module.php' );
		qrms_assert_contains( 'qmo-chatbot-front', $mod, 'ön yüz CSS kuyruğu' );
	}
);

/* ---------------------------------------------------------------------------
 * 9. QR Analiz — hub + kategori alt sayfaları + eski adresin yönlendirmesi
 * ------------------------------------------------------------------------ */

// Dosyalar dosya kapsamında yalnızca fonksiyon ve sabit tanımlar; stub
// ortamında yan etkisiz yüklenir.


/* P2 çeviri testleri (birleşme sonrası taşındı) */

echo "\nQR Çeviri (P0 köprü / chatbot)\n";

require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/ui-stringler.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/fiyat.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/settings.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/veri-kaynaklar.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/kaynaklar.php';

qrms_test(
	'chat kaynak metinleri katalogda; Garson/Hesap tek satır',
	function () {
		$metinler = rma_ceviri_modul_stringleri( 'chat' );
		$beklenen = array(
			'Asistanı kullanmak için masanızdaki QR kodu okutun.',
			'Çevrimiçi',
			'Kapat',
			'Gönder',
			'Çağrı butonlarını kullanmak için masanızdaki QR kodu okutun.',
			'Garson Çağır',
			'Hesap İste',
			'Garson çağrınız iletildi.',
			'Hesap talebiniz iletildi.',
			'İstek iletilemedi, lütfen tekrar deneyin.',
			'Bağlantı hatası oluştu.',
			'Yazıyor...',
			'Siparişiniz iletilemedi, lütfen garsona bildirin.',
			'Oturum süreniz doldu. Devam etmek için masadaki QR kodu tekrar okutun.',
			'Bu oturum için mesaj limitine ulaştınız.',
			'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyin.',
			'Geçersiz sipariş',
			'Sipariş iletilemedi, lütfen garsona bildirin.',
			'Çağrı sistemi şu anda kullanılamıyor.',
			'Çağrınız iletildi, lütfen bekleyin.',
			'İletildi',
			'Geçersiz istek',
			'Çağrı iletilemedi, lütfen tekrar deneyin.',
			'Talep alındı.',
			'Çok hızlı soru gönderiyorsunuz. Lütfen biraz bekleyin.',
			'Bu konuda yardımcı olamam.',
		);

		foreach ( $beklenen as $metin ) {
			qrms_assert_same(
				$metin,
				$metinler[ rma_ceviri_ui_anahtari( $metin ) ],
				$metin
			);
		}
	}
);

qrms_test(
	'shortcode ve HFB yedekleri aynı chat anahtarını kullanır',
	function () {
		$bot = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-chatbot.php' );
		$btn = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-buttons.php' );
		$hfb = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/trait-frontend.php' );

		qrms_assert_contains( "qmo_ceviri_chat( __( 'Çevrimiçi', 'qrms' ) )", $bot, 'Çevrimiçi' );
		qrms_assert_contains( "qmo_ceviri_chat( __( 'Kapat', 'qrms' ) )", $bot, 'Kapat' );
		qrms_assert_false( (bool) preg_match( "/esc_attr_e\(\s*'Kapat'/", $bot ), 'teaser Kapat esc_attr_e kalmadı' );
		qrms_assert_contains( "qmo_ceviri_chat( __( 'Gönder', 'qrms' ) )", $bot, 'Gönder' );
		qrms_assert_contains( "rma_ceviri_option( 'gemini_bot_name'", $bot, 'bot adı option' );
		qrms_assert_contains( "rma_ceviri_option( 'gemini_placeholder_text'", $bot, 'placeholder option' );
		qrms_assert_contains( "rma_ceviri_option( 'gemini_welcome_text'", $bot, 'karşılama option' );
		qrms_assert_contains( "qmo_ceviri_chat( __( 'Garson Çağır', 'qrms' ) )", $btn, 'shortcode Garson' );
		qrms_assert_contains( "qmo_ceviri_chat( __( 'Hesap İste', 'qrms' ) )", $btn, 'shortcode Hesap' );
		qrms_assert_contains( "qmo_ceviri_chat( \$garson_yedek )", $hfb, 'HFB aynı köprü' );
		qrms_assert_contains( "__( 'Garson Çağır', 'qrms' )", $hfb, 'HFB aynı Türkçe' );
		qrms_assert_contains( "__( 'Hesap İste', 'qrms' )", $hfb, 'HFB aynı Hesap' );
	}
);

qrms_test(
	'JS localize yedeği korur; Bağlantı hatası tek anahtar',
	function () {
		$assets = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/assets.php' );
		$chat   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/chatbot.php' );
		$btnjs  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/buttons.js' );
		$botjs  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/chatbot.js' );
		$sepet  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' );

		qrms_assert_contains( 'qmo_chat_js_metinleri', $assets, 'localize köprüsü' );
		qrms_assert_contains( "array( 'qmo-chatbot', 'qmo-buttons' )", $assets, 'yalnız chat/buton handle' );
		qrms_assert_false(
			(bool) preg_match( "/in_array\(\s*\\\$handle,\s*array\([^)]*qmo-sepet/", $assets ),
			'sepet qmoData\'da yok'
		);
		qrms_assert_contains( "'baglantiHatasi'", $chat, 'tek PHP anahtarı' );
		qrms_assert_contains( "metin( 'baglantiHatasi', 'Bağlantı hatası oluştu.' )", $btnjs, 'buttons yedek' );
		qrms_assert_contains( "metin( 'baglantiHatasi', 'Bağlantı hatası oluştu.' )", $botjs, 'chatbot yedek' );
		qrms_assert_contains( "metin( 'yaziyor', 'Yazıyor...' )", $botjs, 'Yazıyor yedek' );
		qrms_assert_contains( "metin( 'siparisIletilemedi'", $botjs, 'sipariş yedek' );
		qrms_assert_false( (bool) preg_match( '/qmoData\.i18n|baglantiHatasi/', $sepet ), 'sepet.js qmoData kullanmaz' );
	}
);

qrms_test(
	'AJAX/REST ziyaretçi mesajları chat köprüsünden geçer; oturum metni tek',
	function () {
		$help  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/helpers.php' );
		$ajax  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/ajax-chat.php' );
		$rest  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/rest-order.php' );
		$cagri = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/ajax-waiter-bill.php' );
		$dil   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/dil.php' );

		qrms_assert_contains( 'function qmo_ceviri_chat', $help, 'köprü yardımcı' );
		qrms_assert_contains( "qmo_ceviri_chat( __( 'Oturum süreniz doldu. Devam etmek için masadaki QR kodu tekrar okutun.', 'qrms' ) )", $help, 'helpers oturum' );
		qrms_assert_contains( "qmo_ceviri_chat( __( 'Oturum süreniz doldu. Devam etmek için masadaki QR kodu tekrar okutun.', 'qrms' ) )", $rest, 'REST aynı oturum metni' );
		qrms_assert_false( (bool) preg_match( '/Masadaki QR kodu tekrar okutun\./', $rest ), 'eski REST oturum metni kalmadı' );
		qrms_assert_contains( "qmo_ceviri_chat( __( 'Asistan şu anda yanıt veremiyor, lütfen tekrar deneyin.', 'qrms' ) )", $ajax, 'asistan' );
		qrms_assert_contains( "qmo_ceviri_chat( __( 'Bağlantı hatası oluştu.', 'qrms' ) )", $ajax, 'bağlantı ziyaretçi' );
		qrms_assert_contains( "qmo_log( 'Gemini API anahtarı boş.' )", $ajax, 'API anahtarı log' );
		qrms_assert_false( (bool) preg_match( '/Ayarlar sayfasından API anahtarını/', $ajax ), 'admin API metni ziyaretçiye gitmez' );
		qrms_assert_false( (bool) preg_match( '/Yanıt alınamadı, sebep:/', $ajax ), 'finishReason ziyaretçiye gitmez' );
		qrms_assert_contains( "qmo_ceviri_chat( __( 'Geçersiz sipariş', 'qrms' ) )", $rest, 'geçersiz sipariş' );
		qrms_assert_contains( "qmo_ceviri_chat( __( 'Çağrı sistemi şu anda kullanılamıyor.', 'qrms' ) )", $cagri, 'çağrı sistemi' );
		qrms_assert_contains( "qmo_ceviri_chat( __( 'İletildi', 'qrms' ) )", $cagri, 'İletildi' );
		qrms_assert_contains( "qmo_ceviri_chat( __( 'Geçersiz istek', 'qrms' ) )", $cagri, 'geçersiz istek' );
		qrms_assert_contains( "qmo_ceviri_chat( __( 'Çağrı iletilemedi, lütfen tekrar deneyin.', 'qrms' ) )", $cagri, 'çağrı iletilemedi' );
		qrms_assert_contains( "qmo_ceviri_chat( __( 'Talep alındı.', 'qrms' ) )", $cagri, 'talep alındı' );
		qrms_assert_contains( "\$_REQUEST['lang']", $dil, 'AJAX lang parametresi' );
		qrms_assert_contains( 'admin-ajax.php', $dil, 'cookie admin-ajax notu' );
		qrms_assert_false( (bool) preg_match( '/HTTP_ACCEPT_LANGUAGE/', $dil ), 'AJAX Accept-Language yok' );
	}
);

qrms_test(
	'qmo_ceviri_chat çeviri yoksa Türkçe döner; fetch çerez gönderir',
	function () {
		qrms_assert_same(
			'Çevrimiçi',
			qmo_ceviri_chat( 'Çevrimiçi' ),
			'tablo yokken Türkçe'
		);

		$btnjs = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/buttons.js' );
		$botjs = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/chatbot.js' );
		qrms_assert_contains( "credentials: 'same-origin'", $btnjs, 'buttons cookie' );
		qrms_assert_contains( "credentials: 'same-origin'", $botjs, 'chatbot cookie' );
	}
);

echo "\nQR Çeviri (P0 köprü / sepet)\n";

qrms_test(
	'cart kaynak metinleri katalogda; eski sepet-CSV yasağı kalktı',
	function () {
		$ui      = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/ui-stringler.php' );
		$metinler = rma_ceviri_modul_stringleri( 'cart' );
		$beklenen = array(
			'Sepet',
			'Sepetiniz',
			'Toplam',
			'Siparişi Gönder',
			'Sepetiniz boş',
			'Ürün notu (isteğe bağlı)…',
			'Sepete eklendi',
			'Siparişiniz mutfağa iletildi ✓',
			'Gönderilemedi, tekrar deneyin',
			'Ödeme TL üzerinden alınır.',
			'Sepeti aç',
			'Sil',
			'Kapat',
		);

		foreach ( $beklenen as $metin ) {
			qrms_assert_same(
				$metin,
				$metinler[ rma_ceviri_ui_anahtari( $metin ) ],
				$metin
			);
		}

		qrms_assert_false(
			(bool) preg_match( '/CSV\'de hiçbir zaman eşleşmeyen satırlar/', $ui ),
			'eski sepet yasağı yorumu kalktı'
		);
		qrms_assert_contains( 'item_type=cart', $ui, 'yeni cart notu' );
	}
);

qrms_test(
	'sepet PHP iskeleti cart köprüsüyle sarılı; aria eksikleri de sarıldı',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-sepet.php' );

		qrms_assert_contains( "qmo_ceviri_cart( __( 'Sepeti aç', 'qrms' ) )", $php, 'Sepeti aç aria' );
		qrms_assert_contains( "qmo_ceviri_cart( __( 'Sepet', 'qrms' ) )", $php, 'Sepet' );
		qrms_assert_contains( "qmo_ceviri_cart( __( 'Sepetiniz', 'qrms' ) )", $php, 'Sepetiniz' );
		qrms_assert_contains( "qmo_ceviri_cart( __( 'Kapat', 'qrms' ) )", $php, 'Kapat aria' );
		qrms_assert_contains( "qmo_ceviri_cart( __( 'Toplam', 'qrms' ) )", $php, 'Toplam' );
		qrms_assert_contains( "qmo_ceviri_cart( __( 'Siparişi Gönder', 'qrms' ) )", $php, 'Siparişi Gönder' );
		qrms_assert_false( (bool) preg_match( '/aria-label="Sepeti aç"/', $php ), 'sabit Sepeti aç kalmadı' );
		qrms_assert_false( (bool) preg_match( '/aria-label="Sil"/', $php ), 'PHP\'de sabit Sil yok' );
	}
);

qrms_test(
	'sepet.js iç tablo yedek kalır; localize tüm dilleri taşır',
	function () {
		$js   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' );
		$php  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-sepet.php' );
		$help = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/helpers.php' );

		qrms_assert_contains( "var TXT = {", $js, 'iç tablo durur' );
		qrms_assert_contains( "sepet: 'Cart'", $js, 'EN yedek' );
		qrms_assert_contains( "sepet: 'Warenkorb'", $js, 'DE yedek' );
		qrms_assert_contains( "sepet: 'السلة'", $js, 'AR yedek' );
		qrms_assert_contains( "function metin( anahtar, yedek )", $js, 'metin köprüsü' );
		qrms_assert_contains( 'qmoSepet.i18n', $js, 'localize okur' );
		qrms_assert_contains( "T( 'sil' )", $js, 'Sil aria T()' );
		qrms_assert_contains( "T( 'ac' )", $js, 'Sepeti aç T()' );
		qrms_assert_false( (bool) preg_match( "/aria-label',\s*'Sil'/", $js ), 'sabit Sil kalmadı' );
		qrms_assert_contains( 'qmo_ceviri_cart_js_metinleri', $php, 'localize tüm diller' );
		qrms_assert_contains( "'i18n'", $php, 'qmoSepet.i18n' );
		qrms_assert_contains( "cache'lenebilir", $php, 'cache gerekçesi shortcode' );
		qrms_assert_contains( 'function qmo_ceviri_cart', $help, 'PHP köprü' );
		qrms_assert_contains( 'function qmo_ceviri_cart_js_metinleri', $help, 'JS tablo' );
		qrms_assert_contains( 'tam sayfa cache', $help, 'cache gerekçesi helper' );
		qrms_assert_contains( 'rma_translate_field', $help, 'yalnız tablo satırı' );
	}
);

qrms_test(
	'qmo_ceviri_cart çeviri yoksa Türkçe; boş tablo localize\'i boş bırakır',
	function () {
		qrms_assert_same( 'Sepet', qmo_ceviri_cart( 'Sepet' ), 'tablo yokken Türkçe' );
		qrms_assert_same( 'Sepeti aç', qmo_ceviri_cart( 'Sepeti aç' ), 'aria Türkçe' );

		$i18n = qmo_ceviri_cart_js_metinleri();
		qrms_assert_true( is_array( $i18n ), 'i18n dizi' );
		qrms_assert_same( array(), $i18n, 'tablo boşken iç tablo yedek' );

		$anahtarlar = qmo_ceviri_cart_anahtarlari();
		qrms_assert_same( 'Ödeme TL üzerinden alınır.', $anahtarlar['tl'], 'TL metni olduğu gibi' );
		qrms_assert_same( 'Sepeti aç', $anahtarlar['ac'], 'denetim aria' );
		qrms_assert_same( 'Sil', $anahtarlar['sil'], 'denetim Sil' );
	}
);

echo "\nQR Çeviri (P0 köprü / yorum-feedback 7-1)\n";

qrms_test(
	'çağrı JS sunucu msg gövdesini okur; hız sınırı chat köprüsünde',
	function () {
		$js    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/buttons.js' );
		$ayar  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/class-ayarlar.php' );

		qrms_assert_contains( 'yanit.data.msg', $js, 'msg alanı' );
		qrms_assert_contains(
			"qmo_ceviri_chat( __( 'Çok hızlı soru gönderiyorsunuz. Lütfen biraz bekleyin.', 'qrms' ) )",
			$ayar,
			'hız sınırı'
		);
	}
);
