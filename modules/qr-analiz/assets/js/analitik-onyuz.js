/**
 * QR Menü — ön yüz analitik beacon'ı.
 *
 * Splash, dil seçici, galeri ve (Faz 8D) ürün detay modalı bu dosyadaki
 * `yaz()` ile olay gönderir. qr-analiz lisansta yoksa dosya kuyruğa
 * girmez; çağıran `window.qrmsAnalitikOnyuz` yoksa sessizce çıkar.
 *
 * Yanıt her zaman yutulur: analitik, menü/splash/galeri akışını kesmez.
 * Masa istemciden GÖNDERİLMEZ — sunucu oturum çerezi / referer'dan okur.
 */
( function ( global ) {
	'use strict';

	var CFG = global.qrmsAnalitikOnyuzCfg || {};

	function yaz( tip, extra ) {
		if ( ! CFG.ajaxUrl || ! CFG.nonce || ! tip ) {
			return;
		}

		extra = extra || {};

		var govde = new URLSearchParams();
		govde.append( 'action', 'qrms_analitik_onyuz' );
		govde.append( 'nonce', CFG.nonce );
		govde.append( 'tip', String( tip ) );

		if ( extra.item_name ) {
			govde.append( 'item_name', String( extra.item_name ) );
		}

		if ( extra.item_id ) {
			govde.append( 'item_id', String( extra.item_id ) );
		}

		try {
			global.fetch( CFG.ajaxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				credentials: 'same-origin',
				keepalive: true,
				body: govde.toString()
			} ).catch( function () {} );
		} catch ( e ) {}
	}

	global.qrmsAnalitikOnyuz = { yaz: yaz };
}( window ) );
