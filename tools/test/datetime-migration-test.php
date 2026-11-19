<?php
/**
 * Standalone unit harness for the pre-1.6 DATETIME option migration (#90).
 *
 * Loads the SHIPPED entries (includes/tags/deprecated-tags.php), the SHIPPED transform
 * pipeline (MigrationRegistry::run_transform) and the SHIPPED modern normalizer
 * (bws_normalize_datetime_options) — no transcribed copies of any of the three.
 *
 * The property is EQUIVALENCE, and it is asserted end to end rather than by comparing
 * option maps: legacy wire read a flag one way, migrated wire must read it the same way.
 * Both sides are run, not described.
 *
 *   D1 EQUIVALENCE — for every spelling of the two inverted booleans, the value the OLD
 *                    renderer computed equals the value the modern pipeline computes from
 *                    the migrated wire. The legacy read is the one thing here with no file
 *                    left to load (date-helpers.php's `! empty( $options['smart_time'] )`
 *                    was deleted in the 1.6 consolidation), so it is mirrored in one
 *                    expression, cited to its git ref. The modern read is shipped code.
 *   D2 ERA GATE    — the flags are injected on ABSENCE, so the transform must be able to
 *                    tell legacy wire with an unchecked box from modern wire that never had
 *                    the key. `fallback_text` triggers the entry but proves nothing (it is
 *                    renamed on every base tag); a legacy FIELD key proves era. A pre-1.6
 *                    tag NAME proves it without any option at all.
 *   D3 OUTPUT GATE — no `showMidnight` on a date-only tag, no `showCurrentYear` on a
 *                    time-only one. Both are inert where they are skipped, but migrated wire
 *                    is hand-editable (ADR 0004) and an inert flag reads as a live setting.
 *                    The date-only case also pins the fixed_options read: `as:date` arrives
 *                    at step 7, AFTER the transforms, so reading $options alone gets it wrong.
 *   D4 CASCADE     — one pass reaches a fixed point, and report agrees with run (prior art:
 *                    fold-migration-test.php §M6, related-post-src-migration-test.php §R3).
 *   D5 REGRESSION  — the `'false' === …` test that shipped from 1.6.0 to 1.17.0 is gone.
 *                    It was reachable only from hand-edited wire (GB never serializes a false
 *                    boolean) and INVERTED even there, since `! empty( 'false' )` is true.
 *
 * VERIFY BY MUTATION — six shapes are pinned by name below, each confirmed failing when its
 * fix landed:
 *   1. disable the era gate (`datetime_legacy_era()` always true)  → D2.1 fails (fallback_text
 *      alone would wrongly license injection).
 *   2. drop the pre-rename snapshot (era test reads post-rename `$options`) → D2.5 fails (the
 *      era key is already renamed away by the time the gate runs).
 *   3. blind the `fixed_options` `as` read (gate `$options['as']` only)     → D3.1 fails (a
 *      date-only tag's `as:date` arrives at step 7, after the gate).
 *   4. reverse the `fixed_options`-vs-`$options` precedence                 → D3.5 fails (the
 *      entry's `as:date` must win over wire-stated `time_only`).
 *   5. drop the `fallback_text` exclusion from the derived era list         → D2.1 fails a
 *      different way (a modern tag carrying only the universal rename key would inject).
 *   6. restore the `'false' === …` rule                                    → D5.1 fails.
 *
 * NOT covered here: whether a migrated tag RENDERS the same date. That needs real field
 * reads — testbed territory (docs/testbed.md), tools/test/datetime-test-matrix.md.
 *
 * Run:  php tools/test/datetime-migration-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

// The WP surface the loaded files touch when CALLED (nothing runs at load time).
if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $s ) ); }
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) { return $value; }
}
if ( ! function_exists( 'wp_validate_boolean' ) ) {
	// Core's body, verbatim (wp-includes/functions.php). bws_flag_is_on() reads through it.
	function wp_validate_boolean( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) && 'false' === strtolower( $value ) ) {
			return false;
		}
		return (bool) $value;
	}
}

require __DIR__ . '/../../includes/helpers/slot-fold.php';
require __DIR__ . '/../../includes/helpers/serialization-order.php';
require __DIR__ . '/../../includes/helpers/slot-fold-migrate.php';
require __DIR__ . '/../../includes/classes/class-migration-registry.php';
require __DIR__ . '/../../includes/classes/class-deprecated-tag-registry.php';
require __DIR__ . '/../../includes/classes/class-tag-template-registry.php';
require __DIR__ . '/../../includes/tags/deprecated-tags.php';
// The MODERN read half of the equivalence property.
require __DIR__ . '/../../includes/helpers/registration-helpers.php'; // bws_use_effective(): the merged tag's `use`.
require __DIR__ . '/../../includes/tags/datetime-tags.php';

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
	echo "       expected: " . var_export( $expected, true ) . "\n";
	echo "       actual:   " . var_export( $actual, true ) . "\n";
}

bws_register_nxm_tag_migrations();
bws_register_early_deprecated_tag_migrations();
bws_register_option_migrations();

/** Run the TAG-name door (a pre-1.6 tag name is rewritten whole). */
$migrate_tag = static function ( string $tag_string ): string {
	[ $tag_name ] = MigrationRegistry::parse_tag_string( $tag_string );
	return MigrationRegistry::transform_tag( $tag_name, $tag_string );
};

