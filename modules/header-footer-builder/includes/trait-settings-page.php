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
		$stored = get_option( $this->hamburger_option, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		if ( ! isset( $stored['blocks'] ) || ! is_array( $stored['blocks'] ) || empty( $stored['blocks'] ) ) {
			if ( isset( $stored['block_order'] ) || isset( $stored['block_logo'] ) || isset( $stored['block_menu'] ) || isset( $stored['block_social'] ) || isset( $stored['block_text'] ) || isset( $stored['text'] ) ) {
				$stored['blocks'] = $this->migrate_hamburger_blocks( $stored );
			}
		}

		$merged = $this->merge_options( $stored, $this->hamburger_defaults );

		if ( isset( $merged['blocks'] ) && is_array( $merged['blocks'] ) ) {
			$merged['blocks'] = $this->normalize_hamburger_blocks( $merged['blocks'] );
		}

		return $merged;
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
		$types = array(
			'logo'   => __( 'Logo', 'qrms' ),
			'menu'   => __( 'Menü', 'qrms' ),
			'social' => __( 'Sosyal medya', 'qrms' ),
			'text'   => __( 'Metin Kutusu', 'qrms' ),
			'button' => __( 'Buton', 'qrms' ),
		);

		if ( $this->lang_switcher_available() ) {
			$types['lang'] = __( 'Dil Seçici', 'qrms' );
		}

		return $types;
	}

	/**
	 * Hamburger buton şekil seçenekleri.
	 *
	 * @return array<string,string>
	 */
	public function hamburger_button_shapes() {
		return array(
			'square'  => __( 'Köşeli', 'qrms' ),
			'rounded' => __( 'Yuvarlak köşe', 'qrms' ),
			'pill'    => __( 'Tam yuvarlak (pill)', 'qrms' ),
		);
	}

	/**
	 * Eski sabit blok formatını dinamik `blocks` dizisine dönüştürür.
	 *
	 * @param array<string,mixed> $stored Depodaki ham değer.
	 * @return array<int,array<string,mixed>>
	 */
	private function migrate_hamburger_blocks( $stored ) {
		$legacy_order = array( 'logo', 'menu', 'social', 'text' );

		if ( isset( $stored['block_order'] ) && is_array( $stored['block_order'] ) ) {
			$legacy_order = $this->sanitize_legacy_block_order( $stored['block_order'] );
		}

		$blocks     = array();
		$id_counter = 1;

		foreach ( $legacy_order as $type ) {
			$enabled_key = 'block_' . $type;
			$enabled     = isset( $stored[ $enabled_key ] ) ? (bool) $stored[ $enabled_key ] : true;

			if ( 'text' === $type && isset( $stored['block_text'] ) ) {
				$enabled = (bool) $stored['block_text'];
			}

			$block = array(
				'id'      => 'blk_' . $id_counter,
				'type'    => $type,
				'enabled' => $enabled,
				'align'   => 'center',
			);

			if ( 'text' === $type && isset( $stored['text'] ) ) {
				$block['content'] = (string) $stored['text'];
			}

			$blocks[] = $block;
			++$id_counter;
		}

		if ( empty( $blocks ) ) {
			return $this->hamburger_defaults['blocks'];
		}

		return $this->normalize_hamburger_blocks( $blocks );
	}

	/**
	 * Blok dizisini şemaya uygun hâle getirir.
	 *
	 * @param array<int,mixed> $blocks Ham blok listesi.
	 * @return array<int,array<string,mixed>>
	 */
	private function normalize_hamburger_blocks( $blocks ) {
		$valid_types = array_keys( $this->hamburger_block_types() );
		$normalized  = array();
		$seen_ids    = array();

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$type = isset( $block['type'] ) ? sanitize_key( (string) $block['type'] ) : '';
			if ( ! in_array( $type, $valid_types, true ) ) {
				continue;
			}

			$id = isset( $block['id'] ) ? sanitize_key( (string) $block['id'] ) : '';
			if ( '' === $id || in_array( $id, $seen_ids, true ) ) {
				$id = $this->next_hamburger_block_id( $normalized );
			}

			$seen_ids[] = $id;
			$normalized[] = $this->default_hamburger_block( $type, $id, $block );
		}

		if ( empty( $normalized ) ) {
			return $this->hamburger_defaults['blocks'];
		}

		return $normalized;
	}

	/**
	 * Tek blok için varsayılan alanları birleştirir.
	 *
	 * @param string              $type    Blok tipi.
	 * @param string              $id      Blok kimliği.
	 * @param array<string,mixed> $current Mevcut değerler.
	 * @return array<string,mixed>
	 */
	private function default_hamburger_block( $type, $id, $current = array() ) {
		$block = array(
			'id'      => $id,
			'type'    => $type,
			'enabled' => ! empty( $current['enabled'] ),
			'align'   => $this->sanitize_align( isset( $current['align'] ) ? $current['align'] : 'center', 'center' ),
		);

		if ( 'text' === $type ) {
			$block['content'] = isset( $current['content'] ) ? (string) $current['content'] : '';
		}

		// Logo bloğunun altındaki tanıtım cümlesi (referans tasarımdaki
		// marka açıklaması). Boşken hiç basılmaz.
		if ( 'logo' === $type ) {
			$block['description'] = isset( $current['description'] ) ? (string) $current['description'] : '';
		}

		if ( 'button' === $type ) {
			$shapes = array_keys( $this->hamburger_button_shapes() );
			$shape  = isset( $current['shape'] ) ? sanitize_key( (string) $current['shape'] ) : 'pill';

			$block['label']       = isset( $current['label'] ) ? (string) $current['label'] : __( 'Buton', 'qrms' );
			$block['url']         = isset( $current['url'] ) ? (string) $current['url'] : '';
			$block['bg_color']    = isset( $current['bg_color'] ) ? (string) $current['bg_color'] : '#c9a84c';
			$block['text_color']  = isset( $current['text_color'] ) ? (string) $current['text_color'] : '#0a0a0c';
			$block['shape']       = in_array( $shape, $shapes, true ) ? $shape : 'pill';
			$block['font']        = isset( $current['font'] ) ? (string) $current['font'] : $this->hamburger_defaults['font_family'];
			$block['font_size']   = isset( $current['font_size'] ) ? (int) $current['font_size'] : 15;
			$block['font_weight'] = isset( $current['font_weight'] ) ? (int) $current['font_weight'] : 600;
			$block['full_width']  = ! empty( $current['full_width'] ) ? 1 : 0;
		}

		return $block;
	}

	/**
	 * Yeni blok kimliği üretir.
	 *
	 * @param array<int,array<string,mixed>> $blocks Mevcut bloklar.
	 * @return string
	 */
	private function next_hamburger_block_id( $blocks ) {
		$max = 0;

		foreach ( $blocks as $block ) {
			if ( ! isset( $block['id'] ) ) {
				continue;
			}

			if ( preg_match( '/^blk_(\d+)$/', (string) $block['id'], $matches ) ) {
				$max = max( $max, (int) $matches[1] );
			}
		}

		return 'blk_' . ( $max + 1 );
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

		$opts['content_full_width'] = $this->sanitize_checkbox( $input, 'hfb_header_content_full_width' );

		if ( isset( $input['hfb_header_content_width'] ) ) {
			$opts['content_width'] = $this->sanitize_int_range(
				$input['hfb_header_content_width'],
				self::CONTENT_WIDTH_MIN,
				self::CONTENT_WIDTH_MAX,
				$this->header_defaults['content_width']
			);
		}

		$paddings = array(
			'padding_x_desktop' => array( 'hfb_header_padding_x_desktop', self::PADDING_X_MIN, self::PADDING_X_MAX ),
			'padding_y_desktop' => array( 'hfb_header_padding_y_desktop', self::PADDING_Y_MIN, self::PADDING_Y_MAX ),
			'padding_x_mobile'  => array( 'hfb_header_padding_x_mobile', self::PADDING_X_MIN, self::PADDING_X_MOBILE_MAX ),
			'padding_y_mobile'  => array( 'hfb_header_padding_y_mobile', self::PADDING_Y_MIN, self::PADDING_Y_MOBILE_MAX ),
		);

		foreach ( $paddings as $key => $meta ) {
			list( $field, $min, $max ) = $meta;

			if ( ! isset( $input[ $field ] ) ) {
				continue;
			}

			$opts[ $key ] = $this->sanitize_int_range(
				$input[ $field ],
				$min,
				$max,
				$this->header_defaults[ $key ]
			);
		}

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

		$opts['close_icon_color'] = $this->sanitize_color_field( $input, 'hfb_hamburger_close_icon_color', $current['close_icon_color'] );
		$opts['panel_bg_color']   = $this->sanitize_color_field( $input, 'hfb_hamburger_panel_bg_color', $current['panel_bg_color'] );
		$opts['font_color']       = $this->sanitize_color_field( $input, 'hfb_hamburger_font_color', $current['font_color'] );

		$opts['blocks'] = $this->sanitize_hamburger_blocks(
			$input,
			isset( $current['blocks'] ) && is_array( $current['blocks'] ) ? $current['blocks'] : $this->hamburger_defaults['blocks']
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
	 * Dinamik hamburger blok listesini temizler.
	 *
	 * @param array<string,mixed>              $input   Ham girdi.
	 * @param array<int,array<string,mixed>> $current Mevcut bloklar.
	 * @return array<int,array<string,mixed>>
	 */
	private function sanitize_hamburger_blocks( $input, $current ) {
		$valid_types = array_keys( $this->hamburger_block_types() );
		$raw_blocks  = $this->extract_hamburger_blocks_from_input( $input );

		$order_raw = isset( $input['hfb_hamburger_block_order'] )
			? $input['hfb_hamburger_block_order']
			: '';

		$order = $this->sanitize_hamburger_block_order_ids( $order_raw, array_keys( $raw_blocks ) );

		if ( empty( $raw_blocks ) && ! empty( $current ) ) {
			$raw_blocks = array();
			foreach ( $current as $block ) {
				if ( isset( $block['id'] ) ) {
					$raw_blocks[ (string) $block['id'] ] = $block;
				}
			}
			$order = wp_list_pluck( $current, 'id' );
		}

		$current_by_id = array();
		foreach ( $current as $block ) {
			if ( isset( $block['id'] ) ) {
				$current_by_id[ (string) $block['id'] ] = $block;
			}
		}

		$sanitized_by_id = array();

		foreach ( $raw_blocks as $raw_id => $raw_block ) {
			if ( ! is_array( $raw_block ) ) {
				continue;
			}

			$id   = sanitize_key( (string) $raw_id );
			$type = isset( $raw_block['type'] ) ? sanitize_key( (string) $raw_block['type'] ) : '';

			if ( '' === $id || ! in_array( $type, $valid_types, true ) ) {
				continue;
			}

			$fallback = isset( $current_by_id[ $id ] ) ? $current_by_id[ $id ] : array();
			$enabled  = isset( $raw_block['enabled'] ) && '' !== (string) $raw_block['enabled'] && '0' !== (string) $raw_block['enabled'];

			$block = array(
				'id'      => $id,
				'type'    => $type,
				'enabled' => $enabled,
				'align'   => $this->sanitize_align(
					isset( $raw_block['align'] ) ? $raw_block['align'] : ( isset( $fallback['align'] ) ? $fallback['align'] : 'center' ),
					'center'
				),
			);

			if ( 'text' === $type ) {
				if ( isset( $raw_block['content'] ) ) {
					$block['content'] = wp_kses_post( (string) $raw_block['content'] );
				} elseif ( isset( $fallback['content'] ) ) {
					$block['content'] = (string) $fallback['content'];
				} else {
					$block['content'] = '';
				}
			}

			if ( 'logo' === $type ) {
				if ( isset( $raw_block['description'] ) ) {
					$block['description'] = sanitize_textarea_field( (string) $raw_block['description'] );
				} elseif ( isset( $fallback['description'] ) ) {
					$block['description'] = (string) $fallback['description'];
				} else {
					$block['description'] = '';
				}
			}

			if ( 'button' === $type ) {
				$shapes = array_keys( $this->hamburger_button_shapes() );
				$shape  = isset( $raw_block['shape'] ) ? sanitize_key( (string) $raw_block['shape'] ) : 'pill';

				$block['label'] = isset( $raw_block['label'] )
					? sanitize_text_field( (string) $raw_block['label'] )
					: ( isset( $fallback['label'] ) ? sanitize_text_field( (string) $fallback['label'] ) : '' );

				$block['url'] = isset( $raw_block['url'] )
					? esc_url_raw( (string) $raw_block['url'] )
					: ( isset( $fallback['url'] ) ? esc_url_raw( (string) $fallback['url'] ) : '' );

				$block['bg_color'] = $this->sanitize_color_field(
					$raw_block,
					'bg_color',
					isset( $fallback['bg_color'] ) ? (string) $fallback['bg_color'] : '#c9a84c'
				);

				$block['text_color'] = $this->sanitize_color_field(
					$raw_block,
					'text_color',
					isset( $fallback['text_color'] ) ? (string) $fallback['text_color'] : '#0a0a0c'
				);

				$block['shape'] = in_array( $shape, $shapes, true ) ? $shape : 'pill';

				$font = isset( $raw_block['font'] ) ? (string) $raw_block['font'] : ( isset( $fallback['font'] ) ? (string) $fallback['font'] : $this->hamburger_defaults['font_family'] );
				$block['font'] = array_key_exists( $font, $this->font_catalog() ) ? $font : $this->hamburger_defaults['font_family'];

				$block['font_size'] = $this->sanitize_int_range(
					isset( $raw_block['font_size'] ) ? $raw_block['font_size'] : ( isset( $fallback['font_size'] ) ? $fallback['font_size'] : 15 ),
					10,
					32,
					15
				);

				$block['font_weight'] = $this->sanitize_font_weight(
					isset( $raw_block['font_weight'] ) ? $raw_block['font_weight'] : ( isset( $fallback['font_weight'] ) ? $fallback['font_weight'] : 600 ),
					600
				);

				$block['full_width'] = $this->sanitize_checkbox( $raw_block, 'full_width' );
			}

			$sanitized_by_id[ $id ] = $block;
		}

		$ordered = array();
		foreach ( $order as $id ) {
			if ( isset( $sanitized_by_id[ $id ] ) ) {
				$ordered[] = $sanitized_by_id[ $id ];
				unset( $sanitized_by_id[ $id ] );
			}
		}

		foreach ( $sanitized_by_id as $block ) {
			$ordered[] = $block;
		}

		if ( empty( $ordered ) ) {
			return $this->normalize_hamburger_blocks( $this->hamburger_defaults['blocks'] );
		}

		return $this->normalize_hamburger_blocks( $ordered );
	}

	/**
	 * Blok kimlik sırasını beyaz listeye indirger.
	 *
	 * @param mixed    $raw  Virgülle ayrılmış dize veya dizi.
	 * @param string[] $keys Geçerli blok kimlikleri.
	 * @return string[]
	 */
	private function sanitize_hamburger_block_order_ids( $raw, $keys ) {
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
	 * Form girdisinden hamburger blok alanlarını çıkarır.
	 *
	 * Normal POST iç içe dizileri doğrudan verir; AJAX önizleme ise
	 * `hfb_hamburger_blocks[blk_1][enabled]` biçiminde düz anahtarlar gönderir.
	 *
	 * @param array<string,mixed> $input Ham girdi.
	 * @return array<string,array<string,mixed>>
	 */
	private function extract_hamburger_blocks_from_input( $input ) {
		$blocks = array();

		if ( isset( $input['hfb_hamburger_blocks'] ) && is_array( $input['hfb_hamburger_blocks'] ) ) {
			foreach ( $input['hfb_hamburger_blocks'] as $id => $fields ) {
				if ( is_array( $fields ) ) {
					$blocks[ sanitize_key( (string) $id ) ] = $fields;
				}
			}
		}

		$pattern = '/^hfb_hamburger_blocks\[([^\]]+)\]\[([^\]]+)\]$/';

		foreach ( $input as $key => $value ) {
			if ( ! is_string( $key ) || ! preg_match( $pattern, $key, $matches ) ) {
				continue;
			}

			$id    = sanitize_key( $matches[1] );
			$field = sanitize_key( $matches[2] );

			if ( ! isset( $blocks[ $id ] ) || ! is_array( $blocks[ $id ] ) ) {
				$blocks[ $id ] = array();
			}

			$blocks[ $id ][ $field ] = $value;
		}

		return $blocks;
	}

	/**
	 * Eski sabit blok sırasını temizler (göç için).
	 *
	 * @param mixed $raw Ham sıra.
	 * @return string[]
	 */
	private function sanitize_legacy_block_order( $raw ) {
		$keys = array( 'logo', 'menu', 'social', 'text' );

		if ( is_string( $raw ) ) {
			$raw = preg_split( '/\s*,\s*/', $raw );
		}

		if ( ! is_array( $raw ) ) {
			return $keys;
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
