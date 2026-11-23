/**
 * Harness for the focus helper (assets/js/focus-after.js). Run: `node tools/test/focus-after-test.js`
 * (exits non-zero on failure, house convention).
 *
 * WHAT IT HOLDS. The rule for where focus goes after an Add or a Remove (`candidates()`, §A1),
 * and the lookup that acts on it against a STUB DOM (§A2 to §A5): which control inside a marked
 * entry takes focus, when a fallback may be taken, and that a lookup that finds nothing gives up.
 *
 * WHAT IT DOES NOT HOLD. Where a real browser puts focus, and what a screen reader announces:
 * the stub answers `querySelectorAll`, `focus` and the few attributes the helper reads, so a
 * passing run says the lookup logic is right and nothing about a browser. That is measured in the
 * testbed editor (docs/editor-controls.md §Keyboard focus after Add and Remove). What each button
 * asks for is held where the button is: `datetime-read-control-test.js` §D12 and
 * `slot-fold-repeater-test.js`.
 *
 * @package BWS_Dynamic_Tags
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const root = path.resolve( __dirname, '../..' );

/* -------------------------------------------------------------------------
 * A stub DOM, and timers the test steps by hand
 * ---------------------------------------------------------------------- */

const pending = [];
global.setTimeout = function ( fn ) { pending.push( fn ); return pending.length; };
/** Run the one timer the helper has queued; false when it has queued none. */
function tick() {
	const fn = pending.shift();
	if ( ! fn ) { return false; }
	fn();
	return true;
}
function drain() { let n = 0; while ( tick() ) { n++; } return n; }

/** A page: marked wrappers in page order, each holding controls. */
let page = [];
const stubDocument = {
	activeElement: { name: 'body' },
	querySelectorAll: function ( selector ) {
		const m = /^\[data-bws-focus(\^?)="(.*)"\]$/.exec( selector );
		return page.filter( function ( n ) {
			return m[ 1 ] ? n.marker.indexOf( m[ 2 ] ) === 0 : n.marker === m[ 2 ];
		} );
	},
	getElementById: function ( id ) {
		const all = [].concat.apply( [], page.map( function ( w ) { return w.controls; } ) );
		return all.filter( function ( c ) { return c.id === id; } )[ 0 ] || null;
	}
};
global.document = stubDocument;

/** A control; `skip` marks a Remove, `hidden` an element that is not on the page. */
function control( name, opts ) {
	opts = opts || {};
	return {
		name: name,
		id: opts.id,
		disabled: !! opts.disabled,
		offsetParent: opts.hidden ? null : {},
		matches: function () { return true; },
		hasAttribute: function ( a ) { return 'data-bws-skip-focus' === a && !! opts.skip; },
		getAttribute: function () { return null; },
		focus: function () { stubDocument.activeElement = this; }
	};
}
/** A marked wrapper, which is not itself a control. */
function wrapper( marker, controls ) {
	return {
		marker: marker,
		controls: controls,
		matches: function () { return false; },
		querySelectorAll: function () { return controls; }
	};
}

global.window = {};
const file = path.join( root, 'assets/js/focus-after.js' );
vm.runInThisContext( fs.readFileSync( file, 'utf8' ), { filename: file } );
const fa = global.window.bwsFocusAfter;

let fail = 0;
let total = 0;
function check( label, got, want ) {
	total++;
	const ok = JSON.stringify( got ) === JSON.stringify( want );
	if ( ! ok ) { fail++; }
	console.log( ( ok ? 'PASS  ' : 'FAIL  ' ) + label );
	if ( ! ok ) {
		console.log( '        want  : ' + JSON.stringify( want ) );
		console.log( '        got   : ' + JSON.stringify( got ) );
	}
}
const active = function () { return stubDocument.activeElement.name; };
function reset() { page = []; pending.length = 0; stubDocument.activeElement = { name: 'body' }; }

/* =========================================================================
 * §A1 — the rule
 * ====================================================================== */

