/**
 * Admin repeaters (itinerary, FAQ). Vanilla JS.
 */
( function () {
	'use strict';

	function init( repeater ) {
		var rows = repeater.querySelector( '.wpistic-rows' );
		var template = repeater.querySelector( '.wpistic-row-template' );
		var addButton = repeater.querySelector( '.wpistic-add' );

		if ( addButton && rows && template ) {
			addButton.addEventListener( 'click', function () {
				var clone = template.cloneNode( true );
				clone.classList.remove( 'wpistic-row-template' );
				clone.removeAttribute( 'hidden' );
				clone.querySelectorAll( 'input, textarea, select' ).forEach( function ( field ) {
					field.disabled = false;
				} );
				rows.appendChild( clone );
			} );
		}

		repeater.addEventListener( 'click', function ( event ) {
			var remove = event.target.closest( '.wpistic-remove' );
			if ( remove ) {
				event.preventDefault();
				var row = remove.closest( '.wpistic-row' );
				if ( row && row.parentNode ) {
					row.parentNode.removeChild( row );
				}
			}
		} );
	}

	document.querySelectorAll( '.wpistic-repeater' ).forEach( init );
}() );
