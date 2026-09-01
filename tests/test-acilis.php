<?php
/**
 * Açılış Ekranı testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/qr-acilis-ekrani/includes/class-acilis-ekrani.php';

echo "\nAçılış Ekranı\n";

/**
 * Modülün taze bir örneği (hook kaydı yapmadan).
 *
 * @return QRMS_Acilis_Ekrani
 */
function qrms_ae() {
	return new QRMS_Acilis_Ekrani();
}

/**
 * Kuyruğa alınmış stili handle'ına göre bulur.
 *
 * @param string $handle Stil handle'ı.
 * @return array|null
 */
function qrms_ae_style( $handle ) {
	foreach ( $GLOBALS['qrms_test']['styles'] as $style ) {
		if ( $handle === $style['handle'] ) {
			return $style;
		}
	}

	return null;
}

/**
 * Bir yönetim sayfasını POST ile kaydeder ve çıktısını döndürür.
 *
 * @param string $slug Sayfa slug'ı.
 * @param array  $post Gönderilen alanlar.
 * @return string Sayfanın HTML çıktısı.
 */
function qrms_ae_submit( $slug, array $post ) {
	$_POST = array_merge( array( 'qrms_ae_submit' => '1' ), $post );

	$method = 'render_' . str_replace( '-', '_', $slug );

	ob_start();
	qrms_ae()->$method();

	return ob_get_clean();
}

qrms_test(
	'modül loader sözleşmesine uyar: slug, dosya ve init fonksiyonu',
	function () {
		qrms_assert_true(
			in_array( 'qr-acilis-ekrani', QRMS_Helpers::MODULE_SLUGS, true ),
			'slug bilinen modüller arasında'
		);
		qrms_assert_true( QRMS_Module_Loader::module_file_exists( 'qr-acilis-ekrani' ), 'module.php diskte' );
		qrms_assert_same(
			'qrms_module_qr_acilis_ekrani_init',
			QRMS_Module_Loader::get_init_function( 'qr-acilis-ekrani' ),
			'init fonksiyon adı'
		);

		update_option( 'qrms_active_modules', array( 'qr-acilis-ekrani' ) );

		qrms_assert_same(
			array( 'qr-acilis-ekrani' ),
			QRMS_Module_Loader::load_modules(),
			'aktifken yüklenir'
		);
	}
);

qrms_test(
	'ayar ekranları gizli alt sayfa olarak kaydedilir, menüde satırları olmaz',
	function () {
		update_option( 'qrms_active_modules', array( 'qr-acilis-ekrani' ) );

		QRMS_Admin::register_menu();
		qrms_ae()->register_admin_pages();

		$slugs = qrms_registered_submenu_slugs();

		foreach ( array_keys( qrms_ae()->admin_pages() ) as $slug ) {
			qrms_assert_true( in_array( $slug, $slugs, true ), $slug . ' kayıtlı' );
		}

		// Menüde görünmemeleri suite'in işi: beyaz listede olmadıkları için
		// admin_head'de düşürülürler.
		QRMS_Admin::hide_module_subpages();

		foreach ( array_keys( qrms_ae()->admin_pages() ) as $slug ) {
			qrms_assert_true(
				in_array( $slug, $GLOBALS['qrms_test']['removed'], true ),
				$slug . ' menüden düşer'
			);
		}
	}
);

qrms_test(
	'modül lisansta aktif değilken hiçbir ekran kaydedilmez',
	function () {
		// "Açılış Ekranı" satırı yoksa $submenu de boştur; ekranların
		// kaydedilmesi menüde ölü satır bırakırdı.
		qrms_ae()->register_admin_pages();

		qrms_assert_same( array(), $GLOBALS['qrms_test']['submenus'], 'kayıt yok' );
	}
);

qrms_test(
	'hub tüm ekranları kart olarak basar ve ikonları dashicon\'dur',
	function () {
		ob_start();
		qrms_ae()->render_hub();
		$html = ob_get_clean();

		qrms_assert_contains( 'qrms-hub-grid', $html, 'ortak kart ızgarası' );
		qrms_assert_contains( 'qrms-hub-stats', $html, 'özet kutuları' );

		foreach ( qrms_ae()->admin_pages() as $slug => $page ) {
			qrms_assert_contains( 'page=' . $slug, $html, $slug . ' kartı' );
			qrms_assert_same( 0, strpos( $page['icon'], 'dashicons-' ), $slug . ' ikonu' );
		}
	}
);

qrms_test(
	'bir sayfayı kaydetmek diğer sayfaların ayarlarını silmez',
	function () {
		// Bu, sekmeli tek formdan dört ayrı sayfaya geçerken doğan asıl risk:
		// POST'ta bulunmayan onay kutusu "kapalı" sayılırsa başka bir sayfada
		// yapılmış seçim sessizce silinir.
		update_option(
			'splash_screen_options',
			array(
				'payment_methods'       => array( 'nakit', 'kart' ),
				'social_media_active'   => array( 'instagram' ),
				'social_media'          => array( 'instagram' => 'https://instagram.com/x' ),
				'btn_surface_apply_cta' => 1,
			)
		);

		qrms_ae_submit( 'qrms-ae-davranis', array( 'wifi_password' => 'misafir123' ) );

		$opts = get_option( 'splash_screen_options' );

		qrms_assert_same( 'misafir123', $opts['wifi_password'], 'kendi alanı yazılır' );
		qrms_assert_same( array( 'nakit', 'kart' ), $opts['payment_methods'], 'ödeme seçimi korunur' );
		qrms_assert_same( array( 'instagram' ), $opts['social_media_active'], 'sosyal hesap Ayarlar kaydında korunur' );
		qrms_assert_same( 1, $opts['btn_surface_apply_cta'], 'görünüm kutusu korunur' );

		// Buna karşılık SAHİBİ sayfa gönderilince kutu gerçekten kapanabilmeli.
		qrms_ae_submit( 'qrms-ae-gorunum', array( 'bg_color' => '#101010' ) );

		$opts = get_option( 'splash_screen_options' );

		qrms_assert_same( 0, $opts['btn_surface_apply_cta'], 'sahibi sayfa kutuyu kapatır' );
		qrms_assert_same( array( 'nakit', 'kart' ), $opts['payment_methods'], 'ödeme yine korunur' );
	}
);

qrms_test(
	'ödeme sayfası gönderildiğinde işaretsiz yöntemler temizlenir',
	function () {
		update_option( 'splash_screen_options', array( 'payment_methods' => array( 'nakit', 'kart' ) ) );

		qrms_ae_submit( 'qrms-ae-odeme', array( 'payment_methods' => array( 'sodexo' ) ) );

		qrms_assert_same(
			array( 'sodexo' ),
			get_option( 'splash_screen_options' )['payment_methods'],
			'yalnızca gönderilen kalır'
		);

		qrms_ae_submit( 'qrms-ae-odeme', array() );

		qrms_assert_same(
			array(),
			get_option( 'splash_screen_options' )['payment_methods'],
			'hiçbiri işaretsizse boşalır'
		);
	}
);

