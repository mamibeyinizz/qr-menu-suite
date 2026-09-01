<?php
defined( 'ABSPATH' ) || exit;

trait QRMS_AE_Frontend {

    /**
     * Elementor editörü ve Customizer önizlemesinde splash basılmamalı;
     * sadece gerçek ana sayfa ziyaretinde render edilir.
     */
    private function should_render() {
        if (!is_front_page()) return false;
        if (isset($_GET['elementor-preview'])) return false;
        if (function_exists('is_customize_preview') && is_customize_preview()) return false;
        return true;
    }

    /**
     * Efektif renk şeması: 'light' | 'dark'.
     *
     * VARSAYILAN 'dark'. Önceki sürümde arkaplan görseli varken 'light'
     * varsayılıyordu; gerçek fotoğraf üzerinde test edilince yanlış olduğu
     * görüldü — kırmızı/alev tonlu, yüksek kontrastlı bir görselde alta
     * doğru beyaza geçiş metni okunmaz yapıyor.
     */
    private function resolve_scheme($opts) {
        $scheme = isset($opts['bg_scheme']) ? $opts['bg_scheme'] : 'auto';
        if ($scheme === 'light' || $scheme === 'dark') {
            return $scheme;
        }
        if (!empty($opts['bg_image'])) {
            return 'dark';
        }
        $luminance = $this->hex_luminance($opts['bg_color'] ?: $this->defaults['bg_color']);
        return ($luminance > 140) ? 'light' : 'dark';
    }

    /**
     * wp_head, öncelik 2: FOUC'u önlemek için içerikten önce çalışır.
     *
     * ÖNEMLİ: Bu çıktı $_COOKIE değerinden TAMAMEN bağımsızdır. Ana sayfa için
     * üretilen HTML her ziyaretçide birebir aynıdır (tam sayfa cache güvenli);
     * "splash gösterilsin mi" kararı tamamen client-side, document.cookie
     * okunarak veriliyor.
     *
     * Buraya SADECE değişken değerler basılır; tüm selector'lar statik ve
     * cache'lenebilir splash-frontend.css içinde durur.
     */
    /**
     * Splash'ın tüm ayarlanabilir değerleri CSS özel değişkeni olarak.
     *
     * Tek kaynak: frontend `:root` üzerinde, admin önizlemesi ise doğrudan
     * overlay üzerinde kullanır (özel değişkenler alt elemanlara miras kalır).
     * Böylece önizleme, frontend'in renk/ölçü hesabının kopyası olmaz.
     *
     * @return array<string,string> Değişken adı => değer.
     */
    private function build_css_vars($opts) {
        $bg_color          = $opts['bg_color'] ?: $this->defaults['bg_color'];
        $button_bg_color   = $opts['button_bg_color'] ?: $this->defaults['button_bg_color'];
        $button_text_color = $opts['button_text_color'] ?: $this->defaults['button_text_color'];

        $overlay     = max(0, min(100, absint($opts['bg_overlay_strength']))) / 100;
        $btn_alpha   = max(0, min(100, absint($opts['button_opacity']))) / 100;
        $accent_rgb  = $this->hex_to_rgb_triplet($button_bg_color, $this->hex_to_rgb_triplet($this->defaults['button_bg_color']));

        // --- Buton yüzeyi (rozetler + isteğe bağlı olarak CTA) ---
        $surface_rgb   = $this->hex_to_rgb_triplet($this->opt_hex($opts, 'btn_surface_color'), '255,255,255');
        $surface_alpha = $this->opt_int($opts, 'btn_surface_opacity', 0, 100) / 100;
        $cta_uses_surface = !empty($opts['btn_surface_apply_cta']);
        $cta_bg = $cta_uses_surface
            ? 'rgba(' . $surface_rgb . ',' . $surface_alpha . ')'
            : 'rgba(' . $accent_rgb . ',' . $btn_alpha . ')';

        // --- Logo şeridi ---
        $logo_bar_h     = $this->opt_int($opts, 'logo_bar_height', 48, 220);
        $logo_bar_rgb   = $this->hex_to_rgb_triplet($this->opt_hex($opts, 'logo_bar_color'), '0,0,0');
        $logo_bar_alpha = $this->opt_int($opts, 'logo_bar_opacity', 0, 100) / 100;

        // --- Yüklenme göstergesi ---
        $loader_rgb  = $this->hex_to_rgb_triplet($this->opt_hex($opts, 'loader_color'), '255,255,255');
        $loader_size = $this->opt_int($opts, 'loader_size', 18, 44);

        // --- Dil bayrağı (logo şeridi, 3:2 kutu) ---
        $flag_size = $this->opt_int($opts, 'ceviri_flag_size', 20, 48);

        return array(
            '--sp-bg'              => $bg_color,
            '--sp-accent'          => $button_bg_color,
            '--sp-accent-rgb'      => $accent_rgb,
            '--sp-btn-text'        => $button_text_color,
            '--sp-btn-alpha'       => (string) $btn_alpha,
            '--sp-overlay'         => (string) $overlay,
            '--sp-seconds'         => absint($opts['redirect_seconds']) . 's',
            // Rozet/cam yüzey dolgusu - admin'den gelir.
            '--splash-btn-bg'      => $surface_rgb,
            '--splash-btn-opacity' => (string) $surface_alpha,
            '--sp-cta-bg'          => $cta_bg,
            // Logo şeridi
            '--sp-logo-bar-h'      => $logo_bar_h . 'px',
            '--sp-logo-bar-rgb'    => $logo_bar_rgb,
            '--sp-logo-bar-alpha'  => (string) $logo_bar_alpha,
            // Yüklenme göstergesi (logo şeridinin sağı)
            '--sp-loader'          => $this->opt_hex($opts, 'loader_color'),
            '--sp-loader-rgb'      => $loader_rgb,
            '--sp-loader-size'     => $loader_size . 'px',
            // Dil bayrağı (logo şeridinin sol tarafı)
            '--sp-flag-size'       => $flag_size . 'px',
        );
    }

