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
			'help'    => __( 'Hide the year when it matches the current year. Ranges spanning two different years always show both years regardless of this setting.', 'generateblocks' ),
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
			'help'    => __( 'Hide the year when it matches the current year. Ranges spanning two different years always show both years regardless of this setting.', 'generateblocks' ),
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
	return array_merge(
		bws_get_datetime_single_leading_options(),
		bws_get_datetime_single_field_key_options(),
		array(
			'fallback' => array(
				'type'        => 'text',
				'label'       => __( 'Fallback Text', 'generateblocks' ),
				'help'        => __( 'Text to display when no valid date/time is found.', 'generateblocks' ),
				'placeholder' => 'Date/time TBA',
			),
		)
	);
}

function bws_get_datetime_single_leading_options(): array {
	return array(
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
			'type'        => 'bws-format-input',
			'label'       => __( 'Format', 'generateblocks' ),
			'help'        => __( 'PHP date format string. Leave blank to use ACF field format or WordPress defaults.', 'generateblocks' ),
			'placeholder' => 'F j, Y g:i A',
		),
		'timeSep'          => array(
			'type'        => 'text',
			'label'       => __( 'Date/Time Separator', 'generateblocks' ),
			'help'        => __( 'Text between date and time when using a separate time field. Default: ", ".', 'generateblocks' ),
			'placeholder' => ', ',
			'show_if'     => array( 'format' => 'empty', 'as' => 'not_in:date,time' ),
		),
		'showCurrentYear' => array(
			'type'  => 'checkbox',
			'label' => __( 'Show current year', 'generateblocks' ),
			'help'  => __( 'Include the year even when it matches the current year. Ranges spanning two different years always show both years regardless of this setting.', 'generateblocks' ),
		),
		'showMidnight'    => array(
			'type'  => 'checkbox',
			'label' => __( 'Show time at midnight', 'generateblocks' ),
			'help'  => __( 'Show the time component even when it is 12:00 AM.', 'generateblocks' ),
		),
	);
}

function bws_get_datetime_single_field_key_options(): array {
	return array(
		'key'     => array(
			'type'         => 'bws-field-combo',
			'label'        => __( 'Date/Time Field Key', 'generateblocks' ),
			'help'         => __( 'ACF field key for a date, date-time, or time picker field.', 'generateblocks' ),
			'placeholder'  => 'event_date',
		),
		'timeKey' => array(
			'type'         => 'bws-field-combo',
			'label'        => __( 'Time Field Key (optional)', 'generateblocks' ),
			'help'         => __( 'ACF time picker field to override or add time component.', 'generateblocks' ),
			'placeholder'  => 'event_time',
		),
	);
}

function bws_get_base_datetime_single_options(): array {
	return array_merge(
		bws_get_datetime_single_leading_options(),
		bws_base_source_option(),
		bws_base_traversal_options(),
		bws_get_datetime_single_field_key_options(),
		array(
			'fallback' => array(
				'type'        => 'text',
				'label'       => __( 'Fallback Text', 'generateblocks' ),
				'help'        => __( 'Text to display when no valid date/time is found.', 'generateblocks' ),
				'placeholder' => 'Date/time TBA',
			),
		),
		bws_get_datetime_list_options( false ),
		function_exists( 'bws_get_link_options' ) ? bws_get_link_options() : array()
	);
}

/**
 * List-mode options (`limit` / `sep`) shared by both datetime base tags (#30).
 *
 * Mirrors base text/title: list mode only applies to the final traversal step —
 * terms (srcTermIn set) or related posts (src:ref). Scalar sources return one
 * value, so both controls stay hidden otherwise.
 *
 * @since 1.15.0
 * @param bool $range Range tag: `sep` help gains the sep-vs-rangeSep distinction.
 * @return array
 */
