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

		$fonksiyon_basi = strpos( $kaynak, 'function handle_csv_import()' );
		$fonksiyon_sonu = strpos( $kaynak, "\n    /**\n     * CSV sütunları" );
		$govde          = substr( $kaynak, $fonksiyon_basi, $fonksiyon_sonu - $fonksiyon_basi );

		qrms_assert_contains( '$fiyat_gecersiz          = 0;', $govde, 'sayaç sıfırla başlatılıyor' );
		qrms_assert_contains( '$fiyat_gecersiz_satirlar = [];', $govde, 'satır listesi sıfırla başlatılıyor' );
		qrms_assert_contains( "\$d['_rma_satir_no'] = \$i + 1;", $govde, 'gerçek dosya satır numarası saklanıyor' );
		qrms_assert_contains( '$fiyat_gecersiz++;', $govde, 'geçersiz fiyatta sayaç artırılıyor' );
		qrms_assert_contains(
			"\$fiyat_gecersiz_satirlar[] = (int) ( \$d['_rma_satir_no'] ?? 0 );",
			$govde,
			'satır numarası (int olarak) listeye ekleniyor'
		);

		$sayac_konumu = strpos( $govde, '$fiyat_gecersiz++;' );
		$sanitize_konumu = strpos( $govde, "\$gecerli_fiyat = \$this->sanitize_price_value( \$d[3] ?? '' );" );
		qrms_assert_true( $sanitize_konumu < $sayac_konumu, 'sayaç yalnızca doğrulamadan SONRA artırılıyor' );
	}
);

qrms_test(
	'kaynak kod: güncellenen (mevcut) üründe eski fiyat korunuyor, yeni üründe geçersiz fiyat kaydedilmiyor',
	function () {
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-import-export.php'
		);

		$fonksiyon_basi = strpos( $kaynak, 'function handle_csv_import()' );
		$fonksiyon_sonu = strpos( $kaynak, "\n    /**\n     * CSV sütunları" );
		$govde          = substr( $kaynak, $fonksiyon_basi, $fonksiyon_sonu - $fonksiyon_basi );

		// null === $gecerli_fiyat dalı: sadece YENİ ürün (hedef_id yok) için
		// boş meta yazılır; mevcut (güncellenen) ürüne rma_price hiç dokunulmaz.
		$fiyat_blok_basi = strpos( $govde, '$gecerli_fiyat = $this->sanitize_price_value(' );
		$fiyat_blok_sonu = strpos( $govde, '$meta_map = [' );
		$fiyat_blok      = substr( $govde, $fiyat_blok_basi, $fiyat_blok_sonu - $fiyat_blok_basi );

		qrms_assert_contains( 'if ( ! $hedef_id ) {', $fiyat_blok, 'yalnızca yeni ürün dalında meta yazılıyor' );
		qrms_assert_contains( "update_post_meta( \$pid, 'rma_price', '' );", $fiyat_blok, 'yeni üründe geçersiz fiyat boş kaydediliyor' );
		qrms_assert_contains( "update_post_meta( \$pid, 'rma_price', \$gecerli_fiyat );", $fiyat_blok, 'geçerli fiyat hâlâ doğrudan kaydediliyor' );

		// "! $hedef_id" kontrolü olmadan koşulsuz bir update_post_meta çağrısı
		// (eski davranış — güncellenen üründe de eski fiyatı siliyordu) kalmamalı.
		qrms_assert_false(
			false !== strpos( $fiyat_blok, "'rma_price', null === \$gecerli_fiyat ? '' : \$gecerli_fiyat" ),
			'eski koşulsuz üzerine yazma deseni kaldırıldı'
		);
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
		$_GET = array( 'imported' => 5 );

		ob_start();
		$h->render_csv_import_page();
		$html = ob_get_clean();

		qrms_assert_contains( '<strong>5</strong> ürün aktarıldı.', $html, 'mevcut başarı bildirimi bozulmadı' );
		qrms_assert_false( false !== strpos( $html, 'notice-warning' ), 'geçersiz fiyat yokken uyarı basılmaz' );
	}
);

qrms_test(
	'render_csv_import_page(): geçersiz fiyat sayacı ve satır numaraları güvenli biçimde basılır',
	function () {
		$h    = new RMA_Test_CSV_Page_Harness();
		$_GET = array(
			'imported'                 => 12,
			'rma_csv_fiyat_gecersiz'   => 3,
			'rma_csv_fiyat_satirlar'   => '2,5,9',
		);

		ob_start();
		$h->render_csv_import_page();
		$html = ob_get_clean();

		qrms_assert_contains( '<strong>12</strong> ürün aktarıldı.', $html, 'geçerli satırların içe aktarımı engellenmedi' );
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
