<?php
/**
 * Standalone unit harness for the datetime formatter layer
 * (includes/helpers/datetime-helpers.php) and the datetime option-key
 * normalizer (includes/tags/datetime-tags.php).
 *
 * No WordPress required — both files load inert (ABSPATH defined) per the
 * join-template-test.php pattern; WP calls the formatters make at RUNTIME
 * (wp_date / wp_timezone / get_option / __) are shimmed below with
 * deterministic UTC equivalents. Only pure functions are invoked:
 *
 *   - bws_format_time_range()          — #25 surface (12-hour consolidation)
 *   - bws_format_date_range()          — separator / year-omission / smart-time
 *   - bws_format_single_date_time()    — year-omission / midnight suppression
 *   - bws_resolve_time_only_format()   — custom → separate time field → field's own tokens →
 *                                        WP-default chain (v1.7.4)
 *   - bws_build_single_format() / bws_build_range_format() — format assembly
 *   - bws_format_time_display()       — the ONE time display, single core and range core
 *                                       alike
 *   - the option-key normalizer        — public→core key mapping (FW-2 baseline;
 *     bws_normalize_datetime_options(), the one parse site. The pre-FW-2
 *     mapper fallback went with the wrappers themselves in 1.21.0 —
 *     expectations survived both the rewrite and the delete, unchanged)
 *
 * Baseline discipline (#48 Stage 0a): expectations were captured from CURRENT
 * behavior before the FW-2/#25/#30 pass; behavior-preserving stages must keep
 * them green unchanged. Current-year cases derive their inputs from the live
 * clock (year-omission compares against wp_date('Y')) — deterministic per-run.
 *
 * Run:
 *   php tools/test/datetime-format-test.php
 *
 * Exit 0 = all pass, 1 = any failure.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

// --- WP shims (deterministic, UTC) ---
if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}
if ( ! function_exists( 'wp_timezone' ) ) {
	function wp_timezone() { return new DateTimeZone( 'UTC' ); }
}
if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $timestamp = null ) {
		$dt = new DateTime( '@' . ( null === $timestamp ? time() : $timestamp ) );
		$dt->setTimezone( wp_timezone() );
		return $dt->format( $format );
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		$map = array( 'date_format' => 'F j, Y', 'time_format' => 'g:i A' );
		return $map[ $name ] ?? $default;
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
}
if ( ! function_exists( 'wp_validate_boolean' ) ) {
	// Core's body, verbatim (wp-includes/functions.php).
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

require __DIR__ . '/../../includes/helpers/slot-fold.php'; // bws_flag_is_on().
require __DIR__ . '/../../includes/helpers/registration-helpers.php'; // bws_use_effective(): the merged tag's `use`.
require __DIR__ . '/../../includes/helpers/datetime-helpers.php';
require __DIR__ . '/../../includes/tags/datetime-tags.php';

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
	echo '       expected: ' . json_encode( $expected ) . "\n";
	echo '       actual:   ' . json_encode( $actual ) . "\n";
}

/** DateTime in the shimmed (UTC) timezone. */
function dt( string $str ): DateTime {
	return new DateTime( $str, new DateTimeZone( 'UTC' ) );
}

/** Normalize single-tag options through the one parse site. */
function norm_single( array $options ): array {
	return bws_normalize_datetime_options( $options, false );
}

/** Normalize range-tag options through the one parse site. */
function norm_range( array $options ): array {
	return bws_normalize_datetime_options( $options, true );
}

/** Pick a key subset for comparison (missing keys reported as '(unset)'). */
function pick( array $arr, array $keys ): array {
	$out = array();
	foreach ( $keys as $k ) {
		$out[ $k ] = array_key_exists( $k, $arr ) ? $arr[ $k ] : '(unset)';
	}
	return $out;
}

$cy      = wp_date( 'Y' );                 // current year (shimmed UTC clock)
$ny      = (string) ( (int) $cy + 1 );
$empty_result = array(
	'date'      => null,
	'has_time'  => false,
	'time_only' => null,
	'formats'   => array( 'date_format' => null, 'time_format' => null, 'combined_format' => null ),
);

// ===========================================================================
echo "N — option-key normalization (public keys → core keys; FW-2 baseline)\n";
// ===========================================================================

assert_same(
	'N1 single defaults: empty options → auto format, omit year, smart time',
	array(
		'date_time_field' => '(unset)',
		'time_field'      => '(unset)',
		'format_type'     => 'auto',
		'custom_format'   => '(unset)',
		'date_only'       => false,
		'time_only'       => false,
		'omit_current_year' => true,
		'hide_midnight'   => true,
		'fallback'        => '(unset)',
	),
	pick( norm_single( array() ), array(
		'date_time_field', 'time_field', 'format_type', 'custom_format',
		'date_only', 'time_only', 'omit_current_year', 'hide_midnight', 'fallback',
	) )
);

assert_same(
	'N2 single full: key/timeKey/format/timeSep/show* mapped; fallback passes through',
	array(
		'date_time_field'     => 'event_date',
		'time_field'          => 'event_time',
		'format_type'         => 'custom',
		'custom_format'       => 'Y-m-d',
		'date_time_separator' => ' @ ',
		'omit_current_year'   => false,
		'hide_midnight'       => false,
		'fallback'            => 'TBA',
	),
	pick( norm_single( array(
		'key'             => 'event_date',
		'timeKey'         => 'event_time',
		'format'          => 'Y-m-d',
		'timeSep'         => ' @ ',
		'showCurrentYear' => '1',
		'showMidnight'    => '1',
		'fallback'        => 'TBA',
	) ), array(
		'date_time_field', 'time_field', 'format_type', 'custom_format',
		'date_time_separator', 'omit_current_year', 'hide_midnight', 'fallback',
	) )
);

