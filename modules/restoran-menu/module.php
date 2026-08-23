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

	// Kısa kod rehberine bildirim. add_shortcode() çağrıları dosya kapsamında
	// (qr-menu.php, shortcode-vitrin.php, shortcode-slider.php) yapılır; rehber
	// yalnızca bu tek kayıttan beslenir.
	QRMS_Shortcodes::register(
		'restoran-menu',
		array(
			array(
				'tag'   => 'restaurant_menu',
				'title' => __( 'Restoran Menüsü', 'qrms' ),
				'desc'  => __( 'Ürünlerinizi kategorilere ayrılmış, aranabilir ve filtrelenebilir menü olarak gösterir. Menü sayfanızın ana kısa kodu budur.', 'qrms' ),
				'attrs' => array(
					array(
						'name'    => 'show_search',
						'default' => 'yes',
						'desc'    => __( 'Arama kutusunu gizlemek için "no" yazın.', 'qrms' ),
					),
				),
			),
			array(
				'tag'   => 'qrms_urun_vitrini',
				'title' => __( 'Ürün Vitrini', 'qrms' ),
				'desc'  => __( 'Seçtiğiniz ürünleri kayan bir vitrin şeridinde gösterir. Her vitrinin kendi numarası vardır.', 'qrms' ),
				'usage' => '[qrms_urun_vitrini id="1"]',
				'attrs' => array(
					array(
						'name'    => 'id',
						'default' => '',
						'desc'    => __( 'Vitrin numarası — Ürün Vitrini ekranından öğrenin. Zorunludur.', 'qrms' ),
					),
				),
			),
			array(
				'tag'   => 'qmo_one_cikan_slider',
				'title' => __( 'Öne Çıkan Slider', 'qrms' ),
				'desc'  => __( 'Menünün üstünde kayan görsel şerit: öne çıkarmak istediğiniz ürün grupları.', 'qrms' ),
				'attrs' => array(
					array(
						'name'    => 'show_title',
						'default' => 'yes',
						'desc'    => __( 'Slayt başlıklarını gizlemek için "no" yazın.', 'qrms' ),
					),
				),
			),
			array(
				'tag'   => 'rma_qr_notice',
				'title' => __( 'Karekod Bilgilendirme Metni', 'qrms' ),
				'desc'  => __( 'Karekod kullanamayan müşteriler için yasal bilgilendirme cümlesini basar.', 'qrms' ),
				'attrs' => array(
					array(
						'name'    => 'style',
						'default' => 'inline',
						'desc'    => __( 'Dikkat çeken kutulu görünüm için "banner" yazın.', 'qrms' ),
					),
				),
			),
		)
	);

	if ( is_admin() ) {
		// "Restoran Menü" satırı, modülün tüm işlerini listeleyen başlangıç
		// ekranını açar; işlerin kendisi aşağıda ayrı ayrı sayfa olarak
		// kaydedilir.
		QRMS_Admin::register_module_page(
			'restoran-menu',
			array( Restaurant_Menu_Automation::get_instance(), 'render_admin_page' )
		);

		add_action( 'admin_enqueue_scripts', 'qrms_module_restoran_menu_admin_assets' );

		// CPT ve taksonomi ekranlarının suite menüsündeki yeri ve vurgusu.
		// Öncelik 20: hem çekirdeğin _add_post_type_submenus()'ü hem
		// QRMS_Admin::register_menu() öncelik 10'da çalışır.
		add_action( 'admin_menu', 'qrms_module_restoran_menu_admin_menu', 20 );

		// Ürün Vitrini tabloları. Suite modüllerinin kendi
		// register_activation_hook'u yok (loader plugins_loaded'da yükler),
		// bu yüzden qr-ceviri'deki gibi sürüm guard'ıyla kurulur: option
		// güncelse hiçbir şey yapılmaz, tek bir get_option maliyeti kalır.
		add_action( 'admin_init', array( 'RMA_Vitrin_DB', 'belki_kur' ) );
		add_action( 'admin_init', array( 'RMA_Kampanya_DB', 'belki_kur' ) );
		add_filter( 'parent_file', 'qrms_module_restoran_menu_parent_file' );
		add_filter( 'submenu_file', 'qrms_module_restoran_menu_submenu_file' );
	}
}

/**
 * Modülün ekranlarını kaydeder — hepsi sol menüde GİZLİDİR.
 *
 * Sol admin menüsü tek seviyeye indirildi: orada yalnızca "Restoran Menü"
 * satırı durur, o satır da modülün tüm işlerini kart olarak listeleyen hub
 * ekranını (render_admin_page) açar. Buradaki sayfalar gerçek, ayrı
 * WordPress sayfaları olarak kaydolmaya devam eder — adresleri, hook adları
 * ve yetkileri değişmez; yalnızca menüde boyanmazlar
 * (bkz. QRMS_Admin::hide_module_subpages).
 *
 * Ürün listesi, "Ürün Ekle" ve taksonomi ekranları çekirdeğin kendi
 * sayfalarıdır; onlar için menü satırı üretmeye artık gerek yok. CPT'nin
 * show_in_menu'sü MENU_SLUG olarak kalır: ekranlarda $parent_file'ın
 * çözülmesi buna bağlıdır, üretilen satırı da beyaz liste düşürür.
 *
 * @return void
 */
