<?php
/**
 * Yorum istatistikleri ve yönetim listesi testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/dashboard.php';
require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/helpers.php';

/** Birleşik istatistik sorgusunun döndürdüğü satırın taklidi. */
function qrms_sahte_stat_satiri( $args = array() ) {
	return array_merge(
		array(
			'total'           => 40,
			'approved'        => 30,
			'avg_rating'      => 4.25,
			'google_eligible' => 22,
			'positive_total'    => 28,
			'positive_approved' => 24,
			'crit_1'          => 4.5,
			'crit_2'          => 3.5,
			'crit_3'          => 4.0,
			'crit_4'          => null,
			'crit_5'          => 2.0,
		),
		$args
	);
}

echo "\nYorum istatistikleri — tek sorgu\n";

qrms_test(
	'altı ayrı AVG/COUNT sorgusu TEK sorguya indi',
	function () {
		$wpdb = qrms_sayan_wpdb();
		$wpdb->rows[] = qrms_sahte_stat_satiri();

		$stats = qrm_pro_fetch_review_stats( 3.5 );

		qrms_assert_same( 1, count( $wpdb->queries ), 'toplam sorgu sayısı' );
		qrms_assert_same( 1, $wpdb->kac_kez( 'FROM wp_qrm_reviews' ), 'tablo bir kez taranır' );

		$sql = $wpdb->queries[0];

		// Beş kriterin de aynı SELECT'in içinde olması şart.
		for ( $i = 1; $i <= 5; $i++ ) {
			qrms_assert_contains( 'rating_' . $i, $sql, 'kriter ' . $i . ' aynı sorguda' );
		}

		qrms_assert_contains( 'AS google_eligible', $sql, 'Google eşiği aynı sorguda' );
		qrms_assert_contains( "rating >= 3.5", $sql, 'eşik değeri yerine kondu' );

		// Sekme sayaçları (olumlu/olumsuz) da AYNI taramadan gelir; sekmeye
		// tıklamak ekstra bir COUNT sorgusu açmamalı.
		qrms_assert_contains( 'AS positive_total', $sql, 'olumlu sayacı aynı sorguda' );
		qrms_assert_contains( 'AS positive_approved', $sql, 'olumlu/yayında sayacı aynı sorguda' );

		qrms_assert_same( 40, $stats['total'], 'toplam' );
		qrms_assert_same( 30, $stats['approved'], 'yayında' );
		qrms_assert_same( 10, $stats['pending'], 'bekleyen türetilir' );
		qrms_assert_same( 22, $stats['google_eligible'], 'eşiği geçen' );
		qrms_assert_same( 4.5, $stats['crit'][1], 'kriter 1 ortalaması' );
	}
);

qrms_test(
	'hiç oy almamış kriterin NULL ortalaması sıfıra iner',
	function () {
		$wpdb = qrms_sayan_wpdb();
		$wpdb->rows[] = qrms_sahte_stat_satiri();

		$stats = qrm_pro_fetch_review_stats( 3.5 );

		qrms_assert_same( 0.0, $stats['crit'][4], 'NULL kriter' );
	}
);

qrms_test(
	'okunamayan sorgu "tablo yok" ile karıştırılmaz ve önbelleğe yazılmaz',
	function () {
		$wpdb = qrms_sayan_wpdb();
		$wpdb->rows[] = null; // Sorgu başarısız.

		qrms_assert_same( null, qrm_pro_fetch_review_stats( 3.5 ), 'ham çekim null döner' );

		$stats = qrm_pro_review_stats( true );

		qrms_assert_true( $stats['table_ok'], 'tablo var sayılır (yanlış tanı basılmaz)' );
		qrms_assert_same( 0, $stats['total'], 'sayaçlar sıfır' );
		qrms_assert_false(
			get_transient( QRM_PRO_STATS_TRANSIENT ),
			'başarısız okuma önbelleğe yazılmaz'
		);
	}
);

