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
check( 'a newly selected block with counted tags asks', 'core/paragraph', list( [], [ 'core/paragraph' ] ) );
check( 'a block already saved does not ask again', '', list( [ 'core/paragraph' ], [ 'core/paragraph' ] ) );
check( 'a newly selected block with a zero count does not ask', '', list( [], [ 'core/table' ] ) );
check( 'a newly selected block the scan never saw does not ask', '', list( [], [ 'acme/new' ] ) );
check( 'only the newly selected, counted ones are listed, in selection order', 'core/heading,core/paragraph', list( [ 'core/table' ], [ 'core/table', 'core/heading', 'core/paragraph' ] ) );
check( 'deselecting never asks', '', list( [ 'core/paragraph' ], [] ) );
check( 'no scan counts at all: nothing to ask', '', needsConfirm( [], [ 'core/paragraph' ], {} ).join( ',' ) );

const { totalsText } = global.window.bwsBlockPicker;
const words = { totals: 'In the blocks below: %1$d fill, %2$d others.', none: 'None.' };
check( 'totals name both counts', 'In the blocks below: 5 fill, 2 others.', totalsText( 5, 2, words ) );
check( 'only the second kind still reads as totals', 'In the blocks below: 0 fill, 3 others.', totalsText( 0, 3, words ) );
check( 'both zero says none', 'None.', totalsText( 0, 0, words ) );

const { sortRows } = global.window.bwsBlockPicker;
const rowsIn = [
	{ id: 'a', block: 'paragraph', contents: 'inline text', registered: 12 },
	{ id: 'b', block: 'columns', contents: null, registered: 0 },
	{ id: 'c', block: 'heading', contents: 'inline text', registered: 4 },
	{ id: 'd', block: 'button', contents: 'inline text; the block is the link', registered: 4 },
	{ id: 'e', block: 'list', contents: 'no text of its own', registered: 0 },
];
const order = function ( key, dir ) { return sortRows( rowsIn, key, dir ).map( function ( r ) { return r.id; } ).join( '' ); };
check( 'Block ascending is alphabetical', 'dbcea', order( 'block', 'ascending' ) );
check( 'Block descending reverses it', 'aecbd', order( 'block', 'descending' ) );
check( 'Contents ascending is by label, Not tested (null) last, ties keep their order', 'acdeb', order( 'contents', 'ascending' ) );
check( 'Contents descending puts Not tested first; tied rows keep original order, not reversed', 'bedac', order( 'contents', 'descending' ) );
check( 'numbers sort numerically, not as text (12 after 4); ties keep order', 'becda', order( 'registered', 'ascending' ) );
check( 'numbers descending; ties keep order', 'acdbe', order( 'registered', 'descending' ) );
check( 'sortRows does not touch its input', 'abcde', rowsIn.map( function ( r ) { return r.id; } ).join( '' ) );

console.log( '\n' + count + ' assertions, ' + failures + ' failed' );
process.exit( failures ? 1 : 0 );
