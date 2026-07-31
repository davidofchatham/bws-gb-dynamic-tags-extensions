/**
 * SPIKE B (FW-56/57) — `bws-proto-fold` composite control for {{proto_fold}}.
 *
 * NOT SHIPPED CODE (tools/, .distignore'd; enqueued only by the spike PHP).
 *
 * One control OWNS one folded `{N}:` option key (DECISION 2: control holds
 * parsed step-state, rewrites the WHOLE value on every commit). Renders:
 *   - slot chrome: header + Remove (any slot, OUT OF ORDER — compaction closes
 *     the hole) and, on the last slot, Add slot.
 *   - both axis sub-controls, always (source select [+ ref-field arg], read
 *     select [+ key-field arg]); `same` is an enum row on each, on slot ≥2.
 *
 * REPEATER MODEL (2026-07-30) — replaced the show_if reveal chain + intent
 * radio. Cardinality is now EXPLICIT (add/remove) rather than a side effect of
 * how far the author had configured; slots 1..MIN always render and slot N≥3
 * renders when it holds a value. The add button writes the explicit seed
 * `src(same);use(same)`, so no arming state exists to lose on remount. The 2×2
 * intent cell survives only as advisory text (inferIntent), never as a gate.
 *
 * Wire grammar = the Spike A frontrunner (opt-sep `;`, hop-sep `+`, step-sep
 * `,`, L1 `()`); slot values here carry no free-form text, so no `\:`/`\|`
 * escaping is needed in the spike (real build: escape free-form per Spike A).
 *
 * Source enum arrives DERIVED from the real base builder via
 * window.bwsProtoFold.srcOptions (anti-drift seam under test) — engine value
 * `ref` maps to wire slug `refs` (DECISION 3) at serialize time.
 *
 * @package BWS_Dynamic_Tags
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.hooks || ! wp.element || ! wp.components ) {
		return;
	}
	// RadioControl is no longer used (the intent radio was replaced by explicit
	// add/remove cardinality) — do not gate the control on its presence.
	if ( ! wp.components.SelectControl || ! wp.components.Button ) {
		return;
	}

	var el            = wp.element.createElement;
	var Fragment      = wp.element.Fragment;
	var SelectControl = wp.components.SelectControl;
	var TextControl   = wp.components.TextControl;
	var Button        = wp.components.Button;
	var __            = wp.i18n ? wp.i18n.__ : function ( s ) { return s; };

	// ── Grammar (frontrunner chars — mirror of the PHP spike parser) ─────────
	// Canonical (emit) + lenient accept classes (parse): `,`≡`;`, `+`≡`/`, `()`≡`[]`.
	var OPT_SEP    = ';';
	var OPT_CLASS  = [ ';', ',' ];
	var HOP_SEP    = '+';
	var HOP_RE     = /[+/]/;
	var STEP_SEP   = ',';
	var STEP_RE    = /[,;]/;
	var BR_OPEN    = '(';
	var BR_CLOSE   = ')';
	var BR_PAIRS   = { '(': ')', '[': ']' };
	var BR_CLOSERS = [ ')', ']' ];

	// Balance-aware tokenize on the opt-sep CLASS; per-token delimiter rule
	// (first open char fixes the structural pair; the other pair is inert).
	function tokenize( value ) {
		var toks = [], buf = '', depth = 0, pair = null;
		for ( var i = 0; i < value.length; i++ ) {
			var c = value[ i ];
			if ( depth === 0 ) {
				if ( BR_PAIRS[ c ] ) { pair = [ c, BR_PAIRS[ c ] ]; depth = 1; }
				else if ( BR_CLOSERS.indexOf( c ) !== -1 ) { return null; }
				else if ( OPT_CLASS.indexOf( c ) !== -1 ) { toks.push( buf ); buf = ''; continue; }
			} else {
				if ( c === pair[ 0 ] ) { depth++; }
				else if ( c === pair[ 1 ] ) { depth--; }
			}
			buf += c;
		}
		if ( depth !== 0 ) { return null; }
		toks.push( buf );
		return toks.map( function ( t ) { return t.trim(); } ).filter( Boolean );
	}

	// Parse a folded slot value → { chain: [{slug,arg,limit}], read: {kind,...}|null }.
	// Returns null on unparseable (lens falls to 'both' with raw preserved — spike
	// keeps it simple: unparseable resets the slot).
	function parseSlot( value ) {
		var toks = tokenize( value || '' );
		if ( toks === null ) { return null; }
		var slot = { chain: [], read: null };
		for ( var i = 0; i < toks.length; i++ ) {
			var tok = toks[ i ];
			// Per-token delimiter rule: first accepted open char fixes the pair.
			var p = -1, open = null;
			Object.keys( BR_PAIRS ).forEach( function ( o ) {
				var q = tok.indexOf( o );
				if ( q !== -1 && ( p === -1 || q < p ) ) { p = q; open = o; }
			} );
			if ( p === -1 ) { continue; }                    // bare tokens ignored in spike (no Option-R UI here)
			var close = BR_PAIRS[ open ];
			if ( tok[ tok.length - 1 ] !== close ) { return null; }
			// Close-then-reopen junk guard (mirror of the PHP parser).
			var depth = 0;
			for ( var j = p; j < tok.length; j++ ) {
				if ( tok[ j ] === open ) { depth++; }
				else if ( tok[ j ] === close ) {
					depth--;
					if ( depth === 0 && j < tok.length - 1 ) { return null; }
				}
			}
			var name = tok.slice( 0, p );
			var val  = tok.slice( p + 1, -1 );
			if ( 'src' === name ) {
				slot.chain = val.split( HOP_RE ).map( function ( seg ) {
					var parts = seg.trim().split( STEP_RE );
					return { slug: parts[ 0 ].trim(), arg: parts[ 1 ] ? parts[ 1 ].trim() : null, limit: parts[ 2 ] ? parts[ 2 ].trim() : null };
				} );
			} else if ( 'use' === name ) {
				slot.read = ( 'same' === val ) ? { kind: 'same' } : { kind: 'analog', slug: val };
			} else if ( 'key' === name ) {
				slot.read = { kind: 'key', field: val };
			}
		}
		return slot;
	}

	// Serialize { chain, read } → canonical folded value (src leads, read follows).
	// Empty struct → '' (caller deletes the key — the delete-omit idiom, so the
	// show_if_any not_empty reveal gate sees a truly absent slot).
	function serializeSlot( slot ) {
		var toks = [];
		if ( slot.chain && slot.chain.length ) {
			toks.push( 'src' + BR_OPEN + slot.chain.map( function ( s ) {
				var seg = s.slug;
				if ( s.arg ) { seg += STEP_SEP + s.arg; }
				if ( s.limit ) { seg += STEP_SEP + s.limit; }
				return seg;
			} ).join( HOP_SEP ) + BR_CLOSE );
		}
		var r = slot.read;
		if ( r ) {
			if ( 'same' === r.kind )        { toks.push( 'use' + BR_OPEN + 'same' + BR_CLOSE ); }
			else if ( 'key' === r.kind )    { toks.push( 'key' + BR_OPEN + ( r.field || '' ) + BR_CLOSE ); }
			else if ( 'analog' === r.kind && 'default' !== r.slug ) { toks.push( 'use' + BR_OPEN + r.slug + BR_CLOSE ); }
		}
		return toks.join( OPT_SEP );
	}

	// ── Lens (B2): infer the 2×2 intent cell from the parsed slot ────────────
	// source axis: chain [{slug:'same'}] → same, anything else (incl. empty=current) → new
	// read axis:   read {kind:'same'} → same, else → new
	// Cells: 'context' = new src + same read (A) · 'field' = same src + new read (B) · 'both' (C).
	// null slot → '' (radio unset — default-empty UX; slot stays "not real").
	function inferIntent( slot ) {
		if ( ! slot || ( ! slot.chain.length && ! slot.read ) ) { return ''; }
		var srcSame  = slot.chain.length === 1 && 'same' === slot.chain[ 0 ].slug;
		var readSame = !! ( slot.read && 'same' === slot.read.kind );
		if ( srcSame && readSame ) { return '';       }   // degenerate — treat as unset
		if ( readSame )            { return 'context'; }
		if ( srcSame )             { return 'field';   }
		return 'both';
	}

	// ── Mount-reconcile: synthesize a fold struct from LEGACY separate keys ──
	// JS port of bws_spike_fold_from_legacy (PHP is the reference — the shared
	// mapping must not fork). Returns {slot, legacy:true} | {flag} | null.
	function foldFromLegacy( n, state ) {
		var p   = ( 1 === n ) ? '' : n + '-';
		var src = state[ p + 'src' ] || '';
		var ref = state[ p + 'ref' ] || '';
		var use = state[ p + 'use' ] || '';
		var key = state[ p + 'key' ] || '';
		if ( ! src && ! ref && ! use && ! key ) { return null; }

		var chain = [];
		if ( 'ref' === src )                          { chain = [ { slug: 'refs', arg: ref || null, limit: null } ]; }
		else if ( 'same' === src )                    { chain = [ { slug: 'same', arg: null, limit: null } ]; }
		else if ( src && 'current' !== src )          { chain = [ { slug: src, arg: null, limit: null } ]; }

		var read;
		if ( 'key' === use || ( ! use && key && 1 === n ) ) {
			read = { kind: 'key', field: key };
		} else if ( use ) {
			read = ( 'same' === use ) ? { kind: 'same' } : { kind: 'analog', slug: use };
		} else if ( ! key ) {
			read = ( n >= 2 ) ? { kind: 'same' } : null;   // S1 synth vs slot-1 default
		} else {
			return { flag: 'legacy slot ' + n + ': key set without use (ambiguous FW-51 shape) — needs author review' };
		}
		return { slot: { chain: chain, read: read }, legacy: true };
	}

	// Legacy sibling keys for slot n — cleared on ANY commit (delete-omit), so a
	// confirmed modal writes the folded value and drops the old wire in one step.
	function legacyKeys( n ) {
		var p = ( 1 === n ) ? '' : n + '-';
		return [ p + 'src', p + 'ref', p + 'use', p + 'key' ];
	}

	// ── Repeater: cardinality, seeding, compaction ──────────────────────────
	// Mirrors of the PHP ceiling/floor (BWS_SPIKE_FOLD_MAX_SLOTS / _MIN_SLOTS).
	var MAX_SLOTS = 8;
	var MIN_SLOTS = 2;

	// The SEED for a newly added slot: explicit inherit on BOTH axes. Chosen over
	// a bare `same` word (which would need a second reserved bare vocabulary
	// competing with the Option-R type position) and over absence-means-inherit
	// (the FW-51 shape — meaning re-coupled to position, invisible to compaction).
	// Costs ~15 chars more than `2:same`; buys: parses under today's grammar,
	// both axes independently editable, and compaction can SEE the inheritance.
	function seedSlot() {
		return { chain: [ { slug: 'same', arg: null, limit: null } ], read: { kind: 'same' } };
	}

	// Highest slot ordinal holding a value. Cardinality = max(MIN, that) — the
	// control derives slot count from CONTENT, so it survives remount with no
	// external store (an "armed but empty" slot would not).
	//
	// MIN_SLOTS is a hard floor: slot 2 is always visible on a multislot tag,
	// even when empty. Removing slot 1 or 2 while exactly two are present
	// therefore compacts and leaves two visible (one carrying the survivor's
	// value, one empty) rather than dropping to a single slot.
	function slotCount( state ) {
		var highest = 0;
		for ( var i = 1; i <= MAX_SLOTS; i++ ) {
			if ( state[ String( i ) ] ) { highest = i; }
			else if ( foldFromLegacy( i, state ) ) { highest = i; }   // unmigrated wire still counts
		}
		return Math.max( MIN_SLOTS, highest );
	}

	// Read slot n as a struct regardless of which wire era it is stored in.
	function readSlot( n, state ) {
		var raw = state[ String( n ) ] || '';
		if ( raw ) { return parseSlot( raw ); }
		var rec = foldFromLegacy( n, state );
		return ( rec && rec.slot ) ? rec.slot : null;
	}

	// REMOVE WITH COMPACTION. Out-of-order removal must not leave a hole: slot
	// n+1's value slides down to n, and so on. One whole-object setState, so no
	// intermediate gap state is ever committed.
	//
	// THE HAZARD compaction introduces that hop-removal does not have: `same` is
	// a POSITIONAL backreference ("inherit from the previous slot"). Sliding a
	// slot down re-points its `same` at a DIFFERENT neighbour — a silent meaning
	// change. So before renumbering we MATERIALIZE the survivor's inherited axes
	// against the slot being removed: whatever slot n resolved that axis to
	// becomes explicit on slot n+1. Only the immediate successor can be affected
	// (only it referenced the removed slot), so this is a single fixup, not a
	// cascade.
	//
	// Slot 1 is not special-cased away here: promoting slot 2 into position 1
	// leaves it with a `same` that has no predecessor to point at, which is
	// exactly the case materialization already handles (it becomes explicit
	// before the slide). Any residual `same` at position 1 is then illegal and
	// is dropped to a plain unset axis.
	function removeSlotFrom( n, state, count ) {
		var removed  = readSlot( n, state );
		var successor = ( n + 1 <= count ) ? readSlot( n + 1, state ) : null;

		// Materialize the successor's inherited axes against the removed slot.
		if ( removed && successor ) {
			if ( successor.chain.length === 1 && 'same' === successor.chain[ 0 ].slug ) {
				successor.chain = removed.chain.slice();
			}
			if ( successor.read && 'same' === successor.read.kind ) {
				successor.read = removed.read ? JSON.parse( JSON.stringify( removed.read ) ) : null;
			}
		}

		// Collect survivors in order, with the fixed-up successor substituted in.
		var survivors = [];
		for ( var i = 1; i <= count; i++ ) {
			if ( i === n ) { continue; }
			var s = ( i === n + 1 && successor ) ? successor : readSlot( i, state );
			if ( s ) { survivors.push( s ); }
		}

		// Position 1 cannot hold an inherit — strip any that landed there.
		if ( survivors.length ) {
			var first = survivors[ 0 ];
			if ( first.chain.length === 1 && 'same' === first.chain[ 0 ].slug ) { first.chain = []; }
			if ( first.read && 'same' === first.read.kind ) { first.read = null; }
		}

		// Rewrite the whole block: clear every key (folded AND legacy siblings,
		// so a compaction also completes the touch-migration), then write the
		// survivors densely from 1.
		var upd = Object.assign( {}, state );
		for ( var k = 1; k <= MAX_SLOTS; k++ ) {
			delete upd[ String( k ) ];
			legacyKeys( k ).forEach( function ( lk ) { delete upd[ lk ]; } );
		}
		survivors.forEach( function ( s, idx ) {
			var v = serializeSlot( s );
			if ( v ) { upd[ String( idx + 1 ) ] = v; }
		} );
		return upd;
	}

	// ── Source enum — DERIVED from the base builder (window.bwsProtoFold) ────
	// Engine value → wire slug mapping (DECISION 3): ref → refs. 'same' row is
	// slot-≥2-only and the radio owns it here, so it's filtered from the select.
	function srcEnum() {
		var g = window.bwsProtoFold || {};
		return ( g.srcOptions || [] ).filter( function ( o ) {
			return 'same' !== o.value;
		} );
	}
	function engineToWire( v ) { return 'ref' === v ? 'refs' : v; }
	function wireToEngine( v ) { return 'refs' === v ? 'ref' : v; }

	var READ_OPTIONS = [
		{ value: '',      label: __( 'Default (intrinsic analog)', 'generateblocks' ) },
		{ value: 'title', label: __( 'Title/Name', 'generateblocks' ) },
		{ value: 'key',   label: __( 'Meta/Option Field', 'generateblocks' ) },
	];

	// ── Shared chrome (2026-07-31 trial) ────────────────────────────────────
	// The box marks a GROUP (the whole source chain / the whole field picker),
	// not an individual step — an earlier pass boxed each step, which made a
	// multi-hop chain read as N peer objects rather than one path, and stacked
	// three nested borders deep once the field filters landed. Steps inside the
	// source group are separated by the same rule the slots use, one level down.
	// NO marginBottom: the modal content column already applies a 15px row-gap
	// (.gb-dynamic-tag-modal__content), so a margin of our own double-spaces
	// against it. Let the host own between-box spacing; the box owns only its
	// own inset. (Found in trial 2026-07-31 with layout guides on.)
	// Tinted fill, with INPUTS deliberately white on top of it (trial 2026-07-31):
	// the group reads as a container and the fields read as fields. Stock
	// ComboboxControl already paints its own white wrapper, which is the intended
	// look, not the inset artifact it first appeared to be — so the fill stays and
	// the other inputs are matched to it rather than the fill being dropped.
	var GROUP_BOX = {
		border: '1px solid #e0e0e0', borderRadius: '2px',
		padding: '12px', background: 'rgba(0,0,0,0.02)',
	};
	// Between-step rule INSIDE the source group. Mirrors the slot separator
	// (which is 2px/#bbb at the outer level) at a lighter weight, so the nesting
	// reads by weight: slot rule > step rule. Used between STEPS only — the field
	// group does not need it (one decision, no internal boundary to draw).
	var STEP_RULE = { borderTop: '1px solid #ddd', marginTop: '10px', paddingTop: '10px' };
	// Group caption — same treatment as the slot header's title, one step down.
	var GROUP_CAP = {
		fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.4px',
		opacity: 0.65, marginBottom: '10px', display: 'block',
	};
	// Stock control labels sit tight against whatever precedes them, so a label
	// following another control reads as belonging to it. Space the OWNER, not
	// the label (we do not control the stock components' internal markup).
	var STACKED = { marginTop: '14px' };

	// ComboboxControl renders grey-plus-white-inset INSIDE its own border when it
	// sits on a tinted background (trial 2026-07-31). Cause, confirmed against
	// WP's shipped CSS: `__suggestions-container` carries the BORDER and padding
	// but declares NO background, so it is transparent — the group's tint shows
	// through it while the `__input` within paints itself white. One control,
	// two fills.
	//
	// So the fix is that one element, not the outer wrapper: give the bordered
	// box an opaque white fill and it reads as a single field edge to edge.
	// Scoped to the spike's own subtree; the stock component is untouched.
	var COMBO_FILL_CSS =
		'.bws-proto-fold .components-combobox-control__suggestions-container{background:#fff;}' +
		// The combobox's own LABEL sits flush against the two filter selects above
		// it, so it reads as belonging to them rather than to the field picker it
		// names. The label lives inside the shipped control's markup, so the
		// wrapper's margin cannot reach it — space it here. Scoped to the spike's
		// subtree; a real build would own this in its stylesheet.
		'.bws-proto-fold .components-combobox-control .components-base-control__label' +
		'{margin-top:12px;display:inline-block;}';

	// Inject once per page (spike-only; a real build would ship this in a
	// stylesheet rather than an inline <style>). DOM-guarded: the compaction
	// probe loads this file headlessly to test the pure wire logic, so nothing
	// at module scope may assume a document exists.
	if ( 'undefined' !== typeof document && ! document.getElementById( 'bws-proto-fold-css' ) ) {
		var styleEl = document.createElement( 'style' );
		styleEl.id  = 'bws-proto-fold-css';
		styleEl.appendChild( document.createTextNode( COMBO_FILL_CSS ) );
		document.head.appendChild( styleEl );
	}

	// ── Field-combo bridge (the field FILTERS under the fold) ───────────────
	//
	// THE PROBLEM: the shipped `bws-field-combo` control (Location filter + Type
	// filter + discovery combobox, ~760 lines) mounts per OPTION KEY and commits
	// with `upd[key] = value`. Under the fold there IS no `{N}-key` option — the
	// field lives INSIDE the slot value's `key(...)` token, owned by this control.
	// Two controls cannot own one key, and reimplementing discovery in the spike
	// would be both wasteful and a fidelity lie (the filters ARE the surface under
	// test).
	//
	// THE SEAM: FieldComboControl is a plain component taking `{optionKey, context}`
	// and touching state in exactly four places — `state[key]` (its own value),
	// `state[p+'src']` + `state[p+'srcTermIn']` (sibling source, for the Location
	// filter's kind preset), and `state.key` (tag-level repeater scope). So it can
	// be driven by a SYNTHETIC context that presents folded state in the legacy
	// shape it expects and funnels writes back into the folded value. No change to
	// the shipped control — which is the point: if the bridge needs the control
	// modified, the fold has broken it, and that is a finding.
	//
	// The kind preset is the interesting translation. Legacy read sibling `src`
	// tokens; under the fold the source is the CHAIN, so the preset must derive
	// from the chain's TERMINAL step (the step whose output the read applies to) —
	// FW-56's stated editor-UX floor, exercised here for real.
	// Bridge for a HOP's reference field. The shipped `ref` option is itself a
	// bws-field-combo (base-shared.php:146, "Relationship Field Key") — the spike
	// had downgraded it to a bare TextControl, which understated the real surface.
	// Deliberately UNSCOPED with no kind preset: `ref` names a relationship field
	// on the SOURCE post, and the hop target's post type is not reliably known
	// (SPEC V3), so presetting would falsely assert a kind. v2 type-filters this
	// to relationship/post_object.
	function foldedHopContext( step, commitArg ) {
		return {
			state: { key: step.arg || '' },
			setState: function ( upd ) {
				commitArg( ( upd && 'undefined' !== typeof upd.key ) ? upd.key : '' );
			},
		};
	}

	function foldedFieldContext( slot, state, commitRead ) {
		var terminal = slot.chain.length ? slot.chain[ slot.chain.length - 1 ] : null;
		var synth    = {};

		// NOTE — a real NAME COLLISION the fold exposes, worth recording even
		// though the spike sidesteps it: the shipped control reads the BARE `key`
		// two different ways. Under `scope:'row'` it is the TAG-LEVEL repeater
		// name (field-combo-control.js:529); otherwise it is the control's own
		// value. Legacy kept those apart by prefix — a slot's own key was `{N}-key`,
		// never bare. Under the fold every slot's field arrives as bare `key` in
		// the synthetic context, so both readings would land on one name. The
		// spike does not pass `scope:'row'`, so only the value reading is live
		// here; a real build combining the fold with {{table}}'s row-scoped columns
		// must disambiguate (pass the repeater name as an explicit prop rather
		// than smuggling it through state).

		// Present the terminal step in the legacy sibling shape the preset reads.
		// `refs` is deliberately NOT preset (SPEC V3: the hop target's post type is
		// not reliably known until ref-hop parity), matching legacy behavior — so
		// leaving it unmapped is correct, not an omission.
		if ( terminal ) {
			if ( 'site' === terminal.slug )      { synth.src = 'site'; }
			else if ( 'termIn' === terminal.slug ) { synth.srcTermIn = terminal.arg || '1'; }
			else if ( 'same' !== terminal.slug ) { synth.src = terminal.slug; }
		}

		// The control's own value: the folded read's field, under the bare `key`
		// name it mounts on.
		var read = slot.read;
		synth.key = ( read && 'key' === read.kind ) ? ( read.field || '' ) : '';

		return {
			state: synth,
			// FieldComboControl commits `upd.key = <field>` (or deletes it). Funnel
			// that single key back into the folded read; ignore everything else it
			// might carry, since the synthetic siblings are display-only.
			setState: function ( upd ) {
				var next = ( upd && 'undefined' !== typeof upd.key ) ? upd.key : '';
				commitRead( next );
			},
		};
	}

	function ProtoFoldControl( props ) {
		var ctx      = props.context;
		var state    = ctx.state || {};
		var setState = ctx.setState;
		var key      = props.optionKey;          // '1' | '2' | '3'
		var ordinal  = parseInt( key, 10 );
		var raw      = state[ key ] || '';

		// MOUNT-RECONCILE: folded value absent → recover from legacy separate
		// keys (shared mapping). Display-only until the author commits (the
		// modal-confirm boundary keeps the stored wire untouched on cancel).
		var slot      = null;
		var recovered = false;
		var legacyFlag = '';
		if ( raw ) {
			slot = parseSlot( raw );
		} else {
			var rec = foldFromLegacy( ordinal, state );
			if ( rec && rec.flag ) { legacyFlag = rec.flag; }
			else if ( rec )        { slot = rec.slot; recovered = true; }
		}
		// UNCONFIGURED-SLOT DEFAULT is position-aware (2026-07-31). A blanket
		// `{chain: [], read: null}` gave the floor-visible slot 2 an empty chain
		// and a default read — which is precisely the silent-reset shape the
		// renderer now FLAGS as malformed: it displayed "Default (intrinsic
		// analog)" and would have resolved against ambient context rather than
		// inheriting. Slot ≥2 mounts as the same all-inherit state the Add-slot
		// seed writes, so an untouched slot 2 reads (and would serialize)
		// identically whether it arrived by the floor or by the button.
		//
		// Slot 1 keeps the empty default: it has no predecessor, so `current` is
		// genuinely its unset state.
		slot = slot || ( ordinal >= 2 ? seedSlot() : { chain: [], read: null } );

		// REPEATER CARDINALITY (2026-07-30): the intent radio is GONE. It was a
		// per-slot ephemeral signpost that also drove axis VISIBILITY, which
		// meant reveal state and slot existence were tangled. Under the repeater
		// both axes always render and cardinality is explicit (add/remove), so
		// the radio has no remaining job. inferIntent() is retained below only as
		// editor-time ADVISORY text — it describes the slot, it no longer gates.
		var count   = slotCount( state );
		var isLast  = ordinal === count;

		// Slots past the live count do not render at all — GB registers keys up
		// to the ceiling, but only the first `count` are part of this tag.
		if ( ordinal > count ) { return null; }

		// Whole-object setState + delete-omit (param authority idiom): '' deletes
		// the key so the folded reveal predicate (show_if_any not_empty) un-gates.
		// ANY commit also drops this slot's legacy sibling keys — touch-migration.
		function write( next ) {
			var upd = Object.assign( {}, state );
			var v   = serializeSlot( next );
			if ( v ) { upd[ key ] = v; } else { delete upd[ key ]; }
			legacyKeys( ordinal ).forEach( function ( k ) { delete upd[ k ]; } );
			setState( upd );
		}

		// Add the NEXT slot by writing the explicit seed into its key. No arming
		// state is needed anywhere: the slot exists because it HOLDS A VALUE, so
		// cardinality survives remount and no cross-control store is required
		// (a per-key useState could never reveal a sibling — GB mounts one
		// control per option key).
		function addSlot() {
			if ( count >= MAX_SLOTS ) { return; }
			var upd = Object.assign( {}, state );
			upd[ String( count + 1 ) ] = serializeSlot( seedSlot() );
			setState( upd );
		}

		function removeSelf() {
			setState( removeSlotFrom( ordinal, state, count ) );
		}

		// Both axes ALWAYS render now (the radio no longer gates them).
		var showSource = true;
		var showRead   = true;

		var children = [];

		// FW-51 ambiguous legacy shape — surface to the author instead of guessing
		// (the flag the converter-run migrator has no UI for).
		if ( legacyFlag ) {
			children.push( el( 'p', { key: 'flag', style: { color: '#a00', fontSize: '12px' } }, '⚑ ' + legacyFlag ) );
		}
		if ( recovered ) {
			children.push( el( 'p', { key: 'rec', style: { opacity: 0.7, fontSize: '12px', margin: '0 0 4px' } },
				__( 'Recovered from legacy options — saving folds this slot and removes them.', 'generateblocks' ) ) );
		}

		// SLOT HEADER — the repeater chrome. Remove is offered on every slot past
		// the floor, INCLUDING out of order: compaction closes the hole, so there
		// is no "can only remove the last one" restriction (the point of the
		// exercise). Slots 1..MIN_SLOTS have no remove because the tag always has
		// at least two.
		// The advisory rides INSIDE the header container rather than as its own
		// child of the control (trial 2026-07-31): the modal column's 15px
		// row-gap applies between children, so a standalone advisory was pushed a
		// full gap away from the title it describes. Nested here, it sits tight
		// under the title and the gap falls after the whole header block.
		var headerKids = [
			el( 'div', {
				key:   'row',
				style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '8px' },
			}, [
				el( 'strong', { key: 'ttl', style: { fontSize: '12px', textTransform: 'uppercase',
				                                     letterSpacing: '0.4px' } },
					( props.label || ( __( 'Slot', 'generateblocks' ) + ' ' + key ) ) ),
				// Remove is live on EVERY slot whenever 2+ are visible (2026-07-31),
				// matching the step UI: an author may want to drop either one and
				// add anew. Gating it at the floor made slot 1 and slot 2 asymmetric
				// for no reason visible to the author.
				//
				// The floor still holds — removing while exactly two are present
				// compacts the survivor down and leaves two visible (one carrying
				// the value, one empty), so this widens the AFFORDANCE without
				// changing cardinality.
				count > 1
					? el( Button, {
						key:      'rm',
						variant:  'tertiary',
						isSmall:  true,
						isDestructive: true,
						onClick:  removeSelf,
					}, __( 'Remove', 'generateblocks' ) )
					: null,
			] ),
		];

		// ADVISORY (not a gate): describe what this slot varies vs its
		// predecessor. Pure read of the wire — the former radio's only surviving
		// job. Flags the all-inherit seed, which resolves identically to the
		// previous slot and is therefore always a "you have not configured this
		// yet" state rather than a resting one.
		if ( ordinal >= 2 ) {
			var cell = inferIntent( slot );
			var advisory = {
				context: __( 'Varies: source (field inherited)', 'generateblocks' ),
				field:   __( 'Varies: field (source inherited)', 'generateblocks' ),
				both:    __( 'Varies: source and field', 'generateblocks' ),
			}[ cell ];
			if ( ! advisory ) {
				advisory = __( 'Inherits both axes — this slot duplicates the previous one until you change something.', 'generateblocks' );
			}
			headerKids.push( el( 'p', {
				key:   'adv',
				style: { fontSize: '11px', opacity: 0.7, margin: '4px 0 0' },
			}, advisory ) );
		}

		children.push( el( 'div', {
			key:   'hdr',
			// Slot rule is the HEAVIEST (2px/#bbb): outer level. Step rules inside
			// the source group are 1px/#ddd, so nesting reads by weight.
			style: { margin: '14px 0 0', borderTop: '2px solid #bbb', paddingTop: '10px' },
		}, headerKids ) );

		if ( showSource ) {
			// PER-STEP controls. The wire supports an ordered chain
			// (`src(refs,office+refs,region)`), so the control must edit EVERY step
			// — an earlier single-hop version silently TRUNCATED hops 2+ whenever
			// the author touched step 1 (found in editor trial 2026-07-30). Each
			// step edit rebuilds the whole chain positionally; the value is
			// rewritten in full on every commit (DECISION 2).
			var chain = slot.chain.slice();

			// Write a chain mutated at one index (null newStep = delete the step).
			function writeChainAt( idx, newStep ) {
				var next = chain.slice();
				if ( null === newStep ) { next.splice( idx, 1 ); }
				else                    { next[ idx ] = newStep; }
				// Emptying the chain on slot ≥2 must fall back to EXPLICIT inherit,
				// never to absence: a bare empty chain there resolved to ambient
				// context, i.e. a silent reset rather than an inherit. The renderer
				// now flags that shape as malformed, so never emit it.
				//
				// Now reachable via "Remove step" on a 1-step chain (the old enum
				// row was suppressed at that length, so this path could not be hit).
				// Slot 1 has no predecessor, so an empty chain there is legitimately
				// "current" and the empty-state picker takes over on re-render.
				if ( ordinal >= 2 && ! next.length ) {
					next = [ { slug: 'same', arg: null, limit: null } ];
				}
				write( { chain: next, read: slot.read } );
			}

			// The `same` inherit occupies the whole chain (no hops off an inherited
			// source in the spike) — render it as a single step, no add affordance.
			var isSameChain = chain.length === 1 && 'same' === chain[ 0 ].slug;

			// With the radio gone, `same` is no longer expressible anywhere else,
			// so slot ≥2's source select must carry it as a real enum row — it is
			// now the ONLY way to say "inherit the previous slot's source".
			function srcRows() {
				var rows = srcEnum();
				if ( ordinal >= 2 ) {
					rows = [ { value: 'same', label: __( 'Same as previous slot', 'generateblocks' ) } ].concat( rows );
				}
				return rows;
			}

			// ── SOURCE GROUP ────────────────────────────────────────────────
			// ONE box for the whole chain (2026-07-31 trial). Boxing each step
			// individually made a multi-hop chain read as N peer objects rather
			// than one path, and once the field filters landed it stacked three
			// nested borders deep. Steps are now separated INSIDE the group by a
			// lighter rule — the same device the slots use, one level down, so
			// nesting reads by weight rather than by more borders.
			//
			// Hop remove stays inline in each step's header. Its collision with
			// the slot-level Remove is resolved by PLACEMENT (slot remove sits in
			// the slot header, above the box; hop remove sits inside it) — COLOR
			// is reserved for semantics: both removes red, both adds blue.
			var stepNodes = [];

			chain.forEach( function ( step, i ) {
				var stepKids = [];

				// Step header: ordinal + inline remove. Shown only for real
				// multi-step chains — a lone step needs no ordinal (the group
				// caption already names the box), and a `same` chain is the
				// inherit marker rather than a hop.
				if ( ! isSameChain && chain.length > 1 ) {
					stepKids.push( el( 'div', {
						key:   'sh',
						style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between',
						         gap: '8px', marginBottom: '4px' },
					}, [
						el( 'span', { key: 'n', style: { fontSize: '11px', textTransform: 'uppercase',
						                                 letterSpacing: '0.4px', opacity: 0.65 } },
							__( 'Step', 'generateblocks' ) + ' ' + ( i + 1 ) ),
						el( Button, {
							key:           'rm',
							variant:       'tertiary',
							isSmall:       true,
							isDestructive: true,
							onClick:       function () { writeChainAt( i, null ); },
						}, __( 'Remove step', 'generateblocks' ) ),
					] ) );
				}

				stepKids.push( el( SelectControl, {
					key: 'src',
					// Label is ALWAYS present (screen readers need it; omitting the
					// prop leaves the control unlabelled). On a single-step chain
					// the group caption says the same word, so the visible copy is
					// suppressed there instead — see the caption below, which
					// renders only for multi-step chains.
					label:               __( 'Source', 'generateblocks' ),
					hideLabelFromVision: chain.length <= 1,
					value:    wireToEngine( step.slug ),
					options:  srcRows(),
					onChange: function ( v ) {
						if ( ! v ) { writeChainAt( i, null ); return; }
						// Changing the hop kind keeps the arg only if still a ref hop.
						var slug = engineToWire( v );
						writeChainAt( i, { slug: slug, arg: 'refs' === slug ? step.arg : null, limit: step.limit } );
					},
					__nextHasNoMarginBottom: true,
				} ) );

				if ( 'refs' === step.slug ) {
					// The SHIPPED ref option is a bws-field-combo, not a text box —
					// mirror it here (filters visible, unscoped per SPEC V3) so the
					// spike shows the real surface rather than understating it.
					var HopCombo = window.bwsFieldComboControl;
					stepKids.push( el( 'div', { key: 'srcArg', style: STACKED, className: 'bws-proto-fold' },
						HopCombo
							? el( HopCombo, {
								optionKey:   'key',
								label:       __( 'Relationship Field Key', 'generateblocks' ),
								help:        __( 'ACF relationship or post object field key.', 'generateblocks' ),
								placeholder: 'related_posts',
								context:     foldedHopContext( step, function ( v ) {
									writeChainAt( i, { slug: 'refs', arg: v || null, limit: step.limit } );
								} ),
							} )
							: el( TextControl, {
								label:       __( 'Relationship Field Key', 'generateblocks' ),
								value:       step.arg || '',
								placeholder: 'related_posts',
								help:        __( 'Field discovery unavailable — free text only.', 'generateblocks' ),
								onChange:    function ( v ) {
									writeChainAt( i, { slug: 'refs', arg: v || null, limit: step.limit } );
								},
								__nextHasNoMarginBottom: true,
							} )
					) );
					// A `refs` hop with no field has nothing to hop THROUGH — it
					// serializes as a bare `refs` and resolves to nothing. `canAppend`
					// already blocks BUILDING past an incomplete hop, but nothing
					// stopped one from being serialized, so say so where it is
					// fixable (trial 2026-07-31).
					if ( ! step.arg ) {
						stepKids.push( el( 'p', {
							key:   'argwarn',
							style: { fontSize: '11px', color: '#a00', margin: '4px 0 0' },
						}, __( 'Needs a reference field — this step resolves to nothing until set.', 'generateblocks' ) ) );
					}
				}

				// Separator rule between steps (never above the first).
				stepNodes.push( el( 'div', {
					key:   'step-' + i,
					style: i > 0 ? STEP_RULE : null,
				}, stepKids ) );
			} );

			// No chain yet → one empty source picker that seeds step 1. Reachable
			// on slot 1 (legitimately unset = current) and on a slot ≥2 whose wire
			// was hand-edited to drop its src token.
			if ( ! chain.length ) {
				stepNodes.push( el( SelectControl, {
					key:      'src-new',
					label:    __( 'Source', 'generateblocks' ),
					value:    '',
					options:  srcRows(),
					onChange: function ( v ) {
						if ( ! v ) { return; }
						write( { chain: [ { slug: engineToWire( v ), arg: null, limit: null } ], read: slot.read } );
					},
					__nextHasNoMarginBottom: true,
				} ) );
			}

			// Append-a-hop: only off a real (non-inherit) chain, and only once the
			// last step is complete, so we never serialize a half-built step. Lives
			// INSIDE the source group — it appends to this chain, so it belongs to
			// the box, unlike Add slot which sits at the slot's outer edge.
			var last = chain.length ? chain[ chain.length - 1 ] : null;
			var canAppend = ! isSameChain && last && ( 'refs' !== last.slug || last.arg );
			if ( canAppend ) {
				stepNodes.push( el( 'div', {
					key:   'addhop-wrap',
					style: { marginTop: '8px' },
				}, el( Button, {
					variant: 'tertiary',
					isSmall: true,
					onClick: function () {
						write( { chain: chain.concat( [ { slug: 'refs', arg: null, limit: null } ] ), read: slot.read } );
					},
				}, '+ ' + __( 'Add hop', 'generateblocks' ) ) ) );
			}

			children.push( el( 'div', { key: 'srcgroup', style: GROUP_BOX }, [
				el( 'span', { key: 'cap', style: GROUP_CAP },
					chain.length > 1
						? __( 'Source path', 'generateblocks' )
						: __( 'Source', 'generateblocks' ) ),
			].concat( stepNodes ) ) );
		}

		if ( showRead ) {
			var read    = slot.read;
			var readVal = '';
			if ( read && 'key' === read.kind )    { readVal = 'key'; }
			if ( read && 'analog' === read.kind ) { readVal = read.slug; }
			if ( read && 'same' === read.kind )   { readVal = 'same'; }
			// As with source: with the radio gone this select is the only place
			// `same` can be expressed on the read axis.
			var readRows = ( ordinal >= 2 )
				? [ { value: 'same', label: __( 'Same as previous slot', 'generateblocks' ) } ].concat( READ_OPTIONS )
				: READ_OPTIONS;
			// ── FIELD GROUP ─────────────────────────────────────────────────
			// Same box as the source group. The read kind select plus (when the
			// kind is `key`) the whole field-combo — two filters and a combobox —
			// are ONE decision about what to read, so they share one container.
			var readNodes = [];

			readNodes.push( el( SelectControl, {
				key: 'read',
				// Caption carries the group name; the select picks the KIND.
				label:    __( 'Read', 'generateblocks' ),
				value:    readVal,
				options:  readRows,
				onChange: function ( v ) {
					var next = { chain: slot.chain.slice(), read: null };
					if ( 'same' === v )       { next.read = { kind: 'same' }; }
					else if ( 'key' === v )   { next.read = { kind: 'key', field: read && read.field || '' }; }
					else if ( v )             { next.read = { kind: 'analog', slug: v }; }
					write( next );
				},
				__nextHasNoMarginBottom: true,
			} ) );
			if ( read && 'key' === read.kind ) {
				// THE FIELD FILTERS, bridged. Renders the SHIPPED bws-field-combo
				// (Location filter + Type filter + discovery combobox) against a
				// synthetic context, rather than the spike's old bare TextControl —
				// so what is under test is the real surface, including whether the
				// Location filter's kind preset still works when its input is a
				// chain terminal instead of a sibling `src` token.
				//
				// Falls back to free text if the shipped control is absent (its JS
				// is enqueued independently; a spike must not hard-depend on load
				// order it does not control).
				// NO internal rule here: the read kind and the field picker are one
				// decision, so there is no boundary to draw (unlike the source
				// group, where each step is a separate hop). Spacing alone
				// separates them — enough to stop the picker's label reading as
				// part of the select above it.
				var FieldCombo = window.bwsFieldComboControl;
				if ( FieldCombo ) {
					readNodes.push( el( 'div', { key: 'readArg', style: STACKED, className: 'bws-proto-fold' },
						el( FieldCombo, {
							optionKey:    'key',
							label:        __( 'Meta/Option Field', 'generateblocks' ),
							dynamicLabel: true,
							context:      foldedFieldContext( slot, state, function ( field ) {
								write( { chain: slot.chain.slice(), read: { kind: 'key', field: field } } );
							} ),
						} )
					) );
				} else {
					readNodes.push( el( 'div', { key: 'readArg', style: STACKED },
						el( TextControl, {
							label:       __( 'Meta/Option Field Key', 'generateblocks' ),
							value:       read.field || '',
							placeholder: 'field_name',
							help:        __( 'Field discovery unavailable — free text only.', 'generateblocks' ),
							onChange:    function ( v ) {
								write( { chain: slot.chain.slice(), read: { kind: 'key', field: v } } );
							},
							__nextHasNoMarginBottom: true,
						} )
					) );
				}
			}

			children.push( el( 'div', { key: 'readgroup', style: GROUP_BOX }, [
				el( 'span', { key: 'cap', style: GROUP_CAP }, __( 'Field', 'generateblocks' ) ),
			].concat( readNodes ) ) );
		}

		// Wire echo — spike-only debug aid so the value rewrite is visible live.
		children.push( el( 'code', { key: 'echo', style: { display: 'block', opacity: 0.55, fontSize: '11px', margin: '0 0 10px' } },
			key + ':' + ( state[ key ] || '∅' ) + ( recovered ? '  [legacy → ' + serializeSlot( slot ) + ']' : '' ) ) );

		// ADD lives on the LAST slot only — one add affordance per tag, always at
		// the bottom of the stack, which is where a repeater's "add another"
		// belongs. Writing the seed is the whole operation (no arming state).
		if ( isLast && count < MAX_SLOTS ) {
			children.push( el( Button, {
				key:     'addslot',
				variant: 'secondary',
				isSmall: true,
				style:   { marginBottom: '12px' },
				onClick: addSlot,
			}, '+ ' + __( 'Add slot', 'generateblocks' ) ) );
		}

		return el( Fragment, null, children );
	}

	wp.hooks.addFilter(
		'generateblocks.editor.tagSpecificControls',
		'bws/spike-proto-fold-control',
		function ( element, allOptions, context ) {
			if ( ! element || ! allOptions || ! context ) { return element; }
			var cfg = allOptions[ element.key ];
			if ( ! cfg || 'bws-proto-fold' !== cfg.type ) { return element; }
			return el( ProtoFoldControl, {
				key:       element.key,
				optionKey: element.key,
				label:     cfg.label,
				context:   context,
			} );
		}
	);

} )();
