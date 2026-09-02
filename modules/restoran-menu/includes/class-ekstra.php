<?php
/**
 * Yan ürünler / ekstralar ("sos +10 ₺").
 *
 * İki kaynak birlikte çalışır:
 *
 *  - LİSTE: `rma_ekstra_listeleri` option'ında tanımlı, birden çok üründe
 *    yeniden kullanılan gruplar (Soslar, İçecekler…). Fiyat tek yerden
 *    güncellenir.
 *  - MANUEL: yalnızca o ürüne ait satırlar (`_rma_ekstra_manuel`).
 *
 * Ekstra HİÇBİR üründe varsayılan olarak görünmez: ürün ekranında liste
 * seçilmedikçe ve manuel satır girilmedikçe modalda blok basılmaz.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'RMA_Ekstra' ) ) :

class RMA_Ekstra {

	/** Yeniden kullanılabilir listelerin option anahtarı. */
	const OPTION = 'rma_ekstra_listeleri';

	/** Ürüne özel satırlar. */
	const META_MANUEL = '_rma_ekstra_manuel';

	/** Ürüne bağlı liste id'leri. */
	const META_LISTE = '_rma_ekstra_listeler';

	/** Bir üründe seçilebilecek azami ekstra sayısı (istemci + sunucu sınırı). */
	const AZAMI_SECIM = 10;

	/* =============================================================
	   LİSTELER (option)
	============================================================= */

	/**
	 * Tanımlı ekstra listeleri.
	 *
	 * @return array<int,array{id:string,ad:string,urunler:array<int,array{ad:string,fiyat:float}>}>
	 */
	public static function listeler() {
		return self::listeleri_temizle( get_option( self::OPTION, array() ) );
	}

	/**
	 * Tek liste.
	 *
	 * @param string $id Liste id'si.
	 * @return array|null
	 */
	public static function liste( $id ) {
		$id = sanitize_key( (string) $id );

		foreach ( self::listeler() as $liste ) {
			if ( $liste['id'] === $id ) {
				return $liste;
			}
		}

		return null;
	}

	/**
	 * Her liste id'sinin kaç üründe kullanıldığını döndürür (tek sorgu).
	 *
	 * @return array<string,int>
	 */
	public static function kullanim_sayilari() {
		static $memo = null;

		if ( null !== $memo ) {
			return $memo;
		}

		global $wpdb;

		$memo = array();
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::META_LISTE
			)
		);

		foreach ( $rows as $ham ) {
			$idler = maybe_unserialize( $ham );

			if ( ! is_array( $idler ) ) {
				continue;
			}

			foreach ( $idler as $id ) {
				$id = sanitize_key( (string) $id );

				if ( '' === $id ) {
					continue;
				}

				if ( ! isset( $memo[ $id ] ) ) {
					$memo[ $id ] = 0;
				}

				++$memo[ $id ];
			}
		}

		return $memo;
	}

	/**
	 * Listeleri kaydeder.
	 *
	 * @param mixed $ham POST edilen dizi.
	 * @return array Kaydedilen temiz dizi.
	 */
	public static function listeleri_kaydet( $ham ) {
		$temiz = self::listeleri_temizle( $ham );

		update_option( self::OPTION, $temiz, false );

		return $temiz;
	}

	/**
	 * Liste dizisini doğrular. id boşsa addan türetilir, çakışırsa sayı eklenir.
	 *
	 * @param mixed $ham Ham dizi.
	 * @return array
	 */
	public static function listeleri_temizle( $ham ) {
		if ( ! is_array( $ham ) ) {
			return array();
		}

		$temiz    = array();
		$kullanan = array();

		foreach ( $ham as $liste ) {
			if ( ! is_array( $liste ) ) {
				continue;
			}

			$ad = trim( sanitize_text_field( (string) ( $liste['ad'] ?? '' ) ) );
			if ( '' === $ad ) {
				continue;
			}

			$urunler = self::satirlari_temizle( $liste['urunler'] ?? array() );
			if ( empty( $urunler ) ) {
				continue;
			}

			$id = sanitize_key( (string) ( $liste['id'] ?? '' ) );
			if ( '' === $id ) {
				$id = sanitize_key( sanitize_title( $ad ) );
			}
			if ( '' === $id ) {
				$id = 'liste';
			}

			$temel = $id;
			$n     = 2;
			while ( isset( $kullanan[ $id ] ) ) {
				$id = $temel . '-' . $n;
				++$n;
			}
			$kullanan[ $id ] = true;

			$temiz[] = array(
				'id'      => $id,
				'ad'      => mb_substr( $ad, 0, 60 ),
				'urunler' => $urunler,
			);
		}

		return $temiz;
	}

	/* =============================================================
	   ÜRÜN BAĞLANTISI (post meta)
	============================================================= */

	/**
	 * Ürüne özel manuel satırlar.
	 *
	 * @param int $post_id Ürün ID.
	 * @return array<int,array{ad:string,fiyat:float}>
	 */
	public static function manuel( $post_id ) {
		return self::satirlari_temizle( get_post_meta( (int) $post_id, self::META_MANUEL, true ) );
	}

	/**
	 * Ürüne bağlı liste id'leri.
	 *
	 * @param int $post_id Ürün ID.
	 * @return string[]
	 */
	public static function liste_idleri( $post_id ) {
		$ham = get_post_meta( (int) $post_id, self::META_LISTE, true );

		if ( ! is_array( $ham ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'sanitize_key', $ham ) ) );
	}

	/**
	 * Ürün ayarlarını kaydeder.
	 *
	 * @param int   $post_id  Ürün ID.
	 * @param mixed $manuel   Manuel satırlar.
	 * @param mixed $liste_id Seçili liste id'leri.
	 * @return void
	 */
	public static function urun_kaydet( $post_id, $manuel, $liste_id ) {
		$post_id = (int) $post_id;

		$manuel_temiz = self::satirlari_temizle( $manuel );
		if ( empty( $manuel_temiz ) ) {
			delete_post_meta( $post_id, self::META_MANUEL );
		} else {
			update_post_meta( $post_id, self::META_MANUEL, $manuel_temiz );
		}

		$gecerli = wp_list_pluck( self::listeler(), 'id' );
		$secili  = is_array( $liste_id ) ? array_map( 'sanitize_key', $liste_id ) : array();
		$secili  = array_values( array_intersect( $secili, $gecerli ) );

		if ( empty( $secili ) ) {
			delete_post_meta( $post_id, self::META_LISTE );
		} else {
			update_post_meta( $post_id, self::META_LISTE, $secili );
		}
	}

	/**
	 * Üründe gösterilecek ekstra grupları (liste grupları + manuel grup).
	 *
	 * @param int $post_id Ürün ID.
	 * @return array<int,array{baslik:string,urunler:array}>
	 */
	public static function gruplar( $post_id ) {
		$gruplar = array();

		foreach ( self::liste_idleri( $post_id ) as $id ) {
			$liste = self::liste( $id );
			if ( $liste ) {
				$gruplar[] = array(
					'baslik'  => $liste['ad'],
					'urunler' => $liste['urunler'],
				);
			}
		}

		$manuel = self::manuel( $post_id );
		if ( ! empty( $manuel ) ) {
			$gruplar[] = array(
				'baslik'  => '',
				'urunler' => $manuel,
			);
		}

		return $gruplar;
	}

	/**
	 * Üründe ekstra var mı?
	 *
	 * @param int $post_id Ürün ID.
	 * @return bool
	 */
	public static function var_mi( $post_id ) {
		return array() !== self::gruplar( $post_id );
	}

	/* =============================================================
	   ÖN YÜZ
	============================================================= */

	/**
	 * Modalın altındaki açılır "Ekstra ekle" bloğu.
	 *
	 * <details> kullanılır: JavaScript olmadan da açılıp kapanır, ekran
	 * okuyucu için doğru semantiktir ve kapalıyken yer kaplamaz.
	 *
	 * @param int $post_id Ürün ID.
	 * @return string
	 */
	public static function html( $post_id ) {
		$gruplar = self::gruplar( $post_id );

		if ( empty( $gruplar ) ) {
			return '';
		}

		$out = '<details class="rma-ekstra" data-rma-ekstra="1">'
			. '<summary class="rma-ekstra-baslik">'
			. '<span>' . esc_html( self::cevir( 'Ekstra ekle' ) ) . '</span>'
			. '<span class="rma-ekstra-ok" aria-hidden="true">&#8250;</span>'
			. '</summary>'
			. '<div class="rma-ekstra-govde">';

		foreach ( $gruplar as $grup ) {
			if ( '' !== $grup['baslik'] ) {
				$out .= '<div class="rma-ekstra-grup-ad">' . esc_html( self::cevir( $grup['baslik'] ) ) . '</div>';
			}

			foreach ( $grup['urunler'] as $urun ) {
				$out .= '<label class="rma-ekstra-sec">'
					. '<input type="checkbox" value="' . esc_attr( $urun['ad'] ) . '"'
					. ' data-fiyat="' . esc_attr( number_format( (float) $urun['fiyat'], 2, '.', '' ) ) . '">'
					. '<span class="rma-ekstra-ad">' . esc_html( self::cevir( $urun['ad'] ) ) . '</span>'
					. '<span class="rma-ekstra-fiyat">' . esc_html( self::fiyat_metni( $urun['fiyat'] ) ) . '</span>'
					. '</label>';
			}
		}

		return $out . '</div></details>';
	}

	/* =============================================================
	   YARDIMCILAR
	============================================================= */

	/**
	 * {ad, fiyat} satırlarını doğrular.
	 *
	 * @param mixed $ham Ham dizi.
	 * @return array<int,array{ad:string,fiyat:float}>
	 */
	public static function satirlari_temizle( $ham ) {
		if ( ! is_array( $ham ) ) {
			return array();
		}

		$temiz = array();

		foreach ( $ham as $satir ) {
			if ( ! is_array( $satir ) ) {
				continue;
			}

			$ad = trim( sanitize_text_field( (string) ( $satir['ad'] ?? '' ) ) );
			if ( '' === $ad ) {
				continue;
			}

			$temiz[] = array(
				'ad'    => mb_substr( $ad, 0, 60 ),
				// 0.0 ile karşılaştırılır: max( 0, -5.0 ) int 0 döndürürdü,
				// alan tipi tutarsız kalırdı.
				'fiyat' => max( 0.0, self::sayi( $satir['fiyat'] ?? 0 ) ),
			);

			if ( count( $temiz ) >= 30 ) {
				break;
			}
		}

		return $temiz;
	}

	/**
	 * "+10 ₺" — ücretsizse "Ücretsiz".
	 *
	 * @param float $fiyat Ekstra fiyatı.
	 * @return string
	 */
	public static function fiyat_metni( $fiyat ) {
		$fiyat = (float) $fiyat;

		if ( $fiyat < 0.005 ) {
			return self::cevir( 'Ücretsiz' );
		}

		return class_exists( 'RMA_Kampanya' )
			? RMA_Kampanya::fiyat_yazi( $fiyat, '+{n} ₺' )
			: '+' . $fiyat . ' ₺';
	}

	/**
	 * Virgüllü girdiyi de kabul eden sayı dönüşümü.
	 *
	 * @param mixed $deger Ham değer.
	 * @return float
	 */
	private static function sayi( $deger ) {
		if ( is_numeric( $deger ) ) {
			return round( (float) $deger, 2 );
		}

		$metin = str_replace( array( ' ', '₺' ), '', (string) $deger );
		$metin = str_replace( ',', '.', $metin );
		$metin = preg_replace( '/[^0-9.\-]/', '', $metin );

		return is_numeric( $metin ) ? round( (float) $metin, 2 ) : 0.0;
	}

	/**
	 * Sabit arayüz metni çevirisi.
	 *
	 * @param string $metin Türkçe metin.
	 * @return string
	 */
	private static function cevir( $metin ) {
		return function_exists( 'rma_ceviri_ui' ) ? rma_ceviri_ui( $metin ) : $metin;
	}
}

endif;
