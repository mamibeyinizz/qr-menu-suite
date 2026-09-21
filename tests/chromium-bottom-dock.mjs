/**
 * Headless Chromium: cart closed → chatbot visible; cart open → hidden;
 * cart closed again → visible. Also checks service-row chrome and overflow.
 *
 * Usage: node tests/chromium-bottom-dock.mjs
 */
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
let puppeteer;
try {
	( { default: puppeteer } = await import( 'puppeteer-core' ) );
} catch ( e ) {
	( { default: puppeteer } = await import( '/tmp/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js' ) );
}

const __dirname = path.dirname( fileURLToPath( import.meta.url ) );
const ROOT = path.resolve( __dirname, '..' );
const VIEWPORTS = [ 320, 375, 390, 430, 768, 1024, 1440 ];
const CHROME = process.env.CHROME || '/usr/bin/google-chrome-stable';

const MIME = {
	'.html': 'text/html; charset=utf-8',
	'.css': 'text/css; charset=utf-8',
	'.js': 'text/javascript; charset=utf-8',
	'.svg': 'image/svg+xml',
};

function serve() {
	return new Promise( ( resolve ) => {
		const server = http.createServer( ( req, res ) => {
			const urlPath = decodeURIComponent( ( req.url || '/' ).split( '?' )[ 0 ] );
			const file = path.join( ROOT, urlPath === '/' ? 'tests/fixtures/bottom-dock-ux.html' : urlPath );
			if ( ! file.startsWith( ROOT ) ) {
				res.writeHead( 403 );
				res.end();
				return;
			}
			fs.readFile( file, ( err, data ) => {
				if ( err ) {
					res.writeHead( 404 );
					res.end( 'not found ' + urlPath );
					return;
				}
				res.writeHead( 200, { 'Content-Type': MIME[ path.extname( file ) ] || 'application/octet-stream' } );
				res.end( data );
			} );
		} );
		server.listen( 0, '127.0.0.1', () => {
			resolve( { server, port: server.address().port } );
		} );
	} );
}

async function measure( page, phase ) {
	return page.evaluate( ( phaseName ) => {
		function vis( el ) {
			if ( ! el ) {
				return { shown: false, display: 'missing', w: 0, h: 0, top: 0, bottom: 0 };
			}
			const cs = getComputedStyle( el );
			const r = el.getBoundingClientRect();
			const shown = cs.display !== 'none' && cs.visibility !== 'hidden' && Number( cs.opacity ) > 0.01 && r.width > 0 && r.height > 0;
			return { shown, display: cs.display, visibility: cs.visibility, opacity: cs.opacity, w: r.width, h: r.height, top: r.top, bottom: r.bottom, left: r.left, right: r.right };
		}
		const fab = document.querySelector( '.gemini-chat-toggle-btn' );
		const wrap = document.querySelector( '.hfb-footer__call-wrap' );
		const garson = document.querySelector( '[data-qmo-cagri="garson"]' );
		const hesap = document.querySelector( '[data-qmo-cagri="hesap"]' );
		const bar = document.getElementById( 'qmo-bar' );
		const dr = document.getElementById( 'qmo-dr' );
		const cta = document.getElementById( 'last-cta' );
		const fabV = vis( fab );
		const wrapCs = getComputedStyle( wrap );
		const gCs = getComputedStyle( garson );
		const barV = vis( bar );
		const drV = vis( dr );
		const ctaR = cta.getBoundingClientRect();
		return {
			phase: phaseName,
			vw: window.innerWidth,
			overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
			fab: {
				...fabV,
				ariaHidden: fab.getAttribute( 'aria-hidden' ),
				tabindex: fab.getAttribute( 'tabindex' ),
				ariaLabel: fab.getAttribute( 'aria-label' ),
				focusable: fab.getAttribute( 'tabindex' ) !== '-1' && fabV.display !== 'none',
			},
			service: {
				wrapW: wrap.getBoundingClientRect().width,
				bg: wrapCs.backgroundColor,
				shadow: wrapCs.boxShadow,
				garson: {
					color: gCs.color,
					fontSize: gCs.fontSize,
					bg: gCs.backgroundColor,
					border: gCs.borderTopWidth,
					shadow: gCs.boxShadow,
					minH: parseFloat( gCs.minHeight ),
					h: garson.getBoundingClientRect().height,
				},
				hesapH: hesap.getBoundingClientRect().height,
			},
			cart: barV,
			drawer: { on: dr.classList.contains( 'qmo-on' ), ...drV },
			cta: { bottom: ctaR.bottom },
		};
	}, phase );
}

function isTransparent( color ) {
	if ( ! color || color === 'transparent' ) {
		return true;
	}
	const m = String( color ).match( /rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([0-9.]+))?/ );
	if ( ! m ) {
		return false;
	}
	if ( m[ 4 ] !== undefined && Number( m[ 4 ] ) === 0 ) {
		return true;
	}
	return Number( m[ 1 ] ) === 0 && Number( m[ 2 ] ) === 0 && Number( m[ 3 ] ) === 0 && m[ 4 ] !== undefined && Number( m[ 4 ] ) === 0;
}

