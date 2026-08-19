/**
 * QR Menü → Chatbot Ayarları ekranı.
 * Renk seçiciler, medya yükleyici, hazır şablonlar ve canlı önizleme.
 */
jQuery( function ( $ ) {
	'use strict';

	$( '.qmo-color-picker' ).wpColorPicker( {
		change: function () {
			setTimeout( onizlemeGuncelle, 30 );
		},
		clear: function () {
			setTimeout( onizlemeGuncelle, 30 );
		}
	} );

	/* ---- medya yükleyici ---- */

	var yukleyici;
	$( '#qmo-upload-icon' ).on( 'click', function ( e ) {
		e.preventDefault();

		if ( yukleyici ) {
			yukleyici.open();
			return;
		}

		yukleyici = wp.media( {
			title: qmoAdmin.ikonSec,
			button: { text: qmoAdmin.kullan },
			multiple: false
		} );

		yukleyici.on( 'select', function () {
			var ek = yukleyici.state().get( 'selection' ).first().toJSON();
			$( '#gemini_bot_icon' ).val( ek.url );
			$( '#qmo-preview-icon' ).html(
				$( '<img>' ).attr( { src: ek.url, alt: '' } )
			);
		} );

		yukleyici.open();
	} );

	/* ---- başlık canlı yazımı ---- */

	$( '#gemini_bot_name' ).on( 'input', function () {
		$( '#qmo-preview-title' ).text( $( this ).val() );
	} );

	/* ---- hazır şablonlar ---- */

	$( '.qmo-apply-preset' ).on( 'click', function () {
		var anahtar = $( this ).data( 'preset' );
		var renkler = qmoAdmin.presets[ anahtar ];
		if ( ! renkler ) {
			return;
		}

		$.each( renkler, function ( alan, renk ) {
			var $alan = $( 'input[name="' + alan + '"]' );
			if ( ! $alan.length ) {
				return;
			}
			$alan.val( renk ).trigger( 'change' );
			if ( $alan.hasClass( 'wp-color-picker' ) ) {
				$alan.wpColorPicker( 'color', renk );
			}
		} );

		$( '#qmo_active_preset' ).val( anahtar );
		$( '.qmo-preset-card' ).removeClass( 'is-active' ).find( '.qmo-active-badge' ).remove();

		var $kart = $( '.qmo-preset-card[data-preset="' + anahtar + '"]' );
		$kart.addClass( 'is-active' ).append( '<span class="qmo-active-badge">✓ Aktif</span>' );

		setTimeout( onizlemeGuncelle, 60 );

		var $toast = $( '#qmo-toast' );
		$toast.text( '"' + $kart.find( 'strong' ).text() + '" ' + qmoAdmin.uygulandi ).fadeIn();
		setTimeout( function () {
			$toast.fadeOut();
		}, 3000 );
	} );

	/* ---- canlı önizleme ---- */

	function g( ad ) {
		var v = $( 'input[name="' + ad + '"]' ).val();
		return v ? v : qmoAdmin.defaults[ ad ];
	}

	function onizlemeGuncelle() {
		var kenar = g( 'gemini_border_color' );
		var yaricap = $( 'input[name="gemini_border_radius"]' ).val() || 16;

		$( '#qmo-preview-header' ).css( {
			background: g( 'gemini_header_bg_color' ),
			color: g( 'gemini_header_text_color' )
		} );
		$( '#qmo-preview-close' ).css( { color: g( 'gemini_header_icon_color' ) } );
		$( '#qmo-preview-log' ).css( { background: g( 'gemini_chat_bg_color' ) } );
		$( '.qmo-preview-bubble' ).css( { 'border-color': kenar, 'border-radius': yaricap + 'px' } );
		$( '#qmo-preview-bot-bubble' ).css( {
			background: g( 'gemini_bot_msg_color' ),
			color: g( 'gemini_bot_msg_text_color' )
		} );
		$( '#qmo-preview-user-bubble' ).css( {
			background: g( 'gemini_user_msg_color' ),
			color: g( 'gemini_user_msg_text_color' )
		} );
		$( '#qmo-preview-input-area' ).css( {
			background: g( 'gemini_input_area_bg_color' ),
			'border-color': kenar
		} );
		$( '#qmo-preview-input' ).css( {
			background: g( 'gemini_input_bg_color' ),
			'border-color': kenar,
			color: g( 'gemini_text_color' )
		} );
		$( '#qmo-preview-send' ).css( {
			background: g( 'gemini_send_btn_bg_color' ),
			color: g( 'gemini_send_btn_icon_color' )
		} );
	}

	onizlemeGuncelle();
} );
