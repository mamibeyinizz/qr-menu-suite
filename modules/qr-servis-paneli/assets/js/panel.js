(function () {
	'use strict';

	var L = window.QRMS_SP || {};
	var panel = document.getElementById('qrms-sp-panel');
	if (!panel || !L.ajaxUrl) {
		return;
	}

	var esikSari = parseInt(panel.getAttribute('data-esik-sari') || '3', 10);
	var esikKirmizi = parseInt(panel.getAttribute('data-esik-kirmizi') || '7', 10);
	var baseInterval = parseInt(panel.getAttribute('data-yenileme') || '5', 10) * 1000;
	var interval = baseInterval;
	var hataSayisi = 0;
	var sonGorulen = '';
	var kayitlar = {};
	var timer = null;
	var sesAcik = localStorage.getItem('qrms_sp_ses') !== '0';
	var sesSeviye = parseFloat(localStorage.getItem('qrms_sp_ses_seviye') || '0.8');
	var orijinalBaslik = document.title;

	var sesInput = document.getElementById('qrms-sp-ses');
	var sesSeviyeInput = document.getElementById('qrms-sp-ses-seviye');
	var hataEl = document.getElementById('qrms-sp-hata');
	var tipFiltre = document.getElementById('qrms-sp-tip-filtre');
	var masaAra = document.getElementById('qrms-sp-masa-ara');
	var aktifFiltre = document.getElementById('qrms-sp-aktif');
	var bildirimBtn = document.getElementById('qrms-sp-bildirim');

	if (sesInput) {
		sesInput.checked = sesAcik;
		sesInput.addEventListener('change', function () {
			sesAcik = sesInput.checked;
			localStorage.setItem('qrms_sp_ses', sesAcik ? '1' : '0');
		});
	}
	if (sesSeviyeInput) {
		sesSeviyeInput.value = Math.round(sesSeviye * 100);
		sesSeviyeInput.addEventListener('input', function () {
			sesSeviye = parseInt(sesSeviyeInput.value, 10) / 100;
			localStorage.setItem('qrms_sp_ses_seviye', String(sesSeviye));
		});
	}

	if (bildirimBtn) {
		bildirimBtn.addEventListener('click', function () {
			if ('Notification' in window) {
				Notification.requestPermission();
			}
		});
	}

	function bip() {
		if (!sesAcik) { return; }
		try {
			var ctx = new (window.AudioContext || window.webkitAudioContext)();
			var osc = ctx.createOscillator();
			var gain = ctx.createGain();
			osc.connect(gain);
			gain.connect(ctx.destination);
			osc.frequency.value = 880;
			gain.gain.value = sesSeviye * 0.3;
			osc.start();
			osc.stop(ctx.currentTime + 0.15);
		} catch (e) { /* sessiz */ }
	}

	function beklemeDk(createdAt) {
		if (!createdAt) { return 0; }
		var t = new Date(createdAt).getTime();
		if (isNaN(t)) { t = Date.parse(createdAt.replace('Z', '+00:00')); }
		return Math.floor((Date.now() - t) / 60000);
	}

	function sonrakiDurum(d) {
		var map = { bekliyor: 'hazirlaniyor', hazirlaniyor: 'serviste', serviste: 'tamamlandi' };
		return map[d] || '';
	}
	function oncekiDurum(d) {
		var map = { hazirlaniyor: 'bekliyor', serviste: 'hazirlaniyor', tamamlandi: 'serviste' };
		return map[d] || '';
	}

	function kartHtml(k) {
		var dk = beklemeDk(k.createdAt);
		var sinif = dk >= esikKirmizi ? 'is-kirmizi' : (dk >= esikSari ? 'is-sari' : '');
		var tipEtiket = L.i18n[k.tip] || k.tip;
		var kalemler = '';
		if (k.items && k.items.length) {
			kalemler = k.items.map(function (it) {
				var ad = it.urunAdi || it.ad || '';
				var adet = it.adet || 1;
				var not = it.notTr || it.notOrijinal || '';
				return '<div>' + ad + ' × ' + adet + (not ? ' <em>(' + not + ')</em>' : '') + '</div>';
			}).join('');
		}
		var ileri = sonrakiDurum(k.durum);
		var geri = oncekiDurum(k.durum);
		return '<div class="qrms-sp-kart ' + sinif + '" data-id="' + k.id + '" data-durum="' + k.durum + '" data-created="' + k.createdAt + '">' +
			'<div class="qrms-sp-kart-masa">' + (k.masaAdi || k.masaNo) + '</div>' +
			'<span class="qrms-sp-kart-tip">' + tipEtiket + '</span>' +
			'<div class="qrms-sp-kart-sure" data-sure="' + k.createdAt + '">' + dk + ' dk</div>' +
			'<div class="qrms-sp-kart-kalemler">' + kalemler + '</div>' +
			'<div class="qrms-sp-kart-butonlar">' +
			(geri ? '<button type="button" class="button qrms-sp-geri" data-durum="' + geri + '">' + L.i18n.geri + '</button>' : '') +
			(ileri ? '<button type="button" class="button button-primary qrms-sp-ileri" data-durum="' + ileri + '">' + L.i18n.ileri + '</button>' : '') +
			(k.durum !== 'iptal' && k.durum !== 'tamamlandi' ? '<button type="button" class="button qrms-sp-iptal" data-durum="iptal">' + L.i18n.iptal + '</button>' : '') +
			'</div></div>';
	}

	function filtreUygun(k) {
		if (tipFiltre && tipFiltre.value && k.tip !== tipFiltre.value) { return false; }
		if (masaAra && masaAra.value) {
			var q = masaAra.value.toLowerCase();
			var ad = (k.masaAdi || k.masaNo || '').toLowerCase();
			if (ad.indexOf(q) === -1) { return false; }
		}
		if (aktifFiltre && aktifFiltre.checked && (k.durum === 'tamamlandi' || k.durum === 'iptal')) {
			if (k.durum === 'tamamlandi') {
				var dk = beklemeDk(k.guncellendi || k.createdAt);
				if (dk > 120) { return false; }
			} else {
				return false;
			}
		}
		return true;
	}

	function render() {
		var durumlar = ['bekliyor', 'hazirlaniyor', 'serviste', 'tamamlandi'];
		var sayilar = { bekliyor: 0, hazirlaniyor: 0, serviste: 0 };
		var liste = Object.keys(kayitlar).map(function (id) { return kayitlar[id]; });

		liste.sort(function (a, b) {
			var da = beklemeDk(a.createdAt), db = beklemeDk(b.createdAt);
			var ka = da >= esikKirmizi ? 1 : 0, kb = db >= esikKirmizi ? 1 : 0;
			if (ka !== kb) { return kb - ka; }
			return new Date(b.createdAt) - new Date(a.createdAt);
		});

		durumlar.forEach(function (d) {
			var el = document.querySelector('.qrms-sp-kartlar[data-durum="' + d + '"]');
			if (!el) { return; }
			var html = '';
			liste.forEach(function (k) {
				if (k.durum === d && filtreUygun(k)) {
					html += kartHtml(k);
					if (sayilar[d] !== undefined) { sayilar[d]++; }
				}
			});
			el.innerHTML = html;
		});

		document.querySelectorAll('.qrms-sp-rozet').forEach(function (r) {
			var d = r.getAttribute('data-durum');
			if (sayilar[d] !== undefined) { r.textContent = sayilar[d]; }
		});

		var yeni = liste.filter(function (k) { return k.durum === 'bekliyor'; }).length;
		document.title = yeni > 0 ? '(' + yeni + ') ' + L.i18n.yeni + ' — ' + orijinalBaslik : orijinalBaslik;
	}

	function yukle() {
		var body = new FormData();
		body.append('action', 'qrms_sp_liste');
		body.append('nonce', L.nonce);
		body.append('son_gorulen', sonGorulen);
		body.append('since', new Date(Date.now() - 86400000).toISOString());

		fetch(L.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (!res.success) { throw new Error(res.data && res.data.mesaj || 'error'); }
				hataSayisi = 0;
				if (hataEl) { hataEl.hidden = true; }
				interval = document.hidden ? 30000 : baseInterval;

				var oncekiIds = Object.keys(kayitlar);
				(res.data.kayitlar || []).forEach(function (k) {
					var yeniKayit = !kayitlar[k.id];
					kayitlar[k.id] = k;
					if (k.createdAt > sonGorulen) { sonGorulen = k.createdAt; }
					if (yeniKayit && k.durum === 'bekliyor') {
						bip();
						if ('Notification' in window && Notification.permission === 'granted') {
							new Notification(L.i18n.yeni, { body: (k.masaAdi || k.masaNo) + ' — ' + (L.i18n[k.tip] || k.tip) });
						}
					}
				});
				render();
			})
			.catch(function () {
				hataSayisi++;
				if (hataEl) { hataEl.hidden = false; }
				interval = Math.min(60000, baseInterval * Math.pow(2, hataSayisi));
			});
	}

	function durumGuncelle(id, durum, mevcut) {
		var body = new FormData();
		body.append('action', 'qrms_sp_durum');
		body.append('nonce', L.nonce);
		body.append('id', id);
		body.append('durum', durum);
		body.append('mevcut', mevcut);
		fetch(L.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (res.success && kayitlar[id]) {
					kayitlar[id].durum = durum;
					render();
				}
			});
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.qrms-sp-ileri, .qrms-sp-geri, .qrms-sp-iptal');
		if (!btn) { return; }
		var kart = btn.closest('.qrms-sp-kart');
		if (!kart) { return; }
		durumGuncelle(kart.getAttribute('data-id'), btn.getAttribute('data-durum'), kart.getAttribute('data-durum'));
	});

	document.querySelectorAll('.qrms-sp-sekme').forEach(function (tab) {
		tab.addEventListener('click', function () {
			document.querySelectorAll('.qrms-sp-sekme').forEach(function (t) { t.classList.remove('is-active'); });
			tab.classList.add('is-active');
			var d = tab.getAttribute('data-durum');
			document.querySelectorAll('.qrms-sp-sutun').forEach(function (s) {
				s.classList.toggle('is-active', s.getAttribute('data-durum') === d);
			});
		});
	});

	if (tipFiltre) { tipFiltre.addEventListener('change', render); }
	if (masaAra) { masaAra.addEventListener('input', render); }
	if (aktifFiltre) { aktifFiltre.addEventListener('change', render); }

	document.addEventListener('visibilitychange', function () {
		if (!document.hidden) {
			yukle();
			interval = baseInterval;
		}
	});

	setInterval(function () {
		document.querySelectorAll('[data-sure]').forEach(function (el) {
			var dk = beklemeDk(el.getAttribute('data-sure'));
			el.textContent = dk + ' dk';
			var kart = el.closest('.qrms-sp-kart');
			if (kart) {
				kart.classList.remove('is-sari', 'is-kirmizi');
				if (dk >= esikKirmizi) { kart.classList.add('is-kirmizi'); }
				else if (dk >= esikSari) { kart.classList.add('is-sari'); }
			}
		});
	}, 30000);

	function dongu() {
		yukle();
		clearTimeout(timer);
		timer = setTimeout(dongu, interval);
	}
	dongu();

	var ayarForm = document.getElementById('qrms-sp-ayarlar-form');
	if (ayarForm) {
		ayarForm.addEventListener('submit', function (e) {
			e.preventDefault();
			var fd = new FormData(ayarForm);
			fd.append('action', 'qrms_sp_ayarlar_kaydet');
			fd.append('nonce', L.ayarNonce);
			fetch(L.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) { if (res.success) { alert('Kaydedildi.'); } });
		});
	}
})();
