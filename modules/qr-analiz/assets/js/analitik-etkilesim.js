/**
 * QR Menü — Müşteri Etkileşimi kategorisi.
 *
 * Chatbot zaman dağılımı, yorum/form, ödül, dil ve galeri tek bir AJAX
 * çağrısının (qrms_analitik_etkilesim) sonucundan üretilir. Aralık/masa
 * ADRESTEDİR; bu dosya onları değiştirmez. Lisansta pasif bir modülün
 * paneli PHP'de basılmaz, ilgili kap da yoktur — yoksa sessizce atlanır.
 *
 * Boş tablo bir hata değildir: bu olaylar Faz 8 ile toplanmaya başladı.
 */
( function () {
	'use strict';

	var CFG   = window.qrmsAnalitikEtkilesim || {};
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
			'Etkileşim olayları toplanmaya yeni başladı. Bu bir hata değil; chatbot, yorum, dil seçimi ve galeri kullanıldıkça sayılar burada görünecek.'
		);
	}

	function kartHtml( kartlar ) {
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

		return html;
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

	function chatbotBas( ozet, grafik, bos ) {
		if ( ! el.chatbotCards ) {
			return;
		}

		el.chatbotCards.innerHTML = kartHtml( [
			{
				ikon: 'dashicons-format-chat',
				etiket: metin( 'cardChatbot', 'Chatbot mesajı' ),
				deger: ORTAK.kisa( ozet.chatbot ),
				alt: ORTAK.sayi( ozet.chatbot ) + ' ' + metin( 'events', 'olay' )
			}
		] );

		if ( ! el.chatbotGrafik ) {
			return;
		}

		if ( ! grafik || ! grafik.length || ! ozet.chatbot ) {
			el.chatbotGrafik.innerHTML = ORTAK.bosDurum(
				'dashicons-format-chat',
				bos ? yeniBasladi() : metin( 'noChatbot', 'Bu aralıkta chatbot mesajı yok.' )
			);
			return;
		}

		var satirlar = grafik.map( function ( s ) {
			return {
				label: s.etiket,
				mv: s.adet,
				pc: 0
			};
		} );

		el.chatbotGrafik.innerHTML = '<div class="qrms-an-chart">' +
			ORTAK.grafikHtml( satirlar, {
				mv: metin( 'chatbotMsg', 'Mesaj' ),
				pc: metin( 'chatbotMsg', 'Mesaj' )
			} ) +
			'</div>';
	}

	function yorumBas( ozet, formlar, bos ) {
		if ( ! el.yorumCards ) {
			return;
		}

		el.yorumCards.innerHTML = kartHtml( [
			{
				ikon: 'dashicons-star-filled',
				etiket: metin( 'cardReview', 'Yorum gönderimi' ),
				deger: ORTAK.kisa( ozet.review ),
				alt: ORTAK.sayi( ozet.review ) + ' ' + metin( 'events', 'olay' )
			},
			{
				ikon: 'dashicons-feedback',
				etiket: metin( 'cardForm', 'Form gönderimi' ),
				deger: ORTAK.kisa( ozet.form ),
				alt: ORTAK.sayi( ozet.form ) + ' ' + metin( 'events', 'olay' )
			}
		] );

		if ( ! el.formlar ) {
			return;
		}

		if ( ! formlar || ! formlar.length ) {
			el.formlar.innerHTML = ORTAK.bosDurum(
				'dashicons-feedback',
				bos ? yeniBasladi() : metin( 'noForm', 'Bu aralıkta form gönderimi yok.' )
			);
			return;
		}

		var basliklar = [
			metin( 'formName', 'Form' ),
			metin( 'submissions', 'Gönderim' )
		];
		var govde = '';

		formlar.forEach( function ( f, i ) {
			var sira = i + 1;
			var sinif = sira <= 3 ? 'qrms-an-rank-' + sira : 'qrms-an-rank-n';

			govde += '<tr>' +
				ORTAK.hucre( basliklar[ 0 ],
					'<span class="qrms-an-rank ' + sinif + '">' + sira + '</span> ' +
					'<strong>' + ORTAK.esc( f.ad || '—' ) + '</strong>'
				) +
				ORTAK.hucre( basliklar[ 1 ], '<span class="qrms-an-val-gold">' + ORTAK.sayi( f.adet ) + '</span>' ) +
				'</tr>';
		} );

		el.formlar.innerHTML = ORTAK.tabloIskelet( basliklar, govde, '' );
	}

	function odulBas( ozet ) {
		if ( ! el.odulCards ) {
			return;
		}

		el.odulCards.innerHTML = kartHtml( [
			{
				ikon: 'dashicons-awards',
				etiket: metin( 'cardIssued', 'Üretilen kod' ),
				deger: ORTAK.kisa( ozet.reward_issued ),
				alt: metin( 'rewardSource', 'Ödül tablosuyla aynı zaman ekseni' )
			},
			{
				ikon: 'dashicons-yes-alt',
				etiket: metin( 'cardRedeemed', 'Kullanılan kod' ),
				deger: ORTAK.kisa( ozet.reward_redeemed ),
				alt: ORTAK.sayi( ozet.reward_redeemed ) + ' ' + metin( 'events', 'olay' )
			},
			{
				ikon: 'dashicons-chart-line',
				etiket: metin( 'cardRate', 'Dönüşüm oranı' ),
				deger: '%' + ORTAK.sayi( ozet.reward_oran ),
				alt: metin( 'rewardRateHint', 'Kullanılan / üretilen' )
			}
		] );
	}

	function dilBas( diller, bos ) {
		if ( ! el.diller ) {
			return;
		}

		if ( ! diller || ! diller.length ) {
			el.diller.innerHTML = ORTAK.bosDurum(
				'dashicons-translation',
				bos ? yeniBasladi() : metin( 'noLang', 'Bu aralıkta dil değişimi yok.' )
			);
			return;
		}

		var basliklar = [
			metin( 'language', 'Dil' ),
			metin( 'switches', 'Seçim' ),
			metin( 'share', 'Pay' )
		];
		var govde = '';

		diller.forEach( function ( d ) {
			var pay = parseInt( d.pay, 10 ) || 0;

			govde += '<tr>' +
				ORTAK.hucre(
					basliklar[ 0 ],
					'<strong>' + ORTAK.esc( d.ad || d.kod || '—' ) + '</strong>' +
					( d.kod ? ' <span class="qrms-an-muted">' + ORTAK.esc( d.kod ) + '</span>' : '' )
				) +
				ORTAK.hucre( basliklar[ 1 ], '<span class="qrms-an-val-gold">' + ORTAK.sayi( d.adet ) + '</span>' ) +
				ORTAK.hucre(
					basliklar[ 2 ],
					'<span class="qrms-an-progress"><span class="qrms-an-progress-bg">' +
					'<span class="qrms-an-progress-fill" style="width:' + pay + '%"></span></span>' +
					'<span class="qrms-an-progress-pct">%' + pay + '</span></span>'
				) +
				'</tr>';
		} );

		el.diller.innerHTML = ORTAK.tabloIskelet( basliklar, govde, '' );
	}

	function galeriBas( ozet ) {
		if ( ! el.galeriCards ) {
			return;
		}

		el.galeriCards.innerHTML = kartHtml( [
			{
				ikon: 'dashicons-format-gallery',
				etiket: metin( 'cardGallery', 'Galeri görüntüleme' ),
				deger: ORTAK.kisa( ozet.gallery ),
				alt: ORTAK.sayi( ozet.gallery ) + ' ' + metin( 'events', 'olay' )
			}
		] );
	}

	function bas( veri ) {
		var ozet = veri.ozet || {};
		var bos  = !! veri.bos;

		bosKutuBas( bos );
		chatbotBas( ozet, veri.grafik, bos );
		yorumBas( ozet, veri.formlar, bos );
		odulBas( ozet );
		dilBas( veri.diller, bos );
		galeriBas( ozet );
	}

	function hataGoster() {
		var msg = metin( 'loadError', 'Veri yüklenemedi. Sayfayı yenileyin.' );

		if ( el.chatbotGrafik ) {
			el.chatbotGrafik.innerHTML = ORTAK.bosDurum( 'dashicons-warning', msg );
		}

		if ( el.formlar ) {
			el.formlar.innerHTML = ORTAK.bosDurum( 'dashicons-warning', msg );
		}

		if ( el.diller ) {
			el.diller.innerHTML = ORTAK.bosDurum( 'dashicons-warning', msg );
		}
	}

	function yukle() {
		ORTAK.post(
			CFG.ajaxUrl,
			{
				action: 'qrms_analitik_etkilesim',
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
		el.bos          = $( 'qrms-an-etkilesim-bos' );
		el.chatbotCards = $( 'qrms-an-etk-chatbot-cards' );
		el.chatbotGrafik = $( 'qrms-an-etk-chatbot-grafik' );
		el.yorumCards   = $( 'qrms-an-etk-yorum-cards' );
		el.formlar      = $( 'qrms-an-etk-formlar' );
		el.odulCards    = $( 'qrms-an-etk-odul-cards' );
		el.diller       = $( 'qrms-an-etk-diller' );
		el.galeriCards  = $( 'qrms-an-etk-galeri-cards' );

		if ( ! el.bos && ! el.chatbotCards && ! el.yorumCards && ! el.diller && ! el.galeriCards ) {
			return;
		}

		ORTAK.filtreKur( document.querySelector( '.qrms-an-etkilesim' ) );
		yukle();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', hazir );
	} else {
		hazir();
	}
}() );
