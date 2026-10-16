/**
 * Harness for the {{table}} presentation controls (assets/js/table-style-control.js): what each
 * composite WRITES, which advisories it shows, and which repair it offers. Run:
 * `node tools/test/table-style-control-test.js` (exits non-zero on failure).
 *
 * Loads the SHIPPED grammar twin and control against stubbed wp globals, with the vocabulary
 * PHP inlines read through `php` (bws_table_style_vocabulary()), so the control is held to the
 * same lists the renderer uses. Components are expanded by calling them, and the tree is searched
 * for the element a user would operate; the real WP components, the combobox's behavior in a
 * browser and the screen-reader announcements are NOT reachable here and are measured in the
 * editor (ticket table-tag/07, "Measure at build").
 *
 * @package BWS_Dynamic_Tags
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );
const { execFileSync } = require( 'child_process' );

const root = path.resolve( __dirname, '../..' );

const vocab = JSON.parse( execFileSync( 'php', [ '-r', "define('ABSPATH',1); require 'includes/helpers/table-helpers.php'; echo json_encode(bws_table_style_vocabulary());" ], { cwd: root } ).toString() );

const filters = [];
global.window = { bwsTableVocab: vocab, bwsTableDefaults: { stripeColor: 'currentColor', stripeOpacity: 5 } };
global.wp = {
	hooks: { addFilter: function ( hook, ns, fn ) { filters.push( fn ); } },
	element: {
		createElement: function ( type, props ) {
			const kids = Array.prototype.slice.call( arguments, 2 );
			return { type: type, props: props || {}, children: kids };
		},
		Fragment: 'F',
		useState: function ( initial ) { return [ initial, function () {} ]; }
	},
	components: { ComboboxControl: 'Combo', SelectControl: 'Select', TextControl: 'Text', Button: 'Button' },
	data: { select: function () { return { getSettings: function () { return { colors: [ { name: 'Accent', slug: 'accent', color: '#00f' } ] }; } }; } },
	i18n: { __: function ( s ) { return s; } }
};
global.window.wp = global.wp;

function load( relative ) {
	const file = path.join( root, relative );
	vm.runInThisContext( fs.readFileSync( file, 'utf8' ), { filename: file } );
}
load( 'assets/js/serialization-order-normalizer.js' );
load( 'assets/js/slot-fold-grammar.js' );
load( 'assets/js/table-style-grammar.js' );
load( 'assets/js/table-style-control.js' );

const UI = global.window.bwsTableStyleUI;
if ( ! UI ) {
	console.error( 'control did not load' );
	process.exit( 2 );
}

let fail = 0;
let total = 0;
function check( label, got, want ) {
	total++;
	const ok = JSON.stringify( got ) === JSON.stringify( want );
	if ( ! ok ) {
		fail++;
	}
	console.log( ( ok ? 'PASS  ' : 'FAIL  ' ) + label + ( ok ? '' : '\n        got:  ' + JSON.stringify( got ) + '\n        want: ' + JSON.stringify( want ) ) );
}

/** Expand function components into a plain tree of host/stub nodes. */
function expand( node ) {
	if ( node === null || node === undefined || typeof node !== 'object' ) {
		return node;
	}
	if ( Array.isArray( node ) ) {
		return node.map( expand );
	}
	if ( typeof node.type === 'function' ) {
		return expand( node.type( Object.assign( {}, node.props, { children: node.children } ) ) );
	}
	return { type: node.type, props: node.props, children: ( node.children || [] ).map( expand ) };
}

function walk( node, fn ) {
	if ( Array.isArray( node ) ) {
		node.forEach( ( n ) => walk( n, fn ) );
		return;
	}
	if ( node && typeof node === 'object' ) {
		fn( node );
		( node.children || [] ).forEach( ( n ) => walk( n, fn ) );
		const p = node.props || {};
		[ 'help' ].forEach( ( k ) => p[ k ] && typeof p[ k ] === 'object' && walk( p[ k ], fn ) );
	}
}

function find( tree, pred ) {
	const out = [];
	walk( tree, ( n ) => {
		if ( pred( n ) ) {
			out.push( n );
		}
	} );
	return out;
}

