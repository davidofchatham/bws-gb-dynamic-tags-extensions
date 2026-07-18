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
 *   - bws_resolve_time_only_format()   — custom → ACF → WP-default chain (v1.7.4)
 *   - bws_build_single_format() / bws_build_range_format() — format assembly
 *   - bws_datetime_coerce_read_target() — FW-3a legacy-scalar → resolved-source shim
 *   - the option-key normalizer        — public→core key mapping (FW-2 baseline;
 *     calls bws_normalize_datetime_options() when it exists, else the pre-FW-2
 *     mappers — expectations survive the rewrite)
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

/** Normalize single-tag options: normalizer when present (FW-2+), else mapper. */
function norm_single( array $options ): array {
	return function_exists( 'bws_normalize_datetime_options' )
		? bws_normalize_datetime_options( $options, false )
		: bws_base_map_datetime_options( $options );
}

/** Normalize range-tag options: normalizer when present (FW-2+), else mapper. */
function norm_range( array $options ): array {
	return function_exists( 'bws_normalize_datetime_options' )
		? bws_normalize_datetime_options( $options, true )
		: bws_base_map_datetime_range_options( $options );
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
		'smart_time'      => true,
		'fallback_text'   => '(unset)',
	),
	pick( norm_single( array() ), array(
		'date_time_field', 'time_field', 'format_type', 'custom_format',
		'date_only', 'time_only', 'omit_current_year', 'smart_time', 'fallback_text',
	) )
);

assert_same(
	'N2 single full: key/timeKey/format/timeSep/show*/fallback all mapped',
	array(
		'date_time_field'     => 'event_date',
		'time_field'          => 'event_time',
		'format_type'         => 'custom',
		'custom_format'       => 'Y-m-d',
		'date_time_separator' => ' @ ',
		'omit_current_year'   => false,
		'smart_time'          => false,
		'fallback_text'       => 'TBA',
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
		'date_time_separator', 'omit_current_year', 'smart_time', 'fallback_text',
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
		'smart_time'        => true,
	),
	pick( norm_range( array(
		'format'          => 'g:i',
		'showCurrentYear' => '1',
	) ), array( 'format_type', 'custom_format', 'omit_current_year', 'smart_time' ) )
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

// ===========================================================================
echo "\nT — bws_format_time_range (two-ended time; #25 baseline)\n";
// ===========================================================================

assert_same( 'T1 non-smart: both sides full 12-hour',
	'9:00 AM–5:00 PM',
	bws_format_time_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 17:00' ), false )
);
assert_same( 'T2 smart, same meridiem → consolidated single AM/PM',
	'9:00–11:30 AM',
	bws_format_time_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 11:30' ), true )
);
assert_same( 'T3 smart, cross meridiem → both sides carry AM/PM',
	'9:00 AM–5:00 PM',
	bws_format_time_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 17:00' ), true )
);
assert_same( 'T4 smart, both midnight → empty',
	'',
	bws_format_time_range( dt( '2030-08-12 00:00' ), dt( '2030-08-12 00:00' ), true )
);
assert_same( 'T5 smart, start midnight → end only',
	'2:00 PM',
	bws_format_time_range( dt( '2030-08-12 00:00' ), dt( '2030-08-12 14:00' ), true )
);
assert_same( 'T6 smart, end midnight → start only',
	'9:00 AM',
	bws_format_time_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 00:00' ), true )
);
assert_same( 'T7 non-smart, both midnight → rendered verbatim',
	'12:00 AM–12:00 AM',
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
assert_same( 'T13 non-smart custom format: both sides full (#25)',
	'09:00–17:00',
	bws_format_time_range( dt( '2030-08-12 09:00' ), dt( '2030-08-12 17:00' ), false, 'H:i' )
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
	bws_format_single_date_time( dt( '2030-08-12 00:00' ), 'F j, Y g:i A', array( 'smart_time' => true ) )
);
assert_same( 'S5 smart off keeps midnight (showMidnight)',
	'August 12, 2030 12:00 AM',
	bws_format_single_date_time( dt( '2030-08-12 00:00' ), 'F j, Y g:i A', array( 'smart_time' => false ) )
);
assert_same( 'S6 smart time leaves non-midnight alone',
	'August 12, 2030 3:30 PM',
	bws_format_single_date_time( dt( '2030-08-12 15:30' ), 'F j, Y g:i A', array( 'smart_time' => true ) )
);

// ===========================================================================
echo "\nR — bws_format_date_range (redundancy removal, separators)\n";
// ===========================================================================

$opts_plain = array( 'separator' => '–', 'omit_current_year' => true, 'smart_time' => true );

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
		array( 'separator' => ' to ', 'omit_current_year' => true, 'smart_time' => true ) )
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

// ===========================================================================
echo "\nK — bws_datetime_coerce_read_target (FW-3a legacy-scalar shim)\n";
// ===========================================================================

assert_same( 'K1 resolved-source array passes through verbatim (idempotent)',
	array( 'kind' => 'term', 'id' => 7, 'taxonomy' => 'department' ),
	bws_datetime_coerce_read_target( array( 'kind' => 'term', 'id' => 7, 'taxonomy' => 'department' ) )
);
assert_same( 'K2 \'option\' sentinel → site kind',
	array( 'kind' => 'site' ),
	bws_datetime_coerce_read_target( 'option' )
);
assert_same( 'K3 ACF term object-id string → term kind + taxonomy split',
	array( 'kind' => 'term', 'id' => 5, 'taxonomy' => 'department' ),
	bws_datetime_coerce_read_target( 'department_5' )
);
assert_same( 'K4 underscored taxonomy keeps full slug (greedy prefix)',
	array( 'kind' => 'term', 'id' => 12, 'taxonomy' => 'product_cat' ),
	bws_datetime_coerce_read_target( 'product_cat_12' )
);
assert_same( 'K5 bare int → post kind',
	array( 'kind' => 'post', 'id' => 42 ),
	bws_datetime_coerce_read_target( 42 )
);
assert_same( 'K6 numeric string → post kind (no term-regex false positive)',
	array( 'kind' => 'post', 'id' => 123 ),
	bws_datetime_coerce_read_target( '123' )
);
assert_same( 'K7 false → post kind, id false (loop-row entity-less read)',
	array( 'kind' => 'post', 'id' => false ),
	bws_datetime_coerce_read_target( false )
);
assert_same( 'K8 empty string → post kind, id false',
	array( 'kind' => 'post', 'id' => false ),
	bws_datetime_coerce_read_target( '' )
);

echo "\n" . ( $failures ? "FAILED {$failures}/{$count}\n" : "PASSED {$count}/{$count}\n" );
exit( $failures ? 1 : 0 );