qrms_test(
	'sayısal ayarlar sınırlarına kırpılır, bozuk renk varsayılana düşer',
	function () {
		qrms_ae_submit(
			'qrms-ae-gorunum',
			array(
				'loader_size'         => 999,
				'logo_bar_height'     => 1,
				'bg_overlay_strength' => 250,
				'btn_surface_opacity' => 150,
				'bg_color'            => 'kırmızı',
				'loader_type'         => 'diskoTopu',
			)
		);

		$opts = get_option( 'splash_screen_options' );

		qrms_assert_same( 44, $opts['loader_size'], 'gösterge boyutu üst sınıra' );
		qrms_assert_same( 48, $opts['logo_bar_height'], 'şerit yüksekliği alt sınıra' );
		qrms_assert_same( 100, $opts['bg_overlay_strength'], 'karartma üst sınıra' );
		qrms_assert_same( 100, $opts['btn_surface_opacity'], 'opaklık üst sınıra' );
		qrms_assert_same( '#f7f9fc', $opts['bg_color'], 'geçersiz renk varsayılana' );
		qrms_assert_same( 'spinner', $opts['loader_type'], 'beyaz liste dışı tip varsayılana' );
	}
);

qrms_test(
	'sosyal hesap sırası işaretlenme sırasıdır ve altı hesapla sınırlıdır',
	function () {
		qrms_ae_submit(
			'qrms-ae-sosyal',
			array(
				'social_media_active'    => array( 'instagram', 'facebook', 'youtube', 'x', 'tiktok', 'whatsapp', 'linkedin' ),
				'social_media_order'     => 'whatsapp,instagram,facebook,youtube,x,tiktok,linkedin',
				'social_media_url_whatsapp' => 'https://wa.me/900000000',
			)
		);

		$opts = get_option( 'splash_screen_options' );

		qrms_assert_same( 6, count( $opts['social_media_active'] ), 'en fazla altı hesap' );
		qrms_assert_same( 'whatsapp', $opts['social_media_active'][0], 'işaretlenme sırası korunur' );
		qrms_assert_false(
			in_array( 'linkedin', $opts['social_media_active'], true ),
			'yedinci hesap düşer'
		);
	}
);

qrms_test(
	'eski üç alanlı sosyal kayıt yeni sisteme kendiliğinden taşınır',
	function () {
		// v3.5 ve öncesinden yükseltilen kurulumda social_media_active hiç
		// yazılmamıştır; eski bağlantılar tekrar kaydetmeden görünmelidir.
		update_option(
			'splash_screen_options',
			array(
				'social_links' => array(
					'facebook'  => 'https://facebook.com/lokanta',
					'instagram' => 'https://instagram.com/lokanta',
					'twitter'   => 'https://x.com/lokanta',
				),
			)
		);

		ob_start();
		qrms_ae()->render_splash_preview();
		$html = ob_get_clean();

		qrms_assert_contains( 'https://facebook.com/lokanta', $html, 'facebook rozeti' );
		qrms_assert_contains( 'https://instagram.com/lokanta', $html, 'instagram rozeti' );
		qrms_assert_contains( 'https://x.com/lokanta', $html, 'twitter -> x rozeti' );
	}
);

qrms_test(
	'kritik head çıktısı çerezden BAĞIMSIZDIR (tam sayfa cache güvenliği)',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option( 'splash_screen_options', array( 'bg_color' => '#101010' ) );

		ob_start();
		qrms_ae()->print_critical_head();
		$cerezsiz = ob_get_clean();

		$_COOKIE['splash_dismissed'] = '1';

		ob_start();
		qrms_ae()->print_critical_head();
		$cerezli = ob_get_clean();

		unset( $_COOKIE['splash_dismissed'] );

		qrms_assert_same( $cerezsiz, $cerezli, 'çıktı her ziyaretçide aynı' );
		qrms_assert_contains( '--sp-bg: #101010', $cerezsiz, 'değişkenler basılır' );
		qrms_assert_contains( 'splash-loading', $cerezsiz, 'FOUC sınıfı betikten eklenir' );
		qrms_assert_contains( 'splash_dismissed', $cerezsiz, 'karar client-side verilir' );
	}
);

qrms_test(
	'splash yalnızca ana sayfada basılır',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = false;

		ob_start();
		qrms_ae()->print_critical_head();
		qrms_ae()->handle_frontend();

		qrms_assert_same( '', ob_get_clean(), 'diğer sayfalarda hiçbir çıktı yok' );
	}
);

qrms_test(
	'ön yüzde overlay gizli başlar, önizlemede isPreview bayrağı taşır',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option( 'splash_screen_options', array( 'button_links' => array( 'btn1' => 'https://restoran.test/menu' ) ) );

		ob_start();
		qrms_ae()->handle_frontend();
		$on_yuz = ob_get_clean();

		ob_start();
		qrms_ae()->render_splash_preview();
		$onizleme = ob_get_clean();

		qrms_assert_contains( 'style="display:none"', $on_yuz, 'ön yüzde gizli başlar' );
		qrms_assert_false( strpos( $on_yuz, 'data-preview' ), 'ön yüzde önizleme bayrağı yok' );

		qrms_assert_contains( 'data-preview="1"', $onizleme, 'önizlemede bayrak var' );
		qrms_assert_false( strpos( $onizleme, 'style="display:none"' ), 'önizleme gizli başlamaz' );

		// Önizleme frontend'in taklidi değil, aynı markup'ıdır.
		qrms_assert_contains( 'https://restoran.test/menu', $on_yuz, 'ön yüzde CTA adresi' );
		qrms_assert_contains( 'https://restoran.test/menu', $onizleme, 'önizlemede aynı adres' );
	}
);

qrms_test(
	'renk şeması: arkaplan görseli koyu, açık zemin light kabul edilir',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;

		update_option( 'splash_screen_options', array( 'bg_scheme' => 'auto', 'bg_image' => 12 ) );
		ob_start();
		qrms_ae()->handle_frontend();
		qrms_assert_contains( 'splash-scheme-dark', ob_get_clean(), 'görsel varken koyu' );

		update_option( 'splash_screen_options', array( 'bg_scheme' => 'auto', 'bg_color' => '#ffffff' ) );
		ob_start();
		qrms_ae()->handle_frontend();
		qrms_assert_contains( 'splash-scheme-light', ob_get_clean(), 'açık zeminde light' );
	}
);

qrms_test(
	'ödeme satırı: yöntem yoksa hiç basılmaz, yazı modunda ikon gelmez',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;

		update_option( 'splash_screen_options', array( 'payment_methods' => array() ) );
		ob_start();
		qrms_ae()->handle_frontend();
		qrms_assert_false( strpos( ob_get_clean(), 'splash-pay-row' ), 'seçim yoksa satır yok' );

		update_option(
			'splash_screen_options',
			array(
				'payment_methods'      => array( 'nakit' ),
				'payment_display_mode' => 'text_only',
			)
		);
		ob_start();
		qrms_ae()->handle_frontend();
		$html = ob_get_clean();

		qrms_assert_contains( 'splash-pay-row', $html, 'satır basılır' );
		qrms_assert_contains( 'Nakit', $html, 'etiket basılır' );
		qrms_assert_false( strpos( $html, 'splash-pay-icon' ), 'yazı modunda ikon yok' );
	}
);

