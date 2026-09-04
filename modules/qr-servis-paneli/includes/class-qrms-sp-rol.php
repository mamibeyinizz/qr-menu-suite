<?php
/**
 * Servis Paneli rolü ve yeteneği.
 *
 * Garson ve mutfak personelinin yönetim paneline girmesi gerekiyor ama
 * menüyü, ürünleri ya da ayarları görmemeli. Bunun için kendi yeteneği olan
 * dar bir rol tanımlanır ve o rolle giren kullanıcıya panelden başka hiçbir
 * ekran gösterilmez.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rol ve yetenek yönetimi.
 */
class QRMS_SP_Rol {

	/** Panel yeteneği. */
	const YETENEK = 'qrms_servis_panel';

	/** Rol anahtarı. */
	const ROL = 'qrms_servis';

	/** Rolün sürümü — tanım değişirse artırılır. */
	const SURUM = 1;

	/** Sürümün saklandığı option. */
	const OPTION_SURUM = 'qrms_sp_rol_surum';

	/**
	 * Rolü ve yeteneği kurar.
	 *
	 * Her istekte add_role() çağırmak boşuna yazma demektir; sürüm option'ı
	 * değişmediği sürece hiçbir şey yapılmaz.
	 *
	 * @return void
	 */
	public static function kur() {
		if ( (int) get_option( self::OPTION_SURUM, 0 ) === self::SURUM ) {
			return;
		}

		remove_role( self::ROL );

		add_role(
			self::ROL,
			__( 'Servis Personeli', 'qrms' ),
			array(
				'read'        => true,
				self::YETENEK => true,
			)
		);

		foreach ( array( 'administrator', 'editor' ) as $rol_adi ) {
			$rol = get_role( $rol_adi );

			if ( $rol ) {
				$rol->add_cap( self::YETENEK );
			}
		}

		update_option( self::OPTION_SURUM, self::SURUM, false );
	}

	/**
	 * Kullanıcı YALNIZCA servis personeli mi?
	 *
	 * Yöneticiler de panele girer ama menüleri kısıtlanmaz.
	 *
	 * @return bool
	 */
	public static function yalniz_servis_mi() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( current_user_can( QRMS_Admin::CAPABILITY ) ) {
			return false;
		}

		return current_user_can( self::YETENEK );
	}

	/**
	 * Servis personelinin yönetim panelini sadeleştirir.
	 *
	 * @return void
	 */
	public static function paneli_sadelestir() {
		if ( ! self::yalniz_servis_mi() ) {
			return;
		}

		add_action( 'admin_menu', array( __CLASS__, 'menuyu_temizle' ), 999 );
		add_action( 'admin_init', array( __CLASS__, 'yonlendir' ) );
		add_action( 'wp_before_admin_bar_render', array( __CLASS__, 'arac_cubugu' ) );
		add_filter( 'show_admin_bar', '__return_true' );
	}

	/**
	 * Panel dışındaki bütün menü satırlarını kaldırır.
	 *
	 * @return void
	 */
	public static function menuyu_temizle() {
		global $menu;

		if ( ! is_array( $menu ) ) {
			return;
		}

		foreach ( $menu as $satir ) {
			if ( empty( $satir[2] ) || QRMS_Admin::MENU_SLUG === $satir[2] ) {
				continue;
			}

			remove_menu_page( $satir[2] );
		}

		// QR Menü menüsünün altında yalnızca panel kalsın.
		global $submenu;

		if ( isset( $submenu[ QRMS_Admin::MENU_SLUG ] ) && is_array( $submenu[ QRMS_Admin::MENU_SLUG ] ) ) {
			foreach ( $submenu[ QRMS_Admin::MENU_SLUG ] as $satir ) {
				if ( isset( $satir[2] ) && QRMS_SP_PANEL_SAYFA !== $satir[2] ) {
					remove_submenu_page( QRMS_Admin::MENU_SLUG, $satir[2] );
				}
			}
		}
	}

	/**
	 * Başka bir yönetim ekranına gitmeye çalışan servis personelini panele
	 * geri gönderir.
	 *
	 * @return void
	 */
	public static function yonlendir() {
		if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}

		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'admin.php' === $pagenow && QRMS_SP_PANEL_SAYFA === $page ) {
			return;
		}

		// Profil ekranı serbest: personel kendi şifresini değiştirebilmeli.
		if ( in_array( $pagenow, array( 'profile.php', 'admin-post.php' ), true ) ) {
			return;
		}

		wp_safe_redirect( add_query_arg( 'page', QRMS_SP_PANEL_SAYFA, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Araç çubuğundan gereksiz satırları düşürür.
	 *
	 * @return void
	 */
	public static function arac_cubugu() {
		global $wp_admin_bar;

		if ( ! is_object( $wp_admin_bar ) ) {
			return;
		}

		foreach ( array( 'wp-logo', 'comments', 'new-content', 'updates' ) as $dugum ) {
			$wp_admin_bar->remove_node( $dugum );
		}
	}
}
