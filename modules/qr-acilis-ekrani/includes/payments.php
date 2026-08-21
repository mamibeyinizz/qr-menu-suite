<?php
defined( 'ABSPATH' ) || exit;

/**
 * Ödeme yöntemi rozetleri.
 *
 * İkonlar kod ile üretilen inline SVG'dir; medya kütüphanesi kullanılmaz ve
 * harici dosya çağrılmaz. Marka logolarının birebir kopyası ÜRETİLMEZ — her
 * yöntem, marka rengini taşıyan dolgulu bir "chip" arkalığı + üzerinde beyaz
 * jenerik glif ile temsil edilir (Apple Pay / Google Pay tarzı ödeme seçici dili).
 */
trait QRMS_AE_Payments {

    /**
     * Desteklenen ödeme yöntemleri. Anahtarlar sabittir; option içinde bu
     * anahtarlardan oluşan bir dizi saklanır.
     */
    private function payment_methods_map() {
        return array(
            'nakit' => array(
                'label' => 'Nakit',
                'color' => '#1a9850',
                'glyph' => 'note',
            ),
            // Etiket bilinçli olarak kısa: satır sarmasını azaltır ve
            // referanstaki kompakt dille uyumludur.
            'kart' => array(
                'label' => 'Kart',
                'color' => '#2b6cb0',
                'glyph' => 'card',
            ),
            // Doğru marka adı Edenred'dir; "Ticket Restaurant" da aynı firmadır.
            'edenred' => array(
                'label' => 'Edenred',
                'color' => '#e2001a',
                'glyph' => 'card',
            ),
            'multinet' => array(
                'label' => 'Multinet',
                'color' => '#f36f21',
                'glyph' => 'card',
            ),
            'sodexo' => array(
                'label' => 'Sodexo',
                'color' => '#0a3d91',
                'glyph' => 'card',
            ),
            'setcard' => array(
                'label' => 'Setcard',
                'color' => '#0f9d6c',
                'glyph' => 'card',
            ),
        );
    }

    /**
     * 24x24 viewBox, iki katmanlı glif tanımları:
     *   1) arka plan şekli - marka renginde dolgu (currentColor; renk dışarıdan
     *      --sp-pay-color ile verilir, böylece aynı symbol tüm kart yöntemleri
     *      için tekrar kullanılabilir)
     *   2) ön plan glifi - beyaz, aynı şablondan türetilmiş detay
     *
     * Değerler kod içinde sabittir, kullanıcı girdisi içermez.
     */
    private function payment_glyph_shapes() {
        // Arkalıklar TAM OPAK marka rengindedir (opacity düşürülmez); arkaplan
        // fotoğrafından ayrışsınlar diye ince koyu bir kenarlık taşırlar.
        $edge = ' stroke="rgba(0,0,0,0.15)" stroke-width="0.5"';

        return array(
            // Nakit: madeni para arkalık + üzerinde beyaz banknot penceresi
            'note' =>
                '<circle cx="12" cy="12" r="10.2" fill="currentColor"' . $edge . '/>'
                . '<rect x="5.4" y="8.9" width="13.2" height="6.2" rx="1.6" fill="#fff"/>'
                . '<circle cx="12" cy="12" r="1.9" fill="currentColor"/>',

            // Kart yöntemleri: yuvarlak köşeli kart arkalık + beyaz şerit + çip
            'card' =>
                '<rect x="1" y="4" width="22" height="16" rx="5" fill="currentColor"' . $edge . '/>'
                . '<rect x="3.9" y="7.9" width="16.2" height="3.2" rx="1.6" fill="#fff"/>'
                . '<rect x="3.9" y="13.2" width="6.4" height="4.2" rx="1.3" fill="#fff" opacity=".85"/>',
        );
    }

    /**
     * Option'da saklanan ham değerden, geçerli ve sıralı yöntem anahtarlarını çıkarır.
     * Sıra, payment_methods_map() içindeki tanım sırasıdır.
     */
    private function get_active_payment_methods($opts) {
        $saved = isset($opts['payment_methods']) && is_array($opts['payment_methods'])
            ? $opts['payment_methods']
            : array();

        $active = array();
        foreach ($this->payment_methods_map() as $key => $method) {
            if (in_array($key, $saved, true)) {
                $active[] = $key;
            }
        }
        return $active;
    }

