/**
 * Serialization-order normalizer for BWS dynamic tags (FW-52).
 *
 * Decouples SERIALIZATION order (the saved tag string, left-to-right) from CONTROL
 * order (the editor panel, top-to-bottom). Control order is driven by PHP registration
 * order; this normalizer drives serialization order independently by rebuilding the whole
 * `extraTagParams` object in a canonical order inside `setState`:
 *
 *   format → source(per-slot, contiguous) → link → fallback
 *
 * - `format` (as, format tokens) LEADS the string for copy-visibility (the sole
 *   deliberate departure from GB's format-last convention).
 * - `link` sits AFTER source (source-relative — linkTo links the entity, linkKey reads
 *   a field off it), matching GB's own source → field → link chain.
 * - Each `N-` try_ slot's keys stay contiguous; slots ascend. Fixes the multi-slot
 *   reset-scatter where GB appends a late-added earlier-slot key globally last.
 *
 * MECHANISM: a per-tag invisible normalizer. GB fires the tagSpecificControls filter
 * once PER OPTION; we run the reorder exactly once per tag by acting only on the FIRST
 * option, and only when the tag is OURS (it carries at least one `bws-`-typed control —
 * GB core tags never do, so this is a zero-false-positive gate). The reorder is a
 * whole-object `setState`; a re-entrancy guard rewrites only when key-order changed, so
 * it round-trips GB serialization without a render loop (spike-proven 2026-06-06).
 *
 * This is a TRANSFORM, not a full re-sort: a STABLE sort by
 * (group_rank, slot, within_group_rank). Keys not in the canonical map keep their
 * incoming (GB insertion) order at the tail of the source group. The algorithm is a
 * faithful port of includes/helpers/serialization-order.php, which the harness
 * tools/test/serialization-order-test.php pins.
 *
 * @package BWS_Dynamic_Tags
 * @since   1.16.0
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.hooks || ! wp.element ) {
		return;
	}

	var useEffect = wp.element.useEffect;

	// --- Canonical order data (hardcoded — mirrors serialization-order.php; grill-2 #3) ---

	var GROUP_RANK = { format: 0, source: 1, link: 2, fallback: 3 };

	// bare option name → [group, within-group rank].
	var KEY_MAP = {
		// format (leads; global)
		as: [ 'format', 0 ],
		size: [ 'format', 1 ], // GB-reserved today (never in extraTagParams); harmless.
		// join tag-level assembly keys (share format group): mode → valueSep →
		// format. valueSep (renamed from sep, 1.16.0/FW-52) is a slot-value joiner,
		// distinct from the source-group list sep.
		mode: [ 'format', 2 ],
		valueSep: [ 'format', 3 ],
		format: [ 'format', 4 ],
		rangeSep: [ 'format', 5 ],
		timeSep: [ 'format', 6 ],
		showCurrentYear: [ 'format', 7 ],
		showMidnight: [ 'format', 8 ],
		// source (per-slot: src → ref → srcTermIn → limit → sep → use → key → datetime keys)
		src: [ 'source', 0 ],
		ref: [ 'source', 1 ],
		srcTermIn: [ 'source', 2 ],
		limit: [ 'source', 3 ],
		sep: [ 'source', 4 ],
		use: [ 'source', 5 ],
		key: [ 'source', 6 ],
		timeKey: [ 'source', 7 ],
		startKey: [ 'source', 8 ],
		startTimeKey: [ 'source', 9 ],
		endKey: [ 'source', 10 ],
		endTimeKey: [ 'source', 11 ],
		// link (after source; entity-link OR email/phone own-anchor set subject → noLink)
		linkTo: [ 'link', 0 ],
		linkKey: [ 'link', 1 ],
		newTab: [ 'link', 2 ],
		subject: [ 'link', 3 ],
		noLink: [ 'link', 4 ],
		// fallback (last)
		fallback: [ 'fallback', 0 ],
	};

	var UNKNOWN_WITHIN = 100; // unknown keys tail the source group.

	/**
	 * Split `N-name` into [slot, bareName]; unprefixed → [0, key].
	 */
	function parseSlot( key ) {
		var m = /^(\d+)-(.+)$/.exec( key );
		if ( m ) {
			return [ parseInt( m[ 1 ], 10 ), m[ 2 ] ];
		}
		return [ 0, key ];
	}

	/**
	 * Reorder a list of option keys into canonical serialization order.
	 * Pure: keys in → reordered keys out. Stable (incoming index breaks ties).
	 */
	function reorderKeys( keys ) {
		var decorated = keys.map( function ( key, idx ) {
			var parsed = parseSlot( key );
			var slot   = parsed[ 0 ];
			var bare   = parsed[ 1 ];
			var group, within;
			if ( KEY_MAP.hasOwnProperty( bare ) ) {
				group  = KEY_MAP[ bare ][ 0 ];
				within = KEY_MAP[ bare ][ 1 ];
			} else {
				group  = 'source';
				within = UNKNOWN_WITHIN;
			}
			return { key: key, grank: GROUP_RANK[ group ], slot: slot, within: within, idx: idx };
		} );

		decorated.sort( function ( a, b ) {
			return ( a.grank - b.grank )
				|| ( a.slot - b.slot )
				|| ( a.within - b.within )
				|| ( a.idx - b.idx );
		} );

		return decorated.map( function ( d ) { return d.key; } );
	}

	/**
	 * True when two key lists differ in order (same members assumed).
	 */
	function orderChanged( before, after ) {
		if ( before.length !== after.length ) { return true; }
		for ( var i = 0; i < before.length; i++ ) {
			if ( before[ i ] !== after[ i ] ) { return true; }
		}
		return false;
	}

	/**
	 * True when the tag is OURS — it declares at least one `bws-`-typed control.
	 * GB core tags never use `bws-` option types, so this never false-positives.
	 */
	function isBwsTag( allOptions ) {
		if ( ! allOptions ) { return false; }
		var names = Object.keys( allOptions );
		for ( var i = 0; i < names.length; i++ ) {
			var cfg = allOptions[ names[ i ] ];
			if ( cfg && typeof cfg.type === 'string' && cfg.type.indexOf( 'bws-' ) === 0 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Invisible normalizer control. Renders nothing; on mount / state change it
	 * rebuilds extraTagParams in canonical order if the order is off.
	 */
	function OrderNormalizer( props ) {
		var state    = props.state || {};
		var setState = props.setState;

		useEffect( function () {
			var keys       = Object.keys( state );
			if ( keys.length < 2 ) { return; }
			var reordered = reorderKeys( keys );
			if ( ! orderChanged( keys, reordered ) ) { return; }
			var next = {};
			reordered.forEach( function ( k ) { next[ k ] = state[ k ]; } );
			setState( next );
		} );

		return null;
	}

	// Fire once per tag: only on the FIRST option, only for our tags. Wrap (not replace)
	// the first option's element so its own control still renders.
	function normalizerFilter( element, allOptions, context ) {
		if ( ! element || ! allOptions || ! context ) { return element; }
		if ( ! isBwsTag( allOptions ) ) { return element; }

		var firstKey = Object.keys( allOptions )[ 0 ];
		if ( element.key !== firstKey ) { return element; }

		return wp.element.createElement(
			wp.element.Fragment,
			null,
			wp.element.createElement( OrderNormalizer, {
				key: 'bws-order-normalizer',
				state: context.state,
				setState: context.setState,
			} ),
			element
		);
	}

	wp.hooks.addFilter(
		'generateblocks.editor.tagSpecificControls',
		'bws/serialization-order-normalizer',
		normalizerFilter,
		// Priority 20: run after the composite/field controls have registered so the
		// first-option element we wrap is already its final rendered control.
		20
	);

	// Expose the pure reorder for any future harness / debugging (non-enumerable-ish).
	window.bwsReorderKeys = reorderKeys;

} )();
