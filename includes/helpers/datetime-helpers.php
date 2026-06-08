<?php
/**
 * BWS Date/Time Helper Functions
 *
 * Utility and helper functions for date/time parsing, formatting, and ACF integration.
 * Extracted from date-time-tags.php to support modular architecture.
 *
 * Includes:
 * - Format utilities (has_time, has_date, clean_format, extract_time, remove_time, etc.)
 * - ACF field value and return format retrieval
 * - Combined date/time parsing from separate or combined fields
 * - Intelligent date value parsing with timezone handling
 * - Single and range format string building
 * - Single date/time formatting with smart options
 * - Date range formatting with redundancy removal
 * - Same-day and multi-day range formatting
 * - Time range formatting with AM/PM consolidation
 * - Fallback handling for missing date/time values
 *
 * @package BWS_Dynamic_Tags
 * @since 3.1.3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ===============================================
// CORE UTILITIES
// ===============================================

/**
 * Format utilities collection
 *
 * @since 3.0.0
 * @return array Collection of utility functions
 */
if ( ! function_exists( 'bws_format_utils' ) ) {
function bws_format_utils() {
    static $utils = null;

    if ( null === $utils ) {
        $utils = [
            'has_time' => function( $format ) {
                return false !== strpbrk( $format, 'GHghisuAa' );
            },
            'has_date' => function( $format ) {
                return false !== strpbrk( $format, 'dDjlNSwzWFmMnYyLo' );
            },
            'clean_format' => function( $format ) {
                return trim( preg_replace( '/\s+/', ' ', $format ) );
            },
            'extract_time' => function( $format ) {
                if ( preg_match( '/[GH]:[i](?::[s])?/', $format, $matches ) ) {
                    return $matches[0]; // 24-hour format
                }
                if ( preg_match( '/[gh]:[i](?::[s])?\s*[Aa]/', $format, $matches ) ) {
                    return $matches[0]; // 12-hour format
                }
                return 'g:i A'; // Default fallback
            },
            'remove_time' => function( $format ) {
                // Remove common time patterns
                $patterns = [
                    '/\s*[GH]:[i](?::[s])?\s*/',     // 24-hour time
                    '/\s*[gh]:[i](?::[s])?\s*[Aa]\s*/', // 12-hour time with AM/PM
                    '/\s*[GHgh]\s*/',                // Standalone hour
                    '/\s*[is]\s*/',                  // Standalone minute/second
                    '/\s*[Aa]\s*/',                  // Standalone AM/PM
                ];

                foreach ( $patterns as $pattern ) {
                    $format = preg_replace( $pattern, ' ', $format );
                }

                $format = preg_replace( '/\s+/', ' ', $format );

                // Clean up trailing comma/separator that was before the time
                $format = preg_replace( '/,\s*$/', '', $format );
                $format = preg_replace( '/\s*–\s*$/', '', $format );

                return trim( $format );
            },
            'remove_year' => function( $format ) {
                // Drop the year token (Y/y) wherever it sits in the format, not
                // just before a comma or at the end. A datetime format like
                // "F j, Y g:i A" has the year mid-string followed by a space and
                // the time, which the old comma/end-anchored patterns missed —
                // leaving the year in consolidated range output (e.g.
                // "April 1, 2026 3:27 PM–30").
                //
                // Strategy: remove the year token plus one adjacent separator
                // (leading ", " / " " or, if year is first, a trailing one),
                // then normalise leftover punctuation/whitespace.
                $format = preg_replace( '/,\s*[Yy]\b/', '', $format );   // ", Y" → ""  (e.g. "F j, Y g:i" → "F j g:i")
                $format = preg_replace( '/\s+[Yy]\b/', '', $format );    // " Y"  → ""  (e.g. "j Y" → "j")
                $format = preg_replace( '/^[Yy]\b[,\s\-\/]*/', '', $format ); // leading "Y, " / "Y " / "Y-" → ""

                // Normalise: collapse doubled separators left behind.
                $format = preg_replace( '/\s+/', ' ', $format );
                $format = preg_replace( '/,\s*,/', ',', $format );
                $format = preg_replace( '/^\s*,\s*/', '', $format );
                $format = preg_replace( '/,\s*$/', '', $format );

                return trim( $format );
            },
            'remove_time' => function( $format ) {
                // Remove common time patterns
                $patterns = [
                    '/\s*[GH]:[i](?::[s])?\s*/',     // 24-hour time
                    '/\s*[gh]:[i](?::[s])?\s*[Aa]\s*/', // 12-hour time with AM/PM
                    '/\s*[GHgh]\s*/',                // Standalone hour
                    '/\s*[is]\s*/',                  // Standalone minute/second
                    '/\s*[Aa]\s*/',                  // Standalone AM/PM
                ];

                foreach ( $patterns as $pattern ) {
                    $format = preg_replace( $pattern, ' ', $format );
                }

                $format = preg_replace( '/\s+/', ' ', $format );

                // Clean up trailing comma/separator that was before the time
                $format = preg_replace( '/,\s*$/', '', $format );
                $format = preg_replace( '/\s*–\s*$/', '', $format );

                return trim( $format );
            },
            'remove_date' => function( $format ) {
                // Remove date components to get time-only format
                $patterns = [
                    '/[dDjlNSwzW]/',  // Day
                    '/[FmMn]/',       // Month
                    '/[YyLo]/',       // Year
                ];

                foreach ( $patterns as $pattern ) {
                    $format = preg_replace( $pattern, '', $format );
                }

                // Clean up punctuation that might be left over
                $format = preg_replace( '/[,\.\-\/]/', '', $format );
                $format = preg_replace( '/\s+/', ' ', $format );
                return trim( $format );
            },
            'extract_day' => function( $format ) {
                // Extract just the day component, or return 'j' if no day found
                if ( preg_match( '/[dDjlNS]/', $format, $matches ) ) {
                    return $matches[0];
                }
                return 'j'; // Default to day of month without leading zeros
            }
        ];
    }

    return $utils;
}
}

