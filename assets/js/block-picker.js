/**
 * Settings-page block table (FW-156): the Show, Category and search filters, column sort and unsaved-changes state.
 *
 * Rows and strings are rendered by PHP (includes/helpers/core-block-picker.php); this filters
 * and sorts them, runs the on-demand content scan (includes/helpers/core-block-survey.php) and asks
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
	 * The blocks a save should ask about: newly enabled (not in the initial posted set) with a
	 * handled-by-GB tag count above zero from the last scan. Advisory only; the caller never blocks.
	 */
	function needsConfirm( initial, posted, counts ) {
		return posted.filter( function ( name ) {
			return initial.indexOf( name ) === -1 && ( counts[ name ] || 0 ) > 0;
		} );
	}

	/**
	 * Rows ordered by one key, as a new array. A null value sorts after every other value
	 * ascending (the Holds column's "Not tested"), so first descending. Ties keep their input
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

	/*
	 * The filter bar's rule. A view is { show, cat, query, scanned }; a row is { enabled, tags,
	 * cat, search, touched }. Show is one of default (enabled, or with tags once a scan exists),
	 * enabled, tags, all. Category narrows within Show; a non-empty search ignores Show but
	 * respects Category; a row ticked or unticked this visit stays until reload.
	 */
	function showValues( scanned ) {
		return scanned ? [ 'default', 'enabled', 'tags', 'all' ] : [ 'default', 'all' ];
	}

	/** A Show value the current scan state no longer offers falls back to default. */
	function normalizeShow( show, scanned ) {
		return showValues( scanned ).indexOf( show ) === -1 ? 'default' : show;
	}

	function hasQuery( view ) {
		return String( view.query ).trim() !== '';
	}

	// The default view: enabled, or (once a scan exists) with tags. The Show count and the row test share it.
	function inDefault( scanned, row ) {
		return row.enabled || ( scanned && row.tags );
	}

	function inShow( view, row ) {
		switch ( view.show ) {
			case 'all':
				return true;
			case 'enabled':
				return row.enabled;
			case 'tags':
				return row.tags;
		}
		return inDefault( view.scanned, row );
	}

	function isVisible( view, row ) {
		if ( view.cat && row.cat !== view.cat ) {
			return false;
		}
		if ( hasQuery( view ) ) {
			return matches( row.search, view.query );
		}
		return row.touched || inShow( view, row );
	}

	/** Site-wide Show counts: not narrowed by Category or search. */
	function showCounts( rows, scanned ) {
		var out = { default: 0, enabled: 0, tags: 0, all: rows.length };
		rows.forEach( function ( row ) {
			out.enabled += row.enabled ? 1 : 0;
			out.tags += row.tags ? 1 : 0;
			out.default += inDefault( scanned, row ) ? 1 : 0;
		} );
		return out;
	}

	function filtersActive( view ) {
		return 'default' !== view.show || '' !== view.cat || hasQuery( view );
	}

	/** Which empty state applies: '' (rows shown), 'filtered', or 'default' / 'default-unscanned' (the default view itself is empty). */
	function emptyKind( view, shown ) {
		if ( shown > 0 ) {
			return '';
		}
		if ( filtersActive( view ) ) {
			return 'filtered';
		}
		return view.scanned ? 'default' : 'default-unscanned';
	}

	/** "Showing N of M blocks.", plus the scan hint on the default, un-searched, un-scanned view. */
	function showingText( view, shown, total, strings ) {
		var text = strings.showing.replace( '%1$d', shown ).replace( '%2$d', total );
		return ! view.scanned && 'default' === view.show && ! hasQuery( view ) ? text + ' ' + strings.showing_hint : text;
	}

	if ( typeof window !== 'undefined' ) {
		window.bwsBlockPicker = {
			matches: matches, setKey: setKey, needsConfirm: needsConfirm, sortRows: sortRows,
			showValues: showValues, normalizeShow: normalizeShow, isVisible: isVisible, showCounts: showCounts,
			filtersActive: filtersActive, emptyKind: emptyKind, showingText: showingText
		};
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
		var catSelect = picker.querySelector( '.bws-block-picker__cat' );
		var showing = picker.querySelector( '.bws-block-picker__showing' );
		var resetBtn = picker.querySelector( '.bws-block-picker__reset' );
		var showRadios = Array.prototype.slice.call( picker.querySelectorAll( '.bws-block-picker__show input' ) );
		var dirtyNote = picker.querySelector( '.bws-block-picker__dirty' );
		var empty = picker.querySelector( '.bws-block-picker__empty' );
		var items = Array.prototype.slice.call( picker.querySelectorAll( '.bws-block-picker__item' ) );
		var groups = Array.prototype.slice.call( picker.querySelectorAll( '.bws-block-picker__group' ) );
		var boxes = Array.prototype.slice.call( picker.querySelectorAll( 'input[type="checkbox"]' ) );
		var status = picker.querySelector( '.bws-block-picker__survey-status' );
		var caption = picker.querySelector( 'caption' );
		var announce = picker.querySelector( '.bws-block-picker__sorted' );
		var sortButtons = Array.prototype.slice.call( picker.querySelectorAll( '.bws-block-picker__sort' ) );
		var sorted = null;
		var view = { show: 'default', cat: '', query: '', scanned: '1' === picker.getAttribute( 'data-scanned' ) };
		// Block names ticked or unticked this visit: their rows stay put until reload.
		var touched = {};

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

		function refreshDirty() {
			dirty = setKey( posted() ) !== initial;
			dirtyNote.hidden = ! dirty;
		}

		function rowState( li ) {
			return {
				enabled: li.querySelector( 'input[type="checkbox"]' ).checked,
				tags: found( li ) > 0,
				cat: li.parentNode.getAttribute( 'data-bws-cat' ),
				search: li.getAttribute( 'data-search' ),
				touched: !! touched[ li.getAttribute( 'data-bws-block' ) ]
			};
		}

		// Everything the filter bar shows, recomputed from the rows' own state: Show values and counts,
		// row and group visibility, "Showing N of M", Reset, the empty state.
		function refreshFilters() {
			view.scanned = '1' === picker.getAttribute( 'data-scanned' );
			view.show = normalizeShow( view.show, view.scanned );
			view.cat = catSelect.value;
			view.query = search.value;
			var rows = items.map( rowState );
			var shown = 0;
			items.forEach( function ( li, i ) {
				var show = isVisible( view, rows[ i ] );
				li.hidden = ! show;
				shown += show ? 1 : 0;
			} );
			groups.forEach( function ( g ) {
				g.hidden = ! g.querySelector( '.bws-block-picker__item:not([hidden])' );
			} );
			var offered = showValues( view.scanned );
			var counted = showCounts( rows, view.scanned );
			showRadios.forEach( function ( radio ) {
				var label = radio.parentNode;
				label.hidden = offered.indexOf( radio.value ) === -1;
				radio.checked = radio.value === view.show;
				label.querySelector( '.bws-block-picker__show-n' ).textContent = counted[ radio.value ];
			} );
			picker.querySelector( '.bws-block-picker__show-label--default' ).textContent = view.scanned ? cfg.strings.show_default : cfg.strings.show_default_unscanned;
			showing.textContent = showingText( view, shown, items.length, cfg.strings );
			resetBtn.hidden = ! filtersActive( view );
			var kind = emptyKind( view, shown );
			empty.hidden = '' === kind;
			Array.prototype.forEach.call( empty.children, function ( p ) {
				p.hidden = p.getAttribute( 'data-kind' ) !== kind;
			} );
		}

		// Where focus lands when the control that was pressed goes away: the checked Show radio.
		function focusShow() {
			var on = showRadios.filter( function ( r ) { return r.checked; } )[ 0 ];
			if ( on ) {
				on.focus();
			}
		}

		function resetFilters() {
			view.show = 'default';
			catSelect.value = '';
			search.value = '';
			refreshFilters();
			focusShow();
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

		search.addEventListener( 'input', refreshFilters );
		catSelect.addEventListener( 'change', refreshFilters );
		showRadios.forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				view.show = radio.value;
				refreshFilters();
			} );
		} );
		resetBtn.addEventListener( 'click', resetFilters );
		Array.prototype.forEach.call( empty.querySelectorAll( 'button' ), function ( btn ) {
			btn.addEventListener( 'click', function () {
				if ( btn.hasAttribute( 'data-reset' ) ) {
					resetFilters();
					return;
				}
				view.show = 'all';
				refreshFilters();
				focusShow();
			} );
		} );
		// Enter in the search box must filter, not submit the whole settings form.
		search.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' === e.key ) {
				e.preventDefault();
			}
		} );
		boxes.forEach( function ( b ) {
			b.addEventListener( 'change', function () {
				touched[ b.value ] = true;
				refreshFilters();
				refreshDirty();
			} );
		} );

		// A Handled by GB or Not handled by GB cell for a block the scan found nothing in: the zero bws_core_blocks_survey_count_html() renders. A block it did find tags in arrives as server-built HTML instead.
		function setZero( cell ) {
			cell.textContent = '';
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

		// Handled-by-GB tag counts from the last scan, read off the rows.
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
		// with a counted, newly enabled block shows it instead of submitting, and "Save anyway"
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
								var cell = li.querySelector( '[data-bws-count="' + kind + '"]' );
								if ( row ) {
									// Server-built and escaped: the markup the page loads with (bws_core_blocks_survey_count_html()).
									cell.innerHTML = row[ kind + '_html' ];
								} else {
									setZero( cell );
								}
							} );
						} );
						caption.textContent = cfg.strings.caption;
						applySort();
						status.textContent = res.data.status;
						scan.classList.replace( 'gp-button--primary', 'gp-button--secondary' );
						picker.setAttribute( 'data-scanned', '1' );
						refreshFilters();
						hideConfirm();
					} )
					.catch( function () {
						status.textContent = cfg.strings.error;
					} )
					.then( function () {
						scan.disabled = false;
						scan.textContent = idle;
						scan.focus();
					} );
			} );
		}
		window.addEventListener( 'beforeunload', function ( e ) {
			if ( dirty && ! submitting ) {
				e.preventDefault();
				e.returnValue = '';
			}
		} );

		refreshFilters();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
