/**
 * QR Menu Official — Chatbot davranışı.
 *
 * Güvenlik notları:
 *  - Her istek qmoData.nonce ile imzalanır.
 *  - Masa numarası istemciden GÖNDERİLMEZ; sunucu oturum cookie'sinden okur.
 *  - Asistan yanıtı DOM'a HTML olarak değil, kaçırılmış metin olarak yazılır.
 */
( function () {
	'use strict';

	var kok = document.querySelector( '.gemini-shortcode-container' );
	if ( ! kok || kok.dataset.qmoInit ) {
		return;
	}
	kok.dataset.qmoInit = '1';

	var overlay = kok.querySelector( '.gemini-chat-overlay' );
	var acButon = kok.querySelector( '.gemini-chat-toggle-btn' );
	if ( ! overlay || ! acButon ) {
		return;
	}

	document.body.appendChild( overlay );

	var log      = overlay.querySelector( '.gemini-chat-log' );
	var input    = overlay.querySelector( '.gemini-chat-input' );
	var gonder   = overlay.querySelector( '.gemini-chat-send' );
	var kapatEl  = overlay.querySelector( '.gemini-chat-close' );
	var welcome  = overlay.querySelector( '.gemini-welcome-screen' );
	var basla    = overlay.querySelector( '.gemini-welcome-start' );
	var teaser   = kok.querySelector( '.gemini-teaser' );
	var teaserX  = kok.querySelector( '.gemini-teaser-kapat' );
	var rozet    = kok.querySelector( '.gemini-unread-badge' );
	var hazirlar = overlay.querySelectorAll( '.gemini-quick-reply' );
	var hazirKutu = overlay.querySelector( '.gemini-quick-replies' );
	var inputBar = overlay.querySelector( '.gemini-chat-input-area' );

	var gecmis = [];
	var rozetAcik = '1' === kok.dataset.badge;
	var kapaliMi  = '1' === kok.dataset.closed;
	var kapaliMsg = kok.dataset.closedMsg || 'Şu an kapalıyız, yakında görüşmek üzere.';
	var teaserAcik = '1' === kok.dataset.teaser;
	var teaserGecikme = Math.max( 1, parseInt( kok.dataset.teaserDelay || '4', 10 ) ) * 1000;
	var kayitliY = 0;

	function teaserAnahtar() {
		return 'qmo_cb_teaser_kapat';
	}

	function rozetGoster() {
		if ( rozet && rozetAcik ) {
			rozet.hidden = false;
		}
	}

	function rozetGizle() {
		if ( rozet ) {
			rozet.hidden = true;
		}
	}

	function teaserGizle() {
		if ( teaser ) {
			teaser.hidden = true;
		}
	}

	function sohbetHazir() {
		if ( welcome ) {
			welcome.hidden = true;
		}
		if ( log ) {
			log.hidden = false;
		}
		if ( hazirKutu ) {
			hazirKutu.hidden = false;
		}
		if ( inputBar ) {
			inputBar.hidden = false;
		}
	}

	function metin( anahtar, yedek ) {
		if ( typeof qmoData === 'undefined' || ! qmoData.i18n ) {
			return yedek;
		}
		var v = qmoData.i18n[ anahtar ];
		return ( 'string' === typeof v && '' !== v ) ? v : yedek;
	}

	/* ------------------------------------------------------------------ */

	function kilitle() {
		if ( document.documentElement.classList.contains( 'gm-scroll-kilit' ) ) {
			return;
		}
		kayitliY = window.scrollY;
		document.documentElement.classList.add( 'gm-scroll-kilit' );
		document.body.classList.add( 'gm-scroll-kilit' );
		document.body.style.top = '-' + kayitliY + 'px';
	}

	function ac() {
		kilitle();
		if ( kapaliMi ) {
			teaserGizle();
			if ( log ) {
				var varMi = overlay.querySelector( '.gemini-msg-sys' );
				if ( ! varMi ) {
					balon( kapaliMsg, 'bot' );
				}
			}
			overlay.classList.add( 'gemini-acik' );
			kok.classList.add( 'gm-open' );
			rozetGizle();
			if ( inputBar ) {
				inputBar.hidden = true;
			}
			if ( hazirKutu ) {
				hazirKutu.hidden = true;
			}
			if ( welcome ) {
				welcome.hidden = true;
			}
			if ( log ) {
				log.hidden = false;
				log.scrollTop = log.scrollHeight;
			}
			return;
		}

		overlay.classList.add( 'gemini-acik' );
		kok.classList.add( 'gm-open' );
		teaserGizle();
		rozetGizle();
		if ( welcome && ! welcome.hidden ) {
			if ( log ) {
				log.hidden = true;
			}
			if ( hazirKutu ) {
				hazirKutu.hidden = true;
			}
			if ( inputBar ) {
				inputBar.hidden = true;
			}
		} else {
			sohbetHazir();
			if ( log ) {
				log.scrollTop = log.scrollHeight;
			}
			if ( input ) {
				input.focus();
			}
		}
	}

	function kapat() {
		overlay.classList.remove( 'gemini-acik' );
		kok.classList.remove( 'gm-open' );
		document.documentElement.classList.remove( 'gm-scroll-kilit' );
		document.body.classList.remove( 'gm-scroll-kilit' );
		document.body.style.top = '';
		window.scrollTo( 0, kayitliY );
	}

	function balon( metin, tip, hataMi ) {
		var el = document.createElement( 'div' );
		el.className = 'gemini-msg-bubble ' + ( 'user' === tip ? 'gemini-msg-user' : 'gemini-msg-bot' );
		if ( hataMi ) {
			el.classList.add( 'gemini-msg-hata' );
		}
		if ( 'sys' === tip ) {
			el.classList.add( 'gemini-msg-sys' );
		}

		var parcalar = String( metin ).split( '\n' );
		parcalar.forEach( function ( p, i ) {
			if ( i > 0 ) {
				el.appendChild( document.createElement( 'br' ) );
			}
			el.appendChild( document.createTextNode( p ) );
		} );

		if ( log ) {
			log.appendChild( el );
			log.scrollTop = log.scrollHeight;
		}
		return el;
	}

	function urunEtiketleriTemizle( metin ) {
		return String( metin || '' )
			.replace( /\[URUN:\d+\]/g, '' )
			.replace( /[ \t]{2,}/g, ' ' )
			.replace( /\n{3,}/g, '\n\n' )
			.trim();
	}

	function sepetOku() {
		try {
			var v = JSON.parse( sessionStorage.getItem( 'qmo_sepet' ) || '[]' );
			return Array.isArray( v ) ? v : [];
		} catch ( e ) {
			return [];
		}
	}

	function sepetKaydet( s ) {
		try {
			sessionStorage.setItem( 'qmo_sepet', JSON.stringify( s ) );
		} catch ( e ) {}
	}

	function sepetAnalitik( pid ) {
		pid = parseInt( pid, 10 ) || 0;
		if ( ! pid || typeof qmoSepet === 'undefined' || ! qmoSepet.analitik || ! qmoSepet.ajaxUrl || ! qmoSepet.nonce ) {
			return;
		}
		var govde = new URLSearchParams();
		govde.append( 'action', 'qmo_sepet_olay' );
		govde.append( 'nonce', qmoSepet.nonce );
		govde.append( 'olaylar', JSON.stringify( [ { tip: 'cart_add', item_id: pid } ] ) );
		fetch( qmoSepet.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			credentials: 'same-origin',
			body: govde.toString()
		} ).catch( function () {} );
	}

	function sepetCubuguGuncelle() {
		var bar = document.getElementById( 'qmo-bar' );
		if ( ! bar ) {
			return;
		}
		var s = sepetOku();
		var adet = 0;
		s.forEach( function ( x ) {
			adet += x.adet || 0;
		} );
		var badge = document.getElementById( 'qmo-badge' );
		if ( badge ) {
			badge.textContent = adet;
		}
		bar.classList.toggle( 'qmo-on', adet > 0 );
	}

	function sepeteEkle( urun ) {
		if ( ! urun || ! urun.id ) {
			return false;
		}
		var pid = parseInt( urun.id, 10 );
		var ad = String( urun.ad || '' );
		var fy = parseFloat( urun.fiyatSayi );
		if ( isNaN( fy ) ) {
			fy = 0;
		}
		var s = sepetOku();
		var mevcut = s.filter( function ( x ) {
			return ( pid && x.pid === pid ) || ( ad && x.id === ad );
		} )[ 0 ];

		if ( mevcut ) {
			mevcut.adet = Math.min( 20, ( mevcut.adet || 0 ) + 1 );
			if ( urun.gorsel ) {
				mevcut.img = urun.gorsel;
			}
			if ( pid && ! mevcut.pid ) {
				mevcut.pid = pid;
			}
		} else {
			s.push( {
				id: ad,
				pid: pid,
				ad: ad,
				fiyat: fy,
				adet: 1,
				not: '',
				img: urun.gorsel || ''
			} );
		}

		sepetKaydet( s );
		sepetAnalitik( pid );
		sepetCubuguGuncelle();
		return true;
	}

	function urunKartiBas( urun ) {
		var kart = document.createElement( 'div' );
		kart.className = 'gemini-urun-kart';

		if ( urun.gorsel ) {
			var img = document.createElement( 'img' );
			img.className = 'gemini-urun-gorsel';
			img.src = urun.gorsel;
			img.alt = '';
			img.loading = 'lazy';
			kart.appendChild( img );
		}

		var govde = document.createElement( 'div' );
		govde.className = 'gemini-urun-govde';

		var adEl = document.createElement( 'div' );
		adEl.className = 'gemini-urun-ad';
		adEl.textContent = urun.ad || '';

		var fyEl = document.createElement( 'div' );
		fyEl.className = 'gemini-urun-fiyat';
		fyEl.textContent = urun.fiyat || '';

		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'gemini-urun-sepete';
		btn.textContent = metin( 'sepeteEkle', 'Sepete Ekle' );

		btn.addEventListener( 'click', function () {
			if ( btn.disabled ) {
				return;
			}
			if ( sepeteEkle( urun ) ) {
				btn.disabled = true;
				btn.classList.add( 'is-eklendi' );
				btn.textContent = metin( 'sepette', 'Sepette ✓' );
			}
		} );

		govde.appendChild( adEl );
		govde.appendChild( fyEl );
		govde.appendChild( btn );
		kart.appendChild( govde );
		return kart;
	}

	function botBalonu( metin, urunler, hataMi ) {
		var grup = document.createElement( 'div' );
		grup.className = 'gemini-msg-grup';

		var el = document.createElement( 'div' );
		el.className = 'gemini-msg-bubble gemini-msg-bot';
		if ( hataMi ) {
			el.classList.add( 'gemini-msg-hata' );
		}

		var parcalar = String( metin ).split( '\n' );
		parcalar.forEach( function ( p, i ) {
			if ( i > 0 ) {
				el.appendChild( document.createElement( 'br' ) );
			}
			el.appendChild( document.createTextNode( p ) );
		} );
		grup.appendChild( el );

		if ( urunler && urunler.length ) {
			var liste = document.createElement( 'div' );
			liste.className = 'gemini-urun-kartlari';
			urunler.forEach( function ( u ) {
				if ( u && u.id ) {
					liste.appendChild( urunKartiBas( u ) );
				}
			} );
			if ( liste.childNodes.length ) {
				grup.appendChild( liste );
			}
		}

		if ( log ) {
			log.appendChild( grup );
			log.scrollTop = log.scrollHeight;
		}
		return grup;
	}

	function yaziyorGoster() {
		var el = document.createElement( 'div' );
		el.className = 'gemini-typing';
		el.textContent = metin( 'yaziyor', 'Yazıyor...' );
		if ( log ) {
			log.appendChild( el );
			log.scrollTop = log.scrollHeight;
		}
		return el;
	}

	function istek( veri ) {
		var govde = new URLSearchParams();
		govde.append( 'nonce', qmoData.nonce );
		Object.keys( veri ).forEach( function ( k ) {
			govde.append( k, veri[ k ] );
		} );

		return fetch( qmoData.ajaxUrl, {
			method: 'POST',
			body: govde,
			credentials: 'same-origin'
		} ).then( function ( r ) {
			return r.json().catch( function () {
				return { success: false };
			} );
		} );
	}

	function oturumBittiMi( yanit ) {
		return !! ( yanit && ! yanit.success && yanit.data &&
			( 'oturum_bitti' === yanit.data.kod || 'nonce' === yanit.data.kod ) );
	}

	function mesajGonder( hazirMetin ) {
		if ( kapaliMi ) {
			return;
		}

		var mesaj = ( hazirMetin || ( input ? input.value : '' ) ).trim();
		if ( ! mesaj ) {
			return;
		}

		sohbetHazir();
		balon( mesaj, 'user' );
		if ( input ) {
			input.value = '';
		}

		var yaziyor = yaziyorGoster();

		istek( {
			action: 'gemini_chat_req',
			message: mesaj,
			history: JSON.stringify( gecmis )
		} ).then( function ( yanit ) {
			yaziyor.remove();

			if ( ! yanit || ! yanit.success ) {
				if ( oturumBittiMi( yanit ) ) {
					balon( yanit.data.mesaj, 'bot', true );
					return;
				}
				var hata = ( yanit && yanit.data ) ? yanit.data : metin( 'birHata', 'Bir hata oluştu, lütfen tekrar deneyin.' );
				balon( 'string' === typeof hata ? hata : ( hata.mesaj || metin( 'birHataKisa', 'Bir hata oluştu.' ) ), 'bot', true );
				return;
			}

			var payload = yanit.data;
			var cevap   = 'string' === typeof payload ? payload : ( payload && payload.mesaj ? payload.mesaj : '' );
			var urunler = payload && payload.urunler && Array.isArray( payload.urunler ) ? payload.urunler : [];
			var ham     = cevap;

			if ( cevap.indexOf( '[CALL_WAITER]' ) !== -1 ) {
				cevap = cevap.replace( '[CALL_WAITER]', '' ).trim();
				istek( { action: 'garson_cagir' } );
			}

			if ( cevap.indexOf( '[CALL_BILL]' ) !== -1 ) {
				cevap = cevap.replace( '[CALL_BILL]', '' ).trim();
				istek( { action: 'hesap_iste' } );
			}

			if ( cevap.indexOf( '[BILEMEDI]' ) !== -1 ) {
				cevap = cevap.replace( /\[BILEMEDI\]/g, '' ).trim();
			}

			var eslesme = cevap.match( /\[SIPARIS\]([\s\S]*?)\[\/SIPARIS\]/i );
			if ( eslesme ) {
				cevap = cevap.replace( eslesme[ 0 ], '' ).trim();
				try {
					var siparisUrunler = JSON.parse( eslesme[ 1 ].trim() );
					if ( Array.isArray( siparisUrunler ) && siparisUrunler.length ) {
						istek( {
							action: 'gemini_bot_siparis',
							items: JSON.stringify( siparisUrunler )
						} ).then( function ( sy ) {
							if ( ! sy || ! sy.success ) {
								var msg = metin( 'siparisIletilemedi', 'Siparişiniz iletilemedi, lütfen garsona bildirin.' );
								if ( sy && sy.data ) {
									if ( 'string' === typeof sy.data ) {
										msg = sy.data;
									} else if ( sy.data.mesaj ) {
										msg = sy.data.mesaj;
									}
								}
								balon( msg, 'bot', true );
							}
						} ).catch( function () {
							balon( metin( 'siparisIletilemedi', 'Siparişiniz iletilemedi, lütfen garsona bildirin.' ), 'bot', true );
						} );
					}
				} catch ( e ) {
					// Bozuk JSON — sipariş gönderilmez.
				}
			}

			cevap = urunEtiketleriTemizle( cevap );

			gecmis.push( { role: 'user', parts: [ { text: mesaj } ] } );
			gecmis.push( { role: 'model', parts: [ { text: ham } ] } );
			if ( gecmis.length > 20 ) {
				gecmis = gecmis.slice( -20 );
			}

			if ( urunler.length ) {
				botBalonu( cevap, urunler );
			} else {
				balon( cevap, 'bot' );
			}
			if ( ! overlay.classList.contains( 'gemini-acik' ) ) {
				rozetGoster();
			}
		} ).catch( function () {
			yaziyor.remove();
			balon( metin( 'baglantiHatasi', 'Bağlantı hatası oluştu.' ), 'bot', true );
		} );
	}

	acButon.addEventListener( 'click', ac );
	acButon.addEventListener( 'keydown', function ( e ) {
		if ( 'Enter' === e.key || ' ' === e.key ) {
			e.preventDefault();
			ac();
		}
	} );
	if ( kapatEl ) {
		kapatEl.addEventListener( 'click', kapat );
	}
	if ( gonder ) {
		gonder.addEventListener( 'click', function () {
			mesajGonder();
		} );
	}
	if ( input ) {
		input.addEventListener( 'keypress', function ( e ) {
			if ( 13 === e.which || 'Enter' === e.key ) {
				e.preventDefault();
				mesajGonder();
			}
		} );
	}
	if ( basla ) {
		basla.addEventListener( 'click', function () {
			sohbetHazir();
			if ( input ) {
				input.focus();
			}
		} );
	}

	hazirlar.forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var soru = btn.getAttribute( 'data-question' ) || btn.textContent;
			mesajGonder( soru );
		} );
	} );

	if ( teaserX ) {
		teaserX.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			teaserGizle();
			try {
				window.sessionStorage.setItem( teaserAnahtar(), '1' );
			} catch ( err ) {
				// depolama yok
			}
		} );
	}

	if ( teaserAcik && teaser ) {
		var kapatildi = false;
		try {
			kapatildi = '1' === window.sessionStorage.getItem( teaserAnahtar() );
		} catch ( err ) {
			kapatildi = false;
		}
		if ( ! kapatildi ) {
			window.setTimeout( function () {
				if ( overlay.classList.contains( 'gemini-acik' ) ) {
					return;
				}
				teaser.hidden = false;
				rozetGoster();
			}, teaserGecikme );
		}
	}

	if ( welcome ) {
		if ( log ) {
			log.hidden = true;
		}
		if ( hazirKutu ) {
			hazirKutu.hidden = true;
		}
		if ( inputBar ) {
			inputBar.hidden = true;
		}
	}
}() );