// ===============================================
// ACF FIELD HELPERS
// ===============================================

/**
 * Get ACF field return format
 *
 * @since 3.0.0
 * @param string $field_key ACF field key
 * @param int $post_id Post ID
 * @return string|false Return format or false
 */
if ( ! function_exists( 'bws_get_acf_return_format' ) ) {
function bws_get_acf_return_format( $field_key, $post_id ) {
    if ( empty( $field_key ) || ! function_exists( 'get_field_object' ) ) {
        return false;
    }

    $field_object = get_field_object( $field_key, $post_id );

    if ( ! $field_object ) {
        return false;
    }

    return $field_object['return_format'] ?? false;
}
}

/**
 * Detect time-only values from raw string when ACF return_format is unavailable.
 *
 * Used as a fallback for `$date_is_time_only` detection in `bws_parse_combined_date_time()`
 * when `bws_get_acf_return_format()` can't return the configured format — e.g. flat
 * ACF repeater rows under GB Pro TYPE_OPTION / TYPE_POST_META loops, where ACF
 * subfield metadata isn't reachable via the resolved object_id. Without this,
 * `DateTime::createFromFormat('H:i:s', '14:30:00')` produces a DateTime at TODAY's
 * date + parsed time, and the time-only-inheritance branch is skipped because the
 * format-derived flag is false. (Issue #22 follow-up, bugfix v1.7.2.)
 *
 * Matches common ACF time-only storage shapes: `14:30:00`, `14:30`, `2:30 PM`,
 * `02:30 pm`. Rejects anything containing date separators (`-`, `/`) or alpha
 * month tokens.
 *
 * @since 1.7.2
 * @param mixed $value Raw ACF-stored value.
 * @return bool True when value parses as time-only.
 */
if ( ! function_exists( 'bws_value_looks_time_only' ) ) {
function bws_value_looks_time_only( $value ) {
    if ( ! is_string( $value ) || '' === $value ) {
        return false;
    }
    $trimmed = trim( $value );
    return (bool) preg_match( '/^\d{1,2}:\d{2}(:\d{2})?(\s*[ap]m)?$/i', $trimmed );
}
}

// ===============================================
// DATE/TIME PARSING FUNCTIONS
// ===============================================

/**
 * Parse combined date/time from separate or combined fields.
 *
 * INVARIANT: ACF field-config lookups (`bws_get_acf_return_format()`,
 * `bws_parse_acf_date_value()`) must receive the resolved ACF object_id, NOT a
 * raw caller-supplied `$post_id` that may be false in loop-row Mode 2b. Use
 * `bws_resolve_acf_object_id( $instance, $post_id )` which maps GB Pro
 * TYPE_OPTION rows → 'option' and TYPE_POST_META rows → outer page's postId.
 * Without this, `get_field_object()` fails on flat ACF repeater rows under GB
 * query loops and custom return_formats fall through to format-agnostic parsing
 * (issue #22, bugfix v1.7.2).
 *
 * @invariant The VALUE read (`bws_read_field`) is a SEPARATE arg from the
 * field-config object_id. For src:site datetime the `'option'` sentinel must
 * reach the value read — `$numeric_id` coerces it to false, so `$value_id`
 * re-threads `'option'` (v1.9.0, DT-1b). Don't collapse `$value_id` back into
 * `$numeric_id` or site-datetime values silently dead-end at null.
 *
 * @since 3.0.0
 * @param int $post_id Post ID
 * @param string $date_field Primary date/datetime/time field key
 * @param string $time_field Optional time field key
 * @param string $context 'start', 'end', or 'datetime'
 * @param DateTime|null $inherit_date Date to inherit for time-only fields
 * @param array $options Tag options for date_time_separator
 * @return array Combined result with 'date', 'has_time', 'time_only', 'formats'
 */
