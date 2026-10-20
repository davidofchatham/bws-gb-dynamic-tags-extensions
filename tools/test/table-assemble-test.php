<?php
/**
 * Standalone unit harness for {{table}} HTML assembly (bws_table_assemble).
 *
 * Loads the REAL bws_table_assemble() from includes/helpers/table-helpers.php, which holds
 * nothing else. esc_html/esc_attr are stubbed to a minimal htmlspecialchars so the markup
 * shape is exercised without WP.
 *
 * Covers the W3C responsive-data-table pattern added 1.17.0:
 *   - the always-present scroll wrapper (role=region, tabindex=0),
 *   - <caption> + aria-labelledby wiring (present only when a caption is set),
 *   - <thead> emitted only when a header label exists,
 *   - escaping of caption / headers / cells,
 *   - empty-matrix short-circuit,
 *   - the scope class on a styled table, and the rule builder behind it (bws_table_rules):
 *     selectors, stripe counting with and without a <thead>, once-per-id hashing, no inline
 *     `style`, no `display`.
 *
 * Run:  php tools/test/table-assemble-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

$failures = 0;
$count    = 0;

function assert_eq( string $label, $expected, $actual ): void {
	global $failures, $count;
	$count++;
	if ( $expected === $actual ) {
		echo "  ok   {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL {$label}\n";
	echo "       expected: " . var_export( $expected, true ) . "\n";
	echo "       actual:   " . var_export( $actual, true ) . "\n";
}

function assert_contains( string $label, string $needle, string $haystack ): void {
	global $failures, $count;
	$count++;
	if ( str_contains( $haystack, $needle ) ) {
		echo "  ok   {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL {$label}\n";
	echo "       missing: {$needle}\n";
	echo "       in:      {$haystack}\n";
}

function assert_not_contains( string $label, string $needle, string $haystack ): void {
	global $failures, $count;
	$count++;
	if ( ! str_contains( $haystack, $needle ) ) {
		echo "  ok   {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL {$label}\n";
	echo "       unexpected: {$needle}\n";
	echo "       in:         {$haystack}\n";
}

// ---------------------------------------------------------------------------
// WP escaping stubs — minimal htmlspecialchars, enough to exercise the shape.
// ---------------------------------------------------------------------------
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
}

// ---------------------------------------------------------------------------
// The REAL assembler — includes/helpers/table-helpers.php is pure for this reason.
// ---------------------------------------------------------------------------
define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../../includes/helpers/serialization-order.php';
require __DIR__ . '/../../includes/helpers/slot-fold.php';
require __DIR__ . '/../../includes/helpers/table-helpers.php';

// ---------------------------------------------------------------------------
// Cases
// ---------------------------------------------------------------------------

echo "Empty matrix\n";
assert_eq( 'T1 no rows → empty string', '', bws_table_assemble( array( 'A' ), array(), 'Caption', 'c1' ) );
assert_eq( 'T1 no rows, allowed empty → header and an empty body', '<table><thead><tr><th>A</th></tr></thead><tbody></tbody></table>', bws_table_assemble( array( 'A' ), array(), '', '', '', true ) );

echo "\nNo-caption table = bare <table>, no wrapper, no classes\n";
$out = bws_table_assemble( array(), array( array( 'x' ) ) );
assert_not_contains( 'T2 no wrapper div', '<div', $out );
assert_not_contains( 'T2 no role=region', 'role="region"', $out );
assert_not_contains( 'T2 no tabindex', 'tabindex', $out );
assert_contains( 'T2 bare table (no class)', '<table><tbody>', $out );
assert_not_contains( 'T2 no bws-table class', 'class=', $out );

echo "\nHeader-less table\n";
$out = bws_table_assemble( array( '', '' ), array( array( 'a', 'b' ) ) );
assert_not_contains( 'T3 no thead', '<thead>', $out );
assert_contains( 'T3 body row', '<tbody><tr><td>a</td><td>b</td></tr></tbody>', $out );

echo "\nHeaders (bare th — scope pair deferred)\n";
$out = bws_table_assemble( array( 'Name', 'Role' ), array( array( 'Jane', 'Lead' ) ) );
assert_contains( 'T4 thead', '<thead><tr><th>Name</th><th>Role</th></tr></thead>', $out );
assert_not_contains( 'T4 no scope=col yet', 'scope=', $out );

echo "\nOne header among five columns → thead with one filled th and four blanks\n";
$out = bws_table_assemble( array( '', 'Role', '', '', '' ), array( array( 'a', 'b', 'c', 'd', 'e' ) ) );
assert_contains( 'T4b blank th keep the cell count', '<thead><tr><th></th><th>Role</th><th></th><th></th><th></th></tr></thead>', $out );

echo "\nCaption → wrapper (role=region tabindex aria-labelledby) + <caption id>\n";
$out = bws_table_assemble( array( 'Name' ), array( array( 'Jane' ) ), 'Team members', 'bws-table-caption-1' );
assert_contains( 'T5 wrapper with all three attrs', '<div role="region" tabindex="0" aria-labelledby="bws-table-caption-1">', $out );
assert_contains( 'T5 caption with id', '<caption id="bws-table-caption-1">Team members</caption>', $out );
assert_contains( 'T5 wrapper closes', '</table></div>', $out );

echo "\nCaption text but no id → no wrapper, no caption (guard)\n";
$out = bws_table_assemble( array( 'Name' ), array( array( 'Jane' ) ), 'Orphan', '' );
assert_not_contains( 'T6 no caption element', '<caption', $out );
assert_not_contains( 'T6 no wrapper', '<div', $out );

echo "\nNo caption → no wrapper, no caption, no aria-labelledby\n";
$out = bws_table_assemble( array( 'Name' ), array( array( 'Jane' ) ) );
assert_not_contains( 'T7 no caption element', '<caption', $out );
assert_not_contains( 'T7 no wrapper', '<div', $out );
assert_not_contains( 'T7 no aria-labelledby', 'aria-labelledby', $out );

echo "\nEscaping\n";
$out = bws_table_assemble(
	array( '<b>H</b>' ),
	array( array( 'a&b<c>' ) ),
	'Caption "quoted" & <script>',
	'id"with<bad'
);
assert_contains( 'T8 header escaped', '<th>&lt;b&gt;H&lt;/b&gt;</th>', $out );
assert_contains( 'T8 cell escaped', '<td>a&amp;b&lt;c&gt;</td>', $out );
assert_contains( 'T8 caption text escaped', 'Caption &quot;quoted&quot; &amp; &lt;script&gt;', $out );
assert_contains( 'T8 caption id attr escaped', '<caption id="id&quot;with&lt;bad">', $out );

echo "\nFull shape (caption + header + multi-row)\n";
$out = bws_table_assemble(
	array( 'Name', 'Role' ),
	array( array( 'Jane', 'Lead' ), array( 'Bob', 'Dev' ) ),
	'Staff',
	'cap9'
);
$expected = '<div role="region" tabindex="0" aria-labelledby="cap9">'
	. '<table>'
	. '<caption id="cap9">Staff</caption>'
	. '<thead><tr><th>Name</th><th>Role</th></tr></thead>'
	. '<tbody><tr><td>Jane</td><td>Lead</td></tr><tr><td>Bob</td><td>Dev</td></tr></tbody>'
	. '</table></div>';
assert_eq( 'T9 exact markup', $expected, $out );

// ---------------------------------------------------------------------------
// Presentation: scope class + the rule builder (bws_table_rules)
// ---------------------------------------------------------------------------

/** Build the stylesheet for tag options + per-column slot opts; scope class is named `S` for legibility. */
function style_css( array $opts, array $cols = array(), bool $has_header = true, ?array $defaults = null ): string {
	$defaults ??= array( 'color' => 'currentColor', 'opacity' => 5.0 );
	$rules      = bws_table_rules( bws_table_style_read( $opts ), array_map( 'bws_table_column_style_read', $cols ), $has_header, $defaults );
	return bws_table_rules_css( 'S', $rules );
}

