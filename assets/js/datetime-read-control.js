/**
 * The `key` option of `{{datetime}}` and `{{try_datetime}}`: the list of reads, one `;` entry
 * each, and inside an entry a primary field and an optional separate time field joined by `+`.
 *
 *   key:event_datetime                      one read
 *   key:event_date+start_time               one read, date and time in two fields
 *   key:event_date+start_time;end_time      two reads: a range
 *
 * THE GRAMMAR IS bws_datetime_reads()'s: PHP owns what the value means, and this file only edits
 * the value it reads, splitting it into reads and a read's fields as that function does.
 * It writes `key` and nothing else, so the stripped-default `use` rule and the serialization
 * order stay with the controls that own them.
 *
 * THE CONCEPTS ARE THE SLOT REPEATER'S, NOT ITS MACHINERY (slot-fold-control.js):
 *   1. ADD WRITES AN EXPLICIT SEED. A second read is the entry `''`, written as a real `;`, not
 *      revealed by state the wire never held.
 *   2. AN ABSENT VALUE DISPLAYS AS THE DEFAULT IT SPELLS: no `key` is one empty read, so the
 *      control opens on one read with Add offered, never on nothing.
 *   3. REMOVAL COMPACTS. Removing the first of two reads makes the second the first; no hole.
 *   4. ADD GATES ON AVAILABILITY. It disappears at `max` reads (PHP: BWS_DATETIME_MAX_READS).
 *
 * ONE LOCATION FILTER AND ONE TYPE FILTER serve every picker here. Their state is hoisted into
 * this control and handed to each picker as `sharedFilters`; the first picker draws the pair and
 * the rest hide theirs (the field combo's own `scopeToRepeater` hides a pair the same way). Two
 * pairs would be two places to set one view of the same pool.
 *
 * Every noun, label and count arrives on the option definition (bws_get_datetime_reads_option()).
 *
 * @package BWS_Dynamic_Tags
 * @since   Unreleased
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.hooks || ! wp.element || ! wp.components ) {
		return;
	}

	var el        = wp.element.createElement;
	var Fragment  = wp.element.Fragment;
	var useState  = wp.element.useState;
	var Button    = wp.components.Button;
	var __        = ( wp.i18n && wp.i18n.__ ) || function ( s ) { return s; };

	/**
	 * `key` wire → entries, each an array of operands (`['date', 'time']`). An absent or blank
	 * wire is ONE empty entry: the read it spells (concept 2).
	 *
	 * @param {string} raw The `key` value.
	 * @return {string[][]} Entries, at least one.
	 */
	function parse( raw ) {
		var wire = String( raw || '' ).trim();
		if ( '' === wire ) {
			return [ [ '' ] ];
		}
		return wire.split( ';' ).map( function ( entry ) {
			return entry.split( '+' ).map( function ( op ) { return op.trim(); } );
		} );
	}

	/**
	 * Entries → `key` wire. Trailing empty operands drop, so a time field left unset is
	 * never written; one empty entry is no wire at all, and any more are written as `;` (the
	 * seed a second read starts from).
	 *
	 * @param {string[][]} entries
	 * @return {string} '' when there is nothing to write.
	 */
	function emit( entries ) {
		var parts = entries.map( function ( ops ) {
			var kept = ops.slice();
			while ( kept.length > 1 && '' === kept[ kept.length - 1 ] ) {
				kept.pop();
			}
			return kept.join( '+' );
		} );
		return ( 1 === parts.length && '' === parts[ 0 ] ) ? '' : parts.join( ';' );
	}

	/** Entries with operand `j` of entry `i` set to `value`; the rest of the entry is kept. */
	function setOperand( entries, i, j, value ) {
		return entries.map( function ( ops, n ) {
			if ( n !== i ) { return ops; }
			var next = ops.slice();
			while ( next.length <= j ) { next.push( '' ); }
			next[ j ] = value;
			return next;
		} );
	}

	function DatetimeReadControl( props ) {
		var ctx      = props.context;
		var state    = ctx.state || {};
		var setState = ctx.setState;
		var cfg      = props.cfg;
		var FieldCombo = window.bwsFieldComboControl;
		var max      = Math.max( 1, parseInt( cfg.max, 10 ) || 1 );

		var locState  = useState( null );
		var typeState = useState( null );

		var entries = parse( state.key );
		var count   = entries.length;

		function write( next ) {
			var upd = Object.assign( {}, state );
			var wire = emit( next );
			if ( wire ) {
				upd.key = wire;
			} else {
				delete upd.key;
			}
			setState( upd );
		}

		/** A picker's context: the tag's own state with `key` replaced by the one operand it edits. */
		function operandContext( i, j ) {
			return {
				state: Object.assign( {}, state, { key: entries[ i ][ j ] || '' } ),
				setState: function ( upd ) {
					write( setOperand( entries, i, j, ( upd && 'undefined' !== typeof upd.key ) ? upd.key : '' ) );
				}
			};
		}

		function picker( i, j ) {
			var first = 0 === i && 0 === j;
			var time  = 1 === j;
			return el( FieldCombo, {
				key: 'op-' + i + '-' + j,
				optionKey: 'key',
				label: time ? cfg.timeLabel : cfg.label,
				help: time ? cfg.timeHelp : cfg.help,
				placeholder: time ? cfg.timePlaceholder : cfg.placeholder,
				typeDefault: cfg.typeDefault,
				sharedFilters: {
					loc: locState[ 0 ],
					setLoc: locState[ 1 ],
					type: typeState[ 0 ],
					setType: typeState[ 1 ],
					show: first
				},
				context: operandContext( i, j )
			} );
		}

		var children = [];

		entries.forEach( function ( ops, i ) {
			var kids = [];
			if ( count > 1 ) {
				kids.push( el( 'div', {
					key: 'hdr',
					style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '8px' }
				}, [
					el( 'strong', {
						key: 'ttl',
						style: { fontSize: '12px', textTransform: 'uppercase', letterSpacing: '0.4px' }
					}, cfg.noun + ' ' + ( i + 1 ) ),
					el( Button, {
						key: 'rm',
						variant: 'tertiary',
						isDestructive: true,
						style: { marginRight: '-8px' },
						onClick: function () {
							write( entries.filter( function ( e, n ) { return n !== i; } ) );
						}
					}, cfg.removeLabel )
				] ) );
			}
			kids.push( picker( i, 0 ), picker( i, 1 ) );
			children.push( el( Fragment, { key: 'entry-' + i }, kids ) );
		} );

		if ( count < max ) {
			children.push( el( Button, {
				key: 'add',
				variant: 'secondary',
				onClick: function () { write( entries.concat( [ [ '' ] ] ) ); }
			}, '+ ' + cfg.addLabel ) );
		}

		return el( Fragment, null, children );
	}

	wp.hooks.addFilter(
		'generateblocks.editor.tagSpecificControls',
		'bws/datetime-read-control',
		function ( element, allOptions, context ) {
			// Compose: a control a prior filter hid stays hidden.
			if ( ! element || ! allOptions || ! context ) {
				return element;
			}
			var cfg = allOptions[ element.key ];
			if ( ! cfg || 'bws-datetime-reads' !== cfg.type ) {
				return element;
			}
			return el( DatetimeReadControl, {
				key: element.key,
				cfg: {
					label: cfg.label,
					help: cfg.help,
					placeholder: cfg.placeholder,
					timeLabel: cfg.timeLabel,
					timeHelp: cfg.timeHelp,
					timePlaceholder: cfg.timePlaceholder,
					noun: cfg.noun,
					addLabel: cfg.addLabel,
					removeLabel: cfg.removeLabel,
					max: cfg.max,
					typeDefault: cfg.typeDefault
				},
				context: context
			} );
		}
	);
}() );
