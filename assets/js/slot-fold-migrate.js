/**
 * Mount-time fold migration — the JS TWIN of the pure layer in
 * includes/helpers/slot-fold-migrate.php, plus the invisible control that applies it.
 *
 * THE SECOND MIGRATION PATH, and NOT a subset of the first. The converter's scanner
 * reads `wpdb->posts.post_content` only, so block widgets (the `widget_block` option)
 * are reachable ONLY here — an author who opens a widget's tag modal is the only event
 * that can migrate it. Conversely the scanner reaches drafts and templates nobody
 * opens. Both paths run the SAME rules, which is why this file is a twin rather than a
 * second implementation: `bwsSlotFold.foldFromLegacy` (the grammar owner's twin) makes
 * every legacy→folded decision, and what remains here is the wire-level adapter —
 * strip, emit, canonicalize — mirroring bws_fold_migrate_slots().
 *
 * NO TAG-NAME TABLE HERE. The PHP migrator matches by tag name because
 * MigrationRegistry does; the editor instead reads the `fold` config off the option
 * definition GB hands to the filter, so a container's parameters — including which
 * legacy axes are per-slot at all (`legacyAxes`) — arrive DERIVED from registration.
 * Nothing in this file knows that `try_text` exists.
 *
 * MODAL-CONFIRM BOUNDARY. `setState` writes the modal's draft state, so a mount
 * migration only reaches stored content when the author confirms the tag. Cancelling
 * leaves the old wire untouched, and the render dual-read keeps resolving it.
 *
 * A FUNCTION UPDATER, deliberately: the FW-52 order normalizer wraps a different
 * element on the same tag and also rewrites the whole object, so two whole-object
 * writes can land in one React batch. Composing off `prev` means neither transform is
 * lost, and returning `prev` unchanged is what stops the effect re-rendering forever.
 *
 * @package BWS_Dynamic_Tags
 * @since   1.17.0
 */
