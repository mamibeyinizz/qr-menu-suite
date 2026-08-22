<?php
if (!defined('ABSPATH')) exit;

// 9. YAPAY ZEKÂ İÇGÖRÜLERİ (Gemini)
//
// "Detaylı İçgörüler" ekranındaki sayısal ortalamalar neyin düştüğünü söyler ama
// NEDEN düştüğünü söylemez — o bilgi yorum metinlerinin içindedir. Bu dosya,
// yayındaki yorumları Gemini'ye özetletip tekrar eden şikâyetleri ve övgüleri
// çıkarır.
//
// TASARIM KARARLARI
//
// 1) ANAHTAR: qr-chatbot modülünün zaten yapılandırılmış `gemini_api_key`
//    option'ı yeniden kullanılır — restoran sahibinden ikinci bir anahtar
//    istenmez. Modülün `qmo_` FONKSİYONLARINA bağlanmadığı kuralı korunur
//    (bkz. module.php başlığı): burada yalnızca option ADI paylaşılır,
//    _qmo-ortak yüklenmez ve qmo_* çağrılmaz.
//
// 2) TETİKLEME: sayfa açılışında otomatik çağrı YOK. Her açılış ücretli bir
//    API isteği demek olurdu. Özet, "Özet oluştur" butonuyla üretilir ve
//    transient'ta saklanır.
//
// 3) GÖNDERİLEN VERİ: yalnızca PUANLAR ve YORUM METNİ. Müşteri adı, telefonu,
//    e-postası ve masa numarası gönderilmez — özet için gereksizdir ve bunlar
//    restoranın müşterisine ait kişisel verilerdir. Sorgu bu yüzden `SELECT *`
//    değil, iki sütunla sınırlıdır.

/** Özetin saklandığı transient'ın önekidir. */
const QRM_AI_TRANSIENT_PREFIX = 'qrm_ai_ozet_';

/** Özete girecek en fazla yorum sayısı (istem boyutunu sınırlar). */
const QRM_AI_MAX_REVIEWS = 120;

/**
 * Gemini API anahtarı.
 *
 * @return string Boşsa özellik kapalıdır.
 */
function qrm_ai_api_key() {
    return trim((string) get_option('gemini_api_key', ''));
}

/**
 * Kullanılacak model.
 *
 * qr-chatbot ile aynı option; ortak yardımcı yüklüyse onun kararı geçerlidir.
 *
 * @return string
 */
function qrm_ai_model() {
    if (function_exists('qmo_gemini_model')) {
        return qmo_gemini_model();
    }

    $m = trim((string) get_option('qmo_gemini_model', ''));
    return $m !== '' ? $m : 'gemini-3-flash-preview';
}

/**
 * Özete girecek yorumlar — SADECE puan ve metin.
 *
 * Kişisel veri sütunları (customer_name, customer_phone, table_no) bilinçli
 * olarak seçilmez: dış bir servise gönderilecek veri, gönderilmesi gerekmeyen
 * hiçbir alanı taşımamalı.
 *
 * @return array<int,array{rating:float,comment:string}>
 */
function qrm_ai_collect_reviews() {
    global $wpdb;

    if (!qrm_pro_reviews_table_exists()) {
        return [];
    }

    $table = $wpdb->prefix . 'qrm_reviews';

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT rating, comment FROM {$table}
             WHERE status = 1 AND comment <> ''
             ORDER BY created_at DESC
             LIMIT %d",
            QRM_AI_MAX_REVIEWS
        ),
        ARRAY_A
    );

    $out = [];

    foreach ((array) $rows as $row) {
        $comment = trim((string) $row['comment']);

        if ($comment === '') continue;

        $out[] = [
            'rating'  => (float) $row['rating'],
            'comment' => $comment,
        ];
    }

    return $out;
}

/**
 * Gemini'ye gönderilecek istem metni.
 *
 * Saf fonksiyon (WordPress'e bağımlı değil) — doğrudan test edilir.
 *
 * @param array $reviews qrm_ai_collect_reviews() çıktısı.
 * @return string
 */
