<?php
if (!defined('ABSPATH')) exit;

// KVKK / pazarlama izni — ortak yardımcılar (v4.2.7)

/**
 * Onay metni (boşsa checkbox gösterilmez).
 *
 * @param array|null $settings Ayarlar.
 * @return string
 */
function qrm_pro_consent_text($settings = null) {
    if ($settings === null) {
        $settings = qrm_pro_get_settings();
    }
    return trim((string) ($settings['qrm_consent_text'] ?? ''));
}

/**
 * Aydınlatma metni sayfa URL'si.
 *
 * @param array|null $settings Ayarlar.
 * @return string
 */
function qrm_pro_consent_page_url($settings = null) {
    if ($settings === null) {
        $settings = qrm_pro_get_settings();
    }
    $url = trim((string) ($settings['qrm_consent_page_url'] ?? ''));
    return $url !== '' ? esc_url($url) : '';
}

/**
 * Pazarlama onay kutusu etkin mi?
 *
 * @param array|null $settings Ayarlar.
 * @return bool
 */
function qrm_pro_consent_enabled($settings = null) {
    return qrm_pro_consent_text($settings) !== '';
}

/**
 * Gösterilen metnin md5 özeti.
 *
 * @param string $text Onay metni.
 * @return string
 */
function qrm_pro_consent_text_hash($text) {
    return md5((string) $text);
}

/**
 * İstekten onay alanlarını üretir (işaretlenmemişse sıfır değerler).
 *
 * @param array|null $settings Ayarlar.
 * @return array{consent_marketing:int,consent_at:?string,consent_text_hash:?string}
 */
function qrm_pro_consent_from_request($settings = null) {
    $empty = [
        'consent_marketing' => 0,
        'consent_at'        => null,
        'consent_text_hash' => null,
    ];

    if (!qrm_pro_consent_enabled($settings)) {
        return $empty;
    }

    if (empty($_POST['consent_marketing'])) {
        return $empty;
    }

    $text = qrm_pro_consent_text($settings);

    return [
        'consent_marketing' => 1,
        'consent_at'        => current_time('mysql'),
        'consent_text_hash' => qrm_pro_consent_text_hash($text),
    ];
}

/**
 * Onay kutusu HTML (metin boşsa boş string).
 *
 * @param array|null $settings Ayarlar.
 * @return string
 */
function qrm_pro_render_consent_checkbox($settings = null) {
    if ($settings === null) {
        $settings = qrm_pro_get_settings();
    }

    $text = qrm_pro_consent_text($settings);
    if ($text === '') {
        return '';
    }

    $page_url = qrm_pro_consent_page_url($settings);
    $label    = qrm_ceviri_option('qrm_settings.qrm_consent_text', $text);

    ob_start();
    ?>
    <div class="qrm-input-group qrm-consent-group full">
        <label class="qrm-consent-label" style="display:flex; align-items:flex-start; gap:8px; cursor:pointer; font-weight:normal;">
            <input type="checkbox" name="consent_marketing" value="1" style="width:auto; margin-top:3px;">
            <span>
                <?php echo esc_html($label); ?>
                <?php if ($page_url !== ''): ?>
                    <a href="<?php echo esc_url($page_url); ?>" target="_blank" rel="noopener noreferrer" class="qrm-consent-link" onclick="event.stopPropagation();">
                        <?php echo esc_html(qrm_ceviri_review(__('Aydınlatma Metni', 'qrms'))); ?>
                    </a>
                <?php endif; ?>
            </span>
        </label>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * İzinli kişi sayısı (yalnızca consent_marketing = 1).
 *
 * @param string $bas_dt   Başlangıç datetime.
 * @param string $bit_excl Bitiş (hariç) datetime.
 * @return int
 */
function qrm_pro_count_consent_entries($bas_dt, $bit_excl) {
    global $wpdb;

    $reviews = $wpdb->prefix . 'qrm_reviews';
    $subs    = qrm_cf_submissions_table();

    $n_reviews = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$reviews}
         WHERE consent_marketing = 1 AND consent_at >= %s AND consent_at < %s",
        $bas_dt,
        $bit_excl
    ));

    $n_subs = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$subs}
         WHERE consent_marketing = 1 AND consent_at >= %s AND consent_at < %s",
        $bas_dt,
        $bit_excl
    ));

    return $n_reviews + $n_subs;
}

