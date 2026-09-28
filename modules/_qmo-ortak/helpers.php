<?php
/**
 * Ortak yardımcılar — oturum zorlama, nonce doğrulama, hız sınırlama.
 *
 * Tüm public AJAX/REST uçları buradaki guard'lardan geçer. Guard'sız uç
 * bırakmayın: admin-ajax.php herkese açıktır.
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** AJAX nonce eylem adı. */
if ( ! defined( 'QMO_NONCE_ACTION' ) ) {
	define( 'QMO_NONCE_ACTION', 'qmo_ajax' );
}

/**
 * Chatbot / çağrı sabit metnini QR Çeviri tablosundan geçirir.
 *
 * Çeviri yoksa veya modül kapalıysa girdi (Türkçe) döner. AJAX/REST
 * uçlarında dil rma_get_current_lang() ile çözülür: $_REQUEST['lang']
 * sonra rma_lang cookie — admin-ajax ve REST çerezi alır, tam sayfa
 * cache bu uçlara uygulanmaz.
 *
 * @param string $metin Türkçe kaynak (genelde __( '…', 'qrms' ) çıktısı).
 * @return string
 */
if ( ! function_exists( 'qmo_ceviri_chat' ) ) {
	function qmo_ceviri_chat( $metin ) {
		$metin = (string) $metin;
		if ( function_exists( 'rma_ceviri_modul' ) ) {
			return rma_ceviri_modul( 'chat', $metin );
		}
		return $metin;
	}
}

/**
 * Sepet sabit metnini QR Çeviri tablosundan geçirir (item_type=cart).
 *
 * PHP iskeleti (JS yüklenmeden görünen ilk HTML) için. Dil
 * rma_get_current_lang() — cache'li menü sayfasında ilk boya yanlış
 * dilde kalabilir; sepet.js ciz() qmoSepet.i18n + iç tablo ile üzerine
 * yazar. Çeviri yoksa veya modül kapalıysa girdi (Türkçe) döner.
 *
 * @param string $metin Türkçe kaynak (genelde __( '…', 'qrms' ) çıktısı).
 * @return string
 */
if ( ! function_exists( 'qmo_ceviri_ui' ) ) {
	function qmo_ceviri_ui( $metin ) {
		$metin = (string) $metin;
		if ( function_exists( 'rma_ceviri_ui' ) ) {
			return rma_ceviri_ui( $metin );
		}
		return $metin;
	}
}

if ( ! function_exists( 'qmo_ceviri_cart' ) ) {
	function qmo_ceviri_cart( $metin ) {
		$metin = (string) $metin;
		if ( function_exists( 'rma_ceviri_modul' ) ) {
			return rma_ceviri_modul( 'cart', $metin );
		}
		return $metin;
	}
}

/**
 * sepet.js TXT anahtarı => Türkçe kaynak. İç tabloyla birebir; katalog
 * ve localize aynı dizeleri kullanır.
 *
 * @return array<string,string>
 */
if ( ! function_exists( 'qmo_ceviri_cart_anahtarlari' ) ) {
	function qmo_ceviri_cart_anahtarlari() {
		return array(
			'sepet'      => 'Sepet',
			'sepetiniz'  => 'Sepetiniz',
			'toplam'     => 'Toplam',
			'gonder'     => 'Siparişi Gönder',
			'bos'        => 'Sepetiniz boş',
			'notPh'      => 'Ürün notu (isteğe bağlı)…',
			'eklendi'    => 'Sepete eklendi',
			'gonderildi' => 'Siparişiniz mutfağa iletildi ✓',
			'hata'       => 'Gönderilemedi, tekrar deneyin',
			'tl'         => 'Ödeme TL üzerinden alınır.',
			'ac'         => 'Sepeti aç',
			'sil'        => 'Sil',
			'kapat'      => 'Kapat',
		);
	}
}

/**
 * sepet.js için localize tablosu — tüm etkin diller.
 *
 * Sepet menü sayfasındadır ve tam sayfa cache'e girebilir. Tek dil
 * (rma_get_current_lang) basmak splash data-sp-* sorununu tekrarlar:
 * ilk ziyaretçinin dili HTML'e kilitlenir. Bu yüzden her dil ayrı
 * basılır; istemci mevcut dil() ile seçer.
 *
 * Yalnızca tabloda kaynaktan farklı duran çeviriler eklenir. Boş veya
 * modül kapalıysa [] — sepet.js 6 dilli iç tablosu yedek kalır,
 * davranış bugünküyle aynıdır.
 *
 * @return array<string,array<string,string>> anahtar => dil => metin.
 */
if ( ! function_exists( 'qmo_ceviri_cart_js_metinleri' ) ) {
	function qmo_ceviri_cart_js_metinleri() {
		if ( ! function_exists( 'rma_ceviri_modul' )
			|| ! function_exists( 'rma_translate_field' )
			|| ! function_exists( 'rma_ceviri_ui_anahtari' ) ) {
			return array();
		}

		$diller = function_exists( 'rma_ceviri_aktif_diller' )
			? rma_ceviri_aktif_diller()
			: array( 'en', 'ar', 'de', 'fr', 'ru' );

		$out = array();
		foreach ( qmo_ceviri_cart_anahtarlari() as $k => $tr ) {
			$satir = array();
			foreach ( $diller as $dil ) {
				$dil = strtolower( (string) $dil );
				if ( '' === $dil || 'tr' === $dil ) {
					continue;
				}
				$ceviri = rma_translate_field( 0, 'cart', rma_ceviri_ui_anahtari( $tr ), $tr, $dil );
				if ( '' !== (string) $ceviri && (string) $ceviri !== $tr ) {
					$satir[ $dil ] = (string) $ceviri;
				}
			}
			if ( $satir ) {
				$out[ $k ] = $satir;
			}
		}

		return $out;
	}
}

/**
 * Hata günlüğü — yalnızca WP_DEBUG açıkken yazar.
 * Müşteriye gösterilmeyen ayrıntılar (API hataları vb.) buraya düşer.
 *
 * @param string $mesaj Mesaj.
 */
if ( ! function_exists( 'qmo_log' ) ) {
	function qmo_log( $mesaj ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[QR Menu Official] ' . $mesaj );
		}
	}
}

/**
 * Kritik operasyonel hata — WP_DEBUG kapalı production'da da error_log'a yazılır.
 *
 * @param string               $olay    Kısa olay adı.
 * @param array<string, mixed> $alanlar İsteğe bağlı correlation alanları (PII yok).
 * @return void
 */
if ( ! function_exists( 'qmo_log_critical' ) ) {
	function qmo_log_critical( $olay, array $alanlar = array() ) {
		$olay = sanitize_text_field( (string) $olay );
		if ( '' === $olay ) {
			$olay = 'critical';
		}

		$parcalar = array( $olay );
		foreach ( $alanlar as $anahtar => $deger ) {
			if ( ! is_scalar( $deger ) ) {
				continue;
			}
			$anahtar = sanitize_key( (string) $anahtar );
			if ( '' === $anahtar ) {
				continue;
			}
			$metin = sanitize_text_field( (string) $deger );
			if ( '' === $metin ) {
				continue;
			}
			if ( strlen( $metin ) > 200 ) {
				$metin = substr( $metin, 0, 200 );
			}
			$parcalar[] = $anahtar . '=' . $metin;
		}

		error_log( '[QR Menu Official][CRITICAL] ' . implode( ' | ', $parcalar ) );
	}
}

