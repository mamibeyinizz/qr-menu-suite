/**
 * QR Menü → Masalar ekranı.
 * QR kod ve PDF üretimi tarayıcıda yapılır; sunucuya yük binmez.
 */
( function () {
	'use strict';

	/**
	 * QRious CDN betiği bazı sitelerde (performans eklentisinin script'leri
	 * ertelemesi/async yapması, yavaş CDN, ağ hatası) DOMContentLoaded anında
	 * henüz hazır olmayabilir. Eskiden bu durumda satır işleme fonksiyonu
	 * sessizce çıkıyordu: QR önizlemesi hiç basılmıyor, PNG/PDF butonlarına
	 * tıklama dinleyicisi HİÇ EKLENMİYORDU — kütüphane bir an sonra yüklense
	 * bile o satır kalıcı olarak ölü kalıyordu (sayfa yenilenmeden düzelmezdi).
	 * Şimdi kütüphane hazır olana kadar kısa aralıklarla yeniden denenir;
	 * makul bir süre sonra hâlâ yoksa (CDN engellendi vb.) önizleme hücresinde
	 * görünür bir uyarı gösterilir.
	 *
	 * @param {function(boolean):void} cb Hazır olunca true, zaman aşımında false.
	 */
	function qriousBekle( cb ) {
		var deneme = 0;
		var azami  = 40; // 150ms * 40 ≈ 6 saniye.

		( function dene() {
			if ( 'undefined' !== typeof window.QRious ) {
				cb( true );
				return;
			}
			deneme++;
			if ( deneme >= azami ) {
				cb( false );
				return;
			}
			setTimeout( dene, 150 );
		}() );
	}

	/**
	 * Bir masa QR'ının PDF sayfası: üstte ortalı masa adı, altta kod.
	 * Hem tekli "PDF indir" butonu hem "Tümünü Yazdır" aynı yerleşimi kullanır
	 * ki iki çıktı birbirinden farklı görünmesin.
	 *
	 * @param {jsPDF}  doc     Sayfanın çizileceği jsPDF belgesi (zaten bir sayfa açık olmalı).
	 * @param {string} ad      Masa adı.
	 * @param {string} dataUri QR kodunun PNG data URI'si.
	 */
	function masaPdfSayfasiCiz( doc, ad, dataUri ) {
		doc.setFontSize( 40 );
		doc.text( ad, 105, 40, { align: 'center' } );
		doc.addImage( dataUri, 'PNG', 35, 60, 140, 140 );
		doc.setFontSize( 14 );
		doc.text( 'Lutfen kameraniza okutunuz', 105, 220, { align: 'center' } );
	}

	/**
	 * QR kodunun üstüne, ortalı hizada masa adını basan bir PNG üretir.
	 *
	 * Ham QRious çıktısı yalnızca kodun kendisidir — masaya yapıştırılacak bir
	 * görsel için ad da üstünde basılı olmalı, aksi hâlde tek başına bir
	 * kare kod hangi masaya ait olduğunu söylemez. Uzun adlar canvas
	 * genişliğini taşmasın diye yazı boyutu genişliğe göre küçültülür.
	 *
	 * @param {string}                  ad        Masa adı.
	 * @param {string}                  qrDataUri Ham QR PNG data URI'si.
	 * @param {function(string):void}   cb        Etiketli PNG data URI'siyle çağrılır.
	 */
	function etiketliPngUret( ad, qrDataUri, cb ) {
		var img = new Image();
		img.onload = function () {
			var qrBoyut = img.naturalWidth || 800;
			var kenar   = Math.round( qrBoyut * 0.05 );
			var ustAlan = Math.round( qrBoyut * 0.16 );

			var canvas = document.createElement( 'canvas' );
			canvas.width  = qrBoyut + ( kenar * 2 );
			canvas.height = qrBoyut + ustAlan + kenar;

			var ctx = canvas.getContext( '2d' );
			ctx.fillStyle = '#ffffff';
			ctx.fillRect( 0, 0, canvas.width, canvas.height );

			var maxGenislik = canvas.width - ( kenar * 2 );
			var fontBoyut   = Math.round( qrBoyut * 0.09 );

			ctx.textAlign    = 'center';
			ctx.textBaseline = 'middle';
			ctx.fillStyle    = '#1d2327';

			do {
				ctx.font = 'bold ' + fontBoyut + 'px Arial, sans-serif';
				fontBoyut -= 2;
			} while ( ctx.measureText( ad ).width > maxGenislik && fontBoyut > 16 );

			ctx.fillText( ad, canvas.width / 2, ustAlan / 2 );
			ctx.drawImage( img, kenar, ustAlan, qrBoyut, qrBoyut );

			cb( canvas.toDataURL( 'image/png' ) );
		};
		img.src = qrDataUri;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var satirlar    = document.querySelectorAll( '.qmo-row' );
		var toplamSatir = satirlar.length;
		var hazirVeri   = []; // { ad, dataUri } — "Tümünü Yazdır" bunu kullanır.
		var yazdirBtn   = document.getElementById( 'qmo-tumunu-yazdir' );

		satirlar.forEach( function ( row ) {
			var url      = row.getAttribute( 'data-url' );
			var ad       = row.getAttribute( 'data-name' ) || 'masa';
			var onizleme = row.querySelector( '.qmo-qr-preview' );

			qriousBekle( function ( hazir ) {
				if ( ! hazir ) {
					if ( onizleme ) {
						onizleme.alt = 'QR kod üretilemedi — sayfayı yenileyin';
					}
					return;
				}

				var qr = new window.QRious( { value: url, size: 800, level: 'H' } );
				var dataUri = qr.toDataURL( 'image/png' );

				if ( onizleme ) {
					onizleme.src = dataUri;
				}

				hazirVeri.push( { ad: ad, dataUri: dataUri } );

				var dosyaAdi = ad.replace( /\s+/g, '-' );

				var pngBtn = row.querySelector( '.qmo-dl-png' );
				if ( pngBtn ) {
					pngBtn.addEventListener( 'click', function () {
						etiketliPngUret( ad, dataUri, function ( etiketliDataUri ) {
							var a = document.createElement( 'a' );
							a.download = dosyaAdi + '-QR.png';
							a.href = etiketliDataUri;
							document.body.appendChild( a );
							a.click();
							document.body.removeChild( a );
						} );
					} );
				}

				var pdfBtn = row.querySelector( '.qmo-dl-pdf' );
				if ( pdfBtn ) {
					pdfBtn.addEventListener( 'click', function () {
						if ( 'undefined' === typeof window.jspdf ) {
							window.alert( 'PDF kütüphanesi yükleniyor, lütfen 1 saniye sonra tekrar deneyin.' );
							return;
						}

						var doc = new window.jspdf.jsPDF();
						masaPdfSayfasiCiz( doc, ad, dataUri );
						doc.save( dosyaAdi + '-QR.pdf' );
					} );
				}
			} );
		} );

		if ( yazdirBtn ) {
			yazdirBtn.addEventListener( 'click', function () {
				if ( 'undefined' === typeof window.jspdf ) {
					window.alert( 'PDF kütüphanesi yükleniyor, lütfen 1 saniye sonra tekrar deneyin.' );
					return;
				}
				if ( ! toplamSatir ) {
					return;
				}
				if ( hazirVeri.length < toplamSatir ) {
					window.alert( 'QR kodları hâlâ hazırlanıyor, birkaç saniye sonra tekrar deneyin.' );
					return;
				}

				var doc = new window.jspdf.jsPDF();

				hazirVeri.forEach( function ( veri, index ) {
					if ( index > 0 ) {
						doc.addPage();
					}
					masaPdfSayfasiCiz( doc, veri.ad, veri.dataUri );
				} );

				doc.save( 'masalar-QR.pdf' );
			} );
		}
	} );
}() );

