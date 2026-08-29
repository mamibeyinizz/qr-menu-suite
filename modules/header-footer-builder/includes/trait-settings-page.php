<?php
/**
 * Header Footer Builder — ayar şeması, temizleme ve kayıt.
 *
 * Header/footer içeriğinin yanı sıra tasarım ayarları (logo boyutu, renk,
 * sticky davranış, hamburger paneli, tipografi) da burada tutulur.
 * Varsayılan palet dark-gold kimliğidir; kullanıcı bunları ayar
 * sayfasından değiştirir.
 *
 * Hem form kaydı hem canlı önizleme AYNI temizleyicileri kullanır
 * (sanitize_header_input / sanitize_footer_input / sanitize_hamburger_input).
 * Böylece önizlemede görünen çıktı ile kaydedilen çıktı birbirinden
 * ayrışamaz.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

trait QRMS_HFB_Settings_Page {

	/**
	 * Header ayarlarını döndürür.
	 *
	 * @return array<string,mixed>
	 */
	public function get_header_options() {
		return $this->merge_options( get_option( $this->header_option, array() ), $this->header_defaults );
	}

	/**
	 * Footer ayarlarını döndürür.
	 *
	 * @return array<string,mixed>
	 */
	public function get_footer_options() {
		return $this->merge_options( get_option( $this->footer_option, array() ), $this->footer_defaults );
	}

	/**
	 * Hamburger panel ayarlarını döndürür.
	 *
	 * @return array<string,mixed>
	 */
	public function get_hamburger_options() {
		return $this->merge_options( get_option( $this->hamburger_option, array() ), $this->hamburger_defaults );
	}

	/**
	 * Depodaki değerleri varsayılanlarla birleştirir ve ARTIK TANIMLI
	 * OLMAYAN anahtarları atar.
	 *
	 * Eski kurulumların option'ında kaldırılmış anahtarlar (varyant,
	 * gradient, eski tekil logo_width) duruyor olabilir; budama olmadan
	 * hem okunmaya devam eder hem de her kayıtta geri yazılırlardı.
	 *
	 * @param mixed               $stored   Depodaki değer.
	 * @param array<string,mixed> $defaults Varsayılanlar.
	 * @return array<string,mixed>
	 */
	private function merge_options( $stored, $defaults ) {
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$merged = array_intersect_key( wp_parse_args( $stored, $defaults ), $defaults );

		foreach ( $defaults as $key => $default ) {
			if ( is_array( $default ) && isset( $merged[ $key ] ) && ! is_array( $merged[ $key ] ) ) {
				$merged[ $key ] = $default;
			}
		}

		return $merged;
	}

	/**
	 * Yazı tipi kataloğu.
	 *
	 * Admin açılır listesi, sanitize beyaz listesi ve frontend font
	 * yüklemesi hep buradan okur. Playfair Display + Manrope eklentinin
	 * mevcut yüzleridir; birkaç sistem/Google seçeneği eklenmiştir.
	 *
	 * `google` boşsa dış istek yapılmaz.
	 *
	 * @return array<string,array{etiket:string,stack:string,google:string}>
	 */
	public function font_catalog() {
		return array(
			'Playfair Display' => array(
				'etiket' => 'Playfair Display (serif)',
				'stack'  => "'Playfair Display', Georgia, serif",
				'google' => 'Playfair+Display:wght@400;500;600;700',
			),
			'Manrope'          => array(
				'etiket' => 'Manrope',
				'stack'  => "'Manrope', system-ui, sans-serif",
				'google' => 'Manrope:wght@400;500;600;700',
			),
			'Inter'            => array(
				'etiket' => 'Inter',
				'stack'  => "'Inter', system-ui, sans-serif",
				'google' => 'Inter:wght@400;500;600;700',
			),
			'Poppins'          => array(
				'etiket' => 'Poppins',
				'stack'  => "'Poppins', system-ui, sans-serif",
				'google' => 'Poppins:wght@400;500;600;700',
			),
			'Montserrat'       => array(
				'etiket' => 'Montserrat',
				'stack'  => "'Montserrat', system-ui, sans-serif",
				'google' => 'Montserrat:wght@400;500;600;700',
			),
			'system'           => array(
				'etiket' => 'Sistem yazı tipi',
				'stack'  => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
				'google' => '',
			),
			'Georgia'          => array(
				'etiket' => 'Georgia (serif)',
				'stack'  => 'Georgia, "Times New Roman", serif',
				'google' => '',
			),
		);
	}

	/**
	 * Seçilen yazı tipinin CSS font-family yığını.
	 *
	 * @param string $key Katalog anahtarı.
	 * @return string
	 */
	public function font_stack( $key ) {
		$catalog = $this->font_catalog();

		if ( isset( $catalog[ $key ] ) ) {
			return $catalog[ $key ]['stack'];
		}

		return $catalog['Playfair Display']['stack'];
	}

	/**
	 * Hamburger panelindeki sıralanabilir blok tipleri.
	 *
	 * @return array<string,string> anahtar => etiket
	 */
	public function hamburger_block_types() {
		return array(
			'logo'   => __( 'Logo', 'qrms' ),
			'menu'   => __( 'Menü', 'qrms' ),
			'social' => __( 'Sosyal medya', 'qrms' ),
			'text'   => __( 'Metin', 'qrms' ),
		);
	}

	/**
	 * Sosyal medya platform kataloğu.
	 *
	 * @return array<string,array{label:string,icon:string}>
	 */
	public function social_media_map() {
		return array(
			'facebook'  => array( 'label' => 'Facebook', 'icon' => 'facebook' ),
			'x'         => array( 'label' => 'X (Twitter)', 'icon' => 'x' ),
			'youtube'   => array( 'label' => 'YouTube', 'icon' => 'youtube' ),
			'instagram' => array( 'label' => 'Instagram', 'icon' => 'instagram' ),
			'tiktok'    => array( 'label' => 'TikTok', 'icon' => 'tiktok' ),
			'whatsapp'  => array( 'label' => 'WhatsApp', 'icon' => 'whatsapp' ),
			'linkedin'  => array( 'label' => 'LinkedIn', 'icon' => 'linkedin' ),
			'pinterest' => array( 'label' => 'Pinterest', 'icon' => 'pinterest' ),
		);
	}

	/**
	 * Sosyal medya durumunu çözümler.
	 *
	 * @param array<string,mixed> $opts Ayarlar.
	 * @return array{active:string[],urls:array<string,string>}
	 */
	public function resolve_social_media_state( $opts ) {
		$map    = $this->social_media_map();
		$active = isset( $opts['social_media_active'] ) && is_array( $opts['social_media_active'] ) ? $opts['social_media_active'] : array();
		$urls   = isset( $opts['social_media'] ) && is_array( $opts['social_media'] ) ? $opts['social_media'] : array();

		$ordered = array();
		foreach ( $active as $key ) {
			if ( isset( $map[ $key ] ) && ! empty( $urls[ $key ] ) && ! in_array( $key, $ordered, true ) ) {
				$ordered[] = $key;
			}
		}

		return array(
			'active' => array_slice( $ordered, 0, 6 ),
			'urls'   => $urls,
		);
	}

	/**
	 * Bir girdi dizisinden sosyal medya alanlarını çözer.
	 *
	 * @param array<string,mixed> $input   Ham girdi.
	 * @param array<string,mixed> $current Mevcut ayarlar.
	 * @param string              $prefix  Alan adı ön eki.
	 * @return array{social_media_active:string[],social_media:array<string,string>}
	 */
	private function sanitize_social_input( $input, $current, $prefix ) {
		$valid  = array_keys( $this->social_media_map() );
		$active = array();
		$urls   = isset( $current['social_media'] ) && is_array( $current['social_media'] ) ? $current['social_media'] : array();

		$raw_active = isset( $input[ $prefix . 'social_media_active' ] ) ? $input[ $prefix . 'social_media_active' ] : array();
		if ( is_array( $raw_active ) ) {
			foreach ( $raw_active as $raw_key ) {
				$key = sanitize_key( $raw_key );
				if ( in_array( $key, $valid, true ) && ! in_array( $key, $active, true ) ) {
					$active[] = $key;
				}
			}
		}

		foreach ( $valid as $key ) {
			$field = $prefix . 'social_media_url_' . $key;
			if ( isset( $input[ $field ] ) ) {
				$urls[ $key ] = esc_url_raw( (string) $input[ $field ] );
			}
		}

		return array(
			'social_media_active' => array_slice( $active, 0, 6 ),
			'social_media'        => $urls,
		);
	}

	/**
	 * Bir onay kutusunun girdideki değeri.
	 *
	 * Form ve önizleme yükü tam gönderilir; alan yoksa kutu işaretsizdir.
	 *
	 * @param array<string,mixed> $input Ham girdi.
	 * @param string              $field Alan adı.
	 * @return int
	 */
	private function sanitize_checkbox( $input, $field ) {
		return ( isset( $input[ $field ] ) && '' !== $input[ $field ] && '0' !== (string) $input[ $field ] ) ? 1 : 0;
	}

	/**
	 * Hex rengi temizler; geçersizse varsayılana düşer.
	 *
	 * @param array<string,mixed> $input    Ham girdi.
	 * @param string              $field    Alan adı.
	 * @param string              $fallback Varsayılan.
	 * @return string
	 */
	private function sanitize_color_field( $input, $field, $fallback ) {
		if ( ! isset( $input[ $field ] ) ) {
			return $fallback;
		}

		$raw = trim( (string) $input[ $field ] );

		if ( '' === $raw ) {
			return $fallback;
		}

		$color = sanitize_hex_color( $raw );

		return $color ? $color : $fallback;
	}

	/**
	 * Tam sayı aralığı.
	 *
	 * @param mixed $value   Ham değer.
	 * @param int   $min     Alt sınır.
	 * @param int   $max     Üst sınır.
	 * @param int   $default Varsayılan.
	 * @return int
	 */
	private function sanitize_int_range( $value, $min, $max, $default ) {
		if ( null === $value || '' === $value ) {
			return (int) $default;
		}

		$n = absint( $value );

		if ( $n < $min ) {
			return (int) $min;
		}

		if ( $n > $max ) {
			return (int) $max;
		}

		return $n;
	}

	/**
	 * Yazı kalınlığı (400/500/600/700).
	 *
	 * @param mixed $value   Ham değer.
	 * @param int   $default Varsayılan.
	 * @return int
	 */
	private function sanitize_font_weight( $value, $default ) {
		$n = absint( $value );

		return in_array( $n, array( 400, 500, 600, 700 ), true ) ? $n : (int) $default;
	}

	/**
	 * Metin hizalama.
	 *
	 * @param mixed  $value   Ham değer.
	 * @param string $default Varsayılan.
	 * @return string
	 */
	private function sanitize_align( $value, $default ) {
		$value = sanitize_key( (string) $value );

		return in_array( $value, array( 'left', 'center', 'right' ), true ) ? $value : $default;
	}

	/**
	 * Logo yüksekliği: otomatik işaretliyken 0, değilse aralığa sıkıştırılır.
	 *
	 * @param array<string,mixed> $input     Ham girdi.
	 * @param string              $auto_key  Otomatik oran onay kutusu.
	 * @param string              $height_key Yükseklik alanı.
	 * @param int                 $current    Mevcut yükseklik.
	 * @return array{auto:int,height:int}
	 */
	private function sanitize_logo_height( $input, $auto_key, $height_key, $current ) {
		$auto = $this->sanitize_checkbox( $input, $auto_key );

		if ( $auto ) {
			return array(
				'auto'   => 1,
				'height' => 0,
			);
		}

		$raw = isset( $input[ $height_key ] ) ? $input[ $height_key ] : $current;

		return array(
			'auto'   => 0,
			'height' => $this->sanitize_int_range( $raw, self::LOGO_HEIGHT_MIN, self::LOGO_HEIGHT_MAX, self::LOGO_HEIGHT_MIN ),
		);
	}

	/**
	 * Header girdisini temizler.
	 *
	 * @param array<string,mixed> $input   Ham girdi (POST alan adlarıyla).
	 * @param array<string,mixed> $current Mevcut ayarlar.
	 * @return array<string,mixed>
	 */
	public function sanitize_header_input( $input, $current ) {
		$opts = $current;

		if ( isset( $input['hfb_header_logo'] ) ) {
			$opts['logo'] = absint( $input['hfb_header_logo'] );
		}

		if ( isset( $input['hfb_header_brand_line1'] ) ) {
			$opts['brand_line1'] = sanitize_text_field( (string) $input['hfb_header_brand_line1'] );
		}

		if ( isset( $input['hfb_header_brand_line2'] ) ) {
			$opts['brand_line2'] = sanitize_text_field( (string) $input['hfb_header_brand_line2'] );
		}

		if ( isset( $input['hfb_header_menu_id'] ) ) {
			$opts['menu_id'] = absint( $input['hfb_header_menu_id'] );
		}

		if ( isset( $input['hfb_header_cta_phone'] ) ) {
			$opts['cta_phone'] = sanitize_text_field( (string) $input['hfb_header_cta_phone'] );
		}

		$opts['sticky']      = $this->sanitize_checkbox( $input, 'hfb_header_sticky' );
		$opts['sticky_blur'] = $this->sanitize_checkbox( $input, 'hfb_header_sticky_blur' );
		$opts['lang_show']   = $this->sanitize_checkbox( $input, 'hfb_lang_show' );

		if ( isset( $input['hfb_header_logo_width_desktop'] ) ) {
			$opts['logo_width_desktop'] = $this->sanitize_int_range(
				$input['hfb_header_logo_width_desktop'],
				self::LOGO_WIDTH_MIN,
				self::LOGO_WIDTH_MAX,
				$this->header_defaults['logo_width_desktop']
			);
		}

		if ( isset( $input['hfb_header_logo_width_tablet'] ) ) {
			$opts['logo_width_tablet'] = $this->sanitize_int_range(
				$input['hfb_header_logo_width_tablet'],
				self::LOGO_WIDTH_MIN,
				self::LOGO_WIDTH_MAX,
				$this->header_defaults['logo_width_tablet']
			);
		}

		if ( isset( $input['hfb_header_logo_width_mobile'] ) ) {
			$opts['logo_width_mobile'] = $this->sanitize_int_range(
				$input['hfb_header_logo_width_mobile'],
				self::LOGO_WIDTH_MIN,
				self::LOGO_WIDTH_MAX,
				$this->header_defaults['logo_width_mobile']
			);
		}

		$desktop_h = $this->sanitize_logo_height(
			$input,
			'hfb_header_logo_height_auto_desktop',
			'hfb_header_logo_height_desktop',
			isset( $current['logo_height_desktop'] ) ? (int) $current['logo_height_desktop'] : 0
		);
		$opts['logo_height_auto_desktop'] = $desktop_h['auto'];
		$opts['logo_height_desktop']      = $desktop_h['height'];

		$tablet_h = $this->sanitize_logo_height(
			$input,
			'hfb_header_logo_height_auto_tablet',
			'hfb_header_logo_height_tablet',
			isset( $current['logo_height_tablet'] ) ? (int) $current['logo_height_tablet'] : 0
		);
		$opts['logo_height_auto_tablet'] = $tablet_h['auto'];
		$opts['logo_height_tablet']      = $tablet_h['height'];

		$mobile_h = $this->sanitize_logo_height(
			$input,
			'hfb_header_logo_height_auto_mobile',
			'hfb_header_logo_height_mobile',
			isset( $current['logo_height_mobile'] ) ? (int) $current['logo_height_mobile'] : 0
		);
		$opts['logo_height_auto_mobile'] = $mobile_h['auto'];
		$opts['logo_height_mobile']      = $mobile_h['height'];

		$opts['bg_color']             = $this->sanitize_color_field( $input, 'hfb_header_bg_color', $current['bg_color'] );
		$opts['icon_color']           = $this->sanitize_color_field( $input, 'hfb_header_icon_color', $current['icon_color'] );
		$opts['hamburger_icon_color'] = $this->sanitize_color_field( $input, 'hfb_header_hamburger_icon_color', $current['hamburger_icon_color'] );

		$social = $this->sanitize_social_input( $input, $current, 'hfb_header_' );

		return array_merge( $opts, $social );
	}

	/**
	 * Footer girdisini temizler.
	 *
	 * @param array<string,mixed> $input   Ham girdi (POST alan adlarıyla).
	 * @param array<string,mixed> $current Mevcut ayarlar.
	 * @return array<string,mixed>
	 */
	public function sanitize_footer_input( $input, $current ) {
		$opts = $current;

		if ( isset( $input['hfb_footer_logo'] ) ) {
			$opts['logo'] = absint( $input['hfb_footer_logo'] );
		}

		if ( isset( $input['hfb_footer_brand_line1'] ) ) {
			$opts['brand_line1'] = sanitize_text_field( (string) $input['hfb_footer_brand_line1'] );
		}

		if ( isset( $input['hfb_footer_brand_line2'] ) ) {
			$opts['brand_line2'] = sanitize_text_field( (string) $input['hfb_footer_brand_line2'] );
		}

		if ( isset( $input['hfb_footer_description'] ) ) {
			$opts['description'] = sanitize_textarea_field( (string) $input['hfb_footer_description'] );
		}

		if ( isset( $input['hfb_footer_phone'] ) ) {
			$opts['phone'] = sanitize_text_field( (string) $input['hfb_footer_phone'] );
		}

		if ( isset( $input['hfb_footer_email'] ) ) {
			$opts['email'] = sanitize_email( (string) $input['hfb_footer_email'] );
		}

		if ( isset( $input['hfb_footer_copyright'] ) ) {
			$opts['copyright'] = sanitize_text_field( (string) $input['hfb_footer_copyright'] );
		}

		if ( isset( $input['hfb_footer_menu_id'] ) ) {
			$opts['menu_id'] = absint( $input['hfb_footer_menu_id'] );
		}

		$social = $this->sanitize_social_input( $input, $current, 'hfb_' );

		return array_merge( $opts, $social );
	}

	/**
	 * Hamburger panel girdisini temizler.
	 *
	 * @param array<string,mixed> $input   Ham girdi (POST alan adlarıyla).
	 * @param array<string,mixed> $current Mevcut ayarlar.
	 * @return array<string,mixed>
	 */
	public function sanitize_hamburger_input( $input, $current ) {
		$opts = $current;
		$keys = array_keys( $this->hamburger_block_types() );

		$opts['close_icon_color'] = $this->sanitize_color_field( $input, 'hfb_hamburger_close_icon_color', $current['close_icon_color'] );
		$opts['panel_bg_color']   = $this->sanitize_color_field( $input, 'hfb_hamburger_panel_bg_color', $current['panel_bg_color'] );
		$opts['font_color']       = $this->sanitize_color_field( $input, 'hfb_hamburger_font_color', $current['font_color'] );

		$opts['block_logo']   = $this->sanitize_checkbox( $input, 'hfb_hamburger_block_logo' );
		$opts['block_menu']   = $this->sanitize_checkbox( $input, 'hfb_hamburger_block_menu' );
		$opts['block_social'] = $this->sanitize_checkbox( $input, 'hfb_hamburger_block_social' );
		$opts['block_text']   = $this->sanitize_checkbox( $input, 'hfb_hamburger_block_text' );

		if ( isset( $input['hfb_hamburger_text'] ) ) {
			$opts['text'] = wp_kses_post( (string) $input['hfb_hamburger_text'] );
		}

		$opts['block_order'] = $this->sanitize_block_order(
			isset( $input['hfb_hamburger_block_order'] ) ? $input['hfb_hamburger_block_order'] : $current['block_order'],
			$keys
		);

		if ( isset( $input['hfb_hamburger_font_family'] ) ) {
			$family = (string) $input['hfb_hamburger_font_family'];
			$opts['font_family'] = array_key_exists( $family, $this->font_catalog() )
				? $family
				: $this->hamburger_defaults['font_family'];
		}

		if ( isset( $input['hfb_hamburger_font_size_desktop'] ) ) {
			$opts['font_size_desktop'] = $this->sanitize_int_range(
				$input['hfb_hamburger_font_size_desktop'],
				self::FONT_SIZE_MIN,
				self::FONT_SIZE_MAX,
				$this->hamburger_defaults['font_size_desktop']
			);
		}

		if ( isset( $input['hfb_hamburger_font_size_mobile'] ) ) {
			$opts['font_size_mobile'] = $this->sanitize_int_range(
				$input['hfb_hamburger_font_size_mobile'],
				self::FONT_SIZE_MOBILE_MIN,
				self::FONT_SIZE_MOBILE_MAX,
				$this->hamburger_defaults['font_size_mobile']
			);
		}

		if ( isset( $input['hfb_hamburger_font_weight_desktop'] ) ) {
			$opts['font_weight_desktop'] = $this->sanitize_font_weight(
				$input['hfb_hamburger_font_weight_desktop'],
				$this->hamburger_defaults['font_weight_desktop']
			);
		}

		if ( isset( $input['hfb_hamburger_font_weight_mobile'] ) ) {
			$opts['font_weight_mobile'] = $this->sanitize_font_weight(
				$input['hfb_hamburger_font_weight_mobile'],
				$this->hamburger_defaults['font_weight_mobile']
			);
		}

		if ( isset( $input['hfb_hamburger_font_align_desktop'] ) ) {
			$opts['font_align_desktop'] = $this->sanitize_align(
				$input['hfb_hamburger_font_align_desktop'],
				$this->hamburger_defaults['font_align_desktop']
			);
		}

		if ( isset( $input['hfb_hamburger_font_align_mobile'] ) ) {
			$opts['font_align_mobile'] = $this->sanitize_align(
				$input['hfb_hamburger_font_align_mobile'],
				$this->hamburger_defaults['font_align_mobile']
			);
		}

		return $opts;
	}

	/**
	 * Blok sırasını beyaz listeye indirger; eksik tipleri sona ekler.
	 *
	 * @param mixed    $raw  Virgülle ayrılmış dize veya dizi.
	 * @param string[] $keys Geçerli blok anahtarları.
	 * @return string[]
	 */
	private function sanitize_block_order( $raw, $keys ) {
		if ( is_string( $raw ) ) {
			$raw = preg_split( '/\s*,\s*/', $raw );
		}

		if ( ! is_array( $raw ) ) {
			$raw = $keys;
		}

		$ordered = array();

		foreach ( $raw as $item ) {
			$key = sanitize_key( (string) $item );
			if ( in_array( $key, $keys, true ) && ! in_array( $key, $ordered, true ) ) {
				$ordered[] = $key;
			}
		}

		foreach ( $keys as $key ) {
			if ( ! in_array( $key, $ordered, true ) ) {
				$ordered[] = $key;
			}
		}

		return $ordered;
	}

	/**
	 * Tek formdan gelen header + footer + hamburger + dil ayarlarını kaydeder.
	 *
	 * Ayarlar sayfası tek form / tek "Kaydet" düğmesidir; sekmeler ve
	 * adımlar yalnızca görsel gruplamadır. Bu yüzden kayıt da tek
	 * noktadan yapılır.
	 *
	 * @param array<string,mixed> $input Ham girdi.
	 * @return void
	 */
	public function save_settings( $input ) {
		update_option( $this->header_option, $this->sanitize_header_input( $input, $this->get_header_options() ) );
		update_option( $this->footer_option, $this->sanitize_footer_input( $input, $this->get_footer_options() ) );
		update_option( $this->hamburger_option, $this->sanitize_hamburger_input( $input, $this->get_hamburger_options() ) );
	}

	/**
	 * WordPress menü listesi.
	 *
	 * @return array<int,string>
	 */
	public function get_nav_menus() {
		$menus = wp_get_nav_menus();
		$list  = array( 0 => __( '— Menü seçin —', 'qrms' ) );

		if ( is_array( $menus ) ) {
			foreach ( $menus as $menu ) {
				$list[ (int) $menu->term_id ] = $menu->name;
			}
		}

		return $list;
	}
}