/**
 * Sipariş idempotency kaydı TTL (saniye).
 *
 * @return int
 */
if ( ! function_exists( 'qmo_idempotency_ttl' ) ) {
	function qmo_idempotency_ttl() {
		return 30 * MINUTE_IN_SECONDS;
	}
}

/**
 * İstemci idempotency anahtarını doğrular (UUID v4).
 *
 * @param string $key Ham anahtar.
 * @return string Geçerli anahtar veya boş.
 */
if ( ! function_exists( 'qmo_idempotency_key_dogrula' ) ) {
	function qmo_idempotency_key_dogrula( $key ) {
		$key = strtolower( trim( (string) $key ) );
		if ( '' === $key || strlen( $key ) > 36 ) {
			return '';
		}
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $key ) ) {
			return '';
		}
		return $key;
	}
}

/**
 * Idempotency anahtarını Firestore order_id / documentId olarak normalize eder.
 *
 * @param string $key Doğrulanmış UUID v4.
 * @return string
 */
if ( ! function_exists( 'qmo_idempotency_order_id' ) ) {
	function qmo_idempotency_order_id( $key ) {
		$key = qmo_idempotency_key_dogrula( $key );
		if ( '' === $key ) {
			return '';
		}
		$doc = preg_replace( '/[^A-Za-z0-9_-]/', '', $key );
		return is_string( $doc ) ? $doc : '';
	}
}

/**
 * Tek sipariş kalemi için Firestore ile uyumlu kanonik satır.
 *
 * @param string $urun_adi      Ürün adı.
 * @param mixed  $adet          Adet.
 * @param string $not_orijinal  Orijinal not.
 * @param string $not_tr        Türkçe not yedeği.
 * @return array{urunAdi:string,adet:int,not:string}
 */
if ( ! function_exists( 'qmo_siparis_canonical_satir' ) ) {
	function qmo_siparis_canonical_satir( $urun_adi, $adet, $not_orijinal = '', $not_tr = '' ) {
		$ad = preg_replace( '/\s+/u', ' ', trim( (string) $urun_adi ) );
		$adet = max( 1, min( 20, (int) $adet ) );
		$not_o = trim( (string) $not_orijinal );
		$not   = '' !== $not_o ? $not_o : trim( (string) $not_tr );
		$not   = mb_substr( $not, 0, 200 );

		return array(
			'urunAdi' => $ad,
			'adet'    => $adet,
			'not'     => $not,
		);
	}
}

/**
 * Kanonik satır listesi + masa/dil için SHA-256.
 *
 * @param string              $masa  Masa slug.
 * @param string              $dil   Dil kodu.
 * @param array<int, array>   $lines qmo_siparis_canonical_satir çıktıları.
 * @return string
 */
if ( ! function_exists( 'qmo_siparis_canonical_hash' ) ) {
	function qmo_siparis_canonical_hash( $masa, $dil, array $lines ) {
		$dil = substr( sanitize_text_field( (string) $dil ), 0, 5 );
		if ( '' === $dil ) {
			$dil = 'tr';
		}

		usort(
			$lines,
			function ( $a, $b ) {
				$cmp = strcmp( (string) ( $a['urunAdi'] ?? '' ), (string) ( $b['urunAdi'] ?? '' ) );
				if ( 0 !== $cmp ) {
					return $cmp;
				}
				$cmp = ( (int) ( $a['adet'] ?? 0 ) <=> (int) ( $b['adet'] ?? 0 ) );
				if ( 0 !== $cmp ) {
					return $cmp;
				}
				return strcmp( (string) ( $a['not'] ?? '' ), (string) ( $b['not'] ?? '' ) );
			}
		);

		return hash(
			'sha256',
			wp_json_encode(
				array(
					'masa'  => sanitize_title( (string) $masa ),
					'dil'   => $dil,
					'items' => $lines,
				)
			)
		);
	}
}

/**
 * İstek gövdesinden kanonik hash (itemId kullanılmaz).
 *
 * @param string              $masa  Masa.
 * @param string              $dil   Dil.
 * @param array<int, array>   $temiz Temiz kalemler.
 * @return string
 */
if ( ! function_exists( 'qmo_siparis_canonical_hash_istek' ) ) {
	function qmo_siparis_canonical_hash_istek( $masa, $dil, array $temiz ) {
		$lines = array();
		foreach ( $temiz as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			$lines[] = qmo_siparis_canonical_satir(
				$it['urunAdi'] ?? '',
				$it['adet'] ?? 1,
				$it['not'] ?? '',
				$it['not'] ?? ''
			);
		}
		return qmo_siparis_canonical_hash( $masa, $dil, $lines );
	}
}

/**
 * Firestore calls/{order_id} belgesinden kanonik hash.
 *
 * @param array<string,mixed> $doc QMO_Firestore::belge_coz çıktısı.
 * @return array{hash:string,masa:string,dil:string}|null Güvenli değilse null.
 */
if ( ! function_exists( 'qmo_siparis_canonical_firestore' ) ) {
	function qmo_siparis_canonical_firestore( array $doc ) {
		if ( ! isset( $doc['masaNo'] ) || ! is_scalar( $doc['masaNo'] ) ) {
			return null;
		}

		$masa = sanitize_title( (string) $doc['masaNo'] );
		if ( '' === $masa ) {
			return null;
		}

		$dil = isset( $doc['notDili'] ) ? substr( sanitize_text_field( (string) $doc['notDili'] ), 0, 5 ) : 'tr';
		if ( '' === $dil ) {
			$dil = 'tr';
		}

		$items = isset( $doc['items'] ) && is_array( $doc['items'] ) ? $doc['items'] : null;
		if ( null === $items || empty( $items ) ) {
			return null;
		}

		$lines = array();
		foreach ( $items as $it ) {
			if ( ! is_array( $it ) ) {
				return null;
			}
			if ( ! isset( $it['urunAdi'] ) || ! is_scalar( $it['urunAdi'] ) ) {
				return null;
			}
			if ( ! isset( $it['adet'] ) ) {
				return null;
			}
			$lines[] = qmo_siparis_canonical_satir(
				$it['urunAdi'],
				$it['adet'],
				$it['notOrijinal'] ?? '',
				$it['notTr'] ?? ''
			);
		}

		return array(
			'hash' => qmo_siparis_canonical_hash( $masa, $dil, $lines ),
			'masa' => $masa,
			'dil'  => $dil,
		);
	}
}

/**
 * Tek sipariş kalemi için kanonik satır hash'i (sıra bağımsız; occurrence ayrı eklenir).
 *
 * @param array{urunAdi:string,adet:int,not:string} $canonical_satir qmo_siparis_canonical_satir çıktısı.
 * @return string 64 karakter hex SHA-256.
 */
if ( ! function_exists( 'qmo_siparis_line_hash_tek' ) ) {
	function qmo_siparis_line_hash_tek( array $canonical_satir ) {
		return hash(
			'sha256',
			wp_json_encode(
				array(
					'urunAdi' => (string) ( $canonical_satir['urunAdi'] ?? '' ),
					'adet'    => (int) ( $canonical_satir['adet'] ?? 1 ),
					'not'     => (string) ( $canonical_satir['not'] ?? '' ),
				)
			)
		);
	}
}

