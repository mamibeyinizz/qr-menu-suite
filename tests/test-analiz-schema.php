<?php
/**
 * Analitik genel bakış sorgusu, şema ve performans testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

echo "\nQR Analiz — genel bakış tek sorgu\n";

/** genel_bakis()'in beklediği satır ve pc_tumu sayacı. */
function qrms_analitik_besle( $wpdb, $satir = array(), $pc_tumu = 900 ) {
	$wpdb->rows[] = array_merge(
		array(
			'mv_bugun' => 12,
			'mv_hafta' => 60,
			'mv_ay'    => 200,
			'pc_bugun' => 5,
			'pc_hafta' => 30,
			'uv_bugun' => 9,
			'masa_gun' => 4,
		),
		$satir
	);
	$wpdb->vars[] = $pc_tumu;
}

qrms_test(
	'sekiz ayrı COUNT sorgusu ikiye indi',
	function () {
		$wpdb = qrms_sayan_wpdb();
		qrms_analitik_besle( $wpdb );

		$genel = QRMS_Analitik::genel_bakis();

		qrms_assert_same( 2, count( $wpdb->queries ), 'toplam sorgu sayısı' );
		qrms_assert_same( 12, $genel['mv_bugun'], 'bugünkü görüntüleme' );
		qrms_assert_same( 900, $genel['pc_tumu'], 'tüm zamanlar tıklama' );
		qrms_assert_same( 4, $genel['masa_gun'], 'bugün hareket eden masa' );
	}
);

qrms_test(
	'tarihli kovalar İNDEKSLİ bir aralıkla sınırlanır',
	function () {
		// Regresyon koruması: bu sorgu bir ara WHERE'siz yazılmıştı ve 90
		// günlük tablonun tamamını satır satır tarıyordu. Alt sınır olmadan
		// idx_date/idx_td kullanılamaz.
		$wpdb = qrms_sayan_wpdb();
		qrms_analitik_besle( $wpdb );

		QRMS_Analitik::genel_bakis();

		qrms_assert_contains( 'WHERE created_at >=', $wpdb->queries[0], 'aralık sınırı var' );

		// Alt sınır ay başını da hafta başını da kapsamalı: ayın ilk
		// günlerinde "son 7 gün" penceresi önceki aya taşar.
		$ay_basi    = gmdate( 'Y-m-01' );
		$hafta_basi = gmdate( 'Y-m-d', strtotime( '-6 days' ) );
		$beklenen   = min( $ay_basi, $hafta_basi );

		qrms_assert_contains( $beklenen . ' 00:00:00', $wpdb->queries[0], 'alt sınır en eski kovayı kapsıyor' );

		// Tarih sınırı OLMAYAN tek kova ayrı ve index-only sayımdır.
		qrms_assert_contains( "COUNT(*)", $wpdb->queries[1], 'pc_tumu ayrı sayım' );
		qrms_assert_false(
			false !== strpos( $wpdb->queries[1], 'created_at' ),
			'pc_tumu tarih koşulu taşımaz'
		);
	}
);

qrms_test(
	'aralıkta kayıt yokken NULL toplamlar sıfıra iner',
	function () {
		$wpdb = qrms_sayan_wpdb();
		qrms_analitik_besle(
			$wpdb,
			array(
				'mv_bugun' => null,
				'mv_hafta' => null,
				'mv_ay'    => null,
				'pc_bugun' => null,
				'pc_hafta' => null,
				'uv_bugun' => null,
				'masa_gun' => null,
			),
			0
		);

		$genel = QRMS_Analitik::genel_bakis();

		foreach ( $genel as $anahtar => $deger ) {
			qrms_assert_same( 0, $deger, $anahtar . ' sıfır' );
		}
	}
);

