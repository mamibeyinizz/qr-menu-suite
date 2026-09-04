<?php
/**
 * Güvenlik Ayarı ve Yorum & Feedback testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/qr-masa-oturum-guvenligi/module.php';

echo "\nGüvenlik Ayarı sayfaları\n";

qrms_test(
	'iki ekran da kayıt defterinde ve her birinin callback\'i var',
	function () {
		$pages = qrms_module_qr_masa_oturum_guvenligi_sayfalar();

		qrms_assert_same(
			array( QRMS_GUVENLIK_OTURUM_SAYFA, QRMS_GUVENLIK_FIREBASE_SAYFA ),
			array_keys( $pages ),
			'sayfa listesi'
		);

		foreach ( $pages as $slug => $page ) {
			foreach ( array( 'title', 'render', 'desc', 'icon' ) as $key ) {
				qrms_assert_true( ! empty( $page[ $key ] ), $slug . ' -> ' . $key . ' dolu' );
			}

			qrms_assert_same( 0, strpos( $page['icon'], 'dashicons-' ), $slug . ' ikonu dashicon' );
		}
	}
);

qrms_test(
	'Firebase ekranının ADRESİ taşınmadan önceki adrestir',
	function () {
		// Ekran qr-analiz'den buraya taşındı; canlı sitelerdeki yer imleri ve
		// dahili bağlantılar kırılmasın diye slug DEĞERİ korunur.
		qrms_assert_same( 'qrms-analiz-ayarlar', QRMS_GUVENLIK_FIREBASE_SAYFA, 'eski adres' );

		qrms_assert_false(
			QRMS_GUVENLIK_FIREBASE_SAYFA === QRMS_Admin::get_module_page_slug( 'qr-masa-oturum-guvenligi' ),
			'modül satırıyla çakışmaz'
		);
	}
);

qrms_test(
	'hub, iki ekranı da kart olarak basar',
	function () {
		ob_start();
		qrms_module_qr_masa_oturum_guvenligi_hub();
		$html = ob_get_clean();

		qrms_assert_contains( 'qrms-hub-grid', $html, 'ortak kart ızgarası' );
		qrms_assert_contains( 'page=' . QRMS_GUVENLIK_OTURUM_SAYFA, $html, 'oturum kartı' );
		qrms_assert_contains( 'page=' . QRMS_GUVENLIK_FIREBASE_SAYFA, $html, 'Firebase kartı' );
		qrms_assert_contains( 'Güvenlik Ayarı', $html, 'hub başlığı' );
	}
);

qrms_test(
	'modül aktifken iki ekran da gizli sayfa olarak kaydedilir',
	function () {
		$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] = array(
			qrms_submenu_satiri( 'Güvenlik Ayarı', QRMS_Admin::get_module_page_slug( 'qr-masa-oturum-guvenligi' ) ),
		);

		qrms_module_qr_masa_oturum_guvenligi_admin_menu();

		qrms_assert_same(
			array( QRMS_GUVENLIK_OTURUM_SAYFA, QRMS_GUVENLIK_FIREBASE_SAYFA ),
			array_map(
				function ( $item ) {
					return $item['slug'];
				},
				$GLOBALS['qrms_test']['submenus']
			),
			'kaydedilen sayfalar'
		);

		qrms_assert_true( QRMS_Admin::is_module_subpage( QRMS_GUVENLIK_FIREBASE_SAYFA ), 'kayıt defterinde' );

		// Kayıt gerçek bir alt menüdir (parent: MENU_SLUG) — route çözümü buna
		// bağlıdır; menüden düşürme işi admin_head'de yapılır.
		qrms_assert_same( QRMS_Admin::MENU_SLUG, $GLOBALS['qrms_test']['submenus'][0]['parent'], 'üst menü' );
	}
);

qrms_test(
	'modül lisansta aktif değilken hiçbir sayfa kaydedilmez',
	function () {
		qrms_module_qr_masa_oturum_guvenligi_admin_menu();

		qrms_assert_same( array(), $GLOBALS['qrms_test']['submenus'], 'kayıt yok' );
	}
);

/* ---------------------------------------------------------------------------
 * 8z. Yorum & Feedback — yorum listesinin SAYFALAMASI
 * ------------------------------------------------------------------------ */

// Yalnızca fonksiyon tanımları; hook kaydı yok. Sıra qr-menu-reviews.php'deki
// gerçek yükleme sırasıyla aynı: reviews-list.php içindeki
// qrm_pro_sanitize_reviews_list_query() fotoğraf filtresini kapatabilmek için
// qrm_pro_media_is_enabled()'ı (review-media.php) çağırır; masa.php ve
// consent.php da submit-review.php'nin bağımlılığıdır (aşağıda ayrıca
// require edilir, burada tekrar yüklenmesin diye atlanır).
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/settings.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/review-media.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/reviews-list.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/form-steps.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/form-render.php';

