/**
 * JS side of the fold-MIGRATE twin: run tools/test/fold-migration-corpus.json through
 * assets/js/slot-fold-migrate.js and print a canonical JSON result document, in the exact
 * shape the PHP twin block in tools/test/fold-migration-test.php builds.
 *
 * Loads the SHIPPING files, evaluated as the browser would (IIFEs against a `window`),
 * rather than porting them — a port would test a reimplementation instead of the code the
 * editor runs. The order normalizer loads first because the migrator canonicalizes key
 * order through its `window.bwsReorderKeys` rather than carrying a second copy of the
 * ranks; without it the emitted order would fall back to insertion order and the diff
 * against PHP would show it.
 *
 * Result shape: one entry per corpus case, either `null` (nothing to migrate) or an
 * ORDERED array of [key, value] pairs. Ordered pairs rather than an object because key
 * ORDER is half the property under test, and a JS object cannot even represent an order
 * that puts a named key before an all-digit one.
 *
 * Run:  node tools/test/fold-migration-driver.js
 *
 * @package BWS_Dynamic_Tags
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const root = path.resolve( __dirname, '../..' );

// Minimal browser/WP surface. slot-fold-migrate.js registers an editor filter after
// exporting its pure layer, and returns early without wp.hooks/wp.element — both are
// stubbed so the load reaches the filter registration rather than bailing before it.
global.window = {};
global.wp = {
	hooks: { addFilter: function () {} },
	element: {
		useEffect: function () {},
		createElement: function () {},
		Fragment: 'Fragment'
	}
};
global.window.wp = global.wp;

function load( relative ) {
	const file = path.join( root, relative );
	vm.runInThisContext( fs.readFileSync( file, 'utf8' ), { filename: file } );
}

load( 'assets/js/serialization-order-normalizer.js' );
load( 'assets/js/slot-fold-grammar.js' );
load( 'assets/js/slot-fold-migrate.js' );

const migrate = global.window.bwsSlotFoldMigrate;
if ( ! migrate ) {
	console.error( 'slot-fold-migrate.js did not export window.bwsSlotFoldMigrate' );
	process.exit( 2 );
}
if ( 'function' !== typeof global.window.bwsReorderKeys ) {
	console.error( 'serialization-order-normalizer.js did not export window.bwsReorderKeys' );
	process.exit( 2 );
}

const corpus = JSON.parse( fs.readFileSync( path.join( __dirname, 'fold-migration-corpus.json' ), 'utf8' ) );

const doc = corpus.cases.map( function ( c ) {
	const out = migrate.migrateSlots( c.options, c.conf );
	if ( null === out ) {
		return null;
	}
	return Object.keys( out ).map( function ( key ) {
		return [ key, String( out[ key ] ) ];
	} );
} );

process.stdout.write( JSON.stringify( doc, null, 4 ) + '\n' );
