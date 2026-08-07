/**
 * Repeater harness for the folded-slot control — cardinality, seeding, and slot
 * REMOVAL WITH COMPACTION. Run: `node tools/test/slot-fold-repeater-test.js`
 * (exits non-zero on failure, house convention).
 *
 * A JS harness in a directory that was 100% PHP: deliberate, because the property
 * under test only exists in JS. Out-of-order slot removal is the one repeater
 * operation the PHP side can NEVER exercise — compaction lives entirely in the
 * control, and the renderer only ever sees wire that has ALREADY been compacted.
 * Porting the cases to PHP would test a reimplementation instead of the shipping
 * logic, which is the opposite of the point.
 *
 * THE PROPERTY: `same` is a POSITIONAL backreference ("inherit from the previous
 * slot"). Sliding a slot down re-points it at a DIFFERENT neighbour, silently
 * changing what the tag resolves to. So compaction must MATERIALIZE an inherited
 * axis against the slot being removed BEFORE renumbering. Hop removal carries no
 * such hazard (a hop holds no backreference), which is why the hop pattern could
 * not simply be copied across.
 *
 * CONTAINER-PARAMETERIZED, and that is a correction rather than a nicety: the spike
 * probe asserted `src(same);use(same)` as THE seed shape, which is the SELECTING
 * seed. A combining container seeds `src(same)` with the read UNSET, because
 * choosing a field is the configuration act there. Graduating the single-container
 * assertion would have encoded a try_-only rule as a general invariant.
 *
 * Loads the SHIPPED files (grammar twin, order normalizer, control) against stubbed
 * wp globals and uses the control's own `window.bwsSlotFoldRepeater` export.
 *
 * @package BWS_Dynamic_Tags
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const root = path.resolve( __dirname, '../..' );

global.window = {};
global.wp = {
	hooks: { addFilter: function () {} },
	// Recording createElement — chainSteps is the one export whose subject is the
	// RENDERED tree (which picker exists, what it is showing, whether Add step is
	// reachable), so the stub has to keep what it was handed. Everything else in this
	// harness is pure and never calls it.
	element: {
		createElement: function ( type, props ) {
			return {
				type: type,
				props: props || {},
				children: Array.prototype.slice.call( arguments, 2 )
			};
		},
		Fragment: 'F',
		useEffect: function () {}
	},
	components: { SelectControl: {}, TextControl: {}, Button: {}, ComboboxControl: {}, Flex: {}, FlexItem: {} },
	i18n: { __: function ( s ) { return s; }, sprintf: function ( f, v ) { return f.replace( '%s', v ); } }
};
global.window.wp = global.wp;

function load( relative ) {
	const file = path.join( root, relative );
	vm.runInThisContext( fs.readFileSync( file, 'utf8' ), { filename: file } );
}

// ENQUEUE ORDER, matching the plugin's own registration — the control declares all three
// of these as script dependencies and will not mount without them, so loading them here
// is modelling the real load rather than propping the harness up. The wrapper is what
// owns the box class names; a fallback copy of them in the control is what this ordering
// replaced, and a harness that skipped the file would have kept that copy alive.
load( 'assets/js/option-group.js' );
load( 'assets/js/serialization-order-normalizer.js' );
load( 'assets/js/slot-fold-grammar.js' );
// The migrate twin owns which legacy sibling keys a container has; the control is a hard
// dependency on it (it will not mount without it), so it loads here too.
load( 'assets/js/slot-fold-migrate.js' );
load( 'assets/js/slot-fold-control.js' );

const fold = global.window.bwsSlotFold;
const rep = global.window.bwsSlotFoldRepeater;
if ( ! fold || ! rep ) {
	console.error( 'grammar or repeater export missing — control did not load' );
	process.exit( 2 );
}

// Container configs, built through the control's own reader so a config-shape change
// cannot silently bypass the harness. Only the fields the pure repeater logic touches
// are supplied; enums/labels belong to the rendered control, not to these rules.
const SELECTING = rep.foldConfig( { fold: { container: 'try', combining: false, perSlotUse: true, min: 2, max: 5 } } );
const COMBINING = rep.foldConfig( { fold: { container: 'join', combining: true, min: 2, max: 10 } } );
// The other two SELECTING read shapes (a selecting container is not one thing):
//   KEY_ONLY  — per-slot key, no `use` enum          (try_email, try_phone)
//   CHAIN_ONLY— no per-slot read at all              (try_title, try_permalink, try_datetime_*)
// Both carry perSlotUse false, and the seed must follow: `use(same)` names an axis
// neither container has a control for.
const KEY_ONLY = rep.foldConfig( {
	fold: { container: 'try', combining: false, perSlotUse: false, min: 2, max: 5, keyOption: { label: 'Meta/Option Field' } }
} );
const CHAIN_ONLY = rep.foldConfig( { fold: { container: 'try', combining: false, perSlotUse: false, min: 2, max: 5 } } );

let fail = 0;
let total = 0;

function check( label, got, want, extra ) {
	total++;
	const ok = got === want;
	if ( ! ok ) {
		fail++;
	}
	console.log( ( ok ? 'PASS  ' : 'FAIL  ' ) + label );
	if ( extra ) {
		console.log( '        ' + extra );
	}
	if ( ! ok ) {
		console.log( '        want  : ' + want );
		console.log( '        got   : ' + got );
	}
}

/** Render a state map as `1:value | B:value` for readable failures. */
function show( state, conf ) {
	const rows = [];
	for ( let i = 1; i <= conf.max; i++ ) {
		const k = fold.slotKey( i );
		if ( state[ k ] ) {
			rows.push( k + ':' + state[ k ] );
		}
	}
	return rows.join( ' | ' ) || '(none)';
}

