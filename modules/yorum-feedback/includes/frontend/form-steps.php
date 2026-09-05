<?php
if (!defined('ABSPATH')) exit;

/**
 * Bir formdaki azami adım sayısı. Alt sınır 1 kalır.
 *
 * @return int
 */
function qrm_pro_max_step_no() {
    return 12;
}

/**
 * Adım numarasını 1..qrm_pro_max_step_no() aralığına kırpar.
 *
 * @param mixed $step
 * @return int
 */
function qrm_pro_sanitize_step_no($step) {
    $step = (int) $step;
    if ($step < 1) return 1;
    $max = qrm_pro_max_step_no();
    if ($step > $max) return $max;
    return $step;
}

/** Yorum formu varsayılan adım etiketleri. */
function qrm_pro_default_step_labels() {
    return [
        'rating'  => 'Puanlama',
        'comment' => 'Yorumunuz',
        'info'    => 'Bilgileriniz',
        'summary' => 'Gönder',
    ];
}

/**
 * Yorum formu adım etiketi (qrm_step_labels + qrm_ceviri_review).
 *
 * @param string $key     rating|comment|info|summary
 * @param array  $settings
 * @return string
 */
function qrm_pro_step_label($key, $settings) {
    $defaults = qrm_pro_default_step_labels();
    $labels   = (isset($settings['qrm_step_labels']) && is_array($settings['qrm_step_labels']))
        ? $settings['qrm_step_labels'] : [];
    $raw = isset($labels[$key]) ? trim((string) $labels[$key]) : '';
    if ($raw === '' && isset($defaults[$key])) {
        $raw = $defaults[$key];
    }
    return qrm_ceviri_review($raw !== '' ? $raw : $key);
}

/**
 * Özel form adım etiketi (settings.step_labels).
 *
 * @param int   $step_num 1..4
 * @param array $settings Form ayarları
 * @return string
 */
function qrm_cf_step_label($step_num, $settings) {
    $step_num = qrm_pro_sanitize_step_no($step_num);
    $labels   = (isset($settings['step_labels']) && is_array($settings['step_labels']))
        ? $settings['step_labels'] : [];
    $raw = isset($labels[$step_num]) ? trim((string) $labels[$step_num]) : '';
    if ($raw === '') {
        $raw = sprintf(__('%d. Adım', 'qrms'), $step_num);
    }
    return qrm_ceviri_review($raw);
}

/**
 * Yorum/iletişim formu adımlarını hesaplar. Boş adım oluşturulmaz.
 *
 * @param array  $settings
 * @param array  $active_fields
 * @param array  $opts media_on, form_source
 * @return array{steps:array,use_stepper:bool,comment_fields:array,info_fields:array}
 */
function qrm_pro_build_steps($settings, $active_fields, $opts = []) {
    $media_on    = !empty($opts['media_on']);
    $form_source = isset($opts['form_source']) ? $opts['form_source'] : 'review';

    $comment_fields = [];
    $info_fields    = [];
    foreach ((array) $active_fields as $f) {
        if ($f->field_type === 'textarea') {
            $comment_fields[] = $f;
        } else {
            $info_fields[] = $f;
        }
    }

    // Kayıtlı düzen varsa (düzenleyici en az bir kez kaydedilmişse) adımlar
    // oradan üretilir. Boş düzen = canlı sitelerdeki sabit sıra.
    if (function_exists('qrm_pro_review_form_layout_is_custom')) {
        $layout = qrm_pro_get_review_form_layout($settings);
        if (qrm_pro_review_form_layout_is_custom($layout)) {
            return qrm_pro_build_steps_from_layout($settings, $active_fields, $opts, $layout);
        }
    }

    $steps = [];

    $has_rating = false;
    if ($form_source === 'review') {
        for ($i = 1; $i <= 5; $i++) {
            if (!empty($settings['crit_' . $i . '_active'])) {
                $has_rating = true;
                break;
            }
        }
    }
    if ($has_rating) {
        $steps[] = [
            'id'    => 'rating',
            'type'  => 'rating',
            'label' => qrm_pro_step_label('rating', $settings),
        ];
    }

    if (!empty($comment_fields) || $media_on) {
        $steps[] = [
            'id'        => 'comment',
            'type'      => 'comment',
            'label'     => qrm_pro_step_label('comment', $settings),
            'fields'    => array_map(function ($f) { return $f->field_key; }, $comment_fields),
            'has_media' => $media_on,
        ];
    }

    if (!empty($info_fields)) {
        $steps[] = [
            'id'     => 'info',
            'type'   => 'info',
            'label'  => qrm_pro_step_label('info', $settings),
            'fields' => array_map(function ($f) { return $f->field_key; }, $info_fields),
        ];
    }

    if (!empty($steps)) {
        $steps[count($steps) - 1]['captcha'] = true;
        $steps[count($steps) - 1]['consent'] = true;
    }

    $summary_on = !empty($settings['qrm_steps_summary_enabled']);
    if ($summary_on && count($steps) >= 2) {
        $steps[] = [
            'id'    => 'summary',
            'type'  => 'summary',
            'label' => qrm_pro_step_label('summary', $settings),
        ];
    }

    foreach ($steps as $i => &$step) {
        $step['step'] = $i + 1;
    }
    unset($step);

    return [
        'steps'          => $steps,
        'use_stepper'    => count($steps) >= 2,
        'comment_fields' => $comment_fields,
        'info_fields'    => $info_fields,
    ];
}

