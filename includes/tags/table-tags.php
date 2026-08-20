<?php
/**
 * {{table}} — structured-output tag: build an HTML table from a repeater field.
 *
 * A composing tag over the traversal fold (traversal-pipeline.php). The tag-level
 * source + traversal resolve a base entity; the `key` option names the repeater
 * field, and a `rows` fold step steps that entity's repeater to a meta_row[] (one
 * row per repeater entry); each configured column reads a sub-field off every row.
 *
 * `key` NAMES THE FIELD (house nomenclature — the primary field target, parallel to
 * every other tag's `key`); the internal fold step it drives stays named `rows`
 * (the datum it produces). The `key`→`rows`-step mapping is TABLE-ONLY, owned here:
 * no other tag turns a bare `key` into a rows step. Output is an atomic <table>
 * string in a
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
 * names, slot-maximum const naming, composite controls, {{list}}. See handoff §11.
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
 * (current/ref/site), `ref`/`srcTermIn` traversal, and `key` (the repeater field
 * key — drives the table-only `rows` fold step). Then N column slots, ALL
 * `{N}-`-prefixed from N=1 (bare reserved for the tag-level source — option i). Per
 * column: `{N}-use`, `{N}-key`/`{N}-ref`, `{N}-label` (header cell text). Each
 * `{N}-key` carries `'scope' => 'row'` so its field picker auto-narrows to the
 * chosen repeater's sub-fields (see field-combo-control.js; #12). The tag-level
 * `key` carries `'typeDefault' => 'repeater'` — the OTHER scope axis: its picker
 * opens showing only repeater fields, but keeps the filter controls visible so the
 * author can widen (a non-ACF meta repeater has no discovered type → falls to All).
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
			'key' => array(
				'type'         => 'bws-field-combo',
				'label'        => __( 'Repeater Field', 'generateblocks' ),
				'dynamicLabel' => true,
				'help'         => __( 'ACF repeater (or meta) field key. Each row of the repeater becomes a table row.', 'generateblocks' ),
				'placeholder'  => 'repeater_field',
				// Pre-scope the picker's type filter to repeater fields (the OTHER scope
				// axis vs the {N}-key column controls' 'scope' => 'row'). The two filter
				// SelectControls stay visible, so the author can widen to All or pick a
				// plain-meta key (a non-ACF meta repeater has no discovered 'repeater'
				// type, so the guard falls back to All). See field-combo-control.js
				// (typeDefault) + FW-53.
				'typeDefault'  => 'repeater',
			),
			// Accessible caption (W3C data-table pattern). Labels the table for
			// screen readers and, when set, names the responsive scroll region via
			// aria-labelledby. Optional — omit for no <caption>.
			'caption' => array(
				'type'        => 'text',
				'label'       => __( 'Caption', 'generateblocks' ),
				'help'        => __( 'Optional table caption. Labels the table for assistive technology.', 'generateblocks' ),
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
				// Auto-scope the picker to the tag-level `key` repeater's sub-fields
				// (#12): the field-combo control reads the sibling `key`, filters
				// results to that repeater's row-context sub-fields, and HIDES the two
				// location/type filter selectors. Free-text of any key still allowed.
				'scope'        => 'row',
			),
			$col_trigger
		);
	}

	return function_exists( 'bws_prepare_registration_options' )
		? bws_prepare_registration_options( array_merge( $tag_level, $columns ) )
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
 * use:title → the sub-field holds a relationship id; step it ref→post (limit-1)
 *             and read the post title. Missing sub-field / no target → ''.
 *
 * @since 1.17.0
 * @param array  $row_source { kind:'meta_row', row }.
 * @param array  $col        { use, key, label }.
 * @param object $instance   GB instance. Reserved seam — unused by the v1 scalar +
 *                           ref→title reads, kept for the non-scalar cell reader
 *                           (nested repeater / image / rich value, plan #10) that
 *                           will need the instance for a nested render/context.
 * @return string Cell value (may be '').
 */