/**
 * Kanonik satır karşılaştırması (deterministik sıralama / eşitlik).
 *
 * @param array{urunAdi:string,adet:int,not:string} $a Kanonik satır.
 * @param array{urunAdi:string,adet:int,not:string} $b Kanonik satır.
 * @return int strcmp benzeri.
 */
if ( ! function_exists( 'qmo_siparis_canonical_satir_cmp' ) ) {
	function qmo_siparis_canonical_satir_cmp( array $a, array $b ) {
		$cmp = strcmp( (string) ( $a['urunAdi'] ?? '' ), (string) ( $b['urunAdi'] ?? '' ) );
		if ( 0 !== $cmp ) {
			return $cmp;
		}
		$cmp = ( (int) ( $a['adet'] ?? 0 ) <=> (int) ( $b['adet'] ?? 0 ) );
		if ( 0 !== $cmp ) {
			return $cmp;
		}
		return strcmp( (string) ( $a['not'] ?? '' ), (string) ( $b['not'] ?? '' ) );
	}
}

/**
 * Firestore items[] kalemi → kanonik satır.
 *
 * @param array<string,mixed> $it FS item.
 * @return array{urunAdi:string,adet:int,not:string}
 */
if ( ! function_exists( 'qmo_siparis_canonical_satir_fs_item' ) ) {
	function qmo_siparis_canonical_satir_fs_item( array $it ) {
		return qmo_siparis_canonical_satir(
			$it['urunAdi'] ?? '',
			$it['adet'] ?? 1,
			$it['notOrijinal'] ?? '',
			$it['notTr'] ?? ''
		);
	}
}

/**
 * Temiz istek kalemi → kanonik satır.
 *
 * @param array<string,mixed> $it Temiz kalem.
 * @return array{urunAdi:string,adet:int,not:string}
 */
if ( ! function_exists( 'qmo_siparis_canonical_satir_temiz_item' ) ) {
	function qmo_siparis_canonical_satir_temiz_item( array $it ) {
		$not = $it['not'] ?? '';
		return qmo_siparis_canonical_satir(
			$it['urunAdi'] ?? '',
			$it['adet'] ?? 1,
			$not,
			$not
		);
	}
}

/**
 * Firestore / fallback items[] için line_key listesi (çıktı sırası = kaynak sırası).
 *
 * line_key occurrence, kanonik satırların deterministik sıralamasına göre atanır;
 * client / Firestore dizi permütasyonundan bağımsızdır (aynı multiset → aynı key set).
 *
 * @param array<int, array<string,mixed>> $items call_oku / belge_coz items[].
 * @return array<int, array{line_key:string,urunAdi:string,adet:int,not:string,canonical:array}>
 */
if ( ! function_exists( 'qmo_siparis_line_keys_from_items' ) ) {
	function qmo_siparis_line_keys_from_items( array $items ) {
		$indexed = array();
		$seq     = 0;

		foreach ( $items as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			$canon     = qmo_siparis_canonical_satir_fs_item( $it );
			$indexed[] = array(
				'seq'   => $seq,
				'canon' => $canon,
			);
			++$seq;
		}

		if ( empty( $indexed ) ) {
			return array();
		}

		$sortable = $indexed;
		usort(
			$sortable,
			function ( $a, $b ) {
				return qmo_siparis_canonical_satir_cmp( $a['canon'], $b['canon'] );
			}
		);

		$counts         = array();
		$key_by_seq     = array();
		foreach ( $sortable as $row ) {
			$base = qmo_siparis_line_hash_tek( $row['canon'] );
			$occ  = isset( $counts[ $base ] ) ? (int) $counts[ $base ] : 0;
			$counts[ $base ] = $occ + 1;
			$key_by_seq[ (int) $row['seq'] ] = $base . ':' . $occ;
		}

		$out = array();
		foreach ( $indexed as $row ) {
			$canon = $row['canon'];
			$out[] = array(
				'line_key'  => (string) ( $key_by_seq[ (int) $row['seq'] ] ?? '' ),
				'urunAdi'   => $canon['urunAdi'],
				'adet'      => $canon['adet'],
				'not'       => $canon['not'],
				'canonical' => $canon,
			);
		}

		return $out;
	}
}

/**
 * Temiz kalemleri Firestore items sırasına göre item_id ile eşleştirir (kanonik + occurrence).
 *
 * @param array<int, array<string,mixed>> $fs_items   Firestore items[].
 * @param array<int, array<string,mixed>> $temiz_list Temiz kalemler.
 * @return array<int, int> FS sıra indeksi => item_id (0 = çözülemedi).
 */
if ( ! function_exists( 'qmo_siparis_temiz_item_ids_for_fs_items' ) ) {
	function qmo_siparis_temiz_item_ids_for_fs_items( array $fs_items, array $temiz_list ) {
		$temiz_canon = array();
		foreach ( array_values( $temiz_list ) as $ti => $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			$temiz_canon[ $ti ] = qmo_siparis_canonical_satir_temiz_item( $it );
		}

		$used_temiz = array();
		$fs_occ     = array();
		$sonuc      = array();
		$fs_seq     = 0;

		foreach ( $fs_items as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			$fs_canon = qmo_siparis_canonical_satir_fs_item( $it );
			$base     = qmo_siparis_line_hash_tek( $fs_canon );
			$occ      = isset( $fs_occ[ $base ] ) ? (int) $fs_occ[ $base ] : 0;
			$fs_occ[ $base ] = $occ + 1;

			$adaylar = array();
			foreach ( $temiz_canon as $ti => $tc ) {
				if ( ! empty( $used_temiz[ $ti ] ) ) {
					continue;
				}
				if ( 0 === qmo_siparis_canonical_satir_cmp( $fs_canon, $tc ) ) {
					$adaylar[] = $ti;
				}
			}

			$id = 0;
			if ( ! empty( $adaylar ) ) {
				sort( $adaylar, SORT_NUMERIC );
				if ( isset( $adaylar[ $occ ] ) ) {
					$pick = (int) $adaylar[ $occ ];
					$used_temiz[ $pick ] = true;
					$id                  = isset( $temiz_list[ $pick ]['item_id'] ) ? absint( $temiz_list[ $pick ]['item_id'] ) : 0;
				}
			}

			$sonuc[ $fs_seq ] = $id;
			++$fs_seq;
		}

		return $sonuc;
	}
}

/**
 * Temiz istek kalemlerini Firestore items[] biçimine çevirir (sıra korunur).
 *
 * Firestore belgesi henüz okunamadığında analytics line identity, Firestore'a
 * yazılan items[] ile aynı sıra/kanonik kaynaktan üretilir.
 *
 * @param array<int, array<string,mixed>> $temiz qmo_siparis_isle temiz kalemler.
 * @return array<int, array<string,mixed>>
 */
if ( ! function_exists( 'qmo_siparis_firestore_items_from_temiz' ) ) {
	function qmo_siparis_firestore_items_from_temiz( array $temiz ) {
		$items = array();
		foreach ( $temiz as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			$items[] = array(
				'urunAdi'     => $it['urunAdi'] ?? '',
				'adet'        => $it['adet'] ?? 1,
				'notOrijinal' => $it['not'] ?? '',
				'notTr'       => $it['not'] ?? '',
			);
		}
		return $items;
	}
}

