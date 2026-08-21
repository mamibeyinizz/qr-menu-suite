<?php
defined( 'ABSPATH' ) || exit;

/**
 * Aksiyon rozetlerinin çizgisel ikonları.
 *
 * Hepsi 24x24 viewBox, fill yok, stroke=currentColor, stroke-width 1.6.
 * "Nizami" görünümün tek kaynağı budur: dolgulu ve çizgisel ikon
 * karıştırılmaz, tüm gliflerin optik ağırlığı aynı tutulur.
 *
 * Sosyal ikonlar da aynı çizgisel dilde çizilir; marka logolarının renkli
 * kopyası kullanılmaz.
 */
trait QRMS_AE_Icons {

    private function icon_paths() {
        return array(
            // İletişim - telefon ahizesi
            'phone' => '<path d="M21 16.9v2.6a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 1.1 3.7 2 2 0 0 1 3.1 1.5h2.6a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L6.8 9.2a16 16 0 0 0 6 6l1.1-1.1a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/>',

            // Rezervasyon - takvim
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',

            // Yorum Yap - yıldız
            'star' => '<path d="m12 3 2.7 5.5 6 .9-4.3 4.2 1 6-5.4-2.8L6.6 19.6l1-6L3.3 9.4l6-.9L12 3Z"/>',

            // Wifi
            'wifi' => '<path d="M5 12.6a11 11 0 0 1 14 0M1.5 9.1a16 16 0 0 1 21 0M8.5 16.1a6 6 0 0 1 7 0"/><path d="M12 19.5h.01"/>',

            // Instagram - yuvarlak köşeli kare + lens + flaş noktası
            'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.2 6.8h.01"/>',

            // Facebook - "f" harf formu, çizgisel (gövde + orta çizgi)
            'facebook' => '<path d="M15 3.5h-1.8A3.7 3.7 0 0 0 9.5 7.2V20.5"/><path d="M6.6 10.6h7.2"/>',

            // X (Twitter) - harf formu. Çizgisel çapraz "kapat" ikonuyla
            // karıştığı için DOLGU ile çizilir ve köşegenler eşit kalınlıkta
            // değildir; sağ üst / sol alt uçlar kalınlaşır.
            // Kalınlıklar, yanındaki çizgisel ikonlarla aynı optik ağırlıkta
            // durması için ölçüldü; kapladığı alan da onlarla aynı (3.5-20.5).
            'x' => '<path fill="currentColor" stroke="none" d="M3.5 3h3.4l13.6 18h-3.4z"/>'
                 . '<path fill="currentColor" stroke="none" d="M18.2 3h2.3L6.1 21H3.8z"/>',

            // YouTube - yuvarlak köşeli oynatıcı çerçevesi + dolgulu oynat üçgeni
            'youtube' => '<rect x="2.5" y="6" width="19" height="12" rx="4"/>'
                       . '<path fill="currentColor" stroke="none" d="M10.3 9.3v5.4l4.9-2.7Z"/>',

            // TikTok - nota gövdesi + ses dalgası, marka glifinin sadeleştirilmiş hali
            'tiktok' => '<path d="M14 3v10.6a3.4 3.4 0 1 1-3-3.37"/><path d="M14 3c.4 3.1 2.7 5.3 5.8 5.6"/>',

            // WhatsApp - kuyruklu konuşma balonu + iç kıvrım
            'whatsapp' => '<path d="M12 3a9 9 0 0 0-7.8 13.5L3 21l4.7-1.2A9 9 0 1 0 12 3Z"/><path d="M8.3 9.6c.5 3.2 2.7 5.4 6 5.9"/>',

            // LinkedIn - yuvarlak köşeli kare + "in" glifi
            'linkedin' => '<rect x="3" y="3" width="18" height="18" rx="4"/><path d="M8 10.5V17"/>'
                        . '<circle cx="8" cy="7.2" r="0.9" fill="currentColor" stroke="none"/>'
                        . '<path d="M12.3 17v-4a2.4 2.4 0 0 1 4.8 0v4M12.3 10.5v1.3"/>',

            // Pinterest - daire + "P" kancası
            'pinterest' => '<circle cx="12" cy="12" r="9"/><path d="M9.3 19c1-3.3 1.8-6.6 2.4-9a2.4 2.4 0 1 1 2.9 1.7c-.6 2-2 3.1-3.6 3.1"/>',

            // Telegram - kağıt uçak
            'telegram' => '<path d="m21 3-18 7.5 6.5 2.3M21 3l-3.3 17-6.2-6.2M21 3 11 15.6"/>',

            // Spotify - daire + üç ses dalgası
            'spotify' => '<circle cx="12" cy="12" r="9"/>'
                       . '<path stroke-linecap="round" d="M7 10.3c3.5-1.2 7-.9 9.8.9M7.4 13.4c2.9-.9 5.9-.7 8.3.8M7.9 16.2c2.3-.6 4.6-.5 6.6.7"/>',
        );
    }

