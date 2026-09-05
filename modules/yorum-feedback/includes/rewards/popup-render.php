<?php
if (!defined('ABSPATH')) exit;

// ÖDÜL MODÜLÜ: POPUP RENDER (stil bloğu + gizli DOM + vanilla JS state machine)

/** Tema seçimine göre popup renklerini çözer. */
function qrm_reward_popup_colors($settings) {
    $theme = isset($settings['qrm_reward_popup_theme']) ? $settings['qrm_reward_popup_theme'] : 'light';

    if ($theme === 'dark') {
        $c = ['bg' => '#111827', 'text' => '#f9fafb', 'btn' => '#10b981', 'btn_text' => '#ffffff'];
    } elseif ($theme === 'custom') {
        $c = [
            'bg'       => sanitize_hex_color($settings['qrm_reward_popup_bg_color']) ?: '#ffffff',
            'text'     => sanitize_hex_color($settings['qrm_reward_popup_text_color']) ?: '#1e293b',
            'btn'      => sanitize_hex_color($settings['qrm_reward_popup_btn_color']) ?: '#10b981',
            'btn_text' => sanitize_hex_color($settings['qrm_reward_popup_btn_text_color']) ?: '#ffffff',
        ];
    } else {
        $c = ['bg' => '#ffffff', 'text' => '#1e293b', 'btn' => '#10b981', 'btn_text' => '#ffffff'];
    }

    $c['border'] = qrm_pro_hex_to_rgba($c['text'], 0.14);
    $c['soft']   = qrm_pro_hex_to_rgba($c['text'], 0.06);
    $c['radius'] = max(0, min(40, intval($settings['qrm_reward_popup_border_radius'])));
    return $c;
}

/** Google butonu için bekleme süresi (sn). */
function qrm_reward_wait_seconds($settings) {
    $v = intval($settings['qrm_reward_wait_seconds']);
    return max(5, min(120, $v ?: 30));
}

/** Kullanıcı hiçbir şey yapmazsa e-posta adımına geçme süresi (sn, 15-30). */
function qrm_reward_auto_seconds($settings) {
    $v = intval($settings['qrm_reward_auto_trigger_seconds']);
    return max(15, min(30, $v ?: 20));
}

/** Davranış tabanlı dönüş zorunluluğu açık mı? */
function qrm_reward_require_return($settings) {
    return !empty($settings['qrm_reward_require_return']);
}

/** Sekmeden minimum ayrılma süresi (sn, 5-120). */
function qrm_reward_min_away_seconds($settings) {
    $v = intval($settings['qrm_reward_min_away_seconds']);
    return max(5, min(120, $v ?: 20));
}

/**
 * Popup stil bloğu. qrm_pro_render_style_block() ile aynı desende,
 * tüm renkler admin ayarlarından gelir.
 */
