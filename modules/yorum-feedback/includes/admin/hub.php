<?php
if (!defined('ABSPATH')) exit;

// 2b. ADMİN: BAŞLANGIÇ EKRANI ("QR Menü > Yorum & Feedback" satırı)
//
// Suite menüsündeki modül satırı, modülün yedi ekranını listeleyen bir başlangıç
// ekranı açar — restoran-menu modülündeki başlangıç ekranının aynı mantığı.
// Kartlar qrm_pro_admin_pages() listesinden üretilir, yani menüde olan her sayfa
// burada da vardır; ikisi birbirinden ayrışamaz.
//
// Üstteki özet, restoran sahibinin panele girer girmez görmesi gereken tek şeyi
// söyler: bekleyen iş var mı? Bu yüzden "Onay Bekleyen" ilk kutudur.

/** Başlangıç ekranı. */
function qrm_pro_admin_hub() {
    if (!current_user_can('manage_options')) {
        wp_die('Bu sayfayı görüntüleme yetkiniz yok.');
    }

    $stats = qrm_pro_review_stats();
    $unread = (int) qrm_cf_unread_total();
    ?>
    <div class="wrap qrm-pro-wrap">
        <h1>Yorum &amp; Feedback</h1>
        <p class="qrm-lead">Müşteri yorumlarınız, puanlama ayarlarınız, Google ödül sisteminiz ve kendi formlarınız burada.</p>

        <?php if (!$stats['table_ok']): ?>
            <div class="notice notice-error">
                <p>
                    <strong>Yorum tablosu veritabanında bulunamadı.</strong>
                    Bu yüzden hiçbir yorum listelenemiyor. Genel Ayarlar sayfasından lisansı yeniden
                    doğrulayın; sorun sürerse veritabanı kullanıcısının tablo oluşturma yetkisi olmayabilir.
                </p>
            </div>
        <?php endif; ?>

        <div class="qrm-hub-summary">
            <div class="qrm-stat-box" style="border-left-color:<?php echo $stats['pending'] > 0 ? '#f59e0b' : '#10b981'; ?>;">
                <h3>Onay Bekleyen</h3>
                <div class="value">
                    <?php if ($stats['pending'] > 0): ?>
                        <a href="<?php echo esc_url(qrm_pro_admin_url('qrms-yf-yorumlar', ['durum' => 'bekleyen'])); ?>"><?php echo intval($stats['pending']); ?></a>
                    <?php else: ?>
                        0
                    <?php endif; ?>
                </div>
            </div>
            <div class="qrm-stat-box" style="border-left-color:#8b5cf6;">
                <h3>Toplam Yorum</h3>
                <div class="value"><?php echo intval($stats['total']); ?></div>
            </div>
            <div class="qrm-stat-box" style="border-left-color:#3b82f6;">
                <h3>Genel Ortalama</h3>
                <div class="value"><?php echo $stats['approved'] > 0 ? esc_html(number_format($stats['avg'], 1)) . ' ★' : '—'; ?></div>
            </div>
            <div class="qrm-stat-box" style="border-left-color:<?php echo $unread > 0 ? '#f59e0b' : '#10b981'; ?>;">
                <h3>Okunmamış Form Gönderimi</h3>
                <div class="value">
                    <?php if ($unread > 0): ?>
                        <a href="<?php echo esc_url(qrm_pro_admin_url('qrms-yf-formlar', ['tab' => 'submissions'])); ?>"><?php echo intval($unread); ?></a>
                    <?php else: ?>
                        0
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="qrm-hub-grid">
            <?php foreach (qrm_pro_admin_pages() as $slug => $page): ?>
                <a class="qrm-hub-card" href="<?php echo esc_url(qrm_pro_admin_url($slug)); ?>">
                    <span class="dashicons <?php echo esc_attr($page['icon']); ?>" aria-hidden="true"></span>
                    <span>
                        <h2>
                            <?php echo esc_html($page['menu_title']); ?>
                            <?php echo wp_kses_post(qrm_pro_hub_card_badge($slug)); ?>
                        </h2>
                        <p><?php echo esc_html($page['desc']); ?></p>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

/**
 * Başlangıç kartındaki rozet. Menü rozetiyle aynı kaynaktan beslenir
 * (qrm_pro_menu_badge_state) ama burada okunabilir bir etiket olarak basılır.
 *
 * @param string $slug Sayfa slug'ı.
 * @return string
 */
function qrm_pro_hub_card_badge($slug) {
    $state = qrm_pro_menu_badge_state();

    if ($slug === 'qrms-yf-formlar' && $state['formlar'] > 0) {
        return '<span class="qrm-hub-card-badge">' . intval($state['formlar']) . ' yeni</span>';
    }

    if ($slug === 'qrms-yf-odul' && $state['odul']) {
        return '<span class="qrm-hub-card-badge">kurulum eksik</span>';
    }

    return '';
}
