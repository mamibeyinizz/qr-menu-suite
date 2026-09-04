<?php
/**
 * Menü Mühendisliği testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/qr-menu-muhendisligi/includes/class-qrms-mm-hesap.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-menu-muhendisligi/includes/class-qrms-mm-maliyet.php';

echo "\nMenü Mühendisliği\n";

/**
 * Test için ürün satırı.
 *
 * @param int    $id      Kimlik.
 * @param string $ad      Ad.
 * @param mixed  $fiyat   Fiyat.
 * @param mixed  $maliyet Maliyet.
 * @param int    $satis   Sipariş adedi.
 * @param int    $sepet   Sepete ekleme.
 * @param int    $tik     Görüntülenme.
 * @return array
 */
function qrms_mm_satir( $id, $ad, $fiyat, $maliyet, $satis, $sepet = 0, $tik = 0 ) {
	return array(
		'item_id'       => $id,
		'item_name'     => $ad,
		'category_name' => 'Ana Yemek',
		'fiyat'         => $fiyat,
		'maliyet'       => $maliyet,
		'satis'         => $satis,
		'sepet'         => $sepet,
		'tik'           => $tik,
	);
}

/**
 * Sonuçtan ürünü kutusuyla birlikte bulur.
 *
 * @param array  $sonuc Hesap çıktısı.
 * @param string $ad    Ürün adı.
 * @return array|null
 */
function qrms_mm_bul( array $sonuc, $ad ) {
	foreach ( $sonuc['urunler'] as $urun ) {
		if ( $urun['item_name'] === $ad ) {
			return $urun;
		}
	}

	return null;
}

qrms_test(
	'dört kutu doğru dağılır: çok satan/kârlı ekseni',
	function () {
		// Toplam 100 adet, 4 ürün. Popülerlik eşiği (1/4)*0.70 = %17,5.
		// Ağırlıklı ortalama katkı payı:
		//   (60*20 + 30*5 + 5*40 + 5*3) / 100 = (1200+150+200+15)/100 = 15,65
		$satirlar = array(
			qrms_mm_satir( 1, 'Yıldız Ürün', 50, 30, 60 ),   // pay %60  katkı 20  -> yıldız
			qrms_mm_satir( 2, 'İş Atı Ürün', 25, 20, 30 ),   // pay %30  katkı  5  -> iş atı
			qrms_mm_satir( 3, 'Bulmaca Ürün', 90, 50, 5 ),   // pay  %5  katkı 40  -> bulmaca
			qrms_mm_satir( 4, 'Köpek Ürün', 15, 12, 5 ),     // pay  %5  katkı  3  -> köpek
		);

		$sonuc = QRMS_MM_Hesap::hesapla( $satirlar, array( 'populerlik_esigi' => 0.70 ) );

		qrms_assert_same( 'siparis', $sonuc['kaynak'], 'gerçek satış verisi' );
		qrms_assert_same( 'yildiz', qrms_mm_bul( $sonuc, 'Yıldız Ürün' )['kutu'], 'yıldız' );
		qrms_assert_same( 'is_ati', qrms_mm_bul( $sonuc, 'İş Atı Ürün' )['kutu'], 'iş atı' );
		qrms_assert_same( 'bulmaca', qrms_mm_bul( $sonuc, 'Bulmaca Ürün' )['kutu'], 'bulmaca' );
		qrms_assert_same( 'kopek', qrms_mm_bul( $sonuc, 'Köpek Ürün' )['kutu'], 'köpek' );

		qrms_assert_same( 1, $sonuc['ozet']['kutular']['yildiz'], 'yıldız sayacı' );
		qrms_assert_same( 100, $sonuc['ozet']['toplam_adet'], 'toplam adet' );

		// Ortalama katkı payı adetle AĞIRLIKLIDIR: düz ortalama alınsaydı
		// (20+5+40+3)/4 = 17 çıkar, hiç satmayan pahalı ürün eşiği yukarı
		// çeker ve İş Atı yanlışlıkla Köpek olurdu.
		qrms_assert_same( 15.65, round( $sonuc['ozet']['esik_katki'], 2 ), 'ağırlıklı ortalama' );
	}
);

