(function ($) {
	'use strict';

	var preview = document.getElementById('qrms-login-onizleme');
	if (!preview) {
		return;
	}

	var kart = preview.querySelector('.qrms-login-onizleme-kart');

	function setVar(name, value) {
		preview.style.setProperty(name, value);
	}

	function sync() {
		$('.qrms-login-field').each(function () {
			var $f = $(this);
			var v = $f.val();
			var cssVar = $f.data('var');
			var suffix = $f.data('suffix') || '';
			var textSel = $f.data('text');

			if (cssVar) {
				setVar(cssVar, v + suffix);
			}
			if (textSel && v) {
				$(textSel).text(v);
			}
			if ($f.data('shadow')) {
				kart.style.boxShadow = $f.prop('checked') ? '0 8px 32px rgba(0,0,0,.15)' : 'none';
			}
			if ($f.data('glass')) {
				kart.style.background = $f.prop('checked') ? 'rgba(255,255,255,.85)' : '#fff';
				kart.style.backdropFilter = $f.prop('checked') ? 'blur(12px)' : 'none';
			}
		});

		var tip = $('[data-bg-tip]').val();
		if (tip === 'gradyan') {
			setVar('--qrms-login-bg', $('#arkaplan_gradyan').val() || 'linear-gradient(135deg,#1e1e2e,#2d2d44)');
		} else if (tip === 'gorsel') {
			var url = $('#arkaplan_gorsel').val();
			if (url) {
				preview.style.backgroundImage = 'url(' + url + ')';
				preview.style.backgroundSize = 'cover';
			}
		}
	}

	$('.qrms-login-field').on('input change', sync);

	if ($.fn.wpColorPicker) {
		$('.qrms-color-picker').wpColorPicker({ change: sync });
	}

	$('.qrms-login-media').on('click', function (e) {
		e.preventDefault();
		if (!wp || !wp.media) {
			return;
		}
		var frame = wp.media({ title: 'Logo seç', button: { text: 'Seç' }, multiple: false });
		frame.on('select', function () {
			var att = frame.state().get('selection').first().toJSON();
			$('#logo_url').val(att.url).trigger('input');
		});
		frame.open();
	});

	sync();
})(jQuery);