// ── Removal + compaction ───────────────────────────────────────────────────
// [ name, conf, beforeState, removeOrdinal, expectedAfter ]
const CASES = [
	[ 'plain compaction — remove middle of 4, no inheritance anywhere', SELECTING,
		{ 'A': 'key(staff_name)', 'B': 'src(refs,office);key(city)', 'C': 'src(refs,region);key(name)', 'D': 'src(post,9999);use(title)' }, 2,
		'A:key(staff_name) | B:src(refs,region);key(name) | C:src(post,9999);use(title)' ],

	[ 'MATERIALIZE src — successor inherited the removed slot\'s source', SELECTING,
		{ 'A': 'key(staff_name)', 'B': 'src(refs,office);key(city)', 'C': 'src(same);key(region_name)' }, 2,
		'A:key(staff_name) | B:src(refs,office);key(region_name)' ],

	[ 'MATERIALIZE read — successor inherited the removed slot\'s read', SELECTING,
		{ 'A': 'key(staff_name)', 'B': 'src(refs,office);key(city)', 'C': 'src(refs,region);use(same)' }, 2,
		'A:key(staff_name) | B:src(refs,region);key(city)' ],

	[ 'PROMOTION — an all-inherit slot 2 promoted to position 1 drops its illegal `same`', SELECTING,
		{ 'A': 'src(refs,office);key(city)', 'B': 'src(same);use(same)' }, 1,
		'A:src(refs,office);key(city)' ],

	[ 'no successor — removing the last slot needs no materialization', SELECTING,
		{ 'A': 'key(staff_name)', 'B': 'src(refs,office);key(city)', 'C': 'src(refs,region);key(name)' }, 3,
		'A:key(staff_name) | B:src(refs,office);key(city)' ],

	[ 'multi-hop materialization — the WHOLE chain carries, not just hop 1', SELECTING,
		{ 'A': 'key(a)', 'B': 'src(refs,office;refs,region);key(city)', 'C': 'src(same);key(zip)' }, 2,
		'A:key(a) | B:src(refs,office;refs,region);key(zip)' ],

	[ 'chained inherit — only the IMMEDIATE successor referenced the removed slot', SELECTING,
		{ 'A': 'key(a)', 'B': 'src(refs,office);key(b)', 'C': 'src(same);key(c)', 'D': 'src(same);key(d)' }, 2,
		'A:key(a) | B:src(refs,office);key(c) | C:src(same);key(d)' ],

	// Remove is live on every slot once 2+ are visible. The floor still holds: one
	// value survives and the second slot renders empty.
	[ 'AT THE FLOOR — remove slot 1 of 2; survivor compacts into position 1', SELECTING,
		{ 'A': 'key(a)', 'B': 'src(refs,office);key(b)' }, 1,
		'A:src(refs,office);key(b)' ],

	[ 'AT THE FLOOR — remove slot 2 of 2; slot 1 untouched', SELECTING,
		{ 'A': 'key(a)', 'B': 'src(refs,office);key(b)' }, 2,
		'A:key(a)' ],

	// A per-slot option that is NOT an axis must survive compaction untouched. The
	// spike's struct had only chain+read, so a table column's `label` had nothing to
	// be dropped from; under the shipped grammar it does.
	[ 'compaction preserves non-axis slot options (label, type, link)', COMBINING,
		{ 'A': 'label(Name);title', 'B': 'label(City);src(refs,office);key(city)', 'C': 'label(Zip);src(same);key(zip);linkTo(permalink)' }, 2,
		'A:label(Name);title | B:label(Zip);src(refs,office);key(zip);linkTo(permalink)' ],

	// The position-1 strip is a SEPARATE guard from materialization, and only these
	// two cases isolate it: materialization normally REPLACES the successor's `same`
	// with a real value, so a residual inherit reaches position 1 only when the
	// REMOVED slot was itself inheriting (a hand-edited slot 1). Position 1 has no
	// predecessor, so `same` there is illegal and must drop to a plain unset axis.
	[ 'POSITION-1 STRIP (src) — removed slot was itself inheriting', SELECTING,
		{ 'A': 'src(same);key(a)', 'B': 'src(same);key(b)' }, 1,
		'A:key(b)' ],

	[ 'POSITION-1 STRIP (read) — removed slot inherited its read', SELECTING,
		{ 'A': 'src(refs,x);use(same)', 'B': 'src(refs,y);use(same)' }, 1,
		'A:src(refs,y)' ],

	// Combining materialization: the successor's inherited SOURCE still carries, and
	// its unset read stays unset (there is no read to inherit in a combining slot).
	[ 'COMBINING — materialize src; an unset read stays unset', COMBINING,
		{ 'A': 'key(a)', 'B': 'src(refs,office);key(b)', 'C': 'src(same)' }, 2,
		'A:key(a) | B:src(refs,office)' ],
];

