<?php
/**
 * Ürün maliyeti, reçete ve malzeme fiyatları.
 *
 * Maliyet iki yoldan girilebilir:
 *
 *   manuel : işletmeci ürün başına tek bir rakam yazar.
 *   recete : malzeme + miktar satırları girilir, maliyet malzeme birim
 *            fiyatlarından HESAPLANIR ve meta'ya yazılır.
 *
 * Reçete modunda maliyet raporun içinde değil, KAYDEDERKEN hesaplanır: rapor
 * yüzlerce ürünü tek sorguda çekiyor, her satırda reçete çözmek onu N+1
 * sorguya çevirirdi.
 *
 * Malzeme taksonomisi restoran-menu modülünündür (`rma_ingredient`); adı
 * tahmin edilmez, sınıf varsa oradan okunur.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Maliyet ve reçete deposu.
 */
class QRMS_MM_Maliyet {

	/** Ürün maliyeti (₺, KDV hariç). */
	const META_MALIYET = '_qrms_mm_maliyet';

	/** Maliyetin kaynağı: manuel | recete. */
	const META_KAYNAK = '_qrms_mm_maliyet_kaynak';

	/** Reçete satırları. */
	const META_RECETE = '_qrms_mm_recete';

	/** Malzeme birim fiyatları option'ı. */
	const OPTION_MALZEME = 'qrms_mm_malzeme_fiyat';

	/** Modül ayarları option'ı. */
	const OPTION_AYAR = 'qrms_mm_ayarlar';

	/** Ürün fiyatının tutulduğu meta (restoran-menu modülü). */
	const META_FIYAT = 'rma_price';

	/** Menü ürünü CPT'si. */
	const CPT = 'rma_menu_item';

	/** Menü kategorisi taksonomisi. */
	const TAX_KATEGORI = 'rma_category';

	/**
	 * Reçete tek seferde yeniden hesaplanacak azami ürün sayısı.
	 *
	 * Üstünde kalanlar arka plana atılır; malzeme fiyatı kaydeden kullanıcı
	 * zaman aşımına düşmesin.
	 */
	const TOPLU_SINIR = 500;

	/**
	 * Malzeme taksonomisinin adı.
	 *
	 * @return string
	 */
	public static function malzeme_taksonomisi() {
		if ( class_exists( 'RMA_Ingredient_Taxonomy' ) && defined( 'RMA_Ingredient_Taxonomy::TAXONOMY' ) ) {
			return (string) constant( 'RMA_Ingredient_Taxonomy::TAXONOMY' );
		}

		return 'rma_ingredient';
	}

	/* -----------------------------------------------------------------
	   AYARLAR
	----------------------------------------------------------------- */

	/**
	 * Varsayılan ayarlar.
	 *
	 * @return array
	 */
	public static function ayar_varsayilan() {
		return array(
			'populerlik_esigi'  => 0.70,
			'fire_yuzdesi'      => 0,
			'varsayilan_aralik' => 30,
		);
	}

	/**
	 * Kayıtlı ayarlar.
	 *
	 * @return array
	 */
	public static function ayarlar() {
		$kayitli = get_option( self::OPTION_AYAR, array() );

		if ( ! is_array( $kayitli ) ) {
			$kayitli = array();
		}

		return array_merge( self::ayar_varsayilan(), $kayitli );
	}

	/**
	 * Ayarları temizler.
	 *
	 * @param array $raw Ham veri.
	 * @return array
	 */
	public static function ayar_temizle( $raw ) {
		$v   = self::ayar_varsayilan();
		$raw = is_array( $raw ) ? $raw : array();

		if ( isset( $raw['populerlik_esigi'] ) ) {
			$esik = (float) str_replace( ',', '.', (string) $raw['populerlik_esigi'] );
			$v['populerlik_esigi'] = round( max( 0.5, min( 1.0, $esik ) ), 2 );
		}

		if ( isset( $raw['fire_yuzdesi'] ) ) {
			$v['fire_yuzdesi'] = max( 0, min( 50, absint( $raw['fire_yuzdesi'] ) ) );
		}

		if ( isset( $raw['varsayilan_aralik'] ) ) {
			$aralik = absint( $raw['varsayilan_aralik'] );
			$v['varsayilan_aralik'] = in_array( $aralik, array( 7, 30, 90 ), true ) ? $aralik : 30;
		}

		return $v;
	}

