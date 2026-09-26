<?php
/**
 * Firestore iptal durumunu analitik order_cancelled olayına yansıtır.
 *
 * Mobil QR Servis uygulaması calls/{order_id}.durum alanını doğrudan
 * "iptal" yapabilir; WordPress servis paneli bunu görmeyebilir. Bu yüzden
 * kaynak Firestore belgesidir (call_oku), panelin bildiği durum akışı değil.
 *
 * Listeleme/limit ile tarama yapılmaz: order_id biliniyorsa call_oku(order_id).
 * Aynı order_id için order_cancelled tekildir (uygulama katmanı idempotency).
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'QRMS_Siparis_Iptal_Uzlastirma' ) ) {
	return;
}

/**
 * Sipariş iptali uzlaştırma.
 */
class QRMS_Siparis_Iptal_Uzlastirma {

	/** WP-Cron kancası. */
	const CRON_HOOK = 'qrms_siparis_iptal_uzlastirma';

	/** cron_schedules anahtarı (~5 dakika). */
	const CRON_ARALIK = 'qrms_iptal_uzlastirma';

	/** Cron aralığı (saniye). */
	const ARALIK_SANIYE = 300;

	/** Bir turda azami Firestore GET. */
	const BATCH = 10;

	/** Bir turun süre bütçesi (saniye). */
	const SURE_BUTCE = 8;

	/** Aday yaşam penceresi (saniye). Operasyonel polling sınırı; iptal yasağı değil. */
	const PENCERE_SANIYE = DAY_IN_SECONDS;

	/**
	 * SQL'den çekilecek aday tavanı (GET tavanından ayrı).
	 *
	 * Skip cache en eski siparişleri LIMIT 10'da tıkarsa daha yeni iptaller
	 * hiç GET edilmezdi. Tarama 50, GET hâlâ en fazla 10.
	 */
	const ADAY_TARAMA = 50;

	/** Job kilidi transient anahtarı. */
	const KILIT = 'qrms_iu_job_lock';

	/** Job kilidi TTL (saniye). */
	const KILIT_TTL = 60;

	/** Skip-cache transient öneki (ardına sanitize order_id). */
	const SKIP_ONEK = 'qrms_iu_skip_';

	/**
	 * Non-blocking flock tutamacı (tur süresince).
	 *
	 * @var resource|null
	 */
	private static $kilit_fp = null;

	/**
	 * Bilinen bir order_id için Firestore belgesini okur ve gerekirse
	 * order_cancelled yazar.
	 *
	 * @param string     $order_id Firestore belge kimliği.
	 * @param array|null $belge    Önceden call_oku ile alınmış belge (tekrar GET yok).
	 * @return bool Yazıldıysa veya zaten varsa true; iptal değilse/hata false.
	 */
	public static function uzlastir( $order_id, $belge = null ) {
		$order_id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $order_id );

		if ( '' === $order_id ) {
			return false;
		}

		if ( ! is_array( $belge ) ) {
			if ( ! class_exists( 'QMO_Firestore' ) ) {
				return false;
			}

			$belge = QMO_Firestore::call_oku( $order_id );

			if ( is_wp_error( $belge ) ) {
				return false;
			}
		}

