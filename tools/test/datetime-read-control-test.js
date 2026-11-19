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
 * THE VERBS (§D8): each read is a field or a verb, `use` is written only when a verb is among the
 * reads and then as the full ordered list, and the rows a source offers.
 *
 * THE NOTICES (§D10): a `key` entry no read takes, and an operand past a read's second, each named
 * in a warning, and each notice's claim (removed on the next change, or kept and ignored) held by a
 * write.
 *
 * WHAT THIS DOES NOT COVER, stated so a passing run is not read as full coverage: the grammar
 * (`bws_datetime_reads()`, owned and tested in PHP), and anything needing
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
		Notice: 'Notice',
		BaseControl: { VisualLabel: 'VisualLabel' },
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
	readLabel: 'Date/Time Read',
	reads: [
		{ value: 'key', label: 'Date/Time Field' },
		{ value: 'now', label: 'Current Date/Time' },
		{ value: 'published', label: 'Post Published Date', kinds: [ 'post' ] },
		{ value: 'modified', label: 'Post Modified Date', kinds: [ 'post' ] },
	],
	timeLabel: 'Time Field Key (optional)',
	timeHelp: 'A time field.',
	timePlaceholder: 'event_time',
	entryLabels: [ 'Start Date/Time', 'End Date/Time' ],
	addLabel: 'Add End Date/Time',
	timeAddLabel: 'Add Time Field',
	timeRemoveLabel: 'Remove Time Field',
	removeLabel: 'Remove',
	surplusNotice: [ 'Field %s has no read to use it and will be removed on your next change here.', 'Fields %s have no read to use them and will be removed on your next change here.' ],
	extraNotice: [ 'A read combines one date and one time field only, so %s is ignored.', 'A read combines one date and one time field only, so %s are ignored.' ],
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
/** The per-read headings: WordPress's own control label, not a styled element of ours. */
const headings = function ( tree ) { return findAll( tree, 'VisualLabel' ).map( function ( n ) { return n.children[ 0 ]; } ); };
const addButton = function ( tree ) { return buttons( tree ).filter( function ( b ) { return 'secondary' === b.props.variant; } )[ 0 ]; };
const addTimeButtons = function ( tree ) { return buttons( tree ).filter( function ( b ) { return 'link' === b.props.variant && ! b.props.isDestructive; } ); };
const removeTimeButtons = function ( tree ) { return buttons( tree ).filter( function ( b ) { return 'link' === b.props.variant && b.props.isDestructive; } ); };
const removeButtons = function ( tree ) { return buttons( tree ).filter( function ( b ) { return b.props.isDestructive && 'tertiary' === b.props.variant; } ); };

/** Each Notice as text, a `<code>` child written in backticks. */
const noticeTexts = function ( tree ) {
	return findAll( tree, 'Notice' ).map( function ( n ) {
		return [ n.props.status, n.props.isDismissible ].concat( [ ( function flat( kids ) {
			return kids.map( function ( k ) {
				if ( Array.isArray( k ) ) { return flat( k ); }
				if ( k && 'code' === k.type ) { return '`' + flat( k.children ) + '`'; }
				return k && k.children ? flat( k.children ) : String( null == k ? '' : k );
			} ).join( '' );
		}( n.children ) ) ] );
	} );
};
const readSelects = function ( tree ) { return findAll( tree, 'SelectControl' ); };
const readValues = function ( tree ) { return readSelects( tree ).map( function ( s ) { return s.props.value; } ); };
const offered = function ( tree, n ) { return readSelects( tree )[ n ].props.options.map( function ( o ) { return o.value; } ); };
/** Choose a row in read `n`'s select. */
function choose( m, n, value ) { readSelects( m.tree )[ n ].props.onChange( value ); }
/** The last committed state's `use`, or `undefined` when it deleted the key. */
function lastUse( m ) {
	const last = m.writes[ m.writes.length - 1 ];
	return last && Object.prototype.hasOwnProperty.call( last, 'use' ) ? last.use : undefined;
}
/** Mount again on what the control last wrote (a reload of the editor over the saved tag). */
async function reload( m, base ) {
	return mount( Object.assign( {}, base, m.writes[ m.writes.length - 1 ] ) );
}

