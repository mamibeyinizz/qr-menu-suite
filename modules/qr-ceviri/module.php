<?php
/**
 * Modül: QR Çeviri (qr-ceviri)
 *
 * CSV ile toplu yönetilen, veritabanı tabanlı çoklu dil çeviri sistemi.
 * Dosyalar qr-ceviri deposundaki çeviri eklentisinden aynen taşındı; burada
 * yalnızca yükleme bağlantısı ve suite menüsündeki sayfa kaydı var.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modülü başlatır.
 *
 * QRMS_Module_Loader tarafından `plugins_loaded` (öncelik 20) sırasında
 * argümansız çağrılır. Taşınan dosyalar hook'larını dosya kapsamında
 * kaydettiği için require bilinçli olarak bu fonksiyonun içindedir.
 *
 * @return void
 */
function qrms_module_qr_ceviri_init() {
	require_once __DIR__ . '/ceviri.php';

	if ( is_admin() ) {
		QRMS_Admin::register_module_page( 'qr-ceviri', 'qrmenu_trans_page' );
	}
}
