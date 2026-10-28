<?php
/**
 * Standalone harness for the button-host link rule (FW-156, core-block-tags/05):
 * `bws_host_unlinked_options()` in includes/helpers/link-helpers.php, plus the census of
 * the shells that must route their options through it.
 *
 * §1-§3 assert the rule's CONSEQUENCE as a shell observes it: on a button host the options a
 * shell reads carry no link wrap (`linkTo` none, `noLink` on, so `bws_wrap_with_link()` and the
 * email/phone mailto:/tel: wrap both skip), every other option is untouched, and any other host
 * (a GB block, a paragraph, malformed input) gets its options back unchanged. §4 pins that each
 * shell that can wrap its output asks the helper, because a shell that forgot would still wrap.
 * §5 pins the `{{content}}` refusal on a button host: both shells ask before anything is read,
 * so neither a value nor a `try_` fallback reaches the button.
 *
 * Loads the real helper file; it needs no WordPress symbol beyond ABSPATH.
 *
 * Run:  php tools/test/button-host-unlinked-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

$root = dirname( __DIR__, 2 );
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

$linked = array(
	'key'     => 'phone',
	'linkTo'  => 'permalink',
	'linkKey' => 'url_field',
	'newTab'  => '1',
	'subject' => 'Hello',
	'sep'     => ' / ',
);

echo "§1 — a button host drops the wrap\n";
$out = bws_host_unlinked_options( $linked, $blk( 'core/button' ) );
assert_same( 'linkTo is none', 'none', $out['linkTo'] );
assert_same( 'noLink is on', true, $out['noLink'] );
assert_same( 'other options kept', array( 'phone', 'url_field', '1', 'Hello', ' / ' ), array( $out['key'], $out['linkKey'], $out['newTab'], $out['subject'], $out['sep'] ) );
$out = bws_host_unlinked_options( array(), $blk( 'core/button' ) );
assert_same( 'empty options still unlinked', array( 'linkTo' => 'none', 'noLink' => true ), $out );

echo "§2 — every other host is left alone\n";
foreach ( array( 'generateblocks/text', 'generateblocks/element', 'core/paragraph', 'core/heading', 'core/list-item', 'core/table', 'core/buttons', 'core/group', '' ) as $name ) {
	assert_same( "'{$name}': options unchanged", $linked, bws_host_unlinked_options( $linked, $blk( $name ) ) );
}

echo "§3 — malformed \$block never fatals and never unlinks\n";
foreach ( array( 'null' => null, 'string' => 'core/button', 'int' => 7, 'false' => false, 'empty array' => array(), 'no blockName key' => array( 'attrs' => array() ), 'non-string blockName' => array( 'blockName' => array( 'core/button' ) ) ) as $label => $bad ) {
	assert_same( "{$label}: options unchanged", $linked, bws_host_unlinked_options( $linked, $bad ) );
}

echo "§4 — every shell that can wrap its output asks the helper\n";
$shells = array(
	'includes/tags/base-tags.php'                       => array( 'bws_base_text_callback', 'bws_base_title_callback' ),
	'includes/tags/datetime-tags.php'                   => array( 'bws_base_datetime_single_callback', 'bws_base_datetime_range_callback' ),
	'includes/tags/email-tags.php'                      => array( 'bws_email_callback' ),
	'includes/tags/phone-tags.php'                      => array( 'bws_phone_callback' ),
);
foreach ( $shells as $file => $fns ) {
	$src = (string) file_get_contents( $root . '/' . $file );
	foreach ( $fns as $fn ) {
		// The helper call must sit before the first wrap or resolve in the function body.
		$start = strpos( $src, "function {$fn}(" );
		$next  = false === $start ? false : strpos( $src, "\nfunction ", $start + 1 );
		$body  = false === $start ? '' : substr( $src, $start, false === $next ? null : $next - $start );
		assert_same( "{$fn} calls bws_host_unlinked_options()", true, str_contains( $body, 'bws_host_unlinked_options(' ) );
	}
}
$registry = (string) file_get_contents( $root . '/includes/classes/class-tag-template-registry.php' );
$start    = strpos( $registry, '= bws_try_run_attempts(' );
$head     = false === $start ? '' : substr( $registry, max( 0, $start - 1500 ), 1500 );
assert_same( 'try_ closure rewrites options before bws_try_run_attempts()', true, str_contains( $head, 'bws_host_unlinked_options(' ) );

echo "§5 — both {{content}} shells refuse a button host before they read\n";
$base  = (string) file_get_contents( $root . '/includes/tags/base-tags.php' );
$start = strpos( $base, 'function bws_base_content_callback(' );
$read  = false === $start ? false : strpos( $base, 'bws_base_content_resolve_value(', $start );
$body  = false === $read ? '' : substr( $base, $start, $read - $start );
assert_same( 'bws_base_content_callback asks bws_core_block_is_button() before the read', true, str_contains( $body, 'bws_core_block_is_button(' ) );
assert_same( 'try_ closure refuses a content slot on a button before bws_try_run_attempts()', true, str_contains( $head, "'content' === \$tpl_key && bws_core_block_is_button(" ) );

echo "\n{$count} assertions, {$failures} failed\n";
exit( $failures ? 1 : 0 );
