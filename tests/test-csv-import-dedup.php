<?php
/**
 * Ana CSV toplu ürün içe aktarımı — deduplication testleri.
 *
 * BULGU: handle_csv_import() her satır için koşulsuz wp_insert_post()
 * çağırıyordu; aynı CSV dosyası iki kez yüklendiğinde ürünler çoğalıyordu.
 * Düzeltme: döngüden önce tek sorguda "başlık+kategori anahtarı => ürün ID"
 * haritası kurulur (csv_import_existing_map()), eşleşen ürün wp_update_post()
 * ile güncellenir, eşleşmeyen satır wp_insert_post() ile açılır. Anahtara
 * kategori de dahildir (csv_dedup_key()): aynı adlı ama farklı kategorideki
 * ürünler yanlışlıkla birleşmez.
 *
 * import_title_map()/import_title_key() için kurulmuş olan gerçek desen
 * izlenir (bkz. tests/test-analiz-schema.php, "Döngü içi sorgular (N+1)"
 * bölümü): csv_dedup_key() saf bir fonksiyon olduğu için doğrudan çağrılıp
 * gerçek varlık/normalizasyon davranışı test edilir; wp_insert_post/
 * wp_update_post/$wpdb'ye bağımlı asıl akışın kablolanması ise (harita
 * döngüden önce kuruluyor mu, eşleşince update mi insert mi çağrılıyor,
 * dosya-içi tekrar ikinci ürün açmıyor mu) kaynak koda karşı doğrulanır —
 * tıpkı bu projede wp_insert_post/wp_update_post kullanan HER akışın test
 * edildiği yöntemle (bkz. test-banner.php, test-analiz-schema.php).
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-import-export.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-helpers.php';

if ( ! class_exists( 'RMA_Menu_Test_Import' ) ) {
	class RMA_Menu_Test_Import {
		use RMA_Import_Export_Trait;
		use RMA_Helpers_Trait;
	}
}

echo "\nAna CSV İçe Aktarımı — Deduplication (BULGU: aynı CSV iki kez yüklenince ürünler çoğalıyordu)\n";

/* ---------------------------------------------------------------------
   1) csv_dedup_key() — saf fonksiyon, gerçek normalizasyon davranışı.
--------------------------------------------------------------------- */

qrms_test(
	'csv_dedup_key: aynı başlık ve kategori seti — harf büyüklüğü/boşluk/sıra farkı sonucu değiştirmez',
	function () {
		$menu = new RMA_Menu_Test_Import();

		$a = $menu->csv_dedup_key( '  Mercimek Çorbası  ', 'Çorbalar, Ana Yemek' );
		$b = $menu->csv_dedup_key( 'mercimek çorbası', 'ana yemek , çorbalar' );

		qrms_assert_same( $a, $b, 'başlık ve kategori normalizasyonu MySQL harmanlaması gibi büyük/küçük harf ve boşluk ayırmaz' );
	}
);

qrms_test(
	'csv_dedup_key: aynı dosyadaki tekrar eden kategori adı anahtarı değiştirmez',
	function () {
		$menu = new RMA_Menu_Test_Import();

		$a = $menu->csv_dedup_key( 'Kola', 'İçecekler, İçecekler' );
		$b = $menu->csv_dedup_key( 'Kola', 'İçecekler' );

		qrms_assert_same( $a, $b, 'kategori setinde tekrar eden ad tek kez sayılır' );
	}
);

qrms_test(
	'csv_dedup_key: AYNI başlık FARKLI kategoride farklı anahtar üretir — yanlış birleşme engellenir',
	function () {
		$menu = new RMA_Menu_Test_Import();

		$corba  = $menu->csv_dedup_key( 'Çorba', 'Çorbalar' );
		$tatli  = $menu->csv_dedup_key( 'Çorba', 'Tatlılar' );

		qrms_assert_false(
			$corba === $tatli,
			'aynı adlı ama farklı kategorideki ürünler aynı anahtara düşmüyor'
		);
	}
);

qrms_test(
	'csv_dedup_key: farklı başlık aynı kategoride farklı anahtar üretir',
	function () {
		$menu = new RMA_Menu_Test_Import();

		$a = $menu->csv_dedup_key( 'Adana Kebap', 'Ana Yemek' );
		$b = $menu->csv_dedup_key( 'Urfa Kebap', 'Ana Yemek' );

		qrms_assert_false( $a === $b, 'başlık farklıysa anahtar da farklı kalır' );
	}
);

