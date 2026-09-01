<?php
/**
 * QR Chatbot — sohbet geçmişi ve cevaplanamayan soru tabloları.
 *
 * Kurulum dbDelta ile yapılır. Sorgular indexed alanlara yazılır
 * (created_at, masa_no, oturum_id, tekrar, resolved).
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tablo kurulumunu ve kayıt işlemlerini yönetir.
 */
class QMO_Chatbot_DB {

	const SURUM = '1.0';
	const OPT   = 'qmo_chatbot_db_surum';

	/**
	 * Sürüm eşleşmiyorsa şemayı kurar.
	 *
	 * @return void
	 */
	public static function sema_kontrol() {
		if ( self::SURUM === get_option( self::OPT ) ) {
			return;
		}
		self::tablolari_kur();
		update_option( self::OPT, self::SURUM, false );
	}

	/**
	 * Sohbet geçmişi tablosu.
	 *
	 * @return string
	 */
	public static function mesaj_tablosu() {
		global $wpdb;
		return $wpdb->prefix . 'qmo_chatbot_mesajlar';
	}

	/**
	 * Cevaplanamayan sorular tablosu.
	 *
	 * @return string
	 */
	public static function bilinmeyen_tablosu() {
		global $wpdb;
		return $wpdb->prefix . 'qmo_chatbot_bilinmeyen';
	}

