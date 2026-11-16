/**
 * Harness for the {{table}} editor stylesheet (assets/js/table-editor-style.js): what it builds
 * from the `data-bws-table-style` payload the preview replacement carries, where it puts the
 * stylesheet, and what it refuses. Run: `node tools/test/table-editor-style-test.js` (exits
 * non-zero on failure).
 *
 * The expected CSS is NOT computed by the script's own twin: every case is handed to the PHP
 * builder (bws_table_rules + bws_table_rules_css, the owner) and the injected text must equal
 * it, over the same corpus table-style-twin-test.php uses. The DOM is a minimal stub, so what a
 * real canvas does (iframe timing, React re-rendering a block) is not reachable here and is
 * measured in the testbed editor (table-test-matrix.md, TE6).
 *
 * @package BWS_Dynamic_Tags
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );
const { execFileSync } = require( 'child_process' );

const root = path.resolve( __dirname, '../..' );
const php = ( code, input ) => execFileSync( 'php', [ '-r', code ], { cwd: root, input: input } ).toString();
const vocab = JSON.parse( php( "define('ABSPATH',1); require 'includes/helpers/table-helpers.php'; echo json_encode(bws_table_style_vocabulary());" ) );
const corpus = JSON.parse( fs.readFileSync( path.join( __dirname, 'table-style-corpus.json' ), 'utf8' ) );

// The owner's answer per case: its scope class and its stylesheet text.
const owner = JSON.parse( php(
	"define('ABSPATH',1); require 'includes/helpers/serialization-order.php'; require 'includes/helpers/slot-fold.php'; require 'includes/helpers/table-helpers.php';" +
	"$out=array(); foreach (json_decode(file_get_contents('php://stdin'),true) as $c) {" +
	"$cols=array_map('bws_table_column_style_read',$c['columns']);" +
	"$r=bws_table_rules(bws_table_style_read($c['options']),$cols,$c['hasHeader'],$c['defaults']);" +
	"$s=bws_table_style_class($r); $out[]=array('scope'=>$s,'css'=>'' === $s ? '' : bws_table_rules_css($s,$r)); }" +
	'echo json_encode($out);',
	JSON.stringify( corpus.rules )
) );

// ── A DOM small enough to read ─────────────────────────────────────────────────────
const observers = [];
global.MutationObserver = function ( cb ) {
	this.cb = cb;
	observers.push( this );
	this.observe = function ( target ) { this.target = target; };
};

function makeDoc() {
	const doc = { tables: [], frames: [], byId: {}, nodeType: 9 };
	doc.head = { children: [], appendChild: function ( el ) { this.children.push( el ); doc.byId[ el.id ] = el; return el; } };
	doc.createElement = ( tag ) => ( { tagName: tag.toUpperCase(), id: '', textContent: '', nodeType: 1 } );
	doc.getElementById = ( id ) => doc.byId[ id ] || null;
	doc.querySelectorAll = ( sel ) => ( /iframe/.test( sel ) ? doc.frames.slice() : doc.tables.slice() );
	return doc;
}
function makeTable( doc, scope, payload ) {
	const attrs = payload === undefined ? {} : { 'data-bws-table-style': typeof payload === 'string' ? payload : JSON.stringify( payload ) };
	const t = {
		nodeType: 1, tagName: 'TABLE', ownerDocument: doc, className: scope,
		getAttribute: ( n ) => ( n in attrs ? attrs[ n ] : null ),
		hasAttribute: ( n ) => n in attrs
	};
	return t;
}
function makeFrame( inner ) {
	const listeners = {};
	return {
		nodeType: 1, tagName: 'IFRAME', contentDocument: inner,
		addEventListener: ( ev, fn ) => { listeners[ ev ] = fn; },
		fire: ( ev ) => listeners[ ev ] && listeners[ ev ]()
	};
}
function observerOf( doc ) {
	return observers.filter( ( o ) => o.target === doc )[ 0 ];
}

global.window = { bwsTableVocab: vocab };
global.document = makeDoc();
function load( relative ) {
	const file = path.join( root, relative );
	vm.runInThisContext( fs.readFileSync( file, 'utf8' ), { filename: file } );
}
load( 'assets/js/serialization-order-normalizer.js' );
load( 'assets/js/slot-fold-grammar.js' );
load( 'assets/js/table-style-grammar.js' );
const mainDoc = global.document;
const preexisting = makeTable( mainDoc, 'bws-dte-table-0000000000', undefined );
mainDoc.tables.push( preexisting );
load( 'assets/js/table-editor-style.js' );

let fail = 0;
let total = 0;
function check( label, expected, actual ) {
	total++;
	if ( JSON.stringify( expected ) === JSON.stringify( actual ) ) {
		return;
	}
	fail++;
	console.log( 'FAIL ' + label + '\n  expected: ' + JSON.stringify( expected ) + '\n  actual:   ' + JSON.stringify( actual ) );
}
const styleIds = ( doc ) => doc.head.children.map( ( s ) => s.id );

// ── Parity with the PHP builder over the twin corpus ───────────────────────────────
corpus.rules.forEach( ( c, i ) => {
	const doc = makeDoc();
	const o = owner[ i ];
	if ( o.scope === '' ) {
		return;
	}
	const payload = { t: c.options, c: c.columns, h: c.hasHeader, s: c.defaults };
	global.window.bwsTableEditorStyle.watch( doc );
	doc.tables.push( makeTable( doc, o.scope, payload ) );
	observerOf( doc ).cb( [ { type: 'childList', addedNodes: [ doc.tables[ 0 ] ] } ] );
	check( 'corpus ' + i + ': one stylesheet for the scope', [ 'bws-table-' + o.scope ], styleIds( doc ) );
	check( 'corpus ' + i + ': text equals the PHP builder\'s', o.css, doc.head.children[ 0 ] ? doc.head.children[ 0 ].textContent : null );
} );

// ── Behavior ───────────────────────────────────────────────────────────────────────
const styled = corpus.rules.findIndex( ( c, i ) => owner[ i ].scope !== '' && c.options.stripe );
const sc = corpus.rules[ styled ];
const scope = owner[ styled ].scope;
const payloadOf = ( over ) => Object.assign( { t: sc.options, c: sc.columns, h: sc.hasHeader, s: sc.defaults }, over || {} );

// A table already in the document when the script loads is read at load; one without a payload is left alone.
check( 'a table with no payload gets no stylesheet', [], styleIds( mainDoc ) );

{
	const doc = makeDoc();
	doc.tables.push( makeTable( doc, scope, payloadOf() ), makeTable( doc, scope, payloadOf() ) );
	global.window.bwsTableEditorStyle.watch( doc );
	check( 'two tables with one scope share one stylesheet', [ 'bws-table-' + scope ], styleIds( doc ) );
	check( 'the stylesheet goes where the table lives (its own document head)', [], styleIds( mainDoc ) );
}

{
	const doc = makeDoc();
	global.window.bwsTableEditorStyle.watch( doc );
	const bad = [ '{not json', '[]', 'null', '"x"' ];
	bad.forEach( ( b ) => doc.tables.push( makeTable( doc, scope, b ) ) );
	observerOf( doc ).cb( [ { type: 'childList', addedNodes: doc.tables } ] );
	check( 'a malformed payload builds nothing and throws nothing', [], styleIds( doc ) );
}

{
	const doc = makeDoc();
	global.window.bwsTableEditorStyle.watch( doc );
	[ 'evil{}', 'bws-dte-table-ZZZZZZZZZZ', 'bws-dte-table-0123456789a', '' ].forEach( ( bad ) => doc.tables.push( makeTable( doc, bad, payloadOf() ) ) );
	observerOf( doc ).cb( [ { type: 'childList', addedNodes: doc.tables } ] );
	check( 'only a scope class of the PHP shape is ever put in a selector', [], styleIds( doc ) );
}

{
	const doc = makeDoc();
	global.window.bwsTableEditorStyle.watch( doc );
	const stripeCase = corpus.rules.findIndex( ( c ) => c.options.stripe && c.defaults.color === 'currentColor' && c.defaults.opacity === 5 && c.columns.length === 0 && c.hasHeader );
	const t = makeTable( doc, owner[ stripeCase ].scope, { t: corpus.rules[ stripeCase ].options, c: [], h: true, s: { color: 'red}body{x:y', opacity: 5 } } );
	doc.tables.push( t );
	observerOf( doc ).cb( [ { type: 'childList', addedNodes: [ t ] } ] );
	const css = doc.head.children[ 0 ] ? doc.head.children[ 0 ].textContent : '';
	check( 'a forged stripe default falls back to the built-in one, the PHP builder\'s text for the same table', owner[ stripeCase ].css, css );
}

{
	const doc = makeDoc();
	global.window.bwsTableEditorStyle.watch( doc );
	const t = makeTable( doc, scope, payloadOf() );
	observerOf( doc ).cb( [ { type: 'attributes', target: t } ] );
	check( 'an attribute change on a table is read', [ 'bws-table-' + scope ], styleIds( doc ) );
}

{
	// The canvas iframe: the table lives in the frame's document, the script in the admin window.
	const admin = makeDoc();
	const inner = makeDoc();
	inner.tables.push( makeTable( inner, scope, payloadOf() ) );
	const frame = makeFrame( inner );
	admin.frames.push( frame );
	global.window.bwsTableEditorStyle.watch( admin );
	check( 'a table in an iframe gets its stylesheet in the iframe document', [ 'bws-table-' + scope ], styleIds( inner ) );
	check( 'and none in the admin document', [], styleIds( admin ) );

	// A frame added later, and a frame whose document is replaced on load.
	const later = makeDoc();
	later.tables.push( makeTable( later, scope, payloadOf() ) );
	const lateFrame = makeFrame( null );
	admin.frames.push( lateFrame );
	observerOf( admin ).cb( [ { type: 'childList', addedNodes: [ lateFrame ] } ] );
	check( 'a frame with no document yet is skipped without error', [], styleIds( later ) );
	lateFrame.contentDocument = later;
	lateFrame.fire( 'load' );
	check( 'the frame\'s load event attaches it', [ 'bws-table-' + scope ], styleIds( later ) );

	const reloaded = makeDoc();
	reloaded.tables.push( makeTable( reloaded, scope, payloadOf() ) );
	frame.contentDocument = reloaded;
	frame.fire( 'load' );
	check( 'a reloaded frame document is styled again', [ 'bws-table-' + scope ], styleIds( reloaded ) );

	const sealed = makeFrame( null );
	Object.defineProperty( sealed, 'contentDocument', { get: () => { throw new Error( 'cross-origin' ); } } );
	admin.frames.push( sealed );
	observerOf( admin ).cb( [ { type: 'childList', addedNodes: [ sealed ] } ] );
	check( 'a frame the window may not read is skipped without error', true, true );
}

console.log( ( fail ? 'FAILED ' + fail + ' of ' : 'all ' ) + total + ' checks' + ( fail ? '' : ' passed' ) );
process.exit( fail ? 1 : 0 );
