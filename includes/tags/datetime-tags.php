<?php
/**
 * Date/Time core functions, option definitions, and base tag callbacks.
 *
 * Note: date-tags.php content was merged into this file in v1.6.0.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 * @since 1.6.0 Merged date-tags.php; added base tag option functions and callbacks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ===============================================
// OPTION DEFINITIONS — DATE TEMPLATES
// ===============================================

/**
 * Date-only single tag options (no time-related options).
 *
 * @since 1.0.0
 * @return array
 */
function bws_get_date_single_options() {
	return array(
		'date_time_field' => array(
			'type'        => 'text',
			'label'       => __( 'Date Field', 'generateblocks' ),
			'help'        => __( 'ACF date picker field key.', 'generateblocks' ),
			'placeholder' => __( 'event_date', 'generateblocks' ),
		),
		'format_type'     => array(
			'type'    => 'select',
			'label'   => __( 'Format Type', 'generateblocks' ),
			'default' => 'auto',
			'options' => array(
				array( 'value' => 'auto',   'label' => __( 'Auto (Use ACF Return Format)', 'generateblocks' ) ),
				array( 'value' => 'custom', 'label' => __( 'Custom Format', 'generateblocks' ) ),
			),
		),
		'custom_format'   => array(
			'type'        => 'text',
			'label'       => __( 'Custom Format', 'generateblocks' ),
			'help'        => __( 'PHP date format string (e.g., "F j, Y"). See PHP date() documentation.', 'generateblocks' ),
			'placeholder' => __( 'F j, Y', 'generateblocks' ),
		),
		'omit_current_year' => array(
			'type'    => 'checkbox',
			'label'   => __( 'Omit Current Year', 'generateblocks' ),
			'help'    => __( 'Hide the year when it matches the current year.', 'generateblocks' ),
			'default' => true,
		),
		'fallback_text'   => array(
			'type'        => 'text',
			'label'       => __( 'Fallback Text', 'generateblocks' ),
			'help'        => __( 'Text to display when no valid date is found.', 'generateblocks' ),
			'default'     => '',
			'placeholder' => __( 'Date TBA', 'generateblocks' ),
		),
	);
}

/**
 * Date-only range tag options (no time-related options).
 *
 * @since 1.0.0
 * @return array
 */
function bws_get_date_range_options() {
	return array(
		'start_field'   => array(
			'type'        => 'text',
			'label'       => __( 'Start Date Field', 'generateblocks' ),
			'help'        => __( 'ACF date picker field key for start date.', 'generateblocks' ),
			'placeholder' => __( 'start_date', 'generateblocks' ),
		),
		'end_field'     => array(
			'type'        => 'text',
			'label'       => __( 'End Date Field', 'generateblocks' ),
			'help'        => __( 'ACF date picker field key for end date (optional).', 'generateblocks' ),
			'placeholder' => __( 'end_date', 'generateblocks' ),
		),
		'format_type'   => array(
			'type'    => 'select',
			'label'   => __( 'Format Type', 'generateblocks' ),
			'default' => 'auto',
			'options' => array(
				array( 'value' => 'auto',   'label' => __( 'Auto (Use ACF Return Format)', 'generateblocks' ) ),
				array( 'value' => 'custom', 'label' => __( 'Custom Format', 'generateblocks' ) ),
			),
		),
		'custom_format' => array(
			'type'        => 'text',
			'label'       => __( 'Custom Format', 'generateblocks' ),
			'help'        => __( 'PHP date format string (e.g., "F j, Y"). See PHP date() documentation.', 'generateblocks' ),
			'placeholder' => __( 'F j, Y', 'generateblocks' ),
		),
		'omit_current_year' => array(
			'type'    => 'checkbox',
			'label'   => __( 'Omit Current Year', 'generateblocks' ),
			'help'    => __( 'Hide the year when it matches the current year.', 'generateblocks' ),
			'default' => true,
		),
		'separator'     => array(
			'type'        => 'text',
			'label'       => __( 'Date Separator', 'generateblocks' ),
			'help'        => __( 'Text between start and end dates.', 'generateblocks' ),
			'default'     => '–',
			'placeholder' => __( '–', 'generateblocks' ),
		),
		'fallback_text' => array(
			'type'        => 'text',
			'label'       => __( 'Fallback Text', 'generateblocks' ),
			'help'        => __( 'Text to display when no valid dates are found.', 'generateblocks' ),
			'default'     => '',
			'placeholder' => __( 'Date TBA', 'generateblocks' ),
		),
	);
}

// ===============================================
// OPTION DEFINITIONS — DATETIME TEMPLATES
// ===============================================

/**
 * DateTime single tag options (full option set with time support).
 *
 * @since 1.0.0
 * @return array
 */
