<?php
/**
 * Garson Çağır / Hesap İste — interaction/state UX revizyonu (V1) testleri.
 *
 * Kapsam: qmo_cagri_gonder() yanıt şekli (additive 'cooldown' alanı),
 * buttons.js state makinesi (idle/loading/success, çift tıklama koruması,
 * erişilebilirlik, hata kurtarma) ve i18n köprüsü.
 *
 * NEDEN ÇOĞU KONTROL KAYNAK METNİ ÜZERİNDEN: qmo_cagri_gonder() (ve onun
 * çağırdığı qmo_oturum_zorla()/qmo_nonce_dogrula()) her erken-dönüş
 * dalında wp_send_json_error() çağırır ama ARDINDAN return/exit YOKTUR —
 * gerçek WordPress'te bunu wp_die() durdurur, bu stub ortamı durdurmaz
 * (bkz. test-ajax-403-status.php dosya başlığı ve test-chatbot.php'deki
 * "canlı sohbet AJAX uçları" testi — aynı fonksiyon AYNI nedenle sadece
 * kaynak metinden doğrulanıyor). Bu yüzden 403/429/503/500 dalları kaynak
 * metniyle doğrulanır; yalnızca HİÇBİR erken-dönüş dalının tetiklenmediği
 * mutlu yol (nonce+oturum+Firestore hazır+hız sınırı içinde) gerçek
 * çalıştırmayla test edilir — o yolda wp_send_json_success() zaten TEK
 * çağrılan wp_send_json'dur.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/class-qmo-firestore.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/ajax-waiter-bill.php';
require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/assets.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-buttons.php';

echo "\nGarson Çağır / Hesap İste — UX state revizyonu\n";

/**
 * QRMS_Analitik::kaydet()'in $wpdb->insert() çağrısını yakalayan minimal
 * stub — test-ajax-403-status.php'deki aynı desen (ör.
 * QRMS_Ajax403_Reward_Wpdb). Gerçek bir tablo/DB gerektirmez; qmo_cagri_gonder
 * başarı yolunda hâlâ qmo_analitik_yaz() çağırdığını KANITLAMAK için kullanılır.
 */
if ( ! class_exists( 'QRMS_GH_Wpdb' ) ) {
	class QRMS_GH_Wpdb {
		public $prefix  = 'wp_';
		public $inserts = array();

		public function insert( $table, $data, $format = null ) {
			$this->inserts[] = array( 'table' => $table, 'data' => $data );
			return 1;
		}
	}
}

/**
 * @return QRMS_GH_Wpdb
 */
function qrms_gh_wpdb() {
	$GLOBALS['wpdb'] = new QRMS_GH_Wpdb();
	return $GLOBALS['wpdb'];
}

/**
 * Firestore'u "hazır" duruma getirir ve calls koleksiyonuna yazımı taklit
 * eder (test-servis-paneli.php'deki desenle aynı: access_token() önce
 * transient'e bakar, gerçek JWT imzalamaya hiç girilmez).
 *
 * @param string $proje Firebase proje kimliği.
 * @return void
 */
function qrms_gh_firestore_hazir( $proje = 'test-gh-proj' ) {
	update_option(
		'qmo_firebase_sa',
		wp_json_encode(
			array(
				'client_email' => 'svc@test-gh-proj.iam.gserviceaccount.com',
				'private_key'  => 'test-anahtar',
				'project_id'   => $proje,
			)
		)
	);
	set_transient(
		'qmo_gcp_token_' . substr( md5( QMO_Firestore::SCOPE_DATASTORE ), 0, 12 ),
		'test-token',
		3500
	);

	$GLOBALS['qrms_test']['http'] = function ( $url ) use ( $proje ) {
		if ( false !== strpos( $url, "/{$proje}/databases/(default)/documents/calls" ) ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array( 'name' => "projects/{$proje}/databases/(default)/documents/calls/T1" )
				),
			);
		}
		return new WP_Error( 'beklenmeyen_url', 'mock kapsamı dışı: ' . $url );
	};
}