function bws_get_datetime_list_options( bool $range = false ): array {
	$sep_help = $range
		? __( 'Text to place between results. Default: ", ". This joins whole ranges; the Range Separator stays between each start and end.', 'generateblocks' )
		: __( 'Text to place between results. Default: ", ".', 'generateblocks' );
	return array(
		'limit' => array(
			'type'        => 'number',
			'label'       => __( 'Result Limit', 'generateblocks' ),
			'help'        => __( 'Maximum number of results to return. Default: 1.', 'generateblocks' ),
			'show_if_any' => array( 'srcTermIn' => 'not_empty', 'src' => 'ref' ),
		),
		'sep'   => array(
			'type'        => 'text',
			'label'       => __( 'Result Separator', 'generateblocks' ),
			'help'        => $sep_help,
			'placeholder' => ', ',
			'show_if_any' => array( 'srcTermIn' => 'not_empty', 'src' => 'ref' ),
		),
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
	return array_merge(
		bws_get_datetime_range_leading_options(),
		bws_get_datetime_range_field_key_options(),
		array(
			'fallback' => array(
				'type'        => 'text',
				'label'       => __( 'Fallback Text', 'generateblocks' ),
				'help'        => __( 'Text to display when no valid dates are found.', 'generateblocks' ),
				'placeholder' => 'Date TBA',
			),
		)
	);
}

function bws_get_datetime_range_leading_options(): array {
	return array(
		'as'                => array(
			'type'    => 'select',
			'label'   => __( 'Show:', 'generateblocks' ),
			'options' => array(
				array( 'value' => '',     'label' => __( 'Date and Time', 'generateblocks' ) ),
				array( 'value' => 'date', 'label' => __( 'Date only', 'generateblocks' ) ),
				array( 'value' => 'time', 'label' => __( 'Time only', 'generateblocks' ) ),
			),
		),
		'rangeSep'        => array(
			'type'        => 'text',
			'label'       => __( 'Range Separator', 'generateblocks' ),
			'help'        => __( 'Text between start and end dates. Default: –', 'generateblocks' ),
			'placeholder' => '–',
		),
		'format'          => array(
			'type'        => 'bws-format-input',
			'label'       => __( 'Format', 'generateblocks' ),
			'help'        => __( 'PHP date format string. Leave blank to use ACF field format or WordPress defaults.', 'generateblocks' ),
			'placeholder' => 'F j, Y g:i A',
		),
		'timeSep'         => array(
			'type'        => 'text',
			'label'       => __( 'Date/Time Separator', 'generateblocks' ),
			'help'        => __( 'Text between date and time when using a separate time field. Default: ", ".', 'generateblocks' ),
			'placeholder' => ', ',
			'show_if'     => array( 'format' => 'empty', 'as' => 'not_in:date,time' ),
		),
		'showCurrentYear' => array(
			'type'  => 'checkbox',
			'label' => __( 'Show current year', 'generateblocks' ),
			'help'  => __( 'Include the year even when it matches the current year. Ranges spanning two different years always show both years regardless of this setting.', 'generateblocks' ),
		),
		'showMidnight'    => array(
			'type'  => 'checkbox',
			'label' => __( 'Show time at midnight', 'generateblocks' ),
			'help'  => __( 'Show the time component even when it is 12:00 AM.', 'generateblocks' ),
		),
	);
}

function bws_get_datetime_range_field_key_options(): array {
	return array(
		'startKey'     => array(
			'type'         => 'bws-field-combo',
			'label'        => __( 'Start Date/Time Field Key', 'generateblocks' ),
			'help'         => __( 'ACF field key for start date-time, date, or time picker.', 'generateblocks' ),
			'placeholder'  => 'start_date',
		),
		'startTimeKey' => array(
			'type'         => 'bws-field-combo',
			'label'        => __( 'Start Time Field Key (optional)', 'generateblocks' ),
			'help'         => __( 'ACF time picker to override or add time component for start.', 'generateblocks' ),
			'placeholder'  => 'start_time',
		),
		'endKey'       => array(
			'type'         => 'bws-field-combo',
			'label'        => __( 'End Date/Time Field Key', 'generateblocks' ),
			'help'         => __( 'ACF field key for end date-time. Time-only values inherit date from start.', 'generateblocks' ),
			'placeholder'  => 'end_date',
		),
		'endTimeKey'   => array(
			'type'         => 'bws-field-combo',
			'label'        => __( 'End Time Field Key (optional)', 'generateblocks' ),
			'help'         => __( 'ACF time picker to override or add time component for end.', 'generateblocks' ),
			'placeholder'  => 'end_time',
		),
	);
}

function bws_get_base_datetime_range_options(): array {
	return array_merge(
		bws_get_datetime_range_leading_options(),
		bws_base_source_option(),
		bws_base_traversal_options(),
		bws_get_datetime_range_field_key_options(),
		array(
			'fallback' => array(
				'type'        => 'text',
				'label'       => __( 'Fallback Text', 'generateblocks' ),
				'help'        => __( 'Text to display when no valid dates are found.', 'generateblocks' ),
				'placeholder' => 'Date TBA',
			),
		),
		bws_get_datetime_list_options( true ),
		function_exists( 'bws_get_link_options' ) ? bws_get_link_options() : array()
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

	$result = bws_parse_combined_date_time( $post_id, $date_field, '', 'datetime', null, $date_options, $instance );

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

	$start_result = bws_parse_combined_date_time( $post_id, $start_field, '', 'start', null, $date_options, $instance );

	$end_result = null;
	if ( ! empty( $end_field ) ) {
		$end_result = bws_parse_combined_date_time( $post_id, $end_field, '', 'end', $start_result['date'], $date_options, $instance );
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
 * INVARIANT: must not hard-bail on `! $post_id` when the block instance is in a GB
 * loop-row context (`generateblocks/loopItem` set). Mode 2b flat-repeater rows
 * (GB Pro TYPE_OPTION site-options repeaters, TYPE_POST_META post-meta repeaters)
 * legitimately have no row entity, but the field-read layer (`bws_read_field()`)
 * can still resolve subfield values from `$loop_item[$key]`. Bailing before that
 * path runs produces silent fallback output. (Bugfix v1.7.2, issue #22.)
 *
 * @since 1.0.0
 * @param int|string|false $post_id  Resolved post ID (or ACF object ID for term context).
 * @param array            $options  Tag options.
 * @param object           $instance Block instance.
 * @return string
 */
function bws_datetime_single_core( $post_id, $options, $instance ) {
	$is_loop_row = bws_get_loop_row_context( $instance )['in_loop'];

	if ( ! $post_id && ! $is_loop_row ) {
		return bws_handle_date_time_fallback( $options, $instance, 'single' );
	}

	$date_time_field = sanitize_text_field( $options['date_time_field'] ?? '' );
	$time_field      = sanitize_text_field( $options['time_field'] ?? '' );

	if ( empty( $date_time_field ) && empty( $time_field ) ) {
		return bws_handle_date_time_fallback( $options, $instance, 'single' );
	}

	$result = bws_parse_combined_date_time( $post_id, $date_time_field, $time_field, 'datetime', null, $options, $instance );

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
 * INVARIANT: must not hard-bail on `! $post_id` when the block instance is in a GB
 * loop-row context (see bws_datetime_single_core() for full rationale). Bugfix
 * v1.7.2, issue #22.
 *
 * @since 1.0.0
 * @param int|string|false $post_id  Resolved post ID (or ACF object ID for term context).
 * @param array            $options  Tag options.
 * @param object           $instance Block instance.
 * @return string
 */
function bws_datetime_range_core( $post_id, $options, $instance ) {
	$is_loop_row = bws_get_loop_row_context( $instance )['in_loop'];

	if ( ! $post_id && ! $is_loop_row ) {
		return bws_handle_date_time_fallback( $options, $instance, 'range' );
	}

	$start_field      = sanitize_text_field( $options['start_field'] ?? '' );
	$start_time_field = sanitize_text_field( $options['start_time_field'] ?? '' );
	$end_field        = sanitize_text_field( $options['end_field'] ?? '' );
	$end_time_field   = sanitize_text_field( $options['end_time_field'] ?? '' );

	if ( empty( $start_field ) && empty( $start_time_field ) ) {
		return bws_handle_date_time_fallback( $options, $instance, 'range' );
	}

	$start_result = bws_parse_combined_date_time( $post_id, $start_field, $start_time_field, 'start', null, $options, $instance );

	$end_result = null;
	if ( ! empty( $end_field ) || ! empty( $end_time_field ) ) {
		$end_result = bws_parse_combined_date_time( $post_id, $end_field, $end_time_field, 'end', $start_result['date'], $options, $instance );
	}

	if ( ! $start_result['date'] && ! $start_result['time_only'] && ! $end_result ) {
		return bws_handle_date_time_fallback( $options, $instance, 'range' );
	}

	$time_only = ! empty( $options['time_only'] );

	// Handle time-only range (`as:time`). Accept either `date` or `time_only` from
	// parse results — start-side time-only values have no date to inherit and live
	// under `time_only`, while end-side values that inherited a start date live
	// under `date`. Must run before the partial-parts diagnostic branch below,
	// otherwise `as:time` ranges built from two time-only fields fall through to
	// "Start time: …; End time: …" output.
	if ( $time_only ) {
		$start_dt = $start_result['date'] ?: $start_result['time_only'];
		$end_dt   = $end_result ? ( $end_result['date'] ?: $end_result['time_only'] ) : null;
		if ( $start_dt ) {
			if ( $end_dt ) {
				// Two-ended time range (#25): resolve the format via the same chain
				// the single-ended case uses (custom token → ACF time format → WP
				// default); consolidation applies only for 12-hour formats.
				$smart_time  = ! empty( $options['smart_time'] );
				$time_format = bws_resolve_time_only_format( $options, $start_result );
				$time_range  = bws_format_time_range( $start_dt, $end_dt, $smart_time, $time_format );
				return GenerateBlocks_Dynamic_Tag_Callbacks::output( $time_range, $options, $instance );
			}
			// Single-ended time: honor custom format (time tokens only), then the
			// ACF field's time format, then the WordPress time_format option.
			$time_format = bws_resolve_time_only_format( $options, $start_result );
			$formatted   = wp_date( $time_format, $start_dt->getTimestamp() );
			return GenerateBlocks_Dynamic_Tag_Callbacks::output( $formatted, $options, $instance );
		}
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

	if ( ! $start_result['date'] && ! $time_only ) {
		return bws_handle_date_time_fallback( $options, $instance, 'range' );
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
 * Normalize public datetime option keys to the canonical core keys — THE single
 * parse point for datetime options (FW-2).
 *
 * Public keys (key, timeKey, startKey/endKey, as, format, timeSep, rangeSep,
 * showCurrentYear, showMidnight, fallback) map to the canonical keys the core
 * functions, format builders, and formatters read (date_time_field, time_field,
 * start_field/end_field, date_only/time_only, format_type/custom_format,
 * date_time_separator, separator, omit_current_year, smart_time,
 * fallback_text).
 *
 * INVARIANT: this function is the ONLY place public datetime keys are parsed.
 * Both render paths (base callbacks, try_/modifier template closures) and the
 * editor preview call it — they can never disagree about what an option means.
 * A future comma-folded key value (FW-41 `key:date,time`) is an edit to this
 * function alone, not a re-sweep of the read sites.
 *
 * Canonical keys already present in $options are preserved (legacy
 * template-tag callers pass them directly; the isset guards keep them
 * authoritative over their public twins).
 *
 * @since 1.6.0 As bws_base_map_datetime_options()/…_range_options().
 * @since 1.15.0 Collapsed into the single normalizer (FW-2, #48).
 * @param array $options Raw tag options from GenerateBlocks.
 * @param bool  $range   Range-tag mapping (startKey/endKey family) when true.
 * @return array Options with canonical core keys populated.
 */
function bws_normalize_datetime_options( array $options, bool $range = false ): array {
	$mapped = $options;

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

	// timeSep → date_time_separator (used by bws_parse_combined_date_time()).
	if ( isset( $options['timeSep'] ) && ! isset( $options['date_time_separator'] ) ) {
		$mapped['date_time_separator'] = $options['timeSep'];
	}

	// showCurrentYear (true = show) → omit_current_year (true = omit) — inverted.
	// Absent showCurrentYear → omit_current_year true (default: omit year).
	$mapped['omit_current_year'] = empty( $options['showCurrentYear'] );

	// showMidnight (true = show midnight) → smart_time (true = hide midnight) — inverted.
	// Absent showMidnight → smart_time true (default: smart-hide midnight).
	$mapped['smart_time'] = empty( $options['showMidnight'] );

	// fallback → fallback_text.
	if ( isset( $options['fallback'] ) && ! isset( $options['fallback_text'] ) ) {
		$mapped['fallback_text'] = $options['fallback'];
	}

	if ( ! $range ) {
		// key → date_time_field, timeKey → time_field.
		if ( isset( $options['key'] ) && ! isset( $options['date_time_field'] ) ) {
			$mapped['date_time_field'] = $options['key'];
		}
		if ( isset( $options['timeKey'] ) && ! isset( $options['time_field'] ) ) {
			$mapped['time_field'] = $options['timeKey'];
		}
		return $mapped;
	}

	// Range: start/end field family (the single-tag field keys stay unmapped —
	// a range tag's `key` option, if present, belongs to the range core's own
	// start_field mapping below, never date_time_field).
	if ( isset( $options['startKey'] ) && ! isset( $options['start_field'] ) ) {
		$mapped['start_field'] = $options['startKey'];
	}
	if ( isset( $options['startTimeKey'] ) && ! isset( $options['start_time_field'] ) ) {
		$mapped['start_time_field'] = $options['startTimeKey'];
	}
	if ( isset( $options['endKey'] ) && ! isset( $options['end_field'] ) ) {
		$mapped['end_field'] = $options['endKey'];
	}
	if ( isset( $options['endTimeKey'] ) && ! isset( $options['end_time_field'] ) ) {
		$mapped['end_time_field'] = $options['endTimeKey'];
	}

	// rangeSep → separator (used by bws_format_date_range()).
	if ( isset( $options['rangeSep'] ) && ! isset( $options['separator'] ) ) {
		$mapped['separator'] = $options['rangeSep'];
	}

	return $mapped;
}

/**
 * Back-compat wrapper over bws_normalize_datetime_options() — single mapping.
 *
 * External API: bws-portal-system pins this name in its template map. Internal
 * call sites use the normalizer directly; do not add parsing here.
 *
 * @since 1.6.0
 * @param array $options Raw tag options from GenerateBlocks.
 * @return array Remapped options for existing core functions.
 */
function bws_base_map_datetime_options( array $options ): array {
	return bws_normalize_datetime_options( $options, false );
}

/**
 * Back-compat wrapper over bws_normalize_datetime_options() — range mapping.
 *
 * External API: bws-portal-system pins this name in its template map. Internal
 * call sites use the normalizer directly; do not add parsing here.
 *
 * @since 1.6.0
 * @param array $options Raw tag options from GenerateBlocks.
 * @return array Remapped options for existing range core functions.
 */
function bws_base_map_datetime_range_options( array $options ): array {
	return bws_normalize_datetime_options( $options, true );
}

/**
 * Collect a datetime list (#30): render each item, skip empties, slice to
 * `limit` (default 1, floored at 1), join with `sep` (default ", ").
 *
 * FW-3 shaping: $items carries ACF-object-id-shaped values — integer post ids
 * or WP_Term objects today; a term object-id string ("{taxonomy}_{term_id}")
 * slots into the same loop when FW-3's kind dispatch lands. The loop contract
 * never coerces an item; $render owns the item→string read.
 *
 * @since 1.15.0
 * @param array    $items   Read targets in document order.
 * @param callable $render  Item → rendered string ('' = skip).
 * @param array    $options Raw tag options (limit / sep).
 * @return array{value:string, count:int, first:mixed} `first` = first item
 *               that produced output (null when none) — the link-wrap target
 *               iff count is exactly 1.
 */
function bws_datetime_collect_list( array $items, callable $render, array $options ): array {
	$limit = max( 1, (int) ( $options['limit'] ?? 1 ) );
	$sep   = $options['sep'] ?? ', ';
	$out   = array();
	$first = null;
	foreach ( array_slice( $items, 0, $limit ) as $item ) {
		$result = $render( $item );
		if ( '' !== $result ) {
			$out[] = $result;
			if ( null === $first ) {
				$first = $item;
			}
		}
	}
	return array(
		'value' => implode( $sep, $out ),
		'count' => count( $out ),
		'first' => $first,
	);
}

/**
 * Callback for the `datetime_single` base tag.
 *
 * Resolves entity via `source`, applies srcTerm hop when set, then
 * delegates to bws_datetime_single_core() or bws_term_datetime_single_core().
 * Normalizes base tag option keys to the canonical core keys before dispatch.
 *
 * List mode (#30, V14 parity with base text/title): srcTermIn and src:ref
 * collect up to `limit` results joined with `sep`; empty items are skipped;
 * the fallback is suppressed per-item and fires once on all-empty output;
 * link-wrap applies only when exactly one result renders.
 *
 * @since 1.6.0
 * @since 1.15.0 List mode (limit/sep); src:ref fans out through the shared
 *               traversal engine instead of collapsing to the first target.
 */
function bws_base_datetime_single_callback( $options, $block, $instance ): string {
	$is_preview = ! empty( $instance->context['bwsEditorPreview'] );

	$tax      = sanitize_key( $options['srcTermIn'] ?? '' );
	$mapped   = bws_normalize_datetime_options( $options );
	$link_to  = $options['linkTo'] ?? 'none';
	$link_key = $options['linkKey'] ?? '';
	$new_tab  = ! empty( $options['newTab'] );

	$link_id   = 0;
	$link_type = 'post';

	// src:site — ACF options-page date field. Pass 'option' object-id to _core; the
	// DT-1 bws_read_field branch (allowlist-gated get_field($key,'option')) performs
	// the value read, and the format chain (bws_build_single_format) recovers the
	// field's return format. Link-wrap with sentinel id 1, entity_type 'site'.
	if ( 'site' === ( $options['src'] ?? '' ) ) {
		$value = bws_datetime_single_core( 'option', $mapped, $instance );
		if ( '' !== $value && function_exists( 'bws_wrap_with_link' ) ) {
			$value = bws_wrap_with_link( $value, $link_to, $link_key, $new_tab, 1, 'site' );
		}
		if ( '' !== $value ) {
			return $value;
		}
		return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'datetime_single' ) : '';
	}

	// Per-item reads in list mode suppress the fallback — it fires ONCE below
	// on all-empty output, never per item (else an empty term/post inside the
	// limit window would inject the fallback text into the list).
	$item_opts = $mapped;
	unset( $item_opts['fallback_text'] );

	$is_ref = 'ref' === ( $options['src'] ?? $options['source'] ?? '' );

	if ( '' !== $tax ) {
		$post_id = function_exists( 'bws_resolve_post_by_source' )
			? bws_resolve_post_by_source( $options, $instance )
			: get_the_ID();
		$terms = ( $post_id && function_exists( 'bws_get_srcterm_terms' ) )
			? bws_get_srcterm_terms( (int) $post_id, $tax )
			: [];
		$collected = bws_datetime_collect_list(
			$terms,
			static function ( $term ) use ( $item_opts, $instance ) {
				return bws_term_datetime_single_core( $term->term_id, $item_opts, $instance );
			},
			$options
		);
		$value = $collected['value'];
		if ( 1 === $collected['count'] && $collected['first'] instanceof WP_Term ) {
			$link_id   = $collected['first']->term_id;
			$link_type = 'term';
		}
	} elseif ( $is_ref ) {
		// src:ref list mode: read EVERY fanned-out ref target via the shared
		// traversal engine (plural resolver, not the collapse-to-first wrapper).
		$base = function_exists( 'bws_base_resolve_source_for_callback' )
			? bws_base_resolve_source_for_callback( $options, $instance )
			: array( 'kind' => 'post', 'id' => 0 );
		$post_ids = function_exists( 'bws_base_post_ids_from_source' )
			? bws_base_post_ids_from_source( $base, $options )
			: array();
		$collected = bws_datetime_collect_list(
			$post_ids,
			static function ( $oid ) use ( $item_opts, $instance ) {
				return bws_datetime_single_core( $oid, $item_opts, $instance );
			},
			$options
		);
		$value = $collected['value'];
		if ( 1 === $collected['count'] && is_numeric( $collected['first'] ) ) {
			$link_id   = (int) $collected['first'];
			$link_type = 'post';
		}
	} else {
		$post_id   = function_exists( 'bws_resolve_post_by_source' )
			? bws_resolve_post_by_source( $options, $instance )
			: get_the_ID();
		$value     = bws_datetime_single_core( $post_id, $mapped, $instance );
		$link_id   = (int) $post_id;
		$link_type = 'post';
	}

	// List-mode all-empty → the fallback fires once, unwrapped.
	if ( '' === $value && ( '' !== $tax || $is_ref ) ) {
		$value   = bws_handle_date_time_fallback( $mapped, $instance, 'single' );
		$link_id = 0;
	}

	if ( '' !== $value ) {
		if ( $link_id && function_exists( 'bws_wrap_with_link' ) ) {
			$value = bws_wrap_with_link( $value, $link_to, $link_key, $new_tab, $link_id, $link_type );
		}
		return $value;
	}

	return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'datetime_single' ) : '';
}

/**
 * Callback for the `datetime_range` base tag.
 *
 * Resolves entity via `source`, applies srcTerm hop when set, then
 * delegates to bws_datetime_range_core() or bws_term_datetime_range_core().
 * Normalizes base tag option keys to the canonical core keys before dispatch.
 *
 * List mode (#30): as bws_base_datetime_single_callback(). `sep` joins whole
 * formatted ranges; `rangeSep` stays the intra-range start↔end separator.
 *
 * @since 1.6.0
 * @since 1.15.0 List mode (limit/sep); src:ref fans out through the shared
 *               traversal engine instead of collapsing to the first target.
 */
function bws_base_datetime_range_callback( $options, $block, $instance ): string {
	$is_preview = ! empty( $instance->context['bwsEditorPreview'] );

	$tax      = sanitize_key( $options['srcTermIn'] ?? '' );
	$mapped   = bws_normalize_datetime_options( $options, true );
	$link_to  = $options['linkTo'] ?? 'none';
	$link_key = $options['linkKey'] ?? '';
	$new_tab  = ! empty( $options['newTab'] );

	$link_id   = 0;
	$link_type = 'post';

	// src:site — ACF options-page date range. 'option' object-id → DT-1 value read +
	// format chain (bws_build_range_format). Link-wrap sentinel id 1, type 'site'.
	if ( 'site' === ( $options['src'] ?? '' ) ) {
		$value = bws_datetime_range_core( 'option', $mapped, $instance );
		if ( '' !== $value && function_exists( 'bws_wrap_with_link' ) ) {
			$value = bws_wrap_with_link( $value, $link_to, $link_key, $new_tab, 1, 'site' );
		}
		if ( '' !== $value ) {
			return $value;
		}
		return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'datetime_range' ) : '';
	}

	// Per-item reads in list mode suppress the fallback (fires once below).
	$item_opts = $mapped;
	unset( $item_opts['fallback_text'] );

	$is_ref = 'ref' === ( $options['src'] ?? $options['source'] ?? '' );

	if ( '' !== $tax ) {
		$post_id = function_exists( 'bws_resolve_post_by_source' )
			? bws_resolve_post_by_source( $options, $instance )
			: get_the_ID();
		$terms = ( $post_id && function_exists( 'bws_get_srcterm_terms' ) )
			? bws_get_srcterm_terms( (int) $post_id, $tax )
			: [];
		$collected = bws_datetime_collect_list(
			$terms,
			static function ( $term ) use ( $item_opts, $instance ) {
				return bws_term_datetime_range_core( $term->term_id, $item_opts, $instance );
			},
			$options
		);
		$value = $collected['value'];
		if ( 1 === $collected['count'] && $collected['first'] instanceof WP_Term ) {
			$link_id   = $collected['first']->term_id;
			$link_type = 'term';
		}
	} elseif ( $is_ref ) {
		// src:ref list mode: read EVERY fanned-out ref target via the shared
		// traversal engine (plural resolver, not the collapse-to-first wrapper).
		$base = function_exists( 'bws_base_resolve_source_for_callback' )
			? bws_base_resolve_source_for_callback( $options, $instance )
			: array( 'kind' => 'post', 'id' => 0 );
		$post_ids = function_exists( 'bws_base_post_ids_from_source' )
			? bws_base_post_ids_from_source( $base, $options )
			: array();
		$collected = bws_datetime_collect_list(
			$post_ids,
			static function ( $oid ) use ( $item_opts, $instance ) {
				return bws_datetime_range_core( $oid, $item_opts, $instance );
			},
			$options
		);
		$value = $collected['value'];
		if ( 1 === $collected['count'] && is_numeric( $collected['first'] ) ) {
			$link_id   = (int) $collected['first'];
			$link_type = 'post';
		}
	} else {
		$post_id   = function_exists( 'bws_resolve_post_by_source' )
			? bws_resolve_post_by_source( $options, $instance )
			: get_the_ID();
		$value     = bws_datetime_range_core( $post_id, $mapped, $instance );
		$link_id   = (int) $post_id;
		$link_type = 'post';
	}

	// List-mode all-empty → the fallback fires once, unwrapped.
	if ( '' === $value && ( '' !== $tax || $is_ref ) ) {
		$value   = bws_handle_date_time_fallback( $mapped, $instance, 'range' );
		$link_id = 0;
	}

	if ( '' !== $value ) {
		if ( $link_id && function_exists( 'bws_wrap_with_link' ) ) {
			$value = bws_wrap_with_link( $value, $link_to, $link_key, $new_tab, $link_id, $link_type );
		}
		return $value;
	}

	return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'datetime_range' ) : '';
}