function bws_get_datetime_single_options() {
	return array(
		'date_time_field'   => array(
			'type'        => 'text',
			'label'       => __( 'Date/Date-Time Field', 'generateblocks' ),
			'help'        => __( 'ACF field key for a date-time, date, or time picker field.', 'generateblocks' ),
			'placeholder' => __( 'event_date', 'generateblocks' ),
		),
		'time_field'        => array(
			'type'        => 'text',
			'label'       => __( 'Time Field (Optional)', 'generateblocks' ),
			'help'        => __( 'ACF time picker field to override or add time component.', 'generateblocks' ),
			'placeholder' => __( 'event_time', 'generateblocks' ),
		),
		'format_type'       => array(
			'type'    => 'select',
			'label'   => __( 'Format Type', 'generateblocks' ),
			'default' => 'auto',
			'options' => array(
				array( 'value' => 'auto',   'label' => __( 'Auto (Use ACF Return Format)', 'generateblocks' ) ),
				array( 'value' => 'custom', 'label' => __( 'Custom Format', 'generateblocks' ) ),
			),
		),
		'custom_format'     => array(
			'type'        => 'text',
			'label'       => __( 'Custom Format', 'generateblocks' ),
			'help'        => __( 'PHP date format string (e.g., "F j, Y g:i A").', 'generateblocks' ),
			'placeholder' => __( 'F j, Y g:i A', 'generateblocks' ),
		),
		'date_only'         => array(
			'type'    => 'checkbox',
			'label'   => __( 'Show date only', 'generateblocks' ),
			'help'    => __( 'Hide time components even if present in fields.', 'generateblocks' ),
			'default' => false,
		),
		'time_only'         => array(
			'type'    => 'checkbox',
			'label'   => __( 'Show time only', 'generateblocks' ),
			'help'    => __( 'Hide date components even if present in fields.', 'generateblocks' ),
			'default' => false,
		),
		'smart_time'        => array(
			'type'    => 'checkbox',
			'label'   => __( 'Smart Time Formatting', 'generateblocks' ),
			'help'    => __( 'Hide time if midnight and other intelligent time formatting.', 'generateblocks' ),
			'default' => true,
		),
		'omit_current_year' => array(
			'type'    => 'checkbox',
			'label'   => __( 'Omit Current Year', 'generateblocks' ),
			'help'    => __( 'Hide the year when it matches the current year.', 'generateblocks' ),
			'default' => true,
		),
		'fallback_text'     => array(
			'type'        => 'text',
			'label'       => __( 'Fallback Text', 'generateblocks' ),
			'help'        => __( 'Text to display when no valid date-time is found.', 'generateblocks' ),
			'default'     => '',
			'placeholder' => __( 'Date/time TBA', 'generateblocks' ),
		),
	);
}

/**
 * DateTime range tag options (full option set with time support).
 *
 * @since 1.0.0
 * @return array
 */
