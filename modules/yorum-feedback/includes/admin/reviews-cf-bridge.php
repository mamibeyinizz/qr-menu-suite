<?php
if (!defined('ABSPATH')) exit;

// TÜM YORUMLAR KÖPRÜSÜ: ÖZEL FORM GÖNDERİMLERİ (v4.4.0)
//
// Restoran "Ana Yorum Formu" ([qr_menu_reviews]) yerine (ya da onun yanında)
// Puanlama Kriterleri widget'lı kendi özel formunu (ör. "Bizi Değerlendirin")
// kullanıyorsa, o gönderimler wp_qrm_reviews'e HİÇ yazılmaz — kendi tablosunda
// (JSON içinde rating_1..5) kalır (bkz. forms/db.php, forms/functions.php).
// Bu dosya, öyle bir gönderimi Tüm Yorumlar listesinin native satır şekline
// ÇEVİRİR ki dashboard.php'nin render döngüsü hiç değişmeden iki kaynağı da
// aynı tabloda, aynı olumlu/olumsuz ayrımıyla basabilsin.
//
// Kimlik çakışması: normalize edilmiş satırın id'si NEGATİFTİR (-submission_id).
// Native id'ler AUTO_INCREMENT olduğu için hep pozitiftir, yani iki kaynak asla
// çakışmaz VE `$r->id < 0` tek başına "bu satır özel formdan geldi" testidir.
// Bu ayrım önemlidir: iş akışı AJAX'ı (ajax/review-workflow.php) id'yi
// absint() ile pozitife çevirip doğrudan wp_qrm_reviews'te günceller — negatif
// bir id'nin oraya HİÇ ulaşmaması gerekir, bu yüzden aşağıdaki satırlar için
// iş akışı kontrolleri ve onayla/yayından kaldır aksiyonları hiç basılmaz
// (bkz. dashboard.php'deki `$r->id < 0` dalları).

/**
 * rating_group içeren, dolayısıyla Tüm Yorumlar'a "yorum" olarak katılan
 * özel formlar. Formun güncel durumu (aktif/taslak/arşiv) burada elenmez:
 * geçmişte toplanmış gerçek geri bildirim, form sonradan arşivlense de
 * geçerliliğini korur.
 *
 * @return array form_id => form nesnesi (qrm_cf_get_forms() satırı)
 */
function qrm_pro_cf_review_forms() {
    static $cache = null;
    if ($cache !== null) return $cache;

    $cache = [];
    foreach (qrm_cf_get_forms() as $form) {
        foreach (qrm_cf_get_fields($form->id) as $field) {
            if ($field->field_type === 'rating_group') {
                $cache[(int) $form->id] = $form;
                break;
            }
        }
    }

    return $cache;
}

/**
 * Bir özel form gönderimini wp_qrm_reviews satırıyla AYNI özellik adlarını
 * taşıyan bir nesneye çevirir.
 *
 * İsim tahmini: özel formlarda "bu alan isim" diye ayrı bir işaret yok, bu
 * yüzden formun İLK EN FAZLA İKİ 'text' alanı (ör. Adınız + Soyadınız) ad
 * olarak alınır — en yaygın form düzeni budur, kesin bir kural değildir.
 * "Yorum" sütunu ise TAHMİN ETMEZ: widget olmayan tüm alanların "Etiket:
 * değer" dökümüdür, hiçbir veri kaybolmaz.
 *
 * @param object $submission qrm_cf_get_all_submissions() satırı
 * @param object $form       qrm_cf_get_form() satırı
 * @param array  $fields     qrm_cf_get_fields($form->id) çıktısı
 * @param array  $masa_labels table_id => masa adı (dashboard.php'de zaten hesaplanır)
 * @return object wp_qrm_reviews şeklinde stdClass
 */
