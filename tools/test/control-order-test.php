<?php
/**
 * Standalone harness for CONTROL order — the order a tag's option controls stack
 * top-to-bottom in the editor panel.
 *
 * CONTROL order IS REGISTRATION order. GB renders `options` as declared and nothing
 * reorders it; the FW-52 normalizer moves the SERIALIZED key order only, inside
 * `setState`. So the registration arrays in base-tags.php / TagTemplateRegistry ARE
 * the panel, and this harness is the only thing that reads them as one.
 *
 * WHY it exists (1.17.0): option grouping draws a box around the controls that describe
 * one decision (assets/js/option-group.js), and a group draws as ONE box only where its
 * members register CONTIGUOUSLY — the CSS joins adjacent siblings and can see nothing
 * else. That turned control order from a matter of taste into a correctness property,
 * and immediately surfaced a divergence nobody had had a reason to notice: the try_
 * constructor registered its format cluster FIRST (i.e. in serialization order, on the
 * one family that renders one), put `fallback` ahead of `link`, and appended the
 * chain-level `limit`/`sep` dead last — where, being source-group options, they drew
 * their own captionless box at the foot of the panel.
 *
 * The property asserted is CONTIGUITY, not a fixed sequence: the three constructors
 * legitimately differ in where a group sits (term_ leads with format; base and try_ do
 * not), but no constructor may SPLIT a group. Contiguity is exactly what the boxes need
 * and it generalizes to a family this file does not yet enumerate.
 *
 * Per-tag expected sequences are asserted on top of that for the shapes whose order was
 * wrong, so a regression names itself instead of showing up as a vague box complaint.
 *
 * Run:  php tools/test/control-order-test.php
 * Exit 0 = pass, 1 = fail.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

$root = dirname( __DIR__, 2 );
define( 'BWS_DYNAMIC_TAGS_PATH', $root . '/' );

// ---------------------------------------------------------------------------
// WP + GB stubs. Only what registration touches — this file never renders a tag.
// ---------------------------------------------------------------------------

if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $s, $d = null ) { return $s; }
}
foreach ( array( 'add_action', 'add_filter', 'do_action', 'register_rest_route' ) as $fn ) {
	if ( ! function_exists( $fn ) ) {
		eval( "function {$fn}() { return null; }" );
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) { return $value; }
}
if ( ! function_exists( 'get_option' ) ) {
	// Settings reads decide which modifier families generate. Empty = every default on.
	function get_option( $name, $default = false ) { return $default; }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $k ) ); }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
}

/**
 * Capturing stand-in for GB's registrar. Registration is the whole subject here, so the
 * stub keeps every tag's option array in declaration order and answers get_tags() from
 * the same store (the try_ constructor reads it for its own dup-check).
 */
class GenerateBlocks_Register_Dynamic_Tag {
	public static array $tags = array();

	public function __construct( $args ) {
		self::$tags[ $args['tag'] ] = $args;
	}

	public static function get_tags() {
		return self::$tags;
	}
}

class GenerateBlocks_Dynamic_Tags {}
class GenerateBlocks_Dynamic_Tag_Callbacks {
	public static function output( $v, $o = array(), $i = null ) { return $v; }
}

// ---------------------------------------------------------------------------
// Load in the plugin's own order (see bws-gb-dynamic-tags-extensions.php).
// ---------------------------------------------------------------------------

require $root . '/autoload.php';

foreach ( array(
	'includes/helpers/image-helpers.php',
	'includes/helpers/traversal-pipeline.php',
	'includes/helpers/field-helpers.php',
	'includes/helpers/link-helpers.php',
	'includes/helpers/preview-helpers.php',
	'includes/helpers/content-helpers.php',
	'includes/helpers/datetime-helpers.php',
	'includes/helpers/taxonomy-helpers.php',
	'includes/helpers/join-helpers.php',
	'includes/helpers/registration-helpers.php',
	'includes/helpers/serialization-order.php',
	'includes/helpers/slot-fold.php',
	'includes/helpers/slot-fold-compile.php',
	'includes/helpers/slot-fold-migrate.php',
	'includes/tags/base-shared.php',
	'includes/tags/base-tags.php',
	'includes/tags/content-tags.php',
	'includes/tags/image-tags.php',
	'includes/tags/datetime-tags.php',
	'includes/tags/email-tags.php',
	'includes/tags/phone-tags.php',
	'includes/tags/table-tags.php',
	'includes/tags/taxonomy-tags.php',
) as $rel ) {
	require_once $root . '/' . $rel;
}

bws_register_base_tags();
\BWS\DynamicTags\TagTemplateRegistry::generate_base_try_tags();

$registered = GenerateBlocks_Register_Dynamic_Tag::get_tags();

// ---------------------------------------------------------------------------

$failures = 0;
$count    = 0;

function ok( string $label ): void {
	global $count;
	$count++;
	echo "  ok   {$label}\n";
}

function fail( string $label, $expected, $actual ): void {
	global $failures, $count;
	$count++;
	$failures++;
	echo "  FAIL {$label}\n";
	echo "       expected: " . json_encode( $expected ) . "\n";
	echo "       actual:   " . json_encode( $actual ) . "\n";
}

function assert_same( string $label, $expected, $actual ): void {
	if ( $expected === $actual ) {
		ok( $label );
		return;
	}
	fail( $label, $expected, $actual );
}

/**
 * The group each registered option landed in, in registration order.
 *
 * Reads the `_group` the registration pass STAMPED rather than re-consulting the map:
 * an option that never went through bws_prepare_registration_options() carries no stamp,
 * and that is itself worth catching — an unstamped option renders unboxed no matter
 * what the map says about its name.
 *
 * @param array $options Registered options, in declaration order.
 * @return array<int,array{0:string,1:string}> [ option name, group ] for grouped options only.
 */
