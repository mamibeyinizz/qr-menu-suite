<?php
/**
 * Galeri inline CSS/JS varlıkları.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

trait QRMGM_Assets_Trait {

	private function admin_css(): string {
		return <<<CSS
.qrmgm-wrap .qrmgm-title{display:flex;align-items:center;gap:16px}
.qrmgm-table td,.qrmgm-table th{vertical-align:middle}
.qrmgm-drag-handle{cursor:grab;color:#94a3b8;font-size:18px;text-align:center}
.qrmgm-switch{position:relative;display:inline-block;width:40px;height:22px}
.qrmgm-switch input{opacity:0;width:0;height:0}
.qrmgm-slider{position:absolute;inset:0;background:#cbd5e1;border-radius:22px;transition:.2s;cursor:pointer}
.qrmgm-slider:before{content:"";position:absolute;width:16px;height:16px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s}
.qrmgm-switch input:checked+.qrmgm-slider{background:#0F172A}
.qrmgm-switch input:checked+.qrmgm-slider:before{transform:translateX(18px)}
.qrmgm-modal{position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:100000;display:flex;align-items:center;justify-content:center}
.qrmgm-modal-box{background:#fff;padding:24px;border-radius:12px;width:480px;max-width:92vw;max-height:88vh;overflow:auto}
.qrmgm-modal-actions{display:flex;gap:8px}
.qrmgm-dropzone{border:2px dashed #94a3b8;border-radius:12px;padding:32px;text-align:center;margin:16px 0;background:#f8fafc}
.qrmgm-dropzone.is-dragover{border-color:#D4AF37;background:#fffbea}
.qrmgm-images-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px}
.qrmgm-image-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;position:relative}
.qrmgm-image-card img{width:100%;height:140px;object-fit:cover;display:block}
.qrmgm-card-body{padding:10px}
.qrmgm-card-desc{font-size:12px;color:#64748b;margin:4px 0}
.qrmgm-card-actions{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;align-items:center}
.qrmgm-mini-switch{font-size:11px;display:flex;align-items:center;gap:2px}
.qrmgm-tag-pill{display:inline-block;font-size:11px;background:#D4AF37;color:#0F172A;padding:2px 8px;border-radius:10px;margin-left:6px}
.qrmgm-card-drag{position:absolute;top:4px;left:4px;background:rgba(255,255,255,.85);border-radius:4px;padding:0 4px;z-index:2}
#qrmgm-upload-progress{margin-top:10px;font-size:13px;color:#0F172A}
CSS;
	}

	private function admin_js(): string {
		return <<<'JS'
(function($){
	$(function(){
		function ajax(action, data, cb){
			data = data || {};
			data.action = action;
			data.nonce  = QRMGM.nonce;
			$.post(QRMGM.ajaxUrl, data, function(res){
				if (res && res.success) { cb(res.data || {}); }
				else { alert((res && res.data && res.data.message) || QRMGM.i18n.error); }
			}).fail(function(){ alert(QRMGM.i18n.error); });
		}

		var $secModal = $('#qrmgm-section-modal');
		function openSectionModal(data){
			data = data || {};
			$('#qrmgm-modal-title').text(data.id ? 'Bölümü Düzenle' : 'Yeni Bölüm');
			$('#qrmgm-s-id').val(data.id || 0);
			$('#qrmgm-s-title').val(data.title || '');
			$('#qrmgm-s-slug').val(data.slug || '');
			$('#qrmgm-s-desc').val(data.desc || '');
			$('#qrmgm-s-icon').val(data.icon || '');
			$('#qrmgm-s-bg').val(data.bg || '#0F172A').wpColorPicker('color', data.bg || '#0F172A');
			$('#qrmgm-s-fg').val(data.fg || '#FFFFFF').wpColorPicker('color', data.fg || '#FFFFFF');
			$('#qrmgm-s-cover').val(data.cover || 0);
			$('#qrmgm-s-cover-preview').html(data.coverUrl ? '<img src="'+data.coverUrl+'" style="width:40px;height:40px;object-fit:cover;border-radius:6px;vertical-align:middle;margin-left:8px;">' : '');
			$secModal.show();
		}
		if ($('.qrmgm-color').length) { $('.qrmgm-color').wpColorPicker(); }

		$('#qrmgm-new-section').on('click', function(){ openSectionModal({}); });
		$(document).on('click', '.qrmgm-edit-section', function(){
			var d = $(this).data();
			openSectionModal({ id:d.id, title:d.title, slug:d.slug, desc:d.desc, icon:d.icon, bg:d.bg, fg:d.fg, cover:d.cover, coverUrl:d.coverUrl });
		});
		$('#qrmgm-s-cancel').on('click', function(){ $secModal.hide(); });
		$('#qrmgm-s-cover-btn').on('click', function(e){
			e.preventDefault();
			var frame = wp.media({ title:'Kapak Görseli Seç', multiple:false });
			frame.on('select', function(){
				var att = frame.state().get('selection').first().toJSON();
				$('#qrmgm-s-cover').val(att.id);
				$('#qrmgm-s-cover-preview').html('<img src="'+(att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url)+'" style="width:40px;height:40px;object-fit:cover;border-radius:6px;vertical-align:middle;margin-left:8px;">');
			});
			frame.open();
		});
		$('#qrmgm-s-save').on('click', function(){
			ajax('qrmgm_save_section', {
				id: $('#qrmgm-s-id').val(), title: $('#qrmgm-s-title').val(), slug: $('#qrmgm-s-slug').val(),
				desc: $('#qrmgm-s-desc').val(), icon: $('#qrmgm-s-icon').val(),
				bg: $('#qrmgm-s-bg').val(), fg: $('#qrmgm-s-fg').val(), cover: $('#qrmgm-s-cover').val()
			}, function(){ window.location.reload(); });
		});
		$(document).on('click', '.qrmgm-delete-section', function(){
			if (!confirm(QRMGM.i18n.confirmDelete)) return;
			var id = $(this).data('id');
			ajax('qrmgm_delete_section', { id:id }, function(){ window.location.reload(); });
		});
		$(document).on('change', '.qrmgm-toggle-section', function(){
			ajax('qrmgm_toggle_section_status', { id: $(this).data('id'), active: this.checked ? 1 : 0 }, function(){});
		});
		if ($('#qrmgm-sections-sortable').length) {
			$('#qrmgm-sections-sortable').sortable({
				handle: '.qrmgm-drag-handle',
				update: function(){
					var order = $('#qrmgm-sections-sortable tr').map(function(){ return $(this).data('id'); }).get();
					ajax('qrmgm_reorder_sections', { order: order }, function(){});
				}
			});
		}

		$('#qrmgm-section-select').on('change', function(){
			var id = $(this).val();
			window.location.href = window.location.pathname + '?page=qrmgm-images&section=' + id;
		});

		var $dropzone = $('#qrmgm-dropzone');
		var $fileInput = $('#qrmgm-file-input');
		$('#qrmgm-browse-btn').on('click', function(){ $fileInput.trigger('click'); });
		$fileInput.on('change', function(){ handleFiles(this.files); });
		$dropzone.on('dragover', function(e){ e.preventDefault(); $(this).addClass('is-dragover'); });
		$dropzone.on('dragleave', function(){ $(this).removeClass('is-dragover'); });
		$dropzone.on('drop', function(e){
			e.preventDefault(); $(this).removeClass('is-dragover');
			handleFiles(e.originalEvent.dataTransfer.files);
		});

		function handleFiles(files){
			var sectionId = $('#qrmgm-images-grid').data('section');
			var total = files.length, done = 0;
			$('#qrmgm-upload-progress').text('Yükleniyor: 0/' + total);
			Array.prototype.forEach.call(files, function(file){
				var fd = new FormData();
				fd.append('action', 'qrmgm_upload_image');
				fd.append('nonce', QRMGM.nonce);
				fd.append('section_id', sectionId);
				fd.append('file', file);
				$.ajax({
					url: QRMGM.ajaxUrl, type: 'POST', data: fd, processData: false, contentType: false
				}).always(function(){
					done++;
					$('#qrmgm-upload-progress').text('Yükleniyor: ' + done + '/' + total);
					if (done === total) { refreshGrid(sectionId); }
				});
			});
		}

		function refreshGrid(sectionId){
			ajax('qrmgm_get_section_images', { section_id: sectionId }, function(data){
				$('#qrmgm-images-grid').html(data.html);
				$('#qrmgm-upload-progress').text('');
			});
		}

		$(document).on('click', '.qrmgm-delete-image', function(){
			if (!confirm(QRMGM.i18n.confirmDelete)) return;
			var id = $(this).data('id');
			var sectionId = $('#qrmgm-images-grid').data('section');
			ajax('qrmgm_delete_image', { id:id }, function(){ refreshGrid(sectionId); });
		});
		$(document).on('click', '.qrmgm-duplicate-image', function(){
			var id = $(this).data('id');
			var sectionId = $('#qrmgm-images-grid').data('section');
			ajax('qrmgm_duplicate_image', { id:id }, function(){ refreshGrid(sectionId); });
		});
		$(document).on('change', '.qrmgm-toggle-image', function(){
			ajax('qrmgm_toggle_image_status', { id: $(this).data('id'), active: this.checked ? 1 : 0 }, function(){});
		});
		$(document).on('change', '.qrmgm-toggle-featured', function(){
			ajax('qrmgm_toggle_image_featured', { id: $(this).data('id'), featured: this.checked ? 1 : 0 }, function(){});
		});

		var $imgModal = $('#qrmgm-image-modal');
		$(document).on('click', '.qrmgm-edit-image', function(){
			var d = $(this).data();
			$('#qrmgm-i-id').val(d.id); $('#qrmgm-i-title').val(d.title); $('#qrmgm-i-alt').val(d.alt);
			$('#qrmgm-i-desc').val(d.desc); $('#qrmgm-i-tag').val(d.tag);
			$imgModal.show();
		});
		$('#qrmgm-i-cancel').on('click', function(){ $imgModal.hide(); });
		$('#qrmgm-i-save').on('click', function(){
			var sectionId = $('#qrmgm-images-grid').data('section');
			ajax('qrmgm_save_image', {
				id: $('#qrmgm-i-id').val(), title: $('#qrmgm-i-title').val(), alt: $('#qrmgm-i-alt').val(),
				desc: $('#qrmgm-i-desc').val(), tag: $('#qrmgm-i-tag').val()
			}, function(){ $imgModal.hide(); refreshGrid(sectionId); });
		});

		if ($('#qrmgm-images-grid').length) {
			$('#qrmgm-images-grid').sortable({
				handle: '.qrmgm-card-drag', items: '.qrmgm-image-card',
				update: function(){
					var order = $('#qrmgm-images-grid .qrmgm-image-card').map(function(){ return $(this).data('id'); }).get();
					ajax('qrmgm_reorder_images', { order: order }, function(){});
				}
			});
		}
	});
})(jQuery);
JS;
	}

	private function frontend_css( array $s ): string {
		$shadow_map = [
			'none'   => 'none',
			'light'  => '0 2px 8px rgba(15,23,42,.08)',
			'medium' => '0 8px 24px rgba(15,23,42,.14)',
			'strong' => '0 16px 40px rgba(15,23,42,.22)',
		];
		$shadow = $shadow_map[ $s['shadow'] ] ?? $shadow_map['medium'];

		return <<<CSS
.qrmgm-gallery{
	--qrmgm-radius:{$s['radius']}px;
	--qrmgm-gap:{$s['gap']}px;
	--qrmgm-cols-desktop:{$s['columns_desktop']};
	--qrmgm-cols-tablet:{$s['columns_tablet']};
	--qrmgm-cols-mobile:{$s['columns_mobile']};
	--qrmgm-dark:{$s['color_dark']};
	--qrmgm-gold:{$s['color_gold']};
	--qrmgm-light:{$s['color_light']};
	--qrmgm-white:{$s['color_white']};
	--qrmgm-overlay:{$s['overlay_opacity']}%;
	--qrmgm-shadow:{$shadow};
	font-family:'{$s['font']}',sans-serif;
}
.qrmgm-filter-bar{display:flex;flex-wrap:nowrap;overflow-x:auto;gap:8px;margin-bottom:20px;scrollbar-width:none;-ms-overflow-style:none}
.qrmgm-filter-bar::-webkit-scrollbar{display:none}
.qrmgm-filter-btn{flex-shrink:0;white-space:nowrap;border:1px solid var(--qrmgm-dark);background:transparent;color:var(--qrmgm-dark);padding:8px 16px;border-radius:999px;cursor:pointer;font-size:13px;transition:all .25s ease}
.qrmgm-filter-btn.is-active,.qrmgm-filter-btn:hover{background:var(--qrmgm-dark);color:var(--qrmgm-white)}
.qrmgm-grid{display:grid;gap:14px;grid-template-columns:repeat(var(--qrmgm-cols-desktop, 3),1fr)}
@media(max-width:1024px){.qrmgm-grid{grid-template-columns:repeat(var(--qrmgm-cols-tablet, 2),1fr)}}
@media(max-width:640px){.qrmgm-grid{grid-template-columns:repeat(var(--qrmgm-cols-mobile, 1),1fr)}}
.qrmgm-item{margin:0;position:relative;border-radius:14px;overflow:hidden;background:#0f172a;transition:box-shadow .35s ease}
.qrmgm-item.qrmgm-hidden-filter{display:none}
.qrmgm-item a{display:block;width:100%;height:100%;position:relative}
.qrmgm-item picture{display:block;width:100%;height:100%}
.qrmgm-item img{width:100%;height:100%;object-fit:cover;display:block;aspect-ratio:var(--qrmgm-ratio, 4/3);transition:transform .35s ease}
.qrmgm-item:hover img{transform:scale(1.04)}
.qrmgm-item:hover{box-shadow:0 10px 30px rgba(0,0,0,.28)}
.qrmgm-item figcaption{position:absolute;left:0;right:0;bottom:0;padding:10px 12px;display:flex;align-items:center;gap:8px;color:#fff;font-size:13px;font-weight:600;
	background:linear-gradient(to top,rgba(0,0,0,.72),transparent);
	opacity:0;transform:translateY(6px);transition:.28s ease}
.qrmgm-item:hover figcaption{opacity:1;transform:none}
@media(hover:none){.qrmgm-item figcaption{opacity:1;transform:none}}
@media(prefers-reduced-motion:reduce){
	.qrmgm-gallery *,.qrmgm-gallery *::before,.qrmgm-gallery *::after{transform:none!important;transition:none!important}
}
.qrmgm-lightbox{position:fixed;inset:0;background:rgba(15,23,42,.94);z-index:99999;display:none;align-items:center;justify-content:center;flex-direction:column}
.qrmgm-lightbox.is-open{display:flex}
.qrmgm-lightbox img{max-width:90vw;max-height:78vh;border-radius:8px;touch-action:none}
.qrmgm-lightbox-caption{color:var(--qrmgm-white);margin-top:14px;font-size:14px;text-align:center;max-width:80vw}
.qrmgm-lightbox-controls{position:absolute;top:20px;right:24px;display:flex;gap:14px}
.qrmgm-lightbox-controls button{background:rgba(255,255,255,.12);border:none;color:#fff;width:40px;height:40px;border-radius:50%;cursor:pointer;font-size:16px}
.qrmgm-lightbox-nav{position:absolute;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.12);border:none;color:#fff;width:46px;height:46px;border-radius:50%;cursor:pointer;font-size:20px}
.qrmgm-lightbox-prev{left:20px}
.qrmgm-lightbox-next{right:20px}
.qrmgm-lightbox-counter{position:absolute;top:24px;left:24px;color:#fff;font-size:13px;opacity:.8}
CSS;
	}

	private function frontend_js( array $s ): string {
		return <<<'JS'
(function(){
	document.addEventListener('DOMContentLoaded', function(){
		var galleries = document.querySelectorAll('.qrmgm-gallery');
		if (!galleries.length) return;

		if (window.qrmsAnalitikOnyuz && typeof window.qrmsAnalitikOnyuz.yaz === 'function') {
			window.qrmsAnalitikOnyuz.yaz('gallery_view');
		}

		galleries.forEach(function(gallery){
			var items = Array.prototype.slice.call(gallery.querySelectorAll('.qrmgm-item'));

			if ('IntersectionObserver' in window) {
				var io = new IntersectionObserver(function(entries){
					entries.forEach(function(entry, idx){
						if (entry.isIntersecting) {
							setTimeout(function(){ entry.target.classList.add('qrmgm-visible'); }, (idx % 8) * 60);
							io.unobserve(entry.target);
						}
					});
				}, { threshold: 0.12 });
				items.forEach(function(item){ io.observe(item); });
			} else {
				items.forEach(function(item){ item.classList.add('qrmgm-visible'); });
			}

			var filterBtns = gallery.querySelectorAll('.qrmgm-filter-btn');
			filterBtns.forEach(function(btn){
				btn.addEventListener('click', function(){
					filterBtns.forEach(function(b){ b.classList.remove('is-active'); });
					btn.classList.add('is-active');
					var filter = btn.getAttribute('data-filter');
					items.forEach(function(item){
						var match = (filter === 'all' || item.getAttribute('data-section') === filter);
						item.classList.toggle('qrmgm-hidden-filter', !match);
					});
				});
			});

			if (gallery.getAttribute('data-lightbox') !== '1') return;

			var triggers = Array.prototype.slice.call(gallery.querySelectorAll('.qrmgm-lightbox-trigger'));
			if (!triggers.length) return;

			var lb = document.createElement('div');
			lb.className = 'qrmgm-lightbox';
			lb.innerHTML = '<div class="qrmgm-lightbox-counter"></div>' +
				'<div class="qrmgm-lightbox-controls">' +
					'<button type="button" class="qrmgm-lb-download" title="İndir">&#8681;</button>' +
					'<button type="button" class="qrmgm-lb-fullscreen" title="Tam Ekran">&#9974;</button>' +
					'<button type="button" class="qrmgm-lb-close" title="Kapat">&times;</button>' +
				'</div>' +
				'<button type="button" class="qrmgm-lightbox-nav qrmgm-lightbox-prev">&#10094;</button>' +
				'<img src="" alt="" />' +
				'<button type="button" class="qrmgm-lightbox-nav qrmgm-lightbox-next">&#10095;</button>' +
				'<div class="qrmgm-lightbox-caption"></div>';
			document.body.appendChild(lb);

			var img = lb.querySelector('img');
			var caption = lb.querySelector('.qrmgm-lightbox-caption');
			var counter = lb.querySelector('.qrmgm-lightbox-counter');
			var current = 0;
			var visibleTriggers = function(){
				return triggers.filter(function(t){ return t.closest('.qrmgm-item').offsetParent !== null; });
			};

			function open(index){
				var list = visibleTriggers();
				if (!list.length) return;
				current = index;
				var t = list[current];
				img.src = t.getAttribute('href');
				img.alt = t.getAttribute('data-caption') || '';
				caption.textContent = t.getAttribute('data-caption') || '';
				counter.textContent = (current + 1) + ' / ' + list.length;
				lb.classList.add('is-open');
				document.body.style.overflow = 'hidden';
			}
			function close(){
				lb.classList.remove('is-open');
				document.body.style.overflow = '';
			}
			function nav(dir){
				var list = visibleTriggers();
				current = (current + dir + list.length) % list.length;
				open(current);
			}

			triggers.forEach(function(t, i){
				t.addEventListener('click', function(e){
					e.preventDefault();
					open(visibleTriggers().indexOf(t));
				});
			});

			lb.querySelector('.qrmgm-lb-close').addEventListener('click', close);
			lb.querySelector('.qrmgm-lightbox-prev').addEventListener('click', function(){ nav(-1); });
			lb.querySelector('.qrmgm-lightbox-next').addEventListener('click', function(){ nav(1); });
			lb.querySelector('.qrmgm-lb-download').addEventListener('click', function(){
				var a = document.createElement('a');
				a.href = img.src; a.download = '';
				document.body.appendChild(a); a.click(); a.remove();
			});
			lb.querySelector('.qrmgm-lb-fullscreen').addEventListener('click', function(){
				if (lb.requestFullscreen) { lb.requestFullscreen(); }
			});
			lb.addEventListener('click', function(e){ if (e.target === lb) close(); });

			document.addEventListener('keydown', function(e){
				if (!lb.classList.contains('is-open')) return;
				if (e.key === 'Escape') close();
				if (e.key === 'ArrowLeft') nav(-1);
				if (e.key === 'ArrowRight') nav(1);
			});

			lb.addEventListener('wheel', function(e){
				if (!lb.classList.contains('is-open')) return;
				e.preventDefault();
				nav(e.deltaY > 0 ? 1 : -1);
			}, { passive: false });

			var touchStartX = 0, touchStartY = 0, initialDist = 0, scale = 1;
			lb.addEventListener('touchstart', function(e){
				if (e.touches.length === 1) {
					touchStartX = e.touches[0].clientX;
					touchStartY = e.touches[0].clientY;
				} else if (e.touches.length === 2) {
					initialDist = Math.hypot(
						e.touches[0].clientX - e.touches[1].clientX,
						e.touches[0].clientY - e.touches[1].clientY
					);
				}
			}, { passive: true });
			lb.addEventListener('touchmove', function(e){
				if (e.touches.length === 2) {
					var dist = Math.hypot(
						e.touches[0].clientX - e.touches[1].clientX,
						e.touches[0].clientY - e.touches[1].clientY
					);
					scale = Math.max(1, Math.min(3, dist / initialDist));
					img.style.transform = 'scale(' + scale + ')';
				}
			}, { passive: true });
			lb.addEventListener('touchend', function(e){
				if (e.changedTouches.length === 1 && scale === 1) {
					var dx = e.changedTouches[0].clientX - touchStartX;
					var dy = e.changedTouches[0].clientY - touchStartY;
					if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy)) {
						nav(dx < 0 ? 1 : -1);
					}
				}
				if (e.touches.length === 0) { scale = 1; img.style.transform = ''; }
			});
		});
	});
})();
JS;
	}
}
