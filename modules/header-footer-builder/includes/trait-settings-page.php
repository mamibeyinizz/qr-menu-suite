<?php
/**
 * Header Footer Builder — ayar şeması, temizleme ve kayıt.
 *
 * Tasarım seçenekleri (varyant, renk, gradient, gölge, tipografi) bilinçli
 * olarak YOKTUR: header ve footer, projenin dark-gold kimliğine sabitlenmiş
 * tek bir tasarımla render edilir. Burada yalnızca içerik alanları ve
 * davranış anahtarları tutulur.
 *
 * Hem form kaydı hem canlı önizleme AYNI temizleyicileri kullanır
 * (sanitize_header_input / sanitize_footer_input). Böylece önizlemede
 * görünen çıktı ile kaydedilen çıktı birbirinden ayrışamaz.
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
	 * Depodaki değerleri varsayılanlarla birleştirir ve ARTIK TANIMLI
	 * OLMAYAN anahtarları atar.
	 *
	 * Bu sürümde tasarım ayarları (varyant, renkler, gradient, tipografi)
	 * kaldırıldı. Eski kurulumların option'ında bu anahtarlar duruyor;
	 * budama olmadan hem okunmaya devam eder hem de her kayıtta geri
	 * yazılırlardı.
	 *
	 * @param mixed               $stored   Depodaki değer.
	 * @param array<string,mixed> $defaults Varsayılanlar.
	 * @return array<string,mixed>
	 */
	private function merge_options( $stored, $defaults ) {
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_intersect_key( wp_parse_args( $stored, $defaults ), $defaults );
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

		$opts['sticky']    = $this->sanitize_checkbox( $input, 'hfb_header_sticky' );
		$opts['lang_show'] = $this->sanitize_checkbox( $input, 'hfb_lang_show' );

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
	 * Tek formdan gelen header + footer + dil ayarlarını kaydeder.
	 *
	 * Ayarlar sayfası tek form / tek "Kaydet" düğmesidir; sekmeler yalnızca
	 * görsel gruplamadır. Bu yüzden kayıt da tek noktadan yapılır.
	 *
	 * @param array<string,mixed> $input Ham girdi.
	 * @return void
	 */
	public function save_settings( $input ) {
		update_option( $this->header_option, $this->sanitize_header_input( $input, $this->get_header_options() ) );
		update_option( $this->footer_option, $this->sanitize_footer_input( $input, $this->get_footer_options() ) );
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
