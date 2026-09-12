/**
 * QR Menu Suite — giriş ekranı davranışları.
 *
 * Form WordPress'in kendi formudur; burada yalnızca üç küçük iyileştirme
 * yapılır. Hiçbiri girişin ön koşulu değildir: betik yüklenmezse ekran
 * eksiksiz çalışmaya devam eder.
 *
 * 1. Caps Lock uyarısı — şifre alanında büyük harf kilidi açıkken uyarır.
 * 2. Gönderim durumu — düğme iki kez tıklanamaz, "bekleyin" metnine geçer.
 * 3. Odak — boş kullanıcı adı alanına masaüstünde odaklanır (mobilde
 *    odaklanmak klavyeyi açıp sayfayı zıplattığı için yapılmaz).
 */
( function () {
	'use strict';

	var metin = window.QRMS_LOGIN || {};

	/**
	 * Olay gerçek bir FİZİKSEL tuşa mı ait?
	 *
	 * Sanal klavyeler Caps Lock durumunu güvenilir bildirmez: Android IME'leri
	 * tuş olaylarını `keyCode 229` / `key "Unidentified"` ile gönderir, iOS ve
	 * bazı Android klavyeleri otomatik büyük harf (auto-capitalize) durumunu
	 * Shift/CapsLock gibi raporlar. Bu olaylara bakmak, kilit KAPALIYKEN uyarı
	 * gösterilmesinin başlıca sebebidir; bu yüzden hepsi elenir.
	 *
	 * Ayırt edici alan `code`: fiziksel tuşta her zaman doludur ("KeyA",
	 * "CapsLock"), sanal klavyelerde boş string gelir.
	 *
	 * @param {KeyboardEvent} olay Olay.
	 * @return {boolean} Fiziksel tuş ise true.
	 */
	function fizikselTus( olay ) {
		if ( ! olay || false === olay.isTrusted ) {
			return false;
		}

		if ( olay.isComposing || 229 === olay.keyCode ) {
			return false;
		}

		if ( ! olay.code || ! olay.key || 'Unidentified' === olay.key ) {
			return false;
		}

		return 'function' === typeof olay.getModifierState;
	}

	/**
	 * Olaydan Caps Lock durumunu çıkarır.
	 *
	 * Önce kesin kanıta bakılır: basılan tuş bir HARF ise, üretilen karakterin
	 * büyük/küçük olması ile Shift durumunun çelişmesi kilidin açık olduğunu
	 * tek başına kanıtlar (tarayıcıdan bağımsız). Kanıt yoksa
	 * `getModifierState` kullanılır.
	 *
	 * @param {KeyboardEvent} olay Olay.
	 * @return {boolean|null} Durum; belirlenemiyorsa null.
	 */
	function capsDurumu( olay ) {
		var tus = olay.key;

		if ( tus && 1 === tus.length ) {
			var kucuk = tus.toLowerCase();
			var buyuk = tus.toUpperCase();

			// Harf olmayan karakterler (rakam, noktalama) hiçbir şey söylemez.
			if ( kucuk !== buyuk ) {
				return ( tus === buyuk ) !== !! olay.shiftKey;
			}
		}

		try {
			return ! ! olay.getModifierState( 'CapsLock' );
		} catch ( hata ) {
			return null;
		}
	}

	/**
	 * Şifre alanına Caps Lock uyarısı bağlar.
	 *
	 * @param {HTMLInputElement|null} alan Şifre alanı.
	 * @return {void}
	 */
	function capsUyarisi( alan ) {
		if ( ! alan || 'function' !== typeof alan.closest ) {
			return;
		}

		var kap = alan.closest( '.wp-pwd' ) || alan.parentNode;

		if ( ! kap || ! kap.parentNode ) {
			return;
		}

		// Betik iki kez çalışsa (başka bir eklenti, canlı önizleme) bile tek
		// uyarı elemanı kalsın.
		var uyari = document.getElementById( 'qrms-caps-uyari' );

		if ( ! uyari ) {
			uyari = document.createElement( 'span' );
			uyari.id        = 'qrms-caps-uyari';
			uyari.className = 'qrms-caps';
			uyari.textContent = metin.capsLock || 'Caps Lock açık';
			uyari.setAttribute( 'role', 'status' );
			uyari.setAttribute( 'aria-live', 'polite' );

			// Uyarı, şifre kutusunun (ve "şifreyi göster" düğmesinin) DIŞINA,
			// hemen altına eklenir: düğmenin konumu ve alanın genişliği
			// etkilenmez, gönderim hiçbir şekilde engellenmez.
			kap.parentNode.insertBefore( uyari, kap.nextSibling );
		}

		var gorunur = null;

		/**
		 * Uyarıyı gösterir/gizler.
		 *
		 * Görünürlük SINIFLA yönetilir: `[hidden]` özniteliğinin `display:none`
		 * kuralı, stylesheet'teki `.qrms-login .qrms-caps` seçicisi tarafından
		 * eziliyor ve uyarı kilit kapalıyken de ekranda kalıyordu. Öznitelik
		 * yine de erişilebilirlik için birlikte güncellenir.
		 *
		 * @param {boolean} acik Görünsün mü?
		 * @return {void}
		 */
		function ciz( acik ) {
			acik = ! ! acik;

			if ( acik === gorunur ) {
				return;
			}

			gorunur = acik;
			uyari.hidden = ! acik;

			if ( acik ) {
				uyari.classList.add( 'qrms-caps-acik' );
			} else {
				uyari.classList.remove( 'qrms-caps-acik' );
			}
		}

		ciz( false );

		/**
		 * Tuş olayını değerlendirir.
		 *
		 * @param {KeyboardEvent} olay Olay.
		 * @return {void}
		 */
		function tus( olay ) {
			if ( ! fizikselTus( olay ) ) {
				// Sanal klavye: durum bilinemez, YANLIŞ uyarı gösterme.
				ciz( false );
				return;
			}

			// Caps Lock tuşunun KENDİSİ: keydown anında bildirilen durum
			// tarayıcıya göre kilitten önceki değer olabiliyor; karar keyup'a
			// bırakılır.
			if ( 'CapsLock' === olay.key && 'keydown' === olay.type ) {
				return;
			}

			var durum = capsDurumu( olay );

			if ( null === durum ) {
				return;
			}

			ciz( durum );
		}

		alan.addEventListener( 'keydown', tus );
		alan.addEventListener( 'keyup', tus );

		// Odak yokken kilidin durumu değişmiş olabilir; ölçemediğimiz her
		// durumda uyarı kapalıdır.
		alan.addEventListener( 'blur', function () {
			ciz( false );
		} );

		alan.addEventListener( 'focus', function () {
			ciz( false );
		} );

		window.addEventListener( 'blur', function () {
			ciz( false );
		} );
	}

	function gonderimDurumu( form ) {
		if ( ! form ) {
			return;
		}

		form.addEventListener( 'submit', function () {
			var dugme = form.querySelector( '#wp-submit' );

			if ( ! dugme || dugme.disabled ) {
				return;
			}

			// Değeri değiştirmeden önce sakla: gönderim sunucu tarafında
			// reddedilirse (boş alan) tarayıcı geri geldiğinde eski metin
			// geri gelsin.
			dugme.dataset.eski = dugme.value;
			dugme.value = metin.bekleyin || dugme.value;
			dugme.disabled = true;

			window.setTimeout( function () {
				dugme.disabled = false;
				dugme.value = dugme.dataset.eski;
			}, 8000 );
		} );
	}

	function odakla() {
		if ( window.matchMedia && window.matchMedia( '(pointer: coarse)' ).matches ) {
			return;
		}

		var kullanici = document.getElementById( 'user_login' );

		if ( kullanici && '' === kullanici.value ) {
			kullanici.focus();
		}
	}

	function baslat() {
		// Bir iyileştirmedeki hata diğerlerini ve GİRİŞİ durdurmasın.
		try {
			capsUyarisi( document.getElementById( 'user_pass' ) );
		} catch ( hata ) {}

		try {
			gonderimDurumu( document.getElementById( 'loginform' ) );
		} catch ( hata ) {}

		try {
			odakla();
		} catch ( hata ) {}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', baslat );
	} else {
		baslat();
	}
}() );