CASES.forEach( function ( c ) {
	const [ name, conf, before, n, want ] = c;
	const count = rep.slotCount( before, conf );
	const got = show( rep.removeSlotFrom( n, before, count, conf ), conf );
	check( name, got, want, 'before: ' + show( before, conf ) + '   (remove slot ' + n + ')' );
} );

// ── Seed shape — CONTAINER-DEPENDENT ───────────────────────────────────────
check( 'SELECTING seed = src(same);use(same)', fold.emitSlot( rep.seedSlot( SELECTING ) ), 'src(same);use(same)' );
check( 'COMBINING seed = src(same) with the read UNSET', fold.emitSlot( rep.seedSlot( COMBINING ) ), 'src(same)' );
// Not a third container, a third READ SHAPE: selecting with no `use` axis. An absent
// read already inherits in a selecting container, so the sentinel would only add a
// token naming an axis with no control — and the renderer resolves both the same way.
check( 'KEY-ONLY seed omits the read sentinel', fold.emitSlot( rep.seedSlot( KEY_ONLY ) ), 'src(same)' );
check( 'CHAIN-ONLY seed omits the read sentinel', fold.emitSlot( rep.seedSlot( CHAIN_ONLY ) ), 'src(same)' );

// Compaction is read-shape blind — it materializes whatever axes the wire holds. A
// key-only successor inheriting its key must still carry it across a removal.
check(
	'KEY-ONLY compaction materializes an inherited key',
	show( rep.removeSlotFrom( 2, { 'A': 'key(a)', 'B': 'src(refs,office);key(b)', 'C': 'src(same)' }, 3, KEY_ONLY ), KEY_ONLY ),
	'A:key(a) | B:src(refs,office)'
);
check(
	'CHAIN-ONLY compaction materializes the chain with no read to carry',
	show( rep.removeSlotFrom( 2, { 'A': 'src(post,9999)', 'B': 'src(refs,office)', 'C': 'src(same)' }, 3, CHAIN_ONLY ), CHAIN_ONLY ),
	'A:src(post,9999) | B:src(refs,office)'
);

