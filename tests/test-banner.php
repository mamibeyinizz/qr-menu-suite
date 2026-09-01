<?php
/**
 * Kampanya Banner slider testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

echo "\nKampanya Banner slider\n";

qrms_test(
	'banner modülü ürün vitrini slider\'ından bağımsızdır',
	function () {
		// İki slider ayrı dosyalarda, ayrı prefix'lerle durur: birinin
		// stili/betiği diğerinin seçicilerine dokunmaz.
		$dizin = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/';

		foreach ( array( 'admin-cpt-banner.php', 'shortcode-banner-slider.php', 'frontend-banner-slider.css', 'frontend-banner-slider.js' ) as $dosya ) {
			qrms_assert_true( file_exists( $dizin . $dosya ), $dosya . ' var' );
		}

		$css = file_get_contents( $dizin . 'frontend-banner-slider.css' );
		$js  = file_get_contents( $dizin . 'frontend-banner-slider.js' );

		qrms_assert_false( strpos( $css, '.qmo-slider-' ) !== false, 'banner css ürün slider seçicisine dokunmaz' );
		qrms_assert_false( strpos( $js, 'qmo-slider-' ) !== false, 'banner betiği ürün slider seçicisine dokunmaz' );

		// 16:9; slayt track'in iç genişliğinin tamamı (peek açıkken %88).
		qrms_assert_contains( 'aspect-ratio: 16 / 9', $css, 'banner oranı' );
		qrms_assert_contains( 'flex: 0 0 100%', $css, 'slayt track iç genişliğini kaplar' );

		// Autoplay + IntersectionObserver + hareket tercihi + swipe.
		qrms_assert_contains( 'IntersectionObserver', $js, 'viewport tetikli autoplay' );
		qrms_assert_contains( 'prefers-reduced-motion', $js, 'hareket tercihi' );
		qrms_assert_contains( 'touchend', $js, 'swipe' );

		// Bootstrap: yeni CPT ve kısa kod ana dosyadan başlatılır.
		$boot = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qmo-one-cikan-slider.php' );
		qrms_assert_contains( 'QMO_Banner_CPT::init()', $boot, 'CPT başlatılır' );
		qrms_assert_contains( 'QMO_Shortcode_Banner_Slider::init()', $boot, 'kısa kod başlatılır' );
	}
);

qrms_test(
	'banner kaydı nonce/yetki geçer, görselsizken sessizce basılmaz',
	function () {
		$dizin = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/';
		$cpt   = file_get_contents( $dizin . 'admin-cpt-banner.php' );
		$kod   = file_get_contents( $dizin . 'shortcode-banner-slider.php' );

		// Kaydetme güvenliği mevcut qmo_slide deseninin aynısı.
		qrms_assert_contains( 'wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD )', $cpt, 'nonce alanı' );
		qrms_assert_contains( 'wp_verify_nonce', $cpt, 'nonce doğrulaması' );
		qrms_assert_contains( 'current_user_can( \'edit_post\', $post_id )', $cpt, 'yetki kontrolü' );
		qrms_assert_contains( 'esc_url_raw', $cpt, 'bağlantı temizliği' );

		// Görseli olmayan banner atlanır; hiç kalmazsa kısa kod boş döner.
		qrms_assert_contains( 'if ( empty( $banners ) ) return \'\';', $kod, 'sessiz fallback' );

		// Boyut uyarıları (GÖREV 3).
		qrms_assert_contains( '1600x900px (16:9), JPG/WEBP, maksimum 300KB', $cpt, 'banner boyut notu' );

		$slide = file_get_contents( $dizin . 'admin-cpt-slide.php' );
		qrms_assert_contains( '1080x1080px (1:1 kare), JPG/WEBP, maksimum 200KB', $slide, 'ürün görseli boyut notu' );
	}
);

qrms_test(
	'sıra no kaydı save_post içinde sonsuz özyinelemeye girmez',
	function () {
		// REGRESYON: wp_update_post() `save_post_*` kancasını yeniden tetikler.
		// Kanca kaldırılmadan çağrılırsa save_meta -> wp_update_post -> save_meta
		// döngüsü bellek tükenmesiyle wp-admin/post.php üzerinde fatal error
		// verirdi (banner görseli kaydedilirken "ciddi bir sorun çıktı").
		$dizin = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/';

		$beklenen = array(
			'admin-cpt-banner.php' => "remove_action( 'save_post_' . self::POST_TYPE, [ __CLASS__, 'save_meta' ] );",
			'admin-cpt-slide.php'  => "remove_action( 'save_post_qmo_slide', [ __CLASS__, 'save_meta' ] );",
		);

		foreach ( $beklenen as $dosya => $kaldirma ) {
			$kod = file_get_contents( $dizin . $dosya );

			$kaldirma_yeri = strpos( $kod, $kaldirma );
			$guncelleme    = strpos( $kod, 'wp_update_post( [' );
			// strrpos: aynı add_action satırı init() içinde de geçer, aranan
			// olan save_meta'daki geri ekleme dosyadaki son örnektir.
			$geri_ekleme   = strrpos( $kod, str_replace( 'remove_action', 'add_action', $kaldirma ) );

			qrms_assert_true( false !== $kaldirma_yeri, $dosya . ': kanca kaldırılıyor' );
			qrms_assert_true( false !== $geri_ekleme, $dosya . ': kanca geri ekleniyor' );

			// Sıra: kaldır -> güncelle -> geri ekle.
			qrms_assert_true( $kaldirma_yeri < $guncelleme, $dosya . ': kaldırma wp_update_post öncesinde' );
			qrms_assert_true( $guncelleme < $geri_ekleme, $dosya . ': geri ekleme wp_update_post sonrasında' );
		}
	}
);

qrms_test(
	'banner yönetimi kendi sayfasında, Fiyat Kampanyaları ve Menü Görünümü temiz',
	function () {
		// İSİMLENDİRME: "Kampanya" = banner görselleri, "Fiyat Kampanyası" =
		// toplu zam/indirim. İkisi ayrı ekranlardır ve ortak kodu yoktur;
		// ön yüzdeki [qmo_banner_slider] kısa kodu bu taşımadan etkilenmez.
		$dizin    = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/';
		$sayfa    = file_get_contents( $dizin . 'trait-admin-pages.php' );
		$kampanya = file_get_contents( $dizin . 'trait-kampanya-admin.php' );
		$banner   = file_get_contents( $dizin . 'trait-kampanya-banner-admin.php' );

		// Fiyat Kampanyaları sayfasında banner'dan eser kalmadı.
		// (Dosyada yalnızca "buraya geri eklenmemeli" notu kalır; kod kalmadı.)
		qrms_assert_false( strpos( $kampanya, 'function render_banner_section' ) !== false, 'liste bölümü kampanya ekranından çıktı' );
		qrms_assert_false( strpos( $kampanya, '$this->render_banner_section();' ) !== false, 'kampanya listesi artık banner basmıyor' );
		qrms_assert_false( strpos( $kampanya, 'function render_banner_settings_page' ) !== false, 'ayar ekranı kampanya ekranından çıktı' );
		qrms_assert_false( strpos( $kampanya, 'function handle_banner_settings_save' ) !== false, 'kaydetme ucu kampanya ekranından çıktı' );
		qrms_assert_false( strpos( $kampanya, 'qmo_banner_slide' ) !== false, 'kampanya ekranı banner CPT\'sine bakmıyor' );
		qrms_assert_false( strpos( $kampanya, 'QMO_Banner_Slider_Settings' ) !== false, 'kampanya ekranı banner ayarına bakmıyor' );

		// Fiyat tarafının kendi içeriği bozulmadan duruyor.
		qrms_assert_contains( 'private function render_kampanya_list()', $kampanya, 'fiyat kampanyası listesi' );
		qrms_assert_contains( 'Kampanyalarım', $kampanya, 'geçmiş kampanya kartı' );
		qrms_assert_contains( '+ Yeni Kampanya', $kampanya, 'yeni kampanya butonu' );
		qrms_assert_contains( 'rma_kampanya_geri_al', $kampanya, 'geri alma ucu' );

		// Sihirbaz KENDİ sayfasında: get_subpages()'te bağımsız bir slug'ı var.
		qrms_assert_contains( "'qrms-rm-kampanya-banner' => [", $sayfa, 'sayfa kayıtlı' );
		qrms_assert_contains( "'render'     => 'render_kampanya_banner_page'", $sayfa, 'render metodu bağlı' );
		qrms_assert_contains( 'public function render_kampanya_banner_page()', $banner, 'sayfa render metodu tanımlı' );
		qrms_assert_contains( 'public function render_banner_wizard_section()', $banner, 'sihirbaz gövdesi' );

		// Hub'da kendi kartı var.
		qrms_assert_contains( "\$from_sub( \$this, 'qrms-rm-kampanya-banner' )", $sayfa, 'hub kartı' );

		// Menü Görünümü sayfasında banner'a dair HİÇBİR iz kalmadı.
		qrms_assert_false( strpos( $sayfa, 'render_banner_wizard_section' ) !== false, 'görünüm sayfası sihirbazı basmıyor' );
		qrms_assert_false( strpos( $sayfa, 'banner_anchor' ) !== false, 'görünüm sayfasında banner çapası yok' );

		// Sihirbaz adımları da yeni sayfaya bakıyor, Menü Görünümü'ne değil.
		qrms_assert_contains( "'qrms-rm-kampanya-banner',", $banner, 'adım adresleri kendi sayfasına' );
		qrms_assert_false( strpos( $banner, "'qrms-rm-gorunum'" ) !== false, 'sihirbaz görünüm sayfasına link vermiyor' );

		// Üç adım da tanımlı.
		foreach ( array( 'ozet', 'kampanyalar', 'olustur' ) as $adim ) {
			qrms_assert_contains( "'" . $adim . "'", $banner, $adim . ' adımı tanımlı' );
		}
		qrms_assert_contains( 'private function render_banner_adim_ozet()', $banner, '1. adım' );
		qrms_assert_contains( 'private function render_banner_adim_kampanyalar()', $banner, '2. adım' );
		qrms_assert_contains( 'private function render_banner_adim_olustur()', $banner, '3. adım' );

		// Liste olduğu gibi taşındı: kısa kod notu ve iki eylem butonu.
		qrms_assert_contains( '[qmo_banner_slider]', $banner, 'kısa kod açıklaması' );
		qrms_assert_contains( 'Yeni Kampanya Ekle', $banner, 'ekleme butonu' );
		qrms_assert_contains( 'Tüm Kampanyaları Yönet', $banner, 'yönetim butonu' );

		// Veri katmanı DEĞİŞMEDİ: CPT ve meta anahtarları sabit üzerinden.
		qrms_assert_contains( 'QMO_Banner_CPT::POST_TYPE', $banner, 'CPT slug\'ı sabitten' );
		qrms_assert_contains( 'QMO_Banner_CPT::META_IMAGE', $banner, 'görsel meta anahtarı sabitten' );
	}
);

qrms_test(
	'eski qrms-rm-banner-ayar adresi yeni konuma yönlendirir',
	function () {
		// Sayfa kaldırıldı ama slug silinmedi: kırık link/404 bırakılmaz.
		$sayfa = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-admin-pages.php' );

		// Artık gerçek bir sayfa DEĞİL: get_subpages() kaydı düştü ve aynı
		// işlev iki slug'ta tutulmuyor.
		qrms_assert_false( strpos( $sayfa, "'render'     => 'render_banner_settings_page'" ) !== false, 'sayfa kaydı kaldırıldı' );
		qrms_assert_false( strpos( $sayfa, "'qrms-rm-banner-ayar' => [" ) !== false, 'eski slug artık sayfa değil' );

		// Ama eski slug hâlâ kayıtlı ve YENİ BAĞIMSIZ sayfaya yönlendiriliyor.
		qrms_assert_contains( "'qrms-rm-banner-ayar'      => [ 'qrms-rm-kampanya-banner'", $sayfa, 'eski slug yeni sayfaya yönlenir' );
		qrms_assert_contains( "[ 'banner_adim' => 'kampanyalar' ]", $sayfa, 'hedef 2. adım' );

		// Yönlendirme, tablodaki query arg'larını da taşır.
		qrms_assert_contains( '$this->admin_page_url( $target[0], $target[2] ?? [], $target[1] )', $sayfa, 'arg\'lar hedefe taşınır' );
		qrms_assert_contains( 'wp_safe_redirect(', $sayfa, 'güvenli yönlendirme' );
	}
);

qrms_test(
	'toplu kampanya görseli: canvas -> AJAX -> medya kütüphanesi + banner kaydı',
	function () {
		$banner = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-kampanya-banner-admin.php' );
		$js     = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/banner-olustur.js' );
		$boot   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' );

		// Uç kayıtlı ve nonce + yetki kontrolü mevcut kod tabanı desenine uyuyor.
		qrms_assert_contains( 'wp_ajax_qmo_banner_gorsel_olustur', $boot, 'AJAX ucu kayıtlı' );
		qrms_assert_contains( 'check_ajax_referer( $this->banner_olustur_nonce_action', $banner, 'nonce doğrulaması' );
		qrms_assert_contains( 'QRMS_Admin::CAPABILITY', $banner, 'yetki kontrolü' );

		// Data URI dört kademede doğrulanır; hiçbiri atlanmaz.
		qrms_assert_contains( "'data:image/png;base64,'", $banner, 'önek kontrolü' );
		qrms_assert_contains( 'base64_decode(', $banner, 'base64 çözümü' );
		qrms_assert_contains( '"\x89PNG\r\n\x1a\n"', $banner, 'PNG imza kontrolü' );
		qrms_assert_contains( 'getimagesize(', $banner, 'dosyaya yazıldıktan sonra doğrulama' );
		qrms_assert_contains( 'banner_uretim_max_byte()', $banner, 'boyut sınırı' );

		// Üretilen görsel CPT'nin BEKLEDİĞİ yere bağlanır (featured image değil,
		// _qmo_banner_gorsel_id meta'sı) ki listede ve ön yüzde görünsün.
		qrms_assert_contains( 'wp_insert_attachment(', $banner, 'medya kaydı' );
		qrms_assert_contains( 'wp_generate_attachment_metadata(', $banner, 'ek meta üretimi' );
		qrms_assert_contains( "update_post_meta( \$kayit_id, QMO_Banner_CPT::META_IMAGE", $banner, 'görsel banner kaydına bağlanır' );
		qrms_assert_contains( "'post_status' => 'publish'", $banner, 'kayıt yayına alınır' );

		// Oran seçenekleri QMO_Banner_Slider_Settings ile aynı kaynaktan gelir.
		qrms_assert_contains( 'QMO_Banner_Slider_Settings::oranlar()', $banner, 'oran listesi tek kaynaktan' );

		// Şablonlar tek kaynakta; JS renkleri data-* üzerinden okur, sabit renk tutmaz.
		qrms_assert_contains( 'public static function banner_sablonlari()', $banner, 'şablon tanımı' );
		qrms_assert_contains( "getAttribute('data-bg-bas')", $js, 'JS rengi markup\'tan okur' );
		qrms_assert_contains( "toDataURL('image/png')", $js, 'canvas dışa aktarımı' );
	}
);

/* ---------------------------------------------------------------------------
 * Kampanya Banner — görünüm ayarları
 *
 * QMO_Banner_Slider_Settings, QMO_Slider_Settings ile aynı deseni izler ama
 * ayrı bir option'da (qmo_banner_slider_settings) ve kendi alan kümesiyle
 * durur: oran, geçiş biçimi, otomatik geçiş, oklar/noktalar ve başlık.
 * Option hiç yoksa eski görünüm korunur.
 * ------------------------------------------------------------------------ */

