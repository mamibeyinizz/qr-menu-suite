<?php
if (!defined('ABSPATH')) exit;

// FORMLAR SAYFASI: "GÖNDERİLER" SEKMESİ (v4.2.0, v4.2.1'de sekme hâline geldi)
//
// Her özel formun gönderimleri kendi listesinde tutulur; yorum formu verileriyle
// (qrm_reviews) hiçbir şekilde karışmaz. Menü şişmesin diye tüm formlar TEK bir
// sekmede, üstteki form seçici sekme çubuğuyla gezilir.

/** "Gönderiler" sekmesinin POST aksiyonları. */
function qrm_cf_admin_handle_submission_actions() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['qrm_cf_submission_action'])) {
        return '';
    }
    check_admin_referer('qrm_cf_submissions');

    $action = sanitize_key($_POST['qrm_cf_submission_action']);
    $id     = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
    $fid    = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;

    if ($action === 'mark_read' && $id > 0 && qrm_cf_set_submission_status($id, 'read')) {
        return 'Gönderim okundu olarak işaretlendi.';
    }
    if ($action === 'mark_new' && $id > 0 && qrm_cf_set_submission_status($id, 'new')) {
        return 'Gönderim yeniden "yeni" olarak işaretlendi.';
    }
    if ($action === 'archive' && $id > 0 && qrm_cf_set_submission_status($id, 'archived')) {
        return 'Gönderim arşivlendi.';
    }
    if ($action === 'delete' && $id > 0 && qrm_cf_delete_submission($id)) {
        return 'Gönderim silindi.';
    }
    if ($action === 'mark_all_read' && $fid > 0) {
        return sprintf('%d gönderim okundu olarak işaretlendi.', qrm_cf_mark_all_read($fid));
    }
    return '';
}

