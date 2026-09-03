<?php
/**
 * Menü mühendisliği testleri.
 *
 * @package QR_Menu_Suite
 */

echo "\nMenü Mühendisliği\n";

require_once QRMS_PLUGIN_DIR . 'modules/qr-menu-muhendisligi/includes/class-qrms-mm-hesap.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-menu-muhendisligi/includes/class-qrms-mm-maliyet.php';

qrms_test(
	'kutu dağılımı sınırda eşitlikte yüksek tarafa düşer',
	function () {
		$urunler = array(
			array( 'id' => 1, 'ad' => 'A', 'kategori' => 'Ana', 'fiyat' => 100, 'maliyet' => 60 ),
			array( 'id' => 2, 'ad' => 'B', 'kategori' => 'Ana', 'fiyat' => 100, 'maliyet' => 40 ),
		);
		$analitik = array(
			array( 'item_id' => 1, 'satis' => 10, 'sepet' => 0, 'tik' => 0 ),
			array( 'item_id' => 2, 'satis' => 10, 'sepet' => 0, 'tik' => 0 ),
		);
		$sonuc = QRMS_MM_Hesap::hesapla( '2026-01-01', '2026-12-31', '', $urunler, $analitik, array( 'populerlik_esigi' => 1.0 ) );
		qrms_assert_same( 'siparis', $sonuc['kaynak'], 'kaynak siparis' );
		qrms_assert_same( QRMS_MM_Hesap::KUTU_YILDIZ, $sonuc['urunler'][0]['kutu'], 'yüksek CM yıldız' );
		qrms_assert_same( QRMS_MM_Hesap::KUTU_IS_ATI, $sonuc['urunler'][1]['kutu'], 'düşük CM iş atı' );
	}
);

qrms_test(
	'fiyatı veya maliyeti eksik ürün matrise girmez',
	function () {
		$urunler = array(
			array( 'id' => 1, 'ad' => 'Tam', 'kategori' => '', 'fiyat' => 50, 'maliyet' => 20 ),
			array( 'id' => 2, 'ad' => 'Eksik', 'kategori' => '', 'fiyat' => 50, 'maliyet' => 0 ),
		);
		$analitik = array(
			array( 'item_id' => 1, 'satis' => 5, 'sepet' => 0, 'tik' => 0 ),
			array( 'item_id' => 2, 'satis' => 5, 'sepet' => 0, 'tik' => 0 ),
		);
		$sonuc = QRMS_MM_Hesap::hesapla( '2026-01-01', '2026-12-31', '', $urunler, $analitik );
		qrms_assert_same( 1, count( $sonuc['urunler'] ), 'tek ürün matriste' );
		qrms_assert_same( 1, count( $sonuc['eksik'] ), 'eksik listede' );
	}
);

qrms_test(
	'satış < 20 iken vekil skor kullanılır',
	function () {
		$urunler = array(
			array( 'id' => 1, 'ad' => 'A', 'kategori' => '', 'fiyat' => 100, 'maliyet' => 30 ),
		);
		$analitik = array(
			array( 'item_id' => 1, 'satis' => 5, 'sepet' => 2, 'tik' => 10 ),
		);
		$sonuc = QRMS_MM_Hesap::hesapla( '2026-01-01', '2026-12-31', '', $urunler, $analitik );
		qrms_assert_same( 'vekil', $sonuc['kaynak'], 'vekil kaynak' );
		qrms_assert_true( '' !== $sonuc['uyari'], 'uyarı şeridi' );
		qrms_assert_same( 16, $sonuc['urunler'][0]['q'], 'vekil skor tik+sepet*3' );
	}
);

qrms_test(
	'ağırlıklı ortalama katkı payı qty ile hesaplanır',
	function () {
		$urunler = array(
			array( 'id' => 1, 'ad' => 'A', 'kategori' => '', 'fiyat' => 100, 'maliyet' => 40 ),
			array( 'id' => 2, 'ad' => 'B', 'kategori' => '', 'fiyat' => 100, 'maliyet' => 40 ),
		);
		$analitik = array(
			array( 'item_id' => 1, 'satis' => 30, 'sepet' => 0, 'tik' => 0 ),
			array( 'item_id' => 2, 'satis' => 10, 'sepet' => 0, 'tik' => 0 ),
		);
		$sonuc = QRMS_MM_Hesap::hesapla( '2026-01-01', '2026-12-31', '', $urunler, $analitik );
		$toplam_q = 40;
		$beklenen = ( ( 60 * 30 ) + ( 60 * 10 ) ) / $toplam_q;
		qrms_assert_same( 60.0, $sonuc['urunler'][0]['cm'], 'CM A' );
		qrms_assert_true( abs( $beklenen - 60 ) < 0.01, 'ağırlıklı ortalama CM' );
	}
);

qrms_test(
	'reçeteden maliyet birim çevrimi ve fire doğru',
	function () {
		update_option(
			QRMS_MM_Maliyet::OPT_MALZEME_FIYAT,
			array(
				10 => array( 'birim' => 'kg', 'fiyat' => 100 ),
				20 => array( 'birim' => 'lt', 'fiyat' => 50 ),
				30 => array( 'birim' => 'adet', 'fiyat' => 5 ),
			)
		);
		update_option( QRMS_MM_Maliyet::OPT_AYARLAR, array( 'fire_yuzdesi' => 10 ) );

		$recete = array(
			array( 'term_id' => 10, 'miktar' => 500 ),
			array( 'term_id' => 20, 'miktar' => 1000 ),
			array( 'term_id' => 30, 'miktar' => 2 ),
		);
		$ham = ( 500 / 1000 * 100 ) + ( 1000 / 1000 * 50 ) + ( 2 * 5 );
		$beklenen = round( $ham * 1.1, 2 );
		qrms_assert_same( $beklenen, QRMS_MM_Maliyet::receteden_hesapla( $recete ), 'reçete maliyeti' );
	}
);

qrms_test(
	'modül slug kayıtlı',
	function () {
		qrms_assert_true( QRMS_Helpers::is_valid_module( 'qr-menu-muhendisligi' ), 'slug geçerli' );
		qrms_assert_contains( 'dashicons-chart-pie', QRMS_Helpers::get_module_icon( 'qr-menu-muhendisligi' ), 'ikon' );
	}
);
