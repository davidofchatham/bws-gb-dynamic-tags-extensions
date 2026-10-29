<?php
/**
 * Standalone unit harness for TagConverter::migrate_string() (FW-73, ticket 01).
 *
 * migrate_string() is the shared rewriter: the two loops that used to sit inside
 * migrate_post() (deprecated tag renames, then option migrations, both passing the
 * ownership guard) behind a string-in, string-out seam. This drives it directly, on one
 * string holding a tag rename, an option migration and an ownership-declined tag, and
 * checks all four returned fields. The real files are loaded; only the WordPress surface
 * they touch when called (get_option, the collision record) is stubbed, and the migration
 * entries are synthetic so a registration change elsewhere cannot move this file.
 *
 * WHAT A PASS HERE DOES NOT PROVE. That migrate_post() routes through the function, and
 * that the post write around it is unchanged: converter-ownership-test.php §O7 holds the
 * routing, and the testbed's verify-migration.php holds the write.
 *
 * Run:  php tools/test/migrate-string-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) { return $default; }
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) { return $value; }
}

// The one collision this harness declares: `ms_held` is a name another plugin took.
// bws_gb_tag_name_collisions() itself is gb-registration-boundary.php's, pinned by
// control-order-test.php; here it is only the fact the guard reads.
function bws_gb_tag_name_collisions(): array {
	return array( 'ms_held' => array( 'tag' => 'ms_held', 'outcome' => 'yielded', 'title' => '' ) );
}

$root = dirname( __DIR__, 2 );
require $root . '/includes/helpers/slot-fold.php'; // bws_slot_ordinal_num(), which the key parser reads.
require $root . '/includes/helpers/serialization-order.php';
require $root . '/includes/helpers/gb-output-boundary.php';
require $root . '/includes/helpers/converter-ownership.php';
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

// A tag rename, an option migration, and a rename whose name the guard refuses.
MigrationRegistry::register( array(
	'type'               => 'tag',
	'match_tag'          => 'ms_old',
	'new_tag'            => 'ms_new',
	'transform_callback' => static fn ( string $s ): string => str_replace( '{{ms_old', '{{ms_new', $s ),
) );
MigrationRegistry::register( array(
	'type'               => 'tag',
	'match_tag'          => 'ms_held',
	'new_tag'            => 'ms_held_new',
	'transform_callback' => static fn ( string $s ): string => str_replace( '{{ms_held', '{{ms_held_new', $s ),
) );
MigrationRegistry::register( array(
	'type'               => 'option',
	'match_tag'          => 'ms_base',
	'match_options'      => array( 'legacy' ),
	'transform_callback' => static fn ( string $s ): string => str_replace( 'legacy:', 'key:', $s ),
) );

echo "MS1 — one string, every field\n";

$in  = 'A {{ms_old use:x}} B {{ms_base legacy:v}} C {{ms_held use:y}} D';
$out = TagConverter::migrate_string( $in );

$keys = array_keys( $out );
sort( $keys );
assert_eq( 'MS1.1 returns exactly the four fields', array( 'content', 'declined', 'option_count', 'tag_count' ), $keys );
assert_eq( 'MS1.2 content: rename and option migration applied, declined tag left byte-identical',
	'A {{ms_new use:x}} B {{ms_base key:v}} C {{ms_held use:y}} D', $out['content'] );
assert_eq( 'MS1.3 tag_count counts the accepted rename only', 1, $out['tag_count'] );
assert_eq( 'MS1.4 option_count counts the option migration', 1, $out['option_count'] );
assert_eq( 'MS1.5 declined: tag name to ownership reason, one entry', array( 'ms_held' => 'name_not_ours' ), $out['declined'] );

echo "\nMS2 — nothing to do\n";

$none = TagConverter::migrate_string( 'plain {{unrelated}} text' );
assert_eq( 'MS2.1 untouched content, zero counts, nothing declined',
	array( 'content' => 'plain {{unrelated}} text', 'tag_count' => 0, 'option_count' => 0, 'declined' => array() ), $none );

echo "\n";
if ( $failures ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