function text( tree ) {
	let s = '';
	walk( tree, ( n ) => {
		[].concat( ...( n.children || [] ) ).forEach( ( c ) => {
			if ( typeof c === 'string' ) {
				s += c + ' ';
			}
		} );
	} );
	return s;
}

/** Render a composite against a state; returns { tree, writes } with every setState recorded. */
function mount( Comp, state, extra ) {
	const writes = [];
	const tree = expand( Comp( Object.assign( { label: 'L', context: { state: state, setState: ( s ) => writes.push( s ) } }, extra || {} ) ) );
	return { tree: tree, writes: writes };
}

function radio( tree, name, index ) {
	return find( tree, ( n ) => n.type === 'input' && n.props.type === 'radio' && n.props.name === name )[ index ];
}

function button( tree, label ) {
	return find( tree, ( n ) => n.type === 'Button' && n.children.some( ( c ) => c === label ) )[ 0 ];
}

// ── Sizing and Width ─────────────────────────────────────────────────────────────────
let m = mount( UI.SizingControl, {} );
radio( m.tree, 'bws-table-layout', 2 ).props.onChange();
check( 'Sizing: picking Fixed writes `layout:fixed` only', m.writes[ 0 ], { layout: 'fixed' } );
m = mount( UI.SizingControl, { layout: 'fixed', width: '100%', other: 'x' } );
radio( m.tree, 'bws-table-layout', 0 ).props.onChange();
check( 'Sizing: Theme deletes the key and leaves the rest of the state alone', m.writes[ 0 ], { width: '100%', other: 'x' } );
check( 'Sizing: Fixed with a real width shows no advisory', find( m.tree, ( n ) => n.props && n.props.role === 'status' ).length, 0 );
m = mount( UI.SizingControl, { layout: 'fixed', width: 'auto' } );
check( 'Sizing: Fixed with Table Width auto shows advisory 1, with the references italic', find( m.tree, ( n ) => n.type === 'em' ).map( ( n ) => n.children[ 0 ] ), [ 'Table Width', 'Auto' ] );
m = mount( UI.SizingControl, { layout: 'auto', width: 'auto' } );
check( 'Sizing: Auto with width auto shows none', find( m.tree, ( n ) => n.props && n.props.role === 'status' ).length, 0 );
m = mount( UI.SizingControl, { layout: 'banana' } );
check( 'Sizing: a hand-edited layout reads as Theme help, not a crash', text( m.tree ).indexOf( 'Whatever your theme does' ) !== -1, true );

// ── Width / size fields ───────────────────────────────────────────────────────────────
const widthField = find( mount( UI.SizingControl, { width: '8rem' } ).tree, ( n ) => n.type === 'Combo' )[ 0 ];
const rowsFor = ( typed ) => {
	let captured;
	// Re-render the field with a typed filter so the row list is observable.
	const f = UI.SizeField( { label: 'W', value: '', kind: 'width', onChange: () => {} } );
	captured = expand( f );
	return captured;
};
check( 'Width field: a stored value is the selected option', widthField.props.value, '8rem' );
check( 'Width field: the list opens with one hint row, disabled', [ widthField.props.options[ 0 ].disabled, widthField.props.options[ 0 ].value ], [ true, '__hint' ] );
let commits = [];
const sf = expand( UI.SizeField( { label: 'W', value: '', kind: 'width', onChange: ( v ) => commits.push( v ) } ) );
sf.props.onChange( '__hint' );
sf.props.onChange( '__invalid:calc(1px)' );
sf.props.onChange( '12rem' );
sf.props.onChange( null );
check( 'Width field: the hint row and an invalid row commit nothing; a value and a reset do', commits, [ '12rem', '' ] );
check( 'Width field: a refused stored value is shown with the "not a recognized width" error', find( expand( UI.SizeField( { label: 'W', value: 'calc(1px)', kind: 'width', onChange: () => {} } ) ), ( n ) => n.type === 'span' && /not a recognized width/.test( n.children.join( '' ) ) ).length, 1 );
check( 'Font size field: the hint names keywords', /keyword/.test( expand( UI.SizeField( { label: 'S', value: '', kind: 'size', onChange: () => {} } ) ).props.options[ 0 ].label ), true );

