<?php
/**
 * WordPress çekirdeğinin, testlerin ihtiyaç duyduğu fonksiyonlarının stub'ları.
 *
 * Gerçek bir WordPress kurulumu olmadan plugin mantığını (lisans dallarını,
 * modül yükleyiciyi, admin menüsünü) çalıştırabilmek için kullanılır.
 *
 * @package QR_Menu_Suite
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'QRMS_VERSION', 'test' );
define( 'QRMS_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'QRMS_PLUGIN_URL', 'https://example.test/wp-content/plugins/qr-menu-suite/' );

$GLOBALS['menu']    = array();
$GLOBALS['submenu'] = array();

$GLOBALS['qrms_test'] = array(
	'options'    => array(),
	'transients' => array(),
	'actions'    => array(),
	'cron'       => array(),
	'menus'      => array(),
	'submenus'   => array(),
	'removed'    => array(),
	'redirects'  => array(),
	'http'       => null, // wp_remote_post'un döndüreceği değer (veya callable).
	'http_calls' => array(),
	'can'        => true,
	'styles'     => array(),
	'shortcodes' => array(),
	'json'       => null,
);

/** Basit WP_Error taklidi. */
class WP_Error {
	/**
	 * Hata kodu.
	 *
	 * @var string
	 */
	public $code;

	/**
	 * Hata mesajı.
	 *
	 * @var string
	 */
	public $message;

	/**
	 * Kurucu.
	 *
	 * @param string $code    Kod.
	 * @param string $message Mesaj.
	 */
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	/**
	 * Hata kodu (çekirdekteki erişimcinin karşılığı).
	 *
	 * @return string
	 */
	public function get_error_code() {
		return $this->code;
	}

	/**
	 * Hata mesajı (çekirdekteki erişimcinin karşılığı).
	 *
	 * @return string
	 */
	public function get_error_message() {
		return $this->message;
	}
}

/**
 * WP_Error mi?
 *
 * @param mixed $thing Değer.
 * @return bool
 */
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/**
 * Option okur.
 *
 * @param string $name    Option adı.
 * @param mixed  $default Varsayılan.
 * @return mixed
 */
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['qrms_test']['options'] )
		? $GLOBALS['qrms_test']['options'][ $name ]
		: $default;
}

/**
 * Option yazar.
 *
 * @param string    $name     Option adı.
 * @param mixed     $value    Değer.
 * @param bool|null $autoload Autoload (WP 6.4+; stub yok sayar).
 * @return bool
 */
function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['qrms_test']['options'][ $name ] = $value;

	return true;
}

/**
 * Option siler.
 *
 * @param string $name Option adı.
 * @return bool
 */
function delete_option( $name ) {
	unset( $GLOBALS['qrms_test']['options'][ $name ] );

	return true;
}

/**
 * Transient yazar.
 *
 * @param string $name       Ad.
 * @param mixed  $value      Değer.
 * @param int    $expiration Süre.
 * @return bool
 */
function set_transient( $name, $value, $expiration = 0 ) {
	$GLOBALS['qrms_test']['transients'][ $name ] = $value;

	return true;
}

/**
 * Transient okur.
 *
 * @param string $name Ad.
 * @return mixed
 */
function get_transient( $name ) {
	return isset( $GLOBALS['qrms_test']['transients'][ $name ] )
		? $GLOBALS['qrms_test']['transients'][ $name ]
		: false;
}

/**
 * Transient siler.
 *
 * @param string $name Ad.
 * @return bool
 */
function delete_transient( $name ) {
	unset( $GLOBALS['qrms_test']['transients'][ $name ] );

	return true;
}

/**
 * Hook kaydını not eder.
 *
 * @param string   $hook     Hook adı.
 * @param callable $callback Callback.
 * @param int      $priority Öncelik.
 * @param int      $args     Argüman sayısı.
 * @return bool
 */
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['qrms_test']['actions'][ $hook ][]    = $callback;
	$GLOBALS['qrms_test']['priorities'][ $hook ][] = $priority;

	return true;
}

/**
 * Filtre kaydı (test için action ile aynı).
 *
 * @param string   $hook     Hook adı.
 * @param callable $callback Callback.
 * @param int      $priority Öncelik.
 * @param int      $args     Argüman sayısı.
 * @return bool
 */
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	return add_action( $hook, $callback, $priority, $args );
}

/**
 * Zamanlanmış cron var mı?
 *
 * @param string $hook Hook adı.
 * @return int|false
 */
function wp_next_scheduled( $hook ) {
	return isset( $GLOBALS['qrms_test']['cron'][ $hook ] )
		? $GLOBALS['qrms_test']['cron'][ $hook ]
		: false;
}

/**
 * Cron zamanlar.
 *
 * @param int    $timestamp Zaman.
 * @param string $recurrence Tekrar.
 * @param string $hook      Hook adı.
 * @return bool
 */
function wp_schedule_event( $timestamp, $recurrence, $hook ) {
	$GLOBALS['qrms_test']['cron'][ $hook ] = $timestamp;

	return true;
}

/**
 * Cron temizler.
 *
 * @param string $hook Hook adı.
 * @return int
 */
function wp_clear_scheduled_hook( $hook ) {
	unset( $GLOBALS['qrms_test']['cron'][ $hook ] );

	return 1;
}

/**
 * HTTP POST taklidi.
 *
 * @param string $url  Adres.
 * @param array  $args Argümanlar.
 * @return array|WP_Error
 */
function wp_remote_post( $url, $args = array() ) {
	$GLOBALS['qrms_test']['http_calls'][] = array(
		'url'  => $url,
		'args' => $args,
	);

	$response = $GLOBALS['qrms_test']['http'];

	if ( is_callable( $response ) ) {
		return call_user_func( $response, $url, $args );
	}

	return $response;
}

