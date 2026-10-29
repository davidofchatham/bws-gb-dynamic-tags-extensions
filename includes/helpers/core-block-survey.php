<?php
/**
 * Dynamic tags in core blocks (FW-156) — the content survey behind the settings-page block list.
 *
 * An on-demand, advisory scan: which block types already hold tag-like `{{...}}` strings, split
 * into REGISTERED tags (the ones that start printing a value once their block type is enabled)
 * and unregistered ones (which stay literal regardless). Never a gate.
 *
 * Three parts: the pure WALK over one post-content string (it takes the string and a
 * registered-name test and knows nothing of WordPress storage), the pure helpers around its
 * result, and the WordPress-side RUNNER that feeds it every post and block widget and answers
 * the AJAX call. FW-155's converter is meant to read block types through the same walk. The
 * block widgets are enumerated by bws_block_widget_rows(), which the Migration Tool's scan
 * shares (FW-73); the post enumeration is the runner's own, because it batches by ID cursor.
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
 * @return array<string, array{registered: int, unregistered: int, registered_tags: array<string, int>, unregistered_tags: array<string, int>}> Block name => counts and, per kind, tag name => times found; blocks with no tag are absent.
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
					$out[ $name ] = $out[ $name ] ?? bws_core_blocks_survey_cell();
					$kind         = $is_registered( $tag ) ? 'registered' : 'unregistered';
					++$out[ $name ][ $kind ];
					$out[ $name ][ $kind . '_tags' ][ $tag ] = 1 + ( $out[ $name ][ $kind . '_tags' ][ $tag ] ?? 0 );
				}
			}
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			bws_core_blocks_survey_walk_blocks( (array) $block['innerBlocks'], $is_registered, $out );
		}
	}
}

/**
 * An empty per-block cell: the two counts and, per kind, the tag names behind them.
 *
 * @since Unreleased
 * @return array
 */
function bws_core_blocks_survey_cell(): array {
	return array(
		'registered'        => 0,
		'unregistered'      => 0,
		'registered_tags'   => array(),
		'unregistered_tags' => array(),
	);
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
		$total[ $name ] = $total[ $name ] ?? bws_core_blocks_survey_cell();
		foreach ( array( 'registered', 'unregistered' ) as $kind ) {
			$total[ $name ][ $kind ] += (int) $cell[ $kind ];
			foreach ( (array) ( $cell[ $kind . '_tags' ] ?? array() ) as $tag => $n ) {
				$total[ $name ][ $kind . '_tags' ][ $tag ] = (int) $n + ( $total[ $name ][ $kind . '_tags' ][ $tag ] ?? 0 );
			}
		}
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
			foreach ( array( 'registered', 'unregistered' ) as $kind ) {
				$blocks[ $name ][ $kind . '_tags' ] = array();
				foreach ( (array) ( $cell[ $kind . '_tags' ] ?? array() ) as $tag => $n ) {
					if ( (int) $n > 0 && preg_match( '/^[A-Za-z0-9_\-.]+$/', (string) $tag ) ) {
						$blocks[ $name ][ $kind . '_tags' ][ $tag ] = (int) $n;
					}
				}
			}
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
 * Every block widget instance: instance number => the block markup it stores.
 *
 * THE ONE ENUMERATION OF BLOCK WIDGETS. The survey runner counts over it and the Migration
 * Tool's scan lists from it, so what counts as an instance is stated once. Posts are NOT
 * shared the same way: the survey batches 200 posts by ID cursor on a bare `{{` LIKE, while
 * TagConverter::scan() runs one query with a LIKE per deprecated name.
 *
 * An instance is a numeric key whose value is an array holding a string `content`; the
 * `_multiwidget` marker and anything malformed are not instances. Classic Text and HTML widgets
 * live in other options and are out of scope.
 *
 * @since Unreleased
 * @return array<int, string> Ascending by instance number.
 */
function bws_block_widget_rows(): array {
	return bws_block_widget_rows_of( (array) get_option( 'widget_block', array() ) );
}

/**
 * The instances in a `widget_block` option value already in hand: number => content.
 *
 * bws_block_widget_rows() over a value instead of the stored option, so a write path that has
 * just read the option itself (TagConverter::migrate_widgets()) asks the same question.
 *
 * @since Unreleased
 * @param array $option The option value.
 * @return array<int, string> Ascending by instance number.
 */
function bws_block_widget_rows_of( array $option ): array {
	$rows = array();
	foreach ( $option as $number => $widget ) {
		if ( is_int( $number ) && is_array( $widget ) && isset( $widget['content'] ) && is_string( $widget['content'] ) ) {
			$rows[ $number ] = $widget['content'];
		}
	}
	ksort( $rows );
	return $rows;
}

/**
 * Which active sidebar holds each block widget instance.
 *
 * Instances in the inactive area, in an orphaned area, or in no sidebar at all are absent from
 * the result; the caller calls those "Inactive". Widgets sit in `sidebars_widgets` as
 * `block-<number>`, and an instance placed in two sidebars reports the first.
 *
 * @since Unreleased
 * @param array $sidebars_widgets wp_get_sidebars_widgets(): sidebar id => widget ids.
 * @return array<int, string> Instance number => sidebar id.
 */
function bws_block_widget_placements( array $sidebars_widgets ): array {
	$out = array();
	foreach ( $sidebars_widgets as $sidebar => $ids ) {
		if ( ! is_array( $ids ) || 'wp_inactive_widgets' === $sidebar || 0 === strpos( (string) $sidebar, 'orphaned_widgets' ) ) {
			continue;
		}
		foreach ( $ids as $id ) {
			if ( is_string( $id ) && 1 === preg_match( '/^block-(\d+)$/', $id, $m ) && ! isset( $out[ (int) $m[1] ] ) ) {
				$out[ (int) $m[1] ] = (string) $sidebar;
			}
		}
	}
	return $out;
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
	foreach ( bws_block_widget_rows() as $content ) {
		$widgets = bws_core_blocks_survey_merge( $widgets, bws_core_blocks_survey_walk( $content, $registered ) );
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
 * Response: `status` (the scan status line, totals included), `rows` (block name => { registered, unregistered,
 * registered_html, unregistered_html }, the last two being each Handled by GB or Not handled by GB cell's content: count and tag list;
 * only blocks with a count; the page shows 0 and no tag list in every other row).
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
			'registered'        => $cell['registered'],
			'unregistered'      => $cell['unregistered'],
			'registered_html'   => bws_core_blocks_survey_count_html( $cell['registered'], $text, $cell['registered_tags'] ),
			'unregistered_html' => bws_core_blocks_survey_count_html( $cell['unregistered'], $text, $cell['unregistered_tags'] ),
		);
	}
	wp_send_json_success(
		array(
			'status' => bws_core_blocks_survey_status( $result, bws_core_blocks_picker_groups( $result ), $text ),
			'rows'   => $rows,
		)
	);
}