function rgbOf( str ) {
	const m = String( str ).match( /rgba?\((\d+),\s*(\d+),\s*(\d+)/ );
	return m ? [ Number( m[ 1 ] ), Number( m[ 2 ] ), Number( m[ 3 ] ) ] : null;
}

function assert( cond, msg, errors ) {
	if ( ! cond ) {
		errors.push( msg );
	}
}

function checkClosed( m, errors ) {
	const p = `${m.vw}px ${m.phase}`;
	assert( m.fab.shown, `${p}: chatbot visible`, errors );
	assert( m.fab.ariaHidden !== 'true', `${p}: aria-hidden not true`, errors );
	assert( m.fab.tabindex !== '-1', `${p}: tabindex not -1`, errors );
	assert( m.fab.ariaLabel === 'Menü asistanını aç', `${p}: aria-label preserved`, errors );
	assert( m.fab.focusable, `${p}: chatbot focusable`, errors );
	assert( m.cart.shown, `${p}: cart bar visible`, errors );
	assert( ! m.drawer.on, `${p}: drawer not qmo-on`, errors );
	assert( ! m.overflow, `${p}: no horizontal overflow`, errors );
	assert( m.service.wrapW < m.vw - 8, `${p}: service wrap not full-width (${m.service.wrapW} vs ${m.vw})`, errors );
	assert( isTransparent( m.service.bg ), `${p}: wrap bg transparent (${m.service.bg})`, errors );
	assert( m.service.shadow === 'none', `${p}: wrap no shadow (${m.service.shadow})`, errors );
	assert( isTransparent( m.service.garson.bg ), `${p}: garson bg transparent (${m.service.garson.bg})`, errors );
	assert( m.service.garson.shadow === 'none', `${p}: garson no shadow`, errors );
	assert( parseFloat( m.service.garson.border ) === 0, `${p}: garson no border`, errors );
	assert( m.service.garson.minH >= 44, `${p}: garson min-height 44`, errors );
	assert( m.service.garson.h >= 44, `${p}: garson touch height ${m.service.garson.h}`, errors );
	assert( m.service.hesapH >= 44, `${p}: hesap touch height`, errors );
	const fs = parseFloat( m.service.garson.fontSize );
	assert( fs >= 13.5 && fs <= 16, `${p}: font-size 14–15px got ${m.service.garson.fontSize}`, errors );
	const rgb = rgbOf( m.service.garson.color );
	if ( rgb ) {
		assert( ! ( rgb[ 0 ] > 160 && rgb[ 1 ] > 130 && rgb[ 2 ] < 120 ), `${p}: garson color not gold (${m.service.garson.color})`, errors );
	}
	if ( m.vw >= 1440 ) {
		assert( m.service.wrapW <= 440, `${p}: service wrap not stretched (${m.service.wrapW})`, errors );
	}
	assert( m.cta.bottom < m.cart.top - 1, `${p}: last CTA (${m.cta.bottom}) above cart (top ${m.cart.top})`, errors );
}

function checkOpen( m, errors ) {
	const p = `${m.vw}px ${m.phase}`;
	assert( ! m.fab.shown, `${p}: chatbot hidden (display=${m.fab.display} w=${m.fab.w} h=${m.fab.h})`, errors );
	assert( m.fab.display === 'none', `${p}: display none`, errors );
	assert( m.fab.ariaHidden === 'true', `${p}: aria-hidden true`, errors );
	assert( m.fab.tabindex === '-1', `${p}: tabindex -1`, errors );
	assert( m.fab.ariaLabel === 'Menü asistanını aç', `${p}: aria-label preserved`, errors );
	assert( ! m.fab.focusable, `${p}: not focusable`, errors );
	assert( m.drawer.on, `${p}: drawer qmo-on`, errors );
	assert( ! m.overflow, `${p}: no overflow`, errors );
}

const errors = [];
const { server, port } = await serve();
const browser = await puppeteer.launch( {
	executablePath: CHROME,
	headless: true,
	args: [ '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage' ],
} );

try {
	for ( const w of VIEWPORTS ) {
		const page = await browser.newPage();
		await page.setViewport( { width: w, height: 844, deviceScaleFactor: 1 } );
		await page.goto( `http://127.0.0.1:${port}/tests/fixtures/bottom-dock-ux.html`, { waitUntil: 'networkidle0' } );
		await page.waitForSelector( '.gemini-chat-toggle-btn' );

		const closed1 = await measure( page, 'closed-1' );
		checkClosed( closed1, errors );

		await page.evaluate( () => {
			document.getElementById( 'qmo-ov' ).classList.add( 'qmo-on' );
			document.getElementById( 'qmo-dr' ).classList.add( 'qmo-on' );
		} );
		await new Promise( ( r ) => setTimeout( r, 100 ) );

		const open = await measure( page, 'open' );
		checkOpen( open, errors );

		await page.evaluate( () => {
			document.getElementById( 'qmo-ov' ).classList.remove( 'qmo-on' );
			document.getElementById( 'qmo-dr' ).classList.remove( 'qmo-on' );
		} );
		await new Promise( ( r ) => setTimeout( r, 100 ) );

		const closed2 = await measure( page, 'closed-2' );
		checkClosed( closed2, errors );

		await page.close();
		process.stdout.write( `${w}px  closed✓  open-hidden✓  closed-again✓\n` );
	}
} finally {
	await browser.close();
	server.close();
}

if ( errors.length ) {
	console.error( '\nFAILURES:\n' + errors.join( '\n' ) );
	process.exit( 1 );
}

console.log( `\nChromium bottom-dock UX: ${VIEWPORTS.length} viewports, 0 failures` );
