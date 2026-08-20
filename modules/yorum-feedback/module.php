<?php
/**
 * Modül: Yorum & Feedback (yorum-feedback)
 *
 * Çoklu kriter puanlamalı müşteri yorumları (`{prefix}qrm_reviews`), Google
 * yorum yönlendirmesi (review gating), ödül/indirim kodu sistemi ve dinamik
 * form oluşturucu. Dosyalar `yorumfeedback` deposundaki "QR Menü Gelişmiş
 * Müşteri Yorumları & Değerlendirme" (v4.2.1) eklentisinden taşındı.
 *
 * MENÜ (suite'e uyarlama): kaynağın kendi "QR Yorumlar" ÜST MENÜSÜ kaldırıldı.
 * Kaynakta modülün yedi ekranı ayrı bir top-level menüde duruyordu ve suite
 * menüsündeki "Yorum & Feedback" satırı da bunlardan birini (Tüm Yorumlar)
 * ikinci kez basıyordu; aynı ekran iki menüden açılıyor, diğer altı ekran ise
 * suite menüsünde hiç görünmüyordu. Artık tek giriş noktası var: yedi ekranın
 * hepsi "QR Menü"nün doğrudan alt satırı, "Yorum & Feedback" ise onları
 * listeleyen başlangıç ekranı — restoran-menu modülündeki desenin aynısı.
 * Eski adreslerden gelenler qrm_pro_legacy_page_target() ile yönlendirilir.
 *
 * Bu modül `_qmo-ortak/ortak.php`'yi YÜKLEMEZ: `qrm_pro_` / `qrm_reward_` /
 * `qrm_cf_` ad alanıyla tamamen kendi kendine yeterlidir. Kaynakta tek bir
 * `qmo_` / `QMO_` referansı yoktur (masa oturumunu, ortak nonce'u ve ortak
 * varlık kayıt defterini kullanmaz); kendi hız sınırı ve cooldown guard'ları
 * vardır.
 *
 * Suite ile çakışma taraması temiz: fonksiyon adlarının, kısa kodların
 * (`qr_menu_reviews`, `qr_menu_contact`, `qr_menu_form`), AJAX action'larının,
 * option'ların ve `QRM_PRO_*` sabitlerinin hiçbiri suite'te yok. Tablolar
 * `qrm_` ön ekini qr-masa'nın `{prefix}qrm_tables` tablosuyla paylaşır ama
 * isimler ayrıdır (`qrm_reviews`, `qrm_form_fields`, `qrm_reward_codes`,
 * `qrm_custom_forms`, `qrm_custom_form_fields`, `qrm_custom_form_submissions`).
 *
 * DAĞITIM NOTU: eski tekil "QR Menü Gelişmiş Müşteri Yorumları" eklentisi
 * devre dışı bırakılmalıdır — bu modül onun yerini alır. Kaynağın 190
 * fonksiyonunun hiçbirinde function_exists() guard'ı yoktur, yani iki kopya
 * yan yana yüklenirse çift tanım ölümcül hatası verir. Aşağıdaki
 * `QRM_PRO_VERSION` kontrolü bunu fatal yerine sessiz devre dışı kalmaya
 * çevirir (eski eklenti daha erken yüklendiği için sabiti o tanımlar ve
 * çalışmaya devam eder), ama iki kopyayı birlikte çalışır kılmaz.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modülü başlatır.
 *
 * QRMS_Module_Loader tarafından `plugins_loaded` (öncelik 20) sırasında
 * argümansız çağrılır. Taşınan dosyalar hook'larını dosya kapsamında
 * kaydettiği için require bilinçli olarak bu fonksiyonun içindedir:
 * qr-menu-reviews.php sabitleri tanımlar ve dosyaların tamamını yükler.
 * Bağlanılan kancaların hepsi (`admin_menu`, `admin_enqueue_scripts`,
 * `admin_page_access_denied`, `wp_ajax_*`, `wp_footer`, kısa kodlar)
 * plugins_loaded'dan sonra tetiklenir.
 *
 * @return void
 */
