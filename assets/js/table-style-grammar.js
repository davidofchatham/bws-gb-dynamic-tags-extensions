/**
 * {{table}} presentation values — the JS TWIN of the validators and readers in
 * includes/helpers/table-helpers.php.
 *
 * This file carries NO independent decisions. The PHP side validates and renders; this side
 * lets the editor controls say the same thing about a value BEFORE it is saved, so an author
 * is told "not a recognized width" in the modal rather than finding a table that ignores it.
 * Agreement is TESTED, not assumed: tools/test/table-style-twin-test.php runs one corpus
 * through both implementations and diffs the results.
 *
 * What is NOT copied: every LIST (units, size keywords, color names, the CSS globals, the text
 * keys) arrives as `window.bwsTableVocab`, inlined from bws_table_style_vocabulary(), so the two
 * languages cannot carry different lists. Only the regex SHAPES live here, and the harness holds
 * those.
 *
 * Splitting goes through ONE function, headArgsItems(), over the fold grammar's splitDepth:
 * every `head,args;head,args` value (the six text keys, `stripe`, `border`, `borderRadius`) is
 * read by it, so a
 * hand-edited value cannot read two ways. Its PHP twin is bws_head_args_items().
 *
 * @package BWS_Dynamic_Tags
 * @since   1.22.0
 */
