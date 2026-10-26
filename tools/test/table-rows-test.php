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
// The source gate names WP symbols (its own PHPDoc: never called by a pure harness). Admit all,
// so an analog case reaches the dispatcher, whose own id guards are what is under test.
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

// The analog readers the dispatcher routes to, stubbed where they live in files this harness
// does not load (base-tags.php): each echoes the kind and id it was handed, so a case pins
// the ROUTING. The user reader is the real one, over a stubbed WP lookup.
if ( ! function_exists( 'bws_try_text_post_dispatch' ) ) {
	function bws_try_text_post_dispatch( $id, $o, $i ) { return "post-{$id}"; }
}
if ( ! function_exists( 'bws_try_text_term_dispatch' ) ) {
	function bws_try_text_term_dispatch( $id, $o, $i ) { return "term-{$id}"; }
}
if ( ! function_exists( 'bws_site_resolve_value' ) ) {
	function bws_site_resolve_value( $tag, $o, $i ) { return "site-{$tag}"; }
}
if ( ! function_exists( 'get_search_query' ) ) {
	function get_search_query() { return 'x'; }
}
if ( ! function_exists( 'get_the_author_meta' ) ) {
	function get_the_author_meta( $f, $id ) { return "user-{$f}-{$id}"; }
}

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
$aligned = bws_table_columns( array( 'A' => 'header(Price);key(p);textAlign(right;header,center)', 'B' => 'header(Note);textAlign(left)', 'C' => 'key(q)', 'D' => 'key(r);textAlign(left;header,[x)' ) );
check( 'a column carries its parsed textAlign', array( 'right', 'center' ), array( $aligned[0]['style']['text']['textAlign']['base'], $aligned[0]['style']['text']['textAlign']['header'] ) );
check( 'a header-only column keeps its textAlign', 'left', $aligned[1]['style']['text']['textAlign']['base'] );
check( 'a column without the token has none', array(), $aligned[2]['style']['text'] );
check( 'an unbalanced inner bracket drops the key, not the column', array( 'r', array() ), array( $aligned[3]['opts']['key'], $aligned[3]['style']['text'] ) );
$styled = bws_table_columns( array( 'A' => 'key(p);width(8rem);color(#c00);fontWeight(header,bold)', 'B' => 'key(q);width(calc(1px + 2px))', 'C' => 'key(r);width(var(--w,10px))' ) );
check( 'a column carries its width', '8rem', $styled[0]['style']['width'] );
check( 'a column carries its other text tokens', array( '#c00', null, null, 'bold' ), array( $styled[0]['style']['text']['color']['base'], $styled[0]['style']['text']['color']['header'], $styled[0]['style']['text']['fontWeight']['base'], $styled[0]['style']['text']['fontWeight']['header'] ) );
check( 'a refused width is as if absent', null, $styled[1]['style']['width'] );
check( 'a var() width survives the slot grammar brackets', 'var(--w,10px)', $styled[2]['style']['width'] );
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

echo "analog columns: dispatch off the resolved source\n";
$title = array( 'src' => '', 'use' => 'title', 'key' => '' );
$acell = static fn( array $source, array $opts = array() ) => bws_table_read_cell( $source, array( 'opts' => $opts + $title, 'start' => null, 'steps' => array() ), null );
check( 'a post row reads its post analog', 'post-7', $acell( array( 'kind' => 'post', 'id' => 7 ) ) );
check( 'a term row reads its term name', 'term-9', $acell( array( 'kind' => 'term', 'id' => 9 ) ) );
check( 'a user row reads its display name', 'user-display_name-3', $acell( array( 'kind' => 'user', 'id' => 3 ) ) );
check( 'a site row reads its site analog through the text tag', 'site-text', $acell( array( 'kind' => 'site' ) ) );
check( 'a meta_row refuses an analog and does not fall through to the ambient entity', '', $acell( array( 'kind' => 'meta_row', 'row' => array( 'name' => 'Alice' ) ) ) );
check( 'a query_context row reads its canonical heading (no id, so not routed by identity)', 'Search Results for &#8220;x&#8221;', $acell( array( 'kind' => 'query_context', 'sub' => 'search', 'payload' => array() ) ) );
check( 'an id-less entity row is empty', '', $acell( array( 'kind' => 'term', 'id' => 0 ) ) );
check( 'a user row has no permalink analog: `use:permalink` is empty', '', $acell( array( 'kind' => 'user', 'id' => 3 ), array( 'use' => 'permalink' ) ) );

echo "inline-only host refusal (the classification owner decides which blocks)\n";
// The refusal is the callback's FIRST act: an instance that trips on any read proves it. A host
// the guard lets through reads `$instance->context` at once (the contrast); an inline-only host
// returns '' without touching the instance, a captioned table included.
$trap = new class() {
	public function __get( $name ) { throw new RuntimeException( "read {$name}" ); }
};
$reaches = static function ( $block ) use ( $trap ): bool {
	try {
		bws_table_callback( array( 'src' => 'site', 'A' => 'key(name)' ), $block, $trap );
	} catch ( RuntimeException $e ) {
		return true;
	}
	return false;
};
check( 'a container host proceeds past the guard (contrast)', true, $reaches( array( 'blockName' => 'generateblocks/element' ) ) );
check( 'a core block outside the classified set proceeds', true, $reaches( array( 'blockName' => 'core/group' ) ) );
check( 'no block array at all proceeds', true, $reaches( null ) );
foreach ( array( 'core/paragraph', 'core/heading', 'core/list-item', 'core/table', 'core/button' ) as $host ) {
	check( "{$host} host returns '' first", '', bws_table_callback( array( 'src' => 'site', 'A' => 'key(name)' ), array( 'blockName' => $host ), $trap ) );
	check( "{$host} host returns '' first for a captioned table", '', bws_table_callback( array( 'src' => 'site', 'A' => 'key(name)', 'caption' => 'Staff' ), array( 'blockName' => $host ), $trap ) );
}
echo $failures ? "\n{$failures} FAILED\n" : "\nall passed\n";
exit( $failures ? 1 : 0 );
