<?php
/**
 * Yorum formu görsel yüklemeleri (v4.2.8).
 *
 * @package QR_Menu_Reviews
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Medya kütüphanesi etiketi. */
const QRM_REVIEW_MEDIA_TAG = 'QR Yorum Görseli';

/** Büyük görsellerin indirileceği azami kenar uzunluğu (px). */
const QRM_REVIEW_MEDIA_MAX_EDGE = 1600;

/**
 * qrm_review_media tablo adı.
 *
 * @return string
 */
function qrm_review_media_table() {
    global $wpdb;

    return $wpdb->prefix . 'qrm_review_media';
}

/**
 * İzin verilen MIME türleri (wp_handle_upload için).
 *
 * @return array<string,string>
 */
function qrm_pro_media_allowed_mimes() {
    return [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'webp'         => 'image/webp',
    ];
}

/**
 * Görsel yükleme açık mı?
 *
 * @param array|null $settings Eklenti ayarları.
 * @return bool
 */
function qrm_pro_media_is_enabled($settings = null) {
    if ($settings === null) {
        $settings = qrm_pro_get_settings();
    }

    return !empty($settings['qrm_media_enabled']);
}

/**
 * Sunucu tarafı dosya adedi ve boyut sınırları.
 *
 * @param array|null $settings Eklenti ayarları.
 * @return array{max_files:int,max_bytes:int}
 */
function qrm_pro_media_limits($settings = null) {
    if ($settings === null) {
        $settings = qrm_pro_get_settings();
    }

    $max_files = isset($settings['qrm_media_max_files']) ? (int) $settings['qrm_media_max_files'] : 2;
    $max_mb    = isset($settings['qrm_media_max_mb']) ? (int) $settings['qrm_media_max_mb'] : 3;

    return [
        'max_files' => max(1, min(5, $max_files)),
        'max_bytes' => max(1, min(8, $max_mb)) * 1024 * 1024,
    ];
}

/**
 * Gerçek MIME doğrulaması: uzantı değil, dosya içeriği.
 *
 * @param string $file_path Geçici dosya yolu.
 * @param string $filename  Orijinal dosya adı.
 * @return bool
 */
function qrm_pro_media_validate_mime($file_path, $filename) {
    $allowed = qrm_pro_media_allowed_mimes();
    $checked = wp_check_filetype_and_ext($file_path, $filename, $allowed);

    if (empty($checked['type']) || !in_array($checked['type'], array_values($allowed), true)) {
        return false;
    }

    if (!function_exists('finfo_open')) {
        return true;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        return false;
    }

    $mime = finfo_file($finfo, $file_path);
    finfo_close($finfo);

    return is_string($mime) && in_array($mime, array_values($allowed), true);
}

/**
 * EXIF konum verisini temizlemek için yeniden kaydet; büyük kenarları küçült.
 *
 * @param int $attachment_id Ek dosya kimliği.
 * @return void
 */
function qrm_pro_media_strip_exif_and_resize($attachment_id) {
    $file = get_attached_file((int) $attachment_id);
    if (!$file || !file_exists($file)) {
        return;
    }

    $editor = wp_get_image_editor($file);
    if (is_wp_error($editor)) {
        return;
    }

    $size = $editor->get_size();
    if (!is_array($size)) {
        return;
    }

    $w = (int) $size['width'];
    $h = (int) $size['height'];

    if ($w > QRM_REVIEW_MEDIA_MAX_EDGE || $h > QRM_REVIEW_MEDIA_MAX_EDGE) {
        if ($w >= $h) {
            $editor->resize(QRM_REVIEW_MEDIA_MAX_EDGE, null);
        } else {
            $editor->resize(null, QRM_REVIEW_MEDIA_MAX_EDGE);
        }
    }

    $saved = $editor->save($file);
    if (is_wp_error($saved)) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';

    $metadata = wp_generate_attachment_metadata((int) $attachment_id, $file);
    wp_update_attachment_metadata((int) $attachment_id, $metadata);
}

