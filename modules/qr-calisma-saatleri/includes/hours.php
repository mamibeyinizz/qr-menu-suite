<?php
/**
 * QR Çalışma Saatleri — veri katmanı.
 *
 * Saatler `qrms_calisma_saatleri` option'ında tutulur (suite'in diğer
 * ayar modülleri gibi options tablosu). Post meta kullanılmaz: saatler
 * restorana aittir, tekil bir CPT kaydına değil.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'QRMS_CS_OPTION' ) ) {
	define( 'QRMS_CS_OPTION', 'qrms_calisma_saatleri' );
}

/**
 * Haftanın gün anahtarları (Pazartesi → Pazar, ISO sırası).
 *
 * @return string[]
 */
function qrms_cs_day_keys() {
	return array(
		'monday',
		'tuesday',
		'wednesday',
		'thursday',
		'friday',
		'saturday',
		'sunday',
	);
}

/**
 * Gün anahtarı → görünen ad (tablo → textdomain → Türkçe).
 *
 * @return array<string,string>
 */
function qrms_cs_day_labels() {
	return array(
		'monday'    => qrms_cs_cevir( __( 'Pazartesi', 'qrms' ) ),
		'tuesday'   => qrms_cs_cevir( __( 'Salı', 'qrms' ) ),
		'wednesday' => qrms_cs_cevir( __( 'Çarşamba', 'qrms' ) ),
		'thursday'  => qrms_cs_cevir( __( 'Perşembe', 'qrms' ) ),
		'friday'    => qrms_cs_cevir( __( 'Cuma', 'qrms' ) ),
		'saturday'  => qrms_cs_cevir( __( 'Cumartesi', 'qrms' ) ),
		'sunday'    => qrms_cs_cevir( __( 'Pazar', 'qrms' ) ),
	);
}

/**
 * Ön yüz saat metnini çeviri köprüsünden geçirir.
 *
 * Desen: rma_ceviri_modul( 'hours', __( '…', 'qrms' ) ). Çeviri kapalıysa
 * textdomain/Türkçe girdi aynen döner.
 *
 * @param string $metin Textdomain çıktısı (site dili TR iken Türkçe kaynak).
 * @return string
 */
function qrms_cs_cevir( $metin ) {
	if ( function_exists( 'rma_ceviri_modul' ) ) {
		return rma_ceviri_modul( 'hours', $metin );
	}

	return $metin;
}

/**
 * Varsayılan haftalık tablo.
 *
 * @return array<string,array{closed:bool,open:string,close:string}>
 */
function qrms_cs_defaults() {
	$days = array();

	foreach ( qrms_cs_day_keys() as $key ) {
		$days[ $key ] = array(
			'closed' => false,
			'open'   => '09:00',
			'close'  => '22:00',
		);
	}

	return $days;
}

/**
 * HH:MM (24 saat) doğrular; geçersizse boş string döner.
 *
 * @param mixed $value Ham değer.
 * @return string
 */
function qrms_cs_sanitize_time( $value ) {
	$value = sanitize_text_field( (string) $value );

	if ( ! preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $m ) ) {
		return '';
	}

	return sprintf( '%02d:%02d', (int) $m[1], (int) $m[2] );
}

/**
 * Form veya option girdisini tam bir haftalık tabloya indirger.
 *
 * @param mixed $input Ham dizi.
 * @return array<string,array{closed:bool,open:string,close:string}>
 */
function qrms_cs_sanitize( $input ) {
	$input    = is_array( $input ) ? $input : array();
	$defaults = qrms_cs_defaults();
	$out      = array();

	foreach ( qrms_cs_day_keys() as $key ) {
		$row     = ( isset( $input[ $key ] ) && is_array( $input[ $key ] ) ) ? $input[ $key ] : array();
		$closed  = ! empty( $row['closed'] ) && '0' !== (string) $row['closed'];
		$open    = qrms_cs_sanitize_time( isset( $row['open'] ) ? $row['open'] : '' );
		$close   = qrms_cs_sanitize_time( isset( $row['close'] ) ? $row['close'] : '' );

		if ( '' === $open ) {
			$open = $defaults[ $key ]['open'];
		}
		if ( '' === $close ) {
			$close = $defaults[ $key ]['close'];
		}

		$out[ $key ] = array(
			'closed' => $closed,
			'open'   => $open,
			'close'  => $close,
		);
	}

	return $out;
}

/**
 * Kayıtlı çalışma saatlerini döndürür (eksik günler varsayılanla tamamlanır).
 *
 * @return array<string,array{closed:bool,open:string,close:string}>
 */
function qrms_cs_get() {
	$stored = get_option( QRMS_CS_OPTION, array() );

	/**
	 * Çalışma saatleri tablosunu filtreler.
	 *
	 * @param array $hours Sanitize edilmiş haftalık tablo.
	 */
	return apply_filters( 'qrms_cs_hours', qrms_cs_sanitize( $stored ) );
}

