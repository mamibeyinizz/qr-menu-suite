<?php
/**
 * CSV dışa aktarım — yorumlar, özel form gönderileri, ödül kodları.
 *
 * admin_post_qrm_export_csv (AJAX değil); bellek korumalı parça parça yazar.
 *
 * @package QR_Menu_Reviews
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Parça boyutu (satır). */
const QRM_EXPORT_CSV_CHUNK = 1000;

add_action('admin_post_qrm_export_csv', 'qrm_export_csv_handler');

/**
 * İstekten yorum listesi ek filtrelerini okur.
 *
 * @param array $source $_GET veya $_POST.
 * @return array
 */
function qrm_pro_admin_review_list_filters(array $source = []) {
    $bas = (isset($source['liste_bas']) && is_scalar($source['liste_bas']))
        ? sanitize_text_field(wp_unslash($source['liste_bas'])) : '';
    $bit = (isset($source['liste_bit']) && is_scalar($source['liste_bit']))
        ? sanitize_text_field(wp_unslash($source['liste_bit'])) : '';
    $search = (isset($source['s']) && is_scalar($source['s']))
        ? sanitize_text_field(wp_unslash($source['s'])) : '';
    $table_id = isset($source['table_id']) ? absint($source['table_id']) : 0;

    $extra = [
        'liste_bas' => $bas,
        'liste_bit' => $bit,
        'search'    => $search,
        'table_id'  => $table_id > 0 ? $table_id : 0,
    ];

    if ($bas !== '' && $bit !== '') {
        $range = qrm_pro_report_date_range($bas, $bit);
        $extra['bas_dt']   = $range['bas_dt'];
        $extra['bit_excl'] = $range['bit_excl'];
        $extra['liste_bas'] = $range['bas'];
        $extra['liste_bit'] = $range['bit'];
    }

    return $extra;
}

/**
 * Ek liste filtreleri aktif mi?
 *
 * @param array $extra qrm_pro_admin_review_list_filters() çıktısı.
 * @return bool
 */
function qrm_pro_admin_review_has_list_filters(array $extra) {
    return !empty($extra['bas_dt'])
        || $extra['search'] !== ''
        || !empty($extra['table_id']);
}

/**
 * Formül enjeksiyonuna karşı hücreyi güvenli hâle getirir.
 *
 * @param mixed $value Hücre değeri.
 * @return string
 */
function qrm_export_csv_cell($value) {
    $value = is_scalar($value) ? (string) $value : '';
    if ($value === '') {
        return '';
    }

    $first = $value[0];
    if ($first === '=' || $first === '+' || $first === '-' || $first === '@' || $first === "\t" || $first === "\r") {
        return "'" . $value;
    }

    return $value;
}

/**
 * CSV çıktısını başlatır (UTF-8 BOM + başlıklar).
 *
 * @param string   $filename İndirme dosya adı.
 * @param string[] $headers  Başlık satırı.
 * @return resource|false
 */
function qrm_export_csv_begin($filename, array $headers) {
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        return false;
    }

    // Excel Türkçe karakter için BOM.
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, array_map('qrm_export_csv_cell', $headers), ',');
    fflush($out);

    return $out;
}

/**
 * Tek satır yazar ve tamponu boşaltır.
 *
 * @param resource $out  Dosya tanıtıcısı.
 * @param array    $row  Satır değerleri.
 * @return void
 */
function qrm_export_csv_write_row($out, array $row) {
    fputcsv($out, array_map('qrm_export_csv_cell', $row), ',');
    fflush($out);
}

/**
 * Zaman damgalı dosya adı üretir.
 *
 * @param string $prefix Önek (yorumlar, form-gonderileri, odul-kodlari).
 * @return string
 */
function qrm_export_csv_filename($prefix) {
    return sanitize_file_name($prefix . '-' . gmdate('Y-m-d-Hi') . '.csv');
}

