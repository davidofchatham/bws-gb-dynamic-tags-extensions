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

console.log( '\n' + count + ' assertions, ' + failures + ' failed' );
process.exit( failures ? 1 : 0 );
