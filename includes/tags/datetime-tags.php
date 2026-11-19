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
// OPTION DEFINITIONS — BASE TAGS
// ===============================================

/**
 * Options for the `datetime_single` base tag.
 *
 * Uses simplified option keys (key, key2, as, format, …) rather than the
 * legacy keys used by template-generated datetime tags. Callbacks remap
 * these to the existing core function keys via bws_normalize_datetime_options().
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
		array( 'key' => bws_get_datetime_legacy_reads_option( 'single' ) ), // The read control, in the plain picker's place.
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
			'label'   => __( 'Return As', 'generateblocks' ),
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
			'help'  => __( 'Show the time component even when it marks the start or end of the day (12:00 AM, or 11:59 PM as a range end).', 'generateblocks' ),
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
	// Canonical CONTROL order (FW-52): source → format → link → fallback.
	// Source group = src → ref/srcTermIn → sep → field keys (key/timeKey); no tag-level
	// `limit` control since 1.17.0 (#62 — a chain states its limits on its steps).
	// The format block (as/format/timeSep/…) is control-LATE, serialize-EARLY (the
	// normalizer lifts it to the front of the string). link then fallback last.
	return array_merge(
		bws_build_src_chain_option(),
		bws_base_traversal_options(),
		bws_get_datetime_list_options( false ),
		bws_get_datetime_single_field_key_options(),
		array( 'key' => bws_get_datetime_legacy_reads_option( 'single' ) ), // Replaces the plain key picker, in its place.
		bws_get_datetime_single_leading_options(),
		function_exists( 'bws_get_link_options' ) ? bws_get_link_options() : array(),
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

/**
 * List-mode options shared by both datetime base tags (#30).
 *
 * `sep` only, since 1.17.0: the tag-level `limit` retired from every base tag whose
 * source is a chain (#62), because a limit is stated where the source is stated and a
 * chain states its source as steps. The VALUE is still read on stored wire — see
 * includes/tags/base-tags.php for the whole reasoning.
 *
 * Mirrors base text/title: list mode only applies to the final traversal step —
 * terms (srcTermIn set) or related posts (src:ref). Scalar sources return one
 * value, so the control stays hidden otherwise.
 *
 * @since 1.15.0
 * @since 1.17.0 `limit` removed; `sep` is the whole set.
 * @param bool $range Range tag: `sep` help gains the sep-vs-rangeSep distinction.
 * @return array
 */
