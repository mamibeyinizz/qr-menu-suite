/**
 * Header Footer Builder — ön yüz davranışı.
 *
 * Elementor notu: editör/önizleme çerçevesinde widget'lar AJAX ile yeniden
 * basılır ve DOMContentLoaded bir daha çalışmaz. Bu yüzden init idempotent
 * (data-hfb-ready) ve Elementor'un frontend kancalarına da bağlı. Editörde
 * gövde kaydırma kilidi kurulmaz — tuvalin kendi kaydırması bozulmasın.
 */
(function () {
  'use strict';

  var READY_FLAG = 'hfbReady';

  function isEditor(wrap) {
    if (wrap.querySelector('.hfb-header--editor')) {
      return true;
    }

    if (wrap.closest && wrap.closest('#hfb-preview-stage')) {
      return true;
    }

    try {
      return !!(window.elementorFrontend &&
        typeof window.elementorFrontend.isEditMode === 'function' &&
        window.elementorFrontend.isEditMode());
    } catch (e) {
      return false;
    }
  }

  function initSubmenus(panel) {
    var items = panel.querySelectorAll('.menu-item-has-children > a');

    Array.prototype.forEach.call(items, function (link) {
      var parent = link.parentElement;
      var submenu = parent ? parent.querySelector('.sub-menu') : null;

      if (!submenu) {
        return;
      }

      link.setAttribute('aria-expanded', 'false');
      link.setAttribute('aria-haspopup', 'true');

      link.addEventListener('click', function (e) {
        e.preventDefault();

        var isOpen = parent.classList.contains('is-open');

        Array.prototype.forEach.call(
          panel.querySelectorAll('.menu-item-has-children.is-open'),
          function (openItem) {
            if (openItem !== parent) {
              openItem.classList.remove('is-open');
              var openLink = openItem.querySelector('a');
              if (openLink) {
                openLink.setAttribute('aria-expanded', 'false');
              }
            }
          }
        );

        if (isOpen) {
          parent.classList.remove('is-open');
        } else {
          parent.classList.add('is-open');
        }

        link.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
      });
    });
  }

  function initHeaderWrap(wrap) {
    if (!wrap || wrap.dataset[READY_FLAG] === '1') {
      return;
    }
    wrap.dataset[READY_FLAG] = '1';

    var header = wrap.querySelector('.hfb-header');
    var toggle = wrap.querySelector('.hfb-header__toggle');
    var panel = wrap.querySelector('.hfb-mobile-panel');

    if (!header || !toggle || !panel) {
      return;
    }

    var closeBtn = panel.querySelector('.hfb-mobile-panel__close');
    var backdrop = panel.querySelector('.hfb-mobile-panel__backdrop');
    var editor = isEditor(wrap);

    initSubmenus(panel);

    if (!toggle.getAttribute('data-label-open')) {
      toggle.setAttribute('data-label-open', toggle.getAttribute('aria-label') || 'Menüyü aç');
      toggle.setAttribute('data-label-close', 'Menüyü kapat');
    }

    function openPanel() {
      panel.classList.add('is-open');
      panel.setAttribute('aria-hidden', 'false');
      toggle.setAttribute('aria-expanded', 'true');
      toggle.setAttribute('aria-label', toggle.getAttribute('data-label-close'));

      if (!editor) {
        document.body.style.overflow = 'hidden';
      }
    }

    function closePanel() {
      panel.classList.remove('is-open');
      panel.setAttribute('aria-hidden', 'true');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', toggle.getAttribute('data-label-open'));

      if (!editor) {
        document.body.style.overflow = '';
      }

      Array.prototype.forEach.call(
        panel.querySelectorAll('.menu-item-has-children.is-open'),
        function (item) {
          item.classList.remove('is-open');
          var link = item.querySelector('a');
          if (link) {
            link.setAttribute('aria-expanded', 'false');
          }
        }
      );
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
      if ((e.key === 'Escape' || e.key === 'Esc') && panel.classList.contains('is-open')) {
        closePanel();
      }
    });

    if (header.classList.contains('hfb-header--sticky') && !editor) {
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

  function boot(scope) {
    var root = scope && scope.querySelectorAll ? scope : document;
    var wraps = root.querySelectorAll('.hfb-header-wrap');

    Array.prototype.forEach.call(wraps, initHeaderWrap);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      boot(document);
    });
  } else {
    boot(document);
  }

  // Elementor editörü / önizlemesi: widget yeniden basıldığında tekrar bağla.
  window.addEventListener('elementor/frontend/init', function () {
    try {
      if (window.elementorFrontend && window.elementorFrontend.hooks) {
        window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function ($scope) {
          boot($scope && $scope[0] ? $scope[0] : document);
        });
      }
    } catch (e) {
      /* Elementor API'si beklenenden farklıysa sessizce standart init'e düş. */
    }

    boot(document);
  });

  window.qrmsHfbBoot = boot;
})();