function qrms_module_yorum_feedback_init() {
	// Eski tekil eklenti hâlâ aktifse sabiti o tanımlamıştır. Devam etmek 190
	// fonksiyonun tamamını yeniden tanımlamaya çalışmak (= fatal error) olurdu;
	// modül sessizce çekilir, site eski eklentiyle çalışmaya devam eder.
	if ( defined( 'QRM_PRO_VERSION' ) ) {
		return;
	}

	require_once __DIR__ . '/qr-menu-reviews.php';

	// Tabloların kurulumu/yükseltmesi. Kaynağın iki yolu da modül bağlamında
	// ölü kalır: register_activation_hook() bir eklenti dosyası olmadığı için
	// hiç tetiklenmez, kaynağın `plugins_loaded` (öncelik 10) kaydı ise bu
	// dosya zaten öncelik 20 içinde yüklendiğinden çoktan geçmiş bir önceliğe
	// eklenir ve hiç çalışmaz. Kaynağın kendi fonksiyonu burada doğrudan
	// çağrılır; sürüm option'ları eşleştiğinde iki get_option ile erken döner.
	qrm_pro_maybe_upgrade();

	if ( is_admin() ) {
		// Suite menüsündeki "Yorum & Feedback" satırı, modülün yedi ekranını
		// listeleyen başlangıç ekranını açar; ekranların kendisi aşağıda ayrı
		// ayrı sayfa olarak kaydedilir.
		QRMS_Admin::register_module_page( 'yorum-feedback', 'qrm_pro_admin_hub' );

		add_action( 'admin_enqueue_scripts', 'qrms_module_yorum_feedback_admin_assets' );

		// Öncelik 20: QRMS_Admin::register_menu() öncelik 10'da çalışır, yani
		// "Yorum & Feedback" satırı biz eklerken $submenu'de hazırdır.
		add_action( 'admin_menu', 'qrms_module_yorum_feedback_admin_menu', 20 );
	}
}

/**
 * Modülün suite menüsündeki alt satırları: ekranları ekler, sırayı düzeltir.
 *
 * WordPress iki seviyeli bir admin menüsüne sahiptir — "Yorum & Feedback"
 * girişinin ALTINA giriş eklenemez. Bu yüzden modülün yedi ekranı da "QR
 * Menü"nün doğrudan alt öğesidir; modüle ait olduklarını göstermek için
 * etiketleri "—" ile öneklenir ve "Yorum & Feedback" satırının hemen ardına
 * sıralanır. Hepsi add_submenu_page ile kaydedilmiş gerçek, ayrı sayfalardır —
 * JS ile gizlenip gösterilen sekme yoktur.
 *
 * @return void
 */
function qrms_module_yorum_feedback_admin_menu() {
	global $submenu;

	$parent = QRMS_Admin::MENU_SLUG;

	// Modül lisansta aktif değilse "Yorum & Feedback" satırı hiç kaydolmaz;
	// o zaman burada düzenlenecek bir şey de yoktur.
	if ( empty( $submenu[ $parent ] ) ) {
		return;
	}

	foreach ( qrm_pro_admin_pages() as $slug => $page ) {
		add_submenu_page(
			$parent,
			$page['title'],
			'— ' . $page['menu_title'] . qrm_pro_menu_badge( $slug ),
			QRMS_Admin::CAPABILITY,
			$slug,
			$page['render']
		);
	}

	$submenu[ $parent ] = qrms_module_yorum_feedback_submenu_sirala( $submenu[ $parent ] );
}

/**
 * Modülün satırlarını "Yorum & Feedback" girişinin hemen ardına taşır.
 *
 * WordPress'e bağımlılığı yoktur (saf dizi dönüşümü), bu yüzden doğrudan test
 * edilir. Sonuç array_values() ile yeniden indekslenir: WordPress alt menüleri
 * anahtara göre sıraladığından 0..n indeksleme buradaki sırayı korur.
 *
 * Diğer modüllerin (ör. restoran-menu) satırları "others" içinde göreli
 * sıralarını koruyarak geçer; iki modülün sıralama geçişi birbirini bozmaz.
 *
 * @param array $rows $submenu[QRMS_Admin::MENU_SLUG] satırları.
 * @return array Yeniden sıralanmış satırlar.
 */
