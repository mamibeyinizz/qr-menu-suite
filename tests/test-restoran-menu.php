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
	'yalnızca durumu active olan kampanya geçerlidir; applied tarihçe kaydı değildir',
	function () {
		$zaman = strtotime( '2026-01-05 14:30:00 UTC' );

		qrms_assert_true( RMA_Kampanya_DB::aktif_mi( array( 'status' => 'active' ), $zaman ), 'aktif' );
		qrms_assert_false( RMA_Kampanya_DB::aktif_mi( array( 'status' => 'passive' ), $zaman ), 'pasif' );
		qrms_assert_false( RMA_Kampanya_DB::aktif_mi( array( 'status' => 'applied' ), $zaman ), 'uygulanmış zam' );
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
	'indirim akışı fiyat alanına yazmaz; zam akışı toplu yazım kullanır',
	function () {
		$kampanya = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-kampanya.php' );
		$admin    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-kampanya-admin.php' );
		$db       = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-kampanya-db.php' );

		qrms_assert_false(
			(bool) preg_match( "/update_post_meta\([^;]*'(rma_price|_qmo_kombin_fiyat)'/", $kampanya ),
			'ön yüz sınıfı fiyat alanına yazmaz'
		);
		qrms_assert_false(
			(bool) preg_match( "/update_post_meta\([^;]*'(rma_price|_qmo_kombin_fiyat)'/", $admin ),
			'indirim yönetimi tek tek fiyat yazmaz'
		);
		qrms_assert_contains( 'kampanya_zam_uygula', $admin, 'zam uygulama yolu' );
		qrms_assert_contains( 'kampanya_indirim_uygula', $admin, 'indirim uygulama yolu ayrı' );
		qrms_assert_contains( 'fiyatlari_toplu_yaz', $db, 'zam toplu fiyat yazımı' );
		qrms_assert_contains( 'zam_uygulandi', $db, 'applied durumu' );
		qrms_assert_contains( 'orijinal_yedekleri_toplu_yaz', $db, 'write-once yedek' );
	}
);

echo "\nFiyat Kampanyası — kalıcı zam\n";

qrms_test(
	'zam fiyat satırları doğru meta anahtarı ve biçimle üretilir',
	function () {
		$satirlar = RMA_Kampanya_DB::zam_fiyat_satirlari(
			array(
				array( 'product_id' => 10, 'fiyat' => 52.5, 'kombin' => false ),
				array( 'product_id' => 11, 'fiyat' => 99, 'kombin' => true ),
			)
		);

		qrms_assert_same( 2, count( $satirlar ), 'iki satır' );
		qrms_assert_same( 10, $satirlar[0]['product_id'], 'ürün ID' );
		qrms_assert_same( '52,50', $satirlar[0]['fiyat'], 'biçimli fiyat' );
		qrms_assert_same( 'rma_price', $satirlar[0]['meta_key'], 'normal ürün' );
		qrms_assert_same( '_qmo_kombin_fiyat', $satirlar[1]['meta_key'], 'kombin ürün' );
	}
);

