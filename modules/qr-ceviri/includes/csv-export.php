<?php
/**
 * Çeviri CSV'sini dışa aktarma.
 *
 * Sütunlar:
 *   item_id | item_type | field | original_text | original_hash | en | de | …
 *
 * Mevcut çeviriler ilgili dil sütununa dolu gelir; böylece kısmi güncelleme
 * yapılabilir, her seferinde sıfırdan çeviri gerekmez.
 *
 * admin-post.php + nonce + current_user_can deseni QR Menu Official'daki
 * menu-data-upload.php ile aynı.
 *
 * @package QRMenu_Ceviri
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ini memory_limit / boyut dizgesini bayta çevirir.
 *
 * WordPress'e bağımsız — test edilebilir. "-1" veya boş = sınırsız.
 *
 * @param string $deger "128M", "1G", "262144" …
 * @return int Bayt; sınırsızsa -1.
 */
if ( ! function_exists( 'rma_ceviri_bayt_coz' ) ) {
	function rma_ceviri_bayt_coz( $deger ) {
		$deger = trim( (string) $deger );

		if ( '' === $deger || '-1' === $deger ) {
			return -1;
		}

		$birim = strtolower( substr( $deger, -1 ) );
		$sayi  = (float) $deger;
		$kat   = array(
			'g' => 1073741824,
			'm' => 1048576,
			'k' => 1024,
		);

		if ( isset( $kat[ $birim ] ) ) {
			$sayi *= $kat[ $birim ];
		}

		return (int) $sayi;
	}
}

/**
 * CSV uçları için bellek tavanını yükseltmeyi dener.
 *
 * @param string $minimum Hedef sınır (ör. 256M).
 * @return int Yeni sınır bayt cinsinden; -1 = sınırsız.
 */
if ( ! function_exists( 'rma_ceviri_bellek_pay_ayir' ) ) {
	function rma_ceviri_bellek_pay_ayir( $minimum = '256M' ) {
		$hedef  = rma_ceviri_bayt_coz( $minimum );
		$mevcut = rma_ceviri_bayt_coz( (string) ini_get( 'memory_limit' ) );

		if ( -1 !== $mevcut && ( -1 === $hedef || $mevcut < $hedef ) ) {
			// phpcs:ignore WordPress.PHP.IniSet.memory_limit_Disallowed,WordPress.PHP.NoSilencedErrors.Discouraged
			@ini_set( 'memory_limit', $minimum );
			$mevcut = rma_ceviri_bayt_coz( (string) ini_get( 'memory_limit' ) );
		}

		return $mevcut;
	}
}

/**
 * Bellek kullanımının sınıra yaklaştığı eşiği.
 *
 * @param float $pay 0–1 arası doluluk (varsayılan %85).
 * @return bool
 */
if ( ! function_exists( 'rma_ceviri_bellek_sinirda_mi' ) ) {
	function rma_ceviri_bellek_sinirda_mi( $pay = 0.85 ) {
		$limit = rma_ceviri_bayt_coz( (string) ini_get( 'memory_limit' ) );

		if ( $limit < 1 ) {
			return false;
		}

		$pay = (float) $pay;
		if ( $pay <= 0 || $pay > 1 ) {
			$pay = 0.85;
		}

		return memory_get_usage( true ) >= (int) ( $limit * $pay );
	}
}

add_action( 'admin_post_rma_ceviri_export', 'rma_ceviri_csv_disa_aktar' );

/**
 * CSV'yi üret ve indirt.
 */
if ( ! function_exists( 'rma_ceviri_csv_disa_aktar' ) ) {
	function rma_ceviri_csv_disa_aktar() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Yetkiniz yok.' );
		}
		check_admin_referer( 'rma_ceviri_export_action', 'rma_ceviri_export_nonce' );

		$ayrac = ( isset( $_POST['ayrac'] ) && ',' === $_POST['ayrac'] ) ? ',' : ';';

		// Elementor sayfa seçimi bu formdan geliyor; bir sonraki dışa aktarımda
		// hatırlansın diye seçeneğe de yazılıyor. Silinmiş ID'ler yazılmaz.
		$secili = isset( $_POST['elementor_sayfalar'] )
			? array_map( 'intval', (array) $_POST['elementor_sayfalar'] )
			: array();
		if ( function_exists( 'rma_ceviri_elementor_secimini_ele' ) ) {
			$secili = rma_ceviri_elementor_secimini_ele( $secili );
		}
		update_option( 'rma_ceviri_elementor_sayfalar', $secili, false );

		if ( function_exists( 'rma_ceviri_bellek_pay_ayir' ) ) {
			rma_ceviri_bellek_pay_ayir();
		}

		// Uzun menülerde tarama zaman alabilir.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$diller = rma_ceviri_hedef_diller();
		$mevcut = RMA_Ceviri_Tablo::export_haritasi();

		if ( function_exists( 'rma_ceviri_bellek_sinirda_mi' ) && rma_ceviri_bellek_sinirda_mi() ) {
			wp_die(
				'Çeviri tablosu bu sunucunun belleğine sığmıyor. Dil sayısını azaltın veya barındırıcıdan bellek sınırını yükseltin.',
				'Bellek yetersiz',
				array( 'response' => 507 )
			);
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header(
			'Content-Disposition: attachment; filename=qr-ceviri-' . gmdate( 'Y-m-d-Hi' ) . '.csv'
		);

		// Excel'in UTF-8'i doğru açması için BOM.
		echo "\xEF\xBB\xBF";

		$cikti = fopen( 'php://output', 'w' );

		fputcsv(
			$cikti,
			array_merge(
				array( 'item_id', 'item_type', 'field', 'original_text', 'original_hash' ),
				$diller
			),
			$ayrac,
			'"',
			'\\'
		);

		$satir_no = 0;
		foreach ( rma_ceviri_kaynak_satirlari() as $satir ) {
			++$satir_no;
			$anahtar = rma_ceviri_anahtar( $satir['item_type'], $satir['item_id'], $satir['field'] );

			$hucreler = array(
				$satir['item_id'],
				$satir['item_type'],
				$satir['field'],
				$satir['original'],
				md5( $satir['original'] ),
			);

			foreach ( $diller as $dil ) {
				$hucreler[] = isset( $mevcut[ $anahtar ][ $dil ] ) ? $mevcut[ $anahtar ][ $dil ] : '';
			}

			if ( 0 === ( $satir_no % 200 ) && function_exists( 'rma_ceviri_bellek_sinirda_mi' ) && rma_ceviri_bellek_sinirda_mi() ) {
				break;
			}

			fputcsv( $cikti, $hucreler, $ayrac, '"', '\\' );
		}

		update_option( 'rma_ceviri_son_disa', time(), false );

		fclose( $cikti );
		exit;
	}
}