qrms_test(
	'eşiği tam tutturan ürün "yüksek" tarafa yazılır',
	function () {
		// İki ürün, eşit satış: her birinin payı %50, eşik (1/2)*0.7 = %35.
		// Katkılar eşit -> ikisi de ortalamaya EŞİT, ikisi de yıldız olmalı.
		$sonuc = QRMS_MM_Hesap::hesapla(
			array(
				qrms_mm_satir( 1, 'A', 40, 20, 15 ),
				qrms_mm_satir( 2, 'B', 40, 20, 15 ),
			)
		);

		qrms_assert_same( 'yildiz', qrms_mm_bul( $sonuc, 'A' )['kutu'], 'sınırda yüksek' );
		qrms_assert_same( 'yildiz', qrms_mm_bul( $sonuc, 'B' )['kutu'], 'sınırda yüksek' );
		qrms_assert_same( 2, $sonuc['ozet']['kutular']['yildiz'], 'ikisi de yıldız' );
	}
);

qrms_test(
	'fiyatı ya da maliyeti olmayan ürün matrise GİRMEZ, eksik listesine düşer',
	function () {
		$sonuc = QRMS_MM_Hesap::hesapla(
			array(
				qrms_mm_satir( 1, 'Tam', 40, 20, 30 ),
				qrms_mm_satir( 2, 'Maliyetsiz', 40, null, 30 ),
				qrms_mm_satir( 3, 'Fiyatsız', null, 20, 30 ),
				qrms_mm_satir( 4, 'Sıfır fiyat', 0, 20, 30 ),
			)
		);

		qrms_assert_same( 1, count( $sonuc['urunler'] ), 'yalnızca tam olan' );
		qrms_assert_same( 3, count( $sonuc['eksik'] ), 'üç eksik' );

		$sebepler = wp_list_pluck( $sonuc['eksik'], 'sebep' );

		qrms_assert_true( in_array( 'Maliyet girilmemiş', $sebepler, true ), 'maliyet sebebi' );
		qrms_assert_true( in_array( 'Fiyat girilmemiş', $sebepler, true ), 'fiyat sebebi' );

		// Eksik ürünler toplam adede de girmez: girseydi popülerlik eşiği
		// hesaplanamayan ürünler yüzünden bozulurdu.
		qrms_assert_same( 30, $sonuc['ozet']['toplam_adet'], 'yalnızca geçerli ürünün adedi' );
	}
);

qrms_test(
	'sipariş verisi azken vekil skora düşülür ve bu bildirilir',
	function () {
		// Toplam satış 6 < MIN_SATIS (20) -> vekil skor devreye girer.
		$sonuc = QRMS_MM_Hesap::hesapla(
			array(
				qrms_mm_satir( 1, 'Bakılan', 40, 20, 3, 10, 40 ),  // 40 + 10*3 = 70
				qrms_mm_satir( 2, 'Bakılmayan', 40, 20, 3, 1, 5 ), // 5 + 1*3 = 8
			)
		);

		qrms_assert_same( 'vekil', $sonuc['kaynak'], 'vekil skora düşüldü' );
		qrms_assert_same( 70, qrms_mm_bul( $sonuc, 'Bakılan' )['adet'], 'tık + 3×sepet' );
		qrms_assert_same( 8, qrms_mm_bul( $sonuc, 'Bakılmayan' )['adet'], 'tık + 3×sepet' );

		// Gerçek sipariş adedi kaybolmaz; ekranda ayrıca gösterilebilsin.
		qrms_assert_same( 3, qrms_mm_bul( $sonuc, 'Bakılan' )['satis'], 'gerçek satış saklanır' );
	}
);

