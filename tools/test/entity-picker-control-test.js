/**
 * Harness for the entity-picker control's display layer (FW-39, D14-D21). Run:
 * `node tools/test/entity-picker-control-test.js` (exits non-zero on failure, house
 * convention — see field-combo-control-test.js for the pattern this mirrors).
 *
 * NO NEW EXPORTS (#94): `assets/js/entity-picker-control.js` exposes only
 * `window.bwsEntityPickerControl`, the component itself. Everything below reaches its
 * private helpers (the group derivation, the taxonomy filter, the REST path builder) by
 * rendering the component against stubbed `wp.element` hooks and a stubbed
 * `wp.apiFetch`, and reading the tree AND THE REQUESTED PATHS it produces — the same
 * posture field-combo-control-test.js takes for the same reason.
 *
 * KIND-TABLE-DRIVEN (FW-39 ticket 03): `term` and `post` are both rows in the same
 * table, driven by the same loop — adding `post` was exactly the fixture row and loop
 * the ticket-02 header predicted, not a rewrite, because every assertion below already
 * read the kind off `props.kind` rather than hard-coding 'term' into the request-path
 * checks. `user` (D12) has no row: it is designed, not built, and adding a row for a
 * kind the control does not yet serve would assert behavior nothing backs.
 *
 * WHAT THIS DOES NOT COVER: the REST route itself (entity-lookup-test.php owns
 * that), ComboboxControl's own rendering/keyboard behaviour, and anything needing a
 * real DOM — those stay manual, held by registered-roots-test-matrix.md.
 *
 * @package BWS_Dynamic_Tags
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const root = path.resolve( __dirname, '../..' );

/* -------------------------------------------------------------------------
 * A minimal hook runtime — see field-combo-control-test.js for the rationale.
 * ---------------------------------------------------------------------- */

const hooks = { cells: [], idx: 0, effects: [], cleanups: [], dirty: true };

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

function useMemo( fn ) {
	return fn();
}

function useEffect( fn, deps ) {
	const i = hooks.idx++;
	const prev = hooks.cells[ i ];
	const changed = ! prev || ! deps || deps.some( function ( d, j ) { return d !== prev[ j ]; } );
	if ( changed ) {
		hooks.effects.push( fn );
	}
	hooks.cells[ i ] = deps;
}

/** Run passes over the SAME hook cells until nothing set state during a pass. */
async function settle( Component, props ) {
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

/** Fresh mount — new hook cells, as a first render always gets. */
async function render( Component, props ) {
	hooks.cells = [];
	hooks.dirty = true;
	return settle( Component, props );
}

/**
 * Re-render the SAME mounted instance after firing an event on its rendered tree —
 * the "select a filter, see the list narrow" cases (D16) need the instance's OWN
 * state to persist across the trigger, which a fresh `render()` call (new hook
 * cells) cannot exercise.
 */
async function rerender( Component, props ) {
	hooks.dirty = true;
	return settle( Component, props );
}

/* -------------------------------------------------------------------------
 * The wp globals the control declares
 * ---------------------------------------------------------------------- */

function createElement( type, props ) {
	const args = Array.prototype.slice.call( arguments, 2 );
	const children = ( 1 === args.length && Array.isArray( args[ 0 ] ) ) ? args[ 0 ] : args;
	return { type: type, props: props || {}, children: children };
}

const requestedPaths = [];

/** Fixture SERVER: routes a request path to a canned response, by substring. */
const responses = {
	'kind=term&mode=browse': { rows: [] },
};

global.window = {};
global.wp = {
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
	},
	apiFetch: function ( opts ) {
		requestedPaths.push( opts.path );
		let hit = null;
		Object.keys( responses ).forEach( function ( needle ) {
			if ( opts.path.indexOf( needle ) !== -1 ) { hit = responses[ needle ]; }
		} );
		return Promise.resolve( hit || { rows: [] } );
	},
	i18n: {
		__: function ( s ) { return s; },
	},
};
global.window.wp = global.wp;

/* -------------------------------------------------------------------------
 * Load the shipped file
 * ---------------------------------------------------------------------- */

const file = path.join( root, 'assets/js/entity-picker-control.js' );
vm.runInThisContext( fs.readFileSync( file, 'utf8' ), { filename: file } );

const EntityPickerControl = global.window.bwsEntityPickerControl;

