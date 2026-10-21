/**
 * Harness for the {{table}} Live Formatting Preview (assets/js/table-style-preview.js): what it
 * draws from a tag's state, what it keeps out of the tag string, and the accessibility contract
 * of the thumbnail and its Sticky switch. Run: `node tools/test/table-style-preview-test.js`
 * (exits non-zero on failure).
 *
 * The rules themselves are the grammar twin's and are held to PHP by table-style-twin-test.php;
 * this file pins what the preview does WITH them. Not reachable here, so measured in the editor
 * ("Measure at build"): sticky inside the real GB modal, the stacking of an
 * open suggestion list over the preview, the pinned/compact measurement.
 *
 * @package BWS_Dynamic_Tags
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );
const { execFileSync } = require( 'child_process' );

const root = path.resolve( __dirname, '../..' );
const vocab = JSON.parse( execFileSync( 'php', [ '-r', "define('ABSPATH',1); require 'includes/helpers/table-helpers.php'; echo json_encode(bws_table_style_vocabulary());" ], { cwd: root } ).toString() );

const filters = [];
let storage = { value: null, throws: false };
global.window = {
	bwsTableVocab: vocab,
	bwsTableDefaults: { stripeColor: 'currentColor', stripeOpacity: 5 },
	localStorage: {
		getItem: function () {
			if ( storage.throws ) {
				throw new Error( 'blocked' );
			}
			return storage.value;
		},
		setItem: function ( k, v ) {
			if ( storage.throws ) {
				throw new Error( 'blocked' );
			}
			storage.value = v;
		}
	}
};
global.wp = {
	hooks: { addFilter: function ( hook, ns, fn ) { filters.push( fn ); } },
	element: {
		createElement: function ( type, props ) {
			const kids = Array.prototype.slice.call( arguments, 2 );
			return { type: type, props: props || {}, children: kids };
		},
		Fragment: 'F',
		useState: function ( initial ) { return [ typeof initial === 'function' ? initial() : initial, function ( v ) { global.lastSet = v; } ]; },
		useRef: function () { return { current: null }; },
		useEffect: function () {}
	},
	components: { ComboboxControl: 'Combo', SelectControl: 'Select', TextControl: 'Text', Button: 'Button' },
	data: { select: function () { return { getSettings: function () { return { colors: [] }; } }; } },
	i18n: { __: function ( s ) { return s; } }
};
global.window.wp = global.wp;

function load( relative ) {
	const file = path.join( root, relative );
	vm.runInThisContext( fs.readFileSync( file, 'utf8' ), { filename: file } );
}
load( 'assets/js/serialization-order-normalizer.js' );
load( 'assets/js/slot-fold-grammar.js' );
load( 'assets/js/table-style-grammar.js' );
load( 'assets/js/table-style-control.js' );
load( 'assets/js/table-style-preview.js' );

const P = global.window.bwsTablePreview;
if ( ! P ) {
	console.error( 'preview did not load' );
	process.exit( 2 );
}

let fail = 0;
let total = 0;
function check( label, got, want ) {
	total++;
	const ok = JSON.stringify( got ) === JSON.stringify( want );
	if ( ! ok ) {
		fail++;
	}
	console.log( ( ok ? 'PASS  ' : 'FAIL  ' ) + label + ( ok ? '' : '\n        got:  ' + JSON.stringify( got ) + '\n        want: ' + JSON.stringify( want ) ) );
}

function expand( node ) {
	if ( node === null || node === undefined || typeof node !== 'object' ) {
		return node;
	}
	if ( Array.isArray( node ) ) {
		return node.map( expand );
	}
	if ( typeof node.type === 'function' ) {
		return expand( node.type( Object.assign( {}, node.props, { children: node.children } ) ) );
	}
	return { type: node.type, props: node.props, children: [].concat( ...( node.children || [] ).map( ( c ) => ( Array.isArray( c ) ? c : [ c ] ) ) ).map( expand ) };
}

function find( tree, pred, out ) {
	out = out || [];
	if ( Array.isArray( tree ) ) {
		tree.forEach( ( n ) => find( n, pred, out ) );
	} else if ( tree && typeof tree === 'object' ) {
		if ( pred( tree ) ) {
			out.push( tree );
		}
		( tree.children || [] ).forEach( ( n ) => find( n, pred, out ) );
	}
	return out;
}

const D = { color: 'currentColor', opacity: 5 };
const m = ( state ) => P.model( state, 10, D );

// ── What it draws ────────────────────────────────────────────────────────────────────
let model = m( {} );
check( 'no columns: three sample columns, with a header row, and no rules', [ model.columns.length, model.hasHeader, model.css ], [ 3, true, '' ] );

model = m( { A: 'header(Name),key(sku)', B: 'key(price)' } );
check( 'columns are the slots with a header or a read, in order', model.columns.map( ( c ) => c.header ), [ 'Name', '' ] );
check( 'a header on any column makes a header row', model.hasHeader, true );

model = m( { A: 'key(sku)', B: 'key(price)' } );
check( 'no header on any column: no header row (stripes then count from the first row)', model.hasHeader, false );

model = m( { A: 'header( ),width(20%)', B: 'width(20%)', C: 'key(c)' } );
check( 'a slot with no header and no read is no column', model.columns.length, 1 );

model = m( { A: 'header(X', B: 'key(price)' } );
check( 'wire that does not parse is left out', model.columns.length, 1 );

model = m( { layout: 'fixed' } );
check( 'layout:fixed alone: fixed layout and the width it brings, under the preview scope', model.css, '.bws-tp-table{table-layout:fixed;width:100%}' );

model = m( { width: '400px', fontSize: '2rem', color: '#c00' } );
check( 'width is rescaled to the box, font size left out, color kept', model.css, '.bws-tp-table{width:50%}.bws-tp-table :is(th,td){color:#c00}' );

model = m( { width: 'var(--w)', layout: 'auto' } );
check( 'a var() width cannot resolve against a nominal viewport and is left out', model.css, '.bws-tp-table{table-layout:auto}' );

model = m( { width: '1px' } );
check( 'a tiny table width is held at 20% of the box', model.css, '.bws-tp-table{width:20%}' );

model = m( { A: 'header(N),key(a),width(8rem)', B: 'header(P),key(b),width(auto)', C: 'header(Q),key(c),width(var(--c))', D: 'header(R),key(d),width(30%)' } );
check( 'column widths: lengths become a share of the table, auto and var() drop, % stays', model.css, '.bws-tp-table :is(th,td):nth-child(1){width:16%}.bws-tp-table :is(th,td):nth-child(4){width:30%}' );

model = m( { stripe: 'rows' } );
check( 'stripes come from the rule builder (header row counted)', model.css.indexOf( ' tbody tr:nth-child(odd)' ) !== -1, true );

model = m( { border: 'rows,2px', borderRadius: 'outer,8px' } );
check( 'borders come from the rule builder, border widths untouched by the width fit', model.css.indexOf( '.bws-tp-table tbody tr:not(:last-child) td{border-bottom-width:2px;border-bottom-style:solid}' ) !== -1, true );
check( 'the radius and its clip come through', [ model.css.indexOf( 'border-radius:8px' ) !== -1, model.css.indexOf( '.bws-tp-table tbody{clip-path:inset(0 round 8px)}' ) !== -1 ], [ true, true ] );
check( 'no border key, no border rule (the theme baseline shows)', m( {} ).css.indexOf( 'border' ), -1 );

model = m( { A: 'header(P),key(x),textAlign(right;header,left)', textAlign: 'center' } );
check( 'a column alignment overrides the table level, body and header separately', model.css, '.bws-tp-table :is(th,td){text-align:center}.bws-tp-table tbody td:nth-child(1){text-align:right}.bws-tp-table thead th:nth-child(1){text-align:left}' );

// ── The tag string is never touched ──────────────────────────────────────────────────
const writes = [];
const state = { layout: 'fixed' };
const filter = filters[ filters.length - 1 ];
const options = { stylePreview: { type: 'bws-table-style-preview', label: 'Live Formatting Preview' }, A: { fold: { max: 10 } } };
const el = filter( { key: 'stylePreview' }, options, { state: state, setState: ( s ) => writes.push( s ) } );
check( 'the filter replaces only the preview option', typeof el.type === 'function', true );
check( 'other options pass through', filter( { key: 'layout' }, { layout: { type: 'bws-table-sizing' } }, {} ) !== null, true );
check( 'an option of another type is returned as given', filter( 'sentinel', { x: { type: 'text' } }, { state: {} } ), 'sentinel' );

// ── Accessibility and the Sticky switch ──────────────────────────────────────────────
let tree = expand( el );
const img = find( tree, ( n ) => n.props && n.props.role === 'img' )[ 0 ];
check( 'the preview is an image with a text alternative', [ !! img, ( img.props[ 'aria-label' ] || '' ) !== '' ], [ true, true ] );
const sw = find( tree, ( n ) => n.type === 'input' && n.props.role === 'switch' )[ 0 ];
check( 'the Sticky switch is a native checkbox named "Toggle sticky preview"', [ sw.props.type, sw.props[ 'aria-label' ] ], [ 'checkbox', 'Toggle sticky preview' ] );
check( 'the switch has a visible "Sticky" label first (WCAG 2.5.3: the name contains it)', find( tree, ( n ) => n.type === 'label' )[ 0 ].children[ 0 ], 'Sticky' );
check( 'sticky is on by default (nothing stored)', find( tree, ( n ) => n.props && /is-sticky/.test( n.props.className || '' ) ).length, 1 );

sw.props.onChange( { target: { checked: false } } );
check( 'turning it off persists "0"', storage.value, '0' );
check( 'and updates the control', global.lastSet, false );
check( 'the tag string is never written', writes, [] );

tree = expand( filter( { key: 'stylePreview' }, options, { state: state, setState: () => {} } ) );
check( 'a stored "0" renders it off', find( tree, ( n ) => n.props && /is-sticky/.test( n.props.className || '' ) ).length, 0 );

storage = { value: null, throws: true };
let threw = false;
try {
	tree = expand( filter( { key: 'stylePreview' }, options, { state: state, setState: () => {} } ) );
	find( tree, ( n ) => n.type === 'input' && n.props.role === 'switch' )[ 0 ].props.onChange( { target: { checked: true } } );
} catch ( e ) {
	threw = true;
}
check( 'storage failure throws nothing', threw, false );
check( 'storage failure leaves sticky on', find( tree, ( n ) => n.props && /is-sticky/.test( n.props.className || '' ) ).length, 1 );

console.log( '\n' + ( fail ? 'FAILED: ' + fail + '/' + total : 'PASSED: ' + total + '/' + total ) );
process.exit( fail ? 1 : 0 );
