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
	 * - Küçük harfe çevrilir ve noktasız ı, i'ye katlanır: "Kahvaltı", "KAHVALTI"
	 *   ve "kahvalti" tek kelime sayılır.
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

		// İ → i (mb_strtolower 'İ'yi i + birleşik nokta yapar), I → i, ı → i.
		$metin = str_replace( array( 'İ', 'ı' ), 'i', $metin );
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
