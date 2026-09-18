/**
 * The seam where the FOLD CONTROL hands the FIELD PICKER what the chain resolved to.
 * Run: `node tools/test/slot-fold-picker-seam-test.js` (exits non-zero on failure,
 * house convention).
 *
 * WHY IT EXISTS: the two harnesses either side of this seam both pass while it is
 * broken, and did. `slot-fold-repeater-test.js` loads `slot-fold-control.js` and stubs
 * the picker (`FIELD_COMBO_STUB = {}`), so it proves a TOKEN is handed over and nothing
 * about what is done with it. `field-combo-control-test.js` loads
 * `field-combo-control.js` and hand-builds the context, so it proves a picker GIVEN that
 * token narrows and nothing about who gives it. Neither loads the other's file. Nothing
 * in the suite rendered the real fold control against the real picker, which is what an
 * author actually has on screen — and the fold's own read-picker context
 * (`fieldContext()` in `slot-fold-control.js`) had NO automated coverage of any kind:
 * one call site, reached by no test.
 *
 * WHAT IT THEREFORE ASKS is narrow on purpose: does the chain's resolved tail REACH the
 * mounted picker, spelled the way the picker reads it. It is not a second copy of the
 * narrowing rule — `field-combo-control-test.js` §F13/§F16 own that, against a much
 * larger fixture — so the envelope here is the smallest one under which a narrowing is
 * observable at all: one post type that survives, one that does not, and one record of
 * each other kind.
 *
 * TWO SEAMS, NOT ONE, because they are different functions that happened to share a
 * retired reason. `predecessorContext()` feeds a STEP's own argument picker (what the
 * step before it resolved to); `fieldContext()` feeds the SLOT'S READ picker (what the
 * whole chain resolved to). Both withheld a `refs` tail until 1.21.0.
 *
 * NO NEW EXPORTS, per #94. `SlotFoldControl` is private to its IIFE and stays that way;
 * it is reached through the `generateblocks.editor.tagSpecificControls` filter it
 * registers, which is the route the editor itself takes. The picker inside it is the
 * REAL `window.bwsFieldComboControl`, mounted by the control's own code.
 *
 * THE HOOK STUBS ARE INSTALLED BEFORE EITHER FILE LOADS. `field-combo-control.js`
 * captures `useState` / `useMemo` / `useEffect` into locals at IIFE time, so a later
 * replacement would silently exercise the first render only — and since the envelope
 * arrives through a resolved promise, that means an empty list and a suite passing on
 * nothing. `slot-fold-control.js` captures no hooks; it is a pure render.
 *
 * @package BWS_Dynamic_Tags
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const root = path.resolve( __dirname, '../..' );

/* -------------------------------------------------------------------------
 * A minimal hook runtime (the picker's; the fold control uses none)
 * ---------------------------------------------------------------------- */

const hooks = { cells: [], idx: 0, effects: [], dirty: true };

function useState( initial ) {
	const i = hooks.idx++;
	if ( ! ( i in hooks.cells ) ) {
		hooks.cells[ i ] = typeof initial === 'function' ? initial() : initial;
	}
	return [ hooks.cells[ i ], function ( next ) {
		const value = typeof next === 'function' ? next( hooks.cells[ i ] ) : next;
		if ( value !== hooks.cells[ i ] ) {
			hooks.cells[ i ] = value;
			hooks.dirty = true;
		}
	} ];
}

// Called through, never cached — recomputing every pass exercises the same code and
// cannot go stale against a dependency list this harness would otherwise have to model.
function useMemo( fn ) { return fn(); }
function useEffect( fn ) { hooks.effects.push( fn ); }

/** Render a function component to settlement, draining the envelope's microtasks. */
async function render( Component, props ) {
	hooks.cells = [];
	hooks.dirty = true;
	let tree = null;
	let guard = 0;
	while ( hooks.dirty && guard++ < 12 ) {
		hooks.dirty = false;
		hooks.idx = 0;
		hooks.effects = [];
		tree = Component( props );
		hooks.effects.forEach( function ( fn ) { fn(); } );
		await Promise.resolve();
		await Promise.resolve();
	}
	if ( guard >= 12 ) {
		console.error( 'render did not settle in 12 passes — a setter is thrashing' );
		process.exit( 2 );
	}
	return tree;
}

