<?php
/**
 * Header Footer Builder — ön yüz render ve varlıklar.
 *
 * Varsayılan palet dark-gold'dur; kullanıcı ayarları `--hfb-*` CSS
 * değişkenleri olarak sarmalayıcıya yazılır. Markup tek kalır, görünüm
 * option'lardan gelir.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

trait QRMS_HFB_Frontend {

	/**
	 * Header kısa kodu.
	 *
	 * @param array<string,string> $atts Nitelikler.
	 * @return string
	 */
	public function shortcode_header( $atts = array() ) {
		unset( $atts );

		if ( ! $this->should_render( 'header' ) ) {
			return '';
		}

		$opts = $this->get_header_options();
		$this->enqueue_frontend_styles();
		$this->enqueue_header_script();
		$this->mark_rendered( 'header' );

		return $this->render_header( $opts, $this->get_hamburger_options() );
	}

	/**
	 * Footer kısa kodu.
	 *
	 * @param array<string,string> $atts Nitelikler.
	 * @return string
	 */
	public function shortcode_footer( $atts = array() ) {
		unset( $atts );

		if ( ! $this->should_render( 'footer' ) ) {
			return '';
		}

		$opts = $this->get_footer_options();
		$this->enqueue_frontend_styles();
		$this->mark_rendered( 'footer' );

		return $this->render_footer( $opts );
	}

	/**
	 * Ön yüz varlıklarını gerektiğinde yükler.
	 *
	 * @return void
	 */
	public function maybe_enqueue_frontend_assets() {
		$needs_header = $this->page_has_hfb_shortcode( 'hfb_header' );
		$needs_footer = $this->page_has_hfb_shortcode( 'hfb_footer' );

		if ( ! $needs_header && ! $needs_footer ) {
			return;
		}

		$this->enqueue_frontend_styles();

		if ( $needs_header ) {
			$this->enqueue_header_script();
		}
	}

	/**
	 * İçerik veya Elementor verisinde kısa kod arar.
	 *
	 * @param string $tag Kısa kod etiketi.
	 * @return bool
	 */
	private function page_has_hfb_shortcode( $tag ) {
		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post();
		if ( ! $post ) {
			return false;
		}

		if ( has_shortcode( $post->post_content, $tag ) ) {
			return true;
		}

		if ( $this->elementor_loaded() ) {
			$data = get_post_meta( $post->ID, '_elementor_data', true );
			if ( is_string( $data ) && false !== strpos( $data, $tag ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Ortak ön yüz stili (tek dosya).
	 *
	 * Tüm kurallar `.hfb-header-wrap` / `.hfb-footer-wrap` altına
	 * kapsanmıştır; Elementor'un kendi stilleriyle seçici çakışmaz.
	 *
	 * @return void
	 */
	public function enqueue_frontend_styles() {
		$base = 'modules/header-footer-builder/assets/';

		wp_enqueue_style(
			'hfb-frontend',
			QRMS_PLUGIN_URL . $base . 'css/frontend.css',
			array(),
			QRMS_Helpers::asset_version( $base . 'css/frontend.css' )
		);

		wp_enqueue_style(
			'hfb-fonts',
			$this->google_fonts_url(),
			array(),
			null
		);
	}

	/**
	 * Marka + hamburger blokları + footer'da seçilen Google Fonts adresi.
	 *
	 * @return string
	 */
	private function google_fonts_url() {
		$families = array( 'Playfair+Display:wght@400;500;600;700' );
		$catalog  = $this->font_catalog();
		$chosen   = $this->get_hamburger_options();
		$keys     = array();

		$panel_font = isset( $chosen['font_family'] ) ? (string) $chosen['font_family'] : '';
		if ( '' !== $panel_font ) {
			$keys[] = $panel_font;
		}

		if ( isset( $chosen['blocks'] ) && is_array( $chosen['blocks'] ) ) {
			foreach ( $chosen['blocks'] as $block ) {
				if ( ! is_array( $block ) || 'button' !== ( $block['type'] ?? '' ) || empty( $block['enabled'] ) ) {
					continue;
				}

				$btn_font = isset( $block['font'] ) ? (string) $block['font'] : '';
				if ( '' !== $btn_font ) {
					$keys[] = $btn_font;
				}
			}
		}

		$footer = $this->get_footer_options();
		foreach ( array(
			'brand_font_family',
			'links_title_font_family',
			'links_item_font_family',
			'hours_title_font_family',
			'hours_item_font_family',
			'contact_title_font_family',
			'contact_item_font_family',
			'btn_font_family',
		) as $field ) {
			if ( ! empty( $footer[ $field ] ) ) {
				$keys[] = (string) $footer[ $field ];
			}
		}

		$keys = array_unique( array_filter( $keys ) );

		// Yönetimde canlı önizleme kaydetmeden font değiştirir; katalogdaki
		// Google yüzleri peşinen yüklenir ki --hfb-btn-font anında görünsün.
		if ( is_admin() ) {
			foreach ( array_keys( $catalog ) as $key ) {
				$keys[] = $key;
			}
			$keys = array_unique( array_filter( $keys ) );
		}

		foreach ( $keys as $key ) {
			if ( 'Playfair Display' === $key ) {
				continue;
			}

			if ( isset( $catalog[ $key ] ) && '' !== $catalog[ $key ]['google'] ) {
				$families[] = $catalog[ $key ]['google'];
			}
		}

		return 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', array_unique( $families ) ) . '&display=swap';
	}

	/**
	 * Header davranış script'i.
	 *
	 * @return void
	 */
	public function enqueue_header_script() {
		$base = 'modules/header-footer-builder/assets/';

		wp_enqueue_script(
			'hfb-frontend',
			QRMS_PLUGIN_URL . $base . 'js/frontend.js',
			array(),
			QRMS_Helpers::asset_version( $base . 'js/frontend.js' ),
			true
		);
	}

	/**
	 * Header HTML çıktısı.
	 *
	 * @param array<string,mixed>      $opts      Header ayarları.
	 * @param array<string,mixed>|null $hamburger Hamburger ayarları; null ise depodan okunur.
	 * @return string
	 */
	public function render_header( $opts, $hamburger = null ) {
		if ( null === $hamburger ) {
			$hamburger = $this->get_hamburger_options();
		}

		$brand = $this->render_brand( $opts, 'header' );

		// Menü bir kez üretilir, iki kez basılır (masaüstü + mobil panel).
		// wp_nav_menu her <li>'ye `id` verdiği için kopyaların id'leri ayrı
		// bir ön ekle taşınır; aksi hâlde sayfada çift id oluşurdu.
		$nav_raw   = $this->render_nav_menu( (int) $opts['menu_id'], 'hfb-header__menu' );
		$nav       = $this->scope_nav_ids( $nav_raw, 'hfb-h-' );
		$panel_nav = $this->scope_nav_ids( $nav_raw, 'hfb-m-' );
		$social    = $this->render_social_icons( $opts );
		$lang      = $this->render_lang_switcher( $opts );

		$classes = 'hfb-header';
		if ( ! empty( $opts['sticky'] ) ) {
			$classes .= ' hfb-header--sticky';
		}
		if ( ! empty( $opts['sticky'] ) && ! empty( $opts['sticky_blur'] ) ) {
			$classes .= ' hfb-header--sticky-blur';
		}
		if ( $this->elementor_is_edit_mode() ) {
			$classes .= ' hfb-header--editor';
		}

		$style = $this->header_css_vars( $opts, $hamburger );

		ob_start();
		?>
		<div class="hfb-header-wrap" data-hfb="header"<?php echo $style ? ' style="' . esc_attr( $style ) . '"' : ''; ?>>
			<header class="<?php echo esc_attr( $classes ); ?>" role="banner">
				<div class="hfb-header__inner">
					<div class="hfb-header__brand"><?php echo $brand; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>

					<?php if ( $nav ) : ?>
						<nav class="hfb-header__nav" aria-label="<?php echo esc_attr( $this->hfb_cevir_ui( __( 'Ana menü', 'qrms' ) ) ); ?>">
							<?php echo $nav; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</nav>
					<?php endif; ?>

					<div class="hfb-header__actions">
						<?php echo $social; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $lang; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>

					<button type="button" class="hfb-header__toggle" aria-expanded="false" aria-controls="hfb-mobile-panel" aria-label="<?php echo esc_attr( $this->hfb_cevir_ui( __( 'Menüyü aç', 'qrms' ) ) ); ?>">
						<span class="hfb-header__toggle-bar"></span>
						<span class="hfb-header__toggle-bar"></span>
						<span class="hfb-header__toggle-bar"></span>
					</button>
				</div>
			</header>
			<?php echo $this->render_mobile_panel( $opts, $hamburger, $panel_nav, $brand, $social, $lang ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Header sarmalayıcısına yazılacak `--hfb-*` değişkenleri.
	 *
	 * @param array<string,mixed> $opts      Header ayarları.
	 * @param array<string,mixed> $hamburger Hamburger ayarları.
	 * @return string
	 */
	public function header_css_vars( $opts, $hamburger ) {
		$logo_h_desktop = ! empty( $opts['logo_height_auto_desktop'] ) ? 'auto' : (int) $opts['logo_height_desktop'] . 'px';
		$logo_h_tablet  = ! empty( $opts['logo_height_auto_tablet'] ) ? 'auto' : (int) $opts['logo_height_tablet'] . 'px';
		$logo_h_mobile  = ! empty( $opts['logo_height_auto_mobile'] ) ? 'auto' : (int) $opts['logo_height_mobile'] . 'px';

		// Tam genişlik seçiliyken `none` basılır: kural sabit bir piksel
		// değerine değil, her zaman değişkene bakar.
		$max_width = ! empty( $opts['content_full_width'] )
			? 'none'
			: (int) $opts['content_width'] . 'px';

		$vars = array(
			'--hfb-header-max-width'       => $max_width,
			'--hfb-header-padding-x'       => (int) $opts['padding_x_desktop'] . 'px',
			'--hfb-header-padding-y'       => (int) $opts['padding_y_desktop'] . 'px',
			'--hfb-header-padding-x-m'     => (int) $opts['padding_x_mobile'] . 'px',
			'--hfb-header-padding-y-m'     => (int) $opts['padding_y_mobile'] . 'px',
			'--hfb-header-bg'              => (string) $opts['bg_color'],
			'--hfb-icon-color'             => (string) $opts['icon_color'],
			'--hfb-hamburger-icon'         => (string) $opts['hamburger_icon_color'],
			'--hfb-logo-w-desktop'         => (int) $opts['logo_width_desktop'] . 'px',
			'--hfb-logo-h-desktop'         => $logo_h_desktop,
			'--hfb-logo-w-tablet'          => (int) $opts['logo_width_tablet'] . 'px',
			'--hfb-logo-h-tablet'          => $logo_h_tablet,
			'--hfb-logo-w-mobile'          => (int) $opts['logo_width_mobile'] . 'px',
			'--hfb-logo-h-mobile'          => $logo_h_mobile,
			'--hfb-panel-bg'               => (string) $hamburger['panel_bg_color'],
			'--hfb-close-color'            => (string) $hamburger['close_icon_color'],
			'--hfb-panel-font'             => $this->font_stack( $hamburger['font_family'] ),
			'--hfb-panel-font-color'       => (string) $hamburger['font_color'],
			// Panel yalnızca mobilde açılır: tek yazı seti, kırılım yok.
			'--hfb-panel-font-size'        => (int) $hamburger['font_size'] . 'px',
			'--hfb-panel-font-weight'      => (string) (int) $hamburger['font_weight'],
			'--hfb-panel-font-align'       => (string) $hamburger['font_align'],
		);

		return $this->css_vars_string( array_merge( $vars, $this->panel_appearance_css_vars( $hamburger ) ) );
	}

	/**
	 * Hamburger "Görünüm" adımının `--hfb-panel-*` değişkenleri.
	 *
	 * Boş dönen değer `css_vars_string()` tarafından atlanır; kural o zaman
	 * CSS'teki kendi fallback'ine düşer. Sosyal ikon zemini bu yolla
	 * varsayılan olarak şeffaf kalır.
	 *
	 * @param array<string,mixed> $hamburger Hamburger ayarları.
	 * @return array<string,string>
	 */
	private function panel_appearance_css_vars( $hamburger ) {
		// Panel logosu tek settir; kırılıma göre ikinci bir ölçü yoktur.
		$logo_h = ! empty( $hamburger['logo_height_auto'] ) ? 'auto' : (int) $hamburger['logo_height'] . 'px';

		$vars = array(
			'--hfb-panel-logo-w'        => (int) $hamburger['logo_width'] . 'px',
			'--hfb-panel-logo-h'        => $logo_h,
			'--hfb-panel-menu-color'    => (string) $hamburger['menu_link_color'],
			'--hfb-panel-menu-hover'    => (string) $hamburger['menu_hover_color'],
			'--hfb-panel-menu-divider'  => (string) $hamburger['menu_divider_color'],
			'--hfb-panel-menu-arrow'    => (string) $hamburger['menu_arrow_color'],
			'--hfb-panel-social-border' => (string) $hamburger['social_border_color'],
			'--hfb-panel-social-bg'     => (string) $hamburger['social_bg_color'],
			'--hfb-panel-social-icon'   => (string) $hamburger['social_icon_color'],
			'--hfb-panel-btn-bg'        => (string) $hamburger['btn_bg_color'],
			'--hfb-panel-btn-color'     => (string) $hamburger['btn_text_color'],
			'--hfb-panel-btn-radius'    => $this->button_radius( (string) $hamburger['btn_shape'] ),
			'--hfb-panel-btn-font'      => $this->font_stack( (string) $hamburger['btn_font_family'] ),
			'--hfb-panel-btn-size'      => (int) $hamburger['btn_font_size'] . 'px',
			'--hfb-panel-btn-weight'    => (string) (int) $hamburger['btn_font_weight'],
		);

		$bg_image = $this->panel_bg_image_url( $hamburger );

		if ( '' !== $bg_image ) {
			$vars['--hfb-panel-bg-image']   = 'url(' . $bg_image . ')';
			$vars['--hfb-panel-bg-opacity'] = (string) round( $this->panel_bg_opacity( $hamburger ), 2 );
		}

		return $vars;
	}

	/**
	 * Panel arka plan görselinin adresi (yoksa boş dize).
	 *
	 * @param array<string,mixed> $hamburger Hamburger ayarları.
	 * @return string
	 */
	private function panel_bg_image_url( $hamburger ) {
		$id = isset( $hamburger['panel_bg_image'] ) ? (int) $hamburger['panel_bg_image'] : 0;

		if ( $id <= 0 ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $id, 'full' );

		return $url ? esc_url_raw( $url ) : '';
	}

	/**
	 * Panel arka plan görselinin örtü opaklığı (0–1).
	 *
	 * @param array<string,mixed> $hamburger Hamburger ayarları.
	 * @return float
	 */
	private function panel_bg_opacity( $hamburger ) {
		$percent = isset( $hamburger['panel_bg_opacity'] ) ? (int) $hamburger['panel_bg_opacity'] : self::PANEL_BG_OPACITY_MAX;
		$percent = max( self::PANEL_BG_OPACITY_MIN, min( self::PANEL_BG_OPACITY_MAX, $percent ) );

		return $percent / 100;
	}

	/**
	 * CSS değişken dizisini satır içi style değerine çevirir.
	 *
	 * @param array<string,string> $vars Ad => değer.
	 * @return string
	 */
	private function css_vars_string( $vars ) {
		$parts = array();
		foreach ( $vars as $name => $value ) {
			$value = trim( (string) $value );
			if ( '' === $value ) {
				continue;
			}
			$parts[] = $name . ':' . $value;
		}

		return implode( ';', $parts );
	}

	/**
	 * Footer HTML çıktısı.
	 *
	 * Dört sütun: marka, hızlı menü, çalışma saatleri, iletişim.
	 * Saatler yalnızca qr-calisma-saatleri aktifken basılır. Garson/hesap
	 * butonları telif çubuğunun üstünde durur.
	 *
	 * @param array<string,mixed> $opts Ayarlar.
	 * @return string
	 */
	public function render_footer( $opts ) {
		$brand   = $this->render_brand( $opts, 'footer' );
		$nav     = $this->scope_nav_ids( $this->render_nav_menu( (int) $opts['menu_id'], 'hfb-footer__menu' ), 'hfb-f-' );
		$social  = $this->render_social_icons( $opts );
		$contact = $this->render_contact_lines( $opts );
		$hours   = $this->render_hours_column( $opts );
		$call    = $this->render_footer_call_buttons( $opts );
		$style   = $this->footer_css_vars( $opts );

		$links_title   = $this->hfb_cevir_option_varsayilan(
			isset( $opts['links_title'] ) ? $opts['links_title'] : '',
			isset( $this->footer_defaults['links_title'] ) ? $this->footer_defaults['links_title'] : 'Hızlı Menü'
		);
		$contact_title = $this->hfb_cevir_option_varsayilan(
			isset( $opts['contact_title'] ) ? $opts['contact_title'] : '',
			isset( $this->footer_defaults['contact_title'] ) ? $this->footer_defaults['contact_title'] : 'İletişim'
		);
		$hours_title   = $this->hfb_cevir_option_varsayilan(
			isset( $opts['hours_title'] ) ? $opts['hours_title'] : '',
			isset( $this->footer_defaults['hours_title'] ) ? $this->footer_defaults['hours_title'] : 'Çalışma Saatlerimiz'
		);

		$show_links   = (bool) $nav || '' !== $links_title;
		$show_contact = (bool) $contact || (bool) $social || '' !== $contact_title;

		ob_start();
		?>
		<div class="hfb-footer-wrap" data-hfb="footer"<?php echo $style ? ' style="' . esc_attr( $style ) . '"' : ''; ?>>
			<footer class="hfb-footer" role="contentinfo">
				<div class="hfb-footer__cq">
					<div class="hfb-footer__inner">
					<div class="hfb-footer__col hfb-footer__col--brand">
						<?php echo $brand; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php if ( ! empty( $opts['description'] ) ) : ?>
							<p class="hfb-footer__desc"><?php echo esc_html( $opts['description'] ); ?></p>
						<?php endif; ?>
					</div>

					<?php if ( $show_links ) : ?>
						<nav class="hfb-footer__col hfb-footer__col--links" aria-label="<?php echo esc_attr( '' !== $links_title ? $links_title : $this->hfb_cevir_ui( __( 'Hızlı Menü', 'qrms' ) ) ); ?>">
							<?php if ( '' !== $links_title ) : ?>
								<h3 class="hfb-footer__heading"><?php echo esc_html( $links_title ); ?></h3>
							<?php endif; ?>
							<?php if ( $nav ) : ?>
								<?php echo $nav; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endif; ?>
						</nav>
					<?php endif; ?>

					<?php if ( $hours ) : ?>
						<div class="hfb-footer__col hfb-footer__col--hours">
							<?php if ( '' !== $hours_title ) : ?>
								<h3 class="hfb-footer__heading"><?php echo esc_html( $hours_title ); ?></h3>
							<?php endif; ?>
							<?php echo $hours; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					<?php endif; ?>

					<?php if ( $show_contact ) : ?>
						<div class="hfb-footer__col hfb-footer__col--contact">
							<?php if ( '' !== $contact_title ) : ?>
								<h3 class="hfb-footer__heading"><?php echo esc_html( $contact_title ); ?></h3>
							<?php endif; ?>
							<?php echo $contact; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo $social; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					<?php endif; ?>
					</div>
				</div>

				<?php if ( $call ) : ?>
					<div class="hfb-footer__call-wrap">
						<?php echo $call; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $opts['copyright'] ) ) : ?>
					<div class="hfb-footer__bar">
						<p class="hfb-footer__copyright"><?php echo esc_html( $opts['copyright'] ); ?></p>
					</div>
				<?php endif; ?>
			</footer>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Footer sarmalayıcısına yazılacak `--hfb-footer-*` değişkenleri.
	 *
	 * @param array<string,mixed> $opts Footer ayarları.
	 * @return string
	 */
	public function footer_css_vars( $opts ) {
		$logo_h_desktop = ! empty( $opts['logo_height_auto_desktop'] ) ? 'auto' : (int) $opts['logo_height_desktop'] . 'px';
		$logo_h_mobile  = ! empty( $opts['logo_height_auto_mobile'] ) ? 'auto' : (int) $opts['logo_height_mobile'] . 'px';

		$vars = array(
			'--hfb-footer-logo-w-desktop'        => (int) $opts['logo_width_desktop'] . 'px',
			'--hfb-footer-logo-h-desktop'        => $logo_h_desktop,
			'--hfb-footer-logo-w-mobile'         => (int) $opts['logo_width_mobile'] . 'px',
			'--hfb-footer-logo-h-mobile'         => $logo_h_mobile,
			'--hfb-footer-brand-align'           => (string) $opts['brand_align'],
			'--hfb-footer-brand-justify'         => $this->align_to_flex( (string) $opts['brand_align'] ),
			'--hfb-footer-brand-font'            => $this->font_stack( (string) $opts['brand_font_family'] ),
			'--hfb-footer-brand-color'           => (string) $opts['brand_font_color'],
			'--hfb-footer-brand-weight'          => (string) (int) $opts['brand_font_weight'],
			'--hfb-footer-brand-size'            => (int) $opts['brand_font_size_desktop'] . 'px',
			'--hfb-footer-brand-size-mobile'     => (int) $opts['brand_font_size_mobile'] . 'px',
			'--hfb-footer-links-align'           => (string) $opts['links_align'],
			'--hfb-footer-links-justify'         => $this->align_to_flex( (string) $opts['links_align'] ),
			'--hfb-footer-links-title-font'      => $this->font_stack( (string) $opts['links_title_font_family'] ),
			'--hfb-footer-links-title-color'     => (string) $opts['links_title_font_color'],
			'--hfb-footer-links-title-weight'    => (string) (int) $opts['links_title_font_weight'],
			'--hfb-footer-links-title-size'      => (int) $opts['links_title_font_size_desktop'] . 'px',
			'--hfb-footer-links-title-size-m'    => (int) $opts['links_title_font_size_mobile'] . 'px',
			'--hfb-footer-links-item-font'       => $this->font_stack( (string) $opts['links_item_font_family'] ),
			'--hfb-footer-links-item-color'      => (string) $opts['links_item_font_color'],
			'--hfb-footer-links-item-weight'     => (string) (int) $opts['links_item_font_weight'],
			'--hfb-footer-links-item-size'       => (int) $opts['links_item_font_size_desktop'] . 'px',
			'--hfb-footer-links-item-size-m'     => (int) $opts['links_item_font_size_mobile'] . 'px',
			'--hfb-footer-links-item-hover'      => (string) $opts['links_item_hover_color'],
			'--hfb-footer-hours-align'           => (string) $opts['hours_align'],
			'--hfb-footer-hours-justify'         => $this->align_to_flex( (string) $opts['hours_align'] ),
			'--hfb-footer-hours-title-font'      => $this->font_stack( (string) $opts['hours_title_font_family'] ),
			'--hfb-footer-hours-title-color'     => (string) $opts['hours_title_font_color'],
			'--hfb-footer-hours-title-weight'    => (string) (int) $opts['hours_title_font_weight'],
			'--hfb-footer-hours-title-size'      => (int) $opts['hours_title_font_size_desktop'] . 'px',
			'--hfb-footer-hours-title-size-m'    => (int) $opts['hours_title_font_size_mobile'] . 'px',
			'--hfb-footer-hours-item-font'       => $this->font_stack( (string) $opts['hours_item_font_family'] ),
			'--hfb-footer-hours-item-color'      => (string) $opts['hours_item_font_color'],
			'--hfb-footer-hours-item-weight'     => (string) (int) $opts['hours_item_font_weight'],
			'--hfb-footer-hours-item-size'       => (int) $opts['hours_item_font_size_desktop'] . 'px',
			'--hfb-footer-hours-item-size-m'     => (int) $opts['hours_item_font_size_mobile'] . 'px',
			'--hfb-footer-contact-align'         => (string) $opts['contact_align'],
			'--hfb-footer-contact-justify'       => $this->align_to_flex( (string) $opts['contact_align'] ),
			'--hfb-footer-contact-title-font'    => $this->font_stack( (string) $opts['contact_title_font_family'] ),
			'--hfb-footer-contact-title-color'   => (string) $opts['contact_title_font_color'],
			'--hfb-footer-contact-title-weight'  => (string) (int) $opts['contact_title_font_weight'],
			'--hfb-footer-contact-title-size'    => (int) $opts['contact_title_font_size_desktop'] . 'px',
			'--hfb-footer-contact-title-size-m'  => (int) $opts['contact_title_font_size_mobile'] . 'px',
			'--hfb-footer-contact-item-font'     => $this->font_stack( (string) $opts['contact_item_font_family'] ),
			'--hfb-footer-contact-item-color'    => (string) $opts['contact_item_font_color'],
			'--hfb-footer-contact-item-weight'   => (string) (int) $opts['contact_item_font_weight'],
			'--hfb-footer-contact-item-size'     => (int) $opts['contact_item_font_size_desktop'] . 'px',
			'--hfb-footer-contact-item-size-m'   => (int) $opts['contact_item_font_size_mobile'] . 'px',
		);

		$vars = array_merge( $vars, $this->button_style_css_vars( $opts ) );

		return $this->css_vars_string( $vars );
	}

	/**
	 * Çalışma saatleri sütunu — veri tek kaynaktan (qrms_cs_get).
	 *
	 * @param array<string,mixed> $opts Footer ayarları.
	 * @return string
	 */
	private function render_hours_column( $opts ) {
		unset( $opts );

		if ( ! $this->hours_module_available() ) {
			return '';
		}

		$hours  = qrms_cs_get();
		$labels = qrms_cs_day_labels();
		$html   = '<ul class="hfb-footer__hours">';

		foreach ( qrms_cs_day_keys() as $key ) {
			$day   = isset( $hours[ $key ] ) ? $hours[ $key ] : array();
			$label = isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
			$range = function_exists( 'qrms_cs_format_day' ) ? qrms_cs_format_day( $day ) : '';
			$html .= '<li class="hfb-footer__hours-row">';
			$html .= '<span class="hfb-footer__hours-day">' . esc_html( $label ) . '</span>';
			$html .= '<span class="hfb-footer__hours-sep" aria-hidden="true"></span>';
			$html .= '<span class="hfb-footer__hours-time">' . esc_html( $range ) . '</span>';
			$html .= '</li>';
		}

		$html .= '</ul>';

		return $html;
	}

	/**
	 * Footer garson/hesap butonları.
	 *
	 * AJAX uçları qr-chatbot'taki mevcut garson_cagir / hesap_iste
	 * mekanizmasıdır; burada yalnızca aynı `data-qmo-cagri` sözleşmesi
	 * bağlanır. Geçerli masa oturumu yoksa buton basılmaz, uyarı basılır.
	 * Yönetim önizlemesinde (oturum yok) stilli butonlar yine görünür.
	 *
	 * @param array<string,mixed> $opts Footer ayarları.
	 * @return string
	 */
	private function render_footer_call_buttons( $opts ) {
		if ( empty( $opts['call_enabled'] ) ) {
			return '';
		}

		$preview = is_admin() || $this->elementor_is_edit_mode();
		$session = function_exists( 'qmo_oturum' ) ? qmo_oturum() : false;
		$live    = $session && function_exists( 'qmo_asset_enqueue' );

		if ( ! $preview && ! $session ) {
			$msg = $this->hfb_cevir_ui( __( 'Lütfen QR kodunu okutarak masanızdan erişin', 'qrms' ) );
			return '<div class="hfb-footer__call hfb-footer__call--warn"><p class="hfb-footer__call-msg">' . esc_html( $msg ) . '</p></div>';
		}

		if ( $live ) {
			if ( function_exists( 'qmo_chatbot_buton_varliklarini_kaydet' ) ) {
				qmo_chatbot_buton_varliklarini_kaydet();
			}
			qmo_asset_enqueue( 'qmo-buttons' );
		}

		// Adım 5B: aynı chat satırı; tekrar sarılmaz.
		$garson_yedek = __( 'Garson Çağır', 'qrms' );
		$hesap_yedek  = __( 'Hesap İste', 'qrms' );
		if ( function_exists( 'qmo_ceviri_chat' ) ) {
			$garson_yedek = qmo_ceviri_chat( $garson_yedek );
			$hesap_yedek  = qmo_ceviri_chat( $hesap_yedek );
		}

		$garson = isset( $opts['call_garson_label'] ) && '' !== trim( (string) $opts['call_garson_label'] )
			? (string) $opts['call_garson_label']
			: $garson_yedek;
		$hesap = isset( $opts['call_hesap_label'] ) && '' !== trim( (string) $opts['call_hesap_label'] )
			? (string) $opts['call_hesap_label']
			: $hesap_yedek;

		$html  = '<div class="hfb-footer__call qmo-cagri-bar">';
		$html .= '<button type="button" class="hfb-btn hfb-footer__call-btn"' . ( $live ? ' data-qmo-cagri="garson"' : '' ) . '>';
		$html .= $this->call_button_icon_svg( 'garson' );
		$html .= '<span>' . esc_html( $garson ) . '</span></button>';
		$html .= '<button type="button" class="hfb-btn hfb-footer__call-btn"' . ( $live ? ' data-qmo-cagri="hesap"' : '' ) . '>';
		$html .= $this->call_button_icon_svg( 'hesap' );
		$html .= '<span>' . esc_html( $hesap ) . '</span></button>';
		$html .= '<p class="qmo-cagri-durum" hidden></p>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Garson (zil) / hesap (fiş) SVG ikonu. Rengi currentColor olduğu için
	 * `--hfb-btn-color` otomatik iner.
	 *
	 * @param string $tip garson|hesap.
	 * @return string
	 */
	private function call_button_icon_svg( $tip ) {
		if ( 'hesap' === $tip ) {
			$path = '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M8 7h8M8 11h8M8 15h5"/>';
		} else {
			$path = '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>';
		}

		return '<svg class="hfb-icon hfb-icon--call" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">' . $path . '</svg>';
	}

	/**
	 * Mobil panel HTML.
	 *
	 * Logo, menü, sosyal, metin, buton ve dil blokları `blocks` dizisindeki
	 * sırayla gövdeye basılır; kapalı bloklar hiç görünmez. CTA telefon
	 * blok sırasının dışında altta kalır.
	 *
	 * @param array<string,mixed> $opts      Header ayarları.
	 * @param array<string,mixed> $hamburger Hamburger ayarları.
	 * @param string              $nav       Menü HTML.
	 * @param string              $brand     Marka HTML.
	 * @param string              $social    Sosyal ikon HTML.
	 * @param string              $lang      Dil seçici HTML.
	 * @return string
	 */
	private function render_mobile_panel( $opts, $hamburger, $nav, $brand, $social, $lang ) {
		$cta    = $this->render_cta( $opts );
		$blocks = isset( $hamburger['blocks'] ) && is_array( $hamburger['blocks'] )
			? $hamburger['blocks']
			: $this->hamburger_defaults['blocks'];

		// Arka plan görseli seçilmemişse katman hiç basılmaz: görsel
		// olmayan kurulumlarda panelin DOM'u değişmez.
		$has_bg_image = '' !== $this->panel_bg_image_url( $hamburger );

		ob_start();
		?>
		<div id="hfb-mobile-panel" class="hfb-mobile-panel" aria-hidden="true">
			<div class="hfb-mobile-panel__backdrop" tabindex="-1"></div>
			<div class="hfb-mobile-panel__sheet" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $this->hfb_cevir_ui( __( 'Mobil menü', 'qrms' ) ) ); ?>">
				<?php if ( $has_bg_image ) : ?>
					<div class="hfb-mobile-panel__bg" aria-hidden="true"></div>
				<?php endif; ?>

				<div class="hfb-mobile-panel__topbar">
					<span class="hfb-mobile-panel__topbar-spacer" aria-hidden="true"></span>
					<button type="button" class="hfb-mobile-panel__close" aria-label="<?php echo esc_attr( $this->hfb_cevir_ui( __( 'Menüyü kapat', 'qrms' ) ) ); ?>">
						<svg class="hfb-icon hfb-icon--close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
					</button>
				</div>

				<div class="hfb-mobile-panel__body">
					<?php
					foreach ( $blocks as $block ) {
						echo $this->render_hamburger_panel_block( $block, $opts, $hamburger, $nav, $brand, $social, $lang ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					?>
				</div>

				<?php if ( $cta ) : ?>
					<div class="hfb-mobile-panel__footer">
						<?php echo $cta; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Tek bir hamburger panel bloğunu render eder.
	 *
	 * @param array<string,mixed> $block     Blok verisi.
	 * @param array<string,mixed> $opts      Header ayarları.
	 * @param array<string,mixed> $hamburger Hamburger ayarları (buton varsayılanları).
	 * @param string              $nav       Menü HTML.
	 * @param string              $brand     Marka HTML.
	 * @param string              $social    Sosyal ikon HTML.
	 * @param string              $lang      Dil seçici HTML.
	 * @return string
	 */
	private function render_hamburger_panel_block( $block, $opts, $hamburger, $nav, $brand, $social, $lang ) {
		if ( ! is_array( $block ) || empty( $block['enabled'] ) ) {
			return '';
		}

		$type  = isset( $block['type'] ) ? (string) $block['type'] : '';
		$align = isset( $block['align'] ) ? (string) $block['align'] : 'center';
		$align = in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : 'center';
		$inner = '';

		switch ( $type ) {
			case 'logo':
				if ( $brand ) {
					$inner       = $brand;
					$description = isset( $block['description'] ) ? trim( (string) $block['description'] ) : '';

					if ( '' !== $description ) {
						$inner .= '<p class="hfb-mobile-panel__desc">' . esc_html( $description ) . '</p>';
					}
				}
				break;

			case 'menu':
				if ( $nav ) {
					$inner = '<nav class="hfb-mobile-panel__nav" aria-label="' . esc_attr( $this->hfb_cevir_ui( __( 'Mobil menü', 'qrms' ) ) ) . '">' . $nav . '</nav>';
				}
				break;

			case 'social':
				if ( $social ) {
					$inner = $social;
				}
				break;

			case 'text':
				$content = isset( $block['content'] ) ? trim( (string) $block['content'] ) : '';
				if ( '' !== $content ) {
					$inner = '<div class="hfb-mobile-panel__text">' . wp_kses_post( $content ) . '</div>';
				}
				break;

			case 'button':
				$inner = $this->render_hamburger_button_block( $block, $hamburger );
				break;

			case 'lang':
				$inner = $this->render_panel_lang_switcher( $opts );
				break;
		}

		if ( '' === $inner ) {
			return '';
		}

		return '<div class="hfb-mobile-panel__block hfb-mobile-panel__block--' . esc_attr( $type ) . ' hfb-mobile-panel__block--align-' . esc_attr( $align ) . '">' . $inner . '</div>';
	}

	/**
	 * Hamburger panel buton bloğu.
	 *
	 * Blok kendi renk/şekil/tipografi değerini taşımıyorsa Görünüm
	 * adımındaki panel geneli buton varsayılanları kullanılır.
	 *
	 * @param array<string,mixed>      $block     Blok verisi.
	 * @param array<string,mixed>|null $hamburger Hamburger ayarları.
	 * @return string
	 */
	private function render_hamburger_button_block( $block, $hamburger = null ) {
		$label = isset( $block['label'] ) ? trim( (string) $block['label'] ) : '';
		if ( '' === $label ) {
			return '';
		}

		$btn    = $this->hamburger_button_defaults( is_array( $hamburger ) ? $hamburger : null );
		$url    = isset( $block['url'] ) ? esc_url( (string) $block['url'] ) : '';
		$shape  = isset( $block['shape'] ) ? sanitize_key( (string) $block['shape'] ) : sanitize_key( (string) $btn['shape'] );
		$shapes = array_keys( $this->hamburger_button_shapes() );
		$shape  = in_array( $shape, $shapes, true ) ? $shape : 'pill';

		$font_key = isset( $block['font'] ) ? (string) $block['font'] : (string) $btn['font'];
		$style    = sprintf(
			'background-color:%1$s;color:%2$s;font-family:%3$s;font-size:%4$dpx;font-weight:%5$d;',
			esc_attr( isset( $block['bg_color'] ) ? (string) $block['bg_color'] : (string) $btn['bg_color'] ),
			esc_attr( isset( $block['text_color'] ) ? (string) $block['text_color'] : (string) $btn['text_color'] ),
			esc_attr( $this->font_stack( $font_key ) ),
			isset( $block['font_size'] ) ? (int) $block['font_size'] : (int) $btn['font_size'],
			isset( $block['font_weight'] ) ? (int) $block['font_weight'] : (int) $btn['font_weight']
		);

		$tag   = $url ? 'a' : 'span';
		$attrs = $url ? ' href="' . $url . '"' : '';

		$classes = 'hfb-mobile-panel__btn hfb-mobile-panel__btn--' . $shape;
		if ( ! empty( $block['full_width'] ) ) {
			$classes .= ' hfb-mobile-panel__btn--full';
		}

		return sprintf(
			'<%1$s class="%2$s" style="%3$s"%4$s>%5$s</%1$s>',
			$tag,
			esc_attr( $classes ),
			$style,
			$attrs,
			esc_html( $label )
		);
	}

	/**
	 * Panel içi dil seçici (header lang_show ayarından bağımsız).
	 *
	 * @param array<string,mixed> $opts Header ayarları.
	 * @return string
	 */
	private function render_panel_lang_switcher( $opts ) {
		unset( $opts );

		if ( ! $this->lang_switcher_available() ) {
			return '';
		}

		$html = do_shortcode( '[' . self::LANG_SHORTCODE . ']' );

		if ( '' === trim( (string) $html ) ) {
			return '';
		}

		return '<div class="hfb-lang">' . $html . '</div>';
	}

	/**
	 * Çeviri modülünün dil seçicisi kullanılabilir mi?
	 *
	 * Gevşek bağ: çeviri modülü kapalıysa kısa kod hiç kayıtlı olmaz ve
	 * burada sessizce `false` döner — ölümcül hata veya boş kutu yok.
	 *
	 * @return bool
	 */
	public function lang_switcher_available() {
		return function_exists( 'shortcode_exists' ) && shortcode_exists( self::LANG_SHORTCODE );
	}

	/**
	 * Dil seçici (çeviri modülünün bayrak kısa kodu).
	 *
	 * @param array<string,mixed> $opts Header ayarları.
	 * @return string
	 */
	public function render_lang_switcher( $opts ) {
		if ( empty( $opts['lang_show'] ) || ! $this->lang_switcher_available() ) {
			return '';
		}

		$html = do_shortcode( '[' . self::LANG_SHORTCODE . ']' );

		if ( '' === trim( (string) $html ) ) {
			return '';
		}

		return '<div class="hfb-lang">' . $html . '</div>';
	}

	/**
	 * CTA (telefon) butonu.
	 *
	 * @param array<string,mixed> $opts Ayarlar.
	 * @return string
	 */
	private function render_cta( $opts ) {
		if ( empty( $opts['cta_phone'] ) ) {
			return '';
		}

		$tel  = preg_replace( '/[^0-9+]/', '', $opts['cta_phone'] );
		$icon = '<svg class="hfb-icon hfb-icon--phone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>';

		return '<a class="hfb-cta" href="tel:' . esc_attr( $tel ) . '">' . $icon . esc_html( $opts['cta_phone'] ) . '</a>';
	}

	/**
	 * İletişim satırları.
	 *
	 * @param array<string,mixed> $opts Ayarlar.
	 * @return string
	 */
	private function render_contact_lines( $opts ) {
		$html = '';

		if ( ! empty( $opts['address'] ) ) {
			$html .= '<p class="hfb-footer__contact hfb-footer__contact--address">'
				. $this->contact_icon_svg( 'pin' )
				. '<span>' . nl2br( esc_html( (string) $opts['address'] ), false ) . '</span></p>';
		}

		if ( ! empty( $opts['phone'] ) ) {
			$tel   = preg_replace( '/[^0-9+]/', '', $opts['phone'] );
			$html .= '<a class="hfb-footer__contact hfb-footer__contact--phone" href="tel:' . esc_attr( $tel ) . '">'
				. $this->contact_icon_svg( 'phone' )
				. '<span>' . esc_html( $opts['phone'] ) . '</span></a>';
		}

		if ( ! empty( $opts['email'] ) ) {
			$html .= '<a class="hfb-footer__contact hfb-footer__contact--email" href="mailto:' . esc_attr( $opts['email'] ) . '">'
				. $this->contact_icon_svg( 'mail' )
				. '<span>' . esc_html( $opts['email'] ) . '</span></a>';
		}

		return $html;
	}

	/**
	 * İletişim satırı ikonu (konum / telefon / zarf).
	 *
	 * @param string $icon pin|phone|mail.
	 * @return string
	 */
	private function contact_icon_svg( $icon ) {
		$paths = array(
			'pin'   => '<path d="M12 21s7-5.4 7-11a7 7 0 1 0-14 0c0 5.6 7 11 7 11z"/><circle cx="12" cy="10" r="2.2"/>',
			'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>',
			'mail'  => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 7 9-7"/>',
		);

		$path = isset( $paths[ $icon ] ) ? $paths[ $icon ] : $paths['pin'];

		return '<svg class="hfb-icon hfb-icon--contact" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">' . $path . '</svg>';
	}

	/**
	 * Marka bloğu: logo görseli varsa o, yoksa QR ikonu + iki satırlık ad.
	 *
	 * @param array<string,mixed> $opts    Ayarlar.
	 * @param string              $context header|footer.
	 * @return string
	 */
	private function render_brand( $opts, $context ) {
		$id   = isset( $opts['logo'] ) ? (int) $opts['logo'] : 0;
		$home = esc_url( home_url( '/' ) );

		if ( $id > 0 ) {
			$img = wp_get_attachment_image(
				$id,
				'medium',
				false,
				array(
					'class'   => 'hfb-brand__img',
					'loading' => 'lazy',
				)
			);

			if ( $img ) {
				return '<a href="' . $home . '" class="hfb-brand hfb-brand--image hfb-brand--' . esc_attr( $context ) . '">' . $img . '</a>';
			}
		}

		$line1 = isset( $opts['brand_line1'] ) && '' !== $opts['brand_line1'] ? $opts['brand_line1'] : get_bloginfo( 'name' );
		$line2 = isset( $opts['brand_line2'] ) ? $opts['brand_line2'] : '';

		$html  = '<a href="' . $home . '" class="hfb-brand hfb-brand--' . esc_attr( $context ) . '">';
		$html .= $this->qr_mark_svg();
		$html .= '<span class="hfb-brand__text">';
		$html .= '<span class="hfb-brand__line hfb-brand__line--1">' . esc_html( $line1 ) . '</span>';

		if ( '' !== $line2 ) {
			$html .= '<span class="hfb-brand__line hfb-brand__line--2">' . esc_html( $line2 ) . '</span>';
		}

		$html .= '</span></a>';

		return $html;
	}

	/**
	 * Marka QR kod ikonu.
	 *
	 * @return string
	 */
	private function qr_mark_svg() {
		return '<svg class="hfb-brand__mark" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">'
			. '<path d="M3 3h7v7H3V3zm2 2v3h3V5H5zM14 3h7v7h-7V3zm2 2v3h3V5h-3zM3 14h7v7H3v-7zm2 2v3h3v-3H5z"/>'
			. '<path d="M14 14h3v3h-3zM19 14h2v2h-2zM14 19h2v2h-2zM18 18h3v3h-3z"/>'
			. '</svg>';
	}

	/**
	 * wp_nav_menu çıktısı.
	 *
	 * @param int    $menu_id Menü ID.
	 * @param string $class   Menü sınıfı.
	 * @return string
	 */
	private function render_nav_menu( $menu_id, $class ) {
		if ( $menu_id <= 0 ) {
			return '';
		}

		return (string) wp_nav_menu(
			array(
				'menu'        => $menu_id,
				'container'   => false,
				'menu_class'  => $class,
				'menu_id'     => 'menu',
				'fallback_cb' => false,
				'echo'        => false,
				'depth'       => 2,
			)
		);
	}

	/**
	 * Menü HTML'indeki tüm `id` değerlerini bir ön eke taşır.
	 *
	 * Aynı menü sayfada birden çok yerde basıldığında (masaüstü + mobil
	 * panel, header + footer) çift id oluşmasını engeller.
	 *
	 * @param string $html   Menü HTML'i.
	 * @param string $prefix Ön ek.
	 * @return string
	 */
	private function scope_nav_ids( $html, $prefix ) {
		if ( '' === $html || '' === $prefix ) {
			return $html;
		}

		return (string) preg_replace( '/\bid="([^"]*)"/', 'id="' . $prefix . '$1"', $html );
	}

	/**
	 * Sosyal ikonlar HTML.
	 *
	 * @param array<string,mixed> $opts Ayarlar.
	 * @return string
	 */
	private function render_social_icons( $opts ) {
		$state = $this->resolve_social_media_state( $opts );
		$map   = $this->social_media_map();

		if ( empty( $state['active'] ) ) {
			return '';
		}

		$html = '<div class="hfb-social">';
		foreach ( $state['active'] as $key ) {
			if ( empty( $state['urls'][ $key ] ) || ! isset( $map[ $key ] ) ) {
				continue;
			}
			$label = $map[ $key ]['label'];
			$icon  = $this->social_icon_svg( $map[ $key ]['icon'] );
			$html .= '<a class="hfb-social__link hfb-social__link--' . esc_attr( $key ) . '" href="' . esc_url( $state['urls'][ $key ] ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( $label ) . '">' . $icon . '</a>';
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * Sosyal ikon SVG.
	 *
	 * @param string $icon İkon anahtarı.
	 * @return string
	 */
	private function social_icon_svg( $icon ) {
		$paths = array(
			'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="0.9" fill="currentColor" stroke="none"/>',
			'facebook'  => '<path d="M15 3.5h-1.8A3.7 3.7 0 0 0 9.5 7.2V20.5"/><path d="M6.6 10.6h7.2"/>',
			'youtube'   => '<rect x="2.5" y="6" width="19" height="12" rx="4"/><path fill="currentColor" stroke="none" d="M10.3 9.3v5.4l4.9-2.7Z"/>',
			'x'         => '<path fill="currentColor" stroke="none" d="M3.5 3h3.4l13.6 18h-3.4z"/><path fill="currentColor" stroke="none" d="M18.2 3h2.3L6.1 21H3.8z"/>',
			'tiktok'    => '<path d="M14 3v10.6a3.4 3.4 0 1 1-3-3.37"/><path d="M14 3c.4 3.1 2.7 5.3 5.8 5.6"/>',
			'whatsapp'  => '<path d="M12 3a9 9 0 0 0-7.8 13.5L3 21l4.7-1.2A9 9 0 1 0 12 3Z"/>',
			'linkedin'  => '<rect x="3" y="3" width="18" height="18" rx="4"/><path d="M8 10.5V17"/><circle cx="8" cy="7.2" r="0.9" fill="currentColor" stroke="none"/><path d="M12.3 17v-4a2.4 2.4 0 0 1 4.8 0v4"/>',
			'pinterest' => '<circle cx="12" cy="12" r="9"/><path d="M10.4 19.2c-.3-1 0-2.3.3-3.3l1-4.2"/><path d="M8.6 9.9a3.4 3.4 0 1 1 6.8.4c0 2.3-1.2 3.9-2.9 3.9-.9 0-1.6-.7-1.4-1.6"/>',
		);

		$path = isset( $paths[ $icon ] ) ? $paths[ $icon ] : '<circle cx="12" cy="12" r="9"/>';

		return '<svg class="hfb-icon hfb-icon--social" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">' . $path . '</svg>';
	}

	/**
	 * HFB chrome metni (aria, uyarı). item_type=ui_string — yeni tip yok.
	 *
	 * Adım 4 aria: splash tampon nitelik görmez; data-sp-attr + tüm diller
	 * splash.js ile yazılır. HFB'de o JS yok; splash'e bağlamak Elementor
	 * çıktısını bozar. Sunucu rma_ceviri_modul ile basar (sepet/yorum);
	 * frontend.js kapat etiketini PHP aria'sından okur.
	 *
	 * @param string $metin Türkçe kaynak (__( '…', 'qrms' )).
	 * @return string
	 */
	private function hfb_cevir_ui( $metin ) {
		$metin = (string) $metin;
		if ( function_exists( 'rma_ceviri_modul' ) ) {
			return rma_ceviri_modul( 'ui_string', $metin );
		}
		return $metin;
	}

	/**
	 * Option sütun başlığı: kod sabitiyle birebirse çevir; yönetici metniyse dokunma (P1).
	 *
	 * Boş değer "başlık yok" demektir — varsayılana zorlanmaz (h3 basılmasın).
	 *
	 * @param string $deger      Option değeri.
	 * @param string $varsayilan Kod sabiti (footer_defaults).
	 * @return string
	 */
	private function hfb_cevir_option_varsayilan( $deger, $varsayilan ) {
		$deger      = trim( (string) $deger );
		$varsayilan = (string) $varsayilan;
		if ( '' === $deger ) {
			return '';
		}
		if ( $deger === $varsayilan ) {
			return $this->hfb_cevir_ui( $varsayilan );
		}
		return $deger;
	}
}
