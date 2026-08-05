/**
 * Base-tag SOURCE CHAIN control (`bws-src-chain`, FW-56).
 *
 * Replaces the base tags' flat Source select with an editor for the whole chain: a
 * root plus ordered fanning steps, added one at a time. The engine has always been
 * able to run an arbitrary chain; until this control there was no way for an author
 * to write one, so "the office of the staff member this event references" was
 * inexpressible rather than merely awkward.
 *
 * WHAT IT DOES NOT OWN. The steps themselves are rendered by
 * `window.bwsSlotFoldRepeater.chainSteps` — the SAME component the folded-slot
 * control uses. A second renderer would be a second place for a hand-authored
 * spelling of `terms` to get in, which is exactly the drift the derived config
 * removed. Likewise every enum, label, noun and slug map arrives on the PHP option
 * definition (bws_build_src_chain_option); nothing here is typed twice.
 *
 * The base tag keeps its own `use`/`key` controls: this edits the SOURCE only. That
 * is the difference from a folded slot, where source and read fold into one value.
 *
 * ── THE CONVERSION, AND WHY IT WRITES A NUMBER ──────────────────────────────
 *
 * A legacy tag stores its source as three separate keys (`src`, `ref`, `srcTermIn`).
 * On mount this control READS them as the chain they describe — display only, so a
 * cancelled modal leaves stored wire untouched. The first commit writes chain wire
 * and deletes the two flat siblings.
 *
 * That commit also serializes `limit:1` when the source fans, and leaves it visible
 * in the `limit` control for the author to clear. It is not a courtesy: since 1.17.0
 * the tag-level cap DEFAULT is selected by the source spelling — flat wire caps at
 * one, chain wire does not — so a conversion that wrote nothing would silently fan
 * out the tag under the author's hands, adding values and dropping anchors (the link
 * gate is count-based). Writing the default it is leaving behind keeps conversion a
 * pure respelling, and writing it into a field the author can see makes the change
 * evidence rather than a surprise.
 *
 * One stored shape across every path. The scanner, the mount migrator and this
 * control all produce the identical tag; inline help instead of serialization would
 * produce a third variant on any author who did not act on it.
 *
 * @package BWS_Dynamic_Tags
 * @since   1.17.0
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.hooks || ! wp.element || ! wp.components ) {
		return;
	}
	// Hard dependencies: the grammar owns the wire, the repeater owns the step rows.
	if ( ! window.bwsSlotFold || ! window.bwsSlotFoldRepeater ) {
		return;
	}

	var el       = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __        = ( wp.i18n && wp.i18n.__ ) ? wp.i18n.__ : function ( s ) { return s; };
	var fold      = window.bwsSlotFold;
	var repeater  = window.bwsSlotFoldRepeater;

	var GROUP_BOX = {
		border: '1px solid #e0e0e0',
		borderRadius: '2px',
		padding: '12px',
		marginBottom: '16px',
		position: 'relative'
	};
	var GROUP_CAP = {
		position: 'absolute',
		top: '-8px',
		left: '8px',
		background: '#fff',
		padding: '0 4px',
		fontSize: '11px',
		textTransform: 'uppercase',
		letterSpacing: '0.4px',
		opacity: 0.65
	};

	/**
	 * The depth-0 chain a base tag's options describe — the JS half of
	 * bws_fold_chain_from_options().
	 *
	 * TWIN, and it has to be: the renderer reads this rule from PHP and the control
	 * writes wire against it, so a divergence stores a source the renderer reads
	 * differently. Every clause below mirrors one there, including the two that look
	 * like edge cases and are not:
	 *
	 * - An orphan `src:ref` (no `ref` field) still contributes a step. It is already
	 *   dead at render; keeping the incomplete step preserves that rather than
	 *   inventing a source out of the author's mistake.
	 * - A SITE root never takes the legacy term hop. `srcTermIn` is registered
	 *   `show_if src: not:site`, so the pair is hand-edit only, and every arm has
	 *   always let the site read win. Folding it in would flip such a tag to empty.
	 *
	 * @param {Object} state The tag's extraTagParams.
	 * @return {Array} Parsed chain (grammar shape).
	 */
	function chainFromOptions( state ) {
		var raw = String( ( state && ( state.src || state.source ) ) || '' ).trim();
		var tax = String( ( state && state.srcTermIn ) || '' ).trim();
		var chain = null;

		if ( raw && chainIsWire( raw ) ) {
			var parsed = fold.parseChain( raw );
			if ( parsed && ! parsed.error ) {
				chain = parsed;
			}
		}

		if ( null === chain ) {
			// Malformed chain wire falls back to the legacy reading (the raw value as a
			// root token), which resolves the ambient entity — never a fabricated hop.
			chain = [];
			if ( 'ref' === raw ) {
				chain.push( step( 'refs', String( ( state && state.ref ) || '' ).trim() || null ) );
			} else if ( raw ) {
				chain.push( step( raw ) );
			}
		}

		if ( tax && 'site' !== rootToken( chain ) ) {
			var hasTerms = chain.some( function ( link ) { return 'terms' === link.slug; } );
			if ( ! hasTerms ) {
				chain.push( step( 'terms', tax ) );
			}
		}

		return chain;
	}

	/** A chain step, in the grammar's shape. */
	function step( slug, arg, limit ) {
		return {
			slug: slug,
			arg: ( arg === undefined ) ? null : arg,
			limit: ( limit === undefined ) ? null : limit,
			extra: []
		};
	}

	// The three wire questions live in the GRAMMAR (assets/js/slot-fold-grammar.js),
	// which is the twinned owner of everything the wire means. Aliased rather than
	// re-implemented: three editor surfaces ask them, and three copies of a wire rule
	// is how the two languages come to disagree about what a stored value means.
	var chainIsWire = fold.chainIsWire;
	var rootToken   = fold.chainRoot;
	var chainFans   = fold.chainFans;

	/**
	 * The next option state for a committed chain — the whole conversion, as one
	 * pure function of (state, key, chain, legacy axes).
	 *
	 * Pure and exported because the decision it makes is not observable in anything
	 * the control renders: the author sees a number appear in a field, and a test
	 * that watched the rendered field would be asserting the `limit` control's
	 * behaviour rather than this one's.
	 *
	 * Three things happen together, and they have to:
	 *
	 * 1. The chain is written (or the key deleted — the delete-omit idiom, since an
	 *    absent `src` is the bare tag's genuine unset state).
	 * 2. The flat siblings whose meaning it absorbed are deleted. Leaving them beside
	 *    the chain would store one source two ways.
	 * 3. On the CONVERSION only, and only where the source fans, the tag-level cap
	 *    the old spelling implied is made explicit. See the file header: chain wire
	 *    defaults to uncapped, so a conversion that wrote nothing would fan the tag
	 *    out under the author's hands. An author who already stated a limit keeps it
	 *    — ordinary option precedence, no extra rule. A source with no step has
	 *    nothing to cap, so a number there would be noise a reader must decide is
	 *    meaningless.
	 *
	 * @param {Object}   state      Current extraTagParams.
	 * @param {string}   key        The source option key (`src`).
	 * @param {Array}    chain      The committed chain.
	 * @param {string[]} legacyAxes Flat option names the chain replaces.
	 * @return {Object} The next extraTagParams.
	 */
	function convertUpdate( state, key, chain, legacyAxes ) {
		var upd  = Object.assign( {}, state );
		// Enclosing level 0 — a base tag's `src:` is the wrapper, so a step `limit`
		// prints one level inside it (`refs,office,limit[2]`). A slot's `src(...)`
		// passes 1. The caller that prints the wrapper owns this number; both shipped
		// bugs on this axis came from recomputing depth locally.
		var wire = fold.emitChain( chain, 0 );

		if ( wire ) {
			upd[ key ] = wire;
		} else {
			delete upd[ key ];
		}

		// The flat siblings go on EVERY commit, not only when the result happens to
		// be chain-shaped. A root-only commit (`src:current`, `src:site`, or a
		// cleared source) emits a plain token, and returning early there left
		// `srcTermIn` behind — which bws_fold_chain_from_options() then re-appends as
		// a `terms` step, so deleting a taxonomy step did not stick at render OR on
		// reopen. This control owns the whole source; anything it leaves beside the
		// value is a second spelling of the same source.
		( legacyAxes || [] ).forEach( function ( axis ) {
			delete upd[ axis ];
		} );

		if ( ! wire ) {
			return upd;
		}
		var wasWire = chainIsWire( String( state[ key ] || '' ) );
		if ( ! wasWire && chainFans( chain )
			&& ( upd.limit === undefined || '' === upd.limit ) ) {
			upd.limit = '1';
		}
		return upd;
	}

	function SrcChainControl( props ) {
		var ctx      = props.context;
		var state    = ctx.state || {};
		var setState = ctx.setState;
		var key      = props.optionKey;
		var conf     = repeater.foldConfig( { fold: props.fold } );

		var chain = chainFromOptions( state );

		/** Commit a chain — the whole rule lives in convertUpdate(). */
		function writeChain( next ) {
			setState( convertUpdate( state, key, next, conf.legacyAxes ) );
		}

		var stepNodes = repeater.chainSteps( {
			conf: conf,
			chain: chain,
			onChange: writeChain,
			// A base tag has no predecessor to inherit from, so an emptied chain
			// legitimately means the ambient entity rather than an inherit.
			inheritOnEmpty: false,
			slotNoun: __( 'tag', 'generateblocks' ),
			hopContext: function ( stepObj, commitArg ) {
				return {
					state: { key: stepObj.arg || '' },
					setState: function ( next ) {
						commitArg( next && next.key ? next.key : '' );
					}
				};
			}
		} );

		return el( 'div', { style: GROUP_BOX }, [
			el( 'span', { key: 'cap', style: GROUP_CAP },
				chain.length > 1 ? __( 'Source path', 'generateblocks' ) : __( 'Source', 'generateblocks' ) )
		].concat( stepNodes ) );
	}

	wp.hooks.addFilter(
		'generateblocks.editor.tagSpecificControls',
		'bws/src-chain-control',
		function ( element, allOptions, context ) {
			// Compose: if a prior filter hid this control, keep it hidden regardless of
			// registration order.
			if ( ! element || ! allOptions || ! context ) {
				return element;
			}
			var cfg = allOptions[ element.key ];
			if ( ! cfg || 'bws-src-chain' !== cfg.type ) {
				return element;
			}
			return el( SrcChainControl, {
				key: element.key,
				optionKey: element.key,
				fold: cfg.fold || {},
				context: context
			} );
		}
	);

	// Exported for the editor harness: these are the pure decisions (what the legacy
	// keys mean as a chain, whether a chain fans, what a conversion writes) that no
	// render assertion can reach.
	window.bwsSrcChain = {
		chainFromOptions: chainFromOptions,
		convertUpdate: convertUpdate,
		chainIsWire: chainIsWire,
		rootToken: rootToken,
		chainFans: chainFans,
		step: step
	};
}() );