/**
 * Toplu oluşturma önizlemesi, grup filtresi ve masa adı düzenleme.
 *
 * Üçü de tamamen sayfa içidir: filtreleme satırları göster/gizle yapar,
 * düzenleme yalnızca hazır formu gösterip gizler — sunucuya istek yalnızca
 * "Kaydet"e basılınca (normal form POST'uyla) gider.
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

	/**
	 * Masa adı düzenleme: "Düzenle" tıklanınca ad yerine hazır (gizli) form
	 * gösterilir; "İptal" eski görünüme döner. Kaydetme normal form POST'u
	 * ile sunucuya gider (bkz. masalar-sayfasi.php: qmo_masa_duzenle).
	 */
	function duzenlekurulum() {
		document.querySelectorAll( '.qmo-edit-toggle' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var row   = btn.closest( '.qmo-row' );
				var hucre = row ? row.querySelector( '.qmo-name-cell' ) : null;
				if ( ! hucre ) {
					return;
				}

				var goster = hucre.querySelector( '.qmo-name-display' );
				var form   = hucre.querySelector( '.qmo-edit-form' );
				if ( goster ) {
					goster.hidden = true;
				}
				if ( form ) {
					form.hidden = false;
					var girdi = form.querySelector( '.qmo-edit-input' );
					if ( girdi ) {
						girdi.focus();
						girdi.select();
					}
				}
			} );
		} );

		document.querySelectorAll( '.qmo-edit-cancel' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var hucre = btn.closest( '.qmo-name-cell' );
				if ( ! hucre ) {
					return;
				}

				var goster = hucre.querySelector( '.qmo-name-display' );
				var form   = hucre.querySelector( '.qmo-edit-form' );
				if ( form ) {
					form.hidden = true;
				}
				if ( goster ) {
					goster.hidden = false;
				}
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		toplukurulum();
		filtrekurulum();
		duzenlekurulum();
	} );
}() );
