<?php
/**
 * {{table}} — structured-output tag: build an HTML table from a source chain.
 *
 * A composing tag over the traversal fold (traversal-pipeline.php). The tag-level
 * source chain's tail IS the row set: one row per resolved source, whatever its kind
 * (the chain's own `limit(N)` is the cap). Each column is a FOLDED SLOT (`A:`, `B:`, …)
 * whose chain hangs off the ROW: empty, or rooted at `inherit`, it starts there; any
 * other root is the escape it names. Output is an atomic <table> string in a
 * text-as-div host (the {{content}} precedent) — NOT a scalar; it must not be inserted
 * into a <p>/<span> (the tag modal description warns of this).
 *
 * The pure assembly is in includes/helpers/table-helpers.php.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Max column slots offered by {{table}}. Distinct from BWS_JOIN_MAX_SLOTS (whose
 * NAME does not travel — grill decision); a table's column count is a separate
 * global constraint, tunable here.
 *
 * THE DRIVER IS REGISTRATION WEIGHT, NOT ADDRESSING. GB options are static, so a
 * container registers its slots to a ceiling and renders one iff it holds a value;
 * the ceiling is how many option definitions ride in the editor payload, one per
 * slot. The slot ORDINAL has no ceiling to inherit — bws_slot_ordinal() encodes
 * spreadsheet-style (27 → `AA`) and every consumer routes through it — so no value
 * here is forced by the wire, and raising it costs one option definition per column.
 *
 * The value matches BWS_JOIN_MAX_SLOTS by independent judgment, not derivation:
 * nothing about a table argues for fewer columns than join allows slots, one ceiling
 * across the plugin is one number for an author to meet, and a table-specific outlier
 * would be the straggler if FW-45 ever lifts ceilings globally.
 *
 * @since 1.17.0
 */
if ( ! defined( 'BWS_TABLE_MAX_COLS' ) ) {
	define( 'BWS_TABLE_MAX_COLS', 10 );
}

/**
 * Register the {{table}} tag.
 *
 * @since 1.17.0
 */
function bws_register_table_tag(): void {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}

	static $registered = false;
	if ( $registered ) {
		return;
	}
	$registered = true;

	bws_gb_register_tag( array(
		'title'       => __( 'Table', 'generateblocks' ),
		'tag'         => 'table',
		'type'        => 'cross-source',
		'supports'    => array(),
		// Help text in the tag selector / modal. text-as-div host: the output is a
		// full <table> block, so the containing element must be a block container.
		'description' => __( 'Builds a table from a repeater field. Outputs a full <table>; do not place inside a <p> or <span> — use a Div or other block element.', 'generateblocks' ),
		'options'     => bws_get_table_options(),
		'return'      => 'bws_table_callback',
		// EXPLICITLY FALSE, never defaulted. {{table}} registers no modifier-template
		// record, so this is stated here, beside the tag it describes (GB stores unknown
		// registration keys untouched). Silence would be wrong because {{table}} is
		// exactly the template a list-shaped heuristic misclassifies: it has no list
		// mode (it repeats rows, joins nothing) yet its `rows` step limit is the most
		// load-bearing limit in the plugin — a first-usable reading of {{table}} would
		// collapse the table to one row. The capability's axis is owned at the
		// modifier-template descriptor docblock (class-tag-template-registry.php).
		'takes_first_usable' => false,
	) );
}

/**
 * Build the {{table}} option definitions.
 *
 * Tag-level: the source chain whose tail is the row set, and `caption`. Then the
 * columns, as folded slots through the same builder `{{join}}` uses, reading through
 * the shared text leaf (keyed, analog and fixed reads alike).
 *
 * `tag_level` names EVERY legacy flat axis: {{table}} shipped folded, so no column was
 * ever stored as `{N}-key` and friends, and the bare `src` a slot-1 flat read would
 * consume is the TAG's source. With no per-slot flat axes the editor neither recovers
 * a column from those keys nor deletes them on commit.
 *
 * @since 1.17.0
 * @since Unreleased Columns are folded slots; the flat `{N}-*` keys and tag-level `key` are gone.
 * @return array Option definitions keyed by option name.
 */
