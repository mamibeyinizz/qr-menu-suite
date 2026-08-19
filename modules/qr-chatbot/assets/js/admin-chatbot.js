/**
 * QR Chatbot — yönetim ekranı (renk şablonları, canlı önizleme, medya seçici).
 */
( function () {
	'use strict';

	var cfg = window.qmoChatbotAdmin || {};
	var presets = cfg.presets || {};
	var colors = cfg.colors || {};

	var toastEl = document.getElementById( 'qmo-toast' );
	var form = document.getElementById( 'qmo-chatbot-form' );
	var activePresetInput = document.getElementById( 'gemini_active_preset' );

	function toast( mesaj ) {
		if ( ! toastEl ) {
			return;
		}
		toastEl.textContent = mesaj;
		toastEl.style.display = 'block';
		window.setTimeout( function () {
			toastEl.style.display = 'none';
		}, 2800 );
	}

	function renk( anahtar ) {
		var input = document.getElementById( anahtar );
		if ( input && input.value ) {
			return input.value;
		}
		if ( colors[ anahtar ] ) {
			return colors[ anahtar ];
		}
		if ( cfg.defaults && cfg.defaults[ anahtar ] ) {
			return cfg.defaults[ anahtar ];
		}
		return '#8a2be2';
	}

	function onizlemeGuncelle() {
		var wrap = document.getElementById( 'qmo-preview-wrap' );
		if ( ! wrap ) {
			return;
		}

		var header = document.getElementById( 'qmo-preview-header' );
		var log = document.getElementById( 'qmo-preview-log' );
		var inputArea = document.getElementById( 'qmo-preview-input-area' );
		var botBubble = document.getElementById( 'qmo-preview-bot-bubble' );
		var userBubble = document.getElementById( 'qmo-preview-user-bubble' );
		var input = document.getElementById( 'qmo-preview-input' );
		var send = document.getElementById( 'qmo-preview-send' );
		var title = document.getElementById( 'qmo-preview-title' );
		var botName = document.getElementById( 'gemini_bot_name' );

		if ( title && botName ) {
			title.textContent = botName.value || 'Asistan';
		}

		var welcome = document.getElementById( 'gemini_welcome_text' );
		if ( botBubble && welcome ) {
			botBubble.textContent = welcome.value || 'Merhaba!';
		}

		var placeholder = document.getElementById( 'gemini_placeholder_text' );
		if ( input && placeholder ) {
			input.value = placeholder.value || 'Bir şeyler sorun...';
		}

		if ( header ) {
			header.style.background = renk( 'gemini_header_bg_color' );
			header.style.color = renk( 'gemini_header_text_color' );
		}
		if ( log ) {
			log.style.background = renk( 'gemini_chat_bg_color' );
		}
		if ( botBubble ) {
			botBubble.style.background = renk( 'gemini_bot_msg_color' );
			botBubble.style.color = renk( 'gemini_bot_msg_text_color' );
			botBubble.style.borderColor = renk( 'gemini_border_color' );
		}
		if ( userBubble ) {
			userBubble.style.background = renk( 'gemini_user_msg_color' );
			userBubble.style.color = renk( 'gemini_user_msg_text_color' );
			userBubble.style.borderColor = renk( 'gemini_border_color' );
		}
		if ( inputArea ) {
			inputArea.style.background = renk( 'gemini_input_area_bg_color' );
			inputArea.style.borderColor = renk( 'gemini_border_color' );
		}
		if ( input ) {
			input.style.background = renk( 'gemini_input_bg_color' );
			input.style.color = renk( 'gemini_text_color' );
			input.style.borderColor = renk( 'gemini_border_color' );
		}
		if ( send ) {
			send.style.background = renk( 'gemini_send_btn_bg_color' );
			send.style.color = renk( 'gemini_send_btn_icon_color' );
		}

		var radius = document.getElementById( 'gemini_border_radius' );
		var radiusPx = radius ? Math.max( 14, parseInt( radius.value, 10 ) || 16 ) : 16;
		wrap.style.borderRadius = radiusPx + 'px';
	}

	function sablonUygula( slug ) {
		var preset = presets[ slug ];
		if ( ! preset || ! preset.colors ) {
			return;
		}

		Object.keys( preset.colors ).forEach( function ( key ) {
			var input = document.getElementById( key );
			if ( input ) {
				input.value = preset.colors[ key ];
				var code = input.parentNode ? input.parentNode.querySelector( 'code' ) : null;
				if ( code ) {
					code.textContent = preset.colors[ key ];
				}
			}
			colors[ key ] = preset.colors[ key ];
		} );

		if ( activePresetInput ) {
			activePresetInput.value = slug;
		}

		document.querySelectorAll( '.qmo-preset-card' ).forEach( function ( card ) {
			card.classList.toggle( 'is-active', card.getAttribute( 'data-preset' ) === slug );
			var badge = card.querySelector( '.qmo-active-badge' );
			if ( card.getAttribute( 'data-preset' ) === slug ) {
				if ( ! badge ) {
					badge = document.createElement( 'span' );
					badge.className = 'qmo-active-badge';
					badge.textContent = 'Aktif';
					card.appendChild( badge );
				}
			} else if ( badge ) {
				badge.remove();
			}
		} );

		onizlemeGuncelle();
		toast( ( cfg.strings && cfg.strings.presetApplied ) || 'Şablon uygulandı.' );
	}

	document.querySelectorAll( '.qmo-apply-preset' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			sablonUygula( btn.getAttribute( 'data-preset' ) );
		} );
	} );

	document.querySelectorAll( '.qmo-color-input' ).forEach( function ( input ) {
		input.addEventListener( 'input', function () {
			var code = input.parentNode ? input.parentNode.querySelector( 'code' ) : null;
			if ( code ) {
				code.textContent = input.value;
			}
			if ( activePresetInput ) {
				activePresetInput.value = '';
			}
			onizlemeGuncelle();
		} );
	} );

	[ 'gemini_bot_name', 'gemini_welcome_text', 'gemini_placeholder_text' ].forEach( function ( id ) {
		var el = document.getElementById( id );
		if ( el ) {
			el.addEventListener( 'input', onizlemeGuncelle );
		}
	} );

	var radiusInput = document.getElementById( 'gemini_border_radius' );
	if ( radiusInput ) {
		radiusInput.addEventListener( 'input', onizlemeGuncelle );
	}

	var uploadBtn = document.getElementById( 'qmo-icon-upload' );
	var iconInput = document.getElementById( 'gemini_bot_icon' );
	if ( uploadBtn && iconInput && window.wp && wp.media ) {
		uploadBtn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var frame = wp.media( {
				title: ( cfg.strings && cfg.strings.selectIcon ) || 'Bot ikonu seç',
				button: { text: ( cfg.strings && cfg.strings.useIcon ) || 'Kullan' },
				multiple: false
			} );
			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				iconInput.value = attachment.url || '';
			} );
			frame.open();
		} );
	}

	if ( form ) {
		form.addEventListener( 'submit', function () {
			// Görünüm sekmesi dışındayken renk alanları DOM'da olmayabilir;
			// kayıt sırasında mevcut option'lar korunur.
		} );
	}

	onizlemeGuncelle();
}() );