function qrm_ai_build_prompt(array $reviews) {
    $satirlar = [];

    foreach ($reviews as $r) {
        $puan = number_format((float) $r['rating'], 1);
        // Tek bir yorum istemin tamamını doldurmasın.
        $metin = mb_substr(preg_replace('/\s+/u', ' ', (string) $r['comment']), 0, 400);
        $satirlar[] = '- (' . $puan . '/5) ' . $metin;
    }

    return "Aşağıda bir restoranın müşteri değerlendirmeleri var. Her satır "
         . "\"(puan/5) yorum\" biçiminde.\n\n"
         . implode("\n", $satirlar) . "\n\n"
         . "Bu değerlendirmeleri restoran sahibi için TÜRKÇE özetle. Şu başlıkları kullan "
         . "ve her maddede kaç yorumda geçtiğini belirt:\n"
         . "1. En çok övülen 3 şey\n"
         . "2. En çok şikâyet edilen 3 şey\n"
         . "3. Bu hafta atılabilecek 3 somut adım\n\n"
         . "Kısa ve doğrudan yaz. Yorumlarda geçmeyen hiçbir şey uydurma; "
         . "veri yetersizse bunu açıkça söyle.";
}

/**
 * Gemini yanıtını çözümler.
 *
 * Saf fonksiyon — hata dallarının hepsi testten geçirilebilsin diye HTTP
 * çağrısından ayrıldı.
 *
 * @param mixed $data json_decode edilmiş yanıt gövdesi.
 * @return array{ok:bool,text:string,error:string}
 */
function qrm_ai_parse_response($data) {
    if (!is_array($data)) {
        return ['ok' => false, 'text' => '', 'error' => 'Yanıt okunamadı.'];
    }

    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        $text = trim((string) $data['candidates'][0]['content']['parts'][0]['text']);

        if ($text !== '') {
            return ['ok' => true, 'text' => $text, 'error' => ''];
        }
    }

    // API anahtarı gibi ayrıntılar ekrana basılmaz; günlüğe yazılır.
    if (isset($data['error']['message'])) {
        qrm_pro_debug_log('Gemini API hatası: ' . $data['error']['message']);
        return ['ok' => false, 'text' => '', 'error' => 'Gemini isteği reddetti. Anahtarınızı ve kotanızı kontrol edin.'];
    }

    if (isset($data['candidates'][0]['finishReason'])) {
        return [
            'ok'    => false,
            'text'  => '',
            'error' => 'Yanıt tamamlanmadı (' . sanitize_text_field($data['candidates'][0]['finishReason']) . ').',
        ];
    }

    return ['ok' => false, 'text' => '', 'error' => 'Beklenmeyen bir yanıt geldi.'];
}

/**
 * Saklanan özetin anahtarı.
 *
 * Yorum sayısı ve en yüksek id anahtara girer: yeni bir yorum yayına alındığında
 * anahtar değişir ve eski özet kendiliğinden geçersizleşir — ayrı bir temizleme
 * kancası gerekmez.
 *
 * @return string
 */
function qrm_ai_cache_key() {
    global $wpdb;

    if (!qrm_pro_reviews_table_exists()) {
        return QRM_AI_TRANSIENT_PREFIX . 'yok';
    }

    $table = $wpdb->prefix . 'qrm_reviews';
    $damga = (string) $wpdb->get_var("SELECT CONCAT(COUNT(*), '-', COALESCE(MAX(id), 0)) FROM {$table} WHERE status = 1");

    return QRM_AI_TRANSIENT_PREFIX . md5($damga);
}

/**
 * Saklanan özet (yoksa boş string).
 *
 * @return string
 */
function qrm_ai_cached_summary() {
    $v = get_transient(qrm_ai_cache_key());
    return is_string($v) ? $v : '';
}

