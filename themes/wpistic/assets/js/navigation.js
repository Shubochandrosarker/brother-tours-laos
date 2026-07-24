/**
 * Front-end interactions: light/dark toggle, scrolled header, mobile menu,
 * cookie consent, reveal-on-scroll. Vanilla JS, no jQuery. Respects
 * prefers-reduced-motion via CSS.
 */
( function () {
	'use strict';

	var doc = document.documentElement;

	/* ---- Light / dark toggle (persisted in localStorage) ---- */
	var toggles = document.querySelectorAll( '[data-theme-toggle]' );
	if ( toggles.length ) {
		var applyTheme = function ( theme ) {
			doc.setAttribute( 'data-theme', theme );
			try {
				window.localStorage.setItem( 'wpistic-theme', theme );
			} catch ( e ) {}
		};
		Array.prototype.forEach.call( toggles, function ( btn ) {
			btn.addEventListener( 'click', function () {
				var current = doc.getAttribute( 'data-theme' ) === 'dark' ? 'dark' : 'light';
				applyTheme( current === 'dark' ? 'light' : 'dark' );
			} );
		} );
	}

	/* ---- Condense the header once the page scrolls ---- */
	var head = document.getElementById( 'site-head' );
	if ( head ) {
		var onScroll = function () {
			head.classList.toggle( 'scrolled', window.scrollY > 40 );
		};
		onScroll();
		window.addEventListener( 'scroll', onScroll, { passive: true } );
	}

	/* ---- Mobile menu ---- */
	var burger = document.querySelector( '.burger' );
	var mobile = document.getElementById( 'mobile-menu' );
	if ( burger && mobile ) {
		var setOpen = function ( open ) {
			burger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			mobile.classList.toggle( 'open', open );
			document.body.classList.toggle( 'nav-open', open );
		};
		burger.addEventListener( 'click', function () {
			setOpen( burger.getAttribute( 'aria-expanded' ) !== 'true' );
		} );
		mobile.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( 'a' ) ) {
				setOpen( false );
			}
		} );
		document.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' ) {
				setOpen( false );
			}
		} );
	}

	/* ---- Cookie consent — analytics (Phase 5) reads body.consent-granted ---- */
	var consent = document.getElementById( 'cookie-consent' );
	if ( consent ) {
		var stored = null;
		try {
			stored = window.localStorage.getItem( 'wpistic_consent' );
		} catch ( e ) {}
		if ( ! stored ) {
			consent.hidden = false;
		} else if ( 'accept' === stored ) {
			document.body.classList.add( 'consent-granted' );
		}
		consent.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-consent]' );
			if ( ! button ) {
				return;
			}
			var choice = button.getAttribute( 'data-consent' );
			try {
				window.localStorage.setItem( 'wpistic_consent', choice );
			} catch ( e ) {}
			document.body.classList.toggle( 'consent-granted', 'accept' === choice );
			consent.hidden = true;
		} );
	}

	/* ---- Reveal sections as they enter the viewport ---- */
	var reveals = document.querySelectorAll( '.reveal' );
	if ( 'IntersectionObserver' in window && reveals.length ) {
		var io = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					entry.target.classList.add( 'in' );
					io.unobserve( entry.target );
				}
			} );
		}, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' } );
		Array.prototype.forEach.call( reveals, function ( el ) {
			io.observe( el );
		} );
	} else {
		Array.prototype.forEach.call( reveals, function ( el ) {
			el.classList.add( 'in' );
		} );
	}
}() );