function bws_get_datetime_range_options() {
	return array(
		'start_field'         => array(
			'type'        => 'text',
			'label'       => __( 'Start Date/Date-Time Field', 'generateblocks' ),
			'help'        => __( 'ACF field key for start date-time, date, or time picker.', 'generateblocks' ),
			'placeholder' => __( 'start_date_time', 'generateblocks' ),
		),
		'start_time_field'    => array(
			'type'        => 'text',
			'label'       => __( 'Start Time Field (Optional)', 'generateblocks' ),
			'help'        => __( 'ACF time picker to override or add time component for start.', 'generateblocks' ),
			'placeholder' => __( 'start_time', 'generateblocks' ),
		),
		'end_field'           => array(
			'type'        => 'text',
			'label'       => __( 'End Date/Date-Time Field', 'generateblocks' ),
			'help'        => __( 'ACF field key for end date-time. Time-only values inherit date from start.', 'generateblocks' ),
			'placeholder' => __( 'end_date_time', 'generateblocks' ),
		),
		'end_time_field'      => array(
			'type'        => 'text',
			'label'       => __( 'End Time Field (Optional)', 'generateblocks' ),
			'help'        => __( 'ACF time picker to override or add time component for end.', 'generateblocks' ),
			'placeholder' => __( 'end_time', 'generateblocks' ),
		),
		'format_type'         => array(
			'type'    => 'select',
			'label'   => __( 'Format Type', 'generateblocks' ),
			'default' => 'auto',
			'options' => array(
				array( 'value' => 'auto',   'label' => __( 'Auto (Use ACF Return Format)', 'generateblocks' ) ),
				array( 'value' => 'custom', 'label' => __( 'Custom Format', 'generateblocks' ) ),
			),
		),
		'custom_format'       => array(
			'type'        => 'text',
			'label'       => __( 'Custom Format', 'generateblocks' ),
			'help'        => __( 'PHP date format string (e.g., "F j, Y g:i A").', 'generateblocks' ),
			'placeholder' => __( 'F j, Y g:i A', 'generateblocks' ),
		),
		'date_only'           => array(
			'type'    => 'checkbox',
			'label'   => __( 'Show date only', 'generateblocks' ),
			'help'    => __( 'Hide time components even if present in fields.', 'generateblocks' ),
			'default' => false,
		),
		'time_only'           => array(
			'type'    => 'checkbox',
			'label'   => __( 'Show time only', 'generateblocks' ),
			'help'    => __( 'Hide date components and show only time range.', 'generateblocks' ),
			'default' => false,
		),
		'smart_time'          => array(
			'type'    => 'checkbox',
			'label'   => __( 'Smart Time Formatting', 'generateblocks' ),
			'help'    => __( 'Hide time if midnight, consolidate AM/PM in ranges.', 'generateblocks' ),
			'default' => true,
		),
		'omit_current_year'   => array(
			'type'    => 'checkbox',
			'label'   => __( 'Omit Current Year', 'generateblocks' ),
			'help'    => __( 'Hide the year when it matches the current year.', 'generateblocks' ),
			'default' => true,
		),
		'separator'           => array(
			'type'        => 'text',
			'label'       => __( 'Date Separator', 'generateblocks' ),
			'help'        => __( 'Text between start and end dates.', 'generateblocks' ),
			'default'     => '–',
			'placeholder' => __( '–', 'generateblocks' ),
		),
		'date_time_separator' => array(
			'type'        => 'text',
			'label'       => __( 'Date-Time Separator', 'generateblocks' ),
			'help'        => __( 'Text between date and time when using separate fields.', 'generateblocks' ),
			'placeholder' => __( ', ', 'generateblocks' ),
		),
		'fallback_text'       => array(
			'type'        => 'text',
			'label'       => __( 'Fallback Text', 'generateblocks' ),
			'help'        => __( 'Text to display when no valid dates are found.', 'generateblocks' ),
			'default'     => '',
			'placeholder' => __( 'Date TBA', 'generateblocks' ),
		),
	);
}

// ===============================================
// OPTION DEFINITIONS — BASE TAGS
// ===============================================

/**
 * Options for the `datetime_single` base tag.
 *
 * Uses simplified option keys (key, key2, as, format, …) rather than the
 * legacy keys used by template-generated datetime tags. Callbacks remap
 * these to the existing core function keys via bws_base_map_datetime_options().
 *
 * @since 1.6.0
 * @return array
 */
/**
 * Template-specific options for the datetime_single modifier template.
 *
 * Returns only the field-key and formatting options — no via or traversal sub-options.
 * Used by register_modifier_template() in bws_register_base_tags() and as trailing
 * options in generate_base_try_tags() for try_datetime_single.
 *
 * @since 1.6.0
 * @return array
 */
function bws_get_datetime_single_template_options(): array {
	return array(
		'key'               => array(
			'type'        => 'text',
			'label'       => __( 'Date / Time Field', 'generateblocks' ),
			'help'        => __( 'ACF field key for a date, date-time, or time picker field.', 'generateblocks' ),
			'placeholder' => 'event_date',
		),
		'key2'              => array(
			'type'        => 'text',
			'label'       => __( 'Time Field (Optional)', 'generateblocks' ),
			'help'        => __( 'ACF time picker field to override or add time component.', 'generateblocks' ),
			'placeholder' => 'event_time',
		),
		'as'                => array(
			'type'    => 'select',
			'label'   => __( 'Show:', 'generateblocks' ),
			'options' => array(
				array( 'value' => '',     'label' => __( 'Date and Time', 'generateblocks' ) ),
				array( 'value' => 'date', 'label' => __( 'Date only', 'generateblocks' ) ),
				array( 'value' => 'time', 'label' => __( 'Time only', 'generateblocks' ) ),
			),
		),
		'format'            => array(
			'type'        => 'text',
			'label'       => __( 'Format', 'generateblocks' ),
			'help'        => __( 'PHP date format string. Leave blank to use ACF field format or WordPress defaults.', 'generateblocks' ),
			'placeholder' => 'F j, Y g:i A',
		),
		'time_sep'          => array(
			'type'        => 'text',
			'label'       => __( 'Date/Time Separator', 'generateblocks' ),
			'help'        => __( 'Text between date and time when using a separate time field. Default: ", ".', 'generateblocks' ),
			'placeholder' => ', ',
			'show_if'     => array( 'format' => 'empty', 'as' => 'not_in:date,time' ),
		),
		'show_current_year' => array(
			'type'  => 'checkbox',
			'label' => __( 'Show current year', 'generateblocks' ),
			'help'  => __( 'Include the year even when it matches the current year.', 'generateblocks' ),
		),
		'show_midnight'     => array(
			'type'  => 'checkbox',
			'label' => __( 'Show time at midnight', 'generateblocks' ),
			'help'  => __( 'Show the time component even when it is 12:00 AM.', 'generateblocks' ),
		),
		'fallback'          => array(
			'type'        => 'text',
			'label'       => __( 'Fallback Text', 'generateblocks' ),
			'help'        => __( 'Text to display when no valid date/time is found.', 'generateblocks' ),
			'placeholder' => 'Date/time TBA',
		),
	);
}

