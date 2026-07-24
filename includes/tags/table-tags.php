<?php
/**
 * {{table}} — structured-output tag: build an HTML table from a repeater field.
 *
 * A composing tag over the traversal fold (traversal-pipeline.php). The tag-level
 * source + traversal resolve a base entity; a `rows` step hops that entity's
 * repeater field to a meta_row[] (one row per repeater entry); each configured
 * column reads a sub-field off every row. Output is an atomic <table> string in a
 * text-as-div host (the {{content}} precedent) — NOT a scalar; it must not be
 * inserted into a <p>/<span> (the tag modal description warns of this).
 *
 * v1 SCOPE (grill 2026-07-24, handoff §11):
 *   - Full source support (current/ref/site) + srcTermIn — the base entity that
 *     OWNS the repeater; the `rows` step reads it via ACF get_field (not have_rows,
 *     whose stateful cursor breaks the pure fold).
 *   - Columns are numbered-prefix (ALL columns `{N}-` from N=1; bare keys reserved
 *     for the tag-level repeater source — option i, join/try_ retrofit deferred).
 *   - Per column: `use` (key = scalar sub-field read off the row; title = a
 *     relationship sub-field hopped ref→post, limit-1, title read).
 *   - `label` per column = the header cell (frontend header; label-mode question
 *     is quarantined — a bare label string is the whole header story for v1).
 *
 * DEFERRED (do NOT infer from this file): header label-mode config, final option
 * names, slot-cap const naming, composite controls, {{list}}. See handoff §11.
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
 * global constraint, tunable here. Provisional value; revisit with the option-name
 * pass (handoff §11 deferred).
 */
if ( ! defined( 'BWS_TABLE_MAX_COLS' ) ) {
	define( 'BWS_TABLE_MAX_COLS', 6 );
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

	new GenerateBlocks_Register_Dynamic_Tag( array(
		'title'       => __( 'Table', 'generateblocks' ),
		'tag'         => 'table',
		'type'        => 'cross-source',
		'supports'    => array(),
		// Help text in the tag selector / modal. text-as-div host: the output is a
		// full <table> block, so the containing element must be a block container.
		'description' => __( 'Builds a table from a repeater field. Outputs a full <table>; do not place inside a <p> or <span> — use a Div or other block element.', 'generateblocks' ),
		'options'     => bws_get_table_options(),
		'return'      => 'bws_table_callback',
	) );
}

/**
 * Build the {{table}} option definitions.
 *
 * Tag-level (bare keys): the base source that OWNS the repeater — `src`
 * (current/ref/site), `ref`/`srcTermIn` traversal, and `rows` (the repeater field
 * key). Then N column slots, ALL `{N}-`-prefixed from N=1 (bare reserved for the
 * tag-level source — option i). Per column: `{N}-use`, `{N}-key`/`{N}-ref`,
 * `{N}-label` (header cell text).
 *
 * @since 1.17.0
 * @return array Option definitions keyed by option name.
 */