require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-banner-slider-settings.php';

qrms_test(
	'banner varsayılanları mevcut görünümü korur',
	function () {
		$v = QMO_Banner_Slider_Settings::varsayilanlar();

		qrms_assert_same( 1, $v['show_nav'], 'oklar açık' );
		qrms_assert_same( 1, $v['show_dots'], 'noktalar açık' );
		qrms_assert_same( 0, $v['show_title'], 'başlık kapalı — görsel tek başına basılırdı' );
		qrms_assert_same( 'slide', $v['gecis'], 'kaydırma geçişi' );
		qrms_assert_same( '16:9', $v['oran'], '16:9 oran' );
		qrms_assert_same( 4500, $v['autoplay'], 'kısa kodun eski varsayılanı' );
		qrms_assert_same( 'Playfair Display', $v['title_font'], 'Playfair' );
		qrms_assert_same( 32, $v['title_size'], 'masaüstü punto' );
		qrms_assert_same( 20, $v['title_size_mobile'], 'mobil punto' );
	}
);

qrms_test(
	'banner sanitize: oran, geçiş ve otomatik geçiş beyaz listeye çekilir',
	function () {
		$temiz = QMO_Banner_Slider_Settings::sanitize(
			array(
				'oran'     => '9:16',
				'gecis'    => 'zoom',
				'autoplay' => 999999,
			)
		);

		qrms_assert_same( '16:9', $temiz['oran'], 'bilinmeyen oran varsayılana düşer' );
		qrms_assert_same( 'slide', $temiz['gecis'], 'bilinmeyen geçiş varsayılana düşer' );
		qrms_assert_same( QMO_Banner_Slider_Settings::MAX_AUTOPLAY, $temiz['autoplay'], 'autoplay üst sınır' );

		$gecerli = QMO_Banner_Slider_Settings::sanitize(
			array(
				'oran'     => '21:9',
				'gecis'    => 'fade',
				'autoplay' => 6000,
			)
		);

		qrms_assert_same( '21:9', $gecerli['oran'], 'geçerli oran' );
		qrms_assert_same( 'fade', $gecerli['gecis'], 'geçerli geçiş' );
		qrms_assert_same( 6000, $gecerli['autoplay'], 'geçerli autoplay' );

		// 0 "kapalı" demektir: alt sınıra çekilmez.
		$kapali = QMO_Banner_Slider_Settings::sanitize( array( 'autoplay' => 0 ) );
		qrms_assert_same( 0, $kapali['autoplay'], 'otomatik geçiş kapatılabilir' );

		// 0'dan büyük ama çok küçük değer alt sınıra çekilir.
		$kucuk = QMO_Banner_Slider_Settings::sanitize( array( 'autoplay' => 200 ) );
		qrms_assert_same( QMO_Banner_Slider_Settings::MIN_AUTOPLAY, $kucuk['autoplay'], 'autoplay alt sınır' );
	}
);