function bws_get_table_options(): array {
	$text_field = bws_get_text_field_options();

	$tag_level = array_merge(
		// The tag's source is authored as a CHAIN, with the same control and root menu as a
		// base tag's: the chain's tail is the row set, and any chain is valid wire.
		bws_build_src_chain_option(),
		bws_base_traversal_options(),
		array(
			// Accessible caption (W3C data-table pattern). Labels the table for
			// screen readers and, when set, names the responsive scroll region via
			// aria-labelledby. Optional — omit for no <caption>.
			'caption' => array(
				'type'  => 'text',
				'label' => __( 'Caption', 'generateblocks' ),
				'help'  => __( 'Optional table caption. Labels the table for assistive technology.', 'generateblocks' ),
			),
		)
	);

	$columns = bws_build_fold_slot_options(
		array(
			'container'  => 'table',
			'max'        => BWS_TABLE_MAX_COLS,
			'base_read'  => $text_field['use'],
			'base_key'   => $text_field['key'],
			'base_fixed' => $text_field['fixed'] ?? array(),
			'allow_site' => true,
			// A column's source is a base tag's source ([I16]): same offer as {{join}}.
			'steps'      => array( 'refs', 'terms', 'rows' ),
			'tag_level'  => BWS_FOLD_FLAT_AXES,
			'noun'       => __( 'column', 'generateblocks' ),
		)
	);

	return bws_prepare_registration_options( array_merge( $tag_level, bws_get_table_style_options(), $columns ) );
}

/**
 * The {{table}} presentation options: `layout`, `width`, the six text keys, `stripe`, `border`
 * and `borderRadius`.
 *
 * Three composite controls own them (assets/js/table-style-control.js): Sizing and Width on
 * `layout`, Typography on `color`, Stripes on `stripe`, Borders on `border` (with `borderRadius`).
 * Each composite commits its keys in one `setState`, so the other keys register only to be known: their `bws-table-style-member`
 * type tells the filter to render nothing for them. Registered CONTIGUOUSLY and all in the
 * `style` visual group (bws_option_visual_groups()), so the composites read as one box.
 * Without the script every key is a plain GB text control, which is still usable wire.
 *
 * The values are validated where they are READ (bws_table_style_read()), never here: this
 * file only declares the keys.
 *
 * @since Unreleased
 * @return array Option definitions keyed by option name.
 */
function bws_get_table_style_options(): array {
	$member = static function ( string $label ): array {
		return array(
			'type'  => 'bws-table-style-member',
			'label' => $label,
		);
	};
	return array(
		// Display-only: the Live Formatting Preview (assets/js/table-style-preview.js). It has
		// no value and is never serialized, and it leads the style box.
		'stylePreview'  => array(
			'type'  => 'bws-table-style-preview',
			'label' => __( 'Live Formatting Preview', 'generateblocks' ),
		),
		'layout'        => array(
			'type'  => 'bws-table-sizing',
			'label' => __( 'Column Sizing', 'generateblocks' ),
		),
		'width'         => $member( __( 'Table Width', 'generateblocks' ) ),
		'color'         => array(
			'type'  => 'bws-table-typography',
			'label' => __( 'Text Color', 'generateblocks' ),
		),
		'fontSize'      => $member( __( 'Font Size', 'generateblocks' ) ),
		'fontWeight'    => $member( __( 'Font Weight', 'generateblocks' ) ),
		'fontStyle'     => $member( __( 'Font Style', 'generateblocks' ) ),
		'textAlign'     => $member( __( 'Text Alignment', 'generateblocks' ) ),
		'textTransform' => $member( __( 'Text Transform', 'generateblocks' ) ),
		'stripe'        => array(
			'type'  => 'bws-table-stripes',
			'label' => __( 'Stripes', 'generateblocks' ),
		),
		'border'        => array(
			'type'  => 'bws-table-borders',
			'label' => __( 'Borders', 'generateblocks' ),
		),
		'borderRadius'  => $member( __( 'Radius', 'generateblocks' ) ),
	);
}

/**
 * The configured columns, in slot order — each one's header and resolved slot options.
 *
 * Folded keys only: {{table}} has no flat era, and bws_fold_slot_struct()'s flat
 * recovery would read the TAG-level `src` as column A's. A column the seam skips
 * (no read, an unfinished step) is still a column when it carries a header: its `opts` are
 * null and every cell down it is empty, so a header-only column (a spacer, an annotation) is
 * valid and the header row keeps the body's cell count. Wire that does not parse is left out.
 * A blank header (whitespace only) is no header.
 *
 * A skipped column never feeds the carry, whatever its chain says, as in {{join}}: a read-less
 * `A:header(X);src(refs,office)` renders empty cells, and a hand-written `B:src(same)` after it
 * carries the row, not the office. The editor seeds a new column with a pending keyed read, so
 * only the read menu's "Select…" row or hand-written wire reaches this.
 *
 * The carry is seeded with the PARENT: an empty column chain is the row, so a
 * hand-written `src(same)` on column A carries that rather than skipping.
 *
 * @since Unreleased
 * @param array $options Tag options.
 * @param array $dropped Out: `{ n, skip }` per column dropped for a skip with no header, so the
 *                       editor skeleton can still warn about it.
 * @return array[] `{ header, opts, style, n, skip }` per column; `opts` is bws_fold_slot_chain_options()'s,
 *                 or null for a header-only column; `style` is bws_table_column_style_read() of the
 *                 column's width and text tokens (a token whose brackets are unbalanced is absent);
 *                 `n` the slot ordinal and `skip` the seam's skip reason, for the editor skeleton.
 */