qrms_test(
	'orijinal yedek write-once davranır',
	function () {
		$GLOBALS['qrms_test']['post_meta'] = array(
			101 => array( RMA_Kampanya_DB::ORIJINAL_META => 40 ),
			102 => array(),
		);

		$wpdb = new class() extends QRMS_Sayan_Wpdb {
			public function query( $sql ) {
				$this->queries[] = $sql;

				if ( false !== stripos( $sql, 'INSERT INTO' ) && preg_match_all( "/\((\d+), '_qrms_orijinal_fiyat', '([^']+)'\)/", $sql, $m, PREG_SET_ORDER ) ) {
					foreach ( $m as $satir ) {
						$pid = (int) $satir[1];
						if ( ! isset( $GLOBALS['qrms_test']['post_meta'][ $pid ][ RMA_Kampanya_DB::ORIJINAL_META ] ) ) {
							$GLOBALS['qrms_test']['post_meta'][ $pid ][ RMA_Kampanya_DB::ORIJINAL_META ] = (float) $satir[2];
						}
					}
				}

				return 1;
			}

			public function get_col( $sql ) {
				$this->queries[] = $sql;

				if ( false !== strpos( $sql, RMA_Kampanya_DB::ORIJINAL_META ) && preg_match( '/post_id IN \(([^)]+)\)/', $sql, $m ) ) {
					$mevcut = array();
					foreach ( explode( ',', $m[1] ) as $pid ) {
						$pid = (int) trim( $pid );
						if ( isset( $GLOBALS['qrms_test']['post_meta'][ $pid ][ RMA_Kampanya_DB::ORIJINAL_META ] ) ) {
							$mevcut[] = (string) $pid;
						}
					}
					return $mevcut;
				}

				return parent::get_col( $sql );
			}
		};

		$GLOBALS['wpdb'] = $wpdb;

		RMA_Kampanya_DB::orijinal_yedekleri_toplu_yaz(
			array(
				101 => 40,
				102 => 55,
				103 => 60,
			)
		);

		qrms_assert_same( 40.0, (float) $GLOBALS['qrms_test']['post_meta'][101][ RMA_Kampanya_DB::ORIJINAL_META ], 'mevcut yedek korunur' );
		qrms_assert_same( 55.0, (float) $GLOBALS['qrms_test']['post_meta'][102][ RMA_Kampanya_DB::ORIJINAL_META ], 'yeni yedek yazılır' );
		qrms_assert_same( 60.0, (float) $GLOBALS['qrms_test']['post_meta'][103][ RMA_Kampanya_DB::ORIJINAL_META ], 'üçüncü ürün yedek' );
	}
);

