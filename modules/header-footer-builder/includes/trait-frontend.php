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
	 * Marka + hamburger panelinde seçilen Google Fonts adresi.
	 *
	 * @return string
	 */
	private function google_fonts_url() {
		$families = array( 'Playfair+Display:wght@500;600;700' );
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

		$keys = array_unique( array_filter( $keys ) );

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
						<nav class="hfb-header__nav" aria-label="<?php esc_attr_e( 'Ana menü', 'qrms' ); ?>">
							<?php echo $nav; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</nav>
					<?php endif; ?>

					<div class="hfb-header__actions">
						<?php echo $social; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $lang; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>

					<button type="button" class="hfb-header__toggle" aria-expanded="false" aria-controls="hfb-mobile-panel" aria-label="<?php esc_attr_e( 'Menüyü aç', 'qrms' ); ?>">
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

		$vars = array(
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
			'--hfb-panel-font-size'        => (int) $hamburger['font_size_desktop'] . 'px',
			'--hfb-panel-font-weight'      => (string) (int) $hamburger['font_weight_desktop'],
			'--hfb-panel-font-align'       => (string) $hamburger['font_align_desktop'],
			'--hfb-panel-font-size-mobile' => (int) $hamburger['font_size_mobile'] . 'px',
			'--hfb-panel-font-weight-m'    => (string) (int) $hamburger['font_weight_mobile'],
			'--hfb-panel-font-align-m'     => (string) $hamburger['font_align_mobile'],
		);

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
	 * @param array<string,mixed> $opts Ayarlar.
	 * @return string
	 */
	public function render_footer( $opts ) {
		$brand   = $this->render_brand( $opts, 'footer' );
		$nav     = $this->scope_nav_ids( $this->render_nav_menu( (int) $opts['menu_id'], 'hfb-footer__menu' ), 'hfb-f-' );
		$social  = $this->render_social_icons( $opts );
		$contact = $this->render_contact_lines( $opts );

		ob_start();
		?>
		<div class="hfb-footer-wrap" data-hfb="footer">
			<footer class="hfb-footer" role="contentinfo">
				<div class="hfb-footer__inner">
					<div class="hfb-footer__col hfb-footer__col--brand">
						<?php echo $brand; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php if ( ! empty( $opts['description'] ) ) : ?>
							<p class="hfb-footer__desc"><?php echo esc_html( $opts['description'] ); ?></p>
						<?php endif; ?>
					</div>

					<?php if ( $nav ) : ?>
						<nav class="hfb-footer__col hfb-footer__col--links" aria-label="<?php esc_attr_e( 'Hızlı bağlantılar', 'qrms' ); ?>">
							<?php echo $nav; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</nav>
					<?php endif; ?>

					<?php if ( $contact || $social ) : ?>
						<div class="hfb-footer__col hfb-footer__col--contact">
							<?php echo $contact; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo $social; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					<?php endif; ?>
				</div>

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

		ob_start();
		?>
		<div id="hfb-mobile-panel" class="hfb-mobile-panel" aria-hidden="true">
			<div class="hfb-mobile-panel__backdrop" tabindex="-1"></div>
			<div class="hfb-mobile-panel__sheet" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Mobil menü', 'qrms' ); ?>">
				<div class="hfb-mobile-panel__topbar">
					<span class="hfb-mobile-panel__topbar-spacer" aria-hidden="true"></span>
					<button type="button" class="hfb-mobile-panel__close" aria-label="<?php esc_attr_e( 'Menüyü kapat', 'qrms' ); ?>">
						<svg class="hfb-icon hfb-icon--close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
					</button>
				</div>

				<div class="hfb-mobile-panel__body">
					<?php
					foreach ( $blocks as $block ) {
						echo $this->render_hamburger_panel_block( $block, $opts, $nav, $brand, $social, $lang ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
	 * @param array<string,mixed> $block  Blok verisi.
	 * @param array<string,mixed> $opts   Header ayarları.
	 * @param string              $nav    Menü HTML.
	 * @param string              $brand  Marka HTML.
	 * @param string              $social Sosyal ikon HTML.
	 * @param string              $lang   Dil seçici HTML.
	 * @return string
	 */
	private function render_hamburger_panel_block( $block, $opts, $nav, $brand, $social, $lang ) {
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
					$inner = $brand;
				}
				break;

			case 'menu':
				if ( $nav ) {
					$inner = '<nav class="hfb-mobile-panel__nav" aria-label="' . esc_attr( __( 'Mobil menü', 'qrms' ) ) . '">' . $nav . '</nav>';
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
				$inner = $this->render_hamburger_button_block( $block );
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
	 * @param array<string,mixed> $block Blok verisi.
	 * @return string
	 */
	private function render_hamburger_button_block( $block ) {
		$label = isset( $block['label'] ) ? trim( (string) $block['label'] ) : '';
		if ( '' === $label ) {
			return '';
		}

		$url    = isset( $block['url'] ) ? esc_url( (string) $block['url'] ) : '';
		$shape  = isset( $block['shape'] ) ? sanitize_key( (string) $block['shape'] ) : 'pill';
		$shapes = array_keys( $this->hamburger_button_shapes() );
		$shape  = in_array( $shape, $shapes, true ) ? $shape : 'pill';

		$font_key = isset( $block['font'] ) ? (string) $block['font'] : $this->hamburger_defaults['font_family'];
		$style    = sprintf(
			'background-color:%1$s;color:%2$s;font-family:%3$s;font-size:%4$dpx;font-weight:%5$d;',
			esc_attr( isset( $block['bg_color'] ) ? (string) $block['bg_color'] : '#c9a84c' ),
			esc_attr( isset( $block['text_color'] ) ? (string) $block['text_color'] : '#0a0a0c' ),
			esc_attr( $this->font_stack( $font_key ) ),
			isset( $block['font_size'] ) ? (int) $block['font_size'] : 15,
			isset( $block['font_weight'] ) ? (int) $block['font_weight'] : 600
		);

		$tag   = $url ? 'a' : 'span';
		$attrs = $url ? ' href="' . $url . '"' : '';

		return sprintf(
			'<%1$s class="hfb-mobile-panel__btn hfb-mobile-panel__btn--%2$s" style="%3$s"%4$s>%5$s</%1$s>',
			$tag,
			esc_attr( $shape ),
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

		if ( ! empty( $opts['phone'] ) ) {
			$tel   = preg_replace( '/[^0-9+]/', '', $opts['phone'] );
			$html .= '<a class="hfb-footer__contact" href="tel:' . esc_attr( $tel ) . '">' . esc_html( $opts['phone'] ) . '</a>';
		}

		if ( ! empty( $opts['email'] ) ) {
			$html .= '<a class="hfb-footer__contact" href="mailto:' . esc_attr( $opts['email'] ) . '">' . esc_html( $opts['email'] ) . '</a>';
		}

		return $html;
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
}