function qrm_reward_render_popup_style_block($settings) {
    $c = qrm_reward_popup_colors($settings);
    ob_start();
    ?>
    <style>
        .qrm-rw-overlay { position: fixed; inset: 0; z-index: 999999; display: flex; align-items: center; justify-content: center; padding: 18px; background: rgba(15,23,42,.55); backdrop-filter: blur(2px); animation: qrmRwFade .25s ease both; }
        .qrm-rw-overlay[hidden] { display: none; }
        @keyframes qrmRwFade { from { opacity: 0; } to { opacity: 1; } }
        @keyframes qrmRwUp { from { opacity: 0; transform: translateY(16px) scale(.97); } to { opacity: 1; transform: none; } }

        .qrm-rw-modal { position: relative; width: 100%; max-width: 400px; background: <?php echo $c['bg']; ?>; color: <?php echo $c['text']; ?>; border-radius: <?php echo $c['radius']; ?>px; padding: 30px 26px 24px; box-shadow: 0 24px 60px rgba(0,0,0,.28); text-align: center; font-family: inherit; animation: qrmRwUp .3s ease both; box-sizing: border-box; }
        .qrm-rw-step-panel { text-align: center; padding: 8px 4px 16px; }
        .qrm-rw-step-panel .qrm-rw-btn { display: inline-flex; align-items: center; justify-content: center; text-decoration: none; box-sizing: border-box; }
        .qrm-rw-step-panel [hidden] { display: none !important; }
        .qrm-rw-step-neutral { opacity: .75; font-size: 14px; }
        .qrm-rw-modal *, .qrm-rw-modal *:before, .qrm-rw-modal *:after { box-sizing: border-box; }
        .qrm-rw-modal [hidden] { display: none; }

        .qrm-rw-close { position: absolute; top: 10px; right: 12px; background: none; border: none; color: <?php echo $c['text']; ?>; opacity: .45; font-size: 24px; line-height: 1; cursor: pointer; padding: 4px 8px; }
        .qrm-rw-close:hover { opacity: .9; }

        .qrm-rw-badge { width: 54px; height: 54px; border-radius: 50%; background: <?php echo $c['soft']; ?>; display: flex; align-items: center; justify-content: center; font-size: 26px; margin: 0 auto 14px; }
        .qrm-rw-title { font-size: 19px; font-weight: 700; margin: 0 0 8px; color: <?php echo $c['text']; ?>; }
        .qrm-rw-text { font-size: 14px; line-height: 1.55; opacity: .82; margin: 0 0 20px; }
        .qrm-rw-note { font-size: 12px; opacity: .6; margin: 14px 0 0; line-height: 1.5; }

        .qrm-rw-btn { position: relative; overflow: hidden; width: 100%; border: none; background: <?php echo $c['btn']; ?>; color: <?php echo $c['btn_text']; ?>; font-family: inherit; font-size: 15px; font-weight: 600; padding: 15px 18px; border-radius: <?php echo max(6, min(18, $c['radius'])); ?>px; cursor: pointer; transition: opacity .2s ease, transform .15s ease; }
        .qrm-rw-btn:hover:not(:disabled) { transform: translateY(-1px); }
        .qrm-rw-btn:disabled { cursor: default; opacity: .95; }
        .qrm-rw-btn-label { position: relative; z-index: 2; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        /* Spam koruma tarzı dolum göstergesi (ekstra kütüphane yok) */
        .qrm-rw-fill { position: absolute; left: 0; top: 0; bottom: 0; width: 0; z-index: 1; background: rgba(255,255,255,.28); }
        .qrm-rw-spin { width: 15px; height: 15px; border-radius: 50%; border: 2px solid rgba(255,255,255,.45); border-top-color: <?php echo $c['btn_text']; ?>; display: inline-block; animation: qrmRwSpin .7s linear infinite; }
        @keyframes qrmRwSpin { to { transform: rotate(360deg); } }

        .qrm-rw-skip { display: block; margin: 14px auto 0; background: none; border: none; color: <?php echo $c['text']; ?>; opacity: .5; font-size: 13px; text-decoration: underline; cursor: pointer; font-family: inherit; }
        .qrm-rw-skip:hover { opacity: .85; }

        .qrm-rw-form { display: block; }
        .qrm-rw-input { width: 100%; padding: 14px 16px; border: 1px solid <?php echo $c['border']; ?>; border-radius: 10px; font-size: 15px; font-family: inherit; background: <?php echo $c['soft']; ?>; color: <?php echo $c['text']; ?>; margin-bottom: 12px; text-align: center; }
        .qrm-rw-input:focus { outline: none; border-color: <?php echo $c['btn']; ?>; box-shadow: 0 0 0 3px <?php echo qrm_pro_hex_to_rgba($c['btn'], 0.2); ?>; }
        .qrm-rw-err { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; border-radius: 8px; padding: 10px 12px; font-size: 13px; margin-bottom: 12px; }

        .qrm-rw-code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 27px; font-weight: 700; letter-spacing: 3px; padding: 16px 10px; border: 2px dashed <?php echo $c['border']; ?>; border-radius: 12px; margin: 0 0 10px; word-break: break-all; }
        .qrm-rw-label { font-size: 14px; font-weight: 600; opacity: .8; margin: 0 0 16px; }

        .qrm-rw-confirm-actions { display: flex; flex-direction: column; gap: 10px; }
        .qrm-rw-btn-secondary { background: <?php echo $c['soft']; ?>; color: <?php echo $c['text']; ?>; border: 1px solid <?php echo $c['border']; ?>; }

        @media(max-width:420px){
            .qrm-rw-modal { padding: 26px 18px 20px; }
            .qrm-rw-code { font-size: 22px; letter-spacing: 2px; }
        }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Popup'ı sayfa altbilgisinde (wp_footer) basılmak üzere sıraya alır.
 *
 * NEDEN FOOTER: Popup DOM'u daha önce doğrudan shortcode çıktısına basılıyor ve
 * "sayfa başına bir kez" kuralı istek düzeyinde bir static ile korunuyordu. Ancak
 * canlı sitelerde `the_content` filtresi çoğu zaman birden fazla kez çalışır
 * (SEO eklentisinin meta açıklama üretmesi, tema/alıntı üretimi, sayfa kurucu
 * önizlemeleri). İlk geçişin çıktısı atıldığı hâlde static bayrağı tüketiyor,
 * gerçekten basılan ikinci geçişte popup DOM'u hiç üretilmiyordu; sonuçta
 * window.qrmRewardPopup tanımsız kalıyor ve ön yüz sessizce eski davranışa
 * düşüyordu. Altbilgide basmak hem bunu kökten çözer hem de popup'ı, konumu
 * bozabilecek (transform/filter uygulayan) tema sarmalayıcılarının dışına taşır.
 *
 * @param array      $settings  Eklenti ayarları
 * @param bool|array $auto_open JS kapalı klasik POST akışında sayfa açılır
 *                              açılmaz göster. Dizi verilirse ödül talebini
 *                              yetkilendiren ['review_id' => int,
 *                              'claim' => string] çifti taşınır — o akışta
 *                              JS'ten gelen bir yanıt olmadığı için anahtar
 *                              popup'a sunucudan gömülmek zorundadır.
 * @return string               Normalde boş; wp_footer kaçırıldıysa satır içi
 */
function qrm_reward_queue_popup($settings, $auto_open = false) {
    if (!empty($GLOBALS['qrm_reward_popup_printed'])) {
        return '';
    }

    $GLOBALS['qrm_reward_popup_needed'] = true;
    if ($auto_open) {
        $GLOBALS['qrm_reward_popup_auto_open'] = $auto_open;
    }

    // wp_footer çoktan çalıştıysa (ya da tema hiç çağırmıyorsa) satır içi bas:
    // popup'ın hiç görünmemesindense konumu ideal olmayan bir yerde basılması iyidir.
    if (did_action('wp_footer')) {
        $GLOBALS['qrm_reward_popup_printed'] = true;
        return qrm_reward_render_popup($settings, $auto_open);
    }

    if (!has_action('wp_footer', 'qrm_reward_print_popup_footer')) {
        add_action('wp_footer', 'qrm_reward_print_popup_footer', 20);
    }
    return '';
}

/** wp_footer: sıraya alınmışsa popup'ı sayfa sonunda bir kez basar. */
function qrm_reward_print_popup_footer() {
    if (empty($GLOBALS['qrm_reward_popup_needed']) || !empty($GLOBALS['qrm_reward_popup_printed'])) {
        return;
    }
    $settings = qrm_pro_get_settings();
    if (!qrm_reward_is_active($settings)) {
        return;
    }
    $GLOBALS['qrm_reward_popup_printed'] = true;
    $auto_open = isset($GLOBALS['qrm_reward_popup_auto_open'])
        ? $GLOBALS['qrm_reward_popup_auto_open']
        : false;

    echo qrm_reward_render_popup($settings, $auto_open);
}

/**
 * Popup DOM'u + state machine JS üretir.
 * Tek basım güvencesi çağıran taraftadır (qrm_reward_queue_popup / footer bayrakları).
 *
 * @param array $settings   Eklenti ayarları
 * @param bool  $auto_open  JS kapalı klasik POST akışında sayfa açılır açılmaz göster
 */
/**
 * Google & Ödül içeriğini form adımı olarak basar (modal / overlay yok).
 *
 * Gönderim sonrası popup ve kod talebi akışı değişmez; bu panel yalnızca
 * yönlendirme metnini adım sırasına yerleştirir.
 *
 * ÖNEMLİ: panel iki bloklu basılır — `.qrm-rw-step-cta` (Google/ödül daveti,
 * varsayılan gizli) ve `.qrm-rw-step-neutral` (nötr teşekkür mesajı,
 * varsayılan görünür). Hangisinin görüneceğine PHP değil, JS karar verir
 * (qrmInitRewardGating, form-steps.php): formda bir puanlama alanı
 * (rating_group ya da 'rating' tipi) varsa CANLI ortalama `data-threshold`
 * ile karşılaştırılır; hiç puanlama alanı yoksa CTA hiçbir zaman açılmaz.
 * Bu olmadan, sistemin her yerinde (dashboard.php, form-render.php'deki
 * gönderim-sonrası popup, rewards/functions.php) uygulanan
 * google_review_threshold eşiği bu adımda by-pass edilir ve düşük puan
 * verecek ziyaretçiye de Google daveti gösterilirdi.
 *
 * @param array $settings
 * @return string
 */
function qrm_reward_render_step_panel($settings) {
    $title     = qrm_ceviri_option('qrm_settings.qrm_reward_popup_title', $settings['qrm_reward_popup_title']);
    $text      = qrm_ceviri_option('qrm_settings.qrm_reward_popup_text', $settings['qrm_reward_popup_text']);
    $btn       = qrm_ceviri_option('qrm_settings.qrm_reward_popup_button_text', $settings['qrm_reward_popup_button_text']);
    $url       = isset($settings['google_review_url']) ? $settings['google_review_url'] : '';
    $threshold = isset($settings['google_review_threshold']) ? (float) $settings['google_review_threshold'] : 0;

    ob_start();
    echo qrm_reward_render_popup_style_block($settings);
    ?>
    <div class="qrm-rw-step-panel" data-threshold="<?php echo esc_attr($threshold); ?>">
        <div class="qrm-rw-step-cta" hidden>
            <div class="qrm-rw-badge">🎁</div>
            <h3 class="qrm-rw-title"><?php echo esc_html($title); ?></h3>
            <p class="qrm-rw-text"><?php echo esc_html($text); ?></p>
            <?php if ($url !== '') : ?>
                <a class="qrm-rw-btn" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">
                    <span class="qrm-rw-btn-label"><?php echo esc_html($btn); ?></span>
                </a>
            <?php endif; ?>
            <p class="qrm-rw-note"><?php echo esc_html(qrm_ceviri_review(__('Ödül kodu, formu gönderdikten sonra açılır.', 'qrms'))); ?></p>
        </div>
        <p class="qrm-rw-step-neutral"><?php echo esc_html(qrm_ceviri_review(__('Değerlendirmeniz için teşekkür ederiz — formu gönderdiğinizde devam edebilirsiniz.', 'qrms'))); ?></p>
    </div>
    <?php
    return ob_get_clean();
}

function qrm_reward_render_popup($settings, $auto_open = false) {
    $wait_seconds  = qrm_reward_wait_seconds($settings);
    $auto_seconds  = qrm_reward_auto_seconds($settings);
    $require_return = qrm_reward_require_return($settings);
    $min_away       = qrm_reward_min_away_seconds($settings);
    $confirm_text   = !empty($settings['qrm_reward_confirm_text'])
        ? $settings['qrm_reward_confirm_text']
        : 'Google\'da değerlendirmeyi tamamladınız mı?';

    // JS kapalı akışta popup'ı açan tek şey sunucudur; kod talebini
    // yetkilendiren anahtar da o yüzden buraya gömülür.
    $auto_claim = [
        'reviewId' => is_array($auto_open) && isset($auto_open['review_id']) ? (int) $auto_open['review_id'] : 0,
        'claim'    => is_array($auto_open) && isset($auto_open['claim']) ? (string) $auto_open['claim'] : '',
    ];

    ob_start();
    echo qrm_reward_render_popup_style_block($settings);
    ?>
    <div class="qrm-rw-overlay" id="qrmRewardPopup" hidden>
        <div class="qrm-rw-modal" role="dialog" aria-modal="true" aria-labelledby="qrmRwTitle">
            <button type="button" class="qrm-rw-close" id="qrmRwClose" aria-label="<?php echo esc_attr(qrm_ceviri_review(__('Kapat', 'qrms'))); ?>">&times;</button>

            <!-- STATE_INITIAL -->
            <div data-rw-step="initial">
                <div class="qrm-rw-badge">🎁</div>
                <h3 class="qrm-rw-title" id="qrmRwTitle"><?php echo esc_html(qrm_ceviri_option('qrm_settings.qrm_reward_popup_title', $settings['qrm_reward_popup_title'])); ?></h3>
                <p class="qrm-rw-text"><?php echo esc_html(qrm_ceviri_option('qrm_settings.qrm_reward_popup_text', $settings['qrm_reward_popup_text'])); ?></p>
                <button type="button" class="qrm-rw-btn" id="qrmRwGoBtn">
                    <span class="qrm-rw-fill" id="qrmRwFill"></span>
                    <span class="qrm-rw-btn-label" id="qrmRwGoLabel"><?php echo esc_html(qrm_ceviri_option('qrm_settings.qrm_reward_popup_button_text', $settings['qrm_reward_popup_button_text'])); ?></span>
                </button>
                <button type="button" class="qrm-rw-skip" id="qrmRwSkip"><?php echo esc_html(qrm_ceviri_option('qrm_settings.qrm_reward_popup_skip_text', $settings['qrm_reward_popup_skip_text'])); ?></button>
            </div>

            <!-- STATE_CONFIRM -->
            <div data-rw-step="confirm" hidden>
                <div class="qrm-rw-badge">✅</div>
                <p class="qrm-rw-text" id="qrmRwConfirmText"><?php echo esc_html(qrm_ceviri_option('qrm_settings.qrm_reward_confirm_text', $confirm_text)); ?></p>
                <div class="qrm-rw-confirm-actions">
                    <button type="button" class="qrm-rw-btn" id="qrmRwConfirmYes">
                        <span class="qrm-rw-btn-label"><?php echo esc_html(qrm_ceviri_review(__('Evet, tamamladım', 'qrms'))); ?></span>
                    </button>
                    <button type="button" class="qrm-rw-btn qrm-rw-btn-secondary" id="qrmRwConfirmNo">
                        <span class="qrm-rw-btn-label"><?php echo esc_html(qrm_ceviri_review(__('Henüz değil', 'qrms'))); ?></span>
                    </button>
                </div>
            </div>

            <!-- STATE_EMAIL -->
            <div data-rw-step="email" hidden>
                <div class="qrm-rw-badge">✉️</div>
                <h3 class="qrm-rw-title"><?php echo esc_html(qrm_ceviri_option('qrm_settings.qrm_reward_popup_email_step_title', $settings['qrm_reward_popup_email_step_title'])); ?></h3>
                <p class="qrm-rw-text"><?php echo esc_html(qrm_ceviri_option('qrm_settings.qrm_reward_popup_email_step_text', $settings['qrm_reward_popup_email_step_text'])); ?></p>
                <form class="qrm-rw-form" id="qrmRwEmailForm" novalidate>
                    <div class="qrm-rw-err" id="qrmRwEmailErr" hidden></div>
                    <input type="email" class="qrm-rw-input" id="qrmRwEmail" autocomplete="email"
                           placeholder="<?php echo esc_attr(qrm_ceviri_option('qrm_settings.qrm_reward_popup_email_placeholder', $settings['qrm_reward_popup_email_placeholder'])); ?>" required>
                    <button type="submit" class="qrm-rw-btn" id="qrmRwEmailBtn">
                        <span class="qrm-rw-btn-label"><?php echo esc_html(qrm_ceviri_option('qrm_settings.qrm_reward_popup_email_button_text', $settings['qrm_reward_popup_email_button_text'])); ?></span>
                    </button>
                </form>
            </div>

            <!-- STATE_RESULT_SUCCESS -->
            <div data-rw-step="success" hidden>
                <div class="qrm-rw-badge">🎉</div>
                <p class="qrm-rw-text" style="margin-bottom:14px;"><?php echo esc_html(qrm_ceviri_option('qrm_settings.qrm_reward_popup_success_text', $settings['qrm_reward_popup_success_text'])); ?></p>
                <div class="qrm-rw-code" id="qrmRwCode"></div>
                <div class="qrm-rw-label" id="qrmRwLabel"></div>
                <button type="button" class="qrm-rw-btn" id="qrmRwCopyBtn">
                    <span class="qrm-rw-btn-label"><?php echo esc_html(qrm_ceviri_option('qrm_settings.qrm_reward_popup_copy_text', $settings['qrm_reward_popup_copy_text'])); ?></span>
                </button>
                <p class="qrm-rw-note" id="qrmRwMailNote" hidden><?php echo esc_html(qrm_ceviri_review(__('Kod ayrıca e-posta adresinize gönderildi.', 'qrms'))); ?></p>
            </div>

            <!-- STATE_RESULT_ALREADY_USED -->
            <div data-rw-step="used" hidden>
                <div class="qrm-rw-badge">ℹ️</div>
                <p class="qrm-rw-text"><?php echo esc_html(qrm_ceviri_option('qrm_settings.qrm_reward_popup_already_used_text', $settings['qrm_reward_popup_already_used_text'])); ?></p>
                <button type="button" class="qrm-rw-btn" id="qrmRwUsedClose"><span class="qrm-rw-btn-label"><?php echo esc_html(qrm_ceviri_review(__('Tamam', 'qrms'))); ?></span></button>
            </div>

            <!-- STATE_RESULT_ERROR -->
            <div data-rw-step="error" hidden>
                <div class="qrm-rw-badge">⚠️</div>
                <p class="qrm-rw-text" id="qrmRwErrorText"><?php echo esc_html(qrm_ceviri_option('qrm_settings.qrm_reward_popup_error_text', $settings['qrm_reward_popup_error_text'])); ?></p>
                <button type="button" class="qrm-rw-btn" id="qrmRwErrorRetry"><span class="qrm-rw-btn-label"><?php echo esc_html(qrm_ceviri_review(__('Tekrar Dene', 'qrms'))); ?></span></button>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var root = document.getElementById('qrmRewardPopup');
        if (!root || root.getAttribute('data-rw-init') === '1') return;
        root.setAttribute('data-rw-init', '1');

        var cfg = {
            ajaxUrl:       <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
            nonce:         <?php echo wp_json_encode(wp_create_nonce('qrm_reward_request_code')); ?>,
            googleUrl:     <?php echo wp_json_encode(esc_url_raw($settings['google_review_url'])); ?>,
            waitSeconds:   <?php echo (int) $wait_seconds; ?>,
            autoSeconds:   <?php echo (int) $auto_seconds; ?>,
            requireReturn: <?php echo $require_return ? 'true' : 'false'; ?>,
            minAwaySeconds:<?php echo (int) $min_away; ?>,
            claimText:     <?php echo wp_json_encode(qrm_ceviri_option('qrm_settings.qrm_reward_popup_claim_text', $settings['qrm_reward_popup_claim_text'])); ?>,
            waitingText:   <?php echo wp_json_encode(qrm_ceviri_option('qrm_settings.qrm_reward_popup_waiting_text', $settings['qrm_reward_popup_waiting_text'])); ?>,
            copyText:      <?php echo wp_json_encode(qrm_ceviri_option('qrm_settings.qrm_reward_popup_copy_text', $settings['qrm_reward_popup_copy_text'])); ?>,
            copiedText:    <?php echo wp_json_encode(qrm_ceviri_option('qrm_settings.qrm_reward_popup_copied_text', $settings['qrm_reward_popup_copied_text'])); ?>,
            errorText:     <?php echo wp_json_encode(qrm_ceviri_option('qrm_settings.qrm_reward_popup_error_text', $settings['qrm_reward_popup_error_text'])); ?>,
            invalidEmail:  <?php echo wp_json_encode(qrm_ceviri_review(__('Lütfen geçerli bir e-posta adresi girin.', 'qrms'))); ?>,
            currentLang:   <?php echo wp_json_encode(qrm_pro_current_lang()); ?>,
            autoOpen:      <?php echo $auto_open ? 'true' : 'false'; ?>,
            autoClaim:     <?php echo wp_json_encode($auto_claim); ?>
        };

        var steps       = root.querySelectorAll('[data-rw-step]');
        var goBtn       = document.getElementById('qrmRwGoBtn');
        var goLabel     = document.getElementById('qrmRwGoLabel');
        var fill        = document.getElementById('qrmRwFill');
        var skipBtn     = document.getElementById('qrmRwSkip');
        var closeBtn    = document.getElementById('qrmRwClose');
        var emailForm   = document.getElementById('qrmRwEmailForm');
        var emailInput  = document.getElementById('qrmRwEmail');
        var emailBtn    = document.getElementById('qrmRwEmailBtn');
        var emailErr    = document.getElementById('qrmRwEmailErr');
        var codeEl      = document.getElementById('qrmRwCode');
        var labelEl     = document.getElementById('qrmRwLabel');
        var copyBtn     = document.getElementById('qrmRwCopyBtn');
        var mailNote    = document.getElementById('qrmRwMailNote');
        var errorText   = document.getElementById('qrmRwErrorText');
        var confirmYes  = document.getElementById('qrmRwConfirmYes');
        var confirmNo   = document.getElementById('qrmRwConfirmNo');

        var autoTimer = null, tickTimer = null;
        var reviewId = 0, claim = '', waited = false, popupShown = false, codeReceived = false;
        var googleClicked = false, hasReturned = false, maxAway = 0, leftAt = 0;
        var useFallback = false, visibilityBound = false;
        var visibilitySupported = (typeof document.hidden !== 'undefined');

        function setStep(name) {
            for (var i = 0; i < steps.length; i++) {
                steps[i].hidden = (steps[i].getAttribute('data-rw-step') !== name);
            }
        }

        function clearTimers() {
            if (autoTimer) { clearTimeout(autoTimer); autoTimer = null; }
            if (tickTimer) { clearInterval(tickTimer); tickTimer = null; }
        }

        function logEvent(event) {
            if (!reviewId) return;
            var fd = new FormData();
            fd.append('action', 'qrm_reward_log_event');
            fd.append('nonce', cfg.nonce);
            fd.append('review_id', reviewId);
            fd.append('claim', claim);
            fd.append('event', event);
            if (cfg.currentLang) fd.append('lang', cfg.currentLang);
            try {
                fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd });
            } catch (e) {}
        }

        function resetTracking() {
            googleClicked = false;
            hasReturned = false;
            maxAway = 0;
            leftAt = 0;
            useFallback = false;
            unbindVisibility();
        }

        function onLeave() {
            if (!googleClicked || leftAt) return;
            leftAt = Date.now();
        }

        function onReturn() {
            if (!googleClicked || !leftAt) return;
            var away = (Date.now() - leftAt) / 1000;
            leftAt = 0;
            if (away >= cfg.minAwaySeconds) {
                hasReturned = true;
                if (away > maxAway) maxAway = away;
                logEvent('returned');
                tryFinishWaiting();
            }
        }

        function onVisibilityChange() {
            if (document.visibilityState === 'hidden') onLeave();
            else if (document.visibilityState === 'visible') onReturn();
        }

        function bindVisibility() {
            if (visibilityBound) return;
            visibilityBound = true;
            if (visibilitySupported) {
                document.addEventListener('visibilitychange', onVisibilityChange);
            } else {
                useFallback = true;
                window.addEventListener('blur', onLeave);
                window.addEventListener('focus', onReturn);
            }
        }

        function unbindVisibility() {
            if (!visibilityBound) return;
            visibilityBound = false;
            if (visibilitySupported) {
                document.removeEventListener('visibilitychange', onVisibilityChange);
            } else {
                window.removeEventListener('blur', onLeave);
                window.removeEventListener('focus', onReturn);
            }
        }

        function behaviorMet() {
            return googleClicked && hasReturned && maxAway >= cfg.minAwaySeconds;
        }

        function tryFinishWaiting() {
            if (!waited || tickTimer === null) return;
            if (!cfg.requireReturn || useFallback || !visibilitySupported) return;
            if (!behaviorMet()) return;
            clearInterval(tickTimer);
            tickTimer = null;
            stateReady();
        }

        function finishWaiting() {
            if (!cfg.requireReturn || useFallback || !visibilitySupported) {
                stateReady();
                return;
            }
            if (behaviorMet()) {
                stateReady();
            } else {
                stateConfirm();
            }
        }

        function stateInitial() {
            setStep('initial');
            goBtn.disabled = false;
            fill.style.transition = 'none';
            fill.style.width = '0';
            clearTimers();
            autoTimer = setTimeout(function() { stateEmail(); }, cfg.autoSeconds * 1000);
            if (!popupShown) {
                popupShown = true;
                logEvent('popup_shown');
            }
        }

        function stateWaiting() {
            if (waited) return;
            waited = true;
            googleClicked = true;
            clearTimers();
            logEvent('google_clicked');
            bindVisibility();

            if (cfg.googleUrl) {
                var opened = window.open(cfg.googleUrl, '_blank', 'noopener');
                if (!opened) useFallback = true;
            }

            goBtn.disabled = true;
            var remaining = cfg.waitSeconds;
            goLabel.innerHTML = '<span class="qrm-rw-spin"></span><span id="qrmRwCount"></span>';
            var countEl = document.getElementById('qrmRwCount');
            function paint() {
                if (countEl) countEl.textContent = cfg.waitingText + ' ' + remaining + ' sn';
            }
            paint();

            fill.style.transition = 'none';
            fill.style.width = '0';
            requestAnimationFrame(function() {
                fill.style.transition = 'width ' + cfg.waitSeconds + 's linear';
                fill.style.width = '100%';
            });

            tickTimer = setInterval(function() {
                remaining--;
                if (remaining <= 0) {
                    clearInterval(tickTimer); tickTimer = null;
                    finishWaiting();
                    return;
                }
                paint();
            }, 1000);
        }

        function stateReady() {
            clearTimers();
            setStep('initial');
            goBtn.disabled = false;
            goLabel.textContent = cfg.claimText;
            fill.style.transition = 'none';
            fill.style.width = '0';
            goBtn.setAttribute('data-rw-ready', '1');
        }

        function stateConfirm() {
            clearTimers();
            setStep('confirm');
        }

        function stateEmail() {
            clearTimers();
            setStep('email');
            emailErr.hidden = true;
            if (emailInput) { try { emailInput.focus(); } catch (e) {} }
        }

        function stateSuccess(res) {
            codeReceived = true;
            setStep('success');
            codeEl.textContent = res.code || '';
            labelEl.textContent = res.discount_label || '';
            mailNote.hidden = !res.emailed;
        }

        function stateAlreadyUsed() {
            setStep('used');
        }

        function stateError(msg) {
            setStep('error');
            errorText.textContent = msg || cfg.errorText;
        }

        function open(opts) {
            reviewId = (opts && opts.reviewId) ? parseInt(opts.reviewId, 10) : 0;
            claim    = (opts && opts.claim) ? String(opts.claim) : '';
            waited = false;
            popupShown = false;
            codeReceived = false;
            resetTracking();
            goBtn.removeAttribute('data-rw-ready');
            root.hidden = false;
            stateInitial();
        }

        function close() {
            if (!root.hidden && reviewId && !codeReceived) {
                logEvent('skipped');
            }
            clearTimers();
            unbindVisibility();
            root.hidden = true;
        }

        goBtn.addEventListener('click', function() {
            if (goBtn.getAttribute('data-rw-ready') === '1') { stateEmail(); return; }
            stateWaiting();
        });
        skipBtn.addEventListener('click', close);
        closeBtn.addEventListener('click', close);
        root.addEventListener('click', function(e) { if (e.target === root) close(); });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !root.hidden) close();
        });

        if (confirmYes) confirmYes.addEventListener('click', stateEmail);
        if (confirmNo) confirmNo.addEventListener('click', stateInitial);

        var usedClose = document.getElementById('qrmRwUsedClose');
        if (usedClose) usedClose.addEventListener('click', close);
        var errRetry = document.getElementById('qrmRwErrorRetry');
        if (errRetry) errRetry.addEventListener('click', stateEmail);

        emailForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var value = (emailInput.value || '').trim();
            if (!value || value.indexOf('@') < 1 || value.indexOf('.') < 0) {
                emailErr.textContent = cfg.invalidEmail || 'Lütfen geçerli bir e-posta adresi girin.';
                emailErr.hidden = false;
                return;
            }
            emailErr.hidden = true;
            emailBtn.disabled = true;
            var btnLabel = emailBtn.querySelector('.qrm-rw-btn-label');
            var originalLabel = btnLabel.textContent;
            btnLabel.innerHTML = '<span class="qrm-rw-spin"></span>';

            var fd = new FormData();
            fd.append('action', 'qrm_reward_request_code');
            fd.append('nonce', cfg.nonce);
            fd.append('email', value);
            fd.append('review_id', reviewId);
            fd.append('claim', claim);
            if (cfg.currentLang) fd.append('lang', cfg.currentLang);

            fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    emailBtn.disabled = false;
                    btnLabel.textContent = originalLabel;
                    if (res && res.success) { stateSuccess(res); return; }
                    if (res && res.already_used) { stateAlreadyUsed(); return; }
                    stateError(res && res.message ? res.message : cfg.errorText);
                })
                .catch(function() {
                    emailBtn.disabled = false;
                    btnLabel.textContent = originalLabel;
                    stateError(cfg.errorText);
                });
        });

        copyBtn.addEventListener('click', function() {
            var code = codeEl.textContent;
            var label = copyBtn.querySelector('.qrm-rw-btn-label');
            function done() {
                label.textContent = cfg.copiedText;
                setTimeout(function() { label.textContent = cfg.copyText; }, 2000);
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).then(done).catch(function() { legacyCopy(code, done); });
            } else {
                legacyCopy(code, done);
            }
        });

        function legacyCopy(text, cb) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'absolute';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); cb(); } catch (e) {}
            document.body.removeChild(ta);
        }

        window.qrmRewardPopup = { open: open, close: close };

        if (cfg.autoOpen) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() { open(cfg.autoClaim); });
            } else {
                open(cfg.autoClaim);
            }
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}
