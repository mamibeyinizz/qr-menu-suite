<?php
/**
 * Header Footer Builder — ayar şeması ve kayıt.
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
		$stored = get_option( $this->header_option, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, $this->header_defaults );
	}

	/**
	 * Footer ayarlarını döndürür.
	 *
	 * @return array<string,mixed>
	 */
	public function get_footer_options() {
		$stored = get_option( $this->footer_option, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, $this->footer_defaults );
	}

	/**
	 * Header varyant seçenekleri.
	 *
	 * @return array<string,string>
	 */
	public function header_variants() {
		return array(
			'minimal-sticky' => __( 'Minimal Sticky', 'qrms' ),
			'glass-bento'    => __( 'Glass Bento', 'qrms' ),
			'kinetic-bold'   => __( 'Kinetic Bold', 'qrms' ),
		);
	}

	/**
	 * Footer varyant seçenekleri.
	 *
	 * @return array<string,string>
	 */
	public function footer_variants() {
		return array(
			'utility-minimal' => __( 'Utility Minimal', 'qrms' ),
			'bento-grid'      => __( 'Bento Grid Footer', 'qrms' ),
			'contact-first'   => __( 'Contact-First Footer', 'qrms' ),
		);
	}

	/**
	 * Google Fonts / sistem font listesi.
	 *
	 * @return string[]
	 */
	public function font_options() {
		return array(
			'Inter',
			'Poppins',
			'Roboto',
			'Open Sans',
			'Montserrat',
			'Playfair Display',
			'DM Sans',
			'Space Grotesk',
			'Georgia, serif',
			'system-ui, sans-serif',
		);
	}

	/**
	 * Google Fonts URL haritası.
	 *
	 * @return array<string,string>
	 */
	public function google_font_map() {
		return array(
			'Inter'             => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
			'Poppins'           => 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
			'Roboto'            => 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap',
			'Open Sans'         => 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap',
			'Montserrat'        => 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap',
			'Playfair Display'  => 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&display=swap',
			'DM Sans'           => 'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap',
			'Space Grotesk'     => 'https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap',
		);
	}

	/**
	 * Font için CSS font-family değeri.
	 *
	 * @param string $font Font adı.
	 * @return string
	 */
	public function font_family_css( $font ) {
		if ( 'Georgia, serif' === $font || 'system-ui, sans-serif' === $font ) {
			return $font;
		}

		return "'" . $font . "', sans-serif";
	}

	/**
	 * Google Font URL'i.
	 *
	 * @param string $font Font adı.
	 * @return string
	 */
	public function google_font_url( $font ) {
		$map = $this->google_font_map();

		return isset( $map[ $font ] ) ? $map[ $font ] : '';
	}

	/**
	 * Sosyal medya platform kataloğu.
	 *
	 * @return array<string,array{label:string,icon:string}>
	 */
	public function social_media_map() {
		return array(
			'instagram' => array( 'label' => 'Instagram', 'icon' => 'instagram' ),
			'facebook'  => array( 'label' => 'Facebook', 'icon' => 'facebook' ),
			'youtube'   => array( 'label' => 'YouTube', 'icon' => 'youtube' ),
			'x'         => array( 'label' => 'X (Twitter)', 'icon' => 'x' ),
			'tiktok'    => array( 'label' => 'TikTok', 'icon' => 'tiktok' ),
			'whatsapp'  => array( 'label' => 'WhatsApp', 'icon' => 'whatsapp' ),
			'linkedin'  => array( 'label' => 'LinkedIn', 'icon' => 'linkedin' ),
		);
	}

	/**
	 * Sosyal medya durumunu çözümler.
	 *
	 * @param array<string,mixed> $opts Footer ayarları.
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
	 * Hamburger kapatma ikon seçenekleri.
	 *
	 * @return array<string,string>
	 */
	public function close_icon_options() {
		return array(
			'x'       => __( 'X (kapat)', 'qrms' ),
			'arrow'   => __( 'Ok', 'qrms' ),
			'chevron' => __( 'Chevron', 'qrms' ),
		);
	}

	/**
	 * Header ayarlarını kaydeder.
	 *
	 * @return void
	 */
	public function save_header_settings() {
		$options = $this->get_header_options();

		$variant = isset( $_POST['hfb_header_variant'] ) ? sanitize_key( wp_unslash( $_POST['hfb_header_variant'] ) ) : $options['variant'];
		$options['variant'] = array_key_exists( $variant, $this->header_variants() ) ? $variant : $options['variant'];

		$options['logo']           = isset( $_POST['hfb_header_logo'] ) ? absint( $_POST['hfb_header_logo'] ) : $options['logo'];
		$options['logo_width']     = isset( $_POST['hfb_header_logo_width'] ) ? max( 60, min( 320, absint( $_POST['hfb_header_logo_width'] ) ) ) : $options['logo_width'];
		$options['menu_id']        = isset( $_POST['hfb_header_menu_id'] ) ? absint( $_POST['hfb_header_menu_id'] ) : $options['menu_id'];

		$align = isset( $_POST['hfb_header_logo_alignment'] ) ? sanitize_key( wp_unslash( $_POST['hfb_header_logo_alignment'] ) ) : $options['logo_alignment'];
		$options['logo_alignment'] = in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : $options['logo_alignment'];

		$options['bg_color']     = isset( $_POST['hfb_header_bg_color'] ) ? ( sanitize_hex_color( wp_unslash( $_POST['hfb_header_bg_color'] ) ) ?: $options['bg_color'] ) : $options['bg_color'];
		$options['text_color']   = isset( $_POST['hfb_header_text_color'] ) ? ( sanitize_hex_color( wp_unslash( $_POST['hfb_header_text_color'] ) ) ?: $options['text_color'] ) : $options['text_color'];
		$options['border_color'] = isset( $_POST['hfb_header_border_color'] ) ? ( sanitize_hex_color( wp_unslash( $_POST['hfb_header_border_color'] ) ) ?: $options['border_color'] ) : $options['border_color'];
		$options['sticky']       = isset( $_POST['hfb_header_sticky'] ) ? 1 : 0;

		$panel = isset( $_POST['hfb_mobile_panel_style'] ) ? sanitize_key( wp_unslash( $_POST['hfb_mobile_panel_style'] ) ) : $options['mobile_panel_style'];
		$options['mobile_panel_style'] = in_array( $panel, array( 'slide', 'fullscreen' ), true ) ? $panel : $options['mobile_panel_style'];

		$options['mobile_panel_bg']         = isset( $_POST['hfb_mobile_panel_bg'] ) ? ( sanitize_hex_color( wp_unslash( $_POST['hfb_mobile_panel_bg'] ) ) ?: $options['mobile_panel_bg'] ) : $options['mobile_panel_bg'];
		$options['mobile_panel_bg_opacity'] = isset( $_POST['hfb_mobile_panel_bg_opacity'] ) ? max( 0, min( 100, absint( $_POST['hfb_mobile_panel_bg_opacity'] ) ) ) : $options['mobile_panel_bg_opacity'];

		$font = isset( $_POST['hfb_mobile_panel_font'] ) ? sanitize_text_field( wp_unslash( $_POST['hfb_mobile_panel_font'] ) ) : $options['mobile_panel_font'];
		$options['mobile_panel_font'] = in_array( $font, $this->font_options(), true ) ? $font : $options['mobile_panel_font'];

		$options['mobile_panel_text_color'] = isset( $_POST['hfb_mobile_panel_text_color'] ) ? ( sanitize_hex_color( wp_unslash( $_POST['hfb_mobile_panel_text_color'] ) ) ?: $options['mobile_panel_text_color'] ) : $options['mobile_panel_text_color'];
		$options['mobile_panel_text_size']  = isset( $_POST['hfb_mobile_panel_text_size'] ) ? max( 14, min( 28, absint( $_POST['hfb_mobile_panel_text_size'] ) ) ) : $options['mobile_panel_text_size'];

		$icon = isset( $_POST['hfb_mobile_close_icon'] ) ? sanitize_key( wp_unslash( $_POST['hfb_mobile_close_icon'] ) ) : $options['mobile_close_icon'];
		$options['mobile_close_icon'] = array_key_exists( $icon, $this->close_icon_options() ) ? $icon : $options['mobile_close_icon'];

		$options['mobile_close_icon_color'] = isset( $_POST['hfb_mobile_close_icon_color'] ) ? ( sanitize_hex_color( wp_unslash( $_POST['hfb_mobile_close_icon_color'] ) ) ?: $options['mobile_close_icon_color'] ) : $options['mobile_close_icon_color'];
		$options['mobile_close_icon_size']  = isset( $_POST['hfb_mobile_close_icon_size'] ) ? max( 16, min( 40, absint( $_POST['hfb_mobile_close_icon_size'] ) ) ) : $options['mobile_close_icon_size'];
		$options['hamburger_color']         = isset( $_POST['hfb_hamburger_color'] ) ? ( sanitize_hex_color( wp_unslash( $_POST['hfb_hamburger_color'] ) ) ?: $options['hamburger_color'] ) : $options['hamburger_color'];

		update_option( $this->header_option, $options );
	}

	/**
	 * Footer ayarlarını kaydeder.
	 *
	 * @return void
	 */
	public function save_footer_settings() {
		$options = $this->get_footer_options();

		$variant = isset( $_POST['hfb_footer_variant'] ) ? sanitize_key( wp_unslash( $_POST['hfb_footer_variant'] ) ) : $options['variant'];
		$options['variant'] = array_key_exists( $variant, $this->footer_variants() ) ? $variant : $options['variant'];

		$options['logo']        = isset( $_POST['hfb_footer_logo'] ) ? absint( $_POST['hfb_footer_logo'] ) : $options['logo'];
		$options['description']   = isset( $_POST['hfb_footer_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['hfb_footer_description'] ) ) : $options['description'];
		$options['phone']       = isset( $_POST['hfb_footer_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['hfb_footer_phone'] ) ) : $options['phone'];
		$options['email']       = isset( $_POST['hfb_footer_email'] ) ? sanitize_email( wp_unslash( $_POST['hfb_footer_email'] ) ) : $options['email'];
		$options['copyright']   = isset( $_POST['hfb_footer_copyright'] ) ? sanitize_text_field( wp_unslash( $_POST['hfb_footer_copyright'] ) ) : $options['copyright'];
		$options['menu_id']     = isset( $_POST['hfb_footer_menu_id'] ) ? absint( $_POST['hfb_footer_menu_id'] ) : $options['menu_id'];

		$valid_social = array_keys( $this->social_media_map() );
		$active       = array();

		if ( isset( $_POST['hfb_social_media_active'] ) && is_array( $_POST['hfb_social_media_active'] ) ) {
			foreach ( wp_unslash( $_POST['hfb_social_media_active'] ) as $raw_key ) {
				$key = sanitize_key( $raw_key );
				if ( in_array( $key, $valid_social, true ) && ! in_array( $key, $active, true ) ) {
					$active[] = $key;
				}
			}
		}

		$options['social_media_active'] = array_slice( $active, 0, 6 );

		if ( ! isset( $options['social_media'] ) || ! is_array( $options['social_media'] ) ) {
			$options['social_media'] = array();
		}

		foreach ( $valid_social as $key ) {
			if ( isset( $_POST[ 'hfb_social_media_url_' . $key ] ) ) {
				$options['social_media'][ $key ] = esc_url_raw( wp_unslash( $_POST[ 'hfb_social_media_url_' . $key ] ) );
			}
		}

		update_option( $this->footer_option, $options );
	}

	/**
	 * WordPress menü listesi.
	 *
	 * @return array<int,string>
	 */
	public function get_nav_menus() {
		$menus = wp_get_nav_menus();
		$list  = array( 0 => __( '— Menü seçin —', 'qrms' ) );

		foreach ( $menus as $menu ) {
			$list[ (int) $menu->term_id ] = $menu->name;
		}

		return $list;
	}
}
