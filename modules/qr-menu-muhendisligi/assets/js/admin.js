/**
 * Menü Mühendisliği — yönetim ekranı davranışları.
 *
 * Saf JavaScript; jQuery'ye bağımlı değildir. Betik yüklenmezse ekranlar
 * çalışmaya devam eder: tablo sıralanmaz, maliyet satır içinde kaydedilmez
 * (malzeme ve ayar formları zaten sunucu tarafında POST'lanır).
 */
( function () {
	'use strict';

	var C = window.QRMS_MM || {};

	/* ---------------------------------------------------------------
	   Sunucuya yazma
	--------------------------------------------------------------- */

	function gonder( eylem, veri, bitti, hata ) {
		var govde = new FormData();

		govde.append( 'action', eylem );
		govde.append( 'nonce', C.nonce );

		Object.keys( veri ).forEach( function ( anahtar ) {
			govde.append( anahtar, veri[ anahtar ] );
		} );

		fetch( C.ajaxUrl, { method: 'POST', body: govde, credentials: 'same-origin' } )
			.then( function ( cevap ) {
				return cevap.json();
			} )
			.then( function ( json ) {
				if ( json && json.success ) {
					bitti( json.data );
				} else {
					hata( json && json.data ? json.data.msg : C.hata );
				}
			} )
			.catch( function () {
				hata( C.hata );
			} );
	}

	function durumGoster( alan, metin, basarili ) {
		if ( ! alan ) {
			return;
		}

		alan.textContent = metin;
		alan.className = 'qrms-mm-durum ' + ( basarili ? 'basarili' : 'hatali' );

		window.setTimeout( function () {
			alan.textContent = '';
			alan.className = 'qrms-mm-durum';
		}, 2500 );
	}

	function satirGuncelle( satir, veri ) {
		var katki = satir.querySelector( '.qrms-mm-katki' );
		var marj  = satir.querySelector( '.qrms-mm-marj' );

		if ( katki ) {
			katki.textContent = veri.katki;
		}
		if ( marj ) {
			marj.textContent = veri.marj;
		}
	}

	/* ---------------------------------------------------------------
	   Satır içi maliyet
	--------------------------------------------------------------- */

	function maliyetAlanlari() {
		document.querySelectorAll( '.qrms-mm-maliyet-alan' ).forEach( function ( alan ) {
			var onceki = alan.value;

			var kaydet = function () {
				if ( alan.readOnly || alan.value === onceki ) {
					return;
				}

				onceki = alan.value;

				var satir = alan.closest( 'tr' );
				var durum = satir.querySelector( '.qrms-mm-durum' );

				gonder(
					'qrms_mm_maliyet',
					{ urun: satir.dataset.urun, maliyet: alan.value },
					function ( veri ) {
						satirGuncelle( satir, veri );
						durumGoster( durum, C.kaydedildi, true );
					},
					function ( mesaj ) {
						durumGoster( durum, mesaj, false );
					}
				);
			};

			alan.addEventListener( 'blur', kaydet );

			alan.addEventListener( 'keydown', function ( olay ) {
				if ( 'Enter' === olay.key ) {
					olay.preventDefault();
					alan.blur();
				}
			} );
		} );
	}

	/* ---------------------------------------------------------------
	   Reçete paneli
	--------------------------------------------------------------- */

	function recetePaneli() {
		document.querySelectorAll( '.qrms-mm-recete-ac' ).forEach( function ( dugme ) {
			dugme.addEventListener( 'click', function () {
				var satir = dugme.closest( 'tr' );
				var panel = document.querySelector(
					'.qrms-mm-recete-satir[data-urun="' + satir.dataset.urun + '"]'
				);

				if ( ! panel ) {
					return;
				}

				var acik = ! panel.hidden;
				panel.hidden = acik;
				dugme.setAttribute( 'aria-expanded', acik ? 'false' : 'true' );
			} );
		} );

		document.querySelectorAll( '.qrms-mm-recete-ekle' ).forEach( function ( dugme ) {
			dugme.addEventListener( 'click', function () {
				var kap = dugme.closest( '.qrms-mm-recete' ).querySelector( '.qrms-mm-recete-satirlar' );
				var ilk = kap.querySelector( '.qrms-mm-recete-alan' );

				if ( ! ilk ) {
					return;
				}

				var kopya = ilk.cloneNode( true );

				kopya.querySelector( '.qrms-mm-recete-malzeme' ).value = '0';
				kopya.querySelector( '.qrms-mm-recete-miktar' ).value = '';
				kap.appendChild( kopya );
			} );
		} );

		// Silme düğmesi sonradan eklenen satırlarda da çalışsın: olay tek tek
		// düğmeye değil, kapsayıcıya bağlanır.
		document.querySelectorAll( '.qrms-mm-recete' ).forEach( function ( panel ) {
			panel.addEventListener( 'click', function ( olay ) {
				var sil = olay.target.closest( '.qrms-mm-recete-sil' );

				if ( ! sil ) {
					return;
				}

				var alanlar = panel.querySelectorAll( '.qrms-mm-recete-alan' );

				// Son satır silinmez: "+ Malzeme ekle" kopyalayacak bir şablon
				// bulamaz ve panel kullanılamaz hâle gelirdi.
				if ( alanlar.length < 2 ) {
					sil.closest( '.qrms-mm-recete-alan' ).querySelector( '.qrms-mm-recete-malzeme' ).value = '0';
					sil.closest( '.qrms-mm-recete-alan' ).querySelector( '.qrms-mm-recete-miktar' ).value = '';
					return;
				}

				sil.closest( '.qrms-mm-recete-alan' ).remove();
			} );
		} );

		document.querySelectorAll( '.qrms-mm-recete-kaydet' ).forEach( function ( dugme ) {
			dugme.addEventListener( 'click', function () {
				var panel = dugme.closest( '.qrms-mm-recete-satir' );
				var urun  = panel.dataset.urun;
				var durum = panel.querySelector( '.qrms-mm-recete-durum' );
				var veri  = [];

				panel.querySelectorAll( '.qrms-mm-recete-alan' ).forEach( function ( alan ) {
					var term = parseInt( alan.querySelector( '.qrms-mm-recete-malzeme' ).value, 10 );
					var mik  = alan.querySelector( '.qrms-mm-recete-miktar' ).value;

					if ( term > 0 && '' !== mik.trim() ) {
						veri.push( { term_id: term, miktar: mik } );
					}
				} );

				var satir = document.querySelector( '.qrms-mm-satir[data-urun="' + urun + '"]' );

				gonder(
					'qrms_mm_recete',
					{ urun: urun, recete: JSON.stringify( veri ) },
					function ( cevap ) {
						satirGuncelle( satir, cevap );

						var alan = satir.querySelector( '.qrms-mm-maliyet-alan' );
						alan.value = cevap.maliyet;
						alan.readOnly = !! cevap.receteli;

						durumGoster( durum, C.kaydedildi, true );
					},
					function ( mesaj ) {
						durumGoster( durum, mesaj, false );
					}
				);
			} );
		} );
	}

	/* ---------------------------------------------------------------
	   Tablo sıralama
	--------------------------------------------------------------- */

	function tabloSirala() {
		var tablo = document.getElementById( 'qrms-mm-tablo' );

		if ( ! tablo ) {
			return;
		}

		var govde = tablo.querySelector( 'tbody' );

		tablo.querySelectorAll( '.qrms-mm-sirala' ).forEach( function ( dugme, indeks ) {
			dugme.addEventListener( 'click', function () {
				var artan = 'artan' !== dugme.dataset.yon;
				var sayi  = 'sayi' === dugme.dataset.tip;

				var satirlar = Array.prototype.slice.call( govde.querySelectorAll( 'tr' ) );

				satirlar.sort( function ( a, b ) {
					var da = a.children[ indeks ].dataset.deger || '';
					var db = b.children[ indeks ].dataset.deger || '';

					if ( sayi ) {
						return ( parseFloat( da ) - parseFloat( db ) ) * ( artan ? 1 : -1 );
					}

					return da.localeCompare( db, 'tr' ) * ( artan ? 1 : -1 );
				} );

				satirlar.forEach( function ( satir ) {
					govde.appendChild( satir );
				} );

				tablo.querySelectorAll( '.qrms-mm-sirala' ).forEach( function ( d ) {
					if ( d !== dugme ) {
						delete d.dataset.yon;
					}
				} );

				dugme.dataset.yon = artan ? 'artan' : 'azalan';
			} );
		} );
	}

	/* ---------------------------------------------------------------
	   Kaydırıcı etiketleri ve birim ölçüsü
	--------------------------------------------------------------- */

	function kaydiricilar() {
		document.querySelectorAll( 'input[type="range"]' ).forEach( function ( alan ) {
			var etiket = document.querySelector( '.qrms-deger[data-icin="' + alan.id + '"]' );

			if ( ! etiket ) {
				return;
			}

			alan.addEventListener( 'input', function () {
				etiket.textContent = alan.step && parseFloat( alan.step ) < 1
					? parseFloat( alan.value ).toFixed( 2 ).replace( '.', ',' )
					: alan.value + '%';
			} );
		} );
	}

	function birimOlcusu() {
		document.querySelectorAll( '.qrms-mm-birim' ).forEach( function ( secim ) {
			secim.addEventListener( 'change', function () {
				var hucre = secim.closest( 'tr' ).querySelector( '.qrms-mm-olcu' );

				if ( hucre ) {
					hucre.textContent = secim.options[ secim.selectedIndex ].dataset.miktar || '';
				}
			} );
		} );
	}

	function baslat() {
		maliyetAlanlari();
		recetePaneli();
		tabloSirala();
		kaydiricilar();
		birimOlcusu();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', baslat );
	} else {
		baslat();
	}
}() );