function qrm_pro_cf_submission_to_review_row($submission, $form, array $fields, array $masa_labels = []) {
    $data = qrm_cf_submission_data($submission);
    $rg   = qrm_cf_rating_group_values($data);

    $ratings = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    $sum = 0;
    $n   = 0;
    foreach ($rg as $i => $v) {
        $ratings[$i] = (int) $v['value'];
        $sum += (int) $v['value'];
        $n++;
    }
    $avg = $n > 0 ? $sum / $n : 0.0;

    $name_parts = [];
    $name_keys  = [];
    $text_used  = 0;
    foreach ($fields as $field) {
        if ($field->field_type !== 'text' || $text_used >= 2) continue;
        $text_used++;
        $name_keys[] = $field->field_key;
        $val = isset($data[$field->field_key]) ? trim((string) $data[$field->field_key]) : '';
        if ($val !== '') $name_parts[] = $val;
    }

    // "Yorum" sütunu, Müşteri sütununda zaten gösterilen ad alanlarını
    // TEKRARLAMAZ — geri kalan her şeyin (masa, telefon, e-posta, açık uçlu
    // metin vb.) kayıpsız dökümüdür.
    $widget_types = qrm_cf_field_types(true);
    $lines        = [];
    foreach ($fields as $field) {
        if (!empty($widget_types[$field->field_type]['is_widget'])) continue;
        if (in_array($field->field_key, $name_keys, true)) continue;
        $val = isset($data[$field->field_key]) ? qrm_cf_format_value($field, $data[$field->field_key]) : '';
        if ($val === '') continue;
        $lines[] = $field->label . ': ' . $val;
    }

    $table_id = isset($data['_qrm_table_id']) ? (int) $data['_qrm_table_id'] : 0;
    $table_no = ($table_id > 0 && isset($masa_labels[$table_id])) ? (string) $masa_labels[$table_id] : '';

    $row                    = new stdClass();
    $row->id                = -1 * (int) $submission->id;
    $row->created_at        = $submission->created_at;
    $row->customer_name     = implode(' ', $name_parts);
    $row->customer_phone    = '';
    $row->table_no          = $table_no;
    $row->table_id          = $table_id;
    $row->is_anonymous      = 0;
    $row->rating            = $avg;
    $row->rating_1          = $ratings[1];
    $row->rating_2          = $ratings[2];
    $row->rating_3          = $ratings[3];
    $row->rating_4          = $ratings[4];
    $row->rating_5          = $ratings[5];
    $row->comment           = implode("\n", $lines);
    // Özel formda onay/moderasyon kavramı yok: toplanan her gönderim zaten
    // "yayında" muamelesi görür (native tarafta "Bekleyen"e hiç düşmez).
    $row->status            = 1;
    $row->is_manual         = 0;
    $row->form_source       = 'cf';
    $row->workflow_status   = '';
    $row->assigned_user_id  = 0;
    $row->internal_note     = '';
    $row->resolved_at       = '';
    $row->_cf_form_title    = $form->title;
    $row->_cf_submission_id = (int) $submission->id;

    return $row;
}

/**
 * Filtreye uyan tüm özel form kaynaklı "yorum" satırlarını döner.
 *
 * Sayfalama YOKTUR: dashboard.php bunları native satırlarla birleştirip
 * TEK bir diziyi tarihe göre sıraladıktan sonra kendi sayfalamasını yapar
 * (bkz. qrm_pro_admin_dashboard()). Restoran başına veri hacmi düşünüldüğünde
 * (onlarca/yüzlerce gönderim) bunu PHP'de yapmak, iki farklı şemayı (sabit
 * sütunlu tablo + JSON blob) tek SQL sorgusunda birleştirmekten çok daha
 * basit ve güvenlidir.
 *
 * @param string $sekme  '' | 'olumlu' | 'olumsuz'
 * @param float  $esik   Olumlu/olumsuz eşiği
 * @param array  $extra  liste_bas/bas_dt, liste_bit/bit_excl, search, table_id
 * @return object[]
 */
function qrm_pro_cf_fetch_review_rows($sekme, $esik, array $extra = []) {
    $forms = qrm_pro_cf_review_forms();
    if (!$forms) return [];

    $masa_labels = [];
    if (class_exists('QMO_Masalar') && method_exists('QMO_Masalar', 'hepsi')) {
        foreach ((array) QMO_Masalar::hepsi() as $masa) {
            if (!empty($masa->id)) {
                $masa_labels[(int) $masa->id] = (string) $masa->table_name;
            }
        }
    }

    $search = !empty($extra['search']) ? (string) $extra['search'] : '';
    $out    = [];

    foreach ($forms as $form) {
        $fields = qrm_cf_get_fields($form->id);

        foreach (qrm_cf_get_all_submissions($form->id) as $submission) {
            $row = qrm_pro_cf_submission_to_review_row($submission, $form, $fields, $masa_labels);

            if ($sekme === 'olumlu' && $row->rating < $esik) continue;
            if ($sekme === 'olumsuz' && $row->rating >= $esik) continue;

            if (!empty($extra['table_id']) && (int) $row->table_id !== (int) $extra['table_id']) continue;

            if (!empty($extra['bas_dt']) && !empty($extra['bit_excl'])) {
                if ($row->created_at < $extra['bas_dt'] || $row->created_at >= $extra['bit_excl']) continue;
            }

            if ($search !== '') {
                $hay = $row->customer_name . ' ' . $row->comment . ' ' . $row->table_no . ' ' . $row->_cf_form_title;
                if (stripos($hay, $search) === false) continue;
            }

            $out[] = $row;
        }
    }

    return $out;
}