/**
 * Sipariş gövdesi için kararlı hash (masa + dil + kalemler).
 *
 * Firestore ile karşılaştırılabilir kanonik gövde kullanır (itemId yok).
 *
 * @param string              $masa  Doğrulanmış masa slug.
 * @param string              $dil   Dil kodu.
 * @param array<int, array>   $temiz Temizlenmiş kalemler.
 * @return string
 */
if ( ! function_exists( 'qmo_siparis_idempotency_hash' ) ) {
	function qmo_siparis_idempotency_hash( $masa, $dil, array $temiz ) {
		return qmo_siparis_canonical_hash_istek( $masa, $dil, $temiz );
	}
}

/**
 * Idempotency transient anahtarı.
 *
 * @param string $idempotency_key Doğrulanmış UUID.
 * @return string
 */
if ( ! function_exists( 'qmo_idempotency_transient_anahtar' ) ) {
	function qmo_idempotency_transient_anahtar( $idempotency_key ) {
		return 'qmo_idem_' . md5( (string) $idempotency_key );
	}
}

/**
 * Bilinen idempotency anahtarı için replay / devam / yeni sipariş durumu.
 *
 * @param string $idempotency_key UUID v4.
 * @param string $masa            Oturumdan gelen masa.
 * @param string $body_hash       qmo_siparis_idempotency_hash çıktısı.
 * @return array{type:string,order_id?:string,response?:array{success:bool,msg:string,http:int}}
 */
if ( ! function_exists( 'qmo_siparis_idempotent_durum' ) ) {
	function qmo_siparis_idempotent_durum( $idempotency_key, $masa, $body_hash ) {
		$order_id = qmo_idempotency_order_id( $idempotency_key );
		if ( '' === $order_id ) {
			return array( 'type' => 'invalid' );
		}

		$masa     = sanitize_title( (string) $masa );
		$body_hash = (string) $body_hash;
		$tkey     = qmo_idempotency_transient_anahtar( $idempotency_key );
		$rec      = get_transient( $tkey );

		$replay_yanit = array(
			'type'     => 'replay',
			'order_id' => $order_id,
			'response' => array(
				'success' => true,
				'msg'     => '',
				'http'    => 200,
			),
		);

		if ( class_exists( 'QMO_Firestore' ) && QMO_Firestore::hazir_mi() ) {
			$oku = QMO_Firestore::call_oku( $order_id, 5 );
			if ( ! is_wp_error( $oku ) && is_array( $oku ) ) {
				$kanon = qmo_siparis_canonical_firestore( $oku );
				if ( null === $kanon ) {
					return array(
						'type'     => 'firestore_invalid',
						'order_id' => $order_id,
					);
				}
				if ( $kanon['masa'] !== $masa ) {
					return array(
						'type'     => 'masa_mismatch',
						'order_id' => $order_id,
					);
				}
				if ( $kanon['hash'] !== $body_hash ) {
					return array(
						'type'     => 'body_mismatch',
						'order_id' => $order_id,
					);
				}
				if ( class_exists( 'QRMS_Analitik' ) && QRMS_Analitik::siparis_olayi_var_mi( $order_id, 'order_sent' ) ) {
					return $replay_yanit;
				}
				return array(
					'type'     => 'continue',
					'order_id' => $order_id,
				);
			}
		}

		if ( is_array( $rec ) ) {
			if ( ! empty( $rec['masa'] ) && (string) $rec['masa'] !== $masa ) {
				return array(
					'type'     => 'masa_mismatch',
					'order_id' => $order_id,
				);
			}
			if ( ! empty( $rec['body_hash'] ) && (string) $rec['body_hash'] !== $body_hash ) {
				return array(
					'type'     => 'body_mismatch',
					'order_id' => $order_id,
				);
			}
		} else {
			set_transient(
				$tkey,
				array(
					'masa'      => $masa,
					'body_hash' => $body_hash,
				),
				qmo_idempotency_ttl()
			);
			return array(
				'type'     => 'new',
				'order_id' => $order_id,
			);
		}

		return array(
			'type'     => 'continue',
			'order_id' => $order_id,
		);
	}
}

/**
 * Geçerli masa oturumunu döndürür.
 *
 * @return array{masa:string,issued:int,last:int,epoch:int}|false
 */
if ( ! function_exists( 'qmo_oturum' ) ) {
	function qmo_oturum() {
		$token = isset( $_COOKIE[ QMO_Oturum::COOKIE ] ) ? wp_unslash( $_COOKIE[ QMO_Oturum::COOKIE ] ) : '';
		return QMO_Oturum::dogrula( $token );
	}
}

/**
 * Bu istek MASA güvenliğinden muaf mı?
 *
 * MASA OTURUMU ≠ WORDPRESS OTURUMU. Masa oturumu (qr_masa_token) müşteriyi
 * bir masaya bağlar; WordPress oturumu (auth cookie) bir kullanıcıyı siteye
 * bağlar. İkisi ayrı yaşar: bu eklentide masa oturumu hiçbir koşulda
 * wp_logout() / wp_clear_auth_cookie() / WP_Session_Tokens çağırmaz, hiçbir
 * WordPress kimlik çerezine dokunmaz. Bu fonksiyon yalnızca "masa kilidi bu
 * isteğe UYGULANMASIN" der; WordPress yetkilendirmesinin yerine GEÇMEZ.
 *
 * Ölçüt YETENEKTİR. Bilinçli olarak kullanılmayan ölçütler:
 *  - is_admin(): isteğin yönetim alanında olup olmadığını söyler, kullanıcının
 *    yönetici olduğunu DEĞİL (admin-ajax.php ön yüz isteklerinde de true'dur).
 *  - kullanıcı adı / kullanıcı ID / e-posta / referer / user agent / çerez
 *    varlığı: hiçbiri kimlik kanıtı değildir, taklit edilebilir.
 *
 * Oturumu olmayan (public) ziyaretçi bu daldan ASLA geçemez: önce
 * is_user_logged_in(), sonra yetenek kontrolü vardır.
 *
 * @return bool
 */
if ( ! function_exists( 'qmo_masa_guvenligi_muaf_mi' ) ) {
	function qmo_masa_guvenligi_muaf_mi() {
		if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) {
			return false;
		}

		$yetenek = class_exists( 'QRMS_Admin' ) ? QRMS_Admin::CAPABILITY : 'manage_options';
		$muaf    = current_user_can( $yetenek );

		/**
		 * Masa güvenliği muafiyetini değiştir.
		 *
		 * Filtre YALNIZCA daraltmak için düşünülmüştür; genişletirken
		 * oturum + yetenek kontrolünü atlamayın.
		 *
		 * @param bool   $muaf    Muaf mı?
		 * @param string $yetenek Kontrol edilen yetenek.
		 */
		return (bool) apply_filters( 'qmo_masa_guvenligi_muaf', $muaf, $yetenek );
	}
}

/**
 * AJAX nonce'unu doğrula. Geçersizse isteği sonlandırır.
 *
 * Nonce, wp_localize_script ile ön yüze iletilir (qmoData.nonce).
 */
if ( ! function_exists( 'qmo_nonce_dogrula' ) ) {
	function qmo_nonce_dogrula() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, QMO_NONCE_ACTION ) ) {
			wp_send_json_error(
				array(
					'kod'   => 'nonce',
					'mesaj' => qmo_ceviri_chat( __( 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyin.', 'qrms' ) ),
				),
				403
			);
		}
	}
}

