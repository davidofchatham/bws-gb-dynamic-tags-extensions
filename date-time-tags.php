<?php
/**
 * BWS ACF Date/Time Dynamic Tags for GenerateBlocks
 * 
 * Provides intelligent date/time formatting with ACF integration:
 * - Single date/time tag for individual values
 * - Range tag for date/time ranges
 * - Support for separate date and time fields
 * - Smart redundancy removal (same year/month/day logic)
 * - Intelligent time range formatting (consolidates AM/PM)
 * - Locale-aware output
 * - Graceful failure with partial information display
 * 
 * @package BWS_Dynamic_Tags
 * @version 3.1.3
 *
 * Changelog:
 * 3.1.3 - Enhanced timezone handling with noon safety buffer for date-only fields; added comprehensive documentation explaining timezone issues; improved format validation
 * 3.1.2 - Added F j, Y, g:i A format support; relaxed ACF format verification to trust field configuration
 * 3.1.1 - Added timezone-aware datetime parsing using WordPress timezone; improved format matching for time-only fields
 * 3.1.0 - Added time_only support for range tag; fixed multi-day same-month ranges (Day–Day, Year format); improved date parsing priority (ACF config first); fixed time-only display removing "Time: " prefix for actual time-only requests
 * 3.0.5 - Removed redundant extract_date function (use remove_time instead)
 * 3.0.4 - Improved remove_time utility to clean up trailing commas/separators after time removal
 * 3.0.3 - Fixed double comma issue when using combined datetime fields (date_time_separator only applies to separate fields)
 * 3.0.2 - Fixed end time field inheritance logic; added date_time_separator option
 * 3.0.1 - Added comprehensive null checks for DateTime objects in all formatting functions
 * 3.0.0 - Complete refactor into single and range tags with separate field support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Prevent duplicate loading
if ( defined( 'BWS_ACF_DATE_TIME_TAGS_V313_LOADED' ) ) {
    return;
}
define( 'BWS_ACF_DATE_TIME_TAGS_V313_LOADED', true );

// ===============================================
// TAG REGISTRATIONS
// ===============================================

/**
 * Register ACF date/time dynamic tags
 * 
 * @since 3.0.0
 * @return void
 */
function bws_register_acf_date_time_tags() {
    if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
        return;
    }
    
    // Prevent duplicate registration
    static $registered = false;
    if ( $registered ) {
        return;
    }
    $registered = true;

    // Single date/time tag
    new GenerateBlocks_Register_Dynamic_Tag(
        [
            'title'       => __( 'Post Date/Time from ACF Field', 'generateblocks' ),
            'tag'         => 'post_acf_date_time_single',
            'type'        => 'post',
            'supports'    => [ 'source' ],
            'description' => __( 'Display a single date, time, or date/time value from ACF fields. Supports separate date and time fields or combined datetime fields.', 'generateblocks' ),
            'options'     => bws_get_single_date_time_options(),
            'return'      => 'bws_get_single_date_time_callback',
        ]
    );

    // Range date/time tag
    new GenerateBlocks_Register_Dynamic_Tag(
        [
            'title'       => __( 'Post Date/Time Range from ACF Fields', 'generateblocks' ),
            'tag'         => 'post_acf_date_time_range',
            'type'        => 'post',
            'supports'    => [ 'source' ],
            'description' => __( 'Display smart date/time ranges from ACF fields with automatic redundancy removal and intelligent time formatting. Supports separate date and time fields.', 'generateblocks' ),
            'options'     => bws_get_range_date_time_options(),
            'return'      => 'bws_get_range_date_time_callback',
        ]
    );
}
add_action( 'init', 'bws_register_acf_date_time_tags' );

// ===============================================
// OPTIONS CONFIGURATION - SINGLE TAG
// ===============================================

/**
 * Get single date/time tag options
 * 
 * @since 3.0.0
 * @return array
 */
