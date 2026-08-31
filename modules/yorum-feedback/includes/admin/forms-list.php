<?php
if (!defined('ABSPATH')) exit;

// FORMLAR SAYFASI (v4.2.0, v4.2.1'de sekmeli tek sayfaya birleştirildi)
//
// Tek kayıtlı admin sayfası: qrms-yf-formlar
//   ?page=qrms-yf-formlar                    -> "Formlarım" sekmesi
//   ?page=qrms-yf-formlar&tab=submissions    -> "Gönderiler" sekmesi
//   ?page=qrms-yf-formlar&view=edit&form_id= -> Form düzenleyici görünümü
// Bağlantıların tamamı qrm_cf_admin_url() ile üretilir; slug hiçbir yere gömülmez.
//
// Düzenleyici bilerek ayrı bir (gizli) admin sayfası DEĞİL: v4.2.0'da gizli sayfa
// deseni WordPress'in hook adı çözümlemesini bozup 403'e yol açmıştı (bkz. menu.php).

/**
 * Formlar sayfasının yönlendiricisi: düzenleyici görünümü ya da sekmeli liste.
 * Menüde kayıtlı tek callback budur.
 */
function qrm_cf_admin_forms_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Bu sayfayı görüntüleme yetkiniz yok.');
    }

    $view = isset($_GET['view']) ? sanitize_key($_GET['view']) : '';
    if ($view === 'edit') {
        qrm_cf_admin_form_editor_view();
        return;
    }

    $tab = (isset($_GET['tab']) && sanitize_key($_GET['tab']) === 'submissions') ? 'submissions' : 'forms';

    // Sekmelerin POST işlemleri: ikisi de satır bazlı aksiyonlar (silme, durum
    // değiştirme). Ortak bir ayar dizisi yazmadıkları için birbirlerini ezemezler.
    $notice = qrm_cf_admin_handle_list_actions();
    $notice = $notice !== '' ? $notice : qrm_cf_admin_handle_submission_actions();

    qrm_cf_admin_styles();
    qrm_cf_copy_script();
    qrm_cf_admin_submissions_styles();
    ?>
    <div class="wrap qrm-cf-wrap">
        <div class="qrm-cf-head">
            <div>
                <h1><?php esc_html_e('Özel Formlar', 'qrms'); ?></h1>
                <p class="qrm-cf-sub"><?php esc_html_e('Şikayet, geri bildirim, rezervasyon, anket… Kendi formlarınızı oluşturun, kısa kodla sayfaya yerleştirin, gönderimleri buradan takip edin.', 'qrms'); ?></p>
            </div>
            <a class="qrm-cf-btn-primary" href="<?php echo esc_url(qrm_cf_admin_url(['view' => 'edit'])); ?>">
                <span class="dashicons dashicons-plus-alt2" style="font-size:17px;width:17px;height:17px;"></span> Yeni Form Oluştur
            </a>
        </div>

        <?php if ($notice !== ''): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
        <?php endif; ?>

        <?php
        /*
         * Sekmeler gerçek bağlantıdır, JS değil. Kaynakta iki panel de basılıp
         * jQuery ile gizlenip gösteriliyordu: sayfadaki herhangi bir JS hatasında
         * ikisi birden görünüyor, hiçbiri aktif işaretlenmiyordu. Ayrıca her açılışta
         * iki panelin de sorguları çalışıyordu. Artık yalnızca aktif panel
         * render edilir — dashboard/insights'ta yapılan dönüşümün aynısı.
         */
        $unread = qrm_cf_unread_total();
        ?>
        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url(qrm_cf_admin_url()); ?>"
               class="nav-tab<?php echo $tab === 'forms' ? ' nav-tab-active' : ''; ?>">Formlarım</a>
            <a href="<?php echo esc_url(qrm_cf_admin_url(['tab' => 'submissions'])); ?>"
               class="nav-tab<?php echo $tab === 'submissions' ? ' nav-tab-active' : ''; ?>">
                Gönderiler
                <?php if ($unread > 0): ?>
                    <span class="qrm-cf-badge qrm-cf-badge-new"><?php echo intval($unread); ?></span>
                <?php endif; ?>
            </a>
        </h2>

        <div class="qrm-forms-pane">
            <?php
            if ($tab === 'submissions') {
                qrm_cf_admin_submissions_pane();
            } else {
                qrm_cf_admin_forms_list_pane();
            }
            ?>
        </div>
    </div>
    <?php
}

