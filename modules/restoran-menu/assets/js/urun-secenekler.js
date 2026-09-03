/**
 * Porsiyon / ekstra / rozet tablolarının satır ekle-sil davranışı.
 *
 * Satırlar klonlanmaz: her tablo kendi <template class="rma-tekrar-sablon">
 * satırını taşır, `__i__` yer tutucusu sıradaki indisle değiştirilir. Böylece
 * tablo boşken de satır eklenebilir ve alan adları PHP tarafında tek yerde
 * tanımlı kalır.
 *
 * Bağımlılık yok (jQuery dahil); ürün düzenleme ekranında ve "Ekstralar ve
 * Rozetler" sayfasında yüklenir. Sayfa özel işlevler yalnızca ilgili markup
 * varken çalışır.
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
	 * @return {HTMLElement|null} Eklenen satır.
	 */
	function satirEkle( tbl ) {
		if ( ! tbl ) {
			return null;
		}

		var govde   = tbl.querySelector( 'tbody' );
		var sablon  = tbl.querySelector( 'template.rma-tekrar-sablon' );
		var azami   = parseInt( tbl.getAttribute( 'data-azami' ), 10 ) || 30;
		var mevcut  = govde ? govde.querySelectorAll( 'tr.rma-tekrar-satir' ).length : 0;

		if ( ! govde || ! sablon || mevcut >= azami ) {
			return null;
		}

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

		var eklenen = null;
		while ( gecici.firstElementChild ) {
			eklenen = gecici.firstElementChild;
			govde.appendChild( eklenen );
		}

		return eklenen;
	}

	/**
	 * "Ekstralar ve Rozetler" sayfasında yeni bir ekstra listesi kabı ekler.
	 *
	 * @return {HTMLElement|null} Eklenen kap.
	 */
	function listeEkleUygula() {
		var kaplar = document.querySelector( '[data-rma-listeler]' );
		var sablon = document.getElementById( 'rma-liste-sablon' );

		if ( ! kaplar || ! sablon ) {
			return null;
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

		var eklenen = null;
		while ( gecici.firstElementChild ) {
			eklenen = gecici.firstElementChild;
			kaplar.appendChild( eklenen );
		}

		bosDurumGuncelle();
		return eklenen;
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

	/* =============================================================
	   EKSTRALAR VE ROZETLER SAYFASI
	============================================================= */

	var IKON_PALETI = [
		{ baslik: 'Servis', ikonlar: [ '⚡', '🔥', '⏱️', '👨‍🍳' ] },
		{ baslik: 'Diyet', ikonlar: [ '🌿', '🥦', '🌾', '💪' ] },
		{ baslik: 'Tat', ikonlar: [ '🌶️', '🍯', '🧄', '🧂' ] },
		{ baslik: 'Öne çıkarma', ikonlar: [ '⭐', '🏆', '✨', '👑' ] },
		{ baslik: 'Sıcaklık', ikonlar: [ '❄️', '♨️', '🧊', '☕' ] },
		{ baslik: 'Diğer', ikonlar: [ '🍃', '🥗', '🫒', '🍋' ] },
	];

	var RENK_PALETI = [ '#c9a84c', '#e74c3c', '#27ae60', '#3498db', '#9b59b6', '#f39c12' ];

	var acikIkonPanel = null;

	/**
	 * @param {HTMLElement} sayfa Kök eleman.
	 * @return {void}
	 */
	function secenekSayfaBaslat( sayfa ) {
		hashSekmeSec( sayfa );
		sekmeleriBagla( sayfa );
		formGonderimleriniBagla( sayfa );
		silmeOnaylariniBagla( sayfa );
		siralamayiBagla( sayfa );
		ikonSecicileriBagla( sayfa );
		renkSecicileriBagla( sayfa );
		onizlemeleriBagla( sayfa );
		sablonButonlariniBagla( sayfa );
		bosDurumGuncelle();
	}

	/**
	 * URL hash'ine göre sekmeyi seçer.
	 *
	 * @param {HTMLElement} sayfa Kök eleman.
	 * @return {void}
	 */
	function hashSekmeSec( sayfa ) {
		var hash = ( window.location.hash || '' ).replace( /^#/, '' );
		var sekme = 'ekstra';

		if ( 'rma-ozel-rozetler' === hash ) {
			sekme = 'rozet';
		} else if ( 'rma-ekstra-listeleri' === hash ) {
			sekme = 'ekstra';
		} else {
			var params = new URLSearchParams( window.location.search );
			if ( 'rozet' === params.get( 'sekme' ) ) {
				sekme = 'rozet';
			}
		}

		sekmeGoster( sayfa, sekme, false );
	}

	/**
	 * Sekme gezinmesini bağlar.
	 *
	 * @param {HTMLElement} sayfa Kök eleman.
	 * @return {void}
	 */
	function sekmeleriBagla( sayfa ) {
		var sekmeler = sayfa.querySelectorAll( '.rma-secenek-sekme' );

		sekmeler.forEach( function ( sekme, indeks ) {
			sekme.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var ad = sekme.id === 'rma-sekme-rozet' ? 'rozet' : 'ekstra';
				sekmeGoster( sayfa, ad, true );
			} );

			sekme.addEventListener( 'keydown', function ( e ) {
				var yon = null;

				if ( 'ArrowRight' === e.key ) {
					yon = 1;
				} else if ( 'ArrowLeft' === e.key ) {
					yon = -1;
				}

				if ( null === yon ) {
					return;
				}

				e.preventDefault();
				var hedef = sekmeler[ ( indeks + yon + sekmeler.length ) % sekmeler.length ];
				hedef.focus();
				var ad = hedef.id === 'rma-sekme-rozet' ? 'rozet' : 'ekstra';
				sekmeGoster( sayfa, ad, true );
			} );
		} );
	}

	/**
	 * Aktif sekmeyi gösterir.
	 *
	 * @param {HTMLElement} sayfa Kök eleman.
	 * @param {string} ad 'ekstra' | 'rozet'.
	 * @param {boolean} urlGuncelle URL'yi güncelle.
	 * @return {void}
	 */
	function sekmeGoster( sayfa, ad, urlGuncelle ) {
		var ekstraSekme = sayfa.querySelector( '#rma-sekme-ekstra' );
		var rozetSekme  = sayfa.querySelector( '#rma-sekme-rozet' );
		var ekstraPanel = sayfa.querySelector( '#rma-panel-ekstra' );
		var rozetPanel  = sayfa.querySelector( '#rma-panel-rozet' );

		if ( ! ekstraSekme || ! rozetSekme || ! ekstraPanel || ! rozetPanel ) {
			return;
		}

		var ekstraAktif = 'ekstra' === ad;

		ekstraSekme.classList.toggle( 'is-active', ekstraAktif );
		rozetSekme.classList.toggle( 'is-active', ! ekstraAktif );
		ekstraSekme.setAttribute( 'aria-selected', ekstraAktif ? 'true' : 'false' );
		rozetSekme.setAttribute( 'aria-selected', ekstraAktif ? 'false' : 'true' );
		ekstraSekme.tabIndex = ekstraAktif ? 0 : -1;
		rozetSekme.tabIndex = ekstraAktif ? -1 : 0;

		ekstraPanel.classList.toggle( 'is-active', ekstraAktif );
		rozetPanel.classList.toggle( 'is-active', ! ekstraAktif );
		ekstraPanel.hidden = ! ekstraAktif;
		rozetPanel.hidden = ekstraAktif;

		sayfa.setAttribute( 'data-aktif-sekme', ad );

		if ( urlGuncelle && window.history && window.history.replaceState ) {
			var url = new URL( window.location.href );
			url.searchParams.set( 'sekme', ad );
			url.hash = ekstraAktif ? 'rma-ekstra-listeleri' : 'rma-ozel-rozetler';
			window.history.replaceState( null, '', url.toString() );
		}
	}

	/**
	 * Form gönderilmeden önce indeksleri yeniden numaralar.
	 *
	 * @param {HTMLElement} sayfa Kök eleman.
	 * @return {void}
	 */
	function formGonderimleriniBagla( sayfa ) {
		var ekstraForm = sayfa.querySelector( '[data-rma-ekstra-form]' );
		var rozetForm  = sayfa.querySelector( '[data-rma-rozet-form]' );

		if ( ekstraForm ) {
			ekstraForm.addEventListener( 'submit', function () {
				ekstraIndeksleriniYenile( ekstraForm );
			} );
		}

		if ( rozetForm ) {
			rozetForm.addEventListener( 'submit', function () {
				rozetIndeksleriniYenile( rozetForm );
			} );
		}
	}

	/**
	 * Ekstra liste indekslerini sıraya göre yeniden yazar.
	 *
	 * @param {HTMLElement} form Form.
	 * @return {void}
	 */
	function ekstraIndeksleriniYenile( form ) {
		var kaplar = form.querySelectorAll( '[data-rma-listeler] > .rma-liste-kap' );

		kaplar.forEach( function ( kap, li ) {
			kap.querySelectorAll( 'input, select, textarea' ).forEach( function ( girdi ) {
				if ( ! girdi.name ) {
					return;
				}
				girdi.name = girdi.name.replace(
					/^rma_ekstra_listeleri\[\d+\]/,
					'rma_ekstra_listeleri[' + li + ']'
				);
			} );

			var tbl = kap.querySelector( '[data-rma-tekrar]' );
			if ( tbl ) {
				tbl.setAttribute( 'data-rma-tekrar', 'liste-' + li );
				var ekleBtn = kap.querySelector( '.rma-tekrar-ekle' );
				if ( ekleBtn ) {
					ekleBtn.setAttribute( 'data-hedef', 'liste-' + li );
				}
			}
		} );
	}

	/**
	 * Rozet satır indekslerini sıraya göre yeniden yazar.
	 *
	 * @param {HTMLElement} form Form.
	 * @return {void}
	 */
	function rozetIndeksleriniYenile( form ) {
		var satirlar = form.querySelectorAll( '[data-rma-rozet-tablosu] tbody > .rma-rozet-satir' );

		satirlar.forEach( function ( satir, i ) {
			satir.querySelectorAll( 'input, select, textarea' ).forEach( function ( girdi ) {
				if ( ! girdi.name ) {
					return;
				}
				girdi.name = girdi.name.replace(
					/^rma_ozel_rozetler\[\d+\]/,
					'rma_ozel_rozetler[' + i + ']'
				);
			} );
		} );
	}

	/**
	 * Kullanımda olan kayıtlar için silme onayı.
	 *
	 * @param {HTMLElement} sayfa Kök eleman.
	 * @return {void}
	 */
	function silmeOnaylariniBagla( sayfa ) {
		sayfa.addEventListener( 'click', function ( e ) {
			var hedef = e.target;
			if ( ! hedef || ! hedef.closest ) {
				return;
			}

			var listeSil = hedef.closest( '.rma-liste-sil' );
			if ( listeSil ) {
				var kap = listeSil.closest( '.rma-liste-kap' );
				var adet = kap ? parseInt( kap.getAttribute( 'data-kullanim' ), 10 ) || 0 : 0;
				if ( adet > 0 && ! window.confirm( 'Bu liste ' + adet + ' üründe kullanılıyor. Silerseniz o ürünlerden kaldırılır.' ) ) {
					e.preventDefault();
					e.stopImmediatePropagation();
				}
				return;
			}

			var satirSil = hedef.closest( '.rma-tekrar-sil' );
			if ( satirSil ) {
				var rozetSatir = satirSil.closest( '.rma-rozet-satir' );
				if ( rozetSatir ) {
					var rozetAdet = parseInt( rozetSatir.getAttribute( 'data-kullanim' ), 10 ) || 0;
					if ( rozetAdet > 0 && ! window.confirm( 'Bu rozet ' + rozetAdet + ' üründe kullanılıyor. Silerseniz o ürünlerden kaldırılır.' ) ) {
						e.preventDefault();
						e.stopImmediatePropagation();
					}
				}
			}
		}, true );
	}

	/**
	 * Sürükle-bırak ve klavye sıralama.
	 *
	 * @param {HTMLElement} sayfa Kök eleman.
	 * @return {void}
	 */
	function siralamayiBagla( sayfa ) {
		var listeKaplar = sayfa.querySelector( '[data-rma-listeler]' );
		if ( listeKaplar ) {
			surukleBagla( listeKaplar, '.rma-liste-kap' );
			sayfa.querySelectorAll( '[data-rma-listeler] .rma-sira-yukari, [data-rma-listeler] .rma-sira-asagi' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var kap = btn.closest( '.rma-liste-kap' );
					if ( ! kap ) {
						return;
					}
					var yukari = btn.classList.contains( 'rma-sira-yukari' );
					komsuTasi( listeKaplar, kap, yukari ? -1 : 1 );
				} );
			} );
		}

		var rozetGovde = sayfa.querySelector( '[data-rma-siralanabilir-tbody]' );
		if ( rozetGovde ) {
			surukleBagla( rozetGovde, '.rma-rozet-satir' );
			sayfa.querySelectorAll( '[data-rma-rozet-tablosu] .rma-sira-yukari, [data-rma-rozet-tablosu] .rma-sira-asagi' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var satir = btn.closest( '.rma-rozet-satir' );
					if ( ! satir ) {
						return;
					}
					var yukari = btn.classList.contains( 'rma-sira-yukari' );
					komsuTasi( rozetGovde, satir, yukari ? -1 : 1 );
				} );
			} );
		}
	}

	/**
	 * HTML5 drag & drop sıralama.
	 *
	 * @param {HTMLElement} kapsayici Liste veya tbody.
	 * @param {string} secici Taşınacak öğe seçicisi.
	 * @return {void}
	 */
	function surukleBagla( kapsayici, secici ) {
		var suruklenen = null;

		kapsayici.addEventListener( 'dragstart', function ( e ) {
			var oge = e.target.closest( secici );
			if ( ! oge ) {
				return;
			}
			suruklenen = oge;
			oge.classList.add( 'rma-surukleniyor' );
			if ( e.dataTransfer ) {
				e.dataTransfer.effectAllowed = 'move';
			}
		} );

		kapsayici.addEventListener( 'dragend', function () {
			if ( suruklenen ) {
				suruklenen.classList.remove( 'rma-surukleniyor' );
			}
			suruklenen = null;
			kapsayici.querySelectorAll( secici ).forEach( function ( oge ) {
				oge.classList.remove( 'rma-suruk-hedef' );
			} );
		} );

		kapsayici.addEventListener( 'dragover', function ( e ) {
			if ( ! suruklenen ) {
				return;
			}
			e.preventDefault();
			var hedef = e.target.closest( secici );
			if ( ! hedef || hedef === suruklenen ) {
				return;
			}
			var rect = hedef.getBoundingClientRect();
			var orta = rect.top + rect.height / 2;
			if ( e.clientY < orta ) {
				kapsayici.insertBefore( suruklenen, hedef );
			} else {
				kapsayici.insertBefore( suruklenen, hedef.nextSibling );
			}
		} );
	}

	/**
	 * Komşu öğeyle yer değiştirir.
	 *
	 * @param {HTMLElement} kapsayici Liste veya tbody.
	 * @param {HTMLElement} oge Taşınacak öğe.
	 * @param {number} yon -1 yukarı, 1 aşağı.
	 * @return {void}
	 */
	function komsuTasi( kapsayici, oge, yon ) {
		var komsu = yon < 0 ? oge.previousElementSibling : oge.nextElementSibling;
		if ( ! komsu ) {
			return;
		}
		if ( yon < 0 ) {
			kapsayici.insertBefore( oge, komsu );
		} else {
			kapsayici.insertBefore( komsu, oge );
		}
	}

	/**
	 * Emoji ikon seçicilerini bağlar.
	 *
	 * @param {HTMLElement} kok Arama kökü.
	 * @return {void}
	 */
	function ikonSecicileriBagla( kok ) {
		kok.querySelectorAll( '[data-rma-ikon-secici]' ).forEach( function ( secici ) {
			if ( secici.getAttribute( 'data-rma-baglandi' ) ) {
				return;
			}
			secici.setAttribute( 'data-rma-baglandi', '1' );

			var tetik = secici.querySelector( '.rma-ikon-tetik' );
			if ( ! tetik ) {
				return;
			}

			tetik.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				ikonPanelAc( secici, tetik );
			} );
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( acikIkonPanel && ! e.target.closest( '.rma-ikon-panel' ) && ! e.target.closest( '.rma-ikon-tetik' ) ) {
				ikonPanelKapat();
			}
		} );
	}

	/**
	 * İkon panelini açar.
	 *
	 * @param {HTMLElement} secici Kapsayıcı.
	 * @param {HTMLElement} tetik Tetikleyici düğme.
	 * @return {void}
	 */
	function ikonPanelAc( secici, tetik ) {
		ikonPanelKapat();

		var panel = document.createElement( 'div' );
		panel.className = 'rma-ikon-panel';
		panel.setAttribute( 'role', 'listbox' );
		panel.innerHTML = '<button type="button" class="rma-ikon-sec rma-ikon-bos" data-ikon="">İkonsuz</button>';

		IKON_PALETI.forEach( function ( grup ) {
			var baslik = document.createElement( 'div' );
			baslik.className = 'rma-ikon-grup-baslik';
			baslik.textContent = grup.baslik;
			panel.appendChild( baslik );

			grup.ikonlar.forEach( function ( ikon ) {
				var btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'rma-ikon-sec';
				btn.setAttribute( 'data-ikon', ikon );
				btn.textContent = ikon;
				panel.appendChild( btn );
			} );
		} );

		var ozel = document.createElement( 'div' );
		ozel.className = 'rma-ikon-ozel';
		ozel.innerHTML = '<label>Özel: <input type="text" class="rma-ikon-ozel-girdi" maxlength="4" placeholder="emoji"></label>';
		panel.appendChild( ozel );

		panel.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.rma-ikon-sec' );
			if ( btn ) {
				ikonSec( secici, btn.getAttribute( 'data-ikon' ) || '' );
				ikonPanelKapat();
				return;
			}
		} );

		var ozelGirdi = panel.querySelector( '.rma-ikon-ozel-girdi' );
		if ( ozelGirdi ) {
			ozelGirdi.addEventListener( 'change', function () {
				ikonSec( secici, ozelGirdi.value.slice( 0, 4 ) );
				ikonPanelKapat();
			} );
		}

		document.body.appendChild( panel );
		acikIkonPanel = panel;
		tetik.setAttribute( 'aria-expanded', 'true' );

		var rect = tetik.getBoundingClientRect();
		panel.style.top = ( rect.bottom + window.scrollY + 4 ) + 'px';
		panel.style.left = ( rect.left + window.scrollX ) + 'px';
	}

	/**
	 * İkon panelini kapatır.
	 *
	 * @return {void}
	 */
	function ikonPanelKapat() {
		if ( acikIkonPanel ) {
			acikIkonPanel.parentNode.removeChild( acikIkonPanel );
			acikIkonPanel = null;
		}
		document.querySelectorAll( '.rma-ikon-tetik[aria-expanded="true"]' ).forEach( function ( btn ) {
			btn.setAttribute( 'aria-expanded', 'false' );
		} );
	}

	/**
	 * İkon değerini uygular.
	 *
	 * @param {HTMLElement} secici Kapsayıcı.
	 * @param {string} ikon Seçilen ikon.
	 * @return {void}
	 */
	function ikonSec( secici, ikon ) {
		var gizli = secici.querySelector( '.rma-rozet-ikon' );
		var goster = secici.querySelector( '.rma-ikon-goster' );
		if ( gizli ) {
			gizli.value = ikon;
			gizli.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		}
		if ( goster ) {
			goster.textContent = ikon || '—';
		}
	}

	/**
	 * Renk seçicilerini bağlar (wp-color-picker + hazır palet).
	 *
	 * @param {HTMLElement} kok Arama kökü.
	 * @return {void}
	 */
	function renkSecicileriBagla( kok ) {
		kok.querySelectorAll( '[data-rma-renk-alan]' ).forEach( function ( alan ) {
			if ( alan.getAttribute( 'data-rma-baglandi' ) ) {
				return;
			}
			alan.setAttribute( 'data-rma-baglandi', '1' );

			var girdi = alan.querySelector( '.rma-rozet-renk' );
			if ( ! girdi ) {
				return;
			}

			var palet = document.createElement( 'div' );
			palet.className = 'rma-renk-palet';
			RENK_PALETI.forEach( function ( renk ) {
				var btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'rma-renk-ornek';
				btn.style.backgroundColor = renk;
				btn.setAttribute( 'aria-label', renk );
				btn.addEventListener( 'click', function () {
					girdi.value = renk;
					girdi.dispatchEvent( new Event( 'input', { bubbles: true } ) );
					wpRenkGuncelle( girdi, renk );
				} );
				palet.appendChild( btn );
			} );
			alan.appendChild( palet );

			wpRenkBaslat( girdi );
		} );
	}

	/**
	 * wp-color-picker'ı isteğe bağlı başlatır.
	 *
	 * @param {HTMLInputElement} girdi Renk alanı.
	 * @return {void}
	 */
	function wpRenkBaslat( girdi ) {
		if ( window.jQuery && window.jQuery.fn.wpColorPicker ) {
			window.jQuery( girdi ).wpColorPicker( {
				change: function ( _, ui ) {
					girdi.value = ui.color.toString();
					girdi.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				},
			} );
		}
	}

	/**
	 * wp-color-picker değerini günceller.
	 *
	 * @param {HTMLInputElement} girdi Renk alanı.
	 * @param {string} renk Hex renk.
	 * @return {void}
	 */
	function wpRenkGuncelle( girdi, renk ) {
		if ( window.jQuery && window.jQuery( girdi ).hasClass( 'wp-color-picker' ) ) {
			window.jQuery( girdi ).wpColorPicker( 'color', renk );
		}
	}

	/**
	 * Rozet canlı önizlemesini bağlar.
	 *
	 * @param {HTMLElement} kok Arama kökü.
	 * @return {void}
	 */
	function onizlemeleriBagla( kok ) {
		kok.addEventListener( 'input', function ( e ) {
			var hedef = e.target;
			if ( ! hedef || ! hedef.closest ) {
				return;
			}

			var satir = hedef.closest( '.rma-rozet-satir' );
			if ( ! satir ) {
				return;
			}

			rozetOnizlemeGuncelle( satir );
		} );

		kok.querySelectorAll( '.rma-rozet-satir' ).forEach( rozetOnizlemeGuncelle );
	}

	/**
	 * Tek rozet satırının önizlemesini günceller.
	 *
	 * @param {HTMLElement} satir Rozet satırı.
	 * @return {void}
	 */
	function rozetOnizlemeGuncelle( satir ) {
		var adGirdi = satir.querySelector( '.rma-rozet-ad' );
		var ikonGirdi = satir.querySelector( '.rma-rozet-ikon' );
		var renkGirdi = satir.querySelector( '.rma-rozet-renk' );
		var onizleme = satir.querySelector( '.rma-rozet-onizleme-kutu' );

		if ( ! onizleme ) {
			return;
		}

		var ad = adGirdi ? adGirdi.value.trim() : '';
		var ikon = ikonGirdi ? ikonGirdi.value.trim() : '';
		var renk = renkGirdi ? renkGirdi.value : '#c9a84c';

		onizleme.style.setProperty( '--rma-rozet-renk', renk );
		onizleme.textContent = ( ikon ? ikon + ' ' : '' ) + ( ad || 'Rozet' );
	}

	/**
	 * Boş durum şablon butonlarını bağlar.
	 *
	 * @param {HTMLElement} sayfa Kök eleman.
	 * @return {void}
	 */
	function sablonButonlariniBagla( sayfa ) {
		var ekstraSablon = sayfa.querySelector( '[data-rma-sablon-ekstra]' );
		if ( ekstraSablon ) {
			ekstraSablon.addEventListener( 'click', function () {
				var sablonlar = [
					{ ad: 'Soslar', urunler: [ { ad: 'Ketçap', fiyat: '5' }, { ad: 'Mayonez', fiyat: '5' }, { ad: 'Acı sos', fiyat: '8' } ] },
					{ ad: 'İçecekler', urunler: [ { ad: 'Kola', fiyat: '35' }, { ad: 'Ayran', fiyat: '25' }, { ad: 'Su', fiyat: '15' } ] },
					{ ad: 'Ekstra Malzeme', urunler: [ { ad: 'Ekstra peynir', fiyat: '20' }, { ad: 'Mantar', fiyat: '15' }, { ad: 'Zeytin', fiyat: '10' } ] },
				];

				sablonlar.forEach( function ( sablon ) {
					var kap = listeEkleUygula();
					if ( ! kap ) {
						return;
					}
					var adGirdi = kap.querySelector( 'input[name$="[ad]"]' );
					if ( adGirdi ) {
						adGirdi.value = sablon.ad;
					}
					var tbl = kap.querySelector( '[data-rma-tekrar]' );
					if ( ! tbl ) {
						return;
					}
					var govde = tbl.querySelector( 'tbody' );
					if ( govde ) {
						govde.innerHTML = '';
					}
					sablon.urunler.forEach( function ( urun ) {
						var satir = satirEkle( tbl );
						if ( ! satir ) {
							return;
						}
						var adInp = satir.querySelector( 'input[name$="[ad]"]' );
						var fiyatInp = satir.querySelector( 'input[name$="[fiyat]"]' );
						if ( adInp ) {
							adInp.value = urun.ad;
						}
						if ( fiyatInp ) {
							fiyatInp.value = urun.fiyat;
						}
					} );
				} );
				bosDurumGuncelle();
			} );
		}

		var rozetSablon = sayfa.querySelector( '[data-rma-sablon-rozet]' );
		if ( rozetSablon ) {
			rozetSablon.addEventListener( 'click', function () {
				var ornekler = [
					{ ad: 'Hızlı Servis', ikon: '⚡', renk: '#c9a84c' },
					{ ad: 'Acı', ikon: '🌶️', renk: '#e74c3c' },
					{ ad: 'Şefin Önerisi', ikon: '👨‍🍳', renk: '#9b59b6' },
				];

				var tbl = sayfa.querySelector( '[data-rma-tekrar="rozet"]' );
				ornekler.forEach( function ( ornek ) {
					var satir = satirEkle( tbl );
					if ( ! satir ) {
						return;
					}
					var adInp = satir.querySelector( '.rma-rozet-ad' );
					var ikonInp = satir.querySelector( '.rma-rozet-ikon' );
					var renkInp = satir.querySelector( '.rma-rozet-renk' );
					if ( adInp ) {
						adInp.value = ornek.ad;
					}
					if ( ikonInp ) {
						ikonSec( satir.querySelector( '[data-rma-ikon-secici]' ), ornek.ikon );
					}
					if ( renkInp ) {
						renkInp.value = ornek.renk;
						wpRenkGuncelle( renkInp, ornek.renk );
					}
					ikonSecicileriBagla( satir );
					renkSecicileriBagla( satir );
					rozetOnizlemeGuncelle( satir );
				} );
				bosDurumGuncelle();
			} );
		}
	}

	/**
	 * Boş durum kutularının görünürlüğünü günceller.
	 *
	 * @return {void}
	 */
	function bosDurumGuncelle() {
		var sayfa = document.querySelector( '[data-rma-secenek-sayfa]' );
		if ( ! sayfa ) {
			return;
		}

		var ekstraBos = sayfa.querySelector( '[data-rma-ekstra-bos]' );
		var kaplar = sayfa.querySelectorAll( '[data-rma-listeler] > .rma-liste-kap' );
		if ( ekstraBos ) {
			ekstraBos.hidden = kaplar.length > 0;
		}

		var rozetBos = sayfa.querySelector( '[data-rma-rozet-bos]' );
		var satirlar = sayfa.querySelectorAll( '[data-rma-rozet-tablosu] tbody > .rma-rozet-satir' );
		if ( rozetBos ) {
			rozetBos.hidden = satirlar.length > 0;
		}
	}

	/* =============================================================
	   OLAY DİNLEYİCİLERİ
	============================================================= */

	document.addEventListener( 'click', function ( e ) {
		var hedef = e.target;
		if ( ! hedef || ! hedef.closest ) {
			return;
		}

		var ekle = hedef.closest( '.rma-tekrar-ekle' );
		if ( ekle ) {
			e.preventDefault();
			var tbl = tablo( ekle.getAttribute( 'data-hedef' ) );
			var yeniSatir = satirEkle( tbl );
			if ( yeniSatir && yeniSatir.classList.contains( 'rma-rozet-satir' ) ) {
				ikonSecicileriBagla( yeniSatir );
				renkSecicileriBagla( yeniSatir );
				rozetOnizlemeGuncelle( yeniSatir );
				bosDurumGuncelle();
			}
			return;
		}

		var sil = hedef.closest( '.rma-tekrar-sil' );
		if ( sil ) {
			e.preventDefault();
			var satir = sil.closest( 'tr' );
			if ( satir ) {
				satir.parentNode.removeChild( satir );
				bosDurumGuncelle();
			}
			return;
		}

		var listeSil = hedef.closest( '.rma-liste-sil' );
		if ( listeSil ) {
			e.preventDefault();
			var kap = listeSil.closest( '.rma-liste-kap' );
			if ( kap ) {
				kap.parentNode.removeChild( kap );
				bosDurumGuncelle();
			}
			return;
		}

		var listeEkle = hedef.closest( '[data-rma-liste-ekle]' );
		if ( listeEkle ) {
			e.preventDefault();
			listeEkleUygula();
		}
	} );

	document.addEventListener( 'change', function ( e ) {
		if ( e.target && 'rma_servis_mod' === e.target.name ) {
			servisAlaniGuncelle();
		}
	} );

	servisAlaniGuncelle();

	var secenekSayfa = document.querySelector( '[data-rma-secenek-sayfa]' );
	if ( secenekSayfa ) {
		secenekSayfaBaslat( secenekSayfa );
	}
}() );
