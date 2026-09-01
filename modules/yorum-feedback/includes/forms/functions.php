<?php
if (!defined('ABSPATH')) exit;

// ÖZEL FORM BUILDER MODÜLÜ (v4.2.0): ALAN TİPİ KAYIT DEFTERİ, CRUD, DOĞRULAMA
//
// Tasarım kuralı: yeni bir alan tipi eklemek için üç noktaya dokunmak yeter —
//   1) qrm_cf_field_types()  (kayıt defteri: etiket, ikon, seçenek gerektiriyor mu)
//   2) qrm_cf_render_field() (render switch/case'i, includes/forms/render.php)
//   3) qrm_cf_validate_value() (doğrulama switch/case'i, bu dosya)
// Başka hiçbir yerde alan tipi listesi sabitlenmez.

/**
 * Desteklenen alan tipleri.
 *
 * label      : Admin paletinde görünen isim
 * icon       : Dashicon sınıfı
 * has_options: Seçenek listesi (select/radio/checkbox) gerektirir mi
 * hint       : Palette gösterilen kısa açıklama
 */
function qrm_cf_field_types() {
    return [
        'text'     => ['label' => 'Kısa Metin',      'icon' => 'dashicons-editor-textcolor', 'has_options' => false, 'hint' => 'Ad, konu, başlık'],
        'textarea' => ['label' => 'Uzun Metin',      'icon' => 'dashicons-editor-paragraph', 'has_options' => false, 'hint' => 'Açıklama, mesaj'],
        'email'    => ['label' => 'E-posta',         'icon' => 'dashicons-email-alt',        'has_options' => false, 'hint' => 'E-posta adresi'],
        'tel'      => ['label' => 'Telefon',         'icon' => 'dashicons-phone',            'has_options' => false, 'hint' => 'Cep / sabit hat'],
        'number'   => ['label' => 'Sayı',            'icon' => 'dashicons-calculator',       'has_options' => false, 'hint' => 'Kişi sayısı, adet'],
        'select'   => ['label' => 'Açılır Liste',    'icon' => 'dashicons-arrow-down-alt2',  'has_options' => true,  'hint' => 'Tek seçim, açılır'],
        'radio'    => ['label' => 'Tek Seçim',       'icon' => 'dashicons-marker',           'has_options' => true,  'hint' => 'Seçeneklerden biri'],
        'checkbox' => ['label' => 'Çoklu Seçim',     'icon' => 'dashicons-yes',              'has_options' => true,  'hint' => 'Birden fazla seçilebilir'],
        'rating'   => ['label' => 'Yıldız Puanlama', 'icon' => 'dashicons-star-filled',      'has_options' => false, 'hint' => '1-5 yıldız'],
        'date'     => ['label' => 'Tarih',           'icon' => 'dashicons-calendar-alt',     'has_options' => false, 'hint' => 'Gün seçimi'],
    ];
}

function qrm_cf_is_valid_field_type($type) {
    return array_key_exists($type, qrm_cf_field_types());
}

function qrm_cf_type_has_options($type) {
    $types = qrm_cf_field_types();
    return !empty($types[$type]['has_options']);
}

function qrm_cf_type_label($type) {
    $types = qrm_cf_field_types();
    return isset($types[$type]) ? $types[$type]['label'] : $type;
}

/**
 * Alan sütun genişliği: yalnızca 'full' (tekli) veya 'half' (ikili).
 *
 * Elementor Form widget Column Width bu kısa kodlara uygulanmaz;
 * genişlik bu değerden CSS class `.half` ile üretilir.
 *
 * @param mixed $value
 * @return string 'full'|'half'
 */
function qrm_pro_sanitize_column_width($value) {
    return ($value === 'half') ? 'half' : 'full';
}

/**
 * Kaydedilmiş sütun genişliği. Sütun henüz yoksa (eski kurulum) önceki
 * otomatik davranışa düşer — migration çalışınca DB'deki değer geçerli olur.
 *
 * @param object|array $field
 * @param string       $context 'review' (yorum/iletişim) veya 'custom'
 * @return string 'full'|'half'
 */
