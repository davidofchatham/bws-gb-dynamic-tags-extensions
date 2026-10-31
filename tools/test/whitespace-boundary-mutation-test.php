<?php
/**
 * Census-mutation harness for the tag-name boundary.
 *
 * A tag whose name and options are separated by any `\s` character must migrate exactly as
 * its space-separated form does (where the name ends: MigrationRegistry::parse_tag_string()).
 * This drives the real TagConverter::migrate_string() over the real registration passes
 * (N×M renames, the pre-1.6 tags, the option migrations) and, for each tag string, compares
 * the space form against its tab, newline, vertical-tab, form-feed and carriage-return forms.
 *
 * WHY A MUTATION AND NOT A REPLAY. A replay only exercises the wire a site holds, and real
 * wire rarely holds a whitespace-separated tag, so a before/after replay of a boundary change
 * can show no movement and prove nothing about it. Mutating every real tag string into that
 * shape is what exercises it.
 *
 * WHAT IS COMPARED. When the space form changes, every mutated form must change to the SAME
 * bytes (the serializer writes one space). When the space form is unchanged, the mutated form
 * must come back as its own input, byte for byte.
 *
 * TWO INPUTS. The synthetic cases always run, and each must change in its space form, or the
 * section would pass by having nothing to compare. A census (`census.jsonl` from
 * bin/harvest-tags.sh, one `tag_string` per line) runs when its path is given as the first
 * argument or in BWS_CENSUS; it holds client wire and is never committed, so without one the
 * harness prints a visible skip notice for that half and still exits on the synthetic result.
 *
 * NOT COVERED. The `term_` modifier → base entries (registered by the bootstrap, which needs
 * WordPress): a census tag on a `term_*` name is compared on the entries loaded here only.
 * The modifier templates are stand-ins, as in migration-order-test.php.
 *
 * Run:  php tools/test/whitespace-boundary-mutation-test.php [census.jsonl]
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

const BWS_MUTATION_SEPARATORS = array( 'tab' => "\t", 'newline' => "\n", 'vtab' => "\x0B", 'formfeed' => "\f", 'cr' => "\r" );

/**
 * Compare one space-separated tag string against its mutated forms.
 *
 * @return bool|null Whether the space form changed; null when the string has no separator to mutate.
 */
function compare_forms( string $tag_string, string $where ): ?bool {
	if ( ! preg_match( '/^(\{\{\s*[A-Za-z0-9_]+) (.*)$/s', $tag_string, $m ) ) {
		return null;
	}
	$space   = TagConverter::migrate_string( $tag_string )['content'];
	$changed = $space !== $tag_string;
	foreach ( BWS_MUTATION_SEPARATORS as $label => $ws ) {
		$mutated = $m[1] . $ws . $m[2];
		$out     = TagConverter::migrate_string( $mutated )['content'];
		if ( $changed ) {
			check( "$where: $label form migrates to the space form's bytes", $out === $space, json_encode( array( 'in' => $mutated, 'space' => $space, 'out' => $out ) ) );
		} else {
			check( "$where: $label form is left alone, as the space form is", $out === $mutated, json_encode( array( 'in' => $mutated, 'out' => $out ) ) );
		}
	}
	return $changed;
}

echo "§W1 synthetic: each case changes in its space form, and every mutated form matches it\n";

$synthetic = array(
	'{{post_custom_text fallback_text:none|key:x}}',         // N×M rename
	'{{text src:related_post|key:related_staff|use:title}}', // option migration on a base tag
	'{{try_text key_1:a|key_2:b}}',                          // try_ slot rename + fold
	'{{current_post_meta_image key:photo}}',                 // pre-1.6 tag
);
foreach ( $synthetic as $i => $s ) {
	$changed = compare_forms( $s, 'W1.' . ( $i + 1 ) . " $s" );
	check( 'W1.' . ( $i + 1 ) . " vacuity: the space form of $s migrates at all", true === $changed );
}

echo "\n§W2 census\n";

$census = $argv[1] ?? ( getenv( 'BWS_CENSUS' ) ?: '' );
if ( '' === $census ) {
	echo "SKIP  §W2: no census given (argument or BWS_CENSUS); only the synthetic cases ran.\n";
} elseif ( ! is_readable( $census ) ) {
	check( "W2.0 census is readable: $census", false );
} else {
	$seen = array();
	$moved = 0;
	foreach ( file( $census, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $n => $line ) {
		$row = json_decode( $line, true );
		$s   = is_array( $row ) ? (string) ( $row['tag_string'] ?? '' ) : '';
		if ( '' === $s || isset( $seen[ $s ] ) ) {
			continue;
		}
		$seen[ $s ] = true;
		if ( compare_forms( $s, 'W2 line ' . ( $n + 1 ) ) ) {
			$moved++;
		}
	}
	echo '      ' . count( $seen ) . " distinct tag strings, $moved of them migrate in their space form\n";
	check( 'W2.1 the census held tag strings at all', count( $seen ) > 0 );
}

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
