<?php
/**
 * KVKK / GDPR araçları — kişisel veri dışa aktarma, silme ve saklama.
 *
 * Modül üç yerde kişisel veri tutar:
 *
 *   wp_qrm_reviews          -> customer_name, customer_phone, comment,
 *                              internal_note, consent_* (e-posta YOK)
 *   wp_qrm_reward_codes     -> email, ip_address, source_review_id
 *   wp_qrm_cf_submissions   -> data (JSON; e-posta alanı içerebilir), ip_address
 *
 * WordPress'in gizlilik araçları e-posta adresi üzerinden çalışır. Yorum
 * tablosunda e-posta sütunu olmadığı için köprü ödül kaydıdır: bir e-postaya
 * verilmiş ödül `source_review_id` ile yorumu işaret eder, o yorumun kişisel
 * alanları da aynı kişiye aittir.
 *
 * @package QR_Menu_Suite
 */

defined('ABSPATH') || exit;

/**
 * Silme sonrası ad alanına yazılan değer.
 */
if (!defined('QRM_PRIVACY_ANON_AD')) {
    define('QRM_PRIVACY_ANON_AD', 'Anonim');
}

/* -------------------------------------------------------------------------
 * DIŞA AKTARMA
 * ---------------------------------------------------------------------- */

add_filter('wp_privacy_personal_data_exporters', 'qrm_privacy_exporter_kaydet');

/**
 * Dışa aktarıcıyı WordPress'in gizlilik araçlarına tanıtır.
 *
 * @param array $exporters Kayıtlı dışa aktarıcılar.
 * @return array
 */
function qrm_privacy_exporter_kaydet($exporters) {
    $exporters['qr-menu-suite-reviews'] = [
        'exporter_friendly_name' => __('QR Menü — yorumlar, ödüller ve form gönderimleri', 'qrms'),
        'callback'               => 'qrm_privacy_disa_aktar',
    ];

    return $exporters;
}

/**
 * Bir e-postaya bağlı tüm kayıtları WordPress'in beklediği biçimde döndürür.
 *
 * @param string $email E-posta adresi.
 * @param int    $page  Sayfa (1'den başlar).
 * @return array{data:array,done:bool}
 */
function qrm_privacy_disa_aktar($email, $page = 1) {
    $email = sanitize_email((string) $email);
    $items = [];

    if ('' === $email || (int) $page > 1) {
        return ['data' => $items, 'done' => true];
    }

    global $wpdb;

    $odul_tablo   = $wpdb->prefix . 'qrm_reward_codes';
    $yorum_tablo  = $wpdb->prefix . 'qrm_reviews';
    $gonderi_tablo = $wpdb->prefix . 'qrm_cf_submissions';

    /* ---- Ödül kodları ---- */
    $oduller = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$odul_tablo} WHERE email = %s", $email)
    );

    $yorum_idleri = [];

    foreach ((array) $oduller as $odul) {
        if (!empty($odul->source_review_id)) {
            $yorum_idleri[] = (int) $odul->source_review_id;
        }

        $items[] = [
            'group_id'    => 'qrm_rewards',
            'group_label' => __('Ödül kodları', 'qrms'),
            'item_id'     => 'qrm-reward-' . (int) $odul->id,
            'data'        => [
                ['name' => __('E-posta', 'qrms'), 'value' => (string) $odul->email],
                ['name' => __('Kod', 'qrms'), 'value' => (string) $odul->code],
                ['name' => __('İndirim', 'qrms'), 'value' => (string) $odul->discount_label],
                ['name' => __('Durum', 'qrms'), 'value' => (string) $odul->status],
                ['name' => __('Oluşturulma', 'qrms'), 'value' => (string) $odul->created_at],
                ['name' => __('IP adresi', 'qrms'), 'value' => (string) $odul->ip_address],
            ],
        ];
    }

    /* ---- Ödüle bağlı yorumlar ---- */
    if (!empty($yorum_idleri)) {
        $yer_tutucu = implode(',', array_fill(0, count($yorum_idleri), '%d'));

        $yorumlar = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$yorum_tablo} WHERE id IN ({$yer_tutucu})", $yorum_idleri)
        );

        foreach ((array) $yorumlar as $yorum) {
            $items[] = [
                'group_id'    => 'qrm_reviews',
                'group_label' => __('Yorumlar', 'qrms'),
                'item_id'     => 'qrm-review-' . (int) $yorum->id,
                'data'        => [
                    ['name' => __('Ad', 'qrms'), 'value' => (string) $yorum->customer_name],
                    ['name' => __('Telefon', 'qrms'), 'value' => (string) $yorum->customer_phone],
                    ['name' => __('Masa', 'qrms'), 'value' => (string) $yorum->table_no],
                    ['name' => __('Puan', 'qrms'), 'value' => (string) $yorum->rating],
                    ['name' => __('Yorum', 'qrms'), 'value' => (string) $yorum->comment],
                    ['name' => __('Tarih', 'qrms'), 'value' => (string) $yorum->created_at],
                    ['name' => __('Pazarlama izni', 'qrms'), 'value' => $yorum->consent_marketing ? __('Var', 'qrms') : __('Yok', 'qrms')],
                ],
            ];
        }
    }

    /* ---- Özel form gönderimleri (JSON içinde e-posta arar) ---- */
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $gonderi_tablo)) === $gonderi_tablo) {
        $like = '%' . $wpdb->esc_like($email) . '%';

        $gonderimler = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$gonderi_tablo} WHERE data LIKE %s", $like)
        );

        foreach ((array) $gonderimler as $gonderim) {
            $alanlar = json_decode((string) $gonderim->data, true);
            $satirlar = [
                ['name' => __('Tarih', 'qrms'), 'value' => (string) $gonderim->created_at],
                ['name' => __('IP adresi', 'qrms'), 'value' => (string) $gonderim->ip_address],
            ];

            foreach ((array) $alanlar as $anahtar => $deger) {
                $satirlar[] = [
                    'name'  => (string) $anahtar,
                    'value' => is_scalar($deger) ? (string) $deger : wp_json_encode($deger),
                ];
            }

            $items[] = [
                'group_id'    => 'qrm_form_submissions',
                'group_label' => __('Form gönderimleri', 'qrms'),
                'item_id'     => 'qrm-submission-' . (int) $gonderim->id,
                'data'        => $satirlar,
            ];
        }
    }

    return ['data' => $items, 'done' => true];
}

