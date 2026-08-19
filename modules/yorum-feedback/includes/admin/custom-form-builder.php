<?php
if (!defined('ABSPATH')) exit;

// FORMLAR SAYFASI: FORM DÜZENLEYİCİ GÖRÜNÜMÜ (v4.2.0)
//
// v4.2.1: Düzenleyici artık ayrı bir admin sayfası değil, kayıtlı "Formlar"
// sayfasının bir görünümü (?page=qrm-forms&view=edit). Gizli admin sayfası deseni
// WordPress'in hook adı çözümlemesini bozup 403'e yol açıyordu (bkz. menu.php).
//
// Not: includes/admin/form-builder.php (Müşteri Bilgileri Formu) mevcut yorum
// formunun sabit alanlarını yönetir ve bu modülle ilgisi yoktur.
//
// Alan listesi tek bir gizli JSON alanında (qrm_cf_fields_json) taşınır: sürükle-bırak
// sıralamasından sonra input adlarını yeniden numaralandırmak gerekmez, sunucu tarafı
// da tek bir yerde (qrm_cf_replace_fields) temizlik yapar.

function qrm_cf_admin_form_editor_view() {
    if (!current_user_can('manage_options')) {
        wp_die('Bu sayfayı görüntüleme yetkiniz yok.');
    }

    $form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
    $notices = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qrm_cf_save_form'])) {
        check_admin_referer('qrm_cf_save_form');
        $result  = qrm_cf_admin_handle_editor_save();
        $notices = $result['notices'];
        if ($result['form_id'] > 0) $form_id = $result['form_id'];
    }

    $form = $form_id > 0 ? qrm_cf_get_form($form_id) : null;
    $is_new = !$form;

    if ($is_new) {
        // Kaydedilmemiş yeni form için boş bir taslak nesnesi
        $form = (object) [
            'id' => 0, 'form_key' => '', 'title' => '', 'description' => '',
            'status' => 'active', 'settings' => '',
        ];
        $fields = [];
    } else {
        $fields = qrm_cf_get_fields($form->id);
    }

    $s     = qrm_cf_get_form_settings($form);
    $types = qrm_cf_field_types();

    // JS tarafına aktarılacak alan durumu
    $state = [];
    foreach ($fields as $f) {
        $state[] = [
            'key'      => $f->field_key,
            'label'    => $f->label,
            'type'     => $f->field_type,
            'required' => (int) $f->is_required,
            'options'  => qrm_cf_field_options($f),
        ];
    }

    qrm_cf_admin_styles();
    qrm_cf_copy_script();
    qrm_cf_admin_builder_styles();
    ?>
    <div class="wrap qrm-cf-wrap qrm-fb-wrap" id="qrm-fb-wrap">
        <?php foreach ($notices as $n): ?>
            <div class="notice notice-<?php echo esc_attr($n['type']); ?> is-dismissible"><p><?php echo wp_kses_post($n['text']); ?></p></div>
        <?php endforeach; ?>

        <form method="post" id="qrm-fb-form">
            <?php wp_nonce_field('qrm_cf_save_form'); ?>
            <input type="hidden" name="qrm_cf_form_id" value="<?php echo intval($form->id); ?>">
            <input type="hidden" name="qrm_cf_fields_json" id="qrm-fb-fields-json" value="">

            <!-- ÜST SABİT ÇUBUK -->
            <div class="qrm-fb-topbar">
                <div class="qrm-fb-topbar-left">
                    <a href="<?php echo esc_url(qrm_cf_admin_url()); ?>" class="qrm-fb-back" title="Form listesine dön">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                    </a>
                    <div>
                        <strong id="qrm-fb-topbar-title"><?php echo $is_new ? 'Yeni Form' : esc_html($form->title); ?></strong>
                        <?php if (!$is_new): ?>
                            <div class="qrm-fb-topbar-shortcode">
                                <code id="qrm-fb-shortcode"><?php echo esc_html(qrm_cf_form_shortcode($form)); ?></code>
                                <button type="button" class="qrm-cf-copy" data-copy="<?php echo esc_attr(qrm_cf_form_shortcode($form)); ?>">Kopyala</button>
                                <?php if ($form->status !== 'active'): ?>
                                    <span class="qrm-cf-badge qrm-cf-badge-draft">Bu form taslak, yayına almak için Aktif yapın</span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="qrm-fb-topbar-shortcode"><span class="qrm-cf-sub">Kısa kod, formu ilk kez kaydettiğinizde burada görünecek.</span></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="qrm-fb-topbar-right">
                    <button type="button" class="button" id="qrm-fb-preview-toggle">
                        <span class="dashicons dashicons-visibility"></span> Önizle
                    </button>
                    <button type="submit" class="qrm-cf-btn-primary" name="qrm_cf_save_form" value="1">
                        <span class="dashicons dashicons-saved"></span> Kaydet
                    </button>
                </div>
            </div>

            <div class="qrm-fb-grid">
                <!-- SOL: ALAN PALETİ -->
                <aside class="qrm-fb-panel qrm-fb-palette">
                    <h2 class="qrm-fb-panel-title">Alan Ekle</h2>
                    <p class="qrm-cf-sub" style="margin:0 0 14px;">Eklemek için bir alan tipine tıklayın.</p>
                    <div class="qrm-fb-palette-grid">
                        <?php foreach ($types as $type => $meta): ?>
                            <button type="button" class="qrm-fb-type-card" data-type="<?php echo esc_attr($type); ?>">
                                <span class="dashicons <?php echo esc_attr($meta['icon']); ?>"></span>
                                <span class="qrm-fb-type-name"><?php echo esc_html($meta['label']); ?></span>
                                <span class="qrm-fb-type-hint"><?php echo esc_html($meta['hint']); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </aside>

                <!-- ORTA: CANLI ÖNİZLEME -->
                <main class="qrm-fb-canvas">
                    <p class="qrm-fb-preview-note">Önizleme modu — formun ziyaretçiye görüneceği hâlin yaklaşık karşılığı. Düzenlemeye dönmek için üstteki butonu kullanın.</p>
                    <div class="qrm-fb-form-preview" id="qrm-fb-preview-box">
                        <h3 id="qrm-fb-preview-title"><?php echo esc_html($form->title !== '' ? $form->title : 'Form Başlığı'); ?></h3>
                        <p class="qrm-fb-preview-desc" id="qrm-fb-preview-desc"><?php echo esc_html($form->description); ?></p>
                        <div id="qrm-fb-items"></div>
                        <div class="qrm-fb-empty-fields" id="qrm-fb-empty-fields">
                            <span class="dashicons dashicons-arrow-left-alt"></span>
                            Soldaki paletten alan ekleyerek formunuzu oluşturmaya başlayın.
                        </div>
                        <button type="button" class="qrm-fb-submit-preview" id="qrm-fb-submit-preview" disabled><?php echo esc_html($s['submit_text']); ?></button>
                    </div>
                </main>

                <!-- SAĞ: FORM AYARLARI -->
                <aside class="qrm-fb-panel qrm-fb-settings">
                    <h2 class="qrm-fb-panel-title">Form Ayarları</h2>

                    <div class="qrm-fb-field">
                        <label for="qrm-fb-title">Form Başlığı</label>
                        <input type="text" id="qrm-fb-title" name="qrm_cf_title" value="<?php echo esc_attr($form->title); ?>" placeholder="Örn: Şikayet Formu" required>
                    </div>

                    <div class="qrm-fb-field">
                        <label for="qrm-fb-desc">Açıklama</label>
                        <textarea id="qrm-fb-desc" name="qrm_cf_description" rows="3" placeholder="Formun üstünde görünecek kısa açıklama (opsiyonel)"><?php echo esc_textarea($form->description); ?></textarea>
                    </div>

                    <div class="qrm-fb-field">
                        <label for="qrm-fb-key">Kısa Kod Anahtarı</label>
                        <input type="text" id="qrm-fb-key" name="qrm_cf_form_key" value="<?php echo esc_attr($form->form_key); ?>" placeholder="sikayet-formu">
                        <p class="qrm-fb-help">Başlıktan otomatik üretilir. Aynı anahtar başka bir formda kullanılıyorsa sonuna numara eklenir.</p>
                    </div>

                    <div class="qrm-fb-field">
                        <label for="qrm-fb-status">Durum</label>
                        <select id="qrm-fb-status" name="qrm_cf_status">
                            <?php foreach (['active', 'draft', 'archived'] as $st): ?>
                                <option value="<?php echo esc_attr($st); ?>" <?php selected($form->status, $st); ?>><?php echo esc_html(qrm_cf_status_label($st)); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="qrm-fb-help">Yalnızca <strong>Aktif</strong> formlar ziyaretçilere gösterilir.</p>
                    </div>

                    <h3 class="qrm-fb-section">Gönderim</h3>

                    <div class="qrm-fb-field">
                        <label for="qrm-fb-submit-text">Gönder Butonu Metni</label>
                        <input type="text" id="qrm-fb-submit-text" name="qrm_cf_settings[submit_text]" value="<?php echo esc_attr($s['submit_text']); ?>">
                    </div>

                    <div class="qrm-fb-field">
                        <label for="qrm-fb-success">Başarı Mesajı</label>
                        <textarea id="qrm-fb-success" name="qrm_cf_settings[success_message]" rows="2"><?php echo esc_textarea($s['success_message']); ?></textarea>
                    </div>

                    <div class="qrm-fb-field">
                        <label class="qrm-fb-check">
                            <input type="checkbox" name="qrm_cf_settings[notify_enabled]" value="1" <?php checked($s['notify_enabled'], 1); ?>>
                            Her yeni gönderimde bana e-posta gönder
                        </label>
                        <input type="email" name="qrm_cf_settings[notify_email]" value="<?php echo esc_attr($s['notify_email']); ?>" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                        <p class="qrm-fb-help">Boş bırakılırsa site yöneticisi adresine gönderilir.</p>
                    </div>

                    <h3 class="qrm-fb-section">Görünüm</h3>

                    <div class="qrm-fb-field">
                        <label class="qrm-fb-check">
                            <input type="checkbox" id="qrm-fb-show-title" name="qrm_cf_settings[show_title]" value="1" <?php checked($s['show_title'], 1); ?>>
                            Form başlığını sayfada göster
                        </label>
                    </div>

                    <div class="qrm-fb-field">
                        <label for="qrm-fb-theme">Tema</label>
                        <select id="qrm-fb-theme" name="qrm_cf_settings[theme_style]">
                            <option value="light" <?php selected($s['theme_style'], 'light'); ?>>Açık</option>
                            <option value="dark" <?php selected($s['theme_style'], 'dark'); ?>>Koyu</option>
                            <option value="transparent" <?php selected($s['theme_style'], 'transparent'); ?>>Şeffaf (sayfa arkaplanı)</option>
                        </select>
                    </div>

                    <div class="qrm-fb-row">
                        <div class="qrm-fb-field">
                            <label for="qrm-fb-btn-color">Buton Rengi</label>
                            <input type="color" id="qrm-fb-btn-color" name="qrm_cf_settings[btn_color]" value="<?php echo esc_attr($s['btn_color']); ?>">
                        </div>
                        <div class="qrm-fb-field">
                            <label for="qrm-fb-btn-text-color">Buton Yazı Rengi</label>
                            <input type="color" id="qrm-fb-btn-text-color" name="qrm_cf_settings[btn_text_color]" value="<?php echo esc_attr($s['btn_text_color']); ?>">
                        </div>
                    </div>

                    <div class="qrm-fb-field">
                        <label for="qrm-fb-radius">Köşe Yuvarlaklığı: <span id="qrm-fb-radius-val"><?php echo intval($s['border_radius']); ?></span>px</label>
                        <input type="range" id="qrm-fb-radius" name="qrm_cf_settings[border_radius]" min="0" max="40" value="<?php echo intval($s['border_radius']); ?>">
                    </div>

                    <h3 class="qrm-fb-section">Güvenlik</h3>
                    <p class="qrm-fb-help" style="margin-top:0;">
                        Bu formlarda <strong>honeypot</strong> (bot tuzağı) ve <strong>zaman tabanlı</strong> spam koruması
                        her zaman açıktır; ayrıca aynı IP'den kısa sürede gelen aşırı gönderimler sınırlanır.
                        Ziyaretçiye ek bir güvenlik sorusu sorulmaz.
                    </p>
                </aside>
            </div>
        </form>
    </div>

    <?php qrm_cf_admin_builder_script($state, $types); ?>
    <?php
}