/** Run the OPTION door (a live tag name carrying legacy keys is rewritten in place). */
$migrate_opt = static function ( string $tag_string ): string {
	[ $tag_name ] = MigrationRegistry::parse_tag_string( $tag_string );
	return MigrationRegistry::apply_option_migration( $tag_name, $tag_string );
};

/**
 * What the PRE-1.6 renderer computed from the raw wire.
 *
 * Mirrored, not loaded: date-helpers.php:550-551 read exactly this and was deleted in the
 * 1.6 consolidation (git show fd7185d~1:includes/helpers/date-helpers.php). The whole
 * defect in #90 is that the option definition's `'default' => true` never reached the
 * callback — GB's parse_options() reports only keys literally present
 * (docs/gb-constraints.md §Option Default Serialization) — so absent read FALSY.
 */
$legacy_read = static function ( string $tag_string ): array {
	[ , $w ] = MigrationRegistry::parse_tag_string( $tag_string );
	return array(
		'hides_midnight' => ! empty( $w['smart_time'] ),
		'omits_year'     => ! empty( $w['omit_current_year'] ),
	);
};

/** What the MODERN pipeline computes from migrated wire — shipped normalizer, no mirror. */
$modern_read = static function ( string $tag_string ): array {
	[ , $o ] = MigrationRegistry::parse_tag_string( $tag_string );
	$n       = bws_normalize_datetime_options( $o );
	return array(
		'hides_midnight' => ! empty( $n['hide_midnight'] ),
		'omits_year'     => ! empty( $n['omit_current_year'] ),
	);
};

/**
 * Compare only the axes the tag actually renders. A date-only tag deliberately carries no
 * showMidnight (D3) and its midnight axis is inert either way — both datetime cores force
 * hide_midnight => false before formatting — so comparing it would assert a difference that
 * cannot reach output.
 */
$equivalent = static function ( string $legacy_wire, callable $migrate, array $axes ) use ( $legacy_read, $modern_read ): array {
	$before = $legacy_read( $legacy_wire );
	$after  = $modern_read( $migrate( $legacy_wire ) );
	return array(
		array_intersect_key( $before, array_flip( $axes ) ),
		array_intersect_key( $after, array_flip( $axes ) ),
	);
};

$both = array( 'hides_midnight', 'omits_year' );

// ===========================================================================
echo "D1 — equivalence: the migrated tag reads what the legacy tag read\n";

