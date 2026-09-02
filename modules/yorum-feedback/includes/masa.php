<?php
/**
 * Masa slug çözümlemesi — qr-masa modülü ile köprü.
 *
 * URL parametresi ?masa= ve HMAC oturum çerezi (qr_masa_token) qr-masa
 * ekosisteminin standart yollarıdır; QMO_Masalar::bul() ile slug → id eşlenir.
 *
 * @package QR_Menu_Reviews
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * İstekteki masa slug'ını döndürür (URL önce, sonra oturum çerezi).
 *
 * qr-masa modülü kapalıysa veya oturum yoksa boş string döner; hata üretmez.
 *
 * @return string
 */
function qrm_pro_request_masa_slug() {
    if (isset($_GET['masa']) && is_scalar($_GET['masa'])) {
        $slug = sanitize_title(wp_unslash($_GET['masa']));
        if ($slug !== '') {
            return $slug;
        }
    }

    if (isset($_POST['masa']) && is_scalar($_POST['masa'])) {
        $slug = sanitize_title(wp_unslash($_POST['masa']));
        if ($slug !== '') {
            return $slug;
        }
    }

    if (function_exists('qmo_oturum')) {
        $sess = qmo_oturum();
        if (is_array($sess) && !empty($sess['masa'])) {
            return sanitize_title((string) $sess['masa']);
        }
    }

    return '';
}

/**
 * Slug'dan masa kimliğini çözer.
 *
 * @param string $slug Masa slug'ı.
 * @return int|null Kayıtlı masa yoksa null.
 */
function qrm_pro_masa_id_from_slug($slug) {
    $slug = sanitize_title((string) $slug);
    if ($slug === '' || !class_exists('QMO_Masalar')) {
        return null;
    }

    if (!method_exists('QMO_Masalar', 'bul')) {
        return null;
    }

    $masa = QMO_Masalar::bul($slug);
    if (!$masa || empty($masa->id)) {
        return null;
    }

    return (int) $masa->id;
}

/**
 * Yorum gönderiminde kullanılacak masa bağlamı.
 *
 * table_id yalnızca kayıtlı slug bulunursa yazılır; aksi hâlde serbest metin
 * table_no korunur ve table_id NULL kalır.
 *
 * @param string $table_no_form POST'tan gelen serbest masa no (rakamlar).
 * @return array{table_id:int|null,table_no:string,masa_slug:string}
 */
function qrm_pro_resolve_masa_for_submission($table_no_form = '') {
    $table_no = preg_replace('/[^0-9]/', '', (string) $table_no_form);
    $slug     = qrm_pro_request_masa_slug();
    $table_id = null;

    if ($slug !== '') {
        $resolved = qrm_pro_masa_id_from_slug($slug);
        if ($resolved !== null && $resolved > 0) {
            $table_id = $resolved;
        }
    }

    return [
        'table_id'   => $table_id,
        'table_no'   => $table_no,
        'masa_slug'  => $slug,
    ];
}
