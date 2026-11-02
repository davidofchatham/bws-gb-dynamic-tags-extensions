<?php
/**
 * Standalone harness for the core-block classification owner (FW-156) in
 * includes/helpers/link-helpers.php: the block-name to content-kind question, asked as a
 * kind and as the two yes/no questions the render guards need.
 *
 * Asserts the CONSEQUENCES of the classification (which blocks answer which question), the
 * way its callers observe them. The map itself is named only by the owner's PHPDoc.
 *
 * Loads the real helper file, the real enabled-set file and the real SettingsPage (a declared
 * kind is read off the `bws_dynamic_tags_core_blocks` filter result). Fabricated: a minimal hook
 * implementation and get_option / sanitize_key.
 *
 * Run:  php tools/test/core-block-classification-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

$GLOBALS['__filters'] = array();
function add_filter( $tag, $fn, $priority = 10, $args = 1 ) {
	$GLOBALS['__filters'][ $tag ][ $priority ][] = $fn;
	return true;
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
function get_option( $name, $default = false ) {
	return $default;
}
function sanitize_key( $k ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) );
}

$root = dirname( __DIR__, 2 );
require $root . '/includes/helpers/table-helpers.php';
require $root . '/includes/classes/admin/class-settings-page.php';
require $root . '/includes/helpers/core-block-tags.php';
require $root . '/includes/helpers/link-helpers.php';

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

$blk = static fn( $name ) => array( 'blockName' => $name );

echo "§1 — the kind, for every block in the built-in map\n";
$map = array(
	BWS_CORE_BLOCK_KIND_INLINE       => array( 'core/paragraph', 'core/heading', 'core/list-item', 'core/table', 'core/pullquote', 'core/details' ),
	BWS_CORE_BLOCK_KIND_PREFORMATTED => array( 'core/verse', 'core/preformatted', 'core/code' ),
	BWS_CORE_BLOCK_KIND_LINK         => array( 'core/button', 'core/file', 'core/navigation-link', 'core/read-more' ),
	BWS_CORE_BLOCK_KIND_CONTAINER    => array( 'core/group', 'core/cover', 'core/media-text', 'core/quote', 'core/buttons', 'core/list', 'core/columns', 'core/column' ),
	BWS_CORE_BLOCK_KIND_UNRESTRICTED => array( 'core/html' ),
);
foreach ( $map as $kind => $names ) {
	foreach ( $names as $name ) {
		assert_same( "{$name} is {$kind}", $kind, bws_core_block_kind( $blk( $name ) ) );
	}
}
assert_same( 'the wire values are the kind names', array( 'inline', 'preformatted', 'link', 'container', 'unrestricted' ), array( BWS_CORE_BLOCK_KIND_INLINE, BWS_CORE_BLOCK_KIND_PREFORMATTED, BWS_CORE_BLOCK_KIND_LINK, BWS_CORE_BLOCK_KIND_CONTAINER, BWS_CORE_BLOCK_KIND_UNRESTRICTED ) );
assert_same( 'the retired kind constants are gone', array( false, false, false ), array_map( static fn( $k ) => defined( 'BWS_CORE_BLOCK_KIND_' . $k ), array( 'TABLE', 'LIST', 'BUTTON' ) ) );

echo "§2 — unclassified is unconstrained\n";
foreach ( array( 'core/image', 'generateblocks/text', 'generateblocks/element', 'acme/thing', '' ) as $name ) {
	assert_same( "'{$name}' is unclassified", '', bws_core_block_kind( $blk( $name ) ) );
}

echo "§3 — malformed \$block never fatals and never classifies\n";
foreach ( array( 'null' => null, 'string' => 'core/paragraph', 'int' => 7, 'false' => false, 'empty array' => array(), 'no blockName key' => array( 'attrs' => array() ), 'non-string blockName' => array( 'blockName' => array( 'core/paragraph' ) ), 'null blockName' => array( 'blockName' => null ) ) as $label => $bad ) {
	assert_same( "{$label}: unclassified", '', bws_core_block_kind( $bad ) );
	assert_same( "{$label}: not inline-only", false, bws_core_block_is_inline_only( $bad ) );
	assert_same( "{$label}: not a link host", false, bws_core_block_is_link( $bad ) );
}

echo "§4 — the two questions\n";
foreach ( array( BWS_CORE_BLOCK_KIND_INLINE, BWS_CORE_BLOCK_KIND_PREFORMATTED, BWS_CORE_BLOCK_KIND_LINK ) as $kind ) {
	foreach ( $map[ $kind ] as $name ) {
		assert_same( "{$name} ({$kind}) is inline-only", true, bws_core_block_is_inline_only( $blk( $name ) ) );
	}
}
foreach ( array_merge( $map[ BWS_CORE_BLOCK_KIND_CONTAINER ], $map[ BWS_CORE_BLOCK_KIND_UNRESTRICTED ], array( 'core/image', 'generateblocks/text' ) ) as $name ) {
	assert_same( "{$name} is not inline-only", false, bws_core_block_is_inline_only( $blk( $name ) ) );
}
foreach ( $map[ BWS_CORE_BLOCK_KIND_LINK ] as $name ) {
	assert_same( "{$name} is a link host", true, bws_core_block_is_link( $blk( $name ) ) );
}
foreach ( array_merge( $map[ BWS_CORE_BLOCK_KIND_INLINE ], $map[ BWS_CORE_BLOCK_KIND_PREFORMATTED ], $map[ BWS_CORE_BLOCK_KIND_CONTAINER ], array( 'core/html', 'generateblocks/text' ) ) as $name ) {
	assert_same( "{$name} is not a link host", false, bws_core_block_is_link( $blk( $name ) ) );
}

echo "§5 — a declared kind reaches the same questions a built-in one does\n";
/** Declare $declared (name => kind) through the filter, resetting the per-request memo first. */
function declare_kinds( array $declared ): void {
	$GLOBALS['__filters'] = array();
	add_filter( 'bws_dynamic_tags_core_blocks', static fn( $b ) => array_merge( $b, $declared ) );
	bws_core_blocks_reset_memo();
}