function bws_table_read_cell( array $row_source, array $col, $instance ): string {
	$use = $col['use'] ?? 'key';
	$key = $col['key'] ?? '';

	if ( 'title' === $use ) {
		// Column-as-mini-traversal: ref-step the sub-field off the row → post[], take
		// the first, read its title (limit-1, no override — v1 precedent). Runs the
		// REAL fold so the meta_row ref reader arm + coercer do the work.
		if ( ! function_exists( 'bws_run_traversal' ) ) {
			return '';
		}
		$posts = bws_run_traversal(
			array( $row_source ),
			array( array( 'type' => 'refs', 'field' => $key ) )
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
		? bws_pipeline_default_reader( array( 'type' => 'refs', 'field' => $key ), $row_source )
		: ( $row_source['row'][ $key ] ?? '' );
	return ( is_scalar( $raw ) && '' !== $raw ) ? (string) $raw : '';
}

/**
 * Assemble the <table> HTML from headers + a matrix of cell strings.
 *
 * Pure presentation (L3, tag-side — the fold stays dumb). Follows the
 * Roselli/W3C responsive-data-table pattern (adrianroselli.com/2020/11/
 * under-engineered-responsive-tables.html; design-system.w3.org/styles/tables.html):
 *
 *   - When a caption is set, the table is wrapped in a focusable scroll region —
 *     `<div role="region" tabindex="0" aria-labelledby="$caption_id">` — and
 *     `<caption id="$caption_id">` names it. The `tabindex="0"` lets a keyboard
 *     user focus and scroll an overflowing table (WCAG 2.1.1); `role="region"` +
 *     `aria-labelledby` give that focusable element its required name+role (WCAG
 *     4.1.2). The frontend CSS (bws_table_print_inline_css) matches this exact
 *     attribute triple to apply `overflow:auto` (WCAG 1.4.10 Reflow) + a focus
 *     outline — NO class hook needed (the attribute selector self-enforces the
 *     a11y markup, per Roselli).
 *   - Without a caption there is NO wrapper — a focusable region with no accessible
 *     name would itself fail WCAG 4.1.2, so a caption-less table is a bare
 *     `<table>` (no scroll containment, but no violation). Authors who want the
 *     scroll region set a caption.
 *   - A <thead> is emitted only when at least one column carries a header label;
 *     every row becomes a <tr> of <td>. Header <th> carry NO scope="col" yet — the
 *     scope-col/scope-row pair is deferred together (col scope only disambiguates
 *     axes once row headers also exist).
 *
 * No presentation class on the <table> either — the tag ships bare structural
 * markup (§9); styling is the theme's, the only plugin CSS is the 6-line
 * attribute-scoped scroll rule.
 *
 * Cells, headers, and caption are escaped (esc_html); the caption id is
 * esc_attr'd. Caller supplies a request-unique $caption_id (wp_unique_id) so the
 * assembler stays framework-free for the pure harness.
 *
 * @since 1.17.0
 * @param string[] $headers    Column header labels (may contain '' entries).
 * @param array[]  $matrix     Row-major cell strings ($matrix[row][col]).
 * @param string   $caption    Caption text ('' = no caption).
 * @param string   $caption_id Unique id for the caption element / aria-labelledby.
 * @return string <table> HTML (wrapped in the scroll region when captioned), or
 *                '' if no rows.
 */
function bws_table_assemble( array $headers, array $matrix, string $caption = '', string $caption_id = '' ): string {
	if ( empty( $matrix ) ) {
		return '';
	}

	$has_caption = '' !== $caption && '' !== $caption_id;

	$has_header = false;
	foreach ( $headers as $h ) {
		if ( '' !== (string) $h ) {
			$has_header = true;
			break;
		}
	}

	$html = '';

	// Focusable scroll region — ONLY with a caption (its accessible name). No
	// caption → no wrapper (an unnamed focusable region fails WCAG 4.1.2).
	if ( $has_caption ) {
		$html .= '<div role="region" tabindex="0" aria-labelledby="' . esc_attr( $caption_id ) . '">';
	}

	$html .= '<table>';

	if ( $has_caption ) {
		$html .= '<caption id="' . esc_attr( $caption_id ) . '">' . esc_html( $caption ) . '</caption>';
	}

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

	if ( $has_caption ) {
		$html .= '</div>';
	}

	return $html;
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
 * Callback for the {{table}} tag.
 *
 * 1. Resolve the base source (ambient/explicit) that OWNS the repeater.
 * 2. Run the tag-level ref steps then the `rows` step → meta_row[].
 * 3. For each row, read every configured column → a cell matrix.
 * 4. Assemble the <table> string, wrapped in the W3C responsive scroll region
 *    (+ <caption> when set), in the text-as-div host.
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

	// Tag-level `key` NAMES the repeater field (house nomenclature); it drives the
	// table-only `rows` fold step below. No other tag maps a bare `key` to a rows
	// step — the mapping is owned here.
	$rows_field = trim( (string) ( $options['key'] ?? '' ) );
	if ( '' === $rows_field ) {
		return '';
	}

	if ( ! function_exists( 'bws_base_resolve_source_for_callback' ) || ! function_exists( 'bws_run_traversal' ) ) {
		return '';
	}

	// L1 — resolve the base entity that owns the repeater. Apply the tag-level ref
	// steps (src:ref) FIRST so the repeater is read off the ref target, then step the
	// `rows` step. srcTermIn is intentionally NOT applied here in v1 (a repeater on a
	// term is read directly from the term base; the post→term step belongs to a later
	// pass with the term-source column story).
	$base = bws_base_resolve_source_for_callback( $options, $instance );

	$ref_steps = function_exists( 'bws_wrapper_ref_steps' ) ? bws_wrapper_ref_steps( $options ) : array();
	$steps     = array_merge( $ref_steps, array( array( 'type' => 'entries', 'field' => $rows_field ) ) );

	$row_sources = bws_run_traversal( array( $base ), $steps );
	if ( empty( $row_sources ) ) {
		return '';
	}

	$headers = array();
	foreach ( $columns as $col ) {
		$headers[] = $col['label'];
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
		if ( ! is_array( $row_source ) || 'meta_row' !== ( $row_source['kind'] ?? '' ) ) {
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

	// text-as-div host: hand the atomic <table> to GB's output() so link/attr
	// handling stays consistent, but the value is already fully-formed HTML.
	return class_exists( 'GenerateBlocks_Dynamic_Tag_Callbacks' )
		? GenerateBlocks_Dynamic_Tag_Callbacks::output( $html, $options, $instance )
		: $html;
}
