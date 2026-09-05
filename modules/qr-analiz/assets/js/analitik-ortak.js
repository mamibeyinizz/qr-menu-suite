/**
 * QR Menü — Analitik ekranlarının ORTAK yardımcıları.
 *
 * Modül tek sayfadan kategori sayfalarına bölündüğünde biçimlendirme, AJAX
 * sarmalayıcısı, tablo iskeleti ve grafik çizimi iki ekranda birden gerekli
 * oldu. Kopyalamak yerine tek yerde toplandı: her ekran bu dosyayı bağımlılık
 * olarak yükler ve window.qrmsAnOrtak üzerinden kullanır.
 *
 * Burada EKRAN BİLGİSİ yoktur — hiçbir fonksiyon belirli bir id'yi ya da
 * sayfanın durumunu bilmez; hepsi argümanlarıyla çalışır. Ekrana özgü her şey
 * (hangi kap, hangi filtre, hangi uç) çağıran dosyada durur.
 *
 * jQuery kullanılmaz.
 */
( function () {
	'use strict';

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

	/** Para gösterimi (₺1.234,56). Sunucudan gelen değer her zaman sayıdır. */
	function para( deger ) {
		var n = parseFloat( deger ) || 0;

		return '₺' + n.toLocaleString( 'tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 } );
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

	/**
	 * admin-ajax POST sarmalayıcısı.
	 *
	 * @param {string}   adres Uç adresi (admin-ajax.php).
	 * @param {Object}   veri  Gönderilecek alanlar.
	 * @param {Function} tamam Başarılı yanıt işleyicisi.
	 * @param {Function} hata  Hata işleyicisi (opsiyonel).
	 */
	function post( adres, veri, tamam, hata ) {
		var xhr  = new XMLHttpRequest();
		var govd = [];
		var k;

		for ( k in veri ) {
			if ( Object.prototype.hasOwnProperty.call( veri, k ) ) {
				govd.push( encodeURIComponent( k ) + '=' + encodeURIComponent( veri[ k ] ) );
			}
		}

		xhr.open( 'POST', adres, true );
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

	/**
	 * Boş durum kutusu.
	 *
	 * İkon bir DASHICON sınıfıdır (emoji değil): emoji, admin'in yazı tipi
	 * yığınına göre kutu karakterine düşebiliyor.
	 *
	 * @param {string} ikon  Dashicon sınıfı (ör. 'dashicons-chart-bar').
	 * @param {string} mesaj Görünen metin.
	 * @return {string}
	 */
	function bosDurum( ikon, mesaj ) {
		return '<div class="qrms-an-empty">' +
			'<span class="qrms-an-empty-icon dashicons ' + esc( ikon ) + '" aria-hidden="true"></span>' +
			'<p class="qrms-an-empty-text">' + esc( mesaj ) + '</p></div>';
	}

	/** Tablo hücresi — data-label dar ekrandaki kart görünümünün etiketidir. */
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

	/**
	 * Çubuk grafiğin HTML'i.
	 *
	 * Yükseklikler YÜZDEdir: mobilde grafik alanı kısaldığında çubuklar da
	 * orantılı kısalır, sabit piksel değerleri taşma yapardı.
	 *
	 * @param {Array}  satirlar {label, mv, pc} satırları.
	 * @param {Object} etiket   {mv, pc} — çubukların title metinleri.
	 * @return {string}
	 */
	function grafikHtml( satirlar, etiket ) {
		var enBuyuk = 1;

		satirlar.forEach( function ( s ) {
			enBuyuk = Math.max( enBuyuk, s.mv, s.pc );
		} );

		var html = '<div class="qrms-an-chart-inner">';

		satirlar.forEach( function ( s ) {
			var mv = s.mv > 0 ? Math.max( ( s.mv / enBuyuk ) * 100, 2 ) : 0;
			var pc = s.pc > 0 ? Math.max( ( s.pc / enBuyuk ) * 100, 2 ) : 0;

			html += '<div class="qrms-an-bargroup">' +
				'<div class="qrms-an-bars">' +
				'<div class="qrms-an-bar qrms-an-bar-gold" style="height:' + mv.toFixed( 2 ) + '%" ' +
				'title="' + esc( etiket.mv + ': ' + s.mv ) + '"></div>' +
				'<div class="qrms-an-bar qrms-an-bar-blue" style="height:' + pc.toFixed( 2 ) + '%" ' +
				'title="' + esc( etiket.pc + ': ' + s.pc ) + '"></div>' +
				'</div>' +
				'<div class="qrms-an-barlabel">' + esc( s.label ) + '</div>' +
				'</div>';
		} );

		return html + '</div>';
	}

	/**
	 * PAYLAŞILAN FİLTRE ÇUBUĞUNU canlandırır.
	 *
	 * Çubuk PHP'den gelir ve JavaScript OLMADAN da çalışır (bağlantılar ve GET
	 * formları). Buradaki iki dokunuş yalnızca kullanışlılık içindir:
	 * seçim değişince formu kendiliğinden göndermek ve özel aralık formunu
	 * açıp kapamak. Bileşen tek yerde tanımlı olduğu için canlandırması da
	 * tek yerdedir — her kategori sayfası bunu çağırır.
	 *
	 * @param {Element} kap Sayfanın kök elemanı.
	 */
	function filtreKur( kap ) {
		if ( ! kap ) {
			return;
		}

		var masa = kap.querySelector( '#qrms-an-masa' );

		if ( masa && masa.form ) {
			masa.addEventListener( 'change', function () {
				masa.form.submit();
			} );

			// JS varken "Uygula" düğmesi gereksiz; JS yokken tek yol odur.
			var uygula = masa.form.querySelector( '.qrms-an-masa-uygula' );

			if ( uygula ) {
				uygula.hidden = true;
			}
		}

		var ozelAc = kap.querySelector( '.qrms-an-ozel-ac' );
		var ozel   = kap.querySelector( '#qrms-an-ozel-form' );

		if ( ozelAc && ozel ) {
			ozelAc.addEventListener( 'click', function () {
				ozel.hidden = ! ozel.hidden;
				ozelAc.setAttribute( 'aria-expanded', ozel.hidden ? 'false' : 'true' );
			} );
		}
	}

	window.qrmsAnOrtak = {
		esc: esc,
		sayi: sayi,
		kisa: kisa,
		para: para,
		oran: oran,
		oranSinifi: oranSinifi,
		tarih: tarih,
		post: post,
		bosDurum: bosDurum,
		hucre: hucre,
		acilirBolum: acilirBolum,
		tabloIskelet: tabloIskelet,
		grafikHtml: grafikHtml,
		filtreKur: filtreKur
	};
}() );
