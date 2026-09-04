<?php
/**
 * Test yardımcıları — birden çok modül dosyasının paylaştığı taklitler.
 *
 * PHP fonksiyon bildirimleri tek dosyada hoisted idi; bölününce erken
 * çağrılan yardımcılar burada durur.
 *
 * @package QR_Menu_Suite
 */

/**
 * Çalıştırılan HER sorguyu kaydeden $wpdb taklidi.
 *
 * Bu bölümün asıl güvencesi sorgu SAYISIdır: birleştirilen sorgular yeniden
 * bölünürse testler düşer.
 */
class QRMS_Sayan_Wpdb {
	public $prefix   = 'wp_';
	public $postmeta = 'wp_postmeta';
	public $queries  = array();
	public $rows     = array();
	public $vars     = array();
	public $results  = array();
	public $cols     = array();
	public $dbh      = true;
	public $kapandi = 0;
	public $acildi  = 0;

	public function prepare( $sql, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		return preg_replace_callback(
			'/%[dsf]/',
			function ( $m ) use ( &$args ) {
				$value = array_shift( $args );

				if ( '%d' === $m[0] ) {
					return (string) (int) $value;
				}
				if ( '%f' === $m[0] ) {
					return (string) (float) $value;
				}

				return "'" . str_replace( "'", "\\'", (string) $value ) . "'";
			},
			$sql
		);
	}

	public function esc_like( $t ) {
		return $t;
	}

	public function suppress_errors( $suppress = true ) {
		return false;
	}

	public function get_row( $sql, $mode = null ) {
		$this->queries[] = $sql;

		return array_shift( $this->rows );
	}

	public function get_var( $sql ) {
		$this->queries[] = $sql;

		return array_shift( $this->vars );
	}

	public function get_results( $sql, $mode = null ) {
		$this->queries[] = $sql;

		$next = array_shift( $this->results );

		return is_array( $next ) ? $next : array();
	}

	/**
	 * Tek sütun döndüren sorgular. RMA_Ekstra::kullanim_sayilari() gibi
	 * `SELECT meta_value FROM wp_postmeta WHERE meta_key = '...'` biçimindeki
	 * sorguları $GLOBALS['qrms_test']['post_meta'] üzerinden yanıtlar —
	 * QRMS_Test_Wpdb::get_col() ile aynı sözleşme (bkz. test-core.php).
	 *
	 * @param string $sql Sorgu.
	 * @return array
	 */
	public function get_col( $sql ) {
		$this->queries[] = $sql;

		if ( ! empty( $this->cols ) ) {
			$next = array_shift( $this->cols );

			return is_array( $next ) ? $next : array();
		}

		if ( false === strpos( $sql, 'postmeta' ) || ! preg_match( "/meta_key = '([^']+)'/", $sql, $eslesme ) ) {
			return array();
		}

		$anahtar = $eslesme[1];
		$sonuc   = array();

		foreach ( $GLOBALS['qrms_test']['post_meta'] ?? array() as $meta ) {
			if ( ! isset( $meta[ $anahtar ] ) ) {
				continue;
			}

			$deger   = $meta[ $anahtar ];
			$sonuc[] = is_array( $deger ) ? serialize( $deger ) : (string) $deger; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		}

		return $sonuc;
	}

	public function close() {
		$this->kapandi++;
		$this->dbh = null;

		return true;
	}

	public function db_connect( $allow_bail = true ) {
		$this->acildi++;
		$this->dbh = true;

		return true;
	}

	/** Kaydedilen sorgulardan verilen parçayı içerenlerin sayısı. */
	public function kac_kez( $parca ) {
		$sayi = 0;

		foreach ( $this->queries as $q ) {
			if ( false !== stripos( $q, $parca ) ) {
				$sayi++;
			}
		}

		return $sayi;
	}
}

/**
 * Bu bölüm için taze bir $wpdb takar ve önbellekleri temizler.
 *
 * @return QRMS_Sayan_Wpdb
 */
function qrms_sayan_wpdb() {
	$GLOBALS['wpdb'] = new QRMS_Sayan_Wpdb();

	$GLOBALS['qrms_test']['transients'] = array();
	unset( $GLOBALS['qrm_pro_stats_memo'], $GLOBALS['qrm_cf_unread_memo'] );

	return $GLOBALS['wpdb'];
}
