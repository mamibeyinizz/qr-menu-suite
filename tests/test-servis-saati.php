<?php
/**
 * QR Restoran Menü — servis saati test edilebilirliği.
 *
 * Saat kaynağı: WordPress current_time('timestamp') (site duvar saati).
 * Testler bunu $GLOBALS['qrms_test']['now'] ile sabitler; üretim fonksiyonları
 * (servis_disi_mi, siparis_filtresi, rozet_html) aynı kararı kullanır.
 *
 * Takvim: 7 Eylül 2026 Pazartesi, 8 Eylül 2026 Salı, 4 Eylül 2026 Cuma.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-servis-saati.php';

/**
 * Site saati damgasını sabitler (WP current_time semantiği: duvar saati).
 *
 * @param int $yil    Yıl.
 * @param int $ay     Ay.
 * @param int $gun    Gün.
 * @param int $saat   Saat (0-23).
 * @param int $dakika Dakika.
 * @return int
 */
function qrms_servis_now( $yil, $ay, $gun, $saat, $dakika ) {
	$zaman = gmmktime( $saat, $dakika, 0, $ay, $gun, $yil );
	$GLOBALS['qrms_test']['now'] = $zaman;

	return $zaman;
}

/**
 * Ürüne özel servis kuralı.
 *
 * @param int    $id     Ürün ID.
 * @param int[]  $gunler ISO günler.
 * @param string $bas    Başlangıç.
 * @param string $bit    Bitiş.
 * @param string $baslik Başlık.
 * @return void
 */
function qrms_servis_urun( $id, array $gunler, $bas, $bit, $baslik = 'Kahvaltı' ) {
	$GLOBALS['qrms_test']['post_types'][ $id ] = 'rma_menu_item';
	$GLOBALS['qrms_test']['posts_by_id'][ $id ]  = (object) array(
		'ID'         => $id,
		'post_title' => $baslik,
		'post_type'  => 'rma_menu_item',
	);
	$GLOBALS['qrms_test']['post_meta'][ $id ]    = array(
		RMA_Servis_Saati::META_MOD    => 'ozel',
		RMA_Servis_Saati::META_GUNLER => $gunler,
		RMA_Servis_Saati::META_BAS    => $bas,
		RMA_Servis_Saati::META_BIT    => $bit,
	);
	RMA_Servis_Saati::sifirla();
}

echo "\nServis saati — üretim yolu ve kontrollü zaman\n";

qrms_test(
	'simdi() current_time ile aynıdır; URL parametresi saati değiştirmez',
	function () {
		$zaman = qrms_servis_now( 2026, 9, 7, 12, 0 );

		$_GET['saat'] = '03:00';
		$_GET['now']  = '1';

		qrms_assert_same( $zaman, RMA_Servis_Saati::simdi(), 'simdi = current_time' );
		qrms_assert_same( $zaman, current_time( 'timestamp' ), 'stub now' );
		qrms_assert_same( 12, (int) gmdate( 'G', RMA_Servis_Saati::simdi() ), 'duvar saati 12' );
	}
);

qrms_test(
	'HTML time saniyeli değer HH:MM olarak saklanır; geçersiz saat boşlanır',
	function () {
		qrms_assert_same( '18:00', RMA_Servis_Saati::saati_temizle( '18:00:00' ), 'saniye düşülür' );
		qrms_assert_same( '07:00', RMA_Servis_Saati::saati_temizle( ' 07:00 ' ), 'boşluk' );
		qrms_assert_same( '', RMA_Servis_Saati::saati_temizle( '24:00' ), '24:00 geçersiz' );
		qrms_assert_same( '', RMA_Servis_Saati::saati_temizle( '7:00' ), 'tek hane' );
		qrms_assert_same( '', RMA_Servis_Saati::saati_temizle( '25:99' ), 'aralık dışı' );
		qrms_assert_same( '', RMA_Servis_Saati::saati_temizle( 'gece' ), 'metin' );
		qrms_assert_same( array( 1 ), RMA_Servis_Saati::gunleri_temizle( array( 1, 1, 9, 0 ) ), 'gün süzgeci' );
	}
);

