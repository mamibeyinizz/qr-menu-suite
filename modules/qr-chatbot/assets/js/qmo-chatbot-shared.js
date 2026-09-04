/**
 * QR Menu Official — chatbot / buton ortak fetch ve i18n.
 *
 * chatbot.js ve buttons.js bu dosyaya bağımlıdır; qmoChatShared üzerinden
 * aynı istek() ve metin() imzasını kullanır.
 */
( function ( global ) {
	'use strict';

	function istek( veri ) {
		if ( typeof qmoData === 'undefined' ) {
			return Promise.reject();
		}

		var govde = new URLSearchParams();
		govde.append( 'nonce', qmoData.nonce );
		Object.keys( veri ).forEach( function ( k ) {
			govde.append( k, veri[ k ] );
		} );

		return fetch( qmoData.ajaxUrl, {
			method: 'POST',
			body: govde,
			credentials: 'same-origin'
		} ).then( function ( r ) {
			return r.json().catch( function () {
				return { success: false };
			} );
		} );
	}

	function metin( anahtar, yedek ) {
		if ( typeof qmoData === 'undefined' || ! qmoData.i18n ) {
			return yedek;
		}
		var v = qmoData.i18n[ anahtar ];
		return ( 'string' === typeof v && '' !== v ) ? v : yedek;
	}

	global.qmoChatShared = {
		istek: istek,
		metin: metin
	};
}( window ) );
