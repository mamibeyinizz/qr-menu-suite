<?php
if (!defined('ABSPATH')) exit;

/**
 * Yorum/iletişim formunun ön yüz script'i.
 *
 * @param array $settings Eklenti ayarları.
 * @param int   $js_limit Sayfa boyutu. v4.2.2'den beri JS bu değeri
 *                        kullanmaz — sayfalama sunucuda yapılır ve boyut
 *                        AJAX ucunda ayardan okunur. Parametre çağrı
 *                        yerlerini kırmamak için duruyor.
 * @param bool  $has_list Sayfada yorum listesi var mı (yalnızca yorum kısa
 *                        kodunda true; iletişim formunda liste yoktur).
 * @return string
 */
function qrm_pro_render_form_script($settings, $js_limit = 0, $has_list = true) {
    ob_start();
    ?>
    <script>
    (function() {
        var qrmCfg = {
            ajaxUrl: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
            headline: <?php echo wp_json_encode($settings['google_review_headline']); ?>,
            subtext: <?php echo wp_json_encode($settings['google_review_subtext']); ?>,
            btnText: <?php echo wp_json_encode($settings['google_review_btn_text']); ?>,
            skipText: <?php echo wp_json_encode($settings['google_review_skip_text']); ?>,
            loadNonce: <?php echo wp_json_encode(wp_create_nonce('qrm_load_reviews')); ?>,
            genericError: 'Bir şeyler ters gitti, lütfen tekrar deneyin.'
        };

        function buildGoogleCta(avg, googleUrl) {
            var stars = Math.round(avg);
            var starsHtml = '★'.repeat(stars) + '☆'.repeat(5 - stars);
            return '' +
                '<div class="qrm-google-cta" id="qrmGoogleCta">' +
                    '<div class="qrm-google-cta-icon">✓</div>' +
                    '<div class="qrm-google-cta-stars">' + starsHtml + '</div>' +
                    '<h3>' + qrmCfg.headline + '</h3>' +
                    '<p>' + qrmCfg.subtext + '</p>' +
                    '<a href="' + googleUrl + '" target="_blank" rel="noopener noreferrer" class="qrm-btn qrm-google-btn">' + qrmCfg.btnText + '</a>' +
                    '<button type="button" class="qrm-google-skip" id="qrmGoogleSkipBtn">' + qrmCfg.skipText + '</button>' +
                '</div>';
        }

        // TR cep telefonu maskesi: 0 (5XX) XXX XX XX
        function formatTRPhone(value) {
            var d = (value || '').replace(/\D/g, '');
            if (d.slice(0, 2) === '90') d = d.slice(2);
            if (d[0] === '0') d = d.slice(1);
            d = d.slice(0, 10);
            if (!d) return '';
            var out = '0 (' + d.slice(0, 3);
            if (d.length > 3) out += ') ' + d.slice(3, 6); else return out;
            if (d.length > 6) out += ' ' + d.slice(6, 8);
            if (d.length > 8) out += ' ' + d.slice(8, 10);
            return out;
        }
        function initPhoneMask(form) {
            var tel = form.querySelector('.qrm-tel-input');
            if (!tel) return;
            tel.addEventListener('input', function() {
                this.value = formatTRPhone(this.value);
            });
        }

        // Aşamalı form (wizard)
        function initWizard(form) {
            var step1 = form.querySelector('[data-step="1"]');
            var step2 = form.querySelector('[data-step="2"]');
            var next  = form.querySelector('#qrm-step-next');
            var back  = form.querySelector('#qrm-step-back');
            var dots  = form.querySelectorAll('.qrm-step-dot');
            if (!step1 || !step2 || !next) return;

            form.classList.add('qrm-js');   // JS aktif -> wizard CSS'i devreye girer
            step2.hidden = true;

            function setDots(n) {
                dots.forEach(function(d) {
                    d.classList.toggle('active', parseInt(d.getAttribute('data-dot'), 10) <= n);
                });
            }
            function allRated() {
                var rows = step1.querySelectorAll('.qrm-rating-row');
                if (!rows.length) return false;
                for (var i = 0; i < rows.length; i++) {
                    if (!rows[i].querySelector('input[type=radio]:checked')) return false;
                }
                return true;
            }
            function showErr(msg) {
                var e = step1.querySelector('.qrm-step-error');
                if (!e) { e = document.createElement('div'); e.className = 'qrm-step-error'; step1.prepend(e); }
                e.textContent = msg;
            }
            function clearErr() {
                var e = step1.querySelector('.qrm-step-error');
                if (e) e.remove();
            }

            next.addEventListener('click', function() {
                if (!allRated()) { showErr('Devam etmek için lütfen tüm kriterleri puanlayın.'); return; }
                clearErr();
                step1.hidden = true; step2.hidden = false; setDots(2);
                var first = step2.querySelector('input, textarea');
                if (first) first.focus();
            });
            if (back) back.addEventListener('click', function() {
                step2.hidden = true; step1.hidden = false; setDots(1);
            });
        }

        function initReviewForm() {
            var form = document.getElementById('qrm-review-form');
            if (!form) return;

            initWizard(form);
            initPhoneMask(form);

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var btn = form.querySelector('button[type="submit"].qrm-btn');
                var label = btn.querySelector('.qrm-btn-label');
                var originalLabel = label ? label.textContent : btn.textContent;

                btn.disabled = true;
                if (label) { label.innerHTML = '<span class="qrm-spinner"></span>'; }

                var fd = new FormData(form);
                fd.append('action', 'qrm_submit_review');

                fetch(qrmCfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (!res || !res.success) {
                            btn.disabled = false;
                            if (label) { label.textContent = originalLabel; }
                            var err = form.querySelector('.qrm-alert.qrm-error');
                            if (!err) {
                                err = document.createElement('div');
                                err.className = 'qrm-alert qrm-error';
                                form.prepend(err);
                            }
                            err.textContent = (res && res.message) ? res.message : qrmCfg.genericError;
                            return;
                        }

                        var box = document.getElementById('qrm-form-box-inner');
                        if (res.show_google && res.google_url) {
                            box.innerHTML = buildGoogleCta(res.avg, res.google_url);
                            var skipBtn = document.getElementById('qrmGoogleSkipBtn');
                            if (skipBtn) {
                                skipBtn.addEventListener('click', function() {
                                    box.innerHTML = '<div class="qrm-alert qrm-success">' + (res.message || 'Değerlendirmeniz için teşekkürler!') + '</div>';
                                });
                            }
                        } else {
                            box.innerHTML = '<div class="qrm-alert qrm-success">' + (res.message || 'Değerlendirmeniz için teşekkürler!') + '</div>';
                        }
                        box.parentElement.classList.remove('qrm-fade-in');
                        void box.offsetWidth;
                        box.parentElement.classList.add('qrm-fade-in');

                        // v4.1.0: Ödül modülü açık ve puan eşiğin üzerindeyse popup açılır.
                        // Modül kapalıyken sunucu show_reward göndermez, akış eskisiyle aynıdır.
                        if (res.show_reward) {
                            if (window.qrmRewardPopup) {
                                window.qrmRewardPopup.open({
                                    reviewId: res.review_id || 0,
                                    // Kod talebini yetkilendiren tek kullanımlık
                                    // anahtar; sunucuda bu gönderim için üretildi.
                                    claim: res.reward_claim || ''
                                });
                            } else if (res.google_url) {
                                // Yedek yol (v4.2.0): popup DOM'u sayfaya ulaşmamış (tema wp_footer
                                // basmıyor, bir eklenti çıktıyı kırpmış vb.). Müşteri Google
                                // yönlendirmesini büsbütün kaybetmesin diye satır içi CTA gösterilir.
                                box.innerHTML = buildGoogleCta(res.avg, res.google_url);
                                var fbSkip = document.getElementById('qrmGoogleSkipBtn');
                                if (fbSkip) {
                                    fbSkip.addEventListener('click', function() {
                                        box.innerHTML = '<div class="qrm-alert qrm-success">' + (res.message || 'Değerlendirmeniz için teşekkürler!') + '</div>';
                                    });
                                }
                            }
                        }
                    })
                    .catch(function() {
                        btn.disabled = false;
                        if (label) { label.textContent = originalLabel; }
                        var err = form.querySelector('.qrm-alert.qrm-error');
                        if (!err) {
                            err = document.createElement('div');
                            err.className = 'qrm-alert qrm-error';
                            form.prepend(err);
                        }
                        err.textContent = qrmCfg.genericError;
                    });
            });
        }

        <?php if ($has_list): ?>
        // "Daha Fazla Göster" artık DOM'da gizlenmiş kartları açmıyor: her
        // sayfa sunucudan ayrı isteniyor. Eskiden tüm onaylı yorumlar ilk
        // yüklemede basılıp CSS ile gizleniyordu; yani sayfa ağırlığı yorum
        // sayısıyla doğrusal büyüyordu.
        function initLoadMore() {
            var btn = document.getElementById('qrm-load-more');
            if (!btn) return;

            var container = document.getElementById('qrm-reviews-container');
            if (!container) return;

            var offset  = container.querySelectorAll('.qrm-review-item').length;
            var loading = false;
            var label   = btn.textContent;

            btn.addEventListener('click', function(e) {
                e.preventDefault();
                if (loading) return;

                loading = true;
                btn.disabled = true;
                btn.textContent = 'Yükleniyor…';

                var fd = new FormData();
                fd.append('action', 'qrm_load_reviews');
                fd.append('nonce', qrmCfg.loadNonce);
                fd.append('offset', offset);

                fetch(qrmCfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        loading = false;
                        btn.disabled = false;
                        btn.textContent = label;

                        if (!res || !res.success || !res.data) {
                            btn.textContent = qrmCfg.genericError;
                            return;
                        }

                        if (res.data.html) {
                            container.insertAdjacentHTML('beforeend', res.data.html);
                            offset += (res.data.count || 0);
                        }

                        if (!res.data.has_more) {
                            btn.parentElement.removeChild(btn);
                        }
                    })
                    .catch(function() {
                        loading = false;
                        btn.disabled = false;
                        btn.textContent = qrmCfg.genericError;
                    });
            });
        }
        <?php endif; ?>

        document.addEventListener('DOMContentLoaded', function() {
            initReviewForm();
            <?php if ($has_list): ?>initLoadMore();<?php endif; ?>
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
