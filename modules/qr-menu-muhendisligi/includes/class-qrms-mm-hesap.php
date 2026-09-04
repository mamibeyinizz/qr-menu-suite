<?php
/**
 * Menü mühendisliği matris hesabı (Kasavana–Smith).
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QRMS_MM_Hesap' ) ) {

	/**
	 * Saf hesap sınıfı — veritabanına dokunmaz, test edilebilir.
	 */
	class QRMS_MM_Hesap {

		const KUTU_YILDIZ   = 'yildiz';
		const KUTU_IS_ATI   = 'is_ati';
		const KUTU_BULMACA  = 'bulmaca';
		const KUTU_KOPEK    = 'kopek';

		/**
		 * Kutu aksiyon cümleleri.
		 *
		 * @return array<string,string>
		 */
		public static function aksiyonlar() {
			return array(
				self::KUTU_YILDIZ  => __( 'Koru. Fiyatı ve porsiyonu değiştirme, menünün en üstünde ve vitrinde tut.', 'qrms' ),
				self::KUTU_IS_ATI  => __( 'Çok satıyor ama az kazandırıyor. Maliyeti düşür veya %5–10 zam dene.', 'qrms' ),
				self::KUTU_BULMACA => __( 'Kârlı ama görünmüyor. Vitrine al, önerilere ekle, adını ve fotoğrafını güçlendir.', 'qrms' ),
				self::KUTU_KOPEK   => __( 'Menüden çıkarmayı değerlendir; kalacaksa fiyatı yükselt veya reçeteyi ucuzlat.', 'qrms' ),
			);
		}

		/**
		 * Kutu etiketleri.
		 *
		 * @return array<string,string>
		 */
		public static function kutu_etiketleri() {
			return array(
				self::KUTU_YILDIZ  => __( 'Yıldız', 'qrms' ),
				self::KUTU_IS_ATI  => __( 'İş Atı', 'qrms' ),
				self::KUTU_BULMACA => __( 'Bulmaca', 'qrms' ),
				self::KUTU_KOPEK   => __( 'Köpek', 'qrms' ),
			);
		}

		/**
		 * Rapor hesaplar.
		 *
		 * @param string $bas       Başlangıç tarihi (Y-m-d H:i:s).
		 * @param string $bit       Bitiş tarihi.
		 * @param string $kategori  Kategori filtresi (boş = tümü).
		 * @param array  $urunler   Ürün meta: id, ad, kategori, fiyat, maliyet.
		 * @param array  $analitik  SQL satırları: item_id, satis, sepet, tik.
		 * @param array  $ayarlar   populerlik_esigi, kdv_dahil.
		 * @return array
		 */
		public static function hesapla( $bas, $bit, $kategori, array $urunler, array $analitik, array $ayarlar = array() ) {
			$pop_esik = isset( $ayarlar['populerlik_esigi'] ) ? (float) $ayarlar['populerlik_esigi'] : 0.70;
			$pop_esik = max( 0.5, min( 1.0, $pop_esik ) );

			$anal_map = array();
			$toplam_satis = 0;
			foreach ( $analitik as $satir ) {
				$id = (int) ( $satir['item_id'] ?? 0 );
				if ( $id <= 0 ) {
					continue;
				}
				$anal_map[ $id ] = array(
					'satis' => (int) ( $satir['satis'] ?? 0 ),
					'sepet' => (int) ( $satir['sepet'] ?? 0 ),
					'tik'   => (int) ( $satir['tik'] ?? 0 ),
				);
				$toplam_satis += (int) ( $satir['satis'] ?? 0 );
			}

			$kaynak  = 'siparis';
			$uyari   = '';
			if ( $toplam_satis < 20 ) {
				$kaynak = 'vekil';
				$uyari  = __( 'Yeterli sipariş verisi yok; rapor görüntülenme ve sepet verisiyle tahmin ediliyor.', 'qrms' );
			}

			$eksik   = array();
			$adaylar = array();

			foreach ( $urunler as $u ) {
				$id = (int) ( $u['id'] ?? 0 );
				if ( $id <= 0 ) {
					continue;
				}
				if ( '' !== $kategori && ( $u['kategori'] ?? '' ) !== $kategori ) {
					continue;
				}

				$fiyat   = (float) ( $u['fiyat'] ?? 0 );
				$maliyet = (float) ( $u['maliyet'] ?? 0 );

				if ( $fiyat <= 0 || $maliyet < 0 ) {
					$eksik[] = array(
						'id'       => $id,
						'ad'       => (string) ( $u['ad'] ?? '' ),
						'kategori' => (string) ( $u['kategori'] ?? '' ),
						'fiyat'    => $fiyat,
						'maliyet'  => $maliyet,
					);
					continue;
				}

				$a = isset( $anal_map[ $id ] ) ? $anal_map[ $id ] : array( 'satis' => 0, 'sepet' => 0, 'tik' => 0 );

				if ( 'siparis' === $kaynak ) {
					$q = (int) $a['satis'];
				} else {
					$q = (int) $a['tik'] + ( (int) $a['sepet'] * 3 );
				}

				$cm = $fiyat - $maliyet;

				$adaylar[] = array(
					'id'       => $id,
					'ad'       => (string) ( $u['ad'] ?? '' ),
					'kategori' => (string) ( $u['kategori'] ?? '' ),
					'fiyat'    => $fiyat,
					'maliyet'  => $maliyet,
					'cm'       => $cm,
					'q'        => $q,
					'satis'    => (int) $a['satis'],
					'sepet'    => (int) $a['sepet'],
					'tik'      => (int) $a['tik'],
				);
			}

			$n = count( $adaylar );
			if ( 0 === $n ) {
				return array(
					'kaynak'  => $kaynak,
					'uyari'   => $uyari,
					'urunler' => array(),
					'eksik'   => $eksik,
					'ozet'    => array(
						'toplam_ciro'       => 0,
						'toplam_katkı'      => 0,
						'ortalama_marj'     => 0,
						'kutu_sayilari'     => array(),
						'kayip_firsat'      => 0,
					),
					'kutular' => array(
						self::KUTU_YILDIZ  => array(),
						self::KUTU_IS_ATI  => array(),
						self::KUTU_BULMACA => array(),
						self::KUTU_KOPEK   => array(),
					),
				);
			}

			$toplam_q = 0;
			$toplam_cm_q = 0;
			foreach ( $adaylar as $u ) {
				$toplam_q    += $u['q'];
				$toplam_cm_q += $u['cm'] * $u['q'];
			}

			$ortalama_cm = $toplam_q > 0 ? $toplam_cm_q / $toplam_q : 0;
			$pop_threshold = ( 1 / $n ) * $pop_esik;

			$aksiyonlar = self::aksiyonlar();
			$sonuc      = array();
			$kutular    = array(
				self::KUTU_YILDIZ  => array(),
				self::KUTU_IS_ATI  => array(),
				self::KUTU_BULMACA => array(),
				self::KUTU_KOPEK   => array(),
			);

			$toplam_ciro  = 0;
			$toplam_katki = 0;
			$kayip        = 0;

			foreach ( $adaylar as $u ) {
				$ms = $toplam_q > 0 ? $u['q'] / $toplam_q : 0;
				$yuksek_pop = $ms >= $pop_threshold;
				$yuksek_kar = $u['cm'] >= $ortalama_cm;

				if ( $yuksek_pop && $yuksek_kar ) {
					$kutu = self::KUTU_YILDIZ;
				} elseif ( $yuksek_pop && ! $yuksek_kar ) {
					$kutu = self::KUTU_IS_ATI;
				} elseif ( ! $yuksek_pop && $yuksek_kar ) {
					$kutu = self::KUTU_BULMACA;
				} else {
					$kutu = self::KUTU_KOPEK;
				}

				$ciro  = $u['fiyat'] * $u['q'];
				$katki = $u['cm'] * $u['q'];
				$marj  = $u['fiyat'] > 0 ? ( $u['cm'] / $u['fiyat'] ) * 100 : 0;

				$toplam_ciro  += $ciro;
				$toplam_katki += $katki;

				if ( self::KUTU_KOPEK === $kutu || self::KUTU_IS_ATI === $kutu ) {
					$kayip += ( $ortalama_cm - $u['cm'] ) * $u['q'];
				}

				$satir = array(
					'id'       => $u['id'],
					'ad'       => $u['ad'],
					'kategori' => $u['kategori'],
					'fiyat'    => $u['fiyat'],
					'maliyet'  => $u['maliyet'],
					'cm'       => $u['cm'],
					'q'        => $u['q'],
					'satis'    => $u['satis'],
					'ciro'     => $ciro,
					'katki'    => $katki,
					'marj'     => $marj,
					'ms'       => $ms,
					'kutu'     => $kutu,
					'aksiyon'  => $aksiyonlar[ $kutu ] ?? '',
				);

				$sonuc[] = $satir;
				$kutular[ $kutu ][] = $satir;
			}

			$ortalama_marj = $toplam_ciro > 0 ? ( $toplam_katki / $toplam_ciro ) * 100 : 0;

			return array(
				'kaynak'  => $kaynak,
				'uyari'   => $uyari,
				'urunler' => $sonuc,
				'eksik'   => $eksik,
				'ozet'    => array(
					'toplam_ciro'   => $toplam_ciro,
					'toplam_katkı'  => $toplam_katki,
					'ortalama_marj' => $ortalama_marj,
					'kutu_sayilari' => array(
						self::KUTU_YILDIZ  => count( $kutular[ self::KUTU_YILDIZ ] ),
						self::KUTU_IS_ATI  => count( $kutular[ self::KUTU_IS_ATI ] ),
						self::KUTU_BULMACA => count( $kutular[ self::KUTU_BULMACA ] ),
						self::KUTU_KOPEK   => count( $kutular[ self::KUTU_KOPEK ] ),
					),
					'kayip_firsat' => max( 0, $kayip ),
				),
				'kutular' => $kutular,
			);
		}

		/**
		 * Analitik verisini tek SQL ile çeker.
		 *
		 * @param string $bas Başlangıç.
		 * @param string $bit Bitiş.
		 * @return array
		 */
		public static function analitik_cek( $bas, $bit ) {
			global $wpdb;

			if ( ! class_exists( 'QRMS_Analitik' ) || ! QRMS_Analitik::tablo_var_mi() ) {
				return array();
			}

			$tablo = QRMS_Analitik::tablo();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT item_id, item_name, category_name,
						SUM(CASE WHEN event_type='order_sent' THEN qty ELSE 0 END) AS satis,
						SUM(CASE WHEN event_type='cart_add' THEN 1 ELSE 0 END) AS sepet,
						SUM(CASE WHEN event_type='product_click' THEN 1 ELSE 0 END) AS tik
					FROM {$tablo}
					WHERE created_at BETWEEN %s AND %s AND item_id > 0
					GROUP BY item_id",
					$bas,
					$bit
				),
				ARRAY_A
			);

			return is_array( $rows ) ? $rows : array();
		}
	}
}