/**
 * Kayıtlı qrm_review_form_layout'tan adım listesi üretir.
 *
 * Widget'lar (rating_group, google_reward) iletişim formunda basılmaz.
 * Özet adımı ve captcha/consent konumu eski sabit yolla aynıdır.
 *
 * @param array $settings
 * @param array $active_fields
 * @param array $opts
 * @param array $layout
 * @return array{steps:array,use_stepper:bool,comment_fields:array,info_fields:array}
 */
function qrm_pro_build_steps_from_layout($settings, $active_fields, $opts, $layout) {
    $media_on    = !empty($opts['media_on']);
    $form_source = isset($opts['form_source']) ? $opts['form_source'] : 'review';

    $by_key = [];
    foreach ((array) $active_fields as $f) {
        $by_key[$f->field_key] = $f;
    }

    $field_steps = isset($layout['field_steps']) && is_array($layout['field_steps']) ? $layout['field_steps'] : [];
    $widgets     = isset($layout['widgets']) && is_array($layout['widgets']) ? $layout['widgets'] : [];
    $labels      = isset($layout['step_labels']) && is_array($layout['step_labels']) ? $layout['step_labels'] : [];

    $groups = [];
    $ensure = static function ($sn) use (&$groups) {
        $sn = qrm_pro_sanitize_step_no($sn);
        if (!isset($groups[$sn])) {
            $groups[$sn] = ['widgets' => [], 'fields' => []];
        }
        return $sn;
    };

    if ($form_source !== 'contact' && isset($widgets['rating_group'])) {
        $has_active = false;
        for ($i = 1; $i <= 5; $i++) {
            if (!empty($settings['crit_' . $i . '_active'])) {
                $has_active = true;
                break;
            }
        }
        if ($has_active) {
            $sn = $ensure($widgets['rating_group']);
            $groups[$sn]['widgets'][] = 'rating_group';
        }
    }

    foreach ($by_key as $key => $f) {
        if (isset($field_steps[$key])) {
            $sn = $field_steps[$key];
        } else {
            $sn = ($f->field_type === 'textarea') ? 2 : 3;
        }
        $sn = $ensure($sn);
        $groups[$sn]['fields'][] = $f;
    }

    if ($form_source !== 'contact' && isset($widgets['google_reward'])) {
        $sn = $ensure($widgets['google_reward']);
        $groups[$sn]['widgets'][] = 'google_reward';
    }

    ksort($groups);

    $media_step = 0;
    if ($media_on) {
        foreach ($groups as $sn => $g) {
            foreach ($g['fields'] as $f) {
                if ($f->field_type === 'textarea') {
                    $media_step = (int) $sn;
                    break 2;
                }
            }
        }
        if (!$media_step && $groups) {
            $keys = array_keys($groups);
            $media_step = (int) end($keys);
        }
    }

    $comment_fields = [];
    $info_fields    = [];
    foreach ($by_key as $f) {
        if ($f->field_type === 'textarea') {
            $comment_fields[] = $f;
        } else {
            $info_fields[] = $f;
        }
    }

    $steps = [];
    $n     = 0;
    foreach ($groups as $sn => $g) {
        $has_media = ($media_on && (int) $sn === (int) $media_step);
        if (empty($g['widgets']) && empty($g['fields']) && !$has_media) {
            continue;
        }
        $n++;
        $has_rating = in_array('rating_group', $g['widgets'], true);
        $has_gr     = in_array('google_reward', $g['widgets'], true);

        $type = 'fields';
        if ($has_rating && !$has_gr && empty($g['fields']) && !$has_media) {
            $type = 'rating';
        } elseif ($has_gr && !$has_rating && empty($g['fields']) && !$has_media) {
            $type = 'google_reward';
        } elseif (!$has_rating && !$has_gr && !empty($g['fields'])) {
            $only_ta   = true;
            $only_info = true;
            foreach ($g['fields'] as $f) {
                if ($f->field_type !== 'textarea') {
                    $only_ta = false;
                } else {
                    $only_info = false;
                }
            }
            if ($only_ta) {
                $type = 'comment';
            } elseif ($only_info) {
                $type = 'info';
            }
        }

        $label = '';
        if (isset($labels[$sn]) && trim((string) $labels[$sn]) !== '') {
            $label = qrm_ceviri_review(sanitize_text_field($labels[$sn]));
        } elseif (in_array($type, ['rating', 'comment', 'info'], true)) {
            $label = qrm_pro_step_label($type === 'rating' ? 'rating' : $type, $settings);
        } else {
            $label = qrm_cf_step_label((int) $sn, ['step_labels' => $labels]);
        }

        $steps[] = [
            'id'                 => 's' . (int) $sn,
            'type'               => $type,
            'label'              => $label,
            'fields'             => array_map(static function ($f) { return $f->field_key; }, $g['fields']),
            'field_objects'      => $g['fields'],
            'has_rating'         => $has_rating,
            'has_google_reward'  => $has_gr,
            'has_media'          => $has_media,
            'step'               => $n,
        ];
    }

    if (!empty($steps)) {
        $steps[count($steps) - 1]['captcha'] = true;
        $steps[count($steps) - 1]['consent'] = true;
    }

    if (!empty($settings['qrm_steps_summary_enabled']) && count($steps) >= 2) {
        $steps[] = [
            'id'    => 'summary',
            'type'  => 'summary',
            'label' => qrm_pro_step_label('summary', $settings),
            'step'  => count($steps) + 1,
        ];
    }

    return [
        'steps'          => $steps,
        'use_stepper'    => count($steps) >= 2,
        'comment_fields' => $comment_fields,
        'info_fields'    => $info_fields,
    ];
}