/**
 * Geçerli bir masa oturumu + nonce POST alanı kurar.
 *
 * @param string $masa Masa slug'ı.
 * @return void
 */
function qrms_gh_oturum_kur( $masa ) {
	$GLOBALS['qrms_test']['options'][ QMO_Oturum::OPT_KEY ] = 'test-hmac-anahtari-garson-hesap-ux-icin-yeterince-uzun';
	$_COOKIE[ QMO_Oturum::COOKIE ] = QMO_Oturum::token_uret( $masa );
	$_POST['nonce'] = wp_create_nonce( QMO_NONCE_ACTION );
}

/* =========================================================================
   1) Backend — mutlu yol + additive 'cooldown' metadata
========================================================================= */

qrms_test(
	'garson çağrısı başarılı: nonce+oturum+Firestore hazırsa success + cooldown döner',
	function () {
		qrms_gh_oturum_kur( 'masa-201' );
		qrms_gh_firestore_hazir();
		$wpdb = qrms_gh_wpdb();

		qmo_ajax_garson_cagir();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_true( $json['success'], 'başarılı' );
		qrms_assert_same( 60, $json['data']['cooldown'], 'öntanımlı 60sn hız sınırı penceresi yanıta yansır' );
		qrms_assert_true( ! empty( $json['data']['mesaj'] ), 'frontend alanı (res.data.mesaj) korunur' );
		qrms_assert_true( ! empty( $json['data']['msg'] ), 'frontend alanı (res.data.msg) korunur' );

		qrms_assert_same( 1, count( $GLOBALS['qrms_test']['http_calls'] ), 'tek Firestore yazım isteği' );
		$govde = $GLOBALS['qrms_test']['http_calls'][0]['args']['body'];
		qrms_assert_true( false !== strpos( $govde, '"masaNo":{"stringValue":"masa-201"}' ), 'masa oturumdan Firestore\'a yazıldı' );
		qrms_assert_true( false !== strpos( $govde, '"tip":{"stringValue":"garson"}' ), 'tip garson' );

		// Analitik: mevcut event kaydı bozulmadı (event_type/masa_no doğru).
		qrms_assert_same( 1, count( $wpdb->inserts ), 'tam olarak bir analitik satırı yazıldı' );
		qrms_assert_same( 'waiter_call', $wpdb->inserts[0]['data']['event_type'], 'garson çağrısı waiter_call olarak kaydedilir' );
		qrms_assert_same( 'masa-201', $wpdb->inserts[0]['data']['masa_no'], 'analitik satırı doğru masaya bağlanır' );
	}
);

qrms_test(
	'hesap isteği başarılı: cooldown döner, Firestore\'a tip=hesap yazılır, analitik bill_request olarak kaydedilir',
	function () {
		qrms_gh_oturum_kur( 'masa-202' );
		qrms_gh_firestore_hazir();
		$wpdb = qrms_gh_wpdb();

		qmo_ajax_hesap_iste();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_true( $json['success'], 'başarılı' );
		qrms_assert_same( 180, $json['data']['cooldown'], 'hesap için öntanımlı pencere garsondan uzun (180sn) — bkz. FIX #3' );

		$govde = $GLOBALS['qrms_test']['http_calls'][0]['args']['body'];
		qrms_assert_true( false !== strpos( $govde, '"tip":{"stringValue":"hesap"}' ), 'tip hesap' );

		qrms_assert_same( 1, count( $wpdb->inserts ), 'tam olarak bir analitik satırı yazıldı' );
		qrms_assert_same( 'bill_request', $wpdb->inserts[0]['data']['event_type'], 'hesap isteği bill_request olarak kaydedilir' );
	}
);