if ( ! function_exists( 'bws_parse_combined_date_time' ) ) {
function bws_parse_combined_date_time( $post_id, $date_field, $time_field, $context, $inherit_date = null, $options = [], $instance = null ) {
    $result = [
        'date'      => null,
        'has_time'  => false,
        'time_only' => null,
        'formats'   => [
            'date_format' => null,
            'time_format' => null,
            'combined_format' => null,
        ],
    ];

    // ACF term object_id syntax: "{taxonomy}_{term_id}" — route to term meta.
    $term_match = is_string( $post_id ) && preg_match( '/^([a-z][a-z0-9_-]*)_(\d+)$/', $post_id, $m );
    $term_id    = $term_match ? (int) $m[2] : 0;
    $numeric_id = is_numeric( $post_id ) ? (int) $post_id : false;

    // Resolve ACF object_id for field-config lookups. For loop-row Mode 2b contexts
    // (flat ACF repeater rows under TYPE_OPTION / TYPE_POST_META), $post_id is false
    // but ACF still needs an object_id ('option' or outer page id) to return field
    // metadata. Term-context keeps its existing object_id ("{taxonomy}_{term_id}").
    $acf_object_id = $term_match
        ? $post_id
        : ( function_exists( 'bws_resolve_acf_object_id' ) ? bws_resolve_acf_object_id( $instance, $post_id ) : $post_id );

    // DT-1b (V7): thread the 'option' sentinel to the VALUE read. $numeric_id coerces
    // 'option' → false, which would dead-end the DT-1 bws_read_field branch; the
    // value-read id must stay 'option' for site-datetime (ACF options-page fields).
    // Non-'option' callers keep $numeric_id (unchanged behavior).
    $value_id = ( 'option' === $post_id ) ? 'option' : $numeric_id;

    // Get primary field value and format
    $date_value = $date_field
        ? ( $term_match
            ? bws_read_term_field( $date_field, $term_id, true )
            : bws_read_field( $date_field, $instance, $value_id, true ) )
        : null;
    $date_format = bws_get_acf_return_format( $date_field, $acf_object_id );

    // Get time field value and format
    $time_value = $time_field
        ? ( $term_match
            ? bws_read_term_field( $time_field, $term_id, true )
            : bws_read_field( $time_field, $instance, $value_id, true ) )
        : null;
    $time_format = bws_get_acf_return_format( $time_field, $acf_object_id );

    $utils = bws_format_utils();

    // Parse primary field
    $date_obj = null;
    if ( $date_value ) {
        $date_obj = bws_parse_acf_date_value( $date_value, $date_field, $acf_object_id );
    }

    // Determine field types. Primary signal: ACF return_format. Fallback when ACF
    // metadata is unreachable (flat repeater subfields under TYPE_OPTION /
    // TYPE_POST_META): inspect the raw value string for time-only shape and the
    // parsed DateTime for a non-midnight time portion. Without this fallback,
    // time-only end fields lose start-date inheritance and render with today's
    // date (issue #22 follow-up).
    $date_is_time_only = $date_format
        ? ( ! $utils['has_date']( $date_format ) && $utils['has_time']( $date_format ) )
        : bws_value_looks_time_only( $date_value );
    // Detect time presence in fallback mode by scanning raw value for a time
    // separator (`:`). Date-only strings ("2026-05-25") lack it; full datetimes
    // ("2026-05-25 14:00:00") and time-only strings ("14:30:00") have it. Avoids
    // the false-positive from `bws_parse_acf_date_value`'s noon-default for
    // date-only values.
    $value_has_time_chars = is_string( $date_value ) && false !== strpos( $date_value, ':' );
    $date_has_time = $date_format
        ? $utils['has_time']( $date_format )
        : ( $date_is_time_only || $value_has_time_chars );

    // Parse time field
    $time_obj = null;
    if ( $time_value ) {
        $time_obj = bws_parse_acf_date_value( $time_value, $time_field, $acf_object_id );
    }

    // Handle time-only primary field
    if ( $date_is_time_only && $date_obj ) {
        if ( $inherit_date && $context === 'end' ) {
            // Inherit date from start for time-only end field
            $combined = clone $inherit_date;
            $combined->setTime(
                (int) $date_obj->format( 'H' ),
                (int) $date_obj->format( 'i' ),
                (int) $date_obj->format( 's' )
            );
            $result['date'] = $combined;
            $result['has_time'] = true;
            $result['formats']['combined_format'] = $date_format;
        } else {
            // Time-only without date to inherit
            $result['time_only'] = $date_obj;
            $result['formats']['time_format'] = $date_format;
        }

        return $result;
    }

    // Handle combined datetime or date-only field
    if ( $date_obj ) {
        $result['date'] = $date_obj;
        $result['has_time'] = $date_has_time;
        $result['formats']['date_format'] = $date_format;

        // Override/add time from separate field if provided
        if ( $time_obj ) {
            $result['date']->setTime(
                (int) $time_obj->format( 'H' ),
                (int) $time_obj->format( 'i' ),
                (int) $time_obj->format( 's' )
            );
            $result['has_time'] = true;
            $result['formats']['time_format'] = $time_format;

            // Build combined format with configurable separator (only when using separate fields)
            $date_only_format = $utils['remove_time']( $date_format ?: 'F j, Y' );
            $time_only_format = $time_format ?: 'g:i A';
            $date_time_separator = ! empty( $options['date_time_separator'] ) ? $options['date_time_separator'] : ', ';
            $result['formats']['combined_format'] = $date_only_format . $date_time_separator . $time_only_format;
        } else {
            // Use the original format as-is (already contains proper date/time formatting)
            $result['formats']['combined_format'] = $date_format;
        }
    }
    // Handle time-only field without date field (end time case)
    elseif ( $time_obj && $inherit_date && $context === 'end' ) {
        // Create end datetime by combining start date with end time
        $combined = clone $inherit_date;
        $combined->setTime(
            (int) $time_obj->format( 'H' ),
            (int) $time_obj->format( 'i' ),
            (int) $time_obj->format( 's' )
        );
        $result['date'] = $combined;
        $result['has_time'] = true;
        $result['formats']['time_format'] = $time_format;
        $result['formats']['combined_format'] = $time_format ?: 'g:i A';
    }

    return $result;
}
}

