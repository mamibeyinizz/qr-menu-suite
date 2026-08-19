<?php
/**
 * HTML/JSON çıktısı üzerinde güvenli metin değiştirme.
 *
 * Ham str_replace çıktıyı bozar: "Sepete Ekle" metni aynı zamanda
 * class="sepete-ekle" içinde, bir <script> bloğundaki JSON'da ya da bir
 * data- özniteliğinde geçebilir. Bu yüzden çıktı önce etiket/metin diye
 * parçalanır ve YALNIZCA metin düğümlerine dokunulur.
 *
 * Buradaki fonksiyonlar bilinçli olarak WordPress'e bağımsızdır — saf
 * girdi/çıktı oldukları için test edilebilirler.
 *
 * @package QRMenu_Ceviri
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Etiketleri ve metin düğümlerini ayıran desen.
 *
 * - `<!-- … -->` yorumları (içinde `>` olabilir) ayrı ele alınır.
 * - Öznitelik değerleri tırnak içinde tüketilir; böylece
 *   `<div data-x="a>b">` gibi bir etiket ortasından bölünmez.
 *   ("unrolling the loop" deseni — geri izleme patlaması yapmaz.)
 */
if ( ! defined( 'RMA_CEVIRI_ETIKET_DESEN' ) ) {
	define(
		'RMA_CEVIRI_ETIKET_DESEN',
		'/(<!--.*?-->|<\/?[a-zA-Z!][^>"\']*(?:(?:"[^"]*"|\'[^\']*\')[^>"\']*)*>)/s'
	);
}

/** İçeriği asla çevrilmeyecek etiketler. */
if ( ! defined( 'RMA_CEVIRI_KAPALI_ETIKETLER' ) ) {
	define( 'RMA_CEVIRI_KAPALI_ETIKETLER', 'script|style|textarea|code|pre' );
}

/**
 * HTML'i [metin, etiket, metin, etiket, …] dizisine böl.
 *
 * Çift indeksler metin, tek indeksler etikettir (PREG_SPLIT_DELIM_CAPTURE).
 *
 * @param string $html HTML.
 * @return array|false Parçalar; desen çalışmazsa false.
 */
