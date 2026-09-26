<?php
/**
 * QR sipariş olguları — order_id bazlı, salt okunur kesin KPI'lar.
 *
 * Phase 1 sözleşme alanları (order_id, unit_price) olmadan yazılmış
 * satırlar burada "legacy / approximate" sayılır ve yeni kesin
 * sayılara karıştırılmaz. Sepet ekranının oturum bazlı özeti
 * (qrms_analitik_sepet_hesapla) bu sınıfa taşınmaz.
 *
 * Gerçek sipariş: COUNT(DISTINCT order_id) — order_sent satır sayısı değil.
 * QR sipariş tutarı: SUM(unit_price * qty); iptal edilen order_id'ler hariç.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'QRMS_Siparis_Olgulari' ) ) {
	return;
}

/**
 * Salt okunur sipariş gerçekleri.
 */
class QRMS_Siparis_Olgulari {

	/**
	 * Boş kesin özet.
	 *
	 * @return array<string,mixed>
	 */
	public static function bos_ozet() {
		return array(
			'siparis_sayisi'  => 0,
			'siparis_tutari'  => 0.0,
			'iptal_sayisi'    => 0,
			'iptal_tutari'    => 0.0,
			'kaynak'          => 'kesin',
			'legacy_haric'    => true,
		);
	}

	/**
	 * Ham olay satırlarından kesin sipariş olgularını üretir.
	 *
	 * Veritabanına gitmez; ozet() tek aggregate sorgunun çıktısını buraya verir.
	 * order_id'si boş satırlar (legacy) ve unit_price'sız kalemler sessizce
	 * ilgili kesin metriğe dahil edilmez.
	 *
	 * @param array<int,array<string,mixed>> $satirlar order_sent / order_cancelled satırları.
	 * @return array<string,mixed>
	 */
	public static function hesapla( array $satirlar ) {
		$siparisler = array();

		foreach ( $satirlar as $satir ) {
			if ( ! is_array( $satir ) ) {
				continue;
			}

			$order_id = isset( $satir['order_id'] ) ? trim( (string) $satir['order_id'] ) : '';
			if ( '' === $order_id ) {
				continue;
			}

			if ( ! isset( $siparisler[ $order_id ] ) ) {
				$siparisler[ $order_id ] = array(
					'iptal' => false,
					'tutar' => 0.0,
					'satir' => 0,
				);
			}

			$tip = isset( $satir['event_type'] ) ? sanitize_key( (string) $satir['event_type'] ) : '';

			if ( 'order_cancelled' === $tip ) {
				$siparisler[ $order_id ]['iptal'] = true;
				continue;
			}

			if ( 'order_sent' !== $tip ) {
				continue;
			}

			++$siparisler[ $order_id ]['satir'];

			if ( ! array_key_exists( 'unit_price', $satir ) || null === $satir['unit_price'] || '' === $satir['unit_price'] ) {
				continue;
			}

			$adet = isset( $satir['qty'] ) ? max( 1, (int) $satir['qty'] ) : 1;
			$siparisler[ $order_id ]['tutar'] += (float) $satir['unit_price'] * $adet;
		}

		$ozet = self::bos_ozet();

		foreach ( $siparisler as $olgu ) {
			if ( $olgu['satir'] < 1 ) {
				if ( $olgu['iptal'] ) {
					++$ozet['iptal_sayisi'];
				}
				continue;
			}

			if ( $olgu['iptal'] ) {
				++$ozet['iptal_sayisi'];
				$ozet['iptal_tutari'] += $olgu['tutar'];
				continue;
			}

			++$ozet['siparis_sayisi'];
			$ozet['siparis_tutari'] += $olgu['tutar'];
		}

		$ozet['siparis_tutari'] = round( (float) $ozet['siparis_tutari'], 2 );
		$ozet['iptal_tutari']   = round( (float) $ozet['iptal_tutari'], 2 );

		return $ozet;
	}

	/**
	 * Tarih aralığındaki kesin sipariş KPI'ları — tek sorgu.
	 *
	 * idx_td (event_type, created_at) sipariş satırlarını aralık tarar;
	 * iptaller order_id ile idx_order_id üzerinden bağlanır. SELECT * ve
	 * sipariş başına ek sorgu yoktur.
	 *
	 * @param string $bas  Aralık başlangıcı (MySQL biçimi).
	 * @param string $bit  Aralık bitişi (MySQL biçimi).
	 * @param string $masa Masa filtresi (boş = tüm masalar).
	 * @return array<string,mixed>
	 */
	public static function ozet( $bas, $bit, $masa = '' ) {
		if ( ! class_exists( 'QRMS_Analitik' ) ) {
			return self::bos_ozet();
		}

		global $wpdb;

		$tablo   = QRMS_Analitik::tablo();
		$masa_ek = self::masa_sql( $masa );
		$aralik  = $wpdb->prepare( 's.created_at BETWEEN %s AND %s', $bas, $bit );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$satirlar = $wpdb->get_results(
			"SELECT s.order_id, s.event_type, s.qty, s.unit_price,
				c.order_id AS iptal_order_id
			 FROM {$tablo} s
			 LEFT JOIN (
				SELECT DISTINCT order_id
				  FROM {$tablo}
				 WHERE event_type = 'order_cancelled'
				   AND order_id IS NOT NULL AND order_id <> ''
			 ) c ON c.order_id = s.order_id
			 WHERE s.event_type = 'order_sent'
			   AND s.order_id IS NOT NULL AND s.order_id <> ''
			   AND {$aralik}{$masa_ek}",
			ARRAY_A
		);

		return self::hesapla( self::join_satirlarini_normalize_et( (array) $satirlar ) );
	}