qrms_test(
	'bağlantısı olmayan rozet DOM\'a hiç girmez',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option(
			'splash_screen_options',
			array(
				'button_links' => array( 'btn2' => 'tel:+900000000', 'btn3' => '', 'btn4' => '' ),
			)
		);

		ob_start();
		qrms_ae()->handle_frontend();
		$html = ob_get_clean();

		qrms_assert_contains( 'tel:+900000000', $html, 'adresi olan rozet basılır' );
		// Wifi rozeti adres almaz; her zaman basılır.
		qrms_assert_contains( 'id="wifi-btn"', $html, 'wifi rozeti her zaman var' );
		qrms_assert_same( 2, substr_count( $html, 'sp-action-circle' ), 'yalnızca iki rozet' );
	}
);

qrms_test(
	'eski eklentinin adresleri yeni sayfalara yönlendirilir',
	function () {
		$_GET = array( 'page' => 'splash-screen', 'tab' => 'odeme' );

		try {
			qrms_ae()->maybe_redirect_legacy_pages();
			qrms_assert_true( false, 'yönlendirme bekleniyordu' );
		} catch ( QRMS_Test_Redirect $e ) {
			qrms_assert_contains( 'page=qrms-ae-odeme', $e->getMessage(), 'sekme karşılığı sayfaya' );
		}

		$_GET = array( 'page' => 'splash-screen-links' );

		try {
			qrms_ae()->maybe_redirect_legacy_pages();
			qrms_assert_true( false, 'yönlendirme bekleniyordu' );
		} catch ( QRMS_Test_Redirect $e ) {
			qrms_assert_contains( 'page=qrms-ae-butonlar', $e->getMessage(), 'eski bağlantılar sayfası' );
		}

		$GLOBALS['qrms_test']['redirects'] = array();
		$_GET = array( 'page' => 'qrms-overview' );
		qrms_ae()->maybe_redirect_legacy_pages();
		qrms_assert_same( array(), $GLOBALS['qrms_test']['redirects'], 'başka sayfaya dokunulmaz' );
	}
);

qrms_test(
	'varlıklar dosya bazlı sürümle ve yalnızca kendi ekranlarında yüklenir',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		qrms_ae()->enqueue_frontend_assets();

		$splash = qrms_ae_style( 'qrms-ae-splash' );

		qrms_assert_true( null !== $splash, 'ön yüz stili' );
		qrms_assert_same(
			QRMS_VERSION . '.' . filemtime( QRMS_PLUGIN_DIR . 'modules/qr-acilis-ekrani/assets/css/splash.css' ),
			$splash['ver'],
			'sürüm dosyanın kendi zamanını taşır'
		);

		// Modülün ekranında değilken yönetim varlıkları kuyruğa girmez.
		$_GET = array( 'page' => 'qrms-overview' );
		qrms_ae()->admin_enqueue_assets();
		qrms_assert_same( null, qrms_ae_style( 'qrms-ae-admin' ), 'başka ekranda yüklenmez' );

		$_GET = array( 'page' => 'qrms-ae-gorunum' );
		qrms_ae()->admin_enqueue_assets();
		qrms_assert_true( null !== qrms_ae_style( 'qrms-ae-admin' ), 'kendi ekranında yüklenir' );
	}
);


/* ---------------------------------------------------------------------------
 * 15. Açılış Ekranı — TR/EN dil düğmesi
 * ------------------------------------------------------------------------ */

echo "\nAçılış Ekranı (TR/EN)\n";

qrms_test(
	'düğme kapalıyken markup\'a tek bir dil niteliği bile girmez',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option( 'splash_screen_options', array( 'lang_toggle' => 0 ) );

		ob_start();
		qrms_ae()->handle_frontend();
		$html = ob_get_clean();

		qrms_assert_false( strpos( $html, 'splash-lang' ), 'düğme yok' );
		qrms_assert_false( strpos( $html, 'data-sp-en' ), 'ikinci dil niteliği yok' );
	}
);

qrms_test(
	'düğme açık ama hiç İngilizce metin yoksa yine basılmaz',
	function () {
		// Aksi hâlde ziyaretçiye iki kez aynı metni gösteren bir düğme kalırdı.
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option(
			'splash_screen_options',
			array(
				'lang_toggle' => 1,
				'texts_en'    => array( 'btn1' => '', 'divider' => '' ),
			)
		);

		ob_start();
		qrms_ae()->handle_frontend();

		qrms_assert_false( strpos( ob_get_clean(), 'splash-lang' ), 'düğme basılmaz' );
	}
);

qrms_test(
	'iki dil de aynı HTML\'de taşınır; çeviri boşsa katalogdan tamamlanır',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option(
			'splash_screen_options',
			array(
				'lang_toggle'  => 1,
				'divider_text' => 'Bizi takip edin',
				'button_texts' => array( 'btn1' => 'Menüye Git', 'btn2' => 'İletişim', 'btn5' => 'Wifi Şifresi' ),
				'button_links' => array( 'btn1' => 'https://restoran.test/menu', 'btn2' => 'tel:+900' ),
				'texts_en'     => array( 'btn1' => 'View Menu', 'divider' => '' ),
				'social_media_active' => array( 'instagram' ),
				'social_media' => array( 'instagram' => 'https://instagram.com/x' ),
			)
		);

		ob_start();
		qrms_ae()->handle_frontend();
		$html = ob_get_clean();

		qrms_assert_contains( 'splash-lang', $html, 'düğme basılır' );
		qrms_assert_contains( 'data-sp-key="btn1"', $html, 'metin anahtarı' );
		qrms_assert_contains( 'data-sp-tr="Menüye Git"', $html, 'Türkçe metin' );
		qrms_assert_contains( 'data-sp-en="View Menu"', $html, 'İngilizce metin' );
		// Çevirisi girilmemiş metin i18n kataloğundaki karşılığına düşer;
		// katalogda da yoksa Türkçesi basılır.
		qrms_assert_contains( 'data-sp-en="Follow us"', $html, 'boş çeviri katalogdan tamamlanır' );
		// Görünen metin her zaman Türkçedir; dili istemci seçer.
		qrms_assert_contains( '>Menüye Git</a>', $html, 'sunucu Türkçeyi basar' );
	}
);

qrms_test(
	'rozetlerin erişilebilirlik etiketi de çevrilir',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option(
			'splash_screen_options',
			array(
				'lang_toggle'  => 1,
				'button_texts' => array( 'btn2' => 'İletişim' ),
				'button_links' => array( 'btn2' => 'tel:+900' ),
				'texts_en'     => array( 'btn2' => 'Call us' ),
			)
		);

		ob_start();
		qrms_ae()->handle_frontend();
		$html = ob_get_clean();

		// Rozetin görünür yazısı yoktur; dil değişimi aria-label ve title'a yazılır.
		qrms_assert_contains( 'data-sp-attr="aria-label title"', $html, 'nitelik hedefi' );
		qrms_assert_contains( 'data-sp-en="Call us"', $html, 'rozet çevirisi' );
	}
);

qrms_test(
	'dil açıkken de çıktı çerezden BAĞIMSIZ kalır',
	function () {
		// Dil sunucuda seçilseydi tam sayfa önbelleği ilk ziyaretçinin dilini
		// herkese servis ederdi. Karar istemcide verilir; sunucu çıktısı sabit.
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option(
			'splash_screen_options',
			array( 'lang_toggle' => 1, 'texts_en' => array( 'btn1' => 'View Menu' ) )
		);

		ob_start();
		qrms_ae()->handle_frontend();
		$cerezsiz = ob_get_clean();

		$_COOKIE['qrms_splash_lang'] = 'en';

		ob_start();
		qrms_ae()->handle_frontend();
		$cerezli = ob_get_clean();

		unset( $_COOKIE['qrms_splash_lang'] );

		qrms_assert_same( $cerezsiz, $cerezli, 'çıktı her ziyaretçide aynı' );
	}
);

