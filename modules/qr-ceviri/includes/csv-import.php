<?php
/**
 * Çeviri CSV'sini içe aktarma.
 *
 * Eşleştirme sırası (JSON import'undaki üç aşamalı mantığın CSV karşılığı):
 *   1. item_id dolu ve hedef (post/term) hâlâ duruyorsa → doğrudan yaz.
 *   2. item_id 0/boş ise → sabit metin (ui_string veya modül tipi), `field` ile eşleş.
 *   3. Hiçbiri tutmuyorsa → satırı atla ve özete işle.
 *
 * Dolu dil hücreleri upsert edilir, BOŞ hücreler dokunulmadan geçilir
 * (mevcut çeviriyi silmez). "Boş hücreleri temizle" işaretliyse boş hücre o
 * dildeki çeviriyi siler.
 *
 * @package QRMenu_Ceviri
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Yüklenebilecek en büyük CSV — çeviri dosyası menü JSON'undan çok daha büyük olur. */
if ( ! defined( 'RMA_CEVIRI_MAX_YUKLEME' ) ) {
	define( 'RMA_CEVIRI_MAX_YUKLEME', 20971520 ); // 20 MB.
}

/** Özette gösterilecek en fazla örnek satır. */
if ( ! defined( 'RMA_CEVIRI_RAPOR_ORNEK' ) ) {
	define( 'RMA_CEVIRI_RAPOR_ORNEK', 25 );
}

/*
 * rma_ceviri_gecerli_tipler() tanımı ui-stringler.php'dedir — CSV sütunları
 * değişmeden yeni modül tipleri (splash, hours, …) oraya eklenir.
 */

add_action( 'admin_post_rma_ceviri_import', 'rma_ceviri_csv_ice_aktar' );

/**
 * Yönetim ekranına dön.
 *
 * @param array $args Sorgu parametreleri.
 */
