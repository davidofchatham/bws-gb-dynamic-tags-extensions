<?php
/**
 * Standalone harness for the core-block enabled set (FW-156) —
 * includes/helpers/core-block-tags.php, the `core_blocks` settings key, and the GB
 * allowlist hook in includes/hooks.php.
 *
 * The real SettingsPage class, the real table helpers (sanitize_settings() calls them),
 * the real helper file and the real hooks.php are loaded. Fabricated: a minimal WP hook
 * implementation (so the filter ROUTE is exercised, not skipped) and get_option/sanitize_key.
 *
 * Run:  php tools/test/core-block-tags-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

$root = dirname( __DIR__, 2 );

$GLOBALS['__filters'] = array();
$GLOBALS['__option']  = array();

function add_filter( $tag, $fn, $priority = 10, $args = 1 ) {
	$GLOBALS['__filters'][ $tag ][ $priority ][] = $fn;
	return true;
}
function remove_all_filters( $tag ) {
	unset( $GLOBALS['__filters'][ $tag ] );
}
function apply_filters( $tag, $value, ...$rest ) {
	$by_priority = $GLOBALS['__filters'][ $tag ] ?? array();
	ksort( $by_priority );
	foreach ( $by_priority as $fns ) {
		foreach ( $fns as $fn ) {
			$value = $fn( $value, ...$rest );
		}
	}
	return $value;
}
function add_action( $tag, $fn, $priority = 10, $args = 1 ) {
	$GLOBALS['__actions'][ $tag ][] = array( $fn, $priority );
	return true;
}
/** Minimal registry: just the get_registered() the pass reads, handing back live WP_Block_Type-like objects. */
class WP_Block_Type_Registry {
	public static $types = array();
	public static function get_instance() {
		return new self();
	}
	public function is_registered( $name ) {
		return isset( self::$types[ $name ] );
	}
	public function get_registered( $name ) {
		return self::$types[ $name ] ?? null;
	}
}
function get_option( $name, $default = false ) {
	return $GLOBALS['__option'][ $name ] ?? $default;
}
function sanitize_key( $k ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) );
}

require $root . '/includes/helpers/table-helpers.php';
require $root . '/includes/classes/admin/class-settings-page.php';
require $root . '/includes/helpers/core-block-tags.php';
require $root . '/includes/hooks.php';

use BWS\DynamicTags\Admin\SettingsPage;

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

/** Point the saved option at $core_blocks and reset the per-request settings cache. */
function with_saved( $core_blocks ): void {
	$GLOBALS['__option'][ SettingsPage::OPTION_NAME ] = null === $core_blocks ? array() : array( 'core_blocks' => $core_blocks );
	$ref = new ReflectionProperty( SettingsPage::class, 'settings' );
	$ref->setAccessible( true );
	$ref->setValue( null, null );
	remove_all_filters( 'bws_dynamic_tags_core_blocks' );
}

echo "§1 — the enabled set\n";
with_saved( null );
assert_same( 'default (nothing saved) is empty', array(), bws_core_blocks_enabled() );

with_saved( array() );
assert_same( 'empty saved list is empty', array(), bws_core_blocks_enabled() );

with_saved( array( 'core/paragraph', 'core/heading' ) );
assert_same( 'saved list passes through', array( 'core/paragraph', 'core/heading' ), bws_core_blocks_enabled() );

with_saved( array( 'core/paragraph', 'core/heading', 'core/paragraph' ) );
assert_same( 'duplicates collapse, order kept', array( 'core/paragraph', 'core/heading' ), bws_core_blocks_enabled() );

with_saved( array( 'core/paragraph' ) );
add_filter( 'bws_dynamic_tags_core_blocks', fn( $b ) => array_merge( $b, array( 'core/table' ) ) );
assert_same( 'filter can add', array( 'core/paragraph', 'core/table' ), bws_core_blocks_enabled() );

with_saved( array( 'core/paragraph', 'core/heading' ) );
add_filter( 'bws_dynamic_tags_core_blocks', fn( $b ) => array_diff( $b, array( 'core/heading' ) ) );
assert_same( 'filter can remove (list re-indexed)', array( 'core/paragraph' ), bws_core_blocks_enabled() );

with_saved( null );
add_filter( 'bws_dynamic_tags_core_blocks', fn( $b ) => array( 'core/list-item', 'core/list-item' ) );
assert_same( 'filter can enable with nothing saved; deduped after the filter', array( 'core/list-item' ), bws_core_blocks_enabled() );

with_saved( array( 'core/paragraph' ) );
add_filter( 'bws_dynamic_tags_core_blocks', fn( $b ) => 'not-a-list' );
assert_same( 'a filter returning a non-array yields empty, not a fatal', array(), bws_core_blocks_enabled() );

with_saved( array( 'core/paragraph' ) );
add_filter( 'bws_dynamic_tags_core_blocks', fn( $b ) => array( 'core/paragraph', 7, null, array( 'x' ), '' ) );
assert_same( 'non-string filter entries are dropped', array( 'core/paragraph' ), bws_core_blocks_enabled() );

echo "§2 — sanitize_settings() keeps and cleans core_blocks\n";
$s = SettingsPage::sanitize_settings( array( 'core_blocks' => array( 'core/paragraph', ' core/heading ', 'acme/my-block' ) ) );
assert_same( 'valid slugs survive a save, trimmed', array( 'core/paragraph', 'core/heading', 'acme/my-block' ), $s['core_blocks'] );

