<?php
if (!defined('ABSPATH')) exit;

// ÖZEL FORM BUILDER MODÜLÜ (v4.2.0): ÖN YÜZ RENDER
//
// Stil bloğu, yorum formuyla ORTAK olan qrm_pro_input_css() üzerine kuruludur
// (bkz. includes/frontend/form-render.php). Böylece v4.1.1'de düzeltilen
// input[type="tel"] sınıfı hatalar burada yeniden ortaya çıkmaz.

/** Form ayarlarından CSS değişken setini üretir (yorum formundaki tema mantığının aynısı). */
function qrm_cf_style_vars($s) {
    $dark        = ($s['theme_style'] === 'dark');
    $transparent = ($s['theme_style'] === 'transparent');

    $bg     = $dark ? '#1f2937' : ($transparent ? 'transparent' : '#ffffff');
    $text   = $dark ? '#f9fafb' : ($transparent ? 'inherit' : '#1e293b');
    $border = $dark ? '#374151' : ($transparent ? 'rgba(0,0,0,0.12)' : '#e2e8f0');

    return [
        'bg_color'       => $bg,
        'text_color'     => $text,
        'border_color'   => $border,
        'btn_color'      => $s['btn_color'],
        'btn_text_color' => $s['btn_text_color'],
        'input_bg'       => $dark ? '#111827' : '#ffffff',
        'panel_bg'       => $dark ? '#374151' : '#f8fafc',
        'radius'         => intval($s['border_radius']),
    ];
}

/**
 * Tek bir formun stil bloğu. Tüm seçiciler `.qrm-cf-scope-{id}` ile sınırlandırılır;
 * böylece aynı sayfada farklı renk temalı birden fazla form yan yana durabilir ve
 * yorum formunun stilleriyle çakışmaz.
 */
