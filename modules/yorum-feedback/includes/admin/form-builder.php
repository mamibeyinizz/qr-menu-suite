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
            sıralayabilir, etiketlerini değiştirebilir, zorunlu/aktif durumlarını ayarlayabilirsiniz.
        </p>

        <?php if ($notice !== ''): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
        <?php endif; ?>

        <form method="POST">
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
                        <div class="qrm-field-row">
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
    </div>
    <?php
}

/**
 * Müşteri bilgileri form alanlarını kaydeder.
 * Satır sırası = sort_order; POST dizisi sürükle-bırak sonrası DOM sırasında gelir.
 *
 * @param array $rows $_POST['fields'] — [id => ['label'=>..,'required'=>..,'active'=>..]]
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
            ],
            ['id' => $id],
            ['%s', '%d', '%d', '%d'],
            ['%d']
        );

        $order++;
        $saved++;
    }

    return $saved;
}
