<?php
/**
 * BULGU-AUDIT-04 düzeltmesi — AJAX yetkisizlik reddinde tutarsız HTTP durum
 * kodu (200 vs 403).
 *
 * bkz. security-audit/full-release-audit.md §6 BULGU-AUDIT-04: bazı
 * current_user_can()/is_user_logged_in() reddi veren AJAX uçları durum kodu
 * belirtmeden wp_send_json()/wp_send_json_error() çağırıyordu → varsayılan
 * HTTP 200 ile gövdede success:false (ya da eşdeğer bir "reddedildi"
 * gövdesi) dönüyordu. İşlevsel bir güvenlik açığı değildi (yetkisiz işlem
 * hiçbir zaman gerçekleşmiyordu) ama log/WAF/izleme araçları 200'ü
 * "başarılı" sanabiliyordu. Bu dosya, düzeltmenin hedeflediği 8 akışın
 * YALNIZCA yetkisizlik/capability reddi yollarında artık HTTP 403
 * döndüğünü; rate limit, girdi doğrulama, "bulunamadı" gibi capability
 * OLMAYAN diğer erken-dönüş yollarının ve tüm başarılı yanıtların durum
 * kodunun/gövde şeklinin DEĞİŞMEDİĞİNİ doğrular.
 *
 * GÖVDE ŞEKLİ KORUNUR: rewards.php ve admin-kombin-meta.php uçları
 * wp_send_json()'ı DOĞRUDAN (success/data sarmalaması olmadan) çağırıyor;
 * frontend (reward-cashier.php, admin-kombin-meta.php'nin select2 JS'i)
 * res.message / res.email / res.results gibi DÜZ alanlar okuyor. Bu yüzden
 * düzeltme bu uçlarda wp_send_json_error()'a GEÇMEK yerine (bu, alanları
 * res.data.* altına taşıyıp frontend'i kırardı) mevcut wp_send_json()
 * çağrılarına ikinci parametre olarak durum kodunu ekler — WordPress
 * çekirdeğinde wp_send_json() da (4.7+) tıpkı wp_send_json_error() gibi
 * $status_code kabul eder ve verildiğinde status_header() çağırır; stub
 * (tests/stubs-wordpress.php) bu turda aynı şekilde güncellendi.
 * trait-ajax.php'deki current_user_can() reddi zaten wp_send_json_error()
 * kullandığından oraya doğrudan ikinci parametre (403) eklenmesi yeterli.
 *
 * KAYNAK-KOD TESTİ NEDEN GEREKLİ (yalnızca 2 alt senaryoda): Bu test
 * dosyasındaki senaryoların büyük çoğunluğu gerçek fonksiyonları çağırıp
 * $GLOBALS['qrms_test']['json'] ve $GLOBALS['qrms_test']['status_header']
 * üzerinden gerçek stub davranışını doğrular (status_header() zaten
 * test-masa.php/test-hata-sayfalari.php'nin kullandığı aynı yerleşik
 * yakalama noktasıdır). İki istisna, bu projede WP_Query kullanan HER
 * akış için izlenen mevcut yöntemle (bkz. test-csv-import-dedup.php başlık
 * yorumu) kaynak metnine karşı doğrulanır, çünkü bu test paketinde
 * WP_Query'nin bir stub'ı YOKTUR — doğrudan çağrılırsa "class WP_Query
 * not found" ile fatal verir:
 *   - QMO_Kombin_Meta::ajax_search_items() başarı dalı (new WP_Query(...)
 *     içerir); yetkisizlik dalı WP_Query'ye ulaşmadan erken döndüğü için
 *     O davranışsal olarak test edilir, yalnızca başarı dalının durum
 *     kodu/gövdesinin değişmediği kaynak metninden doğrulanır.
 *   - ajax_color_preview_item() başarı dalı zaten test-restoran-menu.php
 *     içinde ("renk önizlemesi yayınlanmış menü ürününden beslenir…")
 *     kaynak tabanlı olarak doğrulanmıştır ve bu görevde değişmemiştir;
 *     burada yalnızca YENİ 403 dalı gerçek çalıştırmayla test edilir.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/rewards/db.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/rewards/capabilities.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/rewards/functions.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/ajax/rewards.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/settings.php';

require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-helpers.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-ajax.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-tukendi.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/admin-kombin-meta.php';

echo "\nBULGU-AUDIT-04 — AJAX yetkisizlik reddinde tutarsız HTTP durum kodu (200 vs 403)\n";

/* =========================================================================
   Yardımcı: rewards.php uçları için sabit "aktif" kodlu taze $wpdb.
   test-reward-capability.php'deki QRMS_Reward_Cap_Wpdb ile aynı desendir;
   sınıf adı çakışmasın diye burada ayrı tanımlanır.
========================================================================= */
if ( ! class_exists( 'QRMS_Ajax403_Reward_Wpdb' ) ) {
	class QRMS_Ajax403_Reward_Wpdb {
		public $prefix  = 'wp_';
		public $row     = null;
		public $updates = array();

		public function prepare( $sql, ...$args ) {
			return $sql;
		}

		public function get_row( $sql ) {
			return $this->row;
		}

		public function update( $table, $data, $where, $format = null, $where_format = null ) {
			$this->updates[] = array( 'table' => $table, 'data' => $data, 'where' => $where );

			if ( isset( $this->row ) && isset( $where['id'] ) && (int) $this->row->id === (int) $where['id'] ) {
				foreach ( $data as $k => $v ) {
					$this->row->$k = $v;
				}
			}

			return 1;
		}

		public function insert( $table, $data, $format = null ) {
			// Kullanıldı işaretlemede tetiklenen analitik yazımı fatal
			// vermeden geçilsin diye — bu testler yetkiyi doğrular, analitiği değil.
			return 1;
		}
	}
}

