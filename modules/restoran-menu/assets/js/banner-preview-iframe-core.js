/**
 * Kampanya banner admin iframe önizlemesi — saf yardımcılar.
 *
 * admin-ui.js bu modülü kullanır; yükseklik regresyon testleri doğrudan
 * aynı fonksiyonları çalıştırır (tek üretim yolu).
 */
(function (root, factory) {
    'use strict';

    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.QMO_BANNER_PREVIEW_IFRAME_CORE = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    /**
     * @param {object} opts
     * @param {string} opts.kokHtml
     * @param {number} opts.viewportWidth
     * @param {{css?: string, js?: string, fonts?: string}} opts.assets
     * @return {string}
     */
    function buildIframeDocument(opts) {
        var o = opts || {};
        var vw = o.viewportWidth || 1280;
        var assets = o.assets || {};
        var css = assets.css || '';
        var js = assets.js || '';
        var fonts = assets.fonts || '';
        var govde = o.kokHtml && String(o.kokHtml).trim() ? String(o.kokHtml).trim() : '';

        if (!govde) {
            govde = '<div class="qmo-banner-root" style="aspect-ratio:16/9;display:flex;align-items:center;justify-content:center;background:#0d0d10;color:#c9a84c;font-family:system-ui,sans-serif;"><span>Henüz görselli kampanya yok</span></div>';
        }

        return '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=' + vw + '">'
            + (fonts ? '<link rel="stylesheet" href="' + fonts + '">' : '')
            + (css ? '<link rel="stylesheet" href="' + css + '">' : '')
            + '<style>html,body{margin:0;padding:0;background:#0a0a0c;}</style>'
            + '</head><body>' + govde
            + (js ? '<script src="' + js + '" defer><\/script>' : '')
            + '</body></html>';
    }

    /**
     * Iframe iç yüksekliği — banner kökü ölçülür; geçmiş iframe yüksekliği
     * veya documentElement.scrollHeight ile şişirilmez.
     *
     * @param {Document|null} doc
     * @param {number} viewportWidth
     * @param {number} [minFallback] vw * 0.45 varsayılan
     * @return {number}
     */
    function computeIframeContentHeight(doc, viewportWidth, minFallback) {
        var vw = viewportWidth || 1280;
        var height = typeof minFallback === 'number' ? minFallback : vw * 0.45;

        if (!doc) {
            return height;
        }

        var rootEl = doc.querySelector('.qmo-banner-root');
        if (rootEl && typeof rootEl.getBoundingClientRect === 'function') {
            height = Math.max(Math.ceil(rootEl.getBoundingClientRect().height), 1);
        } else if (doc.body) {
            height = Math.max(doc.body.scrollHeight, height);
        }

        return height;
    }

    return {
        buildIframeDocument: buildIframeDocument,
        computeIframeContentHeight: computeIframeContentHeight
    };
}));
