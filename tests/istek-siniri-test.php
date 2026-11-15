<?php
/**
 * Menü isteği sınırı: Wi-Fi'yi paylaşan masalar birbirinin hakkını yemez (WordPress gerekmez).
 *
 *   php tests/istek-siniri-test.php
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['tr'] = array();
function apply_filters( $ad, $deger ) { return $deger; }
function sanitize_text_field( $s ) { return trim( (string) $s ); }
function wp_unslash( $s ) { return $s; }
function wp_using_ext_object_cache() { return false; }
function get_transient( $k ) { return isset( $GLOBALS['tr'][ $k ] ) ? $GLOBALS['tr'][ $k ] : false; }
function set_transient( $k, $v, $t ) { $GLOBALS['tr'][ $k ] = $v; return true; }
class Sinir_Asildi extends Exception {}
function wp_send_json_error( $d, $kod ) { throw new Sinir_Asildi( (string) $kod ); }

require __DIR__ . '/../modules/restoran-menu/includes/trait-ajax.php';

class Deneme { use RMA_Ajax_Trait; private function flush_stray_output() {}
	public function dene( $limit ) { $this->rma_ip_rate_limit( 'load', 'x', $limit ); } }

$hata = 0; $say = 0;
function esit( $b, $g, $ad ) { global $hata, $say; $say++; if ( $b !== $g ) { $hata++; echo "BAŞARISIZ: $ad\n  beklenen: " . var_export( $b, true ) . "\n  gelen:    " . var_export( $g, true ) . "\n"; } }
function kac_gecti( $d, $sid, $adet, $limit = 60 ) {
	$_POST['sid'] = $sid; $g = 0;
	for ( $i = 0; $i < $adet; $i++ ) { try { $d->dene( $limit ); $g++; } catch ( Sinir_Asildi $e ) {} }
	return $g;
}

$_SERVER['REMOTE_ADDR'] = '85.1.2.3'; // restoranın Wi-Fi'si: herkes aynı IP
$d = new Deneme();

// 30 masa, her biri 50 istek: eskiden ilk 60'tan sonra herkes 429 alıyordu.
$toplam = 0;
for ( $m = 1; $m <= 30; $m++ ) { $toplam += kac_gecti( $d, 'masa' . str_pad( (string) $m, 6, '0', STR_PAD_LEFT ), 50 ); }
esit( 1500, $toplam, '30 masa x 50 istek: hepsi geçer (her telefonun kendi hakkı)' );

// Tek telefon kendi sınırını aşarsa yalnız o durur.
$GLOBALS['tr'] = array();
esit( 60, kac_gecti( $d, 'telefona1b2', 75 ), 'tek telefon: 60 geçer, fazlası durur' );
esit( 60, kac_gecti( $d, 'telefonb9c8', 60 ), 'yan masadaki telefon etkilenmez' );

// sid'i her istekte değiştiren bot: IP toplam tavanı (30 x 60 = 1800) durdurur.
$GLOBALS['tr'] = array(); $g = 0;
for ( $i = 0; $i < 2000; $i++ ) { $_POST['sid'] = 'bot' . md5( (string) $i ); try { $d->dene( 60 ); $g++; } catch ( Sinir_Asildi $e ) {} }
esit( 1800, $g, 'sid döndüren bot: IP toplam tavanında (30 x 60) durur' );

// sid yoksa (eski önbellekli sayfa) eski davranış: IP başına 60.
$GLOBALS['tr'] = array();
esit( 60, kac_gecti( $d, '', 70 ), 'sid yok: IP başına 60' );
esit( 0, kac_gecti( $d, 'ab', 5 ), 'çok kısa sid geçersiz sayılır, IP sayacına düşer' );

// Pencere sonunda hak yenilenir.
$GLOBALS['tr'] = array();
kac_gecti( $d, 'telefonzz11', 61 );
foreach ( $GLOBALS['tr'] as $k => $v ) { $GLOBALS['tr'][ $k ]['t'] -= 61; }
esit( 60, kac_gecti( $d, 'telefonzz11', 60 ), 'bir dakika sonra hak yenilenir' );

echo ( $say - $hata ) . " / $say geçti\n";
exit( $hata ? 1 : 0 );
