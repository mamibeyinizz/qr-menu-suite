<?php
/**
 * Admin menü çatısı: üst menü, çekirdek sayfalar ve modül placeholder'ları.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin menüsü ve çekirdek admin ekranları.
 */
class QRMS_Admin {

	/**
	 * Üst seviye menü / Genel Bakış sayfası slug'ı.
	 */
	const MENU_SLUG = 'qrms-overview';

	/**
	 * Genel Ayarlar sayfası slug'ı.
	 */
	const SETTINGS_SLUG = 'qrms-settings';

	/**
	 * Modül sayfalarının slug öneki.
	 */
	const MODULE_PAGE_PREFIX = 'qrms-module-';

	/**
	 * Sayfaların gerektirdiği yetki.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Üst menünün konumu.
	 *
	 * Tam sayı yerine bilinçli olarak ondalıklı bir değer kullanılıyor:
	 * WordPress menü öğelerini $menu dizisinde konumu anahtar yaparak tutar
	 * (ör. $menu['30']). Aynı tam sayı konumunu kullanan başka bir plugin
	 * (ör. eski qr-menu-official) diziye doğrudan yazdığında veya bizden sonra
	 * kaydolduğunda o slotu ezer ve menümüz hiç görünmez. Ondalıklı ve bize
	 * özgü bir konum bu çakışmayı pratikte imkânsız kılar.
	 *
	 * @var float
	 */
	const MENU_POSITION = 57.3;

	/**
	 * Modül slug'ı -> kendi sayfasını basan callback.
	 *
	 * Modüller kendi init'lerinde (plugins_loaded, öncelik 20) kayıt olur;
	 * kayıt olmayan modüller placeholder görmeye devam eder.
	 *
	 * @var array<string,callable>
	 */
	private static $module_pages = array();

	/**
	 * Bir modülün kendi yönetim sayfasını kaydeder.
	 *
	 * Modül yükleyici `admin_menu`'den önce çalıştığı için, register_menu()
	 * alt menüyü kurarken bu kaydı görür ve placeholder yerine modülün kendi
	 * ekranını bağlar.
	 *
	 * @param string   $slug     Modül slug'ı.
	 * @param callable $callback Sayfayı basan çağrılabilir.
	 * @return void
	 */
	public static function register_module_page( $slug, $callback ) {
		if ( ! QRMS_Helpers::is_valid_module( $slug ) || ! is_callable( $callback ) ) {
			return;
		}

		self::$module_pages[ $slug ] = $callback;
	}

	/**
	 * Modülün kayıtlı sayfa callback'i (yoksa null).
	 *
	 * @param string $slug Modül slug'ı.
	 * @return callable|null
	 */
	public static function get_module_page_callback( $slug ) {
		return isset( self::$module_pages[ $slug ] ) ? self::$module_pages[ $slug ] : null;
	}

