<?php
/**
 * Header Footer Builder — yönetim paneli.
 *
 * Sayfa tek bir formdur; sekmeler (Header / Footer / Dil) yalnızca görsel
 * gruplamadır ve JS ile sayfa yenilenmeden değişir. Canlı önizleme sekme
 * DEĞİLDİR: forma komşu, sayfada her zaman duran sabit bir paneldir.
 * Gerekçe: önizlemeyi ayrı bir sekmeye koymak, önizlemenin beslendiği
 * formun DOM'da bulunmadığı bir durum yaratıyordu — eski sürümdeki
 * "önizleme çalışmıyor" hatasının kökeni tam olarak buydu.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

trait QRMS_HFB_Admin {

	/**
	 * Ayar sayfası.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			return;
		}

		$saved = false;

		if ( isset( $_POST['hfb_save'] ) && check_admin_referer( 'hfb_save_settings', 'hfb_nonce' ) ) {
			// Alanların her biri sanitize_header_input()/sanitize_footer_input()
			// içinde tek tek temizlenir; burada yalnızca slash'lar çözülür.
			$this->save_settings( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$saved = true;
		}

		$header_opts = $this->get_header_options();
		$footer_opts = $this->get_footer_options();
		$menus       = $this->get_nav_menus();

		$tabs = array(
			'header' => __( 'Header', 'qrms' ),
			'footer' => __( 'Footer', 'qrms' ),
			'lang'   => __( 'Dil / Çeviri', 'qrms' ),
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'header';
		if ( ! array_key_exists( $tab, $tabs ) ) {
			$tab = 'header';
		}
		?>
		<div class="wrap qrms-wrap hfb-wrap">
			<h1 class="qrms-title"><?php esc_html_e( 'Header Footer Builder', 'qrms' ); ?></h1>

			<p class="qrms-muted">
				<?php esc_html_e( 'Header ve footer içeriğini yapılandırın. Tasarım sabittir (siyah + gold). Elementor Shortcode widget\'ına [hfb_header] ve [hfb_footer] kısa kodlarını ekleyin.', 'qrms' ); ?>
			</p>

			<?php if ( $saved ) : ?>
				<div class="qrms-alert qrms-alert-success">
					<p><?php esc_html_e( 'Ayarlar kaydedildi.', 'qrms' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="hfb-layout">
				<div class="hfb-layout__main">
					<nav class="nav-tab-wrapper hfb-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Ayar sekmeleri', 'qrms' ); ?>">
						<?php foreach ( $tabs as $slug => $label ) : ?>
							<button
								type="button"
								class="nav-tab hfb-tabs__link<?php echo $tab === $slug ? ' nav-tab-active' : ''; ?>"
								role="tab"
								id="hfb-tab-<?php echo esc_attr( $slug ); ?>"
								data-hfb-tab="<?php echo esc_attr( $slug ); ?>"
								aria-controls="hfb-panel-<?php echo esc_attr( $slug ); ?>"
								aria-selected="<?php echo $tab === $slug ? 'true' : 'false'; ?>"
							><?php echo esc_html( $label ); ?></button>
						<?php endforeach; ?>
					</nav>

					<form method="post" class="qrms-form hfb-form" id="hfb-settings-form">
						<?php wp_nonce_field( 'hfb_save_settings', 'hfb_nonce' ); ?>

						<div class="hfb-tab-panel<?php echo 'header' === $tab ? ' is-active' : ''; ?>" id="hfb-panel-header" role="tabpanel" aria-labelledby="hfb-tab-header" data-hfb-panel="header"<?php echo 'header' === $tab ? '' : ' hidden'; ?>>
							<?php $this->render_header_fields( $header_opts, $menus ); ?>
						</div>

						<div class="hfb-tab-panel<?php echo 'footer' === $tab ? ' is-active' : ''; ?>" id="hfb-panel-footer" role="tabpanel" aria-labelledby="hfb-tab-footer" data-hfb-panel="footer"<?php echo 'footer' === $tab ? '' : ' hidden'; ?>>
							<?php $this->render_footer_fields( $footer_opts, $menus ); ?>
						</div>

						<div class="hfb-tab-panel<?php echo 'lang' === $tab ? ' is-active' : ''; ?>" id="hfb-panel-lang" role="tabpanel" aria-labelledby="hfb-tab-lang" data-hfb-panel="lang"<?php echo 'lang' === $tab ? '' : ' hidden'; ?>>
							<?php $this->render_lang_fields( $header_opts ); ?>
						</div>

						<p class="hfb-form__actions">
							<button type="submit" name="hfb_save" value="1" class="qrms-button qrms-button-primary">
								<?php esc_html_e( 'Kaydet', 'qrms' ); ?>
							</button>
						</p>
					</form>
				</div>

				<?php $this->render_live_preview_panel( $header_opts, $footer_opts ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Header sekmesi alanları.
	 *
	 * @param array<string,mixed> $opts  Ayarlar.
	 * @param array<int,string>   $menus Menü listesi.
	 * @return void
	 */
	private function render_header_fields( $opts, $menus ) {
		?>
		<div class="qrms-card">
			<h2 class="qrms-card-title"><?php esc_html_e( 'Marka', 'qrms' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Logo seçilmezse QR ikonu ve iki satırlık marka yazısı kullanılır.', 'qrms' ); ?>
			</p>

			<?php $this->render_media_field( 'hfb_header_logo', __( 'Logo (isteğe bağlı)', 'qrms' ), (int) $opts['logo'] ); ?>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_header_brand_line1"><?php esc_html_e( 'Marka — üst satır', 'qrms' ); ?></label>
				<input type="text" id="hfb_header_brand_line1" name="hfb_header_brand_line1" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( $opts['brand_line1'] ); ?>" placeholder="QR MENU" />
			</div>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_header_brand_line2"><?php esc_html_e( 'Marka — alt satır', 'qrms' ); ?></label>
				<input type="text" id="hfb_header_brand_line2" name="hfb_header_brand_line2" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( $opts['brand_line2'] ); ?>" placeholder="OFFİCİAL" />
			</div>
		</div>

		<div class="qrms-card">
			<h2 class="qrms-card-title"><?php esc_html_e( 'Menü & Davranış', 'qrms' ); ?></h2>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_header_menu_id"><?php esc_html_e( 'Menü', 'qrms' ); ?></label>
				<select id="hfb_header_menu_id" name="hfb_header_menu_id" class="qrms-input hfb-preview-trigger">
					<?php foreach ( $menus as $id => $name ) : ?>
						<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( (int) $opts['menu_id'], (int) $id ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="qrms-field">
				<label>
					<input type="checkbox" name="hfb_header_sticky" value="1" class="hfb-preview-trigger" <?php checked( ! empty( $opts['sticky'] ) ); ?> />
					<?php esc_html_e( 'Sayfa kaydırılırken header üstte sabit kalsın', 'qrms' ); ?>
				</label>
			</div>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_header_cta_phone"><?php esc_html_e( 'Mobil menüdeki telefon butonu', 'qrms' ); ?></label>
				<input type="text" id="hfb_header_cta_phone" name="hfb_header_cta_phone" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( $opts['cta_phone'] ); ?>" placeholder="0850 346 6586" />
				<p class="description"><?php esc_html_e( 'Boş bırakılırsa buton görünmez.', 'qrms' ); ?></p>
			</div>
		</div>

		<div class="qrms-card">
			<h2 class="qrms-card-title"><?php esc_html_e( 'Sosyal Medya', 'qrms' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Header\'ın sağ ucunda altın çerçeveli daireler olarak görünür. En fazla 6 tanesi gösterilir.', 'qrms' ); ?></p>
			<?php $this->render_social_fields( $opts, 'hfb_header_' ); ?>
		</div>
		<?php
	}

	/**
	 * Footer sekmesi alanları.
	 *
	 * @param array<string,mixed> $opts  Ayarlar.
	 * @param array<int,string>   $menus Menü listesi.
	 * @return void
	 */
	private function render_footer_fields( $opts, $menus ) {
		?>
		<div class="qrms-card">
			<h2 class="qrms-card-title"><?php esc_html_e( 'Marka & Açıklama', 'qrms' ); ?></h2>
			<?php $this->render_media_field( 'hfb_footer_logo', __( 'Logo (isteğe bağlı)', 'qrms' ), (int) $opts['logo'] ); ?>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_footer_brand_line1"><?php esc_html_e( 'Marka — üst satır', 'qrms' ); ?></label>
				<input type="text" id="hfb_footer_brand_line1" name="hfb_footer_brand_line1" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( $opts['brand_line1'] ); ?>" placeholder="QR MENU" />
			</div>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_footer_brand_line2"><?php esc_html_e( 'Marka — alt satır', 'qrms' ); ?></label>
				<input type="text" id="hfb_footer_brand_line2" name="hfb_footer_brand_line2" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( $opts['brand_line2'] ); ?>" placeholder="OFFİCİAL" />
			</div>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_footer_description"><?php esc_html_e( 'Kısa açıklama', 'qrms' ); ?></label>
				<textarea id="hfb_footer_description" name="hfb_footer_description" class="qrms-input hfb-preview-trigger" rows="3"><?php echo esc_textarea( $opts['description'] ); ?></textarea>
			</div>
		</div>

		<div class="qrms-card">
			<h2 class="qrms-card-title"><?php esc_html_e( 'İletişim & Menü', 'qrms' ); ?></h2>

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
				<select id="hfb_footer_menu_id" name="hfb_footer_menu_id" class="qrms-input hfb-preview-trigger">
					<?php foreach ( $menus as $id => $name ) : ?>
						<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( (int) $opts['menu_id'], (int) $id ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="qrms-card">
			<h2 class="qrms-card-title"><?php esc_html_e( 'Sosyal Medya', 'qrms' ); ?></h2>
			<?php $this->render_social_fields( $opts, 'hfb_' ); ?>
		</div>
		<?php
	}

	/**
	 * Dil / Çeviri sekmesi.
	 *
	 * @param array<string,mixed> $opts Header ayarları.
	 * @return void
	 */
	private function render_lang_fields( $opts ) {
		$available = $this->lang_switcher_available();
		?>
		<div class="qrms-card">
			<h2 class="qrms-card-title"><?php esc_html_e( 'Dil Seçici', 'qrms' ); ?></h2>

			<div class="qrms-field">
				<label>
					<input type="checkbox" name="hfb_lang_show" value="1" class="hfb-preview-trigger" <?php checked( ! empty( $opts['lang_show'] ) ); ?> />
					<?php esc_html_e( 'Dil Seçeneğini Header\'da Göster', 'qrms' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Açıkken bayrak masaüstünde header\'ın en sağında, mobilde açılan menünün üstünde görünür. Kapalıyken hiçbir yerde görünmez.', 'qrms' ); ?>
				</p>
			</div>

			<?php if ( $available ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: shortcode tag */
						esc_html__( 'Bayrak, QR Çeviri modülünün %s kısa kodundan gelir; dil listesi o modülden yönetilir.', 'qrms' ),
						'<code>[' . esc_html( self::LANG_SHORTCODE ) . ']</code>'
					);
					?>
				</p>
			<?php else : ?>
				<div class="qrms-alert">
					<p><?php esc_html_e( 'QR Çeviri modülü şu anda etkin değil. Bu seçenek açık olsa bile modül etkinleşene kadar bayrak görünmez — hata oluşmaz.', 'qrms' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Sosyal medya alan grubu.
	 *
	 * @param array<string,mixed> $opts   Ayarlar.
	 * @param string              $prefix Alan adı ön eki.
	 * @return void
	 */
	private function render_social_fields( $opts, $prefix ) {
		$map    = $this->social_media_map();
		$state  = $this->resolve_social_media_state( $opts );
		$active = isset( $opts['social_media_active'] ) && is_array( $opts['social_media_active'] ) ? $opts['social_media_active'] : $state['active'];
		$urls   = isset( $opts['social_media'] ) && is_array( $opts['social_media'] ) ? $opts['social_media'] : array();

		foreach ( $map as $key => $meta ) :
			$url_val = isset( $urls[ $key ] ) ? $urls[ $key ] : '';
			?>
			<div class="qrms-field hfb-social-row">
				<label>
					<input
						type="checkbox"
						name="<?php echo esc_attr( $prefix ); ?>social_media_active[]"
						value="<?php echo esc_attr( $key ); ?>"
						class="hfb-preview-trigger"
						<?php checked( in_array( $key, $active, true ) ); ?>
					/>
					<?php echo esc_html( $meta['label'] ); ?>
				</label>
				<input
					type="url"
					name="<?php echo esc_attr( $prefix ); ?>social_media_url_<?php echo esc_attr( $key ); ?>"
					class="qrms-input hfb-preview-trigger"
					placeholder="https://"
					value="<?php echo esc_attr( $url_val ); ?>"
				/>
			</div>
			<?php
		endforeach;
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
	 * Yönetim varlıkları.
	 *
	 * Yalnızca modülün kendi sayfasında yüklenir. Elementor editörü de bir
	 * admin ekranıdır; bu kontrol olmasa modülün admin JS'i editörde de
	 * çalışır ve çakışırdı.
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

		wp_enqueue_style(
			'hfb-admin',
			QRMS_PLUGIN_URL . $base . 'css/admin.css',
			array( 'qrms-admin' ),
			QRMS_Helpers::asset_version( $base . 'css/admin.css' )
		);

		wp_enqueue_script(
			'hfb-admin',
			QRMS_PLUGIN_URL . $base . 'js/admin.js',
			array( 'jquery' ),
			QRMS_Helpers::asset_version( $base . 'js/admin.js' ),
			true
		);

		wp_localize_script(
			'hfb-admin',
			'HFB_ADMIN',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'hfb_preview' ),
				'i18n'    => array(
					'updating' => __( 'Güncelleniyor…', 'qrms' ),
					'error'    => __( 'Önizleme güncellenemedi.', 'qrms' ),
				),
			)
		);
	}
}
