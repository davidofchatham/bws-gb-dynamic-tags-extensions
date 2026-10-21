<?php
/**
 * Standalone harness for {{table}}'s presentation VALUES: the width/color/size/weight/style/
 * transform validators, the `stripe` reader, the tag-level read (`bws_table_style_read`) and the
 * generalized header/body cascade, all in includes/helpers/table-helpers.php.
 *
 * Pure: the readers split through bws_fold_split_depth() in slot-fold.php, nothing else. The rule
 * builder that turns these into CSS is pinned in table-assemble-test.php.
 *
 * Run:  php tools/test/table-style-test.php
 * Exit 0 = pass, 1 = fail.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

require __DIR__ . '/../../includes/helpers/serialization-order.php';
require __DIR__ . '/../../includes/helpers/slot-fold.php';
require __DIR__ . '/../../includes/helpers/table-helpers.php';

$failures = 0;

function check( $label, $expected, $actual ): void {
	global $failures;
	if ( $expected === $actual ) {
		echo "  ok   {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL {$label}\n       expected: " . json_encode( $expected ) . "\n       actual:   " . json_encode( $actual ) . "\n";
}

echo "width: one validator for the tag key and the column token\n";
check( 'auto', 'auto', bws_table_width_value( 'auto' ) );
foreach ( array( '100%', '8rem', '12px', '30em', '60ch', '50vw', '.5rem', '1.5em' ) as $ok ) {
	check( "unit $ok", $ok, bws_table_width_value( $ok ) );
}
check( 'unit case is forgiven', '8rem', bws_table_width_value( ' 8REM ' ) );
check( 'var()', 'var(--w)', bws_table_width_value( 'var(--w)' ) );
check( 'var() with a fallback', 'var(--w,10px)', bws_table_width_value( 'var(--w, 10px)' ) );
foreach ( array( 'calc(100% - 2rem)', 'fit-content', 'max-content', '8', '8pt', 'var(--w, var(--x))', 'var(--w;)', 'var(w)', '100% }', '' ) as $bad ) {
	check( "refused: $bad", null, bws_table_width_value( $bad ) );
}

echo "color: the strict allowlist (#30 plus currentColor)\n";
foreach ( array( '#eee', '#EEEEEE', '#eeee', '#eeeeeeee', 'rgb(0,0,0)', 'rgba(0, 0, 0, .5)', 'hsl(120 50% 50%)', 'hsla(120,50%,50%,.5)', 'rgb(0 0 0 / 50%)', 'var(--accent)', 'var(--accent, #fff)', 'red', 'RebeccaPurple', 'transparent' ) as $ok ) {
	check( "accepted: $ok", true, null !== bws_table_color_value( $ok ) );
}
check( 'currentColor canonical', 'currentColor', bws_table_color_value( 'CURRENTCOLOR' ) );
check( 'hex lowercased', '#eeeeee', bws_table_color_value( '#EEEEEE' ) );
foreach ( array( 'red}', '#eee;', 'url(x)', 'expression(1)', '</style>', '#ee', '#eeeee', 'rgb(0,0,0);x', 'rgb(0,0,0)}', 'var(--a, rgb(0,0,0))', 'notacolor', 'red /* x */', '"red"', '' ) as $bad ) {
	check( "refused: $bad", null, bws_table_color_value( $bad ) );
}
check( 'globals only where asked: refused by default', null, bws_table_color_value( 'inherit' ) );
check( 'globals where asked', 'revert-layer', bws_table_color_value( 'revert-layer', true ) );
check( 'alpha: 8-digit hex', true, bws_table_color_has_alpha( '#11223344' ) );
check( 'alpha: 4-digit hex', true, bws_table_color_has_alpha( '#1234' ) );
check( 'alpha: rgba()', true, bws_table_color_has_alpha( 'rgba(0,0,0,.5)' ) );
check( 'alpha: hsla()', true, bws_table_color_has_alpha( 'hsla(0,0%,0%,.5)' ) );
check( 'alpha: slash syntax', true, bws_table_color_has_alpha( 'rgb(0 0 0 / 50%)' ) );
check( 'no alpha: 6-digit hex', false, bws_table_color_has_alpha( '#112233' ) );
check( 'no alpha: rgb()', false, bws_table_color_has_alpha( 'rgb(0,0,0)' ) );
check( 'no alpha: var()', false, bws_table_color_has_alpha( 'var(--x)' ) );

