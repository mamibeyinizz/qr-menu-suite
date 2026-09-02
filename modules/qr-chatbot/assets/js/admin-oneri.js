/**
 * Öneri Yönetimi — ürün meta ve cross-sell kuralları.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.qmoChatbotOneri || {};
	var $bildirim = $( '#qmo-cb-oneri-bildirim' );

	function bildir( metin, tur ) {
		if ( ! $bildirim.length ) {
			return;
		}
		$bildirim
			.removeClass( 'is-error is-success' )
			.addClass( tur === 'error' ? 'is-error' : 'is-success' )
			.text( metin )
			.prop( 'hidden', false );
		window.setTimeout( function () {
			$bildirim.prop( 'hidden', true );
		}, 3200 );
	}

	function istek( action, veri ) {
		return $.post( cfg.ajaxUrl, $.extend( { action: action, nonce: cfg.nonce }, veri || {} ) );
	}

	function bosSatirKaldir() {
		$( '#qmo-cb-oneri-kural-listesi .qmo-cb-oneri-kural-bos' ).remove();
	}

	function urunAdiBul( id ) {
		var ad = '';
		$( '#qmo-cb-oneri-kaynak option' ).each( function () {
			if ( String( $( this ).val() ) === String( id ) ) {
				ad = $( this ).text();
			}
		} );
		return ad || ( '#' + id );
	}

	function kuralSatiriEkle( kural ) {
		bosSatirKaldir();
		$( '#qmo-cb-oneri-kural-listesi tr' ).filter( function () {
			return String( $( this ).data( 'kaynak' ) ) === String( kural.kaynak_urun )
				&& String( $( this ).data( 'hedef' ) ) === String( kural.hedef_urun );
		} ).remove();
		var kaynakAd = urunAdiBul( kural.kaynak_urun );
		var hedefAd = urunAdiBul( kural.hedef_urun );
		var aktif = parseInt( kural.aktif, 10 ) ? ' checked' : '';
		var html =
			'<tr data-kural-id="' + kural.id + '" data-kaynak="' + kural.kaynak_urun + '" data-hedef="' + kural.hedef_urun + '">' +
			'<td>' + kaynakAd + '</td>' +
			'<td>' + hedefAd + '</td>' +
			'<td class="qmo-cb-oneri-kural-agirlik">' + kural.agirlik + '</td>' +
			'<td><label class="qmo-cb-oneri-toggle">' +
			'<input type="checkbox" class="qmo-cb-oneri-kural-aktif" value="1"' + aktif + '>' +
			'<span class="qmo-cb-oneri-toggle-ui" aria-hidden="true"></span></label></td>' +
			'<td><button type="button" class="button-link-delete qmo-cb-oneri-kural-sil" data-id="' + kural.id + '">Sil</button></td>' +
			'</tr>';
		$( '#qmo-cb-oneri-kural-listesi' ).prepend( html );
	}

	$( '#qmo-cb-oneri-urun-kaydet' ).on( 'click', function () {
		var urunler = [];
		$( '#qmo-cb-oneri-urun-tablo tbody tr' ).each( function () {
			var $tr = $( this );
			urunler.push( {
				id: $tr.data( 'urun-id' ),
				dahil: $tr.find( '.qmo-cb-oneri-dahil' ).is( ':checked' ) ? 1 : 0,
				agirlik: $tr.find( '.qmo-cb-oneri-agirlik' ).val()
			} );
		} );

		istek( 'qmo_chatbot_oneri_urun_kaydet', { urunler: urunler } ).done( function ( yanit ) {
			if ( yanit && yanit.success ) {
				bildir( ( yanit.data && yanit.data.mesaj ) || cfg.i18n.kaydedildi, 'success' );
			} else {
				bildir( ( yanit && yanit.data && yanit.data.mesaj ) || cfg.i18n.hata, 'error' );
			}
		} ).fail( function () {
			bildir( cfg.i18n.hata, 'error' );
		} );
	} );

	$( '#qmo-cb-oneri-kural-form' ).on( 'submit', function ( e ) {
		e.preventDefault();
		var kaynak = $( '#qmo-cb-oneri-kaynak' ).val();
		var hedef = $( '#qmo-cb-oneri-hedef' ).val();
		var agirlik = $( '#qmo-cb-oneri-kural-agirlik' ).val();

		if ( kaynak && hedef && String( kaynak ) === String( hedef ) ) {
			bildir( cfg.i18n.ayniUrun, 'error' );
			return;
		}

		istek( 'qmo_chatbot_oneri_kural_ekle', {
			kaynak: kaynak,
			hedef: hedef,
			agirlik: agirlik
		} ).done( function ( yanit ) {
			if ( ! yanit || ! yanit.success ) {
				bildir( ( yanit && yanit.data && yanit.data.mesaj ) || cfg.i18n.hata, 'error' );
				return;
			}
			var kural = yanit.data.kural;
			kuralSatiriEkle( kural );
			bildir( cfg.i18n.kuralEklendi, 'success' );
		} ).fail( function () {
			bildir( cfg.i18n.hata, 'error' );
		} );
	} );

	$( document ).on( 'click', '.qmo-cb-oneri-kural-sil', function () {
		var $btn = $( this );
		var id = $btn.data( 'id' );
		istek( 'qmo_chatbot_oneri_kural_sil', { id: id } ).done( function ( yanit ) {
			if ( yanit && yanit.success ) {
				$btn.closest( 'tr' ).remove();
				if ( ! $( '#qmo-cb-oneri-kural-listesi tr' ).length ) {
					$( '#qmo-cb-oneri-kural-listesi' ).html(
						'<tr class="qmo-cb-oneri-kural-bos"><td colspan="5">Henüz kural yok.</td></tr>'
					);
				}
				bildir( cfg.i18n.kuralSilindi, 'success' );
			} else {
				bildir( cfg.i18n.hata, 'error' );
			}
		} );
	} );

	$( document ).on( 'change', '.qmo-cb-oneri-kural-aktif', function () {
		var $cb = $( this );
		var id = $cb.closest( 'tr' ).data( 'kural-id' );
		var aktif = $cb.is( ':checked' ) ? 1 : 0;
		istek( 'qmo_chatbot_oneri_kural_toggle', { id: id, aktif: aktif } ).fail( function () {
			$cb.prop( 'checked', ! aktif );
			bildir( cfg.i18n.hata, 'error' );
		} );
	} );
}( jQuery ) );
