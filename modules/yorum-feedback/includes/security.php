<?php
if (!defined('ABSPATH')) exit;

// --- SPAM KORUMA & TR TELEFON YARDIMCILARI ---

// Zaman tuzağı: imzalı zaman damgası üret / doğrula (bot form açar açmaz gönderir)
function qrm_pro_make_ts_token() {
    $t = time();
    return $t . '.' . hash_hmac('sha256', $t . '|qrm_ts', wp_salt('auth'));
}
function qrm_pro_check_ts_token($token, $min = 3, $max = 3600) {
    if (empty($token) || strpos($token, '.') === false) return false;
    list($t, $sig) = explode('.', $token, 2);
    $t = intval($t);
    $expected = hash_hmac('sha256', $t . '|qrm_ts', wp_salt('auth'));
    if (!hash_equals($expected, (string) $sig)) return false;
    $elapsed = time() - $t;
    return ($elapsed >= $min && $elapsed <= $max);
}

// Matematik captcha: soru + imzalı doğru cevap
function qrm_pro_make_captcha() {
    $a = wp_rand(1, 9);
    $b = wp_rand(1, 9);
    return [
        'a'    => $a,
        'b'    => $b,
        'hash' => hash_hmac('sha256', (string) ($a + $b), wp_salt('nonce')),
    ];
}
function qrm_pro_check_captcha($answer, $hash) {
    if ($hash === '') return false;
    $expected = hash_hmac('sha256', (string) intval($answer), wp_salt('nonce'));
    return hash_equals($expected, (string) $hash);
}

// Akış koruması (aynı IP'den kısa sürede aşırı gönderim). Eşik restoran/NAT için cömert tutuldu.
// Sorun yoksa true, varsa hata mesajı (string) döner.
function qrm_pro_rate_limit_guard() {
    $ip  = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '0.0.0.0';
    $key = 'qrm_rl_' . md5($ip);
    $cnt = (int) get_transient($key);
    if ($cnt >= 20) {
        return qrm_ceviri_review(__('Çok fazla gönderim algılandı, lütfen birkaç dakika sonra tekrar deneyin.', 'qrms'));
    }
    set_transient($key, $cnt + 1, 5 * MINUTE_IN_SECONDS);
    return true;
}

// Honeypot: botlar gizli alanı doldurur, gerçek kullanıcı görmez. Doluysa true döner.
function qrm_pro_honeypot_tripped($field = 'qrm_website') {
    return !empty($_POST[$field]);
}

// Captcha içermeyen temel spam kontrolü (zaman tuzağı + akış koruması).
// v4.2.0: özel form builder formları bunu kullanır — honeypot çağıran tarafta
// kontrol edilir, çünkü "sessizce başarı döndürme" davranışı forma göre değişir.
function qrm_pro_spam_guard_basic() {
    if (!qrm_pro_check_ts_token(isset($_POST['qrm_ts']) ? $_POST['qrm_ts'] : '')) {
        return qrm_ceviri_review(__('Form çok hızlı gönderildi, lütfen birkaç saniye bekleyip tekrar deneyin.', 'qrms'));
    }
    return qrm_pro_rate_limit_guard();
}

// Birleşik spam kontrolü (yorum/iletişim formu): zaman tuzağı + matematik captcha + akış koruması.
// Sorun yoksa true, varsa hata mesajı (string) döner.
function qrm_pro_spam_guard() {
    if (!qrm_pro_check_ts_token(isset($_POST['qrm_ts']) ? $_POST['qrm_ts'] : '')) {
        return qrm_ceviri_review(__('Form çok hızlı gönderildi, lütfen birkaç saniye bekleyip tekrar deneyin.', 'qrms'));
    }
    if (!qrm_pro_check_captcha(
        isset($_POST['qrm_captcha']) ? $_POST['qrm_captcha'] : '',
        isset($_POST['qrm_captcha_hash']) ? $_POST['qrm_captcha_hash'] : ''
    )) {
        return qrm_ceviri_review(__('Güvenlik sorusunun cevabı hatalı.', 'qrms'));
    }
    return qrm_pro_rate_limit_guard();
}

