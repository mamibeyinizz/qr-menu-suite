<?php
/**
 * Fiyat kampanyası, tükendi ve ürünüm yok testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-kampanya-db.php';

echo "\nFiyat Kampanyası — hesap\n";

qrms_test(
	'yüzde ve sabit tutar, zam ve indirim yönünde doğru hesaplanır',
	function () {
		$yuzde_zam = array( 'calc_type' => 'percent', 'direction' => 'increase', 'amount' => 10, 'rounding' => 'none' );
		qrms_assert_same( 52.25, RMA_Kampanya_DB::yeni_fiyat( 47.50, $yuzde_zam ), '%10 zam' );

		$yuzde_ind = array( 'calc_type' => 'percent', 'direction' => 'decrease', 'amount' => 15, 'rounding' => 'none' );
		qrms_assert_same( 85.0, RMA_Kampanya_DB::yeni_fiyat( 100, $yuzde_ind ), '%15 indirim' );

		$sabit_zam = array( 'calc_type' => 'fixed', 'direction' => 'increase', 'amount' => 5, 'rounding' => 'none' );
		qrms_assert_same( 52.5, RMA_Kampanya_DB::yeni_fiyat( 47.50, $sabit_zam ), '+5 ₺' );

		$sabit_ind = array( 'calc_type' => 'fixed', 'direction' => 'decrease', 'amount' => 10, 'rounding' => 'none' );
		qrms_assert_same( 37.5, RMA_Kampanya_DB::yeni_fiyat( 47.50, $sabit_ind ), '-10 ₺' );
	}
);

qrms_test(
	'fiyat hiçbir koşulda 0 ın altına inmez',
	function () {
		// 5 ₺ lik üründe -10 ₺ lik bir indirim negatif fiyat üretirdi.
		$kural = array( 'calc_type' => 'fixed', 'direction' => 'decrease', 'amount' => 10, 'rounding' => 'none' );

		qrms_assert_same( 0.0, RMA_Kampanya_DB::yeni_fiyat( 5, $kural ), 'sıfıra kelepçelenir' );
	}
);

qrms_test(
	'fiyatı olmayan ürün ve tutarsız kural kampanya dışıdır',
	function () {
		$kural = array( 'calc_type' => 'percent', 'direction' => 'increase', 'amount' => 10, 'rounding' => 'none' );

		qrms_assert_same( null, RMA_Kampanya_DB::yeni_fiyat( '', $kural ), 'boş fiyat' );
		qrms_assert_same( null, RMA_Kampanya_DB::yeni_fiyat( 'fiyat sorunuz', $kural ), 'metin fiyat' );

		$sifir = array( 'calc_type' => 'percent', 'direction' => 'increase', 'amount' => 0, 'rounding' => 'none' );
		qrms_assert_same( null, RMA_Kampanya_DB::yeni_fiyat( 50, $sifir ), 'tutar sıfır' );
	}
);

qrms_test(
	'yuvarlama modlarının hepsi beklenen fiyatı üretir',
	function () {
		qrms_assert_same( 52.25, RMA_Kampanya_DB::yuvarla( 52.25, 'none' ), 'kuruş korunur' );
		qrms_assert_same( 52.5, RMA_Kampanya_DB::yuvarla( 52.25, 'half' ), 'en yakın 0,50' );
		qrms_assert_same( 52.0, RMA_Kampanya_DB::yuvarla( 52.25, 'whole' ), 'en yakın 1 ₺' );

		// Psikolojik fiyat modları EN YAKIN adayı seçer (diğer modlarla aynı
		// mantık): her zaman yukarı yuvarlasalardı indirim kampanyaları
		// sessizce törpülenirdi.
		qrms_assert_same( 51.9, RMA_Kampanya_DB::yuvarla( 52.25, 'end90' ), 'aşağıdaki ,90 daha yakın' );
		qrms_assert_same( 52.9, RMA_Kampanya_DB::yuvarla( 52.70, 'end90' ), 'yukarıdaki ,90 daha yakın' );
		qrms_assert_same( 51.99, RMA_Kampanya_DB::yuvarla( 52.10, 'end99' ), ',99 ile biter' );

		// Tanınmayan mod sessizce "yuvarlama yok"a düşer — şema bozulmaz.
		qrms_assert_same( 52.25, RMA_Kampanya_DB::yuvarla( 52.25, 'uydurma' ), 'bilinmeyen mod' );
	}
);

qrms_test(
	'fiyat biçimi kuruşu korur, tam sayıda küsuratı atar',
	function () {
		qrms_assert_same( '52,50', RMA_Kampanya_DB::bicimle( 52.5 ), 'kuruş korunur' );
		qrms_assert_same( '52', RMA_Kampanya_DB::bicimle( 52.0 ), 'tam sayı' );
		qrms_assert_same( '1.250,25', RMA_Kampanya_DB::bicimle( 1250.25 ), 'binlik ayracı' );
	}
);

echo "\nFiyat Kampanyası — form temizliği\n";

qrms_test(
	'uydurma seçim değerleri varsayılana düşer, yüzde üst sınıra kırpılır',
	function () {
		$temiz = RMA_Kampanya_DB::ayarlari_temizle(
			array(
				'title'      => '  Ocak Zammı  ',
				'calc_type'  => 'uydurma',
				'direction'  => 'uydurma',
				'rounding'   => 'uydurma',
				'scope_type' => 'uydurma',
				'amount'     => 500,
			)
		);

		qrms_assert_same( 'Ocak Zammı', $temiz['title'], 'başlık kırpılır' );
		qrms_assert_same( 'percent', $temiz['calc_type'], 'tür varsayılanı' );
		qrms_assert_same( 'increase', $temiz['direction'], 'yön varsayılanı' );
		qrms_assert_same( 'none', $temiz['rounding'], 'yuvarlama varsayılanı' );
		qrms_assert_same( 'all', $temiz['scope_type'], 'kapsam varsayılanı' );
		qrms_assert_same( (float) RMA_Kampanya_DB::MAX_YUZDE, $temiz['amount'], 'yüzde üst sınırı' );
	}
);

qrms_test(
	'eksi ve virgüllü tutar girdisi kabul edilir',
	function () {
		// Yön ayrı alanda tutulur; tutardaki eksi işareti iki yerden gelen
		// çelişkili yön demek olurdu, bu yüzden mutlak değere çekilir.
		qrms_assert_same( 12.5, RMA_Kampanya_DB::tutar_temizle( '12,5' ), 'Türkçe ondalık' );
		qrms_assert_same( 10.0, RMA_Kampanya_DB::tutar_temizle( '-10' ), 'eksi işareti düşer' );
		qrms_assert_same( 0.0, RMA_Kampanya_DB::tutar_temizle( 'bedava' ), 'metin girdi' );
	}
);

qrms_test(
	'kapsam listesi yalnızca kendi kapsam türünde saklanır',
	function () {
		$tum = RMA_Kampanya_DB::ayarlari_temizle(
			array( 'scope_type' => 'all', 'scope_ids' => '3,4', 'amount' => 10 )
		);
		qrms_assert_same( '', $tum['scope_ids'], 'tüm menüde liste tutulmaz' );

		$kat = RMA_Kampanya_DB::ayarlari_temizle(
			array( 'scope_type' => 'category', 'scope_ids' => '3,4,3,0,abc', 'amount' => 10 )
		);
		qrms_assert_same( '3,4', $kat['scope_ids'], 'tekrar ve geçersiz kayıt düşer' );
	}
);

echo "\nFiyat Kampanyası — kapsam ve zaman\n";

qrms_test(
	'kapsam üç dalın hepsinde doğru karar verir',
	function () {
		$tum = array( 'scope_type' => 'all', 'scope_ids' => '' );
		qrms_assert_true( RMA_Kampanya_DB::kapsamda_mi( 55, $tum, array( 9 ) ), 'tüm menü' );

		$kat = array( 'scope_type' => 'category', 'scope_ids' => '7,9' );
		qrms_assert_true( RMA_Kampanya_DB::kapsamda_mi( 55, $kat, array( 9, 12 ) ), 'kategori eşleşir' );
		qrms_assert_false( RMA_Kampanya_DB::kapsamda_mi( 55, $kat, array( 12 ) ), 'kategori eşleşmez' );

		$manuel = array( 'scope_type' => 'manual', 'scope_ids' => '55,56' );
		qrms_assert_true( RMA_Kampanya_DB::kapsamda_mi( 55, $manuel, array() ), 'seçili ürün' );
		qrms_assert_false( RMA_Kampanya_DB::kapsamda_mi( 57, $manuel, array() ), 'seçilmemiş ürün' );

		// Kapsam seçilmiş ama liste boşsa hiçbir ürün etkilenmez: aksi hâlde
		// "kategori" seçip hiç kategori işaretlememek tüm menüyü zamlardı.
		$bos = array( 'scope_type' => 'category', 'scope_ids' => '' );
		qrms_assert_false( RMA_Kampanya_DB::kapsamda_mi( 55, $bos, array( 9 ) ), 'boş liste' );
	}
);

qrms_test(
	'yalnızca durumu aktif olan kampanya geçerlidir',
	function () {
		$zaman = strtotime( '2026-01-05 14:30:00 UTC' );

		qrms_assert_true( RMA_Kampanya_DB::aktif_mi( array( 'status' => 'active' ), $zaman ), 'aktif' );
		qrms_assert_false( RMA_Kampanya_DB::aktif_mi( array( 'status' => 'passive' ), $zaman ), 'pasif' );
		qrms_assert_false( RMA_Kampanya_DB::aktif_mi( array(), $zaman ), 'kayıt yok' );
	}
);

qrms_test(
	'İkinci Faz zaman alanları boşken davranış değişmez',
	function () {
		// Şema zamanlanmış kampanya / Happy Hour için hazır; v1 bu alanları
		// yazmaz ve boş alan "sınır yok" demektir.
		$zaman = strtotime( '2026-01-05 14:30:00 UTC' );

		$kampanya = array(
			'status'      => 'active',
			'starts_at'   => null,
			'ends_at'     => '',
			'daily_start' => null,
			'daily_end'   => '',
			'days_mask'   => 0,
		);

		qrms_assert_true( RMA_Kampanya_DB::aktif_mi( $kampanya, $zaman ), 'sınırsız kampanya' );
	}
);

qrms_test(
	'tarih penceresi, gün maskesi ve saat aralığı değerlendirilir',
	function () {
		$pazartesi = strtotime( '2026-01-05 14:30:00 UTC' );

		$tarihli = array( 'status' => 'active', 'starts_at' => '2026-01-10 00:00:00', 'ends_at' => '' );
		qrms_assert_false( RMA_Kampanya_DB::aktif_mi( $tarihli, $pazartesi ), 'henüz başlamadı' );

		$biten = array( 'status' => 'active', 'starts_at' => '', 'ends_at' => '2026-01-01 00:00:00' );
		qrms_assert_false( RMA_Kampanya_DB::aktif_mi( $biten, $pazartesi ), 'süresi doldu' );

		// Bit 0 = Pazar … bit 6 = Cumartesi. 2026-01-05 bir Pazartesi.
		$haftaici = array( 'status' => 'active', 'days_mask' => 1 << 1 );
		qrms_assert_true( RMA_Kampanya_DB::aktif_mi( $haftaici, $pazartesi ), 'pazartesi maskesi' );

		$haftasonu = array( 'status' => 'active', 'days_mask' => ( 1 << 0 ) | ( 1 << 6 ) );
		qrms_assert_false( RMA_Kampanya_DB::aktif_mi( $haftasonu, $pazartesi ), 'hafta sonu maskesi' );

		$happy = array( 'status' => 'active', 'daily_start' => '16:00', 'daily_end' => '19:00' );
		qrms_assert_false( RMA_Kampanya_DB::aktif_mi( $happy, $pazartesi ), 'saat aralığı dışı' );
		qrms_assert_true(
			RMA_Kampanya_DB::aktif_mi( $happy, strtotime( '2026-01-05 17:00:00 UTC' ) ),
			'saat aralığı içi'
		);
	}
);

qrms_test(
	'gece yarısını aşan saat aralığı doğru çalışır',
	function () {
		// 22:00–02:00 gibi bir aralıkta 23:30 da 01:00 da "içeri" sayılmalı.
		qrms_assert_true( RMA_Kampanya_DB::saat_araliginda_mi( '23:30:00', '22:00', '02:00' ), 'gece yarısı öncesi' );
		qrms_assert_true( RMA_Kampanya_DB::saat_araliginda_mi( '01:00:00', '22:00', '02:00' ), 'gece yarısı sonrası' );
		qrms_assert_false( RMA_Kampanya_DB::saat_araliginda_mi( '15:00:00', '22:00', '02:00' ), 'aralık dışı' );
		qrms_assert_true( RMA_Kampanya_DB::saat_araliginda_mi( '15:00:00', '', '' ), 'sınırsız' );
	}
);

qrms_test(
	'kural metni yönetim ekranında okunur biçimde çıkar',
	function () {
		qrms_assert_same(
			'%10 zam',
			RMA_Kampanya_DB::kural_metni( array( 'calc_type' => 'percent', 'direction' => 'increase', 'amount' => 10 ) ),
			'yüzde zam'
		);

		qrms_assert_same(
			'5,50 ₺ indirim',
			RMA_Kampanya_DB::kural_metni( array( 'calc_type' => 'fixed', 'direction' => 'decrease', 'amount' => 5.5 ) ),
			'sabit indirim'
		);
	}
);

echo "\nFiyat Kampanyası — yapısal güvenceler\n";

qrms_test(
	'ekran hub kartlarına ve alt sayfa listesine kayıtlı',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-admin-pages.php' );

		qrms_assert_contains( "'qrms-rm-kampanya'", $kaynak, 'alt sayfa slug\'ı' );
		qrms_assert_contains( 'render_campaign_page', $kaynak, 'render metodu' );

		// Hub kartları get_subpages()'ten üretiliyor; ayrı bir kart tanımı
		// gerekmiyor — bu satır o bağın kopmadığının güvencesi.
		qrms_assert_contains( 'foreach ( $this->get_subpages() as $slug => $page )', $kaynak, 'kartlar listeden üretilir' );
	}
);

qrms_test(
	'kaydetme, geri alma ve önizleme uçlarının hepsi kayıtlı',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' );

		qrms_assert_contains( "admin_post_rma_kampanya_kaydet", $kaynak, 'kaydetme ucu' );
		qrms_assert_contains( "admin_post_rma_kampanya_geri_al", $kaynak, 'geri alma ucu' );
		qrms_assert_contains( "admin_post_rma_kampanya_sil", $kaynak, 'silme ucu' );
		qrms_assert_contains( "wp_ajax_rma_kampanya_onizleme", $kaynak, 'önizleme ucu' );
	}
);

qrms_test(
	'ön yüzdeki DÖRT fiyat noktası da tek kaynaktan besleniyor',
	function () {
		// Kampanya mimarisinin temel güvencesi: hiçbir gösterim noktası
		// fiyatı ham meta'dan okumaz, hepsi RMA_Kampanya::fiyat_html()
		// çağırır. Aksi hâlde bir yüzeyde kampanyalı, diğerinde eski fiyat
		// görünürdü.
		$noktalar = array(
			'includes/trait-frontend.php'    => 'menü kartı',
			'includes/trait-ajax.php'        => 'ürün modalı',
			'includes/shortcode-vitrin.php'  => 'ürün vitrini',
			'includes/shortcode-slider.php'  => 'öne çıkan slider',
		);

		foreach ( $noktalar as $dosya => $etiket ) {
			$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/' . $dosya );

			qrms_assert_contains( 'RMA_Kampanya::fiyat_html', $kaynak, $etiket . ': ortak giriş' );
			qrms_assert_false(
				strpos( $kaynak, "get_post_meta( \$id, 'rma_price'" ) !== false
					|| strpos( $kaynak, "get_post_meta( \$product_id, 'rma_price'" ) !== false,
				$etiket . ': ham fiyat okuması kalmadı'
			);
		}
	}
);

qrms_test(
	'menü önbelleği anahtarı aktif kampanyayı içerir',
	function () {
		// Kampanya açılıp kapandığında önbelleğe alınmış menü HTML'i
		// geçersizleşmezse müşteri eski fiyatı görmeye devam ederdi.
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-helpers.php' );

		qrms_assert_contains( 'RMA_Kampanya::imza()', $kaynak, 'imza anahtara giriyor' );
	}
);

qrms_test(
	'ürün fiyatı hiçbir kod yolunda üzerine yazılmıyor',
	function () {
		// Özelliğin can damarı: kampanya rma_price/_qmo_kombin_fiyat alanlarına
		// ASLA yazmaz. Yedek meta (_qrms_orijinal_fiyat) ayrı bir alandır.
		$dosyalar = array( 'includes/class-kampanya.php', 'includes/class-kampanya-db.php', 'includes/trait-kampanya-admin.php' );

		foreach ( $dosyalar as $dosya ) {
			$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/' . $dosya );

			// Fiyat alanlarına YAZAN tek bir çağrı bile olmamalı; okuma serbest.
			qrms_assert_false(
				(bool) preg_match( "/update_post_meta\([^;]*'(rma_price|_qmo_kombin_fiyat)'/", $kaynak ),
				$dosya . ': fiyat alanlarına yazılmıyor'
			);
		}

		$admin = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-kampanya-admin.php' );
		qrms_assert_contains( 'RMA_Kampanya_DB::ORIJINAL_META', $admin, 'yedek ayrı alana yazılır' );
	}
);


/* ---------------------------------------------------------------------------
 * 13. Ürün Tükendi (stok durumu)
 *
 * Göster/Gizle (`rma_active`) ürünü menüden kaldırır. Tükendi ayrı bir
 * meta'dır (`_rma_tukendi`): orijinal görünürlük alanını ezmez, menü
 * sorgusundan ürünü düşürmez.
 * ------------------------------------------------------------------------ */

