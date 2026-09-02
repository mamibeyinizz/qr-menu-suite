<?php
/**
 * Porsiyon / varyasyon seçenekleri.
 *
 * Ürünün TEK bir taban fiyatı vardır (`rma_price`); porsiyonlar bu fiyata
 * eklenen FARK olarak tutulur ("Büyük +40 ₺"). Bunun iki sebebi var:
 *
 *  1. Fiyat kampanyası taban fiyatı üzerinden hesaplanır (bkz. class-kampanya.php).
 *     Porsiyon mutlak fiyat olsaydı kampanya porsiyonlu ürünleri atlardı.
 *  2. Toplu zam/indirim tek alanı güncellemekle biter; porsiyon farkları sabittir.
 *
 * Fark 0 olan seçenek "standart" porsiyondur; hiç tanımlanmamışsa render
 * sırasında başa otomatik eklenir — müşteri her zaman taban fiyatı seçebilir.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'RMA_Porsiyon' ) ) :

class RMA_Porsiyon {

	/** Post meta anahtarı (gizli). */
	const META = '_rma_porsiyonlar';

	/** Azami porsiyon sayısı — arayüz de bu sınırı uygular. */
	const AZAMI = 8;

	/**
	 * Ürünün porsiyon listesi.
	 *
	 * @param int $post_id Ürün ID.
	 * @return array<int,array{ad:string,fark:float}>
	 */
	public static function oku( $post_id ) {
		$ham = get_post_meta( (int) $post_id, self::META, true );

		return self::temizle( $ham );
	}

	/**
	 * Ürünün porsiyonu var mı?
	 *
	 * @param int $post_id Ürün ID.
	 * @return bool
	 */
	public static function var_mi( $post_id ) {
		return array() !== self::oku( $post_id );
	}

	/**
	 * Ham diziyi doğrular ve normalize eder.
	 *
	 * @param mixed $ham Kaydedilmiş / POST edilmiş dizi.
	 * @return array<int,array{ad:string,fark:float}>
	 */
	public static function temizle( $ham ) {
		if ( ! is_array( $ham ) ) {
			return array();
		}

		$temiz = array();

		foreach ( $ham as $satir ) {
			if ( ! is_array( $satir ) ) {
				continue;
			}

			$ad = sanitize_text_field( (string) ( $satir['ad'] ?? '' ) );
			$ad = trim( $ad );

			if ( '' === $ad ) {
				continue;
			}

			$temiz[] = array(
				'ad'   => mb_substr( $ad, 0, 60 ),
				'fark' => self::sayi( $satir['fark'] ?? 0 ),
			);

			if ( count( $temiz ) >= self::AZAMI ) {
				break;
			}
		}

		return $temiz;
	}

	/**
	 * Kaydeder. Boş liste meta'yı siler (wp_options/postmeta şişmesin).
	 *
	 * @param int   $post_id Ürün ID.
	 * @param mixed $ham     POST edilen dizi.
	 * @return void
	 */
	public static function kaydet( $post_id, $ham ) {
		$post_id = (int) $post_id;
		$temiz   = self::temizle( $ham );

		if ( empty( $temiz ) ) {
			delete_post_meta( $post_id, self::META );
			return;
		}

		update_post_meta( $post_id, self::META, $temiz );
	}

	/**
	 * Müşteriye gösterilecek liste — standart porsiyon garanti edilir.
	 *
	 * @param int $post_id Ürün ID.
	 * @return array<int,array{ad:string,fark:float}>
	 */
	public static function gosterim_listesi( $post_id ) {
		$liste = self::oku( $post_id );

		if ( empty( $liste ) ) {
			return array();
		}

		foreach ( $liste as $satir ) {
			if ( abs( $satir['fark'] ) < 0.005 ) {
				return $liste;
			}
		}

		array_unshift(
			$liste,
			array(
				'ad'   => self::cevir( 'Standart' ),
				'fark' => 0.0,
			)
		);

		return $liste;
	}

	/**
	 * Modaldaki porsiyon seçim bloğu.
	 *
	 * Fiyat farkı `data-fark` niteliğinde SAYI olarak durur: sepet betiği
	 * (qr-chatbot/assets/js/sepet.js) toplamı buradan hesaplar, metni
	 * ayrıştırmaya çalışmaz.
	 *
	 * @param int $post_id Ürün ID.
	 * @return string
	 */
	public static function html( $post_id ) {
		$liste = self::gosterim_listesi( $post_id );

		if ( empty( $liste ) ) {
			return '';
		}

		$grup = 'rma-porsiyon-' . (int) $post_id;
		$out  = '<div class="rma-porsiyon" data-rma-porsiyon="1">'
			. '<div class="rma-secenek-baslik">' . esc_html( self::cevir( 'Porsiyon' ) ) . '</div>'
			. '<div class="rma-porsiyon-liste">';

		foreach ( $liste as $i => $satir ) {
			$fark_metni = self::fark_metni( $satir['fark'] );

			$out .= '<label class="rma-porsiyon-sec">'
				. '<input type="radio" name="' . esc_attr( $grup ) . '" value="' . esc_attr( $satir['ad'] ) . '"'
				. ' data-fark="' . esc_attr( self::ondalik( $satir['fark'] ) ) . '"'
				. ( 0 === $i ? ' checked' : '' ) . '>'
				. '<span class="rma-porsiyon-ad">' . esc_html( self::cevir_ad( $post_id, $satir['ad'] ) ) . '</span>'
				. ( '' !== $fark_metni ? '<span class="rma-porsiyon-fark">' . esc_html( $fark_metni ) . '</span>' : '' )
				. '</label>';
		}

		return $out . '</div></div>';
	}

	/**
	 * "+40 ₺" / "-20 ₺" — fark sıfırsa boş.
	 *
	 * @param float $fark Fiyat farkı.
	 * @return string
	 */
	public static function fark_metni( $fark ) {
		$fark = (float) $fark;

		if ( abs( $fark ) < 0.005 ) {
			return '';
		}

		$kalip = $fark > 0 ? '+{n} ₺' : '-{n} ₺';

		return class_exists( 'RMA_Kampanya' )
			? RMA_Kampanya::fiyat_yazi( abs( $fark ), $kalip )
			: str_replace( '{n}', (string) abs( $fark ), $kalip );
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
	 * data-* niteliğine yazılacak nokta ayraçlı sayı.
	 *
	 * @param float $deger Tutar.
	 * @return string
	 */
	private static function ondalik( $deger ) {
		return number_format( (float) $deger, 2, '.', '' );
	}

	/**
	 * Porsiyon adı çevirisi — çeviri modülü yoksa orijinal.
	 *
	 * @param int    $post_id Ürün ID.
	 * @param string $ad      Porsiyon adı.
	 * @return string
	 */
	private static function cevir_ad( $post_id, $ad ) {
		return function_exists( 'rma_translate_field' )
			? rma_translate_field( (int) $post_id, 'porsiyon', sanitize_title( $ad ), $ad )
			: $ad;
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
