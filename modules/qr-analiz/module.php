<?php
/**
 * Modül: QR Analiz (qr-analiz)
 *
 * Uygulamanın (admin/müdür paneli) konuştuğu Firebase kimlikli REST yüzeyi:
 *
 *   - `POST /wp-json/qrservis/v1/analytics`     — şube analitiği özeti.
 *   - `POST /wp-json/qrservis/v1/create-user`   — garson/müdür hesabı açma
 *     (yalnızca "ana site" işaretliyse kaydedilir).
 *
 * İkisi de aynı zemine oturur: çağıranın Firebase ID token'ı doğrulanır,
 * Firestore `users/{uid}` dokümanından rolü (admin/müdür) okunur ve yetki
 * buna göre verilir. Bu yüzden create-user ucu chatbot'un değil bu modülün
 * altındadır. Dosyalar eski qr-menu-official eklentisinden aynen taşındı;
 * burada yalnızca yükleme bağlantısı var.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modülü başlatır.
 *
 * QRMS_Module_Loader tarafından `plugins_loaded` (öncelik 20) sırasında
 * argümansız çağrılır. Taşınan dosyalar hook'larını dosya kapsamında
 * kaydettiği için require'lar bilinçli olarak bu fonksiyonun içindedir:
 * dosyanın yüklenmesi = hook'un kaydı. Bağlanılan `rest_api_init` ve
 * `admin_init` kancaları plugins_loaded'dan sonra tetiklenir.
 *
 * @return void
 */
function qrms_module_qr_analiz_init() {
	// QMO_Firestore sınıfı _qmo-ortak altındadır (ortak.php ile yüklenir):
	// her iki REST ucu da çağıranın kimliğini ve rolünü onun üzerinden
	// doğrular (id_token_dogrula → access_token → kullanici_doc).
	require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/ortak.php';

	require_once __DIR__ . '/rest-analytics.php';
	require_once __DIR__ . '/rest-create-user.php';

	// Menü analitiği: restoran-menu modülünün AJAX uçlarına takılıp menü
	// görüntülemelerini ve ürün tıklamalarını masa bazında kaydeder. İzleme
	// ön yüzde de çalışmak zorunda olduğu için is_admin() dışındadır.
	require_once __DIR__ . '/class-qrms-analitik.php';
	QRMS_Analitik::init();

	if ( is_admin() ) {
		// Firebase service account / şube kimliği / ana site bayrağı: uçların
		// dayandığı yapılandırma. Form _qmo-ortak altındadır, çünkü aynı
		// option'ları qr-chatbot da kullanır.
		require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/firebase-ayarlari.php';
		require_once __DIR__ . '/ayarlar-sayfasi.php';
		require_once __DIR__ . '/analitik-sayfasi.php';

		// "QR Analiz" satırı, modülün iki ekranını kart olarak listeleyen hub
		// ekranını açar; ekranların kendisi aşağıda ayrı sayfa olarak kaydedilir.
		QRMS_Admin::register_module_page( 'qr-analiz', 'qrms_module_qr_analiz_hub' );

		add_action( 'admin_menu', 'qrms_module_qr_analiz_admin_menu', 20 );

		add_action( 'admin_enqueue_scripts', 'qrms_module_qr_analiz_admin_assets' );
	}
}

/**
 * Analitik panelinin yönetim sayfası slug'ı.
 */
const QRMS_ANALITIK_SAYFA = 'qrms-analiz-panel';

/**
 * REST/Firebase ayar ekranının yönetim sayfası slug'ı.
 *
 * v1.0'da bu ekran modülün kendi satırındaydı (qrms-module-qr-analiz). Sol menü
 * tek seviyeye indirilince o slug modülün hub ekranı oldu; ayar ekranı kendi
 * slug'ına taşındı. Eski adres kırılmaz — hub'ı açar, oradan bir kart uzaktadır.
 */
const QRMS_ANALIZ_AYAR_SAYFA = 'qrms-analiz-ayarlar';

/**
 * Modülün iki ekranını kaydeder — ikisi de sol menüde GİZLİDİR.
 *
 * Sol menüde yalnızca "QR Analiz" satırı durur ve hub ekranını açar; ekranlar
 * gerçek, ayrı WordPress sayfaları olarak kaydolur (bkz.
 * QRMS_Admin::hide_module_subpages).
 *
 * @return void
 */
