/**
 * QR Menu Suite — sol menünün katlanabilir grup başlıkları.
 *
 * Satırların SIRASI ve kategori RENGİ sunucu tarafındadır (bkz.
 * QRMS_Admin::build_menu_rows): her satır `qrms-menu-item qrms-mg-<grup>`
 * sınıflarıyla basılır. Bu betiğin tek işi o sınıfları okuyup grupların
 * başına birer başlık satırı koymak ve aç/kapa durumunu saklamaktır.
 *
 * JavaScript çalışmazsa menü yine doğru sırada ve renk şeridiyle görünür —
 * sadece başlıklar ve katlama olmaz. Hiçbir sayfanın erişimi bu betiğe bağlı
 * değildir.
 */
( function () {
	'use strict';

	var AYAR = window.qrmsMenu;
	var ANAHTAR = 'qrmsMenuGroups';

	/**
	 * Kapalı grupların anahtar listesi.
	 *
	 * Gizli sekmede ya da depolama kapalıyken localStorage okumak istisna
	 * fırlatabilir; o durumda menü tümüyle açık başlar.
	 *
	 * @return {string[]} Kapalı grup anahtarları.
	 */
	function kapaliGruplar() {
		try {
			var ham = window.localStorage.getItem( ANAHTAR );
			var liste = ham ? JSON.parse( ham ) : [];

			return Array.isArray( liste ) ? liste : [];
		} catch ( e ) {
			return [];
		}
	}

	/**
	 * Kapalı grup listesini saklar.
	 *
	 * @param {string[]} liste Kapalı grup anahtarları.
	 * @return {void}
	 */
	function kaydet( liste ) {
		try {
			window.localStorage.setItem( ANAHTAR, JSON.stringify( liste ) );
		} catch ( e ) {
			// Depolama yoksa durum yalnızca bu sayfa için geçerli olur.
		}
	}

	/**
	 * Bir grubun satırlarını açar ya da kapatır.
	 *
	 * @param {HTMLElement}   baslik  Grup başlığı satırı.
	 * @param {HTMLElement[]} satirlar Gruptaki menü satırları.
	 * @param {boolean}       kapali  Kapatılsın mı?
	 * @return {void}
	 */
	function uygula( baslik, satirlar, kapali ) {
		var dugme = baslik.querySelector( '.qrms-menu-group-toggle' );

		baslik.classList.toggle( 'is-collapsed', kapali );

		if ( dugme ) {
			dugme.setAttribute( 'aria-expanded', kapali ? 'false' : 'true' );
		}

		satirlar.forEach( function ( satir ) {
			satir.classList.toggle( 'is-hidden', kapali );
		} );
	}

	/**
	 * Bir grubun başlık satırını kurar.
	 *
	 * @param {Object}        grup     {key, title}.
	 * @param {HTMLElement[]} satirlar Gruptaki menü satırları.
	 * @param {boolean}       ilk      Menünün ilk grubu mu?
	 * @return {HTMLElement} Başlık satırı.
	 */
	function baslikKur( grup, satirlar, ilk ) {
		var baslik = document.createElement( 'li' );
		var dugme = document.createElement( 'button' );
		var metin = document.createElement( 'span' );
		var ok = document.createElement( 'span' );
		var kimlikler = [];

		baslik.className = 'qrms-menu-group qrms-mg-' + grup.key + ( ilk ? ' qrms-menu-group-first' : '' );

		satirlar.forEach( function ( satir, sira ) {
			if ( ! satir.id ) {
				satir.id = 'qrms-menu-' + grup.key + '-' + sira;
			}

			kimlikler.push( satir.id );
		} );

		metin.className = 'qrms-menu-group-title';
		metin.textContent = grup.title;

		ok.className = 'qrms-menu-group-arrow dashicons dashicons-arrow-down-alt2';
		ok.setAttribute( 'aria-hidden', 'true' );

		dugme.type = 'button';
		dugme.className = 'qrms-menu-group-toggle';
		dugme.setAttribute( 'aria-expanded', 'true' );
		dugme.setAttribute( 'aria-controls', kimlikler.join( ' ' ) );

		if ( AYAR.collapse ) {
			dugme.setAttribute( 'aria-label', grup.title + ' — ' + AYAR.collapse );
		}

		dugme.appendChild( metin );
		dugme.appendChild( ok );
		baslik.appendChild( dugme );

		return baslik;
	}

	/**
	 * Menüyü gruplara ayırır.
	 *
	 * @return {void}
	 */
	function kur() {
		if ( ! AYAR || ! AYAR.groups || ! AYAR.groups.length ) {
			return;
		}

		// Üst "QR Menü" tıklanınca Genel Bakış açılsın (gruplar orada openAll ile açılır).
		// WordPress aksi halde ilk alt satırın (gruplamadan sonra Restoran Menü) adresine gider.
		if ( AYAR.overviewUrl ) {
			var ust = document.querySelector( '#adminmenu li#toplevel_page_qrms-overview > a.menu-top' );
			if ( ust ) {
				ust.setAttribute( 'href', AYAR.overviewUrl );
			}
		}

		var kapali = kapaliGruplar();
		var ilk = true;

		AYAR.groups.forEach( function ( grup ) {
			var satirlar = Array.prototype.slice.call(
				document.querySelectorAll( '#adminmenu li.qrms-menu-item.qrms-mg-' + grup.key )
			);

			if ( ! satirlar.length ) {
				return;
			}

			var baslik = baslikKur( grup, satirlar, ilk );

			satirlar[ 0 ].parentNode.insertBefore( baslik, satirlar[ 0 ] );
			ilk = false;

			// Açık sayfanın grubu her zaman açılır: kullanıcı nerede olduğunu
			// menüde görebilmeli. Genel Bakış'ta (üst menü veya "Genel Bakış"
			// satırı — ikisi aynı sayfa) bütün gruplar açık gelir.
			var acikSayfa = satirlar.some( function ( satir ) {
				return satir.classList.contains( 'current' );
			} );

			uygula( baslik, satirlar, ! AYAR.openAll && ! acikSayfa && kapali.indexOf( grup.key ) !== -1 );

			baslik.querySelector( '.qrms-menu-group-toggle' ).addEventListener( 'click', function () {
				var kapansin = ! baslik.classList.contains( 'is-collapsed' );
				var liste = kapaliGruplar().filter( function ( anahtar ) {
					return anahtar !== grup.key;
				} );

				uygula( baslik, satirlar, kapansin );

				if ( kapansin ) {
					liste.push( grup.key );
				}

				kaydet( liste );
			} );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', kur );
	} else {
		kur();
	}
}() );