/** "Formlarım" sekmesinin POST aksiyonları. */
function qrm_cf_admin_handle_list_actions() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['qrm_cf_delete_form'])) {
        return '';
    }
    check_admin_referer('qrm_cf_forms_list');

    $deleted_id = intval($_POST['qrm_cf_delete_form']);
    $form = qrm_cf_get_form($deleted_id);
    if ($form && qrm_cf_delete_form($deleted_id)) {
        return sprintf('"%s" formu, alanları ve gönderimleriyle birlikte silindi.', $form->title);
    }
    return '';
}

/**
 * Form builder sayfalarının ortak admin stilleri.
 * Native WP renk paleti/fontuyla uyumlu kalır; yalnızca kart gölgesi, boşluk ve
 * yumuşak geçişlerle modernleştirilir (üçüncü parti admin UI çatısı kullanılmaz).
 */
function qrm_cf_admin_styles() {
    static $printed = false;
    if ($printed) return;
    $printed = true;
    ?>
    <style>
        .qrm-cf-wrap { max-width: 1440px; }
        .qrm-cf-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px; margin:14px 0 22px; }
        .qrm-cf-head h1 { margin:0; padding:0; }
        .qrm-cf-sub { color:#646970; font-size:13px; margin:4px 0 0; }

        .qrm-cf-btn-primary { display:inline-flex; align-items:center; gap:7px; background:#2271b1; color:#fff; border:none; border-radius:6px; padding:10px 18px; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; box-shadow:0 1px 3px rgba(0,0,0,.14); transition:background .18s ease, transform .18s ease, box-shadow .18s ease; }
        .qrm-cf-btn-primary:hover { background:#135e96; color:#fff; transform:translateY(-1px); box-shadow:0 4px 12px rgba(34,113,177,.28); }

        /* Kart grid */
        .qrm-cf-grid-cards { display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:20px; }
        .qrm-cf-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,.06); transition:transform .18s ease, box-shadow .18s ease; display:flex; flex-direction:column; gap:14px; }
        .qrm-cf-card:hover { transform:translateY(-3px); box-shadow:0 10px 26px rgba(0,0,0,.09); }
        .qrm-cf-card-top { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
        .qrm-cf-card h2 { margin:0; font-size:17px; line-height:1.35; }
        .qrm-cf-card-desc { margin:0; color:#646970; font-size:13px; line-height:1.5; }

        .qrm-cf-badge { display:inline-block; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; white-space:nowrap; letter-spacing:.2px; }
        .qrm-cf-badge-active { background:#dcfce7; color:#166534; }
        .qrm-cf-badge-draft { background:#fef3c7; color:#92400e; }
        .qrm-cf-badge-archived { background:#f1f5f9; color:#475569; }
        .qrm-cf-badge-new { background:#dbeafe; color:#1e40af; }

        .qrm-cf-metrics { display:flex; gap:18px; padding:12px 0; border-top:1px solid #f1f5f9; border-bottom:1px solid #f1f5f9; }
        .qrm-cf-metric .num { display:block; font-size:22px; font-weight:700; color:#111827; line-height:1.2; }
        .qrm-cf-metric .lbl { font-size:11px; text-transform:uppercase; letter-spacing:.4px; color:#6b7280; }

        .qrm-cf-shortcode { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .qrm-cf-shortcode code { flex:1 1 auto; background:#f8fafc; border:1px dashed #94a3b8; border-radius:6px; padding:7px 10px; font-size:12.5px; color:#334155; overflow-wrap:anywhere; }
        .qrm-cf-copy { border:1px solid #c3c4c7; background:#f6f7f7; border-radius:5px; padding:6px 12px; font-size:12px; cursor:pointer; white-space:nowrap; transition:background .15s ease, border-color .15s ease; }
        .qrm-cf-copy:hover { background:#f0f0f1; border-color:#8c8f94; }
        .qrm-cf-copy.copied { background:#dcfce7; border-color:#86efac; color:#166534; }
        .qrm-cf-draft-note { font-size:12px; color:#92400e; background:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:8px 11px; margin:0; line-height:1.5; }

        .qrm-cf-card-actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:auto; }
        .qrm-cf-danger { color:#b32d2e; border-color:#d5b0b0; }
        .qrm-cf-danger:hover { background:#fcf0f1; border-color:#b32d2e; color:#b32d2e; }

        /* Boş durum */
        .qrm-cf-empty { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:60px 30px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,.05); }
        .qrm-cf-empty-art { font-size:56px; line-height:1; margin-bottom:8px; }
        .qrm-cf-empty h2 { margin:12px 0 6px; font-size:21px; }
        .qrm-cf-empty p { color:#646970; font-size:14px; max-width:520px; margin:0 auto 22px; line-height:1.65; }

        /* Mobil */
        @media screen and (max-width: 782px) {
            .qrm-cf-wrap { margin-right:10px; }
            .qrm-cf-head { align-items:stretch; flex-direction:column; }
            .qrm-cf-head .qrm-cf-btn-primary { justify-content:center; }
            .qrm-cf-grid-cards { grid-template-columns:1fr; }
            .qrm-cf-card { padding:16px 14px; }
            .qrm-cf-metrics { gap:12px; justify-content:space-between; }
            .qrm-cf-shortcode code { flex:1 1 100%; }
            .qrm-cf-card-actions .button, .qrm-cf-card-actions .qrm-cf-copy { flex:1 1 auto; text-align:center; }
            .qrm-cf-empty { padding:40px 18px; }
        }

        @media (pointer: coarse) {
            .qrm-cf-btn-primary, .qrm-cf-copy, .qrm-cf-wrap .button { min-height:44px; }
            .qrm-cf-wrap .button-small { min-height:40px; }
            .qrm-cf-wrap .nav-tab { min-height:44px; display:inline-flex; align-items:center; }
            .qrm-cf-wrap input[type="text"], .qrm-cf-wrap input[type="email"], .qrm-cf-wrap input[type="number"],
            .qrm-cf-wrap select, .qrm-cf-wrap textarea { font-size:16px; min-height:44px; }
            .qrm-cf-wrap input[type="checkbox"], .qrm-cf-wrap input[type="radio"] { height:24px; width:24px; }
        }
    </style>
    <?php
}

/** Tek tıkla kopyalama scripti (ödül popup'ındaki kopyalama mantığının admin sürümü). */
function qrm_cf_copy_script() {
    static $printed = false;
    if ($printed) return;
    $printed = true;
    ?>
    <script>
    (function(){
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
        document.addEventListener('click', function(e){
            var btn = e.target.closest ? e.target.closest('.qrm-cf-copy') : null;
            if (!btn) return;
            e.preventDefault();
            var text = btn.getAttribute('data-copy') || '';
            var original = btn.textContent;
            function done() {
                btn.textContent = 'Kopyalandı ✓';
                btn.classList.add('copied');
                setTimeout(function(){ btn.textContent = original; btn.classList.remove('copied'); }, 2000);
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done).catch(function(){ legacyCopy(text, done); });
            } else {
                legacyCopy(text, done);
            }
        });
    })();
    </script>
    <?php
}

/** Shortcode kutusu + kopyala butonu (liste kartı ve düzenleyici üstü ortak kullanır). */
function qrm_cf_shortcode_box($form) {
    $shortcode = qrm_cf_form_shortcode($form);
    ob_start();
    ?>
    <div class="qrm-cf-shortcode">
        <code><?php echo esc_html($shortcode); ?></code>
        <button type="button" class="qrm-cf-copy" data-copy="<?php echo esc_attr($shortcode); ?>">Kopyala</button>
    </div>
    <?php if ($form->status !== 'active'): ?>
        <p class="qrm-cf-draft-note">
            <?php if ($form->status === 'draft'): ?>
                Bu form <strong>taslak</strong> durumda; kısa kod sayfada boş görünür. Yayına almak için durumu <strong>Aktif</strong> yapın.
            <?php else: ?>
                Bu form <strong>arşivde</strong>; kısa kod sayfada boş görünür. Yayına almak için durumu <strong>Aktif</strong> yapın.
            <?php endif; ?>
        </p>
    <?php endif;
    return ob_get_clean();
}

/** "Formlarım" sekmesinin içeriği. */
function qrm_cf_admin_forms_list_pane() {
    $forms = qrm_cf_get_forms();
    ?>
        <?php if (empty($forms)): ?>
            <div class="qrm-cf-empty">
                <div class="qrm-cf-empty-art">📝</div>
                <h2>Henüz hiç formunuz yok</h2>
                <p>
                    Buradan oluşturduğunuz her form kendi kısa kodunu alır ve gönderimleri
                    yorumlardan tamamen ayrı bir listede toplanır. İlk formunuzu oluşturmak
                    yaklaşık bir dakika sürer.
                </p>
                <a class="qrm-cf-btn-primary" href="<?php echo esc_url(qrm_cf_admin_url(['view' => 'edit'])); ?>">İlk formunu oluştur</a>
            </div>
        <?php else: ?>
            <div class="qrm-cf-grid-cards">
                <?php foreach ($forms as $form):
                    $total  = qrm_cf_count_submissions($form->id);
                    $unread = qrm_cf_count_submissions($form->id, 'new');
                ?>
                <div class="qrm-cf-card">
                    <div class="qrm-cf-card-top">
                        <div>
                            <h2><?php echo esc_html($form->title); ?></h2>
                            <?php if (trim((string) $form->description) !== ''): ?>
                                <p class="qrm-cf-card-desc"><?php echo esc_html(wp_trim_words($form->description, 18)); ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="qrm-cf-badge qrm-cf-badge-<?php echo esc_attr($form->status); ?>"><?php echo esc_html(qrm_cf_status_label($form->status)); ?></span>
                    </div>

                    <div class="qrm-cf-metrics">
                        <div class="qrm-cf-metric">
                            <span class="num"><?php echo intval($total); ?></span>
                            <span class="lbl">Gönderim</span>
                        </div>
                        <div class="qrm-cf-metric">
                            <span class="num" style="<?php echo $unread > 0 ? 'color:#1e40af;' : ''; ?>"><?php echo intval($unread); ?></span>
                            <span class="lbl">Okunmamış</span>
                        </div>
                        <div class="qrm-cf-metric">
                            <span class="num"><?php echo count(qrm_cf_get_fields($form->id)); ?></span>
                            <span class="lbl">Alan</span>
                        </div>
                    </div>

                    <?php echo qrm_cf_shortcode_box($form); ?>

                    <div class="qrm-cf-card-actions">
                        <a class="button button-primary" href="<?php echo esc_url(qrm_cf_admin_url(['view' => 'edit', 'form_id' => intval($form->id)])); ?>">Düzenle</a>
                        <a class="button" href="<?php echo esc_url(qrm_cf_admin_url(['tab' => 'submissions', 'form_id' => intval($form->id)])); ?>">
                            Gönderileri Gör<?php if ($unread > 0): ?> <span class="qrm-cf-badge qrm-cf-badge-new"><?php echo intval($unread); ?></span><?php endif; ?>
                        </a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Bu form, tüm alanları ve gönderimleri kalıcı olarak silinecek. Emin misiniz?');">
                            <?php wp_nonce_field('qrm_cf_forms_list'); ?>
                            <button type="submit" class="button qrm-cf-danger" name="qrm_cf_delete_form" value="<?php echo intval($form->id); ?>">Sil</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php
}
