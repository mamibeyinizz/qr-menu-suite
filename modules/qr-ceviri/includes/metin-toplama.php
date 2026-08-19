<?php
/**
 * Metin Toplama — sitede çevrilmemiş sabit metinleri keşfetme.
 *
 * Tema metinleri, form etiketleri ve üçüncü taraf eklenti çıktıları için
 * yapısal bir kaynak yok: menü öğeleri gibi bir tabloda durmuyorlar, ürün
 * gibi bir post type'ları da yok. Bulmanın tek pratik yolu sayfayı gerçekten
 * basıldığı hâliyle okumak.
 *
 * Bu mod açıkken, YÖNETİCİ siteyi Türkçe gezerken çıktıdaki metin düğümleri
 * toplanır ve yönetim ekranında listelenir; seçilenler "Ek sabit metinler"e
 * geçer ve sonraki CSV dışa aktarımında ui_string satırı olarak çıkar.
 *
 * Çıktıya DOKUNULMAZ — toplama geri çağrısı HTML'i olduğu gibi döndürür.
 *
 * @package QRMenu_Ceviri
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Seçenekte tutulacak en fazla aday. */
if ( ! defined( 'RMA_CEVIRI_TOPLAMA_LIMIT' ) ) {
	define( 'RMA_CEVIRI_TOPLAMA_LIMIT', 500 );
}

/** Aday metnin en fazla uzunluğu (karakter). */
if ( ! defined( 'RMA_CEVIRI_TOPLAMA_MAX_UZUNLUK' ) ) {
	define( 'RMA_CEVIRI_TOPLAMA_MAX_UZUNLUK', 200 );
}

/**
 * Toplama modu yönetim ekranından açık mı?
 *
 * @return bool
 */
if ( ! function_exists( 'rma_ceviri_toplama_acik_mi' ) ) {
	function rma_ceviri_toplama_acik_mi() {
		return (bool) get_option( 'rma_ceviri_toplama_acik', 0 );
	}
}

/**
 * Bu istekte toplama çalışmalı mı?
 *
 * Üç koşul birden: mod açık, istek yapan yönetici, sayfa Türkçe basılıyor.
 * Ziyaretçiye maliyet çıkmasın ve sayfa cache'i etkilenmesin diye
 * yalnızca giriş yapmış yöneticide çalışır. Türkçe şartı, çevrilmiş
 * metinlerin aday olarak toplanmasını engeller.
 *
 * @return bool
 */
if ( ! function_exists( 'rma_ceviri_toplama_calissin_mi' ) ) {
	function rma_ceviri_toplama_calissin_mi() {
		return rma_ceviri_toplama_acik_mi()
			&& is_user_logged_in()
			&& current_user_can( 'manage_options' )
			&& 'tr' === rma_get_current_lang();
	}
}

/**
 * Bu metin çeviri adayı olabilir mi?
 *
 * WordPress'e bağımsız — test edilebilir.
 *
 * @param string $metin Kırpılmış metin düğümü.
 * @return bool
 */
if ( ! function_exists( 'rma_ceviri_toplanabilir_mi' ) ) {
	function rma_ceviri_toplanabilir_mi( $metin ) {
		$metin = trim( (string) $metin );

		if ( '' === $metin ) {
			return false;
		}

		$uzunluk = mb_strlen( $metin );
		if ( $uzunluk < 2 || $uzunluk > RMA_CEVIRI_TOPLAMA_MAX_UZUNLUK ) {
			return false;
		}

		// En az bir harf içermeli — sayı, fiyat, tarih ve simgeleri eler.
		if ( ! preg_match( '/\p{L}/u', $metin ) ) {
			return false;
		}

		// Süslü parantez = satır içi CSS/JS artığı.
		if ( false !== strpos( $metin, '{' ) || false !== strpos( $metin, '}' ) ) {
			return false;
		}

		// Tek kelimelik teknik değerler (dosya adı, sınıf adı, URL parçası).
		if ( preg_match( '#^[a-z0-9_\-./]+$#', $metin ) ) {
			return false;
		}

		return true;
	}
}