qrms_test(
	'zam toplu yazım fiyatı günceller ve kapsam dışı ürüne dokunmaz',
	function () {
		$GLOBALS['qrms_test']['post_meta'] = array(
			201 => array( 'rma_price' => '50' ),
			202 => array( 'rma_price' => '30' ),
			203 => array( '_qmo_kombin_fiyat' => '80' ),
		);

		$wpdb = new class() extends QRMS_Sayan_Wpdb {
			public function query( $sql ) {
				$this->queries[] = $sql;

				if ( false !== stripos( $sql, 'DELETE FROM' ) && preg_match( "/meta_key = '(rma_price|_qmo_kombin_fiyat)' AND post_id IN \(([^)]+)\)/", $sql, $m ) ) {
					$anahtar = $m[1];
					foreach ( explode( ',', $m[2] ) as $pid ) {
						unset( $GLOBALS['qrms_test']['post_meta'][ (int) trim( $pid ) ][ $anahtar ] );
					}
				}

				if ( false !== stripos( $sql, 'INSERT INTO' ) && preg_match_all( "/\((\d+), '(rma_price|_qmo_kombin_fiyat)', '([^']+)'\)/", $sql, $m, PREG_SET_ORDER ) ) {
					foreach ( $m as $satir ) {
						$GLOBALS['qrms_test']['post_meta'][ (int) $satir[1] ][ $satir[2] ] = $satir[3];
					}
				}

				return 1;
			}
		};

		$GLOBALS['wpdb'] = $wpdb;

		RMA_Kampanya_DB::fiyatlari_toplu_yaz(
			array(
				array( 'product_id' => 201, 'fiyat' => 55, 'kombin' => false ),
				array( 'product_id' => 203, 'fiyat' => 88, 'kombin' => true ),
			)
		);

		qrms_assert_same( '55', $GLOBALS['qrms_test']['post_meta'][201]['rma_price'], 'kapsamdaki ürün güncellendi' );
		qrms_assert_same( '30', $GLOBALS['qrms_test']['post_meta'][202]['rma_price'], 'kapsam dışı değişmedi' );
		qrms_assert_same( '88', $GLOBALS['qrms_test']['post_meta'][203]['_qmo_kombin_fiyat'], 'kombin fiyatı güncellendi' );
		qrms_assert_true( $wpdb->kac_kez( 'INSERT INTO' ) >= 1, 'toplu INSERT çalıştı' );
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
	'GÜVENLİK: toplu menü/fiyat yazan uçlar ekranla aynı yetkiyi ister',
	function () {
		$ie    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-import-export.php' );
		$uy    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/urunum-yok/trait-admin.php' );

		// Ekranlar manage_options ile kayıtlı; işleyicilerin edit_posts
		// (Katkıda Bulunan seviyesi) kabul etmesi tüm menünün ve fiyat
		// listesinin düşük yetkiyle ezilmesine izin veriyordu.
		qrms_assert_false( false !== strpos( $ie, "current_user_can( 'edit_posts' )" ), 'içe/dışa aktarımda edit_posts kalmadı' );
		qrms_assert_false( false !== strpos( $uy, "current_user_can( 'edit_posts' )" ), 'ürünüm yok uçlarında edit_posts kalmadı' );

		// Modül tek başına da çalışabildiği için suite yoksa manage_options'a düşer.
		qrms_assert_contains( "class_exists( 'QRMS_Admin' ) ? QRMS_Admin::CAPABILITY : 'manage_options'", $ie, 'içe/dışa aktarım yetkisi' );
		qrms_assert_contains( "class_exists( 'QRMS_Admin' ) ? QRMS_Admin::CAPABILITY : 'manage_options'", $uy, 'ürünüm yok yetkisi' );

		// Dosyadaki ID rastgele bir ürünü işaret edebilir: ürün bazlı kontrol.
		qrms_assert_contains( "current_user_can( 'edit_post', \$pid )", $ie, 'JSON içe aktarımda ürün bazlı yetki' );
		qrms_assert_contains( "current_user_can( 'edit_post', \$pid )", $uy, 'CSV/işaretlemede ürün bazlı yetki' );

		// Önizleme token'ı onu oluşturan kullanıcıya bağlı olmalı.
		qrms_assert_contains( '$sahip !== get_current_user_id()', $uy, 'önizleme sahibi doğrulanır' );
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

qrms_test(
	'ajax_toggle_status post özel edit_post yetkisi kullanır',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-ajax.php' );

		qrms_assert_contains( "current_user_can( 'edit_post', \$post_id )", $kaynak, 'post özel yetki' );
		qrms_assert_contains( "get_post_type( \$post_id ) !== 'rma_menu_item'", $kaynak, 'post tipi doğrulanır' );
	}
);

qrms_test(
	'GÜVENLİK: rma_views kimliksiz uçta IP+ürün başına hız sınırlı',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-ajax.php' );

		// Uç kimliksizdir (soft nonce); sayaç öncesinde hiçbir sınır yoktu,
		// scriptle tekrarlanan istek her seferinde ayrı bir postmeta UPDATE'i
		// üretiyordu.
		qrms_assert_contains( "'rma_view_' . \$id . '_' . md5( \$ip )", $kaynak, 'IP+ürün başına kilit anahtarı' );
		qrms_assert_contains( 'set_transient( $kilit, 1, MINUTE_IN_SECONDS )', $kaynak, 'dakikalık pencere' );
	}
);

qrms_test(
	'GÜVENLİK: JSON menü içe aktarımında görsel URL\'i http(s) ile sınırlı',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-import-export.php' );

		qrms_assert_contains( "0 !== stripos( \$image_url, 'http://' ) && 0 !== stripos( \$image_url, 'https://' )", $kaynak, 'şema http(s) ile sınırlı' );
	}
);