    /**
     * build_css_vars() çıktısını "ad: değer;" listesine çevirir.
     */
    private function css_vars_declarations($opts) {
        $out = '';
        foreach ($this->build_css_vars($opts) as $name => $value) {
            $out .= $name . ': ' . esc_attr($value) . '; ';
        }
        return trim($out);
    }

    public function print_critical_head() {
        if (!$this->should_render()) return;

        $opts             = $this->get_options();
        $bg_id            = absint($opts['bg_image']);
        $dismiss_minutes  = absint($opts['dismiss_duration']);
        ?>
        <style id="splash-critical-style">
            :root { <?php echo $this->css_vars_declarations($opts); ?> }
            html.splash-loading:has(#custom-splash-overlay) { background: var(--sp-bg) !important; }
            html.splash-loading:has(#custom-splash-overlay) body { overflow: hidden; visibility: hidden; }
            html.splash-loading #custom-splash-overlay { display: block !important; visibility: visible; }
            html.splash-loading .splash-modal { visibility: visible; }
        </style>
        <?php
        // LCP elemanı arkaplan görselidir; preload olmadan açılış gözle görülür
        // şekilde gecikir. Çıktı cache güvenliği için cookie'den bağımsızdır.
        if ($bg_id) {
            $srcset = wp_get_attachment_image_srcset($bg_id, 'full');
            if ($srcset) {
                echo '<link rel="preload" as="image" imagesrcset="' . esc_attr($srcset) . '" imagesizes="100vw" fetchpriority="high" />' . "\n";
            } else {
                $src = wp_get_attachment_image_url($bg_id, 'full');
                if ($src) {
                    echo '<link rel="preload" as="image" href="' . esc_url($src) . '" fetchpriority="high" />' . "\n";
                }
            }
        }
        ?>
        <script id="splash-critical-script">
            (function () {
                try {
                    // 0 = her ziyarette göster: eski oturum çerezini sil ve
                    // splash_dismissed kontrolünü atla. Değer siteden gelir,
                    // ziyaretçi çerezinden değil (tam sayfa cache güvenli).
                    var dismissMinutes = <?php echo (int) $dismiss_minutes; ?>;
                    if (dismissMinutes === 0) {
                        document.cookie = 'splash_dismissed=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; SameSite=Lax';
                    }
                    if (dismissMinutes === 0 || document.cookie.indexOf('splash_dismissed=1') === -1) {
                        document.documentElement.classList.add('splash-loading');
                        // Zaman aşımı sigortası: frontend.js hiç çalışmazsa (engellendi,
                        // optimizasyon eklentisi bozdu, overlay cache'te eksik kaldı vb.)
                        // sayfa sonsuza kadar gizli kalmasın. JS normal çalıştığında
                        // dismissSplash() bu zamanlayıcıyı iptal eder.
                        window.__splashFailsafe = setTimeout(function () {
                            var overlay = document.getElementById('custom-splash-overlay');
                            if (!overlay || getComputedStyle(overlay).display === 'none') {
                                document.documentElement.classList.remove('splash-loading');
                            }
                        }, 5000);
                    }
                } catch (e) {}
            })();
        </script>
        <noscript>
            <style>
                html.splash-loading body { overflow: auto !important; visibility: visible !important; }
                #custom-splash-overlay { display: none !important; }
            </style>
        </noscript>
        <?php
    }

    public function enqueue_frontend_assets() {
        if (!$this->should_render()) return;

        wp_enqueue_style(
            'qrms-ae-splash',
            QRMS_PLUGIN_URL . 'modules/qr-acilis-ekrani/assets/css/splash.css',
            array(),
            QRMS_Helpers::asset_version('modules/qr-acilis-ekrani/assets/css/splash.css')
        );
        wp_enqueue_script(
            'qrms-ae-splash',
            QRMS_PLUGIN_URL . 'modules/qr-acilis-ekrani/assets/js/splash.js',
            array(),
            QRMS_Helpers::asset_version('modules/qr-acilis-ekrani/assets/js/splash.js'),
            true
        );
        wp_script_add_data('qrms-ae-splash', 'strategy', 'defer');
    }

    public function handle_frontend() {
        if (!$this->should_render()) return;

        // $_COOKIE'ye göre dallanma YOK: tam sayfa cache altında sunucu çıktısı
        // her ziyaretçide aynı olmalı. Overlay HTML'i her zaman basılır (satır
        // içi style="display:none" ile); "gösterilsin mi" kararını JS verir.
        $opts = $this->get_options();
        $this->output_splash($opts);
    }

    /**
     * Arkaplan görseli katmanı. bg_image boşsa hiçbir şey basılmaz ve
     * mevcut düz bg_color davranışı aynen sürer.
     */
    private function render_background_layer($opts) {
        $bg_id = absint($opts['bg_image']);
        if (!$bg_id) return;

        $wide_id = absint($opts['bg_image_wide']);

        $img_attr = array(
            'class'         => 'splash-bg-img',
            'alt'           => '',
            'sizes'         => '100vw',
            'fetchpriority' => 'high',
            'decoding'      => 'async',
            'loading'       => 'eager',
        );

        if ($wide_id) {
            $wide_srcset = wp_get_attachment_image_srcset($wide_id, 'full');
            if (!$wide_srcset) {
                $wide_srcset = wp_get_attachment_image_url($wide_id, 'full');
            }
            echo '<picture class="splash-bg-picture">';
            if ($wide_srcset) {
                echo '<source media="(min-aspect-ratio: 1/1)" srcset="' . esc_attr($wide_srcset) . '" sizes="100vw" />';
            }
            echo wp_get_attachment_image($bg_id, 'full', false, $img_attr);
            echo '</picture>';
        } else {
            echo wp_get_attachment_image($bg_id, 'full', false, $img_attr);
        }

        // Okunabilirlik katmanı: tek gradient, blur YOK. Gradient'in kendisi
        // statik CSS'te; sadece alt alfa katkısı --sp-overlay ile gelir.
        echo '<div class="splash-bg-overlay" aria-hidden="true"></div>';
    }

    /**
     * Logo şeridinin sağındaki yüklenme göstergesi.
     *
     * Eski alt ilerleme çubuğunun (.splash-progress-track) yerini alır.
     * Tüm hareket CSS keyframe'lerinden gelir; JS animasyon döngüsü yoktur.
     *
     * Tipler:
     *   spinner - dönen ince yay (sonsuz)
     *   ring    - geri sayımı boşalan ilerleme halkası (redirect_seconds'a bağlı)
     *   dots    - sırayla nabzeden üç nokta (sonsuz)
     *   pulse   - genişleyip sönen halka (sonsuz)
     *   none    - hiç basılmaz
     *
     * 'ring' seçiliyken otomatik kapanma kapalıysa (redirect_seconds = 0)
     * gösterecek bir süre olmadığı için spinner'a düşer.
     */
    private function render_loader($opts, $redirect_seconds) {
        $type = $this->opt_choice($opts, 'loader_type', array('spinner', 'ring', 'dots', 'pulse', 'none'));
        if ($type === 'none') return;

        if ($type === 'ring' && $redirect_seconds <= 0) {
            $type = 'spinner';
        }

        // Konum şimdilik sabit; option ileride başka köşeleri de destekleyebilsin
        // diye saklanıyor ve sınıf adına yazılıyor.
        $position = $this->opt_choice($opts, 'loader_position', array('top-right'));

        // aria-label Türkçe yedek: sunucu dil seçmez (tam sayfa cache).
        // i18n açıksa lang_data data-sp-* + data-sp-attr="aria-label" basar;
        // splash.js applyLang() nitelikteki metni ziyaretçi diline yazar.
        echo '<div class="splash-loader sp-loader-' . esc_attr($position) . ' is-' . esc_attr($type) . '" role="status"'
            . $this->lang_data($opts, 'loading', 'Yükleniyor', 'aria-label')
            . ' aria-label="Yükleniyor">';

        if ($type === 'ring') {
            // 18 yarıçaplı daire: çevre = 2*pi*18 ≈ 113.1
            echo '<svg class="sp-loader-ring" viewBox="0 0 44 44" aria-hidden="true" focusable="false">'
                . '<circle class="sp-loader-ring-track" cx="22" cy="22" r="18" />'
                . '<circle class="sp-loader-ring-bar" id="splash-loader-ring" cx="22" cy="22" r="18" />'
                . '</svg>';
        } elseif ($type === 'dots') {
            echo '<span class="sp-loader-dot" aria-hidden="true"></span>'
                . '<span class="sp-loader-dot" aria-hidden="true"></span>'
                . '<span class="sp-loader-dot" aria-hidden="true"></span>';
        } elseif ($type === 'pulse') {
            echo '<span class="sp-loader-pulse-core" aria-hidden="true"></span>'
                . '<span class="sp-loader-pulse-wave" aria-hidden="true"></span>';
        } else {
            echo '<span class="sp-loader-arc" aria-hidden="true"></span>';
        }

        echo '</div>';
    }

    /**
     * Aksiyon rozetleri: btn2/btn3/btn4 (link) + btn5 (wifi modal).
     * CTA'nın ÜSTÜNDE, tek satır, asla sarmaz (4x56 + 3x14 = 266px).
     *
     * Linki boş olan rozet DOM'a hiç girmez; kalanlar ortalı durur.
     */
    private function build_action_badges($opts) {
        $items = array();

        $link_icons = array(
            'btn2' => 'phone',
            'btn3' => 'calendar',
            'btn4' => 'star',
        );

        foreach ($link_icons as $key => $icon) {
            $link = isset($opts['button_links'][$key]) ? trim($opts['button_links'][$key]) : '';
            if ($link === '') continue;

            // Etiket metni doğrudan mevcut button_texts alanından gelir;
            // ayrı bir ayar alanı yoktur.
            $label = isset($opts['button_texts'][$key]) ? $opts['button_texts'][$key] : '';

            $actions = array(
                'btn2' => '',
                'btn3' => 'rezervasyon',
                'btn4' => 'yorum',
            );

            $items[] = '<div class="sp-action">'
                . '<a href="' . esc_url($link) . '" class="splash-badge sp-action-circle" data-splash-dismiss="1"'
                . ( isset( $actions[ $key ] ) && '' !== $actions[ $key ] ? ' data-splash-action="' . esc_attr( $actions[ $key ] ) . '"' : '' )
                . ' aria-label="' . esc_attr($label) . '" title="' . esc_attr($label) . '"'
                . $this->lang_data($opts, $key, $label, 'aria-label title') . '>'
                . $this->icon_svg($icon, 22)
                . '</a>'
                . '<span class="sp-action-label"' . $this->lang_data($opts, $key, $label) . '>' . esc_html($label) . '</span>'
                . '</div>';
        }

        // Wifi: modal açar, splash'ı KAPATMAZ.
        $wifi_label = isset($opts['button_texts']['btn5']) ? $opts['button_texts']['btn5'] : 'Wifi';
        $items[] = '<div class="sp-action">'
            . '<button type="button" class="splash-badge sp-action-circle" id="wifi-btn"'
            . ' aria-label="' . esc_attr($wifi_label) . '" title="' . esc_attr($wifi_label) . '"'
            . $this->lang_data($opts, 'btn5', $wifi_label, 'aria-label title') . '>'
            . $this->icon_svg('wifi', 22)
            . '</button>'
            . '<span class="sp-action-label"' . $this->lang_data($opts, 'btn5', $wifi_label) . '>' . esc_html($wifi_label) . '</span>'
            . '</div>';

        return $items;
    }

    /**
     * Sosyal rozetler: ayracın ALTINDA, ayrı satır, aksiyon rozetlerinden
     * kasıtlı olarak küçük (44px) - hiyerarşi kurar.
     *
     * Render sırası admin'de işaretlenme sırasını DEĞİL sabit bir öncelik
     * sırasını izler: instagram, facebook, youtube, x önce basılır; kalan
     * aktif platformlar admin'de aktif edilme sırasına göre onları izler.
     * URL'si boş olan aktif platform hiç basılmaz. En fazla 6 rozet.
     */
    private function build_social_badges($opts) {
        $state = $this->resolve_social_media_state($opts);
        $map   = $this->social_media_map();

        $priority = array('instagram', 'facebook', 'youtube', 'x');
        $ordered  = array();
        foreach ($priority as $key) {
            if (in_array($key, $state['active'], true)) {
                $ordered[] = $key;
            }
        }
        foreach ($state['active'] as $key) {
            if (!in_array($key, $ordered, true)) {
                $ordered[] = $key;
            }
        }

        $items = array();
        foreach ($ordered as $key) {
            if (!isset($map[$key])) continue;

            $link = isset($state['urls'][$key]) ? trim($state['urls'][$key]) : '';
            if ($link === '') continue;

            $label = $map[$key]['label'];
            $items[] = '<a href="' . esc_url($link) . '" class="splash-badge splash-badge-sm" target="_blank" rel="noopener"'
                . ' data-splash-action="sosyal"'
                . ' aria-label="' . esc_attr($label) . '" title="' . esc_attr($label) . '">'
                . $this->icon_svg($map[$key]['icon'], 20)
                . '</a>';
        }

        return $items;
    }

    /**
     * Splash markup'ı.
     *
     * $is_preview = true olduğunda çıktı admin'deki canlı önizleme çerçevesine
     * girer: overlay gizli başlamaz (önizleme kutusu zaten sınırlı bir alan),
     * data-preview="1" taşır ve JS o bayrağı görünce çerez okumaz/yazmaz,
     * yönlendirme zamanlayıcısı kurmaz. Markup'ın geri kalanı frontend ile
     * BİREBİR aynıdır — önizlemenin ayrı bir taklidi yoktur.
     */
    /**
     * TR/EN düğmesi açık mı? (En az bir İngilizce metin girilmiş olmalı.)
     *
     * Düğme açık ama hiçbir çeviri yazılmamışsa ziyaretçiye iki kez aynı
     * metni gösteren bir düğme kalırdı; o yüzden ikisi birden aranır.
     *
     * @param array $opts Ayarlar.
     * @return bool
     */
    private function lang_toggle_active($opts) {
        if (empty($opts['lang_toggle'])) return false;

        $en = isset($opts['texts_en']) && is_array($opts['texts_en']) ? $opts['texts_en'] : array();

        foreach ($en as $value) {
            if (trim((string) $value) !== '') return true;
        }

        return false;
    }

    /**
     * Bir metnin İngilizce karşılığı; girilmemişse Türkçesi.
     *
     * @param array  $opts Ayarlar.
     * @param string $key  Metin anahtarı (btn1..btn5, divider).
     * @param string $tr   Türkçe metin.
     * @return string
     */
    private function text_en($opts, $key, $tr) {
        $en = isset($opts['texts_en'][$key]) ? trim((string) $opts['texts_en'][$key]) : '';

        return '' !== $en ? $en : $tr;
    }

    /**
     * Bir metin düğümüne iki dili de taşıyan data nitelikleri.
     *
     * DİL SUNUCUDA SEÇİLMEZ. Ana sayfanın HTML'i her ziyaretçide birebir aynı
     * kalmalı (tam sayfa cache güvenliği) — çerezden dallanan bir çıktı, ilk
     * ziyaretçinin dilini herkese servis ederdi. Bu yüzden sunucu Türkçeyi
     * basar, İngilizcesini yanında data niteliği olarak taşır; hangisinin
     * görüneceğine çerezi okuyan istemci karar verir. Aynı desen splash'ın
     * "gösterilsin mi" kararında da kullanılıyor.
     *
     * $attrs verilirse (ör. "aria-label title") JS metni o niteliklere yazar,
     * eleman içeriğine değil.
     *
     * @param array  $opts  Ayarlar.
     * @param string $key   Metin anahtarı.
     * @param string $tr    Türkçe metin.
     * @param string $attrs Boşlukla ayrılmış nitelik listesi.
     * @return string Nitelik dizesi (düğme kapalıysa boş).
     */
    private function lang_data($opts, $key, $tr, $attrs = '') {
        if ( ! $this->i18n_active( $opts ) ) {
            return '';
        }

        $langs = $this->i18n_langs( $opts );
        if ( count( $langs ) <= 1 ) {
            return '';
        }

        // data-sp-key: yönetimdeki canlı önizleme, hangi ayar alanının hangi
        // metin düğümünü beslediğini bu anahtardan bulur.
        $out = ' data-sp-key="' . esc_attr( $key ) . '"';

        foreach ( $langs as $lang ) {
            $out .= ' data-sp-' . esc_attr( $lang ) . '="' . esc_attr( $this->text_for_lang( $opts, $key, $tr, $lang ) ) . '"';
        }

        if ( '' !== $attrs ) {
            $out .= ' data-sp-attr="' . esc_attr( $attrs ) . '"';
        }

        return $out;
    }

    /**
     * QR Çeviri bayrak seçici. Logo şeridinin solunda, yüklenme göstergesinin karşısında.
     *
     * Kendi çeviri motoru YOKTUR: dil listesi QR Çeviri'den gelir, tıklama
     * rma_lang çerezine ve (splash kapandıktan sonra) ?lang= anahtarına
     * yazılır. Splash metinleri i18n kataloğundan data-sp-{kod} ile taşınır;
     * bayrak seçildiğinde istemci anında günceller.
     *
     * Dil SUNUCUDA SEÇİLMEZ. Aktif bayrak her ziyaretçide aynı varsayılanla
     * basılır; hangisinin görüneceğine çerezi/URL'i okuyan istemci karar
     * verir (tam sayfa cache güvenliği, lang_data() ile aynı desen).
     *
     * Kapalıyken ya da gösterilecek dil yokken DOM'a hiç girmez.
     *
     * @param array $opts Ayarlar.
     * @return void
     */
    private function render_ceviri_selector($opts) {
        if (!$this->ceviri_selector_active($opts)) return;

        $tumu   = qrmenu_get_langs();
        $diller = $this->ceviri_selector_langs($opts);
        $cookie = defined('RMA_CEVIRI_COOKIE') ? RMA_CEVIRI_COOKIE : 'rma_lang';

        // Cache-güvenli varsayılan: listede TR varsa o, yoksa ilk dil.
        // Ziyaretçinin gerçek dili JS'te boyanır, sunucu çıktısı sabit kalır.
        $varsayilan = in_array('tr', $diller, true) ? 'tr' : $diller[0];
        $bayrak     = isset($tumu[$varsayilan]['flag']) ? $tumu[$varsayilan]['flag'] : '';
        $ad         = isset($tumu[$varsayilan]['name']) ? $tumu[$varsayilan]['name'] : $varsayilan;
        $lang_attrs = '';
        // Şablon %s (seçili dil adı) ister. Standart lang_data + applyLang
        // aria-label'ı ham şablonla ezerdi; bu yüzden nitelikler ebeveynde
        // data-sp-lang-select-{kod} olarak durur, splash.js paint() %s'i doldurur.
        foreach ( $this->i18n_langs( $opts ) as $kod ) {
            $lang_attrs .= ' data-sp-lang-select-' . esc_attr( $kod ) . '="'
                . esc_attr( $this->text_for_lang( $opts, 'lang_select', 'Dil seç (%s)', $kod ) ) . '"';
        }
        ?>
        <div class="splash-ceviri" data-cookie="<?php echo esc_attr($cookie); ?>" data-default="<?php echo esc_attr($varsayilan); ?>"<?php echo $lang_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <button type="button" class="splash-ceviri-btn" aria-haspopup="listbox" aria-expanded="false" aria-label="<?php echo esc_attr(sprintf('Dil seç (%s)', $ad)); ?>">
                <span class="splash-ceviri-flag" aria-hidden="true"><?php echo esc_html($bayrak); ?></span>
            </button>
            <div class="splash-ceviri-panel" role="listbox">
                <?php foreach ($diller as $kod) :
                    if (!isset($tumu[$kod])) continue;
                    $secili = ($kod === $varsayilan);
                    ?>
                    <button type="button" role="option" class="splash-ceviri-opt"
                        data-lang="<?php echo esc_attr($kod); ?>"
                        data-flag="<?php echo esc_attr($tumu[$kod]['flag']); ?>"
                        data-name="<?php echo esc_attr($tumu[$kod]['name']); ?>"
                        aria-selected="<?php echo $secili ? 'true' : 'false'; ?>">
                        <span class="splash-ceviri-opt-flag" aria-hidden="true"><?php echo esc_html($tumu[$kod]['flag']); ?></span>
                        <span class="splash-ceviri-opt-name"><?php echo esc_html($tumu[$kod]['name']); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * TR|EN düğmesi. Logo şeridinin solunda, bayrak açıksa onun yanında.
     *
     * @param array $opts Ayarlar.
     * @return void
     */
    private function render_lang_toggle($opts) {
        if (!$this->lang_toggle_active($opts)) return;
        ?>
        <div class="splash-lang" role="group" aria-label="Dil / Language">
            <button type="button" class="splash-lang-btn is-active" data-sp-lang="tr" aria-pressed="true" lang="tr">TR</button>
            <button type="button" class="splash-lang-btn" data-sp-lang="en" aria-pressed="false" lang="en">EN</button>
        </div>
        <?php
    }

    private function output_splash($opts, $is_preview = false) {
        $logo_url        = $opts['logo'] ? wp_get_attachment_image_url($opts['logo'], 'medium') : '';
        $animation_class = esc_attr($opts['animation_type']);

        $dismiss_minutes  = absint($opts['dismiss_duration']);
        $redirect_seconds = absint($opts['redirect_seconds']);
        $scheme           = $this->resolve_scheme($opts);
        $has_bg_image     = (bool) absint($opts['bg_image']);

        $cta_text = isset($opts['button_texts']['btn1']) ? $opts['button_texts']['btn1'] : 'Menüye Git';
        $cta_link = isset($opts['button_links']['btn1']) ? trim($opts['button_links']['btn1']) : '';

        $divider_text = isset($opts['divider_text']) ? trim($opts['divider_text']) : '';

        $action_badges = $this->build_action_badges($opts);
        $social_badges = $this->build_social_badges($opts);
        // Sosyal link tanımlı değilse ayraç da basılmaz - ayraç etiketi
        // ("Bizi takip edin") altındaki sosyal satırı tanıtır.
        $show_divider  = !empty($social_badges) && $divider_text !== '';
        ?>

        <div id="custom-splash-overlay"
             class="splash-scheme-<?php echo esc_attr($scheme); ?><?php echo $has_bg_image ? ' splash-has-bg' : ''; ?><?php echo $is_preview ? ' is-preview' : ''; ?>"
             <?php echo $is_preview ? 'data-preview="1"' : 'style="display:none"'; ?>
             data-redirect-url="<?php echo esc_attr(trim($opts['redirect_url'])); ?>"
             data-redirect-ms="<?php echo esc_attr($redirect_seconds * 1000); ?>"
             data-dismiss-minutes="<?php echo esc_attr($dismiss_minutes); ?>">

            <?php $this->render_background_layer($opts); ?>

            <?php // Tam genişlik üst şerit: sol bayrak/dil, orta logo, sağ yüklenme göstergesi. ?>
            <div class="splash-logo">
                <div class="splash-logo-side splash-logo-side-start">
                    <?php $this->render_ceviri_selector($opts); ?>
                    <?php $this->render_lang_toggle($opts); ?>
                </div>
                <div class="sp-logo-wrap">
                    <?php if ($logo_url): ?>
                        <img src="<?php echo esc_url($logo_url); ?>" alt="" />
                    <?php endif; ?>
                </div>
                <div class="splash-logo-side splash-logo-side-end">
                    <?php $this->render_loader($opts, $redirect_seconds); ?>
                </div>
            </div>

            <div class="splash-content">
                <?php // Giriş animasyonu blur'suz bu sarmalayıcıda çalışır; biter bitmez JS sınıfı kaldırır. ?>
                <div class="splash-stack is-animating <?php echo $animation_class; ?>">

                    <?php if (!empty($action_badges)): ?>
                        <div class="splash-actions"><?php echo implode('', $action_badges); ?></div>
                    <?php endif; ?>

                    <?php if ($cta_link): ?>
                        <a href="<?php echo esc_url($cta_link); ?>" class="splash-cta" data-splash-dismiss="1" data-splash-action="menu"<?php echo $this->lang_data($opts, 'btn1', $cta_text); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($cta_text); ?></a>
                    <?php else: ?>
                        <span class="splash-cta is-disabled"<?php echo $this->lang_data($opts, 'btn1', $cta_text); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($cta_text); ?></span>
                    <?php endif; ?>

                    <?php if ($show_divider): ?>
                        <div class="splash-divider">
                            <span class="splash-divider-line" aria-hidden="true"></span>
                            <span class="splash-divider-label"<?php echo $this->lang_data($opts, 'divider', $divider_text); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($divider_text); ?></span>
                            <span class="splash-divider-line" aria-hidden="true"></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($social_badges)): ?>
                        <div class="splash-social"><?php echo implode('', $social_badges); ?></div>
                    <?php endif; ?>

                    <?php $this->render_payment_row($opts); ?>
                </div>
            </div>
        </div>

        <?php
        /*
         * Pencere metinleri eklentinin kendi metinleridir (kullanıcı girdisi
         * değil). Şifrenin kendisi çevrilmez — iki dilde de aynı karakter
         * dizisidir.
         */
        $wifi_baslik = 'Wifi Şifresi';
        $wifi_bos    = 'Henüz bir şifre girilmedi.';
        $iki_dil     = $this->i18n_active( $opts );
        ?>
        <div class="splash-modal" id="wifi-modal">
            <div class="splash-modal-content">
                <button type="button" class="splash-modal-close" aria-label="Kapat"<?php echo $iki_dil ? $this->lang_data( $opts, 'close', 'Kapat', 'aria-label' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — Türkçe yedek; JS data-sp-attr ile günceller ?>>&times;</button>
                <h3<?php echo $iki_dil ? $this->lang_data( $opts, 'wifi_title', $wifi_baslik ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $wifi_baslik ); ?></h3>
                <p style="font-size:26px; margin:18px 0; word-break:break-all;"
                    <?php echo ( $iki_dil && empty( $opts['wifi_password'] ) ) ? $this->lang_data( $opts, 'wifi_empty', $wifi_bos ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                    <?php echo esc_html( $opts['wifi_password'] ?: $wifi_bos ); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Admin ayar ekranlarındaki canlı önizleme.
     *
     * Frontend'in markup'ının aynısını, aynı CSS değişkenleriyle basar; tek
     * fark data-preview bayrağıdır. Bayrak iki koruma sağlar:
     *
     *   - PHP tarafı: overlay `display:none` ile başlamaz, çünkü önizlemede
     *     onu görünür yapacak `.splash-loading` sınıfı yoktur.
     *   - JS tarafı: admin.js bayrağı görünce çerez OKUMAZ/YAZMAZ, yönlendirme
     *     zamanlayıcısı kurmaz ve sayfayı kilitlemez — yönetici önizlemeye
     *     bakarken kendi tarayıcısında splash'ı "kapatmış" duruma düşmez.
     *
     * @return void
     */
    public function render_splash_preview() {
        $opts = $this->get_options();
        ?>
        <div class="qrms-ae-preview">
            <div class="qrms-ae-preview-frame" style="<?php echo $this->css_vars_declarations($opts); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
                <?php $this->output_splash($opts, true); ?>
            </div>
            <p class="qrms-ae-preview-note">
                Bu kutu ana sayfadaki ekranın birebir kendisidir; yalnızca ölçeği
                küçültülmüştür. Önizlemede çerez yazılmaz ve yönlendirme çalışmaz.
                <button type="button" class="button button-small qrms-ae-preview-replay">Animasyonu tekrar oynat</button>
            </p>
        </div>
        <?php
    }
}