/** "Gönderiler" sekmesinin içeriği. */
function qrm_cf_admin_submissions_pane() {
    $forms = qrm_cf_get_forms();

    if (empty($forms)) {
        ?>
        <div class="qrm-cf-empty" style="margin-top:20px;">
            <div class="qrm-cf-empty-art">📭</div>
            <h2>Henüz hiç form yok</h2>
            <p>Gönderim toplayabilmek için önce bir form oluşturmanız gerekiyor.</p>
            <a class="qrm-cf-btn-primary" href="<?php echo esc_url(qrm_cf_admin_url(['view' => 'edit'])); ?>">İlk formunu oluştur</a>
        </div>
        <?php
        return;
    }

    // Aktif form: URL'den gelen form_id, yoksa okunmamışı olan ilk form, o da yoksa ilk form.
    $requested = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
    $current   = null;
    foreach ($forms as $f) {
        if ($f->id == $requested) { $current = $f; break; }
    }
    if (!$current) {
        foreach ($forms as $f) {
            if (qrm_cf_count_submissions($f->id, 'new') > 0) { $current = $f; break; }
        }
    }
    if (!$current) $current = $forms[0];

    $status_filter = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
    if (!in_array($status_filter, ['new', 'read', 'archived'], true)) $status_filter = '';

    $list_filters = function_exists('qrm_pro_admin_review_list_filters')
        ? qrm_pro_admin_review_list_filters($_GET)
        : ['liste_bas' => '', 'liste_bit' => '', 'search' => '', 'table_id' => 0];
    $has_list_filters = function_exists('qrm_pro_admin_review_has_list_filters')
        && qrm_pro_admin_review_has_list_filters($list_filters);

    $paged    = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 25;

    $fields      = qrm_cf_get_fields($current->id);
    if ($has_list_filters && function_exists('qrm_cf_export_submissions_chunk')) {
        $total = qrm_cf_count_submissions_filtered($current->id, $status_filter, $list_filters);
        $submissions = qrm_cf_export_submissions_chunk($current->id, $status_filter, $list_filters, $per_page, ($paged - 1) * $per_page);
    } else {
        $submissions = qrm_cf_get_submissions($current->id, ['paged' => $paged, 'per_page' => $per_page, 'status' => $status_filter]);
        $total       = qrm_cf_count_submissions($current->id, $status_filter);
    }
    $unread      = qrm_cf_count_submissions($current->id, 'new');
    $total_pages = max(1, (int) ceil($total / $per_page));
    ?>
        <!-- FORM SEÇİCİ SEKME ÇUBUĞU -->
        <div class="qrm-sub-tabs">
            <?php foreach ($forms as $f):
                $f_unread = qrm_cf_count_submissions($f->id, 'new');
                $is_current = ($f->id == $current->id);
            ?>
                <a class="qrm-sub-tab <?php echo $is_current ? 'active' : ''; ?>"
                   href="<?php echo esc_url(qrm_cf_admin_url(['tab' => 'submissions', 'form_id' => intval($f->id)])); ?>">
                    <?php echo esc_html($f->title); ?>
                    <?php if ($f_unread > 0): ?>
                        <span class="qrm-cf-badge qrm-cf-badge-new"><?php echo intval($f_unread); ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="qrm-sub-toolbar">
            <div class="qrm-sub-counters">
                <?php if ($unread > 0): ?>
                    <span class="qrm-sub-counter-new">Yeni: <?php echo intval($unread); ?></span>
                <?php else: ?>
                    <span class="qrm-sub-counter-none">Okunmamış gönderim yok</span>
                <?php endif; ?>
                <span class="qrm-sub-counter-total"><?php echo intval(qrm_cf_count_submissions($current->id)); ?> toplam gönderim</span>
            </div>

            <div class="qrm-sub-filters">
                <?php
                $filters = ['' => 'Tümü', 'new' => 'Yeni', 'read' => 'Okundu', 'archived' => 'Arşiv'];
                foreach ($filters as $value => $label):
                    $filter_args = ['tab' => 'submissions', 'form_id' => intval($current->id)];
                    if ($value !== '') $filter_args['status'] = $value;
                    $url = qrm_cf_admin_url($filter_args);
                ?>
                    <a class="qrm-sub-filter <?php echo $status_filter === $value ? 'active' : ''; ?>" href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>

                <?php if ($unread > 0): ?>
                    <form method="post" style="display:inline;">
                        <?php wp_nonce_field('qrm_cf_submissions'); ?>
                        <input type="hidden" name="form_id" value="<?php echo intval($current->id); ?>">
                        <button type="submit" class="button button-small" name="qrm_cf_submission_action" value="mark_all_read">Tümünü okundu işaretle</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <form method="get" class="qrm-list-toolbar qrm-sub-list-filters">
            <input type="hidden" name="page" value="qrm-forms">
            <input type="hidden" name="tab" value="submissions">
            <input type="hidden" name="form_id" value="<?php echo intval($current->id); ?>">
            <?php if ($status_filter !== ''): ?>
                <input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>">
            <?php endif; ?>
            <div class="qrm-list-toolbar-row">
                <label>
                    <span class="qrm-list-toolbar-label"><?php esc_html_e('Başlangıç', 'qrms'); ?></span>
                    <input type="date" name="liste_bas" value="<?php echo esc_attr($list_filters['liste_bas']); ?>">
                </label>
                <label>
                    <span class="qrm-list-toolbar-label"><?php esc_html_e('Bitiş', 'qrms'); ?></span>
                    <input type="date" name="liste_bit" value="<?php echo esc_attr($list_filters['liste_bit']); ?>">
                </label>
                <label class="qrm-list-toolbar-search">
                    <span class="qrm-list-toolbar-label"><?php esc_html_e('Ara', 'qrms'); ?></span>
                    <input type="search" name="s" value="<?php echo esc_attr($list_filters['search']); ?>" placeholder="<?php esc_attr_e('Form verilerinde ara…', 'qrms'); ?>">
                </label>
                <button type="submit" class="button"><?php esc_html_e('Filtrele', 'qrms'); ?></button>
                <?php
                if (function_exists('qrm_export_csv_button')) {
                    echo qrm_export_csv_button('submissions', [
                        'form_id'   => (int) $current->id,
                        'status'    => $status_filter,
                        'liste_bas' => $list_filters['liste_bas'],
                        'liste_bit' => $list_filters['liste_bit'],
                        's'         => $list_filters['search'],
                    ]);
                }
                ?>
            </div>
        </form>

        <?php if (empty($submissions)): ?>
            <div class="qrm-cf-empty" style="padding:44px 24px;">
                <div class="qrm-cf-empty-art">🗂️</div>
                <h2><?php echo $status_filter === '' ? 'Bu forma henüz gönderim gelmemiş' : 'Bu filtrede gönderim yok'; ?></h2>
                <p>
                    Formu yayınlamak için kısa kodu bir sayfaya ekleyin:
                    <code><?php echo esc_html(qrm_cf_form_shortcode($current)); ?></code>
                </p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped qrm-sub-table">
                <thead>
                    <tr>
                        <th style="width:140px;">Tarih</th>
                        <?php foreach ($fields as $field): ?>
                            <th><?php echo esc_html($field->label); ?></th>
                        <?php endforeach; ?>
                        <th style="width:90px;">Durum</th>
                        <th style="width:210px;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($submissions as $row):
                    $data = qrm_cf_submission_data($row);
                ?>
                    <tr class="<?php echo $row->status === 'new' ? 'qrm-sub-row-new' : ''; ?>">
                        <td data-label="Tarih">
                            <?php echo esc_html(mysql2date('d.m.Y H:i', $row->created_at)); ?>
                            <?php if ($row->ip_address !== ''): ?>
                                <span class="qrm-sub-ip"><?php echo esc_html($row->ip_address); ?></span>
                            <?php endif; ?>
                        </td>
                        <?php foreach ($fields as $field):
                            $value = isset($data[$field->field_key]) ? qrm_cf_format_value($field, $data[$field->field_key]) : '';
                        ?>
                            <td data-label="<?php echo esc_attr($field->label); ?>"><?php echo $value === '' ? '<span class="qrm-sub-empty">—</span>' : nl2br(esc_html($value)); ?></td>
                        <?php endforeach; ?>
                        <td data-label="Durum">
                            <span class="qrm-cf-badge qrm-sub-status-<?php echo esc_attr($row->status); ?>"><?php echo esc_html(qrm_cf_submission_status_label($row->status)); ?></span>
                        </td>
                        <td data-label="">
                            <form method="post" class="qrm-sub-actions">
                                <?php wp_nonce_field('qrm_cf_submissions'); ?>
                                <input type="hidden" name="form_id" value="<?php echo intval($current->id); ?>">
                                <input type="hidden" name="submission_id" value="<?php echo intval($row->id); ?>">
                                <?php if ($row->status === 'new'): ?>
                                    <button type="submit" class="button button-small" name="qrm_cf_submission_action" value="mark_read">Okundu</button>
                                <?php else: ?>
                                    <button type="submit" class="button button-small" name="qrm_cf_submission_action" value="mark_new">Yeni yap</button>
                                <?php endif; ?>
                                <?php if ($row->status !== 'archived'): ?>
                                    <button type="submit" class="button button-small" name="qrm_cf_submission_action" value="archive">Arşivle</button>
                                <?php endif; ?>
                                <button type="submit" class="button button-small qrm-cf-danger" name="qrm_cf_submission_action" value="delete"
                                        onclick="return confirm('Bu gönderim kalıcı olarak silinecek. Emin misiniz?');">Sil</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="tablenav"><div class="tablenav-pages">
                    <?php
                    $page_args = ['tab' => 'submissions', 'form_id' => intval($current->id)];
                    if ($status_filter !== '') $page_args['status'] = $status_filter;
                    if ($list_filters['liste_bas'] !== '') $page_args['liste_bas'] = $list_filters['liste_bas'];
                    if ($list_filters['liste_bit'] !== '') $page_args['liste_bit'] = $list_filters['liste_bit'];
                    if ($list_filters['search'] !== '') $page_args['s'] = $list_filters['search'];
                    // %#%, paginate_links'in sayfa numarası yer tutucusu — http_build_query
                    // onu kodlayacağı için URL'ye sonradan eklenir.
                    echo paginate_links([
                        'base'      => qrm_cf_admin_url($page_args) . '&paged=%#%',
                        'format'    => '',
                        'prev_text' => '‹',
                        'next_text' => '›',
                        'total'     => $total_pages,
                        'current'   => $paged,
                    ]);
                    ?>
                </div></div>
            <?php endif; ?>
        <?php endif; ?>
    <?php
}