qrms_test(
	'GÜVENLİK: galeri AJAX uçları post tipini doğrular',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-galeri/includes/trait-ajax.php' );

		// ID doğrudan POST'tan geliyordu; tip kontrolü olmadan bu uçlar galeri
		// dışındaki HERHANGİ bir post'u silebilir/durumunu değiştirebilirdi.
		qrms_assert_contains( 'self::CPT_SECTION !== get_post_type( $id )', $kaynak, 'bölüm silme/durum/sıralama tip kontrolü' );
		qrms_assert_contains( 'self::CPT_IMAGE !== get_post_type( $id )', $kaynak, 'görsel silme tip kontrolü' );

		// En az 3 farklı uçta (sil, durum değiştir, sırala) kontrol geçmeli.
		qrms_assert_true(
			substr_count( $kaynak, 'self::CPT_SECTION !== get_post_type( $id )' ) >= 3,
			'bölüm kontrolü birden çok uçta'
		);
	}
);

qrms_test(
	'GÜVENLİK: Ürünüm Yok malzeme CSV dışa aktarımında formül enjeksiyonu kaçırılır',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/urunum-yok/trait-admin.php' );

		qrms_assert_contains( 'private function csv_hucre_kacir( $deger )', $kaynak, 'kaçırma metodu tanımlı' );
		qrms_assert_contains( "\$this->csv_hucre_kacir( \$p->post_title )", $kaynak, 'ürün başlığı kaçırılır' );
		qrms_assert_contains( "\$this->csv_hucre_kacir( is_wp_error( \$cats )", $kaynak, 'kategori listesi kaçırılır' );
		qrms_assert_contains( "\$this->csv_hucre_kacir( is_wp_error( \$ings )", $kaynak, 'malzeme listesi kaçırılır' );
	}
);

qrms_test(
	'menü sayfası açılışında kaydırma konumu sıfırlanır',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/rma-frontend.js' );

		qrms_assert_contains( 'function rmaInitPageScroll()', $js, 'sayfa kaydırma koruması' );
		qrms_assert_contains( "history.scrollRestoration = 'manual'", $js, 'scrollRestoration manual' );
		qrms_assert_contains( 'function rmaEnsurePageTop()', $js, 'üste alma yardımcısı' );
		qrms_assert_contains( 'rmaInitPageScroll();', $js, 'init içinde çağrılır' );
		qrms_assert_contains( 'if (e.persisted) rmaEnsurePageTop()', $js, 'bfcache dönüşü' );
		qrms_assert_false(
			false !== strpos( $js, 'scrollToSection' ) && false !== strpos( explode( 'function loadAll', $js )[1], 'scrollToSection' ),
			'loadAll içinde otomatik kategori kaydırması yok'
		);
	}
);

/* =====================================================================
   GELİŞMİŞ MENÜ FİLTRELEME
   Karar mantığı RMA_Filtre'de saf fonksiyonlardadır; WordPress gerekmez.
===================================================================== */

require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-filtre.php';

echo "\nGelişmiş filtreleme — girdi doğrulama\n";

/** Testlerde kullanılan alerjen tanımları (gerçek listeden bir alt küme). */
function qrms_test_alerjenler() {
	return array(
		'gluten' => array( 'label' => 'Glüten', 'icon' => '🌾' ),
		'sut'    => array( 'label' => 'Süt / Laktoz', 'icon' => '🥛' ),
		'soya'   => array( 'label' => 'Soya', 'icon' => '🌱' ),
	);
}

qrms_test(
	'GÜVENLİK: tanınmayan filtre anahtarları beyaz listede elenir',
	function () {
		$alerjenler = qrms_test_alerjenler();

		$temiz = RMA_Filtre::temizle_anahtarlar(
			array(
				'vegan',
				'<script>alert(1)</script>',
				"' OR 1=1--",
				'allergen_gluten',
				'allergen_../../wp-config',
				'rma_price',
				'badge_popular',
				'uydurma_filtre',
			),
			$alerjenler
		);

		qrms_assert_same(
			array( 'allergen_gluten', 'badge_popular', 'vegan' ),
			$temiz,
			'yalnızca tanınan anahtarlar kaldı ve sıralandı'
		);
	}
);

