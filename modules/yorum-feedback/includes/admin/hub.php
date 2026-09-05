<?php
if (!defined('ABSPATH')) exit;

// 2b. ADMİN: HUB EKRANI ("QR Menü > Yorum & Feedback" satırı)
//
// Sol admin menüsü tek seviyeye indirildi; modül satırı, modülün dört ekranını
// kart olarak listeleyen bu hub ekranını açar. Kartlar qrm_pro_admin_pages()
// listesinden üretilir, yani kayıtlı olan her sayfa burada da vardır; ikisi
// birbirinden ayrışamaz.
//
// Kartlar üç başlık altında toplanır — Yorumlar / Formlar / Ayarlar. Hangi
// kartın hangi başlığa gireceği sayfa kayıt defterindeki `group` anahtarından
// gelir (bkz. menu.php), başlıkların kendisi qrm_pro_admin_page_groups()'tan.
//
// Sunum (kart ızgarası, özet kutuları, mobil davranış) tüm modüllerde ortak
// olan QRMS_Admin::render_hub() bileşenindedir; burada yalnızca içerik üretilir.
//
// Üstteki özet, restoran sahibinin panele girer girmez görmesi gereken tek şeyi
// söyler: bekleyen iş var mı? Bu yüzden "Onay Bekleyen" ilk kutudur. Dört
// kutunun dördü de tıklanabilir ve ilgili filtrelenmiş listeye gider.

/** Hub ekranı. */
function qrm_pro_admin_hub() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Bu sayfayı görüntüleme yetkiniz yok.', 'qrms'));
    }

    $stats  = qrm_pro_review_stats();
    $unread = (int) qrm_cf_unread_total();

    $notice = '';
    if (!$stats['table_ok']) {
        $notice = '<div class="notice notice-error"><p><strong>'
            . esc_html__('Yorum tablosu veritabanında bulunamadı.', 'qrms') . '</strong> '
            . esc_html__('Bu yüzden hiçbir yorum listelenemiyor. Genel Ayarlar sayfasından lisansı yeniden doğrulayın; sorun sürerse veritabanı kullanıcısının tablo oluşturma yetkisi olmayabilir.', 'qrms')
            . '</p></div>';
    } else {
        $notice = qrm_pro_hub_empty_hint($stats);
    }

    QRMS_Admin::render_hub([
        'title'       => __('Yorum & Feedback', 'qrms'),
        'intro'       => __('Müşteri yorumlarınız, puanlama ayarlarınız, Google ödül sisteminiz ve kendi formlarınız burada.', 'qrms'),
        'notice'      => $notice,
        'stats'       => qrm_pro_hub_stats($stats, $unread),
        'card_groups' => qrm_pro_hub_card_groups(),
    ]);
}

/**
 * Hub üstündeki dört özet kutusu.
 *
 * Dördü de tıklanabilir: her kutu, saydığı kayıtların filtrelenmiş listesine
 * gider — sayıyı görüp "peki bunlar nerede?" diye aramak gerekmesin.
 *
 * @param array $stats  qrm_pro_review_stats() çıktısı.
 * @param int   $unread Okunmamış özel form gönderimi sayısı.
 * @return array render_hub()'ın `stats` argümanı.
 */
function qrm_pro_hub_stats(array $stats, $unread) {
    $bekleyen = (int) $stats['pending'];

    return [
        [
            'label' => __('Onay Bekleyen', 'qrms'),
            'value' => number_format_i18n($bekleyen),
            'url'   => qrm_pro_admin_url('qrms-yf-yorumlar', ['durum' => 'bekleyen']),
            // Bekleyen iş varken kutu hem renk hem de vurgu sınıfıyla öne çıkar;
            // aynı durum sol menüde de rozet olarak görünür (qrm_pro_menu_badge).
            'accent' => $bekleyen > 0 ? '#f59e0b' : '#10b981',
            'class'  => $bekleyen > 0 ? 'qrms-hub-stat-alert' : '',
        ],
        [
            'label'  => __('Toplam Yorum', 'qrms'),
            'value'  => number_format_i18n((int) $stats['total']),
            'url'    => qrm_pro_admin_url('qrms-yf-yorumlar'),
            'accent' => '#8b5cf6',
        ],
        [
            'label' => __('Genel Ortalama', 'qrms'),
            // Hiç yayında yorum yokken "—" hiçbir şey anlatmıyordu: ortalamanın
            // bozuk olduğu mu, henüz puan verilmediği mi belli değildi.
            'value' => $stats['approved'] > 0
                ? number_format_i18n($stats['avg'], 1) . ' ★'
                : __('Henüz puan yok', 'qrms'),
            'url'    => qrm_pro_admin_url('qrms-yf-yorumlar', ['durum' => 'onayli']),
            'accent' => '#3b82f6',
        ],
        [
            'label'  => __('Okunmamış Form Gönderimi', 'qrms'),
            'value'  => number_format_i18n((int) $unread),
            'url'    => qrm_pro_admin_url('qrms-yf-formlar', ['tab' => 'submissions']),
            'accent' => $unread > 0 ? '#f59e0b' : '#10b981',
            'class'  => $unread > 0 ? 'qrms-hub-stat-alert' : '',
        ],
    ];
}

