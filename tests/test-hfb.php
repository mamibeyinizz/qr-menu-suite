<?php
/**
 * Header Footer Builder testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/class-header-footer-builder.php';

echo "\nHeader Footer Builder\n";

/**
 * Modül örneği (hook kaydı yapmadan).
 *
 * @return QRMS_Header_Footer_Builder
 */
function qrms_hfb() {
	return new QRMS_Header_Footer_Builder();
}

/**
 * Çeviri modülünün dil seçici kısa kodunu taklit eder.
 *
 * @return void
 */
function qrms_hfb_fake_lang_shortcode() {
	add_shortcode(
		'qrmenu_flags_only',
		function () {
			return '<div class="qrmenu-lang-dropdown qrmenu-flags-only-mod">TR-BAYRAK</div>';
		}
	);
}

qrms_test(
	'modül loader sözleşmesine uyar: slug, dosya ve init fonksiyonu',
	function () {
		qrms_assert_true(
			in_array( 'header-footer-builder', QRMS_Helpers::MODULE_SLUGS, true ),
			'slug bilinen modüller arasında'
		);
		qrms_assert_true( QRMS_Module_Loader::module_file_exists( 'header-footer-builder' ), 'module.php diskte' );
		qrms_assert_same(
			'qrms_module_header_footer_builder_init',
			QRMS_Module_Loader::get_init_function( 'header-footer-builder' ),
			'init fonksiyon adı'
		);

		update_option( 'qrms_active_modules', array( 'header-footer-builder' ) );

		qrms_assert_same(
			array( 'header-footer-builder' ),
			QRMS_Module_Loader::load_modules(),
			'aktifken yüklenir'
		);
	}
);

qrms_test(
	'tek sabit tasarım basılır: QR marka, iki satır, mobil panel',
	function () {
		$hfb    = qrms_hfb();
		$header = $hfb->render_header( $hfb->get_header_options() );
		$footer = $hfb->render_footer( $hfb->get_footer_options() );

		qrms_assert_contains( 'hfb-header-wrap', $header, 'header sarmalayıcı' );
		qrms_assert_contains( 'hfb-brand__mark', $header, 'QR kod ikonu' );
		qrms_assert_contains( 'QR MENU', $header, 'marka üst satırı' );
		qrms_assert_contains( 'OFFİCİAL', $header, 'marka alt satırı' );
		qrms_assert_contains( 'hfb-header__toggle', $header, 'hamburger düğmesi' );
		qrms_assert_contains( 'hfb-mobile-panel', $header, 'mobil panel' );
		qrms_assert_contains( 'hfb-footer-wrap', $footer, 'footer sarmalayıcı' );
	}
);

qrms_test(
	'eski varyant sınıfları basılmaz; tasarım anahtarları artık varsayılanlarla gelir',
	function () {
		$hfb  = qrms_hfb();
		$opts = $hfb->get_header_options();

		qrms_assert_true( ! array_key_exists( 'variant', $opts ), 'varyant ayarı yok' );
		qrms_assert_true( ! array_key_exists( 'mobile_panel_gradient_start', $opts ), 'eski gradient yok' );
		qrms_assert_same( '#0a0a0c', $opts['bg_color'], 'varsayılan zemin' );
		qrms_assert_same( '#c9a84c', $opts['icon_color'], 'varsayılan ikon rengi' );
		qrms_assert_same( 160, (int) $opts['logo_width_desktop'], 'varsayılan logo genişliği' );

		qrms_assert_true(
			! array_key_exists( 'variant', $hfb->get_footer_options() ),
			'footer varyantı yok'
		);

		$header = $hfb->render_header( $opts );
		foreach ( array( 'minimal-sticky', 'glass-bento', 'kinetic-bold', 'menulux' ) as $eski ) {
			qrms_assert_true(
				false === strpos( $header, 'hfb-header--' . $eski ),
				$eski . ' varyant sınıfı basılmıyor'
			);
		}

		qrms_assert_contains( '--hfb-header-bg:#0a0a0c', $header, 'CSS değişkeni basılır' );
	}
);

qrms_test(
	'eski kurulumun varyant anahtarları budanır, geçerli renk korunur',
	function () {
		update_option(
			'hfb_header_options',
			array(
				'variant'                     => 'menulux',
				'bg_color'                    => '#ffffff',
				'mobile_panel_gradient_start' => '#e91e8c',
				'logo_width'                  => 240,
				'menu_id'                     => 7,
			)
		);

		$hfb  = qrms_hfb();
		$opts = $hfb->get_header_options();

		qrms_assert_true( ! array_key_exists( 'variant', $opts ), 'variant budandı' );
		qrms_assert_true( ! array_key_exists( 'mobile_panel_gradient_start', $opts ), 'gradient budandı' );
		qrms_assert_true( ! array_key_exists( 'logo_width', $opts ), 'eski tekil logo_width budandı' );
		qrms_assert_same( '#ffffff', $opts['bg_color'], 'geçerli zemin rengi taşındı' );
		qrms_assert_same( 7, (int) $opts['menu_id'], 'korunan ayar taşındı' );
		qrms_assert_same( 'QR MENU', $opts['brand_line1'], 'yeni alan varsayılana düştü' );

		$hfb->save_settings( array( 'hfb_header_menu_id' => '7' ) );
		qrms_assert_true(
			! array_key_exists( 'variant', get_option( 'hfb_header_options' ) ),
			'kayıtta da yok'
		);
		qrms_assert_same( '#ffffff', get_option( 'hfb_header_options' )['bg_color'], 'renk kayıtta durur' );
	}
);

qrms_test(
	'sticky kapatılabilir; tasarımın kalanı değişmez',
	function () {
		$hfb  = qrms_hfb();
		$opts = $hfb->get_header_options();

		qrms_assert_contains( 'hfb-header--sticky', $hfb->render_header( $opts ), 'varsayılan sticky' );

		$opts['sticky'] = 0;
		$html           = $hfb->render_header( $opts );

		qrms_assert_true( false === strpos( $html, 'hfb-header--sticky' ), 'kapalıyken sınıf yok' );
		qrms_assert_contains( 'hfb-brand__mark', $html, 'marka yerinde' );
	}
);

qrms_test(
	'sosyal ikonlar yalnızca URL girilmiş platformlar için basılır',
	function () {
		$hfb  = qrms_hfb();
		$opts = $hfb->get_header_options();

		qrms_assert_same( array( 'facebook', 'x', 'youtube' ), $opts['social_media_active'], 'varsayılan üçlü' );
		qrms_assert_true(
			false === strpos( $hfb->render_header( $opts ), 'hfb-social__link' ),
			'URL yokken ikon basılmaz'
		);

		$opts['social_media'] = array(
			'facebook' => 'https://facebook.com/qrmenu',
			'youtube'  => 'https://youtube.com/@qrmenu',
		);

		$html = $hfb->render_header( $opts );

		qrms_assert_contains( 'hfb-social__link--facebook', $html, 'facebook ikonu' );
		qrms_assert_contains( 'hfb-social__link--youtube', $html, 'youtube ikonu' );
		qrms_assert_true( false === strpos( $html, 'hfb-social__link--x"' ), 'URL girilmemiş X basılmaz' );
	}
);

qrms_test(
	'dil seçici: çeviri modülü yokken sessizce çıkmaz',
	function () {
		$hfb  = qrms_hfb();
		$opts = $hfb->get_header_options();

		qrms_assert_same( 1, (int) $opts['lang_show'], 'toggle varsayılan açık' );
		qrms_assert_same( 1, (int) $opts['lang_mobile_show'], 'mobil toggle varsayılan açık' );
		qrms_assert_true( ! $hfb->lang_switcher_available(), 'kısa kod kayıtlı değil' );
		qrms_assert_same( '', $hfb->render_lang_switcher( $opts ), 'çıktı boş' );
		qrms_assert_true(
			false === strpos( $hfb->render_header( $opts ), 'hfb-lang' ),
			'header\'da dil kabı yok'
		);
	}
);

qrms_test(
	'dil seçici açıkken hem masaüstü hem mobil panelde görünür',
	function () {
		qrms_hfb_fake_lang_shortcode();

		$hfb       = qrms_hfb();
		$opts      = $hfb->get_header_options();
		$hamburger = $hfb->get_hamburger_options();

		// Panelde bayrak, dinamik blok modelinde "Dil Seçici" bloğu
		// eklendiğinde görünür; header sağ ucu lang_show'a bağlıdır.
		$hamburger['blocks'][] = array(
			'id'      => 'blk_lang',
			'type'    => 'lang',
			'enabled' => true,
			'align'   => 'center',
		);

		qrms_assert_true( $hfb->lang_switcher_available(), 'kısa kod bulundu' );

		$html = $hfb->render_header( $opts, $hamburger );

		qrms_assert_same( 3, substr_count( $html, 'TR-BAYRAK' ), 'masaüstü sağ ucu + mobil header + mobil panel' );
		qrms_assert_contains( 'hfb-header__actions', $html, 'sağ blok' );
		qrms_assert_contains( 'hfb-header__lang-mobile', $html, 'mobil header bayrağı' );
		qrms_assert_contains( 'hfb-mobile-panel__block--lang', $html, 'mobil paneldeki dil bloğu' );
	}
);

qrms_test(
	'mobil header bayrağı ayrı toggle ile kapatılabilir',
	function () {
		qrms_hfb_fake_lang_shortcode();

		$hfb                        = qrms_hfb();
		$opts                       = $hfb->get_header_options();
		$opts['lang_mobile_show']   = 0;

		$html = $hfb->render_header( $opts );

		qrms_assert_true( false === strpos( $html, 'hfb-header__lang-mobile' ), 'mobil header bayrağı yok' );
		qrms_assert_same( 1, substr_count( $html, 'TR-BAYRAK' ), 'yalnızca masaüstü sağ ucu' );
	}
);

qrms_test(
	'dil toggle kapalıyken masaüstü header\'da bayrak görünmez',
	function () {
		qrms_hfb_fake_lang_shortcode();

		$hfb                        = qrms_hfb();
		$opts                       = $hfb->get_header_options();
		$opts['lang_show']          = 0;
		$opts['lang_mobile_show']   = 0;

		$html = $hfb->render_header( $opts );

		qrms_assert_true( false === strpos( $html, 'TR-BAYRAK' ), 'masaüstünde yok' );
		qrms_assert_true( false === strpos( $html, 'hfb-mobile-panel__block--lang' ), 'mobilde lang bloğu yok' );
	}
);

qrms_test(
	'toggle, kayıtta ve önizlemede aynı biçimde çözülür',
	function () {
		$hfb = qrms_hfb();

		$acik = $hfb->sanitize_header_input(
			array(
				'hfb_lang_show'        => '1',
				'hfb_lang_mobile_show' => '1',
			),
			$hfb->get_header_options()
		);
		qrms_assert_same( 1, $acik['lang_show'], 'işaretliyken 1' );
		qrms_assert_same( 1, $acik['lang_mobile_show'], 'mobil işaretliyken 1' );

		// Onay kutusu işaretsizken tarayıcı alanı hiç göndermez.
		$kapali = $hfb->sanitize_header_input( array(), $hfb->get_header_options() );
		qrms_assert_same( 0, $kapali['lang_show'], 'işaretsizken 0' );
		qrms_assert_same( 0, $kapali['lang_mobile_show'], 'mobil işaretsizken 0' );
		qrms_assert_same( 0, $kapali['sticky'], 'sticky de aynı kuralla' );
	}
);

