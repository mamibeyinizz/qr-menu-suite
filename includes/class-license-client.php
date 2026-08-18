<?php
/**
 * Lisans istemcisi: sunucu ile konuşur, sonucu option'lara yazar, günlük
 * senkronizasyonu yönetir. Hiçbir koşulda sert kilitleme yapmaz.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lisans doğrulama ve senkronizasyon istemcisi.
 */
class QRMS_License_Client {

	/**
	 * Option adları.
	 */
	const OPT_API_KEY     = 'qrms_api_key';
	const OPT_SERVER_URL  = 'qrms_server_url';
	const OPT_MODULES     = 'qrms_active_modules';
	const OPT_LAST_SYNC   = 'qrms_last_sync';
	const OPT_LAST_STATUS = 'qrms_last_status';

	/**
	 * Son "active" cevabının zaman damgası (bilgilendirme notice'ı bunu kullanır).
	 */
	const OPT_LAST_ACTIVE_SYNC = 'qrms_last_active_sync';

	/**
	 * Arka plan senkronizasyonu sorunlu bir durum bıraktığında set edilen bayrak.
	 */
	const OPT_NOTICE_FLAG = 'qrms_license_notice';

	/**
	 * Günlük senkronizasyon cron hook'u.
	 */
	const CRON_HOOK = 'qrms_daily_license_sync';

	/**
	 * Varsayılan lisans sunucusu adresi (wizard'da değiştirilebilir).
	 */
	const DEFAULT_SERVER_URL = 'https://full.qrmenuofficial.com';

	/**
	 * Doğrulama endpoint yolu.
	 */
	const ENDPOINT_PATH = '/wp-json/qmls/v1/validate';

	/**
	 * İstek zaman aşımı (saniye).
	 */
	const REQUEST_TIMEOUT = 15;

	/**
	 * Bilgilendirme notice'ının gösterilmesi için geçmesi gereken süre (saniye).
	 */
	const NOTICE_GRACE = 3 * DAY_IN_SECONDS;

	/**
	 * Hook kayıtları.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_daily_sync' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_render_notice' ) );
	}

	/**
	 * Günlük cron'u (henüz zamanlanmamışsa) kaydeder. Aktivasyonda çağrılır.
	 *
	 * @return void
	 */
	public static function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Günlük cron'u temizler. Deaktivasyonda çağrılır.
	 *
	 * @return void
	 */
	public static function unschedule_cron() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Kayıtlı API anahtarı.
	 *
	 * @return string
	 */
	public static function get_api_key() {
		return (string) get_option( self::OPT_API_KEY, '' );
	}

	/**
	 * Kayıtlı sunucu adresi; kayıt yoksa varsayılan adres.
	 *
	 * @return string
	 */
	public static function get_server_url() {
		$url = (string) get_option( self::OPT_SERVER_URL, '' );

		return '' !== $url ? $url : self::DEFAULT_SERVER_URL;
	}

	/**
	 * Aktif modül listesi (temizlenmiş).
	 *
	 * @return string[]
	 */
	public static function get_active_modules() {
		return QRMS_Helpers::sanitize_module_list( get_option( self::OPT_MODULES, array() ) );
	}

	/**
	 * Son bilinen lisans durumu.
	 *
	 * @return string active|inactive|domain_mismatch|invalid|unreachable|'' .
	 */
	public static function get_last_status() {
		return (string) get_option( self::OPT_LAST_STATUS, '' );
	}

	/**
	 * Sunucuya son ulaşıldığı an (herhangi bir geçerli cevap alındığı an).
	 *
	 * @return int Unix zaman damgası, hiç ulaşılmadıysa 0.
	 */
	public static function get_last_sync() {
		return (int) get_option( self::OPT_LAST_SYNC, 0 );
	}