/**
 * Hub kartları — üç başlık altında.
 *
 * Boş bir grup hiç basılmaz: kayıt defterinden bir sayfa çıkarıldığında başlığı
 * da kendiliğinden kaybolur.
 *
 * @return array render_hub()'ın `card_groups` argümanı.
 */
function qrm_pro_hub_card_groups() {
    $gruplar = [];

    foreach (qrm_pro_admin_page_groups() as $anahtar => $baslik) {
        $gruplar[$anahtar] = ['title' => $baslik, 'cards' => []];
    }

    foreach (qrm_pro_admin_pages() as $slug => $page) {
        $grup = isset($page['group']) ? $page['group'] : '';

        // Gruplanmamış (ya da bilinmeyen gruba yazılmış) bir sayfa kaybolmasın.
        if (!isset($gruplar[$grup])) {
            $grup = key($gruplar);
        }

        $gruplar[$grup]['cards'][] = [
            'url'   => qrm_pro_admin_url($slug),
            'title' => $page['menu_title'],
            'desc'  => $page['desc'],
            'icon'  => $page['icon'],
            'badge' => qrm_pro_hub_card_badge($slug),
        ];
    }

    return array_values(array_filter(
        $gruplar,
        function ($grup) {
            return !empty($grup['cards']);
        }
    ));
}

/**
 * Hiç yorum yokken kartların üstünde görünen tek satırlık yönlendirme.
 *
 * Boş bir panelde asıl soru "sistem bozuk mu?" değil, "ilk yorum nasıl gelir?"
 * olur: cevap QR kodun paylaşılması ve kısa kodun sayfaya konulmasıdır. Kısa
 * kod, suite'in ortak kopyalama betiğiyle (assets/js/admin.js, `data-qrms-copy`)
 * tek tıkla panoya alınır.
 *
 * @param array $stats qrm_pro_review_stats() çıktısı.
 * @return string HTML (yorum varsa boş string).
 */
function qrm_pro_hub_empty_hint(array $stats) {
    if ((int) $stats['total'] > 0) {
        return '';
    }

    $kisa_kod = '[qr_menu_reviews]';

    // `inline` sınıfı olmadan WordPress uyarıyı başlığın hemen altına taşır;
    // yönlendirme, ait olduğu yerde — kartların hemen üstünde — kalsın.
    return '<div class="notice notice-info inline qrms-hub-hint"><p>'
        . '<span class="qrms-hub-hint-text">'
        . esc_html__('Henüz yorum yok — QR kodunuzu müşterilerle paylaşın ve bu kısa kodu değerlendirme sayfanıza ekleyin:', 'qrms')
        . '</span> '
        . '<code class="qrms-sc-tag">' . esc_html($kisa_kod) . '</code> '
        . '<button type="button" class="qrms-sc-copy" data-qrms-copy="' . esc_attr($kisa_kod) . '">'
        . '<span class="qrms-sc-copy-text">' . esc_html__('Kopyala', 'qrms') . '</span>'
        . '</button>'
        . '</p></div>';
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

    if ($slug === 'qrms-yf-yorumlar' && $state['bekleyen'] > 0) {
        /* translators: %s: onay bekleyen yorum sayısı. */
        return sprintf(__('%s onay bekliyor', 'qrms'), number_format_i18n((int) $state['bekleyen']));
    }

    if ($slug === 'qrms-yf-formlar' && $state['formlar'] > 0) {
        /* translators: %s: okunmamış gönderim sayısı. */
        return sprintf(__('%s yeni', 'qrms'), number_format_i18n((int) $state['formlar']));
    }

    if ($slug === 'qrms-yf-odul' && $state['odul']) {
        return __('kurulum eksik', 'qrms');
    }

    return '';
}