echo "\nYorum istatistikleri — önbellek\n";

qrms_test(
	'ikinci çağrı veritabanına hiç gitmez',
	function () {
		$wpdb = qrms_sayan_wpdb();
		$wpdb->rows[] = qrms_sahte_stat_satiri();

		$ilk = qrm_pro_review_stats( true );
		$sorgu_sayisi = count( $wpdb->queries );

		// Memo devrede: aynı istek içinde ikinci çağrı sorgu açmaz.
		$ikinci = qrm_pro_review_stats();
		qrms_assert_same( $sorgu_sayisi, count( $wpdb->queries ), 'memo isabet etti' );
		qrms_assert_same( $ilk['total'], $ikinci['total'], 'aynı sonuç' );

		// Memo düşse bile transient devrede.
		unset( $GLOBALS['qrm_pro_stats_memo'] );
		$ucuncu = qrm_pro_review_stats();
		qrms_assert_same( $sorgu_sayisi, count( $wpdb->queries ), 'transient isabet etti' );
		qrms_assert_same( $ilk['approved'], $ucuncu['approved'], 'aynı sonuç' );
	}
);

qrms_test(
	'flush hem transient\'i hem istek içi memo\'yu temizler',
	function () {
		$wpdb = qrms_sayan_wpdb();
		$wpdb->rows[] = qrms_sahte_stat_satiri();
		$wpdb->rows[] = qrms_sahte_stat_satiri( array( 'total' => 41, 'approved' => 31 ) );

		qrms_assert_same( 40, qrm_pro_review_stats( true )['total'], 'ilk okuma' );

		qrm_pro_flush_review_stats();

		qrms_assert_false( get_transient( QRM_PRO_STATS_TRANSIENT ), 'transient gitti' );
		qrms_assert_false( isset( $GLOBALS['qrm_pro_stats_memo'] ), 'memo gitti' );

		// Yeni yorum sonrası sayaç GERÇEKTEN tazelenmeli; bayat kalırsa
		// yönetici onay bekleyen yorumu hiç görmez.
		qrms_assert_same( 41, qrm_pro_review_stats()['total'], 'tazelenmiş sayaç' );
	}
);

qrms_test(
	'Google eşiği değişince saklanan sonuç kabul edilmez',
	function () {
		$wpdb = qrms_sayan_wpdb();
		$wpdb->rows[] = qrms_sahte_stat_satiri();
		$wpdb->rows[] = qrms_sahte_stat_satiri( array( 'google_eligible' => 5 ) );

		$ayarlar = qrm_pro_get_settings();
		$ayarlar['google_review_threshold'] = 3.5;
		update_option( 'qrm_settings', $ayarlar );

		qrms_assert_same( 22, qrm_pro_review_stats( true )['google_eligible'], 'eşik 3.5' );

		// Eşik değişti: aynı transient artık geçerli değil.
		unset( $GLOBALS['qrm_pro_stats_memo'] );
		$ayarlar['google_review_threshold'] = 4.5;
		update_option( 'qrm_settings', $ayarlar );

		qrms_assert_same( 5, qrm_pro_review_stats()['google_eligible'], 'eşik 4.5 ile yeniden sorulur' );
	}
);

echo "\nYönetimdeki yorum listesi — sayfalama\n";

qrms_test(
	'liste sorgusu LIMIT/OFFSET taşır — üç filtrede de',
	function () {
		$wpdb = qrms_sayan_wpdb();

		qrm_pro_admin_fetch_reviews( '', 25, 3 );
		qrms_assert_contains( 'LIMIT 25 OFFSET 50', $wpdb->queries[0], 'tümü' );
		qrms_assert_false( false !== stripos( $wpdb->queries[0], 'WHERE' ), 'tümünde durum koşulu yok' );

		qrm_pro_admin_fetch_reviews( 'bekleyen', 10, 1 );
		qrms_assert_contains( 'status = 0', $wpdb->queries[1], 'bekleyen filtresi' );
		qrms_assert_contains( 'LIMIT 10 OFFSET 0', $wpdb->queries[1], 'ilk sayfa' );

		qrm_pro_admin_fetch_reviews( 'onayli', 10, 2 );
		qrms_assert_contains( 'status = 1', $wpdb->queries[2], 'onaylı filtresi' );
		qrms_assert_contains( 'LIMIT 10 OFFSET 10', $wpdb->queries[2], 'ikinci sayfa' );
	}
);

