<?php
/**
 * Modül: Menü Mühendisliği (qr-menu-muhendisligi)
 *
 * Ürün başına maliyet (manuel ya da reçeteden) girilir; satış ve etkileşim
 * verisiyle birleştirilip Kasavana–Smith matrisi üretilir: her ürün Yıldız,
 * İş Atı, Bulmaca ya da Köpek kutusuna düşer ve somut bir aksiyon cümlesi
 * alır.
 *
 * Veri kaynakları:
 *   - Ürün, fiyat, kategori, malzeme : restoran-menu modülü (CPT + meta + taksonomi)
 *   - Satış / sepet / görüntülenme    : qr-analiz modülünün rma_analytics tablosu
 *
 * İki modül de pasifse rapor boş durum ekranı basar ve ne yapılacağını yazar;
 * ölümcül hata vermez.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/** Rapor ekranının sayfa slug'ı. */
const QRMS_MM_RAPOR_SAYFA = 'qrms-mm-rapor';

/** Ürün maliyetleri ekranının sayfa slug'ı. */
const QRMS_MM_MALIYET_SAYFA = 'qrms-mm-maliyet';

/** Malzeme fiyatları ekranının sayfa slug'ı. */
const QRMS_MM_MALZEME_SAYFA = 'qrms-mm-malzeme';

/** Ayarlar ekranının sayfa slug'ı. */
const QRMS_MM_AYAR_SAYFA = 'qrms-mm-ayarlar';

/**
 * Modülü başlatır.
 *
 * QRMS_Module_Loader tarafından `plugins_loaded` (öncelik 20) sırasında
 * argümansız çağrılır.
 *
 * @return void
 */
function qrms_module_qr_menu_muhendisligi_init() {
	require_once __DIR__ . '/includes/class-qrms-mm-hesap.php';
	require_once __DIR__ . '/includes/class-qrms-mm-maliyet.php';
	require_once __DIR__ . '/includes/class-qrms-mm-rapor.php';
	require_once __DIR__ . '/includes/bicim.php';

	// Malzeme fiyatı toplu güncellemesi sınırı aştığında arka plana atılır.
	add_action( 'qrms_mm_recete_yenile', array( 'QRMS_MM_Maliyet', 'receteleri_yenile' ) );

	if ( ! is_admin() ) {
		return;
	}

	require_once __DIR__ . '/includes/ajax.php';
	require_once __DIR__ . '/includes/export-csv.php';
	require_once __DIR__ . '/includes/admin/hub-sayfasi.php';
	require_once __DIR__ . '/includes/admin/rapor-sayfasi.php';
	require_once __DIR__ . '/includes/admin/maliyet-sayfasi.php';
	require_once __DIR__ . '/includes/admin/malzeme-sayfasi.php';
	require_once __DIR__ . '/includes/admin/ayarlar-sayfasi.php';

	QRMS_Admin::register_module_page( 'qr-menu-muhendisligi', 'qrms_mm_hub' );

	add_action( 'admin_menu', 'qrms_mm_admin_menu' );
	add_action( 'admin_enqueue_scripts', 'qrms_mm_admin_assets' );
}

/**
 * Modülün alt ekranları.
 *
 * @return array<string,array{title:string,render:callable,desc:string,icon:string}>
 */
function qrms_mm_sayfalar() {
	return array(
		QRMS_MM_RAPOR_SAYFA   => array(
			'title'  => __( 'Menü Mühendisliği Raporu', 'qrms' ),
			'render' => 'qrms_mm_rapor_sayfasi',
			'desc'   => __( 'Hangi ürün para kazandırıyor, hangisi kaybettiriyor — ve ne yapmalısınız.', 'qrms' ),
			'icon'   => 'dashicons-chart-pie',
		),
		QRMS_MM_MALIYET_SAYFA => array(
			'title'  => __( 'Ürün Maliyetleri', 'qrms' ),
			'render' => 'qrms_mm_maliyet_sayfasi',
			'desc'   => __( 'Her ürünün maliyetini girin ya da reçetesinden hesaplatın.', 'qrms' ),
			'icon'   => 'dashicons-money-alt',
		),
		QRMS_MM_MALZEME_SAYFA => array(
			'title'  => __( 'Malzeme Fiyatları', 'qrms' ),
			'render' => 'qrms_mm_malzeme_sayfasi',
			'desc'   => __( 'Malzemelerin birim fiyatları; reçeteli ürünlerin maliyeti buradan hesaplanır.', 'qrms' ),
			'icon'   => 'dashicons-carrot',
		),
		QRMS_MM_AYAR_SAYFA    => array(
			'title'  => __( 'Ayarlar', 'qrms' ),
			'render' => 'qrms_mm_ayarlar_sayfasi',
			'desc'   => __( 'Popülerlik eşiği, fire yüzdesi ve varsayılan rapor aralığı.', 'qrms' ),
			'icon'   => 'dashicons-admin-settings',
		),
	);
}

/**
 * Alt ekranları gizli sayfa olarak kaydeder.
 *
 * Sol menüye satır YAZILMAZ; ekranlara hub kartlarından gidilir
 * (bkz. QRMS_Admin::register_module_subpage).
 *
 * @return void
 */
function qrms_mm_admin_menu() {
	if ( ! QRMS_Module_Loader::is_module_active( 'qr-menu-muhendisligi' ) ) {
		return;
	}

	foreach ( qrms_mm_sayfalar() as $slug => $sayfa ) {
		add_submenu_page(
			QRMS_Admin::MENU_SLUG,
			$sayfa['title'],
			$sayfa['title'],
			QRMS_Admin::CAPABILITY,
			$slug,
			QRMS_Admin::register_module_subpage( 'qr-menu-muhendisligi', $slug, $sayfa['render'] )
		);
	}
}

/**
 * Bu modülün bir ekranında mıyız?
 *
 * @return bool
 */
function qrms_mm_ekranda_mi() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	if ( QRMS_Admin::get_module_page_slug( 'qr-menu-muhendisligi' ) === $page ) {
		return true;
	}

	return isset( qrms_mm_sayfalar()[ $page ] );
}

/**
 * Yönetim varlıkları — yalnızca bu modülün ekranlarında.
 *
 * @return void
 */
function qrms_mm_admin_assets() {
	if ( ! qrms_mm_ekranda_mi() ) {
		return;
	}

	wp_enqueue_style(
		'qrms-mm-admin',
		QRMS_PLUGIN_URL . 'modules/qr-menu-muhendisligi/assets/css/admin.css',
		array( 'qrms-admin' ),
		QRMS_Helpers::asset_version( 'modules/qr-menu-muhendisligi/assets/css/admin.css' )
	);

	wp_enqueue_script(
		'qrms-mm-admin',
		QRMS_PLUGIN_URL . 'modules/qr-menu-muhendisligi/assets/js/admin.js',
		array(),
		QRMS_Helpers::asset_version( 'modules/qr-menu-muhendisligi/assets/js/admin.js' ),
		true
	);

	wp_localize_script(
		'qrms-mm-admin',
		'QRMS_MM',
		array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'qrms_mm' ),
			'kaydedildi' => __( 'Kaydedildi', 'qrms' ),
			'hata'      => __( 'Kaydedilemedi', 'qrms' ),
			'siliniyor' => __( 'Satırı kaldır', 'qrms' ),
			'malzemeSec' => __( 'Malzeme seçin', 'qrms' ),
		)
	);
}
