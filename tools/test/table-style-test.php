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
check( 'an unknown key has no validator', null, bws_table_text_value( 'border', 'red' ) );

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