/**
 * @return QRMS_Ajax403_Reward_Wpdb
 */
function qrms_ajax403_reward_wpdb() {
	$GLOBALS['wpdb']      = new QRMS_Ajax403_Reward_Wpdb();
	$GLOBALS['wpdb']->row = (object) array(
		'id'             => 1,
		'code'           => 'QRM-TEST01',
		'email'          => 'test@example.test',
		'status'         => 'active',
		'discount_label' => 'Standart (%10 indirim)',
		'created_at'     => '2026-01-01 10:00:00',
		'expires_at'     => '',
		'used_at'        => '',
	);

	return $GLOBALS['wpdb'];
}

/* =========================================================================
   1) qrm_reward_ajax_admin_lookup
========================================================================= */

qrms_test(
	'qrm_reward_ajax_admin_lookup: giriş yapılmamışsa artık 403 döner (önceden 200/success:false idi)',
	function () {
		qrms_ajax403_reward_wpdb();
		$GLOBALS['qrms_test']['logged_in'] = false;
		$GLOBALS['qrms_test']['can_map']['qrm_manage_rewards'] = true;
		$_POST['code'] = 'QRM-TEST01';

		qrm_reward_ajax_admin_lookup();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_false( $json['success'], 'oturumsuz istek reddedilir' );
		qrms_assert_same( 403, $GLOBALS['qrms_test']['status_header'], 'HTTP 403 döner' );
		qrms_assert_same( 'Bu işlem için giriş yapmalısınız.', $json['message'], 'frontend alanı (res.message) korunur' );
	}
);

qrms_test(
	'qrm_reward_ajax_admin_lookup: qrm_manage_rewards/manage_options yoksa artık 403 döner',
	function () {
		qrms_ajax403_reward_wpdb();
		$GLOBALS['qrms_test']['logged_in'] = true;
		$GLOBALS['qrms_test']['can_map']['qrm_manage_rewards'] = false;
		$GLOBALS['qrms_test']['can_map']['manage_options']     = false;
		$_POST['code'] = 'QRM-TEST01';

		qrm_reward_ajax_admin_lookup();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_false( $json['success'], 'yetkisiz istek reddedilir' );
		qrms_assert_same( 403, $GLOBALS['qrms_test']['status_header'], 'HTTP 403 döner' );
		qrms_assert_same( 'Bu işlem için yetkiniz yok.', $json['message'], 'frontend alanı (res.message) korunur' );
		qrms_assert_true( ! isset( $json['email'] ), 'müşteri e-postası hâlâ sızmıyor' );
	}
);