if ( ! function_exists( 'rma_ceviri_import_yonlendir' ) ) {
	function rma_ceviri_import_yonlendir( $args ) {
		$args['page'] = 'qrms-cv-ice';
		if ( ! class_exists( 'QRMS_Admin' ) ) {
			$args['page'] = 'qrmenu-translator';
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}

/**
 * CSV ayracını tahmin et — menu-data-upload.php'deki desenin aynısı.
 *
 * @param string $ornek Dosyanın ilk parçası.
 * @return string ';' ya da ','.
 */
if ( ! function_exists( 'rma_ceviri_ayrac_tespit' ) ) {
	function rma_ceviri_ayrac_tespit( $ornek ) {
		return ( substr_count( $ornek, ';' ) > substr_count( $ornek, ',' ) ) ? ';' : ',';
	}
}

/**
 * Başlık satırından sütun haritası çıkar.
 *
 * @param array $basliklar Ham başlık hücreleri.
 * @return array{sabit:array<string,int>,diller:array<string,int>}
 */
if ( ! function_exists( 'rma_ceviri_baslik_haritasi' ) ) {
	function rma_ceviri_baslik_haritasi( $basliklar ) {
		$sabit_adlar = array( 'item_id', 'item_type', 'field', 'original_text', 'original_hash' );
		$tum_diller  = array_keys( qrmenu_get_langs() );

		$sabit  = array();
		$diller = array();

		foreach ( $basliklar as $sira => $baslik ) {
			$baslik = trim( (string) $baslik );

			// İlk hücredeki UTF-8 BOM'u temizle.
			$baslik = preg_replace( '/^\xEF\xBB\xBF/', '', $baslik );
			$baslik = trim( (string) $baslik );

			if ( '' === $baslik ) {
				continue;
			}

			if ( in_array( strtolower( $baslik ), $sabit_adlar, true ) ) {
				$sabit[ strtolower( $baslik ) ] = $sira;
				continue;
			}

			// Dil sütunu — büyük/küçük harf farkını tolere et (zh-cn ↔ zh-CN).
			foreach ( $tum_diller as $kod ) {
				if ( 'tr' !== $kod && strtolower( $kod ) === strtolower( $baslik ) ) {
					$diller[ $kod ] = $sira;
					break;
				}
			}
		}

		return array(
			'sabit'  => $sabit,
			'diller' => $diller,
		);
	}
}

/**
 * CSV'yi oku ve çevirileri yaz.
 */
if ( ! function_exists( 'rma_ceviri_csv_ice_aktar' ) ) {
	function rma_ceviri_csv_ice_aktar() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Yetkiniz yok.' );
		}
		check_admin_referer( 'rma_ceviri_import_action', 'rma_ceviri_import_nonce' );

		if ( empty( $_FILES['rma_ceviri_dosya']['tmp_name'] ) || UPLOAD_ERR_OK !== $_FILES['rma_ceviri_dosya']['error'] ) {
			rma_ceviri_import_yonlendir(
				array(
					'ice' => 'hata',
					'msg' => 'nofile',
				)
			);
		}

		$tmp  = $_FILES['rma_ceviri_dosya']['tmp_name'];
		$isim = sanitize_file_name( $_FILES['rma_ceviri_dosya']['name'] );

		if ( ! is_uploaded_file( $tmp ) ) {
			rma_ceviri_import_yonlendir(
				array(
					'ice' => 'hata',
					'msg' => 'nofile',
				)
			);
		}

		if ( ! in_array( strtolower( pathinfo( $isim, PATHINFO_EXTENSION ) ), array( 'csv', 'txt' ), true ) ) {
			rma_ceviri_import_yonlendir(
				array(
					'ice' => 'hata',
					'msg' => 'type',
				)
			);
		}

		if ( filesize( $tmp ) > RMA_CEVIRI_MAX_YUKLEME ) {
			rma_ceviri_import_yonlendir(
				array(
					'ice' => 'hata',
					'msg' => 'size',
				)
			);
		}

		$dosya = fopen( $tmp, 'r' );
		if ( ! $dosya ) {
			rma_ceviri_import_yonlendir(
				array(
					'ice' => 'hata',
					'msg' => 'nofile',
				)
			);
		}

		// Ayracı ilk parçadan tahmin et, sonra başa dön.
		$ayrac = rma_ceviri_ayrac_tespit( (string) fread( $dosya, 8192 ) );
		rewind( $dosya );

		$basliklar = fgetcsv( $dosya, 0, $ayrac, '"', '\\' );
		if ( ! is_array( $basliklar ) ) {
			fclose( $dosya );
			rma_ceviri_import_yonlendir(
				array(
					'ice' => 'hata',
					'msg' => 'invalidcsv',
				)
			);
		}

		$harita = rma_ceviri_baslik_haritasi( $basliklar );

		if ( ! isset( $harita['sabit']['item_type'], $harita['sabit']['field'] ) || empty( $harita['diller'] ) ) {
			fclose( $dosya );
			rma_ceviri_import_yonlendir(
				array(
					'ice' => 'hata',
					'msg' => 'sutun',
				)
			);
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		if ( function_exists( 'rma_ceviri_bellek_pay_ayir' ) ) {
			rma_ceviri_bellek_pay_ayir();
		}

		$rapor = rma_ceviri_satirlari_isle(
			$dosya,
			$ayrac,
			$harita,
			! empty( $_POST['bos_hucreleri_temizle'] )
		);

		fclose( $dosya );

		$rapor['dosya'] = $isim;

		update_option( 'rma_ceviri_son_ice', time(), false );

		set_transient( 'rma_ceviri_rapor_' . get_current_user_id(), $rapor, 10 * MINUTE_IN_SECONDS );
		rma_ceviri_onbellek_temizle();

		rma_ceviri_import_yonlendir( array( 'ice' => 'ok' ) );
	}
}

/**
 * Satırları işle.
 *
 * @param resource $dosya   Açık dosya tanıtıcısı (başlık satırı okunmuş).
 * @param string   $ayrac   Sütun ayracı.
 * @param array    $harita  Sütun haritası.
 * @param bool     $temizle Boş hücreler mevcut çeviriyi silsin mi?
 * @return array Rapor.
 */
if ( ! function_exists( 'rma_ceviri_satirlari_isle' ) ) {
	function rma_ceviri_satirlari_isle( $dosya, $ayrac, $harita, $temizle ) {
		$sabit  = $harita['sabit'];
		$diller = $harita['diller'];
		$tipler = rma_ceviri_gecerli_tipler();

		$rapor = array(
			'toplam'      => 0,
			'guncellendi' => 0,
			'silindi'     => 0,
			'atlandi'     => 0,
			'atlanan'     => array(),
			'bayat'          => array(),
			'temizle'        => (bool) $temizle,
			'bellek_kesildi' => false,
		);

		$satir_no = 1; // Başlık satırı okundu.

		while ( false !== ( $satir = fgetcsv( $dosya, 0, $ayrac, '"', '\\' ) ) ) {
			++$satir_no;

			if ( 0 === ( $satir_no % 50 ) && function_exists( 'rma_ceviri_bellek_sinirda_mi' ) && rma_ceviri_bellek_sinirda_mi() ) {
				$rapor['bellek_kesildi'] = true;
				break;
			}

			// fgetcsv boş satırda [null] döndürür.
			if ( null === $satir || ( 1 === count( $satir ) && ( null === $satir[0] || '' === trim( (string) $satir[0] ) ) ) ) {
				continue;
			}

			++$rapor['toplam'];

			$item_type = isset( $satir[ $sabit['item_type'] ] ) ? sanitize_key( $satir[ $sabit['item_type'] ] ) : '';
			$field     = isset( $satir[ $sabit['field'] ] ) ? trim( (string) $satir[ $sabit['field'] ] ) : '';
			$item_id   = isset( $sabit['item_id'], $satir[ $sabit['item_id'] ] ) ? (int) $satir[ $sabit['item_id'] ] : 0;

			$orijinal_csv = isset( $sabit['original_text'], $satir[ $sabit['original_text'] ] )
				? (string) $satir[ $sabit['original_text'] ]
				: '';
			$hash_csv     = isset( $sabit['original_hash'], $satir[ $sabit['original_hash'] ] )
				? trim( (string) $satir[ $sabit['original_hash'] ] )
				: '';

			if ( '' === $field || ! in_array( $item_type, $tipler, true ) ) {
				rma_ceviri_atla( $rapor, $satir_no, 'geçersiz item_type/field' );
				continue;
			}

			/* --- Eşleştirme --- */
			if ( function_exists( 'rma_ceviri_modul_sabit_mi' ) && rma_ceviri_modul_sabit_mi( $item_type ) ) {
				// 2. aşama: ID yok, anahtar üzerinden eşleşme (ui_string + modül tipleri).
				$item_id = 0;
				$guncel  = rma_ceviri_guncel_orijinal( 0, $item_type, $field );

				if ( null === $guncel ) {
					rma_ceviri_atla( $rapor, $satir_no, 'bilinmeyen sabit metin anahtarı: ' . $field );
					continue;
				}
			} elseif ( 'option' === $item_type ) {
				$item_id = 0;
				$guncel  = rma_ceviri_guncel_orijinal( 0, 'option', $field );

				if ( null === $guncel ) {
					rma_ceviri_atla( $rapor, $satir_no, 'bilinmeyen yönetici ayarı: ' . $field );
					continue;
				}
			} elseif ( $item_id > 0 ) {
				// 1. aşama: ID ile doğrudan eşleşme (hedef hâlâ duruyor mu?).
				$guncel = rma_ceviri_guncel_orijinal( $item_id, $item_type, $field );

				if ( null === $guncel ) {
					rma_ceviri_atla( $rapor, $satir_no, 'hedef bulunamadı (silinmiş olabilir): ' . $item_type . ' #' . $item_id );
					continue;
				}
			} else {
				// 3. aşama: hiçbiri tutmuyor.
				rma_ceviri_atla( $rapor, $satir_no, 'item_id boş ve tip sabit metin değil' );
				continue;
			}

			/* --- Bayatlık kontrolü (import'u durdurmaz) --- */
			$csv_hash = '' !== trim( $orijinal_csv ) ? md5( $orijinal_csv ) : $hash_csv;

			if ( '' !== $csv_hash && $csv_hash !== md5( $guncel ) && count( $rapor['bayat'] ) < RMA_CEVIRI_RAPOR_ORNEK ) {
				$rapor['bayat'][] = array(
					'satir'    => $satir_no,
					'tip'      => $item_type,
					'id'       => $item_id,
					'field'    => $field,
					'orijinal' => wp_trim_words( $guncel, 12, '…' ),
				);
			}

			/* --- Yazma --- */
			foreach ( $diller as $dil => $sutun ) {
				$deger = isset( $satir[ $sutun ] ) ? trim( (string) $satir[ $sutun ] ) : '';

				if ( '' === $deger ) {
					if ( $temizle ) {
						$rapor['silindi'] += RMA_Ceviri_Tablo::sil( $item_id, $item_type, $field, $dil );
					}
					continue;
				}

				// Orijinal olarak DB'deki GÜNCEL metni saklıyoruz: çıktı değiştirici
				// sayfada gerçekten basılan metni arayacak, CSV'deki bayat kopyayı değil.
				if ( RMA_Ceviri_Tablo::upsert( $item_id, $item_type, $field, $dil, $guncel, wp_kses_post( $deger ) ) ) {
					++$rapor['guncellendi'];
				}
			}
		}

		return $rapor;
	}
}

/**
 * Atlanan satırı rapora işle.
 *
 * @param array  $rapor    Rapor (referans).
 * @param int    $satir_no Satır numarası.
 * @param string $neden    Atlanma nedeni.
 */
if ( ! function_exists( 'rma_ceviri_atla' ) ) {
	function rma_ceviri_atla( &$rapor, $satir_no, $neden ) {
		++$rapor['atlandi'];

		if ( count( $rapor['atlanan'] ) < RMA_CEVIRI_RAPOR_ORNEK ) {
			$rapor['atlanan'][] = array(
				'satir' => $satir_no,
				'neden' => $neden,
			);
		}
	}
}
