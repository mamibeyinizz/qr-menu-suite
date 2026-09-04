<?php
/**
 * QR Analiz izleme, saklama ve ortak varlık testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php';

echo "\nQR Analiz izleme nonce'u\n";

qrms_test(
	'uydurma nonce değeri artık kayıt açtırmaz',
	function () {
		// Eskiden yalnızca "alan boş mu?" diye bakılıyordu: security=x
		// göndermek yeterliydi ve tabloya kimlik doğrulaması olmadan
		// sınırsız satır eklenebiliyordu.
		$_POST['security'] = 'x';
		qrms_assert_false( QRMS_Analitik::izleme_gecerli_mi(), 'uydurma değer reddedilir' );

		$_POST['security'] = 'test-nonce-baska_eylem';
		qrms_assert_false( QRMS_Analitik::izleme_gecerli_mi(), 'başka eylemin nonce\'u reddedilir' );
	}
);

qrms_test(
	'geçerli menü nonce\'u kabul edilir',
	function () {
		$_POST['security'] = wp_create_nonce( QRMS_Analitik::NONCE_TAKIP );

		qrms_assert_true( QRMS_Analitik::izleme_gecerli_mi(), 'menü nonce\'u geçer' );
	}
);

qrms_test(
	'izleme nonce eylemi menü modülünün ürettiğiyle AYNIDIR',
	function () {
		// Ad kayarsa izleme sessizce tamamen durur; bu yüzden üretim yeri
		// doğrudan kaynaktan doğrulanır.
		qrms_assert_same( 'rma_ajax_nonce', QRMS_Analitik::NONCE_TAKIP, 'sabit değeri' );

		$menu_kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-frontend.php'
		);

		qrms_assert_true(
			false !== strpos( $menu_kaynak, "wp_create_nonce( '" . QRMS_Analitik::NONCE_TAKIP . "' )" ),
			'menü tarafı aynı eylemle üretiyor'
		);
	}
);

qrms_test(
	'nonce alanı hiç yoksa kayıt açılmaz',
	function () {
		qrms_assert_false( QRMS_Analitik::izleme_gecerli_mi(), 'alan yok' );

		$_POST['security'] = '';
		qrms_assert_false( QRMS_Analitik::izleme_gecerli_mi(), 'alan boş' );
	}
);

echo "\nQR Analiz saklama politikası\n";

qrms_test(
	'varsayılan saklama süresi 90 gündür',
	function () {
		qrms_assert_same( 90, QRMS_Analitik::saklama_gun(), 'varsayılan' );
		qrms_assert_same( 90, QRMS_Analitik::SAKLAMA_GUN, 'sabit' );
	}
);

qrms_test(
	'saklama süresi filtreyle değiştirilebilir, alt sınırı 7 gündür',
	function () {
		add_filter(
			'qrms_analitik_saklama_gun',
			function () {
				return 30;
			}
		);

		qrms_assert_same( 30, QRMS_Analitik::saklama_gun(), 'filtre uygulanır' );
	}
);

qrms_test(
	'çok kısa saklama süresi 7 güne yükseltilir',
	function () {
		// Daha kısası panelin "son 30 gün" görünümlerini boşaltırdı.
		add_filter(
			'qrms_analitik_saklama_gun',
			function () {
				return 1;
			}
		);

		qrms_assert_same( 7, QRMS_Analitik::saklama_gun(), 'alt sınıra çekilir' );
	}
);

qrms_test(
	'sıfır ya da negatif değer temizliği tamamen kapatır',
	function () {
		add_filter(
			'qrms_analitik_saklama_gun',
			function () {
				return 0;
			}
		);

		qrms_assert_same( 0, QRMS_Analitik::saklama_gun(), 'temizlik kapalı' );
	}
);

qrms_test(
	'günlük temizlik görevi bir kez planlanır',
	function () {
		QRMS_Analitik::temizlik_planla();

		$ilk = wp_next_scheduled( QRMS_Analitik::CRON_TEMIZLIK );
		qrms_assert_true( (bool) $ilk, 'görev kuruldu' );

		QRMS_Analitik::temizlik_planla();
		qrms_assert_same( $ilk, wp_next_scheduled( QRMS_Analitik::CRON_TEMIZLIK ), 'ikinci kez planlanmaz' );

		QRMS_Analitik::temizlik_iptal();
		qrms_assert_false( wp_next_scheduled( QRMS_Analitik::CRON_TEMIZLIK ), 'iptal temizler' );
	}
);

qrms_test(
	'init() hem temizlik kancasını hem planlayıcıyı bağlar',
	function () {
		QRMS_Analitik::init();

		$kancalar = $GLOBALS['qrms_test']['actions'];

		qrms_assert_true(
			isset( $kancalar[ QRMS_Analitik::CRON_TEMIZLIK ] ),
			'cron kancası dinleniyor'
		);
		qrms_assert_true(
			in_array(
				array( 'QRMS_Analitik', 'temizlik_planla' ),
				$kancalar['init'],
				true
			),
			'init planlayıcıyı çağırıyor'
		);
	}
);

qrms_test(
	'eklenti devre dışı bırakılırken temizlik görevi kaldırılır',
	function () {
		// Kanca adı kök eklenti dosyasında elle yazılıdır (modül lisansta
		// kapalıyken sınıf yüklenmemiş olabilir); iki taraf kaymamalı.
		$kok = file_get_contents( QRMS_PLUGIN_DIR . 'qr-menu-suite.php' );

		qrms_assert_true(
			false !== strpos( $kok, "wp_clear_scheduled_hook( '" . QRMS_Analitik::CRON_TEMIZLIK . "' )" ),
			'deaktivasyon aynı kancayı temizliyor'
		);
	}
);

qrms_test(
	'tip bazlı saklama sepeti kısaltır, siparişi uzatır, tanımsız varsayılanı kullanır',
	function () {
		delete_option( QRMS_Analitik::SAKLAMA_OPT );

		qrms_assert_same( 14, QRMS_Analitik::saklama_gun_tip( 'cart_add' ), 'sepet ekleme kısa' );
		qrms_assert_same( 14, QRMS_Analitik::saklama_gun_tip( 'cart_remove' ), 'sepet çıkarma kısa' );
		qrms_assert_same( 14, QRMS_Analitik::saklama_gun_tip( 'splash_view' ), 'açılış gösterimi kısa' );
		qrms_assert_same( 30, QRMS_Analitik::saklama_gun_tip( 'chatbot_message' ), 'chatbot orta' );
		qrms_assert_same( 30, QRMS_Analitik::saklama_gun_tip( 'splash_action' ), 'açılış eylemi orta' );
		qrms_assert_same( 180, QRMS_Analitik::saklama_gun_tip( 'review_submit' ), 'yorum uzun' );
		qrms_assert_same( 365, QRMS_Analitik::saklama_gun_tip( 'reward_issued' ), 'ödül uzun' );
		qrms_assert_same( 365, QRMS_Analitik::saklama_gun_tip( 'order_sent' ), 'sipariş uzun' );
		qrms_assert_same( 365, QRMS_Analitik::saklama_gun_tip( 'order_blocked' ), 'engel uzun' );
		qrms_assert_same( 90, QRMS_Analitik::saklama_gun_tip( 'menu_view' ), 'tanımsız varsayılan' );
		qrms_assert_same( 90, QRMS_Analitik::saklama_gun_tip( 'waiter_call' ), 'çağrı varsayılan' );

		add_filter(
			'qrms_analitik_saklama_gun_tip',
			function ( $harita ) {
				$harita['cart_add'] = 3;
				return $harita;
			}
		);

		qrms_assert_same( 3, QRMS_Analitik::saklama_gun_tip( 'cart_add' ), 'filtre tip istisnasını ezer' );
		qrms_assert_same( 14, QRMS_Analitik::saklama_gun_tip( 'cart_remove' ), 'diğer istisna durur' );

		$GLOBALS['qrms_test']['actions']['qrms_analitik_saklama_gun_tip'] = array();
		delete_option( QRMS_Analitik::SAKLAMA_OPT );
	}
);

qrms_test(
	'global temizlik kapalıyken tip istisnası da silmez',
	function () {
		add_filter(
			'qrms_analitik_saklama_gun',
			function () {
				return 0;
			}
		);

		qrms_assert_same( 0, QRMS_Analitik::saklama_gun_tip( 'cart_add' ), 'sepet de saklanır' );
		qrms_assert_same( 0, QRMS_Analitik::eski_kayitlari_sil(), 'hiçbir tip silinmez' );
	}
);

qrms_test(
	'sepet ve sipariş olay adları varchar(30) sınırının altında',
	function () {
		foreach ( QRMS_Analitik::OLAY_TIPLERI as $tip ) {
			qrms_assert_true( strlen( $tip ) <= 30, $tip . ' uzunluğu' );
		}
	}
);

qrms_test(
	'kaydet dışarıdan çağrılabilir ve tek yazım yoludur',
	function () {
		$yansima = new ReflectionMethod( 'QRMS_Analitik', 'kaydet' );
		qrms_assert_true( $yansima->isPublic(), 'kaydet public' );

		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php' );
		qrms_assert_same( 1, substr_count( $kaynak, '$wpdb->insert(' ), 'tek INSERT' );

		$yardimci = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/helpers.php' );
		qrms_assert_contains( 'function qmo_analitik_yaz', $yardimci, 'sessiz yazım köprüsü' );
		qrms_assert_contains( "class_exists( 'QRMS_Analitik' )", $yardimci, 'lisans/modül yoksa no-op' );
		qrms_assert_contains( 'QRMS_Analitik::kaydet', $yardimci, 'köprü kaydet kullanır' );
	}
);

echo "\nQR Analiz — Faz 8 olay yazımı\n";

qrms_test(
	'Faz 8 olay tipleri varchar(30) altında ve saklama haritasında',
	function () {
		$beklenen = array(
			'chatbot_message',
			'lang_switch',
			'splash_view',
			'splash_action',
			'gallery_view',
			'reward_issued',
			'reward_redeemed',
			'review_submit',
			'form_submit',
			'item_detail_open',
		);

		foreach ( $beklenen as $tip ) {
			qrms_assert_true( in_array( $tip, QRMS_Analitik::OLAY_TIPLERI, true ), $tip . ' listede' );
			qrms_assert_true( strlen( $tip ) <= 30, $tip . ' uzunluğu' );
		}

		qrms_assert_same( 14, QRMS_Analitik::saklama_gun_tip( 'splash_view' ), 'splash_view en kısa yeni tip' );
	}
);

qrms_test(
	'chatbot_message hız sınırından sonra yazılır, mesaj içeriği gitmez',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/ajax-chat.php' );

		$zorla = strpos( $php, 'qmo_chat_zorla' );
		$yaz   = strpos( $php, 'chatbot_message' );
		$bos   = strpos( $php, "'' === \$message" );

		qrms_assert_true( false !== $zorla && false !== $yaz && false !== $bos, 'noktalar var' );
		qrms_assert_true( $zorla < $yaz, 'yazım oturum/limitten sonra' );
		qrms_assert_true( $bos < $yaz, 'boş mesaj yazılmaz' );
		qrms_assert_contains( "'masa_no'", $php, 'masa doldurulur' );
		qrms_assert_false( false !== strpos( $php, "'item_name'     => \$message" ), 'mesaj içeriği yazılmaz' );
		qrms_assert_false( false !== strpos( $php, "'item_name' => \$message" ), 'mesaj içeriği yazılmaz (kısa)' );
	}
);

qrms_test(
	'dil değiştirici ve splash/galeri beacon üzerinden yazar',
	function () {
		$ceviri = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/assets/js/ceviri.js' );
		$splash = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-acilis-ekrani/assets/js/splash.js' );
		$galeri = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-galeri/includes/trait-assets.php' );
		$beacon = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik-onyuz.js' );
		$sinif  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php' );

		qrms_assert_contains( "yaz( 'lang_switch'", $ceviri, 'dil seçici hedef dili yazar' );
		qrms_assert_contains( "analitikYaz('splash_view')", $splash, 'gösterim yazılır' );
		qrms_assert_contains( "splashEylem('menu')", $splash, 'menü eylemi' );
		qrms_assert_contains( "splashEylem('atla')", $splash, 'atlanma eylemi' );
		qrms_assert_contains( "splashEylem('wifi')", $splash, 'wifi eylemi' );
		qrms_assert_contains( "yaz('gallery_view')", $galeri, 'galeri açılışı' );
		qrms_assert_contains( 'qrms_analitik_onyuz', $beacon, 'beacon action' );
		qrms_assert_contains( 'keepalive: true', $beacon, 'navigasyonu kesmez' );
		qrms_assert_contains( 'function masa_onyuz', $sinif, 'masa POST\'tan okunmaz' );
		qrms_assert_contains( 'NONCE_ONYUZ', $sinif, 'ön yüz nonce' );
		qrms_assert_false( false !== strpos( $beacon, 'masa' ), 'istemci masa göndermez' );
	}
);

qrms_test(
	'ödül ve form olayları içerik yazmaz, honeypot sayılmaz',
	function () {
		$odul  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/rewards/functions.php' );
		$yorum = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/ajax/submit-review.php' );
		$form  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/ajax/submit-custom-form.php' );

		qrms_assert_contains( "'event_type' => 'reward_issued'", $odul, 'kod üretimi' );
		qrms_assert_contains( "'event_type' => 'reward_redeemed'", $odul, 'kod kullanımı' );
		qrms_assert_contains( "qmo_analitik_yaz(['event_type' => 'review_submit'])", $yorum, 'yorum olayı' );
		qrms_assert_contains( "'event_type' => 'form_submit'", $form, 'form olayı' );
		qrms_assert_contains( "'item_name'  => isset(\$form->title)", $form, 'form adı' );

		$yorum_yaz = strpos( $yorum, 'review_submit' );
		$yorum_ins = strpos( $yorum, '$wpdb->insert' );
		$honeypot  = strpos( $yorum, 'qrm_website' );

		qrms_assert_true( false !== $yorum_yaz && false !== $yorum_ins && false !== $honeypot, 'yorum noktaları' );
		qrms_assert_true( $honeypot < $yorum_ins, 'honeypot insertten önce çıkar' );
		qrms_assert_true( $yorum_ins < $yorum_yaz, 'yazım kayıttan sonra' );
		qrms_assert_false( false !== strpos( $yorum, '$comment' ) && false !== strpos( substr( $yorum, $yorum_yaz, 400 ), '$comment' ), 'yorum metni analitiğe gitmez' );
		qrms_assert_false( false !== strpos( $form, "validated['data']" ) && false !== strpos( substr( $form, strpos( $form, 'form_submit' ), 350 ), "validated['data']" ), 'form yanıtı analitiğe gitmez' );
	}
);

qrms_test(
	'ön yüz olay kuralları izin listesi dışını reddeder',
	function () {
		$kurallar = QRMS_Analitik::onyuz_olay_kurallari();

		qrms_assert_true( isset( $kurallar['lang_switch']['item_name'] ), 'dil listesi' );
		qrms_assert_true( in_array( 'tr', $kurallar['lang_switch']['item_name'], true ), 'tr' );
		qrms_assert_true( in_array( 'en', $kurallar['lang_switch']['item_name'], true ), 'en' );
		qrms_assert_true( in_array( 'menu', $kurallar['splash_action']['item_name'], true ), 'menu' );
		qrms_assert_true( in_array( 'atla', $kurallar['splash_action']['item_name'], true ), 'atla' );
		qrms_assert_true( in_array( 'wifi', $kurallar['splash_action']['item_name'], true ), 'wifi' );
		qrms_assert_same( 'qr-acilis-ekrani', $kurallar['splash_view']['modul'], 'splash modüle bağlı' );
		qrms_assert_same( 'qr-galeri', $kurallar['gallery_view']['modul'], 'galeri modüle bağlı' );
		qrms_assert_same( 'qr-ceviri', $kurallar['lang_switch']['modul'], 'dil modüle bağlı' );
	}
);

qrms_test(
	'olay_sayaclari idx_td aralık taraması kullanır, şema sürümü kurulu sitelere yansıyacak şekilde artırılmış',
	function () {
		$wpdb            = qrms_sayan_wpdb();
		$wpdb->results[] = array(
			array(
				'event_type' => 'chatbot_message',
				'item_name'  => '',
				'adet'       => 4,
			),
		);

		$sonuc = QRMS_Analitik::olay_sayaclari(
			array( 'chatbot_message', 'review_submit' ),
			'2026-03-01 00:00:00',
			'2026-03-31 23:59:59'
		);

		qrms_assert_same( 1, count( $sonuc ), 'satır' );
		qrms_assert_same( 4, $sonuc[0]['adet'], 'adet' );
		qrms_assert_contains( 'event_type IN', $wpdb->queries[0], 'IN listesi' );
		qrms_assert_contains( 'created_at BETWEEN', $wpdb->queries[0], 'idx_td aralığı' );
		qrms_assert_contains( 'GROUP BY event_type, item_name', $wpdb->queries[0], 'kırılım' );

		// Menü Mühendisliği modülü satış adedini okuyabilsin diye tabloya
		// qty sütunu eklendi (bkz. CREATE TABLE'daki qty smallint). Yeni
		// sütun kurulu sitelere ANCAK dbDelta yeniden çalışırsa ulaşır;
		// sema_kontrol() bunu DB_SURUM değişimine bakarak tetikler. Sürüm
		// artmadan kalsaydı mevcut kurulumlar sütunu hiç görmezdi.
		$sema = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php' );
		qrms_assert_contains( "const DB_SURUM = '1.2'", $sema, 'şema sürümü qty eklemesiyle artırıldı' );
		qrms_assert_contains( 'qty smallint', $sema, 'qty sütunu CREATE TABLE içinde' );
	}
);

/* ---------------------------------------------------------------------------
 * 9a-1. Ortak varlıklar — aynı dosyanın iki handle ile yüklenmesi
 * ------------------------------------------------------------------------ */