if ( ! function_exists( 'bws_get_single_date_time_options' ) ) {
function bws_get_single_date_time_options() {
    return [
        'date_time_field' => [
            'type'        => 'text',
            'label'       => __( 'Date/Date-Time Field', 'generateblocks' ),
            'help'        => __( 'ACF field key for a date-time, date, or time picker field.', 'generateblocks' ),
            'placeholder' => __( 'event_date', 'generateblocks' ),
        ],
        'time_field' => [
            'type'        => 'text',
            'label'       => __( 'Time Field (Optional)', 'generateblocks' ),
            'help'        => __( 'ACF time picker field to override or add time component. Use this for time-only fields instead of Date/Time Field above.', 'generateblocks' ),
            'placeholder' => __( 'event_time', 'generateblocks' ),
        ],
        'format_type' => [
            'type'    => 'select',
            'label'   => __( 'Format Type', 'generateblocks' ),
            'default' => 'auto',
            'options' => [
                [ 'value' => 'auto', 'label' => __( 'Auto (Use ACF Return Format)', 'generateblocks' ) ],
                [ 'value' => 'custom', 'label' => __( 'Custom Format', 'generateblocks' ) ],
            ],
        ],
        'custom_format' => [
            'type'        => 'text',
            'label'       => __( 'Custom Format', 'generateblocks' ),
            'help'        => __( 'PHP date format string (e.g., "F j, Y g:i A"). See PHP date() documentation.', 'generateblocks' ),
            'placeholder' => __( 'F j, Y g:i A', 'generateblocks' ),
        ],
        'date_only' => [
            'type'    => 'checkbox',
            'label'   => __( 'Show date only', 'generateblocks' ),
            'help'    => __( 'Hide time components even if present in fields.', 'generateblocks' ),
            'default' => false,
        ],
        'time_only' => [
            'type'    => 'checkbox',
            'label'   => __( 'Show time only', 'generateblocks' ),
            'help'    => __( 'Hide date components even if present in fields.', 'generateblocks' ),
            'default' => false,
        ],
        'smart_time' => [
            'type'    => 'checkbox',
            'label'   => __( 'Smart Time Formatting', 'generateblocks' ),
            'help'    => __( 'Hide time if midnight and other intelligent time formatting.', 'generateblocks' ),
            'default' => true,
        ],
        'omit_current_year' => [
            'type'    => 'checkbox',
            'label'   => __( 'Omit Current Year', 'generateblocks' ),
            'help'    => __( 'Hide the year when it matches the current year.', 'generateblocks' ),
            'default' => true,
        ],
        'fallback_text' => [
            'type'        => 'text',
            'label'       => __( 'Fallback Text', 'generateblocks' ),
            'help'        => __( 'Text to display when no valid date/time is found.', 'generateblocks' ),
            'default'     => '',
            'placeholder' => __( 'Date/time TBA', 'generateblocks' ),
        ],
    ];
}
}

// ===============================================
// OPTIONS CONFIGURATION - RANGE TAG
// ===============================================

/**
 * Get range date/time tag options
 * 
 * @since 3.0.0
 * @return array
 */
