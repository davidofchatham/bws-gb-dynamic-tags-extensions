/**
 * Harness for the field-combo control's display layer — record building, the two
 * filters, option labelling, selection, and the auto-scope. Run:
 * `node tools/test/field-combo-control-test.js` (exits non-zero on failure, house
 * convention).
 *
 * WHY IT EXISTS: `assets/js/field-combo-control.js` is 841 lines and had NO automated
 * coverage at all, held by `field-selector-test-matrix.md` plus hand-eval. That is how its
 * row formatter came to be verified three separate times by extracting its two pure
 * functions with `node -e` — the same extraction, thrown away each time. This is that
 * extraction, kept.
 *
 * NO NEW EXPORTS, per #94: a control does not grow a test-shaped seam because a test wants
 * one. The pure display functions are private to the file's IIFE and stay that way. They are
 * reached through the two things the file ALREADY exposes — `window.bwsFieldComboControl`
 * (the component, exported for composition) and the `tagSpecificControls` filter it
 * registers — by rendering the component against stubbed `wp.element` hooks and reading the
 * tree it returns. That is a stronger subject than the private functions anyway: it is what
 * an author actually sees.
 *
 * THE HOOK STUBS ARE INSTALLED BEFORE THE FILE LOADS, and that is not optional. The control
 * captures `useState` / `useMemo` / `useEffect` into locals at IIFE time, so replacing them
 * on `wp.element` afterwards would change nothing and every case below would silently
 * exercise the first render only.
 *
 * ASSERTIONS ARE STRUCTURAL, NOT SCALAR. Every case reads the option LISTS out of the
 * rendered tree — labels in order, filter option sets, which option is selected — rather
 * than a count or a joined string. That is the FW-71 / #104 lesson: four defects shipped
 * under a green suite that asserted reductions of the shape instead of the shape.
 *
 * MUTATION-CHECKED 2026-08-28, because a display-layer harness that asserts the wrong shapes
 * passes forever and nobody looks again. Ten rules were broken one at a time in the shipped
 * file and every one failed here by name: always repeating the key in a row (F1.1, F1.2,
 * F2.2), an equality location filter instead of a prefix one (F5.1), serializing the merge-key
 * wrapper instead of the bare key (F11.1), dropping the underscore demotion (F1.1, F1.2),
 * dropping the label from the merge identity (F1.1, F1.4, F6.2), collapsing the auto-scope to
 * an empty list (F8.4), case-folding the custom-key suppression (F7.3), auto-selecting an
 * ambiguous key (F6.2), applying an undiscovered typeDefault (F9.4), and rendering the filters
 * while auto-scoped (F8.1, F8.5).
 *
 * WHAT THIS DOES NOT COVER, stated so a passing run is not read as full coverage of the
 * control: the PHP field-discovery transforms (`field-discovery-test.php` owns those), the
 * REST round trip, the ComboboxControl's own rendering and keyboard behaviour, and anything
 * needing a real DOM. Those stay manual and stay held by
 * `tools/test/field-selector-test-matrix.md`.
 *
 * @package BWS_Dynamic_Tags
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const root = path.resolve( __dirname, '../..' );

/* -------------------------------------------------------------------------
 * A minimal hook runtime
 *
 * Enough React for a function component that holds state and runs one effect: state cells
 * keyed by call order, effects collected and run after each pass, and the pass repeated
 * while any setter changed a cell. The envelope arrives through a resolved promise, so a
 * microtask drain sits between passes — without it every render would see `envelope: null`
 * and the whole file below would test an empty list.
 * ---------------------------------------------------------------------- */

const hooks = {
	cells: [],
	idx: 0,
	effects: [],
	dirty: true,
};

function useState( initial ) {
	const i = hooks.idx++;
	if ( ! ( i in hooks.cells ) ) {
		hooks.cells[ i ] = typeof initial === 'function' ? initial() : initial;
	}
	return [
		hooks.cells[ i ],
		function ( next ) {
			const value = typeof next === 'function' ? next( hooks.cells[ i ] ) : next;
			if ( value !== hooks.cells[ i ] ) {
				hooks.cells[ i ] = value;
				hooks.dirty = true;
			}
		},
	];
}

