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
