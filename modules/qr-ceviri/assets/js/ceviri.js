/**
 * QRMenu dil seçici.
 *
 * v2.0 ile Google Translate widget'ı kaldırıldı. qrmenuTranslate() artık
 * DOM'u çevirmeye çalışmıyor; dili cookie'ye yazıp sayfayı ?lang=xx ile
 * yeniden yüklüyor. Çeviriyi sunucu yapıyor.
 *
 * Fonksiyon imzası (langCode, flag, name) bilinçli olarak korundu: kısa kod
 * çıktısındaki inline onclick'ler ve sitede elle eklenmiş olabilecek
 * butonlar çalışmaya devam etsin.
 */
( function () {
	'use strict';

	var COOKIE = ( window.rmaCeviri && window.rmaCeviri.cookie ) || 'rma_lang';

	/**
	 * URL'deki bir sorgu parametresini güncelle/ekle.
	 *
	 * @param {string} url   Kaynak URL.
	 * @param {string} ad    Parametre adı.
	 * @param {string} deger Yeni değer.
	 * @return {string} Güncellenmiş URL.
	 */
	function updateQueryParam( url, ad, deger ) {
		var parca = String( url ).split( '#' );
		var temel = parca[ 0 ];
		var hash = parca[ 1 ] ? '#' + parca[ 1 ] : '';
		var bolum = temel.split( '?' );
		var yol = bolum[ 0 ];
		var sorgu = bolum[ 1 ] || '';

		var parcalar = sorgu ? sorgu.split( '&' ) : [];
		var yeni = [];
		var bulundu = false;
		var i;

		for ( i = 0; i < parcalar.length; i++ ) {
			if ( ! parcalar[ i ] ) {
				continue;
			}
			if ( parcalar[ i ].split( '=' )[ 0 ] === ad ) {
				if ( ! bulundu ) {
					yeni.push( ad + '=' + encodeURIComponent( deger ) );
					bulundu = true;
				}
				continue;
			}
			yeni.push( parcalar[ i ] );
		}

		if ( ! bulundu ) {
			yeni.push( ad + '=' + encodeURIComponent( deger ) );
		}

		return yol + ( yeni.length ? '?' + yeni.join( '&' ) : '' ) + hash;
	}

	/**
	 * Dili değiştir ve sayfayı o dilde yeniden yükle.
	 *
	 * @param {string} langCode Dil kodu.
	 * @param {string} flag     Bayrak (geriye dönük uyumluluk için).
	 * @param {string} name     Dil adı (geriye dönük uyumluluk için).
	 */
	function qrmenuTranslate( langCode, flag, name ) {
		if ( ! langCode ) {
			return;
		}

		try {
			document.cookie = COOKIE + '=' + encodeURIComponent( langCode ) +
				';path=/;max-age=31536000;samesite=lax';
		} catch ( e ) {}

		// Sepet (sepet.js) para birimini bu değerlerden okuyor.
		window.rmaCeviriDil = langCode;
		try {
			sessionStorage.setItem( 'rma_dil', langCode );
		} catch ( e ) {}

		try {
			if ( window.qrmsAnalitikOnyuz && typeof window.qrmsAnalitikOnyuz.yaz === 'function' ) {
				window.qrmsAnalitikOnyuz.yaz( 'lang_switch', { item_name: langCode } );
			}
		} catch ( e ) {}

		// Butonu hemen güncelle — yeniden yükleme arasındaki anı boş bırakmasın.
		document.querySelectorAll( '.qrmenu-lang-dropdown' ).forEach( function ( dropdown ) {
			var flagEl = dropdown.querySelector( '.qrmenu-current-flag' );
			var textEl = dropdown.querySelector( '.qrmenu-current-text' );
			if ( flagEl && flag ) {
				flagEl.textContent = flag;
			}
			if ( textEl && name ) {
				textEl.textContent = name;
			}
			var panel = dropdown.querySelector( '.qrmenu-options-panel' );
			if ( panel ) {
				panel.classList.remove( 'open' );
			}
		} );

		window.location.href = updateQueryParam( window.location.href, 'lang', langCode );
	}

	// Kısa kod çıktısındaki inline onclick'ler için global.
	window.qrmenuTranslate = qrmenuTranslate;
	window.rmaCeviriUpdateQueryParam = updateQueryParam;

	/* Dropdown aç/kapat — v1.7 davranışıyla aynı. */
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.qrmenu-current-btn' );

		if ( btn ) {
			e.stopPropagation();
			var parent = btn.closest( '.qrmenu-lang-dropdown' );
			var panel = parent ? parent.querySelector( '.qrmenu-options-panel' ) : null;
			if ( ! panel ) {
				return;
			}
			var isOpen = panel.classList.contains( 'open' );

			document.querySelectorAll( '.qrmenu-options-panel' ).forEach( function ( p ) {
				p.classList.remove( 'open' );
			} );

			if ( ! isOpen ) {
				panel.classList.add( 'open' );
			}
			return;
		}

		document.querySelectorAll( '.qrmenu-options-panel' ).forEach( function ( p ) {
			p.classList.remove( 'open' );
		} );
	} );
}() );