// Both axes live: a datetime tag states no `as`, so it renders date AND time.
foreach ( array(
	'D1.1 absent  (the common shape — author never touched either box)' => '{{post_custom_datetime_single date_time_field:d}}',
	'D1.2 bare    (checked, GB boolean serialization)'                  => '{{post_custom_datetime_single date_time_field:d|smart_time|omit_current_year}}',
	'D1.3 smart_time bare only'                                         => '{{post_custom_datetime_single date_time_field:d|smart_time}}',
	'D1.4 omit_current_year bare only'                                  => '{{post_custom_datetime_single date_time_field:d|omit_current_year}}',
	'D1.5 :0      (falsy value — read as OFF, like absent)'             => '{{post_custom_datetime_single date_time_field:d|smart_time:0|omit_current_year:0}}',
	'D1.6 :false  (hand-edited; the non-empty STRING reads as ON)'      => '{{post_custom_datetime_single date_time_field:d|smart_time:false|omit_current_year:false}}',
) as $label => $wire ) {
	[ $before, $after ] = $equivalent( $wire, $migrate_tag, $both );
	assert_eq( $label, $before, $after );
}

// Same six through the OPTION door, which is the half that reaches a tag already carrying
// a live 1.6 name. The two doors find different content (the scanner reads post_content
// only; the tag-name door runs on a deprecated name wherever it is found), so a divergence
// here would store one authored state two ways depending on which door reached it first.
foreach ( array(
	'D1.7 absent, option door'  => '{{datetime_single date_time_field:d}}',
	'D1.8 bare, option door'    => '{{datetime_single date_time_field:d|smart_time|omit_current_year}}',
	'D1.9 :false, option door'  => '{{datetime_single date_time_field:d|smart_time:false|omit_current_year:false}}',
) as $label => $wire ) {
	[ $before, $after ] = $equivalent( $wire, $migrate_opt, $both );
	assert_eq( $label, $before, $after );
}

// The wire itself, once, so a reader can see what the equivalence is made of.
assert_eq( 'D1.10 absent → both modern flags injected',
	'{{datetime_single showCurrentYear|showMidnight|key:d}}',
	$migrate_opt( '{{datetime_single date_time_field:d}}' ) );

assert_eq( 'D1.11 bare → both dropped, neither injected',
	'{{datetime_single key:d}}',
	$migrate_opt( '{{datetime_single date_time_field:d|smart_time|omit_current_year}}' ) );

// ===========================================================================
echo "\nD2 — era gate: absence only means 'unchecked' on wire that is actually legacy\n";

// fallback_text is renamed on EVERY base tag, so it can sit on an otherwise-modern
// datetime tag. It triggers the entry (the rename has to run) and must not license the
// injection — doing so flips a modern tag's year and midnight rendering. This is the
// shape that made the era gate necessary rather than optional.
assert_eq( 'D2.1 fallback_text alone: renamed, NO flags injected',
	'{{datetime_single key:x|fallback:—}}',
	$migrate_opt( '{{datetime_single key:x|fallback_text:—}}' ) );

assert_eq( 'D2.2 a legacy FIELD key is era evidence',
	'{{datetime_single showCurrentYear|showMidnight|key:x|fallback:—}}',
	$migrate_opt( '{{datetime_single date_time_field:x|fallback_text:—}}' ) );

assert_eq( 'D2.3 format_type is era evidence (no field key needed)',
	'{{datetime_single format:Y-m-d|showCurrentYear|showMidnight|key:x}}',
	$migrate_opt( '{{datetime_single key:x|format_type:custom|custom_format:Y-m-d}}' ) );

// A pre-1.6 tag NAME settles era by itself — a 'tag' entry needs no key evidence, which
// is why datetime_era_options is declared on the 'option' entries only.
assert_eq( 'D2.4 a pre-1.6 tag name is era evidence with no options at all',
	'{{datetime_single showCurrentYear|showMidnight}}',
	$migrate_tag( '{{post_custom_datetime_single}}' ) );