qrms_test(
	'aynı filtre kümesi farklı sırada gelse de aynı önbellek anahtarını üretir',
	function () {
		$alerjenler = qrms_test_alerjenler();

		$a = RMA_Filtre::temizle_anahtarlar( array( 'vegan', 'cal_300', 'allergen_sut' ), $alerjenler );
		$b = RMA_Filtre::temizle_anahtarlar( array( 'allergen_sut', 'vegan', 'cal_300' ), $alerjenler );

		qrms_assert_same( $a, $b, 'sıralama deterministik' );
		// Tekrar eden anahtar iki kez sayılmaz.
		qrms_assert_same(
			$a,
			RMA_Filtre::temizle_anahtarlar( array( 'vegan', 'vegan', 'cal_300', 'allergen_sut' ), $alerjenler ),
			'yinelenen anahtar teke düşer'
		);
	}
);

qrms_test(
	'GÜVENLİK: sayısal aralık girdileri kelepçelenir ve ters sınırlar takas edilir',
	function () {
		qrms_assert_same( array( 0, 0 ), RMA_Filtre::temizle_aralik( 'abc', '', 20000 ), 'metin girdi düşer' );
		qrms_assert_same( array( 0, 0 ), RMA_Filtre::temizle_aralik( -50, -10, 20000 ), 'negatif sıfıra kelepçelenir' );
		qrms_assert_same( array( 0, 20000 ), RMA_Filtre::temizle_aralik( '', 999999, 20000 ), 'tavan uygulanır' );
		qrms_assert_same( array( 100, 500 ), RMA_Filtre::temizle_aralik( 500, 100, 20000 ), 'ters sınırlar takas edilir' );
		qrms_assert_same( array( 0, 0 ), RMA_Filtre::temizle_aralik( array( 5 ), null, 20000 ), 'dizi/null düşer' );
	}
);

echo "\nGelişmiş filtreleme — sorgu ve PHP katmanı\n";

qrms_test(
	'meta tabanlı filtreler sorgu klozuna, laktozsuz alerjen klozuna düşer',
	function () {
		$kloz = RMA_Filtre::meta_klozlari( array( 'vegan', 'sugar_free', 'badge_new', 'halal', 'cal_300' ) );

		qrms_assert_same( 3, count( $kloz ), 'yalnızca meta filtreleri sorguya girer' );
		qrms_assert_same( 'rma_is_vegan', $kloz[0]['key'], 'vegan meta anahtarı' );
		qrms_assert_same( 'rma_is_sugar_free', $kloz[1]['key'], 'şekersiz meta anahtarı' );

		// Laktozsuz AYRI bir meta değil: mevcut alerjen NOT IN klozuna katılır.
		$haric = RMA_Filtre::haric_alerjenler(
			array( 'lactose_free', 'allergen_gluten', 'allergen_yok' ),
			array( 'gluten', 'sut', 'soya' )
		);

		qrms_assert_same( array( 'sut', 'gluten' ), $haric, 'laktozsuz süt alerjenine çevrildi, tanımsız slug elendi' );
	}
);

qrms_test(
	'helal filtresi: alkol/domuz alanı BOŞ olan ürün geçer, işaretli olan elenir',
	function () {
		$ctx = RMA_Filtre::php_baglami( array( 'halal' ) );

		// Kurulumların çoğunda bu meta hiç yazılmamıştır; "yok = helal değil"
		// saymak menüyü boşaltırdı.
		qrms_assert_true( RMA_Filtre::satir_gecer( array(), $ctx ), 'meta hiç yoksa geçer' );
		qrms_assert_true( RMA_Filtre::satir_gecer( array( 'alcohol' => '0', 'pork' => '0' ), $ctx ), 'işaretsiz geçer' );
		qrms_assert_false( RMA_Filtre::satir_gecer( array( 'alcohol' => '1' ), $ctx ), 'alkol elenir' );
		qrms_assert_false( RMA_Filtre::satir_gecer( array( 'pork' => '1' ), $ctx ), 'domuz türevi elenir' );
	}
);