// ── Typography ───────────────────────────────────────────────────────────────────────
m = mount( UI.TypographyControl, {} );
const colorCombos = find( m.tree, ( n ) => n.type === 'Combo' && n.props.label === 'Text Color' );
check( 'Typography: a Text Color field at Table and at Header', colorCombos.length, 2 );
check( 'Typography: each Text Color field has its swatch beside it', find( m.tree, ( n ) => n.props && /^bws-ts-sw( |$)/.test( n.props.className || '' ) ).map( ( n ) => / is-empty/.test( n.props.className ) ), [ true, true ] );
colorCombos[ 0 ].props.onChange( '#333' );
check( 'Typography: Table color writes the bare base', m.writes[ 0 ], { color: '#333' } );
m = mount( UI.TypographyControl, { color: '#333' } );
find( m.tree, ( n ) => n.type === 'Combo' && n.props.label === 'Text Color' )[ 1 ].props.onChange( '#000' );
check( 'Typography: Header color writes `base;header,x`, base first', m.writes[ 0 ], { color: '#333;header,#000' } );
m = mount( UI.TypographyControl, { color: '#333;header,#000' } );
find( m.tree, ( n ) => n.type === 'Combo' && n.props.label === 'Text Color' )[ 0 ].props.onChange( '' );
check( 'Typography: clearing the base keeps the header override', m.writes[ 0 ], { color: 'header,#000' } );
m = mount( UI.TypographyControl, { color: 'header,#000' } );
find( m.tree, ( n ) => n.type === 'Combo' && n.props.label === 'Text Color' )[ 1 ].props.onChange( '' );
check( 'Typography: clearing the last value deletes the key', m.writes[ 0 ], {} );
m = mount( UI.TypographyControl, { fontWeight: 'normal' } );
find( m.tree, ( n ) => n.type === 'Select' && n.props.label === 'Font Weight' )[ 1 ].props.onChange( 'bold' );
check( 'Typography: weight select at Header', m.writes[ 0 ], { fontWeight: 'normal;header,bold' } );
check( 'Typography: an unset Header weight shows the table value as its default row', find( m.tree, ( n ) => n.type === 'Select' && n.props.label === 'Font Weight' )[ 1 ].props.children[ 0 ].children[ 0 ], 'normal (table)' );
check( 'Typography: the CSS globals follow a disabled separator row, as GB lists them (no heading)', ( ( rows ) => [ rows[ rows.length - 6 ].props.disabled, rows[ rows.length - 6 ].children[ 0 ], rows.slice( -5 ).map( ( r ) => r.props.value ).length, rows.some( ( r ) => r.type === 'optgroup' ) ] )( find( m.tree, ( n ) => n.type === 'Select' && n.props.label === 'Font Weight' )[ 0 ].props.children ), [ true, '—', 5, false ] );
m = mount( UI.TypographyControl, {} );
button( m.tree, 'Italic' ).props.onClick();
check( 'Typography: a button group writes its value', m.writes[ 0 ], { fontStyle: 'italic' } );
m = mount( UI.TypographyControl, { fontStyle: 'italic' } );
check( 'Typography: the pressed button is announced', button( m.tree, 'Italic' ).props[ 'aria-pressed' ], true );
button( m.tree, 'Italic' ).props.onClick();
check( 'Typography: pressing it again clears the key', m.writes[ 0 ], {} );
m = mount( UI.TypographyControl, { color: '#333', fontWeight: 'normal' } );
check( 'Typography: well-formed wire shows no repair', find( m.tree, ( n ) => n.props && n.props.className === 'bws-table-repair' ).length, 0 );
m = mount( UI.TypographyControl, { fontWeight: 'heavy;header,bold', textAlign: 'left;header,center)' } );
check( 'Typography: a bad value is flagged and keeps the sibling', find( m.tree, ( n ) => n.props && n.props.className === 'bws-table-repair' ).length, 2 );
button( m.tree, 'Fix' ).props.onClick();
check( 'Typography: Fix writes what the renderer already reads', m.writes[ 0 ], { fontWeight: 'header,bold', textAlign: 'left;header,center)' } );
button( m.tree, 'Clear Text Alignment' ).props.onClick();
check( 'Typography: an unbalanced bracket offers to clear the key', m.writes[ 1 ], { fontWeight: 'heavy;header,bold' } );