qrms_test(
	'sayfa numarası geçerli aralığa çekilir',
	function () {
		// Elle girilen &paged=9999 boş bir OFFSET'le veritabanına gitmemeli.
		qrms_assert_same( 4, qrm_pro_admin_reviews_clamp_page( 9999, 100, 25 ), 'son sayfa' );
		qrms_assert_same( 1, qrm_pro_admin_reviews_clamp_page( 0, 100, 25 ), 'sıfır → ilk sayfa' );
		qrms_assert_same( 1, qrm_pro_admin_reviews_clamp_page( -3, 100, 25 ), 'negatif → ilk sayfa' );
		qrms_assert_same( 1, qrm_pro_admin_reviews_clamp_page( 5, 0, 25 ), 'kayıt yokken tek sayfa' );
		qrms_assert_same( 3, qrm_pro_admin_reviews_clamp_page( 3, 100, 25 ), 'geçerli sayfa korunur' );
	}
);

qrms_test(
	'sayfalama toplamı EK SORGU açmadan istatistikten okunur',
	function () {
		$stats = array( 'total' => 40, 'approved' => 30, 'pending' => 10 );

		qrms_assert_same( 40, qrm_pro_admin_reviews_total( '', $stats ), 'tümü' );
		qrms_assert_same( 10, qrm_pro_admin_reviews_total( 'bekleyen', $stats ), 'bekleyen' );
		qrms_assert_same( 30, qrm_pro_admin_reviews_total( 'onayli', $stats ), 'onaylı' );
	}
);

echo "\nYönetimdeki yorum listesi — üç sekme\n";

qrms_test(
	'olumlu/olumsuz eşiği TEK yerden gelir ve filtreyle değişir',
	function () {
		qrms_assert_same( 3.0, qrm_pro_sentiment_threshold(), 'varsayılan eşik' );
		qrms_assert_same( 3.0, (float) QRM_PRO_SENTIMENT_THRESHOLD, 'sabit ile aynı' );

		add_filter(
			'qrm_pro_sentiment_threshold',
			function () {
				return 4.0;
			}
		);

		qrms_assert_same( 4.0, qrm_pro_sentiment_threshold(), 'filtre geçerli' );

		// Aralık dışı bir değer sorguya sızmamalı.
		unset( $GLOBALS['qrms_test']['actions']['qrm_pro_sentiment_threshold'] );
		add_filter(
			'qrm_pro_sentiment_threshold',
			function () {
				return 99;
			}
		);

		qrms_assert_same( 5.0, qrm_pro_sentiment_threshold(), '0-5 aralığına sıkışır' );

		unset( $GLOBALS['qrms_test']['actions']['qrm_pro_sentiment_threshold'] );
	}
);

qrms_test(
	'bilinmeyen sekme değeri sorguya sızmaz',
	function () {
		qrms_assert_same( '', qrm_pro_admin_review_tab( '' ), 'boş -> tümü' );
		qrms_assert_same( 'olumlu', qrm_pro_admin_review_tab( 'olumlu' ), 'olumlu' );
		qrms_assert_same( 'olumsuz', qrm_pro_admin_review_tab( 'olumsuz' ), 'olumsuz' );
		qrms_assert_same( '', qrm_pro_admin_review_tab( 'notr' ), 'bilinmeyen -> tümü' );
		qrms_assert_same( '', qrm_pro_admin_review_tab( '1 OR 1=1' ), 'enjeksiyon denemesi -> tümü' );
		qrms_assert_same( '', qrm_pro_admin_review_tab( array( 'olumlu' ) ), 'dizi -> tümü' );

		// Nötr kategori yok: her yorum iki sekmeden birine düşer.
		qrms_assert_same(
			array( '', 'olumlu', 'olumsuz' ),
			array_keys( qrm_pro_admin_review_tabs() ),
			'üç sekme'
		);
	}
);

