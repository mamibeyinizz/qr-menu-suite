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

if ( ! class_exists( 'RMA_Menu_Test_Import' ) ) {
	class RMA_Menu_Test_Import {
		use RMA_Import_Export_Trait;
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