function qrm_pro_field_column_width($field, $context = 'custom') {
    $row = is_object($field) ? get_object_vars($field) : (array) $field;

    if (isset($row['column_width']) && $row['column_width'] !== '' && $row['column_width'] !== null) {
        return qrm_pro_sanitize_column_width($row['column_width']);
    }

    if ($context === 'review') {
        $key = isset($row['field_key']) ? $row['field_key'] : '';
        return in_array($key, ['customer_name', 'customer_phone', 'table_no'], true) ? 'half' : 'full';
    }

    $type = isset($row['field_type']) ? $row['field_type'] : (isset($row['type']) ? $row['type'] : '');
    return in_array($type, ['text', 'email', 'tel', 'number', 'date'], true) ? 'half' : 'full';
}

// --- FORM AYARLARI (settings sütunundaki JSON) ---

function qrm_cf_default_form_settings() {
    return [
        'submit_text'     => 'Gönder',
        'success_message' => 'Formunuz bize ulaştı, teşekkür ederiz.',
        'show_title'      => 1,
        // Bildirim
        'notify_enabled'  => 0,
        'notify_email'    => '',
        // Görünüm (yorum formundaki stil değişkenleriyle aynı mantık)
        'theme_style'     => 'light',   // light | dark | transparent
        'btn_color'       => '#10b981',
        'btn_text_color'  => '#ffffff',
        'border_radius'   => 10,
    ];
}

/** Form satırındaki JSON ayarları varsayılanlarla birleştirip döndürür. */
function qrm_cf_get_form_settings($form) {
    $raw = is_object($form) ? $form->settings : (is_array($form) ? (isset($form['settings']) ? $form['settings'] : '') : $form);
    $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
    if (!is_array($decoded)) $decoded = [];
    return array_merge(qrm_cf_default_form_settings(), $decoded);
}

/** Admin POST'undan gelen ham ayarları temizler. */
function qrm_cf_sanitize_form_settings($raw) {
    $defaults = qrm_cf_default_form_settings();
    $out = $defaults;
    if (!is_array($raw)) return $out;

    if (isset($raw['submit_text']))     $out['submit_text']     = sanitize_text_field($raw['submit_text']);
    if (isset($raw['success_message'])) $out['success_message'] = sanitize_textarea_field($raw['success_message']);
    $out['show_title']     = !empty($raw['show_title']) ? 1 : 0;
    $out['notify_enabled'] = !empty($raw['notify_enabled']) ? 1 : 0;

    if (isset($raw['notify_email'])) {
        $email = sanitize_email($raw['notify_email']);
        $out['notify_email'] = is_email($email) ? $email : '';
    }
    if ($out['notify_enabled'] && $out['notify_email'] === '') {
        $out['notify_email'] = get_option('admin_email');
    }

    if (isset($raw['theme_style']) && in_array($raw['theme_style'], ['light', 'dark', 'transparent'], true)) {
        $out['theme_style'] = $raw['theme_style'];
    }
    foreach (['btn_color', 'btn_text_color'] as $key) {
        if (isset($raw[$key])) {
            $color = sanitize_hex_color($raw[$key]);
            if ($color) $out[$key] = $color;
        }
    }
    if (isset($raw['border_radius'])) {
        $out['border_radius'] = max(0, min(40, intval($raw['border_radius'])));
    }
    if ($out['submit_text'] === '')     $out['submit_text']     = $defaults['submit_text'];
    if ($out['success_message'] === '') $out['success_message'] = $defaults['success_message'];

    return $out;
}

// --- SLUG / SHORTCODE ---

/** Başlıktan shortcode anahtarı üretir (TR karakterler dahil). */
function qrm_cf_slugify($text) {
    $text = (string) $text;
    $tr = ['ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'İ' => 'i',
           'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u'];
    $text = strtr($text, $tr);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string) $text, '-');
    if ($text === '') $text = 'form';
    return substr($text, 0, 50);
}

