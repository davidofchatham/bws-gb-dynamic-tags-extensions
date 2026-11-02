<?php
/**
 * Standalone harness for the Migration Tool's report-from-the-run rule (#119).
 *
 * The class: the scan lists a tag the run will not rewrite. The rule that closes it is that the
 * report and the run read ONE walk, so these assert behavior at the three seams that carry it:
 *   - MigrationRegistry::walk_option_migrations()   the cascade walk, and apply_option_migration()
 *                                                   as its thin wrapper
 *   - TagConverter::classify_tag()                  the per-string verdict (status, reason, labels, string)
 *   - TagConverter::scan_widgets()                  the scan rows built over it (scan_content() is private;
 *                                                   widgets are its pure public door)
 * and, last, the property the whole change exists for: a stored string's verdict is `convert`
 * exactly when migrate_string() changes it.
 *
 * REQUIRES THE REAL WORDPRESS BLOCK PARSER (the scan rows ask the located walk); see
 * located-walk-test.php for the $BWS_WP_CORE_DIR rule. It FAILS rather than skips without it.
 *
 * WHAT A PASS HERE DOES NOT PROVE. That the entries registered in production agree: the entries
 * here are synthetic so a registration change elsewhere cannot move this file. The shipped entries
 * are covered by fold-migration-test.php and as-size-fold-test.php; the testbed's verify-migration.php
 * covers the row shape on a real database.
 *
 * Run:  php tools/test/migration-report-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

$GLOBALS['mr_options'] = array();

if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) { return $GLOBALS['mr_options'][ $name ] ?? $default; }
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) { $GLOBALS['mr_options'][ $name ] = $value; return true; }
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) { return $value; }
}
function bws_gb_tag_name_collisions(): array { return array(); }
function bws_gb_registered_tags_now(): array { return array(); }
function bws_gb_tags_we_registered(): array { return array(); }
$GLOBALS['mr_held'] = array();
function bws_gb_tag_held_by_other( string $tag, array $registry, array $ours ): ?array { return in_array( $tag, $GLOBALS['mr_held'], true ) ? array( 'file' => 'other-plugin/other.php' ) : null; }
function bws_modifier_skip_report_lines(): array { return array(); }
function bws_gb_other_registrar_phrase( string $title, string $source ): string { return "another plugin"; }
function bws_gb_tag_registrar_file( $callable ): string { return ""; }

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

$swap = static fn ( string $from, string $to ): callable => static fn ( string $s ): string => str_replace( $from, $to, $s );

// A cascade: `a:` becomes `b:`, then `b:` becomes `key:`. The second entry is registered FIRST, so
// it matches nothing on the stored string and fires only after the first has run.
MigrationRegistry::register( array( 'type' => 'option', 'label' => 'b to key', 'match_tag' => 'mr_cascade', 'match_options' => array( 'b' ), 'transform_callback' => $swap( 'b:', 'key:' ) ) );
MigrationRegistry::register( array( 'type' => 'option', 'label' => 'a to b', 'match_tag' => 'mr_cascade', 'match_options' => array( 'a' ), 'transform_callback' => $swap( 'a:', 'b:' ) ) );

// An over-match: the key is present, the transform acts only on one value (the as+size shape).
MigrationRegistry::register( array(
	'type'               => 'option',
	'label'              => 'mr value only',
	'match_tag'          => 'mr_wide',
	'match_options'      => array( 'as' ),
	'transform_callback' => $swap( 'as:old', 'as:new' ),
) );

// A rewrite the ownership guard declines: it introduces a key the plugin does not know.
MigrationRegistry::register( array( 'type' => 'option', 'label' => 'mr foreign', 'match_tag' => 'mr_foreign', 'match_options' => array( 'legacy' ), 'transform_callback' => $swap( 'legacy:', 'zz_foreign_key:' ) ) );

// A deprecated name whose transform changes nothing.
MigrationRegistry::register( array( 'type' => 'tag', 'match_tag' => 'mr_noop_old', 'new_tag' => 'mr_noop_new', 'transform_callback' => static fn ( string $s ): string => $s ) );
MigrationRegistry::register( array( 'type' => 'tag', 'match_tag' => 'mr_old', 'new_tag' => 'mr_new', 'transform_callback' => $swap( '{{mr_old', '{{mr_new' ) ) );

// A base name another plugin holds: the rewrite is fine in itself, the guard refuses it on the name.
$GLOBALS['mr_held'] = array( 'mr_held' );
MigrationRegistry::register( array( 'type' => 'option', 'label' => 'mr held', 'match_tag' => 'mr_held', 'match_options' => array( 'old' ), 'transform_callback' => $swap( 'old:', 'key:' ) ) );

echo "MR1 - the cascade walk\n";
$walk = MigrationRegistry::walk_option_migrations( 'mr_cascade', '{{mr_cascade a:1}}' );
assert_eq( 'MR1.1 the final string is the whole cascade, not the first hop', '{{mr_cascade key:1}}', $walk['string'] );
assert_eq( 'MR1.2 the fired entries come back in firing order, including one that matched only after an earlier hop', array( 'a to b', 'b to key' ), array_column( $walk['fired'], 'label' ) );
assert_eq( 'MR1.3 apply_option_migration() is the walk\'s string', $walk['string'], MigrationRegistry::apply_option_migration( 'mr_cascade', '{{mr_cascade a:1}}' ) );
$none = MigrationRegistry::walk_option_migrations( 'mr_cascade', '{{mr_cascade z:1}}' );
assert_eq( 'MR1.4 nothing matches: the string is untouched and nothing fired', array( '{{mr_cascade z:1}}', array() ), array( $none['string'], $none['fired'] ) );
$wide = MigrationRegistry::walk_option_migrations( 'mr_wide', '{{mr_wide as:other}}' );
assert_eq( 'MR1.5 an entry that matches but moves nothing did not fire', array( '{{mr_wide as:other}}', array() ), array( $wide['string'], $wide['fired'] ) );

echo "\nMR2 - the per-string verdict\n";
$v = TagConverter::classify_tag( 'mr_cascade', '{{mr_cascade a:1}}' );
assert_eq( 'MR2.1 an option cascade converts, names the labels that fired, and carries the final string', array( 'convert', '', array( 'a to b', 'b to key' ), '{{mr_cascade key:1}}' ), array( $v['status'], $v['reason'], $v['labels'], $v['string'] ) );
$v = TagConverter::classify_tag( 'mr_wide', '{{mr_wide as:other}}' );
assert_eq( 'MR2.2 an entry whose key matches but whose transform moves nothing is unchanged, not convert', array( 'unchanged', array() ), array( $v['status'], $v['labels'] ) );
$v = TagConverter::classify_tag( 'mr_wide', '{{mr_wide as:old}}' );
assert_eq( 'MR2.3 the same entry on the value it can move converts', array( 'convert', array( 'mr value only' ) ), array( $v['status'], $v['labels'] ) );
$v = TagConverter::classify_tag( 'mr_foreign', '{{mr_foreign legacy:v}}' );
assert_eq( 'MR2.4 an option rewrite the ownership guard refuses is declined, with the guard\'s reason (the fourth door)', array( 'declined', 'unknown_options', '{{mr_foreign legacy:v}}' ), array( $v['status'], $v['reason'], $v['string'] ) );
$v = TagConverter::classify_tag( 'mr_noop_old', '{{mr_noop_old}}' );
assert_eq( 'MR2.5 a deprecated name whose transform changes nothing is unchanged', 'unchanged', $v['status'] );
$v = TagConverter::classify_tag( 'mr_old', '{{mr_old key:1}}' );
assert_eq( 'MR2.6 a deprecated name that rewrites converts to the chain\'s string', array( 'convert', '{{mr_new key:1}}', array() ), array( $v['status'], $v['string'], $v['labels'] ) );
$v = TagConverter::classify_tag( 'text', '{{text}}' );
assert_eq( 'MR2.7 a name no entry mentions is unchanged', 'unchanged', $v['status'] );

$v = TagConverter::classify_tag( 'mr_cascade', '{{mr_cascade a:1|required:false}}' );
assert_eq( 'MR2.8 GB\'s reserved required option is not foreign wire: the rewrite converts and keeps it (#158)', array( 'convert', '{{mr_cascade key:1|required:false}}' ), array( $v['status'], $v['string'] ) );

echo "\nMR3 - the scan rows read the verdict; the allowlist still reads what is present\n";
$block = static fn ( string $p ): string => '<!-- wp:paragraph --><p>' . $p . '</p><!-- /wp:paragraph -->';
$row   = static fn ( string $p ): array => TagConverter::scan_widgets( array( 1 => $block( $p ) ), array() );

$rows = $row( '{{mr_cascade a:1}}' );
assert_eq( 'MR3.1 a cascade is reported on the first scan, every label that will fire', array( 'a to b', 'b to key' ), array_column( $rows[0]['option_migrations'], 'label' ) );

$rows = $row( '{{mr_wide as:other}}' );
assert_eq( 'MR3.2 an over-matching entry on a string it cannot move is not listed as work', array(), $rows[0]['option_migrations'] ?? array() );
assert_eq( 'MR3.3 but the label is still PRESENT on the site, so the allowlist keeps it', array( 'mr value only' ), TagConverter::rebuild_allowlist_from_scan( $rows )['option_labels'] );

$rows = $row( '{{mr_foreign legacy:v}}' );
assert_eq( 'MR3.4 a declined option rewrite is not listed as work', array(), $rows[0]['option_migrations'] );
$d = $rows[0]['deprecated_tags'][0] ?? array();
assert_eq( 'MR3.5 it appears in the declined channel\'s input, under its base name, with the guard\'s reason', array( 'mr_foreign', 'declined', 'unknown_options', 1 ), array( $d['tag'] ?? null, $d['status'] ?? null, $d['reason'] ?? null, $d['count'] ?? null ) );
assert_eq( 'MR3.6 the channel report carries it', 'mr_foreign', TagConverter::report_channels( $rows )['declined'][0]['tag'] ?? null );

$rows = $row( '{{mr_held old:1}}' );
$ch   = TagConverter::report_channels( $rows )['declined'][0] ?? array();
assert_eq( 'MR3.6b an option migration on a name another plugin holds is declined, with the same claim-these-tags action a retired name gets', array( 'mr_held', 'name_not_ours', array(), true ), array( $ch['tag'] ?? null, $ch['reason'] ?? null, $rows[0]['option_migrations'], '' !== ( $ch['action'] ?? '' ) ) );
$GLOBALS['mr_options'][ BWS_CONVERTER_OWNERSHIP_OPTIN_OPTION ] = array( 'mr_held' );
assert_eq( 'MR3.6c and once the name is claimed it is work again', array( 'mr held' ), array_column( $row( '{{mr_held old:1}}' )[0]['option_migrations'], 'label' ) );
$GLOBALS['mr_options'] = array();
assert_eq( 'MR3.7 a base name is not a retired tag: it stays out of the allowlist\'s tags', array(), TagConverter::rebuild_allowlist_from_scan( $rows )['tags'] );

$rows = $row( '{{mr_noop_old}}' );
assert_eq( 'MR3.8 an unchanged deprecated tag keeps its row (present on site) and is not convert', array( 'mr_noop_old', 'unchanged' ), array( $rows[0]['deprecated_tags'][0]['tag'], $rows[0]['deprecated_tags'][0]['status'] ) );
assert_eq( 'MR3.9 and the allowlist keeps it', array( 'mr_noop_old' ), TagConverter::rebuild_allowlist_from_scan( $rows )['tags'] );

$rows = $row( '{{mr_wide as:other}}' . ' ' . '{{mr_wide as:old}}' );
assert_eq( 'MR3.10 two strings of one tag: only the one that will change is work', array( 'mr value only' ), array_column( $rows[0]['option_migrations'], 'label' ) );
assert_eq( 'MR3.11 and the located list names only that occurrence', array( '{{mr_wide as:old}}' ), array_column( $rows[0]['locations'], 'stored' ) );

MigrationRegistry::register( array( 'type' => 'tag', 'match_tag' => 'mr_chain_old', 'new_tag' => 'mr_chain_new', 'transform_callback' => $swap( '{{mr_chain_old', '{{mr_chain_new' ) ) );
MigrationRegistry::register( array( 'type' => 'option', 'label' => 'mr chain option', 'match_tag' => 'mr_chain_new', 'match_options' => array( 'use' ), 'transform_callback' => $swap( 'use:', 'src:' ) ) );
// A name that is both a deprecated tag and a base tag with option entries; its chain adds a key the guard refuses.
MigrationRegistry::register( array( 'type' => 'tag', 'match_tag' => 'mr_dual', 'new_tag' => 'mr_dual', 'transform_callback' => static fn ( string $s ): string => str_replace( '}}', ' zz_foreign_key:1}}', $s ) ) );
MigrationRegistry::register( array( 'type' => 'option', 'label' => 'mr dual option', 'match_tag' => 'mr_dual', 'match_options' => array( 'legacy' ), 'transform_callback' => $swap( 'legacy:', 'key:' ) ) );

echo "\nMR3b - a chain hop followed by an option cascade, and a name in both lists\n";
$v = TagConverter::classify_tag( 'mr_chain_old', '{{mr_chain_old use:1}}' );
assert_eq( 'MR3b.1 the verdict carries the run\'s final string and the label that fired after the hop', array( 'convert', '{{mr_chain_new src:1}}', array( 'mr chain option' ) ), array( $v['status'], $v['string'], $v['labels'] ) );
assert_eq( 'MR3b.2 and that is what the run writes', '{{mr_chain_new src:1}}', TagConverter::migrate_string( '{{mr_chain_old use:1}}' )['content'] );
$rows = $row( '{{mr_dual legacy:1}}' );
assert_eq( 'MR3b.3 a name that is both a deprecated tag and an option base is tallied once, not once per loop', array( 1, 'declined' ), array( $rows[0]['deprecated_tags'][0]['count'] ?? null, $rows[0]['deprecated_tags'][0]['status'] ?? null ) );
assert_eq( 'MR3b.4 as one row', 1, count( $rows[0]['deprecated_tags'] ) );

echo "\nMR4 - report equals run: a verdict is `convert` exactly when migrate_string() changes the string\n";
$cases = array(
	array( 'mr_cascade', '{{mr_cascade a:1}}' ),
	array( 'mr_cascade', '{{mr_cascade z:1}}' ),
	array( 'mr_wide', '{{mr_wide as:old}}' ),
	array( 'mr_wide', '{{mr_wide as:other}}' ),
	array( 'mr_foreign', '{{mr_foreign legacy:v}}' ),
	array( 'mr_noop_old', '{{mr_noop_old}}' ),
	array( 'mr_old', '{{mr_old key:1}}' ),
	array( 'mr_cascade', "{{mr_cascade\ta:1}}" ),
	array( 'mr_cascade', "{{mr_cascade\na:1}}" ),
);
foreach ( $cases as [ $tag, $stored ] ) {
	$verdict = TagConverter::classify_tag( $tag, $stored );
	$ran     = TagConverter::migrate_string( $stored );
	assert_eq(
		'MR4 ' . str_replace( array( "\t", "\n" ), array( '\t', '\n' ), $stored ) . ': convert iff the run changes it, and to the verdict\'s string',
		array( 'convert' === $verdict['status'], 'convert' === $verdict['status'] ? $verdict['string'] : $stored ),
		array( $ran['content'] !== $stored, $ran['content'] )
	);
}

echo "\n";
if ( $failures ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
