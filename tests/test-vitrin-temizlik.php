<?php
/**
 * Ürün Vitrini kalıntı temizliği ve slider hizalama normalizasyonu testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

echo "\n\033[1mVitrin temizliği — migration ve hizalama\033[0m\n";

require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-vitrin-temizlik.php';

/**
 * `query()` dönüş değeri dıştan ayarlanabilen $wpdb taklidi.
 */
class QRMS_Vitrin_Wpdb {
	public $prefix        = 'wp_';
	public $queries       = array();
	public $query_sonuclari = array();

	/**
	 * @param string $sql Çalıştırılan sorgu.
	 * @return mixed
	 */
	public function query( $sql ) {
		$this->queries[] = $sql;

		if ( empty( $this->query_sonuclari ) ) {
			return 0;
		}

		return array_shift( $this->query_sonuclari );
	}
}

/**
 * Vitrin testleri için taze bir $wpdb takar.
 *
 * @return QRMS_Vitrin_Wpdb
 */
function qrms_vitrin_wpdb() {
	$GLOBALS['wpdb'] = new QRMS_Vitrin_Wpdb();

	return $GLOBALS['wpdb'];
}

qrms_test(
	'tüm DROP başarılıysa bayrak yazılır ve eski sürüm option silinir',
	function () {
		$db = qrms_vitrin_wpdb();
		$db->query_sonuclari = array( 0, 0 );

		update_option( RMA_Vitrin_Temizlik::ESKI_VERSION_OPTION, '1.0' );

		RMA_Vitrin_Temizlik::temizle();

		qrms_assert_same( '1', get_option( RMA_Vitrin_Temizlik::BAYRAK_OPTION ), 'bayrak yazılır' );
		qrms_assert_false( get_option( RMA_Vitrin_Temizlik::ESKI_VERSION_OPTION ), 'eski sürüm option silinir' );
		qrms_assert_same( 2, count( $db->queries ), 'her iki tablo için DROP çalıştı' );
	}
);

qrms_test(
	'DROP false dönerse bayrak yazılmaz',
	function () {
		$db = qrms_vitrin_wpdb();
		$db->query_sonuclari = array( false );

		RMA_Vitrin_Temizlik::temizle();

		qrms_assert_false( get_option( RMA_Vitrin_Temizlik::BAYRAK_OPTION ), 'bayrak yazılmaz' );
		qrms_assert_same( 1, count( $db->queries ), 'ikinci DROP denenmedi' );
	}
);

qrms_test(
	'başarısız migration sonraki admin isteğinde tekrar denenir',
	function () {
		$GLOBALS['qrms_test']['can'] = true;

		$db = qrms_vitrin_wpdb();
		$db->query_sonuclari = array( false );

		RMA_Vitrin_Temizlik::belki_temizle();
		qrms_assert_false( get_option( RMA_Vitrin_Temizlik::BAYRAK_OPTION ), 'ilk denemede bayrak yok' );

		$db->query_sonuclari = array( 0, 0 );
		RMA_Vitrin_Temizlik::belki_temizle();

		qrms_assert_same( '1', get_option( RMA_Vitrin_Temizlik::BAYRAK_OPTION ), 'ikinci denemede bayrak yazılır' );
	}
);

qrms_test(
	'yetkisiz kullanıcı için DROP çalıştırılmaz',
	function () {
		$GLOBALS['qrms_test']['can'] = false;

		$db = qrms_vitrin_wpdb();
		$db->query_sonuclari = array( 0, 0 );

		RMA_Vitrin_Temizlik::belki_temizle();

		qrms_assert_same( 0, count( $db->queries ), 'DROP hiç çalışmadı' );
		qrms_assert_false( get_option( RMA_Vitrin_Temizlik::BAYRAK_OPTION ), 'bayrak yazılmadı' );
	}
);

qrms_test(
	'yetkili kullanıcı için migration çalışır',
	function () {
		$GLOBALS['qrms_test']['can'] = true;

		$db = qrms_vitrin_wpdb();
		$db->query_sonuclari = array( 0, 0 );

		RMA_Vitrin_Temizlik::belki_temizle();

		qrms_assert_same( 2, count( $db->queries ), 'iki DROP çalıştı' );
		qrms_assert_same( '1', get_option( RMA_Vitrin_Temizlik::BAYRAK_OPTION ), 'bayrak yazıldı' );
	}
);

qrms_test(
	'tamamlandıktan sonra tekrar DROP çalıştırılmaz',
	function () {
		$GLOBALS['qrms_test']['can'] = true;

		$db = qrms_vitrin_wpdb();
		$db->query_sonuclari = array( 0, 0 );

		RMA_Vitrin_Temizlik::belki_temizle();
		qrms_assert_same( 2, count( $db->queries ), 'ilk seferde iki DROP çalıştı' );

		RMA_Vitrin_Temizlik::belki_temizle();
		qrms_assert_same( 2, count( $db->queries ), 'ikinci çağrıda yeni DROP çalışmadı' );
	}
);

/* -----------------------------------------------------------------
   Slider hizalama normalizasyonu
----------------------------------------------------------------- */

require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-slider-admin.php';

if ( ! class_exists( 'QRMS_Vitrin_Hizalama_Test_Yardimcisi' ) ) {
	/**
	 * `rma_align_row()` private olduğu için Reflection ile çağıran yardımcı.
	 */
	class QRMS_Vitrin_Hizalama_Test_Yardimcisi {
		use RMA_Slider_Admin_Trait;

		/**
		 * @param string $deger Ham hizalama değeri.
		 * @return string
		 */
		public function normalize( $deger ) {
			$metod = new ReflectionMethod( $this, 'rma_align_row' );
			$metod->setAccessible( true );

			ob_start();
			$metod->invoke( $this, 'test-id', 'test-name', $deger, 'Etiket', 'Açıklama' );
			$html = ob_get_clean();

			preg_match( '/is-selected">\s*<input[^>]*value="([a-z]*)"/s', $html, $eslesme );

			return $eslesme[1] ?? '';
		}
	}
}

qrms_test(
	'hizalama normalization: büyük/küçük harf ve boşluk temizlenir',
	function () {
		$yardimci = new QRMS_Vitrin_Hizalama_Test_Yardimcisi();

		qrms_assert_same( 'right', $yardimci->normalize( ' RIGHT ' ), 'büyük harf ve boşluk temizlenir' );
		qrms_assert_same( 'center', $yardimci->normalize( 'Center' ), 'baş harf büyük olsa da eşleşir' );
	}
);

qrms_test(
	'hizalama normalization: geçersiz değer left\'e düşer',
	function () {
		$yardimci = new QRMS_Vitrin_Hizalama_Test_Yardimcisi();

		qrms_assert_same( 'left', $yardimci->normalize( 'yukari' ), 'tanınmayan değer left olur' );
		qrms_assert_same( 'left', $yardimci->normalize( '' ), 'boş değer left olur' );
	}
);