/** Aynı anahtardan ikinci bir form olamaz; çakışırsa -2, -3 ... eklenir. */
function qrm_cf_unique_form_key($key, $exclude_id = 0) {
    global $wpdb;
    $table = qrm_cf_forms_table();
    $base  = qrm_cf_slugify($key);
    $try   = $base;
    $i     = 2;

    while (true) {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE form_key = %s AND id != %d LIMIT 1",
            $try,
            intval($exclude_id)
        ));
        if (!$exists) return $try;
        $suffix = '-' . $i;
        $try = substr($base, 0, 50 - strlen($suffix)) . $suffix;
        $i++;
    }
}

/**
 * Formlar sayfasının bağlantıları TEK yerden üretilir — admin slug'ı ile bağlantılar
 * arasında kayma olmasın diye (v4.2.0'daki 403 hatasının kaynağı buydu).
 * Slug'ın kendisi de tek yerde durur: qrm_pro_admin_pages() kayıt defteri.
 *
 *   qrm_cf_admin_url()                                   -> Formlarım sekmesi
 *   qrm_cf_admin_url(['tab' => 'submissions'])           -> Gönderiler sekmesi
 *   qrm_cf_admin_url(['view' => 'edit', 'form_id' => 3]) -> Düzenleyici görünümü
 */
function qrm_cf_admin_url($args = []) {
    return qrm_pro_admin_url('qrms-yf-formlar', $args);
}

function qrm_cf_form_shortcode($form) {
    $key = is_object($form) ? $form->form_key : (string) $form;
    return '[qr_menu_form key="' . $key . '"]';
}

function qrm_cf_status_label($status) {
    $map = ['active' => 'Aktif', 'draft' => 'Taslak', 'archived' => 'Arşiv'];
    return isset($map[$status]) ? $map[$status] : $status;
}

function qrm_cf_submission_status_label($status) {
    $map = ['new' => 'Yeni', 'read' => 'Okundu', 'archived' => 'Arşiv'];
    return isset($map[$status]) ? $map[$status] : $status;
}

// --- FORM CRUD ---

function qrm_cf_get_forms($args = []) {
    global $wpdb;
    $table = qrm_cf_forms_table();
    $status = isset($args['status']) ? sanitize_key($args['status']) : '';

    if ($status !== '') {
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status = %s ORDER BY created_at DESC",
            $status
        ));
    }
    return $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
}

function qrm_cf_get_form($id) {
    global $wpdb;
    $table = qrm_cf_forms_table();
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($id)));
}

function qrm_cf_get_form_by_key($key) {
    global $wpdb;
    $table = qrm_cf_forms_table();
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE form_key = %s", (string) $key));
}

/**
 * Form kaydeder (id verilmişse günceller, verilmemişse oluşturur).
 *
 * @param array $data id, title, description, form_key, status, settings (dizi)
 * @return int|WP_Error Form id'si
 */
function qrm_cf_save_form($data) {
    global $wpdb;
    $table = qrm_cf_forms_table();

    $id    = isset($data['id']) ? intval($data['id']) : 0;
    $title = isset($data['title']) ? sanitize_text_field($data['title']) : '';
    if (trim($title) === '') {
        return new WP_Error('qrm_cf_no_title', 'Form başlığı boş olamaz.');
    }

    $status = isset($data['status']) ? sanitize_key($data['status']) : 'draft';
    if (!in_array($status, ['active', 'draft', 'archived'], true)) $status = 'draft';

    $key_source = (isset($data['form_key']) && trim((string) $data['form_key']) !== '') ? $data['form_key'] : $title;
    $form_key   = qrm_cf_unique_form_key($key_source, $id);

    $row = [
        'form_key'    => $form_key,
        'title'       => $title,
        'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
        'status'      => $status,
        'settings'    => wp_json_encode(qrm_cf_sanitize_form_settings(isset($data['settings']) ? $data['settings'] : [])),
        'updated_at'  => current_time('mysql'),
    ];

    if ($id > 0) {
        $wpdb->update($table, $row, ['id' => $id]);
        return $id;
    }

    $row['created_at'] = current_time('mysql');
    $wpdb->insert($table, $row);
    $new_id = (int) $wpdb->insert_id;
    if (!$new_id) {
        return new WP_Error('qrm_cf_insert_failed', 'Form kaydedilemedi.');
    }
    return $new_id;
}

