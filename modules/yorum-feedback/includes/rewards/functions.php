<?php
if (!defined('ABSPATH')) exit;

// ÖDÜL MODÜLÜ: ÇEKİRDEK FONKSİYONLAR (kod üretme, doğrulama, kullanma, e-posta)

/**
 * Modül fiilen çalışabilir durumda mı?
 * Ayar açık olsa bile Google yönlendirmesi kapalıysa ya da link boşsa
 * popup gösterilmez; bu durumda eski satır içi CTA davranışı korunur.
 */
function qrm_reward_is_active($settings = null) {
    if ($settings === null) $settings = qrm_pro_get_settings();
    return !empty($settings['qrm_reward_enabled'])
        && !empty($settings['google_review_enabled'])
        && !empty($settings['google_review_url']);
}

// --- KURULUM YARDIMCILARI (v4.2.0) ---

/**
 * Admin'in girdiği değeri kullanılabilir bir Google değerlendirme linkine çevirir.
 * Ham Place ID, Place ID içeren bir URL, hazır writereview linki veya Google
 * Haritalar/g.page linki kabul edilir. Kaydetmeyi ASLA engellemez; yalnızca
 * durum kodu döndürür, admin'e uyarı göstermek çağıranın işidir.
 *
 * @return array ['url' => string, 'status' => empty|ok|placeid|maps|unknown]
 */
function qrm_reward_normalize_google_url($raw) {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return ['url' => '', 'status' => 'empty'];
    }

    // 1) Doğrudan Place ID yapıştırılmış (ChIJ... gibi, URL değil)
    if (stripos($raw, 'http') !== 0 && preg_match('/^[A-Za-z0-9_\-]{15,}$/', $raw)) {
        return ['url' => 'https://search.google.com/local/writereview?placeid=' . rawurlencode($raw), 'status' => 'placeid'];
    }

    // 2) Zaten hazır değerlendirme linki
    if (stripos($raw, 'search.google.com/local/writereview') !== false) {
        return ['url' => esc_url_raw($raw), 'status' => 'ok'];
    }
    if (preg_match('#^https?://g\.page/r/[^/]+/review#i', $raw)) {
        return ['url' => esc_url_raw($raw), 'status' => 'ok'];
    }

    // 3) İçinde placeid/place_id parametresi olan herhangi bir URL
    if (preg_match('/[?&]place_?id=([A-Za-z0-9_\-]+)/i', $raw, $m)) {
        return ['url' => 'https://search.google.com/local/writereview?placeid=' . rawurlencode($m[1]), 'status' => 'placeid'];
    }

    // 4) Google Haritalar linki: çalışabilir ama değerlendirme penceresini
    //    doğrudan açmayabilir; kaydedilir, admin uyarılır.
    if (preg_match('#^https?://#i', $raw)) {
        $host = strtolower((string) parse_url($raw, PHP_URL_HOST));
        $maps_hosts = ['g.page', 'maps.app.goo.gl', 'goo.gl', 'maps.google.com', 'www.google.com', 'google.com'];
        return ['url' => esc_url_raw($raw), 'status' => in_array($host, $maps_hosts, true) ? 'maps' : 'unknown'];
    }

    return ['url' => esc_url_raw($raw), 'status' => 'unknown'];
}

/**
 * Kurulum sihirbazı için gerçek zamanlı durum listesi.
 * qrm_reward_is_active() ile aynı koşulları kontrol eder; link için ek olarak
 * biçim doğrulaması yapar (daha erken uyarmak için).
 */
