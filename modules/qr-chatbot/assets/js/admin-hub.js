/**
 * QR Chatbot hub — asistan aç/kapa (AJAX).
 */
( function () {
	'use strict';

	var btn = document.getElementById( 'qmo-cb-hub-switch' );
	if ( ! btn ) {
		return;
	}

	var cfg = window.qmoChatbotHub || {};
	var note = document.querySelector( '.qmo-cb-master-note' );
	var wrap = document.querySelector( '.rma-hub' );
	var stateEl = btn.querySelector( '.qmo-cb-switch-state' );

	btn.addEventListener( 'click', function () {
		var acik = 'true' !== btn.getAttribute( 'aria-pressed' );
		var govde = new URLSearchParams();
		govde.append( 'action', 'qmo_chatbot_toggle' );
		govde.append( 'nonce', btn.getAttribute( 'data-nonce' ) || '' );
		govde.append( 'aktif', acik ? 'yes' : 'no' );

		fetch( cfg.ajaxUrl || ajaxurl, {
			method: 'POST',
			body: govde,
			credentials: 'same-origin'
		} ).then( function ( r ) {
			return r.json();
		} ).then( function ( yanit ) {
			if ( ! yanit || ! yanit.success ) {
				return;
			}
			btn.setAttribute( 'aria-pressed', acik ? 'true' : 'false' );
			if ( stateEl ) {
				stateEl.textContent = acik ? ( cfg.acik || 'Açık' ) : ( cfg.kapali || 'Kapalı' );
			}
			if ( note ) {
				note.hidden = acik;
			}
			if ( wrap ) {
				wrap.classList.toggle( 'qmo-cb-hub-kapali', ! acik );
			}
		} );
	} );
}() );
