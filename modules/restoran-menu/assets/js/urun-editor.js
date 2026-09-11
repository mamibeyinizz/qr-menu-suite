/**
 * Ürün ekle / düzenle ekranının arayüz katmanı.
 *
 * Bağımlılık YOKTUR (jQuery dahil) ve yalnızca bu ekranda yüklenir. Hiçbir
 * alan silinmez, yeniden adlandırılmaz ve sunucuya ek istek atılmaz: tüm
 * listeler sayfada zaten basılı olan yerel <input> öğeleridir, bileşen
 * onları taşıyıp çevresine arayüz kurar. Kutucukların kendisi DOM'da kalır,
 * böylece klavye ve ekran okuyucu davranışı korunur; form gönderimi
 * değişmez.
 *
 * İçerik:
 *  1. Aranabilir seçim bileşeni (alerjen, rozet, malzeme, hazır liste, kategori)
 *  2. Katlanabilir bölümler
 *  3. "Temel Bilgiler" kartı — fiyat, kategori ve görsel kutularının taşınması
 *  4. Başlık/açıklama alanlarının etiketlenmesi
 */

( function () {
	'use strict';

	var METIN = ( window.RMA_URUN_EDITOR && window.RMA_URUN_EDITOR.i18n ) || {};

	/**
	 * Çeviri metni.
	 *
	 * @param {string} anahtar Anahtar.
	 * @param {string} varsayilan Yedek metin.
	 * @return {string} Metin.
	 */
	function t( anahtar, varsayilan ) {
		return METIN[ anahtar ] || varsayilan;
	}

	/* =============================================================
	   1. ARANABİLİR SEÇİM BİLEŞENİ
	============================================================= */

	/**
	 * Bir seçenek girdisinin satır (gizlenecek) öğesi.
	 *
	 * Kategori listesinde satır <li>, diğer listelerde <label>'dır.
	 *
	 * @param {HTMLInputElement} girdi Kutucuk.
	 * @return {HTMLElement} Satır.
	 */
	function satir( girdi ) {
		return girdi.closest( 'li' ) || girdi.closest( 'label' ) || girdi.parentNode;
	}

	/**
	 * Seçeneğin görünen adı.
	 *
	 * @param {HTMLInputElement} girdi Kutucuk.
	 * @return {string} Ad.
	 */
	function secenekAdi( girdi ) {
		var etiket = girdi.closest( 'label' );
		var ad = etiket ? etiket.textContent : '';

		return ad.replace( /\s+/g, ' ' ).trim();
	}

	/**
	 * Tek bir aranabilir seçim kurar.
	 *
	 * @param {HTMLElement} kok .qrms-pe-secim öğesi.
	 * @return {void}
	 */
	function secimKur( kok ) {
		var kaynak = kok.querySelector( '.qrms-pe-secim-kaynak' );

		if ( ! kaynak || kok.classList.contains( 'is-hazir' ) ) {
			return;
		}

		var coklu = 'tek' !== kok.getAttribute( 'data-qrms-secim' );
		var etiketMetni = kok.getAttribute( 'data-etiket' ) || t( 'sec', 'Seçin…' );
		var aramaMetni = kok.getAttribute( 'data-ara' ) || t( 'ara', 'Ara…' );

		var chipler = document.createElement( 'div' );
		chipler.className = 'qrms-pe-secim-chipler';

		var tetik = document.createElement( 'button' );
		tetik.type = 'button';
		tetik.className = 'qrms-pe-secim-tetik';
		tetik.setAttribute( 'aria-expanded', 'false' );
		tetik.setAttribute( 'aria-haspopup', 'true' );

		var tetikMetin = document.createElement( 'span' );
		tetikMetin.className = 'qrms-pe-secim-tetik-metin';

		var tetikOk = document.createElement( 'span' );
		tetikOk.className = 'qrms-pe-secim-tetik-ok';
		tetikOk.setAttribute( 'aria-hidden', 'true' );

		tetik.appendChild( tetikMetin );
		tetik.appendChild( tetikOk );

		var panel = document.createElement( 'div' );
		panel.className = 'qrms-pe-secim-panel';
		panel.hidden = true;

		var arama = document.createElement( 'input' );
		arama.type = 'search';
		arama.className = 'qrms-pe-input qrms-pe-secim-arama';
		arama.placeholder = aramaMetni;
		arama.setAttribute( 'aria-label', aramaMetni );
		arama.autocomplete = 'off';

		var bos = document.createElement( 'p' );
		bos.className = 'qrms-pe-secim-bos';
		bos.textContent = t( 'sonucYok', 'Sonuç yok' );
		bos.hidden = true;

		kok.insertBefore( chipler, kaynak );
		kok.insertBefore( tetik, kaynak );
		kok.appendChild( panel );
		panel.appendChild( arama );
		panel.appendChild( kaynak );
		panel.appendChild( bos );
		kok.classList.add( 'is-hazir' );

		/**
		 * Listedeki tüm kutucuklar.
		 *
		 * @return {HTMLInputElement[]} Kutucuklar.
		 */
		function girdiler() {
			return Array.prototype.slice.call( kaynak.querySelectorAll( 'input[type="checkbox"]' ) );
		}

		/**
		 * Chip'leri ve tetik metnini seçili duruma göre yeniler.
		 *
		 * @return {void}
		 */
		function tazele() {
			var secili = girdiler().filter( function ( g ) {
				return g.checked;
			} );

			chipler.textContent = '';

			secili.forEach( function ( girdi ) {
				var chip = document.createElement( 'span' );
				chip.className = 'qrms-pe-chip';

				var ad = document.createElement( 'span' );
				ad.className = 'qrms-pe-chip-ad';
				ad.textContent = secenekAdi( girdi );

				var sil = document.createElement( 'button' );
				sil.type = 'button';
				sil.className = 'qrms-pe-chip-sil';
				sil.innerHTML = '&times;';
				sil.setAttribute( 'aria-label', t( 'kaldir', 'Kaldır' ) + ': ' + ad.textContent );
				sil.addEventListener( 'click', function () {
					girdi.checked = false;
					girdi.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					tetik.focus();
				} );

				chip.appendChild( ad );
				chip.appendChild( sil );
				chipler.appendChild( chip );
			} );

			if ( ! secili.length ) {
				tetik.classList.add( 'is-bos' );
				tetikMetin.textContent = etiketMetni;
			} else if ( coklu ) {
				tetik.classList.remove( 'is-bos' );
				tetikMetin.textContent = t( 'secildi', '%d seçildi' ).replace( '%d', String( secili.length ) );
			} else {
				tetik.classList.remove( 'is-bos' );
				tetikMetin.textContent = secenekAdi( secili[ 0 ] );
			}

			// Tek seçimde chip'e gerek yok: seçim zaten tetikte yazılı.
			chipler.hidden = ! coklu;
		}

		/**
		 * Arama kutusuna göre satırları süzer.
		 *
		 * @return {void}
		 */
		function suz() {
			var q = arama.value.trim().toLocaleLowerCase( 'tr' );
			var gorunen = 0;

			girdiler().forEach( function ( girdi ) {
				var hedef = satir( girdi );
				var uyar = '' === q || secenekAdi( girdi ).toLocaleLowerCase( 'tr' ).indexOf( q ) !== -1;

				hedef.hidden = ! uyar;

				if ( uyar ) {
					gorunen++;
				}
			} );

			bos.hidden = gorunen > 0;
		}

		/**
		 * Paneli açar/kapatır.
		 *
		 * @param {boolean} ac Açılsın mı?
		 * @return {void}
		 */
		function degistir( ac ) {
			panel.hidden = ! ac;
			tetik.setAttribute( 'aria-expanded', ac ? 'true' : 'false' );

			if ( ac ) {
				arama.value = '';
				suz();
				arama.focus();
			}
		}

		/**
		 * Panelde görünür kutucuklar.
		 *
		 * @return {HTMLInputElement[]} Kutucuklar.
		 */
		function gorunurGirdiler() {
			return girdiler().filter( function ( g ) {
				return ! satir( g ).hidden;
			} );
		}

		/**
		 * Okla gezinme.
		 *
		 * @param {number} yon +1 | -1
		 * @return {void}
		 */
		function gez( yon ) {
			var liste = gorunurGirdiler();

			if ( ! liste.length ) {
				return;
			}

			var simdi = liste.indexOf( document.activeElement );
			var sonraki = simdi === -1 ? ( yon > 0 ? 0 : liste.length - 1 ) : simdi + yon;

			if ( sonraki < 0 ) {
				arama.focus();
				return;
			}

			liste[ Math.min( sonraki, liste.length - 1 ) ].focus();
		}

		tetik.addEventListener( 'click', function () {
			degistir( panel.hidden );
		} );

		tetik.addEventListener( 'keydown', function ( e ) {
			if ( 'ArrowDown' === e.key ) {
				e.preventDefault();
				degistir( true );
			}
		} );

		arama.addEventListener( 'input', suz );

		panel.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) {
				e.preventDefault();
				degistir( false );
				tetik.focus();
				return;
			}

			if ( 'ArrowDown' === e.key ) {
				e.preventDefault();
				gez( 1 );
				return;
			}

			if ( 'ArrowUp' === e.key ) {
				e.preventDefault();
				gez( -1 );
				return;
			}

			// Arama kutusunda Enter formu göndermesin; seçimi kapatır.
			if ( 'Enter' === e.key && e.target === arama ) {
				e.preventDefault();
			}
		} );

		kaynak.addEventListener( 'change', function ( e ) {
			if ( ! e.target || 'checkbox' !== e.target.type ) {
				return;
			}

			if ( ! coklu && e.target.checked ) {
				girdiler().forEach( function ( g ) {
					if ( g !== e.target && g.checked ) {
						g.checked = false;
					}
				} );
			}

			tazele();

			if ( ! coklu ) {
				degistir( false );
				tetik.focus();
			}
		} );

		// Liste dışarıdan büyüyebilir (yeni malzeme eklenmesi gibi).
		if ( window.MutationObserver ) {
			new window.MutationObserver( function () {
				tazele();
				suz();
			} ).observe( kaynak, { childList: true, subtree: true } );
		}

		document.addEventListener( 'mousedown', function ( e ) {
			if ( ! panel.hidden && ! kok.contains( e.target ) ) {
				degistir( false );
			}
		} );

		tazele();
	}

	/* =============================================================
	   2. KATLANABİLİR BÖLÜMLER
	============================================================= */

	/**
	 * Bölümün içinde dolu/işaretli alan var mı?
	 *
	 * @param {HTMLElement} govde Bölüm gövdesi.
	 * @return {boolean} Doluluk.
	 */
	function doluMu( govde ) {
		var dolu = false;

		govde.querySelectorAll( 'input, select, textarea' ).forEach( function ( alan ) {
			if ( dolu ) {
				return;
			}

			if ( 'checkbox' === alan.type || 'radio' === alan.type ) {
				dolu = alan.checked && '1' !== alan.getAttribute( 'data-yoksay' );
			} else if ( 'hidden' !== alan.type ) {
				dolu = '' !== String( alan.value || '' ).trim();
			}
		} );

		return dolu;
	}

	/**
	 * Katlanabilir bölümleri kurar.
	 *
	 * @param {HTMLElement} sec Bölüm.
	 * @return {void}
	 */
	function bolumKur( sec ) {
		var tetik = sec.querySelector( '.qrms-pe-sec-tetik' );
		var govde = sec.querySelector( '.qrms-pe-sec-govde' );

		if ( ! tetik || ! govde ) {
			return;
		}

		// Dolu bir bölüm asla kapalı açılmaz: kullanıcı girdiği veriyi
		// kaybolmuş sanmasın.
		var acik = '0' !== sec.getAttribute( 'data-open' ) || doluMu( govde );

		/**
		 * Durumu uygular.
		 *
		 * @param {boolean} ac Açık mı?
		 * @return {void}
		 */
		function uygula( ac ) {
			govde.hidden = ! ac;
			tetik.setAttribute( 'aria-expanded', ac ? 'true' : 'false' );
			sec.classList.toggle( 'qrms-pe-sec-dolu', ! ac && doluMu( govde ) );
		}

		uygula( acik );

		tetik.addEventListener( 'click', function () {
			uygula( govde.hidden );
		} );

		govde.addEventListener( 'change', function () {
			sec.classList.toggle( 'qrms-pe-sec-dolu', govde.hidden && doluMu( govde ) );
		} );
	}

	/* =============================================================
	   3. TEMEL BİLGİLER KARTI
	============================================================= */

	/**
	 * Fiyat alanı ile WordPress'in kategori ve öne çıkarılan görsel
	 * kutularını en üstteki karta taşır.
	 *
	 * Kutular BÜTÜN olarak taşınır (içleri boşaltılmaz): çekirdeğin olay
	 * bağları öğenin kendisine kuruludur, taşıma sırasında korunur.
	 *
	 * @return {void}
	 */
	function temelBilgileriKur() {
		var kart = document.getElementById( 'qrms-pe-temel' );

		if ( ! kart ) {
			return;
		}

		var tasindi = false;

		/**
		 * Bir öğeyi yuvaya taşır.
		 *
		 * @param {string} yuvaAdi Yuva adı.
		 * @param {HTMLElement|null} oge Taşınacak öğe.
		 * @return {void}
		 */
		function tasi( yuvaAdi, oge ) {
			var yuva = kart.querySelector( '[data-qrms-pe-yuva="' + yuvaAdi + '"]' );

			if ( ! yuva || ! oge ) {
				return;
			}

			yuva.appendChild( oge );
			tasindi = true;
		}

		tasi( 'fiyat', document.querySelector( '[data-qrms-pe-slot="fiyat"]' ) );
		tasi( 'kategori', document.getElementById( 'rma_categorydiv' ) );
		tasi( 'gorsel', document.getElementById( 'postimagediv' ) );

		if ( tasindi ) {
			kart.hidden = false;
		}
	}

	/**
	 * Kategori kutusunu tek seçimli aranabilir listeye çevirir.
	 *
	 * Taksonomi, alan adları ve "Yeni kategori ekle" bağlantısı aynen kalır;
	 * yalnızca sunum değişir.
	 *
	 * @return {void}
	 */
	function kategoriyiKur() {
		var panel = document.getElementById( 'rma_category-all' );

		if ( ! panel || panel.closest( '.qrms-pe-secim' ) ) {
			return;
		}

		var sarmal = document.createElement( 'div' );
		sarmal.className = 'qrms-pe-secim';
		sarmal.setAttribute( 'data-qrms-secim', 'tek' );
		sarmal.setAttribute( 'data-etiket', t( 'kategoriSec', 'Kategori seçin…' ) );
		sarmal.setAttribute( 'data-ara', t( 'kategoriAra', 'Kategori ara…' ) );

		panel.parentNode.insertBefore( sarmal, panel );
		panel.classList.add( 'qrms-pe-secim-kaynak' );
		sarmal.appendChild( panel );

		secimKur( sarmal );
	}

	/* =============================================================
	   4. BAŞLIK VE AÇIKLAMA ETİKETLERİ
	============================================================= */

	/**
	 * Ürün adı ve açıklama alanlarını etiketler.
	 *
	 * Editör DOM'da YERİNDEN OYNATILMAZ (TinyMCE iframe'i yeniden yüklenirdi);
	 * yalnızca başına bir başlık eklenir ve kart görünümü verilir.
	 *
	 * @return {void}
	 */
	function basliklariKur() {
		var titlediv = document.getElementById( 'titlediv' );

		if ( titlediv ) {
			titlediv.setAttribute( 'data-qrms-etiket', t( 'urunAdi', 'Ürün adı' ) );
		}

		var editor = document.getElementById( 'postdivrich' );

		if ( editor && ! editor.querySelector( '.qrms-pe-editor-bas' ) ) {
			editor.classList.add( 'qrms-pe-kart' );

			var bas = document.createElement( 'div' );
			bas.className = 'qrms-pe-editor-bas';

			var ad = document.createElement( 'strong' );
			ad.textContent = t( 'aciklama', 'Açıklama' );

			var not = document.createElement( 'em' );
			not.textContent = t( 'aciklamaNot', 'Misafirlerin menüde göreceği ürün açıklaması.' );

			bas.appendChild( ad );
			bas.appendChild( not );
			editor.insertBefore( bas, editor.firstChild );
		}
	}

	/* =============================================================
	   BAŞLAT
	============================================================= */

	/**
	 * Ekranı kurar.
	 *
	 * @return {void}
	 */
	function kur() {
		if ( ! document.body.classList.contains( 'qrms-product-editor' ) ) {
			return;
		}

		basliklariKur();
		kategoriyiKur();
		temelBilgileriKur();

		document.querySelectorAll( '.qrms-pe-secim' ).forEach( secimKur );
		document.querySelectorAll( '[data-qrms-pe-collapse]' ).forEach( bolumKur );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', kur );
	} else {
		kur();
	}
} )();