qrms_test(
	'keyfi aralık özeti TEK indeksli sorguya iner',
	function () {
		$wpdb = qrms_sayan_wpdb();
		$wpdb->rows[] = array(
			'mv'          => 10,
			'pc'          => 4,
			'uv'          => 7,
			'masa_sayisi' => 3,
			'mv_onceki'   => 8,
			'pc_onceki'   => 2,
			'uv_onceki'   => 6,
		);

		$ozet = QRMS_Analitik::aralik_ozeti(
			'2026-03-10 00:00:00',
			'2026-03-16 23:59:59',
			'2026-03-03 00:00:00'
		);

		qrms_assert_same( 1, count( $wpdb->queries ), 'tek sorgu' );
		qrms_assert_same( 10, $ozet['mv'], 'görüntüleme' );
		qrms_assert_same( 8, $ozet['mv_onceki'], 'önceki pencere' );

		// Pencere İKİ UÇTAN sınırlı olmalı: aksi hâlde idx_date bir aralık
		// taraması olarak kullanılamaz ve tablo baştan sona taranır.
		qrms_assert_contains( 'WHERE created_at BETWEEN', $wpdb->queries[0], 'kapalı aralık' );
		qrms_assert_contains( '2026-03-03 00:00:00', $wpdb->queries[0], 'alt sınır önceki pencereden' );
		qrms_assert_contains( '2026-03-16 23:59:59', $wpdb->queries[0], 'üst sınır' );

		// Şimdiki/önceki ayrımı WHERE'de değil SUM/CASE içinde yapılır.
		qrms_assert_contains( "SUM(event_type='menu_view'     AND created_at >=", $wpdb->queries[0], 'kova koşulu' );
	}
);

qrms_test(
	'aralık özetinde kayıt yokken NULL toplamlar sıfıra iner',
	function () {
		$wpdb         = qrms_sayan_wpdb();
		$wpdb->rows[] = array(
			'mv'          => null,
			'pc'          => null,
			'uv'          => null,
			'masa_sayisi' => null,
			'mv_onceki'   => null,
			'pc_onceki'   => null,
			'uv_onceki'   => null,
		);

		foreach ( QRMS_Analitik::aralik_ozeti( 'a', 'b', 'c' ) as $anahtar => $deger ) {
			qrms_assert_same( 0, $deger, $anahtar . ' sıfır' );
		}
	}
);

qrms_test(
	'grafik keyfi aralıkta da sıfır doldurur ve indeksli kalır',
	function () {
		$wpdb = qrms_sayan_wpdb();
		// Yalnızca bir günde veri var; kalan günler sıfırla dolmalı.
		$wpdb->results[] = array(
			array(
				'k'  => '2026-03-11',
				'mv' => 5,
				'pc' => 2,
				'uv' => 3,
			),
		);

		$satirlar = QRMS_Analitik::grafik_araligi( 'daily', '2026-03-10 00:00:00', '2026-03-12 23:59:59' );

		qrms_assert_same( 3, count( $satirlar ), 'üç günün üçü de var' );
		qrms_assert_same( 0, $satirlar[0]['mv'], 'sessiz gün sıfırla dolar' );
		qrms_assert_same( 5, $satirlar[1]['mv'], 'veri olan gün' );
		qrms_assert_contains( 'created_at BETWEEN', $wpdb->queries[0], 'kapalı aralık' );
	}
);

