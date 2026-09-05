<?php
if (!defined('ABSPATH')) exit;

// FORMLAR SAYFASI: FORM DÜZENLEYİCİ GÖRÜNÜMÜ (v4.2.0)
//
// v4.2.1: Düzenleyici artık ayrı bir admin sayfası değil, kayıtlı "Formlar"
// sayfasının bir görünümü (?page=qrms-yf-formlar&view=edit). Gizli admin sayfası deseni
// WordPress'in hook adı çözümlemesini bozup 403'e yol açıyordu (bkz. menu.php).
//
// Sistem formları (Ana Yorum / İletişim) aynı düzenleyici görünümüdür:
// ?page=qrms-yf-formlar&view=edit&system=review|contact
// Veri wp_qrm_custom_forms'tan değil, wp_qrm_form_fields + qrm_settings'ten gelir.
//
// Alan listesi tek bir gizli JSON alanında (qrm_cf_fields_json) taşınır: sürükle-bırak
// sıralamasından sonra input adlarını yeniden numaralandırmak gerekmez, sunucu tarafı
// da tek bir yerde (qrm_cf_replace_fields) temizlik yapar.

function qrm_cf_admin_form_editor_view() {
    if (!current_user_can('manage_options')) {
        wp_die('Bu sayfayı görüntüleme yetkiniz yok.');
    }

    $system = isset($_GET['system']) ? sanitize_key(wp_unslash($_GET['system'])) : '';
    if ($system !== '' && !qrm_pro_is_system_form($system)) {
        $system = '';
    }

    $form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
    $notices = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qrm_cf_save_form'])) {
        check_admin_referer('qrm_cf_save_form');
        if ($system !== '') {
            $result  = qrm_cf_admin_handle_system_form_save($system);
            $notices = $result['notices'];
        } else {
            $result  = qrm_cf_admin_handle_editor_save();
            $notices = $result['notices'];
            if ($result['form_id'] > 0) $form_id = $result['form_id'];
        }
    }

    $qrm_settings = qrm_pro_get_settings();
    $is_system    = ($system !== '');
    $is_new       = false;

    if ($is_system) {
        $sys_meta = qrm_pro_get_system_form($system);
        $form = (object) [
            'id'          => 0,
            'form_key'    => $system,
            'title'       => ($system === 'contact')
                ? $qrm_settings['contact_form_title']
                : $qrm_settings['form_title'],
            'description' => $sys_meta['desc'],
            'status'      => 'active',
            'settings'    => '',
        ];
        $fields = qrm_pro_get_review_form_fields();
        $s      = qrm_cf_get_form_settings($form);
        $s['step_labels'] = qrm_pro_get_review_form_layout($qrm_settings)['step_labels'];
        $types  = qrm_cf_field_types($system === 'review');
        $state  = qrm_pro_review_fields_to_builder_state($fields, $qrm_settings, $system);
    } else {
        $form = $form_id > 0 ? qrm_cf_get_form($form_id) : null;
        $is_new = !$form;

        if ($is_new) {
            $form = (object) [
                'id' => 0, 'form_key' => '', 'title' => '', 'description' => '',
                'status' => 'active', 'settings' => '',
            ];
            $fields = [];
        } else {
            $fields = qrm_cf_get_fields($form->id);
        }

        $s     = qrm_cf_get_form_settings($form);
        $types = qrm_cf_field_types(false);

        $state = [];
        foreach ($fields as $f) {
            $state[] = [
                'key'      => $f->field_key,
                'label'    => $f->label,
                'type'     => $f->field_type,
                'required'      => (int) $f->is_required,
                'options'       => qrm_cf_field_options($f),
                'column_width'  => qrm_pro_field_column_width($f, 'custom'),
                'step_no'       => qrm_pro_sanitize_step_no(isset($f->step_no) ? $f->step_no : 1),
            ];
        }
    }

    qrm_cf_admin_styles();
    qrm_cf_copy_script();
    qrm_cf_admin_builder_styles();
    ?>
    <div class="wrap qrm-cf-wrap qrm-fb-wrap" id="qrm-fb-wrap">
        <?php foreach ($notices as $n): ?>
            <div class="notice notice-<?php echo esc_attr($n['type']); ?> is-dismissible"><p><?php echo wp_kses_post($n['text']); ?></p></div>
        <?php endforeach; ?>
        <?php
        if (!$is_new && function_exists('rma_ceviri_veri_dil_sayisi')) {
            $diller = rma_ceviri_veri_dil_sayisi('cf_form', (int) $form->id, '');
            foreach (array('title', 'description', 'submit_text', 'success_message') as $cf_field) {
                $n = rma_ceviri_veri_dil_sayisi('cf_form', (int) $form->id, $cf_field);
                if ($n > $diller) {
                    $diller = $n;
                }
            }
            foreach ($fields as $f) {
                $n = rma_ceviri_veri_dil_sayisi('cf_field', (int) $f->id, 'label');
                if ($n > $diller) {
                    $diller = $n;
                }
                if (function_exists('qrm_cf_field_options')) {
                    foreach (qrm_cf_field_options($f) as $idx => $opt) {
                        $on = rma_ceviri_veri_dil_sayisi('cf_field', (int) $f->id, 'option_' . (int) $idx);
                        if ($on > $diller) {
                            $diller = $on;
                        }
                    }
                }
            }
            echo rma_ceviri_bayat_uyari_html(rma_ceviri_bayat_uyari_ekran_metni($diller));
        }
        ?>

        <form method="post" id="qrm-fb-form">
            <?php wp_nonce_field('qrm_cf_save_form'); ?>
            <input type="hidden" name="qrm_cf_form_id" value="<?php echo intval($form->id); ?>">
            <?php if ($is_system): ?>
                <input type="hidden" name="qrm_cf_system" value="<?php echo esc_attr($system); ?>">
            <?php endif; ?>
            <input type="hidden" name="qrm_cf_fields_json" id="qrm-fb-fields-json" value="">

            <!-- ÜST SABİT ÇUBUK -->
            <div class="qrm-fb-topbar">
                <div class="qrm-fb-topbar-left">
                    <a href="<?php echo esc_url(qrm_cf_admin_url()); ?>" class="qrm-fb-back" title="Form listesine dön">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                    </a>
                    <div>
                        <strong id="qrm-fb-topbar-title"><?php
                            if ($is_system) {
                                echo esc_html(qrm_pro_get_system_form($system)['title']);
                            } else {
                                echo $is_new ? 'Yeni Form' : esc_html($form->title);
                            }
                        ?></strong>
                        <?php if ($is_system): ?>
                            <div class="qrm-fb-topbar-shortcode">
                                <code id="qrm-fb-shortcode"><?php echo esc_html(qrm_pro_get_system_form($system)['shortcode']); ?></code>
                                <button type="button" class="qrm-cf-copy" data-copy="<?php echo esc_attr(qrm_pro_get_system_form($system)['shortcode']); ?>">Kopyala</button>
                                <span class="qrm-cf-badge qrm-cf-badge-system">Sistem Formu</span>
                            </div>
                        <?php elseif (!$is_new): ?>
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
                    <h2 class="qrm-fb-panel-title"><?php echo $is_system ? 'Widget / Alan' : 'Alan Ekle'; ?></h2>
                    <p class="qrm-cf-sub" style="margin:0 0 14px;"><?php
                        echo $is_system
                            ? esc_html__('Sabit alanlar listede durur. Ana Yorum Formu\'nda puanlama ve Google/ödül widget\'larını ekleyebilirsiniz.', 'qrms')
                            : esc_html__('Eklemek için bir alan tipine tıklayın.', 'qrms');
                    ?></p>
                    <div class="qrm-fb-palette-grid">
                        <?php foreach ($types as $type => $meta):
                            if (!empty($meta['is_system_only']) && $system !== 'review') {
                                continue;
                            }
                            if ($is_system && $system === 'contact' && !empty($meta['is_system_only'])) {
                                continue;
                            }
                            if ($is_system && empty($meta['is_widget'])) {
                                continue;
                            }
                        ?>
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
                        <input type="text" id="qrm-fb-title" name="qrm_cf_title" value="<?php echo esc_attr($form->title); ?>" placeholder="Örn: Şikayet Formu" <?php echo $is_system && $system === 'review' ? '' : 'required'; ?>>
                    </div>

                    <?php if (!$is_system): ?>
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
                    <?php elseif ($system === 'contact'): ?>
                    <p class="qrm-fb-help" style="margin-top:0;">
                        Alanlar <strong>Ana Yorum Formu</strong> ile ortaktır. Burada yalnızca iletişim formu başlığı kaydedilir.
                    </p>
                    <?php endif; ?>

                    <?php if (!$is_system): ?>
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
                    <?php endif; ?>

                    <?php if ($is_system && $system === 'review'): ?>
                    <p class="qrm-fb-help">
                        Puanlama kriterlerinin adı ve aktifliği
                        <a href="<?php echo esc_url(qrm_pro_admin_url('qrms-yf-ayarlar')); ?>">Ayarlar &amp; Puanlama</a>
                        sayfasındadır. Google / ödül metinleri
                        <a href="<?php echo esc_url(qrm_pro_admin_url('qrms-yf-odul')); ?>">Google &amp; Ödül Sistemi</a>
                        sayfasındadır. Burada yalnızca konum ve sıra belirlenir.
                    </p>
                    <?php endif; ?>

                    <h3 class="qrm-fb-section">Adımlar</h3>
                    <p class="qrm-fb-help" style="margin-top:0;">İstediğiniz kadar adım ekleyin; alanları adımlar arasında sürükleyin. Tek adımda stepper gösterilmez.</p>
                    <div id="qrm-fb-step-labels">
                        <?php
                        $saved_step_labels = isset($s['step_labels']) && is_array($s['step_labels']) ? $s['step_labels'] : [];
                        $step_count = 1;
                        foreach ($state as $st_item) {
                            $sn = isset($st_item['step_no']) ? (int) $st_item['step_no'] : 1;
                            if ($sn > $step_count) $step_count = $sn;
                        }
                        foreach ($saved_step_labels as $sn => $_lbl) {
                            if ((int) $sn > $step_count) $step_count = (int) $sn;
                        }
                        $step_count = max(1, min(qrm_pro_max_step_no(), $step_count));
                        for ($si = 1; $si <= $step_count; $si++):
                        ?>
                        <div class="qrm-fb-field qrm-fb-step-label-row" data-step="<?php echo (int) $si; ?>">
                            <label for="qrm-fb-step-label-<?php echo $si; ?>"><?php echo (int) $si; ?>. Adım etiketi</label>
                            <input type="text" id="qrm-fb-step-label-<?php echo $si; ?>" name="qrm_cf_settings[step_labels][<?php echo $si; ?>]"
                                   value="<?php echo esc_attr(isset($saved_step_labels[$si]) ? $saved_step_labels[$si] : ''); ?>"
                                   placeholder="<?php echo esc_attr(sprintf('%d. Adım', $si)); ?>">
                        </div>
                        <?php endfor; ?>
                    </div>
                    <p>
                        <button type="button" class="button" id="qrm-fb-add-step">Adım ekle</button>
                        <button type="button" class="button" id="qrm-fb-remove-step">Son adımı sil</button>
                    </p>

                    <?php if (!$is_system): ?>
                    <h3 class="qrm-fb-section">Güvenlik</h3>
                    <p class="qrm-fb-help" style="margin-top:0;">
                        Bu formlarda <strong>honeypot</strong> (bot tuzağı) ve <strong>zaman tabanlı</strong> spam koruması
                        her zaman açıktır; ayrıca aynı IP'den kısa sürede gelen aşırı gönderimler sınırlanır.
                        Ziyaretçiye ek bir güvenlik sorusu sorulmaz.
                    </p>
                    <?php endif; ?>
                </aside>
            </div>
        </form>
    </div>

    <?php
    qrm_cf_admin_builder_script($state, $types, [
        'system'    => $system,
        'max_steps' => qrm_pro_max_step_no(),
    ]);
    ?>
    <?php
}