qrms_test(
	'csv_dedup_key: kategori sütunu boş olan satır, kategorili satırdan ayrı bir anahtar taşır',
	function () {
		$menu = new RMA_Menu_Test_Import();

		$bos       = $menu->csv_dedup_key( 'Su', '' );
		$bos_yine  = $menu->csv_dedup_key( '  su  ', '   ' );
		$kategorili = $menu->csv_dedup_key( 'Su', 'İçecekler' );

		qrms_assert_same( $bos, $bos_yine, 'boş kategori sütunu deterministik bir anahtar üretir' );
		qrms_assert_false( $bos === $kategorili, 'kategorisiz ve kategorili aynı başlık farklı anahtara düşer' );
	}
);

/* ---------------------------------------------------------------------
   2) Kaynak kod doğrulaması — asıl akışın kablolanması.
   (wp_insert_post/wp_update_post/$wpdb'ye bağımlı olduğu için bu projede
   HER YERDE izlenen yöntem: bkz. test-banner.php "wp_update_post" testleri,
   test-analiz-schema.php "$baslik_haritasi = $this->import_title_map(" testi.)
--------------------------------------------------------------------- */

qrms_test(
	'CSV içe aktarımı artık koşulsuz wp_insert_post çağırmıyor — eşleşen ürün varsa güncelleniyor',
	function () {
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-import-export.php'
		);

		$fonksiyon_basi = strpos( $kaynak, 'function handle_csv_import()' );
		$fonksiyon_sonu = strpos( $kaynak, "\n    /**\n     * CSV sütunları" );
		qrms_assert_true( false !== $fonksiyon_basi && false !== $fonksiyon_sonu, 'handle_csv_import() sınırları bulunuyor' );

		$govde = substr( $kaynak, $fonksiyon_basi, $fonksiyon_sonu - $fonksiyon_basi );

		qrms_assert_contains( '$existing_map = $this->csv_import_existing_map(', $govde, 'eşleştirme haritası kuruluyor' );
		qrms_assert_contains( 'if ( $hedef_id ) {', $govde, 'eşleşen ürün için ayrı dal var' );
		qrms_assert_contains( 'wp_update_post( $postarr )', $govde, 'eşleşen ürün wp_update_post ile güncelleniyor' );
		qrms_assert_contains( '$pid = wp_insert_post( $postarr )', $govde, 'eşleşmeyen satır hâlâ yeni ürün açabiliyor' );

		// Harita, satır işleme döngüsünden ÖNCE kurulmalı.
		$harita_konumu = strpos( $govde, '$existing_map = $this->csv_import_existing_map(' );
		$dongu_konumu  = strpos( $govde, 'foreach ( $parsed_rows as $d ) {' );
		qrms_assert_true( $harita_konumu < $dongu_konumu, 'harita döngüden önce kuruluyor' );
	}
);

qrms_test(
	'CSV içe aktarımı: aynı dosyada tekrar eden satır ikinci bir ürün açmıyor',
	function () {
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-import-export.php'
		);

		// Satır başarıyla işlendikten sonra üretilen/güncellenen ID haritaya
		// geri yazılmalı; aksi hâlde aynı dosyadaki ikinci aynı satır tekrar
		// eşleşmeyi kaçırıp yeni bir ürün açar.
		qrms_assert_contains(
			'$existing_map[ $anahtar ] = $pid;',
			$kaynak,
			'yeni/güncellenen kayıt haritaya geri yazılıyor'
		);

		$yazma_konumu = strpos( $kaynak, '$existing_map[ $anahtar ] = $pid;' );
		$kontrol_konumu = strpos( $kaynak, 'if ( $pid && ! is_wp_error( $pid ) ) {' );
		qrms_assert_true( $kontrol_konumu < $yazma_konumu, 'geri yazma yalnızca başarılı işlemden sonra olur' );
	}
);

qrms_test(
	'CSV içe aktarımı: eşleştirme anahtarı kategori sütununu (d[4]) da kullanıyor',
	function () {
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-import-export.php'
		);

		qrms_assert_contains(
			"\$this->csv_dedup_key( \$title, \$d[4] ?? '' )",
			$kaynak,
			'anahtar kategori sütunuyla birlikte hesaplanıyor'
		);
	}
);

