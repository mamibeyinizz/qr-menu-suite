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
		$js       = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/admin-ui.js' );
		$css      = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/admin-ui.css' );

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
		qrms_assert_contains( 'qmo-banner-yeni-panel', $banner, 'satır içi yeni kampanya paneli' );
		qrms_assert_contains( 'ajax_banner_kampanya_olustur', $banner, 'inline oluşturma ucu' );
		qrms_assert_contains( '+ Yeni kampanya görseli', $banner, 'ekleme butonu' );
		qrms_assert_contains( 'Kampanyalara dön', $banner, 'geri dön metni' );
		qrms_assert_false( strpos( $banner, 'Yeni Kampanya Ekle' ) !== false, 'post-new.php birincil CTA değil' );
		qrms_assert_false( strpos( $banner, 'Sinemaskop' ) !== false, 'sinemaskop etiketi kaldırıldı' );
		qrms_assert_contains( 'qmo-banner-oran-secici', $banner, 'oran kart seçici' );
		qrms_assert_contains( 'render_banner_oran_kartlari', $banner, 'oran kart helper' );
		qrms_assert_contains( "render_banner_oran_kartlari( 'qmo-banner-oran', 'qmo_banner_slider_settings[oran]'", $banner, 'masaüstü oran form alanı bağlantısı' );
		qrms_assert_contains( "render_banner_oran_kartlari( 'qmo-banner-oran-mobil', 'qmo_banner_slider_settings[oran_mobil]'", $banner, 'mobil oran form alanı bağlantısı' );
		qrms_assert_contains( 'name="<?php echo esc_attr( $name_attr ); ?>"', $banner, 'oran radyosu gönderilen name' );
		qrms_assert_contains( 'aria-labelledby="<?php echo esc_attr( $labelledby ); ?>"', $banner, 'oran grubu aria-labelledby' );
		qrms_assert_contains( 'qmo-banner-oran-mobil-label', $banner, 'mobil oran grubu etiket kimliği' );
		qrms_assert_contains( 'qmo-banner-oran-label', $banner, 'masaüstü oran grubu etiket kimliği' );
		qrms_assert_false( strpos( $banner, 'qmo-banner-oran-native' ) !== false, 'gizli select kaldırıldı' );
		qrms_assert_contains( 'prop(\'disabled\', true)', $js, 'satır içi kaydet çift tıklama koruması' );
		qrms_assert_contains( ':has(input:focus-visible)', $css, 'oran kart klavye odağı' );

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
	'yönetim UX: tek gezinme şeridi, katlanır kısa kod, satır içi odak/görsel, erişilebilir durum',
	function () {
		$dizin   = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/';
		$banner  = file_get_contents( $dizin . 'trait-kampanya-banner-admin.php' );
		$boot    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' );
		$js      = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/admin-ui.js' );
		$css     = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/admin-ui.css' );

		/* ADIM KARMAŞASI: dış gezinme artık numaralı "adım" değil sekme.
		   Ayar formunun GERÇEK stepper'ı (Biçim/Gezinme/Başlık) yerinde
		   durur; ekranda yalnızca bir adım sayacı kalır. */
		qrms_assert_contains( 'class="rma-kb-tabs"', $banner, 'sekme şeridi' );
		qrms_assert_false( strpos( $banner, 'Adım <?php echo (int) $adimlar' ) !== false, 'dış şeritte adım sayacı yok' );
		qrms_assert_false( strpos( $banner, '2. adıma git' ) !== false, 'kartlarda adım numarası yok' );
		// Form stepper'ı korundu.
		qrms_assert_contains( 'id="qmo-banner-steps"', $banner, 'ayar formu stepper\'ı duruyor' );
		qrms_assert_contains( 'Adım 1/<?php echo (int) count( $adimlar ); ?>', $banner, 'form stepper sayacı duruyor' );
		// Sekme şeridi .rma-vitrin-steps değil: o sınıf ≤480px'de gizleniyor
		// ve telefonda gezinmeyi tamamen yok ediyordu.
		qrms_assert_contains( '.rma-kb-tabs {', $css, 'sekme stili' );
		qrms_assert_contains( 'a.rma-kb-tab {', $css, 'sekme düğmesi stili' );

		/* KISA KOD: tek yerde, katlanır bir bölümün içinde. */
		qrms_assert_same( 1, substr_count( $banner, '[qmo_banner_slider]' ), 'kısa kod ekranda tek yerde' );
		qrms_assert_contains( 'private function render_banner_shortcode_kutusu()', $banner, 'katlanır kutu' );
		qrms_assert_contains( '<details class="rma-kb-kisa-kod">', $banner, 'details ile katlanır' );
		qrms_assert_contains( 'Banner\'ı Sayfaya Ekle', $banner, 'başlık' );
		qrms_assert_contains( 'Shortcode” widget', $banner, 'Elementor yönergesi' );
		qrms_assert_contains( 'Kısa Kod” bloğuna', $banner, 'blok editör yönergesi' );
		// autoplay="0" ipucu ana açıklamadan çıktı, ayarının yanında kaldı.
		qrms_assert_contains( 'kısa kod bloğunda otomatik geçiş süresini sayfa bazında değiştirebilirsiniz', $banner, 'teknik ipucu ilgili alanın yanında' );

		/* LİSTE: küçük resim ön yüzün basacağı kırpılmış dosyadan gelir. */
		qrms_assert_contains( 'private function banner_satir_onizleme(', $banner, 'satır önizleme yardımcısı' );
		qrms_assert_contains( 'QMO_Banner_Kirpma::gorsel(', $banner, 'kırpılmış sürüm okunur' );
		qrms_assert_contains( 'QMO_Banner_Kirpma::banner_odagi(', $banner, 'odak mevcut metadan okunur' );
		qrms_assert_false( strpos( $banner, "wp_get_attachment_image_url( \$gorsel_id, 'thumbnail' )" ) !== false, 'kare WP thumbnail kaldırıldı' );
		qrms_assert_contains( 'data-satir-odak', $banner, 'satır içi odak seçici' );
		qrms_assert_contains( 'data-satir-gorsel-sec', $banner, 'satır içi görsel değiştirme' );

		/* YENİ AJAX UCU: mevcut sıra ucundan ayrı, nonce + yetki + meta
		   doğrulaması mevcut save_meta ile aynı kurallarda. */
		qrms_assert_contains( 'wp_ajax_qmo_banner_satir_kaydet', $boot, 'satır ucu kayıtlı' );
		qrms_assert_contains( 'public function ajax_banner_satir_kaydet()', $banner, 'işleyici' );
		qrms_assert_contains( 'check_ajax_referer( $this->banner_satir_nonce_action', $banner, 'nonce' );
		qrms_assert_contains( "current_user_can( 'edit_post', \$banner_id )", $banner, 'kayıt bazlı yetki' );
		qrms_assert_contains( 'QMO_Banner_Kirpma::odak( wp_unslash( $_POST[\'odak\'] ) )', $banner, 'odak beyaz listeden' );
		// Mevcut sıra ucu DEĞİŞMEDİ.
		qrms_assert_contains( 'wp_ajax_qmo_banner_sira_kaydet', $boot, 'sıra ucu duruyor' );
		qrms_assert_contains( 'qmo_banner_sira_kaydet', $js, 'sıra AJAX eylemi duruyor' );

		/* ERİŞİLEBİLİRLİK: durum mesajı role="status", ▲▼ dokunmatikte 44px. */
		qrms_assert_contains( 'role="status" aria-live="polite"', $banner, 'erişilebilir durum satırı' );
		qrms_assert_contains( 'function bannerDurum(', $js, 'durum yazıcısı' );
		qrms_assert_contains( "bannerDurum('Sıra kaydedildi')", $js, 'sıra geri bildirimi' );
		qrms_assert_contains( 'aria-controls="qmo-banner-oran-mobil-alan"', $banner, 'koşullu alan ilişkisi' );
		qrms_assert_contains( 'aria-expanded', $banner, 'kutu durumu duyurulur' );
		qrms_assert_contains( '.rma-admin .rma-banner-sira-btn {', $css, 'dokunmatik ok boyutu' );
		qrms_assert_contains( 'min-width: 44px', $css, '44px dokunma hedefi' );
	}
);

/* ---------------------------------------------------------------------------
 * Kampanya Banner — MOBİL ORAN (oran_mobil)
 *
 * Kural: mobil oran "ayrı bir kavram" değil, aynı oran kümesinden dar ekran
 * için yapılan İKİNCİ bir seçimdir. Kapalıyken ya da masaüstüyle aynı oran
 * seçiliyken sistem ayar eklenmeden önceki gibi davranmalıdır — ikinci kırpma
 * doğmamalı, eski kayıtlar "eksik kırpma" görünmemelidir.
 * ------------------------------------------------------------------------ */