/**
 * Cevap gövdesini döndürür.
 *
 * @param array|WP_Error $response Cevap.
 * @return string
 */
function wp_remote_retrieve_body( $response ) {
	return isset( $response['body'] ) ? (string) $response['body'] : '';
}

/**
 * JSON kodlar.
 *
 * @param mixed $data Veri.
 * @return string
 */
/**
 * Varsayılanların üstüne verilen değerleri yazar (WordPress ile aynı davranış).
 *
 * @param array|object|string $args     Gelen değerler.
 * @param array               $defaults Varsayılanlar.
 * @return array
 */
function wp_parse_args( $args, $defaults = array() ) {
	if ( is_object( $args ) ) {
		$args = get_object_vars( $args );
	} elseif ( ! is_array( $args ) ) {
		parse_str( (string) $args, $args );
	}

	return array_merge( $defaults, $args );
}

function wp_json_encode( $data ) {
	return wp_json_encode_impl( $data );
}

/**
 * json_encode sarmalayıcısı.
 *
 * @param mixed $data Veri.
 * @return string
 */
function wp_json_encode_impl( $data ) {
	return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
}

/**
 * URL parçalar.
 *
 * @param string $url       URL.
 * @param int    $component Bileşen.
 * @return mixed
 */
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
}

/**
 * Site adresi.
 *
 * @param string $path Yol.
 * @return string
 */
function home_url( $path = '' ) {
	return 'https://restoran.test' . $path;
}

/**
 * Admin adresi.
 *
 * @param string $path Yol.
 * @return string
 */
function admin_url( $path = '' ) {
	return 'https://restoran.test/wp-admin/' . ltrim( $path, '/' );
}

/**
 * Adrese query arg ekler (yalnızca dizi biçimli çağrı desteklenir).
 *
 * @param array  $args Eklenecek argümanlar.
 * @param string $url  Temel adres.
 * @return string
 */
function add_query_arg( array $args, $url = '' ) {
	$parcalar = explode( '?', (string) $url, 2 );
	$mevcut   = array();

	if ( isset( $parcalar[1] ) && '' !== $parcalar[1] ) {
		parse_str( $parcalar[1], $mevcut );
	}

	$birlesik = array_merge( $mevcut, $args );

	// WordPress ile aynı davranış: değeri false olan anahtar adresten DÜŞER.
	foreach ( $birlesik as $anahtar => $deger ) {
		if ( false === $deger ) {
			unset( $birlesik[ $anahtar ] );
		}
	}

	return empty( $birlesik ) ? $parcalar[0] : $parcalar[0] . '?' . http_build_query( $birlesik );
}

/**
 * Sayıyı yerelleştirir (testte düz biçimlendirme yeter).
 *
 * @param float $sayi    Sayı.
 * @param int   $ondalik Ondalık basamak.
 * @return string
 */
function number_format_i18n( $sayi, $ondalik = 0 ) {
	return number_format( (float) $sayi, (int) $ondalik );
}

/**
 * Yerelleştirilmiş tarih biçimi (testte düz gmdate yeter).
 *
 * @param string   $format Biçim.
 * @param int|null $ts     Zaman damgası.
 * @return string
 */
function date_i18n( $format, $ts = null ) {
	return gmdate( $format, null === $ts ? time() : (int) $ts );
}

/**
 * Tarih biçimlendirir.
 *
 * @param string $format    Format.
 * @param int    $timestamp Zaman damgası.
 * @return string
 */
function wp_date( $format, $timestamp = null ) {
	return gmdate( $format, null === $timestamp ? time() : (int) $timestamp );
}

/**
 * Anahtar temizler.
 *
 * @param string $key Değer.
 * @return string
 */
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

/**
 * Dizideki her öğeden bir alan çeker (WP çekirdeğinin sade taklidi).
 *
 * @param array<int|string,array<string,mixed>|object> $input_list Liste.
 * @param string                                       $field      Alan adı.
 * @param string|null                                  $index_key  İsteğe bağlı indeks alanı.
 * @return array<int|string,mixed>
 */
function wp_list_pluck( $input_list, $field, $index_key = null ) {
	$output = array();

	foreach ( (array) $input_list as $item ) {
		if ( is_object( $item ) ) {
			$item = get_object_vars( $item );
		}

		if ( ! is_array( $item ) || ! array_key_exists( $field, $item ) ) {
			continue;
		}

		if ( null === $index_key ) {
			$output[] = $item[ $field ];
			continue;
		}

		$key = is_array( $item ) && array_key_exists( $index_key, $item )
			? $item[ $index_key ]
			: null;

		if ( null === $key || '' === $key ) {
			$output[] = $item[ $field ];
		} else {
			$output[ $key ] = $item[ $field ];
		}
	}

	return $output;
}

/**
 * Başlıktan slug üretir.
 *
 * Çekirdeğin sanitize_title()'ının test için yeterli sadeleştirmesi: Türkçe
 * harfler ASCII karşılığına indirgenir, harf/rakam dışındaki her şey tireye
 * dönüşür, baştaki ve sondaki tireler kırpılır.
 *
 * @param string $baslik Ham metin.
 * @return string
 */
function sanitize_title( $baslik ) {
	$harita = array(
		'ı' => 'i', 'İ' => 'i', 'ğ' => 'g', 'Ğ' => 'g', 'ü' => 'u', 'Ü' => 'u',
		'ş' => 's', 'Ş' => 's', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c',
	);

	$slug = strtr( (string) $baslik, $harita );
	$slug = strtolower( $slug );
	$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );

	return trim( (string) $slug, '-' );
}

