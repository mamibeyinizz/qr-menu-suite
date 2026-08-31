/**
 * QR Menü — Analitik paneli (klasik/tüm veriler görünümü).
 *
 * Sayfa iskeleti PHP'den gelir; masa kesiti ve ürün listesi burada tek bir
 * AJAX çağrısının sonucundan üretilir.
 *
 * KAPSAM DARALDI. Özet kartları ve zaman grafiği "Genel Bakış" (analitik-genel.js),
 * ürün listesi "Ürünler" (analitik-urunler.js) kategorisine taşındı; burada
 * yalnızca masa kesiti kaldı. Biçimlendirme, AJAX sarmalayıcısı ve tablo
 * iskeleti ORTAK dosyadadır (analitik-ortak.js) — kopyalanmaz.
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
		// Tek kesit kaldı: masa özeti (son 30 gün).
		donem: 'masalar',
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
	   VERİ YÜKLE
	----------------------------------------------------------------- */

	function onbellekAnahtari( donem, masa ) {
		return donem + '|' + masa;
	}

	/** Sunucu yanıtını ekrana basar. */
	function veriyiBas( veri ) {
		state.masa    = veri.masa || '';
		state.masalar = veri.masalar || [];

		masaTablosuBas( veri.grafik );
		csvAdresiGuncelle();
	}

	/**
	 * Masa kesitinin verisini gösterir.
	 *
	 * @param {boolean} zorla true ise önbellek atlanır (Yenile düğmesi).
	 */
	function yukle( zorla ) {
		var anahtar = onbellekAnahtari( state.donem, state.masa );

		// Aynı pencere zaten yüklüyse kategori değişimi istek doğurmaz.
		if ( ! zorla && state.onbellek[ anahtar ] ) {
			veriyiBas( state.onbellek[ anahtar ] );
			return;
		}

		state.yukleniyor = true;

		el.table.innerHTML = '<div class="qrms-an-loading">' + esc( metin( 'loading', 'Yükleniyor' ) ) + '</div>';

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
				el.table.innerHTML = bosDurum( 'dashicons-warning', metin( 'loadError', 'Veri yüklenemedi. Sayfayı yenileyin.' ) );
			}
		);
	}

	/* -----------------------------------------------------------------
	   OLAYLAR
	----------------------------------------------------------------- */

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
			var masaDugme = olay.target.closest( '.qrms-an-masa-sec' );

			if ( masaDugme ) {
				// Masa filtresi paylaşılan bağlamdır: sayfa içinde durum
				// tutmak yerine adrese yazılır, böylece diğer kategorilere
				// geçildiğinde de aynı masa seçili kalır.
				window.location.href = String( CFG.masaUrl || '' )
					.replace( '__MASA__', encodeURIComponent( masaDugme.getAttribute( 'data-masa' ) || '' ) );
			}
		} );

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
		el.table         = $( 'qrms-an-table' );
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
		yukle();
	} );
}() );