function bws_get_datetime_list_options( bool $range = false ): array {
	$sep_help = $range
		? __( 'Text to place between results. Default: ", ". This joins whole ranges; the Range Separator stays between each start and end.', 'generateblocks' )
		: __( 'Text to place between results. Default: ", ".', 'generateblocks' );
	return array(
		'sep' => array(
			'type'        => 'text',
			'label'       => __( 'Result Separator', 'generateblocks' ),
			'help'        => $sep_help,
			'placeholder' => ', ',
			'show_if_any' => array( 'srcTermIn' => 'not_empty', 'src' => array( 'ref', 'chain_fans' ) ),
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
		array( 'key' => bws_get_datetime_legacy_reads_option( 'range' ) ), // The read control, ahead of the old four.
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
			'label'   => __( 'Return As', 'generateblocks' ),
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
			'help'  => __( 'Show the time component even when it marks the start or end of the day (12:00 AM, or 11:59 PM as a range end).', 'generateblocks' ),
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
	// Canonical CONTROL order (FW-52): source → format → link → fallback.
	// Source group = src → ref/srcTermIn → limit/sep → field keys (start/end). The
	// format block (as/rangeSep/format/…) is control-LATE, serialize-EARLY (normalizer
	// lifts it front). link then fallback last.
	return array_merge(
		bws_build_src_chain_option(),
		bws_base_traversal_options(),
		bws_get_datetime_list_options( true ),
		array( 'key' => bws_get_datetime_legacy_reads_option( 'range' ) ),
		bws_get_datetime_range_field_key_options(),
		bws_get_datetime_range_leading_options(),
		function_exists( 'bws_get_link_options' ) ? bws_get_link_options() : array(),
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

/**
 * The merged `datetime` tag's `use` leaf: the stripped-default select every read axis has
 * (BWS_USE_STRIPPED_DEFAULTS), its first value `key`.
 *
 * The wire value is the full ordered read list once a verb is mixed in (`key;now`). The
 * editor never shows this select: the read control (`bws-datetime-reads`, on `key`) writes
 * `use` itself, one entry per read, and the registration stays for the wire and the GB
 * option allowlist. use-read-control.js leaves a list alone.
 *
 * @since Unreleased
 * @return array The `use` option definition.
 */
function bws_get_datetime_use_option(): array {
	return array(
		'type'           => 'select',
		'label'          => __( 'Date/Time Read', 'generateblocks' ),
		'options'        => array_map(
			function ( $row ) {
				return array(
					'value' => $row['value'],
					'label' => $row['label'],
				);
			},
			bws_get_datetime_read_choices()
		),
		'_strip_default' => true,
		'readTag'        => 'datetime',
	);
}

/**
 * What one read can be: a field (`key`) or a verb, as the read control's per-read select
 * offers them, in order (FW-81).
 *
 * `kinds` lists the source kinds a row is offered for; no `kinds` is offered for every source.
 * `published` and `modified` read a post's own date, so only a post source offers them
 * (bws_datetime_verb_result() is where any other source takes the fallback). The `use` option
 * above lists the same values, so the two cannot name different verbs.
 *
 * @since Unreleased
 * @return array<int, array{value:string, label:string, kinds?:string[]}>
 */
function bws_get_datetime_read_choices(): array {
	return array(
		array( 'value' => 'key',       'label' => __( 'Date/Time Field', 'generateblocks' ) ),
		array( 'value' => 'now',       'label' => __( 'Current Date/Time', 'generateblocks' ) ),
		array( 'value' => 'published', 'label' => __( 'Post Published Date', 'generateblocks' ), 'kinds' => array( 'post' ) ),
		array( 'value' => 'modified',  'label' => __( 'Post Modified Date', 'generateblocks' ), 'kinds' => array( 'post' ) ),
	);
}

/**
 * The most reads a datetime tag shows a control for: a start and an end. A third `key` entry
 * is ignored by the normalizer, so the control stops offering Add at this count.
 *
 * @since Unreleased
 */
const BWS_DATETIME_MAX_READS = 2;

/**
 * The merged `datetime` tag's `key` option: the read control's definition (FW-81).
 *
 * `bws-datetime-reads` (assets/js/datetime-read-control.js) owns the whole `key` value, one
 * `;` entry per read and one `+` pair inside an entry, and the `use` value with it (a verb
 * in any read; bws_datetime_reads() owns that grammar). Every heading, label and count the
 * control shows arrives here and is never typed in the control. `entryLabels` is indexed by
 * read, so it must cover BWS_DATETIME_MAX_READS (control-order-test.php pins it). The pickers it renders are the field combo, whose Type
 * filter opens on the date and time types (`typeDefault`) and stays reachable to All for a
 * date stored as Text or plain meta.
 *
 * @since Unreleased
 * @return array The `key` option definition.
 */
function bws_get_datetime_reads_option(): array {
	return array(
		'type'           => 'bws-datetime-reads',
		'label'          => __( 'Date/Time Field Key', 'generateblocks' ),
		'help'           => __( 'ACF field key for a date, date-time, or time picker field.', 'generateblocks' ),
		'placeholder'    => 'event_date',
		// Each read's own select (a field or a verb) and the rows it offers.
		'readLabel'      => __( 'Date/Time Read', 'generateblocks' ),
		'reads'          => bws_get_datetime_read_choices(),
		'timeLabel'      => __( 'Time Field Key (optional)', 'generateblocks' ),
		'timeHelp'       => __( 'ACF time picker field to override or add time component.', 'generateblocks' ),
		'timePlaceholder' => 'event_time',
		// One heading per read, by position: a second read is a range's end.
		'entryLabels'    => array(
			__( 'Start Date/Time', 'generateblocks' ),
			__( 'End Date/Time', 'generateblocks' ),
		),
		'addLabel'       => __( 'Add End Date/Time', 'generateblocks' ),
		'timeAddLabel'   => __( 'Add Time Field', 'generateblocks' ),
		'timeRemoveLabel' => __( 'Remove Time Field', 'generateblocks' ),
		'removeLabel'    => __( 'Remove', 'generateblocks' ),
		'max'            => BWS_DATETIME_MAX_READS,
		'typeDefault'    => BWS_FIELD_TYPE_GROUP_DATETIME,
	);
}

/**
 * The `key` option of an OLD datetime tag (`datetime_single`, `datetime_range`, and their `try_` forms
 * through the two template option builders): the read control, so
 * the modal shows the author's reads once the mount rewrite has turned the old field options into
 * `key` (assets/js/datetime-read-control.js; the rule is bws_datetime_legacy_reads_rewrite()).
 *
 * `legacy` names the family and is what makes the control run that rewrite. The old tags keep their
 * family whatever `key` says and read no `use`, so the read count is the family's (a single reads
 * one) and the only row is the field. The old options stay registered beside it: GenerateBlocks
 * hands them to the modal from the stored tag, and an end-only range (never rewritten) still shows them.
 *
 * @since Unreleased
 * @param string $family `single` or `range`.
 * @return array The `key` option definition.
 */
function bws_get_datetime_legacy_reads_option( string $family ): array {
	$option           = bws_get_datetime_reads_option();
	$option['legacy'] = $family;
	$option['max']    = 'range' === $family ? BWS_DATETIME_MAX_READS : 1;
	$option['reads']  = array_slice( $option['reads'], 0, 1 );
	return $option;
}

/**
 * Options for the merged `datetime` base tag (FW-81).
 *
 * One `key` option carries every field read and `use` the verbs (bws_datetime_reads() owns
 * the grammar), and no option states a mode: bws_normalize_datetime_options() derives it.
 * The old six field options are NOT registered here: only the old tags offer them, and the
 * normalizer still reads them for those tags.
 *
 * @since Unreleased
 * @return array
 */
function bws_get_base_datetime_options(): array {
	return array_merge(
		bws_build_src_chain_option(),
		bws_base_traversal_options(),
		bws_get_datetime_list_options( true ),
		array(
			'use' => bws_get_datetime_use_option(),
			'key' => bws_get_datetime_reads_option(),
		),
		bws_get_datetime_range_leading_options(),
		function_exists( 'bws_get_link_options' ) ? bws_get_link_options() : array(),
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

/**
 * Template-specific options for the merged `datetime` modifier template (`try_datetime`):
 * the format cluster, then the tag-level `use`/`key` reads and `fallback`, each the base
 * tag's own definition. The template has no per-slot read axis, so `use`/`key` stay
 * tag-level and every attempt reads them as the base tag does.
 *
 * @since Unreleased
 * @return array
 */
function bws_get_datetime_template_options(): array {
	return array_merge(
		bws_get_datetime_range_leading_options(),
		array_intersect_key( bws_get_base_datetime_options(), array_flip( array( 'use', 'key', 'fallback' ) ) )
	);
}

// ===============================================
// CORE FUNCTIONS — DATETIME TEMPLATES
// ===============================================

/**
 * Core datetime single logic.
 *
 * INVARIANT: must not hard-bail on `! $post_id` when the query-loop item is one the
 * field read can still be served from — bws_loop_item_is_post_or_row() owns that
 * question, and an item merely being PRESENT is not it. Repeater rows
 * (GB Pro TYPE_OPTION site-options repeaters, TYPE_POST_META post-meta repeaters)
 * legitimately have no entity of their own, but the field-read layer (`bws_read_field()`)
 * can still resolve subfield values from `$loop_item[$key]`. Bailing before that
 * path runs produces silent fallback output. (Bugfix v1.7.2, issue #22.)
 *
 * A read with no date, or `as:time`, is a TIME display and goes to
 * bws_format_time_display(), the same function the range core calls. A verb read
 * (`verb`: `now`, `published`, `modified`) takes the place of the field parse, through
 * bws_datetime_verb_result().
 *
 * @since 1.0.0
 * @since 1.15.0 First arg is a resolved-source payload (FW-3a).
 * @since Unreleased The first arg is always the payload, and the time half renders through
 *               bws_format_time_display(). A verb read (`verb`) replaces the field parse.
 * @param array  $source   Resolved source (the factory's payload shape).
 * @param array  $options  Tag options.
 * @param object $instance Block instance.
 * @return string
 */
function bws_datetime_single_core( array $source, $options, $instance ) {
	// The INVARIANT above is about repeater rows, and this is the predicate that says
	// so: a post or a repeater row, never bare `in_loop`. A TERM or USER loop item
	// reaching the read below leaves it with no entity and no row, and it answers with
	// the surrounding archive's term meta — measured 2026-08-26, and the reason this
	// gate narrowed (bws_loop_item_is_post_or_row()).
	$read_may_serve = bws_loop_item_is_post_or_row( $instance );
	// `meta_row` counts as having something to read for the same reason `site` does:
	// the source carries its own store (the row array) instead of an entity id (FW-74).
	// Only the `rows` ARM below hands this core such a source — a query loop's repeater
	// row reaches here as a post source with a falsy id, and is what $read_may_serve is for.
	$has_entity     = 'site' === $source['kind'] || 'meta_row' === $source['kind'] || ! empty( $source['id'] );

	// A verb read (`now`, `published`, `modified`) reads no field, so the entity gate above
	// is about the field read only.
	$verb = $options['verb'] ?? '';
	if ( '' === $verb && ! $has_entity && ! $read_may_serve ) {
		return bws_handle_date_time_fallback( $options, $instance );
	}

	$date_time_field = sanitize_text_field( $options['date_time_field'] ?? '' );
	$time_field      = sanitize_text_field( $options['time_field'] ?? '' );

	if ( '' === $verb && empty( $date_time_field ) && empty( $time_field ) ) {
		return bws_handle_date_time_fallback( $options, $instance );
	}

	$result = '' !== $verb
		? bws_datetime_verb_result( $verb, $source )
		: bws_parse_combined_date_time( $source, $date_time_field, $time_field, 'datetime', null, $options, $instance );

	if ( ! $result['date'] && ! $result['time_only'] ) {
		return bws_handle_date_time_fallback( $options, $instance );
	}

	// A time display: `as:time`, or a field with no date (a time-only field). Empty when
	// there is no time to show, which takes the fallback like any other empty read.
	if ( ! empty( $options['time_only'] ) || ! $result['date'] ) {
		return bws_gb_tag_output( bws_format_time_display( $result, null, $options ), $options, $instance );
	}

	$date_only    = ! empty( $options['date_only'] );
	$include_time = ! $date_only && $result['has_time'];

	$format = bws_build_single_format( $options, $result, true, $include_time );

	if ( ! $result['date'] || ! is_a( $result['date'], 'DateTime' ) ) {
		return bws_handle_date_time_fallback( $options, $instance );
	}

	$formatted = bws_format_single_date_time( $result['date'], $format, $options );

	return bws_gb_tag_output( $formatted, $options, $instance );
}

/**
 * Core datetime range logic.
 *
 * INVARIANT: must not hard-bail on `! $post_id` when the query-loop item is one the
 * field read can still be served from (see bws_datetime_single_core() for full
 * rationale). Bugfix v1.7.2, issue #22.
 *
 * `as:time`, or a start with no date, is a TIME display and goes to
 * bws_format_time_display(), the same function the single core calls. A range with no
 * start reads nothing, whatever its end holds. A side may be a verb read (`start_verb` /
 * `end_verb`) in place of a field parse.
 *
 * @since 1.0.0
 * @since 1.15.0 First arg is a resolved-source payload (FW-3a).
 * @since Unreleased The first arg is always the payload, and the time half renders through
 *               bws_format_time_display(). Either side may be a verb read.
 * @param array  $source   Resolved source (the factory's payload shape).
 * @param array  $options  Tag options.
 * @param object $instance Block instance.
 * @return string
 */
function bws_datetime_range_core( array $source, $options, $instance ) {
	// Same predicate as the single core above, same reason — and the same `meta_row` term.
	$read_may_serve = bws_loop_item_is_post_or_row( $instance );
	$has_entity     = 'site' === $source['kind'] || 'meta_row' === $source['kind'] || ! empty( $source['id'] );

	$start_verb       = $options['start_verb'] ?? '';
	$end_verb         = $options['end_verb'] ?? '';
	$start_field      = sanitize_text_field( $options['start_field'] ?? '' );
	$start_time_field = sanitize_text_field( $options['start_time_field'] ?? '' );
	$end_field        = sanitize_text_field( $options['end_field'] ?? '' );
	$end_time_field   = sanitize_text_field( $options['end_time_field'] ?? '' );

	// One side's read: a verb's moment, a field read (only where the entity gate lets it
	// read), or null for a side that names nothing.
	$may_read_fields = $has_entity || $read_may_serve;
	$read_side       = static function ( $verb, $field, $time, $context, $inherit ) use ( $source, $options, $instance, $may_read_fields ) {
		if ( '' !== $verb ) {
			return bws_datetime_verb_result( $verb, $source );
		}
		if ( ! $may_read_fields || ( '' === $field && '' === $time ) ) {
			return null;
		}
		return bws_parse_combined_date_time( $source, $field, $time, $context, $inherit, $options, $instance );
	};

	$start_result = $read_side( $start_verb, $start_field, $start_time_field, 'start', null );
	if ( ! $start_result ) {
		return bws_handle_date_time_fallback( $options, $instance );
	}

	$end_result = $read_side( $end_verb, $end_field, $end_time_field, 'end', $start_result['date'] );

	// A verb has no field to say whether its time shows or in what style, so it takes the
	// other side's: `now` against a date-only field stays a date, and not the parser's noon.
	if ( $end_result && '' !== $start_verb && '' === $end_verb ) {
		$start_result['has_time'] = $end_result['has_time'];
		$start_result['formats']  = $end_result['formats'];
	} elseif ( $end_result && '' === $start_verb && '' !== $end_verb ) {
		$end_result['has_time'] = $start_result['has_time'];
	}

	// No start reads nothing, whatever the end holds.
	if ( ! $start_result['date'] && ! $start_result['time_only'] ) {
		return bws_handle_date_time_fallback( $options, $instance );
	}

	// A time display: `as:time`, or a start with no date (a time-only field). Empty when
	// there is no time to show, which takes the fallback like any other empty read.
	if ( ! empty( $options['time_only'] ) || ! $start_result['date'] ) {
		return bws_gb_tag_output( bws_format_time_display( $start_result, $end_result, $options ), $options, $instance );
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

	return bws_gb_tag_output( $formatted, $options, $instance );
}

/**
 * The resolved source for a term the datetime cores read, or the entity-less post source
 * when the term does not validate (the cores then take the fallback, or read a query-loop
 * item they can be served from). The term read is kind-dispatched inside
 * bws_parse_combined_date_time(), with no object-id string smuggle (FW-3a).
 *
 * @since Unreleased
 * @param int|false $term_id Resolved term ID.
 * @return array The factory's resolved-source payload.
 */
function bws_datetime_term_source( $term_id ): array {
	$term = $term_id ? bws_get_validated_term( $term_id ) : null;
	if ( ! $term ) {
		return array( 'kind' => 'post', 'id' => false );
	}
	return array( 'kind' => 'term', 'id' => $term->term_id, 'taxonomy' => $term->taxonomy );
}

/**
 * The resolved source for a post id the datetime cores read (a non-numeric id is the
 * entity-less read, which a query-loop item may still serve).
 *
 * @since Unreleased
 * @param mixed $post_id A post id, or anything else for no entity.
 * @return array The factory's resolved-source payload.
 */
function bws_datetime_post_source( $post_id ): array {
	return array( 'kind' => 'post', 'id' => is_numeric( $post_id ) ? (int) $post_id : false );
}

// ===============================================
// BASE TAG HELPERS + CALLBACKS
// ===============================================

/**
 * The reads a datetime tag spells, in order — the grammar of the merged `datetime` tag's
 * two read options, `key` and `use` (FW-81).
 *
 * A `;` separates `key` entries, and an entry that is empty is still a read (an empty one,
 * which takes the fallback). A `+` joins at most two operands inside one entry: the primary
 * field, which may be a date, a datetime or a time, then an optional supplementary time
 * field. A third operand is ignored.
 *
 * `use` is read through bws_use_effective(). With no verb in it, the reads are the `key`
 * entries, one each. With a verb (`now`, `published`, `modified`) it is the full ORDERED list:
 * each `key` in it takes the next `key` entry (none left is an empty read), a verb takes
 * none, a surplus `key` entry is ignored, and a name that is neither is dropped. Position
 * is what tells "from a field until now" from "from now until a field".
 *
 * This is the only place `;` and `+` are read as datetime grammar. The folded slot grammar
 * in slot-fold.php is untouched: `+` stays reserved there (ADR 0006), because a flat
 * option value is parsed by its own tag and not by the slot splitter.
 *
 * `$faults` is how the editor preview learns what that tolerance hid: the same parse reports
 * each inconsistency it forgave, so the preview cannot disagree with the render about which
 * reads exist. One record per fault, in this order: `extra` (an operand past the second was
 * ignored), `bare` (`reads` lists the 1-based reads that are a `key` with no field),
 * `missing` or `surplus` (`reads` key reads in `use` against `keys` entries in `key`).
 * Nothing about the faults changes what is returned.
 *
 * @since Unreleased
 * @param array      $options Raw tag options.
 * @param bool       $verbs   Whether `use` is read: the merged tag only. The old tags have no `use`.
 * @param array|null $faults  Out: the inconsistencies forgiven, as above.
 * @return array<int, array{verb:string, field:string, time:string}> One record per read;
 *                         `verb` is '' for a field read. Empty when there is nothing to read.
 */
function bws_datetime_reads( array $options, bool $verbs = true, ?array &$faults = null ): array {
	$faults  = array();
	$key     = isset( $options['key'] ) && is_scalar( $options['key'] ) ? trim( (string) $options['key'] ) : '';
	$entries = array();
	$extra   = false;
	if ( '' !== $key ) {
		foreach ( explode( ';', $key ) as $entry ) {
			$operands  = explode( '+', $entry );
			$extra     = $extra || count( $operands ) > 2;
			$entries[] = array(
				'verb'  => '',
				'field' => trim( $operands[0] ),
				'time'  => trim( $operands[1] ?? '' ),
			);
		}
	}
	if ( $extra ) {
		$faults[] = array( 'kind' => 'extra' );
	}

	$use  = $verbs ? bws_use_effective( 'datetime', $options ) : bws_use_stripped_default( 'datetime' );
	$bare = array();
	$keys = count( $entries );

	if ( bws_use_stripped_default( 'datetime' ) === $use ) {
		$reads = $entries;
		foreach ( $reads as $i => $read ) {
			if ( '' === $read['field'] ) {
				$bare[] = $i + 1;
			}
		}
	} else {
		$reads     = array();
		$key_reads = 0;
		foreach ( explode( ';', $use ) as $name ) {
			$name = trim( $name );
			if ( 'key' === $name ) {
				++$key_reads;
				$read = array_shift( $entries );
				if ( null === $read ) {
					$read = array( 'verb' => '', 'field' => '', 'time' => '' );
					// No entry at all is a bare read; some entries but too few is a count mismatch.
					if ( 0 === $keys ) {
						$bare[] = count( $reads ) + 1;
					}
				} elseif ( '' === $read['field'] ) {
					$bare[] = count( $reads ) + 1;
				}
				$reads[] = $read;
			} elseif ( in_array( $name, array( 'now', 'published', 'modified' ), true ) ) {
				$reads[] = array( 'verb' => $name, 'field' => '', 'time' => '' );
			}
		}
		if ( $keys && $keys !== $key_reads ) {
			$mismatch = array( 'kind' => $keys < $key_reads ? 'missing' : 'surplus', 'reads' => $key_reads, 'keys' => $keys );
		}
	}

	if ( $bare ) {
		$faults[] = array( 'kind' => 'bare', 'reads' => $bare );
	}
	if ( isset( $mismatch ) ) {
		$faults[] = $mismatch;
	}
	return $reads;
}

/**
 * The datetime tag names and the family each stands for, the `$range` argument
 * bws_normalize_datetime_options() takes: null for the merged tag (its reads decide the mode),
 * false for `datetime_single`, true for `datetime_range`. THE one map between a family and its
 * tag name; read it through bws_datetime_family() and bws_datetime_family_tag().
 *
 * @since Unreleased
 */
const BWS_DATETIME_FAMILY_TAGS = array(
	'datetime'        => null,
	'datetime_single' => false,
	'datetime_range'  => true,
);

/**
 * The family a datetime tag name stands for. A `try_` prefix names the same family.
 *
 * @since Unreleased
 * @param string $tag A datetime tag name (`datetime`, `try_datetime_range`, ...).
 * @return bool|null The `$range` argument for bws_normalize_datetime_options().
 */
function bws_datetime_family( string $tag ): ?bool {
	return BWS_DATETIME_FAMILY_TAGS[ str_starts_with( $tag, 'try_' ) ? substr( $tag, 4 ) : $tag ] ?? null;
}

/**
 * The base tag name for a datetime family: `datetime`, `datetime_single` or `datetime_range`.
 *
 * @since Unreleased
 * @param bool|null $range The family, as bws_datetime_family() returns it.
 * @return string
 */
function bws_datetime_family_tag( ?bool $range ): string {
	return (string) array_search( $range, BWS_DATETIME_FAMILY_TAGS, true );
}

/**
 * Normalize public datetime option keys to the canonical core keys — THE single
 * parse point for datetime options (FW-2).
 *
 * Public keys (key, timeKey, startKey/endKey, as, format, timeSep, rangeSep,
 * showCurrentYear, showMidnight, fallback) map to the canonical keys the core
 * functions, format builders, and formatters read (date_time_field, time_field,
 * start_field/end_field, date_only/time_only, format_type/custom_format,
 * date_time_separator, separator, omit_current_year, hide_midnight). `fallback`
 * is already canonical and passes through untouched.
 *
 * INVARIANT: this function is the ONLY place public datetime keys are parsed.
 * Both render paths (base callbacks, try_ template closures) and the
 * editor preview call it — they can never disagree about what an option means.
 * The reads themselves are spelled by bws_datetime_reads(), which only this function
 * calls.
 *
 * MODE (FW-81): `$range` true/false is a fixed family — the two old tags pass it, and
 * the old `startKey` family and `key`/`timeKey` keep mapping exactly as they did, with
 * a `key` read filling only what those leave unset. `null` is the merged tag: the mode
 * is range when the old range keys are present or the options spell two or more reads
 * (`key` entries and verbs, bws_datetime_reads()), and the result says which in
 * `is_range`. Nothing else states a mode. A verb read maps to `verb` on a single, and to
 * `start_verb` / `end_verb` on a range; only the merged tag reads `use`.
 *
 * Canonical keys already present in $options are preserved (legacy
 * template-tag callers pass them directly; the isset guards keep them
 * authoritative over their public twins).
 *
 * @since 1.6.0 As bws_base_map_datetime_options()/…_range_options().
 * @since 1.15.0 Collapsed into the single normalizer (FW-2, #48); the two
 *               wrappers kept their names as external API.
 * @since 1.21.0 Those wrappers deleted — their only known caller, an
 *               integrating plugin's template map, dropped the pin.
 * @since Unreleased `$range` accepts null (the merged tag derives its mode from the
 *               reads); the `key` option spells one read per `;` entry, and `use` adds the
 *               verbs; `is_range` out.
 * @param array     $options Raw tag options from GenerateBlocks.
 * @param bool|null $range   Range-tag mapping (startKey/endKey family) when true; null
 *                           derives the mode from the options.
 * @return array Options with canonical core keys populated, plus `is_range`.
 */
function bws_normalize_datetime_options( array $options, ?bool $range = false ): array {
	$mapped = $options;

	$reads        = bws_datetime_reads( $options, null === $range );
	$legacy_range = isset( $options['startKey'] ) || isset( $options['startTimeKey'] )
		|| isset( $options['endKey'] ) || isset( $options['endTimeKey'] );
	if ( null === $range ) {
		$range = $legacy_range || count( $reads ) >= 2;
	}
	$mapped['is_range'] = $range;

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
	$mapped['omit_current_year'] = ! bws_flag_is_on( $options, 'showCurrentYear' );

	// showMidnight (true = show midnight) → hide_midnight — inverted.
	// Absent showMidnight → hide_midnight true (the default).
	$mapped['hide_midnight'] = ! bws_flag_is_on( $options, 'showMidnight' );

	if ( ! $range ) {
		// timeKey → time_field (the old tag's own spelling), then the first `key` read
		// → date_time_field (+ time_field when it names a second operand).
		if ( isset( $options['timeKey'] ) && ! isset( $options['time_field'] ) ) {
			$mapped['time_field'] = $options['timeKey'];
		}
		if ( isset( $reads[0] ) && '' !== $reads[0]['verb'] ) {
			$mapped['verb'] = $reads[0]['verb'];
		} elseif ( isset( $reads[0] ) && ! isset( $options['date_time_field'] ) ) {
			$mapped['date_time_field'] = $reads[0]['field'];
		}
		if ( isset( $reads[0] ) && '' !== $reads[0]['time'] && ! isset( $mapped['time_field'] ) ) {
			$mapped['time_field'] = $reads[0]['time'];
		}
		return $mapped;
	}

	// Range: start/end field family. The range's own four keys win, and a `key` beside
	// any of them stays inert; `key` reads fill the start and end only when none of the
	// four is present (the merged tag).
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
	if ( ! $legacy_range ) {
		foreach ( array( array( 'start_field', 'start_time_field', 'start_verb' ), array( 'end_field', 'end_time_field', 'end_verb' ) ) as $i => $names ) {
			if ( ! isset( $reads[ $i ] ) ) {
				continue;
			}
			if ( '' !== $reads[ $i ]['verb'] ) {
				$mapped[ $names[2] ] = $reads[ $i ]['verb'];
				continue;
			}
			if ( ! isset( $options[ $names[0] ] ) ) {
				$mapped[ $names[0] ] = $reads[ $i ]['field'];
			}
			if ( '' !== $reads[ $i ]['time'] && ! isset( $options[ $names[1] ] ) ) {
				$mapped[ $names[1] ] = $reads[ $i ]['time'];
			}
		}
	}

	// rangeSep → separator (used by bws_format_date_range()).
	if ( isset( $options['rangeSep'] ) && ! isset( $options['separator'] ) ) {
		$mapped['separator'] = $options['rangeSep'];
	}

	return $mapped;
}

/**
 * Whether an old range tag's wire is an END-ONLY range: both start fields empty, an end field
 * set. Such a tag prints its fallback today (bws_datetime_range_core() reads nothing without a
 * start), and `key` would give it a read, so no migration touches it.
 *
 * @since Unreleased
 * @param array $options Raw options of a `datetime_range` tag.
 * @return bool
 */
function bws_datetime_legacy_range_is_end_only( array $options ): bool {
	$text = static function ( string $name ) use ( $options ): string {
		return isset( $options[ $name ] ) && is_scalar( $options[ $name ] ) ? trim( (string) $options[ $name ] ) : '';
	};
	return '' === $text( 'startKey' ) && '' === $text( 'startTimeKey' )
		&& ( '' !== $text( 'endKey' ) || '' !== $text( 'endTimeKey' ) );
}

/**
 * Rewrite an old datetime tag's field options to the one `key` the merged tag reads (FW-81).
 *
 * THE RULE for both migration paths: the editor's mount step (assets/js/datetime-read-control.js,
 * `bwsDatetimeLegacy.rewrite()`, its twin) and the Migration Tool. Pure: options in, rewritten
 * options out, `null` when the tag is left alone. `$range` names the old tag's family, because
 * the old tags have a fixed one.
 *
 * - single: `key` and `timeKey` become one entry, `key+timeKey`, or just the key when the time
 *   field is empty. Only a tag that states `timeKey` has anything to rewrite.
 * - range: the four start/end options become two `;` entries, each built the same way, and a
 *   trailing empty end entry is stripped.
 * - an end-only range is NOT rewritten (bws_datetime_legacy_range_is_end_only()): `key` would
 *   give a tag that prints its fallback a read, and a migration must not change output.
 * - an operand holding `;` or `+` is not a clean entry, so that tag is left alone too.
 *
 * Equivalence (the same fields reach the cores) is pinned in datetime-migration-test.php D8,
 * from the shared corpus the JS twin also runs.
 *
 * @since Unreleased
 * @param array $options Raw tag options.
 * @param bool  $range   True for `datetime_range`, false for `datetime_single`.
 * @return array|null Rewritten options in canonical order, or null when nothing changes.
 */
function bws_datetime_legacy_reads_rewrite( array $options, bool $range ): ?array {
	$names = $range ? array( 'startKey', 'startTimeKey', 'endKey', 'endTimeKey' ) : array( 'key', 'timeKey' );
	$text  = array();
	foreach ( $names as $name ) {
		$value = $options[ $name ] ?? '';
		if ( ! is_scalar( $value ) ) {
			return null;
		}
		$text[ $name ] = trim( (string) $value );
		if ( false !== strpbrk( $text[ $name ], ';+' ) ) {
			return null;
		}
	}
	$entry = static function ( string $field, string $time ): string {
		return '' === $time ? $field : $field . '+' . $time;
	};

	if ( $range ) {
		// No start: the end-only range, or nothing stated at all.
		if ( '' === $text['startKey'] . $text['startTimeKey'] ) {
			return null;
		}
		$start = $entry( $text['startKey'], $text['startTimeKey'] );
		$end   = $entry( $text['endKey'], $text['endTimeKey'] );
		$key   = '' === $end ? $start : $start . ';' . $end;
	} else {
		if ( ! array_key_exists( 'timeKey', $options ) ) {
			return null;
		}
		$key = $entry( $text['key'], $text['timeKey'] );
	}

	$out = array_diff_key( $options, array_flip( $names ) );
	if ( '' !== $key ) {
		$out['key'] = $key;
	}
	return bws_serialization_order_sort_map( $out );
}

/**
 * Resolve a datetime tag's VALUE — the full read path minus link-wrap and the preview
 * label, for all three tags (`datetime`, `datetime_single`, `datetime_range`).
 *
 * Resolves the entity via `source`, applies the srcTerm hop when set, then delegates to
 * the date-time core or the term core of the chosen family, picked once below. Normalizes
 * the option keys to the canonical core keys before dispatch.
 *
 * `$range` names the family: true and false are a fixed one (an old tag keeps its family
 * whatever its options say, so an old range tag given only `key:x` still reads through the
 * range core), and null is the merged tag, whose mode comes from the options — see
 * bws_normalize_datetime_options()'s MODE clause, the only place that is decided.
 *
 * THE FAMILY'S RESOLVE SEAM (FW-136): the two `try_` datetime templates run their attempts
 * through this same function, registered as each template's `resolve_fn` with the fixed
 * family bound, so an attempt reads exactly as the base tag does and inherits whatever the
 * base read gains. The shells on both sides own what the seam leaves out.
 *
 * A list arm's values arrive wrapped per item out of bws_collect_value_list() and report
 * `link_id` 0, while a singular read reports its own entity and the shell wraps once. `sep`
 * joins whole formatted ranges, and `rangeSep` stays the intra-range start-to-end separator:
 * neither the fold nor the seam sees it, since the range cores own the pair's own assembly.
 *
 * THE STATED FALLBACK STAYS HERE, not in the shell, and it is not a departure from the
 * seam contract so much as the only place it can be stated: the cores emit it themselves
 * on a read that found nothing (bws_handle_date_time_fallback(), four sites in
 * bws_datetime_single_core()), and the tail below applies it to any empty result, unwrapped
 * (why, and which arms need it, is stated there), except in the editor preview, where an
 * empty read is left for the shell's label. Under a `try_` attempt the whole thing is inert:
 * bws_try_run_attempts() strips `fallback` before the resolver is called, so both the
 * cores' emit and the tail resolve to '' and the walk moves on, which is the contract
 * the shell's own once-per-tag fallback needs.
 *
 * FW-116's per-tag fix for this family is that same tail, which covers an ambient claim
 * with every other arm. The structural flip is FW-116's, not this seam's.
 *
 * List mode (#30, parity with base text/title): srcTermIn and src:ref collect up to
 * `limit` results joined with `sep`; empty items are skipped; the fallback is suppressed
 * per-item and fires once on all-empty output; link-wrap applies only when exactly one
 * result renders.
 *
 * Term-ambient parity (FW-3a, CONTEXT.md I1): a bare tag on a term archive
 * reads the ambient term's date field through the same term core the explicit srcTermIn
 * branch uses — one factory call, kind branch via bws_base_ambient_analog(),
 * matching base text/title.
 *
 * @since 1.6.0
 * @since 1.15.0 List mode (limit/sep); src:ref fans out through the shared
 *               traversal engine instead of collapsing to the first target.
 *               Term-ambient parity (FW-3a).
 * @since 1.16.0 List collection via the shared bws_collect_value_list fold
 *               (FW-49) — link identity replaces the first-item kind sniff.
 * @since 1.21.0 The `meta_row` list branch — a `rows` chain reads its rows (FW-74).
 * @since 1.21.0 Extracted from the base callbacks, one copy per family.
 * @since Unreleased One function for all three tags: the single and range copies merged,
 *               and `$range` names the family.
 *
 * @param array     $options  Tag options.
 * @param mixed     $instance GB tag instance.
 * @param bool|null $range    The family: true range, false single, null derived from the options.
 * @return array{value:string, link_id:int, link_type:string} link_id 0 = the caller must
 *                        not link-wrap: either there is no entity, or the value came from
 *                        a list arm that already wrapped per item.
 */
function bws_base_datetime_resolve_value( array $options, $instance, ?bool $range = null ): array {
	$res      = bws_base_src_resolution( $options );
	$mapped   = bws_normalize_datetime_options( $options, $range );
	$is_range = $mapped['is_range'];

	// The family's core, chosen once. Every kind of source goes to it as a resolved-source payload.
	$core = $is_range ? 'bws_datetime_range_core' : 'bws_datetime_single_core';

	$link_id   = 0;
	$link_type = 'post';

	// A tag whose every read is `now` reads no source, so no source (or the lack of one: a 404,
	// a search, the blog home) may decide whether it prints, and there is nothing to link to.
	$only_now = $is_range
		? ( 'now' === ( $mapped['start_verb'] ?? '' ) && 'now' === ( $mapped['end_verb'] ?? '' ) )
		: ( 'now' === ( $mapped['verb'] ?? '' ) );
	if ( $only_now ) {
		return array(
			'value'     => $core( bws_datetime_post_source( false ), $mapped, $instance ),
			'link_id'   => 0,
			'link_type' => 'post',
		);
	}

	// src:site — ACF options-page date field. The core gets the site source; the
	// DT-1 bws_read_field branch (allowlist-gated get_field($key,'option')) performs
	// the value read, and the format chain (bws_build_single_format / bws_build_range_format)
	// recovers the field's return format. Link identity is the sentinel pair (id 1, 'site'),
	// reported unconditionally — the shell wraps only what is non-empty, which is the same
	// test the wrap used to carry here.
	if ( 'site' === $res['kind'] ) {
		return array(
			'value'     => $core( array( 'kind' => 'site' ), $mapped, $instance ),
			'link_id'   => 1,
			'link_type' => 'site',
		);
	}

	// L1 — resolve the base source once; ambient dispatch through the
	// one kind-dispatching seam (FW-3a term parity kept byte-identical through it).
	// Explicit src/loop/id already won inside the factory.
	$base = function_exists( 'bws_base_resolve_source_for_callback' )
		? bws_base_resolve_source_for_callback( $options, $instance )
		: array( 'kind' => 'post', 'id' => 0 );
	$res     = bws_base_settle_kind( $res, $base );
	$ambient = function_exists( 'bws_base_ambient_analog' )
		? bws_base_ambient_analog( bws_datetime_family_tag( $is_range ), $base, $options, $instance )
		: null;

	// Unguarded, unlike its neighbours above — see bws_base_read_refused(). Those guards
	// degrade a read; this one would delete a REFUSAL, silently restoring the defect.
	$refused = bws_base_read_refused( $res, $base, array( 'meta_row' ) );

	if ( $refused ) {
		// REFUSED (GH #75/#76/#109) — read nothing, and skip the core: a datetime core
		// handed a falsy id does not stop, it reads a query-loop item it can be served
		// from (bws_loop_item_is_post_or_row() is that question) and then the queried
		// term. This arm's own empty path is the all-empty fallback below.
		$value = '';
	} elseif ( null !== $ambient ) {
		if ( 'term' === $ambient['link_type'] && $ambient['link_id'] ) {
			// Ambient dispatch ONLY rides the seam: the datetime VALUE still reads
			// through the term core, never the analog reader — FW-3's remaining half
			// is gated on a field-object-formats read the seam does not expose, so
			// the seam's own value for a datetime tag is '' by construction.
			$value     = $core( bws_datetime_term_source( (int) $ambient['link_id'] ), $mapped, $instance );
			$link_id   = (int) $ambient['link_id'];
			$link_type = 'term';
		} else {
			// A non-term ambient claim carries the seam's value itself — '' for any
			// kind whose reader has no datetime arm, so the tag renders empty rather
			// than reading an entity the context did not put there.
			$value = (string) $ambient['value'];
		}
	} elseif ( 'term' === $res['kind'] ) {
		// Shared L3 fold (FW-49): suppression + slice + join live in the helper;
		// $mapped ⊇ $options (additive normalizer), so it serves limit/sep too.
		$collected = bws_collect_value_list(
			function_exists( 'bws_base_term_ids_from_source' )
				? bws_base_term_ids_from_source( $base, $options )
				: array(),
			static function ( $tid, array $item_opts ) use ( $core, $instance ) {
				return array(
					'value' => $core( bws_datetime_term_source( (int) $tid ), $item_opts, $instance ),
					'link'  => array( 'kind' => 'term', 'id' => (int) $tid ),
				);
			},
			$mapped
		);
		$value = $collected['value'];
	} elseif ( 'post' === $res['kind'] ) {
		// Post list mode: read EVERY fanned-out target via the shared traversal
		// engine (plural resolver, not the collapse-to-first wrapper).
		$post_ids = function_exists( 'bws_base_post_ids_from_source' )
			? bws_base_post_ids_from_source( $base, $options )
			: array();
		$collected = bws_collect_value_list(
			$post_ids,
			static function ( $oid, array $item_opts ) use ( $core, $instance ) {
				return array(
					'value' => $core( bws_datetime_post_source( $oid ), $item_opts, $instance ),
					'link'  => array( 'kind' => 'post', 'id' => (int) $oid ),
				);
			},
			$mapped
		);
		$value = $collected['value'];
	} elseif ( 'meta_row' === $res['kind'] ) {
		// REPEATER-ROW LIST (FW-74). The third list branch, and the one that reads
		// SOURCES rather than ids: a row has no entity behind it, so the ids selector —
		// which drops `id <= 0` — cannot express it. Fans exactly as the term and post
		// branches do, through the same fold, so `limit` and `sep` serve it unchanged.
		//
		// THE KIND TESTED HERE IS THE WIRE'S ($res), NEVER THE RESOLVED BASE'S — the trap
		// every row branch sets, stated at length by bws_base_text_resolve_value()'s and
		// pinned by fold-test-matrix.md §F9c. What is specific to THIS family: the base
		// that must keep falling through to the post tail below is the one the cores'
		// own INVARIANT is written about, the issue #22 repeater row a query loop
		// positions us inside and bws_read_field() re-infers.
		//
		// The core takes the resolved SOURCE, and the row's VALUE read is the only half
		// that changes down there: the field-config object id stays the core's own
		// derivation, so a row parses format-agnostically — the boundary
		// bws_parse_combined_date_time() states and FW-3 closes.
		//
		// Link identity stays 0/'post' — a row has none (CONTEXT.md I12), and the plain
		// string return is how the fold is told so.
		$collected = bws_collect_value_list(
			function_exists( 'bws_base_sources_of_kind' )
				? bws_base_sources_of_kind( $base, $options, 'meta_row' )
				: array(),
			static fn( $row_source, array $item_opts ) => $core( (array) $row_source, $item_opts, $instance ),
			$mapped
		);
		$value = $collected['value'];
	} else {
		$post_id   = function_exists( 'bws_base_post_id_from_source' )
			? bws_base_post_id_from_source( $base, $options )
			: get_the_ID();
		$value     = $core( bws_datetime_post_source( $post_id ), $mapped, $instance );
		$link_id   = (int) $post_id;
		$link_type = 'post';
	}

	// All-empty → the stated fallback fires once, unwrapped, for EVERY arm. The date-time
	// core self-applies it on a read that found nothing, so an empty value that reaches
	// here is one where no core ran with `fallback` intact: a list arm (the fold strips
	// `fallback` per item), a refusal, an ambient claim. A pinned singular post or term is
	// a list arm of one, which is why the tail cannot ask whether the chain fans.
	// A core that DID apply it returned non-empty, so this never fires twice. Not in the
	// editor preview: an empty read there is the shell's preview label, which carries the
	// fallback beside the read target (bws_base_text_callback() does the same).
	if ( '' === $value && empty( $instance->context['bwsEditorPreview'] ) ) {
		$value   = bws_handle_date_time_fallback( $mapped, $instance );
		$link_id = 0;
	}

	return array(
		'value'     => $value,
		'link_id'   => $link_id,
		'link_type' => $link_type,
	);
}

/**
 * The shell the three datetime callbacks share: resolve the value through
 * bws_base_datetime_resolve_value(), link-wrap what the singular arms returned (a list arm
 * wrapped its own values per item and reports link_id 0), then on empty output apply the
 * editor preview label.
 *
 * NO STATED FALLBACK HERE — this family's fallback is emitted inside the seam, by the
 * cores on a read that found nothing and by the seam's own tail on any other empty result.
 * See there for why it cannot move up.
 *
 * The preview label is the tag's own: `datetime` for the merged tag, whose mode the preview
 * derives from the reads, and the fixed family's for each old one.
 *
 * @since Unreleased One shell for the three tags, replacing a copy per callback.
 *
 * @param mixed     $options  Tag options.
 * @param mixed     $block    GB block.
 * @param mixed     $instance GB tag instance.
 * @param bool|null $range    The family, as bws_base_datetime_resolve_value().
 */
function bws_base_datetime_shell( $options, $block, $instance, ?bool $range ): string {
	$options    = bws_host_unlinked_options( $options, $block );
	$is_preview = ! empty( $instance->context['bwsEditorPreview'] );

	$resolved = bws_base_datetime_resolve_value( (array) $options, $instance, $range );
	$value    = $resolved['value'];

	if ( '' !== $value ) {
		if ( $resolved['link_id'] && function_exists( 'bws_wrap_with_link' ) ) {
			$value = bws_wrap_with_link(
				$value,
				$options['linkTo'] ?? 'none',
				$options['linkKey'] ?? '',
				bws_flag_is_on( $options, 'newTab' ),
				$resolved['link_id'],
				$resolved['link_type']
			);
		}
		return $value;
	}

	if ( ! $is_preview || ! function_exists( 'bws_build_preview_label' ) ) {
		return '';
	}
	return bws_build_preview_label( $options, bws_datetime_family_tag( $range ) );
}

/**
 * Callback for the `datetime_single` base tag: the single family, whatever its options say.
 *
 * @since 1.6.0
 * @since Unreleased A one-line wrapper over bws_base_datetime_shell().
 */
function bws_base_datetime_single_callback( $options, $block, $instance ): string {
	return bws_base_datetime_shell( $options, $block, $instance, false );
}

/**
 * Callback for the `datetime_range` base tag: the range family, whatever its options say.
 *
 * @since 1.6.0
 * @since Unreleased A one-line wrapper over bws_base_datetime_shell().
 */
function bws_base_datetime_range_callback( $options, $block, $instance ): string {
	return bws_base_datetime_shell( $options, $block, $instance, true );
}

/**
 * Callback for the merged `datetime` base tag: the family is derived from the options.
 *
 * @since Unreleased
 */
function bws_base_datetime_callback( $options, $block, $instance ): string {
	return bws_base_datetime_shell( $options, $block, $instance, null );
}
