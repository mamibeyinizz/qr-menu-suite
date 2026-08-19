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
