<?php
/**
 * Plugin Name:       QR Menu Suite
 * Plugin URI:        https://qrmenuofficial.com
 * Description:       Restoranlar için modüler QR menü sistemi. Modüller lisans sunucusundan gelen listeye göre etkinleşir.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            QR Menu Official
 * Text Domain:       qrms
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

define( 'QRMS_VERSION', '1.1.0' );
define( 'QRMS_PLUGIN_FILE', __FILE__ );
define( 'QRMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'QRMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once QRMS_PLUGIN_DIR . 'includes/class-helpers.php';
require_once QRMS_PLUGIN_DIR . 'includes/class-license-client.php';
require_once QRMS_PLUGIN_DIR . 'includes/class-module-loader.php';
require_once QRMS_PLUGIN_DIR . 'includes/class-wizard.php';
require_once QRMS_PLUGIN_DIR . 'includes/class-shortcodes.php';
require_once QRMS_PLUGIN_DIR . 'includes/class-admin.php';
require_once QRMS_PLUGIN_DIR . 'includes/class-query-monitor.php';
require_once QRMS_PLUGIN_DIR . 'includes/class-qrms-login.php';

/**
 * Plugin bileşenlerinin hook'larını kaydeder.
 *
 * @return void
 */
function qrms_bootstrap() {
	QRMS_License_Client::init();
	QRMS_Module_Loader::init();
	QRMS_Wizard::init();
	QRMS_Admin::init();
	QRMS_Login::init();

	// Yavaş sorgu teşhisi. WP_DEBUG + SAVEQUERIES açık değilse kendisi
	// hiçbir kanca kaydetmez; üretimde tamamen sessizdir.
	QRMS_Query_Monitor::init();
}
qrms_bootstrap();

/**
 * Çeviri dosyalarını yükler.
 *
 * @return void
 */
function qrms_load_textdomain() {
	load_plugin_textdomain( 'qrms', false, dirname( plugin_basename( QRMS_PLUGIN_FILE ) ) . '/languages' );
}
add_action( 'init', 'qrms_load_textdomain' );

/**
 * Aktivasyon: günlük lisans cron'unu kur, kurulum yapılmamışsa sihirbaza
 * tek seferlik yönlendirme bayrağı bırak.
 *
 * @return void
 */
function qrms_activate() {
	QRMS_License_Client::schedule_cron();
	QRMS_Wizard::maybe_flag_activation_redirect();
}
register_activation_hook( __FILE__, 'qrms_activate' );

/**
 * Deaktivasyon: zamanlanmış görevleri temizle.
 *
 * Lisans option'ları (anahtar, modüller, durum) ve toplanmış analitik
 * kayıtları KORUNUR; yalnızca cron kayıtları kaldırılır.
 *
 * @return void
 */
function qrms_deactivate() {
	QRMS_License_Client::unschedule_cron();

	// Analitik saklama görevi modül sınıfında tanımlıdır; modül lisansta
	// kapalıysa sınıf hiç yüklenmemiş olabilir, o yüzden kanca adı doğrudan
	// temizlenir (sınıfı yalnızca bunun için yüklemeye değmez).
	wp_clear_scheduled_hook( 'qrms_analitik_temizlik' );
}
register_deactivation_hook( __FILE__, 'qrms_deactivate' );