/**
 * Medya kütüphanesinde etiketle.
 *
 * @param int $attachment_id Ek dosya kimliği.
 * @return void
 */
function qrm_pro_media_tag_attachment($attachment_id) {
    wp_set_object_terms((int) $attachment_id, QRM_REVIEW_MEDIA_TAG, 'post_tag', false);
}

/**
 * Tek dosyayı WordPress medya kütüphanesine yükler.
 *
 * @param array $file_array $_FILES öğesi.
 * @param int   $max_bytes  Azami bayt.
 * @return int|WP_Error attachment_id veya hata.
 */
function qrm_pro_media_upload_one(array $file_array, $max_bytes) {
    if (empty($file_array['tmp_name']) || !is_uploaded_file($file_array['tmp_name'])) {
        return new WP_Error('qrm_media', qrm_ceviri_review(__('Geçersiz dosya yüklemesi.', 'qrms')));
    }

    if (!isset($file_array['error']) || (int) $file_array['error'] !== UPLOAD_ERR_OK) {
        return new WP_Error('qrm_media', qrm_ceviri_review(__('Dosya yüklenemedi.', 'qrms')));
    }

    if ((int) $file_array['size'] > (int) $max_bytes) {
        return new WP_Error('qrm_media', qrm_ceviri_review(__('Dosya boyutu sınırı aşıldı.', 'qrms')));
    }

    $name = isset($file_array['name']) ? (string) $file_array['name'] : '';
    if (!qrm_pro_media_validate_mime($file_array['tmp_name'], $name)) {
        return new WP_Error(
            'qrm_media',
            qrm_ceviri_review(__('Yalnızca JPEG, PNG veya WebP görseller kabul edilir.', 'qrms'))
        );
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $upload = wp_handle_upload(
        $file_array,
        [
            'test_form' => false,
            'mimes'     => qrm_pro_media_allowed_mimes(),
        ]
    );

    if (isset($upload['error'])) {
        return new WP_Error('qrm_media', $upload['error']);
    }

    $attachment_id = wp_insert_attachment(
        [
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name(pathinfo($name, PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_parent'    => 0,
        ],
        $upload['file']
    );

    if (!$attachment_id || is_wp_error($attachment_id)) {
        if (!empty($upload['file']) && file_exists($upload['file'])) {
            wp_delete_file($upload['file']);
        }

        return new WP_Error('qrm_media', qrm_ceviri_review(__('Görsel kaydedilemedi.', 'qrms')));
    }

    $metadata = wp_generate_attachment_metadata((int) $attachment_id, $upload['file']);
    wp_update_attachment_metadata((int) $attachment_id, $metadata);

    qrm_pro_media_strip_exif_and_resize((int) $attachment_id);
    qrm_pro_media_tag_attachment((int) $attachment_id);

    return (int) $attachment_id;
}

/**
 * $_FILES anahtarını tek veya çoklu dosya dizisine çevirir.
 *
 * @param string $files_key $_FILES anahtarı.
 * @return array<int,array>
 */
function qrm_pro_normalize_files_array($files_key) {
    if (empty($_FILES[$files_key])) {
        return [];
    }

    $f = $_FILES[$files_key];

    if (!is_array($f['name'])) {
        if ($f['name'] === '' && (int) $f['error'] === UPLOAD_ERR_NO_FILE) {
            return [];
        }

        return [$f];
    }

    $out   = [];
    $count = count($f['name']);

    for ($i = 0; $i < $count; $i++) {
        if ($f['name'][$i] === '' && (int) $f['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $out[] = [
            'name'     => $f['name'][$i],
            'type'     => $f['type'][$i],
            'tmp_name' => $f['tmp_name'][$i],
            'error'    => $f['error'][$i],
            'size'     => $f['size'][$i],
        ];
    }

    return $out;
}

/**
 * Yorum için seçilen görselleri kaydeder.
 *
 * @param int        $review_id Yorum kimliği.
 * @param array|null $settings  Eklenti ayarları.
 * @return array<int>|WP_Error attachment_id listesi veya hata.
 */
function qrm_pro_save_review_media($review_id, $settings = null) {
    $review_id = (int) $review_id;

    if ($review_id <= 0) {
        return new WP_Error('qrm_media', qrm_ceviri_review(__('Geçersiz yorum.', 'qrms')));
    }

    if ($settings === null) {
        $settings = qrm_pro_get_settings();
    }

    if (!qrm_pro_media_is_enabled($settings)) {
        return [];
    }

    $files = qrm_pro_normalize_files_array('qrm_review_media');
    if (empty($files)) {
        return [];
    }

    $limits = qrm_pro_media_limits($settings);

    if (count($files) > $limits['max_files']) {
        return new WP_Error(
            'qrm_media',
            sprintf(
                /* translators: %d: maximum number of images */
                qrm_ceviri_review(__('En fazla %d görsel yükleyebilirsiniz.', 'qrms')),
                $limits['max_files']
            )
        );
    }

    global $wpdb;

    $table          = qrm_review_media_table();
    $attachment_ids = [];

    foreach ($files as $file) {
        $att_id = qrm_pro_media_upload_one($file, $limits['max_bytes']);

        if (is_wp_error($att_id)) {
            foreach ($attachment_ids as $aid) {
                wp_delete_attachment($aid, true);
            }

            return $att_id;
        }

        $inserted = $wpdb->insert(
            $table,
            [
                'review_id'     => $review_id,
                'attachment_id' => $att_id,
                'created_at'    => current_time('mysql'),
            ],
            ['%d', '%d', '%s']
        );

        if ($inserted === false) {
            wp_delete_attachment($att_id, true);

            foreach ($attachment_ids as $aid) {
                wp_delete_attachment($aid, true);
            }

            return new WP_Error('qrm_media', qrm_ceviri_review(__('Görseller kaydedilemedi.', 'qrms')));
        }

        $attachment_ids[] = $att_id;
    }

    return $attachment_ids;
}

/**
 * Bir yoruma bağlı medya satırları.
 *
 * @param int $review_id Yorum kimliği.
 * @return array<object>
 */
function qrm_pro_get_review_media($review_id) {
    global $wpdb;

    $review_id = (int) $review_id;
    if ($review_id <= 0) {
        return [];
    }

    $table = qrm_review_media_table();
    $rows  = $wpdb->get_results($wpdb->prepare(
        "SELECT id, review_id, attachment_id, created_at FROM {$table} WHERE review_id = %d ORDER BY id ASC",
        $review_id
    ));

    return is_array($rows) ? $rows : [];
}

/**
 * Birden çok yorum için medya haritası (review_id => satırlar).
 *
 * @param array<int> $review_ids Yorum kimlikleri.
 * @return array<int,array<object>>
 */
function qrm_pro_get_review_media_bulk(array $review_ids) {
    global $wpdb;

    $review_ids = array_values(array_filter(array_map('intval', $review_ids)));
    if (empty($review_ids)) {
        return [];
    }

    $table        = qrm_review_media_table();
    $placeholders = implode(',', array_fill(0, count($review_ids), '%d'));
    $rows         = $wpdb->get_results($wpdb->prepare(
        "SELECT id, review_id, attachment_id, created_at FROM {$table} WHERE review_id IN ({$placeholders}) ORDER BY review_id ASC, id ASC",
        $review_ids
    ));

    $map = [];

    if (is_array($rows)) {
        foreach ($rows as $row) {
            $rid = (int) $row->review_id;
            if (!isset($map[$rid])) {
                $map[$rid] = [];
            }
            $map[$rid][] = $row;
        }
    }

    return $map;
}

/**
 * Yoruma bağlı tüm ek dosyaları ve satırları siler.
 *
 * @param int $review_id Yorum kimliği.
 * @return void
 */
function qrm_pro_delete_review_media($review_id) {
    global $wpdb;

    $review_id = (int) $review_id;
    if ($review_id <= 0) {
        return;
    }

    foreach (qrm_pro_get_review_media($review_id) as $row) {
        wp_delete_attachment((int) $row->attachment_id, true);
    }

    $wpdb->delete(qrm_review_media_table(), ['review_id' => $review_id], ['%d']);
}

/**
 * Onaylı yorum kartı için küçük galeri HTML'i.
 *
 * @param int $review_id Yorum kimliği.
 * @param int $status     Yayın durumu (yalnızca 1 ise gösterilir).
 * @return string
 */
function qrm_pro_render_review_media_gallery($review_id, $status = 1) {
    if ((int) $status !== 1) {
        return '';
    }

    $media = qrm_pro_get_review_media((int) $review_id);
    if (empty($media)) {
        return '';
    }

    $html = '<div class="qrm-review-media">';

    foreach ($media as $item) {
        $att_id = (int) $item->attachment_id;
        $full   = wp_get_attachment_url($att_id);

        if (!$full) {
            continue;
        }

        $thumb = wp_get_attachment_image_url($att_id, 'thumbnail');
        if (!$thumb) {
            $thumb = $full;
        }

        $html .= '<button type="button" class="qrm-review-media-item" data-full="' . esc_url($full) . '">';
        $html .= '<img src="' . esc_url($thumb) . '" alt="" loading="lazy" width="72" height="72">';
        $html .= '</button>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Yönetim listesi için küçük önizleme + tam boy bağlantı.
 *
 * @param array<object> $media_rows qrm_pro_get_review_media() çıktısı.
 * @return string
 */
function qrm_pro_render_admin_review_media($media_rows) {
    if (empty($media_rows)) {
        return '';
    }

    $html = '<div class="qrm-admin-review-media">';

    foreach ($media_rows as $item) {
        $att_id = (int) $item->attachment_id;
        $full   = wp_get_attachment_url($att_id);

        if (!$full) {
            continue;
        }

        $thumb = wp_get_attachment_image_url($att_id, 'thumbnail');
        if (!$thumb) {
            $thumb = $full;
        }

        $html .= '<a href="' . esc_url($full) . '" target="_blank" rel="noopener noreferrer" class="qrm-admin-media-thumb">';
        $html .= '<img src="' . esc_url($thumb) . '" alt="" loading="lazy" width="48" height="48">';
        $html .= '</a>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Vanilla lightbox (sayfada bir kez basılır).
 *
 * @return string
 */
function qrm_pro_render_review_media_lightbox() {
    static $done = false;

    if ($done) {
        return '';
    }

    $done = true;

    ob_start();
    ?>
    <div id="qrm-media-lightbox" class="qrm-media-lightbox" hidden>
        <button type="button" class="qrm-media-lightbox-close" aria-label="<?php esc_attr_e('Kapat', 'qrms'); ?>">&times;</button>
        <img src="" alt="" class="qrm-media-lightbox-img">
    </div>
    <script>
    (function() {
        var lb = document.getElementById('qrm-media-lightbox');
        if (!lb) return;
        var img = lb.querySelector('.qrm-media-lightbox-img');
        var closeBtn = lb.querySelector('.qrm-media-lightbox-close');
        function openLightbox(url) {
            img.src = url;
            lb.hidden = false;
            document.body.style.overflow = 'hidden';
        }
        function closeLightbox() {
            lb.hidden = true;
            img.src = '';
            document.body.style.overflow = '';
        }
        document.addEventListener('click', function(e) {
            var item = e.target.closest('.qrm-review-media-item');
            if (item && item.dataset.full) {
                e.preventDefault();
                openLightbox(item.dataset.full);
                return;
            }
            if (e.target === lb || e.target === closeBtn) {
                closeLightbox();
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !lb.hidden) closeLightbox();
        });
    })();
    </script>
    <?php

    return ob_get_clean();
}
