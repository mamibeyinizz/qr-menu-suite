/**
 * QR Menü — Sepet & Sipariş kategorisi.
 *
 * Beş özet kartı ve dört tablo tek bir AJAX çağrısının (qrms_analitik_sepet)
 * sonucundan üretilir. Aralık/masa ADRESTEDİR; bu dosya onları değiştirmez.
 *
 * Sipariş hataları bölümü sayı sıfırsa hiç basılmaz (PHP iskeleti hidden
 * gelir, burada yalnızca doluysa açılır).
 */
( function () {
	'use strict';

	var CFG   = window.qrmsAnalitikSepet || {};
	var T     = CFG.i18n || {};
	var ORTAK = window.qrmsAnOrtak;

	if ( ! ORTAK ) {
		return;
	}

	var el = {};

	function $( id ) {
		return document.getElementById( id );
	}

	function metin( anahtar, yedek ) {
		return T[ anahtar ] || yedek;
	}

	function yeniBasladi() {
		return metin( 'justStarted', 'Sepet ve sipariş olayları toplanmaya yeni başladı. Bu bir hata değil; menüden verilen ilk siparişler burada görünecek.' );
	}

	function kartlariBas( ozet ) {
		var kartlar = [
			{
				ikon: 'dashicons-cart',
				etiket: metin( 'cardAdd', 'Sepete eklenen' ),
				deger: ORTAK.kisa( ozet.cart_add ),
				alt: ORTAK.sayi( ozet.cart_add ) + ' ' + metin( 'events', 'olay' ) +
					' · ' + ORTAK.sayi( ozet.cart_add_urun ) + ' ' + metin( 'uniqueItems', 'tekil ürün' )
			},
			{
				ikon: 'dashicons-yes-alt',
				etiket: metin( 'cardSent', 'Gönderilen sipariş' ),
				deger: ORTAK.kisa( ozet.order_sent ),
				alt: metin( 'approxSession', 'Yaklaşık oturum' )
			},
			{
				ikon: 'dashicons-dismiss',
				etiket: metin( 'cardAbandon', 'Terk edilen sepet' ),
				deger: ORTAK.kisa( ozet.terk ),
				alt: metin( 'abandonRate', 'Terk oranı' ) + ': %' + ORTAK.sayi( ozet.terk_oran ) +
					' (' + ORTAK.sayi( ozet.oturum_add ) + ' ' + metin( 'cartSessions', 'sepet oturumu' ) + ')'
			},
			{
				ikon: 'dashicons-hidden',
				etiket: metin( 'cardBlocked', 'Engellenen sipariş' ),
				deger: ORTAK.kisa( ozet.blocked ),
				alt: metin( 'soldOutReason', 'Tükendi nedeniyle' )
			},
			{
				ikon: 'dashicons-warning',
				etiket: metin( 'cardFailed', 'Başarısız sipariş' ),
				deger: ORTAK.kisa( ozet.failed ),
				alt: metin( 'orderFailed', 'order_failed' )
			}
		];

		var html = '';

		kartlar.forEach( function ( kart ) {
			html += '<div class="qrms-an-card">' +
				'<span class="qrms-an-card-tag">' + ORTAK.esc( CFG.aralikEtiketi || '' ) + '</span>' +
				'<span class="qrms-an-card-icon dashicons ' + ORTAK.esc( kart.ikon ) + '" aria-hidden="true"></span>' +
				'<div class="qrms-an-card-label">' + ORTAK.esc( kart.etiket ) + '</div>' +
				'<div class="qrms-an-card-value">' + ORTAK.esc( kart.deger ) + '</div>' +
				'<div class="qrms-an-card-sub">' + ORTAK.esc( kart.alt ) + '</div>' +
				'</div>';
		} );

		el.cards.innerHTML = html;
	}

	function bosKutuBas( bos ) {
		if ( ! el.bos ) {
			return;
		}

		if ( ! bos ) {
			el.bos.hidden = true;
			el.bos.innerHTML = '';
			return;
		}

		el.bos.hidden = false;
		el.bos.innerHTML =
			'<div class="qrms-an-teshis qrms-an-teshis-bilgi">' +
			'<span class="qrms-an-teshis-icon dashicons dashicons-info-outline" aria-hidden="true"></span>' +
			'<div class="qrms-an-teshis-body">' +
			'<h2 class="qrms-an-teshis-title">' + ORTAK.esc( metin( 'justStartedTitle', 'Toplanmaya yeni başlandı' ) ) + '</h2>' +
			'<p class="qrms-an-teshis-text">' + ORTAK.esc( yeniBasladi() ) + '</p>' +
			'</div></div>';
	}

	function urunAdi( u ) {
		return u.ad || metin( 'unknownItem', 'Bilinmeyen ürün' );
	}

	function terkBas( satirlar, bos ) {
		if ( ! satirlar || ! satirlar.length ) {
			el.terk.innerHTML = ORTAK.bosDurum(
				'dashicons-cart',
				bos ? yeniBasladi() : metin( 'noAbandon', 'Bu aralıkta sepete eklenip gönderilmeyen ürün yok.' )
			);
			return;
		}

		var basliklar = [
			metin( 'product', 'Ürün' ),
			metin( 'category', 'Kategori' ),
			metin( 'abandonSessions', 'Terk (oturum)' ),
			metin( 'addEvents', 'Ekleme (olay)' )
		];
		var govde = '';

		satirlar.forEach( function ( u, i ) {
			var sira = i + 1;
			var sinif = sira <= 3 ? 'qrms-an-rank-' + sira : 'qrms-an-rank-n';

			govde += '<tr>' +
				ORTAK.hucre( basliklar[ 0 ],
					'<span class="qrms-an-rank ' + sinif + '">' + sira + '</span> ' +
					'<strong>' + ORTAK.esc( urunAdi( u ) ) + '</strong>'
				) +
				ORTAK.hucre( basliklar[ 1 ], '<span class="qrms-an-cat">' + ORTAK.esc( u.kategori || '—' ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 2 ], '<span class="qrms-an-val-gold">' + ORTAK.sayi( u.terk ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 3 ], '<span class="qrms-an-val-bold">' + ORTAK.sayi( u.ekleme ) + '</span>' ) +
				'</tr>';
		} );

		el.terk.innerHTML = ORTAK.tabloIskelet( basliklar, govde, '' );
	}

	function cikarBas( satirlar, bos ) {
		if ( ! satirlar || ! satirlar.length ) {
			el.cikar.innerHTML = ORTAK.bosDurum(
				'dashicons-undo',
				bos ? yeniBasladi() : metin( 'noRemove', 'Bu aralıkta sepetten çıkarma yok.' )
			);
			return;
		}

		var basliklar = [
			metin( 'product', 'Ürün' ),
			metin( 'category', 'Kategori' ),
			metin( 'removes', 'Çıkarma' ),
			metin( 'adds', 'Ekleme' ),
			metin( 'addRemoveRatio', 'Ekleme / çıkarma' )
		];
		var govde = '';

		satirlar.forEach( function ( u ) {
			var oran = ( u.oran === null || u.oran === undefined )
				? '—'
				: String( u.oran ).replace( '.', ',' );
			var sik = u.cikarma > 0 && u.ekleme > 0 && ( u.ekleme / u.cikarma ) <= 1.5;
			var oranHtml = sik
				? '<span class="qrms-an-pill qrms-an-pill-mid">' + ORTAK.esc( oran ) + '</span>'
				: '<span class="qrms-an-val-bold">' + ORTAK.esc( oran ) + '</span>';

			govde += '<tr' + ( sik ? ' class="qrms-an-zero"' : '' ) + '>' +
				ORTAK.hucre( basliklar[ 0 ], '<strong>' + ORTAK.esc( urunAdi( u ) ) + '</strong>' ) +
				ORTAK.hucre( basliklar[ 1 ], '<span class="qrms-an-cat">' + ORTAK.esc( u.kategori || '—' ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 2 ], '<span class="qrms-an-val-gold">' + ORTAK.sayi( u.cikarma ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 3 ], '<span class="qrms-an-val-bold">' + ORTAK.sayi( u.ekleme ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 4 ], oranHtml ) +
				'</tr>';
		} );

		el.cikar.innerHTML = ORTAK.tabloIskelet( basliklar, govde, '' );
	}

	function engelBas( satirlar, bos ) {
		if ( ! satirlar || ! satirlar.length ) {
			el.engel.innerHTML = ORTAK.bosDurum(
				'dashicons-hidden',
				bos ? yeniBasladi() : metin( 'noBlocked', 'Bu aralıkta tükendi nedeniyle engellenen sipariş yok.' )
			);
			return;
		}

		var basliklar = [
			metin( 'product', 'Ürün' ),
			metin( 'category', 'Kategori' ),
			metin( 'missedOrders', 'Kaçırılan sipariş' ),
			metin( 'action', 'İşlem' )
		];
		var govde = '';
		var yokUrl = CFG.urunumYokUrl || '';

		satirlar.forEach( function ( u ) {
			var bag = yokUrl
				? '<a class="qrms-an-btn qrms-an-btn-small" href="' + ORTAK.esc( yokUrl ) + '">' +
					ORTAK.esc( metin( 'openSoldOut', 'Ürünüm Yok' ) ) + '</a>'
				: '—';

			govde += '<tr>' +
				ORTAK.hucre( basliklar[ 0 ], '<strong>' + ORTAK.esc( urunAdi( u ) ) + '</strong>' ) +
				ORTAK.hucre( basliklar[ 1 ], '<span class="qrms-an-cat">' + ORTAK.esc( u.kategori || '—' ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 2 ], '<span class="qrms-an-val-gold">' + ORTAK.sayi( u.siparis ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 3 ], bag ) +
				'</tr>';
		} );

		el.engel.innerHTML = ORTAK.tabloIskelet( basliklar, govde, '' );
	}

	function hataBas( ozet, satirlar ) {
		if ( ! el.hataPanel ) {
			return;
		}

		if ( ! ozet.failed ) {
			el.hataPanel.hidden = true;
			return;
		}

		el.hataPanel.hidden = false;

		if ( el.hataUyari ) {
			el.hataUyari.innerHTML =
				'<div class="qrms-an-teshis qrms-an-teshis-uyari">' +
				'<span class="qrms-an-teshis-icon dashicons dashicons-flag" aria-hidden="true"></span>' +
				'<div class="qrms-an-teshis-body">' +
				'<h2 class="qrms-an-teshis-title">' + ORTAK.esc( metin( 'firebaseTitle', 'Firebase yapılandırmasını kontrol edin' ) ) + '</h2>' +
				'<p class="qrms-an-teshis-text">' + ORTAK.esc( metin( 'firebaseText', 'Başarısız siparişler genelde Firestore yazımının düşmesinden gelir. Service account ve şube ayarlarını gözden geçirin.' ) ) + '</p>' +
				( CFG.firebaseUrl
					? '<a class="qrms-an-btn qrms-an-teshis-action" href="' + ORTAK.esc( CFG.firebaseUrl ) + '">' +
						ORTAK.esc( metin( 'firebaseLink', 'Güvenlik Ayarı > Firebase & Şube Ayarları' ) ) + '</a>'
					: '' ) +
				'</div></div>';
		}

		if ( ! satirlar || ! satirlar.length ) {
			el.hata.innerHTML = '';
			return;
		}

		var basliklar = [
			metin( 'when', 'Zaman' ),
			metin( 'failedOrders', 'Başarısız sipariş' )
		];
		var govde = '';

		satirlar.forEach( function ( s ) {
			govde += '<tr>' +
				ORTAK.hucre( basliklar[ 0 ], '<span class="qrms-an-muted">' + ORTAK.esc( s.label ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 1 ], '<span class="qrms-an-val-gold">' + ORTAK.sayi( s.sayi ) + '</span>' ) +
				'</tr>';
		} );

		el.hata.innerHTML = ORTAK.tabloIskelet( basliklar, govde, '' );
	}

	function bas( veri ) {
		var ozet = veri.ozet || {};
		var bos  = !! veri.bos;

		bosKutuBas( bos );
		kartlariBas( ozet );
		terkBas( veri.terk_urun, bos );
		cikarBas( veri.cikarilan, bos );
		engelBas( veri.engellenen, bos );
		hataBas( ozet, veri.hatalar );
	}

	function hataGoster() {
		var msg = metin( 'loadError', 'Veri yüklenemedi. Sayfayı yenileyin.' );

		el.cards.innerHTML = '';
		el.terk.innerHTML  = ORTAK.bosDurum( 'dashicons-warning', msg );
		el.cikar.innerHTML = '';
		el.engel.innerHTML = '';
	}

	function yukle() {
		ORTAK.post(
			CFG.ajaxUrl,
			{
				action: 'qrms_analitik_sepet',
				security: CFG.nonce,
				donem: CFG.donem || '',
				masa: CFG.masa || '',
				bas: CFG.bas || '',
				bit: CFG.bit || ''
			},
			bas,
			hataGoster
		);
	}

	function hazir() {
		el.cards     = $( 'qrms-an-cards' );
		el.bos       = $( 'qrms-an-sepet-bos' );
		el.terk      = $( 'qrms-an-sepet-terk' );
		el.cikar     = $( 'qrms-an-sepet-cikar' );
		el.engel     = $( 'qrms-an-sepet-engel' );
		el.hataPanel = $( 'qrms-an-sepet-hata-panel' );
		el.hataUyari = $( 'qrms-an-sepet-hata-uyari' );
		el.hata      = $( 'qrms-an-sepet-hata' );

		if ( ! el.cards || ! el.terk ) {
			return;
		}

		ORTAK.filtreKur( document.querySelector( '.qrms-an-sepet' ) );
		yukle();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', hazir );
	} else {
		hazir();
	}
}() );