/* -------------------------------------------------------------------------
 * SİLME
 * ---------------------------------------------------------------------- */

add_filter('wp_privacy_personal_data_erasers', 'qrm_privacy_eraser_kaydet');

/**
 * Silme işleyicisini gizlilik araçlarına tanıtır.
 *
 * @param array $erasers Kayıtlı işleyiciler.
 * @return array
 */
function qrm_privacy_eraser_kaydet($erasers) {
    $erasers['qr-menu-suite-reviews'] = [
        'eraser_friendly_name' => __('QR Menü — yorumlar, ödüller ve form gönderimleri', 'qrms'),
        'callback'             => 'qrm_privacy_sil',
    ];

    return $erasers;
}

/**
 * Kişisel alanları temizler.
 *
 * Yorum METNİ ve puan korunur, kimliği belirleyen alanlar (ad, telefon, dahili
 * not) anonimleştirilir: WordPress'in yorum silme davranışıyla aynı yaklaşım —
 * işletmenin toplu puan istatistiği bozulmaz, kişi tanınmaz hâle gelir.
 * Form gönderiminin tamamı kişiye ait olduğu için satır silinir.
 *
 * @param string $email E-posta adresi.
 * @param int    $page  Sayfa.
 * @return array
 */
function qrm_privacy_sil($email, $page = 1) {
    $email = sanitize_email((string) $email);

    $sonuc = [
        'items_removed'  => false,
        'items_retained' => false,
        'messages'       => [],
        'done'           => true,
    ];

    if ('' === $email || (int) $page > 1) {
        return $sonuc;
    }

    global $wpdb;

    $odul_tablo    = $wpdb->prefix . 'qrm_reward_codes';
    $yorum_tablo   = $wpdb->prefix . 'qrm_reviews';
    $gonderi_tablo = $wpdb->prefix . 'qrm_cf_submissions';

    $oduller = $wpdb->get_results(
        $wpdb->prepare("SELECT id, source_review_id FROM {$odul_tablo} WHERE email = %s", $email)
    );

    foreach ((array) $oduller as $odul) {
        // Kod kaydı işletmenin muhasebe kaydıdır; kimlik alanları temizlenir.
        $wpdb->update(
            $odul_tablo,
            ['email' => null, 'ip_address' => ''],
            ['id' => (int) $odul->id],
            ['%s', '%s'],
            ['%d']
        );

        $sonuc['items_removed'] = true;

        $yorum_id = (int) $odul->source_review_id;
        if ($yorum_id > 0) {
            $wpdb->update(
                $yorum_tablo,
                [
                    'customer_name'  => QRM_PRIVACY_ANON_AD,
                    'customer_phone' => '',
                    'is_anonymous'   => 1,
                    'internal_note'  => null,
                ],
                ['id' => $yorum_id],
                ['%s', '%s', '%d', '%s'],
                ['%d']
            );

            $sonuc['items_removed'] = true;
        }
    }

    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $gonderi_tablo)) === $gonderi_tablo) {
        $like    = '%' . $wpdb->esc_like($email) . '%';
        $silinen = $wpdb->query(
            $wpdb->prepare("DELETE FROM {$gonderi_tablo} WHERE data LIKE %s", $like)
        );

        if ($silinen) {
            $sonuc['items_removed'] = true;
        }
    }

    if ($sonuc['items_removed']) {
        $sonuc['messages'][] = __('Yorum metni ve puan istatistik için korundu; ad, telefon ve dahili not anonimleştirildi.', 'qrms');

        if (function_exists('qrm_pro_flush_review_stats')) {
            qrm_pro_flush_review_stats();
        }
    }

    return $sonuc;
}