/**
 * ISO gün numarası (1=Pazartesi … 7=Pazar) → anahtar.
 *
 * @param int $iso ISO-8601 gün numarası.
 * @return string
 */
function qrms_cs_key_from_iso( $iso ) {
	$keys = qrms_cs_day_keys();
	$iso  = (int) $iso;

	if ( $iso < 1 || $iso > 7 ) {
		$iso = 1;
	}

	return $keys[ $iso - 1 ];
}

/**
 * HH:MM → gece yarısından itibaren dakika.
 *
 * @param string $hhmm Saat.
 * @return int
 */
function qrms_cs_time_to_minutes( $hhmm ) {
	$parts = explode( ':', (string) $hhmm );

	if ( 2 !== count( $parts ) ) {
		return 0;
	}

	return ( (int) $parts[0] * 60 ) + (int) $parts[1];
}

/**
 * Verilen anda restoran açık mı?
 *
 * Kapanış saati açılıştan küçükse gece yarısını aşan mesai kabul edilir
 * (ör. 18:00–02:00). Açılış ve kapanış eşitse gün boyunca açıktır.
 *
 * @param int|null $timestamp Unix zamanı (site saatine göre yorumlanır).
 *                            Null ise şu an.
 * @param array|null $hours   Saat tablosu; null ise kayıtlı tablo.
 * @return bool
 */
function qrms_cs_is_open_at( $timestamp = null, $hours = null ) {
	if ( ! is_array( $hours ) ) {
		$hours = qrms_cs_get();
	}

	if ( null === $timestamp ) {
		$timestamp = time();
	}

	$iso = (int) wp_date( 'N', (int) $timestamp );
	$now = qrms_cs_time_to_minutes( wp_date( 'H:i', (int) $timestamp ) );
	$key = qrms_cs_key_from_iso( $iso );

	if ( qrms_cs_day_covers_minutes( isset( $hours[ $key ] ) ? $hours[ $key ] : array(), $now ) ) {
		return true;
	}

	// Önceki günün kapanışı gece yarısını aşıyorsa sabah saatleri ona aittir.
	$prev_iso = ( 1 === $iso ) ? 7 : ( $iso - 1 );
	$prev_key = qrms_cs_key_from_iso( $prev_iso );
	$prev     = isset( $hours[ $prev_key ] ) ? $hours[ $prev_key ] : array();

	if ( ! empty( $prev['closed'] ) ) {
		return false;
	}

	$open  = qrms_cs_time_to_minutes( isset( $prev['open'] ) ? $prev['open'] : '00:00' );
	$close = qrms_cs_time_to_minutes( isset( $prev['close'] ) ? $prev['close'] : '00:00' );

	return ( $close < $open && $now < $close );
}

/**
 * Bu takvim gününün kendi açılış–kapanış aralığı verilen dakikayı kapsar mı?
 *
 * Gece yarısını aşan mesaide yalnızca akşam dilimi bu güne yazılır; sabah
 * dilimi ertesi günün kontrolünde önceki günden okunur.
 *
 * @param array $day Gün satırı.
 * @param int   $now Gece yarısından dakika.
 * @return bool
 */
function qrms_cs_day_covers_minutes( $day, $now ) {
	$day = is_array( $day ) ? $day : array();

	if ( ! empty( $day['closed'] ) ) {
		return false;
	}

	$open  = qrms_cs_time_to_minutes( isset( $day['open'] ) ? $day['open'] : '00:00' );
	$close = qrms_cs_time_to_minutes( isset( $day['close'] ) ? $day['close'] : '00:00' );
	$now   = (int) $now;

	if ( $open === $close ) {
		return true;
	}

	if ( $close > $open ) {
		return ( $now >= $open && $now < $close );
	}

	return ( $now >= $open );
}

/**
 * Tek günün insan okunur metni.
 *
 * @param array $day Gün satırı.
 * @return string
 */
function qrms_cs_format_day( $day ) {
	$day = is_array( $day ) ? $day : array();

	if ( ! empty( $day['closed'] ) ) {
		return qrms_cs_cevir( __( 'Kapalı', 'qrms' ) );
	}

	$open  = isset( $day['open'] ) ? $day['open'] : '';
	$close = isset( $day['close'] ) ? $day['close'] : '';

	if ( $open === $close ) {
		return qrms_cs_cevir( __( '24 saat açık', 'qrms' ) );
	}

	// Biçim dizesinin kendisi çevrilebilir kalır: bazı dillerde ayraç ve
	// sıra (%2$s … %1$s) farklıdır.
	return sprintf(
		/* translators: 1: opening time, 2: closing time */
		qrms_cs_cevir( __( '%1$s – %2$s', 'qrms' ) ),
		$open,
		$close
	);
}