function bws_get_base_datetime_single_options(): array {
	return array_merge(
		bws_base_source_option(),
		bws_base_traversal_options(),
		bws_get_datetime_single_template_options()
	);
}

/**
 * Options for the `datetime_range` base tag.
 *
 * Mirrors bws_get_base_datetime_single_options() with range-specific field keys
 * (key/key2 = start, end/end2 = end) and a range_sep option.
 *
 * @since 1.6.0
 * @return array
 */
/**
 * Template-specific options for the datetime_range modifier template.
 *
 * Returns only the field-key and formatting options — no via or traversal sub-options.
 * Mirrors bws_get_datetime_single_template_options() with range-specific fields.
 *
 * @since 1.6.0
 * @return array
 */
function bws_get_datetime_range_template_options(): array {
	return array(
		'key'               => array(
			'type'        => 'text',
			'label'       => __( 'Start Date / Time Field', 'generateblocks' ),
			'help'        => __( 'ACF field key for start date-time, date, or time picker.', 'generateblocks' ),
			'placeholder' => 'start_date',
		),
		'key2'              => array(
			'type'        => 'text',
			'label'       => __( 'Start Time Field (Optional)', 'generateblocks' ),
			'help'        => __( 'ACF time picker to override or add time component for start.', 'generateblocks' ),
			'placeholder' => 'start_time',
		),
		'end'               => array(
			'type'        => 'text',
			'label'       => __( 'End Date / Time Field', 'generateblocks' ),
			'help'        => __( 'ACF field key for end date-time. Time-only values inherit date from start.', 'generateblocks' ),
			'placeholder' => 'end_date',
		),
		'end2'              => array(
			'type'        => 'text',
			'label'       => __( 'End Time Field (Optional)', 'generateblocks' ),
			'help'        => __( 'ACF time picker to override or add time component for end.', 'generateblocks' ),
			'placeholder' => 'end_time',
		),
		'as'                => array(
			'type'    => 'select',
			'label'   => __( 'Show:', 'generateblocks' ),
			'options' => array(
				array( 'value' => '',     'label' => __( 'Date and Time', 'generateblocks' ) ),
				array( 'value' => 'date', 'label' => __( 'Date only', 'generateblocks' ) ),
				array( 'value' => 'time', 'label' => __( 'Time only', 'generateblocks' ) ),
			),
		),
		'format'            => array(
			'type'        => 'text',
			'label'       => __( 'Format', 'generateblocks' ),
			'help'        => __( 'PHP date format string. Leave blank to use ACF field format or WordPress defaults.', 'generateblocks' ),
			'placeholder' => 'F j, Y g:i A',
		),
		'time_sep'          => array(
			'type'        => 'text',
			'label'       => __( 'Date/Time Separator', 'generateblocks' ),
			'help'        => __( 'Text between date and time when using a separate time field. Default: ", ".', 'generateblocks' ),
			'placeholder' => ', ',
			'show_if'     => array( 'format' => 'empty', 'as' => 'not_in:date,time' ),
		),
		'range_sep'         => array(
			'type'        => 'text',
			'label'       => __( 'Range Separator', 'generateblocks' ),
			'help'        => __( 'Text between start and end dates. Default: –', 'generateblocks' ),
			'placeholder' => '–',
		),
		'show_current_year' => array(
			'type'  => 'checkbox',
			'label' => __( 'Show current year', 'generateblocks' ),
			'help'  => __( 'Include the year even when it matches the current year.', 'generateblocks' ),
		),
		'show_midnight'     => array(
			'type'  => 'checkbox',
			'label' => __( 'Show time at midnight', 'generateblocks' ),
			'help'  => __( 'Show the time component even when it is 12:00 AM.', 'generateblocks' ),
		),
		'fallback'          => array(
			'type'        => 'text',
			'label'       => __( 'Fallback Text', 'generateblocks' ),
			'help'        => __( 'Text to display when no valid dates are found.', 'generateblocks' ),
			'placeholder' => 'Date TBA',
		),
	);
}

function bws_get_base_datetime_range_options(): array {
	return array_merge(
		bws_base_source_option(),
		bws_base_traversal_options(),
		bws_get_datetime_range_template_options()
	);
}

// ===============================================
// CORE FUNCTIONS — DATE TEMPLATES
// ===============================================

