/**
 * Standalone harness for the tagSpecificControls filter CHAIN — specifically, that the
 * plugin's invisible per-tag controls all still fire when they stack on ONE element.
 *
 * WHY THIS EXISTS. GB fires `generateblocks.editor.tagSpecificControls` once per option
 * and keys each control element by its option name. Two of our filters run at priority 20
 * and each anchors on `element.key`:
 *
 *   serialization-order-normalizer.js  → the tag's FIRST option
 *   slot-fold-migrate.js               → the first `bws-slot-fold` option
 *
 * Those anchors COINCIDE on real tags. Measured against live registration (1.17.0),
 * `{{join}}` and the six `try_` templates with no leading options — text, content, title,
 * permalink, email, phone — all have a folded slot as their FIRST option, so both filters
 * target the same element. When two filters wrap one element, the outer wrapper IS what
 * the next filter inspects, so a wrapper built with `createElement(Fragment, null, ...)`
 * nulls the key and every later anchor silently misses.
 *
 * That is not hypothetical: it shipped. The normalizer's keyless wrap switched mount
 * migration off for exactly those seven tags while leaving `try_image` /
 * `try_datetime_single` / `try_datetime_range` working (they lead with `as`, so the
 * anchors differ) — which is why the failure read as flaky rather than broken, and why it
 * survived review. A rule stated in two file comments is a rule two files can drift from;
 * this asserts it once, against the shipping files, for every anchor combination that
 * actually occurs.
 *
 * WHAT THIS DOES NOT COVER: React batching, GB's real element tree, or whether a migration
 * produces the right VALUE (fold-migration-test.php §M7 owns that). This is reachability.
 *
 * NO as+size ENTRY, deliberately: the as+size fold has no mount migrator and cannot have
 * one. `size` is a GB-reserved key, destructured into GB-private `imageSize` state before
 * `extraTagParams` exists, so no control can read or clear it (docs/gb-constraints.md
 * §Reserved keys are destructured into GB-private state). It is Tag-Converter-only by
 * construction, not by omission.
 *
 * Run:  node tools/test/editor-filter-chain-test.js   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const root = path.resolve( __dirname, '../..' );

let pass = 0;
let fail = 0;

function check( label, ok, detail ) {
	if ( ok ) {
		pass++;
		console.log( '  ok   ' + label );
		return;
	}
	fail++;
	console.log( '  FAIL ' + label );
	if ( detail !== undefined ) {
		console.log( '       ' + detail );
	}
}

// ── A stub wp surface that models the two things the bug turned on ──────────
// 1. addFilter honours PRIORITY, ties broken by registration order (= enqueue order).
// 2. createElement(type, props, ...children) puts props.key on the element, and a null
//    props leaves key undefined — exactly what a keyless Fragment wrap does.
const filters = [];
let effects = [];

global.window = {};
global.wp = {
	hooks: {
		addFilter: function ( name, ns, fn, priority ) {
			filters.push( { name: name, ns: ns, fn: fn, priority: priority === undefined ? 10 : priority, seq: filters.length } );
		}
	},
	element: {
		Fragment: 'Fragment',
		createElement: function ( type, props ) {
			const children = Array.prototype.slice.call( arguments, 2 );
			return {
				type: type,
				key: ( props && props.key !== undefined ) ? props.key : undefined,
				props: props || {},
				children: children
			};
		},
		// Effects are queued and flushed once per sweep, so a control that never renders
		// never runs its effect — which is the reachability question.
		useEffect: function ( fn ) { effects.push( fn ); },
		useState: function ( initial ) { return [ initial, function () {} ]; }
	},
	i18n: { __: function ( s ) { return s; } }
};
global.window.wp = global.wp;

function load( relative ) {
	const file = path.join( root, relative );
	vm.runInThisContext( fs.readFileSync( file, 'utf8' ), { filename: file } );
}

// Enqueue order — which is what breaks the priority-20 tie, and therefore which filter
// wraps first. The grammar comes first because slot-fold-migrate.js hard-depends on it
// and the normalizer decodes slot keys through it.
load( 'assets/js/slot-fold-grammar.js' );
load( 'assets/js/serialization-order-normalizer.js' );
load( 'assets/js/slot-fold-migrate.js' );

check(
	'both invisible controls loaded and registered at the same priority',
	2 === filters.filter( f => 20 === f.priority ).length,
	filters.map( f => f.ns + '@' + f.priority ).join( ', ' )
);

function applyFilters( element, allOptions, context ) {
	return filters
		.filter( f => 'generateblocks.editor.tagSpecificControls' === f.name )
		.sort( ( a, b ) => ( a.priority - b.priority ) || ( a.seq - b.seq ) )
		.reduce( ( el, f ) => f.fn( el, allOptions, context ), element );
}

// Identified by the `key` each component gives its own element — those strings are the
// components' own labels, not this file's invention.
const INVISIBLE = {
	'bws-order-normalizer': 'order normalizer',
	'bws-slot-fold-mount-migrator': 'fold mount migrator'
};

/** Sweep every option through the chain and report which invisible controls ran. */
function renderTag( allOptions ) {
	effects = [];
	const seen = {};
	const context = { state: {}, setState: function () {} };

	Object.keys( allOptions ).forEach( function ( name ) {
		const out = applyFilters( { key: name, type: 'control' }, allOptions, context );
		( function walk( node ) {
			if ( ! node || 'object' !== typeof node ) { return; }
			if ( node.props && INVISIBLE[ node.props.key ] ) {
				seen[ node.props.key ] = ( seen[ node.props.key ] || 0 ) + 1;
			}
			( node.children || [] ).forEach( walk );
		}( out ) );
	} );

	effects.forEach( fn => fn() );
	return seen;
}