qrms_test(
	'oran_mobil varsayılanı kapalı: mobil oran masaüstüyle aynı, tek kırpma',
	function () {
		$v = QMO_Banner_Slider_Settings::varsayilanlar();

		qrms_assert_same( 0, $v['oran_mobil_farkli'], 'kutu varsayılan olarak kapalı' );
		qrms_assert_same( '16:9', $v['oran_mobil'], 'mobil oran varsayılanı masaüstüyle aynı' );

		// Option hiç yokken (eski kurulum) davranış birebir eskisi gibi.
		$ayar = QMO_Banner_Slider_Settings::get();

		qrms_assert_same( '16:9', QMO_Banner_Slider_Settings::oran_mobil( $ayar ), 'mobil oran masaüstü oranına düşer' );
		qrms_assert_false( QMO_Banner_Slider_Settings::mobil_oran_farkli( $ayar ), 'fark yok' );
		qrms_assert_same( array( '16:9' ), QMO_Banner_Slider_Settings::aktif_oranlar( $ayar ), 'tek aktif oran = tek kırpma' );
	}
);

qrms_test(
	'oran_mobil kapalıyken saklanan mobil oran YOK SAYILIR (eski kayıtlar eksik kırpma görünmez)',
	function () {
		// Kullanıcı kutuyu açıp 1:1 seçmiş, sonra kutuyu kapatmış olabilir:
		// seçim saklanır ama geçerli DEĞİLDİR.
		$temiz = QMO_Banner_Slider_Settings::sanitize(
			array(
				'oran'              => '21:9',
				'oran_mobil_farkli' => 0,
				'oran_mobil'        => '1:1',
			)
		);

		qrms_assert_same( '1:1', $temiz['oran_mobil'], 'seçim kaybolmaz' );
		qrms_assert_same( '21:9', QMO_Banner_Slider_Settings::oran_mobil( $temiz ), 'ama geçerli olan masaüstü oranı' );
		qrms_assert_false( QMO_Banner_Slider_Settings::mobil_oran_farkli( $temiz ), 'kutu kapalıyken fark yok' );
		qrms_assert_same( array( '21:9' ), QMO_Banner_Slider_Settings::aktif_oranlar( $temiz ), 'ikinci kırpma üretilmez' );
	}
);

qrms_test(
	'oran_mobil açık ve FARKLI: ikinci oran gerçekten aktif',
	function () {
		$temiz = QMO_Banner_Slider_Settings::sanitize(
			array(
				'oran'              => '3:1',
				'oran_mobil_farkli' => '1',
				'oran_mobil'        => '4:3',
			)
		);

		qrms_assert_same( '4:3', QMO_Banner_Slider_Settings::oran_mobil( $temiz ), 'mobil oran seçilen değer' );
		qrms_assert_true( QMO_Banner_Slider_Settings::mobil_oran_farkli( $temiz ), 'fark var' );
		qrms_assert_same( array( '3:1', '4:3' ), QMO_Banner_Slider_Settings::aktif_oranlar( $temiz ), 'iki ayrı kırpma' );

		// Beyaz liste mobil oranda da geçerli.
		$bozuk = QMO_Banner_Slider_Settings::sanitize(
			array( 'oran_mobil_farkli' => '1', 'oran_mobil' => '9:16' )
		);
		qrms_assert_same( '16:9', $bozuk['oran_mobil'], 'bilinmeyen mobil oran varsayılana düşer' );
	}
);

qrms_test(
	'oran_mobil açık ama AYNI oran: gereksiz ikinci kırpma üretilmez',
	function () {
		$temiz = QMO_Banner_Slider_Settings::sanitize(
			array(
				'oran'              => '21:9',
				'oran_mobil_farkli' => '1',
				'oran_mobil'        => '21:9',
			)
		);

		qrms_assert_true( 1 === (int) $temiz['oran_mobil_farkli'], 'kutu işaretli kalır' );
		qrms_assert_false( QMO_Banner_Slider_Settings::mobil_oran_farkli( $temiz ), 'aynı oran seçilince fark yok' );
		qrms_assert_same( array( '21:9' ), QMO_Banner_Slider_Settings::aktif_oranlar( $temiz ), 'tek kırpma yeterli' );
	}
);

qrms_test(
	'mobil oran CSS değişkeni, kırpma listesi ve kısa kod <picture> yoluna bağlanır',
	function () {
		$dizin = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/';

		// Kök öğeye her zaman iki değişken de basılır; kapalıyken ikisi aynıdır.
		$ayni = QMO_Banner_Slider_Settings::css_degiskenleri(
			QMO_Banner_Slider_Settings::sanitize( array( 'oran' => '16:9' ) )
		);
		qrms_assert_contains( '--qmo-banner-oran:16 / 9', $ayni, 'masaüstü oranı' );
		qrms_assert_contains( '--qmo-banner-oran-mobil:16 / 9', $ayni, 'kapalıyken mobil oran masaüstüyle aynı' );

		$farkli = QMO_Banner_Slider_Settings::css_degiskenleri(
			QMO_Banner_Slider_Settings::sanitize(
				array( 'oran' => '3:1', 'oran_mobil_farkli' => '1', 'oran_mobil' => '1:1' )
			)
		);
		qrms_assert_contains( '--qmo-banner-oran:3 / 1', $farkli, 'masaüstü 3:1' );
		qrms_assert_contains( '--qmo-banner-oran-mobil:1 / 1', $farkli, 'mobil 1:1' );

		/* CSS: MOBİL ORAN KURALI @media ALTINDA OLMALI, @container ALTINDA DEĞİL.
		   Gerekçe: kutunun oranını belirleyen kırılım ile kısa kodun
		   <source media> ile dosya seçtiği kırılım aynı referansa (viewport)
		   bakmak zorunda. @container altında kalsaydı dar bir Elementor
		   kolonunda kutu mobil orana geçer, tarayıcı masaüstü dosyasını
		   indirirdi. */
		$css = file_get_contents( $dizin . 'frontend-banner-slider.css' );
		qrms_assert_contains( 'var(--qmo-banner-oran-mobil, var(--qmo-banner-oran, 16 / 9))', $css, 'mobil oran CSS kuralı' );

		$media_blok = strpos( $css, '@media (max-width: 720px)' );
		$cq_blok    = strpos( $css, '@container qmo-banner (max-width: 720px)' );
		$oran_kural = strpos( $css, 'aspect-ratio: var(--qmo-banner-oran-mobil' );

		qrms_assert_true( false !== $media_blok, 'mobil oran için viewport kırılımı var' );
		qrms_assert_true( false !== $cq_blok, 'kapsayıcı kırılımı (punto/ok/peek) korundu' );
		qrms_assert_true( $oran_kural > $media_blok, 'oran kuralı @media bloğunda başlıyor' );
		qrms_assert_true( $oran_kural < $cq_blok, 'oran kuralı @container bloğundan ÖNCE — içinde değil' );
		// @container bloğunda mobil oran değişkenine hiç dokunulmamalı.
		qrms_assert_false(
			strpos( substr( $css, $cq_blok ), '--qmo-banner-oran-mobil' ) !== false,
			'kapsayıcı sorgusu oran kararı vermiyor'
		);
		// Masaüstü oranı hâlâ viewport'un temel kuralı.
		qrms_assert_contains( 'aspect-ratio: var(--qmo-banner-oran, 16 / 9)', $css, 'masaüstü oranı değişmedi' );
		qrms_assert_contains( '.qmo-banner-picture', $css, 'picture display:contents' );

		// Kırılım TEK KAYNAK: PHP sabiti ile CSS'teki sayı aynı olmalı.
		qrms_assert_same( 720, QMO_Banner_Slider_Settings::MOBIL_KIRILIM, 'kırılım sabiti' );
		qrms_assert_same( '(max-width: 720px)', QMO_Banner_Slider_Settings::mobil_medya(), 'medya sorgusu metni' );

		// Kırpma: aktif oranların TAMAMI için üretilir, tek oran çağrısı korunur.
		$kirpma = file_get_contents( $dizin . 'class-banner-kirpma.php' );
		qrms_assert_contains( 'QMO_Banner_Slider_Settings::aktif_oranlar()', $kirpma, 'aktif oran listesi tek kaynaktan' );
		qrms_assert_contains( 'private static function oran_listesi(', $kirpma, 'oran listesi yardımcısı' );
		qrms_assert_contains( 'foreach ( self::oran_listesi( $oran ) as $hedef )', $kirpma, 'her aktif oran için kırpma' );
		// Boyut adı ORAN BAŞINA: mobil kırpma kendi adıyla yaşar, masaüstünü ezmez.
		qrms_assert_contains( "return 'qmo-banner-' . str_replace( ':', 'x', self::gecerli_oran( \$oran ) );", $kirpma, 'kırpma isimlendirmesi korundu' );

		/* Kısa kod: mobil dosya seçimi "yeni kırpma üretildi mi" ölçütüne
		   DEĞİL, "bu dosya gerçekten mobil oranda mı" ölçütüne bağlı.
		   Davranışın kendisi aşağıdaki oranli_gorsel() testlerinde
		   doğrulanıyor; burada yalnızca kısa kodun doğru fonksiyona
		   bağlandığı ve eski hatalı koşulun geri gelmediği kontrol edilir. */
		$kod = file_get_contents( $dizin . 'shortcode-banner-slider.php' );
		qrms_assert_contains( 'QMO_Banner_Slider_Settings::mobil_oran_farkli( $ayar )', $kod, 'kısa kod farkı kontrol eder' );
		qrms_assert_contains( '<source media="', $kod, 'mobil kaynak' );
		qrms_assert_contains( 'QMO_Banner_Kirpma::oranli_gorsel( $image_id, $mobil_oran, $odak )', $kod, 'orana uyan dosya çözülür' );
		qrms_assert_false(
			strpos( $kod, "! empty( \$mobil['kirpildi'] )" ) !== false,
			'eski hatalı "yalnızca kırpıldıysa" koşulu kaldırıldı'
		);
		qrms_assert_contains( 'QMO_Banner_Slider_Settings::mobil_medya()', $kod, 'kırılım tek kaynaktan okunur' );
		qrms_assert_contains( 'if ( $mobil_img === $img )', $kod, 'aynı dosyada <source> basılmaz' );
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
		qrms_assert_contains( 'Tüm görselleri banner oranına uydur', $banner, 'toplu düğme' );
		qrms_assert_contains( 'Yeniden kırp', $banner, 'satır düğmesi' );

		// Satır eylemi bir <span> içinde durduğu için <form> değil nonce'lu
		// bağlantıdır (WordPress'in kendi satır eylemi deseni).
		qrms_assert_contains( 'wp_nonce_url(', $banner, 'bağlantı nonce\'lu' );
		qrms_assert_false( strpos( $banner, 'banner_kirp_formu' ) !== false, 'span içinde form yok' );

		// Satır başına durum rozeti: kullanıcı hangi görselin eski
		// olduğunu görmeden bırakılmaz. Durum artık TEK oran için değil,
		// aktif oranların tamamı (masaüstü + varsa mobil) için hesaplanır —
		// bu yüzden satır durum(] yerine banner_durumu() okur.
		qrms_assert_contains( 'QMO_Banner_Kirpma::banner_durumu(', $banner, 'liste durumu okur' );
		qrms_assert_contains( 'rma-kb-kirpma-rozet', $banner, 'durum rozeti' );
		qrms_assert_contains( 'QMO_Banner_Kirpma::bekleyen_sayisi(', $banner, 'bekleyen sayısı' );

		// banner_durumu() tek oranlı durum() üzerine kuruludur; eski
		// fonksiyon kaldırılmadı (kırpma sınıfı ve testleri onu kullanır).
		$kirpma_src = file_get_contents( $dizin . 'class-banner-kirpma.php' );
		qrms_assert_contains( 'public static function banner_durumu(', $kirpma_src, 'toplu durum fonksiyonu' );
		qrms_assert_contains( 'public static function durum(', $kirpma_src, 'tek oran durumu korundu' );

		// WordPress\'in kendi liste ekranı da uyarır.
		qrms_assert_contains( 'Güncel orana kırpılmadı', $cpt, 'CPT liste sütunu uyarısı' );

		// Oran sonradan değişirse kullanıcı bilgilendirilir.
		qrms_assert_contains( "'oran_degisti'", $banner, 'oran değişimi bildirimi' );
		qrms_assert_contains( 'yeni orana uydurmanız gerekebilir', $banner, 'bildirim metni' );

		// Durum rozetlerinin stili admin CSS\'inde tanımlı.
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/admin-ui.css' );
		qrms_assert_contains( '.rma-kb-kirpma-rozet', $css, 'rozet stili' );
		qrms_assert_contains( '.rma-kb-kirpma-uyari', $css, 'uyarı kutusu stili' );
	}
);