echo "the other four text keys\n";
check( 'fontSize unit', '1.2rem', bws_table_text_value( 'fontSize', '1.2rem' ) );
check( 'fontSize keyword', 'xx-large', bws_table_text_value( 'fontSize', 'xx-large' ) );
check( 'fontSize keyword xxx-large', 'xxx-large', bws_table_text_value( 'fontSize', 'xxx-large' ) );
check( 'fontSize var()', 'var(--s)', bws_table_text_value( 'fontSize', 'var(--s)' ) );
check( 'fontSize global', 'inherit', bws_table_text_value( 'fontSize', 'inherit' ) );
check( 'fontSize refuses calc()', null, bws_table_text_value( 'fontSize', 'calc(1rem + 2px)' ) );
check( 'fontSize refuses a bare number', null, bws_table_text_value( 'fontSize', '12' ) );
check( 'fontWeight bold', 'bold', bws_table_text_value( 'fontWeight', 'bold' ) );
check( 'fontWeight 600', '600', bws_table_text_value( 'fontWeight', '600' ) );
check( 'fontWeight refuses 650', null, bws_table_text_value( 'fontWeight', '650' ) );
check( 'fontWeight refuses 1000', null, bws_table_text_value( 'fontWeight', '1000' ) );
check( 'fontStyle oblique', 'oblique', bws_table_text_value( 'fontStyle', 'oblique' ) );
check( 'fontStyle refuses bold', null, bws_table_text_value( 'fontStyle', 'bold' ) );
check( 'textTransform capitalize', 'capitalize', bws_table_text_value( 'textTransform', 'Capitalize' ) );
check( 'textTransform refuses full-width', null, bws_table_text_value( 'textTransform', 'full-width' ) );
check( 'color takes the globals', 'unset', bws_table_text_value( 'color', 'unset' ) );
check( 'textAlign still routes', 'right', bws_table_text_value( 'textAlign', 'right' ) );
check( 'an unknown key has no validator', null, bws_table_text_value( 'banana', 'red' ) );

echo "the shared head,args tokenizer\n";
check( 'items and parts', array( array( 'raw' => 'rows,odd', 'parts' => array( 'rows', 'odd' ) ), array( 'raw' => 'cols', 'parts' => array( 'cols' ) ) ), bws_head_args_items( 'rows,odd;cols' ) );
check( 'commas inside a function stay in one arg', array( 'rows', 'rgba(0,0,0,.5)', '40%' ), bws_head_args_items( 'rows,rgba(0,0,0,.5),40%' )[0]['parts'] );
check( 'unbalanced bracket is null', null, bws_head_args_items( 'rows,rgba(0,0' ) );