qrms_test(
	'acılık filtresi 0-4 kademede çalışır, geçersiz meta acısız sayılır',
	function () {
		$acisiz = RMA_Filtre::php_baglami( array( 'no_spice' ) );

		qrms_assert_true( RMA_Filtre::satir_gecer( array( 'spicy' => '' ), $acisiz ), 'boş alan acısız' );
		qrms_assert_true( RMA_Filtre::satir_gecer( array( 'spicy' => 'orta acı' ), $acisiz ), 'serbest metin acısız sayılır' );
		qrms_assert_false( RMA_Filtre::satir_gecer( array( 'spicy' => '2' ), $acisiz ), 'orta acı elenir' );

		$cok_aci = RMA_Filtre::php_baglami( array( 'spicy_4' ) );
		qrms_assert_true( RMA_Filtre::satir_gecer( array( 'spicy' => '4' ), $cok_aci ), 'yeni 4. kademe' );

		// Birden çok kademe seçilince birleşim (OR) çalışır.
		$coklu = RMA_Filtre::php_baglami( array( 'spicy_1', 'spicy_2' ) );
		qrms_assert_true( RMA_Filtre::satir_gecer( array( 'spicy' => '1' ), $coklu ), 'az acı geçer' );
		qrms_assert_false( RMA_Filtre::satir_gecer( array( 'spicy' => '3' ), $coklu ), 'acı geçmez' );

		// no_spice ve spicy_0 aynı şeydir, iki ayrı kural üretmez.
		qrms_assert_same(
			RMA_Filtre::php_baglami( array( 'no_spice', 'spicy_0' ) ),
			RMA_Filtre::php_baglami( array( 'spicy_0' ) ),
			'no_spice = spicy_0'
		);
	}
);

qrms_test(
	'kalori filtresi: değeri girilmemiş ürün listeye ALINMAZ, en dar eşik kazanır',
	function () {
		$ctx = RMA_Filtre::php_baglami( array( 'cal_500' ) );

		qrms_assert_true( RMA_Filtre::satir_gecer( array( 'calories' => '420' ), $ctx ), '500 altı geçer' );
		qrms_assert_false( RMA_Filtre::satir_gecer( array( 'calories' => '640' ), $ctx ), '500 üstü elenir' );

		// Boş alan meta_query NUMERIC'te 0'a düşer ve her ürün "500 kcal altı"
		// sayılırdı; PHP katmanı bu yanlış beyanı engeller.
		qrms_assert_false( RMA_Filtre::satir_gecer( array( 'calories' => '' ), $ctx ), 'kalorisi girilmemiş ürün elenir' );
		qrms_assert_false( RMA_Filtre::satir_gecer( array(), $ctx ), 'alanı hiç olmayan ürün elenir' );

		// İki eşik birlikte seçilirse kesişim alınır (boş liste değil).
		$dar = RMA_Filtre::php_baglami( array( 'cal_500', 'cal_300' ) );
		qrms_assert_same( array( 0, 300 ), $dar['cal'], 'en dar eşik kazanır' );

		// Özel aralık da aynı üst sınıra yarışır.
		$ozel = RMA_Filtre::php_baglami( array( 'cal_700' ), array( 'cal' => array( 200, 400 ) ) );
		qrms_assert_same( array( 200, 400 ), $ozel['cal'], 'özel aralık hazır eşikten darsa kalır' );
	}
);

