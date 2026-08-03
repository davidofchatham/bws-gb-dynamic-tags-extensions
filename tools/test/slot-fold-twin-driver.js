/**
 * JS side of the grammar-twin check: run tools/test/slot-fold-corpus.json through
 * assets/js/slot-fold-grammar.js and print a CANONICAL JSON result document, in the
 * exact shape tools/test/slot-fold-twin-driver.php prints.
 *
 * Loads the SHIPPING files, evaluated as the browser would (an IIFE against a
 * `window`), rather than re-implementing them: a port would test a reimplementation
 * instead of the code the editor runs — the same reason the compaction probe evals
 * the real control.
 *
 * It also loads assets/js/serialization-order-normalizer.js first, because the
 * fold's emitter takes its canonical token order from that file's
 * `window.bwsReorderKeys` rather than carrying a second copy of the KEY_MAP. So this
 * driver exercises that reuse: if the dependency were missing, emit would fall back
 * to insertion order and the diff against PHP would show it.
 *
 * Run:  node tools/test/slot-fold-twin-driver.js
 *
 * @package BWS_Dynamic_Tags
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const root = path.resolve( __dirname, '../..' );

// Minimal browser/WP surface. The normalizer registers an editor filter at load and
// bails early without `wp.hooks`/`wp.element`, so both are stubbed; nothing here is
// called by the pure functions under test.
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

const fold = global.window.bwsSlotFold;
if ( ! fold ) {
	console.error( 'slot-fold-grammar.js did not export window.bwsSlotFold' );
	process.exit( 2 );
}
if ( 'function' !== typeof global.window.bwsReorderKeys ) {
	console.error( 'serialization-order-normalizer.js did not export window.bwsReorderKeys' );
	process.exit( 2 );
}

/** Flatten a chain step list to ordered arrays. */
function canonChain( steps ) {
	if ( steps && steps.error ) {
		return [ 'error', steps.error ];
	}
	return ( steps || [] ).map( function ( step ) {
		return [
			step.slug,
			void 0 === step.arg ? null : step.arg,
			void 0 === step.limit ? null : step.limit,
			step.extra || []
		];
	} );
}

/** Flatten a slot struct: fixed field order, map keys sorted, no plain objects. */
function canonSlot( slot ) {
	if ( null === slot || void 0 === slot ) {
		return null;
	}
	if ( slot.error ) {
		return [ 'error', slot.error ];
	}
	let read = slot.read || null;
	if ( read ) {
		read = [ read.kind, void 0 !== read.slug ? read.slug : ( void 0 !== read.field ? read.field : '' ) ];
	}
	const opts = slot.opts || {};
	const optPairs = Object.keys( opts ).sort().map( function ( name ) {
		return [ name, true === opts[ name ] ? true : String( opts[ name ] ) ];
	} );
	return {
		label: void 0 === slot.label ? null : slot.label,
		type: void 0 === slot.type ? null : slot.type,
		chain: canonChain( slot.chain || [] ),
		read: read,
		opts: optPairs,
		extra: slot.extra || []
	};
}

const corpus = JSON.parse( fs.readFileSync( path.join( __dirname, 'slot-fold-corpus.json' ), 'utf8' ) );
const g = fold.grammar;

const doc = {
	grammar: {
		optSep: g.optSep,
		optClass: g.optClass,
		hopSep: g.hopSep,
		hopClass: g.hopClass,
		stepSep: g.stepSep,
		stepClass: g.stepClass,
		brPairs: Object.keys( g.brPairs ).map( function ( open ) {
			return [ open, g.brPairs[ open ] ];
		} ),
		reserved: g.reserved,
		types: g.types,
		flags: g.flags,
		freeform: g.freeform,
		fanningSlugs: g.fanningSlugs,
		brackets: [ fold.bracketPair( 1 ), fold.bracketPair( 2 ), fold.bracketPair( 3 ) ]
	},
	slots: corpus.slots.map( function ( c ) {
		const parsed = fold.parseSlot( c.wire, c.container );
		return {
			parse: canonSlot( parsed ),
			emit: parsed.error ? null : fold.emitSlot( parsed )
		};
	} ),
	chains: corpus.chains.map( function ( c ) {
		const parsed = fold.parseChain( c.chain );
		return {
			parse: canonChain( parsed ),
			emit: parsed.error ? null : fold.emitChain( parsed, c.enclosingLevel )
		};
	} ),
	// Emit-from-STRUCT: the only path that can hold a NUMERIC value (parse normalizes
	// everything to strings), which is where a truthiness guard on `limit` drops a
	// pinned 0 = unlimited.
	emits: corpus.emitStructs.map( function ( c ) {
		const wire = fold.emitSlot( c.slot, c.level );
		return { emit: wire, reparse: canonSlot( fold.parseSlot( wire, 'table' ) ) };
	} ),
	legacy: corpus.legacy.map( function ( c ) {
		const rec = fold.foldFromLegacy( c.n, c.options, c.combining, c.perSlotUse );
		return {
			slot: null === rec ? null : canonSlot( rec.slot ),
			emit: null === rec ? null : fold.emitSlot( rec.slot )
		};
	} )
};

process.stdout.write( JSON.stringify( doc, null, 4 ) + '\n' );