/**
 * Veritabanı bağlantısını uzun bir dış istek boyunca serbest bırakır.
 *
 * Bu modül bilinçli olarak `_qmo-ortak`'ı yüklemez (bkz. module.php başlığı),
 * bu yüzden oradaki qmo_db_serbest_birak()'ın eşi burada kendi ad alanında
 * durur. Gerekçe aynı: 45 saniyelik bir Gemini çağrısı boyunca MySQL bağlantısı
 * tek bir sorgu çalıştırmadan havuzda yer kaplıyordu.
 *
 * DİKKAT: wpdb::close() sonrası bağlantı kendiliğinden geri gelmez —
 * sonraki sorgular sessizce false döner. Geri açmak qrm_db_geri_baglan()'ın
 * işidir ve veritabanına dokunan her kod onun ARDINDA olmalıdır.
 *
 * @return bool Bağlantı gerçekten kapatıldıysa true.
 */
function qrm_db_serbest_birak() {
    global $wpdb;

    /**
     * Uzun dış istekler boyunca veritabanı bağlantısı bırakılsın mı?
     *
     * @param bool $serbest Varsayılan true.
     */
    if (!apply_filters('qrm_db_baglanti_serbest', true)) {
        return false;
    }

    if (!isset($wpdb) || !is_object($wpdb)
        || !method_exists($wpdb, 'close') || !method_exists($wpdb, 'db_connect')) {
        return false;
    }

    return (bool) $wpdb->close();
}

/**
 * qrm_db_serbest_birak() ile kapatılan bağlantıyı geri açar.
 *
 * @param bool $kapatildi qrm_db_serbest_birak() çıktısı.
 * @return void
 */
function qrm_db_geri_baglan($kapatildi) {
    global $wpdb;

    if (!$kapatildi) {
        return;
    }

    if (isset($wpdb) && is_object($wpdb) && method_exists($wpdb, 'db_connect')) {
        $wpdb->db_connect();
    }
}

/**
 * Özeti üretir ve saklar.
 *
 * @return array{ok:bool,text:string,error:string}
 */
function qrm_ai_generate_summary() {
    $key = qrm_ai_api_key();

    if ($key === '') {
        return ['ok' => false, 'text' => '', 'error' => 'Gemini API anahtarı tanımlı değil.'];
    }

    $reviews = qrm_ai_collect_reviews();

    if (count($reviews) < 3) {
        return ['ok' => false, 'text' => '', 'error' => 'Özet için en az 3 yayındaki yorum gerekir.'];
    }

    // Model adı bağlantı açıkken çözülür; aşağıdaki 45 saniyelik çağrı
    // boyunca veritabanına gidilemeyecek.
    $model = qrm_ai_model();

    $db_kapali = qrm_db_serbest_birak();

    $response = wp_remote_post(
        'https://generativelanguage.googleapis.com/v1beta/models/' . $model
            . ':generateContent?key=' . rawurlencode($key),
        [
            'timeout' => 45,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode([
                'contents' => [[
                    'role'  => 'user',
                    'parts' => [['text' => qrm_ai_build_prompt($reviews)]],
                ]],
            ]),
        ]
    );

    // Bundan sonrası (transient anahtarı bir sorgu açar, ardından yazma)
    // veritabanına dokunur — geri bağlanmadan devam edilemez.
    qrm_db_geri_baglan($db_kapali);

    if (is_wp_error($response)) {
        return ['ok' => false, 'text' => '', 'error' => 'Bağlantı hatası: ' . $response->get_error_message()];
    }

    $sonuc = qrm_ai_parse_response(json_decode(wp_remote_retrieve_body($response), true));

    if ($sonuc['ok']) {
        set_transient(qrm_ai_cache_key(), $sonuc['text'], DAY_IN_SECONDS);
    }

    return $sonuc;
}

/**
 * AJAX: "Özet oluştur".
 *
 * @return void
 */
add_action('wp_ajax_qrm_ai_summary', 'qrm_ai_ajax_summary');
function qrm_ai_ajax_summary() {
    check_ajax_referer('qrm_ai_summary');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Bu işlem için yetkiniz yok.');
    }

    $sonuc = qrm_ai_generate_summary();

    if (!$sonuc['ok']) {
        wp_send_json_error($sonuc['error']);
    }

    wp_send_json_success($sonuc['text']);
}