/**
 * Yorum listesi sorguları için $wpdb taklidi.
 *
 * prepare() çekirdekteki gibi yer tutucuları doldurur; testler böylece
 * ÜRETİLEN SQL'i — özellikle LIMIT/OFFSET değerlerini — doğrulayabilir.
 * (6c bölümünün QRMS_Test_Wpdb'si ayrıdır; bu sınıf
 * yalnızca aşağıdaki testler için $GLOBALS['wpdb']'ye takılır.)
 */
class QRMS_Yorum_Wpdb {
	public $prefix  = 'wp_';
	public $queries = array();
	public $results = array();
	public $vars    = array();

	public function prepare( $sql, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		return preg_replace_callback(
			'/%[dsf]/',
			function ( $m ) use ( &$args ) {
				$value = array_shift( $args );

				if ( '%d' === $m[0] ) {
					return (string) (int) $value;
				}
				if ( '%f' === $m[0] ) {
					return (string) (float) $value;
				}

				return "'" . str_replace( "'", "\\'", (string) $value ) . "'";
			},
			$sql
		);
	}

	public function get_results( $sql, $mode = null ) {
		$this->queries[] = $sql;

		return array_shift( $this->results ) ?: array();
	}

	public function get_var( $sql ) {
		$this->queries[] = $sql;

		return array_shift( $this->vars );
	}

	public function get_row( $sql ) {
		$this->queries[] = $sql;

		return array_shift( $this->vars );
	}

	public function son_sorgu() {
		return end( $this->queries ) ?: '';
	}
}

/**
 * Yorum testleri için taze bir $wpdb takar.
 *
 * @return QRMS_Yorum_Wpdb
 */
function qrms_yorum_wpdb() {
	$GLOBALS['wpdb'] = new QRMS_Yorum_Wpdb();

	return $GLOBALS['wpdb'];
}

/**
 * Test için sahte yorum satırı.
 *
 * @param int $id Satır kimliği.
 * @return object
 */
function qrms_sahte_yorum( $id ) {
	return (object) array(
		'id'            => $id,
		'rating'        => 4.5,
		'comment'       => 'Yorum ' . $id,
		'customer_name' => 'Müşteri ' . $id,
		'is_anonymous'  => 0,
		'created_at'    => '2026-01-01 12:00:00',
	);
}

echo "\nYorum listesi sayfalaması\n";

qrms_test(
	'sayfa boyutu ayardan gelir',
	function () {
		qrms_assert_same( 3, qrm_pro_reviews_page_size( array( 'reviews_per_page' => '3' ) ), '3 yorum' );
		qrms_assert_same( 5, qrm_pro_reviews_page_size( array( 'reviews_per_page' => '5' ) ), '5 yorum' );
	}
);

qrms_test(
	'"Tümü" ayarı sınırsız sorguya DÖNÜŞMEZ, üst sınıra çekilir',
	function () {
		// Asıl düzeltme bu: eskiden 'all' seçildiğinde sorgu LIMIT'siz
		// çalışıyor, tüm onaylı yorumlar çekilip HTML'e basılıyordu.
		$boyut = qrm_pro_reviews_page_size( array( 'reviews_per_page' => 'all' ) );

		qrms_assert_same( 50, $boyut, 'üst sınıra çekilir' );
		qrms_assert_true( $boyut > 0 && $boyut <= 50, 'her koşulda sınırlı' );
	}
);

qrms_test(
	'bozuk ya da eksik ayar güvenli varsayılana düşer',
	function () {
		qrms_assert_same( 3, qrm_pro_reviews_page_size( array() ), 'ayar yok' );
		qrms_assert_same( 3, qrm_pro_reviews_page_size( array( 'reviews_per_page' => '0' ) ), 'sıfır' );
		qrms_assert_same( 3, qrm_pro_reviews_page_size( array( 'reviews_per_page' => '-7' ) ), 'negatif' );
		qrms_assert_same( 3, qrm_pro_reviews_page_size( array( 'reviews_per_page' => 'abc' ) ), 'metin' );
	}
);

qrms_test(
	'sayfa boyutu üst sınırı filtreyle daraltılabilir',
	function () {
		add_filter(
			'qrm_reviews_max_page_size',
			function () {
				return 10;
			}
		);

		qrms_assert_same( 10, qrm_pro_reviews_page_size( array( 'reviews_per_page' => 'all' ) ), '"tümü" daralır' );
		qrms_assert_same( 5, qrm_pro_reviews_page_size( array( 'reviews_per_page' => '5' ) ), 'sınır altı korunur' );
	}
);

