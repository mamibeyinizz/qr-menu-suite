<?php
/**
 * Ön yüz fiyat biçimi — tek para birimi, döviz yok.
 *
 * Sayı ayracı dile göre; sembol ve sıra ui_string kalıbındadır
 * (`{n} ₺`, `-{n} ₺`, `%{n}`). Intl.NumberFormat ve kur çevrimi yok.
 * Admin kural metni (RMA_Kampanya_DB::bicimle) bu dosyaya dokunmaz.
 *
 * @package QRMenu_Ceviri
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dil kodunun ilk iki harfi.
 *
 * @param string|null $lang Dil; boşsa rma_get_current_lang().
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_fiyat_dil' ) ) {
	function rma_ceviri_fiyat_dil( $lang = null ) {
		if ( null === $lang || '' === (string) $lang ) {
			$lang = function_exists( 'rma_get_current_lang' ) ? rma_get_current_lang() : 'tr';
		}

		return strtolower( substr( (string) $lang, 0, 2 ) );
	}
}

/**
 * Binlik ve ondalık ayracları.
 *
 * tr/de/es/it/pt/nl → 1.234,56
 * fr → 1 234,56
 * en/ar/ru ve diğerleri → 1,234.56
 *
 * @param string|null $lang Dil.
 * @return array{binlik:string,ondalik:string}
 */
if ( ! function_exists( 'rma_ceviri_fiyat_ayraclar' ) ) {
	function rma_ceviri_fiyat_ayraclar( $lang = null ) {
		$kod = rma_ceviri_fiyat_dil( $lang );

		if ( in_array( $kod, array( 'tr', 'de', 'es', 'it', 'pt', 'nl' ), true ) ) {
			return array(
				'binlik'  => '.',
				'ondalik' => ',',
			);
		}

		if ( 'fr' === $kod ) {
			return array(
				'binlik'  => ' ',
				'ondalik' => ',',
			);
		}

		return array(
			'binlik'  => ',',
			'ondalik' => '.',
		);
	}
}

/**
 * Sayıyı dile göre yazar; kuruş yoksa ondalığı düşürür.
 *
 * @param float|int|string $deger Tutar.
 * @param string|null      $lang  Dil.
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_fiyat_sayi' ) ) {
	function rma_ceviri_fiyat_sayi( $deger, $lang = null ) {
		$a     = rma_ceviri_fiyat_ayraclar( $lang );
		$metin = number_format( (float) $deger, 2, $a['ondalik'], $a['binlik'] );
		$sifir = $a['ondalik'] . '00';

		return ( substr( $metin, -3 ) === $sifir ) ? substr( $metin, 0, -3 ) : $metin;
	}
}

/**
 * Üç biçim dizesi (ui_string kaynakları).
 *
 * @return array{fiyat:string,indirim:string,yuzde:string}
 */
if ( ! function_exists( 'rma_ceviri_fiyat_kaliplari' ) ) {
	function rma_ceviri_fiyat_kaliplari() {
		return array(
			'fiyat'   => '{n} ₺',
			'indirim' => '-{n} ₺',
			'yuzde'   => '%{n}',
		);
	}
}

/**
 * Kalıbı ui_string tablosundan geçirir; `{n}` yoksa kaynağa döner.
 *
 * @param string      $kalip Türkçe kalıp.
 * @param string|null $lang  Dil.
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_fiyat_kalip' ) ) {
	function rma_ceviri_fiyat_kalip( $kalip, $lang = null ) {
		$kalip = (string) $kalip;

		if ( function_exists( 'rma_ceviri_modul' ) ) {
			$ceviri = (string) rma_ceviri_modul( 'ui_string', $kalip, $lang );
			if ( '' !== $ceviri && false !== strpos( $ceviri, '{n}' ) ) {
				return $ceviri;
			}
		}

		return $kalip;
	}
}

/**
 * Sayı + kalıp. Çeviri yoksa Türkçe kalıp.
 *
 * @param float|int|string $deger Tutar.
 * @param string           $kalip `{n} ₺` | `-{n} ₺` | `%{n}`.
 * @param string|null      $lang  Dil.
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_fiyat' ) ) {
	function rma_ceviri_fiyat( $deger, $kalip = '{n} ₺', $lang = null ) {
		$sayi  = rma_ceviri_fiyat_sayi( $deger, $lang );
		$bicim = rma_ceviri_fiyat_kalip( $kalip, $lang );

		if ( false === strpos( $bicim, '{n}' ) ) {
			$bicim = '{n} ₺';
		}

		return str_replace( '{n}', $sayi, $bicim );
	}
}

/**
 * sepet.js için kalıplar — tüm diller (menü cache-güvenli).
 *
 * @return array<string,array<string,string>>
 */
if ( ! function_exists( 'rma_ceviri_fiyat_js_kaliplari' ) ) {
	function rma_ceviri_fiyat_js_kaliplari() {
		$diller = function_exists( 'rma_ceviri_aktif_diller' )
			? rma_ceviri_aktif_diller()
			: array( 'en', 'ar', 'de', 'fr', 'ru' );

		if ( ! in_array( 'tr', $diller, true ) ) {
			array_unshift( $diller, 'tr' );
		}

		$out = array();
		foreach ( rma_ceviri_fiyat_kaliplari() as $k => $tr ) {
			$satir = array( 'tr' => $tr );
			foreach ( $diller as $dil ) {
				$dil = strtolower( (string) $dil );
				if ( '' === $dil || 'tr' === $dil ) {
					continue;
				}
				$ceviri = rma_ceviri_fiyat_kalip( $tr, $dil );
				if ( $ceviri !== $tr ) {
					$satir[ $dil ] = $ceviri;
				}
			}
			$out[ $k ] = $satir;
		}

		return $out;
	}
}
