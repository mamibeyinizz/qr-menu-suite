/* =====================================================================
   QR MENÜ — FRONTEND (VANILLA JS)

   v6 performans revizyonu:
   - jQuery bağımlılığı kaldırıldı (menü sayfasında ~90 KB indirme +
     ayrıştırma tasarrufu). Script 'defer' ile yüklenir.
   - Scroll sırasında bölüm konumları her karede yeniden ölçülmüyor;
     ölçümler önbelleklenip yalnızca gerçekten değiştiğinde (içerik,
     yeniden boyutlanma, görsel yüklenmesi) tazeleniyor. Eski kod her
     scroll karesinde her bölüm için offset() okuyarak zorunlu yeniden
     yerleşim (layout thrashing) tetikliyordu — mobilde ana jank kaynağı.
   - Kart listesi ölçümünde jQuery'nin :visible seçicisi kullanılmıyor
     (her kart için ayrı layout okuması demekti).
   - Kaydırma animasyonları native `scrollTo({behavior:'smooth'})` ile
     yapılıyor; JS tabanlı kare kare animasyon yok.

   Sunucudan gelen global'ler (trait-frontend.php içinde basılır):
   AJAX_URL, NONCE, SUGGEST_CFG, STICKY_ON, RMA_I18N
===================================================================== */
(function () {
'use strict';

var state = {
    activeFilters : [],
    sortBy        : '',
    search        : '',
    activeSection : '',
    scrollLocked  : false
};

var wrap, filterTrigger, filterBadge,
    panelOverlay, panelSheet,
    nav, navWrapper, content, loader, modal, modalBox;

/* -----------------------------------------------------------------
   KÜÇÜK YARDIMCILAR
----------------------------------------------------------------- */
function qs(sel, root)  { return (root || document).querySelector(sel); }
function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

function docTop(el) {
    return el.getBoundingClientRect().top + (window.pageYOffset || document.documentElement.scrollTop || 0);
}

function scrollY() {
    return window.pageYOffset || document.documentElement.scrollTop || 0;
}

// Tarayıcı `scrollTo({behavior})` seçeneğini ve CSS scroll-behavior'ı
// destekliyor mu? Desteklemiyorsa kaydırma zaten hep anlıktır.
var RMA_SMOOTH_OK = !!(document.documentElement.style &&
                       'scrollBehavior' in document.documentElement.style);

/**
 * ANINDA (animasyonsuz) kaydırma.
 *
 * Stil dosyasında `html { scroll-behavior: smooth }` tanımlı: bu yüzden
 * düz `window.scrollTo(0, y)` çağrısı bile yumuşak animasyona dönüşür.
 * Gövde kilidi açıldığı anda belge konumu 0 olduğundan, kilidi çözen
 * kaydırma 0'dan hedefe doğru animasyonla akıyordu — kullanıcının
 * gördüğü "önce en başa gitti, sonra hızla ürüne kaydı" hareketinin
 * asıl kaynağı buydu. Burada scroll-behavior hem seçenekle hem de
 * geçici satır içi stille zorla kapatılır.
 *
 * @param {number} y Hedef belge kaydırma konumu.
 */
function scrollToInstant(y) {
    var root = document.documentElement;
    var prev = root.style.scrollBehavior;

    root.style.scrollBehavior = 'auto';
    if (RMA_SMOOTH_OK) {
        window.scrollTo({ top: y, left: 0, behavior: 'auto' });
    } else {
        window.scrollTo(0, y);
    }
    root.style.scrollBehavior = prev;
}

// Sunucuya form-encoded POST. jQuery'nin dizi/nesne serileştirmesiyle
// aynı biçimi üretir, böylece PHP tarafı değişmeden çalışır.
// XHR kullanılır: fetch/Promise gerektirmez, dolayısıyla eski mobil
// tarayıcılarda da (QR menüyü tarayan her cihazda) çalışır.
function encodeForm(data) {
    var pairs = [];

    function push(key, val) {
        pairs.push(encodeURIComponent(key) + '=' + encodeURIComponent(val == null ? '' : String(val)));
    }

    Object.keys(data).forEach(function (key) {
        var val = data[key];
        if (Object.prototype.toString.call(val) === '[object Array]') {
            val.forEach(function (item) { push(key + '[]', item); });
        } else {
            push(key, val);
        }
    });

    return pairs.join('&');
}

/**
 * @param {Object}   data Gönderilecek alanlar.
 * @param {Function} cb   cb(json|null, status) — json ayrıştırılamazsa null.
 */
function postForm(data, cb) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', AJAX_URL, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;

        var json = null;
        if (xhr.status >= 200 && xhr.status < 300) {
            try { json = JSON.parse(xhr.responseText); } catch (e) { json = null; }
        }
        cb(json, xhr.status);
    };

    xhr.send(encodeForm(data));
}

/* -----------------------------------------------------------------
   ÇEVİRİ KÖPRÜSÜ
   RMA_I18N, çeviri eklentisi kuruluyken PHP tarafından basılır
   (trait-frontend.php). Yoksa Türkçe sabitlere düşülür — eklenti
   olmadan da davranış değişmez.
----------------------------------------------------------------- */
function rmaText(key, fallback) {
    return (typeof RMA_I18N !== 'undefined' && RMA_I18N && RMA_I18N[key]) || fallback;
}

function rmaLang() {
    if (typeof RMA_I18N !== 'undefined' && RMA_I18N && RMA_I18N.lang) return RMA_I18N.lang;
    return window.rmaCeviriDil || 'tr';
}