/**
 * Sistem formu (review|contact) düzenleyici POST'u.
 *
 * Alan kaydı qrm_pro_save_review_form_fields() / başlık kaydı
 * qrm_pro_save_contact_form_title() ile yapılır — kopyalanmaz, çağrılır.
 *
 * @param string $system review|contact
 * @return array{form_id:int,notices:array}
 */
function qrm_cf_admin_handle_system_form_save($system) {
    $notices = [];

    $raw_fields = isset($_POST['qrm_cf_fields_json']) ? wp_unslash($_POST['qrm_cf_fields_json']) : '';
    $fields     = json_decode($raw_fields, true);
    if (!is_array($fields)) {
        $fields = [];
    }

    $parsed = qrm_pro_builder_state_to_review_save($fields);
    $title  = isset($_POST['qrm_cf_title']) ? wp_unslash($_POST['qrm_cf_title']) : '';

    $step_labels = [];
    if (isset($_POST['qrm_cf_settings']['step_labels']) && is_array($_POST['qrm_cf_settings']['step_labels'])) {
        foreach (wp_unslash($_POST['qrm_cf_settings']['step_labels']) as $n => $label) {
            $n = (int) $n;
            if ($n < 1) {
                continue;
            }
            $step_labels[qrm_pro_sanitize_step_no($n)] = sanitize_text_field($label);
        }
    }
    $parsed['layout']['step_labels'] = $step_labels;

    if ($system === 'contact') {
        qrm_pro_save_contact_form_title($title);
        $notices[] = ['type' => 'success', 'text' => 'İletişim formu ayarları kaydedildi. Alanlar Ana Yorum Formu ile ortaktır.'];
        return ['form_id' => 0, 'notices' => $notices];
    }

    $saved = qrm_pro_save_review_form_fields($parsed['rows']);
    qrm_pro_save_review_form_layout($parsed['layout'], $step_labels);

    if ($title !== '') {
        $settings = qrm_pro_get_settings();
        $settings['form_title'] = sanitize_text_field($title);
        update_option('qrm_settings', $settings);
    }

    $notices[] = [
        'type' => 'success',
        'text' => sprintf('%d form alanı güncellendi. Kısa kod: <code>[qr_menu_reviews]</code>', (int) $saved),
    ];
    return ['form_id' => 0, 'notices' => $notices];
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
            'is_required'  => !empty($f['required']) ? 1 : 0,
            'options'      => isset($f['options']) ? $f['options'] : [],
            'column_width' => isset($f['column_width']) ? $f['column_width'] : 'full',
            'step_no'      => qrm_pro_sanitize_step_no(isset($f['step_no']) ? $f['step_no'] : 1),
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

        .qrm-fb-items-grid { display:flex; flex-wrap:wrap; gap:0 12px; }
        #qrm-fb-items { display:flex; flex-wrap:wrap; gap:0 12px; }
        .qrm-fb-item { position:relative; border:1px solid transparent; border-radius:10px; padding:8px 10px 2px; margin-bottom:6px; transition:border-color .15s ease, background .15s ease, opacity .15s ease; flex:1 1 100%; min-width:0; }
        .qrm-fb-item.is-half { flex:1 1 calc(50% - 6px); }
        .qrm-fb-item:hover { border-color:#cbd5e1; background:rgba(148,163,184,.06); }
        .qrm-fb-item.dragging { opacity:.4; }
        .qrm-fb-item.drop-target { border-top:2px solid #2271b1; }
        .qrm-fb-item-bar { display:flex; align-items:center; gap:6px; margin-bottom:4px; opacity:0; transition:opacity .15s ease; }
        .qrm-fb-item:hover .qrm-fb-item-bar, .qrm-fb-item.editing .qrm-fb-item-bar { opacity:1; }
        .qrm-fb-drag { cursor:grab; color:#94a3b8; }
        .qrm-fb-drag:active { cursor:grabbing; }
        .qrm-fb-icon-btn[disabled] { opacity:.35; cursor:default; }
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

        .qrm-fb-step-group { width:100%; margin-bottom:18px; min-height:56px; padding:6px; border:1px dashed transparent; border-radius:10px; }
        .qrm-fb-step-group.is-drop { border-color:#2271b1; background:rgba(34,113,177,.04); }
        .qrm-fb-step-group-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#64748b; margin:0 0 10px; padding-bottom:6px; border-bottom:1px dashed #e2e8f0; }
        .qrm-fb-step-empty { font-size:12.5px; color:#94a3b8; padding:10px 4px; }
        .qrm-fb-widget-preview { background:#fffbeb; border:1px dashed #f59e0b; border-radius:10px; padding:12px 14px; font-size:13px; color:#92400e; }
        .qrm-fb-type-card[data-disabled="1"] { opacity:.4; pointer-events:none; }
        .qrm-cf-badge-system { background:#dbeafe; color:#1e40af; }
        .qrm-fb-empty-fields { text-align:center; padding:34px 16px; color:#94a3b8; font-size:13.5px; border:2px dashed #e2e8f0; border-radius:12px; margin-bottom:14px; }

        /* Alan düzenleme paneli */
        .qrm-fb-edit-panel { background:#f8fafc; border:1px solid #e2e8f0; border-radius:9px; padding:13px; margin:6px 0 10px; }
        .qrm-fb-edit-panel label { display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px; }
        .qrm-fb-edit-panel input[type=text], .qrm-fb-edit-panel textarea, .qrm-fb-edit-panel select { width:100%; margin-bottom:10px; }
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

        /* Mobil: üç panel ≤1100px'te zaten alt alta iner (yukarıda); burada
           sadece dar ekran boşlukları ve dokunmatik hedef boyutları ayarlanır. */
        @media screen and (max-width:782px) {
            .qrm-fb-wrap { margin-right:10px; }
            .qrm-fb-topbar { align-items:stretch; flex-direction:column; }
            .qrm-fb-topbar .button, .qrm-fb-topbar .qrm-cf-btn-primary { justify-content:center; width:100%; }
            .qrm-fb-palette-grid { grid-template-columns:1fr; }
            .qrm-fb-row { flex-direction:column; }
            .qrm-fb-item.is-half { flex:1 1 100%; }
        }

        @media (pointer: coarse) {
            .qrm-fb-wrap .button, .qrm-fb-wrap .qrm-cf-btn-primary { min-height:44px; }
            .qrm-fb-wrap input[type=text], .qrm-fb-wrap input[type=email], .qrm-fb-wrap input[type=number],
            .qrm-fb-wrap select, .qrm-fb-wrap textarea { font-size:16px; min-height:44px; }
            .qrm-fb-wrap input[type=checkbox], .qrm-fb-wrap input[type=radio] { height:24px; width:24px; }
            /* Alanları sürükleme tutamacı parmakla yakalanabilir olmalı. */
            /* Tutamak masaüstünde yalnızca hover'da beliriyor; dokunmatikte hover
               yok, bu yüzden her zaman görünür ve parmakla yakalanabilir olmalı. */
            .qrm-fb-item-bar { min-height:44px; opacity:1; }
            .qrm-fb-drag { padding:10px; touch-action:none; }
        }
    </style>
    <?php
}

/** Düzenleyici JS'i: alan ekleme, sürükle-bırak sıralama, düzenleme, canlı önizleme. */
function qrm_cf_admin_builder_script($state, $types, $ctx = []) {
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
        var CTX    = <?php echo wp_json_encode(wp_parse_args($ctx, [
            'system'       => '',
            'max_steps'    => function_exists('qrm_pro_max_step_no') ? qrm_pro_max_step_no() : 12,
            'settings_url' => admin_url('admin.php?page=qrms-yf-ayarlar'),
            'reward_url'   => admin_url('admin.php?page=qrms-yf-odul'),
        ]), $qrm_json_flags); ?>;
        var maxSteps   = parseInt(CTX.max_steps, 10) || 12;
        var systemForm = CTX.system || '';
        var stepCount  = 1;

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
        var keyTouched = !!(keyInput && keyInput.value !== '');

        function isWidget(field) {
            return !!(field && (field.widget || (TYPES[field.type] && TYPES[field.type].is_widget)));
        }
        function computeStepCount() {
            var max = 1;
            fields.forEach(function(f) {
                var sn = parseInt(f.step_no, 10) || 1;
                if (sn > max) max = sn;
            });
            var wrapLabels = document.getElementById('qrm-fb-step-labels');
            if (wrapLabels) {
                wrapLabels.querySelectorAll('[data-step]').forEach(function(el) {
                    var n = parseInt(el.getAttribute('data-step'), 10);
                    if (n > max) max = n;
                });
            }
            if (max > maxSteps) max = maxSteps;
            if (max < 1) max = 1;
            return max;
        }
        function syncStepLabelInputs() {
            var wrapLabels = document.getElementById('qrm-fb-step-labels');
            if (!wrapLabels) return;
            var existing = {};
            wrapLabels.querySelectorAll('.qrm-fb-step-label-row input').forEach(function(inp) {
                var n = parseInt(inp.id.replace('qrm-fb-step-label-', ''), 10);
                if (n > 0) existing[n] = inp.value;
            });
            var html = '';
            for (var i = 1; i <= stepCount; i++) {
                html += '<div class="qrm-fb-field qrm-fb-step-label-row" data-step="' + i + '">';
                html += '<label for="qrm-fb-step-label-' + i + '">' + i + '. Adım etiketi</label>';
                html += '<input type="text" id="qrm-fb-step-label-' + i + '" name="qrm_cf_settings[step_labels][' + i + ']" value="' + esc(existing[i] || '') + '" placeholder="' + i + '. Adım">';
                html += '</div>';
            }
            wrapLabels.innerHTML = html;
            var addBtn = document.getElementById('qrm-fb-add-step');
            var remBtn = document.getElementById('qrm-fb-remove-step');
            if (addBtn) addBtn.disabled = stepCount >= maxSteps;
            if (remBtn) remBtn.disabled = stepCount <= 1;
        }
        function updatePaletteAvailability() {
            document.querySelectorAll('.qrm-fb-type-card').forEach(function(card) {
                var type = card.getAttribute('data-type');
                var meta = TYPES[type];
                if (meta && meta.is_widget && fields.some(function(f) { return f.type === type; })) {
                    card.setAttribute('data-disabled', '1');
                } else {
                    card.removeAttribute('data-disabled');
                }
            });
        }

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
                case 'rating_group':
                    control = '<div class="qrm-fb-widget-preview">⭐ Puanlama kriterleri bu adımda basılır. İsim/aktiflik <a href="' + esc(CTX.settings_url) + '">Ayarlar &amp; Puanlama</a> sayfasındadır.</div>';
                    break;
                case 'google_reward':
                    control = '<div class="qrm-fb-widget-preview">🎁 Google &amp; ödül paneli bu adımda basılır. Metinler <a href="' + esc(CTX.reward_url) + '">Google &amp; Ödül Sistemi</a> sayfasındadır.</div>';
                    break;
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
            if (isWidget(field)) {
                if (field.type === 'rating_group') {
                    html += '<p class="qrm-fb-help">Puanlama kriterlerinin adı ve aktifliği <a href="' + esc(CTX.settings_url) + '">Ayarlar &amp; Puanlama</a> sayfasındadır. Burada yalnızca adım konumu belirlenir.</p>';
                } else if (field.type === 'google_reward') {
                    html += '<p class="qrm-fb-help">Google / ödül metinleri <a href="' + esc(CTX.reward_url) + '">Google &amp; Ödül Sistemi</a> sayfasındadır. Burada yalnızca adım konumu belirlenir.</p>';
                }
                html += '<label>Form adımı</label>';
                html += '<select data-edit="step_no" data-index="' + index + '">';
                for (var wsn = 1; wsn <= stepCount; wsn++) {
                    html += '<option value="' + wsn + '"' + ((field.step_no || 1) === wsn ? ' selected' : '') + '>' + wsn + '. Adım</option>';
                }
                html += '</select>';
                html += '<button type="button" class="button button-small" data-act="done" data-index="' + index + '">Tamam</button>';
                html += '</div>';
                return html;
            }
            html += '<div class="qrm-fb-edit-row">';
            html += '<div><label>Alan Etiketi</label><input type="text" data-edit="label" data-index="' + index + '" value="' + esc(field.label) + '"' + (field.core ? ' readonly' : '') + '></div>';
            if (!systemForm) {
                html += '<div><label>Alan Anahtarı</label><input type="text" data-edit="key" data-index="' + index + '" value="' + esc(field.key) + '"></div>';
            }
            html += '</div>';
            if (hasOptions) {
                html += '<label>Seçenekler (her satıra bir seçenek)</label>';
                html += '<textarea rows="4" data-edit="options" data-index="' + index + '">' + esc(field.options.join('\n')) + '</textarea>';
            }
            if (!field.core) {
                html += '<label class="qrm-fb-check"><input type="checkbox" data-edit="required" data-index="' + index + '"' + (field.required ? ' checked' : '') + '> Bu alan zorunlu olsun</label>';
            }
            html += '<label>Sütun genişliği</label>';
            html += '<select data-edit="column_width" data-index="' + index + '">';
            html += '<option value="full"' + (field.column_width !== 'half' ? ' selected' : '') + '>Tekli (tam genişlik)</option>';
            html += '<option value="half"' + (field.column_width === 'half' ? ' selected' : '') + '>İkili (yarım genişlik)</option>';
            html += '</select>';
            html += '<label>Form adımı</label>';
            html += '<select data-edit="step_no" data-index="' + index + '">';
            for (var sn = 1; sn <= stepCount; sn++) {
                html += '<option value="' + sn + '"' + ((field.step_no || 1) === sn ? ' selected' : '') + '>' + sn + '. Adım</option>';
            }
            html += '</select>';
            html += '<p class="qrm-fb-help">Masaüstünde yan yana iki alan için İkili seçin. Mobilde hepsi tam genişliğe düşer. Elementor Column Width bu forma uygulanmaz.</p>';
            html += '<button type="button" class="button button-small" data-act="done" data-index="' + index + '">Tamam</button>';
            html += '</div>';
            return html;
        }

        function render() {
            stepCount = computeStepCount();
            syncStepLabelInputs();
            updatePaletteAvailability();

            var groups = {};
            for (var gi = 1; gi <= stepCount; gi++) groups[gi] = [];
            fields.forEach(function(field, index) {
                var sn = parseInt(field.step_no, 10) || 1;
                if (sn < 1) sn = 1;
                if (sn > maxSteps) sn = maxSteps;
                if (!groups[sn]) groups[sn] = [];
                groups[sn].push({ field: field, index: index });
            });
            var stepNums = Object.keys(groups).map(function(k) { return parseInt(k, 10); }).sort(function(a, b) { return a - b; });

            itemsBox.innerHTML = stepNums.map(function(sn) {
                var groupHtml = '<div class="qrm-fb-step-group" data-step="' + sn + '"><div class="qrm-fb-step-group-title">' + sn + '. Adım</div>';
                if (!groups[sn].length) {
                    groupHtml += '<div class="qrm-fb-step-empty">Bu adıma alan sürükleyin.</div>';
                }
                groupHtml += groups[sn].map(function(item) {
                    var field = item.field;
                    var index = item.index;
                var typeLabel = TYPES[field.type] ? TYPES[field.type].label : field.type;
                var html = '<div class="qrm-fb-item' + (editingIndex === index ? ' editing' : '') + (field.column_width === 'half' ? ' is-half' : '') + '" draggable="true" data-index="' + index + '" data-step="' + sn + '">';
                html += '<div class="qrm-fb-item-bar">';
                html += '<span class="dashicons dashicons-menu qrm-fb-drag" title="Sıralamak için sürükleyin"></span>';
                html += '<span class="qrm-fb-item-type">' + esc(typeLabel) + ' · Adım ' + (field.step_no || 1) + '</span>';
                html += '<span class="spacer"></span>';
                html += '<button type="button" class="qrm-fb-icon-btn" data-act="up" data-index="' + index + '" title="Yukarı taşı"' + (index === 0 ? ' disabled' : '') + '><span class="dashicons dashicons-arrow-up-alt2"></span></button>';
                html += '<button type="button" class="qrm-fb-icon-btn" data-act="down" data-index="' + index + '" title="Aşağı taşı"' + (index === fields.length - 1 ? ' disabled' : '') + '><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
                html += '<button type="button" class="qrm-fb-icon-btn" data-act="edit" data-index="' + index + '" title="Düzenle"><span class="dashicons dashicons-edit"></span></button>';
                if (!field.core) {
                    html += '<button type="button" class="qrm-fb-icon-btn danger" data-act="delete" data-index="' + index + '" title="Sil"><span class="dashicons dashicons-trash"></span></button>';
                }
                html += '</div>';
                html += fieldPreviewHtml(field);
                if (editingIndex === index) html += editPanelHtml(field, index);
                html += '</div>';
                return html;
                }).join('');
                groupHtml += '</div>';
                return groupHtml;
            }).join('');

            if (emptyBox) emptyBox.style.display = fields.length ? 'none' : 'block';
            jsonInput.value = JSON.stringify(fields);
        }

        // --- Palet: alan ekleme ---
        document.querySelectorAll('.qrm-fb-type-card').forEach(function(card){
            card.addEventListener('click', function(){
                if (card.getAttribute('data-disabled')) return;
                var type = card.getAttribute('data-type');
                var meta = TYPES[type];
                if (!meta) return;
                if (meta.is_widget && fields.some(function(f){ return f.type === type; })) return;
                var label = meta.label;
                fields.push({
                    key: meta.is_widget ? type : uniqueKey(label, -1),
                    label: label,
                    type: type,
                    required: type === 'rating_group' ? 1 : 0,
                    options: meta.has_options ? defaultOptions() : [],
                    column_width: 'full',
                    step_no: 1,
                    widget: meta.is_widget ? 1 : 0
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
                if (fields[index] && fields[index].core) return;
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
            } else if (act === 'up' || act === 'down') {
                var hedef = act === 'up' ? index - 1 : index + 1;
                if (hedef < 0 || hedef >= fields.length) return;

                var tasinan = fields[index];
                fields[index] = fields[hedef];
                fields[hedef] = tasinan;

                // Açık düzenleme paneli taşınan alanla birlikte gitmeli.
                if (editingIndex === index) editingIndex = hedef;
                else if (editingIndex === hedef) editingIndex = index;

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
            var stepInput = e.target.closest('[data-edit="step_no"]');
            if (stepInput) {
                var sIndex = parseInt(stepInput.getAttribute('data-index'), 10);
                if (!fields[sIndex]) return;
                var sn = parseInt(stepInput.value, 10) || 1;
                fields[sIndex].step_no = Math.max(1, Math.min(maxSteps, sn));
                if (fields[sIndex].step_no > stepCount) stepCount = fields[sIndex].step_no;
                render();
                return;
            }
            var widthInput = e.target.closest('[data-edit="column_width"]');
            if (widthInput) {
                var wIndex = parseInt(widthInput.getAttribute('data-index'), 10);
                if (!fields[wIndex]) return;
                fields[wIndex].column_width = widthInput.value === 'half' ? 'half' : 'full';
                render();
                return;
            }
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
            var group = e.target.closest('.qrm-fb-step-group');
            itemsBox.querySelectorAll('.qrm-fb-item').forEach(function(el){ el.classList.remove('drop-target'); });
            itemsBox.querySelectorAll('.qrm-fb-step-group').forEach(function(el){ el.classList.remove('is-drop'); });
            if (item) item.classList.add('drop-target');
            if (group) group.classList.add('is-drop');
        });
        itemsBox.addEventListener('dragleave', function(e){
            var item = e.target.closest('.qrm-fb-item');
            var group = e.target.closest('.qrm-fb-step-group');
            if (item) item.classList.remove('drop-target');
            if (group) group.classList.remove('is-drop');
        });
        itemsBox.addEventListener('drop', function(e){
            e.preventDefault();
            if (dragIndex === null) return;
            var item = e.target.closest('.qrm-fb-item');
            var group = e.target.closest('.qrm-fb-step-group');
            var moved = fields.splice(dragIndex, 1)[0];
            if (item) {
                var target = parseInt(item.getAttribute('data-index'), 10);
                var targetStep = parseInt(item.getAttribute('data-step'), 10);
                if (targetStep) moved.step_no = targetStep;
                if (target > dragIndex) target -= 1;
                fields.splice(target, 0, moved);
            } else if (group) {
                var gStep = parseInt(group.getAttribute('data-step'), 10) || 1;
                moved.step_no = gStep;
                var insertAt = fields.length;
                for (var i = fields.length - 1; i >= 0; i--) {
                    if ((parseInt(fields[i].step_no, 10) || 1) === gStep) {
                        insertAt = i + 1;
                        break;
                    }
                }
                fields.splice(insertAt, 0, moved);
            } else {
                fields.splice(dragIndex, 0, moved);
            }
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
            itemsBox.querySelectorAll('.qrm-fb-step-group').forEach(function(el){
                el.classList.remove('is-drop');
            });
        });

        var addStepBtn = document.getElementById('qrm-fb-add-step');
        var remStepBtn = document.getElementById('qrm-fb-remove-step');
        if (addStepBtn) addStepBtn.addEventListener('click', function(){
            if (stepCount >= maxSteps) return;
            stepCount += 1;
            render();
        });
        if (remStepBtn) remStepBtn.addEventListener('click', function(){
            if (stepCount <= 1) return;
            var last = stepCount;
            fields.forEach(function(f){
                if ((parseInt(f.step_no, 10) || 1) === last) f.step_no = last - 1;
            });
            stepCount -= 1;
            render();
        });

        // --- Sağ panel: canlı başlık/açıklama/tema güncellemesi ---
        var previewTitle = document.getElementById('qrm-fb-preview-title');
        var previewDesc  = document.getElementById('qrm-fb-preview-desc');
        var showTitle    = document.getElementById('qrm-fb-show-title');

        function syncTitle() {
            if (!previewTitle || !titleInput) return;
            var visible = !showTitle || showTitle.checked;
            previewTitle.style.display = visible ? '' : 'none';
            previewTitle.textContent = titleInput.value || 'Form Başlığı';
            var topTitle = document.getElementById('qrm-fb-topbar-title');
            if (topTitle) topTitle.textContent = titleInput.value || (systemForm ? previewTitle.textContent : 'Yeni Form');
        }

        if (titleInput) titleInput.addEventListener('input', function(){
            syncTitle();
            if (keyInput && !keyTouched) keyInput.value = slugify(titleInput.value);
        });
        if (keyInput) keyInput.addEventListener('input', function(){ keyTouched = true; });
        if (descInput && previewDesc) descInput.addEventListener('input', function(){ previewDesc.textContent = descInput.value; });
        if (showTitle) showTitle.addEventListener('change', syncTitle);

        var submitText = document.getElementById('qrm-fb-submit-text');
        var submitPreview = document.getElementById('qrm-fb-submit-preview');
        if (submitText && submitPreview) {
            submitText.addEventListener('input', function(){
                submitPreview.textContent = submitText.value || 'Gönder';
            });
        }

        var btnColor  = document.getElementById('qrm-fb-btn-color');
        var btnText   = document.getElementById('qrm-fb-btn-text-color');
        var radius    = document.getElementById('qrm-fb-radius');
        var radiusVal = document.getElementById('qrm-fb-radius-val');
        var theme     = document.getElementById('qrm-fb-theme');

        function syncTheme() {
            if (!previewBox || !btnColor || !btnText || !radius || !theme) return;
            var dark = theme.value === 'dark';
            var transparent = theme.value === 'transparent';
            previewBox.style.setProperty('--qrm-btn', btnColor.value);
            previewBox.style.setProperty('--qrm-btn-text', btnText.value);
            previewBox.style.setProperty('--qrm-radius', radius.value + 'px');
            previewBox.style.setProperty('--qrm-bg', dark ? '#1f2937' : (transparent ? '#f0f0f1' : '#ffffff'));
            previewBox.style.setProperty('--qrm-text', dark ? '#f9fafb' : '#1e293b');
            previewBox.style.setProperty('--qrm-border', dark ? '#374151' : '#e2e8f0');
            previewBox.style.setProperty('--qrm-input-bg', dark ? '#111827' : '#ffffff');
            if (radiusVal) radiusVal.textContent = radius.value;
        }
        [btnColor, btnText, radius, theme].forEach(function(el){
            if (!el) return;
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
