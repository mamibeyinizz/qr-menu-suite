<?php
/**
 * Modül: QR Masa Oturum Güvenliği (qr-masa-oturum-guvenligi)
 *
 * HMAC imzalı masa oturumu, masa doğrulama / kilit ekranı ve oturum
 * limitlerinin yönetim sayfası. Dosyalar eski qr-menu-official
 * eklentisinden aynen taşındı; burada yalnızca yükleme bağlantısı var.
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
 * dosyanın yüklenmesi = hook'ların kaydı. Bağlanılan kancaların hepsi
 * (`init`, `template_redirect`, `admin_init`, `admin_menu`) plugins_loaded'dan
 * sonra tetiklenir.
 *
 * @return void
 */
function qrms_module_qr_masa_oturum_guvenligi_init() {
	require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/ortak.php';

	// QMO_Oturum sınıfı _qmo-ortak altındadır (ortak.php ile yüklenir):
	// masa oturumu üç modülün de ortak zeminidir. Bu modül oturumun
	// ZORLANMASINDAN sorumludur — kilit ekranı, sahte QR reddi, sayfa kilidi.
	require_once __DIR__ . '/masa-dogrulama.php';

	if ( is_admin() ) {
		require_once __DIR__ . '/oturum-ayarlari.php';

		QRMS_Admin::register_module_page( 'qr-masa-oturum-guvenligi', 'qmo_oturum_ayar_sayfasi' );
	}
}