qrms_test(
	'kırılım aralıkla uyumsuzsa en yakın anlamlıya düşer',
	function () {
		// Tek gün: yalnızca saatlik anlamlı (günlük tek çubuk olurdu).
		qrms_assert_same( array( 'hourly' ), QRMS_Analitik_Filtre::kirilimlar( 1 ), 'tek gün' );

		// Bir hafta: günlük; haftalık iki tam hafta istemeden anlamsız.
		qrms_assert_same( array( 'daily' ), QRMS_Analitik_Filtre::kirilimlar( 7 ), 'yedi gün' );

		// İki hafta ve üstü: haftalık da açılır.
		qrms_assert_same( array( 'daily', 'weekly' ), QRMS_Analitik_Filtre::kirilimlar( 14 ), 'iki hafta' );

		// İki ay ve üstü: aylık da açılır.
		qrms_assert_same( array( 'daily', 'weekly', 'monthly' ), QRMS_Analitik_Filtre::kirilimlar( 60 ), 'iki ay' );

		// Çok uzun aralıkta günlük düşer (yüzlerce çubuk okunmaz).
		qrms_assert_same( array( 'weekly', 'monthly' ), QRMS_Analitik_Filtre::kirilimlar( 200 ), 'uzun aralık' );

		// "Bugün" + aylık istenirse saatliğe düşülür, hata verilmez.
		qrms_assert_same(
			'hourly',
			QRMS_Analitik_Filtre::kirilim(
				array(
					'donem'   => 'bugun',
					'kirilim' => 'monthly',
				)
			),
			'geçersiz kırılım düzeltilir'
		);

		// Geçerli olan aynen kalır.
		qrms_assert_same(
			'weekly',
			QRMS_Analitik_Filtre::kirilim(
				array(
					'donem'   => 'ozel',
					'bas'     => '2026-01-01',
					'bit'     => '2026-03-01',
					'kirilim' => 'weekly',
				)
			),
			'geçerli kırılım korunur'
		);
	}
);

qrms_test(
	'filtre aralığı ve karşılaştırma penceresi eşit uzunluktadır',
	function () {
		$bugun = QRMS_Analitik_Filtre::aralik( array( 'donem' => 'bugun' ) );
		qrms_assert_same( 1, $bugun['gun'], 'bugün tek gün' );
		qrms_assert_contains( '00:00:00', $bugun['bas'], 'günün başı' );
		qrms_assert_contains( '23:59:59', $bugun['bit'], 'günün sonu' );

		$hafta = QRMS_Analitik_Filtre::aralik( array( 'donem' => 'hafta' ) );
		qrms_assert_same( 7, $hafta['gun'], 'son 7 gün' );

		$ozel = array(
			'donem' => 'ozel',
			'bas'   => '2026-03-10',
			'bit'   => '2026-03-12',
		);

		qrms_assert_same( 3, QRMS_Analitik_Filtre::aralik( $ozel )['gun'], 'özel aralık gün sayısı' );

		// Karşılaştırma penceresi aralığın hemen öncesinde ve aynı uzunlukta:
		// 10–12 Mart'ın öncesi 7–9 Mart'tır.
		qrms_assert_same( '2026-03-07 00:00:00', QRMS_Analitik_Filtre::onceki_baslangic( $ozel ), 'önceki pencere' );
	}
);

qrms_test(
	'masa filtresi İKİ sorguya birden uygulanır',
	function () {
		$wpdb = qrms_sayan_wpdb();
		qrms_analitik_besle( $wpdb );

		QRMS_Analitik::genel_bakis( 'masa-3' );

		// Filtre yalnızca birine uygulanırsa pc_tumu bütün masaları sayar ve
		// panel seçili masa için yanlış bir toplam gösterir.
		qrms_assert_contains( "masa_no = 'masa-3'", $wpdb->queries[0], 'aralık sorgusunda filtre' );
		qrms_assert_contains( "masa_no = 'masa-3'", $wpdb->queries[1], 'pc_tumu sorgusunda filtre' );
	}
);

echo "\nUzun dış istekler — bağlantı serbest bırakma\n";

qrms_test(
	'yardımcılar bağlantıyı kapatır ve GERİ AÇAR',
	function () {
		$wpdb = qrms_sayan_wpdb();

		$kapandi = qmo_db_serbest_birak();

		qrms_assert_true( $kapandi, 'bağlantı kapatıldı' );
		qrms_assert_same( 1, $wpdb->kapandi, 'close() çağrıldı' );

		qmo_db_geri_baglan( $kapandi );

		qrms_assert_same( 1, $wpdb->acildi, 'db_connect() çağrıldı' );
		qrms_assert_true( (bool) $wpdb->dbh, 'bağlantı yeniden hazır' );
	}
);

