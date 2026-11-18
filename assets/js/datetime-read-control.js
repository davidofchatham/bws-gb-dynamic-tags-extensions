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
 * THE TIME PICKER IS BEHIND "Add Time Field" until the read has a time field. A read that names
 * one opens its picker and keeps it open until Remove Time Field; the open state is local and
 * never on the wire.
 *
 * Every heading, label and count arrives on the option definition (bws_get_datetime_reads_option()).
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
	var useEffect = wp.element.useEffect;
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

		// Per read, whether its time picker is open. Editor-only, never on the wire, and dense
		// (one boolean per read) so removing a read can drop its slot.
		var timeState = useState( [] );
		var timeOpen  = timeState[ 0 ];

		function setTimeOpen( i, open ) {
			timeState[ 1 ]( function ( prev ) {
				var next = prev.slice();
				while ( next.length <= i ) { next.push( false ); }
				next[ i ] = open;
				return next;
			} );
		}

		// A read that names a time field opens its picker and keeps it open, so clearing the
		// field (or emptying it to retype) leaves the picker where it was. Only Remove Time
		// Field closes it.
		useEffect( function () {
			entries.forEach( function ( ops, i ) {
				if ( ops[ 1 ] && ! timeOpen[ i ] ) { setTimeOpen( i, true ); }
			} );
		} );

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
					}, ( cfg.entryLabels && cfg.entryLabels[ i ] ) || '' ),
					el( Button, {
						key: 'rm',
						variant: 'tertiary',
						isDestructive: true,
						style: { marginRight: '-8px' },
						onClick: function () {
							timeState[ 1 ]( function ( prev ) { return prev.filter( function ( s, n ) { return n !== i; } ); } );
							write( entries.filter( function ( e, n ) { return n !== i; } ) );
						}
					}, cfg.removeLabel )
				] ) );
			}
			kids.push( picker( i, 0 ) );
			if ( ops[ 1 ] || timeOpen[ i ] ) {
				// Not the read's Remove: a link in the picker's label row, named for the field it
				// drops. The label is the combo's own, so the link is laid over its right end.
				kids.push( el( 'div', { key: 'time-' + i, style: { position: 'relative', marginBottom: '12px' } }, [
					picker( i, 1 ),
					el( Button, {
						key: 'rm-time',
						variant: 'link',
						isDestructive: true,
						// `top` puts the link on the combo's label line: the label row sits about
						// 11px below the wrapper's top edge. Tuned by eye in the testbed editor.
						style: { position: 'absolute', top: '11px', right: 0, height: 'auto', fontSize: '12px' },
						onClick: function () {
							setTimeOpen( i, false );
							if ( ops[ 1 ] ) {
								write( setOperand( entries, i, 1, '' ) );
							}
						}
					}, cfg.timeRemoveLabel )
				] ) );
			} else {
				kids.push( el( Button, {
					key: 'add-time',
					variant: 'link',
					style: { display: 'block', margin: '8px 0 12px' },
					onClick: function () {
						setTimeOpen( i, true );
					}
				}, '+ ' + cfg.timeAddLabel ) );
			}
			children.push( el( Fragment, { key: 'entry-' + i }, kids ) );
		} );

		if ( count < max ) {
			children.push( el( Button, {
				key: 'add',
				variant: 'secondary',
				style: { marginTop: '12px' },
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
					entryLabels: cfg.entryLabels,
					addLabel: cfg.addLabel,
					timeAddLabel: cfg.timeAddLabel,
					timeRemoveLabel: cfg.timeRemoveLabel,
					removeLabel: cfg.removeLabel,
					max: cfg.max,
					typeDefault: cfg.typeDefault
				},
				context: context
			} );
		}
	);
}() );
