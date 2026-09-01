<?php
/**
 * Güvenlik ayar kaydı ve QR Masa testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/class-qmo-oturum.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-masa-oturum-guvenligi/oturum-ayarlari.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-masa-oturum-guvenligi/masa-dogrulama.php';

echo "\nGüvenlik Ayarı — ayar kaydı\n";

qrms_test(
	'ekrandaki HER form, register_setting ile kaydedilmiş bir gruba gönderir',
	function () {
		// Bu testin varlık sebebi gerçek bir hatadır: sayfa kilidi formu
		// 'qmo_sayfa_grup' grubuna gönderiyordu ama o grup hiçbir yerde
		// register_setting() ile kaydedilmemişti. WordPress gönderimi
		// "seçenekler sayfası bulunamadı" diyerek reddediyor, ayar sessizce
		// hiç yazılmıyor ve sayfa kilidi hiçbir zaman devreye girmiyordu.
		qmo_oturum_ayarlarini_kaydet();

		ob_start();
		qmo_oturum_ayar_sayfasi();
		ob_end_clean();

		$kayitli = array_keys( $GLOBALS['qrms_test']['settings'] );
		$formlar = $GLOBALS['qrms_test']['settings_fields'];

		qrms_assert_true( count( $formlar ) >= 2, 'ekranda en az iki ayar formu var' );

		foreach ( $formlar as $grup ) {
			qrms_assert_true(
				in_array( $grup, $kayitli, true ),
				$grup . ' grubu register_setting ile kaydedilmiş'
			);
		}
	}
);

qrms_test(
	'sayfa kilidi option\'ı kendi grubuyla ve temizleyicisiyle kaydedilir',
	function () {
		qmo_oturum_ayarlarini_kaydet();

		$kayitli = $GLOBALS['qrms_test']['settings'];

		qrms_assert_true(
			isset( $kayitli['qmo_sayfa_grup']['qmo_korumali_sayfalar'] ),
			'sayfa kilidi option\'ı kayıtlı'
		);
		qrms_assert_same(
			'qmo_korumali_sayfalar_temizle',
			$kayitli['qmo_sayfa_grup']['qmo_korumali_sayfalar']['sanitize_callback'],
			'temizleyici bağlı'
		);

		// Oturum limitleri ayrı grupta kalır; iki form birbirini ezmemeli.
		qrms_assert_true(
			isset( $kayitli['qr_masa_grup'][ QMO_Oturum::OPT ] ),
			'oturum limitleri kendi grubunda'
		);
	}
);

qrms_test(
	'slug listesi temizlenir: boşluk, büyük harf, Türkçe karakter, tekrar',
	function () {
		qrms_assert_same(
			'menu,menu-tr',
			qmo_korumali_sayfalar_temizle( ' Menu , Menu-TR ' ),
			'boşluk kırpılır, küçük harfe iner'
		);
		qrms_assert_same(
			'bahce-kat',
			qmo_korumali_sayfalar_temizle( 'Bahçe Kat' ),
			'Türkçe harfler indirgenir'
		);
		qrms_assert_same(
			'menu',
			qmo_korumali_sayfalar_temizle( 'menu,menu,,menu' ),
			'tekrarlar ve boşlar atılır'
		);
		qrms_assert_same( '', qmo_korumali_sayfalar_temizle( '' ), 'boş girdi boş kalır' );
	}
);

qrms_test(
	'kaydedilen değer, kilidi okuyan taraf tarafından aynen çözülür',
	function () {
		// Asıl güvence: yazma tarafının ürettiği metin, okuma tarafının
		// (qmo_korumali_sluglar) beklediği biçimle birebir uyuşmalı.
		$kaydedilen = qmo_korumali_sayfalar_temizle( 'Menu, Bahçe Kat' );
		update_option( 'qmo_korumali_sayfalar', $kaydedilen );

		qrms_assert_same(
			array( 'menu', 'bahce-kat' ),
			array_values( qmo_korumali_sluglar() ),
			'okuma tarafı iki slug görür'
		);
	}
);

qrms_test(
	'ayar hiç kaydedilmemişken sayfa kilidi kapalıdır',
	function () {
		qrms_assert_same( array(), array_values( qmo_korumali_sluglar() ), 'boş liste' );
	}
);

/* ---------------------------------------------------------------------------
 * 9b. QR Masa — toplu oluşturma ve grup filtresi
 * ------------------------------------------------------------------------ */

