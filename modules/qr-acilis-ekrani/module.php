<?php
/**
 * Modül: Açılış Ekranı (qr-acilis-ekrani)
 *
 * Ana sayfaya gelen ziyaretçiyi karşılayan tam ekran açılış: logo şeridi,
 * arkaplan görseli, menü butonu, iletişim/rezervasyon/yorum rozetleri, wifi
 * penceresi, sosyal hesaplar ve ödeme yöntemleri. Yönetim ekranı suite
 * menüsüne `QRMS_Admin::register_module_page()` ile bağlanır; ayarlar
 * `splash_screen_options` option'ında tutulur.
 *
 * Kısa kodu yoktur: ekran ana sayfada kendiliğinden çalışır.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modülü başlatır.
 *
 * QRMS_Module_Loader tarafından `plugins_loaded` (öncelik 20) sırasında
 * argümansız çağrılır.
 *
 * @return void
 */
function qrms_module_qr_acilis_ekrani_init() {
	require_once __DIR__ . '/includes/class-acilis-ekrani.php';

	QRMS_Acilis_Ekrani::init();
}