if ( ! function_exists( 'bws_get_range_date_time_options' ) ) {
function bws_get_range_date_time_options() {
    return [
        'start_field' => [
            'type'        => 'text',
            'label'       => __( 'Start Date/Date-Time Field', 'generateblocks' ),
            'help'        => __( 'ACF field key for start date-time, date, or time picker (required).', 'generateblocks' ),
            'default'     => __( 'start_date_time' ),
            'placeholder' => __( 'start_date_time', 'generateblocks' ),
        ],
        'start_time_field' => [
            'type'        => 'text',
            'label'       => __( 'Start Time Field (Optional)', 'generateblocks' ),
            'help'        => __( 'ACF time picker to override or add time component for start. Use this for time-only fields instead of Start Date/Date-Time Field above.', 'generateblocks' ),
            'placeholder' => __( 'start_time', 'generateblocks' ),
        ],
        'end_field' => [
            'type'        => 'text',
            'label'       => __( 'End Date/Date-Time Field', 'generateblocks' ),
            'help'        => __( 'ACF field key for end date-time, date, or time picker (optional). Time-only values will inherit date from start.', 'generateblocks' ),
            'default'     => __( 'end_date_time' ),
            'placeholder' => __( 'end_date_time', 'generateblocks' ),
        ],
        'end_time_field' => [
            'type'        => 'text',
            'label'       => __( 'End Time Field (Optional)', 'generateblocks' ),
            'help'        => __( 'ACF time picker to override or add time component for end. Use this for time-only fields instead of End Date/Date-Time Field above.', 'generateblocks' ),
            'placeholder' => __( 'end_time', 'generateblocks' ),
        ],
        'format_type' => [
            'type'    => 'select',
            'label'   => __( 'Format Type', 'generateblocks' ),
            'default' => 'auto',
            'options' => [
                [ 'value' => 'auto', 'label' => __( 'Auto (Use ACF Return Format)', 'generateblocks' ) ],
                [ 'value' => 'custom', 'label' => __( 'Custom Format', 'generateblocks' ) ],
            ],
        ],
        'custom_format' => [
            'type'        => 'text',
            'label'       => __( 'Custom Format', 'generateblocks' ),
            'help'        => __( 'PHP date format string (e.g., "F j, Y g:i A"). See PHP date() documentation.', 'generateblocks' ),
            'placeholder' => __( 'F j, Y g:i A', 'generateblocks' ),
        ],
        'date_only' => [
            'type'    => 'checkbox',
            'label'   => __( 'Show date only', 'generateblocks' ),
            'help'    => __( 'Hide time components even if present in fields.', 'generateblocks' ),
            'default' => false,
        ],
        'time_only' => [
            'type'    => 'checkbox',
            'label'   => __( 'Show time only', 'generateblocks' ),
            'help'    => __( 'Hide date components and show only time range.', 'generateblocks' ),
            'default' => false,
        ],
        'smart_time' => [
            'type'    => 'checkbox',
            'label'   => __( 'Smart Time Formatting', 'generateblocks' ),
            'help'    => __( 'Hide time if midnight, consolidate AM/PM in ranges (e.g., "5:00–8:00 PM"), and other intelligent time formatting.', 'generateblocks' ),
            'default' => true,
        ],
        'omit_current_year' => [
            'type'    => 'checkbox',
            'label'   => __( 'Omit Current Year', 'generateblocks' ),
            'help'    => __( 'Hide the year when it matches the current year.', 'generateblocks' ),
            'default' => true,
        ],
        'separator' => [
            'type'        => 'text',
            'label'       => __( 'Date Separator', 'generateblocks' ),
            'help'        => __( 'Text between start and end dates.', 'generateblocks' ),
            'default'     => '–',
            'placeholder' => __( '–', 'generateblocks' ),
        ],
        'date_time_separator' => [
            'type'        => 'text',
            'label'       => __( 'Date-Time Separator', 'generateblocks' ),
            'help'        => __( 'Text between date and time when using separate fields (e.g., ", " or " at "). Leave empty to use default (", ").', 'generateblocks' ),
            'placeholder' => __( ', ', 'generateblocks' ),
        ],
        'fallback_text' => [
            'type'        => 'text',
            'label'       => __( 'Fallback Text', 'generateblocks' ),
            'help'        => __( 'Text to display when no valid dates are found.', 'generateblocks' ),
            'default'     => '',
            'placeholder' => __( 'Date TBA', 'generateblocks' ),
        ],
    ];
}
}

// ===============================================
// MAIN CALLBACK - SINGLE TAG
// ===============================================

/**
 * Single date/time callback
 * 
 * @since 3.0.0
 * @param array $options Tag options
 * @param array $block Block data
 * @param object $instance Block instance
 * @return string
 */
if ( ! function_exists( 'bws_get_single_date_time_callback' ) ) {
function bws_get_single_date_time_callback( $options, $block, $instance ) {
    $post_id = GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance );
    
    if ( ! $post_id ) {
        return bws_handle_date_time_fallback( $options, $instance, 'single' );
    }
    
    // Get field keys
    $date_time_field = sanitize_text_field( $options['date_time_field'] ?? '' );
    $time_field = sanitize_text_field( $options['time_field'] ?? '' );
    
    if ( empty( $date_time_field ) && empty( $time_field ) ) {
        return bws_handle_date_time_fallback( $options, $instance, 'single' );
    }
    
    // Parse combined date/time structure
    $result = bws_parse_combined_date_time(
        $post_id,
        $date_time_field,
        $time_field,
        'datetime',
        null,
        $options
    );
    
    if ( ! $result['date'] && ! $result['time_only'] ) {
        return bws_handle_date_time_fallback( $options, $instance, 'single' );
    }
    
    // Handle true time-only case (no date component at all)
    if ( $result['time_only'] && ! $result['date'] ) {
        $formatted = wp_date( 'g:i A', $result['time_only']->getTimestamp() );
        return GenerateBlocks_Dynamic_Tag_Callbacks::output( $formatted, $options, $instance );
    }
    
    // Determine format and component inclusion
    $time_only = ! empty( $options['time_only'] );
    $date_only = ! empty( $options['date_only'] );
    $include_time = ! $date_only && $result['has_time'];
    $include_date = ! $time_only;
    
    $format = bws_build_single_format( $options, $result, $include_date, $include_time );
    
    // Safety check before formatting
    if ( ! $result['date'] || ! is_a( $result['date'], 'DateTime' ) ) {
        return bws_handle_date_time_fallback( $options, $instance, 'single' );
    }
    
    // Format the date/time
    $formatted_date = bws_format_single_date_time(
        $result['date'],
        $format,
        $options
    );
    
    return GenerateBlocks_Dynamic_Tag_Callbacks::output( $formatted_date, $options, $instance );
}
}

