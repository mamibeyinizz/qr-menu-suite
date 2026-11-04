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

	/** Arka plan reçete yenileme kancası. */
	const CRON_RECETE_YENILE = 'qrms_mm_recete_yenile';

	/** Sayfalama imleci (option). */
	const OPTION_RECETE_YENILE_OFFSET = 'qrms_mm_recete_yenile_offset';

	/** Reçete maliyetlerinin yeniden hesaplandığı son şema sürümü (option). */
	const OPTION_RECETE_SURUM = 'qrms_mm_recete_surum';

	/**
	 * Güncel reçete hesap sürümü.
	 *
	 * 2: fiyatsız malzeme 0 TL sayılmaz (maliyet meta'sı silinir); bilinçli
	 * 0 TL fiyat saklanır. Mevcut reçeteli ürünler bir kez yeniden hesaplanır.
	 */
	const RECETE_SURUM = 2;

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

			// Boş bırakılan alan saklanmaz ("girilmemiş"); bilinçli yazılan 0
			// saklanır ("bedava"). Negatif fiyat geçersizdir.
			$ham_fiyat = isset( $satir['fiyat'] ) ? trim( (string) $satir['fiyat'] ) : '';

			if ( '' === $ham_fiyat || $fiyat < 0 ) {
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
	 * @return float|null Fiyatı girilmemiş malzeme varsa null (0 TL sayılmaz).
	 */
	public static function recete_maliyeti( array $recete, array $fiyatlar, $fire = 0 ) {
		$birimler = self::birimler();
		$toplam   = 0.0;

		foreach ( $recete as $satir ) {
			$term_id = isset( $satir['term_id'] ) ? (int) $satir['term_id'] : 0;

			if ( ! isset( $fiyatlar[ $term_id ] ) ) {
				return null; // Fiyatı girilmemiş malzeme: maliyet hesaplanamaz.
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
	 * Reçeteli ürünün maliyeti eksik malzeme fiyatı yüzünden mi hesaplanamadı?
	 *
	 * @param int $post_id Ürün.
	 * @return bool
	 */
	public static function recete_eksik( $post_id ) {
		return 'recete' === self::kaynak( $post_id ) && null === self::maliyet( $post_id );
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
	 * @return float|null Hesaplanan maliyet; eksik malzeme fiyatı varsa null.
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
		self::maliyet_meta_yaz( $post_id, $maliyet );
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
		$cron = doing_action( self::CRON_RECETE_YENILE );

		if ( ! $cron ) {
			delete_option( self::OPTION_RECETE_YENILE_OFFSET );
			wp_clear_scheduled_hook( self::CRON_RECETE_YENILE );
		}

		$offset = $cron ? (int) get_option( self::OPTION_RECETE_YENILE_OFFSET, 0 ) : 0;
		$sinir  = max( 1, (int) apply_filters( 'qrms_mm_toplu_sinir', self::TOPLU_SINIR ) );

		$idler = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => $sinir,
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_key'       => self::META_KAYNAK, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'recete', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'no_found_rows'  => true,
			)
		);

		if ( empty( $idler ) ) {
			delete_option( self::OPTION_RECETE_YENILE_OFFSET );
			wp_clear_scheduled_hook( self::CRON_RECETE_YENILE );
			update_option( self::OPTION_RECETE_SURUM, self::RECETE_SURUM );

			return 0;
		}

		$ayar     = self::ayarlar();
		$fiyatlar = self::malzeme_fiyatlari();
		$sayi     = 0;

		foreach ( $idler as $id ) {
			$recete = self::recete( $id );

			if ( empty( $recete ) ) {
				continue;
			}

			self::maliyet_meta_yaz( $id, self::recete_maliyeti( $recete, $fiyatlar, $ayar['fire_yuzdesi'] ) );

			++$sayi;
		}

		self::onbellek_temizle();

		$yeni_offset = $offset + count( $idler );

		$devam = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'offset'         => $yeni_offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_key'       => self::META_KAYNAK, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'recete', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'no_found_rows'  => true,
			)
		);

		if ( ! empty( $devam ) ) {
			update_option( self::OPTION_RECETE_YENILE_OFFSET, $yeni_offset );

			if ( function_exists( 'wp_schedule_single_event' ) && ! wp_next_scheduled( self::CRON_RECETE_YENILE ) ) {
				wp_schedule_single_event( time() + 30, self::CRON_RECETE_YENILE );
			}

			return -1;
		}

		delete_option( self::OPTION_RECETE_YENILE_OFFSET );
		wp_clear_scheduled_hook( self::CRON_RECETE_YENILE );
		update_option( self::OPTION_RECETE_SURUM, self::RECETE_SURUM );

		return $sayi;
	}

	/**
	 * Sürüm yükseltmesi: reçeteli ürünlerin maliyetini bir kez yeniden hesaplatır.
	 *
	 * İş istekte DEĞİL, cron'da ve receteleri_yenile()'nin parçalı akışıyla
	 * (TOPLU_SINIR) yapılır; böylece büyük menülerde yönetim isteği zaman
	 * aşımına düşmez. Sürüm option'ı yalnızca iş TAMAMEN bittiğinde yazılır
	 * (receteleri_yenile içinde), bu yüzden yarıda kalan iş sonraki istekte
	 * yeniden zamanlanır; zamanlanmış iş varken ikinci kez zamanlanmaz.
	 *
	 * @return void
	 */
	public static function yukselt() {
		if ( (int) get_option( self::OPTION_RECETE_SURUM, 0 ) >= self::RECETE_SURUM ) {
			return;
		}

		if ( wp_next_scheduled( self::CRON_RECETE_YENILE ) ) {
			return;
		}

		delete_option( self::OPTION_RECETE_YENILE_OFFSET );
		wp_schedule_single_event( time() + 30, self::CRON_RECETE_YENILE );
	}

	/* -----------------------------------------------------------------
	   YARDIMCILAR
	----------------------------------------------------------------- */

	/**
	 * Reçeteden hesaplanan maliyeti yazar; null ise meta silinir ve ürün
	 * "maliyeti girilmemiş" yoluna düşer (matrise alınmaz).
	 *
	 * @param int        $post_id Ürün.
	 * @param float|null $maliyet Hesaplanan maliyet.
	 * @return void
	 */
	private static function maliyet_meta_yaz( $post_id, $maliyet ) {
		if ( null === $maliyet ) {
			delete_post_meta( $post_id, self::META_MALIYET );

			return;
		}

		update_post_meta( $post_id, self::META_MALIYET, $maliyet );
	}

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
