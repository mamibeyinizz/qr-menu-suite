/**
 * [qmo_sepet] — menüden direkt sipariş.
 *
 * Ne yapar:
 *  - Ürün detay modalına not + adet + "Sepete Ekle" bloğu enjekte eder.
 *  - Alt sabit sepet çubuğu + açılır çekmece: adet düzenleme, ürün notu,
 *    silme, "Siparişi Gönder".
 *  - Sepet sessionStorage'da tutulur (masa oturumuyla birlikte yaşar).
 *  - Fiyat TL sabittir; seçili dile göre yaklaşık karşılık ikinci satırda
 *    gösterilir (ar/en/ru → USD, de/fr → EUR, tr → gizli).
 *  - Siparişi REST ucuna gönderir; masa oturum cookie'si tarayıcıca
 *    otomatik eklenir, nonce başlıkta gider.
 */
( function () {
	'use strict';

	var KOK = document.getElementById( 'qmo-sepet-root' );
	if ( ! KOK || KOK.dataset.qmoInit ) {
		return;
	}
	KOK.dataset.qmoInit = '1';

	var KUR = qmoSepet.kur; // { USD: x, EUR: y } — 1 TL karşılığı
	var KEY = 'qmo_sepet';

	/* ---- dil & para birimi ---- */

	function dil() {
		try {
			return ( window.rmaCeviriDil || sessionStorage.getItem( 'rma_dil' ) ||
				document.documentElement.lang || 'tr' ).slice( 0, 2 ).toLowerCase();
		} catch ( e ) {
			return 'tr';
		}
	}

	var CUR = {
		ar: [ 'USD', '$' ],
		en: [ 'USD', '$' ],
		ru: [ 'USD', '$' ],
		de: [ 'EUR', '€' ],
		fr: [ 'EUR', '€' ]
	};

	var TXT = {
		tr: { sepet: 'Sepet', sepetiniz: 'Sepetiniz', toplam: 'Toplam', gonder: 'Siparişi Gönder', bos: 'Sepetiniz boş',
			notPh: 'Ürün notu (isteğe bağlı)…', eklendi: 'Sepete eklendi', gonderildi: 'Siparişiniz mutfağa iletildi ✓',
			hata: 'Gönderilemedi, tekrar deneyin', tl: 'Ödeme TL üzerinden alınır.' },
		en: { sepet: 'Cart', sepetiniz: 'Your Cart', toplam: 'Total', gonder: 'Send Order', bos: 'Your cart is empty',
			notPh: 'Note for this item (optional)…', eklendi: 'Added to cart', gonderildi: 'Your order was sent ✓',
			hata: 'Failed, please try again', tl: 'Payment is charged in Turkish Lira (TL).' },
		ar: { sepet: 'السلة', sepetiniz: 'سلتك', toplam: 'المجموع', gonder: 'إرسال الطلب', bos: 'سلتك فارغة',
			notPh: 'ملاحظة على هذا الطبق (اختياري)…', eklendi: 'أُضيف إلى السلة', gonderildi: 'تم إرسال طلبك ✓',
			hata: 'فشل الإرسال، حاول مجدداً', tl: 'يتم الدفع بالليرة التركية.' },
		de: { sepet: 'Warenkorb', sepetiniz: 'Ihr Warenkorb', toplam: 'Summe', gonder: 'Bestellung senden', bos: 'Warenkorb ist leer',
			notPh: 'Hinweis zu diesem Gericht (optional)…', eklendi: 'Hinzugefügt', gonderildi: 'Bestellung gesendet ✓',
			hata: 'Fehlgeschlagen, erneut versuchen', tl: 'Die Zahlung erfolgt in TL.' },
		fr: { sepet: 'Panier', sepetiniz: 'Votre panier', toplam: 'Total', gonder: 'Envoyer', bos: 'Panier vide',
			notPh: 'Note pour ce plat (optionnel)…', eklendi: 'Ajouté au panier', gonderildi: 'Commande envoyée ✓',
			hata: 'Échec, réessayez', tl: 'Le paiement se fait en TL.' },
		ru: { sepet: 'Корзина', sepetiniz: 'Ваша корзина', toplam: 'Итого', gonder: 'Отправить заказ', bos: 'Корзина пуста',
			notPh: 'Пометка к блюду (необязательно)…', eklendi: 'Добавлено', gonderildi: 'Заказ отправлен ✓',
			hata: 'Ошибка, попробуйте снова', tl: 'Оплата производится в TL.' }
	};

	function T( k ) {
		var d = dil();
		return ( TXT[ d ] || TXT.tr )[ k ] || TXT.tr[ k ];
	}

	/* ---- sepet durumu ---- */

	function oku() {
		try {
			var v = JSON.parse( sessionStorage.getItem( KEY ) || '[]' );
			return Array.isArray( v ) ? v : [];
		} catch ( e ) {
			return [];
		}
	}

	function kaydet( s ) {
		try {
			sessionStorage.setItem( KEY, JSON.stringify( s ) );
		} catch ( e ) {}
	}

	function yaz( s ) {
		kaydet( s );
		ciz();
	}

	/* ---- fiyat çözümleme: ".rma-card-price" → "450 ₺" ---- */

	function fiyatSayi( t ) {
		t = ( t || '' ).replace( /[^\d.,]/g, '' ).replace( /\.(?=\d{3}\b)/g, '' ).replace( ',', '.' );
		var f = parseFloat( t );
		return isNaN( f ) ? 0 : f;
	}

	/* ---- UI referansları ---- */

	var bar     = document.getElementById( 'qmo-bar' );
	var badge   = document.getElementById( 'qmo-badge' );
	var barTot  = document.getElementById( 'qmo-bar-tot' );
	var ov      = document.getElementById( 'qmo-ov' );
	var dr      = document.getElementById( 'qmo-dr' );
	var list    = document.getElementById( 'qmo-list' );
	var tot     = document.getElementById( 'qmo-tot' );
	var yakEl   = document.getElementById( 'qmo-yak' );
	var toastEl = document.getElementById( 'qmo-toast' );
	var send    = document.getElementById( 'qmo-send' );

	function toast( m ) {
		toastEl.textContent = m;
		toastEl.classList.add( 'qmo-on' );
		clearTimeout( toastEl._t );
		toastEl._t = setTimeout( function () {
			toastEl.classList.remove( 'qmo-on' );
		}, 2200 );
	}

	function ac() {
		ov.classList.add( 'qmo-on' );
		dr.classList.add( 'qmo-on' );
	}

	function kapat() {
		ov.classList.remove( 'qmo-on' );
		dr.classList.remove( 'qmo-on' );
	}

	function yaklasik( tl ) {
		var d = dil();
		var c = CUR[ d ];
		if ( ! c || ! KUR[ c[ 0 ] ] ) {
			return '';
		}
		return '~' + c[ 1 ] + ( tl * KUR[ c[ 0 ] ] ).toFixed( 2 );
	}

	/* ---- ürün detay MODALINA not + adet + sepete ekle bloğu ----
	   Kartlara dokunulmaz: kart içine buton enjekte etmek grid yerleşimini
	   bozup görselleri bulanıklaştırıyordu. Kart tıklanınca modal açılır,
	   ekleme orada yapılır. */

	function modalEkle() {
		var eklendi = false;

		document.querySelectorAll( '.rma-modal-body:not([data-qmo])' ).forEach( function ( body ) {
			body.dataset.qmo = '1';

			var box = document.createElement( 'div' );
			box.className = 'qmo-md';

			var not = document.createElement( 'textarea' );
			not.className = 'qmo-not';
			not.maxLength = 200;
			not.placeholder = T( 'notPh' );

			var row = document.createElement( 'div' );
			row.className = 'qmo-md-row';

			var st = document.createElement( 'div' );
			st.className = 'qmo-st';
			var eksi = document.createElement( 'button' );
			eksi.type = 'button';
			eksi.textContent = '−';
			var adetEl = document.createElement( 'span' );
			adetEl.textContent = '1';
			var arti = document.createElement( 'button' );
			arti.type = 'button';
			arti.textContent = '+';
			st.appendChild( eksi );
			st.appendChild( adetEl );
			st.appendChild( arti );

			var ekle = document.createElement( 'button' );
			ekle.type = 'button';
			ekle.className = 'qmo-add';
			ekle.textContent = '+ ' + T( 'sepet' );

			row.appendChild( st );
			row.appendChild( ekle );
			box.appendChild( not );
			box.appendChild( row );

			eksi.addEventListener( 'click', function () {
				var v = parseInt( adetEl.textContent, 10 );
				if ( v > 1 ) {
					adetEl.textContent = v - 1;
				}
			} );

			arti.addEventListener( 'click', function () {
				var v = parseInt( adetEl.textContent, 10 );
				if ( v < 20 ) {
					adetEl.textContent = v + 1;
				}
			} );

			ekle.addEventListener( 'click', function ( ev ) {
				ev.preventDefault();
				ev.stopPropagation();

				var baslik = body.querySelector( '.rma-modal-title' );
				var ad     = baslik ? baslik.textContent.trim() : '';
				if ( ! ad ) {
					return;
				}

				var fiyatEl = body.querySelector( '.rma-modal-price' );
				var fy      = fiyatSayi( fiyatEl ? fiyatEl.textContent : '' );
				// Dış kapsayıcı: vitrin/slider modalı .qrms-detail-box üretir;
				// ana menü modalı hâlâ .rma-modal-box. Görsel class'ı
				// (.rma-modal-img) AJAX içeriğinde değişmedi.
				var kutu    = body.closest( '.qrms-detail-box, .rma-modal-box' ) || document;
				var imgEl   = kutu.querySelector( '.rma-modal-img' );
				var im      = imgEl ? imgEl.src : '';

				var adet = parseInt( adetEl.textContent, 10 ) || 1;
				var s    = oku();
				var m    = s.filter( function ( x ) {
					return x.id === ad;
				} )[ 0 ];

				if ( m ) {
					m.adet = Math.min( 20, m.adet + adet );
					if ( not.value ) {
						m.not = not.value;
					}
					if ( im ) {
						m.img = im;
					}
				} else {
					s.push( { id: ad, ad: ad, fiyat: fy, adet: adet, not: not.value || '', img: im } );
				}

				yaz( s );

				ekle.classList.add( 'qmo-added' );
				ekle.textContent = '✓';
				setTimeout( function () {
					ekle.classList.remove( 'qmo-added' );
					ekle.textContent = '+ ' + T( 'sepet' );
				}, 1200 );

				toast( T( 'eklendi' ) );
			} );

			body.appendChild( box );
			eklendi = true;
		} );

		return eklendi;
	}

	function modalAcikMi() {
		// Dış kapsayıcı qrms-detail-* (vitrin/slider); iç içerik hâlâ
		// .rma-modal-body. Ana menü modalının eski overlay/box adları da
		// durur — o şablon değişmedi.
		var m = document.querySelector( '.qrms-detail-overlay, .qrms-detail-box, .rma-modal-overlay, .rma-modal-box, .rma-modal-body' );
		if ( ! m ) {
			return false;
		}
		var r = m.getBoundingClientRect();
		return r.width > 0 && r.height > 0;
	}

	function barGizle( v ) {
		if ( bar ) {
			bar.classList.toggle( 'qmo-gizle', !! v );
		}
	}

	/* Performans: sürekli DOM dinlemek yerine, ürün kartına dokununca modal
	   AJAX ile gelene kadar KISA süreli (max ~3 sn) yokla; gelince enjekte
	   et ve dur. Menü geçişleri etkilenmez. */
	function modaliYakala() {
		barGizle( true ); // Not yazarken sepet çubuğu rahatsız etmesin.
		var kalan = 30;
		var iv = setInterval( function () {
			if ( modalEkle() || --kalan <= 0 ) {
				clearInterval( iv );
			}
		}, 100 );
	}

	/* ---- çekmece içeriğini çiz ---- */

	function satirCiz( x, i ) {
		var el = document.createElement( 'div' );
		el.className = 'qmo-it';

		var top = document.createElement( 'div' );
		top.className = 'qmo-it-top';

		if ( x.img ) {
			var img = document.createElement( 'img' );
			img.className = 'qmo-it-img';
			img.src = x.img;
			img.alt = '';
			img.loading = 'lazy';
			top.appendChild( img );
		}

		var ad = document.createElement( 'div' );
		ad.className = 'qmo-it-ad';
		ad.textContent = x.ad;
		top.appendChild( ad );

		var st = document.createElement( 'div' );
		st.className = 'qmo-st';
		var eksi = document.createElement( 'button' );
		eksi.type = 'button';
		eksi.textContent = '−';
		var adetEl = document.createElement( 'span' );
		adetEl.textContent = x.adet;
		var arti = document.createElement( 'button' );
		arti.type = 'button';
		arti.textContent = '+';
		st.appendChild( eksi );
		st.appendChild( adetEl );
		st.appendChild( arti );
		top.appendChild( st );

		var fy = document.createElement( 'div' );
		fy.className = 'qmo-it-fy';
		fy.appendChild( document.createTextNode( '₺' + ( x.fiyat * x.adet ).toFixed( 0 ) ) );
		var y2 = yaklasik( x.fiyat * x.adet );
		if ( y2 ) {
			fy.appendChild( document.createElement( 'br' ) );
			var small = document.createElement( 'small' );
			small.className = 'qmo-it-yak';
			small.textContent = y2;
			fy.appendChild( small );
		}
		top.appendChild( fy );

		var sil = document.createElement( 'button' );
		sil.type = 'button';
		sil.className = 'qmo-del';
		sil.setAttribute( 'aria-label', 'Sil' );
		sil.textContent = '🗑';
		top.appendChild( sil );

		var not = document.createElement( 'textarea' );
		not.className = 'qmo-not';
		not.maxLength = 200;
		not.placeholder = T( 'notPh' );
		not.value = x.not || '';

		el.appendChild( top );
		el.appendChild( not );

		eksi.addEventListener( 'click', function () {
			var v = oku();
			if ( ! v[ i ] ) {
				return;
			}
			if ( v[ i ].adet > 1 ) {
				v[ i ].adet--;
			} else {
				v.splice( i, 1 );
			}
			yaz( v );
		} );

		arti.addEventListener( 'click', function () {
			var v = oku();
			if ( ! v[ i ] ) {
				return;
			}
			v[ i ].adet = Math.min( 20, v[ i ].adet + 1 );
			yaz( v );
		} );

		sil.addEventListener( 'click', function () {
			var v = oku();
			v.splice( i, 1 );
			yaz( v );
		} );

		not.addEventListener( 'input', function ( ev ) {
			var v = oku();
			if ( ! v[ i ] ) {
				return;
			}
			v[ i ].not = ev.target.value;
			// Yeniden çizmeden kaydet: yazarken imleç kaybolmasın.
			kaydet( v );
		} );

		return el;
	}

	function ciz() {
		var s = oku();
		var n = 0;
		var t = 0;

		s.forEach( function ( x ) {
			n += x.adet;
			t += x.adet * x.fiyat;
		} );

		badge.textContent = n;
		barTot.textContent = '₺' + t.toFixed( 0 );
		document.getElementById( 'qmo-bar-txt' ).textContent = T( 'sepet' );
		document.getElementById( 'qmo-dr-title' ).textContent = T( 'sepetiniz' );
		document.getElementById( 'qmo-t-top' ).textContent = T( 'toplam' );
		document.getElementById( 'qmo-tl-not' ).textContent = T( 'tl' );
		send.textContent = T( 'gonder' );

		bar.classList.toggle( 'qmo-on', n > 0 );
		if ( 0 === n ) {
			kapat();
		}

		tot.textContent = '₺' + t.toFixed( 0 );
		yakEl.textContent = yaklasik( t );

		list.textContent = '';
		if ( ! s.length ) {
			var bos = document.createElement( 'div' );
			bos.className = 'qmo-empty';
			bos.textContent = T( 'bos' );
			list.appendChild( bos );
			return;
		}

		s.forEach( function ( x, i ) {
			list.appendChild( satirCiz( x, i ) );
		} );
	}

	/* ---- siparişi gönder ---- */

	function gonder() {
		var s = oku();
		if ( ! s.length ) {
			return;
		}

		send.disabled = true;

		fetch( qmoSepet.endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': qmoSepet.restNonce
			},
			credentials: 'same-origin',
			body: JSON.stringify( {
				dil: dil(),
				items: s.map( function ( x ) {
					return { urunAdi: x.ad, adet: x.adet, not: x.not || '' };
				} )
			} )
		} ).then( function ( r ) {
			return r.json().catch( function () {
				return { success: false };
			} );
		} ).then( function ( res ) {
			send.disabled = false;
			if ( res && res.success ) {
				yaz( [] );
				kapat();
				toast( T( 'gonderildi' ) );
				return;
			}
			toast( ( res && res.msg ) ? res.msg : T( 'hata' ) );
		} ).catch( function () {
			send.disabled = false;
			toast( T( 'hata' ) );
		} );
	}

	/* ---- bağla ---- */

	bar.addEventListener( 'click', ac );
	ov.addEventListener( 'click', kapat );
	document.getElementById( 'qmo-x' ).addEventListener( 'click', kapat );
	send.addEventListener( 'click', gonder );

	document.addEventListener( 'click', function ( e ) {
		// Ana menü .rma-card; vitrin .qrms-vitrin-card; slider .qmo-slider-product.
		var kart = e.target.closest ? e.target.closest( '.rma-card, .qrms-vitrin-card, .qmo-slider-product' ) : null;
		if ( kart ) {
			modaliYakala();
		}
	}, true );

	// Modal kapandığında çubuğu geri getir (yalnızca gizliyken çalışır).
	// Açıkken de yokla: vitrin AJAX'ı .qrms-detail-inner'a .rma-modal-body
	// basınca modalEkle o elemanı bulsun.
	setInterval( function () {
		if ( modalAcikMi() ) {
			modalEkle();
		}
		if ( bar && bar.classList.contains( 'qmo-gizle' ) && ! modalAcikMi() ) {
			barGizle( false );
		}
	}, 400 );

	modalEkle();
	ciz();
}() );
