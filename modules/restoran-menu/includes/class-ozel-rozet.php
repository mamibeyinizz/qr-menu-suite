<?php
/**
 * Özel rozetler ("Hızlı Servis", "Acı", "Yüksek Protein"…).
 *
 * Yerleşik rozetler (Popüler / Yeni / Önerilen / İndirim) sabittir; bu sınıf
 * işletmenin kendi tanımladığı rozetleri yönetir. Tanımlar tek bir option'da
 * durur, ürüne yalnızca slug listesi yazılır — rozetin adı, ikonu veya rengi
 * değiştiğinde ürünlere dokunulmaz.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'RMA_Ozel_Rozet' ) ) :

class RMA_Ozel_Rozet {

	/** Tanımların option anahtarı. */
	const OPTION = 'rma_ozel_rozetler';

	/** Üründe seçili rozet slug'ları. */
	const META = '_rma_ozel_rozetler';

	/** Azami tanım sayısı. */
	const AZAMI = 20;

	/** Varsayılan rozet rengi (menü vurgu rengiyle uyumlu). */
	const RENK = '#c9a84c';

	/**
	 * Tanımlı rozetler.
	 *
	 * @return array<int,array{slug:string,ad:string,ikon:string,renk:string}>
	 */
	public static function tanimlar() {
		return self::temizle( get_option( self::OPTION, array() ) );
	}

	/**
	 * slug => tanım eşlemesi.
	 *
	 * @return array<string,array>
	 */
	public static function slug_haritasi() {
		$harita = array();

		foreach ( self::tanimlar() as $tanim ) {
			$harita[ $tanim['slug'] ] = $tanim;
		}

		return $harita;
	}

	/**
	 * Her rozet slug'ının kaç üründe kullanıldığını döndürür (tek sorgu).
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
				self::META
			)
		);

		foreach ( $rows as $ham ) {
			$sluglar = maybe_unserialize( $ham );

			if ( ! is_array( $sluglar ) ) {
				continue;
			}

			foreach ( $sluglar as $slug ) {
				$slug = sanitize_key( (string) $slug );

				if ( '' === $slug ) {
					continue;
				}

				if ( ! isset( $memo[ $slug ] ) ) {
					$memo[ $slug ] = 0;
				}

				++$memo[ $slug ];
			}
		}

		return $memo;
	}

	/**
	 * Tanımları kaydeder.
	 *
	 * @param mixed $ham POST edilen dizi.
	 * @return array
	 */
	public static function kaydet( $ham ) {
		$temiz = self::temizle( $ham );

		update_option( self::OPTION, $temiz, false );

		return $temiz;
	}

	/**
	 * Tanım dizisini doğrular.
	 *
	 * @param mixed $ham Ham dizi.
	 * @return array
	 */
	public static function temizle( $ham ) {
		if ( ! is_array( $ham ) ) {
			return array();
		}

		$temiz    = array();
		$kullanan = array();

		foreach ( $ham as $tanim ) {
			if ( ! is_array( $tanim ) ) {
				continue;
			}

			$ad = trim( sanitize_text_field( (string) ( $tanim['ad'] ?? '' ) ) );
			if ( '' === $ad ) {
				continue;
			}

			$slug = sanitize_key( (string) ( $tanim['slug'] ?? '' ) );
			if ( '' === $slug ) {
				$slug = sanitize_key( sanitize_title( $ad ) );
			}
			if ( '' === $slug ) {
				$slug = 'rozet';
			}

			$temel = $slug;
			$n     = 2;
			while ( isset( $kullanan[ $slug ] ) ) {
				$slug = $temel . '-' . $n;
				++$n;
			}
			$kullanan[ $slug ] = true;

			$renk = sanitize_hex_color( (string) ( $tanim['renk'] ?? '' ) );

			$temiz[] = array(
				'slug' => $slug,
				'ad'   => mb_substr( $ad, 0, 40 ),
				'ikon' => mb_substr( trim( sanitize_text_field( (string) ( $tanim['ikon'] ?? '' ) ) ), 0, 4 ),
				'renk' => $renk ? $renk : self::RENK,
			);

			if ( count( $temiz ) >= self::AZAMI ) {
				break;
			}
		}

		return $temiz;
	}

	/**
	 * Üründe seçili rozet slug'ları.
	 *
	 * @param int $post_id Ürün ID.
	 * @return string[]
	 */
	public static function urun_sluglari( $post_id ) {
		$ham = get_post_meta( (int) $post_id, self::META, true );

		if ( ! is_array( $ham ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'sanitize_key', $ham ) ) );
	}

	/**
	 * Üründeki rozet tanımları (tanımı silinmiş slug'lar elenir).
	 *
	 * @param int $post_id Ürün ID.
	 * @return array<int,array>
	 */
	public static function urun_rozetleri( $post_id ) {
		$harita = self::slug_haritasi();
		$out    = array();

		foreach ( self::urun_sluglari( $post_id ) as $slug ) {
			if ( isset( $harita[ $slug ] ) ) {
				$out[] = $harita[ $slug ];
			}
		}

		return $out;
	}

	/**
	 * Ürün seçimini kaydeder.
	 *
	 * @param int   $post_id Ürün ID.
	 * @param mixed $ham     POST edilen slug listesi.
	 * @return void
	 */
	public static function urun_kaydet( $post_id, $ham ) {
		$post_id  = (int) $post_id;
		$gecerli  = array_keys( self::slug_haritasi() );
		$sluglar  = is_array( $ham ) ? array_map( 'sanitize_key', $ham ) : array();
		$sluglar  = array_values( array_intersect( $sluglar, $gecerli ) );

		if ( empty( $sluglar ) ) {
			delete_post_meta( $post_id, self::META );
			return;
		}

		update_post_meta( $post_id, self::META, $sluglar );
	}

	/**
	 * Menü kartındaki rozet şeridi HTML'i.
	 *
	 * @param int $post_id Ürün ID.
	 * @return string
	 */
	public static function rozet_html( $post_id ) {
		$out = '';

		foreach ( self::urun_rozetleri( $post_id ) as $rozet ) {
			$out .= '<span class="rma-badge rma-badge-ozel" style="--rma-rozet-renk:' . esc_attr( $rozet['renk'] ) . '">'
				. ( '' !== $rozet['ikon'] ? esc_html( $rozet['ikon'] ) . ' ' : '' )
				. esc_html( self::cevir( $rozet['ad'] ) )
				. '</span>';
		}

		return $out;
	}

	/**
	 * Modaldaki özellik etiketleri.
	 *
	 * @param int $post_id Ürün ID.
	 * @return string
	 */
	public static function etiket_html( $post_id ) {
		$out = '';

		foreach ( self::urun_rozetleri( $post_id ) as $rozet ) {
			$out .= '<span class="rma-attr rma-attr-ozel" style="--rma-rozet-renk:' . esc_attr( $rozet['renk'] ) . '">'
				. ( '' !== $rozet['ikon'] ? esc_html( $rozet['ikon'] ) . ' ' : '' )
				. esc_html( self::cevir( $rozet['ad'] ) )
				. '</span>';
		}

		return $out;
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