/* ---------------------------------------------------------------------------
 * MOBİL KAYNAK SEÇİMİ — GERÇEK DAVRANIŞ (string değil)
 *
 * Buradaki testler QMO_Banner_Kirpma'yı gerçekten çalıştırır: ek metadata'sı
 * stub'lara yazılır, durum()/gorsel()/oranli_gorsel() çağrılır ve dönen
 * DOSYA karşılaştırılır. Böylece "mobil <source> hangi durumda basılır"
 * sorusu implementasyon metnine değil davranışa bağlanır.
 * ------------------------------------------------------------------------ */

require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/admin-cpt-banner.php';

/**
 * Test için sahte bir ek (attachment) kurar.
 *
 * @param int   $ek_id Ek kimliği.
 * @param int   $en    Kaynak genişlik.
 * @param int   $boy   Kaynak yükseklik.
 * @param array $sizes Metadata'daki ek boyutlar (kırpmalar).
 * @return void
 */
function qrms_banner_ek_kur( $ek_id, $en, $boy, array $sizes = array() ) {
	$GLOBALS['qrms_test']['post_types'][ $ek_id ] = 'attachment';

	$meta = array( 'width' => (int) $en, 'height' => (int) $boy );

	if ( $sizes ) {
		$meta['sizes'] = $sizes;
	}

	$GLOBALS['qrms_test']['attachment_meta'][ $ek_id ] = $meta;
}

/**
 * Bir orana ait sahte kırpma kaydı üretir (kirp()'in yazdığıyla aynı şekil).
 *
 * @param string $oran Oran anahtarı.
 * @param int    $en   Kırpma genişliği.
 * @param int    $boy  Kırpma yüksekliği.
 * @param string $odak Odak anahtarı.
 * @return array
 */
function qrms_banner_kirpma_kaydi( $oran, $en, $boy, $odak = 'merkez' ) {
	return array(
		QMO_Banner_Kirpma::boyut_adi( $oran ) => array(
			'file'      => 'banner-' . str_replace( ':', 'x', $oran ) . '.jpg',
			'width'     => (int) $en,
			'height'    => (int) $boy,
			'mime-type' => 'image/jpeg',
			'qmo_odak'  => $odak,
		),
	);
}

/**
 * Bir banner kaydını (CPT) görsel ve odakla kurar.
 *
 * @param int    $banner_id Banner kimliği.
 * @param int    $ek_id     Ek kimliği.
 * @param string $odak      Odak anahtarı.
 * @return void
 */
function qrms_banner_kayit_kur( $banner_id, $ek_id, $odak = 'merkez' ) {
	$GLOBALS['qrms_test']['post_types'][ $banner_id ] = QMO_Banner_CPT::POST_TYPE;

	update_post_meta( $banner_id, QMO_Banner_CPT::META_IMAGE, $ek_id );
	update_post_meta( $banner_id, QMO_Banner_Kirpma::META_ODAK, $odak );
}

qrms_test(
	'SENARYO 1 — kaynak NATİF mobil oranda: mobil kaynak orijinalden gelir',
	function () {
		// Masaüstü 16:9, mobil 4:3. Kaynak 1200x900 = tam 4:3.
		QMO_Banner_Slider_Settings::kaydet(
			array( 'oran' => '16:9', 'oran_mobil_farkli' => '1', 'oran_mobil' => '4:3' )
		);

		// 4:3 için kırpma YOK (gerekmiyor); 16:9 için kırpma var.
		qrms_banner_ek_kur( 41, 1200, 900, qrms_banner_kirpma_kaydi( '16:9', 1200, 675 ) );
		qrms_banner_kayit_kur( 401, 41 );

		// Durum: mobil oran kaynağa zaten uyuyor.
		qrms_assert_same( 'uygun', QMO_Banner_Kirpma::durum( 41, '4:3', 'merkez' ), 'kaynak zaten 4:3' );
		qrms_assert_same( 'hazir', QMO_Banner_Kirpma::durum( 41, '16:9', 'merkez' ), '16:9 kırpması hazır' );

		// ASIL DÜZELTME: kırpma dosyası üretilmemiş olmasına rağmen mobil
		// kaynak çözülebiliyor ve ORİJİNALDİR.
		$mobil = QMO_Banner_Kirpma::oranli_gorsel( 41, '4:3', 'merkez' );
		qrms_assert_true( is_array( $mobil ), 'mobil kaynak çözüldü' );
		qrms_assert_false( $mobil['kirpildi'], 'kırpma dosyası yok — orijinal kullanılıyor' );
		qrms_assert_same( wp_get_attachment_image_url( 41, 'full' ), $mobil['url'], 'orijinal dosya' );

		// Masaüstü kaynağı kırpılmış dosyadır ve mobilden FARKLIDIR:
		// yani kısa kod <source> basar.
		$masaustu = QMO_Banner_Kirpma::oranli_gorsel( 41, '16:9', 'merkez' );
		qrms_assert_true( $masaustu['kirpildi'], '16:9 kırpması kullanılıyor' );
		qrms_assert_false( $masaustu['url'] === $mobil['url'], 'iki oran iki farklı dosya' );

		// Kayıt "bekliyor" görünmemeli: iki oran da çözülüyor.
		qrms_assert_same( 'hazir', QMO_Banner_Kirpma::banner_durumu( 401 ), 'kayıt hazır' );
		qrms_assert_same( 0, QMO_Banner_Kirpma::bekleyen_sayisi( null, array( (object) array( 'ID' => 401 ) ) ), 'bekleyen yok' );

		qrms_reset();
	}
);