/**
 * Core date-only single logic (shared by source-specific callbacks).
 *
 * @since 1.0.0
 * @param int|string|false $post_id  Resolved post ID (or ACF object ID for term context).
 * @param array            $options  Tag options.
 * @param object           $instance Block instance.
 * @return string
 */
function bws_date_single_core( $post_id, $options, $instance ) {
	if ( ! $post_id ) {
		return bws_handle_date_time_fallback( $options, $instance, 'single' );
	}

	$date_field = sanitize_text_field( $options['date_time_field'] ?? '' );

	if ( empty( $date_field ) ) {
		return bws_handle_date_time_fallback( $options, $instance, 'single' );
	}

	// Force date_only mode — no time handling.
	$date_options = array_merge( $options, array(
		'date_only'  => true,
		'time_only'  => false,
		'smart_time' => false,
		'time_field' => '',
	) );

	$result = bws_parse_combined_date_time( $post_id, $date_field, '', 'datetime', null, $date_options );

	if ( ! $result['date'] || ! is_a( $result['date'], 'DateTime' ) ) {
		return bws_handle_date_time_fallback( $options, $instance, 'single' );
	}

	$format = bws_build_single_format( $date_options, $result, true, false );

	$formatted = bws_format_single_date_time( $result['date'], $format, $date_options );

	return GenerateBlocks_Dynamic_Tag_Callbacks::output( $formatted, $options, $instance );
}

/**
 * Core date-only range logic (shared by source-specific callbacks).
 *
 * @since 1.0.0
 * @param int|string|false $post_id  Resolved post ID (or ACF object ID for term context).
 * @param array            $options  Tag options.
 * @param object           $instance Block instance.
 * @return string
 */
function bws_date_range_core( $post_id, $options, $instance ) {
	if ( ! $post_id ) {
		return bws_handle_date_time_fallback( $options, $instance, 'range' );
	}

	$start_field = sanitize_text_field( $options['start_field'] ?? '' );
	$end_field   = sanitize_text_field( $options['end_field'] ?? '' );

	if ( empty( $start_field ) ) {
		return bws_handle_date_time_fallback( $options, $instance, 'range' );
	}

	// Force date_only mode — no time handling.
	$date_options = array_merge( $options, array(
		'date_only'        => true,
		'time_only'        => false,
		'smart_time'       => false,
		'start_time_field' => '',
		'end_time_field'   => '',
	) );

	$start_result = bws_parse_combined_date_time( $post_id, $start_field, '', 'start', null, $date_options );

	$end_result = null;
	if ( ! empty( $end_field ) ) {
		$end_result = bws_parse_combined_date_time( $post_id, $end_field, '', 'end', $start_result['date'], $date_options );
	}

	if ( ! $start_result['date'] ) {
		return bws_handle_date_time_fallback( $options, $instance, 'range' );
	}

	$format = bws_build_range_format( $date_options, $start_result, $end_result, false );

	$formatted = bws_format_date_range(
		$start_result['date'],
		$end_result ? $end_result['date'] : null,
		$format,
		$date_options
	);

	return GenerateBlocks_Dynamic_Tag_Callbacks::output( $formatted, $options, $instance );
}

/**
 * Term date single core — converts term_id to ACF object_id format, then delegates.
 *
 * ACF accepts '{taxonomy}_{term_id}' (e.g. 'category_5') as the object_id for term fields.
 *
 * @since 1.2.0
 * @param int|false $term_id  Resolved term ID.
 * @param array     $options  Tag options.
 * @param object    $instance Block instance.
 * @return string
 */
function bws_term_date_single_core( $term_id, $options, $instance ) {
	if ( ! $term_id ) {
		return bws_date_single_core( false, $options, $instance );
	}
	$term = bws_get_validated_term( $term_id );
	if ( ! $term ) {
		return bws_date_single_core( false, $options, $instance );
	}
	return bws_date_single_core( $term->taxonomy . '_' . $term->term_id, $options, $instance );
}

/**
 * Term date range core — converts term_id to ACF object_id format, then delegates.
 *
 * @since 1.2.0
 * @param int|false $term_id  Resolved term ID.
 * @param array     $options  Tag options.
 * @param object    $instance Block instance.
 * @return string
 */
function bws_term_date_range_core( $term_id, $options, $instance ) {
	if ( ! $term_id ) {
		return bws_date_range_core( false, $options, $instance );
	}
	$term = bws_get_validated_term( $term_id );
	if ( ! $term ) {
		return bws_date_range_core( false, $options, $instance );
	}
	return bws_date_range_core( $term->taxonomy . '_' . $term->term_id, $options, $instance );
}

// ===============================================
// CORE FUNCTIONS — DATETIME TEMPLATES
// ===============================================