function bws_get_table_options(): array {
	$source_opt     = function_exists( 'bws_base_source_option' ) ? bws_base_source_option() : array();
	$traversal_opts = function_exists( 'bws_base_traversal_options' ) ? bws_base_traversal_options() : array();

	$tag_level = array_merge(
		$source_opt,
		$traversal_opts,
		array(
			'rows' => array(
				'type'         => 'bws-field-combo',
				'label'        => __( 'Repeater Field', 'generateblocks' ),
				'dynamicLabel' => true,
				'help'         => __( 'ACF repeater (or meta) field key. Each row of the repeater becomes a table row.', 'generateblocks' ),
				'placeholder'  => 'repeater_field',
			),
		)
	);

	$columns = array();
	for ( $n = 1; $n <= BWS_TABLE_MAX_COLS; $n++ ) {
		// Column N reveal: columns 1-2 always visible; column N ≥ 3 reveals when the
		// previous column is "real" (has a key set). Mirrors join's combining reveal.
		if ( $n <= 2 ) {
			$col_trigger = array();
		} else {
			$prev        = $n - 1;
			$col_trigger = array(
				'show_if_any' => array( "{$prev}-key" => 'not_empty' ),
			);
		}

		$columns[ "{$n}-label" ] = array_merge(
			array(
				'type'        => 'text',
				/* translators: %d: column number */
				'label'       => sprintf( __( 'Column %d Header', 'generateblocks' ), $n ),
				'help'        => __( 'Header cell text for this column. Leave empty for no header.', 'generateblocks' ),
			),
			$col_trigger
		);

		$columns[ "{$n}-use" ] = array_merge(
			array(
				'type'           => 'select',
				/* translators: %d: column number */
				'label'          => sprintf( __( 'Column %d Field', 'generateblocks' ), $n ),
				'options'        => array(
					array( 'value' => 'key',   'label' => __( 'Meta/Option Field', 'generateblocks' ) ),
					array( 'value' => 'title', 'label' => __( 'Related Post Title', 'generateblocks' ) ),
				),
				'_strip_default' => true,
			),
			$col_trigger
		);

		// key — the sub-field read off each repeater row. For use:key it is the
		// scalar column value; for use:title it is the relationship sub-field hopped
		// ref→post (limit-1) then title-read.
		$columns[ "{$n}-key" ] = array_merge(
			array(
				'type'         => 'bws-field-combo',
				'dynamicLabel' => true,
				/* translators: %d: column number */
				'label'        => sprintf( __( 'Column %d Field Key', 'generateblocks' ), $n ),
				'help'         => __( 'Sub-field key within each repeater row.', 'generateblocks' ),
				'placeholder'  => 'sub_field',
			),
			$col_trigger
		);
	}

	return function_exists( 'bws_strip_default_select_values' )
		? bws_strip_default_select_values( array_merge( $tag_level, $columns ) )
		: array_merge( $tag_level, $columns );
}

/**
 * Extract the ordered column configs from a flat {{table}} option map.
 *
 * Pure transform (no WP/GB symbols) — reads `{N}-use`/`{N}-key`/`{N}-label` for
 * N in 1..$max. A column is "present" iff it has a non-empty key (use:key/title
 * both need a field). Stops at the first absent column (columns are contiguous
 * from 1). Returns a list of { use, key, label }.
 *
 * @since 1.17.0
 * @param array $options Flat tag options.
 * @param int   $max     Max columns to scan (BWS_TABLE_MAX_COLS).
 * @return array[] Ordered column configs; empty if no column has a key.
 */
function bws_table_collect_columns( array $options, int $max ): array {
	$cols = array();
	for ( $n = 1; $n <= $max; $n++ ) {
		$key = trim( (string) ( $options[ "{$n}-key" ] ?? '' ) );
		if ( '' === $key ) {
			break; // Columns are contiguous — first gap ends the set.
		}
		$cols[] = array(
			'use'   => $options[ "{$n}-use" ] ?? 'key',
			'key'   => $key,
			'label' => (string) ( $options[ "{$n}-label" ] ?? '' ),
		);
	}
	return $cols;
}

/**
 * Read one column's cell value off a single meta_row resolved source.
 *
 * use:key  → scalar sub-field read (bws_pipeline_default_reader meta_row arm).
 * use:title → the sub-field holds a relationship id; hop it ref→post (limit-1)
 *             and read the post title. Missing sub-field / no target → ''.
 *
 * @since 1.17.0
 * @param array  $row_source { kind:'meta_row', row }.
 * @param array  $col        { use, key, label }.
 * @param object $instance   GB instance.
 * @return string Cell value (may be '').
 */
function bws_table_read_cell( array $row_source, array $col, $instance ): string {
	$use = $col['use'] ?? 'key';
	$key = $col['key'] ?? '';

	if ( 'title' === $use ) {
		// Column-as-mini-traversal: ref-hop the sub-field off the row → post[], take
		// the first, read its title (limit-1, no override — v1 precedent). Runs the
		// REAL fold so the meta_row ref reader arm + coercer do the work.
		if ( ! function_exists( 'bws_run_traversal' ) ) {
			return '';
		}
		$posts = bws_run_traversal(
			array( $row_source ),
			array( array( 'type' => 'ref', 'field' => $key ) )
		);
		$first = reset( $posts );
		if ( ! is_array( $first ) || 'post' !== ( $first['kind'] ?? '' ) ) {
			return '';
		}
		$pid = (int) ( $first['id'] ?? 0 );
		return $pid > 0 ? (string) get_the_title( $pid ) : '';
	}

	// use:key — scalar sub-field read off the row.
	$raw = function_exists( 'bws_pipeline_default_reader' )
		? bws_pipeline_default_reader( array( 'type' => 'ref', 'field' => $key ), $row_source )
		: ( $row_source['row'][ $key ] ?? '' );
	return ( is_scalar( $raw ) && '' !== $raw ) ? (string) $raw : '';
}

