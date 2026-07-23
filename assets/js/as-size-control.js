/**
 * Image `as`+`size` composite control (`bws-as-size`) for BWS dynamic tags.
 *
 * Folds the image return-mode selector (`as`) and its size argument into ONE tag
 * option value: `as:<mode>[,<size>]` (as+size fold, FW-52 Phase 3). Replaces GB's
 * native `as` select AND GB's native `image-size` ComboboxControl (the plugin drops
 * 'image-size' support), because `as:url,full` is not a valid GB `select` entry — GB
 * would blank/corrupt it on modal reopen.
 *
 * FRAME (plan §Image `as`+`size` unification): `as` is a PARAMETERIZED return-type
 * selector. Most return types are nullary (`id`/`alt`/`title`/`caption` — no arg);
 * `url` is unary — it takes a `size` argument (size changes WHICH url). So size is a
 * parameter of the url return, carried in `as`'s own value:
 *
 *   {{image as:url,medium}}   // url(medium) — size arg present iff mode === 'url'
 *   {{image as:url,full}}     // default size STILL serialized (always-serialize, no strip)
 *   {{image as:alt}}          // nullary return — bare mode, NO size sub-slot
 *
 * MECHANICS:
 * - Always-serialized: the whole `as` token opts out of default-stripping (parallel
 *   to today's `as` opt-out). `url` always writes `url,<size>`; default `full` is
 *   never stripped. → deterministic value, NO interior `,,`, no strip logic.
 * - Size dropdown shows ONLY when mode === 'url' (hand-coded show_if — `show_if` tests
 *   whole option values and can't gate a comma sub-slot; the composite renders its own
 *   children so it gates trivially).
 * - Size STASH across mode-flip: flipping url→alt structurally drops the size arg
 *   (nullary has no arg slot — correct-by-construction). To avoid the editor papercut,
 *   the last-picked size lives in React state and restores on return to url. The WIRE
 *   stays model-pure — `as:alt` serializes nullary; the stash never touches
 *   serialization, so it can't drift the model. A saved `{{image as:alt}}` never had a
 *   size; nothing is lost on reopen.
 * - Size enum + pretty labels come from PHP (bws_get_image_size_options →
 *   window.bwsImageSizes), which respects the image_size_names_choose filter GB ignored.
 * - Whole-object setState (control-layer param authority): the composite owns the `as`
 *   token end to end; FW-52's order-normalizer re-sorts position independently
 *   (value/order split — this writes VALUE, guards nothing about key order).
 *
 * Registered via `generateblocks.editor.tagSpecificControls`; activates for any
 * option whose PHP `type` is `bws-as-size` (the image family's `as`).
 *
 * @package BWS_Dynamic_Tags
 * @since   1.16.0
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.hooks || ! wp.element || ! wp.components ) {
		return;
	}

	if ( ! wp.components.SelectControl ) {
		return;
	}

	var el            = wp.element.createElement;
	var Fragment      = wp.element.Fragment;
	var useState      = wp.element.useState;
	var SelectControl = wp.components.SelectControl;
	var __            = wp.i18n ? wp.i18n.__ : function ( s ) { return s; };

	var DEFAULT_SIZE = 'full';

	// Return-mode enum — mirrors the PHP `as` option (id/alt/title/caption are nullary,
	// url is unary and carries the size arg). Labels match the pre-fold GB select.
	var MODE_OPTIONS = [
		{ value: 'url',     label: __( 'URL', 'generateblocks' ) },
		{ value: 'id',      label: __( 'ID', 'generateblocks' ) },
		{ value: 'title',   label: __( 'Image Title', 'generateblocks' ) },
		{ value: 'alt',     label: __( 'Alt Text', 'generateblocks' ) },
		{ value: 'caption', label: __( 'Caption', 'generateblocks' ) },
	];

	/**
	 * Size options from PHP (bws_get_image_size_options, localized). Falls back to the
	 * standard WP sizes if the global is absent (unexpected — enqueue always localizes).
	 */
	function sizeOptions() {
		var list = window.bwsImageSizes;
		if ( list && list.length ) {
			return list;
		}
		return [
			{ value: 'thumbnail',    label: __( 'Thumbnail', 'generateblocks' ) },
			{ value: 'medium',       label: __( 'Medium', 'generateblocks' ) },
			{ value: 'medium_large', label: __( 'Medium Large', 'generateblocks' ) },
			{ value: 'large',        label: __( 'Large', 'generateblocks' ) },
			{ value: 'full',         label: __( 'Full Size', 'generateblocks' ) },
		];
	}

	/**
	 * Parse a stored `as` value into { mode, size }. Mirrors PHP bws_parse_as_option:
	 * split on the first comma; empty mode → 'url'; empty/absent size → 'full'.
	 */
	function parseAs( raw ) {
		var str  = ( raw === undefined || raw === null ) ? '' : String( raw );
		var i    = str.indexOf( ',' );
		var mode = ( i === -1 ) ? str : str.slice( 0, i );
		var size = ( i === -1 ) ? '' : str.slice( i + 1 );
		return {
			mode: mode || 'url',
			size: size || DEFAULT_SIZE,
		};
	}

	/**
	 * Serialize { mode, size } back to the folded `as` value. url → `url,<size>`
	 * (always, default size included); nullary modes → bare mode (no arg slot).
	 */
	function serializeAs( mode, size ) {
		if ( 'url' === mode ) {
			return 'url,' + ( size || DEFAULT_SIZE );
		}
		return mode;
	}

	function AsSizeControl( props ) {
		var ctx      = props.context;
		var state    = ctx.state || {};
		var setState = ctx.setState;
		var key      = props.optionKey;

		var parsed = parseAs( state[ key ] );
		var mode   = parsed.mode;
		var size   = parsed.size;

		// Stash the last non-default size chosen under url, so url→alt→url restores it.
		// Seeded from the persisted size (a saved url,medium reopens with medium stashed).
		var stashState = useState( size );
		var sizeStash    = stashState[ 0 ];
		var setSizeStash = stashState[ 1 ];

		function write( nextMode, nextSize ) {
			var upd = Object.assign( {}, state );
			upd[ key ] = serializeAs( nextMode, nextSize );
			setState( upd );
		}

		function onModeChange( nextMode ) {
			// On return to url, restore the stashed size (papercut fix); the wire only
			// ever carries a size under url, so nullary flips serialize bare.
			var nextSize = ( 'url' === nextMode ) ? sizeStash : size;
			write( nextMode, nextSize );
		}

		function onSizeChange( nextSize ) {
			setSizeStash( nextSize );
			write( 'url', nextSize );
		}

		var children = [
			el( SelectControl, {
				key:      'mode',
				label:    props.label || __( 'Return type:', 'generateblocks' ),
				value:    mode,
				options:  MODE_OPTIONS,
				onChange: onModeChange,
				__nextHasNoMarginBottom: true,
			} ),
		];

		// Size dropdown only for the unary `url` return — nullary modes take no arg.
		if ( 'url' === mode ) {
			children.push(
				el( SelectControl, {
					key:      'size',
					label:    __( 'Image Size', 'generateblocks' ),
					value:    size,
					options:  sizeOptions(),
					onChange: onSizeChange,
					__nextHasNoMarginBottom: true,
				} )
			);
		}

		return el( Fragment, null, children );
	}

	function asSizeFilter( element, allOptions, context ) {
		// Compose: a prior filter (conditional-options show_if) hiding this control wins.
		if ( ! element ) { return element; }
		if ( ! allOptions || ! context ) { return element; }

		var cfg = allOptions[ element.key ];
		if ( ! cfg || 'bws-as-size' !== cfg.type ) { return element; }

		return el( AsSizeControl, {
			key:       element.key,
			optionKey: element.key,
			label:     cfg.label,
			context:   context,
		} );
	}

	wp.hooks.addFilter(
		'generateblocks.editor.tagSpecificControls',
		'bws/as-size-control',
		asSizeFilter
	);

} )();
