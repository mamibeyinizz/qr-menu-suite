/**
 * Hazır sorular — sürükle-bırak ve satır ekleme/silme.
 */
( function () {
	'use strict';

	var tbody = document.getElementById( 'qmo-cb-soru-listesi' );
	var ekle = document.getElementById( 'qmo-cb-soru-ekle' );
	if ( ! tbody ) {
		return;
	}

	var suruklenen = null;

	function yenidenIsimlendir() {
		Array.prototype.forEach.call( tbody.querySelectorAll( '.qmo-cb-soru-satir' ), function ( tr, i ) {
			tr.querySelectorAll( 'input' ).forEach( function ( input ) {
				input.name = input.name.replace( /qmo_chatbot_quick_replies\[\d+\]/, 'qmo_chatbot_quick_replies[' + i + ']' );
			} );
		} );
	}

	tbody.addEventListener( 'dragstart', function ( e ) {
		var tr = e.target.closest( '.qmo-cb-soru-satir' );
		if ( ! tr ) {
			return;
		}
		suruklenen = tr;
		tr.style.opacity = '0.5';
	} );

	tbody.addEventListener( 'dragend', function () {
		if ( suruklenen ) {
			suruklenen.style.opacity = '';
		}
		suruklenen = null;
		yenidenIsimlendir();
	} );

	tbody.addEventListener( 'dragover', function ( e ) {
		e.preventDefault();
		var tr = e.target.closest( '.qmo-cb-soru-satir' );
		if ( ! tr || ! suruklenen || tr === suruklenen ) {
			return;
		}
		var rect = tr.getBoundingClientRect();
		var after = ( e.clientY - rect.top ) > ( rect.height / 2 );
		tbody.insertBefore( suruklenen, after ? tr.nextSibling : tr );
	} );

	tbody.addEventListener( 'click', function ( e ) {
		if ( ! e.target.classList.contains( 'qmo-cb-soru-sil' ) ) {
			return;
		}
		var tr = e.target.closest( 'tr' );
		if ( tr ) {
			tr.remove();
			yenidenIsimlendir();
		}
	} );

	if ( ekle ) {
		ekle.addEventListener( 'click', function () {
			var i = tbody.querySelectorAll( '.qmo-cb-soru-satir' ).length;
			var tr = document.createElement( 'tr' );
			tr.className = 'qmo-cb-soru-satir';
			tr.draggable = true;
			tr.innerHTML =
				'<td class="qmo-cb-drag-handle">&#8942;&#8942;</td>' +
				'<td><input type="hidden" name="qmo_chatbot_quick_replies[' + i + '][id]" value="n' + Date.now() + '">' +
				'<input type="text" name="qmo_chatbot_quick_replies[' + i + '][label]" class="regular-text"></td>' +
				'<td><input type="text" name="qmo_chatbot_quick_replies[' + i + '][question]" class="regular-text"></td>' +
				'<td><input type="hidden" name="qmo_chatbot_quick_replies[' + i + '][enabled]" value="0">' +
				'<input type="checkbox" name="qmo_chatbot_quick_replies[' + i + '][enabled]" value="1" checked></td>' +
				'<td><button type="button" class="button-link-delete qmo-cb-soru-sil">Sil</button></td>';
			tbody.appendChild( tr );
		} );
	}
}() );
