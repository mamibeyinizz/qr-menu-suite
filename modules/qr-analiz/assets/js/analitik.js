/**
 * QR Menü — Analitik paneli (klasik/tüm veriler görünümü).
 *
 * Sayfa iskeleti PHP'den gelir; masa kesiti ve ürün listesi burada tek bir
 * AJAX çağrısının sonucundan üretilir.
 *
 * KAPSAM DARALDI. Özet kartları ve zaman grafiği "Genel Bakış" kategorisine
 * taşındı (analitik-genel.js); burada masa kesiti ve ürün kesiti kaldı, ikisi
 * de kendi kategorilerine taşınana kadar. Biçimlendirme, AJAX sarmalayıcısı,
 * tablo iskeleti ve grafik çizimi ORTAK dosyadadır (analitik-ortak.js) —
 * kopyalanmaz.
 *
 * MASA FİLTRESİ ARTIK ADRESTEDİR. Sayfanın kendi masa seçici kutusu yerine
 * bütün kategorilerle ORTAK filtre çubuğu kullanılır; seçim query arg olarak
 * taşındığı için "Bu masayı incele" düğmesi de sayfayı o adresle yeniler.
 *
 * Tablolar dar ekranda karta dönüşür (CSS), bunun için her hücreye
 * data-label yazılır: kart görünümünde sütun başlığı hücrenin solunda
 * görünen etikettir.
 */