/* -------------------------------------------------------------------------
 * The wp globals both files declare
 * ---------------------------------------------------------------------- */

function createElement( type, props ) {
	return { type: type, props: props || {}, children: Array.prototype.slice.call( arguments, 2 ) };
}

const registeredFilters = {};

global.window = {};
global.wp = {
	hooks: {
		// Keyed by NAMESPACE, not by hook name: both files register on
		// `tagSpecificControls` and this harness needs the fold one specifically.
		addFilter: function ( hook, ns, fn ) { registeredFilters[ ns ] = fn; },
	},
	element: {
		createElement: createElement,
		Fragment: 'Fragment',
		useState: useState,
		useEffect: useEffect,
		useMemo: useMemo,
	},
	components: {
		SelectControl: 'SelectControl',
		TextControl: 'TextControl',
		Button: 'Button',
		ComboboxControl: 'ComboboxControl',
		Flex: 'Flex',
		FlexItem: 'FlexItem',
	},
	// The picker's entity-lookup round trip. NOTHING here selects a specific entity —
	// a chain carrying a step has no root argument to resolve — so an unconditional
	// null row is the right answer and a request arriving at all would be the defect.
	apiFetch: function () { return Promise.resolve( { row: null } ); },
	i18n: {
		__: function ( s ) { return s; },
		sprintf: function ( f, v ) { return String( f ).replace( '%s', v ); },
	},
};
global.window.wp = global.wp;

/* -------------------------------------------------------------------------
 * The fixture envelope — the smallest one a narrowing is visible in
 *
 *   `main_line`  post, scoped `staff`   — survives a `staff` narrowing
 *   `dept_lead`  post, scoped `staff`   — the relationship stepped THROUGH, allowing `staff`
 *   `headline`   post, scoped `page`    — dropped by a `staff` narrowing, kept by kind alone
 *   `blurb`      TERM                   — dropped by the kind gate, either way
 *   `org_name`   SITE, unscoped         — the scopeless record of another kind (ticket 01's case)
 * ---------------------------------------------------------------------- */

global.window.bwsFieldEnvelope = {
	post: [
		{
			group_title: 'Staff Contact',
			scope: [ 'staff' ],
			fields: [
				{ name: 'main_line', label: 'Main Line', type: 'text' },
				{ name: 'dept_lead', label: 'Dept Lead', type: 'post_object', ref_types: [ 'staff' ] },
				{ name: 'any_ref', label: 'Any Ref', type: 'post_object', ref_types: [] },
			],
		},
		{
			group_title: 'Page Builder',
			scope: [ 'page' ],
			fields: [
				{ name: 'headline', label: 'Headline', type: 'text' },
			],
		},
	],
	term: [
		{
			group_title: 'Department Details',
			scope: [ 'department' ],
			fields: [ { name: 'blurb', label: 'Blurb', type: 'textarea' } ],
		},
	],
	site: [
		{
			group_title: 'Organization',
			fields: [ { name: 'org_name', label: 'Org Name', type: 'text' } ],
		},
	],
};

// Inlined from `bws_registered_root_rows()` / `bws_fold_wire_vocabulary()` on a real
// editor page, as the picker's own harness inlines them.
global.window.bwsRootArgKinds = { term: 'term', post: 'post' };
global.window.bwsChainKinds = {
	steps: { refs: 'post', terms: 'term', rows: 'meta_row' },
	roots: { site: 'site', term: 'term', post: 'post' },
};

/* -------------------------------------------------------------------------
 * Load BOTH shipped files, in the plugin's own enqueue order
 * ---------------------------------------------------------------------- */

function load( relative ) {
	const file = path.join( root, relative );
	vm.runInThisContext( fs.readFileSync( file, 'utf8' ), { filename: file } );
}