$default_bg = 'var(--bws-dte-table-stripe,color-mix(in srgb,currentColor 5%,transparent))';
$grad       = static function ( string $c ): string {
	return "background-image:linear-gradient($c,$c)";
};

echo "\nHas-header is the one answer\n";
assert_eq( 'T10 a label anywhere', true, bws_table_has_header( array( '', 'x' ) ) );
assert_eq( 'T10 all blank', false, bws_table_has_header( array( '', '' ) ) );

echo "\nScope class on the <table>\n";
assert_contains( 'T11 styled table carries its class', '<table class="bws-dte-table-0123456789">', bws_table_assemble( array(), array( array( 'x' ) ), '', '', 'bws-dte-table-0123456789' ) );
assert_contains( 'T11 unstyled table carries none', '<table><tbody>', bws_table_assemble( array(), array( array( 'x' ) ), '', '', '' ) );
assert_contains( 'T11 the class rides inside the caption wrapper', '<table class="s1"><caption', bws_table_assemble( array( 'H' ), array( array( 'x' ) ), 'C', 'c1', 's1' ) );

echo "\nLayout and width\n";
assert_eq( 'T12 layout:fixed alone brings width:100%', '.S{table-layout:fixed;width:100%}', style_css( array( 'layout' => 'fixed' ) ) );
assert_eq( 'T12 layout:fixed with width:auto renders as written', '.S{table-layout:fixed;width:auto}', style_css( array( 'layout' => 'fixed', 'width' => 'auto' ) ) );
assert_eq( 'T12 layout:auto is written', '.S{table-layout:auto}', style_css( array( 'layout' => 'auto' ) ) );
assert_eq( 'T12 no layout key, no table-layout rule', '.S{width:60%}', style_css( array( 'width' => '60%' ) ) );
assert_eq( 'T12 width:var()', '.S{width:var(--w,10px)}', style_css( array( 'width' => 'var(--w, 10px)' ) ) );
assert_eq( 'T12 a refused width is nothing', '', style_css( array( 'width' => 'calc(1px + 2px)' ) ) );
assert_eq( 'T12 an unstyled table has no rules', array(), bws_table_rules( bws_table_style_read( array() ), array(), true, array( 'color' => 'currentColor', 'opacity' => 5.0 ) ) );
assert_eq( 'T12 no rules, no class', '', bws_table_style_class( array() ) );

