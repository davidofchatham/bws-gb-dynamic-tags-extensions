/**
 * Term-hop combined control for BWS dynamic tags.
 *
 * Renders a checkbox + taxonomy ComboboxControl as a single composite control.
 * Both UI affordances write to a single persisted option key (`srcTermIn`):
 *
 *   - Empty / absent       → disabled
 *   - Slug string          → enabled + taxonomy slug
 *
 * The checkbox state is local React state derived from the persisted slug on mount.
 * Checking the box without picking a slug does not persist (intentional — incomplete
 * config = disabled).
 *
 * Registered via `generateblocks.editor.tagSpecificControls`. Activates for any
 * option whose PHP `type` is `bws-term-hop`.
 *
 * @package BWS_Dynamic_Tags
 * @since   1.6.0
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.hooks || ! wp.element || ! wp.components || ! wp.data ) {
		return;
	}

	var el              = wp.element.createElement;
	var Fragment        = wp.element.Fragment;
	var useState        = wp.element.useState;
	var useEffect       = wp.element.useEffect;
	var CheckboxControl = wp.components.CheckboxControl;
	var ComboboxControl = wp.components.ComboboxControl;
	var useSelect       = wp.data.useSelect;
	var __              = wp.i18n ? wp.i18n.__ : function ( s ) { return s; };

	function TermHopControl( props ) {
		var ctx      = props.context;
		var state    = ctx.state || {};
		var setState = ctx.setState;
		var key      = props.optionKey;
		var slug     = state[ key ] || '';

		var initialChecked = '' !== slug;
		var checkedState   = useState( initialChecked );
		var checked        = checkedState[ 0 ];
		var setChecked     = checkedState[ 1 ];

		// Re-derive checkbox when persisted slug changes externally (e.g. modal reopen).
		useEffect( function () {
			if ( ( '' !== slug ) !== checked ) {
				setChecked( '' !== slug );
			}
		}, [ slug ] );

		// Strip any legacy `srcTerm` boolean carried in from older tag strings — the new
		// model encodes presence in `srcTermIn` only, so stale `srcTerm` would re-serialize.
		useEffect( function () {
			if ( 'srcTerm' in state ) {
				var upd = Object.assign( {}, state );
				upd.srcTerm = false;
				setState( upd );
			}
		}, [] );

		var taxonomies = useSelect( function ( select ) {
			var core = select( 'core' );
			if ( ! core || ! core.getTaxonomies ) { return []; }
			var list = core.getTaxonomies( { per_page: -1 } ) || [];
			return list.filter( function ( t ) {
				return t && t.visibility ? false !== t.visibility.public : true;
			} );
		}, [] );

		var options = ( taxonomies || [] ).map( function ( t ) {
			return { value: t.slug, label: t.name || t.slug };
		} );

		function onCheck( next ) {
			setChecked( next );
			if ( ! next ) {
				// Clear persisted slug when unchecked.
				var upd = {}; upd[ key ] = false;
				setState( Object.assign( {}, state, upd ) );
			}
			// Checking without slug: do not persist; combobox below will set slug when chosen.
		}

		function onPickSlug( val ) {
			var v = val || '';
			var upd = {}; upd[ key ] = '' !== v ? v : false;
			setState( Object.assign( {}, state, upd ) );
		}

		return el( Fragment, null,
			el( CheckboxControl, {
				label:    props.label,
				help:     props.help,
				checked:  checked,
				onChange: onCheck,
			} ),
			checked
				? el( ComboboxControl, {
					label:    props.pickLabel || __( 'Taxonomy', 'generateblocks' ),
					help:     props.pickHelp || '',
					value:    slug,
					options:  options,
					onChange: onPickSlug,
				} )
				: null
		);
	}

	function termHopFilter( element, allOptions, context ) {
		if ( ! element || ! allOptions || ! context ) { return element; }

		var cfg = allOptions[ element.key ];
		if ( ! cfg || 'bws-term-hop' !== cfg.type ) { return element; }

		return el( TermHopControl, {
			key:       element.key,
			optionKey: element.key,
			label:     cfg.label,
			help:      cfg.help,
			pickLabel: cfg.pickLabel,
			pickHelp:  cfg.pickHelp,
			context:   context,
		} );
	}

	wp.hooks.addFilter(
		'generateblocks.editor.tagSpecificControls',
		'bws/term-hop-control',
		termHopFilter
	);

} )();