qrms_test(
	'kapatılmadıysa geri açma da denenmez',
	function () {
		$wpdb = qrms_sayan_wpdb();

		qmo_db_geri_baglan( false );

		qrms_assert_same( 0, $wpdb->acildi, 'gereksiz yeniden bağlanma yok' );
	}
);

qrms_test(
	'filtre ile tamamen kapatılabilir',
	function () {
		$wpdb = qrms_sayan_wpdb();

		// HyperDB / kalıcı bağlantı kullanan kurulumlar için çıkış kapısı.
		add_filter(
			'qmo_db_baglanti_serbest',
			function () {
				return false;
			}
		);

		qrms_assert_false( qmo_db_serbest_birak(), 'bırakma atlandı' );
		qrms_assert_same( 0, $wpdb->kapandi, 'bağlantıya dokunulmadı' );

		// Filtre sonraki testlere sızmasın.
		unset( $GLOBALS['qrms_test']['actions']['qmo_db_baglanti_serbest'] );
	}
);

qrms_test(
	'uzun çağrıların hepsi bırak/geri-aç çiftiyle sarılı',
	function () {
		// Sarmanın YARISI (bırakma var, geri açma yok) sessiz veri kaybı
		// demektir: kapalı bağlantıda sorgular false döner. Bu yüzden her
		// dosyada iki tarafın da bulunduğu doğrulanır.
		$dosyalar = array(
			'modules/qr-chatbot/includes/ajax-chat.php'         => array( 'qmo_db_serbest_birak', 'qmo_db_geri_baglan' ),
			'modules/qr-chatbot/rest-order.php'                 => array( 'qmo_db_serbest_birak', 'qmo_db_geri_baglan' ),
		);

		foreach ( $dosyalar as $yol => $cift ) {
			$kaynak = file_get_contents( QRMS_PLUGIN_DIR . $yol );

			qrms_assert_contains( $cift[0] . '()', $kaynak, $yol . ' bırakıyor' );
			qrms_assert_contains( $cift[1] . '(', $kaynak, $yol . ' geri açıyor' );
		}
	}
);

qrms_test(
	'bağlantı kapalıyken okunacak ayarlar önceden çözülüyor',
	function () {
		// get_option() kapalı bağlantıda sessizce false döner; çeviri o yüzden
		// sebepsiz hataya düşerdi. Anahtar ve model bırakmadan ÖNCE okunur.
		$siparis = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/rest-order.php' );

		$anahtar_konum = strpos( $siparis, '$api_key = get_option( \'gemini_api_key\' );' );
		$birakma_konum = strpos( $siparis, '$db_kapali = qmo_db_serbest_birak();', false !== $anahtar_konum ? $anahtar_konum : 0 );

		qrms_assert_true( false !== $anahtar_konum, 'anahtar önceden çözülüyor' );
		qrms_assert_true( false !== $birakma_konum, 'bırakma noktası var' );
		qrms_assert_true( $anahtar_konum < $birakma_konum, 'anahtar bırakmadan ÖNCE okunuyor' );
		qrms_assert_contains( 'qmo_not_cevir( $it[\'not\'], $dil, $api_key, $model )', $siparis, 'döngüye geçiriliyor' );
	}
);


/* ---------------------------------------------------------------------------
 * 15. YAVAŞ SORGU CEPHESİ — İNDEKSLER, N+1, CRON, TEŞHİS
 *
 * Hosting'in kesin teşhisi "bağlantı limiti değil, sorguların 1 saniyenin
 * altında bitmemesi" olduktan sonra eklenen korumalar.
 * ------------------------------------------------------------------------ */

echo "\nŞema — eksik indeksler\n";

/**
 * Bir CREATE TABLE metninde verilen indeks tanımlı mı?
 *
 * Sütun listesi boşluk farkına takılmasın diye normalize edilerek aranır.
 *
 * @param string $sema    CREATE TABLE metnini içeren kaynak.
 * @param string $sutunlar Ör. "status, created_at".
 * @return bool
 */
