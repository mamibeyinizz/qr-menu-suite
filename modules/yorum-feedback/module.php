<?php
/**
 * Modül: Yorum & Feedback (yorum-feedback)
 *
 * Çoklu kriter puanlamalı müşteri yorumları (`{prefix}qrm_reviews`), Google
 * yorum yönlendirmesi (review gating), ödül/indirim kodu sistemi ve dinamik
 * form oluşturucu. Dosyalar `yorumfeedback` deposundaki "QR Menü Gelişmiş
 * Müşteri Yorumları & Değerlendirme" (v4.2.1) eklentisinden AYNEN taşındı;
 * 29 kaynak dosyanın hepsi kaynağıyla birebir aynıdır. Burada yalnızca yükleme
 * bağlantısı, kurulum tetiği ve suite sayfasının varlık kuyruğu var.
 *
 * Bu modül `_qmo-ortak/ortak.php`'yi YÜKLEMEZ: `qrm_pro_` / `qrm_reward_` /
 * `qrm_cf_` ad alanıyla tamamen kendi kendine yeterlidir. Kaynakta tek bir
 * `qmo_` / `QMO_` referansı yoktur (masa oturumunu, ortak nonce'u ve ortak
 * varlık kayıt defterini kullanmaz); kendi hız sınırı ve cooldown guard'ları
 * vardır. Taşınacak paylaşımlı varlık da yok: kaynağın `assets/` klasörleri
 * boştur, tüm CSS/JS satır içi basılır.
 *
 * Suite ile çakışma taraması temiz: 190 fonksiyon adının, kısa kodların
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
 * qr-menu-reviews.php sabitleri tanımlar ve 28 dosyanın tamamını kaynaktaki
 * sırayla yükler. Bağlanılan kancaların hepsi (`admin_menu`,
 * `admin_enqueue_scripts`, `admin_page_access_denied`, `wp_ajax_*`,
 * `wp_footer`, kısa kodlar) plugins_loaded'dan sonra tetiklenir.
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
		// Eklentinin kendi "QR Yorumlar" üst menüsü ve beş alt sayfası kaynakta
		// olduğu gibi kayıtlı kalır (menü rozetleri ve eski slug yönlendirmeleri
		// dahil); suite menüsündeki "Yorum & Feedback" satırı da aynı Tüm
		// Yorumlar ekranını basar. Ekranı basan fonksiyon tektir.
		QRMS_Admin::register_module_page( 'yorum-feedback', 'qrm_pro_admin_dashboard' );

		add_action( 'admin_enqueue_scripts', 'qrms_module_yorum_feedback_admin_assets' );
	}
}

/**
 * Suite menüsündeki "Yorum & Feedback" ekranının yönetim varlıkları.
 *
 * Kaynaktaki qrm_pro_admin_scripts() varlıkları yalnızca hook adı `qrm-`
 * içeren ekranlarda yükler; bu koşul eklentinin kendi sayfalarında sağlanır
 * (ör. `qr-yorumlar_page_qrm-pro-settings`) ama suite'in sayfa slug'ında
 * (`qrms-module-yorum-feedback` → `qr-menu_page_qrms-module-yorum-feedback`)
 * sağlanmaz. Varlıksız kalan ekranda Tüm Yorumlar/İçgörüler sekmelerinin
 * dayandığı `.qrm-pro-wrap`, `.qrm-card`, `.qrm-insight-grid`, `.qrm-stat-box`
 * stilleri hiç basılmazdı.
 *
 * Bu yüzden varlıklar burada kopyalanmaz: kaynağın kendi fonksiyonu, kendi
 * koşulunu karşılayan bir hook adıyla çağrılır. Böylece satır içi CSS/JS tek
 * bir yerde (kaynakta) kalır ve iki kopya birbirinden ayrışamaz. wp_enqueue_*
 * çağrıları tekrar edilebilir olduğu için kaynağın kendi kancasıyla birlikte
 * çalışması sorun çıkarmaz.
 *
 * Koşul, qr-masa ve restoran-menu modüllerindeki desenin aynısı: varlıklar
 * yalnızca bu modülün kendi suite sayfası render edilirken yüklenir.
 *
 * @return void
 */
function qrms_module_yorum_feedback_admin_assets() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	if ( QRMS_Admin::get_module_page_slug( 'yorum-feedback' ) !== $page ) {
		return;
	}

	qrm_pro_admin_scripts( 'qrm-suite-module-page' );
}