// ── Cardinality — content-derived, in EITHER wire era ──────────────────────
check( 'cardinality floors at the container minimum', rep.slotCount( {}, SELECTING ), 2 );
check( 'cardinality counts the highest folded value', rep.slotCount( { 'A': 'key(a)', 'D': 'src(same);use(same)' }, SELECTING ), 4 );
check( 'cardinality counts an UNMIGRATED legacy slot', rep.slotCount( { 'key': 'a', '3-src': 'ref', '3-ref': 'office' }, SELECTING ), 3 );
check( 'cardinality never exceeds the ceiling scan', rep.slotCount( { 'E': 'key(a)' }, SELECTING ), 5 );

// ── Mount default for an UNCONFIGURED slot — position-aware ────────────────
// Reimplements the control's fallback rule (it lives inside the mounted component
// and cannot be called headlessly), so this pins the RULE: slot 1 unconfigured is
// empty/current; slot ≥2 unconfigured is the container's seed. A blanket empty
// struct on slot 2 is the silent-RESET shape (resolves against ambient context
// instead of inheriting), which the renderer treats as malformed.
function mountDefault( ordinal, conf ) {
	const slot = ordinal >= 2
		? rep.seedSlot( conf )
		: { label: null, type: null, chain: [], read: null, opts: {}, extra: [] };
	return fold.emitSlot( slot ) || '(empty — current, no read)';
}
check( 'unconfigured slot 1 mounts empty (current)', mountDefault( 1, SELECTING ), '(empty — current, no read)' );
check( 'unconfigured slot 2 mounts as the selecting seed', mountDefault( 2, SELECTING ), 'src(same);use(same)' );
check( 'unconfigured slot 2 mounts as the combining seed', mountDefault( 2, COMBINING ), 'src(same)' );

// ── Hop removal (the control's writeChainAt delete branch) ─────────────────
// Also a rule-mirror rather than an import, for the same reason. Worth pinning
// because the inline "Remove step" button made the 1-step case reachable at all:
// the old enum row was suppressed at that length, so emptying a chain by hop
// removal could not previously happen.
function removeHop( chainStr, idx, ordinal ) {
	const parsed = fold.parseSlot( 'src(' + chainStr + ')', 'try' );
	const next = parsed.chain.slice();
	next.splice( idx, 1 );
	if ( ordinal >= 2 && ! next.length ) {
		next.push( { slug: 'same', arg: null, limit: null, extra: [] } );
	}
	parsed.chain = next;
	return fold.emitSlot( parsed ) || '(empty chain — slot 1 falls to current)';
}
const HOP_CASES = [
	[ 'remove hop 1 of 2 keeps hop 2', 'refs,office;refs,region', 0, 1, 'src(refs,region)' ],
	[ 'remove hop 2 of 2 keeps hop 1', 'refs,office;refs,region', 1, 1, 'src(refs,office)' ],
	[ 'remove middle hop of 3', 'post,9999;refs,a;refs,b', 1, 1, 'src(post,9999;refs,b)' ],
	[ 'empty a 1-step chain on slot 1 — legitimately unset (current)', 'refs,office', 0, 1, '(empty chain — slot 1 falls to current)' ],
	[ 'empty a 1-step chain on slot ≥2 — MUST fall back to explicit inherit', 'refs,office', 0, 2, 'src(same)' ],
	[ 'hop removal preserves a per-step limit on the survivor', 'refs,a;terms,category,limit[3]', 0, 1, 'src(terms,category,limit[3])' ],
];
HOP_CASES.forEach( function ( c ) {
	check( 'hop: ' + c[ 0 ], removeHop( c[ 1 ], c[ 2 ], c[ 3 ] ), c[ 4 ],
		c[ 1 ] + '  remove idx ' + c[ 2 ] + ' (slot ' + c[ 3 ] + ')' );
} );

