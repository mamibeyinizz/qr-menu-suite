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
 * @param array $list_opts Liste yapılandırması (sorgu, sayfalama modu).
 * @return string
 */
function qrm_pro_render_form_script($settings, $js_limit = 0, $has_list = true, array $list_opts = []) {
    $list_query = isset($list_opts['list_query']) && is_array($list_opts['list_query'])
        ? $list_opts['list_query']
        : (function_exists('qrm_pro_sanitize_reviews_list_query') ? qrm_pro_sanitize_reviews_list_query([]) : []);
    $pagination_mode = isset($list_opts['pagination_mode'])
        ? sanitize_key((string) $list_opts['pagination_mode'])
        : (function_exists('qrm_pro_reviews_pagination_mode') ? qrm_pro_reviews_pagination_mode($settings) : 'loadmore');
    if (!in_array($pagination_mode, ['loadmore', 'pages'], true)) {
        $pagination_mode = 'loadmore';
    }
    $media_enabled = !empty($list_opts['media_enabled']);

    ob_start();
    ?>
    <script>
    (function() {
        var qrmI18n = <?php echo wp_json_encode(qrm_ceviri_review_js_metinleri(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        function metin(anahtar, yedek) {
            if (!qrmI18n || typeof qrmI18n[anahtar] !== 'string' || qrmI18n[anahtar] === '') {
                return yedek;
            }
            return qrmI18n[anahtar];
        }
        var qrmCfg = {
            ajaxUrl: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
            headline: <?php echo wp_json_encode(qrm_ceviri_option('qrm_settings.google_review_headline', $settings['google_review_headline'])); ?>,
            subtext: <?php echo wp_json_encode(qrm_ceviri_option('qrm_settings.google_review_subtext', $settings['google_review_subtext'])); ?>,
            btnText: <?php echo wp_json_encode(qrm_ceviri_option('qrm_settings.google_review_btn_text', $settings['google_review_btn_text'])); ?>,
            skipText: <?php echo wp_json_encode(qrm_ceviri_option('qrm_settings.google_review_skip_text', $settings['google_review_skip_text'])); ?>,
            loadNonce: <?php echo wp_json_encode(wp_create_nonce('qrm_load_reviews')); ?>,
            currentLang: <?php echo wp_json_encode(qrm_pro_current_lang()); ?>,
            genericError: metin('genericError', 'Bir şeyler ters gitti, lütfen tekrar deneyin.'),
            reviewsList: <?php echo wp_json_encode([
                'paginationMode' => $pagination_mode,
                'mediaEnabled'   => $media_enabled,
                'pageSize'       => (int) $js_limit,
                'query'          => [
                    'sort'        => $list_query['sort'],
                    'star'        => (int) $list_query['star'],
                    'photos_only' => !empty($list_query['photos_only']),
                    'page'        => (int) $list_query['page'],
                ],
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
        };

        function appendLang(fd) {
            var lang = qrmCfg.currentLang || '';
            if (!lang) {
                try {
                    var params = new URLSearchParams(window.location.search);
                    lang = params.get('lang') || '';
                    if (!lang) {
                        var m = document.cookie.match(/(?:^|;\s*)rma_lang=([^;]+)/);
                        if (m) lang = decodeURIComponent(m[1]);
                    }
                } catch (e) {}
            }
            if (lang) fd.append('lang', lang);
            return fd;
        }

        function kacirHtml(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function buildGoogleCta(avg, googleUrl) {
            var stars = Math.round(avg);
            var starsHtml = '★'.repeat(stars) + '☆'.repeat(5 - stars);
            return '' +
                '<div class="qrm-google-cta" id="qrmGoogleCta">' +
                    '<div class="qrm-google-cta-icon">✓</div>' +
                    '<div class="qrm-google-cta-stars">' + starsHtml + '</div>' +
                    '<h3>' + kacirHtml(qrmCfg.headline) + '</h3>' +
                    '<p>' + kacirHtml(qrmCfg.subtext) + '</p>' +
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

        function initMediaUpload(form) {
            var wrap = form.querySelector('.qrm-media-upload');
            var input = form.querySelector('#qrm_review_media');
            if (!wrap || !input) return;

            var maxFiles = parseInt(wrap.getAttribute('data-max-files'), 10) || 2;
            var maxMb = parseFloat(wrap.getAttribute('data-max-mb')) || 3;
            var maxBytes = maxMb * 1024 * 1024;
            var previews = wrap.querySelector('.qrm-media-previews');
            var selected = [];

            function syncInput() {
                if (typeof DataTransfer === 'undefined') return;
                var dt = new DataTransfer();
                selected.forEach(function(item) { dt.items.add(item.file); });
                input.files = dt.files;
            }

            function renderPreviews() {
                previews.innerHTML = '';
                selected.forEach(function(item) {
                    var box = document.createElement('div');
                    box.className = 'qrm-media-preview';
                    var img = document.createElement('img');
                    img.src = item.url;
                    img.alt = '';
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'qrm-media-preview-remove';
                    btn.setAttribute('aria-label', 'Kaldır');
                    btn.textContent = '\u00d7';
                    btn.addEventListener('click', function() {
                        URL.revokeObjectURL(item.url);
                        selected = selected.filter(function(s) { return s !== item; });
                        syncInput();
                        renderPreviews();
                    });
                    box.appendChild(img);
                    box.appendChild(btn);
                    previews.appendChild(box);
                });
            }

            input.addEventListener('change', function() {
                var files = Array.prototype.slice.call(input.files || []);
                var err = form.querySelector('.qrm-media-error');
                if (err) err.remove();

                files.forEach(function(file) {
                    if (selected.length >= maxFiles) return;
                    if (file.size > maxBytes) return;
                    if (!/^image\/(jpeg|png|webp)$/i.test(file.type)) return;
                    selected.push({ file: file, url: URL.createObjectURL(file) });
                });

                if (files.length && selected.length === 0) {
                    var msg = document.createElement('div');
                    msg.className = 'qrm-alert qrm-error qrm-media-error';
                    msg.textContent = metin('mediaError', 'Geçersiz görsel veya boyut sınırı aşıldı.');
                    wrap.appendChild(msg);
                }

                syncInput();
                renderPreviews();
                input.value = '';
            });
        }

        // Aşamalı form (wizard)
        <?php echo qrm_pro_steps_wizard_js(); ?>

        function initWizard(form) {
            if (!form || !form.getAttribute('data-qrm-steps')) return;
            qrmInitSteps(form, { buildSummary: qrmBuildReviewSummary });
        }

        function initReviewForm() {
            var form = document.getElementById('qrm-review-form');
            if (!form) return;

            initWizard(form);
            initPhoneMask(form);
            initMediaUpload(form);

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var btn = form.querySelector('button[type="submit"].qrm-btn');
                var label = btn.querySelector('.qrm-btn-label');
                var originalLabel = label ? label.textContent : btn.textContent;

                btn.disabled = true;
                if (label) { label.innerHTML = '<span class="qrm-spinner"></span>'; }

                var fd = new FormData(form);
                fd.append('action', 'qrm_submit_review');
                appendLang(fd);

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
                                    box.innerHTML = '<div class="qrm-alert qrm-success">' + (res.message || metin('thanks', 'Değerlendirmeniz için teşekkürler!')) + '</div>';
                                });
                            }
                        } else {
                            box.innerHTML = '<div class="qrm-alert qrm-success">' + (res.message || metin('thanks', 'Değerlendirmeniz için teşekkürler!')) + '</div>';
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
                                        box.innerHTML = '<div class="qrm-alert qrm-success">' + (res.message || metin('thanks', 'Değerlendirmeniz için teşekkürler!')) + '</div>';
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
        function initReviewsList() {
            var container = document.getElementById('qrm-reviews-container');
            if (!container || !qrmCfg.reviewsList) return;

            var sortEl = document.getElementById('qrm-reviews-sort');
            var starEl = document.getElementById('qrm-reviews-star');
            var photosEl = document.getElementById('qrm-reviews-photos');
            var loadMoreBtn = document.getElementById('qrm-load-more');
            var loadMoreWrap = document.getElementById('qrm-load-more-wrap');
            var pagesWrap = document.getElementById('qrm-reviews-pagination-wrap');
            var mode = qrmCfg.reviewsList.paginationMode || 'loadmore';
            var loading = false;
            var loadMoreLabel = loadMoreBtn ? loadMoreBtn.textContent : metin('loadMore', 'Daha Fazla Göster');

            var state = {
                sort: qrmCfg.reviewsList.query.sort || 'newest',
                star: parseInt(qrmCfg.reviewsList.query.star, 10) || 0,
                photos: !!qrmCfg.reviewsList.query.photos_only,
                page: parseInt(qrmCfg.reviewsList.query.page, 10) || 1
            };
            var offset = container.querySelectorAll('.qrm-review-item').length;

            function readControls() {
                if (sortEl) state.sort = sortEl.value;
                if (starEl) state.star = parseInt(starEl.value, 10) || 0;
                if (photosEl) state.photos = photosEl.checked;
            }

            function writeControls() {
                if (sortEl) sortEl.value = state.sort;
                if (starEl) starEl.value = String(state.star || 0);
                if (photosEl) photosEl.checked = !!state.photos;
            }

            function syncUrl() {
                var params = new URLSearchParams(window.location.search);
                if (state.sort && state.sort !== 'newest') params.set('qrm_sort', state.sort); else params.delete('qrm_sort');
                if (state.star > 0) params.set('qrm_star', String(state.star)); else params.delete('qrm_star');
                if (state.photos) params.set('qrm_photos', '1'); else params.delete('qrm_photos');
                if (mode === 'pages' && state.page > 1) params.set('qrm_page', String(state.page)); else params.delete('qrm_page');
                var qs = params.toString();
                history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : '') + window.location.hash);
            }

            function bindClearFilters() {
                container.querySelectorAll('.qrm-reviews-clear-filters').forEach(function(link) {
                    if (link.dataset.qrmBound) return;
                    link.dataset.qrmBound = '1';
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        state.sort = 'newest';
                        state.star = 0;
                        state.photos = false;
                        state.page = 1;
                        writeControls();
                        reloadList(false);
                    });
                });
            }

            function bindPageButtons() {
                var nav = document.getElementById('qrm-reviews-pages');
                if (!nav) return;
                nav.querySelectorAll('.qrm-reviews-page-btn').forEach(function(btn) {
                    if (btn.dataset.qrmBound) return;
                    btn.dataset.qrmBound = '1';
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        var p = parseInt(btn.getAttribute('data-page'), 10);
                        if (!p || p === state.page) return;
                        state.page = p;
                        reloadList(false);
                        container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                });
            }

            function updatePagination(data) {
                if (mode !== 'pages' || !pagesWrap) return;
                pagesWrap.innerHTML = data.pagination_html || '';
                bindPageButtons();
            }

            function updateLoadMore(data, append) {
                if (mode !== 'loadmore' || !loadMoreWrap) return;
                if (!append) offset = data.count || 0;
                else offset += (data.count || 0);
                if (data.has_more) {
                    loadMoreWrap.style.display = '';
                } else {
                    loadMoreWrap.style.display = 'none';
                }
            }

            function reloadList(append) {
                if (loading) return;
                loading = true;
                if (loadMoreBtn) {
                    loadMoreBtn.disabled = true;
                    if (!append) loadMoreBtn.textContent = metin('loading', 'Yükleniyor…');
                }

                var fd = new FormData();
                fd.append('action', 'qrm_load_reviews');
                fd.append('nonce', qrmCfg.loadNonce);
                fd.append('qrm_sort', state.sort);
                if (state.star > 0) fd.append('qrm_star', String(state.star));
                if (state.photos) fd.append('qrm_photos', '1');
                appendLang(fd);
                if (mode === 'pages') {
                    fd.append('qrm_page', String(state.page));
                } else {
                    fd.append('offset', append ? offset : 0);
                }

                fetch(qrmCfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        loading = false;
                        if (loadMoreBtn) {
                            loadMoreBtn.disabled = false;
                            loadMoreBtn.textContent = loadMoreLabel;
                        }
                        if (!res || !res.success || !res.data) {
                            if (loadMoreBtn) loadMoreBtn.textContent = qrmCfg.genericError;
                            return;
                        }
                        if (append && res.data.html) {
                            container.insertAdjacentHTML('beforeend', res.data.html);
                        } else {
                            container.innerHTML = res.data.html || '';
                        }
                        updatePagination(res.data);
                        updateLoadMore(res.data, append);
                        syncUrl();
                        bindClearFilters();
                    })
                    .catch(function() {
                        loading = false;
                        if (loadMoreBtn) {
                            loadMoreBtn.disabled = false;
                            loadMoreBtn.textContent = qrmCfg.genericError;
                        }
                    });
            }

            function onFilterChange() {
                readControls();
                state.page = 1;
                offset = 0;
                reloadList(false);
            }

            if (sortEl) sortEl.addEventListener('change', onFilterChange);
            if (starEl) starEl.addEventListener('change', onFilterChange);
            if (photosEl) photosEl.addEventListener('change', onFilterChange);
            if (loadMoreBtn) {
                loadMoreBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    reloadList(true);
                });
            }

            bindPageButtons();
            bindClearFilters();
            writeControls();
        }
        <?php endif; ?>

        document.addEventListener('DOMContentLoaded', function() {
            initReviewForm();
            <?php if ($has_list): ?>initReviewsList();<?php endif; ?>
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
