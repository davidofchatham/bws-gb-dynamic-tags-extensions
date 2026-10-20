/**
 * `{{table}}` Live Formatting Preview (`bws-table-style-preview`): a display-only lead member of
 * the Styling option group, a thumbnail of the table the tag string describes.
 *
 * It decides nothing about styling. The rules come from `bwsTableStyle.rules()`, the JS twin of
 * the PHP rule builder that renders the page, held to it by tools/test/table-style-twin-test.php,
 * so the thumbnail cannot disagree with the canvas or the front end. The only changes made to a
 * built rule are to fit a 800px-wide table into a small box: lengths in `width` are rescaled
 * (see scaleWidth) and `font-size` is left out, because the thumbnail does not scale type.
 *
 * The option (`stylePreview`) has no value and is never serialized. The Sticky switch is a UI
 * preference, kept in localStorage (try/catch: storage can be absent or throw) and never in the
 * tag string.
 *
 * Sticky: the option-group wrapper is a flat sibling in the modal column, so the wrapper itself
 * is what sticks (`:has()` on its child), not an element inside a composite. Compact when
 * pinned (two body rows) while the wrapper keeps its full flow height through `margin-bottom`,
 * so the controls below do not jump.
 *
 * @package BWS_Dynamic_Tags
 * @since   Unreleased
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.hooks || ! wp.element || ! window.bwsTableStyle || ! window.bwsSlotFold || ! window.bwsTableStyleUI ) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useRef = wp.element.useRef;
	var useEffect = wp.element.useEffect;
	var __ = wp.i18n ? wp.i18n.__ : function ( s ) { return s; };

	var T = window.bwsTableStyle;
	var fold = window.bwsSlotFold;

	var SCOPE = 'bws-tp-table';
	var STORE_KEY = 'bwsTablePreviewSticky';
	var DEFAULT_COLUMNS = 3;
	// The widths of the bars standing in for text, so auto layout gives unequal columns.
	var BARS = { head: [ 34, 60, 44 ], body: [ [ 26, 84, 46 ], [ 30, 70, 38 ], [ 22, 96, 52 ], [ 28, 64, 42 ] ] };
	// Table and column lengths are rescaled against an assumed content area and viewport.
	var CONTENT_PX = 800;
	var PX_PER = { px: 1, em: 16, rem: 16, ch: 8, vw: 12 };

	var CSS =
		// Zero specificity (:where), so a built rule under the scope class wins on any tie. The theme
		// borders are GeneratePress 3.6.1's own (main.min.css): a separate-model table that draws its
		// top and left edges, cells their right and bottom, so a `border` key shows against what it replaces.
		':where(.bws-tp-table){width:100%;margin:0 auto;border-collapse:separate;border-spacing:0;border:1px solid rgba(0,0,0,.1);border-width:1px 0 0 1px;table-layout:auto;color:#3a3a3a}' +
		':where(.bws-tp-table) :where(th,td){text-align:left;height:14px;padding:3px 6px;border:1px solid rgba(0,0,0,.1);border-width:0 1px 1px 0;overflow:hidden}' +
		'.bws-tp-bar{display:inline-block;vertical-align:middle;height:4px;max-width:100%;border-radius:2px;background:currentColor;opacity:.55}' +
		'.bws-tp-table th .bws-tp-bar{height:5px;opacity:.85}' +
		'.bws-tp-mini{display:inline-block;max-width:100%;font-size:6px;line-height:1;font-weight:inherit;color:inherit;white-space:nowrap;overflow:hidden;vertical-align:middle}' +
		'.bws-tp-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:4px}' +
		'.bws-tp-cap{font-size:11px;font-weight:600;text-transform:uppercase;line-height:1.4;color:#50575e}' +
		'.bws-tp-pin{display:flex;align-items:center;font-size:12px;line-height:16px;color:#50575e;cursor:pointer;white-space:nowrap}' +
		'.bws-tp-sw{position:relative;display:inline-flex;flex:none;margin-left:6px}' +
		'.bws-tp-sw input{position:absolute;top:0;left:0;width:100%;height:100%;margin:0;opacity:0;cursor:pointer}' +
		'.bws-tp-track{box-sizing:border-box;display:flex;align-items:center;width:28px;height:14px;padding:0 2px;border:1px solid #1e1e1e;border-radius:7px;background:#fff;transition:background .15s,border-color .15s}' +
		'.bws-tp-thumb{flex:none;width:8px;height:8px;border-radius:50%;background:#1e1e1e;transition:transform .15s,background .15s}' +
		'.bws-tp-sw input:checked+.bws-tp-track{background:#3858e9;border-color:#3858e9}' +
		'.bws-tp-sw input:checked+.bws-tp-track .bws-tp-thumb{background:#fff;transform:translateX(14px)}' +
		'.bws-tp-sw input:focus-visible+.bws-tp-track{outline:2px solid #3858e9;outline-offset:2px}' +
		// White, not the group's grey: the page background is not detectable and white shows fills truest.
		'.bws-tp-box{width:100%;box-sizing:border-box;background:#fff;overflow:hidden;-webkit-mask-image:linear-gradient(to bottom,#000 calc(100% - 20px),transparent);mask-image:linear-gradient(to bottom,#000 calc(100% - 20px),transparent)}' +
		// The right edge fades too, only when the table runs past the box.
		'.bws-tp-box.is-cut{-webkit-mask-image:linear-gradient(to bottom,#000 calc(100% - 20px),transparent),linear-gradient(to right,#000 calc(100% - 56px),transparent);mask-image:linear-gradient(to bottom,#000 calc(100% - 20px),transparent),linear-gradient(to right,#000 calc(100% - 56px),transparent);-webkit-mask-composite:source-in;mask-composite:intersect}' +
		// Sticky. The wrapper is the option-group member; top:-4px cancels the modal content's 4px top
		// padding (top:0 pins 4px low). Opaque: the group's own tint is translucent. z-index 3 sits above
		// the controls' own (the slider thumb, the Link button, a focused icon button) and below a focused
		// combobox (5), so an open suggestion list paints over the preview.
		'.bws-optgroup:has(>.bws-tp.is-sticky){position:sticky;top:-4px;z-index:3;background:#fafafa}' +
		'.bws-optgroup:has(>.bws-tp.is-stuck){box-shadow:0 1px 0 #c3c4c7,0 6px 8px -4px rgba(0,0,0,.25)}' +
		'.bws-tp.is-stuck tbody tr:nth-child(n+3){display:none}';

	if ( typeof document !== 'undefined' && ! document.getElementById( 'bws-table-preview-css' ) ) {
		var styleEl = document.createElement( 'style' );
		styleEl.id = 'bws-table-preview-css';
		styleEl.textContent = CSS;
		document.head.appendChild( styleEl );
	}

	// ── Model (pure) ────────────────────────────────────────────────────────────────

	/**
	 * A length in the preview's own terms. A table width is a share of the box (20 to 100%); a
	 * column width a share of the table. A `%` stays one. `auto` is no column width. A `var()`
	 * cannot be resolved against a nominal viewport, so it is left out.
	 */
	function scaleWidth( value, isTable ) {
		if ( value === 'auto' ) {
			return isTable ? 'auto' : null;
		}
		var m = /^(\d*\.?\d+)(px|em|rem|%|ch|vw)$/.exec( value );
		if ( ! m ) {
			return null;
		}
		if ( m[ 2 ] === '%' ) {
			return value;
		}
		var share = Number( m[ 1 ] ) * PX_PER[ m[ 2 ] ] / CONTENT_PX * 100;
		return T.cssNumber( Math.max( isTable ? 20 : 2, Math.min( 100, share ) ) ) + '%';
	}

	/** One built rule, fitted to the thumbnail; null when nothing is left of it. */
	function fit( rule ) {
		var isTable = rule.sel.length === 1 && rule.sel[ 0 ] === '';
		var decls = [];
		rule.decl.split( ';' ).forEach( function ( d ) {
			if ( d.indexOf( 'font-size:' ) === 0 ) {
				return;
			}
			if ( d.indexOf( 'width:' ) === 0 ) {
				var w = scaleWidth( d.slice( 6 ), isTable );
				if ( w !== null ) {
					decls.push( 'width:' + w );
				}
				return;
			}
			decls.push( d );
		} );
		return decls.length ? { sel: rule.sel, decl: decls.join( ';' ) } : null;
	}

	/**
	 * The columns the tag defines, as the renderer counts them: a slot with a header or a read.
	 * (The renderer also drops a column whose chain is unfinished; that needs the PHP seam, so a
	 * half-built column can show here and not on the page.)
	 */
	function columnsOf( state, max ) {
		var cols = [];
		for ( var n = 1; n <= max; n++ ) {
			var raw = state[ fold.slotKey( n ) ];
			if ( ! raw || ! String( raw ).trim() ) {
				continue;
			}
			var slot = fold.parseSlot( String( raw ), 'table' );
			var header = slot.error ? '' : String( slot.header || '' ).trim();
			if ( slot.error || ( header === '' && ! slot.read ) ) {
				continue;
			}
			cols.push( { header: header, style: T.columnStyleRead( slot.opts ) } );
		}
		return cols;
	}

	/** Everything the thumbnail draws: columns, header presence and the scoped stylesheet. */
	function model( state, max, stripeDefaults ) {
		var cols = columnsOf( state, max );
		var sample = cols.length === 0;
		if ( sample ) {
			// Nothing to draw from yet: sample columns, so the formatting still shows.
			for ( var i = 0; i < DEFAULT_COLUMNS; i++ ) {
				cols.push( { header: '', style: T.columnStyleRead( {} ) } );
			}
		}
		var hasHeader = sample || cols.some( function ( c ) {
			return c.header !== '';
		} );
		var built = T.rules( T.styleRead( state ), cols.map( function ( c ) {
			return c.style;
		} ), hasHeader, stripeDefaults );
		return {
			columns: cols,
			hasHeader: hasHeader,
			css: T.rulesCss( SCOPE, built.map( fit ).filter( Boolean ) )
		};
	}

	// ── Sticky preference ───────────────────────────────────────────────────────────

	function readSticky() {
		try {
			return window.localStorage.getItem( STORE_KEY ) !== '0';
		} catch ( e ) {
			return true;
		}
	}

	function writeSticky( on ) {
		try {
			window.localStorage.setItem( STORE_KEY, on ? '1' : '0' );
		} catch ( e ) {
			// Storage can be absent or full; the preference then lasts until the modal closes.
		}
	}

	/** The nearest ancestor that scrolls vertically. */
	function scrollParent( node ) {
		for ( var n = node.parentNode; n && n.nodeType === 1; n = n.parentNode ) {
			var oy = window.getComputedStyle( n ).overflowY;
			if ( oy === 'auto' || oy === 'scroll' ) {
				return n;
			}
		}
		return null;
	}

	// ── Component ───────────────────────────────────────────────────────────────────

	function Table( p ) {
		var m = p.model;
		var bar = function ( px ) {
			return el( 'i', { className: 'bws-tp-bar', style: { width: px + 'px' } } );
		};
		var head = m.hasHeader ? el( 'thead', { key: 'h' }, el( 'tr', null, m.columns.map( function ( c, i ) {
			return el( 'th', { key: i }, c.header ? el( 'span', { className: 'bws-tp-mini' }, c.header ) : bar( BARS.head[ i % 3 ] ) );
		} ) ) ) : null;
		var body = el( 'tbody', { key: 'b' }, BARS.body.map( function ( row, r ) {
			return el( 'tr', { key: r }, m.columns.map( function ( c, i ) {
				return el( 'td', { key: i }, bar( row[ i % 3 ] ) );
			} ) );
		} ) );
		return el( 'table', { className: SCOPE }, [ head, body ] );
	}

	function Preview( p ) {
		var stickyState = useState( readSticky );
		var sticky = stickyState[ 0 ];
		var rootRef = useRef( null );
		var boxRef = useRef( null );
		var m = model( p.state, p.max, window.bwsTableStyleUI.defaults() );

		// Pinned state is measured, not assumed: the wrapper is pinned when its top has met the
		// scroller's. Every pass drops the compact class first, so the full height is never stale.
		useEffect( function () {
			var root = rootRef.current;
			var box = boxRef.current;
			if ( box ) {
				var t = box.querySelector( 'table' );
				box.classList.toggle( 'is-cut', !! t && t.scrollWidth > box.clientWidth + 1 );
			}
			var member = root && root.parentNode;
			var scroller = member && scrollParent( member );
			if ( ! scroller ) {
				return undefined;
			}
			function update() {
				root.classList.remove( 'is-stuck' );
				member.style.marginBottom = '';
				var full = member.offsetHeight;
				var pinned = sticky && scroller.scrollTop > 0 && member.getBoundingClientRect().top - scroller.getBoundingClientRect().top < 1;
				if ( pinned ) {
					root.classList.add( 'is-stuck' );
					member.style.marginBottom = ( full - member.offsetHeight ) + 'px';
				}
				// A focused field scrolls out from under the pinned preview, not behind it.
				scroller.style.scrollPaddingTop = sticky ? ( member.offsetHeight + 15 ) + 'px' : '';
			}
			update();
			scroller.addEventListener( 'scroll', update, { passive: true } );
			window.addEventListener( 'resize', update );
			return function () {
				scroller.removeEventListener( 'scroll', update );
				window.removeEventListener( 'resize', update );
				scroller.style.scrollPaddingTop = '';
				member.style.marginBottom = '';
			};
		} );

		return el( 'div', { className: 'bws-tp' + ( sticky ? ' is-sticky' : '' ), ref: rootRef }, [
			el( 'div', { key: 'head', className: 'bws-tp-head' }, [
				el( 'span', { key: 'cap', className: 'bws-tp-cap' }, p.label ),
				// Visible label first; the accessible name contains it (WCAG 2.5.3).
				el( 'label', { key: 'pin', className: 'bws-tp-pin' }, [
					__( 'Sticky', 'generateblocks' ),
					el( 'span', { key: 'sw', className: 'bws-tp-sw' }, [
						el( 'input', {
							key: 'in',
							type: 'checkbox',
							role: 'switch',
							'aria-label': __( 'Toggle sticky preview', 'generateblocks' ),
							checked: sticky,
							onChange: function ( e ) {
								writeSticky( e.target.checked );
								stickyState[ 1 ]( e.target.checked );
							}
						} ),
						el( 'span', { key: 'tr', className: 'bws-tp-track', 'aria-hidden': true }, el( 'span', { className: 'bws-tp-thumb' } ) )
					] )
				] )
			] ),
			el( 'div', { key: 'box', className: 'bws-tp-box', role: 'img', 'aria-label': __( 'Preview of the table formatting', 'generateblocks' ), ref: boxRef }, [
				el( 'style', { key: 'css' }, m.css ),
				el( Table, { key: 'table', model: m } )
			] )
		] );
	}

	wp.hooks.addFilter(
		'generateblocks.editor.tagSpecificControls',
		'bws/table-style-preview',
		function ( element, allOptions, context ) {
			if ( ! element || ! allOptions || ! context ) {
				return element;
			}
			var cfg = allOptions[ element.key ];
			if ( ! cfg || cfg.type !== 'bws-table-style-preview' ) {
				return element;
			}
			var first = allOptions[ fold.slotKey( 1 ) ];
			return el( Preview, {
				key: element.key,
				label: cfg.label,
				state: context.state || {},
				max: first && first.fold && first.fold.max ? first.fold.max : 10
			} );
		}
	);

	window.bwsTablePreview = { model: model, scaleWidth: scaleWidth, columnsOf: columnsOf, Preview: Preview };
}() );