	/**
	 * Hook kayıtları.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_menu', array( __CLASS__, 'ensure_menu_registered' ), 999 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		// Diğer modül stillerinden sonra yüklensin ki native menü gizleme kazansın.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_menu_css' ), 100 );
	}

	/**
	 * Şu an bu plugin'in ekranlarından birinde miyiz?
	 *
	 * @return bool
	 */
	public static function is_plugin_screen() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		return ( '' !== $page && 0 === strpos( $page, 'qrms' ) );
	}

	/**
	 * Modül sayfası slug'ı.
	 *
	 * @param string $slug Modül slug'ı.
	 * @return string
	 */
	public static function get_module_page_slug( $slug ) {
		return self::MODULE_PAGE_PREFIX . $slug;
	}

	/**
	 * Menü ikonu (inline SVG data URI).
	 *
	 * @return string
	 */
	private static function get_menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="black">'
			. '<path d="M2 2h7v7H2V2zm2 2v3h3V4H4zM11 2h7v7h-7V2zm2 2v3h3V4h-3zM2 11h7v7H2v-7zm2 2v3h3v-3H4z"/>'
			. '<path d="M11 11h3v3h-3v-3zm4 0h3v2h-3v-2zm-4 4h2v3h-2v-3zm3 1h4v2h-4v-2zm2-2h2v2h-2v-2z"/>'
			. '</svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Üst menü, çekirdek sayfalar ve SADECE aktif modüllerin alt menülerini kaydeder.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'QR Menü', 'qrms' ),
			__( 'QR Menü', 'qrms' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( __CLASS__, 'render_overview' ),
			self::get_menu_icon(),
			self::MENU_POSITION
		);

		// Genel Bakış: her zaman, en üstte.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Genel Bakış', 'qrms' ),
			__( 'Genel Bakış', 'qrms' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( __CLASS__, 'render_overview' )
		);

		// Modüller: yalnızca lisansta aktif olanlar (sabit sırayla).
		$active = QRMS_License_Client::get_active_modules();

		foreach ( QRMS_Helpers::MODULE_SLUGS as $slug ) {
			if ( ! in_array( $slug, $active, true ) ) {
				continue;
			}

			$name = QRMS_Helpers::get_module_name( $slug );

			add_submenu_page(
				self::MENU_SLUG,
				$name,
				$name,
				self::CAPABILITY,
				self::get_module_page_slug( $slug ),
				static function () use ( $slug ) {
					QRMS_Admin::render_module_page( $slug );
				}
			);
		}

		// Genel Ayarlar: her zaman, en altta.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Genel Ayarlar', 'qrms' ),
			__( 'Genel Ayarlar', 'qrms' ),
			self::CAPABILITY,
			self::SETTINGS_SLUG,
			array( __CLASS__, 'render_settings' )
		);
	}

	/**
	 * Üst menü satırı $menu dizisinde duruyor mu?
	 *
	 * @return bool Menü dizisi henüz kurulmamışsa true döner (karışma).
	 */
	private static function is_menu_present() {
		global $menu;

		if ( ! is_array( $menu ) ) {
			return true;
		}

		foreach ( $menu as $item ) {
			if ( isset( $item[2] ) && self::MENU_SLUG === $item[2] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Üst menü başka bir plugin tarafından ezildiyse geri ekler.
	 *
	 * Menü satırlarını $menu dizisine doğrudan yazan (konum slotunu ezen)
	 * pluginlere karşı emniyet kemeri; `admin_menu` zincirinin sonunda çalışır.
	 * Sayfalar ilk kayıtta zaten $_registered_pages'e girdiği için burada
	 * yalnızca menüdeki satır geri gelir, sayfa erişimi etkilenmez.
	 *
	 * Menüyü bilerek kaldıran siteler `qrms_ensure_menu_registered` filtresini
	 * false döndürerek bu davranışı kapatabilir.
	 *
	 * @return void
	 */
	public static function ensure_menu_registered() {
		/**
		 * Ezilen üst menünün geri eklenip eklenmeyeceğini belirler.
		 *
		 * @param bool $ensure Varsayılan true.
		 */
		if ( ! apply_filters( 'qrms_ensure_menu_registered', true ) ) {
			return;
		}

		if ( self::is_menu_present() ) {
			return;
		}

		add_menu_page(
			__( 'QR Menü', 'qrms' ),
			__( 'QR Menü', 'qrms' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( __CLASS__, 'render_overview' ),
			self::get_menu_icon(),
			self::MENU_POSITION
		);
	}

	/**
	 * WordPress sol menüsü için native flyout gizlemeyi geri yükler.
	 *
	 * Sayfa kartı stillerinden ayrıdır ve her yönetim ekranında yüklenir:
	 * alt menü takılı kalınca çakışma başka bir üst menüde (Eklentiler vb.)
	 * görünür.
	 *
	 * @return void
	 */
	public static function enqueue_admin_menu_css() {
		wp_enqueue_style(
			'qrms-admin-menu',
			QRMS_PLUGIN_URL . 'assets/css/admin-menu.css',
			array( 'admin-menu' ),
			QRMS_VERSION
		);
	}

	/**
	 * Admin CSS/JS dosyalarını sadece bu plugin'in ekranlarında yükler.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( ! self::is_plugin_screen() ) {
			return;
		}

		wp_enqueue_style(
			'qrms-admin',
			QRMS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			QRMS_VERSION
		);

		wp_enqueue_script(
			'qrms-admin',
			QRMS_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			QRMS_VERSION,
			true
		);

		wp_localize_script(
			'qrms-admin',
			'qrmsAdmin',
			array(
				'validating' => __( 'Doğrulanıyor…', 'qrms' ),
			)
		);
	}

	/**
	 * Genel Bakış ekranı.
	 *
	 * @return void
	 */
	public static function render_overview() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		$active = QRMS_License_Client::get_active_modules();
		?>
		<div class="wrap qrms-wrap">
			<h1 class="qrms-title"><?php esc_html_e( 'QR Menü — Genel Bakış', 'qrms' ); ?></h1>

			<div class="qrms-card">
				<h2 class="qrms-card-title"><?php esc_html_e( 'Aktif Modülleriniz', 'qrms' ); ?></h2>

				<?php if ( empty( $active ) ) : ?>
					<p class="qrms-muted"><?php esc_html_e( 'Henüz aktif modül yok. Lisansınızı doğruladığınızda modülleriniz burada listelenir.', 'qrms' ); ?></p>
					<a class="qrms-button qrms-button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ) ); ?>">
						<?php esc_html_e( 'Lisansı Doğrula', 'qrms' ); ?>
					</a>
				<?php else : ?>
					<ul class="qrms-module-list">
						<?php foreach ( $active as $slug ) : ?>
							<li class="qrms-module-list-item">
								<span class="qrms-check" aria-hidden="true">&#10003;</span>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::get_module_page_slug( $slug ) ) ); ?>">
									<?php echo esc_html( QRMS_Helpers::get_module_name( $slug ) ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Modül sayfası: modül kendi ekranını kaydettiyse onu, aksi halde
	 * placeholder'ı basar.
	 *
	 * @param string $slug Modül slug'ı.
	 * @return void
	 */
	public static function render_module_page( $slug ) {
		$callback = self::get_module_page_callback( $slug );

		if ( null === $callback ) {
			self::render_module_placeholder( $slug );
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		call_user_func( $callback );
	}

	/**
	 * Modül placeholder sayfası ("Yakında").
	 *
	 * @param string $slug Modül slug'ı.
	 * @return void
	 */
	public static function render_module_placeholder( $slug ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}
		?>
		<div class="wrap qrms-wrap">
			<h1 class="qrms-title"><?php echo esc_html( QRMS_Helpers::get_module_name( $slug ) ); ?></h1>

			<div class="qrms-card">
				<p class="qrms-muted"><?php esc_html_e( 'Bu modül yakında burada olacak.', 'qrms' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Genel Ayarlar ekranı: lisans durumu ve yeniden doğrulama.
	 *
	 * @return void
	 */
	public static function render_settings() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		// Yeniden doğrulama formu gönderildiyse işle (otomatik redirect asla tetiklenmez).
		$result = QRMS_Wizard::handle_submission();

		$status  = QRMS_License_Client::get_last_status();
		$active  = QRMS_License_Client::get_active_modules();
		$is_open = ( is_array( $result ) && 'active' !== $result['status'] );
		?>
		<div class="wrap qrms-wrap">
			<h1 class="qrms-title"><?php esc_html_e( 'Genel Ayarlar', 'qrms' ); ?></h1>

			<?php if ( is_array( $result ) && 'active' === $result['status'] ) : ?>
				<div class="qrms-alert qrms-alert-success">
					<p><?php esc_html_e( 'Lisansınız doğrulandı, modül listeniz güncellendi.', 'qrms' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="qrms-card">
				<h2 class="qrms-card-title"><?php esc_html_e( 'Lisans Durumu', 'qrms' ); ?></h2>

				<ul class="qrms-detail-list">
					<li class="qrms-detail">
						<span class="qrms-detail-label"><?php esc_html_e( 'Durum', 'qrms' ); ?></span>
						<span class="qrms-detail-value qrms-status qrms-status-<?php echo esc_attr( '' !== $status ? $status : 'unknown' ); ?>">
							<?php echo esc_html( QRMS_Helpers::get_status_label( $status ) ); ?>
						</span>
					</li>
					<li class="qrms-detail">
						<span class="qrms-detail-label"><?php esc_html_e( 'Son senkronizasyon', 'qrms' ); ?></span>
						<span class="qrms-detail-value"><?php echo esc_html( QRMS_Helpers::format_datetime( QRMS_License_Client::get_last_sync() ) ); ?></span>
					</li>
					<li class="qrms-detail">
						<span class="qrms-detail-label"><?php esc_html_e( 'Sunucu adresi', 'qrms' ); ?></span>
						<span class="qrms-detail-value qrms-detail-break"><?php echo esc_html( QRMS_License_Client::get_server_url() ); ?></span>
					</li>
					<li class="qrms-detail">
						<span class="qrms-detail-label"><?php esc_html_e( 'Alan adı', 'qrms' ); ?></span>
						<span class="qrms-detail-value qrms-detail-break"><?php echo esc_html( QRMS_Helpers::get_site_domain() ); ?></span>
					</li>
				</ul>
			</div>

			<div class="qrms-card">
				<h2 class="qrms-card-title"><?php esc_html_e( 'Aktif Modüller', 'qrms' ); ?></h2>

				<?php if ( empty( $active ) ) : ?>
					<p class="qrms-muted"><?php esc_html_e( 'Aktif modül bulunmuyor.', 'qrms' ); ?></p>
				<?php else : ?>
					<ul class="qrms-module-list">
						<?php foreach ( $active as $slug ) : ?>
							<li class="qrms-module-list-item">
								<span class="qrms-check" aria-hidden="true">&#10003;</span>
								<?php echo esc_html( QRMS_Helpers::get_module_name( $slug ) ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<details class="qrms-card qrms-details" <?php echo $is_open ? 'open' : ''; ?>>
				<summary class="qrms-summary"><?php esc_html_e( 'Lisansı Yeniden Doğrula', 'qrms' ); ?></summary>

				<div class="qrms-details-body">
					<p class="qrms-muted">
						<?php esc_html_e( 'Anahtarınızı veya sunucu adresinizi değiştirdiyseniz buradan yeniden doğrulayabilirsiniz.', 'qrms' ); ?>
					</p>
					<?php QRMS_Wizard::render_form( $result ); ?>
				</div>
			</details>
		</div>
		<?php
	}
}
