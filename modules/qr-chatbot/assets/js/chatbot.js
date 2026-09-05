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
	var ekranKaydi = [];
	var gonderKilitli = false;
	var OTURUM_ANAHTAR = ( typeof qmoData !== 'undefined' && qmoData.oturumAnahtari ) ? String( qmoData.oturumAnahtari ) : '';
	var GECMIS_STORAGE_KEY = OTURUM_ANAHTAR ? ( 'qmo_cb_gecmis_' + OTURUM_ANAHTAR ) : '';
	var rozetAcik = '1' === kok.dataset.badge;
	var kapaliMi  = '1' === kok.dataset.closed;
	var kapaliMsg = kok.dataset.closedMsg || 'Şu an kapalıyız, yakında görüşmek üzere.';
	var teaserAcik = '1' === kok.dataset.teaser;
	var teaserGecikme = Math.max( 1, parseInt( kok.dataset.teaserDelay || '4', 10 ) ) * 1000;
	var kayitliY = 0;
	var GARSON_COOLDOWN_MS = 90000;
	var GARSON_STORAGE_KEY = 'qmo_cb_garson_cagri';

	function garsonCooldownAktifMi() {
		try {
			var ts = parseInt( sessionStorage.getItem( GARSON_STORAGE_KEY ), 10 );
			return ts > 0 && ( Date.now() - ts ) < GARSON_COOLDOWN_MS;
		} catch ( e ) {
			return false;
		}
	}

	function garsonCooldownKaydet() {
		try {
			sessionStorage.setItem( GARSON_STORAGE_KEY, String( Date.now() ) );
		} catch ( e ) {}
	}

	function metinParcala( el, metin ) {
		var parcalar = String( metin ).split( '\n' );
		parcalar.forEach( function ( p, i ) {
			if ( i > 0 ) {
				el.appendChild( document.createElement( 'br' ) );
			}
			el.appendChild( document.createTextNode( p ) );
		} );
	}

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

	var istek = window.qmoChatShared.istek;
	var metin = window.qmoChatShared.metin;

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

	function balon( mesajMetni, tip, hataMi ) {
		var el = document.createElement( 'div' );
		el.className = 'gemini-msg-bubble ' + ( 'user' === tip ? 'gemini-msg-user' : 'gemini-msg-bot' );
		if ( hataMi ) {
			el.classList.add( 'gemini-msg-hata' );
		}
		if ( 'sys' === tip ) {
			el.classList.add( 'gemini-msg-sys' );
		}

		metinParcala( el, mesajMetni );

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

	function botBalonu( mesajMetni, urunler, hataMi ) {
		var grup = document.createElement( 'div' );
		grup.className = 'gemini-msg-grup';

		var el = document.createElement( 'div' );
		el.className = 'gemini-msg-bubble gemini-msg-bot';
		if ( hataMi ) {
			el.classList.add( 'gemini-msg-hata' );
		}

		var parcalar = String( mesajMetni ).split( '\n' );
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

	function eskalasyonBalonu( mesajMetni ) {
		var grup = document.createElement( 'div' );
		grup.className = 'gemini-msg-grup';

		if ( mesajMetni ) {
			var el = document.createElement( 'div' );
			el.className = 'gemini-msg-bubble gemini-msg-bot';
			metinParcala( el, mesajMetni );
			grup.appendChild( el );
		}

		var kutu = document.createElement( 'div' );
		kutu.className = 'gemini-eskalasyon';

		var mesajEl = document.createElement( 'p' );
		mesajEl.className = 'gemini-eskalasyon-metin';
		mesajEl.textContent = metin( 'eskalasyonMsg', 'Bu konuda emin olamadım. Garson çağırmamı ister misiniz?' );
		kutu.appendChild( mesajEl );

		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'gemini-eskalasyon-btn';
		var cagrildi = garsonCooldownAktifMi();
		btn.textContent = cagrildi
			? metin( 'garsonCagrildi', 'Garson çağrıldı ✓' )
			: metin( 'garsonCagir', 'Garson Çağır' );
		btn.disabled = cagrildi;

		btn.addEventListener( 'click', function () {
			if ( btn.disabled ) {
				return;
			}
			btn.disabled = true;
			istek( { action: 'garson_cagir' } ).then( function ( yanit ) {
				if ( yanit && yanit.success ) {
					garsonCooldownKaydet();
					btn.textContent = metin( 'garsonCagrildi', 'Garson çağrıldı ✓' );
					return;
				}
				btn.disabled = false;
			} ).catch( function () {
				btn.disabled = false;
			} );
		} );

		kutu.appendChild( btn );
		grup.appendChild( kutu );

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

	function oturumBittiMi( yanit ) {
		return !! ( yanit && ! yanit.success && yanit.data &&
			( 'oturum_bitti' === yanit.data.kod || 'nonce' === yanit.data.kod ) );
	}

	/* ---- konuşma kalıcılığı (sayfa yenilemeye dayanıklı) ----
	   gecmis (AI bağlamı) ve ekranKaydi (görünür balonlar) oturuma özel bir
	   sessionStorage anahtarında saklanır. Anahtar sunucuda türetilen,
	   hassas olmayan bir oturum kimliğini (qmoData.oturumAnahtari) içerir;
	   masa oturumu değişirse (yeni QR okutma → yeni issued zamanı) anahtar
	   da değişir ve eski kayıt asla okunmaz. */

	function gecmisKaydet() {
		if ( ! GECMIS_STORAGE_KEY ) {
			return;
		}
		try {
			sessionStorage.setItem( GECMIS_STORAGE_KEY, JSON.stringify( {
				gecmis: gecmis,
				ekran: ekranKaydi
			} ) );
		} catch ( e ) {}
	}

	function gecmisYukle() {
		if ( ! GECMIS_STORAGE_KEY ) {
			return;
		}
		try {
			var ham = sessionStorage.getItem( GECMIS_STORAGE_KEY );
			if ( ! ham ) {
				return;
			}
			var v = JSON.parse( ham );
			if ( v && Array.isArray( v.gecmis ) ) {
				gecmis = v.gecmis.slice( -20 );
			}
			if ( v && Array.isArray( v.ekran ) && v.ekran.length ) {
				ekranKaydi = v.ekran.slice( -20 );
				sohbetHazir();
				ekranKaydi.forEach( function ( satir ) {
					if ( satir && satir.metin ) {
						balon( satir.metin, 'bot' === satir.rol ? 'bot' : 'user' );
					}
				} );
			}
		} catch ( e ) {}
	}

	function ekranaKaydet( rol, metinDegeri ) {
		ekranKaydi.push( { rol: rol, metin: metinDegeri } );
		if ( ekranKaydi.length > 20 ) {
			ekranKaydi = ekranKaydi.slice( -20 );
		}
	}

	/* ---- gönderim kilidi ----
	   İstek sürerken input/gönder butonu kilitlenmezse Enter'a basılı
	   tutmak veya art arda tıklamak paralel Gemini istekleri tetikler;
	   yanıtlar ağ gecikmesine göre sırasız dönebilir ve oturum mesaj
	   limiti gereksiz yere tüketilir. */

	function kilitKapa() {
		gonderKilitli = true;
		if ( input ) {
			input.disabled = true;
		}
		if ( gonder ) {
			gonder.disabled = true;
		}
	}

	function kilitAc() {
		gonderKilitli = false;
		if ( input ) {
			input.disabled = false;
		}
		if ( gonder ) {
			gonder.disabled = false;
		}
	}

	function hataBalonuTekrarDeneli( mesajMetni, orijinalMesaj ) {
		var grup = document.createElement( 'div' );
		grup.className = 'gemini-msg-grup';

		var el = document.createElement( 'div' );
		el.className = 'gemini-msg-bubble gemini-msg-bot gemini-msg-hata';
		metinParcala( el, mesajMetni );
		grup.appendChild( el );

		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'gemini-retry-btn';
		btn.textContent = metin( 'tekrarDene', 'Tekrar Dene' );
		btn.addEventListener( 'click', function () {
			if ( btn.disabled || gonderKilitli ) {
				return;
			}
			btn.disabled = true;
			grup.remove();
			gonderIstek( orijinalMesaj, false );
		} );
		grup.appendChild( btn );

		if ( log ) {
			log.appendChild( grup );
			log.scrollTop = log.scrollHeight;
		}
		return grup;
	}

	function gonderIstek( mesaj, otomatikTekrarDenensin ) {
		if ( gonderKilitli ) {
			return;
		}
		kilitKapa();
		var yaziyor = yaziyorGoster();

		istek( {
			action: 'gemini_chat_req',
			message: mesaj,
			history: JSON.stringify( gecmis )
		} ).then( function ( yanit ) {
			yaziyor.remove();
			kilitAc();

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

			var eskalasyon = cevap.indexOf( '[ESCALATE]' ) !== -1;
			if ( eskalasyon ) {
				cevap = cevap.replace( /\[ESCALATE\]/g, '' ).trim();
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

			// Geçmişe (AI bağlamı) yalnızca TEMİZLENMİŞ metin yazılır; ham
			// (etiketli) metin yazılırsa model kendi kontrol etiketlerini
			// ([SIPARIS] bloğu dahil) bir sonraki turda geçmiş olarak geri
			// görür ve gereksiz yere kafası karışabilir.
			gecmis.push( { role: 'user', parts: [ { text: mesaj } ] } );
			gecmis.push( { role: 'model', parts: [ { text: cevap } ] } );
			if ( gecmis.length > 20 ) {
				gecmis = gecmis.slice( -20 );
			}
			ekranaKaydet( 'bot', cevap );
			gecmisKaydet();

			if ( eskalasyon ) {
				eskalasyonBalonu( cevap );
			} else if ( urunler.length ) {
				botBalonu( cevap, urunler );
			} else {
				balon( cevap, 'bot' );
			}
			if ( ! overlay.classList.contains( 'gemini-acik' ) ) {
				rozetGoster();
			}
		} ).catch( function () {
			yaziyor.remove();
			kilitAc();

			if ( otomatikTekrarDenensin ) {
				window.setTimeout( function () {
					gonderIstek( mesaj, false );
				}, 800 );
				return;
			}

			hataBalonuTekrarDeneli( metin( 'baglantiHatasi', 'Bağlantı hatası oluştu.' ), mesaj );
		} );
	}

	function mesajGonder( hazirMetin ) {
		if ( kapaliMi || gonderKilitli ) {
			return;
		}

		var mesaj = ( hazirMetin || ( input ? input.value : '' ) ).trim();
		if ( ! mesaj ) {
			return;
		}

		sohbetHazir();
		balon( mesaj, 'user' );
		ekranaKaydet( 'user', mesaj );
		gecmisKaydet();
		if ( input ) {
			input.value = '';
		}

		gonderIstek( mesaj, true );
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

	// Karşılama ekranı gizleme kararından SONRA çalışır: restore edilecek
	// bir geçmiş varsa sohbetHazir() burada log/input'u tekrar açar.
	gecmisYukle();
}() );
