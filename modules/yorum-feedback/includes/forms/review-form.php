<?php
if (!defined('ABSPATH')) exit;

// ANA YORUM / İLETİŞİM FORMU — VERİ VE SİSTEM FORMU YARDIMCILARI
//
// wp_qrm_reviews ve wp_qrm_form_fields şeması AYNEN kalır. Bu dosya yalnızca
// admin düzenleyicisinin o tabloları + qrm_settings'i okuyup yazması içindir.
// Özel form tablolarına (wp_qrm_custom_forms) satır yazılmaz.
//
// Adım (step_no) ve widget konumları qrm_form_fields'e kolon eklemeden
// qrm_settings['qrm_review_form_layout'] içinde tutulur. Düzenleyici hiç
// kaydedilmemişse düzen boştur ve önyüz eski sabit adım sırasını kullanır
// (canlı siteler kırılmasın).

/**
 * Sistem formlarının sabit tanımı. wp_qrm_custom_forms satırı değildir.
 *
 * @return array<string,array{key:string,title:string,shortcode:string,desc:string}>
 */
function qrm_pro_system_forms() {
    return [
        'review' => [
            'key'       => 'review',
            'title'     => __('Ana Yorum Formu', 'qrms'),
            'shortcode' => '[qr_menu_reviews]',
            'desc'      => __('Yorum formunda müşteriden istenecek bilgi alanları, puanlama ve Google/ödül konumu.', 'qrms'),
        ],
        'contact' => [
            'key'       => 'contact',
            'title'     => __('İletişim Formu', 'qrms'),
            'shortcode' => '[qr_menu_contact]',
            'desc'      => __('İletişim sayfanıza koyacağınız hazır form. Alanları Ana Yorum Formu ile ortaktır.', 'qrms'),
        ],
    ];
}

/**
 * Geçerli bir sistem formu anahtarı mı?
 *
 * @param string $key
 * @return bool
 */
function qrm_pro_is_system_form($key) {
    return array_key_exists((string) $key, qrm_pro_system_forms());
}

/**
 * Tek bir sistem formu tanımı. Bilinmeyen anahtar için null.
 *
 * @param string $key review|contact
 * @return array|null
 */
function qrm_pro_get_system_form($key) {
    $all = qrm_pro_system_forms();
    return isset($all[$key]) ? $all[$key] : null;
}

/**
 * wp_qrm_form_fields satırlarını sort_order sırasında döndürür.
 *
 * @return array<object>
 */
function qrm_pro_get_review_form_fields() {
    global $wpdb;
    $table = $wpdb->prefix . 'qrm_form_fields';
    $rows  = $wpdb->get_results("SELECT * FROM $table ORDER BY sort_order ASC");
    return is_array($rows) ? $rows : [];
}

/**
 * Kayıtlı yorum formu düzeni (adım etiketleri, alan adımları, widget konumları).
 *
 * Boş dizi = düzenleyici hiç kaydedilmemiş; önyüz sabit qrm_pro_build_steps()
 * yolunu kullanır.
 *
 * @param array|null $settings
 * @return array{step_labels:array,field_steps:array,widgets:array}
 */
function qrm_pro_get_review_form_layout($settings = null) {
    if ($settings === null) {
        $settings = qrm_pro_get_settings();
    }
    $raw = isset($settings['qrm_review_form_layout']) ? $settings['qrm_review_form_layout'] : [];
    return qrm_pro_sanitize_review_form_layout($raw);
}

/**
 * Düzen dizisini temizler. Bilinmeyen widget / geçersiz adım atılır.
 *
 * @param mixed $raw
 * @return array{step_labels:array<int,string>,field_steps:array<string,int>,widgets:array<string,int>}
 */