qrms_test(
	'sorgu GERÇEKTEN LIMIT ve OFFSET taşır',
	function () {
		$db = qrms_yorum_wpdb();
		$db->results[] = array( qrms_sahte_yorum( 1 ), qrms_sahte_yorum( 2 ) );

		qrm_pro_fetch_approved_reviews( 3, 6 );

		$sorgu = $db->son_sorgu();

		qrms_assert_true( false !== strpos( $sorgu, 'WHERE status = 1' ), 'yalnızca onaylılar' );
		// Sayfa boyutundan BİR FAZLA istenir: fazladan satır "daha var mı?"
		// sorusunu ayrı bir COUNT sorgusu olmadan cevaplar.
		qrms_assert_true( false !== strpos( $sorgu, 'LIMIT 4' ), 'LIMIT = boyut + 1' );
		qrms_assert_true( false !== strpos( $sorgu, 'OFFSET 6' ), 'OFFSET uygulanır' );
	}
);

qrms_test(
	'fazladan satır listeye girmez, yalnızca "daha var" der',
	function () {
		// 3 istendi, 4 döndü -> devamı var, ama kullanıcıya 3 kart gider.
		$db = qrms_yorum_wpdb();
		$db->results[] = array(
			qrms_sahte_yorum( 1 ),
			qrms_sahte_yorum( 2 ),
			qrms_sahte_yorum( 3 ),
			qrms_sahte_yorum( 4 ),
		);

		$sayfa = qrm_pro_fetch_approved_reviews( 3, 0 );

		qrms_assert_same( 3, count( $sayfa['rows'] ), 'sayfa boyutu kadar satır' );
		qrms_assert_true( $sayfa['has_more'], 'devamı var' );
		qrms_assert_same( 3, (int) $sayfa['rows'][2]->id, 'fazladan satır atıldı' );
	}
);

qrms_test(
	'son sayfada "daha fazla" denmez',
	function () {
		$db = qrms_yorum_wpdb();
		$db->results[] = array( qrms_sahte_yorum( 1 ), qrms_sahte_yorum( 2 ) );

		$sayfa = qrm_pro_fetch_approved_reviews( 3, 0 );

		qrms_assert_same( 2, count( $sayfa['rows'] ), 'gelen satırlar' );
		qrms_assert_false( $sayfa['has_more'], 'devamı yok' );
	}
);

qrms_test(
	'hiç yorum yokken boş sayfa döner',
	function () {
		qrms_yorum_wpdb();

		$sayfa = qrm_pro_fetch_approved_reviews( 3, 0 );

		qrms_assert_same( array(), $sayfa['rows'], 'boş liste' );
		qrms_assert_false( $sayfa['has_more'], 'devamı yok' );
	}
);

qrms_test(
	'negatif ya da sıfır sayfa boyutu sorguyu sınırsız bırakmaz',
	function () {
		$db = qrms_yorum_wpdb();

		qrm_pro_fetch_approved_reviews( 0, -5 );

		$sorgu = $db->son_sorgu();

		qrms_assert_true( false !== strpos( $sorgu, 'LIMIT 2' ), 'en az 1 + 1' );
		qrms_assert_true( false !== strpos( $sorgu, 'OFFSET 0' ), 'negatif offset sıfırlanır' );
	}
);

qrms_test(
	'toplam sayaç ayrı sorulur (LIMIT sayacı bozmasın diye)',
	function () {
		$db = qrms_yorum_wpdb();
		$db->vars[] = '4231';

		qrms_assert_same( 4231, qrm_pro_count_approved_reviews(), 'toplam' );
		qrms_assert_true(
			false !== strpos( $db->son_sorgu(), 'COUNT(*)' ),
			'sayım sorgusu'
		);
	}
);

qrms_test(
	'kart çıktısı müşteri adını ve yorumu kaçırarak basar',
	function () {
		$yorum                = qrms_sahte_yorum( 1 );
		$yorum->customer_name = '<script>alert(1)</script>';
		$yorum->comment       = 'Harika & lezzetli <b>çok</b>';

		$html = qrm_pro_render_review_card( $yorum );

		qrms_assert_true( false === strpos( $html, '<script>' ), 'ad kaçırıldı' );
		qrms_assert_true( false === strpos( $html, '<b>çok</b>' ), 'yorum kaçırıldı' );
		qrms_assert_true( false !== strpos( $html, 'qrm-review-item' ), 'kart sınıfı korundu' );
	}
);

