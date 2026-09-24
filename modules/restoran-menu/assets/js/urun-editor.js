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
 *  5. WP editor-expand sabit toolbar — premium shell header ofseti
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

		// Panel, tetiklendiği kartın/kutunun `overflow:hidden` sınırının
		// DIŞINA taşınır — aksi halde kategori/malzeme gibi kutuların içine
		// gömülü olduğu kart/metabox açılan listeyi keser (mobilde asıl
		// şikayet budur). Hedef <form>'un KENDİSİdir, document.body DEĞİL:
		// aksi halde içindeki kutucuklar formun dışına düşer ve kayıt
		// sırasında gönderilmez. Konumu her açılışta viewport'a göre
		// `konumlandir()` hesaplar.
		var gonderimKapsayici = kok.closest( 'form' ) || document.body;

		kok.insertBefore( chipler, kaynak );
		kok.insertBefore( tetik, kaynak );
		panel.appendChild( arama );
		panel.appendChild( kaynak );
		panel.appendChild( bos );
		gonderimKapsayici.appendChild( panel );
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
		 * Panelin viewport içindeki konumunu ve azami yüksekliğini hesaplar.
		 *
		 * `position:fixed` kullanır; bu yüzden hiçbir üst kartın `overflow`
		 * kuralından etkilenmez. Altta yeterli yer yoksa panel yukarı açılır.
		 * Yükseklik bütçesi `visualViewport`'a göre hesaplanır — mobilde
		 * klavye açıkken de panel, klavyenin kapladığı alanın dışına taşmaz.
		 *
		 * @return {void}
		 */
		function konumlandir() {
			if ( panel.hidden ) {
				return;
			}

			var rect = tetik.getBoundingClientRect();
			var vv = window.visualViewport;
			var ustSinir = vv ? vv.offsetTop : 0;
			var solSinir = vv ? vv.offsetLeft : 0;
			var genislikSinir = vv ? vv.width : window.innerWidth;
			var yukseklikSinir = vv ? vv.height : window.innerHeight;
			var altSinir = ustSinir + yukseklikSinir;

			// Tetikleyici görünür alanın tamamen dışına kaydıysa (sayfa
			// kaydırıldı) panel havada asılı kalmasın, kapansın.
			if ( rect.bottom < ustSinir || rect.top > altSinir ) {
				degistir( false );
				return;
			}

			var marj = 8;
			var asgariYukseklik = 120;
			var azamiYukseklik = 320;
			var altBosluk = altSinir - rect.bottom - marj;
			var ustBosluk = rect.top - ustSinir - marj;
			var yukariAc = altBosluk < asgariYukseklik && ustBosluk > altBosluk;
			var butce = Math.max( asgariYukseklik, Math.min( azamiYukseklik, yukariAc ? ustBosluk : altBosluk ) );

			var genislik = Math.min( rect.width, genislikSinir - marj * 2 );
			var sol = Math.max( solSinir + marj, Math.min( rect.left, solSinir + genislikSinir - genislik - marj ) );

			panel.style.maxHeight = butce + 'px';
			panel.style.width = genislik + 'px';
			panel.style.left = sol + 'px';

			if ( yukariAc ) {
				panel.style.top = 'auto';
				panel.style.bottom = ( window.innerHeight - rect.top + 4 ) + 'px';
			} else {
				panel.style.bottom = 'auto';
				panel.style.top = ( rect.bottom + 4 ) + 'px';
			}
		}

		var konumKareTalebi = null;

		/**
		 * `konumlandir`'ı bir sonraki çizim karesine erteler; kaydırma ve
		 * yeniden boyutlandırma sırasında gereksiz tekrar hesaplamayı önler.
		 *
		 * @return {void}
		 */
		function konumGuncelle() {
			if ( konumKareTalebi || panel.hidden ) {
				return;
			}

			konumKareTalebi = window.requestAnimationFrame( function () {
				konumKareTalebi = null;
				konumlandir();
			} );
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
				konumlandir();
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
				konumGuncelle();
			} ).observe( kaynak, { childList: true, subtree: true } );
		}

		document.addEventListener( 'mousedown', function ( e ) {
			if ( ! panel.hidden && ! kok.contains( e.target ) && ! panel.contains( e.target ) ) {
				degistir( false );
			}
		} );

		// Panel artık kok'un dışında (bkz. yukarıdaki taşıma); konumu sayfa
		// kaydırıldığında, pencere/klavye boyutu değiştiğinde tazelenmeli.
		window.addEventListener( 'resize', konumGuncelle );
		window.addEventListener( 'scroll', konumGuncelle, true );

		if ( window.visualViewport ) {
			window.visualViewport.addEventListener( 'resize', konumGuncelle );
			window.visualViewport.addEventListener( 'scroll', konumGuncelle );
		}

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
	   5. WP EDİTÖR SABİT TOOLBAR — premium shell header ofseti
	============================================================= */

	/**
	 * Ölçülen premium shell üst çubuğu yüksekliği (px).
	 *
	 * @return {number} Piksel.
	 */
	function premiumShellHeaderYukseklik() {
		var header = document.querySelector( '.qrms-shell__header' );

		if ( ! header ) {
			return 0;
		}

		return Math.ceil( header.getBoundingClientRect().height );
	}

	/**
	 * --qrms-pe-shell-header-offset değişkenini günceller.
	 *
	 * @return {number} Piksel ofset.
	 */
	function premiumShellHeaderOfsetiniGuncelle() {
		var px = premiumShellHeaderYukseklik();

		document.body.style.setProperty( '--qrms-pe-shell-header-offset', px + 'px' );

		return px;
	}

	/**
	 * Öğe position:fixed mi?
	 *
	 * @param {HTMLElement|null} el Öğe.
	 * @return {boolean} Sabit mi.
	 */
	function editorAracSabitMi( el ) {
		if ( ! el ) {
			return false;
		}

		return 'fixed' === el.style.position || 'fixed' === window.getComputedStyle( el ).position;
	}

	/**
	 * Sabit katman konumunu yalnızca değişiklik gerektiğinde yazır (top her zaman "Npx").
	 *
	 * @param {HTMLElement|null} el        Öğe.
	 * @param {string}         topPxStr   Örn. "82px".
	 * @param {string|null}    widthPxStr Genişlik veya null.
	 * @return {void}
	 */
	function editorAracKonumAyarla( el, topPxStr, widthPxStr ) {
		if ( ! el ) {
			return;
		}

		if ( 'fixed' !== el.style.position ) {
			el.style.position = 'fixed';
		}

		if ( el.style.top !== topPxStr ) {
			el.style.top = topPxStr;
		}

		if ( widthPxStr && el.style.width !== widthPxStr ) {
			el.style.width = widthPxStr;
		}
	}

	/**
	 * editor-expand sabitleme aralığını shell header ile hesaplar (tüm genişlikler).
	 *
	 * @param {number} ofset Shell header yüksekliği (px).
	 * @return {Object|null} Durum veya geçersiz.
	 */
	function shellEditorPinDurumu( ofset ) {
		var jq = window.jQuery;

		if ( ! jq || ! ofset ) {
			return null;
		}

		var $wrap = jq( '#postdivrich' );

		if ( ! $wrap.length || ! $wrap.hasClass( 'wp-editor-expand' ) ) {
			return null;
		}

		var $contentWrap = jq( '#wp-content-wrap' );
		var $tools = jq( '#wp-content-editor-tools' );
		var visual = $contentWrap.hasClass( 'tmce-active' );
		var mceEditor = window.tinymce && window.tinymce.get( 'content' );

		if ( mceEditor ) {
			visual = visual && ! mceEditor.isHidden();
		}

		var $top = visual ? $contentWrap.find( '.mce-toolbar-grp' ) : jq( '#ed_toolbar' );

		if ( ! $top.length ) {
			return null;
		}

		var $editor = visual ? $contentWrap.find( '.mce-edit-area' ) : jq( '#content' );
		var windowPos = jq( window ).scrollTop();
		var toolsHeight = $tools.outerHeight() || 0;
		var topPos = $top.parent().offset().top;
		var editorHeight = $editor.outerHeight() || 0;
		var topHeight = visual ? ( $contentWrap.find( '.mce-toolbar-grp' ).outerHeight() || 0 ) : ( jq( '#ed_toolbar' ).outerHeight() || 0 );
		var autoresizeMinHeight = 300;
		var buffer = autoresizeMinHeight;
		var canPin = visual ? autoresizeMinHeight + topHeight : autoresizeMinHeight + 20;

		canPin = editorHeight > ( canPin + 5 );

		if ( ! canPin ) {
			return {
				canPin: false,
				shouldPin: false,
				visual: visual,
				toolsHeight: toolsHeight,
				contentWrapWidth: $contentWrap.width(),
			};
		}

		var pinStart = topPos - toolsHeight - ofset;
		var pinEnd = topPos - toolsHeight - ofset + editorHeight - buffer;

		return {
			canPin: true,
			shouldPin: windowPos >= pinStart && windowPos <= pinEnd,
			pinStart: pinStart,
			pinEnd: pinEnd,
			visual: visual,
			toolsHeight: toolsHeight,
			contentWrapWidth: $contentWrap.width(),
		};
	}

	/**
	 * QRMS header-aware sabitleme gerekli mi (geçiş bandı dahil)?
	 *
	 * @param {Object}            pin   shellEditorPinDurumu() çıktısı.
	 * @param {HTMLElement}       tools #wp-content-editor-tools.
	 * @param {number}            ofset Shell header (px).
	 * @return {boolean} Gerekli mi.
	 */
	function qrmsEditorSabitlemeli( pin, tools, ofset ) {
		if ( ! pin || ! tools || ! ofset || ! pin.canPin ) {
			return false;
		}

		if ( pin.shouldPin ) {
			return true;
		}

		var windowPos = window.pageYOffset || document.documentElement.scrollTop || 0;

		if ( windowPos < pin.pinStart || windowPos > pin.pinEnd ) {
			return false;
		}

		return tools.getBoundingClientRect().top < ofset - 0.5;
	}

	/**
	 * Shell header altında sabit toolbar zincirini yalnızca gerektiğinde uygular.
	 *
	 * @param {Object}      pin   shellEditorPinDurumu() çıktısı.
	 * @param {number}      ofset Shell header yüksekliği (px).
	 * @param {HTMLElement} tools #wp-content-editor-tools.
	 * @return {void}
	 */
	function shellEditorErkenSabitle( pin, ofset, tools ) {
		var wrap = document.getElementById( 'wp-content-wrap' );

		if ( ! pin || ! tools || ! wrap ) {
			return;
		}

		var contentWrapWidth = pin.contentWrapWidth;
		var toolsHeight = pin.toolsHeight;
		var visual = pin.visual;
		var menuBar = wrap.querySelector( '.mce-menubar' );
		var menuBarHeight = menuBar ? menuBar.offsetHeight : 0;
		var borderWidth = 1;
		var topBar = visual ? wrap.querySelector( '.mce-toolbar-grp' ) : document.getElementById( 'ed_toolbar' );
		var genislikTam = contentWrapWidth + 'px';
		var genislikIc = ( contentWrapWidth - ( borderWidth * 2 ) ) + 'px';
		var topTools = ofset + 'px';
		var topMenu = ( ofset + toolsHeight ) + 'px';
		var topFormat = ( ofset + toolsHeight + menuBarHeight ) + 'px';
		var formatGenislik = genislikIc;

		if ( menuBarHeight < 3 ) {
			menuBarHeight = 0;
			topFormat = ( ofset + toolsHeight ) + 'px';
		}

		if ( topBar && ! visual ) {
			formatGenislik = ( contentWrapWidth - ( borderWidth * 2 ) - ( topBar.offsetWidth - topBar.clientWidth ) ) + 'px';
		}

		editorAracKonumAyarla( tools, topTools, genislikTam );

		if ( visual && menuBar ) {
			editorAracKonumAyarla( menuBar, topMenu, genislikIc );
		}

		if ( topBar ) {
			editorAracKonumAyarla( topBar, topFormat, formatGenislik );
		}
	}

	/**
	 * WP editor-expand sabit #wp-content-editor-tools çubuğunu premium header
	 * altına hizalar (inline top:0 WordPress tarafından sürekli yazılır).
	 *
	 * @return {void}
	 */
	function wpEditorSabitToolbarOfsetiniKur() {
		var tools = document.getElementById( 'wp-content-editor-tools' );
		var header = document.querySelector( '.qrms-shell__header' );

		if ( ! tools || ! header ) {
			return;
		}

		var rafId = null;

		/**
		 * Öğe position:fixed ise top değerini ayarlar.
		 *
		 * @param {HTMLElement|null} el Öğe.
		 * @param {number} px Üst ofset (px).
		 * @return {void}
		 */
		function sabitTopAyarla( el, px ) {
			if ( ! el || ! editorAracSabitMi( el ) ) {
				return;
			}

			var topPx = px + 'px';

			if ( el.style.top !== topPx ) {
				el.style.top = topPx;
			}
		}

		/**
		 * Sabit toolbar top değerini senkronize eder (WP editor-expand ile aynı zincir).
		 *
		 * @return {void}
		 */
		function senkronize() {
			var ofset = premiumShellHeaderOfsetiniGuncelle();
			var pin = shellEditorPinDurumu( ofset );

			if ( qrmsEditorSabitlemeli( pin, tools, ofset ) ) {
				shellEditorErkenSabitle( pin, ofset, tools );
				return;
			}

			if ( ! editorAracSabitMi( tools ) ) {
				return;
			}

			var wrap = document.getElementById( 'wp-content-wrap' );
			var toolsHeight = tools.offsetHeight || 0;
			var menuBar = wrap ? wrap.querySelector( '.mce-menubar' ) : null;
			var visualTop = wrap ? wrap.querySelector( '.mce-toolbar-grp' ) : null;
			var textTop = document.getElementById( 'ed_toolbar' );
			var menuBarHeight = menuBar && menuBar.offsetHeight ? menuBar.offsetHeight : 0;

			/* WP editor-expand: gizli menubar yüksekliği sayılmaz. */
			if ( menuBarHeight < 3 ) {
				menuBarHeight = 0;
			}

			sabitTopAyarla( tools, ofset );
			sabitTopAyarla( menuBar, ofset + toolsHeight );
			sabitTopAyarla( visualTop, ofset + toolsHeight + menuBarHeight );
			sabitTopAyarla( textTop, ofset + toolsHeight + menuBarHeight );
		}

		/**
		 * Scroll/resize ile gelen tekrarları tek karede toplar.
		 *
		 * @return {void}
		 */
		function planliSenkronize() {
			if ( rafId ) {
				return;
			}

			rafId = window.requestAnimationFrame( function () {
				rafId = null;
				senkronize();
			} );
		}

		premiumShellHeaderOfsetiniGuncelle();

		if ( window.jQuery ) {
			var jq = window.jQuery;

			jq( document ).on( 'editor-expand-on.qrms-pe', function () {
				jq( window ).on( 'scroll.qrms-pe', planliSenkronize );
				planliSenkronize();
			} );

			jq( document ).on( 'editor-expand-off.qrms-pe', function () {
				jq( window ).off( 'scroll.qrms-pe' );
			} );

			jq( document ).on( 'editor-classchange.qrms-pe', planliSenkronize );

			if ( jq( '#postdivrich' ).hasClass( 'wp-editor-expand' ) ) {
				jq( window ).on( 'scroll.qrms-pe', planliSenkronize );
			}
		}

		if ( 'undefined' !== typeof ResizeObserver ) {
			new ResizeObserver( planliSenkronize ).observe( header );
		}

		window.addEventListener( 'resize', planliSenkronize );
		window.addEventListener( 'load', planliSenkronize );
		document.addEventListener( 'tinymce-editor-init', planliSenkronize );
		planliSenkronize();
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
		zorunluAlanlariKur();
		wpEditorSabitToolbarOfsetiniKur();

		document.querySelectorAll( '.qrms-pe-secim' ).forEach( secimKur );
		document.querySelectorAll( '[data-qrms-pe-collapse]' ).forEach( bolumKur );
	}

	/**
	 * Ürün adını tarayıcı seviyesinde zorunlu kılar: boşken "Kaydet"/"Yayınla"
	 * tıklanırsa form hiç gönderilmez, tarayıcı kendi native uyarısını
	 * gösterip alana odaklanır. Sunucu tarafı kontrol save_menu_item_meta()
	 * içindedir; bu yalnızca istemci tarafı ilk savunma hattıdır.
	 *
	 * @return {void}
	 */
	function zorunluAlanlariKur() {
		var baslik = document.getElementById( 'title' );
		if ( baslik ) {
			baslik.required = true;
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', kur );
	} else {
		kur();
	}
} )();