load( 'assets/js/option-group.js' );
load( 'assets/js/serialization-order-normalizer.js' );
load( 'assets/js/slot-fold-grammar.js' );
load( 'assets/js/slot-fold-migrate.js' );
load( 'assets/js/slot-fold-control.js' );
// The picker last, as the enqueue does. The fold control reads
// `window.bwsFieldComboControl` at RENDER time, so this order is the real one rather
// than a load-order workaround — and if it ever became one, the fold would paint its
// plain-text fallback and every row below would fail rather than pass quietly.
load( 'assets/js/field-combo-control.js' );

const foldFilter = registeredFilters[ 'bws/slot-fold-control' ];
const FieldCombo = global.window.bwsFieldComboControl;
if ( ! foldFilter ) {
	console.error( 'the slot-fold control did not register its tagSpecificControls filter' );
	process.exit( 2 );
}
if ( ! FieldCombo ) {
	console.error( 'window.bwsFieldComboControl missing — the picker did not load' );
	process.exit( 2 );
}

/* -------------------------------------------------------------------------
 * The container config, shaped as bws_build_fold_slot_options() ships it
 * ---------------------------------------------------------------------- */

const SLOT_KEY = 'A';

// KEY-ONLY selecting container (the `try_email` / `try_phone` shape): no per-slot `use`
// enum, so the read picker is unconditional and the subject is the seam rather than a
// read-axis enum. `{{join}}`'s combining shape reaches the same `fieldContext()`.
const FOLD = {
	container: 'try',
	combining: false,
	perSlotUse: false,
	min: 2,
	max: 5,
	noun: 'attempt',
	srcRows: [
		{ value: 'current', label: 'Current' },
		{ value: 'refs', label: 'In Reference/Relational Field' },
	],
	srcRowsWithSame: [
		{ value: 'same', label: 'Same as Previous Source' },
		{ value: 'current', label: 'Current' },
	],
	steps: {
		refs: { label: 'In Reference/Relational Field', arg: 'field', accepts: [ 'post', 'term', 'user', 'meta_row', 'site' ], produces: 'post' },
		terms: { label: 'In Taxonomy Term', arg: 'slug', accepts: [ 'post' ], produces: 'term' },
		rows: { label: 'In Repeater Rows', arg: 'field', accepts: [ 'post', 'term', 'user', 'meta_row', 'site' ], produces: 'meta_row' },
	},
	offer: [ 'terms', 'refs', 'rows' ],
	roots: { site: 'site' },
	defaultRoot: 'current',
	refOption: { label: 'Relationship Field Key', placeholder: 'related_posts' },
	rowsOption: { label: 'Repeater Field Key', placeholder: 'team_members', typeDefault: 'repeater' },
	keyOption: { label: 'Meta/Option Field', dynamicLabel: true },
	limitOption: { label: 'Limit results', placeholder: '0 (all)', help: 'Maximum number of results.' },
	taxonomies: [ { value: 'department', label: 'Department' } ],
};

/** Mount the fold control for one slot, through its own registered filter. */
function mountSlot( slotValue ) {
	const element = foldFilter(
		{ key: SLOT_KEY },
		{ [ SLOT_KEY ]: { type: 'bws-slot-fold', label: 'Attempt', fold: FOLD } },
		{ state: { [ SLOT_KEY ]: slotValue }, setState: function () {} }
	);
	if ( ! element || 'function' !== typeof element.type ) {
		console.error( 'the fold filter did not return a mounted control for ' + slotValue );
		process.exit( 2 );
	}
	// SlotFoldControl holds no state of its own, so one call is the whole render.
	return element.type( element.props );
}

/* -------------------------------------------------------------------------
 * Reading the rendered trees
 * ---------------------------------------------------------------------- */

function walk( node, out ) {
	out = out || [];
	if ( ! node || typeof node !== 'object' ) { return out; }
	if ( Array.isArray( node ) ) {
		node.forEach( function ( n ) { walk( n, out ); } );
		return out;
	}
	if ( node.type ) { out.push( node ); }
	( node.children || [] ).forEach( function ( c ) { walk( c, out ); } );
	return out;
}

/**
 * Every REAL field picker the fold control mounted, in render order.
 *
 * Identity is the component itself, not a type string: the point of this harness is that
 * the thing mounted IS `window.bwsFieldComboControl` and not a stand-in, so matching on
 * anything looser would pass against the stub the other harness installs.
 */
