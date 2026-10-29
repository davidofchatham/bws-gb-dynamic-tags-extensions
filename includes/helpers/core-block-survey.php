<?php
/**
 * Dynamic tags in core blocks (FW-156) — the content survey behind the settings-page block list.
 *
 * An on-demand, advisory scan: which block types already hold tag-like `{{...}}` strings, split
 * into REGISTERED tags (the ones that start printing a value once their block type is enabled)
 * and unregistered ones (which stay literal regardless). Never a gate.
 *
 * Three parts: the pure LOCATED WALK over one content string (it takes the string, a
 * registered-name test, the container it came from and a classifier, and knows nothing of
 * WordPress storage; it yields one record per tag occurrence, FW-73), the pure helpers around
 * it (the survey is a count over those records), and the WordPress-side RUNNER that feeds it
 * every post and block widget and answers the AJAX call. FW-155's converter is meant to read
 * block types from the same records.
 *
 * WHICH ENUMERATION IS SHARED. Only the widgets: bws_block_widget_rows() is read by this runner
 * and by the Migration Tool's scan, so what counts as a widget instance is stated once. The
 * post enumeration stays the runner's own and is NOT shared with TagConverter::scan(), because
 * the survey batches 200 posts by ID cursor on a bare `{{` LIKE while scan() runs one query
 * with a LIKE per deprecated name.
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
 * The located walk: one record per tag-like string in one content string.
 *
 * A tag is attributed to the block whose OWN markup holds it (its `innerContent` strings,
 * never its inner blocks' and never its comment attributes), because that is the markup GB
 * would replace tags in once the block's type is allowlisted. Text outside any block has no
 * block type and is left out, as is a block that only references other content (a reusable
 * block or template part): the referenced post is scanned as a post of its own. Malformed
 * markup is whatever WordPress's parser makes of it.
 *
 * `category` is the BASE category the survey counts: `registered` (Handled by GB) or
 * `unregistered` (Not handled by GB). `convertible` is independent of it: true only when
 * `$classify` answers `convert`, so a deprecated name still registered and the same name after
 * an unregister are both flagged and sit in different categories. The walk holds no rule of its
 * own for the flag; see bws_core_blocks_survey_classifier(). A declined or skipped occurrence
 * is `false`, like one the classifier is not asked about.
 *
 * `ordinal` is the block's 1-based position among blocks of its type in this container, in
 * document order with a parent before its children, counting blocks that hold no tag, because
 * it is what an author counts in the editor's List View.
 *
 * @since Unreleased
 * @param string        $content       Post content or a widget's block markup.
 * @param callable      $is_registered Given a tag name, whether GB has it registered.
 * @param array         $container     `{kind: post|widget, id: int}`, stamped on every record.
 * @param callable|null $classify      Given (tag name, stored string), the classifier's status string or null when the name is not its business.
 * @return array<int, array{tag: string, category: string, convertible: bool, block_name: string, container: array, ordinal: int, stored: string}>
 */
function bws_core_blocks_survey_walk( string $content, callable $is_registered, array $container, ?callable $classify = null ): array {
	if ( false === strpos( $content, '{{' ) ) {
		return array();
	}
	$out  = array();
	$seen = array();
	bws_core_blocks_survey_walk_blocks( parse_blocks( $content ), $is_registered, $container, $classify, $seen, $out );
	return $out;
}

/** Recursive half of the walk: append each block's own-markup records to `$out`. */
function bws_core_blocks_survey_walk_blocks( array $blocks, callable $is_registered, array $container, ?callable $classify, array &$seen, array &$out ): void {
	foreach ( $blocks as $block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}
		$name = $block['blockName'] ?? null;
		if ( is_string( $name ) && '' !== $name ) {
			$seen[ $name ] = 1 + ( $seen[ $name ] ?? 0 );
			$own           = implode( '', array_filter( (array) ( $block['innerContent'] ?? array() ), 'is_string' ) );
			if ( false !== strpos( $own, '{{' ) && preg_match_all( '/\{\{\s*([A-Za-z0-9_\-.]+)(?:\s[^}]*)?\}\}/', $own, $found, PREG_SET_ORDER ) ) {
				foreach ( $found as $m ) {
					$out[] = array(
						'tag'         => $m[1],
						'category'    => $is_registered( $m[1] ) ? 'registered' : 'unregistered',
						'convertible' => null !== $classify && 'convert' === $classify( $m[1], $m[0] ),
						'block_name'  => $name,
						'container'   => $container,
						'ordinal'     => $seen[ $name ],
						'stored'      => $m[0],
					);
				}
			}
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			bws_core_blocks_survey_walk_blocks( (array) $block['innerBlocks'], $is_registered, $container, $classify, $seen, $out );
		}
	}
}

/**
 * Count walk records per block type: the survey's per-block cells.
 *
 * @since Unreleased
 * @param array $records bws_core_blocks_survey_walk() records.
 * @return array<string, array{registered: int, unregistered: int, registered_tags: array<string, int>, unregistered_tags: array<string, int>}> Block name => counts and, per category, tag name => times found; blocks with no record are absent.
 */
function bws_core_blocks_survey_count( array $records ): array {
	$out = array();
	foreach ( $records as $r ) {
		$name         = $r['block_name'];
		$kind         = $r['category'];
		$out[ $name ] = $out[ $name ] ?? bws_core_blocks_survey_cell();
		++$out[ $name ][ $kind ];
		$out[ $name ][ $kind . '_tags' ][ $r['tag'] ] = 1 + ( $out[ $name ][ $kind . '_tags' ][ $r['tag'] ] ?? 0 );
	}
	return $out;
}

/**
 * The walk's `$classify` callable: TagConverter::classify_tag()'s status for a deprecated tag name.
 *
 * classify_tag() is THE owner of what the Migration Tool would convert, and it answers
 * `convert` for any name whose transform changes nothing, so it is asked only of the names the
 * converter loops over (MigrationRegistry::get_deprecated_tag_names()); every other name gets
 * null. Null as well when the converter is not loadable.
 *
 * @since Unreleased
 * @return callable|null (string $tag, string $stored): ?string
 */
function bws_core_blocks_survey_classifier(): ?callable {
	$registry  = '\BWS\DynamicTags\MigrationRegistry';
	$converter = '\BWS\DynamicTags\Admin\TagConverter';
	if ( ! class_exists( $registry ) || ! class_exists( $converter ) ) {
		return null;
	}
	$deprecated = array_flip( $registry::get_deprecated_tag_names() );
	return static fn( string $tag, string $stored ): ?string => isset( $deprecated[ $tag ] ) ? $converter::classify_tag( $tag, $stored )['status'] : null;
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
	$classify   = bws_core_blocks_survey_classifier();
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
			$total   = bws_core_blocks_survey_merge( $total, bws_core_blocks_survey_count( bws_core_blocks_survey_walk( (string) $row->post_content, $registered, array( 'kind' => 'post', 'id' => $last_id ), $classify ) ) );
		}
	} while ( count( (array) $rows ) === 200 );

	$widgets = array();
	foreach ( bws_block_widget_rows() as $number => $content ) {
		$widgets = bws_core_blocks_survey_merge( $widgets, bws_core_blocks_survey_count( bws_core_blocks_survey_walk( $content, $registered, array( 'kind' => 'widget', 'id' => $number ), $classify ) ) );
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