( function () {
	'use strict';

	var V = window.bwsTableVocab || {};
	var list = function ( name ) {
		return V[ name ] || [];
	};

	function inList( name, value ) {
		return list( name ).indexOf( value ) !== -1;
	}

	var HEADER = V.headerHead || 'header';

	// ── Values ────────────────────────────────────────────────────────────────

	function unitRe() {
		return '(' + list( 'units' ).map( function ( u ) {
			return u.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
		} ).join( '|' ) + ')';
	}

	/** `var(--name)` or `var(--name, fallback)`, fallback without parentheses; canonical or null. */
	function cssVar( value ) {
		var m = /^var\(\s*(--[A-Za-z0-9_-]+)\s*(?:,\s*([#A-Za-z0-9 .%,_-]+?)\s*)?\)$/.exec( String( value ).trim() );
		return m ? 'var(' + m[ 1 ] + ( m[ 2 ] !== undefined ? ',' + m[ 2 ] : '' ) + ')' : null;
	}

	/** `auto`, a number with a unit, or `var()`. */
	function widthValue( candidate ) {
		var c = String( candidate ).trim();
		if ( c.toLowerCase() === 'auto' ) {
			return 'auto';
		}
		var m = new RegExp( '^(\\d*\\.?\\d+)' + unitRe() + '$', 'i' ).exec( c );
		return m ? m[ 1 ] + m[ 2 ].toLowerCase() : cssVar( c );
	}

	/** The strict color allowlist. `globals` also admits the CSS globals (text color only). */
	function colorValue( candidate, globals ) {
		var c = String( candidate ).trim();
		var l = c.toLowerCase();
		if ( l === 'currentcolor' ) {
			return 'currentColor';
		}
		if ( globals && inList( 'globals', l ) ) {
			return l;
		}
		if ( /^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test( c ) ) {
			return l;
		}
		if ( /^(?:rgba?|hsla?)\(\s*[0-9a-z.%+\/\s,-]+\)$/i.test( c ) ) {
			return l.replace( /\s+/g, ' ' );
		}
		if ( inList( 'colorNames', l ) ) {
			return l;
		}
		return cssVar( c );
	}

	/** Whether a canonical color carries its own alpha, so an opacity does not apply to it. */
	function colorHasAlpha( color ) {
		return /^(?:#(?:[0-9a-f]{4}|[0-9a-f]{8})|rgba\(.*|hsla\(.*|(?:rgb|hsl)\(.*\/.*|transparent)$/i.test( color );
	}

	function fontSizeValue( candidate ) {
		var c = String( candidate ).trim();
		var l = c.toLowerCase();
		if ( inList( 'fontSizeKeywords', l ) || inList( 'globals', l ) ) {
			return l;
		}
		var m = new RegExp( '^(\\d*\\.?\\d+)' + unitRe() + '$', 'i' ).exec( c );
		return m ? m[ 1 ] + m[ 2 ].toLowerCase() : cssVar( c );
	}

	/** One text-key value, or null when the key is unknown or the value refused. */
	function textValue( key, candidate ) {
		var l = String( candidate ).trim().toLowerCase();
		var glob = inList( 'globals', l );
		switch ( key ) {
			case 'color':
				return colorValue( candidate, true );
			case 'fontSize':
				return fontSizeValue( candidate );
			case 'fontWeight':
				return glob || l === 'normal' || l === 'bold' || /^[1-9]00$/.test( l ) ? l : null;
			case 'fontStyle':
				return glob || inList( 'fontStyles', l ) ? l : null;
			case 'textAlign':
				return glob || inList( 'textAligns', l ) ? l : null;
			case 'textTransform':
				return glob || inList( 'textTransforms', l ) ? l : null;
		}
		return null;
	}

	/** A stripe opacity `N%`, 0 to 100, as a number; null when it is neither. */
	function opacityValue( candidate ) {
		var m = /^(\d*\.?\d+)%$/.exec( String( candidate ).trim() );
		return m && parseFloat( m[ 1 ] ) <= 100 ? parseFloat( m[ 1 ] ) : null;
	}

	/** Locale-free number for the wire, no trailing zeros (twin of bws_table_css_number()). */
	function cssNumber( n ) {
		return String( parseFloat( Number( n ).toFixed( 4 ) ) );
	}

	// ── The shared head,args tokenizer ────────────────────────────────────────

	/**
	 * Items split on `;`, parts on `,`, both outside brackets. Null on an unbalanced bracket.
	 * Twin of bws_head_args_items().
	 */
	function headArgsItems( value ) {
		var fold = window.bwsSlotFold;
		var items = fold.splitDepth( String( value ), [ ';' ] );
		if ( items.error ) {
			return null;
		}
		var out = [];
		items.forEach( function ( item ) {
			item = item.trim();
			if ( item !== '' ) {
				out.push( {
					raw: item,
					parts: fold.splitDepth( item, [ ',' ] ).map( function ( p ) {
						return p.trim();
					} )
				} );
			}
		} );
		return out;
	}

	/** A flag is `{ code, text }`: the code drives the editor's message, the text the harness count. */
	function flag( code, text, arg ) {
		return { code: code, text: text, arg: arg };
	}

	/**
	 * Read a `base;header,x` value. Twin of bws_headed_value_parse(); same drops, same flags
	 * (each flag here corresponds to one PHP flag string).
	 */
	function parseText( key, value ) {
		var items = headArgsItems( value );
		if ( items === null ) {
			return null;
		}
		var out = { base: null, header: null, flags: [] };
		items.forEach( function ( item ) {
			var parts = item.parts;
			var slot;
			var arg;
			if ( parts.length === 1 ) {
				slot = 'base';
				arg = parts[ 0 ];
			} else if ( parts[ 0 ] === HEADER && parts.length === 2 ) {
				slot = 'header';
				arg = parts[ 1 ];
			} else if ( parts[ 0 ] === HEADER ) {
				out.flags.push( flag( 'headerArity', "header item '" + item.raw + "' takes one value" ) );
				return;
			} else {
				out.flags.push( flag( 'unknownHead', "unknown head '" + parts[ 0 ] + "'" ) );
				return;
			}
			var canon = arg === '' ? null : textValue( key, arg );
			if ( canon === null ) {
				out.flags.push( flag( 'badValue', "'" + item.raw + "' is not a recognized value", arg ) );
			} else if ( out[ slot ] !== null ) {
				out.flags.push( flag( 'repeated', 'repeated ' + slot + " item '" + item.raw + "'" ) );
			} else {
				out[ slot ] = canon;
			}
		} );
		return out;
	}

	/** Emit `{ base, header }` as `base;header,x`, base first; '' when neither is set. */
	function emitText( parsed ) {
		var out = [];
		if ( parsed && parsed.base ) {
			out.push( parsed.base );
		}
		if ( parsed && parsed.header ) {
			out.push( HEADER + ',' + parsed.header );
		}
		return out.join( ';' );
	}

	/**
	 * Read a `stripe` value. Twin of bws_table_stripe_parse(). Each target is
	 * `{ parity, color, opacity }`; a refused color is dropped and flagged, never carried.
	 */
	function parseStripe( value ) {
		var items = headArgsItems( value );
		if ( items === null ) {
			return null;
		}
		var out = { rows: null, cols: null, flags: [] };
		items.forEach( function ( item ) {
			var head = item.parts[ 0 ].toLowerCase();
			if ( head !== 'rows' && head !== 'cols' ) {
				out.flags.push( flag( 'unknownHead', "unknown head '" + item.parts[ 0 ] + "'" ) );
				return;
			}
			if ( out[ head ] !== null ) {
				out.flags.push( flag( 'repeatedTarget', "repeated target '" + item.raw + "'" ) );
				return;
			}
			var spec = { parity: 'even', color: null, opacity: null };
			var seen = {};
			item.parts.slice( 1 ).forEach( function ( arg ) {
				var l = arg.toLowerCase();
				var slot;
				var val;
				var opacity;
				var color;
				if ( l === 'odd' || l === 'even' ) {
					slot = 'parity';
					val = l;
				} else if ( ( opacity = opacityValue( arg ) ) !== null ) {
					slot = 'opacity';
					val = opacity;
				} else if ( ( color = colorValue( arg, false ) ) !== null ) {
					slot = 'color';
					val = color;
				} else {
					out.flags.push( flag( 'badStripeValue', "'" + arg + "' is not a recognized stripe value", arg ) );
					return;
				}
				if ( seen[ slot ] ) {
					out.flags.push( flag( 'repeatedArg', 'repeated ' + slot + " '" + arg + "'", arg ) );
					return;
				}
				seen[ slot ] = true;
				spec[ slot ] = val;
			} );
			out[ head ] = spec;
		} );
		return out;
	}

	/** Emit a parsed stripe: `rows,odd,#eee,40%;cols`. Parity is written only when not the `even` default. */
	function emitStripe( stripe ) {
		var items = [];
		[ 'rows', 'cols' ].forEach( function ( target ) {
			var spec = stripe && stripe[ target ];
			if ( ! spec ) {
				return;
			}
			var parts = [ target ];
			if ( spec.parity === 'odd' ) {
				parts.push( 'odd' );
			}
			if ( spec.color ) {
				parts.push( spec.color );
			}
			if ( spec.opacity !== null && spec.opacity !== undefined ) {
				parts.push( cssNumber( spec.opacity ) + '%' );
			}
			items.push( parts.join( ',' ) );
		} );
		return items.join( ';' );
	}

	// ── Borders ───────────────────────────────────────────────────────────────

	/** A border width or radius: a number with px, em or rem, or `var()`. Twin of bws_table_border_length_value(). */
	function borderLengthValue( candidate ) {
		var c = String( candidate ).trim();
		var units = '(' + list( 'borderUnits' ).join( '|' ) + ')';
		var m = new RegExp( '^(\\d*\\.?\\d+)' + units + '$', 'i' ).exec( c );
		return m ? m[ 1 ] + m[ 2 ].toLowerCase() : cssVar( c );
	}

	/**
	 * Read a `border` value. Twin of bws_table_border_parse() (its PHPDoc states the policy):
	 * `{ base: {width,style,color,opacity}|null, lines: {outer,header,rows,cols: {width,style}|null}, flags }`.
	 */
	function parseBorder( value ) {
		var items = headArgsItems( value );
		if ( items === null ) {
			return null;
		}
		var heads = list( 'borderHeads' );
		var lines = {};
		heads.forEach( function ( h ) {
			lines[ h ] = null;
		} );
		var out = { base: null, lines: lines, flags: [] };
		items.forEach( function ( item ) {
			var head = item.parts[ 0 ].toLowerCase();
			var isLine = heads.indexOf( head ) !== -1;
			if ( isLine ? lines[ head ] !== null : out.base !== null ) {
				out.flags.push( flag( 'repeatedTarget', ( isLine ? 'repeated line' : 'repeated base' ) + " '" + item.raw + "'" ) );
				return;
			}
			var spec = isLine ? { width: null, style: null } : { width: null, style: null, color: null, opacity: null };
			var dup = false;
			var args = isLine ? item.parts.slice( 1 ) : item.parts;
			for ( var i = 0; i < args.length; i++ ) {
				var arg = args[ i ];
				var l = arg.toLowerCase();
				var slot;
				var val;
				var v;
				if ( inList( 'borderStyles', l ) ) {
					slot = 'style';
					val = l;
				} else if ( ( v = opacityValue( arg ) ) !== null ) {
					slot = 'opacity';
					val = v;
				} else if ( ( v = borderLengthValue( arg ) ) !== null ) {
					slot = 'width';
					val = v;
				} else if ( ( v = colorValue( arg, false ) ) !== null ) {
					slot = 'color';
					val = v;
				} else {
					out.flags.push( flag( 'badBorderValue', "'" + arg + "' is not a recognized border value", arg ) );
					continue;
				}
				if ( ! Object.prototype.hasOwnProperty.call( spec, slot ) ) {
					out.flags.push( flag( 'lineTakesNo', "line '" + head + "' takes no " + slot + " ('" + arg + "')", arg ) );
					continue;
				}
				if ( spec[ slot ] !== null ) {
					dup = true;
					break;
				}
				spec[ slot ] = val;
			}
			if ( dup ) {
				out.flags.push( flag( 'twoOfKind', "two of one kind in '" + item.raw + "'", item.raw ) );
			} else if ( isLine ) {
				lines[ head ] = spec;
			} else {
				out.base = spec;
			}
		} );
		return out;
	}

	/** One segment's args in the reader's slot order. */
	function borderArgs( spec, keys ) {
		var parts = [];
		keys.forEach( function ( k ) {
			if ( spec[ k ] !== null && spec[ k ] !== undefined && spec[ k ] !== '' ) {
				parts.push( k === 'opacity' ? cssNumber( spec[ k ] ) + '%' : spec[ k ] );
			}
		} );
		return parts;
	}

	/** Emit a parsed border: the base first, then each line in head order; '' when nothing is set. */
	function emitBorder( border ) {
		if ( ! border ) {
			return '';
		}
		var segs = [];
		var base = border.base ? borderArgs( border.base, [ 'width', 'style', 'color', 'opacity' ] ) : [];
		if ( base.length ) {
			segs.push( base.join( ',' ) );
		}
		list( 'borderHeads' ).forEach( function ( h ) {
			var line = border.lines && border.lines[ h ];
			if ( line ) {
				segs.push( [ h ].concat( borderArgs( line, [ 'width', 'style' ] ) ).join( ',' ) );
			}
		} );
		return segs.join( ';' );
	}

	/** What a parsed border draws, per line. Twin of bws_table_border_lines() (its PHPDoc owns the rule). */
	function borderLines( border ) {
		var base = border.base || {};
		var bw = base.width || null;
		var bs = base.style || null;
		var draws = bw !== null || bs !== null;
		var out = {};
		list( 'borderHeads' ).forEach( function ( h ) {
			var line = border.lines[ h ] || null;
			if ( ! draws && ( line === null || ( line.width === null && line.style === null ) ) ) {
				out[ h ] = null;
				return;
			}
			var style = line && line.style !== null ? line.style : ( bs !== null && ! ( bs === 'none' && line !== null ) ? bs : 'solid' );
			out[ h ] = {
				off: style === 'none',
				width: line && line.width !== null ? line.width : ( bw !== null ? bw : '1px' ),
				style: style
			};
		} );
		return out;
	}

	/** The base's line color, or null for the theme's. Twin of bws_table_border_color(). */
	function borderColor( base ) {
		if ( ! base || ( base.color === null && base.opacity === null ) ) {
			return null;
		}
		var color = base.color !== null ? base.color : 'currentColor';
		return base.opacity === null || colorHasAlpha( color )
			? color
			: 'color-mix(in srgb,' + color + ' ' + cssNumber( base.opacity ) + '%,transparent)';
	}

	/** Read a `borderRadius` value. Twin of bws_table_border_radius_parse(). */
	function parseBorderRadius( value ) {
		var items = headArgsItems( value );
		if ( items === null ) {
			return null;
		}
		var out = { outer: null, flags: [] };
		items.forEach( function ( item ) {
			var parts = item.parts;
			var len;
			if ( parts.length === 1 && borderLengthValue( parts[ 0 ] ) !== null ) {
				out.flags.push( flag( 'bareRadius', "bare radius '" + parts[ 0 ] + "' is reserved for cells", parts[ 0 ] ) );
			} else if ( parts[ 0 ].toLowerCase() !== 'outer' ) {
				out.flags.push( flag( 'unknownHead', "unknown head '" + parts[ 0 ] + "'" ) );
			} else if ( out.outer !== null ) {
				out.flags.push( flag( 'repeatedTarget', "repeated line '" + item.raw + "'" ) );
			} else if ( parts.length !== 2 || ( len = borderLengthValue( parts[ 1 ] ) ) === null ) {
				out.flags.push( flag( 'badRadius', "'" + item.raw + "' is not a recognized radius", parts.slice( 1 ).join( ',' ) ) );
			} else {
				out.outer = len;
			}
		} );
		return out;
	}

	/** Emit a radius: `outer,8px`, or '' for none. */
	function emitBorderRadius( outer ) {
		return outer ? 'outer,' + outer : '';
	}

	/**
	 * The cascade, twin of bws_table_text_for_cell(): the value one cell takes for one text
	 * key, or '' for none.
	 */
	function textForCell( key, table, column, isHeader ) {
		var covers = list( 'columnCoversHeader' ).indexOf( key ) !== -1;
		var levels = isHeader
			? [ column && column.header, covers ? column && column.base : null, table && table.header, table && table.base ]
			: [ column && column.base, table && table.base ];
		for ( var i = 0; i < levels.length; i++ ) {
			if ( levels[ i ] ) {
				return levels[ i ];
			}
		}
		return '';
	}

	// ── The rule builder ──────────────────────────────────────────────────────

	function present( obj, key ) {
		return obj[ key ] !== undefined && obj[ key ] !== null;
	}

	/** Twin of bws_table_text_read(): key => parse, only keys that parsed to something. */
	function textRead( options ) {
		var text = {};
		list( 'textKeys' ).forEach( function ( key ) {
			var parsed = present( options, key ) ? parseText( key, String( options[ key ] ) ) : null;
			if ( parsed !== null && ( parsed.base !== null || parsed.header !== null ) ) {
				text[ key ] = parsed;
			}
		} );
		return text;
	}

	/** Twin of bws_table_style_read(): a table's presentation keys off its options. */
	function styleRead( options ) {
		var layout = String( options.layout || '' ).trim().toLowerCase();
		var stripe = present( options, 'stripe' ) ? parseStripe( String( options.stripe ) ) : null;
		var border = present( options, 'border' ) ? parseBorder( String( options.border ) ) : null;
		var radius = present( options, 'borderRadius' ) ? parseBorderRadius( String( options.borderRadius ) ) : null;
		var anyLine = border !== null && Object.keys( border.lines ).some( function ( h ) {
			return border.lines[ h ] !== null;
		} );
		return {
			layout: layout === 'auto' || layout === 'fixed' ? layout : null,
			width: present( options, 'width' ) ? widthValue( String( options.width ) ) : null,
			text: textRead( options ),
			stripe: stripe !== null && ( stripe.rows !== null || stripe.cols !== null ) ? stripe : null,
			border: border !== null && ( border.base !== null || anyLine ) ? border : null,
			radius: radius !== null ? radius.outer : null
		};
	}

	/** Twin of bws_table_column_style_read(): a column's width and text tokens. */
	function columnStyleRead( slotOpts ) {
		return {
			width: present( slotOpts, 'width' ) ? widthValue( String( slotOpts.width ) ) : null,
			text: textRead( slotOpts )
		};
	}

	/** Twin of bws_table_stripe_color(): the color expression one stripe paints. */
	function stripeColor( spec, defaults ) {
		function compose( color, opacity ) {
			return colorHasAlpha( color ) ? color : 'color-mix(in srgb,' + color + ' ' + cssNumber( opacity ) + '%,transparent)';
		}
		if ( spec.color === null && spec.opacity === null ) {
			return 'var(--bws-dte-table-stripe,' + compose( defaults.color, defaults.opacity ) + ')';
		}
		return compose( spec.color !== null ? spec.color : defaults.color, spec.opacity !== null ? spec.opacity : defaults.opacity );
	}

	/**
	 * Twin of bws_table_rules(): the ordered `{ sel, decl }` list the editor preview draws from.
	 * The PHP builder is the owner (its PHPDoc states the rules); tools/test/table-style-twin-test.php
	 * diffs this against it over a corpus, so the thumbnail cannot disagree with the page.
	 */
	function rules( table, columns, hasHeader, stripeDefaults ) {
		var out = [];
		var props = V.textProps || {};
		function add( sel, decls ) {
			if ( decls.length ) {
				out.push( { sel: sel, decl: decls.join( ';' ) } );
			}
		}
		function decl( key, value ) {
			return props[ key ] + ':' + value;
		}
		var keys = list( 'textKeys' );

		var self = [];
		if ( table.layout !== null ) {
			self.push( 'table-layout:' + table.layout );
		}
		var width = table.width !== null ? table.width : ( table.layout === 'fixed' ? '100%' : null );
		if ( width !== null ) {
			self.push( 'width:' + width );
		}
		add( [ '' ], self );

		var base = [];
		var header = [];
		keys.forEach( function ( key ) {
			var parsed = table.text[ key ];
			if ( ! parsed ) {
				return;
			}
			if ( parsed.base !== null ) {
				base.push( decl( key, parsed.base ) );
			}
			if ( hasHeader && parsed.header !== null ) {
				header.push( decl( key, parsed.header ) );
			}
		} );
		add( [ ' :is(th,td)' ], base );
		add( [ ' thead th' ], header );

		[ 'rows', 'cols' ].forEach( function ( target ) {
			var spec = table.stripe ? table.stripe[ target ] : null;
			if ( ! spec ) {
				return;
			}
			var even = spec.parity === 'even';
			var color = stripeColor( spec, stripeDefaults );
			var sel;
			if ( target === 'cols' ) {
				sel = [ ' :is(th,td):nth-child(' + ( even ? 'even' : 'odd' ) + ')' ];
			} else if ( hasHeader ) {
				sel = even ? [ ' tbody tr:nth-child(odd)' ] : [ ' thead tr', ' tbody tr:nth-child(even)' ];
			} else {
				sel = [ ' tbody tr:nth-child(' + ( even ? 'even' : 'odd' ) + ')' ];
			}
			add( sel, [ 'background-image:linear-gradient(' + color + ',' + color + ')' ] );
		} );

		// Borders: the PHP builder's comment states the edge-ownership scheme.
		var heads = list( 'borderHeads' );
		var border = table.border || null;
		var radius = table.radius || null;
		var lines = border ? borderLines( border ) : {};
		var bcolor = border ? borderColor( border.base ) : null;
		function edge( side, ln ) {
			return ln.off ? [ 'border-' + side + '-width:0' ] : [ 'border-' + side + '-width:' + ln.width, 'border-' + side + '-style:' + ln.style ];
		}
		if ( bcolor !== null ) {
			add( [ '', ' :is(th,td)' ], [ 'border-color:' + bcolor ] );
		}
		var line = lines.outer || null;
		if ( line ) {
			add( [ '' ], line.off ? [ 'border-width:0' ] : [ 'border-width:' + line.width, 'border-style:' + line.style ] );
			add( [ ' :is(th,td):first-child' ], [ 'border-left-width:0' ] );
			add( [ ' :is(th,td):last-child' ], [ 'border-right-width:0' ] );
			add( [ ' thead th', ':not(:has(> thead)) tbody tr:first-child td' ], [ 'border-top-width:0' ] );
			add( [ ' tbody tr:last-child td' ], [ 'border-bottom-width:0' ] );
		}
		if ( ( line = lines.cols || null ) ) {
			add( [ ' :is(th,td):not(:last-child)' ], edge( 'right', line ) );
			add( [ ' :is(th,td):not(:first-child)' ], [ 'border-left-width:0' ] );
		}
		if ( ( line = lines[ heads[ 1 ] ] || null ) ) {
			add( [ ' thead th' ], edge( 'bottom', line ) );
			add( [ ' thead + tbody tr:first-child td' ], [ 'border-top-width:0' ] );
		}
		if ( ( line = lines.rows || null ) ) {
			add( [ ' tbody tr:not(:last-child) td' ], edge( 'bottom', line ) );
			add( [ ' tbody tr:not(:first-child) td' ], [ 'border-top-width:0' ] );
		}
		if ( radius !== null ) {
			var outer = lines.outer || null;
			var clip = outer && ! outer.off ? 'max(0px,calc(' + radius + ' - ' + outer.width + '))' : radius;
			add( [ '' ], [ 'border-collapse:separate', 'border-spacing:0', 'border-radius:' + radius ] );
			add( [ ' :is(th,td):not(:first-child)' ], [ 'border-left-width:0' ] );
			add( [ ' thead + tbody td', ':not(:has(> thead)) tbody tr:not(:first-child) td' ], [ 'border-top-width:0' ] );
			add( [ ' tbody' ], [ 'clip-path:inset(0 round ' + clip + ')' ] );
			add( [ ' thead' ], [ 'clip-path:inset(0 round ' + clip + ' ' + clip + ' 0 0)' ] );
			add( [ ' thead + tbody' ], [ 'clip-path:inset(0 round 0 0 ' + clip + ' ' + clip + ')' ] );
		}

		columns.forEach( function ( col, i ) {
			var n = i + 1;
			var onBoth = [];
			var onBody = [];
			var onHead = [];
			if ( col.width !== null && col.width !== undefined ) {
				onBoth.push( 'width:' + col.width );
			}
			keys.forEach( function ( key ) {
				var tbl = table.text[ key ] || null;
				var cl = col.text[ key ] || null;
				if ( cl === null ) {
					return;
				}
				var b = textForCell( key, tbl, cl, false );
				var h = hasHeader ? textForCell( key, tbl, cl, true ) : '';
				b = b !== textForCell( key, tbl, null, false ) ? b : '';
				h = h !== textForCell( key, tbl, null, true ) ? h : '';
				if ( b !== '' && b === h ) {
					onBoth.push( decl( key, b ) );
					return;
				}
				if ( b !== '' ) {
					onBody.push( decl( key, b ) );
				}
				if ( h !== '' ) {
					onHead.push( decl( key, h ) );
				}
			} );
			add( [ ' :is(th,td):nth-child(' + n + ')' ], onBoth );
			add( [ ' tbody td:nth-child(' + n + ')' ], onBody );
			add( [ ' thead th:nth-child(' + n + ')' ], onHead );
		} );
		return out;
	}

	/** Twin of bws_table_rules_css(): the stylesheet text under a scope class. */
	function rulesCss( scope, ruleList ) {
		return ruleList.map( function ( rule ) {
			return rule.sel.map( function ( suffix ) {
				return '.' + scope + suffix;
			} ).join( ',' ) + '{' + rule.decl + '}';
		} ).join( '' );
	}

	window.bwsTableStyle = {
		vocab: V,
		styleRead: styleRead,
		columnStyleRead: columnStyleRead,
		rules: rules,
		rulesCss: rulesCss,
		cssVar: cssVar,
		widthValue: widthValue,
		colorValue: colorValue,
		colorHasAlpha: colorHasAlpha,
		fontSizeValue: fontSizeValue,
		textValue: textValue,
		opacityValue: opacityValue,
		cssNumber: cssNumber,
		headArgsItems: headArgsItems,
		parseText: parseText,
		emitText: emitText,
		parseStripe: parseStripe,
		emitStripe: emitStripe,
		borderLengthValue: borderLengthValue,
		parseBorder: parseBorder,
		emitBorder: emitBorder,
		borderLines: borderLines,
		borderColor: borderColor,
		parseBorderRadius: parseBorderRadius,
		emitBorderRadius: emitBorderRadius,
		textForCell: textForCell
	};
}() );