/**
 * Toplanmış adaylar: metin => ilk görülme zamanı.
 *
 * @return array<string,int>
 */
if ( ! function_exists( 'rma_ceviri_bulunan_metinler' ) ) {
	function rma_ceviri_bulunan_metinler() {
		$liste = get_option( 'rma_ceviri_bulunan_metinler', array() );
		return is_array( $liste ) ? $liste : array();
	}
}

/**
 * Aday listesini yaz.
 *
 * @param array<string,int> $liste Liste.
 */
if ( ! function_exists( 'rma_ceviri_bulunanlari_yaz' ) ) {
	function rma_ceviri_bulunanlari_yaz( $liste ) {
		update_option( 'rma_ceviri_bulunan_metinler', $liste, false );
	}
}

/**
 * HTML çıktısındaki metinleri topla.
 *
 * Çıktıyı DEĞİŞTİRMEZ; yalnızca okur ve yeni adayları seçeneğe ekler.
 *
 * @param string $html Çıktı.
 * @return string Aynı çıktı.
 */
if ( ! function_exists( 'rma_ceviri_metin_topla' ) ) {
	function rma_ceviri_metin_topla( $html ) {
		$mevcut = rma_ceviri_bulunan_metinler();

		if ( count( $mevcut ) >= RMA_CEVIRI_TOPLAMA_LIMIT ) {
			return $html;
		}

		// Zaten bilinen sabit metinler yeniden önerilmesin.
		$bilinen = array_flip( array_values( rma_ceviri_ui_stringleri() ) );
		$eklendi = false;
		$simdi   = time();

		foreach ( rma_ceviri_metin_dugumleri( $html ) as $metin ) {
			if ( count( $mevcut ) >= RMA_CEVIRI_TOPLAMA_LIMIT ) {
				break;
			}
			if ( isset( $mevcut[ $metin ] ) || isset( $bilinen[ $metin ] ) ) {
				continue;
			}
			if ( ! rma_ceviri_toplanabilir_mi( $metin ) ) {
				continue;
			}

			$mevcut[ $metin ] = $simdi;
			$eklendi          = true;
		}

		if ( $eklendi ) {
			rma_ceviri_bulunanlari_yaz( $mevcut );
		}

		return $html;
	}
}

/**
 * JSON yanıtındaki metinleri topla (AJAX ile gelen formlar için).
 *
 * @param mixed $veri Çözülmüş JSON.
 */
if ( ! function_exists( 'rma_ceviri_json_metin_topla' ) ) {
	function rma_ceviri_json_metin_topla( $veri ) {
		if ( is_string( $veri ) ) {
			rma_ceviri_metin_topla( $veri );
			return;
		}

		if ( is_array( $veri ) || is_object( $veri ) ) {
			foreach ( (array) $veri as $deger ) {
				rma_ceviri_json_metin_topla( $deger );
			}
		}
	}
}

/**
 * Seçilen adayları "Ek sabit metinler" kutusuna taşı ve listeden düşür.
 *
 * @param string[] $secilenler Taşınacak metinler.
 * @return int Taşınan adet.
 */
if ( ! function_exists( 'rma_ceviri_bulunanlari_aktar' ) ) {
	function rma_ceviri_bulunanlari_aktar( $secilenler ) {
		$secilenler = array_filter( array_map( 'trim', (array) $secilenler ) );

		if ( empty( $secilenler ) ) {
			return 0;
		}

		$mevcut_ham = (string) get_option( 'rma_ceviri_ek_metinler', '' );
		$satirlar   = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $mevcut_ham ) ) );

		$liste   = rma_ceviri_bulunan_metinler();
		$tasinan = 0;

		foreach ( $secilenler as $metin ) {
			if ( ! in_array( $metin, $satirlar, true ) ) {
				$satirlar[] = $metin;
			}
			unset( $liste[ $metin ] );
			++$tasinan;
		}

		update_option( 'rma_ceviri_ek_metinler', implode( "\n", $satirlar ), false );
		rma_ceviri_bulunanlari_yaz( $liste );

		return $tasinan;
	}
}