qrms_test(
	'qrm_reward_ajax_admin_lookup: yetkili istek 200 kalır, gövde/davranış değişmedi',
	function () {
		qrms_ajax403_reward_wpdb();
		$GLOBALS['qrms_test']['logged_in'] = true;
		$GLOBALS['qrms_test']['can_map']['qrm_manage_rewards'] = true;
		$_POST['code'] = 'QRM-TEST01';

		qrm_reward_ajax_admin_lookup();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_true( $json['success'], 'yetkili istek başarılı' );
		qrms_assert_true( empty( $GLOBALS['qrms_test']['status_header'] ), 'başarılı yanıtta durum kodu değişmedi (varsayılan 200)' );
		qrms_assert_same( 'test@example.test', $json['email'], 'frontend alanı (res.email) korunur' );
		qrms_assert_true( $json['can_mark_used'], 'frontend alanı (res.can_mark_used) korunur' );
	}
);

qrms_test(
	'qrm_reward_ajax_admin_lookup: "kod bulunamadı"/rate-limit/boş-kod dalları capability reddi DEĞİLDİR — kaynak kodda dokunulmadı (kaynak-kod testi neden gerekli: bu dallarda erken wp_send_json() sonrası return yoktur — gerçek WP\'de wp_die() bunu durdurur ama bu stub ortamı durdurmaz; çalıştırılırsa fonksiyonun SONUNDAKİ başarı yanıtı bu dalı ezer. Bu, görevin kapsamındaki bir davranış DEĞİL, önceden var olan ve bu PR\'da dokunulmayan bir durumdur — bu yüzden gerçek çalıştırma yerine kaynak metni doğrulanır)',
	function () {
		$src = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/ajax/rewards.php' );

		qrms_assert_contains( "wp_send_json(['success' => false, 'message' => \$limit]);", $src, 'rate-limit dalı değişmedi' );
		qrms_assert_contains( "wp_send_json(['success' => false, 'message' => 'Lütfen bir kod girin.']);", $src, 'boş kod dalı değişmedi' );
		qrms_assert_contains( "wp_send_json(['success' => false, 'found' => false, 'message' => 'Böyle bir kod bulunamadı.']);", $src, '"bulunamadı" dalı değişmedi (403 eklenmedi)' );
	}
);

/* =========================================================================
   2) qrm_reward_ajax_cashier_mark_used
========================================================================= */

qrms_test(
	'qrm_reward_ajax_cashier_mark_used: giriş yapılmamışsa artık 403 döner',
	function () {
		$wpdb = qrms_ajax403_reward_wpdb();
		$GLOBALS['qrms_test']['logged_in'] = false;
		$GLOBALS['qrms_test']['can_map']['qrm_manage_rewards'] = true;
		$_POST['code'] = 'QRM-TEST01';

		qrm_reward_ajax_cashier_mark_used();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_false( $json['success'], 'oturumsuz istek reddedilir' );
		qrms_assert_same( 403, $GLOBALS['qrms_test']['status_header'], 'HTTP 403 döner' );
		qrms_assert_same( 'active', $wpdb->row->status, 'yan etki yok' );
	}
);

qrms_test(
	'qrm_reward_ajax_cashier_mark_used: qrm_manage_rewards/manage_options yoksa artık 403 döner',
	function () {
		$wpdb = qrms_ajax403_reward_wpdb();
		$GLOBALS['qrms_test']['logged_in'] = true;
		$GLOBALS['qrms_test']['can_map']['qrm_manage_rewards'] = false;
		$GLOBALS['qrms_test']['can_map']['manage_options']     = false;
		$_POST['code'] = 'QRM-TEST01';

		qrm_reward_ajax_cashier_mark_used();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_false( $json['success'], 'yetkisiz istek reddedilir' );
		qrms_assert_same( 403, $GLOBALS['qrms_test']['status_header'], 'HTTP 403 döner' );
		qrms_assert_same( 'active', $wpdb->row->status, 'kod durumu DEĞİŞMEDİ — hiçbir yan etki yok' );
		qrms_assert_true( 0 === count( $wpdb->updates ), 'wpdb->update() hiç çağrılmadı' );
	}
);

qrms_test(
	'qrm_reward_ajax_cashier_mark_used: yetkili istek 200 kalır, kodu gerçekten kullanıldı işaretler',
	function () {
		$wpdb = qrms_ajax403_reward_wpdb();
		$GLOBALS['qrms_test']['logged_in'] = true;
		$GLOBALS['qrms_test']['can_map']['qrm_manage_rewards'] = true;
		$_POST['code'] = 'QRM-TEST01';

		qrm_reward_ajax_cashier_mark_used();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_true( $json['success'], 'yetkili istek başarılı' );
		qrms_assert_true( empty( $GLOBALS['qrms_test']['status_header'] ), 'başarılı yanıtta durum kodu değişmedi' );
		qrms_assert_same( 'used', $wpdb->row->status, 'kod durumu gerçekten used oldu' );
	}
);