/* -----------------------------------------------------------------
   MASA BİLGİSİ
   QR kod adresi ?masa=masa-31 biçimindedir. Değer menü isteklerine
   eklenir; qr-analiz modülü görüntüleme/tıklama kayıtlarını buna göre
   masa bazında tutar. Modül kurulu değilse alan sunucuda yok sayılır.

   Adres çubuğunda parametre yoksa (ör. müşteri menüde gezerken adres
   temizlendiyse) imzalı masa oturumu çerezinden okunur; çerezin ilk
   alanı masa slug'ıdır ve içeriği sunucuda HMAC ile doğrulanır.
----------------------------------------------------------------- */
var rmaMasaCache = null;

function rmaMasa() {
    if (rmaMasaCache !== null) return rmaMasaCache;

    var masa = '';

    try {
        var m = /[?&]masa=([^&#]*)/.exec(window.location.search);
        if (m) masa = decodeURIComponent(m[1].replace(/\+/g, ' '));
    } catch (e) { masa = ''; }

    if (!masa) {
        try {
            var c = /(?:^|;\s*)qr_masa_token=([^;]*)/.exec(document.cookie);
            if (c) masa = decodeURIComponent(c[1]).split('|')[0];
        } catch (e2) { masa = ''; }
    }

    rmaMasaCache = String(masa || '').slice(0, 64);
    return rmaMasaCache;
}

/* -----------------------------------------------------------------
   NAV YÜKSEKLİĞİ / STICKY
----------------------------------------------------------------- */
var navOffsetTop = 0;

function navHeight() {
    return (nav && nav.offsetHeight) || 52;
}

function updateNavHeightVar() {
    document.documentElement.style.setProperty('--rma-nav-h', navHeight() + 'px');
}

function recalcNavOffset() {
    if (!STICKY_ON || !nav) return;
    nav.classList.remove('is-sticky');
    nav.style.transform = '';
    if (navWrapper) navWrapper.style.paddingTop = '0px';
    navOffsetTop = navWrapper ? docTop(navWrapper) : 0;
    updateNavHeightVar();
}

/* -----------------------------------------------------------------
   BÖLÜM KONUMLARI — ÖNBELLEKLİ ÖLÇÜM

   Scroll sırasında DOM'dan tek bir okuma bile yapılmaz; aktif kategori
   önceden ölçülmüş konum listesinden bulunur.
----------------------------------------------------------------- */
var sectionTops = [];   // [{ slug, top }]
var measureTimer = null;

function measureSections() {
    measureTimer = null;
    if (!content) { sectionTops = []; return; }
    var top = scrollY();
    sectionTops = qsa('.rma-section', content).map(function (el) {
        return { slug: el.getAttribute('data-cat-slug') || '', top: el.getBoundingClientRect().top + top };
    });
}

function scheduleMeasure() {
    if (measureTimer) return;
    measureTimer = setTimeout(measureSections, 120);
}

function onScroll() {
    var top  = scrollY();
    var navH = navHeight();

    if (STICKY_ON && navOffsetTop > 0 && wrap) {
        // Menü konteynerinin (.rma-wrap) viewport'a göre alt sınırı.
        // Nav yalnızca menü içeriği görünürken sabit kalmalı; menü bitince
        // (çalışma saatleri, yorumlar vb. Elementor bölümleri) serbest bırakılır.
        var wrapBottomVp = wrap.getBoundingClientRect().bottom;
        var pastTop      = top >= navOffsetTop;
        var overlap      = navH - wrapBottomVp; // menü bitişine yaklaştıkça artar (0 → navH)

        if (pastTop && overlap < navH) {
            if (!nav.classList.contains('is-sticky')) {
                nav.classList.add('is-sticky');
                if (navWrapper) navWrapper.style.paddingTop = navH + 'px';
                updateNavHeightVar();
            }
            // Menü biterken nav'ı yukarı süzerek çıkar (ani kaybolma / jank yok).
            nav.style.transform = overlap > 0 ? 'translateY(' + (-overlap) + 'px)' : '';
        } else if (nav.classList.contains('is-sticky')) {
            nav.classList.remove('is-sticky');
            if (navWrapper) navWrapper.style.paddingTop = '0px';
            nav.style.transform = '';
            updateNavHeightVar();
        }
    }
    if (!state.scrollLocked) syncNavOnScroll(top, navH);
}

function syncNavOnScroll(top, navH) {
    if (!sectionTops.length) return;

    var offset  = navH + 28;
    var current = '';
    for (var i = 0; i < sectionTops.length; i++) {
        if (top + offset >= sectionTops[i].top) current = sectionTops[i].slug;
    }
    if (current && current !== state.activeSection) {
        state.activeSection = current;
        setActiveBtn(current, true);
    }
}

function setActiveBtn(slug, scrollBtn) {
    var active = null;
    qsa('.rma-nav-btn', nav).forEach(function (btn) {
        var isActive = btn.getAttribute('data-slug') === slug;
        btn.classList.toggle('active', isActive);
        if (isActive) active = btn;
    });

    if (scrollBtn && active && nav) {
        var pos = active.offsetLeft - (nav.offsetWidth / 2) + (active.offsetWidth / 2);
        if (nav.scrollTo) {
            nav.scrollTo({ left: Math.max(0, pos), behavior: 'smooth' });
        } else {
            nav.scrollLeft = Math.max(0, pos);
        }
    }
}

var unlockTimer = null;

function scrollToSection(slug) {
    var section = qs('.rma-section[data-cat-slug="' + (window.CSS && CSS.escape ? CSS.escape(slug) : slug) + '"]', content);
    if (!section) return;

    var target = Math.max(0, docTop(section) - navHeight() - 12);
    state.scrollLocked = true;
    window.scrollTo({ top: target, behavior: 'smooth' });

    function unlock() {
        clearTimeout(unlockTimer);
        state.activeSection = slug;
        state.scrollLocked  = false;
    }

    // Kaydırma gerçekten bittiğinde çöz; desteklenmiyorsa zaman aşımı
    // güvenlik ağı olarak kalır (uzun mesafelerde erken çözülme olmasın).
    if ('onscrollend' in window) {
        window.addEventListener('scrollend', unlock, { once: true });
    }
    clearTimeout(unlockTimer);
    unlockTimer = setTimeout(unlock, 900);
}

/* -----------------------------------------------------------------
   FİLTRE PANELİ
----------------------------------------------------------------- */
function openPanel() {
    panelOverlay.classList.add('open');
    panelSheet.classList.add('open');
    document.body.style.overflow = 'hidden';
    filterTrigger.classList.add('active');
}

function closePanel() {
    panelOverlay.classList.remove('open');
    panelSheet.classList.remove('open');
    document.body.style.overflow = '';
    filterTrigger.classList.remove('active');
}

/* -----------------------------------------------------------------
   DELEGE EDİLMİŞ OLAY DİNLEYİCİLERİ
   Tüm tıklamalar tek bir document dinleyicisinde toplanır; kart sayısı
   arttıkça dinleyici sayısı artmaz. Dinleyiciler yalnızca menü bu
   sayfada gerçekten varsa (init içinde) kurulur.
----------------------------------------------------------------- */
var searchTimer = null;

function bindEvents() {

document.addEventListener('click', function (e) {
    if (!e.target || typeof e.target.closest !== 'function') return;

    var el;

    if ((el = e.target.closest('.rma-filter-card'))) {
        var cb = el.querySelector('input[type="checkbox"]');
        if (cb) {
            var isChk = !cb.checked;
            cb.checked = isChk;
            el.classList.toggle('selected', isChk);
        }
        return;
    }

    if ((el = e.target.closest('.rma-sort-pill'))) {
        qsa('.rma-sort-pill').forEach(function (pill) { pill.classList.remove('selected'); });
        el.classList.add('selected');
        var radio = el.querySelector('input[type="radio"]');
        if (radio) radio.checked = true;
        return;
    }

    if (e.target.closest('#rma-panel-reset')) {
        qsa('.rma-filter-card').forEach(function (card) {
            card.classList.remove('selected');
            var cb2 = card.querySelector('input[type="checkbox"]');
            if (cb2) cb2.checked = false;
        });
        qsa('.rma-sort-pill').forEach(function (pill) { pill.classList.remove('selected'); });
        var def = qs('.rma-sort-pill[data-value=""]');
        if (def) {
            def.classList.add('selected');
            var defRadio = def.querySelector('input[type="radio"]');
            if (defRadio) defRadio.checked = true;
        }
        return;
    }

    if (e.target.closest('#rma-panel-apply')) {
        state.activeFilters = qsa('.rma-filter-card.selected input[type="checkbox"]').map(function (cb3) {
            return cb3.value;
        });
        var selectedPill = qs('.rma-sort-pill.selected');
        state.sortBy = (selectedPill && selectedPill.getAttribute('data-value')) || '';

        var total = state.activeFilters.length + (state.sortBy ? 1 : 0);
        filterBadge.textContent = String(total);
        filterBadge.classList.toggle('visible', total > 0);
        filterTrigger.classList.toggle('has-selection', total > 0);
        closePanel();
        loadAll();
        return;
    }

    if (e.target.closest('#rma-panel-close')) { closePanel(); return; }

    if ((el = e.target.closest('.rma-nav-btn'))) {
        var slug = el.getAttribute('data-slug');
        setActiveBtn(slug, false);
        scrollToSection(slug);
        return;
    }

    if (e.target.closest('.rma-modal-close')) { closeModal(); return; }
    if (e.target === modal) { closeModal(); return; }

    if ((el = e.target.closest('.rma-card'))) { openModal(el); return; }
});

// Kartlar role="button" + tabindex taşıyor: klavye ile de açılabilsin.
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    var card = e.target.closest && e.target.closest('.rma-card');
    if (!card) return;
    e.preventDefault();
    openModal(card);
});

document.addEventListener('input', function (e) {
    if (!e.target || e.target.id !== 'rma-search') return;
    clearTimeout(searchTimer);
    var val = String(e.target.value || '').trim();
    searchTimer = setTimeout(function () {
        if (val !== state.search) { state.search = val; loadAll(); }
    }, 320);
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeModal(); closePanel(); }
    else if (modal && modal.classList.contains('open') && e.key === 'ArrowRight') rmaNav(1);
    else if (modal && modal.classList.contains('open') && e.key === 'ArrowLeft')  rmaNav(-1);
});