// Called through, never cached. Memoization is an optimization; recomputing every pass
// exercises the same code and cannot go stale against a dependency list this harness would
// otherwise have to model.
function useMemo( fn ) {
	return fn();
}

function useEffect( fn ) {
	hooks.effects.push( fn );
}

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
		// The envelope promise resolves on the microtask queue.
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
 * The wp globals the control declares
 * ---------------------------------------------------------------------- */

function createElement( type, props ) {
	return {
		type: type,
		props: props || {},
		children: Array.prototype.slice.call( arguments, 2 ),
	};
}

const registeredFilters = {};

global.window = {};
global.wp = {
	hooks: {
		addFilter: function ( hook, ns, fn ) {
			registeredFilters[ hook ] = fn;
		},
	},
	element: {
		createElement: createElement,
		Fragment: 'Fragment',
		useState: useState,
		useEffect: useEffect,
		useMemo: useMemo,
	},
	components: {
		ComboboxControl: 'ComboboxControl',
		SelectControl: 'SelectControl',
		Flex: 'Flex',
		FlexItem: 'FlexItem',
	},
	// Present because the control's guard requires it. Never called: the inline envelope
	// below is the path a real editor page takes too (wp_add_inline_script), and the REST
	// round trip is out of this harness's scope.
	apiFetch: function () {
		throw new Error( 'apiFetch called — the inline envelope should have short-circuited it' );
	},
	i18n: {
		__: function ( s ) { return s; },
	},
};
global.window.wp = global.wp;

/* -------------------------------------------------------------------------
 * The fixture envelope
 *
 * Shaped as the REST endpoint's, with each row chosen for a rule below rather than for
 * plausibility alone:
 *   `event_date`        — label equals key, so the row must NOT repeat the key in brackets
 *   `venue_city`        — ordinary labelled field
 *   `name` ×2           — same key, DIFFERENT labels: two rows, and an AMBIGUOUS selection
 *   `email` post + site — same key across KINDS: two rows (a kind is part of the identity)
 *   `staff_list`        — a repeater, so it decorates its own location path
 *   `role`              — a repeater sub-field: row context, and the auto-scope target
 *   `_gb_internal`      — underscore-prefixed: DEMOTED to the bottom, never hidden
 *   `photo`             — reached through two homes: one merged row listing both paths
 * ---------------------------------------------------------------------- */

global.window.bwsFieldEnvelope = {
	post: [
		{
			group_title: 'Event Details',
			fields: [
				{ name: 'event_date', label: 'event_date', type: 'date_picker' },
				{ name: 'venue_city', label: 'City', type: 'text' },
				{ name: 'name', label: 'Name', type: 'text' },
				{ name: '_gb_internal', label: '_gb_internal', type: 'text' },
				{ name: 'staff_list', label: 'Staff List', type: 'repeater' },
				{ name: 'role', label: 'Role', type: 'text', parent_path: 'Staff List', context_hint: 'row', repeater_key: 'staff_list' },
				{ name: 'photo', label: 'Photo', type: 'image' },
			],
		},
		{
			group_title: 'Feature Block',
			fields: [
				{ name: 'name', label: 'Feature Name', type: 'text' },
				{ name: 'photo', label: 'Photo', type: 'image' },
			],
		},
	],
	term: [
		{
			group_title: 'Taxonomy Extras',
			fields: [
				{ name: 'blurb', label: 'Blurb', type: 'textarea' },
			],
		},
	],
	site: [
		{
			group_title: 'Site Options',
			fields: [
				{ name: 'email', label: 'Email', type: 'email' },
			],
		},
	],
};

// post `email` too, so the same key exists under two KINDS.
global.window.bwsFieldEnvelope.post[ 0 ].fields.push( { name: 'email', label: 'Email', type: 'email' } );

/* -------------------------------------------------------------------------
 * Load the shipped file
 * ---------------------------------------------------------------------- */