// ── Advisory is DESCRIPTIVE — it must never gate ───────────────────────────
// inferIntent is the residue of a cut intent radio that also drove axis visibility.
// These rows pin that it is a pure read of the wire: every cell, plus the
// all-inherit case that must report "unset" rather than a variation.
const INTENT = [
	[ 'src(refs,office);use(same)', 'context' ],
	[ 'src(same);key(x)', 'field' ],
	[ 'src(refs,office);key(x)', 'both' ],
	[ 'src(same);use(same)', '' ],
	[ '', '' ],
];
INTENT.forEach( function ( c ) {
	const parsed = fold.parseSlot( c[ 0 ], 'try' );
	check( 'advisory cell for "' + ( c[ 0 ] || '(empty)' ) + '"', rep.inferIntent( parsed ), c[ 1 ] );
} );

// ── Touch-migration surface: the legacy sibling keys a commit clears ───────
// `srcTermIn` and `limit` were absent from the spike's list, and leaving either
// behind produces a MIXED wire — the shape a half-applied migration makes, which
// the renderer must flag rather than merge.
//
// The surface is CONTAINER-DERIVED (`flatAxes`, from PHP), and the reason is a bug
// this harness now pins: on a try_ template a bare `limit` is the TAG-level limit for
// every slot, and on the read-less shapes a bare `use`/`key` is a TAG-level option
// too. A control that listed the six axes itself deleted them on first touch, and
// the mapper folded them into slot 1 as that slot's own read.
const cleared = rep.legacyKeys( 2, COMBINING ).sort().join( ',' );
check( 'legacy sibling keys cover all six when nothing is tag-level', cleared, '2-key,2-limit,2-ref,2-src,2-srcTermIn,2-use' );
const slot1Cleared = rep.legacyKeys( 1, COMBINING ).sort().join( ',' );
check( 'slot 1 legacy keys are UNPREFIXED', slot1Cleared, 'key,limit,ref,src,srcTermIn,use' );

const TRY_TEXT = rep.foldConfig( {
	fold: {
		container: 'try', combining: false, perSlotUse: true, min: 2, max: 5,
		flatAxes: [ 'src', 'ref', 'srcTermIn', 'use', 'key' ]
	}
} );
check(
	'a try_ template\'s TAG-level limit is not a slot key',
	rep.legacyKeys( 1, TRY_TEXT ).join( ',' ),
	'src,ref,srcTermIn,use,key'
);

const TRY_DATETIME = rep.foldConfig( {
	fold: {
		container: 'try', combining: false, perSlotUse: false, min: 2, max: 5,
		flatAxes: [ 'src', 'ref', 'srcTermIn' ]
	}
} );
check(
	'a read-less template\'s TAG-level key/use/limit are not slot keys',
	rep.legacyKeys( 1, TRY_DATETIME ).join( ',' ),
	'src,ref,srcTermIn'
);
// The read consequence, which is the half a delete-list alone would not fix: slot 1 of
// a read-less try_ tag carrying a TAG-level `key` must mount as a chain-only slot, NOT
// with that key folded in as its read.
check(
	'a tag-level key does not become slot 1\'s read',
	fold.emitSlot( rep.readSlot( 1, { key: 'event_date', src: 'site' }, TRY_DATETIME ) ),
	'src(site)'
);
check(
	'the same wire on a per-slot-read container DOES fold the key',
	fold.emitSlot( rep.readSlot( 1, { key: 'event_date', src: 'site' }, TRY_TEXT ) ),
	'src(site);key(event_date)'
);

