<?php
/**
 * Header Footer Builder — yönetim paneli.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

trait QRMS_HFB_Admin {

	/**
	 * Ana yönetim sayfası (Header / Footer / Canlı Önizleme sekmeleri).
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			return;
		}

		$saved = false;
		$tab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'header';

		if ( isset( $_POST['hfb_save'] ) && check_admin_referer( 'hfb_save_settings', 'hfb_nonce' ) ) {
			$save_tab = isset( $_POST['hfb_tab'] ) ? sanitize_key( wp_unslash( $_POST['hfb_tab'] ) ) : 'header';

			if ( 'footer' === $save_tab ) {
				$this->save_footer_settings();
				$tab = 'footer';
			} else {
				$this->save_header_settings();
				$tab = 'header';
			}

			$saved = true;
		}

		$header_opts = $this->get_header_options();
		$footer_opts = $this->get_footer_options();
		$menus       = $this->get_nav_menus();
		$tabs        = array(
			'header'  => __( 'Header', 'qrms' ),
			'footer'  => __( 'Footer', 'qrms' ),
			'preview' => __( 'Canlı Önizleme', 'qrms' ),
		);

		if ( ! array_key_exists( $tab, $tabs ) ) {
			$tab = 'header';
		}

		$base_url = admin_url( 'admin.php?page=' . rawurlencode( QRMS_Admin::get_module_page_slug( self::MODULE ) ) );
		?>
		<div class="wrap qrms-wrap hfb-wrap">
			<h1 class="qrms-title"><?php esc_html_e( 'Header Footer Builder', 'qrms' ); ?></h1>

			<p class="qrms-muted">
				<?php esc_html_e( 'Header ve footer bileşenlerini yapılandırın. Elementor Shortcode widget\'ına [hfb_header] ve [hfb_footer] kısa kodlarını ekleyin.', 'qrms' ); ?>
			</p>

			<?php if ( $saved ) : ?>
				<div class="qrms-alert qrms-alert-success">
					<p><?php esc_html_e( 'Ayarlar kaydedildi.', 'qrms' ); ?></p>
				</div>
			<?php endif; ?>

			<nav class="hfb-tabs" aria-label="<?php esc_attr_e( 'Ayar sekmeleri', 'qrms' ); ?>">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a
						href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base_url ) ); ?>"
						class="hfb-tabs__link<?php echo $tab === $slug ? ' is-active' : ''; ?>"
					><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php if ( 'preview' === $tab ) : ?>
				<?php $this->render_live_preview_tab( $header_opts, $footer_opts ); ?>
			<?php else : ?>
				<form method="post" class="qrms-form hfb-form" data-hfb-tab="<?php echo esc_attr( $tab ); ?>">
					<?php wp_nonce_field( 'hfb_save_settings', 'hfb_nonce' ); ?>
					<input type="hidden" name="hfb_tab" value="<?php echo esc_attr( $tab ); ?>" />

					<?php
					if ( 'footer' === $tab ) {
						$this->render_footer_fields( $footer_opts, $menus );
					} else {
						$this->render_header_fields( $header_opts, $menus );
					}
					?>

					<button type="submit" name="hfb_save" value="1" class="qrms-button qrms-button-primary">
						<?php esc_html_e( 'Kaydet', 'qrms' ); ?>
					</button>
				</form>

				<div class="qrms-card hfb-inline-preview">
					<h2 class="qrms-card-title"><?php esc_html_e( 'Hızlı Önizleme', 'qrms' ); ?></h2>
					<div id="hfb-preview-inline" class="hfb-preview-frame" data-preview-type="<?php echo esc_attr( $tab ); ?>">
						<?php
						if ( 'footer' === $tab ) {
							echo $this->render_footer( $footer_opts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						} else {
							echo $this->render_header( $header_opts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Header form alanları.
	 *
	 * @param array<string,mixed> $opts  Ayarlar.
	 * @param array<int,string>   $menus Menü listesi.
	 * @return void
	 */
	private function render_header_fields( $opts, $menus ) {
		?>
		<div class="qrms-card">
			<h2 class="qrms-card-title"><?php esc_html_e( 'Header Varyantı', 'qrms' ); ?></h2>
			<div class="qrms-field">
				<label class="qrms-label" for="hfb_header_variant"><?php esc_html_e( 'Tasarım', 'qrms' ); ?></label>
				<select id="hfb_header_variant" name="hfb_header_variant" class="qrms-input hfb-preview-trigger">
					<?php foreach ( $this->header_variants() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $opts['variant'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="qrms-card">
			<h2 class="qrms-card-title"><?php esc_html_e( 'Logo & Menü', 'qrms' ); ?></h2>
			<?php $this->render_media_field( 'hfb_header_logo', __( 'Logo', 'qrms' ), (int) $opts['logo'] ); ?>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_header_logo_width"><?php esc_html_e( 'Logo genişliği (px)', 'qrms' ); ?></label>
				<input type="number" id="hfb_header_logo_width" name="hfb_header_logo_width" class="qrms-input hfb-preview-trigger" min="60" max="320" value="<?php echo esc_attr( (int) $opts['logo_width'] ); ?>" />
			</div>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_header_logo_text_size"><?php esc_html_e( 'Logo metin boyutu (px)', 'qrms' ); ?></label>
				<input type="number" id="hfb_header_logo_text_size" name="hfb_header_logo_text_size" class="qrms-input hfb-preview-trigger" min="14" max="36" value="<?php echo esc_attr( (int) $opts['logo_text_size'] ); ?>" />
			</div>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_header_logo_alignment"><?php esc_html_e( 'Logo hizalama', 'qrms' ); ?></label>
				<select id="hfb_header_logo_alignment" name="hfb_header_logo_alignment" class="qrms-input hfb-preview-trigger">
					<option value="left" <?php selected( $opts['logo_alignment'], 'left' ); ?>><?php esc_html_e( 'Sol', 'qrms' ); ?></option>
					<option value="center" <?php selected( $opts['logo_alignment'], 'center' ); ?>><?php esc_html_e( 'Orta', 'qrms' ); ?></option>
					<option value="right" <?php selected( $opts['logo_alignment'], 'right' ); ?>><?php esc_html_e( 'Sağ', 'qrms' ); ?></option>
				</select>
			</div>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_header_menu_id"><?php esc_html_e( 'Menü', 'qrms' ); ?></label>
				<select id="hfb_header_menu_id" name="hfb_header_menu_id" class="qrms-input">
					<?php foreach ( $menus as $id => $name ) : ?>
						<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( (int) $opts['menu_id'], (int) $id ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="qrms-card">
			<h2 class="qrms-card-title"><?php esc_html_e( 'Renkler & Davranış', 'qrms' ); ?></h2>
			<?php
			$this->render_color_field( 'hfb_header_bg_color', __( 'Arka plan', 'qrms' ), $opts['bg_color'] );
			$this->render_color_field( 'hfb_header_text_color', __( 'Yazı rengi', 'qrms' ), $opts['text_color'] );
			$this->render_color_field( 'hfb_header_border_color', __( 'Alt çizgi', 'qrms' ), $opts['border_color'] );
			$this->render_color_field( 'hfb_header_brand_color', __( 'Marka rengi (logo)', 'qrms' ), $opts['brand_color'] );
			$this->render_color_field( 'hfb_hamburger_color', __( 'Hamburger ikon rengi', 'qrms' ), $opts['hamburger_color'] );
			?>
			<div class="qrms-field">
				<label>
					<input type="checkbox" name="hfb_header_sticky" value="1" class="hfb-preview-trigger" <?php checked( ! empty( $opts['sticky'] ) ); ?> />
					<?php esc_html_e( 'Scroll\'da küçülen sticky header', 'qrms' ); ?>
				</label>
			</div>
		</div>

		<div class="qrms-card">
			<h2 class="qrms-card-title"><?php esc_html_e( 'Mobil Hamburger Paneli', 'qrms' ); ?></h2>
			<div class="qrms-field">
				<label class="qrms-label" for="hfb_mobile_panel_style"><?php esc_html_e( 'Panel stili', 'qrms' ); ?></label>
				<select id="hfb_mobile_panel_style" name="hfb_mobile_panel_style" class="qrms-input hfb-preview-trigger">
					<option value="slide" <?php selected( $opts['mobile_panel_style'], 'slide' ); ?>><?php esc_html_e( 'Yandan kayan', 'qrms' ); ?></option>
					<option value="fullscreen" <?php selected( $opts['mobile_panel_style'], 'fullscreen' ); ?>><?php esc_html_e( 'Tam ekran', 'qrms' ); ?></option>
					<option value="menulux" <?php selected( $opts['mobile_panel_style'], 'menulux' ); ?>><?php esc_html_e( 'Menulux (gradient)', 'qrms' ); ?></option>
				</select>
			</div>

			<?php
			$this->render_color_field( 'hfb_mobile_panel_bg', __( 'Panel arka plan', 'qrms' ), $opts['mobile_panel_bg'] );
			$this->render_color_field( 'hfb_mobile_panel_gradient_start', __( 'Gradient başlangıç rengi', 'qrms' ), $opts['mobile_panel_gradient_start'] );
			$this->render_color_field( 'hfb_mobile_panel_gradient_end', __( 'Gradient bitiş rengi', 'qrms' ), $opts['mobile_panel_gradient_end'] );
			?>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_mobile_panel_bg_opacity"><?php esc_html_e( 'Panel opaklığı (%)', 'qrms' ); ?></label>
				<input type="number" id="hfb_mobile_panel_bg_opacity" name="hfb_mobile_panel_bg_opacity" class="qrms-input hfb-preview-trigger" min="0" max="100" value="<?php echo esc_attr( (int) $opts['mobile_panel_bg_opacity'] ); ?>" />
			</div>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_mobile_panel_font"><?php esc_html_e( 'Panel yazı fontu', 'qrms' ); ?></label>
				<select id="hfb_mobile_panel_font" name="hfb_mobile_panel_font" class="qrms-input hfb-preview-trigger">
					<?php foreach ( $this->font_options() as $font ) : ?>
						<option value="<?php echo esc_attr( $font ); ?>" <?php selected( $opts['mobile_panel_font'], $font ); ?>><?php echo esc_html( $font ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<?php
			$this->render_color_field( 'hfb_mobile_panel_text_color', __( 'Panel yazı rengi', 'qrms' ), $opts['mobile_panel_text_color'] );
			?>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_mobile_panel_text_size"><?php esc_html_e( 'Panel yazı boyutu (px)', 'qrms' ); ?></label>
				<input type="number" id="hfb_mobile_panel_text_size" name="hfb_mobile_panel_text_size" class="qrms-input hfb-preview-trigger" min="14" max="28" value="<?php echo esc_attr( (int) $opts['mobile_panel_text_size'] ); ?>" />
			</div>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_mobile_close_icon"><?php esc_html_e( 'Kapatma ikonu', 'qrms' ); ?></label>
				<select id="hfb_mobile_close_icon" name="hfb_mobile_close_icon" class="qrms-input hfb-preview-trigger">
					<?php foreach ( $this->close_icon_options() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $opts['mobile_close_icon'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<?php
			$this->render_color_field( 'hfb_mobile_close_icon_color', __( 'Kapatma ikon rengi', 'qrms' ), $opts['mobile_close_icon_color'] );
			?>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_mobile_close_icon_size"><?php esc_html_e( 'Kapatma ikon boyutu (px)', 'qrms' ); ?></label>
				<input type="number" id="hfb_mobile_close_icon_size" name="hfb_mobile_close_icon_size" class="qrms-input hfb-preview-trigger" min="16" max="40" value="<?php echo esc_attr( (int) $opts['mobile_close_icon_size'] ); ?>" />
			</div>
		</div>

		<div class="qrms-card">
			<h2 class="qrms-card-title"><?php esc_html_e( 'Menulux — Dil, CTA & Sosyal', 'qrms' ); ?></h2>
			<div class="qrms-field">
				<label class="qrms-label" for="hfb_lang_code"><?php esc_html_e( 'Mevcut dil kodu', 'qrms' ); ?></label>
				<input type="text" id="hfb_lang_code" name="hfb_lang_code" class="qrms-input hfb-preview-trigger" maxlength="5" value="<?php echo esc_attr( $opts['lang_code'] ); ?>" />
			</div>
			<div class="qrms-field">
				<label class="qrms-label" for="hfb_lang_alt_code"><?php esc_html_e( 'Dil seçici etiketi', 'qrms' ); ?></label>
				<input type="text" id="hfb_lang_alt_code" name="hfb_lang_alt_code" class="qrms-input hfb-preview-trigger" maxlength="5" value="<?php echo esc_attr( $opts['lang_alt_code'] ); ?>" />
			</div>
			<div class="qrms-field">
				<label class="qrms-label" for="hfb_lang_alt_url"><?php esc_html_e( 'Dil seçici URL', 'qrms' ); ?></label>
				<input type="url" id="hfb_lang_alt_url" name="hfb_lang_alt_url" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( $opts['lang_alt_url'] ); ?>" placeholder="https://" />
			</div>
			<?php
			$this->render_color_field( 'hfb_lang_border_color', __( 'Dil seçici çerçeve rengi', 'qrms' ), $opts['lang_border_color'] );
			$this->render_color_field( 'hfb_lang_text_color', __( 'Dil seçici yazı rengi', 'qrms' ), $opts['lang_text_color'] );
			?>
			<div class="qrms-field">
				<label class="qrms-label" for="hfb_cta_phone"><?php esc_html_e( 'CTA telefon numarası', 'qrms' ); ?></label>
				<input type="text" id="hfb_cta_phone" name="hfb_cta_phone" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( $opts['cta_phone'] ); ?>" placeholder="0850 346 6586" />
			</div>
			<?php
			$this->render_color_field( 'hfb_cta_bg_color', __( 'CTA buton rengi', 'qrms' ), $opts['cta_bg_color'] );
			$this->render_color_field( 'hfb_cta_text_color', __( 'CTA yazı rengi', 'qrms' ), $opts['cta_text_color'] );
			$this->render_color_field( 'hfb_social_color', __( 'Sosyal ikon rengi', 'qrms' ), $opts['social_color'] );
			?>
			<p class="description"><?php esc_html_e( 'Menulux mobil panelinde gösterilecek sosyal medya bağlantıları.', 'qrms' ); ?></p>
			<?php
			$social_map    = $this->social_media_map();
			$social_state  = $this->resolve_social_media_state( $opts );
			$social_active = $social_state['active'];
			$social_urls   = isset( $opts['social_media'] ) && is_array( $opts['social_media'] ) ? $opts['social_media'] : array();

			foreach ( $social_map as $key => $meta ) :
				$checked = in_array( $key, $social_active, true );
				$url_val = isset( $social_urls[ $key ] ) ? $social_urls[ $key ] : '';
				?>
				<div class="qrms-field hfb-social-field">
					<label>
						<input type="checkbox" name="hfb_header_social_media_active[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $checked ); ?> />
						<?php echo esc_html( $meta['label'] ); ?>
					</label>
					<input type="url" name="hfb_header_social_media_url_<?php echo esc_attr( $key ); ?>" class="qrms-input" value="<?php echo esc_attr( $url_val ); ?>" placeholder="https://" />
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Footer form alanları.
	 *
	 * @param array<string,mixed> $opts  Ayarlar.
	 * @param array<int,string>   $menus Menü listesi.
	 * @return void
	 */
	private function render_footer_fields( $opts, $menus ) {
		$social_map    = $this->social_media_map();
		$social_state  = $this->resolve_social_media_state( $opts );
		$social_active = $social_state['active'];
		$social_urls   = isset( $opts['social_media'] ) && is_array( $opts['social_media'] ) ? $opts['social_media'] : array();
		?>
		<div class="qrms-card">
			<h2 class="qrms-card-title"><?php esc_html_e( 'Footer Varyantı', 'qrms' ); ?></h2>
			<div class="qrms-field">
				<label class="qrms-label" for="hfb_footer_variant"><?php esc_html_e( 'Tasarım', 'qrms' ); ?></label>
				<select id="hfb_footer_variant" name="hfb_footer_variant" class="qrms-input hfb-preview-trigger">
					<?php foreach ( $this->footer_variants() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $opts['variant'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="qrms-card">
			<h2 class="qrms-card-title"><?php esc_html_e( 'İçerik', 'qrms' ); ?></h2>
			<?php $this->render_media_field( 'hfb_footer_logo', __( 'Logo', 'qrms' ), (int) $opts['logo'] ); ?>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_footer_description"><?php esc_html_e( 'Kısa açıklama', 'qrms' ); ?></label>
				<textarea id="hfb_footer_description" name="hfb_footer_description" class="qrms-input hfb-preview-trigger" rows="3"><?php echo esc_textarea( $opts['description'] ); ?></textarea>
			</div>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_footer_phone"><?php esc_html_e( 'Telefon', 'qrms' ); ?></label>
				<input type="text" id="hfb_footer_phone" name="hfb_footer_phone" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( $opts['phone'] ); ?>" />
			</div>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_footer_email"><?php esc_html_e( 'E-posta', 'qrms' ); ?></label>
				<input type="email" id="hfb_footer_email" name="hfb_footer_email" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( $opts['email'] ); ?>" />
			</div>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_footer_copyright"><?php esc_html_e( 'Telif metni', 'qrms' ); ?></label>
				<input type="text" id="hfb_footer_copyright" name="hfb_footer_copyright" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( $opts['copyright'] ); ?>" />
			</div>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_footer_menu_id"><?php esc_html_e( 'Hızlı linkler menüsü', 'qrms' ); ?></label>
				<select id="hfb_footer_menu_id" name="hfb_footer_menu_id" class="qrms-input">
					<?php foreach ( $menus as $id => $name ) : ?>
						<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( (int) $opts['menu_id'], (int) $id ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="qrms-card">
			<h2 class="qrms-card-title"><?php esc_html_e( 'Sosyal Medya', 'qrms' ); ?></h2>
			<?php foreach ( $social_map as $key => $item ) : ?>
				<div class="qrms-field hfb-social-row">
					<label>
						<input type="checkbox" name="hfb_social_media_active[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $social_active, true ) ); ?> />
						<?php echo esc_html( $item['label'] ); ?>
					</label>
					<input
						type="url"
						name="hfb_social_media_url_<?php echo esc_attr( $key ); ?>"
						class="qrms-input"
						placeholder="https://"
						value="<?php echo esc_attr( isset( $social_urls[ $key ] ) ? $social_urls[ $key ] : '' ); ?>"
					/>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Medya yükleme alanı.
	 *
	 * @param string $name  Alan adı.
	 * @param string $label Etiket.
	 * @param int    $id    Ek ID.
	 * @return void
	 */
	private function render_media_field( $name, $label, $id ) {
		$preview = $id > 0 ? wp_get_attachment_image_url( $id, 'thumbnail' ) : '';
		?>
		<div class="qrms-field hfb-media-field" data-field="<?php echo esc_attr( $name ); ?>">
			<label class="qrms-label"><?php echo esc_html( $label ); ?></label>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $id ); ?>" class="hfb-media-id hfb-preview-trigger" />
			<div class="hfb-media-preview">
				<?php if ( $preview ) : ?>
					<img src="<?php echo esc_url( $preview ); ?>" alt="" />
				<?php endif; ?>
			</div>
			<button type="button" class="button hfb-media-upload" data-target="<?php echo esc_attr( $name ); ?>"><?php esc_html_e( 'Medya seç', 'qrms' ); ?></button>
			<button type="button" class="button hfb-media-remove" data-target="<?php echo esc_attr( $name ); ?>"><?php esc_html_e( 'Kaldır', 'qrms' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Renk seçici alanı.
	 *
	 * @param string $name  Alan adı.
	 * @param string $label Etiket.
	 * @param string $value Değer.
	 * @return void
	 */
	private function render_color_field( $name, $label, $value ) {
		?>
		<div class="qrms-field">
			<label class="qrms-label" for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label>
			<input type="text" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" class="qrms-input hfb-color-picker hfb-preview-trigger" value="<?php echo esc_attr( $value ); ?>" data-default-color="<?php echo esc_attr( $value ); ?>" />
		</div>
		<?php
	}

	/**
	 * Yönetim varlıkları.
	 *
	 * @param string $hook Sayfa kancası.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		unset( $hook );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( QRMS_Admin::get_module_page_slug( self::MODULE ) !== $page ) {
			return;
		}

		$base = 'modules/header-footer-builder/assets/';

		$this->enqueue_preview_styles();

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );

		wp_enqueue_style(
			'hfb-admin',
			QRMS_PLUGIN_URL . $base . 'css/admin.css',
			array( 'qrms-admin', 'wp-color-picker' ),
			QRMS_Helpers::asset_version( $base . 'css/admin.css' )
		);

		wp_enqueue_script(
			'hfb-admin',
			QRMS_PLUGIN_URL . $base . 'js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			QRMS_Helpers::asset_version( $base . 'js/admin.js' ),
			true
		);

		wp_localize_script(
			'hfb-admin',
			'HFB_ADMIN',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'hfb_preview' ),
			)
		);
	}
}
