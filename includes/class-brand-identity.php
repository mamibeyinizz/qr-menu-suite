<?php
/**
 * Merkezi restoran markası — depo ve erişim API'si.
 *
 * Modüller doğrudan option okumamalı; logo, marka adı ve alt metin bu sınıf
 * üzerinden alınır. Phase 1 yalnızca depo + yönetim; mevcut modül logo
 * alanlarına dokunulmaz.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * QRMS restoran markası.
 */
class QRMS_Brand_Identity {

	/**
	 * Ayarların tutulduğu option.
	 */
	const OPTION = 'qrms_marka_kimligi';

	/**
	 * Ayar formunun nonce eylemi.
	 */
	const NONCE = 'qrms_marka_kimligi_kaydet';

	/**
	 * Ayarların kaydedildiği admin-post eylemi.
	 */
	const ACTION = 'qrms_marka_kimligi_kaydet';

	/**
	 * Varsayılan marka metinleri (Premium Admin Shell yedek).
	 */
	const FALLBACK_NAME = 'QR MENU';

	/**
	 * Varsayılan alt marka metni (Premium Admin Shell yedek).
	 */
	const FALLBACK_SUB = 'OFFICIAL';

	/**
	 * Hook kayıtları.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_settings_submit' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	/**
	 * Varsayılan ayar şeması.
	 *
	 * @return array{logo:int,ad:string,alt_ad:string}
	 */
	public static function default_settings() {
		return array(
			'logo'   => 0,
			'ad'     => '',
			'alt_ad' => '',
		);
	}

	/**
	 * Kayıtlı ayarlar (eksik anahtarlar varsayılanla tamamlanır).
	 *
	 * @return array{logo:int,ad:string,alt_ad:string}
	 */
	public static function get_settings() {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( self::default_settings(), $stored );
	}

	/**
	 * Ham POST verisini temizleyip kayda hazırlar.
	 *
	 * @param array<string,mixed> $raw Ham girdi.
	 * @return array{logo:int,ad:string,alt_ad:string}
	 */
	public static function sanitize_settings( array $raw ) {
		$out = self::default_settings();

		$out['logo'] = isset( $raw['logo'] ) ? absint( $raw['logo'] ) : 0;

		if ( isset( $raw['ad'] ) ) {
			$out['ad'] = sanitize_text_field( (string) $raw['ad'] );
		}

		if ( isset( $raw['alt_ad'] ) ) {
			$out['alt_ad'] = sanitize_text_field( (string) $raw['alt_ad'] );
		}

		return $out;
	}

	/**
	 * Ana logo ek ID'si.
	 *
	 * @return int
	 */
	public static function get_logo_id() {
		return (int) self::get_settings()['logo'];
	}

	/**
	 * Merkezi logo geçerliyse onu, değilse legacy attachment ID'sini döndürür.
	 *
	 * HFB option'larını okumaz; legacy değer çağıran tarafından verilir.
	 *
	 * @param int $legacy_id Bağlamdaki mevcut logo (ör. hfb_header_options['logo']).
	 * @return int
	 */
	public static function resolve_logo_id( $legacy_id ) {
		$legacy_id = absint( $legacy_id );
		$central   = self::get_logo_id();

		if ( $central > 0 && self::attachment_is_valid_logo( $central ) ) {
			return $central;
		}

		return $legacy_id;
	}

	/**
	 * Attachment ID görüntü olarak kullanılabilir mi?
	 *
	 * @param int $attachment_id Ek dosya kimliği.
	 * @return bool
	 */
	public static function attachment_is_valid_logo( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id ) {
			return false;
		}

		if ( function_exists( 'wp_attachment_is_image' ) && ! wp_attachment_is_image( $attachment_id ) ) {
			return false;
		}

