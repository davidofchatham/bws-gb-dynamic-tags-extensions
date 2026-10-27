<?php
/**
 * Dynamic tags in core blocks (FW-156) — the content survey behind the settings-page block list.
 *
 * An on-demand, advisory scan: which block types already hold tag-like `{{...}}` strings, split
 * into REGISTERED tags (the ones that start printing a value once their block type is selected)
 * and unregistered ones (which stay literal regardless). Never a gate.
 *
 * Three parts: the pure WALK over one post-content string (the unit FW-128's shared scanner and
 * FW-155's converter are meant to adopt; it takes the string and a registered-name test and
 * knows nothing of WordPress storage), the pure helpers around its result, and the
 * WordPress-side RUNNER that feeds it every post and block widget and answers the AJAX call.
 *
 * @package BWS_Dynamic_Tags
 * @since Unreleased
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Option holding the last scan's result. Separate from the settings option, which a save rebuilds from a fixed shape. */
const BWS_CORE_BLOCKS_SURVEY_OPTION = 'bws_dynamic_tags_core_block_survey';

/**
 * Count the tag-like strings in one post-content string, per block type.
 *
 * A tag is counted against the block whose OWN markup holds it (its `innerContent` strings,
 * never its inner blocks' and never its comment attributes), because that is the markup GB
 * would replace tags in once the block's type is allowlisted. Text outside any block has no
 * block type and is left out, as is a block that only references other content (a reusable
 * block or template part): the referenced post is scanned as a post of its own. Malformed
 * markup is whatever WordPress's parser makes of it.
 *
 * @since Unreleased
 * @param string   $content       Post content (block markup).
 * @param callable $is_registered Given a tag name, whether GB has it registered.
 * @return array<string, array{registered: int, unregistered: int}> Block name => counts; blocks with no tag are absent.
 */
function bws_core_blocks_survey_walk( string $content, callable $is_registered ): array {
	if ( false === strpos( $content, '{{' ) ) {
		return array();
	}
	$out = array();
	bws_core_blocks_survey_walk_blocks( parse_blocks( $content ), $is_registered, $out );
	return $out;
}

/** Recursive half of the walk: add each block's own-markup counts to `$out`. */
function bws_core_blocks_survey_walk_blocks( array $blocks, callable $is_registered, array &$out ): void {
	foreach ( $blocks as $block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}
		$name = $block['blockName'] ?? null;
		if ( is_string( $name ) && '' !== $name ) {
			$own = implode( '', array_filter( (array) ( $block['innerContent'] ?? array() ), 'is_string' ) );
			if ( false !== strpos( $own, '{{' ) && preg_match_all( '/\{\{\s*([A-Za-z0-9_\-.]+)(?:\s[^}]*)?\}\}/', $own, $found ) ) {
				foreach ( $found[1] as $tag ) {
					$out[ $name ] = $out[ $name ] ?? array( 'registered' => 0, 'unregistered' => 0 );
					++$out[ $name ][ $is_registered( $tag ) ? 'registered' : 'unregistered' ];
				}
			}
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			bws_core_blocks_survey_walk_blocks( (array) $block['innerBlocks'], $is_registered, $out );
		}
	}
}

/**
 * Add one walk's counts into a running total.
 *
 * @since Unreleased
 * @param array $total Running total (block name => counts).
 * @param array $part  One walk's result.
 * @return array
 */
function bws_core_blocks_survey_merge( array $total, array $part ): array {
	foreach ( $part as $name => $cell ) {
		$total[ $name ] = $total[ $name ] ?? array( 'registered' => 0, 'unregistered' => 0 );
		$total[ $name ]['registered']   += (int) $cell['registered'];
		$total[ $name ]['unregistered'] += (int) $cell['unregistered'];
	}
	return $total;
}

/**
 * A stored result, cleaned for use: ints, well-formed block names only. Null when it is not a
 * result at all (never scanned, or the option holds something else).
 *
 * @since Unreleased
 * @param mixed $raw The stored value.
 * @return array{time: int, blocks: array, widgets: array{registered: int, unregistered: int}}|null
 */