// assets.php dosya kapsamında yalnızca fonksiyon tanımlar ve stub'lanmış
// add_action çağrıları yapar.
require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/assets.php';

echo "\nOrtak varlık handle'ları\n";

qrms_test(
	'aynı dosyayı gösteren handle tek kanonik ada indirgenir',
	function () {
		// [qr_garson_hesap] ve [ikili_buton] ile [garson_butonu] aynı
		// buttons.css/buttons.js dosyalarını kullanıyor. Handle'lar ayrı
		// kalırsa WordPress dosyayı iki kez basar, buttons.js iki kez çalışır
		// ve butonlara olay dinleyicileri iki kez bağlanır.
		qrms_assert_same( 'qmo-buttons', qmo_asset_kanonik_handle( 'qmo-garson-hesap' ), 'takma ad indirgenir' );
		qrms_assert_same( 'qmo-buttons', qmo_asset_kanonik_handle( 'qmo-buttons' ), 'kanonik ad korunur' );
	}
);

qrms_test(
	'takma ad olmayan handle\'lar olduğu gibi geçer',
	function () {
		foreach ( array( 'qmo-chatbot', 'qmo-sepet', 'qmo-oturum-kutu', 'bilinmeyen-handle' ) as $handle ) {
			qrms_assert_same( $handle, qmo_asset_kanonik_handle( $handle ), $handle . ' değişmez' );
		}
	}
);