function bws_table_columns( array $options, array &$dropped = array() ): array {
	$carry   = bws_fold_empty_carry( bws_use_stripped_default( 'text' ), true );
	$columns = array();
	for ( $n = 1; $n <= BWS_TABLE_MAX_COLS; $n++ ) {
		if ( '' === trim( (string) ( $options[ bws_slot_ordinal( $n ) ] ?? '' ) ) ) {
			continue;
		}
		$slot = bws_fold_slot_struct( $n, $options, 'table' );
		if ( null === $slot ) {
			continue;
		}
		$header = trim( (string) ( $slot['header'] ?? '' ) );
		$skip   = '';
		$opts   = bws_fold_slot_chain_options( $slot, $carry, true, $skip );
		if ( null === $opts && '' === $header ) {
			$dropped[] = array( 'n' => $n, 'skip' => $skip );
			continue;
		}
		$columns[] = array(
			'header' => $header,
			'opts'   => $opts,
			'style'  => bws_table_column_style_read( $slot['opts'] ),
			'n'      => $n,
			'skip'   => $skip,
		);
	}
	return $columns;
}

/**
 * Where a column's chain starts: the row (null), a source its own root names, or
 * false when that root is refused.
 *
 * Asked once per column, not per row — only an `inherit`/empty root depends on the row.
 *
 * @since Unreleased
 * @param array|null $opts    The column's resolved slot options; null (a header-only column) starts nowhere.
 * @param string $explicit_id The editor-injected post id ('' on the front end; I11).
 * @param object $instance    GB tag instance.
 * @return array|false|null False for a refused root or a header-only column.
 */
function bws_table_column_start( ?array $opts, string $explicit_id, $instance ) {
	if ( null === $opts ) {
		return false;
	}
	$root = bws_fold_src_root_token( $opts );
	if ( '' === $root || BWS_FOLD_INHERIT_ROOT === $root ) {
		return null;
	}
	$res = bws_base_src_resolution( $opts );
	if ( '' !== $explicit_id && 'site' !== $res['kind'] ) {
		$opts['id'] = $explicit_id;
	}
	$base = bws_base_resolve_source_for_callback( $opts, $instance );
	return bws_base_read_refused( $res, $base, bws_table_serves() ) ? false : $base;
}

/**
 * A column's ANALOG read (`use:title`) off one already-resolved source.
 *
 * The third kind switch beside bws_read_resolved_source_value() (keyed) and
 * bws_source_link_identity() (identity): which analog does this kind expose. It calls the
 * readers the text tag's own arms use, by id, rather than bws_base_ambient_analog(), which
 * answers only an ambient chain (its PHPDoc owns the rule). Table-only: promote it the day a
 * second reader dispatches from a resolved source (FW-120).
 *
 * A `meta_row` has no analog (a row is not an entity) and an unlisted kind has none yet:
 * both are '' and never fall through to the ambient entity. `query_context` (a 404, search or
 * archive page as the row) reads its canonical heading.
 *
 * @since Unreleased
 * @param array  $source   One resolved source.
 * @param array  $opts     The column's slot options.
 * @param object $instance GB instance.
 * @return string Analog value, '' on a miss or a kind with none.
 */
function bws_table_read_analog( array $source, array $opts, $instance ): string {
	// Entity-less, so it has no link identity to dispatch on: its reader takes the source.
	if ( 'query_context' === ( $source['kind'] ?? '' ) ) {
		return bws_base_query_context_analog_read( 'text', $source, $opts, $instance );
	}
	$identity = bws_source_link_identity( $source );
	if ( null === $identity ) {
		return '';
	}
	switch ( $identity['kind'] ) {
		case 'post':
			return (string) bws_try_text_post_dispatch( $identity['id'], $opts, $instance );
		case 'term':
			return bws_base_term_analog_read( 'text', $identity['id'], $opts, $instance );
		case 'user':
			return bws_base_user_analog_read( 'text', $identity['id'], $opts, $instance );
		case 'site':
			return bws_site_resolve_value( 'text', $opts, $instance );
	}
	return '';
}