qrms_test(
	'önizlemede dil düğmesi çalışır ama çerez yazılmaz',
	function () {
		update_option(
			'splash_screen_options',
			array( 'lang_toggle' => 1, 'texts_en' => array( 'btn1' => 'View Menu' ) )
		);

		ob_start();
		qrms_ae()->render_splash_preview();
		$html = ob_get_clean();

		qrms_assert_contains( 'splash-lang', $html, 'düğme önizlemede de var' );

		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-acilis-ekrani/assets/js/splash.js' );

		qrms_assert_contains( 'initLang(overlay, true)', $js, 'önizleme dili açık' );
		qrms_assert_contains( 'if (!persist || isPreview) return;', $js, 'önizlemede çerez yazılmaz' );
	}
);

qrms_test(
	'kayıtta eksik alt anahtar varsayılandan tamamlanır',
	function () {
		// array_merge SIĞ birleştirir: kayıtta button_texts varsa dizinin
		// tamamı kayıttan gelir ve eksik alt anahtar varsayılandan GELMEZ.
		// Eski sürümden gelen bir option'da bu gerçek bir durum; sayfa
		// "undefined index" uyarısı basıyordu.
		update_option(
			'splash_screen_options',
			array(
				'button_texts' => array( 'btn1' => 'Menüye Git' ),
				'texts_en'     => array( 'btn1' => 'View Menu' ),
			)
		);

		$html = qrms_ae_submit( 'qrms-ae-butonlar', array( 'button_text_1' => 'Menüye Git' ) );

		qrms_assert_contains( 'name="button_text_5"', $html, 'eksik anahtarlı satır yine basılır' );
		qrms_assert_false( strpos( $html, 'name="text_en_btn5"' ), 'İngilizce alanı yok' );

		$opts = get_option( 'splash_screen_options' );

		qrms_assert_same( 'Menüye Git', $opts['button_texts']['btn1'], 'kayıtlı değer ezilmez' );
		qrms_assert_same( 'Wifi Şifresi', $opts['button_texts']['btn5'], 'eksik anahtar varsayılandan' );
		qrms_assert_same( 4, count( $opts['footer_icons'] ), 'sayısal liste büyümez' );
	}
);

qrms_test(
	'yönetim: İngilizce çeviri alanları kaldırıldı, düğme anahtarı durur',
	function () {
		update_option(
			'splash_screen_options',
			array(
				'texts_en' => array( 'btn1' => 'View Menu', 'divider' => 'Follow us' ),
			)
		);

		$html = qrms_ae_submit(
			'qrms-ae-butonlar',
			array(
				'lang_toggle'     => '1',
				'button_text_1'   => 'Menüye Git',
				'text_en_btn1'    => 'Should Not Save',
				'text_en_divider' => 'Should Not Save Either',
			)
		);

		$opts = get_option( 'splash_screen_options' );

		qrms_assert_same( 1, $opts['lang_toggle'], 'düğme açıldı' );
		qrms_assert_same( 'View Menu', $opts['texts_en']['btn1'], 'eski çeviri ezilmez' );
		qrms_assert_same( 'Follow us', $opts['texts_en']['divider'], 'eski ayraç çevirisi durur' );
		qrms_assert_false( strpos( $html, 'name="text_en_btn1"' ), 'buton İngilizce alanı yok' );
		qrms_assert_false( strpos( $html, 'name="text_en_divider"' ), 'ayraç İngilizce alanı yok' );
		qrms_assert_false( strpos( $html, 'Buton yazısı (English)' ), 'İngilizce etiket yok' );
		qrms_assert_contains( 'name="lang_toggle"', $html, 'düğme anahtarı basılır' );
		qrms_assert_contains( 'name="button_text_1"', $html, 'Türkçe yazı alanı durur' );

		qrms_ae_submit( 'qrms-ae-davranis', array( 'wifi_password' => 'x' ) );

		qrms_assert_same( 1, get_option( 'splash_screen_options' )['lang_toggle'], 'başka sayfa dili kapatmaz' );
	}
);

qrms_test(
	'Ayarlar ve Sosyal Medya Bağlantısı ayrı sayfalardır',
	function () {
		$pages = qrms_ae()->admin_pages();

		qrms_assert_true( isset( $pages['qrms-ae-davranis'] ), 'Ayarlar slug durur' );
		qrms_assert_same( 'Ayarlar', $pages['qrms-ae-davranis']['title'], 'sekme adı Ayarlar' );
		qrms_assert_false( strpos( $pages['qrms-ae-davranis']['title'], 'Sosyal' ), 'Ayarlar adında Sosyal yok' );

		qrms_assert_true( isset( $pages['qrms-ae-sosyal'] ), 'sosyal sayfa kayıtlı' );
		qrms_assert_same( 'Sosyal Medya Bağlantısı', $pages['qrms-ae-sosyal']['title'], 'yeni sekme adı' );

		$ayarlar = qrms_ae_submit( 'qrms-ae-davranis', array() );
		$sosyal  = qrms_ae_submit( 'qrms-ae-sosyal', array() );

		qrms_assert_contains( 'name="dismiss_duration"', $ayarlar, 'süre Ayarlar\'da' );
		qrms_assert_contains( 'name="wifi_password"', $ayarlar, 'wifi Ayarlar\'da' );
		qrms_assert_false( strpos( $ayarlar, 'name="social_media_active[]"' ), 'sosyal kutu Ayarlar\'da yok' );

		qrms_assert_contains( 'name="social_media_active[]"', $sosyal, 'sosyal kutu yeni sayfada' );
		qrms_assert_false( strpos( $sosyal, 'name="wifi_password"' ), 'wifi sosyal sayfada yok' );
	}
);

qrms_test(
	'sosyal sayfayı kaydetmek wifi şifresini silmez, Ayarlar sosyal seçimi silmez',
	function () {
		update_option(
			'splash_screen_options',
			array(
				'wifi_password'       => 'misafir123',
				'social_media_active' => array( 'instagram' ),
				'social_media'        => array( 'instagram' => 'https://instagram.com/x' ),
			)
		);

		qrms_ae_submit( 'qrms-ae-sosyal', array( 'social_media_active' => array( 'facebook' ) ) );

		$opts = get_option( 'splash_screen_options' );

		qrms_assert_same( 'misafir123', $opts['wifi_password'], 'wifi durur' );
		qrms_assert_same( array( 'facebook' ), $opts['social_media_active'], 'sosyal sahibi sayfada yazılır' );

		qrms_ae_submit( 'qrms-ae-davranis', array( 'wifi_password' => 'yeni-sifre' ) );

		$opts = get_option( 'splash_screen_options' );

		qrms_assert_same( 'yeni-sifre', $opts['wifi_password'], 'wifi Ayarlar\'da yazılır' );
		qrms_assert_same( array( 'facebook' ), $opts['social_media_active'], 'sosyal Ayarlar kaydında durur' );
	}
);

