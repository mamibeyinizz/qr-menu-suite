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
 * ikinci kez basıyordu. Artık tek giriş noktası var: sol menüde yalnızca
 * "Yorum & Feedback" satırı durur ve yedi ekranı kart olarak listeleyen hub
 * ekranını açar — restoran-menu modülündeki desenin aynısı. Ekranların
 * kendisi gizli ama gerçek sayfalar olarak kayıtlıdır; eski adreslerden
 * gelenler qrm_pro_legacy_page_target() ile yönlendirilir.
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

	QRMS_Shortcodes::register(
		'yorum-feedback',
		array(
			array(
				'tag'   => 'qr_menu_reviews',
				'title' => __( 'Yorum Formu ve Listesi', 'qrms' ),
				'desc'  => __( 'Müşterilerin puan verip yorum bıraktığı form ile onaylanmış yorumların listesini birlikte gösterir.', 'qrms' ),
			),
			array(
				'tag'   => 'qr_menu_contact',
				'title' => __( 'İletişim Formu', 'qrms' ),
				'desc'  => __( 'Yalnızca iletişim formu — puanlama ve yorum listesi olmadan. İletişim sayfanız için.', 'qrms' ),
			),
			array(
				'tag'   => 'qr_menu_form',
				'title' => __( 'Özel Form', 'qrms' ),
				'desc'  => __( 'Formlar ekranında oluşturduğunuz kendi formlarınızdan birini (şikayet, rezervasyon, anket…) sayfaya yerleştirir.', 'qrms' ),
				'usage' => '[qr_menu_form key="rezervasyon"]',
				'attrs' => array(
					array(
						'name'    => 'key',
						'default' => '',
						'desc'    => __( 'Formun anahtarı — Formlar ekranından öğrenin. Zorunludur.', 'qrms' ),
					),
				),
			),
		)
	);

	if ( is_admin() ) {
		// Suite menüsündeki "Yorum & Feedback" satırı, modülün yedi ekranını
		// listeleyen başlangıç ekranını açar; ekranların kendisi aşağıda ayrı
		// ayrı sayfa olarak kaydedilir.
		QRMS_Admin::register_module_page( 'yorum-feedback', 'qrm_pro_admin_hub' );

		add_action( 'admin_enqueue_scripts', 'qrms_module_yorum_feedback_admin_assets' );

		// Öncelik 20: QRMS_Admin::register_menu() öncelik 10'da çalışır, yani
		// "Yorum & Feedback" satırı biz eklerken $submenu'de hazırdır.
		add_action( 'admin_menu', 'qrms_module_yorum_feedback_admin_menu', 20 );

		add_filter( 'qrms_module_menu_label', 'qrms_module_yorum_feedback_menu_label', 10, 2 );
	}
}

/**
 * Modülün yedi ekranını kaydeder — hepsi sol menüde GİZLİDİR.
 *
 * Sol admin menüsü tek seviyeye indirildi: orada yalnızca "Yorum & Feedback"
 * satırı durur, o satır da yedi ekranı kart olarak listeleyen hub ekranını
 * (qrm_pro_admin_hub) açar. Ekranlar gerçek, ayrı WordPress sayfaları olarak
 * kaydolmaya devam eder — adresleri, hook adları ve yetkileri değişmez;
 * yalnızca menüde boyanmazlar (bkz. QRMS_Admin::hide_module_subpages).
 *
 * @return void
 */
function qrms_module_yorum_feedback_admin_menu() {
	global $submenu;

	$parent = QRMS_Admin::MENU_SLUG;

	// Modül lisansta aktif değilse "Yorum & Feedback" satırı hiç kaydolmaz;
	// o zaman ekranlarının da kaydedilmemesi gerekir.
	if ( empty( $submenu[ $parent ] ) ) {
		return;
	}

	foreach ( qrm_pro_admin_pages() as $slug => $page ) {
		add_submenu_page(
			$parent,
			$page['title'],
			$page['menu_title'],
			QRMS_Admin::CAPABILITY,
			$slug,
			QRMS_Admin::register_module_subpage( 'yorum-feedback', $slug, $page['render'] )
		);
	}
}

/**
 * Modülün sol menüdeki satırına rozet ekler.
 *
 * Alt satırlar kaldırıldığı için okunmamış form gönderimi ya da eksik ödül
 * kurulumu artık yalnızca modülün kendi satırında görünebilir; rozetlerin
 * ayrıntısı hub ekranındaki kartlarda durur.
 *
 * @param string $label Modülün görünen adı.
 * @param string $slug  Modül slug'ı.
 * @return string
 */
function qrms_module_yorum_feedback_menu_label( $label, $slug ) {
	return 'yorum-feedback' === $slug ? $label . qrm_pro_menu_badge() : $label;
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

	// Hub ekranının stili suite'in ortak admin.css'inden gelir; modülün kendi
	// varlıklarına yalnızca yedi yönetim ekranının ihtiyacı var.
	if ( ! array_key_exists( $page, qrm_pro_admin_pages() ) ) {
		return;
	}

	wp_enqueue_style(
		'qrm-admin',
		QRMS_PLUGIN_URL . 'modules/yorum-feedback/assets/css/admin.css',
		array(),
		QRMS_VERSION
	);

	// Renk seçici ve sürükle-bırak yalnızca onlara ihtiyaç duyan ekranlarda.
	if ( in_array( $page, array( 'qrms-yf-ayarlar', 'qrms-yf-odul' ), true ) ) {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script( 'wp-color-picker', "jQuery(function($){ $('.qrm-color-picker').wpColorPicker(); });" );
	}

	if ( 'qrms-yf-form-alanlari' === $page ) {
		wp_enqueue_script( 'jquery-ui-sortable' );

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