/** Mount the control on a tag state; `writes` collects every state it commits. */
async function mount( state ) {
	const writes = [];
	const element = filter( { key: 'key' }, { key: KEY_DEF }, { state: state, setState: function ( u ) { writes.push( u ); } } );
	const tree = await render( element.type, element.props );
	return { tree: tree, writes: writes, element: element };
}

/** Mount the control on another option definition (an old tag's); returns the rendered tree. */
async function mount2( def, state ) {
	let element = filter( { key: 'key' }, { key: def }, { state: state, setState: function () {} } );
	// An old tag's control comes wrapped with its mount migrator; draw the control itself.
	if ( 'Fragment' === element.type ) { element = element.children[ 1 ]; }
	return render( element.type, element.props );
}

/** Click every "Add Time Field" and render again, so each read shows both its pickers. */
async function revealTime( m ) {
	addTimeButtons( m.tree ).forEach( function ( b ) { b.props.onClick(); } );
	m.tree = await render( m.element.type, m.element.props, true );
	return m;
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
	check( 'D1.1 no `key` shows ONE read: a date picker, the optional time picker held back', pickers( empty.tree ).map( function ( p ) { return p.props.label; } ), [ 'Date/Time Field Key' ] );
	check( 'D1.2 ...with no per-read heading and no Remove, since there is nothing to go back to', [ headings( empty.tree ), removeButtons( empty.tree ).length ], [ [], 0 ] );
	check( 'D1.3 ...and Add offered', addButton( empty.tree ).children[ 0 ], '+ Add End Date/Time' );
	const blank = await mount( { key: '  ' } );
	check( 'D1.4 a blank `key` reads the same as an absent one', pickers( blank.tree ).length, 1 );

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
	check( 'D2.4 the seed is two reads on reopen, each empty', [ headings( seeded.tree ), pickers( seeded.tree ).length ], [ [ 'Start Date/Time', 'End Date/Time' ], 2 ] );

	/* =====================================================================
	 * §D3 — removal COMPACTS
	 * ================================================================== */

	const two = await mount( { key: 'event_date+start_time;end_dt' } );
	check( 'D3.1 two reads are headed Start and End', headings( two.tree ), [ 'Start Date/Time', 'End Date/Time' ] );
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

	const date = await revealTime( await mount( { key: 'event_date' } ) );
	pick( date, 1, 'start_time' );
	check( 'D5.1 a time field picked beside a date field writes `date+time`', lastKey( date ), 'event_date+start_time' );

	const pair = await mount( { key: 'event_date+start_time' } );
	pick( pair, 1, null );
	check( 'D5.2 clearing the time field writes the date alone, never a dangling `+`', lastKey( pair ), 'event_date' );
	pick( pair, 0, null );
	check( 'D5.3 clearing the date field leaves the time operand where it was', lastKey( pair ), '+start_time' );

	const range = await revealTime( await mount( { key: 'event_date+start_time;end_dt' } ) );
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
	 * §D5b — the time picker is behind "Add Time Field" until a read has one
	 * ================================================================== */

	const hidden = await mount( { key: 'event_date;end_dt+end_time' } );
	check( 'D5b.1 a read with no time field offers Add Time Field, and one with a time field shows its picker', [ addTimeButtons( hidden.tree ).map( function ( b ) { return b.children[ 0 ]; } ), pickers( hidden.tree ).length ], [ [ '+ Add Time Field' ], 3 ] );
	await revealTime( hidden );
	check( 'D5b.2 clicking it shows the picker and writes nothing to the wire', [ pickers( hidden.tree ).length, hidden.writes.length ], [ 4, 0 ] );
	check( 'D5b.3 ...and the button is gone', addTimeButtons( hidden.tree ).length, 0 );

	const drop = await mount( { key: 'event_date+start_time;end_dt+end_time' } );
	check( 'D5b.4 each read with a time field has its own Remove Time Field, apart from the reads Remove', [ removeTimeButtons( drop.tree ).map( function ( b ) { return b.children[ 0 ]; } ), removeButtons( drop.tree ).length ], [ [ 'Remove Time Field', 'Remove Time Field' ], 2 ] );
	removeTimeButtons( drop.tree )[ 0 ].props.onClick();
	check( 'D5b.5 Remove Time Field clears the time operand of that read and keeps the read', lastKey( drop ), 'event_date;end_dt+end_time' );
	const shownEmpty = await revealTime( await mount( { key: 'event_date' } ) );
	removeTimeButtons( shownEmpty.tree )[ 0 ].props.onClick();
	shownEmpty.tree = await render( shownEmpty.element.type, shownEmpty.element.props, true );
	check( 'D5b.6 on an empty revealed picker it only hides it, writing nothing', [ shownEmpty.writes.length, pickers( shownEmpty.tree ).length, addTimeButtons( shownEmpty.tree ).length ], [ 0, 1, 1 ] );

	// Open state follows the reads. Two reads, the second's picker opened by Add Time Field; the
	// control then sees the wire after the first read was removed (the harness's state is static,
	// so the re-render hands it the wire the write would have produced).
	const open2 = await mount( { key: 'a;b' } );
	addTimeButtons( open2.tree )[ 1 ].props.onClick();
	open2.tree = await render( open2.element.type, open2.element.props, true );
	removeButtons( open2.tree )[ 0 ].props.onClick();
	const afterRm = Object.assign( {}, open2.element.props, { context: { state: { key: 'b' }, setState: function () {} } } );
	open2.tree = await render( open2.element.type, afterRm, true );
	check( 'D5b.7 removing a read takes its open time picker with it: the second read keeps its own', [ pickers( open2.tree ).length, addTimeButtons( open2.tree ).length ], [ 2, 0 ] );

	const sticky = await mount( { key: 'a+b' } );
	const clearedWire = Object.assign( {}, sticky.element.props, { context: { state: { key: 'a' }, setState: function () {} } } );
	sticky.tree = await render( sticky.element.type, clearedWire, true );
	check( 'D5b.8 a time field that came from the wire keeps its picker open once cleared, so retyping it is not interrupted', [ pickers( sticky.tree ).length, addTimeButtons( sticky.tree ).length ], [ 2, 0 ] );

	const thirdOp = await mount( { key: 'a+b+c' } );
	removeTimeButtons( thirdOp.tree )[ 0 ].props.onClick();
	check( 'D5b.9 Remove Time Field on a hand-written third operand clears the time operand and keeps the third (the wire stays hand-editable)', lastKey( thirdOp ), 'a++c' );

	/* =====================================================================
	 * §D6 — what the control leaves alone
	 * ================================================================== */

	const cleared = await mount( { key: 'event_date', src: 'site', format: 'F j' } );
	pick( cleared, 0, null );
	check( 'D6.1 clearing the only field deletes `key` and keeps the rest of the tag', cleared.writes[ 0 ], { src: 'site', format: 'F j' } );

	const verbs = await mount( { use: 'now;key', key: 'event_date' } );
	pick( verbs, 0, 'other' );
	check( 'D6.2 editing a field of a verb-mixed tag keeps the ordered `use` and the other options as they were', verbs.writes[ 0 ], { use: 'now;key', key: 'other' } );

	check( 'D6.3 another option type is returned untouched', filter( { key: 'format' }, { format: { type: 'text' } }, { state: {} } ).type, undefined );
	check( 'D6.4 a control a prior filter hid stays hidden', filter( null, { key: KEY_DEF }, { state: {} } ), null );

	/* =====================================================================
	 * §D7 — one pair of filters for every picker, through the REAL picker
	 * ================================================================== */

	const twoState = { key: 'a;b', src: 'site' };
	const live = await revealTime( await mount( twoState ) );
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
	const live2 = await revealTime( await mount( { key: ';' } ) );
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

	/* =====================================================================
	 * §D8 — verbs: each read is a field or a verb, and `use` follows without being seen
	 * ================================================================== */

	const v0 = await mount( {} );
	check( 'D8.1 one read shows ONE select, on the field row it spells (absent displays as the default)', [ readValues( v0.tree ), readSelects( v0.tree )[ 0 ].props.label ], [ [ 'key' ], 'Date/Time Read' ] );
	check( 'D8.2 with no source kind named, every row is offered', offered( v0.tree, 0 ), [ 'key', 'now', 'published', 'modified' ] );

	choose( v0, 0, 'now' );
	check( 'D8.3 a verb picked on the only read writes `use` and leaves no `key`', [ lastUse( v0 ), lastKey( v0 ) ], [ 'now', undefined ] );
	check( 'D8.4 ...and a verb read shows no field picker', ( await ( async function () { const m = await mount( { use: 'now' } ); return [ pickers( m.tree ).length, addTimeButtons( m.tree ).length ]; }() ) ), [ 0, 0 ] );

	const f1 = await mount( { key: 'start_dt' } );
	addButton( f1.tree ).props.onClick();
	f1.tree = await render( f1.element.type, Object.assign( {}, f1.element.props, { context: { state: { key: 'start_dt;' }, setState: function ( u ) { f1.writes.push( u ); } } } ), true );
	choose( f1, 1, 'now' );
	check( 'D8.5 a field then now writes the full ordered list, `key` first, and the one field entry', [ lastUse( f1 ), lastKey( f1 ) ], [ 'key;now', 'start_dt' ] );

	const f2 = await mount( { key: 'end_dt;x' } );
	choose( f2, 0, 'now' );
	check( 'D8.6 now then a field: the verb takes no entry, so the field read keeps ITS entry (the second)', [ lastUse( f2 ), lastKey( f2 ) ], [ 'now;key', 'x' ] );

	const f3 = await mount( { use: 'now;key', key: 'end_dt' } );
	check( 'D8.7 a stored ordered list reads back in its order: the verb, then the field read with its entry', [ readValues( f3.tree ), pickers( f3.tree ).map( function ( p ) { return p.props.context.state.key; } ) ], [ [ 'now', 'key' ], [ 'end_dt' ] ] );
	const f4 = await mount( { use: 'key;now', key: 'start_dt' } );
	check( 'D8.8 ...and the other order is a different read', [ readValues( f4.tree ), pickers( f4.tree ).map( function ( p ) { return p.props.context.state.key; } ) ], [ [ 'key', 'now' ], [ 'start_dt' ] ] );
	const f5 = await mount( { use: 'key;now' } );
	check( 'D8.9 a `key` in `use` with no entry is an empty field read (it displays, it is not dropped)', [ readValues( f5.tree ), pickers( f5.tree ).length ], [ [ 'key', 'now' ], 1 ] );

	// Removing the last verb removes `use`; an all-field list never leaves one behind.
	const r1 = await mount( { use: 'key;now', key: 'start_dt', format: 'F j' } );
	removeButtons( r1.tree )[ 1 ].props.onClick();
	check( 'D8.10 removing the last verb removes `use`, keeping the field and the rest of the tag', r1.writes[ 0 ], { key: 'start_dt', format: 'F j' } );
	const r2 = await mount( { use: 'key;now', key: 'start_dt' } );
	choose( r2, 1, 'key' );
	check( 'D8.11 turning the verb back into a field removes `use` and keeps a second, empty field read', [ lastUse( r2 ), lastKey( r2 ) ], [ undefined, 'start_dt;' ] );
	const r3 = await mount( { use: 'key;now', key: 'start_dt' } );
	removeButtons( r3.tree )[ 0 ].props.onClick();
	check( 'D8.12 removing the field leaves the verb alone: `use:now`, no `key`', [ lastUse( r3 ), lastKey( r3 ) ], [ 'now', undefined ] );
	const r4 = await mount( { use: 'now;published' } );
	check( 'D8.13 two verbs fill the control: no Add, each read has a Remove', [ !! addButton( r4.tree ), removeButtons( r4.tree ).length ], [ false, 2 ] );
	const r5 = await mount( { use: 'key;key', key: 'a;b' } );
	pick( r5, 0, 'c' );
	check( 'D8.14 a redundant all-field `use` is not carried: an edit writes no `use`', [ lastUse( r5 ), lastKey( r5 ) ], [ undefined, 'c;b' ] );

	// Position is by add and remove only: nothing offers a reorder.
	check( 'D8.15 no reorder control: the only buttons are Add, Remove, and the time-field links', buttons( f4.tree ).map( function ( b ) { return b.children[ 0 ]; } ).filter( function ( t ) { return ! /^(\+ Add|Remove)/.test( t ); } ), [] );

	// Round trip: what the control writes, a reload shows as the same reads.
	const rt = await mount( {} );
	pick( rt, 0, 'start_dt' );
	let rtState = Object.assign( {}, rt.writes[ rt.writes.length - 1 ] );
	const rtAdd = await mount( rtState );
	addButton( rtAdd.tree ).props.onClick();
	rtState = rtAdd.writes[ rtAdd.writes.length - 1 ];
	const rtVerb = await mount( rtState );
	choose( rtVerb, 1, 'modified' );
	const back = await reload( rtVerb, {} );
	check( 'D8.16 serialize then reload shows the same reads (a field, then a verb), the field picker holding its field', [ readValues( back.tree ), pickers( back.tree ).map( function ( p ) { return p.props.context.state.key; } ), rtVerb.writes[ 0 ] ], [ [ 'key', 'modified' ], [ 'start_dt' ], { key: 'start_dt', use: 'key;modified' } ] );

	// Which verbs a source offers: `now` everywhere, the post dates for a post. The chain
	// vocabulary names the three roots only here: elsewhere a `site` source would narrow the picker.
	global.window.bwsChainKinds = { steps: {}, settles: '', roots: { site: 'site', term: 'term', post: 'post' } };
	const postSrc = await mount( { src: 'post,22' } );
	const termSrc = await mount( { src: 'term' } );
	const siteSrc = await mount( { src: 'site' } );
	check( 'D8.17 a post source offers every row', offered( postSrc.tree, 0 ), [ 'key', 'now', 'published', 'modified' ] );
	check( 'D8.18 a term source and a site source offer the field and now, not the post dates', [ offered( termSrc.tree, 0 ), offered( siteSrc.tree, 0 ) ], [ [ 'key', 'now' ], [ 'key', 'now' ] ] );
	const heldTerm = await mount( { src: 'term', use: 'published' } );
	check( 'D8.19 a read already on a post date keeps its row when the source is not a post, so it still displays as itself', [ readValues( heldTerm.tree ), offered( heldTerm.tree, 0 ) ], [ [ 'published' ], [ 'key', 'now', 'published' ] ] );

	global.window.bwsChainKinds = { steps: {}, settles: '', roots: {} };

	// The `use` select is the control's to write, so the editor does not show it.
	const useEl = { key: 'use' };
	check( 'D8.20 the `use` select is hidden on a tag whose `key` is this control', filter( useEl, { use: { type: 'select' }, key: KEY_DEF }, { state: {} } ), null );
	check( 'D8.21 ...and left alone on any other tag', filter( useEl, { use: { type: 'select' }, key: { type: 'bws-field-combo' } }, { state: {} } ), useEl );

	/* =====================================================================
	 * §D9 — an OLD tag's modal: the six old options rewrite to `key` on mount
	 * ================================================================== */

	// The old tags register the same control with `legacy` (their family), no verb rows, and
	// a read count the family can use. The rewrite itself is pinned by the shared corpus in
	// datetime-migration-test.php D8; this holds what the editor does with it.
	const oldRow = KEY_DEF.reads.slice( 0, 1 );
	const SINGLE_DEF = Object.assign( {}, KEY_DEF, { legacy: 'single', max: 1, reads: oldRow } );
	const RANGE_DEF = Object.assign( {}, KEY_DEF, { legacy: 'range', max: 2, reads: oldRow } );
	const oldOpts = function ( def, names ) {
		const o = { key: def };
		names.forEach( function ( n ) { o[ n ] = { type: 'bws-field-combo' }; } );
		return o;
	};
	const SINGLE_OPTS = oldOpts( SINGLE_DEF, [ 'timeKey' ] );
	const RANGE_OPTS = oldOpts( RANGE_DEF, [ 'startKey', 'startTimeKey', 'endKey', 'endTimeKey' ] );
	const through = function ( name, opts, state ) {
		return filter( { key: name }, opts, { state: state, setState: function () {} } );
	};
	/** Mount the migrator a `key` element carries and return what it does to `state`. */
	async function mountMigrates( element, state ) {
		const child = ( element.children || [] ).filter( function ( c ) { return c && c.props && c.props.setState; } )[ 0 ];
		if ( ! child ) { return '(no migrator)'; }
		const updaters = [];
		const patched = Object.assign( {}, child.props, { setState: function ( fn ) { updaters.push( fn ); } } );
		await render( child.type, patched );
		const next = updaters[ 0 ]( state );
		return { next: next, again: updaters[ 0 ]( next ), same: next === state };
	}

	const oldSingle = { key: 'a', timeKey: 't' };
	const mig = await mountMigrates( through( 'key', SINGLE_OPTS, oldSingle ), oldSingle );
	check( 'D9.1 an old single tag rewrites on mount: key and timeKey become `key:a+t`', mig.next, { key: 'a+t' } );
	check( 'D9.2 ...and the rewrite is a fixed point: a second pass returns the same object, so React stops', mig.again === mig.next, true );

	const oldRange = { startKey: 's', endKey: 'e', endTimeKey: 'et', fallback: 'TBA' };
	const migR = await mountMigrates( through( 'key', RANGE_OPTS, oldRange ), oldRange );
	// Key order is the PHP twin's corpus pin (D8); this harness loads no order normalizer.
	check( 'D9.3 an old range tag rewrites on mount, other options kept', [ migR.next.key, migR.next.fallback, Object.keys( migR.next ).length ], [ 's;e+et', 'TBA', 2 ] );

	check( 'D9.4 once migrated, the old controls are hidden and the read control (with its mount migrator) is the only thing left',
		[ through( 'timeKey', SINGLE_OPTS, { key: 'a+t' } ), through( 'startKey', RANGE_OPTS, { key: 's;e' } ), through( 'endTimeKey', RANGE_OPTS, { key: 's;e' } ), 'Fragment' === through( 'key', RANGE_OPTS, { key: 's;e' } ).type ],
		[ null, null, null, true ] );
	check( 'D9.5 before the rewrite lands the old controls are already hidden (no flash of six blank fields)',
		[ through( 'startKey', RANGE_OPTS, oldRange ), through( 'timeKey', SINGLE_OPTS, oldSingle ) ], [ null, null ] );

	// An end-only range is not rewritten, so it opens as it was: its own four fields,
	// and no read control to fight them.
	const endOnly = { endKey: 'e' };
	const heldEls = [ 'startKey', 'startTimeKey', 'endKey', 'endTimeKey' ].map( function ( n ) { return through( n, RANGE_OPTS, endOnly ); } );
	check( 'D9.6 an end-only old range keeps its four old controls', heldEls.map( function ( e ) { return e && e.key; } ), [ 'startKey', 'startTimeKey', 'endKey', 'endTimeKey' ] );
	check( 'D9.7 ...and shows no read control', through( 'key', RANGE_OPTS, endOnly ), null );

	// What the control draws for an old tag.
	const oneRead = await mount2( SINGLE_DEF, { key: 'a+t' } );
	check( 'D9.8 an old single tag offers one read: no Add, no read select (only one row to pick)', [ !! addButton( oneRead ), readSelects( oneRead ).length, pickers( oneRead ).length ], [ false, 0, 2 ] );
	const twoRead = await mount2( RANGE_DEF, { key: 's;e' } );
	check( 'D9.9 an old range tag shows its two reads with Start/End headings and no read select', [ headings( twoRead ), readSelects( twoRead ).length ], [ [ 'Start Date/Time', 'End Date/Time' ], 0 ] );
	const range1 = await mount2( RANGE_DEF, { key: 's' } );
	check( 'D9.10 ...and offers Add End Date/Time at one read', addButton( range1 ).children[ 0 ], '+ Add End Date/Time' );

	// The merged tag has no `legacy`, so none of this touches it.
	check( 'D9.11 a tag without `legacy` is untouched: no migrator, old-named keys pass through',
		[ 'Fragment' === through( 'key', { key: KEY_DEF }, { key: 'a', timeKey: 't' } ).type, through( 'timeKey', { key: KEY_DEF, timeKey: { type: 'x' } }, { timeKey: 't' } ).key ], [ false, 'timeKey' ] );

	/* =====================================================================
	 * §D10 — a value the control cannot show is named in a notice
	 * ================================================================== */

	const surplus = await mount( { use: 'now;key', key: 'a;b' } );
	check( 'D10.1 a `key` entry with no read left is named, and its removal stated',
		noticeTexts( surplus.tree ), [ [ 'warning', false, 'Field `b` has no read to use it and will be removed on your next change here.' ] ] );
	pick( surplus, 0, 'c' );
	check( 'D10.2 ...and it is: the next change writes the reads the control shows, `b` gone', [ lastUse( surplus ), lastKey( surplus ) ], [ 'now;key', 'c' ] );
	const surplus2 = await mount( { use: 'key;now', key: 'a;b+t;c' } );
	check( 'D10.3 several left over: every field of each is named, in order',
		noticeTexts( surplus2.tree ), [ [ 'warning', false, 'Fields `b`, `t`, `c` have no read to use them and will be removed on your next change here.' ] ] );
	const extra = await mount( { key: 'a+t+x' } );
	check( 'D10.4 an operand past the second is named as ignored, and no removal is claimed',
		noticeTexts( extra.tree ), [ [ 'warning', false, 'A read combines one date and one time field only, so `x` is ignored.' ] ] );
	pick( extra, 0, 'b' );
	check( 'D10.5 ...and it is kept: a change to the read writes it back', lastKey( extra ), 'b+t+x' );
	const extra2 = await mount( { key: 'a+t+x+y;e+u+z' } );
	check( 'D10.6 several past the second, across reads: one notice names them all',
		noticeTexts( extra2.tree ), [ [ 'warning', false, 'A read combines one date and one time field only, so `x`, `y`, `z` are ignored.' ] ] );
	const fine = [ {}, { key: 'a+t;e' }, { use: 'now;key', key: 'a' }, { use: 'key;now', key: ';' }, { use: 'now;key' } ];
	for ( const state of fine ) {
		check( 'D10.7 no notice on a consistent value: ' + JSON.stringify( state ), noticeTexts( ( await mount( state ) ).tree ), [] );
	}
	// Past `max` reads the render takes no more, so the control shows no more and names the rest.
	const three = await mount( { key: 'a;b+t;c' } );
	check( 'D10.8 a `key` entry past the most reads is not shown: two headed reads, no Add',
		[ headings( three.tree ), !! addButton( three.tree ) ], [ [ 'Start Date/Time', 'End Date/Time' ], false ] );
	check( 'D10.9 ...and is named as removed on the next change',
		noticeTexts( three.tree ), [ [ 'warning', false, 'Field `c` has no read to use it and will be removed on your next change here.' ] ] );
	pick( three, 0, 'x' );
	check( 'D10.10 ...and it is: the next change writes the two reads shown', lastKey( three ), 'x;b+t' );
	const threeUse = await mount( { use: 'key;now;key', key: 'a;b' } );
	check( 'D10.11 a `use` list past the most reads stops there too, and the entry its third read would take is named',
		[ readValues( threeUse.tree ), noticeTexts( threeUse.tree ) ], [ [ 'key', 'now' ], [ [ 'warning', false, 'Field `b` has no read to use it and will be removed on your next change here.' ] ] ] );
	/* =====================================================================
	 * §D11 — the control inside a slot card (try_datetime): exported, and "same" at slot 2+
	 * ================================================================== */

	// The slot repeater mounts this component itself, once per slot card, so each card has its
	// own Location and Type filters (they live in the component). It hands it a context and a
	// cfg: the definition above, plus `sameLabel` from slot 2.
	const Component = global.window.bwsDatetimeReadControl;
	check( 'D11.1 the component is exported for the slot repeater to mount', typeof Component, 'function' );
	async function mountSlot( state, extra ) {
		const writes = [];
		const props = { cfg: Object.assign( {}, KEY_DEF, extra || {} ), context: { state: state, setState: function ( u ) { writes.push( u ); } } };
		const tree = await render( Component, props );
		return { tree: tree, writes: writes };
	}
	const SAME = 'Same as Previous Slot';

	const noSame = await mountSlot( { key: 'a' } );
	check( 'D11.2 with no sameLabel (slot A, the base tag) the first read offers no "same" row', offered( noSame.tree, 0 ), [ 'key', 'now', 'published', 'modified' ] );
	const withSame = await mountSlot( { key: 'a' }, { sameLabel: SAME } );
	check( 'D11.3 with a sameLabel the first read\'s select leads with it, in that label', [ offered( withSame.tree, 0 )[ 0 ], readSelects( withSame.tree )[ 0 ].props.options[ 0 ].label ], [ 'same', SAME ] );
	check( 'D11.4 ...and the rest of the rows follow, unchanged', offered( withSame.tree, 0 ).slice( 1 ), [ 'key', 'now', 'published', 'modified' ] );

	const carrying = await mountSlot( { use: 'same' }, { sameLabel: SAME } );
	check( 'D11.5 use(same) shows the "same" row selected', readValues( carrying.tree ), [ 'same' ] );
	check( 'D11.6 ...hides the field pickers, the time field and the Add button', [ pickers( carrying.tree ).length, addTimeButtons( carrying.tree ).length, !! addButton( carrying.tree ) ], [ 0, 0, false ] );
	check( 'D11.7 ...and names no notice', noticeTexts( carrying.tree ), [] );

	choose( withSame, 0, 'same' );
	check( 'D11.8 choosing "same" writes use(same) and drops the key', [ withSame.writes[ 0 ].use, 'key' in withSame.writes[ 0 ] ], [ 'same', false ] );
	choose( carrying, 0, 'key' );
	check( 'D11.9 choosing the field row again writes no use and no key (an empty read)', [ 'use' in carrying.writes[ 0 ], 'key' in carrying.writes[ 0 ] ], [ false, false ] );
	choose( carrying, 0, 'now' );
	check( 'D11.10 choosing a verb from "same" writes that verb read', [ carrying.writes[ 1 ].use ], [ 'now' ] );

	const second = await mountSlot( { use: 'key;now', key: 'a' }, { sameLabel: SAME } );
	check( 'D11.11 "same" is offered on the first read only: a range\'s end read never reuses', [ offered( second.tree, 0 )[ 0 ], offered( second.tree, 1 ).indexOf( 'same' ) ], [ 'same', -1 ] );
	check( 'D11.12 a read the slot writes other than "same" leaves the picker and Add as the base tag has them', [ pickers( second.tree ).length, !! addButton( second.tree ) ], [ 1, false ] );


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