qrms_test(
	'qmo_cagri_bekleme filtresi cooldown\'ı değiştirirse yanıta yansır (backward-compatible additive alan)',
	function () {
		qrms_gh_oturum_kur( 'masa-203' );
		qrms_gh_firestore_hazir();
		qrms_gh_wpdb();

		$filtre = function ( $saniye, $tip ) {
			return 'garson' === $tip ? 15 : $saniye;
		};
		add_filter( 'qmo_cagri_bekleme', $filtre, 10, 2 );

		qmo_ajax_garson_cagir();

		// qrms_reset() her qrms_test() başında action/filter defterini
		// temizler; bu yüzden burada remove_filter() gerekmez (stub'da yok).
		qrms_assert_same( 15, $GLOBALS['qrms_test']['json']['data']['cooldown'], 'filtrelenmiş pencere yanıta yansır' );
	}
);

qrms_test(
	'FIX #3: hesap ve garson öntanımlı cooldown\'ları FARKLI (garson 60sn korunur, hesap 180sn\'e çıkar), her ikisi de qmo_cagri_bekleme ile override edilebilir kalır',
	function () {
		qrms_gh_oturum_kur( 'masa-204' );
		qrms_gh_firestore_hazir();
		qrms_gh_wpdb();
		qmo_ajax_garson_cagir();
		$garsonCooldown = $GLOBALS['qrms_test']['json']['data']['cooldown'];

		qrms_gh_oturum_kur( 'masa-205' );
		qrms_gh_firestore_hazir();
		qrms_gh_wpdb();
		qmo_ajax_hesap_iste();
		$hesapCooldown = $GLOBALS['qrms_test']['json']['data']['cooldown'];

		qrms_assert_same( 60, $garsonCooldown, 'garsonun mevcut rate-limit davranışı DEĞİŞMEDİ' );
		qrms_assert_same( 180, $hesapCooldown, 'hesap için UX cooldown\'ı garsondan uzun' );
		qrms_assert_true( $hesapCooldown > $garsonCooldown, 'hesap süresi garsondan gerçekten uzun' );

		// Aynı filtre mekanizması hâlâ HER İKİ tipi de override edebilir —
		// yeni bir sistem icat edilmedi, sadece varsayılan değişti.
		qrms_gh_oturum_kur( 'masa-206' );
		qrms_gh_firestore_hazir();
		qrms_gh_wpdb();
		$ozelFiltre = function ( $saniye, $tip ) {
			return 'hesap' === $tip ? 30 : $saniye;
		};
		add_filter( 'qmo_cagri_bekleme', $ozelFiltre, 10, 2 );
		qmo_ajax_hesap_iste();
		qrms_assert_same( 30, $GLOBALS['qrms_test']['json']['data']['cooldown'], 'operatör filtreyle yeni varsayılanı da ezebilir' );
	}
);

/* =========================================================================
   2) Backend — hız sınırı primitifi (qmo_hiz_siniri) izole testi
      (qmo_cagri_gonder'in 429 dalı wp_die() gerektirdiği için tam çalıştırma
      ile test edilemez — bkz. dosya başlığı; primitif kendisi saf bir
      fonksiyondur ve doğrudan test edilebilir.)
========================================================================= */

qrms_test(
	'qmo_hiz_siniri: aynı anahtar+masa için pencere içinde ikinci çağrı reddedilir',
	function () {
		qrms_assert_true( qmo_hiz_siniri( 'cagri_test_gh', 'masa-300', 60 ), 'ilk çağrıya izin var' );
		qrms_assert_false( qmo_hiz_siniri( 'cagri_test_gh', 'masa-300', 60 ), 'aynı pencerede ikinci çağrı reddedilir' );
	}
);

qrms_test(
	'qmo_hiz_siniri: farklı masa aynı anahtarla çakışmaz (masa+IP bazlı)',
	function () {
		qrms_assert_true( qmo_hiz_siniri( 'cagri_test_gh2', 'masa-301', 60 ), 'masa-301 ilk çağrı' );
		qrms_assert_true( qmo_hiz_siniri( 'cagri_test_gh2', 'masa-302', 60 ), 'masa-302 bağımsız pencere' );
	}
);