// Mobilde donanım/tarayıcı geri tuşu: modal açıkken pushlanan state pop
// edilince buraya düşer. Sayfadan çıkmak yerine sadece modalı kapatır.
window.addEventListener('popstate', function () {
    if (modal && modal.classList.contains('open') && !modal.classList.contains('rma-closing')) {
        rmaHistoryPushed = false;
        rmaDismiss(rmaPendingDismissMode);
    }
});

} /* bindEvents */

function restoreSearchValue() {
    var input = qs('#rma-search');
    if (input && state.search) input.value = state.search;
}

/* -----------------------------------------------------------------
   MENÜYÜ YÜKLE
----------------------------------------------------------------- */
function loadAll() {
    content.innerHTML = '';
    sectionTops = [];
    loader.style.display = 'block';

    postForm({
        action      : 'rma_load_items',
        security    : NONCE,
        filters     : state.activeFilters,
        sort_by     : state.sortBy,
        search      : state.search,
        suggest_cfg : JSON.stringify(typeof SUGGEST_CFG !== 'undefined' ? SUGGEST_CFG : {}),
        lang        : rmaLang(),
        masa        : rmaMasa()
    }, function (res, status) {
        loader.style.display = 'none';

        if (res && res.success && res.data) {
            try { sessionStorage.removeItem('rmaReloadGuard'); } catch (e) {}
            content.innerHTML = res.data.html;
            rebuildNav(res.data.categories, res.data.has_suggestions);
            recalcNavOffset();
            measureSections();
            onScroll();
            restoreSearchValue();
            watchContentSize();
            return;
        }

        // Sunucu 200 döndürdü ama success:false ise ürün yok demektir.
        if (res && status >= 200 && status < 300) {
            showEmpty(rmaText('empty', 'Ürün bulunamadı.'));
            nav.innerHTML = '';
            return;
        }

        // Bayat nonce (önbellek) durumunda sayfayı EN FAZLA BİR KEZ yenile.
        // Guard olmadan kalıcı 403 sonsuz reload döngüsüne yol açar.
        var reloaded = false;
        try { reloaded = sessionStorage.getItem('rmaReloadGuard') === '1'; } catch (e) {}

        if ((status === 403 || status === 400) && !reloaded) {
            try { sessionStorage.setItem('rmaReloadGuard', '1'); } catch (e) {}
            showEmpty(rmaText('reloading', 'Sayfa yenileniyor…'));
            setTimeout(function () { location.reload(); }, 1200);
        } else {
            showEmpty(rmaText('loadError', 'Yükleme hatası oluştu. Lütfen sayfayı yenileyin.'));
        }
    });
}