/**
 * Özel form adımlarını hesaplar (step_no sütununa göre).
 *
 * @param array $fields
 * @param array $settings
 * @return array{steps:array,use_stepper:bool,groups:array}
 */
function qrm_cf_build_steps($fields, $settings) {
    $groups = [];
    foreach ((array) $fields as $f) {
        $sn = qrm_pro_sanitize_step_no(isset($f->step_no) ? $f->step_no : 1);
        if (!isset($groups[$sn])) $groups[$sn] = [];
        $groups[$sn][] = $f;
    }
    ksort($groups);

    $step_nums = array_keys($groups);
    if (count($step_nums) < 2) {
        return ['steps' => [], 'use_stepper' => false, 'groups' => $groups];
    }

    $steps = [];
    $i = 0;
    foreach ($step_nums as $sn) {
        $i++;
        $steps[] = [
            'step'   => $i,
            'id'     => 'cf-' . $sn,
            'type'   => 'fields',
            'label'  => qrm_cf_step_label($sn, $settings),
            'step_no'=> (int) $sn,
            'fields' => array_map(function ($f) { return $f->field_key; }, $groups[$sn]),
        ];
    }

    if (!empty($steps)) {
        $steps[count($steps) - 1]['consent'] = true;
    }

    return [
        'steps'       => $steps,
        'use_stepper' => true,
        'groups'      => $groups,
    ];
}

/**
 * Stepper başlığı (yorum + özel formlar ortak).
 *
 * @param array $steps   ['label'=>..,'step'=>..,'id'=>..]
 * @param int   $current Aktif adım (1 tabanlı)
 * @return string
 */