require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-tukendi.php';

echo "\nÜrün Tükendi — stok durumu\n";

qrms_test(
	'yalnızca açık 1 değeri tükendi sayılır',
	function () {
		qrms_assert_true( RMA_Tukendi::meta_tukendi_mi( '1' ), 'string 1' );
		qrms_assert_false( RMA_Tukendi::meta_tukendi_mi( '0' ), 'sıfır' );
		qrms_assert_false( RMA_Tukendi::meta_tukendi_mi( '' ), 'boş meta' );
		qrms_assert_false( RMA_Tukendi::meta_tukendi_mi( null ), 'null' );
		qrms_assert_false( RMA_Tukendi::meta_tukendi_mi( 'yes' ), 'rastgele metin' );
	}
);

qrms_test(
	'ürün adı büyük/küçük harf ve boşluk farkını yok sayar',
	function () {
		qrms_assert_true( RMA_Tukendi::ad_eslesir( 'Adana Kebap', 'adana kebap' ), 'küçük harf' );
		qrms_assert_true( RMA_Tukendi::ad_eslesir( '  Adana Kebap ', 'Adana Kebap' ), 'kırpılmış boşluk' );
		qrms_assert_false( RMA_Tukendi::ad_eslesir( 'Adana Kebap', 'Urfa Kebap' ), 'farklı ürün' );
		qrms_assert_false( RMA_Tukendi::ad_eslesir( '', '' ), 'iki boş ad eşleşmez' );
		qrms_assert_same( 'adana kebap', RMA_Tukendi::ad_normalize( ' Adana Kebap ' ), 'normalize' );
	}
);