/**
 * Dışa aktar butonu (aktif filtreleri gizli alan olarak taşır).
 *
 * @param string $type   reviews | submissions | reward_codes.
 * @param array  $fields Gizli alanlar.
 * @return string HTML
 */
function qrm_export_csv_button($type, array $fields = []) {
    ob_start();
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="qrm-export-csv-form">
        <?php wp_nonce_field('qrm_export_csv'); ?>
        <input type="hidden" name="action" value="qrm_export_csv">
        <input type="hidden" name="export_type" value="<?php echo esc_attr($type); ?>">
        <?php foreach ($fields as $key => $val):
            if ($val === '' || $val === null) {
                continue;
            }
        ?>
            <input type="hidden" name="<?php echo esc_attr((string) $key); ?>" value="<?php echo esc_attr((string) $val); ?>">
        <?php endforeach; ?>
        <button type="submit" class="button"><?php esc_html_e('Dışa Aktar (CSV)', 'qrms'); ?></button>
    </form>
    <?php
    return ob_get_clean();
}

/**
 * admin_post işleyicisi.
 *
 * @return void
 */
function qrm_export_csv_handler() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Bu işlem için yetkiniz yok.', 'qrms'));
    }

    check_admin_referer('qrm_export_csv');

    $type = isset($_POST['export_type']) ? sanitize_key(wp_unslash($_POST['export_type'])) : '';

    switch ($type) {
        case 'reviews':
            qrm_export_csv_reviews($_POST);
            break;
        case 'submissions':
            qrm_export_csv_submissions($_POST);
            break;
        case 'reward_codes':
            qrm_export_csv_reward_codes($_POST);
            break;
        case 'consent_marketing':
            qrm_export_csv_consent_marketing($_POST);
            break;
        default:
            wp_die(esc_html__('Geçersiz dışa aktarım türü.', 'qrms'));
    }
}

/**
 * Filtrelenmiş yorum sayısı.
 *
 * @param string $durum     '' | bekleyen | onayli.
 * @param string $sekme     '' | olumlu | olumsuz.
 * @param float  $threshold Eşik.
 * @param string $wf        İş akışı.
 * @param array  $extra     Liste filtreleri.
 * @return int
 */
function qrm_export_reviews_count($durum, $sekme, $threshold, $wf, array $extra) {
    global $wpdb;

    if (!qrm_pro_reviews_table_exists()) {
        return 0;
    }

    $table = $wpdb->prefix . 'qrm_reviews';
    list($where, $params) = qrm_pro_admin_reviews_where($durum, $sekme, $threshold, $wf, $extra);

    if (!empty($params)) {
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table}{$where}", $params));
    }

    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}{$where}");
}

/**
 * Yorum parçası çeker.
 *
 * @param string $durum     Durum filtresi.
 * @param string $sekme     Sekme.
 * @param float  $threshold Eşik.
 * @param string $wf        İş akışı.
 * @param array  $extra     Liste filtreleri.
 * @param int    $limit     LIMIT.
 * @param int    $offset    OFFSET.
 * @return array
 */
function qrm_export_reviews_chunk($durum, $sekme, $threshold, $wf, array $extra, $limit, $offset) {
    global $wpdb;

    $table = $wpdb->prefix . 'qrm_reviews';
    list($where, $params) = qrm_pro_admin_reviews_where($durum, $sekme, $threshold, $wf, $extra);

    $params[] = (int) $limit;
    $params[] = (int) $offset;

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table}{$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
        $params
    ));

    return is_array($rows) ? $rows : [];
}

/**
 * Yorumları CSV olarak dışa aktarır.
 *
 * @param array $post $_POST.
 * @return void
 */