    /**
     * Sosyal medya platform kataloğu. Dizi sırası aynı zamanda render
     * öncelik sırasıdır: instagram/facebook/youtube/x en üstte sabit,
     * kalanlar admin'de aktif edilme sırasına göre bunların ardına eklenir.
     */
    private function social_media_map() {
        return array(
            'instagram' => array('label' => 'Instagram',    'icon' => 'instagram'),
            'facebook'  => array('label' => 'Facebook',     'icon' => 'facebook'),
            'youtube'   => array('label' => 'YouTube',      'icon' => 'youtube'),
            'x'         => array('label' => 'X (Twitter)',  'icon' => 'x'),
            'tiktok'    => array('label' => 'TikTok',       'icon' => 'tiktok'),
            'whatsapp'  => array('label' => 'WhatsApp',     'icon' => 'whatsapp'),
            'linkedin'  => array('label' => 'LinkedIn',     'icon' => 'linkedin'),
            'pinterest' => array('label' => 'Pinterest',    'icon' => 'pinterest'),
            'telegram'  => array('label' => 'Telegram',     'icon' => 'telegram'),
            'spotify'   => array('label' => 'Spotify',      'icon' => 'spotify'),
        );
    }

    /**
     * social_media / social_media_active option'larını okunabilir hale
     * getirir: ['active' => [key, ...], 'urls' => [key => url]].
     *
     * Geriye dönük uyum: social_media_active hiç kaydedilmemişse (eklenti
     * v3.5 veya öncesinden yükseltildiyse) eski 3 sabit alanlı social_links
     * burada otomatik olarak yeni sisteme taşınır — admin tekrar kaydetmeden
     * de eski bağlantılar frontend'de görünmeye devam eder.
     *
     * En fazla 6 aktif platformla sınırlanır (save_settings() zaten bu
     * sınırı uyguluyor; burada savunma amaçlı tekrarlanır).
     */
    private function resolve_social_media_state($opts) {
        $map    = $this->social_media_map();
        $active = isset($opts['social_media_active']) && is_array($opts['social_media_active']) ? $opts['social_media_active'] : array();
        $urls   = isset($opts['social_media']) && is_array($opts['social_media']) ? $opts['social_media'] : array();

        if (empty($active)) {
            $legacy     = isset($opts['social_links']) && is_array($opts['social_links']) ? $opts['social_links'] : array();
            $legacy_map = array('instagram' => 'instagram', 'facebook' => 'facebook', 'twitter' => 'x');
            foreach ($legacy_map as $legacy_key => $new_key) {
                if (!empty($legacy[$legacy_key])) {
                    $urls[$new_key] = $legacy[$legacy_key];
                    $active[]       = $new_key;
                }
            }
        }

        $active = array_values(array_unique(array_intersect($active, array_keys($map))));
        $active = array_slice($active, 0, 6);

        return array('active' => $active, 'urls' => $urls);
    }

    /**
     * Tek bir çizgisel ikon. Rozetin kendisi aria-label taşıdığı için
     * ikon daima aria-hidden'dır.
     */
    private function icon_svg($name, $size = 22) {
        $paths = $this->icon_paths();
        if (!isset($paths[$name])) return '';

        return '<svg class="splash-icon" viewBox="0 0 24 24" width="' . absint($size) . '" height="' . absint($size) . '"'
            . ' fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"'
            . ' aria-hidden="true" focusable="false">'
            . $paths[$name]
            . '</svg>';
    }
}
