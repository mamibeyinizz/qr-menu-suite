/**
 * QR Menu Suite — premium admin shell interactions (overview only).
 */
( function () {
	'use strict';

	var body = document.body;
	var sidebar = document.getElementById( 'qrms-shell-sidebar' );
	var backdrop = document.getElementById( 'qrms-shell-backdrop' );
	var toggle = document.getElementById( 'qrms-shell-menu-toggle' );
	var drawerClose = document.getElementById( 'qrms-shell-drawer-close' );

	if ( ! body || ! sidebar || ! toggle ) {
		return;
	}

	var mqDesktop = window.matchMedia( '(min-width: 1024px)' );
	var resizeTimer = null;
	var lockedScrollY = 0;
	var touchMoveBlocked = false;
	var drawerTrapKeyDown = null;
	var drawerFocusInHandler = null;

	var FOCUSABLE_SELECTOR = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

	function isDesktop() {
		return mqDesktop.matches;
	}

	function lockPageScroll() {
		if ( isDesktop() || body.classList.contains( 'qrms-shell-scroll-locked' ) ) {
			return;
		}

		lockedScrollY = window.scrollY || window.pageYOffset || 0;
		document.documentElement.classList.add( 'qrms-shell-scroll-locked' );
		body.classList.add( 'qrms-shell-scroll-locked' );
		body.style.position = 'fixed';
		body.style.top = '-' + lockedScrollY + 'px';
		body.style.left = '0';
		body.style.right = '0';
		body.style.width = '100%';
		touchMoveBlocked = true;
	}

	function unlockPageScroll() {
		if ( ! body.classList.contains( 'qrms-shell-scroll-locked' ) ) {
			touchMoveBlocked = false;
			return;
		}

		var restoreY = lockedScrollY;
		document.documentElement.classList.remove( 'qrms-shell-scroll-locked' );
		body.classList.remove( 'qrms-shell-scroll-locked' );
		body.style.position = '';
		body.style.top = '';
		body.style.left = '';
		body.style.right = '';
		body.style.width = '';
		touchMoveBlocked = false;
		window.scrollTo( 0, restoreY );
	}

	function onDocumentTouchMove( event ) {
		if ( ! touchMoveBlocked || isDesktop() ) {
			return;
		}

		if ( event.target.closest && event.target.closest( '#qrms-shell-sidebar' ) ) {
			return;
		}

		event.preventDefault();
	}

	function focusElementWithoutScroll( el ) {
		if ( ! el ) {
			return;
		}

		try {
			el.focus( { preventScroll: true } );
		} catch ( err ) {
			el.focus();
		}
	}

	function focusToggleWithoutScroll() {
		focusElementWithoutScroll( toggle );
	}

	function focusDrawerCloseWithoutScroll() {
		focusElementWithoutScroll( drawerClose || toggle );
	}

	function getDrawerFocusables() {
		var nodes = sidebar.querySelectorAll( FOCUSABLE_SELECTOR );
		var list = [];
		var i;

		for ( i = 0; i < nodes.length; i++ ) {
			var el = nodes[ i ];

			if ( el.hasAttribute( 'hidden' ) ) {
				continue;
			}

			if ( 'none' === window.getComputedStyle( el ).display ) {
				continue;
			}

			if ( ! el.getClientRects().length ) {
				continue;
			}

			list.push( el );
		}

		return list;
	}

	function onDrawerTrapKeyDown( event ) {
		if ( ! body.classList.contains( 'qrms-shell-sidebar-open' ) || isDesktop() ) {
			return;
		}

		if ( 'Tab' !== event.key ) {
			return;
		}

		var focusables = getDrawerFocusables();
		if ( ! focusables.length ) {
			event.preventDefault();
			focusDrawerCloseWithoutScroll();
			return;
		}

		var first = focusables[ 0 ];
		var last = focusables[ focusables.length - 1 ];
		var active = document.activeElement;

		if ( event.shiftKey ) {
			if ( active === first || ! sidebar.contains( active ) ) {
				event.preventDefault();
				focusElementWithoutScroll( last );
			}
			return;
		}

		if ( active === last || ! sidebar.contains( active ) ) {
			event.preventDefault();
			focusElementWithoutScroll( first );
		}
	}

	function onDrawerFocusIn( event ) {
		if ( ! body.classList.contains( 'qrms-shell-sidebar-open' ) || isDesktop() ) {
			return;
		}

		if ( sidebar.contains( event.target ) ) {
			return;
		}

		focusDrawerCloseWithoutScroll();
	}

	function attachDrawerTrap() {
		if ( drawerTrapKeyDown ) {
			return;
		}

		drawerTrapKeyDown = onDrawerTrapKeyDown;
		drawerFocusInHandler = onDrawerFocusIn;
		document.addEventListener( 'keydown', drawerTrapKeyDown );
		document.addEventListener( 'focusin', drawerFocusInHandler );
	}

	function detachDrawerTrap() {
		if ( drawerTrapKeyDown ) {
			document.removeEventListener( 'keydown', drawerTrapKeyDown );
			drawerTrapKeyDown = null;
		}

		if ( drawerFocusInHandler ) {
			document.removeEventListener( 'focusin', drawerFocusInHandler );
			drawerFocusInHandler = null;
		}
	}

	function syncDrawerA11yState( open ) {
		if ( isDesktop() ) {
			sidebar.removeAttribute( 'inert' );
			sidebar.removeAttribute( 'aria-hidden' );
			sidebar.removeAttribute( 'role' );
			sidebar.removeAttribute( 'aria-modal' );
			detachDrawerTrap();
			return;
		}

		if ( open ) {
			sidebar.removeAttribute( 'inert' );
			sidebar.setAttribute( 'aria-hidden', 'false' );
			sidebar.setAttribute( 'role', 'dialog' );
			sidebar.setAttribute( 'aria-modal', 'true' );
			attachDrawerTrap();
			window.requestAnimationFrame( function () {
				focusDrawerCloseWithoutScroll();
			} );
			return;
		}

		sidebar.setAttribute( 'inert', '' );
		sidebar.setAttribute( 'aria-hidden', 'true' );
		sidebar.removeAttribute( 'role' );
		sidebar.removeAttribute( 'aria-modal' );
		detachDrawerTrap();
	}

	function setOpen( open ) {
		body.classList.toggle( 'qrms-shell-sidebar-open', open );
		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

		if ( backdrop ) {
			if ( open && ! isDesktop() ) {
				backdrop.hidden = false;
				backdrop.classList.add( 'is-visible' );
				backdrop.setAttribute( 'aria-hidden', 'false' );
			} else {
				backdrop.classList.remove( 'is-visible' );
				backdrop.setAttribute( 'aria-hidden', 'true' );
				backdrop.hidden = true;
			}
		}

		if ( open && ! isDesktop() ) {
			lockPageScroll();
		} else {
			unlockPageScroll();
		}

		syncDrawerA11yState( open && ! isDesktop() );
	}

	function restoreDrawerOpenerFocus() {
		window.requestAnimationFrame( function () {
			focusToggleWithoutScroll();
		} );
	}

	function closeDrawer( restoreFocus ) {
		var shouldRestore = restoreFocus !== false;

		if ( body.classList.contains( 'qrms-shell-sidebar-open' ) ) {
			setOpen( false );
		}

		if ( shouldRestore ) {
			restoreDrawerOpenerFocus();
		}
	}

	function openDrawer() {
		if ( isDesktop() ) {
			return;
		}

		if ( openAccountMenu ) {
			closeAccountMenu( false );
		}

		setOpen( true );
	}

	function onToggleClick() {
		if ( body.classList.contains( 'qrms-shell-sidebar-open' ) ) {
			closeDrawer( false );
		} else {
			openDrawer();
		}
	}

	function onBackdropClick() {
		closeDrawer( true );
	}

	function onDrawerCloseClick() {
		closeDrawer( true );
	}

	function onKeyDown( event ) {
		if ( 'Escape' !== event.key || ! body.classList.contains( 'qrms-shell-sidebar-open' ) ) {
			return;
		}

		event.preventDefault();
		closeDrawer( true );
	}

	function onViewportChange() {
		if ( isDesktop() ) {
			closeDrawer( false );
		}
	}

	function debouncedResize() {
		window.clearTimeout( resizeTimer );
		resizeTimer = window.setTimeout( onViewportChange, 120 );
	}

	toggle.addEventListener( 'click', onToggleClick );

	if ( drawerClose ) {
		drawerClose.addEventListener( 'click', onDrawerCloseClick );
	}

	if ( backdrop ) {
		backdrop.addEventListener( 'click', onBackdropClick );
	}

	if ( 'function' === typeof mqDesktop.addEventListener ) {
		mqDesktop.addEventListener( 'change', onViewportChange );
	} else if ( 'function' === typeof mqDesktop.addListener ) {
		mqDesktop.addListener( onViewportChange );
	}

	window.addEventListener( 'resize', debouncedResize, { passive: true } );

	document.addEventListener( 'touchmove', onDocumentTouchMove, { passive: false } );

	sidebar.addEventListener( 'click', function ( event ) {
		var link = event.target.closest( 'a.qrms-shell__nav-link' );
		if ( link && ! isDesktop() ) {
			closeDrawer( true );
		}
	} );

	var accountWraps = document.querySelectorAll( '.qrms-shell__account-wrap' );
	var openAccountMenu = null;

	function closeAccountMenu( restoreFocus ) {
		if ( ! openAccountMenu ) {
			return;
		}

		var trigger = openAccountMenu.trigger;
		var menu = openAccountMenu.menu;

		menu.hidden = true;
		trigger.setAttribute( 'aria-expanded', 'false' );
		openAccountMenu = null;

		if ( restoreFocus && trigger ) {
			window.requestAnimationFrame( function () {
				focusElementWithoutScroll( trigger );
			} );
		}
	}

	function openAccountMenuFor( wrap ) {
		var trigger = wrap.querySelector( '.qrms-shell__account-trigger' );
		var menu = wrap.querySelector( '.qrms-shell__account-menu' );

		if ( ! trigger || ! menu ) {
			return;
		}

		if ( openAccountMenu && openAccountMenu.wrap !== wrap ) {
			closeAccountMenu( false );
		}

		menu.hidden = false;
		trigger.setAttribute( 'aria-expanded', 'true' );
		openAccountMenu = { wrap: wrap, trigger: trigger, menu: menu };
	}

	function toggleAccountMenu( wrap ) {
		if ( body.classList.contains( 'qrms-shell-sidebar-open' ) && ! isDesktop() ) {
			return;
		}

		if ( openAccountMenu && openAccountMenu.wrap === wrap ) {
			closeAccountMenu( true );
			return;
		}

		openAccountMenuFor( wrap );
	}

	accountWraps.forEach( function ( wrap ) {
		var trigger = wrap.querySelector( '.qrms-shell__account-trigger' );
		var menu = wrap.querySelector( '.qrms-shell__account-menu' );

		if ( ! trigger || ! menu ) {
			return;
		}

		trigger.addEventListener( 'click', function ( event ) {
			event.stopPropagation();
			toggleAccountMenu( wrap );
		} );

		trigger.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key || ' ' === event.key ) {
				event.preventDefault();
				toggleAccountMenu( wrap );
			}
		} );

		menu.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				event.preventDefault();
				event.stopPropagation();
				closeAccountMenu( true );
			}
		} );
	} );

	document.addEventListener( 'click', function ( event ) {
		if ( ! openAccountMenu ) {
			return;
		}

		if ( openAccountMenu.wrap.contains( event.target ) ) {
			return;
		}

		closeAccountMenu( true );
	} );

	function onKeyDownWithAccount( event ) {
		if ( openAccountMenu && 'Escape' === event.key ) {
			event.preventDefault();
			event.stopPropagation();
			closeAccountMenu( true );
			return;
		}

		onKeyDown( event );
	}

	document.addEventListener( 'keydown', onKeyDownWithAccount );

	setOpen( false );
}() );