// ── Stripes ──────────────────────────────────────────────────────────────────────────
m = mount( UI.StripesControl, {} );
radio( m.tree, 'bws-table-stripes', 1 ).props.onChange();
check( 'Stripes: Rows writes the bare target (even is the default)', m.writes[ 0 ], { stripe: 'rows' } );
m = mount( UI.StripesControl, { stripe: 'rows,odd,#eee,40%' } );
radio( m.tree, 'bws-table-stripes', 3 ).props.onChange();
check( 'Stripes: adding Columns keeps the rows spec and starts columns linked to its look', m.writes[ 0 ], { stripe: 'rows,odd,#eee,40%;cols,#eee,40%' } );
// ── Link between rows and columns ──
m = mount( UI.StripesControl, { stripe: 'rows,#eee,40%;cols,#eee,40%' } );
const lk = ( t ) => find( t, ( n ) => n.type === 'Button' && n.props.icon );
check( 'Link: same look on both reads as linked (pressed), and columns show no color or opacity of their own', [ lk( m.tree )[ 0 ].props.isPressed, find( m.tree, ( n ) => n.type === 'Combo' && n.props.label === 'Stripe Color' ).length, find( m.tree, ( n ) => n.type === 'input' && n.props.type === 'range' ).length ], [ true, 1, 1 ] );
find( m.tree, ( n ) => n.type === 'Combo' && n.props.label === 'Stripe Color' )[ 0 ].props.onChange( '#ddd' );
check( 'Link: a rows color change is written to both targets', m.writes[ 0 ], { stripe: 'rows,#ddd,40%;cols,#ddd,40%' } );
m = mount( UI.StripesControl, { stripe: 'rows,#eee,40%;cols,#ddd,40%' } );
check( 'Link: different looks read as unlinked, toggle disabled, fix offered', [ lk( m.tree )[ 0 ].props.isPressed, lk( m.tree )[ 0 ].props.disabled, find( m.tree, ( n ) => n.type === 'Combo' && n.props.label === 'Stripe Color' ).length ], [ false, true, 2 ] );
button( m.tree, 'Use the Rows color for both' ).props.onClick();
check( 'Link: the fix writes the rows look onto columns', m.writes[ 0 ], { stripe: 'rows,#eee,40%;cols,#eee,40%' } );
m = mount( UI.StripesControl, { stripe: 'rows;cols,odd' } );
check( 'Link: rows and columns with no look of their own are linked; parity is never linked', [ lk( m.tree )[ 0 ].props.isPressed, find( m.tree, ( n ) => n.type === 'Select' && n.props.label === 'Pattern' ).length ], [ true, 2 ] );
m = mount( UI.StripesControl, { stripe: 'rows,odd,#eee,40%;cols' } );
radio( m.tree, 'bws-table-stripes', 1 ).props.onChange();
check( 'Stripes: narrowing to Rows drops the cols item only', m.writes[ 0 ], { stripe: 'rows,odd,#eee,40%' } );
radio( m.tree, 'bws-table-stripes', 0 ).props.onChange();
check( 'Stripes: None deletes the key', m.writes[ 1 ], {} );
m = mount( UI.StripesControl, { stripe: 'rows' } );
find( m.tree, ( n ) => n.type === 'Select' && n.props.label === 'Pattern' )[ 0 ].props.onChange( 'odd' );
check( 'Stripes: Pattern odd', m.writes[ 0 ], { stripe: 'rows,odd' } );
find( m.tree, ( n ) => n.type === 'Combo' && n.props.label === 'Stripe Color' )[ 0 ].props.onChange( 'rgba(0,0,0,.2)' );
check( 'Stripes: color is written as its own arg', m.writes[ 1 ], { stripe: 'rows,rgba(0,0,0,.2)' } );
find( m.tree, ( n ) => n.type === 'input' && n.props.type === 'range' )[ 0 ].props.onChange( { target: { value: '40' } } );
check( 'Stripes: opacity is written as N%', m.writes[ 2 ], { stripe: 'rows,40%' } );
const bar = ( t ) => find( t, ( n ) => n.props && /bws-ts-asb/.test( n.props.className || '' ) )[ 0 ];
check( 'Stripes: the opacity bar carries the opaque color and the composed thumb color; dashed only while the opacity is the default', [ bar( m.tree ).props.style, / is-default/.test( bar( m.tree ).props.className ) ], [ { '--bws-tc': 'color-mix(in srgb,currentColor 5%,transparent)', '--bws-tb': 'currentColor' }, true ] );
m = mount( UI.StripesControl, { stripe: 'rows,#c00,40%' } );
check( 'Stripes: a set color and opacity reach the bar, not dashed', [ bar( m.tree ).props.style, / is-default/.test( bar( m.tree ).props.className ) ], [ { '--bws-tc': 'color-mix(in srgb,#c00 40%,transparent)', '--bws-tb': '#c00' }, false ] );
check( 'Stripes: the color placeholder is the default itself', find( m.tree, ( n ) => n.type === 'Combo' && n.props.label === 'Stripe Color' )[ 0 ].props.placeholder, 'currentColor' );
m = mount( UI.StripesControl, { stripe: 'rows,rgba(0,0,0,.2),40%' } );
check( 'Stripes: a color with its own alpha replaces the slider with the note', [ find( m.tree, ( n ) => n.type === 'input' && n.props.type === 'range' ).length, /Opacity is set by the color/.test( text( m.tree ) ) ], [ 0, true ] );
m = mount( UI.StripesControl, { stripe: 'rows;cols' } );
check( 'Stripes: one matrix, a named row group per target', find( m.tree, ( n ) => n.props && n.props.className === 'bws-ts-smx-row' ).map( ( n ) => n.props[ 'aria-label' ] ), [ 'Row stripes', 'Column stripes' ] );
m = mount( UI.StripesControl, { stripe: 'rows,40%' } );
button( m.tree, 'Reset' ).props.onClick();
check( 'Stripes: Reset clears the opacity and keeps the rest', m.writes[ 0 ], { stripe: 'rows' } );
m = mount( UI.StripesControl, { stripe: 'rows,every' } );
check( 'Stripes: a bad sequence word is reported and no radio is lost', [ /"every" is not a color, an opacity or a sequence/.test( text( m.tree ) ), radio( m.tree, 'bws-table-stripes', 1 ).props.checked ], [ true, true ] );
button( m.tree, 'Fix' ).props.onClick();
check( 'Stripes: Fix adopts the effective value', m.writes[ 0 ], { stripe: 'rows' } );
m = mount( UI.StripesControl, { stripe: 'rows,rgba(0,0,0' } );
check( 'Stripes: an unbalanced bracket leaves no radio chosen', [ 0, 1, 2, 3 ].map( ( i ) => radio( m.tree, 'bws-table-stripes', i ).props.checked ), [ false, false, false, false ] );
button( m.tree, 'Clear Stripes' ).props.onClick();
check( 'Stripes: ...and offers to clear the key', m.writes[ 0 ], {} );
m = mount( UI.StripesControl, { stripe: 'banana;cols' } );
check( 'Stripes: an unknown target is reported, the valid one stands', [ /"banana" is not a recognized option/.test( text( m.tree ) ), radio( m.tree, 'bws-table-stripes', 2 ).props.checked ], [ true, true ] );

