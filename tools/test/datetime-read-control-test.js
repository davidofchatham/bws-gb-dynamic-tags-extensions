/**
 * Harness for the `{{datetime}}` read control — the `key` option as a list of reads
 * (assets/js/datetime-read-control.js). Run: `node tools/test/datetime-read-control-test.js`
 * (exits non-zero on failure, house convention).
 *
 * WHAT IT HOLDS. The four behaviors the control lifts from the slot repeater (§D1 to §D4), the operand pair `a+b` (§D5), what the control
 * leaves alone when it writes (§D6), and the join with the REAL field picker, whose one pair of
 * filters has to serve every picker the control mounts (§D7).
 *
 * TWO FILES ARE REAL, ONE IS A STAND-IN. The control and the field picker load as shipped; a
 * stub of the picker would hold only half of §D7. The tag modal is not here: GenerateBlocks
 * owns it, so a test drives the control through the filter it registers, exactly as GB does.
 *
 * WHAT THIS DOES NOT COVER, stated so a passing run is not read as full coverage: the grammar
 * (`bws_datetime_reads()`, owned and tested in PHP), the verbs (a later control), and anything needing
 * a real DOM. How the grouped Type select renders, keyboard-navigates and announces is measured
 * in the testbed editor, not here.
 *
 * @package BWS_Dynamic_Tags
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const root = path.resolve( __dirname, '../..' );

/* -------------------------------------------------------------------------
 * A minimal hook runtime (the field-combo harness's, with cells kept on request)
 * ---------------------------------------------------------------------- */

const hooks = { cells: [], idx: 0, effects: [], dirty: true };

function useState( initial ) {
	const i = hooks.idx++;
	if ( ! ( i in hooks.cells ) ) {
		hooks.cells[ i ] = typeof initial === 'function' ? initial() : initial;
	}
	return [ hooks.cells[ i ], function ( next ) {
		const value = typeof next === 'function' ? next( hooks.cells[ i ] ) : next;
		if ( value !== hooks.cells[ i ] ) {
			hooks.cells[ i ] = value;
			hooks.dirty = true;
		}
	} ];
}
function useMemo( fn ) { return fn(); }
function useEffect( fn ) { hooks.effects.push( fn ); }

/** Render a function component to settlement. `keep` renders again over the cells it has. */
async function render( Component, props, keep ) {
	if ( ! keep ) { hooks.cells = []; }
	hooks.dirty = true;
	let tree = null;
	let guard = 0;
	while ( hooks.dirty && guard++ < 12 ) {
		hooks.dirty = false;
		hooks.idx = 0;
		hooks.effects = [];
		tree = Component( props );
		hooks.effects.forEach( function ( fn ) { fn(); } );
		await Promise.resolve();
		await Promise.resolve();
	}
	if ( guard >= 12 ) {
		console.error( 'render did not settle in 12 passes — a setter is thrashing' );
		process.exit( 2 );
	}
	return tree;
}

function createElement( type, props ) {
	return { type: type, props: props || {}, children: Array.prototype.slice.call( arguments, 2 ) };
}

const registeredFilters = {};

global.window = {};
global.wp = {
	hooks: { addFilter: function ( hook, ns, fn ) { registeredFilters[ ns ] = fn; } },
	element: {
		createElement: createElement,
		Fragment: 'Fragment',
		useState: useState,
		useEffect: useEffect,
		useMemo: useMemo,
	},
	components: {
		ComboboxControl: 'ComboboxControl',
		SelectControl: 'SelectControl',
		Button: 'Button',
		Flex: 'Flex',
		FlexItem: 'FlexItem',
	},
	apiFetch: function () { return Promise.resolve( { row: null } ); },
	i18n: { __: function ( s ) { return s; } },
};
global.window.wp = global.wp;

/* -------------------------------------------------------------------------
 * The fixture: three date-ish fields and one that is not, as the picker would list them
 * ---------------------------------------------------------------------- */