qrms_test(
	'Pazartesi 10:00–22:00: sınırlar ve aralık dışı (2026-09-07 site saati)',
	function () {
		qrms_servis_urun( 940, array( 1 ), '10:00', '22:00' );

		qrms_servis_now( 2026, 9, 7, 9, 59 );
		qrms_assert_true( RMA_Servis_Saati::servis_disi_mi( 940 ), '09:59 kapalı' );

		qrms_servis_now( 2026, 9, 7, 10, 0 );
		qrms_assert_true( ! RMA_Servis_Saati::servis_disi_mi( 940 ), '10:00 açık (başlangıç dahil)' );

		qrms_servis_now( 2026, 9, 7, 12, 0 );
		qrms_assert_true( ! RMA_Servis_Saati::servis_disi_mi( 940 ), '12:00 açık' );

		qrms_servis_now( 2026, 9, 7, 21, 59 );
		qrms_assert_true( ! RMA_Servis_Saati::servis_disi_mi( 940 ), '21:59 açık' );

		qrms_servis_now( 2026, 9, 7, 22, 0 );
		qrms_assert_true( RMA_Servis_Saati::servis_disi_mi( 940 ), '22:00 kapalı (bitiş hariç)' );
	}
);

qrms_test(
	'Salı kapalı gün: 12:00 ve 20:00 sipariş kabul edilmez (2026-09-08)',
	function () {
		qrms_servis_urun( 941, array( 1 ), '10:00', '22:00' );

		qrms_servis_now( 2026, 9, 8, 12, 0 );
		qrms_assert_true( RMA_Servis_Saati::servis_disi_mi( 941 ), 'Salı 12:00 kapalı' );

		qrms_servis_now( 2026, 9, 8, 20, 0 );
		qrms_assert_true( RMA_Servis_Saati::servis_disi_mi( 941 ), 'Salı 20:00 kapalı' );
	}
);

qrms_test(
	'18:00–02:00 gece yarısını aşar; 02:00 hariç (Cuma 4 Eyl 2026)',
	function () {
		qrms_servis_urun( 942, array( 5 ), '18:00', '02:00', 'Gece menüsü' );

		$ornekler = array(
			array( 4, 17, 59, true,  'Cuma 17:59 kapalı' ),
			array( 4, 18, 0,  false, 'Cuma 18:00 açık' ),
			array( 4, 23, 59, false, 'Cuma 23:59 açık' ),
			array( 5, 0,  0,  false, 'Cmt 00:00 açık (önceki günün pencerisi)' ),
			array( 5, 1,  59, false, 'Cmt 01:59 açık' ),
			array( 5, 2,  0,  true,  'Cmt 02:00 kapalı (bitiş hariç)' ),
			array( 5, 2,  1,  true,  'Cmt 02:01 kapalı' ),
		);

		foreach ( $ornekler as $ornek ) {
			list( $gun, $saat, $dakika, $disi, $etiket ) = $ornek;
			qrms_servis_now( 2026, 9, $gun, $saat, $dakika );
			qrms_assert_same( $disi, RMA_Servis_Saati::servis_disi_mi( 942 ), $etiket );
		}
	}
);

qrms_test(
	'00:00 başlangıç dahil; 00:00–00:00 kural sayılmaz',
	function () {
		qrms_servis_urun( 943, array( 1 ), '00:00', '10:00' );

		qrms_servis_now( 2026, 9, 7, 0, 0 );
		qrms_assert_true( ! RMA_Servis_Saati::servis_disi_mi( 943 ), 'Pzt 00:00 açık' );

		qrms_servis_now( 2026, 9, 7, 9, 59 );
		qrms_assert_true( ! RMA_Servis_Saati::servis_disi_mi( 943 ), '09:59 açık' );

		qrms_servis_now( 2026, 9, 7, 10, 0 );
		qrms_assert_true( RMA_Servis_Saati::servis_disi_mi( 943 ), '10:00 kapalı' );

		qrms_servis_urun( 944, array( 1 ), '00:00', '00:00' );
		qrms_servis_now( 2026, 9, 7, 12, 0 );
		qrms_assert_same( null, RMA_Servis_Saati::kural( 944 ), 'eşit saat = kısıt yok' );
		qrms_assert_true( ! RMA_Servis_Saati::servis_disi_mi( 944 ), '00:00–00:00 kilitlenmez' );
	}
);

