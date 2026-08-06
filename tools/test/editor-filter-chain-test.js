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
// The option-group wrapper runs at priority 30, BEHIND both anchored filters — so it is
// the one most exposed to the keyless-wrap failure and the one whose own wrap is
// currently load-bearing for nobody. That is exactly the shape the mechanism sweep at
// the bottom exists for.
load( 'assets/js/option-group.js' );

check(
	'all three invisible controls loaded and registered at the same priority',
	3 === filters.filter( f => 20 === f.priority ).length,
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
	'bws-slot-fold-mount-migrator': 'fold mount migrator',
	'bws-base-src-mount-migrator': 'base src mount migrator'
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
const FOLD = { type: 'bws-slot-fold', fold: { max: 5, combining: false, perSlotUse: true, flatAxes: [ 'src', 'ref', 'srcTermIn', 'use', 'key' ] } };
const ASSIZE = { type: 'bws-as-size' };
const PLAIN = { type: 'text' };
const CHAIN = { type: 'bws-src-chain', fold: { flatAxes: [ 'ref', 'srcTermIn' ] } };

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
		// A BASE tag: the normalizer's anchor (the tag's FIRST option) and the base-src
		// migrator's anchor (the `bws-src-chain` option) are THE SAME ELEMENT. That is
		// the shipped-bug shape again, one depth down — a keyless wrap here switches the
		// other filter off silently.
		label: 'text — the chain control IS the first option, so two filters share one anchor',
		options: { src: CHAIN, use: PLAIN, key: PLAIN, limit: PLAIN, fallback: PLAIN },
		expect: [ 'bws-order-normalizer', 'bws-base-src-mount-migrator' ]
	},
	{
		// The shape where they are DISTINCT — datetime leads with `format`.
		label: 'datetime_single — leads with `format`, so the two anchors differ',
		options: { format: PLAIN, src: CHAIN, key: PLAIN, fallback: PLAIN },
		expect: [ 'bws-order-normalizer', 'bws-base-src-mount-migrator' ]
	},
	{
		label: 'image — no folded slots and no chain control, so only the normalizer fires',
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
const MECHANISM_OPTIONS = {
	as: ASSIZE,
	A: FOLD,
	B: FOLD,
	// Grouped, so the option-group wrapper is in the sweep rather than skipped by it.
	src: Object.assign( { _group: 'source', _group_lead: true }, CHAIN ),
	limit: { type: 'number', _group: 'source' },
	fallback: PLAIN
};
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

// THE DEFAULT ROOT round-trip. `src` is _strip_default, so absence IS `current` — the
// control shows that root (otherwise its step select displays "Current" while holding
// nothing, which is what made picking Current fire no change event and left Add step
// with no step to append to) and convertUpdate writes it back as absence. Both halves
// are asserted, because either alone is a different bug: display without the strip puts
// `src:current` on every tag an author opens, and the strip without the display leaves
// the seed picker exactly as it was.
let rooted = srcChain.convertUpdate( { key: 'name' }, 'src', [ st( 'current' ) ], AXES, 'current' );
check( 'a lone default root is stored as ABSENCE', undefined === rooted.src, JSON.stringify( rooted.src ) );
check( 'stripping the root leaves the rest of the tag alone', 'name' === rooted.key );
rooted = srcChain.convertUpdate( {}, 'src', [ st( 'current' ), st( 'terms', 'department' ) ], AXES, 'current' );
check( 'a default root with a step behind it is written in full', 'current;terms,department' === rooted.src, JSON.stringify( rooted.src ) );
rooted = srcChain.convertUpdate( {}, 'src', [ st( 'site' ) ], AXES, 'current' );
check( 'a NON-default root is written', 'site' === rooted.src, JSON.stringify( rooted.src ) );
// The other half of the round trip: what the control SHOWS for a stored value. Kept
// separate from chainFromOptions(), which is the twin of the PHP reader and must stay
// byte-faithful to it — the default root is a display decision, not a wire one.
[
	[ 'bare tag shows the default root', {}, 'current' ],
	[ 'an explicit root is shown as itself', { src: 'site' }, 'site' ],
	[ 'chain wire is shown as parsed', { src: 'refs,office' }, 'refs:office' ],
	// A legacy term step with no `src` is NOT bare: it already has a chain, so the
	// default root must not be prepended in front of it.
	[ 'a legacy step suppresses the default root', { srcTermIn: 'department' }, 'terms:department' ]
].forEach( function ( row ) {
	check(
		'displayChain — ' + row[ 0 ],
		shape( srcChain.displayChain( row[ 1 ], 'current' ) ) === row[ 2 ],
		'got ' + JSON.stringify( shape( srcChain.displayChain( row[ 1 ], 'current' ) ) ) + ', want ' + JSON.stringify( row[ 2 ] )
	);
} );
check(
	'with no default root registered, display is the faithful reading',
	'' === shape( srcChain.displayChain( {}, '' ) )
);

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
		{ src: { type: 'bws-src-chain', fold: { srcRows: [], stepRows: [], slugMap: {}, taxonomies: [], flatAxes: AXES } } },
		{ state: { src: 'current' }, setState: function () {} }
	),
	'element returned'
);