qrms_test(
	'tükendi rma_active alanına yazmaz, ayrı meta kullanır',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-tukendi.php' );

		qrms_assert_contains( "const META = '_rma_tukendi'", $kaynak, 'ayrı meta anahtarı' );
		qrms_assert_false(
			(bool) preg_match( "/update_post_meta\([^;]*'rma_active'/", $kaynak ),
			'Göster/Gizle alanına yazılmaz'
		);

		$kaydet = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-post-types.php' );
		qrms_assert_contains( 'RMA_Tukendi::kaydet', $kaydet, 'ürün kaydında ayrı yazılır' );
		qrms_assert_false(
			(bool) preg_match( "/\\\$checkboxes = \[[^\]]*rma_tukendi/", $kaydet ),
			'genel checkbox listesine karışmaz'
		);
	}
);

qrms_test(
	'menü sorgusu tükendi ürünleri gizlemez; kart ve vitrin işareti basar',
	function () {
		$ajax = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-ajax.php' );
		qrms_assert_contains( "'key' => 'rma_active'", $ajax, 'gizleme hâlâ rma_active' );
		qrms_assert_false(
			(bool) preg_match( "/'key'\s*=>\s*'_rma_tukendi'/", $ajax ),
			'tükendi meta_query filtresi değil'
		);

		$kart = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-frontend.php' );
		qrms_assert_contains( 'is-tukendi', $kart, 'kart sınıfı' );
		qrms_assert_contains( 'RMA_Tukendi::rozet_html', $kart, 'kart rozeti' );

		$vitrin = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/shortcode-vitrin.php' );
		qrms_assert_contains( 'RMA_Tukendi::urun_tukendi', $vitrin, 'vitrin durumu' );

		$slider = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/shortcode-slider.php' );
		qrms_assert_contains( 'RMA_Tukendi::urun_tukendi', $slider, 'slider durumu' );
	}
);