/** Formu, alanlarını ve gönderimlerini siler. */
function qrm_cf_delete_form($id) {
    global $wpdb;
    $id = intval($id);
    if ($id <= 0) return false;

    $wpdb->delete(qrm_cf_fields_table(), ['form_id' => $id], ['%d']);
    $wpdb->delete(qrm_cf_submissions_table(), ['form_id' => $id], ['%d']);

    qrm_cf_flush_unread_total();
    return (bool) $wpdb->delete(qrm_cf_forms_table(), ['id' => $id], ['%d']);
}

// --- ALAN CRUD ---

function qrm_cf_get_fields($form_id) {
    global $wpdb;
    $table = qrm_cf_fields_table();
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE form_id = %d ORDER BY sort_order ASC, id ASC",
        intval($form_id)
    ));
}

/** Alan satırındaki JSON seçenek listesini diziye çevirir. */
function qrm_cf_field_options($field) {
    $raw = is_object($field) ? $field->options : $field;
    $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
    if (!is_array($decoded)) return [];
    $out = [];
    foreach ($decoded as $opt) {
        $opt = trim((string) $opt);
        if ($opt !== '') $out[] = $opt;
    }
    return $out;
}

/** Ham seçenek metnini (satır başına bir seçenek) diziye çevirir. */
function qrm_cf_parse_options($raw) {
    if (is_array($raw)) {
        $lines = $raw;
    } else {
        $lines = preg_split('/\r\n|\r|\n/', (string) $raw);
    }
    $out = [];
    foreach ($lines as $line) {
        $line = sanitize_text_field($line);
        if (trim($line) !== '') $out[] = $line;
    }
    return $out;
}

/**
 * Formun alanlarını tamamen değiştirir (sil + yeniden yaz).
 * Alan seti düzenleyicide bir bütün olarak gönderildiği için diff yerine
 * replace tercih edildi; gönderim verisi field_key ile eşleştiği için
 * satır id'lerinin değişmesi kayıtları etkilemez.
 *
 * @param int   $form_id
 * @param array $fields  [['field_key'=>..,'label'=>..,'field_type'=>..,'options'=>[],'is_required'=>0|1,'column_width'=>'full'|'half'], ...]
 * @return int Kaydedilen alan sayısı
 */
function qrm_cf_replace_fields($form_id, $fields) {
    global $wpdb;
    $form_id = intval($form_id);
    $table   = qrm_cf_fields_table();
    if ($form_id <= 0) return 0;

    $wpdb->delete($table, ['form_id' => $form_id], ['%d']);

    $order = 0;
    $used_keys = [];
    $yazilacak = [];
    $saved = 0;

    foreach ((array) $fields as $field) {
        $type = isset($field['field_type']) ? sanitize_key($field['field_type']) : '';
        if (!qrm_cf_is_valid_field_type($type)) continue;

        $label = isset($field['label']) ? sanitize_text_field($field['label']) : '';
        if (trim($label) === '') $label = qrm_cf_type_label($type);

        $key = isset($field['field_key']) ? sanitize_key($field['field_key']) : '';
        if ($key === '') $key = qrm_cf_slugify($label);
        $key = str_replace('-', '_', $key);
        if ($key === '') $key = 'alan';

        // Aynı form içinde alan anahtarı tekilleştirilir (gönderim JSON'unda çakışmasın).
        $base = substr($key, 0, 50);
        $key  = $base;
        $i    = 2;
        while (in_array($key, $used_keys, true)) {
            $suffix = '_' . $i;
            $key = substr($base, 0, 50 - strlen($suffix)) . $suffix;
            $i++;
        }
        $used_keys[] = $key;

        $options = qrm_cf_type_has_options($type) ? qrm_cf_parse_options(isset($field['options']) ? $field['options'] : []) : [];

        // Satır satır INSERT yerine biriktir; tek sorguda yazılır.
        $yazilacak[] = [
            $form_id,
            $key,
            $label,
            $type,
            $options ? wp_json_encode($options) : '',
            !empty($field['is_required']) ? 1 : 0,
            $order,
            qrm_pro_sanitize_column_width(isset($field['column_width']) ? $field['column_width'] : 'full'),
        ];

        $order++;
        $saved++;
    }

    if ($yazilacak) {
        // Alan sayısı kullanıcı tarafından belirlenir; tek dev sorgu
        // max_allowed_packet'e takılmasın diye parçalanır.
        foreach (array_chunk($yazilacak, 200) as $parca) {
            $degerler = [];
            $params   = [];

            foreach ($parca as $satir) {
                $degerler[] = '(%d, %s, %s, %s, %s, %d, %d, %s)';
                foreach ($satir as $deger) $params[] = $deger;
            }

            $wpdb->query($wpdb->prepare(
                "INSERT INTO $table (form_id, field_key, label, field_type, options, is_required, sort_order, column_width)
                 VALUES " . implode(', ', $degerler),
                $params
            ));
        }
    }

    return $saved;
}