qrms_test(
	'bayrak boyutu CSS değişkenine basılır, buton padding anahtarı silinir',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;

		update_option(
			'splash_screen_options',
			array(
				'button_padding' => '14px 28px',
			)
		);

		qrms_ae_submit(
			'qrms-ae-gorunum',
			array(
				'ceviri_flag_size' => '40',
			)
		);

		ob_start();
		qrms_ae()->print_critical_head();
		$html = ob_get_clean();

		qrms_assert_contains( '--sp-flag-size: 40px', $html, 'bayrak boyutu CSS\'e basılır' );
		qrms_assert_false( isset( get_option( 'splash_screen_options' )['button_padding'] ), 'eski padding anahtarı silinir' );
		qrms_assert_false( strpos( $html, '--sp-cta-pad' ), 'CTA padding değişkeni basılmaz' );
	}
);

qrms_test(
	'tekrar göstermeme süresi 0 iken çerez yazılmaz, kontrol atlanır',
	function () {
		$js   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-acilis-ekrani/assets/js/splash.js' );
		$head = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-acilis-ekrani/includes/frontend.php' );

		qrms_assert_contains( 'if (dismissMinutes > 0)', $js, 'çerez yalnızca süre > 0 iken yazılır' );
		qrms_assert_contains( 'else if (hasDismissCookie())', $js, '0 iken çerez kontrolü atlanır' );
		qrms_assert_contains( 'dismissMinutes === 0', $head, 'kritik head 0\'ı ayrı ele alır' );

		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option( 'splash_screen_options', array( 'dismiss_duration' => 0 ) );

		ob_start();
		qrms_ae()->print_critical_head();
		$html = ob_get_clean();

		qrms_assert_contains( 'var dismissMinutes = 0;', $html, 'süre head betiğine basılır' );
		qrms_assert_contains( 'splash-loading', $html, '0 iken splash yine gösterilir' );
	}
);


/* ---------------------------------------------------------------------------
 * 15b. Açılış Ekranı — QR Çeviri bayrak seçici
 * ------------------------------------------------------------------------ */

echo "\nAçılış Ekranı (QR Çeviri seçici)\n";

/**
 * Testlerde QR Çeviri fonksiyonlarının ince bir taklidi.
 *
 * ceviri.php yüklenmez (aktivasyon hook'u ve tablo katmanı stub'larda yok);
 * splash yalnızca dil listesini ve aktif dil kodlarını sorduğu için bu yeter.
 *
 * @return void
 */
function qrms_ae_stub_ceviri() {
	if ( ! function_exists( 'qrmenu_get_langs' ) ) {
		/**
		 * @return array<string,array{name:string,flag:string}>
		 */
		function qrmenu_get_langs() {
			return array(
				'tr' => array( 'name' => 'Türkçe', 'flag' => 'TR' ),
				'en' => array( 'name' => 'English', 'flag' => 'EN' ),
				'de' => array( 'name' => 'Deutsch', 'flag' => 'DE' ),
			);
		}
	}
	if ( ! function_exists( 'rma_ceviri_aktif_diller' ) ) {
		/**
		 * @return string[]
		 */
		function rma_ceviri_aktif_diller() {
			$aktif = get_option( 'qrmenu_active_langs', array( 'tr', 'en', 'de' ) );
			return is_array( $aktif ) ? $aktif : array( 'tr', 'en', 'de' );
		}
	}
}

qrms_test(
	'seçici kapalıyken bayrak markup\'a hiç girmez',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option( 'qrms_active_modules', array( 'qr-acilis-ekrani', 'qr-ceviri' ) );
		update_option(
			'splash_screen_options',
			array(
				'ceviri_selector'       => 0,
				'ceviri_selector_langs' => array( 'tr', 'en' ),
			)
		);

		qrms_ae_stub_ceviri();

		ob_start();
		qrms_ae()->handle_frontend();
		$html = ob_get_clean();

		qrms_assert_false( strpos( $html, 'splash-ceviri' ), 'seçici yok' );
	}
);

qrms_test(
	'QR Çeviri kapalıyken seçici açık olsa da basılmaz',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option( 'qrms_active_modules', array( 'qr-acilis-ekrani' ) );
		update_option(
			'splash_screen_options',
			array(
				'ceviri_selector'       => 1,
				'ceviri_selector_langs' => array( 'tr', 'en' ),
			)
		);

		qrms_ae_stub_ceviri();

		ob_start();
		qrms_ae()->handle_frontend();

		qrms_assert_false( strpos( ob_get_clean(), 'splash-ceviri' ), 'modül yokken basılmaz' );
	}
);

qrms_test(
	'açık ama hiç dil seçilmemişse yine basılmaz',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option( 'qrms_active_modules', array( 'qr-acilis-ekrani', 'qr-ceviri' ) );
		update_option(
			'splash_screen_options',
			array(
				'ceviri_selector'       => 1,
				'ceviri_selector_langs' => array(),
			)
		);

		qrms_ae_stub_ceviri();

		ob_start();
		qrms_ae()->handle_frontend();

		qrms_assert_false( strpos( ob_get_clean(), 'splash-ceviri' ), 'dil yokken basılmaz' );
	}
);

qrms_test(
	'açıkken yalnızca işaretli diller basılır, çıktı çerezden bağımsızdır',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option( 'qrms_active_modules', array( 'qr-acilis-ekrani', 'qr-ceviri' ) );
		update_option(
			'splash_screen_options',
			array(
				'ceviri_selector'       => 1,
				'ceviri_selector_langs' => array( 'tr', 'en', 'xx' ),
			)
		);

		qrms_ae_stub_ceviri();

		ob_start();
		qrms_ae()->handle_frontend();
		$cerezsiz = ob_get_clean();

		$_COOKIE['rma_lang'] = 'en';
		$_GET['lang']        = 'en';

		ob_start();
		qrms_ae()->handle_frontend();
		$cerezli = ob_get_clean();

		unset( $_COOKIE['rma_lang'], $_GET['lang'] );

		qrms_assert_same( $cerezsiz, $cerezli, 'çıktı her ziyaretçide aynı' );
		qrms_assert_contains( 'splash-ceviri', $cerezsiz, 'seçici basılır' );
		qrms_assert_contains( 'data-lang="tr"', $cerezsiz, 'Türkçe seçenek' );
		qrms_assert_contains( 'data-lang="en"', $cerezsiz, 'İngilizce seçenek' );
		qrms_assert_false( strpos( $cerezsiz, 'data-lang="xx"' ), 'QR Çeviri dışı kod düşer' );
		qrms_assert_false( strpos( $cerezsiz, 'data-lang="de"' ), 'işaretsiz dil basılmaz' );
		qrms_assert_contains( 'data-cookie="rma_lang"', $cerezsiz, 'QR Çeviri çerezi' );
	}
);

qrms_test(
	'QR Çeviri bir dili kapatırsa splash seçeneği de düşer',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option( 'qrms_active_modules', array( 'qr-acilis-ekrani', 'qr-ceviri' ) );
		update_option( 'qrmenu_active_langs', array( 'tr', 'en' ) );
		update_option(
			'splash_screen_options',
			array(
				'ceviri_selector'       => 1,
				'ceviri_selector_langs' => array( 'tr', 'en', 'de' ),
			)
		);

		qrms_ae_stub_ceviri();

		ob_start();
		qrms_ae()->handle_frontend();
		$html = ob_get_clean();

		qrms_assert_contains( 'data-lang="en"', $html, 'açık dil kalır' );
		qrms_assert_false( strpos( $html, 'data-lang="de"' ), 'QR Çeviri\'de kapalı dil düşer' );

		update_option( 'qrmenu_active_langs', array( 'tr', 'en', 'de' ) );
	}
);

