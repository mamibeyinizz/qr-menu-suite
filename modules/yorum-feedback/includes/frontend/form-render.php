<?php
if (!defined('ABSPATH')) exit;

// Küçük yardımcı: isim baş harfinden renkli avatar üretir
function qrm_pro_avatar_html($name) {
    $name = trim((string)$name) !== '' ? $name : 'M';
    $initial = mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
    $colors = ['#6366f1', '#ec4899', '#10b981', '#f59e0b', '#3b82f6', '#8b5cf6', '#ef4444', '#14b8a6'];
    $idx = array_sum(array_map('ord', str_split(substr($name, 0, 3)))) % count($colors);
    return '<span class="qrm-avatar" style="background:' . esc_attr($colors[$idx]) . '">' . esc_html($initial) . '</span>';
}

// JS kapalıyken (klasik POST akışı) Google yönlendirme ekranını sunucu tarafında üretir
function qrm_pro_render_google_cta($settings, $avg) {
    $stars = round($avg);
    $stars_html = str_repeat('★', $stars) . str_repeat('☆', 5 - $stars);
    ob_start();
    ?>
    <div class="qrm-google-cta" id="qrmGoogleCta">
        <div class="qrm-google-cta-icon">✓</div>
        <div class="qrm-google-cta-stars"><?php echo $stars_html; ?></div>
        <h3><?php echo esc_html($settings['google_review_headline']); ?></h3>
        <p><?php echo esc_html($settings['google_review_subtext']); ?></p>
        <a href="<?php echo esc_url($settings['google_review_url']); ?>" target="_blank" rel="noopener noreferrer" class="qrm-btn qrm-google-btn"><?php echo esc_html($settings['google_review_btn_text']); ?></a>
        <button type="button" class="qrm-google-skip" onclick="document.getElementById('qrmGoogleCta').outerHTML='<div class=&quot;qrm-alert qrm-success&quot;>Değerlendirmeniz için teşekkürler!</div>';"><?php echo esc_html($settings['google_review_skip_text']); ?></button>
    </div>
    <?php
    return ob_get_clean();
}

// 8. FRONT-END: PAYLAŞILAN YARDIMCILAR (Tema renkleri, otomatik renklendirme, stil/script bloğu)

function qrm_pro_hex_to_rgba($hex, $alpha = 1) {
    $hex = ltrim((string)$hex, '#');
    if (strlen($hex) == 3) { $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]; }
    if (strlen($hex) != 6 || !ctype_xdigit($hex)) { return "rgba(0,0,0,$alpha)"; }
    $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
    return "rgba($r,$g,$b,$alpha)";
}

function qrm_pro_get_theme_colors($settings) {
    $bg_color = '#ffffff'; $text_color = '#1e293b'; $border_color = '#e2e8f0'; $card_bg = '#ffffff';
    if ($settings['theme_style'] == 'dark') {
        $bg_color = '#1f2937'; $text_color = '#f9fafb'; $border_color = '#374151'; $card_bg = '#111827';
    } elseif ($settings['theme_style'] == 'transparent') {
        $bg_color = 'transparent'; $text_color = 'inherit'; $border_color = 'rgba(0,0,0,0.1)'; $card_bg = 'transparent';
    }
    return [$bg_color, $text_color, $border_color, $card_bg];
}

/**
 * Alan sütun genişliği: yalnızca 'full' (tekli) veya 'half' (ikili).
 *
 * Elementor Form widget Column Width bu kısa kodlara uygulanmaz;
 * genişlik bu değerden CSS class `.half` ile üretilir.
 *
 * @param mixed $value
 * @return string 'full'|'half'
 */
function qrm_pro_sanitize_column_width($value) {
    return ($value === 'half') ? 'half' : 'full';
}

/**
 * Kaydedilmiş sütun genişliği. Sütun henüz yoksa (eski kurulum) önceki
 * otomatik davranışa düşer — migration çalışınca DB'deki değer geçerli olur.
 *
 * @param object|array $field
 * @param string       $context 'review' (yorum/iletişim) veya 'custom'
 * @return string 'full'|'half'
 */
