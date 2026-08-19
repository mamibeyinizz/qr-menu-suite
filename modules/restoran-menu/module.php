<?php
/**
 * Modül: Restoran Menü (restoran-menu)
 *
 * AJAX destekli restoran menüsü (`rma_menu_item` CPT, kategoriler, alerjenler,
 * filtreleme/arama, içe-dışa aktarma, öneriler, Elementor widget'ı) ve öne
 * çıkan ürünler slider'ı + kombin ürün meta kutusu. Dosyalar 12-menu
 * deposundaki QR MENÜ eklentisinden aynen taşındı; burada yalnızca yükleme
 * bağlantısı ve suite menüsündeki sayfanın varlık kuyruğu var.
 *
 * Bu modül `_qmo-ortak/ortak.php`'yi YÜKLEMEZ: RMA_/QMO_ ad alanıyla tamamen
 * kendi kendine yeterlidir, ortak yardımcıların (QMO_Oturum, qmo_oturum(),
 * QMO_NONCE_ACTION) hiçbirini kullanmaz ve varlıklarını kendi klasöründen
 * yükler.
 *
 * DAĞITIM NOTU: eski tekil "QR MENÜ" eklentisi devre dışı bırakılmalıdır —
 * bu modül onun yerini alır. Yan yana bırakılırsa eski eklenti daha erken
 * yüklendiği için RMA_PLUGIN_URL / QMO_PLUGIN_URL sabitlerini o tanımlar ve
 * kaynak dosyalardaki varlık adresleri eski klasörü gösterir. Sabitlerin
 * defined() guard'ı ve require'ların __DIR__ tabanına alınması yalnızca
 * notice ile çift yüklemeyi önler, iki eklentiyi birlikte çalışır kılmaz.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modülü başlatır.
 *
 * QRMS_Module_Loader tarafından `plugins_loaded` (öncelik 20) sırasında
 * argümansız çağrılır. Taşınan dosyalar hook'larını dosya kapsamında
 * kaydettiği için require bilinçli olarak bu fonksiyonun içindedir: qr-menu.php
 * on trait'i, slider bootstrap'ını ve Elementor widget'ını yükler, sonunda
 * `Restaurant_Menu_Automation::get_instance()` çağrısıyla tüm hook'lar kaydolur.
 * Bağlanılan kancaların hepsi (`init`, `admin_menu`, `admin_init`,
 * `wp_enqueue_scripts`, `wp_ajax_*`, kısa kodlar) plugins_loaded'dan sonra
 * tetiklenir.
 *
 * @return void
 */
function qrms_module_restoran_menu_init() {
	require_once __DIR__ . '/qr-menu.php';

	if ( is_admin() ) {
		// Sekmeli ayar ekranının kendisi kaynakta olduğu gibi kalır; suite
		// menüsünden de aynı metot basılır. Eklentinin kendi kaydı olan
		// "Menü Ürünleri > Ayarlar" alt menüsü de çalışmaya devam eder.
		QRMS_Admin::register_module_page(
			'restoran-menu',
			array( Restaurant_Menu_Automation::get_instance(), 'render_admin_page' )
		);

		add_action( 'admin_enqueue_scripts', 'qrms_module_restoran_menu_admin_assets' );
	}
}

/**
 * Suite menüsündeki "Restoran Menü" ekranının yönetim varlıkları.
 *
 * Kaynaktaki RMA_Admin_Pages_Trait::admin_scripts() varlıkları yalnızca
 * `rma_menu_item` ekranlarında yükler (`get_current_screen()->post_type`
 * kontrolü). Suite'in kendi sayfasında post_type boş olduğu için o dal hiç
 * çalışmaz; sekmeler ve renk seçici varlıksız kalırdı. Burada aynı ekranın
 * (Ayarlar dalının) ihtiyaç duyduğu varlıklar birebir aynı handle ve
 * bağımlılıklarla kuyruğa alınır — ekranın kendi kodu değişmez.
 *
 * Koşul, qr-masa modülündeki desenin aynısı: varlıklar yalnızca bu modülün
 * kendi suite sayfası render edilirken yüklenir.
 *
 * @return void
 */
function qrms_module_restoran_menu_admin_assets() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	if ( QRMS_Admin::get_module_page_slug( 'restoran-menu' ) !== $page ) {
		return;
	}

	$url = QRMS_PLUGIN_URL . 'modules/restoran-menu/';

	wp_enqueue_script( 'jquery-ui-sortable' );
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );

	wp_enqueue_style( 'rma-admin-ui', $url . 'assets/css/admin-ui.css', array(), QRMS_VERSION );
	wp_enqueue_script(
		'rma-admin-ui',
		$url . 'assets/js/admin-ui.js',
		array( 'jquery', 'wp-color-picker', 'jquery-ui-sortable' ),
		QRMS_VERSION,
		true
	);

	// Kayar Başlık sekmesindeki canlı önizleme, frontend'in gerçek nav
	// stylesheet'ini kullanır; aktif gösterge CSS'inin dört varyantı da
	// ekranın kendi kaynağından (get_nav_indicator_css) gelir.
	wp_enqueue_style( 'rma-nav', $url . 'assets/css/rma-nav.css', array( 'rma-admin-ui' ), QRMS_VERSION );

	$rma           = Restaurant_Menu_Automation::get_instance();
	$indicator_css = '';

	foreach ( array( 'background', 'dot', 'none', 'bottom_line' ) as $variant ) {
		$indicator_css .= str_replace(
			'.rma-nav-btn.active',
			'.rma-nav-preview[data-rma-ind="' . $variant . '"] .rma-nav-btn.active',
			$rma->get_nav_indicator_css( $variant )
		);
	}

	wp_add_inline_style( 'rma-nav', $indicator_css );

	wp_add_inline_script(
		'rma-admin-ui',
		'var RMA_ADMIN = ' . wp_json_encode(
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'rma_admin_nonce' ),
			)
		) . ';',
		'before'
	);
}