function qrms_module_qr_analiz_admin_menu() {
	global $submenu;

	$parent = QRMS_Admin::MENU_SLUG;

	// Modül lisansta aktif değilse "QR Analiz" satırı hiç kaydolmaz; o zaman
	// ekranlarının da kaydedilmemesi gerekir.
	if ( empty( $submenu[ $parent ] ) ) {
		return;
	}

	foreach ( qrms_module_qr_analiz_sayfalar() as $slug => $page ) {
		add_submenu_page(
			$parent,
			$page['title'],
			$page['title'],
			QRMS_Admin::CAPABILITY,
			$slug,
			QRMS_Admin::register_module_subpage( 'qr-analiz', $slug, $page['render'] )
		);
	}
}

/**
 * Modülün ekranları — TEK KAYNAK.
 *
 * Sayfa kaydı ve hub kartları aynı listeden beslenir; sıra kart sırasıdır.
 *
 * @return array<string,array{title:string,render:string,desc:string,icon:string}>
 */
function qrms_module_qr_analiz_sayfalar() {
	return array(
		QRMS_ANALITIK_SAYFA      => array(
			'title'  => __( 'Menü Analitiği', 'qrms' ),
			'render' => 'qrms_analitik_sayfasi',
			'desc'   => __( 'Menüye kaç kişi baktı, hangi ürünlere tıklandı, hangi masadan geldi.', 'qrms' ),
			'icon'   => 'dashicons-chart-area',
		),
		QRMS_ANALIZ_AYAR_SAYFA   => array(
			'title'  => __( 'Firebase & Şube Ayarları', 'qrms' ),
			'render' => 'qmo_analiz_ayar_sayfasi',
			'desc'   => __( 'Uygulamanın (müdür/garson paneli) kullandığı REST uçlarının yapılandırması.', 'qrms' ),
			'icon'   => 'dashicons-admin-generic',
		),
	);
}

/**
 * "QR Analiz" satırının açtığı hub ekranı.
 *
 * @return void
 */
function qrms_module_qr_analiz_hub() {
	$cards = array();

	foreach ( qrms_module_qr_analiz_sayfalar() as $slug => $page ) {
		$cards[] = array(
			'url'   => admin_url( 'admin.php?page=' . $slug ),
			'title' => $page['title'],
			'desc'  => $page['desc'],
			'icon'  => $page['icon'],
		);
	}

	QRMS_Admin::render_hub(
		array(
			'title' => __( 'QR Analiz', 'qrms' ),
			'intro' => __( 'Menü hareketlerinizin raporu ve uygulamanın bağlandığı uçların ayarları.', 'qrms' ),
			'cards' => $cards,
		)
	);
}

/**
 * Modülün ekranlarının yönetim varlıkları.
 *
 * Yalnızca bu modülün kendi sayfaları render edilirken yüklenir; ayar
 * ekranındaki ortak admin stili durum rozetlerini (qmo-durum-ok /
 * qmo-durum-eksik) biçimlendirir. Hub ekranının stili suite'in ortak
 * admin.css'inden gelir, burada bir şey gerekmez.
 *
 * @return void
 */
function qrms_module_qr_analiz_admin_assets() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	if ( QRMS_ANALITIK_SAYFA === $page ) {
		qrms_module_qr_analiz_panel_assets();
		return;
	}

	if ( QRMS_ANALIZ_AYAR_SAYFA !== $page ) {
		return;
	}

	wp_enqueue_style(
		'qmo-admin',
		QRMS_PLUGIN_URL . 'modules/_qmo-ortak/assets/css/admin.css',
		array(),
		QRMS_Helpers::asset_version( 'modules/_qmo-ortak/assets/css/admin.css' )
	);
}

/**
 * Analitik panelinin stil ve script'i.
 *
 * Panelin tüm içeriği (kartlar, grafik, tablolar) tek bir AJAX çağrısıyla
 * dolduğu için script'e nonce'lar ve JS'te basılan metinler buradan geçilir.
 *
 * @return void
 */
