(function () {
	'use strict';

	var tablo = document.getElementById('qrms-mm-rapor-tablo');
	if (!tablo) {
		return;
	}

	var basliklar = tablo.querySelectorAll('th[data-sort]');
	var tbody = tablo.querySelector('tbody');

	basliklar.forEach(function (th) {
		th.addEventListener('click', function () {
			var key = th.getAttribute('data-sort');
			var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
			var asc = th.getAttribute('data-asc') !== '1';
			th.setAttribute('data-asc', asc ? '1' : '0');

			rows.sort(function (a, b) {
				var av, bv;
				if (key === 'ad' || key === 'kategori') {
					av = a.cells[key === 'ad' ? 0 : 1].textContent.trim();
					bv = b.cells[key === 'ad' ? 0 : 1].textContent.trim();
					return asc ? av.localeCompare(bv) : bv.localeCompare(av);
				}
				var idx = { satis: 2, ciro: 3, cm: 4, marj: 5 }[key];
				av = parseFloat(a.cells[idx].getAttribute('data-val') || '0');
				bv = parseFloat(b.cells[idx].getAttribute('data-val') || '0');
				return asc ? av - bv : bv - av;
			});

			rows.forEach(function (r) { tbody.appendChild(r); });
		});
	});

	document.querySelectorAll('.qrms-mm-urun-link').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var id = btn.getAttribute('data-id');
			var row = tablo.querySelector('tr[data-id="' + id + '"]');
			if (!row) {
				return;
			}
			tablo.querySelectorAll('tr').forEach(function (r) { r.classList.remove('qrms-mm-vurgu'); });
			row.classList.add('qrms-mm-vurgu');
			row.scrollIntoView({ behavior: 'smooth', block: 'center' });
		});
	});
})();
