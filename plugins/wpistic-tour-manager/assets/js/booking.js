/**
 * Booking / inquiry form submit → REST capture endpoint. Vanilla JS, no jQuery.
 */
( function () {
	'use strict';

	var config = window.wpisticTM || {};

	function handle( form ) {
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			var status = form.querySelector( '.wpistic-form-status' );
			var button = form.querySelector( 'button[type="submit"]' );
			var data = {};
			new FormData( form ).forEach( function ( value, key ) {
				data[ key ] = value;
			} );

			if ( button ) {
				button.disabled = true;
			}
			if ( status ) {
				status.textContent = '';
				status.className = 'wpistic-form-status';
			}

			var endpoint = ( form.getAttribute( 'data-type' ) === 'newsletter' && config.newsletterUrl )
				? config.newsletterUrl
				: config.restUrl;

			fetch( endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
				body: JSON.stringify( data )
			} )
				.then( function ( response ) {
					return response.json().then( function ( json ) {
						return { ok: response.ok, json: json };
					} );
				} )
				.then( function ( result ) {
					if ( result.ok ) {
						form.reset();
						if ( status ) {
							status.textContent = ( result.json && result.json.message ) || 'Thank you — we reply within 24 hours.';
							status.className = 'wpistic-form-status success';
						}
					} else if ( status ) {
						status.textContent = ( result.json && result.json.message ) || 'Please check your details and try again.';
						status.className = 'wpistic-form-status error';
					}
				} )
				.catch( function () {
					if ( status ) {
						status.textContent = 'Network error. Please try again.';
						status.className = 'wpistic-form-status error';
					}
				} )
				.finally( function () {
					if ( button ) {
						button.disabled = false;
					}
				} );
		} );
	}

	document.querySelectorAll( '.wpistic-booking-form' ).forEach( handle );
}() );
