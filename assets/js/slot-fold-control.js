/**
 * `bws-slot-fold` — the composite control that owns ONE folded slot value.
 *
 * One option key per slot, whose VALUE holds the slot's whole configuration
 * (source chain + field read + per-slot options). The control parses that value on
 * mount and REWRITES IT IN FULL on every commit (DECISION 2: the control is the
 * editor-side source of truth for the value; it never patches a fragment).
 *
 * REPEATER MODEL. Cardinality is EXPLICIT — add/remove — not a side effect of how
 * far the author has configured. Slots 1..min always render; slot N ≥ min+1 renders
 * when it HOLDS A VALUE, and the add button writes that value (the seed). So no
 * "armed but empty" state exists to lose on remount, and cardinality is derived from
 * CONTENT, which is the only thing that durably survives a remount.
 *
 * NOTHING IN HERE IS HAND-AUTHORED VOCABULARY:
 * - The GRAMMAR comes from window.bwsSlotFold (assets/js/slot-fold-grammar.js, the
 *   tested twin of the PHP owner). This control never parses or emits wire itself.
 * - Every ENUM, LABEL and NOUN arrives on the PHP option definition, derived from
 *   the shipped builders (the source twin, the read twin, the text leaf, the
 *   traversal options). Hand-porting those strings is how four copies of the read
 *   enum arose and how image's `Return type:` / `Return image as:` drifted; a
 *   control that re-types them re-creates the drift it was built to remove.
 *
 * `inferIntent()` is ADVISORY TEXT and never a gate. It is the residue of a cut 2×2
 * intent radio that also drove axis VISIBILITY, which tangled reveal state with slot
 * existence. It describes what a slot varies against its predecessor; it must never
 * regrow authority over what renders or what serializes.
 *
 * @package BWS_Dynamic_Tags
 * @since   1.17.0
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.hooks || ! wp.element || ! wp.components ) {
		return;
	}
	if ( ! wp.components.SelectControl || ! wp.components.Button ) {
		return;
	}
	// Hard dependency: the grammar owner's twin. Without it this control cannot read
	// or write the wire, and a partial fallback would emit something the renderer does
	// not parse — worse than not mounting.
	if ( ! window.bwsSlotFold ) {
		return;
	}

	var el            = wp.element.createElement;
	var Fragment      = wp.element.Fragment;
	var SelectControl = wp.components.SelectControl;
	var TextControl   = wp.components.TextControl;
	var Button        = wp.components.Button;
	var __            = wp.i18n ? wp.i18n.__ : function ( s ) { return s; };
	var fold          = window.bwsSlotFold;

	// ── Layout ───────────────────────────────────────────────────────────────
	// The box marks a GROUP (the whole source chain / the whole field picker), not an
	// individual step: boxing each step made a multi-hop chain read as N peer objects
	// rather than one path, and stacked three borders deep once the field filters
	// landed. Nesting reads by WEIGHT instead — slot rule 2px > step rule 1px.
	// No marginBottom anywhere: the modal content column already applies a 15px
	// row-gap, so a margin of our own double-spaces against it.
	var GROUP_BOX = {
		border: '1px solid #e0e0e0', borderRadius: '2px',
		padding: '12px', background: 'rgba(0,0,0,0.02)'
	};
	var STEP_RULE = { borderTop: '1px solid #ddd', marginTop: '10px', paddingTop: '10px' };
	var GROUP_CAP = {
		fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.4px',
		opacity: 0.65, marginBottom: '10px', display: 'block'
	};
	// Stock control labels sit tight against whatever precedes them, so a label
	// following another control reads as belonging to it. Space the OWNER — we do not
	// control the stock components' internal markup.
	var STACKED = { marginTop: '14px' };

	// ComboboxControl's `__suggestions-container` carries the border and padding but
	// declares NO background, so a tinted group shows through it while the `__input`
	// within paints itself white — one control, two fills. Fix that one element, not
	// the wrapper. Its own label also sits flush against the filters above it and
	// lives inside the shipped control's markup, out of reach of our wrapper margin.
	var SCOPED_CSS =
		'.bws-slot-fold .components-combobox-control__suggestions-container{background:#fff;}' +
		'.bws-slot-fold .components-combobox-control .components-base-control__label' +
		'{margin-top:12px;display:inline-block;}';

	if ( 'undefined' !== typeof document && ! document.getElementById( 'bws-slot-fold-css' ) ) {
		var styleEl = document.createElement( 'style' );
		styleEl.id = 'bws-slot-fold-css';
		styleEl.appendChild( document.createTextNode( SCOPED_CSS ) );
		document.head.appendChild( styleEl );
	}

	// ── Config (all of it PHP-derived; see the header) ───────────────────────

	/**
	 * Read the fold config off the option definition, with inert defaults. An
	 * unregistered noun is a REGISTRATION bug, so the fallbacks here exist to keep a
	 * misregistered tag renderable, not to supply vocabulary.
	 */
	function foldConfig( cfg ) {
		var c = ( cfg && cfg.fold ) || {};
		return {
			container: c.container || 'join',
			combining: !! c.combining,
			perSlotUse: false !== c.perSlotUse,
			min: c.min || 2,
			max: c.max || 5,
			noun: c.noun || '',
			srcRows: c.srcRows || [],
			srcRowsInherit: c.srcRowsInherit || [],
			hopRows: c.hopRows || [],
			readRows: c.readRows || [],
			readRowsInherit: c.readRowsInherit || [],
			readLabel: c.readLabel || '',
			slugMap: c.slugMap || {},
			taxonomies: c.taxonomies || [],
			refOption: c.refOption || null,
			keyOption: c.keyOption || null,
			entriesOption: c.entriesOption || null,
			// Which tag-level option names the repeater a row-scoped picker narrows to.
			// The control passes its VALUE down as an explicit `scopeKey` prop rather
			// than letting the picker reach outward for a bare `key` — under the fold
			// that name means something else one level in.
			scopeStateKey: c.scopeStateKey || '',
			fieldScope: c.fieldScope || ''
		};
	}

	/** Engine option value → wire step slug (DECISION 3: `ref` → `refs`). */
	function toWire( conf, value ) {
		return conf.slugMap[ value ] || value;
	}

	/** Wire step slug → engine option value (the inverse of the PHP-supplied map). */
	function toEngine( conf, slug ) {
		var found = slug;
		Object.keys( conf.slugMap ).forEach( function ( engine ) {
			if ( conf.slugMap[ engine ] === slug ) {
				found = engine;
			}
		} );
		return found;
	}

	/** Slugs whose ARG is a field key discovered through the field picker. */
	function argKind( conf, slug ) {
		if ( 'refs' === slug ) { return 'ref'; }
		if ( 'entries' === slug ) { return 'entries'; }
		if ( 'terms' === slug ) { return 'taxonomy'; }
		return '';
	}

	// ── Slot structs ────────────────────────────────────────────────────────

	/** An empty slot struct in the grammar's shape. */
	function emptySlot() {
		return { label: null, type: null, chain: [], read: null, opts: {}, extra: [] };
	}

	function step( slug, arg, limit ) {
		return { slug: slug, arg: ( void 0 === arg ? null : arg ), limit: ( void 0 === limit ? null : limit ), extra: [] };
	}

	/**
	 * The SEED a newly added slot is created with — CONTAINER-DEPENDENT, and that is
	 * the whole point of the parameter.
	 *
	 * Both containers seed `src(same)`: reading a different field off the same source
	 * is the common case in combining and the point of carry-forward in selecting.
	 * The READ axis diverges. A SELECTING slot seeds `use(same)` (same field, different
	 * source = a fallback chain). A COMBINING slot seeds the read UNSET, because
	 * choosing a field IS the configuration act there — seeding `same` would create a
	 * slot that assembles the identical datum twice. The spike used one seed for both
	 * because it only ever ran one container.
	 */
	function seedSlot( conf ) {
		var slot = emptySlot();
		slot.chain = [ step( 'same' ) ];
		slot.read = conf.combining ? null : { kind: 'same' };
		return slot;
	}

	/** Legacy sibling keys for slot n — dropped on ANY commit (touch-migration). */
	function legacyKeys( n ) {
		var p = ( 1 === n ) ? '' : n + '-';
		return [ p + 'src', p + 'ref', p + 'srcTermIn', p + 'use', p + 'key', p + 'limit' ];
	}

	/** Read slot n as a struct whichever wire era it is stored in. */
	function readSlot( n, state, conf ) {
		var raw = state[ String( n ) ] || '';
		if ( raw ) {
			var parsed = fold.parseSlot( raw, conf.container );
			return parsed.error ? null : parsed;
		}
		var rec = fold.foldFromLegacy( n, state, conf.combining, conf.perSlotUse );
		return ( rec && rec.slot ) ? rec.slot : null;
	}

	/**
	 * Highest slot ordinal holding a value, floored at the container's minimum.
	 * Counts a slot present in EITHER era, so an unmigrated tag mounts with its true
	 * slot count.
	 */
	function slotCount( state, conf ) {
		var highest = 0;
		for ( var i = 1; i <= conf.max; i++ ) {
			if ( state[ String( i ) ] ) {
				highest = i;
			} else if ( fold.foldFromLegacy( i, state, conf.combining, conf.perSlotUse ) ) {
				highest = i;
			}
		}
		return Math.max( conf.min, highest );
	}

	/**
	 * Remove slot n and CLOSE THE HOLE — out-of-order removal must not leave a gap.
	 *
	 * THE HAZARD compaction introduces: `same` is a POSITIONAL backreference, so
	 * sliding a slot down re-points its inheritance at a DIFFERENT neighbour, silently
	 * changing meaning. So the survivor's inherited axes are MATERIALIZED against the
	 * slot being removed BEFORE any renumbering. Only the immediate successor can be
	 * affected (only it referenced the removed slot), so this is one fixup, not a
	 * cascade.
	 *
	 * Position 1 cannot hold an inherit (no predecessor), so any `same` that lands
	 * there after the slide is dropped to a plain unset axis.
	 *
	 * One whole-object setState, so no intermediate gap state is ever committed. Every
	 * legacy sibling key across the block is cleared too: a compaction rewrites all
	 * slots densely, which also completes the touch-migration.
	 */
	function removeSlotFrom( n, state, count, conf ) {
		var removed = readSlot( n, state, conf );
		var successor = ( n + 1 <= count ) ? readSlot( n + 1, state, conf ) : null;

		if ( removed && successor ) {
			if ( 1 === successor.chain.length && 'same' === successor.chain[ 0 ].slug ) {
				successor.chain = removed.chain.slice();
			}
			if ( successor.read && 'same' === successor.read.kind ) {
				successor.read = removed.read ? JSON.parse( JSON.stringify( removed.read ) ) : null;
			}
		}

		var survivors = [];
		for ( var i = 1; i <= count; i++ ) {
			if ( i === n ) {
				continue;
			}
			var s = ( i === n + 1 && successor ) ? successor : readSlot( i, state, conf );
			if ( s ) {
				survivors.push( s );
			}
		}
		if ( survivors.length ) {
			var first = survivors[ 0 ];
			if ( 1 === first.chain.length && 'same' === first.chain[ 0 ].slug ) {
				first.chain = [];
			}
			if ( first.read && 'same' === first.read.kind ) {
				first.read = null;
			}
		}

		var upd = Object.assign( {}, state );
		for ( var k = 1; k <= conf.max; k++ ) {
			delete upd[ String( k ) ];
			legacyKeys( k ).forEach( function ( lk ) {
				delete upd[ lk ];
			} );
		}
		survivors.forEach( function ( s, idx ) {
			var v = fold.emitSlot( s );
			if ( v ) {
				upd[ String( idx + 1 ) ] = v;
			}
		} );
		return upd;
	}

	/**
	 * ADVISORY ONLY — never a gate. Describes what this slot varies against its
	 * predecessor, by pure read of the wire. The all-inherit state is called out
	 * because it resolves identically to the previous slot, so it is always a
	 * "not configured yet" state rather than a resting one.
	 */
	function inferIntent( slot ) {
		if ( ! slot || ( ! slot.chain.length && ! slot.read ) ) {
			return '';
		}
		var srcSame = 1 === slot.chain.length && 'same' === slot.chain[ 0 ].slug;
		var readSame = !! ( slot.read && 'same' === slot.read.kind );
		if ( srcSame && readSame ) { return ''; }
		if ( readSame ) { return 'context'; }
		if ( srcSame ) { return 'field'; }
		return 'both';
	}

	// ── Field-picker bridges ────────────────────────────────────────────────
	//
	// The shipped `bws-field-combo` mounts per OPTION KEY and commits `upd[key]`.
	// Under the fold there IS no `{N}-key` option: the field lives inside the slot
	// value's `key(...)` token, owned by THIS control. Two controls cannot own one
	// key, so the picker is rendered directly against a SYNTHETIC context that
	// presents folded state in the shape it expects and funnels writes back into the
	// folded value. The shipped control is unmodified — if a bridge needed it
	// modified, the fold would have broken it, and that is a finding, not a patch.

	/** Context for a hop's reference/repeater field argument. */
	function hopContext( stepObj, commitArg ) {
		return {
			state: { key: stepObj.arg || '' },
			setState: function ( upd ) {
				commitArg( ( upd && 'undefined' !== typeof upd.key ) ? upd.key : '' );
			}
		};
	}

	/**
	 * Context for the slot's READ field.
	 *
	 * The interesting translation is the picker's location PRESET. Legacy read the
	 * sibling `src` token; under the fold the source is a CHAIN, so the preset derives
	 * from the chain's TERMINAL step — the step whose output the read applies to.
	 * That is FW-56's stated editor-UX floor (the terminal step's output kind must be
	 * statically computable from the wire), exercised here for real.
	 *
	 * `refs` is deliberately NOT preset: the hop target's post type is not reliably
	 * known until ref-hop parity, so presetting would falsely assert a kind. Leaving
	 * it unmapped matches shipped behaviour and is not an omission.
	 */
	function fieldContext( slot, commitField ) {
		var terminal = slot.chain.length ? slot.chain[ slot.chain.length - 1 ] : null;
		var synth = {};
		if ( terminal ) {
			if ( 'site' === terminal.slug ) {
				synth.src = 'site';
			} else if ( 'terms' === terminal.slug ) {
				synth.srcTermIn = terminal.arg || '1';
			} else if ( 'same' !== terminal.slug && 'refs' !== terminal.slug ) {
				synth.src = terminal.slug;
			}
		}
		var read = slot.read;
		synth.key = ( read && 'key' === read.kind ) ? ( read.field || '' ) : '';
		return {
			state: synth,
			setState: function ( upd ) {
				commitField( ( upd && 'undefined' !== typeof upd.key ) ? upd.key : '' );
			}
		};
	}

	// ── The control ─────────────────────────────────────────────────────────

	function SlotFoldControl( props ) {
		var ctx = props.context;
		var state = ctx.state || {};
		var setState = ctx.setState;
		var key = props.optionKey;
		var ordinal = parseInt( key, 10 );
		var conf = props.conf;
		var raw = state[ key ] || '';
		var FieldCombo = window.bwsFieldComboControl;

		// MOUNT-RECONCILE: a folded value absent → recover from the legacy separate
		// keys through the SHARED mapping. Display-only until the author commits, so
		// the modal-confirm boundary keeps stored wire untouched on cancel.
		var slot = null;
		var recovered = false;
		var parseError = '';
		if ( raw ) {
			var parsed = fold.parseSlot( raw, conf.container );
			if ( parsed.error ) {
				parseError = parsed.error;
			} else {
				slot = parsed;
			}
		} else {
			var rec = fold.foldFromLegacy( ordinal, state, conf.combining, conf.perSlotUse );
			if ( rec && rec.slot ) {
				slot = rec.slot;
				recovered = true;
			}
		}

		// UNCONFIGURED-SLOT DEFAULT is position-aware. A blanket empty struct gave the
		// floor-visible slot 2 an empty chain, which is the silent-RESET shape (it
		// resolves against ambient context instead of inheriting) — the renderer treats
		// it as malformed. So slot ≥2 mounts as the same all-inherit state the add
		// button seeds, and an untouched slot 2 reads identically however it arrived.
		// Slot 1 keeps the empty default: with no predecessor, `current` genuinely IS
		// its unset state.
		slot = slot || ( ordinal >= 2 ? seedSlot( conf ) : emptySlot() );

		var count = slotCount( state, conf );
		var isLast = ordinal === count;

		// Slots past the live count do not render: PHP registers keys up to the
		// ceiling, but only the first `count` are part of THIS tag.
		if ( ordinal > count ) {
			return null;
		}

		/**
		 * Commit a whole slot struct. `''` DELETES the key (the delete-omit idiom —
		 * an absent key is what makes the slot genuinely not present), and any commit
		 * also drops this slot's legacy sibling keys, which is the touch-migration.
		 */
		function write( next ) {
			var upd = Object.assign( {}, state );
			var v = fold.emitSlot( next );
			if ( v ) {
				upd[ key ] = v;
			} else {
				delete upd[ key ];
			}
			legacyKeys( ordinal ).forEach( function ( lk ) {
				delete upd[ lk ];
			} );
			setState( upd );
		}

		/** Write the chain, preserving every other axis of the slot. */
		function writeChain( chain ) {
			var next = Object.assign( {}, slot );
			next.chain = chain;
			write( next );
		}

		/** Write the read, preserving every other axis of the slot. */
		function writeRead( read ) {
			var next = Object.assign( {}, slot );
			next.read = read;
			write( next );
		}

		function addSlot() {
			if ( count >= conf.max ) {
				return;
			}
			var upd = Object.assign( {}, state );
			upd[ String( count + 1 ) ] = fold.emitSlot( seedSlot( conf ) );
			setState( upd );
		}

		var children = [];

		if ( parseError ) {
			// Hand-edited wire this control cannot represent. Say so rather than
			// silently resetting the slot, which would discard the author's text.
			children.push( el( 'p', { key: 'err', style: { color: '#a00', fontSize: '12px', margin: '0 0 4px' } },
				'⚠ ' + parseError ) );
		}
		if ( recovered ) {
			children.push( el( 'p', { key: 'rec', style: { opacity: 0.7, fontSize: '12px', margin: '0 0 4px' } },
				__( 'Recovered from older options — saving updates this slot and removes them.', 'generateblocks' ) ) );
		}

		// ── Slot header ─────────────────────────────────────────────────────
		// Remove is offered on EVERY slot whenever 2+ are visible, including out of
		// order: compaction closes the hole, so there is no "last one only"
		// restriction. The floor still holds — removing while exactly two are present
		// compacts the survivor down and leaves two visible.
		// Size follows hierarchy: slot-level actions at default size, chain-level
		// actions small, so slot Remove matches Add and both outrank step Remove.
		var headerKids = [
			el( 'div', {
				key: 'row',
				style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '8px' }
			}, [
				el( 'strong', {
					key: 'ttl',
					style: { fontSize: '12px', textTransform: 'uppercase', letterSpacing: '0.4px' }
				}, props.label || ( conf.noun ? conf.noun + ' ' + key : key ) ),
				count > 1
					? el( Button, {
						key: 'rm',
						variant: 'tertiary',
						isDestructive: true,
						// Trim the stock horizontal padding so the label aligns flush
						// with the group boxes below.
						style: { marginRight: '-8px' },
						onClick: function () {
							setState( removeSlotFrom( ordinal, state, count, conf ) );
						}
					}, __( 'Remove', 'generateblocks' ) )
					: null
			] )
		];

		if ( ordinal >= 2 ) {
			var advisory = {
				context: __( 'Varies: source (field inherited)', 'generateblocks' ),
				field: __( 'Varies: field (source inherited)', 'generateblocks' ),
				both: __( 'Varies: source and field', 'generateblocks' )
			}[ inferIntent( slot ) ];
			if ( ! advisory ) {
				advisory = conf.combining
					? __( 'Inherits the previous source — pick a field for this slot.', 'generateblocks' )
					: __( 'Inherits both axes — this slot duplicates the previous one until you change something.', 'generateblocks' );
			}
			// Nested INSIDE the header container: the modal column's row-gap applies
			// between children, so a standalone advisory sits a full gap from the title
			// it describes.
			headerKids.push( el( 'p', {
				key: 'adv',
				style: { fontSize: '11px', opacity: 0.7, margin: '4px 0 0' }
			}, advisory ) );
		}
		children.push( el( 'div', { key: 'hdr' }, headerKids ) );

		// ── Source group ────────────────────────────────────────────────────
		// ONE box for the whole chain, with steps separated inside it by a lighter
		// rule. The wire supports an ordered chain, so the control must edit EVERY
		// step: a single-hop version silently TRUNCATED hops 2+ whenever the author
		// touched step 1.
		var chain = slot.chain.slice();
		var isSameChain = 1 === chain.length && 'same' === chain[ 0 ].slug;

		/** Rewrite the chain with one index replaced (null step = delete it). */
		function writeChainAt( idx, newStep ) {
			var next = chain.slice();
			if ( null === newStep ) {
				next.splice( idx, 1 );
			} else {
				next[ idx ] = newStep;
			}
			// Emptying the chain on slot ≥2 falls back to EXPLICIT inherit, never to
			// absence: the renderer resolves a bare empty chain against the ambient
			// entity (a RESET, not an inherit — legacy absence migrates to an explicit
			// `src(same)`, so absence means what it says), and losing an inherit to a
			// step deletion would be a silent meaning change.
			// Slot 1 has no predecessor, so empty there is legitimately `current`.
			if ( ordinal >= 2 && ! next.length ) {
				next = [ step( 'same' ) ];
			}
			writeChain( next );
		}

		/** Rows for a step by POSITION: only the first step may start a source. */
		function stepRows( idx ) {
			if ( idx > 0 ) {
				return conf.hopRows;
			}
			var rows = ( ordinal >= 2 ) ? conf.srcRowsInherit : conf.srcRows;
			return rows.concat( conf.hopRows );
		}

		var stepNodes = [];
		chain.forEach( function ( stepObj, i ) {
			var stepKids = [];

			// Step header (ordinal + inline remove) only for real multi-step chains: a
			// lone step needs no ordinal, and a `same` chain is an inherit marker rather
			// than a hop. Its collision with the slot-level Remove is resolved by
			// PLACEMENT — slot remove sits above the box, step remove inside it. COLOR
			// stays semantic: both removes red, both adds blue.
			if ( ! isSameChain && chain.length > 1 ) {
				stepKids.push( el( 'div', {
					key: 'sh',
					style: {
						display: 'flex', alignItems: 'center', justifyContent: 'space-between',
						gap: '8px', marginBottom: '4px'
					}
				}, [
					el( 'span', {
						key: 'n',
						style: { fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.4px', opacity: 0.65 }
					}, __( 'Step', 'generateblocks' ) + ' ' + ( i + 1 ) ),
					el( Button, {
						key: 'rm',
						variant: 'tertiary',
						size: 'small',
						isDestructive: true,
						style: { marginRight: '-6px' },
						onClick: function () {
							writeChainAt( i, null );
						}
					}, __( 'Remove step', 'generateblocks' ) )
				] ) );
			}

			stepKids.push( el( SelectControl, {
				key: 'src',
				// The label is ALWAYS present (an unlabelled control is unusable to a
				// screen reader); on a single-step chain the group caption says the same
				// word, so only the VISIBLE copy is suppressed.
				label: __( 'Source', 'generateblocks' ),
				hideLabelFromVision: chain.length <= 1,
				value: toEngine( conf, stepObj.slug ),
				options: stepRows( i ),
				onChange: function ( v ) {
					if ( ! v ) {
						writeChainAt( i, null );
						return;
					}
					var slug = toWire( conf, v );
					// Keep the arg only when the new slug consumes the same kind of arg;
					// keep `limit` always (it bounds the step, not the arg).
					var keepArg = argKind( conf, slug ) && argKind( conf, slug ) === argKind( conf, stepObj.slug );
					writeChainAt( i, step( slug, keepArg ? stepObj.arg : null, stepObj.limit ) );
				},
				__nextHasNoMarginBottom: true
			} ) );

			var kind = argKind( conf, stepObj.slug );
			if ( 'ref' === kind || 'entries' === kind ) {
				var argCfg = ( 'entries' === kind ? conf.entriesOption : conf.refOption ) || {};
				var commitArg = function ( v ) {
					writeChainAt( i, step( stepObj.slug, v || null, stepObj.limit ) );
				};
				stepKids.push( el( 'div', { key: 'arg', style: STACKED, className: 'bws-slot-fold' },
					FieldCombo
						? el( FieldCombo, {
							optionKey: 'key',
							label: argCfg.label,
							help: argCfg.help,
							placeholder: argCfg.placeholder,
							typeDefault: argCfg.typeDefault,
							context: hopContext( stepObj, commitArg )
						} )
						: el( TextControl, {
							label: argCfg.label,
							value: stepObj.arg || '',
							placeholder: argCfg.placeholder,
							help: __( 'Field discovery unavailable — free text only.', 'generateblocks' ),
							onChange: commitArg,
							__nextHasNoMarginBottom: true
						} )
				) );
				if ( ! stepObj.arg ) {
					// A hop with no field has nothing to hop THROUGH: it serializes as a
					// bare slug and resolves to nothing. Say so where it is fixable —
					// `canAppend` blocks BUILDING past an incomplete hop but nothing stops
					// one being serialized.
					stepKids.push( el( 'p', {
						key: 'argwarn',
						style: { fontSize: '11px', color: '#a00', margin: '4px 0 0' }
					}, __( 'Needs a field — this step resolves to nothing until set.', 'generateblocks' ) ) );
				}
			} else if ( 'taxonomy' === kind ) {
				stepKids.push( el( 'div', { key: 'arg', style: STACKED },
					el( SelectControl, {
						label: __( 'Taxonomy', 'generateblocks' ),
						value: stepObj.arg || '',
						options: conf.taxonomies,
						onChange: function ( v ) {
							writeChainAt( i, step( 'terms', v || null, stepObj.limit ) );
						},
						__nextHasNoMarginBottom: true
					} )
				) );
			}

			stepNodes.push( el( 'div', {
				key: 'step-' + i,
				style: i > 0 ? STEP_RULE : null
			}, stepKids ) );
		} );

		// No chain yet → one empty picker that seeds step 1. Reachable on slot 1
		// (legitimately unset = current) and on a slot ≥2 whose wire was hand-edited
		// to drop its src token.
		if ( ! chain.length ) {
			stepNodes.push( el( SelectControl, {
				key: 'src-new',
				label: __( 'Source', 'generateblocks' ),
				value: '',
				options: stepRows( 0 ),
				onChange: function ( v ) {
					if ( v ) {
						writeChain( [ step( toWire( conf, v ) ) ] );
					}
				},
				__nextHasNoMarginBottom: true
			} ) );
		}

		// Append-a-hop: only off a real (non-inherit) chain, and only once the last
		// step is complete, so a half-built step is never serialized. Lives INSIDE the
		// source box because it appends to THIS chain — unlike Add slot, which sits at
		// the slot's outer edge.
		var last = chain.length ? chain[ chain.length - 1 ] : null;
		var canAppend = ! isSameChain && last && ( ! argKind( conf, last.slug ) || last.arg ) && conf.hopRows.length;
		if ( canAppend ) {
			stepNodes.push( el( 'div', { key: 'addhop', style: { marginTop: '8px' } },
				el( Button, {
					variant: 'tertiary',
					size: 'small',
					onClick: function () {
						writeChain( chain.concat( [ step( toWire( conf, conf.hopRows[ 0 ].value ) ) ] ) );
					}
				}, '+ ' + __( 'Add hop', 'generateblocks' ) )
			) );
		}

		children.push( el( 'div', { key: 'srcgroup', style: GROUP_BOX }, [
			el( 'span', { key: 'cap', style: GROUP_CAP },
				chain.length > 1 ? __( 'Source path', 'generateblocks' ) : __( 'Source', 'generateblocks' ) )
		].concat( stepNodes ) ) );

		// ── Field group ─────────────────────────────────────────────────────
		// The read-kind select plus (when the kind is `key`) the whole field picker are
		// ONE decision about what to read, so they share one box and take no internal
		// rule — unlike the source group, where each step is a separate hop.
		if ( conf.readRows.length ) {
			var read = slot.read;
			var readVal = '';
			if ( read && 'key' === read.kind ) { readVal = 'key'; }
			if ( read && 'analog' === read.kind ) { readVal = read.slug; }
			if ( read && 'same' === read.kind ) { readVal = 'same'; }

			var readNodes = [ el( SelectControl, {
				key: 'read',
				// The caption carries the group name; this select picks the KIND. Its
				// label is the base read definition's own noun ("Text Field", …),
				// arriving with the enum rather than hand-authored beside it.
				label: conf.readLabel || __( 'Field', 'generateblocks' ),
				hideLabelFromVision: true,
				value: readVal,
				options: ( ordinal >= 2 && conf.readRowsInherit.length ) ? conf.readRowsInherit : conf.readRows,
				onChange: function ( v ) {
					if ( 'same' === v ) {
						writeRead( { kind: 'same' } );
					} else if ( 'key' === v ) {
						writeRead( { kind: 'key', field: ( read && read.field ) || '' } );
					} else if ( v ) {
						writeRead( { kind: 'analog', slug: v } );
					} else {
						writeRead( null );
					}
				},
				__nextHasNoMarginBottom: true
			} ) ];

			if ( read && 'key' === read.kind ) {
				var keyCfg = conf.keyOption || {};
				var commitField = function ( field ) {
					writeRead( { kind: 'key', field: field } );
				};
				readNodes.push( el( 'div', { key: 'readArg', style: STACKED, className: 'bws-slot-fold' },
					FieldCombo
						? el( FieldCombo, {
							optionKey: 'key',
							label: keyCfg.label,
							help: keyCfg.help,
							placeholder: keyCfg.placeholder,
							dynamicLabel: keyCfg.dynamicLabel,
							// Row scope, when the container has one, with the repeater name
							// passed EXPLICITLY: under the fold a slot's own read is also
							// spelled `key(...)`, so the picker must not reach outward for
							// "the bare key" to discover its scope.
							scope: conf.fieldScope,
							scopeKey: conf.scopeStateKey ? ( state[ conf.scopeStateKey ] || '' ) : void 0,
							context: fieldContext( slot, commitField )
						} )
						: el( TextControl, {
							label: keyCfg.label,
							value: read.field || '',
							placeholder: keyCfg.placeholder,
							help: __( 'Field discovery unavailable — free text only.', 'generateblocks' ),
							onChange: commitField,
							__nextHasNoMarginBottom: true
						} )
				) );
			}

			children.push( el( 'div', { key: 'readgroup', style: GROUP_BOX }, [
				el( 'span', { key: 'cap', style: GROUP_CAP }, conf.readLabel || __( 'Field', 'generateblocks' ) )
			].concat( readNodes ) ) );
		}

		// The slot rule CLOSES the slot rather than opening it: a leading rule made
		// slot 1 open with a divider under the tag description, reading as header
		// chrome, and unlike every single-slot tag where nothing precedes the first
		// control. Closing means the rule always follows content it summarises, and on
		// the last slot it separates Add from the slot it would extend. Heaviest rule
		// in the control (2px) so nesting reads by weight. Suppressed on the final slot
		// when no Add follows — a closing rule with nothing after it dangles.
		if ( ! isLast || count < conf.max ) {
			children.push( el( 'div', {
				key: 'rule',
				style: { borderTop: '2px solid #bbb', margin: '4px 0 12px' }
			} ) );
		}

		// ADD lives on the LAST slot only — one add affordance per tag, at the bottom
		// of the stack. `secondary` at DEFAULT size follows the one in-repo precedent
		// for a primary action inside a tag modal (the media picker): this is the
		// repeater's primary affordance and the only way to grow the tag.
		if ( isLast && count < conf.max ) {
			/* translators: %s: the container's slot noun, e.g. "attempt", "field", "column". */
			var addLabel = conf.noun
				? ( wp.i18n && wp.i18n.sprintf
					? wp.i18n.sprintf( __( 'Add %s', 'generateblocks' ), conf.noun )
					: 'Add ' + conf.noun )
				: __( 'Add', 'generateblocks' );
			children.push( el( Button, {
				key: 'addslot',
				variant: 'secondary',
				onClick: addSlot
			}, '+ ' + addLabel ) );
		}

		return el( Fragment, null, children );
	}

	wp.hooks.addFilter(
		'generateblocks.editor.tagSpecificControls',
		'bws/slot-fold-control',
		function ( element, allOptions, context ) {
			// Compose: if a prior filter hid this control, keep it hidden regardless of
			// registration order.
			if ( ! element || ! allOptions || ! context ) {
				return element;
			}
			var cfg = allOptions[ element.key ];
			if ( ! cfg || 'bws-slot-fold' !== cfg.type ) {
				return element;
			}
			return el( SlotFoldControl, {
				key: element.key,
				optionKey: element.key,
				label: cfg.label,
				conf: foldConfig( cfg ),
				context: context
			} );
		}
	);

	// Exported for the harness that pins the pure repeater logic (cardinality,
	// seeding, compaction + `same` materialization) — the part the PHP side
	// structurally cannot reach, because compaction lives entirely in the control and
	// the renderer only ever sees ALREADY-compacted wire.
	window.bwsSlotFoldRepeater = {
		seedSlot: seedSlot,
		slotCount: slotCount,
		readSlot: readSlot,
		removeSlotFrom: removeSlotFrom,
		legacyKeys: legacyKeys,
		inferIntent: inferIntent,
		foldConfig: foldConfig
	};
}() );