// --- GÖNDERİM DOĞRULAMA (saf fonksiyon: birim testlerinden doğrudan çağrılabilir) ---

/**
 * Tek bir alanın değerini tipine göre doğrular ve normalize eder.
 *
 * @return array ['ok' => bool, 'value' => mixed, 'error' => string]
 */
function qrm_cf_validate_value($field, $raw) {
    $type    = is_object($field) ? $field->field_type : $field['field_type'];
    $label   = is_object($field) ? $field->label : $field['label'];
    $options = qrm_cf_field_options($field);

    switch ($type) {
        case 'textarea':
            return ['ok' => true, 'value' => sanitize_textarea_field((string) $raw), 'error' => ''];

        case 'email':
            $email = sanitize_email((string) $raw);
            if ($email !== '' && !is_email($email)) {
                return ['ok' => false, 'value' => '', 'error' => sprintf('"%s" alanına geçerli bir e-posta adresi girin.', $label)];
            }
            return ['ok' => true, 'value' => $email, 'error' => ''];

        case 'tel':
            $digits = preg_replace('/[^0-9]/', '', (string) $raw);
            if ($digits === '') return ['ok' => true, 'value' => '', 'error' => ''];
            // TR cep numarası ise mevcut normalizasyon kullanılır, değilse
            // (sabit hat / yurt dışı) makul uzunluk kontrolüyle yetinilir.
            $tr = qrm_pro_normalize_tr_phone($raw);
            if ($tr !== false && $tr !== '') {
                return ['ok' => true, 'value' => $tr, 'error' => ''];
            }
            if (strlen($digits) < 7 || strlen($digits) > 15) {
                return ['ok' => false, 'value' => '', 'error' => sprintf('"%s" alanına geçerli bir telefon numarası girin.', $label)];
            }
            return ['ok' => true, 'value' => $digits, 'error' => ''];

        case 'number':
            $val = trim((string) $raw);
            if ($val === '') return ['ok' => true, 'value' => '', 'error' => ''];
            if (!is_numeric(str_replace(',', '.', $val))) {
                return ['ok' => false, 'value' => '', 'error' => sprintf('"%s" alanına yalnızca sayı girin.', $label)];
            }
            return ['ok' => true, 'value' => (string) (float) str_replace(',', '.', $val), 'error' => ''];

        case 'rating':
            $val = intval($raw);
            if ($val === 0) return ['ok' => true, 'value' => '', 'error' => ''];
            if ($val < 1 || $val > 5) {
                return ['ok' => false, 'value' => '', 'error' => sprintf('"%s" alanı için 1-5 arası bir puan seçin.', $label)];
            }
            return ['ok' => true, 'value' => (string) $val, 'error' => ''];

        case 'date':
            $val = trim((string) $raw);
            if ($val === '') return ['ok' => true, 'value' => '', 'error' => ''];
            $parts = explode('-', $val);
            if (count($parts) !== 3 || !checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
                return ['ok' => false, 'value' => '', 'error' => sprintf('"%s" alanına geçerli bir tarih seçin.', $label)];
            }
            return ['ok' => true, 'value' => $val, 'error' => ''];

        case 'select':
        case 'radio':
            $val = sanitize_text_field((string) $raw);
            if ($val === '') return ['ok' => true, 'value' => '', 'error' => ''];
            if (!in_array($val, $options, true)) {
                return ['ok' => false, 'value' => '', 'error' => sprintf('"%s" alanı için geçersiz bir seçenek gönderildi.', $label)];
            }
            return ['ok' => true, 'value' => $val, 'error' => ''];

        case 'checkbox':
            $vals = is_array($raw) ? $raw : ($raw === '' || $raw === null ? [] : [$raw]);
            $clean = [];
            foreach ($vals as $v) {
                $v = sanitize_text_field((string) $v);
                if ($v === '') continue;
                if (!in_array($v, $options, true)) {
                    return ['ok' => false, 'value' => [], 'error' => sprintf('"%s" alanı için geçersiz bir seçenek gönderildi.', $label)];
                }
                $clean[] = $v;
            }
            return ['ok' => true, 'value' => $clean, 'error' => ''];

        case 'text':
        default:
            return ['ok' => true, 'value' => sanitize_text_field((string) $raw), 'error' => ''];
    }
}