function qrms_indeks_var_mi( $sema, $sutunlar ) {
	$sutunlar = preg_replace( '/\s+/', '', $sutunlar );
	$sema     = preg_replace( '/\s+/', '', $sema );

	return false !== stripos( $sema, '(' . $sutunlar . ')' );
}

qrms_test(
	'yorum tablosu artık PRIMARY KEY dışında indeks taşıyor',
	function () {
		// Regresyon koruması: tablo uzun süre indekssizdi ve üzerindeki HER
		// sorgu tam tablo taraması + filesort yapıyordu.
		$sema = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/install.php' );

		qrms_assert_true(
			qrms_indeks_var_mi( $sema, 'status, created_at' ),
			'idx_status_created — ön yüz listesi ve sayaçlar'
		);
		qrms_assert_contains( 'KEY idx_created (created_at)', $sema, 'idx_created — filtresiz yönetim listesi' );
		qrms_assert_true(
			qrms_indeks_var_mi( $sema, 'is_active, sort_order' ),
			'form alanları — her ön yüz render\'ında sorgulanır'
		);
	}
);

qrms_test(
	'indeksler gerçekten çalışan sorgulara karşılık geliyor',
	function () {
		// İndeksin işe yaraması için sütun SIRASI sorgudakiyle uyumlu olmalı:
		// önce eşitlik (status), sonra sıralama (created_at).
		$liste = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/reviews-list.php' );

		qrms_assert_contains( 'WHERE status = 1 ORDER BY created_at DESC', $liste, 'ön yüz sorgusu' );

		// Yönetim sorgusu artık dinamik kurulur (sekme + durum filtresi); sütun
		// sırası üretilen SQL üzerinden doğrulanır.
		$wpdb = qrms_sayan_wpdb();

		qrm_pro_admin_fetch_reviews( 'bekleyen', 10, 1, '', 3.0 );

		qrms_assert_contains( 'WHERE status = 0 ORDER BY created_at DESC', $wpdb->queries[0], 'yönetim sorgusu' );
	}
);

qrms_test(
	'ödül ve gönderim tablolarının indeksleri de sorgularla eşleşiyor',
	function () {
		$odul = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/rewards/db.php' );

		qrms_assert_true( qrms_indeks_var_mi( $odul, 'status, created_at' ), 'ödül: durum filtresi + sıralama' );
		qrms_assert_contains( 'KEY idx_source_review (source_review_id)', $odul, 'ödül: yorum başına kod kontrolü' );

		$formlar = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/db.php' );

		qrms_assert_true(
			qrms_indeks_var_mi( $formlar, 'form_id, status, created_at' ),
			'gönderim: iki sütunluk filtre + sıralama'
		);
		qrms_assert_true( qrms_indeks_var_mi( $formlar, 'form_id, created_at' ), 'gönderim: durum filtresi yokken' );
	}
);

echo "\nŞema — güncelleme akışı\n";

qrms_test(
	'şema güncellemesi ön yüz isteğinde ÇALIŞMAZ',
	function () {
		// ALTER TABLE büyük bir tabloda saniyeler sürebilir. plugins_loaded'a
		// bağlanmış olsaydı bu, menüyü açan bir MÜŞTERİNİN isteğinde olurdu.
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/qr-menu-reviews.php' );

		qrms_assert_contains(
			"add_action('admin_init', 'qrm_pro_schema_maybe_upgrade'",
			$kaynak,
			'yalnızca yönetim isteğinde'
		);
		qrms_assert_false(
			false !== strpos( $kaynak, "add_action('plugins_loaded', 'qrm_pro_schema_maybe_upgrade'" ),
			'plugins_loaded\'a bağlı değil'
		);
		qrms_assert_contains( "current_user_can('manage_options')", $kaynak, 'yetki kontrolü var' );
	}
);

