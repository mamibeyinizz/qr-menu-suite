<?php
/**
 * Modül: QR Analiz (qr-analiz)
 *
 * Modül suite'in standart HUB desenini kullanır: sol menüdeki "İstatistikler"
 * satırı bir kart ızgarası açar, her konu kendi alt sayfasındadır (bkz.
 * hub-sayfasi.php). Kategoriler mevcut tek sayfalık panelden kademe kademe
 * taşınır; taşınana kadar panel "Tüm Veriler (klasik görünüm)" kartından
 * erişilebilir kalır.
 *
 * (v1.0'da modülün altında "Firebase & Şube Ayarları" ekranı da vardı;
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
		// Paylaşılan filtre bağlamı: kategori sayfaları arasında taşınan
		// zaman aralığı ve masa seçimi (yalnızca yönetimde gerekir).
		require_once __DIR__ . '/class-qrms-analitik-filtre.php';

		// Paylaşılan filtre çubuğu bileşeni ve onu kullanan ekranlar.
		require_once __DIR__ . '/filtre-cubugu.php';
		require_once __DIR__ . '/analitik-sayfasi.php';
		require_once __DIR__ . '/genel-sayfasi.php';
		require_once __DIR__ . '/urunler-sayfasi.php';
		require_once __DIR__ . '/hub-sayfasi.php';

		// "İstatistikler" satırı artık hub ekranıdır; klasik panel onun bir
		// kartı uzağındadır.
		QRMS_Admin::register_module_page( 'qr-analiz', 'qrms_module_qr_analiz_hub' );

		add_action( 'admin_menu', 'qrms_module_qr_analiz_admin_menu', 20 );

		// Alt sayfaların "geri" bağlantısı da aktif filtreyi taşımalı; aksi
		// hâlde kullanıcı hub'a döndüğünde seçimi sıfırlanırdı.
		add_filter( 'qrms_subpage_back_url', 'qrms_module_qr_analiz_geri_url', 10, 2 );

		add_action( 'admin_enqueue_scripts', 'qrms_module_qr_analiz_admin_assets' );
	}
}

/**
 * Modülün alt sayfalarındaki "geri" bağlantısına filtreyi ekler.
 *
 * @param string $url         Çekirdeğin ürettiği hub adresi.
 * @param string $module_slug Alt sayfanın sahibi modül.
 * @return string
 */
