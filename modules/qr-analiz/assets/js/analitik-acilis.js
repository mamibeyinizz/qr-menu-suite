/**
 * QR Menü — Açılış Ekranı kategorisi.
 *
 * Üç özet kartı ve buton tablosu tek bir AJAX çağrısının
 * (qrms_analitik_acilis) sonucundan üretilir. Aralık/masa ADRESTEDİR.
 *
 * splash_view sıfırken menüye geçiş / atlanma oranı %0 basılır; bölme yok.
 * Boş tablo bir hata değildir: bu olaylar Faz 8 ile toplanmaya başladı.
 */
( function () {
	'use strict';

	var CFG   = window.qrmsAnalitikAcilis || {};
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
		return metin(
			'justStarted',
			'Açılış ekranı olayları toplanmaya yeni başladı. Bu bir hata değil; misafirler açılışı gördükçe sayılar burada görünecek.'
		);
	}

	function kartlariBas( ozet ) {
		var kartlar = [
			{
				ikon: 'dashicons-visibility',
				etiket: metin( 'cardView', 'Gösterim' ),
				deger: ORTAK.kisa( ozet.view ),
				alt: ORTAK.sayi( ozet.view ) + ' ' + metin( 'events', 'olay' )
			},
			{
				ikon: 'dashicons-food',
				etiket: metin( 'cardMenu', 'Menüye geçiş oranı' ),
				deger: '%' + ORTAK.sayi( ozet.menu_oran ),
				alt: ORTAK.sayi( ozet.menu ) + ' / ' + ORTAK.sayi( ozet.view )
			},
			{
				ikon: 'dashicons-controls-forward',
				etiket: metin( 'cardSkip', 'Atlanma oranı' ),
				deger: '%' + ORTAK.sayi( ozet.atla_oran ),
				alt: ORTAK.sayi( ozet.atla ) + ' / ' + ORTAK.sayi( ozet.view )
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

	function butonBas( satirlar, bos ) {
		var toplam = 0;

		if ( satirlar ) {
			satirlar.forEach( function ( s ) {
				toplam += parseInt( s.adet, 10 ) || 0;
			} );
		}

		if ( ! toplam ) {
			el.butonlar.innerHTML = ORTAK.bosDurum(
				'dashicons-screenoptions',
				bos ? yeniBasladi() : metin( 'noButtons', 'Bu aralıkta açılış butonu tıklaması yok.' )
			);
			return;
		}

		var basliklar = [
			metin( 'button', 'Buton' ),
			metin( 'clicks', 'Tıklama' ),
			metin( 'share', 'Pay (gösterime)' )
		];
		var govde = '';

		satirlar.forEach( function ( s ) {
			var pay = parseInt( s.pay, 10 ) || 0;

			govde += '<tr>' +
				ORTAK.hucre( basliklar[ 0 ], '<strong>' + ORTAK.esc( s.ad || s.kod || '—' ) + '</strong>' ) +
				ORTAK.hucre( basliklar[ 1 ], '<span class="qrms-an-val-gold">' + ORTAK.sayi( s.adet ) + '</span>' ) +
				ORTAK.hucre(
					basliklar[ 2 ],
					'<span class="qrms-an-progress"><span class="qrms-an-progress-bg">' +
					'<span class="qrms-an-progress-fill" style="width:' + pay + '%"></span></span>' +
					'<span class="qrms-an-progress-pct">%' + pay + '</span></span>'
				) +
				'</tr>';
		} );

		el.butonlar.innerHTML = ORTAK.tabloIskelet( basliklar, govde, '' );
	}

	function bas( veri ) {
		var ozet = veri.ozet || {};
		var bos  = !! veri.bos;

		bosKutuBas( bos );
		kartlariBas( ozet );
		butonBas( veri.butonlar, bos );
	}

	function hataGoster() {
		var msg = metin( 'loadError', 'Veri yüklenemedi. Sayfayı yenileyin.' );

		el.cards.innerHTML    = '';
		el.butonlar.innerHTML = ORTAK.bosDurum( 'dashicons-warning', msg );
	}

	function yukle() {
		ORTAK.post(
			CFG.ajaxUrl,
			{
				action: 'qrms_analitik_acilis',
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
		el.cards    = $( 'qrms-an-acilis-cards' );
		el.bos      = $( 'qrms-an-acilis-bos' );
		el.butonlar = $( 'qrms-an-acilis-butonlar' );

		if ( ! el.cards || ! el.butonlar ) {
			return;
		}

		ORTAK.filtreKur( document.querySelector( '.qrms-an-acilis' ) );
		yukle();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', hazir );
	} else {
		hazir();
	}
}() );