const file = path.join( root, 'assets/js/field-combo-control.js' );
vm.runInThisContext( fs.readFileSync( file, 'utf8' ), { filename: file } );

const FieldComboControl = global.window.bwsFieldComboControl;
const fieldComboFilter = registeredFilters[ 'generateblocks.editor.tagSpecificControls' ];

if ( ! FieldComboControl ) {
	console.error( 'window.bwsFieldComboControl missing — the control did not load' );
	process.exit( 2 );
}
if ( ! fieldComboFilter ) {
	console.error( 'the tagSpecificControls filter was never registered' );
	process.exit( 2 );
}

/* -------------------------------------------------------------------------
 * Reading the rendered tree
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

function findAll( tree, type ) {
	return walk( tree ).filter( function ( n ) { return n.type === type; } );
}

function combo( tree ) {
	const found = findAll( tree, 'ComboboxControl' );
	return found.length ? found[ 0 ].props : null;
}

/** The two filter SelectControls, in render order: [location, type]. */
function selects( tree ) {
	return findAll( tree, 'SelectControl' ).map( function ( n ) { return n.props; } );
}

function labels( optionList ) {
	return ( optionList || [] ).map( function ( o ) { return o.label; } );
}

function ctx( state, onSet ) {
	return {
		state: state || {},
		setState: onSet || function () {},
	};
}

/* -------------------------------------------------------------------------
 * Assertions
 * ---------------------------------------------------------------------- */

let fail = 0;
let total = 0;

function check( label, got, want, extra ) {
	total++;
	const g = JSON.stringify( got );
	const w = JSON.stringify( want );
	const ok = g === w;
	if ( ! ok ) { fail++; }
	console.log( ( ok ? 'PASS  ' : 'FAIL  ' ) + label );
	if ( extra ) { console.log( '        ' + extra ); }
	if ( ! ok ) {
		console.log( '        want  : ' + w );
		console.log( '        got   : ' + g );
	}
}