function qrm_export_csv_reviews(array $post) {
    if (!qrm_pro_reviews_table_exists()) {
        wp_die(esc_html__('Yorum tablosu bulunamadı.', 'qrms'));
    }

    $settings  = qrm_pro_get_settings();
    $sekme     = qrm_pro_admin_review_tab(isset($post['sekme']) ? wp_unslash($post['sekme']) : '');
    $durum     = (isset($post['durum']) && is_scalar($post['durum'])) ? sanitize_key(wp_unslash($post['durum'])) : '';
    if (!in_array($durum, ['bekleyen', 'onayli'], true)) {
        $durum = '';
    }
    $wf        = qrm_pro_admin_review_workflow_filter(isset($post['wf']) ? wp_unslash($post['wf']) : '');
    $extra     = qrm_pro_admin_review_list_filters($post);
    $threshold = qrm_pro_sentiment_threshold();
    $wf_labels = qrm_pro_review_workflow_statuses();

    $table_names = [];
    if (class_exists('QMO_Masalar') && method_exists('QMO_Masalar', 'hepsi')) {
        foreach ((array) QMO_Masalar::hepsi() as $masa) {
            if (!empty($masa->id)) {
                $table_names[(int) $masa->id] = (string) $masa->table_name;
            }
        }
    }

    $headers = [
        __('ID', 'qrms'),
        __('Tarih', 'qrms'),
        __('Müşteri', 'qrms'),
        __('Telefon', 'qrms'),
        __('Masa (kayıtlı)', 'qrms'),
        __('Masa No', 'qrms'),
        __('Ortalama', 'qrms'),
    ];
    for ($i = 1; $i <= 5; $i++) {
        if (!empty($settings['crit_'.$i.'_active'])) {
            $headers[] = $settings['crit_'.$i.'_name'];
        }
    }
    $headers = array_merge($headers, [
        __('Yorum', 'qrms'),
        __('Yayın', 'qrms'),
        __('İş Akışı', 'qrms'),
        __('Sorumlu', 'qrms'),
        __('İç Not', 'qrms'),
        __('Kaynak', 'qrms'),
    ]);

    $out = qrm_export_csv_begin(qrm_export_csv_filename('yorumlar'), $headers);
    if ($out === false) {
        wp_die(esc_html__('CSV dosyası oluşturulamadı.', 'qrms'));
    }

    $offset = 0;
    $chunk  = QRM_EXPORT_CSV_CHUNK;

    while (true) {
        $rows = qrm_export_reviews_chunk($durum, $sekme, $threshold, $wf, $extra, $chunk, $offset);
        if (empty($rows)) {
            break;
        }

        foreach ($rows as $r) {
            $name = $r->is_anonymous ? __('Anonim', 'qrms') : (string) $r->customer_name;
            $table_label = '';
            if (!empty($r->table_id) && isset($table_names[(int) $r->table_id])) {
                $table_label = $table_names[(int) $r->table_id];
            }

            $assignee = '';
            if (!empty($r->assigned_user_id)) {
                $user = get_userdata((int) $r->assigned_user_id);
                $assignee = $user ? $user->display_name : ('#' . (int) $r->assigned_user_id);
            }

            $wf_key = isset($r->workflow_status) ? sanitize_key($r->workflow_status) : 'new';
            $wf_label = isset($wf_labels[$wf_key]) ? $wf_labels[$wf_key] : $wf_key;

            $row = [
                (int) $r->id,
                date_i18n('Y-m-d H:i', strtotime($r->created_at)),
                $name,
                (string) $r->customer_phone,
                $table_label,
                (string) $r->table_no,
                number_format((float) $r->rating, 1, '.', ''),
            ];

            for ($i = 1; $i <= 5; $i++) {
                if (empty($settings['crit_'.$i.'_active'])) {
                    continue;
                }
                $val = isset($r->{'rating_'.$i}) ? (int) $r->{'rating_'.$i} : 0;
                $row[] = $val > 0 ? (string) $val : '';
            }

            $row[] = (string) $r->comment;
            $row[] = !empty($r->status) ? __('Yayında', 'qrms') : __('Bekliyor', 'qrms');
            $row[] = $wf_label;
            $row[] = $assignee;
            $row[] = isset($r->internal_note) ? (string) $r->internal_note : '';
            $row[] = (!empty($r->form_source) && $r->form_source === 'contact') ? __('İletişim', 'qrms') : __('Yorum', 'qrms');

            qrm_export_csv_write_row($out, $row);
        }

        if (count($rows) < $chunk) {
            break;
        }
        $offset += $chunk;
    }

    fclose($out);
    exit;
}