qrms_test(
	'qrm_reward_ajax_cashier_mark_used: "kod bulunamadı"/"kullanılamaz"/"güncellenemedi" dalları capability reddi DEĞİLDİR — kaynak kodda dokunulmadı (bkz. admin_lookup testindeki aynı kaynak-kod-testi gerekçesi)',
	function () {
		$src = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/ajax/rewards.php' );

		qrms_assert_contains( "'message'      => 'Bu kod kullanılamaz (' . qrm_reward_status_label(\$row->status) . ').',", $src, '"kullanılamaz" dalı değişmedi (403 eklenmedi)' );
		qrms_assert_contains( "wp_send_json(['success' => false, 'message' => 'Kod güncellenemedi, tekrar deneyin.']);", $src, '"güncellenemedi" dalı değişmedi' );
	}
);

/* =========================================================================
   3) qrm_reward_ajax_admin_selftest
========================================================================= */

qrms_test(
	'qrm_reward_ajax_admin_selftest: manage_options yoksa artık 403 döner, gövde şekli (show_reward/message) korunur',
	function () {
		$GLOBALS['qrms_test']['can_map']['manage_options'] = false;

		qrm_reward_ajax_admin_selftest();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_same( 403, $GLOBALS['qrms_test']['status_header'], 'HTTP 403 döner' );
		qrms_assert_false( $json['show_reward'], 'frontend alanı (res.show_reward) korunur' );
		qrms_assert_same( 'Bu işlem için yetkiniz yok.', $json['message'], 'frontend alanı (res.message) korunur' );
		qrms_assert_true( ! isset( $json['success'] ), 'bu uç zaten success alanı kullanmıyor — gövde şekli değişmedi' );
	}
);

qrms_test(
	'qrm_reward_ajax_admin_selftest: manage_options varsa 200 kalır, davranış/gövde şekli değişmedi',
	function () {
		$GLOBALS['qrms_test']['can_map']['manage_options'] = true;
		update_option(
			'qrm_settings',
			array(
				'google_review_enabled'   => 1,
				'google_review_url'       => 'https://search.google.com/local/writereview?placeid=ABC',
				'google_review_threshold' => 4,
				'qrm_reward_enabled'      => 0,
			)
		);

		qrm_reward_ajax_admin_selftest();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_true( empty( $GLOBALS['qrms_test']['status_header'] ), 'başarılı yanıtta durum kodu değişmedi (varsayılan 200)' );
		qrms_assert_true( isset( $json['show_reward'] ), 'frontend alanı (res.show_reward) korunur' );
		qrms_assert_true( isset( $json['message'] ), 'frontend alanı (res.message) korunur' );
		qrms_assert_false( $json['reward_on'], 'iş mantığı değişmedi (qrm_reward_enabled=0 → reward_on=false)' );
	}
);

/* =========================================================================
   4) RMA_Ajax_Trait — ajax_toggle_status / ajax_toggle_tukendi /
      ajax_save_category_order / ajax_color_preview_item
      (trait-ajax.php, modules/restoran-menu)

   Yalnızca ilgili trait'i (ve bump_cache_version() için RMA_Helpers_Trait'i)
   kullanan hafif bir harness sınıfı — test-csv-import-dedup.php ve
   test-restoran-menu.php'deki RMA_Test_Ingredient_CSV_Harness ile aynı
   desen (bkz. o dosyadaki RMA_CACHE_VERSION_OPTION notu: sabit trait'te
   tanımlanamadığı için kullanan sınıfta tanımlanır).
========================================================================= */

if ( ! class_exists( 'RMA_Test_Ajax403_Harness' ) ) {
	class RMA_Test_Ajax403_Harness {
		use RMA_Helpers_Trait;
		use RMA_Ajax_Trait;

		const RMA_CACHE_VERSION_OPTION = 'rma_cache_version';
	}
}

/* ---- ajax_toggle_status ---- */