/**
 * Metin temizler.
 *
 * @param string $value Değer.
 * @return string
 */
function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
}

/**
 * Slash temizler.
 *
 * @param mixed $value Değer.
 * @return mixed
 */
function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

/**
 * URL temizler.
 *
 * @param string $url URL.
 * @return string
 */
function esc_url_raw( $url ) {
	return filter_var( (string) $url, FILTER_SANITIZE_URL );
}

/**
 * URL'i çıktı için temizler.
 *
 * @param string $url URL.
 * @return string
 */
function esc_url( $url ) {
	return htmlspecialchars( (string) $url, ENT_QUOTES );
}

/**
 * Sondaki eğik çizgiyi atar.
 *
 * @param string $value Değer.
 * @return string
 */
function untrailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' );
}

/**
 * HTML kaçışı.
 *
 * @param string $text Metin.
 * @return string
 */
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

/**
 * Attribute kaçışı.
 *
 * @param string $text Metin.
 * @return string
 */
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

/**
 * Çeviri stub'ı.
 *
 * @param string $text   Metin.
 * @param string $domain Text domain.
 * @return string
 */
function __( $text, $domain = 'default' ) {
	return $text;
}

/**
 * Çeviri + kaçış.
 *
 * @param string $text   Metin.
 * @param string $domain Text domain.
 * @return string
 */
function esc_html__( $text, $domain = 'default' ) {
	return esc_html( $text );
}

/**
 * Çeviri + kaçış + yazdırma.
 *
 * @param string $text   Metin.
 * @param string $domain Text domain.
 * @return void
 */
function esc_html_e( $text, $domain = 'default' ) {
	echo esc_html( $text );
}

/**
 * Oturum açmış kullanıcı var mı?
 *
 * @return bool
 */
function is_user_logged_in() {
	return ! empty( $GLOBALS['qrms_test']['logged_in'] );
}

/**
 * Yetki kontrolü.
 *
 * @param string $capability Yetki.
 * @return bool
 */
function current_user_can( $capability ) {
	return (bool) $GLOBALS['qrms_test']['can'];
}

/**
 * Ölümcül hata.
 *
 * @param string $message Mesaj.
 * @return void
 */
function wp_die( $message = '' ) {
	throw new RuntimeException( 'wp_die: ' . $message );
}

/**
 * Ajax isteği mi?
 *
 * @return bool
 */
function wp_doing_ajax() {
	return false;
}

/**
 * Nonce alanı.
 *
 * @param string $action Action.
 * @param string $name   Alan adı.
 * @return void
 */
function wp_nonce_field( $action = '', $name = '_wpnonce' ) {
	echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="test-nonce" />';
}

/**
 * Nonce doğrulaması (testte her zaman geçerli).
 *
 * @param string $action Action.
 * @param string $name   Alan adı.
 * @return bool
 */
function check_admin_referer( $action = -1, $name = '_wpnonce' ) {
	return true;
}

/**
 * Yönetim sayfasının başlığı. WordPress admin-header.php bunu $title
 * globaline yazar; alt sayfa breadcrumb'ı aynı kaynaktan okur.
 *
 * @return string
 */
function get_admin_page_title() {
	return isset( $GLOBALS['title'] ) ? (string) $GLOBALS['title'] : '';
}

/**
 * Ayar kaydı (testte hangi grubun hangi option'ı kaydettiği saklanır).
 *
 * @param string $group   Ayar grubu.
 * @param string $option  Option adı.
 * @param array  $args    Ek argümanlar (sanitize_callback vb.).
 * @return void
 */
function register_setting( $group, $option, $args = array() ) {
	$GLOBALS['qrms_test']['settings'][ $group ][ $option ] = $args;
}

/**
 * Ayar formunun gizli alanları.
 *
 * Çekirdekte bu çağrı, options.php'ye hangi ayar GRUBUNUN gönderileceğini
 * belirler. Testte grup adı hem çıktıya basılır hem de kaydedilir; böylece
 * "form şu grubu gönderiyor ama o grup register_setting ile hiç kaydedilmemiş"
 * hatası yakalanabilir.
 *
 * @param string $group Ayar grubu.
 * @return void
 */
function settings_fields( $group ) {
	$GLOBALS['qrms_test']['settings_fields'][] = $group;

	echo '<input type="hidden" name="option_page" value="' . esc_attr( $group ) . '" />';
}

/**
 * Ayar hatası/bildirim kutuları (testte çıktı üretmez).
 *
 * @return void
 */
function settings_errors() {}

/**
 * Gönder butonu.
 *
 * @param string $text Buton metni.
 * @return void
 */
function submit_button( $text = 'Kaydet' ) {
	echo '<button type="submit" class="button button-primary">' . esc_html( $text ) . '</button>';
}

/**
 * Yönlendirme (testte sadece kaydedilir).
 *
 * @param string $location Adres.
 * @return bool
 */
function wp_safe_redirect( $location ) {
	$GLOBALS['qrms_test']['redirects'][] = $location;

	throw new QRMS_Test_Redirect( $location );
}

/** Yönlendirmeyi test edilebilir kılmak için istisna. */
class QRMS_Test_Redirect extends RuntimeException {}

/**
 * Üst menü kaydı.
 *
 * @param string   $page_title Sayfa başlığı.
 * @param string   $menu_title Menü başlığı.
 * @param string   $capability Yetki.
 * @param string   $menu_slug  Slug.
 * @param callable $callback   Callback.
 * @param string   $icon       İkon.
 * @param int      $position   Konum.
 * @return string
 */