qrms_test(
	'anonim yorumda müşteri adı hiç basılmaz',
	function () {
		$yorum                = qrms_sahte_yorum( 1 );
		$yorum->customer_name = 'Gizli Kalmalı';
		$yorum->is_anonymous  = 1;

		$html = qrm_pro_render_review_card( $yorum );

		qrms_assert_true( false === strpos( $html, 'Gizli Kalmalı' ), 'ad sızmaz' );
		qrms_assert_true( false !== strpos( $html, 'Anonim Misafir' ), 'anonim etiketi' );
	}
);

qrms_test(
	'puan yıldızları 0-5 aralığının dışına taşmaz',
	function () {
		// str_repeat negatif sayıyla ölümcül hata verir; bozuk bir satır
		// tüm listeyi düşürmemeli.
		$yorum         = qrms_sahte_yorum( 1 );
		$yorum->rating = 9.7;
		$html          = qrm_pro_render_review_card( $yorum );
		qrms_assert_same( 5, substr_count( $html, '★' ), 'tavan 5' );

		$yorum->rating = -3;
		$html          = qrm_pro_render_review_card( $yorum );
		qrms_assert_same( 5, substr_count( $html, '☆' ), 'taban 0 dolu yıldız' );
	}
);

/* ---------------------------------------------------------------------------
 * 8z-2. Yorum & Feedback — ödül kodu TALEP DOĞRULAMASI
 * ------------------------------------------------------------------------ */

require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/rewards/db.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/rewards/functions.php';

echo "\nÖdül kodu talep doğrulaması\n";

qrms_test(
	'anahtarsız talep reddedilir',
	function () {
		qrms_yorum_wpdb();

		$sonuc = qrm_reward_verify_claim( 42, '', array( 'google_review_threshold' => 4 ) );

		qrms_assert_true( is_wp_error( $sonuc ), 'reddedildi' );
		qrms_assert_same( 'qrm_reward_claim', $sonuc->get_error_code(), 'hata kodu' );
	}
);

qrms_test(
	'uydurma anahtar reddedilir',
	function () {
		qrms_yorum_wpdb();
		qrm_reward_issue_claim( 42 );

		$sonuc = qrm_reward_verify_claim( 42, 'uydurma-anahtar', array( 'google_review_threshold' => 4 ) );

		qrms_assert_true( is_wp_error( $sonuc ), 'reddedildi' );
	}
);

qrms_test(
	'başka bir yorumun anahtarı bu yorum için kullanılamaz',
	function () {
		qrms_yorum_wpdb();

		$anahtar = qrm_reward_issue_claim( 7 );

		// Saldırgan kendi yorumunun anahtarını başka review_id ile deniyor.
		$sonuc = qrm_reward_verify_claim( 8, $anahtar, array( 'google_review_threshold' => 4 ) );

		qrms_assert_true( is_wp_error( $sonuc ), 'çapraz kullanım engellenir' );
	}
);

qrms_test(
	'anahtar veritabanında ham saklanmaz',
	function () {
		qrms_yorum_wpdb();

		$anahtar  = qrm_reward_issue_claim( 42 );
		$saklanan = get_transient( qrm_reward_claim_key( 42 ) );

		qrms_assert_true( '' !== (string) $saklanan, 'bir şey saklandı' );
		qrms_assert_false( $anahtar === $saklanan, 'ham anahtar saklanmıyor' );
		qrms_assert_same( wp_hash( $anahtar ), $saklanan, 'hash saklanıyor' );
	}
);

qrms_test(
	'geçerli anahtar + eşiği geçen yorum kabul edilir',
	function () {
		$db      = qrms_yorum_wpdb();
		$anahtar = qrm_reward_issue_claim( 42 );

		// 1) yorum satırı, 2) "bu yoruma kod verilmiş mi" -> hayır
		$db->vars[] = (object) array( 'id' => 42, 'rating' => 4.8 );
		$db->vars[] = null;

		$sonuc = qrm_reward_verify_claim( 42, $anahtar, array( 'google_review_threshold' => 4.5 ) );

		qrms_assert_true( true === $sonuc, 'kabul edildi' );
	}
);

qrms_test(
	'eşiğin ALTINDA kalan yorum için kod üretilemez',
	function () {
		$db      = qrms_yorum_wpdb();
		$anahtar = qrm_reward_issue_claim( 42 );

		$db->vars[] = (object) array( 'id' => 42, 'rating' => 2.0 );
		$db->vars[] = null;

		$sonuc = qrm_reward_verify_claim( 42, $anahtar, array( 'google_review_threshold' => 4.5 ) );

		qrms_assert_true( is_wp_error( $sonuc ), 'reddedildi' );
		qrms_assert_contains( 'koşulunu karşılamıyor', $sonuc->get_error_message(), 'sebep' );
	}
);