qrms_test(
	'csv_import_existing_map(): satır başına arama sorgusu yok, tek IN(...) sorgusu ve kategori önbelleklemesi var',
	function () {
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-import-export.php'
		);

		$fonksiyon_basi = strpos( $kaynak, 'private function csv_import_existing_map(' );
		qrms_assert_true( false !== $fonksiyon_basi, 'csv_import_existing_map() tanımlı' );

		$fonksiyon_sonu = strpos( $kaynak, 'private function import_title_map(' );
		$govde = substr( $kaynak, $fonksiyon_basi, $fonksiyon_sonu - $fonksiyon_basi );

		qrms_assert_contains( 'post_title IN (', $govde, 'tek sorguda toplu arama' );
		qrms_assert_contains( 'array_chunk(', $govde, 'çok uzun liste parçalara bölünüyor' );
		qrms_assert_contains( "update_object_term_cache( \$post_ids, 'rma_menu_item' )", $govde, 'kategori terimleri tek sorguda önbelleğe alınıyor (N+1 yok)' );
	}
);

qrms_test(
	'CSV içe aktarımı: fiyat doğrulama akışına dokunulmadı',
	function () {
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-import-export.php'
		);

		qrms_assert_contains(
			"\$gecerli_fiyat = \$this->sanitize_price_value( \$d[3] ?? '' );",
			$kaynak,
			'fiyat hâlâ sanitize_price_value() üzerinden doğrulanıyor'
		);
	}
);

/* =====================================================================
   BULGU-AUDIT-03 — Ana CSV içe aktarımında fiyat üst sınırı aşımı için de
   admin geri bildirimi yoktu.
   handle_csv_import() geçersiz (biçim/negatif/üst sınır) fiyatlı satırları
   sessizce boş fiyatla içe aktarıyor, kaç satırın etkilendiğine dair hiçbir
   sayaç/uyarı admin ekranına yansımıyordu. Düzeltme: $fiyat_gecersiz sayacı
   + ilk 20 satır numarası redirect ile taşınır, render_csv_import_page()
   bunu güvenli (esc_html + intval süzülmüş) bir uyarı olarak basar.
   wp_insert_post()/wp_update_post()'a bağımlı asıl akış bu projede izlenen
   yöntemle (kaynak koda karşı) doğrulanır; render_csv_import_page() ise
   wp_insert_post'a bağımlı olmadığı için GERÇEKTEN çalıştırılıp çıktısı
   test edilir.
===================================================================== */

echo "\nAna CSV İçe Aktarımı — Geçersiz Fiyat Admin Geri Bildirimi (BULGU-AUDIT-03)\n";

qrms_test(
	'kaynak kod: geçersiz fiyat satırları sayılıyor, satır numarası saklanıyor',
	function () {
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-import-export.php'
		);

		qrms_assert_contains( "\$d['_rma_satir_no'] = \$i + 1;", $kaynak, 'gerçek dosya satır numarası saklanıyor' );
		qrms_assert_contains( "\$out['fiyat_gecersiz']++;", $kaynak, 'geçersiz fiyatta sayaç artırılıyor' );
		qrms_assert_contains( "\$out['fiyat_satirlar'][] = \$i + 1;", $kaynak, 'satır numarası listeye ekleniyor' );

		$collect_basi = strpos( $kaynak, 'function csv_collect_import_rows(' );
		$collect_sonu = strpos( $kaynak, 'function csv_import_result_notice(' );
		$collect      = substr( $kaynak, $collect_basi, $collect_sonu - $collect_basi );

		$sayac_konumu    = strpos( $collect, "\$out['fiyat_gecersiz']++;" );
		$sanitize_konumu = strpos( $collect, "\$gecerli_fiyat      = \$this->sanitize_price_value( \$d[3] ?? '' );" );
		qrms_assert_true( false !== $sanitize_konumu && $sanitize_konumu < $sayac_konumu, 'sayaç yalnızca doğrulamadan SONRA artırılıyor' );
	}
);