assert_same(
	'N3 single as:date → date_only true / time_only false',
	array( 'date_only' => true, 'time_only' => false ),
	pick( norm_single( array( 'as' => 'date' ) ), array( 'date_only', 'time_only' ) )
);

assert_same(
	'N4 single as:time → time_only true / date_only false',
	array( 'date_only' => false, 'time_only' => true ),
	pick( norm_single( array( 'as' => 'time' ) ), array( 'date_only', 'time_only' ) )
);

assert_same(
	'N5 range: start/end keys mapped, single-tag field keys unset, rangeSep → separator',
	array(
		'start_field'      => 'start_date',
		'start_time_field' => 'start_time',
		'end_field'        => 'end_date',
		'end_time_field'   => 'end_time',
		'separator'        => ' to ',
		'date_time_field'  => '(unset)',
		'time_field'       => '(unset)',
	),
	pick( norm_range( array(
		'startKey'     => 'start_date',
		'startTimeKey' => 'start_time',
		'endKey'       => 'end_date',
		'endTimeKey'   => 'end_time',
		'rangeSep'     => ' to ',
	) ), array(
		'start_field', 'start_time_field', 'end_field', 'end_time_field',
		'separator', 'date_time_field', 'time_field',
	) )
);

assert_same(
	'N6 range inherits base mapping (format/showCurrentYear/showMidnight)',
	array(
		'format_type'       => 'custom',
		'custom_format'     => 'g:i',
		'omit_current_year' => false,
		'hide_midnight'     => true,
	),
	pick( norm_range( array(
		'format'          => 'g:i',
		'showCurrentYear' => '1',
	) ), array( 'format_type', 'custom_format', 'omit_current_year', 'hide_midnight' ) )
);

// #92: a hand-written `:false` / `:0` reads as OFF, the same as absent.
assert_same(
	'N7 showCurrentYear:false / showMidnight:false read as absent (flags off)',
	array( 'omit_current_year' => true, 'hide_midnight' => true ),
	pick( norm_single( array(
		'showCurrentYear' => 'false',
		'showMidnight'    => 'false',
	) ), array( 'omit_current_year', 'hide_midnight' ) )
);

// FW-81: the merged tag's one `key` option. `;` separates reads (the entry count is the
// mode), `+` joins a primary field to a supplementary time field, a third operand is ignored.
$reads_of = static function ( array $options ): array {
	return pick( bws_normalize_datetime_options( $options, null ), array(
		'is_range', 'date_time_field', 'time_field',
		'start_field', 'start_time_field', 'end_field', 'end_time_field',
	) );
};
$unset6 = array(
	'is_range' => '(unset)', 'date_time_field' => '(unset)', 'time_field' => '(unset)',
	'start_field' => '(unset)', 'start_time_field' => '(unset)',
	'end_field' => '(unset)', 'end_time_field' => '(unset)',
);

assert_same( 'N8 one entry is a single read',
	array_merge( $unset6, array( 'is_range' => false, 'date_time_field' => 'event_datetime' ) ),
	$reads_of( array( 'key' => 'event_datetime' ) )
);
assert_same( 'N9 `a+b` is one read from two fields',
	array_merge( $unset6, array( 'is_range' => false, 'date_time_field' => 'event_date', 'time_field' => 'start_time' ) ),
	$reads_of( array( 'key' => 'event_date+start_time' ) )
);
assert_same( 'N10 a third operand is ignored',
	$reads_of( array( 'key' => 'a+b' ) ),
	$reads_of( array( 'key' => 'a+b+c' ) )
);
assert_same( 'N11 two entries are a range, start then end',
	array_merge( $unset6, array( 'is_range' => true, 'start_field' => 'start_dt', 'end_field' => 'end_dt' ) ),
	$reads_of( array( 'key' => 'start_dt;end_dt' ) )
);
assert_same( 'N12 `a+b;c` is a range whose start is two fields and whose end is a time-only field',
	array_merge( $unset6, array( 'is_range' => true, 'start_field' => 'event_date', 'start_time_field' => 'start_time', 'end_field' => 'end_time' ) ),
	$reads_of( array( 'key' => 'event_date+start_time;end_time' ) )
);
assert_same( 'N13 `a;` is still a range (an empty second read), `a;b;c` ignores the third',
	array( true, '', 'b' ),
	array(
		$reads_of( array( 'key' => 'a;' ) )['is_range'],
		$reads_of( array( 'key' => 'a;' ) )['end_field'],
		$reads_of( array( 'key' => 'a;b;c' ) )['end_field'],
	)
);
assert_same( 'N14 a blank or absent `key` is a read-less single (the fallback path)',
	array_merge( $unset6, array( 'is_range' => false ) ),
	$reads_of( array( 'key' => '  ' ) )
);
assert_same( 'N14b ...and so is no key at all',
	$reads_of( array( 'key' => '' ) ),
	$reads_of( array() )
);
assert_same( 'N15 the old range keys still derive a range, the old single keys a single',
	array( true, false ),
	array(
		bws_normalize_datetime_options( array( 'endKey' => 'x' ), null )['is_range'],
		bws_normalize_datetime_options( array( 'key' => 'x', 'timeKey' => 't' ), null )['is_range'],
	)
);
assert_same( 'N16 a stray `key` beside the old range keys stays inert on the old range tag',
	array( 'start_field' => 'a', 'end_field' => '(unset)' ),
	pick( norm_range( array( 'startKey' => 'a', 'key' => 'x;y' ) ), array( 'start_field', 'end_field' ) )
);
assert_same( 'N17 the old tags\' fixed families ignore the derive: a single stays single on `a;b`',
	array( 'date_time_field' => 'a', 'is_range' => false ),
	pick( norm_single( array( 'key' => 'a;b' ) ), array( 'date_time_field', 'is_range' ) )
);
assert_same( 'N18 the old `timeKey` wins over a `+` operand (the old family keeps its own spelling)',
	'old_t',
	norm_single( array( 'key' => 'a+new_t', 'timeKey' => 'old_t' ) )['time_field']
);