qrms_test(
	'banner sanitize: checkbox, renk, font, punto ve hizalama temizlenir',
	function () {
		$kapali = QMO_Banner_Slider_Settings::sanitize( array() );
		qrms_assert_same( 0, $kapali['show_nav'], 'ok kapalı' );
		qrms_assert_same( 0, $kapali['show_dots'], 'nokta kapalı' );
		qrms_assert_same( 0, $kapali['show_title'], 'başlık kapalı' );

		$acik = QMO_Banner_Slider_Settings::sanitize(
			array(
				'show_nav'          => '1',
				'show_dots'         => 'on',
				'show_title'        => 1,
				'title_color'       => 'mavi',
				'title_font'        => 'Comic Sans',
				'title_size'        => 999,
				'title_size_mobile' => 1,
				'title_weight'      => 850,
				'title_align'       => 'justify',
			)
		);

		qrms_assert_same( 1, $acik['show_nav'], 'ok açık' );
		qrms_assert_same( 1, $acik['show_dots'], 'nokta açık' );
		qrms_assert_same( 1, $acik['show_title'], 'başlık açık' );
		qrms_assert_same( '#f5f0e8', $acik['title_color'], 'geçersiz renk varsayılana düşer' );
		qrms_assert_same( 'Playfair Display', $acik['title_font'], 'bilinmeyen font Playfair\'e düşer' );
		qrms_assert_same( QMO_Banner_Slider_Settings::MAX_TITLE_SIZE, $acik['title_size'], 'masaüstü üst sınır' );
		qrms_assert_same( QMO_Banner_Slider_Settings::MIN_TITLE_SIZE_MOBILE, $acik['title_size_mobile'], 'mobil alt sınır' );
		qrms_assert_same( 600, $acik['title_weight'], 'kalınlık varsayılana düşer' );
		qrms_assert_same( 'center', $acik['title_align'], 'hizalama varsayılana düşer' );
	}
);