qrms_test(
	'ajax_toggle_status: edit_post yetkisi yoksa artık 403 döner (post tipi/ID geçerliyken)',
	function () {
		$h = new RMA_Test_Ajax403_Harness();
		$GLOBALS['qrms_test']['post_types'][55]       = 'rma_menu_item';
		$GLOBALS['qrms_test']['can_map']['edit_post']  = false;
		$_POST['id']     = 55;
		$_POST['status'] = '1';

		$h->ajax_toggle_status();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_false( $json['success'], 'yetkisiz istek reddedilir' );
		qrms_assert_same( 403, $json['status'], 'wp_send_json_error() 403 durum kodunu taşır' );
		qrms_assert_true( ! isset( $GLOBALS['qrms_test']['post_meta'][55]['rma_active'] ), 'yan etki yok — meta güncellenmedi' );
	}
);

qrms_test(
	'ajax_toggle_status/ajax_toggle_tukendi: geçersiz post ID/tip dalı capability reddi DEĞİLDİR — kaynak kodda dokunulmadı (kaynak-kod testi: bu dalın SONRASINDA erken return yoktur — gerçek WP\'de wp_die() bunu durdurur; PR kapsamındaki tek değişiklik CAPABILITY dalına 403 eklemek ve capability reddine "return" eklemektir, bu görevin dışındaki girdi-doğrulama dalına dokunulmadı)',
	function () {
		$src = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-ajax.php' );

		qrms_assert_same(
			2,
			substr_count( $src, "if ( \$post_id < 1 || get_post_type( \$post_id ) !== 'rma_menu_item' ) {\n            wp_send_json_error();\n        }" ),
			'ajax_toggle_status ve ajax_toggle_tukendi\'nin geçersiz ID dalları birebir korunuyor (403 eklenmedi)'
		);
	}
);

qrms_test(
	'ajax_toggle_status: yetkili istek 200 kalır, meta gerçekten güncellenir',
	function () {
		$h = new RMA_Test_Ajax403_Harness();
		$GLOBALS['qrms_test']['post_types'][55]       = 'rma_menu_item';
		$GLOBALS['qrms_test']['can_map']['edit_post']  = true;
		$_POST['id']     = 55;
		$_POST['status'] = '1';

		$h->ajax_toggle_status();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_true( $json['success'], 'yetkili istek başarılı' );
		qrms_assert_true( empty( $GLOBALS['qrms_test']['status_header'] ), 'başarılı yanıtta durum kodu değişmedi' );
		qrms_assert_same( '1', $GLOBALS['qrms_test']['post_meta'][55]['rma_active'], 'meta gerçekten güncellendi' );
	}
);

/* ---- ajax_toggle_tukendi ---- */

qrms_test(
	'ajax_toggle_tukendi: edit_post yetkisi yoksa artık 403 döner',
	function () {
		$h = new RMA_Test_Ajax403_Harness();
		$GLOBALS['qrms_test']['post_types'][55]       = 'rma_menu_item';
		$GLOBALS['qrms_test']['can_map']['edit_post']  = false;
		$_POST['id']     = 55;
		$_POST['status'] = '1';

		$h->ajax_toggle_tukendi();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_false( $json['success'], 'yetkisiz istek reddedilir' );
		qrms_assert_same( 403, $json['status'], 'wp_send_json_error() 403 durum kodunu taşır' );
		qrms_assert_true( ! isset( $GLOBALS['qrms_test']['post_meta'][55]['_rma_tukendi'] ), 'yan etki yok' );
	}
);

qrms_test(
	'ajax_toggle_tukendi: yetkili istek 200 kalır, meta gerçekten güncellenir',
	function () {
		$h = new RMA_Test_Ajax403_Harness();
		$GLOBALS['qrms_test']['post_types'][55]       = 'rma_menu_item';
		$GLOBALS['qrms_test']['can_map']['edit_post']  = true;
		$_POST['id']     = 55;
		$_POST['status'] = '1';

		$h->ajax_toggle_tukendi();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_true( $json['success'], 'yetkili istek başarılı' );
		qrms_assert_true( empty( $GLOBALS['qrms_test']['status_header'] ), 'başarılı yanıtta durum kodu değişmedi' );
		qrms_assert_same( '1', $GLOBALS['qrms_test']['post_meta'][55]['_rma_tukendi'], 'meta gerçekten güncellendi' );
	}
);

/* ---- ajax_save_category_order ---- */