/**
 * Özel form gönderimi WHERE parçası.
 *
 * @param int    $form_id Form ID.
 * @param string $status  '' | new | read | archived.
 * @param array  $extra   bas_dt, bit_excl, search.
 * @return array{0:string,1:array}
 */
function qrm_cf_submissions_where($form_id, $status = '', array $extra = []) {
    global $wpdb;

    $kosullar = ['form_id = %d'];
    $params   = [(int) $form_id];

    if (in_array($status, ['new', 'read', 'archived'], true)) {
        $kosullar[] = 'status = %s';
        $params[]   = $status;
    }

    if (!empty($extra['bas_dt']) && !empty($extra['bit_excl'])) {
        $kosullar[] = 'created_at >= %s';
        $params[]   = $extra['bas_dt'];
        $kosullar[] = 'created_at < %s';
        $params[]   = $extra['bit_excl'];
    }

    if (!empty($extra['search'])) {
        $like = '%' . $wpdb->esc_like($extra['search']) . '%';
        $kosullar[] = 'data LIKE %s';
        $params[]   = $like;
    }

    return [
        ' WHERE ' . implode(' AND ', $kosullar),
        $params,
    ];
}

/**
 * Filtrelenmiş gönderim sayısı.
 *
 * @param int    $form_id Form ID.
 * @param string $status  '' | new | read | archived.
 * @param array  $extra   bas_dt, bit_excl, search.
 * @return int
 */
function qrm_cf_count_submissions_filtered($form_id, $status = '', array $extra = []) {
    global $wpdb;

    $table = qrm_cf_submissions_table();
    list($where, $params) = qrm_cf_submissions_where((int) $form_id, $status, $extra);

    if (!empty($params)) {
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table}{$where}", $params));
    }

    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}{$where}");
}

/**
 * Gönderim parçası.
 *
 * @param int    $form_id Form ID.
 * @param string $status  Durum.
 * @param array  $extra   Ek filtreler.
 * @param int    $limit   LIMIT.
 * @param int    $offset  OFFSET.
 * @return array
 */
function qrm_cf_export_submissions_chunk($form_id, $status, array $extra, $limit, $offset) {
    global $wpdb;

    $table = qrm_cf_submissions_table();
    list($where, $params) = qrm_cf_submissions_where($form_id, $status, $extra);
    $params[] = (int) $limit;
    $params[] = (int) $offset;

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table}{$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
        $params
    ));

    return is_array($rows) ? $rows : [];
}

/**
 * Özel form gönderimlerini dışa aktarır.
 *
 * @param array $post $_POST.
 * @return void
 */
