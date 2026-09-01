/**
 * QR Chatbot — görünüm sihirbazı ve canlı önizleme.
 */
( function () {
	'use strict';

	var cfg = window.qmoChatbotAdmin || {};
	var presets = cfg.presets || {};
	var colors = cfg.colors || {};
	var initial = cfg.initial || {};
	var icons = cfg.icons || {};
	var toastEl = document.getElementById( 'qmo-toast' );
	var activePresetInput = document.getElementById( 'gemini_active_preset' );
	var overrideInput = document.getElementById( 'qmo_chatbot_color_overrides' );
	var overrides = [];

	try {
		overrides = overrideInput && overrideInput.value ? JSON.parse( overrideInput.value ) : [];
	} catch ( e ) {
		overrides = [];
	}
	if ( ! Array.isArray( overrides ) ) {
		overrides = [];
	}

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

	function hexParcala( hex ) {
		hex = String( hex || '' ).replace( '#', '' );
		if ( 3 === hex.length ) {
			hex = hex[ 0 ] + hex[ 0 ] + hex[ 1 ] + hex[ 1 ] + hex[ 2 ] + hex[ 2 ];
		}
		return [
			parseInt( hex.substr( 0, 2 ), 16 ) || 0,
			parseInt( hex.substr( 2, 2 ), 16 ) || 0,
			parseInt( hex.substr( 4, 2 ), 16 ) || 0
		];
	}

	function rgbHex( r, g, b ) {
		function p( n ) {
			var s = Math.max( 0, Math.min( 255, Math.round( n ) ) ).toString( 16 );
			return 1 === s.length ? '0' + s : s;
		}
		return '#' + p( r ) + p( g ) + p( b );
	}

	function parlaklik( hex ) {
		var c = hexParcala( hex );
		return ( 0.2126 * c[ 0 ] + 0.7152 * c[ 1 ] + 0.0722 * c[ 2 ] ) / 255;
	}

	function karistir( a, b, oran ) {
		var x = hexParcala( a );
		var y = hexParcala( b );
		return rgbHex(
			x[ 0 ] + ( y[ 0 ] - x[ 0 ] ) * oran,
			x[ 1 ] + ( y[ 1 ] - x[ 1 ] ) * oran,
			x[ 2 ] + ( y[ 2 ] - x[ 2 ] ) * oran
		);
	}

	function kontrastYazi( zemin ) {
		return parlaklik( zemin ) > 0.55 ? '#1a1a1a' : '#ffffff';
	}

	function okunur( yazi, zemin ) {
		return Math.abs( parlaklik( yazi ) - parlaklik( zemin ) ) >= 0.35 ? yazi : kontrastYazi( zemin );
	}

	function turetilsin( ana, zemin, yazi ) {
		var headerYazi = kontrastYazi( ana );
		var koyu = parlaklik( zemin ) < 0.35;
		var userBg = koyu ? ana : karistir( ana, '#ffffff', 0.78 );
		var botBg = koyu ? karistir( zemin, '#ffffff', 0.08 ) : karistir( zemin, '#ffffff', 0.65 );
		var giris = koyu ? karistir( zemin, '#ffffff', 0.06 ) : karistir( zemin, '#ffffff', 0.35 );
		var alan = koyu ? zemin : '#ffffff';
		var kenar = koyu ? karistir( zemin, '#ffffff', 0.14 ) : karistir( zemin, yazi, 0.16 );
		return {
			gemini_main_color: ana,
			gemini_toggle_bg_color: ana,
			gemini_toggle_text_color: headerYazi,
			gemini_header_bg_color: ana,
			gemini_header_text_color: headerYazi,
			gemini_header_icon_color: headerYazi,
			gemini_text_color: okunur( yazi, zemin ),
			gemini_border_color: kenar,
			gemini_bg_color: zemin,
			gemini_chat_bg_color: zemin,
			gemini_user_msg_color: userBg,
			gemini_user_msg_text_color: okunur( koyu ? headerYazi : yazi, userBg ),
			gemini_bot_msg_color: botBg,
			gemini_bot_msg_text_color: okunur( yazi, botBg ),
			gemini_input_bg_color: giris,
			gemini_input_area_bg_color: alan,
			gemini_send_btn_bg_color: ana,
			gemini_send_btn_icon_color: headerYazi
		};
	}

	function yazRenk( anahtar, deger ) {
		var input = document.getElementById( anahtar );
		if ( input ) {
			input.value = deger;
			var code = input.parentNode ? input.parentNode.querySelector( 'code' ) : null;
			if ( code ) {
				code.textContent = deger;
			}
		}
		colors[ anahtar ] = deger;
		if ( 'gemini_main_color' === anahtar ) {
			var gizli = document.getElementById( 'gemini_main_color' );
			if ( gizli ) {
				gizli.value = deger;
			}
			var ana = document.getElementById( 'qmo_cb_ana' );
			if ( ana ) {
				ana.value = deger;
			}
		}
	}

	function anaRenkleriUygula() {
		var ana = ( document.getElementById( 'qmo_cb_ana' ) || {} ).value || renk( 'gemini_main_color' );
		var zemin = renk( 'gemini_chat_bg_color' );
		var yazi = renk( 'gemini_text_color' );
		var t = turetilsin( ana, zemin, yazi );
		Object.keys( t ).forEach( function ( key ) {
			if ( overrides.indexOf( key ) !== -1 ) {
				return;
			}
			if ( 'gemini_chat_bg_color' === key || 'gemini_text_color' === key ) {
				return;
			}
			yazRenk( key, t[ key ] );
		} );
		yazRenk( 'gemini_main_color', ana );
		if ( overrideInput ) {
			overrideInput.value = JSON.stringify( overrides );
		}
	}

	function secili( name ) {
		var el = document.querySelector( 'input[name="' + name + '"]:checked' );
		return el ? el.value : '';
	}

	function ikonHtml() {
		var preset = ( document.getElementById( 'qmo_chatbot_icon_preset' ) || {} ).value || 'bubble';
		var url = ( document.getElementById( 'gemini_bot_icon' ) || {} ).value || initial.iconUrl || '';
		if ( 'custom' === preset && url ) {
			return '<img src="' + url.replace( /"/g, '' ) + '" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" />';
		}
		return icons[ preset ] || cfg.defaultIcon || '';
	}

	function onizlemeGuncelle() {
		var wrap = document.getElementById( 'qmo-preview-wrap' );
		if ( ! wrap ) {
			return;
		}

		anaRenkleriUygula();

		var header = document.getElementById( 'qmo-preview-header' );
		var log = document.getElementById( 'qmo-preview-log' );
		var inputArea = document.getElementById( 'qmo-preview-input-area' );
		var botBubble = document.getElementById( 'qmo-preview-bot-bubble' );
		var userBubble = document.getElementById( 'qmo-preview-user-bubble' );
		var input = document.getElementById( 'qmo-preview-input' );
		var send = document.getElementById( 'qmo-preview-send' );
		var title = document.getElementById( 'qmo-preview-title' );
		var toggle = document.getElementById( 'qmo-preview-toggle' );
		var live = document.getElementById( 'qmo-cb-live' );
		var badge = document.getElementById( 'qmo-preview-badge' );
		var teaser = document.getElementById( 'qmo-preview-teaser' );
		var welcome = document.getElementById( 'qmo-preview-welcome' );

		if ( title ) {
			title.textContent = initial.botName || 'Asistan';
		}
		if ( botBubble ) {
			botBubble.textContent = initial.welcome || 'Merhaba!';
		}
		if ( input ) {
			input.value = initial.placeholder || 'Bir şeyler sorun...';
		}

		var html = ikonHtml();
		[ 'qmo-preview-icon', 'qmo-preview-header-icon', 'qmo-preview-welcome-icon' ].forEach( function ( id ) {
			var el = document.getElementById( id );
			if ( el ) {
				el.innerHTML = html;
			}
		} );

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

		var kose = secili( 'qmo_chatbot_radius_preset' ) || 'soft';
		var radiusPx = ( cfg.radii && cfg.radii[ kose ] ) ? cfg.radii[ kose ] : 16;
		var sizeKey = document.getElementById( 'gemini_border_radius' );
		if ( sizeKey ) {
			sizeKey.value = radiusPx;
		}
		wrap.style.borderRadius = radiusPx + 'px';

		var boyut = secili( 'qmo_chatbot_icon_size_preset' ) || 'medium';
		var px = ( cfg.sizes && cfg.sizes[ boyut ] ) ? cfg.sizes[ boyut ] : 48;
		var sizeInput = document.getElementById( 'gemini_icon_size' );
		if ( sizeInput ) {
			sizeInput.value = px;
		}
		if ( toggle ) {
			toggle.style.width = px + 'px';
			toggle.style.height = px + 'px';
			toggle.style.background = ( document.getElementById( 'qmo_chatbot_icon_bg_color' ) || {} ).value || '#8a2be2';
			toggle.style.color = ( document.getElementById( 'qmo_chatbot_icon_color' ) || {} ).value || '#ffffff';
			toggle.style.left = 'left' === secili( 'qmo_chatbot_position' ) ? '16px' : 'auto';
			toggle.style.right = 'left' === secili( 'qmo_chatbot_position' ) ? 'auto' : '16px';
			var off = secili( 'qmo_chatbot_offset' ) || 'mid';
			toggle.style.bottom = ( ( cfg.offsets && cfg.offsets[ off ] ) ? cfg.offsets[ off ] : 108 ) + 'px';
		}

		if ( badge ) {
			badge.style.display = ( document.querySelector( 'input[name="qmo_chatbot_badge"]:checked' ) && 'yes' === document.querySelector( 'input[name="qmo_chatbot_badge"]:checked' ).value ) ||
				( document.querySelector( 'input[name="qmo_chatbot_badge"][type="checkbox"]' ) && document.querySelector( 'input[name="qmo_chatbot_badge"][type="checkbox"]' ).checked )
				? 'block' : 'none';
		}

		if ( teaser ) {
			var teaserOn = document.querySelector( 'input[name="qmo_chatbot_teaser"][type="checkbox"]' );
			teaser.style.display = teaserOn && teaserOn.checked ? 'block' : 'none';
			var teaserText = document.getElementById( 'qmo_chatbot_teaser_text' );
			if ( teaserText ) {
				teaser.textContent = teaserText.value;
			}
		}

		if ( welcome ) {
			var wOn = document.querySelector( 'input[name="qmo_chatbot_welcome_screen"][type="checkbox"]' );
			welcome.classList.toggle( 'is-on', !!( wOn && wOn.checked ) );
			var wText = document.getElementById( 'qmo_chatbot_welcome_intro' );
			var wBtn = document.getElementById( 'qmo_chatbot_welcome_btn' );
			var wName = document.getElementById( 'qmo-preview-welcome-name' );
			var wP = document.getElementById( 'qmo-preview-welcome-text' );
			var wB = document.getElementById( 'qmo-preview-welcome-btn' );
			if ( wName ) {
				wName.textContent = initial.botName || 'Asistan';
			}
			if ( wP && wText ) {
				wP.textContent = wText.value;
			}
			if ( wB && wBtn ) {
				wB.textContent = wBtn.value;
			}
		}

		if ( live ) {
			var genis = secili( 'qmo_chatbot_window_width' ) || 'normal';
			wrap.style.maxWidth = ( ( cfg.widths && cfg.widths[ genis ] ) ? cfg.widths[ genis ] : 380 ) + 'px';
		}
	}

	function sablonUygula( slug ) {
		var preset = presets[ slug ];
		if ( ! preset || ! preset.colors ) {
			return;
		}
		overrides = [];
		Object.keys( preset.colors ).forEach( function ( key ) {
			yazRenk( key, preset.colors[ key ] );
		} );
		if ( activePresetInput ) {
			activePresetInput.value = slug;
		}
		if ( overrideInput ) {
			overrideInput.value = '[]';
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

	document.querySelectorAll( '.qmo-cb-step' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var step = btn.getAttribute( 'data-step' );
			document.querySelectorAll( '.qmo-cb-step' ).forEach( function ( b ) {
				b.classList.toggle( 'is-active', b === btn );
			} );
			document.querySelectorAll( '.qmo-cb-panel' ).forEach( function ( p ) {
				p.classList.toggle( 'is-active', p.getAttribute( 'data-step-panel' ) === step );
			} );
		} );
	} );

	document.querySelectorAll( '[data-preview-device]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			document.querySelectorAll( '[data-preview-device]' ).forEach( function ( b ) {
				b.classList.toggle( 'is-active', b === btn );
			} );
			var live = document.getElementById( 'qmo-cb-live' );
			if ( live ) {
				live.classList.toggle( 'is-phone', 'phone' === btn.getAttribute( 'data-preview-device' ) );
			}
		} );
	} );

	document.querySelectorAll( '[data-preview-state]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			document.querySelectorAll( '[data-preview-state]' ).forEach( function ( b ) {
				b.classList.toggle( 'is-active', b === btn );
			} );
			var live = document.getElementById( 'qmo-cb-live' );
			if ( live ) {
				live.classList.toggle( 'is-closed', 'closed' === btn.getAttribute( 'data-preview-state' ) );
				live.classList.toggle( 'is-open', 'open' === btn.getAttribute( 'data-preview-state' ) );
			}
		} );
	} );

	document.querySelectorAll( '.qmo-cb-icon-tile' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var slug = btn.getAttribute( 'data-icon' );
			var hidden = document.getElementById( 'qmo_chatbot_icon_preset' );
			if ( hidden ) {
				hidden.value = slug;
			}
			document.querySelectorAll( '.qmo-cb-icon-tile' ).forEach( function ( t ) {
				t.classList.toggle( 'is-selected', t === btn );
				t.setAttribute( 'aria-pressed', t === btn ? 'true' : 'false' );
			} );
			var custom = document.getElementById( 'qmo-cb-custom-icon' );
			if ( custom ) {
				custom.classList.toggle( 'is-open', 'custom' === slug );
			}
			onizlemeGuncelle();
		} );
	} );

	document.querySelectorAll( '.qmo-apply-preset' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			sablonUygula( btn.getAttribute( 'data-preset' ) );
		} );
	} );

	document.querySelectorAll( '.qmo-cb-ana-renk' ).forEach( function ( input ) {
		input.addEventListener( 'input', function () {
			if ( activePresetInput ) {
				activePresetInput.value = '';
			}
			onizlemeGuncelle();
		} );
	} );

	document.querySelectorAll( '.qmo-cb-adv-renk' ).forEach( function ( input ) {
		input.addEventListener( 'input', function () {
			var key = input.getAttribute( 'data-color-key' );
			if ( key && overrides.indexOf( key ) === -1 ) {
				overrides.push( key );
			}
			if ( overrideInput ) {
				overrideInput.value = JSON.stringify( overrides );
			}
			if ( activePresetInput ) {
				activePresetInput.value = '';
			}
			var code = input.parentNode ? input.parentNode.querySelector( 'code' ) : null;
			if ( code ) {
				code.textContent = input.value;
			}
			onizlemeGuncelle();
		} );
	} );

	var adv = document.querySelector( '.qmo-cb-advanced' );
	if ( adv ) {
		adv.addEventListener( 'toggle', function () {
			var hidden = document.getElementById( 'qmo_chatbot_advanced_colors' );
			if ( hidden ) {
				hidden.value = adv.open ? 'yes' : 'no';
			}
		} );
	}

	var resetBtn = document.getElementById( 'qmo-cb-reset-colors' );
	if ( resetBtn ) {
		resetBtn.addEventListener( 'click', function () {
			overrides = [];
			if ( cfg.defaults ) {
				Object.keys( cfg.defaults ).forEach( function ( key ) {
					yazRenk( key, cfg.defaults[ key ] );
				} );
			}
			if ( activePresetInput ) {
				activePresetInput.value = '';
			}
			if ( overrideInput ) {
				overrideInput.value = '[]';
			}
			onizlemeGuncelle();
		} );
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
				initial.iconUrl = iconInput.value;
				onizlemeGuncelle();
			} );
			frame.open();
		} );
	}

	document.querySelectorAll( '.qmo-cb-wizard input, .qmo-cb-wizard textarea' ).forEach( function ( el ) {
		el.addEventListener( 'input', onizlemeGuncelle );
		el.addEventListener( 'change', onizlemeGuncelle );
	} );

	onizlemeGuncelle();
}() );
