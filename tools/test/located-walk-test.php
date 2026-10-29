<?php
/**
 * Standalone harness for the located walk (FW-73, ticket 04, spec decisions 8 and 9) in
 * includes/helpers/core-block-survey.php: one record per tag occurrence, carrying its base
 * category, its Convertible flag, its block type, its container, its ordinal and the stored string.
 *
 * REQUIRES THE REAL WORDPRESS BLOCK PARSER (see core-block-survey-test.php for why; same
 * $BWS_WP_CORE_DIR rule, and it FAILS rather than skips when the parser is missing) and loads the
 * REAL TagConverter, because the Convertible flag is TagConverter::classify_tag()'s verdict and a
 * stand-in would test our idea of it. The migration entries are synthetic, so a registration
 * change elsewhere cannot move this file. Fabricated: parse_blocks() (a one-liner over the real
 * parser, as core defines it) and the few WordPress functions the loaded files touch.
 *
 * Asserts: one record per occurrence, ordinals per block type, container kind and id for a post
 * and a widget, a convertible occurrence in each base category, a declined and a skipped
 * occurrence carrying no flag, and the survey counted over the records.
 *
 * WHAT A PASS HERE DOES NOT PROVE. The DECLINED verdict is injected: the real one needs GB's
 * tag registry (converter-ownership-test.php holds the guard itself). That the survey's output
 * is unchanged on a real database is the testbed's (core-block-test-matrix.md §CB, page snapshots).
 *
 * Run:  php tools/test/located-walk-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

$failures = 0;
$count    = 0;

function assert_same( $label, $expected, $actual ): void {
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

$core   = rtrim( (string) ( getenv( 'BWS_WP_CORE_DIR' ) ?: 'D:/Resources/WordPress/wordpress' ), '/\\' );
$parser = $core . '/wp-includes/class-wp-block-parser.php';
if ( ! is_file( $parser ) ) {
	assert_same( "the real WP block parser is at {$parser} (set BWS_WP_CORE_DIR)", true, false );
	echo "\n{$count} assertions, {$failures} failed\n";
	exit( 1 );
}
require $parser;

function parse_blocks( $content ) {
	return ( new WP_Block_Parser() )->parse( $content );
}
function __( $text, $domain = null ) {
	return $text;
}
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}
function get_option( $name, $default = false ) {
	return $default;
}
function update_option( $name, $value, $autoload = null ) {
	return true;
}
function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . $path;
}
function apply_filters( $hook, $value ) {
	return $value;
}
function bws_gb_tag_name_collisions(): array {
	return array();
}

$root = dirname( __DIR__, 2 );
require $root . '/includes/helpers/slot-fold.php';
require $root . '/includes/helpers/serialization-order.php';
require $root . '/includes/helpers/gb-output-boundary.php';
require $root . '/includes/helpers/converter-ownership.php';
require $root . '/includes/helpers/core-block-tags.php';
require $root . '/includes/helpers/core-block-survey.php';
require $root . '/includes/classes/class-migration-registry.php';
require $root . '/includes/classes/admin/class-tag-converter.php';

use BWS\DynamicTags\MigrationRegistry;

// Two deprecated names. `ws_old` is still registered with GB (the converter would rewrite it);
// `ws_gone` is not (the same situation after an unregister). `text` is a live tag that no
// migration entry names.
foreach ( array( 'ws_old' => 'ws_new', 'ws_gone' => 'ws_gone_new' ) as $from => $to ) {
	MigrationRegistry::register( array(
		'type'               => 'tag',
		'match_tag'          => $from,
		'new_tag'            => $to,
		'transform_callback' => static fn ( string $s ): string => str_replace( '{{' . $from, '{{' . $to, $s ),
	) );
}

$known = array( 'text' => true, 'ws_old' => true );
$reg   = static fn( string $name ): bool => isset( $known[ $name ] );
$post  = array( 'kind' => 'post', 'id' => 42 );
$real  = bws_core_blocks_survey_classifier();

$blocks = '<!-- wp:group --><div class="wp-block-group">'
	. '<!-- wp:paragraph --><p>{{ws_old use:x}} and {{text}}</p><!-- /wp:paragraph -->'
	. '<!-- wp:paragraph --><p>{{ws_gone}}</p><!-- /wp:paragraph -->'
	. '<!-- wp:paragraph --><p>no tag here</p><!-- /wp:paragraph -->'
	. '<!-- wp:heading --><h2>{{nope}}</h2><!-- /wp:heading -->'
	. '<!-- wp:paragraph --><p>{{text}}</p><!-- /wp:paragraph -->'
	. '</div><!-- /wp:group -->';

echo "LW1 - one record per occurrence\n";
$records = bws_core_blocks_survey_walk( $blocks, $reg, $post, $real );
assert_same( 'LW1.1 five occurrences, five records, in document order', array( 'ws_old', 'text', 'ws_gone', 'nope', 'text' ), array_column( $records, 'tag' ) );
assert_same( 'LW1.2 each record carries exactly the contract keys, in order', array( 'tag', 'category', 'convertible', 'block_name', 'container', 'ordinal', 'stored' ), array_keys( $records[0] ) );
assert_same( 'LW1.3 the stored string is the match exactly as content holds it', '{{ws_old use:x}}', $records[0]['stored'] );
assert_same( 'LW1.4 two tags in one block are two records', array( 'core/paragraph', 'core/paragraph' ), array( $records[0]['block_name'], $records[1]['block_name'] ) );
assert_same( 'LW1.5 no braces: no records', array(), bws_core_blocks_survey_walk( '<!-- wp:paragraph --><p>plain</p><!-- /wp:paragraph -->', $reg, $post, $real ) );

echo "\nLW2 - ordinals count blocks of that type, in document order, tagless ones included\n";
assert_same( 'LW2.1 ordinal within the block type: paragraphs 1, 1, 2, 4 (the third holds no tag) and the heading is its own first', array( 1, 1, 2, 1, 4 ), array_column( $records, 'ordinal' ) );
$groups = bws_core_blocks_survey_walk( '<!-- wp:group -->{{text}}<!-- wp:group -->{{text}}<!-- /wp:group --><!-- /wp:group -->', $reg, $post );
assert_same( 'LW2.2 the outer group is the 1st core/group, the nested one the 2nd', array( 1, 2 ), array_column( $groups, 'ordinal' ) );

echo "\nLW3 - container kind and id\n";
assert_same( 'LW3.1 a post container is stamped on every record', array( $post ), array_values( array_unique( array_column( $records, 'container' ), SORT_REGULAR ) ) );
$widget = bws_core_blocks_survey_walk( '<!-- wp:paragraph --><p>{{text}}</p><!-- /wp:paragraph -->', $reg, array( 'kind' => 'widget', 'id' => 7 ) );
assert_same( 'LW3.2 a widget container', array( 'kind' => 'widget', 'id' => 7 ), $widget[0]['container'] );

echo "\nLW4 - base category and the Convertible flag\n";
assert_same( 'LW4.1 base category is registered or unregistered, as the survey counts it', array( 'registered', 'registered', 'unregistered', 'unregistered', 'registered' ), array_column( $records, 'category' ) );
assert_same( 'LW4.2 a deprecated name still registered: Handled by GB and convertible', array( 'registered', true ), array( $records[0]['category'], $records[0]['convertible'] ) );
assert_same( 'LW4.3 a deprecated name not registered: Not handled by GB and convertible', array( 'unregistered', true ), array( $records[2]['category'], $records[2]['convertible'] ) );
assert_same( 'LW4.4 a live tag no migration names is not convertible, in either category', array( false, false ), array( $records[1]['convertible'], $records[3]['convertible'] ) );
assert_same( 'LW4.5 no classifier given: nothing is flagged', array( false, false, false, false, false ), array_column( bws_core_blocks_survey_walk( $blocks, $reg, $post ), 'convertible' ) );

$verdict = array( '{{ws_old use:x}}' => 'declined', '{{ws_gone}}' => 'skipped' );
$stub    = static fn( string $tag, string $stored ): ?string => $verdict[ $stored ] ?? null;
$stubbed = bws_core_blocks_survey_walk( $blocks, $reg, $post, $stub );
assert_same( 'LW4.6 a declined and a skipped occurrence carry no flag', array( false, false ), array( $stubbed[0]['convertible'], $stubbed[2]['convertible'] ) );
$yes = static fn( string $tag, string $stored ): ?string => 'convert';
assert_same( 'LW4.7 only a convert verdict sets the flag', true, bws_core_blocks_survey_walk( $blocks, $reg, $post, $yes )[0]['convertible'] );

echo "\nLW5 - the classifier is TagConverter::classify_tag(), asked only of deprecated names\n";
assert_same( 'LW5.1 a name the registry does not hold as a deprecated tag is not asked', null, $real( 'text', '{{text}}' ) );
assert_same( 'LW5.2 a deprecated name gets classify_tag\'s status verbatim', \BWS\DynamicTags\Admin\TagConverter::classify_tag( 'ws_old', '{{ws_old use:x}}' )['status'], $real( 'ws_old', '{{ws_old use:x}}' ) );

echo "\nLW6 - the survey is a count over the records\n";
$cell = static fn( int $r, int $u, array $rt = array(), array $ut = array() ): array => array( 'registered' => $r, 'unregistered' => $u, 'registered_tags' => $rt, 'unregistered_tags' => $ut );
assert_same(
	'LW6.1 per block type, registered and unregistered apart with their tag lists',
	array(
		'core/paragraph' => $cell( 3, 1, array( 'ws_old' => 1, 'text' => 2 ), array( 'ws_gone' => 1 ) ),
		'core/heading'   => $cell( 0, 1, array(), array( 'nope' => 1 ) ),
	),
	bws_core_blocks_survey_count( $records )
);
assert_same( 'LW6.2 no records, no cells', array(), bws_core_blocks_survey_count( array() ) );
assert_same( 'LW6.3 the flag moves nothing between categories: counts are the same with and without a classifier', bws_core_blocks_survey_count( $records ), bws_core_blocks_survey_count( bws_core_blocks_survey_walk( $blocks, $reg, $post ) ) );

echo "\n{$count} assertions, {$failures} failed\n";
exit( $failures ? 1 : 0 );