/**
 * Tüm Yorumlar sekme sayaçlarına (Tümü / Olumlu / Olumsuz) eklenecek özel
 * form katkısı. Tek geçişte hem toplamı hem olumlu/olumsuz kırılımını verir,
 * qrm_pro_cf_fetch_review_rows()'u üç kez (her sekme için ayrı ayrı) değil
 * bir kez çağırır.
 *
 * @param float $esik
 * @param array $extra
 * @return array{total:int,olumlu:int,olumsuz:int}
 */
function qrm_pro_cf_review_sentiment_counts($esik, array $extra = []) {
    $rows   = qrm_pro_cf_fetch_review_rows('', $esik, $extra);
    $total  = count($rows);
    $olumlu = 0;
    foreach ($rows as $row) {
        if ($row->rating >= $esik) $olumlu++;
    }

    return ['total' => $total, 'olumlu' => $olumlu, 'olumsuz' => $total - $olumlu];
}

/**
 * qrm_pro_review_stats() çıktısına özel form katkısını ekleyip Tüm Yorumlar
 * sayfasının sekme/alt-filtre sayaçlarında kullanılacak GÖRÜNÜM kopyasını
 * üretir. Asıl $stats (moderasyon, Google eşiği vb. başka yerlerde kullanılan
 * gerçek native sayaçlar) DEĞİŞTİRİLMEZ — yalnızca ekranda gösterilecek toplam
 * büyütülür. "Bekleyen" hiç etkilenmez: özel formda onay kavramı yoktur.
 *
 * @param array $stats   qrm_pro_review_stats() çıktısı.
 * @param array $cf_sent qrm_pro_cf_review_sentiment_counts() çıktısı.
 * @return array Görünüm için $stats kopyası.
 */
function qrm_pro_cf_merge_stats_for_display(array $stats, array $cf_sent) {
    $stats['total']    += $cf_sent['total'];
    $stats['approved'] += $cf_sent['total'];

    if (isset($stats['sentiment']['olumlu'])) {
        $stats['sentiment']['olumlu']['total']    += $cf_sent['olumlu'];
        $stats['sentiment']['olumlu']['approved'] += $cf_sent['olumlu'];
    }
    if (isset($stats['sentiment']['olumsuz'])) {
        $stats['sentiment']['olumsuz']['total']    += $cf_sent['olumsuz'];
        $stats['sentiment']['olumsuz']['approved'] += $cf_sent['olumsuz'];
    }

    return $stats;
}

/**
 * Tüm Yorumlar'daki özel form satırının "Sil" aksiyonu.
 *
 * Native satırların approve/unapprove/delete'i qrm_pro_admin_handle_review_actions()
 * içinde `action` + `id` (pozitif, wp_qrm_reviews.id) taşır. Özel form satırları
 * TAMAMEN AYRI bir parametre çiftiyle (`cf_action` + `cf_id`) çalışır ki iki
 * aksiyon asla aynı sorgu string'inde karışmasın — approve/unapprove burada
 * hiç yoktur çünkü özel formda onay kavramı yoktur.
 *
 * @return string Kullanıcıya gösterilecek mesaj (yoksa boş string).
 */
function qrm_pro_cf_handle_review_delete() {
    if (!isset($_GET['cf_action'], $_GET['cf_id'])) return '';

    $action = sanitize_key(wp_unslash($_GET['cf_action']));
    $id     = intval($_GET['cf_id']);

    if ($action !== 'delete' || $id <= 0) return '';
    if (!current_user_can('manage_options')) return '';

    check_admin_referer('qrm_cf_review_action_' . $id);

    qrm_cf_delete_submission($id);

    return __('Yorum silindi.', 'qrms');
}
