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
 * Whether a table gets a `<thead>`: at least one column carries a header label.
 *
 * The one answer for the assembler and for the stripe/header rules, so the markup and the CSS
 * written for it cannot disagree about whether a header row exists.
 *
 * @since Unreleased
 * @param string[] $headers Column header labels (may contain '' entries).
 * @return bool
 */
function bws_table_has_header( array $headers ): bool {
	foreach ( $headers as $h ) {
		if ( '' !== (string) $h ) {
			return true;
		}
	}
	return false;
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
 * An unstyled table carries no class: the tag ships bare structural markup and styling
 * is the theme's. A styled one carries its one scope class (`$scope_class`, from
 * bws_table_style_class()), which every presentation rule is scoped under; the only other
 * plugin CSS is the 6-line attribute-scoped scroll rule.
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
 * @param string   $scope_class The styled table's scope class ('' = unstyled, no class).
 * @return string <table> HTML (wrapped in the scroll region when captioned), or
 *                '' if no rows.
 */
function bws_table_assemble( array $headers, array $matrix, string $caption = '', string $caption_id = '', string $scope_class = '' ): string {
	if ( empty( $matrix ) ) {
		return '';
	}

	$has_caption = '' !== $caption && '' !== $caption_id;
	$has_header  = bws_table_has_header( $headers );

	$html = '';

	// Focusable scroll region — ONLY with a caption (its accessible name). No
	// caption → no wrapper (an unnamed focusable region fails WCAG 4.1.2).
	if ( $has_caption ) {
		$html .= '<div role="region" tabindex="0" aria-labelledby="' . esc_attr( $caption_id ) . '">';
	}

	$html .= '' === $scope_class ? '<table>' : '<table class="' . esc_attr( $scope_class ) . '">';

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

/** The `fontStyle` values beside the CSS globals. */
const BWS_TABLE_FONT_STYLES = array( 'normal', 'italic', 'oblique' );

/** The `textTransform` values beside the CSS globals. */
const BWS_TABLE_TEXT_TRANSFORMS = array( 'none', 'uppercase', 'lowercase', 'capitalize' );

/**
 * The head word that marks a header-cell override inside a text key's value
 * (`fontWeight:normal;header,bold`). The one place it is spelled; the JS twin reads the same
 * word off the vocabulary bws_table_style_vocabulary() ships.
 */
const BWS_TABLE_HEADER_HEAD = 'header';

/**
 * Tokenize a `head,args;head,args` value: THE one splitter for every valued key that spells
 * its items that way (the six text keys via bws_headed_value_parse(), and `stripe`).
 *
 * Items split on `;` outside brackets, each item's parts on `,` outside brackets, so a
 * function argument (`rgba(0,0,0,.5)`) stays one part. Empty items are dropped. An unbalanced
 * bracket returns null for the WHOLE value, because no item boundary can be trusted past it.
 * Reading policy (which head is a base, what a bad part costs) is each caller's; the SPLIT is
 * this function's alone, so one hand-edited value cannot read two ways. Its JS twin is
 * `headArgsItems()` in table-style-grammar.js.
 *
 * @since Unreleased
 * @param string $value Raw key value.
 * @return array<int,array{raw:string,parts:string[]}>|null Null when a bracket is unbalanced.
 */
function bws_head_args_items( string $value ): ?array {
	$items = bws_fold_split_depth( $value, array( ';' ) );
	if ( isset( $items['error'] ) ) {
		return null;
	}
	$out = array();
	foreach ( $items as $item ) {
		$item = trim( $item );
		if ( '' !== $item ) {
			$out[] = array( 'raw' => $item, 'parts' => array_map( 'trim', bws_fold_split_depth( $item, array( ',' ) ) ) );
		}
	}
	return $out;
}

/**
 * Read a `base;header,x` value: THE reader for a valued key that carries an optional
 * header-cell override (the six text keys; `stripe` shares the tokenizer, not this reader).
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
	$items = bws_head_args_items( $value );
	if ( null === $items ) {
		return null;
	}
	$out = array( 'base' => null, 'header' => null, 'flags' => array() );
	foreach ( $items as $item ) {
		$parts = $item['parts'];
		$raw   = $item['raw'];
		if ( 1 === count( $parts ) ) {
			$slot = 'base';
			$arg  = $parts[0];
		} elseif ( BWS_TABLE_HEADER_HEAD === $parts[0] && 2 === count( $parts ) ) {
			$slot = 'header';
			$arg  = $parts[1];
		} elseif ( BWS_TABLE_HEADER_HEAD === $parts[0] ) {
			$out['flags'][] = "header item '$raw' takes one value";
			continue;
		} else {
			$out['flags'][] = "unknown head '{$parts[0]}'";
			continue;
		}
		$canon = '' === $arg ? null : $valid( $arg );
		if ( null === $canon ) {
			$out['flags'][] = "'$raw' is not a recognized value";
		} elseif ( null !== $out[ $slot ] ) {
			$out['flags'][] = "repeated $slot item '$raw'";
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
 * The alignment one cell takes, or '' for none (the theme's). The `textAlign` case of
 * bws_table_text_for_cell(), kept for its own harness.
 *
 * @since Unreleased
 * @param array|null $table     bws_table_text_align_parse() of the tag-level key.
 * @param array|null $column    bws_table_text_align_parse() of the column token.
 * @param bool       $is_header True for a `<th>` in the header row.
 * @return string
 */
function bws_table_text_align_for_cell( ?array $table, ?array $column, bool $is_header ): string {
	return bws_table_text_for_cell( 'textAlign', $table, $column, $is_header );
}

/** The six text keys and the CSS property each one writes, in control order. */
const BWS_TABLE_TEXT_KEYS = array(
	'color'         => 'color',
	'fontSize'      => 'font-size',
	'fontWeight'    => 'font-weight',
	'fontStyle'     => 'font-style',
	'textAlign'     => 'text-align',
	'textTransform' => 'text-transform',
);

/**
 * The text keys whose bare column value also reaches the column's header cell. The rest
 * (color and the font keys) are body-only in a column; a header cell reaches them only through
 * the column Header or the table.
 */
const BWS_TABLE_TEXT_COLUMN_COVERS_HEADER = array( 'textAlign' );

/** Unit suffixes a width or font size may carry. */
const BWS_TABLE_UNITS = 'px|em|rem|%|ch|vw';

/** CSS named colors (CSS Color 4), lowercase. `currentColor` is handled apart. */
const BWS_TABLE_COLOR_NAMES = 'aliceblue antiquewhite aqua aquamarine azure beige bisque black blanchedalmond blue blueviolet brown burlywood cadetblue chartreuse chocolate coral cornflowerblue cornsilk crimson cyan darkblue darkcyan darkgoldenrod darkgray darkgreen darkgrey darkkhaki darkmagenta darkolivegreen darkorange darkorchid darkred darksalmon darkseagreen darkslateblue darkslategray darkslategrey darkturquoise darkviolet deeppink deepskyblue dimgray dimgrey dodgerblue firebrick floralwhite forestgreen fuchsia gainsboro ghostwhite gold goldenrod gray green greenyellow grey honeydew hotpink indianred indigo ivory khaki lavender lavenderblush lawngreen lemonchiffon lightblue lightcoral lightcyan lightgoldenrodyellow lightgray lightgreen lightgrey lightpink lightsalmon lightseagreen lightskyblue lightslategray lightslategrey lightsteelblue lightyellow lime limegreen linen magenta maroon mediumaquamarine mediumblue mediumorchid mediumpurple mediumseagreen mediumslateblue mediumspringgreen mediumturquoise mediumvioletred midnightblue mintcream mistyrose moccasin navajowhite navy oldlace olive olivedrab orange orangered orchid palegoldenrod palegreen paleturquoise palevioletred papayawhip peachpuff peru pink plum powderblue purple rebeccapurple red rosybrown royalblue saddlebrown salmon sandybrown seagreen seashell sienna silver skyblue slateblue slategray slategrey snow springgreen steelblue tan teal thistle tomato transparent turquoise violet wheat white whitesmoke yellow yellowgreen';

/** The `fontSize` keywords. */
const BWS_TABLE_FONT_SIZE_KEYWORDS = array( 'xx-small', 'x-small', 'small', 'medium', 'large', 'x-large', 'xx-large', 'xxx-large', 'larger', 'smaller' );

/**
 * Canonical form of a `var(--name)` or `var(--name, fallback)`, or null.
 *
 * The fallback carries no parentheses and none of `; { } < > " ' \ / @`, so it cannot open
 * a nested function or leave the declaration it sits in. Width, color and font size share it.
 *
 * @since Unreleased
 * @param string $value Candidate.
 * @return string|null
 */
function bws_table_css_var( string $value ): ?string {
	if ( ! preg_match( '/^var\(\s*(--[A-Za-z0-9_-]+)\s*(?:,\s*([#A-Za-z0-9 .%,_-]+?)\s*)?\)$/', trim( $value ), $m ) ) {
		return null;
	}
	return 'var(' . $m[1] . ( isset( $m[2] ) ? ',' . $m[2] : '' ) . ')';
}

/**
 * Canonical form of one width, or null: `auto`, a number with a unit, or `var()`.
 *
 * One validator for the tag key and the column token. Never run a width through
 * sanitize_text_field(), which strips `%` followed by two hex digits. `calc()`,
 * `fit-content` and `max-content` are refused.
 *
 * @since Unreleased
 * @param string $candidate Raw value.
 * @return string|null
 */
function bws_table_width_value( string $candidate ): ?string {
	$candidate = trim( $candidate );
	if ( 'auto' === strtolower( $candidate ) ) {
		return 'auto';
	}
	if ( preg_match( '/^(\d*\.?\d+)(' . BWS_TABLE_UNITS . ')$/i', $candidate, $m ) ) {
		return $m[1] . strtolower( $m[2] );
	}
	return bws_table_css_var( $candidate );
}

/**
 * Canonical form of one color, or null: THE strict allowlist for every color
 * that reaches the scoped stylesheet.
 *
 * Accepts `#rgb`/`#rgba`/`#rrggbb`/`#rrggbbaa`, `rgb()`/`rgba()`/`hsl()`/`hsla()` over digits
 * and a few separators, `var()`, a CSS color name and `currentColor`. A value reaches a
 * `<style>` block on every page that renders the table, so this is an allowlist of FORMS, not
 * a denylist of characters: `}`, `;`, `<`, quotes and comment syntax are refused because none
 * appears in an allowed form.
 *
 * @since Unreleased
 * @param string $candidate Raw value.
 * @param bool   $globals   Also accept the CSS globals (the text `color` key does, a stripe does not).
 * @return string|null
 */
function bws_table_color_value( string $candidate, bool $globals = false ): ?string {
	$candidate = trim( $candidate );
	$lower     = strtolower( $candidate );
	if ( 'currentcolor' === $lower ) {
		return 'currentColor';
	}
	if ( $globals && in_array( $lower, BWS_TABLE_CSS_GLOBALS, true ) ) {
		return $lower;
	}
	if ( preg_match( '/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $candidate ) ) {
		return $lower;
	}
	if ( preg_match( '/^(?:rgba?|hsla?)\(\s*[0-9a-z.%+\/\s,-]+\)$/i', $candidate ) ) {
		return preg_replace( '/\s+/', ' ', $lower );
	}
	if ( in_array( $lower, explode( ' ', BWS_TABLE_COLOR_NAMES ), true ) ) {
		return $lower;
	}
	return bws_table_css_var( $candidate );
}

/**
 * Whether a color from bws_table_color_value() carries its own alpha, so a stripe opacity does
 * not apply to it: 4- or 8-digit hex, `rgba()`, `hsla()`, slash syntax, `transparent`.
 *
 * @since Unreleased
 * @param string $color A canonical color.
 * @return bool
 */
function bws_table_color_has_alpha( string $color ): bool {
	return 1 === preg_match( '/^(?:#(?:[0-9a-f]{4}|[0-9a-f]{8})|rgba\(.*|hsla\(.*|(?:rgb|hsl)\(.*\/.*|transparent)$/i', $color );
}

/**
 * Canonical `fontSize`: a number with a unit, `var()`, a size keyword or a CSS global.
 * `calc()` stays unrecognized.
 *
 * @since Unreleased
 * @param string $candidate Raw value.
 * @return string|null
 */
function bws_table_font_size_value( string $candidate ): ?string {
	$candidate = trim( $candidate );
	$lower     = strtolower( $candidate );
	if ( in_array( $lower, BWS_TABLE_FONT_SIZE_KEYWORDS, true ) || in_array( $lower, BWS_TABLE_CSS_GLOBALS, true ) ) {
		return $lower;
	}
	if ( preg_match( '/^(\d*\.?\d+)(' . BWS_TABLE_UNITS . ')$/i', $candidate, $m ) ) {
		return $m[1] . strtolower( $m[2] );
	}
	return bws_table_css_var( $candidate );
}

/**
 * Canonical form of one candidate for a text key, or null when the key is unknown or the value
 * is refused. The one dispatch from key to validator.
 *
 * @since Unreleased
 * @param string $key       One of the BWS_TABLE_TEXT_KEYS.
 * @param string $candidate Raw value.
 * @return string|null
 */
function bws_table_text_value( string $key, string $candidate ): ?string {
	$lower = strtolower( trim( $candidate ) );
	$glob  = in_array( $lower, BWS_TABLE_CSS_GLOBALS, true );
	switch ( $key ) {
		case 'color':
			return bws_table_color_value( $candidate, true );
		case 'fontSize':
			return bws_table_font_size_value( $candidate );
		case 'fontWeight':
			return $glob || 'normal' === $lower || 'bold' === $lower || 1 === preg_match( '/^[1-9]00$/', $lower ) ? $lower : null;
		case 'fontStyle':
			return $glob || in_array( $lower, BWS_TABLE_FONT_STYLES, true ) ? $lower : null;
		case 'textAlign':
			return bws_table_text_align_value( $candidate );
		case 'textTransform':
			return $glob || in_array( $lower, BWS_TABLE_TEXT_TRANSFORMS, true ) ? $lower : null;
	}
	return null;
}

/**
 * Read one text key's value, tag level or column token.
 *
 * @since Unreleased
 * @param string $key   One of the BWS_TABLE_TEXT_KEYS.
 * @param string $value Raw value.
 * @return array{base:?string,header:?string,flags:string[]}|null See bws_headed_value_parse().
 */
function bws_table_text_parse( string $key, string $value ): ?array {
	return bws_headed_value_parse(
		$value,
		static function ( string $candidate ) use ( $key ): ?string {
			return bws_table_text_value( $key, $candidate );
		}
	);
}

/**
 * The value one cell takes for one text key, or '' for none (the theme's).
 *
 * A body cell takes the column's base, then the table's. A HEADER cell takes the column's
 * header override, then (for the keys in BWS_TABLE_TEXT_COLUMN_COVERS_HEADER only) the
 * column's base, then the table's header override, then the table's base. A refused or absent
 * value is simply not in the parse, so the cascade falls through it. THE one statement of the
 * cascade: bws_table_rules() asks it rather than restating it.
 *
 * @since Unreleased
 * @param string     $key       One of the BWS_TABLE_TEXT_KEYS.
 * @param array|null $table     bws_table_text_parse() of the tag-level key.
 * @param array|null $column    bws_table_text_parse() of the column token.
 * @param bool       $is_header True for a `<th>` in the header row.
 * @return string
 */
function bws_table_text_for_cell( string $key, ?array $table, ?array $column, bool $is_header ): string {
	$covers = in_array( $key, BWS_TABLE_TEXT_COLUMN_COVERS_HEADER, true );
	$levels = $is_header
		? array( $column['header'] ?? null, $covers ? ( $column['base'] ?? null ) : null, $table['header'] ?? null, $table['base'] ?? null )
		: array( $column['base'] ?? null, $table['base'] ?? null );
	foreach ( $levels as $value ) {
		if ( null !== $value ) {
			return $value;
		}
	}
	return '';
}

/**
 * Canonical stripe opacity (`N%`, 0 to 100) as a float, or null.
 *
 * @since Unreleased
 * @param string $candidate Raw value.
 * @return float|null
 */
function bws_table_opacity_value( string $candidate ): ?float {
	if ( ! preg_match( '/^(\d*\.?\d+)%$/', trim( $candidate ), $m ) || (float) $m[1] > 100 ) {
		return null;
	}
	return (float) $m[1];
}

/**
 * Read a `stripe` value: `rows,odd,#eee,40%;cols`.
 *
 * Items are `rows` and `cols` heads, split by bws_head_args_items(). Args after the head are
 * typed by VALUE, in any order: a parity word (`odd`/`even`, default `even`), an opacity
 * `N%`, a color (bws_table_color_value(), no CSS globals). A bad arg is dropped and flagged and
 * the item stands on the default for it; a repeated arg or repeated target keeps the first; an
 * unknown head drops the item; an unbalanced bracket drops the whole key (null). A color that
 * was refused is never carried, so it cannot reach the stylesheet in any form.
 *
 * @since Unreleased
 * @param string $value Raw value.
 * @return array{rows:?array,cols:?array,flags:string[]}|null Each target `{parity,color,opacity}`.
 */
function bws_table_stripe_parse( string $value ): ?array {
	$items = bws_head_args_items( $value );
	if ( null === $items ) {
		return null;
	}
	$out = array( 'rows' => null, 'cols' => null, 'flags' => array() );
	foreach ( $items as $item ) {
		$head = strtolower( $item['parts'][0] );
		if ( 'rows' !== $head && 'cols' !== $head ) {
			$out['flags'][] = "unknown head '{$item['parts'][0]}'";
			continue;
		}
		if ( null !== $out[ $head ] ) {
			$out['flags'][] = "repeated target '{$item['raw']}'";
			continue;
		}
		$spec = array( 'parity' => 'even', 'color' => null, 'opacity' => null );
		$seen = array();
		foreach ( array_slice( $item['parts'], 1 ) as $arg ) {
			$lower = strtolower( $arg );
			if ( 'odd' === $lower || 'even' === $lower ) {
				$slot  = 'parity';
				$val   = $lower;
			} elseif ( null !== ( $opacity = bws_table_opacity_value( $arg ) ) ) {
				$slot  = 'opacity';
				$val   = $opacity;
			} elseif ( null !== ( $color = bws_table_color_value( $arg ) ) ) {
				$slot  = 'color';
				$val   = $color;
			} else {
				$out['flags'][] = "'$arg' is not a recognized stripe value";
				continue;
			}
			if ( isset( $seen[ $slot ] ) ) {
				$out['flags'][] = "repeated $slot '$arg'";
				continue;
			}
			$seen[ $slot ] = true;
			$spec[ $slot ] = $val;
		}
		$out[ $head ] = $spec;
	}
	return $out;
}

/**
 * Read every text key present in an options array (tag level or a slot's opts).
 *
 * @since Unreleased
 * @param array $options Tag options or a slot's `opts`.
 * @return array<string,array> Key => bws_table_text_parse(), only keys that parsed to something.
 */
function bws_table_text_read( array $options ): array {
	$text = array();
	foreach ( array_keys( BWS_TABLE_TEXT_KEYS ) as $key ) {
		$parsed = isset( $options[ $key ] ) ? bws_table_text_parse( $key, (string) $options[ $key ] ) : null;
		if ( null !== $parsed && ( null !== $parsed['base'] || null !== $parsed['header'] ) ) {
			$text[ $key ] = $parsed;
		}
	}
	return $text;
}

/**
 * Read a table's presentation keys off its options.
 *
 * `layout` is `auto` or `fixed`; anything else is as if absent. A refused value is as if
 * absent everywhere, so the table renders what its other keys say.
 *
 * @since Unreleased
 * @param array $options Tag options.
 * @return array{layout:?string,width:?string,text:array<string,array>,stripe:?array}
 */
function bws_table_style_read( array $options ): array {
	$layout = strtolower( trim( (string) ( $options['layout'] ?? '' ) ) );
	$stripe = isset( $options['stripe'] ) ? bws_table_stripe_parse( (string) $options['stripe'] ) : null;
	return array(
		'layout' => 'auto' === $layout || 'fixed' === $layout ? $layout : null,
		'width'  => isset( $options['width'] ) ? bws_table_width_value( (string) $options['width'] ) : null,
		'text'   => bws_table_text_read( $options ),
		'stripe' => null !== $stripe && ( null !== $stripe['rows'] || null !== $stripe['cols'] ) ? $stripe : null,
	);
}

/**
 * Whether a bws_table_style_read() carries nothing that renders.
 *
 * @since Unreleased
 * @param array $style bws_table_style_read().
 * @return bool
 */
function bws_table_style_is_empty( array $style ): bool {
	return null === $style['layout'] && null === $style['width'] && empty( $style['text'] ) && null === $style['stripe'];
}

/**
 * Read a column's presentation tokens off its slot options.
 *
 * @since Unreleased
 * @param array $slot_opts A folded slot's `opts`.
 * @return array{width:?string,text:array<string,array>}
 */
function bws_table_column_style_read( array $slot_opts ): array {
	return array(
		'width' => isset( $slot_opts['width'] ) ? bws_table_width_value( (string) $slot_opts['width'] ) : null,
		'text'  => bws_table_text_read( $slot_opts ),
	);
}

/** The built-in stripe default: the text color at 5%, which stays visible on any theme. */
const BWS_TABLE_STRIPE_DEFAULT_COLOR = 'currentColor';

/** The built-in stripe opacity, in percent. */
const BWS_TABLE_STRIPE_DEFAULT_OPACITY = 5.0;

/**
 * A number for a stylesheet: locale-free, no trailing zeros.
 *
 * @since Unreleased
 * @param float $n Number.
 * @return string
 */
function bws_table_css_number( float $n ): string {
	return rtrim( rtrim( sprintf( '%.4F', $n ), '0' ), '.' );
}

/**
 * The color expression one stripe paints.
 *
 * `color-mix(in srgb, C N%, transparent)`, or the color as written when it carries its own
 * alpha. Neither a color nor an opacity written means the DEFAULT, wrapped in
 * `var(--bws-dte-table-stripe, …)` so a theme can still override it in CSS.
 *
 * @since Unreleased
 * @param array $spec     A stripe target `{parity,color,opacity}`.
 * @param array $defaults `{color,opacity}`: the saved or built-in default pair.
 * @return string
 */
function bws_table_stripe_color( array $spec, array $defaults ): string {
	$compose = static function ( string $color, float $opacity ): string {
		return bws_table_color_has_alpha( $color ) ? $color : 'color-mix(in srgb,' . $color . ' ' . bws_table_css_number( $opacity ) . '%,transparent)';
	};
	if ( null === $spec['color'] && null === $spec['opacity'] ) {
		return 'var(--bws-dte-table-stripe,' . $compose( $defaults['color'], $defaults['opacity'] ) . ')';
	}
	return $compose( $spec['color'] ?? $defaults['color'], $spec['opacity'] ?? $defaults['opacity'] );
}

/**
 * Build a table's presentation rules: the ONE builder behind the front end and the editor.
 *
 * Returns an ordered list of `{ sel: string[], decl: string }`, where each selector is a
 * suffix to append to the table's scope class (`' :is(th,td)'`, `''` for the table itself).
 * Cell values are asked of bws_table_text_for_cell() and emitted only where they differ from
 * what the table level already gives, so the cascade is stated once and the CSS stays small.
 * Specificity does the rest: a column rule (`.S tbody td:nth-child(n)`) outranks the table's
 * (`.S :is(th,td)`), and a column header rule outranks a table header rule.
 *
 * Stripes count the header row as row 1: with a `<thead>`, `even` shades body rows 1, 3, …
 * and `odd` the header row and body rows 2, 4, …; with none, counting starts at the first
 * emitted row. Nothing here sets `display` on a table part.
 *
 * @since Unreleased
 * @param array $table           bws_table_style_read().
 * @param array $columns         Per column `{width,text}` (bws_table_column_style_read()), in cell order.
 * @param bool  $has_header      Whether a `<thead>` is emitted (bws_table_has_header()).
 * @param array $stripe_defaults `{color,opacity}`.
 * @return array[]
 */
function bws_table_rules( array $table, array $columns, bool $has_header, array $stripe_defaults ): array {
	$rules = array();
	$add   = static function ( array $sel, array $decls ) use ( &$rules ): void {
		if ( ! empty( $decls ) ) {
			$rules[] = array( 'sel' => $sel, 'decl' => implode( ';', $decls ) );
		}
	};
	$decl  = static function ( string $key, string $value ): string {
		return BWS_TABLE_TEXT_KEYS[ $key ] . ':' . $value;
	};

	// The table itself. Fixed layout does nothing without a table width, so it brings one.
	$self = array();
	if ( null !== $table['layout'] ) {
		$self[] = 'table-layout:' . $table['layout'];
	}
	$width = $table['width'] ?? ( 'fixed' === $table['layout'] ? '100%' : null );
	if ( null !== $width ) {
		$self[] = 'width:' . $width;
	}
	$add( array( '' ), $self );

	// Table-level text: the base on every cell, the header override on header cells.
	$base   = array();
	$header = array();
	foreach ( $table['text'] as $key => $parsed ) {
		if ( null !== $parsed['base'] ) {
			$base[] = $decl( $key, $parsed['base'] );
		}
		if ( $has_header && null !== $parsed['header'] ) {
			$header[] = $decl( $key, $parsed['header'] );
		}
	}
	$add( array( ' :is(th,td)' ), $base );
	$add( array( ' thead th' ), $header );

	// Stripes.
	foreach ( array( 'rows', 'cols' ) as $target ) {
		$spec = $table['stripe'][ $target ] ?? null;
		if ( null === $spec ) {
			continue;
		}
		$even  = 'even' === $spec['parity'];
		$color = bws_table_stripe_color( $spec, $stripe_defaults );
		if ( 'cols' === $target ) {
			$sel = array( ' :is(th,td):nth-child(' . ( $even ? 'even' : 'odd' ) . ')' );
		} elseif ( $has_header ) {
			$sel = $even ? array( ' tbody tr:nth-child(odd)' ) : array( ' thead tr', ' tbody tr:nth-child(even)' );
		} else {
			$sel = array( ' tbody tr:nth-child(' . ( $even ? 'even' : 'odd' ) . ')' );
		}
		$add( $sel, array( 'background-image:linear-gradient(' . $color . ',' . $color . ')' ) );
	}

	// Columns. A cell value is emitted only where it differs from the table level's.
	foreach ( $columns as $i => $col ) {
		$n       = $i + 1;
		$on_both = array();
		$on_body = array();
		$on_head = array();
		if ( null !== ( $col['width'] ?? null ) ) {
			$on_both[] = 'width:' . $col['width'];
		}
		foreach ( array_keys( BWS_TABLE_TEXT_KEYS ) as $key ) {
			$tbl = $table['text'][ $key ] ?? null;
			$cl  = $col['text'][ $key ] ?? null;
			if ( null === $cl ) {
				continue;
			}
			$b = bws_table_text_for_cell( $key, $tbl, $cl, false );
			$h = $has_header ? bws_table_text_for_cell( $key, $tbl, $cl, true ) : '';
			$b = $b !== bws_table_text_for_cell( $key, $tbl, null, false ) ? $b : '';
			$h = $h !== bws_table_text_for_cell( $key, $tbl, null, true ) ? $h : '';
			if ( '' !== $b && $b === $h ) {
				$on_both[] = $decl( $key, $b );
				continue;
			}
			if ( '' !== $b ) {
				$on_body[] = $decl( $key, $b );
			}
			if ( '' !== $h ) {
				$on_head[] = $decl( $key, $h );
			}
		}
		$add( array( ' :is(th,td):nth-child(' . $n . ')' ), $on_both );
		$add( array( ' tbody td:nth-child(' . $n . ')' ), $on_body );
		$add( array( ' thead th:nth-child(' . $n . ')' ), $on_head );
	}
	return $rules;
}

/**
 * The scope class for a rule list: `bws-dte-table-<hash>`, the hash taken from the built rules
 * so identical tables share one stylesheet and differing ones cannot. '' for no rules.
 *
 * @since Unreleased
 * @param array[] $rules bws_table_rules().
 * @return string
 */
function bws_table_style_class( array $rules ): string {
	return empty( $rules ) ? '' : 'bws-dte-table-' . substr( md5( json_encode( $rules ) ), 0, 10 );
}

/**
 * The stylesheet text for a rule list under its scope class.
 *
 * Every value in the rules passed a validator in this file, so none can carry a `}`, `;` or
 * markup out of its declaration; this is the sink, and it escapes nothing further.
 *
 * @since Unreleased
 * @param string  $scope Scope class from bws_table_style_class().
 * @param array[] $rules bws_table_rules().
 * @return string
 */
function bws_table_rules_css( string $scope, array $rules ): string {
	$css = '';
	foreach ( $rules as $rule ) {
		$css .= implode( ',', array_map( static function ( string $suffix ) use ( $scope ): string {
			return '.' . $scope . $suffix;
		}, $rule['sel'] ) ) . '{' . $rule['decl'] . '}';
	}
	return $css;
}

/**
 * Canonical default stripe opacity from a saved setting (`5`, `12.5` or `5%`), or null.
 *
 * The settings page stores a bare percent; the wire spells it `N%`. Both read here, so the
 * settings sanitizer and the saved-value accessor cannot disagree.
 *
 * @since Unreleased
 * @param string $raw Saved value.
 * @return float|null 0 to 100, or null when empty or out of range.
 */
function bws_table_stripe_opacity_setting( string $raw ): ?float {
	$raw = trim( $raw );
	if ( '' === $raw ) {
		return null;
	}
	return bws_table_opacity_value( '%' === substr( $raw, -1 ) ? $raw : $raw . '%' );
}

/**
 * The stripe default pair from the two saved settings, each falling back to its built-in
 * value when empty or invalid (`currentColor` at 5%).
 *
 * @since Unreleased
 * @param string $color   Saved Default stripe color.
 * @param string $opacity Saved Default stripe opacity.
 * @return array{color:string,opacity:float}
 */
function bws_table_stripe_defaults_resolve( string $color, string $opacity ): array {
	return array(
		'color'   => bws_table_color_value( $color ) ?? BWS_TABLE_STRIPE_DEFAULT_COLOR,
		'opacity' => bws_table_stripe_opacity_setting( $opacity ) ?? BWS_TABLE_STRIPE_DEFAULT_OPACITY,
	);
}

/**
 * The vocabulary the editor's validators need, inlined as `window.bwsTableVocab`.
 *
 * Every list is read off the constants the PHP validators use, so the two languages cannot
 * carry different lists; the regex SHAPES (a number with a unit, a hex color, `var()`) are the
 * twin's, held to PHP by tools/test/table-style-twin-test.php.
 *
 * @since Unreleased
 * @return array<string,mixed>
 */
function bws_table_style_vocabulary(): array {
	return array(
		'headerHead'        => BWS_TABLE_HEADER_HEAD,
		'units'             => explode( '|', BWS_TABLE_UNITS ),
		'globals'           => BWS_TABLE_CSS_GLOBALS,
		'colorNames'        => explode( ' ', BWS_TABLE_COLOR_NAMES ),
		'fontSizeKeywords'  => BWS_TABLE_FONT_SIZE_KEYWORDS,
		'fontStyles'        => BWS_TABLE_FONT_STYLES,
		'textTransforms'    => BWS_TABLE_TEXT_TRANSFORMS,
		'textAligns'        => BWS_TABLE_TEXT_ALIGN_VALUES,
		'textKeys'          => array_keys( BWS_TABLE_TEXT_KEYS ),
		'columnCoversHeader' => BWS_TABLE_TEXT_COLUMN_COVERS_HEADER,
		'stripeDefaultColor'   => BWS_TABLE_STRIPE_DEFAULT_COLOR,
		'stripeDefaultOpacity' => BWS_TABLE_STRIPE_DEFAULT_OPACITY,
	);
}