		if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
			return false;
		}

		$url = wp_get_attachment_image_url( $attachment_id, 'full' );

		return '' !== $url;
	}

	/**
	 * Ana logo URL'si (çözülmüş; yoksa boş).
	 *
	 * @param string $size WordPress görsel boyutu.
	 * @return string
	 */
	public static function get_logo_url( $size = 'medium' ) {
		$id = self::get_logo_id();

		if ( ! $id || ! function_exists( 'wp_get_attachment_image_url' ) ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $id, $size );

		return $url ? (string) $url : '';
	}

	/**
	 * Marka adı (boş olabilir).
	 *
	 * @return string
	 */
	public static function get_name() {
		return (string) self::get_settings()['ad'];
	}

	/**
	 * Kısa / alt marka metni (boş olabilir).
	 *
	 * @return string
	 */
	public static function get_short_name() {
		return (string) self::get_settings()['alt_ad'];
	}

	/**
	 * Merkezi marka adı doluysa onu, değilse legacy üst satırı döndürür.
	 *
	 * Option yazmaz; yalnızca okuma ve çözümleme yapar.
	 *
	 * @param string $legacy_line1 Bağlamdaki mevcut üst satır (ör. brand_line1, baslik).
	 * @return string
	 */
	public static function resolve_name( $legacy_line1 ) {
		$central = trim( self::get_name() );

		if ( '' !== $central ) {
			return $central;
		}

		return trim( (string) $legacy_line1 );
	}

	/**
	 * Merkezi kısa marka adı doluysa onu, değilse legacy alt satırı döndürür.
	 *
	 * Option yazmaz; yalnızca okuma ve çözümleme yapar.
	 *
	 * @param string $legacy_line2 Bağlamdaki mevcut alt satır (ör. brand_line2, alt_metin).
	 * @return string
	 */
	public static function resolve_short_name( $legacy_line2 ) {
		$central = trim( self::get_short_name() );

		if ( '' !== $central ) {
			return $central;
		}

		return trim( (string) $legacy_line2 );
	}

	/**
	 * Shell yedek üst satır.
	 *
	 * @return string
	 */
	public static function get_fallback_name() {
		return self::FALLBACK_NAME;
	}

	/**
	 * Shell yedek alt satır.
	 *
	 * @return string
	 */
	public static function get_fallback_sub_name() {
		return self::FALLBACK_SUB;
	}

	/**
	 * Logo için erişilebilir alternatif metin.
	 *
	 * @return string
	 */
	public static function get_logo_alt_text() {
		$name = self::get_name();

		if ( '' !== $name ) {
			return $name;
		}

		if ( function_exists( 'get_bloginfo' ) ) {
			$site = (string) get_bloginfo( 'name' );
			if ( '' !== $site ) {
				return $site;
			}
		}

		return self::get_fallback_name();
	}

	/**
	 * Premium Admin Shell marka bloğunu basar.
	 *
	 * @return void
	 */
	public static function render_shell_brand() {
		$logo_id = self::get_logo_id();
		$name    = self::get_name();
		$sub     = self::get_short_name();
		$has_logo = $logo_id > 0 && '' !== self::get_logo_url( 'thumbnail' );

		if ( $has_logo ) {
			$url = self::get_logo_url( 'medium' );
			?>
			<div class="qrms-shell__brand qrms-shell__brand--has-logo">
				<div class="qrms-shell__brand-logo">
					<img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( self::get_logo_alt_text() ); ?>" width="36" height="36" decoding="async" />
				</div>
				<?php if ( '' !== $name || '' !== $sub ) : ?>
					<span class="qrms-shell__brand-text">
						<?php if ( '' !== $name ) : ?>
							<span class="qrms-shell__brand-name"><?php echo esc_html( $name ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $sub ) : ?>
							<span class="qrms-shell__brand-sub"><?php echo esc_html( $sub ); ?></span>
						<?php endif; ?>
					</span>
				<?php endif; ?>
			</div>
			<?php
			return;
		}

		$has_name = '' !== $name;
		$has_sub  = '' !== $sub;

		if ( ! $has_name && ! $has_sub ) {
			$name = self::get_fallback_name();
			$sub  = self::get_fallback_sub_name();
			$has_name = true;
			$has_sub  = true;
		}
		?>
		<div class="qrms-shell__brand">
			<span class="qrms-shell__brand-mark" aria-hidden="true"></span>
			<span class="qrms-shell__brand-text">
				<?php if ( $has_name ) : ?>
					<span class="qrms-shell__brand-name"><?php echo esc_html( $name ); ?></span>
				<?php endif; ?>
				<?php if ( $has_sub ) : ?>
					<span class="qrms-shell__brand-sub"><?php echo esc_html( $sub ); ?></span>
				<?php endif; ?>
			</span>
		</div>
		<?php
	}

	/**
	 * Restoran Markası ayar sekmesinin varlıkları.
	 *
	 * @param string $hook Ekran kancası.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook = '' ) {
		unset( $hook );

		if ( ! self::is_settings_tab() ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'qrms-brand-admin',
			QRMS_PLUGIN_URL . 'assets/css/brand-admin.css',
			array( 'qrms-admin' ),
			QRMS_Helpers::asset_version( 'assets/css/brand-admin.css' )
		);

		wp_enqueue_script(
			'qrms-brand-admin',
			QRMS_PLUGIN_URL . 'assets/js/brand-admin.js',
			array( 'jquery' ),
			QRMS_Helpers::asset_version( 'assets/js/brand-admin.js' ),
			true
		);

		wp_localize_script(
			'qrms-brand-admin',
			'QRMS_BRAND_ADMIN',
			array(
				'sec'    => __( 'Logo Seç', 'qrms' ),
				'kullan' => __( 'Bu görseli kullan', 'qrms' ),
			)
		);
	}

	/**
	 * Şu an Restoran Markası sekmesinde miyiz?
	 *
	 * @return bool
	 */
	public static function is_settings_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		return QRMS_Admin::SETTINGS_SLUG === $page && 'marka' === $tab;
	}

	/**
	 * Ayar formunu işler.
	 *
	 * @return void
	 */
	public static function handle_settings_submit() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'qrms' ) );
		}

		check_admin_referer( self::NONCE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce yukarıda doğrulandı.
		$ham = isset( $_POST['qrms_marka'] ) && is_array( $_POST['qrms_marka'] )
			? wp_unslash( $_POST['qrms_marka'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_settings() içinde temizlenir.
			: array();

		$yeni = self::sanitize_settings( $ham );

		update_option( self::OPTION, $yeni );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => QRMS_Admin::SETTINGS_SLUG,
					'tab'        => 'marka',
					'kaydedildi' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Ayar sekmesi gövdesi.
	 *
	 * @return void
	 */
	public static function render_settings_tab() {
		require_once QRMS_PLUGIN_DIR . 'includes/brand-ayar-sayfasi.php';

		qrms_marka_ayar_sayfasi();
	}
}