function showEmpty(text) {
    var div = document.createElement('div');
    div.className = 'rma-empty';
    div.textContent = text;
    content.innerHTML = '';
    content.appendChild(div);
    sectionTops = [];
}

function rebuildNav(cats, hasSuggestions) {
    nav.innerHTML = '';
    if (!cats || !cats.length) return;

    // Tek fragment ile tek DOM yazımı — buton başına reflow yok.
    var frag = document.createDocumentFragment();

    if (hasSuggestions) {
        var suggBtn = document.createElement('button');
        suggBtn.type = 'button';
        suggBtn.className = 'rma-nav-btn rma-suggestions-btn active';
        suggBtn.setAttribute('data-slug', '__suggestions__');
        suggBtn.textContent = rmaText('suggestions', 'Öneriler');
        frag.appendChild(suggBtn);
    }

    cats.forEach(function (cat, i) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'rma-nav-btn' + (!hasSuggestions && i === 0 ? ' active' : '');
        btn.setAttribute('data-slug', cat.slug);
        btn.textContent = cat.name;
        frag.appendChild(btn);
    });

    nav.appendChild(frag);

    state.activeSection = hasSuggestions ? '__suggestions__' : (cats.length ? cats[0].slug : '');
    updateNavHeightVar();
}

/* -----------------------------------------------------------------
   İÇERİK BOYUTU İZLEME
   Görseller yüklendikçe / font değiştikçe bölüm konumları kayar.
   ResizeObserver varsa onu, yoksa görsel yükleme olaylarını kullanırız.
----------------------------------------------------------------- */
var contentObserver = null;

function watchContentSize() {
    if (contentObserver || !content) return;
    if (typeof ResizeObserver === 'function') {
        contentObserver = new ResizeObserver(scheduleMeasure);
        contentObserver.observe(content);
    } else {
        // Yedek: her görselin yüklenmesi ölçümü tazeler (capture fazında,
        // load olayı kabarcıklanmadığı için).
        content.addEventListener('load', scheduleMeasure, true);
    }
}

/* -----------------------------------------------------------------
   GÖVDE KAYDIRMA KİLİDİ

   Modal açıkken gövde `position:fixed` yapılır. Kapanışta menü, TEK bir
   `scrollTo` çağrısıyla nihai konuma oturur. Ara adım (önce kilit
   anındaki konuma dön, sonra karta yumuşak kaydır) bilinçli olarak
   kaldırıldı: iki ayrı kaydırma mobilde "önce en başa gitti, sonra
   ürüne döndü" şeklinde çift zıplama olarak görülüyordu.
----------------------------------------------------------------- */
var rmaScrollY      = 0;      // kilit anındaki belge kaydırma konumu
var rmaScrollLocked = false;  // idempotency bayrağı

function lockBodyScroll() {
    // Zaten kilitliyken tekrar çağrılırsa konumu OKUMA: gövde fixed iken
    // scrollY() 0 döner ve rmaScrollY sıfırlanırdı (kapanışta menü en
    // başa atlıyordu).
    if (rmaScrollLocked) return;
    rmaScrollLocked = true;

    rmaScrollY = scrollY();
    var s = document.body.style;
    s.position = 'fixed';
    s.top      = -rmaScrollY + 'px';
    s.left     = '0';
    s.right    = '0';
    s.width    = '100%';
    s.overflow = 'hidden';
}

/**
 * Kilidi açar ve belgeyi TEK kaydırma işlemiyle nihai konuma oturtur.
 *
 * @param {number} [targetY] Nihai belge kaydırma konumu. Verilmezse kilit
 *                           anındaki konum kullanılır — yani parametresiz
 *                           çağrı eski davranışı birebir korur.
 */
