/**
 * Sohbet geçmişi ve cevaplanamayan sorular.
 */
( function () {
	'use strict';

	var cfg = window.qmoChatbotYonetim || {};

	function istek( action, nonce, veri ) {
		var govde = new URLSearchParams();
		govde.append( 'action', action );
		govde.append( 'nonce', nonce );
		Object.keys( veri || {} ).forEach( function ( k ) {
			if ( Array.isArray( veri[ k ] ) ) {
				veri[ k ].forEach( function ( v ) {
					govde.append( k + '[]', v );
				} );
			} else {
				govde.append( k, veri[ k ] );
			}
		} );
		return fetch( cfg.ajaxUrl || ajaxurl, {
			method: 'POST',
			body: govde,
			credentials: 'same-origin'
		} ).then( function ( r ) {
			return r.json();
		} );
	}

	var hepsi = document.getElementById( 'qmo-cb-gecmis-hepsi' );
	if ( hepsi ) {
		hepsi.addEventListener( 'change', function () {
			document.querySelectorAll( '.qmo-cb-gecmis-sec' ).forEach( function ( c ) {
				c.checked = hepsi.checked;
			} );
		} );
	}

	var toplu = document.getElementById( 'qmo-cb-toplu-sil' );
	if ( toplu ) {
		toplu.addEventListener( 'click', function () {
			var ids = [];
			document.querySelectorAll( '.qmo-cb-gecmis-sec:checked' ).forEach( function ( c ) {
				ids.push( c.value );
			} );
			if ( ! ids.length ) {
				return;
			}
			istek( 'qmo_chatbot_gecmis_toplu_sil', cfg.nonceG, { ids: ids } ).then( function ( y ) {
				if ( y && y.success ) {
					window.location.reload();
				}
			} );
		} );
	}

	document.querySelectorAll( '.qmo-cb-tek-sil' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			istek( 'qmo_chatbot_gecmis_sil', cfg.nonceG, { id: btn.getAttribute( 'data-id' ) } ).then( function ( y ) {
				if ( y && y.success ) {
					var tr = btn.closest( 'tr' );
					if ( tr ) {
						tr.remove();
					}
				}
			} );
		} );
	} );

	var modal = document.getElementById( 'qmo-cb-oturum-modal' );
	var govde = document.getElementById( 'qmo-cb-oturum-govde' );
	document.querySelectorAll( '.qmo-cb-gecmis-satir' ).forEach( function ( tr ) {
		tr.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( 'input, button' ) ) {
				return;
			}
			var oturum = tr.getAttribute( 'data-oturum' );
			if ( ! oturum || ! modal || ! govde ) {
				return;
			}
			istek( 'qmo_chatbot_oturum_getir', cfg.nonceG, { oturum_id: oturum } ).then( function ( y ) {
				if ( ! y || ! y.success ) {
					return;
				}
				govde.innerHTML = '';
				( y.data.satirlar || [] ).forEach( function ( s ) {
					var q = document.createElement( 'p' );
					q.innerHTML = '<strong>Ziyaretçi:</strong> ';
					q.appendChild( document.createTextNode( s.soru ) );
					var a = document.createElement( 'p' );
					a.innerHTML = '<strong>Asistan:</strong> ';
					a.appendChild( document.createTextNode( s.cevap ) );
					govde.appendChild( q );
					govde.appendChild( a );
				} );
				modal.hidden = false;
			} );
		} );
	} );

	if ( modal ) {
		var kapat = modal.querySelector( '.qmo-cb-modal-kapat' );
		if ( kapat ) {
			kapat.addEventListener( 'click', function () {
				modal.hidden = true;
			} );
		}
		modal.addEventListener( 'click', function ( e ) {
			if ( e.target === modal ) {
				modal.hidden = true;
			}
		} );
	}

	document.querySelectorAll( '.qmo-cb-coz' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			istek( 'qmo_chatbot_bilinmeyen_coz', cfg.nonceB, { id: btn.getAttribute( 'data-id' ) } ).then( function ( y ) {
				if ( y && y.success ) {
					var tr = btn.closest( 'tr' );
					if ( tr ) {
						tr.remove();
					}
				}
			} );
		} );
	} );

	document.querySelectorAll( '.qmo-cb-soruya-ekle' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			istek( 'qmo_chatbot_bilinmeyen_soruya', cfg.nonceB, { id: btn.getAttribute( 'data-id' ) } ).then( function ( y ) {
				if ( y && y.success ) {
					var tr = btn.closest( 'tr' );
					if ( tr ) {
						tr.remove();
					}
				}
			} );
		} );
	} );
}() );
