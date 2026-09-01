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
  var SCROLL_LOCK_CLASS = 'hfb-scroll-locked';
  var hfbScrollY = 0;
  var hfbScrollLockCount = 0;

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

  function getScrollbarWidth() {
    return Math.max(0, window.innerWidth - document.documentElement.clientWidth);
  }

  /**
   * iOS Safari dahil: gövdeyi position:fixed yapıp kaydırma konumunu saklar.
   * Zaten kilitliyken tekrar çağrılırsa scrollY okunmaz (0 döner).
   */
  function lockBodyScroll() {
    if (hfbScrollLockCount > 0) {
      hfbScrollLockCount++;
      return;
    }
    hfbScrollLockCount = 1;

    hfbScrollY = window.scrollY || window.pageYOffset || 0;

    var body = document.body;
    var pad = getScrollbarWidth();

    body.classList.add(SCROLL_LOCK_CLASS);
    if (pad > 0) {
      body.style.paddingRight = pad + 'px';
    }

    body.style.position = 'fixed';
    body.style.top = (-hfbScrollY) + 'px';
    body.style.left = '0';
    body.style.right = '0';
    body.style.width = '100%';
    body.style.overflow = 'hidden';
  }

  /**
   * Kilidi açar ve kullanıcıyı kilit anındaki kaydırma konumuna döndürür.
   */
  function unlockBodyScroll() {
    if (hfbScrollLockCount <= 0) {
      return;
    }
    hfbScrollLockCount--;
    if (hfbScrollLockCount > 0) {
      return;
    }

    var y = hfbScrollY;
    var body = document.body;
    var s = body.style;

    s.top = (-y) + 'px';
    s.position = '';
    s.top = '';
    s.left = '';
    s.right = '';
    s.width = '';
    s.overflow = '';
    s.paddingRight = '';
    body.classList.remove(SCROLL_LOCK_CLASS);

    void document.documentElement.scrollHeight;
    window.scrollTo(0, y);
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
      // Kapat: PHP aria (hfb_cevir_ui). Sabit TR yazılmaz — adım 4 tampon
      // aria'yı görmez; burada etiket zaten sunucuda basıldı.
      var closeFromPhp = closeBtn ? closeBtn.getAttribute('aria-label') : '';
      toggle.setAttribute('data-label-close', closeFromPhp || 'Menüyü kapat');
    }

    function openPanel() {
      panel.classList.add('is-open');
      panel.setAttribute('aria-hidden', 'false');
      toggle.setAttribute('aria-expanded', 'true');
      toggle.setAttribute('aria-label', toggle.getAttribute('data-label-close'));

      if (!editor) {
        lockBodyScroll();
      }
    }

    function closePanel() {
      panel.classList.remove('is-open');
      panel.setAttribute('aria-hidden', 'true');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', toggle.getAttribute('data-label-open'));

      if (!editor) {
        unlockBodyScroll();
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

    panel.addEventListener('click', function (e) {
      var link = e.target.closest('.hfb-mobile-panel__nav a');
      if (!link || !panel.classList.contains('is-open')) {
        return;
      }

      var li = link.parentElement;
      if (li && li.classList.contains('menu-item-has-children') && li.firstElementChild === link) {
        return;
      }

      closePanel();
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