function unlockBodyScroll(targetY) {
    if (!rmaScrollLocked) return;
    rmaScrollLocked = false;

    var y = (typeof targetY === 'number' && isFinite(targetY)) ? Math.max(0, targetY) : rmaScrollY;
    var s = document.body.style;

    // `top`, position temizlenmeden HEMEN önce nihai hedefe göre ayarlanır:
    // aradaki hiçbir noktada tarayıcının farklı bir konumu boyama fırsatı
    // olmaz (tüm blok tek event-loop turunda, senkron çalışır).
    s.top      = -y + 'px';
    s.position = '';
    s.top      = '';
    s.left     = '';
    s.right    = '';
    s.width    = '';
    s.overflow = '';

    // Gövde fixed iken belge yüksekliği çöker (içerik akıştan çıkmıştır).
    // Yeniden yerleşimi burada zorlamazsak scrollTo, henüz güncellenmemiş
    // (küçük) kaydırma aralığına kırpılabilir — sonuç: menü en başa
    // yapışır. scrollHeight okuması yerleşimi senkron olarak tazeler.
    void document.documentElement.scrollHeight;

    // Tek çağrı, anında (behavior:'auto'). İkinci bir smooth kaydırma yok.
    scrollToInstant(y);
    rmaScrollY = y;
}

/* ===== Ürün detay modalı: aç / kapat / yatay geçiş / kaydırma ===== */
// DOM-index tabanlı: aynı ürün birden çok bölümde (öneriler + kendi kategorisi)
// görünebildiği için id yerine kartın gerçek konumu (rmaIdx) esas alınır.
var rmaCards = [];      // o an görünür kart DOM elemanları (sıralı)
var rmaIds   = [];      // rmaCards ile paralel id listesi (prefetch için)
var rmaIdx   = -1;      // açık kartın listedeki konumu
var rmaBusy  = false;   // eşzamanlı geçişleri engelle
var rmaCache = {};      // id -> detay HTML (komşular önceden çekilir)

var RMA_EASE_SETTLE = 'cubic-bezier(0.32, 0.72, 0, 1)';   // yumuşak yerleşme (iOS hissi)
var RMA_EASE_OUT    = 'cubic-bezier(0.4, 0, 0.2, 1)';     // çıkış

var rmaHistoryPushed      = false;  // modal açıkken push edilmiş bir history state var mı
var rmaPendingDismissMode = 'fade'; // popstate ile kapanırken hangi animasyon kullanılacak
var rmaDismissTimer       = null;   // kapanış animasyonu zamanlayıcısı (yeniden açılışta iptal edilir)
var rmaPrevScrollRestore  = null;   // modal açılmadan önceki history.scrollRestoration değeri

/**
 * Modal açıkken tarayıcının kendi kaydırma geri yüklemesini kapatır.
 *
 * Modal açılışında push edilen history state, gövde `position:fixed`
 * iken oluşturulduğu için tarayıcı o girdinin kaydırma konumunu 0 olarak
 * kaydediyor; `history.back()` sırasında da menüyü bir anlığına en başa
 * çekiyordu. Kapanış tamamlanınca eski değer geri verilir — böylece
 * sayfadan çıkıp geri dönen kullanıcı normal geri yüklemeyi kaybetmez.
 */
function rmaHoldScrollRestoration() {
    if (!('scrollRestoration' in history) || rmaPrevScrollRestore !== null) return;
    try {
        rmaPrevScrollRestore = history.scrollRestoration;
        history.scrollRestoration = 'manual';
    } catch (e) { rmaPrevScrollRestore = null; }
}

function rmaReleaseScrollRestoration() {
    if (rmaPrevScrollRestore === null) return;
    try { history.scrollRestoration = rmaPrevScrollRestore; } catch (e) {}
    rmaPrevScrollRestore = null;
}

/**
 * Ürün detayını getirir. prefetch=true olan istekler sunucuda görüntülenme
 * sayacını ARTIRMAZ — sessiz ön yükleme gerçek bir görüntülenme değildir
 * ve her komşu kart için gereksiz bir yazma sorgusu doğuruyordu.
 */
function rmaFetch(id, prefetch, cb) {
    if (rmaCache[id]) { cb(rmaCache[id]); return; }

    var payload = { action: 'rma_get_product_details', id: id, security: NONCE, lang: rmaLang(), masa: rmaMasa() };
    if (prefetch) payload.prefetch = '1';

    postForm(payload, function (res) {
        if (res && res.success) {
            rmaCache[id] = res.data;
            // Ön yüklemede yalnızca HTML'i saklamak yetmiyordu: kullanıcı
            // komşu karta geçtiğinde metin anında geliyor ama görsel o an
            // ilk kez indirilmeye başlıyordu. Görseli de şimdiden tarayıcı
            // önbelleğine düşür.
            if (prefetch) rmaWarmImage(res.data);
            cb(res.data);
        } else {
            cb(null);
        }
    });
}

/**
 * Detay HTML'indeki ana ürün görselini arka planda indirtir (yalnızca
 * tarayıcı önbelleğini ısıtmak için; DOM'a hiçbir şey eklenmez).
 * srcset/sizes de kopyalanır, böylece ısıtılan dosya modalın gerçekten
 * göstereceği dosyayla aynı olur.
 *
 * @param {string} html Sunucudan gelen detay HTML'i.
 */
function rmaWarmImage(html) {
    if (!html) return;

    var tpl = document.createElement('template');
    if (!('content' in tpl)) return;   // çok eski tarayıcı: ısıtmayı atla

    tpl.innerHTML = html;              // template içeriği inert: kendisi indirmez
    var src = tpl.content.querySelector('img.rma-modal-img');
    if (!src) return;

    var warm = new Image();
    var ss   = src.getAttribute('srcset');
    var sz   = src.getAttribute('sizes');
    if (sz) warm.sizes  = sz;
    if (ss) warm.srcset = ss;
    warm.src = src.getAttribute('src') || '';
}