    /**
     * SVG sprite. Sayfada bir kez basılır ve yalnızca fiilen kullanılan
     * glifleri içerir (aynı glifi paylaşan yöntemler tek symbol'ü tekrar kullanır;
     * marka rengi <use> tarafında currentColor ile verildiği için bu mümkün).
     */
    private function payment_sprite_markup($active_keys) {
        if (empty($active_keys)) return '';

        $map    = $this->payment_methods_map();
        $shapes = $this->payment_glyph_shapes();
        $needed = array();

        foreach ($active_keys as $key) {
            if (!isset($map[$key])) continue;
            $glyph = $map[$key]['glyph'];
            if (isset($shapes[$glyph])) {
                $needed[$glyph] = $shapes[$glyph];
            }
        }
        if (empty($needed)) return '';

        $out = '<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true" focusable="false">';
        foreach ($needed as $glyph => $markup) {
            // $markup kod içinde tanımlı sabittir; kullanıcı girdisi içermez.
            $out .= '<symbol id="sp-pay-' . esc_attr($glyph) . '" viewBox="0 0 24 24">' . $markup . '</symbol>';
        }
        $out .= '</svg>';

        return $out;
    }

    /**
     * Tek bir rozetin markup'ı. Frontend'de tıklanabilir değildir (bilgilendirme
     * amaçlı <span>); admin önizlemesinde de aynı markup kullanılır.
     *
     * $with_icon = false → sadece yazı (text_only ve ikonsuz marquee modu).
     */
    private function payment_badge_markup($key, $with_icon = true) {
        $map = $this->payment_methods_map();
        if (!isset($map[$key])) return '';

        $method = $map[$key];

        $icon = $with_icon
            ? '<svg class="splash-pay-icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">'
                . '<use href="#sp-pay-' . esc_attr($method['glyph']) . '"></use>'
                . '</svg>'
            : '';

        return '<span class="splash-pay" style="--sp-pay-color:' . esc_attr($method['color']) . '">'
            . $icon
            . '<span class="splash-pay-label">' . esc_html($method['label']) . '</span>'
            . '</span>';
    }

    /**
     * Kayan şerit (marquee) turunun süresi. Yöntem sayısıyla orantılıdır ki
     * hız, kaç rozet seçildiğinden bağımsız olarak sabit kalsın.
     * Admin'deki "hız" değeri 6 yöntemlik tam bir tur için saniye cinsindendir.
     */
    private function payment_marquee_duration($opts, $count) {
        $base = $this->opt_int($opts, 'payment_marquee_speed', 6, 60);
        if ($count < 1) $count = 1;

        $duration = ($base / 6) * $count;
        return round(max(4, min(120, $duration)), 1);
    }

    /**
     * Frontend ödeme satırı. Hiçbir yöntem seçili değilse DOM'a hiçbir şey girmez.
     *
     * Render biçimi payment_display_mode ile belirlenir; hangi yöntemlerin
     * basılacağı her modda aynı tikleme sisteminden gelir.
     */
    private function render_payment_row($opts) {
        $active = $this->get_active_payment_methods($opts);
        if (empty($active)) return;

        $mode = $this->opt_choice($opts, 'payment_display_mode', array('icon_text', 'text_only', 'marquee'));
        $with_icon = ($mode === 'icon_text')
            || ($mode === 'marquee' && !empty($opts['payment_marquee_with_icon']));

        if ($with_icon) {
            echo $this->payment_sprite_markup($active);
        }

        $badges = '';
        foreach ($active as $key) {
            $badges .= $this->payment_badge_markup($key, $with_icon);
        }

        if ($mode === 'marquee') {
            $duration = $this->payment_marquee_duration($opts, count($active));

            // Seamless loop: içerik birebir iki kez basılır, şerit -%50
            // ötelenir. İkinci kopya ekran okuyuculardan gizlenir.
            echo '<div class="splash-pay-row is-marquee' . ($with_icon ? '' : ' is-text-only') . '"'
                . ' style="--sp-marquee-duration:' . esc_attr($duration) . 's">';
            echo '<div class="splash-pay-track">';
            echo '<div class="splash-pay-seq">' . $badges . '</div>';
            echo '<div class="splash-pay-seq" aria-hidden="true">' . $badges . '</div>';
            echo '</div>';
            echo '</div>';
            return;
        }

        echo '<div class="splash-pay-row' . ($with_icon ? '' : ' is-text-only') . '">';
        echo $badges;
        echo '</div>';
    }

    /**
     * Admin "Ödeme" sekmesi: 6 checkbox + her birinin yanında gerçek SVG önizlemesi.
     * Önizleme frontend ile aynı sprite'tan beslenir; ikon değişince otomatik güncellenir.
     */
    private function render_payment_admin_field($opts) {
        $map    = $this->payment_methods_map();
        $active = $this->get_active_payment_methods($opts);

        echo $this->payment_sprite_markup(array_keys($map));

        echo '<div class="splash-pay-admin-grid">';
        foreach ($map as $key => $method) {
            $id = 'splash_pay_' . $key;
            echo '<label class="splash-pay-admin-item" for="' . esc_attr($id) . '">';
            echo '<input type="checkbox" id="' . esc_attr($id) . '" name="payment_methods[]" value="' . esc_attr($key) . '" ' . checked(in_array($key, $active, true), true, false) . ' />';
            echo $this->payment_badge_markup($key);
            echo '</label>';
        }
        echo '</div>';
    }
}