qrms_test(
	'sekme filtresi SQL tarafında uygulanır',
	function () {
		// Asıl mesele bu: tablonun tamamı PHP'ye çekilip orada elenirse, çok
		// yorumlu bir sitede sayfa açılmaz olur.
		$wpdb = qrms_sayan_wpdb();

		qrm_pro_admin_fetch_reviews( '', 25, 1, 'olumlu', 3.0 );
		qrms_assert_contains( 'WHERE rating >= 3', $wpdb->queries[0], 'olumlu koşulu SQL\'de' );
		qrms_assert_contains( 'LIMIT 25 OFFSET 0', $wpdb->queries[0], 'sayfalama duruyor' );

		qrm_pro_admin_fetch_reviews( '', 25, 2, 'olumsuz', 3.0 );
		qrms_assert_contains( 'WHERE rating < 3', $wpdb->queries[1], 'olumsuz koşulu SQL\'de' );
		qrms_assert_contains( 'LIMIT 25 OFFSET 25', $wpdb->queries[1], 'ikinci sayfa' );

		// Sekme ve durum filtresi birlikte de tek sorguda birleşir.
		qrm_pro_admin_fetch_reviews( 'bekleyen', 10, 1, 'olumsuz', 3.0 );
		qrms_assert_contains( 'WHERE status = 0 AND rating < 3', $wpdb->queries[2], 'iki filtre birlikte' );

		// Eşik filtreden geliyorsa da sorguya o değer girer.
		qrm_pro_admin_fetch_reviews( '', 10, 1, 'olumlu', 4.5 );
		qrms_assert_contains( 'rating >= 4.5', $wpdb->queries[3], 'eşik değeri yerine kondu' );
	}
);

qrms_test(
	'sekme sayaçları ve sayfalama toplamı EK SORGU açmaz',
	function () {
		$wpdb = qrms_sayan_wpdb();
		$wpdb->rows[] = qrms_sahte_stat_satiri();

		$stats = qrm_pro_review_stats( true );

		qrms_assert_same( 1, count( $wpdb->queries ), 'tek sorgu' );

		// 40 kayıt, 28'i olumlu -> 12'si olumsuz; nötr kova yok.
		qrms_assert_same( 28, $stats['sentiment']['olumlu']['total'], 'olumlu toplam' );
		qrms_assert_same( 12, $stats['sentiment']['olumsuz']['total'], 'olumsuz toplam' );
		qrms_assert_same(
			$stats['total'],
			$stats['sentiment']['olumlu']['total'] + $stats['sentiment']['olumsuz']['total'],
			'iki sekme toplamı = tüm yorumlar'
		);

		// 30 yayında, 24'ü olumlu -> olumsuz yayında 6, olumsuz bekleyen 6.
		qrms_assert_same( 24, $stats['sentiment']['olumlu']['approved'], 'olumlu yayında' );
		qrms_assert_same( 4, $stats['sentiment']['olumlu']['pending'], 'olumlu bekleyen' );
		qrms_assert_same( 6, $stats['sentiment']['olumsuz']['approved'], 'olumsuz yayında' );
		qrms_assert_same( 6, $stats['sentiment']['olumsuz']['pending'], 'olumsuz bekleyen' );

		// Sayfalama toplamı sekme + durum kombinasyonunda da aynı diziden okunur.
		qrms_assert_same( 28, qrm_pro_admin_reviews_total( '', $stats, 'olumlu' ), 'olumlu / tümü' );
		qrms_assert_same( 6, qrm_pro_admin_reviews_total( 'bekleyen', $stats, 'olumsuz' ), 'olumsuz / bekleyen' );
		qrms_assert_same( 40, qrm_pro_admin_reviews_total( '', $stats ), 'sekmesiz davranış korunur' );

		qrms_assert_same( 1, count( $wpdb->queries ), 'sayaçlar için ek sorgu yok' );
	}
);

