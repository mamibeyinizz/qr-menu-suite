<?php
/**
 * Servis Personeli rolü ve yetki yönetimi.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QRMS_SP_Rol' ) ) {

	/**
	 * Servis personeli rolü ve admin kısıtlamaları.
	 */
	class QRMS_SP_Rol {

		const CAPABILITY  = 'qrms_servis_panel';
		const ROLE        = 'qrms_servis';
		const OPT_SURUM   = 'qrms_sp_rol_surum';
		const ROL_SURUM   = 1;

		/**
		 * Rol ve yetenekleri kurar.
		 *
		 * @return void
		 */
		public static function init() {
			if ( (int) get_option( self::OPT_SURUM, 0 ) < self::ROL_SURUM ) {
				self::rol_ekle();
				update_option( self::OPT_SURUM, self::ROL_SURUM, false );
			}

			$admin = get_role( 'administrator' );
			if ( $admin && ! $admin->has_cap( self::CAPABILITY ) ) {
				$admin->add_cap( self::CAPABILITY );
			}
			$editor = get_role( 'editor' );
			if ( $editor && ! $editor->has_cap( self::CAPABILITY ) ) {
				$editor->add_cap( self::CAPABILITY );
			}

			if ( self::servis_kullanicisi_mi() ) {
				add_action( 'admin_menu', array( __CLASS__, 'menu_kisitla' ), 999 );
				add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar_kisitla' ), 999 );
				add_action( 'admin_init', array( __CLASS__, 'yonlendir' ) );
			}
		}

		/**
		 * Servis personeli rolünü ekler.
		 *
		 * @return void
		 */
		public static function rol_ekle() {
			if ( get_role( self::ROLE ) ) {
				return;
			}
			add_role(
				self::ROLE,
				__( 'Servis Personeli', 'qrms' ),
				array(
					'read'             => true,
					self::CAPABILITY   => true,
				)
			);
		}

		/**
		 * Oturum açmış kullanıcı servis personeli mi?
		 *
		 * @return bool
		 */
		public static function servis_kullanicisi_mi() {
			if ( ! is_user_logged_in() ) {
				return false;
			}
			$user = wp_get_current_user();
			return in_array( self::ROLE, (array) $user->roles, true );
		}

		/**
		 * Panel yetkisi var mı?
		 *
		 * @return bool
		 */
		public static function yetkili_mi() {
			return current_user_can( self::CAPABILITY );
		}

		/**
		 * Sol menüyü sadeleştirir.
		 *
		 * @return void
		 */
		public static function menu_kisitla() {
			global $menu, $submenu;

			$izin = array(
				QRMS_Admin::MENU_SLUG,
				QRMS_Admin::get_module_page_slug( 'qr-servis-paneli' ),
				'qrms-sp-ayarlar',
			);

			if ( is_array( $menu ) ) {
				foreach ( $menu as $key => $item ) {
					if ( ! in_array( $item[2], $izin, true ) ) {
						unset( $menu[ $key ] );
					}
				}
			}

			if ( is_array( $submenu ) ) {
				foreach ( $submenu as $parent => $items ) {
					if ( ! in_array( $parent, $izin, true ) ) {
						unset( $submenu[ $parent ] );
					}
				}
			}
		}

		/**
		 * Admin bar'ı sadeleştirir.
		 *
		 * @param WP_Admin_Bar $bar Admin bar.
		 * @return void
		 */
		public static function admin_bar_kisitla( $bar ) {
			if ( ! is_object( $bar ) ) {
				return;
			}
			$bar->remove_node( 'wp-logo' );
			$bar->remove_node( 'comments' );
			$bar->remove_node( 'new-content' );
		}

		/**
		 * Başka sayfalara gitmeyi engeller.
		 *
		 * @return void
		 */
		public static function yonlendir() {
			if ( wp_doing_ajax() ) {
				return;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
			$izin = array(
				QRMS_Admin::MENU_SLUG,
				QRMS_Admin::get_module_page_slug( 'qr-servis-paneli' ),
				'qrms-sp-ayarlar',
			);
			if ( '' !== $page && ! in_array( $page, $izin, true ) ) {
				wp_safe_redirect( QRMS_Admin::get_module_page_url( 'qr-servis-paneli' ) );
				exit;
			}
		}
	}
}