echo "\nStripes: the header row is row 1\n";
assert_eq( 'T13 rows (even): body rows 1, 3, …', ".S tbody tr:nth-child(odd){{$grad($default_bg)}}", style_css( array( 'stripe' => 'rows' ) ) );
assert_eq( 'T13 rows,odd: the header row and body rows 2, 4, …', ".S thead tr,.S tbody tr:nth-child(even){{$grad($default_bg)}}", style_css( array( 'stripe' => 'rows,odd' ) ) );
assert_eq( 'T13 cols run through the header cell', ".S :is(th,td):nth-child(even){{$grad($default_bg)}}", style_css( array( 'stripe' => 'cols' ) ) );
assert_eq( 'T13 cols,odd', ".S :is(th,td):nth-child(odd){{$grad($default_bg)}}", style_css( array( 'stripe' => 'cols,odd' ) ) );
assert_eq( 'T13 headerless rows counts from the first emitted row (even = 2, 4)', ".S tbody tr:nth-child(even){{$grad($default_bg)}}", style_css( array( 'stripe' => 'rows' ), array(), false ) );
assert_eq( 'T13 headerless rows,odd (1, 3)', ".S tbody tr:nth-child(odd){{$grad($default_bg)}}", style_css( array( 'stripe' => 'rows,odd' ), array(), false ) );
assert_eq(
	'T13 rows and cols both paint, as gradients so they compose where they cross',
	".S tbody tr:nth-child(odd){{$grad($default_bg)}}.S :is(th,td):nth-child(even){{$grad($default_bg)}}",
	style_css( array( 'stripe' => 'rows;cols' ) )
);

