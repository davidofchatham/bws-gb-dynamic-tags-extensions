<?php
/**
 * DateTime core functions and tag template registration.
 *
 * DateTime tags (post_custom_datetime_single, post_custom_datetime_range, and related/term variants)
 * are registered via the template system (TagTemplateRegistry::generate_all_tags()).
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// DateTime tags are registered via the template system (TagTemplateRegistry::generate_all_tags()).

/**
 * Register datetime dynamic tag templates.
 *
 * @since 1.2.0
 */
function bws_register_datetime_tag_templates() {
	\BWS\DynamicTags\TagTemplateRegistry::register_template( array(
		'key'                => 'custom_datetime_single',
		'title'              => 'Custom Date/Time',
		'gb_type'            => null,
		'supports'           => array( 'source' ),
		'options_fn'         => 'bws_get_datetime_single_options',
		'core_fn'            => 'bws_datetime_single_core',
		'context_types'      => array( 'post', 'term' ),
		'term_core_fn'       => 'bws_term_datetime_single_core',
		'supports_try'       => true,
		'default_enabled_map' => array(
			'related_post' => false,  // related_post_custom_datetime_single = opt-in
			'term'         => false,  // term_custom_datetime_single = opt-in
		),
	) );

	\BWS\DynamicTags\TagTemplateRegistry::register_template( array(
		'key'                => 'custom_datetime_range',
		'title'              => 'Custom Date/Time Range',
		'gb_type'            => null,
		'supports'           => array( 'source' ),
		'options_fn'         => 'bws_get_datetime_range_options',
		'core_fn'            => 'bws_datetime_range_core',
		'context_types'      => array( 'post', 'term' ),
		'term_core_fn'       => 'bws_term_datetime_range_core',
		'supports_try'       => true,
		'default_enabled_map' => array(
			'related_post' => false,  // related_post_custom_datetime_range = opt-in
			'term'         => false,  // term_custom_datetime_range = opt-in
		),
	) );
}

// ===============================================
// OPTION DEFINITIONS
// ===============================================

/**
 * DateTime single tag options (full option set with time support).
 *
 * @since 1.0.0
 * @return array
 */
