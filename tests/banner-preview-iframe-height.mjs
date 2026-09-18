/**
 * Kampanya banner iframe yüksekliği — davranış regresyonu (Node + jsdom).
 *
 * Amaç: oran/cihaz değişince yükseklik hem artabilir hem azalabilir;
 * documentElement.scrollHeight ile tek yönde şişme olmamalı.
 */
import { JSDOM } from 'jsdom';
import core from '../modules/restoran-menu/assets/js/banner-preview-iframe-core.js';

const { buildIframeDocument, computeIframeContentHeight } = core;

function ratioHeight(vw, ratio) {
    const parts = String(ratio).split(':').map(Number);
    const w = parts[0] || 16;
    const h = parts[1] || 9;
    return Math.ceil((vw * h) / w);
}

function docWithBanner(vw, ratio, previousIframeHeight) {
    const html = buildIframeDocument({
        kokHtml: '<div class="qmo-banner-root"></div>',
        viewportWidth: vw,
        assets: {}
    });
    const dom = new JSDOM(html, { pretendToBeVisual: true });
    const doc = dom.window.document;
    const root = doc.querySelector('.qmo-banner-root');
    const h = ratioHeight(vw, ratio);
    root.style.width = vw + 'px';
    root.style.height = h + 'px';
    root.getBoundingClientRect = () => ({
        width: vw,
        height: h,
        top: 0,
        left: 0,
        right: vw,
        bottom: h
    });

    if (previousIframeHeight) {
        doc.documentElement.style.height = previousIframeHeight + 'px';
        doc.body.style.minHeight = previousIframeHeight + 'px';
        Object.defineProperty(doc.documentElement, 'scrollHeight', {
            configurable: true,
            get: () => previousIframeHeight
        });
        Object.defineProperty(doc.body, 'scrollHeight', {
            configurable: true,
            get: () => previousIframeHeight
        });
    }

    return { doc, expected: h };
}

function assertDecrease(label, vw, fromRatio, toRatio) {
    const first = docWithBanner(vw, fromRatio, 0);
    const h1 = computeIframeContentHeight(first.doc, vw);
    const second = docWithBanner(vw, toRatio, h1);
    const h2 = computeIframeContentHeight(second.doc, vw);

    if (!(h2 < h1)) {
        throw new Error(`${label}: beklenen küçülme yok (${h1} → ${h2})`);
    }
    if (h2 !== second.expected) {
        throw new Error(`${label}: kök ölçümü ${second.expected}, sonuç ${h2}`);
    }
}

function assertChange(label, vw, fromRatio, toRatio, direction) {
    const first = docWithBanner(vw, fromRatio, 0);
    const h1 = computeIframeContentHeight(first.doc, vw);
    const second = docWithBanner(vw, toRatio, h1);
    const h2 = computeIframeContentHeight(second.doc, vw);

    if (direction === 'down' && !(h2 < h1)) {
        throw new Error(`${label}: beklenen azalma yok (${h1} → ${h2})`);
    }
    if (direction === 'up' && !(h2 > h1)) {
        throw new Error(`${label}: beklenen artış yok (${h1} → ${h2})`);
    }
}

const vwDesktop = 1280;
const vwMobile = 390;

assertDecrease('16:9 → 21:9', vwDesktop, '16:9', '21:9');
assertChange('21:9 → 4:3', vwDesktop, '21:9', '4:3', 'up');
assertDecrease('4:3 → 3:1', vwDesktop, '4:3', '3:1');
assertChange('3:1 → 16:9', vwDesktop, '3:1', '16:9', 'up');

const desk = docWithBanner(vwDesktop, '16:9', 0);
const deskH = computeIframeContentHeight(desk.doc, vwDesktop);
const mob = docWithBanner(vwMobile, '16:9', deskH);
const mobH = computeIframeContentHeight(mob.doc, vwMobile);
if (!(mobH < deskH)) {
    throw new Error(`desktop → mobile: ${deskH} → ${mobH}`);
}

const mob2 = docWithBanner(vwMobile, '16:9', 0);
const mobH2 = computeIframeContentHeight(mob2.doc, vwMobile);
const desk2 = docWithBanner(vwDesktop, '16:9', mobH2);
const deskH2 = computeIframeContentHeight(desk2.doc, vwDesktop);
if (!(deskH2 > mobH2)) {
    throw new Error(`mobile → desktop: ${mobH2} → ${deskH2}`);
}

if (buildIframeDocument({ kokHtml: '<div></div>', viewportWidth: 1280, assets: {} }).includes('min-height:100vh')) {
    throw new Error('buildIframeDocument hâlâ body min-height:100vh içeriyor');
}

console.log('banner-preview-iframe-height: OK');