/**
 * Core datetime single logic.
 *
 * @since 1.0.0
 * @param int|string|false $post_id  Resolved post ID (or ACF object ID for term context).
 * @param array            $options  Tag options.
 * @param object           $instance Block instance.
 * @return string
 */
function bws_datetime_single_core( $post_id, $options, $instance ) {
	if ( ! $post_id ) {
		return bws_handle_date_time_fallback( $options, $instance, 'single' );
	}

	$date_time_field = sanitize_text_field( $options['date_time_field'] ?? '' );
	$time_field      = sanitize_text_field( $options['time_field'] ?? '' );

	if ( empty( $date_time_field ) && empty( $time_field ) ) {
		return bws_handle_date_time_fallback( $options, $instance, 'single' );
	}

	$result = bws_parse_combined_date_time( $post_id, $date_time_field, $time_field, 'datetime', null, $options );

	if ( ! $result['date'] && ! $result['time_only'] ) {
		return bws_handle_date_time_fallback( $options, $instance, 'single' );
	}

	// Handle true time-only case.
	if ( $result['time_only'] && ! $result['date'] ) {
		$formatted = wp_date( 'g:i A', $result['time_only']->getTimestamp() );
		return GenerateBlocks_Dynamic_Tag_Callbacks::output( $formatted, $options, $instance );
	}

	$time_only    = ! empty( $options['time_only'] );
	$date_only    = ! empty( $options['date_only'] );
	$include_time = ! $date_only && $result['has_time'];
	$include_date = ! $time_only;

	$format = bws_build_single_format( $options, $result, $include_date, $include_time );

	if ( ! $result['date'] || ! is_a( $result['date'], 'DateTime' ) ) {
		return bws_handle_date_time_fallback( $options, $instance, 'single' );
	}

	$formatted = bws_format_single_date_time( $result['date'], $format, $options );

	return GenerateBlocks_Dynamic_Tag_Callbacks::output( $formatted, $options, $instance );
}

/**
 * Core datetime range logic.
 *
 * @since 1.0.0
 * @param int|string|false $post_id  Resolved post ID (or ACF object ID for term context).
 * @param array            $options  Tag options.
 * @param object           $instance Block instance.
 * @return string
 */
function bws_datetime_range_core( $post_id, $options, $instance ) {
	if ( ! $post_id ) {
		return bws_handle_date_time_fallback( $options, $instance, 'range' );
	}

	$start_field      = sanitize_text_field( $options['start_field'] ?? '' );
	$start_time_field = sanitize_text_field( $options['start_time_field'] ?? '' );
	$end_field        = sanitize_text_field( $options['end_field'] ?? '' );
	$end_time_field   = sanitize_text_field( $options['end_time_field'] ?? '' );

	if ( empty( $start_field ) && empty( $start_time_field ) ) {
		return bws_handle_date_time_fallback( $options, $instance, 'range' );
	}

	$start_result = bws_parse_combined_date_time( $post_id, $start_field, $start_time_field, 'start', null, $options );

	$end_result = null;
	if ( ! empty( $end_field ) || ! empty( $end_time_field ) ) {
		$end_result = bws_parse_combined_date_time( $post_id, $end_field, $end_time_field, 'end', $start_result['date'], $options );
	}

	if ( ! $start_result['date'] && ! $end_result ) {
		return bws_handle_date_time_fallback( $options, $instance, 'range' );
	}

	// Handle partial information.
	$partial_parts = array();
	if ( $start_result['time_only'] ) {
		$partial_parts[] = __( 'Start time: ', 'generateblocks' ) . wp_date( 'g:i A', $start_result['time_only']->getTimestamp() );
	}
	if ( $end_result && $end_result['time_only'] ) {
		$partial_parts[] = __( 'End time: ', 'generateblocks' ) . wp_date( 'g:i A', $end_result['time_only']->getTimestamp() );
	} elseif ( $end_result && $end_result['date'] && ! $start_result['date'] ) {
		$partial_parts[] = __( 'End date/time: ', 'generateblocks' ) . wp_date( 'F j, Y g:i A', $end_result['date']->getTimestamp() );
	}
	if ( ! empty( $partial_parts ) && ! $start_result['date'] ) {
		$formatted = implode( '; ', $partial_parts );
		return GenerateBlocks_Dynamic_Tag_Callbacks::output( $formatted, $options, $instance );
	}

	$time_only = ! empty( $options['time_only'] );

	if ( ! $start_result['date'] && ! $time_only ) {
		return bws_handle_date_time_fallback( $options, $instance, 'range' );
	}

	// Handle time-only range.
	if ( $time_only && $start_result['date'] ) {
		$end_date = $end_result ? $end_result['date'] : null;
		if ( $end_date ) {
			$smart_time = ! empty( $options['smart_time'] );
			$time_range = bws_format_time_range( $start_result['date'], $end_date, $smart_time );
			return GenerateBlocks_Dynamic_Tag_Callbacks::output( $time_range, $options, $instance );
		} else {
			$formatted = wp_date( 'g:i A', $start_result['date']->getTimestamp() );
			return GenerateBlocks_Dynamic_Tag_Callbacks::output( $formatted, $options, $instance );
		}
	}

	// Normal range formatting.
	$include_time = ! empty( $options['date_only'] ) ? false : ( $start_result['has_time'] || ( $end_result && $end_result['has_time'] ) );
	$format       = bws_build_range_format( $options, $start_result, $end_result, $include_time );

	$formatted = bws_format_date_range(
		$start_result['date'],
		$end_result ? $end_result['date'] : null,
		$format,
		$options
	);

	return GenerateBlocks_Dynamic_Tag_Callbacks::output( $formatted, $options, $instance );
}

