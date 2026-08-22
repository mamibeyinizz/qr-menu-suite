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
 * @param string $name  Option adı.
 * @param mixed  $value Değer.
 * @return bool
 */
function update_option( $name, $value ) {
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
	$GLOBALS['qrms_test']['actions'][ $hook ][] = $callback;

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
function apply_filters( $hook, $value ) {
	return $value;
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
 * Yönetim ekranı mı?
 *
 * @return bool
 */
function is_admin() {
	return ! empty( $GLOBALS['qrms_test']['is_admin'] );
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
 * Script'e veri geçirir (no-op).
 *
 * @param string $handle Handle.
 * @param string $name   Nesne adı.
 * @param array  $data   Veri.
 * @return void
 */
function wp_localize_script( $handle, $name, $data ) {}

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

require_once QRMS_PLUGIN_DIR . 'includes/class-helpers.php';
require_once QRMS_PLUGIN_DIR . 'includes/class-license-client.php';
require_once QRMS_PLUGIN_DIR . 'includes/class-module-loader.php';
require_once QRMS_PLUGIN_DIR . 'includes/class-wizard.php';
require_once QRMS_PLUGIN_DIR . 'includes/class-shortcodes.php';
require_once QRMS_PLUGIN_DIR . 'includes/class-admin.php';