/**
 * Read one column's cell off one row.
 *
 * Runs the column's steps from its start (the row, unless its root names another
 * source), then reads every source it lands on, joined `, ` as a {{join}} slot does.
 * The FIXED read comes first and reads nothing, so it applies on every row kind,
 * `meta_row` included; bws_fixed_text_read() owns what an unresolved source shows.
 * A keyed read goes through the L2 read seam, an analog read through
 * bws_table_read_analog().
 *
 * @since 1.17.0
 * @since Unreleased A column is a folded slot; keyed, analog and fixed reads.
 * @param array  $row_source One resolved row source.
 * @param array  $col        `{ opts, start, steps }`; null `opts` is a header-only column.
 * @param object $instance   GB instance.
 * @return string Cell value (may be '').
 */
function bws_table_read_cell( array $row_source, array $col, $instance ): string {
	if ( null === $col['opts'] || false === $col['start'] ) {
		return '';
	}
	$opts    = $col['opts'];
	$sources = bws_run_traversal( array( $col['start'] ?? $row_source ), $col['steps'] );

	$values = array();
	foreach ( $sources as $source ) {
		if ( 'fixed' === $opts['use'] ) {
			$value = bws_fixed_text_read( $opts, $instance );
		} elseif ( 'key' === $opts['use'] ) {
			$value = bws_read_resolved_source( $source, (string) $opts['key'], $instance );
		} else {
			$value = bws_table_read_analog( $source, $opts, $instance );
		}
		if ( '' !== $value ) {
			$values[] = $value;
		}
	}
	return implode( ', ', $values );
}

/**
 * The 6-line responsive-table CSS (Roselli recipe).
 *
 * The scroll region is inert without this: the wrapper attributes give keyboard +
 * name/role semantics (WCAG 2.1.1 / 4.1.2), but `overflow:auto` (WCAG 1.4.10
 * Reflow) and the focus outline (WCAG 2.4.7 / 1.4.11) are CSS. Attribute-scoped
 * selector (`[role="region"][aria-labelledby][tabindex]`) — no class hook; the
 * rule applies only to a wrapper carrying the full accessible-region markup, so
 * the CSS itself enforces the a11y. Queued once per request via the shared
 * bws_queue_inline_css footer-printer (the {{content}} inline-CSS precedent);
 * plugin ships no stylesheet asset.
 *
 * @since 1.17.0
 */
const BWS_TABLE_INLINE_CSS = '[role="region"][aria-labelledby][tabindex]{overflow:auto}'
	. '[role="region"][aria-labelledby][tabindex]:focus{outline:.1em solid rgba(0,0,0,.35)}';

/**
 * The wire kinds {{table}} serves as rows: every kind the wire vocabulary can name.
 *
 * DERIVED, never hand-listed — a row may be any resolved kind, so a kind that joins the
 * vocabulary is servable the day it joins. A literal list would start refusing it.
 *
 * @since Unreleased
 * @return string[] Root kinds and step-produced kinds off bws_fold_wire_vocabulary().
 */
function bws_table_serves(): array {
	$vocab = bws_fold_wire_vocabulary();
	$kinds = array_values( (array) ( $vocab['roots'] ?? array() ) );
	foreach ( (array) ( $vocab['steps'] ?? array() ) as $step ) {
		if ( isset( $step['produces'] ) ) {
			$kinds[] = $step['produces'];
		}
	}
	return array_values( array_unique( $kinds ) );
}

/**
 * Whether this render is the editor's tag preview (a REST request), where no `wp_footer` runs.
 *
 * @since Unreleased
 * @return bool
 */
function bws_table_in_editor_preview(): bool {
	return defined( 'REST_REQUEST' ) && REST_REQUEST;
}

/**
 * The stripe default pair: the saved settings where valid, else the built-in `currentColor` at 5%.
 *
 * @since Unreleased
 * @return array{color:string,opacity:float}
 */
function bws_table_stripe_defaults(): array {
	return class_exists( '\BWS\DynamicTags\Admin\SettingsPage' )
		? \BWS\DynamicTags\Admin\SettingsPage::get_table_stripe_defaults()
		: bws_table_stripe_defaults_resolve( '', '' );
}