/**
 * Modal görselini "yükleniyor" durumuna alır: kartın ZATEN indirilmiş
 * küçük görseli bulanık zemin (blur-up) olarak gösterilir, büyük görsel
 * yüklenince yerine yumuşakça geçer. İndirme süresi değişmez ama
 * kullanıcı boş bir kutuya bakmaz.
 *
 * @param {number} idx rmaCards içindeki konum (küçük görselin kaynağı).
 */
function rmaPrepImage(idx) {
    var img = modal ? qs('.rma-modal-img', modal) : null;
    if (!img) return;

    var holder = img.parentNode;
    var card   = rmaCards[idx];
    var thumb  = card ? qs('.rma-card-img', card) : null;
    var ph     = thumb ? (thumb.currentSrc || thumb.src) : '';

    if (holder && holder.classList) {
        if (ph) holder.style.setProperty('--rma-ph', 'url("' + ph.replace(/"/g, '%22') + '")');
        else    holder.style.removeProperty('--rma-ph');
        holder.classList.toggle('rma-has-ph', !!ph);
        holder.classList.add('rma-img-loading');
    }

    function done() {
        if (holder && holder.classList) holder.classList.remove('rma-img-loading');
    }

    // Görsel önbellekten geldiyse (ön ısıtma işe yaradıysa) load olayı
    // hiç tetiklenmeyebilir — tamamlanmışsa hemen göster.
    if (img.complete && img.naturalWidth > 0) { done(); return; }
    img.addEventListener('load',  done, { once: true });
    img.addEventListener('error', done, { once: true });
}

function rmaAnalitikDetay(id) {
    var an = window.qrmsAnalitikOnyuz;
    if (!an || typeof an.yaz !== 'function' || !id) return;
    an.yaz('item_detail_open', { item_id: String(id) });
}

// Açık kartın komşularını (önceki + sonraki) sessizce önceden yükle —
// böylece kaydırınca AJAX beklemesi olmadan anında yumuşak geçiş olur.
function rmaPrefetch(idx) {
    [ idx - 1, idx + 1 ].forEach(function (i) {
        if (i >= 0 && i < rmaIds.length) {
            var id = rmaIds[i];
            if (!rmaCache[id]) rmaFetch(id, true, function () {});
        }
    });
}

// İçeriği doğrudan yerleştir (ilk açılış). idx = rmaCards içindeki konum.
function rmaShow(idx) {
    var id = rmaIds[idx];
    if (id == null) return;
    rmaFetch(id, false, function (html) {
        if (html == null) return;
        var inner = qs('.rma-modal-inner', modal);
        if (inner) inner.innerHTML = html;
        if (modalBox) modalBox.scrollTop = 0;
        rmaIdx = idx;
        rmaPrepImage(idx);
        rmaPrefetch(idx);
        rmaAnalitikDetay(id);
    });
}

// Sürükleme iptal edilince kutuyu merkeze yumuşakça geri yaylandır
function rmaSpringBack() {
    if (!modalBox) return;
    modalBox.style.transition = 'transform .34s ' + RMA_EASE_SETTLE + ', opacity .34s ease-out';
    modalBox.style.transform  = 'translate(0,0)';
    modalBox.style.opacity    = '1';
}

// Yatay geçiş — kutu tabanlı carousel: mevcut kart kayma yönünde savrulur,
// yeni kart (önceden cache'lenmiş) karşı taraftan yumuşakça yerleşir.
function rmaSwipe(step) {
    if (rmaBusy) return;
    var ni = rmaIdx + step;
    if (ni < 0 || ni >= rmaIds.length) { rmaSpringBack(); return; }
    if (!modalBox) return;

    var w    = modalBox.offsetWidth || 360;
    var outX = step > 0 ? -w * 0.55 : w * 0.55;   // mevcut kartın savrulma yönü
    var inX  = step > 0 ?  w * 0.55 : -w * 0.55;  // yeni kartın giriş yönü

    rmaBusy = true;
    var id = rmaIds[ni];

    // 1) mevcut kartı kayma yönünde savur + solup git
    modalBox.style.transition = 'transform .20s ' + RMA_EASE_OUT + ', opacity .20s ease-out';
    modalBox.style.transform  = 'translateX(' + outX + 'px)';
    modalBox.style.opacity    = '0';

    var swapped = false;
    function doSwap(html) {
        if (swapped) return;
        swapped = true;

        if (html != null) {
            var inner = qs('.rma-modal-inner', modal);
            if (inner) inner.innerHTML = html;
            modalBox.scrollTop = 0;
            rmaPrepImage(ni);
            rmaAnalitikDetay(id);
        }
        // 2) yeni kartı karşı tarafa ışınla (animasyonsuz)
        modalBox.style.transition = 'none';
        modalBox.style.transform  = 'translateX(' + inX + 'px)';
        modalBox.style.opacity    = '0';
        void modalBox.offsetHeight; // reflow
        // 3) merkeze yumuşakça yerleş
        modalBox.style.transition = 'transform .36s ' + RMA_EASE_SETTLE + ', opacity .30s ease-out';
        modalBox.style.transform  = 'translateX(0)';
        modalBox.style.opacity    = '1';
        rmaIdx = ni;
        rmaPrefetch(ni);
        setTimeout(function () { rmaBusy = false; }, 360);
    }

    // içerik hazır olması ile savrulma süresi (min 180ms) senkronize edilir
    var htmlReady = null, htmlDone = false, timeDone = false;
    rmaFetch(id, false, function (html) { htmlReady = html; htmlDone = true; if (timeDone) doSwap(htmlReady); });
    setTimeout(function () { timeDone = true; if (htmlDone) doSwap(htmlReady); }, 180);
}

function openModal(el) {
    rmaCards = qsa('.rma-card', content);
    rmaIds   = rmaCards.map(function (c) { return String(c.getAttribute('data-id')); });
    rmaIdx   = rmaCards.indexOf(el);           // tıklanan kartın gerçek konumu
    if (rmaIdx < 0) rmaIdx = 0;

    // Devam eden bir kapanış varsa iptal et: aksi halde 300 ms sonra
    // tetiklenen zamanlayıcı, yeni açılan modalı kapatır ve kilidi
    // yanlış konumla çözerdi.
    if (rmaDismissTimer) { clearTimeout(rmaDismissTimer); rmaDismissTimer = null; }

    // önceki kapatmadan kalan inline stilleri temizle
    if (modalBox) {
        modalBox.style.transition = '';
        modalBox.style.transform  = '';
        modalBox.style.opacity    = '';
    }
    modal.classList.remove('rma-closing');

    rmaShow(rmaIdx);
    modal.classList.add('open');
    lockBodyScroll();
    rmaInitGestures();

    // Geri tuşu ile kapatma: modal açılırken sahte bir history state push edilir.
    // Kullanıcı geri tuşuna basınca sayfadan çıkmak yerine bu state pop edilir
    // ve aşağıdaki 'popstate' dinleyicisi modalı kapatır.
    if (!rmaHistoryPushed) {
        rmaHoldScrollRestoration();
        history.pushState({ rmaModal: true }, '');
        rmaHistoryPushed = true;
    }
}

// Klavye okları da aynı yumuşak geçişi kullanır
function rmaNav(step) {
    if (rmaIdx < 0 || rmaBusy) return false;
    var ni = rmaIdx + step;
    if (ni < 0 || ni >= rmaIds.length) return false;
    rmaSwipe(step);
    return true;
}

/**
 * Modalda en son görülen kart için NİHAİ kaydırma konumunu hesaplar —
 * kaydırmayı kendisi YAPMAZ. Böylece hedef, gövde hâlâ `position:fixed`
 * iken hesaplanıp kilit açılışıyla birlikte tek seferde uygulanabilir.
 *
 * @param {number} idx   rmaCards içindeki konum.
 * @param {number} baseY Ölçümün referans aldığı belge konumu. Gövde
 *                       kilitliyken window.scrollY 0 olduğu için kilit
 *                       anındaki konum (rmaScrollY) geçilmelidir.
 * @return {number} Hedef konum; kart zaten tamamen görünürse baseY.
 */
function rmaCardTargetY(idx, baseY) {
    var el = rmaCards[idx];
    if (!el) return baseY;

    var rect    = el.getBoundingClientRect();
    var navH    = navHeight();
    var cardTop = rect.top + baseY;   // belge koordinatı
    var cardH   = rect.height;
    var visTop  = baseY + navH + 12;
    var visBot  = baseY + window.innerHeight - 12;

    // kart zaten tamamen görünüyorsa konumu değiştirme
    if (cardTop >= visTop && (cardTop + cardH) <= visBot) return baseY;
    return Math.max(0, cardTop - navH - 24);
}

// Menüyü, modalda en son görülen kartı görünür kılacak konuma ANINDA
// kaydırır. Kapanış akışı artık bunu çağırmıyor (kaydırma tek çağrıyla
// unlockBodyScroll içinde yapılıyor); imza dış kullanım için korundu.
function rmaScrollToCard(idx) {
    var base   = scrollY();
    var target = rmaCardTargetY(idx, base);
    if (target !== base) scrollToInstant(target);
}

// Animasyonlu kapatma. mode: 'down' (aşağı kaydırarak) | 'fade' (buton/overlay/esc)
function rmaDismiss(mode) {
    // Modal zaten kapanıyorsa veya kapalıysa tekrar çalıştırma
    // (requestClose -> history.back() -> popstate zincirinde çift tetiklenmeyi önler)
    if (!modal.classList.contains('open') || modal.classList.contains('rma-closing')) return;

    var lastIdx = (rmaIdx >= 0 && rmaIdx < rmaCards.length) ? rmaIdx : -1;

    if (modalBox) {
        modalBox.style.transition = 'transform .30s ' + RMA_EASE_OUT + ', opacity .28s ease-out';
        modalBox.style.transform  = (mode === 'down') ? 'translateY(100%)' : 'translateY(14px) scale(0.985)';
        modalBox.style.opacity    = '0';
    }
    modal.classList.add('rma-closing');   // overlay arka planı solar

    clearTimeout(rmaDismissTimer);
    rmaDismissTimer = setTimeout(function () {
        rmaDismissTimer = null;

        modal.classList.remove('open');
        modal.classList.remove('rma-closing');
        if (modalBox) {
            modalBox.style.transition = '';
            modalBox.style.transform  = '';
            modalBox.style.opacity    = '';
        }
        var inner = qs('.rma-modal-inner', modal);
        if (inner) inner.innerHTML = '';

        // Hedef konum, gövde HÂLÂ fixed iken hesaplanır; kilit açılışı ve
        // kaydırma tek adımda olur. Önceki sürüm önce kilit anındaki
        // konuma dönüp ardından rAF içinde karta smooth kaydırıyordu —
        // arka arkaya iki scrollTo, "önce başa, sonra ürüne" çift
        // zıplamasının kaynağıydı.
        var targetY = (lastIdx >= 0) ? rmaCardTargetY(lastIdx, rmaScrollY) : rmaScrollY;
        unlockBodyScroll(targetY);
        rmaReleaseScrollRestoration();
    }, 300);
}

// Kapatma isteklerinin tek giriş noktası. Modal açılışında bir history state
// push edildiyse önce history.back() çağrılır (fiziksel/UI geri tuşuyla aynı
// davranış); asıl kapanma animasyonu popstate dinleyicisinde tetiklenir.
// State push edilmemişse (beklenmedik durum) doğrudan kapatılır.
function requestClose(mode) {
    rmaPendingDismissMode = mode || 'fade';
    if (rmaHistoryPushed) {
        rmaHistoryPushed = false;
        history.back();
    } else {
        rmaDismiss(rmaPendingDismissMode);
    }
}

function closeModal() { requestClose('fade'); }

/* Dokunmatik jestler: en üstteyken aşağı çekme = kapat, yatay kaydırma = ürün geçişi.
   Mesafe eşiği + hız (flick) algısı ile premium his. */
function rmaInitGestures() {
    if (!modalBox || modalBox.dataset.rmaGest) return;
    modalBox.dataset.rmaGest = '1';

    var box = modalBox;
    var startX = 0, startY = 0, dx = 0, dy = 0, axis = null, active = false, atTop = false, startT = 0;
    var H_THRESH = 45, V_THRESH = 95, H_VELO = 0.35, V_VELO = 0.5;

    box.addEventListener('touchstart', function (e) {
        if (rmaBusy || e.touches.length !== 1) { active = false; return; }
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
        dx = 0; dy = 0; axis = null; active = true;
        atTop  = box.scrollTop <= 0;
        startT = Date.now();
        box.style.transition = 'none';
    }, { passive: true });

    box.addEventListener('touchmove', function (e) {
        if (!active) return;
        dx = e.touches[0].clientX - startX;
        dy = e.touches[0].clientY - startY;
        if (!axis) {
            if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 8) { axis = 'x'; }
            else if (Math.abs(dy) > Math.abs(dx) && Math.abs(dy) > 8) {
                if (atTop && dy > 0) { axis = 'y'; } else { active = false; return; }
            } else { return; }
        }
        if (axis === 'x') {
            e.preventDefault();
            var rx = dx;
            // uçlarda kauçuk direnç
            if ((rmaIdx <= 0 && dx > 0) || (rmaIdx >= rmaIds.length - 1 && dx < 0)) rx = dx * 0.28;
            box.style.transform = 'translateX(' + rx + 'px)';
            box.style.opacity   = String(Math.max(0.55, 1 - Math.abs(rx) / 900));
        } else if (axis === 'y') {
            e.preventDefault();
            var mv = Math.max(0, dy);
            box.style.transform = 'translateY(' + mv + 'px)';
            box.style.opacity   = String(Math.max(0.45, 1 - mv / 500));
        }
    }, { passive: false });

    box.addEventListener('touchend', function () {
        if (!active) return;
        active = false;
        var dt = Math.max(1, Date.now() - startT);
        if (axis === 'y') {
            var vy = dy / dt;
            if (dy > V_THRESH || vy > V_VELO) { requestClose('down'); }
            else { rmaSpringBack(); }
        } else if (axis === 'x') {
            var vx = dx / dt;
            if (Math.abs(dx) > H_THRESH || Math.abs(vx) > H_VELO) { rmaSwipe(dx < 0 ? 1 : -1); }
            else { rmaSpringBack(); }
        }
        axis = null;
    });
}

