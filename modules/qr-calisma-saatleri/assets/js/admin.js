/**
 * QR Çalışma Saatleri — yönetim ekranı.
 *
 * İki iş yapar:
 *   1) "Kapalı" kutusu işaretlenince o günün saat alanlarını kilitler.
 *   2) Canlı önizlemeyi besler: saat, kapalı gün ve renk değişiklikleri
 *      kaydetmeden listeye yansır.
 *
 * Önizleme kısa kodun GERÇEK çıktısıdır (PHP tarafında basılır) ve gerçek
 * frontend stylesheet'ini kullanır; buradaki kod yalnızca hazır DOM'u
 * günceller — ikinci bir şablon yoktur.
 */
(function ($) {
	'use strict';

	var L = window.QRMS_CS || {};

	/**
	 * Tek günün metni.
	 *
	 * PHP'deki qrms_cs_format_day() ile AYNI dallanma: kapalıysa "Kapalı",
	 * açılış ile kapanış eşitse "24 saat açık", değilse "açılış – kapanış".
	 * Metinlerin kendisi PHP'den gelir (wp_localize_script), burada
	 * yazılmaz. Kural paritesi testle korunuyor.
	 */
	function formatDay(closed, open, close) {
		if (closed) {
			return L.kapali || 'Kapalı';
		}

		if (open === close) {
			return L.yirmiDort || '24 saat açık';
		}

		return (L.aralik || '%1$s – %2$s').replace('%1$s', open).replace('%2$s', close);
	}

	function previewItem(day) {
		return document.querySelector('#qrms-cs-preview .qrms-cs-item[data-day="' + day + '"]');
	}

	function previewList() {
		return document.querySelector('#qrms-cs-preview .qrms-cs-list');
	}

	/* ---------------- Gün kartları ---------------- */

	function bindDay(card) {
		var day = card.getAttribute('data-day');
		var closed = card.querySelector('.qrms-cs-closed');
		var open = card.querySelector('.qrms-cs-open');
		var close = card.querySelector('.qrms-cs-close');
		var times = card.querySelectorAll('input[type="time"]');

		if (!closed) {
			return;
		}

		function sync() {
			var on = closed.checked;
			var i;

			card.classList.toggle('is-closed', on);
			for (i = 0; i < times.length; i++) {
				times[i].disabled = on;
			}

			var item = previewItem(day);
			if (!item) {
				return;
			}

			item.classList.toggle('is-closed', on);

			var hours = item.querySelector('.qrms-cs-item-hours');
			if (hours) {
				hours.textContent = formatDay(on, open ? open.value : '', close ? close.value : '');
			}
		}

		closed.addEventListener('change', sync);
		if (open) {
			open.addEventListener('input', sync);
			open.addEventListener('change', sync);
		}
		if (close) {
			close.addEventListener('input', sync);
			close.addEventListener('change', sync);
		}

		sync();
	}

	/* ---------------- Renkler ---------------- */

	function applyColor(input, value) {
		var list = previewList();
		var name = input.getAttribute('data-css-var');

		if (!list || !name) {
			return;
		}

		// Boş değer "temadan devral" demektir: değişken basılmaz, CSS'teki
		// geri düşüş devreye girer. PHP tarafındaki kuralın aynısı.
		if (value) {
			list.style.setProperty(name, value);
		} else {
			list.style.removeProperty(name);
		}
	}

	function bindColors() {
		var $pickers = $('.qrms-cs-color-picker');

		if (!$pickers.length) {
			return;
		}

		if (typeof $.fn.wpColorPicker !== 'function') {
			// Renk seçici yüklenmediyse alan düz metin kutusu olarak çalışır.
			$pickers.on('input change', function () {
				applyColor(this, this.value);
			});
			return;
		}

		$pickers.wpColorPicker({
			change: function (event, ui) {
				applyColor(event.target, ui.color.toString());
			},
			clear: function (event) {
				applyColor($(event.target).closest('.wp-picker-input-wrap').find('.qrms-cs-color-picker')[0] || event.target, '');
			}
		});
	}

	$(function () {
		var cards = document.querySelectorAll('.qrms-cs-day');
		var i;

		for (i = 0; i < cards.length; i++) {
			bindDay(cards[i]);
		}

		bindColors();
	});
})(jQuery);
