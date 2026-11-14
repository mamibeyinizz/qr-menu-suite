/**
 * QR Menu Suite — panel özellik araması.
 *
 * Dizin PHP'den gelir (QRMS_Ozellik_Arama::dizin): başlık, grup, açıklama, eşanlamlı
 * kelimeler ve adres. Eşleme GENİŞTİR:
 *  - Türkçe harfler katlanır (ı→i, ş→s, ğ→g, ü→u, ö→o, ç→c); büyük/küçük harf fark etmez.
 *  - Kelime başı (önek) eşleşir: "kamp" → "Kampanyalar".
 *  - Birden çok kelime VEYA mantığıyla aranır, çok kelimeyle eşleşen üste çıkar.
 *  - 1–2 harflik yazım hatası tolere edilir: "keywrds" → "keywords".
 *  - Eşanlamlılar: "keywords" → "Menüde Bulunamayan Aramalar".
 *
 * Saf fonksiyonlar (katla, hazirla, ara) Node'da test edilir: tests/ozellik-arama-test.js.
 */
( function ( kok ) {
	'use strict';

	function katla( metin ) {
		return String( metin || '' )
			.replace( /İ/g, 'i' )
			.replace( /I/g, 'i' )
			.toLowerCase()
			.replace( /ı/g, 'i' )
			.normalize( 'NFD' )
			.replace( /[̀-ͯ]/g, '' )
			.replace( /[^a-z0-9]+/g, ' ' )
			.trim();
	}

	function kelimeler( metin ) {
		var k = katla( metin );
		return k ? k.split( ' ' ) : [];
	}

	// İki kelime arasındaki düzenleme uzaklığı (sınır aşılınca erken çıkar).
	function uzaklik( a, b, sinir ) {
		if ( Math.abs( a.length - b.length ) > sinir ) {
			return sinir + 1;
		}

		var onceki = [];
		var i;
		var j;

		for ( j = 0; j <= b.length; j++ ) {
			onceki[ j ] = j;
		}

		for ( i = 1; i <= a.length; i++ ) {
			var simdiki = [ i ];
			var enKucuk = i;

			for ( j = 1; j <= b.length; j++ ) {
				var maliyet = a.charAt( i - 1 ) === b.charAt( j - 1 ) ? 0 : 1;
				simdiki[ j ] = Math.min( onceki[ j ] + 1, simdiki[ j - 1 ] + 1, onceki[ j - 1 ] + maliyet );
				enKucuk = Math.min( enKucuk, simdiki[ j ] );
			}

			if ( enKucuk > sinir ) {
				return sinir + 1;
			}

			onceki = simdiki;
		}

		return onceki[ b.length ];
	}

	function hazirla( dizin ) {
		return ( dizin || [] ).map( function ( d ) {
			return {
				kayit: d,
				baslik: kelimeler( d.b ),
				grup: kelimeler( d.g ),
				kelime: kelimeler( d.k ),
				aciklama: kelimeler( d.a ),
				baslikTam: katla( d.b )
			};
		} );
	}

	// Bir sorgu kelimesinin bir kelime listesine en iyi uyumu: 3 önek, 2 içinde geçer, 1 yazım hatası, 0 yok.
	function uyum( t, liste ) {
		var en = 0;
		var sinir = t.length >= 7 ? 2 : 1;

		for ( var i = 0; i < liste.length; i++ ) {
			var w = liste[ i ];

			if ( 0 === w.indexOf( t ) ) {
				return 3;
			}

			if ( t.length >= 3 && -1 !== w.indexOf( t ) ) {
				en = Math.max( en, 2 );
			} else if ( t.length >= 4 && en < 1 ) {
				// Yazım hatası: kelimenin tamamına ya da (yazarken) aynı uzunluktaki başına göre.
				if ( uzaklik( t, w, sinir ) <= sinir || ( w.length > t.length && uzaklik( t, w.slice( 0, t.length ), sinir ) <= sinir ) ) {
					en = 1;
				}
			}
		}

		return en;
	}

	function ara( hazir, sorgu, limit ) {
		var parcalar = kelimeler( sorgu ).filter( function ( t ) {
			return t.length >= 2;
		} );

		if ( ! parcalar.length ) {
			return [];
		}

		var tam = katla( sorgu );
		var sonuc = [];

		hazir.forEach( function ( h ) {
			var puan = 0;
			var eslesen = 0;

			parcalar.forEach( function ( t ) {
				var p = Math.max(
					uyum( t, h.baslik ) * 10,
					uyum( t, h.kelime ) * 7,
					uyum( t, h.grup ) * 4,
					uyum( t, h.aciklama ) * 2
				);

				if ( p > 0 ) {
					eslesen++;
					puan += p;
				}
			} );

			if ( ! eslesen ) {
				return;
			}

			if ( 0 === h.baslikTam.indexOf( tam ) ) {
				puan += 15; // Başlık sorguyla başlıyor.
			}

			sonuc.push( { kayit: h.kayit, puan: puan, eslesen: eslesen } );
		} );

		sonuc.sort( function ( a, b ) {
			return ( b.eslesen - a.eslesen ) || ( b.puan - a.puan ) || a.kayit.b.localeCompare( b.kayit.b, 'tr' );
		} );

		return sonuc.slice( 0, limit || 8 ).map( function ( s ) {
			return s.kayit;
		} );
	}

	var api = { katla: katla, hazirla: hazirla, ara: ara };

	if ( 'object' === typeof module && module.exports ) {
		module.exports = api;
		return;
	}

	/* ------------------------------------------------------------------
	   Arayüz
	------------------------------------------------------------------ */

	var CFG = kok.qrmsOzAra || {};
	var M = CFG.metin || {};

	function kur() {
		var kap = document.getElementById( 'qrms-oz' );
		var girdi = document.getElementById( 'qrms-oz-girdi' );
		var liste = document.getElementById( 'qrms-oz-liste' );
		var durum = document.getElementById( 'qrms-oz-durum' );
		var ac = document.getElementById( 'qrms-oz-ac' );
		var kapat = document.getElementById( 'qrms-oz-kapat' );

		if ( ! kap || ! girdi || ! liste ) {
			return;
		}

		var hazir = hazirla( CFG.dizin || [] );
		var sonuclar = [];
		var secili = -1;

		function esc( s ) {
			return String( s ).replace( /[&<>"']/g, function ( c ) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
			} );
		}

		function listeyiKapat() {
			liste.hidden = true;
			girdi.setAttribute( 'aria-expanded', 'false' );
			girdi.removeAttribute( 'aria-activedescendant' );
			secili = -1;
		}

		function sec( i ) {
			var ogeler = liste.querySelectorAll( '[role="option"]' );

			if ( ! ogeler.length ) {
				return;
			}

			secili = ( i + ogeler.length ) % ogeler.length;

			Array.prototype.forEach.call( ogeler, function ( o, k ) {
				o.setAttribute( 'aria-selected', k === secili ? 'true' : 'false' );
			} );

			girdi.setAttribute( 'aria-activedescendant', ogeler[ secili ].id );
			ogeler[ secili ].scrollIntoView( { block: 'nearest' } );
		}

		function git( kayit ) {
			if ( kayit && kayit.u ) {
				window.location.href = kayit.u;
			}
		}

		function bas() {
			var q = girdi.value;
			sonuclar = ara( hazir, q, 8 );

			if ( ! katla( q ) ) {
				listeyiKapat();
				durum.textContent = '';
				return;
			}

			if ( ! sonuclar.length ) {
				liste.innerHTML = '<li class="qrms-oz__bos" role="presentation">' + esc( M.yok || 'Sonuç yok.' ) + '</li>';
			} else {
				liste.innerHTML = sonuclar.map( function ( k, i ) {
					return '<li id="qrms-oz-s' + i + '" role="option" aria-selected="false" class="qrms-oz__oge" data-i="' + i + '">' +
						'<span class="qrms-oz__baslik">' + esc( k.b ) + '</span>' +
						( k.g ? '<span class="qrms-oz__grup">' + esc( k.g ) + '</span>' : '' ) +
						'</li>';
				} ).join( '' );
			}

			liste.hidden = false;
			girdi.setAttribute( 'aria-expanded', 'true' );
			durum.textContent = ( M.sonuc || '%d sonuç' ).replace( '%d', sonuclar.length );

			if ( sonuclar.length ) {
				sec( 0 );
			}
		}

		girdi.addEventListener( 'input', bas );
		girdi.addEventListener( 'focus', function () {
			if ( girdi.value ) {
				bas();
			}
		} );

		girdi.addEventListener( 'keydown', function ( e ) {
			if ( 'ArrowDown' === e.key ) {
				e.preventDefault();
				if ( liste.hidden ) {
					bas();
				} else {
					sec( secili + 1 );
				}
			} else if ( 'ArrowUp' === e.key ) {
				e.preventDefault();
				sec( secili - 1 );
			} else if ( 'Enter' === e.key ) {
				if ( sonuclar.length ) {
					e.preventDefault();
					git( sonuclar[ Math.max( 0, secili ) ] );
				}
			} else if ( 'Escape' === e.key ) {
				if ( ! liste.hidden ) {
					listeyiKapat();
				} else {
					girdi.value = '';
					girdi.blur();
					kap.classList.remove( 'is-acik' );
				}
			}
		} );

		// mousedown: input blur olmadan önce seçilir.
		liste.addEventListener( 'mousedown', function ( e ) {
			var oge = e.target.closest( '[role="option"]' );

			if ( oge ) {
				e.preventDefault();
				git( sonuclar[ parseInt( oge.getAttribute( 'data-i' ), 10 ) ] );
			}
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! kap.contains( e.target ) ) {
				listeyiKapat();
				kap.classList.remove( 'is-acik' );
				if ( ac ) {
					ac.setAttribute( 'aria-expanded', 'false' );
				}
			}
		} );

		// "/" ya da Ctrl/Cmd+K: aramaya odaklan (yazı alanındayken değil).
		document.addEventListener( 'keydown', function ( e ) {
			var hedef = e.target;
			var yaziyor = hedef && ( /^(INPUT|TEXTAREA|SELECT)$/.test( hedef.tagName ) || hedef.isContentEditable );

			if ( ( '/' === e.key && ! yaziyor ) || ( ( e.ctrlKey || e.metaKey ) && 'k' === String( e.key ).toLowerCase() ) ) {
				e.preventDefault();
				kap.classList.add( 'is-acik' );
				girdi.focus();
				girdi.select();
			}
		} );

		// Mobil: büyüteç düğmesi kutuyu başlığın üstünde açar.
		if ( ac ) {
			ac.addEventListener( 'click', function () {
				kap.classList.add( 'is-acik' );
				ac.setAttribute( 'aria-expanded', 'true' );
				girdi.focus();
			} );
		}

		if ( kapat ) {
			kapat.addEventListener( 'click', function () {
				girdi.value = '';
				listeyiKapat();
				kap.classList.remove( 'is-acik' );
				if ( ac ) {
					ac.setAttribute( 'aria-expanded', 'false' );
					ac.focus();
				}
			} );
		}
	}

	/**
	 * Aramadan çapayla gelinen bölümü görünür tutar.
	 *
	 * Panel sayfaları tablolarını sonradan (AJAX ile) doldurur; tarayıcının ilk kaydırması
	 * bittikten sonra içerik büyüyünce hedef bölüm aşağı itiliyordu. Kullanıcı kendisi
	 * kaydırmadıkça birkaç kez yeniden hizalanır.
	 */
	function capaKoru() {
		var id = '';

		try {
			id = decodeURIComponent( String( window.location.hash || '' ).slice( 1 ) );
		} catch ( e ) {
			return;
		}

		var hedef = id ? document.getElementById( id ) : null;

		if ( ! hedef ) {
			return;
		}

		var kullaniciKaydirdi = false;
		var dur = function () {
			kullaniciKaydirdi = true;
		};

		[ 'wheel', 'touchstart', 'keydown', 'mousedown' ].forEach( function ( olay ) {
			window.addEventListener( olay, dur, { once: true, passive: true } );
		} );

		[ 120, 600, 1300, 2400, 4000 ].forEach( function ( ms ) {
			window.setTimeout( function () {
				if ( ! kullaniciKaydirdi ) {
					hedef.scrollIntoView( { block: 'start' } );
				}
			}, ms );
		} );
	}

	function baslat() {
		try {
			kur();
		} catch ( e ) {}

		try {
			capaKoru();
		} catch ( e ) {}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', baslat );
	} else {
		baslat();
	}
}( this ) );