function qrm_cf_value_is_empty($value) {
    if (is_array($value)) return count($value) === 0;
    return trim((string) $value) === '';
}

/**
 * Tüm gönderimi alan tanımlarına göre doğrular.
 * Sunucu tarafı doğrulama tarayıcıdaki "required" özniteliğine güvenmez.
 *
 * @param array $fields qrm_cf_get_fields() çıktısı
 * @param array $post   Ham gönderim dizisi ($_POST)
 * @return array ['ok' => bool, 'errors' => [string], 'data' => [field_key => value]]
 */
function qrm_cf_validate_submission($fields, $post) {
    $data   = [];
    $errors = [];

    foreach ((array) $fields as $field) {
        $key      = is_object($field) ? $field->field_key : $field['field_key'];
        $label    = is_object($field) ? $field->label : $field['label'];
        $required = is_object($field) ? !empty($field->is_required) : !empty($field['is_required']);
        $raw      = array_key_exists($key, (array) $post) ? $post[$key] : '';

        $result = qrm_cf_validate_value($field, $raw);
        if (!$result['ok']) {
            $errors[] = $result['error'];
            continue;
        }
        if ($required && qrm_cf_value_is_empty($result['value'])) {
            $errors[] = sprintf('"%s" alanı zorunludur.', $label);
            continue;
        }
        $data[$key] = $result['value'];
    }

    return ['ok' => count($errors) === 0, 'errors' => $errors, 'data' => $data];
}

// --- GÖNDERİM CRUD ---

function qrm_cf_insert_submission($form_id, $data, $ip = '') {
    global $wpdb;
    $wpdb->insert(qrm_cf_submissions_table(), [
        'form_id'    => intval($form_id),
        'data'       => wp_json_encode($data),
        'status'     => 'new',
        'ip_address' => substr((string) $ip, 0, 100),
        'created_at' => current_time('mysql'),
    ], ['%d', '%s', '%s', '%s', '%s']);

    qrm_cf_flush_unread_total();

    return (int) $wpdb->insert_id;
}

function qrm_cf_get_submissions($form_id, $args = []) {
    global $wpdb;
    $table    = qrm_cf_submissions_table();
    $per_page = isset($args['per_page']) ? max(1, intval($args['per_page'])) : 25;
    $paged    = isset($args['paged']) ? max(1, intval($args['paged'])) : 1;
    $offset   = ($paged - 1) * $per_page;
    $status   = isset($args['status']) ? sanitize_key($args['status']) : '';

    if (in_array($status, ['new', 'read', 'archived'], true)) {
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE form_id = %d AND status = %s ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
            intval($form_id), $status, $per_page, $offset
        ));
    }
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE form_id = %d ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
        intval($form_id), $per_page, $offset
    ));
}

