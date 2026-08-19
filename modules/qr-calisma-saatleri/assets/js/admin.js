/**
 * Kapalı kutusu işaretlenince o günün saat alanlarını kilitler.
 */
(function () {
	function bindDay(card) {
		var closed = card.querySelector('.qrms-cs-closed');
		var times = card.querySelectorAll('input[type="time"]');

		if (!closed) {
			return;
		}

		function sync() {
			var on = closed.checked;
			card.classList.toggle('is-closed', on);
			for (var i = 0; i < times.length; i++) {
				times[i].disabled = on;
			}
		}

		closed.addEventListener('change', sync);
		sync();
	}

	var cards = document.querySelectorAll('.qrms-cs-day');
	for (var i = 0; i < cards.length; i++) {
		bindDay(cards[i]);
	}
})();