function qrm_pro_steps_head($steps, $current = 1) {
    if (empty($steps)) return '';

    $total   = count($steps);
    $current = max(1, min($total, (int) $current));

    ob_start();
    ?>
    <div class="qrm-steps-head" role="list" aria-label="<?php echo esc_attr(qrm_ceviri_review(__('Form adımları', 'qrms'))); ?>">
        <div class="qrm-steps-track">
            <?php foreach ($steps as $idx => $step):
                $num   = $idx + 1;
                $state = 'is-todo';
                if ($num < $current) $state = 'is-done';
                elseif ($num === $current) $state = 'is-active';
                $lid = 'qrm-step-lbl-' . esc_attr($step['id']);
                if ($idx > 0): ?>
                    <span class="qrm-step-line<?php echo $num <= $current ? ' is-filled' : ''; ?>" data-line="<?php echo (int) $idx; ?>" aria-hidden="true"></span>
                <?php endif; ?>
                <div class="qrm-step-item <?php echo esc_attr($state); ?>" role="listitem" data-step-item="<?php echo (int) $num; ?>">
                    <span class="qrm-step-label" id="<?php echo $lid; ?>"><?php echo esc_html($step['label']); ?></span>
                    <button type="button" class="qrm-step-dot" data-dot="<?php echo (int) $num; ?>" aria-labelledby="<?php echo $lid; ?>"<?php echo $num === $current ? ' aria-current="step"' : ''; ?>>
                        <?php if ($num < $current): ?>
                            <svg class="qrm-step-check" viewBox="0 0 12 12" aria-hidden="true"><path fill="currentColor" d="M10.2 2.8 4.5 8.5 1.8 5.8l-.9.9 3.6 3.6 6.6-6.6z"/></svg>
                        <?php else: ?>
                            <span class="qrm-step-num"><?php echo (int) $num; ?></span>
                        <?php endif; ?>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="qrm-steps-mobile-label" aria-live="polite"></div>
    </div>
    <div class="qrm-step-announcer sr-only" aria-live="polite" aria-atomic="true"></div>
    <?php
    return ob_get_clean();
}

/**
 * Paylaşılan adım gezinmesi (#qrm-step-next / #qrm-step-back tek örnek).
 *
 * @param string $submit_label
 * @return string
 */