// ── argKind — which ARG a hop slug takes, in the config's own vocabulary ─────
//
// Covered because it was NOT: `argKind` returned a hand-typed `'taxonomy'` (a third
// spelling of a slug the config already names twice) until 1.17.0, and stubbing it to
// return '' left the whole suite green — the rendered step controls it drives are React
// code no harness reaches. These cases are the mutation guard: every answer must come
// back through `slugMap`, so a hop added on the PHP side needs no edit here, and a
// re-typed local string fails.
// `slugMap` mirrors the one bws_build_fold_slot_options() emits — engine option value →
// wire step slug (DECISION 3). It is INPUT here, not an expectation: the cases assert that
// every answer is derived from whatever map arrives, never that the map holds these pairs.
const HOPS = rep.foldConfig( {
	fold: {
		container: 'join', combining: true, min: 2, max: 10,
		slugMap: { ref: 'refs', srcTermIn: 'terms', rows: 'entries' }
	}
} );
check( 'argKind: refs takes the ref field arg', rep.argKind( HOPS, 'refs' ), 'ref' );
check( 'argKind: terms takes the taxonomy arg, named as the engine names it', rep.argKind( HOPS, 'terms' ), 'srcTermIn' );
check( 'argKind: entries takes the repeater field arg', rep.argKind( HOPS, 'entries' ), 'rows' );
// A ROOT slug and an unknown one both take no arg, and the '' is load-bearing twice: it
// gates the Add-hop row, and it is what stops toEngine()'s pass-through from reporting a
// non-hop as its own kind.
check( 'argKind: an entity root takes no arg', rep.argKind( HOPS, 'current' ), '' );
check( 'argKind: an unknown slug takes no arg', rep.argKind( HOPS, 'sideways' ), '' );
// The engine spellings themselves are NOT wire slugs — passing one in means a caller
// mixed the vocabularies, and it must not round-trip into a kind.
check( 'argKind: an engine option value is not a wire slug', rep.argKind( HOPS, 'srcTermIn' ), '' );
// Derived means DEPENDENT: with no map on the option definition, no hop takes an arg and
// every step renders bare. Same posture as `flatAxes` in the migrate twin — an absent
// list is a REGISTRATION bug, not a shape to tolerate — and asserted so the dependency is
// visible rather than discovered in the editor.
const NO_MAP = rep.foldConfig( { fold: { container: 'join', combining: true, min: 2, max: 10 } } );
check( 'argKind: an absent slugMap yields no arg at all (registration bug)', rep.argKind( NO_MAP, 'refs' ), '' );

// ── An absent chain DISPLAYS the root it spells ─────────────────────────────
// A SelectControl whose value matches no row paints its FIRST row while believing
// nothing is selected, so the row on screen cannot be picked (selecting the displayed
// value fires no change) and, with no step in hand, `+ Add step` never appears. That
// shipped twice — once on the base-tag control, once here — which is why the display
// rule now lives in chainSteps, where both callers reach it.
//
// The assertions are on the RENDERED tree because that is where the bug was: the value
// and the enum were each individually correct.

