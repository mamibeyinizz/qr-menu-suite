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

	function isDesktop() {
		return mqDesktop.matches;
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
		} else {
			sidebar.removeAttribute( 'aria-modal' );
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

	sidebar.addEventListener( 'click', function ( event ) {
		var link = event.target.closest( 'a.qrms-shell__nav-link' );
		if ( link && ! isDesktop() ) {
			closeDrawer();
		}
	} );

	setOpen( false );
}() );