function qrm_reward_setup_status($settings = null) {
    if ($settings === null) $settings = qrm_pro_get_settings();

    $url = trim((string) $settings['google_review_url']);
    $url_ok = ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) !== false);

    $has_active_template = false;
    foreach (qrm_reward_get_templates() as $t) {
        if ($t['active']) { $has_active_template = true; break; }
    }

    $steps = [
        [
            'key'   => 'url',
            'label' => 'Google değerlendirme linki girildi',
            'ok'    => $url_ok,
            'hint'  => ($url === '') ? 'Link alanı boş — kurulumun tek zorunlu adımı budur.' : 'Girilen değer geçerli bir bağlantı gibi görünmüyor.',
            'sub'   => 'kurulum',
        ],
        [
            'key'   => 'redirect',
            'label' => 'Google yönlendirmesi etkin',
            'ok'    => !empty($settings['google_review_enabled']),
            'hint'  => '"Google yönlendirmesini etkinleştir" kutusu işaretli değil.',
            'sub'   => 'kurulum',
        ],
        [
            'key'   => 'reward',
            'label' => 'Ödül popup modülü etkin',
            'ok'    => !empty($settings['qrm_reward_enabled']),
            'hint'  => '"Ödül sistemini etkinleştir" kutusu işaretli değil.',
            'sub'   => 'kurulum',
        ],
        [
            'key'   => 'template',
            'label' => 'En az bir aktif indirim şablonu var',
            'ok'    => $has_active_template,
            'hint'  => 'Aktif şablon yok; kod üretilemez.',
            'sub'   => 'sablonlar',
        ],
    ];

    $all_ok = true;
    $missing = [];
    foreach ($steps as $s) {
        if (!$s['ok']) { $all_ok = false; $missing[] = $s; }
    }

    return ['steps' => $steps, 'all_ok' => $all_ok, 'missing' => $missing];
}

/**
 * Aktif (geçerli) ödül kodu sayısı.
 *
 * Genel Bakış analiz şeridi BURADAN beslenir; istek içi statik önbellek
 * ikinci bir COUNT sorgusunu önler.
 *
 * @return int
 */
function qrm_reward_active_code_count() {
    static $sayi = null;
    if ($sayi !== null) {
        return $sayi;
    }

    if (!function_exists('qrm_reward_table')) {
        $sayi = 0;
        return $sayi;
    }

    global $wpdb;
    $table = qrm_reward_table();
    $suppress = $wpdb->suppress_errors(true);
    $sayi = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE status = %s",
        'active'
    ));
    $wpdb->suppress_errors($suppress);

    return $sayi;
}

/**
 * Kuru çalışma: verilen ortalama puanla gerçek gönderim mantığını (eşik + modül
 * durumu) hiçbir kayıt oluşturmadan hesaplar. Admin'deki "Test Et" için.
 */
function qrm_reward_simulate_submission($settings = null, $avg = 5) {
    if ($settings === null) $settings = qrm_pro_get_settings();

    $threshold   = floatval($settings['google_review_threshold']);
    $eligible    = !empty($settings['google_review_enabled']) && !empty($settings['google_review_url']) && $avg >= $threshold;
    $reward_on   = qrm_reward_is_active($settings);
    $template    = qrm_reward_get_default_template();

    return [
        'avg'          => (float) $avg,
        'threshold'    => $threshold,
        'eligible'     => $eligible,
        'reward_on'    => $reward_on,
        'show_reward'  => $eligible && $reward_on,
        'show_google'  => $eligible && !$reward_on,
        'template'     => $template ? qrm_reward_template_label($template) : '',
        'google_url'   => (string) $settings['google_review_url'],
    ];
}

// --- İNDİRİM ŞABLONLARI (option: qrm_reward_templates) ---

function qrm_reward_get_templates() {
    $templates = get_option('qrm_reward_templates', null);
    if (!is_array($templates)) {
        $templates = qrm_reward_default_templates();
    }
    $clean = [];
    foreach ($templates as $t) {
        if (empty($t['id'])) continue;
        $clean[] = [
            'id'         => sanitize_key($t['id']),
            'name'       => isset($t['name']) ? sanitize_text_field($t['name']) : '',
            'percent'    => isset($t['percent']) ? floatval($t['percent']) : 0,
            'active'     => !empty($t['active']) ? 1 : 0,
            'is_default' => !empty($t['is_default']) ? 1 : 0,
        ];
    }
    return $clean;
}

