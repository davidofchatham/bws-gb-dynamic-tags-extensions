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
	i18n: { __: function ( s ) { return s; }, sprintf: function ( s, a ) { return String( s ).replace( '%s', a ); } },
	// Identity stubs — the components are never rendered here, only referenced as
	// element types. The slot-fold control and the src-chain control both bail out
	// when wp.components is absent, so omitting these silently loads nothing.
	components: { SelectControl: {}, TextControl: {}, Button: {}, ComboboxControl: {}, Flex: {}, FlexItem: {} }
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

// ── The list-mode reveal, across both source spellings (FW-63) ──────────────
//
// `limit` and `sep` are revealed by a show_if_any that named the two TOKENS which
// used to be the only fanning spellings (`srcTermIn` not empty, `src` = `ref`).
// Chain wire is neither, so both controls vanished the moment a source was spelled
// as a chain. That read as the right outcome while chain wire had no cap of its
// own; it is wrong now that chain wire defaults to unlimited and a migrated tag
// carries an explicit `limit:1` — a control that does not render cannot be cleared.
//
// The predicate is loaded from the shipping file and driven through the real filter
// chain, because a visibility rule is a property of the CHAIN (a filter ahead of it
// that nulls an anchor switches it off) rather than of the predicate alone.
console.log( '\nlist-mode reveal — flat and chain spellings answer alike\n' );

load( 'assets/js/editor-conditional-options.js' );

const LIST_OPTIONS = {
	src:       PLAIN,
	ref:       PLAIN,
	srcTermIn: { type: 'bws-term-hop' },
	limit:     { type: 'number', show_if_any: { srcTermIn: 'not_empty', src: [ 'ref', 'chain_fans' ] } },
	sep:       { type: 'text', show_if_any: { srcTermIn: 'not_empty', src: [ 'ref', 'chain_fans' ] } },
	key:       PLAIN
};

function limitVisible( state ) {
	return null !== applyFilters( { key: 'limit', type: 'number' }, LIST_OPTIONS, { state: state, setState: function () {} } );
}

[
	// [ label, state, expected visible ]
	[ 'bare tag — nothing fans', {}, false ],
	[ 'src:current — nothing fans', { src: 'current' }, false ],
	[ 'src:site — nothing fans', { src: 'site' }, false ],
	[ 'FLAT src:ref', { src: 'ref', ref: 'office' }, true ],
	[ 'FLAT srcTermIn', { srcTermIn: 'department' }, true ],
	[ 'CHAIN src:refs,office', { src: 'refs,office' }, true ],
	[ 'CHAIN src:terms,department', { src: 'terms,department' }, true ],
	[ 'CHAIN src:refs,office;terms,department', { src: 'refs,office;terms,department' }, true ],
	// A bare fanning slug IS a one-hop chain, and a site ROOT that then hops fans.
	[ 'CHAIN src:refs (argless one-hop)', { src: 'refs' }, true ],
	[ 'CHAIN src:site;refs,partner', { src: 'site;refs,partner' }, true ],
	// A registry-source root is root-only: no hop, no list mode. This is the row
	// that catches a predicate widened to "anything unrecognised".
	[ 'a registry source root does NOT fan', { src: 'related_post' }, false ]
].forEach( function ( row ) {
	const shown = limitVisible( row[ 1 ] );
	check(
		'`limit` ' + ( row[ 2 ] ? 'shows' : 'hides' ) + ' — ' + row[ 0 ],
		shown === row[ 2 ],
		'state = ' + JSON.stringify( row[ 1 ] )
	);
} );

// An ARRAY condition entry is a full condition, not only a literal — which is what
// lets one key carry `[ 'ref', 'chain_fans' ]` at all, since show_if_any is keyed BY
// OPTION and two rules about `src` cannot be two entries. The literal half must keep
// behaving exactly as it did.
check(
	'array conditions still match a plain literal (back-compat)',
	null !== applyFilters(
		{ key: 'sep', type: 'text' },
		{ sep: { type: 'text', show_if_any: { src: [ 'ref', 'site' ] } } },
		{ state: { src: 'site' }, setState: function () {} }
	),
	'literal entry inside an array condition'
);

// ── The base-tag source chain: reading legacy keys, and what a conversion writes ──
//
// TWIN ASSERTIONS. chainFromOptions() is the JS half of bws_fold_chain_from_options(),
// and the renderer reads that rule from PHP while this control writes wire against
// it — so a divergence does not fail loudly, it stores a source the renderer reads
// differently. The rows below mirror §C8 of fold-chain-compile-test.php one for one.
console.log( '\nbase-tag source chain — legacy reading + conversion\n' );

load( 'assets/js/slot-fold-control.js' );
load( 'assets/js/src-chain-control.js' );

const srcChain = global.window.bwsSrcChain;
check( 'the src-chain control loaded', !! srcChain );

/** Compact a parsed chain to `slug:arg` pairs, for readable expectations. */
function shape( chain ) {
	return chain.map( s => s.slug + ( s.arg ? ':' + s.arg : '' ) ).join( ';' );
}

