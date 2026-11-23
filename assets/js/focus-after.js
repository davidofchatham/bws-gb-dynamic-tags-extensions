/**
 * Keyboard focus after a button that removes itself.
 *
 * An Add or Remove button in a repeater often unmounts as a result of being pressed: Add
 * stops at a maximum, Remove shows only while there is more than one entry, a link swaps
 * for the field it opens. The browser then drops focus on the page body, outside the tag
 * dialog, and the next Tab starts from the top of the editor. A control asks this helper to
 * put focus back once its write has rendered.
 *
 * THE CONVENTION. A control marks what can take focus with `data-bws-focus="<scope>:<name>"`:
 * the scope names one control (so two repeaters in one dialog never answer each other, and
 * the slot repeater's per-slot instances share one), the name one thing in it. A marked
 * wrapper hands focus to its first control, and a marked control takes it itself. A wrapper is
 * `display: contents`, so marking an entry leaves the flat-sibling layout of the option column
 * as it was. `data-bws-skip-focus` on a control (every Remove) keeps it from being picked as a
 * wrapper's first control, so focus never lands on the button that would repeat the removal.
 *
 * THE RULE, owned here. `candidates()` answers where focus goes, in order: after an Add, the
 * entry it created; after a Remove, the entry that took the removed one's place (the one
 * before it when the last went), else the Add button, else the control's first field. The
 * controls and the docs state only the consequence.
 *
 * WHEN A CANDIDATE IS TAKEN. The first name is the one wanted; the rest are fallbacks, tried
 * only once FALLBACK_FROM ticks have passed without it. A fallback taken on the first tick would
 * win over a wanted entry that has not mounted yet (on an Add, the first field is always already
 * on the page). A lookup that finds nothing in TRIES ticks gives up silently.
 *
 * Why a lookup by marker and not a ref held by the control: a slot repeater is one component
 * instance per slot, so the entry that should take focus after a Remove can live in an
 * instance other than the one whose button was pressed.
 *
 * `byId()` is the same wait for a control that already has an id (the table column header
 * the field-label action returns to), so there is one place that waits for a render.
 *
 * Where a real browser puts focus, and what a screen reader announces, is measured in the
 * testbed editor (docs/editor-controls.md); `tools/test/focus-after-test.js` holds the rule
 * and the lookup against a stub DOM.
 *
 * @since Unreleased
 * @package BWS_Dynamic_Tags
 */
( function () {
	'use strict';

	var FOCUSABLE = 'input:not([type="hidden"]),select,textarea,button,a[href],[tabindex]';
	// A write reaches the dialog through GenerateBlocks's own state, so the entry that should take
	// focus can mount a render or two after the click. Look every STEP ms, TRIES times.
	var STEP          = 50;
	var TRIES         = 12;
	var FALLBACK_FROM = 6;

	/**
	 * Where focus goes after an Add or a Remove, as marker names in the order to try them.
	 *
	 * @param {string} op    'add' or 'remove'.
	 * @param {number} index Add: the new entry's index. Remove: the removed entry's index.
	 * @param {number} count Remove: how many entries are left. Add: unused.
	 * @return {string[]} Names for `request()`, e.g. `entry-1`, `add`, `first`.
	 */
	function candidates( op, index, count ) {
		if ( 'add' === op ) {
			return [ 'entry-' + index, 'first' ];
		}
		var out = [];
		if ( count > 0 ) {
			out.push( 'entry-' + Math.min( index, count - 1 ) );
		}
		return out.concat( [ 'add', 'first' ] );
	}

	/** Whether a control can take focus: on the page, enabled, not skipped, not out of the tab order. */
	function usable( node ) {
		return ! node.disabled
			&& ! node.hasAttribute( 'data-bws-skip-focus' )
			&& '-1' !== node.getAttribute( 'tabindex' )
			&& null !== node.offsetParent;
	}

	/** The element itself if it is a usable control, else the first usable control it holds. */
	function firstControl( root ) {
		if ( root.matches( FOCUSABLE ) ) {
			return usable( root ) ? root : null;
		}
		var nodes = root.querySelectorAll( FOCUSABLE );
		for ( var i = 0; i < nodes.length; i++ ) {
			if ( usable( nodes[ i ] ) ) {
				return nodes[ i ];
			}
		}
		return null;
	}

	/** `first` is any marker of the scope, in page order; every other name is exact. */
	function selector( scope, name ) {
		var prefix = String( scope ).replace( /["\\]/g, '\\$&' ) + ':';
		return 'first' === name ? '[data-bws-focus^="' + prefix + '"]' : '[data-bws-focus="' + prefix + name + '"]';
	}

	/** Focus the first of `names` that is on the page. True once something took focus. */
	function attempt( scope, names ) {
		for ( var i = 0; i < names.length; i++ ) {
			var found = document.querySelectorAll( selector( scope, names[ i ] ) );
			for ( var j = 0; j < found.length; j++ ) {
				var control = firstControl( found[ j ] );
				if ( control ) {
					control.focus();
					return document.activeElement === control;
				}
			}
		}
		return false;
	}

	/** Run `fn( tick )` after STEP ms, and again every STEP ms until it says it is done or TRIES run out. */
	function later( fn ) {
		if ( 'undefined' === typeof document ) {
			return;
		}
		var tick = 0;
		function run() {
			if ( ! fn( tick ) && ++tick < TRIES ) {
				setTimeout( run, STEP );
			}
		}
		setTimeout( run, STEP );
	}

	var api = {
		candidates: candidates,

		/**
		 * Put focus on the first of `names` (`candidates()`'s) that mounts in `scope`, taking a
		 * fallback only once the wanted one (the first name) has had FALLBACK_FROM ticks to mount.
		 */
		request: function ( scope, names ) {
			later( function ( tick ) { return attempt( scope, tick < FALLBACK_FROM ? names.slice( 0, 1 ) : names ); } );
		},

		/** `request()` with the names `candidates()` gives for this press. */
		after: function ( scope, op, index, count ) {
			api.request( scope, candidates( op, index, count ) );
		},

		/** Put focus on the element with this id, once it is on the page. */
		byId: function ( id ) {
			later( function () {
				var node = document.getElementById( id );
				if ( ! node ) {
					return false;
				}
				node.focus();
				return document.activeElement === node;
			} );
		}
	};

	window.bwsFocusAfter = api;
}() );
