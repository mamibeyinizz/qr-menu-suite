/**
 * Servis Paneli — canlı kanban.
 *
 * Kart şablonunun TEK kaynağı bu dosyadır; PHP tarafı yalnızca iskeleti
 * basar. Canlı bir panelde sunucuda bir kez üretilen kart ikinci saniyede
 * zaten eskimiş olurdu.
 *
 * Yoklama davranışı:
 *   - Sekme önplandayken ayarlardaki aralık (varsayılan 5 sn).
 *   - Arka plandayken 30 sn; öne gelince ANINDA bir istek atılır.
 *   - Arka arkaya hata geldikçe aralık ikiye katlanarak 60 sn'ye çıkar,
 *     bağlantı dönünce eski aralığa iner.
 */
( function () {
	'use strict';

	var C = window.QRMS_SP || {};
	var M = C.metin || {};

	var kanban   = document.getElementById( 'qrms-sp-kanban' );
	var serit    = document.getElementById( 'qrms-sp-serit' );
	var sekmeler = document.getElementById( 'qrms-sp-sekmeler' );

	if ( ! kanban ) {
		return;
	}

	var durumlar = C.durumlar || {};
	var tipler   = C.tipler || {};
	var akis     = C.akis || {};

	var normalAralik = ( C.yenileme || 5 ) * 1000;
	var aralik       = normalAralik;
	var zamanlayici  = null;
	var hataSayisi   = 0;
	var bilinen      = {};       // id -> true (yeni kayıt tespiti için)
	var ilkYukleme   = true;
	var sonKayitlar  = [];
	var sunucuFarki  = 0;        // sunucu saati - tarayıcı saati (saniye)
	var aktifSekme   = 'bekliyor';

	/* ---------------------------------------------------------------
	   Ses — harici dosya yok, ton kodda üretilir
	--------------------------------------------------------------- */

	var sesAcik = ( function () {
		try {
			var kayitli = window.localStorage.getItem( 'qrms_sp_ses' );
			return null === kayitli ? !! C.sesVarsayilan : '1' === kayitli;
		} catch ( e ) {
			return !! C.sesVarsayilan;
		}
	}() );

	var sesBaglami = null;

	function bipCal() {
		if ( ! sesAcik ) {
			return;
		}

		try {
			var Ctx = window.AudioContext || window.webkitAudioContext;

			if ( ! Ctx ) {
				return;
			}

			if ( ! sesBaglami ) {
				sesBaglami = new Ctx();
			}

			// Tarayıcı, kullanıcı etkileşimi olmadan sesi askıya alır;
			// düğmeye basılmışsa bağlam zaten çözülmüştür.
			if ( 'suspended' === sesBaglami.state ) {
				sesBaglami.resume();
			}

			var osc = sesBaglami.createOscillator();
			var kaz = sesBaglami.createGain();

			osc.type = 'sine';
			osc.frequency.setValueAtTime( 880, sesBaglami.currentTime );
			kaz.gain.setValueAtTime( 0.0001, sesBaglami.currentTime );
			kaz.gain.exponentialRampToValueAtTime( 0.25, sesBaglami.currentTime + 0.02 );
			kaz.gain.exponentialRampToValueAtTime( 0.0001, sesBaglami.currentTime + 0.4 );

			osc.connect( kaz ).connect( sesBaglami.destination );
			osc.start();
			osc.stop( sesBaglami.currentTime + 0.42 );
		} catch ( e ) {
			// Ses çalınamaması panelin çalışmasını engellemez.
		}
	}

	function sesDugmesi() {
		var dugme = document.querySelector( '.qrms-sp-ses' );

		if ( ! dugme ) {
			return;
		}

		var yansit = function () {
			dugme.setAttribute( 'aria-pressed', sesAcik ? 'true' : 'false' );
			dugme.classList.toggle( 'aktif', sesAcik );
			dugme.querySelector( '.qrms-sp-ses-metin' ).textContent = sesAcik ? M.sesKapat : M.sesAc;
		};

		dugme.addEventListener( 'click', function () {
			sesAcik = ! sesAcik;

			try {
				window.localStorage.setItem( 'qrms_sp_ses', sesAcik ? '1' : '0' );
			} catch ( e ) {}

			yansit();

			if ( sesAcik ) {
				bipCal();
			}
		} );

		yansit();
	}

	/* ---------------------------------------------------------------
	   Masaüstü bildirimi — izin YALNIZCA düğmeye basılınca istenir
	--------------------------------------------------------------- */

	function bildirimDugmesi() {
		var dugme = document.querySelector( '.qrms-sp-bildirim' );

		if ( ! dugme || ! ( 'Notification' in window ) ) {
			if ( dugme ) {
				dugme.hidden = true;
			}
			return;
		}

		var yansit = function () {
			dugme.classList.toggle( 'aktif', 'granted' === Notification.permission );
			dugme.disabled = 'denied' === Notification.permission;
		};

		dugme.addEventListener( 'click', function () {
			Notification.requestPermission().then( yansit );
		} );

		yansit();
	}

	function bildirimGoster( sayi ) {
		if ( ! ( 'Notification' in window ) || 'granted' !== Notification.permission ) {
			return;
		}

		try {
			new Notification( M.yeniSiparis, { body: sayi + '', tag: 'qrms-sp' } );
		} catch ( e ) {}
	}

	/* ---------------------------------------------------------------
	   Başlık yanıp sönmesi
	--------------------------------------------------------------- */

	var baslikAsil = document.title;
	var baslikVuru = null;

	function baslikUyar( sayi ) {
		if ( baslikVuru ) {
			window.clearInterval( baslikVuru );
		}

		var acik = false;

		baslikVuru = window.setInterval( function () {
			document.title = acik ? baslikAsil : '(' + sayi + ') ' + M.yeniSiparis;
			acik = ! acik;
		}, 1200 );

		var durdur = function () {
			window.clearInterval( baslikVuru );
			baslikVuru = null;
			document.title = baslikAsil;
			document.removeEventListener( 'visibilitychange', durdur );
			window.removeEventListener( 'focus', durdur );
		};

		document.addEventListener( 'visibilitychange', durdur );
		window.addEventListener( 'focus', durdur );
	}

	/* ---------------------------------------------------------------
	   Kart üretimi
	--------------------------------------------------------------- */

	function metinDugumu( etiket, sinif, icerik ) {
		var el = document.createElement( etiket );

		if ( sinif ) {
			el.className = sinif;
		}
		if ( undefined !== icerik ) {
			// textContent: kayıt içeriği müşteriden gelir, HTML olarak
			// yorumlanmamalı.
			el.textContent = icerik;
		}

		return el;
	}

	function gecenSaniye( kayit ) {
		if ( ! kayit.olusmaTs ) {
			return 0;
		}

		var simdi = Math.floor( Date.now() / 1000 ) + sunucuFarki;

		return Math.max( 0, simdi - kayit.olusmaTs );
	}

	function sureMetni( saniye ) {
		var dk = Math.floor( saniye / 60 );
		var sn = saniye % 60;

		return dk + ':' + ( sn < 10 ? '0' : '' ) + sn;
	}

	function aciliyet( saniye, durum ) {
		if ( 'tamamlandi' === durum ) {
			return '';
		}
		if ( saniye >= C.esikKirmizi ) {
			return 'kirmizi';
		}
		if ( saniye >= C.esikSari ) {
			return 'sari';
		}

		return 'yesil';
	}

	function kartYap( kayit ) {
		var saniye = gecenSaniye( kayit );
		var acil   = aciliyet( saniye, kayit.durum );

		var kart = document.createElement( 'article' );
		kart.className = 'qrms-sp-kart qrms-sp-acil-' + acil;
		kart.dataset.id = kayit.id;
		kart.dataset.durum = kayit.durum;

		var bas = metinDugumu( 'header', 'qrms-sp-kart-basi' );
		bas.appendChild( metinDugumu( 'span', 'qrms-sp-masa', kayit.masaAd || '—' ) );
		bas.appendChild( metinDugumu( 'span', 'qrms-sp-tip qrms-sp-tip-' + kayit.tip, tipler[ kayit.tip ] || kayit.tip ) );
		bas.appendChild( metinDugumu( 'span', 'qrms-sp-sure', sureMetni( saniye ) ) );
		kart.appendChild( bas );

		if ( kayit.kalemler && kayit.kalemler.length ) {
			var liste = document.createElement( 'ul' );
			liste.className = 'qrms-sp-kalemler';

			kayit.kalemler.forEach( function ( kalem ) {
				var li = document.createElement( 'li' );

				li.appendChild( metinDugumu( 'span', 'qrms-sp-adet', kalem.adet + '×' ) );
				li.appendChild( metinDugumu( 'span', 'qrms-sp-kalem-ad', kalem.ad ) );

				// Çeviri varsa ve orijinalden farklıysa ikisi de gösterilir:
				// mutfak Türkçesini okur, garson misafire kendi dilinde döner.
				if ( kalem.notTr ) {
					li.appendChild( metinDugumu( 'span', 'qrms-sp-not', M.not + ': ' + kalem.notTr ) );

					if ( kalem.not && kalem.not !== kalem.notTr ) {
						li.appendChild( metinDugumu( 'span', 'qrms-sp-not qrms-sp-not-asil', kalem.not ) );
					}
				} else if ( kalem.not ) {
					li.appendChild( metinDugumu( 'span', 'qrms-sp-not', M.not + ': ' + kalem.not ) );
				}

				liste.appendChild( li );
			} );

			kart.appendChild( liste );
		}

		if ( kayit.personel ) {
			kart.appendChild( metinDugumu( 'p', 'qrms-sp-personel', kayit.personel ) );
		}

		kart.appendChild( dugmeler( kayit ) );

		return kart;
	}

	function dugmeler( kayit ) {
		var kap = document.createElement( 'div' );
		kap.className = 'qrms-sp-dugmeler';

		var hedefler = akis[ kayit.durum ] || [];

		hedefler.forEach( function ( hedef ) {
			var dugme = document.createElement( 'button' );
			dugme.type = 'button';
			dugme.className = 'button qrms-sp-gecis' + ( 'iptal' === hedef ? ' qrms-sp-iptal' : '' );
			dugme.dataset.hedef = hedef;

			// İleri yönlü geçiş birincil düğmedir: personelin en sık bastığı o.
			if ( hedef !== 'iptal' && hedefler.indexOf( hedef ) === 0 ) {
				dugme.className += ' button-primary';
			}

			dugme.textContent = 'iptal' === hedef ? M.iptal : ( durumlar[ hedef ] || hedef );
			kap.appendChild( dugme );
		} );

		return kap;
	}

	/* ---------------------------------------------------------------
	   Çizim
	--------------------------------------------------------------- */

	function filtreler( kayitlar ) {
		var tip  = ( document.getElementById( 'qrms-sp-tip' ) || {} ).value || '';
		var masa = ( ( document.getElementById( 'qrms-sp-masa' ) || {} ).value || '' ).trim().toLocaleLowerCase( 'tr' );

		return kayitlar.filter( function ( kayit ) {
			if ( tip && kayit.tip !== tip ) {
				return false;
			}
			if ( masa && -1 === ( kayit.masaAd || '' ).toLocaleLowerCase( 'tr' ).indexOf( masa ) ) {
				return false;
			}

			return true;
		} );
	}

	function ciz( kayitlar ) {
		var gorunen = filtreler( kayitlar );

		Object.keys( durumlar ).forEach( function ( durum ) {
			var kap = kanban.querySelector( '[data-kartlar="' + durum + '"]' );

			if ( ! kap ) {
				return;
			}

			var altKume = gorunen.filter( function ( kayit ) {
				return kayit.durum === durum;
			} );

			// Aciliyeti yüksek olan üstte: kırmızıya geçen kart gözden kaçmasın.
			altKume.sort( function ( a, b ) {
				return gecenSaniye( b ) - gecenSaniye( a );
			} );

			kap.textContent = '';

			if ( ! altKume.length ) {
				kap.appendChild( metinDugumu( 'p', 'qrms-sp-bos', M.bos ) );
			} else {
				altKume.forEach( function ( kayit ) {
					kap.appendChild( kartYap( kayit ) );
				} );
			}

			var sayac = document.querySelector( '[data-sayac="' + durum + '"]' );

			if ( sayac ) {
				sayac.textContent = altKume.length;
			}

			var sekme = sekmeler.querySelector( '[data-sekme="' + durum + '"] .qrms-sp-sekme-sayi' );

			if ( sekme ) {
				sekme.textContent = altKume.length;
			}
		} );
	}

	/* ---------------------------------------------------------------
	   Sekmeler (dar ekran)
	--------------------------------------------------------------- */

	function sekmeKur() {
		Object.keys( durumlar ).forEach( function ( durum ) {
			var dugme = document.createElement( 'button' );
			dugme.type = 'button';
			dugme.className = 'qrms-sp-sekme' + ( durum === aktifSekme ? ' aktif' : '' );
			dugme.dataset.sekme = durum;
			dugme.setAttribute( 'role', 'tab' );
			dugme.setAttribute( 'aria-selected', durum === aktifSekme ? 'true' : 'false' );

			dugme.appendChild( metinDugumu( 'span', 'qrms-sp-sekme-ad', durumlar[ durum ] ) );
			dugme.appendChild( metinDugumu( 'span', 'qrms-sp-sekme-sayi', '0' ) );

			dugme.addEventListener( 'click', function () {
				aktifSekme = durum;

				sekmeler.querySelectorAll( '.qrms-sp-sekme' ).forEach( function ( d ) {
					var secili = d.dataset.sekme === durum;
					d.classList.toggle( 'aktif', secili );
					d.setAttribute( 'aria-selected', secili ? 'true' : 'false' );
				} );

				kanban.dataset.aktif = durum;
			} );

			sekmeler.appendChild( dugme );
		} );

		kanban.dataset.aktif = aktifSekme;
	}

	/* ---------------------------------------------------------------
	   Sunucu
	--------------------------------------------------------------- */

	function istek( eylem, veri ) {
		var govde = new FormData();

		govde.append( 'action', eylem );
		govde.append( 'nonce', C.nonce );

		Object.keys( veri || {} ).forEach( function ( anahtar ) {
			govde.append( anahtar, veri[ anahtar ] );
		} );

		return fetch( C.ajaxUrl, { method: 'POST', body: govde, credentials: 'same-origin' } )
			.then( function ( cevap ) {
				return cevap.json();
			} );
	}

	function seritGoster( metin, hata ) {
		if ( ! serit ) {
			return;
		}

		if ( ! metin ) {
			serit.hidden = true;
			return;
		}

		serit.hidden = false;
		serit.textContent = metin;
		serit.className = 'qrms-sp-serit' + ( hata ? ' hatali' : ' basarili' );
	}

	function yeniKayitlar( kayitlar ) {
		var yeni = 0;

		kayitlar.forEach( function ( kayit ) {
			if ( ! bilinen[ kayit.id ] ) {
				bilinen[ kayit.id ] = true;

				if ( ! ilkYukleme && 'bekliyor' === kayit.durum ) {
					yeni++;
				}
			}
		} );

		return yeni;
	}

	function yokla() {
		istek( 'qrms_sp_liste', {} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					throw new Error( json && json.data ? json.data.msg : '' );
				}

				hataSayisi = 0;
				aralik = document.hidden ? 30000 : normalAralik;
				seritGoster( '' );

				sunucuFarki = ( json.data.sunucuSaat || 0 ) - Math.floor( Date.now() / 1000 );
				sonKayitlar = json.data.kayitlar || [];

				var yeni = yeniKayitlar( sonKayitlar );

				ciz( sonKayitlar );

				if ( yeni > 0 ) {
					bipCal();
					bildirimGoster( yeni );

					if ( document.hidden ) {
						baslikUyar( yeni );
					}
				}

				ilkYukleme = false;
			} )
			.catch( function ( hata ) {
				hataSayisi++;

				// Tek bir hata ağ dalgalanması olabilir; ikinciden itibaren
				// kullanıcıya söylenir ve aralık üstel olarak açılır.
				if ( hataSayisi >= 2 ) {
					seritGoster( ( hata && hata.message ) || M.baglantiYok, true );
					aralik = Math.min( 60000, aralik * 2 );
				}
			} )
			.then( planla );
	}

	function planla() {
		if ( zamanlayici ) {
			window.clearTimeout( zamanlayici );
		}

		zamanlayici = window.setTimeout( yokla, aralik );
	}

	/* ---------------------------------------------------------------
	   Durum değişikliği
	--------------------------------------------------------------- */

	function geciselerKur() {
		kanban.addEventListener( 'click', function ( olay ) {
			var dugme = olay.target.closest( '.qrms-sp-gecis' );

			if ( ! dugme ) {
				return;
			}

			var kart = dugme.closest( '.qrms-sp-kart' );

			if ( ! kart || dugme.disabled ) {
				return;
			}

			// Çift tıklama iki istek göndermesin.
			kart.querySelectorAll( '.qrms-sp-gecis' ).forEach( function ( d ) {
				d.disabled = true;
			} );

			istek( 'qrms_sp_durum', {
				id: kart.dataset.id,
				eski: kart.dataset.durum,
				yeni: dugme.dataset.hedef
			} )
				.then( function ( json ) {
					if ( ! json || ! json.success ) {
						throw new Error( json && json.data ? json.data.msg : M.hata );
					}

					// Yerel kopyayı hemen güncelle: bir sonraki yoklamayı
					// beklemeden kart doğru sütuna geçsin.
					sonKayitlar.forEach( function ( kayit ) {
						if ( kayit.id === kart.dataset.id ) {
							kayit.durum = json.data.durum;
						}
					} );

					ciz( sonKayitlar );
				} )
				.catch( function ( hata ) {
					seritGoster( ( hata && hata.message ) || M.hata, true );

					kart.querySelectorAll( '.qrms-sp-gecis' ).forEach( function ( d ) {
						d.disabled = false;
					} );
				} );
		} );
	}

	/* ---------------------------------------------------------------
	   Başlat
	--------------------------------------------------------------- */

	function baslat() {
		sekmeKur();
		sesDugmesi();
		bildirimDugmesi();
		geciselerKur();

		[ 'qrms-sp-tip', 'qrms-sp-masa' ].forEach( function ( id ) {
			var alan = document.getElementById( id );

			if ( alan ) {
				alan.addEventListener( 'input', function () {
					ciz( sonKayitlar );
				} );
			}
		} );

		// Süre sayaçları her saniye tazelenir; sunucuya gidilmez.
		window.setInterval( function () {
			kanban.querySelectorAll( '.qrms-sp-kart' ).forEach( function ( kart ) {
				var kayit = sonKayitlar.filter( function ( k ) {
					return k.id === kart.dataset.id;
				} )[ 0 ];

				if ( ! kayit ) {
					return;
				}

				var saniye = gecenSaniye( kayit );
				var sure   = kart.querySelector( '.qrms-sp-sure' );

				if ( sure ) {
					sure.textContent = sureMetni( saniye );
				}

				kart.className = 'qrms-sp-kart qrms-sp-acil-' + aciliyet( saniye, kayit.durum );
			} );
		}, 1000 );

		document.addEventListener( 'visibilitychange', function () {
			if ( document.hidden ) {
				aralik = 30000;
				planla();
			} else {
				aralik = normalAralik;
				yokla();
			}
		} );

		yokla();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', baslat );
	} else {
		baslat();
	}
}() );
