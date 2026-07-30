/**
 * SPIKE B (FW-56/57) — `bws-proto-fold` composite control for {{proto_fold}}.
 *
 * NOT SHIPPED CODE (tools/, .distignore'd; enqueued only by the spike PHP).
 *
 * One control OWNS one folded `{N}:` option key (DECISION 2: control holds
 * parsed step-state, rewrites the WHOLE value on every commit). Renders:
 *   - slot ≥2: a stock RadioControl "intent" signpost (the 2×2 minus the
 *     degenerate cell) — EPHEMERAL (Model 1): inferred from the wire on load,
 *     never serialized.
 *   - the revealed axis sub-controls (source select [+ ref-field arg], read
 *     select [+ key-field arg]).
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
	if ( ! wp.components.SelectControl || ! wp.components.RadioControl ) {
		return;
	}

	var el            = wp.element.createElement;
	var Fragment      = wp.element.Fragment;
	var useState      = wp.element.useState;
	var SelectControl = wp.components.SelectControl;
	var TextControl   = wp.components.TextControl;
	var RadioControl  = wp.components.RadioControl;
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
		slot = slot || { chain: [], read: null };

		// B2/B4 — the EPHEMERAL intent radio: seeded from the wire (or the
		// recovered struct), never serialized. Remount re-infers (Model 1).
		var intentState = useState( inferIntent( ( raw || recovered ) ? slot : null ) );
		var intent      = intentState[ 0 ];
		var setIntent   = intentState[ 1 ];

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

		function onIntentChange( cell ) {
			setIntent( cell );
			// Rewrite the value SKELETON for the chosen cell (S1: both axes always
			// explicit on slot ≥2): keep whatever the kept axis already holds.
			var next = { chain: slot.chain.slice(), read: slot.read };
			if ( 'context' === cell ) {
				// new source, SAME read — read pins to inherit; stale 'same' chain clears.
				if ( next.chain.length === 1 && 'same' === next.chain[ 0 ].slug ) { next.chain = []; }
				next.read = { kind: 'same' };
			} else if ( 'field' === cell ) {
				// SAME source, new read — chain pins to inherit; stale 'same' read clears.
				next.chain = [ { slug: 'same', arg: null, limit: null } ];
				if ( next.read && 'same' === next.read.kind ) { next.read = null; }
			} else if ( 'both' === cell ) {
				if ( next.chain.length === 1 && 'same' === next.chain[ 0 ].slug ) { next.chain = []; }
				if ( next.read && 'same' === next.read.kind ) { next.read = null; }
			}
			write( next );
		}

		// Axis visibility per cell. Slot 1 has no radio and no 'same' — both axes shown.
		var showSource = ordinal === 1 || 'context' === intent || 'both' === intent;
		var showRead   = ordinal === 1 || 'field' === intent || 'both' === intent;

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

		if ( ordinal >= 2 ) {
			children.push( el( RadioControl, {
				key:      'intent',
				label:    props.label || ( key + ':' ),
				help:     __( 'What changes in this slot vs the previous one?', 'generateblocks' ),
				selected: intent,
				options: [
					{ value: 'context', label: __( 'New source, same field', 'generateblocks' ) },
					{ value: 'field',   label: __( 'Same source, new field', 'generateblocks' ) },
					{ value: 'both',    label: __( 'New source and field', 'generateblocks' ) },
				],
				onChange: onIntentChange,
			} ) );
		}

		if ( showSource ) {
			// First-hop slug (spike: single hop; multi-hop UX is the deferred
			// append-a-step control). Empty chain = current (implicit — no src token).
			var hop     = slot.chain.length ? slot.chain[ 0 ] : null;
			var hopSame = hop && 'same' === hop.slug;
			var srcVal  = ( hop && ! hopSame ) ? wireToEngine( hop.slug ) : '';
			children.push( el( SelectControl, {
				key:      'src',
				label:    key + ': ' + __( 'Source', 'generateblocks' ),
				value:    srcVal,
				options:  srcEnum(),
				onChange: function ( v ) {
					var next = { chain: [], read: slot.read };
					if ( v ) { next.chain = [ { slug: engineToWire( v ), arg: hop && ! hopSame ? hop.arg : null, limit: null } ]; }
					if ( ordinal >= 2 && 'context' === intent ) { next.read = { kind: 'same' }; }
					write( next );
				},
				__nextHasNoMarginBottom: true,
			} ) );
			if ( hop && 'refs' === hop.slug ) {
				children.push( el( TextControl, {
					key:         'srcArg',
					label:       key + ': ' + __( 'Reference Field', 'generateblocks' ),
					value:       hop.arg || '',
					placeholder: 'field_name',
					onChange:    function ( v ) {
						var next = { chain: [ { slug: 'refs', arg: v || null, limit: hop.limit } ], read: slot.read };
						write( next );
					},
					__nextHasNoMarginBottom: true,
				} ) );
			}
		}

		if ( showRead ) {
			var read    = slot.read;
			var readVal = '';
			if ( read && 'key' === read.kind )    { readVal = 'key'; }
			if ( read && 'analog' === read.kind ) { readVal = read.slug; }
			children.push( el( SelectControl, {
				key:      'read',
				label:    key + ': ' + __( 'Field', 'generateblocks' ),
				value:    readVal,
				options:  READ_OPTIONS,
				onChange: function ( v ) {
					var next = { chain: slot.chain.slice(), read: null };
					if ( 'key' === v )        { next.read = { kind: 'key', field: read && read.field || '' }; }
					else if ( v )             { next.read = { kind: 'analog', slug: v }; }
					if ( ordinal >= 2 && 'field' === intent && ! next.chain.length ) {
						next.chain = [ { slug: 'same', arg: null, limit: null } ];
					}
					write( next );
				},
				__nextHasNoMarginBottom: true,
			} ) );
			if ( read && 'key' === read.kind ) {
				children.push( el( TextControl, {
					key:         'readArg',
					label:       key + ': ' + __( 'Meta/Option Field Key', 'generateblocks' ),
					value:       read.field || '',
					placeholder: 'field_name',
					onChange:    function ( v ) {
						write( { chain: slot.chain.slice(), read: { kind: 'key', field: v } } );
					},
					__nextHasNoMarginBottom: true,
				} ) );
			}
		}

		// Wire echo — spike-only debug aid so the value rewrite is visible live.
		children.push( el( 'code', { key: 'echo', style: { display: 'block', opacity: 0.6, fontSize: '11px', marginBottom: '12px' } },
			key + ':' + ( state[ key ] || '∅' ) + ( recovered ? '  [legacy → ' + serializeSlot( slot ) + ']' : '' ) ) );

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