// ── Visual grouping (option-group.js) ───────────────────────────────────────
//
// The wrapper is presentation, so most of it is not assertable here. Three things are,
// and all three have bitten: it must not wrap what the conditional gate HID (a box with
// nothing in it), it must not wrap an option the tag did not register (the map is keyed
// by name, and GB core tags have a `key` and a `source` too — which is why the marker
// rides on the option DEFINITION rather than on a name list in JS), and the generated
// CSS must carry a join rule per group (the attribute selector cannot say "same value as
// my sibling", so a missing group name silently means no joining, i.e. N stacked boxes).
console.log( '\noption grouping\n' );

const GROUPED = {
	src:      { type: 'bws-src-chain', fold: { srcRows: [], stepRows: [], slugMap: {}, taxonomies: [], flatAxes: [] }, _group: 'source', _group_lead: true },
	limit:    { type: 'number', _group: 'source', show_if_any: { src: [ 'ref', 'chain_fans' ] } },
	use:      { type: 'select', _group: 'field', _group_lead: true },
	// A lead too, and the reason is `{{email}}`/`{{phone}}`/`{{table}}`: the field key is
	// their whole read, with no `use` enum in front of it, so a lone-member opt-out would
	// leave the simplest reads as the only ones with no field group.
	key:      { type: 'bws-field-combo', _group: 'field', _group_lead: true },
	fallback: { type: 'text' }
};

function wrapped( name, state ) {
	return applyFilters( { key: name, type: 'control' }, GROUPED, { state: state || {}, setState: function () {} } );
}

const srcOut = wrapped( 'src' );
check( 'a grouped option is wrapped in a group member', 'div' === srcOut.type, JSON.stringify( srcOut.type ) );
check( 'the member names its group', 'source' === srcOut.props[ 'data-bws-group' ], JSON.stringify( srcOut.props[ 'data-bws-group' ] ) );
check( 'the LEAD member is marked (it stays boxed when alone)', /bws-optgroup--lead/.test( srcOut.props.className ), srcOut.props.className );
// The field key leads its own group — the `{{email}}`/`{{phone}}` shape, where it is the
// only member and the group must still show.
check( 'a lone field key leads its group', /bws-optgroup--lead/.test( wrapped( 'key', { src: 'refs,x' } ).props.className ), wrapped( 'key' ).props.className );
check( 'a non-lead member is not marked', ! /--lead/.test( wrapped( 'limit', { src: 'refs,office' } ).props.className ), wrapped( 'limit', { src: 'refs,office' } ).props.className );
check( 'an ungrouped option is left alone', 'control' === wrapped( 'fallback' ).type );

// `limit` is hidden until the source fans. The gate runs at 10 and returns null; a
// wrapper that boxed it anyway would draw an empty bordered strip on every base tag.
check( 'a HIDDEN option is not wrapped', null === wrapped( 'limit' ), JSON.stringify( wrapped( 'limit' ) ) );
check( 'the same option IS wrapped once revealed', 'div' === wrapped( 'limit', { src: 'refs,office' } ).type );

// An option the tag never registered: no definition, so no marker, so no box —
// the reason grouping is stamped at registration instead of matched by name in JS.
check(
	'an unregistered option name is never wrapped',
	'control' === applyFilters( { key: 'key', type: 'control' }, { source: PLAIN, key: undefined }, { state: {}, setState: function () {} } ).type
);

const css = global.window.bwsOptionGroup.buildCss( [ 'source', 'field' ] );
[ 'source', 'field' ].forEach( function ( name ) {
	const sel = '.bws-optgroup[data-bws-group="' + name + '"]';
	check( '`' + name + '` gets a continuation rule', css.indexOf( sel + '+' + sel ) !== -1 );
	check( '`' + name + '` gets a lone-member opt-out', css.indexOf( ':not(.bws-optgroup--lead)' ) !== -1 );
} );

console.log( '' );
if ( fail ) {
	console.log( 'FAILED: ' + fail + '/' + ( pass + fail ) );
	process.exit( 1 );
}
console.log( 'PASSED: ' + pass + '/' + pass );
process.exit( 0 );
