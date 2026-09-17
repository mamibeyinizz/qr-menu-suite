<?php
/**
 * FOUC — Elementor "Global Widget" / "Şablon Ekle" referans çözümü testleri.
 *
 * b04d185'in code review'ında MEDIUM-3 olarak işaretlenen boşluğu kapatır:
 * kısa kod/widget etiketi sayfanın kendi `_elementor_data`'sında DEĞİL,
 * referans verilen ayrı bir Elementor Library şablonunda olabilir.
 * `rma_elementor_data_contains()` (ve modül eşdeğerleri
 * `QRMS_HFB_Elementor::elementor_data_contains()`,
 * `QRMGM_Frontend_Trait::elementor_data_contains()`,
 * `qmo_elementor_data_contains()`) bu referansı çözer.
 *
 * ÖNEMLİ — KAPSAM SINIRI: Bu testler GERÇEK bir Elementor Pro kurulumuna
 * karşı ÇALIŞTIRILMADI. Yalnızca çözücü fonksiyonların kendi mantığını
 * (JSON içinde referans arama, recursion, döngü koruması, istek-içi
 * önbellekleme) doğrular. Elementor'un global widget/"Şablon Ekle"
 * referanslarını GERÇEKTEN "template_id"/"templateID"/"templateId"
 * anahtarlarından biriyle sakladığı varsayımı, gerçek bir WordPress +
 * Elementor Pro ortamında ayrıca doğrulanmalıdır.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

// modules/restoran-menu/includes/trait-frontend.php ve
// modules/qr-galeri/includes/trait-frontend.php test-suite'in başka hiçbir
// dosyasında gerçek PHP olarak require edilmiyor (yalnızca
// file_get_contents() ile kaynak metni okunuyor) — bu yüzden burada
// require_once ediyoruz. Her iki dosya da yalnızca fonksiyon/trait TANIMLAR,
// üst düzeyde başka hiçbir şey çalıştırmaz; bağımsız yüklemeleri güvenlidir.
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-frontend.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-galeri/includes/trait-frontend.php';

// modules/header-footer-builder/includes/trait-elementor.php zaten
// tests/test-hfb.php içinde class-header-footer-builder.php üzerinden
// gerçek PHP olarak yüklendi (qrms_hfb() yardımcı fonksiyonu orada
// tanımlı). modules/_qmo-ortak/assets.php da zaten
// tests/test-analiz-izleme.php'de yüklendi (qmo_elementor_data_contains()
// orada tanımlı). Burada tekrar require_once etmiyoruz.

echo "\nFOUC — Elementor Global Widget / Şablon Ekle referans çözümü\n";

/**
 * qr-galeri'nin `private` elementor_data_contains()'ini test edebilmek için
 * ince bir sarmalayıcı. Trait'in kendisi başka hiçbir bağımlılık gerektirmez.
 */
class QRMS_Test_QRMGM_Elementor {
	use QRMGM_Frontend_Trait;

	public function test_contains( $tag, $data ) {
		return $this->elementor_data_contains( $tag, $data );
	}
}

/* ---------------------------------------------------------------------------
 * Senaryo A / H — Elementor (Pro) yokken üst seviye kontrol noktaları
 * çözücüyü hiç çağırmıyor; mevcut davranış (b04d185) korunuyor.
 * ------------------------------------------------------------------------ */

qrms_test(
	'Senaryo A/H: stub ortamında Elementor ve Elementor Pro tanımlı değil, kapılar kapalı',
	function () {
		qrms_assert_false( (bool) did_action( 'elementor/loaded' ), 'stub ortamında did_action() her zaman 0/false döner' );
		qrms_assert_false( class_exists( '\Elementor\Plugin' ), 'stub ortamında Elementor\\Plugin tanımlı değil' );
		qrms_assert_false( class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ), 'stub ortamında ElementorPro tanımlı değil' );

		// Her çağrı noktası hâlâ "did_action(...) || class_exists(...)" ile
		// korunuyor mu? (b04d185'teki mevcut mimari bozulmamalı — çözücü
		// yalnızca Elementor gerçekten yüklüyken devreye girmeli.)
		$korumali_dosyalar = array(
			'modules/restoran-menu/includes/trait-frontend.php',
			'modules/restoran-menu/includes/shortcode-vitrin.php',
			'modules/restoran-menu/includes/shortcode-slider.php',
			'modules/restoran-menu/includes/shortcode-banner-slider.php',
			'modules/qr-galeri/includes/trait-frontend.php',
			'modules/_qmo-ortak/assets.php',
		);

		foreach ( $korumali_dosyalar as $goreli ) {
			$kaynak = file_get_contents( QRMS_PLUGIN_DIR . $goreli );
			qrms_assert_contains( "did_action( 'elementor/loaded' )", $kaynak, $goreli . ': Elementor kapısı korunuyor' );
		}

		$hfb_kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/trait-frontend.php' );
		qrms_assert_contains( 'elementor_loaded()', $hfb_kaynak, 'HFB: elementor_loaded() kapısı korunuyor' );
	}
);

