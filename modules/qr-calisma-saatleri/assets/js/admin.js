/**
 * QR Çalışma Saatleri — yönetim ekranı.
 *
 * Dört iş yapar:
 *   1) "Kapalı" anahtarı açılınca gün kartını ve durum etiketini günceller.
 *   2) Canlı önizlemeyi besler: saat, kapalı gün, renk ve yazı tipi
 *      değişiklikleri kaydetmeden listeye yansır.
 *   3) Hızlı işlemler: bir günün saatlerini hafta içine / hafta sonuna /
 *      tüm günlere kopyalar, tüm günleri açar veya kapatır — tek adımlık
 *      geri alma ile.
 *   4) Kaydedilmemiş değişiklik varsa uyarır.
 *
 * Önizleme kısa kodun GERÇEK çıktısıdır (PHP tarafında basılır) ve gerçek
 * frontend stylesheet'ini kullanır; buradaki kod yalnızca hazır DOM'u
 * günceller — ikinci bir şablon yoktur.
 */
(function ($) {
	'use strict';

	var L = window.QRMS_CS || {};

	// Gün anahtarı -> { card, closed, open, close, sync }. Hızlı işlemler
	// ve geri alma bu kayıttan çalışır; DOM ikinci kez taranmaz.
	var days = {};
	var undoState = null;
	var dirty = false;
	var ready = false;

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

	/**
	 * Gün kartındaki durum etiketi — PHP'deki qrms_cs_admin_durum() ile
	 * aynı üç dal. Aralık dalında saatin kendisi alanlarda yazdığı için
	 * kartta yalnızca "Açık" der.
	 */
	function stateLabel(closed, open, close) {
		if (closed) {
			return L.kapali || 'Kapalı';
		}

		if (open === close) {
			return L.yirmiDort || '24 saat açık';
		}

		return L.acik || 'Açık';
	}

	function previewItem(day) {
		return document.querySelector('#qrms-cs-preview .qrms-cs-item[data-day="' + day + '"]');
	}

	// Renk değişkenleri kartta (kısa kodun kapsayıcısında) durur — ön yüzde
	// qrms_cs_inline_style_attr() de onları oraya basar.
	function previewList() {
		return document.querySelector('#qrms-cs-preview .qrms-cs-card');
	}

	/* ---------------- Kaydedilmemiş değişiklik ---------------- */

	function markDirty() {
		if (dirty || !ready) {
			return;
		}

		dirty = true;

		var note = document.getElementById('qrms-cs-dirty');

		if (note) {
			note.hidden = false;
		}
	}

	function bindDirty() {
		var form = document.getElementById('qrms-cs-form');

		if (!form) {
			return;
		}

		// Kaynak gün seçici veriyi değiştirmez, yalnızca hangi günün
		// kopyalanacağını söyler — "kaydedilmemiş değişiklik" saymaz.
		function onEdit(event) {
			if (event.target && 'qrms-cs-quick-day' === event.target.id) {
				return;
			}

			markDirty();
		}

		form.addEventListener('input', onEdit);
		form.addEventListener('change', onEdit);
		form.addEventListener('submit', function () {
			dirty = false;
		});

		window.addEventListener('beforeunload', function (event) {
			if (!dirty) {
				return;
			}

			// Metni tarayıcı belirler; burada yalnızca uyarıyı açıyoruz.
			event.preventDefault();
			event.returnValue = '';
		});
	}

	/* ---------------- Gün kartları ---------------- */

	function bindDay(card) {
		var day = card.getAttribute('data-day');
		var closed = card.querySelector('.qrms-cs-closed');
		var open = card.querySelector('.qrms-cs-open');
		var close = card.querySelector('.qrms-cs-close');
		var state = card.querySelector('.qrms-cs-state');
		var stateText = card.querySelector('.qrms-cs-state-text');

		if (!closed || !open || !close) {
			return;
		}

		function sync() {
			var on = closed.checked;

			card.classList.toggle('is-closed', on);

			/*
			 * Saat alanları KİLİTLENMEZ, yalnızca soluklaşır: disabled alan
			 * gönderilmez ve kapalı bir günün saatleri her kayıtta
			 * varsayılana düşerdi (qrms_cs_sanitize boş değeri varsayılanla
			 * doldurur). Gün yeniden açıldığında eski saatler duruyor.
			 */
			if (state) {
				state.classList.toggle('is-shut', on);
				state.classList.toggle('is-open', !on);
			}
			if (stateText) {
				stateText.textContent = stateLabel(on, open.value, close.value);
			}

			var item = previewItem(day);
			if (!item) {
				return;
			}

			item.classList.toggle('is-closed', on);

			var hours = item.querySelector('.qrms-cs-item-hours');
			if (hours) {
				hours.textContent = formatDay(on, open.value, close.value);
			}
		}

		closed.addEventListener('change', sync);
		open.addEventListener('input', sync);
		open.addEventListener('change', sync);
		close.addEventListener('input', sync);
		close.addEventListener('change', sync);

		days[day] = {
			card: card,
			closed: closed,
			open: open,
			close: close,
			sync: sync
		};

		sync();
	}

	/* ---------------- Hızlı işlemler ---------------- */

	function toast(message) {
		var box = document.getElementById('qrms-cs-toast');

		if (!box) {
			return;
		}

		box.textContent = message;
		box.hidden = false;
	}

	function showUndo(on) {
		var button = document.getElementById('qrms-cs-undo');

		if (button) {
			button.hidden = !on;
		}
	}

	function snapshot() {
		var state = {};
		var key;

		for (key in days) {
			if (Object.prototype.hasOwnProperty.call(days, key)) {
				state[key] = {
					closed: days[key].closed.checked,
					open: days[key].open.value,
					close: days[key].close.value
				};
			}
		}

		return state;
	}

	function restore(state) {
		var key;

		for (key in state) {
			if (Object.prototype.hasOwnProperty.call(state, key) && days[key]) {
				days[key].closed.checked = state[key].closed;
				days[key].open.value = state[key].open;
				days[key].close.value = state[key].close;
				days[key].sync();
			}
		}
	}

	/**
	 * Toplu işlemden ÖNCEKİ hâli saklar: geri alma tek adımlıktır,
	 * kaydetmeden önce yanlış bir kopyalama geri alınabilsin diye.
	 */
	function remember() {
		undoState = snapshot();
		showUndo(true);
	}

	function copyTo(targets, source) {
		var from = days[source];
		var to;
		var i;

		if (!from) {
			return;
		}

		for (i = 0; i < targets.length; i++) {
			to = days[targets[i]];

			if (!to || targets[i] === source) {
				continue;
			}

			to.closed.checked = from.closed.checked;
			to.open.value = from.open.value;
			to.close.value = from.close.value;
			to.sync();
		}
	}

	function setAllClosed(on) {
		var key;

		for (key in days) {
			if (Object.prototype.hasOwnProperty.call(days, key)) {
				days[key].closed.checked = on;
				days[key].sync();
			}
		}
	}

	function bindQuick() {
		var box = document.getElementById('qrms-cs-quick');
		var source = document.getElementById('qrms-cs-quick-day');

		if (!box || !source) {
			return;
		}

		// JS olmadan çalışmayan düğmeler hiç görünmesin (PHP hidden basar).
		box.hidden = false;

		box.addEventListener('click', function (event) {
			var button = event.target.closest('[data-qrms-cs-action]');

			if (!button) {
				return;
			}

			var action = button.getAttribute('data-qrms-cs-action');

			if ('undo' === action) {
				if (undoState) {
					restore(undoState);
					undoState = null;
					showUndo(false);
					toast(L.geriAlindi || '');
					markDirty();
				}
				return;
			}

			remember();

			if ('weekdays' === action) {
				copyTo(L.haftaIci || [], source.value);
			} else if ('weekend' === action) {
				copyTo(L.haftaSonu || [], source.value);
			} else if ('all' === action) {
				copyTo(L.gunler || [], source.value);
			} else if ('open-all' === action) {
				setAllClosed(false);
			} else if ('close-all' === action) {
				setAllClosed(true);
			}

			toast(L.uygulandi || '');
			markDirty();
		});
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
		markDirty();
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
		bindQuick();
		bindDirty();

		// Kurulum sırasındaki senkron çağrılar "değişiklik" sayılmasın.
		ready = true;
	});
})(jQuery);