async function main() {

	/* =====================================================================
	 * §F1 — the record list: merge identity, and the order it comes out in
	 *
	 * Merge identity is (kind, key, label). Same key + same label within a kind is ONE
	 * field surfaced in two homes and collapses to one row; same key + a different label
	 * is two fields; and the same key under two KINDS is two rows, because a post `email`
	 * and a site `email` are read through different paths.
	 * ================================================================== */

	const base = await render( FieldComboControl, {
		optionKey: 'key',
		label: 'Field',
		context: ctx( {} ),
	} );

	const baseLabels = labels( combo( base ).options );

	check(
		'F1.1 the full option list, in order — flat alphabetical by label, underscore keys demoted',
		baseLabels,
		[
			'Blurb (Text Area, \'blurb\')',
			'City (Text, \'venue_city\')',
			'Email (Email, \'email\')',
			'Email (Email, \'email\')',
			'event_date (Date)',
			'Feature Name (Text, \'name\')',
			'Name (Text, \'name\')',
			'Photo (Image, \'photo\')',
			'Role (Text, \'role\')',
			'Staff List (Repeater, \'staff_list\')',
			'_gb_internal (Text)',
		]
	);

	// The demotion is the property, not the position: an underscore key sorts into a
	// TRAILING block rather than being hidden, so it stays selectable.
	check(
		'F1.2 the underscore-prefixed key is LAST, not absent — demoted, never hidden',
		baseLabels[ baseLabels.length - 1 ],
		'_gb_internal (Text)'
	);

	// `photo` appears in both post groups with the same label: one row, two homes.
	check(
		'F1.3 one key + one label in two homes collapses to a SINGLE row',
		baseLabels.filter( function ( l ) { return l.indexOf( 'Photo' ) === 0; } ).length,
		1
	);

	// `name` is "Name" in one group and "Feature Name" in another: two fields, two rows.
	check(
		'F1.4 one key + two labels stays TWO rows — they are different fields',
		baseLabels.filter( function ( l ) { return l.indexOf( '\'name\'' ) !== -1; } ),
		[ 'Feature Name (Text, \'name\')', 'Name (Text, \'name\')' ]
	);

	// Post `email` and site `email` share a key AND a label, and are still two rows: the
	// kind is part of the merge identity because the two are read through different paths.
	check(
		'F1.5 one key + one label under two KINDS stays two rows',
		baseLabels.filter( function ( l ) { return l === "Email (Email, 'email')"; } ).length,
		2
	);

	/* =====================================================================
	 * §F2 — how a row reads
	 *
	 * The bracket group carries what the row has not already said, type then key. This is
	 * the formatter that was hand-extracted three times.
	 * ================================================================== */

	check(
		'F2.1 a labelled field shows type and quoted key',
		baseLabels.filter( function ( l ) { return l.indexOf( 'City' ) === 0; } ),
		[ 'City (Text, \'venue_city\')' ]
	);
	check(
		'F2.2 a field whose label IS its key does not repeat the key in brackets',
		baseLabels.filter( function ( l ) { return l.indexOf( 'event_date' ) === 0; } ),
		[ 'event_date (Date)' ]
	);
	check(
		'F2.3 the type annotation is derived and title-cased, not carried in the label text',
		baseLabels.filter( function ( l ) { return l.indexOf( 'Staff List' ) === 0; } ),
		[ "Staff List (Repeater, 'staff_list')" ]
	);

	/* =====================================================================
	 * §F3 — the Location filter's options
	 *
	 * Every path PREFIX present across the records: kind roots, then root › group, then
	 * deeper. A segment naming a container FIELD is decorated with what kind of container
	 * it is, taken from that field's own row rather than parsed out of the breadcrumb.
	 * ================================================================== */

	const baseSelects = selects( base );

	check(
		'F3.1 the location options are the full prefix set, All first',
		labels( baseSelects[ 0 ].options ),
		[
			'All detected fields',
			'Post fields',
			'Post fields › Event Details',
			'Post fields › Event Details › Staff List (repeater)',
			'Post fields › Feature Block',
			'Site fields',
			'Site fields › Site Options',
			'Term fields',
			'Term fields › Taxonomy Extras',
		]
	);
	check(
		'F3.2 the container hint decorates the LABEL only — the value stays the raw path',
		baseSelects[ 0 ].options.filter( function ( o ) { return o.label.indexOf( '(repeater)' ) !== -1; } )
			.map( function ( o ) { return o.value; } ),
		[ 'Post fields › Event Details › Staff List' ]
	);
	check( 'F3.3 no location override is active, so the filter reads All', baseSelects[ 0 ].value, '__all_locations' );

	/* =====================================================================
	 * §F4 — the Field-type filter's options
	 * ================================================================== */

	check(
		'F4.1 All and Loop fields lead, then every discovered type, sorted by LABEL',
		labels( baseSelects[ 1 ].options ),
		[ 'All field types', 'Loop fields', 'Date', 'Email', 'Image', 'Repeater', 'Text', 'Text Area' ]
	);

	/* =====================================================================
	 * §F5 — what the filters do to the list
	 *
	 * Location is a PREFIX match, so picking a group keeps the fields nested below it.
	 * Type is exact, except Loop fields, which asks "is this usable in a loop" rather
	 * than "is this loop-only".
	 * ================================================================== */

	// DRIVEN THROUGH THE FILTER'S OWN onChange, the way an author drives it — not by
	// reaching into the component's state, which would be asserting against a shape the
	// harness invented. `render()` cannot be used here because it clears the state cells on
	// entry, so a pick made against one tree would be gone before the next: the pick has to
	// land mid-flight, between passes of one render loop.
	hooks.cells = [];
	let tree = null;
	hooks.dirty = true;
	let pass = 0;
	while ( hooks.dirty && pass++ < 12 ) {
		hooks.dirty = false;
		hooks.idx = 0;
		hooks.effects = [];
		tree = FieldComboControl( { optionKey: 'key', label: 'Field', context: ctx( {} ) } );
		hooks.effects.forEach( function ( fn ) { fn(); } );
		if ( ! hooks.dirty && selects( tree ).length ) {
			// Apply the author's pick once the envelope has landed, then let the loop
			// run one more pass so the tree reflects it.
			if ( selects( tree )[ 0 ].value === '__all_locations' ) {
				selects( tree )[ 0 ].onChange( 'Post fields › Event Details' );
			}
		}
		await Promise.resolve();
		await Promise.resolve();
	}

	check(
		'F5.1 a group location keeps the fields NESTED below it — the match is a prefix, not an equality',
		labels( combo( tree ).options ),
		[
			'City (Text, \'venue_city\')',
			'Email (Email, \'email\')',
			'event_date (Date)',
			'Name (Text, \'name\')',
			'Photo (Image, \'photo\')',
			'Role (Text, \'role\')',
			'Staff List (Repeater, \'staff_list\')',
			'_gb_internal (Text)',
		],
		'Role lives under Event Details › Staff List and must survive a filter on Event Details'
	);

	/* =====================================================================
	 * §F6 — which option shows as selected for a persisted bare key
	 *
	 * The serialized value is only the bare key, which can name more than one discovered
	 * field. Auto-selecting a labelled row in that case would assert the author picked
	 * that specific field, which they did not.
	 * ================================================================== */

	const exact = await render( FieldComboControl, {
		optionKey: 'key',
		label: 'Field',
		context: ctx( { key: 'venue_city' } ),
	} );
	const exactCombo = combo( exact );
	check(
		'F6.1 a key matching exactly ONE record selects that record\'s row',
		exactCombo.options.filter( function ( o ) { return o.value === exactCombo.value; } ).map( function ( o ) { return o.label; } ),
		[ 'City (Text, \'venue_city\')' ]
	);

	const ambiguous = await render( FieldComboControl, {
		optionKey: 'key',
		label: 'Field',
		context: ctx( { key: 'name' } ),
	} );
	const ambiguousCombo = combo( ambiguous );
	check(
		'F6.2 an AMBIGUOUS key selects a neutral passthrough showing the raw key — it asserts nothing',
		[ ambiguousCombo.value, ambiguousCombo.options[ 0 ].label ],
		[ 'name', 'name' ]
	);

	const unknown = await render( FieldComboControl, {
		optionKey: 'key',
		label: 'Field',
		context: ctx( { key: 'never_discovered' } ),
	} );
	const unknownCombo = combo( unknown );
	check(
		'F6.3 an UNKNOWN key is shown bare and stays selected — a persisted value is never dropped',
		[ unknownCombo.value, unknownCombo.options[ 0 ].label ],
		[ 'never_discovered', 'never_discovered' ]
	);

	/* =====================================================================
	 * §F7 — committing a key the discovery never found
	 *
	 * Typing an unmatched key offers to commit it bare. Suppressed only when the typed
	 * text ALREADY commits an existing option, and the match is case-SENSITIVE, because
	 * meta keys are: a case-fold would hide the escape hatch and leave the lower-cased
	 * variant uncommittable.
	 * ================================================================== */

	async function withTyped( typed, state ) {
		hooks.cells = [];
		hooks.dirty = true;
		let t = null;
		let p = 0;
		let typedYet = false;
		while ( hooks.dirty && p++ < 12 ) {
			hooks.dirty = false;
			hooks.idx = 0;
			hooks.effects = [];
			t = FieldComboControl( { optionKey: 'key', label: 'Field', context: ctx( state || {} ) } );
			hooks.effects.forEach( function ( fn ) { fn(); } );
			if ( ! hooks.dirty && ! typedYet && combo( t ) ) {
				typedYet = true;
				combo( t ).onFilterValueChange( typed );
			}
			await Promise.resolve();
			await Promise.resolve();
		}
		return t;
	}

	const typedNew = await withTyped( 'my_own_key' );
	check(
		'F7.1 an unmatched typed key offers a custom-key option, at the head of the list',
		combo( typedNew ).options[ 0 ],
		{ value: 'my_own_key', label: 'Use custom key: "my_own_key"' }
	);

	const typedExisting = await withTyped( 'venue_city' );
	check(
		'F7.2 typing a key that already commits an option offers no duplicate',
		combo( typedExisting ).options.filter( function ( o ) { return o.label.indexOf( 'Use custom key' ) === 0; } ),
		[]
	);

	const typedCase = await withTyped( 'Venue_City' );
	check(
		'F7.3 the suppression is case-SENSITIVE — meta keys are, so the variant stays committable',
		combo( typedCase ).options[ 0 ],
		{ value: 'Venue_City', label: 'Use custom key: "Venue_City"' }
	);

	/* =====================================================================
	 * §F8 — the repeater auto-scope
	 *
	 * `scope: 'row'` narrows the pool to one repeater's sub-fields and HIDES both filters:
	 * the scope IS the filter, so the selectors would be redundant and misleading.
	 * ================================================================== */

	const scoped = await render( FieldComboControl, {
		optionKey: 'A-key',
		label: 'Column field',
		scope: 'row',
		scopeKey: 'staff_list',
		context: ctx( {} ),
	} );

	check( 'F8.1 an auto-scoped control renders NEITHER filter', selects( scoped ).length, 0 );
	check(
		'F8.2 ...and the list is narrowed to that repeater\'s sub-fields',
		labels( combo( scoped ).options ),
		[ "Role (Text, 'role')" ]
	);

	// The scope handle is a PROP first. A control given no prop falls back to the sibling
	// `key` state, which is what the shipped flat `{N}-key` registrations rely on.
	const scopedByState = await render( FieldComboControl, {
		optionKey: '2-key',
		label: 'Column field',
		scope: 'row',
		context: ctx( { key: 'staff_list' } ),
	} );
	check(
		'F8.3 with no scopeKey prop it falls back to the sibling `key` state',
		labels( combo( scopedByState ).options ),
		[ "Role (Text, 'role')" ]
	);

	// AN UNKNOWN REPEATER KEY MUST NOT COLLAPSE THE LIST. Stranding the author with an
	// empty picker and no filters is the one failure mode worse than showing too much.
	const scopedUnknown = await render( FieldComboControl, {
		optionKey: 'A-key',
		label: 'Column field',
		scope: 'row',
		scopeKey: 'not_a_repeater',
		context: ctx( {} ),
	} );
	check(
		'F8.4 an unmatched repeater key falls through to the FULL pool rather than stranding the author',
		labels( combo( scopedUnknown ).options ).length,
		baseLabels.length
	);
	check( 'F8.5 ...and the filters stay hidden, because the control is still scoped', selects( scopedUnknown ).length, 0 );

	/* =====================================================================
	 * §F9 — the type default
	 *
	 * A pre-selected type filter is a starting VIEW, not a lock: the selectors stay
	 * visible so the author can widen back to All.
	 * ================================================================== */

	const typeDefaulted = await render( FieldComboControl, {
		optionKey: 'key',
		label: 'Field',
		typeDefault: 'repeater',
		context: ctx( {} ),
	} );
	check( 'F9.1 typeDefault pre-selects the type filter', selects( typeDefaulted )[ 1 ].value, 'repeater' );
	check(
		'F9.2 ...and narrows the list to that type',
		labels( combo( typeDefaulted ).options ),
		[ "Staff List (Repeater, 'staff_list')" ]
	);
	check( 'F9.3 ...while BOTH filters stay visible — a starting view, not a lock', selects( typeDefaulted ).length, 2 );

	// Only applied when that type was actually discovered; otherwise the picker would
	// open on an empty list.
	const typeMissing = await render( FieldComboControl, {
		optionKey: 'key',
		label: 'Field',
		typeDefault: 'gallery',
		context: ctx( {} ),
	} );
	check(
		'F9.4 an undiscovered typeDefault falls back to All rather than opening on nothing',
		selects( typeMissing )[ 1 ].value,
		'__all_types'
	);

	/* =====================================================================
	 * §F10 — the label
	 * ================================================================== */

	check( 'F10.1 without dynamicLabel the label is the one it was given', combo( base ).label, 'Field' );

	const dynamic = await render( FieldComboControl, {
		optionKey: 'key',
		dynamicLabel: true,
		context: ctx( { src: 'site' } ),
	} );
	check(
		'F10.2 with dynamicLabel and a sibling source preset, the label names the kind',
		combo( dynamic ).label,
		'Site Option Field'
	);

	const prefixed = await render( FieldComboControl, {
		optionKey: 'key',
		dynamicLabel: true,
		labelPrefix: 'URL',
		context: ctx( { src: 'site' } ),
	} );
	check( 'F10.3 ...and labelPrefix is honored', combo( prefixed ).label, 'URL Site Option Field' );

	/* =====================================================================
	 * §F11 — what onChange commits
	 *
	 * The option VALUE is a private merge key carrying a control character; the
	 * SERIALIZED value is the bare field key. The wrapper must never reach the tag.
	 * ================================================================== */

	let committed = null;
	const committing = await render( FieldComboControl, {
		optionKey: 'key',
		label: 'Field',
		context: ctx( {}, function ( next ) { committed = next; } ),
	} );
	const committingCombo = combo( committing );

	const cityOption = committingCombo.options.filter( function ( o ) { return o.label.indexOf( 'City' ) === 0; } )[ 0 ];
	committingCombo.onChange( cityOption.value );
	check( 'F11.1 picking a row commits the BARE key, not the merge-key wrapper', committed, { key: 'venue_city' } );
	check(
		'F11.2 ...and the wrapper really was a private value, so the strip is doing work',
		cityOption.value.indexOf( String.fromCharCode( 31 ) ) !== -1,
		true
	);

	committed = null;
	committingCombo.onChange( '' );
	check( 'F11.3 clearing DELETES the option rather than writing an empty string', committed, {} );

	committed = null;
	committingCombo.onChange( 'free_text_key' );
	check( 'F11.4 genuine free text commits verbatim', committed, { key: 'free_text_key' } );

	// A merge key with no map entry is a bug in the option-build paths. Serializing the
	// U+001F wrapper into a tag is the one outcome that must never happen, so the commit
	// is dropped instead.
	committed = null;
	committingCombo.onChange( 'post' + String.fromCharCode( 31 ) + 'ghost' + String.fromCharCode( 31 ) + 'Ghost' );
	check( 'F11.5 an unregistered merge key is DROPPED, never serialized', committed, null );

	/* =====================================================================
	 * §F12 — the mount filter
	 *
	 * Composition with the conditional-options filter is the property: whichever runs
	 * first, a hidden control stays hidden.
	 * ================================================================== */

	const stub = { key: 'key', type: 'stub' };

	check(
		'F12.1 an element already hidden by a prior filter stays hidden, whatever the order',
		fieldComboFilter( null, { key: { type: 'bws-field-combo' } }, ctx( {} ) ),
		null
	);
	check(
		'F12.2 an option that is not a bws-field-combo is passed through untouched',
		fieldComboFilter( stub, { key: { type: 'select' } }, ctx( {} ) ),
		stub
	);
	check(
		'F12.3 ...and so is one with no context to render against',
		fieldComboFilter( stub, { key: { type: 'bws-field-combo' } }, null ),
		stub
	);

	const mounted = fieldComboFilter(
		{ key: 'key' },
		{ key: { type: 'bws-field-combo', label: 'Meta/Option Field', scope: 'row', scopeKey: 'staff_list' } },
		ctx( {} )
	);
	check( 'F12.4 a matching option mounts the combo control', mounted.type === FieldComboControl, true );
	check(
		'F12.5 ...and the config reaches it, scope handle included',
		[ mounted.props.optionKey, mounted.props.label, mounted.props.scope, mounted.props.scopeKey ],
		[ 'key', 'Meta/Option Field', 'row', 'staff_list' ]
	);

	console.log( '' );
	if ( fail ) {
		console.log( 'FIELD COMBO CONTROL TEST FAILED (' + fail + ' of ' + total + ')' );
		process.exit( 1 );
	}
	console.log( 'FIELD COMBO CONTROL TEST PASSED (' + total + ' assertions)' );
}

main().catch( function ( e ) {
	console.error( e && e.stack ? e.stack : e );
	process.exit( 2 );
} );
