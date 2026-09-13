<?php
if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------------------------
 * ÖDÜL YÖNETİMİ ÖZEL CAPABILITY'Sİ
 *
 * GÜVENLİK (BULGU-001, dinamik denetimde doğrulandı): qrm_reward_ajax_admin_lookup
 * ve qrm_reward_ajax_cashier_mark_used eskiden current_user_can('edit_posts')
 * kontrol ediyordu. edit_posts, WordPress'in Contributor rolünde bile bulunan
 * yerleşik bir capability'dir — sentetik Contributor/Author hesaplarıyla gerçek
 * müşteri e-postası görüntülenip gerçek bir ödül kodu "kullanıldı" işaretlenerek
 * kanıtlandı. Bu dosya, o iki uca ve ?view=kasa sayfasına özgü, dar kapsamlı bir
 * capability (qrm_manage_rewards) tanımlar ve mevcut kurulumlara sürüm kontrollü
 * bir migration ile ekler.
 *
 * qrm_pro_schema_maybe_upgrade() (bkz. qr-menu-reviews.php) ile AYNI desen:
 * admin_init'e bağlı, manage_options gerektirir, sürüm option'ı eşleşene kadar
 * çalışır, tekrar çalıştırılması zararsızdır (add_cap/remove_cap doğası gereği
 * idempotenttir).
 * ---------------------------------------------------------------------- */

/** Ödül yönetimi capability'si. */
define('QRM_REWARD_CAP', 'qrm_manage_rewards');

/** Capability migration sürümü. */
define('QRM_REWARD_CAP_VERSION', '1');

/** Migration sürümünün saklandığı option. */
define('QRM_REWARD_CAP_OPTION', 'qrm_reward_cap_version');

/**
 * Capability migration'ı bekliyor mu?
 *
 * @return bool
 */
function qrm_reward_cap_pending() {
    return get_option(QRM_REWARD_CAP_OPTION) !== QRM_REWARD_CAP_VERSION;
}

/**
 * Rol adı/slug'ında "kasiyer" veya "cashier" geçen özel rolleri bulur.
 *
 * Eklenti hiçbir zaman kendi kasiyer rolünü tanımlamadı (add_role çağrısı yok);
 * ama site sahibi bir rol yönetimi eklentisiyle böyle bir rol oluşturmuş
 * olabilir. Bulunursa bu role da capability eklenir; bulunamazsa varsayılan
 * olarak SADECE Administrator'a verilir (rastgele bir role yetki verilmez).
 *
 * @return string[] Bulunan rol slug'ları.
 */
function qrm_reward_cap_kasiyer_rolleri() {
    $bulunan = array();
    $roller  = wp_roles();

    if (!$roller || empty($roller->roles)) {
        return $bulunan;
    }

    foreach ($roller->roles as $slug => $rol_verisi) {
        if ('administrator' === $slug) {
            continue;
        }

        $isim = isset($rol_verisi['name']) ? (string) $rol_verisi['name'] : '';

        if (false !== stripos($slug, 'kasiyer') || false !== stripos($slug, 'cashier')
            || false !== stripos($isim, 'kasiyer') || false !== stripos($isim, 'cashier')) {
            $bulunan[] = $slug;
        }
    }

    return $bulunan;
}

/**
 * Capability'yi mevcut kurulumlara uygular.
 *
 * - Administrator her zaman alır (güvenli varsayılan, geriye dönük uyumluluk).
 * - Adı/slug'ı "kasiyer"/"cashier" içeren özel roller varsa onlar da alır.
 * - Contributor/Author/Subscriber gibi WordPress'in hazır rollerinden bu
 *   capability açıkça kaldırılır (zaten hiç verilmemiş olsa da, olası bir
 *   üçüncü parti karışıklığına karşı zararsız bir güvence).
 *
 * @return void
 */
function qrm_reward_cap_upgrade() {
    $admin_rol = get_role('administrator');

    if ($admin_rol && !$admin_rol->has_cap(QRM_REWARD_CAP)) {
        $admin_rol->add_cap(QRM_REWARD_CAP);
    }

    $kasiyer_rolleri = qrm_reward_cap_kasiyer_rolleri();

    foreach ($kasiyer_rolleri as $slug) {
        $rol = get_role($slug);
        if ($rol && !$rol->has_cap(QRM_REWARD_CAP)) {
            $rol->add_cap(QRM_REWARD_CAP);
        }
    }

    foreach (array('contributor', 'author', 'subscriber') as $slug) {
        $rol = get_role($slug);
        if ($rol && $rol->has_cap(QRM_REWARD_CAP)) {
            $rol->remove_cap(QRM_REWARD_CAP);
        }
    }

    if (empty($kasiyer_rolleri)) {
        update_option('qrm_reward_cap_kasiyer_bulunamadi', 1, false);
        if (function_exists('qmo_log')) {
            qmo_log('QR Menu Suite: özel bir kasiyer rolü bulunamadı; ödül yönetimi capability\'si (qrm_manage_rewards) yalnızca Administrator rolüne verildi. Belirli bir çalışana bu yetkiyi vermek için bir rol yönetimi eklentisiyle capability\'yi ilgili role ekleyebilirsiniz.');
        }
    } else {
        delete_option('qrm_reward_cap_kasiyer_bulunamadi');
    }

    update_option(QRM_REWARD_CAP_OPTION, QRM_REWARD_CAP_VERSION, false);
}

add_action('admin_init', 'qrm_reward_cap_maybe_upgrade', 5);
/**
 * Yönetim isteğinde capability migration'ını (gerekiyorsa) çalıştırır.
 *
 * @return void
 */
function qrm_reward_cap_maybe_upgrade() {
    if (!qrm_reward_cap_pending()) {
        return;
    }

    // Yetkisiz bir isteğin rol tablosunu değiştirmesini engelle.
    if (!current_user_can('manage_options')) {
        return;
    }

    qrm_reward_cap_upgrade();
}
