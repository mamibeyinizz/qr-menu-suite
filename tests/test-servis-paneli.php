<?php
/**
 * Servis Paneli testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/class-qmo-firestore.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-servis-paneli/includes/class-qrms-sp-veri.php';

echo "\nServis Paneli\n";

qrms_test(
	'Firestore belgesi düz diziye çözülür',
	function () {
		$belge = array(
			'name'   => 'projects/p/databases/(default)/documents/calls/ABC123',
			'fields' => array(
				'branchId'  => array( 'stringValue' => 'sube-1' ),
				'masaNo'    => array( 'stringValue' => 'masa-4' ),
				'tip'       => array( 'stringValue' => 'siparis' ),
				'durum'     => array( 'stringValue' => 'bekliyor' ),
				'createdAt' => array( 'timestampValue' => '2026-09-04T10:00:00Z' ),
				'items'     => array(
					'arrayValue' => array(
						'values' => array(
							array(
								'mapValue' => array(
									'fields' => array(
										'urunAdi' => array( 'stringValue' => 'Lahmacun' ),
										'adet'    => array( 'integerValue' => '3' ),
										'notTr'   => array( 'stringValue' => 'Az acılı' ),
										'ceviri_hata' => array( 'booleanValue' => false ),
									),
								),
							),
						),
					),
				),
			),
		);

		$coz = QMO_Firestore::belge_coz( $belge );

		qrms_assert_same( 'ABC123', $coz['id'], 'kimlik yolun son parçası' );
		qrms_assert_same( 'sube-1', $coz['branchId'], 'stringValue' );
		qrms_assert_same( 3, $coz['items'][0]['adet'], 'integerValue tamsayı olur' );
		qrms_assert_same( 'Lahmacun', $coz['items'][0]['urunAdi'], 'mapValue çözülür' );
		qrms_assert_same( false, $coz['items'][0]['ceviri_hata'], 'booleanValue' );

		// Sarmalayıcısı olmayan/bilinmeyen değer boş metne düşer, hata atmaz.
		qrms_assert_same( '', QMO_Firestore::deger_coz( array( 'uydurmaValue' => 1 ) ), 'bilinmeyen tip' );
		qrms_assert_same( null, QMO_Firestore::deger_coz( array( 'nullValue' => null ) ), 'nullValue' );
	}
);

qrms_test(
	'panel kaydı normalize edilir, eksik alanlar varsayılana düşer',
	function () {
		$kayit = QRMS_SP_Veri::normalize(
			array(
				'id'     => 'X1',
				'tip'    => 'siparis',
				'masaNo' => 'masa-4',
				'items'  => array(
					array( 'urunAdi' => 'Ayran', 'adet' => 2, 'notOrijinal' => 'no ice', 'notTr' => 'Buzsuz' ),
					array( 'urunAdi' => 'Su' ),
				),
				'createdAt' => '2026-09-04T10:00:00Z',
			),
			array( 'masa-4' => 'Bahçe 4' )
		);

		qrms_assert_same( 'X1', $kayit['id'], 'kimlik' );
		qrms_assert_same( 'Bahçe 4', $kayit['masaAd'], 'masa adı çözülür' );
		qrms_assert_same( 'bekliyor', $kayit['durum'], 'durum yoksa bekliyor' );
		qrms_assert_same( 2, count( $kayit['kalemler'] ), 'kalem sayısı' );
		qrms_assert_same( 1, $kayit['kalemler'][1]['adet'], 'adet yoksa 1' );
		qrms_assert_same( 'Buzsuz', $kayit['kalemler'][0]['notTr'], 'çeviri notu' );
		qrms_assert_true( $kayit['olusmaTs'] > 0, 'zaman damgası çözüldü' );

		// Masa adı bilinmiyorsa slug gösterilir; panel yine çalışır.
		$slugla = QRMS_SP_Veri::normalize(
			array( 'id' => 'X2', 'tip' => 'garson', 'masaNo' => 'masa-9' ),
			array()
		);

		qrms_assert_same( 'masa-9', $slugla['masaAd'], 'ad yoksa slug' );
	}
);

qrms_test(
	'geçersiz belge yok sayılır',
	function () {
		qrms_assert_same( null, QRMS_SP_Veri::normalize( array( 'tip' => 'siparis' ) ), 'kimliksiz' );
		qrms_assert_same( null, QRMS_SP_Veri::normalize( array( 'id' => 'A', 'tip' => 'uydurma' ) ), 'bilinmeyen tip' );

		// Bilinmeyen durum "bekliyor"a düşer: kayıt kaybolmaktansa ilk
		// sütunda görünsün, personel gözden kaçırmasın.
		$kayit = QRMS_SP_Veri::normalize( array( 'id' => 'A', 'tip' => 'hesap', 'durum' => 'uydurma' ) );
		qrms_assert_same( 'bekliyor', $kayit['durum'], 'bilinmeyen durum' );
	}
);

qrms_test(
	'durum akışı geçersiz sıçramaları reddeder',
	function () {
		qrms_assert_true( QRMS_SP_Veri::gecis_gecerli( 'bekliyor', 'hazirlaniyor' ), 'ileri' );
		qrms_assert_true( QRMS_SP_Veri::gecis_gecerli( 'hazirlaniyor', 'bekliyor' ), 'geri' );
		qrms_assert_true( QRMS_SP_Veri::gecis_gecerli( 'serviste', 'tamamlandi' ), 'tamamla' );
		qrms_assert_true( QRMS_SP_Veri::gecis_gecerli( 'bekliyor', 'iptal' ), 'iptal' );

		// Sıçrama reddedilir: iki panel aynı anda tıklandığında sipariş
		// hazırlanmadan tamamlanmış görünmesin.
		qrms_assert_false( QRMS_SP_Veri::gecis_gecerli( 'bekliyor', 'tamamlandi' ), 'sıçrama' );
		qrms_assert_false( QRMS_SP_Veri::gecis_gecerli( 'tamamlandi', 'bekliyor' ), 'kapalı durumdan çıkış yok' );
		qrms_assert_false( QRMS_SP_Veri::gecis_gecerli( 'iptal', 'bekliyor' ), 'iptalden dönüş yok' );
		qrms_assert_false( QRMS_SP_Veri::gecis_gecerli( 'uydurma', 'bekliyor' ), 'bilinmeyen durum' );
	}
);

qrms_test(
	'ayarlar temizlenir; kırmızı eşik sarının altına düşemez',
	function () {
		$ayar = QRMS_SP_Veri::ayar_temizle(
			array(
				'esik_sari'       => 600,
				'esik_kirmizi'    => 120,   // sarıdan küçük -> düzeltilir
				'yenileme'        => 1,     // alt sınır 3
				'tamam_penceresi' => 99,    // üst sınır 24
				'tipler'          => array( 'siparis', 'uydurma' ),
			)
		);

		// Kırmızı sarının altında kalsaydı kart doğrudan kırmızıya atlar,
		// sarı uyarı hiç görünmezdi.
		qrms_assert_same( 660, $ayar['esik_kirmizi'], 'kırmızı sarının üstüne çekilir' );
		qrms_assert_same( 3, $ayar['yenileme'], 'yenileme alt sınırı' );
		qrms_assert_same( 24, $ayar['tamam_penceresi'], 'pencere üst sınırı' );
		qrms_assert_same( array( 'siparis' ), $ayar['tipler'], 'bilinmeyen tip düşer' );

		// Hiç tip seçilmezse panel boş kalırdı; hepsine dönülür.
		$bos = QRMS_SP_Veri::ayar_temizle( array( 'tipler' => array() ) );
		qrms_assert_same( 3, count( $bos['tipler'] ), 'boş seçim hepsine döner' );
	}
);

qrms_test(
	'Firebase yapılandırılmamışken okuma ve yazma WP_Error döner',
	function () {
		// Service account yok: panel ölümcül hata vermemeli, açıklayıcı bir
		// hata dönmeli ki ekran kullanıcıya ne yapacağını söyleyebilsin.
		qrms_assert_false( QRMS_SP_Veri::hazir_mi(), 'yapılandırma yok' );
		qrms_assert_true( is_wp_error( QRMS_SP_Veri::kayitlar() ), 'okuma hatası' );
		qrms_assert_true(
			is_wp_error( QRMS_SP_Veri::durum_degistir( 'A1', 'bekliyor', 'hazirlaniyor' ) ),
			'yazma hatası'
		);

		// Geçersiz geçiş Firestore'a HİÇ gitmez; önce akış doğrulanır.
		$sonuc = QRMS_SP_Veri::durum_degistir( 'A1', 'bekliyor', 'tamamlandi' );
		qrms_assert_same( 'gecis', $sonuc->get_error_code(), 'akış önce doğrulanır' );
	}
);

qrms_test(
	'durum ve tip listeleri panelin beklediği sırada',
	function () {
		qrms_assert_same(
			array( 'bekliyor', 'hazirlaniyor', 'serviste', 'tamamlandi' ),
			array_keys( QRMS_SP_Veri::durumlar() ),
			'sütun sırası'
		);

		qrms_assert_same(
			array( 'siparis', 'garson', 'hesap' ),
			array_keys( QRMS_SP_Veri::tipler() ),
			'tipler'
		);

		// Akış tablosunda her durumun karşılığı olmalı; olmayan bir durum
		// kartı düğmesiz bırakırdı.
		foreach ( array_keys( QRMS_SP_Veri::durumlar() ) as $durum ) {
			qrms_assert_true( isset( QRMS_SP_Veri::akis()[ $durum ] ), $durum . ' akışta var' );
		}
	}
);
