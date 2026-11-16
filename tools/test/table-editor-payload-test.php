<?php
/**
 * Standalone harness for the {{table}} editor-preview style payload: the `data-*` attribute the
 * preview replacement carries so the editor can build the table's stylesheet itself
 * (assets/js/table-editor-style.js), because GenerateBlocks 2.5.1 runs kses over every preview
 * replacement and kses drops a `<style>` tag.
 *
 * Needs the REAL files (table-helpers.php, table-tags.php), so it is not "pure" in the
 * table-assemble-test.php sense. What reaches the editor DOM through the REST route is measured
 * on the testbed (tools/test/table-test-matrix.md, TE6).
 *
 * The REST gate reads REST_REQUEST, which cannot be undefined again, so the gated cases run in a
 * child process of this same file (`--rest`).
 *
 * Run:  php tools/test/table-editor-payload-test.php
 * Exit 0 = pass, 1 = fail.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

$in_rest = in_array( '--rest', $argv ?? array(), true );
if ( $in_rest ) {
	define( 'REST_REQUEST', true );
}

if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
}
// The settings page, reduced to the one read the table asks: the built-in stripe default (no saved
// setting), through the real resolver. Declared first so the registry lib keeps it.
eval( 'namespace BWS\DynamicTags\Admin; class SettingsPage {
	public static function is_modifier_enabled( $key ) { return true; }
	public static function get_table_stripe_defaults() { return \bws_table_stripe_defaults_resolve( "", "" ); }
}' );
require_once __DIR__ . '/lib-source-registry.php';
foreach ( array( 'add_action', 'add_filter', 'do_action', 'apply_filters' ) as $fn ) {
	if ( ! function_exists( $fn ) ) {
		eval( "function {$fn}() { return func_num_args() > 1 ? func_get_arg(1) : null; }" );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) { return trim( (string) $s ); }
}
if ( ! function_exists( 'bws_gb_tag_output' ) ) {
	function bws_gb_tag_output( $v, $o = array(), $i = null ) { return $v; }
}
function bws_is_source_usable( array $source ) { return true; }
require __DIR__ . '/../../includes/tags/base-shared.php';
require __DIR__ . '/../../includes/helpers/serialization-order.php';
require __DIR__ . '/../../includes/helpers/slot-fold.php';
require __DIR__ . '/../../includes/helpers/table-helpers.php';
require __DIR__ . '/../../includes/helpers/traversal-pipeline.php';
require __DIR__ . '/../../includes/helpers/slot-fold-compile.php';
require __DIR__ . '/../../includes/helpers/registration-helpers.php';
require __DIR__ . '/../../includes/helpers/field-helpers.php';
require __DIR__ . '/../../includes/helpers/link-helpers.php';
require __DIR__ . '/../../includes/tags/table-tags.php';

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

// The real column records and the rendered table, under a styled and an unstyled tag. Both modes
// run the same inputs; only the REST gate differs.
$styled_opts = array( 'src' => 'site', 'A' => 'header(Name);key(p);width(8rem)', 'B' => 'key(q)', 'stripe' => 'rows' );
$bare_opts   = array( 'src' => 'site', 'A' => 'header(Name);key(p)', 'B' => 'key(q)' );
$matrix      = array( array( 'x', 'y' ) );
$attr_re     = '/<table class="(bws-dte-table-[0-9a-f]{10})" data-bws-table-style="([^"]*)">/';

if ( $in_rest ) {
	echo "editor preview (REST_REQUEST): the table carries its payload, never a <style>\n";
	$html = bws_table_html( $styled_opts, bws_table_columns( $styled_opts ), $matrix, true );
	check( 'no <style> tag in the replacement', false, str_contains( $html, '<style' ) );
	check( 'the scope class and the payload attribute sit on <table>', 1, preg_match( $attr_re, $html, $m ) );
	check(
		'the payload is the raw tokens of this table',
		'{"t":{"stripe":"rows"},"c":[{"width":"8rem"},{}],"h":true,"s":{"color":"currentColor","opacity":5}}',
		html_entity_decode( $m[2] ?? '', ENT_QUOTES )
	);
	$html = bws_table_html( $bare_opts, bws_table_columns( $bare_opts ), $matrix, true );
	check( 'an unstyled table carries no payload', false, str_contains( $html, 'data-bws-table-style' ) );
	echo $failures ? "\n{$failures} FAILED\n" : "\nall passed\n";
	exit( $failures ? 1 : 0 );
}

echo "columns keep their raw style tokens\n";
$cols = bws_table_columns( array( 'src' => 'site', 'A' => 'key(p);width(8rem);color(#c00);fontWeight(header,bold)' ) );
check( 'width, then text keys in vocabulary order', array( 'width' => '8rem', 'color' => '#c00', 'fontWeight' => 'header,bold' ), $cols[0]['style_src'] ?? null );

echo "front end (no REST_REQUEST): no payload, no <style>\n";
$html = bws_table_html( $styled_opts, bws_table_columns( $styled_opts ), $matrix );
check( 'styled table has its scope class', 1, preg_match( '/<table class="bws-dte-table-[0-9a-f]{10}">/', $html ) );
check( 'no payload attribute', false, str_contains( $html, 'data-bws-table-style' ) );
check( 'no <style> tag', false, str_contains( $html, '<style' ) );

echo "editor preview, driven in a REST_REQUEST child process\n";
exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --rest 2>&1', $child, $child_status );
echo implode( "\n", $child ) . "\n";
if ( 0 !== $child_status ) {
	$failures++;
}

echo "payload: the raw style tokens, nothing parsed\n";
$stripe  = array( 'color' => 'currentColor', 'opacity' => 5.0 );
$options = array( 'src' => 'site', 'A' => 'header(N);key(a)', 'width' => '100%', 'stripe' => 'rows', 'textAlign' => 'center;right', 'caption' => 'Staff' );
$columns = array(
	array( 'header' => 'N', 'style_src' => array( 'width' => '30%', 'fontWeight' => 'bold' ) ),
	array( 'header' => '', 'style_src' => array() ),
);
check(
	'table keys, column keys, header flag and stripe pair as one JSON string',
	'{"t":{"width":"100%","stripe":"rows","textAlign":"center;right"},"c":[{"width":"30%","fontWeight":"bold"},{}],"h":true,"s":{"color":"currentColor","opacity":5}}',
	bws_table_editor_payload( $options, $columns, true, $stripe )
);
check(
	'a column record without style_src (the placeholder column) is an empty object',
	'{"t":{},"c":[{}],"h":false,"s":{"color":"currentColor","opacity":5}}',
	bws_table_editor_payload( array(), array( array( 'header' => '', 'style' => array(), 'text' => 'No columns' ) ), false, $stripe )
);
check(
	'a non-style key (caption, src, a slot) never rides along',
	false,
	str_contains( bws_table_editor_payload( $options, $columns, true, $stripe ), 'Staff' )
);

echo $failures ? "\n{$failures} FAILED\n" : "\nall passed\n";
exit( $failures ? 1 : 0 );
