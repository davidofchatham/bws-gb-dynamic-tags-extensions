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

// The cell read's two non-assembly dependencies, shimmed to identity: the fixed read
// sanitizes and hands its text to GB's output pipeline, neither of which a cell test is about.
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) { return trim( (string) $s ); }
}
if ( ! function_exists( 'bws_gb_tag_output' ) ) {
	function bws_gb_tag_output( $v, $o = array(), $i = null ) { return $v; }
}
require __DIR__ . '/../../includes/tags/base-shared.php';
require __DIR__ . '/../../includes/helpers/slot-fold.php';
require __DIR__ . '/../../includes/helpers/traversal-pipeline.php';
require __DIR__ . '/../../includes/helpers/slot-fold-compile.php';
require __DIR__ . '/../../includes/helpers/registration-helpers.php';
require __DIR__ . '/../../includes/helpers/field-helpers.php';
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

echo "columns: folded slots off the row\n";
$opts = array(
	'src' => 'rows,team_members',
	'A'   => 'header(Name);key(name)',
	'B'   => 'src(inherit;rows,shifts);key(day)',
	'C'   => 'src(same);key(role)',
	'D'   => 'fixed(Team)',
	'E'   => 'header(Note)',
	'1-key' => 'flat_is_never_read',
);
$cols = bws_table_columns( $opts );
check( 'every configured column is kept; read-less E survives on its header alone', 5, count( $cols ) );
check( 'the `header` token is the header cell', array( 'Name', '', '', '', 'Note' ), array_column( $cols, 'header' ) );
check( 'a header-only column has no opts and renders empty', array( null, '' ), array( $cols[4]['opts'], bws_table_read_cell( array( 'kind' => 'meta_row', 'row' => array( 'name' => 'Alice' ) ), array( 'opts' => null, 'start' => false, 'steps' => array() ), null ) ) );
check( 'a read-less column with no header is still skipped', 0, count( bws_table_columns( array( 'A' => 'src(rows,x)' ) ) ) );
check( 'a whitespace-only header is no header: the read-less column is skipped', 0, count( bws_table_columns( array( 'A' => 'header(   )' ) ) ) );
check( 'a header is trimmed', 'Name', bws_table_columns( array( 'A' => 'header( Name );key(n)' ) )[0]['header'] );
check( 'a header with a chain but no read is kept, empty', array( 'X', null ), array_values( array_intersect_key( bws_table_columns( array( 'A' => 'header(X);src(refs,office)' ) )[0], array( 'header' => 1, 'opts' => 1 ) ) ) );
$after = bws_table_columns( array( 'A' => 'header(X);src(refs,office)', 'B' => 'src(same);key(f)' ) );
check( '...and never feeds the carry: a later `src(same)` carries the row, not the office', '', $after[1]['opts']['src'] );
check( 'a header with an unfinished step is kept, empty', array( 'X', null ), array_values( array_intersect_key( bws_table_columns( array( 'A' => 'header(X);src(refs)' ) )[0], array( 'header' => 1, 'opts' => 1 ) ) ) );
check( 'a header-only column starts nowhere', false, bws_table_column_start( null, '', null ) );
check( 'a bare column chain is empty (the row)', '', $cols[0]['opts']['src'] );
check( 'a path root is kept', 'inherit;rows,shifts', $cols[1]['opts']['src'] );
check( '`src(same)` carries the previous column\'s chain', 'inherit;rows,shifts', $cols[2]['opts']['src'] );
check( 'no flat era: `1-key` and the tag-level `src` never become column A', 'name', $cols[0]['opts']['key'] );
check( '`src(same)` at column A carries the row instead of skipping', '', bws_table_columns( array( 'A' => 'src(same);key(x)' ) )[0]['opts']['src'] ?? 'skipped' );

check( 'an empty root starts at the row', null, bws_table_column_start( $cols[0]['opts'], '', null ) );
check( 'an `inherit` root starts at the row', null, bws_table_column_start( $cols[1]['opts'], '', null ) );
check( 'a step-first chain starts at the row', null, bws_table_column_start( array( 'src' => 'rows,shifts' ), '', null ) );

$row = array( 'kind' => 'meta_row', 'row' => array( 'name' => 'Alice', 'role' => 'Lead', 'shifts' => array( array( 'day' => 'Mon' ), array( 'day' => 'Wed' ) ) ) );
$bare = array( 'kind' => 'meta_row', 'row' => array( 'name' => 'Bob' ) );
$cell = static function ( array $col, array $row ) {
	$col['start'] = bws_table_column_start( $col['opts'], '', null );
	$col['steps'] = bws_field_values_assemble_steps( $col['opts'] );
	return bws_table_read_cell( $row, $col, null );
};
check( 'keyed read off the row', 'Alice', $cell( $cols[0], $row ) );
check( 'a hop off the row reads every source it lands on', 'Mon, Wed', $cell( $cols[1], $row ) );
check( 'fixed text renders on a meta_row', 'Team', $cell( $cols[3], $row ) );
check( 'fixed text after a hop shows once per source', 'Team, Team', $cell( array( 'opts' => array( 'src' => 'inherit;rows,shifts', 'use' => 'fixed', 'fixed' => 'Team', 'key' => '' ) ), $row ) );
check( '...and is empty where the hop resolves nothing', '', $cell( array( 'opts' => array( 'src' => 'inherit;rows,shifts', 'use' => 'fixed', 'fixed' => 'Team', 'key' => '' ) ), $bare ) );
check( 'a pending `use(fixed)` renders empty', '', $cell( array( 'opts' => array( 'src' => '', 'use' => 'fixed', 'fixed' => '', 'key' => '' ) ), $row ) );
$carried = bws_table_columns( array( 'A' => 'fixed(Team)', 'B' => 'use(same)' ) );
check( 'a hand-written `use(same)` carries a fixed column\'s text over', array( 'fixed', 'Team' ), array( $carried[1]['opts']['use'] ?? null, $carried[1]['opts']['fixed'] ?? null ) );
check( 'a refused start renders an empty cell', '', bws_table_read_cell( $row, array( 'opts' => $cols[0]['opts'], 'start' => false, 'steps' => array() ), null ) );

echo $failures ? "\n{$failures} FAILED\n" : "\nall passed\n";
exit( $failures ? 1 : 0 );
