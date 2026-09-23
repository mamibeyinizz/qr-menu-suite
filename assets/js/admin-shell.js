/**
 * QR Menu Suite — premium admin shell interactions (overview only).
 */
( function () {
	'use strict';

	var body = document.body;
	var sidebar = document.getElementById( 'qrms-shell-sidebar' );
	var backdrop = document.getElementById( 'qrms-shell-backdrop' );
	var toggle = document.getElementById( 'qrms-shell-menu-toggle' );

	if ( ! body || ! sidebar || ! toggle ) {
		return;
	}

	var mqDesktop = window.matchMedia( '(min-width: 1024px)' );
	var resizeTimer = null;
	var lockedScrollY = 0;
	var touchMoveBlocked = false;

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
			sidebar.setAttribute( 'aria-modal', 'true' );
			lockPageScroll();
		} else {
			sidebar.removeAttribute( 'aria-modal' );
			unlockPageScroll();
		}
	}

	function closeDrawer() {
		setOpen( false );
	}

	function openDrawer() {
		if ( isDesktop() ) {
			return;
		}
		setOpen( true );
	}

	function onToggleClick() {
		if ( body.classList.contains( 'qrms-shell-sidebar-open' ) ) {
			closeDrawer();
		} else {
			openDrawer();
		}
	}

	function onBackdropClick() {
		closeDrawer();
	}

	function onKeyDown( event ) {
		if ( 'Escape' === event.key && body.classList.contains( 'qrms-shell-sidebar-open' ) ) {
			closeDrawer();
			toggle.focus();
		}
	}

	function onViewportChange() {
		if ( isDesktop() ) {
			closeDrawer();
		}
	}

	function debouncedResize() {
		window.clearTimeout( resizeTimer );
		resizeTimer = window.setTimeout( onViewportChange, 120 );
	}

	toggle.addEventListener( 'click', onToggleClick );

	if ( backdrop ) {
		backdrop.addEventListener( 'click', onBackdropClick );
	}

	document.addEventListener( 'keydown', onKeyDown );

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
			closeDrawer();
		}
	} );

	setOpen( false );
}() );
