/**
 * QR Chatbot yönetim — asistan aç/kapa (AJAX), durum şeridi ve kısa kod kopyalama.
 */
( function () {
	'use strict';

	var cfg = window.qmoChatbotHub || {};

	/* Kısa kod kopyalama — Site Entegrasyonu sayfası. */
	Array.prototype.forEach.call(
		document.querySelectorAll( '.qmo-cb-kopyala' ),
		function ( btn ) {
			btn.addEventListener( 'click', function () {
				var hedef = document.querySelector( btn.getAttribute( 'data-kopyala' ) || '' );
				if ( ! hedef ) {
					return;
				}
				hedef.select();
				hedef.setSelectionRange( 0, 99999 );
				try {
					document.execCommand( 'copy' );
				} catch ( e ) {
					return;
				}
				var eski = btn.textContent;
				btn.textContent = cfg.kopyalandi || 'Kopyalandı';
				window.setTimeout( function () {
					btn.textContent = eski;
				}, 1600 );
			} );
		}
	);

	var btn = document.getElementById( 'qmo-cb-hub-switch' );
	if ( ! btn ) {
		return;
	}

	var note = document.querySelector( '.qmo-cb-master-note' );
	var wrap = document.querySelector( '.qmo-cb-hub' );
	var stateEl = btn.querySelector( '.qmo-cb-switch-state' );
	var durum = document.querySelector( '.qmo-cb-status' );
	var durumMetin = durum ? durum.querySelector( '.qmo-cb-status-text' ) : null;
	var durumNot = durum ? durum.querySelector( '.qmo-cb-status-note' ) : null;

	function durumuTazele( acik ) {
		if ( ! durum ) {
			return;
		}
		var sinif = acik ? ( durum.getAttribute( 'data-acik-sinif' ) || 'ok' ) : 'kapali';
		durum.classList.remove( 'qmo-cb-status-ok', 'qmo-cb-status-uyari', 'qmo-cb-status-kapali' );
		durum.classList.add( 'qmo-cb-status-' + sinif );
		if ( durumMetin ) {
			durumMetin.textContent = durum.getAttribute( acik ? 'data-acik-metin' : 'data-kapali-metin' ) || '';
		}
		if ( durumNot ) {
			durumNot.textContent = durum.getAttribute( acik ? 'data-acik-not' : 'data-kapali-not' ) || '';
		}
	}

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
			durumuTazele( acik );
		} );
	} );
}() );