/* ---------------------------------------------------------------------------
 * rma_elementor_data_contains() — çözücünün kendi mantığı (restoran-menu)
 * ------------------------------------------------------------------------ */

qrms_test(
	'Senaryo B: global şablon referansı hiç yoksa false döner',
	function () {
		$data = wp_json_encode( array( array( 'elType' => 'widget', 'widgetType' => 'heading' ) ) );
		qrms_assert_false( rma_elementor_data_contains( 'rma_menu_widget', $data ), 'düz veri, referans yok' );
	}
);

qrms_test(
	'Senaryo C: global widget referansı var ama hedef şablon aranan etiketi içermiyor',
	function () {
		$GLOBALS['qrms_test']['post_meta'][501]['_elementor_data'] = wp_json_encode(
			array( array( 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Merhaba' ) ) )
		);

		$sayfa_verisi = wp_json_encode(
			array( array( 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => 501 ) )
		);

		qrms_assert_false( rma_elementor_data_contains( 'rma_menu_widget', $sayfa_verisi ), 'referans çözüldü ama etiket yok' );
	}
);

qrms_test(
	'Senaryo D: global widget referansı var ve hedef şablon aranan etiketi içeriyor → true',
	function () {
		$GLOBALS['qrms_test']['post_meta'][502]['_elementor_data'] = wp_json_encode(
			array( array( 'elType' => 'widget', 'widgetType' => 'rma_menu_widget' ) )
		);

		$sayfa_verisi = wp_json_encode(
			array( array( 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => 502 ) )
		);

		qrms_assert_true( rma_elementor_data_contains( 'rma_menu_widget', $sayfa_verisi ), 'global widget referansı çözülüp etiket bulundu' );
	}
);

qrms_test(
	'Senaryo E: aynı şablon ID birden fazla kez referans edilse de yalnızca bir kez okunur (memoization)',
	function () {
		$GLOBALS['qrms_test']['post_meta'][503]['_elementor_data'] = wp_json_encode(
			array( array( 'elType' => 'widget', 'widgetType' => 'rma_menu_widget' ) )
		);

		$sayfa_verisi = wp_json_encode(
			array( array( 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => 503 ) )
		);

		// İlk çağrı: şablonu gerçekten okur, sonucu (true) istek-boyu
		// önbelleğe alır.
		qrms_assert_true( rma_elementor_data_contains( 'rma_menu_widget', $sayfa_verisi ), 'ilk çözümleme' );

		// Şablonun post_meta'sını "silmiş" gibi yapıyoruz. Fonksiyon
		// get_post_meta()'yu TEKRAR çağırsaydı artık boş veri alır ve bu
		// dal için false dönerdi; önbellek çalışıyorsa ikinci çağrı hâlâ
		// true döner — bu, "aynı ID tekrar okunmuyor" gereksiniminin
		// gözlemlenebilir (black-box) kanıtıdır.
		unset( $GLOBALS['qrms_test']['post_meta'][503]['_elementor_data'] );

		qrms_assert_true(
			rma_elementor_data_contains( 'rma_menu_widget', $sayfa_verisi ),
			'ikinci çağrı önbellekten okumalı, get_post_meta() tekrar çağrılmamalı'
		);
	}
);

qrms_test(
	'Senaryo F: döngüsel şablon referansı (A→B→A) sonsuz döngüye girmeden false döner',
	function () {
		$GLOBALS['qrms_test']['post_meta'][601]['_elementor_data'] = wp_json_encode(
			array( array( 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => 602 ) )
		);
		$GLOBALS['qrms_test']['post_meta'][602]['_elementor_data'] = wp_json_encode(
			array( array( 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => 601 ) )
		);

		$sayfa_verisi = wp_json_encode(
			array( array( 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => 601 ) )
		);

		qrms_assert_false( rma_elementor_data_contains( 'rma_menu_widget', $sayfa_verisi ), 'döngü güvenle sonlanır, hiçbir yerde etiket yok' );
	}
);

qrms_test(
	'Senaryo G: geçersiz/silinmiş şablon ID fatal üretmez, false döner',
	function () {
		$sayfa_verisi = wp_json_encode(
			array( array( 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => 999999 ) )
		);

		qrms_assert_false( rma_elementor_data_contains( 'rma_menu_widget', $sayfa_verisi ), 'var olmayan şablon ID sessizce atlanır' );
	}
);

qrms_test(
	'Derinlik sınırı: 3 seviyeden uzun bir referans zinciri güvenle durur (ikinci sabit sınır)',
	function () {
		$GLOBALS['qrms_test']['post_meta'][611]['_elementor_data'] = wp_json_encode(
			array( array( 'templateID' => 612 ) )
		);
		$GLOBALS['qrms_test']['post_meta'][612]['_elementor_data'] = wp_json_encode(
			array( array( 'templateID' => 613 ) )
		);
		$GLOBALS['qrms_test']['post_meta'][613]['_elementor_data'] = wp_json_encode(
			array( array( 'templateID' => 614 ) )
		);
		// 614'ün kendisi etiketi GERÇEKTEN içeriyor, ama zincir 3 seviyelik
		// sınırı aştığı için bu seviyeye hiç inilmez — kasıtlı, belgelenmiş
		// bir sınırlamadır (tam kapsam yerine güvenlik/performans tercih
		// edilir).
		$GLOBALS['qrms_test']['post_meta'][614]['_elementor_data'] = wp_json_encode(
			array( array( 'elType' => 'widget', 'widgetType' => 'rma_menu_widget' ) )
		);

		$sayfa_verisi = wp_json_encode( array( array( 'templateID' => 611 ) ) );

		qrms_assert_false(
			rma_elementor_data_contains( 'rma_menu_widget', $sayfa_verisi ),
			'derinlik: sayfa(0)->611(1)->612(2)->613(3, sınır) — 614 hiç okunmaz'
		);
	}
);

/* ---------------------------------------------------------------------------
 * Parite testleri — aynı mantığın diğer üç modül kopyasında da çalıştığını
 * doğrular (qr-galeri, header-footer-builder, _qmo-ortak). Her modül kendi
 * bağımsız kopyasını taşır (restoran-menu'nün "tamamen kendi kendine
 * yeterli" mimarisi nedeniyle paylaşılan tek bir yardımcı kullanılamaz).
 * ------------------------------------------------------------------------ */

qrms_test(
	'Parite — qr-galeri: elementor_data_contains() global widget referansını çözer',
	function () {
		$GLOBALS['qrms_test']['post_meta'][701]['_elementor_data'] = wp_json_encode(
			array( array( 'elType' => 'widget', 'widgetType' => 'qrmenu_gallery' ) )
		);
		$sayfa_verisi = wp_json_encode( array( array( 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => 701 ) ) );

		$wrapper = new QRMS_Test_QRMGM_Elementor();
		qrms_assert_true( $wrapper->test_contains( 'qrmenu_gallery', $sayfa_verisi ), 'qr-galeri kopyası global widget\'ı çözer' );
	}
);

qrms_test(
	'Parite — qr-galeri: döngüsel referans sonsuz döngüye girmez',
	function () {
		$GLOBALS['qrms_test']['post_meta'][702]['_elementor_data'] = wp_json_encode( array( array( 'templateID' => 703 ) ) );
		$GLOBALS['qrms_test']['post_meta'][703]['_elementor_data'] = wp_json_encode( array( array( 'templateID' => 702 ) ) );
		$sayfa_verisi = wp_json_encode( array( array( 'templateID' => 702 ) ) );

		$wrapper = new QRMS_Test_QRMGM_Elementor();
		qrms_assert_false( $wrapper->test_contains( 'qrmenu_gallery', $sayfa_verisi ), 'qr-galeri kopyası döngüde güvenle durur' );
	}
);

qrms_test(
	'Parite — Header Footer Builder: elementor_data_contains() global widget referansını çözer',
	function () {
		$GLOBALS['qrms_test']['post_meta'][801]['_elementor_data'] = wp_json_encode(
			array( array( 'elType' => 'widget', 'widgetType' => 'shortcode', 'settings' => array( 'shortcode' => '[hfb_header]' ) ) )
		);
		$sayfa_verisi = wp_json_encode( array( array( 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => 801 ) ) );

		$hfb = qrms_hfb();
		qrms_assert_true( $hfb->elementor_data_contains( 'hfb_header', $sayfa_verisi ), 'HFB kopyası global widget\'ı çözer' );
	}
);

qrms_test(
	'Parite — Header Footer Builder: döngüsel referans sonsuz döngüye girmez',
	function () {
		$GLOBALS['qrms_test']['post_meta'][802]['_elementor_data'] = wp_json_encode( array( array( 'templateID' => 803 ) ) );
		$GLOBALS['qrms_test']['post_meta'][803]['_elementor_data'] = wp_json_encode( array( array( 'templateID' => 802 ) ) );
		$sayfa_verisi = wp_json_encode( array( array( 'templateID' => 802 ) ) );

		$hfb = qrms_hfb();
		qrms_assert_false( $hfb->elementor_data_contains( 'hfb_header', $sayfa_verisi ), 'HFB kopyası döngüde güvenle durur' );
	}
);

qrms_test(
	'Parite — ortak asset kısa kodları: qmo_elementor_data_contains() global widget referansını çözer',
	function () {
		$GLOBALS['qrms_test']['post_meta'][901]['_elementor_data'] = wp_json_encode(
			array( array( 'elType' => 'widget', 'widgetType' => 'shortcode', 'settings' => array( 'shortcode' => '[gemini_chatbot]' ) ) )
		);
		$sayfa_verisi = wp_json_encode( array( array( 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => 901 ) ) );

		qrms_assert_true( qmo_elementor_data_contains( 'gemini_chatbot', $sayfa_verisi ), 'ortak kopya global widget\'ı çözer' );
	}
);

qrms_test(
	'Parite — ortak asset kısa kodları: döngüsel referans sonsuz döngüye girmez',
	function () {
		$GLOBALS['qrms_test']['post_meta'][902]['_elementor_data'] = wp_json_encode( array( array( 'templateID' => 903 ) ) );
		$GLOBALS['qrms_test']['post_meta'][903]['_elementor_data'] = wp_json_encode( array( array( 'templateID' => 902 ) ) );
		$sayfa_verisi = wp_json_encode( array( array( 'templateID' => 902 ) ) );

		qrms_assert_false( qmo_elementor_data_contains( 'gemini_chatbot', $sayfa_verisi ), 'ortak kopya döngüde güvenle durur' );
	}
);

/* ---------------------------------------------------------------------------
 * Kaynak kod: çözücü doğru çağrı noktalarına bağlandı mı, b04d185'in
 * mimarisi (has_shortcode() -> _elementor_data -> Theme Builder sırası,
 * render-anı fallback'ler) korunuyor mu?
 * ------------------------------------------------------------------------ */

qrms_test(
	'Kaynak kod: çözücü doğru çağrı noktalarına bağlandı, render-anı fallback\'ler duruyor',
	function () {
		$rma = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-frontend.php' );
		qrms_assert_contains( "rma_elementor_data_contains( 'rma_menu_widget', \$elementor_data )", $rma, 'should_load_assets() çözücüyü kullanıyor' );
		qrms_assert_contains( "rma_elementor_data_contains( 'rma_menu_widget', \$data )", $rma, 'theme_builder_has_menu_widget() çözücüyü kullanıyor' );
		qrms_assert_contains( 'enqueue_frontend_assets()', $rma, 'restoran-menu render-anı fallback hâlâ duruyor' );

		$vitrin = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/shortcode-vitrin.php' );
		qrms_assert_contains( 'rma_elementor_data_contains( self::SHORTCODE, $data )', $vitrin, 'vitrin çözücüyü kullanıyor' );

		$slider = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/shortcode-slider.php' );
		qrms_assert_contains( "rma_elementor_data_contains( 'qmo_one_cikan_slider', \$data )", $slider, 'slider çözücüyü kullanıyor' );

		$banner = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/shortcode-banner-slider.php' );
		qrms_assert_contains( 'rma_elementor_data_contains( self::SHORTCODE, $data )', $banner, 'banner slider çözücüyü kullanıyor' );

		$hfb_elementor = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/trait-elementor.php' );
		qrms_assert_contains( 'function elementor_data_contains(', $hfb_elementor, 'HFB çözücüsü tanımlı' );
		qrms_assert_contains( '$this->elementor_data_contains( $tag, $data )', $hfb_elementor, 'theme_builder_documents_contain() çözücüyü kullanıyor' );

		$hfb_frontend = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/trait-frontend.php' );
		qrms_assert_contains( '$this->elementor_data_contains( $tag, $data )', $hfb_frontend, 'page_has_hfb_shortcode() çözücüyü kullanıyor' );
		qrms_assert_contains( 'enqueue_frontend_styles()', $hfb_frontend, 'HFB render-anı fallback hâlâ duruyor' );

		$galeri = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-galeri/includes/trait-frontend.php' );
		qrms_assert_contains( "\$this->elementor_data_contains( 'qrmenu_gallery', \$data )", $galeri, 'should_load_gallery_assets() çözücüyü kullanıyor' );
		qrms_assert_contains( 'ensure_frontend_assets()', $galeri, 'qr-galeri render-anı fallback hâlâ duruyor' );

		$ortak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/assets.php' );
		qrms_assert_contains( 'qmo_elementor_data_contains( $kisa_kod, $elementor_data )', $ortak, 'qmo_icerikten_yukle() çözücüyü kullanıyor' );
	}
);