/**
 * Parse ACF date value with intelligent format detection and timezone handling
 *
 * CRITICAL TIMEZONE HANDLING:
 * ACF date fields store plain strings like "2025-01-16" without timezone info.
 * When creating DateTime objects, PHP needs a timezone context to interpret these strings.
 *
 * THE PROBLEM WITHOUT TIMEZONE HANDLING:
 * - PHP defaults to server timezone (often UTC) when parsing date strings
 * - DateTime("2025-01-16") becomes "2025-01-16 00:00:00 UTC"
 * - getTimestamp() converts to Unix timestamp (seconds since epoch in UTC)
 * - wp_date() then converts that UTC timestamp to WP timezone (e.g., EST = UTC-5)
 * - Result: "2025-01-16 00:00:00 UTC" displays as "2025-01-15 19:00:00 EST"
 * - You get WRONG DATES (off by one day, especially noticeable late at night)
 *
 * THE SOLUTION:
 * 1. Pass wp_timezone() when creating DateTime objects so dates are parsed in WP timezone
 *    "2025-01-16" now means "2025-01-16 in my site's timezone", not "2025-01-16 UTC"
 * 2. For date-only fields, set time to noon (12:00) instead of midnight (00:00)
 *    Even if timezone issues occur, noon provides a safety buffer against crossing day boundaries
 *
 * @since 3.1.3 - Added noon safety buffer for date-only fields and comprehensive documentation
 * @since 3.1.1 - Added timezone handling to prevent date shifting
 * @param mixed $date_value Date value from ACF
 * @param string $field_key ACF field key
 * @param int $post_id Post ID
 * @return DateTime|false DateTime object or false
 */