	/**
	 * ozet() JOIN çıktısını hesapla() girdisine çevirir.
	 *
	 * @param array<int,array<string,mixed>> $satirlar Ham sorgu satırları.
	 * @return array<int,array<string,mixed>>
	 */
	public static function join_satirlarini_normalize_et( array $satirlar ) {
		$cikti = array();
		$iptal = array();

		foreach ( $satirlar as $satir ) {
			if ( ! is_array( $satir ) ) {
				continue;
			}

			$order_id = isset( $satir['order_id'] ) ? trim( (string) $satir['order_id'] ) : '';
			if ( '' === $order_id ) {
				continue;
			}

			$cikti[] = array(
				'order_id'   => $order_id,
				'event_type' => isset( $satir['event_type'] ) ? $satir['event_type'] : 'order_sent',
				'qty'        => isset( $satir['qty'] ) ? $satir['qty'] : 1,
				'unit_price' => array_key_exists( 'unit_price', $satir ) ? $satir['unit_price'] : null,
			);

			$iptal_mi = false;
			if ( array_key_exists( 'iptal', $satir ) ) {
				$iptal_mi = (int) $satir['iptal'] > 0;
			} elseif ( ! empty( $satir['iptal_order_id'] ) ) {
				$iptal_mi = true;
			} elseif ( isset( $satir['c_order_id'] ) && '' !== (string) $satir['c_order_id'] ) {
				$iptal_mi = true;
			}

			if ( $iptal_mi ) {
				$iptal[ $order_id ] = true;
			}
		}

		foreach ( array_keys( $iptal ) as $order_id ) {
			$cikti[] = array(
				'order_id'   => $order_id,
				'event_type' => 'order_cancelled',
			);
		}

		return $cikti;
	}

	/**
	 * Tek bir order_id için olgular.
	 *
	 * @param string $order_id Firestore belge kimliği / analitik order_id.
	 * @return array<string,mixed>
	 */
	public static function order_id_icin( $order_id ) {
		$order_id = substr( sanitize_text_field( (string) $order_id ), 0, 36 );

		$bos = array(
			'order_id'       => $order_id,
			'kaynak'         => '' === $order_id ? 'yok' : 'kesin',
			'iptal'          => false,
			'satir_sayisi'   => 0,
			'siparis_tutari' => 0.0,
			'legacy'         => false,
		);

		if ( '' === $order_id || ! class_exists( 'QRMS_Analitik' ) ) {
			return $bos;
		}

		global $wpdb;

		$tablo = QRMS_Analitik::tablo();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$satirlar = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_type, qty, unit_price, order_id
				 FROM {$tablo}
				 WHERE order_id = %s
				   AND event_type IN ('order_sent','order_cancelled')",
				$order_id
			),
			ARRAY_A
		);

		$ozet = self::hesapla( (array) $satirlar );

		$bos['iptal']          = $ozet['iptal_sayisi'] > 0;
		$bos['satir_sayisi']   = 0;
		$bos['siparis_tutari'] = $bos['iptal'] ? $ozet['iptal_tutari'] : $ozet['siparis_tutari'];

		foreach ( (array) $satirlar as $satir ) {
			if ( is_array( $satir ) && 'order_sent' === ( $satir['event_type'] ?? '' ) ) {
				++$bos['satir_sayisi'];
			}
		}

		if ( $bos['iptal'] ) {
			$bos['siparis_sayisi'] = 0;
		} else {
			$bos['siparis_sayisi'] = $ozet['siparis_sayisi'];
		}

		return $bos;
	}

	/**
	 * Masa filtresi SQL parçası (idx_masa_td ile uyumlu).
	 *
	 * @param string $masa Masa slug'ı.
	 * @return string
	 */
	private static function masa_sql( $masa ) {
		global $wpdb;

		if ( '' === $masa ) {
			return '';
		}

		return $wpdb->prepare( ' AND s.masa_no = %s', $masa );
	}
}
