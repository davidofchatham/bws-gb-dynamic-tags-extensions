/**
 * JS half of the {{table}} presentation twin harness. Loads the SHIPPED grammar files against
 * the vocabulary PHP inlines, runs the shared corpus, and prints one JSON document that
 * table-style-twin-test.php diffs against the PHP side.
 *
 * Usage: node table-style-twin-driver.js <vocab.json> <corpus.json>
 *
 * @package BWS_Dynamic_Tags
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const root = path.resolve( __dirname, '../..' );
global.window = {};
global.window.bwsTableVocab = JSON.parse( fs.readFileSync( process.argv[ 2 ], 'utf8' ) );
const corpus = JSON.parse( fs.readFileSync( process.argv[ 3 ], 'utf8' ) );

function load( relative ) {
	const file = path.join( root, relative );
	vm.runInThisContext( fs.readFileSync( file, 'utf8' ), { filename: file } );
}

load( 'assets/js/serialization-order-normalizer.js' );
load( 'assets/js/slot-fold-grammar.js' );
load( 'assets/js/table-style-grammar.js' );

const T = global.window.bwsTableStyle;

function counted( parsed ) {
	if ( parsed === null ) {
		return null;
	}
	return Object.assign( {}, parsed, { flags: parsed.flags.length } );
}

const out = {
	widths: corpus.widths.map( ( v ) => T.widthValue( v ) ),
	colors: corpus.colors.map( ( v ) => T.colorValue( v, false ) ),
	colorsGlobals: corpus.colors.map( ( v ) => T.colorValue( v, true ) ),
	alpha: corpus.colors.map( ( v ) => {
		const c = T.colorValue( v, false );
		return c === null ? null : T.colorHasAlpha( c );
	} ),
	fontSizes: corpus.fontSizes.map( ( v ) => T.fontSizeValue( v ) ),
	text: {},
	opacities: corpus.opacities.map( ( v ) => T.opacityValue( v ) ),
	headed: {},
	stripes: corpus.stripes.map( ( v ) => counted( T.parseStripe( v ) ) ),
	items: corpus.items.map( ( v ) => T.headArgsItems( v ) ),
	rules: corpus.rules.map( ( c ) => T.rules( T.styleRead( c.options ), c.columns.map( ( col ) => T.columnStyleRead( col ) ), c.hasHeader, c.defaults ) )
};
Object.keys( corpus.text ).forEach( ( k ) => {
	out.text[ k ] = corpus.text[ k ].map( ( v ) => T.textValue( k, v ) );
} );
Object.keys( corpus.headed ).forEach( ( k ) => {
	out.headed[ k ] = corpus.headed[ k ].map( ( v ) => counted( T.parseText( k, v ) ) );
} );

process.stdout.write( JSON.stringify( out ) );
