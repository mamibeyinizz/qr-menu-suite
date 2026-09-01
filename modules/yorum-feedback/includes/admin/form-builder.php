<?php
if (!defined('ABSPATH')) exit;

// 4. ADMİN: MÜŞTERİ BİLGİLERİ FORMU (yorum formunun sabit alanları)
//
// v4.2.1'de "Yorum Formu Ayarları" sayfasının JS sekmesine gömülmüş ve o sayfanın
// POST'una bağlanmıştı; suite'e taşınırken yeniden kendi sayfası oldu ve kendi
// nonce'u + kendi Kaydet'i ile çalışıyor. Böylece alan sıralamasını kaydetmek için
// artık puanlama ayarlarının tamamını yeniden göndermek gerekmiyor.
//
// Not: Bu ekran, yorum formunun koda gömülü alanlarını yönetir. Sınırsız sayıda
// özel form oluşturmak için "Formlar" ekranı kullanılır (includes/admin/forms-list.php).

/** Müşteri Bilgileri Formu ekranı. */
function qrm_pro_admin_form_builder() {
    if (!current_user_can('manage_options')) {
        wp_die('Bu sayfayı görüntüleme yetkiniz yok.');
    }

    $notice = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qrm_save_fields'])) {
        check_admin_referer('qrm_save_review_form_fields');
        $saved = qrm_pro_save_review_form_fields(isset($_POST['fields']) ? $_POST['fields'] : []);
        $notice = sprintf('%d form alanı güncellendi.', $saved);
    }

    global $wpdb;
    $table_fields = $wpdb->prefix . 'qrm_form_fields';
    $fields = $wpdb->get_results("SELECT * FROM $table_fields ORDER BY sort_order ASC");
    ?>
    <div class="wrap qrm-pro-wrap">
        <h1>Müşteri Bilgileri Formu</h1>
        <p class="qrm-lead">
            Yorum ve iletişim formunda müşteriden istenecek bilgi alanları. Satırları sürükleyerek
            sıralayabilir, etiketlerini değiştirebilir, zorunlu/aktif durumlarını ve her alanın
            sütun genişliğini (tekli / ikili) ayarlayabilirsiniz. Elementor Form widget'ındaki
            Column Width bu kısa koda uygulanmaz — genişlik buradan seçilir.
        </p>
        <?php
        if (function_exists('rma_ceviri_veri_dil_sayisi') && !empty($fields)) {
            $diller = 0;
            foreach ($fields as $f) {
                $n = rma_ceviri_veri_dil_sayisi('form_field', (int) $f->id, 'label');
                if ($n > $diller) {
                    $diller = $n;
                }
            }
            echo rma_ceviri_bayat_uyari_html(rma_ceviri_bayat_uyari_ekran_metni($diller));
        }
        ?>

        <?php if ($notice !== ''): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
        <?php endif; ?>

        <div class="qrm-fp-layout">
        <div class="qrm-fp-settings">
        <form method="POST" id="qrm-fields-form">
            <?php wp_nonce_field('qrm_save_review_form_fields'); ?>

            <div class="qrm-card">
                <div class="notice notice-info inline" id="qrm-sort-hint" style="display:none; margin:0 0 14px;">
                    <p>Sıralamayı değiştirdiniz — kaydetmek için aşağıdaki <strong>Kaydet</strong> butonuna basın.</p>
                </div>

                <?php if (empty($fields)): ?>
                    <div class="qrm-empty">
                        <strong>Form alanları oluşturulmamış.</strong>
                        <p>Varsayılan alanlar modül kurulumunda eklenir. Genel Ayarlar sayfasından lisansı yeniden doğrulayın.</p>
                    </div>
                <?php else: ?>
                    <div id="qrm-sortable-fields">
                        <?php foreach ($fields as $f):
                            $is_core = in_array($f->field_key, ['comment'], true);
                        ?>
                        <div class="qrm-field-row"
                             data-key="<?php echo esc_attr($f->field_key); ?>"
                             data-type="<?php echo esc_attr($f->field_type); ?>"
                             data-core="<?php echo $is_core ? '1' : '0'; ?>">
                            <span class="dashicons dashicons-menu qrm-field-handle" title="Sıralamak için sürükleyin"></span>
                            <?php // Sürükle-bırakın erişilebilir karşılığı: jQuery UI sortable
                                  // dokunmatik ekranlarda çalışmaz, klavyeyle de sürüklenemez. ?>
                            <span class="qrm-field-move">
                                <button type="button" class="button button-small qrm-field-up" title="Yukarı taşı" aria-label="Yukarı taşı">▲</button>
                                <button type="button" class="button button-small qrm-field-down" title="Aşağı taşı" aria-label="Aşağı taşı">▼</button>
                            </span>
                            <strong class="qrm-field-key"><?php echo esc_html(strtoupper($f->field_key)); ?></strong>
                            <input type="text" name="fields[<?php echo intval($f->id); ?>][label]" value="<?php echo esc_attr($f->field_label); ?>">
                            <?php if ($is_core): ?>
                                <span class="description">Yorum metni her zaman zorunludur, kapatılamaz.</span>
                                <input type="hidden" name="fields[<?php echo intval($f->id); ?>][required]" value="1">
                                <input type="hidden" name="fields[<?php echo intval($f->id); ?>][active]" value="1">
                            <?php else: ?>
                                <label><input type="checkbox" name="fields[<?php echo intval($f->id); ?>][required]" value="1" <?php checked($f->is_required, 1); ?>> Zorunlu</label>
                                <label><input type="checkbox" name="fields[<?php echo intval($f->id); ?>][active]" value="1" <?php checked($f->is_active, 1); ?>> Aktif</label>
                            <?php endif; ?>
                            <label class="qrm-field-width">
                                Sütun
                                <select name="fields[<?php echo intval($f->id); ?>][column_width]">
                                    <option value="full" <?php selected(qrm_pro_field_column_width($f, 'review'), 'full'); ?>>Tekli (tam genişlik)</option>
                                    <option value="half" <?php selected(qrm_pro_field_column_width($f, 'review'), 'half'); ?>>İkili (yarım genişlik)</option>
                                </select>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($fields)): ?>
            <p class="submit">
                <input type="submit" name="qrm_save_fields" class="button button-primary button-large" value="Kaydet">
            </p>
            <?php endif; ?>
        </form>
        </div><!-- /.qrm-fp-settings -->

        <?php qrm_pro_render_form_preview($fields, qrm_pro_get_settings()); ?>
        </div><!-- /.qrm-fp-layout -->
    </div>
    <?php
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

/**
 * Müşteri bilgileri form alanlarını kaydeder.
 * Satır sırası = sort_order; POST dizisi sürükle-bırak sonrası DOM sırasında gelir.
 *
 * @param array $rows $_POST['fields'] — [id => ['label'=>..,'required'=>..,'active'=>..,'column_width'=>full|half]]
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
                'is_required' => $is_core ? 1 : (isset($data['required']) ? 1 : 0),
                'is_active'   => $is_core ? 1 : (isset($data['active']) ? 1 : 0),
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
