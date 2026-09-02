<?php
if (!defined('ABSPATH')) exit;

// ÖDÜL MODÜLÜ: DÖNÜŞÜM OLAY KAYDI VE HUNİ RAPORU (v4.3.1)

/** İzin verilen olay türleri. */
const QRM_REWARD_EVENT_TYPES = [
    'popup_shown',
    'google_clicked',
    'returned',
    'email_submitted',
    'code_issued',
    'skipped',
    'code_used',
];

/** Günlük eski olay temizliği cron kancası. */
const QRM_REWARD_EVENTS_CLEANUP_CRON_HOOK = 'qrm_reward_events_cleanup_daily';

function qrm_reward_events_table() {
    global $wpdb;
    return $wpdb->prefix . 'qrm_reward_events';
}

/**
 * Olay tablosunu kurar. qrm_reward_install() içinden çağrılır.
 *
 * @return void
 */
function qrm_reward_events_install() {
    global $wpdb;
    $table = qrm_reward_events_table();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        review_id mediumint(9) NOT NULL,
        event varchar(30) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        ip_hash char(64) NOT NULL DEFAULT '',
        PRIMARY KEY  (id),
        KEY idx_event_created (event, created_at),
        KEY idx_review (review_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    if (function_exists('qrm_reward_schedule_events_cleanup_cron')) {
        qrm_reward_schedule_events_cleanup_cron();
    }
}

/**
 * Tek bir ödül hunisi olayı yazar. IP açık saklanmaz; wp_hash() ile hashlenir.
 *
 * @param int    $review_id
 * @param string $event
 * @return bool
 */
function qrm_reward_log_event($review_id, $event) {
    if ($review_id <= 0 || !in_array($event, QRM_REWARD_EVENT_TYPES, true)) {
        return false;
    }

    global $wpdb;
    $ip = function_exists('qrm_pro_client_ip') ? qrm_pro_client_ip() : '';

    return (false !== $wpdb->insert(
        qrm_reward_events_table(),
        [
            'review_id'  => (int) $review_id,
            'event'      => $event,
            'created_at' => current_time('mysql'),
            'ip_hash'    => wp_hash((string) $ip),
        ],
        ['%d', '%s', '%s', '%s']
    ));
}

/**
 * Huni raporu: her adımda benzersiz review_id sayısı ve önceki adıma göre yüzde.
 *
 * @param int $days Son kaç gün (varsayılan 30).
 * @return array<int, array{key:string,label:string,count:int,pct:float|null}>
 */
function qrm_reward_get_funnel_stats($days = 30) {
    global $wpdb;

    $days  = max(1, min(365, (int) $days));
    $table = qrm_reward_events_table();
    $since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

    $steps = [
        ['key' => 'popup_shown',    'label' => 'Gösterim'],
        ['key' => 'google_clicked', 'label' => 'Google tıklama'],
        ['key' => 'returned',       'label' => 'Sekmeye dönüş'],
        ['key' => 'email_submitted','label' => 'E-posta'],
        ['key' => 'code_issued',    'label' => 'Kod verildi'],
        ['key' => 'code_used',      'label' => 'Kod kullanıldı'],
    ];

    $counts = [];
    foreach ($steps as $step) {
        $counts[$step['key']] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT review_id) FROM {$table}
             WHERE event = %s AND created_at >= %s",
            $step['key'],
            $since
        ));
    }

    $out = [];
    $prev = null;
    foreach ($steps as $step) {
        $count = $counts[$step['key']];
        $pct   = null;
        if ($prev !== null && $prev > 0) {
            $pct = round(($count / $prev) * 100, 1);
        } elseif ($prev === null) {
            $pct = $count > 0 ? 100.0 : 0.0;
        } else {
            $pct = 0.0;
        }
        $out[] = [
            'key'   => $step['key'],
            'label' => $step['label'],
            'count' => $count,
            'pct'   => $pct,
        ];
        $prev = $count;
    }

    return $out;
}

/**
 * @return void
 */
function qrm_reward_schedule_events_cleanup_cron() {
    if (!wp_next_scheduled(QRM_REWARD_EVENTS_CLEANUP_CRON_HOOK)) {
        wp_schedule_event(time() + (2 * HOUR_IN_SECONDS), 'daily', QRM_REWARD_EVENTS_CLEANUP_CRON_HOOK);
    }
}

/**
 * @return void
 */
function qrm_reward_unschedule_events_cleanup_cron() {
    wp_clear_scheduled_hook(QRM_REWARD_EVENTS_CLEANUP_CRON_HOOK);
}

add_action(QRM_REWARD_EVENTS_CLEANUP_CRON_HOOK, 'qrm_reward_cron_delete_old_events');

/**
 * Saklama süresinden eski olayları siler.
 *
 * @return void
 */
function qrm_reward_cron_delete_old_events() {
    global $wpdb;

    $settings = qrm_pro_get_settings();
    $days     = isset($settings['qrm_reward_events_retention_days'])
        ? (int) $settings['qrm_reward_events_retention_days']
        : 180;
    $days = max(30, min(730, $days ?: 180));

    $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
    $table  = qrm_reward_events_table();

    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$table} WHERE created_at < %s",
        $cutoff
    ));
}

/**
 * Kod kullanıldığında code_used olayını yazar (source_review_id varsa).
 *
 * @param int $code_id
 * @return void
 */
function qrm_reward_log_code_used_event($code_id) {
    global $wpdb;

    $row = $wpdb->get_row($wpdb->prepare(
        'SELECT source_review_id FROM ' . qrm_reward_table() . ' WHERE id = %d',
        (int) $code_id
    ));

    if ($row && !empty($row->source_review_id)) {
        qrm_reward_log_event((int) $row->source_review_id, 'code_used');
    }
}