// ── The anchor combinations that actually occur (live registration, 1.17.0) ──
const FOLD = { type: 'bws-slot-fold', fold: { max: 5, combining: false, perSlotUse: true, legacyAxes: [ 'src', 'ref', 'srcTermIn', 'use', 'key' ] } };
const ASSIZE = { type: 'bws-as-size' };
const PLAIN = { type: 'text' };

const CASES = [
	{
		// The shipped-bug shape. try_text / try_content / try_title / try_permalink /
		// try_email / try_phone all look like this.
		label: 'try_text — first option IS the first folded slot (both filters share one anchor)',
		options: { A: FOLD, B: FOLD, fallback: PLAIN },
		expect: [ 'bws-order-normalizer', 'bws-slot-fold-mount-migrator' ]
	},
	{
		label: 'join — same shared anchor, with tag-level options trailing it',
		options: { A: FOLD, B: FOLD, mode: PLAIN, valueSep: PLAIN, format: PLAIN },
		expect: [ 'bws-order-normalizer', 'bws-slot-fold-mount-migrator' ]
	},
	{
		// The shape that kept working through the bug, which is why it read as flaky.
		label: 'try_image — leads with `as`, so the two anchors are DISTINCT elements',
		options: { as: ASSIZE, A: FOLD, B: FOLD, fallback: PLAIN },
		expect: [ 'bws-order-normalizer', 'bws-slot-fold-mount-migrator' ]
	},
	{
		label: 'image — no folded slots at all, so only the normalizer fires',
		options: { src: PLAIN, use: PLAIN, key: PLAIN, as: ASSIZE, fallback: PLAIN },
		expect: [ 'bws-order-normalizer' ]
	},
	{
		label: 'a GB core tag (no bws- types) — the normalizer gate holds, nothing fires',
		options: { source: PLAIN, key: PLAIN },
		expect: []
	}
];

console.log( '\nfilter-chain reachability\n' );

CASES.forEach( function ( c ) {
	const seen = renderTag( c.options );
	c.expect.forEach( function ( key ) {
		check(
			c.label + ' → ' + INVISIBLE[ key ] + ' fires',
			1 === seen[ key ],
			'fired ' + ( seen[ key ] || 0 ) + ' time(s)'
		);
	} );
	Object.keys( INVISIBLE ).forEach( function ( key ) {
		if ( -1 === c.expect.indexOf( key ) ) {
			check(
				c.label + ' → ' + INVISIBLE[ key ] + ' stays out',
				undefined === seen[ key ],
				'fired ' + ( seen[ key ] || 0 ) + ' time(s)'
			);
		}
	} );
} );

// The MECHANISM, asserted directly rather than only through its symptom.
//
// Every wrapper this plugin adds must carry the wrapped element's key forward, or the next
// anchor in the chain cannot find it. Asserting it directly matters because the
// reachability cases above only catch a keyless wrap where some OTHER filter happens to
// anchor on the same element today: the fold migrator's own wrap is currently load-bearing
// for nobody, so a keyless regression there would sail through them. It stops being
// harmless the moment a third filter anchors on a folded slot — which is exactly how this
// bug arrived the first time. So: sweep EVERY anchor, not just the contested one.
console.log( '' );
const MECHANISM_OPTIONS = { as: ASSIZE, A: FOLD, B: FOLD, fallback: PLAIN };
Object.keys( MECHANISM_OPTIONS ).forEach( function ( name ) {
	const wrapped = applyFilters( { key: name, type: 'control' }, MECHANISM_OPTIONS, { state: {}, setState: function () {} } );
	check(
		'`' + name + '` still carries its option key after every wrap',
		name === wrapped.key,
		'key = ' + JSON.stringify( wrapped.key )
	);
} );

console.log( '' );
if ( fail ) {
	console.log( 'FAILED: ' + fail + '/' + ( pass + fail ) );
	process.exit( 1 );
}
console.log( 'PASSED: ' + pass + '/' + pass );
process.exit( 0 );
