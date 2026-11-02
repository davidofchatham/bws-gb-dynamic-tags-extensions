<?php
/**
 * Census harness for the Migration Tool's report-equals-run rule (#119), on the REAL registrations.
 *
 * migration-report-test.php pins the rule on synthetic entries. This drives it over the entries
 * the plugin ships: for every tag string, TagConverter::classify_tag() says `convert` exactly
 * when TagConverter::migrate_string() changes it, and its `string` is what the run writes.
 * It also counts the strings the OLD report would have listed and the run leaves alone (a deprecated
 * name whose transform changes nothing, or an option entry whose key matches and whose transform
 * moves nothing), which is the number this change exists to take to zero.
 *
 * TWO INPUTS. The synthetic half always runs: a bare `{{name}}` for every registered deprecated
 * tag name, and it must contain at least one conversion or it would pass by comparing nothing.
 * A census (`census.jsonl` from bin/harvest-tags.sh, one `tag_string` per line) runs when its path
 * is the first argument or in BWS_CENSUS; it holds client wire and is never committed, so without
 * one the harness prints a visible skip notice for that half.
 *
 * NOT COVERED. The `term_` modifier entries registered by the bootstrap need WordPress; the modifier
 * templates here are stand-ins, as in migration-order-test.php. The ownership guard sees no
 * foreign plugin, so no `declined` verdict arises; that is converter-ownership-test.php's.
 *
 * Run:  php tools/test/report-run-agreement-census-test.php [census.jsonl]
 * Exit 0 = pass, 1 = fail.
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
bws_register_early_deprecated_tag_migrations();
bws_register_option_migrations();

$pass = 0;
$fail = 0;

function check( string $label, bool $ok, string $detail = '' ): void {
	global $pass, $fail;
	if ( $ok ) {
		$pass++;
		return;
	}
	$fail++;
	echo "FAIL  $label\n";
	if ( '' !== $detail ) {
		echo "      $detail\n";
	}
}

use BWS\DynamicTags\MigrationRegistry;

$deprecated = array_flip( MigrationRegistry::get_deprecated_tag_names() );
$by_tag     = MigrationRegistry::get_option_migrations_by_tag();
$stats      = array( 'strings' => 0, 'convert' => 0, 'old_listed_noop' => 0, 'skipped' => 0, 'declined' => 0 );

/**
 * Compare the verdict with the run for one stored string.
 */
function compare_verdict( string $stored, string $where ): void {
	global $deprecated, $by_tag, $stats;
	[ $name ] = MigrationRegistry::parse_tag_string( $stored );
	if ( ! isset( $deprecated[ $name ] ) && ! isset( $by_tag[ $name ] ) ) {
		return;
	}

	$verdict = TagConverter::classify_tag( $name, $stored );
	$ran     = TagConverter::migrate_string( $stored )['content'];
	++$stats['strings'];

	if ( 'skipped' === $verdict['status'] ) {
		++$stats['skipped'];
		return; // The run has no skip channel: it rewrites what the entry produces; skipped is the entry declining the SHAPE, asserted elsewhere.
	}
	if ( 'declined' === $verdict['status'] ) {
		++$stats['declined'];
	}

	$converts = 'convert' === $verdict['status'];
	$stats['convert'] += (int) $converts;
	check( "$where: convert iff the run changes it ($stored)", $converts === ( $ran !== $stored ), json_encode( array( 'status' => $verdict['status'], 'ran' => $ran ) ) );
	if ( $converts ) {
		check( "$where: the verdict's string is the run's ($stored)", $verdict['string'] === $ran, json_encode( array( 'verdict' => $verdict['string'], 'ran' => $ran ) ) );
	}

	// What the OLD report listed: a deprecated name always, an option entry whenever its key matched.
	$old_listed = isset( $deprecated[ $name ] );
	if ( ! $old_listed ) {
		[ , $options ] = MigrationRegistry::parse_tag_string( $stored );
		foreach ( $by_tag[ $name ] as $entry ) {
			$old_listed = $old_listed || MigrationRegistry::entry_matches( $entry, array_keys( $options ), $options );
		}
	}
	if ( $old_listed && $ran === $stored && 'unchanged' === $verdict['status'] ) {
		++$stats['old_listed_noop'];
	}
}

echo "§C1 synthetic: every registered deprecated name, bare\n";
foreach ( array_keys( $deprecated ) as $name ) {
	compare_verdict( '{{' . $name . '}}', 'C1' );
}
check( 'C1.1 at least one bare deprecated name converts', $stats['convert'] > 0 );

echo "\n§C2 census\n";
$census = $argv[1] ?? ( getenv( 'BWS_CENSUS' ) ?: '' );
if ( '' === $census ) {
	echo "SKIP  §C2: no census given (argument or BWS_CENSUS); only the synthetic cases ran.\n";
} elseif ( ! is_readable( $census ) ) {
	check( "C2.0 census is readable: $census", false );
} else {
	$seen = array();
	foreach ( file( $census, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
		$row = json_decode( $line, true );
		if ( is_array( $row ) && is_string( $row['tag_string'] ?? null ) ) {
			$seen[ $row['tag_string'] ] = true;
		}
	}
	check( 'C2.1 the census held tag strings at all', count( $seen ) > 0 );
	$before = $stats;
	foreach ( array_keys( $seen ) as $stored ) {
		compare_verdict( $stored, 'C2' );
	}
	printf( "census: %d distinct strings; %d reach a registered name; %d convert; %d the old report listed and the run leaves alone; %d skipped; %d declined\n",
		count( $seen ), $stats['strings'] - $before['strings'], $stats['convert'] - $before['convert'], $stats['old_listed_noop'] - $before['old_listed_noop'], $stats['skipped'] - $before['skipped'], $stats['declined'] - $before['declined'] );
}

echo "\n";
if ( $fail ) {
	echo "FAILED: {$fail} (passed {$pass})\n";
	exit( 1 );
}
echo "PASSED: {$pass}\n";
exit( 0 );