	/* -----------------------------------------------------------------
	   MALZEME FİYATLARI
	----------------------------------------------------------------- */

	/**
	 * Kabul edilen birimler ve ölçek.
	 *
	 * `bolen`: reçetedeki miktarın birim fiyata çevrilmesi için bölünecek
	 * değer. kg fiyatı verildiğinde reçete gram yazılır (1000'e bölünür);
	 * adet fiyatında bölme yoktur.
	 *
	 * @return array<string,array{ad:string,miktar:string,bolen:float}>
	 */
	public static function birimler() {
		return array(
			'kg'   => array(
				'ad'     => __( 'kg', 'qrms' ),
				'miktar' => __( 'gram', 'qrms' ),
				'bolen'  => 1000.0,
			),
			'lt'   => array(
				'ad'     => __( 'litre', 'qrms' ),
				'miktar' => __( 'ml', 'qrms' ),
				'bolen'  => 1000.0,
			),
			'adet' => array(
				'ad'     => __( 'adet', 'qrms' ),
				'miktar' => __( 'adet', 'qrms' ),
				'bolen'  => 1.0,
			),
		);
	}

	/**
	 * Malzeme birim fiyatları.
	 *
	 * @return array<int,array{birim:string,fiyat:float}>
	 */
	public static function malzeme_fiyatlari() {
		$kayitli = get_option( self::OPTION_MALZEME, array() );

		return is_array( $kayitli ) ? $kayitli : array();
	}

	/**
	 * Malzeme fiyatlarını temizler.
	 *
	 * @param array $raw Ham veri (term_id => [birim, fiyat]).
	 * @return array
	 */
	public static function malzeme_temizle( $raw ) {
		$temiz   = array();
		$birimler = self::birimler();

		foreach ( (array) $raw as $term_id => $satir ) {
			$term_id = absint( $term_id );

			if ( ! $term_id || ! is_array( $satir ) ) {
				continue;
			}

			$birim = isset( $satir['birim'] ) ? sanitize_key( $satir['birim'] ) : 'kg';
			$birim = isset( $birimler[ $birim ] ) ? $birim : 'kg';
			$fiyat = self::sayi( isset( $satir['fiyat'] ) ? $satir['fiyat'] : 0 );

			// Fiyatı sıfır olan malzeme hiç saklanmaz: option şişmesin ve
			// "girilmemiş" ile "bedava" ayırt edilebilsin.
			if ( $fiyat <= 0 ) {
				continue;
			}

			$temiz[ $term_id ] = array(
				'birim' => $birim,
				'fiyat' => $fiyat,
			);
		}

		return $temiz;
	}

	/* -----------------------------------------------------------------
	   REÇETE
	----------------------------------------------------------------- */

	/**
	 * Reçeteyi temizler.
	 *
	 * @param array $raw Ham satırlar.
	 * @return array<int,array{term_id:int,miktar:float}>
	 */
	public static function recete_temizle( $raw ) {
		$temiz = array();

		foreach ( (array) $raw as $satir ) {
			if ( ! is_array( $satir ) ) {
				continue;
			}

			$term_id = isset( $satir['term_id'] ) ? absint( $satir['term_id'] ) : 0;
			$miktar  = self::sayi( isset( $satir['miktar'] ) ? $satir['miktar'] : 0 );

			if ( ! $term_id || $miktar <= 0 ) {
				continue;
			}

			$temiz[] = array(
				'term_id' => $term_id,
				'miktar'  => $miktar,
			);
		}

		return $temiz;
	}