/* =========================================================================
   3) Backend — kaynak metni doğrulaması: nonce/oturum/hız sınırı/analitik
      sırası ve mesajları DEĞİŞMEDİ (bkz. dosya başlığı — 403/429/503/500
      dalları wp_die() gerektirir, tam çalıştırma ile test edilemez).
========================================================================= */

qrms_test(
	'qmo_cagri_gonder: oturum zorlama, masa kaynağı, hız sınırı ve analitik sırası korunuyor',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/ajax-waiter-bill.php' );

		qrms_assert_contains( '$sess = qmo_oturum_zorla();', $php, 'nonce+oturum zorlanır' );
		qrms_assert_contains( "\$masa = \$sess['masa'];", $php, 'masa DOĞRULANMIŞ oturumdan okunur' );
		qrms_assert_contains( "qmo_hiz_siniri( 'cagri_' . \$tip, \$masa, \$saniye )", $php, 'masa+IP hız sınırı korunur' );
		qrms_assert_contains(
			"apply_filters( 'qmo_cagri_bekleme', ( 'hesap' === \$tip ? 180 : 60 ), \$tip )",
			$php,
			'FIX #3: aynı filtre mekanizması, tip bazlı yeni varsayılan (garson 60 korunur, hesap 180)'
		);
		qrms_assert_contains(
			"wp_send_json_error( array( 'msg' => qmo_ceviri_chat( __( 'Çağrınız iletildi, lütfen bekleyin.', 'qrms' ) ) ), 429 )",
			$php,
			'429 mesajı ve durum kodu değişmedi'
		);
		qrms_assert_contains(
			"'event_type' => ( 'hesap' === \$tip ) ? 'bill_request' : 'waiter_call'",
			$php,
			'analitik olay tipleri değişmedi'
		);

		qrms_assert_true(
			strpos( $php, 'qmo_analitik_yaz(' ) < strpos( $php, 'qmo_db_serbest_birak()' ),
			'analitik, hız sınırına takılmayan çağrılar için hâlâ DB serbest bırakmadan ÖNCE yazılır'
		);
		qrms_assert_true(
			strpos( $php, "if ( ! qmo_hiz_siniri" ) < strpos( $php, 'qmo_analitik_yaz(' ),
			'hız sınırına takılan çağrılar analitik sayacını şişirmez (kontrol önce)'
		);

		qrms_assert_contains( "'cooldown' => \$saniye", $php, 'yeni additive alan: gerçek hız sınırı penceresi yanıta eklendi' );
		qrms_assert_contains( "'msg'      => qmo_ceviri_chat( __( 'İletildi', 'qrms' ) )", $php, 'mevcut msg alanı korunur' );
		qrms_assert_contains( "'mesaj'    => qmo_ceviri_chat( __( 'Talep alındı.', 'qrms' ) )", $php, 'mevcut mesaj alanı korunur' );
	}
);

/* =========================================================================
   4) Frontend (buttons.js) — state makinesi kaynak doğrulaması
========================================================================= */

qrms_test(
	'buttons.js: tek merkezi durum fonksiyonu var; loading disabled+aria-busy uygular',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/buttons.js' );

		qrms_assert_contains( 'function durumUygula(', $js, 'tek merkezi state fonksiyonu' );
		qrms_assert_contains( "btn.setAttribute( 'aria-busy', 'true' )", $js, 'loading aria-busy=true' );
		qrms_assert_contains( "btn.classList.add( 'is-disabled' )", $js, 'loading mevcut is-disabled sınıfını kullanır (yeni paralel sınıf yok)' );
	}
);