function qrm_reward_save_templates($templates) {
    $clean = [];
    $default_seen = false;
    foreach ($templates as $t) {
        $id = !empty($t['id']) ? sanitize_key($t['id']) : qrm_reward_new_template_id();
        $name = isset($t['name']) ? sanitize_text_field($t['name']) : '';
        if ($name === '') continue;
        $percent = isset($t['percent']) ? floatval(str_replace(',', '.', $t['percent'])) : 0;
        $percent = min(100, max(0, $percent));
        $is_default = (!$default_seen && !empty($t['is_default'])) ? 1 : 0;
        if ($is_default) $default_seen = true;
        $clean[] = [
            'id'         => $id,
            'name'       => $name,
            'percent'    => $percent,
            'active'     => !empty($t['active']) ? 1 : 0,
            'is_default' => $is_default,
        ];
    }
    update_option('qrm_reward_templates', $clean, false);
    return $clean;
}

function qrm_reward_new_template_id() {
    return 'tpl' . substr(md5(uniqid('qrm', true)), 0, 8);
}

function qrm_reward_get_template($id) {
    foreach (qrm_reward_get_templates() as $t) {
        if ($t['id'] === $id) return $t;
    }
    return null;
}

/**
 * Otomatik (popup) kod üretiminde kullanılacak şablon:
 * önce "varsayılan" işaretli ve aktif olan, yoksa ilk aktif şablon.
 */
function qrm_reward_get_default_template() {
    $templates = qrm_reward_get_templates();
    foreach ($templates as $t) {
        if ($t['is_default'] && $t['active']) return $t;
    }
    foreach ($templates as $t) {
        if ($t['active']) return $t;
    }
    return null;
}

function qrm_reward_template_label($template) {
    if (!$template) return 'İndirim';
    $percent = (float) $template['percent'];
    if ($percent > 0) {
        $pretty = (floor($percent) == $percent) ? number_format($percent, 0) : number_format($percent, 1);
        return $template['name'] . ' (%' . $pretty . ' indirim)';
    }
    return $template['name'];
}

// --- KOD ÜRETİMİ ---

/**
 * İnsan tarafından okunabilir kod: QRM-XXXXXX
 * Karışabilen karakterler (0/O, 1/I/L) alfabeden çıkarıldı.
 */
function qrm_reward_random_code($prefix = 'QRM', $length = 6) {
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $idx = function_exists('random_int') ? random_int(0, $max) : wp_rand(0, $max);
        $out .= $alphabet[$idx];
    }
    return $prefix . '-' . $out;
}

/** Veritabanında çakışmayan benzersiz bir kod üretir. */
function qrm_reward_generate_unique_code() {
    global $wpdb;
    $table = qrm_reward_table();
    for ($i = 0; $i < 12; $i++) {
        $code = qrm_reward_random_code();
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE code = %s", $code));
        if (!$exists) return $code;
    }
    // Aşırı düşük ihtimal: son çare olarak zaman damgalı bir kod
    return qrm_reward_random_code('QRM', 8);
}

// --- SORGULAMA ---

function qrm_reward_normalize_email($raw) {
    $email = sanitize_email(strtolower(trim((string) $raw)));
    return is_email($email) ? $email : '';
}

function qrm_reward_find_by_email($email) {
    global $wpdb;
    $table = qrm_reward_table();
    $email = qrm_reward_normalize_email($email);
    if ($email === '') return null;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE email = %s", $email));
}

function qrm_reward_find_by_code($code) {
    global $wpdb;
    $table = qrm_reward_table();
    $code = strtoupper(sanitize_text_field(trim((string) $code)));
    if ($code === '') return null;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE code = %s", $code));
}

