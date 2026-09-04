/**
 * QR Menu Suite — giriş ekranı davranışları.
 *
 * Form WordPress'in kendi formudur; burada yalnızca üç küçük iyileştirme
 * yapılır. Hiçbiri girişin ön koşulu değildir: betik yüklenmezse ekran
 * eksiksiz çalışmaya devam eder.
 *
 * 1. Caps Lock uyarısı — şifre alanında büyük harf kilidi açıkken uyarır.
 * 2. Gönderim durumu — düğme iki kez tıklanamaz, "bekleyin" metnine geçer.
 * 3. Odak — boş kullanıcı adı alanına masaüstünde odaklanır (mobilde
 *    odaklanmak klavyeyi açıp sayfayı zıplattığı için yapılmaz).
 */
( function () {
	'use strict';

	var metin = window.QRMS_LOGIN || {};

	function capsUyarisi( alan ) {
		if ( ! alan ) {
			return;
		}

		var uyari = document.createElement( 'span' );
		uyari.className = 'qrms-caps';
		uyari.textContent = metin.capsLock || 'Caps Lock';
		uyari.hidden = true;

		var kap = alan.closest( '.wp-pwd' ) || alan.parentNode;
		kap.parentNode.insertBefore( uyari, kap.nextSibling );

		alan.addEventListener( 'keyup', function ( olay ) {
			if ( typeof olay.getModifierState !== 'function' ) {
				return;
			}

			uyari.hidden = ! olay.getModifierState( 'CapsLock' );
		} );

		alan.addEventListener( 'blur', function () {
			uyari.hidden = true;
		} );
	}

	function gonderimDurumu( form ) {
		if ( ! form ) {
			return;
		}

		form.addEventListener( 'submit', function () {
			var dugme = form.querySelector( '#wp-submit' );

			if ( ! dugme || dugme.disabled ) {
				return;
			}

			// Değeri değiştirmeden önce sakla: gönderim sunucu tarafında
			// reddedilirse (boş alan) tarayıcı geri geldiğinde eski metin
			// geri gelsin.
			dugme.dataset.eski = dugme.value;
			dugme.value = metin.bekleyin || dugme.value;
			dugme.disabled = true;

			window.setTimeout( function () {
				dugme.disabled = false;
				dugme.value = dugme.dataset.eski;
			}, 8000 );
		} );
	}

	function odakla() {
		if ( window.matchMedia && window.matchMedia( '(pointer: coarse)' ).matches ) {
			return;
		}

		var kullanici = document.getElementById( 'user_login' );

		if ( kullanici && '' === kullanici.value ) {
			kullanici.focus();
		}
	}

	function baslat() {
		capsUyarisi( document.getElementById( 'user_pass' ) );
		gonderimDurumu( document.getElementById( 'loginform' ) );
		odakla();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', baslat );
	} else {
		baslat();
	}
}() );