// The gate reads the PRE-rename snapshot. Step 3 renames date_time_field → key before the
// transforms run at step 5, so a gate reading $options at that point sees no era key and
// silently stops injecting on every tag — the failure this assertion exists to catch.
assert_eq( 'D2.5 era survives the rename that consumes its evidence',
	true,
	false !== strpos( $migrate_opt( '{{datetime_single date_time_field:d}}' ), 'showMidnight' ) );

// The hand-written early wrapper renames the same range options as the N×M ones: a key it
// left behind would reach the target as an unknown option.
assert_eq( 'D2.6 post_acf_date_time_range: end_time_field → endTimeKey',
	'{{datetime_range as:time|rangeSep:–|showMidnight|startKey:start_date_time|endTimeKey:end_time}}',
	$migrate_tag( '{{post_acf_date_time_range start_field:start_date_time|format_type:auto|separator:–|end_time_field:end_time|time_only}}' ) );
assert_eq( 'D2.7 post_acf_date_time_range: start_time_field and date_time_separator too',
	'{{datetime_range rangeSep:–|timeSep:@|showCurrentYear|showMidnight|startKey:a|startTimeKey:b|endTimeKey:c}}',
	$migrate_tag( '{{post_acf_date_time_range start_field:a|start_time_field:b|end_time_field:c|separator:–|date_time_separator:@}}' ) );

// ===========================================================================
echo "\nD3 — output gate: no flag for a part the tag does not render\n";

// as:date arrives from the entry's fixed_options at step 7 — AFTER the transforms — so
// this passes only if the transform reads the entry, not just $options.
// `as:date` trails the flags because fixed_options are injected at step 7, after them.
assert_eq( 'D3.1 date-only tag: year flag only, no showMidnight',
	'{{datetime_single as:date|showCurrentYear|key:d}}',
	$migrate_tag( '{{post_custom_date_single date_time_field:d}}' ) );

// time_only becomes as:time inside the transform (step 3), so this half is readable from
// $options — asserted anyway, because the two gates are one decision and a change to
// either should show up here.
assert_eq( 'D3.2 time-only tag: midnight flag only, no showCurrentYear',
	'{{datetime_single as:time|showMidnight|key:d}}',
	$migrate_tag( '{{post_custom_datetime_single date_time_field:d|time_only}}' ) );

// THE TWO SOURCES OF `as` DISAGREE HERE, and the entry's must win. `time_only` turns
// $options['as'] into 'time' at step 3, but the entry's fixed_options `as:date` is assigned
// unconditionally at step 7, so the tag SERIALIZES as date-only whatever the wire said.
// Gating on $options would therefore emit showMidnight onto an as:date tag — inert, but
// reading as a live setting in hand-editable wire (ADR 0004), which is exactly what D3
// exists to prevent. The shape is hand-edited (the old date tags had no time-only box);
// it is pinned because the precedence is invisible from inside the transform.
//
// `as` leads here exactly as it does in D3.1, because step 8 sorts the whole map into
// canonical order. Before that sort the two disagreed for a reason that had nothing to do
// with either gate — step 3 inserts `as` before the flags are decided and step 7 overwrites
// that key in place rather than appending, so a tag whose wire stated a shape kept the
// early slot and one that stated none did not. See §D6.
assert_eq( 'D3.5 date entry + time_only wire: the ENTRY decides the gate',
	'{{datetime_single as:date|showCurrentYear|key:d}}',
	$migrate_tag( '{{post_custom_date_single date_time_field:d|time_only}}' ) );

// The skipped axis is inert, which is the whole reason skipping it is safe: assert that
// the axis the tag DOES render still matches the legacy read.
[ $before, $after ] = $equivalent( '{{post_custom_date_single date_time_field:d}}', $migrate_tag, array( 'omits_year' ) );
assert_eq( 'D3.3 date-only: the rendered axis is still equivalent', $before, $after );