qrms_test(
	'başarısız ALTER "tamam" diye işaretlenmez',
	function () {
		// Veritabanı kullanıcısının ALTER yetkisi yoksa dbDelta sessizce
		// başarısız olur; sürüm damgası yine de atılırsa site kalıcı olarak
		// indekssiz kalır ve kimse fark etmez.
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/qr-menu-reviews.php' );

		// Sürüm damgası atan HER yol önce indeksi doğrulamalı: doğrulamayan
		// bir yol, ALTER yetkisi olmayan kurulumu kalıcı olarak indekssiz
		// bırakır ve admin_init'teki denetim bir daha hiç devreye girmez.
		$damgalar = array();
		$son      = 0;

		while ( false !== ( $son = strpos( $kaynak, 'update_option(QRM_PRO_SCHEMA_OPTION, QRM_PRO_SCHEMA_VERSION, false);', $son ) ) ) {
			$damgalar[] = $son;
			$son++;
		}

		qrms_assert_true( count( $damgalar ) >= 2, 'iki yol da damga atıyor' );

		foreach ( $damgalar as $i => $konum ) {
			// Damgadan hemen önceki 400 karakterde bir doğrulama çağrısı olmalı.
			$onceki = substr( $kaynak, max( 0, $konum - 400 ), min( 400, $konum ) );

			qrms_assert_true(
				false !== strpos( $onceki, 'qrm_pro_schema_indexes_ok()' ),
				'damga ' . ( $i + 1 ) . ' doğrulamanın ardında'
			);
		}

		qrms_assert_contains( 'SHOW INDEX FROM', $kaynak, 'gerçekten tabloya bakıyor' );
	}
);

// import_title_key() saf bir yardımcıdır; trait'i taşıyan minik bir sınıfla
// doğrudan çağrılır (asıl sınıf WordPress'in tamamına bağımlı).
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-import-export.php';

class RMA_Menu_Test_Import {
	use RMA_Import_Export_Trait;
}

echo "\nDöngü içi sorgular (N+1)\n";

qrms_test(
	'içe aktarma artık satır başına wp_posts taraması yapmıyor',
	function () {
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-import-export.php'
		);

		// wp_posts.post_title WordPress çekirdeğinde İNDEKSLİ DEĞİLDİR; satır
		// başına bir eşitlik sorgusu, satır başına bir tam tarama demekti.
		qrms_assert_false(
			false !== strpos( $kaynak, 'AND post_title = %s' ),
			'satır başına eşitlik sorgusu kalmadı'
		);
		qrms_assert_contains( 'post_title IN (', $kaynak, 'tek sorguda toplu arama' );

		// Harita döngüden ÖNCE kurulmalı, yoksa hiçbir şey kazanılmaz.
		$harita = strpos( $kaynak, '$baslik_haritasi = $this->import_title_map(' );
		$dongu  = strpos( $kaynak, "foreach ( \$data['items'] as \$item )" );

		qrms_assert_true( false !== $harita, 'harita kuruluyor' );
		qrms_assert_true( $harita < $dongu, 'döngüden önce kuruluyor' );
	}
);

qrms_test(
	'aynı dosyada tekrar eden başlık ikinci ürünü açmaz',
	function () {
		// Satır başına sorgu yapan eski kod, az önce oluşturulan kaydı
		// kendiliğinden buluyordu; harita bunu elle sürdürmeli.
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-import-export.php'
		);

		qrms_assert_contains(
			'$baslik_haritasi[ $this->import_title_key( $title ) ] = (int) $pid;',
			$kaynak,
			'yeni kayıt haritaya ekleniyor'
		);
	}
);