	/**
	 * Lisansı sunucuda doğrular ve sonucu option'lara yazar (senkron istek).
	 *
	 * Fail-safe: qrms_active_modules SADECE "active" cevabında güncellenir.
	 * invalid / inactive / domain_mismatch / unreachable durumlarında mevcut
	 * modül listesine dokunulmaz.
	 *
	 * @param string $api_key    API anahtarı.
	 * @param string $server_url Lisans sunucusu kök adresi.
	 * @return array{status:string,modules:string[],message:string} Doğrulama sonucu.
	 */
	public static function validate( $api_key, $server_url ) {
		$api_key    = trim( (string) $api_key );
		$server_url = self::normalize_server_url( $server_url );
		$domain     = QRMS_Helpers::get_site_domain();

		$response = wp_remote_post(
			$server_url . self::ENDPOINT_PATH,
			array(
				'timeout' => self::REQUEST_TIMEOUT,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'api_key' => $api_key,
						'domain'  => $domain,
					)
				),
			)
		);

		// Sunucuya hiç ulaşılamadı: modüllere ve son bağlantı tarihine dokunma.
		if ( is_wp_error( $response ) ) {
			return self::store_result( 'unreachable', null, $api_key, $server_url, false );
		}

		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$status = ( is_array( $body ) && isset( $body['status'] ) ) ? sanitize_key( (string) $body['status'] ) : '';

		// Beklenmeyen/bozuk cevap da "ulaşılamadı" gibi ele alınır.
		if ( ! in_array( $status, array( 'active', 'inactive', 'domain_mismatch', 'invalid' ), true ) ) {
			return self::store_result( 'unreachable', null, $api_key, $server_url, false );
		}

		$modules = null;

		if ( 'active' === $status ) {
			$modules = QRMS_Helpers::sanitize_module_list( isset( $body['modules'] ) ? $body['modules'] : array() );
		}

		return self::store_result( $status, $modules, $api_key, $server_url, true );
	}

	/**
	 * Günlük cron: kayıtlı key/sunucu ile sessizce yeniden doğrulama yapar.
	 *
	 * @return void
	 */
	public static function run_daily_sync() {
		$api_key = self::get_api_key();

		if ( '' === $api_key ) {
			return; // Henüz kurulum yapılmamış.
		}

		self::validate( $api_key, self::get_server_url() );
	}

	/**
	 * Doğrulama sonucunu option'lara yazar.
	 *
	 * @param string        $status     Durum.
	 * @param string[]|null $modules    Sadece "active" durumunda dolu; aksi halde null (dokunma).
	 * @param string        $api_key    Doğrulamada kullanılan anahtar.
	 * @param string        $server_url Doğrulamada kullanılan sunucu adresi.
	 * @param bool          $reached    Sunucudan geçerli bir cevap alındı mı?
	 * @return array{status:string,modules:string[],message:string}
	 */
	private static function store_result( $status, $modules, $api_key, $server_url, $reached ) {
		$now = time();

		// Kullanıcının girdiği key/sunucu her denemede saklanır ki cron aynı
		// bilgilerle devam edebilsin.
		if ( '' !== $api_key ) {
			update_option( self::OPT_API_KEY, $api_key );
		}

		update_option( self::OPT_SERVER_URL, $server_url );
		update_option( self::OPT_LAST_STATUS, $status );

		if ( $reached ) {
			update_option( self::OPT_LAST_SYNC, $now );
		}

		// Fail-safe: modül listesi yalnızca "active" cevabında değişir.
		if ( 'active' === $status && is_array( $modules ) ) {
			update_option( self::OPT_MODULES, $modules );
			update_option( self::OPT_LAST_ACTIVE_SYNC, $now );
			delete_option( self::OPT_NOTICE_FLAG );
		} else {
			update_option( self::OPT_NOTICE_FLAG, $status );
		}

		return array(
			'status'  => $status,
			'modules' => self::get_active_modules(),
			'message' => QRMS_Helpers::get_status_message( $status ),
		);
	}

	/**
	 * Sunucu adresini normalize eder (boşsa varsayılan, sondaki eğik çizgi atılır).
	 *
	 * @param string $server_url Ham adres.
	 * @return string
	 */
	public static function normalize_server_url( $server_url ) {
		$server_url = trim( (string) $server_url );

		if ( '' === $server_url ) {
			$server_url = self::DEFAULT_SERVER_URL;
		}

		if ( ! preg_match( '#^https?://#i', $server_url ) ) {
			$server_url = 'https://' . ltrim( $server_url, '/' );
		}

		$server_url = esc_url_raw( $server_url );

		return untrailingslashit( $server_url );
	}

	/**
	 * Bilgilendirme notice'ı gösterilmeli mi?
	 *
	 * Koşul: son durum sorunlu (unreachable/invalid/inactive/domain_mismatch) VE
	 * sağlıklı son senkronizasyondan bu yana 3 günden fazla geçmiş.
	 *
	 * @return bool
	 */
	public static function should_show_notice() {
		$status = self::get_last_status();

		if ( ! in_array( $status, array( 'unreachable', 'invalid', 'inactive', 'domain_mismatch' ), true ) ) {
			return false;
		}

		$reference = (int) get_option( self::OPT_LAST_ACTIVE_SYNC, 0 );

		if ( $reference <= 0 ) {
			$reference = self::get_last_sync();
		}

		if ( $reference <= 0 ) {
			return false; // Hiç başarılı bağlantı olmamış (kurulum yapılmamış).
		}

		return ( time() - $reference ) > self::NOTICE_GRACE;
	}

	/**
	 * Sadece bu plugin'in ekranlarında, engellemeyen bilgilendirme notice'ı.
	 *
	 * @return void
	 */
	public static function maybe_render_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! QRMS_Admin::is_plugin_screen() ) {
			return;
		}

		if ( ! self::should_show_notice() ) {
			return;
		}

		$date = QRMS_Helpers::format_datetime( self::get_last_sync() );

		printf(
			'<div class="notice notice-warning is-dismissible qrms-notice"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: tarih, 2: lisans durumu. */
					__( 'Lisans sunucusuyla son bağlantı %1$s tarihinde kuruldu, durum: %2$s. Mevcut modülleriniz etkilenmedi.', 'qrms' ),
					$date,
					QRMS_Helpers::get_status_label( self::get_last_status() )
				)
			)
		);
	}
}

/**
 * Lisansı doğrular (senkron). Wizard ve günlük cron bu fonksiyonu kullanır.
 *
 * @param string $api_key    API anahtarı.
 * @param string $server_url Lisans sunucusu kök adresi.
 * @return array{status:string,modules:string[],message:string}
 */
function qrms_validate_license( $api_key, $server_url ) {
	return QRMS_License_Client::validate( $api_key, $server_url );
}