qrms_test(
	'kaynak kod: geçersiz fiyatlı satır yazılmaz, geçerli fiyat kaydedilir',
	function () {
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-import-export.php'
		);

		$fonksiyon_basi = strpos( $kaynak, 'function handle_csv_import()' );
		$fonksiyon_sonu = strpos( $kaynak, "\n    /**\n     * CSV sütunları" );
		$govde          = substr( $kaynak, $fonksiyon_basi, $fonksiyon_sonu - $fonksiyon_basi );

		$fiyat_blok_basi = strpos( $govde, '$gecerli_fiyat = $this->sanitize_price_value(' );
		$fiyat_blok_sonu = strpos( $govde, '$meta_map = [' );
		$fiyat_blok      = substr( $govde, $fiyat_blok_basi, $fiyat_blok_sonu - $fiyat_blok_basi );

		qrms_assert_contains( 'if ( null === $gecerli_fiyat ) {', $fiyat_blok, 'geçersiz fiyat ayrı dal' );
		qrms_assert_contains( 'continue;', $fiyat_blok, 'geçersiz fiyat satırı yazılmadan atlanır' );
		qrms_assert_contains( "update_post_meta( \$pid, 'rma_price', \$gecerli_fiyat );", $fiyat_blok, 'geçerli fiyat hâlâ doğrudan kaydediliyor' );
		qrms_assert_false(
			false !== strpos( $fiyat_blok, "update_post_meta( \$pid, 'rma_price', '' )" ),
			'geçersiz fiyat boş meta olarak yazılmaz'
		);

		$continue_konumu = strpos( $govde, 'if ( null === $gecerli_fiyat ) {' );
		$insert_konumu   = strpos( $govde, 'wp_insert_post( $postarr )' );
		qrms_assert_true( $continue_konumu < $insert_konumu, 'fiyat reddi insert öncesinde' );
	}
);

qrms_test(
	'kaynak kod: redirect geçersiz fiyat sayacını ve satır listesini admin ekranına taşıyor',
	function () {
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-import-export.php'
		);

		qrms_assert_contains( "\$redirect_args['rma_csv_fiyat_gecersiz'] = \$fiyat_gecersiz;", $kaynak, 'sayaç redirect argümanına ekleniyor' );
		qrms_assert_contains( "\$redirect_args['rma_csv_fiyat_satirlar'] = implode( ',', \$fiyat_gecersiz_satirlar );", $kaynak, 'satır listesi redirect argümanına ekleniyor' );
		qrms_assert_contains( "if ( \$fiyat_gecersiz > 0 ) {", $kaynak, 'sayaç sıfırken redirect kirletilmiyor' );
	}
);

if ( ! class_exists( 'RMA_Test_CSV_Page_Harness' ) ) {
	class RMA_Test_CSV_Page_Harness {
		use RMA_Import_Export_Trait;
		use RMA_Helpers_Trait;
	}
}

qrms_test(
	'render_csv_import_page(): geçersiz fiyat yokken hiçbir uyarı basılmaz (regresyon)',
	function () {
		$h = new RMA_Test_CSV_Page_Harness();
		$_GET = array(
			'imported'      => 5,
			'rma_csv_sonuc' => 'ok',
		);

		ob_start();
		$h->render_csv_import_page();
		$html = ob_get_clean();

		qrms_assert_contains( '<strong>5</strong> ürün başarıyla aktarıldı.', $html, 'mevcut başarı bildirimi bozulmadı' );
		qrms_assert_false( false !== strpos( $html, 'notice-warning' ), 'geçersiz fiyat yokken uyarı basılmaz' );
		qrms_assert_contains( 'updated', $html, 'tam başarı yeşil bildiridir' );
	}
);

qrms_test(
	'render_csv_import_page(): kalıntı imported=1 tek başına başarı basmaz (RM-003)',
	function () {
		$h    = new RMA_Test_CSV_Page_Harness();
		$_GET = array( 'imported' => 1 );

		ob_start();
		$h->render_csv_import_page();
		$html = ob_get_clean();

		qrms_assert_false( false !== strpos( $html, 'ürün aktarıldı' ), 'query string kalıntısı yeşil başarı üretmez' );
		qrms_assert_false( false !== strpos( $html, 'updated' ), 'kalıntıda success notice yok' );
	}
);

