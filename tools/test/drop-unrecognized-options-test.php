<?php
/**
 * Harness for the `drop_unrecognized_options` flag on the REAL shipped migration entries.
 *
 * migrate-string-test.php (MS3) and converter-ownership-test.php (§O9) pin the mechanism on
 * synthetic entries. This holds the part they cannot: WHICH shipped entries carry the flag,
 * and what the real chains do with it. The flag widens what the converter rewrites, so its
 * scope is pinned here rather than read off deprecated-tags.php: a fourth entry gaining it
 * fails by name.
 *
 * NOT COVERED. The `term_` modifier entries registered by the bootstrap need WordPress; the
 * modifier templates here are stand-ins, as in migration-order-test.php. The ownership
 * guard sees no foreign plugin, so no `name_not_ours` verdict arises: that is
 * converter-ownership-test.php's and migrate-string-test.php MS3.4.
 *
 * Run:  php tools/test/drop-unrecognized-options-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return $s; }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $s, $d = null ) { return $s; }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
}
if ( ! function_exists( '_x' ) ) {
	function _x( $s, $c = '', $d = null ) { return $s; }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $s ) ); }
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) { return $default; }
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) { return $value; }
}
if ( ! function_exists( 'get_intermediate_image_sizes' ) ) {
	function get_intermediate_image_sizes() { return array( 'thumbnail', 'medium', 'large' ); }
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() {}
}

// Nothing is held by another plugin: ownership is converter-ownership-test.php's business.
function bws_gb_tag_name_collisions(): array { return array(); }
function bws_gb_registered_tags_now(): array { return array(); }
function bws_gb_tags_we_registered(): array { return array(); }
function bws_gb_tag_held_by_other( string $tag, array $registry, array $ours ): ?array { return null; }

$root = dirname( __DIR__, 2 );
require $root . '/includes/helpers/image-helpers.php';
require $root . '/includes/helpers/serialization-order.php';
require $root . '/includes/helpers/slot-fold.php';
require $root . '/includes/helpers/slot-fold-compile.php';
require $root . '/includes/helpers/slot-fold-migrate.php';
require $root . '/includes/helpers/join-helpers.php';
require $root . '/includes/helpers/gb-output-boundary.php';
require $root . '/includes/helpers/converter-ownership.php';
require $root . '/includes/classes/class-migration-registry.php';
require $root . '/includes/classes/class-deprecated-tag-registry.php';
require $root . '/includes/classes/class-tag-template-registry.php';
require $root . '/includes/tags/deprecated-tags.php';
require $root . '/includes/classes/admin/class-tag-converter.php';

use BWS\DynamicTags\Admin\TagConverter;
use BWS\DynamicTags\TagTemplateRegistry;

foreach ( array( 'text', 'content', 'image' ) as $key ) {
	TagTemplateRegistry::register_modifier_template( array( 'key' => $key, 'supports_try' => true ) );
}
bws_register_nxm_tag_migrations();

use BWS\DynamicTags\MigrationRegistry;

$pass = 0;
$fail = 0;

function check( string $label, bool $ok, string $detail = '' ): void {
	global $pass, $fail;
	if ( $ok ) {
		++$pass;
		echo "  ok   {$label}\n";
		return;
	}
	++$fail;
	echo "  FAIL {$label}" . ( '' !== $detail ? " — {$detail}" : '' ) . "\n";
}

echo "D1 — which shipped entries carry the flag\n";

$flagged = array();
foreach ( MigrationRegistry::get_all() as $entry ) {
	if ( ! empty( $entry['drop_unrecognized_options'] ) ) {
		$flagged[] = $entry['type'] . ':' . $entry['match_tag'];
	}
}
sort( $flagged );
check( 'D1.1 exactly the three old content tags, and no option entry',
	array( 'tag:post_content', 'tag:related_post_content', 'tag:term_related_post_content' ) === $flagged, json_encode( $flagged ) );

echo "\nD2 — the real chains\n";

$run = static fn ( string $in ): array => TagConverter::migrate_string( $in );

$r = $run( '{{post_content css_handling:strip|processing_level:basic}}' );
check( 'D2.1 the tag that was declined converts to a bare {{content}}', '{{content}}' === $r['content'] && array() === $r['declined'], json_encode( $r ) );
check( 'D2.2 …and the report says convert, to the same string',
	array( 'convert', '{{content}}' ) === array_values( array_intersect_key( TagConverter::classify_tag( 'post_content', '{{post_content css_handling:strip|processing_level:basic}}' ), array_flip( array( 'status', 'string' ) ) ) ) );

$r = $run( '{{post_content fallback_text:None|css_handling:extract|max_length:50}}' );
check( 'D2.3 renamed keys still carry over; the unrecognized ones go', '{{content fallback:None}}' === $r['content'], $r['content'] );

$r = $run( '{{related_post_content key:rel1|target_field:post_content|css_handling:strip}}' );
check( 'D2.4 related_post_content (the callback path, content target) drops the key', 1 === preg_match( '/^\{\{content src:ref\|ref:rel1\}\}$/', $r['content'] ) && array() === $r['declined'], $r['content'] );

$r = $run( '{{term_related_post_content rel:rel1|css_handling:strip}}' );
check( 'D2.5 term_related_post_content drops the key and keeps its source', false === strpos( $r['content'], 'css_handling' ) && false !== strpos( $r['content'], 'ref:rel1' ) && array() === $r['declined'], $r['content'] );

$r = $run( '{{post_content fallback_text:a|b|css_handling:strip}}' );
check( 'D2.6 a fallback text split on a pipe still declines instead of converting with its tail cut', '{{post_content fallback_text:a|b|css_handling:strip}}' === $r['content'] && array( 'post_content' => 'unknown_options' ) === $r['declined'], json_encode( $r ) );

echo "\nD3— an unflagged shipped entry is not touched by any of this\n";

$r = $run( '{{post_custom_text css_handling:strip}}' );
check( 'D3.1 an unrecognized key on another deprecated tag still declines', '{{post_custom_text css_handling:strip}}' === $r['content'] && array( 'post_custom_text' => 'unknown_options' ) === $r['declined'], json_encode( $r ) );

echo "\n";
if ( $fail ) {
	echo "FAILED: {$fail} (passed {$pass})\n";
	exit( 1 );
}
echo "PASSED: {$pass}/{$pass}\n";
exit( 0 );
