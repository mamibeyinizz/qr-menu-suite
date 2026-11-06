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
	 * Sunucu lisansı geçersiz saydığı ilk anın zaman damgası ("active" gelince silinir).
	 */
	const OPT_REVOKE_SINCE = 'qrms_revoke_since';

	/**
	 * Lisans geçersiz sayıldıktan sonra modüllerin açık kalacağı süre (saniye).
	 */
	const REVOKE_GRACE = DAY_IN_SECONDS;

	/**
	 * Sunucunun lisansı geçersiz saydığı durumlar. "unreachable" bunlara dahil
	 * DEĞİL: sunucuya ulaşılamaması modül kapatmaz.
	 */
	const REVOKING_STATUSES = array( 'inactive', 'invalid', 'domain_mismatch', 'expired' );

	/**
	 * Günlük senkronizasyon cron hook'u.
	 */
	const CRON_HOOK = 'qrms_daily_license_sync';

	/**
	 * Varsayılan lisans sunucusu adresi (wizard'da değiştirilebilir).
	 */
	const DEFAULT_SERVER_URL = 'https://qrmenuofficial.com';

	/**
	 * Lisans sunucusunun Ed25519 açık anahtarı (base64). Sunucu cevapları bu
	 * anahtarla doğrulanır; imzasız/bozuk cevap "unreachable" sayılır.
	 * Test/özel kurulum için QRMS_LICENSE_PUBKEY sabitiyle geçersiz kılınabilir.
	 */
	const PUBLIC_KEY = 'HVJpYbCZfwMXCOL6+yDPPz1Pjk+Nn71zRG31fg0Y8i4=';

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
		// Aktivasyon çalışmadan kurulan/güncellenen sitelerde cron'u kendini onarır.
		add_action( 'admin_init', array( __CLASS__, 'schedule_cron' ) );
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
		// Geçersiz lisans + 24 saatlik süre dolduysa modüller kendiliğinden kapanır.
		// Kontrol okuma anında yapılır (cron beklenmez); saklanan liste silinmez.
		if ( self::is_lapsed() ) {
			return array();
		}

		return QRMS_Helpers::sanitize_module_list( get_option( self::OPT_MODULES, array() ) );
	}

	/**
	 * Sunucunun lisansı ilk geçersiz saydığı an.
	 *
	 * @return int Unix zaman damgası, geçerli/belirsiz durumda 0.
	 */
	public static function get_revoke_since() {
		return (int) get_option( self::OPT_REVOKE_SINCE, 0 );
	}

	/**
	 * Modüllerin kapanacağı an (geçersiz sayılma + 24 saat).
	 *
	 * @return int Unix zaman damgası, süre işlemiyorsa 0.
	 */
	public static function get_revoke_deadline() {
		$since = self::get_revoke_since();

		return $since > 0 ? $since + self::REVOKE_GRACE : 0;
	}

	/**
	 * Lisans geçersiz sayıldı ve 24 saatlik süre doldu mu?
	 *
	 * @return bool
	 */
	public static function is_lapsed() {
		$deadline = self::get_revoke_deadline();

		return $deadline > 0 && time() >= $deadline;
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
	 * qrms_active_modules SADECE "active" cevabında güncellenir.
	 * invalid / inactive / domain_mismatch / expired durumlarında liste
	 * 24 saat boyunca korunur, sonra get_active_modules() boş döner.
	 * unreachable durumunda hiçbir şey değişmez (sunucu hatası kapatmaz).
	 *
	 * @param string $api_key    API anahtarı.
	 * @param string $server_url Lisans sunucusu kök adresi.
	 * @return array{status:string,modules:string[],message:string} Doğrulama sonucu.
	 */
	public static function validate( $api_key, $server_url ) {
		$api_key    = trim( (string) $api_key );
		$server_url = self::normalize_server_url( $server_url );
		$domain     = QRMS_Helpers::get_site_domain();

		// Doğrulama günlük cron'dan da çağrılır ve WP cron bir ZİYARETÇİNİN
		// isteği üzerinde çalışır: 15 saniyelik bu istek boyunca o ziyaretçinin
		// veritabanı bağlantısı hiç kullanılmadan açık kalıyordu. Sonuç
		// option'lara yazılana kadar veritabanına ihtiyaç yok, bağlantı bırakılır.
		$db_kapali = self::db_serbest_birak();

		// wp_safe_remote_post: hedef, yönetici tarafından değiştirilebilen bir
		// option'dan (qrms_server_url) geliyor; güvenli varyant özel/yerel IP
		// aralıklarına isteği reddederek bu ayarın SSRF'e dönüşmesini engeller.
		$response = wp_safe_remote_post(
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
						'version' => defined( 'QRMS_VERSION' ) ? QRMS_VERSION : '',
					)
				),
			)
		);

		// store_result() option yazar; bağlantı öncesinde geri açılmalı.
		self::db_geri_baglan( $db_kapali );

		// Sunucuya hiç ulaşılamadı: modüllere ve son bağlantı tarihine dokunma.
		if ( is_wp_error( $response ) ) {
			return self::store_result( 'unreachable', null, $api_key, $server_url, false );
		}

		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$status = ( is_array( $body ) && isset( $body['status'] ) ) ? sanitize_key( (string) $body['status'] ) : '';

		// İmzasız, bozuk imzalı ya da başka alan adı için üretilmiş cevap
		// (sahte sunucu / tekrar oynatma) "ulaşılamadı" gibi ele alınır: modülleri
		// ne açar ne kapatır.
		if ( ! self::verify_signature( $body, $domain ) ) {
			return self::store_result( 'unreachable', null, $api_key, $server_url, false );
		}

		// Beklenmeyen/bozuk cevap da "ulaşılamadı" gibi ele alınır.
		if ( ! in_array( $status, array( 'active', 'inactive', 'domain_mismatch', 'invalid', 'expired' ), true ) ) {
			return self::store_result( 'unreachable', null, $api_key, $server_url, false );
		}

		$modules = null;

		if ( 'active' === $status ) {
			$modules = QRMS_Helpers::sanitize_module_list( isset( $body['modules'] ) ? $body['modules'] : array() );
		}

		return self::store_result( $status, $modules, $api_key, $server_url, true );
	}

	/**
	 * İmzalanan mesaj: sürüm, durum, alan adı ve modül listesi.
	 *
	 * @param string   $status  Durum.
	 * @param string   $domain  Site alan adı.
	 * @param string[] $modules Sunucunun gönderdiği modül listesi (olduğu gibi).
	 * @return string
	 */
	public static function signed_message( $status, $domain, array $modules ) {
		return "qrms1\n" . $status . "\n" . $domain . "\n" . implode( ',', $modules );
	}

	/**
	 * Sunucu cevabının Ed25519 imzasını doğrular.
	 *
	 * @param mixed  $body   Çözülmüş JSON gövdesi.
	 * @param string $domain Bu sitenin alan adı (cevap bu alan adına bağlı olmalı).
	 * @return bool
	 */
	private static function verify_signature( $body, $domain ) {
		if ( ! is_array( $body ) || empty( $body['sig'] ) || ! is_string( $body['sig'] )
			|| ! isset( $body['status'], $body['domain'] ) || ! is_string( $body['domain'] )
			|| ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			return false;
		}

		if ( strtolower( $body['domain'] ) !== $domain ) {
			return false;
		}

		$modules = isset( $body['modules'] ) && is_array( $body['modules'] ) ? $body['modules'] : array();

		foreach ( $modules as $slug ) {
			if ( ! is_string( $slug ) ) {
				return false;
			}
		}

		$sig = base64_decode( $body['sig'], true );
		$key = base64_decode( defined( 'QRMS_LICENSE_PUBKEY' ) ? QRMS_LICENSE_PUBKEY : self::PUBLIC_KEY, true );

		if ( false === $sig || false === $key
			|| SODIUM_CRYPTO_SIGN_BYTES !== strlen( $sig ) || SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== strlen( $key ) ) {
			return false;
		}

		return sodium_crypto_sign_verify_detached(
			$sig,
			self::signed_message( (string) $body['status'], $domain, $modules ),
			$key
		);
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
	 * Veritabanı bağlantısını lisans isteği boyunca serbest bırakır.
	 *
	 * Modüllerdeki qmo_db_serbest_birak()'ın çekirdek karşılığı; çekirdek
	 * modüllere bağımlı olmadığı için tekrarlanır.
	 *
	 * DİKKAT: wpdb::close() sonrası bağlantı kendiliğinden geri gelmez —
	 * `ready` bayrağı düşer ve sonraki sorgular sessizce false döner. Geri
	 * açmak db_geri_baglan()'ın işidir.
	 *
	 * @return bool Bağlantı gerçekten kapatıldıysa true.
	 */
	private static function db_serbest_birak() {
		global $wpdb;

		/**
		 * Lisans isteği boyunca veritabanı bağlantısı bırakılsın mı?
		 *
		 * Kalıcı bağlantı kullanan kurulumlarda (ör. HyperDB)
		 * `add_filter( 'qrms_db_baglanti_serbest', '__return_false' )`.
		 *
		 * @param bool $serbest Varsayılan true.
		 */
		if ( ! apply_filters( 'qrms_db_baglanti_serbest', true ) ) {
			return false;
		}

		if ( ! isset( $wpdb ) || ! is_object( $wpdb )
			|| ! method_exists( $wpdb, 'close' ) || ! method_exists( $wpdb, 'db_connect' ) ) {
			return false;
		}

		return (bool) $wpdb->close();
	}

	/**
	 * db_serbest_birak() ile kapatılan bağlantıyı geri açar.
	 *
	 * @param bool $kapatildi db_serbest_birak() çıktısı.
	 * @return void
	 */
	private static function db_geri_baglan( $kapatildi ) {
		global $wpdb;

		if ( ! $kapatildi ) {
			return;
		}

		if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'db_connect' ) ) {
			$wpdb->db_connect();
		}
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

		// Anahtar/sunucu yalnızca doğrulanınca (veya hiç kayıt yokken) saklanır;
		// aksi halde yanlış bir deneme çalışan anahtarı ezer.
		if ( 'active' === $status || '' === self::get_api_key() ) {
			if ( '' !== $api_key ) {
				update_option( self::OPT_API_KEY, $api_key );
			}

			update_option( self::OPT_SERVER_URL, $server_url );
		}
		update_option( self::OPT_LAST_STATUS, $status );

		if ( $reached ) {
			update_option( self::OPT_LAST_SYNC, $now );
		}

		// Fail-safe: modül listesi yalnızca "active" cevabında değişir.
		if ( 'active' === $status && is_array( $modules ) ) {
			update_option( self::OPT_MODULES, $modules );
			update_option( self::OPT_LAST_ACTIVE_SYNC, $now );
			delete_option( self::OPT_NOTICE_FLAG );
			delete_option( self::OPT_REVOKE_SINCE );
		} else {
			update_option( self::OPT_NOTICE_FLAG, $status );

			// 24 saatlik süre ilk geçersiz cevapta başlar; tekrarlayan cevaplar sıfırlamaz.
			if ( in_array( $status, self::REVOKING_STATUSES, true ) && self::get_revoke_since() <= 0 ) {
				update_option( self::OPT_REVOKE_SINCE, $now );
			}
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

		// http:// düz metin kabul edilirse API anahtarı ağ üzerinde açık gider
		// ve yanıt (aktif modül listesi) yolda değiştirilebilir; şema burada
		// https'e zorlanır.
		$server_url = preg_replace( '#^http://#i', 'https://', $server_url );

		$server_url = esc_url_raw( $server_url );

		return untrailingslashit( $server_url );
	}

	/**
	 * Bilgilendirme notice'ı gösterilmeli mi?
	 *
	 * Koşul: son durum sorunlu (unreachable/invalid/inactive/domain_mismatch/expired) VE
	 * sağlıklı son senkronizasyondan bu yana 3 günden fazla geçmiş.
	 *
	 * @return bool
	 */
	public static function should_show_notice() {
		$status = self::get_last_status();

		if ( ! in_array( $status, array( 'unreachable', 'invalid', 'inactive', 'domain_mismatch', 'expired' ), true ) ) {
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

		if ( self::get_revoke_since() <= 0 && ! self::should_show_notice() ) {
			return;
		}

		if ( self::get_revoke_since() > 0 ) {
			$label = QRMS_Helpers::get_status_label( self::get_last_status() );

			if ( self::is_lapsed() ) {
				/* translators: %s: lisans durumu. */
				$text = sprintf( __( 'Lisansınız geçersiz (%s) ve 24 saatlik süre doldu; modülleriniz kapatıldı. Lisansı Genel Ayarlar\'tan yeniden doğrulayın.', 'qrms' ), $label );
			} else {
				/* translators: 1: lisans durumu, 2: kapanma zamanı. */
				$text = sprintf( __( 'Lisansınız geçersiz (%1$s). Modülleriniz %2$s tarihinde kapanacak. Lisansı Genel Ayarlar\'tan yeniden doğrulayın.', 'qrms' ), $label, QRMS_Helpers::format_datetime( self::get_revoke_deadline() ) );
			}

			printf( '<div class="notice notice-error qrms-notice"><p>%s</p></div>', esc_html( $text ) );

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
