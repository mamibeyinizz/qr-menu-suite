/**
 * QR Menu Official — garson / hesap buton davranışı.
 *
 * Action adları (garson_cagir, hesap_iste) eski eklentiyle aynıdır.
 * Sunucu uçları Firestore yazdığı için bu göçte yok; uç gelince
 * aynı POST gövdesi çalışır.
 */
( function () {
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

	function yaz( bar, metin, hataMi ) {
		var el = bar.querySelector( '.qmo-cagri-durum' );
		if ( ! el ) {
			return;
		}
		el.hidden = false;
		el.textContent = metin;
		el.classList.toggle( 'is-hata', !! hataMi );
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-qmo-cagri]' );
		if ( ! btn ) {
			return;
		}

		var bar = btn.closest( '.qmo-cagri-bar' );
		var tip = btn.getAttribute( 'data-qmo-cagri' );
		var action = ( 'hesap' === tip ) ? 'hesap_iste' : 'garson_cagir';

		btn.disabled = true;
		istek( { action: action } ).then( function ( yanit ) {
			btn.disabled = false;
			if ( yanit && yanit.success ) {
				yaz( bar, 'hesap' === tip
					? metin( 'hesapIletildi', 'Hesap talebiniz iletildi.' )
					: metin( 'garsonIletildi', 'Garson çağrınız iletildi.' ), false );
				return;
			}
			var mesaj = metin( 'istekIletilemedi', 'İstek iletilemedi, lütfen tekrar deneyin.' );
			if ( yanit && yanit.data ) {
				if ( typeof yanit.data === 'string' ) {
					mesaj = yanit.data;
				} else if ( yanit.data.mesaj ) {
					mesaj = yanit.data.mesaj;
				}
			}
			yaz( bar, mesaj, true );
		} ).catch( function () {
			btn.disabled = false;
			yaz( bar, metin( 'baglantiHatasi', 'Bağlantı hatası oluştu.' ), true );
		} );
	} );
}() );