qrms_test(
	'SENARYO 2 — mobil kırpma mevcut: mobil kaynak kırpılmış dosyadan gelir',
	function () {
		QMO_Banner_Slider_Settings::kaydet(
			array( 'oran' => '16:9', 'oran_mobil_farkli' => '1', 'oran_mobil' => '4:3' )
		);

		// Kare kaynak; iki oran için de kırpma üretilmiş.
		$sizes = array_merge(
			qrms_banner_kirpma_kaydi( '16:9', 1000, 563 ),
			qrms_banner_kirpma_kaydi( '4:3', 1000, 750 )
		);
		qrms_banner_ek_kur( 42, 1000, 1000, $sizes );
		qrms_banner_kayit_kur( 402, 42 );

		qrms_assert_same( 'hazir', QMO_Banner_Kirpma::durum( 42, '4:3', 'merkez' ), '4:3 kırpması hazır' );

		$mobil    = QMO_Banner_Kirpma::oranli_gorsel( 42, '4:3', 'merkez' );
		$masaustu = QMO_Banner_Kirpma::oranli_gorsel( 42, '16:9', 'merkez' );

		qrms_assert_true( $mobil['kirpildi'], 'mobil kırpma kullanılıyor' );
		qrms_assert_same( 750, $mobil['boy'], 'mobil dosya 4:3 yüksekliğinde' );
		qrms_assert_same( 563, $masaustu['boy'], 'masaüstü dosya 16:9 yüksekliğinde' );
		qrms_assert_false( $mobil['url'] === $masaustu['url'], 'iki ayrı dosya' );

		qrms_assert_same( 'hazir', QMO_Banner_Kirpma::banner_durumu( 402 ), 'kayıt hazır' );

		qrms_reset();
	}
);

qrms_test(
	'SENARYO 3 — mobil kırpma yok ve kaynak mobil oranda değil: <source> basılmaz, durum bekliyor',
	function () {
		QMO_Banner_Slider_Settings::kaydet(
			array( 'oran' => '16:9', 'oran_mobil_farkli' => '1', 'oran_mobil' => '4:3' )
		);

		// Kare kaynak; YALNIZCA 16:9 kırpması var.
		qrms_banner_ek_kur( 43, 1000, 1000, qrms_banner_kirpma_kaydi( '16:9', 1000, 563 ) );
		qrms_banner_kayit_kur( 403, 43 );

		qrms_assert_same( 'bekliyor', QMO_Banner_Kirpma::durum( 43, '4:3', 'merkez' ), '4:3 kırpması eksik' );

		// gorsel() burada SESSİZCE orijinale düşerdi — yanlış oranlı dosya.
		$ham = QMO_Banner_Kirpma::gorsel( 43, '4:3', 'merkez' );
		qrms_assert_true( is_array( $ham ), 'gorsel() yine de bir şey döndürür' );
		qrms_assert_false( $ham['kirpildi'], 'döndürdüğü orijinaldir' );

		// oranli_gorsel() bunu KABUL ETMEZ: mobil <source> hiç basılmaz ve
		// tarayıcı masaüstü dosyasına düşer.
		qrms_assert_true( null === QMO_Banner_Kirpma::oranli_gorsel( 43, '4:3', 'merkez' ), 'uymayan dosya reddedilir' );

		// Kullanıcı bunu yönetimde görür.
		qrms_assert_same( 'bekliyor', QMO_Banner_Kirpma::banner_durumu( 403 ), 'kayıt bekliyor' );
		qrms_assert_same( 1, QMO_Banner_Kirpma::bekleyen_sayisi( null, array( (object) array( 'ID' => 403 ) ) ), 'bekleyen sayılır' );

		qrms_reset();
	}
);

qrms_test(
	'SENARYO 4 — mobil oran KAPALI: eski kayıtlar eski davranışın aynısı',
	function () {
		// Kutu kapalı ama saklı bir mobil oran var (kullanıcı açıp kapatmış).
		QMO_Banner_Slider_Settings::kaydet(
			array( 'oran' => '16:9', 'oran_mobil_farkli' => '0', 'oran_mobil' => '4:3' )
		);

		// Kare kaynak, yalnızca 16:9 kırpması — 4:3 kırpması YOK.
		qrms_banner_ek_kur( 44, 1000, 1000, qrms_banner_kirpma_kaydi( '16:9', 1000, 563 ) );
		qrms_banner_kayit_kur( 404, 44 );

		// Tek aktif oran: 4:3'ün eksikliği kaydı BEKLİYOR yapmaz.
		qrms_assert_same( array( '16:9' ), QMO_Banner_Slider_Settings::aktif_oranlar(), 'tek aktif oran' );
		qrms_assert_same( 'hazir', QMO_Banner_Kirpma::banner_durumu( 404 ), 'eski kayıt hazır görünür' );
		qrms_assert_same( 0, QMO_Banner_Kirpma::bekleyen_sayisi( null, array( (object) array( 'ID' => 404 ) ) ), 'bekleyen yok' );

		// Sonuç tek oranlı eski çağrıyla birebir aynı.
		qrms_assert_same(
			QMO_Banner_Kirpma::durum( 44, '16:9', 'merkez' ),
			QMO_Banner_Kirpma::banner_durumu( 404 ),
			'toplu durum tek oran durumuyla aynı'
		);

		qrms_reset();
	}
);

qrms_test(
	'SENARYO 5 — mobil oran masaüstüyle AYNI: ikinci kırpma da <source> da doğmaz',
	function () {
		QMO_Banner_Slider_Settings::kaydet(
			array( 'oran' => '16:9', 'oran_mobil_farkli' => '1', 'oran_mobil' => '16:9' )
		);

		qrms_banner_ek_kur( 45, 1000, 1000, qrms_banner_kirpma_kaydi( '16:9', 1000, 563 ) );
		qrms_banner_kayit_kur( 405, 45 );

		qrms_assert_false( QMO_Banner_Slider_Settings::mobil_oran_farkli(), 'fark yok' );
		qrms_assert_same( array( '16:9' ), QMO_Banner_Slider_Settings::aktif_oranlar(), 'tek kırpma' );
		qrms_assert_same( 'hazir', QMO_Banner_Kirpma::banner_durumu( 405 ), 'kayıt hazır' );

		// Aynı oran istendiğinde iki çözümleme AYNI dosyayı verir; kısa kod
		// bu durumda <source> basmaz (mobil_img === img kontrolü).
		$masaustu = QMO_Banner_Kirpma::oranli_gorsel( 45, '16:9', 'merkez' );
		$mobil    = QMO_Banner_Kirpma::oranli_gorsel( 45, QMO_Banner_Slider_Settings::oran_mobil(), 'merkez' );
		qrms_assert_same( $masaustu['url'], $mobil['url'], 'aynı dosya' );

		qrms_reset();
	}
);

qrms_test(
	'SENARYO 6 — dar kapsayıcı + geniş viewport: oran kararı ile dosya seçimi aynı referansa bakar',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/frontend-banner-slider.css' );
		$kod = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/shortcode-banner-slider.php' );

		/* Senaryo: viewport 1200px, Elementor kolonu 500px.
		   Eski hâlde @container (500 <= 720) eşleşip kutuyu mobil orana
		   alıyor, <source media> (1200 > 720) eşleşmiyordu → mobil oranlı
		   kutuya masaüstü kırpması giriyordu.

		   Düzeltme: oran kuralı @media'ya taşındı. Artık bu senaryoda
		   İKİSİ DE eşleşmez → kutu masaüstü oranında, dosya masaüstü
		   kırpması. Çelişki yapısal olarak imkânsız hâle geldi. */
		$oran_kural = strpos( $css, 'aspect-ratio: var(--qmo-banner-oran-mobil' );
		$cq_blok    = strpos( $css, '@container qmo-banner (max-width: 720px)' );

		qrms_assert_true( false !== $oran_kural, 'mobil oran kuralı var' );
		qrms_assert_true( $oran_kural < $cq_blok, 'oran kuralı kapsayıcı bloğunun dışında' );

		// İki taraf da AYNI sayıyı kullanıyor ve sayı tek kaynaktan geliyor.
		qrms_assert_contains( '@media (max-width: ' . QMO_Banner_Slider_Settings::MOBIL_KIRILIM . 'px)', $css, 'CSS kırılımı' );
		qrms_assert_contains( 'QMO_Banner_Slider_Settings::mobil_medya()', $kod, 'kısa kod aynı kaynaktan okur' );
		qrms_assert_same( '(max-width: 720px)', QMO_Banner_Slider_Settings::mobil_medya(), 'aynı sorgu metni' );

		// Kapsayıcı sorgusu hâlâ duruyor ama YALNIZCA yerleşim için:
		// punto, ok boyutu ve peek. Hiçbiri dosya seçmez.
		$cq_govde = substr( $css, $cq_blok );
		qrms_assert_contains( '--qmo-banner-peek: 7.5%', $cq_govde, 'peek kapsayıcıya bağlı kaldı' );
		qrms_assert_contains( '--qmo-banner-title-size-mobile', $cq_govde, 'punto kapsayıcıya bağlı kaldı' );
		qrms_assert_false( strpos( $cq_govde, 'aspect-ratio' ) !== false, 'kapsayıcı sorgusu oran belirlemiyor' );
	}
);