function qrm_cf_count_submissions($form_id, $status = '') {
    global $wpdb;
    $table = qrm_cf_submissions_table();
    if (in_array($status, ['new', 'read', 'archived'], true)) {
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE form_id = %d AND status = %s",
            intval($form_id), $status
        ));
    }
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE form_id = %d",
        intval($form_id)
    ));
}

/** Okunmamış gönderim sayacının saklandığı transient. */
const QRM_CF_UNREAD_TRANSIENT = 'qrm_cf_unread_total';

/** Sayaç önbelleğinin ömrü. */
const QRM_CF_UNREAD_TTL = 5 * MINUTE_IN_SECONDS;

/**
 * Tüm formlardaki okunmamış gönderim sayısı (admin menü rozeti için).
 *
 * Bu sayaç sol menü etiketinden okunur, yani wp-admin'in HER sayfasında —
 * eklentinin kendi ekranlarında değil, panelin tamamında — bir COUNT sorgusu
 * açıyordu. Yönetici bir oturumda onlarca sayfa gezdiğinde bu, hiç değişmeyen
 * bir sayı için onlarca bağlantı kullanımı demekti.
 *
 * Artık kısa ömürlü bir transient'te durur ve gönderim geldiğinde / okundu
 * işaretlendiğinde / silindiğinde qrm_cf_flush_unread_total() ile
 * geçersizlenir; TTL yalnızca emniyet kemeridir.
 *
 * @return int
 */
function qrm_cf_unread_total() {
    global $wpdb;

    // Memo $GLOBALS'ta durur ki flush aynı istek içinde onu da temizleyebilsin.
    if (isset($GLOBALS['qrm_cf_unread_memo'])) {
        return (int) $GLOBALS['qrm_cf_unread_memo'];
    }

    $saklanan = get_transient(QRM_CF_UNREAD_TRANSIENT);
    if ($saklanan !== false) {
        $GLOBALS['qrm_cf_unread_memo'] = (int) $saklanan;
        return (int) $saklanan;
    }

    $table = qrm_cf_submissions_table();
    $suppress = $wpdb->suppress_errors(true);
    $sayi = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'new'");
    $wpdb->suppress_errors($suppress);

    set_transient(QRM_CF_UNREAD_TRANSIENT, $sayi, QRM_CF_UNREAD_TTL);
    $GLOBALS['qrm_cf_unread_memo'] = $sayi;

    return $sayi;
}

/**
 * Okunmamış gönderim sayacını geçersizler.
 *
 * @return void
 */
function qrm_cf_flush_unread_total() {
    unset($GLOBALS['qrm_cf_unread_memo']);
    delete_transient(QRM_CF_UNREAD_TRANSIENT);
}

function qrm_cf_set_submission_status($id, $status) {
    global $wpdb;
    if (!in_array($status, ['new', 'read', 'archived'], true)) return false;

    $ok = (bool) $wpdb->update(qrm_cf_submissions_table(), ['status' => $status], ['id' => intval($id)], ['%s'], ['%d']);

    qrm_cf_flush_unread_total();

    return $ok;
}

function qrm_cf_mark_all_read($form_id) {
    global $wpdb;
    $table = qrm_cf_submissions_table();
    $sayi = (int) $wpdb->query($wpdb->prepare(
        "UPDATE $table SET status = 'read' WHERE form_id = %d AND status = 'new'",
        intval($form_id)
    ));

    qrm_cf_flush_unread_total();

    return $sayi;
}

function qrm_cf_delete_submission($id) {
    global $wpdb;

    $ok = (bool) $wpdb->delete(qrm_cf_submissions_table(), ['id' => intval($id)], ['%d']);

    qrm_cf_flush_unread_total();

    return $ok;
}

/** Gönderim satırındaki JSON veriyi diziye çevirir. */
function qrm_cf_submission_data($submission) {
    $raw = is_object($submission) ? $submission->data : $submission;
    $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
    return is_array($decoded) ? $decoded : [];
}