function qrm_pro_sanitize_review_form_layout($raw) {
    $out = [
        'step_labels' => [],
        'field_steps' => [],
        'widgets'     => [],
    ];
    if (!is_array($raw)) {
        return $out;
    }

    if (isset($raw['step_labels']) && is_array($raw['step_labels'])) {
        foreach ($raw['step_labels'] as $n => $label) {
            $n = (int) $n;
            if ($n < 1) {
                continue;
            }
            $n = qrm_pro_sanitize_step_no($n);
            $out['step_labels'][$n] = sanitize_text_field($label);
        }
    }

    if (isset($raw['field_steps']) && is_array($raw['field_steps'])) {
        foreach ($raw['field_steps'] as $key => $step) {
            $key = sanitize_key($key);
            if ($key === '') {
                continue;
            }
            $out['field_steps'][$key] = qrm_pro_sanitize_step_no($step);
        }
    }

    $allowed_widgets = ['rating_group', 'google_reward'];
    if (isset($raw['widgets']) && is_array($raw['widgets'])) {
        foreach ($raw['widgets'] as $type => $step) {
            $type = sanitize_key($type);
            if (!in_array($type, $allowed_widgets, true)) {
                continue;
            }
            $out['widgets'][$type] = qrm_pro_sanitize_step_no($step);
        }
    }

    return $out;
}

/**
 * Düzen kaydedilmiş ve önyüzde kullanılacak kadar dolu mu?
 *
 * Widget ya da alan adımı yoksa "kayıtlı düzen yok" sayılır — canlı siteler
 * eski sabit adım sırasına düşer.
 *
 * @param array $layout
 * @return bool
 */
function qrm_pro_review_form_layout_is_custom(array $layout) {
    return !empty($layout['field_steps']) || !empty($layout['widgets']);
}

/**
 * Alan satırlarından düzenleyici JSON durumunu üretir.
 *
 * Kayıtlı düzen yoksa mevcut önyüz ayrımı kullanılır: textarea → adım 2,
 * diğerleri → adım 3. Puanlama widget'ı (varsa) adım 1'e konur.
 *
 * @param array  $fields   wp_qrm_form_fields satırları
 * @param array  $settings
 * @param string $system   review|contact
 * @return array
 */
function qrm_pro_review_fields_to_builder_state($fields, $settings, $system = 'review') {
    $layout  = qrm_pro_get_review_form_layout($settings);
    $custom  = qrm_pro_review_form_layout_is_custom($layout);
    $state   = [];

    if ($system === 'review') {
        $rating_step = $custom && isset($layout['widgets']['rating_group'])
            ? (int) $layout['widgets']['rating_group']
            : 1;
        $state[] = [
            'key'          => 'rating_group',
            'label'        => 'Puanlama Kriterleri',
            'type'         => 'rating_group',
            'required'     => 1,
            'options'      => [],
            'column_width' => 'full',
            'step_no'      => $rating_step,
            'widget'       => 1,
        ];
        if ($custom && isset($layout['widgets']['google_reward'])) {
            $state[] = [
                'key'          => 'google_reward',
                'label'        => 'Google & Ödül Yönlendirme',
                'type'         => 'google_reward',
                'required'     => 0,
                'options'      => [],
                'column_width' => 'full',
                'step_no'      => (int) $layout['widgets']['google_reward'],
                'widget'       => 1,
            ];
        }
    }

    foreach ((array) $fields as $f) {
        $key = $f->field_key;
        if ($custom && isset($layout['field_steps'][$key])) {
            $step = (int) $layout['field_steps'][$key];
        } else {
            $step = ($f->field_type === 'textarea') ? 2 : 3;
        }
        $state[] = [
            'key'          => $key,
            'label'        => $f->field_label,
            'type'         => $f->field_type,
            'required'     => (int) $f->is_required,
            'active'       => (int) $f->is_active,
            'options'      => [],
            'column_width' => qrm_pro_field_column_width($f, 'review'),
            'step_no'      => qrm_pro_sanitize_step_no($step),
            'db_id'        => (int) $f->id,
            'core'         => ($key === 'comment') ? 1 : 0,
        ];
    }

    usort($state, static function ($a, $b) {
        $sa = isset($a['step_no']) ? (int) $a['step_no'] : 1;
        $sb = isset($b['step_no']) ? (int) $b['step_no'] : 1;
        if ($sa !== $sb) {
            return $sa - $sb;
        }
        return 0;
    });

    return $state;
}

/**
 * Düzenleyici JSON'undan alan satırlarını ve düzeni ayırır.
 *
 * @param array $fields JSON'dan gelen dizi
 * @return array{rows:array,layout:array}
 */