qrms_test(
	'banner option yokken get() varsayılanları döner, css değişkenleri basılır',
	function () {
		$ayar = QMO_Banner_Slider_Settings::get();
		qrms_assert_same( 1, $ayar['show_nav'], 'kayıt yokken oklar açık' );
		qrms_assert_same( '16:9', $ayar['oran'], 'kayıt yokken 16:9' );

		$css = QMO_Banner_Slider_Settings::css_degiskenleri( $ayar );
		qrms_assert_contains( '--qmo-banner-oran:16 / 9', $css, 'oran değişkeni' );
		qrms_assert_contains( "--qmo-banner-title-font:'Playfair Display'", $css, 'font yığını' );
		qrms_assert_contains( '--qmo-banner-title-size:32px', $css, 'masaüstü punto' );
		qrms_assert_contains( '--qmo-banner-title-size-mobile:20px', $css, 'mobil punto' );

		$fade = QMO_Banner_Slider_Settings::css_degiskenleri(
			QMO_Banner_Slider_Settings::sanitize( array( 'oran' => '3:1' ) )
		);
		qrms_assert_contains( '--qmo-banner-oran:3 / 1', $fade, 'seçilen oran CSS\'e çevrilir' );
	}
);

qrms_test(
	'önerilen px canvas ve CSS oranıyla birebir eşleşir',
	function () {
		$onalti = QMO_Banner_Slider_Settings::onerilen_px( '16:9' );
		qrms_assert_same( 1600, $onalti[0], '16:9 genişlik' );
		qrms_assert_same( 900, $onalti[1], '16:9 yükseklik' );

		$uc = QMO_Banner_Slider_Settings::onerilen_px( '3:1' );
		qrms_assert_same( 1600, $uc[0], '3:1 genişlik' );
		qrms_assert_same( (int) round( 1600 / 3 ), $uc[1], '3:1 yükseklik' );

		$yirmi = QMO_Banner_Slider_Settings::onerilen_px( '21:9' );
		qrms_assert_same( 1600, $yirmi[0], '21:9 genişlik' );
		qrms_assert_same( (int) round( 1600 * 9 / 21 ), $yirmi[1], '21:9 yükseklik' );
	}
);

qrms_test(
	'banner kaydet() option\'a yazar, get() geri okur',
	function () {
		qrms_reset();

		QMO_Banner_Slider_Settings::kaydet(
			array(
				'show_nav'   => '0',
				'gecis'      => 'fade',
				'oran'       => '21:9',
				'show_title' => '1',
				'autoplay'   => '8000',
			)
		);

		$ayar = QMO_Banner_Slider_Settings::get();

		qrms_assert_same( 0, $ayar['show_nav'], 'ok kapatıldı' );
		qrms_assert_same( 'fade', $ayar['gecis'], 'solma geçişi' );
		qrms_assert_same( '21:9', $ayar['oran'], 'oran' );
		qrms_assert_same( 1, $ayar['show_title'], 'başlık açık' );
		qrms_assert_same( 8000, $ayar['autoplay'], 'autoplay' );

		qrms_reset();
	}
);