/**
 * The <table> HTML for headers + a cell matrix, with its presentation: '' when there is nothing
 * to build.
 *
 * One rule builder serves the front end, the editor's real table and its skeleton; the scope
 * class is hashed off the built rules, so an unstyled table has none and identical tables share
 * one.
 *
 * @since Unreleased
 * @param array   $options     Tag options.
 * @param array[] $columns     bws_table_columns() records (`header`, `style`).
 * @param array[] $matrix      Row-major cell strings.
 * @param bool    $allow_empty Build a table with an empty body for no rows (editor only).
 * @return string
 */
function bws_table_html( array $options, array $columns, array $matrix, bool $allow_empty = false ): string {
	$headers = array_column( $columns, 'header' );

	// Accessible caption + a request-unique id for aria-labelledby (W3C responsive
	// data-table pattern). wp_unique_id is a deterministic per-request counter — no
	// randomness — so it is safe on the frontend and stable within a render.
	$caption    = trim( (string) ( $options['caption'] ?? '' ) );
	$caption_id = '' !== $caption && function_exists( 'wp_unique_id' )
		? wp_unique_id( 'bws-table-caption-' )
		: '';

	$rules = bws_table_rules(
		bws_table_style_read( $options ),
		array_column( $columns, 'style' ),
		bws_table_has_header( $headers ),
		bws_table_stripe_defaults()
	);
	$scope = bws_table_style_class( $rules );

	$html = bws_table_assemble( $headers, $matrix, $caption, $caption_id, $scope, $allow_empty );
	if ( '' === $html ) {
		return '';
	}

	// Everything below runs AFTER the empty-html guard, so a table that renders no rows leaves
	// no orphan CSS. A rendered captioned table carries the scroll-region wrapper: queue its CSS
	// (idempotent, shared footer-printer).
	if ( '' !== $caption_id && function_exists( 'bws_queue_inline_css' ) ) {
		bws_queue_inline_css( 'bws-table-inline-css', BWS_TABLE_INLINE_CSS );
	}
	if ( '' !== $scope ) {
		$css = bws_table_rules_css( $scope, $rules );
		if ( bws_table_in_editor_preview() ) {
			// The editor's replacement is not a page: nothing prints wp_footer, so the rules
			// travel with the table. A trusted user without `unfiltered_html` gets this
			// `<style>` stripped by GB's kses and sees an unstyled table; accepted.
			$html = '<style>' . $css . '</style>' . $html;
		} elseif ( function_exists( 'bws_queue_inline_css' ) ) {
			bws_queue_inline_css( $scope, $css );
		}
	}
	return $html;
}

/** Body rows in the editor skeleton. */
const BWS_TABLE_SKELETON_ROWS = 3;

/**
 * The editor's placeholder for a table that cannot render here: the real table's shape, each
 * cell holding its column's configuration, never invented data.
 *
 * EDITOR ONLY. On a published page an unusable table renders nothing; a skeleton there would
 * show visitors made-up content.
 *
 * Headers follow the real table's `<thead>` gate and the styling is the real table's rules, so
 * only the content is a stand-in. Two marks say so, because either alone misleads: the dashed
 * outline + accessible name (the marker does not say why) and cell text that reads as
 * configuration (that alone reads as data). The note line says why.
 *
 * @since Unreleased
 * @param array   $options Tag options.
 * @param array[] $columns bws_table_columns() records; empty when none is configured.
 * @param array[] $dropped bws_table_columns()' dropped columns, warned about but not shown.
 * @return string
 */
function bws_table_skeleton( array $options, array $columns, array $dropped = array() ): string {
	if ( empty( $columns ) ) {
		$label        = 'Placeholder: no table columns set';
		$tag_warnings = array( 'no columns set' );
		$columns      = array( array( 'header' => '', 'style' => array(), 'text' => 'No columns' ) );
	} else {
		$label          = 'Placeholder: table source not available in the editor';
		$inert          = array();
		bws_try_preview_source_part( (string) ( $options['src'] ?? '' ), true, $inert );
		$source_warning = bws_preview_inert_warning( $inert );
		$tag_warnings   = array( '' !== $source_warning ? $source_warning : 'source not available in the editor' );
	}

	$slot_warnings = array();
	foreach ( $dropped as $col ) {
		$summary = bws_preview_slot_summary( null, $col['skip'] );
		if ( null !== $summary ) {
			$slot_warnings[] = array( 'n' => $col['n'], 'detail' => $summary['warning'] );
		}
	}
	$cells = array();
	foreach ( $columns as $col ) {
		$summary = isset( $col['n'] ) ? bws_preview_slot_summary( $col['opts'], $col['skip'] ) : null;
		if ( null !== $summary && '' !== $summary['warning'] ) {
			$slot_warnings[] = array( 'n' => $col['n'], 'detail' => $summary['warning'] );
		}
		$cells[] = $col['text'] ?? ( $summary['text'] ?? '' );
	}

	$html = bws_table_html( $options, $columns, array_fill( 0, BWS_TABLE_SKELETON_ROWS, $cells ) );
	$note = '⚠ Table: ' . bws_slot_warnings_text( $slot_warnings, $tag_warnings );
	return '<div role="group" aria-label="' . esc_attr( $label ) . '" style="border:1px dashed currentColor;padding:.5em">'
		. '<p>' . esc_html( $note ) . '</p>'
		. $html
		. '</div>';
}