// ── The column card ──────────────────────────────────────────────────────────────────
function column( opts, tag ) {
	const writes = [];
	const tree = expand( UI.ColumnStyle( { slot: { header: null, chain: [], read: null, opts: opts, extra: [] }, tagState: tag || {}, write: ( s ) => writes.push( s ), slotKey: 'A' } ) );
	return { tree: tree, writes: writes };
}
let c = column( {} );
button( c.tree, 'Right' ).props.onClick();
check( 'Column: alignment writes the base token', c.writes[ 0 ].opts, { textAlign: 'right' } );
c = column( { textAlign: 'right' } );
check( 'Column: with no custom styles only the alignment block shows', find( c.tree, ( n ) => n.type === 'Combo' ).length, 0 );
c = column( { color: '#c00' } );
check( 'Column: a stored body style opens the Body matrix', find( c.tree, ( n ) => n.type === 'Combo' && n.props.label === 'Text Color' ).length, 1 );
check( 'Column: ...and "Body" reads as chosen', radio( c.tree, 'bws-table-colstyle-A', 1 ).props.checked, true );
c = column( { fontWeight: 'header,bold', color: '#c00' } );
check( 'Column: header and body values read as "Body and header"', radio( c.tree, 'bws-table-colstyle-A', 3 ).props.checked, true );
radio( c.tree, 'bws-table-colstyle-A', 1 ).props.onChange();
check( 'Column: narrowing to Body clears the header override and keeps the body', c.writes[ 0 ].opts, { color: '#c00' } );
radio( c.tree, 'bws-table-colstyle-A', 0 ).props.onChange();
check( 'Column: None clears both', c.writes[ 1 ].opts, {} );
c = column( { textAlign: 'right;header,center', color: '#c00' } );
radio( c.tree, 'bws-table-colstyle-A', 0 ).props.onChange();
check( 'Column: None keeps both alignments (their own block), clears the rest', c.writes[ 0 ].opts, { textAlign: 'right;header,center' } );
c = column( { textAlign: 'right' } );
find( c.tree, ( n ) => n.type === 'Button' && n.props.title === 'Center' )[ 1 ].props.onClick();
check( 'Column: the Header Alignment group writes the header token and keeps the base', c.writes[ 0 ].opts, { textAlign: 'right;header,center' } );
const shaded = ( tree ) => find( tree, ( n ) => n.type === 'Button' && n.props.style && n.props.style.background ).map( ( n ) => n.props.title );
c = column( {}, { textAlign: 'center;header,right' } );
check( 'Column: unset alignments shade what they inherit (body: table, header: table header)', shaded( c.tree ), [ 'Center', 'Right' ] );
c = column( { textAlign: 'left' }, { textAlign: 'center;header,right' } );
check( 'Column: a set body alignment is not shaded; the header then inherits it', shaded( c.tree ), [ 'Left' ] );
m = mount( UI.TypographyControl, { textAlign: 'center' } );
check( 'Typography: the Header Alignment group shades the table value; the Table group shades nothing', shaded( m.tree ), [ 'Center' ] );
c = column( { textAlign: 'right;header,center' } );
check( 'Column: an alignment-only header does not open the Header text styles', radio( c.tree, 'bws-table-colstyle-A', 0 ).props.checked, true );
c = column( { fontWeight: 'bold' } );
find( c.tree, ( n ) => n.type === 'Select' && n.props.label === 'Font Weight' )[ 0 ].props.onChange( '' );
check( 'Column: clearing the last value drops the token', c.writes[ 0 ].opts, {} );
c = column( { color: '#c00' }, { color: '#333' } );
check( 'Column: an unset body color shows the table value it inherits', find( c.tree, ( n ) => n.type === 'Combo' && n.props.label === 'Text Color' )[ 0 ].props.placeholder, '#333 (table)' );
c = column( { fontWeight: 'heavy', color: '#c00' } );
button( c.tree, 'Fix' ).props.onClick();
check( 'Column: a bad token is repaired to what the renderer reads', c.writes[ 0 ].opts, { color: '#c00' } );
const wfTree = expand( UI.ColumnWidthField( { slot: { opts: { width: '8rem' } }, tagState: { layout: 'fixed' }, write: () => {} } ) );
check( 'Column Width: Fixed needs no hint, placeholder is Equal share', [ find( wfTree, ( n ) => n.type === 'Combo' )[ 0 ].props.placeholder, /Column Sizing/.test( text( wfTree ) ) ], [ 'Equal share', false ] );
const wa = expand( UI.ColumnWidthField( { slot: { opts: {} }, tagState: { layout: 'auto' }, write: () => {} } ) );
check( 'Column Width: Auto says the width is a hint', find( wa, ( n ) => n.type === 'em' ).map( ( n ) => n.children[ 0 ] ), [ 'Column Sizing', 'Fixed' ] );

