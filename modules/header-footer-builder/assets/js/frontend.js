(function () {
  'use strict';

  function initHeaderWrap(wrap) {
    var header = wrap.querySelector('.hfb-header');
    var toggle = wrap.querySelector('.hfb-header__toggle');
    var panel = wrap.querySelector('.hfb-mobile-panel');
    if (!header || !toggle || !panel) {
      return;
    }

    var closeBtn = panel.querySelector('.hfb-mobile-panel__close');
    var backdrop = panel.querySelector('.hfb-mobile-panel__backdrop');

    function openPanel() {
      panel.classList.add('is-open');
      panel.setAttribute('aria-hidden', 'false');
      toggle.setAttribute('aria-expanded', 'true');
      document.body.style.overflow = 'hidden';
    }

    function closePanel() {
      panel.classList.remove('is-open');
      panel.setAttribute('aria-hidden', 'true');
      toggle.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    }

    toggle.addEventListener('click', function () {
      if (panel.classList.contains('is-open')) {
        closePanel();
      } else {
        openPanel();
      }
    });

    if (closeBtn) {
      closeBtn.addEventListener('click', closePanel);
    }

    if (backdrop) {
      backdrop.addEventListener('click', closePanel);
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && panel.classList.contains('is-open')) {
        closePanel();
      }
    });

    if (header.classList.contains('hfb-header--sticky') && header.classList.contains('hfb-header--minimal-sticky')) {
      var onScroll = function () {
        if (window.scrollY > 24) {
          header.classList.add('is-scrolled');
        } else {
          header.classList.remove('is-scrolled');
        }
      };
      onScroll();
      window.addEventListener('scroll', onScroll, { passive: true });
    }
  }

  function boot() {
    document.querySelectorAll('.hfb-header-wrap').forEach(initHeaderWrap);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
