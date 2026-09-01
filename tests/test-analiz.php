<?php
/**
 * QR Analiz sayfa, kategori ve slug sayacı testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik-filtre.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/module.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/filtre-cubugu.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/genel-sayfasi.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/urunler-sayfasi.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/masalar-sayfasi.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/sepet-sayfasi.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/etkilesim-sayfasi.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/acilis-sayfasi.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/sistem-sayfasi.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/hub-sayfasi.php';

echo "\nQR Analiz sayfaları\n";

qrms_test(
	'modül satırı hub ekranıdır; yedi kategori kart olur, klasik görünüm kalktı',
	function () {
		// Sayfa listesi TEK KAYNAK: kayıt da kartlar da buradan beslenir.
		$sayfalar = qrms_module_qr_analiz_sayfalar();

		qrms_assert_same(
			array(
				'qrms-an-genel',
				'qrms-an-urunler',
				'qrms-an-masalar',
				'qrms-an-sepet',
				'qrms-an-etkilesim',
				'qrms-an-acilis',
				'qrms-an-sistem',
			),
			array_keys( $sayfalar ),
			'kategori slug\'ları'
		);

		foreach ( $sayfalar as $slug => $sayfa ) {
			qrms_assert_true( is_callable( $sayfa['render'] ), $slug . ' render edilebilir' );
			qrms_assert_true( '' !== $sayfa['desc'], $slug . ' açıklaması' );
			// İkonlar dashicons setinden gelir — emoji değil.
			qrms_assert_true( 0 === strpos( $sayfa['icon'], 'dashicons-' ), $slug . ' dashicon' );
		}

		// Eski tek-sayfa yapısı kapandı: dosyası yok, kartı yok.
		qrms_assert_false(
			file_exists( QRMS_PLUGIN_DIR . 'modules/qr-analiz/analitik-sayfasi.php' ),
			'klasik panel dosyası silindi'
		);

		// Kart sayısı lisansa bağlıdır; bu test tümü aktifken bakar (süzme
		// kendi testinde doğrulanır).
		update_option( 'qrms_active_modules', array( 'qr-analiz', 'qr-chatbot', 'qr-acilis-ekrani' ) );

		$kartlar = qrms_module_qr_analiz_hub_kartlari();
		qrms_assert_same( 7, count( $kartlar ), 'yalnızca kategoriler' );

		foreach ( $kartlar as $kart ) {
			qrms_assert_false(
				false !== strpos( $kart['url'], QRMS_ANALITIK_KLASIK_SAYFA ),
				'klasik görünüm kartı kalktı'
			);
		}

		// Bütün kategoriler doldu: rozet kalmaz.
		$rozetli = array();

		foreach ( $kartlar as $kart ) {
			if ( '' !== $kart['badge'] ) {
				$rozetli[] = $kart['title'];
			}
		}

		qrms_assert_same( 0, count( $rozetli ), 'Yakında rozeti kalmadı' );

		// Ayar ekranı dosyası artık güvenlik modülünün altındadır.
		qrms_assert_false( defined( 'QRMS_ANALIZ_AYAR_SAYFA' ), 'ayar slug sabiti taşındı' );
		qrms_assert_true(
			file_exists( QRMS_PLUGIN_DIR . 'modules/qr-masa-oturum-guvenligi/firebase-ayarlari-sayfasi.php' ),
			'yeni konumda var'
		);
	}
);

qrms_test(
	'lisansta pasif modüle bağlı kategori hiç basılmaz',
	function () {
		// Sepet & Sipariş chatbot'a, Açılış Ekranı açılış modülüne bağlıdır:
		// kullanıcıya satın almadığı bir şeyin boş ekranı gösterilmez.
		update_option( 'qrms_active_modules', array( 'qr-analiz', 'restoran-menu' ) );

		$gecerli = qrms_module_qr_analiz_gecerli_sayfalar();

		qrms_assert_false( isset( $gecerli['qrms-an-sepet'] ), 'chatbot pasif: sepet yok' );
		qrms_assert_false( isset( $gecerli['qrms-an-acilis'] ), 'açılış pasif: kategori yok' );
		qrms_assert_false( isset( $gecerli['qrms-an-etkilesim'] ), 'etkileşim bağlı modüller pasif: kategori yok' );
		qrms_assert_true( isset( $gecerli['qrms-an-genel'] ), 'çekirdek kategoriler durur' );
		qrms_assert_true( isset( $gecerli['qrms-an-masalar'] ), 'masalar modüle bağlı değil' );

		// Chatbot açılınca sepet ve (OR bağlı) etkileşim geri gelir.
		update_option( 'qrms_active_modules', array( 'qr-analiz', 'qr-chatbot' ) );

		$gecerli = qrms_module_qr_analiz_gecerli_sayfalar();

		qrms_assert_true( isset( $gecerli['qrms-an-sepet'] ), 'chatbot aktif: sepet var' );
		qrms_assert_true( isset( $gecerli['qrms-an-etkilesim'] ), 'chatbot aktif: etkileşim var' );

		update_option( 'qrms_active_modules', array() );
	}
);

qrms_test(
	'chatbot pasifken sepet sayfası yine kayıtlıdır ama hub kartı yoktur',
	function () {
		update_option( 'qrms_active_modules', array( 'qr-analiz' ) );
		QRMS_Analitik_Filtre::sifirla();

		$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] = array(
			qrms_submenu_satiri( 'İstatistikler', QRMS_Admin::get_module_page_slug( 'qr-analiz' ) ),
		);

		qrms_module_qr_analiz_admin_menu();

		$sluglar = array_map(
			function ( $item ) {
				return $item['slug'];
			},
			$GLOBALS['qrms_test']['submenus']
		);

		qrms_assert_true( in_array( 'qrms-an-sepet', $sluglar, true ), 'doğrudan URL çalışsın diye kayıtlı' );

		$kart_url = array();
		foreach ( qrms_module_qr_analiz_hub_kartlari() as $kart ) {
			$kart_url[] = $kart['url'];
		}

		$sepet_kart = false;
		foreach ( $kart_url as $url ) {
			if ( false !== strpos( $url, 'page=qrms-an-sepet' ) ) {
				$sepet_kart = true;
			}
		}

		qrms_assert_false( $sepet_kart, 'hub kartı basılmaz' );

		ob_start();
		qrms_analitik_sayfa_sepet();
		$html = ob_get_clean();

		qrms_assert_contains( 'Chatbot Asistan bu lisansta kapalı', $html, 'anlamlı mesaj' );
		qrms_assert_false( false !== strpos( $html, 'id="qrms-an-cards"' ), 'boş tablo yok' );
		qrms_assert_false( false !== strpos( $html, 'qrms-an-table' ), 'tablo iskeleti yok' );

		update_option( 'qrms_active_modules', array() );
	}
);

qrms_test(
	'kategori sayfaları menüye satır EKLEMEZ; alt sayfa olarak kaydolur',
	function () {
		QRMS_Analitik_Filtre::sifirla();

		$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] = array(
			qrms_submenu_satiri( 'İstatistikler', QRMS_Admin::get_module_page_slug( 'qr-analiz' ) ),
		);

		qrms_module_qr_analiz_admin_menu();

		$sluglar = array_map(
			function ( $item ) {
				return $item['slug'];
			},
			$GLOBALS['qrms_test']['submenus']
		);

		foreach ( array_keys( qrms_module_qr_analiz_gecerli_sayfalar() ) as $slug ) {
			qrms_assert_true( in_array( $slug, $sluglar, true ), $slug . ' kayıtlı' );
			qrms_assert_true( QRMS_Admin::is_module_subpage( $slug ), $slug . ' alt sayfa defterinde' );
		}

		// Eski adresler yönlendirme olarak kayıtlı kalır (ekran değil).
		qrms_assert_true( in_array( QRMS_ANALITIK_KLASIK_SAYFA, $sluglar, true ), 'klasik slug yönlendirir' );
		qrms_assert_true( in_array( QRMS_ANALITIK_SAYFA, $sluglar, true ), 'eski panel slug\'ı yönlendirir' );

		// Hepsi menüden düşer: beyaz listede yalnızca modül satırı vardır.
		$gizlenen = QRMS_Admin::collect_hidden_rows(
			$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ],
			QRMS_Admin::get_menu_row_slugs()
		);

		foreach ( array_keys( qrms_module_qr_analiz_gecerli_sayfalar() ) as $slug ) {
			qrms_assert_true( in_array( $slug, $gizlenen, true ), $slug . ' menüden düşer' );
		}
	}
);

qrms_test(
	'paylaşılan filtre her bağlantıya yapışır, varsayılanda adres temiz kalır',
	function () {
		// Dokunulmamış filtre: adreslerde gereksiz arg yok.
		QRMS_Analitik_Filtre::ayarla( array() );
		qrms_assert_same( 'bugun', QRMS_Analitik_Filtre::donem(), 'varsayılan dönem' );
		qrms_assert_same( array(), QRMS_Analitik_Filtre::args(), 'temiz adres' );

		// Seçim yapıldığında hub kartları da geri bağlantısı da taşır.
		QRMS_Analitik_Filtre::ayarla(
			array(
				'donem' => 'hafta',
				'masa'  => 'Masa 3',
			)
		);

		qrms_assert_same(
			array(
				'donem' => 'hafta',
				'masa'  => 'masa-3',
			),
			QRMS_Analitik_Filtre::args(),
			'taşınan filtre'
		);

		$url = QRMS_Analitik_Filtre::url( 'qrms-an-sepet' );
		qrms_assert_contains( 'page=qrms-an-sepet', $url, 'sayfa' );
		qrms_assert_contains( 'donem=hafta', $url, 'dönem taşındı' );
		qrms_assert_contains( 'masa=masa-3', $url, 'masa taşındı' );

		foreach ( qrms_module_qr_analiz_hub_kartlari() as $kart ) {
			qrms_assert_contains( 'donem=hafta', $kart['url'], 'kart dönemi taşır' );
			qrms_assert_contains( 'masa=masa-3', $kart['url'], 'kart masayı taşır' );
		}

		// Geri bağlantısı hub'a döner ama seçimi kaybetmez.
		$geri = qrms_module_qr_analiz_geri_url( QRMS_Admin::get_module_page_url( 'qr-analiz' ), 'qr-analiz' );
		qrms_assert_contains( 'page=' . QRMS_Admin::get_module_page_slug( 'qr-analiz' ), $geri, 'hub adresi' );
		qrms_assert_contains( 'donem=hafta', $geri, 'geri bağlantısı dönemi taşır' );

		// Başka modülün alt sayfası etkilenmez.
		qrms_assert_same(
			QRMS_Admin::get_module_page_url( 'qr-galeri' ),
			qrms_module_qr_analiz_geri_url( QRMS_Admin::get_module_page_url( 'qr-galeri' ), 'qr-galeri' ),
			'yabancı modül dokunulmaz'
		);

		QRMS_Analitik_Filtre::sifirla();
	}
);

qrms_test(
	'filtre bağlamı bozuk değerleri güvenli varsayılana indirger',
	function () {
		// Tanınmayan dönem.
		$b = QRMS_Analitik_Filtre::coz( array( 'donem' => 'yil' ) );
		qrms_assert_same( 'bugun', $b['donem'], 'tanınmayan dönem' );

		// "ozel" ama tarih eksik: yarım aralık tüm tabloyu taratırdı.
		$b = QRMS_Analitik_Filtre::coz(
			array(
				'donem' => 'ozel',
				'bas'   => '2026-01-01',
			)
		);
		qrms_assert_same( 'bugun', $b['donem'], 'yarım aralık reddedilir' );

		// Takvimde olmayan gün.
		$b = QRMS_Analitik_Filtre::coz(
			array(
				'donem' => 'ozel',
				'bas'   => '2026-02-31',
				'bit'   => '2026-03-01',
			)
		);
		qrms_assert_same( 'bugun', $b['donem'], 'geçersiz tarih' );

		// Ters aralık takas edilir.
		$b = QRMS_Analitik_Filtre::coz(
			array(
				'donem' => 'ozel',
				'bas'   => '2026-03-10',
				'bit'   => '2026-03-01',
			)
		);
		qrms_assert_same( 'ozel', $b['donem'], 'özel aralık' );
		qrms_assert_same( '2026-03-01', $b['bas'], 'başlangıç takas edildi' );
		qrms_assert_same( '2026-03-10', $b['bit'], 'bitiş takas edildi' );

		// Dönem özel değilse tarihler taşınmaz.
		$b = QRMS_Analitik_Filtre::coz(
			array(
				'donem' => 'ay',
				'bas'   => '2026-03-01',
				'bit'   => '2026-03-10',
			)
		);
		qrms_assert_same( '', $b['bas'], 'artık tarih taşınmaz' );

		// Dizi gelirse (?masa[]=x) çökme değil, boş filtre.
		$b = QRMS_Analitik_Filtre::coz( array( 'masa' => array( 'x' ) ) );
		qrms_assert_same( '', $b['masa'], 'dizi değer yok sayılır' );
	}
);

qrms_test(
	'panelin eski adresi kayıtlı kalır ve modül satırına yönlendirir',
	function () {
		$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] = array(
			qrms_submenu_satiri( 'QR Analiz', QRMS_Admin::get_module_page_slug( 'qr-analiz' ) ),
		);

		qrms_module_qr_analiz_admin_menu();

		$eski = array_values(
			array_filter(
				$GLOBALS['qrms_test']['submenus'],
				function ( $item ) {
					return QRMS_ANALITIK_SAYFA === $item['slug'];
				}
			)
		);

		qrms_assert_same( 1, count( $eski ), 'eski adres bir kez kayıtlı' );

		// Üst menü '' — satır sol menüde hiç görünmez, yalnızca adres çalışır.
		qrms_assert_same( '', $eski[0]['parent'], 'gizli sayfa' );

		try {
			qrms_module_qr_analiz_eski_adresi_yonlendir();
			qrms_assert_true( false, 'yönlendirme bekleniyordu' );
		} catch ( QRMS_Test_Redirect $e ) {
			qrms_assert_same(
				QRMS_Admin::get_module_page_url( 'qr-analiz' ),
				$e->getMessage(),
				'panel adresine gider'
			);
		}
	}
);

qrms_test(
	'modül lisansta aktif değilken eski adres de kaydedilmez',
	function () {
		// "QR Analiz" satırı yoksa $submenu de boştur; ekranın kaydedilmesi
		// menüde ölü satır bırakırdı.
		qrms_module_qr_analiz_admin_menu();

		qrms_assert_same( array(), $GLOBALS['qrms_test']['submenus'], 'kayıt yok' );
	}
);

echo "\nFaz 9 — eski slug sayacı\n";

qrms_test(
	'eski slug vuruşu option\'a yazılır; boş slug yok sayılır',
	function () {
		QRMS_Helpers::legacy_slug_hit( 'rma_settings' );
		QRMS_Helpers::legacy_slug_hit( 'rma_settings' );
		QRMS_Helpers::legacy_slug_hit( 'qrms-analiz-panel' );
		QRMS_Helpers::legacy_slug_hit( '' );
		QRMS_Helpers::legacy_slug_hit( 'Not A Key!!' );

		$hits = QRMS_Helpers::legacy_slug_hits();

		qrms_assert_same( 2, $hits['rma_settings']['count'], 'aynı slug artar' );
		qrms_assert_same( 1, $hits['qrms-analiz-panel']['count'], 'ikinci slug ayrı' );
		qrms_assert_false( isset( $hits[''] ), 'boş slug yazılmaz' );
		qrms_assert_true( isset( $hits['notakey'] ), 'sanitize_key uygulanır' );
		qrms_assert_true( isset( $hits['rma_settings']['first'] ), 'ilk vuruş damgası' );
		qrms_assert_true( isset( $hits['rma_settings']['last'] ), 'son vuruş damgası' );
		qrms_assert_same(
			$hits['rma_settings']['first'],
			$hits['rma_settings']['last'],
			'aynı saniye içinde first=last'
		);
	}
);

qrms_test(
	'analiz eski adresi yönlendirirken sayacı artırır',
	function () {
		$_GET['page'] = QRMS_ANALITIK_SAYFA;

		try {
			qrms_module_qr_analiz_eski_adresi_yonlendir();
			qrms_assert_true( false, 'yönlendirme bekleniyordu' );
		} catch ( QRMS_Test_Redirect $e ) {
			$hits = QRMS_Helpers::legacy_slug_hits();
			qrms_assert_same( 1, $hits[ QRMS_ANALITIK_SAYFA ]['count'], 'vuruş kaydı' );
		}
	}
);

echo "\nQR Analiz teşhisi\n";

// Sınıf dosya kapsamında yalnızca tanım içerir (kancalar init() içinde
// kaydolur), bu yüzden stub ortamında doğrudan yüklenebilir. Test edilen
// eşleştirici saf bir dizi/string dönüşümüdür.
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php';

qrms_test(
	'sınıfın dosyası eklentinin GİRİŞ dosyasına eşlenir',
	function () {
		// Sınıf çoğu zaman alt klasörde durur; devre dışı bırakma bağlantısı
		// ise eklentinin giriş dosyasını ister.
		qrms_assert_same(
			'rma-analytics/rma-analytics.php',
			QRMS_Analitik::eklenti_dosyasini_bul(
				'rma-analytics/includes/class-analytics.php',
				array( 'akismet/akismet.php', 'rma-analytics/rma-analytics.php' )
			),
			'klasörlü eklenti'
		);
	}
);

qrms_test(
	'tek dosyalık eklenti de eşleşir',
	function () {
		qrms_assert_same(
			'rma-analytics.php',
			QRMS_Analitik::eklenti_dosyasini_bul( 'rma-analytics.php', array( 'rma-analytics.php' ) ),
			'kök dosya'
		);
	}
);

qrms_test(
	'eşleşme yoksa boş string döner, yanlış eklenti kapatılmaz',
	function () {
		// Regresyon: gevşek bir eşleştirme başka bir eklentiyi devre dışı
		// bırakma bağlantısı üretebilirdi.
		qrms_assert_same(
			'',
			QRMS_Analitik::eklenti_dosyasini_bul( 'rma-analytics/rma.php', array( 'akismet/akismet.php' ) ),
			'listede yok'
		);
		qrms_assert_same( '', QRMS_Analitik::eklenti_dosyasini_bul( '', array( 'akismet/akismet.php' ) ), 'boş yol' );
	}
);

qrms_test(
	'aynı adla başlayan başka bir klasör eşleşmez',
	function () {
		qrms_assert_same(
			'',
			QRMS_Analitik::eklenti_dosyasini_bul(
				'rma-analytics/rma.php',
				array( 'rma-analytics-pro/rma-analytics-pro.php' )
			),
			'klasör adı tam eşleşmeli'
		);
	}
);

echo "\nQR Analiz (kategoriler)\n";

qrms_test(
	'eski tek-sayfa yapısı kapandı; araçlar "Veri & Sistem"e taşındı',
	function () {
		$sistem = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/sistem-sayfasi.php' );

		// Taşınan araçların hepsi burada.
		qrms_assert_contains( 'id="qrms-an-clear"', $sistem, 'verileri sil' );
		qrms_assert_contains( 'id="qrms-an-refresh"', $sistem, 'yenile' );
		qrms_assert_contains( 'id="qrms-an-confirm"', $sistem, 'onay modalı' );
		qrms_assert_contains( 'qrms_analitik_teshis_listesi()', $sistem, 'teşhis' );
		qrms_assert_contains( "'kategori' => 'ham'", $sistem, 'ham CSV' );

		// Teşhis İKİ yerde ama TEK kaynaktan: hub kısa uyarı, sistem tam liste.
		$hub = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/hub-sayfasi.php' );

		qrms_assert_contains( 'QRMS_Analitik::teshis()', $hub, 'hub aynı kaynaktan' );
		qrms_assert_contains( 'QRMS_Analitik::teshis()', $sistem, 'sistem aynı kaynaktan' );

		// Silme akışı AYNEN taşındı: aynı uç, aynı nonce.
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik-sistem.js' );

		qrms_assert_contains( 'qrms_analitik_temizle', $js, 'silme ucu değişmedi' );
		qrms_assert_contains( 'confirmTable', $js, 'masa kapsamlı silme metni' );

		// Klasik panel betiği silindi: kuyruğa da, diske de yok.
		qrms_assert_false(
			file_exists( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik.js' ),
			'ölü betik silindi'
		);

		$modul = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/module.php' );

		qrms_assert_false( false !== strpos( $modul, 'assets/js/analitik.js' ), 'kuyruğa girmiyor' );
	}
);

echo "\nQR Analiz — Ürünler kategorisi\n";

qrms_test(
	'en az tıklananlar CPT\'den başlar: hiç tıklanmamış ürün de listelenir',
	function () {
		// İki kaynak birleşir: yayındaki ürünler + tıklama sayaçları.
		// Sayacı olmayan ürün listeden DÜŞMEMELİ; aranan tam da odur.
		$GLOBALS['qrms_test']['posts'] = array(
			(object) array(
				'ID'         => 11,
				'post_title' => 'Mercimek Çorbası',
			),
			(object) array(
				'ID'         => 12,
				'post_title' => 'Ayran',
			),
			(object) array(
				'ID'         => 13,
				'post_title' => 'Künefe',
			),
		);
		$GLOBALS['qrms_test']['post_meta'] = array( 13 => array( '_rma_tukendi' => '1' ) );
		$GLOBALS['qrms_test']['terms']     = array( 11 => 'Çorbalar' );

		qrms_analitik_onbellek_sifirla();

		$sonuc = qrms_analitik_en_az_tiklananlar(
			array(
				11 => array(
					'toplam' => 9,
					'tekil'  => 4,
					'son'    => '2026-03-10 12:00:00',
				),
			)
		);

		qrms_assert_same( 3, $sonuc['toplam'], 'üç ürün de listede' );
		qrms_assert_same( 2, $sonuc['hic'], 'iki ürün hiç tıklanmadı' );
		qrms_assert_same( 1, $sonuc['tukendi'], 'bir ürün tükendi' );

		// Artan sıralama; eşitlikte ada göre (sayfalama kaymasın).
		qrms_assert_same( 'Ayran', $sonuc['satirlar'][0]['ad'], 'en az tıklanan önce' );
		qrms_assert_same( 'Künefe', $sonuc['satirlar'][1]['ad'], 'eşitlikte ada göre' );
		qrms_assert_same( 'Mercimek Çorbası', $sonuc['satirlar'][2]['ad'], 'en çok tıklanan sonda' );

		// Tükendi ürün "ölü ürün" sanılmasın diye işaretlenir.
		qrms_assert_true( $sonuc['satirlar'][1]['tukendi'], 'tükendi bayrağı' );
		qrms_assert_false( $sonuc['satirlar'][0]['tukendi'], 'stokta olan işaretsiz' );

		// Sayaçlar ürüne doğru eşleşir.
		qrms_assert_same( 9, $sonuc['satirlar'][2]['toplam'], 'tıklama sayısı' );
		qrms_assert_same( 'Çorbalar', $sonuc['satirlar'][2]['kategori'], 'kategori adı' );
	}
);

qrms_test(
	'ürün listesi N+1 sorgu üretmez: tek get_posts, tek GROUP BY',
	function () {
		$GLOBALS['qrms_test']['posts'] = array();

		for ( $i = 1; $i <= 40; $i++ ) {
			$GLOBALS['qrms_test']['posts'][] = (object) array(
				'ID'         => $i,
				'post_title' => 'Ürün ' . $i,
			);
		}

		$GLOBALS['qrms_test']['post_meta'] = array();
		$GLOBALS['qrms_test']['terms']     = array();
		$GLOBALS['qrms_test']['get_posts_calls'] = 0;

		qrms_analitik_onbellek_sifirla();

		$wpdb = qrms_sayan_wpdb();
		$wpdb->results[] = array();  // urun_tiklama_sayaclari
		$wpdb->results[] = array();  // kategori_dagilimi
		$wpdb->vars[]    = 0;        // kategorisiz sayımı
		$wpdb->results[] = array();  // olay_sayaclari detay
		$wpdb->results[] = array();  // urun_siralamasi

		$veri = qrms_analitik_urun_verisi(
			array(
				'bas' => '2026-03-01 00:00:00',
				'bit' => '2026-03-31 23:59:59',
				'gun' => 31,
			),
			'',
			1,
			25
		);

		// Ürün sayısı 40 olmasına rağmen sorgu sayısı SABİT kalır: bir
		// get_posts + beş analitik sorgusu (sayaçlar, dağılım, kategorisiz
		// sayımı, detay açılışı, en çok tıklananlar).
		qrms_assert_same( 1, $GLOBALS['qrms_test']['get_posts_calls'], 'tek ürün sorgusu' );
		qrms_assert_same( 5, count( $wpdb->queries ), 'sabit sayıda analitik sorgusu' );

		// Sayfalama: 40 üründen ilk 25'i.
		qrms_assert_same( 25, count( $veri['enaz'] ), 'sayfa boyu' );
		qrms_assert_same( 2, $veri['enazOzet']['sayfalar'], 'iki sayfa' );
		qrms_assert_same( 40, $veri['enazOzet']['toplam'], 'toplam ürün' );

		// İkinci sayfa kalanı verir.
		qrms_analitik_onbellek_sifirla();
		$wpdb = qrms_sayan_wpdb();
		$wpdb->results[] = array();
		$wpdb->results[] = array();
		$wpdb->vars[]    = 0;
		$wpdb->results[] = array();
		$wpdb->results[] = array();

		$veri = qrms_analitik_urun_verisi(
			array(
				'bas' => '2026-03-01 00:00:00',
				'bit' => '2026-03-31 23:59:59',
				'gun' => 31,
			),
			'',
			2,
			25
		);

		qrms_assert_same( 15, count( $veri['enaz'] ), 'ikinci sayfa' );
	}
);

echo "\nQR Analiz — Veri & Sistem\n";

qrms_test(
	'saklama süresi kaydedilir; filtre en sonda kalır',
	function () {
		delete_option( QRMS_Analitik::SAKLAMA_OPT );

		// Ayar yokken sabit varsayılan geçerlidir.
		qrms_assert_same( 90, QRMS_Analitik::saklama_ayari(), 'varsayılan' );
		qrms_assert_same( 90, QRMS_Analitik::saklama_gun(), 'geçerli süre' );
		qrms_assert_false( QRMS_Analitik::saklama_kilitli_mi(), 'filtre yok' );

		// Kaydedilen değer geçerli olur.
		QRMS_Analitik::saklama_kaydet( 30 );
		qrms_assert_same( 30, QRMS_Analitik::saklama_gun(), 'kaydedilen süre' );

		// Alt sınır: 0 dışında 7 günün altına inilmez (panelin "son 30 gün"
		// görünümleri boşalmasın).
		QRMS_Analitik::saklama_kaydet( 3 );
		qrms_assert_same( 7, QRMS_Analitik::saklama_gun(), 'alt sınır' );

		// 0 temizliği kapatır.
		QRMS_Analitik::saklama_kaydet( 0 );
		qrms_assert_same( 0, QRMS_Analitik::saklama_gun(), 'sınırsız saklama' );
		qrms_assert_same( 0, QRMS_Analitik::eski_kayitlari_sil(), 'temizlik çalışmaz' );

		delete_option( QRMS_Analitik::SAKLAMA_OPT );
	}
);

qrms_test(
	'kodla sabitlenmiş saklama süresi ekranda görünür kılınır',
	function () {
		delete_option( QRMS_Analitik::SAKLAMA_OPT );
		QRMS_Analitik::saklama_kaydet( 30 );

		// Bir mu-plugin süreyi sabitlemişse ekrandan kaydedilen değer
		// geçersizdir; kullanıcı bunu bilmeli.
		add_filter(
			'qrms_analitik_saklama_gun',
			function () {
				return 120;
			}
		);

		qrms_assert_same( 30, QRMS_Analitik::saklama_ayari(), 'kayıtlı değer korunur' );
		qrms_assert_same( 120, QRMS_Analitik::saklama_gun(), 'filtre kazanır' );
		qrms_assert_true( QRMS_Analitik::saklama_kilitli_mi(), 'ekran uyarır' );

		$GLOBALS['qrms_test']['actions']['qrms_analitik_saklama_gun'] = array();
		delete_option( QRMS_Analitik::SAKLAMA_OPT );
	}
);

qrms_test(
	'tablo istatistikleri transient ile önbelleklenir',
	function () {
		delete_transient( 'qrms_analitik_tablo_istat' );

		$wpdb = qrms_sayan_wpdb();
		$wpdb->vars[]    = 'wp_rma_analytics'; // tablo_var_mi
		$wpdb->rows[]    = array(
			'satir' => 1200,
			'ilk'   => '2026-01-05 10:00:00',
		);
		$wpdb->vars[]    = 3145728; // DATA_LENGTH + INDEX_LENGTH

		$istat = QRMS_Analitik::tablo_istatistikleri();

		qrms_assert_same( 1200, $istat['satir'], 'satır sayısı' );
		qrms_assert_same( '2026-01-05 10:00:00', $istat['ilk'], 'en eski kayıt' );
		qrms_assert_same( 3145728, $istat['boyut'], 'disk boyutu' );

		$sorgu_sayisi = count( $wpdb->queries );

		// İkinci çağrı hiç sorgu açmaz: COUNT(*) ve information_schema her
		// sayfa açılışında çalıştırılacak şeyler değildir.
		QRMS_Analitik::tablo_istatistikleri();
		qrms_assert_same( $sorgu_sayisi, count( $wpdb->queries ), 'ikinci çağrı önbellekten' );

		// Kayıt silindiğinde önbellek düşer, yoksa ekran bayat sayı gösterirdi.
		QRMS_Analitik::istatistik_onbellegini_temizle();
		qrms_assert_false( get_transient( 'qrms_analitik_tablo_istat' ), 'önbellek düştü' );

		qrms_assert_contains( 'MB', qrms_analitik_boyut_metni( 3145728 ), 'okunabilir boyut' );
		qrms_assert_same( '—', qrms_analitik_boyut_metni( 0 ), 'boş tablo' );
	}
);

qrms_test(
	'ham CSV akış hâlinde yazar: id ilerlemeli, tavanlı',
	function () {
		// Bellek: bütün tabloyu diziye almak yerine dilim dilim çekilir.
		// Sayfalama OFFSET ile değil id > son_id ile yapılır (OFFSET büyüdükçe
		// MySQL atlanan satırları da okur).
		$sinif = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php' );

		qrms_assert_contains( 'id > %d', $sinif, 'id ilerlemeli sayfalama' );
		// OFFSET yalnızca gerekçe yorumunda geçer, SORGUDA değil.
		qrms_assert_false( false !== strpos( $sinif, 'LIMIT %d OFFSET' ), 'OFFSET ile sayfalama yok' );
		qrms_assert_contains( 'const CSV_TAVAN', $sinif, 'satır tavanı' );
		qrms_assert_contains( 'const CSV_PARCA', $sinif, 'dilim boyu' );

		// Tavana dayanılırsa kullanıcı bunu dosyanın içinde görür; sessizce
		// kesmek eksik veriyi tam sanmaktan kötüdür.
		qrms_assert_contains( 'UYARI: Dosya', $sinif, 'kesme uyarısı' );
	}
);

echo "\nQR Analiz — Masalar kategorisi\n";

qrms_test(
	'hiç okutulmamış masa listeden DÜŞMEZ, 0 ile görünür',
	function () {
		// Kayıt kaynağı qr-masa; sayaç kaynağı analitik tablosu. Sayacı
		// olmayan masa listede kalmalı — asıl aranan satır odur.
		$masalar = array(
			array(
				'slug' => 'bahce-1',
				'ad'   => 'Bahçe 1',
				'grup' => 'bahce',
			),
			array(
				'slug' => 'bahce-2',
				'ad'   => 'Bahçe 2',
				'grup' => 'bahce',
			),
			array(
				'slug' => 'salon-1',
				'ad'   => 'Salon 1',
				'grup' => 'salon',
			),
		);

		$sayaclar = array(
			'bahce-1' => array(
				'mv'  => 40,
				'pc'  => 10,
				'uv'  => 22,
				'son' => '2026-03-10 12:00:00',
			),
			'salon-1' => array(
				'mv'  => 5,
				'pc'  => 1,
				'uv'  => 3,
				'son' => '2026-03-09 20:00:00',
			),
		);

		$karne = qrms_analitik_masa_karnesi( $masalar, $sayaclar );

		qrms_assert_same( 3, count( $karne['satirlar'] ), 'üç masa da listede' );
		qrms_assert_same( 3, $karne['ozet']['kayitli'], 'kayıtlı masa sayısı' );
		qrms_assert_same( 1, $karne['ozet']['sessiz'], 'hiç okutulmayan bir masa' );

		// Hareketi çok olan üstte, sessiz masa altta ama LİSTEDE.
		qrms_assert_same( 'bahce-1', $karne['satirlar'][0]['masa'], 'en hareketli üstte' );
		qrms_assert_same( 'bahce-2', $karne['satirlar'][2]['masa'], 'sessiz masa listede' );
		qrms_assert_same( 0, $karne['satirlar'][2]['mv'], 'sıfır okutma' );
	}
);

qrms_test(
	'silinmiş masanın kaydı "kayıtlı değil" olarak durur, masasız erişim ayrıdır',
	function () {
		$masalar = array(
			array(
				'slug' => 'salon-1',
				'ad'   => 'Salon 1',
				'grup' => 'salon',
			),
		);

		$sayaclar = array(
			'salon-1' => array(
				'mv'  => 10,
				'pc'  => 2,
				'uv'  => 6,
				'son' => '2026-03-10 10:00:00',
			),
			// Artık qr-masa'da olmayan bir masa: geçmişi kaybolmamalı.
			'eski-masa' => array(
				'mv'  => 30,
				'pc'  => 8,
				'uv'  => 15,
				'son' => '2026-02-01 10:00:00',
			),
			// QR okutmadan gelen hareketler.
			'' => array(
				'mv'  => 7,
				'pc'  => 1,
				'uv'  => 4,
				'son' => '2026-03-11 09:00:00',
			),
		);

		$karne   = qrms_analitik_masa_karnesi( $masalar, $sayaclar );
		$durumlar = array();

		foreach ( $karne['satirlar'] as $satir ) {
			$durumlar[ $satir['masa'] ] = $satir['durum'];
		}

		qrms_assert_same( 'kayitli', $durumlar['salon-1'], 'kayıtlı masa' );
		qrms_assert_same( 'kayitsiz', $durumlar['eski-masa'], 'silinmiş masa listede kalır' );
		qrms_assert_same( 'masasiz', $durumlar[''], 'masasız erişim ayrı' );
		qrms_assert_same( 1, $karne['ozet']['kayitsiz'], 'kayıtsız sayacı' );

		// Masasız satır bir masa değildir: sıralamaya karışmaz, en sonda durur.
		$son = $karne['satirlar'][ count( $karne['satirlar'] ) - 1 ];
		qrms_assert_same( 'masasiz', $son['durum'], 'masasız en sonda' );

		// Gruplar YALNIZCA kayıtlı masalardan üretilir.
		qrms_assert_same( 1, count( $karne['gruplar'] ), 'tek grup' );
		qrms_assert_same( 'salon', $karne['gruplar'][0]['grup'], 'grup adı' );
		qrms_assert_same( 10, $karne['gruplar'][0]['mv'], 'grup toplamı' );
	}
);

qrms_test(
	'gruplar toplulaştırılır ve sessiz masaları ayrıca sayar',
	function () {
		$masalar = array();

		for ( $i = 1; $i <= 3; $i++ ) {
			$masalar[] = array(
				'slug' => 'bahce-' . $i,
				'ad'   => 'Bahçe ' . $i,
				'grup' => 'bahce',
			);
		}

		$masalar[] = array(
			'slug' => 'salon-1',
			'ad'   => 'Salon 1',
			'grup' => 'salon',
		);

		$karne = qrms_analitik_masa_karnesi(
			$masalar,
			array(
				'bahce-1' => array(
					'mv'  => 60,
					'pc'  => 20,
					'uv'  => 30,
					'son' => '',
				),
				'bahce-2' => array(
					'mv'  => 60,
					'pc'  => 20,
					'uv'  => 30,
					'son' => '',
				),
				'salon-1' => array(
					'mv'  => 300,
					'pc'  => 40,
					'uv'  => 120,
					'son' => '',
				),
			)
		);

		// Sıralama toplam harekete göre: salon (340) > bahçe (160).
		qrms_assert_same( 'salon', $karne['gruplar'][0]['grup'], 'en hareketli grup' );
		qrms_assert_same( 300, $karne['gruplar'][0]['mv'], 'salon okutma' );
		qrms_assert_same( 120, $karne['gruplar'][1]['mv'], 'bahçe okutma toplamı' );
		qrms_assert_same( 3, $karne['gruplar'][1]['masa'], 'bahçede üç masa' );
		qrms_assert_same( 1, $karne['gruplar'][1]['sessiz'], 'biri hiç okutulmamış' );
	}
);

qrms_test(
	'uzun masa slug\'ı analitikteki kırpılmış anahtarla eşleşir',
	function () {
		// masa_no varchar(64); qrm_tables.table_slug varchar(100). Kırpma
		// hesaba katılmazsa uzun adlı masa "hiç okutulmadı" görünürdü.
		$uzun    = str_repeat( 'a', 70 );
		$kirpik  = substr( $uzun, 0, QRMS_Analitik::MASA_UZUNLUK );

		qrms_assert_same( 64, strlen( $kirpik ), 'anahtar 64 karaktere iner' );
		qrms_assert_same( $kirpik, qrms_analitik_masa_anahtari( $uzun ), 'anahtar kırpılır' );

		$karne = qrms_analitik_masa_karnesi(
			array(
				array(
					'slug' => $uzun,
					'ad'   => 'Uzun Masa',
					'grup' => 'uzun',
				),
			),
			array(
				$kirpik => array(
					'mv'  => 12,
					'pc'  => 3,
					'uv'  => 7,
					'son' => '2026-03-10 12:00:00',
				),
			)
		);

		qrms_assert_same( 1, count( $karne['satirlar'] ), 'tek satır (kopya yok)' );
		qrms_assert_same( 12, $karne['satirlar'][0]['mv'], 'sayaç eşleşti' );
		qrms_assert_same( 0, $karne['ozet']['kayitsiz'], 'kayıtsız satır üretilmedi' );
	}
);

qrms_test(
	'masa sayaçları tek GROUP BY ile indeksli aralıktan gelir',
	function () {
		$wpdb            = qrms_sayan_wpdb();
		$wpdb->results[] = array(
			array(
				'masa_no' => 'bahce-1',
				'mv'      => 10,
				'pc'      => 2,
				'uv'      => 6,
				'son'     => '2026-03-10 12:00:00',
			),
		);

		$sayac = QRMS_Analitik::masa_sayaclari( '2026-03-01 00:00:00', '2026-03-31 23:59:59', 'bahce-1' );

		qrms_assert_same( 1, count( $wpdb->queries ), 'tek sorgu' );
		qrms_assert_same( 10, $sayac['bahce-1']['mv'], 'sayaç masa_no ile anahtarlanır' );

		// Aralık iki uçtan sınırlı + masa filtresi: idx_masa_td kullanılabilir.
		qrms_assert_contains( 'created_at BETWEEN', $wpdb->queries[0], 'kapalı aralık' );
		qrms_assert_contains( "masa_no = 'bahce-1'", $wpdb->queries[0], 'masa filtresi' );
		qrms_assert_contains( 'GROUP BY masa_no', $wpdb->queries[0], 'tek gruplama' );
	}
);

qrms_test(
	'garson çağırma / hesap isteme bölümü Masalar ekranında BASILMAZ',
	function () {
		$sayfa = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/masalar-sayfasi.php' );

		// Faz 6 olayları yazıyor; Masalar raporu henüz bağlamaz. Boş bir
		// tablo veri yokluğunu hata gibi gösterirdi; bölüm hiç basılmaz
		// ama yeri yorumla işaretlidir.
		qrms_assert_contains( 'Garson çağırma / hesap isteme sayaçları', $sayfa, 'yer işareti' );
		qrms_assert_contains( 'waiter_call', $sayfa, 'olay adı yazılı' );
		qrms_assert_contains( 'bill_request', $sayfa, 'olay adı yazılı' );

		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik-masalar.js' );

		qrms_assert_false( false !== strpos( $js, 'waiter' ), 'ekranda bölüm yok' );
		qrms_assert_false( false !== strpos( $js, 'bill' ), 'ekranda bölüm yok' );
	}
);

echo "\nQR Analiz — Sepet & Sipariş kategorisi\n";

qrms_test(
	'Faz 6 yazım stratejisi: debounce toplu gönderim, sipariş kalem başına tek satır',
	function () {
		$js  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' );
		$sip = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/rest-order.php' );

		qrms_assert_contains( 'ANALITIK_PENCERE = 3000', $js, '3 sn debounce' );
		qrms_assert_contains( 'istemci debounce + toplu gönderim seçildi', $js, 'strateji debounce, oturum sonu değil' );
		qrms_assert_contains( 'Adet kadar satır YAZILMAZ', $sip, 'sipariş kalem başına' );
		qrms_assert_contains( 'Her kalem (satır) için bir olay', $sip, 'adet çoğaltılmaz' );
	}
);

qrms_test(
	'terk oturum bazlıdır: cart_add var, order_sent yok',
	function () {
		// İki oturum: biri terk, biri dönüşmüş. Olay sayısı 3+1 olsa da
		// terk 1 oturumdur — olay bazlı saymak yanıltırdı.
		$gruplar = array(
			array(
				'ip_hash'    => 'aaa',
				'masa_no'    => 'masa-1',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'cart_add',
				'item_id'    => 10,
				'item_name'  => 'Burger',
				'category_name' => 'Ana',
				'adet'       => 3,
			),
			array(
				'ip_hash'    => 'bbb',
				'masa_no'    => 'masa-2',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'cart_add',
				'item_id'    => 10,
				'item_name'  => 'Burger',
				'category_name' => 'Ana',
				'adet'       => 1,
			),
			array(
				'ip_hash'    => 'bbb',
				'masa_no'    => 'masa-2',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'order_sent',
				'item_id'    => 10,
				'item_name'  => 'Burger',
				'category_name' => 'Ana',
				'adet'       => 1,
			),
		);

		$sonuc = qrms_analitik_sepet_hesapla( $gruplar, 1, 0 );

		qrms_assert_same( 4, $sonuc['ozet']['cart_add'], 'olay sayısı (3+1), porsiyon değil' );
		qrms_assert_same( 1, $sonuc['ozet']['cart_add_urun'], 'tekil ürün' );
		qrms_assert_same( 1, $sonuc['ozet']['order_sent'], 'dönüşen oturum' );
		qrms_assert_same( 1, $sonuc['ozet']['terk'], 'terk oturum' );
		qrms_assert_same( 2, $sonuc['ozet']['oturum_add'], 'sepeti olan oturum' );
		qrms_assert_same( 50, $sonuc['ozet']['terk_oran'], 'terk oranı %50' );
		qrms_assert_false( $sonuc['bos'], 'veri var' );

		// Burger: terk oturumunda dönüşmedi, dönüşen oturumda dönüştü.
		qrms_assert_same( 1, count( $sonuc['terk_urun'] ), 'yalnızca dönüşmeyen oturumdaki ürün' );
		qrms_assert_same( 10, $sonuc['terk_urun'][0]['id'], 'burger' );
		qrms_assert_same( 1, $sonuc['terk_urun'][0]['terk'], 'bir oturum terk' );
		qrms_assert_same( 3, $sonuc['terk_urun'][0]['ekleme'], 'terk oturumundaki ekleme olayı' );
	}
);

qrms_test(
	'siparişe dönüşen kalem terk tablosuna girmez; aynı oturumda kalan girer',
	function () {
		// Burger + ayran eklendi, yalnızca ayran sipariş edildi: burger
		// fiyat direnci tablosundadır, oturum ise TERK DEĞİLDİR (order_sent var).
		$gruplar = array(
			array(
				'ip_hash'    => 'aaa',
				'masa_no'    => 'masa-1',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'cart_add',
				'item_id'    => 10,
				'item_name'  => 'Burger',
				'adet'       => 1,
			),
			array(
				'ip_hash'    => 'aaa',
				'masa_no'    => 'masa-1',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'cart_add',
				'item_id'    => 11,
				'item_name'  => 'Ayran',
				'adet'       => 1,
			),
			array(
				'ip_hash'    => 'aaa',
				'masa_no'    => 'masa-1',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'order_sent',
				'item_id'    => 11,
				'item_name'  => 'Ayran',
				'adet'       => 1,
			),
		);

		$sonuc = qrms_analitik_sepet_hesapla( $gruplar, 1, 0 );

		qrms_assert_same( 0, $sonuc['ozet']['terk'], 'oturum sipariş verdi' );
		qrms_assert_same( 1, $sonuc['ozet']['order_sent'], 'gönderildi' );
		qrms_assert_same( 1, count( $sonuc['terk_urun'] ), 'burger dönüşmedi' );
		qrms_assert_same( 'Burger', $sonuc['terk_urun'][0]['ad'], 'dönüşmeyen ürün' );
	}
);

qrms_test(
	'sepetten çıkarma oranı küresel ekleme/çıkarma sayısından hesaplanır',
	function () {
		$gruplar = array(
			array(
				'ip_hash'    => 'a',
				'masa_no'    => 'm1',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'cart_add',
				'item_id'    => 7,
				'item_name'  => 'Künefe',
				'adet'       => 4,
			),
			array(
				'ip_hash'    => 'a',
				'masa_no'    => 'm1',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'cart_remove',
				'item_id'    => 7,
				'item_name'  => 'Künefe',
				'adet'       => 2,
			),
			array(
				'ip_hash'    => 'b',
				'masa_no'    => 'm2',
				'pencere'    => '2026-03-10 14',
				'event_type' => 'cart_remove',
				'item_id'    => 8,
				'item_name'  => 'Çay',
				'adet'       => 1,
			),
		);

		$sonuc = qrms_analitik_sepet_hesapla( $gruplar, 1, 0 );

		qrms_assert_same( 2, count( $sonuc['cikarilan'] ), 'iki ürün çıkarıldı' );
		qrms_assert_same( 'Künefe', $sonuc['cikarilan'][0]['ad'], 'daha çok çıkan üstte' );
		qrms_assert_same( 2, $sonuc['cikarilan'][0]['cikarma'], 'çıkarma' );
		qrms_assert_same( 4, $sonuc['cikarilan'][0]['ekleme'], 'ekleme' );
		qrms_assert_same( 2.0, $sonuc['cikarilan'][0]['oran'], '4/2=2' );
		qrms_assert_same( 'Çay', $sonuc['cikarilan'][1]['ad'], 'ikinci' );
		qrms_assert_same( 0, $sonuc['cikarilan'][1]['ekleme'], 'hiç eklenmeden çıkarılmış olabilir' );
		qrms_assert_same( 0.0, $sonuc['cikarilan'][1]['oran'], '0/1=0' );
	}
);

qrms_test(
	'engellenen sipariş ürüne toplanır; hatalar sıfırsa dağılım boştur',
	function () {
		$gruplar = array(
			array(
				'ip_hash'    => 'a',
				'masa_no'    => 'm1',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'order_blocked',
				'item_id'    => 3,
				'item_name'  => 'Izgara',
				'adet'       => 2,
			),
			array(
				'ip_hash'    => 'b',
				'masa_no'    => 'm2',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'order_blocked',
				'item_id'    => 3,
				'item_name'  => 'Izgara',
				'adet'       => 1,
			),
		);

		$sonuc = qrms_analitik_sepet_hesapla( $gruplar, 1, 0 );

		qrms_assert_same( 3, $sonuc['ozet']['blocked'], 'kaçırılan sipariş olayı' );
		qrms_assert_same( 1, count( $sonuc['engellenen'] ), 'tek ürün' );
		qrms_assert_same( 3, $sonuc['engellenen'][0]['siparis'], 'ürüne toplanır' );
		qrms_assert_same( 0, $sonuc['ozet']['failed'], 'hata yok' );
		qrms_assert_same( array(), $sonuc['hatalar'], 'dağılım basılmaz' );
	}
);

qrms_test(
	'sipariş hataları oturum bazlı zaman kovasına düşer; çok kalemli sipariş tek sayılır',
	function () {
		$gruplar = array(
			array(
				'ip_hash'    => 'a',
				'masa_no'    => 'm1',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'order_failed',
				'item_id'    => 1,
				'item_name'  => 'A',
				'adet'       => 1,
			),
			array(
				'ip_hash'    => 'a',
				'masa_no'    => 'm1',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'order_failed',
				'item_id'    => 2,
				'item_name'  => 'B',
				'adet'       => 1,
			),
			array(
				'ip_hash'    => 'c',
				'masa_no'    => 'm2',
				'pencere'    => '2026-03-11 08',
				'event_type' => 'order_failed',
				'item_id'    => 1,
				'item_name'  => 'A',
				'adet'       => 1,
			),
		);

		$gun = qrms_analitik_sepet_hesapla( $gruplar, 1, 0 );
		qrms_assert_same( 2, $gun['ozet']['failed'], 'iki oturum' );
		qrms_assert_same( 2, count( $gun['hatalar'] ), 'iki pencere (tek gün kırılımı)' );

		$ay = qrms_analitik_sepet_hesapla( $gruplar, 7, 0 );
		qrms_assert_same( 2, count( $ay['hatalar'] ), 'güne indirgenir' );
		qrms_assert_same( '2026-03-10', $ay['hatalar'][0]['label'], 'ilk gün' );
		qrms_assert_same( 1, $ay['hatalar'][0]['sayi'], 'o gün bir oturum' );
	}
);

qrms_test(
	'boş girdi "toplanmaya yeni başlandı" durumudur, hata değil',
	function () {
		$sonuc = qrms_analitik_sepet_hesapla( array(), 1, 0 );

		qrms_assert_true( $sonuc['bos'], 'boş bayrağı' );
		qrms_assert_same( 0, $sonuc['ozet']['cart_add'], 'sıfır' );
		qrms_assert_same( array(), $sonuc['terk_urun'], 'tablo boş' );
	}
);

qrms_test(
	'sepet grupları tek GROUP BY, indeksli aralık, istek içi önbellekli',
	function () {
		$wpdb            = qrms_sayan_wpdb();
		$wpdb->results[] = array(
			array(
				'ip_hash'       => 'x',
				'masa_no'       => 'masa-1',
				'pencere'       => '2026-03-10 12',
				'event_type'    => 'cart_add',
				'item_id'       => 10,
				'item_name'     => 'Burger',
				'category_name' => 'Ana',
				'adet'          => 1,
				'ilk'           => '2026-03-10 12:01:00',
				'son'           => '2026-03-10 12:01:00',
			),
		);

		QRMS_Analitik::sepet_onbellegini_temizle();
		qrms_analitik_onbellek_sifirla();

		$grup = QRMS_Analitik::sepet_olay_gruplari( '2026-03-10 00:00:00', '2026-03-10 23:59:59', 'masa-1' );

		qrms_assert_same( 1, count( $wpdb->queries ), 'tek sorgu' );
		qrms_assert_contains( 'created_at BETWEEN', $wpdb->queries[0], 'idx_td/idx_date aralığı' );
		qrms_assert_contains( "event_type IN ('cart_add','cart_remove','order_sent','order_failed','order_blocked')", $wpdb->queries[0], 'beş olay tipi' );
		qrms_assert_contains( 'GROUP BY ip_hash, masa_no', $wpdb->queries[0], 'oturum gruplaması SQL\'de' );
		qrms_assert_contains( "masa_no = 'masa-1'", $wpdb->queries[0], 'masa filtresi idx_masa_td' );
		qrms_assert_false( false !== strpos( $wpdb->queries[0], 'LIMIT %d OFFSET' ), 'OFFSET yok' );

		// İkinci çağrı aynı istekte sorgu açmaz.
		QRMS_Analitik::sepet_olay_gruplari( '2026-03-10 00:00:00', '2026-03-10 23:59:59', 'masa-1' );
		qrms_assert_same( 1, count( $wpdb->queries ), 'istek içi önbellek' );

		qrms_analitik_onbellek_sifirla();
		$veri = qrms_analitik_sepet_verisi(
			array(
				'bas' => '2026-03-10 00:00:00',
				'bit' => '2026-03-10 23:59:59',
				'gun' => 1,
			),
			'masa-1'
		);
		qrms_assert_same( 1, $veri['ozet']['cart_add'], 'hesaplama gruplardan' );
		qrms_assert_same( 1, count( $wpdb->queries ), 'veri fonksiyonu yeni sorgu açmaz' );

		qrms_assert_same( 1, count( $grup ), 'grup satırı' );
	}
);

qrms_test(
	'sepet CSV\'si sistem ham/ürün/masa indirmesinden ayrıdır',
	function () {
		$sinif = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php' );
		$sayfa = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/sepet-sayfasi.php' );
		$sistem = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/sistem-sayfasi.php' );

		qrms_assert_contains( "if ( 'sepet' === \$kategori )", $sinif, 'ayrı kategori anahtarı' );
		qrms_assert_contains( 'csv_sepet', $sinif, 'sepet CSV üreticisi' );
		qrms_assert_contains( "'kategori' => 'sepet'", $sayfa, 'sayfa kendi indirmesini ister' );
		qrms_assert_false( false !== strpos( $sistem, "'kategori' => 'sepet'" ), 'sistem sayfası sepet CSV üretmez' );
		qrms_assert_contains( 'qr-analitik-sepet-', $sinif, 'dosya adı çakışmaz' );
	}
);

qrms_test(
	'sepet sayfası paylaşılan filtreyi kullanır ve Ürünüm Yok bağlantısı filtreyi taşır',
	function () {
		$sayfa = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/sepet-sayfasi.php' );
		$js    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik-sepet.js' );
		$hub   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/hub-sayfasi.php' );

		qrms_assert_contains( "qrms_analitik_filtre_cubugu( 'qrms-an-sepet' )", $sayfa, 'paylaşılan filtre' );
		qrms_assert_contains( 'qrms-rm-urunum-yok', $sayfa, 'Ürünüm Yok bağlantısı' );
		qrms_assert_contains( 'QRMS_Analitik_Filtre::args()', $sayfa, 'filtre taşınır' );
		qrms_assert_contains( 'qrms-analiz-ayarlar', $sayfa, 'Firebase ayar slug yedek' );
		qrms_assert_contains( 'qrms_analitik_sepet', $js, 'AJAX ucu' );
		qrms_assert_contains( 'hataPanel.hidden', $js, 'sıfır hatada bölüm basılmaz' );
		qrms_assert_contains( 'justStarted', $js, 'yeni başladı boş durumu' );
		qrms_assert_contains( "'hazir'  => true", $hub, 'sepet kartı yakında değil' );
		qrms_assert_false(
			false !== strpos( $hub, 'function qrms_analitik_sayfa_sepet' ),
			'placeholder fonksiyon hub\'dan kalktı'
		);
	}
);

qrms_test(
	'etkileşim hesaplaması saf fonksiyondur: ödül dönüşümü sıfıra bölünmez',
	function () {
		$bos = qrms_analitik_etkilesim_hesapla( array() );

		qrms_assert_true( $bos['bos'], 'boş girdi' );
		qrms_assert_same( 0, $bos['ozet']['reward_oran'], 'üretilen yokken oran 0' );

		$sonuc = qrms_analitik_etkilesim_hesapla(
			array(
				array(
					'event_type' => 'chatbot_message',
					'item_name'  => '',
					'adet'       => 4,
				),
				array(
					'event_type' => 'form_submit',
					'item_name'  => 'Rezervasyon',
					'adet'       => 3,
				),
				array(
					'event_type' => 'form_submit',
					'item_name'  => 'İletişim',
					'adet'       => 1,
				),
				array(
					'event_type' => 'reward_issued',
					'item_name'  => '',
					'adet'       => 10,
				),
				array(
					'event_type' => 'reward_redeemed',
					'item_name'  => '',
					'adet'       => 4,
				),
				array(
					'event_type' => 'lang_switch',
					'item_name'  => 'en',
					'adet'       => 6,
				),
				array(
					'event_type' => 'lang_switch',
					'item_name'  => 'ar',
					'adet'       => 2,
				),
				array(
					'event_type' => 'gallery_view',
					'item_name'  => '',
					'adet'       => 5,
				),
			)
		);

		qrms_assert_false( $sonuc['bos'], 'dolu girdi' );
		qrms_assert_same( 4, $sonuc['ozet']['chatbot'], 'chatbot' );
		qrms_assert_same( 4, $sonuc['ozet']['form'], 'form toplam' );
		qrms_assert_same( 40, $sonuc['ozet']['reward_oran'], '4/10 dönüşüm' );
		qrms_assert_same( 'Rezervasyon', $sonuc['formlar'][0]['ad'], 'form sırası adet' );
		qrms_assert_same( 8, $sonuc['ozet']['lang'], 'dil toplam' );
		qrms_assert_same( 'en', $sonuc['diller'][0]['kod'], 'en önde' );
		qrms_assert_same( 75, $sonuc['diller'][0]['pay'], 'en payı' );
		qrms_assert_same( 5, $sonuc['ozet']['gallery'], 'galeri' );
	}
);

qrms_test(
	'etkileşim CSV\'si ayrı kategori anahtarı kullanır',
	function () {
		$sinif = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php' );
		$sayfa = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/etkilesim-sayfasi.php' );

		qrms_assert_contains( "if ( 'etkilesim' === \$kategori )", $sinif, 'ayrı kategori anahtarı' );
		qrms_assert_contains( 'csv_etkilesim', $sinif, 'etkileşim CSV üreticisi' );
		qrms_assert_contains( "'kategori' => 'etkilesim'", $sayfa, 'sayfa kendi indirmesini ister' );
		qrms_assert_contains( 'qr-analitik-etkilesim-', $sinif, 'dosya adı çakışmaz' );
	}
);

qrms_test(
	'etkileşim sayfası paylaşılan filtreyi kullanır ve bağlı olmayan bölümü basmaz',
	function () {
		$sayfa = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/etkilesim-sayfasi.php' );
		$js    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik-etkilesim.js' );
		$hub   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/hub-sayfasi.php' );

		qrms_assert_contains( "qrms_analitik_filtre_cubugu( 'qrms-an-etkilesim' )", $sayfa, 'paylaşılan filtre' );
		qrms_assert_contains( 'qrms_analitik_etkilesim', $js, 'AJAX ucu' );
		qrms_assert_contains( 'justStarted', $js, 'yeni başladı boş durumu' );
		qrms_assert_contains( "'hazir'    => true", $hub, 'etkileşim kartı yakında değil' );
		qrms_assert_false(
			false !== strpos( $hub, 'function qrms_analitik_sayfa_etkilesim' ),
			'placeholder fonksiyon hub\'dan kalktı'
		);
		qrms_assert_contains( 'moduller', $hub, 'OR lisans süzmesi' );
	}
);

qrms_test(
	'etkileşim bağlı modüller pasifken sayfa yine kayıtlıdır ama hub kartı yoktur',
	function () {
		update_option( 'qrms_active_modules', array( 'qr-analiz' ) );
		QRMS_Analitik_Filtre::sifirla();

		$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] = array(
			qrms_submenu_satiri( 'İstatistikler', QRMS_Admin::get_module_page_slug( 'qr-analiz' ) ),
		);

		qrms_module_qr_analiz_admin_menu();

		$sluglar = array_map(
			function ( $item ) {
				return $item['slug'];
			},
			$GLOBALS['qrms_test']['submenus']
		);

		qrms_assert_true( in_array( 'qrms-an-etkilesim', $sluglar, true ), 'doğrudan URL çalışsın diye kayıtlı' );

		$etk_kart = false;
		foreach ( qrms_module_qr_analiz_hub_kartlari() as $kart ) {
			if ( false !== strpos( $kart['url'], 'page=qrms-an-etkilesim' ) ) {
				$etk_kart = true;
			}
		}

		qrms_assert_false( $etk_kart, 'hub kartı basılmaz' );

		ob_start();
		qrms_analitik_sayfa_etkilesim();
		$html = ob_get_clean();

		qrms_assert_contains( 'Bu kategori bu lisansta kapalı', $html, 'anlamlı mesaj' );
		qrms_assert_false( false !== strpos( $html, 'id="qrms-an-etk-chatbot-cards"' ), 'boş tablo yok' );

		update_option( 'qrms_active_modules', array() );
	}
);

qrms_test(
	'açılış hesaplaması saf fonksiyondur: splash_view sıfırken oran 0',
	function () {
		$bos = qrms_analitik_acilis_hesapla( array() );

		qrms_assert_true( $bos['bos'], 'boş girdi' );
		qrms_assert_same( 0, $bos['ozet']['menu_oran'], 'gösterim yokken menü oranı 0' );
		qrms_assert_same( 0, $bos['ozet']['atla_oran'], 'gösterim yokken atlanma 0' );

		$sifir_payda = qrms_analitik_acilis_hesapla(
			array(
				array(
					'event_type' => 'splash_action',
					'item_name'  => 'menu',
					'adet'       => 3,
				),
			)
		);

		qrms_assert_same( 0, $sifir_payda['ozet']['view'], 'gösterim yok' );
		qrms_assert_same( 3, $sifir_payda['ozet']['menu'], 'eylem sayılır' );
		qrms_assert_same( 0, $sifir_payda['ozet']['menu_oran'], 'payda sıfır: bölme yok' );

		$sonuc = qrms_analitik_acilis_hesapla(
			array(
				array(
					'event_type' => 'splash_view',
					'item_name'  => '',
					'adet'       => 10,
				),
				array(
					'event_type' => 'splash_action',
					'item_name'  => 'menu',
					'adet'       => 4,
				),
				array(
					'event_type' => 'splash_action',
					'item_name'  => 'atla',
					'adet'       => 2,
				),
				array(
					'event_type' => 'splash_action',
					'item_name'  => 'wifi',
					'adet'       => 3,
				),
				array(
					'event_type' => 'splash_action',
					'item_name'  => 'sosyal',
					'adet'       => 1,
				),
			)
		);

		qrms_assert_false( $sonuc['bos'], 'dolu girdi' );
		qrms_assert_same( 10, $sonuc['ozet']['view'], 'gösterim' );
		qrms_assert_same( 40, $sonuc['ozet']['menu_oran'], '4/10 menü' );
		qrms_assert_same( 20, $sonuc['ozet']['atla_oran'], '2/10 atla' );
		qrms_assert_same( 'wifi', $sonuc['butonlar'][0]['kod'], 'wifi ilk buton' );
		qrms_assert_same( 3, $sonuc['butonlar'][0]['adet'], 'wifi adet' );
		qrms_assert_same( 30, $sonuc['butonlar'][0]['pay'], 'wifi payı gösterime' );
	}
);

qrms_test(
	'açılış CSV\'si ayrı kategori anahtarı kullanır',
	function () {
		$sinif = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php' );
		$sayfa = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/acilis-sayfasi.php' );

		qrms_assert_contains( "if ( 'acilis' === \$kategori )", $sinif, 'ayrı kategori anahtarı' );
		qrms_assert_contains( 'csv_acilis', $sinif, 'açılış CSV üreticisi' );
		qrms_assert_contains( "'kategori' => 'acilis'", $sayfa, 'sayfa kendi indirmesini ister' );
		qrms_assert_contains( 'qr-analitik-acilis-', $sinif, 'dosya adı çakışmaz' );
	}
);

qrms_test(
	'açılış sayfası paylaşılan filtreyi kullanır ve sıfıra bölmez',
	function () {
		$sayfa = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/acilis-sayfasi.php' );
		$js    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik-acilis.js' );
		$hub   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/hub-sayfasi.php' );

		qrms_assert_contains( "qrms_analitik_filtre_cubugu( 'qrms-an-acilis' )", $sayfa, 'paylaşılan filtre' );
		qrms_assert_contains( 'qrms_analitik_acilis', $js, 'AJAX ucu' );
		qrms_assert_contains( 'justStarted', $js, 'yeni başladı boş durumu' );
		qrms_assert_contains( "'hazir'  => true", $hub, 'açılış kartı yakında değil' );
		qrms_assert_false(
			false !== strpos( $hub, 'function qrms_analitik_sayfa_acilis' ),
			'placeholder fonksiyon hub\'dan kalktı'
		);
		qrms_assert_contains( '$ozet[\'view\'] > 0', $sayfa, 'payda sıfır koruması' );
	}
);

qrms_test(
	'açılış modülü pasifken sayfa yine kayıtlıdır ama hub kartı yoktur',
	function () {
		update_option( 'qrms_active_modules', array( 'qr-analiz' ) );
		QRMS_Analitik_Filtre::sifirla();

		$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] = array(
			qrms_submenu_satiri( 'İstatistikler', QRMS_Admin::get_module_page_slug( 'qr-analiz' ) ),
		);

		qrms_module_qr_analiz_admin_menu();

		$sluglar = array_map(
			function ( $item ) {
				return $item['slug'];
			},
			$GLOBALS['qrms_test']['submenus']
		);

		qrms_assert_true( in_array( 'qrms-an-acilis', $sluglar, true ), 'doğrudan URL çalışsın diye kayıtlı' );

		$acilis_kart = false;
		foreach ( qrms_module_qr_analiz_hub_kartlari() as $kart ) {
			if ( false !== strpos( $kart['url'], 'page=qrms-an-acilis' ) ) {
				$acilis_kart = true;
			}
		}

		qrms_assert_false( $acilis_kart, 'hub kartı basılmaz' );

		ob_start();
		qrms_analitik_sayfa_acilis();
		$html = ob_get_clean();

		qrms_assert_contains( 'Bu kategori bu lisansta kapalı', $html, 'anlamlı mesaj' );
		qrms_assert_false( false !== strpos( $html, 'id="qrms-an-acilis-cards"' ), 'boş tablo yok' );

		update_option( 'qrms_active_modules', array() );
	}
);

qrms_test(
	'detay modalı açılışı ayrı olaydır ve tıklama sıfırken oran 0',
	function () {
		$frontend = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/rma-frontend.js' );
		$modal    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/rma-detail-modal.js' );
		$urun     = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/urunler-sayfasi.php' );
		$js       = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik-urunler.js' );

		qrms_assert_contains( "yaz('item_detail_open'", $frontend, 'ana menü modalı' );
		qrms_assert_contains( 'rmaAnalitikDetay(id)', $frontend, 'gösterimde yazılır' );
		qrms_assert_contains( "yaz('item_detail_open'", $modal, 'vitrin/slider modalı' );
		qrms_assert_false( false !== strpos( $urun, 'bilinçli olarak YOKTUR' ), 'Faz 3 yorumu kalktı' );
		qrms_assert_contains( 'qrms-an-detay-cards', $urun, 'ürünler bölümü' );
		qrms_assert_contains( 'justStartedDetail', $js, 'yeni başladı boş durumu' );

		$bos = qrms_analitik_urun_detay_hesapla( array() );
		qrms_assert_true( $bos['bos'], 'açılış yok' );
		qrms_assert_same( 0, $bos['oran'], 'payda sıfır' );

		$oran = qrms_analitik_urun_detay_hesapla(
			array(
				array(
					'event_type' => 'product_click',
					'item_name'  => 'A',
					'adet'       => 4,
				),
				array(
					'event_type' => 'item_detail_open',
					'item_name'  => 'A',
					'adet'       => 6,
				),
			)
		);

		qrms_assert_same( 4, $oran['click'], 'tıklama' );
		qrms_assert_same( 6, $oran['open'], 'açılış' );
		qrms_assert_same( 150, $oran['oran'], 'önbellek yüzünden %100 üzeri' );
		qrms_assert_false( $oran['bos'], 'dolu' );
	}
);

qrms_test(
	'kategori dağılımı boş adları listeye KARIŞTIRMAZ, ayrı sayar',
	function () {
		$wpdb            = qrms_sayan_wpdb();
		$wpdb->results[] = array(
			array(
				'category_name' => 'Tatlılar',
				'toplam'        => 12,
				'urun_sayisi'   => 3,
				'tekil'         => 8,
			),
		);
		$wpdb->vars[] = 5;

		$sonuc = QRMS_Analitik::kategori_dagilimi( '2026-03-01 00:00:00', '2026-03-31 23:59:59' );

		qrms_assert_same( 1, count( $sonuc['satirlar'] ), 'yalnızca gerçek kategoriler' );
		qrms_assert_same( 'Tatlılar', $sonuc['satirlar'][0]['kategori'], 'kategori adı' );
		qrms_assert_same( 5, $sonuc['kategorisiz'], 'boş adlar ayrı sayılır' );

		// category_name yalnızca product_click olayında dolar; sorgu da
		// yalnızca ona bakar ve boş adları dışarıda bırakır.
		qrms_assert_contains( "event_type='product_click'", $wpdb->queries[0], 'yalnızca tıklama olayı' );
		qrms_assert_contains( "category_name <> ''", $wpdb->queries[0], 'boş adlar dışarıda' );

		// Aralık iki uçtan sınırlı: idx_td aralık taraması olarak kullanılır.
		qrms_assert_contains( 'created_at BETWEEN', $wpdb->queries[0], 'kapalı aralık' );
	}
);

qrms_test(
	'yeniden adlandırılmış kategori "eski ad" olarak işaretlenir',
	function () {
		$GLOBALS['qrms_test']['posts']      = array();
		$GLOBALS['qrms_test']['post_meta']  = array();
		$GLOBALS['qrms_test']['terms']      = array();
		// Taksonomide yalnızca "Tatlılar" var; "Tatlı" eski addır.
		$GLOBALS['qrms_test']['term_names'] = array( 'Tatlılar', 'Çorbalar' );

		qrms_analitik_onbellek_sifirla();

		$wpdb            = qrms_sayan_wpdb();
		$wpdb->results[] = array(); // sayaçlar
		$wpdb->results[] = array(
			array(
				'category_name' => 'Tatlılar',
				'toplam'        => 12,
				'urun_sayisi'   => 3,
				'tekil'         => 8,
			),
			array(
				'category_name' => 'Tatlı',
				'toplam'        => 4,
				'urun_sayisi'   => 1,
				'tekil'         => 3,
			),
		);
		$wpdb->vars[]    = 0;
		$wpdb->results[] = array(); // en çok tıklananlar

		$veri = qrms_analitik_urun_verisi(
			array(
				'bas' => '2026-03-01 00:00:00',
				'bit' => '2026-03-31 23:59:59',
				'gun' => 31,
			)
		);

		qrms_assert_false( $veri['kategoriler'][0]['eski_ad'], 'mevcut ad işaretlenmez' );
		qrms_assert_true( $veri['kategoriler'][1]['eski_ad'], 'artık olmayan ad işaretlenir' );

		// Sayı DÜZELTİLMEZ: iki satır da olduğu gibi kalır, yalnızca
		// etiketlenir (hangi eski adın hangi yeni ada karşılık geldiğini
		// söyleyen bir kayıt yok).
		qrms_assert_same( 2, count( $veri['kategoriler'] ), 'satırlar birleştirilmez' );
		qrms_assert_same( 4, $veri['kategoriler'][1]['toplam'], 'eski adın sayısı korunur' );
	}
);

qrms_test(
	'ortak JS yardımcıları TEK dosyada durur, iki ekranda kopyalanmaz',
	function () {
		$ortak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik-ortak.js' );
		$genel = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik-genel.js' );

		// Fetch sarmalayıcısı, tablo iskeleti, grafik çizimi ve filtre çubuğu:
		// hepsi ortakta tanımlı, Genel Bakış'ta YENİDEN tanımlı değil.
		foreach ( array( 'function post(', 'function tabloIskelet(', 'function grafikHtml(', 'function filtreKur(' ) as $fn ) {
			qrms_assert_contains( $fn, $ortak, $fn . ' ortakta' );
			qrms_assert_false( false !== strpos( $genel, $fn ), $fn . ' Genel Bakış\'ta kopyalanmadı' );
		}

		// Ekran ortağı kullanır ve yoksa sessizce durur.
		qrms_assert_contains( 'window.qrmsAnOrtak', $ortak, 'ortak ad alanı' );
		qrms_assert_contains( 'window.qrmsAnOrtak', $genel, 'Genel Bakış ortağı kullanır' );
	}
);

qrms_test(
	'Genel Bakış aynı kırılımı iki kez istemez',
	function () {
		// Aralık ve masa sayfa ömrü boyunca sabittir (değişmeleri sayfayı
		// yeniler); yalnızca kırılım değişir, o da önbelleğe alınır.
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik-genel.js' );

		qrms_assert_contains( 'state.onbellek[ state.kirilim ]', $js, 'kırılım önbelleği' );
	}
);

qrms_test(
	'kategori şeridi ve tablolar dar ekranda taşmaz',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/css/analitik.css' );

		// Şerit kırpılmaz, yatay kayar; chip'ler sıkışıp okunmaz olmaz.
		qrms_assert_contains( '.qrms-an-cats {', $css, 'kategori şeridi' );
		qrms_assert_contains( 'overflow-x: auto', $css, 'yatay kaydırma' );

		// Tablolar 660px altında karta döner (sütun başlığı hücrenin etiketi).
		qrms_assert_contains( 'max-width: 660px', $css, 'kart görünümü kırılımı' );
		qrms_assert_contains( 'content: attr( data-label )', $css, 'kart etiketi' );
	}
);

/* ---------------------------------------------------------------------------
 * 9a. Güvenlik Ayarı — sayfa kayıt defteri ve hub
 * ------------------------------------------------------------------------ */

// module.php dosya kapsamında yalnızca fonksiyon ve sabit tanımlar; stub
// ortamında yan etkisiz yüklenir.
