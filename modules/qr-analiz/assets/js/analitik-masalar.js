/**
 * QR Menü — Masalar kategorisi.
 *
 * Üç şey basar: (varsa) odaklanılan masanın karnesi, bütün masaların
 * karşılaştırmalı listesi ve masa gruplarının toplamları. Üçü de tek bir AJAX
 * çağrısının (qrms_analitik_masalar) sonucundan üretilir.
 *
 * MASA FİLTRESİ BURADA "ODAK" DEMEKTİR. Sayfanın kendisi zaten masa
 * kırılımıdır; filtre seçiliyken liste daralmaz, üstüne o masanın karnesi
 * eklenir ve satırı vurgulanır. Seçim ADRESTEDİR (paylaşılan filtre çubuğu),
 * bu dosya onu değiştirmez.
 *
 * Biçimlendirme, AJAX ve tablo iskeleti ORTAK dosyadadır (analitik-ortak.js).
 */
( function () {
	'use strict';

	var CFG   = window.qrmsAnalitikMasalar || {};
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

	/** Satırın durum rozeti: kayıtlı ama sessiz, silinmiş, ya da masasız. */
	function durumRozeti( satir ) {
		if ( 'kayitsiz' === satir.durum ) {
			return '<span class="qrms-an-badge" title="' +
				ORTAK.esc( metin( 'unknownHint', 'Bu masa qr-masa listesinde yok; silinmiş olabilir. Geçmiş kayıtları duruyor.' ) ) +
				'">' + ORTAK.esc( metin( 'unknown', 'kayıtlı değil' ) ) + '</span>';
		}

		if ( 'masasiz' === satir.durum ) {
			return '<span class="qrms-an-badge">' + ORTAK.esc( metin( 'direct', 'QR\'sız' ) ) + '</span>';
		}

		if ( 0 === satir.mv + satir.pc ) {
			return '<span class="qrms-an-badge qrms-an-badge-warn" title="' +
				ORTAK.esc( metin( 'silentHint', 'Bu masanın QR kodu seçili aralıkta hiç okutulmadı. QR basılmamış, yapıştırılmamış ya da yıpranmış olabilir.' ) ) +
				'">' + ORTAK.esc( metin( 'silent', 'hiç okutulmadı' ) ) + '</span>';
		}

		return '';
	}

	/* -----------------------------------------------------------------
	   ODAK KARNESİ
	----------------------------------------------------------------- */

	function odakBas( satir, gruptakiler ) {
		if ( ! satir ) {
			el.odak.innerHTML = '';
			return;
		}

		var kutular = [
			{
				etiket: metin( 'cardViews', 'Menü Okutma' ),
				deger: ORTAK.sayi( satir.mv )
			},
			{
				etiket: metin( 'cardClicks', 'Ürün Tıklama' ),
				deger: ORTAK.sayi( satir.pc )
			},
			{
				etiket: metin( 'cardUnique', 'Tekil Ziyaretçi' ),
				deger: ORTAK.sayi( satir.uv )
			},
			{
				etiket: metin( 'rank', 'Sıralama' ),
				deger: satir.sira + ' / ' + satir.toplam
			}
		];

		var html = '<div class="qrms-an-panel qrms-an-odak">' +
			'<div class="qrms-an-panel-header"><h2 class="qrms-an-panel-title">' +
			'<span class="dashicons dashicons-location" aria-hidden="true"></span> ' +
			ORTAK.esc( satir.label ) + ' ' + durumRozeti( satir ) +
			'</h2></div>' +
			'<div class="qrms-an-cards">';

		kutular.forEach( function ( kutu ) {
			html += '<div class="qrms-an-card">' +
				'<div class="qrms-an-card-label">' + ORTAK.esc( kutu.etiket ) + '</div>' +
				'<div class="qrms-an-card-value">' + ORTAK.esc( kutu.deger ) + '</div>' +
				'</div>';
		} );

		html += '</div>';

		html += '<p class="qrms-an-panel-note">' +
			ORTAK.esc( metin( 'lastSeen', 'Son hareket' ) + ': ' + ( satir.son ? ORTAK.tarih( satir.son ) : '—' ) ) +
			'</p>';

		// "Bu masa mı sessiz, yoksa bütün grup mu?" — komşuları görmeden
		// tek bir sayı yanıltıcıdır.
		if ( gruptakiler && gruptakiler.length > 1 ) {
			var toplam = 0;

			gruptakiler.forEach( function ( k ) {
				toplam += k.mv + k.pc;
			} );

			html += '<p class="qrms-an-panel-note">' +
				ORTAK.esc(
					metin( 'groupCompare', 'Grubu' ) + ' "' + satir.grup + '": ' +
					gruptakiler.length + ' ' + metin( 'tables', 'masa' ) + ' · ' +
					metin( 'totalMoves', 'toplam hareket' ) + ': ' + ORTAK.sayi( toplam )
				) +
				'</p>';
		}

		html += '</div>';

		el.odak.innerHTML = html;
	}

	/* -----------------------------------------------------------------
	   MASA LİSTESİ
	----------------------------------------------------------------- */

	function listeBas( satirlar, ozet ) {
		if ( ! satirlar || ! satirlar.length ) {
			el.liste.innerHTML = ORTAK.bosDurum( 'dashicons-editor-table', metin( 'noTables', 'Tanımlı masa yok ve bu aralıkta hareket kaydedilmemiş.' ) );
			return;
		}

		var basliklar = [
			metin( 'colMasa', 'Masa' ),
			metin( 'group', 'Grup' ),
			metin( 'cardViews', 'Menü Okutma' ),
			metin( 'cardClicks', 'Ürün Tıklama' ),
			metin( 'cardUnique', 'Tekil Ziyaretçi' ),
			metin( 'lastSeen', 'Son hareket' ),
			metin( 'action', 'İşlem' )
		];

		var toplamMv = 0;
		var toplamPc = 0;
		var toplamUv = 0;
		var govde    = '';

		satirlar.forEach( function ( s ) {
			toplamMv += s.mv;
			toplamPc += s.pc;
			toplamUv += s.uv;

			var sessiz = 0 === s.mv + s.pc;
			var odakta = CFG.masa && s.masa === CFG.masa;

			// Masa seçimi paylaşılan bağlamdır: sayfa içi durum yerine adrese
			// yazılır, böylece diğer kategorilere de taşınır.
			var dugme = s.masa
				? '<a class="qrms-an-btn qrms-an-btn-small" href="' +
					ORTAK.esc( String( CFG.masaUrl || '' ).replace( '__MASA__', encodeURIComponent( s.masa ) ) ) + '">' +
					ORTAK.esc( metin( 'filterTable', 'Bu masayı incele' ) ) + '</a>'
				: '<span class="qrms-an-muted">—</span>';

			var sinif = [];

			if ( sessiz ) {
				sinif.push( 'qrms-an-zero' );
			}

			if ( odakta ) {
				sinif.push( 'qrms-an-row-focus' );
			}

			govde += '<tr' + ( sinif.length ? ' class="' + sinif.join( ' ' ) + '"' : '' ) + '>' +
				ORTAK.hucre( basliklar[ 0 ], '<strong>' + ORTAK.esc( s.label ) + '</strong> ' + durumRozeti( s ) ) +
				ORTAK.hucre( basliklar[ 1 ], '<span class="qrms-an-cat">' + ORTAK.esc( s.grup || '—' ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 2 ], '<span class="' + ( sessiz ? 'qrms-an-val-zero' : 'qrms-an-val-gold' ) + '">' + ORTAK.sayi( s.mv ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 3 ], '<span class="qrms-an-val-blue">' + ORTAK.sayi( s.pc ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 4 ], '<span class="qrms-an-val-bold">' + ORTAK.sayi( s.uv ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 5 ], '<span class="qrms-an-muted">' + ORTAK.esc( s.son ? ORTAK.tarih( s.son ) : '—' ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 6 ], dugme ) +
				'</tr>';
		} );

		var altToplam = '<tr class="qrms-an-total">' +
			ORTAK.hucre( basliklar[ 0 ], '<strong>' + ORTAK.esc( metin( 'total', 'TOPLAM' ) ) + '</strong>' ) +
			ORTAK.hucre( basliklar[ 1 ], '' ) +
			ORTAK.hucre( basliklar[ 2 ], '<span class="qrms-an-val-gold">' + ORTAK.sayi( toplamMv ) + '</span>' ) +
			ORTAK.hucre( basliklar[ 3 ], '<span class="qrms-an-val-blue">' + ORTAK.sayi( toplamPc ) + '</span>' ) +
			ORTAK.hucre( basliklar[ 4 ], '<span class="qrms-an-val-bold">' + ORTAK.sayi( toplamUv ) + '</span>' ) +
			ORTAK.hucre( basliklar[ 5 ], '' ) +
			ORTAK.hucre( basliklar[ 6 ], '' ) +
			'</tr>';

		el.liste.innerHTML = ozetSatiri( ozet ) + ORTAK.tabloIskelet( basliklar, govde, altToplam );
	}

	function ozetSatiri( ozet ) {
		if ( ! ozet ) {
			return '';
		}

		var parcalar = [
			metin( 'registered', 'Kayıtlı masa' ) + ': ' + ORTAK.sayi( ozet.kayitli ),
			metin( 'silentCount', 'Hiç okutulmayan' ) + ': ' + ORTAK.sayi( ozet.sessiz )
		];

		if ( ozet.kayitsiz > 0 ) {
			parcalar.push( metin( 'unknownCount', 'Kayıtlı olmayan masa' ) + ': ' + ORTAK.sayi( ozet.kayitsiz ) );
		}

		return '<p class="qrms-an-panel-note">' + ORTAK.esc( parcalar.join( ' · ' ) ) + '</p>';
	}

	/* -----------------------------------------------------------------
	   GRUP TOPLAMLARI
	----------------------------------------------------------------- */

	function gruplariBas( gruplar ) {
		if ( ! gruplar || ! gruplar.length ) {
			el.gruplar.innerHTML = ORTAK.bosDurum( 'dashicons-screenoptions', metin( 'noGroups', 'Gruplandırılacak kayıtlı masa yok.' ) );
			return;
		}

		var basliklar = [
			metin( 'group', 'Grup' ),
			metin( 'tableCount', 'Masa Sayısı' ),
			metin( 'silentCount', 'Hiç okutulmayan' ),
			metin( 'cardViews', 'Menü Okutma' ),
			metin( 'cardClicks', 'Ürün Tıklama' ),
			metin( 'share', 'Pay' )
		];

		var enBuyuk = 1;

		gruplar.forEach( function ( g ) {
			enBuyuk = Math.max( enBuyuk, g.mv + g.pc );
		} );

		var govde = '';

		gruplar.forEach( function ( g ) {
			var pay = Math.max( Math.round( ( ( g.mv + g.pc ) / enBuyuk ) * 100 ), 1 );

			govde += '<tr>' +
				ORTAK.hucre( basliklar[ 0 ], '<strong>' + ORTAK.esc( g.grup ) + '</strong>' ) +
				ORTAK.hucre( basliklar[ 1 ], '<span class="qrms-an-val-bold">' + ORTAK.sayi( g.masa ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 2 ], '<span class="' + ( g.sessiz > 0 ? 'qrms-an-val-zero' : 'qrms-an-muted' ) + '">' + ORTAK.sayi( g.sessiz ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 3 ], '<span class="qrms-an-val-gold">' + ORTAK.sayi( g.mv ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 4 ], '<span class="qrms-an-val-blue">' + ORTAK.sayi( g.pc ) + '</span>' ) +
				ORTAK.hucre(
					basliklar[ 5 ],
					'<span class="qrms-an-progress"><span class="qrms-an-progress-bg">' +
					'<span class="qrms-an-progress-fill" style="width:' + pay + '%"></span></span>' +
					'<span class="qrms-an-progress-pct">%' + pay + '</span></span>'
				) +
				'</tr>';
		} );

		el.gruplar.innerHTML = ORTAK.tabloIskelet( basliklar, govde, '' );
	}

	/* -----------------------------------------------------------------
	   VERİ
	----------------------------------------------------------------- */

	function yukle() {
		el.liste.innerHTML = '<div class="qrms-an-loading">' + ORTAK.esc( metin( 'loading', 'Yükleniyor' ) ) + '</div>';

		ORTAK.post(
			CFG.ajaxUrl,
			{
				action: 'qrms_analitik_masalar',
				security: CFG.nonce,
				donem: CFG.donem,
				masa: CFG.masa,
				bas: CFG.bas,
				bit: CFG.bit
			},
			function ( veri ) {
				odakBas( veri.odakSatir, veri.odakGrup );
				listeBas( veri.satirlar, veri.ozet );
				gruplariBas( veri.gruplar );
			},
			function () {
				el.liste.innerHTML = ORTAK.bosDurum( 'dashicons-warning', metin( 'loadError', 'Veri yüklenemedi. Sayfayı yenileyin.' ) );
				el.gruplar.innerHTML = '';
				el.odak.innerHTML    = '';
			}
		);
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		el.wrap = document.querySelector( '.qrms-an-masalar' );

		if ( ! el.wrap || ! CFG.ajaxUrl ) {
			return;
		}

		el.odak    = $( 'qrms-an-masa-odak' );
		el.liste   = $( 'qrms-an-masa-liste' );
		el.gruplar = $( 'qrms-an-masa-gruplar' );

		// Filtre çubuğu (aralık + masa) ortak bileşendir; değişimi adresi
		// yeniler, bu ekranda durum tutmaz.
		ORTAK.filtreKur( el.wrap );

		yukle();
	} );
}() );