function qrm_pro_steps_shared_nav($submit_label = '') {
    if ($submit_label === '') {
        $submit_label = qrm_ceviri_review(__('Gönder', 'qrms'));
    }
    ob_start();
    ?>
    <div class="qrm-nav-row qrm-steps-nav">
        <button type="button" class="qrm-btn-secondary" id="qrm-step-back" hidden><?php echo esc_html(qrm_ceviri_review(__('← Geri', 'qrms'))); ?></button>
        <button type="button" class="qrm-btn" id="qrm-step-next"><span class="qrm-btn-label"><?php echo esc_html(qrm_ceviri_review(__('Devam Et →', 'qrms'))); ?></span></button>
        <button type="submit" name="qrm_review_submit" class="qrm-btn qrm-step-submit" hidden><span class="qrm-btn-label"><?php echo esc_html($submit_label); ?></span></button>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Özel form paylaşılan gezinme.
 *
 * @param string $submit_label
 * @return string
 */
function qrm_cf_steps_shared_nav($submit_label) {
    ob_start();
    ?>
    <div class="qrm-nav-row qrm-steps-nav">
        <button type="button" class="qrm-btn-secondary qrm-cf-step-back" hidden><?php echo esc_html(qrm_ceviri_review(__('← Geri', 'qrms'))); ?></button>
        <button type="button" class="qrm-btn qrm-cf-step-next"><span class="qrm-btn-label"><?php echo esc_html(qrm_ceviri_review(__('Devam Et →', 'qrms'))); ?></span></button>
        <button type="submit" class="qrm-btn qrm-cf-step-submit" hidden><span class="qrm-btn-label"><?php echo esc_html($submit_label); ?></span></button>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Stepper CSS kuralları (form-render stil bloğuna eklenir).
 *
 * @param array $v btn_color, btn_text_color, border_color, theme_style
 * @return string
 */
function qrm_pro_steps_css($v) {
    $btn       = $v['btn_color'];
    $btn_tx    = $v['btn_text_color'];
    $border    = $v['border_color'];
    $todo_bg   = ($v['theme_style'] === 'dark') ? '#374151' : '#e2e8f0';
    $todo_col  = '#64748b';

    $rules = [
        ['.qrm-steps-head', 'display:none; flex-direction:column; align-items:stretch; margin-bottom:24px;'],
        ['.qrm-steps-track', 'display:flex; align-items:flex-end; justify-content:center; gap:0; overflow-x:auto; -webkit-overflow-scrolling:touch; scrollbar-width:none; padding:4px 2px;'],
        ['.qrm-steps-track::-webkit-scrollbar', 'display:none;'],
        ['.qrm-step-item', 'display:flex; flex-direction:column; align-items:center; gap:6px; flex:0 0 auto; min-width:52px;'],
        ['.qrm-step-label', 'font-size:12px; font-weight:600; line-height:1.2; text-align:center; max-width:72px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; opacity:.85;'],
        ['.qrm-step-dot', 'width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; border:2px solid transparent; background:' . $todo_bg . '; color:' . $todo_col . '; cursor:default; padding:0; font-family:inherit; transition:background .25s ease,color .25s ease,border-color .25s ease,transform .15s ease;'],
        ['.qrm-step-dot:focus-visible', 'outline:2px solid ' . $btn . '; outline-offset:2px;'],
        ['.qrm-step-item.is-active .qrm-step-dot', 'background:' . $btn . '; color:' . $btn_tx . '; border-color:' . $btn . ';'],
        ['.qrm-step-item.is-done .qrm-step-dot', 'background:' . $btn . '; color:' . $btn_tx . '; border-color:' . $btn . '; cursor:pointer;'],
        ['.qrm-step-item.is-todo .qrm-step-dot', 'background:transparent; border-color:' . $border . '; color:' . $todo_col . ';'],
        ['.qrm-step-check', 'width:12px; height:12px; display:block;'],
        ['.qrm-step-line', 'flex:1 1 44px; min-width:20px; max-width:56px; height:2px; background:' . $border . '; margin-bottom:14px; transition:background .3s ease;'],
        ['.qrm-step-line.is-filled', 'background:' . $btn . ';'],
        ['.qrm-steps-mobile-label', 'display:none; text-align:center; font-size:13px; font-weight:600; margin-top:10px; opacity:.8;'],
        ['.qrm-step-panels', 'position:relative;'],
        ['.qrm-step', 'animation:qrmFadeInUp .35s ease both; min-height:0; transition:min-height .2s ease;'],
        ['.qrm-step[hidden]', 'display:none !important;'],
        ['form.qrm-js .qrm-step-panels > .qrm-step:not([hidden])', 'min-height:var(--qrm-step-min,0);'],
        ['.qrm-step-summary', 'font-size:14px; line-height:1.6;'],
        ['.qrm-summary-block', 'margin-bottom:18px; padding:14px 16px; background:rgba(0,0,0,.03); border-radius:10px;'],
        ['.qrm-summary-block h4', 'margin:0 0 8px; font-size:13px; text-transform:uppercase; letter-spacing:.04em; opacity:.7;'],
        ['.qrm-summary-edit', 'background:none; border:none; color:' . $btn . '; font-size:13px; font-weight:600; cursor:pointer; text-decoration:underline; padding:0; margin-top:6px;'],
        ['.qrm-summary-edit:focus-visible', 'outline:2px solid ' . $btn . '; outline-offset:2px;'],
        ['.sr-only', 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;'],
        // !important: bazı temalar/Elementor global stilleri `button` öğelerine
        // display değeri basıyor (ör. "tüm butonlar inline-flex görünsün");
        // bu, aşağıdaki gizleme/gösterme kurallarını !important olmadan ezip
        // "Geri" ve "Gönder" butonlarının aktif adımdan bağımsız her zaman
        // görünmesine yol açıyordu (yalnızca "Devam Et" görünmeliydi).
        ['.qrm-steps-nav', 'display:none !important;'],
        ['.qrm-step-submit, .qrm-cf-step-submit', 'display:none !important;'],
        ['form.qrm-js .qrm-steps-head', 'display:flex !important;'],
        ['form.qrm-js .qrm-steps-nav', 'display:flex !important;'],
        ['form.qrm-js #qrm-step-next, form.qrm-js .qrm-cf-step-next', 'display:flex !important;'],
        ['form.qrm-js #qrm-step-next[hidden], form.qrm-js .qrm-cf-step-next[hidden]', 'display:none !important;'],
        ['form.qrm-js #qrm-step-back:not([hidden]), form.qrm-js .qrm-cf-step-back:not([hidden])', 'display:inline-flex !important;'],
        ['form.qrm-js #qrm-step-back[hidden], form.qrm-js .qrm-cf-step-back[hidden]', 'display:none !important;'],
        ['form.qrm-js .qrm-step-submit:not([hidden]), form.qrm-js .qrm-cf-step-submit:not([hidden])', 'display:flex !important;'],
        ['form.qrm-js .qrm-step-submit[hidden], form.qrm-js .qrm-cf-step-submit[hidden]', 'display:none !important;'],
        ['@media(max-width:600px)', '.qrm-step-dot{width:24px;height:24px;font-size:12px;} .qrm-step-label{font-size:11px;max-width:60px;} .qrm-step-item{min-width:44px;}'],
        ['@media(max-width:360px)', '.qrm-step-label{display:none;} .qrm-steps-mobile-label{display:block;} .qrm-step-item.is-active .qrm-step-label{display:block;max-width:none;}'],
        ['@media(prefers-reduced-motion:reduce)', '.qrm-step,.qrm-step-dot,.qrm-step-line{animation:none;transition:none;}'],
    ];

    $out = '';
    foreach ($rules as $rule) {
        $out .= $rule[0] . ' { ' . $rule[1] . " }\n";
    }
    return $out;
}

/**
 * JS'e verilecek adım yapılandırması.
 *
 * @param array $steps
 * @return array
 */
function qrm_pro_steps_js_config($steps) {
    $out = [];
    foreach ((array) $steps as $step) {
        $out[] = [
            'step'     => (int) $step['step'],
            'id'       => $step['id'],
            'type'     => $step['type'],
            'label'    => $step['label'],
            'fields'   => isset($step['fields']) ? array_values((array) $step['fields']) : [],
            'captcha'  => !empty($step['captcha']),
            'consent'  => !empty($step['consent']),
            'has_media'=> !empty($step['has_media']),
        ];
    }
    return $out;
}

/**
 * Paylaşılan çok adımlı form wizard JS gövdesi (form-script + özel form).
 *
 * @return string
 */
function qrm_pro_steps_wizard_js() {
    return <<<'JS'
        function qrmInitSteps(form, opts) {
            opts = opts || {};
            var stepsAttr = form.getAttribute('data-qrm-steps');
            var steps = opts.steps;
            if (!steps && stepsAttr) {
                try { steps = JSON.parse(stepsAttr); } catch (e) { steps = null; }
            }
            if (!steps || !steps.length) return;

            var nextSel = opts.nextSel || '#qrm-step-next';
            var backSel = opts.backSel || '#qrm-step-back';
            var submitSel = opts.submitSel || '.qrm-step-submit';
            var panels = form.querySelectorAll('.qrm-step[data-step]');
            var nextBtn = form.querySelector(nextSel);
            var backBtn = form.querySelector(backSel);
            var submitBtn = form.querySelector(submitSel) || form.querySelector('button[type="submit"].qrm-btn');
            var head = form.querySelector('.qrm-steps-head');
            var mobileLbl = head ? head.querySelector('.qrm-steps-mobile-label') : null;
            var announcer = form.querySelector('.qrm-step-announcer');
            var formBox = document.getElementById('qrm-form-box');
            var current = 1;
            var total = steps.length;
            var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            form.classList.add('qrm-js');

            function stepCfg(n) {
                for (var i = 0; i < steps.length; i++) {
                    if (steps[i].step === n) return steps[i];
                }
                return null;
            }

            function panel(n) {
                return form.querySelector('.qrm-step[data-step="' + n + '"]');
            }

            function scrollToForm() {
                if (!formBox) return;
                var top = formBox.getBoundingClientRect().top + window.pageYOffset - 12;
                window.scrollTo({ top: top, behavior: reduced ? 'auto' : 'smooth' });
            }

            function updateHead(n) {
                if (!head) return;
                var items = head.querySelectorAll('.qrm-step-item');
                var lines = head.querySelectorAll('.qrm-step-line');
                items.forEach(function(item) {
                    var sn = parseInt(item.getAttribute('data-step-item'), 10);
                    item.classList.remove('is-done', 'is-active', 'is-todo');
                    var dot = item.querySelector('.qrm-step-dot');
                    if (sn < n) {
                        item.classList.add('is-done');
                        if (dot) {
                            dot.innerHTML = '<svg class="qrm-step-check" viewBox="0 0 12 12" aria-hidden="true"><path fill="currentColor" d="M10.2 2.8 4.5 8.5 1.8 5.8l-.9.9 3.6 3.6 6.6-6.6z"/></svg>';
                            dot.removeAttribute('aria-current');
                        }
                    } else if (sn === n) {
                        item.classList.add('is-active');
                        if (dot) {
                            dot.innerHTML = '<span class="qrm-step-num">' + sn + '</span>';
                            dot.setAttribute('aria-current', 'step');
                        }
                        item.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'nearest', inline: 'center' });
                    } else {
                        item.classList.add('is-todo');
                        if (dot) {
                            dot.innerHTML = '<span class="qrm-step-num">' + sn + '</span>';
                            dot.removeAttribute('aria-current');
                        }
                    }
                });
                lines.forEach(function(line, idx) {
                    line.classList.toggle('is-filled', (idx + 1) < n);
                });
                var cfg = stepCfg(n);
                if (mobileLbl && cfg) {
                    mobileLbl.textContent = n + '/' + total + ' · ' + cfg.label;
                }
                if (announcer && cfg) {
                    announcer.textContent = n + '. adım: ' + cfg.label;
                }
            }

            function updateNav(n) {
                var isFirst = n <= 1;
                var isLast = n >= total;
                if (backBtn) backBtn.hidden = isFirst;
                if (nextBtn) nextBtn.hidden = isLast;
                if (submitBtn && submitBtn !== nextBtn) submitBtn.hidden = !isLast;
            }

            function showStep(n, focusField) {
                n = Math.max(1, Math.min(total, n));
                var prev = panel(current);
                if (prev && prev.offsetHeight) {
                    form.style.setProperty('--qrm-step-min', prev.offsetHeight + 'px');
                }
                panels.forEach(function(p) {
                    var sn = parseInt(p.getAttribute('data-step'), 10);
                    p.hidden = sn !== n;
                });
                current = n;
                updateHead(n);
                updateNav(n);
                var cfg = stepCfg(n);
                if (cfg && cfg.type === 'summary' && typeof opts.buildSummary === 'function') {
                    opts.buildSummary(form, steps);
                }
                scrollToForm();
                if (focusField !== false) {
                    var active = panel(n);
                    if (active) {
                        var el = active.querySelector('input:not([type=hidden]):not([disabled]), textarea, select, button.qrm-summary-edit');
                        if (el) setTimeout(function() { el.focus({ preventScroll: true }); }, reduced ? 0 : 80);
                    }
                }
            }

            function clearErr(p) {
                if (!p) return;
                var e = p.querySelector('.qrm-step-error');
                if (e) e.remove();
            }

            function showErr(p, msg) {
                clearErr(p);
                var e = document.createElement('div');
                e.className = 'qrm-step-error';
                e.setAttribute('role', 'alert');
                e.textContent = msg;
                p.insertBefore(e, p.firstChild);
            }

            function fieldInvalid(el) {
                if (!el || el.disabled) return false;
                if (el.type === 'checkbox' || el.type === 'radio') {
                    if (!el.required) return false;
                    var group = form.querySelectorAll('[name="' + el.name.replace(/"/g, '\\"') + '"]');
                    for (var i = 0; i < group.length; i++) {
                        if (group[i].checked) return false;
                    }
                    return true;
                }
                if (el.type === 'file') return false;
                if (typeof el.checkValidity === 'function' && !el.checkValidity()) return true;
                return el.required && !String(el.value || '').trim();
            }

            function validateStep(n) {
                var p = panel(n);
                var cfg = stepCfg(n);
                if (!p || !cfg) return true;
                clearErr(p);

                var rows = p.querySelectorAll('.qrm-rating-row');
                if (rows.length) {
                    for (var i = 0; i < rows.length; i++) {
                        if (!rows[i].querySelector('input[type=radio]:checked')) {
                            showErr(p, typeof metin === 'function' ? metin('rateRequired', 'Devam etmek için lütfen tüm kriterleri puanlayın.') : 'Devam etmek için lütfen tüm kriterleri puanlayın.');
                            var first = rows[i].querySelector('input[type=radio]');
                            if (first) first.focus();
                            return false;
                        }
                    }
                    if (cfg.type === 'rating') return true;
                }

                if (cfg.type === 'summary' || cfg.type === 'google_reward') return true;

                var seenRadio = {};
                var fields = p.querySelectorAll('input, textarea, select');
                for (var j = 0; j < fields.length; j++) {
                    var f = fields[j];
                    if (f.type === 'hidden' || f.closest('.qrm-honeypot')) continue;
                    if (f.type === 'radio') {
                        if (seenRadio[f.name]) continue;
                        seenRadio[f.name] = true;
                        if (f.required) {
                            var checked = p.querySelector('input[type=radio][name="' + f.name.replace(/"/g, '\\"') + '"]:checked');
                            if (!checked) {
                                f.focus();
                                showErr(p, typeof metin === 'function' ? metin('fieldRequired', 'Lütfen zorunlu alanları doldurun.') : 'Lütfen zorunlu alanları doldurun.');
                                return false;
                            }
                        }
                        continue;
                    }
                    if (fieldInvalid(f)) {
                        if (typeof f.reportValidity === 'function') f.reportValidity();
                        f.focus();
                        showErr(p, typeof metin === 'function' ? metin('fieldRequired', 'Lütfen zorunlu alanları doldurun.') : 'Lütfen zorunlu alanları doldurun.');
                        return false;
                    }
                }
                return true;
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    if (!validateStep(current)) return;
                    showStep(current + 1);
                });
            }
            if (backBtn) {
                backBtn.addEventListener('click', function() {
                    showStep(current - 1);
                });
            }

            if (head) {
                head.addEventListener('click', function(e) {
                    var dot = e.target.closest('.qrm-step-dot');
                    if (!dot) return;
                    var target = parseInt(dot.getAttribute('data-dot'), 10);
                    if (!target || target >= current) return;
                    showStep(target);
                });
            }

            form.addEventListener('click', function(e) {
                var edit = e.target.closest('.qrm-summary-edit');
                if (!edit) return;
                e.preventDefault();
                var go = parseInt(edit.getAttribute('data-edit-step'), 10);
                if (go) showStep(go);
            });

            form._qrmGoToStep = function(n) { showStep(n, false); };
            form._qrmFindStepForField = function(name) {
                for (var i = 0; i < steps.length; i++) {
                    var cfg = steps[i];
                    if (cfg.fields && cfg.fields.indexOf(name) !== -1) return cfg.step;
                    if (cfg.type === 'rating' && /^rating_/.test(name)) return cfg.step;
                }
                return 1;
            };

            panels.forEach(function(p) {
                var sn = parseInt(p.getAttribute('data-step'), 10);
                if (sn > 1) p.hidden = true;
            });
            updateHead(1);
            updateNav(1);
        }

        function qrmBuildReviewSummary(form, steps) {
            var box = form.querySelector('#qrm-step-summary-body');
            if (!box) return;
            var html = '';
            steps.forEach(function(cfg) {
                if (cfg.type === 'summary') return;
                var block = '<div class="qrm-summary-block"><h4>' + cfg.label + '</h4>';
                if (cfg.type === 'rating') {
                    form.querySelectorAll('.qrm-rating-row').forEach(function(row) {
                        var label = row.querySelector('span');
                        var checked = row.querySelector('input[type=radio]:checked');
                        if (label) {
                            block += '<div>' + label.textContent + ': ' + (checked ? checked.value + ' ★' : '—') + '</div>';
                        }
                    });
                } else if (cfg.fields && cfg.fields.length) {
                    cfg.fields.forEach(function(key) {
                        var grp = form.querySelector('[data-field-key="' + key + '"]');
                        var el = form.querySelector('[name="' + key + '"]');
                        if (!el && grp) el = grp.querySelector('input, textarea, select');
                        if (!el) return;
                        var val = '';
                        if (el.type === 'checkbox') val = el.checked ? '✓' : '—';
                        else if (el.tagName === 'SELECT') val = el.options[el.selectedIndex] ? el.options[el.selectedIndex].text : '';
                        else val = String(el.value || '').trim();
                        if (el.tagName === 'TEXTAREA' && val.length > 200) val = val.slice(0, 200) + '…';
                        var lbl = grp ? grp.querySelector('label') : null;
                        block += '<div>' + (lbl ? lbl.textContent.replace(/\*/g, '').trim() : key) + ': ' + (val || '—') + '</div>';
                    });
                }
                block += '<button type="button" class="qrm-summary-edit" data-edit-step="' + cfg.step + '">' +
                    (typeof metin === 'function' ? metin('summaryEdit', 'Düzenle') : 'Düzenle') + '</button></div>';
                html += block;
            });
            box.innerHTML = html;
        }

        // Google & Ödül adım paneli (qrm_reward_render_step_panel): CTA'yı
        // yalnızca formda bir puanlama alanı VARSA ve canlı ortalama eşiği
        // karşılıyorsa gösterir. Puanlama alanı hiç yoksa (ya da henüz
        // doldurulmamışsa) daima nötr metin kalır — google_review_threshold
        // gönderim öncesi bu adımda by-pass edilmesin diye.
        function qrmComputeLiveRatingAvg(form) {
            var checked = form.querySelectorAll(
                '.qrm-rating-row input[type=radio]:checked, .qrm-rating-stars input[type=radio]:checked'
            );
            if (!checked.length) return null;
            var sum = 0;
            checked.forEach(function(el) { sum += parseFloat(el.value) || 0; });
            return sum / checked.length;
        }

        function qrmSyncRewardPanels(form) {
            var panels = form.querySelectorAll('.qrm-rw-step-panel');
            if (!panels.length) return;
            var avg = qrmComputeLiveRatingAvg(form);
            panels.forEach(function(panel) {
                var threshold = parseFloat(panel.getAttribute('data-threshold')) || 0;
                var show = (avg !== null && avg >= threshold);
                var cta = panel.querySelector('.qrm-rw-step-cta');
                var neutral = panel.querySelector('.qrm-rw-step-neutral');
                if (cta) cta.hidden = !show;
                if (neutral) neutral.hidden = show;
            });
        }

        function qrmInitRewardGating(form) {
            if (!form || !form.querySelector('.qrm-rw-step-panel')) return;
            qrmSyncRewardPanels(form);
            form.addEventListener('change', function(e) {
                var t = e.target;
                if (t && t.type === 'radio' && t.closest('.qrm-rating-row, .qrm-rating-stars')) {
                    qrmSyncRewardPanels(form);
                }
            });
        }
JS;
}