/**
 * Düzenleyici POST'unu işler.
 * @return array ['form_id' => int, 'notices' => [['type'=>..,'text'=>..]]]
 */
function qrm_cf_admin_handle_editor_save() {
    $notices = [];
    $form_id = isset($_POST['qrm_cf_form_id']) ? intval($_POST['qrm_cf_form_id']) : 0;

    $raw_fields = isset($_POST['qrm_cf_fields_json']) ? wp_unslash($_POST['qrm_cf_fields_json']) : '';
    $fields     = json_decode($raw_fields, true);
    if (!is_array($fields)) $fields = [];

    // JS tarafındaki anahtar isimleri sunucu tarafı şemasına çevrilir
    // (temizlik ve doğrulama qrm_cf_replace_fields içinde yapılır).
    $normalized = [];
    foreach ($fields as $f) {
        if (!is_array($f)) continue;
        $normalized[] = [
            'field_key'   => isset($f['key']) ? $f['key'] : '',
            'label'       => isset($f['label']) ? $f['label'] : '',
            'field_type'  => isset($f['type']) ? $f['type'] : '',
            'is_required' => !empty($f['required']) ? 1 : 0,
            'options'     => isset($f['options']) ? $f['options'] : [],
        ];
    }

    $result = qrm_cf_save_form([
        'id'          => $form_id,
        'title'       => isset($_POST['qrm_cf_title']) ? wp_unslash($_POST['qrm_cf_title']) : '',
        'description' => isset($_POST['qrm_cf_description']) ? wp_unslash($_POST['qrm_cf_description']) : '',
        'form_key'    => isset($_POST['qrm_cf_form_key']) ? wp_unslash($_POST['qrm_cf_form_key']) : '',
        'status'      => isset($_POST['qrm_cf_status']) ? $_POST['qrm_cf_status'] : 'draft',
        'settings'    => isset($_POST['qrm_cf_settings']) ? wp_unslash($_POST['qrm_cf_settings']) : [],
    ]);

    if (is_wp_error($result)) {
        return ['form_id' => $form_id, 'notices' => [['type' => 'error', 'text' => esc_html($result->get_error_message())]]];
    }

    $form_id = $result;
    $saved   = qrm_cf_replace_fields($form_id, $normalized);
    $form    = qrm_cf_get_form($form_id);

    $text = 'Form kaydedildi (' . intval($saved) . ' alan). Kısa kod: <code>' . esc_html(qrm_cf_form_shortcode($form)) . '</code>';
    if ($form->status !== 'active') {
        $text .= ' — <strong>Bu form ' . esc_html(strtolower(qrm_cf_status_label($form->status))) . ' durumda, yayına almak için Aktif yapın.</strong>';
    }
    $notices[] = ['type' => 'success', 'text' => $text];

    if ($saved === 0) {
        $notices[] = ['type' => 'warning', 'text' => 'Formda hiç alan yok. Ziyaretçilere gösterilebilmesi için en az bir alan ekleyin.'];
    }

    return ['form_id' => $form_id, 'notices' => $notices];
}