/** Tek bir gönderim değerini tabloda/e-postada gösterilecek metne çevirir. */
function qrm_cf_format_value($field, $value) {
    $type = is_object($field) ? $field->field_type : $field['field_type'];

    if (is_array($value)) {
        return implode(', ', $value);
    }
    if ($type === 'rating' && $value !== '') {
        $n = max(0, min(5, intval($value)));
        return str_repeat('★', $n) . str_repeat('☆', 5 - $n) . ' (' . $n . '/5)';
    }
    return (string) $value;
}

/**
 * Gönderimden cooldown kimlik bilgilerini (e-posta / telefon) çıkarır.
 * İlk e-posta ve ilk telefon alanı yeterlidir; yoksa yalnızca IP ile kimliklendirilir.
 *
 * @return array ['email' => string, 'phone' => string]
 */
function qrm_cf_cooldown_identifiers($fields, $data) {
    $out = ['email' => '', 'phone' => ''];

    foreach ((array) $fields as $field) {
        $type = is_object($field) ? $field->field_type : $field['field_type'];
        $key  = is_object($field) ? $field->field_key : $field['field_key'];
        if (!isset($data[$key]) || !is_string($data[$key]) || $data[$key] === '') continue;

        if ($type === 'email' && $out['email'] === '') {
            $out['email'] = $data[$key];
        } elseif ($type === 'tel' && $out['phone'] === '') {
            $out['phone'] = $data[$key];
        }
    }

    return $out;
}

// --- BİLDİRİM E-POSTASI ---

/**
 * Yeni gönderimde admin'e bildirim gönderir (form ayarında açıksa).
 * Ödül modülündeki wp_mail deseniyle aynı: content-type filtresi geçici eklenir.
 */
function qrm_cf_notify_admin($form, $fields, $data) {
    $settings = qrm_cf_get_form_settings($form);
    if (empty($settings['notify_enabled'])) return false;

    $to = $settings['notify_email'] !== '' ? $settings['notify_email'] : get_option('admin_email');
    if (!is_email($to)) return false;

    $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $subject   = sprintf('[%s] Yeni form gönderimi: %s', $site_name, $form->title);

    $body  = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;padding:24px;color:#1e293b;">';
    $body .= '<h2 style="margin:0 0 4px;font-size:19px;">' . esc_html($form->title) . '</h2>';
    $body .= '<p style="font-size:13px;opacity:.65;margin:0 0 18px;">' . esc_html(current_time('d.m.Y H:i')) . ' tarihinde yeni bir gönderim alındı.</p>';
    $body .= '<table style="width:100%;border-collapse:collapse;font-size:14px;">';

    foreach ((array) $fields as $field) {
        $key   = $field->field_key;
        $value = isset($data[$key]) ? qrm_cf_format_value($field, $data[$key]) : '';
        $body .= '<tr>';
        $body .= '<td style="padding:9px 12px;border:1px solid #e2e8f0;background:#f8fafc;font-weight:600;width:36%;">' . esc_html($field->label) . '</td>';
        $body .= '<td style="padding:9px 12px;border:1px solid #e2e8f0;">' . nl2br(esc_html($value)) . '</td>';
        $body .= '</tr>';
    }

    $body .= '</table>';
    $body .= '<p style="font-size:13px;margin:18px 0 0;"><a href="' . esc_url(qrm_cf_admin_url(['tab' => 'submissions', 'form_id' => intval($form->id)])) . '">Yönetim panelinde görüntüle →</a></p>';
    $body .= '</div>';

    add_filter('wp_mail_content_type', 'qrm_cf_mail_content_type');
    $sent = wp_mail($to, $subject, $body);
    remove_filter('wp_mail_content_type', 'qrm_cf_mail_content_type');

    if (!$sent) {
        qrm_pro_debug_log('Form bildirim e-postası gönderilemedi: form_id=' . intval($form->id));
    }
    return $sent;
}

function qrm_cf_mail_content_type() {
    return 'text/html';
}
