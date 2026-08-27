<?php
/**
 * Header Footer Builder — canlı önizleme.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

trait QRMS_HFB_Live_Preview {

	/**
	 * Canlı önizleme sekmesi.
	 *
	 * @param array<string,mixed> $header_opts Header ayarları.
	 * @param array<string,mixed> $footer_opts Footer ayarları.
	 * @return void
	 */
	public function render_live_preview_tab( $header_opts, $footer_opts ) {
		?>
		<div class="qrms-card hfb-preview-card">
			<h2 class="qrms-card-title"><?php esc_html_e( 'Canlı Önizleme', 'qrms' ); ?></h2>
			<p class="qrms-muted">
				<?php esc_html_e( 'Header ve footer bileşenlerinin birlikte görünümü. Ayar sekmelerindeki değişiklikler kaydetmeden burada güncellenir.', 'qrms' ); ?>
			</p>

			<div class="hfb-preview-toolbar">
				<button type="button" class="button hfb-preview-refresh"><?php esc_html_e( 'Önizlemeyi yenile', 'qrms' ); ?></button>
				<label class="hfb-preview-device">
					<?php esc_html_e( 'Görünüm:', 'qrms' ); ?>
					<select id="hfb-preview-viewport">
						<option value="desktop"><?php esc_html_e( 'Masaüstü', 'qrms' ); ?></option>
						<option value="mobile"><?php esc_html_e( 'Mobil', 'qrms' ); ?></option>
					</select>
				</label>
			</div>

			<div id="hfb-preview-full" class="hfb-preview-frame hfb-preview-frame--full" data-viewport="desktop">
				<div class="hfb-preview-section" data-section="header">
					<?php echo $this->render_header( $header_opts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="hfb-preview-placeholder">
					<p><?php esc_html_e( 'Sayfa içeriği alanı', 'qrms' ); ?></p>
				</div>
				<div class="hfb-preview-section" data-section="footer">
					<?php echo $this->render_footer( $footer_opts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Önizleme için tüm frontend stillerini yükler.
	 *
	 * @return void
	 */
	public function enqueue_preview_styles() {
		$base = 'modules/header-footer-builder/assets/';

		wp_enqueue_style(
			'hfb-base',
			QRMS_PLUGIN_URL . $base . 'css/frontend-base.css',
			array(),
			QRMS_Helpers::asset_version( $base . 'css/frontend-base.css' )
		);

		$variants = array(
			'header-minimal-sticky',
			'header-glass-bento',
			'header-kinetic-bold',
			'footer-utility-minimal',
			'footer-bento-grid',
			'footer-contact-first',
		);

		foreach ( $variants as $handle ) {
			$css = 'css/' . $handle . '.css';
			wp_enqueue_style(
				'hfb-' . $handle,
				QRMS_PLUGIN_URL . $base . $css,
				array( 'hfb-base' ),
				QRMS_Helpers::asset_version( $base . $css )
			);
		}

		wp_enqueue_style(
			'hfb-kinetic-font',
			'https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap',
			array(),
			null
		);
	}

	/**
	 * AJAX önizleme endpoint'i.
	 *
	 * @return void
	 */
	public function ajax_preview() {
		check_ajax_referer( 'hfb_preview', 'nonce' );

		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'header';
		$raw  = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		if ( 'footer' === $type ) {
			$opts = $this->preview_sanitize_footer( $raw );
			$html = $this->render_footer( $opts );
		} else {
			$opts = $this->preview_sanitize_header( $raw );
			$html = $this->render_header( $opts );
		}

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Önizleme için header verisini temizler.
	 *
	 * @param array<string,mixed> $raw Ham veri.
	 * @return array<string,mixed>
	 */
	private function preview_sanitize_header( $raw ) {
		$opts = $this->get_header_options();

		if ( isset( $raw['variant'] ) ) {
			$variant = sanitize_key( $raw['variant'] );
			if ( array_key_exists( $variant, $this->header_variants() ) ) {
				$opts['variant'] = $variant;
			}
		}

		if ( isset( $raw['logo_width'] ) ) {
			$opts['logo_width'] = max( 60, min( 320, absint( $raw['logo_width'] ) ) );
		}

		if ( isset( $raw['logo_text_size'] ) ) {
			$opts['logo_text_size'] = max( 14, min( 36, absint( $raw['logo_text_size'] ) ) );
		}

		$color_keys = array(
			'bg_color',
			'text_color',
			'border_color',
			'brand_color',
			'hamburger_color',
			'mobile_panel_bg',
			'mobile_panel_gradient_start',
			'mobile_panel_gradient_end',
			'mobile_panel_text_color',
			'mobile_close_icon_color',
			'lang_border_color',
			'lang_text_color',
			'cta_bg_color',
			'cta_text_color',
			'social_color',
		);
		foreach ( $color_keys as $key ) {
			if ( isset( $raw[ $key ] ) ) {
				$opts[ $key ] = sanitize_hex_color( $raw[ $key ] ) ?: $opts[ $key ];
			}
		}

		if ( isset( $raw['logo_alignment'] ) ) {
			$align = sanitize_key( $raw['logo_alignment'] );
			if ( in_array( $align, array( 'left', 'center', 'right' ), true ) ) {
				$opts['logo_alignment'] = $align;
			}
		}

		if ( isset( $raw['sticky'] ) ) {
			$opts['sticky'] = (int) $raw['sticky'];
		}

		if ( isset( $raw['mobile_panel_style'] ) ) {
			$style = sanitize_key( $raw['mobile_panel_style'] );
			if ( in_array( $style, array( 'slide', 'fullscreen', 'menulux' ), true ) ) {
				$opts['mobile_panel_style'] = $style;
			}
		}

		if ( isset( $raw['mobile_panel_bg_opacity'] ) ) {
			$opts['mobile_panel_bg_opacity'] = max( 0, min( 100, absint( $raw['mobile_panel_bg_opacity'] ) ) );
		}

		if ( isset( $raw['mobile_panel_font'] ) ) {
			$font = sanitize_text_field( $raw['mobile_panel_font'] );
			if ( in_array( $font, $this->font_options(), true ) ) {
				$opts['mobile_panel_font'] = $font;
			}
		}

		if ( isset( $raw['mobile_panel_text_size'] ) ) {
			$opts['mobile_panel_text_size'] = max( 14, min( 28, absint( $raw['mobile_panel_text_size'] ) ) );
		}

		if ( isset( $raw['mobile_close_icon'] ) ) {
			$icon = sanitize_key( $raw['mobile_close_icon'] );
			if ( array_key_exists( $icon, $this->close_icon_options() ) ) {
				$opts['mobile_close_icon'] = $icon;
			}
		}

		if ( isset( $raw['mobile_close_icon_size'] ) ) {
			$opts['mobile_close_icon_size'] = max( 16, min( 40, absint( $raw['mobile_close_icon_size'] ) ) );
		}

		if ( isset( $raw['cta_phone'] ) ) {
			$opts['cta_phone'] = sanitize_text_field( $raw['cta_phone'] );
		}

		if ( isset( $raw['lang_alt_code'] ) ) {
			$opts['lang_alt_code'] = sanitize_text_field( $raw['lang_alt_code'] );
		}

		return $opts;
	}

	/**
	 * Önizleme için footer verisini temizler.
	 *
	 * @param array<string,mixed> $raw Ham veri.
	 * @return array<string,mixed>
	 */
	private function preview_sanitize_footer( $raw ) {
		$opts = $this->get_footer_options();

		if ( isset( $raw['variant'] ) ) {
			$variant = sanitize_key( $raw['variant'] );
			if ( array_key_exists( $variant, $this->footer_variants() ) ) {
				$opts['variant'] = $variant;
			}
		}

		if ( isset( $raw['description'] ) ) {
			$opts['description'] = sanitize_textarea_field( $raw['description'] );
		}

		if ( isset( $raw['phone'] ) ) {
			$opts['phone'] = sanitize_text_field( $raw['phone'] );
		}

		if ( isset( $raw['email'] ) ) {
			$opts['email'] = sanitize_email( $raw['email'] );
		}

		if ( isset( $raw['copyright'] ) ) {
			$opts['copyright'] = sanitize_text_field( $raw['copyright'] );
		}

		return $opts;
	}
}