function qrm_export_csv_submissions(array $post) {
    $form_id = isset($post['form_id']) ? absint($post['form_id']) : 0;
    $form    = $form_id > 0 ? qrm_cf_get_form($form_id) : null;

    if (!$form) {
        wp_die(esc_html__('Form bulunamadı.', 'qrms'));
    }

    $status = isset($post['status']) ? sanitize_key(wp_unslash($post['status'])) : '';
    if (!in_array($status, ['new', 'read', 'archived'], true)) {
        $status = '';
    }

    $extra  = qrm_pro_admin_review_list_filters($post);
    $fields = qrm_cf_get_fields($form->id);

    $headers = [
        __('ID', 'qrms'),
        __('Tarih', 'qrms'),
        __('Durum', 'qrms'),
        __('IP', 'qrms'),
    ];
    foreach ($fields as $field) {
        $headers[] = (string) $field->label;
    }

    $status_labels = [
        'new'      => __('Yeni', 'qrms'),
        'read'     => __('Okundu', 'qrms'),
        'archived' => __('Arşiv', 'qrms'),
    ];

    $out = qrm_export_csv_begin(qrm_export_csv_filename('form-gonderileri'), $headers);
    if ($out === false) {
        wp_die(esc_html__('CSV dosyası oluşturulamadı.', 'qrms'));
    }

    $offset = 0;
    $chunk  = QRM_EXPORT_CSV_CHUNK;

    while (true) {
        $rows = qrm_cf_export_submissions_chunk($form->id, $status, $extra, $chunk, $offset);
        if (empty($rows)) {
            break;
        }

        foreach ($rows as $sub) {
            $data = json_decode((string) $sub->data, true);
            if (!is_array($data)) {
                $data = [];
            }

            $st = isset($sub->status) ? (string) $sub->status : 'new';
            $row = [
                (int) $sub->id,
                date_i18n('Y-m-d H:i', strtotime($sub->created_at)),
                isset($status_labels[$st]) ? $status_labels[$st] : $st,
                (string) $sub->ip_address,
            ];

            foreach ($fields as $field) {
                $key = (string) $field->field_key;
                if (!isset($data[$key])) {
                    $row[] = '';
                    continue;
                }
                $val = $data[$key];
                if (is_array($val)) {
                    $row[] = implode(', ', array_map('strval', $val));
                } else {
                    $row[] = (string) $val;
                }
            }

            qrm_export_csv_write_row($out, $row);
        }

        if (count($rows) < $chunk) {
            break;
        }
        $offset += $chunk;
    }

    fclose($out);
    exit;
}

/**
 * Ödül kodu WHERE parçası.
 *
 * @param array $filters status, search, bas_dt, bit_excl.
 * @return array{0:string,1:array}
 */
function qrm_reward_codes_where(array $filters) {
    global $wpdb;

    $kosullar = ['1=1'];
    $params   = [];

    if (!empty($filters['status']) && in_array($filters['status'], ['active', 'used', 'expired', 'revoked'], true)) {
        $kosullar[] = 'status = %s';
        $params[]   = $filters['status'];
    }

    if (!empty($filters['search'])) {
        $like = '%' . $wpdb->esc_like($filters['search']) . '%';
        $kosullar[] = '(email LIKE %s OR code LIKE %s)';
        $params[]   = $like;
        $params[]   = $like;
    }

    if (!empty($filters['bas_dt']) && !empty($filters['bit_excl'])) {
        $kosullar[] = 'created_at >= %s';
        $params[]   = $filters['bas_dt'];
        $kosullar[] = 'created_at < %s';
        $params[]   = $filters['bit_excl'];
    }

    return [
        ' WHERE ' . implode(' AND ', $kosullar),
        $params,
    ];
}

/**
 * Ödül kodu parçası.
 *
 * @param array $filters Filtreler.
 * @param int   $limit   LIMIT.
 * @param int   $offset  OFFSET.
 * @return array
 */
function qrm_reward_export_codes_chunk(array $filters, $limit, $offset) {
    global $wpdb;

    $table = qrm_reward_table();
    list($where, $params) = qrm_reward_codes_where($filters);
    $params[] = (int) $limit;
    $params[] = (int) $offset;

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table}{$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
        $params
    ));

    return is_array($rows) ? $rows : [];
}

/**
 * Ödül kodlarını dışa aktarır.
 *
 * @param array $post $_POST.
 * @return void
 */