function qrms_module_qr_analiz_panel_assets() {
	wp_enqueue_style(
		'qrms-analitik',
		QRMS_PLUGIN_URL . 'modules/qr-analiz/assets/css/analitik.css',
		array(),
		QRMS_Helpers::asset_version( 'modules/qr-analiz/assets/css/analitik.css' )
	);

	wp_enqueue_script(
		'qrms-analitik',
		QRMS_PLUGIN_URL . 'modules/qr-analiz/assets/js/analitik.js',
		array(),
		QRMS_Helpers::asset_version( 'modules/qr-analiz/assets/js/analitik.js' ),
		true
	);

	wp_localize_script(
		'qrms-analitik',
		'qrmsAnalitik',
		array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( QRMS_Analitik::NONCE ),
			'csvNonce' => wp_create_nonce( QRMS_Analitik::NONCE_CSV ),
			'i18n'     => array(
				'hourly'          => __( 'Saatlik — Bugün', 'qrms' ),
				'daily'           => __( 'Günlük — Son 30 gün', 'qrms' ),
				'weekly'          => __( 'Haftalık — Son 12 hafta', 'qrms' ),
				'monthly'         => __( 'Aylık — Son 12 ay', 'qrms' ),
				'masalar'         => __( 'Masalara göre — Son 30 gün', 'qrms' ),
				'colHourly'       => __( 'Saat', 'qrms' ),
				'colDaily'        => __( 'Tarih', 'qrms' ),
				'colWeekly'       => __( 'Hafta', 'qrms' ),
				'colMonthly'      => __( 'Ay', 'qrms' ),
				'colMasa'         => __( 'Masa', 'qrms' ),
				'colPeriod'       => __( 'Dönem', 'qrms' ),
				'cardViews'       => __( 'Menü Görüntüleme', 'qrms' ),
				'cardClicks'      => __( 'Ürün Tıklama', 'qrms' ),
				'cardUnique'      => __( 'Tekil Ziyaretçi', 'qrms' ),
				'cardTables'      => __( 'Hareketli Masa', 'qrms' ),
				'cardTablesSub'   => __( 'Toplam tıklama', 'qrms' ),
				'today'           => __( 'BUGÜN', 'qrms' ),
				'thisWeek'        => __( 'Bu hafta', 'qrms' ),
				'thisMonth'       => __( 'Bu ay', 'qrms' ),
				'ipNote'          => __( 'IP bazlı, gizlilik korumalı', 'qrms' ),
				'conversion'      => __( 'Dönüşüm', 'qrms' ),
				'periodTable'     => __( 'Dönem tablosu', 'qrms' ),
				'rows'            => __( 'satır', 'qrms' ),
				'total'           => __( 'TOPLAM', 'qrms' ),
				'lastSeen'        => __( 'Son hareket', 'qrms' ),
				'lastClick'       => __( 'Son Tıklama', 'qrms' ),
				'tableCount'      => __( 'Masa Sayısı', 'qrms' ),
				'action'          => __( 'İşlem', 'qrms' ),
				'filterTable'     => __( 'Bu masayı incele', 'qrms' ),
				'allTables'       => __( 'Tüm masalar', 'qrms' ),
				'product'         => __( 'Ürün', 'qrms' ),
				'category'        => __( 'Kategori', 'qrms' ),
				'totalClicks'     => __( 'Toplam Tıklama', 'qrms' ),
				'uniqueClicks'    => __( 'Tekil Tıklama', 'qrms' ),
				'popularity'      => __( 'Popülerlik', 'qrms' ),
				'loading'         => __( 'Yükleniyor', 'qrms' ),
				'loadingChart'    => __( 'Grafik yükleniyor', 'qrms' ),
				'loadError'       => __( 'Veri yüklenemedi. Sayfayı yenileyin.', 'qrms' ),
				'noData'          => __( 'Bu dönemde henüz veri yok.', 'qrms' ),
				'noProducts'      => __( 'Seçili dönemde henüz ürün tıklaması yok.', 'qrms' ),
				'noProductsTable' => __( 'Bu masada henüz ürün tıklaması yok.', 'qrms' ),
				'confirmAll'      => __( 'Tüm görüntüleme ve tıklama kayıtları kalıcı olarak silinecek. Bu işlem geri alınamaz.', 'qrms' ),
				'confirmTable'    => __( 'Yalnızca bu masanın kayıtları silinecek:', 'qrms' ),
				'deleting'        => __( 'Siliniyor…', 'qrms' ),
			),
		)
	);
}