qrms_test(
	'banner ayarları kısa kod, css, js ve admin ekranına bağlanır',
	function () {
		$dizin = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/';

		$kod   = file_get_contents( $dizin . 'shortcode-banner-slider.php' );
		$css   = file_get_contents( $dizin . 'frontend-banner-slider.css' );
		$js    = file_get_contents( $dizin . 'frontend-banner-slider.js' );
		$admin = file_get_contents( $dizin . 'trait-kampanya-banner-admin.php' );
		$sayfa = file_get_contents( $dizin . 'trait-admin-pages.php' );
		$adminjs = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/admin-ui.js' );

		// Kısa kod ayarı okur ve ok/nokta/başlık/geçiş çıktısına yansıtır.
		qrms_assert_contains( 'QMO_Banner_Slider_Settings::get', $kod, 'kısa kod ayar okur' );
		qrms_assert_contains( '$show_nav', $kod, 'ok bloğu ayara bağlı' );
		qrms_assert_contains( 'data-qmo-banner-prev', $kod, 'önceki oku' );
		qrms_assert_contains( 'data-qmo-banner-next', $kod, 'sonraki oku' );
		qrms_assert_contains( 'qmo-banner-title', $kod, 'başlık öğesi' );
		qrms_assert_contains( 'data-gecis', $kod, 'geçiş biçimi betiğe taşınır' );

		// CSS: oran değişkeni, solma geçişi, ok ve başlık stilleri.
		foreach ( array( '--qmo-banner-oran', '--qmo-banner-title-font', '--qmo-banner-title-color', '--qmo-banner-title-size', '--qmo-banner-title-size-mobile', '--qmo-banner-title-weight', '--qmo-banner-title-align' ) as $degisken ) {
			qrms_assert_contains( $degisken, $css, $degisken . ' frontend' );
			qrms_assert_contains( $degisken, $adminjs, $degisken . ' önizleme' );
		}

		qrms_assert_contains( '.qmo-banner-root.is-fade .qmo-banner-slide', $css, 'solma geçişi' );
		qrms_assert_contains( '.qmo-banner-nav-btn', $css, 'ok stili' );

		// Betik: oklar ve solma mantığı.
		qrms_assert_contains( "data-qmo-banner-prev", $js, 'ok butonu bağlanır' );
		qrms_assert_contains( "getAttribute('data-gecis')", $js, 'geçiş biçimi okunur' );
		qrms_assert_contains( "classList.toggle('is-active'", $js, 'aktif slayt sınıfı' );

		// İki slider hâlâ birbirinden bağımsız.
		qrms_assert_false( strpos( $css, '.qmo-slider-' ) !== false, 'banner css ürün slider seçicisine dokunmaz' );
		qrms_assert_false( strpos( $js, 'qmo-slider-' ) !== false, 'banner betiği ürün slider seçicisine dokunmaz' );

		// Admin: kendi sayfası, kaydetme ucu ve nonce.
		// (Ayar formu sihirbazın 2. adımı; alanların hiçbiri düşmedi.)
		qrms_assert_contains( "'qrms-rm-kampanya-banner' => [", $sayfa, 'sayfa kayıtlı' );
		qrms_assert_contains( 'render_kampanya_banner_page', $sayfa, 'render metodu bağlı' );
		qrms_assert_contains( 'private function render_banner_ayar_formu()', $admin, 'ayar formu tanımlı' );
		qrms_assert_contains( 'public function handle_banner_settings_save()', $admin, 'kaydetme ucu' );
		qrms_assert_contains( 'check_admin_referer( $this->banner_nonce_action )', $admin, 'nonce' );
		qrms_assert_contains( 'initBannerPreview', $adminjs, 'canlı önizleme' );

		// Görünüm formundaki HİÇBİR alan taşımada düşmedi.
		foreach ( array( '[oran]', '[gecis]', '[show_nav]', '[show_dots]', '[autoplay]', '[show_title]', '[title_font]', '[title_color]', '[title_size]', '[title_size_mobile]', '[title_weight]', '[title_align]' ) as $alan ) {
			qrms_assert_contains( 'qmo_banner_slider_settings' . $alan, $admin, $alan . ' alanı korundu' );
		}
	}
);

qrms_test(
	'banner peek: komşu slaytların kenarı görünür, tek banner\'da kapalı',
	function () {
		$dizin = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/';

		$css = file_get_contents( $dizin . 'frontend-banner-slider.css' );
		$js  = file_get_contents( $dizin . 'frontend-banner-slider.js' );
		$kod = file_get_contents( $dizin . 'shortcode-banner-slider.php' );

		// Peek, track'e verilen yatay padding'le kurulur; slaytın
		// `flex: 0 0 100%` yüzdesi kendiliğinden daralır. Bunun çalışması
		// track'in border-box olmasına bağlıdır.
		qrms_assert_contains( '--qmo-banner-peek', $css, 'peek değişkeni' );
		qrms_assert_contains( 'padding-inline: var(--qmo-banner-peek)', $css, 'track yatay padding' );
		qrms_assert_contains( 'box-sizing: border-box', $css, 'track kutu modeli' );
		qrms_assert_contains( 'gap: var(--qmo-banner-gap)', $css, 'slaytlar arası boşluk' );
		qrms_assert_contains( 'border-radius: var(--qmo-banner-radius)', $css, 'yuvarlak köşe' );
		qrms_assert_contains( 'min-width: 0', $css, 'slayt içerik minine kilitlenmez' );
		qrms_assert_contains( 'flex: 0 0 auto', $css, 'peek slayt genişliği width:100% ile' );
		qrms_assert_false( strpos( $css, 'min-width: 100%' ) !== false, 'min-width:100% peek\'i yutardı' );

		// Peek yalnızca birden fazla banner varken açılır: tek banner'da
		// yanlarda gösterilecek komşu yok.
		qrms_assert_contains( "\$kok_sinif .= ' is-peek';", $kod, 'is-peek sınıfı' );
		qrms_assert_contains( 'if ( $count > 1 ) {', $kod, 'yalnızca 2+ banner' );
		qrms_assert_contains( 'filemtime( $css )', $kod, 'css sürümü dosya zamanı' );
		qrms_assert_contains( 'filemtime( $js )', $kod, 'js sürümü dosya zamanı' );

		// Solma modunda peek kapalı: slaytlar üst üste, komşu kenarı yok.
		foreach ( array( 'track', 'slide' ) as $parca ) {
			qrms_assert_contains(
				'.qmo-banner-root.is-peek:not(.is-fade) .qmo-banner-' . $parca,
				$css,
				$parca . ' peek kuralı fade dışında'
			);
		}

		// Transform artık yüzde değil piksel: slayt genişliği + gap
		// runtime'da ölçülür (gap cqi tabanlı clamp, sabit yüzdeyle
		// ifade edilemez), pencere boyutu değişince yeniden hesaplanır.
		qrms_assert_contains( 'function slideStep()', $js, 'adım ölçümü' );
		qrms_assert_contains( 'getBoundingClientRect().left', $js, 'gerçek konum okunur' );
		qrms_assert_contains( 'offsetLeft', $js, 'layout yokken yedek ölçüm' );
		qrms_assert_contains( 'requestAnimationFrame', $js, 'stil uygulandıktan sonra yeniden ölçülür' );
		qrms_assert_contains( "translateX(' + (-slideStep() * trackIndex) + 'px)", $js, 'px cinsinden transform' );
		qrms_assert_contains( "addEventListener('resize'", $js, 'yeniden boyutlandırma' );
		qrms_assert_false( strpos( $js, "(-100 * current) + '%'" ) !== false, 'eski yüzde hesabı kaldırıldı' );
	}
);

