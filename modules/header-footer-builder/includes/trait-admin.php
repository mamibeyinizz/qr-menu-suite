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
			4 => array( 'Yerleşim', 'Yerleşim / Boşluklar' ),
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

		<div class="qrms-card hfb-step" data-step="4" data-step-title="<?php esc_attr_e( 'Yerleşim / Boşluklar', 'qrms' ); ?>" style="display:none;">
			<h2 class="qrms-card-title"><?php esc_html_e( '4. Yerleşim / Boşluklar', 'qrms' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Header içeriğinin sayfada kapladığı genişlik ve kenarlardan bıraktığı boşluk. Masaüstü ve mobil için ayrı boşluk setleri vardır; sağdaki Masaüstü/Mobil Önizleme düğmeleriyle ikisini de görebilirsiniz.', 'qrms' ); ?>
			</p>

			<h3 class="hfb-section-title"><?php esc_html_e( 'Genel', 'qrms' ); ?></h3>
			<div class="qrms-field">
				<label class="hfb-check-row">
					<input type="checkbox" name="hfb_header_content_full_width" id="hfb_header_content_full_width" value="1" class="hfb-preview-trigger hfb-full-width-toggle" data-hfb-width="hfb_header_content_width" <?php checked( ! empty( $opts['content_full_width'] ) ); ?> />
					<span><?php esc_html_e( 'Tam genişlik (içerik ekranın tamamına yayılsın)', 'qrms' ); ?></span>
				</label>
				<p class="description"><?php esc_html_e( 'Açıkken maksimum genişlik sınırı kalkar; aşağıdaki kaydırıcı devre dışı kalır.', 'qrms' ); ?></p>
			</div>

			<div class="hfb-content-width-row<?php echo ! empty( $opts['content_full_width'] ) ? ' is-disabled' : ''; ?>">
				<?php
				$this->hfb_size_row(
					'hfb_header_content_width',
					'hfb_header_content_width',
					(int) $opts['content_width'],
					self::CONTENT_WIDTH_MIN,
					self::CONTENT_WIDTH_MAX,
					__( 'İçerik maksimum genişliği', 'qrms' ),
					__( 'Header içeriğinin ortalanacağı en geniş ölçü. Sayfa gövdenizin genişliğiyle aynı tutmak hizayı bozmaz.', 'qrms' )
				);
				?>
			</div>

			<h3 class="hfb-section-title"><?php esc_html_e( 'Masaüstü', 'qrms' ); ?></h3>
			<div class="hfb-size-group" data-hfb-preview-bp="desktop">
				<?php
				$this->hfb_size_row(
					'hfb_header_padding_x_desktop',
					'hfb_header_padding_x_desktop',
					(int) $opts['padding_x_desktop'],
					self::PADDING_X_MIN,
					self::PADDING_X_MAX,
					__( 'Sol/sağ iç boşluk', 'qrms' ),
					__( 'Header içeriğinin sol ve sağ kenardan uzaklığı.', 'qrms' )
				);
				$this->hfb_size_row(
					'hfb_header_padding_y_desktop',
					'hfb_header_padding_y_desktop',
					(int) $opts['padding_y_desktop'],
					self::PADDING_Y_MIN,
					self::PADDING_Y_MAX,
					__( 'Üst/alt iç boşluk', 'qrms' ),
					__( 'Header çubuğunun yüksekliğini belirleyen dikey boşluk.', 'qrms' )
				);
				?>
			</div>

			<h3 class="hfb-section-title"><?php esc_html_e( 'Mobil', 'qrms' ); ?></h3>
			<div class="hfb-size-group" data-hfb-preview-bp="mobile">
				<?php
				$this->hfb_size_row(
					'hfb_header_padding_x_mobile',
					'hfb_header_padding_x_mobile',
					(int) $opts['padding_x_mobile'],
					self::PADDING_X_MIN,
					self::PADDING_X_MOBILE_MAX,
					__( 'Sol/sağ iç boşluk', 'qrms' ),
					__( 'Telefonda kenar boşluğu. Dar ekranda masaüstünden küçük tutmak taşmayı önler.', 'qrms' )
				);
				$this->hfb_size_row(
					'hfb_header_padding_y_mobile',
					'hfb_header_padding_y_mobile',
					(int) $opts['padding_y_mobile'],
					self::PADDING_Y_MIN,
					self::PADDING_Y_MOBILE_MAX,
					__( 'Üst/alt iç boşluk', 'qrms' ),
					__( 'Telefonda header çubuğunun dikey boşluğu.', 'qrms' )
				);
				?>
			</div>
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
			3 => array( 'Saatler', 'Çalışma Saatleri' ),
			4 => array( 'İletişim', 'İletişim Bilgileri' ),
			5 => array( 'Çağrı', 'Garson / Hesap Butonu' ),
		);

		$this->render_stepper_bar( 'footer', $adimlar );
		?>
		<div class="qrms-card hfb-step" data-step="1" data-step-title="<?php esc_attr_e( 'Logo ve Slogan', 'qrms' ); ?>">
			<h2 class="qrms-card-title"><?php esc_html_e( '1. Logo ve Slogan', 'qrms' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Footer\'ın 1. sütunu: logo, marka adı ve kısa açıklama.', 'qrms' ); ?></p>

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
					'title'        => __( 'Slogan ve açıklama yazısı', 'qrms' ),
					'desc'         => __( 'Sol sütundaki marka adı ve kısa açıklama', 'qrms' ),
					'color_label'  => __( 'Slogan yazı rengi', 'qrms' ),
					'size_label'   => __( 'Slogan yazı boyutu', 'qrms' ),
					'family_label' => __( 'Slogan yazı tipi', 'qrms' ),
					'weight_label' => __( 'Slogan yazı kalınlığı', 'qrms' ),
				)
			);
			?>
		</div>

		<div class="qrms-card hfb-step" data-step="2" data-step-title="<?php esc_attr_e( 'Hızlı Menü', 'qrms' ); ?>" style="display:none;">
			<h2 class="qrms-card-title"><?php esc_html_e( '2. Hızlı Menü', 'qrms' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Footer\'ın 2. sütunundaki başlık ve hızlı menü listesi.', 'qrms' ); ?></p>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_footer_links_title"><?php esc_html_e( 'Sütun başlığı', 'qrms' ); ?></label>
				<input type="text" id="hfb_footer_links_title" name="hfb_footer_links_title" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( $opts['links_title'] ); ?>" placeholder="<?php esc_attr_e( 'Hızlı Menü', 'qrms' ); ?>" />
				<?php $this->hfb_ceviri_bayat_uyari( 'hfb_footer.links_title' ); ?>
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
					'title'        => __( 'Başlık yazısı', 'qrms' ),
					'desc'         => __( 'Hızlı Menü sütununun başlık satırı', 'qrms' ),
					'color_label'  => __( 'Başlık yazı rengi', 'qrms' ),
					'size_label'   => __( 'Başlık yazı boyutu', 'qrms' ),
					'family_label' => __( 'Başlık yazı tipi', 'qrms' ),
					'weight_label' => __( 'Başlık yazı kalınlığı', 'qrms' ),
				)
			);
			$this->hfb_typo_block(
				'hfb_footer_',
				$opts,
				'links_item',
				array(
					'title'        => __( 'Menü bağlantıları', 'qrms' ),
					'desc'         => __( 'Hızlı Menü listesindeki link satırları (başlık DEĞİL)', 'qrms' ),
					'color_label'  => __( 'Link yazı rengi', 'qrms' ),
					'size_label'   => __( 'Link yazı boyutu', 'qrms' ),
					'family_label' => __( 'Link yazı tipi', 'qrms' ),
					'weight_label' => __( 'Link yazı kalınlığı', 'qrms' ),
					'hover_key'    => 'links_item_hover_color',
					'hover_label'  => __( 'Link hover rengi', 'qrms' ),
					'hover_desc'   => __( 'Bağlantının üzerine gelince kullanılan renk.', 'qrms' ),
				)
			);
			?>
		</div>

		<div class="qrms-card hfb-step" data-step="3" data-step-title="<?php esc_attr_e( 'Çalışma Saatleri', 'qrms' ); ?>" style="display:none;">
			<h2 class="qrms-card-title"><?php esc_html_e( '3. Çalışma Saatleri', 'qrms' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Footer\'ın 3. sütunundaki gün/saat listesi.', 'qrms' ); ?></p>

			<div class="hfb-subpanel">
				<h3 class="hfb-subpanel__title"><?php esc_html_e( 'Çalışma Saatleri', 'qrms' ); ?></h3>
				<?php if ( $this->hours_module_available() ) : ?>
					<p class="description"><?php esc_html_e( 'Gün/saat listesi modülden gelir; burada yalnızca görünüm ayarlanır.', 'qrms' ); ?></p>
					<div class="qrms-field">
						<label class="qrms-label" for="hfb_footer_hours_title"><?php esc_html_e( 'Sütun başlığı', 'qrms' ); ?></label>
						<input type="text" id="hfb_footer_hours_title" name="hfb_footer_hours_title" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( $opts['hours_title'] ); ?>" placeholder="<?php esc_attr_e( 'Çalışma Saatlerimiz', 'qrms' ); ?>" />
						<?php $this->hfb_ceviri_bayat_uyari( 'hfb_footer.hours_title' ); ?>
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
							'title'        => __( 'Başlık yazısı', 'qrms' ),
							'desc'         => __( 'Saat sütununun başlık satırı', 'qrms' ),
							'color_label'  => __( 'Başlık yazı rengi', 'qrms' ),
							'size_label'   => __( 'Başlık yazı boyutu', 'qrms' ),
							'family_label' => __( 'Başlık yazı tipi', 'qrms' ),
							'weight_label' => __( 'Başlık yazı kalınlığı', 'qrms' ),
						)
					);
					$this->hfb_typo_block(
						'hfb_footer_',
						$opts,
						'hours_item',
						array(
							'title'        => __( 'Gün ve saat metinleri', 'qrms' ),
							'desc'         => __( 'Gün adı ve saat aralığı satırları', 'qrms' ),
							'color_label'  => __( 'Gün/saat yazı rengi', 'qrms' ),
							'size_label'   => __( 'Gün/saat yazı boyutu', 'qrms' ),
							'family_label' => __( 'Gün/saat yazı tipi', 'qrms' ),
							'weight_label' => __( 'Gün/saat yazı kalınlığı', 'qrms' ),
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
		</div>

		<div class="qrms-card hfb-step" data-step="4" data-step-title="<?php esc_attr_e( 'İletişim Bilgileri', 'qrms' ); ?>" style="display:none;">
			<h2 class="qrms-card-title"><?php esc_html_e( '4. İletişim Bilgileri', 'qrms' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Footer\'ın 4. sütunundaki adres, telefon ve sosyal ikonlar.', 'qrms' ); ?></p>

			<div class="hfb-subpanel">
				<h3 class="hfb-subpanel__title"><?php esc_html_e( 'İletişim', 'qrms' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Footer\'ın 4. sütunundaki adres/telefon/sosyal ikonlar.', 'qrms' ); ?></p>
				<div class="qrms-field">
					<label class="qrms-label" for="hfb_footer_contact_title"><?php esc_html_e( 'Sütun başlığı', 'qrms' ); ?></label>
					<input type="text" id="hfb_footer_contact_title" name="hfb_footer_contact_title" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( $opts['contact_title'] ); ?>" placeholder="<?php esc_attr_e( 'İletişim', 'qrms' ); ?>" />
					<?php $this->hfb_ceviri_bayat_uyari( 'hfb_footer.contact_title' ); ?>
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

				<p class="description"><?php esc_html_e( '4. sütundaki sosyal ikonlar; en fazla 6 tanesi gösterilir.', 'qrms' ); ?></p>
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
						'title'        => __( 'Başlık yazısı', 'qrms' ),
						'desc'         => __( 'İletişim sütununun başlık satırı', 'qrms' ),
						'color_label'  => __( 'Başlık yazı rengi', 'qrms' ),
						'size_label'   => __( 'Başlık yazı boyutu', 'qrms' ),
						'family_label' => __( 'Başlık yazı tipi', 'qrms' ),
						'weight_label' => __( 'Başlık yazı kalınlığı', 'qrms' ),
					)
				);
				$this->hfb_typo_block(
					'hfb_footer_',
					$opts,
					'contact_item',
					array(
						'title'        => __( 'Adres, telefon ve e-posta satırları', 'qrms' ),
						'desc'         => __( 'Adres, telefon, e-posta ve telif satırları', 'qrms' ),
						'color_label'  => __( 'İletişim satır yazı rengi', 'qrms' ),
						'size_label'   => __( 'İletişim satır yazı boyutu', 'qrms' ),
						'family_label' => __( 'İletişim satır yazı tipi', 'qrms' ),
						'weight_label' => __( 'İletişim satır yazı kalınlığı', 'qrms' ),
					)
				);
				?>
			</div>
		</div>

		<div class="qrms-card hfb-step" data-step="5" data-step-title="<?php esc_attr_e( 'Garson / Hesap Butonu', 'qrms' ); ?>" style="display:none;">
			<h2 class="qrms-card-title"><?php esc_html_e( '5. Garson / Hesap Butonu', 'qrms' ); ?></h2>
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
				<?php $this->hfb_ceviri_bayat_uyari( 'hfb_footer.call_garson_label' ); ?>
			</div>

			<div class="qrms-field">
				<label class="qrms-label" for="hfb_footer_call_hesap_label"><?php esc_html_e( 'Hesap butonu metni', 'qrms' ); ?></label>
				<input type="text" id="hfb_footer_call_hesap_label" name="hfb_footer_call_hesap_label" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( $opts['call_hesap_label'] ); ?>" />
				<?php $this->hfb_ceviri_bayat_uyari( 'hfb_footer.call_hesap_label' ); ?>
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
					<span><?php esc_html_e( 'Dil Seçeneğini Masaüstü Header\'da Göster', 'qrms' ); ?></span>
				</label>
				<p class="description">
					<?php esc_html_e( 'Açıkken bayrak masaüstünde header\'ın en sağında görünür. Kapalıyken masaüstü header\'da görünmez.', 'qrms' ); ?>
				</p>
			</div>

			<div class="qrms-field">
				<label class="hfb-check-row">
					<input type="checkbox" name="hfb_lang_mobile_show" value="1" class="hfb-preview-trigger" <?php checked( ! empty( $opts['lang_mobile_show'] ) ); ?><?php echo $available ? '' : ' disabled="disabled"'; ?> />
					<span><?php esc_html_e( 'Dil Bayrağını Mobil Header\'da Göster', 'qrms' ); ?></span>
				</label>
				<p class="description">
					<?php esc_html_e( 'Açıkken mobil header\'da solda kompakt bayrak, ortada logo ve sağda hamburger görünür. Offcanvas menüdeki dil seçici ayrıca kalır.', 'qrms' ); ?>
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
			3 => array( 'Görünüm', 'Panel Görünümü' ),
			4 => array( 'Yazı', 'Yazı Tipi ve Renk' ),
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
				<?php esc_html_e( 'Açılan panelde hangi blokların görüneceğini işaretleyin ve sürükleyerek sıralayın. İşaretsiz blok panelde hiç görünmez. Aynı tipten birden fazla blok ekleyebilirsiniz. Sıra değişince sağdaki önizleme anında güncellenir.', 'qrms' ); ?>
			</p>

			<?php
			$types  = $this->hamburger_block_types();
			$blocks = isset( $opts['blocks'] ) && is_array( $opts['blocks'] ) ? $opts['blocks'] : array();
			$order  = wp_list_pluck( $blocks, 'id' );
			?>

			<input type="hidden" name="hfb_hamburger_block_order" id="hfb_hamburger_block_order" class="hfb-preview-trigger" value="<?php echo esc_attr( implode( ',', array_map( 'strval', $order ) ) ); ?>" />

			<ul class="hfb-block-sortable" id="hfb-block-sortable">
				<?php
				$social_fields_rendered = false;
				foreach ( $blocks as $block ) :
					$this->render_hamburger_block_item( $block, $header_opts, $menus, $types, false, $social_fields_rendered );
				endforeach;
				?>
			</ul>

			<div class="hfb-block-add">
				<div class="hfb-block-add__menu" id="hfb-block-add-menu" hidden>
					<?php foreach ( $types as $type_key => $type_label ) : ?>
						<button type="button" class="button hfb-block-add-type" data-block-type="<?php echo esc_attr( $type_key ); ?>">
							<?php echo esc_html( $type_label ); ?>
						</button>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button button-secondary" id="hfb-block-add-toggle" aria-expanded="false" aria-controls="hfb-block-add-menu">
					<?php esc_html_e( 'Yeni Blok Ekle', 'qrms' ); ?>
				</button>
			</div>

			<div id="hfb-block-templates" hidden aria-hidden="true">
				<?php
				foreach ( $types as $type_key => $type_label ) {
					$template_block = $this->default_hamburger_block(
						$type_key,
						'__ID__',
						array(
							'enabled' => true,
							'align'   => 'center',
							'content' => '',
							'label'   => __( 'Buton', 'qrms' ),
						)
					);
					echo '<template id="hfb-block-tpl-' . esc_attr( $type_key ) . '">';
					$this->render_hamburger_block_item( $template_block, $header_opts, $menus, $types, true );
					echo '</template>';
				}
				?>
			</div>
		</div>

		<div class="qrms-card hfb-step" data-step="3" data-step-title="<?php esc_attr_e( 'Panel Görünümü', 'qrms' ); ?>" style="display:none;">
			<h2 class="qrms-card-title"><?php esc_html_e( '3. Panel Görünümü', 'qrms' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Bloklar adımı panelde NE görüneceğini belirler; bu adım NASIL görüneceğini. Buradaki her ayar yalnızca hamburger panelini etkiler — header ve footer kendi ayarlarını kullanmaya devam eder. Değişiklikler kaydetmeden sağdaki önizlemede görünür; paneli açmak için "Önizlemede Aç" düğmesini kullanın.', 'qrms' ); ?>
			</p>

			<h3 class="hfb-section-title"><?php esc_html_e( 'Genel', 'qrms' ); ?></h3>
			<div class="hfb-notice">
				<p><?php esc_html_e( 'Panel arka plan RENGİ ve kapatma (X) ikonu rengi 1. Açılış adımında ayarlanır; burada tekrarlanmaz. Aşağıdaki arka plan görseli, o renkli zeminin üzerine biner.', 'qrms' ); ?></p>
			</div>

			<?php
			$this->render_media_field(
				'hfb_hamburger_panel_bg_image',
				__( 'Panel arka plan görseli', 'qrms' ),
				(int) $opts['panel_bg_image']
			);
			?>
			<p class="description">
				<?php esc_html_e( 'İsteğe bağlıdır. Seçilen görsel panelin zemin rengi üzerine tam kaplayan bir örtü olarak biner. "Kaldır" düğmesi görseli tamamen kapatır; geriye yalnızca zemin rengi kalır.', 'qrms' ); ?>
			</p>

			<?php
			$this->hfb_percent_row(
				'hfb_hamburger_panel_bg_opacity',
				'hfb_hamburger_panel_bg_opacity',
				(int) $opts['panel_bg_opacity'],
				self::PANEL_BG_OPACITY_MIN,
				self::PANEL_BG_OPACITY_MAX,
				__( 'Arka plan görseli opaklığı', 'qrms' ),
				__( 'Düşük değerlerde zemin rengi görselin altından okunur; %100 görselin zemini tümüyle kapatması demektir. Görsel seçilmemişse etkisizdir.', 'qrms' )
			);
			?>

			<h3 class="hfb-section-title"><?php esc_html_e( 'Panel içi logo boyutu', 'qrms' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Panelin logo bloğundaki görselin ölçüsü. Header sekmesindeki logo boyutundan bağımsızdır — biri değişince diğeri değişmez. Panel yalnızca mobilde açıldığı için tek ölçü yeterlidir; masaüstü/mobil ayrımı yoktur.', 'qrms' ); ?>
			</p>

			<div class="hfb-size-group" data-hfb-preview-bp="mobile">
				<?php
				$this->hfb_size_row(
					'hfb_hamburger_logo_width',
					'hfb_hamburger_logo_width',
					(int) $opts['logo_width'],
					self::PANEL_LOGO_WIDTH_MIN,
					self::LOGO_WIDTH_MAX,
					__( 'Logo genişlik', 'qrms' ),
					__( 'Panel logosunun genişliği. Header logosundan daha küçük değerler seçilebilir.', 'qrms' )
				);
				$this->hfb_logo_height_block(
					'',
					(int) $opts['logo_height'],
					! empty( $opts['logo_height_auto'] ),
					'hfb_hamburger'
				);
				?>
			</div>

			<h3 class="hfb-section-title"><?php esc_html_e( 'Liste / menü satırı renkleri', 'qrms' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Menü bloğundaki satırların renkleri. Yazı tipi ve punto 4. Yazı adımından gelir; burada yalnızca renkler ayarlanır.', 'qrms' ); ?>
			</p>

			<?php
			$this->hfb_color_field(
				'hfb_hamburger_menu_link_color',
				'hfb_hamburger_menu_link_color',
				(string) $opts['menu_link_color'],
				__( 'Satır metin rengi', 'qrms' ),
				__( 'Menü bağlantılarının duruş hâlindeki rengi.', 'qrms' ),
				'#f5f0e8'
			);
			$this->hfb_color_field(
				'hfb_hamburger_menu_hover_color',
				'hfb_hamburger_menu_hover_color',
				(string) $opts['menu_hover_color'],
				__( 'Satır hover / aktif rengi', 'qrms' ),
				__( 'Üzerine gelindiğinde ya da klavyeyle odaklanıldığında satırın rengi. Satır zemini de bu rengin soluk bir tonuyla boyanır.', 'qrms' ),
				'#c9a84c'
			);
			$this->hfb_color_field(
				'hfb_hamburger_menu_divider_color',
				'hfb_hamburger_menu_divider_color',
				(string) $opts['menu_divider_color'],
				__( 'Ayraç çizgisi rengi', 'qrms' ),
				__( 'Satırlar arasındaki ince çizgi (border-bottom). Çizgi seçilen rengin soluk tonuyla basılır; \'ince ayraç\' görünümü korunur.', 'qrms' ),
				'#c9a84c'
			);
			$this->hfb_color_field(
				'hfb_hamburger_menu_arrow_color',
				'hfb_hamburger_menu_arrow_color',
				(string) $opts['menu_arrow_color'],
				__( 'Sağdaki ok ikonu rengi', 'qrms' ),
				__( 'Satırların sağ ucundaki ok ve alt menüsü olan satırlardaki açılır ok bu rengi kullanır.', 'qrms' ),
				'#c9a84c'
			);
			?>

			<h3 class="hfb-section-title"><?php esc_html_e( 'Sosyal medya ikon renkleri', 'qrms' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Panelin sosyal bloğundaki daire ikonlar. Header\'ın sağ ucundaki ikonlardan bağımsızdır; onlar Header sekmesindeki ikon rengini kullanır.', 'qrms' ); ?>
			</p>

			<?php
			$this->hfb_color_field(
				'hfb_hamburger_social_border_color',
				'hfb_hamburger_social_border_color',
				(string) $opts['social_border_color'],
				__( 'İkon çerçeve rengi', 'qrms' ),
				__( 'Daireyi çevreleyen 1px çerçevenin rengi.', 'qrms' ),
				'#c9a84c'
			);
			$this->hfb_color_field(
				'hfb_hamburger_social_bg_color',
				'hfb_hamburger_social_bg_color',
				(string) $opts['social_bg_color'],
				__( 'İkon arka plan rengi', 'qrms' ),
				__( 'Dairenin içini dolduran renk. Boş bırakılırsa (renk seçicideki "Temizle") zemin şeffaf kalır — varsayılan budur.', 'qrms' ),
				''
			);
			$this->hfb_color_field(
				'hfb_hamburger_social_icon_color',
				'hfb_hamburger_social_icon_color',
				(string) $opts['social_icon_color'],
				__( 'İkon rengi', 'qrms' ),
				__( 'Dairenin içindeki logonun (glyph) rengi. Üzerine gelindiğinde bu renk zemine, panel arka plan rengi glyph\'e geçer.', 'qrms' ),
				'#c9a84c'
			);
			?>

			<h3 class="hfb-section-title"><?php esc_html_e( 'Buton bloğu varsayılanları', 'qrms' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Panele yeni eklenen buton blokları bu ayarlarla başlar. Her buton bloğu 2. Bloklar adımında kendi rengini, şeklini ve yazı tipini belirleyip bu varsayılanı ezebilir.', 'qrms' ); ?>
			</p>

			<?php $this->hfb_button_style_fields( 'hfb_hamburger_', $opts, '' ); ?>
		</div>

		<div class="qrms-card hfb-step" data-step="4" data-step-title="<?php esc_attr_e( 'Yazı Tipi ve Renk', 'qrms' ); ?>" style="display:none;">
			<h2 class="qrms-card-title"><?php esc_html_e( '4. Yazı Tipi ve Renk', 'qrms' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Hamburger panelindeki tüm metinler — menü bağlantıları, metin bloğu — bu ayarları kullanır. Panel yalnızca mobilde açıldığı için tek bir ayar seti vardır; masaüstü/mobil ayrımı yoktur.', 'qrms' ); ?>
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

			<h3 class="hfb-section-title"><?php esc_html_e( 'Boyut, kalınlık ve hizalama', 'qrms' ); ?></h3>
			<div class="hfb-size-group" data-hfb-preview-bp="mobile">
				<?php
				$this->hfb_size_row(
					'hfb_hamburger_font_size',
					'hfb_hamburger_font_size',
					(int) $opts['font_size'],
					self::FONT_SIZE_MIN,
					self::FONT_SIZE_MAX,
					__( 'Yazı boyutu', 'qrms' ),
					__( 'Panel metinlerinin punto değeri.', 'qrms' )
				);
				$this->hfb_weight_row(
					'hfb_hamburger_font_weight',
					'hfb_hamburger_font_weight',
					(int) $opts['font_weight'],
					__( 'Yazı kalınlığı', 'qrms' ),
					__( '400 sakin, 600–700 daha vurgulu durur.', 'qrms' )
				);
				$this->hfb_align_row(
					'hfb_hamburger_font_align',
					'hfb_hamburger_font_align',
					(string) $opts['font_align'],
					__( 'Metin hizalama', 'qrms' ),
					__( 'Panel metinlerinin yaslanması.', 'qrms' )
				);
				?>
			</div>
		</div>

		<?php
		$this->render_step_nav( 'hamburger' );
	}

	/**
	 * Hamburger paneli için tek bir blok satırı.
	 *
	 * @param array<string,mixed> $block       Blok verisi.
	 * @param array<string,mixed> $header_opts Header ayarları.
	 * @param array<int,string>   $menus       Menü listesi.
	 * @param array<string,string> $types      Blok tip etiketleri.
	 * @param bool                $template    Şablon çıktısı mı (id yer tutuculu).
	 * @param bool                $social_fields_rendered Sosyal alanlar basıldı mı (referans).
	 * @return void
	 */
	private function render_hamburger_block_item( $block, $header_opts, $menus, $types, $template = false, &$social_fields_rendered = false ) {
		$id      = isset( $block['id'] ) ? (string) $block['id'] : '';
		$type    = isset( $block['type'] ) ? (string) $block['type'] : '';
		$enabled = ! empty( $block['enabled'] );
		$align   = isset( $block['align'] ) ? (string) $block['align'] : 'center';
		$label   = isset( $types[ $type ] ) ? $types[ $type ] : $type;
		$prefix  = 'hfb_hamburger_blocks[' . $id . ']';
		?>
		<li class="hfb-block-item" data-block-id="<?php echo esc_attr( $id ); ?>" data-block-type="<?php echo esc_attr( $type ); ?>">
			<span class="hfb-block-drag" aria-hidden="true">⋮⋮</span>
			<div class="hfb-block-item__body">
				<div class="hfb-block-item__head">
					<label class="hfb-check-row hfb-block-item__title">
						<input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[enabled]" value="1" class="hfb-preview-trigger" <?php checked( $enabled ); ?> />
						<strong><?php echo esc_html( $label ); ?></strong>
					</label>
					<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[type]" value="<?php echo esc_attr( $type ); ?>" />
					<?php $this->hfb_block_align_row( $prefix . '[align]', $align, $id ); ?>
					<button type="button" class="button-link hfb-block-delete" title="<?php esc_attr_e( 'Bloğu sil', 'qrms' ); ?>" aria-label="<?php esc_attr_e( 'Bloğu sil', 'qrms' ); ?>">
						<span class="dashicons dashicons-trash" aria-hidden="true"></span>
					</button>
				</div>

				<?php if ( 'menu' === $type ) : ?>
					<div class="qrms-field">
						<label class="qrms-label" for="hfb_header_menu_id_<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'WordPress menüsü', 'qrms' ); ?></label>
						<select id="hfb_header_menu_id_<?php echo esc_attr( $id ); ?>" name="hfb_header_menu_id" class="qrms-input hfb-preview-trigger">
							<?php foreach ( $menus as $menu_id => $name ) : ?>
								<option value="<?php echo esc_attr( (string) $menu_id ); ?>" <?php selected( (int) $header_opts['menu_id'], (int) $menu_id ); ?>><?php echo esc_html( $name ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Aynı menü masaüstü header\'ında da kullanılır.', 'qrms' ); ?></p>
					</div>
				<?php elseif ( 'social' === $type ) : ?>
					<p class="description"><?php esc_html_e( 'Header\'ın sağ ucunda ve hamburger panelinde altın çerçeveli daireler olarak görünür. En fazla 6 tanesi gösterilir.', 'qrms' ); ?></p>
					<?php if ( ! $template && ! $social_fields_rendered ) : ?>
						<?php $this->render_social_fields( $header_opts, 'hfb_header_' ); ?>
						<?php $social_fields_rendered = true; ?>
					<?php elseif ( ! $template ) : ?>
						<p class="description"><?php esc_html_e( 'Sosyal medya bağlantıları yukarıdaki ilk Sosyal medya bloğunda veya Header sekmesinde düzenlenir.', 'qrms' ); ?></p>
					<?php endif; ?>
				<?php elseif ( 'text' === $type ) : ?>
					<div class="qrms-field">
						<label class="qrms-label" for="hfb_hamburger_text_<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Serbest metin / HTML', 'qrms' ); ?></label>
						<textarea id="hfb_hamburger_text_<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $prefix ); ?>[content]" class="qrms-input hfb-preview-trigger" rows="4"><?php echo esc_textarea( isset( $block['content'] ) ? (string) $block['content'] : '' ); ?></textarea>
						<p class="description"><?php esc_html_e( 'İzin verilen HTML (paragraf, bağlantı, vurgu) kaydedilir; zararlı etiketler temizlenir.', 'qrms' ); ?></p>
					</div>
				<?php elseif ( 'logo' === $type ) : ?>
					<p class="description"><?php esc_html_e( 'Header sekmesinde yüklenen logo (veya marka yazısı) panel içinde bu sırada görünür.', 'qrms' ); ?></p>
					<div class="qrms-field">
						<label class="qrms-label" for="hfb_logo_desc_<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Logo altı açıklama', 'qrms' ); ?></label>
						<textarea id="hfb_logo_desc_<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $prefix ); ?>[description]" class="qrms-input hfb-preview-trigger" rows="3"><?php echo esc_textarea( isset( $block['description'] ) ? (string) $block['description'] : '' ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Marka adının altında görünen kısa tanıtım cümlesi. Boş bırakılırsa basılmaz.', 'qrms' ); ?></p>
					</div>
				<?php elseif ( 'lang' === $type ) : ?>
					<p class="description"><?php esc_html_e( 'QR Çeviri modülünün dil seçici bayrağı. Panelde bu sırada görünür.', 'qrms' ); ?></p>
				<?php elseif ( 'button' === $type ) : ?>
					<div class="hfb-button-block-fields">
						<div class="qrms-field">
							<label class="qrms-label" for="hfb_btn_label_<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Buton metni', 'qrms' ); ?></label>
							<input type="text" id="hfb_btn_label_<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $prefix ); ?>[label]" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( isset( $block['label'] ) ? (string) $block['label'] : '' ); ?>" />
						</div>
						<div class="qrms-field">
							<label class="qrms-label" for="hfb_btn_url_<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Bağlantı (URL)', 'qrms' ); ?></label>
							<input type="url" id="hfb_btn_url_<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $prefix ); ?>[url]" class="qrms-input hfb-preview-trigger" value="<?php echo esc_attr( isset( $block['url'] ) ? (string) $block['url'] : '' ); ?>" placeholder="https://" />
						</div>
						<?php
						$this->hfb_color_field(
							'hfb_btn_bg_' . $id,
							$prefix . '[bg_color]',
							isset( $block['bg_color'] ) ? (string) $block['bg_color'] : '#c9a84c',
							__( 'Buton rengi', 'qrms' ),
							__( 'Arka plan rengi.', 'qrms' ),
							'#c9a84c'
						);
						$this->hfb_color_field(
							'hfb_btn_text_' . $id,
							$prefix . '[text_color]',
							isset( $block['text_color'] ) ? (string) $block['text_color'] : '#0a0a0c',
							__( 'Yazı rengi', 'qrms' ),
							__( 'Buton metninin rengi.', 'qrms' ),
							'#0a0a0c'
						);
						?>
						<div class="qrms-field">
							<label class="qrms-label" for="hfb_btn_shape_<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Şekil', 'qrms' ); ?></label>
							<select id="hfb_btn_shape_<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $prefix ); ?>[shape]" class="qrms-input hfb-preview-trigger">
								<?php foreach ( $this->hamburger_button_shapes() as $shape_key => $shape_label ) : ?>
									<option value="<?php echo esc_attr( $shape_key ); ?>" <?php selected( isset( $block['shape'] ) ? (string) $block['shape'] : 'pill', $shape_key ); ?>><?php echo esc_html( $shape_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="qrms-field">
							<label class="hfb-check-row">
								<input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[full_width]" value="1" class="hfb-preview-trigger" <?php checked( ! empty( $block['full_width'] ) ); ?> />
								<span><?php esc_html_e( 'Tam genişlik (panelde satırı boydan boya kaplasın)', 'qrms' ); ?></span>
							</label>
							<p class="description"><?php esc_html_e( 'Referans tasarımdaki dolgun CTA görünümü için açın.', 'qrms' ); ?></p>
						</div>
						<div class="qrms-field">
							<label class="qrms-label" for="hfb_btn_font_<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Yazı tipi', 'qrms' ); ?></label>
							<select id="hfb_btn_font_<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $prefix ); ?>[font]" class="qrms-input hfb-preview-trigger">
								<?php foreach ( $this->font_catalog() as $font_key => $font_meta ) : ?>
									<option value="<?php echo esc_attr( $font_key ); ?>" <?php selected( isset( $block['font'] ) ? (string) $block['font'] : 'Playfair Display', $font_key ); ?>><?php echo esc_html( $font_meta['etiket'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<?php
						$this->hfb_size_row(
							'hfb_btn_size_' . $id,
							$prefix . '[font_size]',
							isset( $block['font_size'] ) ? (int) $block['font_size'] : 15,
							10,
							32,
							__( 'Yazı boyutu', 'qrms' ),
							__( 'Buton metninin punto değeri.', 'qrms' )
						);
						$this->hfb_weight_row(
							'hfb_btn_weight_' . $id,
							$prefix . '[font_weight]',
							isset( $block['font_weight'] ) ? (int) $block['font_weight'] : 600,
							__( 'Yazı kalınlığı', 'qrms' ),
							__( '400 sakin, 600–700 daha vurgulu durur.', 'qrms' )
						);
						?>
					</div>
				<?php endif; ?>
			</div>
		</li>
		<?php
	}

	/**
	 * Blok satırı için kompakt hizalama seçici.
	 *
	 * @param string $name  Form alanı adı.
	 * @param string $deger Mevcut değer.
	 * @param string $id    Blok kimliği (benzersiz id ön eki).
	 * @return void
	 */
	private function hfb_block_align_row( $name, $deger, $id ) {
		$deger = in_array( $deger, array( 'left', 'center', 'right' ), true ) ? $deger : 'center';

		$secenekler = array(
			'left'   => array( 'Sol', array( 0, 0, 0 ) ),
			'center' => array( 'Orta', array( 0, 3, 1.5 ) ),
			'right'  => array( 'Sağ', array( 0, 6, 3 ) ),
		);
		?>
		<div class="hfb-block-align" role="radiogroup" aria-label="<?php esc_attr_e( 'Blok hizalama', 'qrms' ); ?>">
			<?php
			foreach ( $secenekler as $hiza => $secenek ) :
				list( $hiza_etiket, $ofset ) = $secenek;
				$secili                      = $hiza === $deger;
				?>
				<label class="hfb-align-btn hfb-align-btn--compact<?php echo $secili ? ' is-selected' : ''; ?>" title="<?php echo esc_attr( $hiza_etiket ); ?>">
					<input type="radio" name="<?php echo esc_attr( $name ); ?>"
						   class="hfb-align-input hfb-preview-trigger"
						   value="<?php echo esc_attr( $hiza ); ?>"
						   <?php checked( $secili ); ?>>
					<svg class="hfb-align-ic" viewBox="0 0 16 12" width="14" height="10" aria-hidden="true" focusable="false">
						<rect x="<?php echo esc_attr( (string) $ofset[0] ); ?>" y="1" width="16" height="2" rx="1"></rect>
						<rect x="<?php echo esc_attr( (string) $ofset[1] ); ?>" y="5" width="10" height="2" rx="1"></rect>
						<rect x="<?php echo esc_attr( (string) $ofset[2] ); ?>" y="9" width="13" height="2" rx="1"></rect>
					</svg>
					<span class="screen-reader-text"><?php echo esc_html( $hiza_etiket ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
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
	 * Yüzde kaydırıcısı — hfb_size_row()'un % birimli karşılığı.
	 *
	 * @param string $id       Alan id'si.
	 * @param string $name     Form alanı adı.
	 * @param int    $deger    Mevcut değer.
	 * @param int    $min      Alt sınır (%).
	 * @param int    $max      Üst sınır (%).
	 * @param string $etiket   Satır başlığı.
	 * @param string $aciklama Slider altındaki açıklama.
	 * @return void
	 */
	private function hfb_percent_row( $id, $name, $deger, $min, $max, $etiket, $aciklama ) {
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
					   oninput="this.nextElementSibling.textContent=this.value+'%'">
				<span class="hfb-range-val"><?php echo (int) $deger; ?>%</span>
			</div>
			<p class="description"><?php echo esc_html( $aciklama ); ?></p>
		</div>
		<?php
	}

	/**
	 * Logo yüksekliği: otomatik oran kutusu + px slider.
	 *
	 * @param string $bp     desktop|tablet|mobile; boş dize = kırılımsız tek
	 *                       set (hamburger paneli — yalnızca mobilde açılır).
	 * @param int    $height Mevcut yükseklik.
	 * @param bool   $auto   Otomatik oran açık mı.
	 * @param string $prefix Form alanı öneki (hfb_header / hfb_footer).
	 * @return void
	 */
	private function hfb_logo_height_block( $bp, $height, $auto, $prefix = 'hfb_header' ) {
		$son      = '' !== (string) $bp ? '_' . $bp : '';
		$auto_id  = $prefix . '_logo_height_auto' . $son;
		$range_id = $prefix . '_logo_height' . $son;
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
	 * @param array<string,mixed> $args        title, desc, color_label, size_label, family_label, weight_label, hover_key, hover_label.
	 * @return void
	 */
	private function hfb_typo_block( $form_prefix, $opts, $group, $args = array() ) {
		$keys         = $this->typo_keys( $group );
		$title        = isset( $args['title'] ) ? (string) $args['title'] : '';
		$desc         = isset( $args['desc'] ) ? (string) $args['desc'] : '';
		$color_label  = isset( $args['color_label'] ) ? (string) $args['color_label'] : __( 'Yazı rengi', 'qrms' );
		$size_label   = isset( $args['size_label'] ) ? (string) $args['size_label'] : __( 'Yazı boyutu', 'qrms' );
		$family_label = isset( $args['family_label'] ) ? (string) $args['family_label'] : __( 'Yazı tipi', 'qrms' );
		$weight_label = isset( $args['weight_label'] ) ? (string) $args['weight_label'] : __( 'Yazı kalınlığı', 'qrms' );
		?>
		<div class="hfb-typo">
			<?php if ( '' !== $title ) : ?>
				<h3 class="hfb-section-title"><?php echo esc_html( $title ); ?></h3>
				<?php if ( '' !== $desc ) : ?>
					<p class="description"><?php echo esc_html( $desc ); ?></p>
				<?php endif; ?>
			<?php endif; ?>

			<h4 class="hfb-section-sub"><?php esc_html_e( 'Genel', 'qrms' ); ?></h4>
			<?php
			$this->hfb_font_family_row(
				$form_prefix . $keys['family'],
				$form_prefix . $keys['family'],
				(string) $opts[ $keys['family'] ],
				$family_label,
				isset( $args['family_desc'] ) ? (string) $args['family_desc'] : __( 'Bu metin grubunun yazı tipi.', 'qrms' )
			);
			$this->hfb_weight_row(
				$form_prefix . $keys['weight'],
				$form_prefix . $keys['weight'],
				(int) $opts[ $keys['weight'] ],
				$weight_label,
				__( '400 sakin, 600–700 daha vurgulu durur.', 'qrms' )
			);
			$this->hfb_color_field(
				$form_prefix . $keys['color'],
				$form_prefix . $keys['color'],
				(string) $opts[ $keys['color'] ],
				$color_label,
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
					$size_label,
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
					$size_label,
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
	 * Footer garson/hesap buton stil alanları.
	 *
	 * @param string              $form_prefix Form öneki (hfb_footer_ / hfb_hamburger_).
	 * @param array<string,mixed> $opts        btn_* anahtarlarını taşıyan ayarlar.
	 * @param string              $baslik      Bölüm başlığı; boşsa hiç basılmaz.
	 * @return void
	 */
	private function hfb_button_style_fields( $form_prefix, $opts, $baslik = null ) {
		$baslik = null === $baslik ? __( 'Buton stili', 'qrms' ) : $baslik;

		if ( '' !== $baslik ) :
			?>
			<h3 class="hfb-section-title"><?php echo esc_html( $baslik ); ?></h3>
			<?php
		endif;
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
	 * Option metninin çevirisi varsa kaydı engellemeyen uyarı.
	 *
	 * @param string $field option field.
	 * @return void
	 */
	private function hfb_ceviri_bayat_uyari( $field ) {
		if ( ! function_exists( 'rma_ceviri_bayat_uyari_html' ) ) {
			return;
		}
		echo rma_ceviri_bayat_uyari_html(
			rma_ceviri_bayat_uyari_metni( rma_ceviri_veri_dil_sayisi( 'option', 0, $field ) )
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