qrms_test(
	'render_csv_import_page(): geçersiz fiyat sayacı ve satır numaraları güvenli biçimde basılır',
	function () {
		$h    = new RMA_Test_CSV_Page_Harness();
		$_GET = array(
			'imported'                 => 9,
			'rma_csv_sonuc'            => 'partial',
			'rma_csv_hatali'           => 3,
			'rma_csv_fiyat_gecersiz'   => 3,
			'rma_csv_fiyat_satirlar'   => '2,5,9',
		);

		ob_start();
		$h->render_csv_import_page();
		$html = ob_get_clean();

		qrms_assert_contains( '<strong>9</strong> ürün aktarıldı, <strong>3</strong> satır hata nedeniyle atlandı.', $html, 'kısmi sonuç doğru sayıları gösterir' );
		qrms_assert_contains( 'notice-warning', $html, 'geçersiz fiyat uyarısı basıldı' );
		qrms_assert_contains( '<strong>3</strong> satırda fiyat geçersiz', $html, 'sayaç doğru gösteriliyor' );
		qrms_assert_contains( 'Etkilenen sat', $html, 'satır numaraları listeleniyor' );
		qrms_assert_contains( '2, 5, 9', $html, 'satır numaraları doğru sırayla basılıyor' );
		qrms_assert_false( false !== strpos( $html, 've diğerleri' ), 'tüm satırlar listelendiğinde "ve diğerleri" eklenmez' );
	}
);

qrms_test(
	'render_csv_import_page(): 20\'den fazla etkilenen satırda "ve diğerleri" eklenir',
	function () {
		$h    = new RMA_Test_CSV_Page_Harness();
		$_GET = array(
			'rma_csv_fiyat_gecersiz' => 25,
			'rma_csv_fiyat_satirlar' => implode( ',', range( 2, 21 ) ), // yalnızca ilk 20 satır saklanır
		);

		ob_start();
		$h->render_csv_import_page();
		$html = ob_get_clean();

		qrms_assert_contains( '<strong>25</strong> satırda fiyat geçersiz', $html, 'gerçek toplam sayı gösteriliyor' );
		qrms_assert_contains( 've diğerleri', $html, 'listelenmeyen satırlar için özet eklenir' );
	}
);

qrms_test(
	'render_csv_import_page(): satır parametresine enjekte edilen HTML/JS süzülür (XSS güvenliği)',
	function () {
		$h    = new RMA_Test_CSV_Page_Harness();
		$_GET = array(
			'rma_csv_fiyat_gecersiz' => 2,
			'rma_csv_fiyat_satirlar' => '5,<script>alert(1)</script>',
		);

		ob_start();
		$h->render_csv_import_page();
		$html = ob_get_clean();

		qrms_assert_false( false !== strpos( $html, '<script>' ), 'ham script etiketi çıktıya sızmaz' );
		qrms_assert_contains( '<strong>2</strong> satırda fiyat geçersiz', $html, 'sayaç yine de doğru basılır' );
	}
);

echo "\nAna CSV İçe Aktarımı — RM-003 yanıltıcı başarı mesajı\n";

qrms_test(
	'RM-003: geçerli örnek CSV tek satır olarak kabul edilir (TEST 1)',
	function () {
		$h       = new RMA_Test_CSV_Page_Harness();
		$columns = $h->get_csv_columns();
		$header  = implode( ',', array_map( static function ( $col ) {
			return $col[0];
		}, $columns ) );
		$row     = implode( ',', array_map( static function ( $col ) {
			return $col[2];
		}, $columns ) );

		$hazir = $h->csv_collect_import_rows( $header . "\n" . $row );
		$ozet  = $h->csv_import_result_notice( count( $hazir['rows'] ), $hazir['hatali'], $hazir['atlanan'], $hazir['error'] );

		qrms_assert_same( 0, $hazir['error'], 'başlık kabul edilir' );
		qrms_assert_same( 1, count( $hazir['rows'] ), 'bir ürün satırı' );
		qrms_assert_same( 0, $hazir['hatali'], 'hata yok' );
		qrms_assert_same( 'ok', $ozet['sonuc'], 'tam başarı' );
		qrms_assert_same( '1 ürün başarıyla aktarıldı.', $ozet['message'], 'sayı doğru' );
	}
);

