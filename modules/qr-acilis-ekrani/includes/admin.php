<?php
/**
 * Modülün yönetim ekranları: hub + dört ayar sayfası.
 *
 * Bağımsız eklentide tek sayfa ve dört JS sekmesi vardı. Suite'te sol menü tek
 * seviyedir: "Açılış Ekranı" satırı hub'ı açar, dört ekran oradaki kartlardan
 * gidilen GERÇEK sayfalardır (bkz. QRMS_Admin::register_module_subpage ve
 * yorum-feedback/restoran-menu'deki aynı desen). Sekme kaybolmaz, adres kazanır:
 * her ekranın kendi adresi, kendi formu ve kendi kaydı vardır.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

trait QRMS_AE_Admin {

	/**
	 * Yönetim sayfaları — tek kaynak.
	 *
	 * Sıra hub'daki kart sırasıdır. `group` save_settings()'in hangi alan
	 * kümesini işleyeceğini seçer; `render` sayfanın gövdesini basan metottur.
	 *
	 * @return array<string,array{title:string,group:string,render:string,desc:string,icon:string}>
	 */
	public function admin_pages() {
		return array(
			'qrms-ae-gorunum'  => array(
				'title'  => __( 'Görünüm', 'qrms' ),
				'group'  => 'gorunum',
				'render' => 'render_page_gorunum',
				'desc'   => __( 'Arkaplan görseli, renk paleti, logo şeridi, giriş animasyonu, yüklenme göstergesi ve dil seçici.', 'qrms' ),
				'icon'   => 'dashicons-art',
			),
			'qrms-ae-butonlar' => array(
				'title'  => __( 'Butonlar & Bağlantılar', 'qrms' ),
				'group'  => 'butonlar',
				'render' => 'render_page_butonlar',
				'desc'   => __( 'Menü butonu, iletişim/rezervasyon/yorum rozetleri ve wifi penceresi.', 'qrms' ),
				'icon'   => 'dashicons-admin-links',
			),
			'qrms-ae-odeme'    => array(
				'title'  => __( 'Ödeme Yöntemleri', 'qrms' ),
				'group'  => 'odeme',
				'render' => 'render_page_odeme',
				'desc'   => __( 'Kabul ettiğiniz ödeme yöntemleri ve satırın görünüm biçimi.', 'qrms' ),
				'icon'   => 'dashicons-money-alt',
			),
			'qrms-ae-davranis' => array(
				'title'  => __( 'Davranış & Sosyal', 'qrms' ),
				'group'  => 'davranis',
				'render' => 'render_page_davranis',
				'desc'   => __( 'Otomatik kapanma süresi, yönlendirme adresi, wifi şifresi ve sosyal hesaplar.', 'qrms' ),
				'icon'   => 'dashicons-share',
			),
		);
	}

	/**
	 * Bir yönetim sayfasının tam adresi.
	 *
	 * @param string $slug Sayfa slug'ı.
	 * @return string
	 */
	public function admin_url_for( $slug ) {
		return admin_url( 'admin.php?page=' . rawurlencode( $slug ) );
	}

	/**
	 * Modülün dört ekranını kaydeder — hepsi sol menüde gizlidir.
	 *
	 * @return void
	 */
	public function register_admin_pages() {
		global $submenu;

		$parent = QRMS_Admin::MENU_SLUG;

		// Modül lisansta aktif değilse modülün satırı hiç kaydolmaz; o zaman
		// ekranlarının da kaydedilmemesi gerekir.
		if ( empty( $submenu[ $parent ] ) ) {
			return;
		}

		foreach ( $this->admin_pages() as $slug => $page ) {
			add_submenu_page(
				$parent,
				$page['title'],
				$page['title'],
				QRMS_Admin::CAPABILITY,
				$slug,
				QRMS_Admin::register_module_subpage(
					'qr-acilis-ekrani',
					$slug,
					array( $this, 'render_' . str_replace( '-', '_', $slug ) )
				)
			);
		}
	}

	/**
	 * Hub: dört ekranı kart olarak listeler.
	 *
	 * @return void
	 */
	public function render_hub() {
		$opts    = $this->get_options();
		$social  = $this->resolve_social_media_state( $opts );
		$seconds = absint( $opts['redirect_seconds'] );

		$cards = array();
		foreach ( $this->admin_pages() as $slug => $page ) {
			$cards[] = array(
				'url'   => $this->admin_url_for( $slug ),
				'title' => $page['title'],
				'desc'  => $page['desc'],
				'icon'  => $page['icon'],
			);
		}

		QRMS_Admin::render_hub(
			array(
				'title'  => __( 'Açılış Ekranı', 'qrms' ),
				'intro'  => __( 'Ana sayfaya gelen ziyaretçiyi karşılayan tam ekran karşılama. Ne yapmak istiyorsanız kartına dokunun.', 'qrms' ),
				'accent' => '#0073aa',
				'stats'  => array(
					array(
						'label'  => __( 'Otomatik kapanma', 'qrms' ),
						'value'  => $seconds > 0 ? $seconds . ' sn' : __( 'kapalı', 'qrms' ),
						'accent' => $seconds > 0 ? '#0073aa' : '#8b5cf6',
					),
					array(
						'label'  => __( 'Ödeme yöntemi', 'qrms' ),
						'value'  => count( $this->get_active_payment_methods( $opts ) ),
						'accent' => '#10b981',
					),
					array(
						'label'  => __( 'Sosyal hesap', 'qrms' ),
						'value'  => count( $social['active'] ),
						'accent' => '#f59e0b',
					),
				),
				'cards'  => $cards,
			)
		);
	}

	/**
	 * Bir ayar sayfasının ortak kabuğu: kayıt, başlık, form, önizleme.
	 *
	 * Kaydetme her sayfanın KENDİ nonce'u ve KENDİ alan kümesiyle yapılır;
	 * bir sayfayı kaydetmek diğerlerinin ayarlarına dokunmaz.
	 *
	 * @param string $slug Sayfa slug'ı.
	 * @return void
	 */
	private function render_settings_shell( $slug ) {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			return;
		}

		$pages = $this->admin_pages();
		if ( ! isset( $pages[ $slug ] ) ) {
			return;
		}

		$page  = $pages[ $slug ];
		$saved = false;

		if ( isset( $_POST['qrms_ae_submit'] ) && check_admin_referer( 'qrms_ae_save_' . $slug, 'qrms_ae_nonce' ) ) {
			$this->save_settings( $this->get_options(), $page['group'] );
			$saved = true;
		}

		$options = $this->get_options();
		$method  = $page['render'];
		?>
		<div class="wrap qrms-ae-wrap">
			<h1><?php echo esc_html( $page['title'] ); ?></h1>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ayarlar kaydedildi.', 'qrms' ); ?></p></div>
			<?php endif; ?>

			<p class="qrms-ae-page-intro"><?php echo esc_html( $page['desc'] ); ?></p>

			<div class="qrms-ae-layout">
				<form method="post" class="qrms-ae-form">
					<?php wp_nonce_field( 'qrms_ae_save_' . $slug, 'qrms_ae_nonce' ); ?>

					<?php $this->$method( $options ); ?>

					<div class="qrms-ae-save-bar">
						<button type="submit" name="qrms_ae_submit" class="button button-primary button-large"><?php esc_html_e( 'Kaydet', 'qrms' ); ?></button>
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener" class="qrms-ae-preview-link"><?php esc_html_e( 'Ana sayfayı yeni sekmede aç', 'qrms' ); ?></a>
					</div>
				</form>

				<aside class="qrms-ae-preview-col">
					<h2><?php esc_html_e( 'Canlı Önizleme', 'qrms' ); ?></h2>
					<?php $this->render_splash_preview(); ?>
				</aside>
			</div>
		</div>
		<?php
	}

	/** Görünüm sayfası. @return void */
	public function render_qrms_ae_gorunum() {
		$this->render_settings_shell( 'qrms-ae-gorunum' );
	}

	/** Butonlar sayfası. @return void */
	public function render_qrms_ae_butonlar() {
		$this->render_settings_shell( 'qrms-ae-butonlar' );
	}

	/** Ödeme sayfası. @return void */
	public function render_qrms_ae_odeme() {
		$this->render_settings_shell( 'qrms-ae-odeme' );
	}

	/** Davranış sayfası. @return void */
	public function render_qrms_ae_davranis() {
		$this->render_settings_shell( 'qrms-ae-davranis' );
	}

	/**
	 * Görünüm sayfasının gövdesi: arkaplan, palet, logo şeridi, animasyon, yüklenme göstergesi, dil seçici.
	 *
	 * Markup bağımsız eklentideki sekmeden birebir taşındı; yalnızca sekme
	 * kabuğu (section sarmalayıcısı ve giriş paragrafı) sayfa kabuğuna devredildi.
	 *
	 * @param array $options Mevcut ayarlar.
	 * @return void
	 */
	private function render_page_gorunum( $options ) {
		list( $padding_v, $padding_h ) = $this->parse_padding( $options['button_padding'] );
		$font_size = $this->parse_font_size( $options['button_font_size'] );

		// Yeni anahtarlar eski kayıtlarda bulunmaz; hepsi isset() kontrollü
		// yardımcılardan okunur ve defaults'a düşer.
		$logo_bar_height     = $this->opt_int( $options, 'logo_bar_height', 48, 220 );
		$logo_bar_opacity    = $this->opt_int( $options, 'logo_bar_opacity', 0, 100 );
		$logo_bar_color      = $this->opt_hex( $options, 'logo_bar_color' );
		$loader_type         = $this->opt_choice( $options, 'loader_type', array( 'spinner', 'ring', 'dots', 'pulse', 'none' ) );
		$loader_color        = $this->opt_hex( $options, 'loader_color' );
		$loader_size         = $this->opt_int( $options, 'loader_size', 18, 44 );
		$btn_surface_color   = $this->opt_hex( $options, 'btn_surface_color' );
		$btn_surface_opacity = $this->opt_int( $options, 'btn_surface_opacity', 0, 100 );
		$btn_surface_cta     = ! empty( $options['btn_surface_apply_cta'] );
		?>

                    <table class="form-table">
                        <tr>
                            <th><label>Hızlı Tema (Premium Paletler)</label></th>
                            <td>
                                <button type="button" class="button theme-preset" data-bg="#0f172a" data-btn="#6366f1" data-text="#ffffff" style="background:#0f172a; color:#818cf8; border-color:#0f172a;">Gece Lacivert</button>
                                <button type="button" class="button theme-preset" data-bg="#0b2b26" data-btn="#c9a227" data-text="#0b2b26" style="background:#0b2b26; color:#c9a227; border-color:#0b2b26;">Zümrüt &amp; Altın</button>
                                <button type="button" class="button theme-preset" data-bg="#2c0d12" data-btn="#e8c39e" data-text="#2c0d12" style="background:#2c0d12; color:#e8c39e; border-color:#2c0d12;">Bordo &amp; Şampanya</button>
                                <button type="button" class="button theme-preset" data-bg="#111827" data-btn="#b45309" data-text="#ffffff" style="background:#111827; color:#d97706; border-color:#111827;">Mürekkep &amp; Bakır</button>
                                <button type="button" class="button theme-preset" data-bg="#f5f1ea" data-btn="#8a6d3b" data-text="#ffffff" style="background:#f5f1ea; color:#8a6d3b; border-color:#8a6d3b;">Mermer &amp; Bronz</button>
                                <p class="description">Bir palete tıklamak aşağıdaki üç rengi birden değiştirir.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="animation_type">Açılış Animasyonu</label></th>
                            <td>
                                <select name="animation_type" id="animation_type">
                                    <option value="anim-blur-up" <?php selected($options['animation_type'], 'anim-blur-up'); ?>>Zarif Yükseliş (Smooth Blur-Up)</option>
                                    <option value="anim-elastic" <?php selected($options['animation_type'], 'anim-elastic'); ?>>Dinamik Yay (Elastic Pop)</option>
                                    <option value="anim-zoom-out" <?php selected($options['animation_type'], 'anim-zoom-out'); ?>>Sinematik Derinlik (Cinematic Zoom-Out)</option>
                                </select>
                                <p class="description">Ekran açılırken içeriğin nasıl belireceğini seçer.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Logo</label></th>
                            <td>
                                <?php $this->image_upload_field('logo', $options['logo'], 'is-logo'); ?>
                                <p class="description">Ekranın en üstündeki tam genişlik şeritte yatay ve dikey ortalanır.</p>
                                <p class="description splash-warn"><strong>Şeffaf arkaplanlı PNG veya SVG kullanın</strong> — opak bir dosyanın kendi arkaplanı, şerit zeminini dolu bir kutu gibi kapatır.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="logo_bar_height">Logo Şeridi Yüksekliği</label></th>
                            <td>
                                <input type="range" name="logo_bar_height" id="logo_bar_height" min="48" max="220" step="4"
                                       value="<?php echo esc_attr($logo_bar_height); ?>"
                                       class="splash-range" data-output="logo_bar_height_out" data-suffix="px" />
                                <output id="logo_bar_height_out" class="splash-range-value"><?php echo esc_html($logo_bar_height); ?>px</output>
                                <p class="description">Ekranın üstündeki logo şeridinin yüksekliği.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Logo Şeridi Arkaplan Rengi</label></th>
                            <td>
                                <input type="text" name="logo_bar_color" value="<?php echo esc_attr($logo_bar_color); ?>" class="color-picker" />
                                <p class="description">Logo şeridinin zemin rengi.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="logo_bar_opacity">Logo Şeridi Opaklığı</label></th>
                            <td>
                                <input type="range" name="logo_bar_opacity" id="logo_bar_opacity" min="0" max="100" step="1"
                                       value="<?php echo esc_attr($logo_bar_opacity); ?>"
                                       class="splash-range" data-output="logo_bar_opacity_out" data-suffix="%" />
                                <output id="logo_bar_opacity_out" class="splash-range-value"><?php echo esc_html($logo_bar_opacity); ?>%</output>
                                <p class="description">Şerit zemininin şeffaflığını ayarlar (0 şeffaf, 100 opak).</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Arkaplan Görseli</label></th>
                            <td>
                                <?php $this->image_upload_field('bg_image', $options['bg_image'], 'is-portrait', 'portrait'); ?>
                                <div class="splash-help">
                                    <p><strong>Önerilen ölçü: 1080 × 2400 px (9:20 dikey)</strong></p>
                                    <ul>
                                        <li>Format: WebP (tercih edilir) veya JPG · En fazla 400 KB</li>
                                        <li>Ana görsel öğe (yemek, ürün) üst %55'lik alanda kalsın — alt %40 buton ve rozetlerle kaplanır</li>
                                        <li>Sol ve sağ kenarlarda %10 boşluk bırak; dar ekranlarda kenarlar kırpılır</li>
                                        <li>Görselin üzerine yazı ekleme</li>
                                        <li>Orta tonlu bir görsel seç; butonlar şeffaf olduğu için aşırı parlak veya aşırı karanlık görsellerde metin okunmaz</li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Geniş Ekran Görseli</label></th>
                            <td>
                                <?php $this->image_upload_field('bg_image_wide', $options['bg_image_wide'], 'is-wide', 'wide'); ?>
                                <p class="description">Tablet ve masaüstünde kullanılan opsiyonel geniş görsel; boşsa dikey görsel kullanılır.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="bg_overlay_strength">Karartma Gücü</label></th>
                            <td>
                                <input type="range" name="bg_overlay_strength" id="bg_overlay_strength" min="0" max="100" step="5"
                                       value="<?php echo esc_attr(absint($options['bg_overlay_strength'])); ?>"
                                       class="splash-range" data-output="bg_overlay_strength_out" data-suffix="%" />
                                <output id="bg_overlay_strength_out" class="splash-range-value"><?php echo esc_html(absint($options['bg_overlay_strength'])); ?>%</output>
                                <p class="description">Görselin alt kısmına uygulanan karartmanın gücü.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="bg_scheme">Renk Şeması</label></th>
                            <td>
                                <select name="bg_scheme" id="bg_scheme">
                                    <option value="auto" <?php selected($options['bg_scheme'], 'auto'); ?>>Otomatik</option>
                                    <option value="light" <?php selected($options['bg_scheme'], 'light'); ?>>Açık (koyu metin)</option>
                                    <option value="dark" <?php selected($options['bg_scheme'], 'dark'); ?>>Koyu (açık metin)</option>
                                </select>
                                <p class="description">Metin ve geçişlerin açık mı koyu mu görüneceğini belirler.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Arkaplan Rengi</label></th>
                            <td>
                                <input type="text" name="bg_color" value="<?php echo esc_attr($options['bg_color']); ?>" class="color-picker" />
                                <p class="description">Arkaplan görseli yüklenmediyse ekranın düz zemin rengi olarak kullanılır.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Buton Arkaplan Rengi</label></th>
                            <td>
                                <input type="text" name="button_bg_color" value="<?php echo esc_attr($options['button_bg_color']); ?>" class="color-picker" />
                                <p class="description">Butonun kenarlık rengi; yüzey kullanılmıyorsa dolgusu da bu renktir.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Buton Metin Rengi</label></th>
                            <td>
                                <input type="text" name="button_text_color" value="<?php echo esc_attr($options['button_text_color']); ?>" class="color-picker" />
                                <p class="description">Buton yazısının rengi.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="button_font_size_px">Buton Yazı Boyutu</label></th>
                            <td>
                                <input type="range" name="button_font_size_px" id="button_font_size_px" min="12" max="24" step="1"
                                       value="<?php echo esc_attr($font_size); ?>"
                                       class="splash-range" data-output="button_font_size_out" data-suffix="px" />
                                <output id="button_font_size_out" class="splash-range-value"><?php echo esc_html($font_size); ?>px</output>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="button_opacity">Buton Şeffaflığı</label></th>
                            <td>
                                <input type="range" name="button_opacity" id="button_opacity" min="0" max="100" step="1"
                                       value="<?php echo esc_attr(absint($options['button_opacity'])); ?>"
                                       class="splash-range" data-output="button_opacity_out" data-suffix="%" />
                                <output id="button_opacity_out" class="splash-range-value"><?php echo esc_html(absint($options['button_opacity'])); ?>%</output>
                                <p class="description">Butonun dolgu şeffaflığını ayarlar (0 şeffaf, 100 opak); belirginliği kenarlık sağlar.</p>
                            </td>
                        </tr>
                        <tr>
                            <th colspan="2">
                                <h3 style="margin-bottom:4px;">Buton Yüzeyi</h3>
                                <p class="description" style="font-weight:400;">Rozetlerin ve ödeme satırının cam yüzey rengi; backdrop-filter etkisi korunur.</p>
                            </th>
                        </tr>
                        <tr>
                            <th><label>Yüzey Rengi</label></th>
                            <td>
                                <input type="text" name="btn_surface_color" value="<?php echo esc_attr($btn_surface_color); ?>" class="color-picker" />
                                <p class="description">Rozet yüzeyinin rengi.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="btn_surface_opacity">Yüzey Opaklığı</label></th>
                            <td>
                                <input type="range" name="btn_surface_opacity" id="btn_surface_opacity" min="0" max="100" step="1"
                                       value="<?php echo esc_attr($btn_surface_opacity); ?>"
                                       class="splash-range" data-output="btn_surface_opacity_out" data-suffix="%" />
                                <output id="btn_surface_opacity_out" class="splash-range-value"><?php echo esc_html($btn_surface_opacity); ?>%</output>
                                <p class="description">Rozet yüzeyinin şeffaflığını ayarlar (0 şeffaf, 100 opak).</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="btn_surface_apply_cta">"Menüye Git" de bu yüzeyi kullansın</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="btn_surface_apply_cta" id="btn_surface_apply_cta" value="1" <?php checked($btn_surface_cta); ?> />
                                    Birincil butonun dolgusu da yukarıdaki yüzey renginden gelsin
                                </label>
                                <p class="description">İşaretlenirse birincil buton da rozet yüzeyini kullanır.</p>
                            </td>
                        </tr>
                        <tr>
                            <th colspan="2">
                                <h3 style="margin-bottom:4px;">Yüklenme Göstergesi</h3>
                                <p class="description" style="font-weight:400;">Ekranın sağ üst köşesindeki bekleme animasyonu.</p>
                            </th>
                        </tr>
                        <tr>
                            <th><label for="loader_type">Animasyon Tipi</label></th>
                            <td>
                                <select name="loader_type" id="loader_type">
                                    <option value="spinner" <?php selected($loader_type, 'spinner'); ?>>Spinner (dönen ince yay)</option>
                                    <option value="ring" <?php selected($loader_type, 'ring'); ?>>Halka (geri sayımı boşalır)</option>
                                    <option value="dots" <?php selected($loader_type, 'dots'); ?>>Noktalar (sırayla nabzeder)</option>
                                    <option value="pulse" <?php selected($loader_type, 'pulse'); ?>>Nabız (genişleyen halka)</option>
                                    <option value="none" <?php selected($loader_type, 'none'); ?>>Gösterme</option>
                                </select>
                                <p class="description">Halka tipi, otomatik kapanma süresini geri sayar; süre 0 ise spinner kullanılır.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Animasyon Rengi</label></th>
                            <td>
                                <input type="text" name="loader_color" value="<?php echo esc_attr($loader_color); ?>" class="color-picker" />
                                <p class="description">Göstergenin rengi.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="loader_size">Gösterge Boyutu</label></th>
                            <td>
                                <input type="range" name="loader_size" id="loader_size" min="18" max="44" step="1"
                                       value="<?php echo esc_attr($loader_size); ?>"
                                       class="splash-range" data-output="loader_size_out" data-suffix="px" />
                                <output id="loader_size_out" class="splash-range-value"><?php echo esc_html($loader_size); ?>px</output>
                                <p class="description">Gösterge boyutu; konumu sağ üst köşede sabittir.</p>
                            </td>
                        </tr>
                        <tr>
                            <th colspan="2">
                                <h3 style="margin-bottom:4px;">Dil Seçici</h3>
                                <p class="description" style="font-weight:400;">Sol üstteki bayrak ikonu; tıklanınca QR Çeviri'nin dilini değiştirir. Splash metinlerini çevirmez.</p>
                            </th>
                        </tr>
                        <tr>
                            <th><label for="ceviri_selector">Bayraklı dil seçici</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="ceviri_selector" id="ceviri_selector" value="1" <?php checked( ! empty( $options['ceviri_selector'] ) ); ?> />
                                    Açılış ekranının sol üst köşesinde göster
                                </label>
                                <?php if ( $this->ceviri_available() ) : ?>
                                    <p class="description">Ziyaretçi bir bayrağa dokununca menü, splash kapandıktan sonra o dilde açılır. Altyapı QR Çeviri'nindir (rma_lang çerezi ve ?lang=).</p>
                                <?php else : ?>
                                    <p class="description splash-warn">QR Çeviri modülü kapalı. Dil seçici o modülün dil listesini ve çerezini kullanır; önce <a href="<?php echo esc_url( QRMS_Admin::get_module_page_url( 'qr-ceviri' ) ); ?>">QR Çeviri</a>'yi etkinleştirin. Açık/kapalı tercih kaydedilir, bayrak ise modül açılana kadar basılmaz.</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ( $this->ceviri_available() ) : ?>
                        <tr>
                            <th>Gösterilecek diller</th>
                            <td>
                                <fieldset class="splash-ceviri-langs">
                                    <legend class="screen-reader-text">Dil seçicide gösterilecek diller</legend>
                                    <?php
                                    $tumu          = qrmenu_get_langs();
                                    $aktif_diller  = rma_ceviri_aktif_diller();
                                    $secili_diller = isset( $options['ceviri_selector_langs'] ) && is_array( $options['ceviri_selector_langs'] )
                                        ? $options['ceviri_selector_langs']
                                        : array();
                                    foreach ( $aktif_diller as $kod ) :
                                        if ( ! isset( $tumu[ $kod ] ) ) {
                                            continue;
                                        }
                                        $etiket = $tumu[ $kod ]['flag'] . ' ' . $tumu[ $kod ]['name'];
                                        ?>
                                        <label>
                                            <input type="checkbox" name="ceviri_selector_langs[]" value="<?php echo esc_attr( $kod ); ?>" <?php checked( in_array( $kod, $secili_diller, true ) ); ?> />
                                            <?php echo esc_html( $etiket ); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                                <p class="description">QR Çeviri'de açık olan diller. Hiçbiri seçili değilse bayrak ikonu basılmaz.</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th><label for="button_padding_v">Buton İç Boşluğu</label></th>
                            <td>
                                <label class="splash-inline-field">
                                    <span>Dikey boşluk (px)</span>
                                    <input type="number" name="button_padding_v" id="button_padding_v" min="0" max="60" step="1" value="<?php echo esc_attr($padding_v); ?>" />
                                </label>
                                <label class="splash-inline-field">
                                    <span>Yatay boşluk (px)</span>
                                    <input type="number" name="button_padding_h" id="button_padding_h" min="0" max="90" step="1" value="<?php echo esc_attr($padding_h); ?>" />
                                </label>
                                <p class="description">Butonun iç boşluğunu (yükseklik/genişlik) belirler.</p>
                            </td>
                        </tr>
                    </table>
		<?php
	}

	/**
	 * Butonlar sayfasının gövdesi: ayraç yazısı ve beş butonun metin/adres satırları.
	 *
	 * Markup bağımsız eklentideki sekmeden birebir taşındı; yalnızca sekme
	 * kabuğu (section sarmalayıcısı ve giriş paragrafı) sayfa kabuğuna devredildi.
	 *
	 * @param array $options Mevcut ayarlar.
	 * @return void
	 */
	private function render_page_butonlar( $options ) {
		$texts_en    = isset( $options['texts_en'] ) && is_array( $options['texts_en'] ) ? $options['texts_en'] : array();
		$lang_toggle = ! empty( $options['lang_toggle'] );
		// btn6 (Sosyal Medya butonu) v3.2'de kaldırıldı: sosyal linkler artık
		// doğrudan aksiyon rozeti satırına giriyor. Option anahtarı korunuyor.
		$button_meta = array(
			1 => array( 'label' => 'Menüye Git',      'desc' => 'Birincil buton. Ekranın altında tam genişlikte görünür; yazısı ekranda okunur.', 'link' => true ),
			2 => array( 'label' => 'İletişim',         'desc' => 'Telefon ikonlu yuvarlak rozet. Adres boşsa rozet hiç görünmez.',  'link' => true ),
			3 => array( 'label' => 'Rezervasyon İste', 'desc' => 'Takvim ikonlu yuvarlak rozet. Adres boşsa rozet hiç görünmez.',   'link' => true ),
			4 => array( 'label' => 'Yorum Yap',        'desc' => 'Yıldız ikonlu yuvarlak rozet. Adres boşsa rozet hiç görünmez.',   'link' => true ),
			5 => array( 'label' => 'Wifi Şifresi',     'desc' => 'Wifi ikonlu yuvarlak rozet. Adres almaz; şifre penceresini açar.', 'link' => false ),
		);
		?>

                    <table class="form-table">
                        <tr>
                            <th><label for="divider_text">Ayraç Yazısı</label></th>
                            <td>
                                <input type="text" name="divider_text" id="divider_text" value="<?php echo esc_attr($options['divider_text']); ?>" class="regular-text" placeholder="Bizi takip edin" />
                                <p class="description">Sosyal medya rozetlerinin üstündeki ayracın etiketi; boşsa ayraç basılmaz.</p>
                                <label class="qrms-ae-en-field">
                                    <span>English</span>
                                    <input type="text" name="text_en_divider" value="<?php echo esc_attr( isset( $texts_en['divider'] ) ? $texts_en['divider'] : '' ); ?>" class="regular-text" placeholder="Follow us" />
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="lang_toggle">Dil Düğmesi</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="lang_toggle" id="lang_toggle" value="1" <?php checked( $lang_toggle ); ?> />
                                    Ekranda TR/EN düğmesi göster
                                </label>
                                <p class="description">
                                    Sol üstte küçük bir TR|EN düğmesi çıkar; ziyaretçinin seçimi bir yıl
                                    hatırlanır. Düğme yalnızca İngilizce alanlardan <strong>en az biri
                                    doluysa</strong> basılır — boş çeviriyle düğme aynı metni iki kez gösterirdi.
                                </p>
                                <p class="description">
                                    Dil sunucuda değil tarayıcıda seçilir: sayfanın HTML'i her ziyaretçide
                                    aynı kalır, böylece tam sayfa önbelleği ilk ziyaretçinin dilini
                                    herkese servis etmez.
                                </p>
                            </td>
                        </tr>
                    </table>

                    <?php foreach ($button_meta as $i => $meta): ?>
                        <div class="splash-button-row">
                            <div class="splash-button-row-head">
                                <span class="splash-button-index">Buton <?php echo (int) $i; ?></span>
                                <span class="splash-button-desc"><?php echo esc_html($meta['desc']); ?></span>
                            </div>
                            <div class="splash-button-row-fields">
                                <label>
                                    <span>Buton yazısı</span>
                                    <input type="text" name="button_text_<?php echo (int) $i; ?>" value="<?php echo esc_attr($options['button_texts']['btn' . $i]); ?>" placeholder="<?php echo esc_attr($meta['label']); ?>" />
                                </label>
                                <label class="qrms-ae-en-field">
                                    <span>Buton yazısı (English)</span>
                                    <input type="text" name="text_en_btn<?php echo (int) $i; ?>" value="<?php echo esc_attr( isset( $texts_en[ 'btn' . $i ] ) ? $texts_en[ 'btn' . $i ] : '' ); ?>" placeholder="Boşsa Türkçesi gösterilir" />
                                </label>
                                <?php if ($meta['link']): ?>
                                    <label>
                                        <span>Bağlantı adresi</span>
                                        <input type="url" name="link_btn<?php echo (int) $i; ?>" value="<?php echo esc_attr($options['button_links']['btn' . $i]); ?>" placeholder="https://" />
                                    </label>
                                <?php else: ?>
                                    <label class="is-disabled">
                                        <span>Bağlantı adresi</span>
                                        <input type="text" value="Bu buton pencere açar, adres almaz" disabled />
                                    </label>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
		<?php
	}

	/**
	 * Ödeme sayfasının gövdesi: yöntem seçimi ve satırın render biçimi.
	 *
	 * Markup bağımsız eklentideki sekmeden birebir taşındı; yalnızca sekme
	 * kabuğu (section sarmalayıcısı ve giriş paragrafı) sayfa kabuğuna devredildi.
	 *
	 * @param array $options Mevcut ayarlar.
	 * @return void
	 */
	private function render_page_odeme( $options ) {
		$payment_mode      = $this->opt_choice( $options, 'payment_display_mode', array( 'icon_text', 'text_only', 'marquee' ) );
		$marquee_with_icon = ! empty( $options['payment_marquee_with_icon'] );
		$marquee_speed     = $this->opt_int( $options, 'payment_marquee_speed', 6, 60 );
		?>

                    <?php $this->render_payment_admin_field($options); ?>

                    <p class="description">Hiçbiri seçilmezse ödeme satırı hiç görünmez.</p>

                    <h3 style="margin-top:24px;">Görünüm Modu</h3>
                    <p class="description">Ödeme yöntemlerinin nasıl görüneceğini belirler.</p>

                    <div class="splash-radio-group">
                        <label class="splash-radio-card">
                            <input type="radio" name="payment_display_mode" value="icon_text" <?php checked($payment_mode, 'icon_text'); ?> />
                            <span class="splash-radio-title">İkon + Yazı</span>
                            <span class="splash-radio-desc">SVG ikon ile yazı yan yana durur.</span>
                        </label>
                        <label class="splash-radio-card">
                            <input type="radio" name="payment_display_mode" value="text_only" <?php checked($payment_mode, 'text_only'); ?> />
                            <span class="splash-radio-title">Sadece Yazı</span>
                            <span class="splash-radio-desc">İkonlar basılmaz, yalnızca yöntem adları görünür.</span>
                        </label>
                        <label class="splash-radio-card">
                            <input type="radio" name="payment_display_mode" value="marquee" <?php checked($payment_mode, 'marquee'); ?> />
                            <span class="splash-radio-title">Kayan Şerit</span>
                            <span class="splash-radio-desc">Yöntemler tam genişlikte, sonsuz döngüde kayar.</span>
                        </label>
                    </div>

                    <table class="form-table">
                        <tr>
                            <th><label for="payment_marquee_with_icon">Kayan Şeritte İkon</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="payment_marquee_with_icon" id="payment_marquee_with_icon" value="1" <?php checked($marquee_with_icon); ?> />
                                    Kayan şeritte ikonlar da görünsün
                                </label>
                                <p class="description">Yalnızca "Kayan Şerit" modunda etkilidir.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="payment_marquee_speed">Kayma Hızı</label></th>
                            <td>
                                <input type="range" name="payment_marquee_speed" id="payment_marquee_speed" min="6" max="60" step="1"
                                       value="<?php echo esc_attr($marquee_speed); ?>"
                                       class="splash-range" data-output="payment_marquee_speed_out" data-suffix=" sn" />
                                <output id="payment_marquee_speed_out" class="splash-range-value"><?php echo esc_html($marquee_speed); ?> sn</output>
                                <p class="description">Altı yöntemlik bir turun kaç saniye süreceğini belirler; değer küçüldükçe hızlanır.</p>
                            </td>
                        </tr>
                    </table>
		<?php
	}

	/**
	 * Davranış sayfasının gövdesi: süreler, yönlendirme, wifi ve sosyal hesaplar.
	 *
	 * Markup bağımsız eklentideki sekmeden birebir taşındı; yalnızca sekme
	 * kabuğu (section sarmalayıcısı ve giriş paragrafı) sayfa kabuğuna devredildi.
	 *
	 * @param array $options Mevcut ayarlar.
	 * @return void
	 */
	private function render_page_davranis( $options ) {
		$social_map    = $this->social_media_map();
		$social_state  = $this->resolve_social_media_state( $options );
		$social_active = $social_state['active'];
		$social_urls   = $social_state['urls'];
		?>

                    <table class="form-table">
                        <tr>
                            <th><label for="redirect_seconds">Otomatik Kapanma/Yönlendirme Süresi (saniye)</label></th>
                            <td>
                                <input type="number" name="redirect_seconds" id="redirect_seconds" value="<?php echo esc_attr($options['redirect_seconds']); ?>" min="0" step="1" />
                                <p class="description">Süre dolunca ekran kapanır veya yönlendirir; 0 = otomatik işlem yok.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="redirect_url">Yönlendirme Adresi</label></th>
                            <td>
                                <input type="url" name="redirect_url" id="redirect_url" value="<?php echo esc_attr($options['redirect_url']); ?>" class="regular-text" placeholder="Boş bırakırsanız sadece ekranı kapatır" />
                                <p class="description">Süre dolunca gidilecek adres; boşsa ekran sadece kapanır.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="dismiss_duration">Tekrar Göstermeme Süresi (dakika)</label></th>
                            <td>
                                <input type="number" name="dismiss_duration" id="dismiss_duration" value="<?php echo esc_attr($options['dismiss_duration']); ?>" min="0" step="1" />
                                <p class="description">Kapatan ziyaretçiye bu süre boyunca tekrar gösterilmez; 0 = her ziyarette gösterilir.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="wifi_password">Wifi Şifresi</label></th>
                            <td>
                                <input type="text" name="wifi_password" id="wifi_password" value="<?php echo esc_attr($options['wifi_password']); ?>" class="regular-text" />
                                <p class="description">Wifi butonuna basıldığında açılan pencerede gösterilir.</p>
                            </td>
                        </tr>
                    </table>

                    <h3 style="margin-bottom:4px;">Sosyal Medya</h3>
                    <p class="description" style="font-weight:400;">Aktif ettiğiniz hesaplar rozet olarak görünür; en fazla 6 hesap seçebilirsiniz.</p>

                    <input type="hidden" name="social_media_order" id="social_media_order" value="<?php echo esc_attr(implode(',', $social_active)); ?>" />
                    <div class="splash-social-grid">
                        <?php foreach ($social_map as $key => $meta): ?>
                            <div class="splash-social-row">
                                <label class="splash-social-check-label">
                                    <input type="checkbox" class="splash-social-check" name="social_media_active[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $social_active, true)); ?> />
                                    <span><?php echo esc_html($meta['label']); ?></span>
                                </label>
                                <input type="url" name="social_media_url_<?php echo esc_attr($key); ?>" value="<?php echo esc_attr(isset($social_urls[$key]) ? $social_urls[$key] : ''); ?>" class="regular-text" placeholder="https://" />
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="splash-social-limit-note">En fazla 6 hesap aktif olabilir; önce birini kapatın.</p>
		<?php
	}

	/**
	 * Yönetim varlıkları — yalnızca bu modülün ekranlarında.
	 *
	 * @return void
	 */
	public function admin_enqueue_assets() {
		if ( ! $this->is_module_screen() ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );

		// Önizleme frontend'in GERÇEK stylesheet'ini kullanır; ayrı bir taklit
		// yoktur, böylece önizleme ile ana sayfa birbirinden ayrışamaz.
		wp_enqueue_style(
			'qrms-ae-splash',
			QRMS_PLUGIN_URL . 'modules/qr-acilis-ekrani/assets/css/splash.css',
			array(),
			QRMS_Helpers::asset_version( 'modules/qr-acilis-ekrani/assets/css/splash.css' )
		);
		wp_enqueue_style(
			'qrms-ae-admin',
			QRMS_PLUGIN_URL . 'modules/qr-acilis-ekrani/assets/css/admin.css',
			array( 'qrms-admin', 'wp-color-picker', 'qrms-ae-splash' ),
			QRMS_Helpers::asset_version( 'modules/qr-acilis-ekrani/assets/css/admin.css' )
		);
		/*
		 * Ön yüz betiği önizlemede de yüklenir: içindeki data-preview guard'ı
		 * çerez ve yönlendirme tarafını kapatır, TR/EN düğmesini ise çalışır
		 * bırakır — yönetici İngilizce hâlin nasıl göründüğünü görebilmeli.
		 */
		wp_enqueue_script(
			'qrms-ae-splash',
			QRMS_PLUGIN_URL . 'modules/qr-acilis-ekrani/assets/js/splash.js',
			array(),
			QRMS_Helpers::asset_version( 'modules/qr-acilis-ekrani/assets/js/splash.js' ),
			true
		);

		wp_enqueue_script(
			'qrms-ae-admin',
			QRMS_PLUGIN_URL . 'modules/qr-acilis-ekrani/assets/js/admin.js',
			array( 'jquery', 'wp-color-picker', 'qrms-ae-splash' ),
			QRMS_Helpers::asset_version( 'modules/qr-acilis-ekrani/assets/js/admin.js' ),
			true
		);
	}

	/**
	 * Modülün yönetim ekranlarından birinde miyiz? (hub dahil)
	 *
	 * @return bool
	 */
	private function is_module_screen() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( QRMS_Admin::get_module_page_slug( 'qr-acilis-ekrani' ) === $page ) {
			return true;
		}

		return isset( $this->admin_pages()[ $page ] );
	}

	/**
	 * Bağımsız eklentinin adreslerini yeni sayfalara taşır.
	 *
	 * Eski kurulumda yer imi, e-posta ya da tarayıcı geçmişinde
	 * `admin.php?page=splash-screen` adresleri kalmış olabilir; boş ekran
	 * yerine karşılığı olan suite sayfasına götürülür.
	 *
	 * @return void
	 */
	public function maybe_redirect_legacy_pages() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = sanitize_key( wp_unslash( $_GET['page'] ) );

		if ( 'splash-screen' !== $page && 'splash-screen-links' !== $page && 'splash-links' !== $page ) {
			return;
		}

		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		if ( 'splash-screen' !== $page ) {
			$tab = 'butonlar';
		}

		$map = array(
			'gorunum'  => 'qrms-ae-gorunum',
			'butonlar' => 'qrms-ae-butonlar',
			'odeme'    => 'qrms-ae-odeme',
			'davranis' => 'qrms-ae-davranis',
		);

		$target = isset( $map[ $tab ] )
			? $this->admin_url_for( $map[ $tab ] )
			: QRMS_Admin::get_module_page_url( 'qr-acilis-ekrani' );

		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Yönetici çubuğuna kısayol.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Çubuk nesnesi.
	 * @return void
	 */
	public function add_admin_bar( $wp_admin_bar ) {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'qrms-acilis-ekrani',
				'title' => __( 'Açılış Ekranı', 'qrms' ),
				'href'  => QRMS_Admin::get_module_page_url( 'qr-acilis-ekrani' ),
			)
		);

		foreach ( $this->admin_pages() as $slug => $page ) {
			$wp_admin_bar->add_node(
				array(
					'id'     => 'qrms-ae-' . $slug,
					'parent' => 'qrms-acilis-ekrani',
					'title'  => $page['title'],
					'href'   => $this->admin_url_for( $slug ),
				)
			);
		}
	}

	/**
	 * Medya kütüphanesi seçici alanı.
	 *
	 * $preview_class ile önizleme kutusunun oranı ayarlanır (dikey arkaplan
	 * görselinde dar/portre önizleme kullanılır).
	 * $warn ile JS tarafındaki ölçü/boyut uyarı kuralı seçilir.
	 *
	 * @param string $name          Alan adı.
	 * @param int    $value         Seçili ek dosya kimliği.
	 * @param string $preview_class Önizleme kutusunun sınıfı.
	 * @param string $warn          JS uyarı kuralının adı.
	 * @return void
	 */
	private function image_upload_field( $name, $value, $preview_class = '', $warn = '' ) {
		$value   = absint( $value );
		$img_url = $value ? wp_get_attachment_image_url( $value, 'medium' ) : '';
		?>
		<div class="splash-media-field">
			<input type="hidden" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" />
			<p class="splash-media-actions">
				<button type="button" class="button upload-image-btn" data-target="<?php echo esc_attr($name); ?>" data-warn="<?php echo esc_attr($warn); ?>">Görsel Seç</button>
				<button type="button" class="button-link splash-remove-image<?php echo $value ? '' : ' hidden'; ?>" data-target="<?php echo esc_attr($name); ?>">Kaldır</button>
			</p>
			<div class="image-preview <?php echo esc_attr($preview_class); ?>">
				<?php if ($img_url): ?>
					<img src="<?php echo esc_url($img_url); ?>" alt="" />
				<?php endif; ?>
			</div>
			<div class="splash-media-warnings" data-for="<?php echo esc_attr($name); ?>"></div>
		</div>
		<?php
	}
}
