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

	const SURUM = '1.2';
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
		$canli    = self::canli_tablosu();
		$personel = self::personel_mesaj_tablosu();

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
	 * Tarih aralığında ürün bazlı öneri raporu (sayılar ve dönüşüm oranı).
	 *
	 * @param string $bas Başlangıç tarihi (Y-m-d).
	 * @param string $bit Bitiş tarihi (Y-m-d).
	 * @return array
	 */
	public static function oneri_rapor( $bas, $bit ) {
		global $wpdb;

		self::sema_kontrol();

		$tablo = self::oneri_log_tablosu();
		$bas   = sanitize_text_field( $bas ) . ' 00:00:00';
		$bit   = sanitize_text_field( $bit ) . ' 23:59:59';

		$satirlar = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT urun_id,
					SUM(CASE WHEN durum = 'gosterildi' THEN 1 ELSE 0 END) AS gosterildi,
					SUM(CASE WHEN durum = 'sepete' THEN 1 ELSE 0 END) AS sepete,
					SUM(CASE WHEN durum = 'siparis' THEN 1 ELSE 0 END) AS siparis
				FROM {$tablo}
				WHERE created_at >= %s AND created_at <= %s
				GROUP BY urun_id
				ORDER BY urun_id ASC",
				$bas,
				$bit
			)
		);

		if ( ! is_array( $satirlar ) ) {
			return array();
		}

		$rapor = array();
		foreach ( $satirlar as $satir ) {
			$gosterildi = (int) $satir->gosterildi;
			$sepete     = (int) $satir->sepete;
			$siparis    = (int) $satir->siparis;
			$rapor[]    = array(
				'urun_id'        => (int) $satir->urun_id,
				'gosterildi'     => $gosterildi,
				'sepete'         => $sepete,
				'siparis'        => $siparis,
				'donusum_orani'  => $gosterildi > 0 ? round( $siparis / $gosterildi, 4 ) : 0.0,
			);
		}

		return $rapor;
	}
}
