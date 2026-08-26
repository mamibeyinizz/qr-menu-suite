<?php
/**
 * Header Footer Builder — ön yüz render ve varlıklar.
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
		$opts = $this->get_header_options();
		$this->enqueue_header_assets( $opts );

		return $this->render_header( $opts );
	}

	/**
	 * Footer kısa kodu.
	 *
	 * @param array<string,string> $atts Nitelikler.
	 * @return string
	 */
	public function shortcode_footer( $atts = array() ) {
		unset( $atts );
		$opts = $this->get_footer_options();
		$this->enqueue_footer_assets( $opts );

		return $this->render_footer( $opts );
	}

	/**
	 * Ön yüz varlıklarını gerektiğinde yükler.
	 *
	 * @return void
	 */
	public function maybe_enqueue_frontend_assets() {
		if ( $this->page_needs_header_assets() ) {
			$this->enqueue_header_assets( $this->get_header_options() );
		}

		if ( $this->page_needs_footer_assets() ) {
			$this->enqueue_footer_assets( $this->get_footer_options() );
		}
	}

	/**
	 * Sayfada header kısa kodu var mı?
	 *
	 * @return bool
	 */
	private function page_needs_header_assets() {
		return $this->page_has_hfb_shortcode( 'hfb_header' );
	}

	/**
	 * Sayfada footer kısa kodu var mı?
	 *
	 * @return bool
	 */
	private function page_needs_footer_assets() {
		return $this->page_has_hfb_shortcode( 'hfb_footer' );
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

		if ( did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' ) ) {
			$data = get_post_meta( $post->ID, '_elementor_data', true );
			if ( is_string( $data ) && false !== strpos( $data, $tag ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Header varlıklarını yükler (yalnızca aktif varyant).
	 *
	 * @param array<string,mixed> $opts Ayarlar.
	 * @return void
	 */
	private function enqueue_header_assets( $opts ) {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		$base    = 'modules/header-footer-builder/assets/';
		$variant = isset( $opts['variant'] ) ? $opts['variant'] : 'minimal-sticky';

		wp_enqueue_style(
			'hfb-base',
			QRMS_PLUGIN_URL . $base . 'css/frontend-base.css',
			array(),
			QRMS_Helpers::asset_version( $base . 'css/frontend-base.css' )
		);

		$css = 'css/header-' . $variant . '.css';
		wp_enqueue_style(
			'hfb-header-' . $variant,
			QRMS_PLUGIN_URL . $base . $css,
			array( 'hfb-base' ),
			QRMS_Helpers::asset_version( $base . $css )
		);

		wp_enqueue_script(
			'hfb-frontend',
			QRMS_PLUGIN_URL . $base . 'js/frontend.js',
			array(),
			QRMS_Helpers::asset_version( $base . 'js/frontend.js' ),
			true
		);

		$url = $this->google_font_url( $opts['mobile_panel_font'] );
		if ( $url ) {
			wp_enqueue_style( 'hfb-header-font', esc_url( $url ), array(), null );
		}

		if ( 'kinetic-bold' === $variant ) {
			wp_enqueue_style(
				'hfb-kinetic-font',
				'https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap',
				array(),
				null
			);
		}
	}

	/**
	 * Footer varlıklarını yükler (yalnızca aktif varyant).
	 *
	 * @param array<string,mixed> $opts Ayarlar.
	 * @return void
	 */
	private function enqueue_footer_assets( $opts ) {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		$base    = 'modules/header-footer-builder/assets/';
		$variant = isset( $opts['variant'] ) ? $opts['variant'] : 'utility-minimal';

		wp_enqueue_style(
			'hfb-base',
			QRMS_PLUGIN_URL . $base . 'css/frontend-base.css',
			array(),
			QRMS_Helpers::asset_version( $base . 'css/frontend-base.css' )
		);

		$css = 'css/footer-' . $variant . '.css';
		wp_enqueue_style(
			'hfb-footer-' . $variant,
			QRMS_PLUGIN_URL . $base . $css,
			array( 'hfb-base' ),
			QRMS_Helpers::asset_version( $base . $css )
		);
	}

	/**
	 * Header HTML çıktısı.
	 *
	 * @param array<string,mixed> $opts Ayarlar.
	 * @return string
	 */
	public function render_header( $opts ) {
		$variant = isset( $opts['variant'] ) ? $opts['variant'] : 'minimal-sticky';
		$logo    = $this->render_logo( $opts, 'header' );
		$nav     = $this->render_nav_menu( (int) $opts['menu_id'], 'hfb-header__menu' );
		$styles  = $this->header_inline_styles( $opts );

		$sticky_class = ! empty( $opts['sticky'] ) ? ' hfb-header--sticky' : '';
		$align_class  = ' hfb-header--align-' . sanitize_html_class( $opts['logo_alignment'] );

		ob_start();
		?>
		<div class="hfb-header-wrap" data-hfb="header" style="<?php echo esc_attr( $styles ); ?>">
			<header class="hfb-header hfb-header--<?php echo esc_attr( $variant ); ?><?php echo esc_attr( $sticky_class . $align_class ); ?>" role="banner">
				<?php
				if ( 'glass-bento' === $variant ) {
					$this->render_header_glass_bento( $logo, $nav );
				} elseif ( 'kinetic-bold' === $variant ) {
					$this->render_header_kinetic_bold( $logo, $nav );
				} else {
					$this->render_header_minimal_sticky( $logo, $nav );
				}
				?>
				<button type="button" class="hfb-header__toggle" aria-expanded="false" aria-controls="hfb-mobile-panel" aria-label="<?php esc_attr_e( 'Menüyü aç', 'qrms' ); ?>">
					<span class="hfb-header__toggle-bar"></span>
					<span class="hfb-header__toggle-bar"></span>
					<span class="hfb-header__toggle-bar"></span>
				</button>
			</header>
			<?php echo $this->render_mobile_panel( $opts, $nav ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Footer HTML çıktısı.
	 *
	 * @param array<string,mixed> $opts Ayarlar.
	 * @return string
	 */
	public function render_footer( $opts ) {
		$variant = isset( $opts['variant'] ) ? $opts['variant'] : 'utility-minimal';

		ob_start();
		?>
		<div class="hfb-footer-wrap" data-hfb="footer">
			<footer class="hfb-footer hfb-footer--<?php echo esc_attr( $variant ); ?>" role="contentinfo">
				<?php
				if ( 'bento-grid' === $variant ) {
					$this->render_footer_bento_grid( $opts );
				} elseif ( 'contact-first' === $variant ) {
					$this->render_footer_contact_first( $opts );
				} else {
					$this->render_footer_utility_minimal( $opts );
				}
				?>
			</footer>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Header inline CSS değişkenleri.
	 *
	 * @param array<string,mixed> $opts Ayarlar.
	 * @return string
	 */
	private function header_inline_styles( $opts ) {
		$vars = array(
			'--hfb-bg'              => $opts['bg_color'],
			'--hfb-text'            => $opts['text_color'],
			'--hfb-border'          => $opts['border_color'],
			'--hfb-logo-width'      => (int) $opts['logo_width'] . 'px',
			'--hfb-hamburger'       => $opts['hamburger_color'],
			'--hfb-panel-bg'        => $opts['mobile_panel_bg'],
			'--hfb-panel-bg-alpha'  => ( (int) $opts['mobile_panel_bg_opacity'] / 100 ),
			'--hfb-panel-text'      => $opts['mobile_panel_text_color'],
			'--hfb-panel-font-size' => (int) $opts['mobile_panel_text_size'] . 'px',
			'--hfb-panel-font'      => $this->font_family_css( $opts['mobile_panel_font'] ),
			'--hfb-close-color'     => $opts['mobile_close_icon_color'],
			'--hfb-close-size'      => (int) $opts['mobile_close_icon_size'] . 'px',
		);

		$parts = array();
		foreach ( $vars as $key => $value ) {
			$parts[] = $key . ':' . $value;
		}

		return implode( ';', $parts );
	}

	/**
	 * Minimal Sticky header iç yapısı.
	 *
	 * @param string $logo Logo HTML.
	 * @param string $nav  Menü HTML.
	 * @return void
	 */
	private function render_header_minimal_sticky( $logo, $nav ) {
		?>
		<div class="hfb-header__inner">
			<div class="hfb-header__logo"><?php echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<nav class="hfb-header__nav hfb-header__nav--desktop" aria-label="<?php esc_attr_e( 'Ana menü', 'qrms' ); ?>">
				<?php echo $nav; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</nav>
		</div>
		<?php
	}

	/**
	 * Glass Bento header iç yapısı.
	 *
	 * @param string $logo Logo HTML.
	 * @param string $nav  Menü HTML.
	 * @return void
	 */
	private function render_header_glass_bento( $logo, $nav ) {
		?>
		<div class="hfb-bento">
			<div class="hfb-bento__cell hfb-bento__cell--logo">
				<?php echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div class="hfb-bento__cell hfb-bento__cell--nav">
				<nav class="hfb-header__nav hfb-header__nav--desktop" aria-label="<?php esc_attr_e( 'Ana menü', 'qrms' ); ?>">
					<?php echo $nav; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</nav>
			</div>
		</div>
		<?php
	}

	/**
	 * Kinetic Bold header iç yapısı.
	 *
	 * @param string $logo Logo HTML.
	 * @param string $nav  Menü HTML.
	 * @return void
	 */
	private function render_header_kinetic_bold( $logo, $nav ) {
		?>
		<div class="hfb-header__inner hfb-header__inner--kinetic">
			<div class="hfb-header__brand">
				<?php echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<nav class="hfb-header__nav hfb-header__nav--desktop hfb-header__nav--kinetic" aria-label="<?php esc_attr_e( 'Ana menü', 'qrms' ); ?>">
				<?php echo $nav; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</nav>
		</div>
		<?php
	}

	/**
	 * Mobil panel HTML.
	 *
	 * @param array<string,mixed> $opts Ayarlar.
	 * @param string              $nav  Menü HTML.
	 * @return string
	 */
	private function render_mobile_panel( $opts, $nav ) {
		$style = sanitize_html_class( $opts['mobile_panel_style'] );
		$icon  = $this->render_close_icon( $opts['mobile_close_icon'] );

		ob_start();
		?>
		<div id="hfb-mobile-panel" class="hfb-mobile-panel hfb-mobile-panel--<?php echo esc_attr( $style ); ?>" aria-hidden="true">
			<div class="hfb-mobile-panel__backdrop" tabindex="-1"></div>
			<div class="hfb-mobile-panel__sheet">
				<button type="button" class="hfb-mobile-panel__close" aria-label="<?php esc_attr_e( 'Menüyü kapat', 'qrms' ); ?>">
					<?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</button>
				<nav class="hfb-mobile-panel__nav" aria-label="<?php esc_attr_e( 'Mobil menü', 'qrms' ); ?>">
					<?php echo $nav; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</nav>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Utility Minimal footer.
	 *
	 * @param array<string,mixed> $opts Ayarlar.
	 * @return void
	 */
	private function render_footer_utility_minimal( $opts ) {
		$logo = $this->render_logo( $opts, 'footer' );
		$nav  = $this->render_nav_menu( (int) $opts['menu_id'], 'hfb-footer__menu' );
		?>
		<div class="hfb-footer__inner hfb-footer__inner--utility">
			<div class="hfb-footer__brand">
				<?php echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php if ( ! empty( $opts['copyright'] ) ) : ?>
					<p class="hfb-footer__copyright"><?php echo esc_html( $opts['copyright'] ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( $nav ) : ?>
				<nav class="hfb-footer__links" aria-label="<?php esc_attr_e( 'Hızlı bağlantılar', 'qrms' ); ?>">
					<?php echo $nav; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</nav>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Bento Grid footer.
	 *
	 * @param array<string,mixed> $opts Ayarlar.
	 * @return void
	 */
	private function render_footer_bento_grid( $opts ) {
		$logo   = $this->render_logo( $opts, 'footer' );
		$nav    = $this->render_nav_menu( (int) $opts['menu_id'], 'hfb-footer__menu' );
		$social = $this->render_social_icons( $opts );
		?>
		<div class="hfb-footer__bento">
			<div class="hfb-footer__bento-cell hfb-footer__bento-cell--brand">
				<?php echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php if ( ! empty( $opts['description'] ) ) : ?>
					<p class="hfb-footer__desc"><?php echo esc_html( $opts['description'] ); ?></p>
				<?php endif; ?>
			</div>
			<div class="hfb-footer__bento-cell hfb-footer__bento-cell--contact">
				<h3 class="hfb-footer__bento-title"><?php esc_html_e( 'İletişim', 'qrms' ); ?></h3>
				<?php $this->render_contact_lines( $opts ); ?>
			</div>
			<div class="hfb-footer__bento-cell hfb-footer__bento-cell--links">
				<h3 class="hfb-footer__bento-title"><?php esc_html_e( 'Hızlı Linkler', 'qrms' ); ?></h3>
				<nav aria-label="<?php esc_attr_e( 'Hızlı bağlantılar', 'qrms' ); ?>">
					<?php echo $nav; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</nav>
			</div>
			<?php if ( $social ) : ?>
				<div class="hfb-footer__bento-cell hfb-footer__bento-cell--social">
					<h3 class="hfb-footer__bento-title"><?php esc_html_e( 'Sosyal Medya', 'qrms' ); ?></h3>
					<?php echo $social; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( ! empty( $opts['copyright'] ) ) : ?>
			<p class="hfb-footer__copyright hfb-footer__copyright--center"><?php echo esc_html( $opts['copyright'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Contact-First footer.
	 *
	 * @param array<string,mixed> $opts Ayarlar.
	 * @return void
	 */
	private function render_footer_contact_first( $opts ) {
		$logo   = $this->render_logo( $opts, 'footer' );
		$social = $this->render_social_icons( $opts );
		?>
		<div class="hfb-footer__inner hfb-footer__inner--contact-first">
			<div class="hfb-footer__contact-col">
				<?php $this->render_contact_lines( $opts, true ); ?>
			</div>
			<div class="hfb-footer__logo-col">
				<?php echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php if ( ! empty( $opts['description'] ) ) : ?>
					<p class="hfb-footer__desc"><?php echo esc_html( $opts['description'] ); ?></p>
				<?php endif; ?>
			</div>
			<div class="hfb-footer__social-col">
				<?php echo $social; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
		<?php if ( ! empty( $opts['copyright'] ) ) : ?>
			<p class="hfb-footer__copyright hfb-footer__copyright--center"><?php echo esc_html( $opts['copyright'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * İletişim satırları.
	 *
	 * @param array<string,mixed> $opts Ayarlar.
	 * @param bool                $prominent Öne çıkan stil.
	 * @return void
	 */
	private function render_contact_lines( $opts, $prominent = false ) {
		$class = $prominent ? 'hfb-footer__contact hfb-footer__contact--prominent' : 'hfb-footer__contact';
		if ( ! empty( $opts['phone'] ) ) :
			$tel = preg_replace( '/[^0-9+]/', '', $opts['phone'] );
			?>
			<a class="<?php echo esc_attr( $class ); ?>" href="tel:<?php echo esc_attr( $tel ); ?>"><?php echo esc_html( $opts['phone'] ); ?></a>
		<?php endif; ?>
		<?php if ( ! empty( $opts['email'] ) ) : ?>
			<a class="<?php echo esc_attr( $class ); ?>" href="mailto:<?php echo esc_attr( $opts['email'] ); ?>"><?php echo esc_html( $opts['email'] ); ?></a>
		<?php endif;
	}

	/**
	 * Logo HTML.
	 *
	 * @param array<string,mixed> $opts Ayarlar.
	 * @param string              $context header|footer.
	 * @return string
	 */
	private function render_logo( $opts, $context ) {
		$id = isset( $opts['logo'] ) ? (int) $opts['logo'] : 0;

		if ( $id > 0 ) {
			$img = wp_get_attachment_image( $id, 'medium', false, array( 'class' => 'hfb-logo__img', 'loading' => 'lazy' ) );
			if ( $img ) {
				return '<a href="' . esc_url( home_url( '/' ) ) . '" class="hfb-logo hfb-logo--' . esc_attr( $context ) . '">' . $img . '</a>';
			}
		}

		return '<a href="' . esc_url( home_url( '/' ) ) . '" class="hfb-logo hfb-logo--text hfb-logo--' . esc_attr( $context ) . '">' . esc_html( get_bloginfo( 'name' ) ) . '</a>';
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
				'menu'       => $menu_id,
				'container'  => false,
				'menu_class' => $class,
				'fallback_cb'=> false,
				'echo'       => false,
				'depth'      => 2,
			)
		);
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
	 * Kapatma ikonu SVG.
	 *
	 * @param string $type x|arrow|chevron.
	 * @return string
	 */
	private function render_close_icon( $type ) {
		$paths = array(
			'x'       => '<path d="M6 6l12 12M18 6L6 18"/>',
			'arrow'   => '<path d="M19 12H5M12 5l-7 7 7 7"/>',
			'chevron' => '<path d="M15 6l-6 6 6 6"/>',
		);

		$path = isset( $paths[ $type ] ) ? $paths[ $type ] : $paths['x'];

		return '<svg class="hfb-icon hfb-icon--close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' . $path . '</svg>';
	}

	/**
	 * Sosyal ikon SVG.
	 *
	 * @param string $icon İkon anahtarı.
	 * @return string
	 */
	private function social_icon_svg( $icon ) {
		$paths = array(
			'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/>',
			'facebook'  => '<path d="M15 3.5h-1.8A3.7 3.7 0 0 0 9.5 7.2V20.5"/><path d="M6.6 10.6h7.2"/>',
			'youtube'   => '<rect x="2.5" y="6" width="19" height="12" rx="4"/><path fill="currentColor" stroke="none" d="M10.3 9.3v5.4l4.9-2.7Z"/>',
			'x'         => '<path fill="currentColor" stroke="none" d="M3.5 3h3.4l13.6 18h-3.4z"/><path fill="currentColor" stroke="none" d="M18.2 3h2.3L6.1 21H3.8z"/>',
			'tiktok'    => '<path d="M14 3v10.6a3.4 3.4 0 1 1-3-3.37"/><path d="M14 3c.4 3.1 2.7 5.3 5.8 5.6"/>',
			'whatsapp'  => '<path d="M12 3a9 9 0 0 0-7.8 13.5L3 21l4.7-1.2A9 9 0 1 0 12 3Z"/>',
			'linkedin'  => '<rect x="3" y="3" width="18" height="18" rx="4"/><path d="M8 10.5V17"/><circle cx="8" cy="7.2" r="0.9" fill="currentColor" stroke="none"/><path d="M12.3 17v-4a2.4 2.4 0 0 1 4.8 0v4"/>',
		);

		$path = isset( $paths[ $icon ] ) ? $paths[ $icon ] : '<circle cx="12" cy="12" r="9"/>';

		return '<svg class="hfb-icon hfb-icon--social" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">' . $path . '</svg>';
	}
}