echo "stripe: reader\n";
function stripe( string $v ): ?array {
	$p = bws_table_stripe_parse( $v );
	if ( null !== $p ) {
		$p['flags'] = count( $p['flags'] );
	}
	return $p;
}
$even = array( 'parity' => 'even', 'color' => null, 'opacity' => null );
check( 'bare target means even', array( 'rows' => $even, 'cols' => null, 'flags' => 0 ), stripe( 'rows' ) );
check( 'both targets', array( 'rows' => $even, 'cols' => $even, 'flags' => 0 ), stripe( 'rows;cols' ) );
check( 'odd parity', array( 'rows' => array( 'parity' => 'odd', 'color' => null, 'opacity' => null ), 'cols' => null, 'flags' => 0 ), stripe( 'rows,odd' ) );
check( 'color and opacity typed by value, any order', array( 'rows' => array( 'parity' => 'odd', 'color' => '#eee', 'opacity' => 40.0 ), 'cols' => null, 'flags' => 0 ), stripe( 'rows,40%,odd,#eee' ) );
check( 'rgba() arg survives its commas', array( 'rows' => array( 'parity' => 'even', 'color' => 'rgba(0,0,0,.5)', 'opacity' => null ), 'cols' => null, 'flags' => 0 ), stripe( 'rows,rgba(0,0,0,.5)' ) );
check( 'currentColor', 'currentColor', stripe( 'rows,currentColor' )['rows']['color'] );
check( 'a bad color falls back to the default and is flagged, the item stands', array( 'rows' => $even, 'cols' => null, 'flags' => 1 ), stripe( 'rows,notacolor' ) );
check( 'a hostile color is gone', array( 'rows' => $even, 'cols' => null, 'flags' => 1 ), stripe( 'rows,red}body{x:y' ) );
check( 'opacity over 100 is refused', array( 'rows' => $even, 'cols' => null, 'flags' => 1 ), stripe( 'rows,120%' ) );
check( 'a global is not a stripe color', array( 'rows' => $even, 'cols' => null, 'flags' => 1 ), stripe( 'rows,inherit' ) );
check( 'a repeated target keeps the first', array( 'rows' => $even, 'cols' => null, 'flags' => 1 ), stripe( 'rows;rows,odd' ) );
check( 'an unknown head is dropped', array( 'rows' => null, 'cols' => $even, 'flags' => 1 ), stripe( 'banana;cols' ) );
check( 'a repeated parity keeps the first', 'odd', stripe( 'rows,odd,even' )['rows']['parity'] );
check( 'unbalanced bracket drops the key', null, stripe( 'rows,rgba(0,0' ) );
check( 'empty is nothing, not an error', array( 'rows' => null, 'cols' => null, 'flags' => 0 ), stripe( '' ) );

echo "border: reader\n";
function border( string $v ): ?array {
	$p = bws_table_border_parse( $v );
	if ( null !== $p ) {
		$p['flags'] = count( $p['flags'] );
	}
	return $p;
}
$no_lines = array( 'outer' => null, 'header' => null, 'rows' => null, 'cols' => null );
$bare     = array( 'width' => null, 'style' => null );
check( 'a head-less segment is the base, args typed by value', array( 'base' => array( 'width' => '2px', 'style' => 'dashed', 'color' => 'red', 'opacity' => 50.0 ), 'lines' => $no_lines, 'flags' => 0 ), border( 'red,50%,dashed,2px' ) );
check( 'a head segment is a line', array( 'base' => null, 'lines' => array_merge( $no_lines, array( 'rows' => array( 'width' => null, 'style' => 'dotted' ) ) ), 'flags' => 0 ), border( 'rows,dotted' ) );
check( 'none is a style, valid in the base', 'none', border( 'none;rows' )['base']['style'] );
check( 'a bare head is a line with nothing of its own', $bare, border( 'none;rows' )['lines']['rows'] );
check( 'heads and styles forgive case', array( 'width' => '2px', 'style' => 'solid' ), border( 'OUTER,2PX,SOLID' )['lines']['outer'] );
check( 'header is the text keys word', $bare, border( 'header' )['lines']['header'] );
check( 'a line takes no color: dropped and flagged, the line stands', array( 'base' => null, 'lines' => array_merge( $no_lines, array( 'outer' => array( 'width' => '2px', 'style' => null ) ) ), 'flags' => 1 ), border( 'outer,2px,red' ) );
check( 'a line takes no opacity', 1, border( 'outer,40%' )['flags'] );
check( 'two widths in one item drop the item', array( 'base' => null, 'lines' => array_merge( $no_lines, array( 'rows' => $bare ) ), 'flags' => 1 ), border( '2px,3px;rows' ) );
check( 'two styles in a line drop the line', null, border( 'rows,dotted,dashed' )['lines']['rows'] );
check( 'two colors in the base drop the base', null, border( 'red,blue' )['base'] );
check( 'a repeated line keeps the first', $bare, border( 'rows;rows,2px' )['lines']['rows'] );
check( 'a second base keeps the first', array( 'width' => '2px', 'style' => null, 'color' => null, 'opacity' => null ), border( '2px;red' )['base'] );
check( 'a bad arg is dropped and flagged, its item stands', array( 'width' => null, 'style' => 'dotted' ), border( 'banana;cols,dotted' )['lines']['cols'] );
check( 'a width takes px, em or rem only', 1, border( 'outer,2%' )['flags'] );
check( 'thin is not a width', 1, border( 'outer,thin' )['flags'] );
check( 'a hostile value is gone', null, border( 'red}body{x:y' )['base']['color'] );
check( 'an unbalanced bracket drops the key', null, border( 'rows,rgba(0,0' ) );
check( 'a global is not a border color', 1, border( 'inherit' )['flags'] );