/* =============================================================================
 * FAZ 2 AŞAMA 1+2 — payloadlar() → kok_html() → render_shortcode() TEK
 * RENDERER + wp_ajax_qmo_banner_onizleme (kaydedilmemiş oran canlı önizleme)
 *
 * Buradan itibaren QMO_Shortcode_Banner_Slider ve
 * RMA_Kampanya_Banner_Admin_Trait GERÇEKTEN yüklenip çalıştırılır — yalnızca
 * kaynak metin karşılaştırması değil. QMO_Banner_CPT::get_published_banners()
 * bir WP_Query açtığından ve bu test paketinde WP_Query'nin genel bir
 * stub'ı bulunmadığından (bkz. test-ajax-403-status.php başlık yorumu),
 * burada YALNIZCA banner sorgusunun kullandığı dar argüman kümesini
 * (post_type + post_status) destekleyen minimal bir taklit tanımlanır. Bu
 * ekleme geriye dönük NÖTRDÜR: WP_Query daha önce hiçbir yerde tanımlı
 * olmadığından hiçbir mevcut test onu gerçekten çalıştırmıyordu (çalıştırsaydı
 * zaten "Class WP_Query not found" ile fatal verip paket kırmızı olurdu).
 * ========================================================================= */

if ( ! defined( 'QMO_PLUGIN_DIR' ) ) {
	define( 'QMO_PLUGIN_DIR', QRMS_PLUGIN_DIR . 'modules/restoran-menu/' );
}
if ( ! defined( 'QMO_PLUGIN_URL' ) ) {
	define( 'QMO_PLUGIN_URL', 'https://example.test/wp-content/plugins/qr-menu-suite/modules/restoran-menu/' );
}

require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/helpers.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/shortcode-banner-slider.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-kampanya-banner-admin.php';

if ( ! class_exists( 'RMA_Test_Banner_Onizleme_Harness' ) ) {
	/**
	 * ajax_banner_onizleme() dışında hiçbir admin sayfası bağımlılığı
	 * gerektirmez; test-ajax-403-status.php'deki RMA_Test_Ajax403_Harness
	 * ile aynı desen — trait YALNIZ BAŞINA `use` edilebilir bir sınıfa alınır.
	 */
	class RMA_Test_Banner_Onizleme_Harness {
		use RMA_Kampanya_Banner_Admin_Trait;
	}
}

/**
 * Banner CPT kaydını, ek metadata'sıyla birlikte kurar; qrms_banner_kayit_kur()'a
 * (yukarıda tanımlı) başlık ekler — WP_Query taklidinin post_title alanı bunu okur.
 *
 * @param int    $banner_id Banner kimliği.
 * @param int    $ek_id     Ek kimliği.
 * @param string $baslik    Banner başlığı (alt metin yedeği).
 * @param string $odak      Odak anahtarı.
 * @return void
 */
function qrms_banner_yayinla( $banner_id, $ek_id, $baslik = 'Test Banner', $odak = 'merkez' ) {
	qrms_banner_kayit_kur( $banner_id, $ek_id, $odak );
	$GLOBALS['qrms_test']['post_title'][ $banner_id ] = $baslik;
}

qrms_test(
	'payloadlar(): gerçek çalıştırma — tek banner, kırpılmamış orijinal, mobil oran kapalı',
	function () {
		QMO_Banner_Slider_Settings::kaydet( array( 'oran' => '16:9' ) );

		qrms_banner_ek_kur( 501, 1600, 900 ); // zaten 16:9, kırpma gerekmiyor.
		qrms_banner_yayinla( 601, 501, 'Yaz Kampanyası' );

		$banners = QMO_Shortcode_Banner_Slider::payloadlar();

		qrms_assert_same( 1, count( $banners ), 'tek yayınlanmış banner' );
		qrms_assert_same( 'Yaz Kampanyası', $banners[0]['alt'], 'alt metin yedeği başlığa düşer' );
		qrms_assert_same( '', $banners[0]['mobil_img'], 'mobil oran kapalıyken mobil dosya yok' );
		qrms_assert_true( '' !== $banners[0]['img'], 'görsel URL çözüldü' );
	}
);

qrms_test(
	'payloadlar(): HENÜZ KAYDEDİLMEMİŞ $ayar override\'ı ile çağrılınca kayıtlı ayarı DEĞİL, verileni kullanır',
	function () {
		// Kayıtlı ayar 16:9/mobil KAPALI — override 16:9 masaüstü, mobil 4:3
		// AÇIK. payloadlar() override'ı almasaydı ikinci çağrı da mobil_img
		// boş dönerdi (kayıtlı ayarda mobil oran hiç aranmaz).
		QMO_Banner_Slider_Settings::kaydet( array( 'oran' => '16:9', 'oran_mobil_farkli' => '0' ) );

		$sizes = array_merge(
			qrms_banner_kirpma_kaydi( '16:9', 1000, 563 ),
			qrms_banner_kirpma_kaydi( '4:3', 1000, 750 )
		);
		qrms_banner_ek_kur( 502, 1000, 1000, $sizes );
		qrms_banner_yayinla( 602, 502 );

		$kayitli_ayar    = QMO_Banner_Slider_Settings::get();
		$banners_kayitli = QMO_Shortcode_Banner_Slider::payloadlar( $kayitli_ayar );
		qrms_assert_same( '', $banners_kayitli[0]['mobil_img'], 'kayıtlı ayarda mobil kapalı — mobil dosya yok' );

		$override                       = $kayitli_ayar;
		$override['oran_mobil_farkli']  = 1;
		$override['oran_mobil']         = '4:3';

		$banners_override = QMO_Shortcode_Banner_Slider::payloadlar( $override );
		qrms_assert_true( '' !== $banners_override[0]['mobil_img'], 'HENÜZ KAYDEDİLMEMİŞ override ile mobil dosya çözülür' );
		qrms_assert_false(
			$banners_override[0]['mobil_img'] === $banners_override[0]['img'],
			'mobil ve masaüstü farklı dosyalar — override gerçekten etkiledi'
		);

		// Kayıtlı ayar bu çağrılardan ETKİLENMEDİ.
		qrms_assert_same( '0', (string) QMO_Banner_Slider_Settings::get()['oran_mobil_farkli'], 'option değişmedi' );
	}
);

qrms_test(
	'kok_html(): temel markup sözleşmesi — kök/viewport/track/slide sınıfları, data ve ARIA öznitelikleri korunur',
	function () {
		$banners = array(
			array( 'img' => 'https://x.test/a.jpg', 'srcset' => '', 'alt' => 'A', 'title' => 'A', 'link' => '', 'odak' => '', 'mobil_img' => '' ),
			array( 'img' => 'https://x.test/b.jpg', 'srcset' => '', 'alt' => 'B', 'title' => 'B', 'link' => '', 'odak' => '', 'mobil_img' => '' ),
		);
		$ayar = array_merge( QMO_Banner_Slider_Settings::varsayilanlar(), array( 'show_nav' => 1, 'show_dots' => 1 ) );

		$html = QMO_Shortcode_Banner_Slider::kok_html( $banners, $ayar, array( 'betik' => false ) );

		qrms_assert_contains( 'class="qmo-banner-root is-peek"', $html, 'kök sınıf + peek (count>1)' );
		qrms_assert_contains( 'qmo-banner-viewport', $html, 'viewport' );
		qrms_assert_contains( 'data-qmo-banner-track', $html, 'track data attribute' );
		qrms_assert_same( 2, substr_count( $html, 'data-qmo-banner-slide="' ), 'iki slayt basıldı' );
		qrms_assert_contains( 'data-qmo-banner-slide="0"', $html, 'slayt index data attribute' );
		qrms_assert_contains( 'role="region"', $html, 'ARIA region' );
		qrms_assert_contains( 'aria-roledescription="karusel"', $html, 'ARIA karusel' );
		qrms_assert_contains( 'qmo-banner-nav', $html, 'birden fazla banner + show_nav=1 → ok basılır' );
		qrms_assert_contains( 'role="tablist"', $html, 'birden fazla banner + show_dots=1 → nokta basılır' );
		qrms_assert_false( strpos( $html, '<script' ) !== false, 'betik=false → script basılmaz' );
	}
);

qrms_test(
	'kok_html(): betik=true (varsayılan) <script> etiketini basar; tek banner ise nav/dots/peek basılmaz',
	function () {
		$banners = array(
			array( 'img' => 'https://x.test/a.jpg', 'srcset' => '', 'alt' => 'A', 'title' => 'A', 'link' => '', 'odak' => '', 'mobil_img' => '' ),
		);
		$ayar = array_merge( QMO_Banner_Slider_Settings::varsayilanlar(), array( 'show_nav' => 1, 'show_dots' => 1 ) );

		$html = QMO_Shortcode_Banner_Slider::kok_html( $banners, $ayar );

		qrms_assert_contains( '<script src=', $html, 'betik varsayılan true' );
		qrms_assert_false( strpos( $html, 'is-peek' ) !== false, 'tek banner → peek yok' );
		qrms_assert_false( strpos( $html, 'qmo-banner-nav' ) !== false, 'tek banner → ok basılmaz (show_nav=1 olsa bile)' );
		qrms_assert_false( strpos( $html, 'role="tablist"' ) !== false, 'tek banner → nokta basılmaz' );
	}
);