/**
 * İzinli kişi kayıtlarını çeker (birleşik liste).
 *
 * @param string $bas_dt   Başlangıç.
 * @param string $bit_excl Bitiş (hariç).
 * @param int    $limit    LIMIT.
 * @param int    $offset   OFFSET.
 * @return array<object>
 */
function qrm_pro_fetch_consent_entries($bas_dt, $bit_excl, $limit, $offset) {
    global $wpdb;

    $reviews = $wpdb->prefix . 'qrm_reviews';
    $subs    = qrm_cf_submissions_table();
    $forms   = qrm_cf_forms_table();

    $sql = "
        SELECT * FROM (
            SELECT 'review' AS source_type, r.id AS row_id, r.customer_name, r.customer_phone,
                   r.is_anonymous, r.consent_at, NULL AS data_json, NULL AS form_id,
                   CASE WHEN r.form_source = 'contact' THEN %s ELSE %s END AS source_label
            FROM {$reviews} r
            WHERE r.consent_marketing = 1 AND r.consent_at >= %s AND r.consent_at < %s
            UNION ALL
            SELECT 'submission', s.id, '', '', 0, s.consent_at, s.data, s.form_id, f.title
            FROM {$subs} s
            INNER JOIN {$forms} f ON f.id = s.form_id
            WHERE s.consent_marketing = 1 AND s.consent_at >= %s AND s.consent_at < %s
        ) combined
        ORDER BY consent_at DESC, row_id DESC
        LIMIT %d OFFSET %d
    ";

    $label_review  = __('Yorum', 'qrms');
    $label_contact = __('İletişim', 'qrms');

    return $wpdb->get_results($wpdb->prepare(
        $sql,
        $label_contact,
        $label_review,
        $bas_dt,
        $bit_excl,
        $bas_dt,
        $bit_excl,
        (int) $limit,
        (int) $offset
    ));
}

/**
 * Gönderim JSON'undan iletişim bilgisi çıkarır.
 *
 * @param object $row       Sorgu satırı (data_json, form_id).
 * @param array  $fields    form_id => alan dizisi önbelleği.
 * @return array{name:string,phone:string,email:string}
 */
function qrm_pro_consent_contact_from_row($row, array &$fields) {
    $out = ['name' => '', 'phone' => '', 'email' => ''];

    if ($row->source_type === 'review') {
        if (!empty($row->is_anonymous)) {
            $out['name'] = __('Anonim', 'qrms');
        } else {
            $out['name'] = (string) $row->customer_name;
        }
        $out['phone'] = (string) $row->customer_phone;
        return $out;
    }

    $form_id = (int) $row->form_id;
    if (!isset($fields[$form_id])) {
        $fields[$form_id] = qrm_cf_get_fields($form_id);
    }

    $data = json_decode((string) $row->data_json, true);
    if (!is_array($data)) {
        $data = [];
    }

    foreach ($fields[$form_id] as $field) {
        $key = (string) $field->field_key;
        if (!isset($data[$key]) || qrm_cf_value_is_empty($data[$key])) {
            continue;
        }
        $val = is_array($data[$key]) ? implode(', ', $data[$key]) : (string) $data[$key];
        if ($field->field_type === 'email' && $out['email'] === '') {
            $out['email'] = $val;
        } elseif ($field->field_type === 'tel' && $out['phone'] === '') {
            $out['phone'] = $val;
        } elseif ($field->field_type === 'text' && $out['name'] === '') {
            $out['name'] = $val;
        }
    }

    return $out;
}

/**
 * Pazarlama iznini geri alır.
 *
 * @param string $source 'review' | 'submission'.
 * @param int    $id     Kayıt kimliği.
 * @return bool
 */
function qrm_pro_revoke_consent($source, $id) {
    global $wpdb;

    $id = (int) $id;
    if ($id <= 0) {
        return false;
    }

    if ($source === 'review') {
        $table = $wpdb->prefix . 'qrm_reviews';
    } elseif ($source === 'submission') {
        $table = qrm_cf_submissions_table();
    } else {
        return false;
    }

    $updated = $wpdb->update(
        $table,
        ['consent_marketing' => 0],
        ['id' => $id, 'consent_marketing' => 1],
        ['%d'],
        ['%d', '%d']
    );

    return $updated !== false && $updated > 0;
}