qrms_test(
	'banner görselleri tüm slaytlarda object-fit ile kırpılır',
	function () {
		$dizin = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/';
		$css   = file_get_contents( $dizin . 'frontend-banner-slider.css' );
		$kod   = file_get_contents( $dizin . 'shortcode-banner-slider.php' );
		$cpt   = file_get_contents( $dizin . 'admin-cpt-banner.php' );
		$ayar  = file_get_contents( $dizin . 'class-banner-slider-settings.php' );

		// Tüm slayt görselleri: :first-child yok, object-fit her .qmo-banner-img'e.
		qrms_assert_contains( '.qmo-banner-img', $css, 'görsel seçici' );
		qrms_assert_contains( 'object-fit: cover', $css, 'object-fit cover' );
		qrms_assert_contains( 'object-position: center', $css, 'object-position center' );
		qrms_assert_false( strpos( $css, '.qmo-banner-slide:first-child' ) !== false, 'ilk slayta özel kırpma yok' );
		qrms_assert_contains( 'position: absolute', $css, 'görsel akıştan çıkar' );
		qrms_assert_contains( 'min-height: 0', $css, 'flex min-height kilitlenmez' );

		// width/height ipucu kayıtlı orana göre, döngüde sızmaz.
		qrms_assert_contains( 'QMO_Banner_Slider_Settings::onerilen_px', $kod, 'oranla eşleşen px' );
		qrms_assert_contains( 'function onerilen_px', $ayar, 'önerilen px tek kaynak' );

		// Boyut uyarısı oran-duyarlı; 16:9 varsayılanı dosyada durur.
		qrms_assert_contains( '1600x900px (16:9), JPG/WEBP, maksimum 300KB', $cpt, 'banner boyut notu' );
		qrms_assert_contains( 'function boyut_notu()', $cpt, 'dinamik boyut notu' );
	}
);

qrms_test(
	'kaydırma modunda sonsuz karusel klon tekniği, solma etkilenmez',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/frontend-banner-slider.js' );

		qrms_assert_contains( 'data-qmo-banner-clone', $js, 'klon işareti' );
		qrms_assert_contains( 'cloneSlide', $js, 'klon üretici' );
		qrms_assert_contains( 'insertBefore', $js, 'son slayt başa' );
		qrms_assert_contains( 'appendChild', $js, 'ilk slayt sona' );
		qrms_assert_contains( 'snapIfNeeded', $js, 'sınırda anlık sıçrama' );
		qrms_assert_contains( "var looping = !fade && !reducedMotion && realCount > 1", $js, 'solma ve reduced-motion klonlamaz' );
		qrms_assert_contains( 'transitionend', $js, 'geçiş bitince sıçra' );
	}
);

qrms_test(
	'admin kampanya listesi tüm kayıtları çeker ve sıra AJAX ile değişir',
	function () {
		$dizin  = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/';
		$cpt    = file_get_contents( $dizin . 'admin-cpt-banner.php' );
		$banner = file_get_contents( $dizin . 'trait-kampanya-banner-admin.php' );
		$js     = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/admin-ui.js' );
		$boot   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' );
		$kisa   = file_get_contents( $dizin . 'shortcode-banner-slider.php' );

		qrms_assert_contains( 'function get_admin_banners', $cpt, 'yönetim sorgusu' );
		qrms_assert_contains( "'posts_per_page'         => -1", $cpt, 'limit yok' );
		qrms_assert_contains( "'nopaging'               => true", $cpt, 'sayfalama kapalı' );
		qrms_assert_contains( "'draft'", $cpt, 'taslaklar da listelenir' );
		qrms_assert_contains( 'QMO_Banner_CPT::get_admin_banners()', $banner, 'liste admin sorgusunu kullanır' );

		qrms_assert_contains( 'data-yon="up"', $banner, 'yukarı ok' );
		qrms_assert_contains( 'data-yon="down"', $banner, 'aşağı ok' );
		qrms_assert_contains( 'initBannerOrder', $js, 'ok tıklaması bağlanır' );
		qrms_assert_contains( 'qmo_banner_sira_kaydet', $js, 'AJAX eylemi JS' );
		qrms_assert_contains( 'wp_ajax_qmo_banner_sira_kaydet', $boot, 'AJAX ucu kayıtlı' );
		qrms_assert_contains( 'check_ajax_referer( \'rma_admin_nonce\', \'security\' )', $cpt, 'nonce' );
		qrms_assert_contains( 'QRMS_Admin::CAPABILITY', $cpt, 'yetki' );

		// Ön yüz ve admin aynı sıra alanını kullanır.
		qrms_assert_contains( 'QMO_Banner_CPT::get_published_banners()', $kisa, 'ön yüz yayınlanmış + menu_order' );
		qrms_assert_contains( "'menu_order' => 'ASC'", $cpt, 'ortak sıra alanı' );
	}
);

qrms_test(
	'banner kaydetme ucu ve önbellek kancası kayıtlı',
	function () {
		$boot = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' );
		qrms_assert_contains( 'admin_post_qmo_banner_ayar_kaydet', $boot, 'kaydetme ucu' );
		qrms_assert_contains( 'update_option_qmo_banner_slider_settings', $boot, 'önbellek kancası' );

		$slider_boot = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qmo-one-cikan-slider.php' );
		qrms_assert_contains( 'class-banner-slider-settings.php', $slider_boot, 'ayar sınıfı yüklenir' );
	}
);


/* ---------------------------------------------------------------------------
 * Kampanya Banner — SUNUCU TARAFI KIRPMA
 *
 * Kırpma tutarsızlığının kökü, görsellerin yalnızca CSS object-fit ile
 * "kesilmesiydi": dosyalar farklı oranlarda kaldığı için her slayttan farklı
 * bir bölge kayboluyordu. Artık dosyanın kendisi hedef orana getiriliyor
 * (wp_get_image_editor), CSS yalnızca güvenlik ağı.
 * ------------------------------------------------------------------------ */

require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-banner-kirpma.php';