qrms_test(
	'takma ad handle\'ı KENDİ kaynağıyla kaydedilmez',
	function () {
		// Yapısal güvence: qmo-garson-hesap kaydı bir kaynak yolu taşırsa
		// WordPress onu bağımsız bir dosya sayar ve çift yükleme geri gelir.
		// Kayıt kaynaksız (false) olmalı ve qmo-buttons'a bağımlı durmalı.
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/chatbot.php' );

		qrms_assert_true(
			(bool) preg_match(
				"/wp_register_script\(\s*'qmo-garson-hesap',\s*false,\s*array\(\s*'qmo-buttons'\s*\)/",
				$kaynak
			),
			'script takma adı kaynaksız ve qmo-buttons bağımlı'
		);
		qrms_assert_true(
			(bool) preg_match(
				"/wp_register_style\(\s*'qmo-garson-hesap',\s*false,\s*array\(\s*'qmo-buttons'\s*\)/",
				$kaynak
			),
			'stil takma adı kaynaksız ve qmo-buttons bağımlı'
		);
		qrms_assert_false(
			(bool) preg_match( "/'qmo-garson-hesap',\s*\\\$url/", $kaynak ),
			'takma ad artık kendi dosya yolunu göstermiyor'
		);
	}
);

/* ---------------------------------------------------------------------------
 * 9a-2. Güvenlik Ayarı — oturum limitleri ve SAYFA KİLİDİ ayar kaydı
 * ------------------------------------------------------------------------ */

// Oturum sınıfı ile ayar ekranı. İkisi de dosya kapsamında yalnızca tanım
// yapar; add_action çağrıları stub ortamında yan etkisizdir.