function qrm_pro_field_column_width($field, $context = 'custom') {
    $row = is_object($field) ? get_object_vars($field) : (array) $field;

    if (isset($row['column_width']) && $row['column_width'] !== '' && $row['column_width'] !== null) {
        return qrm_pro_sanitize_column_width($row['column_width']);
    }

    if ($context === 'review') {
        $key = isset($row['field_key']) ? $row['field_key'] : '';
        return in_array($key, ['customer_name', 'customer_phone', 'table_no'], true) ? 'half' : 'full';
    }

    $type = isset($row['field_type']) ? $row['field_type'] : (isset($row['type']) ? $row['type'] : '');
    return in_array($type, ['text', 'email', 'tel', 'number', 'date'], true) ? 'half' : 'full';
}

// Otomatik renklendirme aktifse, formdaki sıradaki alan için CSS custom property döndürür (3 renk döngüsel).
function qrm_pro_auto_color_style($settings, &$ac_index) {
    if (empty($settings['auto_color_enabled'])) return '';
    $colors = [$settings['auto_color_1'], $settings['auto_color_2'], $settings['auto_color_3']];
    $color = $colors[$ac_index % 3];
    $ac_index++;
    return ' style="--qrm-ac:' . esc_attr($color) . ';--qrm-ac-soft:' . esc_attr(qrm_pro_hex_to_rgba($color, 0.14)) . ';"';
}

/**
 * PAYLAŞILAN FORM STİLLERİ (v4.2.0)
 *
 * Yıldız puanlama, giriş alanları, buton, uyarı kutusu, captcha ve telefon alanı
 * stilleri tek bir yerde tanımlanır. Hem yorum/iletişim formu (qrm_pro_render_style_block)
 * hem de özel form builder formları (qrm_cf_form_style_block) bunu kullanır; böylece
 * v4.1.1'de düzeltilen input[type="tel"] sınıfı hatalar yeni formlarda tekrar etmez.
 *
 * @param array  $v     border_color, text_color, btn_color, btn_text_color, input_bg, panel_bg, radius
 * @param string $scope Boş değilse tüm seçiciler bu ön ekle sınırlandırılır (aynı sayfada
 *                      farklı renk temalı birden fazla form bulunabilsin diye).
 */
