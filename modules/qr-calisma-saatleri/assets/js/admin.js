/**
 * QR Çalışma Saatleri — yönetim ekranı.
 *
 * İki iş yapar:
 *   1) "Kapalı" kutusu işaretlenince o günün saat alanlarını kilitler.
 *   2) Canlı önizlemeyi besler: saat, kapalı gün, renk ve yazı tipi
 *      değişiklikleri kaydetmeden listeye yansır.
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

	/**
	 * Bir CSS değişkenini önizlemeye yazar (boş değer -> devral).
	 */
	function setVar(name, value) {
		var list = previewList();

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

	/**
	 * Kutu ölçüleri — PHP'deki qrms_cs_box_declarations() ile AYNI kural:
	 * çerçeve kalınlığı yalnızca kenar rengi seçilince, iç boşluk ve köşe
	 * yuvarlaması zemin ya da kenar seçilince basılır. Kural ayrışırsa
	 * önizleme kaydettikten sonraki görünümden farklı çıkardı.
	 */
	function syncBox() {
		var bg = document.getElementById('qrms-cs-renk-bg');
		var border = document.getElementById('qrms-cs-renk-border');
		var bgOn = !!(bg && bg.value);
		var borderOn = !!(border && border.value);

		setVar('--qrms-cs-border-width', borderOn ? '1px' : '');
		setVar('--qrms-cs-pad', bgOn || borderOn ? '12px 16px' : '');
		setVar('--qrms-cs-radius', bgOn || borderOn ? '10px' : '');
	}

	function applyColor(input, value) {
		setVar(input.getAttribute('data-css-var'), value);

		// Renk seçicinin kendi değeri, wpColorPicker'ın "change" olayında
		// henüz alana yazılmamış olabilir; kutu kuralı okunan değere değil
		// gelen değere bakabilsin diye önce alanı eşitliyoruz.
		input.value = value;

		syncBox();
	}

	/* ---------------- Yazı tipi ---------------- */

	/**
	 * Seçilen fontun Google Fonts stylesheet'ini bir kez ekler.
	 *
	 * Ön yüzde bu işi PHP yapar (qrms_cs_enqueue_font); burada yalnızca
	 * önizleme içindir: kullanıcı seçimi kaydetmeden gerçek yazı tipini
	 * görsün. Sistem fontlarında adres boştur, istek yapılmaz.
	 */
	function loadFont(url) {
		if (!url || document.querySelector('link[data-qrms-cs-font="' + url + '"]')) {
			return;
		}

		var link = document.createElement('link');

		link.rel = 'stylesheet';
		link.href = url;
		link.setAttribute('data-qrms-cs-font', url);

		document.head.appendChild(link);
	}

	function bindFont() {
		var select = document.getElementById('qrms-cs-renk-font');

		if (!select) {
			return;
		}

		select.addEventListener('change', function () {
			var option = select.options[select.selectedIndex];

			if (!option) {
				return;
			}

			loadFont(option.getAttribute('data-google'));
			setVar(select.getAttribute('data-css-var'), option.getAttribute('data-family') || '');
		});
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
		bindFont();
	});
})(jQuery);
