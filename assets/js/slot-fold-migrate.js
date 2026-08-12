/**
 * Mount-time fold migration — the JS TWIN of the pure layer in
 * includes/helpers/slot-fold-migrate.php, plus the invisible control that applies it.
 *
 * THE SECOND MIGRATION PATH, and NOT a subset of the first. The converter's scanner
 * reads `wpdb->posts.post_content` only, so block widgets (the `widget_block` option)
 * are reachable ONLY here — an author who opens a widget's tag modal is the only event
 * that can migrate it. Conversely the scanner reaches drafts and templates nobody
 * opens. Both paths run the SAME rules, which is why this file is a twin rather than a
 * second implementation: `bwsSlotFold.foldFromFlat` (the grammar owner's twin) makes
 * every legacy→folded decision, and what remains here is the wire-level adapter —
 * strip, emit, canonicalize — mirroring bws_fold_migrate_slots().
 *
 * NO TAG-NAME TABLE HERE. The PHP migrator matches by tag name because
 * MigrationRegistry does; the editor instead reads the `fold` config off the option
 * definition GB hands to the filter, so a container's parameters — including which
 * legacy axes are per-slot at all (`flatAxes`) — arrive DERIVED from registration.
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
	 * PHP-DERIVED (`flatAxes`, from bws_fold_slot_flat_axes). The fallback is the
	 * full set, matching an unfiltered container: an absent list is a REGISTRATION bug,
	 * and folding too much is visible immediately, whereas folding nothing looks like
	 * "no legacy wire here" and silently strands it.
	 *
	 * @param {Object} conf Fold config.
	 * @return {Array} Axis names.
	 */
	function slotAxes( conf ) {
		var axes = conf && conf.flatAxes;
		return ( axes && axes.length ) ? axes : [ 'src', 'ref', 'srcTermIn', 'use', 'key', 'limit' ];
	}

	/**
	 * Legacy `src` VALUES this container must refuse to fold.
	 *
	 * PHP-DERIVED (`retiredSrc`, from BWS_FOLD_RETIRED_SRC_TOKENS) for the same reason
	 * `flatAxes` is: a hand-kept copy of a rule the renderer owns is how the two paths
	 * drift. The fallback is EMPTY, not the full list, and that direction is deliberate —
	 * unlike `flatAxes`, declining too much would silently stop migrating every slot,
	 * which reads as "no legacy wire here". An absent list is a registration bug, and the
	 * worst it costs is the pre-#56 behaviour on a token that barely exists.
	 *
	 * @param {Object} conf Fold config.
	 * @return {Array} Source tokens.
	 */
	function retiredSrc( conf ) {
		var tokens = conf && conf.retiredSrc;
		return ( tokens && tokens.length ) ? tokens : [];
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
	 * The view the legacy MAPPER reads — slotState(), plus the one key it must see that is
	 * not a per-slot axis.
	 *
	 * A SELECTING container states `limit` ONCE, at tag level, and it is every attempt's
	 * own default rather than a bound across attempts. The mapper has to see it, or the
	 * flat era's materialized default writes a `1` that SHADOWS the author's number — a
	 * slot's own limit wins over the tag-level one in every container arm. It is a READ
	 * and never a fold: the key is not on the per-slot delete list, so the slot loop neither
	 * strips it nor counts it as something to migrate. migrateSlots() retires it once, as a
	 * tag-level decision (#61).
	 *
	 * The gate is exactly foldFromFlat()'s own — SELECTING container, and never where the
	 * slot already owns the key — so the two cannot disagree about which map is being read.
	 *
	 * ONE function because two readers need one answer: migrateSlots() writes the wire and
	 * the fold CONTROL reads a legacy slot to display it. A control showing `1` where the
	 * mount migration is about to commit `3` is the drift this seam exists to prevent.
	 *
	 * @param {Object} state Whole option map.
	 * @param {Object} conf  Fold config.
	 * @return {Object} Filtered map, plus a tag-level `limit` where the container has one.
	 */
	function mapperState( state, conf ) {
		var src = slotState( state, conf );
		if ( ! conf.combining
			&& ! Object.prototype.hasOwnProperty.call( src, 'limit' )
			&& '' !== String( ( state.limit === null || state.limit === undefined ) ? '' : state.limit ).trim() ) {
			return Object.assign( {}, src, { limit: state.limit } );
		}
		return src;
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
	 *     the shipped resolver already discards);
	 *   - a slot naming a RETIRED source token is declined WHOLE — not folded, and not
	 *     stripped either, the one case where those two come apart (#56).
	 *
	 * The retired-token rule matters most HERE, on the path that has no entry chain. The
	 * converter rewrites `src:related_post` to `src:ref` in a separate migration entry
	 * before the fold ever runs, reading the undifferentiated option array — exactly what
	 * the retired source class read. This path cannot: `rel` is not a fold axis, and a
	 * container's `key` may be tag-level and already filtered out. Folding the token
	 * verbatim would store the tag one way while the converter stores it another. Leaving
	 * it alone cannot: writing NOTHING is not a second way to store a tag.
	 *
	 * @param {Object} state Whole option map.
	 * @param {Object} conf  Fold config.
	 * @return {Object|null} Rewritten map, or null.
	 */
	function migrateSlots( state, conf ) {
		var src = slotState( state, conf );
		var view = mapperState( state, conf );
		var next = {};
		var touched = false;
		var hasSlot = false;
		var n;

		Object.keys( state ).forEach( function ( key ) {
			next[ key ] = state[ key ];
		} );

		// #61 — the SELECTING container's tag-level `limit` stops existing. Mirrors
		// bws_fold_migrate_slots(): a LEGACY slot takes the number through the mapper view
		// above, an ALREADY-FOLDED one takes it onto its own chain here, and a slot that
		// fans only by INHERITING takes nothing (the render seam carries the bound with the
		// source it inherits). NUMERIC only — an uninterpretable value is not a number to
		// push anywhere, and deleting an author's text on that basis is a bigger move than
		// this rewrite is entitled to.
		var tagLimit = null;
		if ( ! conf.combining ) {
			var rawLimit = String( ( state.limit === null || state.limit === undefined ) ? '' : state.limit ).trim();
			if ( '' !== rawLimit && fold.isNumericLike( rawLimit ) ) {
				tagLimit = rawLimit;
			}
		}

		for ( n = 1; n <= ( conf.max || 5 ); n++ ) {
			var foldedKey = fold.slotKey( n );
			var foldedVal = String( state[ foldedKey ] || '' ).trim();
			var present = legacyKeys( conf, n ).filter( function ( key ) {
				return Object.prototype.hasOwnProperty.call( src, key );
			} );
			// A slot naming a RETIRED source token does NOT count — it is declined whole
			// below, so the number has nowhere to land in it, and consuming the tag-level
			// key on its account would leave the tag half-treated: legacy keys still there
			// for the converter to fix later, but the bound they need already deleted.
			var declined = '' === foldedVal
				&& present.length
				&& retiredSrc( conf ).indexOf( String( src[ ( 1 === n ? '' : String( n ) + '-' ) + 'src' ] || '' ).trim() ) > -1;
			if ( ! declined && ( '' !== foldedVal || present.length ) ) {
				hasSlot = true;
			}

			// The retiring number lands on an already-folded slot's own last fanning step,
			// through the same three owners the rest of the fold uses. A slot that pins its
			// own limit is left alone: the tag-level number was a DEFAULT, and a default
			// never overwrites a stated value (applyLegacyLimit decides both).
			if ( '' !== foldedVal && null !== tagLimit ) {
				var parsed = fold.parseSlot( foldedVal, conf.container || 'try' );
				if ( parsed ) {
					var applied = fold.applyLegacyLimit( parsed.chain || [], tagLimit );
					if ( applied.consumed ) {
						parsed.chain = applied.chain;
						next[ foldedKey ] = fold.emitSlot( parsed );
					}
				}
			}

			if ( ! present.length ) {
				continue;
			}

			// Declined whole: no fold, no strip. See the docblock.
			if ( declined ) {
				continue;
			}

			touched = true;
			present.forEach( function ( key ) {
				delete next[ key ];
			} );

			// An already-folded value for this slot wins outright.
			if ( '' !== foldedVal ) {
				continue;
			}

			var rec = fold.foldFromFlat( n, view, !! conf.combining, false !== conf.perSlotUse );
			if ( ! rec || ! rec.slot ) {
				continue;
			}
			var wire = fold.emitSlot( rec.slot );
			if ( '' !== wire ) {
				next[ foldedKey ] = wire;
			}
		}

		// The key goes only where there was a slot to push it into — a tag with no slot at
		// all has nowhere for the number to land.
		if ( null !== tagLimit && hasSlot ) {
			delete next.limit;
			touched = true;
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

	/**
	 * Rewrite a BASE tag's flat source triple into depth-0 CHAIN wire.
	 *
	 * TWIN of bws_fold_migrate_base_src() (includes/helpers/slot-fold-migrate.php),
	 * which is where every rule below is decided and explained. The two paths must
	 * write BYTE-IDENTICAL output, key order included: a divergence does not surface
	 * as one path being wrong, it surfaces as one tag stored two ways depending on
	 * which found it first. The shared corpus proves it.
	 *
	 * Returns null when there is nothing to migrate -- which is also the mount
	 * migrator's loop guard, since returning the previous reference makes React bail.
	 *
	 * @param {Object} state The tag's extraTagParams.
	 * @param {Object} conf  The chain config (for the PHP-derived retired-token list).
	 * @return {Object|null} Rewritten options, or null.
	 */
	function baseSrcState( state, conf ) {
		var s = state || {};
		var src = String( ( s.src || s.source ) || '' ).trim();
		var tax = String( s.srcTermIn || '' ).trim();

		var chain = [];

		if ( fold.chainIsWire( src ) ) {
			// Already respelled -- but a TAG-LEVEL LIMIT is legacy by POSITION rather
			// than by spelling, and it is the one shape where a bound is INVISIBLE (the
			// step's own Limit field reads unlimited, and #62 left no control that can
			// reach the key). So it is absorbed onto the step it bounds here too.
			//
			// NUMERIC ONLY, unlike the flat branch below: that one materializes the flat
			// ERA's default when the key is absent or unreadable, because the spelling it
			// leaves behind meant 1. Chain wire is not changing era, so there is no
			// default to carry. See the PHP owner (bws_fold_migrate_base_src).
			var rawLimit = String( ( s.limit === null || s.limit === undefined ) ? '' : s.limit ).trim();
			if ( '' === rawLimit || ! fold.isNumericLike( rawLimit ) ) {
				return null;
			}
			var parsed = fold.parseChain( src );
			if ( ! parsed || parsed.error || ! parsed.length ) {
				return null;
			}
			chain = parsed;
		} else {
			if ( -1 !== retiredSrc( conf || {} ).indexOf( src ) ) {
				return null;   // declined whole, exactly as a slot is (#56)
			}
			if ( 'site' === src ) {
				return null;   // the site read wins; the honest rewrite would DROP a key
			}
			if ( 'ref' !== src && '' === tax ) {
				return null;   // nothing fans, so there is no chain to state
			}

			if ( 'ref' === src ) {
				var ref = String( s.ref || '' ).trim();
				chain.push( { slug: 'refs', arg: '' !== ref ? ref : null, limit: null, extra: [] } );
			}
			if ( '' !== tax ) {
				chain.push( { slug: 'terms', arg: tax, limit: null, extra: [] } );
			}
		}

		// Migration changes the SPELLING, and the spelling selects the tag-level
		// default -- so it must carry the default it is leaving behind, onto the STEPS.
		// The mapping is the grammar's (bws_fold_chain_apply_legacy_limit), shared with the
		// converter half and the author-conversion commit so three surfaces cannot store
		// one tag three ways.
		// Depth 0, so an explicit `limit:0`/`-1` is CONSUMED rather than left behind:
		// chain wire already means unlimited, and since #62 no control can reach the key.
		var bound = fold.applyLegacyLimit( chain, s.limit, true );

		// Enclosing level 0 -- a base tag's `src:` IS the wrapper.
		var wire = fold.emitChain( bound.chain, 0 );
		if ( '' === wire || ! fold.chainIsWire( wire ) ) {
			return null;
		}

		// On the absorb branch the KEY is the point, so a mapping that stood down -- the
		// chain states its own step limits, or it does not fan -- is no rewrite at all.
		// Returning an unchanged map would re-serialize on every open, which is what the
		// mount path's loop guard exists to avoid.
		if ( ! bound.consumed && src === wire ) {
			return null;
		}

		var out = Object.assign( {}, s );
		delete out.source;
		out.src = wire;
		delete out.ref;
		delete out.srcTermIn;
		if ( bound.consumed ) {
			delete out.limit;
		}
		// Canonical key order, through the SAME normalizer the slot half uses -- the two
		// paths must not write one tag two ways, and key order is half the property.
		var keys = Object.keys( out );
		if ( 'function' === typeof window.bwsReorderKeys ) {
			keys = window.bwsReorderKeys( keys );
		}
		var ordered = {};
		keys.forEach( function ( key ) {
			ordered[ key ] = out[ key ];
		} );
		return ordered;
	}

	window.bwsSlotFoldMigrate = {
		slotAxes: slotAxes,
		retiredSrc: retiredSrc,
		legacyKeys: legacyKeys,
		slotKeys: slotKeys,
		slotState: slotState,
		mapperState: mapperState,
		migrateSlots: migrateSlots,
		baseSrcState: baseSrcState
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

	/** Renders nothing; rewrites this BASE tag's flat source on mount. */
	function BaseSrcMountMigrator( props ) {
		var setState = props.setState;
		var conf = props.conf;

		useEffect( function () {
			setState( function ( prev ) {
				var migrated = baseSrcState( prev || {}, conf );
				// Same reference = same loop guard as the slot half.
				return migrated || prev;
			} );
		} );

		return null;
	}

	/**
	 * Fire once per BASE tag, on its `bws-src-chain` source option.
	 *
	 * The depth-0 counterpart of mountFilter(). It anchors on the CHAIN CONTROL rather
	 * than on the tag's first option for the same two reasons the slot half anchors on
	 * a folded key: the control's presence IS the "does this tag author chains" gate,
	 * and the source option always renders, whereas a leading option can be hidden by a
	 * conditional filter.
	 *
	 * Reaches what the content scanner cannot -- a block widget's tag lives in the
	 * `widget_block` option, not in post content -- and misses what only the scanner
	 * reaches, a draft nobody opens. Complementary, which is why both exist.
	 */
	function baseMountFilter( element, allOptions, context ) {
		if ( ! element || ! allOptions || ! context || 'function' !== typeof context.setState ) {
			return element;
		}
		var first = null;
		Object.keys( allOptions ).forEach( function ( name ) {
			var cfg = allOptions[ name ];
			if ( null === first && cfg && 'bws-src-chain' === cfg.type ) {
				first = name;
			}
		} );
		if ( null === first || element.key !== first ) {
			return element;
		}

		// The wrapper CARRIES THE OPTION KEY -- a later filter anchoring on
		// `element.key` must still find it. Two keyless wraps at one priority is how the
		// first one silently switched the second one off, and this control's own filter
		// anchors on exactly this key.
		return wp.element.createElement(
			wp.element.Fragment,
			{ key: element.key },
			wp.element.createElement( BaseSrcMountMigrator, {
				key: 'bws-base-src-mount-migrator',
				setState: context.setState,
				conf: ( allOptions[ first ] && allOptions[ first ].fold ) || {}
			} ),
			element
		);
	}

	wp.hooks.addFilter(
		'generateblocks.editor.tagSpecificControls',
		'bws/base-src-mount-migrate',
		baseMountFilter,
		20
	);

	wp.hooks.addFilter(
		'generateblocks.editor.tagSpecificControls',
		'bws/slot-fold-mount-migrate',
		mountFilter,
		// Priority 20, matching the order normalizer: after the composite controls have
		// registered, so the element being wrapped is already its final control.
		20
	);
}() );
