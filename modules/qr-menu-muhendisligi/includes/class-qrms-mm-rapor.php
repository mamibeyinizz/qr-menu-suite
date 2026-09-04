<?php
/**
 * Menü mühendisliği raporunun veri katmanı.
 *
 * Ürün listesi CPT'den, satış/etkileşim sayıları `{prefix}rma_analytics`
 * tablosundan gelir. İki kaynak PHP tarafında birleştirilir; hiç olayı
 * olmayan ürün de listeye girer — satmayan ürün raporun asıl konusudur,
 * INNER JOIN mantığıyla çalışılsaydı tam da onlar kaybolurdu.
 *
 * Ölçek notu: analitik tarafı TEK toplu sorgudur (item_id'ye GROUP BY),
 * ürün tarafı tek WP_Query'dir. Döngü içinde sorgu yoktur; meta ve terim
 * önbellekleri WP_Query tarafından topluca ısıtılır.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rapor sorguları ve önbelleği.
 */
class QRMS_MM_Rapor {

	/** Önbellek süresi (saniye). */
	const TTL = 300;

	/** Önbellek anahtarlarının listesini tutan option. */
	const ONBELLEK_DEFTER = 'qrms_mm_onbellek';

	/**
	 * Seçilebilir tarih aralıkları.
	 *
	 * @return array<int,string>
	 */
	public static function araliklar() {
		return array(
			7  => __( 'Son 7 gün', 'qrms' ),
			30 => __( 'Son 30 gün', 'qrms' ),
			90 => __( 'Son 90 gün', 'qrms' ),
		);
	}

	/**
	 * İstek parametrelerini temizler.
	 *
	 * @param array $raw Ham parametreler ($_GET).
	 * @return array{gun:int,kategori:int,eksik:int}
	 */
	public static function parametreler( $raw ) {
		$ayar = QRMS_MM_Maliyet::ayarlar();
		$raw  = is_array( $raw ) ? $raw : array();

		$gun = isset( $raw['gun'] ) ? absint( $raw['gun'] ) : 0;

		if ( ! isset( self::araliklar()[ $gun ] ) ) {
			$gun = (int) $ayar['varsayilan_aralik'];
		}

		return array(
			'gun'      => $gun,
			'kategori' => isset( $raw['kategori'] ) ? absint( $raw['kategori'] ) : 0,
			'eksik'    => empty( $raw['eksik'] ) ? 0 : 1,
		);
	}

	/**
	 * Raporu üretir (önbellekli).
	 *
	 * @param array $args parametreler() çıktısı.
	 * @return array QRMS_MM_Hesap::hesapla() çıktısı + 'aralik' bilgisi.
	 */
	public static function rapor( array $args ) {
		$anahtar = self::anahtar( $args );
		$onbellek = get_transient( $anahtar );

		if ( is_array( $onbellek ) ) {
			return $onbellek;
		}

		$satirlar = self::satirlar( $args );
		$sonuc    = QRMS_MM_Hesap::hesapla( $satirlar, QRMS_MM_Maliyet::ayarlar() );

		$sonuc['aralik'] = array(
			'gun'        => $args['gun'],
			'baslangic'  => self::baslangic( $args['gun'] ),
			'bitis'      => current_time( 'mysql' ),
		);

		set_transient( $anahtar, $sonuc, self::TTL );
		self::deftere_yaz( $anahtar );

		return $sonuc;
	}

	/**
	 * Ürün + analitik satırlarını birleştirir.
	 *
	 * @param array $args Parametreler.
	 * @return array
	 */
	private static function satirlar( array $args ) {
		$urunler = self::urunler( $args['kategori'] );

		if ( empty( $urunler ) ) {
			return array();
		}

		$olaylar = self::olaylar( $args['gun'] );
		$satir   = array();

		foreach ( $urunler as $urun ) {
			$id  = $urun['item_id'];
			$say = isset( $olaylar[ $id ] ) ? $olaylar[ $id ] : array(
				'satis' => 0,
				'sepet' => 0,
				'tik'   => 0,
			);

			$satir[] = array(
				'item_id'       => $id,
				'item_name'     => $urun['item_name'],
				'category_name' => $urun['category_name'],
				'fiyat'         => $urun['fiyat'],
				'maliyet'       => $urun['maliyet'],
				'satis'         => $say['satis'],
				'sepet'         => $say['sepet'],
				'tik'           => $say['tik'],
			);
		}

		return $satir;
	}

