/**
 * Brother Tours — front-end behavior.
 *
 * Deliberately small. Motion and progressive enhancement only; nothing here is
 * required to read the site or submit a form.
 */
( function () {
	'use strict';

	/**
	 * Keep the mobile sticky action clear of the field being typed into.
	 *
	 * On a short mobile viewport the on-screen keyboard plus a fixed bottom bar
	 * can cover the input a guest is filling in. Rather than guessing at
	 * keyboard height, hide the bar whenever any form control holds focus.
	 */
	function stickyCtaFocusGuard() {
		var body = document.body;

		function isControl( el ) {
			return !! el && /^(INPUT|SELECT|TEXTAREA)$/.test( el.tagName );
		}

		document.addEventListener( 'focusin', function ( e ) {
			if ( isControl( e.target ) ) {
				body.classList.add( 'bt-form-focus' );
			}
		} );

		document.addEventListener( 'focusout', function ( e ) {
			if ( isControl( e.target ) ) {
				body.classList.remove( 'bt-form-focus' );
			}
		} );
	}

	/**
	 * Reveal-on-scroll for sections marked `.reveal`.
	 *
	 * Skipped entirely when the visitor prefers reduced motion, and skipped when
	 * IntersectionObserver is unavailable — in both cases the CSS fallback
	 * leaves content visible rather than stranding it at opacity 0.
	 */
	function reveal() {
		var reduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		var nodes   = document.querySelectorAll( '.reveal' );

		if ( ! nodes.length ) {
			return;
		}

		if ( reduced || ! ( 'IntersectionObserver' in window ) ) {
			Array.prototype.forEach.call( nodes, function ( n ) {
				n.classList.add( 'in' );
			} );
			return;
		}

		var observer = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						entry.target.classList.add( 'in' );
						observer.unobserve( entry.target );
					}
				} );
			},
			{ threshold: 0.12, rootMargin: '0px 0px -60px 0px' }
		);

		Array.prototype.forEach.call( nodes, function ( n ) {
			observer.observe( n );
		} );
	}

	function init() {
		stickyCtaFocusGuard();
		reveal();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