function qrms_module_restoran_menu_admin_menu() {
	global $submenu;

	$parent = QRMS_Admin::MENU_SLUG;

	// Modül lisansta aktif değilse "Restoran Menü" satırı hiç kaydolmaz;
	// o zaman alt ekranlarının da kaydedilmemesi gerekir.
	if ( empty( $submenu[ $parent ] ) ) {
		return;
	}

	// Sayfa tanımı modülün içinde (get_subpages) tek yerde durur.
	$rma = Restaurant_Menu_Automation::get_instance();

	foreach ( $rma->get_subpages() as $slug => $page ) {
		add_submenu_page(
			$parent,
			$page['title'],
			$page['menu_title'],
			QRMS_Admin::CAPABILITY,
			$slug,
			QRMS_Admin::register_module_subpage( 'restoran-menu', $slug, array( $rma, $page['render'] ) )
		);
	}
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

	// Modülün ekranlarının menüde kendi satırı yoktur; ürün listesi, ürün
	// düzenleme, taksonomiler ve Öne Çıkan Slider dahil hepsinde "Restoran
	// Menü" satırı vurgulanır.
	return QRMS_Admin::get_module_page_slug( 'restoran-menu' );
}

/**
 * Şu an modülün ekranlarından birinde miyiz?
 *
 * @return bool
 */
function qrms_module_restoran_menu_ekranimiz_mi() {
	global $pagenow, $typenow;

	// Öne Çıkan Slider da modülün bir parçasıdır: kendi ekranlarında suite
	// menüsü açık kalmalı ve "Öne Çıkanlar" satırı vurgulanmalıdır.
	if ( 'rma_menu_item' === $typenow || 'qmo_slide' === $typenow ) {
		return true;
	}

	if ( 'edit-tags.php' === $pagenow || 'term.php' === $pagenow ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';

		return in_array( $taxonomy, array( 'rma_category', 'rma_allergen', 'rma_ingredient' ), true );
	}

	return false;
}

/**
 * Modülün suite menüsündeki ekranlarının yönetim varlıkları.
 *
 * Kaynaktaki RMA_Admin_Pages_Trait::admin_scripts() varlıkları yalnızca
 * `rma_menu_item` ekranlarında yükler (`get_current_screen()->post_type`
 * kontrolü). Suite'in kendi sayfalarında post_type boş olduğu için o dal hiç
 * çalışmaz; renk seçici ve sürükle-bırak varlıksız kalırdı. Burada aynı
 * ekranların ihtiyaç duyduğu varlıklar birebir aynı handle ve bağımlılıklarla
 * kuyruğa alınır — ekranların kendi kodu değişmez.
 *
 * Koşul, qr-masa modülündeki desenin aynısı: varlıklar yalnızca bu modülün
 * kendi form ekranları render edilirken yüklenir. Hub ekranı listenin dışındadır:
 * onun ihtiyacı olan her şey suite'in ortak admin.css'indedir.
 *
 * @return void
 */
function qrms_module_restoran_menu_admin_assets() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	$rma = Restaurant_Menu_Automation::get_instance();

	// Hub ekranının stili suite'in ortak admin.css'inden gelir (QRMS_Admin
	// onu her qrms* sayfasında kuyruğa alır); modülün kendi varlıklarına
	// ihtiyacı yoktur.
	if ( ! array_key_exists( $page, $rma->get_subpages() ) ) {
		return;
	}

	$url = QRMS_PLUGIN_URL . 'modules/restoran-menu/';

	// Form ekranlarının hepsinde renk seçici ve sürükle-bırak gerekir.
	wp_enqueue_script( 'jquery-ui-sortable' );
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );

	$deps = array( 'jquery', 'wp-color-picker', 'jquery-ui-sortable' );

	$modul = 'modules/restoran-menu/';

	wp_enqueue_style( 'rma-admin-ui', $url . 'assets/css/admin-ui.css', array(), QRMS_Helpers::asset_version( $modul . 'assets/css/admin-ui.css' ) );
	wp_enqueue_script( 'rma-admin-ui', $url . 'assets/js/admin-ui.js', $deps, QRMS_Helpers::asset_version( $modul . 'assets/js/admin-ui.js' ), true );

	// Görünüm sayfasındaki canlı önizleme, frontend'in gerçek nav
	// stylesheet'ini kullanır; aktif gösterge CSS'inin dört varyantı da
	// ekranın kendi kaynağından (get_nav_indicator_css) gelir.
	if ( 'qrms-rm-gorunum' === $page ) {
		wp_enqueue_style( 'rma-nav', $url . 'assets/css/rma-nav.css', array( 'rma-admin-ui' ), QRMS_Helpers::asset_version( $modul . 'assets/css/rma-nav.css' ) );
		wp_add_inline_style( 'rma-nav', $rma->get_nav_preview_css() );
	}

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