if ( ! function_exists( 'bws_parse_acf_date_value' ) ) {
function bws_parse_acf_date_value( $date_value, $field_key = '', $post_id = 0 ) {
    if ( empty( $date_value ) ) {
        return false;
    }

    // Get WordPress timezone - CRITICAL for correct date interpretation
    // Without this, dates get parsed in server timezone (often UTC) causing date shifts
    $wp_timezone = wp_timezone();

    // First attempt: Check ACF field configuration (most reliable)
    if ( $field_key && $post_id && function_exists( 'get_field_object' ) ) {
        $field_object = get_field_object( $field_key, $post_id );
        $return_format = $field_object['return_format'] ?? null;

        if ( $return_format ) {
            // Parse date in WP timezone - prevents "2025-01-16" from being interpreted as UTC
            $date = DateTime::createFromFormat( $return_format, $date_value, $wp_timezone );
            if ( $date && $date->format( $return_format ) === $date_value ) {
                // For date-only fields, set to noon instead of midnight
                // This prevents day-boundary crossing if any timezone issues occur
                // (max timezone offset is +/- 12 hours, so noon can't cross into another day)
                $utils = bws_format_utils();
                if ( ! $utils['has_time']( $return_format ) ) {
                    $date->setTime( 12, 0, 0 );
                }
                return $date;
            }
        }
    }

    // Second attempt: Common format fallbacks
    $common_formats = [
        // Date + Time formats (most specific first)
        'F j, Y, g:i A', 'F j, Y, h:i A', // Full month name formats with comma
        'F j, Y g:i A', 'F j, Y h:i A',   // Full month name without comma before time
        'm/d/Y h:i A', 'd/m/Y h:i A',     // 12-hour with leading zeros
        'm/d/Y g:i A', 'd/m/Y g:i A',     // 12-hour without leading zeros
        'm/d/Y H:i:s', 'd/m/Y H:i:s',     // 24-hour
        'Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d h:i A', 'Y-m-d g:i A',
        // Date only formats
        'Y-m-d', 'Ymd', 'm/d/Y', 'd/m/Y', 'F j, Y',
        // Time only formats
        'h:i A', 'g:i A', 'h:i a', 'g:i a', 'H:i:s', 'H:i',
        // ISO formats
        'c', DATE_ATOM, DATE_RFC3339,
    ];

    $utils = bws_format_utils();

    foreach ( $common_formats as $format ) {
        // Parse with WP timezone for consistent date interpretation
        $date = DateTime::createFromFormat( $format, $date_value, $wp_timezone );
        if ( $date && $date->format( $format ) === $date_value ) {
            // For date-only formats, set to noon as safety buffer against day-boundary issues
            if ( ! $utils['has_time']( $format ) ) {
                $date->setTime( 12, 0, 0 );
            }
            return $date;
        }
    }

    // Third attempt: Direct DateTime parsing
    try {
        // Use WP timezone for parsing - critical for correct date interpretation
        $date = new DateTime( $date_value, $wp_timezone );
        if ( $date->format( 'Y' ) > 1900 ) {
            // If time is midnight, likely a date-only value - set to noon for safety
            if ( $date->format( 'H:i:s' ) === '00:00:00' ) {
                $date->setTime( 12, 0, 0 );
            }
            return $date;
        }
    } catch ( Exception $e ) {
        // Failed to parse
    }

    return false;
}
}

// ===============================================
// FORMAT BUILDING
// ===============================================

/**
 * Build format string for single date/time tag
 *
 * @since 3.0.0
 * @param array $options Tag options
 * @param array $result Parsed date/time result
 * @param bool $include_date Whether to include date
 * @param bool $include_time Whether to include time
 * @return string Format string
 */
if ( ! function_exists( 'bws_build_single_format' ) ) {
function bws_build_single_format( $options, $result, $include_date, $include_time ) {
    $format_type = $options['format_type'] ?? 'auto';
    $utils = bws_format_utils();

    // Custom format takes precedence
    if ( $format_type === 'custom' && ! empty( $options['custom_format'] ) ) {
        $format = $options['custom_format'];
    }
    // Auto format from ACF
    elseif ( $result['formats']['combined_format'] ) {
        $format = $result['formats']['combined_format'];
    }
    // Fallback: use WordPress date/time format settings.
    else {
        $date_fmt = get_option( 'date_format', 'F j, Y' );
        $format   = $include_time
            ? $date_fmt . ' ' . get_option( 'time_format', 'g:i A' )
            : $date_fmt;
    }

    // Apply date/time only filters
    if ( ! $include_date && $utils['has_date']( $format ) ) {
        $format = $utils['remove_date']( $format );
    }
    if ( ! $include_time && $utils['has_time']( $format ) ) {
        $format = $utils['remove_time']( $format );
    }

    // Add missing components if needed. Gap-fill style: ACF field format first,
    // then WordPress option defaults (not hardcoded constants).
    if ( $include_time && ! $utils['has_time']( $format ) ) {
        $time_format = $result['formats']['time_format'] ?: get_option( 'time_format', 'g:i A' );
        $format .= ' ' . $time_format;
    }
    if ( $include_date && ! $utils['has_date']( $format ) ) {
        $date_format = $result['formats']['date_format'] ?: get_option( 'date_format', 'F j, Y' );
        $format = $utils['remove_time']( $date_format ) . ' ' . $format;
    }

    return $utils['clean_format']( $format );
}
}

/**
 * Build format string for range date/time tag
 *
 * @since 3.0.0
 * @param array $options Tag options
 * @param array $start_result Start date/time result
 * @param array|null $end_result End date/time result
 * @param bool $include_time Whether to include time
 * @return string Format string
 */