// ===============================================
// MAIN CALLBACK - RANGE TAG
// ===============================================

/**
 * Range date/time callback
 * 
 * @since 3.0.0
 * @param array $options Tag options
 * @param array $block Block data
 * @param object $instance Block instance
 * @return string
 */
if ( ! function_exists( 'bws_get_range_date_time_callback' ) ) {
function bws_get_range_date_time_callback( $options, $block, $instance ) {
    $post_id = GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance );
    
    if ( ! $post_id ) {
        return bws_handle_date_time_fallback( $options, $instance, 'range' );
    }
    
    // Get field keys
    $start_field = sanitize_text_field( $options['start_field'] ?? '' );
    $start_time_field = sanitize_text_field( $options['start_time_field'] ?? '' );
    $end_field = sanitize_text_field( $options['end_field'] ?? '' );
    $end_time_field = sanitize_text_field( $options['end_time_field'] ?? '' );
    
    if ( empty( $start_field ) && empty( $start_time_field ) ) {
        return bws_handle_date_time_fallback( $options, $instance, 'range' );
    }
    
    // Parse start date/time
    $start_result = bws_parse_combined_date_time(
        $post_id,
        $start_field,
        $start_time_field,
        'start',
        null,
        $options
    );
    
    // Parse end date/time (may inherit date from start)
    $end_result = null;
    if ( ! empty( $end_field ) || ! empty( $end_time_field ) ) {
        $end_result = bws_parse_combined_date_time(
            $post_id,
            $end_field,
            $end_time_field,
            'end',
            $start_result['date'], // Pass start date for inheritance
            $options
        );
    }
    
    // Handle graceful failure cases
    if ( ! $start_result['date'] && ! $end_result ) {
        return bws_handle_date_time_fallback( $options, $instance, 'range' );
    }
    
    // Build partial range message if needed
    $partial_parts = [];
    
    if ( $start_result['time_only'] ) {
        $partial_parts[] = __( 'Start time: ', 'generateblocks' ) . 
            wp_date( 'g:i A', $start_result['time_only']->getTimestamp() );
    }
    
    if ( $end_result && $end_result['time_only'] ) {
        $partial_parts[] = __( 'End time: ', 'generateblocks' ) . 
            wp_date( 'g:i A', $end_result['time_only']->getTimestamp() );
    } elseif ( $end_result && $end_result['date'] && ! $start_result['date'] ) {
        // Have end date but no start date
        $partial_parts[] = __( 'End date/time: ', 'generateblocks' ) . 
            wp_date( 'F j, Y g:i A', $end_result['date']->getTimestamp() );
    }
    
    // If we have partial information, return it
    if ( ! empty( $partial_parts ) && ! $start_result['date'] ) {
        $formatted = implode( '; ', $partial_parts );
        return GenerateBlocks_Dynamic_Tag_Callbacks::output( $formatted, $options, $instance );
    }
    
    // Can't format without a valid start date (unless we're doing time_only)
    $time_only = ! empty( $options['time_only'] );
    
    if ( ! $start_result['date'] && ! $time_only ) {
        return bws_handle_date_time_fallback( $options, $instance, 'range' );
    }
    
    // Handle time-only range (both have date, but we only want to show times)
    if ( $time_only && $start_result['date'] ) {
        $end_date = $end_result ? $end_result['date'] : null;
        
        if ( $end_date ) {
            // Format time range
            $smart_time = ! empty( $options['smart_time'] );
            $time_range = bws_format_time_range( $start_result['date'], $end_date, $smart_time );
            return GenerateBlocks_Dynamic_Tag_Callbacks::output( $time_range, $options, $instance );
        } else {
            // Single time
            $formatted = wp_date( 'g:i A', $start_result['date']->getTimestamp() );
            return GenerateBlocks_Dynamic_Tag_Callbacks::output( $formatted, $options, $instance );
        }
    }
    
    // Normal range formatting
    $include_time = ! empty( $options['date_only'] ) ? false : ( $start_result['has_time'] || ( $end_result && $end_result['has_time'] ) );
    
    $format = bws_build_range_format( $options, $start_result, $end_result, $include_time );
    
    // Format the date range
    $formatted_range = bws_format_date_range(
        $start_result['date'],
        $end_result ? $end_result['date'] : null,
        $format,
        $options
    );
    
    return GenerateBlocks_Dynamic_Tag_Callbacks::output( $formatted_range, $options, $instance );
}
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
                // Handle the most common patterns correctly
                $format = preg_replace( '/,\s*Y,\s*/', ', ', $format );
                $format = preg_replace( '/,\s*Y$/', '', $format );
                $format = preg_replace( '/\s+Y,\s*/', ' ', $format );
                $format = preg_replace( '/\s+Y$/', '', $format );
                
                // Same patterns for lowercase y
                $format = preg_replace( '/,\s*y,\s*/', ', ', $format );
                $format = preg_replace( '/,\s*y$/', '', $format );
                $format = preg_replace( '/\s+y,\s*/', ' ', $format );
                $format = preg_replace( '/\s+y$/', '', $format );
                
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
// DATE/TIME PARSING FUNCTIONS
// ===============================================