qrms_test(
	'satış eşiği aşıldığında gerçek sipariş verisine geçilir',
	function () {
		$sonuc = QRMS_MM_Hesap::hesapla(
			array(
				qrms_mm_satir( 1, 'A', 40, 20, 20, 99, 999 ),
			)
		);

		qrms_assert_same( 'siparis', $sonuc['kaynak'], 'eşik tam aşıldı' );
		qrms_assert_same( 20, qrms_mm_bul( $sonuc, 'A' )['adet'], 'sepet/tık yok sayılır' );
	}
);

qrms_test(
	'kayıp fırsat yalnızca ortalamanın ALTINDA kalan ürünlerden toplanır',
	function () {
		$sonuc = QRMS_MM_Hesap::hesapla(
			array(
				qrms_mm_satir( 1, 'İyi', 50, 30, 50 ),  // katkı 20
				qrms_mm_satir( 2, 'Kötü', 20, 15, 50 ), // katkı  5
			)
		);

		// Ağırlıklı ortalama: (20*50 + 5*50)/100 = 12,5
		// Kayıp yalnızca "Kötü"den: (12,5 - 5) * 50 = 375
		qrms_assert_same( 375.0, round( $sonuc['ozet']['kayip_firsat'], 2 ), 'kayıp fırsat' );
		qrms_assert_same( 0.0, qrms_mm_bul( $sonuc, 'İyi' )['kayip'], 'ortalamanın üstü kayıp yazmaz' );
	}
);

qrms_test(
	'hiç geçerli ürün yokken özet sıfırlanır, sıfıra bölünmez',
	function () {
		$sonuc = QRMS_MM_Hesap::hesapla( array( qrms_mm_satir( 1, 'Eksik', null, null, 0 ) ) );

		qrms_assert_same( array(), $sonuc['urunler'], 'ürün yok' );
		qrms_assert_same( 0, $sonuc['ozet']['toplam_adet'], 'adet sıfır' );
		qrms_assert_same( 0.0, $sonuc['ozet']['ort_marj'], 'marj sıfır' );
		qrms_assert_same( 0.0, $sonuc['ozet']['kayip_firsat'], 'kayıp sıfır' );
	}
);

qrms_test(
	'hiç satış yokken kârlılık eşiği düz ortalamaya düşer',
	function () {
		// Adet toplamı sıfırken ağırlıklı ortalama hesaplanamaz; eşik
		// anlamsız bir yere düşerse bütün ürünler tek kutuda toplanırdı.
		$sonuc = QRMS_MM_Hesap::hesapla(
			array(
				qrms_mm_satir( 1, 'A', 40, 30, 0 ),  // katkı 10
				qrms_mm_satir( 2, 'B', 40, 10, 0 ),  // katkı 30
			)
		);

		qrms_assert_same( 20.0, round( $sonuc['ozet']['esik_katki'], 2 ), 'düz ortalama' );
		qrms_assert_same( 'kopek', qrms_mm_bul( $sonuc, 'A' )['kutu'], 'ortalamanın altı' );
		qrms_assert_same( 'bulmaca', qrms_mm_bul( $sonuc, 'B' )['kutu'], 'ortalamanın üstü' );
	}
);

qrms_test(
	'her kutunun rengi, ikonu ve aksiyon cümlesi tanımlı',
	function () {
		foreach ( array_keys( QRMS_MM_Hesap::kutular() ) as $kutu ) {
			qrms_assert_same( 0, strpos( QRMS_MM_Hesap::kutu_ikonu( $kutu ), 'dashicons-' ), $kutu . ' ikonu dashicon' );
			qrms_assert_same( 0, strpos( QRMS_MM_Hesap::kutu_rengi( $kutu ), '#' ), $kutu . ' rengi hex' );
			qrms_assert_true( '' !== QRMS_MM_Hesap::aksiyon( $kutu ), $kutu . ' aksiyonu dolu' );
			qrms_assert_true( '' !== QRMS_MM_Hesap::kutu_anlami( $kutu ), $kutu . ' anlamı dolu' );
		}
	}
);

