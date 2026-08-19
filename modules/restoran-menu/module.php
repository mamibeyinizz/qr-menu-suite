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

		// CPT ve taksonomi ekranlarının suite menüsündeki yeri ve vurgusu.
		// Öncelik 20: hem çekirdeğin _add_post_type_submenus()'ü hem
		// QRMS_Admin::register_menu() öncelik 10'da çalışır.
		add_action( 'admin_menu', 'qrms_module_restoran_menu_admin_menu', 20 );
		add_filter( 'parent_file', 'qrms_module_restoran_menu_parent_file' );
		add_filter( 'submenu_file', 'qrms_module_restoran_menu_submenu_file' );
	}
}

/**
 * Modülün suite menüsündeki alt satırları: eksik olanları ekler, sırayı düzeltir.
 *
 * WordPress iki seviyeli bir admin menüsüne sahiptir — "Restoran Menü" girişinin
 * ALTINA giriş eklenemez. Bu yüzden ürün/kategori ekranları "QR Menü"nün doğrudan
 * alt öğesidir; modüle ait olduklarını göstermek için etiketleri "—" ile
 * öneklenir. "Restoran Menü" girişinin kendisi zaten sekmeli ayar ekranını açar,
 * bu yüzden ayrıca bir "Ayarlar" satırı yoktur.
 *
 * CPT'nin show_in_menu'sü bir string olduğunda çekirdek (_add_post_type_submenus,
 * wp-includes/post.php) YALNIZCA ürün listesi satırını ekler; "Ürün Ekle" ve
 * taksonomi satırları wp-admin/menu.php'de sadece top-level menü alan CPT'ler
 * için üretildiğinden hiç oluşmaz. Bu yüzden taksonomilerin kendi show_in_menu
 * ayarını değiştirmek bir işe yaramaz — üç satır burada elle eklenir.
 *
 * @return void
 */
function qrms_module_restoran_menu_admin_menu() {
	global $submenu;

	$parent = QRMS_Admin::MENU_SLUG;

	// Modül lisansta aktif değilse "Restoran Menü" satırı hiç kaydolmaz;
	// o zaman burada düzenlenecek bir şey de yoktur.
	if ( empty( $submenu[ $parent ] ) ) {
		return;
	}

	add_submenu_page(
		$parent,
		__( 'Ürün Ekle', 'qrms' ),
		'— ' . __( 'Ürün Ekle', 'qrms' ),
		'edit_posts',
		'post-new.php?post_type=rma_menu_item'
	);

	add_submenu_page(
		$parent,
		__( 'Menü Kategorileri', 'qrms' ),
		'— ' . __( 'Kategoriler', 'qrms' ),
		'manage_categories',
		'edit-tags.php?taxonomy=rma_category&post_type=rma_menu_item'
	);

	add_submenu_page(
		$parent,
		__( 'Alerjenler', 'qrms' ),
		'— ' . __( 'Alerjenler', 'qrms' ),
		'manage_categories',
		'edit-tags.php?taxonomy=rma_allergen&post_type=rma_menu_item'
	);

	// Çekirdeğin eklediği ürün listesi satırının etiketi labels->all_items'tan
	// gelir ("Menü Ürünleri"). Etiket öneki kaynak labels dizisine yazılmaz;
	// tüm menü sunumu tek yerde, burada durur.
	foreach ( $submenu[ $parent ] as $index => $row ) {
		if ( isset( $row[2] ) && 'edit.php?post_type=rma_menu_item' === $row[2] ) {
			$submenu[ $parent ][ $index ][0] = '— ' . __( 'Ürünler', 'qrms' );
			break;
		}
	}

	$submenu[ $parent ] = qrms_module_restoran_menu_submenu_sirala( $submenu[ $parent ] );
}

/**
 * Modülün satırlarını "Restoran Menü" girişinin hemen ardına taşır.
 *
 * Sıralama pass'i şart: çekirdeğin _add_post_type_submenus() kancası WordPress
 * önyüklemesi sırasında kaydolduğu için, aynı önceliğe (10) sahip
 * QRMS_Admin::register_menu()'den ÖNCE çalışır ve ürün listesi satırını
 * "Genel Bakış"tan bile önce diziye sokar.
 *
 * WordPress'e bağımlılığı yoktur (saf dizi dönüşümü), bu yüzden doğrudan test
 * edilir. Sonuç array_values() ile yeniden indekslenir: WordPress alt menüleri
 * anahtara göre sıraladığından 0..n indeksleme buradaki sırayı korur.
 *
 * @param array $rows $submenu[QRMS_Admin::MENU_SLUG] satırları.
 * @return array Yeniden sıralanmış satırlar.
 */
