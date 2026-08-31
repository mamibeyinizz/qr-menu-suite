/**
 * QR Menü — Ürünler kategorisi.
 *
 * Üç tablo: en çok tıklananlar (klasik panelden taşındı), en az tıklananlar
 * ve kategori dağılımı. Üçü de tek bir AJAX çağrısının (qrms_analitik_urunler)
 * sonucundan üretilir — üç ayrı istek atılmaz.
 *
 * Aralık ve masa filtresi ADRESTEDİR (paylaşılan filtre çubuğu); bu dosya
 * onları değiştirmez, uca olduğu gibi geri gönderir. Sayfa değiştirmek
 * (en az tıklananlar listesi) yalnızca bu ekrana ait bir durumdur, adrese
 * yazılmaz.
 *
 * Biçimlendirme, AJAX ve tablo iskeleti ORTAK dosyadadır (analitik-ortak.js).
 */
( function () {
	'use strict';

	var CFG   = window.qrmsAnalitikUrunler || {};
	var T     = CFG.i18n || {};
	var ORTAK = window.qrmsAnOrtak;

	if ( ! ORTAK ) {
		return;
	}

	var state = {
		sayfa: 1,
		yukleniyor: false
	};

	var el = {};

	function $( id ) {
		return document.getElementById( id );
	}

	function metin( anahtar, yedek ) {
		return T[ anahtar ] || yedek;
	}

	/* -----------------------------------------------------------------
	   EN ÇOK TIKLANANLAR
	----------------------------------------------------------------- */

	function urunleriBas( urunler ) {
		if ( ! urunler || ! urunler.length ) {
			el.products.innerHTML = ORTAK.bosDurum(
				'dashicons-chart-bar',
				CFG.masa
					? metin( 'noProductsTable', 'Bu masada henüz ürün tıklaması yok.' )
					: metin( 'noProducts', 'Seçili dönemde henüz ürün tıklaması yok.' )
			);
			return;
		}

		var basliklar = [
			'#',
			metin( 'product', 'Ürün' ),
			metin( 'category', 'Kategori' ),
			metin( 'totalClicks', 'Toplam Tıklama' ),
			metin( 'uniqueClicks', 'Tekil Tıklama' ),
			CFG.masa ? metin( 'lastClick', 'Son Tıklama' ) : metin( 'tableCount', 'Masa Sayısı' ),
			metin( 'popularity', 'Popülerlik' )
		];

		var enBuyuk = parseInt( urunler[ 0 ].toplam, 10 ) || 1;
		var govde   = '';

		urunler.forEach( function ( u, i ) {
			var sira    = i + 1;
			var sinif   = sira <= 3 ? 'qrms-an-rank-' + sira : 'qrms-an-rank-n';
			var pay     = Math.max( Math.round( ( parseInt( u.toplam, 10 ) / enBuyuk ) * 100 ), 1 );
			var altinci = CFG.masa
				? '<span class="qrms-an-muted">' + ORTAK.esc( ORTAK.tarih( u.son ) ) + '</span>'
				: '<span class="qrms-an-val-bold">' + ORTAK.sayi( u.masa_sayisi ) + '</span>';

			govde += '<tr>' +
				ORTAK.hucre( basliklar[ 0 ], '<span class="qrms-an-rank ' + sinif + '">' + sira + '</span>' ) +
				ORTAK.hucre( basliklar[ 1 ], '<strong>' + ORTAK.esc( u.item_name || '—' ) + '</strong>' ) +
				ORTAK.hucre( basliklar[ 2 ], '<span class="qrms-an-cat">' + ORTAK.esc( u.category_name || '—' ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 3 ], '<span class="qrms-an-val-gold">' + ORTAK.sayi( u.toplam ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 4 ], '<span class="qrms-an-val-bold">' + ORTAK.sayi( u.tekil ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 5 ], altinci ) +
				ORTAK.hucre(
					basliklar[ 6 ],
					'<span class="qrms-an-progress"><span class="qrms-an-progress-bg">' +
					'<span class="qrms-an-progress-fill" style="width:' + pay + '%"></span></span>' +
					'<span class="qrms-an-progress-pct">%' + pay + '</span></span>'
				) +
				'</tr>';
		} );

		el.products.innerHTML = ORTAK.tabloIskelet( basliklar, govde, '' );
	}

	/* -----------------------------------------------------------------
	   EN AZ TIKLANANLAR
	----------------------------------------------------------------- */

	function enAzBas( satirlar, ozet ) {
		if ( ! satirlar || ! satirlar.length ) {
			el.least.innerHTML = ORTAK.bosDurum( 'dashicons-hidden', metin( 'noItems', 'Yayında ürün bulunamadı.' ) );
			return;
		}

		var basliklar = [
			metin( 'product', 'Ürün' ),
			metin( 'category', 'Kategori' ),
			metin( 'status', 'Durum' ),
			metin( 'totalClicks', 'Toplam Tıklama' ),
			metin( 'uniqueClicks', 'Tekil Tıklama' ),
			metin( 'lastClick', 'Son Tıklama' )
		];

		var govde = '';

		satirlar.forEach( function ( u ) {
			// "Hiç tıklanmadı" ile "az tıklandı" farklı şeylerdir; sıfır satırı
			// ayrıca vurgulanır ki listede kaybolmasın.
			var sifir = 0 === parseInt( u.toplam, 10 );

			var durum = u.tukendi
				? '<span class="qrms-an-badge qrms-an-badge-warn">' + ORTAK.esc( metin( 'soldOut', 'Tükendi' ) ) + '</span>'
				: '<span class="qrms-an-muted">' + ORTAK.esc( metin( 'inStock', 'Stokta' ) ) + '</span>';

			govde += '<tr' + ( sifir ? ' class="qrms-an-zero"' : '' ) + '>' +
				ORTAK.hucre( basliklar[ 0 ], '<strong>' + ORTAK.esc( u.ad || '—' ) + '</strong>' ) +
				ORTAK.hucre( basliklar[ 1 ], '<span class="qrms-an-cat">' + ORTAK.esc( u.kategori || '—' ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 2 ], durum ) +
				ORTAK.hucre( basliklar[ 3 ], '<span class="' + ( sifir ? 'qrms-an-val-zero' : 'qrms-an-val-gold' ) + '">' + ORTAK.sayi( u.toplam ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 4 ], '<span class="qrms-an-val-bold">' + ORTAK.sayi( u.tekil ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 5 ], '<span class="qrms-an-muted">' + ORTAK.esc( u.son ? ORTAK.tarih( u.son ) : '—' ) + '</span>' ) +
				'</tr>';
		} );

		el.least.innerHTML = ozetSatiri( ozet ) +
			ORTAK.tabloIskelet( basliklar, govde, '' ) +
			sayfalama( ozet );
	}

	function ozetSatiri( ozet ) {
		var parcalar = [
			metin( 'totalItems', 'Yayındaki ürün' ) + ': ' + ORTAK.sayi( ozet.toplam ),
			metin( 'neverClicked', 'Hiç tıklanmamış' ) + ': ' + ORTAK.sayi( ozet.hic ),
			metin( 'soldOutCount', 'Tükendi işaretli' ) + ': ' + ORTAK.sayi( ozet.tukendi )
		];

		var html = '<p class="qrms-an-panel-note">' + ORTAK.esc( parcalar.join( ' · ' ) ) + '</p>';

		// Tavana dayanıldıysa sessiz kalmak yanıltıcı olurdu: kullanıcı
		// listeyi eksiksiz sanırdı.
		if ( ozet.dolu ) {
			html += '<p class="qrms-an-panel-note qrms-an-warn">' +
				ORTAK.esc( metin( 'capped', 'Ürün sayısı çok yüksek; liste ilk kayıtlarla sınırlandı.' ) ) +
				'</p>';
		}

		return html;
	}

	function sayfalama( ozet ) {
		if ( ozet.sayfalar <= 1 ) {
			return '';
		}

		return '<div class="qrms-an-pager">' +
			'<button type="button" class="qrms-an-btn qrms-an-btn-small qrms-an-pager-prev"' +
			( ozet.sayfa <= 1 ? ' disabled' : '' ) + '>' + ORTAK.esc( metin( 'prev', 'Önceki' ) ) + '</button>' +
			'<span class="qrms-an-pager-info">' +
			ORTAK.esc( ozet.sayfa + ' / ' + ozet.sayfalar ) +
			'</span>' +
			'<button type="button" class="qrms-an-btn qrms-an-btn-small qrms-an-pager-next"' +
			( ozet.sayfa >= ozet.sayfalar ? ' disabled' : '' ) + '>' + ORTAK.esc( metin( 'next', 'Sonraki' ) ) + '</button>' +
			'</div>';
	}

	/* -----------------------------------------------------------------
	   KATEGORİ DAĞILIMI
	----------------------------------------------------------------- */

	function kategorileriBas( satirlar, kategorisiz ) {
		if ( ! satirlar || ! satirlar.length ) {
			el.cats.innerHTML = ORTAK.bosDurum( 'dashicons-category', metin( 'noCats', 'Seçili dönemde kategori verisi yok.' ) );
			return;
		}

		var basliklar = [
			metin( 'category', 'Kategori' ),
			metin( 'totalClicks', 'Toplam Tıklama' ),
			metin( 'uniqueClicks', 'Tekil Tıklama' ),
			metin( 'itemCount', 'Ürün Sayısı' ),
			metin( 'share', 'Pay' )
		];

		var enBuyuk = 1;
		var toplam  = 0;

		satirlar.forEach( function ( k ) {
			enBuyuk = Math.max( enBuyuk, parseInt( k.toplam, 10 ) || 0 );
			toplam += parseInt( k.toplam, 10 ) || 0;
		} );

		var govde = '';

		satirlar.forEach( function ( k ) {
			var pay = Math.max( Math.round( ( parseInt( k.toplam, 10 ) / enBuyuk ) * 100 ), 1 );

			// Taksonomide karşılığı kalmayan ad: kategori yeniden
			// adlandırılmış olabilir. Sayı düzeltilmez, satır etiketlenir.
			var ad = '<strong>' + ORTAK.esc( k.kategori ) + '</strong>';

			if ( k.eski_ad ) {
				ad += ' <span class="qrms-an-badge" title="' +
					ORTAK.esc( metin( 'oldNameHint', 'Bu adla bir kategori artık yok; kayıtlar tıklama anındaki adı taşır.' ) ) +
					'">' + ORTAK.esc( metin( 'oldName', 'eski ad' ) ) + '</span>';
			}

			govde += '<tr>' +
				ORTAK.hucre( basliklar[ 0 ], ad ) +
				ORTAK.hucre( basliklar[ 1 ], '<span class="qrms-an-val-gold">' + ORTAK.sayi( k.toplam ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 2 ], '<span class="qrms-an-val-bold">' + ORTAK.sayi( k.tekil ) + '</span>' ) +
				ORTAK.hucre( basliklar[ 3 ], '<span class="qrms-an-val-bold">' + ORTAK.sayi( k.urun_sayisi ) + '</span>' ) +
				ORTAK.hucre(
					basliklar[ 4 ],
					'<span class="qrms-an-progress"><span class="qrms-an-progress-bg">' +
					'<span class="qrms-an-progress-fill" style="width:' + pay + '%"></span></span>' +
					'<span class="qrms-an-progress-pct">%' + ( toplam > 0 ? Math.round( ( k.toplam / toplam ) * 100 ) : 0 ) + '</span></span>'
				) +
				'</tr>';
		} );

		var html = ORTAK.tabloIskelet( basliklar, govde, '' );

		// Kategorisiz tıklamalar listeye KARIŞMAZ ama saklanmaz da: toplamla
		// listenin neden tutmadığı burada yazar.
		if ( kategorisiz > 0 ) {
			html += '<p class="qrms-an-panel-note">' +
				ORTAK.esc(
					metin( 'uncategorized', 'Kategorisi kaydedilmemiş tıklama' ) + ': ' + ORTAK.sayi( kategorisiz )
				) + '</p>';
		}

		el.cats.innerHTML = html;
	}

	/* -----------------------------------------------------------------
	   VERİ
	----------------------------------------------------------------- */

	function yukle() {
		if ( state.yukleniyor ) {
			return;
		}

		state.yukleniyor = true;

		el.products.innerHTML = '<div class="qrms-an-loading">' + ORTAK.esc( metin( 'loading', 'Yükleniyor' ) ) + '</div>';
		el.least.innerHTML    = '<div class="qrms-an-loading">' + ORTAK.esc( metin( 'loading', 'Yükleniyor' ) ) + '</div>';
		el.cats.innerHTML     = '<div class="qrms-an-loading">' + ORTAK.esc( metin( 'loading', 'Yükleniyor' ) ) + '</div>';

		ORTAK.post(
			CFG.ajaxUrl,
			{
				action: 'qrms_analitik_urunler',
				security: CFG.nonce,
				donem: CFG.donem,
				masa: CFG.masa,
				bas: CFG.bas,
				bit: CFG.bit,
				usayfa: state.sayfa
			},
			function ( veri ) {
				state.yukleniyor = false;

				urunleriBas( veri.encok );
				enAzBas( veri.enaz, veri.enazOzet || {} );
				kategorileriBas( veri.kategoriler, veri.kategorisiz );
			},
			function () {
				state.yukleniyor = false;

				var hata = ORTAK.bosDurum( 'dashicons-warning', metin( 'loadError', 'Veri yüklenemedi. Sayfayı yenileyin.' ) );

				el.products.innerHTML = hata;
				el.least.innerHTML    = '';
				el.cats.innerHTML     = '';
			}
		);
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		el.wrap = document.querySelector( '.qrms-an-urunler' );

		if ( ! el.wrap || ! CFG.ajaxUrl ) {
			return;
		}

		el.products = $( 'qrms-an-products' );
		el.least    = $( 'qrms-an-least' );
		el.cats     = $( 'qrms-an-cats-dist' );

		el.wrap.addEventListener( 'click', function ( olay ) {
			var onceki = olay.target.closest( '.qrms-an-pager-prev' );
			var sonraki = olay.target.closest( '.qrms-an-pager-next' );

			if ( ! onceki && ! sonraki ) {
				return;
			}

			state.sayfa = Math.max( 1, state.sayfa + ( sonraki ? 1 : -1 ) );
			yukle();
		} );

		// Filtre çubuğu (aralık + masa) ortak bileşendir; değişimi adresi
		// yeniler, bu ekranda durum tutmaz.
		ORTAK.filtreKur( el.wrap );

		yukle();
	} );
}() );
