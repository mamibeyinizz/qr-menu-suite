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
   * Her sekme kendi adımlarını taşır. Toplam, DOM'daki `.hfb-step`
   * kartlarından okunur (footer 5, header 4, hamburger 4, dil 1);
   * sabit bir "4/4" yoktur. Kartlar display:none ile gizlenir, submit
   * tüm adımların verisini gönderir — vitrin initVitrinStepper ile
   * aynı sözleşme, ayrı "adım kaydet" yoktur.
   *
   * Son adımda "Devam Et" gizlenir; Kaydet formun altındadır.
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

      function sinirla(adimNo) {
        var n = parseInt(adimNo, 10);
        if (isNaN(n)) {
          return 1;
        }
        return Math.min(Math.max(n, 1), toplam);
      }

      function goster(adimNo) {
        mevcut = sinirla(adimNo);

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

  /**
   * PHP tarzı alan adını yola çevirir.
   *
   *   "hfb_hamburger_blocks[blk_1][enabled]" -> ['hfb_hamburger_blocks','blk_1','enabled']
   *   "hfb_header_social_media_active[]"     -> ['hfb_header_social_media_active','']
   *
   * Köşeli parantezli adlar DÜZ bir anahtar olarak gönderilirse istek
   * `data[hfb_hamburger_blocks[blk_1][enabled]]` hâline gelir; PHP bunu
   * eşleşen parantezlere göre değil, İLK kapanan paranteze göre ayrıştırır ve
   * anahtar `hfb_hamburger_blocks[blk_1` diye bozulur. Sunucu o yüzden hiç blok
   * göremez, kayıtlı bloklara geri düşer — panel/blok değişiklikleri
   * önizlemede görünmezdi. Yükü baştan iç içe kurunca jQuery
   * `data[hfb_hamburger_blocks][blk_1][enabled]` üretir ve PHP doğru çözer.
   *
   * @param {string} name Alan adı.
   * @return {string[]} Yol parçaları.
   */
  function nameToPath(name) {
    var head = name.indexOf('[');

    if (head === -1) {
      return [name];
    }

    var path = [name.slice(0, head)];
    var re = /\[([^\[\]]*)\]/g;
    var match;

    while ((match = re.exec(name)) !== null) {
      path.push(match[1]);
    }

    return path;
  }

  /**
   * Yol boyunca ilerleyip değeri yazar. Boş parça ("[]") diziye ekler.
   *
   * @param {Object} data  Hedef nesne.
   * @param {string[]} path Yol.
   * @param {*} value      Değer.
   * @param {boolean} push Yalnızca kabı hazırla, değeri ekleme.
   * @return {void}
   */
  function assignPath(data, path, value, push) {
    var node = data;

    for (var i = 0; i < path.length - 1; i++) {
      var key = path[i];
      var nextIsList = path[i + 1] === '';

      if (typeof node[key] !== 'object' || node[key] === null) {
        node[key] = nextIsList ? [] : {};
      }

      node = node[key];
    }

    var last = path[path.length - 1];

    if (last === '') {
      // "[]" — kap her hâlükârda oluşur (hiçbiri işaretli değilse boş dizi).
      if (!push) {
        node.push(value);
      }
      return;
    }

    node[last] = value;
  }

  /**
   * Formu, kayıt POST'uyla AYNI yapıda bir nesneye çevirir.
   *
   * Devre dışı alanlar atlanır (tarayıcı da göndermez); işaretsiz onay kutusu
   * boş dize olarak gider, böylece sunucu "kutu kapalı" ile "alan hiç yok"
   * arasında ayrım yapabilir.
   *
   * @param {jQuery} $form Form.
   * @return {Object}
   */
  function serializeForm($form) {
    var data = {};

    $form.find('input, select, textarea').each(function () {
      var $el = $(this);
      var name = $el.attr('name');

      if (!name || $el.is(':disabled')) {
        return;
      }

      var path = nameToPath(name);
      var type = ($el.attr('type') || '').toLowerCase();

      if (type === 'checkbox') {
        if (path[path.length - 1] === '') {
          // Kabı her zaman kur; değeri yalnızca işaretliyse ekle.
          assignPath(data, path, $el.val(), !$el.is(':checked'));
          return;
        }

        assignPath(data, path, $el.is(':checked') ? $el.val() : '');
        return;
      }

      if (type === 'radio') {
        if ($el.is(':checked')) {
          assignPath(data, path, $el.val());
        }
        return;
      }

      assignPath(data, path, $el.val());
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

      var $field = $(this).closest('.hfb-media-field');
      // Alan adı köşeli parantez taşıyabilir (footer yerleşim blokları);
      // id tabanlı seçici yerine DOM'daki tek gizli girdi hedeflenir.
      var $input = $field.find('.hfb-media-id').first();

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
      var $field = $(this).closest('.hfb-media-field');
      $field.find('.hfb-media-id').first().val('0');
      $field.find('.hfb-media-preview').empty();
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

  /**
   * "Yeni Blok Ekle" listesi açık mı?
   *
   * @return {boolean}
   */
  function isBlockMenuOpen() {
    var menu = document.getElementById('hfb-block-add-menu');

    return !!menu && !menu.hidden;
  }

  /**
   * Listeyi açar/kapatır.
   *
   * Liste mutlak konumludur ve sayfa akışına girmez; açılıp kapanması
   * altındaki adım gezinme şeridini oynatmaz, scroll konumu değişmez.
   *
   * @param {boolean} open Açık mı.
   * @return {void}
   */
  function setBlockMenuOpen(open) {
    var menu = document.getElementById('hfb-block-add-menu');
    var toggle = document.getElementById('hfb-block-add-toggle');

    if (!menu) {
      return;
    }

    menu.hidden = !open;

    if (toggle) {
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
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

    $(document).on('click', '#hfb-block-add-toggle', function (e) {
      e.preventDefault();
      setBlockMenuOpen(!isBlockMenuOpen());
    });

    $(document).on('click', '.hfb-block-add-type', function (e) {
      e.preventDefault();
      addHamburgerBlock($(this).data('block-type'));
      setBlockMenuOpen(false);
    });

    // Dışarı tıklama kapatır. Tetikleyici düğme de sarmalayıcının içindedir;
    // kendi işleyicisi zaten çalıştığı için burada elenir, aksi hâlde açılan
    // liste aynı tıklamada kapanırdı.
    $(document).on('click', function (e) {
      if ($(e.target).closest('.hfb-block-add').length) {
        return;
      }
      setBlockMenuOpen(false);
    });

    $(document).on('keydown', function (e) {
      if (e.key === 'Escape' && isBlockMenuOpen()) {
        setBlockMenuOpen(false);
        // Odağı düğmeye geri verirken sayfa kaydırılmaz.
        var toggle = document.getElementById('hfb-block-add-toggle');
        if (toggle) {
          toggle.focus({ preventScroll: true });
        }
      }
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

  /* ------------------------------------------------- Footer yerleşimi */

  /**
   * Bir kapsayıcı içindeki `data-*-id` değerlerinden bir sonraki sıra
   * numarasını üretir. Kimlikler yalnızca kendi kapsayıcıları içinde
   * (satır listesi / bir satırın sütunları / bir sütunun blokları)
   * biricik olmak zorundadır — tam form alanı adı zaten satır+sütun
   * yolunu taşıdığı için çakışma olmaz.
   *
   * @param {string} attr   data özniteliği (data-row-id | data-col-id | data-block-id).
   * @param {string} prefix row | col | blk.
   * @param {jQuery} $items Aday öğeler.
   * @return {string}
   */
  function nextFooterId(attr, prefix, $items) {
    var max = 0;

    $items.each(function () {
      var v = String($(this).attr(attr) || '');
      var m = v.match(new RegExp('^' + prefix + '_(\\d+)$'));
      if (m) {
        max = Math.max(max, parseInt(m[1], 10));
      }
    });

    return prefix + '_' + (max + 1);
  }

  /**
   * Şablondan klonlanan bir satır/sütun/blok içindeki `__ROW__` / `__COL__`
   * / `__BLK__` yer tutucularını gerçek kimliklerle değiştirir — form alanı
   * adları, id/for eşleşmeleri ve data-*-id öznitelikleri dahil.
   *
   * @param {jQuery} $root  Klonlanan kök öğe.
   * @param {Object} tokens {'__ROW__': 'row_3', ...} eşlemesi.
   * @return {void}
   */
  function replaceFooterTokens($root, tokens) {
    function apply(str) {
      Object.keys(tokens).forEach(function (token) {
        str = str.split(token).join(tokens[token]);
      });
      return str;
    }

    ['data-row-id', 'data-col-id', 'data-block-id'].forEach(function (attr) {
      if ($root.is('[' + attr + ']')) {
        $root.attr(attr, apply($root.attr(attr)));
      }
      $root.find('[' + attr + ']').each(function () {
        $(this).attr(attr, apply($(this).attr(attr)));
      });
    });

    ['name', 'id', 'for', 'data-target'].forEach(function (attr) {
      $root.find('[' + attr + ']').each(function () {
        $(this).attr(attr, apply($(this).attr(attr)));
      });
    });
  }

  function initFooterRowSortable() {
    var $list = $('#hfb-fl-rows');

    if (!$list.length || typeof $.fn.sortable !== 'function') {
      return;
    }

    $list.sortable({
      handle: '.hfb-fl-row-drag',
      placeholder: 'hfb-fl-row hfb-fl-row--placeholder',
      axis: 'y',
      update: function () {
        debouncedPreview();
      }
    });
  }

  function initFooterColSortable($list) {
    if (!$list || !$list.length || typeof $.fn.sortable !== 'function') {
      return;
    }

    $list.sortable({
      handle: '.hfb-fl-col-drag',
      placeholder: 'hfb-fl-col hfb-fl-col--placeholder',
      axis: 'x',
      update: function () {
        debouncedPreview();
      }
    });
  }

  function initFooterBlockSortable($list) {
    if (!$list || !$list.length || typeof $.fn.sortable !== 'function') {
      return;
    }

    $list.sortable({
      handle: '.hfb-block-drag',
      placeholder: 'hfb-fl-block hfb-fl-block--placeholder',
      axis: 'y',
      update: function () {
        debouncedPreview();
      }
    });
  }

  function addFooterRow() {
    var $tpl = $('#hfb-fl-tpl-row');
    var $rows = $('#hfb-fl-rows');

    if (!$tpl.length) {
      return;
    }

    var rowId = nextFooterId('data-row-id', 'row', $rows.find('> .hfb-fl-row'));
    var $row = $($tpl.html().trim());

    replaceFooterTokens($row, { __ROW__: rowId, __COL__: 'col_1' });
    $rows.append($row);
    initFooterColSortable($row.find('> .hfb-fl-cols'));
    debouncedPreview();
  }

  function addFooterCol(rowId) {
    var $row = $('.hfb-fl-row[data-row-id="' + rowId + '"]');
    var $cols = $row.find('> .hfb-fl-cols');
    var $tpl = $('#hfb-fl-tpl-col');

    if (!$tpl.length || $cols.find('> .hfb-fl-col').length >= 4) {
      return;
    }

    var colId = nextFooterId('data-col-id', 'col', $cols.find('> .hfb-fl-col'));
    var $col = $($tpl.html().trim());

    replaceFooterTokens($col, { __ROW__: rowId, __COL__: colId });
    $cols.append($col);
    initFooterBlockSortable($col.find('> .hfb-fl-blocks'));
    debouncedPreview();
  }

  function addFooterBlock(rowId, colId, type) {
    var $tpl = $('#hfb-fl-tpl-block-' + type);

    if (!$tpl.length) {
      return;
    }

    var $col = $('.hfb-fl-col[data-row-id="' + rowId + '"][data-col-id="' + colId + '"]');
    var $blocks = $col.find('> .hfb-fl-blocks');
    var blockId = nextFooterId('data-block-id', 'blk', $blocks.find('> .hfb-fl-block'));
    var $block = $($tpl.html().trim());

    replaceFooterTokens($block, { __ROW__: rowId, __COL__: colId, __BLK__: blockId });
    $blocks.append($block);
    initBlockColorPickers($block);
    debouncedPreview();
  }

  function footerConfirmIfHasBlocks($container, message) {
    if ($container.find('.hfb-fl-block').length > 0) {
      return window.confirm(message);
    }
    return true;
  }

  function initFooterLayoutBuilder() {
    var $wrap = $('#hfb-footer-layout');

    if (!$wrap.length) {
      return;
    }

    initFooterRowSortable();
    $wrap.find('.hfb-fl-cols').each(function () {
      initFooterColSortable($(this));
    });
    $wrap.find('.hfb-fl-blocks').each(function () {
      initFooterBlockSortable($(this));
    });

    $(document).on('click', '#hfb-fl-add-row', function (e) {
      e.preventDefault();
      addFooterRow();
    });

    $(document).on('click', '.hfb-fl-add-col', function (e) {
      e.preventDefault();
      addFooterCol(String($(this).data('row-id')));
    });

    $(document).on('click', '.hfb-fl-block-add-toggle', function (e) {
      e.preventDefault();
      var $menu = $(this).siblings('.hfb-fl-block-add__menu');
      var willOpen = !!$menu.prop('hidden');

      $('.hfb-fl-block-add__menu').prop('hidden', true);
      $('.hfb-fl-block-add-toggle').attr('aria-expanded', 'false');

      if (willOpen) {
        $menu.prop('hidden', false);
        $(this).attr('aria-expanded', 'true');
      }
    });

    $(document).on('click', function (e) {
      if (!$(e.target).closest('.hfb-fl-block-add').length) {
        $('.hfb-fl-block-add__menu').prop('hidden', true);
        $('.hfb-fl-block-add-toggle').attr('aria-expanded', 'false');
      }
    });

    $(document).on('click', '.hfb-fl-block-add-type', function (e) {
      e.preventDefault();
      var $col = $(this).closest('.hfb-fl-col');
      addFooterBlock(String($col.data('row-id')), String($col.data('col-id')), String($(this).data('block-type')));
      $(this).closest('.hfb-fl-block-add__menu').prop('hidden', true);
    });

    $(document).on('click', '.hfb-fl-row-delete', function (e) {
      e.preventDefault();
      var $row = $(this).closest('.hfb-fl-row');
      if (footerConfirmIfHasBlocks($row, 'Bu satırdaki tüm sütunlar ve bloklar silinecek. Emin misiniz?')) {
        $row.remove();
        debouncedPreview();
      }
    });

    $(document).on('click', '.hfb-fl-col-delete', function (e) {
      e.preventDefault();
      var $col = $(this).closest('.hfb-fl-col');
      var $siblingCols = $col.closest('.hfb-fl-cols').find('> .hfb-fl-col');

      if ($siblingCols.length <= 1) {
        window.alert('Bir satırda en az bir sütun olmalı. Bu satırı tamamen kaldırmak için satırın çöp kutusu düğmesini kullanın.');
        return;
      }

      if (footerConfirmIfHasBlocks($col, 'Bu sütundaki bloklar silinecek. Emin misiniz?')) {
        $col.remove();
        debouncedPreview();
      }
    });

    $(document).on('click', '.hfb-fl-block-delete', function (e) {
      e.preventDefault();
      $(this).closest('.hfb-fl-block').remove();
      debouncedPreview();
    });

    $(document).on('click', '.hfb-fl-row-up', function (e) {
      e.preventDefault();
      var $row = $(this).closest('.hfb-fl-row');
      var $prev = $row.prev('.hfb-fl-row');
      if ($prev.length) {
        $row.insertBefore($prev);
        debouncedPreview();
      }
    });

    $(document).on('click', '.hfb-fl-row-down', function (e) {
      e.preventDefault();
      var $row = $(this).closest('.hfb-fl-row');
      var $next = $row.next('.hfb-fl-row');
      if ($next.length) {
        $row.insertAfter($next);
        debouncedPreview();
      }
    });

    $(document).on('click', '.hfb-fl-col-left', function (e) {
      e.preventDefault();
      var $col = $(this).closest('.hfb-fl-col');
      var $prev = $col.prev('.hfb-fl-col');
      if ($prev.length) {
        $col.insertBefore($prev);
        debouncedPreview();
      }
    });

    $(document).on('click', '.hfb-fl-col-right', function (e) {
      e.preventDefault();
      var $col = $(this).closest('.hfb-fl-col');
      var $next = $col.next('.hfb-fl-col');
      if ($next.length) {
        $col.insertAfter($next);
        debouncedPreview();
      }
    });

    $(document).on('click', '.hfb-fl-block-up', function (e) {
      e.preventDefault();
      var $block = $(this).closest('.hfb-fl-block');
      var $prev = $block.prev('.hfb-fl-block');
      if ($prev.length) {
        $block.insertBefore($prev);
        debouncedPreview();
      }
    });

    $(document).on('click', '.hfb-fl-block-down', function (e) {
      e.preventDefault();
      var $block = $(this).closest('.hfb-fl-block');
      var $next = $block.next('.hfb-fl-block');
      if ($next.length) {
        $block.insertAfter($next);
        debouncedPreview();
      }
    });

    // Sütunlar arası taşıma: blok, yeni sütun bağlamında biricik kalması
    // için TAZE bir blok kimliği alır (eskisini korumak, hedef sütunda
    // aynı kimlikli başka bir blok varsa POST'ta sessiz çakışmaya yol açar).
    $(document).on('click', '.hfb-fl-block-left, .hfb-fl-block-right', function (e) {
      e.preventDefault();

      var $btn = $(this);
      var $block = $btn.closest('.hfb-fl-block');
      var $col = $block.closest('.hfb-fl-col');
      var $targetCol = $btn.hasClass('hfb-fl-block-left') ? $col.prev('.hfb-fl-col') : $col.next('.hfb-fl-col');

      if (!$targetCol.length) {
        return;
      }

      var oldRow = String($col.data('row-id'));
      var oldCol = String($col.data('col-id'));
      var oldBlk = String($block.data('block-id'));
      var newRow = String($targetCol.data('row-id'));
      var newCol = String($targetCol.data('col-id'));
      var $targetBlocks = $targetCol.find('> .hfb-fl-blocks');
      var newBlk = nextFooterId('data-block-id', 'blk', $targetBlocks.find('> .hfb-fl-block'));

      var oldBase = 'hfb_footer_layout[rows][' + oldRow + '][cols][' + oldCol + '][blocks][' + oldBlk + ']';
      var newBase = 'hfb_footer_layout[rows][' + newRow + '][cols][' + newCol + '][blocks][' + newBlk + ']';

      $block.find('[name]').each(function () {
        var name = $(this).attr('name');
        if (name && name.indexOf(oldBase) === 0) {
          $(this).attr('name', newBase + name.slice(oldBase.length));
        }
      });

      $block.attr('data-block-id', newBlk);
      $targetBlocks.append($block);
      debouncedPreview();
    });
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
    initFooterLayoutBuilder();
    bootPreviewHeader();

    /*
     * Önizleme senkronizasyonu TEK bir delege dinleyicidir ve formun
     * TAMAMINI kapsar: `.hfb-preview-trigger` sınıfı taşıyıp taşımadığına
     * bakılmaksızın her input/select/textarea. Sınıfa bağlı eski kurulumda
     * sonradan eklenen bir alana sınıfı koymayı unutmak, o alanı sessizce
     * önizleme dışında bırakıyordu; delegasyon formun kökünde durduğu için
     * JS ile eklenen blok alanları da kendiliğinden kapsanır.
     *
     * `input` her tuş vuruşunda ateşlenir; istek debounce edilir
     * (DEBOUNCE_MS) ve aynı yük iki kez gönderilmez (bkz. lastPayload).
     */
    $(document).on('input change', '#hfb-settings-form', function (e) {
      if (!$(e.target).is('input, select, textarea')) {
        return;
      }

      debouncedPreview();
    });

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