function qrm_pro_input_css($v, $scope = '') {
    $v = array_merge([
        'border_color'   => '#e2e8f0',
        'text_color'     => '#1e293b',
        'btn_color'      => '#10b981',
        'btn_text_color' => '#ffffff',
        'input_bg'       => '#ffffff',
        'panel_bg'       => '#f8fafc',
        'radius'         => 10,
    ], (array) $v);

    $border = $v['border_color'];
    $text   = $v['text_color'];
    $btn    = $v['btn_color'];
    $btn_tx = $v['btn_text_color'];
    $in_bg  = $v['input_bg'];
    $panel  = $v['panel_bg'];
    $r      = intval($v['radius']) . 'px';

    // Ortak giriş alanı gövdesi: text / tel / textarea ve (yeni) email, number, date, select
    // aynı görünümü paylaşır. Yeni bir alan tipi eklemek için seçici listesine eklemek yeter.
    $input_body = "width: 100%; padding: 14px 16px; border: 1px solid $border; border-radius: $r; font-size: 15px; background: $in_bg; color: $text; font-family: inherit; transition: border-color .2s ease, box-shadow .2s ease;";
    $input_focus = "outline:none; border-color: $btn; box-shadow: 0 0 0 3px {$btn}22;";

    $rules = [
        // --- Yıldız puanlama ---
        ['.qrm-rating-stars', 'display: inline-flex; flex-direction: row-reverse; gap: 2px;'],
        ['.qrm-rating-stars input', 'position:absolute; opacity:0; width:1px; height:1px;'],
        ['.qrm-rating-stars label', 'cursor: pointer; font-size: 30px; color: #cbd5e1; transition: color .15s ease, transform .15s ease; line-height: 1; margin:0;'],
        ['.qrm-rating-stars label:before', "content: '★';"],
        ['.qrm-rating-stars label:hover', 'transform: scale(1.15);'],
        ['.qrm-rating-stars input:checked ~ label, .qrm-rating-stars label:hover, .qrm-rating-stars label:hover ~ label', 'color: #f59e0b;'],
        ['.qrm-rating-stars input:focus-visible ~ label', 'outline: 2px solid #f59e0b; outline-offset: 2px; border-radius: 4px;'],

        // --- Giriş alanları ---
        ['.qrm-input-row', 'display: flex; flex-wrap: wrap; gap: 15px;'],
        ['.qrm-input-group', 'margin-bottom: 15px; flex: 1 1 100%;'],
        ['.qrm-input-group.half', 'flex: 1 1 calc(50% - 15px);'],
        ['.qrm-input-group label', 'display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; opacity: 0.9;'],
        ['.qrm-input-group input[type="text"], .qrm-input-group input[type="tel"], .qrm-input-group textarea', $input_body],
        ['.qrm-input-group input[type="text"]:focus, .qrm-input-group input[type="tel"]:focus, .qrm-input-group textarea:focus', $input_focus],
        ['.qrm-honeypot', 'position: absolute !important; left: -9999px !important; top: -9999px; width:1px; height:1px; overflow:hidden;'],

        // --- Buton ---
        ['.qrm-btn', "background: $btn; color: $btn_tx; border: none; padding: 16px 32px; border-radius: $r; font-size: 16px; font-weight: 600; cursor: pointer; width: 100%; text-transform: uppercase; letter-spacing: 0.5px; transition: transform .15s ease, box-shadow .15s ease, opacity .2s ease; display:flex; align-items:center; justify-content:center; gap:10px;"],
        ['.qrm-btn:hover', "transform: translateY(-2px); box-shadow: 0 8px 20px {$btn}44;"],
        ['.qrm-btn:disabled', 'opacity: .75; cursor: not-allowed; transform:none;'],
        ['.qrm-spinner', 'width:18px; height:18px; border-radius:50%; border:2.5px solid rgba(255,255,255,.4); border-top-color:#fff; animation: qrmSpin .7s linear infinite; display:inline-block;'],

        // --- Uyarı kutuları ---
        ['.qrm-alert', 'padding: 16px; border-radius: 10px; margin-bottom: 25px; text-align: center; font-weight: 500;'],
        ['.qrm-success', 'background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;'],
        ['.qrm-error', 'background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;'],

        // --- Güvenlik sorusu (captcha) ---
        ['.qrm-captcha', "display:flex; align-items:center; flex-wrap:wrap; gap:10px; margin:4px 0 20px; padding:14px 16px; background:$panel; border:1px solid $border; border-radius:$r;"],
        ['.qrm-captcha label', 'font-weight:600; font-size:14px; margin:0;'],
        ['.qrm-captcha input', "width:90px; padding:10px 12px; border:1px solid $border; border-radius:8px; font-size:15px; text-align:center; background:$in_bg; color:$text;"],
        ['.qrm-captcha input:focus', "outline:none; border-color:$btn; box-shadow:0 0 0 3px {$btn}22;"],

        // --- TR telefon alanı (mevcut text stiliyle aynı görünmeli; taban kuraldan sonra gelir) ---
        ['.qrm-input-group input.qrm-tel-input', $input_body],
        ['.qrm-input-group input.qrm-tel-input:focus', $input_focus],
    ];

    return qrm_pro_css_rules($rules, $scope);
}

/**
 * Ek alan tipleri (v4.2.0 form builder): e-posta, sayı, tarih, açılır liste,
 * tek/çoklu seçim. Yorum formu bu tipleri kullanmadığı için ayrı tutulur —
 * böylece mevcut formun çıktısı hiç değişmez.
 */
