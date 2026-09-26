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
}
