/**
 * The `key` option of `{{datetime}}`, and the `key` / `use` tokens of each `{{try_datetime}}` slot (the
 * slot card mounts this component; see window.bwsDatetimeReadControl): the list of reads, one `;`
 * entry each, and inside an entry a primary field and an optional separate time field joined by `+`.
 *
 *   key:event_datetime                      one read
 *   key:event_date+start_time               one read, date and time in two fields
 *   key:event_date+start_time;end_time      two reads: a range
 *
 *   use:key;now|key:event_date               two reads: a field, then now (an ongoing range)
 *   use:published                            one verb read, no `key`
 *
 * THE GRAMMAR IS bws_datetime_reads()'s: PHP owns what the value means, and this file only edits
 * the values it reads, splitting them into reads and a read's fields as that function does.
 * It writes `key` and `use` and nothing else. `use` is written only when a verb is among the
 * reads, and then as the full ordered list, `key` included (the position is what tells "from a
 * field until now" from "from now until a field"); with only field reads there is no `use`.
 * That is the stripped-default rule's output, stated here as a consequence: the owner is
 * bws_use_effective(). The `use` select the editor would otherwise show is hidden, since this
 * control is the only thing that writes it. The serialization order stays with its own control.
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
 * EACH READ HAS ITS OWN SELECT: a field, or a verb. The rows come from the option definition
 * (`reads`, bws_get_datetime_read_choices()), and a row that lists `kinds` is offered only when
 * the source resolves to one of them, or to no known kind (a render-time source). The row a read
 * already holds is always kept, so a read written for another source still displays as itself.
 *
 * Every heading, label and count arrives on the option definition (bws_get_datetime_reads_option()).
 *
 * @package BWS_Dynamic_Tags
 * @since   Unreleased
 */