[
	[ 'bare tag', {}, '' ],
	[ 'src:current', { src: 'current' }, 'current' ],
	[ 'src:site', { src: 'site' }, 'site' ],
	[ 'FLAT src:ref + ref', { src: 'ref', ref: 'office' }, 'refs:office' ],
	[ 'FLAT srcTermIn alone', { srcTermIn: 'department' }, 'terms:department' ],
	[ 'FLAT ref + srcTermIn compound, in that order', { src: 'ref', ref: 'office', srcTermIn: 'department' }, 'refs:office;terms:department' ],
	// An orphan `src:ref` is already dead at render; the incomplete step preserves
	// that rather than inventing a source out of the author's mistake.
	[ 'orphan src:ref (no field) still contributes a step', { src: 'ref' }, 'refs' ],
	// A SITE root never takes the legacy term hop — the pair is hand-edit only and
	// every arm has always let the site read win.
	[ 'src:site + srcTermIn keeps the site read', { src: 'site', srcTermIn: 'department' }, 'site' ],
	[ 'CHAIN wire parses as itself', { src: 'refs,office;terms,department' }, 'refs:office;terms:department' ],
	// Malformed chain wire falls back to the legacy reading (the raw value as a root
	// token), which resolves the ambient entity — never a fabricated hop.
	[ 'malformed chain wire falls back to a root token', { src: 'refs,office;;[' }, 'refs,office;;[' ]
].forEach( function ( row ) {
	check(
		'chainFromOptions — ' + row[ 0 ],
		shape( srcChain.chainFromOptions( row[ 1 ] ) ) === row[ 2 ],
		'got ' + JSON.stringify( shape( srcChain.chainFromOptions( row[ 1 ] ) ) ) + ', want ' + JSON.stringify( row[ 2 ] )
	);
} );

// ── The conversion ──────────────────────────────────────────────────────────
//
// Asserted at the pure function that MAKES the decision, because it is not
// observable in anything this control renders: the author sees a number appear in
// the `limit` field, and a test watching that field would be asserting the limit
// control's behaviour rather than this one's.
const AXES = [ 'ref', 'srcTermIn' ];
const st = srcChain.step;

function convert( state, chain ) {
	return srcChain.convertUpdate( state, 'src', chain, AXES );
}

let out = convert( { src: 'ref', ref: 'office', key: 'name' }, [ st( 'refs', 'office' ) ] );
check( 'conversion writes chain wire', 'refs,office' === out.src, JSON.stringify( out.src ) );
check( 'conversion deletes the flat siblings it absorbed', undefined === out.ref, JSON.stringify( out.ref ) );
check( 'conversion leaves unrelated options alone', 'name' === out.key );
// THE LOAD-BEARING ROW. Chain wire defaults its cap to unlimited, so a conversion
// that wrote nothing would fan the tag out under the author's hands -- extra values,
// and dropped anchors, since the link gate is count-based.
check( 'conversion serializes the cap the old spelling implied', '1' === out.limit, JSON.stringify( out.limit ) );

out = convert( { src: 'ref', ref: 'office', limit: '3' }, [ st( 'refs', 'office' ) ] );
check( 'an author-stated limit survives the conversion', '3' === out.limit, JSON.stringify( out.limit ) );

out = convert( { src: 'current' }, [ st( 'current' ) ] );
check( 'a root-only chain gets NO cap — there is nothing to cap', undefined === out.limit, JSON.stringify( out.limit ) );

// Re-committing an ALREADY-chain tag is not a conversion, so it must not re-inject
// a cap the author has since cleared.
out = convert( { src: 'refs,office', limit: '' }, [ st( 'refs', 'office' ), st( 'terms', 'department' ) ] );
check( 'editing a chain tag does not re-inject the cap', '' === out.limit, JSON.stringify( out.limit ) );
check( 'the appended hop is written', 'refs,office;terms,department' === out.src, JSON.stringify( out.src ) );

out = convert( { src: 'ref', ref: 'office' }, [] );
check( 'clearing the chain DELETES the key (delete-omit)', undefined === out.src, JSON.stringify( out.src ) );

// A per-step cap round-trips at enclosing level 0 — the base tag's `src:` IS the
// wrapper, so the cap prints one level inside it and comes out in PARENS, matching
// the hand-authored `{{phone src:refs,related_staff,limit(1)}}` the fold matrix
// already pins. A slot's `src(...)` passes 1 and gets brackets; recomputing depth
// locally is what both shipped bugs on this axis did.
out = convert( { src: 'refs,office' }, [ st( 'refs', 'office', 2 ) ] );
check( 'a per-step cap emits at the base tag\'s depth', 'refs,office,limit(2)' === out.src, JSON.stringify( out.src ) );

check(
	'the control mounts for a `bws-src-chain` option',
	null !== applyFilters(
		{ key: 'src', type: 'control' },
		{ src: { type: 'bws-src-chain', fold: { srcRows: [], hopRows: [], slugMap: {}, taxonomies: [], legacyAxes: AXES } } },
		{ state: { src: 'current' }, setState: function () {} }
	),
	'element returned'
);

console.log( '' );
if ( fail ) {
	console.log( 'FAILED: ' + fail + '/' + ( pass + fail ) );
	process.exit( 1 );
}
console.log( 'PASSED: ' + pass + '/' + pass );
process.exit( 0 );