function grouped_sequence( array $options ): array {
	$seq = array();
	foreach ( $options as $name => $def ) {
		if ( ! empty( $def['_group'] ) ) {
			$seq[] = array( (string) $name, (string) $def['_group'] );
		}
	}
	return $seq;
}

// ===========================================================================
echo "\n§1 Every group's members register CONTIGUOUSLY\n";
// ===========================================================================
// The invariant the boxes stand on. A group that appears, stops, and appears again
// draws TWO boxes for one decision — which is what the author reads as a stray
// control, and is undiagnosable from the CSS end.

foreach ( $registered as $tag => $args ) {
	$seq    = grouped_sequence( $args['options'] ?? array() );
	$seen   = array();
	$broken = array();
	$prev   = '';

	foreach ( $seq as $pair ) {
		list( $name, $group ) = $pair;
		if ( $group !== $prev ) {
			if ( isset( $seen[ $group ] ) ) {
				$broken[] = $group . ' resumes at ' . $name;
			}
			$seen[ $group ] = true;
			$prev           = $group;
		}
	}

	assert_same( "{{{$tag}}} — no group is split", array(), $broken );
}

// ===========================================================================
echo "\n§2 try_ registers in canonical control order\n";
// ===========================================================================
// source → format → link → fallback, with the attempts standing in for the source and
// the tag-level field reads inside it (same as every base tag: limit/sep and the field
// keys precede format). `fallback` is ungrouped, so it is asserted by position below
// rather than by group.

$try_groups = array();
foreach ( $registered as $tag => $args ) {
	if ( 0 !== strpos( $tag, 'try_' ) ) {
		continue;
	}
	$order = array();
	foreach ( grouped_sequence( $args['options'] ?? array() ) as $pair ) {
		if ( end( $order ) !== $pair[1] ) {
			$order[] = $pair[1];
		}
	}
	$try_groups[ $tag ] = $order;
}

assert_same(
	'try_ tags were generated',
	true,
	count( $try_groups ) > 0
);

foreach ( $try_groups as $tag => $order ) {
	// The canonical sequence with the groups this tag does not carry removed — a
	// subsequence check, so `try_permalink` (no link, no format) passes on the same rule
	// as `try_datetime_range` (all four).
	$canonical = array_values( array_intersect( array( 'source', 'field', 'format', 'link' ), $order ) );
	assert_same( "{{{$tag}}} — group sequence", $canonical, $order );
}

// The two that were actually wrong, pinned by exact option list so a regression names
// the option rather than the group.
assert_same(
	'{{try_text}} — full option order',
	array( 'A', 'B', 'C', 'D', 'E', 'limit', 'sep', 'linkTo', 'linkKey', 'newTab', 'fallback' ),
	array_keys( $registered['try_text']['options'] ?? array() )
);

assert_same(
	'{{try_datetime_range}} — full option order',
	array(
		'A', 'B', 'C', 'D', 'E',
		'startKey', 'startTimeKey', 'endKey', 'endTimeKey',
		// The two checkboxes register showCurrentYear FIRST. tag-reference.md §datetime
		// listed them the other way round from 1.16.0 until this harness read the
		// registration.
		//
		// The doc was corrected to the CODE here, which INVERTS the standing rule —
		// tag-reference.md is authoritative and drift normally resolves by changing the
		// code to match it. Allowed as a one-off (user, 2026-08-06) because the doc row
		// stated no preference and neither order is better, so the shipped one is what
		// authors already see. Not precedent: the next drift moves the code.
		'as', 'rangeSep', 'format', 'timeSep', 'showCurrentYear', 'showMidnight',
		'linkTo', 'linkKey', 'newTab',
		'fallback',
	),
	array_keys( $registered['try_datetime_range']['options'] ?? array() )
);

// ===========================================================================
echo "\n§3 fallback is last wherever it is registered\n";
// ===========================================================================
// Ungrouped and global, on every constructor. It sat AHEAD of the link cluster on try_
// until 1.17.0 because the trailing merge carried it, which is the same edit that put
// limit/sep at the bottom — one cause, two symptoms.

foreach ( $registered as $tag => $args ) {
	$keys = array_keys( $args['options'] ?? array() );
	if ( ! in_array( 'fallback', $keys, true ) ) {
		continue;
	}
	assert_same( "{{{$tag}}} — fallback registers last", 'fallback', end( $keys ) );
}

// ===========================================================================
echo "\n§4 A group's LEAD is present wherever the group is\n";
// ===========================================================================
// A lone non-lead member renders BARE (the opt-out in option-group.js), so a group that
// registers without its lead and without a second member shows no box at all. `link` is
// the deliberate exception: it has no lead, by design — the box appears only once a link
// is configured, which is the one group that is genuinely optional.

$leads = array();
foreach ( bws_option_visual_groups() as $name => $spec ) {
	if ( $spec['lead'] ) {
		$leads[ $spec['group'] ][] = $name;
	}
}

foreach ( $registered as $tag => $args ) {
	$options = $args['options'] ?? array();
	$members = array();
	foreach ( grouped_sequence( $options ) as $pair ) {
		$members[ $pair[1] ][] = $pair[0];
	}
	foreach ( $members as $group => $names ) {
		if ( 'link' === $group || count( $names ) > 1 ) {
			continue;
		}
		$has_lead = (bool) array_intersect( $names, $leads[ $group ] ?? array() );
		assert_same( "{{{$tag}}} — lone `{$group}` member is a lead", true, $has_lead );
	}
}

// ---------------------------------------------------------------------------

echo "\n";
if ( $failures ) {
	echo "FAILED: {$failures} of {$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