( function () {
	'use strict';

	// ── The old tags' mount rewrite (the pure layer) ────────────────────────
	// TWIN of bws_datetime_legacy_reads_rewrite() (includes/tags/datetime-tags.php), which
	// states every rule. The two must agree on the same inputs, key order included: the
	// shared corpus tools/test/datetime-mount-corpus.json is run through both
	// (datetime-migration-test.php D8). Exported before the editor guard below so a harness
	// can load it without WordPress.

	var LEGACY_KEYS = {
		single: [ 'key', 'timeKey' ],
		range: [ 'startKey', 'startTimeKey', 'endKey', 'endTimeKey' ]
	};

	function legacyText( state, name ) {
		var v = state[ name ];
		return ( null === v || undefined === v ) ? '' : String( v ).trim();
	}

	function legacyEntry( field, time ) {
		return '' === time ? field : field + '+' + time;
	}

	/**
	 * @param {Object} state  The tag's options.
	 * @param {string} family `single` or `range`: the old tag's own family.
	 * @return {Object|null} Rewritten options in canonical order, or null when nothing changes.
	 */
	function legacyRewrite( state, family ) {
		var names = LEGACY_KEYS[ family ];
		var text  = {};
		var clean = true;
		if ( ! names ) {
			return null;
		}
		names.forEach( function ( name ) {
			text[ name ] = legacyText( state, name );
			clean = clean && -1 === text[ name ].indexOf( ';' ) && -1 === text[ name ].indexOf( '+' );
		} );
		if ( ! clean ) {
			return null;
		}
		var key;
		if ( 'range' === family ) {
			// No start: the end-only range, or nothing stated at all.
			if ( '' === text.startKey + text.startTimeKey ) {
				return null;
			}
			var start = legacyEntry( text.startKey, text.startTimeKey );
			var end   = legacyEntry( text.endKey, text.endTimeKey );
			key = '' === end ? start : start + ';' + end;
		} else {
			if ( ! Object.prototype.hasOwnProperty.call( state, 'timeKey' ) ) {
				return null;
			}
			key = legacyEntry( text.key, text.timeKey );
		}
		var out = {};
		Object.keys( state ).forEach( function ( name ) {
			if ( -1 === names.indexOf( name ) ) {
				out[ name ] = state[ name ];
			}
		} );
		if ( '' !== key ) {
			out.key = key;
		}
		var keys = Object.keys( out );
		if ( 'function' === typeof window.bwsReorderKeys ) {
			keys = window.bwsReorderKeys( keys );
		}
		var ordered = {};
		keys.forEach( function ( name ) {
			ordered[ name ] = out[ name ];
		} );
		return ordered;
	}

	/**
	 * Whether an old RANGE tag holds its old options: some are set and the rewrite declines
	 * (the end-only range, or an operand that is not a clean entry). A single never holds, so
	 * its `key` control is never taken away.
	 */
	function legacyHold( state, family ) {
		return 'range' === family
			&& LEGACY_KEYS.range.some( function ( name ) { return '' !== legacyText( state, name ); } )
			&& null === legacyRewrite( state, family );
	}

	window.bwsDatetimeLegacy = { rewrite: legacyRewrite, hold: legacyHold };

	if ( ! window.wp || ! wp.hooks || ! wp.element || ! wp.components || ! wp.components.SelectControl ) {
		return;
	}

	var el        = wp.element.createElement;
	var Fragment  = wp.element.Fragment;
	var useState  = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var Button    = wp.components.Button;
	var SelectControl = wp.components.SelectControl;
	// A read's heading is WordPress's own control label, so it matches the labels around it
	// without restating their type.
	var VisualLabel   = ( wp.components.BaseControl && wp.components.BaseControl.VisualLabel ) || 'strong';
	var __       = ( wp.i18n && wp.i18n.__ ) || function ( s ) { return s; };

	/** `key` wire → entries, each an array of operands (`['date', 'time']`). Blank is no entries. */
	function entriesOf( raw ) {
		var wire = String( raw || '' ).trim();
		if ( '' === wire ) {
			return [];
		}
		return wire.split( ';' ).map( function ( entry ) {
			return entry.split( '+' ).map( function ( op ) { return op.trim(); } );
		} );
	}
	/** A field read: `ops` is its operands, the primary field first. */
	function fieldRead( ops ) {
		return { verb: '', ops: ops };
	}
	/**
	 * The tag's state → reads, in order, each `{ verb, ops }` (`verb` '' for a field read).
	 *
	 * With no verb in `use` the reads are the `key` entries, one each, and none at all is ONE
	 * empty read: the read an absent value spells (concept 2). With a verb, `use` is the list:
	 * each `key` in it takes the next entry (none left is an empty read), a verb takes none.
	 * Either way there are at most `max` reads, the most the render takes. An entry no read
	 * takes (left over after the list, or past `max`) is no read: the control cannot show it,
	 * and its next write drops it, so its fields come back as `surplus` for the notice to name
	 * first.
	 *
	 * @param {Object}   state `extraTagParams`.
	 * @param {string[]} verbs The verb names the option definition offers.
	 * @param {number}   max   The most reads (the option definition's `max`).
	 * @return {{reads: Object[], surplus: string[]}} Reads, at least one, and the fields of
	 *         the entries no read takes.
	 */
	function parseReads( state, verbs, max ) {
		var entries = entriesOf( state.key );
		var names   = String( state.use || '' ).split( ';' ).map( function ( n ) { return n.trim(); } ).filter( function ( n ) {
			return 'key' === n || -1 !== verbs.indexOf( n );
		} ).slice( 0, max );
		var reads;
		if ( ! names.some( function ( n ) { return 'key' !== n; } ) ) {
			reads = entries.length ? entries.splice( 0, max ).map( fieldRead ) : [ fieldRead( [ '' ] ) ];
		} else {
			reads = names.map( function ( n ) {
				return 'key' === n ? fieldRead( entries.shift() || [ '' ] ) : { verb: n, ops: [] };
			} );
		}
		return { reads: reads, surplus: [].concat.apply( [], entries ).filter( Boolean ) };
	}

	/**
	 * A notice's text: the singular or plural template (`[ one, many ]`, from the option
	 * definition) with its `%s` replaced by the names, each as code.
	 */
	function noticeText( templates, names ) {
		var parts = String( templates[ 1 === names.length ? 0 : 1 ] || '' ).split( '%s' );
		var named = [];
		names.forEach( function ( name, n ) {
			if ( n ) { named.push( ', ' ); }
			named.push( el( 'code', { key: 'n' + n }, name ) );
		} );
		return [ parts[ 0 ] ].concat( named, parts.slice( 1 ).join( '%s' ) );
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

	/** Reads with operand `j` of read `i` set to `value`; the rest of the read is kept. */
	function setOperand( reads, i, j, value ) {
		return reads.map( function ( read, n ) {
			if ( n !== i ) { return read; }
			var next = read.ops.slice();
			while ( next.length <= j ) { next.push( '' ); }
			next[ j ] = value;
			return fieldRead( next );
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
		// In a try_datetime slot card from slot B on, the first read may instead be "the previous
		// slot's reads" (`use` = `same`): then that select is the whole control. Slot A and the base
		// tag pass no label, so for them `same` is no row and no state.
		var sameLabel = cfg.sameLabel || '';
		var isSame    = !! sameLabel && 'same' === String( state.use || '' ).trim();
		var choices = cfg.reads || [];
		var verbs   = choices.map( function ( c ) { return c.value; } ).filter( function ( v ) { return 'key' !== v; } );
		var parsed  = parseReads( state, verbs, max );
		var reads   = parsed.reads;
		var count   = reads.length;
		// The kind the source chain resolves to, or null when it names none (a render-time
		// source): a row limited to some kinds is offered for null too, since it may be one.
		var kind    = ( FieldCombo && FieldCombo.presetKind ) ? FieldCombo.presetKind( state, 'key' ) : null;

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
			reads.forEach( function ( read, i ) {
				if ( read.ops[ 1 ] && ! timeOpen[ i ] ) { setTimeOpen( i, true ); }
			} );
		} );

		/** Commit the reads: `use` only when a verb is among them, `key` for the field reads. */
		function write( next ) {
			var upd  = Object.assign( {}, state );
			var wire = emit( next.filter( function ( r ) { return ! r.verb; } ).map( function ( r ) { return r.ops; } ) );
			if ( next.some( function ( r ) { return r.verb; } ) ) {
				upd.use = next.map( function ( r ) { return r.verb || 'key'; } ).join( ';' );
			} else {
				delete upd.use;
			}
			if ( wire ) {
				upd.key = wire;
			} else {
				delete upd.key;
			}
			setState( upd );
		}

		/** The rows read `i` can show: the kind-limited ones only where the source allows, its own always. */
		function rowsFor( read, i ) {
			var cur = read.verb || 'key';
			var rows = choices.filter( function ( c ) {
				return c.value === cur || ! c.kinds || ! kind || -1 !== c.kinds.indexOf( kind );
			} ).map( function ( c ) { return { value: c.value, label: c.label }; } );
			return ( sameLabel && 0 === i ) ? [ { value: 'same', label: sameLabel } ].concat( rows ) : rows;
		}

		/** A picker's context: the tag's own state with `key` replaced by the one operand it edits. */
		function operandContext( i, j ) {
			return {
				state: Object.assign( {}, state, { key: reads[ i ].ops[ j ] || '' } ),
				setState: function ( upd ) {
					write( setOperand( reads, i, j, ( upd && 'undefined' !== typeof upd.key ) ? upd.key : '' ) );
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

		reads.forEach( function ( read, i ) {
			var kids = [];
			if ( count > 1 ) {
				kids.push( el( 'div', {
					key: 'hdr',
					style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '8px' }
				}, [
					// The row spaces the heading; the label's own bottom margin would push it off
					// the Remove button's center line.
					el( VisualLabel, {
						key: 'ttl',
						style: { marginBottom: 0 }
					}, ( cfg.entryLabels && cfg.entryLabels[ i ] ) || '' ),
					el( Button, {
						key: 'rm',
						variant: 'tertiary',
						isDestructive: true,
						style: { marginRight: '-8px' },
						onClick: function () {
							timeState[ 1 ]( function ( prev ) { return prev.filter( function ( s, n ) { return n !== i; } ); } );
							write( reads.filter( function ( e, n ) { return n !== i; } ) );
						}
					}, cfg.removeLabel )
				] ) );
			}
			// Which read this is, by its own select. Picking a row replaces the read: a verb
			// takes no operands, and a field starts empty, so no stale field rides a verb.
			// An old tag offers only the field row, so there is nothing to choose and no select.
			if ( choices.length > 1 ) {
				kids.push( el( 'div', { key: 'read', style: { marginBottom: '12px' } },
					el( SelectControl, {
						label: cfg.readLabel,
						value: ( isSame && 0 === i ) ? 'same' : ( read.verb || 'key' ),
						options: rowsFor( read, i ),
						__nextHasNoMarginBottom: true,
						onChange: function ( v ) {
							if ( v === ( ( isSame && 0 === i ) ? 'same' : ( read.verb || 'key' ) ) ) { return; }
							setTimeOpen( i, false );
							if ( 'same' === v ) {
								var carry = Object.assign( {}, state, { use: 'same' } );
								delete carry.key;
								setState( carry );
								return;
							}
							write( reads.map( function ( r, n ) {
								return n !== i ? r : ( 'key' === v ? fieldRead( [ '' ] ) : { verb: v, ops: [] } );
							} ) );
						}
					} )
				) );
			}
			if ( read.verb || isSame ) {
				children.push( el( Fragment, { key: 'entry-' + i }, kids ) );
				return;
			}
			kids.push( picker( i, 0 ) );
			if ( read.ops[ 1 ] || timeOpen[ i ] ) {
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
							if ( read.ops[ 1 ] ) {
								write( setOperand( reads, i, 1, '' ) );
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

		// What the reads above cannot show, named before it is lost or while it is ignored: an
		// entry no read takes (the next write drops it) and an operand past a read's second
		// (kept, and ignored at render: bws_datetime_reads()).
		var extra = [].concat.apply( [], reads.map( function ( r ) { return r.ops.slice( 2 ); } ) ).filter( Boolean );
		[ [ 'surplus', parsed.surplus, cfg.surplusNotice ], [ 'extra', extra, cfg.extraNotice ] ].forEach( function ( n ) {
			if ( n[ 1 ].length && wp.components.Notice ) {
				children.push( el( wp.components.Notice, { key: n[ 0 ], status: 'warning', isDismissible: false },
					noticeText( n[ 2 ] || [], n[ 1 ] ) ) );
			}
		} );

		if ( count < max && ! isSame ) {
			children.push( el( Button, {
				key: 'add',
				variant: 'secondary',
				style: { marginTop: '12px' },
				onClick: function () { write( reads.concat( [ fieldRead( [ '' ] ) ] ) ); }
			}, '+ ' + cfg.addLabel ) );
		}

		return el( Fragment, null, children );
	}

	// Exported for the slot repeater, which mounts it (through createElement, so each slot card
	// keeps its own filters) in a try_datetime slot card.
	window.bwsDatetimeReadControl = DatetimeReadControl;

	/** Renders nothing; rewrites an old tag's six options into `key` when its modal opens. */
	function LegacyMountMigrator( props ) {
		var setState = props.setState;
		var family   = props.family;
		useEffect( function () {
			// A function updater, so it composes with the order normalizer's whole-object write; the
			// SAME reference back is the loop guard that stops React re-rendering a settled tag.
			setState( function ( prev ) {
				return legacyRewrite( prev || {}, family ) || prev;
			} );
		} );
		return null;
	}

	wp.hooks.addFilter(
		'generateblocks.editor.tagSpecificControls',
		'bws/datetime-read-control',
		function ( element, allOptions, context ) {
			// Compose: a control a prior filter hid stays hidden.
			if ( ! element || ! allOptions || ! context ) {
				return element;
			}
			// The `use` select is this control's to write, so the author never sees it. Told
			// apart structurally: a tag whose `key` is this control hides its `use`.
			if ( 'use' === element.key && allOptions.key && 'bws-datetime-reads' === allOptions.key.type ) {
				return null;
			}
			// An OLD tag (`legacy` names its family) carries this control on `key` beside its
			// old options. The old options show only while an end-only range holds them
			// (nothing rewrote it, so the control would fight them); every other old tag has
			// them rewritten into `key` on mount, and the control stands alone.
			var family = allOptions.key && 'bws-datetime-reads' === allOptions.key.type ? allOptions.key.legacy : '';
			var hold   = !! family && legacyHold( context.state || {}, family );
			if ( family && 'key' !== element.key && -1 !== LEGACY_KEYS[ family ].indexOf( element.key ) ) {
				return hold ? element : null;
			}
			var cfg = allOptions[ element.key ];
			if ( ! cfg || 'bws-datetime-reads' !== cfg.type ) {
				return element;
			}
			if ( hold ) {
				return null;
			}
			var control = el( DatetimeReadControl, {
				key: element.key,
				cfg: {
					label: cfg.label,
					help: cfg.help,
					placeholder: cfg.placeholder,
					readLabel: cfg.readLabel,
					reads: cfg.reads,
					timeLabel: cfg.timeLabel,
					timeHelp: cfg.timeHelp,
					timePlaceholder: cfg.timePlaceholder,
					entryLabels: cfg.entryLabels,
					addLabel: cfg.addLabel,
					timeAddLabel: cfg.timeAddLabel,
					timeRemoveLabel: cfg.timeRemoveLabel,
					removeLabel: cfg.removeLabel,
					surplusNotice: cfg.surplusNotice,
					extraNotice: cfg.extraNotice,
					max: cfg.max,
					typeDefault: cfg.typeDefault
				},
				context: context
			} );
			// The wrapper carries the option key, so a later filter anchoring on `element.key`
			// still finds it.
			return family ? el(
				Fragment,
				{ key: element.key },
				el( LegacyMountMigrator, { key: 'bws-datetime-legacy-mount', setState: context.setState, family: family } ),
				control
			) : control;
		}
	);
}() );
