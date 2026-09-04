(function ($) {
	'use strict';

	var L = window.QRMS_MM || {};

	function kaydet(postId, maliyet, kaynak, recete, $row) {
		$.post(L.ajaxUrl, {
			action: 'qrms_mm_maliyet_kaydet',
			nonce: L.nonce,
			post_id: postId,
			maliyet: maliyet,
			kaynak: kaynak || 'manuel',
			recete: recete ? JSON.stringify(recete) : ''
		}).done(function (res) {
			if (!res.success) {
				alert(L.i18n.hata);
				return;
			}
			$row.find('.qrms-mm-cm').text(res.data.cm.toFixed(2));
			$row.find('.qrms-mm-marj').text(res.data.marj.toFixed(1));
		}).fail(function () {
			alert(L.i18n.hata);
		});
	}

	$('.qrms-mm-maliyet-input').on('change', function () {
		var $row = $(this).closest('.qrms-mm-maliyet-satir');
		if ($(this).prop('readonly')) {
			return;
		}
		kaydet($row.data('id'), parseFloat($(this).val()) || 0, 'manuel', null, $row);
	});

	$('#qrms-mm-tumunu-sec').on('change', function () {
		$('.qrms-mm-sec').prop('checked', $(this).prop('checked'));
	});

	$('#qrms-mm-toplu-uygula').on('click', function () {
		var ids = $('.qrms-mm-sec:checked').map(function () { return $(this).val(); }).get();
		var yuzde = parseFloat($('#qrms-mm-toplu-yuzde').val()) || 0;
		if (!ids.length) {
			return;
		}
		$.post(L.ajaxUrl, {
			action: 'qrms_mm_toplu_maliyet',
			nonce: L.nonce,
			ids: ids,
			yuzde: yuzde
		}).done(function () {
			location.reload();
		});
	});

	var malzemeler = [];
	try {
		malzemeler = JSON.parse($('#qrms-mm-malzemeler').text() || '[]');
	} catch (e) {
		malzemeler = [];
	}

	function receteSatirHtml(termId, miktar) {
		var opts = malzemeler.map(function (m) {
			return '<option value="' + m.id + '"' + (String(m.id) === String(termId) ? ' selected' : '') + '>' + m.ad + '</option>';
		}).join('');
		return '<div class="qrms-mm-recete-satir"><select class="qrms-mm-recete-term">' + opts + '</select>' +
			'<input type="number" class="qrms-mm-recete-miktar" step="0.01" min="0" value="' + (miktar || '') + '" placeholder="g/ml/adet" />' +
			'<button type="button" class="button qrms-mm-recete-sil">&times;</button></div>';
	}

	$('.qrms-mm-recete-btn').on('click', function () {
		var $satir = $(this).closest('.qrms-mm-maliyet-satir');
		var $recete = $satir.next('.qrms-mm-recete-satir');
		$recete.prop('hidden', !$recete.prop('hidden'));
		var $panel = $recete.find('.qrms-mm-recete-panel');
		if (!$panel.find('.qrms-mm-recete-satirlari').children().length) {
			var data = [];
			try { data = JSON.parse($panel.attr('data-recete') || '[]'); } catch (e) { data = []; }
			if (!data.length) {
				data = [{ term_id: malzemeler[0] ? malzemeler[0].id : 0, miktar: 0 }];
			}
			data.forEach(function (s) {
				$panel.find('.qrms-mm-recete-satirlari').append(receteSatirHtml(s.term_id, s.miktar));
			});
		}
	});

	$(document).on('click', '.qrms-mm-recete-ekle', function () {
		$(this).siblings('.qrms-mm-recete-satirlari').append(receteSatirHtml(malzemeler[0] ? malzemeler[0].id : 0, ''));
	});

	$(document).on('click', '.qrms-mm-recete-sil', function () {
		$(this).closest('.qrms-mm-recete-satir').remove();
	});

	$(document).on('click', '.qrms-mm-recete-kaydet', function () {
		var $panel = $(this).closest('.qrms-mm-recete-panel');
		var $row = $panel.closest('tr').prev('.qrms-mm-maliyet-satir');
		var recete = [];
		$panel.find('.qrms-mm-recete-satir').each(function () {
			recete.push({
				term_id: parseInt($(this).find('.qrms-mm-recete-term').val(), 10),
				miktar: parseFloat($(this).find('.qrms-mm-recete-miktar').val()) || 0
			});
		});
		var $input = $row.find('.qrms-mm-maliyet-input');
		$input.prop('readonly', true);
		kaydet($row.data('id'), 0, 'recete', recete, $row);
	});

	$('#qrms-mm-malzeme-form').on('submit', function (e) {
		e.preventDefault();
		var fiyatlar = {};
		$(this).find('[name^="fiyatlar"]').each(function () {
			var m = $(this).attr('name').match(/fiyatlar\[(\d+)\]\[(\w+)\]/);
			if (!m) { return; }
			if (!fiyatlar[m[1]]) { fiyatlar[m[1]] = {}; }
			fiyatlar[m[1]][m[2]] = $(this).val();
		});
		$.post(L.ajaxUrl, {
			action: 'qrms_mm_malzeme_kaydet',
			nonce: L.nonce,
			fiyatlar: JSON.stringify(fiyatlar)
		}).done(function () {
			alert(L.i18n.kaydedildi);
		});
	});

	$('#qrms-mm-ayarlar-form').on('submit', function (e) {
		e.preventDefault();
		var $f = $(this);
		$.post(L.ajaxUrl, {
			action: 'qrms_mm_ayarlar_kaydet',
			nonce: L.nonce,
			populerlik_esigi: $f.find('[name=populerlik_esigi]').val(),
			fire_yuzdesi: $f.find('[name=fire_yuzdesi]').val(),
			kdv_dahil: $f.find('[name=kdv_dahil]').prop('checked') ? 1 : 0,
			varsayilan_aralik: $f.find('[name=varsayilan_aralik]').val()
		}).done(function () {
			alert(L.i18n.kaydedildi);
		});
	});
})(jQuery);