qrms_test(
	'önizlemede seçici çalışır; JS dinleyiciyi elemente bağlar',
	function () {
		update_option( 'qrms_active_modules', array( 'qr-acilis-ekrani', 'qr-ceviri' ) );
		update_option(
			'splash_screen_options',
			array(
				'ceviri_selector'       => 1,
				'ceviri_selector_langs' => array( 'tr', 'en' ),
			)
		);

		qrms_ae_stub_ceviri();

		ob_start();
		qrms_ae()->render_splash_preview();
		$html = ob_get_clean();

		qrms_assert_contains( 'splash-ceviri', $html, 'seçici önizlemede de var' );

		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-acilis-ekrani/assets/js/splash.js' );

		qrms_assert_contains( 'if (!root) return noopCeviri()', $js, 'element yoksa dinleyici yok' );
		qrms_assert_contains( 'qrmenuTranslate', $js, 'QR Çeviri fonksiyonuna bağlanır' );
		qrms_assert_contains( 'rma_dil', $js, 'sessionStorage anahtarı QR Çeviri ile aynı' );
	}
);

qrms_test(
	'yönetim: Dil Seçici Görünüm sayfasındadır, başka sayfa kapatmaz',
	function () {
		update_option( 'qrms_active_modules', array( 'qr-acilis-ekrani', 'qr-ceviri' ) );
		qrms_ae_stub_ceviri();

		$html = qrms_ae_submit(
			'qrms-ae-gorunum',
			array(
				'ceviri_selector'       => '1',
				'ceviri_selector_langs' => array( 'tr', 'en', 'de', 'xx' ),
				'bg_color'              => '#101010',
			)
		);

		$opts = get_option( 'splash_screen_options' );

		qrms_assert_same( 1, $opts['ceviri_selector'], 'seçici açıldı' );
		qrms_assert_same( array( 'tr', 'en', 'de' ), $opts['ceviri_selector_langs'], 'geçerli diller kalır, xx düşer' );
		qrms_assert_contains( 'name="ceviri_selector"', $html, 'anahtar basılır' );
		qrms_assert_contains( 'name="ceviri_selector_langs[]"', $html, 'dil listesi basılır' );

		qrms_ae_submit( 'qrms-ae-davranis', array( 'wifi_password' => 'x' ) );

		$opts = get_option( 'splash_screen_options' );

		qrms_assert_same( 1, $opts['ceviri_selector'], 'başka sayfa seçiciyi kapatmaz' );
		qrms_assert_same( array( 'tr', 'en', 'de' ), $opts['ceviri_selector_langs'], 'dil listesi korunur' );

		qrms_ae_submit( 'qrms-ae-gorunum', array( 'bg_color' => '#202020' ) );

		qrms_assert_same( 0, get_option( 'splash_screen_options' )['ceviri_selector'], 'sahibi sayfa kutuyu kapatır' );
	}
);

qrms_test(
	'bayrak seçici açıkken splash metinleri tüm dillerde taşınır',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option( 'qrms_active_modules', array( 'qr-acilis-ekrani', 'qr-ceviri' ) );
		update_option(
			'splash_screen_options',
			array(
				'ceviri_selector'       => 1,
				'ceviri_selector_langs' => array( 'tr', 'en', 'de' ),
				'button_texts'          => array( 'btn1' => 'Menüye Git', 'btn2' => 'İletişim' ),
				'button_links'          => array( 'btn1' => 'https://restoran.test/menu', 'btn2' => 'tel:+900' ),
				'divider_text'          => 'Bizi takip edin',
				'social_media_active'   => array( 'instagram' ),
				'social_media'          => array( 'instagram' => 'https://instagram.com/x' ),
			)
		);

		qrms_ae_stub_ceviri();

		ob_start();
		qrms_ae()->handle_frontend();
		$html = ob_get_clean();

		qrms_assert_contains( 'data-sp-tr="Menüye Git"', $html, 'Türkçe CTA' );
		qrms_assert_contains( 'data-sp-en="View Menu"', $html, 'İngilizce CTA' );
		qrms_assert_contains( 'data-sp-de="Zum Menü"', $html, 'Almanca CTA' );
		qrms_assert_contains( 'data-sp-de="Kontakt"', $html, 'Almanca rozet' );
		qrms_assert_contains( 'data-sp-de="Folgen Sie uns"', $html, 'Almanca ayraç' );
		qrms_assert_contains( 'data-sp-lang-select-de="Sprache wählen (%s)"', $html, 'Almanca bayrak etiketi' );
		qrms_assert_contains( '>Menüye Git</a>', $html, 'sunucu yine Türkçeyi basar' );
	}
);

qrms_test(
	'bayrak seçici ile dil çevirisi istemcide uygulanır',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-acilis-ekrani/assets/js/splash.js' );

		qrms_assert_contains( 'applySplashLang(overlay, lang)', $js, 'seçimde metin güncellenir' );
		qrms_assert_contains( 'data-sp-lang-select-', $js, 'bayrak etiketi şablonu okunur' );
	}
);


/* ---------------------------------------------------------------------------
 * 16. QR Çeviri — yönetim ekranının mobil davranışı
 * ------------------------------------------------------------------------ */



/* P2 çeviri testleri (birleşme sonrası taşındı) */

echo "\nQR Çeviri (P0 köprü / açılış + kilit)\n";

require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/ui-stringler.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/fiyat.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/settings.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/veri-kaynaklar.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/kaynaklar.php';

qrms_test(
	'Nakit ve Kart data-sp ile çevrilir; marka adları çevrilmez',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option(
			'splash_screen_options',
			array(
				'lang_toggle'          => 1,
				'texts_en'             => array( 'btn1' => 'View Menu' ),
				'payment_methods'      => array( 'nakit', 'kart', 'edenred', 'multinet', 'sodexo', 'setcard' ),
				'payment_display_mode' => 'text_only',
			)
		);

		ob_start();
		qrms_ae()->handle_frontend();
		$html = ob_get_clean();

		qrms_assert_contains( 'data-sp-key="pay_nakit"', $html, 'Nakit anahtarı' );
		qrms_assert_contains( 'data-sp-tr="Nakit"', $html, 'Nakit TR' );
		qrms_assert_contains( 'data-sp-en="Cash"', $html, 'Nakit katalog EN' );
		qrms_assert_contains( 'data-sp-key="pay_kart"', $html, 'Kart anahtarı' );
		qrms_assert_contains( 'data-sp-tr="Kart"', $html, 'Kart TR' );
		qrms_assert_contains( 'data-sp-en="Card"', $html, 'Kart katalog EN' );
		qrms_assert_contains( '>Nakit</span>', $html, 'sunucu Türkçe Nakit basar' );
		qrms_assert_contains( '>Kart</span>', $html, 'sunucu Türkçe Kart basar' );

		foreach ( array( 'Edenred', 'Multinet', 'Sodexo', 'Setcard' ) as $marka ) {
			qrms_assert_contains( '>' . $marka . '</span>', $html, $marka . ' basılır' );
		}
		qrms_assert_false( strpos( $html, 'data-sp-key="pay_edenred"' ), 'Edenred i18n yok' );
		qrms_assert_false( strpos( $html, 'data-sp-en="Edenred"' ), 'Edenred data-sp yok' );
	}
);