if ( ! function_exists( 'bws_build_range_format' ) ) {
function bws_build_range_format( $options, $start_result, $end_result, $include_time ) {
    $format_type = $options['format_type'] ?? 'auto';
    $utils = bws_format_utils();

    // Custom format takes precedence
    if ( $format_type === 'custom' && ! empty( $options['custom_format'] ) ) {
        $format = $options['custom_format'];
    }
    // Auto format from ACF - prefer combined format from start
    elseif ( $start_result['formats']['combined_format'] ) {
        $format = $start_result['formats']['combined_format'];
    }
    // Fallback: use WordPress date/time format settings.
    else {
        $date_fmt = get_option( 'date_format', 'F j, Y' );
        $format   = $include_time
            ? $date_fmt . ' ' . get_option( 'time_format', 'g:i A' )
            : $date_fmt;
    }

    // Reconcile components vs. style: `as` (via $include_time / always-on date
    // for the range path) decides WHICH components render; the format string only
    // supplies their STYLE. Complete or strip the format symmetrically so a
    // date-only custom format and a time-only custom format behave consistently.
    // Gap-fill style for a missing component comes from the ACF field format
    // first, then WordPress option defaults.
    $include_date = empty( $options['time_only'] );

    // Strip components the selection excludes.
    if ( ! $include_time && $utils['has_time']( $format ) ) {
        $format = $utils['remove_time']( $format );
    }
    if ( ! $include_date && $utils['has_date']( $format ) ) {
        $format = $utils['remove_date']( $format );
    }

    // Add components the selection includes but the format omits.
    if ( $include_time && ! $utils['has_time']( $format ) ) {
        $time_format = $start_result['formats']['time_format'] ?: get_option( 'time_format', 'g:i A' );
        $format     .= ' ' . $time_format;
    }
    if ( $include_date && ! $utils['has_date']( $format ) ) {
        $date_format = $utils['remove_time']( $start_result['formats']['date_format'] ?: get_option( 'date_format', 'F j, Y' ) );
        $format      = $date_format . ' ' . $format;
    }

    return $utils['clean_format']( $format );
}
}

/**
 * Resolve the time format string for a time-only ('as:time') display.
 *
 * Precedence: a custom format string (reduced to its time tokens, if it
 * contains any) → the ACF field's own time format → the WordPress time_format
 * option. Never hardcodes 'g:i A' except as the final WP-default fallback.
 *
 * @since 1.7.4
 * @param array $options      Tag options (may contain format_type / custom_format).
 * @param array $start_result Parsed start result (formats.time_format).
 * @return string A PHP date() time-format string.
 */
if ( ! function_exists( 'bws_resolve_time_only_format' ) ) {
function bws_resolve_time_only_format( $options, $start_result ) {
    $utils = bws_format_utils();

    // 1. Custom format, reduced to its time component (if it has one).
    if ( ( $options['format_type'] ?? '' ) === 'custom' && ! empty( $options['custom_format'] ) ) {
        $custom = $options['custom_format'];
        if ( $utils['has_time']( $custom ) ) {
            return $utils['remove_date']( $custom );
        }
    }

    // 2. ACF field's own time format. 3. WordPress option default.
    return $start_result['formats']['time_format'] ?: get_option( 'time_format', 'g:i A' );
}
}

// ===============================================
// DATE/TIME FORMATTING
// ===============================================

/**
 * Format single date/time with options
 *
 * @since 3.0.0
 * @param DateTime $date Date object
 * @param string $format Date format
 * @param array $options Tag options
 * @return string Formatted date/time
 */
if ( ! function_exists( 'bws_format_single_date_time' ) ) {
function bws_format_single_date_time( $date, $format, $options ) {
    // Safety check - must have valid date
    if ( ! $date || ! is_a( $date, 'DateTime' ) ) {
        return '';
    }

    $omit_current_year = ! empty( $options['omit_current_year'] );
    $smart_time = ! empty( $options['smart_time'] );
    $current_year = wp_date( 'Y' );
    $utils = bws_format_utils();

    // Remove year if current year
    if ( $omit_current_year && $date->format( 'Y' ) === $current_year ) {
        $format = $utils['remove_year']( $format );
    }

    // Smart time formatting - hide midnight
    if ( $smart_time && $utils['has_time']( $format ) && $date->format( 'H:i' ) === '00:00' ) {
        $format = $utils['remove_time']( $format );
    }

    return wp_date( $format, $date->getTimestamp() );
}
}

/**
 * Format date range with smart redundancy removal
 *
 * @since 3.0.0
 * @param DateTime $start_date Start date
 * @param DateTime|null $end_date End date (optional)
 * @param string $format Date format string
 * @param array $options Tag options
 * @return string Formatted date range
 */
