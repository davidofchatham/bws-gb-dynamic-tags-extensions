/**
 * SPIKE B — slot-compaction probe for the {{proto_fold}} repeater.
 *
 * NOT SHIPPED CODE (tools/spike, .distignore'd). Run: node tools/spike/compaction-probe.js
 * Exits non-zero on failure (house harness convention).
 *
 * WHY THIS EXISTS: out-of-order slot removal is the one repeater operation the
 * PHP render side can never exercise — compaction lives entirely in the control,
 * and `bws render-tag` only ever sees wire that has ALREADY been compacted. The
 * editor is the only other place it runs, and eyeballing it there does not catch
 * the case that actually bites (below).
 *
 * THE PROPERTY UNDER TEST: `same` is a POSITIONAL backreference — "inherit from
 * the previous slot". Sliding a slot down therefore re-points its `same` at a
 * DIFFERENT neighbour, silently changing what the tag resolves to. Compaction
 * must MATERIALIZE an inherited axis against the slot being removed BEFORE
 * renumbering. Hop removal has no equivalent hazard (hops hold no backreference),
 * which is why the hop pattern could not simply be copied across.
 *
 * Loads the real control by stubbing the wp globals and re-exporting the IIFE
 * internals — so the probe tests the SHIPPING logic, not a reimplementation.
 */

global.window = { wp: null, bwsProtoFold: { srcOptions: [
	{ value: 'current', label: 'Current' },
	{ value: 'ref',     label: 'Ref' },
	{ value: 'post',    label: 'Post' },
	{ value: 'same',    label: 'Same' },
] } };
global.wp = {
	hooks:      { addFilter: function () {} },
	element:    { createElement: function () {}, Fragment: 'F' },
	components: { SelectControl: {}, TextControl: {}, Button: {} },
	i18n:       { __: function ( s ) { return s; } },
};
global.window.wp = global.wp;

var fs   = require( 'fs' );
var path = require( 'path' );
var file = path.join( __dirname, 'proto-fold-control.js' );
var src  = fs.readFileSync( file, 'utf8' );
// Re-expose the IIFE internals for the probe (the control exports nothing).
src = src.replace(
	/\}\s*\)\(\);\s*$/,
	'global.__X={removeSlotFrom:removeSlotFrom,serializeSlot:serializeSlot,' +
	'parseSlot:parseSlot,seedSlot:seedSlot,slotCount:slotCount};})();'
);
eval( src );
var X = global.__X;

function show( s ) {
	var r = [];
	for ( var i = 1; i <= 8; i++ ) { if ( s[ String( i ) ] ) { r.push( i + ':' + s[ String( i ) ] ); } }
	return r.join( ' | ' ) || '(none)';
}

// [ name, beforeState, removeOrdinal, expectedAfter ]
var CASES = [
	[ 'plain compaction — remove middle of 4, no inheritance anywhere',
	  { '1': 'key(staff_name)', '2': 'src(refs,office);key(city)',
	    '3': 'src(refs,region);key(name)', '4': 'src(post,9999);use(title)' }, 2,
	  '1:key(staff_name) | 2:src(refs,region);key(name) | 3:src(post,9999);use(title)' ],

	[ 'MATERIALIZE src — successor inherited the removed slot\'s source',
	  { '1': 'key(staff_name)', '2': 'src(refs,office);key(city)', '3': 'src(same);key(region_name)' }, 2,
	  '1:key(staff_name) | 2:src(refs,office);key(region_name)' ],

	[ 'MATERIALIZE read — successor inherited the removed slot\'s read',
	  { '1': 'key(staff_name)', '2': 'src(refs,office);key(city)', '3': 'src(refs,region);use(same)' }, 2,
	  '1:key(staff_name) | 2:src(refs,region);key(city)' ],

	[ 'PROMOTION — removing slot 1 promotes an all-inherit slot 2; `same` is illegal at position 1',
	  { '1': 'src(refs,office);key(city)', '2': 'src(same);use(same)' }, 1,
	  '1:src(refs,office);key(city)' ],

	[ 'no successor — removing the last slot needs no materialization',
	  { '1': 'key(staff_name)', '2': 'src(refs,office);key(city)', '3': 'src(refs,region);key(name)' }, 3,
	  '1:key(staff_name) | 2:src(refs,office);key(city)' ],

	[ 'multi-hop materialization — the WHOLE chain carries, not just hop 1',
	  { '1': 'key(a)', '2': 'src(refs,office+refs,region);key(city)', '3': 'src(same);key(zip)' }, 2,
	  '1:key(a) | 2:src(refs,office+refs,region);key(zip)' ],

	[ 'chained inherit — only the IMMEDIATE successor referenced the removed slot',
	  { '1': 'key(a)', '2': 'src(refs,office);key(b)', '3': 'src(same);key(c)', '4': 'src(same);key(d)' }, 2,
	  '1:key(a) | 2:src(refs,office);key(c) | 3:src(same);key(d)' ],

	// Remove is live on every slot once 2+ are visible (2026-07-31), so these two
	// are newly REACHABLE — previously the button was gated off at the floor.
	// The floor still holds: one value survives, slot 2 renders empty.
	[ 'AT THE FLOOR — remove slot 1 of 2; survivor compacts into position 1',
	  { '1': 'key(a)', '2': 'src(refs,office);key(b)' }, 1,
	  '1:src(refs,office);key(b)' ],

	[ 'AT THE FLOOR — remove slot 2 of 2; slot 1 untouched',
	  { '1': 'key(a)', '2': 'src(refs,office);key(b)' }, 2,
	  '1:key(a)' ],
];