function qrm_cf_extra_field_css($v, $scope = '') {
    $border = $v['border_color'];
    $text   = $v['text_color'];
    $btn    = $v['btn_color'];
    $in_bg  = $v['input_bg'];
    $panel  = $v['panel_bg'];
    $r      = intval($v['radius']) . 'px';

    $body  = "width: 100%; padding: 14px 16px; border: 1px solid $border; border-radius: $r; font-size: 15px; background: $in_bg; color: $text; font-family: inherit; transition: border-color .2s ease, box-shadow .2s ease;";
    $focus = "outline:none; border-color: $btn; box-shadow: 0 0 0 3px {$btn}22;";

    $rules = [
        ['.qrm-input-group input[type="email"], .qrm-input-group input[type="number"], .qrm-input-group input[type="date"], .qrm-input-group select', $body],
        ['.qrm-input-group input[type="email"]:focus, .qrm-input-group input[type="number"]:focus, .qrm-input-group input[type="date"]:focus, .qrm-input-group select:focus', $focus],
        ['.qrm-input-group select', 'appearance:none; -webkit-appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 12 12\'%3E%3Cpath fill=\'%2394a3b8\' d=\'M2 4l4 4 4-4z\'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 14px center; background-size:12px; padding-right:38px;'],
        ['.qrm-choice-list', 'display:flex; flex-direction:column; gap:8px;'],
        ['.qrm-choice-list.inline', 'flex-direction:row; flex-wrap:wrap; gap:10px 18px;'],
        ['.qrm-choice', "display:flex; align-items:center; gap:9px; cursor:pointer; font-weight:500; font-size:15px; padding:10px 12px; border:1px solid $border; border-radius:$r; background:$panel; transition:border-color .2s ease, background .2s ease;"],
        ['.qrm-choice:hover', "border-color:$btn;"],
        ['.qrm-choice input', "width:auto; margin:0; accent-color:$btn; flex:0 0 auto;"],
        ['.qrm-choice span', 'line-height:1.35;'],
        ['.qrm-cf-desc', 'font-size:14px; line-height:1.6; opacity:.75; margin:0 0 22px;'],
        ['.qrm-cf-required', 'color:#ef4444; margin-left:2px;'],
    ];

    return qrm_pro_css_rules($rules, $scope);
}

/** Kural dizisini CSS metnine çevirir; $scope verilmişse her seçiciyi kapsamla sınırlar. */
function qrm_pro_css_rules($rules, $scope = '') {
    $scope = trim((string) $scope);
    $out = '';
    foreach ($rules as $rule) {
        list($selectors, $declarations) = $rule;
        if ($scope !== '') {
            $parts = array_map(function ($sel) use ($scope) {
                return $scope . ' ' . trim($sel);
            }, explode(',', $selectors));
            $selectors = implode(', ', $parts);
        }
        $out .= $selectors . ' { ' . $declarations . " }\n";
    }
    return $out;
}

