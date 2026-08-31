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
		 * Kayıtlı masa sayısı.
		 *
		 * Genel Bakış analiz şeridi BURADAN beslenir; hepsi() bütün satırları
		 * çekmesin diye ayrı COUNT + istek içi statik önbellek.
		 *
		 * @return int
		 */
		public static function sayisi() {
			static $sayi = null;

			if ( null !== $sayi ) {
				return $sayi;
			}

			global $wpdb;
			$tablo = self::tablo();
			$sayi  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tablo}" );

			return $sayi;
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
		 * Toplu oluşturmada tek seferde açılabilecek azami masa sayısı.
		 *
		 * Her masa ayrı bir INSERT'tür; sınır, yanlışlıkla girilen büyük bir
		 * aralığın (1–99999) isteği zaman aşımına düşürmesini engeller.
		 */
		const TOPLU_AZAMI = 200;

		/**
		 * Toplu oluşturmada bir masanın slug'ı: "ic-masa" + 7 -> "ic-masa-7".
		 *
		 * Saf fonksiyon; testlerde doğrudan doğrulanır.
		 *
		 * @param string $onek Slug öneki (ham girdi).
		 * @param int    $no   Masa numarası.
		 * @return string Geçersiz önek/numara için boş string.
		 */
		public static function toplu_slug( $onek, $no ) {
			$onek = sanitize_title( (string) $onek );
			$no   = (int) $no;

			if ( '' === $onek || $no < 1 ) {
				return '';
			}

			return $onek . '-' . $no;
		}

		/**
		 * Toplu oluşturmada bir masanın GÖRÜNEN adı.
		 *
		 * Ad, önekin okunabilir hâlidir ("ic-masa" + 7 -> "Ic Masa 7"). Ama
		 * ekle() slug'ı ADDAN üretir; okunabilir ad sanitize_title'dan geçince
		 * beklenen slug'ı vermiyorsa (Türkçe karakter, üst üste tire vb.) ad
		 * doğrudan slug'a düşürülür — slug'ın doğruluğu görünüşten önemlidir.
		 *
		 * Saf fonksiyon; testlerde doğrudan doğrulanır.
		 *
		 * @param string $onek Slug öneki (ham girdi).
		 * @param int    $no   Masa numarası.
		 * @return string Geçersiz önek/numara için boş string.
		 */
		public static function toplu_ad( $onek, $no ) {
			$slug = self::toplu_slug( $onek, $no );

			if ( '' === $slug ) {
				return '';
			}

			$okunabilir = ucwords( str_replace( '-', ' ', sanitize_title( (string) $onek ) ) ) . ' ' . (int) $no;

			return sanitize_title( $okunabilir ) === $slug ? $okunabilir : $slug;
		}

		/**
		 * Bir masanın filtre grubu: sondaki "-<sayı>" eki atılmış slug.
		 *
		 * "ic-masa-12" -> "ic-masa", "vip-3" -> "vip", "bahce" -> "bahce".
		 * Masalar yönetim ekranındaki grup çipleri bununla üretilir.
		 *
		 * Saf fonksiyon; testlerde doğrudan doğrulanır.
		 *
		 * @param string $slug Masa slug'ı.
		 * @return string
		 */
		public static function grup_adi( $slug ) {
			$slug = (string) $slug;
			$grup = preg_replace( '/-\d+$/', '', $slug );

			// Slug'ın tamamı sayıysa ("12") geriye bir şey kalmaz; o zaman
			// masanın kendisi tek başına bir gruptur.
			return ( null === $grup || '' === $grup ) ? $slug : $grup;
		}

		/**
		 * Toplu oluşturma girdisini doğrular ve normalize eder.
		 *
		 * Veritabanına hiç dokunmaz, bu yüzden doğrudan test edilir; sınır
		 * kontrolleri döngü başlamadan burada biter.
		 *
		 * @param string $onek Slug öneki (ham girdi).
		 * @param int    $bas  Başlangıç numarası.
		 * @param int    $bit  Bitiş numarası.
		 * @return array{onek:string,bas:int,bit:int,adet:int}|WP_Error
		 */
		public static function toplu_aralik_dogrula( $onek, $bas, $bit ) {
			$onek = sanitize_title( (string) $onek );
			$bas  = (int) $bas;
			$bit  = (int) $bit;

			if ( '' === $onek ) {
				return new WP_Error( 'onek', 'Geçerli bir slug öneki girin (ör. ic-masa).' );
			}

			if ( $bas < 1 || $bit < 1 ) {
				return new WP_Error( 'aralik', 'Başlangıç ve bitiş numarası 1 veya daha büyük olmalı.' );
			}

			if ( $bit < $bas ) {
				return new WP_Error( 'aralik', 'Bitiş numarası başlangıçtan küçük olamaz.' );
			}

			$adet = ( $bit - $bas ) + 1;

			if ( $adet > self::TOPLU_AZAMI ) {
				return new WP_Error(
					'sinir',
					sprintf( 'Tek seferde en fazla %d masa oluşturulabilir; aralığı daraltın.', self::TOPLU_AZAMI )
				);
			}

			return array(
				'onek' => $onek,
				'bas'  => $bas,
				'bit'  => $bit,
				'adet' => $adet,
			);
		}

		/**
		 * Numaralı bir aralıkta toplu masa oluşturur.
		 *
		 * Zaten var olan slug'lar ATLANIR (hata değildir): kullanıcı aralığı
		 * genişletip yeniden gönderdiğinde yalnızca eksikler eklenir.
		 *
		 * @param string $onek Slug öneki (ör. "ic-masa").
		 * @param int    $bas  Başlangıç numarası.
		 * @param int    $bit  Bitiş numarası.
		 * @return array{eklenen:int,atlanan:string[],hata:string[]}|WP_Error
		 */
		public static function toplu_ekle( $onek, $bas, $bit ) {
			$aralik = self::toplu_aralik_dogrula( $onek, $bas, $bit );

			if ( is_wp_error( $aralik ) ) {
				return $aralik;
			}

			list( $onek, $bas, $bit ) = array( $aralik['onek'], $aralik['bas'], $aralik['bit'] );

			$eklenen = 0;
			$atlanan = array();
			$hata    = array();

			for ( $no = $bas; $no <= $bit; $no++ ) {
				$ad    = self::toplu_ad( $onek, $no );
				$sonuc = self::ekle( $ad );

				if ( true === $sonuc ) {
					$eklenen++;
					continue;
				}

				$slug = self::toplu_slug( $onek, $no );

				if ( is_wp_error( $sonuc ) && 'mevcut' === $sonuc->get_error_code() ) {
					$atlanan[] = $slug;
					continue;
				}

				$hata[] = $slug;
			}

			return array(
				'eklenen' => $eklenen,
				'atlanan' => $atlanan,
				'hata'    => $hata,
			);
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