qrms_test(
	'FIX #1: buttons.js etiket span\'ini SINIFLA seçer (ikon span\'ini ezmez); shortcode ve HFB markup\'ı bu sınıfı taşır',
	function () {
		$js       = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/buttons.js' );
		$shortcode = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-buttons.php' );
		$hfb      = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/trait-frontend.php' );

		qrms_assert_contains(
			"var span = btn.querySelector( '.qmo-cagri-etiket' );",
			$js,
			'seçici artık isimsiz \'span\' değil, açık .qmo-cagri-etiket class\'ı — ikon span\'i (qmo-cagri-ikon) artık asla eşleşmez'
		);
		qrms_assert_false( false !== strpos( $js, "querySelector( 'span' )" ), 'isimsiz/belirsiz span seçici kalmadı' );
		qrms_assert_false(
			(bool) preg_match( '/querySelector\\(\\s*[\'"]span:(last-of-type|not|first)/', $js ),
			':last-of-type / :not gibi kırılgan CSS seçici hilesi kullanılmadı — açık class kullanılıyor'
		);

		// shortcode-buttons.php: ikon VE etiket ayrı class'lara sahip, ikisi de span ama artık birbirinden ayırt edilebilir.
		qrms_assert_contains( 'class="qmo-cagri-ikon"', $shortcode, 'ikon span\'i kendi class\'ında kalır' );
		qrms_assert_same(
			2,
			substr_count( $shortcode, 'class="qmo-cagri-etiket"' ),
			'iki buton (garson+hesap), her birinde tam bir etiket span\'i'
		);

		// HFB footer: ikon <svg> (span değil), tek span'e de AYNI class eklendi —
		// böylece JS'in tek seçicisi iki farklı markup şeklinde de çalışır.
		qrms_assert_same(
			2,
			substr_count( $hfb, "<span class=\"qmo-cagri-etiket\">" ),
			'HFB\'nin iki butonunda da (garson+hesap) etiket span\'i aynı class\'ı taşır'
		);
	}
);

qrms_test(
	'FIX #1 (gerçek render): qmo_cagri_butonlari_html() ürettiği GERÇEK HTML\'de ikon ve etiket ayrı elemanlar, ikon metni bozulmuyor',
	function () {
		$GLOBALS['qrms_test']['options'][ QMO_Oturum::OPT_KEY ] = 'test-hmac-anahtari-garson-hesap-ux-icin-yeterince-uzun';
		$_COOKIE[ QMO_Oturum::COOKIE ] = QMO_Oturum::token_uret( 'masa-210' );

		$html = qmo_cagri_butonlari_html( 'ikili' );

		qrms_assert_true( '' !== $html, 'oturum geçerliyken buton HTML\'i üretilir' );
		qrms_assert_same( 2, substr_count( $html, 'qmo-cagri-ikon' ), 'iki ikon span\'i (garson🛎️ + hesap🧾)' );
		qrms_assert_same( 2, substr_count( $html, 'qmo-cagri-etiket' ), 'iki etiket span\'i' );
		// Emoji hâlâ SADECE ikon span'inin içinde — etiket span'i emoji taşımaz,
		// yani JS ileride etiketi güncellediğinde ikonun kendisine dokunmaz.
		qrms_assert_true(
			(bool) preg_match( '/<span class="qmo-cagri-ikon"[^>]*>\x{1F6CE}\x{FE0F}<\/span>\s*<span class="qmo-cagri-etiket">Garson Çağır<\/span>/u', $html ),
			'garson: ikon span\'i emoji taşır, HEMEN ARDINDAN ayrı etiket span\'i metni taşır'
		);
		qrms_assert_true(
			(bool) preg_match( '/<span class="qmo-cagri-ikon"[^>]*>\x{1F9FE}<\/span>\s*<span class="qmo-cagri-etiket">Hesap İste<\/span>/u', $html ),
			'hesap: aynı yapı'
		);
	}
);

qrms_test(
	'buttons.js: loading sırasında çift tıklama/rapid-click korumalı (disabled kontrolü + native disabled attribute)',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/buttons.js' );

		qrms_assert_contains( "if ( ! btn || btn.disabled ) {\n\t\t\treturn;\n\t\t}", $js, 'zaten kilitli butonda handler erken çıkar' );
		qrms_assert_contains( "durumUygula( btn, 'loading'", $js, 'tıklamada hemen loading uygulanır (disabled=true burada olur)' );
	}
);

