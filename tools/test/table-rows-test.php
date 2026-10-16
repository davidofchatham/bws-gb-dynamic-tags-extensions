<?php
/**
 * Standalone harness for {{table}}'s row-production refusal in includes/tags/table-tags.php:
 * bws_table_serves() (DERIVED off bws_fold_wire_vocabulary()) driven through the real
 * bws_base_read_refused() with real chain resolutions.
 *
 * Needs the REAL files (not an inline copy), so it is not "pure" in the table-assemble-test.php
 * sense. Rendered row counts are measured on the testbed (tools/test/table-test-matrix.md).
 *
 * Run:  php tools/test/table-rows-test.php
 * Exit 0 = pass, 1 = fail.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}
require_once __DIR__ . '/lib-source-registry.php';
foreach ( array( 'add_action', 'add_filter', 'do_action', 'apply_filters' ) as $fn ) {
	if ( ! function_exists( $fn ) ) {
		eval( "function {$fn}() { return func_num_args() > 1 ? func_get_arg(1) : null; }" );
	}
}

require __DIR__ . '/../../includes/tags/base-shared.php';
require __DIR__ . '/../../includes/helpers/slot-fold.php';
require __DIR__ . '/../../includes/helpers/traversal-pipeline.php';
require __DIR__ . '/../../includes/helpers/slot-fold-compile.php';
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

$serves = bws_table_serves();
$ok     = array( 'id' => 5, 'kind' => 'post' );

echo "bws_table_serves\n";
foreach ( array( 'post', 'term', 'meta_row', 'site' ) as $kind ) {
	check( "serves {$kind}", true, in_array( $kind, $serves, true ) );
}

echo "refusal over real chains\n";
$cases = array(
	'empty chain (current context)'   => array( array(), false ),
	'rows tail'                       => array( array( 'src' => 'rows,team_members' ), false ),
	'refs tail'                       => array( array( 'src' => 'refs,related_posts' ), false ),
	'terms tail'                      => array( array( 'src' => 'terms,category' ), false ),
	'site root'                       => array( array( 'src' => 'site' ), false ),
	'pinned post then rows'           => array( array( 'src' => 'post,5;rows,team_members' ), false ),
	'unknown step slug (empty kind)'  => array( array( 'src' => 'refs,a;bogus,b' ), true ),
);
foreach ( $cases as $label => $c ) {
	$res = bws_base_src_resolution( $c[0] );
	check( $label, $c[1], bws_base_read_refused( $res, $ok, $serves ) );
}

$res = bws_base_src_resolution( array() );
check( 'unresolved root refuses', true, bws_base_read_refused( $res, array( 'kind' => BWS_SOURCE_KIND_UNRESOLVED ), $serves ) );

echo $failures ? "\n{$failures} FAILED\n" : "\nall passed\n";
exit( $failures ? 1 : 0 );