qrms_test(
	'ajax_save_category_order: manage_categories yetkisi yoksa artık 403 döner',
	function () {
		$h = new RMA_Test_Ajax403_Harness();
		$GLOBALS['qrms_test']['can_map']['manage_categories'] = false;
		$_POST['order'] = array( 9, 3, 1 );

		$h->ajax_save_category_order();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_false( $json['success'], 'yetkisiz istek reddedilir' );
		qrms_assert_same( 403, $json['status'], 'wp_send_json_error() 403 durum kodunu taşır' );
	}
);

qrms_test(
	'ajax_save_category_order: "order" hiç gönderilmemişse — capability reddi DEĞİLDİR, durum kodu değişmedi',
	function () {
		$h = new RMA_Test_Ajax403_Harness();
		$GLOBALS['qrms_test']['can_map']['manage_categories'] = true;
		unset( $_POST['order'] );

		$h->ajax_save_category_order();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_false( $json['success'], 'eksik parametre reddedilir' );
		qrms_assert_true( empty( $json['status'] ), 'girdi doğrulama reddi bir yetkisizlik reddi değildir' );
	}
);

qrms_test(
	'ajax_save_category_order: başarı dalı (wp_send_json_success()) bu PR ile değişmedi — kaynak-kod doğrulaması (fonksiyonun SONUNDAKİ wp_send_json_error() dalının öncesinde return yoktur; gerçek WP\'de wp_die() durdurur ama bu stub ortamında başarı yanıtı bile bu son dal tarafından ezilir — bu PR\'ın kapsamı dışındaki, önceden var olan bir durumdur, bu yüzden gerçek çalıştırma yerine kaynak metni doğrulanır)',
	function () {
		$src = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-ajax.php' );

		qrms_assert_contains(
			"update_term_meta( \$tid, 'rma_cat_order', \$i );\n            }\n            // Kategori sırası menü çıktısını belirler — önbelleği tazele.\n            \$this->bump_cache_version();\n            wp_send_json_success();\n        }\n        wp_send_json_error();",
			$src,
			'başarı dalının çağrı sırası/gövdesi (bump_cache_version + wp_send_json_success, durum kodu eklenmeden) korunuyor'
		);
	}
);

/* ---- ajax_color_preview_item ---- */

qrms_test(
	'ajax_color_preview_item: edit_posts yetkisi yoksa artık 403 döner (önceden 200 idi)',
	function () {
		$h = new RMA_Test_Ajax403_Harness();
		$GLOBALS['qrms_test']['can_map']['edit_posts'] = false;

		$h->ajax_color_preview_item();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_false( $json['success'], 'yetkisiz istek reddedilir' );
		qrms_assert_same( 403, $json['status'], 'wp_send_json_error() 403 durum kodunu taşır' );
	}
);
// Başarı dalı ($this->get_color_preview_item() → wp_send_json_success()) bu
// PR'da değişmedi; mevcut kapsamı zaten test-restoran-menu.php'de ("renk
// önizlemesi yayınlanmış menü ürününden beslenir, renk senkronuna
// dokunmaz") kaynak tabanlı olarak doğrulanmıştır — burada tekrar edilmez.

/* =========================================================================
   5) QMO_Kombin_Meta::ajax_search_items (admin-kombin-meta.php)
========================================================================= */

qrms_test(
	'QMO_Kombin_Meta::ajax_search_items: edit_posts yetkisi yoksa artık 403 döner (önceden 200 idi), gövde şekli (results:[]) korunur',
	function () {
		$GLOBALS['qrms_test']['can_map']['edit_posts'] = false;

		QMO_Kombin_Meta::ajax_search_items();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_same( array(), $json['results'], 'frontend alanı (res.results) korunur — select2 processResults() boş dizi bekler' );
		qrms_assert_same( 403, $GLOBALS['qrms_test']['status_header'], 'HTTP 403 döner' );
	}
);

qrms_test(
	'QMO_Kombin_Meta::ajax_search_items: başarı dalı bu PR ile değişmedi (kaynak-kod doğrulaması — bu test paketinde WP_Query stub\'ı yok)',
	function () {
		$src = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/admin-kombin-meta.php' );

		qrms_assert_contains( "wp_send_json( [ 'results' => [] ], 403 );", $src, 'yetkisizlik dalına 403 eklendi' );
		qrms_assert_contains(
			"wp_send_json( [\n            'results'    => \$results,\n            'pagination' => [ 'more' => \$page < (int) \$query->max_num_pages ],\n        ] );",
			$src,
			'başarılı yanıtın çağrısı (gövde + durum kodu) birebir korunuyor'
		);
	}
);
