/**
 * QR Menu Suite — Giriş Ekranı ayar sekmesi.
 *
 * Alanlar değiştikçe önizlemeyi günceller. Önizleme, giriş ekranının gerçek
 * stylesheet'ini kullandığı için burada yapılan tek iş CSS değişkenlerini ve
 * sınıfları taşımaktır; hiçbir görsel kural bu dosyada tekrarlanmaz.
 *
 * Betik çalışmasa da form eksiksiz çalışır: yalnızca önizleme kaydedilene
 * kadar güncellenmez.
 */
( function ( $ ) {
	'use strict';

	var metin = window.QRMS_LOGIN_ADMIN || {};

	$( function () {

		var $onizleme = $( '#qrms-lp' );
		var $cerceve  = $( '.qrms-onizleme-cerceve' );

		if ( ! $onizleme.length ) {
			return;
		}

		var kok = $onizleme[ 0 ];

		/* ---------------------------------------------------------------
		   Yardımcılar
		--------------------------------------------------------------- */

		function degiskenYaz( ad, deger ) {
			kok.style.setProperty( ad, deger );
		}

		/**
		 * Verilen ön ekle başlayan tüm sınıfları söküp yenisini takar.
		 *
		 * @param {string} onEk  Sınıf ön eki (ör. "qrms-login-tema-").
		 * @param {string} deger Yeni değer.
		 */
		function sinifDegistir( onEk, deger ) {
			var kalan = [];

			kok.className.split( /\s+/ ).forEach( function ( sinif ) {
				if ( sinif && 0 !== sinif.indexOf( onEk ) ) {
					kalan.push( sinif );
				}
			} );

			kalan.push( onEk + deger );
			kok.className = kalan.join( ' ' );
		}

		/* ---------------------------------------------------------------
		   Renk seçiciler
		--------------------------------------------------------------- */

		$( '.qrms-renk' ).each( function () {
			var $alan = $( this );
			var deg   = $alan.data( 'onizleme-var' );

			$alan.wpColorPicker( {
				change: function ( olay, ui ) {
					if ( deg ) {
						degiskenYaz( deg, ui.color.toString() );
					}
				},
				clear: function () {
					if ( deg ) {
						degiskenYaz( deg, '' );
					}
				}
			} );
		} );

		/* ---------------------------------------------------------------
		   Kaydırıcılar (px / oran)
		--------------------------------------------------------------- */

		$( 'input[type="range"][data-onizleme-var]' ).on( 'input change', function () {
			var $alan = $( this );
			var deger = parseInt( $alan.val(), 10 );
			var birim = $alan.data( 'birim' );
			var deg   = $alan.data( 'onizleme-var' );

			if ( 'oran' === birim ) {
				degiskenYaz( deg, ( deger / 100 ).toString() );
				$( '.qrms-deger[data-icin="' + $alan.attr( 'id' ) + '"]' ).text( deger + '%' );
			} else {
				degiskenYaz( deg, deger + 'px' );
				$( '.qrms-deger[data-icin="' + $alan.attr( 'id' ) + '"]' ).text( deger + 'px' );
			}
		} );

		/* ---------------------------------------------------------------
		   Düzen / tema / arka plan tipi
		--------------------------------------------------------------- */

		$( 'input[type="radio"][data-onizleme]' ).on( 'change', function () {
			var tur   = $( this ).data( 'onizleme' );
			var deger = $( this ).val();

			if ( 'duzen' === tur ) {
				sinifDegistir( 'qrms-login-duzen-', deger );
			} else if ( 'tema' === tur ) {
				sinifDegistir( 'qrms-login-tema-', deger );
			} else if ( 'arkaplan_tip' === tur ) {
				sinifDegistir( 'qrms-login-bg-', deger );
			}
		} );

		/* ---------------------------------------------------------------
		   Sınıf açan/kapatan onay kutuları
		--------------------------------------------------------------- */

		$( 'input[type="checkbox"][data-onizleme-sinif]' ).on( 'change', function () {
			var $kutu  = $( this );
			var sinif  = $kutu.data( 'onizleme-sinif' );
			var acik   = $kutu.is( ':checked' );

			// data-ters: kutu İŞARETLİYKEN sınıf KALKAR (gizleme sınıfları).
			if ( $kutu.data( 'ters' ) ) {
				acik = ! acik;
			}

			$onizleme.toggleClass( sinif, acik );
		} );

		/* ---------------------------------------------------------------
		   Metin alanları
		--------------------------------------------------------------- */

		$( '[data-onizleme-metin]' ).on( 'input', function () {
			var $alan   = $( this );
			var $hedef  = $onizleme.find( $alan.data( 'onizleme-metin' ) );
			var deger   = $alan.val();
			var yedek   = $alan.attr( 'placeholder' ) || '';

			$hedef.text( '' !== deger ? deger : yedek );
		} );

		/* ---------------------------------------------------------------
		   Medya seçici
		--------------------------------------------------------------- */

		$( '.qrms-medya' ).each( function () {
			var $kap    = $( this );
			var $gizli  = $kap.find( 'input[type="hidden"]' );
			var $gorsel = $kap.find( '.qrms-medya-onizleme' );
			var deg     = $gizli.data( 'onizleme-var' );
			var tur     = $kap.data( 'medya' );
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
						degiskenYaz( deg, 'url(' + ek.url + ')' );

						if ( 'logo' === tur ) {
							$onizleme.addClass( 'qrms-login-logolu' );
						}
					} );
				}

				kutu.open();
			} );

			$kap.on( 'click', '.qrms-medya-sil', function ( olay ) {
				olay.preventDefault();

				$gizli.val( 0 );
				$gorsel.empty();
				degiskenYaz( deg, 'none' );

				if ( 'logo' === tur ) {
					$onizleme.removeClass( 'qrms-login-logolu' );
				}
			} );
		} );

		/* ---------------------------------------------------------------
		   Cihaz düğmeleri
		--------------------------------------------------------------- */

		$( '.qrms-cihaz' ).on( 'click', function () {
			var cihaz = $( this ).data( 'cihaz' );

			$( '.qrms-cihaz' ).removeClass( 'aktif' );
			$( this ).addClass( 'aktif' );

			$cerceve.attr( 'data-cihaz', cihaz );
			$onizleme.toggleClass( 'qrms-lp-mobil', 'mobil' === cihaz );
		} );

		/* ---------------------------------------------------------------
		   Adres kopyalama
		--------------------------------------------------------------- */

		$( '.qrms-kopyala' ).on( 'click', function () {
			var $dugme = $( this );
			var kaynak = document.getElementById( $dugme.data( 'hedef' ) );

			if ( ! kaynak ) {
				return;
			}

			var bitir = function () {
				var eski = $dugme.text();
				$dugme.text( metin.kopyalandi || eski );
				window.setTimeout( function () {
					$dugme.text( eski );
				}, 1600 );
			};

			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( kaynak.textContent ).then( bitir );
				return;
			}

			// Güvenli olmayan bağlamda (http) panoya yazma engellidir; metni
			// seçili bırakmak kullanıcıya elle kopyalama imkânı verir.
			var aralik = document.createRange();
			aralik.selectNodeContents( kaynak );
			window.getSelection().removeAllRanges();
			window.getSelection().addRange( aralik );
		} );
	} );
}( jQuery ) );
