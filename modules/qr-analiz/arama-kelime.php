<?php
/**
 * Menüde bulunamayan arama kelimelerinin temizlenmesi (saf fonksiyon).
 *
 * Ayrı dosyada durur ki WordPress yüklenmeden test edilebilsin
 * (tests/arama-kelime-test.php). Kelime analitik tablosuna serbest metin olarak
 * girer; bu yüzden burada hem görüntü (HTML/betik) hem de gürültü (tek harf,
 * yalnız sayı, aşırı uzun) elenir.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qrms_analitik_arama_kelimesi' ) ) {

	/**
	 * Aranan metni kayıt için normalleştirir.
	 *
	 * - HTML etiketleri ve denetim karakterleri atılır; harf, rakam, boşluk ve
	 *   `' ’ & . - +` dışındaki işaretler silinir (emoji, `<`, `%` vb.); baştaki ve
	 *   sondaki noktalama da atılır.
	 * - Küçük harfe çevrilir (İ ve I → i; yazılmış küçük ı korunur). "Kahvaltı" ile
	 *   "kahvalti" ayrı kaydedilir ama raporda tek kelime sayılır (bkz.
	 *   qrms_analitik_arama_katla / qrms_analitik_arama_birlestir).
	 * - 2 karakterden kısa, yalnız rakamlardan oluşan (fiyat/adet araması) ya da
	 *   harf içermeyen metin kaydedilmez ('' döner).
	 * - En fazla 60 karakter.
	 *
	 * @param mixed $metin Aranan metin.
	 * @return string Temiz kelime ya da kaydedilmeyecekse ''.
	 */
	function qrms_analitik_arama_kelimesi( $metin ) {
		if ( ! is_string( $metin ) ) {
			return '';
		}

		$metin = strip_tags( $metin );
		$metin = preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $metin );

		if ( ! is_string( $metin ) ) {
			return '';
		}

		$metin = preg_replace( '/[^\p{L}\p{N}\s\'’&.\-+]+/u', '', $metin );

		if ( ! is_string( $metin ) ) {
			return '';
		}

		$metin = trim( (string) preg_replace( '/\s+/u', ' ', $metin ) );

		// Baştaki/sondaki noktalama anlamsızdır (`'sushi`, `sushi--`): atılır.
		$metin = trim( (string) preg_replace( '/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $metin ) );

		if ( '' === $metin ) {
			return '';
		}

		// İ → i (mb_strtolower 'İ'yi i + birleşik nokta yapar); I → i (mb_strtolower).
		// Küçük noktasız ı KORUNUR ("kahvaltı" okunur kalır); gruplama için bkz. katlama.
		$metin = str_replace( 'İ', 'i', $metin );
		$metin = function_exists( 'mb_strtolower' ) ? mb_strtolower( $metin, 'UTF-8' ) : strtolower( $metin );
		$metin = str_replace( "i\u{0307}", 'i', $metin );

		if ( function_exists( 'mb_substr' ) ) {
			$metin = trim( mb_substr( $metin, 0, 60, 'UTF-8' ) );
		} else {
			$metin = trim( substr( $metin, 0, 60 ) );
		}

		$uzunluk = function_exists( 'mb_strlen' ) ? mb_strlen( $metin, 'UTF-8' ) : strlen( $metin );

		if ( $uzunluk < 2 || ! preg_match( '/\p{L}/u', $metin ) ) {
			return '';
		}

		return $metin;
	}
}

if ( ! function_exists( 'qrms_analitik_arama_katla' ) ) {

	/**
	 * Gruplama anahtarı: noktasız ı → i. "kahvaltı" ve "kahvalti" aynı kelimedir.
	 *
	 * @param string $kelime Temizlenmiş kelime.
	 * @return string
	 */
	function qrms_analitik_arama_katla( $kelime ) {
		return str_replace( 'ı', 'i', (string) $kelime );
	}
}

