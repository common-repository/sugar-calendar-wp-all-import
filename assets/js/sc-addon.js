/* global sc_vars */
jQuery( document ).ready( function ( $ ) {
    'use strict';

	// When a section nav item is clicked.
	$( '.section-nav li a' ).on( 'click',
		function( j ) {

			// Prevent the default browser action when a link is clicked.
			j.preventDefault();

			// Get the `href` attribute of the item.
			var them  = $( this ),
				href  = them.attr( 'href' ),
				rents = them.parents( '.sc-vertical-sections' );

			// Hide all section content.
			rents.find( '.section-content' ).hide();

			// Find the section content that matches the section nav item and show it.
			rents.find( href ).show();

			// Set the `aria-selected` attribute to false for all section nav items.
			rents.find( '.section-title' ).attr( 'aria-selected', 'false' );

			// Set the `aria-selected` attribute to true for this section nav item.
			them.parent().attr( 'aria-selected', 'true' );

			// Copy the current section item title to the box header.
			$( '.which-section' ).text( them.text() );
		}
	); // click()
} );