/**
 * AJAX ucu için oturum zorla: nonce + geçerli masa oturumu.
 * Başarılıysa oturumu tazeler ve oturum verisini döndürür.
 *
 * @return array{masa:string,issued:int,last:int,epoch:int}
 */
if ( ! function_exists( 'qmo_oturum_zorla' ) ) {
	function qmo_oturum_zorla() {
		qmo_nonce_dogrula();

		$data = qmo_oturum();
		if ( ! $data ) {
			wp_send_json_error(
				array(
					'kod'   => 'oturum_bitti',
					'mesaj' => qmo_ceviri_chat( __( 'Oturum süreniz doldu. Devam etmek için masadaki QR kodu tekrar okutun.', 'qrms' ) ),
				),
				403
			);
		}

		// İşlem yapıldı — idle sayacını sıfırla.
		qmo_cookie_yaz( QMO_Oturum::token_uret( $data['masa'], $data['issued'] ) );
		return $data;
	}
}

/**
 * Chatbot ucu için oturum zorla + oturum başına mesaj limiti.
 * Gemini faturasını korur: limitsiz bir uç, döngüyle POST atan biri
 * tarafından sömürülebilir.
 *
 * @return array{masa:string,issued:int,last:int,epoch:int}
 */
if ( ! function_exists( 'qmo_chat_zorla' ) ) {
	function qmo_chat_zorla() {
		$sess = qmo_oturum_zorla();

		$k     = 'qr_chat_' . md5( $sess['masa'] . '_' . $sess['issued'] );
		$sayac = qmo_sayac_arttir( $k, QMO_Oturum::hard_cap() );
		if ( $sayac > QMO_Oturum::chat_limit() ) {
			wp_send_json_error(
				array(
					'kod'   => 'limit',
					'mesaj' => qmo_ceviri_chat( __( 'Bu oturum için mesaj limitine ulaştınız.', 'qrms' ) ),
				),
				429
			);
		}

		return $sess;
	}
}

/**
 * ?masa=X değeri qrm_tables tablosunda kayıtlı bir slug mu?
 *
 * @param string $slug Masa slug'ı (string; asla absint edilmez).
 * @return bool
 */
if ( ! function_exists( 'qmo_masa_gecerli_mi' ) ) {
	function qmo_masa_gecerli_mi( $slug ) {
		global $wpdb;

		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return false;
		}

		$cache_key = 'qmo_masa_' . md5( $slug );

		// Önce obje önbelleği: kalıcı bir obje önbelleği (Redis/Memcached)
		// kurulu olan sitelerde en ucuz yol budur.
		$cached = wp_cache_get( $cache_key, 'qmo' );
		if ( false !== $cached ) {
			return (bool) $cached;
		}

		// Kalıcı obje önbelleği YOKSA — paylaşımlı hosting'de tipik durum —
		// wp_cache_* yalnızca istek içinde yaşar, yani masa doğrulaması her
		// ziyaretçi isteğinde yeniden sorgu açardı. Masa listesi ise nadiren
		// değişir; transient ikinci katman olarak o boşluğu kapatır.
		$saklanan = get_transient( $cache_key );
		if ( false !== $saklanan ) {
			wp_cache_set( $cache_key, (int) $saklanan, 'qmo', 300 );
			return ( (int) $saklanan ) > 0;
		}

		$tablo = $wpdb->prefix . 'qrm_tables';
		$var   = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$tablo} WHERE table_slug = %s", $slug )
		);
		$ok = ( (int) $var ) > 0;

		wp_cache_set( $cache_key, $ok ? 1 : 0, 'qmo', 300 );

		// Olumsuz sonuç da saklanır ama çok kısa süreyle: sahte bir QR'ın
		// sorguyu her istekte tekrarlaması engellenir, buna karşılık yeni
		// eklenen bir masa en fazla bir dakika "yok" görünür (masa
		// eklendiğinde qmo_masa_cache_temizle zaten çağrılır).
		set_transient( $cache_key, $ok ? 1 : 0, $ok ? 5 * MINUTE_IN_SECONDS : MINUTE_IN_SECONDS );

		return $ok;
	}
}

/**
 * Masa önbelleğini temizle (masa eklendiğinde/silindiğinde çağrılır).
 *
 * @param string $slug Masa slug'ı.
 */
if ( ! function_exists( 'qmo_masa_cache_temizle' ) ) {
	function qmo_masa_cache_temizle( $slug ) {
		$cache_key = 'qmo_masa_' . md5( sanitize_title( $slug ) );

		wp_cache_delete( $cache_key, 'qmo' );
		delete_transient( $cache_key );
	}
}

/**
 * Tüm önbellekleri temizler — nesne önbelleği + kurulu önbellek eklentileri.
 *
 * Bir ayar kaydedildiğinde ön yüz çıktısı değişir, ama sayfa çoğu kurulumda
 * bir önbellek katmanının arkasındadır: kullanıcı "Kaydet" deyip sayfayı
 * yenilese bile eski HTML'i görür ve değişikliğin kaydedilmediğini sanır.
 * Bu yüzden kayıt akışları (HFB, slider, banner…) kayıttan HEMEN SONRA
 * burayı çağırır.
 *
 * Eklenti temizlikleri koşulludur: kurulu olmayan eklenti sessizce atlanır,
 * hiçbir zaman ölümcül hata üretilmez. qmo_masa_cache_temizle() tek bir masa
 * anahtarını hedefler ve bu fonksiyondan bağımsızdır; ikisi birbirinin yerine
 * geçmez.
 *
 * @param string $cache_group Yalnızca bu nesne önbelleği grubu temizlensin
 *                            (ör. 'qmo'). Boşsa ya da kurulum grup bazlı
 *                            temizliği desteklemiyorsa genel flush yapılır.
 * @return string[] Gerçekten çalıştırılan temizleyicilerin adları.
 */
if ( ! function_exists( 'qmo_tum_onbellek_temizle' ) ) {
	function qmo_tum_onbellek_temizle( $cache_group = '' ) {
		$temizlenen = array();

		/*
		 * 1) WordPress nesne önbelleği.
		 *
		 * Grup bazlı temizlik daha dar kapsamlıdır ama her arka uç
		 * desteklemez (wp_cache_supports() WP 6.1+). Desteklenmiyorsa
		 * genel flush'a düşülür — ayar kaydı seyrek bir işlemdir, genel
		 * flush'ın bedeli kabul edilebilir.
		 */
		$cache_group = is_string( $cache_group ) ? trim( $cache_group ) : '';

		if ( '' !== $cache_group
			&& function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' )
			&& function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( $cache_group );
			$temizlenen[] = 'wp_cache_flush_group:' . $cache_group;
		} elseif ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$temizlenen[] = 'wp_cache_flush';
		}

		// 2) WP Rocket.
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
			$temizlenen[] = 'wp_rocket';
		}

		// 3) LiteSpeed Cache — önce genel fonksiyon, yoksa eylem kancası.
		if ( function_exists( 'litespeed_purge_all' ) ) {
			litespeed_purge_all();
			$temizlenen[] = 'litespeed';
		} elseif ( defined( 'LSCWP_V' ) || class_exists( 'LiteSpeed\\Core' ) ) {
			do_action( 'litespeed_purge_all' );
			$temizlenen[] = 'litespeed';
		}

		// 4) W3 Total Cache.
		if ( class_exists( 'W3TC\\Dispatcher' ) ) {
			$flush = \W3TC\Dispatcher::component( 'CacheFlush' );
			if ( is_object( $flush ) && method_exists( $flush, 'flush_all' ) ) {
				$flush->flush_all();
				$temizlenen[] = 'w3tc';
			}
		}

		// 5) WP Super Cache.
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
			$temizlenen[] = 'wp_super_cache';
		}

		// 6) Autoptimize (birleştirilmiş CSS/JS sayfa önbelleği).
		if ( function_exists( 'autoptimize_flush_pagecache' ) ) {
			autoptimize_flush_pagecache();
			$temizlenen[] = 'autoptimize';
		}

		/**
		 * Listede olmayan bir önbellek katmanı için kanca.
		 *
		 * @param string[] $temizlenen  Çalıştırılan temizleyiciler.
		 * @param string   $cache_group İstenen nesne önbelleği grubu.
		 */
		do_action( 'qmo_onbellek_temizlendi', $temizlenen, $cache_group );

		return $temizlenen;
	}
}

