/**
 * QR Chatbot — görünüm sihirbazı ve canlı önizleme.
 *
 * Tek bir state nesnesi tutulur; tüm alanlar onu günceller, tek render()
 * önizlemeyi ön yüzdeki --gm-* değişkenleri ve sınıf adlarıyla çizer.
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
	var live = document.getElementById( 'qmo-cb-live' );
	var root = document.getElementById( 'qmo-cb-preview-root' );
	var overrides = [];

	var state = {
		device: 'phone',
		mode: 'closed',
		welcomeStarted: false,
		teaserDismissed: false
	};

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

	function acikMi( name ) {
		var cb = document.querySelector( 'input[name="' + name + '"][type="checkbox"]' );
		return !!( cb && cb.checked );
	}

	function deger( id, yedek ) {
		var el = document.getElementById( id );
		return el && el.value ? el.value : ( yedek || '' );
	}

	function ikonHtml() {
		var preset = deger( 'qmo_chatbot_icon_preset', 'bubble' );
		var url = deger( 'gemini_bot_icon', initial.iconUrl || '' );
		if ( 'custom' === preset && url ) {
			return '<img src="' + url.replace( /"/g, '' ) + '" alt="" />';
		}
		return icons[ preset ] || cfg.defaultIcon || '';
	}

	function renkHaritasi() {
		return {
			'--gm-main': renk( 'gemini_main_color' ),
			'--gm-toggle-bg': deger( 'qmo_chatbot_icon_bg_color', renk( 'gemini_toggle_bg_color' ) ),
			'--gm-toggle-text': deger( 'qmo_chatbot_icon_color', renk( 'gemini_toggle_text_color' ) ),
			'--gm-header-bg': renk( 'gemini_header_bg_color' ),
			'--gm-header-text': renk( 'gemini_header_text_color' ),
			'--gm-header-icon': renk( 'gemini_header_icon_color' ),
			'--gm-text': renk( 'gemini_text_color' ),
			'--gm-border': renk( 'gemini_border_color' ),
			'--gm-chat-bg': renk( 'gemini_chat_bg_color' ),
			'--gm-user-bg': renk( 'gemini_user_msg_color' ),
			'--gm-user-text': renk( 'gemini_user_msg_text_color' ),
			'--gm-bot-bg': renk( 'gemini_bot_msg_color' ),
			'--gm-bot-text': renk( 'gemini_bot_msg_text_color' ),
			'--gm-input-bg': renk( 'gemini_input_bg_color' ),
			'--gm-input-area-bg': renk( 'gemini_input_area_bg_color' ),
			'--gm-send-bg': renk( 'gemini_send_btn_bg_color' ),
			'--gm-send-icon': renk( 'gemini_send_btn_icon_color' )
		};
	}

	function degiskenYaz( el, ad, degerVar ) {
		if ( el ) {
			el.style.setProperty( ad, degerVar );
		}
	}

	function render() {
		if ( ! live || ! root ) {
			return;
		}

		anaRenkleriUygula();

		var overlay = root.querySelector( '.gemini-chat-overlay' );
		var toggle = root.querySelector( '.gemini-chat-toggle-btn' );
		var teaser = root.querySelector( '.gemini-teaser' );
		var badge = root.querySelector( '.gemini-unread-badge' );
		var welcome = root.querySelector( '.gemini-welcome-screen' );
		var log = root.querySelector( '.gemini-chat-log' );
		var chips = root.querySelector( '.gemini-quick-replies' );
		var inputArea = root.querySelector( '.gemini-chat-input-area' );
		var input = root.querySelector( '.gemini-chat-input' );
		var botBubble = root.querySelector( '[data-preview-welcome]' );
		var kose = secili( 'qmo_chatbot_radius_preset' ) || 'soft';
		var radiusPx = ( cfg.radii && cfg.radii[ kose ] ) ? cfg.radii[ kose ] : 16;
		var boyut = secili( 'qmo_chatbot_icon_size_preset' ) || 'medium';
		var iconPx = ( cfg.sizes && cfg.sizes[ boyut ] ) ? cfg.sizes[ boyut ] : 48;
		var off = secili( 'qmo_chatbot_offset' ) || 'mid';
		var bottomPx = ( cfg.offsets && cfg.offsets[ off ] ) ? cfg.offsets[ off ] : 108;
		var genis = secili( 'qmo_chatbot_window_width' ) || 'normal';
		var windowPx = ( cfg.widths && cfg.widths[ genis ] ) ? cfg.widths[ genis ] : 380;
		var konum = secili( 'qmo_chatbot_position' ) || 'right';
		var hareket = secili( 'qmo_chatbot_attention' ) || 'none';
		var attnMap = { pulse: 'gm-attn-pulse', shake: 'gm-attn-shake', float: 'gm-attn-float' };
		var welcomeOn = acikMi( 'qmo_chatbot_welcome_screen' );
		var teaserOn = acikMi( 'qmo_chatbot_teaser' );
		var badgeOn = acikMi( 'qmo_chatbot_badge' );
		var acik = 'open' === state.mode;
		var girisGoster = acik && welcomeOn && ! state.welcomeStarted;
		var html = ikonHtml();
		var vars = renkHaritasi();
		var sizeInput = document.getElementById( 'gemini_icon_size' );
		var radiusInput = document.getElementById( 'gemini_border_radius' );

		if ( sizeInput ) {
			sizeInput.value = iconPx;
		}
		if ( radiusInput ) {
			radiusInput.value = radiusPx;
		}

		if ( 'phone' === state.device ) {
			windowPx = Math.min( windowPx, 324 );
		}

		[ root, overlay ].forEach( function ( el ) {
			if ( ! el ) {
				return;
			}
			Object.keys( vars ).forEach( function ( ad ) {
				degiskenYaz( el, ad, vars[ ad ] );
			} );
			degiskenYaz( el, '--gm-radius', radiusPx + 'px' );
			degiskenYaz( el, '--gm-icon-size', iconPx + 'px' );
			degiskenYaz( el, '--gm-bottom', bottomPx + 'px' );
			degiskenYaz( el, '--gm-side', '16px' );
			degiskenYaz( el, '--gm-window', windowPx + 'px' );
			degiskenYaz( el, '--gm-z', '2' );
			degiskenYaz( el, '--gm-toggle-pad', '0' );
			el.classList.toggle( 'gm-pos-left', 'left' === konum );
			el.classList.toggle( 'gm-pos-right', 'left' !== konum );
		} );

		live.classList.toggle( 'is-phone', 'phone' === state.device );
		live.classList.toggle( 'is-desktop', 'desktop' === state.device );
		live.classList.toggle( 'is-open', acik );
		live.classList.toggle( 'is-closed', ! acik );

		root.querySelectorAll( '[data-preview-icon]' ).forEach( function ( el ) {
			el.innerHTML = html;
		} );
		root.querySelectorAll( '[data-preview-bot-name]' ).forEach( function ( el ) {
			el.textContent = initial.botName || 'Asistan';
		} );

		var teaserText = root.querySelector( '[data-preview-teaser-text]' );
		if ( teaserText ) {
			teaserText.textContent = deger( 'qmo_chatbot_teaser_text', '' );
		}
		var welcomeText = root.querySelector( '[data-preview-welcome-text]' );
		if ( welcomeText ) {
			welcomeText.textContent = deger( 'qmo_chatbot_welcome_intro', '' );
		}
		var welcomeBtn = root.querySelector( '[data-preview-welcome-btn]' );
		if ( welcomeBtn ) {
			welcomeBtn.textContent = deger( 'qmo_chatbot_welcome_btn', 'Sohbete Başla' );
		}
		if ( botBubble ) {
			botBubble.textContent = initial.welcome || 'Merhaba!';
		}
		if ( input ) {
			input.value = initial.placeholder || 'Bir şeyler sorun...';
		}

		if ( toggle ) {
			toggle.classList.remove( 'gm-attn-pulse', 'gm-attn-shake', 'gm-attn-float' );
			if ( attnMap[ hareket ] ) {
				toggle.classList.add( attnMap[ hareket ] );
			}
		}
		if ( badge ) {
			badge.hidden = ! badgeOn;
		}
		if ( teaser ) {
			teaser.hidden = ! teaserOn || acik || state.teaserDismissed;
		}
		if ( overlay ) {
			overlay.classList.toggle( 'gemini-acik', acik );
		}
		if ( welcome ) {
			welcome.hidden = ! girisGoster;
		}
		if ( log ) {
			log.hidden = ! acik || girisGoster;
		}
		if ( chips ) {
			chips.hidden = ! acik || girisGoster;
		}
		if ( inputArea ) {
			inputArea.hidden = ! acik || girisGoster;
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
		render();
		toast( ( cfg.strings && cfg.strings.presetApplied ) || 'Şablon uygulandı.' );
	}

	function setMode( mode ) {
		state.mode = mode;
		if ( 'closed' === mode ) {
			state.welcomeStarted = false;
		}
		document.querySelectorAll( '[data-preview-state]' ).forEach( function ( b ) {
			b.classList.toggle( 'is-active', b.getAttribute( 'data-preview-state' ) === mode );
		} );
		render();
	}

	function setDevice( device ) {
		state.device = device;
		document.querySelectorAll( '[data-preview-device]' ).forEach( function ( b ) {
			b.classList.toggle( 'is-active', b.getAttribute( 'data-preview-device' ) === device );
		} );
		render();
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
			render();
		} );
	} );

	document.querySelectorAll( '[data-preview-device]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			setDevice( btn.getAttribute( 'data-preview-device' ) );
		} );
	} );

	document.querySelectorAll( '[data-preview-state]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			setMode( btn.getAttribute( 'data-preview-state' ) );
		} );
	} );

	if ( root ) {
		var toggleBtn = root.querySelector( '.gemini-chat-toggle-btn' );
		var closeBtn = root.querySelector( '.gemini-chat-close' );
		var startBtn = root.querySelector( '.gemini-welcome-start' );
		var teaserClose = root.querySelector( '.gemini-teaser-kapat' );

		if ( toggleBtn ) {
			toggleBtn.addEventListener( 'click', function () {
				setMode( 'open' === state.mode ? 'closed' : 'open' );
			} );
		}
		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', function () {
				setMode( 'closed' );
			} );
		}
		if ( startBtn ) {
			startBtn.addEventListener( 'click', function () {
				state.welcomeStarted = true;
				state.mode = 'open';
				render();
			} );
		}
		if ( teaserClose ) {
			teaserClose.addEventListener( 'click', function () {
				state.teaserDismissed = true;
				render();
			} );
		}
	}

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
			render();
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
			render();
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
			render();
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
			render();
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
				render();
			} );
			frame.open();
		} );
	}

	document.querySelectorAll( '.qmo-cb-wizard input, .qmo-cb-wizard textarea' ).forEach( function ( el ) {
		el.addEventListener( 'input', render );
		el.addEventListener( 'change', function () {
			if ( 'qmo_chatbot_teaser' === el.name ) {
				state.teaserDismissed = false;
			}
			if ( 'qmo_chatbot_welcome_screen' === el.name ) {
				state.welcomeStarted = false;
			}
			render();
		} );
	} );

	render();
}() );