if ( ! EntityPickerControl ) {
	console.error( 'window.bwsEntityPickerControl missing — the control did not load' );
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

function selects( tree ) {
	return findAll( tree, 'SelectControl' ).map( function ( n ) { return n.props; } );
}

function labels( optionList ) {
	return ( optionList || [] ).map( function ( o ) { return o.label; } );
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

// One fixture row table, driving every kind this control serves today. A third kind
// (`user`, D12) is a third row here plus nothing else, not a rewrite.
const KINDS = [
	{
		kind: 'term',
		rows: [
			{ id: 5, label: '#5 News', group: 'Category' },
			{ id: 3, label: '#3 Announcements', group: 'Category' },
			{ id: 34, label: '#34 Support', group: 'Benefit Tier' },
		],
	},
	{
		kind: 'post',
		rows: [
			{ id: 5, label: '#5 Hello World', group: 'Post' },
			{ id: 3, label: '#3 A Draft (draft)', group: 'Post' },
			{ id: 34, label: '#34 Support', group: 'Landing Page' },
		],
	},
];

async function main() {
	for ( const fixture of KINDS ) {
		responses[ 'kind=' + fixture.kind + '&mode=browse' ] = { rows: fixture.rows };

		requestedPaths.length = 0;
		const tree = await render( EntityPickerControl, {
			kind: fixture.kind,
			value: '',
			label: 'Term',
			onChange: function () {},
		} );

		check(
			`${fixture.kind}: D17 — opens BROWSABLE on mount, with no typing required first`,
			requestedPaths.length > 0,
			true
		);
		check(
			`${fixture.kind}: …and that first fetch's query is EMPTY, not withheld until a keystroke`,
			requestedPaths[ 0 ].indexOf( 'q=' ) === -1,
			true
		);
		check(
			`${fixture.kind}: the browse fetch names the kind and mode`,
			requestedPaths[ 0 ].indexOf( 'kind=' + fixture.kind ) !== -1 && requestedPaths[ 0 ].indexOf( 'mode=browse' ) !== -1,
			true
		);
		check(
			`${fixture.kind}: D15 — every row shown, ID beside the name, label as-shaped by the route`,
			labels( combo( tree ).options ),
			fixture.rows.map( function ( r ) { return r.label; } )
		);
		check(
			`${fixture.kind}: D21 — the placeholder never reads "ambient"`,
			/ambient|current/i.test( combo( tree ).placeholder || '' ),
			false
		);
		check(
			`${fixture.kind}: D15 — a taxonomy FILTER renders once more than one group is present`,
			selects( tree ).length,
			1
		);
		const expectedGroups = Array.from( new Set( fixture.rows.map( function ( r ) { return r.group; } ) ) ).sort();
		check(
			`${fixture.kind}: …with an "All" row plus one per distinct group, alphabetical`,
			labels( selects( tree )[ 0 ].options ),
			[ 'All' ].concat( expectedGroups )
		);

		// D16: selecting the taxonomy filter narrows the SHOWN list without a second
		// REST request — it is UI state, never sent as a `tax` param.
		requestedPaths.length = 0;
		const filterProps = {
			kind: fixture.kind,
			value: '',
			label: 'Term',
			onChange: function () {},
		};
		const targetGroup = expectedGroups[ 0 ];
		selects( tree )[ 0 ].onChange( targetGroup );
		const filtered = await rerender( EntityPickerControl, filterProps );
		check(
			`${fixture.kind}: D16 — the taxonomy filter narrows client-side…`,
			labels( combo( filtered ).options ),
			fixture.rows.filter( function ( r ) { return targetGroup === r.group; } ).map( function ( r ) { return r.label; } )
		);
		check(
			`${fixture.kind}: …and is NEVER sent as a request parameter`,
			requestedPaths.every( function ( p ) { return -1 === p.indexOf( 'tax=' ); } ),
			true
		);
	}

	// D14: RESOLVE mode reaches the reopen label independently of the browse list —
	// a pin outside the current filter/search still names itself.
	responses[ 'kind=term&mode=resolve&id=34' ] = { row: { id: 34, label: '#34 Support', group: 'Benefit Tier' } };
	requestedPaths.length = 0;
	const withValue = await render( EntityPickerControl, {
		kind: 'term',
		value: '34',
		label: 'Term',
		onChange: function () {},
	} );
	check(
		'a stored value triggers a RESOLVE request for its own label',
		requestedPaths.some( function ( p ) { return p.indexOf( 'mode=resolve' ) !== -1 && p.indexOf( 'id=34' ) !== -1; } ),
		true
	);
	check(
		'D20: the stored id is the combo VALUE, the matching option carrying its name',
		combo( withValue ).value,
		'34'
	);
	check(
		'a pin PRESENT in the list gets NO caption — the combo already reads as its name',
		findAll( withValue, 'p' ).some( function ( n ) { return 'resolved' === n.props.key; } ),
		false
	);

	// The caption's whole job: a pin the browse list does not hold has no option to
	// render, so the combo box comes up empty and only the caption names the entity.
	responses[ 'kind=term&mode=resolve&id=77' ] = { row: { id: 77, label: '#77 Archived', group: 'Benefit Tier' } };
	const offList = await render( EntityPickerControl, {
		kind: 'term',
		value: '77',
		label: 'Term',
		onChange: function () {},
	} );
	check(
		'a pin ABSENT from the list keeps its caption, which is the only place its name shows',
		findAll( offList, 'p' )
			.filter( function ( n ) { return 'resolved' === n.props.key; } )
			.map( function ( n ) { return n.children[ 0 ]; } ),
		[ '#77 Archived' ]
	);

	// D20's "missing" case is the PREVIEW namer's job (preview-label-test.php), not this
	// control's — the picker on reopen with a deleted pin simply has no resolved caption
	// to show, which this covers by omission: no crash, no fabricated label.
	responses[ 'kind=term&mode=resolve&id=999' ] = { row: null };
	requestedPaths.length = 0;
	const missing = await render( EntityPickerControl, {
		kind: 'term',
		value: '999',
		label: 'Term',
		onChange: function () {},
	} );
	check(
		'a deleted pin resolves to no caption, without erroring',
		findAll( missing, 'p' ).some( function ( n ) { return 'resolved' === n.props.key; } ),
		false
	);

	console.log( '' );
	console.log( total + ( fail ? ` run, ${fail} FAILED` : ' passed' ) );
	process.exit( fail ? 1 : 0 );
}

main();