qrms_test(
	'başlık anahtarı MySQL harmanlaması gibi büyük/küçük harf ayırmaz',
	function () {
		$menu = new RMA_Menu_Test_Import();

		qrms_assert_same(
			$menu->import_title_key( '  Adana Kebap  ' ),
			$menu->import_title_key( 'adana kebap' ),
			'harf büyüklüğü ve kenar boşluğu yok sayılır'
		);
		qrms_assert_false(
			$menu->import_title_key( 'Adana Kebap' ) === $menu->import_title_key( 'Urfa Kebap' ),
			'farklı ürünler ayrı kalır'
		);
	}
);

qrms_test(
	'toplu yazımlar ürün başına ayrı INSERT açmıyor',
	function () {
		// Menünün tamamını kapsayan bir kampanyada bu, tek işlemde yüzlerce
		// sorgu demekti.
		$kampanya = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-kampanya-db.php' );
		$vitrin   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-vitrin-db.php' );
		$formlar  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/functions.php' );

		qrms_assert_contains( 'INSERT INTO {$tablo} (campaign_id, product_id, original_price)', $kampanya, 'kampanya toplu' );
		qrms_assert_contains( 'INSERT INTO {$urunler} (showcase_id, product_id, sort_order)', $vitrin, 'vitrin toplu' );
		qrms_assert_contains( 'INSERT INTO $table (form_id, field_key', $formlar, 'form alanları toplu' );

		// Parçalama olmadan tek dev sorgu max_allowed_packet'e takılabilir.
		qrms_assert_contains( 'array_chunk', $kampanya, 'kampanya parçalanıyor' );
	}
);

qrms_test(
	'toplu yazımda tip dönüşümü satır satır yazımdakiyle aynı',
	function () {
		$satirlar = RMA_Kampanya_DB::anlik_satirlari( '7', array( '12' => '19,90', 3 => 45.5 ) );

		qrms_assert_same( 2, count( $satirlar ), 'satır sayısı' );
		qrms_assert_same( 7, $satirlar[0]['campaign_id'], 'kampanya ID int' );
		qrms_assert_same( 12, $satirlar[0]['product_id'], 'ürün ID int' );
		qrms_assert_same( 19.0, $satirlar[0]['original_price'], 'fiyat float' );
		qrms_assert_same( 45.5, $satirlar[1]['original_price'], 'ondalık korunur' );
	}
);

echo "\nCron — ağır işlemler\n";

qrms_test(
	'analitik temizliği birikime yetişebiliyor',
	function () {
		// Eskiden GÜNDE tek bir 5000'lik parça siliniyordu: günde 5000'den
		// fazla olay üreten bir sitede temizlik hiçbir zaman yetişemez, tablo
		// sınırsız büyür ve üzerindeki her sorgu yavaşlardı.
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php' );

		qrms_assert_contains( 'while ( $tur < self::SAKLAMA_TUR', $kaynak, 'tur içinde döngü var' );
		qrms_assert_contains( 'qrms_analitik_temizlik_sure', $kaynak, 'süre bütçesi filtrelenebilir' );
		qrms_assert_contains( 'if ( $silinen < self::SAKLAMA_PARCA )', $kaynak, 'silinecek kalmayınca durur' );
		qrms_assert_contains( 'self::SAKLAMA_TUR', $kaynak, 'sonsuz döngüye karşı emniyet freni' );
		qrms_assert_contains( 'WHERE event_type = %s AND created_at < %s', $kaynak, 'silme idx_td kullanır' );
	}
);

qrms_test(
	'lisans cron\'u 15 sn\'lik istek boyunca bağlantı tutmuyor',
	function () {
		// WP cron bir ZİYARETÇİNİN isteği üzerinde çalışır; o ziyaretçinin
		// bağlantısı 15 saniye boyunca boşuna açık kalıyordu.
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'includes/class-license-client.php' );

		$birak  = strpos( $kaynak, '$db_kapali = self::db_serbest_birak();' );
		$istek  = strpos( $kaynak, '$response = wp_remote_post(' );
		$geri   = strpos( $kaynak, 'self::db_geri_baglan( $db_kapali );' );

		qrms_assert_true( false !== $birak, 'bağlantı bırakılıyor' );
		qrms_assert_true( $birak < $istek, 'istekten ÖNCE bırakılıyor' );
		qrms_assert_true( $istek < $geri, 'istekten SONRA geri açılıyor' );

		// store_result() option yazar; geri açma ondan önce olmalı.
		$yazma = strpos( $kaynak, "return self::store_result( 'unreachable'" );
		qrms_assert_true( $geri < $yazma, 'yazmadan önce geri açılıyor' );
	}
);