qrms_test(
	'önizleme ile kayıt aynı temizleyiciden geçer, çıktı birebir aynı',
	function () {
		$hfb   = qrms_hfb();
		$girdi = array(
			'hfb_header_brand_line1'               => '  Deneme Marka  ',
			'hfb_header_brand_line2'               => 'ALT SATIR',
			'hfb_header_sticky'                    => '1',
			'hfb_lang_show'                        => '1',
			'hfb_header_social_media_active'       => array( 'facebook' ),
			'hfb_header_social_media_url_facebook' => 'https://facebook.com/deneme',
			'hfb_footer_copyright'                 => '© 2026 Deneme',
			'hfb_footer_email'                     => 'bilgi@deneme.test',
			'hfb_hamburger_block_order'            => 'blk_1,blk_2,blk_3,blk_4',
			'hfb_hamburger_blocks'                 => array(
				'blk_1' => array(
					'type'    => 'logo',
					'enabled' => '1',
					'align'   => 'center',
				),
				'blk_2' => array(
					'type'    => 'menu',
					'enabled' => '1',
					'align'   => 'center',
				),
				'blk_3' => array(
					'type'    => 'social',
					'enabled' => '1',
					'align'   => 'center',
				),
				'blk_4' => array(
					'type'    => 'text',
					'enabled' => '0',
					'align'   => 'center',
					'content' => '',
				),
			),
		);

		$header_in    = $hfb->sanitize_header_input( $girdi, $hfb->get_header_options() );
		$hamburger_in = $hfb->sanitize_hamburger_input( $girdi, $hfb->get_hamburger_options() );
		$onizleme     = $hfb->render_header( $header_in, $hamburger_in );

		$hfb->save_settings( $girdi );
		$kayitli = $hfb->render_header( $hfb->get_header_options(), $hfb->get_hamburger_options() );

		qrms_assert_same( $onizleme, $kayitli, 'önizleme ve kayıt aynı HTML' );
		qrms_assert_contains( 'Deneme Marka', $kayitli, 'marka kaydedildi' );
		qrms_assert_contains( 'hfb-social__link--facebook', $kayitli, 'sosyal bağlantı kaydedildi' );

		$footer = $hfb->render_footer( $hfb->get_footer_options() );
		qrms_assert_contains( '© 2026 Deneme', $footer, 'telif kaydedildi' );
		qrms_assert_contains( 'bilgi@deneme.test', $footer, 'e-posta kaydedildi' );
	}
);

qrms_test(
	'aynı istekte ikinci kez render edilmez (Elementor çift çıktı freni)',
	function () {
		$hfb = qrms_hfb();

		qrms_assert_true( $hfb->should_render( 'header' ), 'ilk çağrı serbest' );
		$hfb->mark_rendered( 'header' );
		qrms_assert_true( ! $hfb->should_render( 'header' ), 'ikinci çağrı engellenir' );
		qrms_assert_true( $hfb->should_render( 'footer' ), 'footer ayrı sayılır' );

		// Elementor yüklü değilken uyumluluk kontrolleri sessizce false döner.
		qrms_assert_true( ! $hfb->elementor_loaded(), 'Elementor yok' );
		qrms_assert_true( ! $hfb->elementor_is_edit_mode(), 'editör modu değil' );
		qrms_assert_true( ! $hfb->theme_location_has_template( 'header' ), 'Theme Builder şablonu yok' );
	}
);

qrms_test(
	'AJAX önizleme uç noktası header ve footer\'ı birlikte döndürür',
	function () {
		$hfb = qrms_hfb();

		$_POST = array(
			'nonce' => 'test',
			'data'  => array(
				'hfb_header_brand_line1' => 'Önizleme Marka',
				'hfb_header_menu_id'     => '7',
				'hfb_footer_copyright'   => '© 2026 Önizleme',
			),
		);

		$hfb->ajax_preview();
		$yanit = $GLOBALS['qrms_test']['json'];

		qrms_assert_true( is_array( $yanit ) && ! empty( $yanit['success'] ), 'başarılı yanıt' );
		qrms_assert_contains( 'Önizleme Marka', $yanit['data']['header'], 'header taze veriyle döndü' );
		qrms_assert_contains( '© 2026 Önizleme', $yanit['data']['footer'], 'footer taze veriyle döndü' );

		// Önizleme hiçbir şeyi kaydetmez.
		qrms_assert_same( 'QR MENU', $hfb->get_header_options()['brand_line1'], 'depo değişmedi' );
	}
);

qrms_test(
	'aynı menü iki kez basılır ama id\'ler çakışmaz',
	function () {
		$hfb             = qrms_hfb();
		$h               = $hfb->get_header_options();
		$h['menu_id']    = 7;
		$f               = $hfb->get_footer_options();
		$f['menu_id']    = 7;

		$html = $hfb->render_header( $h ) . $hfb->render_footer( $f );

		preg_match_all( '/\bid="([^"]+)"/', $html, $eslesme );
		$idler = $eslesme[1];

		qrms_assert_same( count( $idler ), count( array_unique( $idler ) ), 'tekrar eden id yok' );
		qrms_assert_contains( 'hfb-h-menu-item-101', $html, 'masaüstü menüsü kendi id alanında' );
		qrms_assert_contains( 'hfb-m-menu-item-101', $html, 'mobil panel kendi id alanında' );
		qrms_assert_contains( 'hfb-f-menu-item-101', $html, 'footer kendi id alanında' );
	}
);

qrms_test(
	'kısa kod kaydı rehbere düşer',
	function () {
		update_option( 'qrms_active_modules', array( 'header-footer-builder' ) );
		qrms_hfb()->register_hooks();

		$gruplar = QRMS_Shortcodes::all();
		qrms_assert_true( isset( $gruplar['header-footer-builder'] ), 'modül kayıtlı' );
		$kodlar = $gruplar['header-footer-builder'];
		qrms_assert_same( 2, count( $kodlar ), 'iki kısa kod' );
		qrms_assert_same( 'hfb_header', $kodlar[0]['tag'], 'header tag' );
		qrms_assert_same( 'hfb_footer', $kodlar[1]['tag'], 'footer tag' );
	}
);

qrms_test(
	'logo boyutu aralığa sıkışır; otomatik yükseklik 0 yazar',
	function () {
		$hfb = qrms_hfb();

		$temiz = $hfb->sanitize_header_input(
			array(
				'hfb_header_logo_width_desktop'       => '999',
				'hfb_header_logo_width_mobile'        => '40',
				'hfb_header_logo_height_auto_desktop' => '1',
				'hfb_header_logo_height_auto_tablet'  => '1',
				'hfb_header_logo_height_auto_mobile'  => '',
				'hfb_header_logo_height_mobile'       => '80',
				'hfb_header_sticky'                   => '1',
				'hfb_lang_show'                       => '1',
			),
			$hfb->get_header_options()
		);

		qrms_assert_same( 320, (int) $temiz['logo_width_desktop'], 'üst sınır' );
		qrms_assert_same( 80, (int) $temiz['logo_width_mobile'], 'alt sınır' );
		qrms_assert_same( 1, (int) $temiz['logo_height_auto_desktop'], 'otomatik açık' );
		qrms_assert_same( 0, (int) $temiz['logo_height_desktop'], 'otomatikte yükseklik 0' );
		qrms_assert_same( 0, (int) $temiz['logo_height_auto_mobile'], 'otomatik kapalı' );
		qrms_assert_same( 80, (int) $temiz['logo_height_mobile'], 'sabit yükseklik' );
	}
);

qrms_test(
	'geçersiz renk varsayılana düşer; geçerli hex korunur',
	function () {
		$hfb = qrms_hfb();
		$cur = $hfb->get_header_options();

		$kotu = $hfb->sanitize_header_input(
			array(
				'hfb_header_bg_color'   => 'red',
				'hfb_header_icon_color' => '#abc',
				'hfb_header_sticky'     => '1',
				'hfb_lang_show'         => '1',
			),
			$cur
		);

		qrms_assert_same( '#0a0a0c', $kotu['bg_color'], 'geçersiz renk reddedildi' );
		qrms_assert_same( '#abc', $kotu['icon_color'], '3 haneli hex kabul' );
	}
);

qrms_test(
	'sticky blur sınıfı yalnızca sticky açıkken basılır',
	function () {
		$hfb  = qrms_hfb();
		$opts = $hfb->get_header_options();

		$opts['sticky']      = 1;
		$opts['sticky_blur'] = 1;
		qrms_assert_contains( 'hfb-header--sticky-blur', $hfb->render_header( $opts ), 'blur sınıfı' );

		$opts['sticky'] = 0;
		qrms_assert_true(
			false === strpos( $hfb->render_header( $opts ), 'hfb-header--sticky-blur' ),
			'sticky kapalıyken blur yok'
		);
	}
);

qrms_test(
	'hamburger blok sırası ve görünürlük panele yansır',
	function () {
		$hfb       = qrms_hfb();
		$header    = $hfb->get_header_options();
		$hamburger = $hfb->get_hamburger_options();

		$header['social_media']        = array( 'instagram' => 'https://instagram.com/x' );
		$header['social_media_active'] = array( 'instagram' );
		$header['cta_phone']           = '0850 000 00 00';

		// Dinamik blok modeli: sıra dizinin kendi sırasıdır, görünürlük
		// blok başına `enabled` alanıdır.
		$hamburger['blocks'] = array(
			array(
				'id'      => 'blk_1',
				'type'    => 'text',
				'enabled' => true,
				'align'   => 'center',
				'content' => '<p>Açık büfe</p>',
			),
			array(
				'id'      => 'blk_2',
				'type'    => 'social',
				'enabled' => true,
				'align'   => 'center',
			),
			array(
				'id'      => 'blk_3',
				'type'    => 'logo',
				'enabled' => true,
				'align'   => 'center',
			),
			array(
				'id'      => 'blk_4',
				'type'    => 'menu',
				'enabled' => false,
				'align'   => 'center',
			),
			array(
				'id'          => 'blk_5',
				'type'        => 'button',
				'enabled'     => true,
				'align'       => 'center',
				'label'       => 'Rezervasyon',
				'url'         => 'https://ornek.test/rezervasyon',
				'bg_color'    => '#c9a84c',
				'text_color'  => '#0a0a0c',
				'shape'       => 'pill',
				'font'        => 'Playfair Display',
				'font_size'   => 15,
				'font_weight' => 600,
			),
		);

		$html = $hfb->render_header( $header, $hamburger );

		qrms_assert_contains( 'hfb-mobile-panel__block--text', $html, 'metin bloğu' );
		qrms_assert_contains( 'Açık büfe', $html, 'metin içeriği' );
		qrms_assert_contains( 'hfb-mobile-panel__block--social', $html, 'sosyal blok' );
		qrms_assert_contains( 'hfb-mobile-panel__block--logo', $html, 'logo blok' );
		qrms_assert_true( false === strpos( $html, 'hfb-mobile-panel__block--menu' ), 'kapalı menü yok' );
		qrms_assert_contains( 'hfb-cta', $html, 'telefon CTA blok sırasının dışında altta' );
		qrms_assert_contains( 'hfb-mobile-panel__btn', $html, 'buton bloğu' );
		qrms_assert_contains( 'Rezervasyon', $html, 'buton metni' );

		$text_pos   = strpos( $html, 'hfb-mobile-panel__block--text' );
		$social_pos = strpos( $html, 'hfb-mobile-panel__block--social' );
		$logo_pos   = strpos( $html, 'hfb-mobile-panel__block--logo' );
		qrms_assert_true( $text_pos < $social_pos && $social_pos < $logo_pos, 'blok sırası text-social-logo' );
	}
);