echo "border: what it draws\n";
$lines = static fn( string $v ) => bws_table_border_lines( bws_table_border_parse( $v ) );
$l     = static fn( string $w, string $s ) => array( 'off' => 'none' === $s, 'width' => $w, 'style' => $s );
check( 'a color-only base reaches no line', $no_lines, $lines( '#ccc,40%' ) );
check( 'a base width reaches all four, style falls back to solid', array_fill_keys( BWS_TABLE_BORDER_HEADS, $l( '2px', 'solid' ) ), $lines( '2px' ) );
check( 'a base style reaches all four, width falls back to 1px', array_fill_keys( BWS_TABLE_BORDER_HEADS, $l( '1px', 'dotted' ) ), $lines( 'dotted' ) );
check( 'a line with a style alone: 1px, the rest untouched', array_merge( $no_lines, array( 'rows' => $l( '1px', 'dotted' ) ) ), $lines( 'rows,dotted' ) );
check( 'a line with a width alone: solid, the rest untouched', array_merge( $no_lines, array( 'rows' => $l( '2px', 'solid' ) ) ), $lines( 'rows,2px' ) );
check( 'a bare head under no base draws nothing: the theme', $no_lines, $lines( 'rows' ) );
check( 'a bare head under a color-only base draws nothing', $no_lines, $lines( '#ccc;rows' ) );
check( 'a bare head under a drawing base takes the base', $l( '2px', 'solid' ), $lines( '2px;rows' )['rows'] );
check( 'a line inherits the base width and its own style', $l( '3px', 'dotted' ), $lines( '3px,dashed,red;rows,dotted' )['rows'] );
check( 'base none turns every line off', $l( '1px', 'none' ), $lines( 'none;rows' )['cols'] );
check( 'a line named under base none is drawn again', $l( '1px', 'solid' ), $lines( 'none;rows' )['rows'] );
check( 'a line set to none under a drawing base is off', $l( '2px', 'none' ), $lines( '2px;outer,none' )['outer'] );
check( 'color: none set is the theme one', null, bws_table_border_color( bws_table_border_parse( '2px' )['base'] ) );
check( 'color: as written without opacity', '#ccc', bws_table_border_color( bws_table_border_parse( '#ccc' )['base'] ) );
check( 'color: opacity composes', 'color-mix(in srgb,#ccc 40%,transparent)', bws_table_border_color( bws_table_border_parse( '#ccc,40%' )['base'] ) );

echo "borderRadius: reader\n";
check( 'outer,8px', array( 'outer' => '8px', 'flags' => array() ), bws_table_border_radius_parse( 'outer,8px' ) );
check( 'var()', 'var(--r)', bws_table_border_radius_parse( 'outer,var(--r)' )['outer'] );
check( 'a bare length is reserved, dropped and flagged', array( 'outer' => null, 'flags' => array( "bare radius '8px' is reserved for cells" ) ), bws_table_border_radius_parse( '8px' ) );
check( 'no %', null, bws_table_border_radius_parse( 'outer,8%' )['outer'] );
check( 'outer with no length is flagged', 1, count( bws_table_border_radius_parse( 'outer' )['flags'] ) );
check( 'an unknown head is flagged', 1, count( bws_table_border_radius_parse( 'cells,8px' )['flags'] ) );
check( 'a repeated outer keeps the first', '8px', bws_table_border_radius_parse( 'outer,8px;outer,9px' )['outer'] );