// ===========================================================================
echo "\nV — verbs and `use` (FW-81)\n";
// ===========================================================================

// `use` lists the reads in order once a verb is present; each `key` in it takes the next
// `key` entry and a verb takes none. Everything below goes through the merged tag's normalizer.
$vreads = static function ( array $options ): array {
	return pick( bws_normalize_datetime_options( $options, null ), array(
		'is_range', 'verb', 'date_time_field', 'time_field',
		'start_verb', 'start_field', 'start_time_field',
		'end_verb', 'end_field', 'end_time_field',
	) );
};
$unsetv = array(
	'is_range' => '(unset)', 'verb' => '(unset)', 'date_time_field' => '(unset)', 'time_field' => '(unset)',
	'start_verb' => '(unset)', 'start_field' => '(unset)', 'start_time_field' => '(unset)',
	'end_verb' => '(unset)', 'end_field' => '(unset)', 'end_time_field' => '(unset)',
);

foreach ( array( 'now', 'published', 'modified' ) as $verb ) {
	assert_same( "V1 `use:{$verb}` alone is a single verb read",
		array_merge( $unsetv, array( 'is_range' => false, 'verb' => $verb ) ),
		$vreads( array( 'use' => $verb ) )
	);
}
assert_same( 'V2 a field then a verb: a range from the field until now',
	array_merge( $unsetv, array( 'is_range' => true, 'start_field' => 'event_date', 'start_time_field' => 'start_time', 'end_verb' => 'now' ) ),
	$vreads( array( 'use' => 'key;now', 'key' => 'event_date+start_time' ) )
);
assert_same( 'V3 a verb then a field: a range from now until the field (position is the difference)',
	array_merge( $unsetv, array( 'is_range' => true, 'start_verb' => 'now', 'end_field' => 'end_date' ) ),
	$vreads( array( 'use' => 'now;key', 'key' => 'end_date' ) )
);
assert_same( 'V4 a verb takes no `key` entry: now;key;key hands its two entries to the two fields, in order',
	array( 'is_range' => true, 'start_verb' => 'now', 'start_field' => '(unset)', 'end_field' => 'a' ),
	array_intersect_key( $vreads( array( 'use' => 'now;key;key', 'key' => 'a;b' ) ), array_flip( array( 'is_range', 'start_verb', 'start_field', 'end_field' ) ) )
);
assert_same( 'V5 two verbs, no key at all, are a range',
	array_merge( $unsetv, array( 'is_range' => true, 'start_verb' => 'published', 'end_verb' => 'now' ) ),
	$vreads( array( 'use' => 'published;now' ) )
);
assert_same( 'V6 an all-`key` use normalizes away: use:key;key|key:a;b reads as key:a;b',
	$vreads( array( 'key' => 'a;b' ) ),
	$vreads( array( 'use' => 'key;key', 'key' => 'a;b' ) )
);
assert_same( 'V7 ...and use:key alone, with or without a key entry, as no use at all',
	array( $vreads( array( 'key' => 'a' ) ), $vreads( array() ) ),
	array( $vreads( array( 'use' => 'key', 'key' => 'a' ) ), $vreads( array( 'use' => 'key' ) ) )
);
assert_same( 'V8 use:key|key:a;b is the range key:a;b says (the normalized-away form must not move output)',
	true,
	$vreads( array( 'use' => 'key', 'key' => 'a;b' ) )['is_range']
);
assert_same( 'V9 a missing entry is an empty read: use:key;now with no key is a range with an empty start',
	array_merge( $unsetv, array( 'is_range' => true, 'start_field' => '', 'end_verb' => 'now' ) ),
	$vreads( array( 'use' => 'key;now' ) )
);
assert_same( 'V10 a surplus entry is ignored: use:now;key|key:a;b;c reads only a',
	$vreads( array( 'use' => 'now;key', 'key' => 'a' ) ),
	$vreads( array( 'use' => 'now;key', 'key' => 'a;b;c' ) )
);
assert_same( 'V11 a verb with a key beside it ignores the key: use:now|key:a is the single verb read',
	$vreads( array( 'use' => 'now' ) ),
	$vreads( array( 'use' => 'now', 'key' => 'a' ) )
);
assert_same( 'V12 a name that is neither `key` nor a verb is dropped, not read as an empty read',
	$vreads( array( 'key' => 'a' ) ),
	$vreads( array( 'use' => 'nope;key', 'key' => 'a' ) )
);
assert_same( 'V13 the old tags have no `use`: their families ignore it',
	array( 'date_time_field' => 'a', 'verb' => '(unset)' ),
	pick( norm_single( array( 'use' => 'now', 'key' => 'a' ) ), array( 'date_time_field', 'verb' ) )
);
assert_same( 'V13b ...and so does the old range family',
	array( 'start_field' => 'a', 'start_verb' => '(unset)' ),
	pick( norm_range( array( 'use' => 'now;key', 'startKey' => 'a' ) ), array( 'start_field', 'start_verb' ) )
);
assert_same( 'V14 a `+` pair rides its own entry across a verb: use:now;key|key:d+t',
	array_merge( $unsetv, array( 'is_range' => true, 'start_verb' => 'now', 'end_field' => 'd', 'end_time_field' => 't' ) ),
	$vreads( array( 'use' => 'now;key', 'key' => 'd+t' ) )
);