function pickersIn( tree ) {
	return walk( tree ).filter( function ( n ) { return n.type === FieldCombo; } );
}

/**
 * The SLOT'S READ picker — always last, after one argument picker per arg-taking step.
 *
 * Read by position rather than by a marker prop, because position is what the control
 * itself guarantees: the chain editor emits its steps in order and the read box follows
 * the whole box. A marker would be a seam added for the test (#94). `S2.0` pins the
 * count this indexing rests on, so a step gaining or losing a picker fails by name there
 * rather than silently moving what every row below reads.
 */
function readPickerIn( tree ) {
	const all = pickersIn( tree );
	return all[ all.length - 1 ];
}

/** The option labels a mounted picker actually shows, rendering it for real. */
async function listOf( picker ) {
	const tree = await render( FieldCombo, picker.props );
	const combo = walk( tree ).filter( function ( n ) { return n.type === 'ComboboxControl'; } )[ 0 ];
	return ( combo.props.options || [] ).map( function ( o ) { return o.label; } );
}

/** The Location filter's own options — the narrowed POOL, before the kind preset hides anything. */
async function poolRootsOf( picker ) {
	const tree = await render( FieldCombo, picker.props );
	const select = walk( tree ).filter( function ( n ) { return n.type === 'SelectControl'; } )[ 0 ];
	const roots = {};
	( select.props.options || [] ).forEach( function ( o ) {
		roots[ String( o.value ).split( ' › ' )[ 0 ] ] = 1;
	} );
	return Object.keys( roots ).sort();
}

/* -------------------------------------------------------------------------
 * Assertions
 * ---------------------------------------------------------------------- */

let total = 0;
let fail = 0;

function check( name, got, want ) {
	total++;
	const a = JSON.stringify( got );
	const b = JSON.stringify( want );
	if ( a === b ) {
		console.log( 'PASS  ' + name );
		return;
	}
	fail++;
	console.log( 'FAIL  ' + name );
	console.log( '        want  : ' + b );
	console.log( '        got   : ' + a );
}

