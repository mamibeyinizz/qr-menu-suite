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

			var cevap = yanit.data || '';
			var ham   = cevap;

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
					var urunler = JSON.parse( eslesme[ 1 ].trim() );
					if ( Array.isArray( urunler ) && urunler.length ) {
						istek( {
							action: 'gemini_bot_siparis',
							items: JSON.stringify( urunler )
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

			gecmis.push( { role: 'user', parts: [ { text: mesaj } ] } );
			gecmis.push( { role: 'model', parts: [ { text: ham } ] } );
			if ( gecmis.length > 20 ) {
				gecmis = gecmis.slice( -20 );
			}

			balon( cevap, 'bot' );
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