global.window.bwsFieldEnvelope = {
	post: [ {
		group_title: 'Event Details',
		fields: [
			{ name: 'event_date', label: 'Event Date', type: 'date_picker' },
			{ name: 'start_time', label: 'Start Time', type: 'time_picker' },
			{ name: 'end_dt', label: 'End', type: 'date_time_picker' },
			{ name: 'venue', label: 'Venue', type: 'text' },
		],
	} ],
	term: [],
	site: [],
};
global.window.bwsRootArgKinds = {};
global.window.bwsChainKinds = { steps: {}, settles: '', roots: {} };
const GROUP = 'group:datetime';
global.window.bwsFieldTypeGroups = [ {
	value: GROUP,
	label: 'Date & time',
	allLabel: 'All date & time',
	members: [
		{ type: 'date_time_picker', label: 'Date/Time' },
		{ type: 'date_picker', label: 'Date' },
		{ type: 'time_picker', label: 'Time' },
	],
} ];

function load( relative ) {
	const file = path.join( root, relative );
	vm.runInThisContext( fs.readFileSync( file, 'utf8' ), { filename: file } );
}
load( 'assets/js/slot-fold-grammar.js' );
load( 'assets/js/option-nodes.js' );
load( 'assets/js/field-combo-control.js' );
load( 'assets/js/datetime-read-control.js' );

const filter = registeredFilters[ 'bws/datetime-read-control' ];
const FieldCombo = global.window.bwsFieldComboControl;
if ( ! filter || ! FieldCombo ) {
	console.error( 'the datetime read control did not register, or the field picker did not load' );
	process.exit( 2 );
}

/** The option definition, as bws_get_datetime_reads_option() ships it. */
const KEY_DEF = {
	type: 'bws-datetime-reads',
	label: 'Date/Time Field Key',
	help: 'A date field.',
	placeholder: 'event_date',
	timeLabel: 'Time Field Key (optional)',
	timeHelp: 'A time field.',
	timePlaceholder: 'event_time',
	noun: 'Date/Time',
	addLabel: 'Add Date/Time',
	removeLabel: 'Remove',
	max: 2,
	typeDefault: GROUP,
};

/* -------------------------------------------------------------------------
 * Reading the rendered tree
 * ---------------------------------------------------------------------- */

function walk( node, out ) {
	out = out || [];
	if ( ! node || typeof node !== 'object' ) { return out; }
	if ( Array.isArray( node ) ) {
		node.forEach( function ( n ) { walk( n, out ); } );
		return out;
	}
	if ( node.type ) { out.push( node ); }
	( node.children || [] ).forEach( function ( c ) { walk( c, out ); } );
	return out;
}
function findAll( tree, type ) {
	return walk( tree ).filter( function ( n ) { return n.type === type; } );
}
const pickers = function ( tree ) { return findAll( tree, FieldCombo ); };
const buttons = function ( tree ) { return findAll( tree, 'Button' ); };
const headings = function ( tree ) { return findAll( tree, 'strong' ).map( function ( n ) { return n.children[ 0 ]; } ); };
const addButton = function ( tree ) { return buttons( tree ).filter( function ( b ) { return 'secondary' === b.props.variant; } )[ 0 ]; };
const removeButtons = function ( tree ) { return buttons( tree ).filter( function ( b ) { return b.props.isDestructive; } ); };

/** Mount the control on a tag state; `writes` collects every state it commits. */
async function mount( state ) {
	const writes = [];
	const element = filter( { key: 'key' }, { key: KEY_DEF }, { state: state, setState: function ( u ) { writes.push( u ); } } );
	const tree = await render( element.type, element.props );
	return { tree: tree, writes: writes, element: element };
}

/** The `key` wire the control committed last, or `undefined` when it deleted the key. */
function lastKey( m ) {
	const last = m.writes[ m.writes.length - 1 ];
	return last && Object.prototype.hasOwnProperty.call( last, 'key' ) ? last.key : undefined;
}

/** Pick `value` in picker `n` (render order: entry 1 date, entry 1 time, entry 2 date, ...). */
function pick( m, n, value ) {
	pickers( m.tree )[ n ].props.context.setState( null === value ? {} : { key: value } );
}

let fail = 0;
let total = 0;
function check( label, got, want ) {
	total++;
	const g = JSON.stringify( got );
	const w = JSON.stringify( want );
	const ok = g === w;
	if ( ! ok ) { fail++; }
	console.log( ( ok ? 'PASS  ' : 'FAIL  ' ) + label );
	if ( ! ok ) {
		console.log( '        want  : ' + w );
		console.log( '        got   : ' + g );
	}
}