/* -------------------------------------------------------------------------
 * GİZLİLİK POLİTİKASI ÖNERİ METNİ
 * ---------------------------------------------------------------------- */

add_action('admin_init', 'qrm_privacy_politika_metni');

/**
 * WordPress'in gizlilik politikası taslağına modülün bölümünü ekler.
 *
 * @return void
 */
function qrm_privacy_politika_metni() {
    if (!function_exists('wp_add_privacy_policy_content')) {
        return;
    }

    $metin = '<p>' . __('Değerlendirme formu üzerinden gönderdiğiniz ad, telefon numarası, masa numarası, puan ve yorum metni sitemizin veritabanında saklanır. Ödül/indirim kodu talep ettiyseniz e-posta adresiniz ve IP adresiniz de kaydedilir.', 'qrms') . '</p>';
    $metin .= '<p>' . __('Bu veriler yalnızca hizmet kalitesinin değerlendirilmesi ve talep ettiğiniz ödülün iletilmesi için kullanılır; üçüncü taraflarla pazarlama amacıyla paylaşılmaz. Verilerinizin dışa aktarılmasını veya silinmesini talep edebilirsiniz.', 'qrms') . '</p>';

    wp_add_privacy_policy_content(__('QR Menü — Değerlendirme ve Ödüller', 'qrms'), wp_kses_post($metin));
}

/* -------------------------------------------------------------------------
 * SAKLAMA SÜRESİ
 *
 * Varsayılan 0 = süresiz saklama. İşletmenin yorum arşivini haber vermeden
 * silmek kabul edilemez; mekanizma sunulur, süreyi işletme seçer.
 * ---------------------------------------------------------------------- */

add_action('qrm_privacy_saklama_temizligi', 'qrm_privacy_saklama_uygula');

// Süre değiştiğinde görev kurulur ya da kaldırılır (0 yapıldığında öksüz cron kalmaz).
add_action('add_option_qrm_saklama_gun', 'qrm_privacy_saklama_cron_kur');
add_action('update_option_qrm_saklama_gun', 'qrm_privacy_saklama_cron_kur');

/**
 * Saklama süresi (gün). 0 ise temizlik çalışmaz.
 *
 * @return int
 */
function qrm_privacy_saklama_gun() {
    $gun = (int) get_option('qrm_saklama_gun', 0);

    /**
     * Kişisel veri saklama süresi (gün).
     *
     * @param int $gun 0 = süresiz.
     */
    return max(0, (int) apply_filters('qrm_privacy_saklama_gun', $gun));
}

/**
 * Günlük temizlik görevini kurar veya kaldırır.
 *
 * @return void
 */
function qrm_privacy_saklama_cron_kur() {
    $planli = wp_next_scheduled('qrm_privacy_saklama_temizligi');

    if (qrm_privacy_saklama_gun() > 0) {
        if (!$planli) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'qrm_privacy_saklama_temizligi');
        }
        return;
    }

    if ($planli) {
        wp_clear_scheduled_hook('qrm_privacy_saklama_temizligi');
    }
}

/**
 * Süresi dolmuş kayıtların kişisel alanlarını temizler.
 *
 * Yorumlar anonimleştirilir (puan/metin kalır), form gönderimleri ve kullanılmış
 * ödül kodlarının kimlik alanları silinir. Her çalıştırmada sınırlı sayıda satır
 * işlenir; büyük tablolar tek seferde kilitlenmez.
 *
 * @return void
 */
function qrm_privacy_saklama_uygula() {
    $gun = qrm_privacy_saklama_gun();

    if ($gun <= 0) {
        return;
    }

    global $wpdb;

    $sinir  = (int) apply_filters('qrm_privacy_saklama_toplu_sinir', 500);
    $esik   = gmdate('Y-m-d H:i:s', time() - ($gun * DAY_IN_SECONDS));
    $yorum  = $wpdb->prefix . 'qrm_reviews';
    $odul   = $wpdb->prefix . 'qrm_reward_codes';
    $gonderi = $wpdb->prefix . 'qrm_cf_submissions';

    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$yorum}
                SET customer_name = %s, customer_phone = '', is_anonymous = 1, internal_note = NULL
              WHERE created_at < %s
                AND ( customer_phone <> '' OR ( customer_name <> '' AND customer_name <> %s ) )
              LIMIT %d",
            QRM_PRIVACY_ANON_AD,
            $esik,
            QRM_PRIVACY_ANON_AD,
            $sinir
        )
    );

    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$odul} SET email = NULL, ip_address = ''
              WHERE created_at < %s AND ( email IS NOT NULL OR ip_address <> '' )
              LIMIT %d",
            $esik,
            $sinir
        )
    );

    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $gonderi)) === $gonderi) {
        $wpdb->query(
            $wpdb->prepare("DELETE FROM {$gonderi} WHERE created_at < %s LIMIT %d", $esik, $sinir)
        );
    }

    if (function_exists('qrm_pro_flush_review_stats')) {
        qrm_pro_flush_review_stats();
    }
}
