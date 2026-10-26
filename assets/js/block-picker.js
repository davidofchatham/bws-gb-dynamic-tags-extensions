/**
 * Settings-page block list (FW-156): search, "N selected" counter and unsaved-changes state.
 *
 * Rows and strings are rendered by PHP (includes/helpers/core-block-picker.php); this only
 * filters and counts them. Dirty state looks at this list's own checkboxes, never the rest of
 * the settings form. The two pure helpers are exposed on window.bwsBlockPicker so a node
 * harness (tools/test/core-block-picker-test.js) can reach them.
 *
 * @package BWS_Dynamic_Tags
 * @since Unreleased
 */
( function () {
	'use strict';

	/** Every whitespace-separated term of the query appears in the lowercase haystack. */
	function matches( haystack, query ) {
		var terms = String( query ).toLowerCase().split( /\s+/ ).filter( Boolean );
		return terms.every( function ( term ) {
			return String( haystack ).indexOf( term ) !== -1;
		} );
	}

	/** Order-independent identity of a set of block names. */
	function setKey( names ) {
		return names.slice().sort().join( '|' );
	}

	if ( typeof window !== 'undefined' ) {
		window.bwsBlockPicker = { matches: matches, setKey: setKey };
	}
	if ( typeof document === 'undefined' ) {
		return;
	}

	function init() {
		var picker = document.querySelector( '.bws-block-picker' );
		if ( ! picker ) {
			return;
		}
		var search = picker.querySelector( '.bws-block-picker__search' );
		var count = picker.querySelector( '.bws-block-picker__count' );
		var dirtyNote = picker.querySelector( '.bws-block-picker__dirty' );
		var empty = picker.querySelector( '.bws-block-picker__empty' );
		var items = Array.prototype.slice.call( picker.querySelectorAll( '.bws-block-picker__item' ) );
		var groups = Array.prototype.slice.call( picker.querySelectorAll( '.bws-block-picker__group' ) );
		var boxes = Array.prototype.slice.call( picker.querySelectorAll( 'input[type="checkbox"]' ) );

		// What a save would send: checked and not locked. Locked rows post nothing.
		function posted() {
			return boxes.filter( function ( b ) { return b.checked && ! b.disabled; } ).map( function ( b ) { return b.value; } );
		}
		var initial = setKey( posted() );
		var dirty = false;
		var submitting = false;

		function refreshCount() {
			var n = boxes.filter( function ( b ) { return b.checked; } ).length;
			count.textContent = ( picker.getAttribute( 'data-count-template' ) || '%d' ).replace( '%d', n );
		}

		function refreshDirty() {
			dirty = setKey( posted() ) !== initial;
			dirtyNote.hidden = ! dirty;
		}

		function refreshSearch() {
			var shown = 0;
			items.forEach( function ( li ) {
				var show = matches( li.getAttribute( 'data-search' ), search.value );
				li.hidden = ! show;
				shown += show ? 1 : 0;
			} );
			groups.forEach( function ( g ) {
				g.hidden = ! g.querySelector( '.bws-block-picker__item:not([hidden])' );
			} );
			empty.hidden = shown > 0;
		}

		search.addEventListener( 'input', refreshSearch );
		// Enter in the search box must filter, not submit the whole settings form.
		search.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' === e.key ) {
				e.preventDefault();
			}
		} );
		boxes.forEach( function ( b ) {
			b.addEventListener( 'change', function () {
				refreshCount();
				refreshDirty();
			} );
		} );

		var form = document.getElementById( 'bws-settings-form' );
		if ( form ) {
			form.addEventListener( 'submit', function () { submitting = true; } );
		}
		window.addEventListener( 'beforeunload', function ( e ) {
			if ( dirty && ! submitting ) {
				e.preventDefault();
				e.returnValue = '';
			}
		} );

		refreshCount();
		refreshSearch();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
