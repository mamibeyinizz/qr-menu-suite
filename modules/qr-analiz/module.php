<?php
/**
 * Modül: QR Analiz (qr-analiz)
 *
 * Modülün TEK ekranı vardır: Menü Analitiği. Sol menüdeki "İstatistikler" satırı
 * doğrudan o ekranı açar — hub yoktur, çünkü dallanacak ikinci bir ekran da
 * yoktur. (v1.0'da modülün altında "Firebase & Şube Ayarları" ekranı da vardı;
 * yapılandırdığı şey raporlama değil kimlik doğrulama olduğu için Güvenlik
 * Ayarı modülüne taşındı — bkz. modules/qr-masa-oturum-guvenligi/module.php.)
 *
 * Uygulamanın (admin/müdür paneli) konuştuğu Firebase kimlikli REST yüzeyi
 * modülün altında KALIR:
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
		require_once __DIR__ . '/analitik-sayfasi.php';

		// "İstatistikler" satırı doğrudan analitik panelini açar.
		QRMS_Admin::register_module_page( 'qr-analiz', 'qrms_analitik_sayfasi' );

		add_action( 'admin_menu', 'qrms_module_qr_analiz_admin_menu', 20 );

		add_action( 'admin_enqueue_scripts', 'qrms_module_qr_analiz_admin_assets' );
	}
}

/**
 * Analitik panelinin ESKİ yönetim sayfası slug'ı.
 *
 * Panel artık modül satırının kendisidir (qrms-module-qr-analiz). Bu adres
 * yalnızca geriye dönük uyumluluk için kayıtlı kalır — yer imleri ve dış
 * bağlantılar kırılmasın diye panele YÖNLENDİRİR. Aynı desen restoran-menu
 * modülünde de var (bkz. get_legacy_page_map()).
 */
const QRMS_ANALITIK_SAYFA = 'qrms-analiz-panel';

/**
 * Panelin eski adresini gizli bir sayfa olarak kaydeder.
 *
 * Üst menü olarak null yerine '' kullanılır: ikisi de aynı
 * (admin_page_<slug>) hook'unu üretir ama '' PHP 8.1+ üzerinde
 * plugin_basename() içindeki null deprecation uyarısını doğurmaz.
 *
 * @return void
 */
function qrms_module_qr_analiz_admin_menu() {
	global $submenu;

	// Modül lisansta aktif değilse "QR Analiz" satırı hiç kaydolmaz; o zaman
	// eski adresinin de kaydedilmemesi gerekir.
	if ( empty( $submenu[ QRMS_Admin::MENU_SLUG ] ) ) {
		return;
	}

	add_submenu_page(
		'',
		__( 'Menü Analitiği', 'qrms' ),
		__( 'Menü Analitiği', 'qrms' ),
		QRMS_Admin::CAPABILITY,
		QRMS_ANALITIK_SAYFA,
		'qrms_module_qr_analiz_eski_adresi_yonlendir'
	);
}

/**
 * Eski panel adresinden modül satırına yönlendirir.
 *
 * @return void
 */
function qrms_module_qr_analiz_eski_adresi_yonlendir() {
	wp_safe_redirect( QRMS_Admin::get_module_page_url( 'qr-analiz' ) );
	exit;
}

/**
 * Modülün ekranının yönetim varlıkları.
 *
 * @return void
 */
function qrms_module_qr_analiz_admin_assets() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	if ( QRMS_Admin::get_module_page_slug( 'qr-analiz' ) !== $page ) {
		return;
	}

	qrms_module_qr_analiz_panel_assets();
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