declare_kinds( array( 'acme/a' => 'inline', 'acme/b' => 'preformatted', 'acme/c' => 'link', 'acme/d' => 'container', 'acme/e' => 'unrestricted', 'acme/bare' ) );
foreach ( array( 'acme/a' => 'inline', 'acme/b' => 'preformatted', 'acme/c' => 'link', 'acme/d' => 'container', 'acme/e' => 'unrestricted' ) as $name => $kind ) {
	assert_same( "{$name} declared {$kind}", $kind, bws_core_block_kind( $blk( $name ) ) );
}
assert_same( 'a bare name stays unclassified', '', bws_core_block_kind( $blk( 'acme/bare' ) ) );
assert_same( 'a name nobody declared stays unclassified', '', bws_core_block_kind( $blk( 'acme/other' ) ) );
assert_same( 'declared inline, preformatted and link are inline-only; container and unrestricted are not', array( true, true, true, false, false ), array_map( static fn( $n ) => bws_core_block_is_inline_only( $blk( $n ) ), array( 'acme/a', 'acme/b', 'acme/c', 'acme/d', 'acme/e' ) ) );
assert_same( 'only the declared link is a link host', array( false, false, true, false, false ), array_map( static fn( $n ) => bws_core_block_is_link( $blk( $n ) ), array( 'acme/a', 'acme/b', 'acme/c', 'acme/d', 'acme/e' ) ) );

declare_kinds( array( 'acme/a' => 'bogus', 'core/paragraph' => 'link', 'core/html' => 'inline', 'core/image' => 'link' ) );
assert_same( 'an unknown kind is unclassified', '', bws_core_block_kind( $blk( 'acme/a' ) ) );
assert_same( 'a built-in name keeps its built-in kind whatever the declaration says', array( 'inline', 'unrestricted' ), array( bws_core_block_kind( $blk( 'core/paragraph' ) ), bws_core_block_kind( $blk( 'core/html' ) ) ) );
assert_same( 'an unmapped core block can be declared', 'link', bws_core_block_kind( $blk( 'core/image' ) ) );

declare_kinds( array( 'acme/a' => 'inline' ) );
assert_same( 'before the filter changes', 'inline', bws_core_block_kind( $blk( 'acme/a' ) ) );
$GLOBALS['__filters'] = array();
assert_same( 'the memo holds the answer for the request', 'inline', bws_core_block_kind( $blk( 'acme/a' ) ) );
bws_core_blocks_reset_memo();
assert_same( 'after the reset the new filter result is read', '', bws_core_block_kind( $blk( 'acme/a' ) ) );

foreach ( array( 'null' => null, 'string' => 'acme/a', 'no blockName key' => array( 'attrs' => array() ) ) as $label => $bad ) {
	declare_kinds( array( 'acme/a' => 'inline' ) );
	assert_same( "{$label}: still unclassified with a declaration in play", '', bws_core_block_kind( $bad ) );
}

echo "\n{$count} assertions, {$failures} failed\n";
exit( $failures ? 1 : 0 );