qrms_test(
	'sanitize_hamburger_input blok sırasını, hizayı, fontu ve metni temizler',
	function () {
		$hfb = qrms_hfb();

		$temiz = $hfb->sanitize_hamburger_input(
			array(
				'hfb_hamburger_block_order'         => 'blk_4,hack,blk_1,blk_1,blk_2',
				'hfb_hamburger_blocks'              => array(
					'blk_1' => array(
						'type'    => 'logo',
						'enabled' => '1',
						'align'   => 'left',
					),
					'blk_2' => array(
						'type'    => 'menu',
						'enabled' => '0',
						'align'   => 'center',
					),
					'blk_3' => array(
						'type'    => 'social',
						'enabled' => '1',
						'align'   => 'center',
					),
					'blk_4' => array(
						'type'    => 'text',
						'enabled' => '1',
						'align'   => 'justify',
						'content' => '<p>Merhaba</p><script>x</script>',
					),
					'blk_5' => array(
						'type'        => 'button',
						'enabled'     => '1',
						'align'       => 'center',
						'label'       => '  Rezervasyon  ',
						'url'         => 'https://ornek.test/rezervasyon',
						'bg_color'    => 'red',
						'shape'       => 'hexagon',
						'font'        => 'Comic Sans',
						'font_size'   => '99',
						'font_weight' => '550',
					),
					// Bilinmeyen tip hiç listeye girmez.
					'blk_6' => array(
						'type'    => 'hack',
						'enabled' => '1',
					),
				),
				'hfb_hamburger_font_family'         => 'Comic Sans',
				'hfb_hamburger_font_size'           => '99',
				'hfb_hamburger_font_weight'         => '550',
				'hfb_hamburger_font_align'          => 'justify',
				'hfb_hamburger_close_icon_color'    => '#ff00aa',
				'hfb_hamburger_panel_bg_color'      => '#111111',
			),
			$hfb->get_hamburger_options()
		);

		$types   = wp_list_pluck( $temiz['blocks'], 'type' );
		$by_type = array();
		foreach ( $temiz['blocks'] as $block ) {
			$by_type[ $block['type'] ] = $block;
		}

		qrms_assert_same( array( 'text', 'logo', 'menu', 'social', 'button' ), $types, 'sıra + eksik tamamlandı; bilinmeyen tip elendi' );
		qrms_assert_same( 1, (int) $by_type['logo']['enabled'], 'logo açık' );
		qrms_assert_same( 0, (int) $by_type['menu']['enabled'], 'menü kapalı (kutu yok)' );
		qrms_assert_same( 'left', $by_type['logo']['align'], 'geçerli blok hizası korunur' );
		qrms_assert_same( 'center', $by_type['text']['align'], 'geçersiz blok hizası varsayılan' );
		qrms_assert_same( 'Playfair Display', $temiz['font_family'], 'bilinmeyen font reddedildi' );
		qrms_assert_same( 32, (int) $temiz['font_size'], 'punto üst sınır' );
		qrms_assert_same( 500, (int) $temiz['font_weight'], 'geçersiz kalınlık varsayılan' );
		qrms_assert_same( 'center', $temiz['font_align'], 'geçersiz hiza varsayılan' );
		qrms_assert_same( '#ff00aa', $temiz['close_icon_color'], 'kapatma rengi' );
		// Zararlı etiket ayıklaması wp_kses_post'un işidir; testte taklit
		// edildiği için burada yalnızca metnin korunduğu doğrulanır.
		qrms_assert_contains( 'Merhaba', $by_type['text']['content'], 'metin durur' );
		qrms_assert_same( 'Rezervasyon', $by_type['button']['label'], 'buton metni' );
		qrms_assert_same( '#c9a84c', $by_type['button']['bg_color'], 'geçersiz buton rengi varsayılan' );
		qrms_assert_same( 'pill', $by_type['button']['shape'], 'geçersiz şekil varsayılan' );
		qrms_assert_same( 'Playfair Display', $by_type['button']['font'], 'bilinmeyen buton fontu reddedildi' );
		qrms_assert_same( 32, (int) $by_type['button']['font_size'], 'buton punto üst sınır' );
	}
);

qrms_test(
	'AJAX önizleme hamburger metnini de döndürür ve kaydetmez',
	function () {
		$hfb = qrms_hfb();

		$_POST = array(
			'nonce' => 'test',
			'data'  => array(
				'hfb_header_brand_line1'        => 'Önizleme Marka',
				'hfb_hamburger_block_order'     => 'blk_4,blk_1,blk_2,blk_3',
				'hfb_hamburger_blocks'          => array(
					'blk_1' => array(
						'type'    => 'logo',
						'enabled' => '1',
						'align'   => 'center',
					),
					'blk_2' => array(
						'type'    => 'menu',
						'enabled' => '1',
						'align'   => 'center',
					),
					'blk_3' => array(
						'type'    => 'social',
						'enabled' => '1',
						'align'   => 'center',
					),
					'blk_4' => array(
						'type'    => 'text',
						'enabled' => '1',
						'align'   => 'center',
						'content' => 'Panel notu',
					),
				),
				'hfb_footer_copyright'          => '© 2026 Önizleme',
			),
		);

		$hfb->ajax_preview();
		$yanit = $GLOBALS['qrms_test']['json'];

		qrms_assert_true( is_array( $yanit ) && ! empty( $yanit['success'] ), 'başarılı yanıt' );
		qrms_assert_contains( 'Panel notu', $yanit['data']['header'], 'hamburger metni önizlemede' );
		qrms_assert_contains( 'hfb-mobile-panel__block--text', $yanit['data']['header'], 'metin bloğu sınıfı' );

		$stored_text = '';
		foreach ( $hfb->get_hamburger_options()['blocks'] as $block ) {
			if ( 'text' === $block['type'] ) {
				$stored_text = isset( $block['content'] ) ? (string) $block['content'] : '';
			}
		}
		qrms_assert_same( '', $stored_text, 'depo değişmedi' );
	}
);


qrms_test(
	'AJAX önizleme düz anahtarlı blok alanlarını da çözer',
	function () {
		// Önizleme isteği blokları `hfb_hamburger_blocks[blk_1][alan]`
		// biçiminde DÜZ anahtarlarla gönderir; kayıt yolundaki iç içe
		// diziden ayrı bir çözümleme dalıdır (bkz.
		// extract_hamburger_blocks_from_input).
		$hfb = qrms_hfb();

		$_POST = array(
			'nonce' => 'test',
			'data'  => array(
				'hfb_header_brand_line1'                   => 'Önizleme Marka',
				'hfb_hamburger_blocks[blk_1][type]'        => 'text',
				'hfb_hamburger_blocks[blk_1][enabled]'     => '1',
				'hfb_hamburger_blocks[blk_1][content]'     => 'Panel notu',
				'hfb_hamburger_blocks[blk_2][type]'        => 'logo',
				'hfb_hamburger_blocks[blk_2][enabled]'     => '1',
				'hfb_hamburger_blocks[blk_2][description]' => 'Lezzetin adresi',
				'hfb_hamburger_block_order'                => 'blk_1,blk_2',
				'hfb_footer_copyright'                     => '© 2026 Önizleme',
			),
		);

		$hfb->ajax_preview();
		$yanit = $GLOBALS['qrms_test']['json'];

		qrms_assert_true( is_array( $yanit ) && ! empty( $yanit['success'] ), 'başarılı yanıt' );
		qrms_assert_contains( 'Önizleme Marka', $yanit['data']['header'], 'marka taze veriyle döndü' );
		qrms_assert_contains( 'Panel notu', $yanit['data']['header'], 'düz anahtarlı metin bloğu' );
		qrms_assert_contains( 'Lezzetin adresi', $yanit['data']['header'], 'logo altı açıklama önizlemede' );

		// Önizleme hiçbir şeyi kaydetmez.
		$kayitli = $hfb->get_hamburger_options();
		qrms_assert_same( 'blk_1', $kayitli['blocks'][0]['id'], 'depodaki ilk blok varsayılan' );
		qrms_assert_same( 'logo', $kayitli['blocks'][0]['type'], 'depo değişmedi' );
	}
);

qrms_test(
	'header yerleşim ayarları CSS değişkeni olarak basılır ve aralığa sıkışır',
	function () {
		$hfb  = qrms_hfb();
		$opts = $hfb->get_header_options();

		$varsayilan = $hfb->render_header( $opts );
		qrms_assert_contains( '--hfb-header-max-width:1200px', $varsayilan, 'varsayılan genişlik' );
		qrms_assert_contains( '--hfb-header-padding-x:20px', $varsayilan, 'masaüstü yan boşluk' );
		qrms_assert_contains( '--hfb-header-padding-y:12px', $varsayilan, 'masaüstü dikey boşluk' );
		qrms_assert_contains( '--hfb-header-padding-x-m:20px', $varsayilan, 'mobil yan boşluk' );
		qrms_assert_contains( '--hfb-header-padding-y-m:12px', $varsayilan, 'mobil dikey boşluk' );

		$temiz = $hfb->sanitize_header_input(
			array(
				'hfb_header_content_width'     => '4000',
				'hfb_header_padding_x_desktop' => '999',
				'hfb_header_padding_y_desktop' => '999',
				'hfb_header_padding_x_mobile'  => '999',
				'hfb_header_padding_y_mobile'  => '4',
				'hfb_header_sticky'            => '1',
				'hfb_lang_show'                => '1',
			),
			$opts
		);

		qrms_assert_same( 1600, (int) $temiz['content_width'], 'genişlik üst sınır' );
		qrms_assert_same( 80, (int) $temiz['padding_x_desktop'], 'masaüstü yan üst sınır' );
		qrms_assert_same( 40, (int) $temiz['padding_y_desktop'], 'masaüstü dikey üst sınır' );
		qrms_assert_same( 32, (int) $temiz['padding_x_mobile'], 'mobil yan üst sınır dar' );
		qrms_assert_same( 4, (int) $temiz['padding_y_mobile'], 'aralıktaki değer korunur' );
		qrms_assert_same( 0, (int) $temiz['content_full_width'], 'tam genişlik kapalı' );

		// Tam genişlik seçilince kural sabit piksel değil `none` görür.
		$tam = $hfb->sanitize_header_input(
			array(
				'hfb_header_content_full_width' => '1',
				'hfb_header_sticky'             => '1',
				'hfb_lang_show'                 => '1',
			),
			$opts
		);

		qrms_assert_same( 1, (int) $tam['content_full_width'], 'tam genişlik açık' );
		qrms_assert_contains( '--hfb-header-max-width:none', $hfb->render_header( $tam ), 'genişlik sınırı kalkar' );
	}
);

qrms_test(
	'header__inner sabit ölçü yerine yerleşim değişkenlerini kullanır',
	function () {
		$css = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/header-footer-builder/assets/css/frontend.css'
		);

		qrms_assert_contains( 'max-width: var(--hfb-header-max-width, 1200px)', $css, 'genişlik değişkeni' );
		qrms_assert_contains( 'var(--hfb-header-padding-y, 0.75rem) var(--hfb-header-padding-x, 1.25rem)', $css, 'masaüstü boşluk değişkenleri' );
		qrms_assert_contains( '--hfb-header-padding-x-m', $css, 'mobil kırılımda ayrı set' );

		// Dil bayrağı: daire içeriğiyle birlikte kurulur, taşma kırpılır.
		qrms_assert_contains( '.hfb-header-wrap .hfb-lang .qrmenu-current-btn img', $css, 'bayrak görseli kuralı' );
		qrms_assert_contains( '.hfb-header__lang-mobile', $css, 'mobil header bayrağı' );
		qrms_assert_contains( '.hfb-mobile-panel .hfb-lang .qrmenu-options-panel', $css, 'offcanvas panel konumu' );
		qrms_assert_contains( 'object-fit: cover', $css, 'bayrak oranı korunur' );

		// Mobil panel zenginleştirmesi.
		qrms_assert_contains( '.hfb-mobile-panel__desc', $css, 'logo altı açıklama stili' );
		qrms_assert_contains( '.hfb-mobile-panel__btn--full', $css, 'tam genişlik buton' );
	}
);