/**
 * İstemci IP'sinin kısa hash'i — hız sınırlama anahtarlarında kullanılır.
 * Ham IP saklanmaz.
 *
 * @return string
 */
if ( ! function_exists( 'qmo_ip_hash' ) ) {
	function qmo_ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		// hash_hmac ile doğru MAC yapısı kullanılır (ham birleştirme yerine);
		// tuz bir şekilde sızarsa IPv4 uzayının (2^32) hızlı donanımla saniyeler
		// içinde geri çözülmesi riski hâlâ küçük uzaydan kaynaklanır, ama en
		// azından hash-flooding/uzatma sınıfı zayıflıklara açık kapı bırakılmaz.
		return substr( hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ), 0, 16 );
	}
}

/**
 * Kısa bir işi dosya kilidiyle (flock) serileştirir.
 *
 * get_transient()/set_transient() ikilisi atomik DEĞİLDİR: iki eşzamanlı
 * istek aynı değeri okuyup ikisi de aynı "yeni" değeri yazabilir (TOCTOU
 * yarış durumu). Kalıcı bir nesne önbelleği (Redis/Memcached) yoksa —
 * paylaşımlı hosting'de tipik durum — bu, aynı sunucudaki PHP-FPM
 * işçileri arasında pratik bir kilit sağlar.
 *
 * Sabit sayıda (32) kilit dosyası kullanılır ("lock striping"): anahtar
 * başına ayrı dosya açmak, hız sınırı anahtarları (masa+IP+eylem gibi)
 * sürekli değiştiği için zamanla sınırsız sayıda ufak dosya biriktirirdi.
 * Kilit tutma süresi mikrosaniyeler mertebesinde olduğu için farklı
 * anahtarların aynı şeride düşmesi zararsızdır.
 *
 * Kilit dosyası açılamazsa (salt-okunur dosya sistemi, çok sunuculu bir
 * havuz vb.) kilitsiz devam edilir — istek asla bloklanmaz, yalnızca eski
 * (yarış durumu mümkün) davranışa düşülür.
 *
 * @param string   $anahtar Kilit şeridini seçmek için kullanılan anahtar.
 * @param callable $islem   Kilit altında çalışacak iş.
 * @return mixed $islem() çağrısının dönüşü.
 */
if ( ! function_exists( 'qmo_kilitli_calistir' ) ) {
	function qmo_kilitli_calistir( $anahtar, $islem ) {
		$dizin = rtrim( function_exists( 'get_temp_dir' ) ? get_temp_dir() : sys_get_temp_dir(), '/\\' );
		$serit = hexdec( substr( md5( (string) $anahtar ), 0, 4 ) ) % 32;
		$dosya = $dizin . '/qmo-rl-' . $serit . '.lock';

		$fp = @fopen( $dosya, 'c' );
		if ( ! $fp ) {
			return call_user_func( $islem );
		}

		$kilitli = flock( $fp, LOCK_EX );
		try {
			return call_user_func( $islem );
		} finally {
			if ( $kilitli ) {
				flock( $fp, LOCK_UN );
			}
			fclose( $fp );
		}
	}
}

/**
 * Bir sayacı atomik biçimde arttırır ve arttırmadan SONRAKİ değeri döner.
 *
 * Öncelik: 1) kalıcı nesne önbelleği varsa wp_cache_incr() (gerçek, tam
 * atomiklik); 2) qmo_kilitli_calistir() ile serileştirilmiş transient
 * oku/yaz (tek sunuculu PHP-FPM için pratik atomiklik). Kalıcı nesne
 * önbelleği OLMAYAN çok sunuculu bir havuzda hâlâ küçük bir yarış payı
 * kalır; böyle kurulumlarda bir Redis/Memcached object-cache eklentisi
 * önerilir.
 *
 * @param string $key Transient anahtarı (önek dahil, benzersiz).
 * @param int    $ttl Saniye.
 * @return int Arttırmadan SONRAKİ değer (1 = pencerede ilk istek).
 */
if ( ! function_exists( 'qmo_sayac_arttir' ) ) {
	function qmo_sayac_arttir( $key, $ttl ) {
		$ttl = max( 1, (int) $ttl );

		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache()
			&& function_exists( 'wp_cache_add' ) && function_exists( 'wp_cache_incr' ) ) {
			wp_cache_add( $key, 0, 'qmo_rl', $ttl );
			$yeni = wp_cache_incr( $key, 1, 'qmo_rl' );
			if ( false !== $yeni ) {
				return (int) $yeni;
			}
		}

		return (int) qmo_kilitli_calistir(
			$key,
			function () use ( $key, $ttl ) {
				$n = (int) get_transient( $key ) + 1;
				set_transient( $key, $n, $ttl );
				return $n;
			}
		);
	}
}

/**
 * IP + masa bazlı hız sınırı.
 *
 * @param string $anahtar Eylem adı (ör. 'garson').
 * @param string $masa    Masa slug'ı.
 * @param int    $saniye  Bekleme süresi.
 * @return bool true = izin var, false = çok sık istek.
 */
if ( ! function_exists( 'qmo_hiz_siniri' ) ) {
	function qmo_hiz_siniri( $anahtar, $masa, $saniye = 60 ) {
		$k = 'qmo_rl_' . md5( $anahtar . '|' . sanitize_title( $masa ) . '|' . qmo_ip_hash() );
		return 1 === qmo_sayac_arttir( $k, $saniye );
	}
}

/* -------------------------------------------------------------------------
 * VERİTABANI BAĞLANTISININ UZUN DIŞ İSTEKLER BOYUNCA SERBEST BIRAKILMASI
 *
 * PHP, MySQL bağlantısını isteğin sonuna kadar açık tutar. Bu modüllerde
 * "isteğin sonu" 45 saniyelik bir Gemini çağrısının ya da arka planda tamamlanan
 * bir sipariş çevirisinin ardı demek olabiliyor — o süre boyunca bağlantı
 * TAMAMEN KULLANILMADAN havuzda yer kaplar. Aynı anda yirmi müşteri chatbot'a
 * yazdığında yirmi bağlantı, hiçbiri sorgu çalıştırmadan dakikalarca tutulur;
 * "Too many connections" hatasının en pahalı sebebi budur.
 *
 * Çözüm, HTTP çağrısını iki yardımcının arasına almaktır. Dikkat: wpdb::close()
 * sonrasında bağlantı KENDİLİĞİNDEN geri gelmez — wpdb `ready` bayrağını
 * düşürür ve sonraki sorgular sessizce false döner. Bu yüzden geri bağlanma
 * açıkça yapılır ve HTTP'den sonra veritabanına ihtiyaç duyan her kod
 * qmo_db_geri_baglan()'ın ARDINDA durmalıdır.
 * ---------------------------------------------------------------------- */

