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
 * THE PROPERTY: `same` is a POSITIONAL backreference ("carry over from the previous
 * slot"). Sliding a slot down re-points it at a DIFFERENT neighbour, silently
 * changing what the tag resolves to. So compaction must MATERIALIZE a carried
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
	[ 'plain compaction — remove middle of 4, no carry-over anywhere', SELECTING,
		{ 'A': 'key(staff_name)', 'B': 'src(refs,office);key(city)', 'C': 'src(refs,region);key(name)', 'D': 'src(post,9999);use(title)' }, 2,
		'A:key(staff_name) | B:src(refs,region);key(name) | C:src(post,9999);use(title)' ],

	[ 'MATERIALIZE src — successor carried the removed slot\'s source', SELECTING,
		{ 'A': 'key(staff_name)', 'B': 'src(refs,office);key(city)', 'C': 'src(same);key(region_name)' }, 2,
		'A:key(staff_name) | B:src(refs,office);key(region_name)' ],

	[ 'MATERIALIZE read — successor carried the removed slot\'s read', SELECTING,
		{ 'A': 'key(staff_name)', 'B': 'src(refs,office);key(city)', 'C': 'src(refs,region);use(same)' }, 2,
		'A:key(staff_name) | B:src(refs,region);key(city)' ],

	[ 'PROMOTION — an all-carry-over slot 2 promoted to position 1 drops its illegal `same`', SELECTING,
		{ 'A': 'src(refs,office);key(city)', 'B': 'src(same);use(same)' }, 1,
		'A:src(refs,office);key(city)' ],

	[ 'no successor — removing the last slot needs no materialization', SELECTING,
		{ 'A': 'key(staff_name)', 'B': 'src(refs,office);key(city)', 'C': 'src(refs,region);key(name)' }, 3,
		'A:key(staff_name) | B:src(refs,office);key(city)' ],

	[ 'multi-hop materialization — the WHOLE chain carries, not just hop 1', SELECTING,
		{ 'A': 'key(a)', 'B': 'src(refs,office;refs,region);key(city)', 'C': 'src(same);key(zip)' }, 2,
		'A:key(a) | B:src(refs,office;refs,region);key(zip)' ],

	[ 'chained carry over — only the IMMEDIATE successor referenced the removed slot', SELECTING,
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
	// with a real value, so a residual carry over reaches position 1 only when the
	// REMOVED slot was itself carrying over (a hand-edited slot 1). Position 1 has no
	// predecessor, so `same` there is illegal and must drop to a plain unset axis.
	[ 'POSITION-1 STRIP (src) — removed slot was itself carrying over', SELECTING,
		{ 'A': 'src(same);key(a)', 'B': 'src(same);key(b)' }, 1,
		'A:key(b)' ],

	[ 'POSITION-1 STRIP (read) — removed slot carried its read', SELECTING,
		{ 'A': 'src(refs,x);use(same)', 'B': 'src(refs,y);use(same)' }, 1,
		'A:src(refs,y)' ],

	// Combining materialization: the successor's carried SOURCE still carries, and
	// its unset read stays unset (there is no read to carry over in a combining slot).
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
// read already carries over in a selecting container, so the sentinel would only add a
// token naming an axis with no control — and the renderer resolves both the same way.
check( 'KEY-ONLY seed omits the read sentinel', fold.emitSlot( rep.seedSlot( KEY_ONLY ) ), 'src(same)' );
check( 'CHAIN-ONLY seed omits the read sentinel', fold.emitSlot( rep.seedSlot( CHAIN_ONLY ) ), 'src(same)' );

// Compaction is read-shape blind — it materializes whatever axes the wire holds. A
// key-only successor carrying over its key must still carry it across a removal.
check(
	'KEY-ONLY compaction materializes a carried key',
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
// instead of carrying over), which the renderer treats as malformed.
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
	[ 'empty a 1-step chain on slot ≥2 — MUST fall back to explicit carry over', 'refs,office', 0, 2, 'src(same)' ],
	[ 'hop removal preserves a per-step limit on the survivor', 'refs,a;terms,category,limit[3]', 0, 1, 'src(terms,category,limit[3])' ],
];
HOP_CASES.forEach( function ( c ) {
	check( 'hop: ' + c[ 0 ], removeHop( c[ 1 ], c[ 2 ], c[ 3 ] ), c[ 4 ],
		c[ 1 ] + '  remove idx ' + c[ 2 ] + ' (slot ' + c[ 3 ] + ')' );
} );

// ── Advisory is DESCRIPTIVE — it must never gate ───────────────────────────
// inferIntent is the residue of a cut intent radio that also drove axis visibility.
// These rows pin that it is a pure read of the wire: every cell, plus the
// all-carry-over case that must report "unset" rather than a variation.
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

// ── stepArg — which ARG a step slug consumes, off the vocabulary record ──────
//
// Covered because its predecessor was NOT: `argKind` returned a hand-typed
// `'taxonomy'` (a third spelling of a slug the config already names twice) until
// 1.17.0, and stubbing it to return '' left the whole suite green — the rendered step
// controls it drives are React code no harness reaches. These cases are the mutation
// guard: every answer must come off `steps[slug].arg` (the compiler seam's own value,
// shipped on the record), so a step added on the PHP side needs no edit here, and a
// re-typed local string fails.
// The `steps` record here mirrors what bws_fold_wire_vocabulary() ships. It is INPUT,
// not an expectation: the cases assert that every answer is derived from whatever
// record arrives, never that the record holds these pairs.
const HOPS = rep.foldConfig( {
	fold: {
		container: 'join', combining: true, min: 2, max: 10,
		steps: {
			refs: { label: 'In Reference/Relational Field', arg: 'field' },
			terms: { label: 'In Taxonomy Term', arg: 'slug' },
			rows: { label: 'In Repeater Rows', arg: 'field' }
		}
	}
} );
check( 'stepArg: refs consumes a field', rep.stepArg( HOPS, 'refs' ), 'field' );
check( 'stepArg: terms consumes a taxonomy slug', rep.stepArg( HOPS, 'terms' ), 'slug' );
// refs and rows CONSUME THE SAME ARG — that equality is what lets a slug switch
// between them keep the field (asserted on the rendered picker below).
check( 'stepArg: rows consumes a field too', rep.stepArg( HOPS, 'rows' ), 'field' );
// A ROOT slug and an unknown one both take no arg, and the '' is load-bearing: it
// gates the Add-step row.
check( 'stepArg: an entity root takes no arg', rep.stepArg( HOPS, 'current' ), '' );
check( 'stepArg: an unknown slug takes no arg', rep.stepArg( HOPS, 'sideways' ), '' );
// The retired engine spellings are NOT wire slugs and have no record — a caller
// passing one mixed the vocabularies, and it must not answer a kind.
check( 'stepArg: a retired engine spelling is not a wire slug', rep.stepArg( HOPS, 'srcTermIn' ), '' );
// Derived means DEPENDENT: with no vocabulary on the option definition, no step takes
// an arg and every step renders bare. Same posture as `flatAxes` in the migrate twin —
// an absent record is a REGISTRATION bug, not a shape to tolerate — and asserted so
// the dependency is visible rather than discovered in the editor.
const NO_MAP = rep.foldConfig( { fold: { container: 'join', combining: true, min: 2, max: 10 } } );
check( 'stepArg: an absent vocabulary yields no arg at all (registration bug)', rep.stepArg( NO_MAP, 'refs' ), '' );

// ── An absent chain DISPLAYS the root it spells ─────────────────────────────
// A SelectControl whose value matches no row paints its FIRST row while believing
// nothing is selected, so the row on screen cannot be picked (selecting the displayed
// value fires no change) and, with no step in hand, `+ Add step` never appears. That
// shipped twice — once on the base-tag control, once here — which is why the display
// rule now lives in chainSteps, where both callers reach it.
//
// The assertions are on the RENDERED tree because that is where the bug was: the value
// and the enum were each individually correct.

// The RAW fold literal, kept as its own const — CHAIN_CONF below is what
// rep.foldConfig() RETURNS (its fields live at the TOP LEVEL: `.offer`, `.steps`,
// `.roots`, never `.fold`), so a fixture wanting "the same config with one row
// overridden" has to re-derive from THIS literal, never from `CHAIN_CONF.fold`
// (undefined — the bug PIN_CONF shipped with once, caught by #1's own review: it
// silently dropped `offer`/`steps`/`roots`, which is why an unfilled pinned root's
// "Add step" gap did not fail here the first time this section was written).
const CHAIN_FOLD = {
	container: 'try',
	combining: false,
	perSlotUse: true,
	min: 2,
	max: 5,
	// The slot twin's `ref` row respelled to the wire slug, exactly as
	// bws_build_fold_slot_options() ships it since #70 — the picker's value IS the
	// stored step's slug, so no translation stands between the row and the wire.
	srcRows: [
		{ value: 'current', label: 'Current' },
		{ value: 'refs', label: 'In Reference/Relational Field' }
	],
	srcRowsWithSame: [
		{ value: 'same', label: 'Same as Previous Source' },
		{ value: 'current', label: 'Current' }
	],
	// Shaped exactly as bws_fold_wire_vocabulary() ships it: one record per WIRE
	// slug — label declared once, `arg` from the compiler seam, `accepts` from the
	// engine's own refusal list, `produces` the step's output kind — plus the per-
	// container ordered OFFER and the parse-time root kinds (BWS_FOLD_PARSE_TIME_ROOT_KINDS
	// owns which roots have one; this fixture declares only `site`, and PIN_CONF below
	// declares the two pinning roots).
	steps: {
		refs: { label: 'In Reference/Relational Field', arg: 'field', accepts: [ 'post', 'term', 'user', 'meta_row', 'site' ], produces: 'post' },
		terms: { label: 'In Taxonomy Term', arg: 'slug', accepts: [ 'post' ], produces: 'term' },
		rows: { label: 'In Repeater Rows', arg: 'field', accepts: [ 'post', 'term', 'user', 'meta_row', 'site' ], produces: 'meta_row' }
	},
	offer: [ 'terms', 'refs', 'rows' ],
	roots: { site: 'site' },
	// The `rows` step's ARG picker, shipped since 1.21.0 wherever the step is offered
	// (bws_fold_rows_picker_def). Without it the control paints a field combo with no
	// label at all, which is the failure the offer made reachable.
	rowsOption: { label: 'Repeater Field Key', placeholder: 'team_members', typeDefault: 'repeater' },
	defaultRoot: 'current',
	// Shaped exactly as bws_fold_wire_vocabulary() ships it (#95). Supplied as DATA
	// because that is the property: the control authors none of these strings, and the
	// only thing it decides is WHICH help a given step gets.
	limitOption: {
		label: 'Limit results',
		placeholder: '0 (all)',
		help: 'Maximum number of results. Leave blank for all.',
		helpFanning: 'Maximum number of results for each previous-step result. Leave blank for all.'
	}
};
const CHAIN_CONF = rep.foldConfig( { fold: CHAIN_FOLD } );

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

function renderChain( chain, sameOnEmpty ) {
	return rep.chainSteps( {
		conf: CHAIN_CONF,
		chain: chain,
		onChange: function () {},
		sameOnEmpty: !! sameOnEmpty,
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
check( 'slot ≥2, no chain: shows the CARRY-OVER row, not `current`', sel2[ 0 ] && sel2[ 0 ].value, 'same' );
// A carry-over is not a chain to continue — appending to it would state a step off a
// source this slot has not chosen.
check( 'slot ≥2, no chain: Add step is NOT offered off a carry-over', hasAddStep( empty2 ), false );

// A real chain is untouched by the display rule. The picker's value is the stored
// step's OWN slug — nothing translates between the wire and the row any more.
const real = renderChain( [ { slug: 'refs', arg: 'office', limit: null } ], false );
check( 'a configured chain still shows its own root', selectsIn( real )[ 0 ].value, 'refs' );

// ── Slug switch: the ARG follows the vocabulary's `arg`, not the slug ────────
// Switching between two steps that CONSUME THE SAME ARG keeps the field (refs and
// rows both consume a field); a different arg kind drops it. The retired compare
// used engine spellings, under which refs ↔ rows compared unequal and silently
// dropped the field — unreachable then only because no container offered both.
// `limit` rides along either way: it bounds the step, not the arg.
let committed = null;
const switchable = rep.chainSteps( {
	conf: CHAIN_CONF,
	chain: [ { slug: 'refs', arg: 'office', limit: 3, extra: [] } ],
	onChange: function ( next ) { committed = next; },
	sameOnEmpty: false,
	slotNoun: 'attempt',
	stepContext: function () { return { state: {}, setState: function () {} }; }
} );
const switchPicker = selectsIn( switchable )[ 0 ];
switchPicker.onChange( 'rows' );
check( 'same arg kind: the field survives the slug switch', committed[ 0 ].arg, 'office' );
check( '...on the new slug', committed[ 0 ].slug, 'rows' );
check( '...with the limit riding along', committed[ 0 ].limit, 3 );
switchPicker.onChange( 'terms' );
check( 'different arg kind: the field is dropped', committed[ 0 ].arg, null );
check( '...and the limit still rides', committed[ 0 ].limit, 3 );

// ── A STORED slug absent from the vocabulary keeps its own row ───────────────
// Absence from `steps` means "offer it", never "refuse it": a SelectControl whose
// value is missing from its own options paints a different row while believing
// nothing is selected — the unselectable-row defect, arrived at from a hand-edited
// slug this time. The row is appended; the offer is NOT narrowed.
const unknownStored = renderChain( [ { slug: 'sideways', arg: null, limit: null } ], false );
const unknownPicker = selectsIn( unknownStored )[ 0 ];
check( 'a stored unknown slug paints its own value', unknownPicker.value, 'sideways' );
check(
	'...its own row is in its own list',
	( unknownPicker.options || [] ).some( function ( r ) { return 'sideways' === r.value; } ),
	true
);
check(
	'...and every offered step is still offered',
	( unknownPicker.options || [] ).some( function ( r ) { return 'terms' === r.value; } ),
	true
);

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
	'a `terms` step after a SITE root is not offered (the engine accepts a post input only)',
	lastPickerValues( afterSite ).indexOf( 'terms' ),
	-1
);
check(
	'a `refs` step after a SITE root IS offered (an options page holds relationship fields)',
	lastPickerValues( afterSite ).indexOf( 'refs' ) !== -1,
	true
);

// A step already STORED at a refused position stays in its OWN list, filter or not: a
// value missing from its own options paints a different row while believing nothing is
// selected — the seed-picker defect, arrived at from the other direction. Only what the
// author may ADD is filtered.
const storedDead = renderChain( [ { slug: 'site', arg: null, limit: null }, { slug: 'terms', arg: 'department', limit: null } ], false );
check( 'a stored-but-refused step still shows its own value', selectsIn( storedDead )[ 1 ].value, 'terms' );
check(
	'...and its own row is in its own list',
	lastPickerValues( storedDead ).indexOf( 'terms' ) !== -1,
	true
);

// Add step is gated on the OFFER, not on the registered list: with every registered
// step refused, an Add could only produce a dead step.
const siteOnly = renderChain( [ { slug: 'site', arg: null, limit: null } ], false );
check( 'Add step is still offered off site (refs applies)', hasAddStep( siteOnly ), true );

// ── The `rows` step's ARG picker (1.21.0, FW-74 ticket 07) ──────────────────
// The offer made this reachable: before it, `rows` was hand-edited wire and nothing
// rendered its argument control, so a missing `rowsOption` cost nothing. Now the control
// paints a field combo per stored `rows` step, and an absent config paints it with no
// label — which is why the picker definition ships from ONE owner beside the offer.

/** The ARG controls in a rendered tree, in step order (keyed `arg`, unlike `limit`). */
function argsIn( nodes ) {
	const out = [];
	( function walk( n, inArg ) {
		if ( ! n ) { return; }
		if ( Array.isArray( n ) ) { n.forEach( function ( c ) { walk( c, inArg ); } ); return; }
		const here = inArg || ( n.props && 'arg' === n.props.key );
		if ( here && ( n.type === global.wp.components.TextControl || n.type === global.wp.components.ComboboxControl ) ) {
			out.push( n.props );
			return;
		}
		( n.children || [] ).forEach( function ( c ) { walk( c, here ); } );
	}( nodes, false ) );
	return out;
}

const rowsStep = argsIn( renderChain( [ { slug: 'rows', arg: 'team_members', limit: null } ], false ) );
// The TextControl FALLBACK is what renders here (no field-combo global in this fixture),
// so the LABEL is the half both branches share and the half asserted. The `typeDefault`
// preset rides the combo branch only, and is pinned on the shipped config instead
// (slot-options-build-test.php) — asserting it here would pin the fallback, not the seam.
check( 'a `rows` step renders its argument picker with the shipped label', rowsStep[ 0 ] && rowsStep[ 0 ].label, 'Repeater Field Key' );

// A NESTED repeater is legal wire (`rows` accepts `meta_row`), so the second one is
// offered off the first. The engine's list is what says so; nothing here restates it.
const afterRows = renderChain( [ { slug: 'rows', arg: 'team_members', limit: null }, { slug: 'refs', arg: 'lead_ref', limit: null } ], false );
check(
	'a `rows` step IS offered after a `rows` step (a nested repeater)',
	lastPickerValues( afterRows ).indexOf( 'rows' ) !== -1,
	true
);

const TERMS_ONLY = rep.foldConfig( { fold: Object.assign( {}, {
	container: 'try',
	combining: false,
	perSlotUse: true,
	srcRows: [ { value: 'current', label: 'Current' }, { value: 'site', label: 'Site' } ],
	srcRowsWithSame: [],
	steps: {
		refs: { label: 'In Reference/Relational Field', arg: 'field', produces: 'post' },
		terms: { label: 'In Taxonomy Term', arg: 'slug', accepts: [ 'post' ], produces: 'term' },
		rows: { label: 'In Repeater Rows', arg: 'field', produces: 'meta_row' }
	},
	offer: [ 'terms' ],
	roots: { site: 'site' },
	defaultRoot: 'current'
} ) } );
const siteTermsOnly = rep.chainSteps( {
	conf: TERMS_ONLY,
	chain: [ { slug: 'site', arg: null, limit: null } ],
	onChange: function () {},
	sameOnEmpty: false,
	slotNoun: 'attempt',
	stepContext: function () { return { state: {}, setState: function () {} }; }
} );
check( 'Add step is withheld when every registered step is refused', hasAddStep( siteTermsOnly ), false );

// An ambient root has no parse-time kind, so nothing is filtered — the editor must not
// guess whether `current` is a post or a term.
const fromCurrent = renderChain( [ { slug: 'current', arg: null, limit: null } ], false );
check( 'an ambient root filters nothing', lastPickerValues( fromCurrent ).indexOf( 'terms' ) !== -1, true );

// A term cannot step to terms again; it can step to a relationship.
const afterTerms = renderChain( [ { slug: 'terms', arg: 'department', limit: null }, { slug: 'refs', arg: 'lead', limit: null } ], false );
check( 'a second `terms` step off a term is not offered', lastPickerValues( afterTerms ).indexOf( 'terms' ), -1 );

// ── The per-step LIMIT control: label, and which help it gets (#95) ─────────
// Asserted on the RENDERED tree, and on the TEXT rather than on a predicate. The rule
// that decides the help already exists once, as the PHP fanning predicate and its
// grammar twin; a test that reached for a boolean would license a second copy here,
// which is precisely what the strings-on-the-definition rule exists to prevent.

/** The per-step limit controls in a rendered tree, in step order. */
function limitsIn( nodes ) {
	const out = [];
	( function walk( n, inLimit ) {
		if ( ! n ) { return; }
		if ( Array.isArray( n ) ) { n.forEach( function ( c ) { walk( c, inLimit ); } ); return; }
		const here = inLimit || ( n.props && 'limit' === n.props.key );
		if ( here && n.type === global.wp.components.TextControl ) {
			out.push( n.props );
			return;
		}
		( n.children || [] ).forEach( function ( c ) { walk( c, here ); } );
	}( nodes, false ) );
	return out;
}

const oneStep = limitsIn( renderChain( [ { slug: 'refs', arg: 'office', limit: null } ], false ) );
check( 'the step limit control is labelled for what it BOUNDS', oneStep[ 0 ] && oneStep[ 0 ].label, 'Limit results' );
check( 'the placeholder names the value that means unlimited', oneStep[ 0 ] && oneStep[ 0 ].placeholder, '0 (all)' );
// Nothing upstream fans — the step IS the first thing that fans, so per-input and total
// coincide and the clause would describe a distinction that cannot arise.
check(
	'nothing fanning upstream: the plain help',
	oneStep[ 0 ] && oneStep[ 0 ].help,
	'Maximum number of results. Leave blank for all.'
);

// A step BELOW a fanning one: the total is a product, not a ceiling, and the help says so.
const belowFanning = limitsIn( renderChain( [
	{ slug: 'refs', arg: 'office', limit: null },
	{ slug: 'terms', arg: 'department', limit: null }
], false ) );
check(
	'a fanning step above: the per-input help',
	belowFanning[ 1 ] && belowFanning[ 1 ].help,
	'Maximum number of results for each previous-step result. Leave blank for all.'
);
check(
	'...and the fanning step itself still takes the plain form',
	belowFanning[ 0 ] && belowFanning[ 0 ].help,
	'Maximum number of results. Leave blank for all.'
);

// POSITION IS NOT THE CONDITION. A step at chain position 3 whose predecessors resolve
// one entity each still has one input, so it takes the plain form — an index-based rule
// would have it stating a per-input product that cannot happen. (A ROOT renders no limit
// control at all, so the `site` step contributes nothing to this list; the `terms` step
// is chain position 3 and list index 1.)
const deepSingle = limitsIn( renderChain( [
	{ slug: 'site', arg: null, limit: null },
	{ slug: 'refs', arg: null, limit: null },
	{ slug: 'terms', arg: 'department', limit: null }
], false ) );
check( 'a ROOT renders no limit control, so only the two steps do', deepSingle.length, 2 );
check(
	'chain position 3 with single-valued predecessors: still the plain help',
	deepSingle[ 1 ] && deepSingle[ 1 ].help,
	'Maximum number of results. Leave blank for all.'
);
// The argless `refs` above it is why: the compiler DROPS it (a field-less step would
// short-circuit to empty), so the chain does not fan on its own account. The same
// predicate the migrator stamps by, reached through its grammar twin — not re-derived.
check(
	'...because an ARGLESS fanning step upstream does not count',
	deepSingle[ 0 ] && deepSingle[ 0 ].help,
	'Maximum number of results. Leave blank for all.'
);

// The rows above assert the SHIPPED text, which is what an author reads — but they would
// pass just as well against a control that hard-coded it. So one config of sentinels,
// which only a control that renders what it is GIVEN can satisfy. This is the half of
// the property the shipped strings cannot state, and it is the half that rots: the
// registration harness owns whether the definition ships the right words.
const SENTINEL = rep.foldConfig( { fold: Object.assign( {}, CHAIN_CONF, {
	limitOption: { label: 'L-SENT', placeholder: 'P-SENT', help: 'H-PLAIN', helpFanning: 'H-FAN' }
} ) } );
function sentinelLimits( chain ) {
	return limitsIn( rep.chainSteps( {
		conf: SENTINEL,
		chain: chain,
		onChange: function () {},
		sameOnEmpty: false,
		slotNoun: 'attempt',
		stepContext: function () { return { state: {}, setState: function () {} }; }
	} ) );
}
const sent = sentinelLimits( [
	{ slug: 'refs', arg: 'office', limit: null },
	{ slug: 'terms', arg: 'department', limit: null }
] );
check( 'the label is the definition\'s, not the control\'s', sent[ 0 ] && sent[ 0 ].label, 'L-SENT' );
check( 'the placeholder is the definition\'s', sent[ 0 ] && sent[ 0 ].placeholder, 'P-SENT' );
check( 'the plain help is the definition\'s', sent[ 0 ] && sent[ 0 ].help, 'H-PLAIN' );
check( 'the fanning help is the definition\'s', sent[ 1 ] && sent[ 1 ].help, 'H-FAN' );

// PER-STEP limit labels (1.18.0): a step that names what it PRODUCES wears its own label
// and the generic one is the fallback. Sentinels again, and the two rows are one property
// — a control that always read the step record would leave a limitLabel-less slug
// unlabelled, and one that never did would show the generic string on every step.
const PERKIND = rep.foldConfig( { fold: Object.assign( {}, CHAIN_CONF, {
	limitOption: { label: 'L-GENERIC', placeholder: 'P', help: 'H', helpFanning: 'HF' },
	steps: Object.assign( {}, CHAIN_CONF.steps, {
		refs: Object.assign( {}, CHAIN_CONF.steps.refs, { limitLabel: 'L-REFS' } )
	} )
} ) } );
const perKind = limitsIn( rep.chainSteps( {
	conf: PERKIND,
	chain: [
		{ slug: 'refs', arg: 'office', limit: null },
		{ slug: 'terms', arg: 'department', limit: null }
	],
	onChange: function () {},
	sameOnEmpty: false,
	slotNoun: 'attempt',
	stepContext: function () { return { state: {}, setState: function () {} }; }
} ) );
check( 'a step with its own limitLabel wears it', perKind[ 0 ] && perKind[ 0 ].label, 'L-REFS' );
check( '...and a step without one falls back to the generic label', perKind[ 1 ] && perKind[ 1 ].label, 'L-GENERIC' );

// Derived means DEPENDENT — same posture as `stepArg` above and as `flatAxes` in the
// migrate twin: with no `limitOption` on the definition the control renders an unlabelled,
// help-less field rather than inventing words. Asserted so the dependency is VISIBLE here
// instead of discovered in the editor; `slot-options-build-test.php` is what pins that
// registration actually ships it.
const NO_LIMIT_CFG = limitsIn( rep.chainSteps( {
	conf: rep.foldConfig( { fold: Object.assign( {}, CHAIN_CONF, { limitOption: null } ) } ),
	chain: [ { slug: 'refs', arg: 'office', limit: null } ],
	onChange: function () {},
	sameOnEmpty: false,
	slotNoun: 'attempt',
	stepContext: function () { return { state: {}, setState: function () {} }; }
} ) );
check( 'no limitOption: the field still renders (registration bug, not a crash)', NO_LIMIT_CFG.length, 1 );
check( '...with no invented label', NO_LIMIT_CFG[ 0 ] && NO_LIMIT_CFG[ 0 ].label, undefined );
check( '...and no invented help', NO_LIMIT_CFG[ 0 ] && NO_LIMIT_CFG[ 0 ].help, undefined );

// ── The FIELD CONFIGURATION NOTE (#96) ──────────────────────────────────────
//
// A statement about the field the author just picked, rendered between the field key
// control and Limit results. Every string in it is authored in PHP and arrives on the
// field-discovery envelope, so these rows supply SENTINEL segments: rows asserting the
// six real sentences would pass just as well against a control that hard-coded them,
// and the derivation harness (`field-discovery-test.php`) is what owns the wording.
//
// The subject is the RENDERED TREE, for the same reason the limit rows above are: the
// note has no other observer, and its whole contract is what an author sees.

/** The note panel node in a rendered tree, or null. */
function noteIn( nodes ) {
	let found = null;
	( function walk( n ) {
		if ( ! n || found ) { return; }
		if ( Array.isArray( n ) ) { n.forEach( walk ); return; }
		if ( n.props && 'fieldnote' === n.props.key ) { found = n; return; }
		( n.children || [] ).forEach( walk );
	}( nodes ) );
	return found;
}

/** The note as `text` / `*text` (starred = emphasised), joined — readable failures. */
function noteText( nodes ) {
	const node = noteIn( nodes );
	if ( ! node ) { return '(no note)'; }
	const out = [];
	( function walk( n ) {
		if ( ! n || 'string' === typeof n ) { return; }
		if ( Array.isArray( n ) ) { n.forEach( walk ); return; }
		if ( 'span' === n.type ) {
			out.push( ( n.props.style && n.props.style.fontWeight ? '*' : '' ) + n.children[ 0 ] );
			return;
		}
		( n.children || [] ).forEach( walk );
	}( node ) );
	return out.join( ' | ' );
}

/** Ordered child keys of the first rendered step — where the note SITS. */
function stepKidKeys( nodes ) {
	const kids = ( ( nodes[ 0 ] || {} ).children || [] )[ 0 ] || [];
	return kids.map( function ( k ) { return ( k && k.props ) ? k.props.key : null; } ).join( ',' );
}

/** One post-kind envelope group holding the given field entries. */
function envWith( fields ) {
	return { post: [ { group_title: 'G', kind: 'post', scope: [], source: 'acf', fields: fields } ] };
}

/** Render with an envelope in scope, then take it back out. */
function withEnvelope( env, fn ) {
	global.window.bwsFieldEnvelope = env;
	try {
		return fn();
	} finally {
		delete global.window.bwsFieldEnvelope;
	}
}

const TWO_SEGMENTS = [
	{ text: 'N-LEAD', emph: false },
	{ text: 'N-EMPH', emph: true }
];
const refsStep = [ { slug: 'refs', arg: 'partners', limit: null } ];

check(
	'the note renders the segments it is GIVEN, in order',
	withEnvelope( envWith( [ { name: 'partners', label: 'Partners', type: 'relationship', note: TWO_SEGMENTS } ] ),
		function () { return noteText( renderChain( refsStep, false ) ); } ),
	'N-LEAD | *N-EMPH'
);
// Emphasis is a property of the SEGMENT, not of its position: the marked one is
// weighted and the other is not, which is the reason the shape is a list rather than a
// string plus a trailing emphasis field.
check(
	'a single unemphasised segment renders unweighted',
	withEnvelope( envWith( [ { name: 'partners', type: 'relationship', note: [ { text: 'N-ONLY', emph: false } ] } ] ),
		function () { return noteText( renderChain( refsStep, false ) ); } ),
	'N-ONLY'
);
check(
	'a field with nothing to say renders NO note',
	withEnvelope( envWith( [ { name: 'partners', type: 'relationship', note: null } ] ),
		function () { return noteText( renderChain( refsStep, false ) ); } ),
	'(no note)'
);
check(
	'a field key matching nothing discovered renders no note',
	withEnvelope( envWith( [ { name: 'other_field', type: 'relationship', note: TWO_SEGMENTS } ] ),
		function () { return noteText( renderChain( refsStep, false ) ); } ),
	'(no note)'
);
// No discovery on the page at all is silence, not a fallback: with no definitions in
// hand there is nothing to say.
check( 'no envelope at all renders no note', noteText( renderChain( refsStep, false ) ), '(no note)' );
// Clearing the field takes the note with it — the note describes what is SELECTED, and
// an argless step shows the "will be skipped" warning instead.
check(
	'clearing the field removes the note',
	withEnvelope( envWith( [ { name: 'partners', type: 'relationship', note: TWO_SEGMENTS } ] ),
		function () { return noteText( renderChain( [ { slug: 'refs', arg: null, limit: null } ], false ) ); } ),
	'(no note)'
);
// A `terms` step's arg is a TAXONOMY slug, not a field key. Looking a note up for it
// would describe a field the step never reads.
check(
	'a taxonomy step never carries a field note',
	withEnvelope( envWith( [ { name: 'department', type: 'relationship', note: TWO_SEGMENTS } ] ),
		function () { return noteText( renderChain( [ { slug: 'terms', arg: 'department', limit: null } ], false ) ); } ),
	'(no note)'
);

// AMBIGUITY IS SILENCE. The wire stores a BARE key, which entries of different kinds
// can share, and a note is a claim about ONE field's configuration — so a note shows
// only where every entry holding that key agrees. This mirrors the field picker's own
// rule for an ambiguous key ("show the bare key, assert nothing").
check(
	'two entries with DIFFERENT notes fall silent',
	withEnvelope(
		{
			post: [ { fields: [ { name: 'partners', note: TWO_SEGMENTS } ] } ],
			term: [ { fields: [ { name: 'partners', note: [ { text: 'N-OTHER', emph: false } ] } ] } ]
		},
		function () { return noteText( renderChain( refsStep, false ) ); }
	),
	'(no note)'
);
// One entry WITH a note and one without is a disagreement too — skipping the note-less
// entry would assert the noteworthy field's configuration for both.
check(
	'a noted entry and an unnoted one fall silent',
	withEnvelope(
		{
			post: [ { fields: [ { name: 'partners', note: TWO_SEGMENTS } ] } ],
			term: [ { fields: [ { name: 'partners', note: null } ] } ]
		},
		function () { return noteText( renderChain( refsStep, false ) ); }
	),
	'(no note)'
);
check(
	'two entries that AGREE still show it (the same field in two homes)',
	withEnvelope(
		{
			post: [
				{ fields: [ { name: 'partners', note: TWO_SEGMENTS } ] },
				{ fields: [ { name: 'partners', note: [ { text: 'N-LEAD', emph: false }, { text: 'N-EMPH', emph: true } ] } ] }
			]
		},
		function () { return noteText( renderChain( refsStep, false ) ); }
	),
	'N-LEAD | *N-EMPH'
);

// PLACEMENT: beneath the field key control, above Limit results — so it reads as the
// setup for the number the author is about to choose. The adjacency is the note's whole
// value, and it is a property of ORDER, which no text assertion can see.
check(
	'the note sits between the field control and the limit control',
	withEnvelope( envWith( [ { name: 'partners', type: 'relationship', note: TWO_SEGMENTS } ] ),
		function () { return stepKidKeys( renderChain( refsStep, false ) ); } ),
	'src,arg,fieldnote,limit'
);

// IT GATES NOTHING. Everything else about the step renders and behaves identically with
// a note present — the limit control, its help, the append affordance, and what a slug
// switch commits.
const noted = withEnvelope( envWith( [ { name: 'partners', type: 'relationship', note: TWO_SEGMENTS } ] ), function () {
	let out = null;
	const nodes = rep.chainSteps( {
		conf: CHAIN_CONF,
		chain: [ { slug: 'refs', arg: 'partners', limit: 3, extra: [] } ],
		onChange: function ( next ) { out = next; },
		sameOnEmpty: false,
		slotNoun: 'attempt',
		stepContext: function () { return { state: {}, setState: function () {} }; }
	} );
	selectsIn( nodes )[ 0 ].onChange( 'rows' );
	return { nodes: nodes, committed: out };
} );
check( 'with a note present, the limit control still renders', limitsIn( noted.nodes ).length, 1 );
check( '...with its own value untouched', limitsIn( noted.nodes )[ 0 ].value, '3' );
check( '...and its own help', limitsIn( noted.nodes )[ 0 ].help, 'Maximum number of results. Leave blank for all.' );
check( '...Add step is still reachable', hasAddStep( noted.nodes ), true );
check( '...and a slug switch commits exactly what it would without one', noted.committed[ 0 ].slug, 'rows' );
check( '...carrying the same field', noted.committed[ 0 ].arg, 'partners' );
check( '...and the same limit', noted.committed[ 0 ].limit, 3 );

// =========================================================================
// A PINNED ROOT's argument control (FW-39, D5/D6/D11) — mounted at position 0,
// off the SAME `srcRows` row the root's own SelectControl reads. `slot-fold-control.js`
// is the ONE place a chain's root and its steps both render, so this is the seam that
// makes "the same picker on a base tag, a {{join}} field and a try_ attempt" (D11, D15)
// true by construction rather than by three copies staying in sync.
// =========================================================================
console.log( '\npinned root argument (FW-39)' );

const PIN_CONF = rep.foldConfig( { fold: Object.assign( {}, CHAIN_FOLD, {
	// Both pinning roots declare a PARSE-TIME kind, which is the whole mechanism behind
	// "the editor offers steps off a pinned root with no render having occurred" (ticket
	// 04): the offer is a function of `roots` + `steps`, both shipped in the config, so
	// nothing below queries anything.
	roots: { site: 'site', term: 'term', post: 'post' },
	srcRows: [
		{ value: 'current', label: 'Current' },
		{ value: 'refs', label: 'In Reference/Relational Field' },
		{ value: 'term', label: 'Term', arg: { label: 'Term', control: 'bws-entity-picker', argless: 'refuse', kind: 'term' } },
		{ value: 'post', label: 'Post', arg: { label: 'Post', control: 'bws-entity-picker', argless: 'refuse', kind: 'post' } }
	],
	srcRowsWithSame: [
		{ value: 'same', label: 'Same as Previous Source' },
		{ value: 'current', label: 'Current' },
		{ value: 'term', label: 'Term', arg: { label: 'Term', control: 'bws-entity-picker', argless: 'refuse', kind: 'term' } }
	]
} ) } );

function renderPinChain( chain, EntityPicker, sameOnEmpty, stepContext ) {
	// KEYED ON THE CONTROL NAME (`bws-entity-picker`), matching how the control looks
	// itself up in the shipped code — `window.bwsRootArgControls[ rootArg.control ]`,
	// never a bare global whose mere presence would mount the wrong picker for a root
	// declaring a different control name.
	const prior = global.window.bwsRootArgControls;
	if ( EntityPicker ) {
		global.window.bwsRootArgControls = { 'bws-entity-picker': EntityPicker };
	} else {
		delete global.window.bwsRootArgControls;
	}
	try {
		return rep.chainSteps( {
			conf: PIN_CONF,
			chain: chain,
			onChange: function () {},
			sameOnEmpty: !! sameOnEmpty,
			slotNoun: 'attempt',
			stepContext: stepContext || function () { return { state: {}, setState: function () {} }; }
		} );
	} finally {
		if ( prior === undefined ) {
			delete global.window.bwsRootArgControls;
		} else {
			global.window.bwsRootArgControls = prior;
		}
	}
}

/** The mounted entity-picker element in a rendered chain, or null. */
function pickerIn( nodes, PickerType ) {
	let found = null;
	( function walk( n ) {
		if ( ! n || found ) { return; }
		if ( Array.isArray( n ) ) { n.forEach( walk ); return; }
		if ( n.type === PickerType ) { found = n; return; }
		( n.children || [] ).forEach( walk );
	}( nodes ) );
	return found;
}

const ENTITY_PICKER_STUB = {};
const pinNoArg = renderPinChain( [ { slug: 'term', arg: null, limit: null } ], ENTITY_PICKER_STUB );
const pinPicker = pickerIn( pinNoArg, ENTITY_PICKER_STUB );

check( 'the root row\'s declared control mounts at position 0', !! pinPicker, true );
check( 'it is handed the DERIVED kind off the row\'s own `arg.kind`', pinPicker && pinPicker.props.kind, 'term' );
check( 'and the row\'s own label, verbatim', pinPicker && pinPicker.props.label, 'Term' );
function hasKey( nodes, key ) {
	let found = false;
	( function walk( n ) {
		if ( ! n || found ) { return; }
		if ( Array.isArray( n ) ) { n.forEach( walk ); return; }
		if ( n.props && key === n.props.key ) { found = true; return; }
		( n.children || [] ).forEach( walk );
	}( nodes ) );
	return found;
}
check( 'an EMPTY pin is warned — it will be skipped, not silently ambient (D8)', hasKey( pinNoArg, 'rootargwarn' ), true );
// D8's editor-side mirror: an UNFILLED pin must not ALSO offer "Add step" beside that
// warning. `stepArg()` answers '' for any slug outside the step vocabulary — which a
// root slug always is, roots and steps being disjoint namespaces — so without a
// root-aware completeness test this button would appear on a chain the factory
// unconditionally refuses (traversal-pipeline.php's argless-root decline).
check( '...and "Add step" does NOT appear beside it — the chain is not buildable yet', hasKey( pinNoArg, 'addstep' ), false );

const pinWithArg = renderPinChain( [ { slug: 'term', arg: '34', limit: null } ], ENTITY_PICKER_STUB );
check( 'a FILLED pin carries its stored value through to the control', pickerIn( pinWithArg, ENTITY_PICKER_STUB ).props.value, '34' );
// ...and NOW "Add step" is offered — a pinning root that IS filled is a complete,
// buildable chain, exactly as a `refs`/`rows` step with its field set already is.
check( '...and a FILLED pin DOES offer "Add step" — the chain is complete', hasKey( pinWithArg, 'addstep' ), true );
check( '...and carries no "will be skipped" warning', hasKey( pinWithArg, 'rootargwarn' ), false );

// No entity-picker component loaded (a partial deploy, or a harness that never stubs
// it) DEGRADES to a plain text input rather than rendering nothing — the same
// FieldCombo-or-TextControl fallback pattern the refs/rows arg control already takes.
const pinFallback = renderPinChain( [ { slug: 'term', arg: '34', limit: null } ], null );
check(
	'with no entity-picker component available, the control falls back to plain text',
	!! pickerIn( pinFallback, global.wp.components.TextControl ),
	true
);

// A NON-PINNING root (no `arg` on its row) renders no root-argument control at all —
// this branch must not fire for `current`/`refs`, or every ordinary chain would sprout
// an entity picker nobody asked for.
const noPin = renderPinChain( [ { slug: 'current' } ], ENTITY_PICKER_STUB );
check( 'an ordinary (non-pinning) root mounts no entity-picker control', !! pickerIn( noPin, ENTITY_PICKER_STUB ), false );

// A pinned root behind a `same` carry (slot ≥2, `sameOnEmpty: true`) is offered on the
// SAME terms — position 0's row lookup reads BOTH `srcRows` and `srcRowsWithSame`
// (rootArgOf checks both pools), so a slot ≥2 picking `term` gets the identical control
// slot 1 does.
const pinSlot2 = renderPinChain( [ { slug: 'term', arg: '5', limit: null } ], ENTITY_PICKER_STUB, true );
check( 'slot ≥2 offers the same pinned-root control (D11 — base and slot ship together)', !! pickerIn( pinSlot2, ENTITY_PICKER_STUB ), true );

// ── STEPS OFF A PINNED ROOT (FW-39 ticket 04, D3) ────────────────────────────
//
// A pinned root is a REAL chain root, so the step offer off it is the engine's own
// admission list and nothing else — the same derive that already narrows the offer off
// `site`. What makes the PIN different from `current` is only that its kind is known at
// PARSE time (`conf.roots`), so this whole section runs with no render, no query and no
// resolver: the offer is computed from the shipped config alone. That is the acceptance
// criterion, stated as the way these rows are able to exist.
/** Option values of the LAST picker in a rendered pinned chain — the step-slug offer. */
function pinLastValues( nodes ) {
	const sels = selectsIn( nodes );
	return ( sels[ sels.length - 1 ].options || [] ).map( function ( r ) { return r.value; } );
}

const afterPinnedTerm = renderPinChain(
	[ { slug: 'term', arg: '34', limit: null }, { slug: 'refs', arg: 'dept_lead', limit: null } ],
	ENTITY_PICKER_STUB
);
check(
	'a `refs` step IS offered off a pinned TERM root (refs accepts a term input)',
	pinLastValues( afterPinnedTerm ).indexOf( 'refs' ) !== -1,
	true
);
// `rows` IS offered off the pin, and it is the same rule answering: the engine accepts a
// term input for `rows`, so the pin's kind admits it exactly as it admits `refs`. Until
// 1.21.0 it was absent here for a CONTAINER reason instead — no arm assembled a repeater
// row, so `rows` was on no offer at all — and that absence was never a kind refusal. Both
// halves stay asserted so the row below is read as the kind rule it is.
check( '`rows` IS offered off the pin — the offer carries it since 1.21.0', pinLastValues( afterPinnedTerm ).indexOf( 'rows' ) !== -1, true );
check(
	'...and that is a KIND answer — the engine accepts a term input for `rows`',
	CHAIN_FOLD.steps.rows.accepts.indexOf( 'term' ) !== -1,
	true
);
check(
	'a `terms` step is NOT offered off a pinned TERM root — there is no term→term edge',
	pinLastValues( afterPinnedTerm ).indexOf( 'terms' ),
	-1
);

// The POST pin is the contrast that makes the row above a KIND rule rather than a rule
// about pinned roots: the SAME step the term pin refuses is offered off the post pin.
const afterPinnedPost = renderPinChain(
	[ { slug: 'post', arg: '1692', limit: null }, { slug: 'refs', arg: 'partner_staff', limit: null } ],
	ENTITY_PICKER_STUB
);
check(
	'a `terms` step IS offered off a pinned POST root',
	pinLastValues( afterPinnedPost ).indexOf( 'terms' ) !== -1,
	true
);

// A SECOND step is offered on what the FIRST step produced, not on the pin — `refs`
// produces a post, so `terms` becomes offerable one hop after the pin refused it. This is
// the `term,34;refs,<rel>;terms,<tax>` chain, offered a step at a time.
// (`rows` is the HELD slug at that position on purpose — a held `terms` would be included
// as `keep` whatever the filter said, and the row would pass while asserting nothing.)
const afterPinThenRefs = renderPinChain(
	[
		{ slug: 'term', arg: '34', limit: null },
		{ slug: 'refs', arg: 'dept_lead', limit: null },
		{ slug: 'rows', arg: 'team_members', limit: null }
	],
	ENTITY_PICKER_STUB
);
check(
	'...and `terms` IS offered after a `refs` hop off that same pin (the D3 headline chain)',
	pinLastValues( afterPinThenRefs ).indexOf( 'terms' ) !== -1,
	true
);

// Stored-but-refused paints its own row here as everywhere: a hand-authored
// `term,34;terms,department` is shown as authored and resolves to nothing at render
// (fold-test-matrix.md §F22.5), never repainted as a different step.
const pinStoredDead = renderPinChain(
	[ { slug: 'term', arg: '34', limit: null }, { slug: 'terms', arg: 'department', limit: null } ],
	ENTITY_PICKER_STUB
);
check( 'a hand-authored refused step off a pin still shows its own value', selectsIn( pinStoredDead )[ 1 ].value, 'terms' );
check(
	'...and its own row is in its own list',
	pinLastValues( pinStoredDead ).indexOf( 'terms' ) !== -1,
	true
);

// ── THE PIN REACHES THE STEP'S FIELD PICKER (FW-39 ticket 05, D22) ──────────
//
// The picker narrows its own list; what this control owes it is the pin, spelled the way
// the base tag's `src` spells it, so ONE narrowing rule serves the base tag and both fold
// containers rather than three. The picker is stubbed here because the subject is the
// hand-off, not the narrowing — `field-combo-control-test.js` §F13 holds the other side.
//
// POSITION 1 ONLY. The entity a step's field is read off is whatever the chain resolved
// to just before it, and only at position 1 is that the root; handing the pin further
// down would narrow a list against an entity two hops away from the field being picked.
const FIELD_COMBO_STUB = {};

function renderPinChainWithPicker( chain ) {
	const prior = global.window.bwsFieldComboControl;
	global.window.bwsFieldComboControl = FIELD_COMBO_STUB;
	try {
		// The container's own synthetic context, as `chainSteps`' two real callers build
		// it: the step's field key, which the pin hand-off must CARRY rather than replace.
		return renderPinChain( chain, ENTITY_PICKER_STUB, false, function ( stepObj ) {
			return { state: { key: stepObj.arg || '' }, setState: function () {} };
		} );
	} finally {
		if ( prior === undefined ) {
			delete global.window.bwsFieldComboControl;
		} else {
			global.window.bwsFieldComboControl = prior;
		}
	}
}

/** Every mounted field-picker element in a rendered chain, in order. */
function fieldPickersIn( nodes ) {
	const found = [];
	( function walk( n ) {
		if ( ! n ) { return; }
		if ( Array.isArray( n ) ) { n.forEach( walk ); return; }
		if ( n.type === FIELD_COMBO_STUB ) { found.push( n ); }
		( n.children || [] ).forEach( walk );
	}( nodes ) );
	return found;
}

const pinnedRefsArg = fieldPickersIn( renderPinChainWithPicker( [
	{ slug: 'term', arg: '34', limit: null },
	{ slug: 'refs', arg: 'dept_lead', limit: null }
] ) );
check(
	'a step at position 1 gets the pin as a `src` token, spelled as the wire spells it',
	pinnedRefsArg.length === 1 && pinnedRefsArg[ 0 ].props.context.state.src,
	'term,34'
);
// The caller's own synthetic context is CARRIED, not replaced — the field key the picker
// round-trips lives there, and a hand-off that dropped it would blank the stored field.
check(
	"...alongside the field key the caller's own context already carried",
	pinnedRefsArg[ 0 ].props.context.state.key,
	'dept_lead'
);

const deepStepArg = fieldPickersIn( renderPinChainWithPicker( [
	{ slug: 'term', arg: '34', limit: null },
	{ slug: 'refs', arg: 'dept_lead', limit: null },
	{ slug: 'rows', arg: 'team_members', limit: null }
] ) );
check(
	'a step after a `refs` hop gets nothing — a refs argument names the field stepped THROUGH, not what it lands on',
	deepStepArg.length === 2 && deepStepArg[ 1 ].props.context.state.src,
	undefined
);

// A `rows` STEP names what it resolved to — the property the root and `rows` share and
// `refs` and `terms` lack. So its successor is handed it at ANY position: the rule is what
// the predecessor SAYS, never how far along it sits, and the row above is the contrast that
// makes that readable — same position, different predecessor, opposite answer.
const afterRowsArg = fieldPickersIn( renderPinChainWithPicker( [
	{ slug: 'rows', arg: 'duty_roster', limit: null },
	{ slug: 'rows', arg: 'shifts', limit: null }
] ) );
check(
	'a step after a `rows` step gets that repeater as a `src` token',
	afterRowsArg.length === 2 && afterRowsArg[ 1 ].props.context.state.src,
	'rows,duty_roster'
);
check(
	"...carrying the successor's own field key, as the root hand-off does",
	afterRowsArg[ 1 ].props.context.state.key,
	'shifts'
);
check(
	'...and the `rows` step at position 0 gets nothing — it HAS no predecessor',
	afterRowsArg[ 0 ].props.context.state.src,
	undefined
);

// Position 3, behind two hops that name nothing, still gets its IMMEDIATE predecessor.
const deepAfterRows = fieldPickersIn( renderPinChainWithPicker( [
	{ slug: 'term', arg: '34', limit: null },
	{ slug: 'refs', arg: 'dept_lead', limit: null },
	{ slug: 'rows', arg: 'team_members', limit: null },
	{ slug: 'refs', arg: 'lead_ref', limit: null }
] ) );
check(
	'a step deep in a chain reads its immediate predecessor, not the root',
	deepAfterRows[ deepAfterRows.length - 1 ].props.context.state.src,
	'rows,team_members'
);

// An ARGLESS `rows` names no repeater, so it hands over nothing — the same test the root
// arm applies, asked at the other naming position.
const arglessRows = fieldPickersIn( renderPinChainWithPicker( [
	{ slug: 'rows' },
	{ slug: 'refs', arg: 'lead_ref', limit: null }
] ) );
check(
	'an ARGLESS `rows` step hands over nothing — it names no repeater to narrow against',
	arglessRows[ arglessRows.length - 1 ].props.context.state.src,
	undefined
);

const unpinnedStepArg = fieldPickersIn( renderPinChainWithPicker( [
	{ slug: 'current' },
	{ slug: 'refs', arg: 'dept_lead', limit: null }
] ) );
check(
	'an ARGLESS root hands over no pin — `current` has no entity to narrow against',
	unpinnedStepArg[ 0 ].props.context.state.src,
	undefined
);

console.log( '\n' + ( total - fail ) + '/' + total + ' passed' );
process.exit( fail ? 1 : 0 );
