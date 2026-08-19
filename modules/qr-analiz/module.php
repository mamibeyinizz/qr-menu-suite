<?php
/**
 * Modül: QR Analiz (qr-analiz)
 *
 * `POST /wp-json/qrservis/v1/analytics` ucu: Firebase ID token ile kimliği
 * doğrulanan admin/müdür kullanıcılara, seçilen tarih aralığı için
 * `{prefix}rma_analytics` tablosundan menü görüntülenme / ürün tıklama /
 * masa trafiği özeti döner. Dosya eski qr-menu-official eklentisinden aynen
 * taşındı; burada yalnızca yükleme bağlantısı var.
 *
 * Not: Analitik ayar sayfası (Firebase service account JSON'ı ve şube
 * kimliği) bu göçte taşınmadı — eski eklentide `admin/settings-page.php`
 * içindeydi — bu yüzden modülün admin menüsündeki sayfası şimdilik
 * placeholder olarak kalır. Yapılandırma eskisi gibi okunmaya devam eder:
 * wp-config'teki `QMO_FIREBASE_SA_JSON` sabiti, `qmo_firebase_sa` ve
 * `qmo_branch_id` option'ları.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modülü başlatır.
 *
 * QRMS_Module_Loader tarafından `plugins_loaded` (öncelik 20) sırasında
 * argümansız çağrılır. Taşınan dosya hook'unu dosya kapsamında kaydettiği
 * için require bilinçli olarak bu fonksiyonun içindedir: dosyanın yüklenmesi
 * = hook'un kaydı. Bağlanılan `rest_api_init` kancası plugins_loaded'dan
 * sonra tetiklenir.
 *
 * @return void
 */
function qrms_module_qr_analiz_init() {
	// QMO_Firestore sınıfı _qmo-ortak altındadır (ortak.php ile yüklenir):
	// REST ucu çağıranın kimliğini ve rolünü onun üzerinden doğrular.
	require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/ortak.php';

	require_once __DIR__ . '/rest-analytics.php';
}