qrms_test(
	'panel logo bloğu açıklama, buton bloğu tam genişlik taşır',
	function () {
		$hfb       = qrms_hfb();
		$header    = $hfb->get_header_options();
		$hamburger = $hfb->get_hamburger_options();

		$hamburger['blocks'] = array(
			array(
				'id'          => 'blk_1',
				'type'        => 'logo',
				'enabled'     => true,
				'align'       => 'center',
				'description' => 'Lezzetin adresi',
			),
			array(
				'id'         => 'blk_2',
				'type'       => 'button',
				'enabled'    => true,
				'align'      => 'center',
				'label'      => 'Rezervasyon Yap',
				'url'        => 'https://rezervasyon.test',
				'shape'      => 'pill',
				'full_width' => 1,
			),
		);

		$html = $hfb->render_header( $header, $hamburger );

		qrms_assert_contains( 'hfb-mobile-panel__desc', $html, 'açıklama kabı' );
		qrms_assert_contains( 'Lezzetin adresi', $html, 'açıklama metni' );
		qrms_assert_contains( 'hfb-mobile-panel__btn--full', $html, 'tam genişlik sınıfı' );
		qrms_assert_contains( 'Rezervasyon Yap', $html, 'buton metni' );

		// Tam genişlik kapalıyken sınıf hiç basılmaz.
		$hamburger['blocks'][1]['full_width'] = 0;
		qrms_assert_true(
			false === strpos( $hfb->render_header( $header, $hamburger ), 'hfb-mobile-panel__btn--full' ),
			'kapalıyken sınıf yok'
		);
	}
);

qrms_test(
	'Görünüm adımı öncesi kayıtlar görüntü değişmeden taşınır',
	function () {
		$hfb = qrms_hfb();

		// Adım eklenmeden ÖNCEKİ kayıt: Görünüm anahtarları yok, header'ın
		// ikon rengi ve mobil logo ölçüsü özelleştirilmiş.
		update_option(
			'hfb_header_options',
			array(
				'icon_color'              => '#ff0066',
				'logo_width_mobile'       => 210,
				'logo_height_mobile'      => 70,
				'logo_height_auto_mobile' => 0,
			)
		);
		update_option(
			'hfb_hamburger_options',
			array(
				'panel_bg_color' => '#111111',
				'font_color'     => '#dddddd',
			)
		);

		$opts = $hfb->get_hamburger_options();

		// Renkler eski kaynaklarından devralınır.
		qrms_assert_same( '#dddddd', $opts['menu_link_color'], 'satır metni panel yazı renginden' );
		qrms_assert_same( '#ff0066', $opts['menu_hover_color'], 'hover header ikon renginden' );
		qrms_assert_same( '#ff0066', $opts['menu_divider_color'], 'ayraç header ikon renginden' );
		qrms_assert_same( '#ff0066', $opts['menu_arrow_color'], 'ok header ikon renginden' );
		qrms_assert_same( '#ff0066', $opts['social_border_color'], 'sosyal çerçeve header ikon renginden' );
		qrms_assert_same( '#ff0066', $opts['social_icon_color'], 'sosyal glyph header ikon renginden' );
		qrms_assert_same( '', $opts['social_bg_color'], 'sosyal zemin şeffaf kalır' );

		// Panel logosu header'ın MOBİL ölçüsünü devralır (tek set).
		qrms_assert_same( 210, (int) $opts['logo_width'], 'panel logo genişliği devralındı' );
		qrms_assert_same( 70, (int) $opts['logo_height'], 'sabit yükseklik devralındı' );
		qrms_assert_same( 0, (int) $opts['logo_height_auto'], 'otomatik oran kapalı devralındı' );

		// Mevcut kaydedilmiş veri korunur.
		qrms_assert_same( '#111111', $opts['panel_bg_color'], 'panel zemini korunur' );
		qrms_assert_same( '#dddddd', $opts['font_color'], 'yazı rengi korunur' );

		// Kaydedilmiş bir Görünüm değeri varsa geçiş onu EZMEZ.
		update_option(
			'hfb_hamburger_options',
			array(
				'font_color'        => '#dddddd',
				'menu_arrow_color'  => '#00ff00',
				'logo_width_mobile' => 88,
			)
		);

		$kayitli = $hfb->get_hamburger_options();

		qrms_assert_same( '#00ff00', $kayitli['menu_arrow_color'], 'kaydedilmiş ok rengi korunur' );
		qrms_assert_same( 88, (int) $kayitli['logo_width'], 'kaydedilmiş panel logo korunur' );
	}
);

qrms_test(
	'hamburger masaüstü/mobil ayrımı tek sete birleşir, veri kaybolmaz',
	function () {
		$hfb = qrms_hfb();

		// Ayrım kaldırılmadan ÖNCEKİ kayıt: iki ayrı set.
		update_option(
			'hfb_hamburger_options',
			array(
				'font_size_desktop'         => 30,
				'font_size_mobile'          => 21,
				'font_weight_desktop'       => 700,
				'font_weight_mobile'        => 400,
				'font_align_desktop'        => 'left',
				'font_align_mobile'         => 'right',
				'logo_width_desktop'        => 300,
				'logo_width_mobile'         => 95,
				'logo_height_desktop'       => 180,
				'logo_height_mobile'        => 60,
				'logo_height_auto_desktop'  => 0,
				'logo_height_auto_mobile'   => 0,
			)
		);

		$opts = $hfb->get_hamburger_options();

		// Panel yalnızca mobilde açılır: gerçekte görünen MOBİL değerdir,
		// tek sete o taşınır.
		qrms_assert_same( 21, (int) $opts['font_size'], 'punto mobil değerden' );
		qrms_assert_same( 400, (int) $opts['font_weight'], 'kalınlık mobil değerden' );
		qrms_assert_same( 'right', $opts['font_align'], 'hizalama mobil değerden' );
		qrms_assert_same( 95, (int) $opts['logo_width'], 'logo genişliği mobil değerden' );
		qrms_assert_same( 60, (int) $opts['logo_height'], 'logo yüksekliği mobil değerden' );
		qrms_assert_same( 0, (int) $opts['logo_height_auto'], 'otomatik oran mobil değerden' );

		// Kırılım anahtarları artık şemada yok: merge_options() budar.
		foreach ( array( 'font_size_desktop', 'font_size_mobile', 'logo_width_desktop', 'logo_width_mobile' ) as $eski ) {
			qrms_assert_true( ! array_key_exists( $eski, $opts ), $eski . ' anahtarı kalmaz' );
		}

		// Mobil anahtar hiç yoksa masaüstü değeri kurtarılır — veri kaybı yok.
		update_option(
			'hfb_hamburger_options',
			array(
				'font_size_desktop'  => 26,
				'logo_width_desktop' => 175,
			)
		);

		$yalniz_masaustu = $hfb->get_hamburger_options();

		qrms_assert_same( 26, (int) $yalniz_masaustu['font_size'], 'mobil yoksa masaüstü puntosu taşınır' );
		qrms_assert_same( 175, (int) $yalniz_masaustu['logo_width'], 'mobil yoksa masaüstü logosu taşınır' );
	}
);

qrms_test(
	'hamburger sekmesinde masaüstü/mobil alanı yok, panel logosu 50px\'e inebilir',
	function () {
		$hfb = qrms_hfb();
		$GLOBALS['qrms_test']['can'] = true;

		ob_start();
		$hfb->render_admin_page();
		$html = ob_get_clean();

		// Tek set alan adları basılır.
		foreach ( array( 'hfb_hamburger_font_size', 'hfb_hamburger_font_weight', 'hfb_hamburger_font_align', 'hfb_hamburger_logo_width', 'hfb_hamburger_logo_height', 'hfb_hamburger_logo_height_auto' ) as $alan ) {
			qrms_assert_contains( 'name="' . $alan . '"', $html, $alan . ' alanı' );
		}

		// Kırılıma bölünmüş hamburger alanları TAMAMEN kalkar.
		foreach ( array( 'hfb_hamburger_font_size_desktop', 'hfb_hamburger_font_size_mobile', 'hfb_hamburger_font_weight_desktop', 'hfb_hamburger_font_align_desktop', 'hfb_hamburger_logo_width_desktop', 'hfb_hamburger_logo_width_mobile', 'hfb_hamburger_logo_height_auto_desktop' ) as $eski ) {
			qrms_assert_true(
				false === strpos( $html, 'name="' . $eski . '"' ),
				$eski . ' arayüzden çıkar'
			);
		}

		// Header sekmesinin kırılım ayrımına DOKUNULMAZ.
		qrms_assert_contains( 'name="hfb_header_logo_width_desktop"', $html, 'header masaüstü logosu durur' );
		qrms_assert_contains( 'name="hfb_header_logo_width_tablet"', $html, 'header tablet logosu durur' );
		qrms_assert_contains( 'name="hfb_header_logo_width_mobile"', $html, 'header mobil logosu durur' );

		// Panel logo kaydırıcısının alt sınırı 50px (header'ınki 80px kalır).
		qrms_assert_contains(
			'id="hfb_hamburger_logo_width"',
			$html,
			'panel logo kaydırıcısı'
		);
		$parca = substr( $html, (int) strpos( $html, 'id="hfb_hamburger_logo_width"' ) - 200, 400 );
		qrms_assert_contains( 'min="50"', $parca, 'panel logo alt sınırı 50px' );
	}
);

qrms_test(
	'AJAX önizleme Görünüm adımının alanlarını da yansıtır ve kaydetmez',
	function () {
		$hfb = qrms_hfb();

		$_POST = array(
			'nonce' => 'test',
			'data'  => array(
				'hfb_hamburger_panel_bg_image'      => '7',
				'hfb_hamburger_panel_bg_opacity'    => '35',
				'hfb_hamburger_logo_width'          => '95',
				'hfb_hamburger_menu_link_color'     => '#eeeeee',
				'hfb_hamburger_menu_hover_color'    => '#00cc88',
				'hfb_hamburger_menu_divider_color'  => '#334455',
				'hfb_hamburger_menu_arrow_color'    => '#ff2200',
				'hfb_hamburger_social_border_color' => '#445566',
				'hfb_hamburger_social_bg_color'     => '#0b0b0f',
				'hfb_hamburger_social_icon_color'   => '#ffcc00',
				'hfb_hamburger_btn_bg_color'        => '#123123',
				'hfb_hamburger_btn_shape'           => 'rounded',
			),
		);

		$hfb->ajax_preview();
		$yanit = $GLOBALS['qrms_test']['json'];
		$html  = $yanit['data']['header'];

		qrms_assert_true( is_array( $yanit ) && ! empty( $yanit['success'] ), 'başarılı yanıt' );

		// Kaydetmeden, formdaki her Görünüm alanı önizlemeye iner.
		qrms_assert_contains( 'hfb-mobile-panel__bg', $html, 'arka plan katmanı' );
		qrms_assert_contains( '--hfb-panel-bg-opacity:0.35', $html, 'opaklık' );
		qrms_assert_contains( '--hfb-panel-logo-w:95px', $html, 'panel logo ölçüsü (tek set)' );
		qrms_assert_true(
			false === strpos( $html, '--hfb-panel-logo-w-m' ),
			'kırılıma özel ikinci logo değişkeni basılmaz'
		);
		qrms_assert_contains( '--hfb-panel-menu-color:#eeeeee', $html, 'satır metin rengi' );
		qrms_assert_contains( '--hfb-panel-menu-hover:#00cc88', $html, 'satır hover rengi' );
		qrms_assert_contains( '--hfb-panel-menu-divider:#334455', $html, 'ayraç rengi' );
		qrms_assert_contains( '--hfb-panel-menu-arrow:#ff2200', $html, 'ok rengi' );
		qrms_assert_contains( '--hfb-panel-social-border:#445566', $html, 'sosyal çerçeve' );
		qrms_assert_contains( '--hfb-panel-social-bg:#0b0b0f', $html, 'sosyal zemin' );
		qrms_assert_contains( '--hfb-panel-social-icon:#ffcc00', $html, 'sosyal glyph' );
		qrms_assert_contains( '--hfb-panel-btn-bg:#123123', $html, 'buton zemini' );
		qrms_assert_contains( '--hfb-panel-btn-radius:10px', $html, 'yuvarlatılmış buton' );

		// Önizleme depoya yazmaz.
		$kayitli = $hfb->get_hamburger_options();
		qrms_assert_same( 0, (int) $kayitli['panel_bg_image'], 'görsel kaydedilmedi' );
		qrms_assert_same( '#c9a84c', $kayitli['menu_arrow_color'], 'renk kaydedilmedi' );

		$_POST = array();
	}
);