// ── GB's own icon button group, when its package is on the page ─────────────────────
global.window.gb = { stylesBuilder: { ButtonIconControl: 'GbButtons' } };
m = mount( UI.TypographyControl, { textAlign: 'center' } );
const gbAlign = find( m.tree, ( n ) => n.type === 'GbButtons' && n.props.buttons.some( ( b ) => b.value === 'center' ) )[ 0 ];
check( 'GB icon group: used when available, with the stored value', gbAlign.props.value, 'center' );
check( 'GB icon group: each alignment has an icon, labelled for assistive tech', gbAlign.props.buttons.map( ( b ) => [ b.label, !! b.icon ] ), [ [ 'Left', true ], [ 'Center', true ], [ 'Right', true ] ] );
gbAlign.props.onChange( 'right' );
check( 'GB icon group: writes through the same commit', m.writes[ 0 ], { textAlign: 'right' } );
delete global.window.gb;

// ── Color list separators ────────────────────────────────────────────────────────────
const colorRows = find( mount( UI.TypographyControl, {} ).tree, ( n ) => n.type === 'Combo' && n.props.label === 'Text Color' )[ 0 ].props.options;
check( 'Color list: dash rows join the groups, disabled', colorRows.filter( ( r ) => r.label === '—' ).every( ( r ) => r.disabled ) && colorRows.some( ( r ) => r.label === '—' ), true );
check( 'Color list: no heading rows', colorRows.some( ( r ) => /^(Site colors|Color names|CSS values)$/.test( r.label ) ), false );
check( 'Color list: names group ends in the search hint', colorRows.some( ( r ) => r.label === 'Type to search more color names.' ), true );
const colorField = expand( UI.ColorField( { label: 'C', value: '', onChange: ( v ) => commits.push( v ) } ) );
commits = [];
colorField.props.onChange( '__sep:1' );
colorField.props.onChange( '__hint:names' );
check( 'Color list: picking a separator or hint commits nothing', commits, [] );