var fail = 0;
CASES.forEach( function ( c ) {
	var name = c[ 0 ], before = c[ 1 ], n = c[ 2 ], want = c[ 3 ];
	var count = X.slotCount( before );
	var got   = show( X.removeSlotFrom( n, before, count ) );
	var ok    = got === want;
	if ( ! ok ) { fail++; }
	console.log( ( ok ? 'PASS  ' : 'FAIL  ' ) + name );
	console.log( '        before: ' + show( before ) + '   (remove slot ' + n + ')' );
	if ( ! ok ) {
		console.log( '        want  : ' + want );
		console.log( '        got   : ' + got );
	} else {
		console.log( '        after : ' + got );
	}
} );

// The seed is the add-button's entire payload — pin its shape.
var seed = X.serializeSlot( X.seedSlot() );
var seedOk = 'src(same);use(same)' === seed;
if ( ! seedOk ) { fail++; }
console.log( ( seedOk ? 'PASS  ' : 'FAIL  ' ) + 'seed shape = src(same);use(same)   got: ' + seed );

// An UNCONFIGURED floor-visible slot 2 must mount in the same all-inherit state
// the Add-slot button writes — NOT `{chain:[], read:null}`, which displayed
// "Default (intrinsic analog)" and is the silent-reset shape the renderer flags
// (trial 2026-07-31, PFf2).
//
// Reimplements the control's position-aware fallback (it lives inside the
// mounted component and cannot be called headlessly), so this asserts the RULE:
// slot 1 unconfigured = empty/current, slot >=2 unconfigured = the seed.
function mountDefault( ordinal ) {
	return X.serializeSlot(
		ordinal >= 2 ? X.seedSlot() : { chain: [], read: null }
	) || '(empty — current, no read)';
}
var MOUNT_CASES = [
	[ 1, '(empty — current, no read)' ],
	[ 2, 'src(same);use(same)' ],
	[ 3, 'src(same);use(same)' ],
];
MOUNT_CASES.forEach( function ( c ) {
	var got = mountDefault( c[ 0 ] );
	var ok  = got === c[ 1 ];
	if ( ! ok ) { fail++; }
	console.log( ( ok ? 'PASS  ' : 'FAIL  ' ) +
		'unconfigured slot ' + c[ 0 ] + ' mounts as: ' + got );
	if ( ! ok ) { console.log( '        want  : ' + c[ 1 ] ); }
} );

// ── HOP removal (writeChainAt's delete branch) ──────────────────────────────
// Reimplemented here rather than imported: writeChainAt closes over the mounted
// control's props, so it cannot be called headlessly. This mirrors ITS RULES —
// if that function's fallback logic changes, update both (the probe asserts the
// RULE, the control owns the implementation).
//
// Worth pinning because the inline "Remove step" button made the 1-step case
// REACHABLE for the first time: the old enum row was suppressed at that length,
// so emptying a chain by hop removal could not previously happen at all.
function removeHop( chainStr, idx, ordinal ) {
	var parsed = X.parseSlot( 'src(' + chainStr + ')' );
	var next   = parsed.chain.slice();
	next.splice( idx, 1 );
	if ( ordinal >= 2 && ! next.length ) { next = [ { slug: 'same', arg: null, limit: null } ]; }
	return X.serializeSlot( { chain: next, read: null } ) || '(empty chain — slot 1 falls to current)';
}

var HOP_CASES = [
	[ 'remove hop 1 of 2 keeps hop 2', 'refs,office+refs,region', 0, 1, 'src(refs,region)' ],
	[ 'remove hop 2 of 2 keeps hop 1', 'refs,office+refs,region', 1, 1, 'src(refs,office)' ],
	[ 'remove middle hop of 3',        'post,9999+refs,a+refs,b', 1, 1, 'src(post,9999+refs,b)' ],
	[ 'empty a 1-step chain on slot 1 — legitimately unset (current)',
	  'refs,office', 0, 1, '(empty chain — slot 1 falls to current)' ],
	[ 'empty a 1-step chain on slot >=2 — MUST fall back to explicit inherit, never absence',
	  'refs,office', 0, 2, 'src(same)' ],
];
HOP_CASES.forEach( function ( c ) {
	var got = removeHop( c[ 1 ], c[ 2 ], c[ 3 ] );
	var ok  = got === c[ 4 ];
	if ( ! ok ) { fail++; }
	console.log( ( ok ? 'PASS  ' : 'FAIL  ' ) + 'hop: ' + c[ 0 ] );
	console.log( '        ' + c[ 1 ] + '  remove idx ' + c[ 2 ] + ' (slot ' + c[ 3 ] + ')  ->  ' + got );
	if ( ! ok ) { console.log( '        want  : ' + c[ 4 ] ); }
} );

var total = CASES.length + 1 + HOP_CASES.length + MOUNT_CASES.length;
console.log( '\n' + ( total - fail ) + '/' + total + ' passed' );
process.exit( fail ? 1 : 0 );
