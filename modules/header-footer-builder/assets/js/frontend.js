(function () {
  'use strict';

  function initSubmenus(panel) {
    var items = panel.querySelectorAll('.menu-item-has-children > a');
    if (!items.length) {
      return;
    }

    items.forEach(function (link) {
      link.addEventListener('click', function (e) {
        var parent = link.parentElement;
        var submenu = parent.querySelector(':scope > .sub-menu');
        if (!submenu) {
          return;
        }

        e.preventDefault();
        var isOpen = parent.classList.contains('is-open');
        panel.querySelectorAll('.menu-item-has-children.is-open').forEach(function (openItem) {
          if (openItem !== parent) {
            openItem.classList.remove('is-open');
            var openLink = openItem.querySelector(':scope > a');
            if (openLink) {
              openLink.setAttribute('aria-expanded', 'false');
            }
          }
        });
        parent.classList.toggle('is-open', !isOpen);
        link.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
      });

      link.setAttribute('aria-expanded', 'false');
      link.setAttribute('aria-haspopup', 'true');
    });
  }

  function initHeaderWrap(wrap) {
    var header = wrap.querySelector('.hfb-header');
    var toggle = wrap.querySelector('.hfb-header__toggle');
    var panel = wrap.querySelector('.hfb-mobile-panel');
    if (!header || !toggle || !panel) {
      return;
    }

    var closeBtn = panel.querySelector('.hfb-mobile-panel__close');
    var backdrop = panel.querySelector('.hfb-mobile-panel__backdrop');
    var isMenulux = panel.classList.contains('hfb-mobile-panel--menulux');

    if (isMenulux) {
      initSubmenus(panel);
    }

    function openPanel() {
      panel.classList.add('is-open');
      panel.setAttribute('aria-hidden', 'false');
      toggle.setAttribute('aria-expanded', 'true');
      toggle.setAttribute('aria-label', toggle.getAttribute('data-label-close') || 'Menüyü kapat');
      document.body.style.overflow = 'hidden';
    }

    function closePanel() {
      panel.classList.remove('is-open');
      panel.setAttribute('aria-hidden', 'true');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', toggle.getAttribute('data-label-open') || 'Menüyü aç');
      document.body.style.overflow = '';

      panel.querySelectorAll('.menu-item-has-children.is-open').forEach(function (item) {
        item.classList.remove('is-open');
        var link = item.querySelector(':scope > a');
        if (link) {
          link.setAttribute('aria-expanded', 'false');
        }
      });
    }

    if (!toggle.getAttribute('data-label-open')) {
      toggle.setAttribute('data-label-open', toggle.getAttribute('aria-label') || 'Menüyü aç');
      toggle.setAttribute('data-label-close', 'Menüyü kapat');
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

    if (backdrop && !isMenulux) {
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
