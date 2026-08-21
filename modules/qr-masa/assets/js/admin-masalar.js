/**
 * QR Menü → Masalar ekranı.
 * QR kod ve PDF üretimi tarayıcıda yapılır; sunucuya yük binmez.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var satirlar = document.querySelectorAll( '.qmo-row' );

		satirlar.forEach( function ( row ) {
			var url = row.getAttribute( 'data-url' );
			var ad  = row.getAttribute( 'data-name' ) || 'masa';

			if ( 'undefined' === typeof window.QRious ) {
				return;
			}

			var qr = new window.QRious( { value: url, size: 800, level: 'H' } );
			var dataUri = qr.toDataURL( 'image/png' );

			var onizleme = row.querySelector( '.qmo-qr-preview' );
			if ( onizleme ) {
				onizleme.src = dataUri;
			}

			var dosyaAdi = ad.replace( /\s+/g, '-' );

			row.querySelector( '.qmo-dl-png' ).addEventListener( 'click', function () {
				var a = document.createElement( 'a' );
				a.download = dosyaAdi + '-QR.png';
				a.href = dataUri;
				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );
			} );

			row.querySelector( '.qmo-dl-pdf' ).addEventListener( 'click', function () {
				if ( 'undefined' === typeof window.jspdf ) {
					window.alert( 'PDF kütüphanesi yükleniyor, lütfen 1 saniye sonra tekrar deneyin.' );
					return;
				}

				var jsPDF = window.jspdf.jsPDF;
				var doc = new jsPDF();

				doc.setFontSize( 40 );
				doc.text( ad, 105, 40, { align: 'center' } );
				doc.addImage( dataUri, 'PNG', 35, 60, 140, 140 );
				doc.setFontSize( 14 );
				doc.text( 'Lutfen kameraniza okutunuz', 105, 220, { align: 'center' } );

				doc.save( dosyaAdi + '-QR.pdf' );
			} );
		} );
	} );
}() );

/**
 * Toplu oluşturma önizlemesi ve grup filtresi.
 *
 * İkisi de tamamen sayfa içidir: filtreleme satırları göster/gizle yapar,
 * sunucuya istek gitmez.
 */
( function () {
	'use strict';

	/**
	 * "Ic Masa" -> "ic-masa": slug önizlemesi için kaba bir sadeleştirme.
	 * Sunucudaki sanitize_title() son sözü söyler; bu yalnızca kullanıcıya
	 * ne oluşacağını göstermek içindir.
	 */
	function slugla( ham ) {
		return String( ham )
			.toLowerCase()
			.replace( /ı/g, 'i' ).replace( /ğ/g, 'g' ).replace( /ü/g, 'u' )
			.replace( /ş/g, 's' ).replace( /ö/g, 'o' ).replace( /ç/g, 'c' )
			.replace( /[^a-z0-9]+/g, '-' )
			.replace( /^-+|-+$/g, '' );
	}

	function toplukurulum() {
		var onek = document.getElementById( 'qmo-onek' );
		var bas  = document.getElementById( 'qmo-bas' );
		var bit  = document.getElementById( 'qmo-bit' );
		var kutu = document.getElementById( 'qmo-onizleme' );

		if ( ! onek || ! bas || ! bit || ! kutu ) {
			return;
		}

		function tazele() {
			var s = slugla( onek.value );
			var b = parseInt( bas.value, 10 );
			var e = parseInt( bit.value, 10 );

			if ( ! s || isNaN( b ) || isNaN( e ) || b < 1 || e < b ) {
				kutu.textContent = '';
				return;
			}

			var adet = ( e - b ) + 1;

			kutu.textContent = adet <= 2
				? s + '-' + b + ( adet === 2 ? ', ' + s + '-' + e : '' ) + ' → ' + adet + ' masa'
				: s + '-' + b + ', ' + s + '-' + ( b + 1 ) + ', … ' + s + '-' + e + ' → ' + adet + ' masa';
		}

		[ onek, bas, bit ].forEach( function ( alan ) {
			alan.addEventListener( 'input', tazele );
		} );

		tazele();
	}

	function filtrekurulum() {
		var cipler = document.querySelectorAll( '.qmo-chip' );

		if ( ! cipler.length ) {
			return;
		}

		var satirlar = document.querySelectorAll( '.qmo-masa-tablo .qmo-row' );
		var bosSatir = document.querySelector( '.qmo-bos-filtre' );

		cipler.forEach( function ( cip ) {
			cip.addEventListener( 'click', function () {
				var grup = cip.getAttribute( 'data-grup' ) || '';
				var gorunen = 0;

				cipler.forEach( function ( d ) {
					d.classList.toggle( 'is-active', d === cip );
				} );

				satirlar.forEach( function ( satir ) {
					var uyar = ( '' === grup || satir.getAttribute( 'data-grup' ) === grup );
					satir.hidden = ! uyar;

					if ( uyar ) {
						gorunen++;
					}
				} );

				if ( bosSatir ) {
					bosSatir.hidden = gorunen > 0;
				}
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		toplukurulum();
		filtrekurulum();
	} );
}() );
