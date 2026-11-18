/**
 * JS side of the datetime mount-rewrite twin: run tools/test/datetime-mount-corpus.json
 * through assets/js/datetime-read-control.js and print one result per case, in the shape
 * the PHP block in datetime-migration-test.php (D8) builds: `null` when the tag is left
 * alone, otherwise an ORDERED array of [key, value] pairs (key order is part of the
 * property, and a JS object cannot be trusted to carry it for every key).
 *
 * Loads the SHIPPING files as the browser would, against a stubbed `window`; the order
 * normalizer loads first because the rewrite canonicalizes through `window.bwsReorderKeys`.
 *
 * Run:  node tools/test/datetime-mount-driver.js
 *
 * @package BWS_Dynamic_Tags
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const root = path.resolve( __dirname, '../..' );

// The normalizer registers an editor filter after exporting its reorder, so it needs wp.hooks
// and wp.element stubbed. wp.components is left out on purpose: the control file exports its
// pure layer and returns before its editor wiring.
global.window = {};
global.wp = {
	hooks: { addFilter: function () {} },
	element: { useEffect: function () {}, createElement: function () {}, Fragment: 'Fragment' }
};
global.window.wp = global.wp;

function load( relative ) {
	const file = path.join( root, relative );
	vm.runInThisContext( fs.readFileSync( file, 'utf8' ), { filename: file } );
}

load( 'assets/js/slot-fold-grammar.js' );
load( 'assets/js/serialization-order-normalizer.js' );
load( 'assets/js/datetime-read-control.js' );

const legacy = global.window.bwsDatetimeLegacy;
if ( ! legacy ) {
	console.error( 'datetime-read-control.js did not export window.bwsDatetimeLegacy' );
	process.exit( 2 );
}
if ( 'function' !== typeof global.window.bwsReorderKeys ) {
	console.error( 'serialization-order-normalizer.js did not export window.bwsReorderKeys' );
	process.exit( 2 );
}

const corpus = JSON.parse( fs.readFileSync( path.join( __dirname, 'datetime-mount-corpus.json' ), 'utf8' ) );
const out = corpus.cases.map( function ( c ) {
	const got = legacy.rewrite( c.options, c.family );
	return null === got ? null : Object.keys( got ).map( function ( k ) { return [ k, got[ k ] ]; } );
} );

process.stdout.write( JSON.stringify( out ) );