echo "the tag-level read\n";
$read = bws_table_style_read( array(
	'layout'     => 'fixed',
	'width'      => '100%',
	'color'      => '#333;header,#000',
	'fontWeight' => 'normal;header,bold',
	'stripe'     => 'rows,odd',
) );
check( 'layout', 'fixed', $read['layout'] );
check( 'width', '100%', $read['width'] );
check( 'a text key keeps base and header', array( '#333', '#000' ), array( $read['text']['color']['base'], $read['text']['color']['header'] ) );
check( 'an unset text key is absent', false, isset( $read['text']['fontSize'] ) );
check( 'stripe parsed', 'odd', $read['stripe']['rows']['parity'] );
$read = bws_table_style_read( array( 'layout' => 'banana', 'width' => 'calc(1px)' ) );
check( 'a refused layout is as if absent', null, $read['layout'] );
check( 'a refused width is as if absent', null, $read['width'] );
check( 'nothing set reads as nothing', true, bws_table_style_is_empty( bws_table_style_read( array() ) ) );
check( 'a refused-only table reads as nothing too', true, bws_table_style_is_empty( $read ) );
check( 'a set key is not empty', false, bws_table_style_is_empty( bws_table_style_read( array( 'layout' => 'auto' ) ) ) );
check( 'a border key is not empty', false, bws_table_style_is_empty( bws_table_style_read( array( 'border' => 'rows' ) ) ) );
check( 'a radius alone is not empty', false, bws_table_style_is_empty( bws_table_style_read( array( 'borderRadius' => 'outer,4px' ) ) ) );
check( 'a bare radius reads as nothing', true, bws_table_style_is_empty( bws_table_style_read( array( 'borderRadius' => '4px' ) ) ) );
check( 'an empty border reads as nothing', null, bws_table_style_read( array( 'border' => '' ) )['border'] );

echo "the cascade, generalized\n";
$tbl = bws_table_text_parse( 'fontWeight', 'normal;header,bold' );
$col = bws_table_text_parse( 'fontWeight', 'bold' );
check( 'body: the column base beats the table', 'bold', bws_table_text_for_cell( 'fontWeight', $tbl, $col, false ) );
check( 'header: a column base does NOT cover the header for fontWeight', 'bold', bws_table_text_for_cell( 'fontWeight', $tbl, bws_table_text_parse( 'fontWeight', '300' ), true ) );
check( 'header: a column base does NOT cover the header (no table header)', 'normal', bws_table_text_for_cell( 'fontWeight', bws_table_text_parse( 'fontWeight', 'normal' ), $col, true ) );
check( 'header: the column Header wins', '700', bws_table_text_for_cell( 'fontWeight', $tbl, bws_table_text_parse( 'fontWeight', 'header,700' ), true ) );
check( 'textAlign still covers the header', 'right', bws_table_text_for_cell( 'textAlign', null, bws_table_text_parse( 'textAlign', 'right' ), true ) );

echo "the saved default pair
";
check( 'nothing saved is the built-in pair', array( 'color' => 'currentColor', 'opacity' => 5.0 ), bws_table_stripe_defaults_resolve( '', '' ) );
check( 'a valid saved pair', array( 'color' => '#123456', 'opacity' => 12.5 ), bws_table_stripe_defaults_resolve( '#123456', '12.5' ) );
check( 'the opacity may be spelled with its %', 10.0, bws_table_stripe_defaults_resolve( '', '10%' )['opacity'] );
check( 'an invalid color falls back, the valid opacity stands', array( 'color' => 'currentColor', 'opacity' => 20.0 ), bws_table_stripe_defaults_resolve( 'red}', '20' ) );
check( 'an out-of-range opacity falls back', 5.0, bws_table_stripe_defaults_resolve( '', '250' )['opacity'] );
check( 'a non-numeric opacity falls back', 5.0, bws_table_stripe_defaults_resolve( '', 'lots' )['opacity'] );
check( 'both invalid is the built-in pair', array( 'color' => 'currentColor', 'opacity' => 5.0 ), bws_table_stripe_defaults_resolve( 'url(x)', '-3' ) );

echo $failures ? "\n{$failures} FAILED\n" : "\nAll passed\n";
exit( $failures ? 1 : 0 );
