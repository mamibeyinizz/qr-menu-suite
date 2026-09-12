/**
 * Hazır sorular — kart tabanlı sürükle-bırak, ekleme/silme,
 * "asistana aynı soruyu gönder" progressive disclosure ve
 * canlı önizleme senkronizasyonu.
 */
( function () {
	'use strict';

	var list = document.getElementById( 'qmo-cb-soru-listesi' );
	var ekle = document.getElementById( 'qmo-cb-soru-ekle' );
	if ( ! list ) {
		return;
	}

	var suruklenen = null;

	function onizlemeGuncelle() {
		if ( typeof window.qmoChatbotRenderPreview === 'function' ) {
			window.qmoChatbotRenderPreview();
		}
	}

	function yenidenIsimlendir() {
		Array.prototype.forEach.call( list.querySelectorAll( '.qmo-cb-question-card' ), function ( kart, i ) {
			kart.querySelectorAll( '[name]' ).forEach( function ( input ) {
				input.name = input.name.replace( /qmo_chatbot_quick_replies\[\d+\]/, 'qmo_chatbot_quick_replies[' + i + ']' );
			} );
			var index = kart.querySelector( '.qmo-cb-question-index' );
			if ( index ) {
				index.textContent = ( '0' + ( i + 1 ) ).slice( -2 );
			}
		} );
	}

	function toggleMetniGuncelle( kart ) {
		var cb = kart.querySelector( 'input[type="checkbox"][name$="[enabled]"]' );
		var metin = kart.querySelector( '.qmo-cb-toggle-text' );
		if ( cb && metin ) {
			metin.textContent = cb.checked ? 'Aktif' : 'Pasif';
		}
	}

	function ayniSoruDurumu( kart, ayni ) {
		var aiBlok = kart.querySelector( '.qmo-cb-question-ai' );
		if ( aiBlok ) {
			aiBlok.hidden = ayni;
		}
		if ( ayni ) {
			mirrorLabelToQuestion( kart );
		}
	}

	function mirrorLabelToQuestion( kart ) {
		var same = kart.querySelector( '.qmo-cb-same-question' );
		if ( ! same || ! same.checked ) {
			return;
		}
		var label = kart.querySelector( '.qmo-cb-question-label' );
		var question = kart.querySelector( '.qmo-cb-question-question' );
		if ( label && question ) {
			question.value = label.value;
		}
	}

	list.addEventListener( 'dragstart', function ( e ) {
		var kart = e.target.closest( '.qmo-cb-question-card' );
		if ( ! kart ) {
			return;
		}
		suruklenen = kart;
		kart.classList.add( 'is-dragging' );
	} );

	list.addEventListener( 'dragend', function () {
		if ( suruklenen ) {
			suruklenen.classList.remove( 'is-dragging' );
		}
		suruklenen = null;
		yenidenIsimlendir();
		onizlemeGuncelle();
	} );

	list.addEventListener( 'dragover', function ( e ) {
		e.preventDefault();
		var kart = e.target.closest( '.qmo-cb-question-card' );
		if ( ! kart || ! suruklenen || kart === suruklenen ) {
			return;
		}
		var rect = kart.getBoundingClientRect();
		var after = ( e.clientY - rect.top ) > ( rect.height / 2 );
		list.insertBefore( suruklenen, after ? kart.nextSibling : kart );
	} );

	// Sürükle-bırağın erişilebilir alternatifi: tutamak odaktayken ok tuşları.
	list.addEventListener( 'keydown', function ( e ) {
		if ( 'ArrowUp' !== e.key && 'ArrowDown' !== e.key ) {
			return;
		}
		var tutamak = e.target.closest( '.qmo-cb-drag-handle' );
		if ( ! tutamak ) {
			return;
		}
		var kart = tutamak.closest( '.qmo-cb-question-card' );
		if ( ! kart ) {
			return;
		}
		var hedef = 'ArrowUp' === e.key ? kart.previousElementSibling : kart.nextElementSibling;
		if ( ! hedef ) {
			return;
		}
		e.preventDefault();
		if ( 'ArrowUp' === e.key ) {
			list.insertBefore( kart, hedef );
		} else {
			list.insertBefore( hedef, kart );
		}
		yenidenIsimlendir();
		onizlemeGuncelle();
		tutamak.focus();
	} );

	list.addEventListener( 'click', function ( e ) {
		var silBtn = e.target.closest( '.qmo-cb-question-delete' );
		if ( silBtn ) {
			var kart = silBtn.closest( '.qmo-cb-question-card' );
			if ( kart ) {
				kart.remove();
				yenidenIsimlendir();
				onizlemeGuncelle();
			}
			return;
		}

		var editBtn = e.target.closest( '.qmo-cb-question-edit' );
		if ( editBtn ) {
			var kart2 = editBtn.closest( '.qmo-cb-question-card' );
			var advanced = kart2 ? kart2.querySelector( '.qmo-cb-question-advanced' ) : null;
			if ( advanced ) {
				advanced.hidden = ! advanced.hidden;
				editBtn.setAttribute( 'aria-expanded', advanced.hidden ? 'false' : 'true' );
			}
		}
	} );

	list.addEventListener( 'change', function ( e ) {
		if ( e.target.classList.contains( 'qmo-cb-same-question' ) ) {
			var kart = e.target.closest( '.qmo-cb-question-card' );
			if ( kart ) {
				ayniSoruDurumu( kart, e.target.checked );
			}
		}
		if ( e.target.matches( 'input[type="checkbox"][name$="[enabled]"]' ) ) {
			var kartToggle = e.target.closest( '.qmo-cb-question-card' );
			if ( kartToggle ) {
				toggleMetniGuncelle( kartToggle );
			}
		}
		onizlemeGuncelle();
	} );

	list.addEventListener( 'input', function ( e ) {
		if ( e.target.classList.contains( 'qmo-cb-question-label' ) ) {
			var kart = e.target.closest( '.qmo-cb-question-card' );
			if ( kart ) {
				mirrorLabelToQuestion( kart );
			}
			onizlemeGuncelle();
		}
	} );

	if ( ekle ) {
		ekle.addEventListener( 'click', function () {
			var i = list.querySelectorAll( '.qmo-cb-question-card' ).length;
			var yeniId = 'n' + Date.now();
			var kart = document.createElement( 'div' );
			kart.className = 'qmo-cb-question-card';
			kart.draggable = true;
			kart.setAttribute( 'role', 'listitem' );
			kart.innerHTML =
				'<div class="qmo-cb-question-head">' +
					'<button type="button" class="qmo-cb-drag-handle" aria-label="Sürükleyerek sırala">⠿</button>' +
					'<span class="qmo-cb-question-index" aria-hidden="true">' + ( '0' + ( i + 1 ) ).slice( -2 ) + '</span>' +
					'<div class="qmo-cb-question-main">' +
						'<input type="hidden" name="qmo_chatbot_quick_replies[' + i + '][id]" value="' + yeniId + '">' +
						'<input type="text" class="qmo-cb-question-label" placeholder="Müşteriye gösterilecek soru" name="qmo_chatbot_quick_replies[' + i + '][label]">' +
					'</div>' +
					'<label class="qmo-cb-toggle">' +
						'<input type="hidden" name="qmo_chatbot_quick_replies[' + i + '][enabled]" value="0">' +
						'<input type="checkbox" name="qmo_chatbot_quick_replies[' + i + '][enabled]" value="1" checked>' +
						'<span class="qmo-cb-toggle-ui" aria-hidden="true"></span>' +
						'<span class="qmo-cb-toggle-text">Aktif</span>' +
					'</label>' +
					'<button type="button" class="button-link qmo-cb-question-edit" aria-expanded="false">Düzenle</button>' +
					'<button type="button" class="qmo-cb-question-delete" aria-label="Soruyu sil">&times;</button>' +
				'</div>' +
				'<div class="qmo-cb-question-advanced" hidden>' +
					'<label class="qmo-cb-inline-check">' +
						'<input type="checkbox" class="qmo-cb-same-question" checked>' +
						' Asistana aynı soruyu gönder' +
					'</label>' +
					'<div class="qmo-cb-question-ai" hidden>' +
						'<label>Asistana gönderilecek soru</label>' +
						'<input type="text" class="qmo-cb-question-question regular-text" name="qmo_chatbot_quick_replies[' + i + '][question]">' +
					'</div>' +
				'</div>';
			list.appendChild( kart );
			var labelInput = kart.querySelector( '.qmo-cb-question-label' );
			if ( labelInput ) {
				labelInput.focus();
			}
			onizlemeGuncelle();
		} );
	}
}() );