qrms_test(
	'kok_html(): mobil <source> yalnızca mobil oran açık VE mobil_img doluyken basılır',
	function () {
		$ayar_kapali = array_merge( QMO_Banner_Slider_Settings::varsayilanlar(), array( 'oran' => '16:9', 'oran_mobil_farkli' => 0 ) );
		$ayar_acik   = array_merge( QMO_Banner_Slider_Settings::varsayilanlar(), array( 'oran' => '16:9', 'oran_mobil_farkli' => 1, 'oran_mobil' => '4:3' ) );

		$banner_mobilli = array( array( 'img' => 'https://x.test/desktop.jpg', 'srcset' => '', 'alt' => 'A', 'title' => 'A', 'link' => '', 'odak' => '', 'mobil_img' => 'https://x.test/mobile.jpg' ) );

		$html_acik   = QMO_Shortcode_Banner_Slider::kok_html( $banner_mobilli, $ayar_acik, array( 'betik' => false ) );
		$html_kapali = QMO_Shortcode_Banner_Slider::kok_html( $banner_mobilli, $ayar_kapali, array( 'betik' => false ) );

		qrms_assert_contains( '<picture class="qmo-banner-picture">', $html_acik, 'mobil oran açık + mobil dosya var → <picture> basılır' );
		qrms_assert_contains( '<source media="(max-width: 720px)" srcset="https://x.test/mobile.jpg">', $html_acik, 'kaynak dosya ve kırılım doğru' );
		qrms_assert_false( strpos( $html_kapali, '<picture' ) !== false, 'mobil oran kapalıyken AYNI payload <picture> basmaz' );

		$banner_mobilsiz = array( array( 'img' => 'https://x.test/desktop.jpg', 'srcset' => '', 'alt' => 'A', 'title' => 'A', 'link' => '', 'odak' => '', 'mobil_img' => '' ) );
		$html_bos        = QMO_Shortcode_Banner_Slider::kok_html( $banner_mobilsiz, $ayar_acik, array( 'betik' => false ) );
		qrms_assert_false( strpos( $html_bos, '<picture' ) !== false, 'mobil oran açık ama mobil_img boş (crop eksik) → <picture> basılmaz' );
	}
);

qrms_test(
	'kok_html(): odak/object-position yalnızca dolu geldiğinde basılır (kırpılmış banner style basmaz)',
	function () {
		$ayar = QMO_Banner_Slider_Settings::varsayilanlar();

		$odakli = array( array( 'img' => 'https://x.test/a.jpg', 'srcset' => '', 'alt' => 'A', 'title' => 'A', 'link' => '', 'odak' => 'top center', 'mobil_img' => '' ) );
		$html   = QMO_Shortcode_Banner_Slider::kok_html( $odakli, $ayar, array( 'betik' => false ) );
		qrms_assert_contains( 'style="object-position:top center;"', $html, 'odak doluysa inline style basılır' );

		$kirpilmis = array( array( 'img' => 'https://x.test/a.jpg', 'srcset' => '', 'alt' => 'A', 'title' => 'A', 'link' => '', 'odak' => '', 'mobil_img' => '' ) );
		$html2     = QMO_Shortcode_Banner_Slider::kok_html( $kirpilmis, $ayar, array( 'betik' => false ) );
		qrms_assert_false( strpos( $html2, 'object-position' ) !== false, 'kırpılmış banner (odak boş) style basmaz' );
	}
);

qrms_test(
	'kok_html(): link varsa <a>, yoksa <div>; autoplay opsiyon ile ezilir, verilmezse $ayar[autoplay] kullanılır',
	function () {
		$ayar = array_merge( QMO_Banner_Slider_Settings::varsayilanlar(), array( 'autoplay' => 4500 ) );

		$linkli = array( array( 'img' => 'https://x.test/a.jpg', 'srcset' => '', 'alt' => 'A', 'title' => 'A', 'link' => 'https://x.test/kampanya', 'odak' => '', 'mobil_img' => '' ) );
		$html   = QMO_Shortcode_Banner_Slider::kok_html( $linkli, $ayar, array( 'betik' => false ) );
		qrms_assert_contains( '<a class="qmo-banner-slide', $html, 'link varsa <a>' );
		qrms_assert_contains( 'href="https://x.test/kampanya"', $html, 'href doğru' );

		$linksiz = array( array( 'img' => 'https://x.test/a.jpg', 'srcset' => '', 'alt' => 'A', 'title' => 'A', 'link' => '', 'odak' => '', 'mobil_img' => '' ) );
		$html2   = QMO_Shortcode_Banner_Slider::kok_html( $linksiz, $ayar, array( 'betik' => false ) );
		qrms_assert_contains( '<div class="qmo-banner-slide', $html2, 'link yoksa <div>' );

		$html3 = QMO_Shortcode_Banner_Slider::kok_html( $linksiz, $ayar, array( 'betik' => false, 'autoplay' => 0 ) );
		qrms_assert_contains( 'data-autoplay="0"', $html3, 'opsiyon autoplay değerini ezer' );

		$html4 = QMO_Shortcode_Banner_Slider::kok_html( $linksiz, $ayar );
		qrms_assert_contains( 'data-autoplay="4500"', $html4, 'opsiyon verilmezse $ayar[autoplay] kullanılır' );
	}
);

qrms_test(
	'render_shortcode(): gerçek çalıştırma — payloadlar()+kok_html() zincirini kullanır, kısa kod autoplay niteliği ayarı ezer',
	function () {
		QMO_Banner_Slider_Settings::kaydet( array( 'oran' => '16:9', 'autoplay' => 4500 ) );
		qrms_banner_ek_kur( 503, 1600, 900 );
		qrms_banner_yayinla( 603, 503, 'Kış Kampanyası' );

		$html = QMO_Shortcode_Banner_Slider::render_shortcode( array() );
		qrms_assert_contains( 'qmo-banner-root', $html, 'kısa kod markup üretti' );
		qrms_assert_contains( 'data-autoplay="4500"', $html, 'nitelik verilmezse ayar kullanılır' );
		qrms_assert_contains( '<script src=', $html, 'ön yüzde betik basılır (betik=true varsayılan)' );

		$html2 = QMO_Shortcode_Banner_Slider::render_shortcode( array( 'autoplay' => '0' ) );
		qrms_assert_contains( 'data-autoplay="0"', $html2, 'kısa kod niteliği ayarı ezer' );

		// Görsel yoksa boş döner (mevcut sözleşme korunuyor).
		$GLOBALS['qrms_test']['post_types'] = array();
		qrms_assert_same( '', QMO_Shortcode_Banner_Slider::render_shortcode( array() ), 'banner yoksa boş dize' );
	}
);

/* ---------------------------------------------------------------------------
 * wp_ajax_qmo_banner_onizleme — canlı önizleme AJAX ucu
 * ------------------------------------------------------------------------ */

qrms_test(
	'ajax_banner_onizleme: yetkisiz kullanıcı 403 döner, renderer hiç çalışmaz',
	function () {
		$h = new RMA_Test_Banner_Onizleme_Harness();
		$GLOBALS['qrms_test']['can_map']['manage_options'] = false;
		$_POST['oran'] = '16:9';

		$h->ajax_banner_onizleme();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_false( $json['success'], 'yetkisiz istek reddedilir' );
		qrms_assert_same( 403, $json['status'], 'HTTP 403' );
		qrms_assert_true( ! isset( $json['data']['html'] ), 'renderer hiç çalışmadı — html alanı yok' );
	}
);

qrms_test(
	'ajax_banner_onizleme: geçersiz oran reddedilir (whitelist dışı değer kabul edilmez)',
	function () {
		$h = new RMA_Test_Banner_Onizleme_Harness();
		$GLOBALS['qrms_test']['can_map']['manage_options'] = true;
		$_POST['oran'] = '99:1';

		$h->ajax_banner_onizleme();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_false( $json['success'], 'geçersiz oran reddedilir' );
		qrms_assert_same( 400, $json['status'], 'HTTP 400' );
		qrms_assert_true( ! isset( $json['data']['html'] ), 'renderer çalışmadı' );
	}
);

qrms_test(
	'ajax_banner_onizleme: geçersiz oran_mobil reddedilir',
	function () {
		$h = new RMA_Test_Banner_Onizleme_Harness();
		$GLOBALS['qrms_test']['can_map']['manage_options'] = true;
		$_POST['oran_mobil'] = 'kirli-girdi';

		$h->ajax_banner_onizleme();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_false( $json['success'], 'geçersiz mobil oran reddedilir' );
		qrms_assert_same( 400, $json['status'], 'HTTP 400' );
	}
);

