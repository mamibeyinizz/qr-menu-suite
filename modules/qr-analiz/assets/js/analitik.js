/**
 * QR Menü — Analitik paneli.
 *
 * Sayfa iskeleti PHP'den gelir; kartlar, grafik ve tablolar burada tek bir
 * AJAX çağrısının sonucundan üretilir. jQuery kullanılmaz.
 *
 * Tablolar dar ekranda karta dönüşür (CSS), bunun için her hücreye
 * data-label yazılır: kart görünümünde sütun başlığı hücrenin solunda
 * görünen etikettir.
 */
( function () {
	'use strict';

	var CFG = window.qrmsAnalitik || {};
	var T   = CFG.i18n || {};

	var state = {
		donem: 'hourly',
		masa: '',
		masalar: [],
		yukleniyor: false
	};

	var el = {};

	/* -----------------------------------------------------------------
	   YARDIMCILAR
	----------------------------------------------------------------- */

	function $( id ) {
		return document.getElementById( id );
	}

	function metin( anahtar, yedek ) {
		return T[ anahtar ] || yedek;
	}

	function esc( deger ) {
		return String( deger === null || deger === undefined ? '' : deger )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}

	function sayi( deger ) {
		var n = parseInt( deger, 10 );

		if ( isNaN( n ) ) {
			n = 0;
		}

		return n.toLocaleString( 'tr-TR' );
	}

	/** Kartlarda kısaltılmış gösterim (12.4K gibi). */
	function kisa( deger ) {
		var n = parseInt( deger, 10 ) || 0;

		if ( n >= 1000000 ) {
			return ( n / 1000000 ).toFixed( 1 ) + 'M';
		}

		if ( n >= 1000 ) {
			return ( n / 1000 ).toFixed( 1 ) + 'K';
		}

		return n.toLocaleString( 'tr-TR' );
	}

	function oran( pay, payda ) {
		return payda > 0 ? Math.round( ( pay / payda ) * 100 ) : 0;
	}

	function oranSinifi( deger ) {
		if ( deger >= 50 ) {
			return 'qrms-an-pill-high';
		}

		return deger >= 15 ? 'qrms-an-pill-mid' : 'qrms-an-pill-low';
	}

	function tarih( ham ) {
		if ( ! ham ) {
			return '—';
		}

		var d = new Date( String( ham ).replace( ' ', 'T' ) );

		if ( isNaN( d.getTime() ) ) {
			return esc( ham );
		}

		return d.toLocaleDateString( 'tr-TR', {
			day: '2-digit',
			month: '2-digit',
			year: 'numeric',
			hour: '2-digit',
			minute: '2-digit'
		} );
	}

	function post( veri, tamam, hata ) {
		var xhr  = new XMLHttpRequest();
		var govd = [];
		var k;

		for ( k in veri ) {
			if ( Object.prototype.hasOwnProperty.call( veri, k ) ) {
				govd.push( encodeURIComponent( k ) + '=' + encodeURIComponent( veri[ k ] ) );
			}
		}

		xhr.open( 'POST', CFG.ajaxUrl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8' );
		xhr.setRequestHeader( 'X-Requested-With', 'XMLHttpRequest' );

		xhr.onreadystatechange = function () {
			if ( 4 !== xhr.readyState ) {
				return;
			}

			var json = null;

			try {
				json = JSON.parse( xhr.responseText );
			} catch ( e ) {
				json = null;
			}

			if ( json && json.success ) {
				tamam( json.data );
			} else if ( hata ) {
				hata( json );
			}
		};

		xhr.send( govd.join( '&' ) );
	}

	function bosDurum( ikon, mesaj ) {
		return '<div class="qrms-an-empty"><div class="qrms-an-empty-icon" aria-hidden="true">' + ikon + '</div>' +
			'<p class="qrms-an-empty-text">' + esc( mesaj ) + '</p></div>';
	}

	/* -----------------------------------------------------------------
	   BAŞLIKLAR
	----------------------------------------------------------------- */

	function donemBasligi() {
		var basliklar = {
			hourly: metin( 'hourly', 'Saatlik — Bugün' ),
			daily: metin( 'daily', 'Günlük — Son 30 gün' ),
			weekly: metin( 'weekly', 'Haftalık — Son 12 hafta' ),
			monthly: metin( 'monthly', 'Aylık — Son 12 ay' ),
			masalar: metin( 'masalar', 'Masalara göre — Son 30 gün' )
		};

		var baslik = basliklar[ state.donem ] || '';

		if ( state.masa ) {
			baslik += ' · ' + masaEtiketi( state.masa );
		}

		return baslik;
	}

	function sutunBasligi() {
		var basliklar = {
			hourly: metin( 'colHourly', 'Saat' ),
			daily: metin( 'colDaily', 'Tarih' ),
			weekly: metin( 'colWeekly', 'Hafta' ),
			monthly: metin( 'colMonthly', 'Ay' ),
			masalar: metin( 'colMasa', 'Masa' )
		};

		return basliklar[ state.donem ] || metin( 'colPeriod', 'Dönem' );
	}

	function masaEtiketi( slug ) {
		var i;

		for ( i = 0; i < state.masalar.length; i++ ) {
			if ( state.masalar[ i ].slug === slug ) {
				return state.masalar[ i ].label;
			}
		}

		return slug;
	}

	/* -----------------------------------------------------------------
	   RENDER — KARTLAR
	----------------------------------------------------------------- */

	function kartlariBas( genel ) {
		var kartlar = [
			{
				ikon: '👀',
				etiket: metin( 'cardViews', 'Menü Görüntüleme' ),
				rozet: metin( 'today', 'BUGÜN' ),
				deger: kisa( genel.mv_bugun ),
				alt: metin( 'thisWeek', 'Bu hafta' ) + ': ' + kisa( genel.mv_hafta ) + ' · ' +
					metin( 'thisMonth', 'Bu ay' ) + ': ' + kisa( genel.mv_ay )
			},
			{
				ikon: '🖱️',
				etiket: metin( 'cardClicks', 'Ürün Tıklama' ),
				rozet: metin( 'today', 'BUGÜN' ),
				deger: kisa( genel.pc_bugun ),
				alt: metin( 'thisWeek', 'Bu hafta' ) + ': ' + kisa( genel.pc_hafta )
			},
			{
				ikon: '🌐',
				etiket: metin( 'cardUnique', 'Tekil Ziyaretçi' ),
				rozet: metin( 'today', 'BUGÜN' ),
				deger: kisa( genel.uv_bugun ),
				alt: metin( 'ipNote', 'IP bazlı, gizlilik korumalı' )
			},
			{
				ikon: '🍽️',
				etiket: metin( 'cardTables', 'Hareketli Masa' ),
				rozet: metin( 'today', 'BUGÜN' ),
				deger: kisa( genel.masa_gun ),
				alt: metin( 'cardTablesSub', 'Toplam tıklama' ) + ': ' + kisa( genel.pc_tumu )
			}
		];

		var html = '';

		kartlar.forEach( function ( kart ) {
			html += '<div class="qrms-an-card">' +
				'<span class="qrms-an-card-tag">' + esc( kart.rozet ) + '</span>' +
				'<span class="qrms-an-card-icon" aria-hidden="true">' + kart.ikon + '</span>' +
				'<div class="qrms-an-card-label">' + esc( kart.etiket ) + '</div>' +
				'<div class="qrms-an-card-value">' + esc( kart.deger ) + '</div>' +
				'<div class="qrms-an-card-sub">' + esc( kart.alt ) + '</div>' +
				'</div>';
		} );

		el.cards.innerHTML = html;
	}

	/* -----------------------------------------------------------------
	   RENDER — GRAFİK
	----------------------------------------------------------------- */

	function grafikBas( satirlar ) {
		if ( ! satirlar || ! satirlar.length ) {
			el.chart.innerHTML = bosDurum( '📭', metin( 'noData', 'Bu dönemde henüz veri yok.' ) );
			return;
		}

		var enBuyuk = 1;

		satirlar.forEach( function ( s ) {
			enBuyuk = Math.max( enBuyuk, s.mv, s.pc );
		} );

		var html = '<div class="qrms-an-chart-inner">';

		satirlar.forEach( function ( s ) {
			// Yükseklikler yüzde: mobilde grafik alanı kısaldığında çubuklar
			// da orantılı kısalır, sabit piksel değerleri taşma yapardı.
			var mv = s.mv > 0 ? Math.max( ( s.mv / enBuyuk ) * 100, 2 ) : 0;
			var pc = s.pc > 0 ? Math.max( ( s.pc / enBuyuk ) * 100, 2 ) : 0;

			html += '<div class="qrms-an-bargroup">' +
				'<div class="qrms-an-bars">' +
				'<div class="qrms-an-bar qrms-an-bar-gold" style="height:' + mv.toFixed( 2 ) + '%" ' +
				'title="' + esc( metin( 'cardViews', 'Menü Görüntüleme' ) + ': ' + s.mv ) + '"></div>' +
				'<div class="qrms-an-bar qrms-an-bar-blue" style="height:' + pc.toFixed( 2 ) + '%" ' +
				'title="' + esc( metin( 'cardClicks', 'Ürün Tıklama' ) + ': ' + s.pc ) + '"></div>' +
				'</div>' +
				'<div class="qrms-an-barlabel">' + esc( s.label ) + '</div>' +
				'</div>';
		} );

		html += '</div>';

		el.chart.innerHTML = html;
	}

	/* -----------------------------------------------------------------
	   RENDER — DÖNEM TABLOSU
	----------------------------------------------------------------- */

	function donemTablosuBas( satirlar ) {
		if ( ! satirlar || ! satirlar.length ) {
			el.table.innerHTML = '';
			return;
		}

		if ( 'masalar' === state.donem ) {
			masaTablosuBas( satirlar );
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

			var o = oran( s.pc, s.mv );

			govde += '<tr>' +
				hucre( basliklar[ 0 ], '<strong>' + esc( s.label ) + '</strong>' ) +
				hucre( basliklar[ 1 ], '<span class="qrms-an-val-gold">' + sayi( s.mv ) + '</span>' ) +
				hucre( basliklar[ 2 ], '<span class="qrms-an-val-blue">' + sayi( s.pc ) + '</span>' ) +
				hucre( basliklar[ 3 ], '<span class="qrms-an-val-bold">' + sayi( s.uv ) + '</span>' ) +
				hucre( basliklar[ 4 ], '<span class="qrms-an-pill ' + oranSinifi( o ) + '">%' + o + '</span>' ) +
				'</tr>';
		} );

		var toplamOran = oran( toplamPc, toplamMv );
		var altToplam = '<tr class="qrms-an-total">' +
			hucre( basliklar[ 0 ], '<strong>' + esc( metin( 'total', 'TOPLAM' ) ) + '</strong>' ) +
			hucre( basliklar[ 1 ], '<span class="qrms-an-val-gold">' + sayi( toplamMv ) + '</span>' ) +
			hucre( basliklar[ 2 ], '<span class="qrms-an-val-blue">' + sayi( toplamPc ) + '</span>' ) +
			hucre( basliklar[ 3 ], '<span class="qrms-an-val-bold">' + sayi( toplamUv ) + '</span>' ) +
			hucre( basliklar[ 4 ], '<span class="qrms-an-pill ' + oranSinifi( toplamOran ) + '">%' + toplamOran + '</span>' ) +
			'</tr>';

		// Zaman serisi tablosu (24 saat, 30 gün…) dar ekranda kart görünümünde
		// çok uzar; aynı veri hemen üstteki grafikte zaten var. Bu yüzden
		// açılır bir bölüme alınır — geniş ekranda açık gelir.
		var baslik = metin( 'periodTable', 'Dönem tablosu' ) + ' · ' + satirlar.length + ' ' + metin( 'rows', 'satır' );

		el.table.innerHTML = acilirBolum( baslik, tabloIskelet( basliklar, govde, altToplam ) );
	}

	/* -----------------------------------------------------------------
	   RENDER — MASA TABLOSU
	----------------------------------------------------------------- */

	function masaTablosuBas( satirlar ) {
		var basliklar = [
			metin( 'colMasa', 'Masa' ),
			metin( 'cardViews', 'Menü Görüntüleme' ),
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

			var dugme = s.masa
				? '<button type="button" class="qrms-an-btn qrms-an-btn-small qrms-an-masa-sec" data-masa="' + esc( s.masa ) + '">' +
					esc( metin( 'filterTable', 'Bu masayı incele' ) ) + '</button>'
				: '<span class="qrms-an-muted">—</span>';

			govde += '<tr>' +
				hucre( basliklar[ 0 ], '<strong>' + esc( s.label ) + '</strong>' ) +
				hucre( basliklar[ 1 ], '<span class="qrms-an-val-gold">' + sayi( s.mv ) + '</span>' ) +
				hucre( basliklar[ 2 ], '<span class="qrms-an-val-blue">' + sayi( s.pc ) + '</span>' ) +
				hucre( basliklar[ 3 ], '<span class="qrms-an-val-bold">' + sayi( s.uv ) + '</span>' ) +
				hucre( basliklar[ 4 ], '<span class="qrms-an-muted">' + esc( tarih( s.son ) ) + '</span>' ) +
				hucre( basliklar[ 5 ], dugme ) +
				'</tr>';
		} );

		var altToplam = '<tr class="qrms-an-total">' +
			hucre( basliklar[ 0 ], '<strong>' + esc( metin( 'total', 'TOPLAM' ) ) + '</strong>' ) +
			hucre( basliklar[ 1 ], '<span class="qrms-an-val-gold">' + sayi( toplamMv ) + '</span>' ) +
			hucre( basliklar[ 2 ], '<span class="qrms-an-val-blue">' + sayi( toplamPc ) + '</span>' ) +
			hucre( basliklar[ 3 ], '<span class="qrms-an-val-bold">' + sayi( toplamUv ) + '</span>' ) +
			hucre( basliklar[ 4 ], '' ) +
			hucre( basliklar[ 5 ], '' ) +
			'</tr>';

		el.table.innerHTML = tabloIskelet( basliklar, govde, altToplam );
	}

	/* -----------------------------------------------------------------
	   RENDER — EN ÇOK TIKLANAN ÜRÜNLER
	----------------------------------------------------------------- */

	function urunleriBas( urunler ) {
		if ( ! urunler || ! urunler.length ) {
			el.products.innerHTML = bosDurum(
				'📭',
				state.masa
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
			state.masa ? metin( 'lastClick', 'Son Tıklama' ) : metin( 'tableCount', 'Masa Sayısı' ),
			metin( 'popularity', 'Popülerlik' )
		];

		var enBuyuk = parseInt( urunler[ 0 ].toplam, 10 ) || 1;
		var govde   = '';

		urunler.forEach( function ( u, i ) {
			var sira    = i + 1;
			var sinif   = sira <= 3 ? 'qrms-an-rank-' + sira : 'qrms-an-rank-n';
			var pay     = Math.max( Math.round( ( parseInt( u.toplam, 10 ) / enBuyuk ) * 100 ), 1 );
			var altinci = state.masa
				? '<span class="qrms-an-muted">' + esc( tarih( u.son ) ) + '</span>'
				: '<span class="qrms-an-val-bold">' + sayi( u.masa_sayisi ) + '</span>';

			govde += '<tr>' +
				hucre( basliklar[ 0 ], '<span class="qrms-an-rank ' + sinif + '">' + sira + '</span>' ) +
				hucre( basliklar[ 1 ], '<strong>' + esc( u.item_name || '—' ) + '</strong>' ) +
				hucre( basliklar[ 2 ], '<span class="qrms-an-cat">' + esc( u.category_name || '—' ) + '</span>' ) +
				hucre( basliklar[ 3 ], '<span class="qrms-an-val-gold">' + sayi( u.toplam ) + '</span>' ) +
				hucre( basliklar[ 4 ], '<span class="qrms-an-val-bold">' + sayi( u.tekil ) + '</span>' ) +
				hucre( basliklar[ 5 ], altinci ) +
				hucre(
					basliklar[ 6 ],
					'<span class="qrms-an-progress"><span class="qrms-an-progress-bg">' +
					'<span class="qrms-an-progress-fill" style="width:' + pay + '%"></span></span>' +
					'<span class="qrms-an-progress-pct">%' + pay + '</span></span>'
				) +
				'</tr>';
		} );

		el.products.innerHTML = tabloIskelet( basliklar, govde, '' );
	}

	/* -----------------------------------------------------------------
	   TABLO İSKELETİ
	----------------------------------------------------------------- */

	function hucre( etiket, icerik ) {
		return '<td data-label="' + esc( etiket ) + '">' + icerik + '</td>';
	}

	/**
	 * İçeriği açılır bir bölüme sarar. Geniş ekranda açık, dar ekranda kapalı
	 * başlar; kullanıcı iki durumda da açıp kapatabilir.
	 */
	function acilirBolum( baslik, icerik ) {
		var acik = window.innerWidth > 660 ? ' open' : '';

		return '<details class="qrms-an-details"' + acik + '>' +
			'<summary class="qrms-an-summary">' + esc( baslik ) + '</summary>' +
			icerik +
			'</details>';
	}

	function tabloIskelet( basliklar, govde, altToplam ) {
		var bas = '';

		basliklar.forEach( function ( b ) {
			bas += '<th scope="col">' + esc( b ) + '</th>';
		} );

		return '<div class="qrms-an-scroll"><table class="qrms-an-table">' +
			'<thead><tr>' + bas + '</tr></thead>' +
			'<tbody>' + govde + '</tbody>' +
			( altToplam ? '<tfoot>' + altToplam + '</tfoot>' : '' ) +
			'</table></div>';
	}

	/* -----------------------------------------------------------------
	   MASA FİLTRESİ
	----------------------------------------------------------------- */

	function masaSecenekleriBas( masalar ) {
		state.masalar = masalar || [];

		var html = '<option value="">' + esc( metin( 'allTables', 'Tüm masalar' ) ) + '</option>';

		state.masalar.forEach( function ( m ) {
			html += '<option value="' + esc( m.slug ) + '"' + ( m.slug === state.masa ? ' selected' : '' ) + '>' +
				esc( m.label ) + '</option>';
		} );

		el.masa.innerHTML = html;
		el.masa.value     = state.masa;

		el.masaTemizle.hidden = ! state.masa;
		el.wrap.classList.toggle( 'qrms-an-filtered', !! state.masa );
	}

	function csvAdresiGuncelle() {
		if ( ! el.csv ) {
			return;
		}

		el.csv.href = CFG.ajaxUrl +
			'?action=qrms_analitik_csv' +
			'&period=' + encodeURIComponent( state.donem ) +
			'&masa=' + encodeURIComponent( state.masa ) +
			'&security=' + encodeURIComponent( CFG.csvNonce );
	}

	/* -----------------------------------------------------------------
	   VERİ YÜKLE
	----------------------------------------------------------------- */

	function yukle() {
		state.yukleniyor = true;

		el.cards.innerHTML =
			'<div class="qrms-an-card qrms-an-skeleton"></div>' +
			'<div class="qrms-an-card qrms-an-skeleton"></div>' +
			'<div class="qrms-an-card qrms-an-skeleton"></div>' +
			'<div class="qrms-an-card qrms-an-skeleton"></div>';
		el.chart.innerHTML    = '<div class="qrms-an-loading">' + esc( metin( 'loadingChart', 'Grafik yükleniyor' ) ) + '</div>';
		el.table.innerHTML    = '';
		el.products.innerHTML = '<div class="qrms-an-loading">' + esc( metin( 'loading', 'Yükleniyor' ) ) + '</div>';

		el.chartTitle.textContent = donemBasligi();
		csvAdresiGuncelle();

		post(
			{
				action: 'qrms_analitik_veri',
				security: CFG.nonce,
				period: state.donem,
				masa: state.masa
			},
			function ( veri ) {
				state.yukleniyor = false;
				state.masa       = veri.masa || '';

				masaSecenekleriBas( veri.masalar );
				kartlariBas( veri.genel );
				grafikBas( veri.grafik );
				donemTablosuBas( veri.grafik );
				urunleriBas( veri.urunler );

				el.chartTitle.textContent = donemBasligi();
				csvAdresiGuncelle();
			},
			function () {
				state.yukleniyor = false;
				el.chart.innerHTML    = bosDurum( '🔌', metin( 'loadError', 'Veri yüklenemedi. Sayfayı yenileyin.' ) );
				el.products.innerHTML = '';
				el.cards.innerHTML    = '';
			}
		);
	}

	/* -----------------------------------------------------------------
	   OLAYLAR
	----------------------------------------------------------------- */

	function sekmeSec( dugme ) {
		var sekmeler = el.wrap.querySelectorAll( '.qrms-an-tab' );

		Array.prototype.forEach.call( sekmeler, function ( s ) {
			var aktif = s === dugme;

			s.classList.toggle( 'is-active', aktif );
			s.setAttribute( 'aria-selected', aktif ? 'true' : 'false' );
		} );

		// Sekme çubuğu dar ekranda yatay kayar: seçilen sekme görünür kalsın.
		if ( dugme.scrollIntoView ) {
			try {
				dugme.scrollIntoView( { block: 'nearest', inline: 'nearest' } );
			} catch ( e ) {
				// Eski tarayıcı: seçenek nesnesini desteklemiyor, önemli değil.
			}
		}

		state.donem = dugme.getAttribute( 'data-period' );
		yukle();
	}

	function modalAc() {
		el.confirmText.textContent = state.masa
			? metin( 'confirmTable', 'Yalnızca bu masanın kayıtları silinecek:' ) + ' ' + masaEtiketi( state.masa )
			: metin( 'confirmAll', 'Tüm görüntüleme ve tıklama kayıtları kalıcı olarak silinecek. Bu işlem geri alınamaz.' );

		el.confirm.hidden = false;
		el.confirmOk.focus();
	}

	function modalKapat() {
		el.confirm.hidden = true;
	}

	function baglantilariKur() {
		el.wrap.addEventListener( 'click', function ( olay ) {
			var sekme = olay.target.closest( '.qrms-an-tab' );

			if ( sekme ) {
				sekmeSec( sekme );
				return;
			}

			var masaDugme = olay.target.closest( '.qrms-an-masa-sec' );

			if ( masaDugme ) {
				state.masa = masaDugme.getAttribute( 'data-masa' ) || '';
				yukle();
			}
		} );

		el.masa.addEventListener( 'change', function () {
			state.masa = el.masa.value || '';
			yukle();
		} );

		el.masaTemizle.addEventListener( 'click', function () {
			state.masa = '';
			yukle();
		} );

		el.refresh.addEventListener( 'click', function () {
			yukle();
		} );

		el.clear.addEventListener( 'click', modalAc );
		el.confirmCancel.addEventListener( 'click', modalKapat );

		el.confirm.addEventListener( 'click', function ( olay ) {
			if ( olay.target === el.confirm ) {
				modalKapat();
			}
		} );

		document.addEventListener( 'keydown', function ( olay ) {
			if ( 'Escape' === olay.key && ! el.confirm.hidden ) {
				modalKapat();
			}
		} );

		el.confirmOk.addEventListener( 'click', function () {
			var eskiMetin = el.confirmOk.textContent;

			el.confirmOk.textContent = metin( 'deleting', 'Siliniyor…' );
			el.confirmOk.disabled    = true;

			post(
				{
					action: 'qrms_analitik_temizle',
					security: CFG.nonce,
					masa: state.masa
				},
				function () {
					el.confirmOk.textContent = eskiMetin;
					el.confirmOk.disabled    = false;
					modalKapat();
					yukle();
				},
				function () {
					el.confirmOk.textContent = eskiMetin;
					el.confirmOk.disabled    = false;
					modalKapat();
				}
			);
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		el.wrap = document.querySelector( '.qrms-an' );

		if ( ! el.wrap || ! CFG.ajaxUrl ) {
			return;
		}

		el.cards         = $( 'qrms-an-cards' );
		el.chart         = $( 'qrms-an-chart' );
		el.chartTitle    = $( 'qrms-an-chart-title' );
		el.table         = $( 'qrms-an-table' );
		el.products      = $( 'qrms-an-products' );
		el.masa          = $( 'qrms-an-masa' );
		el.masaTemizle   = $( 'qrms-an-masa-temizle' );
		el.refresh       = $( 'qrms-an-refresh' );
		el.clear         = $( 'qrms-an-clear' );
		el.csv           = $( 'qrms-an-csv' );
		el.confirm       = $( 'qrms-an-confirm' );
		el.confirmText   = $( 'qrms-an-confirm-text' );
		el.confirmOk     = $( 'qrms-an-confirm-ok' );
		el.confirmCancel = $( 'qrms-an-confirm-cancel' );

		baglantilariKur();
		yukle();
	} );
}() );
