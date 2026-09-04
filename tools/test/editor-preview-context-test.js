/**
 * Standalone harness for the `generateblocks.editor.preview.context` filter — that what we
 * hand back is IDENTITY-STABLE for a given input context.
 *
 * WHY THIS EXISTS. GB applies that filter inside a block's render and passes the filtered
 * object on to consumers, one of which may use it as a React dependency — so a filter
 * returning a newly built object per call changes that dependency on EVERY render, and the
 * consumer refetches → sets state → renders → refetches, without end. Measured symptom on
 * the fixture site: a Term Query or User Query loop flickering in the editor and console
 * filling with `@wordpress/data`'s "useSelect hook returns different values when called
 * with the same state and parameters".
 *
 * The GB half of that is docs/gb-constraints.md §The editor preview context is filtered
 * during render; the consumer half — which plugin, which hook, which dependency array, at
 * which version — is docs/coresident/gb-query-enhancements.md. Neither is restated here,
 * because both are somebody else's code and have to be re-dated together.
 *
 * `{ ...context, bwsEditorPreview: true }` shipped from 1.6.0 and is exactly that shape.
 * Nothing in the tag corpus is involved — no BWS tag need be present in the loop, because
 * the filter fires on the Looper's own render.
 *
 * The property is a REFERENCE property, so no PHP harness and no page snapshot can see it:
 * the flagged context is byte-identical either way, and every rendered output agrees. Only
 * object identity across two calls tells the two apart, which is what this asserts.
 *
 * IT IS A CENSUS, not a test of one file. Every `.js` under `assets/js/` that registers on
 * the hook is discovered by reading the tree — recursively, so a registrar in a
 * subdirectory cannot escape it — loaded, and held to the same rule, so a second filter
 * added on that hook later is covered by a case nobody wrote. A registrar whose load THROWS
 * against the stub below (calling a `wp` function it does not define) fails the run; one
 * that merely reads an absent property loads quietly, so extending the stub is on whoever
 * adds the file.
 *
 * WHAT THIS DOES NOT COVER: that GB still applies the filter where it does, or that any
 * consumer still uses the result as a dependency. Both are other plugins' internals, held
 * at the two docs named above. If GB stops passing the filtered context on, this stays
 * green and stays right — a filter that returns a new object for an unchanged input is a
 * defect on its own terms.
 *
 * Run:  node tools/test/editor-preview-context-test.js   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

'use strict';

const fs   = require( 'fs' );
const path = require( 'path' );
const vm   = require( 'vm' );

const HOOK = 'generateblocks.editor.preview.context';
const root = path.resolve( __dirname, '../..' );
const jsDir = path.join( root, 'assets/js' );

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

const filters = [];

global.window = {};
global.wp = {
	hooks: {
		addFilter: function ( name, ns, fn, priority ) {
			filters.push( { name: name, ns: ns, fn: fn, priority: priority === undefined ? 10 : priority, seq: filters.length } );
		}
	},
	element: { createElement: function () { return {}; } },
	i18n: { __: function ( s ) { return s; } },
	components: {},
	data: { useSelect: function () { return null; } }
};
global.window.wp = global.wp;

// ── Census: every file that registers on the hook, found by reading the tree ──
const registrars = fs.readdirSync( jsDir, { recursive: true } )
	.filter( f => f.endsWith( '.js' ) )
	.filter( f => fs.readFileSync( path.join( jsDir, f ), 'utf8' ).includes( HOOK ) );

check( 'at least one file registers on ' + HOOK, registrars.length > 0, registrars.join( ', ' ) );

registrars.forEach( function ( file ) {
	const full = path.join( jsDir, file );
	vm.runInThisContext( fs.readFileSync( full, 'utf8' ), { filename: full } );
} );

const hookFilters = filters.filter( f => HOOK === f.name );

check(
	'every registrar file actually registered a callback',
	hookFilters.length >= registrars.length,
	registrars.length + ' file(s), ' + hookFilters.length + ' callback(s)'
);

// ── Per-callback rules ──────────────────────────────────────────────────────
hookFilters.forEach( function ( f ) {
	const ctx = { postId: 12, 'generateblocks/queryType': 'Term_Query' };
	const first = f.fn( ctx, { props: {} } );
	const second = f.fn( ctx, { props: {} } );

	check( f.ns + ': same context object returns the SAME object', first === second );

	check(
		f.ns + ': the input context is not mutated',
		2 === Object.keys( ctx ).length,
		JSON.stringify( ctx )
	);

	check(
		f.ns + ': the input context keys survive',
		12 === first.postId && 'Term_Query' === first[ 'generateblocks/queryType' ]
	);

	// A CHANGED context must still produce a changed object — stability is caching, not
	// freezing. A filter that returned one object forever would pin the first render's
	// context and never see a query type change.
	const other = { postId: 13, 'generateblocks/queryType': 'User_Query' };
	const third = f.fn( other, { props: {} } );
	check( f.ns + ': a different context object returns a different object', third !== first );
	check( f.ns + ': the different context is reflected, not the cached one', 13 === third.postId );
	check( f.ns + ': that one is stable too', third === f.fn( other, { props: {} } ) );

	// Gutenberg passes no `context` prop to a block that declares no `usesContext`, so the
	// filter can be reached with undefined. It must neither throw (a WeakMap key must be an
	// object) nor hand back a fresh object each time.
	let bare1, bare2;
	let threw = false;
	try {
		bare1 = f.fn( undefined, { props: {} } );
		bare2 = f.fn( undefined, { props: {} } );
	} catch ( e ) {
		threw = true;
	}
	check( f.ns + ': an absent context does not throw', ! threw );
	check( f.ns + ': an absent context is stable too', ! threw && bare1 === bare2 );
	// The stability of that path says nothing about it still doing the filter's job.
	check( f.ns + ': an absent context still carries the preview flag', ! threw && true === bare1.bwsEditorPreview );
} );

// ── The chain, which is what GB actually calls ──────────────────────────────
function applyFilters( context ) {
	return hookFilters
		.sort( ( a, b ) => ( a.priority - b.priority ) || ( a.seq - b.seq ) )
		.reduce( ( acc, f ) => f.fn( acc, { props: {} } ), context );
}

const ctx = { postId: 44 };
check(
	'the whole chain is identity-stable across two renders',
	applyFilters( ctx ) === applyFilters( ctx )
);

check(
	'the chain sets the preview flag',
	true === applyFilters( ctx ).bwsEditorPreview
);

console.log( '' );
if ( fail ) {
	console.log( 'FAILED: ' + fail + '/' + ( pass + fail ) );
	process.exit( 1 );
}
console.log( 'PASSED: ' + pass + '/' + pass );
process.exit( 0 );
