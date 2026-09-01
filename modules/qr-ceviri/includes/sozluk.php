<?php
/**
 * Çeviri sözlüğü — sayfa başına TEK sorgu, iki indeks.
 *
 *  - "anahtar" indeksi: "{tip}|{id}|{alan}" => çeviri
 *    Render noktalarından rma_translate_field() ile okunur.
 *  - "metin" indeksi: orijinal metin => çeviri
 *    Çıktı değiştirici (degistirici.php) bunu kullanır.
 *
 * İkisi de aynı tek sorgudan kurulur; N+1 sorgu yok.
 *
 * @package QRMenu_Ceviri
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Metin indeksine girecek en kısa orijinal (karakter). "TL", "1" gibi metinler kazara değişmesin. */
if ( ! defined( 'RMA_CEVIRI_MIN_UZUNLUK' ) ) {
	define( 'RMA_CEVIRI_MIN_UZUNLUK', 2 );
}

/**
 * Sözlük anahtarı.
 *
 * @param string $item_type Öğe tipi.
 * @param int    $item_id   Öğe ID.
 * @param string $field     Alan.
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_anahtar' ) ) {
	function rma_ceviri_anahtar( $item_type, $item_id, $field ) {
		return $item_type . '|' . (int) $item_id . '|' . $field;
	}
}

/**
 * Önbellek sürümü — her içe aktarımda artar, eski transient'ler geçersizleşir.
 *
 * @return int
 */
if ( ! function_exists( 'rma_ceviri_onbellek_surumu' ) ) {
	function rma_ceviri_onbellek_surumu() {
		return (int) get_option( 'rma_ceviri_onbellek_surumu', 1 );
	}
}

/**
 * Sözlük önbelleğini geçersiz kıl.
 */
if ( ! function_exists( 'rma_ceviri_onbellek_temizle' ) ) {
	function rma_ceviri_onbellek_temizle() {
		update_option( 'rma_ceviri_onbellek_surumu', rma_ceviri_onbellek_surumu() + 1, false );
	}
}

/**
 * Bir orijinal/çeviri çiftini metin indeksine ekle.
 *
 * Hem ham hem esc_html()'li biçim eklenir: aynı metin çıktıda kimi yerde
 * "Kahve & Çay", kimi yerde "Kahve &amp; Çay" olarak basılıyor.
 *
 * @param array  $sozluk   Metin indeksi (referans).
 * @param string $orijinal Orijinal metin.
 * @param string $ceviri   Çeviri.
 */
if ( ! function_exists( 'rma_ceviri_sozluge_ekle' ) ) {
	function rma_ceviri_sozluge_ekle( &$sozluk, $orijinal, $ceviri ) {
		$orijinal = trim( (string) $orijinal );
		$ceviri   = trim( (string) $ceviri );

		if ( '' === $orijinal || '' === $ceviri || $orijinal === $ceviri ) {
			return;
		}
		if ( mb_strlen( $orijinal ) < RMA_CEVIRI_MIN_UZUNLUK ) {
			return;
		}
		// Saf sayı/fiyat gibi değerler çeviriye girmemeli.
		if ( preg_match( '/^[\d\s.,]+$/', $orijinal ) ) {
			return;
		}

		$sozluk[ $orijinal ] = $ceviri;

		$kacisli = esc_html( $orijinal );
		if ( $kacisli !== $orijinal ) {
			$sozluk[ $kacisli ] = esc_html( $ceviri );
		}
	}
}

/**
 * Aktif dilin sözlüğü.
 *
 * @param string|null $lang Dil kodu; null ise geçerli dil.
 * @return array{anahtar:array<string,string>,metin:array<string,string>}
 */
if ( ! function_exists( 'rma_ceviri_sozluk' ) ) {
	function rma_ceviri_sozluk( $lang = null ) {
		static $bellek = array();

		$lang = $lang ? $lang : rma_get_current_lang();

		if ( isset( $bellek[ $lang ] ) ) {
			return $bellek[ $lang ];
		}

		$bos = array(
			'anahtar' => array(),
			'metin'   => array(),
			'hash'    => array(),
		);

		if ( 'tr' === $lang ) {
			$bellek[ $lang ] = $bos;
			return $bos;
		}

		$transient = 'rma_ceviri_sozluk_' . $lang . '_' . rma_ceviri_onbellek_surumu();
		$onbellek  = get_transient( $transient );

		if ( is_array( $onbellek ) && isset( $onbellek['anahtar'], $onbellek['metin'] ) ) {
			if ( ! isset( $onbellek['hash'] ) ) {
				$onbellek['hash'] = array();
			}
			$bellek[ $lang ] = $onbellek;
			return $onbellek;
		}

		$sozluk = $bos;

		foreach ( RMA_Ceviri_Tablo::dil_satirlari( $lang ) as $satir ) {
			$anahtar = rma_ceviri_anahtar( $satir->item_type, $satir->item_id, $satir->field );

			$sozluk['anahtar'][ $anahtar ] = $satir->translated_text;

			if ( function_exists( 'rma_ceviri_hash_kapisi_mi' ) && rma_ceviri_hash_kapisi_mi( $satir->item_type ) ) {
				$sozluk['hash'][ $anahtar ] = isset( $satir->original_hash ) ? (string) $satir->original_hash : '';
				// Metin indeksine girmez: eskimiş option tamponla yanlış yere basılmasın.
				continue;
			}

			rma_ceviri_metin_indeksine_ekle(
				$sozluk['metin'],
				(string) $satir->original_text,
				(string) $satir->translated_text
			);
		}

		set_transient( $transient, $sozluk, DAY_IN_SECONDS );
		$bellek[ $lang ] = $sozluk;

		return $sozluk;
	}
}

