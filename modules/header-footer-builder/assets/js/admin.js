/**
 * Header Footer Builder — yönetim ekranı.
 *
 * Üç iş: sekme geçişi, sekme-içi adım sihirbazı, canlı önizleme.
 *
 * Önizlemenin sözleşmesi basit tutuldu: istemci HİÇ stil/HTML üretmez,
 * formu olduğu gibi serileştirip sunucuya gönderir ve dönen HTML'i basar.
 * Böylece önizleme ile kaydedilen çıktı arasında sapma olamaz. Gecikmiş
 * yanıtlar bir istek sayacıyla elenir (yavaş bir cevap, sonrasında gelen
 * taze cevabın üstüne yazamaz).
 */
(function ($) {
  'use strict';

  var DEBOUNCE_MS = 300;
  var debounceTimer = null;
  var requestSeq = 0;
  var lastPayload = null;
  var keepPanelOpen = false;

  /* ------------------------------------------------------------ Sekmeler */

  function activateTab(slug) {
    var $tabs = $('.hfb-tabs__link');
    var $panels = $('.hfb-tab-panel');

    if (!$tabs.filter('[data-hfb-tab="' + slug + '"]').length) {
      return;
    }

    $tabs.each(function () {
      var isActive = $(this).data('hfb-tab') === slug;
      $(this).toggleClass('nav-tab-active', isActive).attr('aria-selected', isActive ? 'true' : 'false');
    });

    $panels.each(function () {
      var isActive = $(this).data('hfb-panel') === slug;
      $(this).toggleClass('is-active', isActive).prop('hidden', !isActive);
    });

    if (slug === 'hamburger') {
      setPreviewMode('mobile');
    }

    if (window.history && window.history.replaceState) {
      try {
        var url = new URL(window.location.href);
        url.searchParams.set('tab', slug);
        window.history.replaceState({}, '', url.toString());
      } catch (e) {
        /* Adres güncellenemezse sekme yine de çalışır. */
      }
    }
  }

  function initTabs() {
    $(document).on('click', '.hfb-tabs__link', function (e) {
      e.preventDefault();
      activateTab($(this).data('hfb-tab'));
    });

    $(document).on('keydown', '.hfb-tabs__link', function (e) {
      if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') {
        return;
      }

      e.preventDefault();

      var $tabs = $('.hfb-tabs__link');
      var index = $tabs.index(this);
      var next = e.key === 'ArrowRight' ? index + 1 : index - 1;

      if (next < 0) {
        next = $tabs.length - 1;
      }
      if (next >= $tabs.length) {
        next = 0;
      }

      var $target = $tabs.eq(next);
      activateTab($target.data('hfb-tab'));
      $target.trigger('focus');
    });
  }

  /* -------------------------------------------------------- Adım sihirbazı */

  /**
   * Her sekme kendi adımlarını taşır. Kartlar DOM'da kalır (display:none);
   * submit tüm adımların verisini gönderir — vitrin initVitrinStepper ile
   * aynı sözleşme, ayrı "adım kaydet" yoktur.
   */
  function initSteppers() {
    $('.hfb-tab-panel').each(function () {
      var $panel = $(this);
      var $steps = $panel.find('.hfb-step');

      if (!$steps.length) {
        return;
      }

      var slug = $panel.data('hfb-panel');
      var toplam = $steps.length;
      var mevcut = 1;
      var $btns = $panel.find('.hfb-step-btn');
      var $compact = $panel.find('[data-hfb-step-compact]');
      var $prev = $panel.find('.hfb-step-prev');
      var $next = $panel.find('.hfb-step-next');

      function baslik(adimNo) {
        return $steps.filter('[data-step="' + adimNo + '"]').data('step-title') || '';
      }

      function goster(adimNo) {
        mevcut = Math.min(Math.max(adimNo, 1), toplam);

        $steps.each(function () {
          var no = parseInt($(this).data('step'), 10);
          $(this).toggle(no === mevcut);
        });

        $btns.each(function () {
          var no = parseInt($(this).data('step-target'), 10);
          $(this)
            .toggleClass('is-active', no === mevcut)
            .toggleClass('is-done', no < mevcut)
            .attr('aria-selected', no === mevcut ? 'true' : 'false');
        });

        $compact.text('Adım ' + mevcut + '/' + toplam + ': ' + baslik(mevcut));
        $prev.prop('disabled', mevcut === 1);
        $next.toggle(mevcut < toplam);
      }

      $btns.on('click', function () {
        goster(parseInt($(this).data('step-target'), 10));
      });

      $prev.on('click', function () {
        goster(mevcut - 1);
      });

      $next.on('click', function () {
        goster(mevcut + 1);
      });

      goster(1);
      $panel.data('hfb-stepper-slug', slug);
    });
  }

  /* ---------------------------------------------------------- Önizleme */

  function serializeForm($form) {
    var data = {};

    $form.find('input, select, textarea').each(function () {
      var $el = $(this);
      var name = $el.attr('name');

      if (!name || $el.is(':disabled')) {
        return;
      }

      var type = ($el.attr('type') || '').toLowerCase();

      if (type === 'checkbox') {
        if (name.slice(-2) === '[]') {
          var key = name.slice(0, -2);
          if (!data[key]) {
            data[key] = [];
          }
          if ($el.is(':checked')) {
            data[key].push($el.val());
          }
          return;
        }

        data[name] = $el.is(':checked') ? $el.val() : '';
        return;
      }

      if (type === 'radio') {
        if ($el.is(':checked')) {
          data[name] = $el.val();
        }
        return;
      }

      data[name] = $el.val();
    });

    return data;
  }

  function setStatus(text) {
    $('#hfb-preview-status').text(text || '');
  }

  var DESKTOP_CANVAS = 1100;

  function fitPreview() {
    var stage = document.getElementById('hfb-preview-stage');
    var canvas = document.getElementById('hfb-preview-canvas');

    if (!stage || !canvas) {
      return;
    }

    if (stage.getAttribute('data-viewport') !== 'desktop') {
      canvas.style.transform = '';
      stage.style.height = '';
      return;
    }

    var available = stage.clientWidth;
    var scale = available > 0 ? Math.min(1, available / DESKTOP_CANVAS) : 1;

    canvas.style.transform = scale < 1 ? 'scale(' + scale + ')' : '';
    stage.style.height = Math.ceil(canvas.offsetHeight * scale) + 'px';
  }

  function setPreviewMode(mode) {
    var $stage = $('#hfb-preview-stage');
    var resolved = mode === 'tablet' ? 'tablet' : mode === 'mobile' ? 'mobile' : 'desktop';

    $stage.attr('data-viewport', resolved);
    $('.hfb-preview__mode-btn')
      .removeClass('is-active')
      .filter('[data-preview-mode="' + (resolved === 'tablet' ? 'desktop' : resolved) + '"]')
      .addClass('is-active');

    if (resolved === 'tablet') {
      $('.hfb-preview__mode-btn').removeClass('is-active');
    }

    fitPreview();
  }

  function bootPreviewHeader() {
    var stage = document.getElementById('hfb-preview-stage');

    if (typeof window.qrmsHfbBoot === 'function' && stage) {
      window.qrmsHfbBoot(stage);
    }
  }

  function setPreviewPanelOpen(open) {
    var stage = document.getElementById('hfb-preview-stage');
    var $btn = $('.hfb-preview__open-panel');
    var i18n = typeof HFB_ADMIN !== 'undefined' && HFB_ADMIN.i18n ? HFB_ADMIN.i18n : {};

    keepPanelOpen = !!open;

    if (!stage) {
      return;
    }

    var wrap = stage.querySelector('.hfb-header-wrap');
    var toggle = wrap ? wrap.querySelector('.hfb-header__toggle') : null;
    var panel = wrap ? wrap.querySelector('.hfb-mobile-panel') : null;

    $(stage).toggleClass('is-panel-open', keepPanelOpen);

    if (keepPanelOpen) {
      setPreviewMode('mobile');
      if (panel) {
        panel.classList.add('is-open');
        panel.setAttribute('aria-hidden', 'false');
      }
      if (toggle) {
        toggle.setAttribute('aria-expanded', 'true');
      }
      $btn.text(i18n.closePanel || 'Önizlemede Kapat');
    } else if (panel) {
      panel.classList.remove('is-open');
      panel.setAttribute('aria-hidden', 'true');
      if (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
      }
      $btn.text(i18n.openPanel || 'Önizlemede Aç');
    }

    fitPreview();
  }

  function refreshPreview(force) {
    if (typeof HFB_ADMIN === 'undefined') {
      return;
    }

    var $form = $('#hfb-settings-form');
    var $stage = $('#hfb-preview-stage');

    if (!$form.length || !$stage.length) {
      return;
    }

    var data = serializeForm($form);
    var fingerprint = JSON.stringify(data);

    if (!force && fingerprint === lastPayload) {
      return;
    }
    lastPayload = fingerprint;

    var seq = ++requestSeq;
    setStatus(HFB_ADMIN.i18n && HFB_ADMIN.i18n.updating ? HFB_ADMIN.i18n.updating : 'Güncelleniyor…');

    $.post(HFB_ADMIN.ajaxUrl, {
      action: 'hfb_preview',
      nonce: HFB_ADMIN.nonce,
      type: 'all',
      data: data
    })
      .done(function (response) {
        if (seq !== requestSeq) {
          return;
        }

        if (!response || !response.success || !response.data) {
          setStatus(HFB_ADMIN.i18n && HFB_ADMIN.i18n.error ? HFB_ADMIN.i18n.error : 'Önizleme güncellenemedi.');
          return;
        }

        $stage.find('[data-preview="header"]').html(response.data.header || '');
        $stage.find('[data-preview="footer"]').html(response.data.footer || '');
        bootPreviewHeader();

        if (keepPanelOpen) {
          setPreviewPanelOpen(true);
        }

        fitPreview();
        setStatus('');
      })
      .fail(function () {
        if (seq !== requestSeq) {
          return;
        }
        setStatus(HFB_ADMIN.i18n && HFB_ADMIN.i18n.error ? HFB_ADMIN.i18n.error : 'Önizleme güncellenemedi.');
      });
  }

  function debouncedPreview() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function () {
      refreshPreview(false);
    }, DEBOUNCE_MS);
  }

  /* -------------------------------------------------------- Medya seçici */

  function initMediaUploader() {
    $(document).on('click', '.hfb-media-upload', function (e) {
      e.preventDefault();

      if (typeof wp === 'undefined' || !wp.media) {
        return;
      }

      var targetId = $(this).data('target');
      var $input = $('#' + targetId);
      var $field = $(this).closest('.hfb-media-field');

      // Alan yalnızca logo için değil (panel arka plan görseli de bunu
      // kullanır); başlık alanın kendi etiketinden okunur.
      var baslik = $.trim($field.find('.qrms-label').first().text()) || 'Görsel seç';

      var frame = wp.media({
        title: baslik,
        button: { text: 'Seç' },
        multiple: false
      });

      frame.on('select', function () {
        var attachment = frame.state().get('selection').first().toJSON();
        $input.val(attachment.id);
        $field.find('.hfb-media-preview').html('<img src="' + attachment.url + '" alt="" />');
        debouncedPreview();
      });

      frame.open();
    });

    $(document).on('click', '.hfb-media-remove', function (e) {
      e.preventDefault();
      var targetId = $(this).data('target');
      $('#' + targetId).val('0');
      $(this).closest('.hfb-media-field').find('.hfb-media-preview').empty();
      debouncedPreview();
    });
  }

  /* -------------------------------------------------------- Renk seçici */

  function initColorPickers() {
    var $pickers = $('.hfb-color-picker');

    if (!$pickers.length || typeof $.fn.wpColorPicker !== 'function') {
      return;
    }

    $pickers.wpColorPicker({
      change: function () {
        debouncedPreview();
      },
      clear: function () {
        debouncedPreview();
      }
    });
  }

  /* ------------------------------------------------- Logo yükseklik auto */

  function syncLogoHeightAuto($box) {
    var target = $box.data('hfb-height');
    var on = $box.is(':checked');
    var $row = $box.closest('.hfb-size-group').find('#' + target).closest('.hfb-logo-height-row');

    if (!$row.length) {
      $row = $('#' + target).closest('.hfb-logo-height-row');
    }

    $row.toggleClass('is-disabled', on);
    $row.find('input[type="range"]').prop('disabled', on);
  }

  function initLogoHeightAuto() {
    $('.hfb-logo-height-auto').each(function () {
      syncLogoHeightAuto($(this));
    });

    $(document).on('change', '.hfb-logo-height-auto', function () {
      syncLogoHeightAuto($(this));
    });
  }

  /* ------------------------------------------ Header tam genişlik anahtarı */

  /**
   * "Tam genişlik" açıkken maksimum genişlik kaydırıcısı anlamsızdır:
   * logo yüksekliği "otomatik oran" kutusuyla aynı desen — satır soluklaşır
   * ve alan devre dışı kalır (devre dışı alan serileştirmeye de girmez,
   * sunucu kayıtlı değeri korur).
   */
  function syncContentWidthToggle($box) {
    var target = $box.data('hfb-width');
    var on = $box.is(':checked');
    var $row = $('#' + target).closest('.hfb-content-width-row');

    $row.toggleClass('is-disabled', on);
    $row.find('input[type="range"]').prop('disabled', on);
  }

  function initContentWidthToggle() {
    $('.hfb-full-width-toggle').each(function () {
      syncContentWidthToggle($(this));
    });

    $(document).on('change', '.hfb-full-width-toggle', function () {
      syncContentWidthToggle($(this));
    });
  }

  /* --------------------------------------------- Hamburger blok sıralama */

  var blockIdCounter = 0;

  function nextBlockId() {
    var max = 0;

    $('#hfb-block-sortable .hfb-block-item').each(function () {
      var id = String($(this).data('block-id') || '');
      var match = id.match(/^blk_(\d+)$/);

      if (match) {
        max = Math.max(max, parseInt(match[1], 10));
      }
    });

    blockIdCounter = Math.max(blockIdCounter, max) + 1;
    return 'blk_' + blockIdCounter;
  }

  function writeBlockOrder() {
    var order = [];

    $('#hfb-block-sortable .hfb-block-item').each(function () {
      var id = $(this).data('block-id');
      if (id) {
        order.push(id);
      }
    });

    $('#hfb_hamburger_block_order').val(order.join(','));
  }

  function initBlockColorPickers($scope) {
    if (typeof $.fn.wpColorPicker !== 'function') {
      return;
    }

    ($scope || $(document)).find('.hfb-color-picker').each(function () {
      var $picker = $(this);

      if ($picker.closest('.wp-picker-container').length) {
        return;
      }

      $picker.wpColorPicker({
        change: function () {
          debouncedPreview();
        },
        clear: function () {
          debouncedPreview();
        }
      });
    });
  }

  function replaceBlockIds($item, newId) {
    $item.attr('data-block-id', newId);

    $item.find('[name]').each(function () {
      var name = $(this).attr('name');
      if (name) {
        $(this).attr('name', name.replace(/hfb_hamburger_blocks\[[^\]]+\]/, 'hfb_hamburger_blocks[' + newId + ']'));
      }
    });

    $item.find('[id]').each(function () {
      var id = $(this).attr('id');
      if (id && id.indexOf('__ID__') !== -1) {
        $(this).attr('id', id.replace(/__ID__/g, newId));
      } else if (id) {
        $(this).attr('id', id.replace(/_blk_\d+$/, '_' + newId).replace(/__ID__/g, newId));
      }
    });

    $item.find('[for]').each(function () {
      var htmlFor = $(this).attr('for');
      if (htmlFor && htmlFor.indexOf('__ID__') !== -1) {
        $(this).attr('for', htmlFor.replace(/__ID__/g, newId));
      }
    });
  }

  function addHamburgerBlock(type) {
    var $template = $('#hfb-block-tpl-' + type);

    if (!$template.length) {
      return;
    }

    var newId = nextBlockId();
    var $item = $($template.html().trim());

    replaceBlockIds($item, newId);
    $('#hfb-block-sortable').append($item);
    initBlockColorPickers($item);
    writeBlockOrder();
    debouncedPreview();
  }

  function initBlockSortable() {
    var $list = $('#hfb-block-sortable');

    if (!$list.length || typeof $.fn.sortable !== 'function') {
      return;
    }

    $list.sortable({
      handle: '.hfb-block-drag',
      placeholder: 'hfb-block-item',
      axis: 'y',
      update: function () {
        writeBlockOrder();
        debouncedPreview();
      }
    });

    $('#hfb-block-add-toggle').on('click', function (e) {
      e.preventDefault();
      var $menu = $('#hfb-block-add-menu');
      var open = !$menu.prop('hidden');
      $menu.prop('hidden', open);
      $(this).attr('aria-expanded', open ? 'false' : 'true');
    });

    $(document).on('click', '.hfb-block-add-type', function (e) {
      e.preventDefault();
      addHamburgerBlock($(this).data('block-type'));
      $('#hfb-block-add-menu').prop('hidden', true);
      $('#hfb-block-add-toggle').attr('aria-expanded', 'false');
    });

    $(document).on('click', function (e) {
      if ($(e.target).closest('.hfb-block-add').length) {
        return;
      }
      $('#hfb-block-add-menu').prop('hidden', true);
      $('#hfb-block-add-toggle').attr('aria-expanded', 'false');
    });

    $(document).on('click', '.hfb-block-delete', function (e) {
      e.preventDefault();
      $(this).closest('.hfb-block-item').remove();
      writeBlockOrder();
      debouncedPreview();
    });

    $(document).on('click', '.hfb-block-align .hfb-align-btn', function () {
      $(this).closest('.hfb-block-align').find('.hfb-align-btn').removeClass('is-selected');
      $(this).addClass('is-selected');
    });

    writeBlockOrder();
  }

  /* ------------------------------------------------------------- Başlat */

  $(function () {
    if (!$('.hfb-wrap').length) {
      return;
    }

    initTabs();
    initSteppers();
    initMediaUploader();
    initColorPickers();
    initLogoHeightAuto();
    initContentWidthToggle();
    initBlockSortable();
    bootPreviewHeader();

    $(document).on('input change', '#hfb-settings-form .hfb-preview-trigger', debouncedPreview);

    $(document).on('focus input', '[data-hfb-preview-bp] .hfb-preview-trigger', function () {
      var bp = $(this).closest('[data-hfb-preview-bp]').data('hfb-preview-bp');
      if (bp) {
        setPreviewMode(bp);
      }
    });

    $(document).on('click', '.hfb-preview__mode-btn', function (e) {
      e.preventDefault();
      setPreviewMode($(this).data('preview-mode'));
    });

    $(document).on('click', '.hfb-preview__open-panel', function (e) {
      e.preventDefault();
      setPreviewPanelOpen(!keepPanelOpen);
    });

    $(document).on('click', '.hfb-align-btn', function () {
      $(this).closest('.hfb-align-group').find('.hfb-align-btn').removeClass('is-selected');
      $(this).addClass('is-selected');
    });

    $(window).on('resize', fitPreview);
    fitPreview();

    $(document).on('click', '.hfb-preview__refresh', function (e) {
      e.preventDefault();
      refreshPreview(true);
    });
  });
})(jQuery);