qrms_test(
	'Görünüm adımı tek formda; alanlar ve Açılış adımına referans basılır',
	function () {
		$hfb = qrms_hfb();
		ob_start();
		$hfb->render_admin_page();
		$html = (string) ob_get_clean();

		// Tek form / tek Kaydet düğmesi korunur.
		qrms_assert_same( 1, substr_count( $html, 'id="hfb-settings-form"' ), 'tek form' );
		qrms_assert_same( 1, substr_count( $html, 'name="hfb_save"' ), 'tek Kaydet düğmesi' );
		qrms_assert_contains( 'name="hfb_nonce"', $html, 'nonce alanı' );

		// Görünüm adımının alanları.
		foreach (
			array(
				'hfb_hamburger_panel_bg_image',
				'hfb_hamburger_panel_bg_opacity',
				'hfb_hamburger_logo_width',
				'hfb_hamburger_logo_height_auto',
				'hfb_hamburger_menu_link_color',
				'hfb_hamburger_menu_hover_color',
				'hfb_hamburger_menu_divider_color',
				'hfb_hamburger_menu_arrow_color',
				'hfb_hamburger_social_border_color',
				'hfb_hamburger_social_bg_color',
				'hfb_hamburger_social_icon_color',
				'hfb_hamburger_btn_bg_color',
				'hfb_hamburger_btn_text_color',
				'hfb_hamburger_btn_shape',
				'hfb_hamburger_btn_font_family',
				'hfb_hamburger_btn_font_size',
				'hfb_hamburger_btn_font_weight',
			) as $alan
		) {
			qrms_assert_contains( 'name="' . $alan . '"', $html, $alan . ' alanı' );
		}

		// Panel arka plan RENGİ ve kapatma ikonu Açılış adımında kalır;
		// Görünüm adımında tekrar oluşturulmaz.
		qrms_assert_same(
			1,
			substr_count( $html, 'name="hfb_hamburger_panel_bg_color"' ),
			'panel arka plan rengi tek yerde (Açılış adımı)'
		);
		qrms_assert_same(
			1,
			substr_count( $html, 'name="hfb_hamburger_close_icon_color"' ),
			'kapatma ikonu rengi tek yerde (Açılış adımı)'
		);
		qrms_assert_contains( '1. Açılış adımında ayarlanır', $html, 'Açılış adımına referans' );
	}
);

qrms_test(
	'sanitize_hamburger_input Görünüm adımının alanlarını temizler',
	function () {
		$hfb = qrms_hfb();

		$temiz = $hfb->sanitize_hamburger_input(
			array(
				// Arka plan görseli + opaklık.
				'hfb_hamburger_panel_bg_image'         => '42abc',
				'hfb_hamburger_panel_bg_opacity'       => '250',
				// Panel içi logo — header logosundan bağımsız, tek set.
				'hfb_hamburger_logo_width'             => '900',
				'hfb_hamburger_logo_height'            => '90',
				// Liste satırı renkleri.
				'hfb_hamburger_menu_link_color'        => '#AABBCC',
				'hfb_hamburger_menu_hover_color'       => 'javascript:alert(1)',
				'hfb_hamburger_menu_divider_color'     => '#123456',
				'hfb_hamburger_menu_arrow_color'       => '#654321',
				// Sosyal ikon renkleri; zemin boş = şeffaf.
				'hfb_hamburger_social_border_color'    => '#0f0f0f',
				'hfb_hamburger_social_bg_color'        => '   ',
				'hfb_hamburger_social_icon_color'      => '#ff8800',
				// Panel geneli buton varsayılanları.
				'hfb_hamburger_btn_bg_color'           => '#010203',
				'hfb_hamburger_btn_shape'              => 'hexagon',
				'hfb_hamburger_btn_font_family'        => 'Comic Sans',
				'hfb_hamburger_btn_font_size'          => '99',
				'hfb_hamburger_btn_font_weight'        => '550',
			),
			$hfb->get_hamburger_options()
		);

		qrms_assert_same( 42, (int) $temiz['panel_bg_image'], 'ek kimliği absint' );
		qrms_assert_same( 100, (int) $temiz['panel_bg_opacity'], 'opaklık üst sınıra sıkışır' );

		qrms_assert_same( 320, (int) $temiz['logo_width'], 'panel logo genişliği üst sınıra sıkışır' );
		qrms_assert_same( 0, (int) $temiz['logo_height_auto'], 'kutu işaretsizken otomatik oran kapalı' );
		qrms_assert_same( 90, (int) $temiz['logo_height'], 'sabit yükseklik korunur' );

		// Panel logosunun alt sınırı header'ın 80px'i değil 50px'tir.
		$dar = $hfb->sanitize_hamburger_input(
			array( 'hfb_hamburger_logo_width' => '55' ),
			$hfb->get_hamburger_options()
		);
		qrms_assert_same( 55, (int) $dar['logo_width'], '50–80 arası değer kabul edilir' );

		$cok_dar = $hfb->sanitize_hamburger_input(
			array( 'hfb_hamburger_logo_width' => '10' ),
			$hfb->get_hamburger_options()
		);
		qrms_assert_same( 50, (int) $cok_dar['logo_width'], 'panel logo genişliği 50px alt sınırına sıkışır' );

		$otomatik = $hfb->sanitize_hamburger_input(
			array( 'hfb_hamburger_logo_height_auto' => '1' ),
			$hfb->get_hamburger_options()
		);
		qrms_assert_same( 1, (int) $otomatik['logo_height_auto'], 'otomatik oran açık' );
		qrms_assert_same( 0, (int) $otomatik['logo_height'], 'otomatik oranda yükseklik sıfırlanır' );

		qrms_assert_same( '#AABBCC', $temiz['menu_link_color'], 'geçerli hex olduğu gibi korunur' );
		qrms_assert_same( '#c9a84c', $temiz['menu_hover_color'], 'geçersiz renk varsayılana düşer' );
		qrms_assert_same( '#123456', $temiz['menu_divider_color'], 'ayraç rengi' );
		qrms_assert_same( '#654321', $temiz['menu_arrow_color'], 'ok rengi' );

		qrms_assert_same( '#0f0f0f', $temiz['social_border_color'], 'sosyal çerçeve rengi' );
		qrms_assert_same( '', $temiz['social_bg_color'], 'boş bırakılan zemin şeffaf kalır' );
		qrms_assert_same( '#ff8800', $temiz['social_icon_color'], 'sosyal glyph rengi' );

		qrms_assert_same( '#010203', $temiz['btn_bg_color'], 'buton zemini' );
		qrms_assert_same( 'pill', $temiz['btn_shape'], 'bilinmeyen şekil varsayılana düşer' );
		qrms_assert_same( 'Playfair Display', $temiz['btn_font_family'], 'bilinmeyen font reddedildi' );
		qrms_assert_same( 32, (int) $temiz['btn_font_size'], 'punto üst sınır' );
		qrms_assert_same( 600, (int) $temiz['btn_font_weight'], 'geçersiz kalınlık varsayılan' );

		// Yazı adımı ve bloklar bu adımdan etkilenmez.
		qrms_assert_same( '#f5f0e8', $temiz['font_color'], 'yazı rengi korunur' );
		qrms_assert_true( count( $temiz['blocks'] ) > 0, 'blok listesi korunur' );
	}
);

qrms_test(
	'Görünüm ayarları panel CSS değişkeni olarak basılır',
	function () {
		$hfb       = qrms_hfb();
		$opts      = $hfb->get_header_options();
		$hamburger = $hfb->get_hamburger_options();

		// Varsayılan hâl: görsel yok, sosyal zemin şeffaf.
		$varsayilan = $hfb->render_header( $opts, $hamburger );

		qrms_assert_contains( '--hfb-panel-logo-w:120px', $varsayilan, 'panel logo genişliği' );
		qrms_assert_contains( '--hfb-panel-logo-h:auto', $varsayilan, 'otomatik oran' );
		qrms_assert_contains( '--hfb-panel-menu-color:#f5f0e8', $varsayilan, 'satır metin rengi' );
		qrms_assert_contains( '--hfb-panel-menu-divider:#c9a84c', $varsayilan, 'ayraç rengi' );
		qrms_assert_contains( '--hfb-panel-btn-radius:999px', $varsayilan, 'buton şekli yarıçapa çevrilir' );
		qrms_assert_true(
			false === strpos( $varsayilan, '--hfb-panel-social-bg' ),
			'şeffaf zemin için değişken hiç basılmaz'
		);
		qrms_assert_true(
			false === strpos( $varsayilan, 'hfb-mobile-panel__bg' ),
			'görsel yokken arka plan katmanı basılmaz'
		);

		$hamburger['panel_bg_image']      = 42;
		$hamburger['panel_bg_opacity']    = 40;
		$hamburger['social_bg_color']     = '#101014';
		$hamburger['menu_arrow_color']    = '#ff0000';
		$hamburger['logo_width']          = 200;
		$hamburger['btn_shape']           = 'square';

		$ozel = $hfb->render_header( $opts, $hamburger );

		qrms_assert_contains( 'hfb-mobile-panel__bg', $ozel, 'arka plan katmanı basılır' );
		qrms_assert_contains( '--hfb-panel-bg-image:url(https://restoran.test', $ozel, 'görsel adresi' );
		qrms_assert_contains( '--hfb-panel-bg-opacity:0.4', $ozel, 'opaklık 0–1 aralığına çevrilir' );
		qrms_assert_contains( '--hfb-panel-social-bg:#101014', $ozel, 'sosyal zemin rengi' );
		qrms_assert_contains( '--hfb-panel-menu-arrow:#ff0000', $ozel, 'ok rengi' );
		qrms_assert_contains( '--hfb-panel-logo-w:200px', $ozel, 'panel logo ölçüsü (tek set)' );
		qrms_assert_contains( '--hfb-panel-btn-radius:0', $ozel, 'köşeli buton' );
	}
);

