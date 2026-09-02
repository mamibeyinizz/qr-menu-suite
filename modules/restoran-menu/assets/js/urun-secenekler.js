/**
 * Porsiyon / ekstra / rozet tablolarının satır ekle-sil davranışı.
 *
 * Satırlar klonlanmaz: her tablo kendi <template class="rma-tekrar-sablon">
 * satırını taşır, `__i__` yer tutucusu sıradaki indisle değiştirilir. Böylece
 * tablo boşken de satır eklenebilir ve alan adları PHP tarafında tek yerde
 * tanımlı kalır.
 *
 * Bağımlılık yok (jQuery dahil); ürün düzenleme ekranında ve "Seçenek & Rozet"
 * sayfasında yüklenir.
 */
( function () {
	'use strict';

	/**
	 * Hedef tabloyu bulur.
	 *
	 * @param {string} ad data-rma-tekrar değeri.
	 * @return {HTMLElement|null} Tablo.
	 */
	function tablo( ad ) {
		return document.querySelector( '[data-rma-tekrar="' + ad + '"]' );
	}

	/**
	 * Tabloya bir satır ekler.
	 *
	 * @param {HTMLElement} tbl Tablo.
	 * @return {void}
	 */
	function satirEkle( tbl ) {
		if ( ! tbl ) {
			return;
		}

		var govde   = tbl.querySelector( 'tbody' );
		var sablon  = tbl.querySelector( 'template.rma-tekrar-sablon' );
		var azami   = parseInt( tbl.getAttribute( 'data-azami' ), 10 ) || 30;
		var mevcut  = govde ? govde.querySelectorAll( 'tr.rma-tekrar-satir' ).length : 0;

		if ( ! govde || ! sablon || mevcut >= azami ) {
			return;
		}

		// İndis çakışmasın diye mevcut satır sayısı değil, o ana kadarki en
		// büyük indis + 1 kullanılır (aradan satır silinmiş olabilir).
		var enBuyuk = -1;
		govde.querySelectorAll( 'input[name]' ).forEach( function ( girdi ) {
			var eslesme = girdi.name.match( /\[(\d+)\]\[[^\]]+\]$/ );
			if ( eslesme ) {
				enBuyuk = Math.max( enBuyuk, parseInt( eslesme[ 1 ], 10 ) );
			}
		} );

		var html = sablon.innerHTML.split( '__i__' ).join( String( enBuyuk + 1 ) );
		var gecici = document.createElement( 'tbody' );
		gecici.innerHTML = html;

		while ( gecici.firstElementChild ) {
			govde.appendChild( gecici.firstElementChild );
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var hedef = e.target;
		if ( ! hedef || ! hedef.closest ) {
			return;
		}

		var ekle = hedef.closest( '.rma-tekrar-ekle' );
		if ( ekle ) {
			e.preventDefault();
			satirEkle( tablo( ekle.getAttribute( 'data-hedef' ) ) );
			return;
		}

		var sil = hedef.closest( '.rma-tekrar-sil' );
		if ( sil ) {
			e.preventDefault();
			var satir = sil.closest( 'tr' );
			if ( satir ) {
				satir.parentNode.removeChild( satir );
			}
			return;
		}

		var listeSil = hedef.closest( '.rma-liste-sil' );
		if ( listeSil ) {
			e.preventDefault();
			var kap = listeSil.closest( '.rma-liste-kap' );
			if ( kap ) {
				kap.parentNode.removeChild( kap );
			}
			return;
		}

		var listeEkle = hedef.closest( '[data-rma-liste-ekle]' );
		if ( listeEkle ) {
			e.preventDefault();
			listeEkleUygula();
		}
	} );

	/**
	 * "Seçenek & Rozet" sayfasında yeni bir ekstra listesi kabı ekler.
	 *
	 * @return {void}
	 */
	function listeEkleUygula() {
		var kaplar = document.querySelector( '[data-rma-listeler]' );
		var sablon = document.getElementById( 'rma-liste-sablon' );

		if ( ! kaplar || ! sablon ) {
			return;
		}

		var enBuyuk = -1;
		kaplar.querySelectorAll( 'input[name^="rma_ekstra_listeleri["]' ).forEach( function ( girdi ) {
			var eslesme = girdi.name.match( /^rma_ekstra_listeleri\[(\d+)\]/ );
			if ( eslesme ) {
				enBuyuk = Math.max( enBuyuk, parseInt( eslesme[ 1 ], 10 ) );
			}
		} );

		var gecici = document.createElement( 'div' );
		gecici.innerHTML = sablon.innerHTML.split( '__li__' ).join( String( enBuyuk + 1 ) );

		while ( gecici.firstElementChild ) {
			kaplar.appendChild( gecici.firstElementChild );
		}
	}

	/**
	 * Servis saati alanları yalnızca "Bu ürüne özel saat" seçiliyken açılır.
	 *
	 * @return {void}
	 */
	function servisAlaniGuncelle() {
		var secili = document.querySelector( 'input[name="rma_servis_mod"]:checked' );
		var alan   = document.querySelector( '.rma-secenek-kutu .rma-servis-alan' );

		if ( ! secili || ! alan ) {
			return;
		}

		alan.classList.toggle( 'rma-pasif', 'ozel' !== secili.value );
	}

	document.addEventListener( 'change', function ( e ) {
		if ( e.target && 'rma_servis_mod' === e.target.name ) {
			servisAlaniGuncelle();
		}
	} );

	servisAlaniGuncelle();
}() );
