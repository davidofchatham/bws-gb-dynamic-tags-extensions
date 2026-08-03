/**
 * JS side of the serialization-order TWIN check: run key lists through the SHIPPING
 * assets/js/serialization-order-normalizer.js and print the reordered lists as JSON.
 *
 * Loads the shipping file as the browser would (IIFE against a `window`) instead of
 * porting its algorithm — a port would test the port. Same posture as
 * slot-fold-twin-driver.js.
 *
 * Usage:  node tools/test/serialization-order-driver.js <corpus.json>
 * Output: {"reordered":[[...],[...]]}
 *
 * The corpus arrives as a FILE (a JSON array of key lists) rather than an argv string:
 * Windows' escapeshellarg() strips the quotes out of inline JSON.
 *
 * @package BWS_Dynamic_Tags
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const root = path.resolve( __dirname, '../..' );

// The normalizer registers an editor filter at load and returns early without
// wp.hooks / wp.element, so both are stubbed. Neither is touched by reorderKeys.
global.wp = {
	hooks: { addFilter: function () {} },
	element: {
		useEffect: function () {},
		createElement: function () {},
		Fragment: 'Fragment'
	}
};
// The file's own guard reads `window.wp`, so the stub must hang off window too — not
// only off the global. Without it the IIFE returns before exporting anything, which
// is what a first run of this driver actually did.
global.window = { wp: global.wp };

vm.runInThisContext(
	fs.readFileSync( path.join( root, 'assets/js/serialization-order-normalizer.js' ), 'utf8' ),
	{ filename: 'serialization-order-normalizer.js' }
);

if ( 'function' !== typeof global.window.bwsReorderKeys ) {
	process.stdout.write( JSON.stringify( { error: 'window.bwsReorderKeys not exported' } ) );
	process.exit( 1 );
}

const lists = JSON.parse( fs.readFileSync( process.argv[ 2 ], 'utf8' ) );
process.stdout.write( JSON.stringify( {
	reordered: lists.map( function ( keys ) {
		return global.window.bwsReorderKeys( keys );
	} )
} ) );
