/**
 * Header Footer Builder — yönetim ekranı.
 *
 * İki iş yapar: sekme geçişi (sayfa yenilemeden) ve canlı önizleme.
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

    // Sekme, sayfa yenilenmeden değişir; adres çubuğu yine de güncellenir ki
    // "Kaydet" sonrası aynı sekmeye dönülsün ve bağlantı paylaşılabilsin.
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

  /**
   * Masaüstü önizlemesini panele sığdırır.
   *
   * Tuval 1100px basılır (ön yüz kırılımları kap genişliğine bağlı, bu
   * yüzden dar bir panelde tuval küçültülmeden masaüstü yerleşimi hiç
   * görünmezdi). `scale` yalnızca görseldir; sahnenin yüksekliği de aynı
   * oranla kısaltılır ki altında boşluk kalmasın.
   */
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
          return; // Gecikmiş yanıt: daha tazesi zaten basıldı.
        }

        if (!response || !response.success || !response.data) {
          setStatus(HFB_ADMIN.i18n && HFB_ADMIN.i18n.error ? HFB_ADMIN.i18n.error : 'Önizleme güncellenemedi.');
          return;
        }

        $stage.find('[data-preview="header"]').html(response.data.header || '');
        $stage.find('[data-preview="footer"]').html(response.data.footer || '');
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
      var frame = wp.media({
        title: 'Logo seç',
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

  /* ------------------------------------------------------------- Başlat */

  $(function () {
    if (!$('.hfb-wrap').length) {
      return;
    }

    initTabs();
    initMediaUploader();

    // Olaylar `document` üzerinde dinlenir: gizli sekmelerdeki alanlar ve
    // sonradan basılan düğümler de ilk andan itibaren önizlemeyi tetikler.
    $(document).on('input change', '#hfb-settings-form .hfb-preview-trigger', debouncedPreview);

    $(document).on('change', '#hfb-preview-viewport', function () {
      $('#hfb-preview-stage').attr('data-viewport', $(this).val());
      fitPreview();
    });

    $(window).on('resize', fitPreview);
    fitPreview();

    $(document).on('click', '.hfb-preview__refresh', function (e) {
      e.preventDefault();
      refreshPreview(true);
    });
  });
})(jQuery);
