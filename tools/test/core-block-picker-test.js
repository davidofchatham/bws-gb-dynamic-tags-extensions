/**
 * Harness for the settings-page block list script (assets/js/block-picker.js): the two pure
 * helpers it exposes, the search match and the unsaved-changes identity. Run:
 * `node tools/test/core-block-picker-test.js` (exits non-zero on failure).
 *
 * The DOM wiring (counter, hiding rows and groups, beforeunload, Enter in the search box) is
 * not reachable here; it is measured in the browser on the testbed.
 *
 * @package BWS_Dynamic_Tags
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const src = fs.readFileSync( path.resolve( __dirname, '../../assets/js/block-picker.js' ), 'utf8' );
global.window = {};
vm.runInThisContext( src );
const { matches, setKey } = global.window.bwsBlockPicker;

let failures = 0;
let count = 0;
function check( label, expected, actual ) {
	count++;
	if ( expected === actual ) {
		console.log( '  ok   ' + label );
		return;
	}
	failures++;
	console.log( '  FAIL ' + label + '\n       expected: ' + expected + '\n       actual:   ' + actual );
}

const hay = 'paragraph core/paragraph text';
check( 'empty query matches everything', true, matches( hay, '' ) );
check( 'whitespace-only query matches everything', true, matches( hay, '   ' ) );
check( 'substring of the title', true, matches( hay, 'parag' ) );
check( 'case-insensitive', true, matches( hay, 'PARAGRAPH' ) );
check( 'matches the block name', true, matches( hay, 'core/p' ) );
check( 'every term must appear, in any order', true, matches( hay, 'text paragraph' ) );
check( 'one missing term fails', false, matches( hay, 'paragraph video' ) );
check( 'no match', false, matches( hay, 'zzz' ) );

check( 'same set, any order, same key', setKey( [ 'a/b', 'c/d' ] ), setKey( [ 'c/d', 'a/b' ] ) );
check( 'a different set differs', false, setKey( [ 'a/b' ] ) === setKey( [ 'a/b', 'c/d' ] ) );
check( 'empty set has a stable key', setKey( [] ), setKey( [] ) );
check( 'setKey does not reorder its input', 'z/z,a/a', [ 'z/z', 'a/a' ].join( ',' ) && ( function () { const a = [ 'z/z', 'a/a' ]; setKey( a ); return a.join( ',' ); }() ) );

const { needsConfirm } = global.window.bwsBlockPicker;
const counts = { 'core/paragraph': 3, 'core/table': 0, 'core/heading': 5 };
const list = function ( initial, posted ) { return needsConfirm( initial, posted, counts ).join( ',' ); };
check( 'a newly enabled block with counted tags asks', 'core/paragraph', list( [], [ 'core/paragraph' ] ) );
check( 'a block already saved does not ask again', '', list( [ 'core/paragraph' ], [ 'core/paragraph' ] ) );
check( 'a newly enabled block with a zero count does not ask', '', list( [], [ 'core/table' ] ) );
check( 'a newly enabled block the scan never saw does not ask', '', list( [], [ 'acme/new' ] ) );
check( 'only the newly enabled, counted ones are listed, in selection order', 'core/heading,core/paragraph', list( [ 'core/table' ], [ 'core/table', 'core/heading', 'core/paragraph' ] ) );
check( 'deselecting never asks', '', list( [ 'core/paragraph' ], [] ) );
check( 'no scan counts at all: nothing to ask', '', needsConfirm( [], [ 'core/paragraph' ], {} ).join( ',' ) );

const { sortRows } = global.window.bwsBlockPicker;
const rowsIn = [
	{ id: 'a', block: 'paragraph', contents: 'inline text', registered: 12 },
	{ id: 'b', block: 'columns', contents: null, registered: 0 },
	{ id: 'c', block: 'heading', contents: 'inline text', registered: 4 },
	{ id: 'd', block: 'button', contents: 'link text', registered: 4 },
	{ id: 'e', block: 'list', contents: 'no text of its own', registered: 0 },
];
const order = function ( key, dir ) { return sortRows( rowsIn, key, dir ).map( function ( r ) { return r.id; } ).join( '' ); };
check( 'Block ascending is alphabetical', 'dbcea', order( 'block', 'ascending' ) );
check( 'Block descending reverses it', 'aecbd', order( 'block', 'descending' ) );
check( 'Holds ascending is by label, Not tested (null) last, ties keep their order', 'acdeb', order( 'contents', 'ascending' ) );
check( 'Holds descending puts Not tested first; tied rows keep original order, not reversed', 'bedac', order( 'contents', 'descending' ) );
check( 'numbers sort numerically, not as text (12 after 4); ties keep order', 'becda', order( 'registered', 'ascending' ) );
check( 'numbers descending; ties keep order', 'acdbe', order( 'registered', 'descending' ) );
check( 'sortRows does not touch its input', 'abcde', rowsIn.map( function ( r ) { return r.id; } ).join( '' ) );

const { showValues, normalizeShow, isVisible, showCounts, filtersActive, emptyKind, showingText } = global.window.bwsBlockPicker;
const view = function ( over ) { return Object.assign( { show: 'default', cat: '', query: '', scanned: true }, over ); };
const row = function ( over ) { return Object.assign( { enabled: false, tags: false, cat: 'text', search: 'paragraph core/paragraph text', touched: false }, over ); };
const seen = function ( v, r ) { return isVisible( view( v ), row( r ) ); };

check( 'a scan offers all four Show values', 'default,enabled,tags,all', showValues( true ).join( ',' ) );
check( 'no scan offers only default and all', 'default,all', showValues( false ).join( ',' ) );
check( 'a stored With tags falls back to default when the scan is gone', 'default', normalizeShow( 'tags', false ) );
check( 'a stored Enabled falls back to default when the scan is gone', 'default', normalizeShow( 'enabled', false ) );
check( 'All blocks survives the missing scan', 'all', normalizeShow( 'all', false ) );
check( 'every value survives with a scan', 'tags', normalizeShow( 'tags', true ) );

check( 'default view, scanned: a plain row is out', false, seen( {}, {} ) );
check( 'default view, scanned: an enabled row is in', true, seen( {}, { enabled: true } ) );
check( 'default view, scanned: a row with tags is in', true, seen( {}, { tags: true } ) );
check( 'default view, no scan: tags never count', false, seen( { scanned: false }, { tags: true } ) );
check( 'default view, no scan: an enabled row is in', true, seen( { scanned: false }, { enabled: true } ) );
check( 'Enabled shows enabled rows only', [ true, false ].join(), [ seen( { show: 'enabled' }, { enabled: true } ), seen( { show: 'enabled' }, { tags: true } ) ].join() );
check( 'With tags shows rows with tags only', [ true, false ].join(), [ seen( { show: 'tags' }, { tags: true } ), seen( { show: 'tags' }, { enabled: true } ) ].join() );
check( 'All blocks shows everything', true, seen( { show: 'all' }, {} ) );
check( 'Category narrows within Show', [ true, false, false ].join(), [ seen( { cat: 'text' }, { enabled: true } ), seen( { cat: 'media' }, { enabled: true } ), seen( { cat: 'text' }, {} ) ].join() );
check( 'a search finds a row outside the Show view', true, seen( { query: 'parag' }, {} ) );
check( 'a search still respects Category', false, seen( { query: 'parag', cat: 'media' }, {} ) );
check( 'a search that misses hides even an enabled row', false, seen( { query: 'zzz' }, { enabled: true } ) );
check( 'a whitespace-only search is no search', false, seen( { query: '   ' }, {} ) );
check( 'a row touched this visit stays visible', true, seen( {}, { touched: true } ) );
check( 'a touched row still respects Category', false, seen( { cat: 'media' }, { touched: true } ) );
check( 'a touched row does not outlive a search that misses', false, seen( { query: 'zzz' }, { touched: true } ) );

const sample = [ row( { enabled: true } ), row( { tags: true } ), row( { enabled: true, tags: true } ), row() ];
check( 'counts are site-wide, scanned', '3,2,2,4', [ 'default', 'enabled', 'tags', 'all' ].map( function ( k ) { return showCounts( sample, true )[ k ]; } ).join() );
check( 'counts before a scan: default is just the enabled ones', 2, showCounts( sample, false ).default );
check( 'unticking moves the Enabled count live', 1, showCounts( [ row( { enabled: true } ), row( { enabled: false } ) ], true ).enabled );

check( 'default view is not filtered', false, filtersActive( view() ) );
check( 'another Show value is filtered', true, filtersActive( view( { show: 'all' } ) ) );
check( 'a category is filtered', true, filtersActive( view( { cat: 'text' } ) ) );
check( 'a search is filtered', true, filtersActive( view( { query: 'x' } ) ) );
check( 'a whitespace search is not', false, filtersActive( view( { query: ' ' } ) ) );

check( 'rows shown: no empty state', '', emptyKind( view(), 3 ) );
check( 'nothing shown on the default view: default empty state', 'default', emptyKind( view(), 0 ) );
check( 'same before a scan: its own wording', 'default-unscanned', emptyKind( view( { scanned: false } ), 0 ) );
check( 'nothing shown with filters on: filtered empty state', 'filtered', emptyKind( view( { cat: 'text' } ), 0 ) );

const sw = { showing: 'Showing %1$d of %2$d blocks.', showing_hint: 'Run a scan.' };
check( 'status line counts shown of total', 'Showing 4 of 110 blocks.', showingText( view(), 4, 110, sw ) );
check( 'before a scan on the default view it adds the hint', 'Showing 4 of 110 blocks. Run a scan.', showingText( view( { scanned: false } ), 4, 110, sw ) );
check( 'before a scan with a search it does not', 'Showing 4 of 110 blocks.', showingText( view( { scanned: false, query: 'a' } ), 4, 110, sw ) );
check( 'before a scan on All blocks it does not', 'Showing 4 of 110 blocks.', showingText( view( { scanned: false, show: 'all' } ), 4, 110, sw ) );

console.log( '\n' + count + ' assertions, ' + failures + ' failed' );
process.exit( failures ? 1 : 0 );