// The verb's parse result: `now` a timed moment, a post date a date, anything else nothing.
function get_post_datetime( $post, $field = 'date' ) {
	$map = array( 7 => array( 'date' => '2026-03-03 09:15:00', 'modified' => '2026-04-04 18:30:00' ) );
	return isset( $map[ $post ] ) ? new DateTimeImmutable( $map[ $post ][ $field ], wp_timezone() ) : false;
}
$post7  = array( 'kind' => 'post', 'id' => 7 );
$vr_now = bws_datetime_verb_result( 'now', array( 'kind' => 'term', 'id' => 3 ) );
assert_same( 'V16 `now` reads on any source, and carries a time',
	array( true, true ),
	array( $vr_now['date'] instanceof DateTime, $vr_now['has_time'] )
);
assert_same( 'V17 `published` is the post date, as a date (no time shown)',
	array( '2026-03-03 09:15', false ),
	array( bws_datetime_verb_result( 'published', $post7 )['date']->format( 'Y-m-d H:i' ), bws_datetime_verb_result( 'published', $post7 )['has_time'] )
);
assert_same( 'V18 `modified` is the post modified date',
	'2026-04-04 18:30',
	bws_datetime_verb_result( 'modified', $post7 )['date']->format( 'Y-m-d H:i' )
);
assert_same( 'V19 a post verb on a term, the site, a row, no entity, or a post with no date reads nothing',
	array( null, null, null, null, null ),
	array(
		bws_datetime_verb_result( 'published', array( 'kind' => 'term', 'id' => 7 ) )['date'],
		bws_datetime_verb_result( 'published', array( 'kind' => 'site' ) )['date'],
		bws_datetime_verb_result( 'modified', array( 'kind' => 'meta_row' ) )['date'],
		bws_datetime_verb_result( 'published', array( 'kind' => 'post', 'id' => false ) )['date'],
		bws_datetime_verb_result( 'published', array( 'kind' => 'post', 'id' => 99 ) )['date'],
	)
);

// ===========================================================================
echo "\nF — bws_resolve_time_only_format (custom → ACF → WP default; v1.7.4)\n";
// ===========================================================================

$res_no_fmt  = $empty_result;
$res_acf_fmt = $empty_result;
$res_acf_fmt['formats']['time_format'] = 'H:i';

assert_same( 'F1 custom time-only format used verbatim',
	'g:i A',
	bws_resolve_time_only_format( array( 'format_type' => 'custom', 'custom_format' => 'g:i A' ), $res_no_fmt )
);
assert_same( 'F2 custom datetime format reduced to its time tokens',
	'g:i A',
	bws_resolve_time_only_format( array( 'format_type' => 'custom', 'custom_format' => 'F j, Y g:i A' ), $res_no_fmt )
);
assert_same( 'F3 custom 24-hour H:i kept as-is',
	'H:i',
	bws_resolve_time_only_format( array( 'format_type' => 'custom', 'custom_format' => 'H:i' ), $res_no_fmt )
);
assert_same( 'F4 custom date-only format (no time tokens) → falls to ACF time format',
	'H:i',
	bws_resolve_time_only_format( array( 'format_type' => 'custom', 'custom_format' => 'F j, Y' ), $res_acf_fmt )
);
assert_same( 'F5 no custom → ACF field time format',
	'H:i',
	bws_resolve_time_only_format( array(), $res_acf_fmt )
);
assert_same( 'F6 no custom, no ACF format → WP time_format default',
	'g:i A',
	bws_resolve_time_only_format( array(), $res_no_fmt )
);

// The field's OWN return format is the next rule down the chain: a datetime
// field whose format carries time tokens supplies them, ahead of the WordPress option.
// Every expectation below differs from the WP shim's `g:i A`, so a pass cannot come from it.
$res_combined_lower = $empty_result;
$res_combined_lower['formats']['combined_format'] = 'F j, Y g:i a';
$res_combined_24h = $empty_result;
$res_combined_24h['formats']['combined_format'] = 'Y-m-d H:i';
$res_combined_date = $empty_result;
$res_combined_date['formats']['combined_format'] = 'F j, Y';
$res_both = $empty_result;
$res_both['formats']['time_format']     = 'H:i';
$res_both['formats']['combined_format'] = 'F j, Y g:i a';

assert_same( 'F7 no custom, no separate time format → the datetime field\'s own time tokens',
	'g:i a',
	bws_resolve_time_only_format( array(), $res_combined_lower )
);
assert_same( 'F8 24-hour field format keeps its style (not the WP 12-hour option)',
	'H:i',
	bws_resolve_time_only_format( array(), $res_combined_24h )
);
assert_same( 'F9 a separate time field\'s format outranks the combined format',
	'H:i',
	bws_resolve_time_only_format( array(), $res_both )
);
assert_same( 'F10 a combined format with NO time tokens contributes nothing → WP default',
	'g:i A',
	bws_resolve_time_only_format( array(), $res_combined_date )
);
assert_same( 'F11 custom format still outranks the field\'s own tokens',
	'H:i',
	bws_resolve_time_only_format( array( 'format_type' => 'custom', 'custom_format' => 'H:i' ), $res_combined_lower )
);

// ===========================================================================
echo "\nU — bws_format_time_display (the ONE time display; single core, range core)\n";
// ===========================================================================
// Pure over parse results. One side is a start only; two sides are a time range. A side
// "has a time" when its parse says so (has_time / time_only), never because a DateTime
// exists: a date-only value carries the parser's noon safety buffer, and showing it is a leak.

