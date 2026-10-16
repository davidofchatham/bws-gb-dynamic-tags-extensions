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

	// ── Layout and headings ───────────────────────────────────────────────────────────
	//
	// ONE vocabulary for every heading and gap in these controls, so the three composites and
	// the column card read as one design (the shapes are the styling prototype's):
	//   .bws-ts-l   a control or section heading: 11px / 600 / uppercase, the look of WP's own
	//               control labels. Class-only labels do NOT get that look from WP (it is
	//               generated CSS-in-JS), so it is drawn here.
	//   .bws-ts-l3  a field label inside a grid: 12px / 500, sentence case.
	// Every WP control below hides its OWN label (still announced) and sits under one of
	// these, because WP's visible labels sit at different heights from one control type to the
	// next, and the option group adds a 12px top margin to a combobox's.
	var CSS =
		'.bws-ts{display:flex;flex-direction:column;gap:12px;min-width:0}' +
		'.bws-ts-l,.bws-ts-l3{display:block;margin:0 0 8px;padding:0;line-height:1.4;color:#1e1e1e}' +
		'.bws-ts-l{font-size:11px;font-weight:600;text-transform:uppercase}' +
		'.bws-ts-l3{font-size:12px;font-weight:500;text-transform:none;margin-bottom:4px}' +
		'.bws-ts-help{margin:4px 0 0;font-size:12px;line-height:1.4;color:#50575e}' +
		'.bws-ts-field{min-width:0}' +
		'.bws-ts-pair{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;align-items:start}' +
		'.bws-ts-radios{display:flex;flex-wrap:wrap;gap:6px 16px}' +
		'.bws-ts-radios label{display:inline-flex;align-items:center;gap:6px;font-size:13px;line-height:16px}' +
		'.bws-ts-radios input{margin:0}' +
		'.bws-ts-sub{padding-left:12px}' +
		'.bws-ts-div{border-top:1px solid #e0e0e0}' +
		// Six controls fill DOWN three columns: color / size | weight / style | alignment / transform.
		'.bws-ts-g3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));grid-template-rows:repeat(2,auto);grid-auto-flow:column;gap:8px 12px;align-items:start}' +
		'.bws-ts-smx{display:grid;grid-template-columns:auto 5rem minmax(0,1fr) minmax(0,1.15fr);gap:8px 12px;align-items:center}' +
		'.bws-ts-smx .bws-ts-l,.bws-ts-smx .bws-ts-l3{margin:0}' +
		'.bws-ts-smx--lk{grid-template-columns:auto 5rem minmax(0,1fr) minmax(0,1.15fr) auto}' +
		// The toggle spans both target rows (rows 2-3) with a rule down its middle joining them.
		'.bws-ts-link{grid-column:5;grid-row:2 / span 2;align-self:stretch;display:flex;align-items:center;justify-content:center;position:relative}' +
		'.bws-ts-link:before{content:"";position:absolute;top:18px;bottom:18px;left:50%;border-left:1px solid #949494}' +
		'.bws-ts-link .components-button{position:relative;z-index:1;margin:auto 0;align-self:center;background:#fff;border:1px solid #949494;border-radius:2px;width:35px;height:35px;padding:0;justify-content:center}' +
		'.bws-ts-link .components-button.is-pressed{background:#1e1e1e;border-color:#1e1e1e;color:#fff}' +
		'.bws-ts-smx-row{display:contents}' +
		// Slider and readout share a line; Reset (only while set) takes its own line below, so the
		// track keeps the width a drag needs (beside the readout it was ~40px, and 5 → 100 was one pixel step).
		'.bws-ts-opacity{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:0 8px;min-width:0}' +
		'.bws-ts-opacity .components-button{grid-column:1/-1;justify-self:start;padding:0;height:auto}' +
		'.bws-ts-readout{flex:none;min-width:2.5em;font-size:13px;font-variant-numeric:tabular-nums}' +
		'.bws-ts-readout.is-default{color:#50575e}' +
		// The range thumb carries its own z-index, so it painted over an open color list. The
		// slider gets a stacking context of its own, and whichever combobox has focus (its list
		// open) outranks every sibling.
		'.bws-ts-range{isolation:isolate}' +
		'.bws-ts .components-combobox-control:focus-within{position:relative;z-index:5}' +
		// One control height, 40px: WP's select and text field are 40, the combobox is 37 and GB's
		// icon button groups are 35 out of the box.
		'.bws-ts .gb-button-icon-control .components-button{height:40px;min-height:40px}' +
		// (__next40pxDefaultSize does not reach the combobox's container in this WP.)
		'.bws-ts .components-combobox-control__suggestions-container{min-height:40px;box-sizing:border-box;align-items:center}' +
		// A list heading is a disabled row, never the first (that is the hint): bold and dark,
		// as a native <optgroup> draws one in a select.
		'.bws-ts .components-form-token-field__suggestion[aria-disabled=true]:not(:first-child){font-weight:600;color:#1e1e1e;opacity:1}' +
		// A third-width field would wrap every suggestion; the list may run wider than the field. Only in the
		// grids whose combobox sits left of the modal's right edge.
		'.bws-ts-g3 .components-form-token-field__suggestions-list,.bws-ts-smx .components-form-token-field__suggestions-list{min-width:15rem}';

	if ( typeof document !== 'undefined' && ! document.getElementById( 'bws-table-style-css' ) ) {
		var styleEl = document.createElement( 'style' );
		styleEl.id = 'bws-table-style-css';
		styleEl.textContent = CSS;
		document.head.appendChild( styleEl );
	}

	/** A heading (default level `l`) as a plain span. */
	function Heading( p ) {
		return el( 'span', { className: 'bws-ts-' + ( p.level || 'l' ) }, p.text );
	}

	/** Our visible label over a control whose own label is hidden from vision. */
	function Field( p ) {
		return el( 'div', { className: 'bws-ts-field' },
			el( 'span', { className: 'bws-ts-' + ( p.level || 'l3' ), 'aria-hidden': true }, p.label ),
			p.children
		);
	}

	/** One-of-N radios in a row. `options` is [ [ value, label ] … ]; `onPick(value)`. */
	function RadioRow( p ) {
		return el( 'div', { className: 'bws-ts-radios', role: 'radiogroup', 'aria-label': p.label },
			p.options.map( function ( o ) {
				return el( 'label', { key: o[ 0 ] || 'none' }, [
					el( 'input', {
						key: 'i',
						type: 'radio',
						name: p.name,
						checked: p.value === o[ 0 ],
						onChange: function () {
							p.onPick( o[ 0 ] );
						}
					} ),
					o[ 1 ]
				] );
			} )
		);
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
		// Heading rows are disabled options: WP's combobox has no heading row of its own.
		var head = function ( id, label, rows ) {
			return rows.length ? [ { value: '__group:' + id, label: label, disabled: true } ].concat( rows ) : [];
		};
		return head( 'site', __( 'Site colors', 'generateblocks' ), sites )
			.concat( head( 'names', __( 'Color names', 'generateblocks' ), extra.concat( plain( names ) ) ) )
			.concat( head( 'globals', __( 'CSS values', 'generateblocks' ), gl ) );
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
			if ( next === '__hint' || ( typeof next === 'string' && next.indexOf( '__group:' ) === 0 ) ) {
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
			__next40pxDefaultSize: true,
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

	/** A 24px line icon: each entry is [x, width] for one of three rows. */
	function lineIcon( rows ) {
		return el( 'svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24, 'aria-hidden': true, focusable: false },
			rows.map( function ( r, i ) {
				return el( 'rect', { key: i, x: r[ 0 ], y: 5 + i * 6, width: r[ 1 ], height: 1.5 } );
			} )
		);
	}

	function glyph( text, style ) {
		return el( 'span', { 'aria-hidden': true, style: Object.assign( { fontSize: '15px', lineHeight: 1, fontWeight: 600 }, style || {} ) }, text );
	}

	/** The icon for one choice, or null (the button then shows its text label). */
	function choiceIcon( group, value ) {
		var icons = {
			textAlign: {
				left: function () { return lineIcon( [ [ 4, 9 ], [ 4, 16 ], [ 4, 9 ] ] ); },
				center: function () { return lineIcon( [ [ 7.5, 9 ], [ 4, 16 ], [ 7.5, 9 ] ] ); },
				right: function () { return lineIcon( [ [ 11, 9 ], [ 4, 16 ], [ 11, 9 ] ] ); }
			},
			fontStyle: {
				normal: function () { return glyph( 'N' ); },
				italic: function () { return glyph( 'I', { fontStyle: 'italic' } ); },
				oblique: function () { return glyph( 'Ob', { fontStyle: 'oblique' } ); }
			},
			textTransform: {
				none: function () { return glyph( '–' ); },
				uppercase: function () { return glyph( 'AA' ); },
				lowercase: function () { return glyph( 'aa' ); },
				capitalize: function () { return glyph( 'Aa' ); }
			}
		};
		var make = icons[ group ] && icons[ group ][ value ];
		return make ? make() : null;
	}

	/**
	 * One-of-N button row. Uses GB's own ButtonIconControl (the Typography panel's group) when
	 * its styles-builder package is on the page, else a plain pressed-state button row.
	 */
	function ChoiceButtons( p ) {
		var GbButtons = window.gb && window.gb.stylesBuilder && window.gb.stylesBuilder.ButtonIconControl;
		if ( GbButtons ) {
			return el( 'div', { className: 'bws-ts-field' }, [
				el( Heading, { key: 'l', level: p.level || 'l3', text: p.label } ),
				el( GbButtons, {
					key: 'b',
					value: p.value,
					buttons: p.choices.map( function ( c ) {
						return { value: c.value, label: c.title || c.label, icon: choiceIcon( p.group, c.value ) || undefined };
					} ),
					onChange: p.onChange
				} )
			] );
		}
		return el( 'div', { className: 'bws-ts-field' }, [
			el( Heading, { key: 'l', level: p.level || 'l3', text: p.label } ),
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

	/**
	 * Font Weight's rows as native <option>s, in GB's own shape: the weights, a disabled
	 * separator, then the CSS globals. GB's list has no heading, so neither does this one.
	 */
	function weightOptions( defaultLabel ) {
		var opt = function ( value, label ) {
			return el( 'option', { key: value || 'default', value: value }, label );
		};
		var rows = [ opt( '', defaultLabel ), opt( 'normal', __( 'Normal', 'generateblocks' ) ), opt( 'bold', __( 'Bold', 'generateblocks' ) ) ];
		[ 100, 200, 300, 400, 500, 600, 700, 800, 900 ].forEach( function ( n ) {
			rows.push( opt( String( n ), String( n ) ) );
		} );
		// GB's own list: a disabled separator, then the CSS globals, no heading.
		rows.push( el( 'option', { key: 'sep', value: '', disabled: true }, '—' ) );
		( V.globals || [] ).forEach( function ( g ) {
			rows.push( opt( g, g ) );
		} );
		return rows;
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
			var value = p.values[ key ] || '';
			var inh = ( p.inherited && p.inherited[ key ] ) || '';
			var onChange = function ( v ) {
				p.onChange( key, v );
			};
			var field;
			if ( key === 'color' ) {
				field = el( ColorField, { label: LABELS[ key ], hideLabel: true, value: value, globals: true, placeholder: inh || __( 'Default', 'generateblocks' ), onChange: onChange } );
			} else if ( key === 'fontSize' ) {
				field = el( SizeField, { label: LABELS[ key ], hideLabel: true, value: value, kind: 'size', placeholder: inh || __( 'Default', 'generateblocks' ), onChange: onChange } );
			} else if ( key === 'fontWeight' ) {
				field = el( SelectControl, {
					label: LABELS[ key ],
					hideLabelFromVision: true,
					value: value,
					children: weightOptions( inh || __( 'Default', 'generateblocks' ) ),
					onChange: onChange,
					__nextHasNoMarginBottom: true
				} );
			} else {
				return el( ChoiceButtons, { key: key, group: key, label: LABELS[ key ], value: value, choices: CHOICES[ key ], onChange: onChange } );
			}
			return el( Field, { key: key, label: LABELS[ key ] }, field );
		} );
		return el( 'div', { className: 'bws-ts-field' }, [
			p.heading ? el( Heading, { key: 'h', text: p.heading } ) : null,
			el( 'div', { key: 'g', className: 'bws-ts-g3' }, nodes )
		] );
	}

	// ── Advisories and repairs ───────────────────────────────────────────────────

	function Advisory( p ) {
		return el( 'p', { role: 'status', className: 'bws-ts-help', style: p.error ? { color: '#cc1818' } : undefined }, rich( p.text ) );
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
			el( 'div', { key: 'pair', className: 'bws-ts-pair' }, [
				el( 'div', { key: 'sizing', className: 'bws-ts-field' }, [
					el( Heading, { key: 'l', text: p.label } ),
					el( RadioRow, { key: 'r', label: p.label, name: 'bws-table-layout', options: radios, value: layout, onPick: function ( v ) {
						write( { layout: v } );
					} } ),
					el( 'p', { key: 'h', className: 'bws-ts-help' }, COPY.sizingHelp[ layout ] !== undefined ? COPY.sizingHelp[ layout ] : COPY.sizingHelp[ '' ] )
				] ),
				el( Field, { key: 'width', level: 'l', label: __( 'Table Width', 'generateblocks' ) }, el( SizeField, {
					label: __( 'Table Width', 'generateblocks' ),
					hideLabel: true,
					value: width,
					kind: 'width',
					placeholder: layout === 'fixed' ? '100%' : __( 'Theme default', 'generateblocks' ),
					onChange: function ( v ) {
						write( { width: v } );
					}
				} ) )
			] )
		];
		if ( layout === 'fixed' && wide === 'auto' ) {
			children.push( el( Advisory, { key: 'adv', text: COPY.fixedNeedsWidth } ) );
		}
		return el( 'div', { className: 'bws-ts' }, children );
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

		return el( 'div', { className: 'bws-ts' }, [
			el( TextStyleBlock, { key: 'table', heading: __( 'Table Text Styles', 'generateblocks' ), values: tableVals, onChange: commit( 'base' ) } ),
			el( 'div', { key: 'div', className: 'bws-ts-div' } ),
			el( TextStyleBlock, { key: 'header', heading: __( 'Header Text Styles', 'generateblocks' ), values: headerVals, inherited: inherited, onChange: commit( 'header' ) } ),
			notices
		] );
	}

	// ── Stripes ──────────────────────────────────────────────────────────────────

	var STRIPE_MODES = [ [ '', __( 'None', 'generateblocks' ) ], [ 'rows', __( 'Rows', 'generateblocks' ) ], [ 'cols', __( 'Columns', 'generateblocks' ) ], [ 'both', __( 'Rows and columns', 'generateblocks' ) ] ];

	function defaults() {
		var d = window.bwsTableDefaults || {};
		return {
			color: d.stripeColor || V.stripeDefaultColor || 'currentColor',
			opacity: d.stripeOpacity !== undefined ? Number( d.stripeOpacity ) : Number( V.stripeDefaultOpacity || 5 )
		};
	}

	/**
	 * One target's row in the stripes matrix: the label, then Pattern, Color and Opacity as
	 * cells of the grid the header row set up. `display: contents` keeps the cells on that
	 * grid while the group name tells a screen reader which row's "Pattern" this is.
	 */
	function StripeRow( p ) {
		var spec = p.spec;
		var d = defaults();
		var hasAlpha = spec.color && T.colorHasAlpha( spec.color );
		function set( patch ) {
			p.onChange( Object.assign( {}, spec, patch ) );
		}
		var shown = spec.opacity === null ? d.opacity : spec.opacity;
		var colorCell = el( ColorField, {
			key: 'c',
			label: __( 'Stripe Color', 'generateblocks' ),
			hideLabel: true,
			value: spec.color || '',
			placeholder: d.color,
			onChange: function ( v ) {
				set( { color: v || null } );
			}
		} );
		var opacityCell = hasAlpha
			? el( 'span', { key: 'o', className: 'bws-ts-help', style: { margin: 0 } }, COPY.opacityFromColor )
			: el( 'div', { key: 'o', className: 'bws-ts-opacity' }, [
				el( 'div', { key: 'r', className: 'bws-ts-range' }, el( RangeControl, {
					label: __( 'Stripe Opacity', 'generateblocks' ),
					hideLabelFromVision: true,
					value: shown,
					min: 0,
					max: 100,
					step: 1,
					withInputField: false,
					onChange: function ( v ) {
						set( { opacity: v === undefined || v === null ? null : Number( v ) } );
					},
					__nextHasNoMarginBottom: true
				} ) ),
				el( 'output', { key: 'v', className: 'bws-ts-readout' + ( spec.opacity === null ? ' is-default' : '' ) }, T.cssNumber( shown ) + '%' ),
				spec.opacity === null ? null : el( Button, {
					key: 'x',
					variant: 'tertiary',
					isSmall: true,
					onClick: function () {
						set( { opacity: null } );
					}
				}, __( 'Reset', 'generateblocks' ) )
			] );
		var look = p.linkedTo
			// Linked: the first target's color and opacity are written to both, so there is
			// nothing to edit here; the link button below says how to part them.
			? [ el( 'span', { key: 'lk', className: 'bws-ts-help', style: { gridColumn: 'span 2', margin: 0 } },
				__( 'Same as', 'generateblocks' ) + ' ' + p.linkedTo ) ]
			: [ colorCell, opacityCell ];
		return el( 'div', { className: 'bws-ts-smx-row', role: 'group', 'aria-label': p.heading }, [
			el( Heading, { key: 'h', text: p.name } ),
			el( SelectControl, {
				key: 'parity',
				label: __( 'Pattern', 'generateblocks' ),
				hideLabelFromVision: true,
				value: spec.parity,
				options: [
					{ value: 'even', label: __( 'Even', 'generateblocks' ) },
					{ value: 'odd', label: __( 'Odd', 'generateblocks' ) }
				],
				onChange: function ( v ) {
					set( { parity: v } );
				},
				__nextHasNoMarginBottom: true
			} )
		].concat( look ) );
	}

	// Link / unlink glyphs (Material paths, as the prototype draws them).
	var LINK_ON = 'M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z';
	var LINK_OFF = 'M17 7h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1 0 1.43-.98 2.63-2.31 2.98l1.46 1.46C20.88 15.61 22 13.95 22 12c0-2.76-2.24-5-5-5zm-1 4h-2.19l2 2H16zM2 4.27l3.11 3.11C3.29 8.12 2 9.91 2 12c0 2.76 2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1 0-1.59 1.21-2.9 2.76-3.07L8.73 11H8v2h2.73L13 15.27V17h1.73l4.01 4L20 19.74 3.27 3 2 4.27z';

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

		// Rows and columns SHARE a color and opacity by default; the link is not on the wire
		// (each target carries its own), so it is derived: both on, the same look, and the
		// author has not asked to part them. `apart` is that request, UI-only.
		var apartState = useState( false );
		var apart = apartState[ 0 ];
		var setApart = apartState[ 1 ];
		var sameLook = !! ( rows && cols ) && ( rows.color || null ) === ( cols.color || null ) && ( rows.opacity === null ? null : rows.opacity ) === ( cols.opacity === null ? null : cols.opacity );
		var linked = sameLook && ! apart;

		function setMode( m ) {
			var cur = parsed || { rows: null, cols: null };
			// A target switched on while the other is on and linked starts with the other's look.
			var fresh = function ( other ) {
				return { parity: 'even', color: other && ! apart ? other.color : null, opacity: other && ! apart ? other.opacity : null };
			};
			write( {
				rows: m === 'rows' || m === 'both' ? ( cur.rows || fresh( cur.cols ) ) : null,
				cols: m === 'cols' || m === 'both' ? ( cur.cols || fresh( cur.rows ) ) : null
			} );
		}

		/** Rows' color and opacity onto columns: what linking does, and what the fix button does. */
		function matchColumns() {
			write( { rows: rows, cols: Object.assign( {}, cols, { color: rows.color, opacity: rows.opacity } ) } );
		}

		var children = [
			el( 'div', { key: 'modes', className: 'bws-ts-field' }, [
				el( Heading, { key: 'l', text: p.label } ),
				el( RadioRow, { key: 'r', label: p.label, name: 'bws-table-stripes', options: STRIPE_MODES, value: mode, onPick: setMode } )
			] )
		];
		if ( rows || cols ) {
			var cells = [
				el( 'span', { key: 'g' } ),
				el( Heading, { key: 'p', level: 'l3', text: __( 'Pattern', 'generateblocks' ) } ),
				el( Heading, { key: 'c', level: 'l3', text: __( 'Color', 'generateblocks' ) } ),
				el( Heading, { key: 'o', level: 'l3', text: __( 'Opacity', 'generateblocks' ) } )
			];
			if ( rows ) {
				cells.push( el( StripeRow, {
					key: 'rows',
					name: __( 'Rows', 'generateblocks' ),
					heading: __( 'Row stripes', 'generateblocks' ),
					spec: rows,
					onChange: function ( s ) {
						// Linked: the look follows to columns in the same write.
						write( { rows: s, cols: linked ? Object.assign( {}, cols, { color: s.color, opacity: s.opacity } ) : cols } );
					}
				} ) );
			}
			if ( cols ) {
				cells.push( el( StripeRow, {
					key: 'cols',
					name: __( 'Columns', 'generateblocks' ),
					heading: __( 'Column stripes', 'generateblocks' ),
					spec: cols,
					linkedTo: linked ? __( 'Rows', 'generateblocks' ) : null,
					onChange: function ( s ) {
						write( { rows: rows, cols: s } );
					}
				} ) );
			}
			if ( rows && cols ) {
				// Fifth column: the link toggle, spanning both target rows.
				cells.splice( 4, 0, el( 'span', { key: 'g5' } ) );
				cells.push( el( 'div', { key: 'lk', className: 'bws-ts-link' }, el( Button, {
					icon: el( 'svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 20, height: 20, 'aria-hidden': true, focusable: false }, el( 'path', { d: linked ? LINK_ON : LINK_OFF } ) ),
					isPressed: linked,
					disabled: ! sameLook,
					label: linked
						? __( 'Linked: rows and columns share one color. Click to set them separately.', 'generateblocks' )
						: __( 'Use one color for rows and columns', 'generateblocks' ),
					showTooltip: true,
					onClick: function () {
						setApart( linked );
					}
				} ) ) );
			}
			children.push( el( 'div', { key: 'smx', className: 'bws-ts-smx' + ( rows && cols ? ' bws-ts-smx--lk' : '' ) }, cells ) );
			if ( rows && cols && ! sameLook ) {
				children.push( el( 'p', { key: 'diff', className: 'bws-ts-help', style: { margin: 0 } }, [
					__( 'Rows and columns use different colors.', 'generateblocks' ) + ' ',
					el( Button, { key: 'b', variant: 'link', onClick: function () {
						setApart( false );
						matchColumns();
					} }, __( 'Use the Rows color for both', 'generateblocks' ) )
				] ) );
			}
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
		return el( 'div', { className: 'bws-ts' }, children );
	}

	// ── The column card ──────────────────────────────────────────────────────────

	/**
	 * Column Width: the column token `width(…)`. Its help depends on the table's Column
	 * Sizing, because that decides whether the width is exact.
	 */
	function ColumnWidthField( p ) {
		var layout = String( ( p.tagState && p.tagState.layout ) || '' ).toLowerCase();
		var opts = p.slot.opts || {};
		return el( Field, { level: 'l', label: __( 'Column Width', 'generateblocks' ) }, el( SizeField, {
			label: __( 'Column Width', 'generateblocks' ),
			hideLabel: true,
			value: opts.width || '',
			kind: 'width',
			placeholder: COPY.columnWidthPlaceholder[ layout ] !== undefined ? COPY.columnWidthPlaceholder[ layout ] : COPY.columnWidthPlaceholder[ '' ],
			help: layout === 'fixed' ? undefined : ( COPY.columnWidthHint[ layout ] || COPY.columnWidthHint[ '' ] ),
			onChange: function ( v ) {
				p.write( setOpt( p.slot, 'width', v ) );
			}
		} ) );
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
		[ 'body', __( 'Body', 'generateblocks' ) ],
		[ 'header', __( 'Header', 'generateblocks' ) ],
		[ 'both', __( 'Body and header', 'generateblocks' ) ]
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
		var blocks = [];
		if ( mode === 'body' || mode === 'both' ) {
			blocks.push( el( TextStyleBlock, {
				key: 'body',
				heading: __( 'Body Text Styles', 'generateblocks' ),
				keys: bodyKeys,
				values: valuesOf( 'base', bodyKeys ),
				inherited: inheritedBody,
				onChange: commit( 'base' )
			} ) );
		}
		if ( mode === 'header' || mode === 'both' ) {
			if ( blocks.length ) {
				blocks.push( el( 'div', { key: 'div', className: 'bws-ts-div' } ) );
			}
			blocks.push( el( TextStyleBlock, {
				key: 'header',
				heading: __( 'Header Text Styles', 'generateblocks' ),
				values: valuesOf( 'header', TEXT_KEYS ),
				inherited: inheritedHeader,
				onChange: commit( 'header' )
			} ) );
		}
		var children = [
			el( ChoiceButtons, {
				key: 'align',
				level: 'l',
				label: __( 'Column Text Alignment', 'generateblocks' ),
				value: alignBase,
				group: 'textAlign',
				choices: CHOICES.textAlign,
				onChange: function ( v ) {
					commit( 'base' )( 'textAlign', v );
				}
			} ),
			el( 'div', { key: 'modes', className: 'bws-ts-field' }, [
				el( Heading, { key: 'l', text: __( 'Custom Column Text Styles', 'generateblocks' ) } ),
				el( RadioRow, { key: 'r', label: __( 'Custom Column Text Styles', 'generateblocks' ), name: 'bws-table-colstyle-' + p.slotKey, options: COLUMN_MODES, value: mode, onPick: pickMode } )
			] )
		];
		if ( blocks.length ) {
			children.push( el( 'div', { key: 'blocks', className: 'bws-ts bws-ts-sub' }, blocks ) );
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
		Field: Field,
		Heading: Heading,
		ColumnStyle: ColumnStyle
	};
}() );
