/**
 * QR Menu Suite — admin script.
 *
 * Tek işi var: lisans formu gönderilirken (senkron istek 15 saniye
 * sürebilir) butonu kilitleyip kullanıcıya beklediğini göstermek.
 * Sayfaların hiçbir işlevi JavaScript'e bağlı değildir.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var forms = document.querySelectorAll( '.qrms-form' );

		Array.prototype.forEach.call( forms, function ( form ) {
			form.addEventListener( 'submit', function () {
				var button = form.querySelector( 'button[type="submit"]' );

				if ( ! button ) {
					return;
				}

				// Butonun value'su POST ile gitsin diye devre dışı bırakmak
				// yerine sadece tekrar tıklamayı engelliyoruz.
				button.setAttribute( 'aria-disabled', 'true' );
				button.classList.add( 'qrms-is-busy' );

				if ( window.qrmsAdmin && window.qrmsAdmin.validating ) {
					button.textContent = window.qrmsAdmin.validating;
				}
			} );
		} );
	} );
}() );

/**
 * Kısa Kodlar rehberindeki "Kopyala" butonları.
 *
 * Panoya yazma modern tarayıcılarda navigator.clipboard ile yapılır; güvenli
 * olmayan bağlamda (http üzerinden çalışan bir admin) o API tanımsız olduğu
 * için gizli bir alan + execCommand yedeği kullanılır.
 */
( function () {
	'use strict';

	function panoyaYaz( metin ) {
		if ( window.navigator && window.navigator.clipboard && window.isSecureContext ) {
			return window.navigator.clipboard.writeText( metin );
		}

		return new Promise( function ( basarili, basarisiz ) {
			var alan = document.createElement( 'textarea' );

			alan.value = metin;
			alan.setAttribute( 'readonly', '' );
			alan.style.position = 'fixed';
			alan.style.top = '-1000px';
			document.body.appendChild( alan );
			alan.select();

			try {
				document.execCommand( 'copy' ) ? basarili() : basarisiz();
			} catch ( e ) {
				basarisiz( e );
			}

			document.body.removeChild( alan );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var butonlar = document.querySelectorAll( '[data-qrms-copy]' );

		Array.prototype.forEach.call( butonlar, function ( buton ) {
			var etiket = buton.querySelector( '.qrms-sc-copy-text' );
			var ilkMetin = etiket ? etiket.textContent : '';
			var zamanlayici = null;

			buton.addEventListener( 'click', function () {
				panoyaYaz( buton.getAttribute( 'data-qrms-copy' ) ).then(
					function () {
						buton.classList.add( 'is-copied' );

						if ( etiket ) {
							etiket.textContent = 'Kopyalandı';
						}

						window.clearTimeout( zamanlayici );
						zamanlayici = window.setTimeout( function () {
							buton.classList.remove( 'is-copied' );

							if ( etiket ) {
								etiket.textContent = ilkMetin;
							}
						}, 1600 );
					},
					function () {
						// Pano erişimi engellendiyse kullanıcı kodu elle seçebilsin.
						var kod = buton.parentNode.querySelector( '.qrms-sc-tag' );

						if ( kod && window.getSelection ) {
							var aralik = document.createRange();
							aralik.selectNodeContents( kod );
							window.getSelection().removeAllRanges();
							window.getSelection().addRange( aralik );
						}
					}
				);
			} );
		} );
	} );
}() );