( function () {
	'use strict';

	var CFG   = window.qrmsAnalitik || {};
	var T     = CFG.i18n || {};
	var ORTAK = window.qrmsAnOrtak;

	if ( ! ORTAK ) {
		return;
	}

	var state = {
		// Seçili chip: 'masalar' ya da 'urunler'.
		kategori: 'masalar',
		// Sunucuya gönderilen dönem — kategoriden türetilir.
		donem: 'masalar',
		// Ürün kesitinin penceresi (kendi seçicisi vardır; ilk seçenek "Bugün").
		urunDonem: 'hourly',
		// Masa filtresi paylaşılan filtre çubuğundan, yani ADRESTEN gelir.
		masa: CFG.masa || '',
		masalar: [],
		// donem|masa -> sunucu yanıtı.
		onbellek: {},
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

	function metin( anahtar, yedek ) {
		return T[ anahtar ] || yedek;
	}

	// Ortak yardımcıların kısa adları — gövde okunur kalsın diye.
	var esc          = ORTAK.esc;
	var sayi         = ORTAK.sayi;
	var tarih        = ORTAK.tarih;
	var bosDurum     = ORTAK.bosDurum;
	var hucre        = ORTAK.hucre;
	var tabloIskelet = ORTAK.tabloIskelet;

	function post( veri, tamam, hata ) {
		ORTAK.post( CFG.ajaxUrl, veri, tamam, hata );
	}

	/* -----------------------------------------------------------------
	   BAŞLIKLAR
	----------------------------------------------------------------- */

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
				'dashicons-chart-bar',
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
	   MASA FİLTRESİ
	----------------------------------------------------------------- */

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
	   KATEGORİLER
	----------------------------------------------------------------- */

	/** Kategorinin sunucuya gönderilecek dönemi. */
	function kategoriDonemi( kategori ) {
		return 'urunler' === kategori ? state.urunDonem : kategori;
	}

	/** Seçili kategoriyi ekrana uygular — chip'ler ve görünen bölüm. */
	function kategoriUygula() {
		var chipler = el.wrap.querySelectorAll( '.qrms-an-tab' );
		var urunMu  = 'urunler' === state.kategori;

		Array.prototype.forEach.call( chipler, function ( chip ) {
			var aktif = chip.getAttribute( 'data-cat' ) === state.kategori;

			chip.classList.toggle( 'is-active', aktif );
			chip.setAttribute( 'aria-selected', aktif ? 'true' : 'false' );

			// Chip şeridi dar ekranda yatay kayar: seçilen chip görünür kalsın.
			if ( aktif && chip.scrollIntoView ) {
				try {
					chip.scrollIntoView( { block: 'nearest', inline: 'nearest' } );
				} catch ( e ) {
					// Eski tarayıcı: seçenek nesnesini desteklemiyor, önemli değil.
				}
			}
		} );

		el.panelVeri.hidden    = urunMu;
		el.panelUrunler.hidden = ! urunMu;

		if ( el.urunDonem ) {
			el.urunDonem.value = state.urunDonem;
		}
	}

	/* -----------------------------------------------------------------
	   VERİ YÜKLE
	----------------------------------------------------------------- */

	function onbellekAnahtari( donem, masa ) {
		return donem + '|' + masa;
	}

	/** Sunucu yanıtının tamamını ekrana basar. */
	function veriyiBas( veri ) {
		state.masa = veri.masa || '';

		state.masalar = veri.masalar || [];

		masaTablosuBas( veri.grafik );
		urunleriBas( veri.urunler );

		csvAdresiGuncelle();
	}

	/**
	 * Seçili kategorinin verisini gösterir.
	 *
	 * @param {boolean} zorla true ise önbellek atlanır (Yenile düğmesi).
	 */
	function yukle( zorla ) {
		state.donem = kategoriDonemi( state.kategori );

		var anahtar = onbellekAnahtari( state.donem, state.masa );

		// Aynı pencere zaten yüklüyse kategori değişimi istek doğurmaz.
		if ( ! zorla && state.onbellek[ anahtar ] ) {
			veriyiBas( state.onbellek[ anahtar ] );
			return;
		}

		state.yukleniyor = true;

		el.table.innerHTML    = '<div class="qrms-an-loading">' + esc( metin( 'loading', 'Yükleniyor' ) ) + '</div>';
		el.products.innerHTML = '<div class="qrms-an-loading">' + esc( metin( 'loading', 'Yükleniyor' ) ) + '</div>';

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

				// İki anahtarla da saklanır: istenen masa ile sunucunun
				// doğruladığı masa farklı olabilir (kayıtlı olmayan masa boşa
				// düşer) ve o zaman aynı yanıt ikinci kez istenirdi.
				state.onbellek[ anahtar ] = veri;
				state.onbellek[ onbellekAnahtari( state.donem, veri.masa || '' ) ] = veri;

				veriyiBas( veri );
			},
			function () {
				state.yukleniyor = false;
				el.table.innerHTML    = bosDurum( 'dashicons-warning', metin( 'loadError', 'Veri yüklenemedi. Sayfayı yenileyin.' ) );
				el.products.innerHTML = '';
			}
		);
	}

	/* -----------------------------------------------------------------
	   OLAYLAR
	----------------------------------------------------------------- */

	function kategoriSec( chip ) {
		var kategori = chip.getAttribute( 'data-cat' );

		if ( ! kategori || kategori === state.kategori ) {
			return;
		}

		state.kategori = kategori;

		kategoriUygula();
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
			var chip = olay.target.closest( '.qrms-an-tab' );

			if ( chip ) {
				kategoriSec( chip );
				return;
			}

			var masaDugme = olay.target.closest( '.qrms-an-masa-sec' );

			if ( masaDugme ) {
				// Masa filtresi paylaşılan bağlamdır: sayfa içinde durum
				// tutmak yerine adrese yazılır, böylece diğer kategorilere
				// geçildiğinde de aynı masa seçili kalır.
				window.location.href = String( CFG.masaUrl || '' )
					.replace( '__MASA__', encodeURIComponent( masaDugme.getAttribute( 'data-masa' ) || '' ) );
			}
		} );

		if ( el.urunDonem ) {
			el.urunDonem.addEventListener( 'change', function () {
				state.urunDonem = el.urunDonem.value || 'hourly';
				yukle();
			} );
		}

		el.refresh.addEventListener( 'click', function () {
			// Yenile, önbelleği tümüyle düşürür: kullanıcı "şu an ne oluyor"u
			// sorar, saklanmış bir yanıtı değil.
			state.onbellek = {};
			yukle( true );
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

					// Kayıtlar silindi: saklanan yanıtların hepsi bayattır.
					state.onbellek = {};
					yukle( true );
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

		el.panelVeri     = $( 'qrms-an-cat-veri' );
		el.panelUrunler  = $( 'qrms-an-cat-urunler' );
		el.table         = $( 'qrms-an-table' );
		el.products      = $( 'qrms-an-products' );
		el.urunDonem     = $( 'qrms-an-urun-donem' );
		el.refresh       = $( 'qrms-an-refresh' );
		el.clear         = $( 'qrms-an-clear' );
		el.csv           = $( 'qrms-an-csv' );
		el.confirm       = $( 'qrms-an-confirm' );
		el.confirmText   = $( 'qrms-an-confirm-text' );
		el.confirmOk     = $( 'qrms-an-confirm-ok' );
		el.confirmCancel = $( 'qrms-an-confirm-cancel' );

		// Filtre çubuğu bütün kategorilerle ortaktır (analitik-ortak.js).
		ORTAK.filtreKur( el.wrap );

		baglantilariKur();
		kategoriUygula();
		yukle();
	} );
}() );