function bws_get_datetime_single_options() {
	return array(
		'date_time_field' => array(
			'type'        => 'text',
			'label'       => __( 'Date/Date-Time Field', 'generateblocks' ),
			'help'        => __( 'ACF field key for a date-time, date, or time picker field.', 'generateblocks' ),
			'placeholder' => __( 'event_date', 'generateblocks' ),
		),
		'time_field' => array(
			'type'        => 'text',
			'label'       => __( 'Time Field (Optional)', 'generateblocks' ),
			'help'        => __( 'ACF time picker field to override or add time component.', 'generateblocks' ),
			'placeholder' => __( 'event_time', 'generateblocks' ),
		),
		'format_type' => array(
			'type'    => 'select',
			'label'   => __( 'Format Type', 'generateblocks' ),
			'default' => 'auto',
			'options' => array(
				array( 'value' => 'auto', 'label' => __( 'Auto (Use ACF Return Format)', 'generateblocks' ) ),
				array( 'value' => 'custom', 'label' => __( 'Custom Format', 'generateblocks' ) ),
			),
		),
		'custom_format' => array(
			'type'        => 'text',
			'label'       => __( 'Custom Format', 'generateblocks' ),
			'help'        => __( 'PHP date format string (e.g., "F j, Y g:i A").', 'generateblocks' ),
			'placeholder' => __( 'F j, Y g:i A', 'generateblocks' ),
		),
		'date_only' => array(
			'type'    => 'checkbox',
			'label'   => __( 'Show date only', 'generateblocks' ),
			'help'    => __( 'Hide time components even if present in fields.', 'generateblocks' ),
			'default' => false,
		),
		'time_only' => array(
			'type'    => 'checkbox',
			'label'   => __( 'Show time only', 'generateblocks' ),
			'help'    => __( 'Hide date components even if present in fields.', 'generateblocks' ),
			'default' => false,
		),
		'smart_time' => array(
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
		'fallback_text' => array(
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
		'start_field' => array(
			'type'        => 'text',
			'label'       => __( 'Start Date/Date-Time Field', 'generateblocks' ),
			'help'        => __( 'ACF field key for start date-time, date, or time picker.', 'generateblocks' ),
			'placeholder' => __( 'start_date_time', 'generateblocks' ),
		),
		'start_time_field' => array(
			'type'        => 'text',
			'label'       => __( 'Start Time Field (Optional)', 'generateblocks' ),
			'help'        => __( 'ACF time picker to override or add time component for start.', 'generateblocks' ),
			'placeholder' => __( 'start_time', 'generateblocks' ),
		),
		'end_field' => array(
			'type'        => 'text',
			'label'       => __( 'End Date/Date-Time Field', 'generateblocks' ),
			'help'        => __( 'ACF field key for end date-time. Time-only values inherit date from start.', 'generateblocks' ),
			'placeholder' => __( 'end_date_time', 'generateblocks' ),
		),
		'end_time_field' => array(
			'type'        => 'text',
			'label'       => __( 'End Time Field (Optional)', 'generateblocks' ),
			'help'        => __( 'ACF time picker to override or add time component for end.', 'generateblocks' ),
			'placeholder' => __( 'end_time', 'generateblocks' ),
		),
		'format_type' => array(
			'type'    => 'select',
			'label'   => __( 'Format Type', 'generateblocks' ),
			'default' => 'auto',
			'options' => array(
				array( 'value' => 'auto', 'label' => __( 'Auto (Use ACF Return Format)', 'generateblocks' ) ),
				array( 'value' => 'custom', 'label' => __( 'Custom Format', 'generateblocks' ) ),
			),
		),
		'custom_format' => array(
			'type'        => 'text',
			'label'       => __( 'Custom Format', 'generateblocks' ),
			'help'        => __( 'PHP date format string (e.g., "F j, Y g:i A").', 'generateblocks' ),
			'placeholder' => __( 'F j, Y g:i A', 'generateblocks' ),
		),
		'date_only' => array(
			'type'    => 'checkbox',
			'label'   => __( 'Show date only', 'generateblocks' ),
			'help'    => __( 'Hide time components even if present in fields.', 'generateblocks' ),
			'default' => false,
		),
		'time_only' => array(
			'type'    => 'checkbox',
			'label'   => __( 'Show time only', 'generateblocks' ),
			'help'    => __( 'Hide date components and show only time range.', 'generateblocks' ),
			'default' => false,
		),
		'smart_time' => array(
			'type'    => 'checkbox',
			'label'   => __( 'Smart Time Formatting', 'generateblocks' ),
			'help'    => __( 'Hide time if midnight, consolidate AM/PM in ranges.', 'generateblocks' ),
			'default' => true,
		),
		'omit_current_year' => array(
			'type'    => 'checkbox',
			'label'   => __( 'Omit Current Year', 'generateblocks' ),
			'help'    => __( 'Hide the year when it matches the current year.', 'generateblocks' ),
			'default' => true,
		),
		'separator' => array(
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
// CALLBACK FUNCTIONS
// ===============================================

/**
 * Core datetime single logic (delegates to existing callback from date-time-tags.php).
 *
 * Reuses the proven bws_get_single_date_time_callback logic but with source-resolved post ID.
 *
 * @since 1.0.0
 * @param int    $post_id  Resolved post ID.
 * @param array  $options  Tag options.
 * @param object $instance Block instance.
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
 * Core datetime range logic (delegates to existing callback from date-time-tags.php).
 *
 * @since 1.0.0
 * @param int    $post_id  Resolved post ID.
 * @param array  $options  Tag options.
 * @param object $instance Block instance.
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

// --- Term core functions ---

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