qrms_test(
	'var olmayan yorum kimliği reddedilir',
	function () {
		$db      = qrms_yorum_wpdb();
		$anahtar = qrm_reward_issue_claim( 42 );

		$db->vars[] = null; // yorum bulunamadı

		$sonuc = qrm_reward_verify_claim( 42, $anahtar, array( 'google_review_threshold' => 4 ) );

		qrms_assert_true( is_wp_error( $sonuc ), 'reddedildi' );
		qrms_assert_contains( 'bulunamadı', $sonuc->get_error_message(), 'sebep' );
	}
);

qrms_test(
	'aynı yoruma ikinci kod verilmez',
	function () {
		$db      = qrms_yorum_wpdb();
		$anahtar = qrm_reward_issue_claim( 42 );

		$db->vars[] = (object) array( 'id' => 42, 'rating' => 5.0 );
		$db->vars[] = 17; // bu yoruma zaten kod üretilmiş

		$sonuc = qrm_reward_verify_claim( 42, $anahtar, array( 'google_review_threshold' => 4 ) );

		qrms_assert_true( is_wp_error( $sonuc ), 'reddedildi' );
		qrms_assert_contains( 'zaten', $sonuc->get_error_message(), 'sebep' );
	}
);

qrms_test(
	'anahtar TEK KULLANIMLIKTIR: harcandıktan sonra geçmez',
	function () {
		$db      = qrms_yorum_wpdb();
		$anahtar = qrm_reward_issue_claim( 42 );

		$db->vars[] = (object) array( 'id' => 42, 'rating' => 5.0 );
		$db->vars[] = null;
		qrms_assert_true( true === qrm_reward_verify_claim( 42, $anahtar, array( 'google_review_threshold' => 4 ) ), 'ilk kullanım' );

		// Kod üretildiğinde uç bunu çağırır.
		qrm_reward_consume_claim( 42 );

		$sonuc = qrm_reward_verify_claim( 42, $anahtar, array( 'google_review_threshold' => 4 ) );
		qrms_assert_true( is_wp_error( $sonuc ), 'ikinci kullanım reddedilir' );
	}
);

qrms_test(
	'geçersiz yorum kimliği için anahtar üretilmez',
	function () {
		qrms_assert_same( '', qrm_reward_issue_claim( 0 ), 'sıfır' );
		qrms_assert_same( '', qrm_reward_issue_claim( -3 ), 'negatif' );
	}
);

qrms_test(
	'ödül ucu doğrulamayı e-posta kontrolünden ÖNCE yapar',
	function () {
		// Sıra önemli: yetkisiz bir istek, hangi e-postaların kod aldığını
		// "already_used" cevabıyla sızdırmamalı.
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/ajax/rewards.php'
		);

		$dogrulama = strpos( $kaynak, 'qrm_reward_verify_claim' );
		$eposta    = strpos( $kaynak, 'qrm_reward_find_by_email' );

		qrms_assert_true( false !== $dogrulama, 'doğrulama çağrılıyor' );
		qrms_assert_true( false !== $eposta, 'e-posta kontrolü duruyor' );
		qrms_assert_true( $dogrulama < $eposta, 'doğrulama önce gelir' );
	}
);

/* ---------------------------------------------------------------------------
 * 8z-3. Yorum gönderimi — puan aralığı ve yazma sonucu
 *
 * qrm_pro_handle_review_submission hem AJAX hem klasik POST akışının ortak
 * yoludur; burada GERÇEK spam/cooldown yardımcıları (security.php) yüklenir ki
 * doğrulama sırası da testin kapsamında kalsın.
 * ------------------------------------------------------------------------ */

require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/security.php';
// Gönderim, masayı qrm_pro_resolve_masa_for_submission() (masa.php) ile,
// onay metnini qrm_pro_consent_from_request() (consent.php) ile çözer.
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/masa.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/consent.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/ajax/submit-review.php';

// Bu bölümün taklidi yalnızca insert() karşılar; sonraki bölümlere sızmaması
// için o ana kadarki $wpdb saklanır ve bölüm sonunda geri takılır.
$qrms_gonderim_onceki_wpdb = $GLOBALS['wpdb'];

/**
 * insert() çağrılarını kaydeden $wpdb taklidi.
 *
 * $sonuc = false yapılarak yazma hatası (tablo yok, bağlantı düştü, sütun
 * taşması) taklit edilir; gerçek $wpdb->insert de bu durumda false döner.
 */