qrms_test(
	'kilit kaynak metinleri katalogda ve anahtarları kararlı',
	function () {
		$metinler = rma_ceviri_modul_stringleri( 'lock' );
		$beklenen = array(
			'Oturum Gerekli',
			'Bu masa için geçerli bir QR kod bulunamadı. Lütfen masanızdaki QR kodu okutun.',
			'Oturumunuz sona erdi. Devam etmek için masadaki QR kodu tekrar okutun.',
			'Bu bölümü kullanmak için masanızdaki QR kodu okutun.',
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
	'masa QR adresi ?lang= taşımaz; kilit ?lang= varsa onu ilk kaynak sayar',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-masa/class-qmo-masalar.php' );

		qrms_assert_contains( "add_query_arg( 'masa'", $kaynak, 'QR masa parametresi' );
		qrms_assert_false(
			(bool) preg_match( "/add_query_arg\(\s*'lang'/", $kaynak ),
			'QR lang parametresi yok'
		);
	}
);

qrms_test(
	'Accept-Language yalnızca kilit ekranındadır, genel dil zincirinde yoktur',
	function () {
		$kilit = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-masa-oturum-guvenligi/masa-dogrulama.php' );
		$dil   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/dil.php' );

		qrms_assert_contains( 'HTTP_ACCEPT_LANGUAGE', $kilit, 'kilit Accept-Language okur' );
		qrms_assert_contains( 'qmo_kilit_ekrani_dili', $kilit, 'kilit dil çözücü' );
		qrms_assert_false(
			(bool) preg_match( '/HTTP_ACCEPT_LANGUAGE/', $dil ),
			'rma_get_current_lang Accept-Language kullanmaz'
		);
		qrms_assert_contains( "?lang → cookie → 'tr'", $dil, 'genel zincir duruyor' );
	}
);

qrms_test(
	'kilit dili: ?lang= → cookie → Accept-Language (etkin) → tr',
	function () {
		qrms_ae_stub_ceviri();
		update_option( 'qrmenu_active_langs', array( 'tr', 'en', 'de' ) );

		$_GET['lang']                        = 'en';
		$_COOKIE['rma_lang']                 = 'de';
		$_SERVER['HTTP_ACCEPT_LANGUAGE']     = 'de-DE,de;q=0.9';
		qrms_assert_same( 'en', qmo_kilit_ekrani_dili(), '?lang= kazanır' );

		$_GET['lang'] = 'zz';
		qrms_assert_same( 'de', qmo_kilit_ekrani_dili(), 'geçersiz ?lang= cookie\'ye düşer' );

		unset( $_GET['lang'] );
		$_COOKIE['rma_lang']             = 'en';
		$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de;q=1';
		qrms_assert_same( 'en', qmo_kilit_ekrani_dili(), 'cookie Accept-Language\'dan önce' );

		unset( $_COOKIE['rma_lang'] );
		$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9';
		qrms_assert_same( 'en', qmo_kilit_ekrani_dili(), 'en-US etkin en\'e iner' );

		$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en;q=0.4,de;q=0.8';
		qrms_assert_same( 'de', qmo_kilit_ekrani_dili(), 'q-değeri yüksek olan' );

		$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'ja-JP,ja;q=0.9';
		qrms_assert_same( 'tr', qmo_kilit_ekrani_dili(), 'etkin olmayan tarayıcı dili Türkçe' );

		unset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] );
		qrms_assert_same( 'tr', qmo_kilit_ekrani_dili(), 'sinyal yokken Türkçe' );
	}
);

qrms_test(
	'kilit ön yüzü rma_ceviri_modul köprüsünü kullanır; html lang çözülen dildir',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-masa-oturum-guvenligi/masa-dogrulama.php' );

		qrms_assert_contains( "rma_ceviri_modul( 'lock'", $kaynak, 'köprü çağrısı' );
		qrms_assert_contains( "__( 'Oturum Gerekli', 'qrms' )", $kaynak, 'textdomain başlık' );
		qrms_assert_contains(
			"__( 'Bu masa için geçerli bir QR kod bulunamadı. Lütfen masanızdaki QR kodu okutun.', 'qrms' )",
			$kaynak,
			'textdomain sahte QR'
		);
		qrms_assert_contains(
			"__( 'Oturumunuz sona erdi. Devam etmek için masadaki QR kodu tekrar okutun.', 'qrms' )",
			$kaynak,
			'textdomain oturum bitti'
		);
		qrms_assert_contains( 'esc_attr( $metin[\'dil\'] )', $kaynak, 'html lang kilit diline bağlı' );
		qrms_assert_false(
			(bool) preg_match( '/<html lang="tr">/', $kaynak ),
			'sabit lang=tr kalmadı'
		);

		qrms_ae_stub_ceviri();
		update_option( 'qrmenu_active_langs', array( 'tr', 'en' ) );
		$_GET  = array();
		$_COOKIE = array();
		unset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] );

		$metin = qmo_kilit_ekrani_metinleri( __( 'Oturumunuz sona erdi. Devam etmek için masadaki QR kodu tekrar okutun.', 'qrms' ) );
		qrms_assert_same( 'tr', $metin['dil'], 'varsayılan dil' );
		qrms_assert_same( 'Oturum Gerekli', $metin['baslik'], 'başlık Türkçe' );
		qrms_assert_same(
			'Oturumunuz sona erdi. Devam etmek için masadaki QR kodu tekrar okutun.',
			$metin['mesaj'],
			'mesaj Türkçe'
		);
		qrms_assert_false( $metin['rtl'], 'TR RTL değil' );
	}
);

qrms_test(
	'uyarı kutusu lock metnini genel dille çevirir, Accept-Language kullanmaz',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/helpers.php' );

		qrms_assert_contains( "rma_ceviri_modul( 'lock'", $kaynak, 'köprü çağrısı' );
		qrms_assert_contains(
			"__( 'Bu bölümü kullanmak için masanızdaki QR kodu okutun.', 'qrms' )",
			$kaynak,
			'textdomain varsayılan kutu'
		);
		qrms_assert_false(
			(bool) preg_match( '/HTTP_ACCEPT_LANGUAGE/', $kaynak ),
			'uyarı kutusu Accept-Language okumaz'
		);
		qrms_assert_false(
			(bool) preg_match( "/rma_ceviri_modul\(\s*'lock',\s*\\\$mesaj,\s*\\\$/", $kaynak ),
			'uyarı kutusu kilit dilini zorlamaz'
		);
	}
);

echo "\nQR Çeviri (P0 köprü / açılış ödeme)\n";

