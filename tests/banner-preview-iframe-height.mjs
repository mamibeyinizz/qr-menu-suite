/**
 * H1 regression guard — banner preview iframe content height calculation.
 *
 * NOT a real Chromium/layout test: no aspect-ratio, ResizeObserver, iframe
 * lifecycle, or picture/source selection. Uses plain object mocks for
 * querySelector + getBoundingClientRect only.
 *
 * Guards against reintroducing ratchet logic such as:
 *   Math.max(previousIframeHeight, documentElement.scrollHeight)
 * which would keep iframe height at 960 when the banner root measures 427.
 */
import core from '../modules/restoran-menu/assets/js/banner-preview-iframe-core.js';

const { buildIframeDocument, computeIframeContentHeight } = core;

/**
 * Minimal Document-like stub: stale scrollHeight simulates a tall iframe shell.
 *
 * @param {number} rootHeight        .qmo-banner-root getBoundingClientRect height
 * @param {number} [staleScrollHeight] inflated document/body scrollHeight
 * @return {object}
 */
function mockDoc(rootHeight, staleScrollHeight) {
    const scroll = typeof staleScrollHeight === 'number' ? staleScrollHeight : rootHeight;
    const root = {
        getBoundingClientRect: () => ({
            width: 1280,
            height: rootHeight,
            top: 0,
            left: 0,
            right: 1280,
            bottom: rootHeight
        })
    };

    return {
        querySelector(sel) {
            return sel === '.qmo-banner-root' ? root : null;
        },
        documentElement: { scrollHeight: scroll },
        body: { scrollHeight: scroll }
    };
}

/** Old ratchet approach this suite must not match production compute. */
function legacyRatchetHeight(rootHeight, staleScrollHeight) {
    return Math.max(staleScrollHeight, rootHeight);
}

function assertHeight(label, rootHeight, staleScrollHeight, viewportWidth, expected) {
    const doc = mockDoc(rootHeight, staleScrollHeight);
    const result = computeIframeContentHeight(doc, viewportWidth);

    if (result !== expected) {
        throw new Error(`${label}: beklenen ${expected}, gelen ${result}`);
    }

    const ratchet = legacyRatchetHeight(rootHeight, staleScrollHeight);
    if (ratchet !== expected && result === ratchet) {
        throw new Error(`${label}: ratchet mantığı geri gelmiş (${ratchet})`);
    }
}

// Explicit H1 scenarios (stale scrollHeight must not win over root measurement).
assertHeight('960 shell → root 427', 427, 960, 1280, 427);
assertHeight('720 shell → root 549', 549, 720, 1280, 549);
assertHeight('427 shell → root 720', 720, 427, 1280, 720);

function assertTransition(label, vw, fromRoot, toRoot) {
    const first = computeIframeContentHeight(mockDoc(fromRoot, 0), vw);
    const second = computeIframeContentHeight(mockDoc(toRoot, first), vw);

    if (toRoot < fromRoot && !(second < first)) {
        throw new Error(`${label}: küçülme yok (${first} → ${second})`);
    }
    if (toRoot > fromRoot && !(second > first)) {
        throw new Error(`${label}: büyüme yok (${first} → ${second})`);
    }
    if (second !== toRoot) {
        throw new Error(`${label}: kök ${toRoot}, sonuç ${second}`);
    }
}

assertTransition('16:9→21:9 (720→549 @1280)', 1280, 720, 549);
assertTransition('21:9→4:3 (549→960 @1280)', 1280, 549, 960);
assertTransition('desktop→mobile (720→219 @390)', 390, 720, 219);

if (buildIframeDocument({ kokHtml: '<div></div>', viewportWidth: 1280, assets: {} }).includes('min-height:100vh')) {
    throw new Error('buildIframeDocument hâlâ body min-height:100vh içeriyor');
}

console.log('banner-preview-iframe-height: OK');
