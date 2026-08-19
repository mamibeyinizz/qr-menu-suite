<?php
if (!defined('ABSPATH')) exit;

// 4. ADMİN: MÜŞTERİ BİLGİLERİ FORMU (yorum formunun sabit alanları)
//
// v4.2.1: Ayrı bir menü maddesi olmaktan çıkıp "Yorum Formu Ayarları" sayfasının
// ikinci sekmesi oldu. Kaydetme işi o sayfanın TEK POST'u içinde yapılır
// (qrm_pro_save_review_form_fields), böylece sekmeler birbirinin verisini ezmez.
//
// Not: Bu, yorum formunun koda gömülü alanlarını yönetir. Sınırsız sayıda özel form
// oluşturmak için "Formlar" sayfası (includes/admin/forms-list.php) kullanılır.

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

/** "Müşteri Bilgileri Formu" sekmesinin içeriği (kendi <form>'u yoktur). */
function qrm_pro_admin_fields_pane() {
    global $wpdb;
    $table_fields = $wpdb->prefix . 'qrm_form_fields';
    $fields = $wpdb->get_results("SELECT * FROM $table_fields ORDER BY sort_order ASC");
    ?>
    <div class="qrm-card" style="margin-top:18px;">
        <h3>Müşteri Bilgileri Formu</h3>
        <p class="description">
            Yorum ve iletişim formunda müşteriden istenecek bilgi alanları. Satırları sürükleyerek
            sıralayabilir, etiketlerini değiştirebilir, zorunlu/aktif durumlarını ayarlayabilirsiniz.
            Değişiklikler sayfanın altındaki <strong>Tüm Ayarları Kaydet</strong> butonuyla kaydedilir.
        </p>

        <div class="notice notice-info inline" id="qrm-sort-hint" style="display:none; margin:10px 0;">
            <p>Sıralamayı değiştirdiniz — kaydetmek için sayfanın altındaki <strong>Tüm Ayarları Kaydet</strong> butonuna basın.</p>
        </div>

        <div id="qrm-sortable-fields">
            <?php foreach ($fields as $f):
                $is_core = in_array($f->field_key, ['comment'], true);
            ?>
            <div class="qrm-field-row">
                <span class="dashicons dashicons-menu qrm-field-handle" title="Sıralamak için sürükleyin"></span>
                <strong><?php echo esc_html(strtoupper($f->field_key)); ?></strong>
                <input type="text" name="fields[<?php echo intval($f->id); ?>][label]" value="<?php echo esc_attr($f->field_label); ?>">
                <label><input type="checkbox" name="fields[<?php echo intval($f->id); ?>][required]" value="1" <?php checked($f->is_required, 1); ?> <?php disabled($is_core, true); ?>> Zorunlu</label>
                <label><input type="checkbox" name="fields[<?php echo intval($f->id); ?>][active]" value="1" <?php checked($f->is_active, 1); ?> <?php disabled($is_core, true); ?>> Aktif</label>
                <?php if ($is_core): ?>
                    <span class="description" style="font-size:12px;">Bu alan zorunludur, kapatılamaz.</span>
                    <input type="hidden" name="fields[<?php echo intval($f->id); ?>][required]" value="1">
                    <input type="hidden" name="fields[<?php echo intval($f->id); ?>][active]" value="1">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