function qrm_cf_form_style_block($form_id, $s) {
    $v     = qrm_cf_style_vars($s);
    $scope = '.qrm-cf-scope-' . intval($form_id);
    $r     = $v['radius'];

    $own = qrm_pro_css_rules([
        ['', "width:100%; max-width:none; margin:0; font-family:inherit; color:{$v['text_color']}; box-sizing:border-box;"],
        ['*, *:before, *:after', 'box-sizing:border-box;'],
        ['.qrm-cf-form', "background:{$v['bg_color']}; padding:30px; border-radius:" . ($r + 6) . "px; border:1px solid {$v['border_color']}; box-shadow:0 4px 24px rgba(0,0,0,0.04);"],
        ['.qrm-cf-form h3', 'margin:0 0 8px; font-size:22px; font-weight:700;'],
        ['.qrm-cf-message:empty', 'display:none;'],
    ], $scope);

    ob_start();
    ?>
    <style>
    @keyframes qrmSpin { to { transform: rotate(360deg); } }
    @keyframes qrmFadeInUp { from { opacity:0; transform: translateY(14px);} to { opacity:1; transform: translateY(0);} }
    <?php echo $own; ?>
    <?php echo qrm_pro_input_css($v, $scope); ?>
    <?php echo qrm_cf_extra_field_css($v, $scope); ?>
    <?php
    echo qrm_pro_steps_css([
        'btn_color'      => $v['btn_color'],
        'btn_text_color' => $v['btn_text_color'],
        'border_color'   => $v['border_color'],
        'theme_style'    => $s['theme_style'],
    ]);
    ?>
    <?php echo $scope; ?> .qrm-cf-form { animation: qrmFadeInUp .4s ease both; }
    @media(max-width:480px){ <?php echo $scope; ?> .qrm-cf-form { padding:20px; } }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Tek bir alanı HTML'e çevirir.
 *
 * Yeni bir alan tipi eklerken yalnızca aşağıdaki switch'e bir case eklemek yeterlidir
 * (kayıt defteri: qrm_cf_field_types(), doğrulama: qrm_cf_validate_value()).
 *
 * @param object $field   Alan satırı
 * @param array  $args    id_prefix
 */
function qrm_cf_render_field($field, $args = []) {
    $args = array_merge(['id_prefix' => 'qrmcf'], $args);

    $key      = $field->field_key;
    $type     = $field->field_type;
    $label    = $field->label;
    $required = !empty($field->is_required);
    $options  = qrm_cf_field_options($field);
    $id       = $args['id_prefix'] . '-' . $key;
    $req_attr = $required ? ' required' : '';
    $req_mark = $required ? ' <span class="qrm-cf-required">*</span>' : '';
    $half     = qrm_pro_field_column_width($field, 'custom') === 'half' ? ' half' : '';

    if ($type === 'rating_group') {
        $ac = 0;
        return qrm_pro_render_rating_criteria(qrm_pro_get_settings(), $ac);
    }
    if ($type === 'google_reward') {
        return qrm_reward_render_step_panel(qrm_pro_get_settings());
    }

    ob_start();
    ?>
    <div class="qrm-input-group<?php echo $half; ?>" data-field-key="<?php echo esc_attr($key); ?>">
        <?php if ($type !== 'checkbox' || count($options) > 0): ?>
            <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html(qrm_ceviri_cf_alan($field->id, $label)) . $req_mark; ?></label>
        <?php endif; ?>

        <?php
        switch ($type):
            case 'textarea': ?>
                <textarea id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($key); ?>" rows="4"<?php echo $req_attr; ?>></textarea>
            <?php break;

            case 'email': ?>
                <input type="email" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($key); ?>" autocomplete="email" placeholder="<?php echo esc_attr(qrm_ceviri_review(__('ornek@eposta.com', 'qrms'))); ?>"<?php echo $req_attr; ?>>
            <?php break;

            case 'tel': ?>
                <input type="tel" class="qrm-tel-input" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($key); ?>"
                       inputmode="tel" autocomplete="tel" placeholder="<?php echo esc_attr(qrm_ceviri_review(__('0 (5__) ___ __ __', 'qrms'))); ?>" maxlength="20"<?php echo $req_attr; ?>>
            <?php break;

            case 'number': ?>
                <input type="number" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($key); ?>" inputmode="numeric" step="any"<?php echo $req_attr; ?>>
            <?php break;

            case 'date': ?>
                <input type="date" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($key); ?>"<?php echo $req_attr; ?>>
            <?php break;

            case 'select': ?>
                <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($key); ?>"<?php echo $req_attr; ?>>
                    <option value=""><?php echo esc_html(qrm_ceviri_review(__('Seçiniz…', 'qrms'))); ?></option>
                    <?php foreach ($options as $i => $opt):
                        $opt_label = qrm_ceviri_cf_field_option((int) $field->id, $i, $opt);
                    ?>
                        <option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt_label); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php break;

            case 'radio': ?>
                <div class="qrm-choice-list">
                    <?php foreach ($options as $i => $opt):
                        $opt_label = qrm_ceviri_cf_field_option((int) $field->id, $i, $opt);
                    ?>
                        <label class="qrm-choice">
                            <input type="radio" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($opt); ?>"<?php echo $required && $i === 0 ? ' required' : ''; ?>>
                            <span><?php echo esc_html($opt_label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php break;

            case 'checkbox': ?>
                <div class="qrm-choice-list">
                    <?php foreach ($options as $i => $opt):
                        $opt_label = qrm_ceviri_cf_field_option((int) $field->id, $i, $opt);
                    ?>
                        <label class="qrm-choice">
                            <input type="checkbox" name="<?php echo esc_attr($key); ?>[]" value="<?php echo esc_attr($opt); ?>">
                            <span><?php echo esc_html($opt_label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php break;

            case 'rating': ?>
                <div class="qrm-rating-stars">
                    <?php for ($star = 5; $star >= 1; $star--): $sid = $id . '-' . $star; ?>
                        <input type="radio" id="<?php echo esc_attr($sid); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo $star; ?>"<?php echo $required && $star === 5 ? ' required' : ''; ?>>
                        <label for="<?php echo esc_attr($sid); ?>" title="<?php echo $star; ?>"></label>
                    <?php endfor; ?>
                </div>
            <?php break;

            case 'text':
            default: ?>
                <input type="text" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($key); ?>"<?php echo $req_attr; ?>>
            <?php break;

        endswitch; ?>
    </div>
    <?php
    return ob_get_clean();
}

/** Formun tam HTML'i (stil bloğu hariç). */
function qrm_cf_render_form($form, $fields, $s = null) {
    if ($s === null) $s = qrm_cf_get_form_settings($form);
    $form_id = intval($form->id);
    $ts      = qrm_pro_make_ts_token();
    $prefix  = 'qrmcf' . $form_id;

    $step_data   = qrm_cf_build_steps($fields, $s);
    $steps       = $step_data['steps'];
    $use_stepper = $step_data['use_stepper'];
    $steps_json  = wp_json_encode(qrm_pro_steps_js_config($steps), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $submit_label = qrm_ceviri_cf_form($form_id, 'submit_text', $s['submit_text']);

    ob_start();
    ?>
    <div class="qrm-cf-scope qrm-cf-scope-<?php echo $form_id; ?> qrm-form-fullbleed">
        <div class="qrm-cf-form" id="<?php echo esc_attr($prefix); ?>-box">
            <?php if (!empty($s['show_title'])): ?>
                <h3><?php echo esc_html(qrm_ceviri_cf_form($form_id, 'title', $form->title)); ?></h3>
            <?php endif; ?>
            <?php if (trim((string) $form->description) !== ''): ?>
                <p class="qrm-cf-desc"><?php echo nl2br(esc_html(qrm_ceviri_cf_form($form_id, 'description', $form->description))); ?></p>
            <?php endif; ?>

            <div class="qrm-cf-message" id="<?php echo esc_attr($prefix); ?>-message"></div>

            <form method="post" id="<?php echo esc_attr($prefix); ?>-form" class="qrm-cf-form-el" novalidate="novalidate"<?php echo $use_stepper ? ' data-qrm-steps="' . esc_attr($steps_json) . '"' : ''; ?>>
                <?php wp_nonce_field('qrm_submit_custom_form', 'qrm_cf_nonce'); ?>
                <input type="hidden" name="form_id" value="<?php echo $form_id; ?>">
                <input type="hidden" name="qrm_ts" value="<?php echo esc_attr($ts); ?>">

                <div class="qrm-honeypot" aria-hidden="true">
                    <?php // Tuzak name=qrm_website. Etiket aria-hidden; çevirilmez. ?>
                    <label for="<?php echo esc_attr($prefix); ?>-website">Web sitesi</label>
                    <input type="text" id="<?php echo esc_attr($prefix); ?>-website" name="qrm_website" tabindex="-1" autocomplete="off">
                </div>

                <?php if ($use_stepper): ?>
                    <?php echo qrm_pro_steps_head($steps, 1); ?>
                <?php endif; ?>

                <div class="qrm-step-panels">
                <?php if ($use_stepper): ?>
                    <?php foreach ($steps as $step):
                        $num = $step['step'];
                        $hidden = ($num > 1) ? ' hidden' : '';
                        $group = isset($step_data['groups'][$step['step_no']]) ? $step_data['groups'][$step['step_no']] : [];
                    ?>
                    <div class="qrm-step" data-step="<?php echo (int) $num; ?>" data-step-type="fields" id="<?php echo esc_attr($prefix); ?>-step-<?php echo (int) $num; ?>" aria-labelledby="qrm-step-lbl-<?php echo esc_attr($step['id']); ?>"<?php echo $hidden; ?>>
                        <div class="qrm-cf-fields qrm-input-row">
                            <?php foreach ($group as $field) {
                                echo qrm_cf_render_field($field, ['id_prefix' => $prefix]);
                            } ?>
                        </div>
                        <?php if (!empty($step['consent'])) {
                            echo qrm_pro_render_consent_checkbox(qrm_pro_get_settings());
                        } ?>
                    </div>
                    <?php endforeach; ?>
                    <?php echo qrm_cf_steps_shared_nav($submit_label); ?>
                <?php else: ?>
                    <div class="qrm-cf-fields qrm-input-row">
                        <?php foreach ($fields as $field) {
                            echo qrm_cf_render_field($field, ['id_prefix' => $prefix]);
                        } ?>
                    </div>

                    <?php echo qrm_pro_render_consent_checkbox(qrm_pro_get_settings()); ?>

                    <button type="submit" class="qrm-btn"><span class="qrm-btn-label"><?php echo esc_html($submit_label); ?></span></button>
                <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/** Formun AJAX gönderim scripti. */
function qrm_cf_render_form_script($form, $s) {
    $form_id = intval($form->id);
    $prefix  = 'qrmcf' . $form_id;
    ob_start();
    ?>
    <?php
    // JSON_HEX_TAG: admin'in girdiği başarı mesajı script kapanış etiketi içerse
    // bile JS bloğundan çıkamaz.
    $json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    ?>
    <script>
    (function(){
        <?php echo qrm_pro_steps_wizard_js(); ?>
        var form = document.getElementById(<?php echo wp_json_encode($prefix . '-form', $json_flags); ?>);
        if (!form) return;
        var box     = document.getElementById(<?php echo wp_json_encode($prefix . '-box', $json_flags); ?>);
        var msgBox  = document.getElementById(<?php echo wp_json_encode($prefix . '-message', $json_flags); ?>);
        var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php'), $json_flags); ?>;
        var currentLang = <?php echo wp_json_encode(qrm_pro_current_lang(), $json_flags); ?>;
        var successText = <?php echo wp_json_encode(qrm_ceviri_cf_form($form_id, 'success_message', $s['success_message']), $json_flags); ?>;
        var qrmCfI18n = <?php echo wp_json_encode([
            'sending'      => qrm_ceviri_review(__('Gönderiliyor…', 'qrms')),
            'submitFailed' => qrm_ceviri_review(__('Gönderim tamamlanamadı, lütfen tekrar deneyin.', 'qrms')),
            'connError'    => qrm_ceviri_review(__('Bağlantı hatası, lütfen tekrar deneyin.', 'qrms')),
        ], $json_flags); ?>;
        function metin(anahtar, yedek) {
            if (!qrmCfI18n || typeof qrmCfI18n[anahtar] !== 'string' || qrmCfI18n[anahtar] === '') {
                return yedek;
            }
            return qrmCfI18n[anahtar];
        }

        if (form && form.getAttribute('data-qrm-steps')) {
            qrmInitSteps(form, {
                nextSel: '.qrm-cf-step-next',
                backSel: '.qrm-cf-step-back',
                submitSel: '.qrm-cf-step-submit'
            });
        }

        function showMessage(type, text) {
            msgBox.innerHTML = '<div class="qrm-alert qrm-' + type + '"></div>';
            msgBox.firstChild.textContent = text;
            box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        form.addEventListener('submit', function(e){
            e.preventDefault();
            if (form.getAttribute('data-qrm-steps') && form._qrmGoToStep) {
                var panels = form.querySelectorAll('.qrm-step[data-step]');
                var cur = 1;
                panels.forEach(function(p) { if (!p.hidden) cur = parseInt(p.getAttribute('data-step'), 10) || 1; });
                var total = panels.length;
                if (cur < total) {
                    var nextBtn = form.querySelector('.qrm-cf-step-next');
                    if (nextBtn) nextBtn.click();
                    return;
                }
            }
            // Tarayıcı doğrulaması: novalidate ile kapatıldı, elle tetikleniyor ki
            // hata mesajı formun kendi uyarı kutusuyla aynı yerde görünsün.
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                form.reportValidity();
                return;
            }
            var btn = form.querySelector('button[type=submit]');
            var label = btn ? btn.querySelector('.qrm-btn-label') : null;
            var original = label ? label.textContent : '';
            if (btn) { btn.disabled = true; }
            if (label) { label.textContent = metin('sending', 'Gönderiliyor…'); }

            var data = new FormData(form);
            data.append('action', 'qrm_submit_custom_form');
            if (currentLang) data.append('lang', currentLang);

            fetch(ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if (btn) { btn.disabled = false; }
                    if (label) { label.textContent = original; }
                    if (res && res.success) {
                        form.style.display = 'none';
                        showMessage('success', res.message || successText);
                        return;
                    }
                    showMessage('error', (res && res.message) ? res.message : metin('submitFailed', 'Gönderim tamamlanamadı, lütfen tekrar deneyin.'));
                })
                .catch(function(){
                    if (btn) { btn.disabled = false; }
                    if (label) { label.textContent = original; }
                    showMessage('error', metin('connError', 'Bağlantı hatası, lütfen tekrar deneyin.'));
                });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