	/**
	 * Tabloları dbDelta ile oluşturur.
	 *
	 * @return void
	 */
	public static function tablolari_kur() {
		global $wpdb;

		$collate = $wpdb->get_charset_collate();
		$mesaj   = self::mesaj_tablosu();
		$bilin   = self::bilinmeyen_tablosu();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta(
			"CREATE TABLE {$mesaj} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				oturum_id varchar(64) NOT NULL DEFAULT '',
				masa_no varchar(64) NOT NULL DEFAULT '',
				soru text NOT NULL,
				cevap text NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY idx_created (created_at),
				KEY idx_masa_created (masa_no, created_at),
				KEY idx_oturum (oturum_id)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$bilin} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				soru varchar(255) NOT NULL DEFAULT '',
				soru_norm varchar(191) NOT NULL DEFAULT '',
				tekrar int(11) NOT NULL DEFAULT 1,
				resolved tinyint(1) NOT NULL DEFAULT 0,
				first_seen datetime NOT NULL,
				last_seen datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY idx_soru_norm (soru_norm),
				KEY idx_resolved_tekrar (resolved, tekrar)
			) {$collate};"
		);
	}

	/**
	 * Bir soru-cevap çiftini kaydeder.
	 *
	 * @param string $oturum_id Oturum anahtarı.
	 * @param string $masa_no   Masa.
	 * @param string $soru      Ziyaretçi sorusu.
	 * @param string $cevap     Bot cevabı.
	 * @return int Eklenen satır kimliği.
	 */
	public static function mesaj_yaz( $oturum_id, $masa_no, $soru, $cevap ) {
		global $wpdb;

		self::sema_kontrol();

		$wpdb->insert(
			self::mesaj_tablosu(),
			array(
				'oturum_id'  => substr( sanitize_text_field( $oturum_id ), 0, 64 ),
				'masa_no'    => substr( sanitize_text_field( $masa_no ), 0, 64 ),
				'soru'       => sanitize_textarea_field( $soru ),
				'cevap'      => sanitize_textarea_field( $cevap ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Cevaplanamayan soruyu sayaca ekler.
	 *
	 * @param string $soru Soru.
	 * @return void
	 */
	public static function bilinmeyen_yaz( $soru ) {
		global $wpdb;

		self::sema_kontrol();

		$soru = sanitize_text_field( $soru );
		$soru = function_exists( 'mb_substr' ) ? mb_substr( $soru, 0, 255 ) : substr( $soru, 0, 255 );
		$norm = self::soru_norm( $soru );
		if ( '' === $norm ) {
			return;
		}

		$tablo = self::bilinmeyen_tablosu();
		$simdi = current_time( 'mysql' );
		$var   = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, tekrar FROM {$tablo} WHERE soru_norm = %s", $norm )
		);

		if ( $var ) {
			$wpdb->update(
				$tablo,
				array(
					'tekrar'    => (int) $var->tekrar + 1,
					'last_seen' => $simdi,
					'resolved'  => 0,
				),
				array( 'id' => (int) $var->id ),
				array( '%d', '%s', '%d' ),
				array( '%d' )
			);
			return;
		}

		$wpdb->insert(
			$tablo,
			array(
				'soru'       => $soru,
				'soru_norm'  => $norm,
				'tekrar'     => 1,
				'resolved'   => 0,
				'first_seen' => $simdi,
				'last_seen'  => $simdi,
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Karşılaştırma için soruyu sadeleştir.
	 *
	 * @param string $soru Soru.
	 * @return string
	 */
	public static function soru_norm( $soru ) {
		$soru = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $soru ) ) : strtolower( trim( $soru ) );
		$soru = preg_replace( '/\s+/u', ' ', $soru );
		return function_exists( 'mb_substr' ) ? mb_substr( $soru, 0, 191 ) : substr( $soru, 0, 191 );
	}

	/**
	 * Geçmiş listesi.
	 *
	 * @param array $args Filtreler.
	 * @return array{satirlar:array,toplam:int}
	 */
	public static function mesaj_liste( $args = array() ) {
		global $wpdb;

		self::sema_kontrol();

		$args = wp_parse_args(
			$args,
			array(
				'baslangic' => '',
				'bitis'     => '',
				'masa'      => '',
				'arama'     => '',
				'sayfa'     => 1,
				'adet'      => 20,
			)
		);

		$tablo   = self::mesaj_tablosu();
		$where   = array( '1=1' );
		$degerler = array();

		if ( '' !== $args['baslangic'] ) {
			$where[]    = 'created_at >= %s';
			$degerler[] = $args['baslangic'] . ' 00:00:00';
		}
		if ( '' !== $args['bitis'] ) {
			$where[]    = 'created_at <= %s';
			$degerler[] = $args['bitis'] . ' 23:59:59';
		}
		if ( '' !== $args['masa'] ) {
			$where[]    = 'masa_no = %s';
			$degerler[] = $args['masa'];
		}
		if ( '' !== $args['arama'] ) {
			$where[]    = '(soru LIKE %s OR cevap LIKE %s)';
			$like       = '%' . $wpdb->esc_like( $args['arama'] ) . '%';
			$degerler[] = $like;
			$degerler[] = $like;
		}

		$sql_where = implode( ' AND ', $where );
		$adet      = max( 1, min( 100, (int) $args['adet'] ) );
		$sayfa     = max( 1, (int) $args['sayfa'] );
		$offset    = ( $sayfa - 1 ) * $adet;

		if ( $degerler ) {
			$toplam = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tablo} WHERE {$sql_where}", $degerler ) );
			$satirlar = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$tablo} WHERE {$sql_where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
					array_merge( $degerler, array( $adet, $offset ) )
				)
			);
		} else {
			$toplam   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tablo}" );
			$satirlar = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$tablo} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
					$adet,
					$offset
				)
			);
		}

		return array(
			'satirlar' => is_array( $satirlar ) ? $satirlar : array(),
			'toplam'   => $toplam,
		);
	}

	/**
	 * Bir oturumun tüm yazışması.
	 *
	 * @param string $oturum_id Oturum.
	 * @return array
	 */
	public static function oturum_yazismasi( $oturum_id ) {
		global $wpdb;

		self::sema_kontrol();

		$oturum_id = sanitize_text_field( $oturum_id );
		if ( '' === $oturum_id ) {
			return array();
		}

		$tablo = self::mesaj_tablosu();
		$satirlar = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$tablo} WHERE oturum_id = %s ORDER BY created_at ASC, id ASC",
				$oturum_id
			)
		);

		return is_array( $satirlar ) ? $satirlar : array();
	}

	/**
	 * Tek kayıt sil.
	 *
	 * @param int $id Kimlik.
	 * @return bool
	 */
	public static function mesaj_sil( $id ) {
		global $wpdb;
		self::sema_kontrol();
		return false !== $wpdb->delete( self::mesaj_tablosu(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * Toplu silme.
	 *
	 * @param int[] $ids Kimlikler.
	 * @return int
	 */
	public static function mesaj_toplu_sil( $ids ) {
		global $wpdb;
		self::sema_kontrol();

		$ids = array_filter( array_map( 'absint', (array) $ids ) );
		if ( empty( $ids ) ) {
			return 0;
		}

		$tablo  = self::mesaj_tablosu();
		$yerler = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- yerler yalnızca %d.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$tablo} WHERE id IN ({$yerler})", $ids ) );
	}

	/**
	 * X günden eski kayıtları sil.
	 *
	 * @param int $gun Gün.
	 * @return int
	 */
	public static function eski_sil( $gun ) {
		global $wpdb;
		self::sema_kontrol();

		$gun = absint( $gun );
		if ( $gun < 1 ) {
			return 0;
		}

		$tablo = self::mesaj_tablosu();
		$esik  = gmdate( 'Y-m-d H:i:s', time() - ( $gun * DAY_IN_SECONDS ) );

		return (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$tablo} WHERE created_at < %s", $esik )
		);
	}

	/**
	 * Cevaplanamayan soru listesi (çoktan aza).
	 *
	 * @param string $durum all|open|resolved.
	 * @return array
	 */
	public static function bilinmeyen_liste( $durum = 'open' ) {
		global $wpdb;
		self::sema_kontrol();

		$tablo = self::bilinmeyen_tablosu();
		$sql   = "SELECT * FROM {$tablo}";

		if ( 'open' === $durum ) {
			$sql .= ' WHERE resolved = 0';
		} elseif ( 'resolved' === $durum ) {
			$sql .= ' WHERE resolved = 1';
		}

		$sql .= ' ORDER BY tekrar DESC, last_seen DESC';

		$satirlar = $wpdb->get_results( $sql );
		return is_array( $satirlar ) ? $satirlar : array();
	}

	/**
	 * Çözüldü olarak işaretle.
	 *
	 * @param int $id Kimlik.
	 * @return bool
	 */
	public static function bilinmeyen_coz( $id ) {
		global $wpdb;
		self::sema_kontrol();

		return false !== $wpdb->update(
			self::bilinmeyen_tablosu(),
			array( 'resolved' => 1 ),
			array( 'id' => absint( $id ) ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Tek bilinmeyen satır.
	 *
	 * @param int $id Kimlik.
	 * @return object|null
	 */
	public static function bilinmeyen_al( $id ) {
		global $wpdb;
		self::sema_kontrol();

		$tablo = self::bilinmeyen_tablosu();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tablo} WHERE id = %d", absint( $id ) ) );
		return $row ? $row : null;
	}

	/**
	 * Kayıtlı masa numaraları (filtre kutusu).
	 *
	 * @return string[]
	 */
	public static function masa_listesi() {
		global $wpdb;
		self::sema_kontrol();

		$tablo = self::mesaj_tablosu();
		$liste = $wpdb->get_col( "SELECT DISTINCT masa_no FROM {$tablo} WHERE masa_no <> '' ORDER BY masa_no ASC" );
		return is_array( $liste ) ? $liste : array();
	}
}