function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon = '', $position = null ) {
	$GLOBALS['qrms_test']['menus'][] = array(
		'slug'     => $menu_slug,
		'title'    => $menu_title,
		'position' => $position,
	);

	// WordPress menü satırlarını konumu anahtar yaparak $menu dizisinde tutar.
	if ( null === $position ) {
		$GLOBALS['menu'][] = array( $menu_title, $capability, $menu_slug );
	} else {
		$GLOBALS['menu'][ (string) $position ] = array( $menu_title, $capability, $menu_slug );
	}

	return 'toplevel_page_' . $menu_slug;
}

/**
 * Filtre uygulaması (testte varsayılan değer aynen döner).
 *
 * @param string $hook  Filtre adı.
 * @param mixed  $value Değer.
 * @return mixed
 */
function apply_filters( $hook, $value, ...$args ) {
	if ( empty( $GLOBALS['qrms_test']['actions'][ $hook ] ) ) {
		return $value;
	}

	foreach ( $GLOBALS['qrms_test']['actions'][ $hook ] as $callback ) {
		$value = call_user_func( $callback, $value, ...$args );
	}

	return $value;
}

/**
 * Eylem tetikleme — kayıtlı callback'leri çağırır ve tetiklemeyi not eder.
 *
 * @param string $hook Eylem adı.
 * @param mixed  ...$args Argümanlar.
 * @return void
 */
function do_action( $hook, ...$args ) {
	$GLOBALS['qrms_test']['fired_actions'][] = $hook;

	if ( empty( $GLOBALS['qrms_test']['actions'][ $hook ] ) ) {
		return;
	}

	foreach ( $GLOBALS['qrms_test']['actions'][ $hook ] as $callback ) {
		call_user_func_array( $callback, $args );
	}
}

/**
 * Nesne önbelleğini tümüyle boşaltır (testte yalnızca not edilir).
 *
 * @return bool
 */
function wp_cache_flush() {
	$GLOBALS['qrms_test']['cache_flush'][] = '*';

	return true;
}

/**
 * Nesne önbelleği arka ucunun yeteneği.
 *
 * Varsayılan WordPress kurulumu (kalıcı obje önbelleği yok) grup bazlı
 * temizliği desteklemez; test de bu varsayılanı taklit eder.
 *
 * @param string $feature Yetenek adı.
 * @return bool
 */
function wp_cache_supports( $feature ) {
	return ! empty( $GLOBALS['qrms_test']['cache_supports'][ $feature ] );
}

/**
 * Tek bir önbellek grubunu boşaltır (testte yalnızca not edilir).
 *
 * @param string $group Grup adı.
 * @return bool
 */
function wp_cache_flush_group( $group ) {
	$GLOBALS['qrms_test']['cache_flush'][] = (string) $group;

	return true;
}

/**
 * Nonce üretimi (testte eylem adına bağlı, öngörülebilir bir değer).
 *
 * @param string $action Eylem adı.
 * @return string
 */
function wp_create_nonce( $action = -1 ) {
	return 'test-nonce-' . $action;
}

/**
 * Rastgele parola/anahtar üretimi.
 *
 * @param int  $length         Uzunluk.
 * @param bool $special_chars  Özel karakter.
 * @param bool $extra_special  Ek özel karakter.
 * @return string
 */
function wp_generate_password( $length = 12, $special_chars = true, $extra_special = false ) {
	$havuz = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	$out   = '';

	for ( $i = 0; $i < $length; $i++ ) {
		$out .= $havuz[ random_int( 0, strlen( $havuz ) - 1 ) ];
	}

	return $out;
}

/**
 * Tuzlanmış hash.
 *
 * @param string $data   Veri.
 * @param string $scheme Şema.
 * @return string
 */
function wp_hash( $data, $scheme = 'auth' ) {
	return hash_hmac( 'md5', (string) $data, 'test-salt-' . $scheme );
}

/**
 * E-posta temizleme.
 *
 * @param string $email E-posta.
 * @return string
 */
function sanitize_email( $email ) {
	return trim( (string) $email );
}

/**
 * E-posta geçerli mi?
 *
 * @param string $email E-posta.
 * @return string|false
 */
function is_email( $email ) {
	return filter_var( (string) $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
}

/**
 * Nonce doğrulaması.
 *
 * Gerçek doğrulamada olduğu gibi, nonce YALNIZCA üretildiği eylem için
 * geçerlidir; başka bir eylemin nonce'u ya da uydurma bir değer reddedilir.
 *
 * @param string $nonce  Gelen değer.
 * @param string $action Eylem adı.
 * @return int|false
 */
function wp_verify_nonce( $nonce, $action = -1 ) {
	return ( is_string( $nonce ) && $nonce === wp_create_nonce( $action ) ) ? 1 : false;
}

/**
 * Alt menü kaydı.
 *
 * @param string   $parent_slug Üst slug.
 * @param string   $page_title  Sayfa başlığı.
 * @param string   $menu_title  Menü başlığı.
 * @param string   $capability  Yetki.
 * @param string   $menu_slug   Slug.
 * @param callable $callback    Callback.
 * @return string
 */
function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '' ) {
	$GLOBALS['qrms_test']['submenus'][] = array(
		'parent'   => $parent_slug,
		'slug'     => $menu_slug,
		'title'    => $menu_title,
		'callback' => $callback,
	);

	// WordPress satırı $submenu dizisinde de tutar; gizleme mantığı (beyaz
	// liste + remove_submenu_page) doğrudan bu diziyle çalışır.
	$GLOBALS['submenu'][ $parent_slug ][] = array( $menu_title, $capability, $menu_slug, $page_title );

	return $parent_slug . '_page_' . $menu_slug;
}