async function main() {
	/* =====================================================================
	 * §S1 — the SLOT'S READ picker, fed by `fieldContext()`
	 *
	 * The read applies to whatever the chain resolved to LAST, so the tail is what this
	 * picker is owed. `refs` was withheld here until 1.21.0 on the ground that the post
	 * a `refs` step lands on has an unknown type — true of the editor, and an answer to
	 * a question the picker was not asking, since `refs` produces `post` unconditionally
	 * and the FIELD names the types it reaches.
	 *
	 * This is the only automated coverage `fieldContext()` has.
	 * ================================================================== */

	const refsTree = mountSlot( 'src(refs,dept_lead);key(main_line)' );
	const readOnRefs = readPickerIn( refsTree );
	check(
		'S1.0 the slot mounts the REAL picker — one per arg-taking step, then one for the read',
		pickersIn( refsTree ).length,
		2
	);
	check(
		'S1.1 a `refs` tail reaches the read picker as a `src` token, spelled as the wire spells it',
		readOnRefs.props.context.state.src,
		'refs,dept_lead'
	);
	// The token arriving is not the property; the picker ACTING on it is. `dept_lead`
	// allows `staff`, so the `page`-scoped `headline` goes and the `staff`-scoped rows stay.
	check(
		'S1.2 ...and the list it shows is narrowed by that field\'s allowed post types',
		await listOf( readOnRefs ),
		[
			"Any Ref (Post Object, 'any_ref')",
			"Dept Lead (Post Object, 'dept_lead')",
			"Main Line (Text, 'main_line')",
		]
	);
	// The slot's OWN stored field has to survive the hand-off. A context that replaced
	// the caller's state rather than extending it would blank the author's field on mount.
	check(
		'S1.3 ...while the slot\'s own stored field key is carried, not replaced',
		readOnRefs.props.context.state.key,
		'main_line'
	);

	// An UNRESTRICTED relationship narrows no further by type, and still binds the kind:
	// `refs` lands on a post whatever it steps through. `headline` returns, `blurb` and
	// `org_name` do not.
	const readOnAny = readPickerIn( mountSlot( 'src(refs,any_ref);key(main_line)' ) );
	check(
		'S1.4 an UNRESTRICTED tail keeps every post type through the seam',
		await listOf( readOnAny ),
		[
			"Any Ref (Post Object, 'any_ref')",
			"Dept Lead (Post Object, 'dept_lead')",
			"Headline (Text, 'headline')",
			"Main Line (Text, 'main_line')",
		]
	);
	check(
		'S1.5 ...and still binds the POOL to kind `post` — no term or site root survives',
		await poolRootsOf( readOnAny ),
		[ 'Post fields', '__all_locations' ]
	);

	// The CONTRAST that makes the rows above readable as "the tail reached it": a chain
	// with no tail to speak of hands nothing over, and the whole envelope is offered.
	const readOnCurrent = readPickerIn( mountSlot( 'src(current);key(main_line)' ) );
	check(
		'S1.6 a `current` tail hands over nothing, and the pool keeps all three kinds',
		await poolRootsOf( readOnCurrent ),
		[ 'Post fields', 'Site fields', 'Term fields', '__all_locations' ]
	);

	// A `terms` tail resolves to a TERM, so the post-type narrowing must not reach it and
	// the kind preset answers instead. The contrast is with S1.2, not with S1.6: something
	// IS handed over here, and it is the other axis.
	const readOnTerms = readPickerIn( mountSlot( 'src(current;terms,department);key(blurb)' ) );
	check(
		'S1.7 a `terms` tail opens on TERM fields, untouched by the post-type axis',
		await listOf( readOnTerms ),
		[ "Blurb (Text Area, 'blurb')" ]
	);

	/* =====================================================================
	 * §S2 — a STEP's ARGUMENT picker, fed by `predecessorContext()`
	 *
	 * The entity a step's field is read off is whatever the chain resolved to just
	 * BEFORE it, so a step at position 1 is owed position 0's answer. The repeater
	 * harness pins the TOKEN leaving the fold control against a stubbed picker; what is
	 * added here is that the real picker receives it and narrows.
	 * ================================================================== */

	const twoStep = pickersIn( mountSlot( 'src(refs,dept_lead;refs,any_ref);key(main_line)' ) );
	check(
		'S2.0 a two-step chain mounts three real pickers — one per step argument, one for the read',
		twoStep.length,
		3
	);
	check(
		'S2.1 the SECOND step\'s argument picker is handed its predecessor, not the root',
		twoStep[ 1 ].props.context.state.src,
		'refs,dept_lead'
	);
	check(
		'S2.2 ...and narrows on it, so the step after a `staff` relationship picks from `staff` fields',
		await listOf( twoStep[ 1 ] ),
		[
			"Any Ref (Post Object, 'any_ref')",
			"Dept Lead (Post Object, 'dept_lead')",
			"Main Line (Text, 'main_line')",
		]
	);
	check(
		'S2.3 the FIRST step has no predecessor, so its own picker is handed nothing',
		twoStep[ 0 ].props.context.state.src,
		undefined
	);
	check(
		'S2.4 ...and is therefore unnarrowed — the pool keeps all three kinds',
		await poolRootsOf( twoStep[ 0 ] ),
		[ 'Post fields', 'Site fields', 'Term fields', '__all_locations' ]
	);
	// The READ picker of the same slot reads the TAIL, which is the second step and not
	// the first. One slot, two seams, two different answers — the row that would catch
	// either seam being wired to the other.
	check(
		'S2.5 the read picker of the same slot reads the TAIL, not the step the second picker read',
		twoStep[ 2 ].props.context.state.src,
		'refs,any_ref'
	);

	console.log( '' );
	if ( fail ) {
		console.log( 'SLOT FOLD PICKER SEAM TEST FAILED (' + fail + ' of ' + total + ')' );
		process.exit( 1 );
	}
	console.log( 'SLOT FOLD PICKER SEAM TEST PASSED (' + total + ' assertions)' );
}

main().catch( function ( e ) {
	console.error( e && e.stack ? e.stack : e );
	process.exit( 2 );
} );