/**
 * Get ACF field value
 * 
 * @since 3.0.0
 * @param int $post_id Post ID
 * @param string $field_key ACF field key
 * @return mixed ACF field value
 */
if ( ! function_exists( 'bws_get_acf_field_value' ) ) {
function bws_get_acf_field_value( $post_id, $field_key ) {
    if ( empty( $field_key ) ) {
        return null;
    }
    
    if ( ! function_exists( 'get_field' ) ) {
        return get_post_meta( $post_id, $field_key, true );
    }
    
    return get_field( $field_key, $post_id );
}
}

/**
 * Parse combined date/time from separate or combined fields
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
function bws_parse_combined_date_time( $post_id, $date_field, $time_field, $context, $inherit_date = null, $options = [] ) {
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
    
    // Get primary field value and format
    $date_value = bws_get_acf_field_value( $post_id, $date_field );
    $date_format = bws_get_acf_return_format( $date_field, $post_id );
    
    // Get time field value and format
    $time_value = bws_get_acf_field_value( $post_id, $time_field );
    $time_format = bws_get_acf_return_format( $time_field, $post_id );
    
    $utils = bws_format_utils();
    
    // Determine field types
    $date_is_time_only = $date_format && ! $utils['has_date']( $date_format ) && $utils['has_time']( $date_format );
    $date_has_time = $date_format && $utils['has_time']( $date_format );
    
    // Parse primary field
    $date_obj = null;
    if ( $date_value ) {
        $date_obj = bws_parse_acf_date_value( $date_value, $date_field, $post_id );
    }
    
    // Parse time field
    $time_obj = null;
    if ( $time_value ) {
        $time_obj = bws_parse_acf_date_value( $time_value, $time_field, $post_id );
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
                // (max timezone offset is ±12 hours, so noon can't cross into another day)
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
    // Fallback
    else {
        $format = $include_time ? 'F j, Y g:i A' : 'F j, Y';
    }
    
    // Apply date/time only filters
    if ( ! $include_date && $utils['has_date']( $format ) ) {
        $format = $utils['remove_date']( $format );
    }
    if ( ! $include_time && $utils['has_time']( $format ) ) {
        $format = $utils['remove_time']( $format );
    }
    
    // Add missing components if needed
    if ( $include_time && ! $utils['has_time']( $format ) ) {
        $time_format = $result['formats']['time_format'] ?: 'g:i A';
        $format .= ' ' . $time_format;
    }
    if ( $include_date && ! $utils['has_date']( $format ) ) {
        $date_format = $result['formats']['date_format'] ?: 'F j, Y';
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
    // Fallback
    else {
        $format = $include_time ? 'F j, Y g:i A' : 'F j, Y';
    }
    
    // Apply date-only filter if requested
    if ( ! $include_time && $utils['has_time']( $format ) ) {
        $format = $utils['remove_time']( $format );
    }
    
    // Add time if needed and not present
    if ( $include_time && ! $utils['has_time']( $format ) ) {
        $time_format = $start_result['formats']['time_format'] ?: 'g:i A';
        $format .= ' ' . $time_format;
    }
    
    return $utils['clean_format']( $format );
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
    
    // Same month and year - show "Month Day–Day, Year"
    if ( $start->format( 'F Y' ) === $end->format( 'F Y' ) ) {
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
    $fallback_text = $options['fallback_text'] ?? '';
    
    if ( empty( $fallback_text ) ) {
        $date_only = ! empty( $options['date_only'] );
        $time_only = ! empty( $options['time_only'] );
        
        if ( $time_only ) {
            $fallback_text = __( 'Time TBA', 'generateblocks' );
        } elseif ( $date_only ) {
            $fallback_text = __( 'Date TBA', 'generateblocks' );
        } else {
            $fallback_text = __( 'Date/time TBA', 'generateblocks' );
        }
    }
    
    return GenerateBlocks_Dynamic_Tag_Callbacks::output( $fallback_text, $options, $instance );
}
}