class QRMS_Yorum_Insert_Wpdb {
	public $prefix    = 'wp_';
	public $inserts   = array();
	public $insert_id = 0;
	public $sonuc     = 1;

	public function insert( $table, $data, $format = null ) {
		$this->inserts[] = array(
			'table'  => $table,
			'data'   => $data,
			'format' => $format,
		);

		if ( false === $this->sonuc ) {
			return false;
		}

		$this->insert_id = 101;

		return 1;
	}

	public function son_insert() {
		return end( $this->inserts ) ?: array();
	}

	/**
	 * Yorum satırının insert'i (analitik kaydı sonra gelebilir).
	 *
	 * @return array
	 */
	public function yorum_insert() {
		foreach ( $this->inserts as $insert ) {
			if ( isset( $insert['data']['rating_1'] ) ) {
				return $insert;
			}
		}

		return $this->son_insert();
	}
}

/**
 * Yorum gönderimi testleri için taze bir $wpdb takar.
 *
 * @param mixed $sonuc insert() dönüş değeri (false = yazma hatası).
 * @return QRMS_Yorum_Insert_Wpdb
 */
function qrms_gonderim_wpdb( $sonuc = 1 ) {
	$db        = new QRMS_Yorum_Insert_Wpdb();
	$db->sonuc = $sonuc;

	$GLOBALS['wpdb'] = $db;

	return $db;
}

/**
 * Gönderim ayarları (beş kriter de açık, ödül/Google kapalı).
 *
 * @param array $ek Üzerine yazılacak ayarlar.
 * @return array
 */
function qrms_gonderim_ayarlari( $ek = array() ) {
	$ayarlar = array(
		'auto_approve_rating'       => 0,
		'google_review_enabled'     => 0,
		'google_review_url'         => '',
		'google_review_threshold'   => 3.5,
		'qrm_spam_cooldown_minutes' => 10,
	);

	for ( $i = 1; $i <= 5; $i++ ) {
		$ayarlar[ 'crit_' . $i . '_active' ] = 1;
	}

	return array_merge( $ayarlar, $ek );
}

/**
 * Spam korumasını geçen geçerli bir $_POST hazırlar.
 *
 * Zaman tuzağı en az 3 saniye beklemeyi şart koştuğu için imzalı damga geçmişe
 * tarihlenir; captcha cevabı da aynı imza şemasıyla üretilir.
 *
 * @param array $puanlar rating_N => değer.
 * @param array $ek      Ek POST alanları.
 * @return void
 */
function qrms_gonderim_postu( $puanlar, $ek = array() ) {
	$damga = time() - 10;

	$_POST = array(
		'qrm_ts'           => $damga . '.' . hash_hmac( 'sha256', $damga . '|qrm_ts', wp_salt( 'auth' ) ),
		'qrm_captcha'      => 7,
		'qrm_captcha_hash' => hash_hmac( 'sha256', '7', wp_salt( 'nonce' ) ),
	);

	foreach ( $puanlar as $kriter => $puan ) {
		$_POST[ 'rating_' . $kriter ] = $puan;
	}

	foreach ( $ek as $anahtar => $deger ) {
		$_POST[ $anahtar ] = $deger;
	}

	// Cooldown yalnızca yetkisiz ziyaretçilere uygulanır; testler gerçek
	// müşteri akışını izlesin diye yetki kapatılır.
	$GLOBALS['qrms_test']['can'] = false;
}

echo "\nYorum gönderimi — puan aralığı ve yazma sonucu\n";

qrms_test(
	'5 üstü puan sunucuda 0\'a düşer ve ortalamaya katılmaz',
	function () {
		// Form 1-5 gönderir; istek elle hazırlandığında aralık dışı değer
		// eskiden olduğu gibi kaydediliyor, ortalamayı 5'in üstüne çıkarıyordu.
		$db = qrms_gonderim_wpdb();
		qrms_gonderim_postu( array( 1 => 4, 2 => 99 ) );

		$sonuc = qrm_pro_handle_review_submission( qrms_gonderim_ayarlari() );
		$veri  = $db->yorum_insert();

		qrms_assert_true( $sonuc['success'], 'gönderim kabul edilir' );
		qrms_assert_same( 0, $veri['data']['rating_2'], 'aralık dışı puan sıfırlanır' );
		qrms_assert_same( 4, $veri['data']['rating_1'], 'geçerli puan korunur' );
		qrms_assert_same( 4.0, $sonuc['avg'], 'ortalama yalnızca geçerli puandan hesaplanır' );
	}
);