/* -----------------------------------------------------------------
   BAŞLAT
----------------------------------------------------------------- */
function init() {
    wrap          = qs('.rma-wrap');
    filterTrigger = qs('#rma-filter-trigger');
    filterBadge   = qs('#rma-filter-badge');
    panelOverlay  = qs('#rma-panel-overlay');
    panelSheet    = qs('#rma-panel-sheet');
    nav           = qs('#rma-nav');
    navWrapper    = qs('.rma-nav-wrapper');
    content       = qs('#rma-content');
    loader        = qs('#rma-loader');
    modal         = qs('#rma-modal');
    modalBox      = modal ? qs('.rma-modal-box', modal) : null;

    // Menü bu sayfada yoksa hiçbir dinleyici kurma. (Örn. yalnızca
    // [rma_qr_notice] kısa kodunun bulunduğu sayfalar.)
    if (!content || !nav || !loader) return;

    bindEvents();
    rmaInitGestures();

    if (filterTrigger) filterTrigger.addEventListener('click', openPanel);
    if (panelOverlay)  panelOverlay.addEventListener('click', closePanel);

    // Scroll: requestAnimationFrame throttle — kare başına en fazla bir
    // hesaplama. Dinleyici passive: tarayıcı kaydırmayı beklemeden sürdürür.
    var rmaTicking = false;
    window.addEventListener('scroll', function () {
        if (rmaTicking) return;
        rmaTicking = true;
        window.requestAnimationFrame(function () {
            onScroll();
            rmaTicking = false;
        });
    }, { passive: true });

    window.addEventListener('resize', function () {
        recalcNavOffset();
        scheduleMeasure();
        onScroll();
    }, { passive: true });

    // Yazı tipleri yerleşince bölüm konumları kayabilir.
    if (document.fonts && document.fonts.ready && typeof document.fonts.ready.then === 'function') {
        document.fonts.ready.then(scheduleMeasure);
    }

    loadAll();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

})();
