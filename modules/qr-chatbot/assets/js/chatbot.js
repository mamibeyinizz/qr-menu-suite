/**
 * QR Menu Official — Chatbot davranışı.
 *
 * Eski sürümde bu kod kısa kod çıktısının içinde inline <script> olarak
 * basılıyordu ve jQuery'ye bağımlıydı. Artık ayrı dosyada ve saf JS.
 *
 * Güvenlik notları:
 *  - Her istek qmoData.nonce ile imzalanır.
 *  - Masa numarası artık istemciden GÖNDERİLMEZ; sunucu oturum
 *    cookie'sinden okur. Bu yüzden aşağıda hiçbir yerde masa_no yoktur.
 *  - Asistan yanıtı DOM'a HTML olarak değil, kaçırılmış metin olarak
 *    yazılır (yalnızca satır sonları <br>'a çevrilir).
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

	// Overlay'i body'ye taşı: tema kapsayıcılarının transform/overflow
	// kuralları tam ekran panelin konumunu bozabiliyor.
	document.body.appendChild( overlay );

	var log     = overlay.querySelector( '.gemini-chat-log' );
	var input   = overlay.querySelector( '.gemini-chat-input' );
	var gonder  = overlay.querySelector( '.gemini-chat-send' );
	var kapatEl = overlay.querySelector( '.gemini-chat-close' );

	// Gemini'ye gönderilen çok adımlı konuşma geçmişi.
	var gecmis = [];

	/* ------------------------------------------------------------------ */

	function ac() {
		overlay.classList.add( 'gemini-acik' );
		document.body.classList.add( 'gemini-no-scroll' );
		log.scrollTop = log.scrollHeight;
	}

	function kapat() {
		overlay.classList.remove( 'gemini-acik' );
		document.body.classList.remove( 'gemini-no-scroll' );
	}

	/**
	 * Baloncuk ekle. Metin daima düz metin olarak eklenir.
	 *
	 * @param {string}  metin   Gösterilecek metin.
	 * @param {string}  tip     'user' | 'bot'.
	 * @param {boolean} hataMi  Kırmızı gösterilsin mi.
	 * @return {Element} Eklenen eleman.
	 */
	function balon( metin, tip, hataMi ) {
		var el = document.createElement( 'div' );
		el.className = 'gemini-msg-bubble ' + ( 'user' === tip ? 'gemini-msg-user' : 'gemini-msg-bot' );
		if ( hataMi ) {
			el.classList.add( 'gemini-msg-hata' );
		}

		// Satır sonlarını <br> yap ama metnin kendisini KAÇIR.
		var parcalar = String( metin ).split( '\n' );
		parcalar.forEach( function ( p, i ) {
			if ( i > 0 ) {
				el.appendChild( document.createElement( 'br' ) );
			}
			el.appendChild( document.createTextNode( p ) );
		} );

		log.appendChild( el );
		log.scrollTop = log.scrollHeight;
		return el;
	}

	function yaziyorGoster() {
		var el = document.createElement( 'div' );
		el.className = 'gemini-typing';
		el.textContent = 'Yazıyor...';
		log.appendChild( el );
		log.scrollTop = log.scrollHeight;
		return el;
	}

	/**
	 * admin-ajax.php'ye POST. Nonce her istekte eklenir.
	 *
	 * @param {Object} veri Gönderilecek alanlar.
	 * @return {Promise<Object>} Sunucu yanıtı.
	 */
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

	/**
	 * Oturum bitti yanıtını yakala.
	 *
	 * @param {Object} yanit Sunucu yanıtı.
	 * @return {boolean} true ise oturum sonlanmış.
	 */
	function oturumBittiMi( yanit ) {
		return !! ( yanit && ! yanit.success && yanit.data &&
			( 'oturum_bitti' === yanit.data.kod || 'nonce' === yanit.data.kod ) );
	}

	/* ------------------------------------------------------------------ */

	function mesajGonder() {
		var mesaj = input.value.trim();
		if ( ! mesaj ) {
			return;
		}

		balon( mesaj, 'user' );
		input.value = '';

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
				var hata = ( yanit && yanit.data ) ? yanit.data : 'Bir hata oluştu, lütfen tekrar deneyin.';
				balon( 'string' === typeof hata ? hata : ( hata.mesaj || 'Bir hata oluştu.' ), 'bot', true );
				return;
			}

			var cevap = yanit.data || '';
			var ham   = cevap; // Etiketler temizlenmeden önceki hali; geçmişe bu gider.

			// Garson çağrısı.
			if ( cevap.indexOf( '[CALL_WAITER]' ) !== -1 ) {
				cevap = cevap.replace( '[CALL_WAITER]', '' ).trim();
				istek( { action: 'garson_cagir' } );
			}

			// Hesap talebi.
			if ( cevap.indexOf( '[CALL_BILL]' ) !== -1 ) {
				cevap = cevap.replace( '[CALL_BILL]', '' ).trim();
				istek( { action: 'hesap_iste' } );
			}

			// Sipariş bloğu: [SIPARIS]json[/SIPARIS].
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
								var msg = 'Siparişiniz iletilemedi, lütfen garsona bildirin.';
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
							balon( 'Siparişiniz iletilemedi, lütfen garsona bildirin.', 'bot', true );
						} );
					}
				} catch ( e ) {
					// Bozuk JSON — sipariş gönderilmez, sohbet normal devam eder.
				}
			}

			gecmis.push( { role: 'user', parts: [ { text: mesaj } ] } );
			gecmis.push( { role: 'model', parts: [ { text: ham } ] } );
			// Geçmişi sınırla: sunucu da kırpıyor ama boşuna veri taşımayalım.
			if ( gecmis.length > 20 ) {
				gecmis = gecmis.slice( -20 );
			}

			balon( cevap, 'bot' );
		} ).catch( function () {
			yaziyor.remove();
			balon( 'Bağlantı hatası oluştu.', 'bot', true );
		} );
	}

	/* ------------------------------------------------------------------ */

	acButon.addEventListener( 'click', ac );
	kapatEl.addEventListener( 'click', kapat );
	gonder.addEventListener( 'click', mesajGonder );

	input.addEventListener( 'keypress', function ( e ) {
		if ( 13 === e.which || 'Enter' === e.key ) {
			e.preventDefault();
			mesajGonder();
		}
	} );
}() );