function qrm_pro_render_style_block($settings) {
    list($bg_color, $text_color, $border_color, $card_bg) = qrm_pro_get_theme_colors($settings);
    ob_start();
    ?>
    <style>
        .qrm-wrap-full { width: 100%; font-family: inherit; color: <?php echo $text_color; ?>; box-sizing: border-box; max-width: 800px; margin: 0 auto;}
        .qrm-wrap-full *, .qrm-wrap-full *:before, .qrm-wrap-full *:after { box-sizing: border-box; }

        @keyframes qrmFadeInUp { from { opacity:0; transform: translateY(14px);} to { opacity:1; transform: translateY(0);} }
        @keyframes qrmPopIn { 0% { opacity:0; transform: scale(.85);} 100% { opacity:1; transform: scale(1);} }
        @keyframes qrmSpin { to { transform: rotate(360deg); } }
        .qrm-fade-in { animation: qrmFadeInUp .45s ease both; }

        /* Premium İstatistik Kutusu */
        .qrm-stats-panel { background: <?php echo $card_bg; ?>; padding: 25px; border-radius: 16px; border: 1px solid <?php echo $border_color; ?>; display: flex; flex-wrap: wrap; gap: 30px; margin-bottom: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.02);}
        .qrm-global-score { display: flex; flex-direction: column; align-items: center; justify-content: center; flex: 1 1 150px; border-right: 1px solid <?php echo $border_color; ?>; }
        .qrm-global-score .big-num { font-size: 48px; font-weight: 800; line-height: 1; color: <?php echo $text_color; ?>; }
        .qrm-global-score .stars { color: #f59e0b; font-size: 20px; margin: 5px 0;}
        .qrm-global-score .total-count { font-size: 13px; opacity: 0.6; }
        .qrm-crit-bars { flex: 2 1 300px; display: flex; flex-direction: column; gap: 10px; justify-content: center;}
        .qrm-crit-bar-row { display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 600;}
        .qrm-crit-bar-label { width: 110px; opacity: 0.8;}
        .qrm-crit-bar-track { flex-grow: 1; height: 8px; background: <?php echo ($settings['theme_style'] == 'dark') ? '#374151' : '#e2e8f0'; ?>; border-radius: 4px; overflow: hidden; }
        .qrm-crit-bar-fill { height: 100%; background: linear-gradient(90deg,#f59e0b,#fbbf24); border-radius: 4px; transition: width .6s ease; }
        .qrm-crit-bar-val { width: 30px; text-align: right; }
        @media(max-width:600px){ .qrm-global-score{ border-right: none; border-bottom: 1px solid <?php echo $border_color; ?>; padding-bottom:20px; } }

        /* Form Css */
        .qrm-form-box { background: <?php echo $bg_color; ?>; padding: 30px; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.04); margin-bottom: 40px; border: 1px solid <?php echo $border_color; ?>; transition: box-shadow .3s ease; }
        .qrm-form-box h3 { margin-top: 0; font-size: 22px; font-weight: 700; margin-bottom: 25px; text-align: center;}

        /* Çoklu Yıldız Sistemi */
        .qrm-multi-rating { display: flex; flex-direction: column; gap: 15px; margin-bottom: 30px; padding: 20px; background: <?php echo ($settings['theme_style'] == 'dark') ? '#374151' : '#f8fafc'; ?>; border-radius: 12px;}
        .qrm-rating-row { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; border-radius: 10px; transition: background .2s ease;}
        .qrm-rating-row > span { font-weight: 600; font-size: 15px; }

        /* Premium Otomatik Renklendirme (3 renk döngüsel, tüm form alanlarını kapsar) */
        .qrm-rating-row.qrm-ac { padding: 10px 12px; background: var(--qrm-ac-soft, transparent); border-left: 4px solid var(--qrm-ac, transparent); }
        .qrm-input-group.qrm-ac input[type="text"],
        .qrm-input-group.qrm-ac input[type="tel"],
        .qrm-input-group.qrm-ac textarea { border-left: 4px solid var(--qrm-ac, <?php echo $border_color; ?>); }
        .qrm-input-group.qrm-ac input[type="text"]:focus,
        .qrm-input-group.qrm-ac input[type="tel"]:focus,
        .qrm-input-group.qrm-ac textarea:focus { border-color: var(--qrm-ac, <?php echo $settings['btn_color']; ?>); box-shadow: 0 0 0 3px var(--qrm-ac-soft, transparent); }
        .qrm-input-group.qrm-ac label:before { content:''; display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--qrm-ac, transparent); margin-right:6px; vertical-align:middle; }

        /* Giriş alanları / buton / uyarı / captcha / telefon alanı: paylaşılan blok.
           type="tel" alanlar (masa no) da text ile aynı görünümü alır — v4.1.1 düzeltmesi
           artık qrm_pro_input_css() içinde tek yerde tanımlı. */
        <?php echo qrm_pro_input_css([
            'border_color'   => $border_color,
            'text_color'     => $text_color,
            'btn_color'      => $settings['btn_color'],
            'btn_text_color' => $settings['btn_text_color'],
            'input_bg'       => ($settings['theme_style'] == 'dark') ? '#1f2937' : '#fff',
            'panel_bg'       => ($settings['theme_style'] == 'dark') ? '#374151' : '#f8fafc',
            'radius'         => 10,
        ]); ?>

        /* Google Yönlendirme Ekranı */
        .qrm-google-cta { text-align:center; padding: 20px 10px 5px; animation: qrmFadeInUp .5s ease both; }
        .qrm-google-cta-icon { width:56px; height:56px; line-height:56px; border-radius:50%; background:#dcfce7; color:#16a34a; font-size:28px; font-weight:700; margin:0 auto 14px; animation: qrmPopIn .4s ease .1s both; }
        .qrm-google-cta-stars { color:#f59e0b; font-size:24px; letter-spacing:2px; margin-bottom:10px; }
        .qrm-google-cta h3 { font-size:20px; font-weight:700; margin:0 0 8px; }
        .qrm-google-cta p { font-size:14px; opacity:.8; margin: 0 0 22px; line-height:1.5; }
        .qrm-google-btn { background:#fff; color:#3c4043; border:1px solid <?php echo $border_color; ?>; box-shadow:0 2px 10px rgba(0,0,0,.06); text-decoration:none; }
        .qrm-google-btn:hover { box-shadow:0 8px 20px rgba(0,0,0,.12); }
        .qrm-google-skip { background:none; border:none; color: inherit; opacity:.55; font-size:13px; margin-top:16px; cursor:pointer; text-decoration:underline; padding:6px; }
        .qrm-google-skip:hover { opacity:.9; }

        /* Yorumlar Listesi */
        .qrm-reviews-grid { display: flex; flex-direction: column; gap: 20px; }
        .qrm-review-item { background: <?php echo $card_bg; ?>; padding: 22px 22px 20px; border-radius: 12px; border: 1px solid <?php echo $border_color; ?>; position: relative; transition: transform .2s ease, box-shadow .2s ease; }
        .qrm-review-item:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(0,0,0,.06); }
        .qrm-review-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; gap: 10px; }
        .qrm-review-who { display:flex; align-items:center; gap:10px; }
        .qrm-avatar { width:36px; height:36px; min-width:36px; border-radius:50%; color:#fff; font-weight:700; font-size:14px; display:flex; align-items:center; justify-content:center; }
        .qrm-reviewer-name { font-weight: 700; font-size: 15px; display: block;}
        .qrm-review-date { font-size: 12px; opacity: 0.6; white-space: nowrap; }
        .qrm-review-stars { color: #f59e0b; font-size: 15px; margin-top: 2px; display:block;}
        .qrm-review-text { font-size: 15px; line-height: 1.6; margin: 12px 0 0 0; opacity: 0.9; }

        .qrm-load-more-wrap { text-align: center; margin-top: 30px; }
        .qrm-load-more-btn { background: transparent; border: 2px solid <?php echo $settings['btn_color']; ?>; color: <?php echo $settings['btn_color']; ?>; padding: 12px 30px; border-radius: 30px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .qrm-load-more-btn:hover { background: <?php echo $settings['btn_color']; ?>; color: <?php echo $settings['btn_text_color']; ?>; }

        .qrm-empty-state { text-align:center; padding: 30px 10px; opacity:.6; font-size:14px; }

        /* --- Aşamalı Form (Wizard) --- */
        .qrm-steps-head { display:none; align-items:center; justify-content:center; gap:8px; margin-bottom:24px; }
        .qrm-step-dot { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; background:<?php echo ($settings['theme_style']=='dark')?'#374151':'#e2e8f0'; ?>; color:#64748b; transition:background .25s ease,color .25s ease; }
        .qrm-step-dot.active { background:<?php echo $settings['btn_color']; ?>; color:<?php echo $settings['btn_text_color']; ?>; }
        .qrm-step-line { width:44px; height:2px; background:<?php echo $border_color; ?>; }
        .qrm-step { animation: qrmFadeInUp .35s ease both; }
        .qrm-step[hidden] { display:none; }
        .qrm-step-error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; padding:11px 14px; border-radius:8px; font-size:13px; margin-bottom:16px; text-align:center; }

        /* JS yoksa: adım butonları/göstergesi gizli, tek uzun form akar */
        #qrm-step-next, #qrm-step-back { display:none; }
        form.qrm-js .qrm-steps-head { display:flex; }
        form.qrm-js #qrm-step-next { display:flex; }
        form.qrm-js #qrm-step-back { display:inline-flex; }

        .qrm-nav-row { display:flex; gap:12px; margin-top:6px; }
        .qrm-nav-row .qrm-btn { flex:1 1 auto; }
        .qrm-btn-secondary { background:transparent; border:1px solid <?php echo $border_color; ?>; color:inherit; padding:16px 24px; border-radius:10px; font-size:15px; font-weight:600; cursor:pointer; flex:0 0 auto; transition:background .2s ease; align-items:center; justify-content:center; }
        .qrm-btn-secondary:hover { background:rgba(0,0,0,.05); }

        /* TR telefon input otomatik renklendirme çizgisi (taban stil paylaşılan blokta) */
        .qrm-input-group.qrm-ac input.qrm-tel-input { border-left:4px solid var(--qrm-ac, <?php echo $border_color; ?>); }

        @media(max-width:480px){
            .qrm-form-box, .qrm-stats-panel { padding: 20px; border-radius: 14px; }
            .qrm-global-score .big-num { font-size: 38px; }
            .qrm-input-group.half { flex: 1 1 100%; }
        }
    </style>
    <?php
    return ob_get_clean();
}

// Form kutusunu üretir: puanlama kriterleri + müşteri bilgi alanları + gönder butonu.
// $form_source: 'review' (normal yorum formu) veya 'contact' (İletişim shortcode'u).
// $auto_open_reward (v4.1.0): JS kapalı klasik POST akışında ödül popup'ı sayfa açılır açılmaz gösterilir.
//   v4.2.2'den beri bool yerine ['review_id' => int, 'claim' => string] dizisi de olabilir:
//   o akışta istemciye dönen bir AJAX yanıtı olmadığı için, kod talebini yetkilendiren
//   tek kullanımlık anahtar popup'a sunucudan gömülmek zorundadır.
function qrm_pro_render_review_form($settings, $active_fields, $message, $show_google_cta, $cta_avg, $form_source = 'review', $auto_open_reward = false) {
    $ac_index = 0;
    $form_title = ($form_source === 'contact') ? $settings['contact_form_title'] : $settings['form_title'];
    $cap = qrm_pro_make_captcha();
    $ts  = qrm_pro_make_ts_token();
    ob_start();
    ?>
    <div class="qrm-form-box qrm-fade-in" id="qrm-form-box">
        <div id="qrm-form-box-inner">
        <?php if ($show_google_cta): ?>
            <?php echo qrm_pro_render_google_cta($settings, $cta_avg); ?>
        <?php else: ?>
            <?php echo $message; ?>
            <h3><?php echo esc_html($form_title); ?></h3>
            <form method="POST" action="#qrm-form-box" id="qrm-review-form">
                <?php wp_nonce_field('qrm_submit_review', 'qrm_review_nonce'); ?>
                <input type="hidden" name="qrm_form_source" value="<?php echo esc_attr($form_source); ?>">
                <input type="hidden" name="qrm_ts" value="<?php echo esc_attr($ts); ?>">
                <div class="qrm-honeypot" aria-hidden="true">
                    <label for="qrm_website">Web sitesi</label>
                    <input type="text" id="qrm_website" name="qrm_website" tabindex="-1" autocomplete="off">
                </div>

                <div class="qrm-steps-head">
                    <span class="qrm-step-dot active" data-dot="1">1</span>
                    <span class="qrm-step-line"></span>
                    <span class="qrm-step-dot" data-dot="2">2</span>
                </div>

                <!-- ADIM 1: PUANLAMA -->
                <div class="qrm-step" data-step="1">
                    <div class="qrm-multi-rating">
                        <?php
                        for ($i = 1; $i <= 5; $i++) {
                            if ($settings['crit_'.$i.'_active']) {
                                $c_name = $settings['crit_'.$i.'_name'];
                                $ac_attr = qrm_pro_auto_color_style($settings, $ac_index);
                                $ac_class = $ac_attr ? ' qrm-ac' : '';
                                echo '<div class="qrm-rating-row'.$ac_class.'"'.$ac_attr.'>
                                        <span>'.esc_html($c_name).'</span>
                                        <div class="qrm-rating-stars">
                                            <input type="radio" id="cr_'.$i.'_5" name="rating_'.$i.'" value="5"><label for="cr_'.$i.'_5"></label>
                                            <input type="radio" id="cr_'.$i.'_4" name="rating_'.$i.'" value="4"><label for="cr_'.$i.'_4"></label>
                                            <input type="radio" id="cr_'.$i.'_3" name="rating_'.$i.'" value="3"><label for="cr_'.$i.'_3"></label>
                                            <input type="radio" id="cr_'.$i.'_2" name="rating_'.$i.'" value="2"><label for="cr_'.$i.'_2"></label>
                                            <input type="radio" id="cr_'.$i.'_1" name="rating_'.$i.'" value="1"><label for="cr_'.$i.'_1"></label>
                                        </div>
                                      </div>';
                            }
                        }
                        ?>
                    </div>
                    <button type="button" class="qrm-btn" id="qrm-step-next"><span class="qrm-btn-label">Devam Et →</span></button>
                </div>

                <!-- ADIM 2: BİLGİLER -->
                <div class="qrm-step" data-step="2">
                    <div class="qrm-input-row">
                    <?php foreach ($active_fields as $f):
                        $req_attr = $f->is_required ? 'required' : '';
                        $class_half = qrm_pro_field_column_width($f, 'review') === 'half' ? 'half' : '';
                        $ac_attr = qrm_pro_auto_color_style($settings, $ac_index);
                        $ac_class = $ac_attr ? ' qrm-ac' : '';
                    ?>
                        <div class="qrm-input-group <?php echo $class_half . $ac_class; ?>"<?php echo $ac_attr; ?>>
                            <?php if ($f->field_type != 'checkbox'): ?>
                                <label><?php echo esc_html($f->field_label); ?> <?php echo $f->is_required ? '<span style="color:red">*</span>' : ''; ?></label>
                            <?php endif; ?>

                            <?php if ($f->field_type == 'textarea'): ?>
                                <textarea name="<?php echo esc_attr($f->field_key); ?>" rows="4" <?php echo $req_attr; ?>></textarea>
                            <?php elseif ($f->field_type == 'checkbox'): ?>
                                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:normal;">
                                    <input type="checkbox" name="<?php echo esc_attr($f->field_key); ?>" value="1" <?php echo $req_attr; ?> style="width:auto;">
                                    <?php echo esc_html($f->field_label); ?>
                                </label>
                            <?php elseif ($f->field_key == 'customer_phone'): ?>
                                <input type="tel" inputmode="numeric" autocomplete="tel" class="qrm-tel-input"
                                       name="customer_phone" placeholder="0 (5__) ___ __ __" maxlength="17" <?php echo $req_attr; ?>>
                            <?php elseif ($f->field_key == 'table_no'): ?>
                                <input type="tel" inputmode="numeric" pattern="[0-9]*" name="table_no"
                                       oninput="this.value=this.value.replace(/[^0-9]/g,'')" <?php echo $req_attr; ?>>
                            <?php else: ?>
                                <input type="text" name="<?php echo esc_attr($f->field_key); ?>" <?php echo $req_attr; ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>

                    <div class="qrm-captcha">
                        <label for="qrm_captcha">Güvenlik sorusu: <?php echo (int)$cap['a'] . ' + ' . (int)$cap['b']; ?> = ?</label>
                        <input type="text" id="qrm_captcha" name="qrm_captcha" inputmode="numeric" autocomplete="off"
                               oninput="this.value=this.value.replace(/[^0-9]/g,'')" required>
                        <input type="hidden" name="qrm_captcha_hash" value="<?php echo esc_attr($cap['hash']); ?>">
                    </div>

                    <div class="qrm-nav-row">
                        <button type="button" class="qrm-btn-secondary" id="qrm-step-back">← Geri</button>
                        <button type="submit" name="qrm_review_submit" class="qrm-btn"><span class="qrm-btn-label">Gönder</span></button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
        </div>
    </div>
    <?php
    // v4.2.0: Ödül modülü açıksa popup wp_footer'da basılmak üzere sıraya alınır.
    // (Doğrudan buraya basmak, the_content'in birden fazla kez çalıştığı sitelerde
    // popup'ın kaybolmasına yol açıyordu — bkz. qrm_reward_queue_popup açıklaması.)
    if (qrm_reward_is_active($settings)) {
        echo qrm_reward_queue_popup($settings, $auto_open_reward);
    }
    return ob_get_clean();
}
