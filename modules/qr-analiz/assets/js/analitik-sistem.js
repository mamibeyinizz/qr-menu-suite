/**
 * QR Menü — Veri & Sistem kategorisi.
 *
 * Sayfanın veri çeken bir yanı yok: bütün sayılar PHP tarafında basılıyor.
 * Buradaki tek iş, klasik panelden AYNEN taşınan silme akışıdır — onay
 * modalı, aynı uç (qrms_analitik_temizle), aynı nonce, aynı davranış. Yıkıcı
 * bir işlemin akışı taşınırken değiştirilmez.
 *
 * Masa filtresi seçiliyken silme YALNIZCA o masayı kapsar; modal metni de
 * buna göre değişir (klasik paneldeki davranışın aynısı).
 */
( function () {
	'use strict';

	var CFG   = window.qrmsAnalitikSistem || {};
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
		el.confirmText.textContent = CFG.masa
			? metin( 'confirmTable', 'Yalnızca bu masanın kayıtları silinecek:' ) + ' ' + ( CFG.masaEtiketi || CFG.masa )
			: metin( 'confirmAll', 'Tüm görüntüleme ve tıklama kayıtları kalıcı olarak silinecek. Bu işlem geri alınamaz.' );

		el.confirm.hidden = false;
		el.confirmOk.focus();
	}

	function modalKapat() {
		el.confirm.hidden = true;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		el.wrap = document.querySelector( '.qrms-an-sistem' );

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

		// Filtre çubuğu (aralık + masa) ortak bileşendir.
		ORTAK.filtreKur( el.wrap );

		el.refresh.addEventListener( 'click', function () {
			// Sayılar PHP'de üretiliyor; tazelemenin tek yolu sayfayı yeniden
			// yüklemek. Tablo istatistiği önbelleği de bu sırada yenilenir.
			window.location.href = CFG.yenileUrl || window.location.href;
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

			ORTAK.post(
				CFG.ajaxUrl,
				{
					action: 'qrms_analitik_temizle',
					security: CFG.nonce,
					masa: CFG.masa || ''
				},
				function () {
					// Silme sonrası ekrandaki bütün sayılar bayattır.
					window.location.reload();
				},
				function () {
					el.confirmOk.textContent = eskiMetin;
					el.confirmOk.disabled    = false;
					modalKapat();
				}
			);
		} );
	} );
}() );
