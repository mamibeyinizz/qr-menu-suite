/**
 * QR Menü — önbellek dayanıklı oturum/nonce tazeleme.
 *
 * Sayfa önbellekten geldiyse (qmoTazeCfg.t eşikten eski) ya da adreste
 * `masa` varken sayfa taze üretilmemişse, admin-ajax `qmo_taze_anahtar`
 * ucundan masa oturumunu açar ve güncel nonce'ları sayfadaki global
 * yapılandırmalara yazar. Taze sayfada hiçbir istek atılmaz.
 *
 * Diğer betikler aksiyondan önce `window.qmoTazeHazir` (Promise) bekler.
 * Ham yanıt `window.qmoTazeVeri` içinde tutulur (kapanış içindeki yerel
 * yapılandırmalar gönderim anında buradan okur).
 */
( function ( w, d ) {
	'use strict';

	var C = w.qmoTazeCfg || {};
	var masa = '';
	try {
		masa = new URLSearchParams( w.location.search ).get( 'masa' ) || '';
	} catch ( e ) {}

	var yas   = Math.floor( Date.now() / 1000 ) - ( parseInt( C.t, 10 ) || 0 );
	var esik  = parseInt( C.esik, 10 ) || 600;
	// Adreste masa varken sayfa normalde önbelleğe girmez (yaş ~0); 30 sn'den
	// eskiyse başka bir katman (CDN vb.) önbelleklemiştir ve çerez yazılmamıştır.
	var gerek = !! C.ajaxUrl && ( yas > esik || ( '' !== masa && yas > 30 ) );

	if ( ! gerek || ! w.fetch || ! w.Promise ) {
		w.qmoTazeHazir = w.Promise ? Promise.resolve( false ) : null;
		return;
	}

	var domHazir = new Promise( function ( coz ) {
		if ( 'loading' !== d.readyState ) {
			coz();
		} else {
			d.addEventListener( 'DOMContentLoaded', coz );
		}
	} );

	function alanlariYaz( ad, deger ) {
		if ( ! deger ) {
			return;
		}
		var el = d.querySelectorAll( 'input[name="' + ad + '"]' );
		for ( var i = 0; i < el.length; i++ ) {
			el[ i ].value = deger;
		}
	}

	function uygula( v ) {
		w.qmoTazeVeri = v;

		if ( w.qmoData ) {
			w.qmoData.nonce     = v.qmo || w.qmoData.nonce;
			w.qmoData.restNonce = v.rest || w.qmoData.restNonce;
		}
		if ( w.qmoSepet ) {
			w.qmoSepet.nonce     = v.qmo || w.qmoSepet.nonce;
			w.qmoSepet.restNonce = v.rest || w.qmoSepet.restNonce;
		}
		if ( v.rma ) {
			if ( 'undefined' !== typeof w.NONCE ) {
				w.NONCE = v.rma;
			}
			if ( w.RMA_MODAL_CFG ) {
				w.RMA_MODAL_CFG.nonce = v.rma;
			}
		}
		if ( v.onyuz && w.qrmsAnalitikOnyuzCfg ) {
			w.qrmsAnalitikOnyuzCfg.nonce = v.onyuz;
		}

		alanlariYaz( 'qrm_ts', v.yorumTs );
		alanlariYaz( 'qrm_review_nonce', v.yorumNonce );
		alanlariYaz( 'qrm_cf_nonce', v.formNonce );
	}

	var govde = new URLSearchParams();
	govde.append( 'action', 'qmo_taze_anahtar' );
	if ( masa ) {
		govde.append( 'masa', masa );
	}

	var istek = fetch( C.ajaxUrl, {
		method: 'POST',
		credentials: 'same-origin',
		cache: 'no-store',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
		body: govde.toString()
	} ).then( function ( r ) {
		return r.json();
	} ).catch( function () {
		return null;
	} );

	w.qmoTazeHazir = Promise.all( [ istek, domHazir ] ).then( function ( s ) {
		var res = s[ 0 ];
		if ( ! res || ! res.success || ! res.data ) {
			return false;
		}
		uygula( res.data );
		return true;
	} );
}( window, document ) );