qrms_test(
	'negatif puan da 0\'a düşer',
	function () {
		$db = qrms_gonderim_wpdb();
		qrms_gonderim_postu( array( 1 => 5, 3 => -4 ) );

		$sonuc = qrm_pro_handle_review_submission( qrms_gonderim_ayarlari() );
		$veri  = $db->yorum_insert();

		qrms_assert_same( 0, $veri['data']['rating_3'], 'negatif puan sıfırlanır' );
		qrms_assert_same( 5.0, $sonuc['avg'], 'ortalama negatiften etkilenmez' );
	}
);

qrms_test(
	'aralık dışı puan TEK kriterse gönderim reddedilir',
	function () {
		// Sıfıra düşen puan "puanlanmamış" sayılır: ortalama 0 kalır ve
		// kayıt hiç açılmaz.
		$db = qrms_gonderim_wpdb();
		qrms_gonderim_postu( array( 1 => 12 ) );

		$sonuc = qrm_pro_handle_review_submission( qrms_gonderim_ayarlari() );

		qrms_assert_false( $sonuc['success'], 'reddedilir' );
		qrms_assert_same( 0, count( $db->inserts ), 'kayıt açılmaz' );
	}
);

qrms_test(
	'5 sınır değeri geçerli sayılır',
	function () {
		$db = qrms_gonderim_wpdb();
		qrms_gonderim_postu( array( 1 => 5 ) );

		$sonuc = qrm_pro_handle_review_submission( qrms_gonderim_ayarlari() );

		qrms_assert_true( $sonuc['success'], 'üst sınır kabul edilir' );
		qrms_assert_same( 5, $db->yorum_insert()['data']['rating_1'], '5 korunur' );
	}
);

qrms_test(
	'insert sütun formatlarıyla çağrılır, sıra veriyle örtüşür',
	function () {
		$db = qrms_gonderim_wpdb();
		qrms_gonderim_postu( array( 1 => 4 ), array( 'is_anonymous' => '1', 'table_no' => 'A12' ) );

		qrm_pro_handle_review_submission( qrms_gonderim_ayarlari() );

		$veri = $db->yorum_insert();

		qrms_assert_true( is_array( $veri['format'] ), 'format dizisi verilir' );
		qrms_assert_same(
			count( $veri['data'] ),
			count( $veri['format'] ),
			'her sütun için bir format'
		);
		qrms_assert_same(
			array( '%f', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s' ),
			$veri['format'],
			'formatlar sütun sırasına göre'
		);
		qrms_assert_same(
			array(
				'rating',
				'rating_1',
				'rating_2',
				'rating_3',
				'rating_4',
				'rating_5',
				'comment',
				'customer_name',
				'customer_phone',
				'table_no',
				'is_anonymous',
				'status',
				'form_source',
			),
			array_keys( $veri['data'] ),
			'sütun sırası formatla aynı'
		);
	}
);

qrms_test(
	'yazma başarısız olursa success:false döner',
	function () {
		// Eskiden insert()'in dönüşü okunmuyordu: kayıt açılmasa bile
		// kullanıcıya "alındı" deniyordu.
		qrms_gonderim_wpdb( false );
		qrms_gonderim_postu( array( 1 => 4 ) );

		$sonuc = qrm_pro_handle_review_submission( qrms_gonderim_ayarlari() );

		qrms_assert_false( $sonuc['success'], 'başarısızlık bildirilir' );
		qrms_assert_contains( 'kaydedilemedi', $sonuc['message'], 'kullanıcıya tekrar deneme mesajı' );
	}
);

qrms_test(
	'yazma başarısız olursa cooldown penceresi BAŞLAMAZ',
	function () {
		// Aksi hâlde kaydı oluşmayan müşteri, dakikalarca tekrar deneyemezdi.
		qrms_gonderim_wpdb( false );
		qrms_gonderim_postu( array( 1 => 4 ), array( 'customer_phone' => '0555 111 22 33' ) );

		qrm_pro_handle_review_submission( qrms_gonderim_ayarlari() );

		foreach ( qrm_pro_cooldown_keys( array( 'phone' => '05551112233' ) ) as $anahtar ) {
			qrms_assert_false( get_transient( $anahtar ), 'cooldown işaretlenmedi: ' . $anahtar );
		}

		// Kısıt gerçekten çalışıyor olsun diye kontrol: başarılı gönderim işaretler.
		qrms_gonderim_wpdb();
		qrms_gonderim_postu( array( 1 => 4 ), array( 'customer_phone' => '0555 111 22 33' ) );
		qrm_pro_handle_review_submission( qrms_gonderim_ayarlari() );

		qrms_assert_true(
			(bool) get_transient( qrm_pro_cooldown_keys( array( 'phone' => '05551112233' ) )[0] ),
			'başarılı gönderim cooldown başlatır'
		);
	}
);

qrms_test(
	'yazma başarısız olursa istatistik önbelleği boşuna geçersizlenmez',
	function () {
		set_transient( QRM_PRO_STATS_TRANSIENT, array( 'total' => 7 ), 60 );

		qrms_gonderim_wpdb( false );
		qrms_gonderim_postu( array( 1 => 4 ) );

		qrm_pro_handle_review_submission( qrms_gonderim_ayarlari() );

		qrms_assert_same(
			array( 'total' => 7 ),
			get_transient( QRM_PRO_STATS_TRANSIENT ),
			'önbellek yerinde kalır'
		);
	}
);

qrms_test(
	'başarılı yazmada review_id insert_id\'den gelir ve akış sürer',
	function () {
		$db = qrms_gonderim_wpdb();
		set_transient( QRM_PRO_STATS_TRANSIENT, array( 'total' => 7 ), 60 );
		qrms_gonderim_postu( array( 1 => 5 ) );

		$ayarlar = qrms_gonderim_ayarlari(
			array(
				'auto_approve_rating'   => 4,
				'google_review_enabled' => 1,
				'google_review_url'     => 'https://example.test/review',
				'qrm_reward_enabled'    => 1,
			)
		);

		$sonuc = qrm_pro_handle_review_submission( $ayarlar );

		qrms_assert_true( $sonuc['success'], 'başarı' );
		qrms_assert_same( 1, $sonuc['status'], 'eşiği geçen yorum yayınlanır' );
		qrms_assert_same( 101, $sonuc['review_id'], 'kimlik insert_id\'den' );
		qrms_assert_true( $sonuc['show_reward'], 'ödül popup\'ı açılır' );
		qrms_assert_false( get_transient( QRM_PRO_STATS_TRANSIENT ), 'önbellek geçersizlendi' );
	}
);

qrms_test(
	'AJAX ucu ile klasik POST akışı aynı fonksiyonu kullanır',
	function () {
		// Tek kod yolu: doğrulama düzeltmeleri iki akışta da geçerli olsun.
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/ajax/submit-review.php'
		);

		qrms_assert_same(
			1,
			substr_count( $kaynak, 'function qrm_pro_handle_review_submission' ),
			'tek işleyici tanımı'
		);
		qrms_assert_same(
			1,
			substr_count( $kaynak, '$wpdb->insert(' ),
			'tek yazma noktası'
		);
	}
);
$GLOBALS['wpdb'] = $qrms_gonderim_onceki_wpdb;
unset( $qrms_gonderim_onceki_wpdb );