qrms_test(
	'splash kaynak metinleri Nakit ve Kart; marka adı yok',
	function () {
		$metinler = rma_ceviri_modul_stringleri( 'splash' );

		qrms_assert_same( 'Nakit', $metinler[ rma_ceviri_ui_anahtari( 'Nakit' ) ], 'Nakit' );
		qrms_assert_same( 'Kart', $metinler[ rma_ceviri_ui_anahtari( 'Kart' ) ], 'Kart' );
		foreach ( array( 'Edenred', 'Multinet', 'Sodexo', 'Setcard' ) as $marka ) {
			qrms_assert_false( isset( $metinler[ rma_ceviri_ui_anahtari( $marka ) ] ), $marka . ' katalogda yok' );
		}
	}
);

qrms_test(
	'ödeme katalogu 11 dili taşır; boş anahtar Türkçeye düşer',
	function () {
		$ae    = qrms_ae();
		$kat   = new ReflectionMethod( $ae, 'i18n_catalog' );
		$kat->setAccessible( true );
		$cevir = new ReflectionMethod( $ae, 'i18n_translate' );
		$cevir->setAccessible( true );

		$katalog = $kat->invoke( $ae );
		$diller  = array( 'tr', 'en', 'de', 'fr', 'es', 'it', 'ru', 'ar', 'nl', 'pl', 'pt' );

		foreach ( array( 'pay_nakit', 'pay_kart' ) as $anahtar ) {
			qrms_assert_true( isset( $katalog[ $anahtar ] ), $anahtar . ' katalogda' );
			foreach ( $diller as $dil ) {
				qrms_assert_true(
					isset( $katalog[ $anahtar ][ $dil ] ) && '' !== $katalog[ $anahtar ][ $dil ],
					$anahtar . ' ' . $dil
				);
			}
		}

		qrms_assert_same( 'Nakit', $katalog['pay_nakit']['tr'], 'Nakit TR' );
		qrms_assert_same( 'Cash', $katalog['pay_nakit']['en'], 'Nakit EN' );
		qrms_assert_same( 'Kart', $katalog['pay_kart']['tr'], 'Kart TR' );
		qrms_assert_same( 'Card', $katalog['pay_kart']['en'], 'Kart EN' );
		qrms_assert_same( 'Yok', $cevir->invoke( $ae, 'olmayan_anahtar', 'en', 'Yok' ), 'katalog boşsa Türkçe' );
	}
);

qrms_test(
	'splash çeviri sırası: tablo → texts_en → katalog → Türkçe',
	function () {
		if ( ! function_exists( 'rma_ceviri_anahtar' ) ) {
			/**
			 * @param string $tip   Tip.
			 * @param int    $id    ID.
			 * @param string $field Alan.
			 * @return string
			 */
			function rma_ceviri_anahtar( $tip, $id, $field ) {
				return $tip . '|' . (int) $id . '|' . $field;
			}
		}
		if ( ! function_exists( 'rma_ceviri_sozluk' ) ) {
			/**
			 * @param string $lang Dil.
			 * @return array{anahtar:array<string,string>,metin:array<string,string>}
			 */
			function rma_ceviri_sozluk( $lang ) {
				$tum = isset( $GLOBALS['qrms_test']['splash_sozluk'] ) ? $GLOBALS['qrms_test']['splash_sozluk'] : array();
				return isset( $tum[ $lang ] ) ? $tum[ $lang ] : array( 'anahtar' => array(), 'metin' => array() );
			}
		}

		$ae   = qrms_ae();
		$fn   = new ReflectionMethod( $ae, 'text_for_lang' );
		$fn->setAccessible( true );
		$opts = array(
			'lang_toggle' => 1,
			'texts_en'    => array(
				'btn1'      => 'View Menu',
				'pay_nakit' => 'Cash Legacy',
			),
		);

		$GLOBALS['qrms_test']['splash_sozluk'] = array();
		qrms_assert_same( 'Nakit', $fn->invoke( $ae, $opts, 'pay_nakit', 'Nakit', 'tr' ), 'TR kaynak' );
		qrms_assert_same( 'Cash Legacy', $fn->invoke( $ae, $opts, 'pay_nakit', 'Nakit', 'en' ), 'texts_en' );
		qrms_assert_same( 'Cash', $fn->invoke( $ae, array(), 'pay_nakit', 'Nakit', 'en' ), 'katalog (toggle kapalı)' );
		qrms_assert_same( 'Nakit', $fn->invoke( $ae, array(), 'yok', 'Nakit', 'de' ), 'katalog boşsa Türkçe' );

		$alan = rma_ceviri_anahtar( 'splash', 0, rma_ceviri_ui_anahtari( 'Nakit' ) );
		$GLOBALS['qrms_test']['splash_sozluk'] = array(
			'en' => array( 'anahtar' => array( $alan => 'Table Cash' ), 'metin' => array() ),
		);
		qrms_assert_same( 'Table Cash', $fn->invoke( $ae, $opts, 'pay_nakit', 'Nakit', 'en' ), 'tablo texts_en\'den önce' );

		$GLOBALS['qrms_test']['splash_sozluk'] = array();
	}
);

qrms_test(
	'aria etiketleri cache-güvenli data-sp deseniyle güncellenir',
	function () {
		$front = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-acilis-ekrani/includes/frontend.php' );
		$js    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-acilis-ekrani/assets/js/splash.js' );

		qrms_assert_contains( "lang_data(\$opts, 'loading', 'Yükleniyor', 'aria-label')", $front, 'loader data-sp-attr' );
		qrms_assert_contains( 'aria-label="Yükleniyor"', $front, 'loader TR yedek' );
		qrms_assert_contains( "lang_data( \$opts, 'close', 'Kapat', 'aria-label' )", $front, 'wifi kapat data-sp-attr' );
		qrms_assert_contains( 'aria-label="Kapat"', $front, 'wifi kapat TR yedek' );
		qrms_assert_contains( 'data-sp-lang-select-', $front, 'dil seç şablon nitelikleri' );
		qrms_assert_contains( "sprintf('Dil seç (%s)'", $front, 'dil seç TR yedek' );
		qrms_assert_contains( "data-sp-lang-select-' + lang", $js, 'JS şablonu okur' );
		qrms_assert_contains( "template.replace('%s', selectedName)", $js, 'JS %s doldurur' );

		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option( 'qrms_active_modules', array( 'qr-acilis-ekrani', 'qr-ceviri' ) );
		qrms_ae_stub_ceviri();
		update_option(
			'splash_screen_options',
			array(
				'ceviri_selector'       => 1,
				'ceviri_selector_langs' => array( 'tr', 'en' ),
				'lang_toggle'           => 1,
				'texts_en'              => array( 'btn1' => 'View Menu' ),
			)
		);

		ob_start();
		qrms_ae()->handle_frontend();
		$html = ob_get_clean();

		qrms_assert_contains( 'data-sp-attr="aria-label"', $html, 'loader/kapat nitelik hedefi' );
		qrms_assert_contains( 'data-sp-en="Loading"', $html, 'Yükleniyor EN' );
		qrms_assert_contains( 'data-sp-en="Close"', $html, 'Kapat EN' );
		qrms_assert_contains( 'data-sp-lang-select-en="Select language (%s)"', $html, 'dil seç EN şablon' );
		qrms_assert_contains( 'aria-label="Yükleniyor"', $html, 'loader yedek HTML\'de' );
		qrms_assert_contains( 'aria-label="Kapat"', $html, 'kapat yedek HTML\'de' );
	}
);

echo "\nQR Çeviri (P0 köprü / chatbot)\n";