/**
 * Callback for the {{table}} tag.
 *
 * 1. Resolve the base source (ambient/explicit) the chain starts from.
 * 2. Run the whole compiled chain → the rows (one per resolved source, any kind).
 * 3. For each row, read every configured column → a cell matrix.
 * 4. Assemble the <table> string, wrapped in the W3C responsive scroll region
 *    (+ <caption> when set), in the text-as-div host.
 *
 * Front end: refused root, no rows or no columns → '' (nothing to build).
 * Editor preview (`bwsEditorPreview`): no rows → the real table with an empty body; refused
 * root or no columns → bws_table_skeleton().
 * The `id` from the editor is threaded into the base source by the factory
 * (composing-tag id threading, CONTEXT.md I11) so the editor preview resolves.
 *
 * @since 1.17.0
 * @since Unreleased The editor preview: the real table, or a skeleton.
 * @param array  $options  Tag options.
 * @param object $block    GB block (unused; signature parity).
 * @param object $instance GB tag instance.
 * @return string
 */
function bws_table_callback( $options, $block, $instance ): string {
	$options    = (array) $options;
	$is_preview = ! empty( $instance->context['bwsEditorPreview'] );
	$dropped    = array();
	$columns    = bws_table_columns( $options, $dropped );

	// A root this render cannot use produces no table, not an empty one.
	$base    = empty( $columns ) ? array() : bws_base_resolve_source_for_callback( $options, $instance );
	$refused = empty( $columns ) || bws_base_read_refused( bws_base_src_resolution( $options ), $base, bws_table_serves() );
	if ( $refused ) {
		return $is_preview ? bws_table_output( bws_table_skeleton( $options, $columns, $dropped ), $options, $instance ) : '';
	}

	// The rows ARE the chain's tail: whatever the whole compiled chain resolves to, one
	// row each. An empty chain is the current context, a one-row table.
	$row_sources = bws_run_traversal( array( $base ), bws_field_values_assemble_steps( $options ) );
	if ( empty( $row_sources ) && ! $is_preview ) {
		return '';
	}

	$explicit_id = (string) ( $options['id'] ?? '' );
	foreach ( $columns as $i => $col ) {
		$columns[ $i ]['start'] = bws_table_column_start( $col['opts'], $explicit_id, $instance );
		$columns[ $i ]['steps'] = bws_field_values_assemble_steps( $col['opts'] ?? array() );
	}

	$matrix = array();
	foreach ( $row_sources as $row_source ) {
		if ( ! is_array( $row_source ) ) {
			continue;
		}
		$cells = array();
		foreach ( $columns as $col ) {
			$cells[] = bws_table_read_cell( $row_source, $col, $instance );
		}
		$matrix[] = $cells;
	}

	$html = bws_table_html( $options, $columns, $matrix, $is_preview );
	return '' === $html ? '' : bws_table_output( $html, $options, $instance );
}

/**
 * Hand finished table HTML to the text-as-div host.
 *
 * Through the GB output boundary so link/attr handling stays consistent, but the value is
 * already fully-formed HTML. The class_exists guard stays: it is what keeps a GB-less install
 * returning the bare markup rather than fatalling.
 *
 * @since Unreleased
 * @param string $html     Table HTML.
 * @param array  $options  Tag options.
 * @param object $instance GB tag instance.
 * @return string
 */
function bws_table_output( string $html, array $options, $instance ): string {
	return class_exists( 'GenerateBlocks_Dynamic_Tag_Callbacks' )
		? bws_gb_tag_output( $html, $options, $instance )
		: $html;
}
