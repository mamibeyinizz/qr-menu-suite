<?php
/**
 * Elementor sayfalarındaki çevrilebilir metinleri çıkarma.
 *
 * Elementor tüm içeriği `_elementor_data` postmeta'sında iç içe JSON olarak
 * tutar ve her widget'ın `settings` şeması widget tipine göre değişir; sabit
 * bir alan listesi çıkarmak mümkün değil. Bu yüzden JSON recursive gezilir ve
 * "metin gibi duran" değerler toplanır.
 *
 * Alan anahtarı widget'ın kendi benzersiz `id`'sine dayanır
 * ({widget_id}.{key}), böylece aynı sayfada aynı widget tipi defalarca geçse
 * bile satırlar birbirine karışmaz. Tekrarlayıcılar (accordion, tabs, ikon
 * listesi) için araya öğe kimliği girer: {widget_id}.{repeater}.{item_id}.{key}.
 *
 * @package QRMenu_Ceviri
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Çok uzun değerler (kod blokları, gömülü JSON) taranmaz. */
if ( ! defined( 'RMA_CEVIRI_ELEMENTOR_MAX' ) ) {
	define( 'RMA_CEVIRI_ELEMENTOR_MAX', 20000 );
}

/**
 * Anahtar adı bir metin alanına mı işaret ediyor?
 *
 * Sondan eşleşme kullanılıyor: "title" ve "tab_title" metindir ama
 * "title_size", "text_color", "content_width" değildir.
 *
 * @param string $anahtar Ayar anahtarı.
 * @return bool
 */
if ( ! function_exists( 'rma_ceviri_elementor_metin_alani_mi' ) ) {
	function rma_ceviri_elementor_metin_alani_mi( $anahtar ) {
		if ( ! is_string( $anahtar ) || '' === $anahtar || '_' === $anahtar[0] ) {
			return false;
		}

		return (bool) preg_match(
			'/(^|_)(title|heading|text|editor|description|content|caption|label|subtitle|placeholder|message)$/i',
			$anahtar
		);
	}
}

/**
 * Değer gerçekten çevrilebilir bir metin mi?
 *
 * URL, renk kodu, CSS ölçüsü, sayı ve bayrak değerleri elenir; içinde en az
 * bir harf bulunmayan hiçbir değer alınmaz.
 *
 * @param mixed $deger Ayar değeri.
 * @return bool
 */
if ( ! function_exists( 'rma_ceviri_elementor_cevrilebilir_mi' ) ) {
	function rma_ceviri_elementor_cevrilebilir_mi( $deger ) {
		if ( ! is_string( $deger ) ) {
			return false;
		}

		$deger = trim( $deger );

		if ( '' === $deger || strlen( $deger ) > RMA_CEVIRI_ELEMENTOR_MAX ) {
			return false;
		}

		// En az bir harf içermeli — sayı, ölçü ve renk kodlarını tek hamlede eler.
		if ( ! preg_match( '/\p{L}/u', $deger ) ) {
			return false;
		}

		// URL / bağlantı hedefi.
		if ( preg_match( '#^(https?:)?//#i', $deger ) || preg_match( '/^(mailto:|tel:|\#|\/)/i', $deger ) ) {
			return false;
		}

		// CSS ölçüsü ve renk anahtar kelimeleri.
		if ( preg_match( '/^-?\d+(\.\d+)?(px|em|rem|%|vh|vw|pt|deg|s|ms)$/i', $deger ) ) {
			return false;
		}

		// Bayrak/seçenek değerleri.
		if ( in_array( strtolower( $deger ), array( 'yes', 'no', 'true', 'false', 'default', 'none', 'left', 'right', 'center', 'justify', 'top', 'bottom', 'solid', 'dashed', 'dotted' ), true ) ) {
			return false;
		}

		// Yalnızca kısa koddan ibaret değer.
		if ( preg_match( '/^\[[^\]]+\]$/', $deger ) ) {
			return false;
		}

		return true;
	}
}

/**
 * Bir widget'ın settings dizisini tara.
 *
 * @param array  $ayarlar   settings dizisi.
 * @param string $widget_id Widget'ın Elementor id'si.
 * @param array  $satirlar  Toplanan satırlar (referans).
 * @param string $onek      Tekrarlayıcı ön eki ("tabs.a1b2.").
 */