check( 'A1.1 Add asks for the entry it created, then the first control', fa.candidates( 'add', 1, 2 ), [ 'entry-1', 'first' ] );
check( 'A1.2 Remove asks for the entry that took the place, then Add, then the first control', fa.candidates( 'remove', 0, 1 ), [ 'entry-0', 'add', 'first' ] );
check( 'A1.3 removing the LAST entry asks for the one before it', fa.candidates( 'remove', 2, 2 ), [ 'entry-1', 'add', 'first' ] );
check( 'A1.4 removing the only entry left asks for Add, never an entry', fa.candidates( 'remove', 0, 0 ), [ 'add', 'first' ] );

/* =========================================================================
 * §A2 — which control inside an entry takes focus
 * ====================================================================== */

reset();
page = [ wrapper( 's:entry-0', [ control( 'remove', { skip: true } ), control( 'select' ), control( 'input' ) ] ) ];
fa.request( 's', [ 'entry-0' ] );
drain();
check( 'A2.1 the first control that is not a Remove takes focus', active(), 'select' );

reset();
page = [ wrapper( 's:entry-0', [ control( 'remove', { skip: true } ), control( 'gone', { hidden: true } ), control( 'off', { disabled: true } ), control( 'input' ) ] ) ];
fa.request( 's', [ 'entry-0' ] );
drain();
check( 'A2.2 a control that is hidden or disabled is passed over', active(), 'input' );

reset();
page = [ wrapper( 's:entry-0', [ control( 'remove', { skip: true } ) ] ), wrapper( 's:add', [ control( 'add' ) ] ) ];
fa.request( 's', [ 'entry-0', 'add' ] );
drain();
check( 'A2.3 an entry holding only a Remove takes nothing, so the next name is tried', active(), 'add' );

reset();
page = [ wrapper( 'other:entry-0', [ control( 'elsewhere' ) ] ), wrapper( 's:entry-0', [ control( 'mine' ) ] ) ];
fa.request( 's', [ 'entry-0' ] );
drain();
check( 'A2.4 a marker of another scope never answers', active(), 'mine' );

/* =========================================================================
 * §A3 — a fallback waits for the wanted entry
 * ====================================================================== */

reset();
page = [ wrapper( 's:entry-0', [ control( 'first-entry' ) ] ) ];
fa.request( 's', fa.candidates( 'add', 1 ) );
tick();
tick();
check( 'A3.1 an Add whose entry has not mounted does not take the first field instead', active(), 'body' );
page.push( wrapper( 's:entry-1', [ control( 'new-entry' ) ] ) );
drain();
check( 'A3.2 ...and focus goes to the new entry once it mounts', active(), 'new-entry' );

reset();
page = [ wrapper( 's:entry-0', [ control( 'first-entry' ) ] ) ];
fa.request( 's', fa.candidates( 'add', 1 ) );
drain();
check( 'A3.3 an entry that never mounts falls back to the first field, late', active(), 'first-entry' );

/* =========================================================================
 * §A4 — giving up, and the id lookup
 * ====================================================================== */

reset();
fa.request( 's', [ 'entry-0', 'add', 'first' ] );
const ticks = drain();
check( 'A4.1 nothing on the page: focus stays where it was, nothing throws', active(), 'body' );
check( 'A4.2 ...and the lookup is bounded', ticks <= 12, true );

reset();
page = [ wrapper( 'x:head', [ control( 'header-input', { id: 'bws-colheader-A' } ) ] ) ];
fa.byId( 'bws-colheader-A' );
drain();
check( 'A4.3 byId focuses the element with that id', active(), 'header-input' );

/* =========================================================================
 * §A5 — `after()` goes through request()
 * ====================================================================== */

const asked = [];
const realRequest = fa.request;
fa.request = function ( scope, names ) { asked.push( [ scope, names ] ); };
fa.after( 'slot', 'remove', 2, 2 );
fa.request = realRequest;
check( 'A5.1 after() asks request() for the names candidates() gives', asked, [ [ 'slot', [ 'entry-1', 'add', 'first' ] ] ] );

console.log( '' );
if ( fail ) {
	console.log( 'FOCUS AFTER TEST FAILED (' + fail + ' of ' + total + ')' );
	process.exit( 1 );
}
console.log( 'FOCUS AFTER TEST PASSED (' + total + ' assertions)' );