/**
 * Alt menü kaldırır (menüden gizler).
 *
 * @param string $parent_slug Üst slug.
 * @param string $menu_slug   Slug.
 * @return bool
 */
function remove_submenu_page( $parent_slug, $menu_slug ) {
	$GLOBALS['qrms_test']['removed'][] = $menu_slug;

	foreach ( $GLOBALS['qrms_test']['submenus'] as $index => $item ) {
		if ( $item['parent'] === $parent_slug && $item['slug'] === $menu_slug ) {
			unset( $GLOBALS['qrms_test']['submenus'][ $index ] );
		}
	}

	$GLOBALS['qrms_test']['submenus'] = array_values( $GLOBALS['qrms_test']['submenus'] );

	// Çekirdek yalnızca $submenu satırını düşürür (anahtarlar seyrek kalır);
	// sayfa kaydı $_registered_pages'te durmaya devam eder.
	if ( isset( $GLOBALS['submenu'][ $parent_slug ] ) ) {
		foreach ( $GLOBALS['submenu'][ $parent_slug ] as $index => $row ) {
			if ( isset( $row[2] ) && $menu_slug === $row[2] ) {
				unset( $GLOBALS['submenu'][ $parent_slug ][ $index ] );
			}
		}
	}

	return true;
}

/**
 * Yayın için güvenli HTML (testte metin aynen döner).
 *
 * @param string $html HTML.
 * @return string
 */
function wp_kses_post( $html ) {
	return $html;
}

/**
 * Stil kaydı (no-op).
 *
 * @param string $handle Handle.
 * @param string $src    Kaynak.
 * @param array  $deps   Bağımlılıklar.
 * @param string $ver    Sürüm.
 * @return void
 */
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false ) {
	if ( ! isset( $GLOBALS['qrms_test']['styles'] ) ) {
		$GLOBALS['qrms_test']['styles'] = array();
	}

	$GLOBALS['qrms_test']['styles'][] = array(
		'handle' => $handle,
		'src'    => $src,
		'ver'    => $ver,
	);
}

/**
 * Script kaydı (no-op).
 *
 * @param string $handle    Handle.
 * @param string $src       Kaynak.
 * @param array  $deps      Bağımlılıklar.
 * @param string $ver       Sürüm.
 * @param bool   $in_footer Footer'da mı?
 * @return void
 */
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
	if ( ! isset( $GLOBALS['qrms_test']['scripts'] ) ) {
		$GLOBALS['qrms_test']['scripts'] = array();
	}

	$GLOBALS['qrms_test']['scripts'][] = array(
		'handle' => $handle,
		'src'    => $src,
		'ver'    => $ver,
	);
}

/**
 * Stil handle kaydı (no-op).
 *
 * @return void
 */
function wp_register_style( $handle, $src = '', $deps = array(), $ver = false ) {}

/**
 * Satır içi stil.
 *
 * @param string $handle Handle.
 * @param string $data   CSS.
 * @return bool
 */
function wp_add_inline_style( $handle, $data ) {
	if ( ! isset( $GLOBALS['qrms_test']['inline_styles'] ) ) {
		$GLOBALS['qrms_test']['inline_styles'] = array();
	}

	$GLOBALS['qrms_test']['inline_styles'][] = array(
		'handle' => $handle,
		'data'   => $data,
	);

	return true;
}

/**
 * Yönetim ekranı mı?
 *
 * @return bool
 */
function is_admin() {
	return ! empty( $GLOBALS['qrms_test']['is_admin'] );
}

/**
 * Feed isteği mi?
 *
 * @return bool
 */
function is_feed() {
	return ! empty( $GLOBALS['qrms_test']['is_feed'] );
}

/**
 * robots.txt isteği mi?
 *
 * @return bool
 */
function is_robots() {
	return ! empty( $GLOBALS['qrms_test']['is_robots'] );
}

/**
 * 404 sayfası mı?
 *
 * @return bool
 */
function is_404() {
	return ! empty( $GLOBALS['qrms_test']['is_404'] );
}

/**
 * wp-login.php mi?
 *
 * @return bool
 */
function is_login() {
	return ! empty( $GLOBALS['qrms_test']['is_login'] );
}

/**
 * Kısa kod kaydı.
 *
 * @param string   $tag      Etiket.
 * @param callable $callback Callback.
 * @return void
 */
function add_shortcode( $tag, $callback ) {
	if ( ! isset( $GLOBALS['qrms_test']['shortcodes'] ) ) {
		$GLOBALS['qrms_test']['shortcodes'] = array();
	}
	$GLOBALS['qrms_test']['shortcodes'][ $tag ] = $callback;
}

/**
 * Kısa kod öznitelik varsayılanları.
 *
 * @param array $pairs Varsayılanlar.
 * @param array $atts  Gelen.
 * @param string $shortcode Etiket.
 * @return array
 */
function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	$atts = is_array( $atts ) ? $atts : array();
	return array_merge( $pairs, $atts );
}

/**
 * Script'e veri geçirir.
 *
 * @param string $handle Handle.
 * @param string $name   Nesne adı.
 * @param array  $data   Veri.
 * @return void
 */
function wp_localize_script( $handle, $name, $data ) {
	$GLOBALS['qrms_test']['localized'][] = array(
		'handle' => $handle,
		'name'   => $name,
		'data'   => $data,
	);
}

/**
 * Negatif olmayan tam sayı.
 *
 * @param mixed $maybeint Değer.
 * @return int
 */
function absint( $maybeint ) {
	return abs( (int) $maybeint );
}

/**
 * Geçerli bir hex renk mi? Değilse null.
 *
 * @param string $color Renk.
 * @return string|null
 */
function sanitize_hex_color( $color ) {
	$color = (string) $color;

	return preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $color ) ? $color : null;
}