if ( ! function_exists( 'rma_ceviri_html_parcala' ) ) {
	function rma_ceviri_html_parcala( $html ) {
		return preg_split( RMA_CEVIRI_ETIKET_DESEN, $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	}
}

/**
 * Bir HTML parçasındaki çevrilebilir metin düğümleri (kırpılmış, boşlar atılmış).
 *
 * Sözlük kurulurken HTML içeren orijinalleri (Elementor "editor" alanı gibi)
 * çevirisiyle eşlemek için kullanılır.
 *
 * @param string $html HTML.
 * @return string[]
 */
if ( ! function_exists( 'rma_ceviri_metin_dugumleri' ) ) {
	function rma_ceviri_metin_dugumleri( $html ) {
		$parcalar = rma_ceviri_html_parcala( (string) $html );
		if ( false === $parcalar ) {
			return array();
		}

		$dugumler = array();
		$atla     = 0;
		$adet     = count( $parcalar );

		for ( $i = 0; $i < $adet; $i++ ) {
			if ( $i % 2 ) {
				$atla = rma_ceviri_kapali_etiket_sayaci( $parcalar[ $i ], $atla );
				continue;
			}
			if ( $atla > 0 ) {
				continue;
			}

			$metin = trim( $parcalar[ $i ] );
			if ( '' !== $metin ) {
				$dugumler[] = $metin;
			}
		}

		return $dugumler;
	}
}

/**
 * script/style/textarea derinliğini güncelle.
 *
 * @param string $etiket Etiket dizesi.
 * @param int    $atla   Mevcut derinlik.
 * @return int Yeni derinlik.
 */
if ( ! function_exists( 'rma_ceviri_kapali_etiket_sayaci' ) ) {
	function rma_ceviri_kapali_etiket_sayaci( $etiket, $atla ) {
		$kapali = RMA_CEVIRI_KAPALI_ETIKETLER;

		if ( preg_match( '#^<\s*/\s*(' . $kapali . ')\b#i', $etiket ) ) {
			return $atla > 0 ? $atla - 1 : 0;
		}

		// Kendiliğinden kapanan (<script … />) sayılmaz.
		if ( preg_match( '#^<\s*(' . $kapali . ')\b#i', $etiket ) && ! preg_match( '#/\s*>$#', $etiket ) ) {
			return $atla + 1;
		}

		return $atla;
	}
}

/**
 * Tek bir metin düğümünü çevir.
 *
 * Önce TAM eşleşme denenir (en güvenli ve en sık durum: düğümün tamamı
 * çevrilecek metindir). Tutmazsa alt-dize geçişi yapılır: strtr() dizi ile
 * en uzun anahtarı önce dener ve değiştirdiği bölgeyi yeniden taramaz, yani
 * "Sepet" anahtarı "Sepete Ekle" çevirisinin içini bozamaz.
 *
 * @param string   $metin  Metin düğümü (kırpılmamış).
 * @param string[] $sozluk orijinal => çeviri.
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_metin_degistir' ) ) {
	function rma_ceviri_metin_degistir( $metin, $sozluk ) {
		$kirpik = trim( $metin );

		if ( '' === $kirpik ) {
			return $metin;
		}

		if ( isset( $sozluk[ $kirpik ] ) ) {
			// Çevresindeki boşlukları koru (satır sonu/girinti bozulmasın).
			$bas = strlen( $metin ) - strlen( ltrim( $metin ) );
			$son = strlen( $metin ) - strlen( rtrim( $metin ) );

			return substr( $metin, 0, $bas )
				. $sozluk[ $kirpik ]
				. ( $son > 0 ? substr( $metin, -$son ) : '' );
		}

		return strtr( $metin, $sozluk );
	}
}

/**
 * HTML çıktısındaki metin düğümlerini çevir.
 *
 * @param string   $html   HTML.
 * @param string[] $sozluk orijinal => çeviri.
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_html_degistir' ) ) {
	function rma_ceviri_html_degistir( $html, $sozluk ) {
		if ( '' === (string) $html || empty( $sozluk ) ) {
			return $html;
		}

		$parcalar = rma_ceviri_html_parcala( (string) $html );
		if ( false === $parcalar ) {
			// Desen çalışmadıysa çıktıyı riske atmaktansa olduğu gibi bırak.
			return $html;
		}

		$atla = 0;
		$adet = count( $parcalar );

		for ( $i = 0; $i < $adet; $i++ ) {
			if ( $i % 2 ) {
				$atla = rma_ceviri_kapali_etiket_sayaci( $parcalar[ $i ], $atla );
				continue;
			}
			if ( $atla > 0 || '' === $parcalar[ $i ] ) {
				continue;
			}

			$parcalar[ $i ] = rma_ceviri_metin_degistir( $parcalar[ $i ], $sozluk );
		}

		return implode( '', $parcalar );
	}
}

/**
 * JSON yanıtındaki metinleri çevir (recursive).
 *
 * AJAX menü yükleme tipik olarak JSON içinde HTML döndürür; her string değer
 * HTML değiştiriciden geçirilir. Anahtarlara dokunulmaz.
 *
 * @param mixed    $veri   Çözülmüş JSON.
 * @param string[] $sozluk orijinal => çeviri.
 * @return mixed
 */
if ( ! function_exists( 'rma_ceviri_json_degistir' ) ) {
	function rma_ceviri_json_degistir( $veri, $sozluk ) {
		if ( is_string( $veri ) ) {
			return rma_ceviri_html_degistir( $veri, $sozluk );
		}

		if ( is_array( $veri ) ) {
			foreach ( $veri as $anahtar => $deger ) {
				$veri[ $anahtar ] = rma_ceviri_json_degistir( $deger, $sozluk );
			}
			return $veri;
		}

		if ( is_object( $veri ) ) {
			foreach ( get_object_vars( $veri ) as $anahtar => $deger ) {
				$veri->$anahtar = rma_ceviri_json_degistir( $deger, $sozluk );
			}
			return $veri;
		}

		return $veri;
	}
}