	/**
	 * Reçeteden maliyet hesaplar.
	 *
	 * SAF fonksiyondur — testler doğrudan çağırır.
	 *
	 * @param array $recete    Temizlenmiş reçete satırları.
	 * @param array $fiyatlar  Malzeme fiyatları (term_id => [birim, fiyat]).
	 * @param int   $fire      Fire yüzdesi (0–50).
	 * @return float
	 */
	public static function recete_maliyeti( array $recete, array $fiyatlar, $fire = 0 ) {
		$birimler = self::birimler();
		$toplam   = 0.0;

		foreach ( $recete as $satir ) {
			$term_id = isset( $satir['term_id'] ) ? (int) $satir['term_id'] : 0;

			if ( ! isset( $fiyatlar[ $term_id ] ) ) {
				continue; // Fiyatı girilmemiş malzeme sıfır sayılır.
			}

			$birim = $fiyatlar[ $term_id ]['birim'];
			$bolen = isset( $birimler[ $birim ]['bolen'] ) ? $birimler[ $birim ]['bolen'] : 1.0;

			$toplam += ( (float) $satir['miktar'] / $bolen ) * (float) $fiyatlar[ $term_id ]['fiyat'];
		}

		$fire = max( 0, min( 50, (int) $fire ) );

		if ( $fire > 0 ) {
			$toplam *= ( 1 + ( $fire / 100 ) );
		}

		return round( $toplam, 2 );
	}

	/* -----------------------------------------------------------------
	   ÜRÜN
	----------------------------------------------------------------- */

	/**
	 * Ürünün maliyeti (girilmemişse null).
	 *
	 * @param int $post_id Ürün.
	 * @return float|null
	 */
	public static function maliyet( $post_id ) {
		$ham = get_post_meta( absint( $post_id ), self::META_MALIYET, true );

		if ( '' === $ham || null === $ham || false === $ham ) {
			return null;
		}

		return (float) $ham;
	}

	/**
	 * Ürünün fiyatı (girilmemişse null).
	 *
	 * @param int $post_id Ürün.
	 * @return float|null
	 */
	public static function fiyat( $post_id ) {
		$ham = get_post_meta( absint( $post_id ), self::META_FIYAT, true );

		if ( '' === $ham || null === $ham || false === $ham ) {
			return null;
		}

		return self::sayi( $ham );
	}

	/**
	 * Ürünün reçetesi.
	 *
	 * @param int $post_id Ürün.
	 * @return array
	 */
	public static function recete( $post_id ) {
		$ham = get_post_meta( absint( $post_id ), self::META_RECETE, true );

		return is_array( $ham ) ? $ham : array();
	}

	/**
	 * Maliyet kaynağı.
	 *
	 * @param int $post_id Ürün.
	 * @return string manuel|recete
	 */
	public static function kaynak( $post_id ) {
		$ham = get_post_meta( absint( $post_id ), self::META_KAYNAK, true );

		return 'recete' === $ham ? 'recete' : 'manuel';
	}

	/**
	 * Manuel maliyeti yazar.
	 *
	 * @param int   $post_id Ürün.
	 * @param mixed $deger   Maliyet ('' ise meta silinir).
	 * @return float|null Yazılan değer.
	 */
	public static function maliyet_yaz( $post_id, $deger ) {
		$post_id = absint( $post_id );

		if ( '' === $deger || null === $deger ) {
			delete_post_meta( $post_id, self::META_MALIYET );
			delete_post_meta( $post_id, self::META_KAYNAK );
			self::onbellek_temizle();

			return null;
		}

		$maliyet = max( 0, self::sayi( $deger ) );

		update_post_meta( $post_id, self::META_MALIYET, $maliyet );
		update_post_meta( $post_id, self::META_KAYNAK, 'manuel' );
		self::onbellek_temizle();

		return $maliyet;
	}