function qrms_module_qr_analiz_geri_url( $url, $module_slug ) {
	if ( 'qr-analiz' !== $module_slug ) {
		return $url;
	}

	return QRMS_Analitik_Filtre::url( QRMS_Admin::get_module_page_slug( 'qr-analiz' ) );
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
 * Kategori alt sayfalarını, klasik paneli ve eski adresi kaydeder.
 *
 * Alt sayfalar GERÇEK alt menü sayfalarıdır (üst menü: QRMS_Admin::MENU_SLUG);
 * sol menüde görünmemeleri hide_module_subpages() ile, route çözüldükten sonra
 * sağlanır. Menüye yeni satır eklenmez — menü tek seviyeli kalır.
 *
 * Eski panel adresi (QRMS_ANALITIK_SAYFA) üst menüsüz kaydedilir: null yerine
 * '' kullanılır, ikisi de aynı (admin_page_<slug>) hook'unu üretir ama ''
 * PHP 8.1+ üzerinde plugin_basename() içindeki null deprecation uyarısını
 * doğurmaz.
 *
 * @return void
 */
function qrms_module_qr_analiz_admin_menu() {
	global $submenu;

	// Modül lisansta aktif değilse "İstatistikler" satırı hiç kaydolmaz; o
	// zaman ne alt sayfaları ne de eski adresi kaydedilmelidir.
	if ( empty( $submenu[ QRMS_Admin::MENU_SLUG ] ) ) {
		return;
	}

	$modul = QRMS_Helpers::get_module_name( 'qr-analiz' );

	foreach ( qrms_module_qr_analiz_sayfalar() as $slug => $sayfa ) {
		add_submenu_page(
			QRMS_Admin::MENU_SLUG,
			$sayfa['title'],
			$sayfa['title'],
			QRMS_Admin::CAPABILITY,
			$slug,
			QRMS_Admin::register_module_subpage( 'qr-analiz', $slug, $sayfa['render'] )
		);
	}

	// Klasik panel: kategoriler doldurulana kadar tüm veriye erişim.
	add_submenu_page(
		QRMS_Admin::MENU_SLUG,
		$modul . ' — ' . __( 'Tüm Veriler', 'qrms' ),
		__( 'Tüm Veriler', 'qrms' ),
		QRMS_Admin::CAPABILITY,
		QRMS_ANALITIK_KLASIK_SAYFA,
		QRMS_Admin::register_module_subpage( 'qr-analiz', QRMS_ANALITIK_KLASIK_SAYFA, 'qrms_analitik_sayfasi' )
	);

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
 * Modülün ekranlarının yönetim varlıkları.
 *
 * Hub ekranının stili suite'in ortak admin.css'inden gelir; panelin kendi
 * stili ve betiği yalnızca klasik panelde gerekir. (Kategori sayfaları
 * dolduruldukça bu liste onlarla genişleyecek.)
 *
 * @return void
 */
function qrms_module_qr_analiz_admin_assets() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	if ( QRMS_ANALITIK_KLASIK_SAYFA === $page ) {
		qrms_module_qr_analiz_panel_assets();
		return;
	}

	if ( 'qrms-an-genel' === $page ) {
		qrms_module_qr_analiz_genel_assets();
		return;
	}

	if ( 'qrms-an-urunler' === $page ) {
		qrms_module_qr_analiz_urunler_assets();
		return;
	}

	// Hub: teşhis kutusu panelin stilini kullanır (aynı bulgular, aynı
	// görünüm). Betiğe gerek yok, yalnızca stil kuyruğa girer.
	if ( QRMS_Admin::get_module_page_slug( 'qr-analiz' ) === $page ) {
		qrms_module_qr_analiz_panel_stili();
	}
}

/**
 * Analitik ekranlarının ORTAK betiği.
 *
 * Biçimlendirme, AJAX sarmalayıcısı, tablo iskeleti, grafik çizimi ve filtre
 * çubuğunun canlandırılması tek dosyadadır; her ekran onu bağımlılık olarak
 * yükler (bkz. assets/js/analitik-ortak.js).
 *
 * @return void
 */
function qrms_module_qr_analiz_ortak_betik() {
	wp_enqueue_script(
		'qrms-analitik-ortak',
		QRMS_PLUGIN_URL . 'modules/qr-analiz/assets/js/analitik-ortak.js',
		array(),
		QRMS_Helpers::asset_version( 'modules/qr-analiz/assets/js/analitik-ortak.js' ),
		true
	);
}

/**
 * "Genel Bakış" kategorisinin varlıkları.
 *
 * Sayfaya geçilen bağlam ADRESTEN çözülmüş filtredir: betik onu değiştirmez,
 * uca olduğu gibi geri gönderir. Böylece aralığın nasıl hesaplandığı tek
 * yerde (QRMS_Analitik_Filtre) kalır.
 *
 * @return void
 */
function qrms_module_qr_analiz_genel_assets() {
	qrms_module_qr_analiz_panel_stili();
	qrms_module_qr_analiz_ortak_betik();

	wp_enqueue_script(
		'qrms-analitik-genel',
		QRMS_PLUGIN_URL . 'modules/qr-analiz/assets/js/analitik-genel.js',
		array( 'qrms-analitik-ortak' ),
		QRMS_Helpers::asset_version( 'modules/qr-analiz/assets/js/analitik-genel.js' ),
		true
	);

	$masa       = QRMS_Analitik_Filtre::masa();
	$kirilimler = array();

	foreach ( qrms_analitik_kirilim_etiketleri() as $anahtar => $etiket ) {
		$kirilimler[ $anahtar ] = $etiket['label'];
	}

	wp_localize_script(
		'qrms-analitik-genel',
		'qrmsAnalitikGenel',
		array(
			'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( QRMS_Analitik::NONCE ),
			'donem'             => QRMS_Analitik_Filtre::donem(),
			'masa'              => $masa,
			'bas'               => QRMS_Analitik_Filtre::bas(),
			'bit'               => QRMS_Analitik_Filtre::bit(),
			'kirilim'           => QRMS_Analitik_Filtre::kirilim(),
			'kirilimEtiketleri' => $kirilimler,
			'aralikEtiketi'     => QRMS_Analitik_Filtre::etiket(),
			'masaEtiketi'       => '' !== $masa ? qrms_analitik_masa_etiketi( $masa ) : '',
			'i18n'              => array(
				'cardViews'      => __( 'Menü Görüntüleme', 'qrms' ),
				'cardClicks'     => __( 'Ürün Tıklama', 'qrms' ),
				'cardUnique'     => __( 'Tekil Ziyaretçi', 'qrms' ),
				'cardTables'     => __( 'Hareketli Masa', 'qrms' ),
				'cardTablesSub2' => __( 'Seçili aralıkta hareket eden masa', 'qrms' ),
				'vsPrev'         => __( 'Önceki döneme göre', 'qrms' ),
				'conversion'     => __( 'Dönüşüm', 'qrms' ),
				'ipNote'         => __( 'IP bazlı, gizlilik korumalı', 'qrms' ),
				'colHourly'      => __( 'Saat', 'qrms' ),
				'colDaily'       => __( 'Tarih', 'qrms' ),
				'colWeekly'      => __( 'Hafta', 'qrms' ),
				'colMonthly'     => __( 'Ay', 'qrms' ),
				'colPeriod'      => __( 'Dönem', 'qrms' ),
				'periodTable'    => __( 'Dönem tablosu', 'qrms' ),
				'rows'           => __( 'satır', 'qrms' ),
				'total'          => __( 'TOPLAM', 'qrms' ),
				'loadingChart'   => __( 'Grafik yükleniyor', 'qrms' ),
				'loadError'      => __( 'Veri yüklenemedi. Sayfayı yenileyin.', 'qrms' ),
				'noData'         => __( 'Bu dönemde henüz veri yok.', 'qrms' ),
			),
		)
	);
}

/**
 * "Ürünler" kategorisinin varlıkları.
 *
 * @return void
 */
function qrms_module_qr_analiz_urunler_assets() {
	qrms_module_qr_analiz_panel_stili();
	qrms_module_qr_analiz_ortak_betik();

	wp_enqueue_script(
		'qrms-analitik-urunler',
		QRMS_PLUGIN_URL . 'modules/qr-analiz/assets/js/analitik-urunler.js',
		array( 'qrms-analitik-ortak' ),
		QRMS_Helpers::asset_version( 'modules/qr-analiz/assets/js/analitik-urunler.js' ),
		true
	);

	wp_localize_script(
		'qrms-analitik-urunler',
		'qrmsAnalitikUrunler',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( QRMS_Analitik::NONCE ),
			'donem'   => QRMS_Analitik_Filtre::donem(),
			'masa'    => QRMS_Analitik_Filtre::masa(),
			'bas'     => QRMS_Analitik_Filtre::bas(),
			'bit'     => QRMS_Analitik_Filtre::bit(),
			'i18n'    => array(
				'product'         => __( 'Ürün', 'qrms' ),
				'category'        => __( 'Kategori', 'qrms' ),
				'status'          => __( 'Durum', 'qrms' ),
				'totalClicks'     => __( 'Toplam Tıklama', 'qrms' ),
				'uniqueClicks'    => __( 'Tekil Tıklama', 'qrms' ),
				'tableCount'      => __( 'Masa Sayısı', 'qrms' ),
				'lastClick'       => __( 'Son Tıklama', 'qrms' ),
				'popularity'      => __( 'Popülerlik', 'qrms' ),
				'itemCount'       => __( 'Ürün Sayısı', 'qrms' ),
				'share'           => __( 'Pay', 'qrms' ),
				'soldOut'         => __( 'Tükendi', 'qrms' ),
				'inStock'         => __( 'Stokta', 'qrms' ),
				'totalItems'      => __( 'Yayındaki ürün', 'qrms' ),
				'neverClicked'    => __( 'Hiç tıklanmamış', 'qrms' ),
				'soldOutCount'    => __( 'Tükendi işaretli', 'qrms' ),
				'capped'          => __( 'Ürün sayısı çok yüksek; liste ilk kayıtlarla sınırlandı.', 'qrms' ),
				'oldName'         => __( 'eski ad', 'qrms' ),
				'oldNameHint'     => __( 'Bu adla bir kategori artık yok; kayıtlar tıklama anındaki adı taşır.', 'qrms' ),
				'uncategorized'   => __( 'Kategorisi kaydedilmemiş tıklama', 'qrms' ),
				'prev'            => __( 'Önceki', 'qrms' ),
				'next'            => __( 'Sonraki', 'qrms' ),
				'loading'         => __( 'Yükleniyor', 'qrms' ),
				'loadError'       => __( 'Veri yüklenemedi. Sayfayı yenileyin.', 'qrms' ),
				'noProducts'      => __( 'Seçili dönemde henüz ürün tıklaması yok.', 'qrms' ),
				'noProductsTable' => __( 'Bu masada henüz ürün tıklaması yok.', 'qrms' ),
				'noItems'         => __( 'Yayında ürün bulunamadı.', 'qrms' ),
				'noCats'          => __( 'Seçili dönemde kategori verisi yok.', 'qrms' ),
			),
		)
	);
}

