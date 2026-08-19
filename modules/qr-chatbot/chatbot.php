<?php
/**
 * QR Chatbot modülü — ön yüz kısa kodları, AJAX uçları ve yönetim sayfası.
 *
 * Dosyalar eski qr-menu-official eklentisindeki includes/ yapısından taşındı;
 * burada yalnızca sabitler ve require zinciri var.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'QMO_CHATBOT_DIR' ) ) {
	define( 'QMO_CHATBOT_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'QMO_CHATBOT_URL' ) ) {
	define( 'QMO_CHATBOT_URL', plugin_dir_url( __FILE__ ) );
}

require_once QMO_CHATBOT_DIR . 'includes/ajax-chat.php';
require_once QMO_CHATBOT_DIR . 'includes/shortcode-chatbot.php';

if ( is_admin() ) {
	require_once QMO_CHATBOT_DIR . 'includes/admin/admin-sayfa.php';
}