qrms_test(
	'ajax_banner_onizleme: oran_mobil_farkli yalnızca "0"/"1" kabul eder',
	function () {
		$h = new RMA_Test_Banner_Onizleme_Harness();
		$GLOBALS['qrms_test']['can_map']['manage_options'] = true;
		$_POST['oran_mobil_farkli'] = '2';

		$h->ajax_banner_onizleme();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_false( $json['success'], '0/1 dışı değer reddedilir' );
		qrms_assert_same( 400, $json['status'], 'HTTP 400' );
	}
);

qrms_test(
	'ajax_banner_onizleme: geçerli oran → html+durum döner, kok_html() gerçekten kullanılır, AYAR KAYDEDİLMEZ',
	function () {
		QMO_Banner_Slider_Settings::kaydet( array( 'oran' => '16:9', 'oran_mobil_farkli' => '0' ) );
		qrms_banner_ek_kur( 504, 1600, 900 );
		qrms_banner_yayinla( 604, 504, 'Yılbaşı Kampanyası' );

		$onceki_option = get_option( 'qmo_banner_slider_settings' );

		$h = new RMA_Test_Banner_Onizleme_Harness();
		$GLOBALS['qrms_test']['can_map']['manage_options'] = true;
		$_POST['oran']              = '4:3';
		$_POST['oran_mobil_farkli'] = '1';
		$_POST['oran_mobil']        = '1:1';

		$h->ajax_banner_onizleme();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_true( $json['success'], 'geçerli istek başarılı' );
		qrms_assert_true( isset( $json['data']['html'] ) && '' !== $json['data']['html'], 'html alanı dolu' );
		qrms_assert_true( isset( $json['data']['durum'] ), 'durum alanı var' );
		qrms_assert_contains( 'qmo-banner-root', $json['data']['html'], 'HTML gerçekten kok_html() çıktısı' );
		qrms_assert_false( strpos( $json['data']['html'], '<script' ) !== false, 'önizleme <script> basmaz (betik=false)' );
		qrms_assert_contains( '--qmo-banner-oran:4 / 3', $json['data']['html'], 'ÖVERRİDE oran (4:3) HTML üzerinde etkili — kaydedilmemiş değer render edildi' );

		// Ayar option'a YAZILMADI: değer aynı kaldı.
		qrms_assert_same( $onceki_option, get_option( 'qmo_banner_slider_settings' ), 'ayar KAYDEDİLMEDİ' );
		qrms_assert_same( '16:9', get_option( 'qmo_banner_slider_settings' )['oran'], 'kayıtlı oran hâlâ 16:9' );
	}
);

qrms_test(
	'ajax_banner_onizleme: kayıtlı ayarda mobil oran KAPALI iken, HENÜZ KAYDEDİLMEMİŞ mobil oran override\'ı önizlemede <picture> üretir (gerçek çalıştırma)',
	function () {
		// Kayıtlı ayar mobil oranı KAPALI tutuyor — kayıtlı ayarla render
		// edilseydi <picture> hiç basılmazdı; bu, override'ın GERÇEKTEN
		// kullanıldığının kanıtıdır.
		QMO_Banner_Slider_Settings::kaydet( array( 'oran' => '16:9', 'oran_mobil_farkli' => '0', 'oran_mobil' => '16:9' ) );

		$sizes = array_merge(
			qrms_banner_kirpma_kaydi( '16:9', 1000, 563 ),
			qrms_banner_kirpma_kaydi( '4:3', 1000, 750 )
		);
		qrms_banner_ek_kur( 505, 1000, 1000, $sizes );
		qrms_banner_yayinla( 605, 505 );

		$h = new RMA_Test_Banner_Onizleme_Harness();
		$GLOBALS['qrms_test']['can_map']['manage_options'] = true;
		$_POST['oran_mobil_farkli'] = '1';
		$_POST['oran_mobil']        = '4:3';

		$h->ajax_banner_onizleme();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_true( $json['success'], 'istek başarılı' );
		qrms_assert_contains( '<picture class="qmo-banner-picture">', $json['data']['html'], 'kaydedilmemiş mobil oran önizlemede etkili — <picture> basıldı' );

		// Kayıtlı ayar bu istekten ETKİLENMEDİ.
		qrms_assert_same( '0', (string) QMO_Banner_Slider_Settings::get()['oran_mobil_farkli'], 'kayıtlı ayar değişmedi' );
	}
);

qrms_test(
	'ajax_banner_onizleme: natif mobil oranlı kaynak — kırpma dosyası üretilmeden mobil kaynak orijinalden çözülür (SENARYO 1 ile aynı veri, uçtan uca)',
	function () {
		QMO_Banner_Slider_Settings::kaydet( array( 'oran' => '16:9', 'oran_mobil_farkli' => '1', 'oran_mobil' => '4:3' ) );

		// Kaynak 1200x900 = tam 4:3; yalnızca 16:9 kırpması var.
		qrms_banner_ek_kur( 506, 1200, 900, qrms_banner_kirpma_kaydi( '16:9', 1200, 675 ) );
		qrms_banner_yayinla( 606, 506 );

		$h = new RMA_Test_Banner_Onizleme_Harness();
		$GLOBALS['qrms_test']['can_map']['manage_options'] = true;

		$h->ajax_banner_onizleme();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_true( $json['success'], 'istek başarılı' );
		qrms_assert_contains( '<picture', $json['data']['html'], 'natif mobil oranlı kaynak yine de <picture> ile sunulur (orijinal dosya)' );
		qrms_assert_same( 'hazir', $json['data']['durum'], 'kırpma bekleyen yok — 4:3 zaten uygun, 16:9 hazır' );
	}
);

qrms_test(
	'ajax_banner_onizleme: mobil kırpma EKSİK — kaynak mobil oranda değil, <picture> hiç basılmaz ve durum bekliyor döner; kırpma dosyası ÜRETİLMEZ',
	function () {
		QMO_Banner_Slider_Settings::kaydet( array( 'oran' => '16:9', 'oran_mobil_farkli' => '1', 'oran_mobil' => '4:3' ) );

		// Kare kaynak, YALNIZCA 16:9 kırpması var — 4:3 EKSİK.
		qrms_banner_ek_kur( 507, 1000, 1000, qrms_banner_kirpma_kaydi( '16:9', 1000, 563 ) );
		qrms_banner_yayinla( 607, 507 );

		$h = new RMA_Test_Banner_Onizleme_Harness();
		$GLOBALS['qrms_test']['can_map']['manage_options'] = true;

		$h->ajax_banner_onizleme();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_true( $json['success'], 'istek başarılı (kırpma eksikliği hata değildir)' );
		qrms_assert_false( strpos( $json['data']['html'], '<picture' ) !== false, 'uymayan dosya kullanılmaz — <picture> basılmaz' );
		qrms_assert_same( 'bekliyor', $json['data']['durum'], 'kırpma bekleyen olarak işaretlenir' );

		// Kırpma dosyası ÜRETİLMEDİ — meta hâlâ sadece 16:9 içeriyor.
		$meta = $GLOBALS['qrms_test']['attachment_meta'][507];
		qrms_assert_true( ! isset( $meta['sizes'][ QMO_Banner_Kirpma::boyut_adi( '4:3' ) ] ), 'AJAX önizleme kırpma dosyası ÜRETMEDİ' );
	}
);

qrms_test(
	'ajax_banner_onizleme: kaynak kodda ikinci bir HTML üretim yolu yok, kırpma üretimi/ayar kaydı çağrılmaz, nonce capability\'den önce kontrol edilir',
	function () {
		$src = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-kampanya-banner-admin.php' );

		$fonksiyon_basi = strpos( $src, 'public function ajax_banner_onizleme()' );
		qrms_assert_true( false !== $fonksiyon_basi, 'fonksiyon var' );

		$fonksiyon_sonu = strpos( $src, "\n    }\n", $fonksiyon_basi );
		$govde          = substr( $src, $fonksiyon_basi, $fonksiyon_sonu - $fonksiyon_basi );

		qrms_assert_contains( 'QMO_Shortcode_Banner_Slider::payloadlar(', $govde, 'TEK renderer: payloadlar() kullanılır' );
		qrms_assert_contains( 'QMO_Shortcode_Banner_Slider::kok_html(', $govde, 'TEK renderer: kok_html() kullanılır' );
		qrms_assert_false( strpos( $govde, 'ob_start(' ) !== false, 'ikinci bir HTML üretimi (kendi ob_start) yok' );
		qrms_assert_false( strpos( $govde, '::kaydet(' ) !== false, 'ayar kaydedilmiyor' );
		qrms_assert_false( strpos( $govde, 'update_option(' ) !== false, 'option doğrudan yazılmıyor' );
		qrms_assert_false( strpos( $govde, '::kirp(' ) !== false, 'kırpma üretimi yok' );
		qrms_assert_false( strpos( $govde, 'banner_kirp(' ) !== false, 'toplu kırpma üretimi yok' );

		$nonce_pos = strpos( $govde, 'check_ajax_referer' );
		$cap_pos   = strpos( $govde, 'current_user_can' );
		qrms_assert_true( false !== $nonce_pos && false !== $cap_pos && $nonce_pos < $cap_pos, 'nonce kontrolü capability kontrolünden ÖNCE' );
	}
);