qrms_test(
	'fiyat aralığı ve tükendi filtresi',
	function () {
		$ctx = RMA_Filtre::php_baglami( array(), array( 'price' => array( 50, 100 ) ) );

		qrms_assert_true( RMA_Filtre::satir_gecer( array( 'price' => '75' ), $ctx ), 'aralık içi' );
		qrms_assert_true( RMA_Filtre::satir_gecer( array( 'price' => '99,50' ), $ctx ), 'virgüllü fiyat okunur' );
		qrms_assert_false( RMA_Filtre::satir_gecer( array( 'price' => '120' ), $ctx ), 'aralık dışı' );
		qrms_assert_false( RMA_Filtre::satir_gecer( array( 'price' => '' ), $ctx ), 'fiyatsız ürün elenir' );

		$stok = RMA_Filtre::php_baglami( array( 'in_stock' ) );
		qrms_assert_false( RMA_Filtre::satir_gecer( array( 'tukendi' => true ), $stok ), 'tükendi gizlenir' );
		qrms_assert_true( RMA_Filtre::satir_gecer( array( 'tukendi' => false ), $stok ), 'stoktaki kalır' );

		// Filtre yokken hiçbir ürün elenmez (varsayılan davranış korunur).
		qrms_assert_true( RMA_Filtre::satir_gecer( array( 'tukendi' => true ), array() ), 'bağlam boşsa herkes geçer' );
	}
);

echo "\nGelişmiş filtreleme — entegrasyon noktaları\n";

qrms_test(
	'AJAX ucu beyaz listeden geçer, aralıklar önbellek anahtarına girer',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-ajax.php' );

		qrms_assert_contains( 'RMA_Filtre::temizle_anahtarlar(', $kaynak, 'filtreler whitelist ile temizlenir' );
		qrms_assert_contains( 'RMA_Filtre::temizle_aralik(', $kaynak, 'aralıklar doğrulanır' );
		qrms_assert_contains( "'cr' => \$ranges['cal']", $kaynak, 'kalori aralığı önbellek anahtarında' );
		qrms_assert_contains( "'pr' => \$ranges['price']", $kaynak, 'fiyat aralığı önbellek anahtarında' );
		qrms_assert_contains( 'RMA_Filtre::satir_gecer(', $kaynak, 'PHP katmanı uygulanır' );

		// Boş durum: filtre varken çıkış yolu gösterilir, yokken eski metin kalır.
		qrms_assert_contains( 'Bu filtrelerle eşleşen ürün bulunamadı.', $kaynak, 'filtreli boş durum metni' );
		qrms_assert_contains( 'rma-empty-reset', $kaynak, 'filtreleri temizle butonu' );
		qrms_assert_contains( "esc_html( \$this->t( 'Ürün bulunamadı.' ) )", $kaynak, 'filtresiz boş durum korundu' );
	}
);

qrms_test(
	'acı seviyesi admin tarafında beyaz listeyle kaydedilir',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-post-types.php' );

		// Alan artık serbest metin değil; genel $fields döngüsünden çıkarıldı.
		qrms_assert_false(
			false !== strpos( $kaynak, "'rma_price', 'rma_spicy_level'" ),
			'acı seviyesi doğrulanmamış alan listesinde değil'
		);
		qrms_assert_contains( '$spicy_allowed', $kaynak, 'beyaz liste değişkeni' );
		qrms_assert_contains( 'RMA_Filtre::aci_seviyeleri()', $kaynak, 'kademeler kayıt defterinden' );
		qrms_assert_contains( "'rma_is_sugar_free'", $kaynak, 'şekersiz meta kaydedilir' );

		// Restoran sahibi hangi alanın hangi filtreyi beslediğini görür.
		qrms_assert_contains( 'Menüdeki “Filtrele” panelini besleyen alanlar', $kaynak, 'admin filtre rehberi' );
	}
);