qrms_test(
	'boş ve geçersiz kural ürünü kilitlemez; tek gün yeter',
	function () {
		$GLOBALS['qrms_test']['post_types'][ 945 ] = 'rma_menu_item';
		$GLOBALS['qrms_test']['post_meta'][ 945 ]  = array();
		qrms_servis_now( 2026, 9, 7, 3, 0 );
		qrms_assert_same( null, RMA_Servis_Saati::kural( 945 ), 'meta yok' );
		qrms_assert_true( ! RMA_Servis_Saati::servis_disi_mi( 945 ), 'kısıt yoksa açık' );

		qrms_servis_urun( 946, array(), '10:00', '22:00' );
		qrms_assert_same( null, RMA_Servis_Saati::kural( 946 ), 'gün yok' );

		qrms_servis_urun( 947, array( 1 ), '10:00', '' );
		qrms_assert_same( null, RMA_Servis_Saati::kural( 947 ), 'bitiş boş' );

		qrms_servis_urun( 948, array( 1 ), '', '22:00' );
		qrms_assert_same( null, RMA_Servis_Saati::kural( 948 ), 'başlangıç boş' );

		qrms_servis_urun( 949, array( 1 ), '99:99', '22:00' );
		qrms_assert_same( null, RMA_Servis_Saati::kural( 949 ), 'geçersiz saat' );

		qrms_servis_urun( 950, array( 1 ), '10:00', '22:00' );
		qrms_servis_now( 2026, 9, 7, 12, 0 );
		qrms_assert_true( ! RMA_Servis_Saati::servis_disi_mi( 950 ), 'yalnız Pazartesi 12:00 açık' );
	}
);

qrms_test(
	'kategori kuralı devralınır; ürün kapali modu kısıtı kapatır',
	function () {
		$GLOBALS['qrms_test']['post_types'][ 951 ] = 'rma_menu_item';
		$GLOBALS['qrms_test']['post_terms'][ 951 ]['rma_category'] = array(
			(object) array( 'term_id' => 77, 'name' => 'Kahvaltı' ),
		);
		$GLOBALS['qrms_test']['term_meta'][ 77 ] = array(
			RMA_Servis_Saati::TERIM_AKTIF  => '1',
			RMA_Servis_Saati::TERIM_GUNLER => array( 1 ),
			RMA_Servis_Saati::TERIM_BAS    => '10:00',
			RMA_Servis_Saati::TERIM_BIT    => '22:00',
		);

		qrms_servis_now( 2026, 9, 7, 9, 0 );
		qrms_assert_true( RMA_Servis_Saati::servis_disi_mi( 951 ), 'kategori 09:00 kapalı' );

		qrms_servis_now( 2026, 9, 7, 11, 0 );
		qrms_assert_true( ! RMA_Servis_Saati::servis_disi_mi( 951 ), 'kategori 11:00 açık' );

		$GLOBALS['qrms_test']['post_meta'][ 951 ][ RMA_Servis_Saati::META_MOD ] = 'kapali';
		RMA_Servis_Saati::sifirla();
		qrms_servis_now( 2026, 9, 7, 9, 0 );
		qrms_assert_true( ! RMA_Servis_Saati::servis_disi_mi( 951 ), 'kısıt yok modu her zaman açık' );
	}
);

qrms_test(
	'PHP varsayılan timezone servis kararını bozmaz (site current_time)',
	function () {
		qrms_servis_urun( 952, array( 1 ), '10:00', '22:00' );
		qrms_servis_now( 2026, 9, 7, 10, 0 );

		$eski = date_default_timezone_get();
		date_default_timezone_set( 'Pacific/Honolulu' );

		qrms_assert_true( ! RMA_Servis_Saati::servis_disi_mi( 952 ), 'Honolulu TZ ile 10:00 hâlâ açık' );
		qrms_assert_same( 10, (int) gmdate( 'G', RMA_Servis_Saati::simdi() ), 'gmdate site duvar saatini okur' );

		date_default_timezone_set( $eski );
	}
);

