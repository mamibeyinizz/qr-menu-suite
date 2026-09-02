/**
 * Tüm Yorumlar — iş akışı satır kontrolleri (AJAX kayıt).
 */
(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	ready(function () {
		var cfg = window.qrmReviewWorkflow;
		if (!cfg || !cfg.ajaxUrl) {
			return;
		}

		var table = document.querySelector('.qrm-review-workflow-table');
		if (!table) {
			return;
		}

		var saveTimers = {};

		function rowBlock(row) {
			return row.closest('.qrm-review-row-block');
		}

		function setStatus(block, state, text) {
			var el = block.querySelector('.qrm-wf-save-status');
			if (!el) {
				return;
			}
			el.className = 'qrm-wf-save-status qrm-wf-save-' + state;
			el.textContent = text || '';
		}

		function collectPayload(block) {
			var row = block.querySelector('.qrm-review-row');
			var status = block.querySelector('.qrm-wf-status');
			var assignee = block.querySelector('.qrm-wf-assignee');
			var note = block.querySelector('.qrm-wf-note');

			return {
				action: 'qrm_review_workflow_save',
				nonce: cfg.nonce,
				id: row ? row.getAttribute('data-review-id') : '0',
				workflow_status: status ? status.value : 'new',
				assigned_user_id: assignee ? assignee.value : '0',
				internal_note: note ? note.value : '',
			};
		}

		function save(block) {
			var id = block.querySelector('.qrm-review-row').getAttribute('data-review-id');
			if (saveTimers[id]) {
				clearTimeout(saveTimers[id]);
				delete saveTimers[id];
			}

			setStatus(block, 'saving', cfg.i18n.saving);

			var body = new URLSearchParams(collectPayload(block));

			fetch(cfg.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString(),
			})
				.then(function (res) {
					return res.json();
				})
				.then(function (json) {
					if (!json || !json.success) {
						var msg = json && json.data && json.data.message ? json.data.message : cfg.i18n.error;
						setStatus(block, 'error', msg);
						return;
					}

					setStatus(block, 'ok', cfg.i18n.saved);

					var resolvedEl = block.querySelector('.qrm-wf-resolved-at');
					if (resolvedEl && json.data) {
						if (json.data.resolved_label) {
							resolvedEl.textContent = cfg.i18n.resolvedAt + ' ' + json.data.resolved_label;
							resolvedEl.hidden = false;
						} else {
							resolvedEl.textContent = '';
							resolvedEl.hidden = true;
						}
					}

					window.setTimeout(function () {
						var statusEl = block.querySelector('.qrm-wf-save-status');
						if (statusEl && statusEl.classList.contains('qrm-wf-save-ok')) {
							statusEl.textContent = '';
							statusEl.className = 'qrm-wf-save-status';
						}
					}, 2000);
				})
				.catch(function () {
					setStatus(block, 'error', cfg.i18n.error);
				});
		}

		function scheduleSave(block) {
			var id = block.querySelector('.qrm-review-row').getAttribute('data-review-id');
			if (saveTimers[id]) {
				clearTimeout(saveTimers[id]);
			}
			saveTimers[id] = window.setTimeout(function () {
				save(block);
			}, 450);
		}

		table.addEventListener('change', function (e) {
			var target = e.target;
			if (!target.classList.contains('qrm-wf-status') && !target.classList.contains('qrm-wf-assignee')) {
				return;
			}
			save(rowBlock(target));
		});

		table.addEventListener('input', function (e) {
			if (!e.target.classList.contains('qrm-wf-note')) {
				return;
			}
			scheduleSave(rowBlock(e.target));
		});

		table.addEventListener('click', function (e) {
			var toggle = e.target.closest('.qrm-wf-note-toggle');
			if (!toggle) {
				return;
			}

			e.preventDefault();
			var block = rowBlock(toggle);
			var noteRow = block.querySelector('.qrm-wf-note-row');
			if (!noteRow) {
				return;
			}

			var open = !noteRow.hidden;
			noteRow.hidden = open;
			toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
			toggle.classList.toggle('is-open', !open);

			if (!open) {
				var note = noteRow.querySelector('.qrm-wf-note');
				if (note) {
					note.focus();
				}
			}
		});
	});
})();