qrms_test(
	'buton bloğu kendi ayarını taşımıyorsa panel varsayılanını kullanır',
	function () {
		$hfb = qrms_hfb();

		// Panel varsayılanları değiştirilir; blok yalnızca etiket taşır.
		update_option(
			'hfb_hamburger_options',
			array(
				'btn_bg_color'   => '#00ff00',
				'btn_text_color' => '#111111',
				'btn_shape'      => 'square',
				'btn_font_size'  => 22,
				'blocks'         => array(
					array(
						'id'      => 'blk_1',
						'type'    => 'button',
						'enabled' => true,
						'align'   => 'center',
						'label'   => 'Rezervasyon',
					),
				),
			)
		);

		$hamburger = $hfb->get_hamburger_options();
		$html      = $hfb->render_header( $hfb->get_header_options(), $hamburger );

		qrms_assert_contains( 'background-color:#00ff00', $html, 'panel varsayılanı butona iner' );
		qrms_assert_contains( 'color:#111111', $html, 'panel yazı rengi' );
		qrms_assert_contains( 'font-size:22px', $html, 'panel punto varsayılanı' );
		qrms_assert_contains( 'hfb-mobile-panel__btn--square', $html, 'panel şekil varsayılanı' );

		// Blok kendi rengini taşıyorsa panel varsayılanını ezer.
		$hamburger['blocks'][0]['bg_color'] = '#0000ff';
		$ezilmis = $hfb->render_header( $hfb->get_header_options(), $hamburger );

		qrms_assert_contains( 'background-color:#0000ff', $ezilmis, 'blok kendi rengini kullanır' );
	}
);

qrms_test(
	'ayar sayfası sekmeleri ve adım başlıklarını basar',
	function () {
		$hfb = qrms_hfb();
		ob_start();
		$hfb->render_admin_page();
		$html = (string) ob_get_clean();

		qrms_assert_contains( 'data-hfb-tab="header"', $html, 'Header sekmesi' );
		qrms_assert_contains( 'data-hfb-tab="hamburger"', $html, 'Hamburger Menü sekmesi' );
		qrms_assert_contains( '1. Logo Boyutu', $html, 'header adım 1' );
		qrms_assert_contains( '2. Header Görünümü', $html, 'header adım 2' );
		qrms_assert_contains( '3. İkon ve Buton Renkleri', $html, 'header adım 3' );
		qrms_assert_contains( '4. Yerleşim / Boşluklar', $html, 'header adım 4' );
		qrms_assert_contains( 'hfb_header_content_width', $html, 'içerik genişliği kaydırıcısı' );
		qrms_assert_contains( 'hfb_header_padding_x_mobile', $html, 'mobil yan boşluk kaydırıcısı' );
		qrms_assert_contains( '1. Açılış Davranışı', $html, 'hamburger adım 1' );
		qrms_assert_contains( '2. İçerik Blokları ve Sıralama', $html, 'hamburger adım 2' );
		qrms_assert_contains( '3. Panel Görünümü', $html, 'hamburger adım 3' );
		qrms_assert_contains( '4. Yazı Tipi ve Renk', $html, 'hamburger adım 4' );
		qrms_assert_contains( '1. Logo ve Slogan', $html, 'footer adım 1' );
		qrms_assert_contains( '2. Hızlı Menü', $html, 'footer adım 2' );
		qrms_assert_contains( '3. Çalışma Saatleri', $html, 'footer adım 3' );
		qrms_assert_contains( '4. İletişim Bilgileri', $html, 'footer adım 4' );
		qrms_assert_contains( '5. Garson / Hesap Butonu', $html, 'footer adım 5' );
		qrms_assert_true( false === strpos( $html, '3. Çalışma Saatleri ve İletişim' ), 'eski birleşik saatler+iletişim başlığı yok' );
		qrms_assert_true( false === strpos( $html, '4. Garson / Hesap Butonu' ), 'çağrı artık 5. adım' );
		qrms_assert_contains( 'Başlık yazı rengi', $html, 'başlık rengi etiketi ayrışmış' );
		qrms_assert_contains( 'Link yazı rengi', $html, 'link rengi etiketi ayrışmış' );
		qrms_assert_contains( 'Gün/saat yazı rengi', $html, 'saat satır rengi etiketi ayrışmış' );
		qrms_assert_contains( 'İletişim satır yazı rengi', $html, 'iletişim satır rengi etiketi ayrışmış' );
		qrms_assert_contains( 'id="hfb-steps-footer"', $html, 'footer adım şeridi' );
		qrms_assert_contains( 'Adım 1/5: Logo ve Slogan', $html, 'footer ilerleme 5 adım' );

		if ( preg_match( '/id="hfb-panel-footer"(.*?)<div class="hfb-tab-panel/s', $html, $footer_panel ) ) {
			$footer_html = $footer_panel[1];

			qrms_assert_contains( 'data-step="5"', $footer_html, 'çağrı data-step=5' );

			if ( preg_match( '/data-step="3"[^>]*>(.*?)<div class="qrms-card hfb-step" data-step="4"/s', $footer_html, $saatler ) ) {
				qrms_assert_contains( 'hfb_footer_hours_title', $saatler[1], 'saatler adımında saat başlığı' );
				qrms_assert_true( false === strpos( $saatler[1], 'hfb_footer_contact_title' ), 'saatler adımında iletişim yok' );
				qrms_assert_true( false === strpos( $saatler[1], 'hfb_footer_address' ), 'saatler adımında adres yok' );
			} else {
				qrms_assert_true( false, 'footer adım 3 kartı bulunamadı' );
			}

			if ( preg_match( '/data-step="4"[^>]*>(.*?)<div class="qrms-card hfb-step" data-step="5"/s', $footer_html, $iletisim ) ) {
				qrms_assert_contains( 'hfb_footer_contact_title', $iletisim[1], 'iletişim adımında iletişim başlığı' );
				qrms_assert_contains( 'hfb_footer_address', $iletisim[1], 'iletişim adımında adres' );
				qrms_assert_true( false === strpos( $iletisim[1], 'hfb_footer_hours_title' ), 'iletişim adımında saat başlığı yok' );
			} else {
				qrms_assert_true( false, 'footer adım 4 kartı bulunamadı' );
			}

			qrms_assert_contains( 'hfb_footer_call_enabled', $footer_html, 'çağrı alanı footer panelinde' );
			qrms_assert_contains( 'hfb_footer_call_garson_label', $footer_html, 'garson metni duruyor' );
		} else {
			qrms_assert_true( false, 'footer paneli bulunamadı' );
		}
		qrms_assert_contains( 'hfb-color-picker', $html, 'renk seçici' );
		qrms_assert_contains( 'hfb-block-sortable', $html, 'sürükle-bırak liste' );
		qrms_assert_contains( 'Masaüstü Önizleme', $html, 'masaüstü önizleme düğmesi' );
		qrms_assert_contains( 'Önizlemede Aç', $html, 'hamburger panel önizleme' );
		qrms_assert_true( false === strpos( $html, 'Tasarım sabittir' ), 'eski sabit tasarım metni yok' );
	}
);

qrms_test(
	'footer adım sihirbazı DOM kart sayısını kullanır, sabit 4 yoktur',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/assets/js/admin.js' );

		qrms_assert_contains( 'var toplam = $steps.length;', $js, 'toplam DOM\'daki kart sayısı' );
		qrms_assert_contains( 'function sinirla(adimNo)', $js, 'adım sınır kontrolü' );
		qrms_assert_contains( '$next.toggle(mevcut < toplam);', $js, 'son adımda Devam Et gizlenir' );
		qrms_assert_true( false === strpos( $js, 'var toplam = 4' ), 'sabit 4 adım yok' );
		qrms_assert_true( false === strpos( $js, "'4/4'" ), 'sabit 4/4 metni yok' );
	}
);

qrms_test(
	'footer dört sütun basar: marka, menü, saatler, iletişim',
	function () {
		$hfb  = qrms_hfb();
		$opts = $hfb->get_footer_options();
		$opts['description'] = 'Lezzetin adresi.';
		$opts['menu_id']     = 7;
		$opts['phone']       = '0850 000 00 00';
		$opts['email']       = 'info@ornek.test';
		$opts['address']     = "Atatürk Cad.\nNo: 12";

		$html = $hfb->render_footer( $opts );

		qrms_assert_contains( 'hfb-footer__col--brand', $html, 'marka sütunu' );
		qrms_assert_contains( 'Lezzetin adresi.', $html, 'açıklama' );
		qrms_assert_contains( 'hfb-footer__col--links', $html, 'hızlı menü sütunu' );
		qrms_assert_contains( 'Hızlı Menü', $html, 'varsayılan menü başlığı' );
		qrms_assert_contains( 'hfb-footer__col--hours', $html, 'saat sütunu (modül yüklü)' );
		qrms_assert_contains( 'Çalışma Saatlerimiz', $html, 'saat başlığı' );
		qrms_assert_contains( 'hfb-footer__hours-day', $html, 'gün adı' );
		qrms_assert_contains( 'hfb-footer__col--contact', $html, 'iletişim sütunu' );
		qrms_assert_contains( 'İletişim', $html, 'iletişim başlığı' );
		qrms_assert_contains( 'hfb-icon--contact', $html, 'iletişim ikonu' );
		qrms_assert_contains( 'Atatürk Cad.', $html, 'adres' );
		qrms_assert_contains( '--hfb-footer-brand-align:left', $html, 'CSS değişkeni' );
		qrms_assert_true( false === strpos( $html, 'data-qmo-cagri' ), 'çağrı kapalıyken buton yok' );
	}
);

qrms_test(
	'sanitize_footer_input yeni alanları temizler, mevcutları korur',
	function () {
		$hfb = qrms_hfb();
		$cur = $hfb->get_footer_options();
		$cur['brand_line1'] = 'Kayıtlı Marka';
		$cur['phone']       = '0212 111 22 33';

		$temiz = $hfb->sanitize_footer_input(
			array(
				'hfb_footer_brand_line1'             => '  Yeni Marka  ',
				'hfb_footer_address'                 => "Cadde 1\n<script>x</script>",
				'hfb_footer_links_title'             => 'Hızlı Menü',
				'hfb_footer_hours_title'             => 'Çalışma Saatlerimiz',
				'hfb_footer_contact_title'           => 'İletişim',
				'hfb_footer_phone'                   => '0212 111 22 33',
				'hfb_footer_email'                   => 'info@ornek.test',
				'hfb_footer_brand_align'             => 'center',
				'hfb_footer_links_align'             => 'justify',
				'hfb_footer_brand_font_family'       => 'Comic Sans',
				'hfb_footer_brand_font_size_desktop' => '99',
				'hfb_footer_brand_font_color'        => '#abcdef',
				'hfb_footer_logo_width_desktop'      => '999',
				'hfb_footer_call_enabled'            => '1',
				'hfb_footer_call_garson_label'       => '  Garson  ',
				'hfb_footer_btn_bg_color'            => 'red',
				'hfb_footer_btn_shape'               => 'hexagon',
				'hfb_footer_btn_font_size'           => '12',
			),
			$cur
		);

		qrms_assert_same( 'Yeni Marka', $temiz['brand_line1'], 'marka temizlendi' );
		qrms_assert_same( '0212 111 22 33', $temiz['phone'], 'telefon durur' );
		qrms_assert_contains( 'Cadde 1', $temiz['address'], 'adres durur' );
		qrms_assert_true( false === strpos( $temiz['address'], '<script>' ), 'script yok' );
		qrms_assert_same( 'info@ornek.test', $temiz['email'], 'e-posta durur' );
		qrms_assert_same( 'center', $temiz['brand_align'], 'hizalama' );
		qrms_assert_same( 'left', $temiz['links_align'], 'geçersiz hiza varsayılan' );
		qrms_assert_same( 'Playfair Display', $temiz['brand_font_family'], 'bilinmeyen font reddedildi' );
		qrms_assert_same( 32, (int) $temiz['brand_font_size_desktop'], 'punto üst sınır' );
		qrms_assert_same( '#abcdef', $temiz['brand_font_color'], 'renk' );
		qrms_assert_same( 320, (int) $temiz['logo_width_desktop'], 'logo genişlik üst sınır' );
		qrms_assert_same( 1, (int) $temiz['call_enabled'], 'çağrı açık' );
		qrms_assert_same( 'Garson', $temiz['call_garson_label'], 'buton metni' );
		qrms_assert_same( '#c9a84c', $temiz['btn_bg_color'], 'geçersiz buton rengi varsayılan' );
		qrms_assert_same( 'pill', $temiz['btn_shape'], 'geçersiz şekil varsayılan' );
		qrms_assert_same( 12, (int) $temiz['btn_font_size'], 'buton punto' );
		qrms_assert_same( 'QR MENU', $hfb->get_footer_options()['brand_line1'], 'depo değişmedi' );
	}
);

