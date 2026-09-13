<?php
/**
 * BULGU-001 düzeltmesi — ödül yönetimi özel capability testleri.
 *
 * Stub ortamı gerçek WP_Roles'u taklit etmediği için current_user_can()'in
 * capability bazlı davranışı qrms_test['can_map'] ile taklit edilir (bkz.
 * stubs-wordpress.php). Rol/migration davranışının uçtan uca doğrulaması
 * (Administrator, kasiyer rolü, Contributor, Author, Subscriber gerçek HTTP
 * çağrılarıyla) izole Docker/WordPress ortamında ayrıca yapılmıştır.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/rewards/db.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/rewards/capabilities.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/rewards/functions.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/ajax/rewards.php';

/**
 * Ödül testleri için minimal sahte $wpdb.
 */
class QRMS_Reward_Cap_Wpdb {
	public $prefix = 'wp_';
	public $row    = null;
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
		// Kod kullanıldı işaretlendiğinde tetiklenen analitik yazımı
		// (qmo_analitik_yaz) burayı çağırır; bu testler analitiği değil
		// yetki kontrolünü doğruladığı için sadece fatal vermeden geçilir.
		return 1;
	}
}

/**
 * Sabit bir "aktif" ödül kodu satırıyla taze bir $wpdb takar.
 *
 * @return QRMS_Reward_Cap_Wpdb
 */
