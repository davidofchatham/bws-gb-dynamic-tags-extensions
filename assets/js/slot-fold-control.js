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
	// Hard dependencies. The grammar twin owns reading and writing the wire; the migrate
	// twin owns which legacy sibling keys this container has (so a commit does not delete
	// a TAG-level option that happens to share a slot axis's name). A partial fallback for
	// either would emit something the renderer does not parse, or strip wire that is still
	// load-bearing — both worse than not mounting. The option-group wrapper owns the class
	// names the boxes here are painted with, for the same reason: a fallback copy of them
	// is a second declaration of the box, which is the drift this control's boxes exist
	// downstream of.
	if ( ! window.bwsSlotFold || ! window.bwsSlotFoldMigrate || ! window.bwsOptionGroup ) {
		return;
	}

	var el            = wp.element.createElement;
	var Fragment      = wp.element.Fragment;
	var SelectControl = wp.components.SelectControl;
	var TextControl   = wp.components.TextControl;
	var Button        = wp.components.Button;
	var __            = wp.i18n ? wp.i18n.__ : function ( s ) { return s; };
	var fold          = window.bwsSlotFold;
	var migrate       = window.bwsSlotFoldMigrate;

	// ── Layout ───────────────────────────────────────────────────────────────
	// The box marks a GROUP (the whole source chain / the whole field picker), not an
	// individual step: boxing each step made a multi-step chain read as N peer objects
	// rather than one path, and stacked three borders deep once the field filters
	// landed. Nesting reads by WEIGHT instead — slot rule 2px > step rule 1px.
	// No marginBottom anywhere: the modal content column already applies a 15px
	// row-gap, so a margin of our own double-spaces against it.
	//
	// The box ITSELF is not declared here. `bws-group` is owned by option-group.js,
	// which draws the same box around a BASE tag's separately-rendered controls — and a
	// slot's box and a base tag's box being two declarations is exactly how the base-tag
	// chain control came to ship an untinted variant with a differently-placed caption.
	var STEP_RULE = { borderTop: '1px solid #ddd', marginTop: '10px', paddingTop: '10px' };
	var CLS = window.bwsOptionGroup.CLS;
	// Stock control labels sit tight against whatever precedes them, so a label
	// following another control reads as belonging to it. Space the OWNER — we do not
	// control the stock components' internal markup.
	var STACKED = { marginTop: '14px' };

	// No stylesheet of its own any more. Both rules this file used to inject — the tinted
	// box showing through a ComboboxControl's suggestions container, and that control's
	// label sitting flush against the filters above it — are properties of being INSIDE A
	// BOX, not of being inside a slot, and base tags have boxes now. They live with the
	// box, in option-group.js. The `bws-slot-fold` class stays on the picker wrappers as
	// a hook for anything genuinely slot-specific.

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
			// The root an absent source spells. On a base tag that is the tag's ambient
			// entity; on a SLOT it is slot 1's `current` (a slot ≥2 spells its absence
			// `same`, which chainSteps holds rather than reading from here). Both are
			// derived from the row their own enum leads with, so neither can disagree
			// with the list the author picks from. See bws_build_src_chain_option() /
			// bws_build_fold_slot_options().
			defaultRoot: c.defaultRoot || '',
			// The full step vocabulary, one record per WIRE slug (#70): label, which arg
			// key the step consumes, the kinds it accepts (the ENGINE's own refusal
			// list) and the kind it produces. See bws_fold_wire_vocabulary(). Absent
			// facts stay permissive — this feeds a DISPLAY filter, never a gate.
			steps: c.steps || {},
			// The per-container OFFER: an ordered slug list, the only per-container
			// step fact. Labels come off `steps`, never typed beside the offer.
			offer: c.offer || [],
			// Root token → static resolved kind (only `site` is static).
			roots: c.roots || {},
			// The per-step limit control's whole vocabulary — label, placeholder and
			// BOTH help forms (#95). Container-invariant, because a step's `limit[N]`
			// belongs to the wire's step grammar, and shipped for the same reason every
			// other string here is: a control that re-types one re-creates the drift the
			// derived config exists to remove. See bws_fold_wire_vocabulary().
			limitOption: c.limitOption || {},
			readRows: c.readRows || [],
			readRowsInherit: c.readRowsInherit || [],
			readLabel: c.readLabel || '',
			taxonomies: c.taxonomies || [],
			refOption: c.refOption || null,
			keyOption: c.keyOption || null,
			entriesOption: c.entriesOption || null,
			// The LEGACY per-slot axes, PHP-derived (bws_fold_slot_flat_axes) — this
			// control never lists them. A hand-kept list deleted a try_ template's
			// TAG-level `limit` and a try_datetime_single's TAG-level `key` on first
			// touch, because both are spelled exactly like slot 1's axes.
			flatAxes: c.flatAxes || [],
			// Which tag-level option names the repeater a row-scoped picker narrows to.
			// The control passes its VALUE down as an explicit `scopeKey` prop rather
			// than letting the picker reach outward for a bare `key` — under the fold
			// that name means something else one level in.
			scopeStateKey: c.scopeStateKey || '',
			fieldScope: c.fieldScope || ''
		};
	}

	/** The step record a wire slug names, or null — known-ness IS presence in `steps`. */
	function stepDef( conf, slug ) {
		return ( conf.steps && conf.steps[ slug ] ) || null;
	}

	/**
	 * The ARG a step consumes — the compiler seam's own value (`field` / `slug`),
	 * arriving on the vocabulary record, never typed here. Comparing two slugs' answers
	 * is what decides whether a slug switch keeps the field: two steps that both
	 * consume a FIELD keep it across the switch. (The retired comparison used engine
	 * spellings, under which `refs` ↔ `entries` compared unequal and dropped the field
	 * — unreachable only because no container offered both.)
	 *
	 * '' means the slug takes no arg — a root, an unknown slug, or a registration that
	 * shipped no vocabulary — which is what gates the Add-step row.
	 */
	function stepArg( conf, slug ) {
		var def = stepDef( conf, slug );
		return ( def && def.arg ) || '';
	}

	/**
	 * Select rows for the per-container OFFER, labelled from the one vocabulary. A slug
	 * with no record keeps its slug as its label — display-permissive, matching the
	 * stored-slug rule below.
	 */
	function offerRows( conf ) {
		return ( conf.offer || [] ).map( function ( slug ) {
			var def = stepDef( conf, slug );
			return { value: slug, label: ( def && def.label ) || slug };
		} );
	}

	// ── Incomplete-step / incomplete-read warnings ──────────────────────────
	//
	// One shape, one voice, one place: "This <noun> will be skipped unless …". The
	// NOUN is the container's registered one (attempt / field / column), so the warning
	// names what the author sees on the panel header and the Add button rather than a
	// third word for the same thing.
	//
	// THE PROMISE IS THE SEAM'S, NOT THIS CONTROL'S. "Will be skipped" is true because
	// bws_fold_slot_flat_options() skips an incomplete slot — including, since the
	// `'step'` reason landed, a `terms` step with no taxonomy. Before that it flattened
	// such a step to an empty `srcTermIn`, which is how "no term step" is spelled, so the
	// slot silently read the UN-HOPPED entity and handed the author a plausible wrong
	// value. If that skip is ever relaxed, this wording is a lie that reads as
	// reassurance, and it must move with it.

	/** `sprintf` if wp.i18n has it, else naive %s substitution. */
	function fmt( pattern, arg ) {
		return ( wp.i18n && wp.i18n.sprintf )
			? wp.i18n.sprintf( pattern, arg )
			: pattern.replace( '%s', arg );
	}

	function warnNode( keyName, text ) {
		return el( 'p', {
			key: keyName,
			style: { fontSize: '11px', color: '#a00', margin: '4px 0 0' }
		}, text );
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
	 *
	 * A selecting container with NO per-slot read axis (`try_permalink` and friends,
	 * whose read is a tag-level option) seeds no read either: the sentinel would name
	 * an axis the tag does not have, and no control would render it.
	 */
	function seedSlot( conf ) {
		var slot = emptySlot();
		slot.chain = [ step( 'same' ) ];
		slot.read = ( conf.combining || ! conf.perSlotUse ) ? null : { kind: 'same' };
		return slot;
	}

	/**
	 * Legacy sibling keys for slot n — dropped on ANY commit (touch-migration).
	 *
	 * Container-derived, never listed here: only the axes this container owns PER SLOT
	 * are the slot's to delete. On a try_ template `limit` (and on the read-less shapes
	 * `use`/`key`) is a TAG-level option spelled exactly like slot 1's axis, so a
	 * hand-kept list deleted the option the resolver reads.
	 */
	function legacyKeys( n, conf ) {
		return migrate.legacyKeys( conf || {}, n );
	}

	/**
	 * Read slot n as a struct whichever wire era it is stored in.
	 *
	 * The legacy read goes through the FILTERED state for the same reason the delete list
	 * is derived: an unfiltered map hands a tag-level `key` to the mapper, which folds it
	 * into slot 1 as that slot's read.
	 */
	function readSlot( n, state, conf ) {
		var raw = state[ fold.slotKey( n ) ] || '';
		if ( raw ) {
			var parsed = fold.parseSlot( raw, conf.container );
			return parsed.error ? null : parsed;
		}
		var rec = fold.foldFromFlat( n, migrate.mapperState( state, conf ), conf.combining, conf.perSlotUse );
		return ( rec && rec.slot ) ? rec.slot : null;
	}

	/**
	 * Highest slot ordinal holding a value, floored at the container's minimum.
	 * Counts a slot present in EITHER era, so an unmigrated tag mounts with its true
	 * slot count.
	 */
	function slotCount( state, conf ) {
		var legacy = migrate.mapperState( state, conf );
		var highest = 0;
		for ( var i = 1; i <= conf.max; i++ ) {
			if ( state[ fold.slotKey( i ) ] ) {
				highest = i;
			} else if ( fold.foldFromFlat( i, legacy, conf.combining, conf.perSlotUse ) ) {
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
			delete upd[ fold.slotKey( k ) ];
			legacyKeys( k, conf ).forEach( function ( lk ) {
				delete upd[ lk ];
			} );
		}
		survivors.forEach( function ( s, idx ) {
			var v = fold.emitSlot( s );
			if ( v ) {
				upd[ fold.slotKey( idx + 1 ) ] = v;
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

	/** Context for a step's reference/repeater field argument. */
	function stepContext( stepObj, commitArg ) {
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
	 * `refs` is deliberately NOT preset: the step target's post type is not reliably
	 * known until ref-step parity, so presetting would falsely assert a kind. Leaving
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

	/**
	 * The CHAIN EDITOR — one box holding every step of a source chain.
	 *
	 * Extracted from SlotFoldControl so the base-tag source control edits chains
	 * through the same component rather than a second copy of it. A copy would be
	 * the drift the derived config exists to prevent: the enums, labels, nouns and
	 * step vocabulary all arrive on the PHP option definition, and a second renderer is
	 * where a hand-authored third spelling of `terms` gets in.
	 *
	 * The wire supports an ordered chain, so this must edit EVERY step: a single-step
	 * version silently TRUNCATED steps 2+ whenever the author touched step 1.
	 *
	 * @param {Object}   props.conf           Derived fold/chain config (foldConfig shape).
	 * @param {Array}    props.chain          Parsed chain (grammar shape).
	 * @param {Function} props.onChange       fn( nextChain ) — the ONLY way out.
	 * @param {boolean}  props.inheritOnEmpty Emptying the chain falls back to an
	 *                                        explicit `same` rather than to absence.
	 *                                        True for a slot ≥2 (losing an inherit to
	 *                                        a step deletion is a silent meaning
	 *                                        change); false where empty legitimately
	 *                                        means the ambient entity.
	 * @param {string}   props.slotNoun       The container's registered unit noun.
	 * @param {Object}   props.stepContext     fn( step, commit ) — field-picker bridge.
	 * @return {Array} Step nodes, ready to place inside the caller's group box.
	 */
	function chainSteps( props ) {
		var conf = props.conf;
		var slotNoun = props.slotNoun;
		var FieldCombo = window.bwsFieldComboControl;
		var stepContext = props.stepContext;
		var chain = ( props.chain || [] ).slice();

		// DISPLAY the root that absence spells, rather than rendering an empty picker.
		// A SelectControl whose value is `''` matches no row, so the browser paints the
		// first one — "Current" on slot 1, "Same as Previous Source" on slot ≥2 — while
		// the control believes nothing is selected. The row on screen then cannot be
		// chosen (selecting the displayed value fires no change event), and with no step
		// in hand there is nothing for `+ Add step` to append to, so it never appears.
		// Same defect the base-tag control carried, same fix; it lives HERE now so both
		// callers get it from one place.
		//
		// Display only: the phantom step is never written. A commit that would restate
		// slot 1's default root is stripped back to absence at the caller, so the wire
		// is byte-identical to what an untouched slot already serializes.
		if ( ! chain.length ) {
			var implied = props.inheritOnEmpty ? 'same' : ( conf.defaultRoot || '' );
			if ( implied ) {
				chain = [ step( implied ) ];
			}
		}

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
			if ( props.inheritOnEmpty && ! next.length ) {
				next = [ step( 'same' ) ];
			}
			props.onChange( next );
		}

		/**
		 * What the chain has resolved to by position `idx` — the kind a step there
		 * would be handed.
		 *
		 * Mirrors bws_fold_chain_resolution()'s tail: the last step's produced kind, or
		 * the root's where that is STATIC. Every other root resolves at render (a bare
		 * tag is a post on a single, a term on a term archive), so it answers '' and
		 * every step is offered — the editor must not guess an ambient kind.
		 */
		function kindAt( idx ) {
			var prev = chain[ idx - 1 ];
			if ( ! prev ) {
				return '';
			}
			var def = stepDef( conf, prev.slug );
			if ( def && def.produces ) {
				return def.produces;
			}
			return ( idx > 1 ) ? '' : ( ( conf.roots || {} )[ prev.slug ] || '' );
		}

		/**
		 * Steps offerable at `idx`, given what the chain resolves to just before it.
		 *
		 * Offering a step the engine refuses authors wire that renders nothing and says
		 * so nowhere — a `terms` step after a `site` root was the reported case, and it
		 * was offered off every root. The allowlist is the engine's own
		 * (BWS_TRAVERSAL_STEP_INPUT_KINDS, reaching here through the option definition),
		 * never a second list.
		 *
		 * `keep` is the slug the step at `idx` ALREADY holds. It is always included,
		 * whatever the filter says: a SelectControl whose value is missing from its own
		 * options paints a different row while believing nothing is selected, which is
		 * the defect this control just fixed at the other end. Stored wire is shown as
		 * stored; only what the author may ADD is filtered.
		 */
		function offerableSteps( idx, keep ) {
			var kind = kindAt( idx );
			var rows = offerRows( conf );
			if ( ! kind ) {
				return rows;
			}
			return rows.filter( function ( row ) {
				if ( keep && row.value === keep ) {
					return true;
				}
				var def = stepDef( conf, row.value );
				var allowed = def && def.accepts;
				return ! allowed || allowed.indexOf( kind ) !== -1;
			} );
		}

		/**
		 * Rows for a step by POSITION: only the first step may start a source.
		 *
		 * A STORED slug always gets its own row, whatever the offer says — absence from
		 * `steps` means "offer it", never "refuse it". A SelectControl whose value is
		 * missing from its own options paints a different row while believing nothing
		 * is selected, which is the unselectable-row defect this control fixed twice.
		 */
		function rowsAt( idx ) {
			var held = chain[ idx ] ? chain[ idx ].slug : '';
			var rows;
			if ( idx > 0 ) {
				rows = offerableSteps( idx, held );
			} else {
				var roots = props.inheritOnEmpty ? conf.srcRowsInherit : conf.srcRows;
				rows = roots.concat( offerableSteps( 0, held ) );
			}
			if ( held && ! rows.some( function ( r ) { return r.value === held; } ) ) {
				var def = stepDef( conf, held );
				rows = rows.concat( [ { value: held, label: ( def && def.label ) || held } ] );
			}
			return rows;
		}

		var stepNodes = [];
		chain.forEach( function ( stepObj, i ) {
			var stepKids = [];

			// Step header (ordinal + inline remove) only for real multi-step chains: a
			// lone step needs no ordinal, and a `same` chain is an inherit marker rather
			// than a step. Its collision with the slot-level Remove is resolved by
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
				value: stepObj.slug,
				options: rowsAt( i ),
				onChange: function ( v ) {
					if ( ! v ) {
						writeChainAt( i, null );
						return;
					}
					// Keep the arg only when the new slug consumes the SAME arg (the
					// vocabulary record's `arg`, so `refs` ↔ `entries` keep the field);
					// keep `limit` always (it bounds the step, not the arg).
					var keepArg = stepArg( conf, v ) && stepArg( conf, v ) === stepArg( conf, stepObj.slug );
					writeChainAt( i, step( v, keepArg ? stepObj.arg : null, stepObj.limit ) );
				},
				__nextHasNoMarginBottom: true
			} ) );

			// Which arg control a step renders is the SLUG's to answer; whether it is a
			// step at all is presence in the vocabulary. Two questions the retired
			// argKind() answered with one engine-spelled string.
			var known = !! stepDef( conf, stepObj.slug );
			if ( known && ( 'refs' === stepObj.slug || 'entries' === stepObj.slug ) ) {
				var argCfg = ( 'entries' === stepObj.slug ? conf.entriesOption : conf.refOption ) || {};
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
							context: stepContext( stepObj, commitArg )
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
					// A step with no field has nothing to step THROUGH: it serializes as a
					// bare slug and resolves to nothing. Say so where it is fixable —
					// `canAppend` blocks BUILDING past an incomplete step but nothing stops
					// one being serialized.
					stepKids.push( warnNode( 'argwarn', fmt(
						/* translators: %s: the container's slot noun (attempt, field, column). */
						__( 'This %s will be skipped unless a field is set.', 'generateblocks' ),
						slotNoun
					) ) );
				}
			} else if ( known && 'terms' === stepObj.slug ) {
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
				if ( ! stepObj.arg ) {
					stepKids.push( warnNode( 'argwarn', fmt(
						/* translators: %s: the container's slot noun (attempt, field, column). */
						__( 'This %s will be skipped unless a taxonomy is set.', 'generateblocks' ),
						slotNoun
					) ) );
				}
			}

			// ── Per-step limit ──────────────────────────────────────────────
			// On every FANNING step, and only those: a step that resolves one source
			// has nothing to bound. Scoped to the STEP — the tag-level `limit` is a
			// different quantity (per-input versus whole-list) and one field would
			// misstate both. Its control retired with the flat spelling it belongs to
			// (#62); the value is still read wherever it is written.
			//
			// Empty means unlimited, and `0` is never serialized. The engine already
			// agrees by construction: it treats `>0` as a bound and anything else as
			// none, so absent and `0` are one behaviour under two spellings, and
			// writing both would put a distinction on the wire that means nothing.
			// `-1` is the older spelling of unlimited and normalizes away identically;
			// it stays parseable for hand-edited wire.
			//
			// EVERY STRING HERE ARRIVES ON THE OPTION DEFINITION (#95), including the
			// choice between the two help forms — the control picks, it never composes.
			// The per-input clause appears only once an EARLIER step actually fans:
			// with one input, per-input and total coincide, so the clause would ask the
			// author to reason about a distinction that cannot arise there. The
			// predicate is the grammar twin's, which is the renderer's own
			// (bws_fold_chain_fanning_steps) — deriving it from the step INDEX here
			// would be a second rule, and it would be wrong on a chain whose earlier
			// steps are single-valued.
			if ( known ) {
				var limitCfg = conf.limitOption || {};
				var upstreamFans = fold.chainFanningSteps( chain ).some( function ( j ) {
					return j < i;
				} );
				stepKids.push( el( 'div', { key: 'limit', style: STACKED },
					el( TextControl, {
						type: 'number',
						label: limitCfg.label,
						value: ( stepObj.limit === null || stepObj.limit === undefined ) ? '' : String( stepObj.limit ),
						placeholder: limitCfg.placeholder,
						help: upstreamFans ? limitCfg.helpFanning : limitCfg.help,
						onChange: function ( v ) {
							var n = parseInt( v, 10 );
							writeChainAt( i, step( stepObj.slug, stepObj.arg, ( isNaN( n ) || n <= 0 ) ? null : n ) );
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

		// No seed picker: an absent chain is DISPLAYED as the root it spells (above), so
		// the ordinary step picker renders and the author sees a selection that is real.
		// The seed picker that used to stand here is what painted an unselectable row
		// and doubled the group's own caption — it labelled itself "Source" inside a box
		// already captioned "Source", because only the step picker suppresses the
		// visible label on a single-step chain.
		//
		// The one case it still leaves bare is a config with NO defaultRoot on slot 1,
		// which cannot happen from a shipped registration (the field derives from the
		// enum's own first row) and would mean the enum itself is empty.

		// Append-a-step: only off a real (non-inherit) chain, and only once the last
		// step is complete, so a half-built step is never serialized. Lives INSIDE the
		// source box because it appends to THIS chain — unlike Add slot, which sits at
		// the slot's outer edge.
		// The offer is what decides, not the registered list: on a `site` root the only
		// step a try_ slot registers (`terms`) is one the engine refuses, so there is
		// nothing to add and the button must not appear. Reading the raw offer here
		// instead would offer an Add that can only produce a dead step.
		var last = chain.length ? chain[ chain.length - 1 ] : null;
		var nextSteps = offerableSteps( chain.length, '' );
		var canAppend = ! isSameChain && last && ( ! stepArg( conf, last.slug ) || last.arg ) && nextSteps.length;
		if ( canAppend ) {
			stepNodes.push( el( 'div', { key: 'addstep', style: { marginTop: '8px' } },
				el( Button, {
					variant: 'tertiary',
					size: 'small',
					onClick: function () {
						props.onChange( chain.concat( [ step( nextSteps[ 0 ].value ) ] ) );
					}
				}, '+ ' + __( 'Add step', 'generateblocks' ) )
			) );
		}

		return stepNodes;
	}

	function SlotFoldControl( props ) {
		var ctx = props.context;
		var state = ctx.state || {};
		var setState = ctx.setState;
		var key = props.optionKey;
		// The option key IS the slot's wire spelling, so its ordinal is a DECODE, not a
		// parseInt: `AA` is slot 27, which parseInt reads as NaN.
		var ordinal = fold.slotOrdinal( key );
		var conf = props.conf;
		// The word every warning below names the unit with. Registered per container
		// (see the fold config); the fallback is inert vocabulary for a misregistered
		// tag, not a default worth relying on.
		var slotNoun = conf.noun || __( 'slot', 'generateblocks' );
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
			var rec = fold.foldFromFlat( ordinal, migrate.mapperState( state, conf ), conf.combining, conf.perSlotUse );
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
			legacyKeys( ordinal, conf ).forEach( function ( lk ) {
				delete upd[ lk ];
			} );
			setState( upd );
		}

		/** Write the chain, preserving every other axis of the slot. */
		function writeChain( chain ) {
			var next = Object.assign( {}, slot );
			next.chain = stripDefaultRoot( chain );
			write( next );
		}

		/**
		 * Drop a lone root that only RESTATES what an absent chain already spells.
		 *
		 * Reachable now that the default root is displayed: picking another source and
		 * picking `Current` back would otherwise serialize `src(current)` where the slot
		 * previously held nothing, so merely LOOKING at a slot could change its wire.
		 *
		 * Slot 1 only. A slot ≥2's `same` is written on purpose — absence there is a
		 * RESET rather than an inherit, so an explicit inherit has to be stated (see
		 * writeChainAt). Stripping it would silently convert one into the other.
		 */
		function stripDefaultRoot( chain ) {
			if ( ordinal >= 2 || 1 !== chain.length || ! conf.defaultRoot ) {
				return chain;
			}
			var only = chain[ 0 ];
			return ( only.slug === conf.defaultRoot && ! only.arg && ! only.limit ) ? [] : chain;
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
			upd[ fold.slotKey( count + 1 ) ] = fold.emitSlot( seedSlot( conf ) );
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
			// A container with NO per-slot read axis has ONE axis to vary, so the two-axis
			// vocabulary would name a field the slot cannot configure. `readRows`/
			// `keyOption` absent is the same test the read group makes — every shape is
			// read off the derived config, never off the container name.
			var intent   = inferIntent( slot );
			var hasRead  = !! ( conf.readRows.length || conf.keyOption );
			var advisory;
			if ( ! hasRead ) {
				advisory = 'both' === intent
					? __( 'Varies: source', 'generateblocks' )
					: __( 'Inherits the previous source — this slot repeats it until you change the source.', 'generateblocks' );
			} else {
				advisory = {
					context: __( 'Varies: source (field inherited)', 'generateblocks' ),
					field: __( 'Varies: field (source inherited)', 'generateblocks' ),
					both: __( 'Varies: source and field', 'generateblocks' )
				}[ intent ];
			}
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
		// step: a single-step version silently TRUNCATED steps 2+ whenever the author
		// touched step 1.
		var chain = slot.chain.slice();
		var stepNodes = chainSteps( {
			conf: conf,
			chain: chain,
			onChange: writeChain,
			inheritOnEmpty: ordinal >= 2,
			slotNoun: slotNoun,
			stepContext: stepContext
		} );

		children.push( el( 'div', { key: 'srcgroup', className: CLS.group }, [
			el( 'span', { key: 'caption', className: CLS.caption },
				chain.length > 1 ? __( 'Source path', 'generateblocks' ) : __( 'Source', 'generateblocks' ) )
		].concat( stepNodes ) ) );

		// ── Field group ─────────────────────────────────────────────────────
		// The read-kind select plus (when the kind is `key`) the whole field picker are
		// ONE decision about what to read, so they share one box and take no internal
		// rule — unlike the source group, where each step is a separate step.
		//
		// THREE read shapes, all read off the derived config rather than the container
		// name: a KIND enum plus picker (readRows non-empty — text/content/image/join),
		// a picker ALONE (`keyOnly`: a per-slot key with no `use` enum — try_email /
		// try_phone, whose only read is "that field"), and NOTHING (neither — the read
		// is a tag-level option, as on try_permalink). In keyOnly there is no row to
		// select `same` with, so an EMPTY field is the inherit: it writes an absent
		// read, which a selecting container already resolves by carry-forward.
		var read = slot.read;
		var keyOnly = ! conf.readRows.length && !! conf.keyOption;
		if ( conf.readRows.length || keyOnly ) {
			var readVal = '';
			if ( read && 'key' === read.kind ) { readVal = 'key'; }
			if ( read && 'analog' === read.kind ) { readVal = read.slug; }
			if ( read && 'same' === read.kind ) { readVal = 'same'; }

			var readNodes = keyOnly ? [] : [ el( SelectControl, {
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

			if ( keyOnly || ( read && 'key' === read.kind ) ) {
				var keyCfg = conf.keyOption || {};
				var commitField = keyOnly
					// Clearing the field is how a keyOnly slot says "inherit" — there is
					// no enum row to say it with. An empty `key()` would instead be an
					// explicit read of nothing, which skips the slot.
					? function ( field ) {
						writeRead( field ? { kind: 'key', field: field } : null );
					}
					: function ( field ) {
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
							value: ( read && read.field ) || '',
							placeholder: keyCfg.placeholder,
							help: __( 'Field discovery unavailable — free text only.', 'generateblocks' ),
							onChange: commitField,
							__nextHasNoMarginBottom: true
						} )
				) );

				// A keyed read with no field reads nothing — the read-axis twin of the
				// step's dead-step warning, in the same words for the same reason. Only
				// where the kind was CHOSEN: in keyOnly an empty field IS the inherit
				// (no enum row exists to say it with), so it is a resting state, not a
				// hole.
				if ( ! keyOnly && read && ! read.field ) {
					readNodes.push( warnNode( 'readwarn', fmt(
						/* translators: %s: the container's slot noun (attempt, field, column). */
						__( 'This %s will be skipped unless a field is set.', 'generateblocks' ),
						slotNoun
					) ) );
				}
			}

			// keyOnly has no read definition to take a noun from, so the caption comes
			// off the KEY definition instead — still derived, never authored here.
			var readCaption = conf.readLabel
				|| ( conf.keyOption && conf.keyOption.label )
				|| __( 'Field', 'generateblocks' );
			children.push( el( 'div', { key: 'readgroup', className: CLS.group }, [
				el( 'span', { key: 'caption', className: CLS.caption }, readCaption )
			].concat( readNodes ) ) );
		}

		// The slot rule separates one slot from the NEXT, so it renders between slots and
		// nowhere else. It closes the slot rather than opening it (a leading rule made
		// slot 1 open with a divider under the tag description, reading as header chrome
		// and unlike every single-slot tag, where nothing precedes the first control) —
		// but "closes" is about placement, not about appearing after the last one.
		//
		// It used to also render on the final slot whenever an Add was coming, on the
		// reasoning that it separated Add from the slot it would extend. It does not:
		// Add belongs to the whole repeater rather than to the slot above it, so a rule
		// there divides a thing from its own control.
		//
		// NO MARGIN, for the reason stated at the top of this file: this control returns
		// a Fragment, so the rule is a direct flex item of the modal's 15px-gap column
		// and is already spaced on both sides. It shipped with `margin: 8px 0`, which
		// bought 23px a side — the one place in the control that broke the file's own
		// no-margin rule. Spacing stays symmetric whichever way it is set: an asymmetric
		// rule reads as attached to the side it sits closer to, which is a claim about
		// grouping this rule is not making. Any residual unevenness is optical (a box
		// edge above, uppercase text below) and is not fixed by biasing the margin.
		//
		// Heaviest rule in the control (2px) so the nesting reads by weight.
		if ( ! isLast ) {
			children.push( el( 'div', {
				key: 'rule',
				style: { borderTop: '2px solid #bbb' }
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
		foldConfig: foldConfig,
		stepArg: stepArg,
		// The chain editor, so the base-tag source control renders the SAME steps.
		chainSteps: chainSteps,
		step: step
	};
}() );
