<?php
/**
 * Modülün yönetim ekranları: hub + ayar sayfaları.
 *
 * Bağımsız eklentide tek sayfa ve dört JS sekmesi vardı. Suite'te sol menü tek
 * seviyedir: "Açılış Ekranı" satırı hub'ı açar, ekranlar oradaki kartlardan
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
				'title'  => __( 'Ayarlar', 'qrms' ),
				'group'  => 'davranis',
				'render' => 'render_page_davranis',
				'desc'   => __( 'Otomatik kapanma süresi, yönlendirme adresi, tekrar gösterme süresi ve wifi şifresi.', 'qrms' ),
				'icon'   => 'dashicons-admin-generic',
			),
			'qrms-ae-sosyal'   => array(
				'title'  => __( 'Sosyal Medya Bağlantısı', 'qrms' ),
				'group'  => 'sosyal',
				'render' => 'render_page_sosyal',
				'desc'   => __( 'Instagram, Facebook, YouTube, X, WhatsApp ve diğer hesap bağlantıları.', 'qrms' ),
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
	 * Modülün ayar ekranlarını kaydeder — hepsi sol menüde gizlidir.
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
	 * Hub: ayar ekranlarını kart olarak listeler.
	 *
	 * @return void
	 */
	/**
	 * Hub: ayar ekranlarını kart olarak listeler.
	 *
	 * Üstteki özet kutuları "ekran şu an ne yapıyor" sorusunu ayar sayfasını
	 * açmadan yanıtlar: kapanma süresi, kaç rozet, kaç ödeme yöntemi, kaç
	 * sosyal hesap.
	 *
	 * @return void
	 */
	public function render_hub() {
		$opts    = $this->get_options();
		$social  = $this->resolve_social_media_state( $opts );
		$seconds = absint( $opts['redirect_seconds'] );

		// Ön yüzde gerçekten basılan rozet sayısı: adresi boş olan buton
		// DOM'a hiç girmediği için burada da sayılmaz (wifi her zaman var).
		$badges = count( $this->build_action_badges( $opts ) );

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
				'title'  => __( 'Karşılama Ekranı', 'qrms' ),
				'intro'  => __( 'Menü açılmadan önce misafirlerinize gösterilen karşılama ekranını özelleştirin.', 'qrms' ),
				'accent' => self::ACCENT,
				'stats'  => array(
					array(
						'label'  => __( 'Otomatik kapanma', 'qrms' ),
						'value'  => $seconds > 0 ? $seconds . ' sn' : __( 'kapalı', 'qrms' ),
						'accent' => $seconds > 0 ? self::ACCENT : '#8b5cf6',
						'url'    => $this->admin_url_for( 'qrms-ae-davranis' ),
					),
					array(
						'label'  => __( 'Görünen rozet', 'qrms' ),
						'value'  => $badges,
						'accent' => '#0ea5e9',
						'url'    => $this->admin_url_for( 'qrms-ae-butonlar' ),
					),
					array(
						'label'  => __( 'Ödeme yöntemi', 'qrms' ),
						'value'  => count( $this->get_active_payment_methods( $opts ) ),
						'accent' => '#10b981',
						'url'    => $this->admin_url_for( 'qrms-ae-odeme' ),
					),
					array(
						'label'  => __( 'Sosyal hesap', 'qrms' ),
						'value'  => count( $social['active'] ) . ' / 6',
						'accent' => '#f59e0b',
						'url'    => $this->admin_url_for( 'qrms-ae-sosyal' ),
					),
				),
				'cards'  => $cards,
			)
		);
	}
	/**
	 * Bir ayar sayfasının ortak kabuğu: kayıt, başlık, sayfa gezinmesi, form, önizleme.
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
			<div class="qrae-head">
				<div class="qrae-head-main">
					<p class="qrae-eyebrow">
						<a href="<?php echo esc_url( QRMS_Admin::get_module_page_url( 'qr-acilis-ekrani' ) ); ?>"><?php esc_html_e( 'Karşılama Ekranı', 'qrms' ); ?></a>
					</p>
					<h1 class="qrae-title"><?php echo esc_html( $page['title'] ); ?></h1>
					<p class="qrae-lead"><?php echo esc_html( $page['desc'] ); ?></p>
				</div>
			</div>

			<?php // Beş ekran arasında gezinme: sayfa yapısı değişmedi, sadece görünür oldu. ?>
			<nav class="qrae-tabs" aria-label="<?php esc_attr_e( 'Karşılama ekranı ayarları', 'qrms' ); ?>">
				<?php foreach ( $pages as $tab_slug => $tab ) : ?>
					<a class="qrae-tab<?php echo $tab_slug === $slug ? ' is-current' : ''; ?>"
						href="<?php echo esc_url( $this->admin_url_for( $tab_slug ) ); ?>"
						<?php echo $tab_slug === $slug ? 'aria-current="page"' : ''; ?>>
						<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>" aria-hidden="true"></span>
						<?php echo esc_html( $tab['title'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ayarlar kaydedildi.', 'qrms' ); ?></p></div>
			<?php endif; ?>

			<div class="qrms-ae-layout">
				<form method="post" class="qrms-ae-form" id="qrae-form">
					<?php wp_nonce_field( 'qrms_ae_save_' . $slug, 'qrms_ae_nonce' ); ?>

					<?php $this->$method( $options ); ?>

					<div class="qrms-ae-save-bar">
						<button type="submit" name="qrms_ae_submit" class="button button-primary button-large"><?php esc_html_e( 'Kaydet', 'qrms' ); ?></button>
						<?php // Kaydedilmemiş değişiklik uyarısı; JS açar, form gönderilince kapanır. ?>
						<span class="qrae-dirty" id="qrae-dirty" role="status" hidden><?php esc_html_e( 'Kaydedilmemiş değişiklikler var', 'qrms' ); ?></span>
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener" class="qrae-link-external"><?php esc_html_e( 'Ana sayfayı yeni sekmede aç', 'qrms' ); ?></a>
					</div>
				</form>

				<aside class="qrms-ae-preview-col">
					<div class="qrae-preview-head">
						<h2><?php esc_html_e( 'Canlı Önizleme', 'qrms' ); ?></h2>
						<?php // Dar ekranda önizleme formu uzatmasın diye katlanır; JS başlangıç durumunu ekran genişliğine göre kurar. ?>
						<button type="button" class="qrae-preview-collapse" aria-expanded="true" aria-controls="qrae-preview-body">
							<span class="qrae-preview-collapse-text"><?php esc_html_e( 'Gizle', 'qrms' ); ?></span>
						</button>
					</div>
					<div id="qrae-preview-body">
						<?php $this->render_splash_preview(); ?>
					</div>
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

	/** Ayarlar sayfası. @return void */
	public function render_qrms_ae_davranis() {
		$this->render_settings_shell( 'qrms-ae-davranis' );
	}

	/** Sosyal medya bağlantıları sayfası. @return void */
	public function render_qrms_ae_sosyal() {
		$this->render_settings_shell( 'qrms-ae-sosyal' );
	}
	/* ==========================================================
	   Form ilkelleri
	   ----------------------------------------------------------
	   Beş ekran da aynı üç parçadan kurulur: KART (ilgili ayarlar
	   bir arada), ALAN (etiket + tek satırlık açıklama + kontrol)
	   ve KOŞUL (bir ayar başka bir ayarı gerektirmiyorsa görünmez).

	   Koşullu alanlar CSS ile GİZLENİR, disable EDİLMEZ: disable
	   edilen bir alan POST'a girmez ve sahibi sayfa kaydedilince
	   kayıtlı değer sessizce silinirdi (bkz. settings.php başlığı).
	   ========================================================== */

	/**
	 * Bir ayar kartının açılışı.
	 *
	 * @param string $title Kart başlığı.
	 * @param string $desc  Tek satırlık açıklama.
	 * @param array  $args  { @type string $when_field, @type string $when_value, @type bool $when_not, @type string $class }
	 * @return void
	 */
	private function card_open( $title, $desc = '', $args = array() ) {
		$class = 'qrae-card' . ( isset( $args['class'] ) ? ' ' . $args['class'] : '' );
		?>
		<section class="<?php echo esc_attr( $class ); ?>"<?php echo $this->when_attrs( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<header class="qrae-card-head">
				<h2 class="qrae-card-title"><?php echo esc_html( $title ); ?></h2>
				<?php if ( '' !== $desc ) : ?>
					<p class="qrae-card-desc"><?php echo esc_html( $desc ); ?></p>
				<?php endif; ?>
			</header>
			<div class="qrae-card-body">
		<?php
	}

	/**
	 * Kartı kapatır.
	 *
	 * @return void
	 */
	private function card_close() {
		echo '</div></section>';
	}

	/**
	 * Koşullu görünürlük nitelikleri.
	 *
	 * JS tarafı `when_field` alanının değerini izler; değer `when_value`
	 * listesinde (dikey çizgiyle ayrılır) ise alan görünür. `when_not`
	 * koşulu tersine çevirir. Onay kutularında değer "1" / "0" olarak okunur.
	 *
	 * @param array $args Alan tanımı.
	 * @return string
	 */
	private function when_attrs( $args ) {
		if ( empty( $args['when_field'] ) ) {
			return '';
		}

		$out = ' data-when-field="' . esc_attr( $args['when_field'] ) . '"'
			. ' data-when-value="' . esc_attr( isset( $args['when_value'] ) ? $args['when_value'] : '1' ) . '"';

		if ( ! empty( $args['when_not'] ) ) {
			$out .= ' data-when-not="1"';
		}
		if ( ! empty( $args['when_mode'] ) ) {
			// "mute": alan gizlenmez, etkisiz olduğu görsel olarak belirtilir.
			$out .= ' data-when-mode="' . esc_attr( $args['when_mode'] ) . '"';
		}

		return $out;
	}

	/**
	 * Alan açılışı: etiket + kısa açıklama.
	 *
	 * `for` verilirse gerçek bir <label> basılır (tek kontrollü alanlar);
	 * verilmezse görsel etiket <span> olur — birden çok kontrolü olan
	 * alanlarda boş label bağı kurmak ekran okuyucuyu yanıltır.
	 *
	 * @param array $args { @type string $label, $hint, $for, $class + when_* }
	 * @return void
	 */
	private function field_open( $args ) {
		$label = isset( $args['label'] ) ? $args['label'] : '';
		$hint  = isset( $args['hint'] ) ? $args['hint'] : '';
		$for   = isset( $args['for'] ) ? $args['for'] : '';
		$class = 'qrae-field' . ( isset( $args['class'] ) ? ' ' . $args['class'] : '' );
		?>
		<div class="<?php echo esc_attr( $class ); ?>"<?php echo $this->when_attrs( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php if ( '' !== $label ) : ?>
				<?php if ( '' !== $for ) : ?>
					<label class="qrae-label" for="<?php echo esc_attr( $for ); ?>"><?php echo esc_html( $label ); ?></label>
				<?php else : ?>
					<span class="qrae-label"><?php echo esc_html( $label ); ?></span>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( '' !== $hint ) : ?>
				<p class="qrae-hint"><?php echo esc_html( $hint ); ?></p>
			<?php endif; ?>
			<div class="qrae-control">
		<?php
	}

	/**
	 * Alanı kapatır.
	 *
	 * @return void
	 */
	private function field_close() {
		echo '</div></div>';
	}

	/**
	 * Renk alanı. wpColorPicker ile sarılır; etiket bağı için id taşır.
	 *
	 * @param array $args { @type string $name, $value, $label, $hint + when_* }
	 * @return void
	 */
	private function color_field( $args ) {
		$name = $args['name'];
		$id   = 'qrae-' . $name;

		$args['for'] = $id;
		$this->field_open( $args );
		?>
		<input type="text" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>"
			value="<?php echo esc_attr( $args['value'] ); ?>" class="color-picker" />
		<?php
		$this->field_close();
	}

	/**
	 * Aralık alanı: kaydırıcı + anlık değer + sınırlar + hazır değerler.
	 *
	 * Hazır değerler ("Dar / Dengeli / Geniş") kullanıcıya sayı ezberletmez;
	 * kaydırıcı yine durur, kaydedilen anahtar ve aralık değişmez.
	 *
	 * @param array $args { @type string $name,$label,$hint,$suffix; @type int $min,$max,$step,$value; @type array $presets }
	 * @return void
	 */
	private function range_field( $args ) {
		$name    = $args['name'];
		$id      = isset( $args['id'] ) ? $args['id'] : $name;
		$suffix  = isset( $args['suffix'] ) ? $args['suffix'] : '';
		$step    = isset( $args['step'] ) ? $args['step'] : 1;
		$presets = isset( $args['presets'] ) ? $args['presets'] : array();

		$args['for'] = $id;
		$this->field_open( $args );
		?>
		<div class="qrae-range">
			<input type="range" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>"
				min="<?php echo esc_attr( $args['min'] ); ?>" max="<?php echo esc_attr( $args['max'] ); ?>"
				step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( $args['value'] ); ?>"
				class="splash-range" data-output="<?php echo esc_attr( $id ); ?>_out" data-suffix="<?php echo esc_attr( $suffix ); ?>" />
			<output id="<?php echo esc_attr( $id ); ?>_out" class="splash-range-value" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $args['value'] . $suffix ); ?></output>
		</div>
		<p class="qrae-range-scale" aria-hidden="true">
			<span><?php echo esc_html( $args['min'] . $suffix ); ?></span>
			<span><?php echo esc_html( $args['max'] . $suffix ); ?></span>
		</p>
		<?php if ( ! empty( $presets ) ) : ?>
			<div class="qrae-presets" role="group" aria-label="<?php esc_attr_e( 'Hazır değerler', 'qrms' ); ?>">
				<?php foreach ( $presets as $preset_value => $preset_label ) : ?>
					<button type="button" class="qrae-preset" data-preset-target="<?php echo esc_attr( $id ); ?>" data-preset-value="<?php echo esc_attr( $preset_value ); ?>">
						<?php echo esc_html( $preset_label ); ?>
					</button>
				<?php endforeach; ?>
			</div>
		<?php endif;
		$this->field_close();
	}

	/**
	 * Açık/kapalı anahtarı (görsel olarak switch, teknik olarak checkbox).
	 *
	 * Checkbox kalır: POST sözleşmesi ve "işaretsizlik yalnızca sahibi sayfada
	 * kapalı demektir" kuralı aynen korunur.
	 *
	 * @param array $args { @type string $name,$label,$hint,$text; @type bool $checked }
	 * @return void
	 */
	private function switch_field( $args ) {
		$name = $args['name'];
		$id   = isset( $args['id'] ) ? $args['id'] : $name;
		$text = isset( $args['text'] ) ? $args['text'] : '';

		$args['for'] = $id;
		$this->field_open( $args );
		?>
		<label class="qrae-switch" for="<?php echo esc_attr( $id ); ?>">
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" value="1" <?php checked( ! empty( $args['checked'] ) ); ?> />
			<span class="qrae-switch-track" aria-hidden="true"><span class="qrae-switch-thumb"></span></span>
			<?php if ( '' !== $text ) : ?>
				<span class="qrae-switch-text"><?php echo esc_html( $text ); ?></span>
			<?php endif; ?>
		</label>
		<?php
		$this->field_close();
	}

	/**
	 * Açılır liste.
	 *
	 * @param array $args { @type string $name,$label,$hint,$value; @type array $options }
	 * @return void
	 */
	private function select_field( $args ) {
		$name = $args['name'];
		$id   = isset( $args['id'] ) ? $args['id'] : $name;

		$args['for'] = $id;
		$this->field_open( $args );
		?>
		<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" class="qrae-select">
			<?php foreach ( $args['options'] as $option_value => $option_label ) : ?>
				<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $args['value'], $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
		$this->field_close();
	}

	/**
	 * Metin/adres alanı.
	 *
	 * Adres alanları `data-qrae-url` taşır: JS, girilen adresi anında
	 * doğrular ve hatayı alanın altında gösterir (sunucu tarafı sanitize
	 * yerine geçmez, ona ek olarak çalışır).
	 *
	 * @param array $args { @type string $name,$label,$hint,$value,$type,$placeholder }
	 * @return void
	 */
	private function text_field( $args ) {
		$name = $args['name'];
		$id   = isset( $args['id'] ) ? $args['id'] : $name;
		$type = isset( $args['type'] ) ? $args['type'] : 'text';

		$args['for'] = $id;
		$this->field_open( $args );
		?>
		<input type="<?php echo esc_attr( 'url' === $type ? 'text' : $type ); ?>"
			name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>"
			value="<?php echo esc_attr( $args['value'] ); ?>"
			class="qrae-input"
			<?php if ( 'url' === $type ) : ?>
				data-qrae-url="1" inputmode="url" spellcheck="false" autocomplete="off"
				aria-describedby="<?php echo esc_attr( $id ); ?>-error"
			<?php endif; ?>
			<?php if ( isset( $args['placeholder'] ) ) : ?>
				placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
			<?php endif; ?>
			<?php if ( isset( $args['min'] ) ) : ?>
				min="<?php echo esc_attr( $args['min'] ); ?>" step="1"
			<?php endif; ?> />
		<?php if ( 'url' === $type ) : ?>
			<p class="qrae-error" id="<?php echo esc_attr( $id ); ?>-error" hidden></p>
		<?php endif;
		$this->field_close();
	}
	/**
	 * Hızlı tema paletleri.
	 *
	 * Palet adları ve üç ana rengi (zemin / vurgu / yazı) bağımsız eklentiden
	 * DEĞİŞMEDEN gelir; palet artık yüzey, şerit ve gösterge renklerini de
	 * birlikte kurduğu için tek tıkla tutarlı bir görünüm oluşur. Tıklama
	 * yalnızca formu doldurur — kaydetmeden hiçbir şey değişmez.
	 *
	 * @return array<int,array<string,string|int>>
	 */
	private function theme_presets() {
		return array(
			array(
				'name'       => __( 'Premium (varsayılan)', 'qrms' ),
				'bg'         => '#141210',
				'btn'        => '#c9a84c',
				'text'       => '#ffffff',
				'surface'    => '#ffffff',
				'surface_op' => 12,
				'bar'        => '#0d0b0a',
				'bar_op'     => 38,
				'loader'     => '#c9a84c',
				'scheme'     => 'dark',
			),
			array(
				'name'       => __( 'Gece Lacivert', 'qrms' ),
				'bg'         => '#0f172a',
				'btn'        => '#6366f1',
				'text'       => '#ffffff',
				'surface'    => '#ffffff',
				'surface_op' => 14,
				'bar'        => '#0b1220',
				'bar_op'     => 40,
				'loader'     => '#818cf8',
				'scheme'     => 'dark',
			),
			array(
				'name'       => __( 'Zümrüt & Altın', 'qrms' ),
				'bg'         => '#0b2b26',
				'btn'        => '#c9a227',
				'text'       => '#0b2b26',
				'surface'    => '#ffffff',
				'surface_op' => 12,
				'bar'        => '#07201c',
				'bar_op'     => 40,
				'loader'     => '#c9a227',
				'scheme'     => 'dark',
			),
			array(
				'name'       => __( 'Bordo & Şampanya', 'qrms' ),
				'bg'         => '#2c0d12',
				'btn'        => '#e8c39e',
				'text'       => '#2c0d12',
				'surface'    => '#ffffff',
				'surface_op' => 12,
				'bar'        => '#1e070b',
				'bar_op'     => 40,
				'loader'     => '#e8c39e',
				'scheme'     => 'dark',
			),
			array(
				'name'       => __( 'Mürekkep & Bakır', 'qrms' ),
				'bg'         => '#111827',
				'btn'        => '#b45309',
				'text'       => '#ffffff',
				'surface'    => '#ffffff',
				'surface_op' => 12,
				'bar'        => '#0b101c',
				'bar_op'     => 42,
				'loader'     => '#d97706',
				'scheme'     => 'dark',
			),
			array(
				// Tek açık palet: CTA yazısı beyaz kalsaydı açık zeminde
				// okunmazdı, bu yüzden koyu mürekkep tonu kullanılır.
				'name'       => __( 'Mermer & Bronz', 'qrms' ),
				'bg'         => '#f5f1ea',
				'btn'        => '#8a6d3b',
				'text'       => '#2b2118',
				'surface'    => '#ffffff',
				'surface_op' => 16,
				'bar'        => '#ffffff',
				'bar_op'     => 55,
				'loader'     => '#8a6d3b',
				'scheme'     => 'light',
			),
		);
	}

	/**
	 * Görünüm sayfasının gövdesi.
	 *
	 * Ayarlar altı karta ayrıldı: hızlı tema, marka, arka plan, ana buton,
	 * rozet yüzeyi, animasyon ve dil. Alan adları (POST anahtarları) ve
	 * sınırlar değişmedi; değişen yalnızca sunum ve gruplama.
	 *
	 * @param array $options Mevcut ayarlar.
	 * @return void
	 */
	private function render_page_gorunum( $options ) {
		$font_size = $this->parse_font_size( $options['button_font_size'] );

		// Yeni anahtarlar eski kayıtlarda bulunmaz; hepsi isset() kontrollü
		// yardımcılardan okunur ve defaults'a düşer.
		$logo_bar_height     = $this->opt_int( $options, 'logo_bar_height', 48, 220 );
		$logo_bar_opacity    = $this->opt_int( $options, 'logo_bar_opacity', 0, 100 );
		$logo_bar_color      = $this->opt_hex( $options, 'logo_bar_color' );
		$loader_type         = $this->opt_choice( $options, 'loader_type', array( 'spinner', 'ring', 'dots', 'pulse', 'none' ) );
		$loader_color        = $this->opt_hex( $options, 'loader_color' );
		$loader_size         = $this->opt_int( $options, 'loader_size', 18, 44 );
		$ceviri_flag_size    = $this->opt_int( $options, 'ceviri_flag_size', 20, 48 );
		$btn_surface_color   = $this->opt_hex( $options, 'btn_surface_color' );
		$btn_surface_opacity = $this->opt_int( $options, 'btn_surface_opacity', 0, 100 );
		$btn_surface_cta     = ! empty( $options['btn_surface_apply_cta'] );
		$overlay_strength    = $this->opt_int( $options, 'bg_overlay_strength', 0, 100 );

		/* ---------- Hızlı tema ---------- */
		$this->card_open(
			__( 'Hızlı Tema', 'qrms' ),
			__( 'Bir palete dokunun; zemin, vurgu, yazı, yüzey ve şerit renkleri birlikte ayarlanır.', 'qrms' )
		);
		?>
		<div class="qrae-theme-grid">
			<?php foreach ( $this->theme_presets() as $preset ) : ?>
				<button type="button" class="qrae-theme theme-preset"
					data-bg="<?php echo esc_attr( $preset['bg'] ); ?>"
					data-btn="<?php echo esc_attr( $preset['btn'] ); ?>"
					data-text="<?php echo esc_attr( $preset['text'] ); ?>"
					data-surface="<?php echo esc_attr( $preset['surface'] ); ?>"
					data-surface-op="<?php echo esc_attr( $preset['surface_op'] ); ?>"
					data-bar="<?php echo esc_attr( $preset['bar'] ); ?>"
					data-bar-op="<?php echo esc_attr( $preset['bar_op'] ); ?>"
					data-loader="<?php echo esc_attr( $preset['loader'] ); ?>"
					data-scheme="<?php echo esc_attr( $preset['scheme'] ); ?>">
					<span class="qrae-theme-swatch" aria-hidden="true" style="background:<?php echo esc_attr( $preset['bg'] ); ?>">
						<span style="background:<?php echo esc_attr( $preset['btn'] ); ?>"></span>
					</span>
					<span class="qrae-theme-name"><?php echo esc_html( $preset['name'] ); ?></span>
				</button>
			<?php endforeach; ?>
		</div>
		<p class="qrae-hint qrae-hint-block"><?php esc_html_e( 'Seçtiğiniz palet formu doldurur; renkleri tek tek değiştirmeye devam edebilirsiniz. Kaydedene kadar ekranda hiçbir şey değişmez.', 'qrms' ); ?></p>
		<?php
		$this->card_close();

		/* ---------- Marka ---------- */
		$this->card_open( __( 'Marka', 'qrms' ), __( 'Logonuz ve ekranın üstündeki şerit.', 'qrms' ) );

		$this->field_open(
			array(
				'label' => __( 'Logo', 'qrms' ),
				'hint'  => __( 'Üst şeritte ortalanır. Şeffaf arkaplanlı PNG veya SVG kullanın.', 'qrms' ),
			)
		);
		$this->image_upload_field( 'logo', $options['logo'], 'is-logo' );
		$this->field_close();

		$this->range_field(
			array(
				'name'    => 'logo_bar_height',
				'label'   => __( 'Şerit yüksekliği', 'qrms' ),
				'hint'    => __( 'Şerit büyüdükçe logo da büyür.', 'qrms' ),
				'min'     => 48,
				'max'     => 220,
				'step'    => 4,
				'value'   => $logo_bar_height,
				'suffix'  => 'px',
				'presets' => array(
					64  => __( 'İnce', 'qrms' ),
					96  => __( 'Dengeli', 'qrms' ),
					140 => __( 'Geniş', 'qrms' ),
				),
			)
		);

		$this->color_field(
			array(
				'name'  => 'logo_bar_color',
				'value' => $logo_bar_color,
				'label' => __( 'Şerit rengi', 'qrms' ),
				'hint'  => __( 'Logonun arkasındaki zemin.', 'qrms' ),
			)
		);

		$this->range_field(
			array(
				'name'    => 'logo_bar_opacity',
				'label'   => __( 'Şerit yoğunluğu', 'qrms' ),
				'hint'    => __( '0 tamamen şeffaf, 100 tamamen dolu.', 'qrms' ),
				'min'     => 0,
				'max'     => 100,
				'value'   => $logo_bar_opacity,
				'suffix'  => '%',
				'presets' => array(
					0  => __( 'Şeffaf', 'qrms' ),
					35 => __( 'Hafif', 'qrms' ),
					70 => __( 'Belirgin', 'qrms' ),
				),
			)
		);

		$this->card_close();

		/* ---------- Arka plan ---------- */
		$this->card_open( __( 'Arka Plan', 'qrms' ), __( 'Misafirin ilk gördüğü görsel ve okunabilirlik ayarları.', 'qrms' ) );

		$this->field_open(
			array(
				'label' => __( 'Arkaplan görseli', 'qrms' ),
				'hint'  => __( 'Telefonda tam ekran gösterilir; dikey (9:20) görsel kullanın.', 'qrms' ),
			)
		);
		$this->image_upload_field( 'bg_image', $options['bg_image'], 'is-portrait', 'portrait' );
		?>
		<details class="qrae-details">
			<summary><?php esc_html_e( 'Görsel hazırlama ipuçları', 'qrms' ); ?></summary>
			<ul class="qrae-list">
				<li><?php esc_html_e( 'Önerilen ölçü 1080 × 2400 px; WebP veya JPG, en fazla 400 KB.', 'qrms' ); ?></li>
				<li><?php esc_html_e( 'Yemek/ürün üst yarıda kalsın: alt bölüm buton ve rozetlerle kaplanır.', 'qrms' ); ?></li>
				<li><?php esc_html_e( 'Sol ve sağ kenarlarda boşluk bırakın; dar ekranlarda kenarlar kırpılır.', 'qrms' ); ?></li>
				<li><?php esc_html_e( 'Görselin üzerine yazı eklemeyin; orta tonlu bir kare metni okunur tutar.', 'qrms' ); ?></li>
			</ul>
		</details>
		<?php
		$this->field_close();

		$this->field_open(
			array(
				'label' => __( 'Geniş ekran görseli', 'qrms' ),
				'hint'  => __( 'Tablet ve bilgisayarda kullanılır. Boş bırakırsanız dikey görsel kullanılır.', 'qrms' ),
			)
		);
		$this->image_upload_field( 'bg_image_wide', $options['bg_image_wide'], 'is-wide', 'wide' );
		$this->field_close();

		$this->range_field(
			array(
				'name'    => 'bg_overlay_strength',
				'label'   => __( 'Karartma', 'qrms' ),
				'hint'    => __( 'Görselin alt kısmını koyulaştırır; yazılar okunur kalır.', 'qrms' ),
				'min'     => 0,
				'max'     => 100,
				'step'    => 5,
				'value'   => $overlay_strength,
				'suffix'  => '%',
				'presets' => array(
					25 => __( 'Az', 'qrms' ),
					55 => __( 'Dengeli', 'qrms' ),
					85 => __( 'Çok', 'qrms' ),
				),
			)
		);

		$this->select_field(
			array(
				'name'    => 'bg_scheme',
				'label'   => __( 'Yazı rengi şeması', 'qrms' ),
				'hint'    => __( 'Koyu görsellerde açık, açık görsellerde koyu yazı kullanın.', 'qrms' ),
				'value'   => $options['bg_scheme'],
				'options' => array(
					'auto'  => __( 'Otomatik', 'qrms' ),
					'light' => __( 'Açık zemin (koyu yazı)', 'qrms' ),
					'dark'  => __( 'Koyu zemin (açık yazı)', 'qrms' ),
				),
			)
		);

		$this->color_field(
			array(
				'name'  => 'bg_color',
				'value' => $options['bg_color'],
				'label' => __( 'Zemin rengi', 'qrms' ),
				'hint'  => __( 'Görsel yüklenmediğinde veya yüklenirken görünen düz renk.', 'qrms' ),
			)
		);

		$this->card_close();

		/* ---------- Ana buton ---------- */
		$this->card_open( __( 'Ana Buton', 'qrms' ), __( '"Menüye Git" butonunun rengi ve yazısı.', 'qrms' ) );

		$this->color_field(
			array(
				'name'  => 'button_bg_color',
				'value' => $options['button_bg_color'],
				'label' => __( 'Vurgu rengi', 'qrms' ),
				'hint'  => __( 'Butonun kenarlığı, odak halkaları ve seçili dil rozeti bu renktedir.', 'qrms' ),
			)
		);

		$this->color_field(
			array(
				'name'  => 'button_text_color',
				'value' => $options['button_text_color'],
				'label' => __( 'Buton yazı rengi', 'qrms' ),
				'hint'  => __( 'Açık zemin şemasında beyaz seçilirse okunabilirlik için koyuya çevrilir.', 'qrms' ),
			)
		);

		$this->range_field(
			array(
				'name'    => 'button_font_size_px',
				'id'      => 'button_font_size_px',
				'label'   => __( 'Buton yazı boyutu', 'qrms' ),
				'hint'    => __( 'Uzun buton yazılarında küçük ölçü satır taşmasını önler.', 'qrms' ),
				'min'     => 12,
				'max'     => 24,
				'value'   => $font_size,
				'suffix'  => 'px',
				'presets' => array(
					15 => __( 'Küçük', 'qrms' ),
					17 => __( 'Dengeli', 'qrms' ),
					20 => __( 'Büyük', 'qrms' ),
				),
			)
		);

		$this->range_field(
			array(
				'name'       => 'button_opacity',
				'label'      => __( 'Buton dolgu yoğunluğu', 'qrms' ),
				'hint'       => __( 'Butonun içini ne kadar dolduracağı; kenarlık her hâlükârda görünür.', 'qrms' ),
				'min'        => 0,
				'max'        => 100,
				'value'      => $this->opt_int( $options, 'button_opacity', 0, 100 ),
				'suffix'     => '%',
				'presets'    => array(
					10 => __( 'Cam', 'qrms' ),
					22 => __( 'Dengeli', 'qrms' ),
					60 => __( 'Dolu', 'qrms' ),
				),
				// Yüzey CTA'ya uygulandığında butonun dolgusu yüzey
				// ayarından gelir; bu kaydırıcı o an etkisizdir.
				'when_field' => 'btn_surface_apply_cta',
				'when_value' => '0',
				'when_mode'  => 'mute',
			)
		);

		$this->card_close();

		/* ---------- Rozet yüzeyi ---------- */
		$this->card_open( __( 'Rozet Yüzeyi', 'qrms' ), __( 'İletişim, rezervasyon, yorum, Wi-Fi rozetlerinin ve ödeme satırının cam yüzeyi.', 'qrms' ) );

		$this->color_field(
			array(
				'name'  => 'btn_surface_color',
				'value' => $btn_surface_color,
				'label' => __( 'Yüzey rengi', 'qrms' ),
				'hint'  => __( 'Koyu görsellerde beyaz, açık görsellerde koyu yüzey daha iyi durur.', 'qrms' ),
			)
		);

		$this->range_field(
			array(
				'name'    => 'btn_surface_opacity',
				'label'   => __( 'Yüzey yoğunluğu', 'qrms' ),
				'hint'    => __( 'Düşük değer cam etkisini, yüksek değer okunabilirliği artırır.', 'qrms' ),
				'min'     => 0,
				'max'     => 100,
				'value'   => $btn_surface_opacity,
				'suffix'  => '%',
				'presets' => array(
					8  => __( 'Cam', 'qrms' ),
					14 => __( 'Dengeli', 'qrms' ),
					40 => __( 'Dolu', 'qrms' ),
				),
			)
		);

		$this->switch_field(
			array(
				'name'    => 'btn_surface_apply_cta',
				'label'   => __( 'Ana buton da bu yüzeyi kullansın', 'qrms' ),
				'hint'    => __( 'Kapalıyken ana buton vurgu renginden beslenir.', 'qrms' ),
				'text'    => __( 'Ana butonu rozetlerle aynı yüzeye bağla', 'qrms' ),
				'checked' => $btn_surface_cta,
			)
		);

		$this->card_close();

		/* ---------- Animasyon ---------- */
		$this->card_open( __( 'Animasyon', 'qrms' ), __( 'Ekran açılırken içeriğin nasıl belireceği ve bekleme göstergesi.', 'qrms' ) );

		$this->select_field(
			array(
				'name'    => 'animation_type',
				'label'   => __( 'Açılış animasyonu', 'qrms' ),
				'hint'    => __( 'Ziyaretçi hareket azaltma tercihi açtıysa animasyon kendiliğinden kapanır.', 'qrms' ),
				'value'   => $options['animation_type'],
				'options' => array(
					'anim-blur-up' => __( 'Zarif yükseliş', 'qrms' ),
					'anim-elastic' => __( 'Dinamik yay', 'qrms' ),
					'anim-zoom-out' => __( 'Sinematik derinlik', 'qrms' ),
				),
			)
		);

		$this->select_field(
			array(
				'name'    => 'loader_type',
				'label'   => __( 'Yüklenme göstergesi', 'qrms' ),
				'hint'    => __( 'Şeridin sağ üst köşesinde görünür.', 'qrms' ),
				'value'   => $loader_type,
				'options' => array(
					'spinner' => __( 'Dönen yay', 'qrms' ),
					'ring'    => __( 'Halka (kapanma süresini geri sayar)', 'qrms' ),
					'dots'    => __( 'Üç nokta', 'qrms' ),
					'pulse'   => __( 'Nabız', 'qrms' ),
					'none'    => __( 'Gösterme', 'qrms' ),
				),
			)
		);

		$this->color_field(
			array(
				'name'       => 'loader_color',
				'value'      => $loader_color,
				'label'      => __( 'Gösterge rengi', 'qrms' ),
				'hint'       => __( 'Şerit zemininde okunabilecek bir ton seçin.', 'qrms' ),
				'when_field' => 'loader_type',
				'when_value' => 'none',
				'when_not'   => true,
			)
		);

		$this->range_field(
			array(
				'name'       => 'loader_size',
				'label'      => __( 'Gösterge boyutu', 'qrms' ),
				'min'        => 18,
				'max'        => 44,
				'value'      => $loader_size,
				'suffix'     => 'px',
				'when_field' => 'loader_type',
				'when_value' => 'none',
				'when_not'   => true,
			)
		);

		$this->card_close();

		/* ---------- Dil seçici ---------- */
		$ceviri_on = $this->ceviri_available();

		$this->card_open( __( 'Dil Seçici', 'qrms' ), __( 'Şeridin solundaki bayrak; misafir menüyü kendi dilinde açar.', 'qrms' ) );

		$this->switch_field(
			array(
				'name'    => 'ceviri_selector',
				'label'   => __( 'Bayraklı dil seçici', 'qrms' ),
				'hint'    => $ceviri_on
					? __( 'Dil listesi ve çeviri altyapısı QR Çeviri modülünden gelir.', 'qrms' )
					: __( 'QR Çeviri modülü kapalı: tercihiniz kaydedilir, bayrak modül açılınca görünür.', 'qrms' ),
				'text'    => __( 'Karşılama ekranında göster', 'qrms' ),
				'checked' => ! empty( $options['ceviri_selector'] ),
			)
		);

		if ( ! $ceviri_on ) {
			?>
			<p class="qrae-notice">
				<a href="<?php echo esc_url( QRMS_Admin::get_module_page_url( 'qr-ceviri' ) ); ?>"><?php esc_html_e( 'QR Çeviri modülünü etkinleştirin', 'qrms' ); ?></a>
			</p>
			<?php
		}

		if ( $ceviri_on ) {
			$this->field_open(
				array(
					'label'      => __( 'Gösterilecek diller', 'qrms' ),
					'hint'       => __( 'Hiçbiri seçili değilse bayrak basılmaz.', 'qrms' ),
					'when_field' => 'ceviri_selector',
					'when_value' => '1',
					'when_mode'  => 'mute',
				)
			);
			?>
			<fieldset class="splash-ceviri-langs qrae-check-grid">
				<legend class="screen-reader-text"><?php esc_html_e( 'Dil seçicide gösterilecek diller', 'qrms' ); ?></legend>
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
					?>
					<label class="qrae-check">
						<input type="checkbox" name="ceviri_selector_langs[]" value="<?php echo esc_attr( $kod ); ?>" <?php checked( in_array( $kod, $secili_diller, true ) ); ?> />
						<span><?php echo esc_html( $tumu[ $kod ]['flag'] . ' ' . $tumu[ $kod ]['name'] ); ?></span>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<?php
			$this->field_close();
		}

		$this->range_field(
			array(
				'name'       => 'ceviri_flag_size',
				'label'      => __( 'Bayrak boyutu', 'qrms' ),
				'hint'       => __( 'Kutunun genişliği; yükseklik 3:2 oranına göre hesaplanır.', 'qrms' ),
				'min'        => 20,
				'max'        => 48,
				'value'      => $ceviri_flag_size,
				'suffix'     => 'px',
				'when_field' => 'ceviri_selector',
				'when_value' => '1',
				'when_mode'  => 'mute',
			)
		);

		$this->card_close();
	}
	/**
	 * Butonlar sayfasının gövdesi.
	 *
	 * Her buton kendi kartında durur: ne işe yaradığı, açık mı kapalı mı
	 * olduğu ve alanları bir arada. Wi-Fi butonu adres almaz — o kartta
	 * adres alanı hiç basılmaz.
	 *
	 * @param array $options Mevcut ayarlar.
	 * @return void
	 */
	private function render_page_butonlar( $options ) {
		$lang_toggle = ! empty( $options['lang_toggle'] );

		// btn6 (Sosyal Medya butonu) v3.2'de kaldırıldı: sosyal linkler artık
		// doğrudan aksiyon rozeti satırına giriyor. Option anahtarı korunuyor.
		$button_meta = array(
			1 => array(
				'title'       => __( 'Ana Buton', 'qrms' ),
				'placeholder' => __( 'Menüye Git', 'qrms' ),
				'desc'        => __( 'Ekranın altındaki birincil buton. Adres boşsa buton soluk görünür ve tıklanamaz.', 'qrms' ),
				'link'        => true,
				'link_hint'   => __( 'Genellikle menü sayfanızın adresi.', 'qrms' ),
			),
			2 => array(
				'title'       => __( 'İletişim', 'qrms' ),
				'placeholder' => __( 'İletişim', 'qrms' ),
				'desc'        => __( 'Telefon ikonlu rozet. Adres boşsa rozet hiç görünmez.', 'qrms' ),
				'link'        => true,
				'link_hint'   => __( 'Telefon için tel:+905551112233 yazabilirsiniz.', 'qrms' ),
			),
			3 => array(
				'title'       => __( 'Rezervasyon', 'qrms' ),
				'placeholder' => __( 'Rezervasyon İste', 'qrms' ),
				'desc'        => __( 'Takvim ikonlu rozet. Adres boşsa rozet hiç görünmez.', 'qrms' ),
				'link'        => true,
				'link_hint'   => __( 'Rezervasyon formunuzun veya WhatsApp hattınızın adresi.', 'qrms' ),
			),
			4 => array(
				'title'       => __( 'Yorum', 'qrms' ),
				'placeholder' => __( 'Yorum Yap', 'qrms' ),
				'desc'        => __( 'Yıldız ikonlu rozet. Adres boşsa rozet hiç görünmez.', 'qrms' ),
				'link'        => true,
				'link_hint'   => __( 'Google işletme profiliniz veya yorum sayfanız.', 'qrms' ),
			),
			5 => array(
				'title'       => __( 'Wi-Fi', 'qrms' ),
				'placeholder' => __( 'Wifi Şifresi', 'qrms' ),
				'desc'        => __( 'Wi-Fi ikonlu rozet. Adres almaz; dokununca şifre penceresini açar.', 'qrms' ),
				'link'        => false,
				'link_hint'   => '',
			),
		);

		foreach ( $button_meta as $i => $meta ) {
			$text = isset( $options['button_texts'][ 'btn' . $i ] ) ? $options['button_texts'][ 'btn' . $i ] : '';
			$link = isset( $options['button_links'][ 'btn' . $i ] ) ? $options['button_links'][ 'btn' . $i ] : '';
			// Wi-Fi her zaman basılır; diğerleri yalnızca adresi varsa.
			$is_on = ! $meta['link'] || '' !== trim( $link );

			$this->card_open( $meta['title'], $meta['desc'], array( 'class' => 'qrae-card-button' ) );
			?>
			<p class="qrae-state">
				<span class="qrae-badge<?php echo $is_on ? ' is-on' : ''; ?>"
					<?php echo $meta['link'] ? 'data-qrae-state="link_btn' . (int) $i . '"' : ''; ?>>
					<?php echo esc_html( $is_on ? __( 'Ekranda görünüyor', 'qrms' ) : __( 'Gizli', 'qrms' ) ); ?>
				</span>
			</p>
			<?php
			$this->text_field(
				array(
					'name'        => 'button_text_' . $i,
					'id'          => 'button_text_' . $i,
					'label'       => __( 'Buton yazısı', 'qrms' ),
					'value'       => $text,
					'placeholder' => $meta['placeholder'],
				)
			);

			if ( $meta['link'] ) {
				$this->text_field(
					array(
						'name'        => 'link_btn' . $i,
						'id'          => 'link_btn' . $i,
						'type'        => 'url',
						'label'       => __( 'Bağlantı', 'qrms' ),
						'hint'        => $meta['link_hint'],
						'value'       => $link,
						'placeholder' => 'https://',
					)
				);
			} else {
				?>
				<p class="qrae-hint qrae-hint-block"><?php esc_html_e( 'Gösterilecek şifreyi "Ayarlar" ekranındaki Wi-Fi alanına yazın.', 'qrms' ); ?></p>
				<?php
			}

			$this->card_close();
		}

		/* ---------- Sosyal ayraç ---------- */
		$this->card_open( __( 'Sosyal Medya Ayracı', 'qrms' ), __( 'Sosyal rozetlerin üstünde görünen küçük başlık.', 'qrms' ) );

		$this->text_field(
			array(
				'name'        => 'divider_text',
				'id'          => 'divider_text',
				'label'       => __( 'Ayraç yazısı', 'qrms' ),
				'hint'        => __( 'Boş bırakırsanız ayraç basılmaz; sosyal hesap yoksa bölümün tamamı gizlenir.', 'qrms' ),
				'value'       => $options['divider_text'],
				'placeholder' => __( 'Bizi takip edin', 'qrms' ),
			)
		);

		$this->card_close();

		/* ---------- Gelişmiş ---------- */
		?>
		<details class="qrae-card qrae-advanced">
			<summary class="qrae-card-title"><?php esc_html_e( 'Gelişmiş', 'qrms' ); ?></summary>
			<div class="qrae-card-body">
				<?php
				$this->switch_field(
					array(
						'name'    => 'lang_toggle',
						'label'   => __( 'TR/EN düğmesi', 'qrms' ),
						'hint'    => __( 'Eski iki dilli kurulumlar içindir. Bayraklı dil seçici açıksa buna gerek yoktur.', 'qrms' ),
						'text'    => __( 'Ekranda TR/EN düğmesi göster', 'qrms' ),
						'checked' => $lang_toggle,
					)
				);
				?>
				<p class="qrae-hint qrae-hint-block"><?php esc_html_e( 'Düğme yalnızca kayıtlı bir İngilizce çeviri varsa basılır. Dil ziyaretçinin tarayıcısında seçilir; sayfa önbelleği bozulmaz.', 'qrms' ); ?></p>
			</div>
		</details>
		<?php
	}
	/**
	 * Ödeme sayfasının gövdesi: yöntem seçimi ve satırın görünüm biçimi.
	 *
	 * Kayan şeride özel ayarlar yalnızca o biçim seçiliyken görünür; POST
	 * sözleşmesi değişmez (alanlar gizlenir, devre dışı bırakılmaz).
	 *
	 * @param array $options Mevcut ayarlar.
	 * @return void
	 */
	private function render_page_odeme( $options ) {
		$payment_mode      = $this->opt_choice( $options, 'payment_display_mode', array( 'icon_text', 'text_only', 'marquee' ) );
		$marquee_with_icon = ! empty( $options['payment_marquee_with_icon'] );
		$marquee_speed     = $this->opt_int( $options, 'payment_marquee_speed', 6, 60 );

		$this->card_open(
			__( 'Kabul Ettiğiniz Ödeme Yöntemleri', 'qrms' ),
			__( 'Seçtikleriniz karşılama ekranının altında rozet olarak görünür.', 'qrms' )
		);

		$this->render_payment_admin_field( $options );
		?>
		<p class="qrae-hint qrae-hint-block"><?php esc_html_e( 'Hiçbiri seçilmezse ödeme satırı ekranda hiç yer kaplamaz.', 'qrms' ); ?></p>
		<?php
		$this->card_close();

		$this->card_open( __( 'Görünüm Biçimi', 'qrms' ), __( 'Ödeme rozetlerinin ekranda nasıl duracağı.', 'qrms' ) );

		$modes = array(
			'icon_text' => array(
				'title' => __( 'İkon + Yazı', 'qrms' ),
				'desc'  => __( 'Rozet ve adı yan yana durur.', 'qrms' ),
			),
			'text_only' => array(
				'title' => __( 'Sadece Yazı', 'qrms' ),
				'desc'  => __( 'Yalnızca yöntem adları görünür.', 'qrms' ),
			),
			'marquee'   => array(
				'title' => __( 'Kayan Şerit', 'qrms' ),
				'desc'  => __( 'Yöntemler kenardan kenara, kesintisiz akar.', 'qrms' ),
			),
		);
		?>
		<div class="qrae-choice-grid">
			<?php foreach ( $modes as $mode_key => $mode ) : ?>
				<label class="qrae-choice">
					<input type="radio" name="payment_display_mode" value="<?php echo esc_attr( $mode_key ); ?>" <?php checked( $payment_mode, $mode_key ); ?> />
					<span class="qrae-choice-body">
						<span class="qrae-choice-title"><?php echo esc_html( $mode['title'] ); ?></span>
						<span class="qrae-choice-desc"><?php echo esc_html( $mode['desc'] ); ?></span>
					</span>
				</label>
			<?php endforeach; ?>
		</div>
		<?php

		$this->switch_field(
			array(
				'name'       => 'payment_marquee_with_icon',
				'label'      => __( 'Şeritte ikonlar', 'qrms' ),
				'text'       => __( 'Kayan şeritte ikonlar da görünsün', 'qrms' ),
				'checked'    => $marquee_with_icon,
				'when_field' => 'payment_display_mode',
				'when_value' => 'marquee',
			)
		);

		$this->range_field(
			array(
				'name'       => 'payment_marquee_speed',
				'label'      => __( 'Kayma hızı', 'qrms' ),
				'hint'       => __( 'Altı yöntemlik bir turun kaç saniye süreceği; küçük değer hızlandırır.', 'qrms' ),
				'min'        => 6,
				'max'        => 60,
				'value'      => $marquee_speed,
				'suffix'     => ' sn',
				'presets'    => array(
					30 => __( 'Yavaş', 'qrms' ),
					18 => __( 'Dengeli', 'qrms' ),
					10 => __( 'Hızlı', 'qrms' ),
				),
				'when_field' => 'payment_display_mode',
				'when_value' => 'marquee',
			)
		);

		$this->card_close();
	}
	/**
	 * Ayarlar sayfasının gövdesi: süreler, yönlendirme ve Wi-Fi.
	 *
	 * Kaydedilen anahtarlar ve anlamları aynıdır (redirect_seconds,
	 * redirect_url, dismiss_duration, wifi_password). Değişen tek şey
	 * sunum: teknik "0 = ..." açıklamaları yerine hazır seçenekler var,
	 * "süre dolunca ne olsun" sorusu adres alanını kendisi açıp kapatıyor.
	 *
	 * @param array $options Mevcut ayarlar.
	 * @return void
	 */
	private function render_page_davranis( $options ) {
		$redirect_seconds = absint( $options['redirect_seconds'] );
		$redirect_url     = isset( $options['redirect_url'] ) ? trim( (string) $options['redirect_url'] ) : '';
		$dismiss          = absint( $options['dismiss_duration'] );

		// "Süre dolunca" seçimi kaydedilen bir ayar DEĞİLDİR: adresin dolu
		// olup olmamasından okunur. Böylece yeni bir option anahtarı
		// eklenmeden mevcut davranış kullanıcıya anlaşılır biçimde sunulur.
		$auto_action = '' !== $redirect_url ? 'redirect' : 'close';

		$this->card_open(
			__( 'Karşılama Ekranı Davranışı', 'qrms' ),
			__( 'Misafir ekrana dokunmazsa ne kadar beklenecek ve sonra ne olacak.', 'qrms' )
		);

		$this->field_open(
			array(
				'label' => __( 'Gösterim süresi', 'qrms' ),
				'hint'  => __( 'Süre dolunca aşağıdaki davranış uygulanır. Ziyaretçi ekrana dokunursa süre baştan başlar.', 'qrms' ),
				'for'   => 'redirect_seconds',
			)
		);
		?>
		<div class="qrae-inline">
			<input type="number" name="redirect_seconds" id="redirect_seconds" class="qrae-input qrae-input-number"
				value="<?php echo esc_attr( $redirect_seconds ); ?>" min="0" step="1" />
			<span class="qrae-unit"><?php esc_html_e( 'saniye', 'qrms' ); ?></span>
		</div>
		<div class="qrae-presets" role="group" aria-label="<?php esc_attr_e( 'Hazır süreler', 'qrms' ); ?>">
			<button type="button" class="qrae-preset" data-preset-target="redirect_seconds" data-preset-value="0"><?php esc_html_e( 'Kapalı', 'qrms' ); ?></button>
			<button type="button" class="qrae-preset" data-preset-target="redirect_seconds" data-preset-value="5">5 sn</button>
			<button type="button" class="qrae-preset" data-preset-target="redirect_seconds" data-preset-value="7">7 sn</button>
			<button type="button" class="qrae-preset" data-preset-target="redirect_seconds" data-preset-value="12">12 sn</button>
		</div>
		<p class="qrae-hint qrae-hint-block"><?php esc_html_e( '"Kapalı" seçilirse ekran kendiliğinden kapanmaz; misafir dokunana kadar durur.', 'qrms' ); ?></p>
		<?php
		$this->field_close();

		$this->select_field(
			array(
				'name'       => 'qrae_auto_action',
				'id'         => 'qrae_auto_action',
				'label'      => __( 'Süre dolunca', 'qrms' ),
				'hint'       => __( 'Yönlendirme seçilirse aşağıya bir adres yazmanız gerekir.', 'qrms' ),
				'value'      => $auto_action,
				'options'    => array(
					'close'    => __( 'Ekranı kapat', 'qrms' ),
					'redirect' => __( 'Başka bir adrese yönlendir', 'qrms' ),
				),
				'when_field' => 'redirect_seconds',
				'when_value' => '0',
				'when_not'   => true,
			)
		);

		$this->text_field(
			array(
				'name'        => 'redirect_url',
				'id'          => 'redirect_url',
				'type'        => 'url',
				'label'       => __( 'Yönlendirme adresi', 'qrms' ),
				'hint'        => __( 'Süre dolunca misafirin gideceği sayfa.', 'qrms' ),
				'value'       => $redirect_url,
				'placeholder' => 'https://',
				'when_field'  => 'qrae_auto_action',
				'when_value'  => 'redirect',
			)
		);

		$this->card_close();

		/* ---------- Tekrar gösterme ---------- */
		$this->card_open(
			__( 'Tekrar Gösterme', 'qrms' ),
			__( 'Ekranı bir kez kapatan misafire ne zaman yeniden gösterileceği.', 'qrms' )
		);

		$this->field_open(
			array(
				'label' => __( 'Tekrar gösterme sıklığı', 'qrms' ),
				'for'   => 'dismiss_duration',
			)
		);
		?>
		<div class="qrae-presets qrae-presets-block" role="group" aria-label="<?php esc_attr_e( 'Hazır sıklıklar', 'qrms' ); ?>">
			<button type="button" class="qrae-preset" data-preset-target="dismiss_duration" data-preset-value="0"><?php esc_html_e( 'Her ziyarette', 'qrms' ); ?></button>
			<button type="button" class="qrae-preset" data-preset-target="dismiss_duration" data-preset-value="1"><?php esc_html_e( '1 dakika', 'qrms' ); ?></button>
			<button type="button" class="qrae-preset" data-preset-target="dismiss_duration" data-preset-value="5"><?php esc_html_e( '5 dakika', 'qrms' ); ?></button>
			<button type="button" class="qrae-preset" data-preset-target="dismiss_duration" data-preset-value="15"><?php esc_html_e( '15 dakika', 'qrms' ); ?></button>
			<button type="button" class="qrae-preset" data-preset-target="dismiss_duration" data-preset-value="60"><?php esc_html_e( '1 saat', 'qrms' ); ?></button>
		</div>
		<div class="qrae-inline">
			<label class="qrae-inline-label" for="dismiss_duration"><?php esc_html_e( 'Özel süre', 'qrms' ); ?></label>
			<input type="number" name="dismiss_duration" id="dismiss_duration" class="qrae-input qrae-input-number"
				value="<?php echo esc_attr( $dismiss ); ?>" min="0" step="1" />
			<span class="qrae-unit"><?php esc_html_e( 'dakika', 'qrms' ); ?></span>
		</div>
		<p class="qrae-hint qrae-hint-block"><?php esc_html_e( '"Her ziyarette" seçilirse ekran her açılışta yeniden gösterilir; misafirin tarayıcısına hiçbir şey yazılmaz.', 'qrms' ); ?></p>
		<?php
		$this->field_close();

		$this->card_close();

		/* ---------- Wi-Fi ---------- */
		$this->card_open(
			__( 'Wi-Fi', 'qrms' ),
			__( 'Wi-Fi rozetine dokunan misafire gösterilecek şifre.', 'qrms' )
		);

		$this->text_field(
			array(
				'name'        => 'wifi_password',
				'id'          => 'wifi_password',
				'label'       => __( 'Wi-Fi şifresi', 'qrms' ),
				'hint'        => __( 'Boş bırakırsanız pencerede "Henüz bir şifre girilmedi." yazar.', 'qrms' ),
				'value'       => $options['wifi_password'],
				'placeholder' => __( 'örn. misafir2024', 'qrms' ),
			)
		);

		$this->card_close();
	}
	/**
	 * Sosyal medya bağlantıları: hesap başına kart, en fazla 6 aktif hesap.
	 *
	 * Option anahtarları (social_media, social_media_active) ve sıralama
	 * mantığı aynıdır; adres alanı yalnızca hesap açıkken görünür.
	 *
	 * @param array $options Mevcut ayarlar.
	 * @return void
	 */
	private function render_page_sosyal( $options ) {
		$social_map    = $this->social_media_map();
		$social_state  = $this->resolve_social_media_state( $options );
		$social_active = $social_state['active'];
		$social_urls   = $social_state['urls'];

		$this->card_open(
			__( 'Sosyal Hesaplar', 'qrms' ),
			__( 'Açtığınız hesaplar karşılama ekranında rozet olarak görünür.', 'qrms' )
		);
		?>
		<p class="qrae-counter">
			<strong id="qrae-social-count"><?php echo esc_html( count( $social_active ) ); ?></strong> / 6
			<span><?php esc_html_e( 'hesap açık', 'qrms' ); ?></span>
		</p>
		<p class="splash-social-limit-note qrae-limit-note" role="status"><?php esc_html_e( 'En fazla 6 hesap açık olabilir; yeni bir hesap eklemek için önce birini kapatın.', 'qrms' ); ?></p>

		<input type="hidden" name="social_media_order" id="social_media_order" value="<?php echo esc_attr( implode( ',', $social_active ) ); ?>" />

		<div class="splash-social-grid qrae-social-grid">
			<?php
			foreach ( $social_map as $key => $meta ) :
				$is_on = in_array( $key, $social_active, true );
				$url   = isset( $social_urls[ $key ] ) ? $social_urls[ $key ] : '';
				?>
				<div class="splash-social-row qrae-social-row<?php echo $is_on ? ' is-on' : ''; ?>">
					<label class="splash-social-check-label qrae-social-head" for="qrae-social-<?php echo esc_attr( $key ); ?>">
						<input type="checkbox" class="splash-social-check" id="qrae-social-<?php echo esc_attr( $key ); ?>"
							name="social_media_active[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $is_on ); ?> />
						<span class="qrae-switch-track" aria-hidden="true"><span class="qrae-switch-thumb"></span></span>
						<span class="qrae-social-icon" aria-hidden="true"><?php echo $this->icon_svg( $meta['icon'], 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — kod içinde sabit SVG ?></span>
						<span class="qrae-social-name"><?php echo esc_html( $meta['label'] ); ?></span>
					</label>
					<div class="qrae-social-url">
						<label class="screen-reader-text" for="qrae-social-url-<?php echo esc_attr( $key ); ?>">
							<?php /* translators: %s: platform adı. */ printf( esc_html__( '%s profil adresi', 'qrms' ), esc_html( $meta['label'] ) ); ?>
						</label>
						<input type="text" id="qrae-social-url-<?php echo esc_attr( $key ); ?>"
							name="social_media_url_<?php echo esc_attr( $key ); ?>"
							value="<?php echo esc_attr( $url ); ?>"
							class="qrae-input" data-qrae-url="1" inputmode="url" spellcheck="false" autocomplete="off"
							aria-describedby="qrae-social-url-<?php echo esc_attr( $key ); ?>-error"
							placeholder="https://" />
						<p class="qrae-error" id="qrae-social-url-<?php echo esc_attr( $key ); ?>-error" hidden></p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="qrae-hint qrae-hint-block"><?php esc_html_e( 'Adresi boş kalan hesap ekranda görünmez. Hiç hesap yoksa sosyal bölümün tamamı gizlenir.', 'qrms' ); ?></p>
		<?php
		$this->card_close();
	}
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

		if ( class_exists( 'QRMS_Helpers' ) && method_exists( 'QRMS_Helpers', 'legacy_slug_hit' ) ) {
			QRMS_Helpers::legacy_slug_hit( $page );
		}

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
				'title' => __( 'Karşılama Ekranı', 'qrms' ),
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
		<div class="splash-media-field qrae-media">
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" />
			<div class="image-preview qrae-media-thumb <?php echo esc_attr( $preview_class ); ?>">
				<?php if ( $img_url ) : ?>
					<img src="<?php echo esc_url( $img_url ); ?>" alt="" />
				<?php else : ?>
					<span class="qrae-media-empty"><?php esc_html_e( 'Görsel seçilmedi', 'qrms' ); ?></span>
				<?php endif; ?>
			</div>
			<p class="splash-media-actions qrae-media-actions">
				<button type="button" class="button upload-image-btn" data-target="<?php echo esc_attr( $name ); ?>" data-warn="<?php echo esc_attr( $warn ); ?>">
					<?php echo esc_html( $img_url ? __( 'Görseli değiştir', 'qrms' ) : __( 'Görsel seç', 'qrms' ) ); ?>
				</button>
				<button type="button" class="button-link splash-remove-image<?php echo $value ? '' : ' hidden'; ?>" data-target="<?php echo esc_attr( $name ); ?>">
					<?php esc_html_e( 'Kaldır', 'qrms' ); ?>
				</button>
			</p>
			<div class="splash-media-warnings" data-for="<?php echo esc_attr( $name ); ?>" role="status"></div>
		</div>
		<?php
	}
}