// Sınıf dosya kapsamında yalnızca tanım ve bir add_shortcode kaydı yapar;
// sayfa dosyası da yalnızca fonksiyon tanımlar. Test edilenler $wpdb'ye
// dokunmayan saf dönüşümler ve DB'ye hiç gitmeyen doğrulama dalları.
require_once QRMS_PLUGIN_DIR . 'modules/qr-masa/class-qmo-masalar.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-masa/masalar-sayfasi.php';

echo "\nQR Masa toplu oluşturma\n";

qrms_test(
	'toplu slug önek ve numaradan üretilir',
	function () {
		qrms_assert_same( 'ic-masa-1', QMO_Masalar::toplu_slug( 'ic-masa', 1 ), 'düz önek' );
		qrms_assert_same( 'ic-masa-10', QMO_Masalar::toplu_slug( 'Ic Masa', 10 ), 'önek slug\'lanır' );
		qrms_assert_same( 'bahce-3', QMO_Masalar::toplu_slug( 'Bahçe', 3 ), 'Türkçe harfler indirgenir' );
	}
);

qrms_test(
	'geçersiz önek veya numara boş slug döndürür',
	function () {
		qrms_assert_same( '', QMO_Masalar::toplu_slug( '', 5 ), 'boş önek' );
		qrms_assert_same( '', QMO_Masalar::toplu_slug( '///', 5 ), 'slug\'a dönüşmeyen önek' );
		qrms_assert_same( '', QMO_Masalar::toplu_slug( 'ic-masa', 0 ), 'sıfır numara' );
	}
);

qrms_test(
	'görünen ad okunabilirdir AMA ürettiği slug her zaman beklenendir',
	function () {
		// Kritik değişmez: ekle() slug'ı ADDAN üretir. Ad ile beklenen slug
		// birbirinden kayarsa masa yanlış adreste açılır ve QR kodları tutmaz.
		$ornekler = array( 'ic-masa', 'Ic Masa', 'Bahçe', 'vip', 'VIP Salon', 'teras-ust' );

		foreach ( $ornekler as $onek ) {
			foreach ( array( 1, 7, 42 ) as $no ) {
				qrms_assert_same(
					QMO_Masalar::toplu_slug( $onek, $no ),
					sanitize_title( QMO_Masalar::toplu_ad( $onek, $no ) ),
					$onek . '-' . $no . ' adı doğru slug\'ı üretir'
				);
			}
		}

		qrms_assert_same( 'Ic Masa 7', QMO_Masalar::toplu_ad( 'ic-masa', 7 ), 'okunabilir ad' );
		qrms_assert_same( '', QMO_Masalar::toplu_ad( '', 7 ), 'geçersiz önek' );
	}
);

qrms_test(
	'toplu ekleme aralığı doğrulanır',
	function () {
		$hatali = array(
			array( '', 1, 10, 'onek' ),
			array( 'ic-masa', 0, 10, 'aralik' ),
			array( 'ic-masa', 5, 3, 'aralik' ),
			array( 'ic-masa', 1, 1000, 'sinir' ),
		);

		foreach ( $hatali as $durum ) {
			$sonuc = QMO_Masalar::toplu_aralik_dogrula( $durum[0], $durum[1], $durum[2] );

			qrms_assert_true( is_wp_error( $sonuc ), $durum[3] . ' hatası döner' );
			qrms_assert_same( $durum[3], $sonuc->get_error_code(), $durum[3] . ' kodu' );
		}
	}
);

qrms_test(
	'azami sınır tam sınırda geçer',
	function () {
		// 1..200 = 200 masa: sınırın kendisi reddedilmemeli.
		$tam = QMO_Masalar::toplu_aralik_dogrula( 'ic-masa', 1, QMO_Masalar::TOPLU_AZAMI );

		qrms_assert_false( is_wp_error( $tam ), 'tam sınır kabul edilir' );
		qrms_assert_same( QMO_Masalar::TOPLU_AZAMI, $tam['adet'], 'adet' );
		qrms_assert_same( 'ic-masa', $tam['onek'], 'önek normalize edilir' );

		qrms_assert_true(
			is_wp_error( QMO_Masalar::toplu_aralik_dogrula( 'ic-masa', 1, QMO_Masalar::TOPLU_AZAMI + 1 ) ),
			'sınırın bir fazlası reddedilir'
		);
	}
);

echo "\nQR Masa grup filtresi\n";

