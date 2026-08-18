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
	 * Sihirbaz sayfasını kaydeder ve menüden gizler.
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

		// Sayfa erişilebilir kalsın ama menüde görünmesin.
		remove_submenu_page( QRMS_Admin::MENU_SLUG, self::PAGE_SLUG );
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
