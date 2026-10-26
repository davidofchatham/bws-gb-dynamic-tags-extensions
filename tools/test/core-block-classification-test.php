<?php
/**
 * Standalone harness for the core-block classification owner (FW-156) in
 * includes/helpers/link-helpers.php: the block-name to content-kind question, asked as a
 * kind and as the two yes/no questions the render guards need.
 *
 * Asserts the CONSEQUENCES of the classification (which blocks answer which question), the
 * way its callers observe them. The map itself is named only by the owner's PHPDoc.
 *
 * Loads the real helper file; it needs no WordPress symbol beyond ABSPATH.
 *
 * Run:  php tools/test/core-block-classification-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

require dirname( __DIR__, 2 ) . '/includes/helpers/link-helpers.php';

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

echo "§1 — the kind\n";
foreach ( array( 'core/paragraph', 'core/heading', 'core/list-item' ) as $name ) {
	assert_same( "{$name} is inline text", BWS_CORE_BLOCK_KIND_INLINE, bws_core_block_kind( $blk( $name ) ) );
}
assert_same( 'core/table is the table kind', BWS_CORE_BLOCK_KIND_TABLE, bws_core_block_kind( $blk( 'core/table' ) ) );
assert_same( 'core/list is the list kind', BWS_CORE_BLOCK_KIND_LIST, bws_core_block_kind( $blk( 'core/list' ) ) );
assert_same( 'core/button is the button kind', BWS_CORE_BLOCK_KIND_BUTTON, bws_core_block_kind( $blk( 'core/button' ) ) );
assert_same( 'the five kinds are distinct', 5, count( array_unique( array( BWS_CORE_BLOCK_KIND_INLINE, BWS_CORE_BLOCK_KIND_TABLE, BWS_CORE_BLOCK_KIND_LIST, BWS_CORE_BLOCK_KIND_BUTTON, '' ) ) ) );

echo "§2 — unclassified is unconstrained\n";
foreach ( array( 'core/group', 'core/quote', 'core/image', 'generateblocks/text', 'generateblocks/element', 'acme/thing', '' ) as $name ) {
	assert_same( "'{$name}' is unclassified", '', bws_core_block_kind( $blk( $name ) ) );
}

echo "§3 — malformed \$block never fatals and never classifies\n";
foreach ( array( 'null' => null, 'string' => 'core/paragraph', 'int' => 7, 'false' => false, 'empty array' => array(), 'no blockName key' => array( 'attrs' => array() ), 'non-string blockName' => array( 'blockName' => array( 'core/paragraph' ) ), 'null blockName' => array( 'blockName' => null ) ) as $label => $bad ) {
	assert_same( "{$label}: unclassified", '', bws_core_block_kind( $bad ) );
	assert_same( "{$label}: not inline-only", false, bws_core_block_is_inline_only( $bad ) );
	assert_same( "{$label}: not a button", false, bws_core_block_is_button( $bad ) );
}

echo "§4 — the two questions\n";
foreach ( array( 'core/paragraph', 'core/heading', 'core/list-item', 'core/table', 'core/button' ) as $name ) {
	assert_same( "{$name} is inline-only", true, bws_core_block_is_inline_only( $blk( $name ) ) );
}
foreach ( array( 'core/list', 'core/group', 'generateblocks/text' ) as $name ) {
	assert_same( "{$name} is not inline-only (the list kind is unconstrained)", false, bws_core_block_is_inline_only( $blk( $name ) ) );
}
assert_same( 'core/button is the button host', true, bws_core_block_is_button( $blk( 'core/button' ) ) );
foreach ( array( 'core/paragraph', 'core/heading', 'core/list-item', 'core/table', 'core/list', 'core/buttons', 'generateblocks/text' ) as $name ) {
	assert_same( "{$name} is not the button host", false, bws_core_block_is_button( $blk( $name ) ) );
}

echo "\n{$count} assertions, {$failures} failed\n";
exit( $failures ? 1 : 0 );