/**
 * Veritabanı bağlantısını geçici olarak kapatır.
 *
 * Yalnızca uzun süren bir dış istekten HEMEN ÖNCE çağrılmalıdır; ardından
 * qmo_db_geri_baglan() ile geri açılır.
 *
 * @return bool Bağlantı gerçekten kapatıldıysa true.
 */
if ( ! function_exists( 'qmo_db_serbest_birak' ) ) {
	function qmo_db_serbest_birak() {
		global $wpdb;

		/**
		 * Uzun dış istekler boyunca veritabanı bağlantısı bırakılsın mı?
		 *
		 * Kalıcı bağlantı kullanan ya da wpdb'yi değiştiren kurulumlarda
		 * (ör. HyperDB) `add_filter( 'qmo_db_baglanti_serbest', '__return_false' )`
		 * ile kapatılabilir.
		 *
		 * @param bool $serbest Varsayılan true.
		 */
		if ( ! apply_filters( 'qmo_db_baglanti_serbest', true ) ) {
			return false;
		}

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'close' )
			|| ! method_exists( $wpdb, 'db_connect' ) ) {
			return false;
		}

		return (bool) $wpdb->close();
	}
}

/**
 * qmo_db_serbest_birak() ile kapatılan bağlantıyı geri açar.
 *
 * @param bool $kapatildi qmo_db_serbest_birak() çıktısı.
 * @return void
 */
if ( ! function_exists( 'qmo_db_geri_baglan' ) ) {
	function qmo_db_geri_baglan( $kapatildi ) {
		global $wpdb;

		if ( ! $kapatildi ) {
			return;
		}

		if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'db_connect' ) ) {
			$wpdb->db_connect();
		}
	}
}

/**
 * Kullanılacak Gemini modeli. Ayarlar sayfasından değiştirilebilir.
 *
 * @return string
 */
if ( ! function_exists( 'qmo_gemini_model' ) ) {
	function qmo_gemini_model() {
		$varsayilan = 'gemini-3-flash-preview';
		$m          = trim( (string) get_option( 'qmo_gemini_model', '' ) );

		// Değer doğrudan Gemini istek URL'sinin YOL parçasına gömülür; boş
		// kontrolü dışında hiç doğrulanmıyordu. Model adları küçük harf,
		// rakam, nokta ve tire dışında karakter taşımaz — beklenmedik bir
		// değer (yanlış yapıştırma, sorgu dizesi enjekte etme denemesi)
		// isteğin yolunu/parametrelerini bozmasın diye reddedilir.
		if ( '' === $m || ! preg_match( '/^[a-z0-9.\-]+$/', $m ) ) {
			return $varsayilan;
		}

		return $m;
	}
}

/**
 * Oturum yokken kısa kodların bastığı bilgi kutusu.
 *
 * @param string $mesaj Gösterilecek metin.
 * @return string
 */
if ( ! function_exists( 'qmo_oturum_uyari_kutusu' ) ) {
	function qmo_oturum_uyari_kutusu( $mesaj = '' ) {
		if ( '' === $mesaj ) {
			$mesaj = __( 'Bu bölümü kullanmak için masanızdaki QR kodu okutun.', 'qrms' );
			if ( function_exists( 'rma_ceviri_modul' ) ) {
				// Uyarı kutusu kilit ekranı değildir: Accept-Language yok,
				// rma_get_current_lang() (?lang= → cookie → tr) kullanılır.
				$mesaj = rma_ceviri_modul( 'lock', $mesaj );
			}
		}
		qmo_asset_enqueue( 'qmo-oturum-kutu' );
		return '<div class="qmo-oturum-kutu"><span class="qmo-oturum-kutu-ikon">🔒</span>'
			. '<span>' . esc_html( $mesaj ) . '</span></div>';
	}
}

/* -------------------------------------------------------------------------
 * ANALİTİK YAZIM — qr-analiz lisansta yoksa no-op
 *
 * Chatbot sipariş/sepet/çağrı uçları buradan yazar. Sınıf yüklenmemişse
 * (modül pasif) hiçbir şey olmaz; analitik bir bağımlılık değildir.
 * ---------------------------------------------------------------------- */

/**
 * Bir analitik olayını sessizce kaydeder.
 *
 * Yazım QRMS_Analitik::kaydet() üzerinden gider; yeni INSERT yolu açılmaz.
 * Başarısızlık çoğu çağrıda yutulur — sipariş/sepet akışı kesilmesin.
 *
 * @param array $satir event_type ve isteğe bağlı item_id / item_name / category_name / price / unit_price / masa_no / order_id / session_id / reason.
 * @return bool
 */
if ( ! function_exists( 'qmo_analitik_yaz' ) ) {
	function qmo_analitik_yaz( array $satir ) {
		if ( ! class_exists( 'QRMS_Analitik' ) ) {
			return false;
		}

		try {
			return (bool) QRMS_Analitik::kaydet( $satir );
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			return false;
		}
	}
}

/**
 * QMO masa oturumu için analitik session_id (s_…); IP yedeklenmez.
 *
 * @param array|false|null $sess qmo_oturum() çıktısı.
 * @return string Boş = oturum yok.
 */
if ( ! function_exists( 'qmo_masa_session_id' ) ) {
	function qmo_masa_session_id( $sess = null ) {
		if ( null === $sess ) {
			$sess = function_exists( 'qmo_oturum' ) ? qmo_oturum() : false;
		}
		if ( ! is_array( $sess ) || empty( $sess['masa'] ) ) {
			return '';
		}
		$issued = isset( $sess['issued'] ) ? (string) $sess['issued'] : '';
		if ( '' === $issued ) {
			return '';
		}
		return 's_' . md5( $sess['masa'] . '_' . $issued );
	}
}

/**
 * Yayınlanmış bir menü ürününden analitik alanlarını çözer.
 *
 * Kimlik geçersizse (yok, yanlış tip, taslak) boş dizi döner; çağıran
 * o olayı atlar. Ad ve kategori SUNUCUDA okunur, istemciye güvenilmez.
 *
 * @param int $item_id Ürün kimliği.
 * @return array{item_id:int,item_name:string,category_name:string,price:float}|array{}
 */
if ( ! function_exists( 'qmo_analitik_urun_alani' ) ) {
	function qmo_analitik_urun_alani( $item_id ) {
		$item_id = absint( $item_id );

		if ( $item_id < 1 ) {
			return array();
		}

		$post = get_post( $item_id );

		if ( ! $post || 'rma_menu_item' !== $post->post_type || 'publish' !== $post->post_status ) {
			return array();
		}

		$terimler = wp_get_post_terms( $item_id, 'rma_category' );
		$kategori = ( ! is_wp_error( $terimler ) && ! empty( $terimler ) ) ? (string) $terimler[0]->name : '';

		return array(
			'item_id'       => $item_id,
			'item_name'     => (string) get_the_title( $item_id ),
			'category_name' => $kategori,
			// Taban fiyat (rma_price). Porsiyon farkı ve kampanya indirimi
			// hesaba katılmaz — ciro raporları bu yüzden yaklaşıktır.
			'price'         => (float) get_post_meta( $item_id, 'rma_price', true ),
		);
	}
}