/**
 * Assemble the <table> HTML from headers + a matrix of cell strings.
 *
 * Pure presentation (L3, tag-side — the fold stays dumb): a <thead> is emitted
 * only when at least one column carries a header label; every row becomes a <tr>
 * of <td>. Cells are escaped (esc_html); headers too. No caption/scope styling in
 * v1 (deferred with the header label-mode question).
 *
 * @since 1.17.0
 * @param string[] $headers Column header labels (may contain '' entries).
 * @param array[]  $matrix  Row-major cell strings ($matrix[row][col]).
 * @return string <table> HTML, or '' if no rows.
 */
function bws_table_assemble( array $headers, array $matrix ): string {
	if ( empty( $matrix ) ) {
		return '';
	}

	$has_header = false;
	foreach ( $headers as $h ) {
		if ( '' !== (string) $h ) {
			$has_header = true;
			break;
		}
	}

	$html = '<table class="bws-table">';

	if ( $has_header ) {
		$html .= '<thead><tr>';
		foreach ( $headers as $h ) {
			$html .= '<th>' . esc_html( (string) $h ) . '</th>';
		}
		$html .= '</tr></thead>';
	}

	$html .= '<tbody>';
	foreach ( $matrix as $cells ) {
		$html .= '<tr>';
		foreach ( $cells as $cell ) {
			$html .= '<td>' . esc_html( (string) $cell ) . '</td>';
		}
		$html .= '</tr>';
	}
	$html .= '</tbody></table>';

	return $html;
}

/**
 * Callback for the {{table}} tag.
 *
 * 1. Resolve the base source (ambient/explicit) that OWNS the repeater.
 * 2. Run the tag-level ref steps then the `rows` step → meta_row[].
 * 3. For each row, read every configured column → a cell matrix.
 * 4. Assemble the <table> string (text-as-div host).
 *
 * Empty repeater or no columns → '' (the fold short-circuits; nothing to build).
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
	$columns = bws_table_collect_columns( $options, BWS_TABLE_MAX_COLS );
	if ( empty( $columns ) ) {
		return '';
	}

	$rows_field = trim( (string) ( $options['rows'] ?? '' ) );
	if ( '' === $rows_field ) {
		return '';
	}

	if ( ! function_exists( 'bws_base_resolve_source_for_callback' ) || ! function_exists( 'bws_run_traversal' ) ) {
		return '';
	}

	// L1 — resolve the base entity that owns the repeater. Apply the tag-level ref
	// steps (src:ref) FIRST so the repeater is read off the ref target, then hop the
	// `rows` step. srcTermIn is intentionally NOT applied here in v1 (a repeater on a
	// term is read directly from the term base; the post→term hop belongs to a later
	// pass with the term-source column story).
	$base = bws_base_resolve_source_for_callback( $options, $instance );

	$ref_steps = function_exists( 'bws_wrapper_ref_steps' ) ? bws_wrapper_ref_steps( $options ) : array();
	$steps     = array_merge( $ref_steps, array( array( 'type' => 'rows', 'field' => $rows_field ) ) );

	$row_sources = bws_run_traversal( array( $base ), $steps );
	if ( empty( $row_sources ) ) {
		return '';
	}

	$headers = array();
	foreach ( $columns as $col ) {
		$headers[] = $col['label'];
	}

	$matrix = array();
	foreach ( $row_sources as $row_source ) {
		if ( ! is_array( $row_source ) || 'meta_row' !== ( $row_source['kind'] ?? '' ) ) {
			continue;
		}
		$cells = array();
		foreach ( $columns as $col ) {
			$cells[] = bws_table_read_cell( $row_source, $col, $instance );
		}
		$matrix[] = $cells;
	}

	$html = bws_table_assemble( $headers, $matrix );
	if ( '' === $html ) {
		return '';
	}

	// text-as-div host: hand the atomic <table> to GB's output() so link/attr
	// handling stays consistent, but the value is already fully-formed HTML.
	return class_exists( 'GenerateBlocks_Dynamic_Tag_Callbacks' )
		? GenerateBlocks_Dynamic_Tag_Callbacks::output( $html, $options, $instance )
		: $html;
}