	/**
	 * Yayınlanmış menü ürünleri (fiyat, maliyet ve kategori adıyla).
	 *
	 * @param int $kategori Kategori terim kimliği (0 = hepsi).
	 * @return array
	 */
	public static function urunler( $kategori = 0 ) {
		$args = array(
			'post_type'              => QRMS_MM_Maliyet::CPT,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			// Meta ve terim önbelleği topluca ısıtılsın: aşağıdaki döngü
			// get_post_meta / get_the_terms çağırıyor, ısıtılmazsa her ürün
			// için ayrı sorgu açılırdı.
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		);

		if ( $kategori > 0 ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => QRMS_MM_Maliyet::TAX_KATEGORI,
					'field'    => 'term_id',
					'terms'    => $kategori,
				),
			);
		}

		$sorgu = new WP_Query( $args );
		$cikti = array();

		foreach ( $sorgu->posts as $post ) {
			$terimler = get_the_terms( $post->ID, QRMS_MM_Maliyet::TAX_KATEGORI );
			$kat      = '';

			if ( is_array( $terimler ) && ! empty( $terimler ) ) {
				$kat = (string) $terimler[0]->name;
			}

			$cikti[] = array(
				'item_id'       => (int) $post->ID,
				'item_name'     => (string) $post->post_title,
				'category_name' => $kat,
				'fiyat'         => QRMS_MM_Maliyet::fiyat( $post->ID ),
				'maliyet'       => QRMS_MM_Maliyet::maliyet( $post->ID ),
			);
		}

		wp_reset_postdata();

		return $cikti;
	}

	/**
	 * Aralıktaki olay sayıları — TEK toplu sorgu.
	 *
	 * @param int $gun Kaç günlük aralık.
	 * @return array<int,array{satis:int,sepet:int,tik:int}>
	 */
	private static function olaylar( $gun ) {
		global $wpdb;

		if ( ! class_exists( 'QRMS_Analitik' ) ) {
			return array();
		}

		$tablo     = QRMS_Analitik::tablo();
		$baslangic = self::baslangic( $gun );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$satirlar = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT item_id,
				        SUM(CASE WHEN event_type = 'order_sent'    THEN qty ELSE 0 END) AS satis,
				        SUM(CASE WHEN event_type = 'cart_add'      THEN 1   ELSE 0 END) AS sepet,
				        SUM(CASE WHEN event_type = 'product_click' THEN 1   ELSE 0 END) AS tik
				   FROM {$tablo}
				  WHERE created_at >= %s
				    AND item_id > 0
				    AND event_type IN ('order_sent', 'cart_add', 'product_click')
				  GROUP BY item_id",
				$baslangic
			),
			ARRAY_A
		);
		// phpcs:enable

		$cikti = array();

		foreach ( (array) $satirlar as $satir ) {
			$cikti[ (int) $satir['item_id'] ] = array(
				'satis' => (int) $satir['satis'],
				'sepet' => (int) $satir['sepet'],
				'tik'   => (int) $satir['tik'],
			);
		}

		return $cikti;
	}

	/**
	 * Aralığın başlangıç zamanı (site saatiyle).
	 *
	 * @param int $gun Gün sayısı.
	 * @return string MySQL datetime.
	 */
	public static function baslangic( $gun ) {
		$gun = max( 1, absint( $gun ) );

		return gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - ( $gun * DAY_IN_SECONDS ) );
	}

	/**
	 * Menü kategorileri (filtre açılır listesi için).
	 *
	 * @return array<int,string>
	 */
	public static function kategoriler() {
		$terimler = get_terms(
			array(
				'taxonomy'   => QRMS_MM_Maliyet::TAX_KATEGORI,
				'hide_empty' => false,
			)
		);

		$cikti = array();

		if ( is_array( $terimler ) ) {
			foreach ( $terimler as $terim ) {
				if ( is_object( $terim ) && isset( $terim->term_id ) ) {
					$cikti[ (int) $terim->term_id ] = (string) $terim->name;
				}
			}
		}

		return $cikti;
	}

	/* -----------------------------------------------------------------
	   ÖNBELLEK
	----------------------------------------------------------------- */

	/**
	 * Parametrelerden önbellek anahtarı.
	 *
	 * @param array $args Parametreler.
	 * @return string
	 */
	private static function anahtar( array $args ) {
		return 'qrms_mm_' . md5( wp_json_encode( array( $args['gun'], $args['kategori'] ) ) );
	}

	/**
	 * Anahtarı deftere yazar.
	 *
	 * Transient'ler tek tek silinebilsin diye ad listesi tutulur; joker
	 * karakterle option tablosunu taramak (DELETE ... LIKE '_transient_%')
	 * büyük sitelerde pahalıdır ve nesne önbelleği kullanan kurulumlarda
	 * hiç çalışmaz.
	 *
	 * @param string $anahtar Transient adı.
	 * @return void
	 */
	private static function deftere_yaz( $anahtar ) {
		$defter = get_option( self::ONBELLEK_DEFTER, array() );

		if ( ! is_array( $defter ) ) {
			$defter = array();
		}

		if ( in_array( $anahtar, $defter, true ) ) {
			return;
		}

		$defter[] = $anahtar;

		// Defter sınırsız büyümesin: aralık × kategori kombinasyonu sonlu.
		if ( count( $defter ) > 200 ) {
			$defter = array_slice( $defter, -200 );
		}

		update_option( self::ONBELLEK_DEFTER, $defter, false );
	}

	/**
	 * Rapor önbelleğini boşaltır.
	 *
	 * @return void
	 */
	public static function onbellek_temizle() {
		$defter = get_option( self::ONBELLEK_DEFTER, array() );

		if ( is_array( $defter ) ) {
			foreach ( $defter as $anahtar ) {
				delete_transient( $anahtar );
			}
		}

		delete_option( self::ONBELLEK_DEFTER );
	}
}