qrms_test(
	'kırpma kutusu: kare, dikey ve geniş görseller aynı orana iner',
	function () {
		$hedef = QMO_Banner_Kirpma::oran_orani( '16:9' );

		// KARE (1000x1000) — dikeyde kesilir, yatay tam kalır.
		$kare = QMO_Banner_Kirpma::kirpma_kutusu( 1000, 1000, $hedef );
		qrms_assert_same( 0, $kare['x'], 'kare: yatayda kesilmez' );
		qrms_assert_same( 219, $kare['y'], 'kare: üstten ve alttan eşit pay' );
		qrms_assert_same( 1000, $kare['en'], 'kare: tam genişlik' );
		qrms_assert_same( 563, $kare['boy'], 'kare: 16:9 yüksekliği' );

		// DİKEY (800x1200) — yine dikeyde kesilir, kayıp daha büyüktür.
		$dikey = QMO_Banner_Kirpma::kirpma_kutusu( 800, 1200, $hedef );
		qrms_assert_same( 375, $dikey['y'], 'dikey: merkezden' );
		qrms_assert_same( 800, $dikey['en'], 'dikey: tam genişlik' );
		qrms_assert_same( 450, $dikey['boy'], 'dikey: 16:9 yüksekliği' );

		// ÇOK GENİŞ (3000x1000) — bu kez yatayda kesilir.
		$genis = QMO_Banner_Kirpma::kirpma_kutusu( 3000, 1000, $hedef );
		qrms_assert_same( 611, $genis['x'], 'geniş: soldan ve sağdan eşit pay' );
		qrms_assert_same( 0, $genis['y'], 'geniş: dikeyde kesilmez' );
		qrms_assert_same( 1778, $genis['en'], 'geniş: 16:9 genişliği' );
		qrms_assert_same( 1000, $genis['boy'], 'geniş: tam yükseklik' );

		// ÜÇÜNÜN DE ÇIKTISI AYNI ORANDA: tutarsızlığın kalıcı çözümü bu.
		foreach ( array( $kare, $dikey, $genis ) as $kutu ) {
			qrms_assert_true(
				abs( ( $kutu['cikti_en'] / $kutu['cikti_boy'] ) / $hedef - 1 ) <= QMO_Banner_Kirpma::TOLERANS,
				'çıktı 16:9 toleransında'
			);
		}

		// Çıktı kaynaktan büyütülmez (upscale bulanıklık üretir).
		$kucuk = QMO_Banner_Kirpma::kirpma_kutusu( 400, 400, $hedef );
		qrms_assert_same( 400, $kucuk['cikti_en'], 'küçük görsel büyütülmez' );
		qrms_assert_same( 225, $kucuk['cikti_boy'], 'küçük görselin 16:9 yüksekliği' );

		// Uzun kenar önerilen 1600px'i aşmaz.
		qrms_assert_same( 1600, $genis['cikti_en'], 'çıktı 1600px ile sınırlı' );
		qrms_assert_same( 900, $genis['cikti_boy'], '1600x900' );
	}
);

qrms_test(
	'kırpma odağı kesilen kenarı kaydırır, beyaz liste dışına çıkmaz',
	function () {
		$hedef = QMO_Banner_Kirpma::oran_orani( '16:9' );
		$ucluk = QMO_Banner_Kirpma::oran_orani( '3:1' );

		// Yatay kesimde sol/sağ, dikey kesimde üst/alt anlamlıdır.
		qrms_assert_same( 0, QMO_Banner_Kirpma::kirpma_kutusu( 3000, 1000, $hedef, 'sol' )['x'], 'sol kenar' );
		qrms_assert_same( 1222, QMO_Banner_Kirpma::kirpma_kutusu( 3000, 1000, $hedef, 'sag' )['x'], 'sağ kenar' );
		qrms_assert_same( 0, QMO_Banner_Kirpma::kirpma_kutusu( 1000, 1000, $ucluk, 'ust' )['y'], 'üst kenar' );
		qrms_assert_same( 667, QMO_Banner_Kirpma::kirpma_kutusu( 1000, 1000, $ucluk, 'alt' )['y'], 'alt kenar' );

		// Bilinmeyen odak merkeze düşer; kutu merkezî kırpmanın aynısı olur.
		qrms_assert_same( 'merkez', QMO_Banner_Kirpma::odak( 'çapraz' ), 'bilinmeyen odak merkez' );
		qrms_assert_same( 'sag', QMO_Banner_Kirpma::odak( ' SAG ' ), 'boşluk ve büyük harf temizlenir' );
		qrms_assert_same(
			QMO_Banner_Kirpma::kirpma_kutusu( 3000, 1000, $hedef, 'merkez' )['x'],
			QMO_Banner_Kirpma::kirpma_kutusu( 3000, 1000, $hedef, 'çapraz' )['x'],
			'geçersiz odak merkezî kırpma verir'
		);

		// object-position karşılıkları: yönetim önizlemesi ve henüz
		// kırpılmamış eski görseller bunu kullanır.
		qrms_assert_same( 'center center', QMO_Banner_Kirpma::odak_css( 'merkez' ), 'merkez css' );
		qrms_assert_same( 'center bottom', QMO_Banner_Kirpma::odak_css( 'alt' ), 'alt css' );
		qrms_assert_same( 'left center', QMO_Banner_Kirpma::odak_css( 'sol' ), 'sol css' );
	}
);

qrms_test(
	'zaten doğru orandaki görsel yeniden yazılmaz, boyut adı orana özeldir',
	function () {
		$hedef = QMO_Banner_Kirpma::oran_orani( '16:9' );

		qrms_assert_true( QMO_Banner_Kirpma::oran_uyuyor( 1600, 900, $hedef ), 'tam 16:9' );
		qrms_assert_true( QMO_Banner_Kirpma::oran_uyuyor( 1600, 901, $hedef ), '1px sapma toleransta — CSS yutar' );
		qrms_assert_false( QMO_Banner_Kirpma::oran_uyuyor( 1000, 1000, $hedef ), 'kare uymaz' );
		qrms_assert_false( QMO_Banner_Kirpma::oran_uyuyor( 800, 1200, $hedef ), 'dikey uymaz' );
		qrms_assert_false( QMO_Banner_Kirpma::oran_uyuyor( 0, 900, $hedef ), 'ölçüsüz görsel uymaz' );

		// Kırpılmış sürüm ORAN BAŞINA ayrı bir ek boyutta durur: 16:9'dan
		// 3:1'e geçilince eskisi silinmez, sadece kullanılmaz.
		qrms_assert_same( 'qmo-banner-16x9', QMO_Banner_Kirpma::boyut_adi( '16:9' ), '16:9 boyut adı' );
		qrms_assert_same( 'qmo-banner-3x1', QMO_Banner_Kirpma::boyut_adi( '3:1' ), '3:1 boyut adı' );
		qrms_assert_same( 'qmo-banner-16x9', QMO_Banner_Kirpma::boyut_adi( '9:16' ), 'bilinmeyen oran varsayılana düşer' );

		// oranlar() listesindeki her oran için ayrı bir ad üretilir.
		$adlar = array();
		foreach ( array_keys( QMO_Banner_Slider_Settings::oranlar() ) as $oran ) {
			$adlar[] = QMO_Banner_Kirpma::boyut_adi( $oran );
		}
		qrms_assert_same( count( $adlar ), count( array_unique( $adlar ) ), 'her oranın adı benzersiz' );
	}
);