/**
 * Onay kutusu işaretli mi? (checked="checked")
 *
 * @param mixed $checked Değer.
 * @param mixed $current Karşılaştırılan.
 * @param bool  $echo    Basılsın mı.
 * @return string
 */
function checked( $checked, $current = true, $echo = true ) {
	$out = ( (string) $checked === (string) $current ) ? ' checked="checked"' : '';

	if ( $echo ) {
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	return $out;
}

/**
 * Seçili option mı? (selected="selected")
 *
 * @param mixed $selected Değer.
 * @param mixed $current  Karşılaştırılan.
 * @param bool  $echo     Basılsın mı.
 * @return string
 */
function selected( $selected, $current = true, $echo = true ) {
	$out = ( (string) $selected === (string) $current ) ? ' selected="selected"' : '';

	if ( $echo ) {
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	return $out;
}

/**
 * Ek dosyanın adresi. Testte kimlik varsa sahte bir adres döner.
 *
 * @param int    $id   Ek dosya kimliği.
 * @param string $size Boyut.
 * @return string
 */
function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) {
	$id = absint( $id );

	return $id ? 'https://restoran.test/wp-content/uploads/' . $id . '-' . $size . '.jpg' : '';
}

/**
 * Ek dosyanın srcset'i.
 *
 * @param int    $id   Ek dosya kimliği.
 * @param string $size Boyut.
 * @return string
 */
function wp_get_attachment_image_srcset( $id, $size = 'medium' ) {
	$id = absint( $id );

	return $id ? wp_get_attachment_image_url( $id, $size ) . ' 1024w' : '';
}

/**
 * Ek dosyanın <img> etiketi.
 *
 * @param int    $id    Ek dosya kimliği.
 * @param string $size  Boyut.
 * @param bool   $icon  Kullanılmıyor.
 * @param array  $attrs Ek nitelikler.
 * @return string
 */
function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attrs = array() ) {
	$id = absint( $id );

	if ( ! $id ) {
		return '';
	}

	$out = '<img src="' . esc_url( wp_get_attachment_image_url( $id, $size ) ) . '"';
	foreach ( (array) $attrs as $name => $value ) {
		$out .= ' ' . $name . '="' . esc_attr( $value ) . '"';
	}

	return $out . ' />';
}

/**
 * Medya kütüphanesi betikleri (no-op).
 *
 * @return void
 */
function wp_enqueue_media() {}

/**
 * Script'e ek veri işler (no-op).
 *
 * @param string $handle Handle.
 * @param string $key    Anahtar.
 * @param mixed  $value  Değer.
 * @return bool
 */
function wp_script_add_data( $handle, $key, $value ) {
	return true;
}

/**
 * Satır içi script ekler (no-op).
 *
 * @param string $handle   Handle.
 * @param string $data     Kod.
 * @param string $position Konum.
 * @return bool
 */
function wp_add_inline_script( $handle, $data, $position = 'after' ) {
	return true;
}

/**
 * Ana sayfada mıyız? Testte $GLOBALS['qrms_test']['is_front_page'] belirler.
 *
 * @return bool
 */
function is_front_page() {
	return ! empty( $GLOBALS['qrms_test']['is_front_page'] );
}

/**
 * Customizer önizlemesinde miyiz? Testte kapalıdır.
 *
 * @return bool
 */
function is_customize_preview() {
	return ! empty( $GLOBALS['qrms_test']['is_customize_preview'] );
}

/**
 * Sitenin yerel saatiyle "şimdi".
 *
 * Testte site saati UTC kabul edilir; sabit bir an gerekiyorsa
 * $GLOBALS['qrms_test']['now'] ile verilebilir.
 *
 * @param string $type      'mysql' | 'timestamp' | date() biçimi.
 * @param int    $gmt       Yok sayılır (test saati zaten UTC).
 * @return string|int
 */
function current_time( $type, $gmt = 0 ) {
	$now = isset( $GLOBALS['qrms_test']['now'] ) ? (int) $GLOBALS['qrms_test']['now'] : time();

	if ( 'timestamp' === $type ) {
		return $now;
	}

	if ( 'mysql' === $type ) {
		return gmdate( 'Y-m-d H:i:s', $now );
	}

	return gmdate( $type, $now );
}

/**
 * Metin alanı temizleme (çok satırlı).
 *
 * @param string $value Değer.
 * @return string
 */
function sanitize_textarea_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

/**
 * Kurulum tuzu.
 *
 * Gerçek wp_salt gibi şemaya göre farklı, çalışma boyunca sabit bir değer
 * döndürür; imzalı zaman tuzağı ve captcha testleri bunun kararlılığına dayanır.
 *
 * @param string $scheme Şema.
 * @return string
 */
function wp_salt( $scheme = 'auth' ) {
	return 'test-salt-' . $scheme;
}

/**
 * Rastgele tam sayı.
 *
 * @param int $min Alt sınır.
 * @param int $max Üst sınır.
 * @return int
 */
function wp_rand( $min = 0, $max = 0 ) {
	if ( $max <= $min ) {
		return $min;
	}

	return random_int( $min, $max );
}
/**
 * Öznitelik için kaçışlanmış metni basar.
 *
 * @param string $text   Metin.
 * @param string $domain Metin alanı.
 * @return void
 */
