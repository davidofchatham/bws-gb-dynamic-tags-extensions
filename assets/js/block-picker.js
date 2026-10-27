/**
 * Settings-page block table (FW-156): search, column sort, "N selected" counter and unsaved-changes state.
 *
 * Rows and strings are rendered by PHP (includes/helpers/core-block-picker.php); this filters,
 * sorts and counts them, runs the on-demand content scan (includes/helpers/core-block-survey.php) and asks
 * the one in-page Save confirmation. Dirty state looks at this list's own checkboxes, never the
 * rest of the settings form. The pure helpers are exposed on window.bwsBlockPicker so a node
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

	/**
	 * The blocks a save should ask about: newly selected (not in the initial posted set) with a
	 * registered-tag count above zero from the last scan. Advisory only; the caller never blocks.
	 */
	function needsConfirm( initial, posted, counts ) {
		return posted.filter( function ( name ) {
			return initial.indexOf( name ) === -1 && ( counts[ name ] || 0 ) > 0;
		} );
	}

	/** The scan-totals sentence; the "none" string when both are zero. */
	function totalsText( registered, other, strings ) {
		if ( ! registered && ! other ) {
			return strings.none;
		}
		return strings.totals.replace( '%1$d', registered ).replace( '%2$d', other );
	}

	/**
	 * Rows ordered by one key, as a new array. A null value sorts after every other value
	 * ascending (the Contents column's "Not tested"), so first descending. Ties keep their input
	 * order in both directions, never reversed.
	 */
	function sortRows( rows, key, dir ) {
		var sign = 'descending' === dir ? -1 : 1;
		function order( x, y ) {
			if ( x === y ) {
				return 0;
			}
			if ( null === x || null === y ) {
				return null === x ? 1 : -1;
			}
			return 'string' === typeof x ? x.localeCompare( y ) : x - y;
		}
		return rows
			.map( function ( row, index ) { return { row: row, index: index }; } )
			.sort( function ( a, b ) { return order( a.row[ key ], b.row[ key ] ) * sign || a.index - b.index; } )
			.map( function ( entry ) { return entry.row; } );
	}

	if ( typeof window !== 'undefined' ) {
		window.bwsBlockPicker = { matches: matches, setKey: setKey, needsConfirm: needsConfirm, totalsText: totalsText, sortRows: sortRows };
	}
	if ( typeof document === 'undefined' ) {
		return;
	}

	function init() {
		var picker = document.querySelector( '.bws-block-picker' );
		if ( ! picker ) {
			return;
		}
		var cfg = window.bwsBlockPickerConfig || {};
		var search = picker.querySelector( '.bws-block-picker__search' );
		var count = picker.querySelector( '.bws-block-picker__count' );
		var dirtyNote = picker.querySelector( '.bws-block-picker__dirty' );
		var empty = picker.querySelector( '.bws-block-picker__empty' );
		var items = Array.prototype.slice.call( picker.querySelectorAll( '.bws-block-picker__item' ) );
		var groups = Array.prototype.slice.call( picker.querySelectorAll( '.bws-block-picker__group' ) );
		var boxes = Array.prototype.slice.call( picker.querySelectorAll( 'input[type="checkbox"]' ) );
		var filterBtn = picker.querySelector( '.bws-block-picker__filter' );
		var when = picker.querySelector( '.bws-block-picker__survey-when' );
		var totals = picker.querySelector( '.bws-block-picker__survey-totals' );
		var caption = picker.querySelector( 'caption' );
		var announce = picker.querySelector( '.bws-block-picker__sorted' );
		var sortButtons = Array.prototype.slice.call( picker.querySelectorAll( '.bws-block-picker__sort' ) );
		var sorted = null;
		var filterOn = false;

		// Tags the last scan found in a row, of either kind.
		function found( li ) {
			return ( parseInt( li.getAttribute( 'data-bws-registered' ), 10 ) || 0 ) + ( parseInt( li.getAttribute( 'data-bws-other' ), 10 ) || 0 );
		}

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
				var show = matches( li.getAttribute( 'data-search' ), search.value ) && ( ! filterOn || found( li ) > 0 );
				li.hidden = ! show;
				shown += show ? 1 : 0;
			} );
			groups.forEach( function ( g ) {
				g.hidden = ! g.querySelector( '.bws-block-picker__item:not([hidden])' );
			} );
			empty.hidden = shown > 0;
		}

		// The scan-totals line counts the rows below it, so it reads off the same attributes the filter does.
		function refreshTotals() {
			if ( '1' !== picker.getAttribute( 'data-scanned' ) ) {
				totals.textContent = '';
				return;
			}
			var registered = 0;
			var other = 0;
			items.forEach( function ( li ) {
				registered += parseInt( li.getAttribute( 'data-bws-registered' ), 10 ) || 0;
				other += parseInt( li.getAttribute( 'data-bws-other' ), 10 ) || 0;
			} );
			totals.textContent = totalsText( registered, other, cfg.strings );
		}

		function rowOf( li ) {
			return {
				el: li,
				block: ( li.getAttribute( 'data-bws-title' ) || '' ).toLowerCase(),
				contents: li.getAttribute( 'data-bws-contents' ) || null,
				registered: parseInt( li.getAttribute( 'data-bws-registered' ), 10 ) || 0,
				unregistered: parseInt( li.getAttribute( 'data-bws-other' ), 10 ) || 0
			};
		}

		// Sort within each category (a tbody), leaving hidden rows in the DOM so their checkboxes still post.
		function applySort() {
			if ( ! sorted ) {
				return;
			}
			groups.forEach( function ( g ) {
				var rows = Array.prototype.slice.call( g.querySelectorAll( '.bws-block-picker__item' ) ).map( rowOf );
				sortRows( rows, sorted.col, sorted.dir ).forEach( function ( row ) { g.appendChild( row.el ); } );
			} );
		}

		sortButtons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var col = btn.getAttribute( 'data-col' );
				var dir = sorted && sorted.col === col && 'ascending' === sorted.dir ? 'descending' : 'ascending';
				sorted = { col: col, dir: dir };
				sortButtons.forEach( function ( other ) {
					var th = other.parentNode;
					if ( other === btn ) {
						th.setAttribute( 'aria-sort', dir );
					} else {
						th.removeAttribute( 'aria-sort' );
					}
				} );
				applySort();
				announce.textContent = cfg.strings.sorted.replace( '%1$s', btn.textContent ).replace( '%2$s', cfg.strings.order[ dir ] );
			} );
		} );

		search.addEventListener( 'input', refreshSearch );
		if ( filterBtn ) {
			filterBtn.addEventListener( 'click', function () {
				filterOn = ! filterOn;
				filterBtn.setAttribute( 'aria-pressed', filterOn ? 'true' : 'false' );
				refreshSearch();
			} );
		}
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

		// A Known or Unknown cell after a scan; the same shape bws_core_blocks_survey_count_html() renders.
		function setCount( cell, n ) {
			cell.textContent = '';
			if ( n ) {
				cell.textContent = n;
				return;
			}
			var zero = document.createElement( 'span' );
			zero.className = 'bws-block-picker__zero';
			zero.textContent = '0';
			cell.appendChild( zero );
		}

		var initialNames = posted();
		var confirmBox = picker.querySelector( '.bws-block-picker__confirm' );
		var confirmList = picker.querySelector( '.bws-block-picker__confirm-list' );
		var confirmed = false;

		function itemFor( name ) {
			return items.filter( function ( li ) { return li.getAttribute( 'data-bws-block' ) === name; } )[ 0 ];
		}

		// Registered-tag counts from the last scan, read off the rows.
		function counts() {
			var out = {};
			items.forEach( function ( li ) {
				out[ li.getAttribute( 'data-bws-block' ) ] = parseInt( li.getAttribute( 'data-bws-registered' ), 10 ) || 0;
			} );
			return out;
		}

		function hideConfirm() {
			confirmed = false;
			if ( confirmBox ) {
				confirmBox.hidden = true;
			}
		}

		// The Save confirmation is an in-page notice, not a blocking dialog: the first Save click
		// with a counted, newly selected block shows it instead of submitting, and "Save anyway"
		// (or any Save click after changing nothing) goes through. Advisory, never a gate.
		function showConfirm( names ) {
			confirmList.textContent = '';
			names.forEach( function ( name ) {
				var li = itemFor( name );
				var item = document.createElement( 'li' );
				item.textContent = ( li.getAttribute( 'data-bws-title' ) || name ) + ' (' + counts()[ name ] + ')';
				confirmList.appendChild( item );
			} );
			confirmBox.hidden = false;
			confirmBox.scrollIntoView( { block: 'nearest' } );
			confirmBox.querySelector( '.bws-block-picker__confirm-save' ).focus();
		}

		var form = document.getElementById( 'bws-settings-form' );
		if ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				var pending = confirmBox && ! confirmed ? needsConfirm( initialNames, posted(), counts() ) : [];
				if ( pending.length ) {
					e.preventDefault();
					showConfirm( pending );
					return;
				}
				submitting = true;
			} );
		}
		if ( confirmBox ) {
			confirmBox.querySelector( '.bws-block-picker__confirm-save' ).addEventListener( 'click', function () {
				confirmed = true;
				if ( form.requestSubmit ) {
					form.requestSubmit();
				} else {
					submitting = true;
					form.submit();
				}
			} );
			confirmBox.querySelector( '.bws-block-picker__confirm-back' ).addEventListener( 'click', function () {
				hideConfirm();
				search.focus();
			} );
			boxes.forEach( function ( b ) { b.addEventListener( 'change', hideConfirm ); } );
		}

		// The on-demand scan: runs server-side, then refreshes each row's counts in place so
		// unsaved selections survive it.
		var scan = picker.querySelector( '.bws-block-picker__scan' );
		if ( scan && cfg.ajaxUrl ) {
			scan.addEventListener( 'click', function () {
				var idle = scan.textContent;
				scan.disabled = true;
				scan.textContent = cfg.strings.running;
				var body = new URLSearchParams( { action: 'bws_core_blocks_survey', nonce: cfg.nonce } );
				fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						if ( ! res || ! res.success ) {
							throw new Error( 'scan' );
						}
						items.forEach( function ( li ) {
							var row = res.data.rows[ li.getAttribute( 'data-bws-block' ) ];
							li.setAttribute( 'data-bws-registered', row ? row.registered : 0 );
							li.setAttribute( 'data-bws-other', row ? row.unregistered : 0 );
							[ 'registered', 'unregistered' ].forEach( function ( kind ) {
								setCount( li.querySelector( '[data-bws-count="' + kind + '"]' ), row ? row[ kind ] : 0 );
							} );
						} );
						caption.textContent = cfg.strings.caption;
						applySort();
						when.textContent = res.data.status;
						picker.setAttribute( 'data-scanned', '1' );
						if ( filterBtn ) {
							filterBtn.disabled = false;
						}
						refreshTotals();
						refreshSearch();
						hideConfirm();
					} )
					.catch( function () {
						when.textContent = cfg.strings.error;
						totals.textContent = '';
					} )
					.then( function () {
						scan.disabled = false;
						scan.textContent = idle;
					} );
			} );
		}
		window.addEventListener( 'beforeunload', function ( e ) {
			if ( dirty && ! submitting ) {
				e.preventDefault();
				e.returnValue = '';
			}
		} );

		refreshCount();
		refreshTotals();
		refreshSearch();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