function qrms_reward_cap_wpdb() {
	$GLOBALS['wpdb'] = new QRMS_Reward_Cap_Wpdb();
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

qrms_test(
	'qrm_manage_rewards yoksa ve manage_options de yoksa: e-posta ASLA görünmez (edit_posts artık yeterli değil)',
	function () {
		qrms_reward_cap_wpdb();
		$GLOBALS['qrms_test']['logged_in'] = true;
		// Eski davranış: genel 'can' bayrağı (edit_posts benzeri geniş bir
		// yetkiyi temsil eder) true, ama yeni özel capability YOK.
		$GLOBALS['qrms_test']['can']              = true;
		$GLOBALS['qrms_test']['can_map']['qrm_manage_rewards'] = false;
		$GLOBALS['qrms_test']['can_map']['manage_options']     = false;
		$_POST['code'] = 'QRM-TEST01';

		qrm_reward_ajax_admin_lookup();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_false( $json['success'], 'yetkisiz istek reddedilir' );
		qrms_assert_true( ! isset( $json['email'] ), 'yanıt hiçbir müşteri e-postası içermez' );
		qrms_assert_same( 'Bu işlem için yetkiniz yok.', $json['message'], 'tutarlı, veri sızdırmayan hata mesajı' );
	}
);

qrms_test(
	'qrm_manage_rewards yoksa: kodu "kullanıldı" işaretleyemez',
	function () {
		$wpdb = qrms_reward_cap_wpdb();
		$GLOBALS['qrms_test']['logged_in'] = true;
		$GLOBALS['qrms_test']['can']              = true;
		$GLOBALS['qrms_test']['can_map']['qrm_manage_rewards'] = false;
		$GLOBALS['qrms_test']['can_map']['manage_options']     = false;
		$_POST['code'] = 'QRM-TEST01';

		qrm_reward_ajax_cashier_mark_used();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_false( $json['success'], 'yetkisiz istek reddedilir' );
		qrms_assert_same( 'active', $wpdb->row->status, 'kod durumu DEĞİŞMEDİ — hiçbir yan etki yok' );
		qrms_assert_true( 0 === count( $wpdb->updates ), 'wpdb->update() hiç çağrılmadı' );
	}
);

qrms_test(
	'qrm_manage_rewards varsa: müşteri e-postasını görebilir (yetkili kasiyer akışı çalışmaya devam eder)',
	function () {
		qrms_reward_cap_wpdb();
		$GLOBALS['qrms_test']['logged_in'] = true;
		$GLOBALS['qrms_test']['can']              = false;
		$GLOBALS['qrms_test']['can_map']['qrm_manage_rewards'] = true;
		$GLOBALS['qrms_test']['can_map']['manage_options']     = false;
		$_POST['code'] = 'QRM-TEST01';

		qrm_reward_ajax_admin_lookup();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_true( $json['success'], 'yetkili istek başarılı' );
		qrms_assert_same( 'test@example.test', $json['email'], 'e-posta doğru döner' );
		qrms_assert_true( $json['can_mark_used'], 'aktif kod kullanılabilir işaretlenir' );
	}
);

qrms_test(
	'manage_options (geriye dönük uyumluluk): qrm_manage_rewards açıkça verilmemiş olsa bile erişebilir',
	function () {
		qrms_reward_cap_wpdb();
		$GLOBALS['qrms_test']['logged_in'] = true;
		$GLOBALS['qrms_test']['can']              = false;
		$GLOBALS['qrms_test']['can_map']['qrm_manage_rewards'] = false;
		$GLOBALS['qrms_test']['can_map']['manage_options']     = true;
		$_POST['code'] = 'QRM-TEST01';

		qrm_reward_ajax_admin_lookup();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_true( $json['success'], 'manage_options tek başına yeterli — mevcut yöneticiler erişimi kaybetmez' );
	}
);

qrms_test(
	'qrm_manage_rewards varsa: kodu gerçekten "kullanıldı" işaretleyebilir (yetkili kasiyer akışı)',
	function () {
		$wpdb = qrms_reward_cap_wpdb();
		$GLOBALS['qrms_test']['logged_in'] = true;
		$GLOBALS['qrms_test']['can']              = false;
		$GLOBALS['qrms_test']['can_map']['qrm_manage_rewards'] = true;
		$GLOBALS['qrms_test']['can_map']['manage_options']     = false;
		$_POST['code'] = 'QRM-TEST01';

		qrm_reward_ajax_cashier_mark_used();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_true( $json['success'], 'yetkili istek başarılı' );
		qrms_assert_same( 'used', $wpdb->row->status, 'kod durumu gerçekten used oldu' );
	}
);

qrms_test(
	'giriş yapmamış kullanıcı: capability true olsa bile reddedilir',
	function () {
		qrms_reward_cap_wpdb();
		$GLOBALS['qrms_test']['logged_in'] = false;
		$GLOBALS['qrms_test']['can']              = true;
		$GLOBALS['qrms_test']['can_map']['qrm_manage_rewards'] = true;
		$_POST['code'] = 'QRM-TEST01';

		qrm_reward_ajax_admin_lookup();

		$json = $GLOBALS['qrms_test']['json'];
		qrms_assert_false( $json['success'], 'oturumsuz istek reddedilir' );
		qrms_assert_true( ! isset( $json['email'] ), 'veri sızmaz' );
	}
);

/*
 * "Kasiyer bulunamadı" admin bildirimi (qrm_reward_cap_kasiyer_notice).
 *
 * qrm_reward_cap_upgrade()/qrm_reward_cap_kasiyer_rolleri() bu stub ortamında
 * çalıştırılamaz (wp_roles()/get_role() burada taklit edilmemiştir; rol
 * atama/migration davranışı izole Docker/WordPress ortamında ayrıca dinamik
 * olarak doğrulanmıştır). Aşağıdaki testler yalnızca bildirimin GÖSTERİM
 * mantığını (yetki, ekran, tekrarlanmama) doğrular; `qrm_reward_cap_kasiyer_bulunamadi`
 * option'ı doğrudan set/delete edilerek "bulunamadı" durumu taklit edilir.
 */

qrms_test(
	'kasiyer bildirimi: option yokken hiçbir şey basılmaz',
	function () {
		delete_option( 'qrm_reward_cap_kasiyer_bulunamadi' );
		$GLOBALS['qrms_test']['is_admin']   = true;
		$GLOBALS['qrms_test']['logged_in']  = true;
		$GLOBALS['qrms_test']['can_map']['manage_options'] = true;
		$_GET['page'] = 'qrms-yf-odul';

		ob_start();
		qrm_reward_cap_kasiyer_notice();
		$out = ob_get_clean();

		qrms_assert_same( '', $out, 'option aktif değilken bildirim üretilmez' );
	}
);

qrms_test(
	'kasiyer bildirimi: option aktifken manage_options kullanıcısına (Administrator) gösterilir',
	function () {
		update_option( 'qrm_reward_cap_kasiyer_bulunamadi', 1, false );
		$GLOBALS['qrms_test']['is_admin']   = true;
		$GLOBALS['qrms_test']['logged_in']  = true;
		$GLOBALS['qrms_test']['can_map']['manage_options'] = true;
		$_GET['page'] = 'qrms-yf-odul';

		ob_start();
		qrm_reward_cap_kasiyer_notice();
		$out = ob_get_clean();

		qrms_assert_contains( 'notice-warning', $out, 'uyarı stiliyle basılır' );
		qrms_assert_contains( 'qrm_manage_rewards', $out, 'hangi capability atanacağı açıkça belirtilir' );
		qrms_assert_contains( 'Kasiyer', $out, 'Türkçe, anlaşılır metin' );
	}
);

qrms_test(
	'kasiyer bildirimi: manage_options yetkisi olmayan kullanıcıya (ör. Contributor/Author) gösterilmez',
	function () {
		update_option( 'qrm_reward_cap_kasiyer_bulunamadi', 1, false );
		$GLOBALS['qrms_test']['is_admin']   = true;
		$GLOBALS['qrms_test']['logged_in']  = true;
		$GLOBALS['qrms_test']['can_map']['manage_options']     = false;
		$GLOBALS['qrms_test']['can_map']['qrm_manage_rewards'] = true;
		$_GET['page'] = 'qrms-yf-odul';

		ob_start();
		qrm_reward_cap_kasiyer_notice();
		$out = ob_get_clean();

		qrms_assert_same( '', $out, 'yetkisiz kullanıcıya hiçbir bilgi sızmaz' );
	}
);

qrms_test(
	'kasiyer bildirimi: frontend\'de (is_admin=false) asla gösterilmez',
	function () {
		update_option( 'qrm_reward_cap_kasiyer_bulunamadi', 1, false );
		$GLOBALS['qrms_test']['is_admin']   = false;
		$GLOBALS['qrms_test']['logged_in']  = true;
		$GLOBALS['qrms_test']['can_map']['manage_options'] = true;
		$_GET['page'] = 'qrms-yf-odul';

		ob_start();
		qrm_reward_cap_kasiyer_notice();
		$out = ob_get_clean();

		qrms_assert_same( '', $out, 'frontend isteğinde bildirim üretilmez' );
	}
);

qrms_test(
	'kasiyer bildirimi: ödül yönetimi ekranı dışında (başka bir admin sayfasında) gösterilmez',
	function () {
		update_option( 'qrm_reward_cap_kasiyer_bulunamadi', 1, false );
		$GLOBALS['qrms_test']['is_admin']   = true;
		$GLOBALS['qrms_test']['logged_in']  = true;
		$GLOBALS['qrms_test']['can_map']['manage_options'] = true;
		$_GET['page'] = 'qrms-yf-baska-ekran';

		ob_start();
		qrm_reward_cap_kasiyer_notice();
		$out = ob_get_clean();

		qrms_assert_same( '', $out, 'yalnızca ilgili ödül yönetimi ekranında gösterilir' );
	}
);

qrms_test(
	'kasiyer bildirimi: aynı istekte birden fazla basılmaz',
	function () {
		update_option( 'qrm_reward_cap_kasiyer_bulunamadi', 1, false );
		$GLOBALS['qrms_test']['is_admin']   = true;
		$GLOBALS['qrms_test']['logged_in']  = true;
		$GLOBALS['qrms_test']['can_map']['manage_options'] = true;
		$_GET['page'] = 'qrms-yf-odul';

		ob_start();
		qrm_reward_cap_kasiyer_notice();
		$ilk = ob_get_clean();

		ob_start();
		qrm_reward_cap_kasiyer_notice();
		$ikinci = ob_get_clean();

		qrms_assert_true( '' !== $ilk, 'ilk çağrıda basılır' );
		qrms_assert_same( '', $ikinci, 'aynı istekte ikinci çağrıda tekrar basılmaz' );
	}
);