[ $before, $after ] = $equivalent( '{{post_custom_datetime_single date_time_field:d|time_only}}', $migrate_tag, array( 'hides_midnight' ) );
assert_eq( 'D3.4 time-only: the rendered axis is still equivalent', $before, $after );

// ===========================================================================
echo "\nD4 — cascade: one pass is a fixed point, and report agrees with run\n";

$once  = $migrate_opt( '{{datetime_single date_time_field:d}}' );
$twice = $migrate_opt( $once );
assert_eq( 'D4.1 migrating migrated wire changes nothing', $once, $twice );

// The flags are injected on absence, so a second pass over already-migrated wire would
// re-inject if the entry still matched. It must not match: the legacy keys are gone.
$agrees = static function ( string $tag_string ) use ( $migrate_opt ): array {
	[ $tag_name, $options ] = MigrationRegistry::parse_tag_string( $tag_string );
	$found                  = MigrationRegistry::find_option_migrations( $tag_name, array_keys( $options ), $options );
	return array( ! empty( $found ), $migrate_opt( $tag_string ) !== $tag_string );
};

assert_eq( 'D4.2 legacy wire is both reported and changed', array( true, true ), $agrees( '{{datetime_single date_time_field:d}}' ) );
assert_eq( 'D4.3 migrated wire is neither reported nor changed', array( false, false ), $agrees( $once ) );
assert_eq( 'D4.4 modern wire is neither reported nor changed', array( false, false ), $agrees( '{{datetime_single key:d|as:date}}' ) );

// ===========================================================================
echo "\nD5 — regression: the ':false'-only rule is gone\n";

// Shipped 1.6.0 → 1.17.0: `if ( 'false' === $options['smart_time'] ) { showMidnight }`.
// Unreachable from editor-written wire, and backwards on the hand-edited wire it did
// reach — `! empty( 'false' )` is true, so the old renderer HID midnight and the transform
// asked for it to be SHOWN. D1.6/D1.9 assert the equivalence; these pin the wire, so a
// reintroduction names itself instead of showing up as an equivalence failure two
// sections up.
assert_eq( 'D5.1 :false does NOT inject showMidnight',
	'{{datetime_single key:d}}',
	$migrate_opt( '{{datetime_single date_time_field:d|smart_time:false|omit_current_year:false}}' ) );

assert_eq( 'D5.2 :false is dropped, not carried through',
	true,
	false === strpos( $migrate_opt( '{{datetime_single date_time_field:d|smart_time:false}}' ), 'smart_time' ) );

// ===========================================================================
echo "\nD6 — key order is the OWNER's, not this file's\n";

// Every expectation above states a literal key order, which is a second copy of the ranking
// unless something ties it back. These assert the tie: the migrated wire is exactly what
// serialization-order.php produces from the same option set. A KEY_MAP change then fails
// HERE, naming the ranking, instead of surfacing as six unexplained string diffs.
$canonical = static function ( string $tag_string ): string {
	[ $name, $opts ] = MigrationRegistry::parse_tag_string( $tag_string );
	$sorted          = bws_serialization_order_sort_map( $opts );
	return $name . ' ' . implode( '|', array_keys( $sorted ) );
};
$emitted = static function ( string $tag_string ): string {
	[ $name, $opts ] = MigrationRegistry::parse_tag_string( $tag_string );
	return $name . ' ' . implode( '|', array_keys( $opts ) );
};

foreach ( array(
	'D6.1 tag door, both flags'  => '{{post_custom_datetime_single date_time_field:d}}',
	'D6.2 date entry, as:date'   => '{{post_custom_date_single date_time_field:d}}',
	'D6.3 option door, fallback' => '{{datetime_single date_time_field:x|fallback_text:—}}',
	'D6.4 range, many keys'      => '{{post_custom_datetime_range start_field:s|end_field:e|separator: to }}',
) as $label => $wire ) {
	$out = false !== strpos( $label, 'option door' ) ? $migrate_opt( $wire ) : $migrate_tag( $wire );
	assert_eq( $label, $canonical( $out ), $emitted( $out ) );
}