if ( ! function_exists( 'qrms_analitik_arama_birlestir' ) ) {

	/**
	 * Aynı ziyaretçinin harf harf yazdığı kelimeyi tek kayda indirir ve yazım
	 * varyantlarını ("kahvaltı"/"kahvalti") birleştirir — saf fonksiyon.
	 *
	 * Birleştirme OKUMA anında yapılır: yavaş yazımda istekler üst üste bindiği için
	 * sunucuda yazma anında "bir önceki kelime" bilgisine güvenilemez.
	 *
	 * Kural: aynı ziyaretçinin ardışık iki kaydı arasında en çok $pencere sn varsa ve
	 * biri diğerinin başlangıcıysa (katlanmış karşılaştırma) ikisi aynı yazımdır;
	 * en uzun hâli tutulur ("sus", "sush", "sushi" → "sushi"; geri silme: "sushi",
	 * "sush" → "sushi"). Aynı kelimenin pencere içinde tekrarı da tek sayılır.
	 *
	 * @param array<int,array{kelime:string,ip:string,t:int}> $kayitlar Kayıtlar (sırasız olabilir).
	 * @param int                                              $pencere Saniye.
	 * @return array<string,int> Görüntülenecek kelime => adet (adete göre azalan, eşitlikte alfabetik).
	 */
	function qrms_analitik_arama_birlestir( array $kayitlar, $pencere = 10 ) {
		$grup = array();

		foreach ( $kayitlar as $k ) {
			if ( ! is_array( $k ) || ! isset( $k['kelime'], $k['ip'], $k['t'] ) || '' === (string) $k['kelime'] ) {
				continue;
			}

			$grup[ (string) $k['ip'] ][] = array( (int) $k['t'], (string) $k['kelime'] );
		}

		$sonuc = array(); // katlanmış anahtar => array( 'adet' => n, 'varyant' => array( kelime => adet ) )

		foreach ( $grup as $satirlar ) {
			usort(
				$satirlar,
				function ( $a, $b ) {
					return $a[0] <=> $b[0] ?: strcmp( $a[1], $b[1] );
				}
			);

			$zincir_kelime = null;
			$onceki_t      = 0;
			$bayrak        = function ( $kelime ) use ( &$sonuc ) {
				$anahtar = qrms_analitik_arama_katla( $kelime );

				if ( ! isset( $sonuc[ $anahtar ] ) ) {
					$sonuc[ $anahtar ] = array( 'adet' => 0, 'varyant' => array() );
				}

				++$sonuc[ $anahtar ]['adet'];
				$sonuc[ $anahtar ]['varyant'][ $kelime ] = ( $sonuc[ $anahtar ]['varyant'][ $kelime ] ?? 0 ) + 1;
			};

			foreach ( $satirlar as $satir ) {
				list( $t, $kelime ) = $satir;

				if ( null !== $zincir_kelime && ( $t - $onceki_t ) <= $pencere ) {
					$a = qrms_analitik_arama_katla( $zincir_kelime );
					$b = qrms_analitik_arama_katla( $kelime );

					if ( 0 === strpos( $b, $a ) ) {
						$zincir_kelime = $kelime; // Yazmaya devam: daha uzun hâli tut.
						$onceki_t      = $t;
						continue;
					}

					if ( 0 === strpos( $a, $b ) ) {
						$onceki_t = $t; // Geri silme ya da tekrar: zincirin kelimesi kalır.
						continue;
					}
				}

				if ( null !== $zincir_kelime ) {
					$bayrak( $zincir_kelime );
				}

				$zincir_kelime = $kelime;
				$onceki_t      = $t;
			}

			if ( null !== $zincir_kelime ) {
				$bayrak( $zincir_kelime );
			}
		}

		$cikti = array();

		foreach ( $sonuc as $veri ) {
			// Görüntü: en çok yazılan varyant; eşitlikte noktasız ı içeren (daha doğru Türkçe), sonra alfabetik.
			$varyantlar = $veri['varyant'];
			uksort(
				$varyantlar,
				function ( $a, $b ) use ( $varyantlar ) {
					return ( $varyantlar[ $b ] <=> $varyantlar[ $a ] )
						?: ( ( false !== strpos( $b, 'ı' ) ) <=> ( false !== strpos( $a, 'ı' ) ) )
						?: strcmp( $a, $b );
				}
			);
			$cikti[ (string) array_key_first( $varyantlar ) ] = $veri['adet'];
		}

		uksort(
			$cikti,
			function ( $a, $b ) use ( $cikti ) {
				return ( $cikti[ $b ] <=> $cikti[ $a ] ) ?: strcmp( $a, $b );
			}
		);

		return $cikti;
	}
}