qrms_test(
	'chatbot siparişi tükendi filtresinden geçer',
	function () {
		$siparis = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/rest-order.php' );
		qrms_assert_contains( 'qmo_siparis_onay_oncesi', $siparis, 'sipariş kancası' );
		qrms_assert_contains( 'order_blocked', $siparis, 'engel analitiği' );
		qrms_assert_contains( 'order_sent', $siparis, 'başarılı sipariş olayı' );
		qrms_assert_contains( 'order_failed', $siparis, 'başarısız sipariş olayı' );
		qrms_assert_contains( 'qmo_analitik_siparis_yaz', $siparis, 'sipariş analitik yazımı' );

		$cagri = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/ajax-waiter-bill.php' );
		$hiz   = strpos( $cagri, 'qmo_hiz_siniri' );
		$yaz   = strpos( $cagri, 'qmo_analitik_yaz' );
		$birak = strpos( $cagri, 'qmo_db_serbest_birak' );
		qrms_assert_true( false !== $hiz && false !== $yaz && false !== $birak, 'çağrı analitik noktaları var' );
		qrms_assert_true( $hiz < $yaz, 'analitik hız sınırından sonra' );
		qrms_assert_true( $yaz < $birak, 'analitik bağlantı bırakılmadan önce' );
		qrms_assert_contains( 'waiter_call', $cagri, 'garson olayı' );
		qrms_assert_contains( 'bill_request', $cagri, 'hesap olayı' );

		$menu = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' );
		qrms_assert_contains( "add_filter( 'qmo_siparis_onay_oncesi'", $menu, 'menü bağlar' );

		$json = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/admin/admin-sayfa.php' );
		qrms_assert_contains( "'tukendi'", $json, 'menü JSON alanı' );

		qrms_assert_same( null, RMA_Tukendi::siparis_engeli( array() ), 'boş sipariş' );
		qrms_assert_same( 'önceki', RMA_Tukendi::siparis_filtresi( 'önceki', array() ), 'önceki engel korunur' );
		qrms_assert_true( method_exists( 'RMA_Tukendi', 'siparis_engeli_detay' ), 'yapısal engel ayrıntısı' );
		qrms_assert_true( method_exists( 'RMA_Tukendi', 'ad_tukendi_urun' ), 'engelleyen ürün kimliği' );
	}
);

