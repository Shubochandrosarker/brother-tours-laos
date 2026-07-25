/**
 * Tour Manager admin dashboard — vanilla JS, no framework/CDN dependency.
 *
 * Two responsibilities:
 *   1. The light/dark/auto theme toggle: persists the choice server-side via
 *      AdminAssets::ajax_set_theme() (user_meta, not localStorage -- wp-admin
 *      pages are never statically cached, so the server already renders the
 *      correct data-theme attribute on load; this only handles the click).
 *   2. The bookings list "select all" checkbox for bulk actions.
 *
 * wpisticTmDashboard is localized by AdminAssets::enqueue().
 */
( function () {
	'use strict';

	/* ---- Theme toggle ---- */
	var toggles = document.querySelectorAll( '.wpistic-tm-theme-btn' );
	if ( toggles.length && window.wpisticTmDashboard ) {
		var cfg = window.wpisticTmDashboard;

		Array.prototype.forEach.call( toggles, function ( btn ) {
			btn.addEventListener( 'click', function () {
				var choice = btn.getAttribute( 'data-theme-choice' );
				if ( ! choice ) {
					return;
				}

				// Optimistic UI: flip the wrapper's data-theme and the
				// buttons' aria-pressed state immediately, then persist.
				var wrap = document.querySelector( '.wpistic-tm-dashboard' );
				if ( wrap ) {
					if ( 'auto' === choice ) {
						wrap.removeAttribute( 'data-theme' );
					} else {
						wrap.setAttribute( 'data-theme', choice );
					}
				}
				Array.prototype.forEach.call( toggles, function ( other ) {
					other.setAttribute( 'aria-pressed', other === btn ? 'true' : 'false' );
				} );

				var body = new FormData();
				body.append( 'action', cfg.action );
				body.append( 'nonce', cfg.nonce );
				body.append( 'theme', choice );

				fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } ).catch( function () {
					// Network failure: the optimistic UI already applied, and
					// the next full page load will fall back to whatever the
					// server still has saved -- no user-facing error needed
					// for a preference toggle.
				} );
			} );
		} );
	}

	/* ---- Bulk-select-all checkbox on the bookings list ---- */
	var selectAll = document.getElementById( 'wpistic-tm-select-all' );
	if ( selectAll ) {
		selectAll.addEventListener( 'change', function () {
			var boxes = document.querySelectorAll( '#wpistic-tm-bookings-table input[name="ids[]"]' );
			Array.prototype.forEach.call( boxes, function ( box ) {
				box.checked = selectAll.checked;
			} );
		} );
	}
} )();