echo "\nStripe color forms\n";
assert_eq( 'T14 color alone takes the default opacity', ".S tbody tr:nth-child(odd){{$grad('color-mix(in srgb,#eee 5%,transparent)')}}", style_css( array( 'stripe' => 'rows,#eee' ) ) );
assert_eq( 'T14 color and opacity', ".S tbody tr:nth-child(odd){{$grad('color-mix(in srgb,#eee 40%,transparent)')}}", style_css( array( 'stripe' => 'rows,#eee,40%' ) ) );
assert_eq( 'T14 opacity alone mixes the default color', ".S tbody tr:nth-child(odd){{$grad('color-mix(in srgb,currentColor 40%,transparent)')}}", style_css( array( 'stripe' => 'rows,40%' ) ) );
assert_eq( 'T14 100% is a solid stripe', ".S tbody tr:nth-child(odd){{$grad('color-mix(in srgb,#eee 100%,transparent)')}}", style_css( array( 'stripe' => 'rows,#eee,100%' ) ) );
assert_eq( 'T14 a color with its own alpha ignores the opacity', ".S tbody tr:nth-child(odd){{$grad('rgba(0,0,0,.2)')}}", style_css( array( 'stripe' => 'rows,rgba(0,0,0,.2),90%' ) ) );
assert_eq( 'T14 8-digit hex ignores the opacity', ".S tbody tr:nth-child(odd){{$grad('#11223344')}}", style_css( array( 'stripe' => 'rows,#11223344,90%' ) ) );
assert_eq( 'T14 var() color', ".S tbody tr:nth-child(odd){{$grad('color-mix(in srgb,var(--c) 5%,transparent)')}}", style_css( array( 'stripe' => 'rows,var(--c)' ) ) );
assert_eq( 'T14 a fractional opacity', ".S tbody tr:nth-child(odd){{$grad('color-mix(in srgb,#eee 7.5%,transparent)')}}", style_css( array( 'stripe' => 'rows,#eee,7.5%' ) ) );
assert_eq(
	'T14 the saved defaults feed the fallback',
	".S tbody tr:nth-child(odd){{$grad('var(--bws-dte-table-stripe,color-mix(in srgb,#123456 10%,transparent))')}}",
	style_css( array( 'stripe' => 'rows' ), array(), true, array( 'color' => '#123456', 'opacity' => 10.0 ) )
);
assert_eq(
	'T14 a saved default with its own alpha is not mixed',
	".S tbody tr:nth-child(odd){{$grad('var(--bws-dte-table-stripe,#12345678)')}}",
	style_css( array( 'stripe' => 'rows' ), array(), true, array( 'color' => '#12345678', 'opacity' => 10.0 ) )
);
$hostile = style_css( array( 'stripe' => 'rows,red}body{display:none}' ) );
assert_eq( 'T14 a color in no allowed form falls back to the default', ".S tbody tr:nth-child(odd){{$grad($default_bg)}}", $hostile );
assert_not_contains( 'T14 and does not appear in the block in any form', 'display', $hostile );
assert_not_contains( 'T14 not even its fragments', 'body{', $hostile );

echo "\nTable-level text\n";
assert_eq(
	'T15 base on every cell, the header override on header cells',
	'.S :is(th,td){color:#333;font-weight:normal}.S thead th{color:#000;font-weight:bold}',
	style_css( array( 'color' => '#333;header,#000', 'fontWeight' => 'normal;header,bold' ) )
);
assert_eq( 'T15 a base-unset value is just the override', '.S thead th{text-transform:uppercase}', style_css( array( 'textTransform' => 'header,uppercase' ) ) );
assert_eq( 'T15 headerless: the header override has nothing to style', '.S :is(th,td){color:#333}', style_css( array( 'color' => '#333;header,#000' ), array(), false ) );
assert_eq( 'T15 a refused value is as if absent', '', style_css( array( 'fontWeight' => 'heavy', 'color' => 'red}' ) ) );

echo "\nColumns\n";
assert_eq( 'T16 column width', '.S :is(th,td):nth-child(2){width:8rem}', style_css( array(), array( array(), array( 'width' => '8rem' ) ) ) );
assert_eq( 'T16 a bare column textAlign covers the header: one rule for both', '.S :is(th,td):nth-child(1){text-align:right}', style_css( array(), array( array( 'textAlign' => 'right' ) ) ) );
assert_eq( 'T16 headerless: body only', '.S tbody td:nth-child(1){text-align:right}', style_css( array(), array( array( 'textAlign' => 'right' ) ), false ) );
assert_eq( 'T16 a bare column color is body-only', '.S tbody td:nth-child(1){color:#c00}', style_css( array(), array( array( 'color' => '#c00' ) ) ) );
assert_eq( 'T16 a column Header reaches the th alone', '.S thead th:nth-child(1){font-weight:bold}', style_css( array(), array( array( 'fontWeight' => 'header,bold' ) ) ) );
assert_eq(
	'T16 column base and Header differ: split rules',
	'.S tbody td:nth-child(1){text-align:right}.S thead th:nth-child(1){text-align:center}',
	style_css( array(), array( array( 'textAlign' => 'right;header,center' ) ) )
);
assert_eq( 'T16 a column that restates the table adds nothing', '.S :is(th,td){text-align:right}', style_css( array( 'textAlign' => 'right' ), array( array( 'textAlign' => 'right' ) ) ) );
assert_eq(
	'T16 width and text share a column selector',
	'.S :is(th,td):nth-child(1){width:6rem;text-align:left}',
	style_css( array(), array( array( 'width' => '6rem', 'textAlign' => 'left' ) ) )
);
assert_eq( 'T16 a column header override beats the table header', '.S thead th{color:#000}.S thead th:nth-child(2){color:#c00}', style_css( array( 'color' => 'header,#000' ), array( array(), array( 'color' => 'header,#c00' ) ) ) );

