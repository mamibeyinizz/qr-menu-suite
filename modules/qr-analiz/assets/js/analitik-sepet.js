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

	function kartHtml( kart ) {
		return '<div class="qrms-an-card">' +
			'<span class="qrms-an-card-tag">' + ORTAK.esc( CFG.aralikEtiketi || '' ) + '</span>' +
			'<span class="qrms-an-card-icon dashicons ' + ORTAK.esc( kart.ikon ) + '" aria-hidden="true"></span>' +
			'<div class="qrms-an-card-label">' + ORTAK.esc( kart.etiket ) + '</div>' +
			'<div class="qrms-an-card-value">' + ORTAK.esc( kart.deger ) + '</div>' +
			'<div class="qrms-an-card-sub">' + ORTAK.esc( kart.alt ) + '</div>' +
			'</div>';
	}

	function grupHtml( baslik, kartlar ) {
		var html = '<div class="qrms-an-card-group">' +
			'<div class="qrms-an-card-group-title">' + ORTAK.esc( baslik ) + '</div>';

		kartlar.forEach( function ( kart ) {
			html += kartHtml( kart );
		} );

		return html + '</div>';
	}

	function kartlariBas( ozet ) {
		var hacim = [
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
			}
		];
		var siparis = [
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
		var para = [
			{
				ikon: 'dashicons-money-alt',
				etiket: metin( 'cardRevenue', 'Ciro' ),
				deger: ORTAK.para( ozet.ciro ),
				alt: metin( 'revenueNote', 'Gönderilen siparişler, taban fiyat üzerinden' )
			},
			{
				ikon: 'dashicons-chart-line',
				etiket: metin( 'cardAov', 'Ortalama sepet tutarı' ),
				deger: ORTAK.para( ozet.ort_sepet_tutari ),
				alt: metin( 'aovNote', 'Ciro / gönderilen sipariş oturumu' )
			},
			{
				ikon: 'dashicons-cart',
				etiket: metin( 'cardPending', 'Sepette bekleyen tutar' ),
				deger: ORTAK.para( ozet.sepet_potansiyeli ),
				alt: metin( 'pendingNote', 'Sepete konan (henüz sipariş olmamış dahil)' )
			},
			{
				ikon: 'dashicons-hidden',
				etiket: metin( 'cardMissedRevenue', 'Kaçan ciro' ),
				deger: ORTAK.para( ozet.kacan_ciro ),
				alt: metin( 'missedRevenueNote', 'Tükendi nedeniyle engellenen siparişler' )
			}
		];

		el.cards.innerHTML =
			grupHtml( metin( 'groupVolume', 'Hacim' ), hacim ) +
			grupHtml( metin( 'groupOrders', 'Sipariş' ), siparis ) +
			grupHtml( metin( 'groupMoney', 'Para' ), para );
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
			'<div class="qrms-an-teshis qrms-an-teshis-bilgi" id="qrms-an-sepet-teshis">' +
			'<span class="qrms-an-teshis-icon dashicons dashicons-info-outline" aria-hidden="true"></span>' +
			'<div class="qrms-an-teshis-body">' +
			'<h2 class="qrms-an-teshis-title">' + ORTAK.esc( metin( 'justStartedTitle', 'Toplanmaya yeni başlandı' ) ) + '</h2>' +
			'<p class="qrms-an-teshis-text">' + ORTAK.esc( yeniBasladi() ) + '</p>' +
			'</div></div>';
	}

	function veriPanelleriniAyarla( bos ) {
		var paneller = document.querySelectorAll( '.qrms-an-sepet-veri-paneli' );
		var i;

		for ( i = 0; i < paneller.length; i++ ) {
			paneller[ i ].hidden = !! bos;
		}
	}

	/**
	 * İlk kurulumda uzun metin masaüstünde kalır; mobilde "Veri yok" + neden?
	 */
	function bosDurumSepet( ikon, baslangic, yedek ) {
		if ( ! baslangic ) {
			return ORTAK.bosDurum( ikon, yedek );
		}

		return '<div class="qrms-an-empty">' +
			'<span class="qrms-an-empty-icon dashicons ' + ORTAK.esc( ikon ) + '" aria-hidden="true"></span>' +
			'<p class="qrms-an-empty-text">' +
			'<span class="qrms-an-empty-kisa">' + ORTAK.esc( metin( 'emptyNone', 'Veri yok' ) ) +
			' <a class="qrms-an-empty-neden" href="#qrms-an-sepet-bos">' +
			ORTAK.esc( metin( 'emptyWhy', 'neden?' ) ) + '</a></span>' +
			'<span class="qrms-an-empty-uzun">' + ORTAK.esc( yeniBasladi() ) + '</span>' +
			'</p></div>';
	}

	function teshiseKaydir( e ) {
		var bag = e.target.closest ? e.target.closest( '.qrms-an-empty-neden' ) : null;
		var hedef;
		var azalt;

		if ( ! bag ) {
			return;
		}

		e.preventDefault();
		hedef = el.bos || document.getElementById( 'qrms-an-sepet-bos' );

		if ( ! hedef ) {
			return;
		}

		azalt = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		hedef.scrollIntoView( { behavior: azalt ? 'auto' : 'smooth', block: 'start' } );
	}

	function urunAdi( u ) {
		return u.ad || metin( 'unknownItem', 'Bilinmeyen ürün' );
	}

	function terkBas( satirlar, bos ) {
		if ( ! satirlar || ! satirlar.length ) {
			el.terk.innerHTML = bosDurumSepet(
				'dashicons-cart',
				bos,
				metin( 'noAbandon', 'Bu aralıkta sepete eklenip gönderilmeyen ürün yok.' )
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
			el.cikar.innerHTML = bosDurumSepet(
				'dashicons-undo',
				bos,
				metin( 'noRemove', 'Bu aralıkta sepetten çıkarma yok.' )
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
			el.engel.innerHTML = bosDurumSepet(
				'dashicons-hidden',
				bos,
				metin( 'noBlocked', 'Bu aralıkta tükendi nedeniyle engellenen sipariş yok.' )
			);
			return;
		}

		var basliklar = [
			metin( 'product', 'Ürün' ),
			metin( 'category', 'Kategori' ),
			metin( 'missedOrders', 'Kaçırılan sipariş' ),
			metin( 'missedRevenue', 'Kaçan ciro' ),
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
				ORTAK.hucre( basliklar[ 3 ], '<span class="qrms-an-val-bold">' + ORTAK.para( u.ciro ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 4 ], bag ) +
				'</tr>';
		} );

		el.engel.innerHTML = ORTAK.tabloIskelet( basliklar, govde, '' );
	}

	function ciroBas( satirlar, bos ) {
		if ( ! satirlar || ! satirlar.length ) {
			el.ciro.innerHTML = bosDurumSepet(
				'dashicons-money-alt',
				bos,
				metin( 'noRevenue', 'Bu aralıkta gönderilmiş sipariş yok.' )
			);
			return;
		}

		var basliklar = [
			metin( 'product', 'Ürün' ),
			metin( 'category', 'Kategori' ),
			metin( 'unitsSold', 'Satılan adet' ),
			metin( 'revenue', 'Ciro' )
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
				ORTAK.hucre( basliklar[ 2 ], '<span class="qrms-an-val-bold">' + ORTAK.sayi( u.adet ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 3 ], '<span class="qrms-an-val-gold">' + ORTAK.para( u.ciro ) + '</span>' ) +
				'</tr>';
		} );

		el.ciro.innerHTML = ORTAK.tabloIskelet( basliklar, govde, '' );
	}

	function huniBas( huni, bos ) {
		if ( ! el.huni ) {
			return;
		}

		huni = huni || {};

		var tepe = huni.view || 0;
		var asamalar = [
			{ ikon: 'dashicons-visibility', etiket: metin( 'funnelView', 'Menü görüntüleme' ), sayi: huni.view || 0 },
			{ ikon: 'dashicons-admin-links', etiket: metin( 'funnelClick', 'Ürün tıklama' ), sayi: huni.click || 0 },
			{ ikon: 'dashicons-cart', etiket: metin( 'funnelCart', 'Sepete ekleme' ), sayi: huni.cart || 0 },
			{ ikon: 'dashicons-yes-alt', etiket: metin( 'funnelOrder', 'Sipariş' ), sayi: huni.orders || 0 }
		];

		if ( ! tepe ) {
			el.huni.innerHTML = bosDurumSepet( 'dashicons-filter', !! bos, yeniBasladi() );
			return;
		}

		var html = '<div class="qrms-an-huni">';

		asamalar.forEach( function ( a ) {
			var yuzde = ORTAK.oran( a.sayi, tepe );

			html += '<div class="qrms-an-huni-asama">' +
				'<div class="qrms-an-huni-bar-track">' +
				'<div class="qrms-an-huni-bar ' + ORTAK.oranSinifi( yuzde ) + '" style="width:' + Math.max( yuzde, 2 ) + '%"></div>' +
				'</div>' +
				'<span class="dashicons ' + ORTAK.esc( a.ikon ) + '" aria-hidden="true"></span>' +
				'<span class="qrms-an-huni-etiket">' + ORTAK.esc( a.etiket ) + '</span>' +
				'<span class="qrms-an-huni-deger">' + ORTAK.sayi( a.sayi ) + ' <span class="qrms-an-muted">(%' + yuzde + ')</span></span>' +
				'</div>';
		} );

		el.huni.innerHTML = html + '</div>';
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
		huniBas( veri.huni, bos );
		veriPanelleriniAyarla( bos );

		if ( ! bos ) {
			ciroBas( veri.en_cok_ciro, bos );
			terkBas( veri.terk_urun, bos );
			cikarBas( veri.cikarilan, bos );
			engelBas( veri.engellenen, bos );
		}

		hataBas( ozet, veri.hatalar );
	}

	function hataGoster() {
		var msg = metin( 'loadError', 'Veri yüklenemedi. Sayfayı yenileyin.' );

		veriPanelleriniAyarla( false );
		el.cards.innerHTML = '';
		if ( el.huni ) {
			el.huni.innerHTML = ORTAK.bosDurum( 'dashicons-warning', msg );
		}
		el.ciro.innerHTML  = '';
		el.terk.innerHTML  = '';
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
		el.huni      = $( 'qrms-an-sepet-huni' );
		el.ciro      = $( 'qrms-an-sepet-ciro' );
		el.terk      = $( 'qrms-an-sepet-terk' );
		el.cikar     = $( 'qrms-an-sepet-cikar' );
		el.engel     = $( 'qrms-an-sepet-engel' );
		el.hataPanel = $( 'qrms-an-sepet-hata-panel' );
		el.hataUyari = $( 'qrms-an-sepet-hata-uyari' );
		el.hata      = $( 'qrms-an-sepet-hata' );

		if ( ! el.cards || ! el.terk ) {
			return;
		}

		var kap = document.querySelector( '.qrms-an-sepet' );

		ORTAK.filtreKur( kap );

		if ( kap ) {
			kap.addEventListener( 'click', teshiseKaydir );
		}

		yukle();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', hazir );
	} else {
		hazir();
	}
}() );