qrms_test(
	'analitik menu_filter olayını yalnızca menü kayıt defterindeki anahtarlarla kabul eder',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php' );

		qrms_assert_contains( "'menu_filter'      => array(", $kaynak, 'beacon kuralı tanımlı' );
		qrms_assert_contains( "'item_name' => self::filtre_anahtarlari()", $kaynak, 'serbest metin kabul edilmez' );
		qrms_assert_contains( 'RMA_Filtre::anahtarlar(', $kaynak, 'beyaz liste menü modülünden okunur' );
		qrms_assert_contains( "'menu_filter',", $kaynak, 'olay tipi kayıtlı' );
		qrms_assert_contains( "'menu_filter'     => 30,", $kaynak, 'saklama süresi tanımlı' );

		// Menü görüntülemesi ZATEN rma_load_items'ta sayılıyor; beacon ikinci
		// bir menu_view yazmamalı.
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/rma-frontend.js' );
		qrms_assert_contains( "yaz('menu_filter'", $js, 'filtre olayı gönderilir' );
		qrms_assert_false( false !== strpos( $js, "yaz('menu_view'" ), 'görüntüleme çiftlenmez' );
	}
);

qrms_test(
	'CSV Şekersiz sütunu SONA eklendi — eski dosyalar bozulmaz',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-import-export.php' );

		// Sütun sayısı, import'un okuduğu en yüksek indeks + 1 olmalı.
		preg_match_all( '/\$d\[(\d+)\]/', $kaynak, $m );
		$en_yuksek = max( array_map( 'intval', $m[1] ) );

		qrms_assert_same( 23, $en_yuksek, 'Şekersiz sütunu 23. indekste' );
		qrms_assert_contains( "'rma_is_sugar_free'     => \$d[23] ?? '0'", $kaynak, 'sütun okunuyor' );
		qrms_assert_contains( "[ 'Şekersiz',", $kaynak, 'sütun rehberde' );
		qrms_assert_contains( '0-4 arası', $kaynak, 'acı sütunu güncellendi' );

		// Alerjen sütunu (22) yerinde kaldı: yeni sütun araya girmedi.
		qrms_assert_contains( "\$allergen_raw      = \$d[22] ?? ''", $kaynak, 'alerjen sütunu yerinde' );
	}
);

qrms_test(
	'filtre paneli ve chip çubuğu erişilebilir, RTL uyumlu yazıldı',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-frontend.php' );
		$js  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/rma-frontend.js' );
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/rma-frontend.css' );

		qrms_assert_contains( 'aria-expanded="false"', $php, 'tetikleyici durum bildirir' );
		qrms_assert_contains( 'id="rma-active-chips"', $php, 'chip çubuğu var' );
		qrms_assert_contains( 'aria-live="polite"', $php, 'chip değişimi okunur' );
		qrms_assert_contains( "setAttribute('aria-expanded', 'true')", $js, 'açılışta güncellenir' );
		qrms_assert_contains( 'function trapPanelFocus', $js, 'odak tuzağı' );
		qrms_assert_contains( 'function clearAllFilters', $js, 'tek temizleme noktası' );

		// Yeni kurallar yön-bağımsız: RTL dilde panel ters akmasın.
		$yeni = substr( $css, strpos( $css, 'GELİŞMİŞ FİLTRELEME' ) );
		qrms_assert_contains( 'padding-inline', $yeni, 'mantıksal iç boşluk' );
		qrms_assert_false(
			false !== strpos( $yeni, 'margin-left:' ) || false !== strpos( $yeni, 'padding-right:' ),
			'yeni blokta fiziksel yön kuralı yok'
		);
	}
);

qrms_test(
	'filtre kartı klavyeyle seçilebilir — tıklama işleyicisi tarayıcının geçişini geri almaz',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/rma-frontend.js' );

		// Eski kod odaklı checkbox'a Space basıldığında kutuyu işaretleyip
		// hemen geri alıyordu (fare ile çift geçiş birbirini götürdüğü için
		// hata yalnızca klavyede görünüyordu).
		qrms_assert_false(
			false !== strpos( $js, 'cb.checked = isChk;' ),
			'elle ters çevirme kaldırıldı'
		);
		qrms_assert_contains( "e.target.type !== 'checkbox'", $js, 'change dinleyicisi kurulu' );
		qrms_assert_contains( "kart.classList.toggle('selected', e.target.checked)", $js, 'sınıf checked ile eşitlenir' );
	}
);
