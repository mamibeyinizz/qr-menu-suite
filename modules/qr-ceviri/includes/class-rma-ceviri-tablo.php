<?php
/**
 * Çeviri tablosu — şema, migration ve veri erişimi.
 *
 * Tablo adı ve benzersiz anahtar bir kere yerleştikten sonra DEĞİŞTİRİLMEMELİ:
 * import/export dosyaları bu şemaya göre üretiliyor.
 *
 * Şemada spec'e ek olarak `original_text` sütunu var. Çıktı üzerinde metin
 * değiştirme yapabilmek için "orijinal metin → çeviri" eşlemesi gerekiyor;
 * `original_hash` tek yönlü olduğu için bunu veremez. Alternatifi her sayfa
 * yüklemesinde kaynakları (özellikle _elementor_data'yı) yeniden taramaktı —
 * çok pahalı.
 *
 * @package QRMenu_Ceviri
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'RMA_Ceviri_Tablo' ) ) {

	/**
	 * {prefix}rma_translations tablosu.
	 */
	class RMA_Ceviri_Tablo {

		/**
		 * Tam tablo adı.
		 *
		 * @return string
		 */
		public static function tablo() {
			global $wpdb;
			return $wpdb->prefix . 'rma_translations';
		}

		/**
		 * Tabloyu oluştur/tazele. Aktivasyonda ve sürüm değişiminde çalışır.
		 */
		public static function tablo_kur() {
			global $wpdb;

			$tablo   = self::tablo();
			$collate = $wpdb->get_charset_collate();

			// field 191: utf8mb4'te indekslenebilir en uzun VARCHAR. Elementor
			// alan anahtarları ({widget_id}.{repeater}.{item_id}.{key}) uzun olabiliyor.
			$sql = "CREATE TABLE {$tablo} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				item_id bigint(20) unsigned NOT NULL DEFAULT 0,
				item_type varchar(20) NOT NULL,
				field varchar(191) NOT NULL,
				lang_code varchar(10) NOT NULL,
				original_text longtext NOT NULL,
				translated_text longtext NOT NULL,
				original_hash varchar(32) NOT NULL DEFAULT '',
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY item_field_lang (item_id, item_type, field, lang_code),
				KEY lang_lookup (lang_code, item_type)
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
		 * Tek satır yaz/güncelle.
		 *
		 * Benzersiz anahtar üzerinden ON DUPLICATE KEY UPDATE kullanılıyor:
		 * REPLACE INTO satırı silip yeniden eklediği için id'yi değiştirirdi.
		 *
		 * @param int    $item_id     Post/term ID; sabit metinlerde 0.
		 * @param string $item_type   product|category|allergen|nav_menu|ui_string|elementor|modül tipi.
		 * @param string $field       Alan anahtarı.
		 * @param string $lang        Hedef dil kodu ('tr' kabul edilmez).
		 * @param string $orijinal    Orijinal (TR) metin.
		 * @param string $ceviri      Çevrilmiş metin.
		 * @return bool Yazıldıysa true.
		 */
		public static function upsert( $item_id, $item_type, $field, $lang, $orijinal, $ceviri ) {
			global $wpdb;

			if ( 'tr' === $lang || '' === $lang || '' === $field ) {
				return false;
			}

			$tablo = self::tablo();

			$sonuc = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$tablo}
						(item_id, item_type, field, lang_code, original_text, translated_text, original_hash, updated_at)
					VALUES (%d, %s, %s, %s, %s, %s, %s, %s)
					ON DUPLICATE KEY UPDATE
						original_text   = VALUES(original_text),
						translated_text = VALUES(translated_text),
						original_hash   = VALUES(original_hash),
						updated_at      = VALUES(updated_at)",
					$item_id,
					$item_type,
					$field,
					$lang,
					$orijinal,
					$ceviri,
					rma_ceviri_hash_olustur( $orijinal, $field ),
					current_time( 'mysql' )
				)
			);

			return false !== $sonuc;
		}

		/**
		 * Tek çeviriyi sil ("boş hücreleri temizle" seçeneği için).
		 *
		 * @param int    $item_id   Öğe ID.
		 * @param string $item_type Öğe tipi.
		 * @param string $field     Alan anahtarı.
		 * @param string $lang      Dil kodu.
		 * @return int Silinen satır sayısı.
		 */
		public static function sil( $item_id, $item_type, $field, $lang ) {
			global $wpdb;

			return (int) $wpdb->delete(
				self::tablo(),
				array(
					'item_id'   => $item_id,
					'item_type' => $item_type,
					'field'     => $field,
					'lang_code' => $lang,
				),
				array( '%d', '%s', '%s', '%s' )
			);
		}

		/**
		 * Bir dilin TÜM çevirileri — sayfa başına tek sorgu (N+1 yok).
		 *
		 * @param string $lang Dil kodu.
		 * @return array<int,object> id, item_id, item_type, field, original_text, translated_text.
		 */
		public static function dil_satirlari( $lang ) {
			global $wpdb;

			if ( 'tr' === $lang || '' === $lang || ! self::tablo_var_mi() ) {
				return array();
			}

			$tablo = self::tablo();

			return (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT item_id, item_type, field, original_text, translated_text, original_hash
					 FROM {$tablo}
					 WHERE lang_code = %s",
					$lang
				)
			);
		}

		/**
		 * Export için mevcut çeviriler.
		 *
		 * @return array<string,array<string,string>> "tip|id|alan" => [ dil => çeviri ].
		 */
		public static function export_haritasi() {
			global $wpdb;

			if ( ! self::tablo_var_mi() ) {
				return array();
			}

			$tablo   = self::tablo();
			$satirlar = (array) $wpdb->get_results(
				"SELECT item_id, item_type, field, lang_code, translated_text FROM {$tablo}"
			);

			$harita = array();
			foreach ( $satirlar as $s ) {
				$anahtar = rma_ceviri_anahtar( $s->item_type, $s->item_id, $s->field );
				if ( ! isset( $harita[ $anahtar ] ) ) {
					$harita[ $anahtar ] = array();
				}
				$harita[ $anahtar ][ $s->lang_code ] = $s->translated_text;
			}

			return $harita;
		}

		/**
		 * Tip + dil bazlı satır sayıları.
		 *
		 * "İçe aktardım ama menü çevrilmiyor" durumunda ilk bakılacak yer:
		 * item_type=product satırları o dil için gerçekten yazılmış mı?
		 *
		 * @return array<string,array<string,int>> tip => [ dil => adet ].
		 */
		public static function tip_dil_sayilari() {
			global $wpdb;

			if ( ! self::tablo_var_mi() ) {
				return array();
			}

			$tablo    = self::tablo();
			$satirlar = (array) $wpdb->get_results(
				"SELECT item_type, lang_code, COUNT(*) AS adet
				 FROM {$tablo}
				 GROUP BY item_type, lang_code"
			);

			$sonuc = array();
			foreach ( $satirlar as $s ) {
				$sonuc[ $s->item_type ][ $s->lang_code ] = (int) $s->adet;
			}

			return $sonuc;
		}

		/**
		 * Dil bazlı satır sayıları — admin özetinde gösterilir.
		 *
		 * @return array<string,int> dil => satır sayısı.
		 */
		public static function dil_sayilari() {
			global $wpdb;

			if ( ! self::tablo_var_mi() ) {
				return array();
			}

			$tablo    = self::tablo();
			$satirlar = (array) $wpdb->get_results(
				"SELECT lang_code, COUNT(*) AS adet FROM {$tablo} GROUP BY lang_code"
			);

			$sonuc = array();
			foreach ( $satirlar as $s ) {
				$sonuc[ $s->lang_code ] = (int) $s->adet;
			}

			return $sonuc;
		}

		/**
		 * Bir tipin çeviri tablosundaki distinct item_id listesi (0 hariç).
		 *
		 * @param string $tip item_type.
		 * @return int[]
		 */
		public static function tip_item_idleri( $tip ) {
			global $wpdb;

			if ( ! self::tablo_var_mi() ) {
				return array();
			}

			$tablo = self::tablo();

			return array_map(
				'intval',
				(array) $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT item_id FROM {$tablo} WHERE item_type = %s AND item_id > 0",
						$tip
					)
				)
			);
		}

		/**
		 * Tip + ID listesindeki satır sayısı.
		 *
		 * @param string $tip   item_type.
		 * @param int[]  $idler item_id.
		 * @return int
		 */
		public static function tip_id_satir_sayisi( $tip, $idler ) {
			global $wpdb;

			$idler = array_values( array_unique( array_map( 'intval', (array) $idler ) ) );
			if ( empty( $idler ) || ! self::tablo_var_mi() ) {
				return 0;
			}

			$tablo = self::tablo();
			$in    = implode( ',', $idler );

			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$tablo} WHERE item_type = %s AND item_id IN ({$in})",
					$tip
				)
			);
		}

		/**
		 * Tip + ID listesini sil.
		 *
		 * @param string $tip   item_type.
		 * @param int[]  $idler item_id.
		 * @return int Silinen satır.
		 */
		public static function tip_idleri_sil( $tip, $idler ) {
			global $wpdb;

			$idler = array_values( array_unique( array_map( 'intval', (array) $idler ) ) );
			if ( empty( $idler ) || ! self::tablo_var_mi() ) {
				return 0;
			}

			$tablo = self::tablo();
			$in    = implode( ',', $idler );

			return (int) $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$tablo} WHERE item_type = %s AND item_id IN ({$in})",
					$tip
				)
			);
		}

		/**
		 * Bir alanın kaç farklı dilde çevirisi var.
		 *
		 * @param string $tip     item_type.
		 * @param int    $item_id ID.
		 * @param string $field   Alan; boşsa o ID'nin tüm alanları.
		 * @return int
		 */
		public static function alan_dil_sayisi( $tip, $item_id, $field = '' ) {
			global $wpdb;

			if ( ! self::tablo_var_mi() ) {
				return 0;
			}

			$tablo = self::tablo();

			if ( '' === $field ) {
				return (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(DISTINCT lang_code) FROM {$tablo} WHERE item_type = %s AND item_id = %d",
						$tip,
						$item_id
					)
				);
			}

			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT lang_code) FROM {$tablo} WHERE item_type = %s AND item_id = %d AND field = %s",
					$tip,
					$item_id,
					$field
				)
			);
		}

		/**
		 * Birden çok option field için distinct dil sayısı.
		 *
		 * @param string[] $fieldler field listesi.
		 * @return int
		 */
		public static function option_alan_dil_sayisi( $fieldler ) {
			global $wpdb;

			$fieldler = array_values( array_filter( array_map( 'strval', (array) $fieldler ) ) );
			if ( empty( $fieldler ) || ! self::tablo_var_mi() ) {
				return 0;
			}

			$tablo = self::tablo();
			$yer   = implode( ',', array_fill( 0, count( $fieldler ), '%s' ) );
			$sql   = "SELECT COUNT(DISTINCT lang_code) FROM {$tablo} WHERE item_type = %s AND item_id = 0 AND field IN ({$yer})";
			$args  = array_merge( array( $sql, 'option' ), $fieldler );

			return (int) $wpdb->get_var( call_user_func_array( array( $wpdb, 'prepare' ), $args ) );
		}

		/**
		 * Hash kapısı için tip satırları (distinct item_id+field+hash).
		 *
		 * @param string $tip item_type.
		 * @return array<int,object>
		 */
		public static function tip_hash_satirlari( $tip ) {
			global $wpdb;

			if ( ! self::tablo_var_mi() ) {
				return array();
			}

			$tablo = self::tablo();

			return (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT item_id, field, original_hash FROM {$tablo} WHERE item_type = %s GROUP BY item_id, field, original_hash",
					$tip
				)
			);
		}
	}
}