/* ---------------------------------------------------------------------------
 * Maliyet ve reçete
 * ------------------------------------------------------------------------ */

echo "\nMenü Mühendisliği — maliyet ve reçete\n";

qrms_test(
	'reçete maliyeti birim çevrimini ve fireyi doğru uygular',
	function () {
		$fiyatlar = array(
			1 => array( 'birim' => 'kg', 'fiyat' => 300.0 ),   // 300 ₺/kg
			2 => array( 'birim' => 'lt', 'fiyat' => 80.0 ),    // 80 ₺/lt
			3 => array( 'birim' => 'adet', 'fiyat' => 2.5 ),   // 2,50 ₺/adet
		);

		$recete = array(
			array( 'term_id' => 1, 'miktar' => 150 ),  // 150 g  -> 45,00
			array( 'term_id' => 2, 'miktar' => 50 ),   // 50 ml  ->  4,00
			array( 'term_id' => 3, 'miktar' => 2 ),    // 2 adet ->  5,00
		);

		qrms_assert_same( 54.0, QRMS_MM_Maliyet::recete_maliyeti( $recete, $fiyatlar, 0 ), 'firesiz' );

		// %10 fire -> 54 * 1,10 = 59,40
		qrms_assert_same( 59.4, QRMS_MM_Maliyet::recete_maliyeti( $recete, $fiyatlar, 10 ), 'fireli' );

		// Fiyatı girilmemiş malzeme sıfır sayılır, hesabı çökertmez.
		$eksik = array( array( 'term_id' => 99, 'miktar' => 500 ) );
		qrms_assert_same( 0.0, QRMS_MM_Maliyet::recete_maliyeti( $eksik, $fiyatlar, 0 ), 'fiyatsız malzeme' );
	}
);

qrms_test(
	'virgüllü ve binlik ayraçlı sayılar doğru okunur',
	function () {
		// Türkçe klavyede ondalık ayracı virgüldür; "12,50" yazan kullanıcının
		// maliyeti 12 TL'ye yuvarlanmamalı.
		qrms_assert_same( 12.5, QRMS_MM_Maliyet::sayi( '12,50' ), 'virgül ondalık' );
		qrms_assert_same( 1250.75, QRMS_MM_Maliyet::sayi( '1.250,75' ), 'binlik + ondalık' );
		qrms_assert_same( 12.5, QRMS_MM_Maliyet::sayi( '12.50' ), 'nokta ondalık' );
		qrms_assert_same( 45.0, QRMS_MM_Maliyet::sayi( '45 ₺' ), 'birim atılır' );
		qrms_assert_same( 0.0, QRMS_MM_Maliyet::sayi( '' ), 'boş sıfır' );
		qrms_assert_same( 0.0, QRMS_MM_Maliyet::sayi( 'abc' ), 'metin sıfır' );
	}
);

qrms_test(
	'reçete ve malzeme girdileri temizlenir',
	function () {
		$recete = QRMS_MM_Maliyet::recete_temizle(
			array(
				array( 'term_id' => '5', 'miktar' => '150' ),
				array( 'term_id' => 0, 'miktar' => '100' ),    // malzeme seçilmemiş
				array( 'term_id' => 7, 'miktar' => '0' ),      // miktar sıfır
				'bozuk',
			)
		);

		qrms_assert_same( 1, count( $recete ), 'yalnızca geçerli satır' );
		qrms_assert_same( 5, $recete[0]['term_id'], 'terim tamsayıya çevrilir' );
		qrms_assert_same( 150.0, $recete[0]['miktar'], 'miktar float' );

		$malzeme = QRMS_MM_Maliyet::malzeme_temizle(
			array(
				3  => array( 'birim' => 'lt', 'fiyat' => '80,50' ),
				4  => array( 'birim' => 'uydurma', 'fiyat' => '10' ),  // birim varsayılana düşer
				5  => array( 'birim' => 'kg', 'fiyat' => '0' ),        // fiyatsız saklanmaz
				0  => array( 'birim' => 'kg', 'fiyat' => '10' ),       // terim yok
			)
		);

		qrms_assert_same( 80.5, $malzeme[3]['fiyat'], 'virgüllü fiyat' );
		qrms_assert_same( 'lt', $malzeme[3]['birim'], 'birim korunur' );
		qrms_assert_same( 'kg', $malzeme[4]['birim'], 'bilinmeyen birim kg olur' );
		qrms_assert_false( isset( $malzeme[5] ), 'sıfır fiyat saklanmaz' );
		qrms_assert_false( isset( $malzeme[0] ), 'terimsiz satır düşer' );
	}
);