qrms_test(
	'wp_ajax_qmo_banner_onizleme kaydı qr-menu.php\'de doğru handler\'a bağlı',
	function () {
		$boot = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' );
		qrms_assert_contains( "add_action( 'wp_ajax_qmo_banner_onizleme', [ \$this, 'ajax_banner_onizleme' ] );", $boot, 'AJAX kancası kayıtlı' );
		qrms_assert_false( strpos( $boot, 'wp_ajax_nopriv_qmo_banner_onizleme' ) !== false, 'önizleme ucu herkese açık DEĞİL' );
	}
);

qrms_test(
	'banner preview iframe core: buildIframeDocument tek üretim yolu (viewport, varlıklar, min-height yok)',
	function () {
		$core_path = QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/banner-preview-iframe-core.js';
		$js        = file_get_contents( $core_path );
		$admin_js  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/admin-ui.js' );

		qrms_assert_contains( 'function buildIframeDocument', $js, 'core dosyası mevcut' );
		qrms_assert_contains( 'QMO_BANNER_PREVIEW_IFRAME_CORE', $admin_js, 'admin-ui core kullanır' );
		qrms_assert_false( strpos( $js, 'min-height:100vh' ) !== false, 'body min-height kaldırıldı' );
		qrms_assert_false( method_exists( 'QMO_Shortcode_Banner_Slider', 'onizleme_belgesi' ), 'ölü PHP renderer kaldırıldı' );

		$node = trim( (string) shell_exec( 'command -v node' ) );
		if ( '' === $node ) {
			echo "\033[33m    (Node yok — H1 yükseklik regresyonu atlandı; saf Node testi: tests/banner-preview-iframe-height.mjs)\033[0m\n";
			return;
		}

		$test_script = QRMS_PLUGIN_DIR . 'tests/banner-preview-iframe-height.mjs';
		$cmd         = escapeshellarg( $node ) . ' ' . escapeshellarg( $test_script ) . ' 2>&1';
		$out         = shell_exec( $cmd );
		qrms_assert_contains( 'banner-preview-iframe-height: OK', (string) $out, 'H1 iframe yüksekliği regresyonu (saf Node, mock DOM)' );
	}
);

qrms_test(
	'wp_ajax_qmo_banner_kampanya_olustur kaydı ve CPT olustur_kayit',
	function () {
		$boot  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' );
		$banner = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-kampanya-banner-admin.php' );
		$cpt   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/admin-cpt-banner.php' );

		qrms_assert_contains( "add_action( 'wp_ajax_qmo_banner_kampanya_olustur', [ \$this, 'ajax_banner_kampanya_olustur' ] );", $boot, 'AJAX kancası' );
		qrms_assert_contains( 'check_ajax_referer( $this->banner_kampanya_olustur_nonce_action', $banner, 'nonce' );
		qrms_assert_contains( 'QMO_Banner_CPT::olustur_kayit', $banner, 'CPT oluşturma helper' );
		qrms_assert_contains( 'public static function olustur_kayit', $cpt, 'olustur_kayit tanımlı' );
	}
);

qrms_test(
	'oran UX metinleri: ux_baslik ve piksel etiketi',
	function () {
		$oranlar = QMO_Banner_Slider_Settings::oranlar();

		qrms_assert_same( 'Geniş Banner', $oranlar['16:9']['ux_baslik'], '16:9 UX başlık' );
		qrms_assert_same( 'Ultra Geniş', $oranlar['21:9']['ux_baslik'], '21:9 UX başlık' );
		qrms_assert_contains( '1600×900', QMO_Banner_Slider_Settings::oran_px_etiketi( '16:9' ), 'piksel etiketi' );
	}
);

qrms_test(
	'banner admin UX: geniş önizleme sütunu ve pending durumu',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/admin-ui.css' );
		$js  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/admin-ui.js' );

		qrms_assert_contains( 'minmax(560px, 720px)', $css, 'geniş banner önizleme sütunu' );
		qrms_assert_contains( 'is-pending', $css, 'pending stili' );
		qrms_assert_contains( 'setPreviewPending', $js, 'pending JS' );
		qrms_assert_contains( 'initBannerOranSecici', $js, 'oran kart JS' );
	}
);

qrms_test(
	'bekleyen_sayisi_oranlar(): aynı kayıt iki oran için bekliyorsa bir kez sayılır',
	function () {
		QMO_Banner_Slider_Settings::kaydet( array( 'oran' => '16:9', 'oran_mobil_farkli' => '1', 'oran_mobil' => '4:3' ) );
		// İki oran için de kırpma yok → bekleyen_sayisi(oran) her oranda +1 (toplam 2).
		qrms_banner_ek_kur( 508, 1000, 1000 );
		qrms_banner_yayinla( 608, 508 );

		$banners = QMO_Banner_CPT::get_published_banners();
		$tek     = QMO_Banner_Kirpma::bekleyen_sayisi_oranlar( array( '16:9', '4:3' ), $banners );
		$toplam  = QMO_Banner_Kirpma::bekleyen_sayisi( '16:9', $banners ) + QMO_Banner_Kirpma::bekleyen_sayisi( '4:3', $banners );

		qrms_assert_same( 1, $tek, 'kayıt başına bir' );
		qrms_assert_same( 2, $toplam, 'oran başına toplam iki (çift sayım farkı)' );
		qrms_assert_true( $tek < $toplam, 'optimize sayım çift sayımı engeller' );
	}
);

qrms_test(
	'ajax_banner_onizleme: show_title POST override kaydedilmeden HTML\'e yansır',
	function () {
		QMO_Banner_Slider_Settings::kaydet( array( 'show_title' => 0, 'oran' => '16:9' ) );
		qrms_banner_ek_kur( 509, 1600, 900 );
		qrms_banner_yayinla( 609, 509, 'Başlık Test' );

		$h = new RMA_Test_Banner_Onizleme_Harness();
		$GLOBALS['qrms_test']['can_map']['manage_options'] = true;
		$_POST['show_title'] = '1';

		$h->ajax_banner_onizleme();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_true( $json['success'], 'istek başarılı' );
		qrms_assert_contains( 'qmo-banner-caption', $json['data']['html'], 'başlık açıkken caption basılır' );
		qrms_assert_same( 0, (int) QMO_Banner_Slider_Settings::get()['show_title'], 'kayıtlı ayar değişmedi' );
	}
);

qrms_test(
	'Faz 3: admin banner önizlemesi iframe + nonce + AJAX bağlantısı kodda mevcut',
	function () {
		$modul = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/module.php' );
		$trait = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-kampanya-banner-admin.php' );
		$js    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/admin-ui.js' );

		qrms_assert_contains( 'QMO_BANNER_PREVIEW', $modul, 'nonce/config wp_localize_script ile' );
		qrms_assert_contains( "wp_create_nonce( 'qmo_banner_onizleme' )", $modul, 'doğru nonce eylemi' );
		qrms_assert_contains( 'qmo-banner-preview-iframe', $trait, 'iframe önizleme' );
		qrms_assert_contains( 'buildIframeDocument', $js, 'gerçek frontend belgesi' );
		qrms_assert_contains( 'scheduleAjaxPreview', $js, 'debounce AJAX' );
		qrms_assert_contains( 'setPreviewDurum', $js, 'AJAX hata geri bildirimi' );
		qrms_assert_contains( '.fail(function', $js, 'AJAX fail işleyicisi' );
		qrms_assert_false( strpos( $js, "setAttribute( 'data-gecis'" ) !== false, 'ölü data-gecis yazımı yok' );
		qrms_assert_false( strpos( $js, "setAttribute( 'data-autoplay'" ) !== false, 'ölü data-autoplay yazımı yok' );
	}
);

qrms_test(
	'WP_Query stub: menu_order ASC sonra ID sıralaması banner sorgusuna uyar',
	function () {
		$GLOBALS['qrms_test']['post_types'][901] = QMO_Banner_CPT::POST_TYPE;
		$GLOBALS['qrms_test']['post_types'][902] = QMO_Banner_CPT::POST_TYPE;
		$GLOBALS['qrms_test']['post_status'][901] = 'publish';
		$GLOBALS['qrms_test']['post_status'][902] = 'publish';
		$GLOBALS['qrms_test']['menu_order'][901] = 5;
		$GLOBALS['qrms_test']['menu_order'][902] = 2;

		$q = new WP_Query(
			array(
				'post_type'   => QMO_Banner_CPT::POST_TYPE,
				'post_status' => array( 'publish' ),
				'orderby'     => array(
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				),
			)
		);

		qrms_assert_same( 902, (int) $q->posts[0]->ID, 'düşük menu_order önce' );
		qrms_assert_same( 901, (int) $q->posts[1]->ID, 'yüksek menu_order sonra' );
	}
);


/* ---------------------------------------------------------------------------
 * 24. HFB — "Yeni Blok Ekle" listesi, canlı önizleme yükü, önbellek temizliği
 * ------------------------------------------------------------------------ */