const CHAIN_CONF = rep.foldConfig( { fold: {
	container: 'try',
	combining: false,
	perSlotUse: true,
	min: 2,
	max: 5,
	srcRows: [
		{ value: 'current', label: 'Current' },
		{ value: 'ref', label: 'In Reference/Relational Field' }
	],
	srcRowsInherit: [
		{ value: 'same', label: 'Same as Previous Source' },
		{ value: 'current', label: 'Current' }
	],
	stepRows: [
		{ value: 'srcTermIn', label: 'In Taxonomy Term' },
		{ value: 'ref', label: 'In Reference/Relational Field' }
	],
	slugMap: { ref: 'refs', srcTermIn: 'terms', rows: 'entries' },
	defaultRoot: 'current',
	// Shaped exactly as bws_fold_step_applicability() ships it: the engine's own
	// refusal list translated to wire slugs, the produced-kind map, and the roots
	// whose kind is STATIC (only `site` — every other root resolves at render).
	stepApplies: {
		inputs: {
			refs: [ 'post', 'term', 'user', 'meta_row', 'site' ],
			terms: [ 'post' ],
			entries: [ 'post', 'term', 'user', 'meta_row', 'site' ]
		},
		kinds: { refs: 'post', terms: 'term', entries: 'meta_row' },
		roots: { site: 'site' }
	}
} } );

/**
 * The STEP pickers in a rendered tree, in order.
 *
 * Keyed `src`, which is what distinguishes them from a step's ARG picker — a `terms`
 * step renders a taxonomy SelectControl right beside its own, so a type-only walk
 * returns the arg picker as the last select and quietly answers questions about the
 * wrong control.
 */
function selectsIn( nodes ) {
	const out = [];
	( function walk( n ) {
		if ( ! n ) { return; }
		if ( Array.isArray( n ) ) { n.forEach( walk ); return; }
		if ( n.type === global.wp.components.SelectControl && n.props && 'src' === n.props.key ) {
			out.push( n.props );
		}
		( n.children || [] ).forEach( walk );
	}( nodes ) );
	return out;
}

/** Whether the tree holds the append-a-step button. */
function hasAddStep( nodes ) {
	let found = false;
	( function walk( n ) {
		if ( ! n || found ) { return; }
		if ( Array.isArray( n ) ) { n.forEach( walk ); return; }
		if ( n.props && 'addstep' === n.props.key ) { found = true; return; }
		( n.children || [] ).forEach( walk );
	}( nodes ) );
	return found;
}

function renderChain( chain, inheritOnEmpty ) {
	return rep.chainSteps( {
		conf: CHAIN_CONF,
		chain: chain,
		onChange: function () {},
		inheritOnEmpty: !! inheritOnEmpty,
		slotNoun: 'attempt',
		stepContext: function () { return { state: {}, setState: function () {} }; }
	} );
}

const empty1 = renderChain( [], false );
const sel1   = selectsIn( empty1 );
check( 'slot 1, no chain: exactly one source picker', sel1.length, 1 );
check( 'slot 1, no chain: it SHOWS the root absence spells', sel1[ 0 ] && sel1[ 0 ].value, 'current' );
// The doubled caption the user reported: the box is already captioned "Source", so the
// lone picker inside it must not print the word again. Only the step picker suppresses
// it; the seed picker this replaced did not, which is why the pair appeared together.
check( 'slot 1, no chain: the visible label is suppressed', sel1[ 0 ] && sel1[ 0 ].hideLabelFromVision, true );
check( 'slot 1, no chain: the label still EXISTS for screen readers', sel1[ 0 ] && sel1[ 0 ].label, 'Source' );
check( 'slot 1, no chain: Add step is reachable', hasAddStep( empty1 ), true );

const empty2 = renderChain( [], true );
const sel2   = selectsIn( empty2 );
check( 'slot ≥2, no chain: shows the INHERIT row, not `current`', sel2[ 0 ] && sel2[ 0 ].value, 'same' );
// An inherit is not a chain to continue — appending to it would state a step off a
// source this slot has not chosen.
check( 'slot ≥2, no chain: Add step is NOT offered off an inherit', hasAddStep( empty2 ), false );

// A real chain is untouched by the display rule.
const real = renderChain( [ { slug: 'refs', arg: 'office', limit: null } ], false );
check( 'a configured chain still shows its own root', selectsIn( real )[ 0 ].value, 'ref' );