qrms_test(
	'buttons.js: başarı state\'i doğrudan buton üzerinde görünür (is-success + metin), cooldown backend süresiyle senkron başlar',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/buttons.js' );

		qrms_assert_contains( "durumUygula( btn, 'success'", $js, 'başarıda buton state\'i güncellenir' );
		qrms_assert_contains( "btn.classList.add( 'is-success' )", $js, 'mevcut is-success sınıfı kullanılır' );
		qrms_assert_contains( "garsonCagrildiBtn", $js, 'garson için "✓ Garson Çağrıldı" metni' );
		qrms_assert_contains( "hesapIstendiBtn", $js, 'hesap için "✓ Hesap İstendi" metni' );
		qrms_assert_contains( 'function cooldownBaslat(', $js, 'cooldown zamanlayıcısı merkezi fonksiyon' );
		qrms_assert_contains( 'cooldownBaslat( btn, yanit.data && yanit.data.cooldown )', $js, 'backend cooldown metadata\'sı okunur' );
		qrms_assert_contains( 'COOLDOWN_YEDEK_SN = 60', $js, 'sunucu cooldown göndermezse aynı öntanımlı pencereye düşer' );
	}
);

qrms_test(
	'FIX #2: success metinleri kısaltıldı (mobil 320px\'te gerçek Chromium ölçümünde butonun kendi kutusunu taşıyordu)',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/buttons.js' );

		qrms_assert_contains( "metin( 'garsonCagrildiBtn', '✓ Çağrıldı' )", $js, 'garson success yedek metni kısaltıldı' );
		qrms_assert_contains( "metin( 'hesapIstendiBtn', '✓ İstendi' )", $js, 'hesap success yedek metni kısaltıldı' );
		qrms_assert_false( false !== strpos( $js, '✓ Garson Çağrıldı' ), 'eski uzun metin JS\'de kalmadı' );
		qrms_assert_false( false !== strpos( $js, '✓ Hesap İstendi' ), 'eski uzun metin JS\'de kalmadı' );
		// Butonun temel boyutu/görünümü DEĞİŞMEDİ — yalnızca metin kısaldı,
		// overflow:hidden/ellipsis gibi bir "safety net" eklenmedi (gerçek
		// ölçümde kısaltma tek başına yeterliydi).
		qrms_assert_false( false !== strpos( $js, 'text-overflow' ), 'ellipsis fallback\'ine gerek kalmadı' );
	}
);

qrms_test(
	'buttons.js: backend hatasında ve bağlantı hatasında buton idle\'a döner, kilitli kalmaz',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/buttons.js' );

		// success:false dalı.
		qrms_assert_true(
			(bool) preg_match( "/durumUygula\\( btn, 'idle' \\);\\s*\\n\\s*yaz\\( bar, mesaj, true \\);/", $js ),
			'success:false yanıtında idle\'a dönülür, sonra hata metni yazılır'
		);
		// ağ hatası (.catch) dalı.
		qrms_assert_true(
			(bool) preg_match( "/\\.catch\\( function \\(\\) \\{\\s*\\n\\s*durumUygula\\( btn, 'idle' \\);/", $js ),
			'.catch() içinde de idle\'a dönülür — UI kilitli kalmaz'
		);
	}
);

