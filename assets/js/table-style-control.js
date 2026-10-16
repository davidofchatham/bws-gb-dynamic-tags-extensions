/**
 * `{{table}}` presentation controls: Sizing and Width, Typography and Stripes, plus the
 * column card's styling block.
 *
 * Three COMPOSITE controls, each committing its keys in ONE `setState` (the precedent is
 * `convertUpdate` in src-chain-control.js), so a stored tag never passes through a half-written
 * state:
 *
 *   bws-table-sizing      on `layout`  → `layout` + `width`
 *   bws-table-typography  on `color`   → the six text keys, at Table and Header
 *   bws-table-stripes     on `stripe`  → `stripe`
 *
 * The other keys register as `bws-table-style-member` and render nothing of their own. The
 * option-group wrapper (priority 30) joins the three composites into one box, because the PHP
 * visual-group map puts every key in the `style` group (bws_option_visual_groups()).
 *
 * THIS FILE DECIDES NOTHING ABOUT VALUES. Every validator, the `head,args;head,args` reader and
 * the cascade live in table-style-grammar.js, the tested twin of table-helpers.php; the controls
 * ask it and write what it emits. A value in no allowed form is never repaired silently: it is
 * shown with a message, and the repair button writes exactly what the renderer already reads
 * (emit of the parse), so the button changes the wire and never the render.
 *
 * Help and advisory copy sets a reference to a control's label or value in italics with `*…*`.
 * WP renders a string help as text, so rich() turns the stars into <em> nodes; the label text
 * stays an escaped React text child (never innerHTML).
 *
 * Exposes `window.bwsTableStyleUI` so the column card in slot-fold-control.js renders the SAME
 * fields rather than a second set.
 *
 * @package BWS_Dynamic_Tags
 * @since   Unreleased
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.hooks || ! wp.element || ! wp.components || ! window.bwsTableStyle ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var C = wp.components;
	var ComboboxControl = C.ComboboxControl;
	var SelectControl = C.SelectControl;
	var TextControl = C.TextControl;
	var Button = C.Button;
	var RangeControl = C.RangeControl;
	var __ = wp.i18n ? wp.i18n.__ : function ( s ) { return s; };

	var T = window.bwsTableStyle;
	var V = T.vocab;
	var HEAD = V.headerHead || 'header';
	var TEXT_KEYS = V.textKeys || [];

	// ── Copy (approved, plan §DECIDED 2026-10-15 "The editor") ────────────────────────

	var COPY = {
		sizingHelp: {
			'': __( 'Whatever your theme does. Some themes fix column sizes, others fit the content.', 'generateblocks' ),
			auto: __( 'Columns fit their content.', 'generateblocks' ),
			fixed: __( 'Columns share the table width equally, unless a Column Width is set.', 'generateblocks' )
		},
		fixedNeedsWidth: __( 'Fixed column sizing has no effect while *Table Width* is *Auto*. Column sizes follow the content instead.', 'generateblocks' ),
		columnWidthHint: {
			auto: __( 'This width is a hint only. Columns can grow to fit their content unless *Column Sizing* is *Fixed*.', 'generateblocks' ),
			'': __( 'Whether this width is exact depends on your theme. Set *Column Sizing* to *Fixed* to make it exact.', 'generateblocks' )
		},
		columnWidthPlaceholder: {
			fixed: __( 'Equal share', 'generateblocks' ),
			auto: __( 'Fits content', 'generateblocks' ),
			'': __( 'Theme default', 'generateblocks' )
		},
		opacityFromColor: __( 'Opacity is set by the color', 'generateblocks' )
	};

	// "{v}" is not a recognized … messages, one per kind of value.
	var ERR = {
		width: __( 'is not a recognized width. Use auto, var(), or a number with px, em, rem, %, ch, or vw.', 'generateblocks' ),
		color: __( 'is not a recognized color. Use hex, rgb, rgba, hsl, hsla, var(), currentColor, or a color name.', 'generateblocks' ),
		opacity: __( 'is not a recognized opacity. Use a number from 0 to 100.', 'generateblocks' ),
		size: __( 'is not a recognized size. Use a keyword such as small or larger, var(), or a number with px, em, rem, %, ch, or vw.', 'generateblocks' ),
		value: __( 'is not a recognized value.', 'generateblocks' )
	};

	function errorText( kind, value ) {
		return '"' + value + '" ' + ( ERR[ kind ] || ERR.value );
	}

	/** Render `*italic*` runs as <em>; everything else stays an escaped text child. */
	function rich( text ) {
		if ( typeof text !== 'string' || text.indexOf( '*' ) === -1 ) {
			return text;
		}
		return text.split( /\*([^*]+)\*/ ).map( function ( part, i ) {
			return i % 2 ? el( 'em', { key: i }, part ) : part;
		} );
	}

	/** Visually hidden text, for the description a screen reader hears. */
	function srOnly( text ) {
		return el( 'span', { className: 'screen-reader-text' }, text );
	}

	// ── State helpers ─────────────────────────────────────────────────────────────

	/** A copy of `state` with `patch` applied; an empty value deletes its key. */
	function applyPatch( state, patch ) {
		var upd = Object.assign( {}, state );
		Object.keys( patch ).forEach( function ( k ) {
			if ( patch[ k ] === undefined || patch[ k ] === null || patch[ k ] === '' ) {
				delete upd[ k ];
			} else {
				upd[ k ] = patch[ k ];
			}
		} );
		return upd;
	}

	/** Parse one text key; a missing value is an empty parse, an unbalanced bracket is null. */
	function readText( key, raw ) {
		if ( raw === undefined || raw === null || raw === '' ) {
			return { base: null, header: null, flags: [] };
		}
		return T.parseText( key, String( raw ) );
	}

	// ── Suggestion lists ──────────────────────────────────────────────────────────

	var NUMBER_RE = /^\d*\.?\d+$/;

	/** Site colors as `var(--slug)` rows, read from the block editor's settings. */
	function siteColors() {
		try {
			var settings = wp.data && wp.data.select( 'core/block-editor' ).getSettings();
			return ( ( settings && settings.colors ) || [] ).filter( function ( c ) {
				return c && c.slug;
			} ).map( function ( c ) {
				return { value: 'var(--' + c.slug + ')', label: ( c.name || c.slug ) + ' (var(--' + c.slug + '))' };
			} );
		} catch ( e ) {
			return [];
		}
	}

	function plain( values ) {
		return values.map( function ( v ) {
			return { value: v, label: v };
		} );
	}

	function colorOptions( typed, globals ) {
		var q = typed.trim().toLowerCase();
		var names = ( V.colorNames || [] ).filter( function ( n ) {
			return q ? n.indexOf( q ) !== -1 : n === 'black' || n === 'white';
		} ).slice( 0, 8 );
		var sites = siteColors().filter( function ( o ) {
			return ! q || o.label.toLowerCase().indexOf( q ) !== -1;
		} );
		var extra = [ { value: 'currentColor', label: 'currentColor' } ].filter( function ( o ) {
			return ! q || o.value.toLowerCase().indexOf( q ) !== -1;
		} );
		var gl = globals && ! q ? plain( V.globals || [] ) : [];
		return sites.concat( extra, plain( names ), gl );
	}

	/** Number typed: offer it in every unit, the natural default first (the unit flag, as a list). */
	function sizeOptions( typed, kind, defaultUnit ) {
		var q = typed.trim();
		var opts = [];
		if ( NUMBER_RE.test( q ) ) {
			var units = ( V.units || [] ).slice();
			units.sort( function ( a, b ) {
				return ( a === defaultUnit ? -1 : 0 ) - ( b === defaultUnit ? -1 : 0 );
			} );
			return units.map( function ( u ) {
				return { value: q + u, label: q + u };
			} );
		}
		if ( kind === 'width' ) {
			opts.push( { value: 'auto', label: 'auto' } );
		} else {
			opts = plain( V.fontSizeKeywords || [] ).concat( q ? [] : plain( V.globals || [] ) );
		}
		var low = q.toLowerCase();
		return opts.filter( function ( o ) {
			return ! low || o.value.indexOf( low ) !== -1;
		} );
	}

	// ── The field: a combobox that also takes typed text ─────────────────────────

	/**
	 * A text value with a suggestion list. WP's ComboboxControl refuses off-list text, so a
	 * typed value is offered as a synthetic first row (the bws-field-combo precedent); picking
	 * it commits. A typed value that fails `validate` is offered as an error row that commits
	 * nothing, so an unrecognized value never reaches the wire from here.
	 *
	 * @param {Object} p label, value, onChange(canonicalOrEmpty), validate(raw)→canonical|null,
	 *   options(typed)→rows, hint, placeholder, errorKind, help, hideLabel.
	 */
	function ComboField( p ) {
		var typedState = useState( '' );
		var typed = typedState[ 0 ];
		var setTyped = typedState[ 1 ];
		var errState = useState( '' );
		var err = errState[ 0 ];
		var setErr = errState[ 1 ];

		var value = p.value || '';
		var rows = p.options( typed );
		var q = typed.trim();
		if ( q ) {
			var canon = p.validate( q );
			var exists = rows.some( function ( o ) {
				return o.value === q || o.value === canon;
			} );
			if ( ! exists ) {
				rows = [ canon !== null
					? { value: canon, label: __( 'Use', 'generateblocks' ) + ' "' + q + '"' }
					: { value: '__invalid:' + q, label: errorText( p.errorKind, q ) }
				].concat( rows );
			}
		}
		if ( value && ! rows.some( function ( o ) { return o.value === value; } ) ) {
			rows = [ { value: value, label: value } ].concat( rows );
		}
		if ( p.hint ) {
			rows = [ { value: '__hint', label: p.hint, disabled: true } ].concat( rows );
		}

		function commit( next ) {
			if ( next === '__hint' ) {
				return;
			}
			if ( typeof next === 'string' && next.indexOf( '__invalid:' ) === 0 ) {
				setErr( errorText( p.errorKind, next.slice( 10 ) ) );
				return;
			}
			setErr( '' );
			setTyped( '' );
			p.onChange( next ? next : '' );
		}

		var shownErr = err || ( value && p.validate( value ) === null ? errorText( p.errorKind, value ) : '' );
		var help = el( Fragment, null, [
			p.help ? el( 'span', { key: 'h' }, rich( p.help ) ) : null,
			shownErr ? el( 'span', { key: 'e', style: { display: 'block', color: '#cc1818' } }, shownErr ) : null,
			p.hint ? el( 'span', { key: 'd', className: 'screen-reader-text' }, p.hint ) : null
		] );

		return el( ComboboxControl, {
			label: p.label,
			hideLabelFromVision: !! p.hideLabel,
			value: value,
			options: rows,
			placeholder: p.placeholder,
			help: help,
			onChange: commit,
			onFilterValueChange: function ( t ) {
				setTyped( t );
				if ( err ) {
					setErr( '' );
				}
			},
			allowReset: true,
			__nextHasNoMarginBottom: true
		} );
	}

	/** Width / size field: the combobox with the unit shown as a list. */
	function SizeField( p ) {
		var isWidth = p.kind !== 'size';
		return el( ComboField, {
			label: p.label,
			hideLabel: p.hideLabel,
			value: p.value,
			placeholder: p.placeholder,
			help: p.help,
			hint: isWidth
				? __( 'Type a number, then pick a unit, or choose auto or a var().', 'generateblocks' )
				: __( 'Type a number, then pick a unit, or choose a keyword or a var().', 'generateblocks' ),
			errorKind: isWidth ? 'width' : 'size',
			validate: isWidth ? T.widthValue : T.fontSizeValue,
			options: function ( typed ) {
				return sizeOptions( typed, isWidth ? 'width' : 'size', p.defaultUnit || ( isWidth ? '%' : 'rem' ) );
			},
			onChange: p.onChange
		} );
	}

	function ColorField( p ) {
		return el( ComboField, {
			label: p.label,
			hideLabel: p.hideLabel,
			value: p.value,
			placeholder: p.placeholder,
			help: p.help,
			hint: __( 'Pick a color, or type hex, rgb(), hsl(), var(), or a color name.', 'generateblocks' ),
			errorKind: 'color',
			validate: function ( v ) {
				return T.colorValue( v, !! p.globals );
			},
			options: function ( typed ) {
				return colorOptions( typed, !! p.globals );
			},
			onChange: p.onChange
		} );
	}

	// ── Button groups (one pressed at a time; pressing it again clears) ─────────

	function ChoiceButtons( p ) {
		return el( 'div', { className: 'components-base-control' }, [
			el( 'span', { key: 'l', className: 'components-base-control__label', style: { display: 'block' } }, p.label ),
			el( 'div', { key: 'b', role: 'group', 'aria-label': p.label, style: { display: 'flex', gap: '4px', flexWrap: 'wrap' } },
				p.choices.map( function ( c ) {
					var on = p.value === c.value;
					return el( Button, {
						key: c.value,
						variant: on ? 'primary' : 'secondary',
						'aria-pressed': on,
						title: c.title || c.label,
						'aria-label': c.title || c.label,
						onClick: function () {
							p.onChange( on ? '' : c.value );
						}
					}, c.label );
				} )
			)
		] );
	}

	var CHOICES = {
		fontStyle: [
			{ value: 'normal', label: __( 'Normal', 'generateblocks' ) },
			{ value: 'italic', label: __( 'Italic', 'generateblocks' ) },
			{ value: 'oblique', label: __( 'Oblique', 'generateblocks' ) }
		],
		textAlign: [
			{ value: 'left', label: __( 'Left', 'generateblocks' ) },
			{ value: 'center', label: __( 'Center', 'generateblocks' ) },
			{ value: 'right', label: __( 'Right', 'generateblocks' ) }
		],
		textTransform: [
			{ value: 'none', label: __( 'None', 'generateblocks' ) },
			{ value: 'uppercase', label: 'AA', title: __( 'Uppercase', 'generateblocks' ) },
			{ value: 'lowercase', label: 'aa', title: __( 'Lowercase', 'generateblocks' ) },
			{ value: 'capitalize', label: 'Aa', title: __( 'Capitalize', 'generateblocks' ) }
		]
	};

	var LABELS = {
		color: __( 'Text Color', 'generateblocks' ),
		fontSize: __( 'Font Size', 'generateblocks' ),
		fontWeight: __( 'Font Weight', 'generateblocks' ),
		fontStyle: __( 'Font Style', 'generateblocks' ),
		textAlign: __( 'Text Alignment', 'generateblocks' ),
		textTransform: __( 'Text Transform', 'generateblocks' )
	};

	function weightOptions( defaultLabel ) {
		var rows = [
			{ value: '', label: defaultLabel },
			{ value: 'normal', label: __( 'Normal', 'generateblocks' ) },
			{ value: 'bold', label: __( 'Bold', 'generateblocks' ) }
		];
		[ 100, 200, 300, 400, 500, 600, 700, 800, 900 ].forEach( function ( n ) {
			rows.push( { value: String( n ), label: String( n ) } );
		} );
		return rows.concat( ( V.globals || [] ).map( function ( g ) {
			return { value: g, label: g };
		} ) );
	}

	// ── One text-style target (the Table, the Header, a column's Body…) ─────────

	/**
	 * Six controls for one target. `values` holds the target's current value per key (''
	 * unset), `inherited` what an unset key shows instead of "Default", `onChange(key, value)`
	 * commits one key. `keys` narrows the set (a column's Body leaves textAlign to its own block).
	 */
	function TextStyleBlock( p ) {
		var keys = p.keys || TEXT_KEYS;
		var nodes = keys.map( function ( key ) {
			var common = {
				key: key,
				label: LABELS[ key ],
				hideLabel: false
			};
			var value = p.values[ key ] || '';
			var inh = ( p.inherited && p.inherited[ key ] ) || '';
			var onChange = function ( v ) {
				p.onChange( key, v );
			};
			if ( key === 'color' ) {
				return el( ColorField, Object.assign( {}, common, { value: value, globals: true, placeholder: inh || __( 'Default', 'generateblocks' ), onChange: onChange } ) );
			}
			if ( key === 'fontSize' ) {
				return el( SizeField, Object.assign( {}, common, { value: value, kind: 'size', placeholder: inh || __( 'Default', 'generateblocks' ), onChange: onChange } ) );
			}
			if ( key === 'fontWeight' ) {
				return el( SelectControl, {
					key: key,
					label: LABELS[ key ],
					value: value,
					options: weightOptions( inh || __( 'Default', 'generateblocks' ) ),
					onChange: onChange,
					__nextHasNoMarginBottom: true
				} );
			}
			return el( ChoiceButtons, { key: key, label: LABELS[ key ], value: value, choices: CHOICES[ key ], onChange: onChange } );
		} );
		return el( 'div', { className: 'bws-table-style-block' }, [
			p.heading ? el( 'strong', { key: 'h', style: { display: 'block', fontSize: '12px', margin: '0 0 8px' } }, p.heading ) : null,
			el( 'div', { key: 'g', style: { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))', gap: '12px' } }, nodes )
		] );
	}

	// ── Advisories and repairs ───────────────────────────────────────────────────

	function Advisory( p ) {
		return el( 'p', { role: 'status', style: { margin: '8px 0 0', fontSize: '12px', color: p.error ? '#cc1818' : '#50575e' } }, rich( p.text ) );
	}

	/** The message for one parse flag. `subject` names the key or target the flag is about. */
	function flagMessage( f, subject, useInstead ) {
		switch ( f.code ) {
			case 'repeated':
			case 'repeatedArg':
			case 'repeatedTarget':
				return subject + ' ' + __( 'has more than one value of the same kind. Only the first will be used.', 'generateblocks' );
			case 'unknownHead':
				return f.text.replace( /^unknown head '(.*)'$/, function ( m, head ) {
					return '"' + head + '" ' + __( 'is not a recognized option.', 'generateblocks' ) + ' ' + useInstead;
				} );
			case 'badStripeValue':
				return '"' + f.arg + '" ' + __( 'is not a color, an opacity or a sequence (odd or even), so it will not be used.', 'generateblocks' );
			default:
				return subject + ': ' + __( 'a value was not recognized and will not be used.', 'generateblocks' );
		}
	}

	var UNBALANCED = __( 'could not be read because a bracket is missing or extra, so this setting will not be applied. The rest of the table is unaffected.', 'generateblocks' );

	/**
	 * Messages plus ONE repair button for a hand-edited value. Repair writes what the renderer
	 * already reads (`fixed`, the emit of the parse; '' clears the key).
	 */
	function RepairNotice( p ) {
		var messages = p.parsed === null ? [ p.subject + ' ' + UNBALANCED ] : p.parsed.flags.map( function ( f ) {
			return flagMessage( f, p.subject, p.useInstead || __( 'Use *Header* instead.', 'generateblocks' ) );
		} );
		if ( ! messages.length ) {
			return null;
		}
		return el( 'div', { className: 'bws-table-repair' }, [
			messages.map( function ( m, i ) {
				return el( Advisory, { key: i, text: m, error: p.parsed === null } );
			} ),
			el( Button, {
				key: 'fix',
				variant: 'secondary',
				size: 'small',
				style: { marginTop: '6px' },
				onClick: function () {
					p.onRepair( p.fixed );
				}
			}, p.parsed === null ? __( 'Clear', 'generateblocks' ) + ' ' + p.subject : __( 'Fix', 'generateblocks' ) )
		] );
	}

	// ── Sizing and Width ─────────────────────────────────────────────────────────

	function SizingControl( p ) {
		var state = p.context.state || {};
		var layout = String( state.layout || '' ).toLowerCase();
		var width = state.width || '';
		var wide = T.widthValue( width );

		function write( patch ) {
			p.context.setState( applyPatch( state, patch ) );
		}

		var radios = [ [ '', __( 'Theme', 'generateblocks' ) ], [ 'auto', __( 'Auto', 'generateblocks' ) ], [ 'fixed', __( 'Fixed', 'generateblocks' ) ] ];
		var children = [
			el( 'div', { key: 'sizing', className: 'components-base-control' }, [
				el( 'span', { key: 'l', className: 'components-base-control__label', style: { display: 'block' } }, p.label ),
				el( 'div', { key: 'r', role: 'radiogroup', 'aria-label': p.label, style: { display: 'flex', gap: '16px', flexWrap: 'wrap' } },
					radios.map( function ( r ) {
						return el( 'label', { key: r[ 0 ] || 'theme', style: { display: 'inline-flex', gap: '6px', alignItems: 'center' } }, [
							el( 'input', {
								key: 'i',
								type: 'radio',
								name: 'bws-table-layout',
								checked: layout === r[ 0 ],
								onChange: function () {
									write( { layout: r[ 0 ] } );
								}
							} ),
							r[ 1 ]
						] );
					} )
				),
				el( 'p', { key: 'h', className: 'components-base-control__help' }, COPY.sizingHelp[ layout ] !== undefined ? COPY.sizingHelp[ layout ] : COPY.sizingHelp[ '' ] )
			] ),
			el( SizeField, {
				key: 'width',
				label: __( 'Table Width', 'generateblocks' ),
				value: width,
				kind: 'width',
				placeholder: layout === 'fixed' ? '100%' : __( 'Theme default', 'generateblocks' ),
				onChange: function ( v ) {
					write( { width: v } );
				}
			} )
		];
		if ( layout === 'fixed' && wide === 'auto' ) {
			children.push( el( Advisory, { key: 'adv', text: COPY.fixedNeedsWidth } ) );
		}
		return el( Fragment, null, children );
	}

	// ── Typography (Table / Header) ──────────────────────────────────────────────

	function TypographyControl( p ) {
		var state = p.context.state || {};
		var parsed = {};
		TEXT_KEYS.forEach( function ( k ) {
			parsed[ k ] = readText( k, state[ k ] );
		} );

		function target( slot ) {
			var values = {};
			TEXT_KEYS.forEach( function ( k ) {
				values[ k ] = parsed[ k ] ? parsed[ k ][ slot ] || '' : '';
			} );
			return values;
		}

		function commit( slot ) {
			return function ( key, value ) {
				var cur = parsed[ key ] || { base: null, header: null };
				var next = { base: cur.base, header: cur.header };
				next[ slot ] = value || null;
				p.context.setState( applyPatch( state, ( function () {
					var patch = {};
					patch[ key ] = T.emitText( next );
					return patch;
				}() ) ) );
			};
		}

		var tableVals = target( 'base' );
		var headerVals = target( 'header' );
		// An unset header key shows what it inherits from the table.
		var inherited = {};
		TEXT_KEYS.forEach( function ( k ) {
			if ( tableVals[ k ] ) {
				inherited[ k ] = tableVals[ k ] + ' (' + __( 'table', 'generateblocks' ) + ')';
			}
		} );

		var notices = [];
		TEXT_KEYS.forEach( function ( k ) {
			var raw = state[ k ];
			if ( raw === undefined || raw === '' ) {
				return;
			}
			var pr = parsed[ k ];
			if ( pr !== null && ! pr.flags.length ) {
				return;
			}
			notices.push( el( RepairNotice, {
				key: k,
				subject: LABELS[ k ],
				parsed: pr,
				fixed: pr === null ? '' : T.emitText( pr ),
				onRepair: function ( fixed ) {
					var patch = {};
					patch[ k ] = fixed;
					p.context.setState( applyPatch( state, patch ) );
				}
			} ) );
		} );

		return el( Fragment, null, [
			el( 'span', { key: 'cap', className: 'components-base-control__label', style: { display: 'block', marginBottom: '8px' } }, __( 'Typography', 'generateblocks' ) ),
			el( TextStyleBlock, { key: 'table', heading: __( 'Table', 'generateblocks' ), values: tableVals, onChange: commit( 'base' ) } ),
			el( 'div', { key: 'sp', style: { height: '12px' } } ),
			el( TextStyleBlock, { key: 'header', heading: __( 'Header', 'generateblocks' ), values: headerVals, inherited: inherited, onChange: commit( 'header' ) } ),
			notices
		] );
	}

	// ── Stripes ──────────────────────────────────────────────────────────────────

	var STRIPE_MODES = [ [ '', __( 'None', 'generateblocks' ) ], [ 'rows', __( 'Rows', 'generateblocks' ) ], [ 'cols', __( 'Columns', 'generateblocks' ) ], [ 'both', __( 'Both', 'generateblocks' ) ] ];

	function defaults() {
		var d = window.bwsTableDefaults || {};
		return {
			color: d.stripeColor || V.stripeDefaultColor || 'currentColor',
			opacity: d.stripeOpacity !== undefined ? Number( d.stripeOpacity ) : Number( V.stripeDefaultOpacity || 5 )
		};
	}

	function StripeTarget( p ) {
		var spec = p.spec;
		var d = defaults();
		var hasAlpha = spec.color && T.colorHasAlpha( spec.color );
		function set( patch ) {
			p.onChange( Object.assign( {}, spec, patch ) );
		}
		return el( 'div', { className: 'bws-table-stripe-target' }, [
			el( 'strong', { key: 'h', style: { display: 'block', fontSize: '12px', margin: '0 0 8px' } }, p.heading ),
			el( SelectControl, {
				key: 'parity',
				label: __( 'Pattern', 'generateblocks' ),
				value: spec.parity,
				options: [
					{ value: 'even', label: p.target === 'rows' ? __( 'Even (body rows 1, 3, …)', 'generateblocks' ) : __( 'Even (columns 2, 4, …)', 'generateblocks' ) },
					{ value: 'odd', label: p.target === 'rows' ? __( 'Odd (header, body rows 2, 4, …)', 'generateblocks' ) : __( 'Odd (columns 1, 3, …)', 'generateblocks' ) }
				],
				onChange: function ( v ) {
					set( { parity: v } );
				},
				__nextHasNoMarginBottom: true
			} ),
			el( 'div', { key: 'c', style: { marginTop: '12px' } }, el( ColorField, {
				label: __( 'Stripe Color', 'generateblocks' ),
				value: spec.color || '',
				placeholder: d.color,
				onChange: function ( v ) {
					set( { color: v || null } );
				}
			} ) ),
			el( 'div', { key: 'o', style: { marginTop: '12px' } }, hasAlpha
				? el( Advisory, { text: COPY.opacityFromColor } )
				: el( RangeControl, {
					label: __( 'Stripe Opacity', 'generateblocks' ),
					value: spec.opacity === null ? undefined : spec.opacity,
					min: 0,
					max: 100,
					step: 1,
					allowReset: true,
					resetFallbackValue: undefined,
					initialPosition: d.opacity,
					onChange: function ( v ) {
						set( { opacity: v === undefined || v === null ? null : Number( v ) } );
					},
					help: spec.opacity === null ? __( 'Default', 'generateblocks' ) + ': ' + T.cssNumber( d.opacity ) + '%' : undefined,
					__nextHasNoMarginBottom: true
				} ) )
		] );
	}

	function StripesControl( p ) {
		var state = p.context.state || {};
		var raw = state.stripe === undefined ? '' : String( state.stripe );
		var parsed = raw === '' ? { rows: null, cols: null, flags: [] } : T.parseStripe( raw );
		var rows = parsed && parsed.rows;
		var cols = parsed && parsed.cols;
		// null parse = a bracket the reader cannot trust; no radio reads as chosen.
		var mode = parsed === null ? null : ( rows && cols ? 'both' : rows ? 'rows' : cols ? 'cols' : '' );

		function write( next ) {
			p.context.setState( applyPatch( state, { stripe: T.emitStripe( next ) } ) );
		}

		function setMode( m ) {
			var cur = parsed || { rows: null, cols: null };
			var fresh = function () {
				return { parity: 'even', color: null, opacity: null };
			};
			write( {
				rows: m === 'rows' || m === 'both' ? ( cur.rows || fresh() ) : null,
				cols: m === 'cols' || m === 'both' ? ( cur.cols || fresh() ) : null
			} );
		}

		var children = [
			el( 'div', { key: 'modes', className: 'components-base-control' }, [
				el( 'span', { key: 'l', className: 'components-base-control__label', style: { display: 'block' } }, p.label ),
				el( 'div', { key: 'r', role: 'radiogroup', 'aria-label': p.label, style: { display: 'flex', gap: '16px', flexWrap: 'wrap' } },
					STRIPE_MODES.map( function ( m ) {
						return el( 'label', { key: m[ 0 ] || 'none', style: { display: 'inline-flex', gap: '6px', alignItems: 'center' } }, [
							el( 'input', {
								key: 'i',
								type: 'radio',
								name: 'bws-table-stripes',
								checked: mode === m[ 0 ],
								onChange: function () {
									setMode( m[ 0 ] );
								}
							} ),
							m[ 1 ]
						] );
					} )
				)
			] )
		];
		if ( rows ) {
			children.push( el( StripeTarget, {
				key: 'rows',
				target: 'rows',
				heading: __( 'Row stripes', 'generateblocks' ),
				spec: rows,
				onChange: function ( s ) {
					write( { rows: s, cols: cols } );
				}
			} ) );
		}
		if ( cols ) {
			children.push( el( StripeTarget, {
				key: 'cols',
				target: 'cols',
				heading: __( 'Column stripes', 'generateblocks' ),
				spec: cols,
				onChange: function ( s ) {
					write( { rows: rows, cols: s } );
				}
			} ) );
		}
		if ( raw !== '' && ( parsed === null || parsed.flags.length ) ) {
			children.push( el( RepairNotice, {
				key: 'repair',
				subject: __( 'Stripes', 'generateblocks' ),
				useInstead: __( 'Use *Rows* or *Columns* instead.', 'generateblocks' ),
				parsed: parsed,
				fixed: parsed === null ? '' : T.emitStripe( parsed ),
				onRepair: function ( fixed ) {
					p.context.setState( applyPatch( state, { stripe: fixed } ) );
				}
			} ) );
		}
		return el( Fragment, null, children );
	}

	// ── The column card ──────────────────────────────────────────────────────────

	/**
	 * Column Width: the column token `width(…)`. Its help depends on the table's Column
	 * Sizing, because that decides whether the width is exact.
	 */
	function ColumnWidthField( p ) {
		var layout = String( ( p.tagState && p.tagState.layout ) || '' ).toLowerCase();
		var opts = p.slot.opts || {};
		return el( SizeField, {
			label: __( 'Column Width', 'generateblocks' ),
			value: opts.width || '',
			kind: 'width',
			placeholder: COPY.columnWidthPlaceholder[ layout ] !== undefined ? COPY.columnWidthPlaceholder[ layout ] : COPY.columnWidthPlaceholder[ '' ],
			help: layout === 'fixed' ? undefined : ( COPY.columnWidthHint[ layout ] || COPY.columnWidthHint[ '' ] ),
			onChange: function ( v ) {
				p.write( setOpt( p.slot, 'width', v ) );
			}
		} );
	}

	/** The slot with one token set (or deleted when empty). */
	function setOpt( slot, key, value ) {
		var next = Object.assign( {}, slot );
		next.opts = Object.assign( {}, slot.opts );
		if ( value === '' || value === null || value === undefined ) {
			delete next.opts[ key ];
		} else {
			next.opts[ key ] = value;
		}
		return next;
	}

	var COLUMN_MODES = [
		[ 'none', __( 'None', 'generateblocks' ) ],
		[ 'header', __( 'Header', 'generateblocks' ) ],
		[ 'body', __( 'Body', 'generateblocks' ) ],
		[ 'both', __( 'Header and body', 'generateblocks' ) ]
	];

	/**
	 * Column text styling: alignment in its own block (it covers the header too), then the rest
	 * behind "Custom Column Text Styles". A column's bare color and font keys reach the BODY
	 * cells only; the Header target is the column Header override.
	 */
	function ColumnStyle( p ) {
		var slot = p.slot;
		var opts = slot.opts || {};
		var tag = {};
		TEXT_KEYS.forEach( function ( k ) {
			tag[ k ] = readText( k, ( p.tagState || {} )[ k ] );
		} );
		var col = {};
		TEXT_KEYS.forEach( function ( k ) {
			col[ k ] = readText( k, opts[ k ] );
		} );

		var hasHeader = false;
		var hasBody = false;
		TEXT_KEYS.forEach( function ( k ) {
			if ( col[ k ] && col[ k ].header ) {
				hasHeader = true;
			}
			if ( col[ k ] && col[ k ].base && k !== 'textAlign' ) {
				hasBody = true;
			}
		} );
		var derived = hasHeader && hasBody ? 'both' : hasHeader ? 'header' : hasBody ? 'body' : 'none';
		var picked = useState( 'none' );
		var local = picked[ 0 ];
		var setLocal = picked[ 1 ];
		var union = function ( a, b ) {
			return a === b ? a : ( a === 'none' ? b : ( b === 'none' ? a : 'both' ) );
		};
		var mode = union( local, derived );

		/** Set one target's value for one key, keeping the other target's. */
		function commit( slotName ) {
			return function ( key, value ) {
				var cur = col[ key ] || { base: null, header: null };
				var next = { base: cur.base, header: cur.header };
				next[ slotName ] = value || null;
				p.write( setOpt( slot, key, T.emitText( next ) ) );
			};
		}

		function valuesOf( slotName, keys ) {
			var out = {};
			keys.forEach( function ( k ) {
				out[ k ] = col[ k ] ? col[ k ][ slotName ] || '' : '';
			} );
			return out;
		}

		function pickMode( m ) {
			setLocal( m );
			// Narrowing clears what the dropped target held, so the wire matches what is shown.
			var next = slot;
			TEXT_KEYS.forEach( function ( k ) {
				var cur = col[ k ] || { base: null, header: null };
				var keepHeader = m === 'header' || m === 'both';
				var keepBody = m === 'body' || m === 'both' || k === 'textAlign';
				var nb = keepBody ? cur.base : null;
				var nh = keepHeader ? cur.header : null;
				if ( nb !== cur.base || nh !== cur.header ) {
					next = setOpt( next, k, T.emitText( { base: nb, header: nh } ) );
				}
			} );
			if ( next !== slot ) {
				p.write( next );
			}
		}

		var bodyKeys = TEXT_KEYS.filter( function ( k ) {
			return k !== 'textAlign';
		} );
		// What an unset target shows instead of "Default": the value it inherits from the table.
		var inheritedBody = {};
		var inheritedHeader = {};
		TEXT_KEYS.forEach( function ( k ) {
			var t = tag[ k ] || {};
			if ( t.base ) {
				inheritedBody[ k ] = t.base + ' (' + __( 'table', 'generateblocks' ) + ')';
				inheritedHeader[ k ] = t.header ? t.header + ' (' + __( 'table header', 'generateblocks' ) + ')' : t.base + ' (' + __( 'table', 'generateblocks' ) + ')';
			} else if ( t.header ) {
				inheritedHeader[ k ] = t.header + ' (' + __( 'table header', 'generateblocks' ) + ')';
			}
		} );

		var alignBase = col.textAlign ? col.textAlign.base || '' : '';
		var children = [
			el( ChoiceButtons, {
				key: 'align',
				label: __( 'Column Text Alignment', 'generateblocks' ),
				value: alignBase,
				choices: CHOICES.textAlign,
				onChange: function ( v ) {
					commit( 'base' )( 'textAlign', v );
				}
			} ),
			el( 'div', { key: 'modes', className: 'components-base-control', style: { marginTop: '12px' } }, [
				el( 'span', { key: 'l', className: 'components-base-control__label', style: { display: 'block' } }, __( 'Custom Column Text Styles', 'generateblocks' ) ),
				el( 'div', { key: 'r', role: 'radiogroup', 'aria-label': __( 'Custom Column Text Styles', 'generateblocks' ), style: { display: 'flex', gap: '16px', flexWrap: 'wrap' } },
					COLUMN_MODES.map( function ( m ) {
						return el( 'label', { key: m[ 0 ], style: { display: 'inline-flex', gap: '6px', alignItems: 'center' } }, [
							el( 'input', {
								key: 'i',
								type: 'radio',
								name: 'bws-table-colstyle-' + p.slotKey,
								checked: mode === m[ 0 ],
								onChange: function () {
									pickMode( m[ 0 ] );
								}
							} ),
							m[ 1 ]
						] );
					} )
				)
			] )
		];
		if ( mode === 'header' || mode === 'both' ) {
			children.push( el( TextStyleBlock, {
				key: 'header',
				heading: __( 'Column header', 'generateblocks' ),
				values: valuesOf( 'header', TEXT_KEYS ),
				inherited: inheritedHeader,
				onChange: commit( 'header' )
			} ) );
		}
		if ( mode === 'body' || mode === 'both' ) {
			children.push( el( TextStyleBlock, {
				key: 'body',
				heading: __( 'Column body', 'generateblocks' ),
				keys: bodyKeys,
				values: valuesOf( 'base', bodyKeys ),
				inherited: inheritedBody,
				onChange: commit( 'base' )
			} ) );
		}
		TEXT_KEYS.forEach( function ( k ) {
			var raw = opts[ k ];
			if ( raw === undefined || raw === '' ) {
				return;
			}
			var pr = col[ k ];
			if ( pr !== null && ! pr.flags.length ) {
				return;
			}
			children.push( el( RepairNotice, {
				key: 'fix-' + k,
				subject: LABELS[ k ],
				parsed: pr,
				fixed: pr === null ? '' : T.emitText( pr ),
				onRepair: function ( fixed ) {
					p.write( setOpt( slot, k, fixed ) );
				}
			} ) );
		} );
		return el( Fragment, null, children );
	}


	// ── The filter ────────────────────────────────────────────────────────────────

	var COMPOSITES = {
		'bws-table-sizing': SizingControl,
		'bws-table-typography': TypographyControl,
		'bws-table-stripes': StripesControl
	};

	wp.hooks.addFilter(
		'generateblocks.editor.tagSpecificControls',
		'bws/table-style-control',
		function ( element, allOptions, context ) {
			// Compose: a prior filter hiding this control wins.
			if ( ! element || ! allOptions || ! context ) {
				return element;
			}
			var cfg = allOptions[ element.key ];
			if ( ! cfg ) {
				return element;
			}
			if ( cfg.type === 'bws-table-style-member' ) {
				// Owned by a composite; drawing it too would be a second place to edit one key.
				return null;
			}
			var Composite = COMPOSITES[ cfg.type ];
			if ( ! Composite ) {
				return element;
			}
			return el( Composite, { key: element.key, label: cfg.label, context: context } );
		}
	);

	window.bwsTableStyleUI = {
		rich: rich,
		applyPatch: applyPatch,
		readText: readText,
		ComboField: ComboField,
		SizeField: SizeField,
		ColorField: ColorField,
		ChoiceButtons: ChoiceButtons,
		TextStyleBlock: TextStyleBlock,
		RepairNotice: RepairNotice,
		Advisory: Advisory,
		COPY: COPY,
		CHOICES: CHOICES,
		LABELS: LABELS,
		SizingControl: SizingControl,
		TypographyControl: TypographyControl,
		StripesControl: StripesControl,
		ColumnWidthField: ColumnWidthField,
		ColumnStyle: ColumnStyle
	};
}() );