echo "\nÜrünüm Yok — elle kapatılanlar listesi\n";

qrms_test(
	'eksik özet elle kapatılan ürün id\'lerini de tutar, ikinci sorgu yok',
	function () {
		$stok = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/urunum-yok/class-stock.php' );

		qrms_assert_contains( "'elle_ids'   => []", $stok, 'elle_ids kovası' );
		qrms_assert_contains( "\$ozet['elle_ids'][] = (int) \$id", $stok, 'elle id aynı döngüde eklenir' );
		qrms_assert_contains( "\$ozet['elle']++", $stok, 'elle sayacı durur' );

		// Tek get_posts: hem malzeme kırılımı hem elle id listesi aynı taramadan.
		qrms_assert_same( 1, substr_count( $stok, "function qmo_urunum_yok_eksik_ozet" ), 'tek özet fonksiyonu' );
	}
);

qrms_test(
	'Ürünüm Yok sayfası elle kapatılanları malzeme listesinin üstünde basar',
	function () {
		$admin = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/urunum-yok/trait-admin.php' );

		qrms_assert_contains( 'render_urunum_yok_elle_liste', $admin, 'elle liste metodu' );
		qrms_assert_contains( 'Elle Kapatılan Ürünler', $admin, 'bölüm başlığı' );
		qrms_assert_contains( 'Elle kapatılan ürün yok.', $admin, 'boş durum mesajı' );
		qrms_assert_contains( 'Tekrar Aktif Et', $admin, 'geri alma butonu' );
		qrms_assert_contains( 'qmo_urunum_yok_eksik_ozet', $admin, 'aynı özet kaynağı' );
		qrms_assert_contains( "\$ozet['elle_ids']", $admin, 'id listesi özettendir' );
		qrms_assert_contains( 'qmo_uy_aktiflestir', $admin, 'mevcut aktifleştirme ucu' );
		qrms_assert_contains( 'qmo_uy_reactivate_', $admin, 'mevcut nonce' );
		qrms_assert_contains( '$limit = 50', $admin, 'sayfalama limiti' );
		qrms_assert_contains( "edit.php?post_type=rma_menu_item", $admin, 'Ürünlerim devam linki' );
		qrms_assert_contains( 'widefat striped', $admin, 'malzeme listesiyle aynı tablo' );
		qrms_assert_contains( 'tbody tr:hover', file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/admin-ui.css' ), 'satır hover' );

		$elle  = strpos( $admin, 'function render_urunum_yok_elle_liste' );
		$malz  = strpos( $admin, 'function render_urunum_yok_aktif_liste' );
		$cagri_elle = strpos( $admin, '$this->render_urunum_yok_elle_liste();' );
		$cagri_malz = strpos( $admin, '$this->render_urunum_yok_aktif_liste();' );

		qrms_assert_true( false !== $elle && false !== $malz, 'iki render metodu da var' );
		qrms_assert_true( $cagri_elle < $cagri_malz, 'elle liste malzeme listesinin üstünde çağrılır' );
	}
);


/* ---------------------------------------------------------------------------
 * 14. VERİTABANI BAĞLANTI OPTİMİZASYONU
 *
 * Canlıda "Too many connections" hatasına yol açan üç desen burada korunur:
 *   (a) aynı tabloyu defalarca tarayan ayrı ayrı aggregate sorguları,
 *   (b) LIMIT'siz liste sorguları,
 *   (c) uzun bir dış API isteği boyunca boşuna açık tutulan bağlantı.
 * ------------------------------------------------------------------------ */

// Yönetimdeki liste sayfalaması ve bağlantı yardımcıları buradan gelir.
// (forms/functions.php YÜKLENMEZ: yukarıda qrm_cf_unread_total'ın taklidi
// tanımlı, gerçeği çift tanım hatası verirdi — o yüzden okunmamış gönderim
// sayacı bu bölümde kaynak üzerinden doğrulanır.)


/* P2 çeviri testleri (birleşme sonrası taşındı) */

echo "\nQR Çeviri (P2 vitrin / slider / banner)\n";

require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/ui-stringler.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/fiyat.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/settings.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/veri-kaynaklar.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/kaynaklar.php';

qrms_test(
	'vitrin slider banner aria ui_string; biçim dizesi sayı korur',
	function () {
		$vit = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/shortcode-vitrin.php' );
		$sld = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/shortcode-slider.php' );
		$ban = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/shortcode-banner-slider.php' );
		$ui  = rma_ceviri_varsayilan_ui_metinleri();

		qrms_assert_contains( "qmo_ceviri_ui( __( 'Önceki', 'qrms' ) )", $vit, 'vitrin önceki' );
		qrms_assert_contains( "qmo_ceviri_ui( __( 'Sonraki', 'qrms' ) )", $vit, 'vitrin sonraki' );
		qrms_assert_contains( "qmo_ceviri_ui( __( '%d ürün — kaydırarak gezinin', 'qrms' ) )", $vit, 'vitrin biçim' );
		qrms_assert_contains( "qmo_ceviri_ui( __( 'Slide navigasyonu', 'qrms' ) )", $sld, 'slider nav' );
		qrms_assert_contains( "qmo_ceviri_ui( __( 'Önceki slide', 'qrms' ) )", $sld, 'slider önceki' );
		qrms_assert_contains( "qmo_ceviri_ui( __( 'Kampanya banner\\'ları', 'qrms' ) )", $ban, 'banner bölge' );
		qrms_assert_contains( "qmo_ceviri_ui( __( '%d. banner', 'qrms' ) )", $ban, 'banner biçim' );
		qrms_assert_same( '3. banner', sprintf( qmo_ceviri_ui( '%d. banner' ), 3 ), 'sayı korunur' );
		qrms_assert_same( '2 ürün — kaydırarak gezinin', sprintf( qmo_ceviri_ui( '%d ürün — kaydırarak gezinin' ), 2 ), 'ürün sayı' );

		foreach ( array( 'Önceki', 'Sonraki', '%d. banner', 'Banner seçimi' ) as $metin ) {
			qrms_assert_true( in_array( $metin, $ui, true ), $metin );
		}
	}
);

qrms_test(
	'detay modal Kapat RMA_MODAL_CFG.i18n; splash Dil data-sp-attr',
	function () {
		$js    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/rma-detail-modal.js' );
		$vit   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/shortcode-vitrin.php' );
		$front = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-acilis-ekrani/includes/frontend.php' );
		$i18n  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-acilis-ekrani/includes/i18n.php' );

		qrms_assert_contains( 'RMA_MODAL_CFG.i18n.kapat', $js, 'JS kapat' );
		qrms_assert_false( (bool) preg_match( '/aria-label="Kapat"/', $js ), 'sabit Kapat yok' );
		qrms_assert_contains( "qmo_ceviri_ui( __( 'Kapat', 'qrms' ) )", $vit, 'vitrin cfg' );
		qrms_assert_contains( "lang_data( \$opts, 'lang_group', 'Dil', 'aria-label' )", $front, 'splash Dil attr' );
		qrms_assert_contains( 'aria-label="Dil"', $front, 'splash TR yedek' );
		qrms_assert_contains( "'lang_group'", $i18n, 'katalog anahtarı' );
		qrms_assert_contains( "'Language'", $i18n, 'EN Language' );
		qrms_assert_false( (bool) preg_match( '/Dil \/ Language/', $front ), 'sabit iki dil kalmadı' );
	}
);

qrms_test(
	'renk önizlemesi yayınlanmış menü ürününden beslenir, renk senkronuna dokunmaz',
	function () {
		$php   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-admin-pages.php' );
		$ajax  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-ajax.php' );
		$boot  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' );
		$js    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/admin-ui.js' );

		qrms_assert_contains( 'function get_color_preview_item', $php, 'ürün yardımcısı' );
		qrms_assert_contains( "'post_type'        => 'rma_menu_item'", $php, 'CPT' );
		qrms_assert_contains( "'taxonomy' => 'rma_category'", $php, 'kategori şartı' );
		qrms_assert_contains( 'Mercimek Çorbası', $php, 'boş menü yedeği' );
		qrms_assert_contains( 'RMA_Kampanya::fiyat_yazi', $php, 'modül fiyat biçimi' );
		qrms_assert_false( false !== strpos( $php, 'wc_price' ), 'Woo fiyatı kullanılmaz' );
		qrms_assert_contains( 'rma-cp-shuffle', $php, 'yenile düğmesi' );

		qrms_assert_contains( 'function ajax_color_preview_item', $ajax, 'AJAX uç' );
		qrms_assert_contains( "check_ajax_referer( 'rma_admin_nonce', 'security' )", $ajax, 'admin nonce' );
		qrms_assert_contains( "wp_ajax_rma_color_preview_item", $boot, 'kayıt' );

		qrms_assert_contains( "action: 'rma_color_preview_item'", $js, 'JS action' );
		qrms_assert_contains( 'applyColorPreviewItem', $js, 'DOM güncellemesi' );
		qrms_assert_contains( 'querySelector(\'.rma-cp-name\')', $js, 'ad alanı' );
		qrms_assert_contains( 'var COLOR_VARS = {', $js, 'renk haritası duruyor' );
	}
);