function qrms_module_restoran_menu_submenu_sirala( array $rows ) {
	$module_slug = QRMS_Admin::get_module_page_slug( 'restoran-menu' );

	// İstenen sıra; dizideki gerçek konumlarından bağımsızdır.
	$child_slugs = array(
		'edit.php?post_type=rma_menu_item',
		'post-new.php?post_type=rma_menu_item',
		'edit-tags.php?taxonomy=rma_category&post_type=rma_menu_item',
		'edit-tags.php?taxonomy=rma_allergen&post_type=rma_menu_item',
	);

	$others   = array();
	$children = array();
	$anchor   = -1;

	foreach ( $rows as $row ) {
		$slug = isset( $row[2] ) ? $row[2] : '';
		$key  = array_search( $slug, $child_slugs, true );

		if ( false !== $key ) {
			$children[ $key ] = $row;
			continue;
		}

		$others[] = $row;

		if ( $module_slug === $slug ) {
			$anchor = count( $others );
		}
	}

	// "Restoran Menü" satırı yoksa taşınacak bir çapa da yok: satırlar
	// bulundukları sırada bırakılır.
	if ( -1 === $anchor ) {
		return array_values( $rows );
	}

	ksort( $children );

	return array_values(
		array_merge(
			array_slice( $others, 0, $anchor ),
			array_values( $children ),
			array_slice( $others, $anchor )
		)
	);
}

/**
 * Modülün ekranlarında suite menüsünün açık ve vurgulu kalmasını sağlar.
 *
 * Bu ekranlarda çekirdek $parent_file'ı "edit.php?post_type=rma_menu_item"
 * yapar; o artık bir top-level menü olmadığı için QR Menü ne açılır ne
 * vurgulanırdı.
 *
 * @param string $parent_file Çekirdeğin belirlediği üst menü dosyası.
 * @return string
 */
function qrms_module_restoran_menu_parent_file( $parent_file ) {
	return qrms_module_restoran_menu_ekranimiz_mi() ? QRMS_Admin::MENU_SLUG : $parent_file;
}

/**
 * Açık ekrana karşılık gelen alt menü satırını vurgular.
 *
 * @param string $submenu_file Çekirdeğin belirlediği alt menü dosyası.
 * @return string
 */
function qrms_module_restoran_menu_submenu_file( $submenu_file ) {
	if ( ! qrms_module_restoran_menu_ekranimiz_mi() ) {
		return $submenu_file;
	}

	global $pagenow;

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
	$page     = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	// Eski ayarlar adresi (edit.php?post_type=rma_menu_item&page=rma_settings).
	// Sayfa erişilebilir kalmaya devam eder — kaydı $_registered_pages üzerinden
	// çalışır, üst menüsünün görünür olması gerekmez; burada yalnızca doğru
	// satır vurgulanır. İçe aktarma yönlendirmeleri hâlâ bu adrese düşüyor.
	if ( 'rma_settings' === $page ) {
		return QRMS_Admin::get_module_page_slug( 'restoran-menu' );
	}

	if ( 'edit-tags.php' === $pagenow || 'term.php' === $pagenow ) {
		return 'edit-tags.php?taxonomy=' . $taxonomy . '&post_type=rma_menu_item';
	}

	if ( 'post-new.php' === $pagenow ) {
		return 'post-new.php?post_type=rma_menu_item';
	}

	// Liste ekranı ve mevcut ürünün düzenleme ekranı "Ürünler" satırında kalır.
	return 'edit.php?post_type=rma_menu_item';
}

/**
 * Şu an modülün ekranlarından birinde miyiz?
 *
 * @return bool
 */
function qrms_module_restoran_menu_ekranimiz_mi() {
	global $pagenow, $typenow;

	if ( 'rma_menu_item' === $typenow ) {
		return true;
	}

	if ( 'edit-tags.php' === $pagenow || 'term.php' === $pagenow ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';

		return in_array( $taxonomy, array( 'rma_category', 'rma_allergen' ), true );
	}

	return false;
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
