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
		bws_base_source_option(),
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

	return bws_prepare_registration_options( array_merge( $tag_level, $columns ) );
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
 * @return array[] `{ header, opts }` per column; `opts` is bws_fold_slot_chain_options()'s,
 *                 or null for a header-only column.
 */
function bws_table_columns( array $options ): array {
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
		$opts   = bws_fold_slot_chain_options( $slot, $carry, true );
		if ( null === $opts && '' === $header ) {
			continue;
		}
		$columns[] = array(
			'header' => $header,
			'opts'   => $opts,
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
 * Read one column's cell off one row.
 *
 * Runs the column's steps from its start (the row, unless its root names another
 * source), then reads every source it lands on, joined `, ` as a {{join}} slot does.
 * The FIXED read comes first and reads nothing, so it applies on every row kind,
 * `meta_row` included; bws_fixed_text_read() owns what an unresolved source shows.
 * A keyed read goes through the L2 read seam. Analog reads render empty here until the
 * analog dispatcher lands.
 *
 * @since 1.17.0
 * @since Unreleased A column is a folded slot; keyed and fixed reads.
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
			$value = '';
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
 * Callback for the {{table}} tag.
 *
 * 1. Resolve the base source (ambient/explicit) the chain starts from.
 * 2. Run the whole compiled chain → the rows (one per resolved source, any kind).
 * 3. For each row, read every configured column → a cell matrix.
 * 4. Assemble the <table> string, wrapped in the W3C responsive scroll region
 *    (+ <caption> when set), in the text-as-div host.
 *
 * Refused root, no rows or no columns → '' (nothing to build).
 * The `id` from the editor is threaded into the base source by the factory
 * (composing-tag id threading, CONTEXT.md I11) so the editor preview resolves.
 *
 * @since 1.17.0
 * @param array  $options  Tag options.
 * @param object $block    GB block (unused; signature parity).
 * @param object $instance GB tag instance.
 * @return string
 */
function bws_table_callback( $options, $block, $instance ): string {
	$options = (array) $options;
	$columns = bws_table_columns( $options );
	if ( empty( $columns ) ) {
		return '';
	}

	// The rows ARE the chain's tail: whatever the whole compiled chain resolves to, one
	// row each. An empty chain is the current context, a one-row table.
	$base = bws_base_resolve_source_for_callback( $options, $instance );
	$res  = bws_base_src_resolution( $options );

	// A root this render cannot use produces no table, not an empty one.
	if ( bws_base_read_refused( $res, $base, bws_table_serves() ) ) {
		return '';
	}

	$row_sources = bws_run_traversal( array( $base ), bws_field_values_assemble_steps( $options ) );
	if ( empty( $row_sources ) ) {
		return '';
	}

	$explicit_id = (string) ( $options['id'] ?? '' );
	$headers     = array();
	foreach ( $columns as $i => $col ) {
		$headers[] = $col['header'];
		$columns[ $i ]['start'] = bws_table_column_start( $col['opts'], $explicit_id, $instance );
		$columns[ $i ]['steps'] = bws_field_values_assemble_steps( $col['opts'] ?? array() );
	}

	// Accessible caption + a request-unique id for aria-labelledby (W3C responsive
	// data-table pattern). wp_unique_id is a deterministic per-request counter — no
	// randomness — so it is safe on the frontend and stable within a render.
	$caption    = trim( (string) ( $options['caption'] ?? '' ) );
	$caption_id = '' !== $caption && function_exists( 'wp_unique_id' )
		? wp_unique_id( 'bws-table-caption-' )
		: '';

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

	$html = bws_table_assemble( $headers, $matrix, $caption, $caption_id );
	if ( '' === $html ) {
		return '';
	}

	// A rendered captioned table carries the scroll-region wrapper — queue its CSS
	// (idempotent, shared footer-printer). After the empty-html guard so an
	// all-skipped-rows table (which assembles to '') leaves no orphan CSS.
	if ( '' !== $caption_id && function_exists( 'bws_queue_inline_css' ) ) {
		bws_queue_inline_css( 'bws-table-inline-css', BWS_TABLE_INLINE_CSS );
	}

	// text-as-div host: hand the atomic <table> to the GB output boundary so
	// link/attr handling stays consistent, but the value is already fully-formed
	// HTML. The class_exists guard stays: it is what keeps a GB-less install
	// returning the bare markup rather than fatalling.
	return class_exists( 'GenerateBlocks_Dynamic_Tag_Callbacks' )
		? bws_gb_tag_output( $html, $options, $instance )
		: $html;
}