qrms_test(
	'buttons.js: erişilebilirlik — aria-label, klavye (native <button>), reduced motion metin/DOM tarafında güvenli (textContent, innerHTML yok)',
	function () {
		$js  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/buttons.js' );
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-buttons.php' );

		qrms_assert_contains( "btn.setAttribute( 'aria-label', durumMetni )", $js, 'her state için aria-label güncellenir' );
		qrms_assert_contains( "btn.removeAttribute( 'aria-label' )", $js, 'idle\'da aria-label temizlenir (orijinal metin span\'de yeterli)' );
		qrms_assert_false( false !== strpos( $js, '.innerHTML' ), 'DOM güncellemeleri innerHTML kullanmaz (XSS riski yok), textContent kullanılır' );
		qrms_assert_contains( 'span.textContent = durumMetni', $js, 'buton metni textContent ile basılır' );
		qrms_assert_false( false !== strpos( $js, 'console.' ), 'prodüksiyon kodunda console.* yok' );
		qrms_assert_contains( '<button type="button"', $php, 'butonlar native <button> — klavye (Enter/Space) tarayıcı tarafından desteklenir' );
		qrms_assert_contains( 'role="status" aria-live="polite"', $php, 'durum satırı ekran okuyucuya duyurulur' );
		$hfb = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/trait-frontend.php' );
		qrms_assert_contains( 'hfb-footer__call-btn--primary', $hfb, 'HFB garson primary sınıfı' );
		qrms_assert_contains( 'hfb-footer__call-btn--secondary', $hfb, 'HFB hesap secondary sınıfı' );
	}
);

qrms_test(
	'CSS: is-success state gerçekten görünür (disabled opacity\'si tarafından ezilmiyor), prefers-reduced-motion destekleniyor',
	function () {
		$footer_css   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/assets/css/frontend.css' );
		$shortcode_css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/css/buttons.css' );

		qrms_assert_contains( '.hfb-footer__call-btn.is-success', $footer_css, 'HFB footer is-success sınıfı durur' );
		qrms_assert_true(
			(bool) preg_match( '/\.hfb-footer__call-btn\.is-success \{[^}]*opacity: 1;/s', $footer_css ),
			'is-success artık :disabled\'ın opacity:0.5\'i tarafından soluklaştırılmıyor'
		);
		qrms_assert_contains( '@media (prefers-reduced-motion: reduce)', $footer_css, 'footer butonu reduced motion\'a uyar' );

		qrms_assert_contains( '.qmo-cagri-btn.is-success', $shortcode_css, 'kısa kod varyantında da is-success tanımlı (paralel state sistemi değil, aynı sınıf adı)' );
		qrms_assert_true(
			(bool) preg_match( '/\.qmo-cagri-btn\.is-success \{[^}]*opacity: 1;/s', $shortcode_css ),
			'kısa kod is-success de soluklaşmaz'
		);
		qrms_assert_contains( '@media (prefers-reduced-motion: reduce)', $shortcode_css, 'kısa kod butonu da reduced motion\'a uyar' );
		qrms_assert_false( false !== strpos( $shortcode_css, 'linear-gradient' ), 'yeni is-success eklenirken gradient/gösterişli efekt eklenmedi' );
	}
);

/* =========================================================================
   5) i18n köprüsü — yeni state metinleri de qmo_ceviri_chat/kataloğu üzerinden gider
========================================================================= */

qrms_test(
	'yeni buton state metinleri i18n kataloğuna ve JS localize köprüsüne eklendi (hardcoded değil)',
	function () {
		$katalog = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/ui-stringler.php' );
		$boot    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/chatbot.php' );

		foreach ( array( 'Çağrılıyor...', 'İsteniyor...', '✓ Çağrıldı', '✓ İstendi' ) as $metin ) {
			qrms_assert_contains( "'" . $metin . "'", $katalog, $metin . ' çeviri kataloğunda (chat modülü)' );
		}

		foreach ( array( 'garsonCagriliyor', 'garsonCagrildiBtn', 'hesapIsteniyor', 'hesapIstendiBtn' ) as $anahtar ) {
			qrms_assert_contains( "'{$anahtar}'", $boot, $anahtar . ' qmo_chat_js_metinleri() localize köprüsünde' );
			qrms_assert_contains( "qmo_ceviri_chat(", $boot, 'qmo_ceviri_chat köprüsü kullanılıyor' );
		}
	}
);