		return self::belgeden_yaz( $order_id, $belge );
	}

	/**
	 * Çözülmüş Firestore belgesinden iptal olayı üretir.
	 *
	 * SAF ağ çağrısı yapmaz; testler doğrudan verir.
	 *
	 * @param string $order_id Belge kimliği.
	 * @param array  $belge    QMO_Firestore::belge_coz() çıktısı.
	 * @return bool
	 */
	public static function belgeden_yaz( $order_id, array $belge ) {
		$order_id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $order_id );

		if ( '' === $order_id ) {
			return false;
		}

		$tip = isset( $belge['tip'] ) ? sanitize_key( (string) $belge['tip'] ) : '';
		if ( '' !== $tip && 'siparis' !== $tip ) {
			return false;
		}

		$durum = isset( $belge['durum'] ) ? sanitize_key( (string) $belge['durum'] ) : '';
		if ( 'iptal' !== $durum ) {
			return false;
		}

		if ( self::iptal_olayi_var_mi( $order_id ) ) {
			return true;
		}

		$yaz = function () use ( $order_id, $belge ) {
			if ( self::iptal_olayi_var_mi( $order_id ) ) {
				return true;
			}

			$kayit = array(
				'event_type' => 'order_cancelled',
				'order_id'   => $order_id,
				'qty'        => 1,
				'reason'     => 'iptal',
				'created_at' => self::iptal_zamani( $belge ),
			);

			if ( ! empty( $belge['masaNo'] ) ) {
				$kayit['masa_no'] = (string) $belge['masaNo'];
			}

			$neden = isset( $belge['iptalNedeni'] ) ? sanitize_key( (string) $belge['iptalNedeni'] ) : '';
			if ( '' !== $neden ) {
				$kayit['reason'] = $neden;
			}

			if ( function_exists( 'qmo_analitik_yaz' ) ) {
				qmo_analitik_yaz( $kayit );
			} elseif ( class_exists( 'QRMS_Analitik' ) ) {
				QRMS_Analitik::kaydet( $kayit );
			} else {
				return false;
			}

			return true;
		};

		if ( function_exists( 'qmo_kilitli_calistir' ) ) {
			return (bool) qmo_kilitli_calistir( 'order_cancelled|' . $order_id, $yaz );
		}

		return (bool) call_user_func( $yaz );
	}

	/**
	 * Bu order_id için order_cancelled zaten var mı?
	 *
	 * idx_order_id ile tek satır; N+1 değil.
	 *
	 * @param string $order_id Sipariş kimliği.
	 * @return bool
	 */
	public static function iptal_olayi_var_mi( $order_id ) {
		if ( ! class_exists( 'QRMS_Analitik' ) ) {
			return false;
		}

		global $wpdb;

		$order_id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $order_id );
		if ( '' === $order_id ) {
			return false;
		}

		$tablo = QRMS_Analitik::tablo();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$var = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$tablo}
				 WHERE event_type = 'order_cancelled' AND order_id = %s
				 LIMIT 1",
				$order_id
			)
		);

		return ! empty( $var );
	}

	/**
	 * İptal olayı zamanı: iptalAt → guncellendi → gözlem anı.
	 *
	 * @param array $belge Firestore belgesi.
	 * @return string MySQL datetime.
	 */
	public static function iptal_zamani( array $belge ) {
		foreach ( array( 'iptalAt', 'guncellendi' ) as $alan ) {
			if ( empty( $belge[ $alan ] ) ) {
				continue;
			}

			$mysql = self::timestamp_mysql( $belge[ $alan ] );
			if ( '' !== $mysql ) {
				return $mysql;
			}
		}

		return current_time( 'mysql' );
	}

	/**
	 * Firestore timestamp / RFC3339 → MySQL datetime.
	 *
	 * @param mixed $deger Ham değer.
	 * @return string
	 */
	public static function timestamp_mysql( $deger ) {
		if ( ! is_scalar( $deger ) ) {
			return '';
		}

		$ham = trim( (string) $deger );
		if ( '' === $ham ) {
			return '';
		}

		$ts = strtotime( $ham );
		if ( false === $ts ) {
			return '';
		}

		$utc = gmdate( 'Y-m-d H:i:s', $ts );

		if ( function_exists( 'get_date_from_gmt' ) ) {
			$yerel = get_date_from_gmt( $utc, 'Y-m-d H:i:s' );
			if ( is_string( $yerel ) && '' !== $yerel ) {
				return $yerel;
			}
		}

		return $utc;
	}

	/* -----------------------------------------------------------------
	   PHASE 3 — otomatik uzlaştırma cron
	----------------------------------------------------------------- */

	/**
	 * Cron kancası, aralık ve planlamayı kaydeder.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_araliklari' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'tur' ) );
		add_action( 'init', array( __CLASS__, 'planla' ), 5 );
	}

	/**
	 * ~5 dakikalık özel WP-Cron aralığı.
	 *
	 * @param array $schedules Mevcut aralıklar.
	 * @return array
	 */
	public static function cron_araliklari( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}

		if ( ! isset( $schedules[ self::CRON_ARALIK ] ) ) {
			$schedules[ self::CRON_ARALIK ] = array(
				'interval' => self::ARALIK_SANIYE,
				'display'  => __( 'QR sipariş iptal uzlaştırma (5 dakika)', 'qrms' ),
			);
		}

		return $schedules;
	}

	/**
	 * Görevi (yoksa) planlar.
	 *
	 * @return void
	 */
	public static function planla() {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		wp_schedule_event( time() + self::ARALIK_SANIYE, self::CRON_ARALIK, self::CRON_HOOK );
	}

	/**
	 * Planı kaldırır (uninstall / deaktivasyon).
	 *
	 * @return void
	 */
	public static function plan_iptal() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * call_oku() sonucunu uzlaştırma katmanında sınıflandırır.
	 *
	 * call_oku davranışını değiştirmez. 404 ve transport iptal değildir.
	 *
	 * @param mixed $sonuc dizi|WP_Error.
	 * @return string found|not_found|transport|auth|config|other
	 */
	public static function oku_sinifi( $sonuc ) {
		if ( ! is_wp_error( $sonuc ) && is_array( $sonuc ) ) {
			return 'found';
		}

		if ( ! is_wp_error( $sonuc ) ) {
			return 'other';
		}

		$kod = $sonuc->get_error_code();

		if ( 'bulunamadi' === $kod ) {
			return 'not_found';
		}

		if ( in_array( $kod, array( 'http_request_failed', 'http_request_not_executed', 'firestore_transport' ), true ) ) {
			return 'transport';
		}

		if ( in_array( $kod, array( 'sa', 'sign', 'token' ), true ) ) {
			return 'auth';
		}

		if ( 'proje' === $kod ) {
			return 'config';
		}

		if ( 'firestore' === $kod ) {
			$mesaj = $sonuc->get_error_message();
			if ( is_string( $mesaj ) && preg_match( '/\((401|403)\)/', $mesaj ) ) {
				return 'auth';
			}

			return 'other';
		}

		return 'other';
	}

	/**
	 * Penceredeki uzlaştırılmamış order_sent adayları — tek sorgu.
	 *
	 * @param int $limit SQL tavanı.
	 * @return array<int,array{order_id:string,ilk_gonderim:string}>
	 */
	public static function adaylar( $limit = self::ADAY_TARAMA ) {
		if ( ! class_exists( 'QRMS_Analitik' ) ) {
			return array();
		}

		global $wpdb;

		$limit = max( 1, (int) $limit );
		$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - self::PENCERE_SANIYE );
		$tablo = QRMS_Analitik::tablo();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$satirlar = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.order_id, MIN(s.created_at) AS ilk_gonderim
				 FROM {$tablo} s
				 WHERE s.event_type = 'order_sent'
				   AND s.order_id IS NOT NULL
				   AND s.order_id <> ''
				   AND s.created_at >= %s
				   AND NOT EXISTS (
					 SELECT 1
					   FROM {$tablo} c
					  WHERE c.event_type = 'order_cancelled'
					    AND c.order_id = s.order_id
				   )
				 GROUP BY s.order_id
				 ORDER BY ilk_gonderim ASC
				 LIMIT %d",
				$since,
				$limit
			),
			ARRAY_A
		);

		$cikti = array();

		foreach ( (array) $satirlar as $satir ) {
			if ( ! is_array( $satir ) ) {
				continue;
			}

			$order_id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $satir['order_id'] ?? '' ) );
			if ( '' === $order_id ) {
				continue;
			}

			$cikti[] = array(
				'order_id'      => $order_id,
				'ilk_gonderim'  => isset( $satir['ilk_gonderim'] ) ? (string) $satir['ilk_gonderim'] : '',
			);
		}

		return $cikti;
	}

	/**
	 * Bir cron turu: adaylar → call_oku → gerekirse belgeden_yaz.
	 *
	 * @return array<string,mixed>
	 */
	public static function tur() {
		$ozet = array(
			'kilit'  => false,
			'aday'   => 0,
			'get'    => 0,
			'yazilan' => 0,
			'kesme'  => '',
		);

		if ( ! self::kilit_al() ) {
			$ozet['kesme'] = 'kilit';
			return $ozet;
		}

		$ozet['kilit'] = true;

		try {
			$batch = (int) apply_filters( 'qrms_iptal_uzlastirma_batch', self::BATCH );
			$batch = max( 1, min( self::BATCH, $batch ) );

			$butce = (float) apply_filters( 'qrms_iptal_uzlastirma_sure', self::SURE_BUTCE );
			if ( $butce < 0 ) {
				$butce = 0;
			}
			if ( $butce > 10 ) {
				$butce = 10;
			}

			$tarama = (int) apply_filters( 'qrms_iptal_uzlastirma_aday_sinir', self::ADAY_TARAMA );
			$tarama = max( $batch, min( 100, $tarama ) );

			$adaylar     = self::adaylar( $tarama );
			$ozet['aday'] = count( $adaylar );
			$bas         = self::simdi();

			foreach ( $adaylar as $satir ) {
				if ( ( self::simdi() - $bas ) >= $butce ) {
					$ozet['kesme'] = 'sure';
					break;
				}

				$order_id = $satir['order_id'];

				if ( self::skip_var_mi( $order_id ) ) {
					continue;
				}

				if ( $ozet['get'] >= $batch ) {
					break;
				}

				if ( ! class_exists( 'QMO_Firestore' ) ) {
					$ozet['kesme'] = 'config';
					break;
				}

				$belge = QMO_Firestore::call_oku( $order_id );
				++$ozet['get'];

				$sinif = self::oku_sinifi( $belge );

				if ( 'auth' === $sinif ) {
					$ozet['kesme'] = 'auth';
					break;
				}

				if ( 'config' === $sinif ) {
					$ozet['kesme'] = 'config';
					break;
				}

				if ( 'transport' === $sinif ) {
					continue;
				}

				if ( 'not_found' === $sinif ) {
					self::skip_yaz( $order_id, self::ARALIK_SANIYE );
					continue;
				}

				if ( 'found' !== $sinif || ! is_array( $belge ) ) {
					self::skip_yaz( $order_id, self::ARALIK_SANIYE );
					continue;
				}

				$tip   = isset( $belge['tip'] ) ? sanitize_key( (string) $belge['tip'] ) : '';
				$durum = isset( $belge['durum'] ) ? sanitize_key( (string) $belge['durum'] ) : '';

				if ( 'iptal' === $durum && ( '' === $tip || 'siparis' === $tip ) ) {
					if ( self::belgeden_yaz( $order_id, $belge ) ) {
						++$ozet['yazilan'];
					}
					continue;
				}

				if ( in_array( $durum, array( 'bekliyor', 'hazirlaniyor', 'serviste' ), true ) ) {
					self::skip_yaz( $order_id, self::ARALIK_SANIYE );
					continue;
				}

				if ( 'tamamlandi' === $durum ) {
					self::skip_yaz( $order_id, self::skip_ttl_tamamlandi( $satir['ilk_gonderim'] ) );
					continue;
				}

				self::skip_yaz( $order_id, self::ARALIK_SANIYE );
			}
		} finally {
			self::kilit_birak();
		}

		return $ozet;
	}

	/**
	 * Tur saatini döner (testler filtreyle sahteleyebilir).
	 *
	 * @return float
	 */
	public static function simdi() {
		return (float) apply_filters( 'qrms_iptal_uzlastirma_simdi', microtime( true ) );
	}

	/**
	 * Non-blocking job kilidi: transient + flock LOCK_NB.
	 *
	 * Alınamazsa false (ikinci job no-op). Batch qmo_kilitli_calistir ile
	 * blocking sarılmaz.
	 *
	 * @return bool
	 */
	public static function kilit_al() {
		if ( false !== get_transient( self::KILIT ) ) {
			return false;
		}

		$dizin = rtrim( function_exists( 'get_temp_dir' ) ? get_temp_dir() : sys_get_temp_dir(), '/\\' );
		$dosya = $dizin . '/qrms-iu-job.lock';
		$fp    = @fopen( $dosya, 'c' );

		if ( $fp ) {
			if ( ! flock( $fp, LOCK_EX | LOCK_NB ) ) {
				fclose( $fp );
				return false;
			}

			if ( false !== get_transient( self::KILIT ) ) {
				flock( $fp, LOCK_UN );
				fclose( $fp );
				return false;
			}

			self::$kilit_fp = $fp;
		}

		set_transient( self::KILIT, 1, self::KILIT_TTL );

		return true;
	}

	/**
	 * Job kilidini bırakır.
	 *
	 * @return void
	 */
	public static function kilit_birak() {
		delete_transient( self::KILIT );

		if ( is_resource( self::$kilit_fp ) ) {
			flock( self::$kilit_fp, LOCK_UN );
			fclose( self::$kilit_fp );
		}

		self::$kilit_fp = null;
	}

	/**
	 * Skip-cache anahtarı.
	 *
	 * @param string $order_id Sipariş kimliği.
	 * @return string
	 */
	public static function skip_anahtar( $order_id ) {
		$order_id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $order_id );

		return self::SKIP_ONEK . $order_id;
	}

	/**
	 * Bu order_id skip-cache'te mi?
	 *
	 * @param string $order_id Sipariş kimliği.
	 * @return bool
	 */
	public static function skip_var_mi( $order_id ) {
		$anahtar = self::skip_anahtar( $order_id );
		if ( self::SKIP_ONEK === $anahtar ) {
			return false;
		}

		return false !== get_transient( $anahtar );
	}

	/**
	 * Skip-cache yazar.
	 *
	 * @param string $order_id Sipariş kimliği.
	 * @param int    $ttl      Saniye.
	 * @return void
	 */
	public static function skip_yaz( $order_id, $ttl ) {
		$anahtar = self::skip_anahtar( $order_id );
		if ( self::SKIP_ONEK === $anahtar ) {
			return;
		}

		$ttl = max( 1, (int) $ttl );
		set_transient( $anahtar, 1, $ttl );
	}

	/**
	 * tamamlandi için pencere sonuna kadar skip TTL.
	 *
	 * @param string $ilk_gonderim Adayın ilk order_sent zamanı.
	 * @return int
	 */
	public static function skip_ttl_tamamlandi( $ilk_gonderim ) {
		$ilk_ts = strtotime( (string) $ilk_gonderim );
		$simdi  = current_time( 'timestamp' );

		if ( false === $ilk_ts ) {
			return self::PENCERE_SANIYE;
		}

		$kalan = ( $ilk_ts + self::PENCERE_SANIYE ) - $simdi;

		return (int) max( self::ARALIK_SANIYE, min( self::PENCERE_SANIYE, $kalan ) );
	}
}