$u_dt = static function ( string $when, string $combined, bool $has_time = true ) use ( $empty_result ): array {
	$r = $empty_result;
	$r['date']     = dt( $when );
	$r['has_time'] = $has_time;
	$r['formats']['combined_format'] = $combined;
	return $r;
};
$u_time_only = static function ( string $when, string $format ) use ( $empty_result ): array {
	$r = $empty_result;
	$r['time_only'] = dt( $when );
	$r['formats']['time_format'] = $format;
	return $r;
};
$show = array( 'hide_midnight' => false );
$hide = array( 'hide_midnight' => true );

assert_same( 'U1 datetime field: its own time tokens, not the WP option (field g:i a, WP g:i A)',
	'9:00 am',
	bws_format_time_display( $u_dt( '2030-08-12 09:00', 'F j, Y g:i a' ), null, $hide )
);
assert_same( 'U2 date-only field: no time part, so nothing (the 12:00 noon buffer never shows)',
	'',
	bws_format_time_display( $u_dt( '2030-08-12 12:00', 'F j, Y', false ), null, $hide )
);
assert_same( 'U3 time-only field: bare time in the field\'s own format',
	'9:00 AM',
	bws_format_time_display( $u_time_only( '2030-08-12 09:00', 'g:i A' ), null, $hide )
);
assert_same( 'U4 midnight is hidden by default (hide_midnight)',
	'',
	bws_format_time_display( $u_dt( '2030-08-12 00:00', 'F j, Y g:i A' ), null, $hide )
);
assert_same( 'U5 midnight prints once showMidnight is on (hide_midnight false)',
	'12:00 AM',
	bws_format_time_display( $u_dt( '2030-08-12 00:00', 'F j, Y g:i A' ), null, $show )
);
assert_same( 'U6 a time-only field at midnight follows the same rule (hidden)',
	'',
	bws_format_time_display( $u_time_only( '2030-08-12 00:00', 'g:i A' ), null, $hide )
);
assert_same( 'U7 two-ended: same meridiem consolidates, in the START field\'s own tokens',
	'9:00–11:30 am',
	bws_format_time_display( $u_dt( '2030-08-12 09:00', 'F j, Y g:i a' ), $u_dt( '2030-08-12 11:30', 'F j, Y g:i a' ), $hide )
);
assert_same( 'U8 two-ended time-only fields: the same range, no labels',
	'9:00–11:30 AM',
	bws_format_time_display( $u_time_only( '2030-08-12 09:00', 'g:i A' ), $u_dt( '2030-08-12 11:30', 'g:i A' ), $hide )
);
assert_same( 'U9 two-ended all-day span (00:00 to 23:59) is hidden by default',
	'',
	bws_format_time_display( $u_dt( '2030-08-12 00:00', 'F j, Y g:i A' ), $u_dt( '2030-08-12 23:59', 'F j, Y g:i A' ), $hide )
);
assert_same( 'U10 two-ended all-day span prints in full with showMidnight',
	'12:00 AM–11:59 PM',
	bws_format_time_display( $u_dt( '2030-08-12 00:00', 'F j, Y g:i A' ), $u_dt( '2030-08-12 23:59', 'F j, Y g:i A' ), $show )
);
assert_same( 'U11 a time-only start with a date-time end prints both times and drops the end\'s date',
	'9:00 AM–5:00 PM',
	bws_format_time_display( $u_time_only( '2030-08-12 09:00', 'g:i A' ), $u_dt( '2030-08-13 17:00', 'F j, Y g:i A' ), $hide )
);
assert_same( 'U12 a date-only start with a timed end shows the end\'s time alone (no noon)',
	'5:00 PM',
	bws_format_time_display( $u_dt( '2030-08-12 12:00', 'F j, Y', false ), $u_dt( '2030-08-12 17:00', 'F j, Y g:i A' ), $hide )
);
assert_same( 'U13 two date-only sides: nothing',
	'',
	bws_format_time_display( $u_dt( '2030-08-12 12:00', 'F j, Y', false ), $u_dt( '2030-08-14 12:00', 'F j, Y', false ), $hide )
);
assert_same( 'U14 custom format beats the field\'s own tokens, one side',
	'09:00',
	bws_format_time_display( $u_dt( '2030-08-12 09:00', 'F j, Y g:i a' ), null, array( 'hide_midnight' => true, 'format_type' => 'custom', 'custom_format' => 'H:i' ) )
);
assert_same( 'U15 custom format on a TIME-ONLY field is honored (it used to print a hardcoded g:i A)',
	'09:00',
	bws_format_time_display( $u_time_only( '2030-08-12 09:00', 'g:i A' ), null, array( 'hide_midnight' => true, 'format_type' => 'custom', 'custom_format' => 'H:i' ) )
);
assert_same( 'U17 an end alone is held to the same all-day sentinel as a pair: 23:59 is no time, hidden',
	'',
	bws_format_time_display( $u_dt( '2030-08-12 12:00', 'F j, Y', false ), $u_dt( '2030-08-12 23:59', 'F j, Y g:i A' ), $hide )
);
assert_same( 'U18 the same end prints once showMidnight is on',
	'11:59 PM',
	bws_format_time_display( $u_dt( '2030-08-12 12:00', 'F j, Y', false ), $u_dt( '2030-08-12 23:59', 'F j, Y g:i A' ), $show )
);
assert_same( 'U19 as:date on a time-only field: it has no date to show, so nothing',
	'',
	bws_format_time_display( $u_time_only( '2030-08-12 09:00', 'g:i A' ), null, array( 'hide_midnight' => true, 'date_only' => true ) )
);
assert_same( 'U20 as:date on a time-only start with a timed end: still nothing',
	'',
	bws_format_time_display( $u_time_only( '2030-08-12 09:00', 'g:i A' ), $u_dt( '2030-08-12 17:00', 'F j, Y g:i A' ), array( 'hide_midnight' => true, 'date_only' => true ) )
);
assert_same( 'U16 no start time at all: nothing, even when an end exists',
	'',
	bws_format_time_display( $empty_result, $u_dt( '2030-08-12 17:00', 'F j, Y g:i A' ), $hide )
);