qrms_test(
	'kırpma kaydetme akışına, ön yüze ve yönetim önizlemesine bağlı',
	function () {
		$dizin  = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/';
		$kirpma = file_get_contents( $dizin . 'class-banner-kirpma.php' );
		$cpt    = file_get_contents( $dizin . 'admin-cpt-banner.php' );
		$kod    = file_get_contents( $dizin . 'shortcode-banner-slider.php' );
		$banner = file_get_contents( $dizin . 'trait-kampanya-banner-admin.php' );
		$boot   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qmo-one-cikan-slider.php' );

		// GERÇEK kırpma WordPress'in görüntü düzenleyicisiyle yapılır;
		// doğrudan GD/Imagick çağrısı yok.
		qrms_assert_contains( 'wp_get_image_editor(', $kirpma, 'WP görüntü düzenleyici' );
		qrms_assert_contains( '$editor->crop(', $kirpma, 'sunucu tarafında kırpma' );
		qrms_assert_contains( '$editor->save(', $kirpma, 'kırpılmış dosya yazılır' );
		qrms_assert_false( strpos( $kirpma, 'imagecreatefrom' ) !== false, 'doğrudan GD çağrısı yok' );
		qrms_assert_false( strpos( $kirpma, 'new Imagick' ) !== false, 'doğrudan Imagick çağrısı yok' );

		// Orijinal korunur: sonuç ek boyut olarak metadata'ya yazılır.
		qrms_assert_contains( "\$meta['sizes'][ \$ad ]", $kirpma, 'ek boyut kaydı' );
		qrms_assert_contains( 'wp_update_attachment_metadata(', $kirpma, 'metadata güncellenir' );

		// Kaydetme akışı: görsel seçilince kırpma çalışır.
		qrms_assert_contains( 'QMO_Banner_Kirpma::banner_kirp( $post_id )', $cpt, 'kayıtta kırpılır' );
		qrms_assert_contains( 'QMO_Banner_Kirpma::META_ODAK', $cpt, 'odak alanı kaydedilir' );

		// Ön yüz kırpılmış sürümü basar; kırpılmışta srcset basılmaz
		// (adaylar farklı oranda olurdu).
		qrms_assert_contains( 'QMO_Banner_Kirpma::gorsel(', $kod, 'ön yüz kırpılmışı okur' );
		qrms_assert_contains( "\$srcset = \$kirpildi ? '' :", $kod, 'kırpılmışta srcset yok' );

		// Yönetim önizlemesi ön yüzle AYNI dosyayı gösterir.
		qrms_assert_contains( 'QMO_Banner_Kirpma::gorsel(', $banner, 'önizleme kırpılmışı okur' );
		qrms_assert_contains( 'QMO_Banner_Kirpma::gorsel(', $cpt, 'meta kutusu kırpılmışı okur' );

		// Sınıf bootstrap'a bağlı.
		qrms_assert_contains( 'class-banner-kirpma.php', $boot, 'kırpma sınıfı yüklenir' );

		// CSS güvenlik ağı olarak DURUR ama artık tek başına iş görmez.
		$css = file_get_contents( $dizin . 'frontend-banner-slider.css' );
		qrms_assert_contains( 'object-fit: cover', $css, 'güvenlik ağı yerinde' );
		qrms_assert_contains( 'GÜVENLİK AĞIDIR', $css, 'CSS\'in rolü belgelenmiş' );
	}
);

qrms_test(
	'eski görseller için yeniden kırpma: toplu düğme, satır uyarısı ve oran değişimi',
	function () {
		$dizin  = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/';
		$banner = file_get_contents( $dizin . 'trait-kampanya-banner-admin.php' );
		$cpt    = file_get_contents( $dizin . 'admin-cpt-banner.php' );
		$boot   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' );

		// admin_post ucu kayıtlı, nonce ve yetki kontrollü.
		qrms_assert_contains( 'admin_post_qmo_banner_kirp', $boot, 'yeniden kırpma ucu kayıtlı' );
		qrms_assert_contains( 'public function handle_banner_kirp()', $banner, 'işleyici tanımlı' );
		qrms_assert_contains( 'check_admin_referer( $this->banner_kirp_nonce_action )', $banner, 'nonce' );
		qrms_assert_contains( 'QRMS_Admin::CAPABILITY', $banner, 'yetki' );

		// İki kapsam: tek kayıt ve tümü.
		qrms_assert_contains( 'QMO_Banner_Kirpma::toplu_kirp()', $banner, 'toplu kırpma' );
		qrms_assert_contains( 'QMO_Banner_Kirpma::banner_kirp( $banner_id )', $banner, 'tek kayıt kırpma' );
		qrms_assert_contains( 'Tüm görselleri yeniden kırp', $banner, 'toplu düğme' );
		qrms_assert_contains( 'Yeniden kırp', $banner, 'satır düğmesi' );

		// Satır eylemi bir <span> içinde durduğu için <form> değil nonce'lu
		// bağlantıdır (WordPress'in kendi satır eylemi deseni).
		qrms_assert_contains( 'wp_nonce_url(', $banner, 'bağlantı nonce\'lu' );
		qrms_assert_false( strpos( $banner, 'banner_kirp_formu' ) !== false, 'span içinde form yok' );

		// Satır başına durum rozeti: kullanıcı hangi görselin eski
		// olduğunu görmeden bırakılmaz.
		qrms_assert_contains( 'QMO_Banner_Kirpma::durum(', $banner, 'liste durumu okur' );
		qrms_assert_contains( 'rma-kb-kirpma-rozet', $banner, 'durum rozeti' );
		qrms_assert_contains( 'QMO_Banner_Kirpma::bekleyen_sayisi(', $banner, 'bekleyen sayısı' );

		// WordPress\'in kendi liste ekranı da uyarır.
		qrms_assert_contains( 'Güncel orana kırpılmadı', $cpt, 'CPT liste sütunu uyarısı' );

		// Oran sonradan değişirse kullanıcı bilgilendirilir.
		qrms_assert_contains( "'oran_degisti'", $banner, 'oran değişimi bildirimi' );
		qrms_assert_contains( 'yeniden kırpılması gerekiyor', $banner, 'bildirim metni' );

		// Durum rozetlerinin stili admin CSS\'inde tanımlı.
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/admin-ui.css' );
		qrms_assert_contains( '.rma-kb-kirpma-rozet', $css, 'rozet stili' );
		qrms_assert_contains( '.rma-kb-kirpma-uyari', $css, 'uyarı kutusu stili' );
	}
);


/* ---------------------------------------------------------------------------
 * 24. HFB — "Yeni Blok Ekle" listesi, canlı önizleme yükü, önbellek temizliği
 * ------------------------------------------------------------------------ */