/**
 * Bir satırı metin indeksine işle.
 *
 * Orijinal HTML içeriyorsa (Elementor "editor" alanı, uzun açıklamalar) bütün
 * hâliyle tek bir metin düğümüne asla denk gelmez. Bu yüzden orijinal ve
 * çeviri aynı yöntemle metin düğümlerine ayrılıp sırayla eşlenir; düğüm
 * sayıları tutmuyorsa etiketsiz hâlleri eşlenir.
 *
 * @param array  $indeks   Metin indeksi (referans).
 * @param string $orijinal Orijinal metin/HTML.
 * @param string $ceviri   Çeviri.
 */
if ( ! function_exists( 'rma_ceviri_metin_indeksine_ekle' ) ) {
	function rma_ceviri_metin_indeksine_ekle( &$indeks, $orijinal, $ceviri ) {
		if ( false === strpos( $orijinal, '<' ) ) {
			rma_ceviri_sozluge_ekle( $indeks, $orijinal, $ceviri );
			return;
		}

		$o_dugumler = rma_ceviri_metin_dugumleri( $orijinal );
		$c_dugumler = rma_ceviri_metin_dugumleri( $ceviri );

		if ( ! empty( $o_dugumler ) && count( $o_dugumler ) === count( $c_dugumler ) ) {
			foreach ( $o_dugumler as $sira => $dugum ) {
				rma_ceviri_sozluge_ekle( $indeks, $dugum, $c_dugumler[ $sira ] );
			}
			return;
		}

		rma_ceviri_sozluge_ekle( $indeks, wp_strip_all_tags( $orijinal ), wp_strip_all_tags( $ceviri ) );
	}
}

/**
 * Çıktı değiştirici için metin indeksi.
 *
 * @param string|null $lang Dil kodu.
 * @return array<string,string>
 */
if ( ! function_exists( 'rma_ceviri_metin_sozlugu' ) ) {
	function rma_ceviri_metin_sozlugu( $lang = null ) {
		$sozluk = rma_ceviri_sozluk( $lang );
		return $sozluk['metin'];
	}
}

/**
 * Tek alanın çevirisi — tüm render noktalarının ortak giriş kapısı.
 *
 * Çeviri yoksa orijinal döner; hiçbir zaman boş göstermez.
 *
 * @param int         $item_id       Post/term ID; sabit metinlerde 0.
 * @param string      $item_type     product|…|option|form_field|cf_field|cf_form.
 * @param string      $field         Alan anahtarı.
 * @param string      $original_text Orijinal (TR) metin — fallback.
 * @param string|null $lang          Dil kodu; null ise geçerli dil.
 * @return string
 */
if ( ! function_exists( 'rma_translate_field' ) ) {
	function rma_translate_field( $item_id, $item_type, $field, $original_text, $lang = null ) {
		$lang = $lang ? $lang : rma_get_current_lang();

		if ( 'tr' === $lang ) {
			return $original_text;
		}

		$sozluk  = rma_ceviri_sozluk( $lang );
		$anahtar = rma_ceviri_anahtar( $item_type, $item_id, $field );

		if ( isset( $sozluk['anahtar'][ $anahtar ] ) && '' !== $sozluk['anahtar'][ $anahtar ] ) {
			if ( function_exists( 'rma_ceviri_hash_kapisi_mi' )
				&& rma_ceviri_hash_kapisi_mi( $item_type )
				&& isset( $sozluk['hash'] )
			) {
				$hash = isset( $sozluk['hash'][ $anahtar ] ) ? $sozluk['hash'][ $anahtar ] : '';
				if ( ! rma_ceviri_hash_guncel_mi( $original_text, $hash ) ) {
					return $original_text;
				}
			}

			return $sozluk['anahtar'][ $anahtar ];
		}

		return $original_text;
	}
}