function qrm_pro_builder_state_to_review_save($fields) {
    $rows   = [];
    $layout = [
        'step_labels' => [],
        'field_steps' => [],
        'widgets'     => [],
    ];

    foreach ((array) $fields as $f) {
        if (!is_array($f)) {
            continue;
        }
        $type = isset($f['type']) ? sanitize_key($f['type']) : '';
        $step = qrm_pro_sanitize_step_no(isset($f['step_no']) ? $f['step_no'] : 1);

        if ($type === 'rating_group' || $type === 'google_reward') {
            $layout['widgets'][$type] = $step;
            continue;
        }

        $id = isset($f['db_id']) ? (int) $f['db_id'] : 0;
        if ($id <= 0) {
            continue;
        }

        $key = isset($f['key']) ? sanitize_key($f['key']) : '';
        if ($key !== '') {
            $layout['field_steps'][$key] = $step;
        }

        $rows[$id] = [
            'label'        => isset($f['label']) ? $f['label'] : '',
            'required'     => !empty($f['required']) ? 1 : 0,
            'active'       => !empty($f['active']) ? 1 : (isset($f['active']) ? 0 : 1),
            'column_width' => isset($f['column_width']) ? $f['column_width'] : 'full',
        ];
        // Çekirdek alan (comment) her zaman aktif+zorunlu — save fonksiyonu da zorlar.
        if ($key === 'comment') {
            $rows[$id]['required'] = 1;
            $rows[$id]['active']   = 1;
        }
    }

    return ['rows' => $rows, 'layout' => $layout];
}

/**
 * Yorum formu düzenini qrm_settings'e yazar. Diğer ayarlara dokunmaz.
 *
 * @param array $layout
 * @param array $step_labels İsteğe bağlı; layout['step_labels'] üzerine yazılır.
 * @return void
 */
function qrm_pro_save_review_form_layout(array $layout, $step_labels = []) {
    $settings = qrm_pro_get_settings();
    if (is_array($step_labels) && $step_labels) {
        $layout['step_labels'] = $step_labels;
    }
    $settings['qrm_review_form_layout'] = qrm_pro_sanitize_review_form_layout($layout);
    update_option('qrm_settings', $settings);
}

/**
 * İletişim formu başlığını kaydeder. Eski qrm_pro_admin_contact() kaydının
 * sarmalanmış hâli — başka bir ayara dokunmaz.
 *
 * @param string $title
 * @return string Kaydedilen başlık
 */
function qrm_pro_save_contact_form_title($title) {
    $settings = qrm_pro_get_settings();
    $settings['contact_form_title'] = sanitize_text_field($title);
    update_option('qrm_settings', $settings);
    return $settings['contact_form_title'];
}

/**
 * Müşteri bilgileri form alanlarını kaydeder.
 * Satır sırası = sort_order; POST dizisi sürükle-bırak sonrası DOM sırasında gelir.
 *
 * @param array $rows [id => ['label'=>..,'required'=>..,'active'=>..,'column_width'=>full|half]]
 * @return int Güncellenen alan sayısı
 */
function qrm_pro_save_review_form_fields($rows) {
    global $wpdb;
    $table_fields = $wpdb->prefix . 'qrm_form_fields';

    // 'comment' alanı çekirdek kabul edilir: her zaman aktif ve zorunlu kalır.
    $core_keys = ['comment'];
    $existing = $wpdb->get_results("SELECT id, field_key FROM $table_fields");
    $key_by_id = [];
    foreach ($existing as $row) {
        $key_by_id[(int) $row->id] = $row->field_key;
    }

    $order = 1;
    $saved = 0;

    foreach ((array) $rows as $id => $data) {
        $id = intval($id);
        if ($id <= 0 || !isset($key_by_id[$id])) continue;

        $is_core = in_array($key_by_id[$id], $core_keys, true);

        $wpdb->update(
            $table_fields,
            [
                'field_label' => sanitize_text_field(isset($data['label']) ? $data['label'] : ''),
                'is_required' => $is_core ? 1 : (!empty($data['required']) ? 1 : 0),
                'is_active'   => $is_core ? 1 : (array_key_exists('active', $data) ? (!empty($data['active']) ? 1 : 0) : 1),
                'sort_order'  => $order,
                'column_width'=> qrm_pro_sanitize_column_width(isset($data['column_width']) ? $data['column_width'] : 'full'),
            ],
            ['id' => $id],
            ['%s', '%d', '%d', '%d', '%s'],
            ['%d']
        );

        $order++;
        $saved++;
    }

    return $saved;
}