// ===========================================================================
echo "\nT — bws_format_time_range (two-ended time; #25 baseline)\n";
// ===========================================================================

assert_same( 'T1 showing midnight, cross meridiem: both sides full 12-hour',
	'9:00 AM–5:00 PM',
	bws_format_time_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 17:00' ), false )
);
assert_same( 'T2 hiding midnight, same meridiem → consolidated single AM/PM',
	'9:00–11:30 AM',
	bws_format_time_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 11:30' ), true )
);
assert_same( 'T3 hiding midnight, cross meridiem → both sides carry AM/PM',
	'9:00 AM–5:00 PM',
	bws_format_time_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 17:00' ), true )
);
assert_same( 'T4 hiding midnight, both midnight → empty',
	'',
	bws_format_time_range( dt( '2030-08-12 00:00' ), dt( '2030-08-12 00:00' ), true )
);
assert_same( 'T5 hiding midnight, start midnight → end only',
	'2:00 PM',
	bws_format_time_range( dt( '2030-08-12 00:00' ), dt( '2030-08-12 14:00' ), true )
);
assert_same( 'T6 hiding midnight, end midnight → start only',
	'9:00 AM',
	bws_format_time_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 00:00' ), true )
);
assert_same( 'T7 showing midnight, both midnight (same instant) → single value, no range',
	'12:00 AM',
	bws_format_time_range( dt( '2030-08-12 00:00' ), dt( '2030-08-12 00:00' ), false )
);

// #25 — custom-format awareness (two-ended); shipped with the datetime pass.
assert_same( 'T8 custom 24-hour: both sides full, no consolidation (#25)',
	'09:00–17:00',
	bws_format_time_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 17:00' ), true, 'H:i' )
);
assert_same( 'T9 custom meridiem-less 12-hour: both sides full (#25)',
	'9:00–11:30',
	bws_format_time_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 11:30' ), true, 'g:i' )
);
assert_same( 'T10 custom 12-hour, same meridiem: still consolidates (#25)',
	'9:00–11:30 AM',
	bws_format_time_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 11:30' ), true, 'g:i A' )
);
assert_same( 'T11 custom 12-hour lowercase, cross meridiem: both full (#25)',
	'09:00 am–05:00 pm',
	bws_format_time_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 17:00' ), true, 'h:i a' )
);
assert_same( 'T12 midnight suppression independent of custom format (#25)',
	'17:00',
	bws_format_time_range( dt( '2030-08-12 00:00' ), dt( '2030-08-12 17:00' ), true, 'H:i' )
);
assert_same( 'T13 showing midnight, 24-hour format: both sides full (#25)',
	'09:00–17:00',
	bws_format_time_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 17:00' ), false, 'H:i' )
);

// #125 — consolidation is independent of midnight suppression. The defect's own
// combination (showing midnight + shared meridiem + 12-hour + non-midnight) was
// reachable by no row above: T1/T13 are cross-meridiem or 24-hour, so the gate
// blocked them and the welded-together branch never showed.
assert_same( 'T14 showing midnight, same meridiem → still consolidates (#125)',
	'9:00–11:30 AM',
	bws_format_time_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 11:30' ), false )
);
assert_same( 'T15 showing midnight, same meridiem, custom 12-hour → consolidates (#125)',
	'09:00–11:30 am',
	bws_format_time_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 11:30' ), false, 'h:i a' )
);
assert_same( 'T16 showing midnight, 24-hour, same meridiem: format gate still blocks (#125)',
	'09:00–11:30',
	bws_format_time_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 11:30' ), false, 'H:i' )
);

// #126 — `23:59` is the END-side "no end time" sentinel, mirroring `00:00` on the
// start side. `00:00`–`23:59` is the all-day encoding and yields no time part.
assert_same( 'T17 hiding midnight, 00:00→23:59 → empty, the all-day encoding (#126)',
	'',
	bws_format_time_range( dt( '2030-08-12 00:00' ), dt( '2030-08-12 23:59' ), true )
);
assert_same( 'T18 hiding midnight, 23:59:59 end reads the same as 23:59 (#126)',
	'',
	bws_format_time_range( dt( '2030-08-12 00:00' ), dt( '2030-08-12 23:59:59' ), true )
);
assert_same( 'T19 hiding midnight, real start + 23:59 end → start only (#126)',
	'9:00 AM',
	bws_format_time_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 23:59' ), true )
);
assert_same( 'T20 hiding midnight, real start + 23:59:59 end → start only (#126)',
	'9:00 AM',
	bws_format_time_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 23:59:59' ), true )
);
// ASYMMETRY: only `00:00` is a start sentinel and only `23:59` an end sentinel.
// Were `23:59` also read as a start sentinel, both ends would qualify and this
// would collapse to '' instead of keeping the start value.
assert_same( 'T21 hiding midnight, 23:59 as a START is a real time, not a sentinel (#126)',
	'11:59 PM',
	bws_format_time_range( dt( '2030-08-12 23:59' ), dt( '2030-08-12 23:59:30' ), true )
);
// The sentinel rides the midnight gate: asking to see midnight shows both ends.
assert_same( 'T22 showing midnight, 00:00→23:59 → both sides full (#126)',
	'12:00 AM–11:59 PM',
	bws_format_time_range( dt( '2030-08-12 00:00' ), dt( '2030-08-12 23:59' ), false )
);

