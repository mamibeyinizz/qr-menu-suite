<?php
/**
 * Porsiyon, ekstra, servis saati ve özel rozet testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-porsiyon.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-ekstra.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-servis-saati.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-ozel-rozet.php';

echo "\nPorsiyon — doğrulama ve gösterim\n";

qrms_test(
	'adsız satır atılır, virgüllü ve metin fiyat sayıya çevrilir',
	function () {
		$temiz = RMA_Porsiyon::temizle(
			array(
				array( 'ad' => 'Küçük', 'fark' => '-20,5' ),
				array( 'ad' => '',      'fark' => '40' ),
				array( 'ad' => 'Büyük', 'fark' => '40 ₺' ),
				array( 'ad' => 'Bozuk', 'fark' => 'fiyat sorunuz' ),
			)
		);

		qrms_assert_same( 3, count( $temiz ), 'adsız satır elendi' );
		qrms_assert_same( -20.5, $temiz[0]['fark'], 'virgüllü negatif fark' );
		qrms_assert_same( 40.0, $temiz[1]['fark'], 'para simgesi temizlendi' );
		qrms_assert_same( 0.0, $temiz[2]['fark'], 'sayıya çevrilemeyen fark sıfır' );
	}
);

qrms_test(
	'azami porsiyon sayısı aşılamaz',
	function () {
		$ham = array();
		for ( $i = 0; $i < 20; $i++ ) {
			$ham[] = array( 'ad' => 'P' . $i, 'fark' => $i );
		}

		qrms_assert_same( RMA_Porsiyon::AZAMI, count( RMA_Porsiyon::temizle( $ham ) ), 'sınır uygulanır' );
	}
);

qrms_test(
	'farkı sıfır olan porsiyon yoksa başa standart seçenek eklenir',
	function () {
		$GLOBALS['qrms_test']['post_meta'][ 900 ] = array(
			RMA_Porsiyon::META => array(
				array( 'ad' => 'Büyük', 'fark' => 40 ),
				array( 'ad' => 'Aile',  'fark' => 90 ),
			),
		);

		$liste = RMA_Porsiyon::gosterim_listesi( 900 );

		qrms_assert_same( 3, count( $liste ), 'standart eklendi' );
		qrms_assert_same( 0.0, $liste[0]['fark'], 'ilk seçenek taban fiyat' );

		// Taban fiyatlı seçenek zaten varsa liste olduğu gibi kalır.
		$GLOBALS['qrms_test']['post_meta'][ 901 ] = array(
			RMA_Porsiyon::META => array(
				array( 'ad' => 'Orta',  'fark' => 0 ),
				array( 'ad' => 'Büyük', 'fark' => 40 ),
			),
		);

		qrms_assert_same( 2, count( RMA_Porsiyon::gosterim_listesi( 901 ) ), 'kendi standardı korunur' );
	}
);

qrms_test(
	'porsiyonu olmayan üründe seçim bloğu hiç basılmaz',
	function () {
		$GLOBALS['qrms_test']['post_meta'][ 902 ] = array();

		qrms_assert_same( '', RMA_Porsiyon::html( 902 ), 'boş çıktı' );
	}
);

qrms_test(
	'seçim bloğu fiyat farkını data-fark niteliğinde SAYI olarak taşır',
	function () {
		$GLOBALS['qrms_test']['post_meta'][ 903 ] = array(
			RMA_Porsiyon::META => array(
				array( 'ad' => 'Orta',  'fark' => 0 ),
				array( 'ad' => 'Büyük', 'fark' => 40.5 ),
			),
		);

		$html = RMA_Porsiyon::html( 903 );

		qrms_assert_true( false !== strpos( $html, 'data-fark="0.00"' ), 'taban seçenek sıfır' );
		qrms_assert_true( false !== strpos( $html, 'data-fark="40.50"' ), 'nokta ayraçlı sayı' );
		qrms_assert_true( false !== strpos( $html, 'checked' ), 'ilk seçenek işaretli' );
	}
);

echo "\nEkstra — listeler ve ürün bağlantısı\n";

qrms_test(
	'ürünsüz veya adsız liste kaydedilmez, id addan türetilir ve çakışmaz',
	function () {
		$temiz = RMA_Ekstra::listeleri_temizle(
			array(
				array( 'ad' => 'Soslar', 'urunler' => array( array( 'ad' => 'Ketçap', 'fiyat' => '10' ) ) ),
				array( 'ad' => 'Soslar', 'urunler' => array( array( 'ad' => 'Mayonez', 'fiyat' => '12' ) ) ),
				array( 'ad' => 'Boş',    'urunler' => array() ),
				array( 'ad' => '',       'urunler' => array( array( 'ad' => 'X', 'fiyat' => 1 ) ) ),
			)
		);

		qrms_assert_same( 2, count( $temiz ), 'boş ve adsız listeler elendi' );
		qrms_assert_same( 'soslar', $temiz[0]['id'], 'id addan türetildi' );
		qrms_assert_same( 'soslar-2', $temiz[1]['id'], 'çakışan id numaralandı' );
	}
);

qrms_test(
	'negatif ekstra fiyatı sıfıra kelepçelenir',
	function () {
		$temiz = RMA_Ekstra::satirlari_temizle( array( array( 'ad' => 'Sos', 'fiyat' => '-5' ) ) );

		qrms_assert_same( 0.0, $temiz[0]['fiyat'], 'indirim ekstrası olmaz' );
	}
);

qrms_test(
	'ekstrası olmayan üründe açılır blok basılmaz',
	function () {
		$GLOBALS['qrms_test']['options'][ RMA_Ekstra::OPTION ] = array();
		$GLOBALS['qrms_test']['post_meta'][ 910 ]              = array();

		qrms_assert_same( '', RMA_Ekstra::html( 910 ), 'boş çıktı' );
		qrms_assert_true( ! RMA_Ekstra::var_mi( 910 ), 'ekstra yok' );
	}
);

qrms_test(
	'ürün hem hazır listeyi hem kendi manuel satırlarını gösterir',
	function () {
		$GLOBALS['qrms_test']['options'][ RMA_Ekstra::OPTION ] = array(
			array(
				'id'      => 'soslar',
				'ad'      => 'Soslar',
				'urunler' => array( array( 'ad' => 'Ketçap', 'fiyat' => 10 ) ),
			),
		);

		$GLOBALS['qrms_test']['post_meta'][ 911 ] = array(
			RMA_Ekstra::META_LISTE  => array( 'soslar', 'olmayan-liste' ),
			RMA_Ekstra::META_MANUEL => array( array( 'ad' => 'Ekstra peynir', 'fiyat' => 30 ) ),
		);

		$gruplar = RMA_Ekstra::gruplar( 911 );

		qrms_assert_same( 2, count( $gruplar ), 'silinmiş liste elendi, manuel grup eklendi' );
		qrms_assert_same( 'Soslar', $gruplar[0]['baslik'], 'liste başlığı' );
		qrms_assert_same( '', $gruplar[1]['baslik'], 'manuel grubun başlığı yok' );

		$html = RMA_Ekstra::html( 911 );

		qrms_assert_true( false !== strpos( $html, 'data-fiyat="10.00"' ), 'liste fiyatı sayı olarak' );
		qrms_assert_true( false !== strpos( $html, 'data-fiyat="30.00"' ), 'manuel fiyat sayı olarak' );
	}
);

echo "\nServis saati — pencere hesabı\n";

qrms_test(
	'gün ve saat girdileri doğrulanır',
	function () {
		qrms_assert_same( array( 1, 5 ), RMA_Servis_Saati::gunleri_temizle( array( 5, '1', 9, 0, 1 ) ), 'aralık dışı ve tekrar elendi' );
		qrms_assert_same( '07:00', RMA_Servis_Saati::saati_temizle( ' 07:00 ' ), 'boşluk kırpılır' );
		qrms_assert_same( '', RMA_Servis_Saati::saati_temizle( '24:00' ), 'geçersiz saat' );
		qrms_assert_same( '', RMA_Servis_Saati::saati_temizle( '7:00' ), 'iki hane zorunlu' );
	}
);

qrms_test(
	'kahvaltı penceresi yalnızca seçili günlerde ve saat aralığında açıktır',
	function () {
		$kural = array( 'gunler' => array( 1, 2, 3, 4, 5 ), 'bas' => '07:00', 'bit' => '11:00', 'kaynak' => 'kategori' );

		// 2 Eylül 2026 Çarşamba.
		$carsamba = gmmktime( 8, 30, 0, 9, 2, 2026 );
		$gec      = gmmktime( 11, 0, 0, 9, 2, 2026 );
		$erken    = gmmktime( 6, 59, 0, 9, 2, 2026 );
		$cumartesi = gmmktime( 8, 30, 0, 9, 5, 2026 );

		qrms_assert_true( RMA_Servis_Saati::pencerede_mi( $kural, $carsamba ), 'hafta içi 08:30 açık' );
		qrms_assert_true( ! RMA_Servis_Saati::pencerede_mi( $kural, $gec ), 'bitiş saati dışarıda' );
		qrms_assert_true( ! RMA_Servis_Saati::pencerede_mi( $kural, $erken ), 'başlangıçtan önce kapalı' );
		qrms_assert_true( ! RMA_Servis_Saati::pencerede_mi( $kural, $cumartesi ), 'hafta sonu kapalı' );
	}
);

qrms_test(
	'gece yarısını aşan pencere ertesi günün sabahını da kapsar',
	function () {
		// Cuma 22:00 – 02:00.
		$kural = array( 'gunler' => array( 5 ), 'bas' => '22:00', 'bit' => '02:00', 'kaynak' => 'urun' );

		$cuma_gece    = gmmktime( 23, 0, 0, 9, 4, 2026 );  // Cuma
		$cmt_sabah    = gmmktime( 1, 0, 0, 9, 5, 2026 );   // Cumartesi 01:00
		$cmt_gunduz   = gmmktime( 12, 0, 0, 9, 5, 2026 );  // Cumartesi öğlen
		$cuma_ogleden = gmmktime( 15, 0, 0, 9, 4, 2026 );

		qrms_assert_true( RMA_Servis_Saati::pencerede_mi( $kural, $cuma_gece ), 'başlangıç günü gecesi açık' );
		qrms_assert_true( RMA_Servis_Saati::pencerede_mi( $kural, $cmt_sabah ), 'ertesi sabah hâlâ açık' );
		qrms_assert_true( ! RMA_Servis_Saati::pencerede_mi( $kural, $cmt_gunduz ), 'ertesi öğlen kapalı' );
		qrms_assert_true( ! RMA_Servis_Saati::pencerede_mi( $kural, $cuma_ogleden ), 'başlangıçtan önce kapalı' );
	}
);

qrms_test(
	'gün listesi okunabilir metne çevrilir',
	function () {
		qrms_assert_same( 'Hafta içi', RMA_Servis_Saati::gun_metni( array( 1, 2, 3, 4, 5 ) ), 'hafta içi' );
		qrms_assert_same( 'Hafta sonu', RMA_Servis_Saati::gun_metni( array( 6, 7 ) ), 'hafta sonu' );
		qrms_assert_same( 'Her gün', RMA_Servis_Saati::gun_metni( array( 1, 2, 3, 4, 5, 6, 7 ) ), 'her gün' );
		qrms_assert_same( 'Pazartesi, Cuma', RMA_Servis_Saati::gun_metni( array( 1, 5 ) ), 'tekil günler' );
	}
);

qrms_test(
	'ürün modu kapalıysa kategori kuralı devralınmaz',
	function () {
		$GLOBALS['qrms_test']['post_meta'][ 920 ] = array(
			RMA_Servis_Saati::META_MOD => 'kapali',
		);

		qrms_assert_same( null, RMA_Servis_Saati::kural( 920 ), 'kısıt yok' );
		qrms_assert_true( ! RMA_Servis_Saati::servis_disi_mi( 920 ), 'her zaman servis edilir' );
	}
);

qrms_test(
	'eksik kural (saat veya gün yok) yok sayılır — ürün kilitlenmez',
	function () {
		$GLOBALS['qrms_test']['post_meta'][ 921 ] = array(
			RMA_Servis_Saati::META_MOD    => 'ozel',
			RMA_Servis_Saati::META_GUNLER => array( 1, 2 ),
			RMA_Servis_Saati::META_BAS    => '07:00',
			RMA_Servis_Saati::META_BIT    => '',
		);

		qrms_assert_same( null, RMA_Servis_Saati::kural( 921 ), 'yarım kural uygulanmaz' );
	}
);

echo "\nÖzel rozet — tanım ve ürün seçimi\n";

qrms_test(
	'rozet tanımı adsızsa atılır, slug çakışması numaralanır, renk doğrulanır',
	function () {
		$temiz = RMA_Ozel_Rozet::temizle(
			array(
				array( 'ad' => 'Hızlı Servis', 'ikon' => '⚡', 'renk' => '#ff0000' ),
				array( 'ad' => 'Hızlı Servis', 'ikon' => '',   'renk' => 'kirmizi' ),
				array( 'ad' => '',             'ikon' => '🔥', 'renk' => '#00ff00' ),
			)
		);

		qrms_assert_same( 2, count( $temiz ), 'adsız tanım elendi' );
		qrms_assert_same( '#ff0000', $temiz[0]['renk'], 'geçerli hex korunur' );
		qrms_assert_same( RMA_Ozel_Rozet::RENK, $temiz[1]['renk'], 'geçersiz renk varsayılana döner' );
		qrms_assert_same( 'hizli-servis-2', $temiz[1]['slug'], 'çakışan slug numaralandı' );
	}
);

qrms_test(
	'üründe tanımı silinmiş rozet gösterilmez',
	function () {
		$GLOBALS['qrms_test']['options'][ RMA_Ozel_Rozet::OPTION ] = array(
			array( 'slug' => 'aci', 'ad' => 'Acı', 'ikon' => '🌶️', 'renk' => '#e74c3c' ),
		);

		$GLOBALS['qrms_test']['post_meta'][ 930 ] = array(
			RMA_Ozel_Rozet::META => array( 'aci', 'silinmis-rozet' ),
		);

		$rozetler = RMA_Ozel_Rozet::urun_rozetleri( 930 );

		qrms_assert_same( 1, count( $rozetler ), 'yalnızca tanımlı rozet' );

		$html = RMA_Ozel_Rozet::rozet_html( 930 );

		qrms_assert_true( false !== strpos( $html, '--rma-rozet-renk:#e74c3c' ), 'renk CSS değişkenine yazıldı' );
		qrms_assert_true( false !== strpos( $html, 'Acı' ), 'rozet adı basıldı' );
	}
);

echo "\nSepet köprüsü — porsiyonlu ada göre engel\n";

qrms_test(
	'tükendi filtresi porsiyon ekli adı ve ürün ID sini tanır',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-tukendi.php' );

		qrms_assert_true( false !== strpos( $kaynak, "\$kalem['item_id']" ), 'önce ürün ID sorulur' );
		qrms_assert_true( false !== strpos( $kaynak, '\([^()]*\)\s*$' ), 'porsiyon eki ada göre eşleşmede kırpılır' );
	}
);

qrms_test(
	'sepet betiği fiyatı data niteliklerinden okur, seçimi ada ve nota taşır',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' );

		qrms_assert_true( false !== strpos( $js, "getAttribute( 'data-fiyat' )" ), 'taban fiyat data-fiyat' );
		qrms_assert_true( false !== strpos( $js, "getAttribute( 'data-fark' )" ), 'porsiyon farkı data-fark' );
		qrms_assert_true( false !== strpos( $js, "data-siparis-kapali" ), 'servis dışı/tükendi ürüne ekleme bloğu basılmaz' );
		qrms_assert_true( false !== strpos( $js, 'imzaUret' ), 'porsiyon+ekstra kombinasyonu ayrı satır' );
	}
);

qrms_test(
	'servis saati sipariş ucunda da kesilir',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' );

		qrms_assert_true(
			false !== strpos( $kaynak, "add_filter( 'qmo_siparis_onay_oncesi', [ 'RMA_Servis_Saati', 'siparis_filtresi' ]" ),
			'sunucu tarafı engel kancası kurulu'
		);
	}
);