qrms_test(
	'grup adı sondaki numarayı atar',
	function () {
		qrms_assert_same( 'ic-masa', QMO_Masalar::grup_adi( 'ic-masa-12' ), 'çok haneli numara' );
		qrms_assert_same( 'vip', QMO_Masalar::grup_adi( 'vip-3' ), 'tek haneli numara' );
		qrms_assert_same( 'bahce', QMO_Masalar::grup_adi( 'bahce' ), 'numarasız slug' );
		qrms_assert_same( 'masa-2-kat', QMO_Masalar::grup_adi( 'masa-2-kat' ), 'ortadaki numara korunur' );
		qrms_assert_same( '12', QMO_Masalar::grup_adi( '12' ), 'tamamı sayı olan slug kendi grubudur' );
	}
);

qrms_test(
	'gruplar doğal sırayla ve sayılarıyla çıkarılır',
	function () {
		$masalar = array();

		foreach ( array( 'ic-masa-2', 'ic-masa-10', 'vip-1', 'bahce', 'ic-masa-1' ) as $slug ) {
			$masalar[] = (object) array( 'table_slug' => $slug );
		}

		qrms_assert_same(
			array(
				'bahce'   => 1,
				'ic-masa' => 3,
				'vip'     => 1,
			),
			qmo_masalar_gruplari( $masalar ),
			'grup => adet'
		);
	}
);

qrms_test(
	'slug\'ı olmayan satır grupları bozmaz',
	function () {
		$masalar = array( (object) array( 'table_slug' => 'vip-1' ), (object) array( 'id' => 3 ) );

		qrms_assert_same( array( 'vip' => 1 ), qmo_masalar_gruplari( $masalar ), 'eksik satır atlanır' );
		qrms_assert_same( array(), qmo_masalar_gruplari( array() ), 'boş liste' );
	}
);

echo "\nQR Masa toplu sonuç bildirimi\n";

qrms_test(
	'sonuç mesajı eklenen, atlanan ve hatalıyı ayrı ayrı söyler',
	function () {
		$mesaj = qmo_toplu_sonuc_mesaji(
			array(
				'eklenen' => 8,
				'atlanan' => array( 'ic-masa-3', 'ic-masa-7' ),
				'hata'    => array(),
			)
		);

		qrms_assert_contains( '8 masa oluşturuldu', $mesaj, 'eklenen sayısı' );
		qrms_assert_contains( '2 tanesi zaten vardı', $mesaj, 'atlanan sayısı' );
		qrms_assert_contains( 'ic-masa-3, ic-masa-7', $mesaj, 'atlanan slug\'ları' );
		qrms_assert_false( false !== strpos( $mesaj, 'kaydedilemedi' ), 'hata yokken hata cümlesi yok' );
	}
);

qrms_test(
	'hiç masa açılmadıysa bunu açıkça söyler',
	function () {
		$mesaj = qmo_toplu_sonuc_mesaji(
			array(
				'eklenen' => 0,
				'atlanan' => array( 'vip-1' ),
				'hata'    => array( 'vip-2' ),
			)
		);

		qrms_assert_contains( 'Hiç yeni masa oluşturulmadı', $mesaj, 'sıfır durumu' );
		qrms_assert_contains( '1 tanesi kaydedilemedi: vip-2', $mesaj, 'hata satırı' );
	}
);

qrms_test(
	'uzun slug listesi kısaltılır',
	function () {
		$sluglar = array( 'a-1', 'a-2', 'a-3', 'a-4', 'a-5', 'a-6', 'a-7' );

		qrms_assert_same(
			'a-1, a-2, a-3, a-4, a-5 ve 2 tane daha',
			qmo_slug_listesi( $sluglar ),
			'ilk beş + özet'
		);
		qrms_assert_same( 'a-1, a-2', qmo_slug_listesi( array( 'a-1', 'a-2' ) ), 'kısa liste olduğu gibi' );
	}
);

/* ---------------------------------------------------------------------------
 * 10. Yorum & Feedback — sayfa kayıt defteri, eski adresler, menü sıralaması
 * ------------------------------------------------------------------------ */

// menu.php sayfa tanımlarını ve adres yardımcılarını içerir; dosya kapsamında
// yalnızca bir add_action kaydı yapar (stub ortamında yan etkisizdir).
// module.php ise sıralama yardımcısını tanımlar — saf dizi dönüşümü.