function qrm_export_csv_reward_codes(array $post) {
    $status = isset($post['status']) ? sanitize_key(wp_unslash($post['status'])) : '';
    if (!in_array($status, ['active', 'used', 'expired', 'revoked'], true)) {
        $status = '';
    }

    $search = (isset($post['s']) && is_scalar($post['s']))
        ? sanitize_text_field(wp_unslash($post['s'])) : '';

    $extra = qrm_pro_admin_review_list_filters($post);
    $filters = [
        'status' => $status,
        'search' => $search,
    ];
    if (!empty($extra['bas_dt'])) {
        $filters['bas_dt']   = $extra['bas_dt'];
        $filters['bit_excl'] = $extra['bit_excl'];
    }

    $headers = [
        __('Oluşturulma', 'qrms'),
        __('E-posta', 'qrms'),
        __('Kod', 'qrms'),
        __('İndirim', 'qrms'),
        __('Durum', 'qrms'),
        __('Kullanım', 'qrms'),
        __('Manuel', 'qrms'),
    ];

    $out = qrm_export_csv_begin(qrm_export_csv_filename('odul-kodlari'), $headers);
    if ($out === false) {
        wp_die(esc_html__('CSV dosyası oluşturulamadı.', 'qrms'));
    }

    $offset = 0;
    $chunk  = QRM_EXPORT_CSV_CHUNK;

    while (true) {
        $rows = qrm_reward_export_codes_chunk($filters, $chunk, $offset);
        if (empty($rows)) {
            break;
        }

        foreach ($rows as $r) {
            qrm_export_csv_write_row($out, [
                date_i18n('Y-m-d H:i', strtotime($r->created_at)),
                (string) $r->email,
                (string) $r->code,
                (string) $r->discount_label,
                qrm_reward_status_label($r->status),
                !empty($r->used_at) ? date_i18n('Y-m-d H:i', strtotime($r->used_at)) : '',
                !empty($r->is_manual) ? __('Evet', 'qrms') : __('Hayır', 'qrms'),
            ]);
        }

        if (count($rows) < $chunk) {
            break;
        }
        $offset += $chunk;
    }

    fclose($out);
    exit;
}

/**
 * İzinli kişiler CSV dışa aktarımı.
 *
 * @param array $post $_POST.
 * @return void
 */
function qrm_export_csv_consent_marketing(array $post) {
    $bas_raw = (isset($post['rapor_bas']) && is_scalar($post['rapor_bas']))
        ? sanitize_text_field(wp_unslash($post['rapor_bas'])) : '';
    $bit_raw = (isset($post['rapor_bit']) && is_scalar($post['rapor_bit']))
        ? sanitize_text_field(wp_unslash($post['rapor_bit'])) : '';
    $range   = qrm_pro_report_date_range($bas_raw, $bit_raw);

    $headers = [
        __('Ad', 'qrms'),
        __('Telefon', 'qrms'),
        __('E-posta', 'qrms'),
        __('Kaynak', 'qrms'),
        __('Onay Tarihi', 'qrms'),
    ];

    $out = qrm_export_csv_begin(qrm_export_csv_filename('izinli-kisiler'), $headers);
    if ($out === false) {
        wp_die(esc_html__('CSV dosyası oluşturulamadı.', 'qrms'));
    }

    $offset = 0;
    $chunk  = QRM_EXPORT_CSV_CHUNK;
    $fields = [];

    while (true) {
        $rows = qrm_pro_fetch_consent_entries($range['bas_dt'], $range['bit_excl'], $chunk, $offset);
        if (empty($rows)) {
            break;
        }

        foreach ($rows as $row) {
            $contact = qrm_pro_consent_contact_from_row($row, $fields);
            qrm_export_csv_write_row($out, [
                $contact['name'],
                $contact['phone'],
                $contact['email'],
                (string) $row->source_label,
                !empty($row->consent_at) ? date_i18n('Y-m-d H:i', strtotime($row->consent_at)) : '',
            ]);
        }

        if (count($rows) < $chunk) {
            break;
        }
        $offset += $chunk;
    }

    fclose($out);
    exit;
}
