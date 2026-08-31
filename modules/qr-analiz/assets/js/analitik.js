/**
 * QR Menü — Analitik paneli (klasik/tüm veriler görünümü).
 *
 * KAPSAM DARALDI. Özet kartları ve zaman grafiği "Genel Bakış"
 * (analitik-genel.js), ürün listesi "Ürünler" (analitik-urunler.js), masa
 * kesiti "Masalar" (analitik-masalar.js) kategorisine taşındı. Bu ekranda
 * artık tablo yoktur; geriye yalnızca veri yönetimi düğmeleri kaldı ve onlar
 * da "Veri & Sistem" kategorisine taşınınca bu dosya kalkacak.
 *
 * Sayfa bu yüzden veri ÇEKMEZ: tek AJAX çağrısı silme işlemidir. "Yenile"
 * sayfayı yeniden yükler — ekrandaki tek canlı bilgi teşhis kutusudur ve o
 * PHP tarafında üretilir.
 *
 * Onay modalı dışındaki her şey ORTAK dosyadadır (analitik-ortak.js).
 */
( function () {
	'use strict';

	var CFG   = window.qrmsAnalitik || {};
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

	function modalAc() {
		el.confirmText.textContent = metin(
			'confirmAll',
			'Tüm görüntüleme ve tıklama kayıtları kalıcı olarak silinecek. Bu işlem geri alınamaz.'
		);

		el.confirm.hidden = false;
		el.confirmOk.focus();
	}

	function modalKapat() {
		el.confirm.hidden = true;
	}

	function baglantilariKur() {
		el.refresh.addEventListener( 'click', function () {
			// Sayfada önbelleklenen bir veri kalmadı; tazelenecek tek şey
			// PHP'nin bastığı teşhis kutusudur.
			window.location.reload();
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

			// Masa parametresi boş gider: bu ekranda masa seçimi kalmadı,
			// yani silme TÜM kayıtları kapsar. (Uç masa bazlı silmeyi
			// desteklemeye devam ediyor; "Veri & Sistem" kategorisi onu
			// yeniden sunacak.)
			ORTAK.post(
				CFG.ajaxUrl,
				{
					action: 'qrms_analitik_temizle',
					security: CFG.nonce,
					masa: ''
				},
				function () {
					window.location.reload();
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

		el.refresh       = $( 'qrms-an-refresh' );
		el.clear         = $( 'qrms-an-clear' );
		el.confirm       = $( 'qrms-an-confirm' );
		el.confirmText   = $( 'qrms-an-confirm-text' );
		el.confirmOk     = $( 'qrms-an-confirm-ok' );
		el.confirmCancel = $( 'qrms-an-confirm-cancel' );

		if ( ! el.refresh || ! el.clear || ! el.confirm ) {
			return;
		}

		baglantilariKur();
	} );
}() );
