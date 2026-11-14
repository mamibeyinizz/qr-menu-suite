/**
 * Panel özellik araması eşleştiricisi testi.
 *
 *   node tests/ozellik-arama-test.js
 */
'use strict';
const O = require( '../assets/js/ozellik-arama.js' );

let hata = 0, say = 0;
function esit( beklenen, gelen, ad ) {
	say++;
	if ( JSON.stringify( beklenen ) !== JSON.stringify( gelen ) ) {
		hata++;
		console.log( 'BAŞARISIZ: ' + ad + '\n  beklenen: ' + JSON.stringify( beklenen ) + '\n  gelen:    ' + JSON.stringify( gelen ) );
	}
}

const dizin = [
	{ b: 'Menüde Bulunamayan Aramalar', g: 'Menü Analizleri › Ürünler', a: 'Müşterilerin aradığı ama menünüzde olmayan ürünler.', k: 'keyword keywords anahtar kelime arama kelimeleri aranan', u: 'an#arama' },
	{ b: 'Kampanyalar', g: 'Menü Yönetimi', a: '', k: 'ürün fiyat indirim kampanya', u: 'kampanya' },
	{ b: 'Masalar', g: 'QR Kodlar', a: '', k: 'masa qr karekod yazdır', u: 'masalar' },
	{ b: 'Toplu QR Oluştur', g: 'QR Kodlar', a: '', k: 'masa qr karekod yazdır', u: 'toplu' },
	{ b: 'Diller & Çeviriler', g: 'Müşteri Deneyimi', a: 'Menünüzü farklı dillerde yayınlayın.', k: 'çeviri dil ingilizce translate', u: 'ceviri' },
	{ b: 'Çalışma Saatleri', g: 'Müşteri Deneyimi', a: '', k: 'saat açık kapalı tatil', u: 'saat' },
	{ b: 'Giriş Ekranı', g: 'Sistem Ayarları', a: '', k: 'giriş login şifre parola', u: 'giris' },
	{ b: 'Servis Paneli', g: 'Menü', a: 'Sipariş, garson ve hesap çağrıları.', k: 'sipariş garson hesap', u: 'servis' },
	{ b: 'İstatistik Genel Bakış', g: 'Menü Analizleri', a: '', k: 'istatistik rapor', u: 'istat' },
];
const h = O.hazirla( dizin );
const ilk = ( q ) => { const r = O.ara( h, q ); return r.length ? r[ 0 ].u : null; };

// Katlama.
esit( 'menude bulunamayan aramalar', O.katla( 'Menüde Bulunamayan Aramalar' ), 'katlama ü' );
esit( 'cikolata sut ig', O.katla( 'ÇİKOLATA Süt ığ' ), 'katlama Ç İ ü ı ğ' );
esit( 'istatistik', O.katla( 'İSTATİSTİK' ), 'büyük İ' );
esit( 'ilik', O.katla( 'ILIK' ), 'büyük I → i' );

// Kullanıcının örneği: "keywords" → Analiz'deki arama bölümü.
esit( 'an#arama', ilk( 'keywords' ), 'keywords → Menüde Bulunamayan Aramalar' );
esit( 'an#arama', ilk( 'Keywords' ), 'büyük harf' );
esit( 'an#arama', ilk( 'keywrds' ), 'yazım hatası (1 harf eksik)' );
esit( 'an#arama', ilk( 'keywrod' ), 'yazım hatası (harf yer değiştirme, yazarken)' );
esit( 'an#arama', ilk( 'anahtar kelime' ), 'Türkçe eşanlamlı' );
esit( 'an#arama', ilk( 'bulunamayan' ), 'başlıktaki kelime' );
esit( 'an#arama', ilk( 'aranan urunler' ), 'aksansız çok kelime' );

// Önek ve Türkçe harfler.
esit( 'kampanya', ilk( 'kamp' ), 'önek' );
esit( 'kampanya', ilk( 'indirim' ), 'eşanlamlı' );
esit( 'ceviri', ilk( 'ceviri' ), 'aksansız çeviri' );
esit( 'ceviri', ilk( 'ÇEVİRİ' ), 'büyük harf Türkçe' );
esit( 'ceviri', ilk( 'ingilizce' ), 'eşanlamlı: ingilizce' );
esit( 'saat', ilk( 'calisma saati' ), 'aksansız iki kelime' );
esit( 'giris', ilk( 'sifre' ), 'eşanlamlı: şifre aksansız' );
esit( 'servis', ilk( 'garson' ), 'eşanlamlı: garson' );
esit( 'servis', ilk( 'siparis' ), 'açıklama + eşanlamlı' );
esit( 'istat', ilk( 'istatistik' ), 'başlık önek İ' );

// Çok kelimede en çok eşleşen öne çıkar.
esit( 'toplu', ilk( 'toplu qr' ), 'toplu qr → Toplu QR Oluştur' );
esit( 'masalar', ilk( 'masalar' ), 'tam başlık' );

// Boş ve anlamsız sorgu.
esit( [], O.ara( h, '' ), 'boş' );
esit( [], O.ara( h, 'a' ), 'tek harf' );
esit( [], O.ara( h, 'zzzqqq' ), 'eşleşmeyen' );
esit( 8, O.ara( h.concat( h ), 'a b c d e masa kampanya ceviri saat' ).length <= 8 ? 8 : -1, 'en fazla 8 sonuç' );

console.log( hata ? `\n${ hata } / ${ say } BAŞARISIZ` : `${ say } / ${ say } geçti` );
process.exit( hata ? 1 : 0 );
