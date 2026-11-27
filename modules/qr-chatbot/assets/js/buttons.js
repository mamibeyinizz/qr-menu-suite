/**
 * QR Menu Official — garson / hesap buton davranışı.
 *
 * Action adları (garson_cagir, hesap_iste) eski eklentiyle aynıdır.
 * Sunucu uçları Firestore yazdığı için bu göçte yok; uç gelince
 * aynı POST gövdesi çalışır.
 */
( function () {
	'use strict';

	// chatbot.js ve sepet.js kendi kök elemanlarında aynı deseni kullanır;
	// bu dosyanın tek bir kökü yok (sayfada birden fazla [data-qmo-cagri]
	// çubuğu olabilir), bu yüzden bayrak <html> üzerinde tutulur. Script
	// birden fazla kez enjekte edilirse (enqueue katmanındaki tekilleştirme
	// atlanırsa) document click dinleyicisi iki kez bağlanıp tek tıkta iki
	// AJAX isteği gitmesin diye.
	if ( document.documentElement.dataset.qmoButtonsInit ) {
		return;
	}
	document.documentElement.dataset.qmoButtonsInit = '1';

	var istek = window.qmoChatShared.istek;
	var metin = window.qmoChatShared.metin;

	// Sunucu başarı yanıtında 'cooldown' göndermezse (ör. eski önbelleklenmiş
	// JS ile eşleşen bir yanıt) düşülecek yedek — ajax-waiter-bill.php'deki
	// qmo_cagri_bekleme filtresinin öntanımlı değeriyle aynıdır.
	var COOLDOWN_YEDEK_SN = 60;

	// Buton başına aktif cooldown zamanlayıcısı. Modül kapsamında (global
	// değil) tutulur; bir sonraki tıklamadan önceki zamanlayıcı temizlenir.
	var cooldownZamanlayicilari = new WeakMap();

	// Sabit, güvenilir SVG sabitleri (kullanıcı verisi içermez).
	var IKON_TIK = '<svg class="qmo-cagri-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
	var IKON_UNLEM = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 7v6M12 17h.01"/></svg>';
	var TOAST_SURE_MS = 2400;

	var toastKok = null;
	var toastZamanlayici = null;

	/**
	 * Butonun hemen üstünde 2.4 sn'lik bildirim kutusu gösterir. Eskiden alt
	 * çubukta kalıcı duran düz yazı yerine kullanılır; ekran okuyucu için
	 * yazı yine .qmo-cagri-durum içinde (görsel olarak gizli) güncellenir.
	 */
	function toastGoster( bar, mesaj, hataMi ) {
		if ( ! toastKok ) {
			toastKok = document.createElement( 'div' );
			toastKok.className = 'qmo-cagri-toast';
			toastKok.setAttribute( 'aria-hidden', 'true' );
			toastKok.innerHTML = '<div class="qmo-cagri-toast__kutu"><span class="qmo-cagri-toast__ikon"></span><span class="qmo-cagri-toast__metin"></span></div>';
			document.body.appendChild( toastKok );
		}

		// Sabit alt çubuğun (veya satır içi çubuğun) tam üstüne hizala.
		var ref = bar.closest( '.hfb-footer__call-wrap' ) || bar;
		var r = ref.getBoundingClientRect();
		toastKok.style.bottom = Math.max( 0, window.innerHeight - r.top ) + 12 + 'px';

		toastKok.classList.toggle( 'is-hata', !! hataMi );
		toastKok.querySelector( '.qmo-cagri-toast__ikon' ).innerHTML = hataMi ? IKON_UNLEM : IKON_TIK;
		toastKok.querySelector( '.qmo-cagri-toast__metin' ).textContent = mesaj;

		// Animasyonu baştan başlat (art arda gösterimde de oynasın).
		toastKok.classList.remove( 'is-acik' );
		void toastKok.offsetWidth;
		toastKok.classList.add( 'is-acik' );

		clearTimeout( toastZamanlayici );
		toastZamanlayici = setTimeout( function () {
			toastKok.classList.remove( 'is-acik' );
		}, TOAST_SURE_MS );
	}

	function yaz( bar, metin, hataMi ) {
		var el = bar.querySelector( '.qmo-cagri-durum' );
		if ( el ) {
			el.hidden = false;
			el.textContent = metin;
			el.classList.toggle( 'is-hata', !! hataMi );
		}
		toastGoster( bar, metin, hataMi );
	}

	function tikTemizle( btn ) {
		var eski = btn.querySelector( '.qmo-cagri-check' );
		if ( eski ) {
			eski.remove();
		}
	}

	/**
	 * Buton durumunu TEK merkezden uygular: metin, disabled, aria-busy,
	 * aria-label ve is-success/is-disabled sınıfları birlikte değişir —
	 * aralarında tutarsız bir ara hal oluşmaz.
	 *
	 * @param {HTMLButtonElement} btn
	 * @param {'idle'|'loading'|'success'} durum
	 * @param {string} [durumMetni] loading/success için görünen metin.
	 */
	function durumUygula( btn, durum, durumMetni ) {
		// '.qmo-cagri-etiket': shortcode-buttons.php'de ikon da bir <span>
		// olduğu için (qmo-cagri-ikon) isimsiz 'span' seçici ikonu ezip
		// state metnini yanlış yere yazıyordu. HFB footer (trait-frontend.php)
		// tek span'ine de aynı class eklendi; ikisi de bu seçiciyle çalışır.
		var span = btn.querySelector( '.qmo-cagri-etiket' );

		if ( 'idle' === durum ) {
			var zamanlayici = cooldownZamanlayicilari.get( btn );
			if ( zamanlayici ) {
				clearTimeout( zamanlayici );
				cooldownZamanlayicilari.delete( btn );
			}
			btn.disabled = false;
			btn.removeAttribute( 'aria-busy' );
			btn.removeAttribute( 'aria-label' );
			btn.classList.remove( 'is-success', 'is-disabled' );
			tikTemizle( btn );
			if ( span && btn.dataset.qmoIdleLabel ) {
				span.textContent = btn.dataset.qmoIdleLabel;
			}
			return;
		}

		// İlk geçişte idle etiketi sakla (sunucudan gelen/çeviri edilmiş metin) —
		// cooldown sonunda veya hatada buna geri dönülür.
		if ( span && ! btn.dataset.qmoIdleLabel ) {
			btn.dataset.qmoIdleLabel = span.textContent;
		}

		if ( 'loading' === durum ) {
			btn.disabled = true;
			btn.setAttribute( 'aria-busy', 'true' );
			btn.classList.add( 'is-disabled' );
			btn.classList.remove( 'is-success' );
		} else if ( 'success' === durum ) {
			btn.disabled = true;
			btn.removeAttribute( 'aria-busy' );
			btn.classList.remove( 'is-disabled' );
			btn.classList.add( 'is-success' );
			// İkonun yerine çizilerek beliren tik; etiketteki "✓" karakteri
			// çift tik olmasın diye kırpılır.
			durumMetni = String( durumMetni ).replace( /^\s*✓\s*/, '' );
			if ( ! btn.querySelector( '.qmo-cagri-check' ) ) {
				btn.insertAdjacentHTML( 'afterbegin', IKON_TIK );
			}
		}

		if ( span ) {
			span.textContent = durumMetni;
		}
		btn.setAttribute( 'aria-label', durumMetni );
	}

	/**
	 * Başarı sonrası butonu kilitli tutar (tekrar gönderim engeli) ve süre
	 * dolunca idle'a döner. Süre, mümkünse sunucunun bildirdiği gerçek hız
	 * sınırı penceresidir (bkz. ajax-waiter-bill.php 'cooldown').
	 *
	 * @param {HTMLButtonElement} btn
	 * @param {*} saniye
	 */
	function cooldownBaslat( btn, saniye ) {
		var sn = parseInt( saniye, 10 );
		if ( ! sn || sn < 1 ) {
			sn = COOLDOWN_YEDEK_SN;
		}

		var mevcut = cooldownZamanlayicilari.get( btn );
		if ( mevcut ) {
			clearTimeout( mevcut );
		}

		cooldownZamanlayicilari.set( btn, setTimeout( function () {
			cooldownZamanlayicilari.delete( btn );
			durumUygula( btn, 'idle' );
		}, sn * 1000 ) );
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-qmo-cagri]' );
		// disabled kontrolü: yerel <button disabled> zaten click üretmez ama
		// hızlı ardışık tıklamalarda (double-click) durum güncellemesi ile
		// olay sırası arasında yarış olmasın diye açıkça de kontrol edilir.
		if ( ! btn || btn.disabled ) {
			return;
		}

		var bar = btn.closest( '.qmo-cagri-bar' );
		var tip = btn.getAttribute( 'data-qmo-cagri' );
		var action = ( 'hesap' === tip ) ? 'hesap_iste' : 'garson_cagir';

		durumUygula( btn, 'loading', 'hesap' === tip
			? metin( 'hesapIsteniyor', 'İsteniyor...' )
			: metin( 'garsonCagriliyor', 'Çağrılıyor...' ) );

		istek( { action: action } ).then( function ( yanit ) {
			if ( yanit && yanit.success ) {
				durumUygula( btn, 'success', 'hesap' === tip
					? metin( 'hesapIstendiBtn', '✓ İstendi' )
					: metin( 'garsonCagrildiBtn', '✓ Çağrıldı' ) );
				yaz( bar, 'hesap' === tip
					? metin( 'hesapIletildi', 'Hesap talebiniz iletildi.' )
					: metin( 'garsonIletildi', 'Garson çağrınız iletildi.' ), false );
				cooldownBaslat( btn, yanit.data && yanit.data.cooldown );
				return;
			}
			var mesaj = metin( 'istekIletilemedi', 'İstek iletilemedi, lütfen tekrar deneyin.' );
			if ( yanit && yanit.data ) {
				if ( typeof yanit.data === 'string' ) {
					mesaj = yanit.data;
				} else if ( yanit.data.mesaj ) {
					mesaj = yanit.data.mesaj;
				} else if ( yanit.data.msg ) {
					mesaj = yanit.data.msg;
				}
			}
			durumUygula( btn, 'idle' );
			yaz( bar, mesaj, true );
		} ).catch( function () {
			durumUygula( btn, 'idle' );
			yaz( bar, metin( 'baglantiHatasi', 'Bağlantı hatası oluştu.' ), true );
		} );
	} );
}() );