function qrms_module_yorum_feedback_submenu_sirala( array $rows ) {
	$module_slug = QRMS_Admin::get_module_page_slug( 'yorum-feedback' );
	$child_slugs = array_keys( qrm_pro_admin_pages() );

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

	// "Yorum & Feedback" satırı yoksa taşınacak bir çapa da yok: satırlar
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
 * Modülün suite menüsündeki ekranlarının yönetim varlıkları.
 *
 * Varlıklar yalnızca bu modülün kendi sayfaları render edilirken yüklenir
 * (qr-masa ve restoran-menu modüllerindeki desenin aynısı).
 *
 * Kaynakta bu iş, hook adında "qrm-" arayan bir koşulla yapılıyordu; suite'in
 * sayfa slug'ları o koşulu sağlamadığı için ekranlar stilsiz kalıyordu. Koşul
 * artık sayfa kayıt defterine bakıyor, yani menüye eklenen her sayfa varlıkları
 * da otomatik alır.
 *
 * @return void
 */
function qrms_module_yorum_feedback_admin_assets() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	if ( ! qrm_pro_is_module_page( $page ) ) {
		return;
	}

	wp_enqueue_style(
		'qrm-admin',
		QRMS_PLUGIN_URL . 'modules/yorum-feedback/assets/css/admin.css',
		array(),
		QRMS_VERSION
	);

	// Başlangıç ekranındaki kart ikonları WordPress'in dashicons setinden gelir.
	wp_enqueue_style( 'dashicons' );

	// Renk seçici ve sürükle-bırak yalnızca onlara ihtiyaç duyan ekranlarda.
	if ( in_array( $page, array( 'qrms-yf-ayarlar', 'qrms-yf-odul' ), true ) ) {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script( 'wp-color-picker', "jQuery(function($){ $('.qrm-color-picker').wpColorPicker(); });" );
	}

	// Yapay zekâ özeti yalnızca İçgörüler ekranında.
	if ( 'qrms-yf-icgoruler' === $page ) {
		wp_enqueue_script(
			'qrm-ai-insights',
			QRMS_PLUGIN_URL . 'modules/yorum-feedback/assets/js/ai-insights.js',
			array(),
			QRMS_VERSION,
			true
		);

		wp_add_inline_script(
			'qrm-ai-insights',
			'var QRM_AI = ' . wp_json_encode( array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) ) . ';',
			'before'
		);
	}

	if ( 'qrms-yf-form-alanlari' === $page ) {
		wp_enqueue_script( 'jquery-ui-sortable' );

		// Canlı önizleme. Sıralama koduna bağlanmaz (listeyi MutationObserver
		// ile izler), bu yüzden bağımlılığı yalnızca kendi DOM'u.
		wp_enqueue_script(
			'qrm-form-preview',
			QRMS_PLUGIN_URL . 'modules/yorum-feedback/assets/js/form-preview.js',
			array(),
			QRMS_VERSION,
			true
		);

		// Sürükle-bırak masaüstü için; sıralama telefondan da yapılabilsin diye
		// her satırdaki yukarı/aşağı butonları satırları DOM'da yer değiştirir.
		// Kaydetme sırası DOM sırasından okunduğu için ikisi aynı sonucu verir.
		wp_add_inline_script(
			'jquery-ui-sortable',
			"jQuery(function($){
				var liste = $('#qrm-sortable-fields');
				if (!liste.length) return;

				function tazele(){
					liste.find('.qrm-field-row').each(function(i, satir){
						var ilk  = i === 0;
						var son  = i === liste.find('.qrm-field-row').length - 1;
						$(satir).find('.qrm-field-up').prop('disabled', ilk);
						$(satir).find('.qrm-field-down').prop('disabled', son);
					});
				}

				function degisti(){
					$('#qrm-sort-hint').slideDown(120);
					tazele();
				}

				liste.sortable({
					handle: '.qrm-field-handle',
					distance: 4,
					tolerance: 'pointer',
					update: degisti
				});

				liste.on('click', '.qrm-field-up', function(){
					var satir = $(this).closest('.qrm-field-row');
					var onceki = satir.prev('.qrm-field-row');
					if (!onceki.length) return;
					satir.insertBefore(onceki);
					degisti();
					$(this).trigger('focus');
				});

				liste.on('click', '.qrm-field-down', function(){
					var satir = $(this).closest('.qrm-field-row');
					var sonraki = satir.next('.qrm-field-row');
					if (!sonraki.length) return;
					satir.insertAfter(sonraki);
					degisti();
					$(this).trigger('focus');
				});

				tazele();
			});"
		);
	}
}
