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
 * raw caller-supplied post id, which is false on a repeater row. Use
 * `bws_resolve_acf_object_id( $instance, $id )` which maps GB Pro
 * TYPE_OPTION rows → 'option' and TYPE_POST_META rows → outer page's postId.
 * Without this, `get_field_object()` fails on flat ACF repeater rows under GB
 * query loops and custom return_formats fall through to format-agnostic parsing
 * (issue #22, bugfix v1.7.2).
 *
 * @invariant The VALUE read is a SEPARATE step from the field-config object_id: the
 * value is read off the resolved source by kind (the read closure below), the
 * object_id only addresses the field's config. A site source's value therefore
 * goes through the one gated site-option reader, and the `'option'` sentinel
 * exists only on the config side.
 *
 * @invariant Kind dispatch (FW-3a): the read target is the factory's resolved-source payload,
 * built by the callers (bws_datetime_term_source() / bws_datetime_post_source() and the site
 * payload). All branching below is on `kind` — never reintroduce string-shape sniffing of the
 * first arg.
 *
 * @since 3.0.0
 * @since 1.15.0 First arg accepts a resolved-source payload (FW-3a).
 * @since Unreleased The first arg is only ever the payload.
 * @since 1.21.0 `meta_row` VALUE leg (FW-74) — a repeater row reads its sub-field
 *               through the L2 seam. Format-agnostic by construction; see the
 *               read closure below for why, and what closes it.
 * @param array $target Resolved source (the factory's payload shape)
 * @param string $date_field Primary date/datetime/time field key
 * @param string $time_field Optional time field key
 * @param string $context 'start', 'end', or 'datetime'
 * @param DateTime|null $inherit_date Date to inherit for time-only fields
 * @param array $options Tag options for date_time_separator
 * @return array Combined result with 'date', 'has_time', 'time_only', 'formats'
 */
if ( ! function_exists( 'bws_parse_combined_date_time' ) ) {
function bws_parse_combined_date_time( $target, $date_field, $time_field, $context, $inherit_date = null, $options = [], $instance = null ) {
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

    // Kind-dispatched read target (FW-3a).
    $is_term = 'term' === ( $target['kind'] ?? '' );
    $is_site = 'site' === ( $target['kind'] ?? '' );
    $term_id = $is_term ? (int) ( $target['id'] ?? 0 ) : 0;

    // Resolve ACF object_id for field-config lookups. Term kind builds ACF's
    // "{taxonomy}_{term_id}" object id. On a repeater row (TYPE_OPTION /
    // TYPE_POST_META query loops) the post id is false but
    // ACF still needs an object_id ('option' or outer page id) to return field
    // metadata.
    if ( $is_term ) {
        $acf_object_id = ( $target['taxonomy'] ?? 'term' ) . '_' . $term_id;
    } else {
        $raw_id        = $is_site ? 'option' : ( $target['id'] ?? false );
        $acf_object_id = function_exists( 'bws_resolve_acf_object_id' ) ? bws_resolve_acf_object_id( $instance, $raw_id ) : $raw_id;
    }

    // ONE value read for both of this parse's fields: the L2 seam's string half
    // (bws_read_resolved_source()) for term, id-carrying post, site and repeater-row
    // sources — a date field is a scalar, so the seam's own coercion is the read
    // datetime wants, and the kind dispatch (and the site option gates) live there once.
    //
    // THE ONE EXCEPTION is an entity-less post source (id <= 0), which keeps
    // bws_read_field()'s loop inference: a GB Pro query loop over repeater rows reaches
    // the resolver's post tail with no id, and only bws_read_field() re-infers the loop
    // row (issue #22), where the seam's post arm answers '' for id 0. FW-194 gives that row
    // a real source and removes this branch.
    //
    // THE VALUE HALF ONLY. $acf_object_id above is untouched by the read, per the
    // invariant in this function's docblock: the field-config id is a SEPARATE step
    // from the value read. A row reaches no sub-field config (the flat-meta convention
    // needs the repeater name and index, which this layer is not handed), so a row's
    // $date_format is null and the parse falls to the format-agnostic common-format walk
    // in bws_parse_acf_date_value(). That is a KNOWN BOUNDARY, not a defect here: the
    // field-object read that would close it is FW-3's, and until it lands an ambiguous
    // value (a `d/m/Y` row whose day is <= 12) reads as `m/d/Y`. Visible on the testbed,
    // both arms of it, at fold-test-matrix.md §F9.5m4.
    $read_value = static function ( $field ) use ( $target, $instance ) {
        if ( ! $field ) {
            return null;
        }
        $entityless_post = 'post' === ( $target['kind'] ?? '' ) && (int) ( $target['id'] ?? 0 ) <= 0;
        if ( ! $entityless_post ) {
            return bws_read_resolved_source( $target, $field, $instance );
        }
        return bws_read_field( $field, $instance, $target['id'] ?? false, true );
    };

    // Get primary field value and format
    $date_value  = $read_value( $date_field );
    $date_format = bws_get_acf_return_format( $date_field, $acf_object_id );

    // Get time field value and format
    $time_value  = $read_value( $time_field );
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
    // A time field given with no primary field and no date to inherit is a time-only read
    // (a start given only as `startTimeKey`, or an end after a time-only start), the same
    // result a time-only primary field produces above.
    elseif ( $time_obj && ! $date_field ) {
        $result['time_only'] = $time_obj;
        $result['formats']['time_format'] = $time_format;
    }

    return $result;
}
}

/**
 * The parse result for a VERB read (`now`, `published`, `modified`): the same shape
 * bws_parse_combined_date_time() returns, with no field behind it.
 *
 * `now` is a moment with a time. `published` and `modified` are the post's own dates and
 * read only from a post source with an entity; any other source (a term, the site, a row,
 * nothing) leaves `date` null, the empty read that takes the fallback. A post date is shown
 * as a date, with no time.
 *
 * @since Unreleased
 * @param string $verb   `now`, `published` or `modified`.
 * @param array  $source Resolved source (the factory's payload shape).
 * @return array Parse result; `date` is null when there is nothing to read.
 */
if ( ! function_exists( 'bws_datetime_verb_result' ) ) {
function bws_datetime_verb_result( $verb, $source ) {
    $result = [
        'date'      => null,
        'has_time'  => false,
        'time_only' => null,
        'formats'   => [ 'date_format' => null, 'time_format' => null, 'combined_format' => null ],
    ];

    if ( 'now' === $verb ) {
        $result['date']     = new DateTime( 'now', wp_timezone() );
        $result['has_time'] = true;
    } elseif ( 'post' === ( $source['kind'] ?? '' ) && ! empty( $source['id'] ) ) {
        $when = get_post_datetime( (int) $source['id'], 'published' === $verb ? 'date' : 'modified' );
        if ( $when ) {
            $result['date'] = DateTime::createFromImmutable( $when );
        }
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
 * Resolve the time format string for a time display — THE one place the time half of
 * a read gets its style, for a single read, a one-ended range and both
 * ends of a two-ended one.
 *
 * Precedence: a custom format string (reduced to its time tokens, if it
 * contains any) → the format of a separate time field or a time-only field →
 * the time tokens of the datetime field's own return format → the WordPress
 * time_format option. Never hardcodes 'g:i A' except as the final WP-default fallback.
 *
 * @since 1.7.4
 * @since Unreleased The datetime field's own return format joins the chain, ahead of
 *                   the WordPress option.
 * @param array $options      Tag options (may contain format_type / custom_format).
 * @param array $start_result Parsed start result (formats.time_format, formats.combined_format).
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

    // 2. A separate time field's (or a time-only field's) own format.
    if ( ! empty( $start_result['formats']['time_format'] ) ) {
        return $start_result['formats']['time_format'];
    }

    // 3. The datetime field's own return format, when it carries time tokens.
    $combined = $start_result['formats']['combined_format'] ?? '';
    if ( $combined && $utils['has_time']( $combined ) ) {
        return $utils['clean_format']( $utils['remove_date']( $combined ) );
    }

    // 4. WordPress option default.
    return get_option( 'time_format', 'g:i A' );
}
}

/**
 * Format the time half of a read — THE one time display, for the single core and the
 * range core alike.
 *
 * `$start` and `$end` are parse results from bws_parse_combined_date_time(). A side
 * shows a time only when its parse says it has one (`time_only`, or `has_time` on a
 * dated value): a date-only value carries the parser's noon safety buffer, and
 * printing it would present an internal artifact as a real time. A side with no time
 * is skipped, one side prints bare, two print as a time range. The style comes from
 * bws_resolve_time_only_format() for both sides; the midnight rule is the same one the
 * date-time display applies (`hide_midnight`).
 *
 * Returns '' when there is nothing to show, which the caller turns into the fallback.
 *
 * @since Unreleased
 * @param array      $start   Parsed start result.
 * @param array|null $end     Parsed end result, or null for a one-sided read.
 * @param array      $options Normalized tag options.
 * @return string
 */
if ( ! function_exists( 'bws_format_time_display' ) ) {
function bws_format_time_display( $start, $end, $options ) {
    // No start at all means no read, even when an end exists.
    if ( ! $start['date'] && ! $start['time_only'] ) {
        return '';
    }

    // `as:date` only reaches here for a start with no date (a time-only field), which has no
    // date to show.
    if ( ! empty( $options['date_only'] ) ) {
        return '';
    }

    $moment = static function ( $result ) {
        if ( ! $result ) {
            return null;
        }
        if ( $result['time_only'] ) {
            return $result['time_only'];
        }
        return ( $result['date'] && $result['has_time'] ) ? $result['date'] : null;
    };

    $first  = $moment( $start );
    $second = $moment( $end );
    $time_format   = bws_resolve_time_only_format( $options, $start );
    $hide_midnight = ! empty( $options['hide_midnight'] );

    if ( $first && $second ) {
        return bws_format_time_range( $first, $second, $hide_midnight, $time_format );
    }

    // One side alone is held to the sentinels bws_format_time_range() applies to that side of
    // a pair: 00:00 is no start time, and 23:59 on an END is no end time.
    $only = $first ?: $second;
    if ( ! $only ) {
        return '';
    }
    $no_time = $first ? array( '00:00' ) : array( '00:00', '23:59' );
    if ( $hide_midnight && in_array( $only->format( 'H:i' ), $no_time, true ) ) {
        return '';
    }
    return wp_date( $time_format, $only->getTimestamp() );
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
    $hide_midnight = ! empty( $options['hide_midnight'] );
    $current_year = wp_date( 'Y' );
    $utils = bws_format_utils();

    // Remove year if current year
    if ( $omit_current_year && $date->format( 'Y' ) === $current_year ) {
        $format = $utils['remove_year']( $format );
    }

    // Hide the time when it's midnight.
    if ( $hide_midnight && $utils['has_time']( $format ) && $date->format( 'H:i' ) === '00:00' ) {
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
    $hide_midnight = ! empty( $options['hide_midnight'] );
    $current_year = wp_date( 'Y' );
    $utils = bws_format_utils();

    // Single date case
    if ( ! $end_date ) {
        return bws_format_single_date( $start_date, $format, $omit_current_year, $hide_midnight, $current_year, $utils );
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
        return bws_format_same_day_range( $start_date, $end_date, $format, $omit_current_year, $hide_midnight, $current_year, $utils );
    }

    // Different days - build smart range
    return bws_format_multi_day_range( $start_date, $end_date, $format, $separator, $omit_current_year, $hide_midnight, $current_year, $utils );
}
}

/**
 * Format single date with options
 *
 * @since 3.0.0
 * @param DateTime $date Date object
 * @param string $format Date format
 * @param bool $omit_current_year Whether to omit current year
 * @param bool $hide_midnight Whether to hide a midnight time
 * @param string $current_year Current year
 * @param array $utils Utility functions
 * @return string Formatted date
 */
if ( ! function_exists( 'bws_format_single_date' ) ) {
function bws_format_single_date( $date, $format, $omit_current_year, $hide_midnight, $current_year, $utils ) {
    // Safety check - must have valid date
    if ( ! $date || ! is_a( $date, 'DateTime' ) ) {
        return '';
    }

    // Remove year if current year
    if ( $omit_current_year && $date->format( 'Y' ) === $current_year ) {
        $format = $utils['remove_year']( $format );
    }

    // Hide the time when it's midnight.
    if ( $hide_midnight && $utils['has_time']( $format ) && $date->format( 'H:i' ) === '00:00' ) {
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
 * @param bool $hide_midnight Whether to hide a midnight time
 * @param string $current_year Current year
 * @param array $utils Utility functions
 * @return string Formatted date range
 */
if ( ! function_exists( 'bws_format_same_day_range' ) ) {
function bws_format_same_day_range( $start, $end, $format, $omit_current_year, $hide_midnight, $current_year, $utils ) {
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

    // Handle time range if applicable. The time part of the range renders in the
    // format's own time tokens (#25) — extract_time falls back to 'g:i A' when
    // the format carries no recognizable time pattern.
    if ( $has_time && $start->format( 'H:i' ) !== $end->format( 'H:i' ) ) {
        $time_range = bws_format_time_range( $start, $end, $hide_midnight, $utils['extract_time']( $format ) );

        if ( $time_range ) {
            return trim( $date_part . ', ' . $time_range );
        }
    }

    // Single date with potential time
    if ( $hide_midnight && $has_time && $start->format( 'H:i' ) === '00:00' ) {
        return $date_part; // Hide midnight time
    }

    return wp_date( $format, $start->getTimestamp() );
}
}

/**
 * Format time range with smart AM/PM consolidation
 *
 * Format-aware (#25): renders both sides in $time_format. AM/PM consolidation
 * (the shared-meridiem collapse, `9:00–11:30 AM`) applies whenever the format
 * is 12-hour (an hour token g/h plus a meridiem token A/a) — a meridiem-less
 * or 24-hour format renders both sides fully. Unlike midnight suppression,
 * consolidation runs regardless of $hide_midnight (#125): it collapses a
 * redundant meridiem token, not a value the author asked to hide.
 *
 * $hide_midnight also treats `23:59`/`23:59:59` on the END side as "no end
 * time known", the mirror of `00:00` meaning "no start time known" (#126):
 * a same-day `00:00`-`23:59` range is the all-day encoding, and both ends
 * are the day's own midnight boundary.
 *
 * A start and end that are the same instant never need a range at all (an
 * outlier reachable only via direct calls to this documented helper — the
 * one production caller never passes equal instants) and collapse to a
 * single formatted value; under $hide_midnight a midnight instant collapses
 * to nothing, same as the two-sided case above.
 *
 * @since 3.0.0
 * @since 1.15.0 $time_format param — custom/ACF/WP-default chain honored
 *               two-ended (issue #25); previously hardcoded 'g:i A'.
 * @since 1.19.1 Consolidation ungated from $hide_midnight (#125); `23:59`
 *               end sentinel (#126); same-instant collapse; param renamed
 *               from $smart_time.
 * @param DateTime $start Start time
 * @param DateTime $end End time
 * @param bool $hide_midnight Whether to hide a midnight/unset time.
 * @param string $time_format Time-only PHP date format for both sides.
 * @return string Formatted time range
 */
if ( ! function_exists( 'bws_format_time_range' ) ) {
function bws_format_time_range( $start, $end, $hide_midnight, $time_format = 'g:i A' ) {
    // Safety checks - must have valid dates
    if ( ! $start || ! is_a( $start, 'DateTime' ) || ! $end || ! is_a( $end, 'DateTime' ) ) {
        return '';
    }

    // Same instant: nothing to range.
    if ( $start->getTimestamp() === $end->getTimestamp() ) {
        if ( $hide_midnight && $start->format( 'H:i' ) === '00:00' ) {
            return '';
        }
        return $start->format( $time_format );
    }

    if ( $hide_midnight ) {
        $start_is_midnight = $start->format( 'H:i' ) === '00:00';
        $end_has_no_time   = in_array( $end->format( 'H:i' ), array( '00:00', '23:59' ), true );

        if ( $start_is_midnight && $end_has_no_time ) {
            return ''; // Neither end has a known time — all-day.
        }
        if ( $start_is_midnight ) {
            return $end->format( $time_format ); // Only end time
        }
        if ( $end_has_no_time ) {
            return $start->format( $time_format ); // Only start time
        }
    }

    // Consolidation: 12-hour formats only, applies whether or not midnight
    // suppression is on (#125).
    $is_twelve_hour = preg_match( '/[gh]/', $time_format ) && preg_match( '/[Aa]/', $time_format );

    if ( $is_twelve_hour && $start->format( 'A' ) === $end->format( 'A' ) ) {
        // Shared meridiem: the start side sheds its meridiem token.
        $bare = trim( preg_replace( '/\s*[Aa]\s*/', ' ', $time_format ) );
        return $start->format( $bare ) . '–' . $end->format( $time_format );
    }

    return $start->format( $time_format ) . '–' . $end->format( $time_format );
}
}

/**
 * Format multi-day range with smart redundancy removal
 *
 * Each end drops its time independently. `00:00` means "no start time known";
 * `23:59`/`23:59:59` on the END side is its mirror, so a `00:00`-`23:59`
 * multi-day span renders as bare dates (#126) — the same sentinel pair
 * bws_format_time_range() applies to a same-day span.
 *
 * @since 3.0.0
 * @since 1.19.1 `23:59` end sentinel (#126); param renamed from $smart_time.
 * @param DateTime $start Start date
 * @param DateTime $end End date
 * @param string $format Original format
 * @param string $separator Separator between dates
 * @param bool $omit_current_year Whether to omit current year
 * @param bool $hide_midnight Whether to hide a midnight/unset time
 * @param string $current_year Current year
 * @param array $utils Utility functions
 * @return string Formatted date range
 */
if ( ! function_exists( 'bws_format_multi_day_range' ) ) {
function bws_format_multi_day_range( $start, $end, $format, $separator, $omit_current_year, $hide_midnight, $current_year, $utils ) {
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

    // Drop each end's time when it carries no real value. `23:59` is the END-side
    // sentinel only — as a START it is a genuine time (#126).
    if ( $hide_midnight ) {
        if ( $utils['has_time']( $start_format ) && $start->format( 'H:i' ) === '00:00' ) {
            $start_format = $utils['remove_time']( $start_format );
        }
        if ( $utils['has_time']( $end_format ) && in_array( $end->format( 'H:i' ), array( '00:00', '23:59' ), true ) ) {
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
 * @return string
 */
if ( ! function_exists( 'bws_handle_date_time_fallback' ) ) {
function bws_handle_date_time_fallback( $options, $instance ) {
    $fallback_text = sanitize_text_field( $options['fallback'] ?? '' );

    if ( empty( $fallback_text ) ) {
        return '';
    }

    return bws_gb_tag_output( $fallback_text, $options, $instance );
}
}