qrms_test(
	'qrm_pro_build_steps dinamik adım sayısı ve stepper eşiği',
	function () {
		require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/settings.php';
		require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/form-steps.php';

		$settings = qrm_pro_default_settings();
		$field    = function ( $key, $type ) {
			return (object) array(
				'id'          => 1,
				'field_key'   => $key,
				'field_label' => $key,
				'field_type'  => $type,
				'is_required' => 0,
				'column_width'=> 'full',
			);
		};

		$full = qrm_pro_build_steps(
			$settings,
			array(
				$field( 'comment', 'textarea' ),
				$field( 'customer_name', 'text' ),
			),
			array( 'form_source' => 'review' )
		);
		qrms_assert_true( $full['use_stepper'], 'kriter + yorum + bilgi = stepper' );
		qrms_assert_same( 3, count( $full['steps'] ), '3 adım' );

		$no_textarea = qrm_pro_build_steps(
			$settings,
			array( $field( 'customer_name', 'text' ) ),
			array( 'form_source' => 'review' )
		);
		qrms_assert_same( 2, count( $no_textarea['steps'] ), 'textarea yoksa 2 adım' );

		$settings['crit_1_active'] = 0;
		$settings['crit_2_active'] = 0;
		$settings['crit_3_active'] = 0;
		$settings['crit_4_active'] = 0;
		$settings['crit_5_active'] = 0;
		$tek = qrm_pro_build_steps( $settings, array(), array( 'form_source' => 'review' ) );
		qrms_assert_false( $tek['use_stepper'], 'kriter ve alan yoksa stepper yok' );
	}
);

/* ---------------------------------------------------------------------------
 * 9a-0. QR Analiz — izleme nonce'u ve saklama politikası
 * ------------------------------------------------------------------------ */

// Sınıf dosya kapsamında yalnızca tanım yapar; init() elle çağrılır.
