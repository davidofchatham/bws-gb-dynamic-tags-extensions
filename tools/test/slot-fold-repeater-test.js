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
	element: { createElement: function () {}, Fragment: 'F', useEffect: function () {} },
	components: { SelectControl: {}, TextControl: {}, Button: {}, ComboboxControl: {}, Flex: {}, FlexItem: {} },
	i18n: { __: function ( s ) { return s; }, sprintf: function ( f, v ) { return f.replace( '%s', v ); } }
};
global.window.wp = global.wp;

function load( relative ) {
	const file = path.join( root, relative );
	vm.runInThisContext( fs.readFileSync( file, 'utf8' ), { filename: file } );
}

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
// The surface is CONTAINER-DERIVED (`legacyAxes`, from PHP), and the reason is a bug
// this harness now pins: on a try_ template a bare `limit` is the TAG-level cap for
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
		legacyAxes: [ 'src', 'ref', 'srcTermIn', 'use', 'key' ]
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
		legacyAxes: [ 'src', 'ref', 'srcTermIn' ]
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

console.log( '\n' + ( total - fail ) + '/' + total + ' passed' );
process.exit( fail ? 1 : 0 );