/* -------------------------------------------------------------------------
 * KOD TALEBİ DOĞRULAMASI (v4.2.2)
 *
 * Popup'taki e-posta adımı, kod üretimini tetikleyen tek yerdir. Eskiden bu
 * uç istemcinin gönderdiği review_id'ye körü körüne güveniyordu: değer hiçbir
 * sahiplik, varlık ya da eşik kontrolünden geçmiyor, yalnızca source_review_id
 * olarak kaydediliyordu. Yani "gerçekten eşiği geçen bir yorum bırakıldı mı?"
 * sorusu sunucuda hiç sorulmuyordu; akışın tamamı istemcideki popup'a
 * emanetti. Nonce da popup ile birlikte ön yüze basıldığı için, tek bir
 * 5 yıldızlı yorum bırakan biri onu ele geçirip farklı e-postalarla kod
 * üretmeye devam edebiliyordu.
 *
 * Çözüm: yorum KAYDEDİLİRKEN sunucuda tek kullanımlık bir "talep anahtarı"
 * üretilir ve yalnızca o gönderimin yanıtıyla istemciye verilir. Kod ancak
 * geçerli, süresi dolmamış ve henüz harcanmamış bir anahtarla üretilebilir.
 * ---------------------------------------------------------------------- */

/** Talep anahtarının transient adı. */
function qrm_reward_claim_key($review_id) {
    return 'qrm_rw_claim_' . (int) $review_id;
}

/** Talep anahtarının ömrü (saniye). Popup akışı dakikalar sürer, saat yeter. */
function qrm_reward_claim_ttl() {
    /**
     * Ödül talep anahtarının geçerlilik süresi.
     *
     * @param int $ttl Saniye.
     */
    return (int) apply_filters('qrm_reward_claim_ttl', HOUR_IN_SECONDS);
}

/**
 * Yeni bir talep anahtarı üretir ve saklar.
 *
 * Saklanan değer anahtarın KENDİSİ değil hash'idir: veritabanı okumasıyla
 * geçerli anahtar ele geçirilemesin.
 *
 * @param int $review_id Yorum kimliği.
 * @return string İstemciye verilecek anahtar ('' = üretilemedi).
 */
function qrm_reward_issue_claim($review_id) {
    $review_id = (int) $review_id;
    if ($review_id <= 0) return '';

    $token = wp_generate_password(32, false);
    set_transient(qrm_reward_claim_key($review_id), wp_hash($token), qrm_reward_claim_ttl());

    return $token;
}

/** Anahtarı harcar (tek kullanımlık). */
function qrm_reward_consume_claim($review_id) {
    delete_transient(qrm_reward_claim_key($review_id));
}

/**
 * Bu yorum için daha önce kod üretilmiş mi?
 *
 * @param int $review_id Yorum kimliği.
 * @return bool
 */
function qrm_reward_review_has_code($review_id) {
    global $wpdb;
    $table = qrm_reward_table();

    return (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE source_review_id = %d LIMIT 1",
        (int) $review_id
    ));
}

/**
 * Kod talebini sunucu tarafında doğrular.
 *
 * Sırayla: anahtar geçerli mi -> yorum gerçekten var mı -> puanı Google
 * eşiğini geçiyor mu -> bu yoruma daha önce kod verilmiş mi.
 *
 * @param int    $review_id Yorum kimliği (istemciden gelir, DOĞRULANIR).
 * @param string $token     Talep anahtarı.
 * @param array|null $settings Ayarlar.
 * @return true|WP_Error
 */