qrms_test(
	'RM-003: Başlık kolonu yoksa import fail olur, satır yazılmaz (TEST 2)',
	function () {
		$h     = new RMA_Test_CSV_Page_Harness();
		$hazir = $h->csv_collect_import_rows( "not,a,valid\nfoo\n" );
		$ozet  = $h->csv_import_result_notice( 0, 0, 0, $hazir['error'] );

		qrms_assert_same( 3, $hazir['error'], 'gerekli kolon hatası' );
		qrms_assert_same( 0, count( $hazir['rows'] ), 'junk satır ürün olmaz' );
		qrms_assert_same( 'fail', $ozet['sonuc'], 'FAIL' );
		qrms_assert_contains( 'Gerekli kolonlar eksik', $ozet['message'], 'açık hata' );
		qrms_assert_false( false !== strpos( $ozet['message'], '1 ürün' ), 'yanıltıcı 1 ürün yok' );
	}
);

qrms_test(
	'RM-003: boş satır başarı sayısını şişirmez (TEST 3)',
	function () {
		$h     = new RMA_Test_CSV_Page_Harness();
		$csv   = "Başlık,İçerik,Özet,Fiyat\nÇorba,,,10\n\nAdana,,,20";
		$hazir = $h->csv_collect_import_rows( $csv );
		$ozet  = $h->csv_import_result_notice( count( $hazir['rows'] ), $hazir['hatali'], $hazir['atlanan'], $hazir['error'] );

		qrms_assert_same( 2, count( $hazir['rows'] ), 'iki ürün' );
		qrms_assert_same( 1, $hazir['atlanan'], 'bir boş satır atlandı' );
		qrms_assert_same( 0, $hazir['hatali'], 'boş satır hata değildir' );
		qrms_assert_same( 'ok', $ozet['sonuc'], 'boş satır tam başarıyı bozmaz' );
		qrms_assert_same( '2 ürün başarıyla aktarıldı.', $ozet['message'], 'sayaç gerçek ürün sayısı' );
	}
);

qrms_test(
	'RM-003: başlığı boş satır reddedilir (TEST 4)',
	function () {
		$h     = new RMA_Test_CSV_Page_Harness();
		$csv   = "Başlık,İçerik,Özet,Fiyat\n,,,10\nGerçek Ürün,,,15\n";
		$hazir = $h->csv_collect_import_rows( $csv );
		$ozet  = $h->csv_import_result_notice( count( $hazir['rows'] ), $hazir['hatali'], $hazir['atlanan'], $hazir['error'] );

		qrms_assert_same( 1, count( $hazir['rows'] ), 'yalnızca başlıklı satır' );
		qrms_assert_same( 1, $hazir['hatali'], 'eksik başlık hatalı' );
		qrms_assert_same( 'partial', $ozet['sonuc'], 'kısmi sonuç' );
		qrms_assert_same( '1 ürün aktarıldı, 1 satır hata nedeniyle atlandı.', $ozet['message'], 'kısmi metin' );
	}
);

qrms_test(
	'RM-003: geçersiz fiyat satırı başarı sayılmaz ve yazılmaz (TEST 5)',
	function () {
		$h     = new RMA_Test_CSV_Page_Harness();
		$csv   = "Başlık,İçerik,Özet,Fiyat\nKötü Fiyat,,,abc\nİyi Ürün,,,40\n";
		$hazir = $h->csv_collect_import_rows( $csv );
		$ozet  = $h->csv_import_result_notice( count( $hazir['rows'] ), $hazir['hatali'], $hazir['atlanan'], $hazir['error'] );

		qrms_assert_same( 1, count( $hazir['rows'] ), 'yalnızca geçerli fiyat yazılır' );
		qrms_assert_same( 'İyi Ürün', $hazir['rows'][0][0], 'kalan satır doğru ürün' );
		qrms_assert_same( 1, $hazir['fiyat_gecersiz'], 'fiyat hatası sayıldı' );
		qrms_assert_same( 1, $hazir['hatali'], 'satır hatalı' );
		qrms_assert_same( 'partial', $ozet['sonuc'], 'kısmi' );
		qrms_assert_false( false !== strpos( $ozet['message'], '2 ürün' ), 'geçersiz satır başarıya eklenmez' );
	}
);