// Same instant: nothing to range, whichever way the midnight flag is set.
assert_same( 'T23 same instant, non-midnight → single value',
	'9:00 AM',
	bws_format_time_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 09:00' ), true )
);
assert_same( 'T24 same instant, non-midnight, showing midnight → single value',
	'9:00 AM',
	bws_format_time_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 09:00' ), false )
);
assert_same( 'T25 same instant, midnight, hiding midnight → empty',
	'',
	bws_format_time_range( dt( '2030-08-12 00:00' ), dt( '2030-08-12 00:00' ), true )
);

// ===========================================================================
echo "\nS — bws_format_single_date_time (year omission, midnight suppression)\n";
// ===========================================================================

assert_same( 'S1 non-current year renders with year despite omit',
	'August 12, 2030',
	bws_format_single_date_time( dt( '2030-08-12 12:00' ), 'F j, Y', array( 'omit_current_year' => true ) )
);
assert_same( 'S2 current year omitted when omit set',
	'April 10',
	bws_format_single_date_time( dt( $cy . '-04-10 12:00' ), 'F j, Y', array( 'omit_current_year' => true ) )
);
assert_same( 'S3 current year kept when omit off (showCurrentYear)',
	"April 10, {$cy}",
	bws_format_single_date_time( dt( $cy . '-04-10 12:00' ), 'F j, Y', array( 'omit_current_year' => false ) )
);
assert_same( 'S4 smart time hides midnight',
	'August 12, 2030',
	bws_format_single_date_time( dt( '2030-08-12 00:00' ), 'F j, Y g:i A', array( 'hide_midnight' => true ) )
);
assert_same( 'S5 smart off keeps midnight (showMidnight)',
	'August 12, 2030 12:00 AM',
	bws_format_single_date_time( dt( '2030-08-12 00:00' ), 'F j, Y g:i A', array( 'hide_midnight' => false ) )
);
assert_same( 'S6 smart time leaves non-midnight alone',
	'August 12, 2030 3:30 PM',
	bws_format_single_date_time( dt( '2030-08-12 15:30' ), 'F j, Y g:i A', array( 'hide_midnight' => true ) )
);

// ===========================================================================
echo "\nR — bws_format_date_range (redundancy removal, separators)\n";
// ===========================================================================

$opts_plain = array( 'separator' => '–', 'omit_current_year' => true, 'hide_midnight' => true );

assert_same( 'R1 no end date → single-date render',
	'August 12, 2030',
	bws_format_date_range( dt( '2030-08-12 12:00' ), null, 'F j, Y', $opts_plain )
);
assert_same( 'R2 same day + differing times → date, time-range',
	'August 12, 2030, 9:00 AM–5:00 PM',
	bws_format_date_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 17:00' ), 'F j, Y g:i A', $opts_plain )
);
assert_same( 'R3 same day, both midnight, smart → bare date',
	'August 12, 2030',
	bws_format_date_range( dt( '2030-08-12 00:00' ), dt( '2030-08-12 00:00' ), 'F j, Y g:i A', $opts_plain )
);
assert_same( 'R4 same month collapse: end side bare day + trailing year',
	'August 1–9, 2030',
	bws_format_date_range( dt( '2030-08-01 12:00' ), dt( '2030-08-09 12:00' ), 'F j, Y', $opts_plain )
);
assert_same( 'R5 same month, current year, omit → no trailing year',
	'April 1–9',
	bws_format_date_range( dt( $cy . '-04-01 12:00' ), dt( $cy . '-04-09 12:00' ), 'F j, Y', $opts_plain )
);
assert_same( 'R6 same year cross month: year only on end side',
	'August 30–September 2, 2030',
	bws_format_date_range( dt( '2030-08-30 12:00' ), dt( '2030-09-02 12:00' ), 'F j, Y', $opts_plain )
);
assert_same( 'R7 cross-year: both years render',
	'December 30, 2030–January 2, 2031',
	bws_format_date_range( dt( '2030-12-30 12:00' ), dt( '2031-01-02 12:00' ), 'F j, Y', $opts_plain )
);
assert_same( 'R8 cross-year suppresses current-year omission',
	"December 30, {$cy}–January 2, {$ny}",
	bws_format_date_range( dt( $cy . '-12-30 12:00' ), dt( $ny . '-01-02 12:00' ), 'F j, Y', $opts_plain )
);
assert_same( 'R9 custom separator joins the two sides',
	'August 30 to September 2, 2030',
	bws_format_date_range( dt( '2030-08-30 12:00' ), dt( '2030-09-02 12:00' ), 'F j, Y',
		array( 'separator' => ' to ', 'omit_current_year' => true, 'hide_midnight' => true ) )
);
assert_same( 'R10 day-name token blocks same-month day collapse',
	'Thu, August 1–Fri, August 9, 2030',
	bws_format_date_range( dt( '2030-08-01 12:00' ), dt( '2030-08-09 12:00' ), 'D, F j, Y', $opts_plain )
);
assert_same( 'R11 time token blocks day collapse; per-side times kept',
	'August 1 9:00 AM–August 9, 2030 5:00 PM',
	bws_format_date_range( dt( '2030-08-01 09:00' ), dt( '2030-08-09 17:00' ), 'F j, Y g:i A', $opts_plain )
);
assert_same( 'R12 same-day custom 24-hour: time range in the format\'s tokens (#25)',
	'August 12, 2030, 09:00–17:00',
	bws_format_date_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 17:00' ), 'F j, Y H:i', $opts_plain )
);