function qrm_reward_verify_claim($review_id, $token, $settings = null) {
    global $wpdb;

    if ($settings === null) $settings = qrm_pro_get_settings();

    $review_id = (int) $review_id;
    $token     = is_string($token) ? trim($token) : '';

    if ($review_id <= 0 || $token === '') {
        return new WP_Error('qrm_reward_claim', qrm_ceviri_review(__('Bu ödül talebi doğrulanamadı. Lütfen değerlendirmenizi yeniden gönderin.', 'qrms')));
    }

    $saklanan = get_transient(qrm_reward_claim_key($review_id));
    if (!is_string($saklanan) || $saklanan === '' || !hash_equals($saklanan, wp_hash($token))) {
        return new WP_Error('qrm_reward_claim', qrm_ceviri_review(__('Bu ödül talebi doğrulanamadı. Lütfen değerlendirmenizi yeniden gönderin.', 'qrms')));
    }

    $table  = $wpdb->prefix . 'qrm_reviews';
    $review = $wpdb->get_row($wpdb->prepare(
        "SELECT id, rating FROM {$table} WHERE id = %d",
        $review_id
    ));

    if (!$review) {
        return new WP_Error('qrm_reward_claim', qrm_ceviri_review(__('Değerlendirme bulunamadı.', 'qrms')));
    }

    // Eşik, popup'ın gösterilme koşuluyla AYNI olmalı; aksi halde sunucu
    // istemcinin gösterdiğinden farklı bir kural uygulardı.
    $threshold = (float) $settings['google_review_threshold'];
    if ((float) $review->rating < $threshold) {
        return new WP_Error('qrm_reward_claim', qrm_ceviri_review(__('Bu değerlendirme ödül koşulunu karşılamıyor.', 'qrms')));
    }

    if (qrm_reward_review_has_code($review_id)) {
        return new WP_Error('qrm_reward_claim', qrm_ceviri_review(__('Bu değerlendirme için zaten bir indirim kodu üretilmiş.', 'qrms')));
    }

    return true;
}

function qrm_reward_status_label($status) {
    $map = [
        'active'  => 'Geçerli',
        'used'    => 'Kullanıldı',
        'expired' => 'Süresi doldu',
        'revoked' => 'İptal edildi',
    ];
    return isset($map[$status]) ? $map[$status] : $status;
}

// --- OLUŞTURMA / DURUM DEĞİŞTİRME ---

/**
 * Yeni bir ödül kodu oluşturur.
 *
 * @param array $args email, template_id, is_manual, source_review_id, ip_address
 * @return array|WP_Error ['code' => ..., 'discount_label' => ..., 'row' => object]
 */
function qrm_reward_create_code($args = []) {
    global $wpdb;
    $table = qrm_reward_table();

    $args = wp_parse_args($args, [
        'email'            => '',
        'template_id'      => '',
        'is_manual'        => 0,
        'source_review_id' => null,
        'ip_address'       => '',
    ]);

    $email = qrm_reward_normalize_email($args['email']);
    if ($email === '' && !$args['is_manual']) {
        return new WP_Error('qrm_reward_email', qrm_ceviri_review(__('Geçerli bir e-posta adresi girin.', 'qrms')));
    }

    // 1 e-posta = 1 kod (DB'deki UNIQUE KEY son savunma hattı, burada kullanıcıya mesaj döneriz)
    if ($email !== '') {
        $existing = qrm_reward_find_by_email($email);
        if ($existing) {
            return new WP_Error('qrm_reward_exists', qrm_ceviri_review(__('Bu e-posta adresi daha önce bir indirim kodu almış.', 'qrms')), ['row' => $existing]);
        }
    }

    $template = $args['template_id'] !== '' ? qrm_reward_get_template($args['template_id']) : qrm_reward_get_default_template();
    if (!$template) {
        return new WP_Error('qrm_reward_template', qrm_ceviri_review(__('Kullanılabilir bir indirim şablonu bulunamadı. Ödül Sistemi sayfasından en az bir aktif şablon tanımlayın.', 'qrms')));
    }

    $code  = qrm_reward_generate_unique_code();
    $label = qrm_reward_template_label($template);

    $inserted = $wpdb->insert($table, [
        'created_at'       => current_time('mysql'),
        'email'            => ($email !== '' ? $email : null),
        'code'             => $code,
        'template_id'      => $template['id'],
        'discount_label'   => $label,
        'status'           => 'active',
        'is_manual'        => $args['is_manual'] ? 1 : 0,
        'source_review_id' => $args['source_review_id'] ? intval($args['source_review_id']) : null,
        'ip_address'       => sanitize_text_field($args['ip_address']),
    ]);

    if (!$inserted) {
        // Yarış durumu: aynı e-posta iki istekte birden gelmiş olabilir (UNIQUE KEY).
        if ($email !== '') {
            $existing = qrm_reward_find_by_email($email);
            if ($existing) {
                return new WP_Error('qrm_reward_exists', qrm_ceviri_review(__('Bu e-posta adresi daha önce bir indirim kodu almış.', 'qrms')), ['row' => $existing]);
            }
        }
        return new WP_Error('qrm_reward_db', qrm_ceviri_review(__('Kod kaydedilemedi, lütfen tekrar deneyin.', 'qrms')));
    }

    // Analitik ikincil kayıttır: ödül tablosu kaynak kalır. Kod/e-posta yazılmaz.
    if (function_exists('qmo_analitik_yaz')) {
        qmo_analitik_yaz(['event_type' => 'reward_issued']);
    }

    return [
        'id'             => (int) $wpdb->insert_id,
        'code'           => $code,
        'discount_label' => $label,
        'template'       => $template,
        'email'          => $email,
    ];
}