qrms_test(
	'ayarlar aralığa sıkıştırılır',
	function () {
		$ayar = QRMS_MM_Maliyet::ayar_temizle(
			array(
				'populerlik_esigi'  => '2,5',   // üst sınır 1.0
				'fire_yuzdesi'      => 200,     // üst sınır 50
				'varsayilan_aralik' => 45,      // listede yok -> 30
			)
		);

		qrms_assert_same( 1.0, $ayar['populerlik_esigi'], 'eşik üst sınırı' );
		qrms_assert_same( 50, $ayar['fire_yuzdesi'], 'fire üst sınırı' );
		qrms_assert_same( 30, $ayar['varsayilan_aralik'], 'bilinmeyen aralık' );

		$alt = QRMS_MM_Maliyet::ayar_temizle( array( 'populerlik_esigi' => '0,1' ) );
		qrms_assert_same( 0.5, $alt['populerlik_esigi'], 'eşik alt sınırı' );
	}
);

qrms_test(
	'maliyet yazma ve silme meta durumunu tutarlı bırakır',
	function () {
		QRMS_MM_Maliyet::maliyet_yaz( 42, '18,75' );

		qrms_assert_same( 18.75, QRMS_MM_Maliyet::maliyet( 42 ), 'maliyet yazıldı' );
		qrms_assert_same( 'manuel', QRMS_MM_Maliyet::kaynak( 42 ), 'kaynak manuel' );

		// Boş değer "sıfır maliyet" değil "girilmemiş" demektir: sıfır
		// yazılsaydı ürün rapora tam kârlı görünerek girerdi.
		QRMS_MM_Maliyet::maliyet_yaz( 42, '' );

		qrms_assert_same( null, QRMS_MM_Maliyet::maliyet( 42 ), 'meta silindi' );
	}
);

qrms_test(
	'reçete kaydı maliyeti hesaplar ve kaynağı reçeteye çevirir',
	function () {
		update_option(
			QRMS_MM_Maliyet::OPTION_MALZEME,
			array( 1 => array( 'birim' => 'kg', 'fiyat' => 200.0 ) )
		);
		update_option( QRMS_MM_Maliyet::OPTION_AYAR, array( 'fire_yuzdesi' => 0 ) );

		$maliyet = QRMS_MM_Maliyet::recete_yaz( 7, array( array( 'term_id' => 1, 'miktar' => 250 ) ) );

		qrms_assert_same( 50.0, $maliyet, '250 g × 200 ₺/kg' );
		qrms_assert_same( 50.0, QRMS_MM_Maliyet::maliyet( 7 ), 'meta yazıldı' );
		qrms_assert_same( 'recete', QRMS_MM_Maliyet::kaynak( 7 ), 'kaynak reçete' );
		qrms_assert_same( 1, count( QRMS_MM_Maliyet::recete( 7 ) ), 'reçete saklandı' );

		// Boş reçete manuel moda döndürür; alan yeniden yazılabilir olmalı.
		QRMS_MM_Maliyet::recete_yaz( 7, array() );

		qrms_assert_same( 'manuel', QRMS_MM_Maliyet::kaynak( 7 ), 'boş reçete manuele döner' );
	}
);