qrms_test(
	'RM-003: karışık CSV 3 başarılı / 2 hatalı raporlar (TEST 6)',
	function () {
		$h   = new RMA_Test_CSV_Page_Harness();
		$csv = "Başlık,İçerik,Özet,Fiyat,Kategori\n"
			. "Bir,,,10,A\n"
			. "İki,,,abc,A\n"
			. "Üç,,,20,A\n"
			. ",sadece açıklama,,30,A\n"
			. "Beş,,,40,A\n";
		$hazir = $h->csv_collect_import_rows( $csv );
		$ozet  = $h->csv_import_result_notice( count( $hazir['rows'] ), $hazir['hatali'], $hazir['atlanan'], $hazir['error'] );

		qrms_assert_same( 3, count( $hazir['rows'] ), 'üç geçerli satır' );
		qrms_assert_same( 2, $hazir['hatali'], 'iki hatalı satır' );
		qrms_assert_same( 'partial', $ozet['sonuc'], 'PARTIAL' );
		qrms_assert_same( '3 ürün aktarıldı, 2 satır hata nedeniyle atlandı.', $ozet['message'], 'gerçek dağılım' );
	}
);

qrms_test(
	'RM-003: tamamen hatalı CSV 0 başarı ve açık fail (TEST 7)',
	function () {
		$h     = new RMA_Test_CSV_Page_Harness();
		$csv   = "Başlık,İçerik,Özet,Fiyat\nA,,,abc\nB,,,-5\n";
		$hazir = $h->csv_collect_import_rows( $csv );
		$ozet  = $h->csv_import_result_notice( count( $hazir['rows'] ), $hazir['hatali'], $hazir['atlanan'], $hazir['error'] );

		qrms_assert_same( 0, count( $hazir['rows'] ), 'hiç satır yazılmaz' );
		qrms_assert_same( 2, $hazir['hatali'], 'iki hata' );
		qrms_assert_same( 'fail', $ozet['sonuc'], 'FAIL' );
		qrms_assert_contains( '0 ürün aktarıldı', $ozet['message'], 'sıfır başarı açık' );
		qrms_assert_false( false !== strpos( $ozet['message'], '1 ürün aktarıldı' ), 'yanıltıcı 1 yok' );

		$yalniz_baslik = $h->csv_collect_import_rows( "Başlık\n" );
		$ozet_bos      = $h->csv_import_result_notice(
			count( $yalniz_baslik['rows'] ),
			$yalniz_baslik['hatali'],
			$yalniz_baslik['atlanan'],
			$yalniz_baslik['error']
		);
		qrms_assert_same( 'fail', $ozet_bos['sonuc'], 'yalnız başlık fail' );
		qrms_assert_contains( 'Aktarılacak geçerli satır bulunamadı', $ozet_bos['message'], 'boş veri açık' );
	}
);

qrms_test(
	'RM-003: noktalı virgül ayırıcı ve eski sütun sırası hâlâ çalışır',
	function () {
		$h     = new RMA_Test_CSV_Page_Harness();
		$csv   = "Başlık;İçerik;Özet;Fiyat;Kategori\nMercimek;Ev yapımı;;95;Çorbalar\n";
		$hazir = $h->csv_collect_import_rows( $csv );

		qrms_assert_same( ';', $hazir['delimiter'], 'noktalı virgül algılanır' );
		qrms_assert_same( 0, $hazir['error'], 'başlık geçerli' );
		qrms_assert_same( 1, count( $hazir['rows'] ), 'satır alınır' );
		qrms_assert_same( 'Mercimek', $hazir['rows'][0][0], 'başlık konum 0' );
		qrms_assert_same( '95', $hazir['rows'][0][3], 'fiyat konum 3' );
	}
);

qrms_test(
	'kaynak kod: başarı sayacı rma_csv_sonuc ile taşınır, leftover imported yetmez',
	function () {
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-import-export.php'
		);
		$js     = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/admin-ui.js'
		);

		qrms_assert_contains( '$this->csv_collect_import_rows( $content )', $kaynak, 'ayrıştırma tek yerde' );
		qrms_assert_contains( "'rma_csv_sonuc'  => \$ozet['sonuc']", $kaynak, 'sonuç kodu redirectte' );
		qrms_assert_contains( 'rma_csv_sonuc', $js, 'sonuç query arg URL\'den temizlenir' );
		qrms_assert_contains( 'rma_csv_hatali', $js, 'hata sayacı temizlenir' );
		qrms_assert_contains( 'rma_csv_fiyat_gecersiz', $js, 'fiyat uyarısı query kalıntısı bırakmaz' );
	}
);