/**
 * Term datetime single core — converts term_id to ACF object_id format, then delegates.
 *
 * ACF accepts '{taxonomy}_{term_id}' (e.g. 'category_5') as the object_id for term fields.
 *
 * @since 1.2.0
 * @param int|false $term_id  Resolved term ID.
 * @param array     $options  Tag options.
 * @param object    $instance Block instance.
 * @return string
 */
function bws_term_datetime_single_core( $term_id, $options, $instance ) {
	if ( ! $term_id ) {
		return bws_datetime_single_core( false, $options, $instance );
	}
	$term = bws_get_validated_term( $term_id );
	if ( ! $term ) {
		return bws_datetime_single_core( false, $options, $instance );
	}
	return bws_datetime_single_core( $term->taxonomy . '_' . $term->term_id, $options, $instance );
}

/**
 * Term datetime range core — converts term_id to ACF object_id format, then delegates.
 *
 * @since 1.2.0
 * @param int|false $term_id  Resolved term ID.
 * @param array     $options  Tag options.
 * @param object    $instance Block instance.
 * @return string
 */
function bws_term_datetime_range_core( $term_id, $options, $instance ) {
	if ( ! $term_id ) {
		return bws_datetime_range_core( false, $options, $instance );
	}
	$term = bws_get_validated_term( $term_id );
	if ( ! $term ) {
		return bws_datetime_range_core( false, $options, $instance );
	}
	return bws_datetime_range_core( $term->taxonomy . '_' . $term->term_id, $options, $instance );
}

// ===============================================
// BASE TAG HELPERS + CALLBACKS
// ===============================================

/**
 * Map base datetime single options to keys expected by existing core functions.
 *
 * Base tags use a simplified, consistent option set (key, key2, as, format,
 * show_current_year, show_midnight, fallback). Existing core functions read
 * legacy keys (date_time_field, time_field, format_type, custom_format,
 * date_only, time_only, smart_time, omit_current_year, fallback_text).
 * This function bridges the gap without modifying the core functions.
 *
 * @since 1.6.0
 * @param array $options Raw tag options from GenerateBlocks.
 * @return array Remapped options for existing core functions.
 */
function bws_base_map_datetime_options( array $options ): array {
	$mapped = $options;

	// key → date_time_field (for single; range overrides this below).
	if ( isset( $options['key'] ) && ! isset( $options['date_time_field'] ) ) {
		$mapped['date_time_field'] = $options['key'];
	}

	// key2 → time_field (for single; range overrides this below).
	if ( isset( $options['key2'] ) && ! isset( $options['time_field'] ) ) {
		$mapped['time_field'] = $options['key2'];
	}

	// as → date_only / time_only.
	$as                  = $options['as'] ?? '';
	$mapped['date_only'] = ( 'date' === $as );
	$mapped['time_only'] = ( 'time' === $as );

	// format (plain string) → format_type + custom_format.
	if ( ! empty( $options['format'] ) ) {
		$mapped['format_type']   = 'custom';
		$mapped['custom_format'] = $options['format'];
	} elseif ( ! isset( $options['format_type'] ) ) {
		$mapped['format_type'] = 'auto';
	}

	// time_sep → date_time_separator (used by bws_parse_combined_date_time()).
	if ( isset( $options['time_sep'] ) && ! isset( $options['date_time_separator'] ) ) {
		$mapped['date_time_separator'] = $options['time_sep'];
	}

	// show_current_year (true = show) → omit_current_year (true = omit) — inverted.
	// Absent show_current_year → omit_current_year true (default: omit year).
	$mapped['omit_current_year'] = empty( $options['show_current_year'] );

	// show_midnight (true = show midnight) → smart_time (true = hide midnight) — inverted.
	// Absent show_midnight → smart_time true (default: smart-hide midnight).
	$mapped['smart_time'] = empty( $options['show_midnight'] );

	// fallback → fallback_text.
	if ( isset( $options['fallback'] ) && ! isset( $options['fallback_text'] ) ) {
		$mapped['fallback_text'] = $options['fallback'];
	}

	return $mapped;
}

