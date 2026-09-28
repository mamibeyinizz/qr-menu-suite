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

	const SURUM = '1.3.2';
	const OPT   = 'qmo_chatbot_db_surum';

	/** Append-only recommendation attribution olayları. */
	const REC_EVENT_SHOWN    = 'shown';
	const REC_EVENT_CART_ADD = 'cart_add';

	/**
	 * Öneri raporunda legacy gösterildi ile events `shown` kaynağının sınır günü (Y-m-d).
	 * PR #268 merge kanıtı: 2026-09-26; cutover gününde rapor yalnızca events `shown` kullanır.
	 */
	const RECOMMENDATION_REPORT_CUTOVER_DATE = '2026-09-26';

	/** recommendation_events shown/cart_add saklama (qrms_analitik_temizlik entegrasyonu). */
	const RECOMMENDATION_EVENTS_SAKLAMA_GUN = 90;

	const RECOMMENDATION_EVENTS_SAKLAMA_PARCA = 5000;

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
	 * Öneri kural tablosu.
	 *
	 * @return string
	 */
	public static function oneri_kural_tablosu() {
		global $wpdb;
		return $wpdb->prefix . 'qmo_chatbot_oneri_kural';
	}

	/**
	 * Öneri log tablosu.
	 *
	 * @return string
	 */
	public static function oneri_log_tablosu() {
		global $wpdb;
		return $wpdb->prefix . 'qmo_chatbot_oneri_log';
	}

	/**
	 * Phase 4 — recommendation attribution olay tablosu.
	 *
	 * @return string
	 */
	public static function recommendation_events_tablosu() {
		global $wpdb;
		return $wpdb->prefix . 'qmo_chatbot_recommendation_events';
	}

	/**
	 * Canlı sohbet (eskalasyon) takip tablosu.
	 *
	 * @return string
	 */
	public static function canli_tablosu() {
		global $wpdb;
		return $wpdb->prefix . 'qmo_chatbot_canli';
	}

	/**
	 * Personel → müşteri mesaj tablosu.
	 *
	 * @return string
	 */
	public static function personel_mesaj_tablosu() {
		global $wpdb;
		return $wpdb->prefix . 'qmo_chatbot_personel_mesaj';
	}

	/**
	 * Tabloları dbDelta ile oluşturur.
	 *
	 * @return void
	 */
	public static function tablolari_kur() {
		global $wpdb;

		$collate  = $wpdb->get_charset_collate();
		$mesaj    = self::mesaj_tablosu();
		$bilin    = self::bilinmeyen_tablosu();
		$kural    = self::oneri_kural_tablosu();
		$log      = self::oneri_log_tablosu();
		$rec      = self::recommendation_events_tablosu();
		$canli    = self::canli_tablosu();
		$personel = self::personel_mesaj_tablosu();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		self::recommendation_events_dedupe_yinelenen();

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

		dbDelta(
			"CREATE TABLE {$kural} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				kaynak_urun bigint(20) unsigned NOT NULL,
				hedef_urun bigint(20) unsigned NOT NULL,
				agirlik smallint(6) NOT NULL DEFAULT 50,
				aktif tinyint(1) NOT NULL DEFAULT 1,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY kaynak (kaynak_urun),
				KEY hedef (hedef_urun),
				UNIQUE KEY cift (kaynak_urun, hedef_urun)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$log} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				oturum_id varchar(64) NOT NULL DEFAULT '',
				masa_no varchar(32) NOT NULL DEFAULT '',
				urun_id bigint(20) unsigned NOT NULL,
				kaynak varchar(20) NOT NULL DEFAULT 'ai',
				durum varchar(20) NOT NULL DEFAULT 'gosterildi',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY oturum (oturum_id),
				KEY urun (urun_id),
				KEY durum_tarih (durum, created_at)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$rec} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				ref_id varchar(36) NOT NULL DEFAULT '',
				event_type varchar(20) NOT NULL DEFAULT '',
				product_id bigint(20) unsigned NOT NULL,
				session_id varchar(36) NOT NULL DEFAULT '',
				source varchar(20) DEFAULT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY idx_ref_id (ref_id),
				KEY idx_session_product_time (session_id, product_id, created_at),
				KEY idx_event_time (event_type, created_at),
				UNIQUE KEY uniq_ref_event (ref_id, event_type)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$canli} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				oturum_id varchar(64) NOT NULL DEFAULT '',
				masa_no varchar(64) NOT NULL DEFAULT '',
				son_musteri_mesaj text NOT NULL,
				son_bot_cevap text NOT NULL,
				durum varchar(20) NOT NULL DEFAULT 'bekliyor',
				son_aktivite datetime NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY idx_oturum (oturum_id),
				KEY idx_durum_aktivite (durum, son_aktivite)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$personel} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				oturum_id varchar(64) NOT NULL DEFAULT '',
				mesaj text NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY idx_oturum_id (oturum_id, id)
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
	 * Kapatılmış canlı sohbet kayıtları ve bunlara ait personel mesajları da
	 * aynı saklama süresine tabidir; "bekliyor"/"devralindi" durumundaki AÇIK
	 * kayıtlara — hâlâ personel ilgisi bekleyebilecekleri için — dokunulmaz.
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

		$tablo_mesaj    = self::mesaj_tablosu();
		$tablo_log      = self::oneri_log_tablosu();
		$tablo_canli    = self::canli_tablosu();
		$tablo_personel = self::personel_mesaj_tablosu();
		$esik           = gmdate( 'Y-m-d H:i:s', time() - ( $gun * DAY_IN_SECONDS ) );

		$silinen  = (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$tablo_mesaj} WHERE created_at < %s", $esik )
		);
		$silinen += (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$tablo_log} WHERE created_at < %s", $esik )
		);

		$kapali_oturumlar = $wpdb->get_col(
			$wpdb->prepare( "SELECT oturum_id FROM {$tablo_canli} WHERE durum = 'kapatildi' AND son_aktivite < %s", $esik )
		);
		if ( $kapali_oturumlar ) {
			$yerler = implode( ',', array_fill( 0, count( $kapali_oturumlar ), '%s' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- yerler yalnızca %s.
			$silinen += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$tablo_personel} WHERE oturum_id IN ({$yerler})", $kapali_oturumlar ) );
		}
		$silinen += (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$tablo_canli} WHERE durum = 'kapatildi' AND son_aktivite < %s", $esik )
		);

		return $silinen;
	}

	/**
	 * Canlı sohbet takibini günceller.
	 *
	 * Eskalasyon bu turda tetiklendiyse yeni bir satır açar (veya var olanı
	 * günceller); tetiklenmediyse yalnızca ZATEN takip edilen bir oturumu
	 * günceller — aksi hâlde her sıradan sohbet "canlı" listesine düşerdi.
	 * `durum` alanına burada dokunulmaz: personel devraldıysa/kapattıysa bu
	 * güncelleme onu ezmez.
	 *
	 * @param string $oturum_id     Oturum anahtarı.
	 * @param string $masa_no       Masa.
	 * @param string $musteri_mesaj Ziyaretçi mesajı.
	 * @param string $bot_cevap     Bot yanıtı.
	 * @param bool   $eskalasyon_mi Bu turda eskalasyon tetiklendi mi.
	 * @return void
	 */
	public static function canli_guncelle( $oturum_id, $masa_no, $musteri_mesaj, $bot_cevap, $eskalasyon_mi ) {
		global $wpdb;

		self::sema_kontrol();

		$oturum_id = substr( sanitize_text_field( $oturum_id ), 0, 64 );
		if ( '' === $oturum_id ) {
			return;
		}

		$masa_no       = substr( sanitize_text_field( $masa_no ), 0, 64 );
		$musteri_mesaj = sanitize_textarea_field( $musteri_mesaj );
		$bot_cevap     = sanitize_textarea_field( $bot_cevap );
		$simdi         = current_time( 'mysql' );
		$tablo         = self::canli_tablosu();

		if ( $eskalasyon_mi ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$tablo} (oturum_id, masa_no, son_musteri_mesaj, son_bot_cevap, durum, son_aktivite, created_at)
						VALUES (%s, %s, %s, %s, 'bekliyor', %s, %s)
						ON DUPLICATE KEY UPDATE
							masa_no = VALUES(masa_no),
							son_musteri_mesaj = VALUES(son_musteri_mesaj),
							son_bot_cevap = VALUES(son_bot_cevap),
							son_aktivite = VALUES(son_aktivite)",
					$oturum_id,
					$masa_no,
					$musteri_mesaj,
					$bot_cevap,
					$simdi,
					$simdi
				)
			);
			return;
		}

		$wpdb->update(
			$tablo,
			array(
				'masa_no'           => $masa_no,
				'son_musteri_mesaj' => $musteri_mesaj,
				'son_bot_cevap'     => $bot_cevap,
				'son_aktivite'      => $simdi,
			),
			array( 'oturum_id' => $oturum_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Kapatılmamış canlı sohbetler (en son aktif olan önce).
	 *
	 * @return array
	 */
	public static function canli_liste() {
		global $wpdb;
		self::sema_kontrol();

		$tablo    = self::canli_tablosu();
		$satirlar = $wpdb->get_results( "SELECT * FROM {$tablo} WHERE durum <> 'kapatildi' ORDER BY son_aktivite DESC" );

		return is_array( $satirlar ) ? $satirlar : array();
	}

	/**
	 * Canlı sohbeti kapatır (devralma bitti).
	 *
	 * @param string $oturum_id Oturum anahtarı.
	 * @return bool
	 */
	public static function canli_kapat( $oturum_id ) {
		global $wpdb;
		self::sema_kontrol();

		$oturum_id = sanitize_text_field( $oturum_id );
		if ( '' === $oturum_id ) {
			return false;
		}

		return false !== $wpdb->update(
			self::canli_tablosu(),
			array( 'durum' => 'kapatildi' ),
			array( 'oturum_id' => $oturum_id ),
			array( '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Personel mesajı yazar; oturumu "devralindi" durumuna taşır.
	 *
	 * @param string $oturum_id Oturum anahtarı.
	 * @param string $mesaj     Personel mesajı.
	 * @return int Eklenen satır kimliği (yazılamadıysa 0).
	 */
	public static function personel_mesaj_yaz( $oturum_id, $mesaj ) {
		global $wpdb;

		self::sema_kontrol();

		$oturum_id = substr( sanitize_text_field( $oturum_id ), 0, 64 );
		$mesaj     = sanitize_textarea_field( $mesaj );
		if ( '' === $oturum_id || '' === $mesaj ) {
			return 0;
		}

		$wpdb->insert(
			self::personel_mesaj_tablosu(),
			array(
				'oturum_id'  => $oturum_id,
				'mesaj'      => $mesaj,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s' )
		);
		$id = (int) $wpdb->insert_id;
		if ( $id < 1 ) {
			return 0;
		}

		$wpdb->update(
			self::canli_tablosu(),
			array( 'durum' => 'devralindi' ),
			array( 'oturum_id' => $oturum_id ),
			array( '%s' ),
			array( '%s' )
		);

		return $id;
	}

	/**
	 * Bir oturuma ait, verilen kimlikten SONRAKİ personel mesajları.
	 *
	 * @param string $oturum_id  Oturum anahtarı.
	 * @param int    $sonrasi_id Bu kimlikten sonraki satırlar (0 = tümü).
	 * @return array
	 */
	public static function personel_mesajlari_al( $oturum_id, $sonrasi_id = 0 ) {
		global $wpdb;
		self::sema_kontrol();

		$oturum_id = sanitize_text_field( $oturum_id );
		if ( '' === $oturum_id ) {
			return array();
		}

		$tablo    = self::personel_mesaj_tablosu();
		$satirlar = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$tablo} WHERE oturum_id = %s AND id > %d ORDER BY id ASC",
				$oturum_id,
				absint( $sonrasi_id )
			)
		);

		return is_array( $satirlar ) ? $satirlar : array();
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

	/**
	 * Öneri kuralı ekler veya günceller (kaynak-hedef çifti benzersiz).
	 *
	 * @param int $kaynak  Tetikleyen ürün kimliği.
	 * @param int $hedef   Önerilecek ürün kimliği.
	 * @param int $agirlik Ağırlık (0–100 arasına kısıtlanır).
	 * @return bool
	 */
	public static function kural_ekle( $kaynak, $hedef, $agirlik ) {
		global $wpdb;

		self::sema_kontrol();

		$agirlik = max( 0, min( 100, absint( $agirlik ) ) );
		$tablo   = self::oneri_kural_tablosu();

		$sonuc = $wpdb->query(
			$wpdb->prepare(
				"REPLACE INTO {$tablo} (kaynak_urun, hedef_urun, agirlik, aktif, created_at) VALUES (%d, %d, %d, 1, %s)",
				absint( $kaynak ),
				absint( $hedef ),
				$agirlik,
				current_time( 'mysql' )
			)
		);

		return false !== $sonuc;
	}

	/**
	 * Öneri kuralını kimliğe göre siler.
	 *
	 * @param int $id Kural kimliği.
	 * @return bool
	 */
	public static function kural_sil( $id ) {
		global $wpdb;

		self::sema_kontrol();

		return false !== $wpdb->delete(
			self::oneri_kural_tablosu(),
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);
	}

	/**
	 * Aktif öneri kurallarını döndürür.
	 *
	 * @param int $kaynak 0 ise tüm aktif kurallar; aksi halde kaynak ürüne göre.
	 * @return array
	 */
	public static function kurallari_getir( $kaynak = 0 ) {
		global $wpdb;

		self::sema_kontrol();

		$tablo = self::oneri_kural_tablosu();

		if ( 0 === (int) $kaynak ) {
			$satirlar = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$tablo} WHERE aktif = %d ORDER BY agirlik DESC",
					1
				)
			);
		} else {
			$satirlar = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$tablo} WHERE kaynak_urun = %d AND aktif = %d ORDER BY agirlik DESC",
					absint( $kaynak ),
					1
				)
			);
		}

		return is_array( $satirlar ) ? $satirlar : array();
	}

	/**
	 * Öneri gösterim / dönüşüm olayını loglar.
	 *
	 * @param string $oturum_id Oturum anahtarı.
	 * @param string $masa_no   Masa numarası.
	 * @param int    $urun_id   Ürün kimliği.
	 * @param string $kaynak    ai|kural.
	 * @param string $durum     gosterildi|sepete|siparis.
	 * @return bool
	 */
	public static function oneri_logla( $oturum_id, $masa_no, $urun_id, $kaynak, $durum ) {
		global $wpdb;

		self::sema_kontrol();

		$izinli_kaynak = array( 'ai', 'kural' );
		$izinli_durum  = array( 'gosterildi', 'sepete', 'siparis' );

		if ( ! in_array( $kaynak, $izinli_kaynak, true ) || ! in_array( $durum, $izinli_durum, true ) ) {
			return false;
		}

		$sonuc = $wpdb->insert(
			self::oneri_log_tablosu(),
			array(
				'oturum_id'  => substr( sanitize_text_field( $oturum_id ), 0, 64 ),
				'masa_no'    => substr( sanitize_text_field( $masa_no ), 0, 32 ),
				'urun_id'    => absint( $urun_id ),
				'kaynak'     => $kaynak,
				'durum'      => $durum,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		return false !== $sonuc;
	}

	/**
	 * Aynı oturum ve ürün için en son gösterildi/sepete kaydının durumunu günceller.
	 *
	 * @param string $oturum_id  Oturum anahtarı.
	 * @param int    $urun_id    Ürün kimliği.
	 * @param string $yeni_durum gosterildi|sepete|siparis.
	 * @return bool
	 */
	public static function oneri_durum_guncelle( $oturum_id, $urun_id, $yeni_durum ) {
		global $wpdb;

		self::sema_kontrol();

		$izinli_durum = array( 'gosterildi', 'sepete', 'siparis' );
		if ( ! in_array( $yeni_durum, $izinli_durum, true ) ) {
			return false;
		}

		$oturum_id = sanitize_text_field( $oturum_id );
		if ( '' === $oturum_id ) {
			return false;
		}

		$tablo = self::oneri_log_tablosu();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$tablo} WHERE oturum_id = %s AND urun_id = %d AND durum IN ('gosterildi', 'sepete') ORDER BY created_at DESC, id DESC LIMIT 1",
				$oturum_id,
				absint( $urun_id )
			)
		);

		if ( ! $row ) {
			return false;
		}

		return false !== $wpdb->update(
			$tablo,
			array( 'durum' => $yeni_durum ),
			array( 'id' => (int) $row->id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Öneri raporu cutover günü (Y-m-d).
	 *
	 * @return string
	 */
	public static function recommendation_report_cutover_date() {
		return (string) apply_filters(
			'qmo_recommendation_report_cutover_date',
			self::RECOMMENDATION_REPORT_CUTOVER_DATE
		);
	}

	/**
	 * Rapor aralığını cutover'a göre gösterildi segmentlerine böler.
	 *
	 * Legacy gösterildi yalnızca cutover öncesi günler; events shown cutover ve sonrası.
	 * Sepete / atfedilen / doğrudan bot siparişi tam seçilen aralıkta kalır.
	 *
	 * @param string $bas_ymd Başlangıç (Y-m-d).
	 * @param string $bit_ymd Bitiş (Y-m-d).
	 * @return array<string, array<string, string>|null>
	 */
	public static function oneri_rapor_aralik_bol( $bas_ymd, $bit_ymd ) {
		$bas_ymd = sanitize_text_field( (string) $bas_ymd );
		$bit_ymd = sanitize_text_field( (string) $bit_ymd );
		$cutover = self::recommendation_report_cutover_date();

		$full_bas = $bas_ymd . ' 00:00:00';
		$full_bit = $bit_ymd . ' 23:59:59';

		$legacy_shown = null;
		if ( $bas_ymd < $cutover ) {
			$legacy_bit_ymd = gmdate( 'Y-m-d', strtotime( $cutover . ' -1 day' ) );
			if ( $bit_ymd < $legacy_bit_ymd ) {
				$legacy_bit_ymd = $bit_ymd;
			}
			$legacy_shown = array(
				'bas' => $full_bas,
				'bit' => $legacy_bit_ymd . ' 23:59:59',
			);
		}

		$event_shown = null;
		if ( $bit_ymd >= $cutover ) {
			$event_bas = ( $bas_ymd >= $cutover ) ? $full_bas : ( $cutover . ' 00:00:00' );
			$event_shown = array(
				'bas' => $event_bas,
				'bit' => $full_bit,
			);
		}

		return array(
			'legacy_shown' => $legacy_shown,
			'event_shown'  => $event_shown,
			'tam_aralik'   => array(
				'bas' => $full_bas,
				'bit' => $full_bit,
			),
		);
	}

	/**
	 * Ürün bazlı legacy gösterildi (cutover öncesi segment).
	 *
	 * @param string $bas Datetime.
	 * @param string $bit Datetime.
	 * @return array<int,int> urun_id => adet
	 */
	private static function oneri_rapor_legacy_gosterildi( $bas, $bit ) {
		global $wpdb;

		$tablo = self::oneri_log_tablosu();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT urun_id, COUNT(*) AS gosterildi
				FROM {$tablo}
				WHERE durum = 'gosterildi'
				  AND created_at >= %s AND created_at <= %s
				GROUP BY urun_id",
				$bas,
				$bit
			)
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$uid = (int) $row->urun_id;
			if ( $uid > 0 ) {
				$out[ $uid ] = (int) $row->gosterildi;
			}
		}

		return $out;
	}

	/**
	 * Ürün bazlı events shown (COUNT DISTINCT ref_id).
	 *
	 * @param string $bas Datetime.
	 * @param string $bit Datetime.
	 * @return array<int,int>
	 */
	private static function oneri_rapor_events_shown( $bas, $bit ) {
		global $wpdb;

		$tablo = self::recommendation_events_tablosu();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id AS urun_id, COUNT(DISTINCT ref_id) AS gosterildi
				FROM {$tablo}
				WHERE event_type = %s
				  AND created_at >= %s AND created_at <= %s
				GROUP BY product_id",
				self::REC_EVENT_SHOWN,
				$bas,
				$bit
			)
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$uid = (int) $row->urun_id;
			if ( $uid > 0 ) {
				$out[ $uid ] = (int) $row->gosterildi;
			}
		}

		return $out;
	}

	/**
	 * Ürün bazlı doğrudan chatbot siparişi (legacy oneri_log siparis).
	 *
	 * @param string $bas Datetime.
	 * @param string $bit Datetime.
	 * @return array<int,int>
	 */
	private static function oneri_rapor_dogrudan_bot_siparis( $bas, $bit ) {
		global $wpdb;

		$tablo = self::oneri_log_tablosu();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT urun_id, COUNT(*) AS siparis
				FROM {$tablo}
				WHERE durum = 'siparis'
				  AND created_at >= %s AND created_at <= %s
				GROUP BY urun_id",
				$bas,
				$bit
			)
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$uid = (int) $row->urun_id;
			if ( $uid > 0 ) {
				$out[ $uid ] = (int) $row->siparis;
			}
		}

		return $out;
	}

	/**
	 * Ürün bazlı recommendation cart_add (COUNT DISTINCT ref_id).
	 *
	 * @param string $bas Datetime.
	 * @param string $bit Datetime.
	 * @return array<int,int>
	 */
	private static function oneri_rapor_recommendation_sepete( $bas, $bit ) {
		global $wpdb;

		$tablo = self::recommendation_events_tablosu();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id AS urun_id, COUNT(DISTINCT ref_id) AS sepete
				FROM {$tablo}
				WHERE event_type = %s AND created_at >= %s AND created_at <= %s
				GROUP BY product_id",
				self::REC_EVENT_CART_ADD,
				$bas,
				$bit
			)
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$uid = (int) $row->urun_id;
			if ( $uid > 0 ) {
				$out[ $uid ] = (int) $row->sepete;
			}
		}

		return $out;
	}

	/**
	 * Phase 6.2 — order-line attribution engine (gözlemsel, nedensellik iddiası yok).
	 *
	 * Kohort: cart_add.created_at ∈ [bas, bit].
	 * Her ref → aynı session+ürün kapsamında ilk sonraki uygun (order_id, item_id) satırı.
	 * İptal: order_cancelled olan order_id hariç. Birim: MIN(line_qty, ref_count). Tutar: unit_price × birim (NULL fiyat → 0).
	 *
	 * @param string $bas Datetime (cart_add alt sınırı).
	 * @param string $bit Datetime (cart_add üst sınırı).
	 * @return array{
	 *   ozet: array{atfedilen_siparis_tekil:int,atfedilen_kalem:int,atfedilen_birim:int,atfedilen_tutar:float},
	 *   urunler: array<int,array{atfedilen_siparis_tekil:int,atfedilen_kalem:int,atfedilen_birim:int,atfedilen_tutar:float}>,
	 *   satirlar: array<int,array<string,mixed>>
	 * }
	 */
	public static function recommendation_attribution_hesapla( $bas, $bit ) {
		if ( ! class_exists( 'QRMS_Analitik' ) ) {
			if ( function_exists( 'qmo_log_critical' ) ) {
				qmo_log_critical( 'Attribution query skipped', array( 'reason' => 'analytics_module_missing' ) );
			}
			return self::recommendation_attribution_bos_sonuc( true );
		}

		if ( ! QRMS_Analitik::tablo_var_mi() ) {
			if ( function_exists( 'qmo_log_critical' ) ) {
				qmo_log_critical( 'Attribution query skipped', array( 'reason' => 'analytics_table_missing' ) );
			}
			return self::recommendation_attribution_bos_sonuc( true );
		}

		global $wpdb;

		$tablo_rec = self::recommendation_events_tablosu();
		$analitik  = QRMS_Analitik::tablo();
		$bas       = sanitize_text_field( (string) $bas );
		$bit       = sanitize_text_field( (string) $bit );

		$sql = "WITH carts AS (
				SELECT id AS cart_row_id, ref_id, product_id, session_id, created_at AS cart_at
				FROM {$tablo_rec}
				WHERE event_type = %s
				  AND created_at >= %s AND created_at <= %s
			),
			lines_base AS (
				SELECT
					o.session_id,
					o.item_id,
					o.order_id,
					MIN(o.created_at) AS line_at,
					MIN(o.id) AS line_row_id,
					SUM(o.qty) AS line_qty
				FROM {$analitik} o
				WHERE o.event_type = 'order_sent'
				  AND o.order_id IS NOT NULL
				  AND o.order_id <> ''
				  AND o.created_at >= %s
				GROUP BY o.session_id, o.item_id, o.order_id
			),
			eligible_lines AS (
				SELECT lb.*
				FROM lines_base lb
				WHERE NOT EXISTS (
					SELECT 1 FROM {$analitik} c
					WHERE c.event_type = 'order_cancelled'
					  AND c.order_id = lb.order_id
				)
			),
			lines_priced AS (
				SELECT
					l.session_id,
					l.item_id,
					l.order_id,
					l.line_at,
					l.line_row_id,
					l.line_qty,
					(
						SELECT o2.unit_price
						FROM {$analitik} o2
						WHERE o2.event_type = 'order_sent'
						  AND o2.session_id = l.session_id
						  AND o2.item_id = l.item_id
						  AND o2.order_id = l.order_id
						ORDER BY o2.created_at ASC, o2.id ASC
						LIMIT 1
					) AS unit_price
				FROM eligible_lines l
			),
			ref_match AS (
				SELECT
					c.ref_id,
					c.product_id,
					c.session_id,
					c.cart_at,
					c.cart_row_id,
					lp.order_id,
					lp.item_id,
					lp.line_at,
					lp.line_row_id,
					lp.line_qty,
					lp.unit_price,
					ROW_NUMBER() OVER (
						PARTITION BY c.ref_id
						ORDER BY lp.line_at ASC, lp.line_row_id ASC
					) AS ref_ord
				FROM carts c
				INNER JOIN lines_priced lp
				  ON lp.session_id = c.session_id
				 AND lp.item_id = c.product_id
				 AND lp.line_at >= c.cart_at
			),
			ref_first AS (
				SELECT * FROM ref_match WHERE ref_ord = 1
			),
			line_refs AS (
				SELECT
					rf.product_id,
					rf.session_id,
					rf.order_id,
					rf.item_id,
					MAX(rf.line_qty) AS line_qty,
					MAX(rf.unit_price) AS unit_price,
					COUNT(*) AS consumed_ref_count
				FROM ref_first rf
				GROUP BY rf.product_id, rf.session_id, rf.order_id, rf.item_id
			),
			line_attrib AS (
				SELECT
					product_id,
					session_id,
					order_id,
					item_id,
					line_qty,
					consumed_ref_count,
					LEAST(line_qty, consumed_ref_count) AS attributed_units,
					unit_price,
					CASE
						WHEN unit_price IS NULL THEN 0
						ELSE LEAST(line_qty, consumed_ref_count) * unit_price
					END AS attributed_revenue
				FROM line_refs
			)
			SELECT
				product_id,
				session_id,
				order_id,
				item_id,
				attributed_units,
				attributed_revenue,
				unit_price
			FROM line_attrib";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$prepared = $wpdb->prepare(
			$sql,
			self::REC_EVENT_CART_ADD,
			$bas,
			$bit,
			$bas
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $prepared, ARRAY_A );

		if ( null === $rows && '' !== (string) $wpdb->last_error ) {
			if ( function_exists( 'qmo_log_critical' ) ) {
				qmo_log_critical(
					'Attribution query failed',
					array( 'db' => (string) $wpdb->last_error )
				);
			}
			return self::recommendation_attribution_bos_sonuc( true );
		}

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return self::recommendation_attribution_bos_sonuc( false );
		}

		$satirlar = array();
		$urunler  = array();
		$orders   = array();
		$lines    = 0;
		$birim    = 0;
		$tutar    = 0.0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$pid   = (int) ( $row['product_id'] ?? 0 );
			$oid   = (string) ( $row['order_id'] ?? '' );
			$units = (int) ( $row['attributed_units'] ?? 0 );
			$rev   = (float) ( $row['attributed_revenue'] ?? 0 );
			if ( $pid < 1 || '' === $oid || $units < 1 ) {
				continue;
			}

			$satirlar[] = array(
				'product_id'         => $pid,
				'session_id'         => (string) ( $row['session_id'] ?? '' ),
				'order_id'           => $oid,
				'item_id'            => (int) ( $row['item_id'] ?? 0 ),
				'attributed_units'   => $units,
				'attributed_revenue' => $rev,
				'unit_price'         => isset( $row['unit_price'] ) ? $row['unit_price'] : null,
			);

			$orders[ $oid ] = true;
			++$lines;
			$birim += $units;
			$tutar += $rev;

			if ( ! isset( $urunler[ $pid ] ) ) {
				$urunler[ $pid ] = array(
					'atfedilen_siparis_tekil' => 0,
					'atfedilen_kalem'         => 0,
					'atfedilen_birim'         => 0,
					'atfedilen_tutar'         => 0.0,
					'_orders'                 => array(),
				);
			}
			$urunler[ $pid ]['_orders'][ $oid ]           = true;
			++$urunler[ $pid ]['atfedilen_kalem'];
			$urunler[ $pid ]['atfedilen_birim']         += $units;
			$urunler[ $pid ]['atfedilen_tutar']         += $rev;
		}

		foreach ( $urunler as $pid => $metrik ) {
			$urunler[ $pid ]['atfedilen_siparis_tekil'] = count( $metrik['_orders'] );
			unset( $urunler[ $pid ]['_orders'] );
		}

		$sonuc = array(
			'ozet'     => array(
				'atfedilen_siparis_tekil' => count( $orders ),
				'atfedilen_kalem'         => $lines,
				'atfedilen_birim'         => $birim,
				'atfedilen_tutar'         => $tutar,
			),
			'urunler'  => $urunler,
			'satirlar' => $satirlar,
		);
		$sonuc['sorgu_hatasi'] = false;

		return $sonuc;
	}

	/**
	 * Attribution engine boş sonuç şablonu (veri yok vs sorgu hatası ayrımı).
	 *
	 * @param bool $sorgu_hatasi true = operasyonel/sorgu hatası; false = gerçekten veri yok.
	 * @return array<string, mixed>
	 */
	private static function recommendation_attribution_bos_sonuc( $sorgu_hatasi ) {
		return array(
			'sorgu_hatasi' => (bool) $sorgu_hatasi,
			'ozet'         => array(
				'atfedilen_siparis_tekil' => 0,
				'atfedilen_kalem'         => 0,
				'atfedilen_birim'         => 0,
				'atfedilen_tutar'         => 0.0,
			),
			'urunler'      => array(),
			'satirlar'     => array(),
		);
	}

	/**
	 * Ürün bazlı tekil atfedilen sipariş (DISTINCT order_id).
	 *
	 * @param string $bas Datetime (cart_add kohort alt sınırı).
	 * @param string $bit Datetime (cart_add kohort üst sınırı).
	 * @return array<int,int> urun_id => atfedilen_siparis_tekil
	 */
	private static function oneri_rapor_atfedilen_siparis( $bas, $bit ) {
		$sonuc = self::recommendation_attribution_hesapla( $bas, $bit );
		$out   = array();
		foreach ( (array) ( $sonuc['urunler'] ?? array() ) as $uid => $metrik ) {
			$uid = (int) $uid;
			if ( $uid > 0 ) {
				$out[ $uid ] = (int) ( $metrik['atfedilen_siparis_tekil'] ?? 0 );
			}
		}
		return $out;
	}

	/**
	 * Tarih aralığında ürün bazlı öneri raporu (sayılar ve dönüşüm oranı).
	 *
	 * Gösterildi: cutover öncesi legacy + cutover sonrası events shown (asla toplama yok).
	 * Ana dönüşüm: atfedilen_siparis / gosterildi. Doğrudan bot siparişi ayrı KPI.
	 *
	 * `atfedilen_siparis`: ürün bazlı tekil sipariş adedi (= atfedilen_siparis_tekil, DISTINCT order_id).
	 * `atfedilen_birim`: recommendation-attributed quantity (MIN qty/ref kuralı).
	 *
	 * @param string $bas Başlangıç tarihi (Y-m-d).
	 * @param string $bit Bitiş tarihi (Y-m-d).
	 * @return array{
	 *   urunler: array<int, array<string, mixed>>,
	 *   ozet: array{
	 *     atfedilen_siparis_tekil: int,
	 *     atfedilen_kalem: int,
	 *     atfedilen_birim: int,
	 *     atfedilen_tutar: float
	 *   }
	 * }
	 */
	public static function oneri_rapor( $bas, $bit ) {
		self::sema_kontrol();

		$bas_ymd = sanitize_text_field( $bas );
		$bit_ymd = sanitize_text_field( $bit );
		$bol     = self::oneri_rapor_aralik_bol( $bas_ymd, $bit_ymd );
		$tam     = $bol['tam_aralik'];

		$gosterildi_map = array();
		if ( is_array( $bol['legacy_shown'] ) ) {
			foreach ( self::oneri_rapor_legacy_gosterildi( $bol['legacy_shown']['bas'], $bol['legacy_shown']['bit'] ) as $uid => $n ) {
				$gosterildi_map[ $uid ] = ( $gosterildi_map[ $uid ] ?? 0 ) + $n;
			}
		}
		if ( is_array( $bol['event_shown'] ) ) {
			foreach ( self::oneri_rapor_events_shown( $bol['event_shown']['bas'], $bol['event_shown']['bit'] ) as $uid => $n ) {
				$gosterildi_map[ $uid ] = ( $gosterildi_map[ $uid ] ?? 0 ) + $n;
			}
		}

		$sepete_map = self::oneri_rapor_recommendation_sepete( $tam['bas'], $tam['bit'] );
		$attr          = self::recommendation_attribution_hesapla( $tam['bas'], $tam['bit'] );
		$sorgu_hatasi  = ! empty( $attr['sorgu_hatasi'] );
		$attr_urun     = (array) ( $attr['urunler'] ?? array() );
		$bot_map       = self::oneri_rapor_dogrudan_bot_siparis( $tam['bas'], $tam['bit'] );

		$urun_ids = array_unique(
			array_merge(
				array_keys( $gosterildi_map ),
				array_keys( $sepete_map ),
				array_keys( $attr_urun ),
				array_keys( $bot_map )
			)
		);

		$attr_ozet = (array) ( $attr['ozet'] ?? array() );
		$ozet      = array(
			'atfedilen_siparis_tekil' => (int) ( $attr_ozet['atfedilen_siparis_tekil'] ?? 0 ),
			'atfedilen_kalem'         => (int) ( $attr_ozet['atfedilen_kalem'] ?? 0 ),
			'atfedilen_birim'         => (int) ( $attr_ozet['atfedilen_birim'] ?? 0 ),
			'atfedilen_tutar'         => (float) ( $attr_ozet['atfedilen_tutar'] ?? 0.0 ),
		);

		if ( empty( $urun_ids ) ) {
			return array(
				'urunler'       => array(),
				'ozet'          => $ozet,
				'sorgu_hatasi'  => $sorgu_hatasi,
			);
		}

		sort( $urun_ids, SORT_NUMERIC );

		$rapor = array();
		foreach ( $urun_ids as $urun_id ) {
			$urun_id    = (int) $urun_id;
			$gosterildi = (int) ( $gosterildi_map[ $urun_id ] ?? 0 );
			$sepete     = (int) ( $sepete_map[ $urun_id ] ?? 0 );
			$bot        = (int) ( $bot_map[ $urun_id ] ?? 0 );
			$a_metrik   = isset( $attr_urun[ $urun_id ] ) && is_array( $attr_urun[ $urun_id ] ) ? $attr_urun[ $urun_id ] : array();
			$siparis    = (int) ( $a_metrik['atfedilen_siparis_tekil'] ?? 0 );
			$birim      = (int) ( $a_metrik['atfedilen_birim'] ?? 0 );

			$rapor[] = array(
				'urun_id'                  => $urun_id,
				'gosterildi'               => $gosterildi,
				'sepete'                   => $sepete,
				'atfedilen_siparis'        => $siparis,
				'atfedilen_siparis_tekil'  => $siparis,
				'atfedilen_kalem'          => (int) ( $a_metrik['atfedilen_kalem'] ?? 0 ),
				'atfedilen_birim'          => $birim,
				'atfedilen_tutar'          => (float) ( $a_metrik['atfedilen_tutar'] ?? 0.0 ),
				'dogrudan_chatbot_siparis' => $bot,
				'donusum_orani'            => $gosterildi > 0 ? round( $siparis / $gosterildi, 4 ) : 0.0,
			);
		}

		return array(
			'urunler'      => $rapor,
			'ozet'         => $ozet,
			'sorgu_hatasi' => $sorgu_hatasi,
		);
	}

	/**
	 * oneri_rapor() ürün satırları (yeni ve eski dizi şeklinde geriye dönük uyum).
	 *
	 * @param array<string, mixed>|array<int, array<string, mixed>> $rapor oneri_rapor çıktısı.
	 * @return array<int, array<string, mixed>>
	 */
	public static function oneri_rapor_urunler( $rapor ) {
		if ( is_array( $rapor ) && array_key_exists( 'urunler', $rapor ) ) {
			return is_array( $rapor['urunler'] ) ? $rapor['urunler'] : array();
		}

		return is_array( $rapor ) ? $rapor : array();
	}

	/**
	 * oneri_rapor() global attribution özet KPI'ları.
	 *
	 * @param array<string, mixed> $rapor oneri_rapor çıktısı.
	 * @return array{
	 *   atfedilen_siparis_tekil: int,
	 *   atfedilen_kalem: int,
	 *   atfedilen_birim: int,
	 *   atfedilen_tutar: float
	 * }
	 */
	public static function oneri_rapor_ozet_attribution( $rapor ) {
		$bos = array(
			'atfedilen_siparis_tekil' => 0,
			'atfedilen_kalem'         => 0,
			'atfedilen_birim'         => 0,
			'atfedilen_tutar'         => 0.0,
		);

		if ( ! is_array( $rapor ) || ! isset( $rapor['ozet'] ) || ! is_array( $rapor['ozet'] ) ) {
			return $bos;
		}

		return array(
			'atfedilen_siparis_tekil' => (int) ( $rapor['ozet']['atfedilen_siparis_tekil'] ?? 0 ),
			'atfedilen_kalem'         => (int) ( $rapor['ozet']['atfedilen_kalem'] ?? 0 ),
			'atfedilen_birim'         => (int) ( $rapor['ozet']['atfedilen_birim'] ?? 0 ),
			'atfedilen_tutar'         => (float) ( $rapor['ozet']['atfedilen_tutar'] ?? 0.0 ),
		);
	}

	/**
	 * Eski recommendation_events shown/cart_add kayıtlarını parça parça siler.
	 *
	 * qmo_chatbot_gecmis_temizle yalnızca oneri_log (~30 gün) temizler; post-cutover
	 * gösterildi events tablosundan geldiği için bu saklama ayrı tutulur.
	 *
	 * @param int      $gun            Gün (varsayılan RECOMMENDATION_EVENTS_SAKLAMA_GUN).
	 * @param float|null $butce_saniye Kalan süre bütçesi; null = bütçesiz.
	 * @return int Silinen satır sayısı.
	 */
	public static function recommendation_events_eski_sil( $gun = null, $butce_saniye = null ) {
		global $wpdb;

		self::sema_kontrol();

		if ( null === $gun ) {
			$gun = self::RECOMMENDATION_EVENTS_SAKLAMA_GUN;
		}

		$gun = absint( $gun );
		if ( $gun < 1 ) {
			return 0;
		}

		$tablo = self::recommendation_events_tablosu();
		$sinir = gmdate( 'Y-m-d H:i:s', time() - ( $gun * DAY_IN_SECONDS ) );
		$parca = self::RECOMMENDATION_EVENTS_SAKLAMA_PARCA;
		$basla = microtime( true );
		$toplam = 0;

		foreach ( array( self::REC_EVENT_SHOWN, self::REC_EVENT_CART_ADD ) as $tip ) {
			while ( true ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$silinen = (int) $wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$tablo} WHERE event_type = %s AND created_at < %s LIMIT %d",
						$tip,
						$sinir,
						$parca
					)
				);

				$toplam += $silinen;

				if ( $silinen < $parca ) {
					break;
				}

				if ( null !== $butce_saniye && $butce_saniye > 0 && ( microtime( true ) - $basla ) >= $butce_saniye ) {
					return $toplam;
				}
			}
		}

		return $toplam;
	}

	/**
	 * Aynı (ref_id, event_type) yinelenen satırları temizler (upgrade öncesi).
	 *
	 * Her grupta en düşük id kalır; cart_add yarışından doğmuş duplicate'ler
	 * UNIQUE indeks eklenmeden önce kaldırılır.
	 *
	 * @return void
	 */
	public static function recommendation_events_dedupe_yinelenen() {
		global $wpdb;

		$tablo = self::recommendation_events_tablosu();
		$like  = $wpdb->esc_like( $tablo );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$var = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
		if ( $var !== $tablo ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			"DELETE e1 FROM {$tablo} e1
			 INNER JOIN {$tablo} e2
			   ON e1.ref_id = e2.ref_id
			  AND e1.event_type = e2.event_type
			  AND e1.id > e2.id"
		);
	}

	/**
	 * Opaque recommendation instance kimliği üretir (sunucu).
	 *
	 * @return string
	 */
	public static function recommendation_ref_uret() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return substr( sanitize_text_field( wp_generate_uuid4() ), 0, 36 );
		}

		return substr( wp_hash( uniqid( 'rec', true ) . wp_rand() ), 0, 36 );
	}

	/**
	 * İstemciden gelen ref_id'yi doğrular.
	 *
	 * Yalnızca string kabul edilir; dizi/nesne/sayı string'e çevrilmeden
	 * reddedilir (Array to string conversion yok). Biçim
	 * recommendation_ref_uret() çıktısıdır: UUID (8-4-4-4-12 hex) veya
	 * yedek yolun 32 hex'i.
	 *
	 * @param mixed $ref_id Ham değer.
	 * @return string Geçerli ref ya da ''.
	 */
	public static function recommendation_ref_dogrula( $ref_id ) {
		if ( ! is_string( $ref_id ) ) {
			return '';
		}

		if ( ! preg_match( '/^(?:[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|[0-9a-f]{32})$/i', $ref_id ) ) {
			return '';
		}

		return $ref_id;
	}

	/**
	 * Son sorgu hatası UNIQUE ihlali mi (MySQL 1062)?
	 *
	 * @return bool
	 */
	private static function yinelenen_anahtar_hatasi_mi() {
		global $wpdb;

		// Hata kodu dil bağımsızdır; last_error metni sunucu lc_messages'a göre çevrilebilir.
		if ( isset( $wpdb->dbh ) && $wpdb->dbh instanceof mysqli ) {
			return 1062 === mysqli_errno( $wpdb->dbh ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_errno -- wpdb errno sunmuyor.
		}

		return 0 === stripos( (string) $wpdb->last_error, 'Duplicate entry' );
	}

	/**
	 * Recommendation attribution olayı ekler (append-only).
	 *
	 * @param string      $ref_id     Instance kimliği.
	 * @param string      $event_type shown|cart_add.
	 * @param int         $product_id Ürün kimliği.
	 * @param string      $session_id Canonical s_… oturumu.
	 * @param string|null $source     ai|kural (shown için).
	 * @return bool
	 */
	public static function recommendation_event_ekle( $ref_id, $event_type, $product_id, $session_id, $source = null ) {
		global $wpdb;

		self::sema_kontrol();

		if ( ! is_scalar( $ref_id ) ) {
			return false;
		}

		$ref_id = substr( sanitize_text_field( (string) $ref_id ), 0, 36 );
		if ( strlen( $ref_id ) < 8 ) {
			return false;
		}

		$event_type = sanitize_key( (string) $event_type );
		if ( ! in_array( $event_type, array( self::REC_EVENT_SHOWN, self::REC_EVENT_CART_ADD ), true ) ) {
			return false;
		}

		$product_id = absint( $product_id );
		if ( $product_id < 1 ) {
			return false;
		}

		$session_id = substr( sanitize_text_field( (string) $session_id ), 0, 36 );
		if ( '' === $session_id || 0 !== strpos( $session_id, 's_' ) ) {
			return false;
		}

		$kaynak = null;
		if ( null !== $source && '' !== (string) $source ) {
			$kaynak = sanitize_key( (string) $source );
			if ( ! in_array( $kaynak, array( 'ai', 'kural' ), true ) ) {
				$kaynak = 'ai';
			}
		}

		$veri = array(
			'ref_id'     => $ref_id,
			'event_type' => $event_type,
			'product_id' => $product_id,
			'session_id' => $session_id,
			'created_at' => current_time( 'mysql' ),
		);
		$format = array( '%s', '%s', '%d', '%s', '%s' );

		if ( null !== $kaynak ) {
			$veri['source'] = $kaynak;
			$format[]       = '%s';
		} else {
			$veri['source'] = null;
			$format[]       = '%s';
		}

		$tablo = self::recommendation_events_tablosu();

		if ( self::REC_EVENT_CART_ADD !== $event_type ) {
			return false !== $wpdb->insert( $tablo, $veri, $format );
		}

		// cart_add yarışında ikinci insert uniq_ref_event'e takılır: beklenen
		// idempotency durumu, hata basılmaz/loglanmaz (SQL, ref_id, session_id
		// yanıta sızmasın). Başka DB hataları wpdb'nin normal raporlamasına döner.
		$onceki = $wpdb->suppress_errors( true );
		$sonuc  = $wpdb->insert( $tablo, $veri, $format );
		$wpdb->suppress_errors( $onceki );

		if ( false === $sonuc && '' !== (string) $wpdb->last_error && ! self::yinelenen_anahtar_hatasi_mi() ) {
			$wpdb->print_error( $wpdb->last_error );
		}

		return false !== $sonuc;
	}

	/**
	 * Gösterim (shown) kayıtlarını ref_id listesi için yükler.
	 *
	 * @param string[] $ref_ids Ref kimlikleri.
	 * @return array<string,array{ref_id:string,product_id:int,session_id:string,event_type:string,created_at:string}>
	 */
	public static function recommendation_shown_refleri( array $ref_ids ) {
		global $wpdb;

		self::sema_kontrol();

		$ref_ids = array_values(
			array_unique(
				array_filter(
					array_map(
						function ( $id ) {
							return substr( sanitize_text_field( (string) $id ), 0, 36 );
						},
						$ref_ids
					)
				)
			)
		);

		if ( empty( $ref_ids ) ) {
			return array();
		}

		$tablo  = self::recommendation_events_tablosu();
		$yer    = implode( ', ', array_fill( 0, count( $ref_ids ), '%s' ) );
		$args   = $ref_ids;
		$args[] = self::REC_EVENT_SHOWN;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
		$sql = "SELECT ref_id, product_id, session_id, event_type, created_at
		          FROM {$tablo}
		         WHERE ref_id IN ({$yer})
		           AND event_type = %s";

		$satirlar = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );

		$cikti = array();
		foreach ( (array) $satirlar as $satir ) {
			if ( ! is_array( $satir ) || empty( $satir['ref_id'] ) ) {
				continue;
			}
			$cikti[ (string) $satir['ref_id'] ] = array(
				'ref_id'     => (string) $satir['ref_id'],
				'product_id' => (int) $satir['product_id'],
				'session_id' => (string) $satir['session_id'],
				'event_type' => (string) $satir['event_type'],
				'created_at' => (string) $satir['created_at'],
			);
		}

		return $cikti;
	}

	/**
	 * Bu ref için cart_add attribution zaten var mı?
	 *
	 * @param string $ref_id Ref kimliği.
	 * @return bool
	 */
	public static function recommendation_cart_attribution_var_mi( $ref_id ) {
		global $wpdb;

		self::sema_kontrol();

		$ref_id = substr( sanitize_text_field( (string) $ref_id ), 0, 36 );
		if ( '' === $ref_id ) {
			return true;
		}

		$tablo = self::recommendation_events_tablosu();
		$var   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$tablo} WHERE ref_id = %s AND event_type = %s LIMIT 1",
				$ref_id,
				self::REC_EVENT_CART_ADD
			)
		);

		return ! empty( $var );
	}

	/**
	 * Doğrulanmış chatbot kart cart_add attribution yazar.
	 *
	 * @param string $ref_id     İstemciden gelen ref (doğrulanır).
	 * @param int    $product_id Sepet ürün kimliği.
	 * @param string $session_id Canonical oturum (s_…).
	 * @return bool
	 */
	public static function recommendation_cart_attribution_kaydet( $ref_id, $product_id, $session_id ) {
		$ref_id     = self::recommendation_ref_dogrula( $ref_id );
		$product_id = absint( $product_id );
		$session_id = substr( sanitize_text_field( (string) $session_id ), 0, 36 );

		if ( '' === $ref_id || $product_id < 1 || '' === $session_id || 0 !== strpos( $session_id, 's_' ) ) {
			return false;
		}

		if ( self::recommendation_cart_attribution_var_mi( $ref_id ) ) {
			return false;
		}

		$shown = self::recommendation_shown_refleri( array( $ref_id ) );
		if ( empty( $shown[ $ref_id ] ) ) {
			return false;
		}

		$kayit = $shown[ $ref_id ];
		if ( (int) $kayit['product_id'] !== $product_id ) {
			return false;
		}
		if ( (string) $kayit['session_id'] !== $session_id ) {
			return false;
		}

		return self::recommendation_event_ekle(
			$ref_id,
			self::REC_EVENT_CART_ADD,
			$product_id,
			$session_id,
			null
		);
	}

	/**
	 * Gözlemsel order_sent ilişkisi: aynı session + ürün, sipariş zamanı >= cart_add.
	 *
	 * Causal iddia değildir; yalnızca analitik satırlarına bakar. Tek ref için bool döner;
	 * rapordaki atfedilen_siparis toplu COUNT(DISTINCT ref_id) ile aynı kurala dayanır (ref başına
	 * en fazla bir instance; aynı order_sent birden fazla ref ile eşleşebilir).
	 *
	 * @param string $ref_id Recommendation instance.
	 * @return bool
	 */
	public static function recommendation_gozlemsel_order_sent_var_mi( $ref_id ) {
		global $wpdb;

		if ( ! class_exists( 'QRMS_Analitik' ) ) {
			return false;
		}

		self::sema_kontrol();

		$ref_id = substr( sanitize_text_field( (string) $ref_id ), 0, 36 );
		if ( '' === $ref_id ) {
			return false;
		}

		$tablo_rec = self::recommendation_events_tablosu();
		$cart      = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT product_id, session_id, created_at FROM {$tablo_rec}
				 WHERE ref_id = %s AND event_type = %s LIMIT 1",
				$ref_id,
				self::REC_EVENT_CART_ADD
			),
			ARRAY_A
		);

		if ( ! is_array( $cart ) || empty( $cart['session_id'] ) ) {
			return false;
		}

		$analitik = QRMS_Analitik::tablo();
		$var      = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$analitik}
				 WHERE event_type = 'order_sent'
				   AND session_id = %s
				   AND item_id = %d
				   AND created_at >= %s
				 LIMIT 1",
				(string) $cart['session_id'],
				(int) $cart['product_id'],
				(string) $cart['created_at']
			)
		);

		return ! empty( $var );
	}

	/**
	 * Verilen ref_id'lerden cart_add attribution'ı olanları döner.
	 *
	 * @param string[] $ref_ids Ref listesi.
	 * @return array<string,bool> ref_id => true
	 */
	public static function recommendation_cart_refleri( array $ref_ids ) {
		global $wpdb;

		self::sema_kontrol();

		$ref_ids = array_values(
			array_unique(
				array_filter(
					array_map(
						function ( $id ) {
							return substr( sanitize_text_field( (string) $id ), 0, 36 );
						},
						$ref_ids
					)
				)
			)
		);

		if ( empty( $ref_ids ) ) {
			return array();
		}

		$tablo = self::recommendation_events_tablosu();
		$yer   = implode( ', ', array_fill( 0, count( $ref_ids ), '%s' ) );
		$args  = $ref_ids;
		$args[] = self::REC_EVENT_CART_ADD;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT ref_id FROM {$tablo} WHERE ref_id IN ({$yer}) AND event_type = %s";

		$kolon = $wpdb->get_col( $wpdb->prepare( $sql, $args ) );

		$cikti = array();
		foreach ( (array) $kolon as $ref ) {
			$cikti[ (string) $ref ] = true;
		}

		return $cikti;
	}

	/**
	 * Toplu sepet olaylarından doğrulanmış recommendation cart_add yazar.
	 *
	 * @param array<int,array<string,mixed>> $olaylar    cart_add olayları.
	 * @param string                         $session_id Canonical s_… oturumu.
	 * @return void
	 */
	public static function recommendation_sepet_olaylari_isle( array $olaylar, $session_id ) {
		$session_id = substr( sanitize_text_field( (string) $session_id ), 0, 36 );
		if ( '' === $session_id || 0 !== strpos( $session_id, 's_' ) ) {
			return;
		}

		$refs = array();
		foreach ( $olaylar as $o ) {
			if ( ! is_array( $o ) ) {
				continue;
			}
			$tip = isset( $o['tip'] ) ? sanitize_key( (string) $o['tip'] ) : '';
			if ( 'cart_add' !== $tip ) {
				continue;
			}
			$ref = isset( $o['ref_id'] ) ? self::recommendation_ref_dogrula( $o['ref_id'] ) : '';
			if ( '' !== $ref ) {
				$refs[] = $ref;
			}
		}

		if ( empty( $refs ) ) {
			return;
		}

		$shown_map  = self::recommendation_shown_refleri( $refs );
		$cart_mevcut = self::recommendation_cart_refleri( $refs );

		foreach ( $olaylar as $o ) {
			if ( ! is_array( $o ) ) {
				continue;
			}
			$tip = isset( $o['tip'] ) ? sanitize_key( (string) $o['tip'] ) : '';
			if ( 'cart_add' !== $tip ) {
				continue;
			}

			$ref = isset( $o['ref_id'] ) ? self::recommendation_ref_dogrula( $o['ref_id'] ) : '';
			if ( '' === $ref || ! isset( $shown_map[ $ref ] ) || isset( $cart_mevcut[ $ref ] ) ) {
				continue;
			}

			$id = isset( $o['item_id'] ) ? absint( $o['item_id'] ) : 0;
			if ( $id < 1 ) {
				continue;
			}

			$kayit = $shown_map[ $ref ];
			if ( (int) $kayit['product_id'] !== $id ) {
				continue;
			}
			if ( (string) $kayit['session_id'] !== $session_id ) {
				continue;
			}

			if ( self::recommendation_event_ekle( $ref, self::REC_EVENT_CART_ADD, $id, $session_id, null ) ) {
				$cart_mevcut[ $ref ] = true;
			}
		}
	}
}