function bws_core_blocks_survey_clean( $raw ): ?array {
	if ( ! is_array( $raw ) || ! isset( $raw['time'], $raw['blocks'] ) || ! is_array( $raw['blocks'] ) ) {
		return null;
	}
	$blocks = array();
	foreach ( $raw['blocks'] as $name => $cell ) {
		if ( is_array( $cell ) && bws_core_blocks_is_block_name( $name ) ) {
			$blocks[ $name ] = array(
				'registered'   => (int) ( $cell['registered'] ?? 0 ),
				'unregistered' => (int) ( $cell['unregistered'] ?? 0 ),
			);
		}
	}
	$widgets = is_array( $raw['widgets'] ?? null ) ? $raw['widgets'] : array();
	return array(
		'time'    => (int) $raw['time'],
		'blocks'  => $blocks,
		'widgets' => array(
			'registered'   => (int) ( $widgets['registered'] ?? 0 ),
			'unregistered' => (int) ( $widgets['unregistered'] ?? 0 ),
		),
	);
}

/**
 * The last scan's result, or null before the first scan.
 *
 * @since Unreleased
 * @return array|null See bws_core_blocks_survey_clean().
 */
function bws_core_blocks_survey_last(): ?array {
	return bws_core_blocks_survey_clean( get_option( BWS_CORE_BLOCKS_SURVEY_OPTION, null ) );
}

/**
 * Walk the whole site: every post of every type (drafts in; revisions, trash and auto-drafts out),
 * which takes in reusable blocks, templates, template parts and GB Pro patterns, then the block
 * widgets. Classic Text and HTML widgets never pass through block rendering and are out.
 *
 * Reads posts in ID-ordered batches, so memory stays flat on a large site.
 *
 * @since Unreleased
 * @return array{blocks: array, widgets: array{registered: int, unregistered: int}}
 */
function bws_core_blocks_survey_run(): array {
	global $wpdb;

	$tags       = class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ? \GenerateBlocks_Register_Dynamic_Tag::get_tags() : array();
	$registered = static fn( string $name ): bool => isset( $tags[ $name ] );
	$total      = array();
	$last_id    = 0;
	$like       = '%' . $wpdb->esc_like( '{{' ) . '%';

	do {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts}
				 WHERE ID > %d AND post_type != 'revision' AND post_status NOT IN ('auto-draft', 'trash') AND post_content LIKE %s
				 ORDER BY ID ASC LIMIT 200",
				$last_id,
				$like
			)
		);
		foreach ( (array) $rows as $row ) {
			$last_id = (int) $row->ID;
			$total   = bws_core_blocks_survey_merge( $total, bws_core_blocks_survey_walk( (string) $row->post_content, $registered ) );
		}
	} while ( count( (array) $rows ) === 200 );

	$widgets = array();
	foreach ( (array) get_option( 'widget_block', array() ) as $widget ) {
		if ( is_array( $widget ) && isset( $widget['content'] ) && is_string( $widget['content'] ) ) {
			$widgets = bws_core_blocks_survey_merge( $widgets, bws_core_blocks_survey_walk( $widget['content'], $registered ) );
		}
	}
	$widget_sum = array( 'registered' => 0, 'unregistered' => 0 );
	foreach ( $widgets as $cell ) {
		$widget_sum['registered']   += $cell['registered'];
		$widget_sum['unregistered'] += $cell['unregistered'];
	}

	return array(
		'blocks'  => bws_core_blocks_survey_merge( $total, $widgets ),
		'widgets' => $widget_sum,
	);
}

/**
 * AJAX: run the scan, store it, hand back what the page needs to update itself.
 *
 * Response: `status` (the "Last scan" line), `rows` (block name => { registered, unregistered }, only
 * blocks with a count; the page shows 0 in every other row).
 *
 * @since Unreleased
 */
function bws_core_blocks_survey_ajax(): void {
	check_ajax_referer( 'bws_core_blocks_survey', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'generateblocks' ) ), 403 );
	}

	$result         = bws_core_blocks_survey_run();
	$result['time'] = time();
	update_option( BWS_CORE_BLOCKS_SURVEY_OPTION, $result, false );

	$text = bws_core_blocks_picker_strings();
	$rows = array();
	foreach ( $result['blocks'] as $name => $cell ) {
		$rows[ $name ] = array(
			'registered'   => $cell['registered'],
			'unregistered' => $cell['unregistered'],
		);
	}
	wp_send_json_success(
		array(
			'status' => bws_core_blocks_survey_status( $result['time'], $text ),
			'rows'   => $rows,
		)
	);
}
