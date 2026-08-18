<?php
/**
 * Modül yükleyici iskeleti: aktif modüllerin dosyalarını arar ve init eder.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * modules/[slug]/module.php dosyalarını yükleyen basit loader.
 *
 * Bu aşamada modül dosyaları henüz yoktur; loader dosya yoksa sessizce atlar.
 */
class QRMS_Module_Loader {

	/**
	 * Bu istekte gerçekten yüklenen modül slug'ları.
	 *
	 * @var string[]
	 */
	private static $loaded = array();

	/**
	 * Hook kayıtları.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'load_modules' ), 20 );
	}

	/**
	 * Aktif modül listesi (option'dan, temizlenmiş).
	 *
	 * @return string[]
	 */
	public static function get_active_modules() {
		return QRMS_License_Client::get_active_modules();
	}

	/**
	 * Modül aktif mi?
	 *
	 * @param string $slug Modül slug'ı.
	 * @return bool
	 */
	public static function is_module_active( $slug ) {
		return in_array( $slug, self::get_active_modules(), true );
	}

	/**
	 * Modülün dosya yolu.
	 *
	 * @param string $slug Modül slug'ı.
	 * @return string Mutlak dosya yolu.
	 */
	public static function get_module_file( $slug ) {
		return QRMS_PLUGIN_DIR . 'modules/' . $slug . '/module.php';
	}

	/**
	 * Modül dosyası diskte var mı? (Aktiflikten bağımsız.)
	 *
	 * @param string $slug Modül slug'ı.
	 * @return bool
	 */
	public static function module_file_exists( $slug ) {
		return QRMS_Helpers::is_valid_module( $slug ) && file_exists( self::get_module_file( $slug ) );
	}

	/**
	 * Slug'dan beklenen init fonksiyon adını türetir.
	 *
	 * Örnek: "restoran-menu" => "qrms_module_restoran_menu_init".
	 *
	 * @param string $slug Modül slug'ı.
	 * @return string
	 */
	public static function get_init_function( $slug ) {
		return 'qrms_module_' . str_replace( '-', '_', $slug ) . '_init';
	}

	/**
	 * Aktif modülleri yükler. Dosya yoksa veya modül aktif değilse sessizce atlar.
	 *
	 * @return string[] Yüklenen modül slug'ları.
	 */
	public static function load_modules() {
		self::$loaded = array();

		foreach ( self::get_active_modules() as $slug ) {
			$file = self::get_module_file( $slug );

			if ( ! file_exists( $file ) ) {
				continue; // Modül henüz paketlenmemiş: sessizce atla.
			}

			require_once $file;

			$callback = self::get_init_function( $slug );

			if ( function_exists( $callback ) ) {
				call_user_func( $callback );
			}

			self::$loaded[] = $slug;
		}

		return self::$loaded;
	}

	/**
	 * Bu istekte yüklenmiş modüller.
	 *
	 * @return string[]
	 */
	public static function get_loaded_modules() {
		return self::$loaded;
	}
}