// A key the ranking does not know must still survive the sort — an unknown key silently
// dropped at step 8 would be permanent data loss on a tag the entry never meant to touch.
$unknown = $migrate_opt( '{{datetime_single date_time_field:d|zzCustomKey:keep-me}}' );
assert_eq( 'D6.5 an UNKNOWN key survives the canonical sort',
	true,
	false !== strpos( $unknown, 'zzCustomKey:keep-me' ) );

// ===========================================================================
echo "\nD7 — the merged `datetime` wire reads what each old key read (FW-81)\n";

// The six old field options and the merged tag's one `key` are two spellings of the same
// reads, so the shipped normalizer must hand the cores the same six canonical fields for
// both. Property, not examples: every old key alone, then the pairings, each against its
// equivalent `key` wire.
$six = array( 'date_time_field', 'time_field', 'start_field', 'start_time_field', 'end_field', 'end_time_field' );
$read_fields = static function ( array $options, ?bool $range ) use ( $six ): array {
	$n   = bws_normalize_datetime_options( $options, $range );
	$out = array();
	foreach ( $six as $k ) {
		$out[ $k ] = $n[ $k ] ?? '';
	}
	return $out;
};

foreach ( array(
	'D7.1 key'                       => array( array( 'key' => 'a' ), false, array( 'key' => 'a' ) ),
	'D7.2 key + timeKey'             => array( array( 'key' => 'a', 'timeKey' => 'b' ), false, array( 'key' => 'a+b' ) ),
	'D7.3 startKey'                  => array( array( 'startKey' => 'a' ), true, array( 'key' => 'a;' ) ),
	'D7.4 startKey + startTimeKey'   => array( array( 'startKey' => 'a', 'startTimeKey' => 'b' ), true, array( 'key' => 'a+b;' ) ),
	'D7.5 start + endKey'            => array( array( 'startKey' => 'a', 'endKey' => 'c' ), true, array( 'key' => 'a;c' ) ),
	'D7.6 start + endTimeKey (the end inherits the start date)' => array( array( 'startKey' => 'a', 'endTimeKey' => 'd' ), true, array( 'key' => 'a;+d' ) ),
	'D7.7 all four'                  => array( array( 'startKey' => 'a', 'startTimeKey' => 'b', 'endKey' => 'c', 'endTimeKey' => 'd' ), true, array( 'key' => 'a+b;c+d' ) ),
) as $label => list( $old, $old_range, $merged ) ) {
	assert_eq( $label, $read_fields( $old, $old_range ), $read_fields( $merged, null ) );
}

// The mode the merged tag derives is the family the old wire named, for every row above.
assert_eq( 'D7.8 old single keys derive single', false, bws_normalize_datetime_options( array( 'key' => 'a', 'timeKey' => 'b' ), null )['is_range'] );
assert_eq( 'D7.9 old range keys derive range', true, bws_normalize_datetime_options( array( 'startKey' => 'a' ), null )['is_range'] );

// ===========================================================================
echo "\nD8 — the old six keys rewritten to the one `key` (FW-81)\n";

// One corpus, two languages. The rewrite is the editor's mount step and the Migration Tool's converter
// step, so the same inputs drive the PHP rule here and the JS twin through the driver below.
// `expect` is a literal, written from the spec, never recomputed.
$corpus = json_decode( (string) file_get_contents( __DIR__ . '/datetime-mount-corpus.json' ), true );
assert_eq( 'D8.0 the shared corpus parses', true, is_array( $corpus ) && ! empty( $corpus['cases'] ) );

$as_pairs = static function ( $map ) {
	return null === $map ? null : array_map( null, array_keys( $map ), array_values( $map ) );
};