if ( ! function_exists( 'bws_format_date_range' ) ) {
function bws_format_date_range( $start_date, $end_date, $format, $options ) {
    // Safety check - must have valid start date
    if ( ! $start_date || ! is_a( $start_date, 'DateTime' ) ) {
        return '';
    }

    $separator = $options['separator'] ?? '–';
    $omit_current_year = ! empty( $options['omit_current_year'] );
    $smart_time = ! empty( $options['smart_time'] );
    $current_year = wp_date( 'Y' );
    $utils = bws_format_utils();

    // Single date case
    if ( ! $end_date ) {
        return bws_format_single_date( $start_date, $format, $omit_current_year, $smart_time, $current_year, $utils );
    }

    // Cross-year override: when the two endpoints fall in different years, the
    // year is meaningful even if one endpoint is the current year — omitting it
    // would produce lopsided output (e.g. "August 12, 2025–June 1"). Suppress
    // current-year omission for the whole range so both years render.
    if ( $start_date->format( 'Y' ) !== $end_date->format( 'Y' ) ) {
        $omit_current_year = false;
    }

    // Same day with time range
    if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) {
        return bws_format_same_day_range( $start_date, $end_date, $format, $omit_current_year, $smart_time, $current_year, $utils );
    }

    // Different days - build smart range
    return bws_format_multi_day_range( $start_date, $end_date, $format, $separator, $omit_current_year, $smart_time, $current_year, $utils );
}
}

/**
 * Format single date with options
 *
 * @since 3.0.0
 * @param DateTime $date Date object
 * @param string $format Date format
 * @param bool $omit_current_year Whether to omit current year
 * @param bool $smart_time Whether to use smart time formatting
 * @param string $current_year Current year
 * @param array $utils Utility functions
 * @return string Formatted date
 */
if ( ! function_exists( 'bws_format_single_date' ) ) {
function bws_format_single_date( $date, $format, $omit_current_year, $smart_time, $current_year, $utils ) {
    // Safety check - must have valid date
    if ( ! $date || ! is_a( $date, 'DateTime' ) ) {
        return '';
    }

    // Remove year if current year
    if ( $omit_current_year && $date->format( 'Y' ) === $current_year ) {
        $format = $utils['remove_year']( $format );
    }

    // Smart time formatting - hide midnight
    if ( $smart_time && $utils['has_time']( $format ) && $date->format( 'H:i' ) === '00:00' ) {
        $format = $utils['remove_time']( $format );
    }

    return wp_date( $format, $date->getTimestamp() );
}
}

/**
 * Format same day range with intelligent time handling
 *
 * @since 3.0.0
 * @param DateTime $start Start date
 * @param DateTime $end End date
 * @param string $format Date format
 * @param bool $omit_current_year Whether to omit current year
 * @param bool $smart_time Whether to use smart time formatting
 * @param string $current_year Current year
 * @param array $utils Utility functions
 * @return string Formatted date range
 */
if ( ! function_exists( 'bws_format_same_day_range' ) ) {
function bws_format_same_day_range( $start, $end, $format, $omit_current_year, $smart_time, $current_year, $utils ) {
    // Safety checks - must have valid dates
    if ( ! $start || ! is_a( $start, 'DateTime' ) || ! $end || ! is_a( $end, 'DateTime' ) ) {
        return '';
    }

    $has_time = $utils['has_time']( $format );

    // Get date part without time
    $date_format = $utils['remove_time']( $format );

    // Remove year if current year
    if ( $omit_current_year && $start->format( 'Y' ) === $current_year ) {
        $date_format = $utils['remove_year']( $date_format );
    }

    $date_part = wp_date( $date_format, $start->getTimestamp() );

    // Handle time range if applicable
    if ( $has_time && $start->format( 'H:i' ) !== $end->format( 'H:i' ) ) {
        $time_range = bws_format_time_range( $start, $end, $smart_time );

        if ( $time_range ) {
            return trim( $date_part . ', ' . $time_range );
        }
    }

    // Single date with potential time
    if ( $smart_time && $has_time && $start->format( 'H:i' ) === '00:00' ) {
        return $date_part; // Hide midnight time
    }

    return wp_date( $format, $start->getTimestamp() );
}
}

/**
 * Format time range with smart AM/PM consolidation
 *
 * @since 3.0.0
 * @param DateTime $start Start time
 * @param DateTime $end End time
 * @param bool $smart_time Whether to use smart formatting
 * @return string Formatted time range
 */
if ( ! function_exists( 'bws_format_time_range' ) ) {
function bws_format_time_range( $start, $end, $smart_time ) {
    // Safety checks - must have valid dates
    if ( ! $start || ! is_a( $start, 'DateTime' ) || ! $end || ! is_a( $end, 'DateTime' ) ) {
        return '';
    }

    // Skip midnight times in smart mode
    if ( $smart_time ) {
        $start_time = $start->format( 'H:i' ) === '00:00' ? '' : $start->format( 'g:i' );
        $end_time = $end->format( 'H:i' ) === '00:00' ? '' : $end->format( 'g:i' );

        if ( ! $start_time && ! $end_time ) {
            return ''; // Both midnight
        }
        if ( ! $start_time ) {
            return $end->format( 'g:i A' ); // Only end time
        }
        if ( ! $end_time ) {
            return $start->format( 'g:i A' ); // Only start time
        }

        // Both times exist - check for AM/PM consolidation
        $start_ampm = $start->format( 'A' );
        $end_ampm = $end->format( 'A' );

        if ( $start_ampm === $end_ampm ) {
            return $start_time . '–' . $end_time . ' ' . $end_ampm;
        }

        return $start_time . ' ' . $start_ampm . '–' . $end_time . ' ' . $end_ampm;
    }

    // Non-smart formatting
    return $start->format( 'g:i A' ) . '–' . $end->format( 'g:i A' );
}
}

