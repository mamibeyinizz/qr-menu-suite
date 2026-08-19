<?php
/**
 * Kurulum sihirbazı: tek ekran, lisans doğrulama formu.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tek ekranlı kurulum sihirbazı ve yeniden doğrulama formu.
 */
class QRMS_Wizard {

	/**
	 * Sihirbaz sayfasının slug'ı (menüde görünmez, sadece doğrudan erişilir).
	 */
	const PAGE_SLUG = 'qrms-wizard';

	/**
	 * Kurulumun tamamlandığını tutan option.
	 */
	const OPT_SETUP_COMPLETED = 'qrms_setup_completed';

	/**
	 * Aktivasyon sonrası tek seferlik yönlendirme bayrağı.
	 */
	const REDIRECT_TRANSIENT = 'qrms_activation_redirect';

	/**
	 * Form nonce action adı.
	 */
	const NONCE_ACTION = 'qrms_validate_license';

	/**
	 * Hook kayıtları.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ), 20 );
		add_action( 'current_screen', array( __CLASS__, 'hide_page_from_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_to_wizard' ) );
	}

	/**
	 * Kurulum tamamlandı mı?
	 *
	 * @return bool
	 */
	public static function is_setup_completed() {
		return (bool) get_option( self::OPT_SETUP_COMPLETED, false );
	}

	/**
	 * Aktivasyonda çağrılır: kurulum yapılmamışsa tek seferlik yönlendirme bayrağı bırakır.
	 *
	 * @return void
	 */
	public static function maybe_flag_activation_redirect() {
		if ( ! self::is_setup_completed() ) {
			set_transient( self::REDIRECT_TRANSIENT, 1, 60 );
		}
	}