/** Düzenleyiciye özgü admin stilleri. */
function qrm_cf_admin_builder_styles() {
    ?>
    <style>
        .qrm-fb-wrap { margin-right:20px; }
        .qrm-fb-topbar { position:sticky; top:32px; z-index:20; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;
            background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px 16px; margin:16px 0 20px; box-shadow:0 2px 10px rgba(0,0,0,.07); }
        @media screen and (max-width:782px) { .qrm-fb-topbar { top:46px; } }
        .qrm-fb-topbar-left { display:flex; align-items:center; gap:12px; min-width:0; }
        .qrm-fb-topbar-left strong { font-size:16px; }
        .qrm-fb-topbar-right { display:flex; align-items:center; gap:10px; }
        .qrm-fb-topbar-right .dashicons { font-size:16px; width:16px; height:16px; vertical-align:text-bottom; }
        .qrm-fb-back { display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:8px; background:#f1f5f9; color:#475569; text-decoration:none; transition:background .15s ease; }
        .qrm-fb-back:hover { background:#e2e8f0; color:#1e293b; }
        .qrm-fb-topbar-shortcode { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-top:3px; }
        .qrm-fb-topbar-shortcode code { background:#f8fafc; border:1px dashed #94a3b8; border-radius:5px; padding:3px 8px; font-size:12px; color:#334155; }

        .qrm-fb-grid { display:grid; grid-template-columns:270px minmax(0,1fr) 320px; gap:20px; align-items:start; }
        @media (max-width:1400px) { .qrm-fb-grid { grid-template-columns:240px minmax(0,1fr) 300px; } }
        @media (max-width:1100px) { .qrm-fb-grid { grid-template-columns:1fr; } }

        .qrm-fb-panel { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,.06); position:sticky; top:130px; max-height:calc(100vh - 160px); overflow-y:auto; }
        @media (max-width:1100px) { .qrm-fb-panel { position:static; max-height:none; } }
        .qrm-fb-panel-title { margin:0 0 4px; font-size:14px; text-transform:uppercase; letter-spacing:.5px; color:#475569; }
        .qrm-fb-section { margin:22px 0 12px; padding-top:14px; border-top:1px solid #f1f5f9; font-size:13px; text-transform:uppercase; letter-spacing:.5px; color:#475569; }

        /* Palet */
        .qrm-fb-palette-grid { display:grid; grid-template-columns:1fr 1fr; gap:9px; }
        .qrm-fb-type-card { display:flex; flex-direction:column; align-items:center; text-align:center; gap:3px; padding:12px 7px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:9px; cursor:pointer; transition:all .16s ease; }
        .qrm-fb-type-card:hover { background:#fff; border-color:#2271b1; transform:translateY(-2px); box-shadow:0 5px 14px rgba(34,113,177,.15); }
        .qrm-fb-type-card .dashicons { color:#2271b1; font-size:20px; width:20px; height:20px; }
        .qrm-fb-type-name { font-size:12.5px; font-weight:600; color:#1e293b; line-height:1.25; }
        .qrm-fb-type-hint { font-size:10.5px; color:#94a3b8; line-height:1.3; }

        /* Canlı önizleme */
        .qrm-fb-canvas { min-width:0; }
        .qrm-fb-form-preview { --qrm-btn:#10b981; --qrm-btn-text:#fff; --qrm-radius:10px; --qrm-bg:#fff; --qrm-text:#1e293b; --qrm-border:#e2e8f0; --qrm-input-bg:#fff;
            background:var(--qrm-bg); color:var(--qrm-text); border:1px solid var(--qrm-border); border-radius:16px; padding:28px; box-shadow:0 4px 24px rgba(0,0,0,.05); }
        .qrm-fb-form-preview h3 { margin:0 0 6px; font-size:21px; font-weight:700; color:var(--qrm-text); }
        .qrm-fb-preview-desc { margin:0 0 20px; font-size:14px; line-height:1.6; opacity:.75; }
        .qrm-fb-preview-desc:empty { display:none; }

        .qrm-fb-item { position:relative; border:1px solid transparent; border-radius:10px; padding:8px 10px 2px; margin-bottom:6px; transition:border-color .15s ease, background .15s ease, opacity .15s ease; }
        .qrm-fb-item:hover { border-color:#cbd5e1; background:rgba(148,163,184,.06); }
        .qrm-fb-item.dragging { opacity:.4; }
        .qrm-fb-item.drop-target { border-top:2px solid #2271b1; }
        .qrm-fb-item-bar { display:flex; align-items:center; gap:6px; margin-bottom:4px; opacity:0; transition:opacity .15s ease; }
        .qrm-fb-item:hover .qrm-fb-item-bar, .qrm-fb-item.editing .qrm-fb-item-bar { opacity:1; }
        .qrm-fb-drag { cursor:grab; color:#94a3b8; }
        .qrm-fb-drag:active { cursor:grabbing; }
        .qrm-fb-item-type { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#94a3b8; }
        .qrm-fb-item-bar .spacer { flex:1; }
        .qrm-fb-icon-btn { background:none; border:none; padding:3px; cursor:pointer; color:#64748b; border-radius:5px; display:inline-flex; transition:background .15s ease, color .15s ease; }
        .qrm-fb-icon-btn:hover { background:#e2e8f0; color:#1e293b; }
        .qrm-fb-icon-btn.danger:hover { background:#fee2e2; color:#b91c1c; }

        /* Önizleme içindeki gerçek görünümlü alanlar */
        .qrm-fb-input-group { margin-bottom:14px; }
        .qrm-fb-input-group > label { display:block; font-weight:600; margin-bottom:7px; font-size:14px; opacity:.9; }
        .qrm-fb-req { color:#ef4444; }
        .qrm-fb-input-group input[type=text], .qrm-fb-input-group input[type=email], .qrm-fb-input-group input[type=tel],
        .qrm-fb-input-group input[type=number], .qrm-fb-input-group input[type=date], .qrm-fb-input-group select, .qrm-fb-input-group textarea {
            width:100%; padding:12px 14px; border:1px solid var(--qrm-border); border-radius:var(--qrm-radius); font-size:14px;
            background:var(--qrm-input-bg); color:var(--qrm-text); font-family:inherit; box-shadow:none; }
        .qrm-fb-choice-list { display:flex; flex-direction:column; gap:7px; }
        .qrm-fb-choice { display:flex; align-items:center; gap:9px; font-size:14px; padding:9px 11px; border:1px solid var(--qrm-border); border-radius:var(--qrm-radius); background:rgba(148,163,184,.07); }
        .qrm-fb-stars { color:#cbd5e1; font-size:26px; letter-spacing:2px; }
        .qrm-fb-stars span { color:#f59e0b; }
        .qrm-fb-no-options { font-size:12.5px; color:#b45309; background:#fffbeb; border:1px solid #fde68a; border-radius:7px; padding:8px 11px; }

        .qrm-fb-submit-preview { width:100%; margin-top:12px; padding:15px 30px; border:none; border-radius:var(--qrm-radius); background:var(--qrm-btn); color:var(--qrm-btn-text);
            font-size:15px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; opacity:.95; cursor:default; }

        .qrm-fb-empty-fields { text-align:center; padding:34px 16px; color:#94a3b8; font-size:13.5px; border:2px dashed #e2e8f0; border-radius:12px; margin-bottom:14px; }

        /* Alan düzenleme paneli */
        .qrm-fb-edit-panel { background:#f8fafc; border:1px solid #e2e8f0; border-radius:9px; padding:13px; margin:6px 0 10px; }
        .qrm-fb-edit-panel label { display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px; }
        .qrm-fb-edit-panel input[type=text], .qrm-fb-edit-panel textarea { width:100%; margin-bottom:10px; }
        .qrm-fb-edit-panel .qrm-fb-check { font-weight:500; font-size:13px; color:#1e293b; }
        .qrm-fb-edit-row { display:flex; gap:12px; flex-wrap:wrap; }
        .qrm-fb-edit-row > div { flex:1 1 160px; }

        /* Sağ panel form alanları */
        .qrm-fb-field { margin-bottom:15px; }
        .qrm-fb-field > label { display:block; font-size:12.5px; font-weight:600; color:#475569; margin-bottom:5px; }
        .qrm-fb-field input[type=text], .qrm-fb-field input[type=email], .qrm-fb-field select, .qrm-fb-field textarea { width:100%; }
        .qrm-fb-field input[type=color] { width:100%; height:36px; padding:2px; border:1px solid #8c8f94; border-radius:4px; background:#fff; cursor:pointer; }
        .qrm-fb-field input[type=range] { width:100%; }
        .qrm-fb-check { display:flex; align-items:flex-start; gap:8px; font-size:13px; line-height:1.4; margin-bottom:8px; }
        .qrm-fb-help { font-size:11.5px; color:#787c82; margin:5px 0 0; line-height:1.5; }
        .qrm-fb-row { display:flex; gap:12px; }
        .qrm-fb-row > .qrm-fb-field { flex:1; }

        /* Önizleme modu: düzenleme arayüzü gizlenir */
        .qrm-fb-previewing .qrm-fb-palette, .qrm-fb-previewing .qrm-fb-settings { display:none; }
        .qrm-fb-previewing .qrm-fb-grid { grid-template-columns:minmax(0,1fr); }
        .qrm-fb-previewing .qrm-fb-item-bar, .qrm-fb-previewing .qrm-fb-edit-panel { display:none !important; }
        .qrm-fb-previewing .qrm-fb-item:hover { border-color:transparent; background:none; }
        .qrm-fb-previewing .qrm-fb-canvas { max-width:720px; margin:0 auto; }
        .qrm-fb-previewing .qrm-fb-empty-fields { display:none; }
        .qrm-fb-preview-note { display:none; text-align:center; font-size:12.5px; color:#646970; margin:0 0 12px; }
        .qrm-fb-previewing .qrm-fb-preview-note { display:block; }
    </style>
    <?php
}

/** Düzenleyici JS'i: alan ekleme, sürükle-bırak sıralama, düzenleme, canlı önizleme. */
function qrm_cf_admin_builder_script($state, $types) {
    ?>
    <?php
    // JSON_HEX_TAG: admin'in girdiği bir etiket metni script kapanış etiketi içerse
    // bile JS bloğundan çıkamaz. (Bu açıklama bilerek PHP yorumu olarak yazıldı:
    // JS yorumu içine yazılan gerçek bir kapanış etiketi, tarayıcının script bloğunu
    // erkenden kapatmasına ve tüm düzenleyici JS'inin ölmesine yol açıyor.)
    $qrm_json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    ?>
    <script>
    (function(){
        var TYPES  = <?php echo wp_json_encode($types, $qrm_json_flags); ?>;
        var fields = <?php echo wp_json_encode($state, $qrm_json_flags); ?>;

        var wrap      = document.getElementById('qrm-fb-wrap');
        var form      = document.getElementById('qrm-fb-form');
        var itemsBox  = document.getElementById('qrm-fb-items');
        var emptyBox  = document.getElementById('qrm-fb-empty-fields');
        var jsonInput = document.getElementById('qrm-fb-fields-json');
        var previewBox = document.getElementById('qrm-fb-preview-box');
        if (!form || !itemsBox) return;

        var titleInput = document.getElementById('qrm-fb-title');
        var descInput  = document.getElementById('qrm-fb-desc');
        var keyInput   = document.getElementById('qrm-fb-key');
        var editingIndex = -1;
        var keyTouched = keyInput.value !== '';

        function esc(str) {
            return String(str === null || str === undefined ? '' : str)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function slugify(text) {
            var map = {'ç':'c','Ç':'c','ğ':'g','Ğ':'g','ı':'i','İ':'i','ö':'o','Ö':'o','ş':'s','Ş':'s','ü':'u','Ü':'u'};
            return String(text).replace(/[çÇğĞıİöÖşŞüÜ]/g, function(c){ return map[c]; })
                .toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 50);
        }

        function uniqueKey(base, skipIndex) {
            var key = slugify(base).replace(/-/g, '_') || 'alan';
            var candidate = key, i = 2;
            var taken = fields.filter(function(f, idx){ return idx !== skipIndex; }).map(function(f){ return f.key; });
            while (taken.indexOf(candidate) !== -1) { candidate = key + '_' + i; i++; }
            return candidate;
        }

        function defaultOptions() { return ['Seçenek 1', 'Seçenek 2', 'Seçenek 3']; }

        // --- Alan önizleme HTML'i (ön yüzdeki qrm_cf_render_field() karşılığı) ---
        function fieldPreviewHtml(field) {
            var req = field.required ? ' <span class="qrm-fb-req">*</span>' : '';
            var label = '<label>' + esc(field.label) + req + '</label>';
            var control = '';

            switch (field.type) {
                case 'textarea': control = '<textarea rows="3" disabled></textarea>'; break;
                case 'email':    control = '<input type="email" placeholder="ornek@eposta.com" disabled>'; break;
                case 'tel':      control = '<input type="tel" placeholder="0 (5__) ___ __ __" disabled>'; break;
                case 'number':   control = '<input type="number" disabled>'; break;
                case 'date':     control = '<input type="date" disabled>'; break;
                case 'select':
                    if (!field.options.length) { control = noOptions(); break; }
                    control = '<select disabled><option>Seçiniz…</option>' + field.options.map(function(o){
                        return '<option>' + esc(o) + '</option>';
                    }).join('') + '</select>';
                    break;
                case 'radio':
                case 'checkbox':
                    if (!field.options.length) { control = noOptions(); break; }
                    control = '<div class="qrm-fb-choice-list">' + field.options.map(function(o){
                        return '<label class="qrm-fb-choice"><input type="' + (field.type === 'radio' ? 'radio' : 'checkbox') + '" disabled><span>' + esc(o) + '</span></label>';
                    }).join('') + '</div>';
                    break;
                case 'rating':   control = '<div class="qrm-fb-stars">★★★★★</div>'; break;
                default:         control = '<input type="text" disabled>';
            }
            return '<div class="qrm-fb-input-group">' + label + control + '</div>';
        }

        function noOptions() {
            return '<div class="qrm-fb-no-options">Bu alan için henüz seçenek girilmedi — kalem ikonuna tıklayıp seçenekleri yazın.</div>';
        }

        function editPanelHtml(field, index) {
            var hasOptions = TYPES[field.type] && TYPES[field.type].has_options;
            var html = '<div class="qrm-fb-edit-panel">';
            html += '<div class="qrm-fb-edit-row">';
            html += '<div><label>Alan Etiketi</label><input type="text" data-edit="label" data-index="' + index + '" value="' + esc(field.label) + '"></div>';
            html += '<div><label>Alan Anahtarı</label><input type="text" data-edit="key" data-index="' + index + '" value="' + esc(field.key) + '"></div>';
            html += '</div>';
            if (hasOptions) {
                html += '<label>Seçenekler (her satıra bir seçenek)</label>';
                html += '<textarea rows="4" data-edit="options" data-index="' + index + '">' + esc(field.options.join('\n')) + '</textarea>';
            }
            html += '<label class="qrm-fb-check"><input type="checkbox" data-edit="required" data-index="' + index + '"' + (field.required ? ' checked' : '') + '> Bu alan zorunlu olsun</label>';
            html += '<button type="button" class="button button-small" data-act="done" data-index="' + index + '">Tamam</button>';
            html += '</div>';
            return html;
        }

        function render() {
            itemsBox.innerHTML = fields.map(function(field, index){
                var typeLabel = TYPES[field.type] ? TYPES[field.type].label : field.type;
                var html = '<div class="qrm-fb-item' + (editingIndex === index ? ' editing' : '') + '" draggable="true" data-index="' + index + '">';
                html += '<div class="qrm-fb-item-bar">';
                html += '<span class="dashicons dashicons-menu qrm-fb-drag" title="Sıralamak için sürükleyin"></span>';
                html += '<span class="qrm-fb-item-type">' + esc(typeLabel) + '</span>';
                html += '<span class="spacer"></span>';
                html += '<button type="button" class="qrm-fb-icon-btn" data-act="edit" data-index="' + index + '" title="Düzenle"><span class="dashicons dashicons-edit"></span></button>';
                html += '<button type="button" class="qrm-fb-icon-btn danger" data-act="delete" data-index="' + index + '" title="Sil"><span class="dashicons dashicons-trash"></span></button>';
                html += '</div>';
                html += fieldPreviewHtml(field);
                if (editingIndex === index) html += editPanelHtml(field, index);
                html += '</div>';
                return html;
            }).join('');

            emptyBox.style.display = fields.length ? 'none' : 'block';
            jsonInput.value = JSON.stringify(fields);
        }

        // --- Palet: alan ekleme ---
        document.querySelectorAll('.qrm-fb-type-card').forEach(function(card){
            card.addEventListener('click', function(){
                var type = card.getAttribute('data-type');
                var meta = TYPES[type];
                if (!meta) return;
                var label = meta.label;
                fields.push({
                    key: uniqueKey(label, -1),
                    label: label,
                    type: type,
                    required: 0,
                    options: meta.has_options ? defaultOptions() : []
                });
                editingIndex = fields.length - 1;
                render();
                var last = itemsBox.lastElementChild;
                if (last) last.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });

        // --- Alan kartı aksiyonları ---
        itemsBox.addEventListener('click', function(e){
            var btn = e.target.closest('[data-act]');
            if (!btn) return;
            var index = parseInt(btn.getAttribute('data-index'), 10);
            var act = btn.getAttribute('data-act');

            if (act === 'delete') {
                if (!confirm('"' + fields[index].label + '" alanı silinsin mi?')) return;
                fields.splice(index, 1);
                if (editingIndex === index) editingIndex = -1;
                else if (editingIndex > index) editingIndex--;
                render();
            } else if (act === 'edit') {
                editingIndex = (editingIndex === index) ? -1 : index;
                render();
            } else if (act === 'done') {
                editingIndex = -1;
                render();
            }
        });

        // --- Alan düzenleme girdileri ---
        itemsBox.addEventListener('input', function(e){
            var input = e.target.closest('[data-edit]');
            if (!input) return;
            var index = parseInt(input.getAttribute('data-index'), 10);
            var what = input.getAttribute('data-edit');
            var field = fields[index];
            if (!field) return;

            if (what === 'label') {
                field.label = input.value;
                var typeName = itemsBox.querySelectorAll('.qrm-fb-item')[index];
                if (typeName) {
                    var lbl = typeName.querySelector('.qrm-fb-input-group > label');
                    if (lbl) lbl.innerHTML = esc(input.value) + (field.required ? ' <span class="qrm-fb-req">*</span>' : '');
                }
            } else if (what === 'key') {
                field.key = input.value;
            } else if (what === 'options') {
                field.options = input.value.split('\n').map(function(o){ return o.trim(); }).filter(function(o){ return o !== ''; });
                var item = itemsBox.querySelectorAll('.qrm-fb-item')[index];
                var group = item ? item.querySelector('.qrm-fb-input-group') : null;
                if (group) group.outerHTML = fieldPreviewHtml(field);
            }
            jsonInput.value = JSON.stringify(fields);
        });

        itemsBox.addEventListener('change', function(e){
            var input = e.target.closest('[data-edit="required"]');
            if (!input) return;
            var index = parseInt(input.getAttribute('data-index'), 10);
            if (!fields[index]) return;
            fields[index].required = input.checked ? 1 : 0;
            render();
        });

        // --- Sürükle-bırak sıralama (native HTML5, ek kütüphane yok) ---
        var dragIndex = null;
        itemsBox.addEventListener('dragstart', function(e){
            var item = e.target.closest('.qrm-fb-item');
            if (!item) return;
            dragIndex = parseInt(item.getAttribute('data-index'), 10);
            item.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', String(dragIndex)); } catch (err) {}
        });
        itemsBox.addEventListener('dragover', function(e){
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            var item = e.target.closest('.qrm-fb-item');
            itemsBox.querySelectorAll('.qrm-fb-item').forEach(function(el){ el.classList.remove('drop-target'); });
            if (item) item.classList.add('drop-target');
        });
        itemsBox.addEventListener('dragleave', function(e){
            var item = e.target.closest('.qrm-fb-item');
            if (item) item.classList.remove('drop-target');
        });
        itemsBox.addEventListener('drop', function(e){
            e.preventDefault();
            var item = e.target.closest('.qrm-fb-item');
            if (dragIndex === null || !item) return;
            var target = parseInt(item.getAttribute('data-index'), 10);
            if (target === dragIndex) { render(); return; }
            var moved = fields.splice(dragIndex, 1)[0];
            fields.splice(target, 0, moved);
            editingIndex = -1;
            dragIndex = null;
            render();
        });
        itemsBox.addEventListener('dragend', function(){
            dragIndex = null;
            itemsBox.querySelectorAll('.qrm-fb-item').forEach(function(el){
                el.classList.remove('dragging');
                el.classList.remove('drop-target');
            });
        });

        // --- Sağ panel: canlı başlık/açıklama/tema güncellemesi ---
        var previewTitle = document.getElementById('qrm-fb-preview-title');
        var previewDesc  = document.getElementById('qrm-fb-preview-desc');
        var showTitle    = document.getElementById('qrm-fb-show-title');

        function syncTitle() {
            var visible = !showTitle || showTitle.checked;
            previewTitle.style.display = visible ? '' : 'none';
            previewTitle.textContent = titleInput.value || 'Form Başlığı';
            var topTitle = document.getElementById('qrm-fb-topbar-title');
            if (topTitle) topTitle.textContent = titleInput.value || 'Yeni Form';
        }

        titleInput.addEventListener('input', function(){
            syncTitle();
            if (!keyTouched) keyInput.value = slugify(titleInput.value);
        });
        keyInput.addEventListener('input', function(){ keyTouched = true; });
        descInput.addEventListener('input', function(){ previewDesc.textContent = descInput.value; });
        if (showTitle) showTitle.addEventListener('change', syncTitle);

        var submitText = document.getElementById('qrm-fb-submit-text');
        var submitPreview = document.getElementById('qrm-fb-submit-preview');
        submitText.addEventListener('input', function(){
            submitPreview.textContent = submitText.value || 'Gönder';
        });

        var btnColor  = document.getElementById('qrm-fb-btn-color');
        var btnText   = document.getElementById('qrm-fb-btn-text-color');
        var radius    = document.getElementById('qrm-fb-radius');
        var radiusVal = document.getElementById('qrm-fb-radius-val');
        var theme     = document.getElementById('qrm-fb-theme');

        function syncTheme() {
            var dark = theme.value === 'dark';
            var transparent = theme.value === 'transparent';
            previewBox.style.setProperty('--qrm-btn', btnColor.value);
            previewBox.style.setProperty('--qrm-btn-text', btnText.value);
            previewBox.style.setProperty('--qrm-radius', radius.value + 'px');
            previewBox.style.setProperty('--qrm-bg', dark ? '#1f2937' : (transparent ? '#f0f0f1' : '#ffffff'));
            previewBox.style.setProperty('--qrm-text', dark ? '#f9fafb' : '#1e293b');
            previewBox.style.setProperty('--qrm-border', dark ? '#374151' : '#e2e8f0');
            previewBox.style.setProperty('--qrm-input-bg', dark ? '#111827' : '#ffffff');
            radiusVal.textContent = radius.value;
        }
        [btnColor, btnText, radius, theme].forEach(function(el){
            el.addEventListener('input', syncTheme);
            el.addEventListener('change', syncTheme);
        });

        // --- Önizleme modu ---
        var previewToggle = document.getElementById('qrm-fb-preview-toggle');
        previewToggle.addEventListener('click', function(){
            var on = wrap.classList.toggle('qrm-fb-previewing');
            previewToggle.innerHTML = on
                ? '<span class="dashicons dashicons-edit"></span> Düzenlemeye Dön'
                : '<span class="dashicons dashicons-visibility"></span> Önizle';
            if (on) editingIndex = -1;
            render();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // --- Kaydetmeden önce alan listesini senkronla ---
        form.addEventListener('submit', function(){
            jsonInput.value = JSON.stringify(fields);
        });

        syncTitle();
        syncTheme();
        render();
    })();
    </script>
    <?php
}