$s = SettingsPage::sanitize_settings( array( 'core_blocks' => array( 'paragraph', 'Core/Paragraph', 'core/', '/p', 'core/a/b', 'core/p q', '<script>', '', 5, null, array( 'core/x' ), 'core/ok-1' ) ) );
assert_same( 'junk entries dropped; only namespace/name slugs stay', array( 'core/ok-1' ), $s['core_blocks'] );

$s = SettingsPage::sanitize_settings( array( 'core_blocks' => array( 'core/paragraph', 'core/paragraph' ) ) );
assert_same( 'duplicates collapse at save', array( 'core/paragraph' ), $s['core_blocks'] );

$s = SettingsPage::sanitize_settings( array( 'core_blocks' => 'core/paragraph' ) );
assert_same( 'a non-array value saves as empty', array(), $s['core_blocks'] );

$s = SettingsPage::sanitize_settings( array() );
assert_same( 'absent key saves as empty list (key always present)', array(), $s['core_blocks'] );

$s = SettingsPage::sanitize_settings( array( 'core_blocks' => array( 'unregistered/deactivated-plugin-block' ) ) );
assert_same( 'unregistered names are kept (not validated against the registry)', array( 'unregistered/deactivated-plugin-block' ), $s['core_blocks'] );

echo "§3 — GB allowlist hook\n";
$gb = 'generateblocks_dynamic_tags_allowed_blocks';
assert_same( 'hooks.php registers the GB allowlist filter', true, ! empty( $GLOBALS['__filters'][ $gb ] ) );

WP_Block_Type_Registry::$types = array( 'core/paragraph' => (object) array(), 'core/heading' => (object) array(), 'generateblocks/text' => (object) array() );

with_saved( null );
assert_same( 'empty set leaves GB list byte-identical', array( 'generateblocks/text' ), apply_filters( $gb, array( 'generateblocks/text' ) ) );

with_saved( array( 'core/paragraph', 'generateblocks/text' ) );
assert_same( 'set is appended; GB entries not duplicated', array( 'generateblocks/text', 'core/paragraph' ), apply_filters( $gb, array( 'generateblocks/text' ) ) );

with_saved( array( 'core/paragraph' ) );
assert_same( 'a non-array GB value is treated as empty list', array( 'core/paragraph' ), apply_filters( $gb, null ) );

with_saved( array( 'core/paragraph', 'acme/deactivated-block' ) );
assert_same( 'an unregistered name is withheld from GB (it would get no loop keys)', array( 'core/paragraph' ), apply_filters( $gb, array() ) );
assert_same( 'the same name stays in the enabled set, so the selection survives', array( 'core/paragraph', 'acme/deactivated-block' ), bws_core_blocks_enabled() );

WP_Block_Type_Registry::$types['acme/deactivated-block'] = (object) array();
assert_same( 'once the plugin is back and the block registers, it is allowed again', array( 'core/paragraph', 'acme/deactivated-block' ), apply_filters( $gb, array() ) );

echo "§4 — the loop-key pass\n";
$loop_keys = array( 'generateblocks/loopItem', 'generateblocks/loopIndex', 'generateblocks/queryType', 'generateblocks/queryId', 'generateblocks/queryData', 'postId', 'postType' );

/** Register fake block types (name => uses_context) and run the pass. */
function run_pass( array $types ): array {
	WP_Block_Type_Registry::$types = array();
	foreach ( $types as $name => $uses ) {
		WP_Block_Type_Registry::$types[ $name ] = (object) array( 'uses_context' => $uses );
	}
	bws_core_blocks_add_loop_context();
	return array_map( fn( $t ) => $t->uses_context, WP_Block_Type_Registry::$types );
}

$hooked = array_filter( $GLOBALS['__actions']['init'] ?? array(), fn( $a ) => 'bws_core_blocks_add_loop_context' === $a[0] );
assert_same( 'hooks.php registers the pass on init', 1, count( $hooked ) );
assert_same( 'the pass runs after core and plugin block registration (priority > 10)', true, $hooked && array_values( $hooked )[0][1] > 10 );

with_saved( array( 'core/paragraph' ) );
$out = run_pass( array( 'core/paragraph' => array(), 'core/heading' => array() ) );
assert_same( 'keys added to a selected block with no context', $loop_keys, $out['core/paragraph'] );
assert_same( 'an unselected block is untouched', array(), $out['core/heading'] );

$out = run_pass( array( 'core/paragraph' => array( 'postId', 'acme/custom' ) ) );
assert_same( 'existing keys kept in place, missing keys appended once', array( 'postId', 'acme/custom', 'generateblocks/loopItem', 'generateblocks/loopIndex', 'generateblocks/queryType', 'generateblocks/queryId', 'generateblocks/queryData', 'postType' ), $out['core/paragraph'] );

$again = run_pass( array( 'core/paragraph' => $out['core/paragraph'] ) );
assert_same( 'a second run changes nothing (idempotent)', $out['core/paragraph'], $again['core/paragraph'] );

with_saved( array( 'core/paragraph', 'acme/not-registered' ) );
$out = run_pass( array( 'core/paragraph' => array() ) );
assert_same( 'a selected name that is not registered is skipped without error', $loop_keys, $out['core/paragraph'] );

with_saved( array() );
$out = run_pass( array( 'core/paragraph' => array(), 'core/heading' => array( 'postId' ) ) );
assert_same( 'empty set touches no block type', array( 'core/paragraph' => array(), 'core/heading' => array( 'postId' ) ), $out );

echo "\n{$count} assertions, {$failures} failed\n";
exit( $failures ? 1 : 0 );
