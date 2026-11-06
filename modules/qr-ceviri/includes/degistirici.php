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

/** Alt-dize değiştirmede tek bir desene konan en fazla anahtar (PCRE desen boyutu sınırı). */
if ( ! defined( 'RMA_CEVIRI_DESEN_PARCA' ) ) {
	define( 'RMA_CEVIRI_DESEN_PARCA', 200 );
}

/**
 * Sözlük için kelime sınırlı alt-dize desenleri (istek içi önbellekli).
 *
 * Anahtarlar uzundan kısaya sıralanır (strtr gibi en uzun eşleşme önce).
 * Eşleşmenin iki yanında harf/rakam olamaz: "En Az Karb" anahtarı
 * "En Az Karbonhidratlı" kelimesinin ortasını yakalayıp "Lowest
 * Carbonhidratlı" üretmez.
 *
 * @param string[] $sozluk orijinal => çeviri.
 * @return array<int,string>|false Desenler; derlenemezse false (strtr'ye düşülür).
 */
if ( ! function_exists( 'rma_ceviri_sozluk_desenleri' ) ) {
	function rma_ceviri_sozluk_desenleri( $sozluk ) {
		static $onbellek = array();

		$anahtarlar = array_keys( $sozluk );
		$imza       = count( $anahtarlar ) . ':' . md5( implode( "\0", array_slice( $anahtarlar, 0, 5 ) ) . "\1" . implode( "\0", array_slice( $anahtarlar, -5 ) ) );

		if ( isset( $onbellek[ $imza ] ) ) {
			return $onbellek[ $imza ];
		}

		$anahtarlar = array_values(
			array_filter(
				array_map( 'strval', $anahtarlar ),
				static function ( $a ) {
					return '' !== $a;
				}
			)
		);
		usort(
			$anahtarlar,
			static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);

		$desenler = array();
		foreach ( array_chunk( $anahtarlar, RMA_CEVIRI_DESEN_PARCA ) as $parca ) {
			$alternatif = implode(
				'|',
				array_map(
					static function ( $a ) {
						return preg_quote( $a, '/' );
					},
					$parca
				)
			);
			$desen = '/(?<![\p{L}\p{N}])(?:' . $alternatif . ')(?![\p{L}\p{N}])/u';

			// Derlenebilir mi? (geçersiz UTF-8 / desen boyutu sınırı)
			if ( false === @preg_match( $desen, '' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$onbellek[ $imza ] = false;
				return false;
			}
			$desenler[] = $desen;
		}

		$onbellek[ $imza ] = $desenler;
		return $desenler;
	}
}

/**
 * Tek bir metin düğümünü çevir.
 *
 * Önce TAM eşleşme denenir (en güvenli ve en sık durum: düğümün tamamı
 * çevrilecek metindir). Tutmazsa kelime sınırlı alt-dize geçişi yapılır:
 * en uzun anahtar önce denenir, değiştirilen bölge yeniden taranmaz ("Sepet"
 * anahtarı "Sepete Ekle" çevirisinin içini bozamaz) ve anahtar bir kelimenin
 * ortasında eşleşmez ("En Az Karb" → "En Az Karbonhidratlı" içinde değişmez).
 * Desen derlenemezse eski strtr() davranışına düşülür.
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

		$desenler = rma_ceviri_sozluk_desenleri( $sozluk );
		if ( false === $desenler ) {
			return strtr( $metin, $sozluk );
		}

		foreach ( $desenler as $desen ) {
			$yeni = preg_replace_callback(
				$desen,
				static function ( $m ) use ( $sozluk ) {
					return isset( $sozluk[ $m[0] ] ) ? $sozluk[ $m[0] ] : $m[0];
				},
				$metin
			);
			if ( null === $yeni ) {
				return strtr( $metin, $sozluk );
			}
			$metin = $yeni;
		}

		return $metin;
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