qrms_test(
	'olumlu/olumsuz eşiği değişince saklanan sayaçlar kabul edilmez',
	function () {
		$wpdb = qrms_sayan_wpdb();
		$wpdb->rows[] = qrms_sahte_stat_satiri();
		$wpdb->rows[] = qrms_sahte_stat_satiri( array( 'positive_total' => 9 ) );

		qrms_assert_same( 28, qrm_pro_review_stats( true )['sentiment']['olumlu']['total'], 'eşik 3.0' );

		unset( $GLOBALS['qrm_pro_stats_memo'] );
		add_filter(
			'qrm_pro_sentiment_threshold',
			function () {
				return 4.0;
			}
		);

		qrms_assert_same( 9, qrm_pro_review_stats()['sentiment']['olumlu']['total'], 'eşik 4.0 ile yeniden sorulur' );

		unset( $GLOBALS['qrms_test']['actions']['qrm_pro_sentiment_threshold'] );
	}
);

qrms_test(
	'LIMIT\'siz "SELECT *" ekrana geri sızmadı',
	function () {
		// Regresyon koruması: liste sorgusu kaynakta LIMIT'siz yazılamaz.
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/dashboard.php'
		);

		qrms_assert_false(
			(bool) preg_match( '/SELECT \* FROM \{?\$?\w+\}?(?![^"\']*LIMIT)[^"\']*["\']/', $kaynak ),
			'LIMIT\'siz tam tablo sorgusu yok'
		);
		qrms_assert_contains( 'LIMIT %d OFFSET %d', $kaynak, 'sayfalı sorgu' );
	}
);

echo "\nOkunmamış form gönderimi sayacı\n";

qrms_test(
	'sayaç her admin sayfasında yeniden sorulmaz',
	function () {
		// Bu sayaç sol menü etiketinden okunur, yani wp-admin'in HER
		// sayfasında çalışıyordu. Artık transient'ten gelir.
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/functions.php'
		);

		// Yalnızca ilgili fonksiyonun gövdesine bakılır; aynı sayım deseni
		// dosyanın başka yerlerinde de geçiyor.
		$bas    = strpos( $kaynak, 'function qrm_cf_unread_total()' );
		$govde  = substr( $kaynak, $bas, strpos( $kaynak, 'function qrm_cf_flush_unread_total()' ) - $bas );

		$okuma = strpos( $govde, 'get_transient(QRM_CF_UNREAD_TRANSIENT)' );
		$sorgu = strpos( $govde, 'SELECT COUNT(*) FROM ' . '$table' );

		qrms_assert_true( false !== $bas, 'fonksiyon bulundu' );
		qrms_assert_true( false !== $okuma, 'önbellekten okuyor' );
		qrms_assert_true( false !== $sorgu, 'sorgu hâlâ var (önbellek boşken)' );
		qrms_assert_true( $okuma < $sorgu, 'önce önbelleğe, sonra veritabanına bakılıyor' );
		qrms_assert_contains( 'set_transient(QRM_CF_UNREAD_TRANSIENT', $govde, 'sonuç saklanıyor' );
	}
);

qrms_test(
	'sayacı değiştiren her yol önbelleği temizler',
	function () {
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/functions.php'
		);

		// Beş yazma yolu: yeni gönderim, durum değişikliği, toplu okundu,
		// gönderim silme, form silme (gönderimlerini de siler).
		qrms_assert_true(
			substr_count( $kaynak, 'qrm_cf_flush_unread_total();' ) >= 5,
			'bütün yazma yolları temizliyor'
		);
	}
);