// ── Font Size list ───────────────────────────────────────────────────────────────────
const sizeRows = find( mount( UI.TypographyControl, {} ).tree, ( n ) => n.type === 'Combo' && n.props.label === 'Font Size' )[ 0 ].props.options;
const sizeSeq = sizeRows.map( ( r ) => ( r.label === '—' ? '—' : r.value ) );
const iLarger = sizeSeq.indexOf( 'larger' );
check( 'Font Size list: larger/smaller sit between dash rows, apart from xx-small…xxx-large', [ sizeSeq[ iLarger - 1 ], sizeSeq[ iLarger + 1 ], sizeSeq[ iLarger + 2 ], sizeSeq.indexOf( 'xxx-large' ) < iLarger ], [ '—', 'smaller', '—', true ] );
check( 'Font Size list: the CSS globals follow a final dash row', sizeSeq[ sizeSeq.indexOf( 'inherit' ) - 1 ], '—' );

// ── Table Width list ─────────────────────────────────────────────────────────────────
const tw = find( expand( UI.SizingControl( { label: 'Sizing', context: { state: {}, setState: () => {} } } ) ), ( n ) => n.type === 'Combo' && n.props.label === 'Table Width' )[ 0 ];
check( 'Table Width list: auto and 100% are offered', tw.props.options.map( ( r ) => r.value ).filter( ( v ) => v[ 0 ] !== '_' ), [ 'auto', '100%' ] );

// ── The filter ───────────────────────────────────────────────────────────────────────
const filter = filters[ filters.length - 1 ];
const opts = { layout: { type: 'bws-table-sizing' }, width: { type: 'bws-table-style-member' }, color: { type: 'bws-table-typography' }, stripe: { type: 'bws-table-stripes' }, other: { type: 'text' } };
const ctx = { state: {}, setState: () => {} };
check( 'Filter: a member renders nothing', filter( { key: 'width' }, opts, ctx ), null );
check( 'Filter: a composite is keyed by its option', [ 'layout', 'color', 'stripe' ].map( ( k ) => filter( { key: k }, opts, ctx ).props.key ), [ 'layout', 'color', 'stripe' ] );
const keep = { key: 'other' };
check( 'Filter: any other option passes through', filter( keep, opts, ctx ), keep );
check( 'Filter: an element a prior filter hid stays hidden', filter( null, opts, ctx ), null );

// ── Copy ─────────────────────────────────────────────────────────────────────────────
check( 'rich(): stars become <em>, the label text stays a plain child', UI.rich( 'Set *Auto* now' ).map( ( p ) => ( typeof p === 'string' ? p : p.type + ':' + p.children[ 0 ] ) ), [ 'Set ', 'em:Auto', ' now' ] );
check( 'rich(): text with no stars is returned untouched', UI.rich( 'plain' ), 'plain' );

console.log( '\n' + ( total - fail ) + '/' + total + ' passed' );
process.exit( fail ? 1 : 0 );