qrms_test(
	'frontend açıkken rozet yok; kapalıyken Servis dışı ve sepet niteliği bağlanır',
	function () {
		qrms_servis_urun( 953, array( 1 ), '10:00', '22:00' );

		qrms_servis_now( 2026, 9, 7, 12, 0 );
		qrms_assert_same( '', RMA_Servis_Saati::rozet_html( 953 ), 'açık: rozet yok' );
		qrms_assert_true( ! RMA_Servis_Saati::servis_disi_mi( 953 ), 'açık: sipariş serbest' );

		qrms_servis_now( 2026, 9, 7, 9, 0 );
		$html = RMA_Servis_Saati::rozet_html( 953 );
		qrms_assert_contains( 'rma-servis-rozet', $html, 'kapalı rozet' );
		qrms_assert_contains( 'Servis dışı', $html, 'etiket' );
		qrms_assert_contains( 'Bu ürün şu an servis edilmiyor', RMA_Servis_Saati::mesaj( 953 ), 'müşteri mesajı' );

		$fe = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-frontend.php' );
		$aj = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-ajax.php' );
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' );

		qrms_assert_contains( 'RMA_Servis_Saati::servis_disi_mi', $fe, 'kart üretim yolu' );
		qrms_assert_contains( 'is-servis-disi', $fe, 'kart sınıfı' );
		qrms_assert_contains( 'data-siparis-kapali="1"', $aj, 'modal kapalı niteliği' );
		qrms_assert_contains( "data-siparis-kapali", $js, 'sepet JS aynı kararı okur' );
		qrms_assert_contains( "body.getAttribute( 'data-siparis-kapali' ) === '1'", $js, 'JS ekleme engeli' );
	}
);

qrms_test(
	'backend sipariş filtresi açıkta geçer, kapalıda keser; doğrudan apply_filters aynı karar',
	function () {
		qrms_servis_urun( 954, array( 1 ), '10:00', '22:00', 'Omlet' );
		add_filter( 'qmo_siparis_onay_oncesi', array( 'RMA_Servis_Saati', 'siparis_filtresi' ), 12, 2 );

		$kalemler = array(
			array(
				'item_id' => 954,
				'urunAdi' => 'Omlet',
			),
		);

		qrms_servis_now( 2026, 9, 7, 12, 0 );
		qrms_assert_same( null, RMA_Servis_Saati::siparis_filtresi( null, $kalemler ), 'üretim filtresi 12:00 açık' );
		qrms_assert_same( null, apply_filters( 'qmo_siparis_onay_oncesi', null, $kalemler ), 'REST kancası 12:00 açık' );

		qrms_servis_now( 2026, 9, 7, 9, 0 );
		$engel = RMA_Servis_Saati::siparis_filtresi( null, $kalemler );
		qrms_assert_true( is_array( $engel ), 'kapalıda dizi engel' );
		qrms_assert_same( 954, $engel['item_id'], 'ürün id' );
		qrms_assert_same( 'service_hours', $engel['reason'], 'neden' );

		$kanca = apply_filters( 'qmo_siparis_onay_oncesi', null, $kalemler );
		qrms_assert_same( $engel['mesaj'], $kanca['mesaj'], 'doğrudan kanca aynı mesaj' );

		$rest = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/rest-order.php' );
		$menu = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' );
		qrms_assert_contains( "apply_filters( 'qmo_siparis_onay_oncesi'", $rest, 'sipariş ucu kancayı çağırır' );
		qrms_assert_contains( "[ 'RMA_Servis_Saati', 'siparis_filtresi' ]", $menu, 'modül kancayı bağlar' );
	}
);

qrms_test(
	'önceki sipariş engeli ve boş sepet korunur (regresyon)',
	function () {
		qrms_servis_urun( 955, array( 1 ), '10:00', '22:00' );
		qrms_servis_now( 2026, 9, 7, 9, 0 );

		qrms_assert_same( 'tükendi', RMA_Servis_Saati::siparis_filtresi( 'tükendi', array( array( 'item_id' => 955 ) ) ), 'önceki string engel' );
		qrms_assert_same( null, RMA_Servis_Saati::siparis_filtresi( null, array() ), 'boş sepet' );

		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' );
		qrms_assert_contains( 'qmo_sepet', $js, 'sepet anahtarı duruyor' );
	}
);

qrms_test(
	'admin arayüzü site saat dilimini ve gece yarısı kuralını anlatır; tek aralık',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-secenek-admin.php' );

		qrms_assert_contains( 'WordPress site saat dilimine', $php, 'timezone notu' );
		qrms_assert_contains( 'gece yarısını aşar', $php, 'overnight notu' );
		qrms_assert_contains( 'step="60"', $php, 'time adımı dakika' );
		qrms_assert_contains( 'Gün başına tek aralık', $php, 'çoklu aralık yok' );
		qrms_assert_false( strpos( $php, 'rma_servis_bas_2' ), 'ikinci aralık alanı yok' );
	}
);