function qrm_cf_admin_submissions_styles() {
    ?>
    <style>
        .qrm-sub-tabs { display:flex; flex-wrap:wrap; gap:6px; border-bottom:1px solid #c3c4c7; margin:18px 0 0; }
        .qrm-sub-tab { display:inline-flex; align-items:center; gap:7px; padding:10px 16px; background:#f1f5f9; border:1px solid #c3c4c7; border-bottom:none;
            border-radius:8px 8px 0 0; text-decoration:none; color:#3c434a; font-size:13.5px; font-weight:600; margin-bottom:-1px; transition:background .15s ease, color .15s ease; }
        .qrm-sub-tab:hover { background:#e8eaed; color:#1d2327; }
        .qrm-sub-tab.active { background:#fff; color:#1d2327; border-bottom:1px solid #fff; }

        .qrm-sub-toolbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;
            background:#fff; border:1px solid #c3c4c7; border-top:none; padding:12px 16px; margin-bottom:16px; }
        .qrm-sub-counters { display:flex; align-items:center; gap:14px; }
        .qrm-sub-counter-new { background:#dbeafe; color:#1e40af; font-weight:700; font-size:13px; padding:5px 13px; border-radius:20px; }
        .qrm-sub-counter-none { color:#646970; font-size:13px; }
        .qrm-sub-counter-total { color:#646970; font-size:13px; }
        .qrm-sub-filters { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
        .qrm-sub-filter { padding:5px 12px; border-radius:20px; text-decoration:none; font-size:12.5px; color:#50575e; transition:background .15s ease; }
        .qrm-sub-filter:hover { background:#f1f5f9; color:#1d2327; }
        .qrm-sub-filter.active { background:#2271b1; color:#fff; }

        .qrm-sub-list-filters { background:#fff; border:1px solid #c3c4c7; border-top:none; padding:12px 16px; margin-bottom:16px; }
        .qrm-list-toolbar-row { display:flex; flex-wrap:wrap; align-items:flex-end; gap:10px 14px; }
        .qrm-list-toolbar label { display:flex; flex-direction:column; gap:4px; font-size:12px; }
        .qrm-list-toolbar-label { font-weight:600; color:#50575e; }
        .qrm-list-toolbar-search { flex:1 1 180px; min-width:140px; }
        .qrm-export-csv-form { display:inline; margin:0; }

        .qrm-sub-table td { vertical-align:top; line-height:1.5; }
        .qrm-sub-row-new td { background:#f8fbff; }
        .qrm-sub-row-new td:first-child { box-shadow:inset 3px 0 0 #2271b1; }
        .qrm-sub-ip { display:block; font-size:11px; color:#a7aaad; margin-top:2px; }
        .qrm-sub-empty { color:#c3c4c7; }
        .qrm-sub-actions { display:flex; gap:5px; flex-wrap:wrap; }
        .qrm-sub-status-new { background:#dbeafe; color:#1e40af; }
        .qrm-sub-status-read { background:#f1f5f9; color:#475569; }
        .qrm-sub-status-archived { background:#f5f5f5; color:#787c82; }

        /* Mobil: gönderim tablosunun sütun sayısı forma göre değişir, dar ekranda
           asla sığmaz. Her satır bir karta, her hücre "etiket + değer" satırına
           döner; etiket data-label'dan (alanın kendi etiketi) gelir. */
        @media screen and (max-width: 782px) {
            .qrm-sub-tab { flex:1 1 auto; justify-content:center; min-height:44px; }
            .qrm-sub-toolbar { flex-direction:column; align-items:stretch; }

            .qrm-sub-table, .qrm-sub-table tbody, .qrm-sub-table tr, .qrm-sub-table td { display:block; width:auto; }
            .qrm-sub-table thead { display:none; }
            .qrm-sub-table tr { background:#fff; border:1px solid #dcdcde; border-radius:8px; margin-bottom:12px; padding:6px 4px; }
            .qrm-sub-table td { border:0; padding:7px 12px; }
            .qrm-sub-table td::before { content:attr(data-label); display:block; font-size:12px; font-weight:600; color:#646970; text-transform:uppercase; margin-bottom:2px; }
            .qrm-sub-table td[data-label=""]::before { display:none; }
            .qrm-sub-row-new td { background:transparent; }
            .qrm-sub-row-new { box-shadow:inset 3px 0 0 #2271b1; }
            .qrm-sub-row-new td:first-child { box-shadow:none; }
            .qrm-sub-actions .button { flex:1 1 auto; }
        }

        @media (pointer: coarse) {
            .qrm-sub-filter, .qrm-sub-tab { min-height:44px; display:inline-flex; align-items:center; }
            .qrm-sub-actions .button { min-height:40px; }
        }
    </style>
    <?php
}