async function main() {

	/* =====================================================================
	 * §D1 — an absent value DISPLAYS as the one read it spells
	 * ================================================================== */

	const empty = await mount( {} );
	check( 'D1.1 no `key` shows ONE read: a date picker and an optional time picker', pickers( empty.tree ).map( function ( p ) { return p.props.label; } ), [ 'Date/Time Field Key', 'Time Field Key (optional)' ] );
	check( 'D1.2 ...with no per-read heading and no Remove, since there is nothing to go back to', [ headings( empty.tree ), removeButtons( empty.tree ).length ], [ [], 0 ] );
	check( 'D1.3 ...and Add offered', addButton( empty.tree ).children[ 0 ], '+ Add Date/Time' );
	const blank = await mount( { key: '  ' } );
	check( 'D1.4 a blank `key` reads the same as an absent one', pickers( blank.tree ).length, 2 );

	/* =====================================================================
	 * §D2 — Add writes an EXPLICIT seed
	 * ================================================================== */

	addButton( empty.tree ).props.onClick();
	check( 'D2.1 Add on an absent `key` writes the two-read seed `;`', lastKey( empty ), ';' );

	const one = await mount( { key: 'event_date', src: 'site' } );
	addButton( one.tree ).props.onClick();
	check( 'D2.2 Add after one read writes a trailing `;`, keeping the first read', lastKey( one ), 'event_date;' );
	check( 'D2.3 ...and every other option of the tag untouched', one.writes[ 0 ].src, 'site' );

	const seeded = await mount( { key: ';' } );
	check( 'D2.4 the seed is two reads on reopen, each empty', [ headings( seeded.tree ), pickers( seeded.tree ).length ], [ [ 'Date/Time 1', 'Date/Time 2' ], 4 ] );

	/* =====================================================================
	 * §D3 — removal COMPACTS
	 * ================================================================== */

	const two = await mount( { key: 'event_date+start_time;end_dt' } );
	check( 'D3.1 two reads are headed by number, never by start and end', headings( two.tree ), [ 'Date/Time 1', 'Date/Time 2' ] );
	removeButtons( two.tree )[ 0 ].props.onClick();
	check( 'D3.2 removing the FIRST of two shifts the second into its place', lastKey( two ), 'end_dt' );
	removeButtons( two.tree )[ 1 ].props.onClick();
	check( 'D3.3 removing the second keeps the first, operand pair and all', lastKey( two ), 'event_date+start_time' );

	const blankSecond = await mount( { key: ';end_dt' } );
	removeButtons( blankSecond.tree )[ 1 ].props.onClick();
	check( 'D3.4 removing down to one EMPTY read deletes the key rather than writing nothing', lastKey( blankSecond ), undefined );
	check( 'D3.5 ...by omitting it (the delete-omit idiom), not by writing an empty string', Object.prototype.hasOwnProperty.call( blankSecond.writes[ 0 ], 'key' ), false );

	/* =====================================================================
	 * §D4 — Add gates on AVAILABILITY
	 * ================================================================== */

	check( 'D4.1 Add is gone at two reads', addButton( two.tree ), undefined );
	check( 'D4.2 ...and every read has Remove', removeButtons( two.tree ).length, 2 );
	check( 'D4.3 Add is offered at one read', !! addButton( one.tree ), true );

	/* =====================================================================
	 * §D5 — the operand pair writes `a+b`
	 * ================================================================== */

	const date = await mount( { key: 'event_date' } );
	pick( date, 1, 'start_time' );
	check( 'D5.1 a time field picked beside a date field writes `date+time`', lastKey( date ), 'event_date+start_time' );

	const pair = await mount( { key: 'event_date+start_time' } );
	pick( pair, 1, null );
	check( 'D5.2 clearing the time field writes the date alone, never a dangling `+`', lastKey( pair ), 'event_date' );
	pick( pair, 0, null );
	check( 'D5.3 clearing the date field leaves the time operand where it was', lastKey( pair ), '+start_time' );

	const range = await mount( { key: 'event_date+start_time;end_dt' } );
	pick( range, 3, 'end_time' );
	check( 'D5.4 a second read takes its own pair, the first untouched', lastKey( range ), 'event_date+start_time;end_dt+end_time' );
	pick( range, 2, null );
	check( 'D5.5 clearing the second read\'s field keeps it a read (a range stays a range)', lastKey( range ), 'event_date+start_time;' );

	const third = await mount( { key: 'a+b+c' } );
	pick( third, 1, 'x' );
	check( 'D5.6 a hand-written third operand survives an edit of the first two', lastKey( third ), 'a+x+c' );

	const fresh = await mount( {} );
	pick( fresh, 0, 'event_date' );
	check( 'D5.7 picking the first field on an absent `key` writes just that field', lastKey( fresh ), 'event_date' );

	/* =====================================================================
	 * §D6 — what the control leaves alone
	 * ================================================================== */

	const cleared = await mount( { key: 'event_date', src: 'site', format: 'F j' } );
	pick( cleared, 0, null );
	check( 'D6.1 clearing the only field deletes `key` and keeps the rest of the tag', cleared.writes[ 0 ], { src: 'site', format: 'F j' } );

	const verbs = await mount( { use: 'now;key', key: 'event_date' } );
	pick( verbs, 0, 'other' );
	check( 'D6.2 it writes `key` only: a `use` already on the tag is not its to touch', verbs.writes[ 0 ], { use: 'now;key', key: 'other' } );

	check( 'D6.3 another option type is returned untouched', filter( { key: 'format' }, { format: { type: 'text' } }, { state: {} } ).type, undefined );
	check( 'D6.4 a control a prior filter hid stays hidden', filter( null, { key: KEY_DEF }, { state: {} } ), null );

	/* =====================================================================
	 * §D7 — one pair of filters for every picker, through the REAL picker
	 * ================================================================== */

	const twoState = { key: 'a;b', src: 'site' };
	const live = await mount( twoState );
	const ps = pickers( live.tree );
	check( 'D7.1 the date and time pickers of each read all name the tag\'s `key` option', ps.map( function ( p ) { return p.props.optionKey; } ), [ 'key', 'key', 'key', 'key' ] );
	check( 'D7.2 every picker is handed the tag\'s other options, a read at a time (the source it reads from)', ps.map( function ( p ) { return p.props.context.state.src + ':' + p.props.context.state.key; } ), [ 'site:a', 'site:', 'site:b', 'site:' ] );
	check( 'D7.3 every picker opens on the date group', ps.map( function ( p ) { return p.props.typeDefault; } ), [ GROUP, GROUP, GROUP, GROUP ] );
	check( 'D7.4 exactly one picker is told to draw the filters, the first', ps.map( function ( p ) { return p.props.sharedFilters.show; } ), [ true, false, false, false ] );

	const drawn = [];
	for ( const p of ps ) {
		const tree = await render( p.type, p.props );
		drawn.push( findAll( tree, 'SelectControl' ).length );
	}
	check( 'D7.5 so the filter pair is drawn ONCE for four pickers', drawn, [ 2, 0, 0, 0 ] );

	const typeSelect = findAll( await render( ps[ 0 ].type, ps[ 0 ].props ), 'SelectControl' )[ 1 ];
	check( 'D7.6 the Type filter opens on the date group', typeSelect.props.value, GROUP );

	// The author picks a member type in the ONE visible filter; every picker follows it.
	// (A fresh mount: the hook cells above now belong to the last picker rendered.)
	const live2 = await mount( { key: ';' } );
	pickers( live2.tree )[ 0 ].props.sharedFilters.setType( 'time_picker' );
	const after = await render( live2.element.type, live2.element.props, true );
	const followed = [];
	for ( const p of pickers( after ) ) {
		const tree = await render( p.type, p.props );
		const combo = findAll( tree, 'ComboboxControl' )[ 0 ].props;
		followed.push( combo.options.map( function ( o ) { return o.label; } ) );
	}
	check(
		'D7.7 a type chosen in the shared filter narrows EVERY picker, the hidden ones too',
		followed,
		[ 0, 1, 2, 3 ].map( function () { return [ "Start Time (Time, 'start_time')" ]; } )
	);

	console.log( '' );
	if ( fail ) {
		console.log( 'DATETIME READ CONTROL TEST FAILED (' + fail + ' of ' + total + ')' );
		process.exit( 1 );
	}
	console.log( 'DATETIME READ CONTROL TEST PASSED (' + total + ' assertions)' );
}

main().catch( function ( e ) {
	console.error( e && e.stack ? e.stack : e );
	process.exit( 2 );
} );
