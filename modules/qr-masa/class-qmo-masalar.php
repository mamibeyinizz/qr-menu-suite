<?php
/**
 * QMO Masalar — masa kayıtları (CRUD) ve QR kod üretimi.
 *
 * Eski "QR Menü Masa Yöneticisi" (qrkod.php) eklentisinin yerini alır.
 * Tablo adı ve şeması aynı bırakıldı: {prefix}qrm_tables — canlı sitelerde
 * kayıtlı masalar korunur.
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QMO_Masalar' ) ) {

	/**
	 * Masa kayıtları.
	 */
	class QMO_Masalar {

		/**
		 * Tam tablo adı.
		 *
		 * @return string
		 */
		public static function tablo() {
			global $wpdb;
			return $wpdb->prefix . 'qrm_tables';
		}

		/**
		 * Tabloyu oluştur/kontrol et. Aktivasyonda ve sürüm değişiminde çalışır.
		 */
		public static function tablo_kur() {
			global $wpdb;

			$tablo   = self::tablo();
			$collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE {$tablo} (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				table_name varchar(100) NOT NULL,
				table_slug varchar(100) NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY table_slug (table_slug)
			) {$collate};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		/**
		 * Tablo veritabanında var mı?
		 *
		 * @return bool
		 */
		public static function tablo_var_mi() {
			global $wpdb;
			$tablo = self::tablo();
			return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tablo ) );
		}

		/**
		 * Tüm masalar (en yeni önce).
		 *
		 * @return array
		 */
		public static function hepsi() {
			global $wpdb;
			$tablo = self::tablo();
			return $wpdb->get_results( "SELECT id, table_name, table_slug, created_at FROM {$tablo} ORDER BY id DESC" );
		}

		/**
		 * Slug'a göre masa getir.
		 *
		 * @param string $slug Masa slug'ı.
		 * @return object|null
		 */
		public static function bul( $slug ) {
			global $wpdb;
			$tablo = self::tablo();
			$slug  = sanitize_title( $slug );
			if ( '' === $slug ) {
				return null;
			}
			return $wpdb->get_row(
				$wpdb->prepare( "SELECT id, table_name, table_slug FROM {$tablo} WHERE table_slug = %s", $slug )
			);
		}

		/**
		 * Yeni masa ekle.
		 *
		 * @param string $ad Masa adı (ör. "Masa 31").
		 * @return true|WP_Error
		 */
		public static function ekle( $ad ) {
			global $wpdb;

			$ad = sanitize_text_field( $ad );
			if ( '' === $ad ) {
				return new WP_Error( 'bos', 'Masa adı boş olamaz.' );
			}

			// Slug STRING'dir: "Masa 31" → "masa-31", "VIP Salon" → "vip-salon".
			$slug = sanitize_title( $ad );
			if ( '' === $slug ) {
				return new WP_Error( 'slug', 'Bu masa adından geçerli bir adres üretilemedi.' );
			}

			$tablo   = self::tablo();
			$mevcut  = $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$tablo} WHERE table_slug = %s", $slug )
			);
			if ( (int) $mevcut > 0 ) {
				return new WP_Error( 'mevcut', 'Bu masa zaten mevcut.' );
			}

			$ok = $wpdb->insert(
				$tablo,
				array(
					'table_name' => $ad,
					'table_slug' => $slug,
				),
				array( '%s', '%s' )
			);
			if ( ! $ok ) {
				return new WP_Error( 'db', 'Masa kaydedilemedi.' );
			}

			qmo_masa_cache_temizle( $slug );
			return true;
		}

		/**
		 * Masayı sil. Silinen masanın açık oturumları da geçersizleşir.
		 *
		 * @param int $id Masa ID'si.
		 * @return true|WP_Error
		 */
		public static function sil( $id ) {
			global $wpdb;

			$id = (int) $id;
			if ( $id <= 0 ) {
				return new WP_Error( 'id', 'Geçersiz masa.' );
			}

			$tablo = self::tablo();
			$slug  = $wpdb->get_var( $wpdb->prepare( "SELECT table_slug FROM {$tablo} WHERE id = %d", $id ) );
			if ( null === $slug ) {
				return new WP_Error( 'yok', 'Masa bulunamadı.' );
			}

			$wpdb->delete( $tablo, array( 'id' => $id ), array( '%d' ) );

			// Masadaki müşterilerin token'ları anında geçersiz olsun.
			QMO_Oturum::masayi_kapat( $slug );
			qmo_masa_cache_temizle( $slug );

			return true;
		}

		/**
		 * Masanın QR hedef adresi: https://site/?masa=masa-31
		 *
		 * @param string $slug Masa slug'ı.
		 * @return string
		 */
		public static function url( $slug ) {
			return add_query_arg( 'masa', rawurlencode( $slug ), trailingslashit( home_url() ) );
		}
	}
}

/**
 * Kısa kod: [qr_aktif_masa] — müşterinin hangi masada olduğunu gösterir.
 * Masa bilgisi doğrulanmış oturumdan okunur; URL'den değil.
 */
add_shortcode( 'qr_aktif_masa', 'qmo_aktif_masa_shortcode' );
if ( ! function_exists( 'qmo_aktif_masa_shortcode' ) ) {
	function qmo_aktif_masa_shortcode() {
		$sess = qmo_oturum();
		if ( ! $sess ) {
			return '';
		}
		$masa = QMO_Masalar::bul( $sess['masa'] );
		$ad   = $masa ? $masa->table_name : str_replace( '-', ' ', $sess['masa'] );
		return esc_html( $ad );
	}
}