/**
 * Canlı form önizlemesi.
 *
 * Restoran sahibi, etiketi değiştirdiğinde ya da alanları sıraladığında formun
 * müşteri tarafında nasıl görüneceğini KAYDETMEDEN görür. İlk hâli burada, PHP
 * tarafında basılır: JS kapalıyken de doğru görünür, canlı güncelleme
 * assets/js/form-preview.js ile eklenir.
 *
 * Bilinçli olarak yalnızca formun BİLGİ ADIMI önizlenir — bu sayfanın yönettiği
 * şey o. Puanlama kriterleri "Ayarlar & Puanlama" sayfasına aittir.
 *
 * Yapı ve sınıf adları frontend'in gerçek formundan gelir
 * (includes/frontend/form-render.php): `.qrm-input-group`, yarım genişlikteki
 * alanlar, zorunluluk yıldızı ve güvenlik sorusu aynı yerde durur.
 *
 * @param array $fields   qrm_form_fields satırları (sort_order sırasında).
 * @param array $settings qrm_pro_get_settings() çıktısı.
 * @return void
 */
function qrm_pro_render_form_preview($fields, $settings) {
    $dark = ($settings['theme_style'] === 'dark');
    ?>
    <div class="qrm-fp-preview">
        <div class="qrm-fp-sticky">
            <h2 class="qrm-fp-heading">Önizleme</h2>
            <p class="qrm-fp-note">
                Müşterinin göreceği bilgi adımı. Değişiklikleriniz burada anında görünür;
                kalıcı olması için <strong>Kaydet</strong>'e basmayı unutmayın.
            </p>

            <div class="qrm-fp-box<?php echo $dark ? ' is-dark' : ''; ?>"
                 id="qrm-form-preview"
                 style="--qrm-fp-btn: <?php echo esc_attr($settings['btn_color']); ?>; --qrm-fp-btn-text: <?php echo esc_attr($settings['btn_text_color']); ?>;">

                <div class="qrm-fp-title"><?php echo esc_html($settings['form_title']); ?></div>

                <div class="qrm-fp-fields">
                    <?php
                    $active = array_filter((array) $fields, static function ($f) {
                        return !empty($f->is_active);
                    });

                    if (empty($active)) : ?>
                        <p class="qrm-fp-empty">Aktif alan yok — müşteriye yalnızca güvenlik sorusu gösterilir.</p>
                    <?php else :
                        foreach ($active as $f) {
                            echo qrm_pro_form_preview_field(
                                $f->field_key,
                                $f->field_type,
                                $f->field_label,
                                (int) $f->is_required,
                                qrm_pro_field_column_width($f, 'review')
                            );
                        }
                    endif; ?>
                </div>

                <div class="qrm-fp-captcha">
                    <label>Güvenlik sorusu: 3 + 4 = ?</label>
                    <input type="text" disabled>
                </div>

                <button type="button" class="qrm-fp-submit" disabled>Gönder</button>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Önizlemedeki tek bir alanın HTML'i.
 *
 * form-preview.js aynı yapıyı JS tarafında üretir; ikisi ayrışmasın diye
 * işaretleme burada tek bir yerde tanımlıdır ve JS bu fonksiyonun ürettiği
 * sınıf adlarını birebir kullanır.
 *
 * @param string $key           Alan anahtarı (customer_phone, table_no…).
 * @param string $type          text | textarea | checkbox.
 * @param string $label         Görünen etiket.
 * @param int    $required      1 ise yıldız basılır.
 * @param string $column_width  full | half
 * @return string
 */
function qrm_pro_form_preview_field($key, $type, $label, $required, $column_width = 'full') {
    $half  = qrm_pro_sanitize_column_width($column_width) === 'half' ? ' half' : '';
    $star  = $required ? ' <span class="qrm-fp-req">*</span>' : '';
    $label = esc_html($label);

    if ($type === 'checkbox') {
        return '<div class="qrm-fp-group qrm-fp-check">'
             . '<label><input type="checkbox" disabled> <span>' . $label . '</span></label>'
             . '</div>';
    }

    $control = ($type === 'textarea')
        ? '<textarea rows="3" disabled></textarea>'
        : '<input type="text" disabled' . ($key === 'customer_phone' ? ' placeholder="0 (5__) ___ __ __"' : '') . '>';

    return '<div class="qrm-fp-group' . $half . '">'
         . '<label>' . $label . $star . '</label>'
         . $control
         . '</div>';
}