qrms_test(
	'garson butonları oturum yokken uyarı basar, önizlemede stilli görünür',
	function () {
		$hfb  = qrms_hfb();
		$opts = $hfb->get_footer_options();
		$opts['call_enabled']      = 1;
		$opts['call_garson_label'] = 'Garson Çağır';
		$opts['call_hesap_label']  = 'Hesap İste';

		$html = $hfb->render_footer( $opts );
		qrms_assert_contains( 'Lütfen QR kodunu okutarak masanızdan erişin', $html, 'oturumsuz uyarı' );
		qrms_assert_true( false === strpos( $html, 'data-qmo-cagri' ), 'sahte çağrı yok' );

		$GLOBALS['qrms_test']['is_admin'] = true;
		$onizleme = $hfb->render_footer( $opts );
		qrms_assert_contains( 'hfb-footer__call-btn', $onizleme, 'önizlemede buton' );
		qrms_assert_contains( 'Garson Çağır', $onizleme, 'garson metni' );
		qrms_assert_contains( 'Hesap İste', $onizleme, 'hesap metni' );
		qrms_assert_true( false === strpos( $onizleme, 'data-qmo-cagri' ), 'önizlemede AJAX bağlanmaz' );
		qrms_assert_contains( 'hfb-icon--call', $onizleme, 'önizlemede zil/fiş ikonu' );
		qrms_assert_contains( 'hfb-footer__cq', $onizleme, 'sütun kabı çağrı çubuğunun dışında' );
	}
);

qrms_test(
	'footer çağrı buton stili admin altı alanı CSS değişkenine basılır',
	function () {
		$hfb  = qrms_hfb();
		$opts = $hfb->get_footer_options();
		$opts['call_enabled']    = 1;
		$opts['btn_bg_color']    = '#112233';
		$opts['btn_text_color']  = '#fefefe';
		$opts['btn_shape']       = 'rounded';
		$opts['btn_font_family'] = 'Inter';
		$opts['btn_font_size']   = 18;
		$opts['btn_font_weight'] = 700;

		$html = $hfb->render_footer( $opts );

		qrms_assert_contains( '--hfb-btn-bg:#112233', $html, 'zemin rengi değişkeni' );
		qrms_assert_contains( '--hfb-btn-color:#fefefe', $html, 'yazı rengi değişkeni' );
		qrms_assert_contains( '--hfb-btn-radius:10px', $html, 'yuvarlatılmış şekil' );
		qrms_assert_contains( '--hfb-btn-font:', $html, 'font yığını basılır' );
		qrms_assert_contains( 'Inter', $html, 'Inter font yığını' );
		qrms_assert_contains( '--hfb-btn-size:18px', $html, 'punto' );
		qrms_assert_contains( '--hfb-btn-weight:700', $html, 'kalınlık' );
		qrms_assert_contains( 'hfb-footer__call--warn', $html, 'oturumsuz uyarı sınıfı (sticky seçici)' );
		qrms_assert_true( false === strpos( $html, 'data-qmo-cagri' ), 'oturumsuzken AJAX yok' );

		$GLOBALS['qrms_test']['is_admin'] = true;
		$onizleme = $hfb->render_footer( $opts );
		qrms_assert_contains( '--hfb-btn-bg:#112233', $onizleme, 'önizlemede zemin' );
		qrms_assert_contains( '--hfb-btn-radius:10px', $onizleme, 'önizlemede şekil' );
		qrms_assert_contains( 'hfb-icon--call', $onizleme, 'önizlemede ikon' );
		qrms_assert_true( false === strpos( $onizleme, 'data-qmo-cagri' ), 'önizlemede AJAX yok' );
	}
);

qrms_test(
	'AJAX önizleme footer buton stil alanlarını döndürür',
	function () {
		$hfb = qrms_hfb();
		$GLOBALS['qrms_test']['is_admin'] = true;

		$_POST = array(
			'nonce' => 'test',
			'data'  => array(
				'hfb_footer_call_enabled'     => '1',
				'hfb_footer_call_garson_label' => 'Garson',
				'hfb_footer_btn_bg_color'     => '#445566',
				'hfb_footer_btn_text_color'   => '#aabbcc',
				'hfb_footer_btn_shape'        => 'square',
				'hfb_footer_btn_font_family'  => 'Poppins',
				'hfb_footer_btn_font_size'    => '20',
				'hfb_footer_btn_font_weight'  => '500',
			),
		);

		$hfb->ajax_preview();
		$yanit = $GLOBALS['qrms_test']['json'];

		qrms_assert_true( is_array( $yanit ) && ! empty( $yanit['success'] ), 'başarılı yanıt' );
		$footer = $yanit['data']['footer'];
		qrms_assert_contains( '--hfb-btn-bg:#445566', $footer, 'önizleme zemin' );
		qrms_assert_contains( '--hfb-btn-color:#aabbcc', $footer, 'önizleme yazı' );
		qrms_assert_contains( '--hfb-btn-radius:0', $footer, 'önizleme köşeli' );
		qrms_assert_contains( 'Poppins', $footer, 'önizleme font' );
		qrms_assert_contains( '--hfb-btn-size:20px', $footer, 'önizleme punto' );
		qrms_assert_contains( '--hfb-btn-weight:500', $footer, 'önizleme kalınlık' );
		qrms_assert_contains( 'hfb-footer__call-btn', $footer, 'önizlemede buton (admin)' );
	}
);

qrms_test(
	'footer çağrı butonu CSS admin değişkenlerini okur, sticky her viewport\'ta',
	function () {
		$css = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/header-footer-builder/assets/css/frontend.css'
		);

		qrms_assert_contains( 'background-color: var(--hfb-btn-bg, var(--hfb-gold))', $css, 'zemin değişkeni' );
		qrms_assert_contains( 'border: 1.5px solid var(--hfb-btn-bg, var(--hfb-gold))', $css, 'kenarlık accent' );
		qrms_assert_contains( 'border-radius: var(--hfb-btn-radius, 999px)', $css, 'şekil değişkeni, sabit 50px yok' );
		qrms_assert_contains( 'font-family: var(--hfb-btn-font, inherit)', $css, 'font değişkeni' );
		qrms_assert_contains( 'font-size: var(--hfb-btn-size, 14px)', $css, 'punto değişkeni' );
		qrms_assert_contains( 'font-weight: var(--hfb-btn-weight, 600)', $css, 'kalınlık değişkeni' );
		qrms_assert_contains( 'linear-gradient(180deg, rgba(255, 255, 255, 0.08), rgba(0, 0, 0, 0.08))', $css, 'gradient overlay' );
		qrms_assert_contains( 'background-blend-mode: overlay', $css, 'blend overlay' );
		qrms_assert_contains( 'transform: translateY(-3px)', $css, 'masaüstü hover kalkış' );
		qrms_assert_contains( '@keyframes btn-spin', $css, 'spinner animasyonu' );
		qrms_assert_contains( '.hfb-footer__call-btn.is-disabled', $css, 'disabled durumu' );
		qrms_assert_contains( '.hfb-footer__call-btn.is-success', $css, 'success durumu' );
		// Çubuk artık masaüstü dâhil her genişlikte sabit: kurallar bir
		// kırılımın içinde değil, satır başında (girintisiz) global durur.
		qrms_assert_true( false === strpos( $css, '@media (max-width: 767px)' ), 'mobil-özel sticky kırılımı kalktı' );
		qrms_assert_contains( "\n.hfb-footer__call-wrap:has(.qmo-cagri-bar),", $css, 'butonlu wrap her viewport\'ta sticky' );
		qrms_assert_contains( "\n.hfb-footer__call-wrap:has(.hfb-footer__call--warn) {", $css, 'uyarı wrap her viewport\'ta sticky' );
		qrms_assert_contains( 'position: fixed', $css, 'ekrana sabit' );
		qrms_assert_contains( "\nbody:has(.hfb-footer__call-wrap .qmo-cagri-bar):not(.wp-admin),", $css, 'body boşluğu kırılımsız' );
		qrms_assert_contains( 'padding-bottom: calc(66px + env(safe-area-inset-bottom, 0px))', $css, 'body boşluğu' );
		qrms_assert_contains( "\n.wp-admin .hfb-footer__call-wrap:has(.qmo-cagri-bar),", $css, 'admin önizlemesi akışta kalır' );
		qrms_assert_contains( 'border-radius: 12px', $css, 'köşeli-yuvarlak buton' );
		qrms_assert_contains( 'flex: 1 1 0', $css, 'iki buton eşit genişlik' );
		qrms_assert_contains( 'transform: scale(0.97)', $css, ':active dokunma' );
		qrms_assert_contains( 'background: rgba(10, 10, 12, 0.82)', $css, 'sticky bar zemini' );
		qrms_assert_true( false === strpos( $css, 'border-radius: 50px' ), 'sabit 50px radius yok' );
		qrms_assert_true( false === strpos( $css, '#d4af37' ), 'hardcoded altın yok' );
		qrms_assert_contains( 'container-name: hfb-footer', $css, 'footer kap sorgusu durur' );
		qrms_assert_contains( '.hfb-footer__cq', $css, 'kap çağrı çubuğunun dışında' );

		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/buttons.js' );
		qrms_assert_contains( '[data-qmo-cagri]', $js, 'tıklama seçicisi durur' );
		qrms_assert_contains( "getAttribute( 'data-qmo-cagri' )", $js, 'tip okuma durur' );
		qrms_assert_contains( "'hesap' === tip", $js, 'hesap action durur' );

		$kayit = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/trait-settings-page.php' );
		qrms_assert_contains( "function_exists( 'qmo_tum_onbellek_temizle' )", $kayit, 'kaydet sonrası ortak önbellek temizliği' );
		qrms_assert_contains( 'qmo_tum_onbellek_temizle()', $kayit, 'qmo_tum_onbellek_temizle çağrılır' );

		$yardimci = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/helpers.php' );
		qrms_assert_contains( "function_exists( 'rocket_clean_domain' )", $yardimci, 'WP Rocket ortak yardımcıda' );
	}
);