// --- ARDIŞIK GÖNDERİM KISITI (COOLDOWN, v4.2.1) ---
//
// Aynı kişi, admin panelden ayarlanan süre dolmadan ikinci bir form gönderemez.
// TÜM formlar için geçerlidir: yorum formu, iletişim formu ve özel form builder
// formları aynı fonksiyonları kullanır.
//
// Kimliklendirme, mevcut akış korumasının yöntemini genişletir: temel anahtar IP
// adresidir (qrm_pro_client_ip); gönderimde e-posta veya telefon varsa onlar da
// ayrı birer anahtar olarak işaretlenir. Böylece IP değiştiren ama aynı e-postayı
// kullanan (ya da aynı ağdaki farklı cihazlardan gönderen) kişi de yakalanır.

/** Ayarlardaki cooldown süresi (dakika). 0 = kapalı. */
function qrm_pro_cooldown_minutes($settings = null) {
    if ($settings === null) $settings = qrm_pro_get_settings();
    $minutes = isset($settings['qrm_spam_cooldown_minutes']) ? intval($settings['qrm_spam_cooldown_minutes']) : 10;
    return max(0, min(1440, $minutes));
}

/**
 * Yetkili kullanıcılar kısıttan muaftır (adminin test amaçlı art arda gönderimi
 * engellenmesin diye). Eşik olarak edit_posts seçildi: eklentinin ön yüzde
 * "yetkili kullanıcı" saydığı yetkinin aynısı (bkz. qrm_cf_shortcode_notice).
 */
function qrm_pro_cooldown_exempt() {
    return function_exists('current_user_can') && current_user_can('edit_posts');
}

/** Verilen kimlik bilgilerinden transient anahtarları üretir. */
function qrm_pro_cooldown_keys($identifiers = []) {
    $keys = ['qrm_cd_ip_' . md5(qrm_pro_client_ip())];

    if (!empty($identifiers['email'])) {
        $email = strtolower(trim((string) $identifiers['email']));
        if ($email !== '') $keys[] = 'qrm_cd_em_' . md5($email);
    }
    if (!empty($identifiers['phone'])) {
        $phone = preg_replace('/[^0-9]/', '', (string) $identifiers['phone']);
        if ($phone !== '') $keys[] = 'qrm_cd_ph_' . md5($phone);
    }

    return $keys;
}

/**
 * Cooldown kontrolü. Sorun yoksa true, varsa kalan süreyi belirten hata mesajı döner.
 *
 * @param array $identifiers ['email' => ..., 'phone' => ...] (opsiyonel)
 */
function qrm_pro_cooldown_guard($identifiers = [], $settings = null) {
    $minutes = qrm_pro_cooldown_minutes($settings);
    if ($minutes <= 0) return true;
    if (qrm_pro_cooldown_exempt()) return true;

    $now = time();
    $blocked_until = 0;

    foreach (qrm_pro_cooldown_keys($identifiers) as $key) {
        $until = (int) get_transient($key);
        if ($until > $now && $until > $blocked_until) {
            $blocked_until = $until;
        }
    }

    if ($blocked_until === 0) return true;

    $remaining = (int) ceil(($blocked_until - $now) / 60);
    if ($remaining < 1) $remaining = 1;

    return sprintf(
        qrm_ceviri_review(__('Çok sık gönderim yapıyorsunuz, lütfen %d dakika sonra tekrar deneyin.', 'qrms')),
        $remaining
    );
}

/** Başarılı gönderimden sonra cooldown penceresini başlatır. */
function qrm_pro_cooldown_mark($identifiers = [], $settings = null) {
    $minutes = qrm_pro_cooldown_minutes($settings);
    if ($minutes <= 0) return false;
    if (qrm_pro_cooldown_exempt()) return false;

    $seconds = $minutes * MINUTE_IN_SECONDS;
    $until   = time() + $seconds;

    foreach (qrm_pro_cooldown_keys($identifiers) as $key) {
        set_transient($key, $until, $seconds);
    }
    return true;
}

// TR cep telefonu normalize: geçersizse false, boşsa '', geçerliyse "05XXXXXXXXX" döner
function qrm_pro_normalize_tr_phone($raw) {
    $d = preg_replace('/[^0-9]/', '', (string) $raw);
    if ($d === '') return '';
    if (strlen($d) === 12 && substr($d, 0, 2) === '90') $d = substr($d, 2);
    if (strlen($d) === 11 && $d[0] === '0')            $d = substr($d, 1);
    if (strlen($d) === 10 && $d[0] === '5') return '0' . $d;
    return false;
}

// İstemci IP'si (ödül modülü ve akış korumaları için ortak yardımcı)
function qrm_pro_client_ip() {
    return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '0.0.0.0';
}
