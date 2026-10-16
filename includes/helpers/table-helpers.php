<?php
/**
 * {{table}} helpers — the PURE assembly (no WP/GB symbols beyond the two escapers).
 *
 * Registration, row production and the cell read stay in includes/tags/table-tags.php;
 * this file is what tools/test/table-assemble-test.php loads, so the boundary sits where
 * the test boundary already was.
 *
 * @package BWS_Dynamic_Tags
 * @since Unreleased
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
 * The shared CSS-wide keywords every {{table}} text key accepts beside its own values.
 * One list for all six keys; a key's own values live with its validator.
 */
const BWS_TABLE_CSS_GLOBALS = array( 'inherit', 'initial', 'unset', 'revert', 'revert-layer' );

/** The `textAlign` values: no `justify`. */
const BWS_TABLE_TEXT_ALIGN_VALUES = array( 'left', 'center', 'right' );

/**
 * Read a `base;header,x` value: THE reader for a valued key that carries an optional
 * header-cell override (the six text keys, and `stripe` once it lands).
 *
 * Items split on `;` outside brackets. A bare item is the base; an item led by the `header`
 * head is the override. Order on the wire is free (an override before the base is read the
 * same), and the result is base-first by construction. A malformed ITEM is dropped and named
 * in `flags` while its valid siblings stand: a second base, a repeated header (the first
 * wins), an unknown head, an empty `header,` and a value `$valid` refuses. An unbalanced
 * bracket drops the WHOLE key (null), because no item boundary can be trusted past it.
 *
 * Do not give a key its own splitter: two readers of one hand-edited value would read it two
 * ways.
 *
 * @since Unreleased
 * @param string   $value Raw value (`right`, `right;header,center`, `header,center`).
 * @param callable $valid Takes one candidate string, returns its canonical form or null when refused.
 * @return array{base:?string,header:?string,flags:string[]}|null Null when a bracket is unbalanced.
 */
function bws_headed_value_parse( string $value, callable $valid ): ?array {
	$items = bws_fold_split_depth( $value, array( ';' ) );
	if ( isset( $items['error'] ) ) {
		return null;
	}
	$out = array( 'base' => null, 'header' => null, 'flags' => array() );
	foreach ( $items as $item ) {
		$item = trim( $item );
		if ( '' === $item ) {
			continue;
		}
		$parts = array_map( 'trim', bws_fold_split_depth( $item, array( ',' ) ) );
		if ( 1 === count( $parts ) ) {
			$slot = 'base';
			$arg  = $parts[0];
		} elseif ( 'header' === $parts[0] && 2 === count( $parts ) ) {
			$slot = 'header';
			$arg  = $parts[1];
		} elseif ( 'header' === $parts[0] ) {
			$out['flags'][] = "header item '$item' takes one value";
			continue;
		} else {
			$out['flags'][] = "unknown head '{$parts[0]}'";
			continue;
		}
		$canon = '' === $arg ? null : $valid( $arg );
		if ( null === $canon ) {
			$out['flags'][] = "'$item' is not a recognized value";
		} elseif ( null !== $out[ $slot ] ) {
			$out['flags'][] = "repeated $slot item '$item'";
		} else {
			$out[ $slot ] = $canon;
		}
	}
	return $out;
}

/**
 * Canonical form of one `textAlign` candidate, or null when it is neither an alignment nor a CSS global.
 *
 * @since Unreleased
 * @param string $candidate Raw value.
 * @return string|null
 */
function bws_table_text_align_value( string $candidate ): ?string {
	$candidate = strtolower( $candidate );
	return in_array( $candidate, BWS_TABLE_TEXT_ALIGN_VALUES, true ) || in_array( $candidate, BWS_TABLE_CSS_GLOBALS, true )
		? $candidate
		: null;
}

/**
 * Read a `textAlign` value, tag level or column token.
 *
 * @since Unreleased
 * @param string $value Raw value.
 * @return array{base:?string,header:?string,flags:string[]}|null See bws_headed_value_parse().
 */
function bws_table_text_align_parse( string $value ): ?array {
	return bws_headed_value_parse( $value, 'bws_table_text_align_value' );
}

/**
 * The alignment one cell takes, or '' for none (the theme's).
 *
 * A body cell takes the column's base, then the table's. A HEADER cell takes the column's
 * header override, the column's base, the table's header override, then the table's base: a
 * column's bare `textAlign` covers its header, which color and the font keys do not.
 * A refused or absent value is simply not in the parse, so the cascade falls
 * through it to the next level.
 *
 * @since Unreleased
 * @param array|null $table     bws_table_text_align_parse() of the tag-level key.
 * @param array|null $column    bws_table_text_align_parse() of the column token.
 * @param bool       $is_header True for a `<th>` in the header row.
 * @return string
 */
function bws_table_text_align_for_cell( ?array $table, ?array $column, bool $is_header ): string {
	$levels = $is_header
		? array( $column['header'] ?? null, $column['base'] ?? null, $table['header'] ?? null, $table['base'] ?? null )
		: array( $column['base'] ?? null, $table['base'] ?? null );
	foreach ( $levels as $value ) {
		if ( null !== $value ) {
			return $value;
		}
	}
	return '';
}