/**
 * Ürün adından yayınlanmış menü kaydını bulur (sipariş kalemleri kimlik taşımaz).
 *
 * Tam başlık eşleşmesi yoksa ad yine yazılır, item_id 0 kalır — sipariş
 * akışı bunun için ek sorguya boğulmasın.
 *
 * @param string $ad Türkçe ürün adı.
 * @return array{item_id:int,item_name:string,category_name:string,price:float}
 */
if ( ! function_exists( 'qmo_analitik_urun_ada_gore' ) ) {
	function qmo_analitik_urun_ada_gore( $ad ) {
		$ad = sanitize_text_field( (string) $ad );

		if ( '' === $ad ) {
			return array(
				'item_id'       => 0,
				'item_name'     => '',
				'category_name' => '',
				'price'         => 0.0,
			);
		}

		$posts = get_posts(
			array(
				'post_type'              => 'rma_menu_item',
				'post_status'            => 'publish',
				'title'                  => $ad,
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
			)
		);

		if ( ! empty( $posts ) && isset( $posts[0]->ID ) ) {
			$alan = qmo_analitik_urun_alani( (int) $posts[0]->ID );

			if ( ! empty( $alan ) ) {
				return $alan;
			}
		}

		return array(
			'item_id'       => 0,
			'item_name'     => $ad,
			'category_name' => '',
			'price'         => 0.0,
		);
	}
}

/**
 * Ürün adından menü kaydı; birden fazla yayın adayı varsa belirsiz (item_id=0).
 *
 * Sipariş analitiğinde kanonik eşleşme yokken kullanılır — sessiz ilk eşleşme yok.
 *
 * @param string $ad Ürün adı.
 * @return array{item_id:int,item_name:string,category_name:string,price:float}
 */
if ( ! function_exists( 'qmo_analitik_urun_ada_gore_belirsiz_guvenli' ) ) {
	function qmo_analitik_urun_ada_gore_belirsiz_guvenli( $ad ) {
		$ad = sanitize_text_field( (string) $ad );

		if ( '' === $ad ) {
			return array(
				'item_id'       => 0,
				'item_name'     => '',
				'category_name' => '',
				'price'         => 0.0,
			);
		}

		$posts = get_posts(
			array(
				'post_type'              => 'rma_menu_item',
				'post_status'            => 'publish',
				'title'                  => $ad,
				'posts_per_page'         => 2,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
			)
		);

		if ( count( $posts ) !== 1 || ! isset( $posts[0]->ID ) ) {
			return array(
				'item_id'       => 0,
				'item_name'     => $ad,
				'category_name' => '',
				'price'         => 0.0,
			);
		}

		$alan = qmo_analitik_urun_alani( (int) $posts[0]->ID );

		if ( ! empty( $alan ) ) {
			return $alan;
		}

		return array(
			'item_id'       => 0,
			'item_name'     => $ad,
			'category_name' => '',
			'price'         => 0.0,
		);
	}
}

/* -------------------------------------------------------------------------
 * Geriye dönük uyumluluk — eski snippet fonksiyon adları
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'qr_masa_oturum_zorla' ) ) {
	/**
	 * @deprecated qmo_oturum_zorla() kullanın.
	 * @return array
	 */
	function qr_masa_oturum_zorla() {
		return qmo_oturum_zorla();
	}
}

if ( ! function_exists( 'qr_masa_chat_zorla' ) ) {
	/**
	 * @deprecated qmo_chat_zorla() kullanın.
	 * @return array
	 */
	function qr_masa_chat_zorla() {
		return qmo_chat_zorla();
	}
}

if ( ! function_exists( 'qrservis_masa_gecerli_mi' ) ) {
	/**
	 * @deprecated qmo_masa_gecerli_mi() kullanın.
	 * @param string $slug Masa slug'ı.
	 * @return bool
	 */
	function qrservis_masa_gecerli_mi( $slug ) {
		return qmo_masa_gecerli_mi( $slug );
	}
}

/* -------------------------------------------------------------------------
 * SIRLARIN AUTOLOAD DIŞINDA TUTULMASI
 *
 * `qmo_firebase_sa` (Firebase private key'ini içeren tam service-account
 * JSON'u) ve `gemini_api_key` varsayılan olarak autoload='yes' ile yazılıyordu:
 * her istekte `alloptions` içine yükleniyorlar demektir. Bir yedekleme
 * eklentisinin option dökümü, bir hata ayıklama çıktısı ya da BAŞKA bir
 * eklentideki SQL enjeksiyonu bu anahtarları tek hamlede sızdırır.
 *
 * Anahtarlar yalnızca kendi uçlarında okunduğu için autoload'a hiç ihtiyaçları
 * yok. Aşağısı hem kayıt anında (updated/added_option) hem de mevcut
 * kurulumlar için bir kez (qmo_sirlari_autoload_disina_al) bayrağı düşürür.
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'qmo_sir_option_adlari' ) ) {
	/**
	 * Autoload dışında tutulması gereken sır option'ları.
	 *
	 * @return string[]
	 */
	function qmo_sir_option_adlari() {
		return array( 'qmo_firebase_sa', 'gemini_api_key' );
	}
}

if ( ! function_exists( 'qmo_autoload_kapat' ) ) {
	/**
	 * Bir option'ın autoload bayrağını kapatır.
	 *
	 * WordPress 6.4 öncesinde autoload'u değiştiren bir API yok; eklenti 6.0'ı
	 * desteklediği için varsa çekirdek fonksiyonu, yoksa doğrudan tablo
	 * güncellemesi kullanılır (ardından alloptions önbelleği tazelenir).
	 *
	 * @param string $option Option adı.
	 * @return void
	 */
	function qmo_autoload_kapat( $option ) {
		if ( function_exists( 'wp_set_option_autoload' ) ) {
			wp_set_option_autoload( $option, false );
			return;
		}

		global $wpdb;

		$degisti = $wpdb->update(
			$wpdb->options,
			array( 'autoload' => 'no' ),
			array(
				'option_name' => $option,
				'autoload'    => 'yes',
			),
			array( '%s' ),
			array( '%s', '%s' )
		);

		if ( $degisti ) {
			wp_cache_delete( 'alloptions', 'options' );
		}
	}
}

if ( ! function_exists( 'qmo_sir_autoload_duzelt' ) ) {
	/**
	 * Sır option'ı her yazıldığında autoload bayrağını kapalı tutar.
	 *
	 * @param string $option Yazılan option adı.
	 * @return void
	 */
	function qmo_sir_autoload_duzelt( $option ) {
		if ( in_array( (string) $option, qmo_sir_option_adlari(), true ) ) {
			qmo_autoload_kapat( (string) $option );
		}
	}
}

if ( ! function_exists( 'qmo_sirlari_autoload_disina_al' ) ) {
	/**
	 * Mevcut kurulumlar için tek seferlik göç.
	 *
	 * @return void
	 */
	function qmo_sirlari_autoload_disina_al() {
		if ( get_option( 'qmo_sir_autoload_gocu' ) ) {
			return;
		}

		foreach ( qmo_sir_option_adlari() as $option ) {
			qmo_autoload_kapat( $option );
		}

		add_option( 'qmo_sir_autoload_gocu', 1, '', false );
	}
}