	/**
	 * Sihirbaz sayfasını üst menümüzün gizli bir alt sayfası olarak kaydeder.
	 *
	 * Kayıt gerçek bir alt menü olarak yapılır (parent: QRMS_Admin::MENU_SLUG);
	 * menüden gizleme işi `admin_head` üzerinden yapılır, bkz.
	 * hide_page_from_menu().
	 *
	 * @return void
	 */
	public static function register_page() {
		add_submenu_page(
			QRMS_Admin::MENU_SLUG,
			__( 'QR Menu Suite Kurulumu', 'qrms' ),
			__( 'Kurulum', 'qrms' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Sihirbazı menü çıktısından gizler.
	 *
	 * `admin_menu` içinde remove_submenu_page() çağırmak sayfayı erişilemez
	 * hale getirir: WordPress, admin.php'de route'u `admin_menu`den SONRA
	 * çözer (wp-admin/admin.php: önce menu.php, sonra get_plugin_page_hook())
	 * ve sayfanın hook adını hesaplarken parent'ı $submenu içinde arar
	 * (get_admin_page_parent()). Alt menü o sırada silinmiş olursa hook adı
	 * "<üst menü>_page_qrms-wizard" yerine "admin_page_qrms-wizard" olarak
	 * hesaplanır, $_registered_pages ile eşleşmez ve WordPress 403 "Bu sayfaya
	 * erişmenize izin verilmiyor" der.
	 *
	 * `current_screen` ise route çözüldükten hemen sonra çalışır
	 * (wp-admin/admin.php: get_plugin_page_hook() → set_current_screen()), yani
	 * hem menü HTML'i basılmadan hem de komut paleti verisi ($menu/$submenu
	 * üzerinden, WP 6.9+) toplanmadan önce. Gizlemenin doğru yeri burasıdır.
	 *
	 * @param WP_Screen|null $screen Geçerli ekran (current_screen hook'undan).
	 * @return void
	 */
	public static function hide_page_from_menu( $screen = null ) {
		remove_submenu_page( QRMS_Admin::MENU_SLUG, self::PAGE_SLUG );

		// Sayfa $submenu'den çıkınca get_admin_page_title() başlığı bulamaz ve
		// $title null kalır (boş tarayıcı başlığı + PHP 8.1+ deprecation
		// uyarısı). Sihirbaz ekranındaysak başlığı elle veriyoruz.
		if ( self::is_wizard_screen( $screen ) ) {
			$GLOBALS['title'] = __( 'QR Menu Suite Kurulumu', 'qrms' );
		}
	}

	/**
	 * Geçerli ekran sihirbaz sayfası mı?
	 *
	 * @param WP_Screen|null $screen Ekran nesnesi.
	 * @return bool
	 */
	private static function is_wizard_screen( $screen ) {
		if ( isset( $screen->id ) && false !== strpos( (string) $screen->id, self::PAGE_SLUG ) ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		return self::PAGE_SLUG === $page;
	}

	/**
	 * Kurulum tamamlanmamışsa, aktivasyondan sonraki ilk admin sayfasında
	 * sihirbaza yönlendirir. Kurulum bir kez tamamlandıysa ASLA yönlendirmez.
	 *
	 * @return void
	 */
	public static function maybe_redirect_to_wizard() {
		if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}

		if ( ! get_transient( self::REDIRECT_TRANSIENT ) ) {
			return;
		}

		delete_transient( self::REDIRECT_TRANSIENT );

		// Kurulum tamamlandıysa otomatik yönlendirme yok.
		if ( self::is_setup_completed() ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Toplu plugin aktivasyonunda kullanıcının akışını bölme.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['activate-multi'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( self::PAGE_SLUG === $current_page ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	/**
	 * Form gönderimini işler ve doğrulama sonucunu döndürür.
	 *
	 * @return array{status:string,message:string,modules:string[]}|null Gönderim yoksa null.
	 */
	public static function handle_submission() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST['qrms_submit_license'] ) ) {
			return null;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return null;
		}

		check_admin_referer( self::NONCE_ACTION, 'qrms_nonce' );

		$api_key    = isset( $_POST['qrms_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['qrms_api_key'] ) ) : '';
		$server_url = isset( $_POST['qrms_server_url'] ) ? sanitize_text_field( wp_unslash( $_POST['qrms_server_url'] ) ) : '';

		if ( '' === trim( $api_key ) ) {
			return array(
				'status'  => 'empty',
				'message' => __( 'Lütfen API anahtarınızı girin.', 'qrms' ),
				'modules' => QRMS_License_Client::get_active_modules(),
			);
		}

		$result = qrms_validate_license( $api_key, $server_url );

		if ( 'active' === $result['status'] ) {
			update_option( self::OPT_SETUP_COMPLETED, true );
		}

		return $result;
	}

	/**
	 * Sihirbaz ekranını çizer (tek ekran).
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		$result = self::handle_submission();
		?>
		<div class="wrap qrms-wrap qrms-wizard">
			<div class="qrms-card">
				<h1 class="qrms-title"><?php esc_html_e( 'QR Menu Suite Kurulumu', 'qrms' ); ?></h1>

				<?php if ( is_array( $result ) && 'active' === $result['status'] ) : ?>
					<?php self::render_success( $result['modules'] ); ?>
				<?php else : ?>
					<p class="qrms-lead">
						<?php esc_html_e( 'Lisans anahtarınızı girin, size tanımlı modüller otomatik olarak aktif edilecek.', 'qrms' ); ?>
					</p>
					<?php self::render_form( $result ); ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Başarılı kurulum ekranı: aktif edilen modüller + devam butonu.
	 *
	 * @param string[] $modules Aktif modül slug'ları.
	 * @return void
	 */
	public static function render_success( $modules ) {
		?>
		<div class="qrms-alert qrms-alert-success">
			<p><?php esc_html_e( 'Lisansınız doğrulandı. Aşağıdaki modüller aktif edildi:', 'qrms' ); ?></p>
		</div>

		<ul class="qrms-module-list">
			<?php foreach ( $modules as $slug ) : ?>
				<li class="qrms-module-list-item">
					<span class="qrms-check" aria-hidden="true">&#10003;</span>
					<?php echo esc_html( QRMS_Helpers::get_module_name( $slug ) ); ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( empty( $modules ) ) : ?>
			<p class="qrms-muted">
				<?php esc_html_e( 'Lisansınıza tanımlı bir modül bulunamadı. Lütfen sağlayıcınızla iletişime geçin.', 'qrms' ); ?>
			</p>
		<?php endif; ?>

		<a class="qrms-button qrms-button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . QRMS_Admin::MENU_SLUG ) ); ?>">
			<?php esc_html_e( 'Devam Et', 'qrms' ); ?>
		</a>
		<?php
	}

	/**
	 * Lisans formu. Hem sihirbazda hem Genel Ayarlar'daki yeniden doğrulama
	 * bölümünde aynı işaretleme kullanılır.
	 *
	 * @param array|null $result Önceki denemenin sonucu (varsa).
	 * @return void
	 */
	public static function render_form( $result = null ) {
		$status = is_array( $result ) && isset( $result['status'] ) ? $result['status'] : '';

		// Kullanıcının girdiği değerler kaybolmasın.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$api_key = isset( $_POST['qrms_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['qrms_api_key'] ) ) : QRMS_License_Client::get_api_key();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$server_url = isset( $_POST['qrms_server_url'] ) ? sanitize_text_field( wp_unslash( $_POST['qrms_server_url'] ) ) : QRMS_License_Client::get_server_url();

		$submit_label = ( 'unreachable' === $status )
			? __( 'Tekrar Dene', 'qrms' )
			: __( 'Doğrula ve Kur', 'qrms' );
		?>

		<?php if ( '' !== $status ) : ?>
			<div class="qrms-alert qrms-alert-error">
				<p>
					<?php
					echo esc_html(
						'empty' === $status
							? $result['message']
							: QRMS_Helpers::get_status_message( $status )
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<form method="post" class="qrms-form">
			<?php wp_nonce_field( self::NONCE_ACTION, 'qrms_nonce' ); ?>

			<div class="qrms-field">
				<label class="qrms-label" for="qrms_api_key"><?php esc_html_e( 'API Anahtarı', 'qrms' ); ?></label>
				<input
					type="text"
					id="qrms_api_key"
					name="qrms_api_key"
					class="qrms-input"
					value="<?php echo esc_attr( $api_key ); ?>"
					autocomplete="off"
					autocapitalize="off"
					autocorrect="off"
					spellcheck="false"
					required
				/>
			</div>

			<div class="qrms-field qrms-field-secondary">
				<label class="qrms-label qrms-label-small" for="qrms_server_url"><?php esc_html_e( 'Sunucu Adresi', 'qrms' ); ?></label>
				<input
					type="text"
					id="qrms_server_url"
					name="qrms_server_url"
					class="qrms-input qrms-input-small"
					value="<?php echo esc_attr( $server_url ); ?>"
					autocomplete="off"
					autocapitalize="off"
					autocorrect="off"
					spellcheck="false"
					inputmode="url"
				/>
				<p class="qrms-help"><?php esc_html_e( 'Değiştirmeniz gerekmiyorsa olduğu gibi bırakın.', 'qrms' ); ?></p>
			</div>

			<button type="submit" name="qrms_submit_license" value="1" class="qrms-button qrms-button-primary">
				<?php echo esc_html( $submit_label ); ?>
			</button>
		</form>
		<?php
	}
}