function esc_attr_e( $text, $domain = 'default' ) {
	echo esc_attr( $text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Öznitelik için çeviri + kaçış.
 *
 * @param string $text   Metin.
 * @param string $domain Metin alanı.
 * @return string
 */
function esc_attr__( $text, $domain = 'default' ) {
	return esc_attr( $text );
}

/**
 * Çoğul çeviri stub'ı — sayıya göre tekil/çoğul kalıbı seçer.
 *
 * @param string $single Tekil kalıp.
 * @param string $plural Çoğul kalıp.
 * @param int    $number Sayı.
 * @param string $domain Metin alanı.
 * @return string
 */
function _n( $single, $plural, $number, $domain = 'default' ) {
	return 1 === (int) $number ? $single : $plural;
}

/**
 * AJAX nonce doğrulaması. Testlerde her zaman geçerli sayılır.
 *
 * @param string $action Eylem.
 * @param string $query  Alan adı.
 * @return bool
 */
function check_ajax_referer( $action = -1, $query = false ) {
	return true;
}

/**
 * Başarılı JSON yanıtı — testlerde çıktı yerine global'e yazılır.
 *
 * @param mixed $data Veri.
 * @return void
 */
function wp_send_json_success( $data = null ) {
	$GLOBALS['qrms_test']['json'] = array(
		'success' => true,
		'data'    => $data,
	);
}

/**
 * Hatalı JSON yanıtı.
 *
 * @param mixed $data   Veri.
 * @param int   $status HTTP durumu.
 * @return void
 */
function wp_send_json_error( $data = null, $status = 0 ) {
	$GLOBALS['qrms_test']['json'] = array(
		'success' => false,
		'data'    => $data,
		'status'  => $status,
	);
}

/**
 * Site bilgisi.
 *
 * @param string $show Alan adı.
 * @return string
 */
function get_bloginfo( $show = 'name' ) {
	$map = array(
		'name'        => 'Test Restoran',
		'description' => 'Test açıklaması',
		'url'         => 'https://restoran.test',
	);

	return isset( $map[ $show ] ) ? $map[ $show ] : '';
}

/**
 * Kısa kod kayıtlı mı?
 *
 * @param string $tag Etiket.
 * @return bool
 */
function shortcode_exists( $tag ) {
	return isset( $GLOBALS['qrms_test']['shortcodes'][ $tag ] );
}

/**
 * Kısa kodu çalıştırır (yalnızca "[tag]" biçimini çözer — testler için yeter).
 *
 * @param string $content İçerik.
 * @return string
 */
function do_shortcode( $content ) {
	if ( ! preg_match( '/^\[([a-z0-9_]+)\]$/i', trim( (string) $content ), $m ) ) {
		return (string) $content;
	}

	if ( ! shortcode_exists( $m[1] ) ) {
		return (string) $content;
	}

	return (string) call_user_func( $GLOBALS['qrms_test']['shortcodes'][ $m[1] ], array() );
}

/**
 * İçerikte kısa kod var mı?
 *
 * @param string $content İçerik.
 * @param string $tag     Etiket.
 * @return bool
 */
function has_shortcode( $content, $tag ) {
	return false !== strpos( (string) $content, '[' . $tag );
}

/**
 * Tekil içerik görüntüleniyor mu? (Testlerde her zaman hayır.)
 *
 * @return bool
 */
function is_singular() {
	return false;
}

/**
 * Geçerli yazı. (Testlerde yok.)
 *
 * @return null
 */
function get_post() {
	return null;
}

/**
 * Yazı meta değeri. (Testlerde boş.)
 *
 * @param int    $post_id Yazı kimliği.
 * @param string $key     Anahtar.
 * @param bool   $single  Tek değer mi?
 * @return string
 */
function get_post_meta( $post_id, $key = '', $single = false ) {
	$meta = isset( $GLOBALS['qrms_test']['post_meta'][ $post_id ] )
		? $GLOBALS['qrms_test']['post_meta'][ $post_id ]
		: array();

	return isset( $meta[ $key ] ) ? $meta[ $key ] : '';
}

/**
 * Yazı listesi. (Testte $GLOBALS['qrms_test']['posts'] döner; çağrı sayılır ki
 * ürün başına sorgu açılmadığı doğrulanabilsin.)
 *
 * @param array $args Sorgu argümanları.
 * @return array
 */
function get_posts( $args = array() ) {
	if ( ! isset( $GLOBALS['qrms_test']['get_posts_calls'] ) ) {
		$GLOBALS['qrms_test']['get_posts_calls'] = 0;
	}

	++$GLOBALS['qrms_test']['get_posts_calls'];

	$kayitlar = isset( $GLOBALS['qrms_test']['posts'] ) ? $GLOBALS['qrms_test']['posts'] : array();
	$limit    = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : -1;

	return $limit > 0 ? array_slice( $kayitlar, 0, $limit ) : $kayitlar;
}

/**
 * Yazının terimleri. (Testte $GLOBALS['qrms_test']['terms'] eşlemesi.)
 *
 * @param int    $post_id  Yazı kimliği.
 * @param string $taxonomy Taksonomi.
 * @return array|false
 */
function get_the_terms( $post_id, $taxonomy ) {
	if ( empty( $GLOBALS['qrms_test']['terms'][ $post_id ] ) ) {
		return false;
	}

	return array( (object) array( 'name' => $GLOBALS['qrms_test']['terms'][ $post_id ] ) );
}

/**
 * Terim listesi — get_the_terms ile aynı kutu.
 *
 * @param int    $post_id  Yazı kimliği.
 * @param string $taxonomy Taksonomi.
 * @return array|WP_Error
 */
function wp_get_post_terms( $post_id, $taxonomy, $args = array() ) {
	unset( $args );
	$terimler = get_the_terms( $post_id, $taxonomy );

	return is_array( $terimler ) ? $terimler : array();
}

/**
 * Yazı başlığı.
 *
 * @param int $post_id Yazı kimliği.
 * @return string
 */
function get_the_title( $post_id = 0 ) {
	$post = get_post( $post_id );

	return ( $post && isset( $post->post_title ) ) ? (string) $post->post_title : '';
}

/**
 * Yazı durumu. Testte $GLOBALS['qrms_test']['post_status'][id] yoksa
 * pozitif ID "publish" sayılır (silinmiş senaryo açıkça işaretlenir).
 *
 * @param int|object|null $post Yazı veya ID.
 * @return string|false
 */
function get_post_status( $post = null ) {
	$id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;

	if ( isset( $GLOBALS['qrms_test']['post_status'][ $id ] ) ) {
		return $GLOBALS['qrms_test']['post_status'][ $id ];
	}

	return $id > 0 ? 'publish' : false;
}

/**
 * Yazı sayısı. Testte $GLOBALS['qrms_test']['post_counts'][type].
 *
 * @param string $type Post type.
 * @return object
 */
function wp_count_posts( $type = 'post' ) {
	$n = isset( $GLOBALS['qrms_test']['post_counts'][ $type ] )
		? (int) $GLOBALS['qrms_test']['post_counts'][ $type ]
		: 0;

	return (object) array( 'publish' => $n );
}

/**
 * Taksonomi terimleri. (Testte yalnızca 'names' alanı desteklenir.)
 *
 * @param array $args Argümanlar.
 * @return array
 */
function get_terms( $args = array() ) {
	return isset( $GLOBALS['qrms_test']['term_names'] ) ? $GLOBALS['qrms_test']['term_names'] : array();
}

/**
 * İçerik türü kayıtlı mı? (Testte her zaman evet.)
 *
 * @param string $tur Tür adı.
 * @return bool
 */
function post_type_exists( $tur ) {
	return true;
}

/**
 * Kayıtlı post type'lar. Testte $GLOBALS['qrms_test']['post_types'] (slug => etiket).
 *
 * @param array  $args   Argümanlar (yok sayılır).
 * @param string $output names|objects.
 * @return array
 */
function get_post_types( $args = array(), $output = 'names' ) {
	$types = isset( $GLOBALS['qrms_test']['post_types'] ) ? $GLOBALS['qrms_test']['post_types'] : array();

	if ( 'objects' !== $output ) {
		return array_keys( $types );
	}

	$out = array();
	foreach ( $types as $slug => $label ) {
		$nesne                     = new stdClass();
		$nesne->labels             = new stdClass();
		$nesne->labels->singular_name = (string) $label;
		$out[ $slug ]              = $nesne;
	}

	return $out;
}

/**
 * Kayıtlı taxonomy'ler. Testte $GLOBALS['qrms_test']['taxonomies'] (slug => etiket).
 *
 * @param array  $args   Argümanlar (yok sayılır).
 * @param string $output names|objects.
 * @return array
 */
function get_taxonomies( $args = array(), $output = 'names' ) {
	$taks = isset( $GLOBALS['qrms_test']['taxonomies'] ) ? $GLOBALS['qrms_test']['taxonomies'] : array();

	if ( 'objects' !== $output ) {
		return array_keys( $taks );
	}

	$out = array();
	foreach ( $taks as $slug => $label ) {
		$nesne                     = new stdClass();
		$nesne->labels             = new stdClass();
		$nesne->labels->singular_name = (string) $label;
		$out[ $slug ]              = $nesne;
	}

	return $out;
}

/**
 * Taksonomi kayıtlı mı? (Testte her zaman evet.)
 *
 * @param string $taksonomi Taksonomi adı.
 * @return bool
 */
function taxonomy_exists( $taksonomi ) {
	return true;
}

/**
 * Kanca kaç kez çalıştı?
 *
 * @param string $tag Kanca adı.
 * @return int
 */
function did_action( $tag ) {
	return 0;
}

/**
 * Kayıtlı menüler. (Testlerde tek bir sahte menü.)
 *
 * @return object[]
 */
function wp_get_nav_menus() {
	$menu           = new stdClass();
	$menu->term_id  = 7;
	$menu->name     = 'Ana Menü';

	return array( $menu );
}

/**
 * Menü çıktısı.
 *
 * @param array $args Argümanlar.
 * @return string
 */
function wp_nav_menu( $args = array() ) {
	$class = isset( $args['menu_class'] ) ? $args['menu_class'] : 'menu';
	$id    = isset( $args['menu_id'] ) ? $args['menu_id'] : 'menu';

	// Gerçek wp_nav_menu her <li>'ye ve <ul>'ye id verir; çift basım
	// senaryosunu yakalayabilmek için stub da veriyor.
	$html = '<ul id="' . esc_attr( $id ) . '" class="' . esc_attr( $class ) . '">'
		. '<li id="menu-item-101" class="menu-item"><a href="#">Menü</a></li>'
		. '<li id="menu-item-102" class="menu-item"><a href="#">İletişim</a></li>'
		. '</ul>';

	if ( empty( $args['echo'] ) ) {
		return $html;
	}

	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	return '';
}

/**
 * Textarea içeriği kaçışlar.
 *
 * @param string $text Metin.
 * @return string
 */
function esc_textarea( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

require_once QRMS_PLUGIN_DIR . 'includes/class-helpers.php';
require_once QRMS_PLUGIN_DIR . 'includes/class-license-client.php';
require_once QRMS_PLUGIN_DIR . 'includes/class-module-loader.php';
require_once QRMS_PLUGIN_DIR . 'includes/class-wizard.php';
require_once QRMS_PLUGIN_DIR . 'includes/class-shortcodes.php';
require_once QRMS_PLUGIN_DIR . 'includes/class-admin.php';
require_once QRMS_PLUGIN_DIR . 'includes/class-query-monitor.php';