echo "\nThe scope class: once per id\n";
$rules_a = bws_table_rules( bws_table_style_read( array( 'layout' => 'fixed', 'stripe' => 'rows' ) ), array(), true, array( 'color' => 'currentColor', 'opacity' => 5.0 ) );
$rules_b = bws_table_rules( bws_table_style_read( array( 'layout' => 'fixed', 'stripe' => 'rows' ) ), array(), true, array( 'color' => 'currentColor', 'opacity' => 5.0 ) );
$rules_c = bws_table_rules( bws_table_style_read( array( 'layout' => 'fixed', 'stripe' => 'cols' ) ), array(), true, array( 'color' => 'currentColor', 'opacity' => 5.0 ) );
assert_eq( 'T17 identical tables share one id', bws_table_style_class( $rules_a ), bws_table_style_class( $rules_b ) );
assert_eq( 'T17 differing tables do not', true, bws_table_style_class( $rules_a ) !== bws_table_style_class( $rules_c ) );
assert_eq( 'T17 shape', 1, preg_match( '/^bws-dte-table-[0-9a-f]{10}$/', bws_table_style_class( $rules_a ) ) );
assert_eq( 'T17 the id is a function of the built rules, not the saved defaults it did not use', bws_table_style_class( $rules_a ), bws_table_style_class( bws_table_rules( bws_table_style_read( array( 'layout' => 'fixed', 'stripe' => 'rows' ) ), array(), true, array( 'color' => 'currentColor', 'opacity' => 5.0 ) ) ) );

echo "\nNothing inline, nothing structural\n";
$everything = style_css(
	array( 'layout' => 'fixed', 'width' => '90%', 'color' => '#111', 'fontSize' => '1rem', 'fontWeight' => 'normal;header,bold', 'fontStyle' => 'italic', 'textAlign' => 'left', 'textTransform' => 'header,uppercase', 'stripe' => 'rows;cols' ),
	array( array( 'width' => '5rem', 'textAlign' => 'right', 'color' => '#c00' ), array( 'fontWeight' => 'header,900' ) )
);
assert_not_contains( 'T18 no inline style attribute', 'style=', $everything );
assert_not_contains( 'T18 no display rule', 'display', $everything );
assert_not_contains( 'T18 no markup', '<', $everything );
assert_not_contains( 'T18 no colgroup', 'col', str_replace( array( 'color', 'cols', 'currentColor', 'color-mix' ), '', $everything ) );

