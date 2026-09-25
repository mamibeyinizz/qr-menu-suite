/**
 * QR Menu Suite — Restoran Markası ayar sekmesi.
 */
( function ( $ ) {
	'use strict';

	var metin = window.QRMS_BRAND_ADMIN || {};

	$( function () {
		var $onizleme = $( '#qrms-marka-onizleme' );

		if ( ! $onizleme.length ) {
			return;
		}

		var $logoKap = $onizleme.find( '[data-onizleme-logo]' );
		var $yedek   = $onizleme.find( '[data-onizleme-yedek]' );
		var $marka   = $onizleme.find( '[data-onizleme-kok]' );

		function logoGoster( url ) {
			if ( url ) {
				$logoKap.removeAttr( 'hidden' ).html( '<img src="' + url + '" alt="">' );
				$yedek.attr( 'hidden', 'hidden' );
				$marka.addClass( 'qrms-marka-onizleme-marka--logolu' );
				$onizleme.find( '.qrms-marka-onizleme-metin' ).toggle(
					$( '#qrms-marka-ad' ).val() !== '' || $( '#qrms-marka-alt-ad' ).val() !== ''
				);
			} else {
				$logoKap.attr( 'hidden', 'hidden' ).empty();
				$yedek.removeAttr( 'hidden' );
				$marka.removeClass( 'qrms-marka-onizleme-marka--logolu' );
				$onizleme.find( '.qrms-marka-onizleme-metin' ).show();
			}
		}

		$( '[data-onizleme-metin]' ).on( 'input', function () {
			var $alan  = $( this );
			var $hedef = $onizleme.find( $alan.data( 'onizleme-metin' ) );
			var deger  = $alan.val();
			var yedek  = '';

			if ( 'qrms-marka-ad' === $alan.attr( 'id' ) ) {
				yedek = 'QR MENU';
			} else if ( 'qrms-marka-alt-ad' === $alan.attr( 'id' ) ) {
				yedek = 'OFFICIAL';
			}

			$hedef.text( '' !== deger ? deger : yedek );

			if ( $marka.hasClass( 'qrms-marka-onizleme-marka--logolu' ) ) {
				$onizleme.find( '.qrms-marka-onizleme-metin' ).toggle(
					$( '#qrms-marka-ad' ).val() !== '' || $( '#qrms-marka-alt-ad' ).val() !== ''
				);
			}
		} );

		$( '.qrms-medya' ).each( function () {
			var $kap    = $( this );
			var $gizli  = $kap.find( 'input[type="hidden"]' );
			var $gorsel = $kap.find( '.qrms-medya-onizleme' );
			var kutu;

			$kap.on( 'click', '.qrms-medya-sec', function ( olay ) {
				olay.preventDefault();

				if ( ! kutu ) {
					kutu = wp.media( {
						title: metin.sec || '',
						button: { text: metin.kullan || '' },
						library: { type: 'image' },
						multiple: false
					} );

					kutu.on( 'select', function () {
						var ek = kutu.state().get( 'selection' ).first().toJSON();

						$gizli.val( ek.id );
						$gorsel.html( '<img src="' + ek.url + '" alt="">' );
						logoGoster( ek.url );
					} );
				}

				kutu.open();
			} );

			$kap.on( 'click', '.qrms-medya-sil', function ( olay ) {
				olay.preventDefault();

				$gizli.val( 0 );
				$gorsel.empty();
				logoGoster( '' );
			} );
		} );

		if ( $marka.hasClass( 'qrms-marka-onizleme-marka--logolu' ) || $logoKap.find( 'img' ).length ) {
			$marka.addClass( 'qrms-marka-onizleme-marka--logolu' );
			if ( $marka.hasClass( 'qrms-marka-onizleme-marka--logolu' ) ) {
				$onizleme.find( '.qrms-marka-onizleme-metin' ).toggle(
					$( '#qrms-marka-ad' ).val() !== '' || $( '#qrms-marka-alt-ad' ).val() !== ''
				);
			}
		}
	} );
}( jQuery ) );
