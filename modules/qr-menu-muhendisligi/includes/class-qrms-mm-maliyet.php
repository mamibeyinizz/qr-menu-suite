<?php
/**
 * Menü mühendisliği maliyet ve reçete yönetimi.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QRMS_MM_Maliyet' ) ) {

	/**
	 * Ürün maliyeti, reçete ve malzeme fiyatları.
	 */
	class QRMS_MM_Maliyet {

		const META_MALIYET       = '_qrms_mm_maliyet';
		const META_KAYNAK        = '_qrms_mm_maliyet_kaynak';
		const META_RECETE        = '_qrms_mm_recete';
		const OPT_MALZEME_FIYAT  = 'qrms_mm_malzeme_fiyat';
		const OPT_AYARLAR        = 'qrms_mm_ayarlar';
		const OPT_RAPOR_BUST     = 'qrms_mm_rapor_bust';

		/**
		 * Varsayılan ayarlar.
		 *
		 * @return array
		 */
		public static function varsayilan_ayarlar() {
			return array(
				'populerlik_esigi' => 0.70,
				'kdv_dahil'        => 0,
				'varsayilan_aralik' => 30,
				'fire_yuzdesi'     => 0,
			);
		}

		/**
		 * Ayarları okur.
		 *
		 * @return array
		 */
		public static function ayarlar() {
			$opt = get_option( self::OPT_AYARLAR, array() );
			if ( ! is_array( $opt ) ) {
				$opt = array();
			}
			return array_merge( self::varsayilan_ayarlar(), $opt );
		}

		/**
		 * Ayarları kaydeder.
		 *
		 * @param array $ayarlar Ayar dizisi.
		 * @return void
		 */
		public static function ayarlari_kaydet( array $ayarlar ) {
			$mevcut = self::ayarlar();
			$yeni   = array_merge( $mevcut, $ayarlar );
			$yeni['populerlik_esigi']  = max( 0.5, min( 1.0, (float) ( $yeni['populerlik_esigi'] ?? 0.70 ) ) );
			$yeni['fire_yuzdesi']      = max( 0, min( 100, (float) ( $yeni['fire_yuzdesi'] ?? 0 ) ) );
			$yeni['varsayilan_aralik'] = max( 7, min( 365, absint( $yeni['varsayilan_aralik'] ?? 30 ) ) );
			$yeni['kdv_dahil']         = ! empty( $yeni['kdv_dahil'] ) ? 1 : 0;
			update_option( self::OPT_AYARLAR, $yeni, false );
			self::rapor_onbellegini_temizle();
		}

		/**
		 * Malzeme fiyatlarını okur.
		 *
		 * @return array
		 */
		public static function malzeme_fiyatlari() {
			$opt = get_option( self::OPT_MALZEME_FIYAT, array() );
			return is_array( $opt ) ? $opt : array();
		}

		/**
		 * Malzeme fiyatlarını kaydeder ve reçeteli ürünleri yeniden hesaplar.
		 *
		 * @param array $fiyatlar term_id => [birim, fiyat].
		 * @return void
		 */
		public static function malzeme_fiyatlari_kaydet( array $fiyatlar ) {
			$temiz = array();
			foreach ( $fiyatlar as $tid => $veri ) {
				$tid = absint( $tid );
				if ( $tid <= 0 || ! is_array( $veri ) ) {
					continue;
				}
				$birim = in_array( $veri['birim'] ?? '', array( 'kg', 'lt', 'adet' ), true ) ? $veri['birim'] : 'kg';
				$temiz[ $tid ] = array(
					'birim' => $birim,
					'fiyat' => max( 0, (float) ( $veri['fiyat'] ?? 0 ) ),
				);
			}
			update_option( self::OPT_MALZEME_FIYAT, $temiz, false );
			self::recete_maliyetlerini_yenile();
			self::rapor_onbellegini_temizle();
		}

		/**
		 * Reçete tabanlı ürün maliyetlerini toplu yeniler.
		 *
		 * @return void
		 */
		public static function recete_maliyetlerini_yenile() {
			$ids = get_posts(
				array(
					'post_type'      => 'rma_menu_item',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'   => self::META_KAYNAK,
							'value' => 'recete',
						),
					),
				)
			);

			if ( count( $ids ) > 500 ) {
				wp_schedule_single_event( time() + 5, 'qrms_mm_recete_yenile' );
				return;
			}

			foreach ( $ids as $id ) {
				$recete = self::recete_oku( $id );
				if ( ! empty( $recete ) ) {
					self::maliyet_kaydet( $id, self::receteden_hesapla( $recete ), 'recete', $recete );
				}
			}
		}

		/**
		 * Reçeteden maliyet hesaplar.
		 *
		 * @param array $recete [ [term_id, miktar], … ].
		 * @return float
		 */
		public static function receteden_hesapla( array $recete ) {
			$fiyatlar = self::malzeme_fiyatlari();
			$ayarlar  = self::ayarlar();
			$fire     = (float) ( $ayarlar['fire_yuzdesi'] ?? 0 );
			$toplam   = 0.0;

			foreach ( $recete as $satir ) {
				if ( ! is_array( $satir ) ) {
					continue;
				}
				$tid    = absint( $satir['term_id'] ?? 0 );
				$miktar = (float) ( $satir['miktar'] ?? 0 );
				if ( $tid <= 0 || $miktar <= 0 ) {
					continue;
				}
				$mf = isset( $fiyatlar[ $tid ] ) ? $fiyatlar[ $tid ] : array();
				$birim = $mf['birim'] ?? 'kg';
				$fiyat = (float) ( $mf['fiyat'] ?? 0 );

				if ( 'adet' === $birim ) {
					$toplam += $miktar * $fiyat;
				} else {
					$toplam += ( $miktar / 1000 ) * $fiyat;
				}
			}

			if ( $fire > 0 ) {
				$toplam *= 1 + ( $fire / 100 );
			}

			return round( $toplam, 2 );
		}

		/**
		 * Ürün reçetesini okur.
		 *
		 * @param int $post_id Ürün kimliği.
		 * @return array
		 */
		public static function recete_oku( $post_id ) {
			$raw = get_post_meta( absint( $post_id ), self::META_RECETE, true );
			return is_array( $raw ) ? $raw : array();
		}

		/**
		 * Ürün maliyetini okur.
		 *
		 * @param int $post_id Ürün kimliği.
		 * @return float
		 */
		public static function maliyet_oku( $post_id ) {
			return (float) get_post_meta( absint( $post_id ), self::META_MALIYET, true );
		}

		/**
		 * Ürün maliyet kaynağını okur.
		 *
		 * @param int $post_id Ürün kimliği.
		 * @return string manuel|recete.
		 */
		public static function kaynak_oku( $post_id ) {
			$k = (string) get_post_meta( absint( $post_id ), self::META_KAYNAK, true );
			return in_array( $k, array( 'manuel', 'recete' ), true ) ? $k : 'manuel';
		}

		/**
		 * Maliyet kaydeder.
		 *
		 * @param int    $post_id Ürün kimliği.
		 * @param float  $maliyet Maliyet (₺).
		 * @param string $kaynak  manuel|recete.
		 * @param array  $recete  Reçete (opsiyonel).
		 * @return void
		 */
		public static function maliyet_kaydet( $post_id, $maliyet, $kaynak = 'manuel', array $recete = array() ) {
			$post_id = absint( $post_id );
			if ( $post_id <= 0 ) {
				return;
			}
			$maliyet = max( 0, round( (float) $maliyet, 2 ) );
			$kaynak  = in_array( $kaynak, array( 'manuel', 'recete' ), true ) ? $kaynak : 'manuel';

			update_post_meta( $post_id, self::META_MALIYET, $maliyet );
			update_post_meta( $post_id, self::META_KAYNAK, $kaynak );
			if ( 'recete' === $kaynak && ! empty( $recete ) ) {
				update_post_meta( $post_id, self::META_RECETE, $recete );
			}
			self::rapor_onbellegini_temizle();
		}

		/**
		 * Tüm ürünleri rapor için hazırlar.
		 *
		 * @return array
		 */
		public static function urun_listesi() {
			$posts = get_posts(
				array(
					'post_type'      => 'rma_menu_item',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);

			$liste = array();
			foreach ( $posts as $p ) {
				$fiyat = (float) get_post_meta( $p->ID, 'rma_price', true );
				$terms = wp_get_post_terms( $p->ID, 'rma_category', array( 'fields' => 'names' ) );
				$kat   = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (string) $terms[0] : '';

				$liste[] = array(
					'id'       => $p->ID,
					'ad'       => $p->post_title,
					'kategori' => $kat,
					'fiyat'    => $fiyat,
					'maliyet'  => self::maliyet_oku( $p->ID ),
					'kaynak'   => self::kaynak_oku( $p->ID ),
					'recete'   => self::recete_oku( $p->ID ),
				);
			}

			return $liste;
		}

		/**
		 * Rapor önbelleğini temizler.
		 *
		 * @return void
		 */
		public static function rapor_onbellegini_temizle() {
			$bust = (int) get_option( self::OPT_RAPOR_BUST, 0 );
			update_option( self::OPT_RAPOR_BUST, $bust + 1, false );
		}

		/**
		 * Rapor hesaplar (transient önbellekli).
		 *
		 * @param string $bas      Başlangıç.
		 * @param string $bit      Bitiş.
		 * @param string $kategori Kategori filtresi.
		 * @return array
		 */
		public static function rapor_hesapla( $bas, $bit, $kategori = '' ) {
			$bust = (int) get_option( self::OPT_RAPOR_BUST, 0 );
			$key  = 'qrms_mm_rapor_' . md5( wp_json_encode( array( $bas, $bit, $kategori, $bust ) ) );
			$cache = get_transient( $key );
			if ( is_array( $cache ) ) {
				return $cache;
			}

			$analitik = QRMS_MM_Hesap::analitik_cek( $bas, $bit );
			$urunler  = self::urun_listesi();
			$ayarlar  = self::ayarlar();
			$sonuc    = QRMS_MM_Hesap::hesapla( $bas, $bit, $kategori, $urunler, $analitik, $ayarlar );

			set_transient( $key, $sonuc, 5 * MINUTE_IN_SECONDS );
			return $sonuc;
		}

		/**
		 * Hub istatistikleri.
		 *
		 * @return array
		 */
		public static function hub_istatistikleri() {
			$urunler = self::urun_listesi();
			$toplam  = count( $urunler );
			$maliyetli = 0;
			foreach ( $urunler as $u ) {
				if ( (float) ( $u['maliyet'] ?? 0 ) > 0 ) {
					$maliyetli++;
				}
			}

			$ayarlar = self::ayarlar();
			$gun     = (int) ( $ayarlar['varsayilan_aralik'] ?? 30 );
			$bit     = current_time( 'mysql' );
			$bas     = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $gun . ' days', strtotime( $bit ) ) );
			$rapor   = self::rapor_hesapla( $bas, $bit, '' );

			$en_kayip = '';
			$en_kayip_deger = 0;
			foreach ( $rapor['urunler'] as $u ) {
				if ( in_array( $u['kutu'], array( QRMS_MM_Hesap::KUTU_KOPEK, QRMS_MM_Hesap::KUTU_IS_ATI ), true ) ) {
					$k = (float) ( $rapor['ozet']['ortalama_marj'] ?? 0 ) > 0
						? ( $u['cm'] < 0 ? abs( $u['katki'] ) : max( 0, ( $rapor['ozet']['toplam_katkı'] / max( 1, count( $rapor['urunler'] ) ) ) - $u['katki'] ) )
						: 0;
					if ( $k > $en_kayip_deger ) {
						$en_kayip_deger = $k;
						$en_kayip       = $u['ad'];
					}
				}
			}

			return array(
				'maliyetli'    => $maliyetli,
				'toplam'       => $toplam,
				'toplam_katki' => (float) ( $rapor['ozet']['toplam_katkı'] ?? 0 ),
				'en_kayip'     => $en_kayip,
			);
		}
	}
}

if ( ! function_exists( 'qrms_mm_recete_yenile_cron' ) ) {
	/**
	 * Arka plan reçete yenileme görevi.
	 *
	 * @return void
	 */
	function qrms_mm_recete_yenile_cron() {
		if ( class_exists( 'QRMS_MM_Maliyet' ) ) {
			$ids = get_posts(
				array(
					'post_type'      => 'rma_menu_item',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'   => QRMS_MM_Maliyet::META_KAYNAK,
							'value' => 'recete',
						),
					),
				)
			);
			foreach ( $ids as $id ) {
				$recete = QRMS_MM_Maliyet::recete_oku( $id );
				if ( ! empty( $recete ) ) {
					QRMS_MM_Maliyet::maliyet_kaydet( $id, QRMS_MM_Maliyet::receteden_hesapla( $recete ), 'recete', $recete );
				}
			}
		}
	}
	add_action( 'qrms_mm_recete_yenile', 'qrms_mm_recete_yenile_cron' );
}