( function () {
	'use strict';

	// Hard dependency on the grammar twin: it owns every rule. A fallback would apply
	// some other mapping to author-facing wire, which is worse than not migrating.
	if ( ! window.bwsSlotFold ) {
		return;
	}

	var fold = window.bwsSlotFold;

	/**
	 * The legacy per-slot axes this container owns.
	 *
	 * PHP-DERIVED (`legacyAxes`, from bws_fold_slot_legacy_axes). The fallback is the
	 * full set, matching an unfiltered container: an absent list is a REGISTRATION bug,
	 * and folding too much is visible immediately, whereas folding nothing looks like
	 * "no legacy wire here" and silently strands it.
	 *
	 * @param {Object} conf Fold config.
	 * @return {Array} Axis names.
	 */
	function slotAxes( conf ) {
		var axes = conf && conf.legacyAxes;
		return ( axes && axes.length ) ? axes : [ 'src', 'ref', 'srcTermIn', 'use', 'key', 'limit' ];
	}

	/** Legacy option keys for slot n (bare at slot 1, `N-` beyond). */
	function legacyKeys( conf, n ) {
		var prefix = ( 1 === n ) ? '' : String( n ) + '-';
		return slotAxes( conf ).map( function ( axis ) {
			return prefix + axis;
		} );
	}

	/** Every legacy key the container owns, across all slots. */
	function slotKeys( conf ) {
		var keys = [];
		for ( var n = 1; n <= ( conf.max || 5 ); n++ ) {
			keys = keys.concat( legacyKeys( conf, n ) );
		}
		return keys;
	}

	/**
	 * The subset of state the legacy mapper may see.
	 *
	 * The filter is the POINT, not a tidy-up: a TAG-level `limit` on a try_ list template
	 * and a TAG-level `key` on a try_datetime_single are spelled exactly like slot 1's
	 * axes. Handing them to the mapper folds them into slot 1 and deletes the option the
	 * resolver actually reads.
	 *
	 * @param {Object} state Whole option map.
	 * @param {Object} conf  Fold config.
	 * @return {Object} Filtered map.
	 */
	function slotState( state, conf ) {
		var out = {};
		slotKeys( conf ).forEach( function ( key ) {
			if ( Object.prototype.hasOwnProperty.call( state, key ) ) {
				out[ key ] = state[ key ];
			}
		} );
		return out;
	}

	/**
	 * Fold a tag's legacy flat slot keys into folded `{N}` values.
	 *
	 * PURE — state in, state out (or null when there is nothing to migrate), so the twin
	 * harness drives it with plain objects. Mirrors bws_fold_migrate_slots(); the three
	 * rules that mirror are the ones a reader would otherwise re-derive:
	 *   - a TAG-level key is invisible, at EVERY slot position;
	 *   - an ALREADY-FOLDED slot wins, and its legacy siblings are dropped rather than
	 *     merged (merging invents a configuration neither side wrote);
	 *   - a slot that maps to NOTHING still loses its legacy keys (the FW-51 shape, which
	 *     the shipped resolver already discards).
	 *
	 * @param {Object} state Whole option map.
	 * @param {Object} conf  Fold config.
	 * @return {Object|null} Rewritten map, or null.
	 */
	function migrateSlots( state, conf ) {
		var src = slotState( state, conf );
		var next = {};
		var touched = false;
		var n;

		Object.keys( state ).forEach( function ( key ) {
			next[ key ] = state[ key ];
		} );

		for ( n = 1; n <= ( conf.max || 5 ); n++ ) {
			var present = legacyKeys( conf, n ).filter( function ( key ) {
				return Object.prototype.hasOwnProperty.call( src, key );
			} );
			if ( ! present.length ) {
				continue;
			}
			touched = true;
			present.forEach( function ( key ) {
				delete next[ key ];
			} );

			// An already-folded value for this slot wins outright.
			if ( '' !== String( state[ fold.slotKey( n ) ] || '' ).trim() ) {
				continue;
			}

			var rec = fold.foldFromLegacy( n, src, !! conf.combining, false !== conf.perSlotUse );
			if ( ! rec || ! rec.slot ) {
				continue;
			}
			var wire = fold.emitSlot( rec.slot );
			if ( '' !== wire ) {
				next[ fold.slotKey( n ) ] = wire;
			}
		}

		if ( ! touched ) {
			return null;
		}

		// Canonical key order, through the FW-52 normalizer's own sort so there is no
		// second copy of the ranks. The two migration paths must not write one tag two
		// ways, so the converter half canonicalizes through the PHP mirror of the same
		// algorithm.
		var keys = Object.keys( next );
		if ( 'function' === typeof window.bwsReorderKeys ) {
			keys = window.bwsReorderKeys( keys );
		}
		var ordered = {};
		keys.forEach( function ( key ) {
			ordered[ key ] = next[ key ];
		} );
		return ordered;
	}

	window.bwsSlotFoldMigrate = {
		slotAxes: slotAxes,
		legacyKeys: legacyKeys,
		slotKeys: slotKeys,
		slotState: slotState,
		migrateSlots: migrateSlots
	};

	// ── The mount control ───────────────────────────────────────────────────
	// Everything below is editor wiring; the rules are above. Bails out entirely
	// without the editor globals so the pure layer still loads for the harness.

	if ( ! window.wp || ! wp.hooks || ! wp.element ) {
		return;
	}

	var useEffect = wp.element.useEffect;

	/** Renders nothing; folds this tag's legacy slot keys on mount. */
	function MountMigrator( props ) {
		var setState = props.setState;
		var conf = props.conf;

		useEffect( function () {
			setState( function ( prev ) {
				var migrated = migrateSlots( prev || {}, conf );
				// Returning the SAME reference is the loop guard: React bails out, so a
				// tag with nothing to migrate re-renders zero extra times.
				return migrated || prev;
			} );
		} );

		return null;
	}

	/**
	 * Fire once per tag, on the FIRST FOLDED slot option.
	 *
	 * Anchoring to a folded key rather than to the tag's first option (the FW-52
	 * normalizer's anchor) does two things: it IS the "is this tag folded" gate — modes
	 * do not mix, so a tag with any `bws-slot-fold` option is on the new wire — and slot
	 * 1 always renders, whereas a leading option can be hidden by a conditional filter.
	 */
	function mountFilter( element, allOptions, context ) {
		if ( ! element || ! allOptions || ! context || 'function' !== typeof context.setState ) {
			return element;
		}
		var first = null;
		Object.keys( allOptions ).forEach( function ( name ) {
			var cfg = allOptions[ name ];
			if ( null === first && cfg && 'bws-slot-fold' === cfg.type ) {
				first = name;
			}
		} );
		if ( null === first || element.key !== first ) {
			return element;
		}

		// The wrapper CARRIES THE OPTION KEY — same rule as the order normalizer's wrap,
		// and for the same reason: a later filter anchoring on `element.key` must still
		// find it. Two keyless wraps at one priority is how the first one silently
		// switched the second one off.
		return wp.element.createElement(
			wp.element.Fragment,
			{ key: element.key },
			wp.element.createElement( MountMigrator, {
				key: 'bws-slot-fold-mount-migrator',
				setState: context.setState,
				conf: ( allOptions[ first ] && allOptions[ first ].fold ) || {}
			} ),
			element
		);
	}

	wp.hooks.addFilter(
		'generateblocks.editor.tagSpecificControls',
		'bws/slot-fold-mount-migrate',
		mountFilter,
		// Priority 20, matching the order normalizer: after the composite controls have
		// registered, so the element being wrapped is already its final control.
		20
	);
}() );