qrms_test(
	'AJAX önizleme footer saat ve başlık alanlarını döndürür',
	function () {
		$hfb = qrms_hfb();

		$_POST = array(
			'nonce' => 'test',
			'data'  => array(
				'hfb_footer_links_title'   => 'Hızlı Menü',
				'hfb_footer_hours_title'   => 'Çalışma Saatlerimiz',
				'hfb_footer_contact_title' => 'İletişim',
				'hfb_footer_address'       => 'Test Sokak 1',
				'hfb_footer_copyright'     => '© 2026 Önizleme',
			),
		);

		$hfb->ajax_preview();
		$yanit = $GLOBALS['qrms_test']['json'];

		qrms_assert_true( is_array( $yanit ) && ! empty( $yanit['success'] ), 'başarılı yanıt' );
		qrms_assert_contains( 'Hızlı Menü', $yanit['data']['footer'], 'menü başlığı' );
		qrms_assert_contains( 'Çalışma Saatlerimiz', $yanit['data']['footer'], 'saat başlığı' );
		qrms_assert_contains( 'Test Sokak 1', $yanit['data']['footer'], 'adres' );
		qrms_assert_contains( '© 2026 Önizleme', $yanit['data']['footer'], 'telif' );
	}
);




/* P2 çeviri testleri (birleşme sonrası taşındı) */

echo "\nQR Çeviri (P0 köprü / HFB)\n";

require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/ui-stringler.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/fiyat.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/settings.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/veri-kaynaklar.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/kaynaklar.php';

qrms_test(
	'HFB chrome ui_string kataloğunda; yeni item_type yok',
	function () {
		$ui = rma_ceviri_varsayilan_ui_metinleri();
		foreach ( array( 'Ana menü', 'Menüyü aç', 'Mobil menü', 'Menüyü kapat', 'Lütfen QR kodunu okutarak masanızdan erişin', 'Hızlı Menü', 'Çalışma Saatlerimiz', 'İletişim' ) as $metin ) {
			qrms_assert_true( in_array( $metin, $ui, true ), $metin );
		}
		$tipler = rma_ceviri_modul_tipleri();
		qrms_assert_false( isset( $tipler['hfb'] ), 'yeni hfb tipi yok' );
		qrms_assert_false( isset( $tipler['header'] ), 'yeni header tipi yok' );
	}
);

qrms_test(
	'HFB aria ve uyarı ui_string köprüsünde; Garson chat kaldı',
	function () {
		$front = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/trait-frontend.php' );
		$js    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/assets/js/frontend.js' );

		qrms_assert_contains( "hfb_cevir_ui( __( 'Ana menü', 'qrms' ) )", $front, 'Ana menü' );
		qrms_assert_contains( "hfb_cevir_ui( __( 'Menüyü aç', 'qrms' ) )", $front, 'Menüyü aç' );
		qrms_assert_contains( "hfb_cevir_ui( __( 'Mobil menü', 'qrms' ) )", $front, 'Mobil menü' );
		qrms_assert_contains( "hfb_cevir_ui( __( 'Menüyü kapat', 'qrms' ) )", $front, 'Menüyü kapat' );
		qrms_assert_contains( "hfb_cevir_ui( __( 'Lütfen QR kodunu okutarak masanızdan erişin', 'qrms' ) )", $front, 'QR uyarı' );
		qrms_assert_contains( 'function hfb_cevir_option_varsayilan', $front, 'option ayrımı' );
		qrms_assert_contains( 'rma_ceviri_option( $field, $deger )', $front, 'option öne bakar' );
		qrms_assert_contains( 'Faz 9 temizlik', $front, 'geçici iki tip notu' );
		qrms_assert_contains( 'rma_ceviri_modul( \'ui_string\'', $front, 'ui_string' );
		qrms_assert_contains( 'qmo_ceviri_chat( $garson_yedek )', $front, '5B Garson' );
		qrms_assert_contains( 'qmo_ceviri_chat( $hesap_yedek )', $front, '5B Hesap' );
		qrms_assert_false( (bool) preg_match( '/hfb_cevir_ui\( \$garson/', $front ), 'Garson tekrar sarılmadı' );
		qrms_assert_contains( 'closeBtn.getAttribute(\'aria-label\')', $js, 'JS kapat PHP\'den' );
		qrms_assert_false( (bool) preg_match( "/setAttribute\('data-label-close', 'Menüyü kapat'\)/", $js ), 'sabit kapat kalmadı' );
	}
);

qrms_test(
	'HFB option varsayılanı çevrilir, yönetici metni dokunulmaz',
	function () {
		$hfb  = qrms_hfb();
		$opts = $hfb->get_footer_options();

		$opts['links_title']   = 'Hızlı Menü';
		$opts['hours_title']   = 'Çalışma Saatlerimiz';
		$opts['contact_title'] = 'İletişim';
		$varsayilan            = $hfb->render_footer( $opts );
		qrms_assert_contains( 'Hızlı Menü', $varsayilan, 'kod sabiti görünür (tablo yok)' );
		qrms_assert_contains( 'Çalışma Saatlerimiz', $varsayilan, 'saat sabiti' );

		$opts['links_title'] = 'Benim Menüm';
		$ozel                = $hfb->render_footer( $opts );
		qrms_assert_contains( 'Benim Menüm', $ozel, 'yönetici metni durur' );
		qrms_assert_false( false !== strpos( $ozel, '>Hızlı Menü<' ), 'varsayılan başlık basılmaz' );

		qrms_assert_same(
			'Lütfen QR kodunu okutarak masanızdan erişin',
			rma_ceviri_modul( 'ui_string', 'Lütfen QR kodunu okutarak masanızdan erişin' ),
			'tablo yokken Türkçe'
		);
	}
);

qrms_test(
	'hamburger buton etiketi option köprüsünde; blk kimliği kararlı',
	function () {
		$front = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/trait-frontend.php' );
		$admin = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/trait-admin.php' );

		qrms_assert_contains( 'rma_ceviri_hamburger_blok_field', $front, 'field yolu' );
		qrms_assert_contains( '$label === $varsayilan', $front, 'varsayılan Buton ayrımı' );
		qrms_assert_contains( '$this->hfb_cevir_ui( $varsayilan )', $front, 'Buton ui_string' );
		qrms_assert_contains( "rma_ceviri_option( rma_ceviri_hamburger_blok_field( \$block_id, 'label' )", $front, 'köprü çağrısı' );
		$ui = rma_ceviri_varsayilan_ui_metinleri();
		qrms_assert_true( in_array( 'Buton', $ui, true ), 'Buton ui_string katalogda' );
		qrms_assert_contains( "rma_ceviri_hamburger_blok_field( \$id, 'label' )", $admin, 'bayat uyarı' );

		update_option(
			'hfb_hamburger_options',
			array(
				'blocks' => array(
					array(
						'id'      => 'blk_9',
						'type'    => 'button',
						'enabled' => true,
						'label'   => 'Rezervasyon Oluştur',
					),
				),
			)
		);

		$defter = rma_ceviri_option_defteri();
		qrms_assert_true( isset( $defter['hfb_hamburger.block.blk_9.label'] ), 'defter satırı' );
		qrms_assert_same(
			'blk_9',
			$defter['hfb_hamburger.block.blk_9.label']['blok_id'],
			'kararlı blok id'
		);
		qrms_assert_same(
			'Rezervasyon Oluştur',
			rma_ceviri_option_guncel( 'hfb_hamburger.block.blk_9.label' ),
			'canlı etiket'
		);

		$satirlar = iterator_to_array( rma_ceviri_option_satirlari() );
		$fieldler = array();
		foreach ( $satirlar as $satir ) {
			$fieldler[ $satir['field'] ] = $satir['original'];
		}
		qrms_assert_same( 'Rezervasyon Oluştur', $fieldler['hfb_hamburger.block.blk_9.label'], 'CSV satırı' );
	}
);

qrms_test(
	'HFB P1 option metinleri defterde; marka ui_string, özel metin option',
	function () {
		$front = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/trait-frontend.php' );
		$admin = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/trait-admin.php' );

		qrms_assert_contains( 'function hfb_cevir_option_metin', $front, 'özel metin köprü' );
		qrms_assert_contains( "hfb_cevir_option_metin( (string) \$opts['description'], 'hfb_footer.description' )", $front, 'footer açıklama' );
		qrms_assert_contains( "hfb_cevir_option_metin( (string) \$opts['copyright'], 'hfb_footer.copyright' )", $front, 'footer telif' );
		qrms_assert_contains( 'hfb_copyright_goruntule', $front, 'telif yıl yerleştirme' );
		qrms_assert_contains( 'çeviri sistemine dahil değildir', $admin, 'HTML blok uyarısı' );
		qrms_assert_contains( "rma_ceviri_hamburger_blok_field( \$block_id, 'description' )", $front, 'logo açıklama' );
		qrms_assert_contains( "hfb_header.brand_line1", $admin, 'header marka uyarı' );
		qrms_assert_contains( "hfb_footer.copyright", $admin, 'telif uyarı' );

		$ui = rma_ceviri_varsayilan_ui_metinleri();
		qrms_assert_true( in_array( 'QR MENU', $ui, true ), 'QR MENU ui_string' );
		qrms_assert_true( in_array( 'OFFİCİAL', $ui, true ), 'OFFİCİAL ui_string' );

		update_option(
			'hfb_footer_options',
			array(
				'description' => 'Lezzetin adresi',
				'copyright'   => '© 2026 Test Restoran',
				'brand_line1' => 'QR MENU',
			)
		);
		update_option(
			'hfb_hamburger_options',
			array(
				'blocks' => array(
					array(
						'id'          => 'blk_3',
						'type'        => 'logo',
						'enabled'     => true,
						'description' => 'Panel tanıtımı',
					),
				),
			)
		);

		$defter = rma_ceviri_option_defteri();
		qrms_assert_true( isset( $defter['hfb_footer.description'] ), 'footer açıklama defteri' );
		qrms_assert_true( isset( $defter['hfb_footer.copyright'] ), 'footer telif defteri' );
		qrms_assert_true( isset( $defter['hfb_hamburger.block.blk_3.description'] ), 'logo açıklama defteri' );
		qrms_assert_same( 'Lezzetin adresi', rma_ceviri_option_guncel( 'hfb_footer.description' ), 'canlı açıklama' );

		qrms_assert_contains( 'hfb_cevir_option_varsayilan', $front, 'marka köprü' );
		qrms_assert_contains( ".brand_line1'", $front, 'marka field birleşimi' );
	}
);

qrms_test(
	'offcanvas açıkken gövde kaydırma kilidi position:fixed ile korunur',
	function () {
		$js     = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/assets/js/frontend.js' );
		$css    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/assets/css/frontend.css' );
		$ceviri = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/assets/js/ceviri.js' );

		qrms_assert_contains( 'function lockBodyScroll', $js, 'kilitleme fonksiyonu' );
		qrms_assert_contains( 'function unlockBodyScroll', $js, 'kilit açma' );
		qrms_assert_contains( 'hfb-scroll-locked', $js, 'gövde sınıfı' );
		qrms_assert_contains( "body.style.position = 'fixed'", $js, 'fixed gövde' );
		qrms_assert_contains( 'window.scrollTo', $js, 'kapanışta konum geri' );
		qrms_assert_contains( 'paddingRight', $js, 'scrollbar telafisi' );
		qrms_assert_contains( 'hfb-mobile-panel__nav a', $js, 'menü linki kapanış' );
		qrms_assert_false(
			(bool) preg_match( '/if\s*\(\s*!editor\s*\)\s*\{\s*document\.body\.style\.overflow\s*=\s*[\'"]hidden[\'"];\s*\}/', $js ),
			'yalnızca overflow kilidi kalmadı'
		);

		qrms_assert_contains( 'body.hfb-scroll-locked', $css, 'kilit CSS' );

		qrms_assert_contains( 'shouldIgnoreScrollForPanelClose', $ceviri, 'offcanvas içi scroll filtresi' );
		qrms_assert_contains( '.hfb-mobile-panel', $ceviri, 'panel seçici' );
	}
);

echo "\nQR Çeviri (P1 yönetici verisi)\n";