/**
 * Map base datetime range options to keys expected by existing range core functions.
 *
 * Extends bws_base_map_datetime_options() with range-specific remapping:
 * key/key2 become start_field/start_time_field, and end/end2 become
 * end_field/end_time_field. range_sep becomes separator.
 *
 * @since 1.6.0
 * @param array $options Raw tag options from GenerateBlocks.
 * @return array Remapped options for existing range core functions.
 */
function bws_base_map_datetime_range_options( array $options ): array {
	// Start with base mapping (handles format, as, show_current_year, show_midnight, etc.).
	$mapped = bws_base_map_datetime_options( $options );

	// Undo single-tag key mappings; range core reads different field names.
	unset( $mapped['date_time_field'], $mapped['time_field'] );

	// key → start_field.
	if ( isset( $options['key'] ) && ! isset( $options['start_field'] ) ) {
		$mapped['start_field'] = $options['key'];
	}

	// key2 → start_time_field.
	if ( isset( $options['key2'] ) && ! isset( $options['start_time_field'] ) ) {
		$mapped['start_time_field'] = $options['key2'];
	}

	// end → end_field.
	if ( isset( $options['end'] ) && ! isset( $options['end_field'] ) ) {
		$mapped['end_field'] = $options['end'];
	}

	// end2 → end_time_field.
	if ( isset( $options['end2'] ) && ! isset( $options['end_time_field'] ) ) {
		$mapped['end_time_field'] = $options['end2'];
	}

	// range_sep → separator (used by bws_format_date_range()).
	if ( isset( $options['range_sep'] ) && ! isset( $options['separator'] ) ) {
		$mapped['separator'] = $options['range_sep'];
	}

	return $mapped;
}

/**
 * Callback for the `datetime_single` base tag.
 *
 * Resolves entity via `source`, applies srcTerm hop when set, then
 * delegates to bws_datetime_single_core() or bws_term_datetime_single_core().
 * Remaps base tag option keys to legacy core function keys before dispatching.
 *
 * @since 1.6.0
 */
function bws_base_datetime_single_callback( $options, $block, $instance ): string {
	if ( ! empty( $instance->context['bwsEditorPreview'] ) ) {
		return function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'datetime_single' ) : '';
	}

	$src_term = ! empty( $options['srcTerm'] );
	$mapped   = bws_base_map_datetime_options( $options );

	if ( $src_term ) {
		$post_id = function_exists( 'bws_resolve_post_by_source' )
			? bws_resolve_post_by_source( $options, $instance )
			: get_the_ID();
		$tax   = sanitize_key( $options['tax'] ?? '' );
		$terms = ( $post_id && function_exists( 'bws_get_srcterm_terms' ) )
			? bws_get_srcterm_terms( (int) $post_id, $tax )
			: [];
		foreach ( $terms as $term ) {
			$result = bws_term_datetime_single_core( $term->term_id, $mapped, $instance );
			if ( '' !== $result ) {
				return $result;
			}
		}
		return '';
	}

	$post_id = function_exists( 'bws_resolve_post_by_source' )
		? bws_resolve_post_by_source( $options, $instance )
		: get_the_ID();
	return bws_datetime_single_core( $post_id, $mapped, $instance );
}

/**
 * Callback for the `datetime_range` base tag.
 *
 * Resolves entity via `source`, applies srcTerm hop when set, then
 * delegates to bws_datetime_range_core() or bws_term_datetime_range_core().
 * Remaps base tag option keys to legacy core function keys before dispatching.
 *
 * @since 1.6.0
 */
function bws_base_datetime_range_callback( $options, $block, $instance ): string {
	if ( ! empty( $instance->context['bwsEditorPreview'] ) ) {
		return function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'datetime_range' ) : '';
	}

	$src_term = ! empty( $options['srcTerm'] );
	$mapped   = bws_base_map_datetime_range_options( $options );

	if ( $src_term ) {
		$post_id = function_exists( 'bws_resolve_post_by_source' )
			? bws_resolve_post_by_source( $options, $instance )
			: get_the_ID();
		$tax   = sanitize_key( $options['tax'] ?? '' );
		$terms = ( $post_id && function_exists( 'bws_get_srcterm_terms' ) )
			? bws_get_srcterm_terms( (int) $post_id, $tax )
			: [];
		foreach ( $terms as $term ) {
			$result = bws_term_datetime_range_core( $term->term_id, $mapped, $instance );
			if ( '' !== $result ) {
				return $result;
			}
		}
		return '';
	}

	$post_id = function_exists( 'bws_resolve_post_by_source' )
		? bws_resolve_post_by_source( $options, $instance )
		: get_the_ID();
	return bws_datetime_range_core( $post_id, $mapped, $instance );
}
