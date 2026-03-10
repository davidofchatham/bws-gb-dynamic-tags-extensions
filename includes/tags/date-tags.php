<?php
/**
 * Date-only core functions and tag template registration.
 *
 * Date tags (post_custom_date_single, post_custom_date_range, and related/term variants)
 * are registered via the template system (TagTemplateRegistry::generate_all_tags()).
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Date tags are registered via the template system (TagTemplateRegistry::generate_all_tags()).

/**
 * Register date dynamic tag templates.
 *
 * @since 1.2.0
 */
function bws_register_date_tag_templates() {
	// Custom date fields work for terms via the 'category_5' ACF object ID format.
	// term_core_fn handles the format conversion before delegating to the post core function.
	\BWS\DynamicTags\TagTemplateRegistry::register_template( array(
		'key'                => 'custom_date_single',
		'title'              => 'Custom Date',
		'gb_type'            => null,
		'supports'           => array( 'source' ),
		'options_fn'         => 'bws_get_date_single_options',
		'core_fn'            => 'bws_date_single_core',
		'context_types'      => array( 'post', 'term' ),
		'term_core_fn'       => 'bws_term_date_single_core',
		'supports_try'       => true,
		'default_enabled_map' => array(
			'related_post' => false,  // related_post_custom_date_single = opt-in
			'term'         => false,  // term_custom_date_single = opt-in
		),
	) );

	\BWS\DynamicTags\TagTemplateRegistry::register_template( array(
		'key'                => 'custom_date_range',
		'title'              => 'Custom Date Range',
		'gb_type'            => null,
		'supports'           => array( 'source' ),
		'options_fn'         => 'bws_get_date_range_options',
		'core_fn'            => 'bws_date_range_core',
		'context_types'      => array( 'post', 'term' ),
		'term_core_fn'       => 'bws_term_date_range_core',
		'supports_try'       => true,
		'default_enabled_map' => array(
			'related_post' => false,  // related_post_custom_date_range = opt-in
			'term'         => false,  // term_custom_date_range = opt-in
		),
	) );
}

// ===============================================
// OPTION DEFINITIONS
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
			'help'        => __( 'PHP date format string (e.g., "F j, Y"). See PHP date() documentation.', 'generateblocks' ),
			'placeholder' => __( 'F j, Y', 'generateblocks' ),
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
		'start_field' => array(
			'type'        => 'text',
			'label'       => __( 'Start Date Field', 'generateblocks' ),
			'help'        => __( 'ACF date picker field key for start date.', 'generateblocks' ),
			'placeholder' => __( 'start_date', 'generateblocks' ),
		),
		'end_field' => array(
			'type'        => 'text',
			'label'       => __( 'End Date Field', 'generateblocks' ),
			'help'        => __( 'ACF date picker field key for end date (optional).', 'generateblocks' ),
			'placeholder' => __( 'end_date', 'generateblocks' ),
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
			'help'        => __( 'PHP date format string (e.g., "F j, Y"). See PHP date() documentation.', 'generateblocks' ),
			'placeholder' => __( 'F j, Y', 'generateblocks' ),
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
 * Core date-only single logic (shared by source-specific callbacks).
 *
 * @since 1.0.0
 * @param int    $post_id  Resolved post ID.
 * @param array  $options  Tag options.
 * @param object $instance Block instance.
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
 * @param int    $post_id  Resolved post ID.
 * @param array  $options  Tag options.
 * @param object $instance Block instance.
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

// --- Term core functions ---

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

