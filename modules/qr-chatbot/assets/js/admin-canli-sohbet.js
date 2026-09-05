/**
 * Canlı Sohbetler — admin devralma paneli.
 *
 * Liste periyodik olarak (10sn) yenilenir; bir satıra tıklayınca yazışma
 * modalı açılır ve o oturum da kendi döngüsüyle (4sn) tazelenir. Panel
 * kapatıldığında (sekme değişimi vb.) yenileme durur — bkz. visibilitychange.
 */
( function () {
	'use strict';

	var wrap = document.getElementById( 'qmo-canli-wrap' );
	if ( ! wrap ) {
		return;
	}

	var nonce   = wrap.dataset.nonce;
	var govde   = document.getElementById( 'qmo-canli-govde' );
	var modal   = document.getElementById( 'qmo-canli-modal' );
	var modalMasa = document.getElementById( 'qmo-canli-modal-masa' );
	var yazismaEl = document.getElementById( 'qmo-canli-yazisma' );
	var mesajEl   = document.getElementById( 'qmo-canli-mesaj' );
	var gonderBtn = document.getElementById( 'qmo-canli-gonder' );
	var kapatBtn  = document.getElementById( 'qmo-canli-kapat-buton' );
	var modalKapatBtn = document.getElementById( 'qmo-canli-modal-kapat' );

	var acikOturum = '';
	var listeTimer = null;
	var yazismaTimer = null;

	function istek( action, veri ) {
		var govdeParam = new URLSearchParams();
		govdeParam.append( 'action', action );
		govdeParam.append( 'nonce', nonce );
		Object.keys( veri || {} ).forEach( function ( k ) {
			govdeParam.append( k, veri[ k ] );
		} );
		return fetch( ( typeof ajaxurl !== 'undefined' ) ? ajaxurl : '/wp-admin/admin-ajax.php', {
			method: 'POST',
			body: govdeParam,
			credentials: 'same-origin'
		} ).then( function ( r ) {
			return r.json().catch( function () {
				return { success: false };
			} );
		} );
	}

	function satirCiz( s ) {
		var tr = document.createElement( 'tr' );
		tr.className = 'qmo-canli-satir';
		tr.dataset.oturum = s.oturum_id;
		tr.dataset.masa = s.masa_no;

		var td1 = document.createElement( 'td' );
		td1.textContent = s.masa_no || '—';
		var td2 = document.createElement( 'td' );
		td2.textContent = s.son_musteri_mesaj || '';
		var td3 = document.createElement( 'td' );
		td3.textContent = 'devralindi' === s.durum ? 'Devralındı' : 'Bekliyor';
		var td4 = document.createElement( 'td' );
		td4.textContent = s.son_aktivite || '';

		tr.appendChild( td1 );
		tr.appendChild( td2 );
		tr.appendChild( td3 );
		tr.appendChild( td4 );

		tr.addEventListener( 'click', function () {
			acikOturum = s.oturum_id;
			if ( modalMasa ) {
				modalMasa.textContent = s.masa_no || '—';
			}
			if ( modal ) {
				modal.hidden = false;
			}
			yazismaYukle();
			yazismaDongusuBaslat();
		} );

		return tr;
	}

	function listeYukle() {
		istek( 'qmo_chatbot_canli_liste', {} ).then( function ( y ) {
			if ( ! govde ) {
				return;
			}
			if ( ! y || ! y.success || ! y.data || ! Array.isArray( y.data.satirlar ) ) {
				return;
			}
			govde.textContent = '';
			if ( ! y.data.satirlar.length ) {
				var bos = document.createElement( 'tr' );
				var td = document.createElement( 'td' );
				td.colSpan = 4;
				td.textContent = 'Şu an devralma bekleyen bir sohbet yok.';
				bos.appendChild( td );
				govde.appendChild( bos );
				return;
			}
			y.data.satirlar.forEach( function ( s ) {
				govde.appendChild( satirCiz( s ) );
			} );
		} );
	}

	function olayCiz( o ) {
		if ( 'personel' === o.tur ) {
			var pEl = document.createElement( 'p' );
			pEl.className = 'qmo-canli-mesaj-personel';
			pEl.innerHTML = '<strong>Personel:</strong> ';
			pEl.appendChild( document.createTextNode( o.mesaj ) );
			return pEl;
		}

		var frag = document.createDocumentFragment();
		var q = document.createElement( 'p' );
		q.innerHTML = '<strong>Ziyaretçi:</strong> ';
		q.appendChild( document.createTextNode( o.soru ) );
		var a = document.createElement( 'p' );
		a.innerHTML = '<strong>Asistan:</strong> ';
		a.appendChild( document.createTextNode( o.cevap ) );
		frag.appendChild( q );
		frag.appendChild( a );
		return frag;
	}

	function yazismaYukle() {
		if ( ! acikOturum || ! yazismaEl ) {
			return;
		}
		istek( 'qmo_chatbot_canli_yazisma', { oturum_id: acikOturum } ).then( function ( y ) {
			if ( ! y || ! y.success || ! y.data || ! Array.isArray( y.data.olaylar ) ) {
				return;
			}
			yazismaEl.textContent = '';
			y.data.olaylar.forEach( function ( o ) {
				yazismaEl.appendChild( olayCiz( o ) );
			} );
			yazismaEl.scrollTop = yazismaEl.scrollHeight;
		} );
	}

	function yazismaDongusuBaslat() {
		if ( yazismaTimer ) {
			clearInterval( yazismaTimer );
		}
		yazismaTimer = setInterval( function () {
			if ( modal && ! modal.hidden ) {
				yazismaYukle();
			}
		}, 4000 );
	}

	function modaliKapat() {
		if ( modal ) {
			modal.hidden = true;
		}
		if ( yazismaTimer ) {
			clearInterval( yazismaTimer );
			yazismaTimer = null;
		}
		acikOturum = '';
	}

	if ( modalKapatBtn ) {
		modalKapatBtn.addEventListener( 'click', modaliKapat );
	}
	if ( modal ) {
		modal.addEventListener( 'click', function ( e ) {
			if ( e.target === modal ) {
				modaliKapat();
			}
		} );
	}

	if ( gonderBtn ) {
		gonderBtn.addEventListener( 'click', function () {
			var mesaj = mesajEl ? mesajEl.value.trim() : '';
			if ( ! mesaj || ! acikOturum || gonderBtn.disabled ) {
				return;
			}
			gonderBtn.disabled = true;
			istek( 'qmo_chatbot_canli_mesaj_gonder', { oturum_id: acikOturum, mesaj: mesaj } ).then( function ( y ) {
				gonderBtn.disabled = false;
				if ( y && y.success && mesajEl ) {
					mesajEl.value = '';
					yazismaYukle();
					listeYukle();
				}
			} ).catch( function () {
				gonderBtn.disabled = false;
			} );
		} );
	}

	if ( kapatBtn ) {
		kapatBtn.addEventListener( 'click', function () {
			if ( ! acikOturum ) {
				return;
			}
			istek( 'qmo_chatbot_canli_kapat', { oturum_id: acikOturum } ).then( function ( y ) {
				if ( y && y.success ) {
					modaliKapat();
					listeYukle();
				}
			} );
		} );
	}

	document.addEventListener( 'visibilitychange', function () {
		if ( 'hidden' === document.visibilityState ) {
			if ( listeTimer ) {
				clearInterval( listeTimer );
				listeTimer = null;
			}
			return;
		}
		listeYukle();
		if ( ! listeTimer ) {
			listeTimer = setInterval( listeYukle, 10000 );
		}
	} );

	listeYukle();
	listeTimer = setInterval( listeYukle, 10000 );
}() );
