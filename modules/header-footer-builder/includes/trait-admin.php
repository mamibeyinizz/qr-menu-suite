<?php
/**
 * Header Footer Builder — yönetim paneli.
 *
 * Sayfa tek bir formdur; sekmeler (Header / Footer / Dil / Hamburger)
 * yalnızca görsel gruplamadır ve JS ile sayfa yenilenmeden değişir.
 * Her sekmenin içinde vitrin modülündeki gibi numaralı adımlar vardır
 * (Geri Dön / Devam Et); adımlar da görseldir — gizli adımların alanları
 * DOM'da kalır, tek "Kaydet" tüm sekmeleri birden yazar.
 *
 * Canlı önizleme sekme DEĞİLDİR: forma komşu, sayfada her zaman duran
 * sabit bir paneldir.
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
			// Alanların her biri sanitize_*_input() içinde tek tek temizlenir;
			// burada yalnızca slash'lar çözülür.
			$this->save_settings( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$saved = true;
		}

		$header_opts    = $this->get_header_options();
		$footer_opts    = $this->get_footer_options();
		$hamburger_opts = $this->get_hamburger_options();
		$menus          = $this->get_nav_menus();

		$tabs = array(
			'header'    => __( 'Header', 'qrms' ),
			'footer'    => __( 'Footer', 'qrms' ),
			'lang'      => __( 'Dil / Çeviri', 'qrms' ),
			'hamburger' => __( 'Hamburger Menü', 'qrms' ),
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
				<?php esc_html_e( 'Header, footer ve hamburger menüsünün içeriğini ve tasarımını yapılandırın. Elementor Shortcode widget\'ına [hfb_header] ve [hfb_footer] kısa kodlarını ekleyin.', 'qrms' ); ?>
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
							<?php $this->render_header_fields( $header_opts ); ?>
						</div>

						<div class="hfb-tab-panel<?php echo 'footer' === $tab ? ' is-active' : ''; ?>" id="hfb-panel-footer" role="tabpanel" aria-labelledby="hfb-tab-footer" data-hfb-panel="footer"<?php echo 'footer' === $tab ? '' : ' hidden'; ?>>
							<?php $this->render_footer_fields( $footer_opts, $menus ); ?>
						</div>

						<div class="hfb-tab-panel<?php echo 'lang' === $tab ? ' is-active' : ''; ?>" id="hfb-panel-lang" role="tabpanel" aria-labelledby="hfb-tab-lang" data-hfb-panel="lang"<?php echo 'lang' === $tab ? '' : ' hidden'; ?>>
							<?php $this->render_lang_fields( $header_opts ); ?>
						</div>

						<div class="hfb-tab-panel<?php echo 'hamburger' === $tab ? ' is-active' : ''; ?>" id="hfb-panel-hamburger" role="tabpanel" aria-labelledby="hfb-tab-hamburger" data-hfb-panel="hamburger"<?php echo 'hamburger' === $tab ? '' : ' hidden'; ?>>
							<?php $this->render_hamburger_fields( $header_opts, $hamburger_opts, $menus ); ?>
						</div>

						<p class="hfb-form__actions">
							<button type="submit" name="hfb_save" value="1" class="qrms-button qrms-button-primary">
								<?php esc_html_e( 'Kaydet', 'qrms' ); ?>
							</button>
						</p>
					</form>
				</div>

				<?php $this->render_live_preview_panel( $header_opts, $footer_opts, $hamburger_opts ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Header sekmesi — adım sihirbazı.
	 *
	 * @param array<string,mixed> $opts Ayarlar.
	 * @return void
	 */
	private function render_header_fields( $opts ) {
		$adimlar = array(
			1 => array( 'Logo', 'Logo Boyutu' ),
			2 => array( 'Görünüm', 'Header Görünümü' ),
			3 => array( 'İkonlar', 'İkon ve Buton Renkleri' ),
		);

		$this->render_stepper_bar( 'header', $adimlar );
		?>
		<div class="qrms-card hfb-step" data-step="1" data-step-title="<?php esc_attr_e( 'Logo Boyutu', 'qrms' ); ?>">
			<h2 class="qrms-card-title"><?php esc_html_e( '1. Logo Boyutu', 'qrms' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Logo görselini yükleyin; genişlik ve yükseklik masaüstü, tablet ve mobil için ayrı ayarlanır. Logo seçilmezse QR ikonu ve iki satırlık marka yazısı kullanılır.', 'qrms' ); ?>
			</p>

			<h3 class="hfb-section-title"><?php esc_html_e( 'Genel', 'qrms' ); ?></h3>
			<?php $this->render_media_field( 'hfb_header_logo', __( 'Logo (isteğe bağlı)', 'qrms' ), (int) $opts['logo'] ); ?>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_header_brand_line1"><?php esc_html_e( 'Marka — üst satır', 'qrms' ); ?></label>
				<input type="text" id="hfb_header_brand_line1" name="hfb_header_brand_line1" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( $opts['brand_line1'] ); ?>" placeholder="QR MENU" />
			</div>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_header_brand_line2"><?php esc_html_e( 'Marka — alt satır', 'qrms' ); ?></label>
				<input type="text" id="hfb_header_brand_line2" name="hfb_header_brand_line2" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( $opts['brand_line2'] ); ?>" placeholder="OFFİCİAL" />
			</div>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_header_cta_phone"><?php esc_html_e( 'Mobil menüdeki telefon butonu', 'qrms' ); ?></label>
				<input type="text" id="hfb_header_cta_phone" name="hfb_header_cta_phone" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( $opts['cta_phone'] ); ?>" placeholder="0850 346 6586" />
				<p class="description"><?php esc_html_e( 'Boş bırakılırsa buton görünmez.', 'qrms' ); ?></p>
			</div>

			<h3 class="hfb-section-title"><?php esc_html_e( 'Masaüstü', 'qrms' ); ?></h3>
			<div class="hfb-size-group" data-hfb-preview-bp="desktop">
				<?php
				$this->hfb_size_row(
					'hfb_header_logo_width_desktop',
					'hfb_header_logo_width_desktop',
					(int) $opts['logo_width_desktop'],
					self::LOGO_WIDTH_MIN,
					self::LOGO_WIDTH_MAX,
					__( 'Logo genişlik', 'qrms' ),
					__( 'Masaüstünde logonun genişliği. Yükseklik otomatikse oran korunur.', 'qrms' )
				);
				$this->hfb_logo_height_block(
					'desktop',
					(int) $opts['logo_height_desktop'],
					! empty( $opts['logo_height_auto_desktop'] )
				);
				?>
			</div>

			<h3 class="hfb-section-title"><?php esc_html_e( 'Tablet', 'qrms' ); ?></h3>
			<div class="hfb-size-group" data-hfb-preview-bp="tablet">
				<?php
				$this->hfb_size_row(
					'hfb_header_logo_width_tablet',
					'hfb_header_logo_width_tablet',
					(int) $opts['logo_width_tablet'],
					self::LOGO_WIDTH_MIN,
					self::LOGO_WIDTH_MAX,
					__( 'Logo genişlik', 'qrms' ),
					__( 'Orta genişlikteki ekranlarda (yaklaşık 768–900px) logonun genişliği.', 'qrms' )
				);
				$this->hfb_logo_height_block(
					'tablet',
					(int) $opts['logo_height_tablet'],
					! empty( $opts['logo_height_auto_tablet'] )
				);
				?>
			</div>

			<h3 class="hfb-section-title"><?php esc_html_e( 'Mobil', 'qrms' ); ?></h3>
			<div class="hfb-size-group" data-hfb-preview-bp="mobile">
				<?php
				$this->hfb_size_row(
					'hfb_header_logo_width_mobile',
					'hfb_header_logo_width_mobile',
					(int) $opts['logo_width_mobile'],
					self::LOGO_WIDTH_MIN,
					self::LOGO_WIDTH_MAX,
					__( 'Logo genişlik', 'qrms' ),
					__( 'Telefonda logonun genişliği. Dar header\'da taşmayı önlemek için masaüstünden küçük tutun.', 'qrms' )
				);
				$this->hfb_logo_height_block(
					'mobile',
					(int) $opts['logo_height_mobile'],
					! empty( $opts['logo_height_auto_mobile'] )
				);
				?>
			</div>
		</div>

		<div class="qrms-card hfb-step" data-step="2" data-step-title="<?php esc_attr_e( 'Header Görünümü', 'qrms' ); ?>" style="display:none;">
			<h2 class="qrms-card-title"><?php esc_html_e( '2. Header Görünümü', 'qrms' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Header\'ın zemin rengi ve sayfa kaydırılırken üstte kalma davranışı. Varsayılan zemin projenin siyah paletidir.', 'qrms' ); ?>
			</p>

			<?php
			$this->hfb_color_field(
				'hfb_header_bg_color',
				'hfb_header_bg_color',
				(string) $opts['bg_color'],
				__( 'Header arka plan rengi', 'qrms' ),
				__( 'Header çubuğunun zemin rengi. Boş bırakılırsa varsayılan siyah (#0a0a0c) kullanılır.', 'qrms' ),
				'#0a0a0c'
			);
			?>

			<div class="qrms-field">
				<label class="hfb-check-row">
					<input type="checkbox" name="hfb_header_sticky" value="1" class="hfb-preview-trigger" <?php checked( ! empty( $opts['sticky'] ) ); ?> />
					<span><?php esc_html_e( 'Sayfa kaydırılırken header üstte sabit kalsın', 'qrms' ); ?></span>
				</label>
			</div>

			<div class="qrms-field">
				<label class="hfb-check-row">
					<input type="checkbox" name="hfb_header_sticky_blur" value="1" class="hfb-preview-trigger" <?php checked( ! empty( $opts['sticky_blur'] ) ); ?> />
					<span><?php esc_html_e( 'Kaydırmada arka plan yarı saydam olsun ve bulanıklaşsın (blur)', 'qrms' ); ?></span>
				</label>
				<p class="description"><?php esc_html_e( 'Yalnızca sticky açıkken ve sayfa kaydırıldığında uygulanır.', 'qrms' ); ?></p>
			</div>
		</div>

		<div class="qrms-card hfb-step" data-step="3" data-step-title="<?php esc_attr_e( 'İkon ve Buton Renkleri', 'qrms' ); ?>" style="display:none;">
			<h2 class="qrms-card-title"><?php esc_html_e( '3. İkon ve Buton Renkleri', 'qrms' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Header üzerindeki ikonların (telefon, dil seçici, sosyal) ve kapalı hamburger çizgilerinin rengi. İki ayar birbirinden bağımsızdır.', 'qrms' ); ?>
			</p>

			<?php
			$this->hfb_color_field(
				'hfb_header_icon_color',
				'hfb_header_icon_color',
				(string) $opts['icon_color'],
				__( 'İkon rengi', 'qrms' ),
				__( 'CTA telefon ikonu, dil seçici çerçevesi ve sosyal medya ikonları bu rengi kullanır.', 'qrms' ),
				'#c9a84c'
			);
			$this->hfb_color_field(
				'hfb_header_hamburger_icon_color',
				'hfb_header_hamburger_icon_color',
				(string) $opts['hamburger_icon_color'],
				__( 'Hamburger menü ikon rengi', 'qrms' ),
				__( 'Menü kapalıyken görünen üç çizgi ikonunun rengi.', 'qrms' ),
				'#c9a84c'
			);
			?>
		</div>

		<?php
		$this->render_step_nav( 'header' );
	}

	/**
	 * Footer sekmesi — adım sihirbazı.
	 *
	 * @param array<string,mixed> $opts  Ayarlar.
	 * @param array<int,string>   $menus Menü listesi.
	 * @return void
	 */
	private function render_footer_fields( $opts, $menus ) {
		$adimlar = array(
			1 => array( 'Logo', 'Logo ve Slogan' ),
			2 => array( 'Menü', 'Hızlı Menü' ),
			3 => array( 'Saatler', 'Çalışma Saatleri ve İletişim' ),
			4 => array( 'Çağrı', 'Garson / Hesap Butonu' ),
		);

		$this->render_stepper_bar( 'footer', $adimlar );
		?>
		<div class="qrms-card hfb-step" data-step="1" data-step-title="<?php esc_attr_e( 'Logo ve Slogan', 'qrms' ); ?>">
			<h2 class="qrms-card-title"><?php esc_html_e( '1. Logo ve Slogan', 'qrms' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Footer\'ın sol sütunundaki logo, iki satırlık marka adı ve kısa açıklama. Logo seçilmezse QR ikonu ve marka yazısı kullanılır.', 'qrms' ); ?></p>

			<h3 class="hfb-section-title"><?php esc_html_e( 'Genel', 'qrms' ); ?></h3>
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

			<h3 class="hfb-section-title"><?php esc_html_e( 'Masaüstü', 'qrms' ); ?></h3>
			<div class="hfb-size-group" data-hfb-preview-bp="desktop">
				<?php
				$this->hfb_size_row(
					'hfb_footer_logo_width_desktop',
					'hfb_footer_logo_width_desktop',
					(int) $opts['logo_width_desktop'],
					self::LOGO_WIDTH_MIN,
					self::LOGO_WIDTH_MAX,
					__( 'Logo genişlik', 'qrms' ),
					__( 'Masaüstünde footer logosunun genişliği.', 'qrms' )
				);
				$this->hfb_logo_height_block(
					'desktop',
					(int) $opts['logo_height_desktop'],
					! empty( $opts['logo_height_auto_desktop'] ),
					'hfb_footer'
				);
				?>
			</div>

			<h3 class="hfb-section-title"><?php esc_html_e( 'Mobil', 'qrms' ); ?></h3>
			<div class="hfb-size-group" data-hfb-preview-bp="mobile">
				<?php
				$this->hfb_size_row(
					'hfb_footer_logo_width_mobile',
					'hfb_footer_logo_width_mobile',
					(int) $opts['logo_width_mobile'],
					self::LOGO_WIDTH_MIN,
					self::LOGO_WIDTH_MAX,
					__( 'Logo genişlik', 'qrms' ),
					__( 'Telefonda footer logosunun genişliği.', 'qrms' )
				);
				$this->hfb_logo_height_block(
					'mobile',
					(int) $opts['logo_height_mobile'],
					! empty( $opts['logo_height_auto_mobile'] ),
					'hfb_footer'
				);
				?>
			</div>

			<?php
			$this->hfb_align_row(
				'hfb_footer_brand_align',
				'hfb_footer_brand_align',
				(string) $opts['brand_align'],
				__( 'Sütun hizalama', 'qrms' ),
				__( 'Logo, slogan ve açıklamanın bu sütundaki yaslanması.', 'qrms' )
			);
			$this->hfb_typo_block(
				'hfb_footer_',
				$opts,
				'brand',
				array(
					'title' => __( 'Slogan ve açıklama yazısı', 'qrms' ),
				)
			);
			?>
		</div>

		<div class="qrms-card hfb-step" data-step="2" data-step-title="<?php esc_attr_e( 'Hızlı Menü', 'qrms' ); ?>" style="display:none;">
			<h2 class="qrms-card-title"><?php esc_html_e( '2. Hızlı Menü', 'qrms' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Footer\'ın ikinci sütunu. Başlık metnini ve WordPress menüsünü seçin; bağlantılar ok ikonuyla listelenir.', 'qrms' ); ?></p>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_footer_links_title"><?php esc_html_e( 'Başlık', 'qrms' ); ?></label>
				<input type="text" id="hfb_footer_links_title" name="hfb_footer_links_title" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( $opts['links_title'] ); ?>" placeholder="<?php esc_attr_e( 'Hızlı Menü', 'qrms' ); ?>" />
			</div>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_footer_menu_id"><?php esc_html_e( 'Hızlı linkler menüsü', 'qrms' ); ?></label>
				<select id="hfb_footer_menu_id" name="hfb_footer_menu_id" class="qrms-input hfb-preview-trigger">
					<?php foreach ( $menus as $id => $name ) : ?>
						<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( (int) $opts['menu_id'], (int) $id ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<?php
			$this->hfb_align_row(
				'hfb_footer_links_align',
				'hfb_footer_links_align',
				(string) $opts['links_align'],
				__( 'Sütun hizalama', 'qrms' ),
				__( 'Başlık ve menü bağlantılarının yaslanması.', 'qrms' )
			);
			$this->hfb_typo_block(
				'hfb_footer_',
				$opts,
				'links_title',
				array(
					'title' => __( 'Başlık yazısı', 'qrms' ),
				)
			);
			$this->hfb_typo_block(
				'hfb_footer_',
				$opts,
				'links_item',
				array(
					'title'       => __( 'Menü bağlantıları', 'qrms' ),
					'hover_key'   => 'links_item_hover_color',
					'hover_label' => __( 'Hover rengi', 'qrms' ),
					'hover_desc'  => __( 'Bağlantının üzerine gelince kullanılan renk.', 'qrms' ),
				)
			);
			?>
		</div>

		<div class="qrms-card hfb-step" data-step="3" data-step-title="<?php esc_attr_e( 'Çalışma Saatleri ve İletişim', 'qrms' ); ?>" style="display:none;">
			<h2 class="qrms-card-title"><?php esc_html_e( '3. Çalışma Saatleri ve İletişim', 'qrms' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Görseldeki gibi yan yana duran iki sütun. Saatler QR Çalışma Saatleri modülünden okunur; iletişim bilgileri burada girilir.', 'qrms' ); ?></p>

			<div class="hfb-subpanel">
				<h3 class="hfb-subpanel__title"><?php esc_html_e( 'Çalışma Saatleri', 'qrms' ); ?></h3>
				<?php if ( $this->hours_module_available() ) : ?>
					<p class="description"><?php esc_html_e( 'Gün ve saat aralıkları QR Çalışma Saatleri modülünden gelir; burada yeniden girilmez. Yalnızca görünüm ayarlanır.', 'qrms' ); ?></p>
					<div class="qrms-field">
						<label class="qrms-label" for="hfb_footer_hours_title"><?php esc_html_e( 'Başlık', 'qrms' ); ?></label>
						<input type="text" id="hfb_footer_hours_title" name="hfb_footer_hours_title" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( $opts['hours_title'] ); ?>" placeholder="<?php esc_attr_e( 'Çalışma Saatlerimiz', 'qrms' ); ?>" />
					</div>
					<?php
					$this->hfb_align_row(
						'hfb_footer_hours_align',
						'hfb_footer_hours_align',
						(string) $opts['hours_align'],
						__( 'Sütun hizalama', 'qrms' ),
						__( 'Saat sütununun yaslanması.', 'qrms' )
					);
					$this->hfb_typo_block(
						'hfb_footer_',
						$opts,
						'hours_title',
						array(
							'title' => __( 'Başlık yazısı', 'qrms' ),
						)
					);
					$this->hfb_typo_block(
						'hfb_footer_',
						$opts,
						'hours_item',
						array(
							'title' => __( 'Gün ve saat metinleri', 'qrms' ),
						)
					);
					?>
				<?php else : ?>
					<div class="qrms-alert">
						<p><?php esc_html_e( 'QR Çalışma Saatleri modülü şu anda etkin değil. Bu sütun footer\'da görünmez — hata oluşmaz. Saatleri göstermek için modülü etkinleştirin.', 'qrms' ); ?></p>
					</div>
					<input type="hidden" name="hfb_footer_hours_title" value="<?php echo esc_attr( $opts['hours_title'] ); ?>" />
				<?php endif; ?>
			</div>

			<div class="hfb-subpanel">
				<h3 class="hfb-subpanel__title"><?php esc_html_e( 'İletişim', 'qrms' ); ?></h3>
				<div class="qrms-field">
					<label class="qrms-label" for="hfb_footer_contact_title"><?php esc_html_e( 'Başlık', 'qrms' ); ?></label>
					<input type="text" id="hfb_footer_contact_title" name="hfb_footer_contact_title" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( $opts['contact_title'] ); ?>" placeholder="<?php esc_attr_e( 'İletişim', 'qrms' ); ?>" />
				</div>

				<div class="qrms-field">
					<label class="qrms-label" for="hfb_footer_address"><?php esc_html_e( 'Adres', 'qrms' ); ?></label>
					<textarea id="hfb_footer_address" name="hfb_footer_address" class="qrms-input hfb-preview-trigger" rows="2"><?php echo esc_textarea( $opts['address'] ); ?></textarea>
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

				<p class="description"><?php esc_html_e( 'İletişim sütununda görünen sosyal ikonlar. En fazla 6 tanesi gösterilir.', 'qrms' ); ?></p>
				<?php $this->render_social_fields( $opts, 'hfb_' ); ?>

				<?php
				$this->hfb_align_row(
					'hfb_footer_contact_align',
					'hfb_footer_contact_align',
					(string) $opts['contact_align'],
					__( 'Sütun hizalama', 'qrms' ),
					__( 'İletişim sütununun yaslanması.', 'qrms' )
				);
				$this->hfb_typo_block(
					'hfb_footer_',
					$opts,
					'contact_title',
					array(
						'title' => __( 'Başlık yazısı', 'qrms' ),
					)
				);
				$this->hfb_typo_block(
					'hfb_footer_',
					$opts,
					'contact_item',
					array(
						'title' => __( 'Adres, telefon ve e-posta satırları', 'qrms' ),
					)
				);
				?>
			</div>
		</div>

		<div class="qrms-card hfb-step" data-step="4" data-step-title="<?php esc_attr_e( 'Garson / Hesap Butonu', 'qrms' ); ?>" style="display:none;">
			<h2 class="qrms-card-title"><?php esc_html_e( '4. Garson / Hesap Butonu', 'qrms' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Footer\'da görünen Garson Çağır ve Hesap İste kısayolları. Tıklama, mevcut masa oturumu + AJAX çağrı mekanizmasına gider; burada yeniden yazılmaz.', 'qrms' ); ?></p>

			<?php if ( ! $this->call_buttons_available() ) : ?>
				<div class="qrms-alert">
					<p><?php esc_html_e( 'Garson/hesap çağrı uçları (QR Chatbot) şu anda etkin değil. Butonlar önizlemede görünür ama sitede tıklanınca çağrı gitmez. Modülü etkinleştirince aynı ayarlar çalışır.', 'qrms' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="qrms-field">
				<label class="hfb-check-row">
					<input type="checkbox" name="hfb_footer_call_enabled" value="1" class="hfb-preview-trigger" <?php checked( ! empty( $opts['call_enabled'] ) ); ?> />
					<span><?php esc_html_e( 'Footer\'da Garson Çağır ve Hesap İste butonlarını göster', 'qrms' ); ?></span>
				</label>
				<p class="description"><?php esc_html_e( 'Müşteri geçerli bir masa oturumunda değilse butonlar tıklanamaz; "Lütfen QR kodunu okutarak masanızdan erişin" uyarısı gösterilir.', 'qrms' ); ?></p>
			</div>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_footer_call_garson_label"><?php esc_html_e( 'Garson butonu metni', 'qrms' ); ?></label>
				<input type="text" id="hfb_footer_call_garson_label" name="hfb_footer_call_garson_label" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( $opts['call_garson_label'] ); ?>" />
			</div>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_footer_call_hesap_label"><?php esc_html_e( 'Hesap butonu metni', 'qrms' ); ?></label>
				<input type="text" id="hfb_footer_call_hesap_label" name="hfb_footer_call_hesap_label" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( $opts['call_hesap_label'] ); ?>" />
			</div>

			<?php $this->hfb_button_style_fields( 'hfb_footer_', $opts ); ?>
		</div>

		<?php
		$this->render_step_nav( 'footer' );
	}

	/**
	 * Dil / Çeviri sekmesi.
	 *
	 * @param array<string,mixed> $opts Header ayarları.
	 * @return void
	 */
	private function render_lang_fields( $opts ) {
		$available = $this->lang_switcher_available();
		$adimlar   = array(
			1 => array( 'Dil', 'Dil Seçici' ),
		);

		$this->render_stepper_bar( 'lang', $adimlar );
		?>
		<div class="qrms-card hfb-step" data-step="1" data-step-title="<?php esc_attr_e( 'Dil Seçici', 'qrms' ); ?>">
			<h2 class="qrms-card-title"><?php esc_html_e( '1. Dil Seçici', 'qrms' ); ?></h2>

			<div class="qrms-field">
				<label class="hfb-check-row">
					<input type="checkbox" name="hfb_lang_show" value="1" class="hfb-preview-trigger" <?php checked( ! empty( $opts['lang_show'] ) ); ?> />
					<span><?php esc_html_e( 'Dil Seçeneğini Header\'da Göster', 'qrms' ); ?></span>
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
		$this->render_step_nav( 'lang' );
	}

	/**
	 * Hamburger Menü sekmesi — adım sihirbazı.
	 *
	 * @param array<string,mixed> $header_opts    Header ayarları (menü, sosyal).
	 * @param array<string,mixed> $opts           Hamburger ayarları.
	 * @param array<int,string>   $menus          Menü listesi.
	 * @return void
	 */
	private function render_hamburger_fields( $header_opts, $opts, $menus ) {
		$adimlar = array(
			1 => array( 'Açılış', 'Açılış Davranışı' ),
			2 => array( 'Bloklar', 'İçerik Blokları ve Sıralama' ),
			3 => array( 'Yazı', 'Yazı Tipi ve Renk' ),
		);

		$this->render_stepper_bar( 'hamburger', $adimlar );
		?>
		<div class="qrms-card hfb-step" data-step="1" data-step-title="<?php esc_attr_e( 'Açılış Davranışı', 'qrms' ); ?>">
			<h2 class="qrms-card-title"><?php esc_html_e( '1. Açılış Davranışı', 'qrms' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Açılan panel her zaman tam genişlik ve tam yükseklik kaplar — bu davranış sabittir, başka bir yerleşim seçeneği yoktur. Kapatma (X) ikonu sağ üst köşede durur.', 'qrms' ); ?>
			</p>

			<div class="hfb-notice">
				<p><?php esc_html_e( 'Panel: tam genişlik + tam yükseklik. Kapatma ikonu: sağ üst köşe.', 'qrms' ); ?></p>
			</div>

			<?php
			$this->hfb_color_field(
				'hfb_hamburger_close_icon_color',
				'hfb_hamburger_close_icon_color',
				(string) $opts['close_icon_color'],
				__( 'Kapatma ikonu rengi', 'qrms' ),
				__( 'Sağ üstteki X ikonunun rengi.', 'qrms' ),
				'#c9a84c'
			);
			$this->hfb_color_field(
				'hfb_hamburger_panel_bg_color',
				'hfb_hamburger_panel_bg_color',
				(string) $opts['panel_bg_color'],
				__( 'Panel arka plan rengi', 'qrms' ),
				__( 'Mobil hamburger menü açılır ekranının kendi zemin rengi. Header arka planından bağımsızdır.', 'qrms' ),
				'#0a0a0c'
			);
			?>
		</div>

		<div class="qrms-card hfb-step" data-step="2" data-step-title="<?php esc_attr_e( 'İçerik Blokları ve Sıralama', 'qrms' ); ?>" style="display:none;">
			<h2 class="qrms-card-title"><?php esc_html_e( '2. İçerik Blokları ve Sıralama', 'qrms' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Açılan panelde hangi blokların görüneceğini işaretleyin ve sürükleyerek sıralayın. İşaretsiz blok panelde hiç görünmez. Sıra değişince sağdaki önizleme anında güncellenir.', 'qrms' ); ?>
			</p>

			<input type="hidden" name="hfb_hamburger_block_order" id="hfb_hamburger_block_order" class="hfb-preview-trigger" value="<?php echo esc_attr( implode( ',', (array) $opts['block_order'] ) ); ?>" />

			<ul class="hfb-block-sortable" id="hfb-block-sortable">
				<?php
				$types = $this->hamburger_block_types();
				$order = isset( $opts['block_order'] ) && is_array( $opts['block_order'] ) ? $opts['block_order'] : array_keys( $types );
				foreach ( $order as $block ) :
					if ( ! isset( $types[ $block ] ) ) {
						continue;
					}
					$enabled = ! empty( $opts[ 'block_' . $block ] );
					?>
					<li class="hfb-block-item" data-block="<?php echo esc_attr( $block ); ?>">
						<span class="hfb-block-drag" aria-hidden="true">⋮⋮</span>
						<div class="hfb-block-item__body">
							<label class="hfb-check-row">
								<input type="checkbox" name="hfb_hamburger_block_<?php echo esc_attr( $block ); ?>" value="1" class="hfb-preview-trigger" <?php checked( $enabled ); ?> />
								<strong><?php echo esc_html( $types[ $block ] ); ?></strong>
							</label>

							<?php if ( 'menu' === $block ) : ?>
								<div class="qrms-field">
									<label class="qrms-label" for="hfb_header_menu_id"><?php esc_html_e( 'WordPress menüsü', 'qrms' ); ?></label>
									<select id="hfb_header_menu_id" name="hfb_header_menu_id" class="qrms-input hfb-preview-trigger">
										<?php foreach ( $menus as $id => $name ) : ?>
											<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( (int) $header_opts['menu_id'], (int) $id ); ?>><?php echo esc_html( $name ); ?></option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e( 'Aynı menü masaüstü header\'ında da kullanılır.', 'qrms' ); ?></p>
								</div>
							<?php elseif ( 'social' === $block ) : ?>
								<p class="description"><?php esc_html_e( 'Header\'ın sağ ucunda ve hamburger panelinde altın çerçeveli daireler olarak görünür. En fazla 6 tanesi gösterilir.', 'qrms' ); ?></p>
								<?php $this->render_social_fields( $header_opts, 'hfb_header_' ); ?>
							<?php elseif ( 'text' === $block ) : ?>
								<div class="qrms-field">
									<label class="qrms-label" for="hfb_hamburger_text"><?php esc_html_e( 'Serbest metin / HTML', 'qrms' ); ?></label>
									<textarea id="hfb_hamburger_text" name="hfb_hamburger_text" class="qrms-input hfb-preview-trigger" rows="4"><?php echo esc_textarea( $opts['text'] ); ?></textarea>
									<p class="description"><?php esc_html_e( 'İzin verilen HTML (paragraf, bağlantı, vurgu) kaydedilir; zararlı etiketler temizlenir.', 'qrms' ); ?></p>
								</div>
							<?php elseif ( 'logo' === $block ) : ?>
								<p class="description"><?php esc_html_e( 'Header sekmesinde yüklenen logo (veya marka yazısı) panel içinde bu sırada görünür.', 'qrms' ); ?></p>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="qrms-card hfb-step" data-step="3" data-step-title="<?php esc_attr_e( 'Yazı Tipi ve Renk', 'qrms' ); ?>" style="display:none;">
			<h2 class="qrms-card-title"><?php esc_html_e( '3. Yazı Tipi ve Renk', 'qrms' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Hamburger panelindeki tüm metinler — menü bağlantıları, metin bloğu — bu ayarları kullanır. Yazı tipi ve renk tüm cihazlarda ortaktır; boyut, kalınlık ve hizalama masaüstü ile mobil için ayrıdır.', 'qrms' ); ?>
			</p>

			<h3 class="hfb-section-title"><?php esc_html_e( 'Genel', 'qrms' ); ?></h3>
			<div class="qrms-field">
				<label class="qrms-label" for="hfb_hamburger_font_family"><?php esc_html_e( 'Yazı tipi', 'qrms' ); ?></label>
				<select id="hfb_hamburger_font_family" name="hfb_hamburger_font_family" class="qrms-input hfb-preview-trigger">
					<?php foreach ( $this->font_catalog() as $font_key => $font_meta ) : ?>
						<option value="<?php echo esc_attr( $font_key ); ?>" <?php selected( $opts['font_family'], $font_key ); ?>><?php echo esc_html( $font_meta['etiket'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<?php
			$this->hfb_color_field(
				'hfb_hamburger_font_color',
				'hfb_hamburger_font_color',
				(string) $opts['font_color'],
				__( 'Yazı rengi', 'qrms' ),
				__( 'Panel içindeki menü bağlantıları ve metin bloğu bu rengi kullanır.', 'qrms' ),
				'#f5f0e8'
			);
			?>

			<h3 class="hfb-section-title"><?php esc_html_e( 'Masaüstü', 'qrms' ); ?></h3>
			<div class="hfb-size-group" data-hfb-preview-bp="desktop">
				<?php
				$this->hfb_size_row(
					'hfb_hamburger_font_size_desktop',
					'hfb_hamburger_font_size_desktop',
					(int) $opts['font_size_desktop'],
					self::FONT_SIZE_MIN,
					self::FONT_SIZE_MAX,
					__( 'Yazı boyutu', 'qrms' ),
					__( 'Geniş ekranda panel metinlerinin punto değeri.', 'qrms' )
				);
				$this->hfb_weight_row(
					'hfb_hamburger_font_weight_desktop',
					'hfb_hamburger_font_weight_desktop',
					(int) $opts['font_weight_desktop'],
					__( 'Yazı kalınlığı', 'qrms' ),
					__( '400 sakin, 600–700 daha vurgulu durur.', 'qrms' )
				);
				$this->hfb_align_row(
					'hfb_hamburger_font_align_desktop',
					'hfb_hamburger_font_align_desktop',
					(string) $opts['font_align_desktop'],
					__( 'Metin hizalama', 'qrms' ),
					__( 'Panel metinlerinin yaslanması.', 'qrms' )
				);
				?>
			</div>

			<h3 class="hfb-section-title"><?php esc_html_e( 'Mobil', 'qrms' ); ?></h3>
			<div class="hfb-size-group" data-hfb-preview-bp="mobile">
				<?php
				$this->hfb_size_row(
					'hfb_hamburger_font_size_mobile',
					'hfb_hamburger_font_size_mobile',
					(int) $opts['font_size_mobile'],
					self::FONT_SIZE_MOBILE_MIN,
					self::FONT_SIZE_MOBILE_MAX,
					__( 'Yazı boyutu', 'qrms' ),
					__( 'Telefonda panel metinlerinin punto değeri. Masaüstünden bağımsızdır.', 'qrms' )
				);
				$this->hfb_weight_row(
					'hfb_hamburger_font_weight_mobile',
					'hfb_hamburger_font_weight_mobile',
					(int) $opts['font_weight_mobile'],
					__( 'Yazı kalınlığı', 'qrms' ),
					__( 'Küçük boyutta bir kademe kalın okunurluğu artırır.', 'qrms' )
				);
				$this->hfb_align_row(
					'hfb_hamburger_font_align_mobile',
					'hfb_hamburger_font_align_mobile',
					(string) $opts['font_align_mobile'],
					__( 'Metin hizalama', 'qrms' ),
					__( 'Dar ekranda panel metinlerinin yaslanması.', 'qrms' )
				);
				?>
			</div>

			<?php $this->hfb_button_style_fields( 'hfb_hamburger_', $opts ); ?>
		</div>

		<?php
		$this->render_step_nav( 'hamburger' );
	}

	/**
	 * Sekme içi adım şeridi (vitrin rma-vitrin-steps deseninin hfb karşılığı).
	 *
	 * @param string                                $slug    Sekme slug'ı.
	 * @param array<int,array{0:string,1:string}> $adimlar No => [kısa etiket, tam başlık].
	 * @return void
	 */
	private function render_stepper_bar( $slug, $adimlar ) {
		$toplam = count( $adimlar );
		$ilk    = reset( $adimlar );
		?>
		<div class="hfb-steps" id="hfb-steps-<?php echo esc_attr( $slug ); ?>" role="tablist" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: tab name */ __( '%s ayar adımları', 'qrms' ), $slug ) ); ?>" data-hfb-stepper="<?php echo esc_attr( $slug ); ?>">
			<?php foreach ( $adimlar as $adim_no => $adim ) : ?>
				<button type="button" class="hfb-step-btn<?php echo 1 === (int) $adim_no ? ' is-active' : ''; ?>"
						data-step-target="<?php echo (int) $adim_no; ?>"
						role="tab" aria-selected="<?php echo 1 === (int) $adim_no ? 'true' : 'false'; ?>">
					<span class="hfb-step-num"><?php echo (int) $adim_no; ?></span>
					<span class="hfb-step-label"><?php echo esc_html( $adim[0] ); ?></span>
				</button>
			<?php endforeach; ?>
		</div>
		<p class="hfb-step-compact" data-hfb-step-compact="<?php echo esc_attr( $slug ); ?>">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: current step, 2: total steps, 3: step title */
					__( 'Adım %1$d/%2$d: %3$s', 'qrms' ),
					1,
					$toplam,
					$ilk[1]
				)
			);
			?>
		</p>
		<?php
	}

	/**
	 * Adım gezinme düğmeleri. Kaydet formun altındadır; burada yoktur.
	 *
	 * @param string $slug Sekme slug'ı.
	 * @return void
	 */
	private function render_step_nav( $slug ) {
		?>
		<div class="hfb-step-nav" data-hfb-step-nav="<?php echo esc_attr( $slug ); ?>">
			<button type="button" class="button hfb-step-prev" disabled>&larr; <?php esc_html_e( 'Geri Dön', 'qrms' ); ?></button>
			<button type="button" class="button button-primary hfb-step-next"><?php esc_html_e( 'Devam Et', 'qrms' ); ?> &rarr;</button>
		</div>
		<?php
	}

	/**
	 * Px slider satırı — vitrin_font_size_row() deseninin hfb karşılığı.
	 *
	 * @param string $id        Alan id'si.
	 * @param string $name      Form alanı adı.
	 * @param int    $deger     Mevcut değer.
	 * @param int    $min       Alt sınır (px).
	 * @param int    $max       Üst sınır (px).
	 * @param string $etiket    Satır başlığı.
	 * @param string $aciklama  Slider altındaki açıklama.
	 * @return void
	 */
	private function hfb_size_row( $id, $name, $deger, $min, $max, $etiket, $aciklama ) {
		?>
		<div class="qrms-field hfb-size-row">
			<label class="qrms-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $etiket ); ?></label>
			<div class="hfb-range-row">
				<input type="range" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>"
					   class="hfb-preview-trigger"
					   min="<?php echo (int) $min; ?>"
					   max="<?php echo (int) $max; ?>"
					   step="1"
					   value="<?php echo (int) $deger; ?>"
					   oninput="this.nextElementSibling.textContent=this.value+'px'">
				<span class="hfb-range-val"><?php echo (int) $deger; ?>px</span>
			</div>
			<p class="description"><?php echo esc_html( $aciklama ); ?></p>
		</div>
		<?php
	}

	/**
	 * Logo yüksekliği: otomatik oran kutusu + px slider.
	 *
	 * @param string $bp     desktop|tablet|mobile.
	 * @param int    $height Mevcut yükseklik.
	 * @param bool   $auto   Otomatik oran açık mı.
	 * @param string $prefix Form alanı öneki (hfb_header / hfb_footer).
	 * @return void
	 */
	private function hfb_logo_height_block( $bp, $height, $auto, $prefix = 'hfb_header' ) {
		$auto_id  = $prefix . '_logo_height_auto_' . $bp;
		$range_id = $prefix . '_logo_height_' . $bp;
		$shown    = $auto ? self::LOGO_HEIGHT_MIN : max( self::LOGO_HEIGHT_MIN, (int) $height );
		?>
		<div class="qrms-field">
			<label class="hfb-check-row">
				<input type="checkbox" name="<?php echo esc_attr( $auto_id ); ?>" id="<?php echo esc_attr( $auto_id ); ?>" value="1" class="hfb-preview-trigger hfb-logo-height-auto" data-hfb-height="<?php echo esc_attr( $range_id ); ?>" <?php checked( $auto ); ?> />
				<span><?php esc_html_e( 'Yükseklik otomatik oran', 'qrms' ); ?></span>
			</label>
			<p class="description"><?php esc_html_e( 'Açıkken yükseklik genişliğe göre korunur. Kapalıyken aşağıdaki kaydırıcıyı kullanın.', 'qrms' ); ?></p>
		</div>
		<div class="hfb-logo-height-row<?php echo $auto ? ' is-disabled' : ''; ?>">
			<?php
			$this->hfb_size_row(
				$range_id,
				$range_id,
				$shown,
				self::LOGO_HEIGHT_MIN,
				self::LOGO_HEIGHT_MAX,
				__( 'Logo yükseklik', 'qrms' ),
				__( 'Sabit yükseklik. Görsel oranı bozulmadan kutuya sığdırılır (object-fit: contain).', 'qrms' )
			);
			?>
		</div>
		<?php
	}

	/**
	 * wp-color-picker alanı — vitrin "Vitrin Arka Plan Rengi" deseninin aynısı.
	 *
	 * @param string $id          Alan id'si.
	 * @param string $name        Form alanı adı.
	 * @param string $value       Mevcut hex.
	 * @param string $label       Etiket.
	 * @param string $description Açıklama.
	 * @param string $default     data-default-color.
	 * @return void
	 */
	private function hfb_color_field( $id, $name, $value, $label, $description, $default ) {
		?>
		<div class="qrms-field hfb-color-field">
			<label class="qrms-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<input type="text" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>"
				   value="<?php echo esc_attr( $value ); ?>"
				   class="hfb-color-picker hfb-preview-trigger"
				   data-default-color="<?php echo esc_attr( $default ); ?>">
			<p class="description"><?php echo esc_html( $description ); ?></p>
		</div>
		<?php
	}

	/**
	 * Yazı kalınlığı açılır listesi.
	 *
	 * @param string $id        Alan id'si.
	 * @param string $name      Form alanı adı.
	 * @param int    $deger     Mevcut değer.
	 * @param string $etiket    Başlık.
	 * @param string $aciklama  Açıklama.
	 * @return void
	 */
	private function hfb_weight_row( $id, $name, $deger, $etiket, $aciklama ) {
		$secenekler = array(
			400 => '400 — Normal',
			500 => '500 — Medium',
			600 => '600 — Semibold',
			700 => '700 — Bold',
		);
		?>
		<div class="qrms-field">
			<label class="qrms-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $etiket ); ?></label>
			<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" class="qrms-input hfb-preview-trigger">
				<?php foreach ( $secenekler as $kalinlik => $kalinlik_etiket ) : ?>
					<option value="<?php echo (int) $kalinlik; ?>" <?php selected( (int) $deger, $kalinlik ); ?>><?php echo esc_html( $kalinlik_etiket ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php echo esc_html( $aciklama ); ?></p>
		</div>
		<?php
	}

	/**
	 * Sol/Orta/Sağ hizalama buton grubu — vitrin_align_row() deseninin hfb karşılığı.
	 *
	 * @param string $id       Grubun id ön eki.
	 * @param string $name     Form alanı adı.
	 * @param string $deger    Mevcut değer (left|center|right).
	 * @param string $etiket   Satır başlığı.
	 * @param string $aciklama Açıklama.
	 * @return void
	 */
	private function hfb_align_row( $id, $name, $deger, $etiket, $aciklama ) {
		$deger = in_array( $deger, array( 'left', 'center', 'right' ), true ) ? $deger : 'center';

		$secenekler = array(
			'left'   => array( 'Sol', array( 0, 0, 0 ) ),
			'center' => array( 'Orta', array( 0, 3, 1.5 ) ),
			'right'  => array( 'Sağ', array( 0, 6, 3 ) ),
		);
		?>
		<div class="qrms-field">
			<span class="qrms-label"><?php echo esc_html( $etiket ); ?></span>
			<div class="hfb-align-group" role="radiogroup" aria-label="<?php echo esc_attr( $etiket ); ?>">
				<?php
				foreach ( $secenekler as $hiza => $secenek ) :
					list( $hiza_etiket, $ofset ) = $secenek;
					$secili                      = $hiza === $deger;
					?>
					<label class="hfb-align-btn<?php echo $secili ? ' is-selected' : ''; ?>">
						<input type="radio" name="<?php echo esc_attr( $name ); ?>"
							   id="<?php echo esc_attr( $id . '-' . $hiza ); ?>"
							   class="hfb-align-input hfb-preview-trigger"
							   value="<?php echo esc_attr( $hiza ); ?>"
							   <?php checked( $secili ); ?>>
						<svg class="hfb-align-ic" viewBox="0 0 16 12" width="16" height="12" aria-hidden="true" focusable="false">
							<rect x="<?php echo esc_attr( (string) $ofset[0] ); ?>" y="1" width="16" height="2" rx="1"></rect>
							<rect x="<?php echo esc_attr( (string) $ofset[1] ); ?>" y="5" width="10" height="2" rx="1"></rect>
							<rect x="<?php echo esc_attr( (string) $ofset[2] ); ?>" y="9" width="13" height="2" rx="1"></rect>
						</svg>
						<span><?php echo esc_html( $hiza_etiket ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
			<p class="description"><?php echo esc_html( $aciklama ); ?></p>
		</div>
		<?php
	}

	/**
	 * Yazı tipi açılır listesi.
	 *
	 * @param string $id        Alan id'si.
	 * @param string $name      Form alanı adı.
	 * @param string $deger     Mevcut katalog anahtarı.
	 * @param string $etiket    Başlık.
	 * @param string $aciklama  Açıklama.
	 * @return void
	 */
	private function hfb_font_family_row( $id, $name, $deger, $etiket, $aciklama ) {
		?>
		<div class="qrms-field">
			<label class="qrms-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $etiket ); ?></label>
			<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" class="qrms-input hfb-preview-trigger">
				<?php foreach ( $this->font_catalog() as $font_key => $font_meta ) : ?>
					<option value="<?php echo esc_attr( $font_key ); ?>" <?php selected( $deger, $font_key ); ?>><?php echo esc_html( $font_meta['etiket'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php echo esc_html( $aciklama ); ?></p>
		</div>
		<?php
	}

	/**
	 * GENEL / MASAÜSTÜ / MOBİL tipografi bloğu.
	 *
	 * @param string              $form_prefix Form öneki (hfb_footer_).
	 * @param array<string,mixed> $opts        Ayarlar.
	 * @param string              $group       Option öneki (brand, links_title…).
	 * @param array<string,mixed> $args        title, color_label, hover_key, hover_label.
	 * @return void
	 */
	private function hfb_typo_block( $form_prefix, $opts, $group, $args = array() ) {
		$keys  = $this->typo_keys( $group );
		$title = isset( $args['title'] ) ? (string) $args['title'] : '';
		?>
		<div class="hfb-typo">
			<?php if ( '' !== $title ) : ?>
				<h3 class="hfb-section-title"><?php echo esc_html( $title ); ?></h3>
			<?php endif; ?>

			<h4 class="hfb-section-sub"><?php esc_html_e( 'Genel', 'qrms' ); ?></h4>
			<?php
			$this->hfb_font_family_row(
				$form_prefix . $keys['family'],
				$form_prefix . $keys['family'],
				(string) $opts[ $keys['family'] ],
				__( 'Yazı tipi', 'qrms' ),
				isset( $args['family_desc'] ) ? (string) $args['family_desc'] : __( 'Bu metin grubunun yazı tipi.', 'qrms' )
			);
			$this->hfb_weight_row(
				$form_prefix . $keys['weight'],
				$form_prefix . $keys['weight'],
				(int) $opts[ $keys['weight'] ],
				__( 'Yazı kalınlığı', 'qrms' ),
				__( '400 sakin, 600–700 daha vurgulu durur.', 'qrms' )
			);
			$this->hfb_color_field(
				$form_prefix . $keys['color'],
				$form_prefix . $keys['color'],
				(string) $opts[ $keys['color'] ],
				isset( $args['color_label'] ) ? (string) $args['color_label'] : __( 'Yazı rengi', 'qrms' ),
				isset( $args['color_desc'] ) ? (string) $args['color_desc'] : __( 'Bu metin grubunun rengi.', 'qrms' ),
				(string) $opts[ $keys['color'] ]
			);

			if ( ! empty( $args['hover_key'] ) ) {
				$hover = (string) $args['hover_key'];
				$this->hfb_color_field(
					$form_prefix . $hover,
					$form_prefix . $hover,
					(string) $opts[ $hover ],
					isset( $args['hover_label'] ) ? (string) $args['hover_label'] : __( 'Hover rengi', 'qrms' ),
					isset( $args['hover_desc'] ) ? (string) $args['hover_desc'] : __( 'İmleç üzerine gelince kullanılan renk.', 'qrms' ),
					(string) $opts[ $hover ]
				);
			}
			?>

			<h4 class="hfb-section-sub"><?php esc_html_e( 'Masaüstü', 'qrms' ); ?></h4>
			<div class="hfb-size-group" data-hfb-preview-bp="desktop">
				<?php
				$this->hfb_size_row(
					$form_prefix . $keys['size_desktop'],
					$form_prefix . $keys['size_desktop'],
					(int) $opts[ $keys['size_desktop'] ],
					self::FONT_SIZE_MIN,
					self::FONT_SIZE_MAX,
					__( 'Yazı boyutu', 'qrms' ),
					__( 'Geniş ekranda punto değeri.', 'qrms' )
				);
				?>
			</div>

			<h4 class="hfb-section-sub"><?php esc_html_e( 'Mobil', 'qrms' ); ?></h4>
			<div class="hfb-size-group" data-hfb-preview-bp="mobile">
				<?php
				$this->hfb_size_row(
					$form_prefix . $keys['size_mobile'],
					$form_prefix . $keys['size_mobile'],
					(int) $opts[ $keys['size_mobile'] ],
					self::FONT_SIZE_MOBILE_MIN,
					self::FONT_SIZE_MOBILE_MAX,
					__( 'Yazı boyutu', 'qrms' ),
					__( 'Telefonda punto değeri. Masaüstünden bağımsızdır.', 'qrms' )
				);
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Buton şekli (hap / yuvarlatılmış / köşeli).
	 *
	 * @param string $id     Grubun id ön eki.
	 * @param string $name   Form alanı adı.
	 * @param string $deger  Mevcut şekil.
	 * @param string $etiket Başlık.
	 * @return void
	 */
	private function hfb_shape_row( $id, $name, $deger, $etiket ) {
		$map   = $this->button_shape_map();
		$deger = array_key_exists( $deger, $map ) ? $deger : 'pill';
		?>
		<div class="qrms-field">
			<span class="qrms-label"><?php echo esc_html( $etiket ); ?></span>
			<div class="hfb-align-group" role="radiogroup" aria-label="<?php echo esc_attr( $etiket ); ?>">
				<?php foreach ( $map as $shape => $meta ) : ?>
					<label class="hfb-align-btn<?php echo $shape === $deger ? ' is-selected' : ''; ?>">
						<input type="radio" name="<?php echo esc_attr( $name ); ?>"
							   id="<?php echo esc_attr( $id . '-' . $shape ); ?>"
							   class="hfb-align-input hfb-preview-trigger"
							   value="<?php echo esc_attr( $shape ); ?>"
							   <?php checked( $shape === $deger ); ?>>
						<span><?php echo esc_html( $meta['etiket'] ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
			<p class="description"><?php esc_html_e( 'Hap tamamen yuvarlak, yuvarlatılmış hafif kavisli, köşeli ise düz köşelidir.', 'qrms' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Paylaşılan buton stil alanları — hamburger CTA ve footer garson/hesap.
	 *
	 * @param string              $form_prefix Form öneki (hfb_footer_ / hfb_hamburger_).
	 * @param array<string,mixed> $opts        btn_* anahtarlarını taşıyan ayarlar.
	 * @return void
	 */
	private function hfb_button_style_fields( $form_prefix, $opts ) {
		?>
		<h3 class="hfb-section-title"><?php esc_html_e( 'Buton stili', 'qrms' ); ?></h3>
		<?php
		$this->hfb_color_field(
			$form_prefix . 'btn_bg_color',
			$form_prefix . 'btn_bg_color',
			(string) $opts['btn_bg_color'],
			__( 'Arka plan rengi', 'qrms' ),
			__( 'Butonun zemin rengi.', 'qrms' ),
			'#c9a84c'
		);
		$this->hfb_color_field(
			$form_prefix . 'btn_text_color',
			$form_prefix . 'btn_text_color',
			(string) $opts['btn_text_color'],
			__( 'Yazı rengi', 'qrms' ),
			__( 'Buton üzerindeki metnin rengi.', 'qrms' ),
			'#0a0a0c'
		);
		$this->hfb_shape_row(
			$form_prefix . 'btn_shape',
			$form_prefix . 'btn_shape',
			(string) $opts['btn_shape'],
			__( 'Şekil', 'qrms' )
		);
		$this->hfb_font_family_row(
			$form_prefix . 'btn_font_family',
			$form_prefix . 'btn_font_family',
			(string) $opts['btn_font_family'],
			__( 'Yazı tipi', 'qrms' ),
			__( 'Buton metninin yazı tipi.', 'qrms' )
		);
		$this->hfb_size_row(
			$form_prefix . 'btn_font_size',
			$form_prefix . 'btn_font_size',
			(int) $opts['btn_font_size'],
			self::FONT_SIZE_MIN,
			self::FONT_SIZE_MAX,
			__( 'Yazı boyutu', 'qrms' ),
			__( 'Buton metninin punto değeri.', 'qrms' )
		);
		$this->hfb_weight_row(
			$form_prefix . 'btn_font_weight',
			$form_prefix . 'btn_font_weight',
			(int) $opts['btn_font_weight'],
			__( 'Yazı kalınlığı', 'qrms' ),
			__( 'Buton yazısının kalınlığı.', 'qrms' )
		);
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
		$this->enqueue_header_script();

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script( 'jquery-ui-sortable' );

		wp_enqueue_style(
			'hfb-admin',
			QRMS_PLUGIN_URL . $base . 'css/admin.css',
			array( 'qrms-admin', 'wp-color-picker' ),
			QRMS_Helpers::asset_version( $base . 'css/admin.css' )
		);

		wp_enqueue_script(
			'hfb-admin',
			QRMS_PLUGIN_URL . $base . 'js/admin.js',
			array( 'jquery', 'wp-color-picker', 'jquery-ui-sortable', 'hfb-frontend' ),
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
					'updating'   => __( 'Güncelleniyor…', 'qrms' ),
					'error'      => __( 'Önizleme güncellenemedi.', 'qrms' ),
					'openPanel'  => __( 'Önizlemede Aç', 'qrms' ),
					'closePanel' => __( 'Önizlemede Kapat', 'qrms' ),
				),
			)
		);
	}
}
