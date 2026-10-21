/**
 * `{{table}}` presentation controls: Sizing and Width, Typography and Stripes, plus the
 * column card's styling block.
 *
 * Four COMPOSITE controls, each committing its keys in ONE `setState` (the precedent is
 * `convertUpdate` in src-chain-control.js), so a stored tag never passes through a half-written
 * state:
 *
 *   bws-table-sizing      on `layout`  → `layout` + `width`
 *   bws-table-typography  on `color`   → the six text keys, at Table and Header
 *   bws-table-stripes     on `stripe`  → `stripe`
 *   bws-table-borders     on `border`  → `border` + `borderRadius`
 *
 * The other keys register as `bws-table-style-member` and render nothing of their own. The
 * option-group wrapper (priority 30) joins the four composites into one box, because the PHP
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
			auto: __( 'Browsers treat this as a hint only, unless the table\'s *Column Sizing* is *Fixed*.', 'generateblocks' ),
			'': __( 'Browsers may treat this as a hint only, depending on your theme. Set the table\'s *Column Sizing* to *Fixed* to make it exact.', 'generateblocks' )
		},
		columnWidthPlaceholder: {
			fixed: __( 'Equal share', 'generateblocks' ),
			auto: __( 'Fits content', 'generateblocks' ),
			'': __( 'Theme default', 'generateblocks' )
		},
		opacityFromColor: __( 'Opacity is set by the color', 'generateblocks' ),
		bareRadius: __( 'on its own is reserved for cell corners, which aren\'t supported yet, so it will not be used. Fix applies it as the Outer Radius.', 'generateblocks' )
	};

	// "{v}" is not a recognized … messages, one per kind of value.
	var ERR = {
		width: __( 'is not a recognized width. Use auto, var(), or a number with px, em, rem, %, ch, or vw.', 'generateblocks' ),
		color: __( 'is not a recognized color. Use hex, rgb, rgba, hsl, hsla, var(), currentColor, or a color name.', 'generateblocks' ),
		opacity: __( 'is not a recognized opacity. Use a number from 0 to 100.', 'generateblocks' ),
		size: __( 'is not a recognized size. Use a keyword such as small or larger, var(), or a number with px, em, rem, %, ch, or vw.', 'generateblocks' ),
		borderWidth: __( 'is not a recognized width. Use var(), or a number with px, em, or rem.', 'generateblocks' ),
		radius: __( 'is not a recognized radius. Use var(), or a number with px, em, or rem.', 'generateblocks' ),
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
	// ONE vocabulary for every heading and gap in these controls, so the composites and
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
		'.bws-table-repair{padding:8px 12px 12px;background:#fff;border-left:4px solid #dba617;box-shadow:0 0 0 1px #e0e0e0}' +
		'.bws-table-repair.is-error{border-left-color:#cc1818}' +
		'.bws-ts-pair{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 16px;align-items:start}' +
		'.bws-ts-radios{display:flex;flex-wrap:wrap;gap:6px 16px}' +
		'.bws-ts-radios label{display:inline-flex;align-items:center;gap:6px;font-size:13px;line-height:16px}' +
		'.bws-ts-radios input{margin:0}' +
		'.bws-ts-sub{padding-left:12px}' +
		'.bws-ts-div{border-top:1px solid #e0e0e0}' +
		// Typography sections breathe like the gap GB leaves above Stripes (~24px); the rule between the
		// Table and Header blocks is pulled to 8px a side so it reads as inside the group, not a split.
		'.bws-ts-sect{gap:24px}' +
		// Sizing is its own option: GB's gap under it left Alignment too close to the Width field and its help.
		'.bws-ts-sizing{margin-bottom:12px}' +
		'.bws-ts-sect>.bws-ts-div{margin:-16px 0;border-top-color:#ececec}' +
		// Six controls fill DOWN three columns: color / size | weight / style | alignment / transform.
		'.bws-ts-g3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));grid-template-rows:repeat(2,auto);grid-auto-flow:column;gap:8px 16px;align-items:start}' +
		'.bws-ts-smx{display:grid;grid-template-columns:auto 5rem minmax(0,1fr) minmax(0,1.15fr);gap:8px 12px;align-items:start}' +
		'.bws-ts-smx .bws-ts-l,.bws-ts-smx .bws-ts-l3{margin:0}' +
		// Top-aligned: a combobox's always-present help line makes its cell taller than the select's,
		// so centering shifted the Pattern select off the Color field. The row label and the help
		// notes take the 40px control height to sit on the same midline.
		'.bws-ts-smx-row>.bws-ts-l,.bws-ts-smx-row>.bws-ts-help{display:flex;align-items:center;min-height:40px;line-height:1.4}' +
		'.bws-ts-smx--lk{grid-template-columns:auto 5rem minmax(0,1fr) minmax(0,1.15fr) auto}' +
		// The toggle spans both target rows (rows 2-3) with a rule down its middle joining them.
		'.bws-ts-link{grid-column:5;grid-row:2 / span 2;align-self:stretch;display:flex;align-items:center;justify-content:center;position:relative}' +
		'.bws-ts-link:before{content:"";position:absolute;top:18px;bottom:18px;left:50%;border-left:1px solid #949494}' +
		'.bws-ts-link .components-button{position:relative;z-index:1;margin:auto 0;align-self:center;background:#fff;border:1px solid #949494;border-radius:2px;width:35px;height:35px;padding:0;justify-content:center}' +
		'.bws-ts-link .components-button.is-pressed{background:#1e1e1e;border-color:#1e1e1e;color:#fff}' +
		'.bws-ts-smx-row{display:contents}' +
		'.bws-ts-smx-row>.bws-ts-same{grid-column:span 2}' +
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
		// Swatch (GB's own: 25px circle, the color over white, a crossbar when empty or unresolvable).
		'.bws-ts-colorrow{display:flex;align-items:flex-start;gap:6px;min-width:0}' +
		'.bws-ts-colorrow>:first-child{flex:1;min-width:0}' +
		'.bws-ts-sw{flex:0 0 25px;box-sizing:border-box;width:25px;height:25px;margin-top:7px;border:1px solid rgba(0,0,0,.2);border-radius:50%;background:#fff;overflow:hidden}' +
		'.bws-ts-sw>i{display:block;width:100%;height:100%}' +
		'.bws-ts-sw.is-empty,.bws-ts-sw[data-na="1"]{background:#fff linear-gradient(-45deg,transparent 48%,rgba(0,0,0,.2) 0,rgba(0,0,0,.2) 52%,transparent 0)}' +
		'.bws-ts-sw.is-empty>i,.bws-ts-sw[data-na="1"]>i{visibility:hidden}' +
		// Opacity bar: checkerboard, then a fade to the opaque color; the native range sits on top
		// with a transparent track, and its round thumb is the swatch (--bws-tc = color at the chosen
		// opacity). An unresolvable var() drops only the fade (--bws-tb) and crosses the thumb.
		'.bws-ts-asb{position:relative;min-width:0;height:40px}' +
		'.bws-ts-asb>i{display:none}' +
		'.bws-ts-asb::before,.bws-ts-asb::after{content:"";position:absolute;left:0;right:0;top:50%;height:14px;margin-top:-7px;border-radius:7px;pointer-events:none}' +
		'.bws-ts-asb::before{background:repeating-conic-gradient(#ddd 0 25%,#fff 0 50%) 0 0/8px 8px}' +
		'.bws-ts-asb::after{background:linear-gradient(90deg,transparent,var(--bws-tb,#949494));box-shadow:inset 0 0 0 1px rgba(0,0,0,.12)}' +
		'.bws-ts-asb[data-na="1"]::after{display:none}' +
		'.bws-ts-asb input[type=range]{position:relative;z-index:1;-webkit-appearance:none;appearance:none;display:block;width:100%;height:40px;min-height:0;margin:0;padding:0;border:0;background:none;box-shadow:none;cursor:pointer}' +
		'.bws-ts-asb input[type=range]::-webkit-slider-runnable-track{height:40px;background:none;border:0}' +
		'.bws-ts-asb input[type=range]::-moz-range-track{height:40px;background:none;border:0}' +
		'.bws-ts-asb input[type=range]::-webkit-slider-thumb{-webkit-appearance:none;appearance:none;box-sizing:border-box;width:25px;height:25px;margin-top:7px;border-radius:50%;border:1px solid rgba(0,0,0,.2);background:linear-gradient(var(--bws-tc,transparent),var(--bws-tc,transparent)),#fff;box-shadow:0 0 0 2px #fff,0 1px 3px rgba(0,0,0,.35)}' +
		'.bws-ts-asb input[type=range]::-moz-range-thumb{box-sizing:border-box;width:25px;height:25px;border-radius:50%;border:1px solid rgba(0,0,0,.2);background:linear-gradient(var(--bws-tc,transparent),var(--bws-tc,transparent)),#fff;box-shadow:0 0 0 2px #fff,0 1px 3px rgba(0,0,0,.35)}' +
		'.bws-ts-asb[data-na="1"] input[type=range]::-webkit-slider-thumb{background:#fff linear-gradient(-45deg,transparent 48%,rgba(0,0,0,.2) 0,rgba(0,0,0,.2) 52%,transparent 0)}' +
		'.bws-ts-asb[data-na="1"] input[type=range]::-moz-range-thumb{background:#fff linear-gradient(-45deg,transparent 48%,rgba(0,0,0,.2) 0,rgba(0,0,0,.2) 52%,transparent 0)}' +
		'.bws-ts-asb.is-default input[type=range]::-webkit-slider-thumb{border-style:dashed}' +
		'.bws-ts-asb.is-default input[type=range]::-moz-range-thumb{border-style:dashed}' +
		'.bws-ts-asb input[type=range]:focus{outline:none;box-shadow:none}' +
		'.bws-ts-asb input[type=range]:focus-visible::-webkit-slider-thumb{box-shadow:0 0 0 2px #fff,0 0 0 4px #3858e9}' +
		'.bws-ts-asb input[type=range]:focus-visible::-moz-range-thumb{box-shadow:0 0 0 2px #fff,0 0 0 4px #3858e9}' +
		'.bws-ts .components-combobox-control:focus-within{position:relative;z-index:5}' +
		// One control height, 40px: WP's select and text field are 40, the combobox is 37 and GB's
		// icon button groups are 35 out of the box.
		'.bws-ts .gb-button-icon-control .components-button{height:40px;min-height:40px}' +
		// (__next40pxDefaultSize does not reach the combobox's container in this WP.)
		'.bws-ts .components-combobox-control__suggestions-container{min-height:40px;box-sizing:border-box;align-items:center}' +
		// A third-width field would wrap every suggestion; the list may run wider than the field. Only in the
		// grids whose combobox sits left of the modal's right edge.
		'.bws-ts-g3 .components-form-token-field__suggestions-list,.bws-ts-smx .components-form-token-field__suggestions-list,.bws-ts-borders .components-form-token-field__suggestions-list{min-width:15rem}' +
		// Borders: the override matrix shares Stripes' 64px label column, so the two line up; Radius is a narrower
		// third field. The style dropdown's trigger is drawn as a full-width select, as GB's border style is.
		'.bws-ts-bmx{display:grid;grid-template-columns:64px minmax(0,1fr) minmax(0,1fr);gap:8px 12px;align-items:start}' +
		'.bws-ts-bmx--r{grid-template-columns:64px minmax(0,1fr) minmax(0,1fr) minmax(0,.62fr)}' +
		'.bws-ts-bmx .bws-ts-l,.bws-ts-bmx .bws-ts-l3{margin:0}' +
		'.bws-ts-sdd,.bws-ts-sdd>.components-button{width:100%}' +
		'.bws-ts-sdd>.components-button{justify-content:flex-start;gap:6px;height:40px;padding:0 8px;border:1px solid #949494;border-radius:2px;background:#fff;color:#1e1e1e}' +
		'.bws-ts-sdd.is-inherited>.components-button{color:#757575}' +
		'.bws-ts-sdd svg{fill:currentColor;flex:none}' +
		// Under 576px (the modal fills the screen): Sizing / Width and Column Header / Width stack; text
		// styles go two across (color / size / weight | style / transform); each stripe target is its own
		// block, name on top, Opacity on a second line, with a label over each control in place of the
		// matrix's column headings (.bws-ts-mob). The link toggle keeps its rule down the right edge.
		'.bws-ts-mob{display:none}' +
		'@media (max-width:575px){' +
			'.bws-ts-sizing .bws-ts-pair,.bws-ts-hdrrow{grid-template-columns:minmax(0,1fr)}' +
			'.bws-ts-g3{grid-template-columns:repeat(2,minmax(0,1fr));grid-template-rows:repeat(3,auto)}' +
			'.bws-ts-mob{display:block}' +
			// Beats the matrix's flush `.bws-ts-smx .bws-ts-l3{margin:0}` so the labels keep their gap.
			'.bws-ts-smx .bws-ts-mob{margin-bottom:4px}' +
			'.bws-ts-smx{grid-template-columns:minmax(0,1fr)}' +
			'.bws-ts-smx--lk{grid-template-columns:minmax(0,1fr) auto}' +
			'.bws-ts-smx>.bws-ts-l3,.bws-ts-smx>span:empty,.bws-ts-bmx>.bws-ts-l3,.bws-ts-bmx>span:empty{display:none}' +
			'.bws-ts-bmx,.bws-ts-bmx--r{grid-template-columns:minmax(0,1fr)}' +
			'.bws-ts-bmx .bws-ts-mob{margin-bottom:4px}' +
				// The empty cell that holds a non-Outer row's place under the Radius column.
				'.bws-ts-bmx .bws-ts-smx-row>span:empty{display:none}' +
			'.bws-ts-smx-row{display:grid;grid-column:1;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:8px 12px;align-items:start}' +
			'.bws-ts-smx-row>.bws-ts-l,.bws-ts-smx-row>.bws-ts-help{grid-column:1/-1;min-height:0}' +
			'.bws-ts-smx-row>.bws-ts-field--wide,.bws-ts-smx-row>.bws-ts-mob{grid-column:1/-1}' +
			'.bws-ts-link{grid-column:2;grid-row:1 / span 2}' +
			// The color field sits in the right-hand column, so its list opens leftward from the field's right
			// edge, clear of the link toggle's column and inside the screen.
			'.bws-ts-smx .components-form-token-field__suggestions-list{left:auto;right:0;max-width:calc(100vw - 2rem)}' +
		'}';

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
		return el( 'div', { className: 'bws-ts-field' + ( p.wide ? ' bws-ts-field--wide' : '' ) },
			el( 'span', { className: 'bws-ts-' + ( p.level || 'l3' ) + ( p.mob ? ' bws-ts-mob' : '' ), 'aria-hidden': true }, p.label ),
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

	/**
	 * None / A / B as three checkboxes, exclusive-with-None. `options` is [ none, a, b ] as
	 * [ value, label ]; `value` is one of those values, 'both', or null (a hand-edit under
	 * repair: nothing ticked, not even None). `onPick(value)` gets the mode the ticks now mean.
	 */
	function CheckRow( p ) {
		var none = p.options[ 0 ];
		var a = p.options[ 1 ];
		var b = p.options[ 2 ];
		var hasA = p.value === a[ 0 ] || p.value === 'both';
		var hasB = p.value === b[ 0 ] || p.value === 'both';
		function toggle( which ) {
			return function ( on ) {
				var nextA = which === 'a' ? on : hasA;
				var nextB = which === 'b' ? on : hasB;
				p.onPick( nextA && nextB ? 'both' : nextA ? a[ 0 ] : nextB ? b[ 0 ] : none[ 0 ] );
			};
		}
		return el( 'div', { className: 'bws-ts-radios', role: 'group', 'aria-label': p.label }, [
			el( C.CheckboxControl, {
				key: 'n',
				label: none[ 1 ],
				checked: p.value === none[ 0 ],
				onChange: function ( on ) {
					// Unticking None ticks nothing, which is None: no change.
					if ( on ) {
						p.onPick( none[ 0 ] );
					}
				},
				__nextHasNoMarginBottom: true
			} ),
			el( C.CheckboxControl, { key: 'a', label: a[ 1 ], checked: hasA, onChange: toggle( 'a' ), __nextHasNoMarginBottom: true } ),
			el( C.CheckboxControl, { key: 'b', label: b[ 1 ], checked: hasB, onChange: toggle( 'b' ), __nextHasNoMarginBottom: true } )
		] );
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
		var named = plain( names );
		if ( ! q && named.length ) {
			named.push( { value: '__hint:names', label: __( 'Type to search more color names.', 'generateblocks' ), disabled: true } );
		}
		return withSeparators( [ extra, sites, named, gl ] );
	}

	/** Groups joined by GB's disabled dash row, as its Font Weight list draws one. */
	function withSeparators( groups ) {
		var out = [];
		groups.filter( function ( g ) {
			return g.length;
		} ).forEach( function ( g, i ) {
			out = out.concat( i ? [ { value: '__sep:' + i, label: '—', disabled: true } ] : [], g );
		} );
		return out;
	}

	/** Number typed: offer it in every unit, the natural default first (the unit flag, as a list). */
	function sizeOptions( typed, kind, defaultUnit, extras ) {
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
			opts = plain( [ 'auto' ].concat( extras || [] ) );
		} else {
			// The relative keywords (larger, smaller) sit apart from the absolute xx-small…xxx-large scale.
			var relative = /^(larger|smaller)$/;
			var words = V.fontSizeKeywords || [];
			var lowq = q.toLowerCase();
			return withSeparators( [
				plain( words.filter( function ( w ) {
					return ! relative.test( w );
				} ) ),
				plain( words.filter( function ( w ) {
					return relative.test( w );
				} ) ),
				q ? [] : plain( V.globals || [] )
			].map( function ( g ) {
				return g.filter( function ( o ) {
					return ! lowq || o.value.indexOf( lowq ) !== -1;
				} );
			} ) );
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
			if ( typeof next === 'string' && /^__(hint|sep)/.test( next ) ) {
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
				? __( 'Type a number, then pick a unit, or choose auto or 100%. A var() can be typed in.', 'generateblocks' )
				: __( 'Type a number, then pick a unit, or choose a keyword. A var() can be typed in.', 'generateblocks' ),
			errorKind: isWidth ? 'width' : 'size',
			validate: isWidth ? T.widthValue : T.fontSizeValue,
			options: function ( typed ) {
				return sizeOptions( typed, isWidth ? 'width' : 'size', p.defaultUnit || ( isWidth ? '%' : 'rem' ), p.presets );
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

	// ── Swatch and opacity bar ───────────────────────────────────────────────────

	/**
	 * Callback ref for a host whose first child `<i>` carries the opaque color as its background:
	 * flags the host `data-na` when a var() in it does not resolve in this document (the computed
	 * background stays transparent), so the CSS can cross the swatch out. Runs on every render, as
	 * a ref that is a new function each time does.
	 */
	function probeUnresolved( color ) {
		return function ( host ) {
			if ( ! host || ! host.firstChild ) {
				return;
			}
			var bad = false;
			if ( color && /var\(/.test( color ) && typeof getComputedStyle === 'function' ) {
				bad = getComputedStyle( host.firstChild ).backgroundColor === 'rgba(0, 0, 0, 0)';
			}
			host.setAttribute( 'data-na', bad ? '1' : '' );
		};
	}

	/** A color for the swatch, or '' when there is nothing to show (empty, invalid or a CSS global). */
	function swatchColor( value ) {
		if ( ! value || /^(inherit|initial|unset|revert|revert-layer)$/i.test( value ) ) {
			return '';
		}
		return T.colorValue( value, false ) === null ? '' : value;
	}

	/** GB-style round swatch of a color value. */
	function Swatch( p ) {
		var color = swatchColor( p.value );
		return el( 'span', { className: 'bws-ts-sw' + ( color ? '' : ' is-empty' ), ref: probeUnresolved( color ), 'aria-hidden': true },
			el( 'i', { style: color ? { background: color } : undefined } ) );
	}

	/**
	 * The stripe opacity slider: a bar fading from clear to the opaque `base`, with a thumb that
	 * shows `composed` (the base at the chosen opacity). `isDefault` draws the thumb dashed.
	 */
	function OpacityBar( p ) {
		var style = { '--bws-tc': p.composed, '--bws-tb': p.base };
		return el( 'div', { className: 'bws-ts-asb' + ( p.isDefault ? ' is-default' : '' ), ref: probeUnresolved( p.base ), style: style }, [
			el( 'i', { key: 'p', style: { background: p.base } } ),
			el( 'input', {
				key: 'r',
				type: 'range',
				min: 0,
				max: 100,
				step: 1,
				value: p.value,
				'aria-label': p.label,
				onChange: function ( e ) {
					p.onChange( Number( e.target.value ) );
				}
			} )
		] );
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

	/** GB's own "none" icon (a circle with a slash), as its Typography buttons draw it. */
	function noneIcon() {
		return el( 'svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24, fill: 'currentColor', 'aria-hidden': true, focusable: false },
			el( 'path', { fillRule: 'evenodd', clipRule: 'evenodd', d: 'M12 18.5A6.5 6.5 0 0 1 6.93 7.931l9.139 9.138A6.473 6.473 0 0 1 12 18.5Zm5.123-2.498a6.5 6.5 0 0 0-9.124-9.124l9.124 9.124ZM4 12a8 8 0 1 1 16 0 8 8 0 0 1-16 0Z' } )
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
				normal: function () { return glyph( 'Ab' ); },
				italic: function () { return glyph( 'I', { fontStyle: 'italic' } ); },
				oblique: function () { return glyph( 'Ob', { fontStyle: 'oblique' } ); }
			},
			textTransform: {
				none: noneIcon,
				uppercase: function () { return glyph( 'AB' ); },
				lowercase: function () { return glyph( 'ab' ); },
				capitalize: function () { return glyph( 'Ab' ); }
			}
		};
		var make = icons[ group ] && icons[ group ][ value ];
		return make ? make() : null;
	}

	/**
	 * One-of-N button row. Uses GB's own ButtonIconControl (the Typography panel's group) when
	 * its styles-builder package is on the page, else a plain pressed-state button row. `fallback`
	 * shades the button for the value an unset group inherits (GB's `placeholder` look).
	 */
	function ChoiceButtons( p ) {
		var GbButtons = window.gb && window.gb.stylesBuilder && window.gb.stylesBuilder.ButtonIconControl;
		if ( GbButtons ) {
			return el( 'div', { className: 'bws-ts-field' }, [
				el( Heading, { key: 'l', level: p.level || 'l3', text: p.label } ),
				el( GbButtons, {
					key: 'b',
					value: p.value,
					placeholder: p.fallback || '',
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
						style: ! p.value && p.fallback === c.value ? { background: 'rgba(0,0,0,.07)' } : undefined,
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
			{ value: 'uppercase', label: 'AB', title: __( 'Uppercase', 'generateblocks' ) },
			{ value: 'lowercase', label: 'ab', title: __( 'Lowercase', 'generateblocks' ) },
			{ value: 'capitalize', label: 'Ab', title: __( 'Capitalize', 'generateblocks' ) }
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
				field = el( 'div', { className: 'bws-ts-colorrow' }, [
					el( ColorField, { key: 'c', label: LABELS[ key ], hideLabel: true, value: value, globals: true, placeholder: inh || __( 'Default', 'generateblocks' ), onChange: onChange } ),
					el( Swatch, { key: 's', value: value } )
				] );
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
					return '"' + head + '" ' + __( 'is not a recognized option.', 'generateblocks' ) + ( useInstead ? ' ' + useInstead : '' );
				} );
			case 'badStripeValue':
				return '"' + f.arg + '" ' + __( 'is not a color, an opacity or a sequence (odd or even), so it will not be used.', 'generateblocks' );
			case 'badBorderValue':
				return '"' + f.arg + '" ' + __( 'is not a width, a style, a color or an opacity, so it will not be used.', 'generateblocks' );
			case 'lineTakesNo':
				return '"' + f.arg + '" ' + __( 'will not be used. A single line takes a width and a style only; color and opacity are set once, for every line.', 'generateblocks' );
			case 'twoOfKind':
				return '"' + f.arg + '" ' + __( 'has two values of the same kind, so none of it will be used.', 'generateblocks' );
			case 'badRadius':
				return '"' + f.arg + '" ' + ERR.radius;
			case 'bareRadius':
				return '"' + f.arg + '" ' + COPY.bareRadius;
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
			return flagMessage( f, p.subject, p.useInstead === undefined ? __( 'Use *Header* instead.', 'generateblocks' ) : p.useInstead );
		} );
		if ( ! messages.length ) {
			return null;
		}
		// Boxed and headed by the setting it is about, so it reads as a notice and not as stray help text.
		return el( 'div', { className: 'bws-table-repair' + ( p.parsed === null ? ' is-error' : '' ) }, [
			el( Heading, { key: 'l', text: p.subject + ': ' + __( 'Stored Value Issue', 'generateblocks' ) } ),
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
					presets: [ '100%' ],
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
		return el( 'div', { className: 'bws-ts bws-ts-sizing' }, children );
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

		var styleKeys = TEXT_KEYS.filter( function ( k ) {
			return k !== 'textAlign';
		} );

		// Alignment sits outside the text style blocks (prototype round 87): Table | Header.
		return el( 'div', { className: 'bws-ts bws-ts-sect' }, [
			el( 'div', { key: 'align', className: 'bws-ts-pair' }, [
				el( ChoiceButtons, { key: 'tbl', level: 'l', label: __( 'Table Alignment', 'generateblocks' ), value: tableVals.textAlign, group: 'textAlign', choices: CHOICES.textAlign, onChange: function ( v ) {
					commit( 'base' )( 'textAlign', v );
				} } ),
				el( ChoiceButtons, { key: 'hdr', level: 'l', label: __( 'Header Alignment', 'generateblocks' ), value: headerVals.textAlign, fallback: tableVals.textAlign, group: 'textAlign', choices: CHOICES.textAlign, onChange: function ( v ) {
					commit( 'header' )( 'textAlign', v );
				} } )
			] ),
			el( TextStyleBlock, { key: 'table', heading: __( 'Table Text Styles', 'generateblocks' ), keys: styleKeys, values: tableVals, onChange: commit( 'base' ) } ),
			el( 'div', { key: 'div', className: 'bws-ts-div' } ),
			el( TextStyleBlock, { key: 'header', heading: __( 'Header Text Styles', 'generateblocks' ), keys: styleKeys, values: headerVals, inherited: inherited, onChange: commit( 'header' ) } ),
			notices
		] );
	}

	// ── Stripes ──────────────────────────────────────────────────────────────────

	var STRIPE_MODES = [ [ '', __( 'None', 'generateblocks' ) ], [ 'rows', __( 'Rows', 'generateblocks' ) ], [ 'cols', __( 'Columns', 'generateblocks' ) ] ];

	function defaults() {
		var d = window.bwsTableDefaults || {};
		return {
			color: d.stripeColor || V.stripeDefaultColor || 'currentColor',
			opacity: d.stripeOpacity !== undefined ? Number( d.stripeOpacity ) : Number( V.stripeDefaultOpacity || 5 )
		};
	}

	/** The stripe color as the renderer composes it (the thumb's preview; PHP: bws_table_stripe_color). */
	function composeStripe( color, opacity ) {
		return opacity >= 100 ? color : 'color-mix(in srgb,' + color + ' ' + T.cssNumber( opacity ) + '%,transparent)';
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
		var base = spec.color || d.color;
		// Under 576px the matrix's column headings are gone; each control carries its own label (.bws-ts-mob).
		var colorCell = el( Field, { key: 'c', mob: true, label: __( 'Color', 'generateblocks' ) }, el( ColorField, {
			label: __( 'Stripe Color', 'generateblocks' ),
			hideLabel: true,
			value: spec.color || '',
			placeholder: d.color,
			onChange: function ( v ) {
				set( { color: v || null } );
			}
		} ) );
		var opacityCell = hasAlpha
			? el( 'span', { key: 'o', className: 'bws-ts-help', style: { margin: 0 } }, COPY.opacityFromColor )
			: el( Field, { key: 'o', mob: true, wide: true, label: __( 'Opacity', 'generateblocks' ) }, el( 'div', { className: 'bws-ts-opacity' }, [
				el( 'div', { key: 'r', className: 'bws-ts-range' }, el( OpacityBar, {
					label: __( 'Stripe Opacity', 'generateblocks' ),
					value: shown,
					base: base,
					composed: composeStripe( base, shown ),
					isDefault: spec.opacity === null,
					onChange: function ( v ) {
						set( { opacity: v } );
					}
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
			] ) );
		var look = p.linkedTo
			// Linked: the first target's color and opacity are written to both, so there is
			// nothing to edit here; the link button below says how to part them.
			? [ el( 'span', { key: 'lk', className: 'bws-ts-help bws-ts-same', style: { margin: 0 } },
				__( 'Same as', 'generateblocks' ) + ' ' + p.linkedTo ) ]
			: hasAlpha
				? [ colorCell, el( 'span', { key: 'ol', className: 'bws-ts-l3 bws-ts-mob', 'aria-hidden': true }, __( 'Opacity', 'generateblocks' ) ), opacityCell ]
				: [ colorCell, opacityCell ];
		return el( 'div', { className: 'bws-ts-smx-row', role: 'group', 'aria-label': p.heading }, [
			el( Heading, { key: 'h', text: p.name } ),
			el( Field, { key: 'parity', mob: true, label: __( 'Pattern', 'generateblocks' ) }, el( SelectControl, {
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
			} ) )
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
		// null parse = a bracket the reader cannot trust; no box reads as ticked.
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
				el( CheckRow, { key: 'r', label: p.label, options: STRIPE_MODES, value: mode, onPick: setMode } )
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

	// ── Borders ──────────────────────────────────────────────────────────────────
	//
	// No Theme / None / Lines choice: nothing set is no `border` key, which is the theme. The base
	// (Width, Style, Color, Opacity) applies to every line; a ticked line overrides Width and Style
	// for that line only. A radius belongs to the Outer line, so it ticks Outer (and lifts Outer's
	// Default style to Solid, since a radius rounds only a drawn line); unticking Outer clears it.

	var BORDER_LINES = [
		[ 'outer', __( 'Outer', 'generateblocks' ) ],
		[ HEAD, __( 'Under header', 'generateblocks' ) ],
		[ 'rows', __( 'Between rows', 'generateblocks' ) ],
		[ 'cols', __( 'Between columns', 'generateblocks' ) ]
	];

	var BORDER_STYLES = [
		[ '', __( 'Default', 'generateblocks' ) ],
		[ 'none', __( 'None', 'generateblocks' ) ],
		[ 'solid', __( 'Solid', 'generateblocks' ) ],
		[ 'dashed', __( 'Dashed', 'generateblocks' ) ],
		[ 'dotted', __( 'Dotted', 'generateblocks' ) ]
	];

	// GB's own border-style icons (styles-builder, 16px), Default its faint rounded box.
	var BORDER_ICONS = {
		none: 'M9.414 8l3.293-3.293-1.414-1.414L8 6.586 4.707 3.293 3.293 4.707 6.586 8l-3.293 3.293 1.414 1.414L8 9.414l3.293 3.293 1.414-1.414L9.414 8z',
		solid: 'M1 7h14v2H1z',
		dashed: 'M0 7h4v2H0zm6 0h4v2H6zm6 0h4v2h-4z',
		dotted: 'M1 7h2v2H1zm4 0h2v2H5zm4 0h2v2H9zm4 0h2v2h-2z'
	};

	function borderIcon( style ) {
		var svg = { xmlns: 'http://www.w3.org/2000/svg', width: 16, height: 16, 'aria-hidden': true, focusable: false };
		if ( ! BORDER_ICONS[ style ] ) {
			return el( 'svg', Object.assign( svg, { viewBox: '0 0 256 256', style: { opacity: 0.1 } } ), el( 'rect', { x: 32, y: 32, width: 192, height: 192, rx: 16 } ) );
		}
		return el( 'svg', Object.assign( svg, { viewBox: '0 0 16 16' } ), el( 'path', { d: BORDER_ICONS[ style ] } ) );
	}

	/**
	 * A line style as GB's border dropdown draws it. `inherited` is the style an unset value
	 * draws, shown dimmed in place of Default.
	 */
	function BorderStyleField( p ) {
		var shown = p.value || p.inherited || '';
		var name = ( BORDER_STYLES.filter( function ( s ) {
			return s[ 0 ] === shown;
		} )[ 0 ] || BORDER_STYLES[ 0 ] )[ 1 ];
		var inherits = ! p.value && p.inherited;
		// "Default (Solid)": a dimmed "Solid" alone reads as a choice made.
		var text = inherits ? BORDER_STYLES[ 0 ][ 1 ] + ' (' + name + ')' : name;
		return el( C.DropdownMenu, {
			className: 'bws-ts-sdd' + ( inherits ? ' is-inherited' : '' ),
			icon: borderIcon( shown ),
			text: text,
			label: p.label + ': ' + text,
			controls: BORDER_STYLES.map( function ( s ) {
				return {
					title: s[ 1 ],
					icon: borderIcon( s[ 0 ] ),
					isActive: s[ 0 ] === ( p.value || '' ),
					role: 'menuitemradio',
					onClick: function () {
						p.onChange( s[ 0 ] );
					}
				};
			} )
		} );
	}

	/** A border width or radius: the combobox with px, em or rem offered for a typed number. */
	function BorderLengthField( p ) {
		return el( ComboField, {
			label: p.label,
			hideLabel: true,
			value: p.value,
			placeholder: p.placeholder,
			hint: __( 'Type a number, then pick a unit. A var() can be typed in.', 'generateblocks' ),
			errorKind: p.kind,
			validate: T.borderLengthValue,
			options: function ( typed ) {
				var q = typed.trim();
				return NUMBER_RE.test( q ) ? plain( ( V.borderUnits || [] ).map( function ( u ) {
					return q + u;
				} ) ) : [];
			},
			onChange: p.onChange
		} );
	}

	/** A blank width's placeholder: what it draws, worded like the style's "Default (Solid)". */
	function defaultWidth( w ) {
		return BORDER_STYLES[ 0 ][ 1 ] + ' (' + w + ')';
	}

	/** The first bare radius a `borderRadius` value carries, or null. */
	function bareArg( parsed ) {
		var f = parsed.flags.filter( function ( x ) {
			return x.code === 'bareRadius';
		} )[ 0 ];
		return f ? f.arg : null;
	}

	function emptyBorder() {
		var lines = {};
		BORDER_LINES.forEach( function ( l ) {
			lines[ l[ 0 ] ] = null;
		} );
		return { base: null, lines: lines, radius: null };
	}

	/** The editor's view of the two keys: a radius ticks Outer, and lifts its Default style to Solid. */
	function normalizeBorder( b ) {
		if ( ! b.radius ) {
			return b;
		}
		var outer = b.lines.outer || { width: null, style: null };
		if ( ! outer.style && ! ( b.base && b.base.style ) ) {
			outer = Object.assign( {}, outer, { style: 'solid' } );
		}
		return Object.assign( {}, b, { lines: Object.assign( {}, b.lines, { outer: outer } ) } );
	}

	function BordersControl( p ) {
		var state = p.context.state || {};
		var rawB = state.border === undefined ? '' : String( state.border );
		var rawR = state.borderRadius === undefined ? '' : String( state.borderRadius );
		var parsedB = rawB === '' ? null : T.parseBorder( rawB );
		var parsedR = rawR === '' ? null : T.parseBorderRadius( rawR );
		var view = emptyBorder();
		if ( parsedB ) {
			view.base = parsedB.base;
			view.lines = Object.assign( {}, parsedB.lines );
		}
		view.radius = parsedR ? parsedR.outer : null;
		view = normalizeBorder( view );

		var base = view.base || { width: null, style: null, color: null, opacity: null };
		var drawn = T.borderLines( view );
		// What the base alone gives a line: the style the base's own Default stands for.
		var probe = emptyBorder();
		probe.base = base;
		probe.lines.outer = { width: null, style: null };
		var baseDrawn = T.borderLines( probe ).outer;

		function write( next ) {
			next = normalizeBorder( next );
			var patch = { border: T.emitBorder( next ) };
			// The radius key is rewritten only when the radius itself changed, so a hand-edited value
			// the reader cannot take (a bare `8px`) survives an edit to the lines.
			if ( next.radius !== view.radius ) {
				patch.borderRadius = T.emitBorderRadius( next.radius );
			}
			p.context.setState( applyPatch( state, patch ) );
		}
		function setBase( patch ) {
			write( Object.assign( {}, view, { base: Object.assign( {}, base, patch ) } ) );
		}
		function setLine( k, line ) {
			var lines = Object.assign( {}, view.lines );
			lines[ k ] = line;
			write( Object.assign( {}, view, { lines: lines, radius: k === 'outer' && ! line ? null : view.radius } ) );
		}

		var hasAlpha = base.color && T.colorHasAlpha( base.color );
		var swatchBase = base.color || 'currentColor';
		var shownOpacity = base.opacity === null ? 100 : base.opacity;
		// A blank width draws 1px wherever the key reaches a line; "Theme default" only while it reaches none.
		var widthPlaceholder = base.style ? defaultWidth( '1px' ) : __( 'Theme default', 'generateblocks' );

		var children = [
			// Heading and fields in one block, as Stripes does, so the heading keeps its own 8px gap.
			el( 'div', { key: 'base', className: 'bws-ts-field' }, [
			el( Heading, { key: 'l', text: p.label } ),
			el( 'div', { key: 'pair', className: 'bws-ts-pair' }, [
				el( Field, { key: 'w', label: __( 'Width', 'generateblocks' ) }, el( BorderLengthField, {
					label: __( 'Border Width', 'generateblocks' ),
					kind: 'borderWidth',
					value: base.width || '',
					placeholder: widthPlaceholder,
					onChange: function ( v ) {
						setBase( { width: v || null } );
					}
				} ) ),
				el( Field, { key: 's', label: __( 'Style', 'generateblocks' ) }, el( BorderStyleField, {
					label: __( 'Border Style', 'generateblocks' ),
					value: base.style || '',
					inherited: baseDrawn ? baseDrawn.style : '',
					onChange: function ( v ) {
						setBase( { style: v || null } );
					}
				} ) ),
				el( Field, { key: 'c', label: __( 'Color', 'generateblocks' ) }, el( 'div', { className: 'bws-ts-colorrow' }, [
					el( ColorField, {
						key: 'c',
						label: __( 'Border Color', 'generateblocks' ),
						hideLabel: true,
						value: base.color || '',
						placeholder: __( 'Theme default', 'generateblocks' ),
						onChange: function ( v ) {
							setBase( { color: v || null } );
						}
					} ),
					el( Swatch, { key: 's', value: base.color || '' } )
				] ) ),
				hasAlpha
					? el( 'span', { key: 'o', className: 'bws-ts-help', style: { margin: 0, alignSelf: 'center' } }, COPY.opacityFromColor )
					: el( Field, { key: 'o', label: __( 'Opacity', 'generateblocks' ) }, el( 'div', { className: 'bws-ts-opacity' }, [
						el( 'div', { key: 'r', className: 'bws-ts-range' }, el( OpacityBar, {
							label: __( 'Border Opacity', 'generateblocks' ),
							value: shownOpacity,
							base: swatchBase,
							composed: composeStripe( swatchBase, shownOpacity ),
							isDefault: base.opacity === null,
							onChange: function ( v ) {
								setBase( { opacity: v } );
							}
						} ) ),
						// No default opacity: unset means the color as it is, shown as a dash.
						el( 'output', { key: 'v', className: 'bws-ts-readout' + ( base.opacity === null ? ' is-default' : '' ) }, base.opacity === null ? '—' : T.cssNumber( base.opacity ) + '%' ),
						base.opacity === null ? null : el( Button, {
							key: 'x',
							variant: 'tertiary',
							isSmall: true,
							onClick: function () {
								setBase( { opacity: null } );
							}
						}, __( 'Reset', 'generateblocks' ) )
					] ) )
			] ) ] ),
			el( 'div', { key: 'ov', className: 'bws-ts-field' }, [
				el( Heading, { key: 'l', text: __( 'Override Specific Borders', 'generateblocks' ) } ),
				el( 'div', { key: 'c', className: 'bws-ts-radios', role: 'group', 'aria-label': __( 'Override Specific Borders', 'generateblocks' ) },
					BORDER_LINES.map( function ( l ) {
						return el( C.CheckboxControl, {
							key: l[ 0 ],
							label: l[ 1 ],
							checked: !! view.lines[ l[ 0 ] ],
							onChange: function ( on ) {
								setLine( l[ 0 ], on ? { width: null, style: null } : null );
							},
							__nextHasNoMarginBottom: true
						} );
					} )
				)
			] )
		];

		var ticked = BORDER_LINES.filter( function ( l ) {
			return view.lines[ l[ 0 ] ];
		} );
		if ( ticked.length ) {
			var withRadius = !! view.lines.outer;
			var cells = [
				el( 'span', { key: 'g' } ),
				el( Heading, { key: 'w', level: 'l3', text: __( 'Width', 'generateblocks' ) } ),
				el( Heading, { key: 's', level: 'l3', text: __( 'Style', 'generateblocks' ) } ),
				withRadius ? el( Heading, { key: 'r', level: 'l3', text: __( 'Radius', 'generateblocks' ) } ) : null
			];
			ticked.forEach( function ( l ) {
				var k = l[ 0 ];
				var line = view.lines[ k ];
				var row = [
					el( Heading, { key: 'h', text: l[ 1 ] } ),
					el( Field, { key: 'w', mob: true, label: __( 'Width', 'generateblocks' ) }, el( BorderLengthField, {
						label: l[ 1 ] + ' ' + __( 'Width', 'generateblocks' ),
						kind: 'borderWidth',
						value: line.width || '',
						placeholder: drawn[ k ] ? defaultWidth( drawn[ k ].width ) : __( 'Theme default', 'generateblocks' ),
						onChange: function ( v ) {
							setLine( k, Object.assign( {}, line, { width: v || null } ) );
						}
					} ) ),
					el( Field, { key: 's', mob: true, label: __( 'Style', 'generateblocks' ) }, el( BorderStyleField, {
						label: l[ 1 ] + ' ' + __( 'Style', 'generateblocks' ),
						value: line.style || '',
						inherited: drawn[ k ] ? drawn[ k ].style : '',
						onChange: function ( v ) {
							setLine( k, Object.assign( {}, line, { style: v || null } ) );
						}
					} ) )
				];
				if ( withRadius ) {
					row.push( k === 'outer'
						? el( Field, { key: 'r', mob: true, label: __( 'Radius', 'generateblocks' ) }, el( BorderLengthField, {
							label: __( 'Outer Radius', 'generateblocks' ),
							kind: 'radius',
							value: view.radius || '',
							placeholder: '0',
							onChange: function ( v ) {
								write( Object.assign( {}, view, { radius: v || null } ) );
							}
						} ) )
						: el( 'span', { key: 'r' } ) );
				}
				cells.push( el( 'div', { key: k, className: 'bws-ts-smx-row', role: 'group', 'aria-label': l[ 1 ] }, row ) );
			} );
			children.push( el( 'div', { key: 'bmx', className: 'bws-ts-bmx' + ( withRadius ? ' bws-ts-bmx--r' : '' ) }, cells ) );
		}

		if ( rawB !== '' && ( parsedB === null || parsedB.flags.length ) ) {
			children.push( el( RepairNotice, {
				key: 'repair',
				subject: p.label,
				useInstead: '',
				parsed: parsedB,
				fixed: parsedB === null ? '' : T.emitBorder( parsedB ),
				onRepair: function ( fixed ) {
					p.context.setState( applyPatch( state, { border: fixed } ) );
				}
			} ) );
		}
		if ( rawR !== '' && ( parsedR === null || parsedR.flags.length ) ) {
			children.push( el( RepairNotice, {
				key: 'repairR',
				subject: __( 'Radius', 'generateblocks' ),
				useInstead: '',
				parsed: parsedR,
				// A bare length is the author's radius for the table: Fix moves it to Outer, unless Outer already has one.
				fixed: parsedR === null ? '' : T.emitBorderRadius( parsedR.outer !== null ? parsedR.outer : bareArg( parsedR ) ),
				onRepair: function ( fixed ) {
					p.context.setState( applyPatch( state, { borderRadius: fixed } ) );
				}
			} ) );
		}
		return el( 'div', { className: 'bws-ts bws-ts-borders' }, children );
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
		[ 'header', __( 'Header', 'generateblocks' ) ]
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
			if ( col[ k ] && col[ k ].header && k !== 'textAlign' ) {
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
				var keepHeader = m === 'header' || m === 'both' || k === 'textAlign';
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
		var alignHeader = col.textAlign ? col.textAlign.header || '' : '';
		var tagAlign = tag.textAlign || {};
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
				keys: bodyKeys,
				values: valuesOf( 'header', bodyKeys ),
				inherited: inheritedHeader,
				onChange: commit( 'header' )
			} ) );
		}
		var children = [
			el( 'div', { key: 'align', className: 'bws-ts-pair' }, [
				el( ChoiceButtons, {
					key: 'col',
					level: 'l',
					label: __( 'Column Alignment', 'generateblocks' ),
					value: alignBase,
					fallback: tagAlign.base || '',
					group: 'textAlign',
					choices: CHOICES.textAlign,
					onChange: function ( v ) {
						commit( 'base' )( 'textAlign', v );
					}
				} ),
				el( ChoiceButtons, {
					key: 'hdr',
					level: 'l',
					label: __( 'Header Alignment', 'generateblocks' ),
					value: alignHeader,
					fallback: alignBase || tagAlign.header || tagAlign.base || '',
					group: 'textAlign',
					choices: CHOICES.textAlign,
					onChange: function ( v ) {
						commit( 'header' )( 'textAlign', v );
					}
				} )
			] ),
			el( 'div', { key: 'modes', className: 'bws-ts-field' }, [
				el( Heading, { key: 'l', text: __( 'Custom Column Text Styles', 'generateblocks' ) } ),
				el( CheckRow, { key: 'r', label: __( 'Custom Column Text Styles', 'generateblocks' ), options: COLUMN_MODES, value: mode, onPick: pickMode } )
			] )
		];
		if ( blocks.length ) {
			children.push( el( 'div', { key: 'blocks', className: 'bws-ts bws-ts-sect bws-ts-sub' }, blocks ) );
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
		'bws-table-stripes': StripesControl,
		'bws-table-borders': BordersControl
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
		BordersControl: BordersControl,
		ColumnWidthField: ColumnWidthField,
		Field: Field,
		Heading: Heading,
		defaults: defaults,
		ColumnStyle: ColumnStyle
	};
}() );