if ( ! function_exists( 'rma_ceviri_elementor_ayarlari_tara' ) ) {
	function rma_ceviri_elementor_ayarlari_tara( $ayarlar, $widget_id, &$satirlar, $onek = '' ) {
		foreach ( $ayarlar as $anahtar => $deger ) {
			if ( is_string( $deger ) ) {
				if ( rma_ceviri_elementor_metin_alani_mi( $anahtar ) && rma_ceviri_elementor_cevrilebilir_mi( $deger ) ) {
					$alan = $widget_id . '.' . $onek . $anahtar;

					// field sütunu 191 karakter — aşarsa kısalt (hash ile benzersiz kalır).
					if ( strlen( $alan ) > 191 ) {
						$alan = substr( $alan, 0, 182 ) . '_' . substr( md5( $alan ), 0, 8 );
					}

					$satirlar[] = array(
						'field'    => $alan,
						'original' => trim( $deger ),
					);
				}
				continue;
			}

			// Tekrarlayıcı: ardışık dizi, her elemanı kendi _id'si olan bir ayar dizisi.
			if ( is_array( $deger ) ) {
				foreach ( $deger as $sira => $eleman ) {
					if ( ! is_array( $eleman ) ) {
						continue;
					}

					$eleman_id = isset( $eleman['_id'] ) && is_string( $eleman['_id'] )
						? $eleman['_id']
						: (string) $sira;

					rma_ceviri_elementor_ayarlari_tara(
						$eleman,
						$widget_id,
						$satirlar,
						$onek . $anahtar . '.' . $eleman_id . '.'
					);
				}
			}
		}
	}
}

/**
 * Elementor düğüm ağacını recursive gez.
 *
 * @param array $dugum    Düğüm (section/column/widget).
 * @param array $satirlar Toplanan satırlar (referans).
 */
if ( ! function_exists( 'rma_ceviri_elementor_dugum_gez' ) ) {
	function rma_ceviri_elementor_dugum_gez( $dugum, &$satirlar ) {
		if ( ! is_array( $dugum ) ) {
			return;
		}

		$id = isset( $dugum['id'] ) && is_string( $dugum['id'] ) ? $dugum['id'] : '';

		if ( '' !== $id && isset( $dugum['settings'] ) && is_array( $dugum['settings'] ) ) {
			rma_ceviri_elementor_ayarlari_tara( $dugum['settings'], $id, $satirlar );
		}

		if ( isset( $dugum['elements'] ) && is_array( $dugum['elements'] ) ) {
			foreach ( $dugum['elements'] as $alt ) {
				rma_ceviri_elementor_dugum_gez( $alt, $satirlar );
			}
		}
	}
}

/**
 * Ham _elementor_data JSON'ından çevrilebilir satırları çıkar.
 *
 * WordPress'e bağımsız — test edilebilir.
 *
 * @param string $json _elementor_data içeriği.
 * @return array<int,array{field:string,original:string}>
 */
if ( ! function_exists( 'rma_ceviri_elementor_json_tara' ) ) {
	function rma_ceviri_elementor_json_tara( $json ) {
		$veri = json_decode( (string) $json, true );

		if ( ! is_array( $veri ) ) {
			return array();
		}

		$satirlar = array();
		foreach ( $veri as $dugum ) {
			rma_ceviri_elementor_dugum_gez( $dugum, $satirlar );
		}

		// Aynı alan iki kez çıkarsa ilki kalsın.
		$benzersiz = array();
		foreach ( $satirlar as $satir ) {
			if ( ! isset( $benzersiz[ $satir['field'] ] ) ) {
				$benzersiz[ $satir['field'] ] = $satir;
			}
		}

		return array_values( $benzersiz );
	}
}

/**
 * Bir postun Elementor metinleri.
 *
 * @param int $post_id Post ID.
 * @return array<int,array{field:string,original:string}>
 */
if ( ! function_exists( 'rma_ceviri_elementor_tara' ) ) {
	function rma_ceviri_elementor_tara( $post_id ) {
		$ham = get_post_meta( (int) $post_id, '_elementor_data', true );

		if ( empty( $ham ) ) {
			return array();
		}

		// Meta'da ters bölü kaçışlı saklanır.
		if ( is_string( $ham ) ) {
			$ham = wp_unslash( $ham );
		} else {
			$ham = wp_json_encode( $ham );
		}

		return rma_ceviri_elementor_json_tara( $ham );
	}
}

/**
 * Elementor ile düzenlenmiş tüm içerikler (yönetim ekranındaki seçim listesi).
 *
 * @return array<int,string> post_id => başlık.
 */
if ( ! function_exists( 'rma_ceviri_elementor_sayfalari' ) ) {
	function rma_ceviri_elementor_sayfalari() {
		$sorgu = new WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 300,
				'meta_key'       => '_elementor_data',
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		$sayfalar = array();
		foreach ( $sorgu->posts as $id ) {
			$baslik = get_the_title( $id );
			$sayfalar[ (int) $id ] = '' !== $baslik ? $baslik : sprintf( '(başlıksız #%d)', $id );
		}

		return $sayfalar;
	}
}

/**
 * Dışa aktarıma dahil edilecek Elementor sayfaları.
 *
 * @return int[]
 */
if ( ! function_exists( 'rma_ceviri_secili_elementor_sayfalari' ) ) {
	function rma_ceviri_secili_elementor_sayfalari() {
		$secili = get_option( 'rma_ceviri_elementor_sayfalar', array() );

		if ( ! is_array( $secili ) ) {
			return array();
		}

		return array_values( array_unique( array_map( 'intval', $secili ) ) );
	}
}
