<?php
if (!defined('ABSPATH')) exit;

// 2b. ADMİN: HUB EKRANI ("QR Menü > Yorum & Feedback" satırı)
//
// Sol admin menüsü tek seviyeye indirildi; modül satırı, modülün yedi ekranını
// kart olarak listeleyen bu hub ekranını açar. Kartlar qrm_pro_admin_pages()
// listesinden üretilir, yani kayıtlı olan her sayfa burada da vardır; ikisi
// birbirinden ayrışamaz.
//
// Sunum (kart ızgarası, özet kutuları, mobil davranış) tüm modüllerde ortak
// olan QRMS_Admin::render_hub() bileşenindedir; burada yalnızca içerik üretilir.
//
// Üstteki özet, restoran sahibinin panele girer girmez görmesi gereken tek şeyi
// söyler: bekleyen iş var mı? Bu yüzden "Onay Bekleyen" ilk kutudur.

/** Hub ekranı. */
function qrm_pro_admin_hub() {
    if (!current_user_can('manage_options')) {
        wp_die('Bu sayfayı görüntüleme yetkiniz yok.');
    }

    $stats  = qrm_pro_review_stats();
    $unread = (int) qrm_cf_unread_total();

    $notice = '';
    if (!$stats['table_ok']) {
        $notice = '<div class="notice notice-error"><p><strong>Yorum tablosu veritabanında bulunamadı.</strong> '
            . 'Bu yüzden hiçbir yorum listelenemiyor. Genel Ayarlar sayfasından lisansı yeniden doğrulayın; '
            . 'sorun sürerse veritabanı kullanıcısının tablo oluşturma yetkisi olmayabilir.</p></div>';
    }

    $cards = [];
    foreach (qrm_pro_admin_pages() as $slug => $page) {
        $cards[] = [
            'url'   => qrm_pro_admin_url($slug),
            'title' => $page['menu_title'],
            'desc'  => $page['desc'],
            'icon'  => $page['icon'],
            'badge' => qrm_pro_hub_card_badge($slug),
        ];
    }

    QRMS_Admin::render_hub([
        'title'  => 'Yorum & Feedback',
        'intro'  => 'Müşteri yorumlarınız, puanlama ayarlarınız, Google ödül sisteminiz ve kendi formlarınız burada.',
        'notice' => $notice,
        'stats'  => [
            [
                'label'  => 'Onay Bekleyen',
                'value'  => $stats['pending'],
                'url'    => $stats['pending'] > 0 ? qrm_pro_admin_url('qrms-yf-yorumlar', ['durum' => 'bekleyen']) : '',
                'accent' => $stats['pending'] > 0 ? '#f59e0b' : '#10b981',
            ],
            [
                'label'  => 'Toplam Yorum',
                'value'  => $stats['total'],
                'accent' => '#8b5cf6',
            ],
            [
                'label'  => 'Genel Ortalama',
                'value'  => $stats['approved'] > 0 ? number_format($stats['avg'], 1) . ' ★' : '—',
                'accent' => '#3b82f6',
            ],
            [
                'label'  => 'Okunmamış Form Gönderimi',
                'value'  => $unread,
                'url'    => $unread > 0 ? qrm_pro_admin_url('qrms-yf-formlar', ['tab' => 'submissions']) : '',
                'accent' => $unread > 0 ? '#f59e0b' : '#10b981',
            ],
        ],
        'cards'  => $cards,
    ]);
}

/**
 * Hub kartındaki rozet metni. Menü rozetiyle aynı kaynaktan beslenir
 * (qrm_pro_menu_badge_state) ama burada okunabilir bir etiket olur.
 *
 * Düz metin döner; kaçış render_hub()'ın işidir.
 *
 * @param string $slug Sayfa slug'ı.
 * @return string
 */
function qrm_pro_hub_card_badge($slug) {
    $state = qrm_pro_menu_badge_state();

    if ($slug === 'qrms-yf-formlar' && $state['formlar'] > 0) {
        return intval($state['formlar']) . ' yeni';
    }

    if ($slug === 'qrms-yf-odul' && $state['odul']) {
        return 'kurulum eksik';
    }

    return '';
}