echo "\nYavaş sorgu teşhisi\n";

qrms_test(
	'üretimde tamamen sessizdir',
	function () {
		// WP_DEBUG ve SAVEQUERIES tanımlı değil (test ortamı = üretim gibi).
		qrms_assert_false( QRMS_Query_Monitor::etkin_mi(), 'kendiliğinden açılmaz' );
	}
);

qrms_test(
	'yalnızca eşiği aşan sorgular raporlanır, en yavaş önce',
	function () {
		$kayitlar = array(
			array( 'SELECT 1', 0.01, 'wpdb->query, hizli_fonksiyon' ),
			array( 'SELECT * FROM wp_qrm_reviews WHERE status = 1', 1.25, 'wpdb->get_results, qrm_pro_fetch_approved_reviews, qrm_pro_shortcode' ),
			array( 'SELECT COUNT(*) FROM wp_rma_analytics', 0.60, 'wpdb->get_var, QRMS_Analitik::genel_bakis' ),
			array( 'SELECT 2', 0.49, 'wpdb->query, sinirda_fonksiyon' ),
		);

		$yavas = QRMS_Query_Monitor::yavaslari_ayikla( $kayitlar, 0.5 );

		qrms_assert_same( 2, count( $yavas ), 'eşiğin altındakiler elendi' );
		qrms_assert_same( 1.25, $yavas[0]['sure'], 'en yavaş başta' );
		qrms_assert_contains( 'qrm_reviews', $yavas[0]['sorgu'], 'sorgu metni taşınıyor' );
	}
);

qrms_test(
	'çağrı zinciri okunabilir hâle getirilir',
	function () {
		// wpdb'nin kendi metotları teşhise katkı sağlamaz, elenir; kalan
		// zincir dıştan içe okunur.
		qrms_assert_same(
			'qrm_pro_shortcode -> qrm_pro_fetch_approved_reviews',
			QRMS_Query_Monitor::cagriyi_kisalt( 'wpdb->get_results, qrm_pro_fetch_approved_reviews, qrm_pro_shortcode' ),
			'zincir çevrildi ve wpdb halkaları atıldı'
		);
		qrms_assert_same( 'bilinmiyor', QRMS_Query_Monitor::cagriyi_kisalt( '' ), 'boş zincir' );
		qrms_assert_same( 'bilinmiyor', QRMS_Query_Monitor::cagriyi_kisalt( 'wpdb->query' ), 'yalnızca wpdb halkası' );
	}
);

qrms_test(
	'uzun sorgu metni günlüğü şişirmez',
	function () {
		$uzun = 'SELECT ' . str_repeat( 'sutun_adi, ', 200 ) . 'son FROM tablo';
		$kisa = QRMS_Query_Monitor::sorguyu_kisalt( $uzun );

		qrms_assert_true( mb_strlen( $kisa ) <= 301, 'kırpıldı' );
		qrms_assert_contains( '…', $kisa, 'kırpma işareti' );

		// Çok satırlı sorgu tek satıra iner (günlük satır satır okunur).
		qrms_assert_same(
			'SELECT a FROM b WHERE c = 1',
			QRMS_Query_Monitor::sorguyu_kisalt( "SELECT a\n  FROM b\n  WHERE c = 1" ),
			'satır sonları düzleştirildi'
		);
	}
);

/* ---------------------------------------------------------------------------
 * 15. Header Footer Builder (header-footer-builder)
 * ------------------------------------------------------------------------ */