/**
 * Format multi-day range with smart redundancy removal
 *
 * @since 3.0.0
 * @param DateTime $start Start date
 * @param DateTime $end End date
 * @param string $format Original format
 * @param string $separator Separator between dates
 * @param bool $omit_current_year Whether to omit current year
 * @param bool $smart_time Whether to use smart time formatting
 * @param string $current_year Current year
 * @param array $utils Utility functions
 * @return string Formatted date range
 */
if ( ! function_exists( 'bws_format_multi_day_range' ) ) {
function bws_format_multi_day_range( $start, $end, $format, $separator, $omit_current_year, $smart_time, $current_year, $utils ) {
    // Safety checks - must have valid dates
    if ( ! $start || ! is_a( $start, 'DateTime' ) || ! $end || ! is_a( $end, 'DateTime' ) ) {
        return '';
    }

    // Determine what to show for each date
    $start_format = $format;
    $end_format = $format;
    $same_month_range = false;

    // Same-month consolidation collapses the END side to a bare day number
    // (e.g. "August 1–9"). That collapse is only valid when the format is
    // pure-date with no per-day-varying tokens:
    //   - day-name tokens (l, D, N) differ across days, so a bare-day end loses them;
    //   - time tokens (g:i A, etc.) differ start vs end, and extract_day() would
    //     drop the end time entirely while the start keeps it — producing lopsided
    //     output like "April 1, 2026 3:27 PM–30".
    // When either is present, fall through to the same-year branch, which keeps
    // the full format on the end side.
    $blocks_day_collapse = (bool) preg_match( '/[lDN]/', $format ) || $utils['has_time']( $format );

    // Same month and year - show "Month Day–Day, Year"
    if ( ! $blocks_day_collapse && $start->format( 'F Y' ) === $end->format( 'F Y' ) ) {
        $same_month_range = true;
        // Start: Full date without year (e.g., "January 10")
        $start_format = $utils['remove_year']( $format );
        // End: Just the day number (e.g., "11")
        $end_format = $utils['extract_day']( $format );
    }
    // Same year - show "Month Day–Month Day, Year"
    elseif ( $start->format( 'Y' ) === $end->format( 'Y' ) ) {
        $start_format = $utils['remove_year']( $format ); // Remove year from start
        $end_format = $format; // Keep full format for end
    }

    // Apply current year omission (but not for same_month_range as we'll add year at end)
    if ( ! $same_month_range ) {
        if ( $omit_current_year && $start->format( 'Y' ) === $current_year ) {
            $start_format = $utils['remove_year']( $start_format );
        }
        if ( $omit_current_year && $end->format( 'Y' ) === $current_year ) {
            $end_format = $utils['remove_year']( $end_format );
        }
    }

    // Apply smart time formatting
    if ( $smart_time ) {
        if ( $utils['has_time']( $start_format ) && $start->format( 'H:i' ) === '00:00' ) {
            $start_format = $utils['remove_time']( $start_format );
        }
        if ( $utils['has_time']( $end_format ) && $end->format( 'H:i' ) === '00:00' ) {
            $end_format = $utils['remove_time']( $end_format );
        }
    }

    $start_formatted = wp_date( $start_format, $start->getTimestamp() );
    $end_formatted = wp_date( $end_format, $end->getTimestamp() );

    // For same month ranges, add year at the end
    if ( $same_month_range ) {
        $show_year = ! $omit_current_year || $start->format( 'Y' ) !== $current_year;
        if ( $show_year ) {
            return $start_formatted . $separator . $end_formatted . ', ' . $start->format( 'Y' );
        }
    }

    return $start_formatted . $separator . $end_formatted;
}
}

// ===============================================
// FALLBACK HANDLING
// ===============================================

/**
 * Handle fallback when no valid dates found
 *
 * @since 3.0.0
 * @param array $options Tag options
 * @param object $instance Block instance
 * @param string $tag_type 'single' or 'range'
 * @return string
 */
if ( ! function_exists( 'bws_handle_date_time_fallback' ) ) {
function bws_handle_date_time_fallback( $options, $instance, $tag_type ) {
    $fallback_text = sanitize_text_field( $options['fallback_text'] ?? '' );

    if ( empty( $fallback_text ) ) {
        return '';
    }

    return GenerateBlocks_Dynamic_Tag_Callbacks::output( $fallback_text, $options, $instance );
}
}
