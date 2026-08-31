/**
 * QR Menü — Genel Bakış kategorisi.
 *
 * Sayfa iskeleti ve filtre çubuğu PHP'den gelir. Buradaki iş üç şeydir:
 * özet kartları, çubuk grafik ve dönem tablosu — hepsi tek bir AJAX
 * çağrısının (qrms_analitik_genel) sonucundan üretilir.
 *
 * ARALIK ≠ KIRILIM. Aralık (bugün / son 7 gün / bu ay / özel) paylaşılan
 * filtre çubuğundadır ve SAYFA YENİLEYEREK değişir — çünkü kategoriler
 * arasında taşınması gerekir. Kırılım (saatlik/günlük/haftalık/aylık) yalnızca
 * bu grafiğin gruplamasıdır; sayfayı yenilemeden, aynı uçtan yeni veri ister.
 *
 * Biçimlendirme, AJAX ve grafik çizimi ORTAK dosyadadır (analitik-ortak.js);
 * burada yalnızca bu ekrana ait olan kalır.
 */
( function () {
	'use strict';

	var CFG   = window.qrmsAnalitikGenel || {};
	var T     = CFG.i18n || {};
	var ORTAK = window.qrmsAnOrtak;

	if ( ! ORTAK ) {
		return;
	}

	var state = {
		kirilim: CFG.kirilim || 'daily',
		// donem/masa/bas/bit adresten gelir ve BURADA DEĞİŞMEZ; uca olduğu
		// gibi geri gönderilir ki sunucu aralığı tek yerde çözsün.
		onbellek: {}
	};

	var el = {};

	function $( id ) {
		return document.getElementById( id );
	}

	function metin( anahtar, yedek ) {
		return T[ anahtar ] || yedek;
	}

	/* -----------------------------------------------------------------
	   KARTLAR
	----------------------------------------------------------------- */

	/**
	 * Önceki eşit uzunluktaki pencereye göre değişim rozeti.
	 *
	 * Önceki pencerede hiç kayıt yoksa yüzde hesaplanamaz (sıfıra bölme);
	 * o durumda rozet basılmaz — "%∞ artış" bilgi değil gürültüdür.
	 */
	function degisim( simdiki, onceki ) {
		if ( ! onceki ) {
			return '';
		}

		var yuzde = Math.round( ( ( simdiki - onceki ) / onceki ) * 100 );
		var sinif = yuzde > 0 ? 'qrms-an-delta-up' : ( yuzde < 0 ? 'qrms-an-delta-down' : 'qrms-an-delta-flat' );
		var isaret = yuzde > 0 ? '+' : '';

		return '<span class="qrms-an-delta ' + sinif + '">' + isaret + yuzde + '%</span>';
	}

	function kartlariBas( ozet ) {
		var kartlar = [
			{
				ikon: 'dashicons-visibility',
				etiket: metin( 'cardViews', 'Menü Görüntüleme' ),
				deger: ORTAK.kisa( ozet.mv ),
				delta: degisim( ozet.mv, ozet.mv_onceki ),
				alt: metin( 'vsPrev', 'Önceki döneme göre' )
			},
			{
				ikon: 'dashicons-pressthis',
				etiket: metin( 'cardClicks', 'Ürün Tıklama' ),
				deger: ORTAK.kisa( ozet.pc ),
				delta: degisim( ozet.pc, ozet.pc_onceki ),
				alt: metin( 'conversion', 'Dönüşüm' ) + ': %' + ORTAK.oran( ozet.pc, ozet.mv )
			},
			{
				ikon: 'dashicons-admin-site-alt3',
				etiket: metin( 'cardUnique', 'Tekil Ziyaretçi' ),
				deger: ORTAK.kisa( ozet.uv ),
				delta: degisim( ozet.uv, ozet.uv_onceki ),
				alt: metin( 'ipNote', 'IP bazlı, gizlilik korumalı' )
			},
			{
				ikon: 'dashicons-editor-table',
				etiket: metin( 'cardTables', 'Hareketli Masa' ),
				deger: ORTAK.kisa( ozet.masa_sayisi ),
				delta: '',
				alt: metin( 'cardTablesSub2', 'Seçili aralıkta hareket eden masa' )
			}
		];

		var html = '';

		kartlar.forEach( function ( kart ) {
			html += '<div class="qrms-an-card">' +
				'<span class="qrms-an-card-tag">' + ORTAK.esc( CFG.aralikEtiketi || '' ) + '</span>' +
				'<span class="qrms-an-card-icon dashicons ' + ORTAK.esc( kart.ikon ) + '" aria-hidden="true"></span>' +
				'<div class="qrms-an-card-label">' + ORTAK.esc( kart.etiket ) + '</div>' +
				'<div class="qrms-an-card-value">' + ORTAK.esc( kart.deger ) + kart.delta + '</div>' +
				'<div class="qrms-an-card-sub">' + ORTAK.esc( kart.alt ) + '</div>' +
				'</div>';
		} );

		el.cards.innerHTML = html;
	}

	/* -----------------------------------------------------------------
	   GRAFİK + TABLO
	----------------------------------------------------------------- */

	function grafikBas( satirlar ) {
		if ( ! satirlar || ! satirlar.length ) {
			el.chart.innerHTML = ORTAK.bosDurum( 'dashicons-chart-bar', metin( 'noData', 'Bu dönemde henüz veri yok.' ) );
			return;
		}

		el.chart.innerHTML = ORTAK.grafikHtml( satirlar, {
			mv: metin( 'cardViews', 'Menü Görüntüleme' ),
			pc: metin( 'cardClicks', 'Ürün Tıklama' )
		} );
	}

	function sutunBasligi() {
		var basliklar = {
			hourly: metin( 'colHourly', 'Saat' ),
			daily: metin( 'colDaily', 'Tarih' ),
			weekly: metin( 'colWeekly', 'Hafta' ),
			monthly: metin( 'colMonthly', 'Ay' )
		};

		return basliklar[ state.kirilim ] || metin( 'colPeriod', 'Dönem' );
	}

	function tabloBas( satirlar ) {
		if ( ! satirlar || ! satirlar.length ) {
			el.table.innerHTML = '';
			return;
		}

		var basliklar = [
			sutunBasligi(),
			metin( 'cardViews', 'Menü Görüntüleme' ),
			metin( 'cardClicks', 'Ürün Tıklama' ),
			metin( 'cardUnique', 'Tekil Ziyaretçi' ),
			metin( 'conversion', 'Dönüşüm' )
		];

		var toplamMv = 0;
		var toplamPc = 0;
		var toplamUv = 0;
		var govde    = '';

		satirlar.forEach( function ( s ) {
			toplamMv += s.mv;
			toplamPc += s.pc;
			toplamUv += s.uv;

			var o = ORTAK.oran( s.pc, s.mv );

			govde += '<tr>' +
				ORTAK.hucre( basliklar[ 0 ], '<strong>' + ORTAK.esc( s.label ) + '</strong>' ) +
				ORTAK.hucre( basliklar[ 1 ], '<span class="qrms-an-val-gold">' + ORTAK.sayi( s.mv ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 2 ], '<span class="qrms-an-val-blue">' + ORTAK.sayi( s.pc ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 3 ], '<span class="qrms-an-val-bold">' + ORTAK.sayi( s.uv ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 4 ], '<span class="qrms-an-pill ' + ORTAK.oranSinifi( o ) + '">%' + o + '</span>' ) +
				'</tr>';
		} );

		var toplamOran = ORTAK.oran( toplamPc, toplamMv );
		var altToplam  = '<tr class="qrms-an-total">' +
			ORTAK.hucre( basliklar[ 0 ], '<strong>' + ORTAK.esc( metin( 'total', 'TOPLAM' ) ) + '</strong>' ) +
			ORTAK.hucre( basliklar[ 1 ], '<span class="qrms-an-val-gold">' + ORTAK.sayi( toplamMv ) + '</span>' ) +
			ORTAK.hucre( basliklar[ 2 ], '<span class="qrms-an-val-blue">' + ORTAK.sayi( toplamPc ) + '</span>' ) +
			ORTAK.hucre( basliklar[ 3 ], '<span class="qrms-an-val-bold">' + ORTAK.sayi( toplamUv ) + '</span>' ) +
			ORTAK.hucre( basliklar[ 4 ], '<span class="qrms-an-pill ' + ORTAK.oranSinifi( toplamOran ) + '">%' + toplamOran + '</span>' ) +
			'</tr>';

		// Zaman serisi tablosu (24 saat, 30 gün…) dar ekranda kart görünümünde
		// çok uzar; aynı veri hemen üstteki grafikte zaten var. Bu yüzden
		// açılır bir bölüme alınır — geniş ekranda açık gelir.
		var baslik = metin( 'periodTable', 'Dönem tablosu' ) + ' · ' + satirlar.length + ' ' + metin( 'rows', 'satır' );

		el.table.innerHTML = ORTAK.acilirBolum( baslik, ORTAK.tabloIskelet( basliklar, govde, altToplam ) );
	}

	/* -----------------------------------------------------------------
	   VERİ
	----------------------------------------------------------------- */

	function baslik() {
		var etiketler = CFG.kirilimEtiketleri || {};
		var parcalar  = [ etiketler[ state.kirilim ] || '', CFG.aralikEtiketi || '' ];

		if ( CFG.masaEtiketi ) {
			parcalar.push( CFG.masaEtiketi );
		}

		return parcalar.filter( Boolean ).join( ' · ' );
	}

	function veriyiBas( veri ) {
		state.kirilim = veri.kirilim || state.kirilim;

		kartlariBas( veri.ozet || {} );
		grafikBas( veri.grafik );
		tabloBas( veri.grafik );

		el.chartTitle.textContent = baslik();
		kirilimiIsaretle();
	}

	function kirilimiIsaretle() {
		if ( ! el.kirilimler ) {
			return;
		}

		Array.prototype.forEach.call( el.kirilimler, function ( dugme ) {
			var aktif = dugme.getAttribute( 'data-kirilim' ) === state.kirilim;

			dugme.classList.toggle( 'is-active', aktif );
			dugme.setAttribute( 'aria-selected', aktif ? 'true' : 'false' );
		} );
	}

	function yukle() {
		// Aynı kırılım daha önce geldiyse yeniden istenmez: aralık ve masa
		// sayfa ömrü boyunca sabittir (değişmeleri sayfayı yeniler).
		if ( state.onbellek[ state.kirilim ] ) {
			veriyiBas( state.onbellek[ state.kirilim ] );
			return;
		}

		el.chart.innerHTML = '<div class="qrms-an-loading">' +
			ORTAK.esc( metin( 'loadingChart', 'Grafik yükleniyor' ) ) + '</div>';
		el.table.innerHTML = '';

		ORTAK.post(
			CFG.ajaxUrl,
			{
				action: 'qrms_analitik_genel',
				security: CFG.nonce,
				donem: CFG.donem,
				masa: CFG.masa,
				bas: CFG.bas,
				bit: CFG.bit,
				kirilim: state.kirilim
			},
			function ( veri ) {
				state.onbellek[ veri.kirilim || state.kirilim ] = veri;
				veriyiBas( veri );
			},
			function () {
				el.chart.innerHTML = ORTAK.bosDurum(
					'dashicons-warning',
					metin( 'loadError', 'Veri yüklenemedi. Sayfayı yenileyin.' )
				);
				el.cards.innerHTML = '';
			}
		);
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		el.wrap = document.querySelector( '.qrms-an-genel' );

		if ( ! el.wrap || ! CFG.ajaxUrl ) {
			return;
		}

		el.cards      = $( 'qrms-an-cards' );
		el.chart      = $( 'qrms-an-chart' );
		el.chartTitle = $( 'qrms-an-chart-title' );
		el.table      = $( 'qrms-an-table' );
		el.kirilimler = el.wrap.querySelectorAll( '.qrms-an-kirilimlar .qrms-an-tab' );

		el.wrap.addEventListener( 'click', function ( olay ) {
			var dugme = olay.target.closest( '.qrms-an-kirilimlar .qrms-an-tab' );

			if ( ! dugme ) {
				return;
			}

			var kirilim = dugme.getAttribute( 'data-kirilim' );

			if ( ! kirilim || kirilim === state.kirilim ) {
				return;
			}

			state.kirilim = kirilim;
			kirilimiIsaretle();
			yukle();
		} );

		// Filtre çubuğu (aralık + masa) ortak bileşendir; canlandırması da
		// ortaktır. Değişimi adresi yeniler, bu ekranda durum tutmaz.
		ORTAK.filtreKur( el.wrap );

		yukle();
	} );
}() );