// ── A step is offered only where the ENGINE would accept it ─────────────────
// Offering a step off a source it refuses authors wire that renders nothing and says
// so nowhere. The allowlist is the engine's own, reaching the control through the
// option definition — never a second list here.

/** Option values of the LAST picker in a rendered chain (the one a step would follow). */
function lastPickerValues( nodes ) {
	const sels = selectsIn( nodes );
	return ( sels[ sels.length - 1 ].options || [] ).map( function ( r ) { return r.value; } );
}

const afterSite = renderChain( [ { slug: 'site', arg: null, limit: null }, { slug: 'refs', arg: 'partner', limit: null } ], false );
check(
	'a `terms` step after a SITE root is not offered (the engine takes srcTermIn off a post only)',
	lastPickerValues( afterSite ).indexOf( 'srcTermIn' ),
	-1
);
check(
	'a `refs` step after a SITE root IS offered (an options page holds relationship fields)',
	lastPickerValues( afterSite ).indexOf( 'ref' ) !== -1,
	true
);

// A step already STORED at a refused position stays in its OWN list, filter or not: a
// value missing from its own options paints a different row while believing nothing is
// selected — the seed-picker defect, arrived at from the other direction. Only what the
// author may ADD is filtered.
const storedDead = renderChain( [ { slug: 'site', arg: null, limit: null }, { slug: 'terms', arg: 'department', limit: null } ], false );
check( 'a stored-but-refused step still shows its own value', selectsIn( storedDead )[ 1 ].value, 'srcTermIn' );
check(
	'...and its own row is in its own list',
	lastPickerValues( storedDead ).indexOf( 'srcTermIn' ) !== -1,
	true
);

// Add step is gated on the OFFER, not on the registered list: with every registered
// step refused, an Add could only produce a dead step.
const siteOnly = renderChain( [ { slug: 'site', arg: null, limit: null } ], false );
check( 'Add step is still offered off site (refs applies)', hasAddStep( siteOnly ), true );

const TERMS_ONLY = rep.foldConfig( { fold: Object.assign( {}, {
	container: 'try',
	combining: false,
	perSlotUse: true,
	srcRows: [ { value: 'current', label: 'Current' }, { value: 'site', label: 'Site' } ],
	srcRowsInherit: [],
	stepRows: [ { value: 'srcTermIn', label: 'In Taxonomy Term' } ],
	slugMap: { ref: 'refs', srcTermIn: 'terms', rows: 'entries' },
	defaultRoot: 'current',
	stepApplies: {
		inputs: { terms: [ 'post' ] },
		kinds: { refs: 'post', terms: 'term', entries: 'meta_row' },
		roots: { site: 'site' }
	}
} ) } );
const siteTermsOnly = rep.chainSteps( {
	conf: TERMS_ONLY,
	chain: [ { slug: 'site', arg: null, limit: null } ],
	onChange: function () {},
	inheritOnEmpty: false,
	slotNoun: 'attempt',
	stepContext: function () { return { state: {}, setState: function () {} }; }
} );
check( 'Add step is withheld when every registered step is refused', hasAddStep( siteTermsOnly ), false );

// An ambient root has no static kind, so nothing is filtered — the editor must not
// guess whether `current` is a post or a term.
const fromCurrent = renderChain( [ { slug: 'current', arg: null, limit: null } ], false );
check( 'an ambient root filters nothing', lastPickerValues( fromCurrent ).indexOf( 'srcTermIn' ) !== -1, true );

// A term cannot step to terms again; it can step to a relationship.
const afterTerms = renderChain( [ { slug: 'terms', arg: 'department', limit: null }, { slug: 'refs', arg: 'lead', limit: null } ], false );
check( 'a second `terms` step off a term is not offered', lastPickerValues( afterTerms ).indexOf( 'srcTermIn' ), -1 );

console.log( '\n' + ( total - fail ) + '/' + total + ' passed' );
process.exit( fail ? 1 : 0 );
