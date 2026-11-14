<?php
/**
 * qrms_analitik_arama_kelimesi() testi (WordPress gerekmez).
 *
 *   php tests/arama-kelime-test.php
 */
define( 'ABSPATH', __DIR__ . '/' );
require __DIR__ . '/../modules/qr-analiz/arama-kelime.php';

$hata = 0;
$say  = 0;
function esit( $beklenen, $gelen, $ad ) {
	global $hata, $say;
	$say++;
	if ( $beklenen !== $gelen ) {
		$hata++;
		echo "BAŞARISIZ: $ad\n  beklenen: " . var_export( $beklenen, true ) . "\n  gelen:    " . var_export( $gelen, true ) . "\n";
	}
}

$f = 'qrms_analitik_arama_kelimesi';

// Normal kelimeler ve küçük harfe çevirme.
esit( 'sushi', $f( 'Sushi' ), 'büyük harf' );
esit( 'sushi', $f( 'SUSHI' ), 'hepsi büyük' );
esit( 'adana kebap', $f( '  Adana   Kebap  ' ), 'fazla boşluk' );
esit( 'sushi', $f( "\tsushi\n" ), 'sekme/yeni satır' );

// Türkçe harfler: ı ve İ i'ye katlanır; ç ş ü ö ğ korunur.
esit( 'kahvalti', $f( 'Kahvaltı' ), 'ı → i' );
esit( 'kahvalti', $f( 'KAHVALTI' ), 'KAHVALTI' );
esit( 'kahvalti', $f( 'kahvalti' ), 'kahvalti (aynı kelime, tek grup)' );
esit( 'iskender', $f( 'İskender' ), 'İ → i' );
esit( 'iskender', $f( 'İSKENDER' ), 'İSKENDER' );
esit( 'çorba', $f( 'Çorba' ), 'ç korunur' );
esit( 'şalgam', $f( 'ŞALGAM' ), 'Ş → ş' );
esit( 'ice tea', $f( 'ICE TEA' ), 'ingilizce I → i' );

// HTML ve tehlikeli girdiler.
esit( 'alert1', $f( '<script>alert(1)</script>' ), 'betik etiketi: etiket atılır, kalan metin zararsız' );
esit( 'sushi', $f( '<b>sushi</b>' ), 'html etiketi' );
esit( 'sushi', $f( "sushi\0" ), 'null bayt' );
esit( 'drop table x', $f( "'; DROP TABLE x;--" ), 'sql benzeri: ; atılır, kenar noktalama atılır' );
esit( 'sushi', $f( "'sushi'" ), 'kenar kesme işareti' );
esit( 'rock n roll', $f( "rock n roll..." ), 'sondaki nokta' );
esit( "o'brien", $f( "O'Brien" ), 'ortadaki kesme işareti korunur' );
esit( 'sushi', $f( 'sushi 🍣' ), 'emoji atılır' );
esit( '', $f( '🍣🍣' ), 'yalnız emoji' );
esit( '', $f( '%%%' ), 'yalnız işaret' );
esit( 'sushi', $f( 'sus%hi' ), '% atılır' );

// Gürültü.
esit( '', $f( '' ), 'boş' );
esit( '', $f( '   ' ), 'yalnız boşluk' );
esit( '', $f( 'a' ), 'tek harf' );
esit( 'ab', $f( 'ab' ), 'iki harf geçer' );
esit( '', $f( '500' ), 'yalnız sayı (fiyat araması)' );
esit( '', $f( '12 34' ), 'yalnız sayılar' );
esit( '7up', $f( '7up' ), 'harfli sayı geçer' );
esit( 'coca-cola', $f( 'Coca-Cola' ), 'tire korunur' );
esit( 'm&m', $f( 'M&M' ), '& korunur' );

// Tür ve uzunluk.
esit( '', $f( null ), 'null' );
esit( '', $f( array( 'sushi' ) ), 'dizi' );
esit( '', $f( 123 ), 'sayı türü' );
$uzun = $f( str_repeat( 'a', 200 ) );
esit( 60, mb_strlen( $uzun, 'UTF-8' ), '60 karakter sınırı' );
$uzun2 = $f( str_repeat( 'ş', 100 ) );
esit( 60, mb_strlen( $uzun2, 'UTF-8' ), 'çok baytlı harflerde de 60 karakter' );
esit( true, 'ş' === mb_substr( $uzun2, 59, 1, 'UTF-8' ), 'karakter ortasından kesilmez' );

echo $hata ? "\n$hata / $say BAŞARISIZ\n" : "$say / $say geçti\n";
exit( $hata ? 1 : 0 );