/**
 * Masanın panelde görünen adı (filtre çubuğundaki listeyle aynı kaynak).
 *
 * @param string $slug Masa slug'ı.
 * @return string
 */
function qrms_analitik_masa_etiketi( $slug ) {
	foreach ( QRMS_Analitik::masa_secenekleri() as $secenek ) {
		if ( $secenek['slug'] === $slug ) {
			return $secenek['label'];
		}
	}

	return $slug;
}

/**
 * Analitik panelinin stil dosyası.
 *
 * Hem hub hem klasik panel aynı stil kaynağını kullansın diye ayrıldı.
 *
 * @return void
 */
function qrms_module_qr_analiz_panel_stili() {
	wp_enqueue_style(
		'qrms-analitik',
		QRMS_PLUGIN_URL . 'modules/qr-analiz/assets/css/analitik.css',
		array(),
		QRMS_Helpers::asset_version( 'modules/qr-analiz/assets/css/analitik.css' )
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
	qrms_module_qr_analiz_panel_stili();
	qrms_module_qr_analiz_ortak_betik();

	wp_enqueue_script(
		'qrms-analitik',
		QRMS_PLUGIN_URL . 'modules/qr-analiz/assets/js/analitik.js',
		array( 'qrms-analitik-ortak' ),
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
			// Masa filtresi ADRESTEN gelir; "Bu masayı incele" düğmesi de
			// sayfayı bu şablonla yeniler (bkz. analitik.js).
			'masa'     => QRMS_Analitik_Filtre::masa(),
			'masaUrl'  => QRMS_Analitik_Filtre::url(
				QRMS_ANALITIK_KLASIK_SAYFA,
				array( QRMS_Analitik_Filtre::ARG_MASA => '__MASA__' )
			),
			// Yalnızca sayfada KALAN masa kesitinin metinleri; taşınan
			// bölümlerin dizeleri kendi ekranlarının varlıklarıyla gelir.
			'i18n'     => array(
				'colMasa'      => __( 'Masa', 'qrms' ),
				'cardViews'    => __( 'Menü Görüntüleme', 'qrms' ),
				'cardClicks'   => __( 'Ürün Tıklama', 'qrms' ),
				'cardUnique'   => __( 'Tekil Ziyaretçi', 'qrms' ),
				'lastSeen'     => __( 'Son hareket', 'qrms' ),
				'action'       => __( 'İşlem', 'qrms' ),
				'filterTable'  => __( 'Bu masayı incele', 'qrms' ),
				'total'        => __( 'TOPLAM', 'qrms' ),
				'loading'      => __( 'Yükleniyor', 'qrms' ),
				'loadError'    => __( 'Veri yüklenemedi. Sayfayı yenileyin.', 'qrms' ),
				'confirmAll'   => __( 'Tüm görüntüleme ve tıklama kayıtları kalıcı olarak silinecek. Bu işlem geri alınamaz.', 'qrms' ),
				'confirmTable' => __( 'Yalnızca bu masanın kayıtları silinecek:', 'qrms' ),
				'deleting'     => __( 'Siliniyor…', 'qrms' ),
			),
		)
	);
}