echo "\nBorders: the key touches only the lines it names\n";
$outer0 = '.S :is(th,td):first-child{border-left-width:0}.S :is(th,td):last-child{border-right-width:0}.S thead th,.S:not(:has(> thead)) tbody tr:first-child td{border-top-width:0}.S tbody tr:last-child td{border-bottom-width:0}';
$cols0  = '.S :is(th,td):not(:first-child){border-left-width:0}';
assert_eq( 'T19 a color-only base recolors and draws nothing', '.S,.S :is(th,td){border-color:#ccc}', style_css( array( 'border' => '#ccc' ) ) );
assert_eq( 'T19 opacity with no color rides currentColor', '.S,.S :is(th,td){border-color:color-mix(in srgb,currentColor 40%,transparent)}', style_css( array( 'border' => '40%' ) ) );
assert_eq( 'T19 a color with its own alpha ignores the opacity', '.S,.S :is(th,td){border-color:rgba(0,0,0,.5)}', style_css( array( 'border' => 'rgba(0,0,0,.5),40%' ) ) );
assert_eq( 'T19 outer,none: the outer line off, nothing else touched', '.S{border-width:0}' . $outer0, style_css( array( 'border' => 'outer,none' ) ) );
assert_eq( 'T19 rows alone: one boundary, both edges owned', '.S tbody tr:not(:last-child) td{border-bottom-width:1px;border-bottom-style:solid}.S tbody tr:not(:first-child) td{border-top-width:0}', style_css( array( 'border' => 'rows,solid' ) ) );
assert_eq( 'T19 a bare head sets nothing, so it draws nothing', '', style_css( array( 'border' => 'rows' ) ) );
assert_eq( 'T19 cols alone', '.S :is(th,td):not(:last-child){border-right-width:2px;border-right-style:dashed}' . $cols0, style_css( array( 'border' => 'cols,2px,dashed' ) ) );
assert_eq( 'T19 header alone', '.S thead th{border-bottom-width:2px;border-bottom-style:solid}.S thead + tbody tr:first-child td{border-top-width:0}', style_css( array( 'border' => 'header,2px' ) ) );
$all = style_css( array( 'border' => '2px' ) );
assert_contains( 'T19 a base width draws all four: outer', '.S{border-width:2px;border-style:solid}', $all );
assert_contains( 'T19 a base width draws all four: cols', '.S :is(th,td):not(:last-child){border-right-width:2px;border-right-style:solid}', $all );
assert_contains( 'T19 a base width draws all four: header', '.S thead th{border-bottom-width:2px;border-bottom-style:solid}', $all );
assert_contains( 'T19 a base width draws all four: rows', '.S tbody tr:not(:last-child) td{border-bottom-width:2px;border-bottom-style:solid}', $all );
$some = style_css( array( 'border' => 'none;rows,2px' ) );
assert_contains( 'T19 base none: every line off', '.S{border-width:0}', $some );
assert_contains( 'T19 base none: cols off', '.S :is(th,td):not(:last-child){border-right-width:0}', $some );
assert_contains( 'T19 base none + rows: rows drawn again, solid', '.S tbody tr:not(:last-child) td{border-bottom-width:2px;border-bottom-style:solid}', $some );
assert_contains( 'T19 a line override keeps the base color', '.S,.S :is(th,td){border-color:red}', style_css( array( 'border' => '3px,dashed,red;rows,dotted' ) ) );
assert_contains( 'T19 a line override changes only its own fields', '.S tbody tr:not(:last-child) td{border-bottom-width:3px;border-bottom-style:dotted}', style_css( array( 'border' => '3px,dashed,red;rows,dotted' ) ) );
assert_not_contains( 'T19 border never forces the model', 'border-collapse', style_css( array( 'border' => '2px' ) ) );
$rad = style_css( array( 'border' => 'outer,2px', 'borderRadius' => 'outer,12px' ) );
assert_contains( 'T19 radius forces the separate model', '.S{border-collapse:separate;border-spacing:0;border-radius:12px}', $rad );
assert_contains( 'T19 radius clears the doubled edges under a header, and not the first row without one', '.S thead + tbody td,.S:not(:has(> thead)) tbody tr:not(:first-child) td{border-top-width:0}', $rad );
assert_contains( 'T19 the clip is the inner radius', '.S tbody{clip-path:inset(0 round max(0px,calc(12px - 2px)))}', $rad );
assert_not_contains( 'T19 never overflow', 'overflow', $rad );
assert_contains( 'T19 radius alone clips at R', '.S thead{clip-path:inset(0 round 12px 12px 0 0)}', style_css( array( 'borderRadius' => 'outer,12px' ) ) );
assert_contains( 'T19 an outer line turned off clips at R', '.S tbody{clip-path:inset(0 round 1em)}', style_css( array( 'border' => 'outer,none', 'borderRadius' => 'outer,1em' ) ) );
assert_eq( 'T19 a bare radius is reserved: nothing', '', style_css( array( 'borderRadius' => '8px' ) ) );
assert_eq( 'T19 an unbalanced border is nothing', '', style_css( array( 'border' => 'rows,rgba(0,0' ) ) );
assert_not_contains( 'T19 a hostile value never reaches the sheet', 'body', style_css( array( 'border' => 'red}body{x:y' ) ) );

// ---------------------------------------------------------------------------
echo "\n";
if ( $failures > 0 ) {
	echo "FAILED {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED {$count}/{$count}\n";
exit( 0 );