// #126 — the all-day encoding, both routes through bws_format_date_range: same-day
// reaches bws_format_time_range(), multi-day reaches bws_format_multi_day_range().
// The multi-day half stripped only `00:00` before this fix, so the end side kept a
// stray 11:59 PM while the start side went bare.
assert_same( 'R13 same-day 00:00→23:59 → bare date, no stray end time (#126)',
	'August 12, 2030',
	bws_format_date_range( dt( '2030-08-12 00:00' ), dt( '2030-08-12 23:59' ), 'F j, Y g:i A', $opts_plain )
);
assert_same( 'R14 same-day 00:00→23:59:59 reads the same as 23:59 (#126)',
	'August 12, 2030',
	bws_format_date_range( dt( '2030-08-12 00:00' ), dt( '2030-08-12 23:59:59' ), 'F j, Y g:i A', $opts_plain )
);
// The day-collapse to `August 12–14` stays blocked by the format's time token
// (R10/R11) — the fix strips the VALUES, not the tokens the collapse reads.
assert_same( 'R15 multi-day 00:00→23:59 → both sides bare (#126)',
	'August 12–August 14, 2030',
	bws_format_date_range( dt( '2030-08-12 00:00' ), dt( '2030-08-14 23:59' ), 'F j, Y g:i A', $opts_plain )
);
assert_same( 'R16 multi-day 00:00→23:59:59 reads the same as 23:59 (#126)',
	'August 12–August 14, 2030',
	bws_format_date_range( dt( '2030-08-12 00:00' ), dt( '2030-08-14 23:59:59' ), 'F j, Y g:i A', $opts_plain )
);
// The all-day encoding now renders identically to the both-midnight encoding it
// has always been a spelling of — the point of the #126 fix.
assert_same( 'R16b multi-day 00:00→00:00 matches the 23:59 spelling exactly (#126)',
	bws_format_date_range( dt( '2030-08-12 00:00' ), dt( '2030-08-14 23:59' ), 'F j, Y g:i A', $opts_plain ),
	bws_format_date_range( dt( '2030-08-12 00:00' ), dt( '2030-08-14 00:00' ), 'F j, Y g:i A', $opts_plain )
);
assert_same( 'R17 multi-day, real end time is untouched by the sentinel (#126)',
	'August 12–August 14, 2030 5:00 PM',
	bws_format_date_range( dt( '2030-08-12 00:00' ), dt( '2030-08-14 17:00' ), 'F j, Y g:i A', $opts_plain )
);
assert_same( 'R18 multi-day 23:59 end, showing midnight → end time renders (#126)',
	'August 12 12:00 AM–August 14, 2030 11:59 PM',
	bws_format_date_range( dt( '2030-08-12 00:00' ), dt( '2030-08-14 23:59' ), 'F j, Y g:i A',
		array( 'separator' => '–', 'omit_current_year' => true, 'hide_midnight' => false ) )
);

// ===========================================================================
echo "\nB — format assembly (bws_build_single_format / bws_build_range_format)\n";
// ===========================================================================

$res_combined = $empty_result;
$res_combined['formats']['combined_format'] = 'F j, Y g:i A';
$res_time_fmt = $empty_result;
$res_time_fmt['formats']['time_format'] = 'H:i';
$res_combined_date = $empty_result;
$res_combined_date['formats']['combined_format'] = 'F j, Y';
$res_combined_date['formats']['time_format']     = 'H:i';

assert_same( 'B1 single: custom format wins over ACF combined',
	'Y-m-d',
	bws_build_single_format( array( 'format_type' => 'custom', 'custom_format' => 'Y-m-d' ), $res_combined, true, false )
);
assert_same( 'B2 single: auto uses ACF combined format',
	'F j, Y g:i A',
	bws_build_single_format( array(), $res_combined, true, true )
);
assert_same( 'B3 single: date-only narrows combined (time stripped)',
	'F j, Y',
	bws_build_single_format( array(), $res_combined, true, false )
);
assert_same( 'B4 single: time-only narrows combined (date stripped)',
	'g:i A',
	bws_build_single_format( array(), $res_combined, false, true )
);
assert_same( 'B5 single: no formats anywhere → WP defaults',
	'F j, Y g:i A',
	bws_build_single_format( array(), $empty_result, true, true )
);
assert_same( 'B6 single: date-only combined + include_time gap-fills ACF time format',
	'F j, Y H:i',
	bws_build_single_format( array(), $res_combined_date, true, true )
);
assert_same( 'B7 range: custom format wins',
	'F j',
	bws_build_range_format( array( 'format_type' => 'custom', 'custom_format' => 'F j' ), $res_combined, null, false )
);
assert_same( 'B8 range: time_only strips date from combined',
	'g:i A',
	bws_build_range_format( array( 'time_only' => true ), $res_combined, null, true )
);
assert_same( 'B9 range: custom time-only format kept under time_only',
	'H:i',
	bws_build_range_format( array( 'time_only' => true, 'format_type' => 'custom', 'custom_format' => 'H:i' ), $res_combined, null, true )
);
assert_same( 'B10 range: no formats → WP defaults, date-only',
	'F j, Y',
	bws_build_range_format( array(), $empty_result, null, false )
);

echo "
" . 'G — the family map: tag name to the $range argument and back' . "
";
foreach ( array(
	'datetime'            => null,
	'datetime_single'     => false,
	'datetime_range'      => true,
	'try_datetime'        => null,
	'try_datetime_single' => false,
	'try_datetime_range'  => true,
) as $tag => $family ) {
	assert_same( "G1 {$tag} is family " . var_export( $family, true ), $family, bws_datetime_family( $tag ) );
}
assert_same( 'G2 each family names its base tag',
	array( 'datetime', 'datetime_single', 'datetime_range' ),
	array( bws_datetime_family_tag( null ), bws_datetime_family_tag( false ), bws_datetime_family_tag( true ) )
);
assert_same( 'G3 a name that is no datetime tag reads as the merged family (null), as documented',
	null, bws_datetime_family( 'text' ) );

echo "\n" . ( $failures ? "FAILED {$failures}/{$count}\n" : "PASSED {$count}/{$count}\n" );
exit( $failures ? 1 : 0 );
