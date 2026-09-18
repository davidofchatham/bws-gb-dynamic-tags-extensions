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
 * TWO FILES LOAD, NOT ONE: the shipped chain grammar goes in first, because the control
 * recognizes a pinned root (§F13) by parsing the sibling `src` through it. A stub of the
 * grammar here would be a second spelling of the thing the twin harnesses exist to keep
 * single.
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
 * MUTATION-CHECKED 2026-08-28 (and again 2026-09-08, 2026-09-18 for §F13, and 2026-09-18 for §F16), because a display-layer
 * harness that asserts the wrong shapes passes forever and nobody looks again. Ten rules
 * were broken one at a time in the shipped file and every one failed here by name: always repeating the key in a row (F1.1, F1.2,
 * F2.2), an equality location filter instead of a prefix one (F5.1), serializing the merge-key
 * wrapper instead of the bare key (F11.1), dropping the underscore demotion (F1.1, F1.2),
 * dropping the label from the merge identity (F1.1, F1.4, F6.2), collapsing the auto-scope to
 * an empty list (F8.4), case-folding the custom-key suppression (F7.3), auto-selecting an
 * ambiguous key (F6.2), applying an undiscovered typeDefault (F9.4), and rendering the filters
 * while auto-scoped (F8.1, F8.5). §F13's four: collapsing the `scopeless` flag into an empty
 * scope list (F13.2 + F13.4, which is what the term `icon` reached through one scoped home and
 * one unscoped one is in the fixture for), dropping the KIND test from the narrowing (F13.1,
 * F13.1b, F13.3, F13.3b, F13.4), narrowing off a chain that
 * HOPS past its pin (F13.6), and narrowing on a pin that failed to resolve (F13.7, reached by
 * making the resolve fallback answer the pin's KIND instead of ''; deleting the empty-scope
 * guard outright is too coarse — it narrows unpinned tags too and the suite dies before §F13).
 * §F16's, and the SHARED PREDICATE both sections now narrow through (`narrowToScope`): dropping
 * its kind test fails F13.1 / F13.1b / F13.3 / F13.3b / F13.4 and F16.1b — and only F16.1b on
 * this side, because every list row in §F16 reads through a Location filter the `refs` tail
 * presets to "Post fields", which hides a surviving term record; the pool has to be read off
 * the filter's own OPTIONS, which is what that row does. Dropping its `scopeless` arm fails
 * F13.2 / F13.3 / F13.4 and F16.1 / F16.1b / F16.2 / F16.2b; making its slug test always true
 * fails F13.3 / F13.4 and four §F16 rows; emptying the D22 slug list fails F13.1 / F13.3 /
 * F13.3b / F13.9. §F16's own five: never applying the narrowing (F16.1, F16.1b, F16.2, F16.2c,
 * F16.7), passing a kind other than `post` (F16.1, F16.1b, F16.2, F16.2b), taking the first
 * matching RECORD instead of unioning across them (F16.2b), keeping the first HOME's types
 * instead of unioning within a record (F16.2, F16.2b), ignoring the server stamp (five rows),
 * running the repeater auto-scope's fall-through against the un-narrowed pool (F16.7), and
 * letting the kind gate ride on the types being known (F16.4b alone — which is the whole
 * reason that row exists: the gate shipped in the types-known arm only and EVERY row in the
 * section passed, because they all read through a preset that was hiding the survivors).
 * Widening the gate to any tail rather than a `refs` one fails eleven rows across §F13/§F15
 * and §F16.4c/§F16.6 — §F13.7 among them, which is the boundary the general form of this
 * rule (FW-13, Open) has to respect and the reason it is not taken here.
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

/* -------------------------------------------------------------------------
 * The entity-lookup fixtures
 *
 * `scope` is the SLUG the picker matches against a discovery group's own `scope` — a
 * taxonomy slug for a term, a post-type slug for a post. `#999` is deliberately absent:
 * a pin that will not resolve must leave the list unnarrowed rather than empty.
 * ---------------------------------------------------------------------- */

const ENTITY_ROUTE = '/bws-dynamic-tags/v1/entities';

const ENTITIES = {
	'term:34': { id: 34, label: '#34 Support', group: 'Department', scope: 'department' },
	'term:77': { id: 77, label: '#77 News', group: 'Category', scope: 'category' },
	'post:12': { id: 12, label: '#12 Jane Partner', group: 'Staff', scope: 'staff' },
};

