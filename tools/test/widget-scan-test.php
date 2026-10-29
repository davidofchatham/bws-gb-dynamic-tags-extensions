<?php
/**
 * Standalone unit harness for the block-widget scan rows (FW-73, ticket 02).
 *
 * Three seams, all pure once WordPress's option reads are stubbed:
 *   - bws_block_widget_rows()          the ONE enumeration of block widget instances (number =>
 *                                      content), shared by the core-block survey runner and the scan.
 *   - bws_block_widget_placements()    which active sidebar holds each instance.
 *   - TagConverter::scan_widgets()     one scan row per instance that holds a migratable tag.
 * Plus the allowlist rebuild, which must take a widget row unchanged, and the LOCATIONS a scan row
 * carries (FW-73 ticket 05): block type and position, read off the located walk.
 *
 * REQUIRES THE REAL WORDPRESS BLOCK PARSER, because scan rows now ask the located walk (see
 * located-walk-test.php for the $BWS_WP_CORE_DIR rule); it FAILS rather than skips when it is missing.
 *
 * The real files are loaded; only get_option / update_option / admin_url are stubbed, and the
 * migration entries are synthetic so a registration change elsewhere cannot move this file.
 *
 * WHAT A PASS HERE DOES NOT PROVE. That scan() appends these rows after the post rows, that
 * the Migration Tool draws them, and that the survey runner's output is unchanged on a real
 * database: those are the testbed's (core-block-test-matrix.md §CB6, the Scan Content button).
 *
 * Run:  php tools/test/widget-scan-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

$GLOBALS['ws_options'] = array();

if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) { return $GLOBALS['ws_options'][ $name ] ?? $default; }
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) { $GLOBALS['ws_options'][ $name ] = $value; return true; }
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) { return $value; }
}
function bws_gb_tag_name_collisions(): array {
	return array();
}

$core   = rtrim( (string) ( getenv( 'BWS_WP_CORE_DIR' ) ?: 'D:/Resources/WordPress/wordpress' ), '/\\' );
$parser = $core . '/wp-includes/class-wp-block-parser.php';
if ( ! is_file( $parser ) ) {
	echo "  FAIL the real WP block parser is at {$parser} (set BWS_WP_CORE_DIR)\n";
	exit( 1 );
}
require $parser;
function parse_blocks( $content ) {
	return ( new WP_Block_Parser() )->parse( $content );
}

$root = dirname( __DIR__, 2 );
require $root . '/includes/helpers/slot-fold.php';
require $root . '/includes/helpers/serialization-order.php';
require $root . '/includes/helpers/gb-output-boundary.php';
require $root . '/includes/helpers/converter-ownership.php';
require $root . '/includes/helpers/core-block-survey.php';
require $root . '/includes/classes/class-migration-registry.php';
require $root . '/includes/classes/admin/class-tag-converter.php';

use BWS\DynamicTags\Admin\TagConverter;
use BWS\DynamicTags\MigrationRegistry;

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
	echo '       expected: ' . var_export( $expected, true ) . "\n";
	echo '       actual:   ' . var_export( $actual, true ) . "\n";
}

MigrationRegistry::register( array(
	'type'               => 'tag',
	'match_tag'          => 'ws_old',
	'new_tag'            => 'ws_new',
	'transform_callback' => static fn ( string $s ): string => str_replace( '{{ws_old', '{{ws_new', $s ),
) );
MigrationRegistry::register( array(
	'type'               => 'option',
	'label'              => 'ws legacy option',
	'match_tag'          => 'ws_base',
	'match_options'      => array( 'legacy' ),
	'transform_callback' => static fn ( string $s ): string => str_replace( 'legacy:', 'key:', $s ),
) );

$old  = '<!-- wp:paragraph --><p>{{ws_old use:x}}</p><!-- /wp:paragraph -->';
$opt  = '<!-- wp:paragraph --><p>{{ws_base legacy:v}}</p><!-- /wp:paragraph -->';
$only = '<!-- wp:paragraph --><p>{{ws_only_widget_old}}</p><!-- /wp:paragraph -->';
$ok   = '<!-- wp:paragraph --><p>nothing here {{unrelated}}</p><!-- /wp:paragraph -->';

echo "WS1 - one enumeration of block widget instances\n";

$GLOBALS['ws_options']['widget_block'] = array(
	'_multiwidget' => 1,
	9001           => array( 'content' => $old, 'extra' => 'kept elsewhere' ),
	3              => array( 'content' => $ok ),
	5              => array( 'title' => 'no content key' ),
	6              => array( 'content' => 42 ),
	7              => 'not an instance',
);
assert_eq( 'WS1.1 number => content, instances only, ascending',
	array( 3 => $ok, 9001 => $old ), bws_block_widget_rows() );

unset( $GLOBALS['ws_options']['widget_block'] );
assert_eq( 'WS1.2 no option stored: no instances', array(), bws_block_widget_rows() );

echo "\nWS2 - which active sidebar holds an instance\n";

assert_eq( 'WS2.1 active instances map to their sidebar; inactive, orphaned and unassigned are absent',
	array( 3 => 'sidebar-1', 12 => 'footer' ),
	bws_block_widget_placements( array(
		'wp_inactive_widgets'   => array( 'block-9001', 'block-4' ),
		'sidebar-1'             => array( 'text-2', 'block-3' ),
		'footer'                => array( 'block-12' ),
		'orphaned_widgets_1'    => array( 'block-8' ),
		'array_version'         => 3,
	) ) );

echo "\nWS3 - scan rows\n";

$rows = TagConverter::scan_widgets(
	array( 3 => $old, 4 => $ok, 9001 => $opt ),
	array( 3 => 'Primary Sidebar' )
);

assert_eq( 'WS3.1 one row per instance holding a migratable tag; a clean instance has none', array( 3, 9001 ), array_column( $rows, 'widget_number' ) );
assert_eq( 'WS3.2 every row carries the widget discriminator', array( 'widget', 'widget' ), array_column( $rows, 'kind' ) );
assert_eq( 'WS3.3 label', 'Block widget #3', $rows[0]['post_title'] );
assert_eq( 'WS3.4 an assigned instance shows its sidebar', 'Primary Sidebar', $rows[0]['placement'] );
assert_eq( 'WS3.5 an unassigned instance is marked inactive, and still listed', array( 'Inactive', true, false ), array( $rows[1]['placement'], $rows[1]['inactive'], $rows[0]['inactive'] ) );
assert_eq( 'WS3.6 edit link goes to the Widgets screen', 'https://example.test/wp-admin/widgets.php', $rows[0]['edit_url'] );
assert_eq( 'WS3.7 no revision note: the row has no revision-support flag', false, array_key_exists( 'has_revision_support', $rows[0] ) );
assert_eq( 'WS3.8 string id the migrate path will accept', 'widget:3', $rows[0]['row_id'] );
assert_eq( 'WS3.9 a widget row has no post id for a post-only consumer to mistake for one', false, array_key_exists( 'post_id', $rows[0] ) );

$d = $rows[0]['deprecated_tags'][0];
assert_eq( 'WS3.10 deprecated tag keys match a post row', array( 'tag', 'has_migration', 'status', 'reason', 'exempt', 'count', 'sample' ), array_keys( $d ) );
assert_eq( 'WS3.11 tag, count and verdict', array( 'ws_old', 1, 'convert' ), array( $d['tag'], $d['count'], $d['status'] ) );
assert_eq( 'WS3.12 option migrations ride the same key as a post row', array( array( 'tag' => 'ws_base', 'label' => 'ws legacy option' ) ), $rows[1]['option_migrations'] );

assert_eq( 'WS3.13 nothing to scan: no rows', array(), TagConverter::scan_widgets( array(), array() ) );

echo "\nWS4 - the allowlist rebuild takes a widget row unchanged\n";

MigrationRegistry::register( array(
	'type'               => 'tag',
	'match_tag'          => 'ws_only_widget_old',
	'new_tag'            => 'ws_only_widget_new',
	'transform_callback' => static fn ( string $s ): string => str_replace( 'ws_only_widget_old', 'ws_only_widget_new', $s ),
) );
$widget_rows = TagConverter::scan_widgets( array( 21 => $only ), array() );
$allow       = TagConverter::rebuild_allowlist_from_scan( $widget_rows );
assert_eq( 'WS4.1 a tag only a widget uses stays listed', array( 'ws_only_widget_old' ), $allow['tags'] );

$allow = TagConverter::rebuild_allowlist_from_scan( TagConverter::scan_widgets( array( 9001 => $opt ), array() ) );
assert_eq( 'WS4.2 an option label only a widget uses stays listed', array( 'ws legacy option' ), $allow['option_labels'] );

echo "\nWS5 - the survey runner reads widgets through the shared enumeration\n";

$survey = file_get_contents( $root . '/includes/helpers/core-block-survey.php' );
assert_eq( 'WS5.1 the runner calls bws_block_widget_rows()', true, 1 === preg_match( '/foreach \( bws_block_widget_rows\(\)/', $survey ) );
assert_eq( 'WS5.2 and the option is read in exactly one place, the enumeration itself', 1, substr_count( $survey, "get_option( 'widget_block'" ) );

echo "\nWS6 - a scan row says where to look: block type and position\n";

$many = '<!-- wp:group --><div>'
	. '<!-- wp:paragraph --><p>clean</p><!-- /wp:paragraph -->'
	. '<!-- wp:paragraph --><p>{{ws_old use:x}} {{unrelated}}</p><!-- /wp:paragraph -->'
	. '<!-- wp:heading --><h2>{{ws_base legacy:v}}</h2><!-- /wp:heading -->'
	. '</div><!-- /wp:group -->';
$loc = TagConverter::scan_widgets( array( 5 => $many ), array() )[0]['locations'];
assert_eq( 'WS6.1 one entry per migratable occurrence, in document order; the live tag beside it has none',
	array(
		array( 'label' => 'core/paragraph #2', 'stored' => '{{ws_old use:x}}' ),
		array( 'label' => 'core/heading #1', 'stored' => '{{ws_base legacy:v}}' ),
	),
	$loc );
assert_eq( 'WS6.2 an option-migration occurrence is located too', 'core/heading #1', $loc[1]['label'] );
assert_eq( 'WS6.3 a tag in text outside any block has no location (and the row is still listed)', array( 8, array() ),
	array( 8, TagConverter::scan_widgets( array( 8 => '{{ws_old}}' ), array() )[0]['locations'] ) );

echo "\n";
if ( $failures ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
