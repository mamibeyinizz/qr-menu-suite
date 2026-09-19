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

	function yaz( bar, metin, hataMi ) {
		var el = bar.querySelector( '.qmo-cagri-durum' );
		if ( ! el ) {
			return;
		}
		el.hidden = false;
		el.textContent = metin;
		el.classList.toggle( 'is-hata', !! hataMi );
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
		var span = btn.querySelector( 'span' );

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
					? metin( 'hesapIstendiBtn', '✓ Hesap İstendi' )
					: metin( 'garsonCagrildiBtn', '✓ Garson Çağrıldı' ) );
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
