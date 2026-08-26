(function ($) {
  'use strict';

  var debounceTimer = null;

  function collectHeaderData($form) {
    return {
      variant: $form.find('[name="hfb_header_variant"]').val(),
      logo_width: $form.find('[name="hfb_header_logo_width"]').val(),
      logo_alignment: $form.find('[name="hfb_header_logo_alignment"]').val(),
      bg_color: $form.find('[name="hfb_header_bg_color"]').val(),
      text_color: $form.find('[name="hfb_header_text_color"]').val(),
      border_color: $form.find('[name="hfb_header_border_color"]').val(),
      hamburger_color: $form.find('[name="hfb_hamburger_color"]').val(),
      sticky: $form.find('[name="hfb_header_sticky"]').is(':checked') ? 1 : 0,
      mobile_panel_style: $form.find('[name="hfb_mobile_panel_style"]').val(),
      mobile_panel_bg: $form.find('[name="hfb_mobile_panel_bg"]').val(),
      mobile_panel_bg_opacity: $form.find('[name="hfb_mobile_panel_bg_opacity"]').val(),
      mobile_panel_font: $form.find('[name="hfb_mobile_panel_font"]').val(),
      mobile_panel_text_color: $form.find('[name="hfb_mobile_panel_text_color"]').val(),
      mobile_panel_text_size: $form.find('[name="hfb_mobile_panel_text_size"]').val(),
      mobile_close_icon: $form.find('[name="hfb_mobile_close_icon"]').val(),
      mobile_close_icon_color: $form.find('[name="hfb_mobile_close_icon_color"]').val(),
      mobile_close_icon_size: $form.find('[name="hfb_mobile_close_icon_size"]').val()
    };
  }

  function collectFooterData($form) {
    return {
      variant: $form.find('[name="hfb_footer_variant"]').val(),
      description: $form.find('[name="hfb_footer_description"]').val(),
      phone: $form.find('[name="hfb_footer_phone"]').val(),
      email: $form.find('[name="hfb_footer_email"]').val(),
      copyright: $form.find('[name="hfb_footer_copyright"]').val()
    };
  }

  function applyHeaderCssVars($wrap, data) {
    var style = [
      '--hfb-bg:' + data.bg_color,
      '--hfb-text:' + data.text_color,
      '--hfb-border:' + data.border_color,
      '--hfb-logo-width:' + data.logo_width + 'px',
      '--hfb-hamburger:' + data.hamburger_color,
      '--hfb-panel-bg:' + data.mobile_panel_bg,
      '--hfb-panel-bg-alpha:' + (parseInt(data.mobile_panel_bg_opacity, 10) / 100),
      '--hfb-panel-text:' + data.mobile_panel_text_color,
      '--hfb-panel-font-size:' + data.mobile_panel_text_size + 'px',
      '--hfb-close-color:' + data.mobile_close_icon_color,
      '--hfb-close-size:' + data.mobile_close_icon_size + 'px'
    ].join(';');

    $wrap.find('.hfb-header-wrap').attr('style', style);

    var $header = $wrap.find('.hfb-header');
    $header.removeClass('hfb-header--minimal-sticky hfb-header--glass-bento hfb-header--kinetic-bold');
    $header.addClass('hfb-header--' + data.variant);

    if (parseInt(data.sticky, 10)) {
      $header.addClass('hfb-header--sticky');
    } else {
      $header.removeClass('hfb-header--sticky');
    }

    var $panel = $wrap.find('.hfb-mobile-panel');
    $panel.removeClass('hfb-mobile-panel--slide hfb-mobile-panel--fullscreen');
    $panel.addClass('hfb-mobile-panel--' + data.mobile_panel_style);
  }

  function refreshPreview($form) {
    if (typeof HFB_ADMIN === 'undefined') {
      return;
    }

    var tab = $form.data('hfb-tab') || 'header';
    var type = tab === 'footer' ? 'footer' : 'header';
    var data = type === 'footer' ? collectFooterData($form) : collectHeaderData($form);
    var $target = $('#hfb-preview-inline');

    if (!$target.length) {
      return;
    }

    if (type === 'header') {
      applyHeaderCssVars($target, data);
    }

    $.post(HFB_ADMIN.ajaxUrl, {
      action: 'hfb_preview',
      nonce: HFB_ADMIN.nonce,
      type: type,
      data: data
    }).done(function (response) {
      if (response && response.success && response.data && response.data.html) {
        $target.html(response.data.html);
        if (type === 'header') {
          applyHeaderCssVars($target, data);
        }
      }
    });
  }

  function debouncedPreview($form) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function () {
      refreshPreview($form);
    }, 280);
  }

  function initMediaUploader() {
    $(document).on('click', '.hfb-media-upload', function (e) {
      e.preventDefault();
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
        $input.val(attachment.id).trigger('change');
        $field.find('.hfb-media-preview').html('<img src="' + attachment.url + '" alt="" />');
        debouncedPreview($('.hfb-form'));
      });

      frame.open();
    });

    $(document).on('click', '.hfb-media-remove', function (e) {
      e.preventDefault();
      var targetId = $(this).data('target');
      $('#' + targetId).val('0').trigger('change');
      $(this).closest('.hfb-media-field').find('.hfb-media-preview').empty();
      debouncedPreview($('.hfb-form'));
    });
  }

  $(function () {
    $('.hfb-color-picker').wpColorPicker({
      change: function () {
        debouncedPreview($('.hfb-form'));
      },
      clear: function () {
        debouncedPreview($('.hfb-form'));
      }
    });

    initMediaUploader();

    $('.hfb-form').on('input change', '.hfb-preview-trigger', function () {
      debouncedPreview($(this).closest('.hfb-form'));
    });

    $('#hfb-preview-viewport').on('change', function () {
      $('#hfb-preview-full').attr('data-viewport', $(this).val());
    });

    $('.hfb-preview-refresh').on('click', function () {
      location.reload();
    });
  });
})(jQuery);