function qrm_reward_set_status($id, $status) {
    global $wpdb;
    $table = qrm_reward_table();
    $allowed = ['active', 'used', 'expired', 'revoked'];
    if (!in_array($status, $allowed, true)) return false;

    $data = ['status' => $status];
    $format = ['%s'];
    if ($status === 'used') {
        $data['used_at'] = current_time('mysql');
        $format[] = '%s';
    }
    $ok = (false !== $wpdb->update($table, $data, ['id' => intval($id)], $format, ['%d']));

    if ($ok && $status === 'used' && function_exists('qmo_analitik_yaz')) {
        qmo_analitik_yaz(['event_type' => 'reward_redeemed']);
    }

    return $ok;
}

function qrm_reward_delete($id) {
    global $wpdb;
    return (false !== $wpdb->delete(qrm_reward_table(), ['id' => intval($id)], ['%d']));
}

// --- E-POSTA ---

/**
 * İndirim kodunu müşteriye HTML e-posta olarak gönderir.
 * wp_mail_content_type filtresi geçici olarak eklenir ve gönderim sonrası kaldırılır.
 */
function qrm_reward_send_code_email($email, $code, $discount_label) {
    $settings  = qrm_pro_get_settings();
    $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $subject   = !empty($settings['qrm_reward_email_subject']) ? $settings['qrm_reward_email_subject'] : 'İndirim kodunuz hazır!';
    $intro     = !empty($settings['qrm_reward_email_intro']) ? $settings['qrm_reward_email_intro'] : '';

    $body  = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:0 auto;padding:24px;color:#1e293b;">';
    $body .= '<h2 style="margin:0 0 6px;font-size:20px;">' . esc_html($site_name) . '</h2>';
    $body .= '<p style="font-size:15px;line-height:1.6;margin:0 0 18px;">' . esc_html($intro) . '</p>';
    $body .= '<div style="text-align:center;border:2px dashed #94a3b8;border-radius:12px;padding:18px;margin:0 0 18px;">';
    $body .= '<div style="font-size:12px;letter-spacing:1px;text-transform:uppercase;opacity:.6;margin-bottom:6px;">İndirim Kodunuz</div>';
    $body .= '<div style="font-size:28px;font-weight:bold;letter-spacing:3px;font-family:monospace;">' . esc_html($code) . '</div>';
    if ($discount_label !== '') {
        $body .= '<div style="font-size:14px;margin-top:8px;">' . esc_html($discount_label) . '</div>';
    }
    $body .= '</div>';
    $body .= '<p style="font-size:13px;opacity:.7;line-height:1.6;margin:0;">Bu kod tek kullanımlıktır ve yalnızca bu e-posta adresi için oluşturulmuştur. Siparişinizde kasada göstermeniz yeterlidir.</p>';
    $body .= '</div>';

    add_filter('wp_mail_content_type', 'qrm_reward_mail_content_type');
    $sent = wp_mail($email, $subject, $body);
    remove_filter('wp_mail_content_type', 'qrm_reward_mail_content_type');

    return $sent;
}

function qrm_reward_mail_content_type() {
    return 'text/html';
}