$php_pairs     = array();
$php_slot_pairs = array();
foreach ( (array) ( $corpus['cases'] ?? array() ) as $case ) {
	$range = 'range' === $case['family'];
	$got   = bws_datetime_legacy_reads_rewrite( $case['options'], $range );
	$php_pairs[] = $as_pairs( $got );
	assert_eq( 'D8.1 ' . $case['label'], $as_pairs( $case['expect'] ), $as_pairs( $got ) );

	// The try_ form: the same rewrite with the read written into slot A, not tag-level `key`.
	$got_a = bws_datetime_legacy_reads_rewrite( $case['options'], $range, true );
	$php_slot_pairs[] = $as_pairs( $got_a );
	assert_eq( 'D8.7 slot A — ' . $case['label'], $as_pairs( $case['expectTry'] ), $as_pairs( $got_a ) );

	// The property the literals stand for: slot A reads what `key` read. Parsed as a slot read
	// (the template's `try_per_slot_reads`), so the list is bws_datetime_reads()'s own.
	if ( null !== $got && null !== $got_a && isset( $got['key'] ) ) {
		$slot = bws_fold_parse_slot( (string) $got_a['A'], 'try', true );
		assert_eq( 'D8.8 slot A holds the same reads — ' . $case['label'], bws_datetime_reads( array( 'key' => $got['key'] ) ), $slot['read']['reads'] ?? '(no read)' );
	}

	// The property the literals stand for: a rewritten tag reads the same fields as the old one,
	// through the shipped normalizer in the old tag's own family.
	if ( null !== $got ) {
		// Trimmed: the cores sanitize every operand, so a padded old value reads the same.
		assert_eq( 'D8.2 same reads — ' . $case['label'], array_map( 'trim', $read_fields( $case['options'], $range ) ), $read_fields( $got, $range ) );
	}
	// The skip the report lists is exactly the end-only range, and it asks one predicate for it.
	if ( $range ) {
		assert_eq(
			'D8.3 end-only predicate — ' . $case['label'],
			! empty( $case['endOnly'] ),
			bws_datetime_legacy_range_is_end_only( $case['options'] )
		);
	}
}

$raw     = (string) shell_exec( 'node ' . escapeshellarg( __DIR__ . '/datetime-mount-driver.js' ) . ' 2>&1' );
$js_pair = json_decode( $raw, true );
if ( ! is_array( $js_pair ) ) {
	// A missing `node` FAILS rather than skips: a silent pass would hide the twin check.
	assert_eq( 'D8.4 the JS driver ran (node on PATH)', true, false );
	echo '       ' . substr( trim( $raw ), 0, 400 ) . "\n";
} else {
	assert_eq( 'D8.4 the JS driver ran (node on PATH)', true, true );
	$js_plain = $js_pair['plain'] ?? array();
	$js_slot  = $js_pair['slotA'] ?? array();
	assert_eq( 'D8.5 both sides answered every case', count( $php_pairs ), count( $js_plain ) );
	foreach ( $php_pairs as $i => $pairs ) {
		assert_eq( 'D8.6 mount twin agrees — ' . $corpus['cases'][ $i ]['label'], $pairs, array_key_exists( $i, $js_plain ) ? $js_plain[ $i ] : '(missing)' );
	}
	// The slot-A mode is a pure function the live mount does not call (the old hidden try_ tags
	// still read tag-level `key`); the twins agree on it all the same.
	assert_eq( 'D8.9 the slot-A mode answered every case', count( $php_slot_pairs ), count( $js_slot ) );
	foreach ( $php_slot_pairs as $i => $pairs ) {
		assert_eq( 'D8.10 slot-A twin agrees — ' . $corpus['cases'][ $i ]['label'], $pairs, array_key_exists( $i, $js_slot ) ? $js_slot[ $i ] : '(missing)' );
	}
}

// ===========================================================================
echo "\n" . ( $failures ? "FAILED — {$failures} of {$count}\n" : "PASSED — {$count} assertions\n" );
exit( $failures ? 1 : 0 );