	/**
	 * Reçeteyi yazar ve maliyeti ondan hesaplar.
	 *
	 * @param int   $post_id Ürün.
	 * @param array $recete  Ham reçete satırları.
	 * @return float Hesaplanan maliyet.
	 */
	public static function recete_yaz( $post_id, $recete ) {
		$post_id = absint( $post_id );
		$temiz   = self::recete_temizle( $recete );

		if ( empty( $temiz ) ) {
			delete_post_meta( $post_id, self::META_RECETE );
			update_post_meta( $post_id, self::META_KAYNAK, 'manuel' );
			self::onbellek_temizle();

			return (float) self::maliyet( $post_id );
		}

		$ayar    = self::ayarlar();
		$maliyet = self::recete_maliyeti( $temiz, self::malzeme_fiyatlari(), $ayar['fire_yuzdesi'] );

		update_post_meta( $post_id, self::META_RECETE, $temiz );
		update_post_meta( $post_id, self::META_MALIYET, $maliyet );
		update_post_meta( $post_id, self::META_KAYNAK, 'recete' );
		self::onbellek_temizle();

		return $maliyet;
	}

	/**
	 * Reçete tabanlı bütün maliyetleri yeniden hesaplar.
	 *
	 * Malzeme fiyatı değiştiğinde çağrılır. TOPLU_SINIR'ı aşan kurulumlarda
	 * iş arka plana atılır.
	 *
	 * @return int Güncellenen ürün sayısı; iş arka plana atıldıysa -1.
	 *             ("hiç reçeteli ürün yok" ile "arka planda sürüyor" farklı
	 *             şeylerdir, ekranda ayrı yazılırlar.)
	 */
	public static function receteleri_yenile() {
		$idler = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => self::TOPLU_SINIR + 1,
				'meta_key'       => self::META_KAYNAK, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'recete', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'no_found_rows'  => true,
			)
		);

		if ( count( $idler ) > self::TOPLU_SINIR ) {
			if ( function_exists( 'wp_schedule_single_event' ) ) {
				wp_schedule_single_event( time() + 30, 'qrms_mm_recete_yenile' );
			}

			return -1;
		}

		$ayar     = self::ayarlar();
		$fiyatlar = self::malzeme_fiyatlari();
		$sayi     = 0;

		foreach ( $idler as $id ) {
			$recete = self::recete( $id );

			if ( empty( $recete ) ) {
				continue;
			}

			update_post_meta(
				$id,
				self::META_MALIYET,
				self::recete_maliyeti( $recete, $fiyatlar, $ayar['fire_yuzdesi'] )
			);

			++$sayi;
		}

		self::onbellek_temizle();

		return $sayi;
	}

	/* -----------------------------------------------------------------
	   YARDIMCILAR
	----------------------------------------------------------------- */

	/**
	 * Kullanıcının yazdığı sayıyı float'a çevirir.
	 *
	 * Türkçe klavyede ondalık ayracı virgüldür; "12,50" yazan kullanıcının
	 * maliyeti 12 TL'ye yuvarlanmamalı.
	 *
	 * @param mixed $ham Ham değer.
	 * @return float
	 */
	public static function sayi( $ham ) {
		if ( is_float( $ham ) || is_int( $ham ) ) {
			return (float) $ham;
		}

		$metin = trim( (string) $ham );

		if ( '' === $metin ) {
			return 0.0;
		}

		// Binlik ayracı olarak kullanılan noktayı at, virgülü ondalık yap.
		if ( false !== strpos( $metin, ',' ) ) {
			$metin = str_replace( '.', '', $metin );
			$metin = str_replace( ',', '.', $metin );
		}

		$metin = preg_replace( '/[^0-9.\-]/', '', $metin );

		return round( (float) $metin, 2 );
	}

	/**
	 * Rapor önbelleğini boşaltır.
	 *
	 * Maliyet, fiyat ya da ayar değiştiğinde rapor eski sonucu göstermemeli.
	 *
	 * @return void
	 */
	public static function onbellek_temizle() {
		if ( class_exists( 'QRMS_MM_Rapor' ) ) {
			QRMS_MM_Rapor::onbellek_temizle();
		}
	}
}