const entityRequests = [];

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
	// The FIELD envelope is inlined below, the path a real editor page takes too
	// (wp_add_inline_script), so a `/fields` fetch here is a defect and still throws.
	// The ENTITY-LOOKUP route is a genuine round trip — it is how a pinned root's own
	// scope slug reaches the picker (FW-39 D22) — so it is served from the fixture
	// table below instead. Anything else is neither and throws.
	apiFetch: function ( args ) {
		const path = ( args && args.path ) || '';
		if ( 0 !== path.indexOf( ENTITY_ROUTE ) ) {
			throw new Error( 'apiFetch called for ' + path + ' — only the entity lookup is served here' );
		}
		entityRequests.push( path );
		const q = {};
		( path.split( '?' )[ 1 ] || '' ).split( '&' ).forEach( function ( pair ) {
			const kv = pair.split( '=' );
			q[ decodeURIComponent( kv[ 0 ] ) ] = decodeURIComponent( kv[ 1 ] || '' );
		} );
		const row = ENTITIES[ q.kind + ':' + q.id ] || null;
		return Promise.resolve( { row: row } );
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
 *   `icon`              — the same two-home shape one kind over: a TERM field reached
 *                         through a scoped home and an unscoped one
 *   `dept_lead`         — a post object restricted to ONE post type: the refs-tail
 *                         narrowing's subject (§F16)
 *   `partners`          — a relationship restricted to TWO, so the union is observable
 *                         as something other than "all of them"
 *   `any_ref`           — an UNRESTRICTED relationship: narrows nothing, on purpose
 *   `shared_ref`        — one key reaching BOTH kinds of union: two homes of one record
 *                         (merged, so the record's own types union), plus a second record
 *                         under a different label (so the lookup unions across records)
 *
 * THREE post-type-scoped groups, not two. A union over two types is only distinguishable
 * from no narrowing at all when a third type exists to be dropped — with two, `partners`
 * would return the whole post-kind list and §F16.2 would pass under a deleted filter.
 * ---------------------------------------------------------------------- */

global.window.bwsFieldEnvelope = {
	post: [
		{
			group_title: 'Event Details',
			// Scoped to ONE post type, so a pin of another kind narrows these away.
			scope: [ 'staff' ],
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
			// NO scope — the endpoint's own "any entity of that kind". Its `photo` is
			// also an Event Details field, so the merged row is reachable both scoped
			// and unscoped: the case the `scopeless` flag exists for.
			// The three relationship fields live HERE, unscoped, so a narrowed list reads
			// as "the scoped fields moved" rather than "everything moved" — and because
			// the picker that offers them is the one picking a `refs` ARGUMENT, which an
			// author reaches before any post type is settled.
			fields: [
				{ name: 'name', label: 'Feature Name', type: 'text' },
				{ name: 'photo', label: 'Photo', type: 'image' },
				{ name: 'dept_lead', label: 'Dept Lead', type: 'post_object', ref_types: [ 'office' ] },
				{ name: 'partners', label: 'Partners', type: 'relationship', ref_types: [ 'office', 'product' ] },
				{ name: 'any_ref', label: 'Any Ref', type: 'post_object', ref_types: [] },
				{ name: 'shared_ref', label: 'Shared Ref', type: 'post_object', ref_types: [ 'office' ] },
			],
		},
		{
			group_title: 'Office Details',
			scope: [ 'office' ],
			fields: [
				{ name: 'office_phone', label: 'Office Phone', type: 'text' },
				// The SAME key and label as the Feature Block entry above, allowing a
				// DIFFERENT post type: one merged record whose allowed types are the union
				// of both homes. Nothing else in the fixture can tell a union apart from
				// a first-match (§F16.2b).
				{ name: 'shared_ref', label: 'Shared Ref', type: 'post_object', ref_types: [ 'product' ] },
			],
		},
		{
			group_title: 'Product Details',
			scope: [ 'product' ],
			fields: [
				{ name: 'sku', label: 'SKU', type: 'text' },
				// `shared_ref` again, under a DIFFERENT label — so it is a second RECORD
				// rather than a third home of the first one (merge identity is kind + key
				// + label). The wire names only the key, so either could be the field
				// stepped through, and the types have to union across the two records as
				// well as within one. Two unions, and §F16.2b fails if either goes.
				{ name: 'shared_ref', label: 'Shared Ref (Legacy)', type: 'post_object', ref_types: [ 'staff' ] },
			],
		},
	],
	term: [
		{
			group_title: 'Taxonomy Extras',
			scope: [ 'department' ],
			fields: [
				{ name: 'blurb', label: 'Blurb', type: 'textarea' },
				{ name: 'icon', label: 'Icon', type: 'image' },
			],
		},
		{
			group_title: 'Term Shared',
			// NO scope, under kind `term` — the same "any entity of that kind" the post
			// `Feature Block` group carries, spelled on the OTHER kind. Both are needed:
			// one unscoped group alone cannot tell "offered under any subtype of its own
			// kind" apart from "offered under any kind at all", which is the whole §F13
			// question. Its `icon` is also a Taxonomy Extras field, so the merged row is
			// reachable both scoped and unscoped — the `scopeless` case, within a kind.
			fields: [
				{ name: 'icon', label: 'Icon', type: 'image' },
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

// WHICH ROOT SLUGS PIN, and of what kind — inlined from bws_registered_root_rows() on a
// real editor page. Spelled here as the two shipped pinning roots do; an argless root is
// absent from the map, which is what `current` below asserts.
global.window.bwsRootArgKinds = { term: 'term', post: 'post' };

// WHAT KIND EACH CHAIN TOKEN RESOLVES TO — inlined from bws_fold_wire_vocabulary() on a real
// editor page, which assembles it from BWS_FOLD_STEP_KINDS + BWS_FOLD_PARSE_TIME_ROOT_KINDS.
// Spelled here EXACTLY as those two constants read, every entry included. `refs => post` is
// what §F15.5 presets through, and it must be present rather than trimmed: with it absent, a
// `refs` tail would preset nothing for the wrong reason and the row would pass while asserting
// the opposite of its name. `meta_row` likewise stays, so §F15.7 measures a produced kind the
// picker has no Location for rather than one nothing ships.
global.window.bwsChainKinds = {
	steps: { refs: 'post', terms: 'term', rows: 'meta_row' },
	roots: { site: 'site', term: 'term', post: 'post' },
};

// The SHIPPED chain grammar, not a stub of it: the control recognizes a pin by parsing
// the sibling `src` through `window.bwsSlotFold`, and a hand-rolled split here would be
// the second spelling of the chain grammar that this repo's twin harnesses exist to
// prevent.
const grammarFile = path.join( root, 'assets/js/slot-fold-grammar.js' );
vm.runInThisContext( fs.readFileSync( grammarFile, 'utf8' ), { filename: grammarFile } );

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
			'Any Ref (Post Object, \'any_ref\')',
			'Blurb (Text Area, \'blurb\')',
			'City (Text, \'venue_city\')',
			'Dept Lead (Post Object, \'dept_lead\')',
			'Email (Email, \'email\')',
			'Email (Email, \'email\')',
			'event_date (Date)',
			'Feature Name (Text, \'name\')',
			'Icon (Image, \'icon\')',
			'Name (Text, \'name\')',
			'Office Phone (Text, \'office_phone\')',
			'Partners (Relationship, \'partners\')',
			'Photo (Image, \'photo\')',
			'Role (Text, \'role\')',
			'Shared Ref (Post Object, \'shared_ref\')',
			'Shared Ref (Legacy) (Post Object, \'shared_ref\')',
			'SKU (Text, \'sku\')',
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
			'Post fields › Office Details',
			'Post fields › Product Details',
			'Site fields',
			'Site fields › Site Options',
			'Term fields',
			'Term fields › Taxonomy Extras',
			'Term fields › Term Shared',
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
		[ 'All field types', 'Loop fields', 'Date', 'Email', 'Image', 'Post Object', 'Relationship', 'Repeater', 'Text', 'Text Area' ]
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
		'Site Option Field Key'
	);

	const prefixed = await render( FieldComboControl, {
		optionKey: 'key',
		dynamicLabel: true,
		labelPrefix: 'URL',
		context: ctx( { src: 'site' } ),
	} );
	check( 'F10.3 ...and labelPrefix is honored', combo( prefixed ).label, 'URL Site Option Field Key' );

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

	/* =====================================================================
	 * §F13 — the pinned-root narrowing (FW-39 D22)
	 *
	 * A single-step chain rooted at a PINNED entity narrows the list to the fields
	 * scoped to that entity's taxonomy or post type. The scope handle is the
	 * entity-lookup route's own `scope`, matched against the discovery envelope's
	 * EXISTING per-field `scope` — nothing is added to discovery, which is where D23
	 * draws the line.
	 *
	 * NARROWING IS WITHIN THE SELECTED ENTITY'S KIND (1.21.0). What an unscoped group reaches
	 * is stated where it is derived, at `bws_field_discovery_derive_kind_scope()`; what the
	 * picker DOES about it is what this section holds, and a fixture envelope cannot fail when
	 * that derivation moves. F13.1b is the row that holds it, and it is the row that CHANGED
	 * here: before 1.21.0 an unscoped post group's fields were offered under a term, which no
	 * term read can reach. The fixture carries an unscoped group under BOTH kinds precisely so
	 * the within-kind rule and the any-kind one produce different lists.
	 *
	 * ASSERTED AS WHOLE LISTS, not as membership of one row: the property is what an
	 * author sees in the picker, and a membership check passes just as happily on a list
	 * that narrowed nothing.
	 * ================================================================== */

	const pinnedDepartment = labels( combo( await render( FieldComboControl, {
		optionKey: 'key',
		label: 'Field',
		context: ctx( { src: 'term,34' } ),
	} ) ).options );

	// Rendered here rather than at F13.4 because F13.2's rule needs it: `icon`'s only
	// SCOPED home is `department`, so `category` is the pin that excludes it.
	const pinnedNews = labels( combo( await render( FieldComboControl, {
		optionKey: 'key',
		label: 'Field',
		context: ctx( { src: 'term,77' } ),
	} ) ).options );

	check(
		"F13.1 a pinned TERM narrows to that taxonomy's fields, plus every unscoped one OF ITS KIND",
		pinnedDepartment,
		[
			"Blurb (Text Area, 'blurb')",
			"Icon (Image, 'icon')",
		]
	);

	// THE ROW THAT MOVED IN 1.21.0, and the reason the fixture needed a second unscoped
	// group. `Feature Name` is an unscoped POST group's field and the second `Email` an
	// unscoped SITE one; both used to pass a term narrowing, because `scopeless`
	// short-circuited before anything consulted kind. A term read reaches neither, so the
	// picker was offering fields that could not work. Asserted as the ABSENCE of the two
	// named rows rather than left to F13.1's whole list, so the regression fails by name.
	check(
		'F13.1b ...and nothing of another KIND, unscoped or not',
		pinnedDepartment.filter( function ( l ) {
			return l.indexOf( 'Feature Name' ) === 0 || l.indexOf( 'Email' ) === 0;
		} ),
		[]
	);

	// `icon` is reached through a `department`-scoped group AND an unscoped one, both under
	// kind `term`. It survives a `category` pin because ONE unscoped home makes a field
	// reachable across its kind — the rule a plain union of scope slugs would have lost.
	check(
		'F13.2 a field with one unscoped home survives a pin its other home excludes',
		pinnedNews.indexOf( "Icon (Image, 'icon')" ) !== -1,
		true
	);

	check(
		"F13.3 a pinned POST narrows to that post type's fields — a different list, same rule",
		labels( combo( await render( FieldComboControl, {
			optionKey: 'key',
			label: 'Field',
			context: ctx( { src: 'post,12' } ),
		} ) ).options ),
		[
			"Any Ref (Post Object, 'any_ref')",
			"City (Text, 'venue_city')",
			"Dept Lead (Post Object, 'dept_lead')",
			"Email (Email, 'email')",
			'event_date (Date)',
			"Feature Name (Text, 'name')",
			"Name (Text, 'name')",
			"Partners (Relationship, 'partners')",
			"Photo (Image, 'photo')",
			"Role (Text, 'role')",
			"Shared Ref (Post Object, 'shared_ref')",
			"Staff List (Repeater, 'staff_list')",
			'_gb_internal (Text)',
		]
	);

	// Only ONE Email now: the post one. The site `email` shares the key and the label and
	// is a different kind, which is the same rule F13.1b states from the term side — and
	// the one place the two-rows-under-two-kinds merge identity (F1.5) is observed being
	// narrowed back apart.
	check(
		'F13.3b ...and the SITE twin of a post key is not among them',
		labels( combo( await render( FieldComboControl, {
			optionKey: 'key',
			label: 'Field',
			context: ctx( { src: 'post,12' } ),
		} ) ).options ).filter( function ( l ) { return l === "Email (Email, 'email')"; } ).length,
		1
	);

	// Re-narrowing is what "changing the pin re-narrows without a reload" means at this
	// layer: the scope is derived per render from the sibling token, never cached against
	// the first pin the control saw.
	check(
		'F13.4 changing the pin re-narrows — a taxonomy with no fields of its own leaves only the unscoped ones of its kind',
		pinnedNews,
		[
			"Icon (Image, 'icon')",
		]
	);

	check(
		'F13.5 an ARGLESS root narrows nothing — `current` has no kind until render',
		labels( combo( await render( FieldComboControl, {
			optionKey: 'key',
			label: 'Field',
			context: ctx( { src: 'current' } ),
		} ) ).options ),
		baseLabels
	);

	// The read applies to the STEP's target, not to the pin. The target's post TYPE is still
	// unknown, so the pin's scope must not reach past the hop — but its KIND is known and the
	// Location filter says so since 1.21.0 (§F15.5), which is why this is asserted as "the pin
	// changed nothing" rather than against the unfiltered list: same hop with and without a
	// pin, byte-identical. A scope narrowing leaking past the hop would drop the `staff`-scoped
	// rows from the first list and not the second.
	const hoppedUnpinned = labels( combo( await render( FieldComboControl, {
		optionKey: 'key',
		label: 'Field',
		context: ctx( { src: 'current;refs,related' } ),
	} ) ).options );
	check(
		'F13.6 a chain that HOPS past the pin narrows nothing — the hop offers its own list either way',
		labels( combo( await render( FieldComboControl, {
			optionKey: 'key',
			label: 'Field',
			context: ctx( { src: 'term,34;refs,related' } ),
		} ) ).options ),
		hoppedUnpinned
	);
	// `related` names no discovered field, so the refs-tail narrowing (§F16) has no post
	// types to narrow by and answers the whole post-kind list — every post-type scope in
	// the fixture is present below. That is §F16.4's rule observed from the other side:
	// a field whose config cannot be read leaves the list at kind `post`, never empty.
	check(
		'F13.6b ...and that list is the POST-kind one the hop lands on, so the row above is not two empties agreeing',
		hoppedUnpinned,
		[
			"Any Ref (Post Object, 'any_ref')",
			"City (Text, 'venue_city')",
			"Dept Lead (Post Object, 'dept_lead')",
			"Email (Email, 'email')",
			'event_date (Date)',
			"Feature Name (Text, 'name')",
			"Name (Text, 'name')",
			"Office Phone (Text, 'office_phone')",
			"Partners (Relationship, 'partners')",
			"Photo (Image, 'photo')",
			"Role (Text, 'role')",
			"Shared Ref (Post Object, 'shared_ref')",
			"Shared Ref (Legacy) (Post Object, 'shared_ref')",
			"SKU (Text, 'sku')",
			"Staff List (Repeater, 'staff_list')",
			'_gb_internal (Text)',
		]
	);

	check(
		'F13.7 a pin that will not resolve leaves the list UNNARROWED, never empty',
		labels( combo( await render( FieldComboControl, {
			optionKey: 'key',
			label: 'Field',
			context: ctx( { src: 'term,999' } ),
		} ) ).options ),
		baseLabels
	);

	// The slot prefix is the same one `presetKind()` reads by: a try_ slot's key control
	// must narrow on ITS OWN slot's source, not on slot 1's.
	check(
		"F13.8 a slot-prefixed key reads its own slot's pin",
		labels( combo( await render( FieldComboControl, {
			optionKey: '2-key',
			label: 'Field',
			context: ctx( { src: 'post,12', '2-src': 'term,34' } ),
		} ) ).options ),
		pinnedDepartment
	);

	// The narrowing composes with the repeater auto-scope rather than replacing it —
	// `staff_list` lives in the `staff`-scoped group, so a post pin keeps its sub-fields.
	check(
		'F13.9 the pin narrowing composes with the repeater auto-scope',
		labels( combo( await render( FieldComboControl, {
			optionKey: 'key',
			label: 'Field',
			scope: 'row',
			scopeKey: 'staff_list',
			context: ctx( { src: 'post,12' } ),
		} ) ).options ),
		[ "Role (Text, 'role')" ]
	);

	// The lookup is the ENTITY route's, once per pin — the discovery envelope is still
	// read inline and never fetched, which is the boundary D23 draws.
	check(
		"F13.10 the scope came from the entity-lookup route's resolve mode, never from discovery",
		entityRequests[ 0 ],
		ENTITY_ROUTE + '?kind=term&mode=resolve&id=34'
	);

	/* =====================================================================
	 * §F14 — the Location preset from a chain's terminal repeater (FW-74)
	 *
	 * A chain ending on a repeater step names the exact home of every field the read can
	 * reach, so the Location filter opens THERE rather than on the kind root or on All.
	 * The recognition is machine-readable both ways: the chain is parsed through the
	 * shipped grammar, and whether the tail's argument names a repeater is asked of the
	 * discovery envelope's own container record — this file carries no list of which step
	 * slugs produce rows, which is why F14.4 and F14.5 preset nothing without naming
	 * `refs` or `terms` as the reason.
	 *
	 * ASSERTED AS THE FILTER VALUE **AND** THE RESULTING LIST. The value alone would pass
	 * on a preset that pointed at a path holding nothing, which is the failure mode the
	 * `locExists` guard exists for.
	 * ================================================================== */

	const rowsChain = await render( FieldComboControl, {
		optionKey: 'key',
		label: 'Field',
		context: ctx( { src: 'rows,staff_list' } ),
	} );

	check(
		'F14.1 a chain ending on a repeater presets Location to that repeater\'s own path',
		selects( rowsChain )[ 0 ].value,
		'Post fields › Event Details › Staff List'
	);
	check(
		'F14.2 ...and the list that opens is that repeater\'s sub-fields',
		labels( combo( rowsChain ).options ),
		[ "Role (Text, 'role')" ]
	);

	// THE TAIL, not the root: the read applies to whatever the chain resolved to last, so
	// a multi-step chain presets off its final step exactly as a one-step chain does. This
	// is also the shape a BASE tag serializes, where the fold containers hand the picker
	// their terminal step alone.
	check(
		'F14.3 a multi-step chain presets off its TAIL, not its root',
		selects( await render( FieldComboControl, {
			optionKey: 'key',
			label: 'Field',
			context: ctx( { src: 'current;rows,staff_list' } ),
		} ) )[ 0 ].value,
		'Post fields › Event Details › Staff List'
	);

	// A `refs` argument names a relationship field, not a container, so the repeater path must
	// decline and let the KIND path answer. Asserting the kind's own value rather than "not a
	// repeater path" is what makes the decline visible: a container path winning here would
	// read as a location three segments deeper.
	check(
		'F14.4 a tail whose argument names no discovered CONTAINER falls through to the kind preset',
		selects( await render( FieldComboControl, {
			optionKey: 'key',
			label: 'Field',
			context: ctx( { src: 'current;refs,related' } ),
		} ) )[ 0 ].value,
		'Post fields'
	);
	check(
		'F14.5 ...and a repeater key nothing discovered presets nothing either, rather than an empty view',
		labels( combo( await render( FieldComboControl, {
			optionKey: 'key',
			label: 'Field',
			context: ctx( { src: 'rows,never_registered' } ),
		} ) ).options ),
		baseLabels
	);

	// Same slot prefix `presetKind()` and the root-argument narrowing read by: a try_ slot's
	// key control presets off ITS OWN slot's source, not off slot 1's.
	check(
		'F14.6 a slot-prefixed key presets off its own slot\'s chain',
		selects( await render( FieldComboControl, {
			optionKey: '2-key',
			label: 'Field',
			context: ctx( { src: 'current', '2-src': 'rows,staff_list' } ),
		} ) )[ 0 ].value,
		'Post fields › Event Details › Staff List'
	);

	// The dynamic label follows the ACTIVE location, which is already its rule — so naming
	// the repeater is a consequence of the preset landing, not a second mechanism.
	check(
		'F14.7 the dynamic label names the repeater the preset landed on',
		combo( await render( FieldComboControl, {
			optionKey: 'key',
			dynamicLabel: true,
			context: ctx( { src: 'rows,staff_list' } ),
		} ) ).label,
		'Staff List Field Key'
	);

	/* =====================================================================
	 * §F15 — the KIND preset follows the chain, not the legacy flat keys
	 *
	 * The question is `bws_fold_chain_resolution()`'s: the tail STEP's produced kind, or the
	 * ROOT's where that answers at parse time. Both maps arrive from PHP on
	 * `window.bwsChainKinds`, so this section asserts the WIRING, not a table of slugs — add
	 * a step type in PHP and it presets here with no edit to the shipped file or to this one.
	 *
	 * WHAT THIS REPLACED, and why the rows read as a pair: the derivation used to be
	 * `srcTermIn` plus a literal `src === 'site'`. Both predate chain wire. `srcTermIn` is
	 * dropped at registration on every chain-source tag (1.17.0), so the ONLY term preset in
	 * the plugin sat on wire nothing can author, while `terms,<tax>` — its replacement —
	 * presetted nothing. F15.1 and F15.2 are that pair, and they must agree.
	 *
	 * ASSERTED AS THE FILTER VALUE AND THE LABEL TOGETHER. The label is derived from the
	 * active location, so a preset that landed without the label following it would be a
	 * half-applied kind, which is what the old `src:ref` behaviour was.
	 * ================================================================== */

	async function presetOf( state ) {
		const t = await render( FieldComboControl, {
			optionKey: 'key',
			dynamicLabel: true,
			context: ctx( state ),
		} );
		return [ selects( t )[ 0 ].value, combo( t ).label ];
	}

	check(
		'F15.1 a LEGACY flat srcTermIn still presets Term — a dropped option is not a dropped value',
		await presetOf( { srcTermIn: 'department' } ),
		[ 'Term fields', 'Term Meta Field Key' ]
	);
	check(
		'F15.2 ...and the chain spelling that REPLACED it presets identically',
		await presetOf( { src: 'terms,department' } ),
		[ 'Term fields', 'Term Meta Field Key' ]
	);
	check(
		'F15.3 a multi-step chain presets off its tail step',
		await presetOf( { src: 'current;terms,department' } ),
		[ 'Term fields', 'Term Meta Field Key' ]
	);
	// A declaring root is the one tail whose kind is known AND whose pool is already narrowed
	// by something finer. It presets the LABEL and leaves the FILTER alone: the D22 narrowing
	// keeps unscoped groups of other kinds (§F13.2), which a "Term fields" filter would drop,
	// and on a selection that failed to resolve it would narrow to nothing (§F13.7). The pair
	// below is the whole property — the label moved, the list did not.
	check(
		'F15.4 a DECLARING root presets its LABEL from the kind but leaves the Location filter alone',
		await presetOf( { src: 'term,34' } ),
		[ '__all_locations', 'Term Meta Field Key' ]
	);
	// SAME ANSWER WITH NO ARGUMENT YET, and that is the property rather than a second case
	// of the first. Gating on a RESOLVED argument gave the empty state a "Term fields"
	// preset and the filled state "All detected fields", so the filter loosened as the
	// author supplied information. The test is what the ROOT is, not whether it is filled.
	check(
		'F15.4b ...and an UNFILLED declaring root answers identically — the filter never loosens on selection',
		await presetOf( { src: 'term' } ),
		[ '__all_locations', 'Term Meta Field Key' ]
	);
	// The LIST half of that property is §F13.1 and §F13.7, which assert the scope-narrowed
	// lists literally. A row here re-rendering `term,34` and comparing it to §F13's own
	// `term,34` would compare a render to itself and pass under any narrowing at all, so the
	// pointer is the assertion: drop the gate and those two go red.

	// `refs` PRESETS, and it is the derivation doing it — no step is exempt. It held an
	// exemption from 1.13.0 (`22bddf1`) on the ground that the target's post TYPE is unknown,
	// which is true and is about a different axis: `refs` produces `post` unconditionally,
	// and the list it used to offer held term and site fields a post read cannot reach. The
	// exemption comes back only if `refs` can produce more than ONE kind, and that fails
	// `BWS_FOLD_STEP_KINDS` before it reaches here.
	check(
		'F15.5 a refs tail presets Post from the vocabulary like any other step — no slug is exempt',
		await presetOf( { src: 'refs,lead' } ),
		[ 'Post fields', 'Post Meta Field Key' ]
	);
	check(
		'F15.6 ...and the legacy flat spelling of the same hop presets identically',
		await presetOf( { src: 'ref', ref: 'lead' } ),
		[ 'Post fields', 'Post Meta Field Key' ]
	);
	// The pair above is HARNESS-ONLY on a healthy stack, and deliberately so: the base tag's
	// mount migrator folds a flat `src:ref|ref:lead` before an author can look at the panel,
	// so no manual row drives F15.6 and none claims to. What it pins is that the flat arm and
	// the step it folds into answer the SAME, which is what makes the fold invisible rather
	// than merely fast — and on a stack where the grammar failed to load, the fold does not
	// happen and this arm is the only preset there is (§F15.10/§F15.11).

	// `rows` produces `meta_row`, which is not a Location the filter can open on. The kind
	// path must decline rather than round to the nearest kind — the repeater path (§F14) is
	// what answers here, and F14.1 proves it still does.
	check(
		'F15.7 a kind with no Location of its own presets nothing through the KIND path',
		await presetOf( { src: 'rows,never_registered' } ),
		[ '__all_locations', 'Meta/Option Field Key' ]
	);
	check(
		'F15.8 an argless root presets nothing — `current` has no kind until render',
		await presetOf( { src: 'current' } ),
		[ '__all_locations', 'Meta/Option Field Key' ]
	);
	check(
		'F15.9 `site` presets through the ROOT map now, not through a literal equality',
		await presetOf( { src: 'site' } ),
		[ 'Site fields', 'Site Option Field Key' ]
	);

	// The maps are PHP's. With none delivered the control presets nothing rather than
	// falling back to a built-in table — the fallback is what would let the two drift.
	const savedKinds = global.window.bwsChainKinds;
	global.window.bwsChainKinds = undefined;
	check(
		'F15.10 with no vocabulary delivered, the chain presets nothing (no built-in table)',
		await presetOf( { src: 'terms,department' } ),
		[ '__all_locations', 'Meta/Option Field Key' ]
	);
	check(
		'F15.11 ...while the legacy flat key, which needs no vocabulary, still does',
		await presetOf( { srcTermIn: 'department' } ),
		[ 'Term fields', 'Term Meta Field Key' ]
	);
	global.window.bwsChainKinds = savedKinds;

	/* =====================================================================
	 * §F16 — the refs-tail narrowing to post TYPES (FW-13)
	 *
	 * §F15.5 takes a `refs` tail as far as kind `post`. This is the axis under it: the
	 * argument names a relationship or post object, and THAT FIELD declares the post types
	 * it can land on. The editor cannot derive them — a `refs` argument is the field
	 * stepped through, not what it reaches — so they arrive stamped on the record
	 * (`ref_types`, `bws_field_discovery_ref_post_types()`), which is the same shape
	 * `repeater_key` takes for a `rows` tail.
	 *
	 * SEVERITY IS WHY THIS IS A SEPARATE SECTION FROM §F13. A record of the wrong KIND is
	 * a wrong answer — the read cannot reach it at all. A record of the wrong post TYPE is
	 * a LOOSE answer — it resolves for some of the posts the step lands on and not others.
	 * The rows below narrow where the field says so and stay loose where it does not, and
	 * F16.3/F16.4 are the loose cases asserted as deliberate rather than left unstated.
	 *
	 * BOTH SEVERITIES LIVE HERE, THOUGH, and only one of them is about post types. `refs`
	 * produces `post` whatever field it steps through, so the KIND gate applies to every
	 * `refs` tail and is not conditional on the types being known — F16.4b is that half, and
	 * it is a POOL row rather than a list row for the reason F16.1b is.
	 *
	 * ASSERTED AS WHOLE LISTS, per the file's own rule. A "does it contain X" row would
	 * pass under a filter that dropped the wrong half.
	 * ================================================================== */

	async function refTailList( wire ) {
		return labels( combo( await render( FieldComboControl, {
			optionKey: 'key',
			label: 'Field',
			context: ctx( { src: wire } ),
		} ) ).options );
	}

	// `dept_lead` allows `office` alone. So: Office Details survives, the unscoped Feature
	// Block survives (an unscoped group means "any subtype of MY kind", and `office` is
	// one), and Event Details (`staff`) and Product Details (`product`) are gone — along
	// with every term and site record, which §F13's kind test already removed.
	//
	// `Feature Name` and `Name` are the pair that makes this readable: the same key `name`
	// under two labels, one home unscoped and one scoped to `staff`. Exactly one survives,
	// so the row cannot pass under a filter keyed on the KEY instead of the home.
	check(
		'F16.1 a refs tail over a field restricted to ONE post type offers that type plus the unscoped post-kind fields',
		await refTailList( 'refs,dept_lead' ),
		[
			"Any Ref (Post Object, 'any_ref')",
			"Dept Lead (Post Object, 'dept_lead')",
			"Feature Name (Text, 'name')",
			"Office Phone (Text, 'office_phone')",
			"Partners (Relationship, 'partners')",
			"Photo (Image, 'photo')",
			"Shared Ref (Post Object, 'shared_ref')",
		]
	);

	// THE POOL, NOT THE VIEW. Every row in this section reads the list through the Location
	// filter, which a `refs` tail presets to "Post fields" (§F15.5) — so the KIND half of
	// the narrowing is invisible in those lists: a term record surviving it would be hidden
	// by the preset rather than by the filter under test. The Location OPTIONS are built
	// from the narrowed pool BEFORE that preset applies, so they are where the pool itself
	// can be read. A record of another kind in there would put its root in this list.
	check(
		'F16.1b the narrowed POOL holds post-kind records only — the Location filter has no other kind to offer',
		labels( selects( await render( FieldComboControl, {
			optionKey: 'key',
			label: 'Field',
			context: ctx( { src: 'refs,dept_lead' } ),
		} ) )[ 0 ].options ),
		// Event Details is here on `photo`'s account and not on its own: `photo` is one
		// merged record reached through Event Details AND the unscoped Feature Block, so
		// it survives and lists under both homes. Every ROOT in the list is `Post fields`,
		// which is what the row is about.
		[
			'All detected fields',
			'Post fields',
			'Post fields › Event Details',
			'Post fields › Feature Block',
			'Post fields › Office Details',
		]
	);

	// `partners` allows `office` AND `product`. The union is what makes this row different
	// from both F16.1 and F16.3: Product Details joins, Event Details still does not.
	check(
		'F16.2 a refs tail over a field allowing SEVERAL types offers the union, and still not the rest',
		await refTailList( 'refs,partners' ),
		[
			"Any Ref (Post Object, 'any_ref')",
			"Dept Lead (Post Object, 'dept_lead')",
			"Feature Name (Text, 'name')",
			"Office Phone (Text, 'office_phone')",
			"Partners (Relationship, 'partners')",
			"Photo (Image, 'photo')",
			"Shared Ref (Post Object, 'shared_ref')",
			"Shared Ref (Legacy) (Post Object, 'shared_ref')",
			"SKU (Text, 'sku')",
		]
	);

	// TWO UNIONS, ONE KEY. `shared_ref` names one merged record reached through two homes
	// (`office` + `product`) and a second record under a different label (`staff`) — so
	// reaching every post type in the fixture takes the union WITHIN a record and the union
	// ACROSS records, and the row goes red if either degrades to a first match. The wire
	// names only the key, so either record could be the field stepped through and offering
	// for one of them alone would hide the other's fields.
	//
	// Asserted against the un-narrowed post list rather than a literal: what is being
	// claimed is "these three types are every type there is", and spelling the list again
	// would let the claim survive a fixture that grew a fourth.
	check(
		'F16.2b a key whose allowed types union to every post type narrows to the whole post-kind list',
		await refTailList( 'refs,shared_ref' ),
		hoppedUnpinned
	);
	// ...and the same key with either union removed narrows to LESS than that, which is
	// what makes the row above an assertion rather than a tautology: `office` alone drops
	// Event Details and Product Details (§F16.1 is that list, one row shorter).
	check(
		'F16.2c ...while one of its homes alone reaches strictly fewer, so the row above is not measuring nothing',
		( await refTailList( 'refs,dept_lead' ) ).length < hoppedUnpinned.length,
		true
	);

	// An unrestricted relationship means "any post type", which is exactly what kind
	// `post` alone already says. Compared against the hop that narrowed nothing at all
	// (§F13.6b) rather than against a literal, so the claim is "identical to no narrowing"
	// and not "happens to list these nine".
	check(
		'F16.3 an UNRESTRICTED field narrows nothing past kind `post` — loose is the honest answer',
		await refTailList( 'refs,any_ref' ),
		hoppedUnpinned
	);
	check(
		'F16.4 ...and so does a field the discovery never saw — never an empty list',
		await refTailList( 'refs,no_such_field' ),
		hoppedUnpinned
	);

	// THE KIND GATE IS UNCONDITIONAL, and the two rows above cannot see that. Both read the
	// list through a Location filter the `refs` tail already presets to "Post fields", so a
	// term record surviving in the POOL is hidden by the preset and they stay green either
	// way — which is exactly what happened: the gate ran in the types-known arm only, and
	// every assertion in this section passed while an unrestricted tail kept 16 unreadable
	// term and site fields one widening click away. The preset is a starting VIEW; it was
	// doing a pool's job. Read off the filter's own OPTIONS, as §F16.1b is.
	check(
		'F16.4b ...and BOTH still bind the pool to kind `post` — the gate does not ride on the types being known',
		labels( selects( await render( FieldComboControl, {
			optionKey: 'key',
			label: 'Field',
			context: ctx( { src: 'refs,any_ref' } ),
		} ) )[ 0 ].options ),
		[
			'All detected fields',
			'Post fields',
			'Post fields › Event Details',
			'Post fields › Event Details › Staff List (repeater)',
			'Post fields › Feature Block',
			'Post fields › Office Details',
			'Post fields › Product Details',
		]
	);
	// The contrast that makes the row above an assertion: an ARGLESS root knows no kind, so
	// its pool keeps all three and every kind root is offered. Same read, opposite answer.
	check(
		'F16.4c ...while a source that knows no kind keeps all three, so the row above is not measuring an empty fixture',
		labels( selects( await render( FieldComboControl, {
			optionKey: 'key',
			label: 'Field',
			context: ctx( { src: 'current' } ),
		} ) )[ 0 ].options ).filter( function ( l ) {
			return l === 'Term fields' || l === 'Site fields';
		} ),
		[ 'Site fields', 'Term fields' ]
	);

	// THE NARROWING IS THE TAIL'S, not any step's. A `refs` hop consumed mid-chain has
	// already been stepped through by the time the read applies, so it says nothing about
	// where the read lands — the tail does, and here the tail is a different `refs`.
	check(
		'F16.5 a refs hop that is NOT the tail does not narrow — the read applies to what the chain last resolved to',
		await refTailList( 'refs,partners;refs,dept_lead' ),
		await refTailList( 'refs,dept_lead' )
	);

	// `terms` is deliberately exempt: its argument is a taxonomy and it produces a term,
	// which is the KIND axis §F15.2 already answers. A post-type narrowing reaching it
	// would be narrowing term records by post-type slugs.
	check(
		'F16.6 a `terms` tail is untouched by this — its argument is a taxonomy, not a relationship field',
		await refTailList( 'refs,dept_lead;terms,department' ),
		[
			"Blurb (Text Area, 'blurb')",
			"Icon (Image, 'icon')",
		]
	);

	// Composes with the repeater auto-scope, the same way §F13.9 does: the narrowing runs
	// first and the scope runs on its result.
	check(
		'F16.7 the refs narrowing composes with the repeater auto-scope rather than replacing it',
		labels( combo( await render( FieldComboControl, {
			optionKey: 'key',
			label: 'Field',
			scope: 'row',
			scopeKey: 'staff_list',
			context: ctx( { src: 'refs,dept_lead' } ),
		} ) ).options ),
		// `staff_list` is an Event Details field, dropped by the `office` narrowing — so
		// the repeater handle matches nothing and the auto-scope falls through to the
		// narrowed pool rather than to an empty list, which is F8.4's rule reached from
		// here. The two narrowings compose; neither one silently overrules the other.
		await refTailList( 'refs,dept_lead' )
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
