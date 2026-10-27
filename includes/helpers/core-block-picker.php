<?php
/**
 * Dynamic tags in core blocks (FW-156) — the settings-page block list.
 *
 * Three parts: the TEXT (one function, so wording is reviewed and changed in one place), the
 * pure ROW BUILDER the page renders from, and the thin WordPress READER that gathers the
 * server block registry for it. The enabled set itself is owned by core-block-tags.php; what
 * a block does with a tag is owned by bws_core_block_kind() in link-helpers.php. This file
 * asks both and states only the consequence.
 *
 * @package BWS_Dynamic_Tags
 * @since Unreleased
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every user-facing string of the block list. Backticks mark code, asterisks emphasis (see
 * bws_core_blocks_picker_markup()). A row's `notes` are keys into this array.
 *
 * @since Unreleased
 * @return array<string, string|string[]>
 */
function bws_core_blocks_picker_strings(): array {
	return array(
		'heading'            => __( 'Enable Tags in Other Blocks', 'generateblocks' ),
		'intro'              => __( 'GenerateBlocks normally parses dynamic tags only in its own blocks. Allow processing tags within other block types by enabling them below. Tags must be manually inserted.', 'generateblocks' ),
		'search_placeholder' => __( 'Search blocks', 'generateblocks' ),
		'show_legend'        => __( 'Show', 'generateblocks' ),
		'show_default'       => __( 'Enabled or with tags', 'generateblocks' ),
		'show_default_unscanned' => __( 'Enabled', 'generateblocks' ),
		'show_enabled'       => __( 'Enabled', 'generateblocks' ),
		'show_tags'          => __( 'With tags', 'generateblocks' ),
		'show_all'           => __( 'All blocks', 'generateblocks' ),
		'cat_label'          => __( 'Category', 'generateblocks' ),
		'cat_all'            => __( 'All categories', 'generateblocks' ),
		'reset'              => __( 'Reset filters', 'generateblocks' ),
		/* translators: 1: blocks shown, 2: blocks in the list. */
		'showing'            => __( 'Showing %1$d of %2$d blocks.', 'generateblocks' ),
		'showing_hint'       => __( 'Run a scan to find the blocks that already hold tags.', 'generateblocks' ),
		'empty_filtered'     => __( 'No blocks match these filters.', 'generateblocks' ),
		'empty_default'      => __( 'No block is enabled and the last scan found no tags. Search for a block, or show all blocks.', 'generateblocks' ),
		'empty_default_unscanned' => __( 'No block is enabled yet. Search for a block, or show all blocks.', 'generateblocks' ),
		'show_all_button'    => __( 'Show all blocks', 'generateblocks' ),
		/* translators: %s: a short list of variation names. */
		'variations'         => __( 'Includes its variations, such as %s.', 'generateblocks' ),
		'inactive_heading'   => __( 'Not active on this site now', 'generateblocks' ),
		'unregistered'       => __( 'Saved earlier. Kept so it works again if its plugin comes back.', 'generateblocks' ),
		'code_set'           => __( 'Turned on by code on this site.', 'generateblocks' ),
		'dirty'              => __( 'Unsaved changes', 'generateblocks' ),
		'contents_inline'    => __( 'Inline text', 'generateblocks' ),
		'contents_button'    => __( 'Inline text; the block is the link', 'generateblocks' ),
		'contents_list'      => __( 'No text of its own', 'generateblocks' ),
		'contents_untested'  => __( 'Not tested', 'generateblocks' ),
		'limits_inline'      => __( '`content` keeps inline formatting only; `table` returns empty', 'generateblocks' ),
		'limits_button'      => __( '`content` keeps text only; `table` returns empty; link tags return plain text', 'generateblocks' ),
		'limits_none'        => __( '–', 'generateblocks' ),
		'limits_none_sr'     => __( 'No limit', 'generateblocks' ),
		'count_unscanned'    => __( 'Not scanned', 'generateblocks' ),
		'caption_before'     => __( 'Blocks, and what a tag can do in each', 'generateblocks' ),
		'caption_after'      => __( 'Blocks, with the tags the last scan found in each and what a tag can do there', 'generateblocks' ),
		'col_block'          => __( 'Block', 'generateblocks' ),
		'col_contents'       => __( 'Contents', 'generateblocks' ),
		'col_limits'         => __( 'Limits', 'generateblocks' ),
		'col_known'          => __( 'Known', 'generateblocks' ),
		'col_unknown'        => __( 'Unknown', 'generateblocks' ),
		/* translators: 1: column name, 2: ascending or descending. */
		'sorted'             => __( 'Sorted by %1$s, %2$s.', 'generateblocks' ),
		'ascending'          => __( 'ascending', 'generateblocks' ),
		'descending'         => __( 'descending', 'generateblocks' ),
		'key_summary'        => __( 'Key to the counts, symbols and limits', 'generateblocks' ),
		// Term, then explanation. A limit's term is the Limits cell's own phrase (Invariant 6).
		'key'                => array(
			array( __( 'Known', 'generateblocks' ), __( 'Tags registered with GenerateBlocks, including those of GenerateBlocks Pro, this plugin, and any other extension.', 'generateblocks' ) ),
			array( __( 'Unknown', 'generateblocks' ), __( '`{{…}}` tags not registered with GenerateBlocks, which may belong to the theme, a plugin or a code snippet.', 'generateblocks' ) ),
			array( __( '–', 'generateblocks' ), __( 'No limits. This plugin changes nothing about how a tag renders in this block.', 'generateblocks' ) ),
			array( __( 'No text of its own', 'generateblocks' ), __( 'The block holds other blocks, not text. Enabling it does not cover the blocks inside it: enable those, and put tags in them.', 'generateblocks' ) ),
			array( __( '`content` keeps inline formatting only', 'generateblocks' ), __( 'Text, bold, italics and links stay. Paragraph breaks, headings, lists and images are dropped, so long content runs together.', 'generateblocks' ) ),
			array( __( '`content` keeps text only', 'generateblocks' ), __( 'Only the text stays. Links are dropped too.', 'generateblocks' ) ),
			array( __( '`table` returns empty', 'generateblocks' ), __( 'Unless *Required to render* is disabled, GenerateBlocks removes the block, like a tag that returns no value.', 'generateblocks' ) ),
			array( __( 'link tags return plain text', 'generateblocks' ), __( '`email`, `phone` and any tag set to *Link To* print just the address, number or text, not a link. In a Button\'s link, type `mailto:` or `tel:` before the tag yourself.', 'generateblocks' ) ),
		),
		'survey_none_yet'    => __( 'No scan has been run yet.', 'generateblocks' ),
		/* translators: 1: known tags in the blocks listed below, 2: unknown tags in them, 3: date and time of the scan. */
		'survey_status'      => __( '%1$d known tags and %2$d unknown tags found in the blocks below as of %3$s.', 'generateblocks' ),
		'survey_desc'        => __( 'A scan checks all of your content (posts, pages, templates, patterns and block widgets) for `{{…}}` tags in every block in this list, enabled or not, and shows how many each block holds, whether or not they are registered with GenerateBlocks. Blocks added only by a plugin\'s editor script are not listed.', 'generateblocks' ),
		'survey_scan'        => __( 'Scan content for tags', 'generateblocks' ),
		'survey_running'     => __( 'Scanning…', 'generateblocks' ),
		'survey_error'       => __( 'The scan could not finish. Try again.', 'generateblocks' ),
		'confirm_intro'      => __( 'These newly enabled blocks already hold known tags. Once you save, those tags are filled in on the site instead of showing as typed.', 'generateblocks' ),
		'confirm_save'       => __( 'Save anyway', 'generateblocks' ),
		'confirm_back'       => __( 'Go back', 'generateblocks' ),
		'note_warn'          => __( 'Warning:', 'generateblocks' ),
		'note_info'          => __( 'Note:', 'generateblocks' ),
		// In display order; `tone` is warn or info.
		'notes'              => array(
			array( 'tone' => 'warn', 'text' => __( 'Disabling a block type that contains tags processed by GenerateBlocks will cause the raw tag strings to be shown on the front end.', 'generateblocks' ) ),
			array( 'tone' => 'info', 'text' => __( 'By default, a tag that returns no value removes its entire block. To keep the block, disable *Required to render*, as in `{{text required:false|key:subtitle}}`.', 'generateblocks' ) ),
		),
	);
}

/**
 * Escape a string, then turn `code` into <code> and *emphasis* into <em>. Safe because the
 * escape runs first and neither marker survives it as markup.
 *
 * @since Unreleased
 * @param string $text Plain text with optional markers.
 * @return string HTML.
 */
function bws_core_blocks_picker_markup( string $text ): string {
	$html = esc_html( $text );
	$html = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $html );
	return preg_replace( '/\*([^*]+)\*/', '<em>$1</em>', $html );
}

/**
 * Build the grouped rows the block list renders. Pure: no WordPress calls.
 *
 * Visible: every entry, except one that hides itself from the inserter (`inserter` false), that
 * GenerateBlocks already allowlists on its own (`$native`, where selecting changes nothing), or
 * that is GenerateBlocks' or GB Pro's own block (GB decides where its blocks take tags), and is
 * not in the enabled set. Names in the enabled set that no entry matches (a deactivated
 * plugin's block) get their own last group, selected, so a save keeps them. A name enabled only
 * by code (in `$enabled`, not in `$saved`) is selected and locked: an unchecked box and a
 * disabled one both post nothing, so the page never writes it into the saved list.
 *
 * Row keys: name, title, selected, disabled, variations (titles), registered and unregistered
 * (the last scan's tag counts for the block: ints once a scan exists, null before), search (lowercase haystack),
 * contents and limits (string keys, see bws_core_blocks_picker_cells()), untested (bool), notes
 * (keys into bws_core_blocks_picker_strings(): `unregistered` and `code_set` when they apply).
 *
 * @since Unreleased
 * @param array[]  $entries    Each: name, title, category (slug), inserter (bool), variations (string[]).
 * @param string[] $categories Category slug => label, in display order.
 * @param string[] $saved      The saved list.
 * @param string[] $enabled    The enabled set (saved plus filter).
 * @param string[] $native     Block names GenerateBlocks allowlists without our addition (see bws_core_blocks_gb_native()).
 * @param array|null $survey   The last scan's `blocks` (name => registered, unregistered counts), or null before the first scan.
 * @return array[] Groups: slug, label, inactive (bool), rows.
 */
function bws_core_blocks_picker_rows( array $entries, array $categories, array $saved, array $enabled, array $native = array(), ?array $survey = null ): array {
	$by_slug = array();
	$known   = array();
	foreach ( $entries as $e ) {
		$known[ $e['name'] ] = true;
		$gb_own = bws_core_blocks_is_gb_own( $e['name'] );
		if ( ( empty( $e['inserter'] ) || $gb_own || in_array( $e['name'], $native, true ) ) && ! in_array( $e['name'], $enabled, true ) ) {
			continue;
		}
		$slug   = '' !== (string) $e['category'] ? (string) $e['category'] : 'uncategorized';
		$label  = $categories[ $slug ] ?? ucfirst( $slug );
		$title  = '' !== trim( (string) $e['title'] ) ? (string) $e['title'] : bws_core_blocks_picker_slug_label( $e['name'] );
		$by_slug[ $slug ][] = bws_core_blocks_picker_row( $e['name'], $title, $label, (array) ( $e['variations'] ?? array() ), true, $saved, $enabled, $survey );
	}

	$groups = array();
	$order  = array_merge( array_keys( $categories ), array_diff( array_keys( $by_slug ), array_keys( $categories ) ) );
	foreach ( $order as $slug ) {
		if ( empty( $by_slug[ $slug ] ) ) {
			continue;
		}
		$groups[] = array(
			'slug'     => $slug,
			'label'    => $categories[ $slug ] ?? ucfirst( $slug ),
			'inactive' => false,
			'rows'     => bws_core_blocks_picker_sorted( $by_slug[ $slug ] ),
		);
	}

	$gone = array();
	foreach ( $enabled as $name ) {
		if ( empty( $known[ $name ] ) ) {
			$gone[] = bws_core_blocks_picker_row( $name, $name, '', array(), false, $saved, $enabled, $survey );
		}
	}
	if ( $gone ) {
		$groups[] = array(
			'slug'     => 'inactive',
			'label'    => '',
			'inactive' => true,
			'rows'     => bws_core_blocks_picker_sorted( $gone ),
		);
	}
	return $groups;
}

/**
 * The Contents and Limits cells of a block, as keys into bws_core_blocks_picker_strings(). Asks
 * the classification owner by block name and states only the consequence, never its map. A block
 * not registered right now is unclassified whatever its name says. Limits is what THIS plugin
 * enforces, so an unclassified block's Limits is "none", never "not tested".
 *
 * @since Unreleased
 * @param string $name       Block name.
 * @param bool   $registered Whether the block is registered on this site now.
 * @return array{contents: string, limits: string, untested: bool}
 */
function bws_core_blocks_picker_cells( string $name, bool $registered = true ): array {
	switch ( $registered ? bws_core_block_kind( array( 'blockName' => $name ) ) : '' ) {
		case BWS_CORE_BLOCK_KIND_INLINE:
		case BWS_CORE_BLOCK_KIND_TABLE:
			return array( 'contents' => 'contents_inline', 'limits' => 'limits_inline', 'untested' => false );
		case BWS_CORE_BLOCK_KIND_BUTTON:
			return array( 'contents' => 'contents_button', 'limits' => 'limits_button', 'untested' => false );
		case BWS_CORE_BLOCK_KIND_LIST:
			return array( 'contents' => 'contents_list', 'limits' => 'limits_none', 'untested' => false );
	}
	return array( 'contents' => 'contents_untested', 'limits' => 'limits_none', 'untested' => true );
}

/** One row. */
function bws_core_blocks_picker_row( string $name, string $title, string $category_label, array $variations, bool $registered, array $saved, array $enabled, ?array $survey = null ): array {
	$notes = array();
	if ( ! $registered ) {
		$notes[] = 'unregistered';
	}
	$selected = in_array( $name, $enabled, true );
	$code_set = $selected && ! in_array( $name, $saved, true );
	if ( $code_set ) {
		$notes[] = 'code_set';
	}
	return array_merge(
		bws_core_blocks_picker_cells( $name, $registered ),
		array(
			'name'         => $name,
			'title'        => $title,
			'selected'     => $selected,
			'disabled'     => $code_set,
			'variations'   => array_values( array_map( 'strval', $variations ) ),
			'registered'   => null === $survey ? null : (int) ( $survey[ $name ]['registered'] ?? 0 ),
			'unregistered' => null === $survey ? null : (int) ( $survey[ $name ]['unregistered'] ?? 0 ),
			'search'       => strtolower( implode( ' ', array_merge( array( $title, $name, $category_label ), $variations ) ) ),
			'notes'        => $notes,
		)
	);
}

/**
 * One Known or Unknown cell's content, as HTML. Before any scan it is a dash with screen reader
 * text, never 0 (a 0 would claim a scan found nothing). block-picker.js writes the same shape
 * after a scan so a row refreshes without a reload.
 *
 * @since Unreleased
 * @param int|null $count The last scan's count for this block and kind, or null before any scan.
 * @param array    $text  bws_core_blocks_picker_strings().
 * @return string HTML.
 */
function bws_core_blocks_survey_count_html( ?int $count, array $text ): string {
	if ( null === $count ) {
		return '<span aria-hidden="true">–</span><span class="screen-reader-text">' . esc_html( $text['count_unscanned'] ) . '</span>';
	}
	return 0 === $count ? '<span class="bws-block-picker__zero">0</span>' : (string) $count;
}

/**
 * The scan status line: the totals and when they were found, or "No scan has been run yet."
 *
 * @since Unreleased
 * @param array|null    $survey The last scan (bws_core_blocks_survey_last()), or null before the first.
 * @param array[]       $groups bws_core_blocks_picker_rows(); the totals are the sums over the rows in them.
 * @param array         $text   bws_core_blocks_picker_strings().
 * @param callable|null $format Time formatter (tests); defaults to the site's date and time format.
 * @return string Plain text.
 */
function bws_core_blocks_survey_status( ?array $survey, array $groups, array $text, ?callable $format = null ): string {
	if ( null === $survey ) {
		return $text['survey_none_yet'];
	}
	$known   = 0;
	$unknown = 0;
	foreach ( $groups as $group ) {
		foreach ( $group['rows'] as $row ) {
			$known   += (int) $row['registered'];
			$unknown += (int) $row['unregistered'];
		}
	}
	$when = $format ? $format( $survey['time'] ) : wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $survey['time'] );
	return sprintf( $text['survey_status'], $known, $unknown, $when );
}

/**
 * The rows the page lists, built from the saved settings, the server registry and the last scan.
 * The page and the scan's reply both ask it, so the status line's totals are over the same rows.
 *
 * @since Unreleased
 * @param array|null $survey The last scan, or null before the first.
 * @return array[] See bws_core_blocks_picker_rows().
 */
function bws_core_blocks_picker_groups( ?array $survey ): array {
	$saved = bws_core_blocks_clean( \BWS\DynamicTags\Admin\SettingsPage::get_settings()['core_blocks'] ?? array() );
	list( $entries, $categories ) = bws_core_blocks_picker_registry();
	return bws_core_blocks_picker_rows( $entries, $categories, $saved, bws_core_blocks_enabled(), bws_core_blocks_gb_native(), $survey['blocks'] ?? null );
}

/** Title fallback for a block with none: the slug after the namespace, capitalized. */
function bws_core_blocks_picker_slug_label( string $name ): string {
	$slug = false !== strpos( $name, '/' ) ? substr( $name, strpos( $name, '/' ) + 1 ) : $name;
	return ucwords( str_replace( '-', ' ', $slug ) );
}

/** Rows sorted by title, then name. */
function bws_core_blocks_picker_sorted( array $rows ): array {
	usort(
		$rows,
		static fn( array $a, array $b ): int => array( strtolower( $a['title'] ), $a['name'] ) <=> array( strtolower( $b['title'] ), $b['name'] )
	);
	return $rows;
}

/**
 * Gather the server block registry and its categories for the row builder. Misses blocks
 * registered only in editor JavaScript; the page says so.
 *
 * @since Unreleased
 * @return array{0: array[], 1: string[]} Entries, then category slug => label.
 */
function bws_core_blocks_picker_registry(): array {
	$entries = array();
	foreach ( \WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type ) {
		$variations = array();
		if ( method_exists( $type, 'get_variations' ) ) {
			foreach ( (array) $type->get_variations() as $v ) {
				$label = is_array( $v ) ? ( $v['title'] ?? $v['name'] ?? '' ) : '';
				if ( is_string( $label ) && '' !== $label ) {
					$variations[] = $label;
				}
			}
		}
		$entries[] = array(
			'name'       => (string) $name,
			'title'      => (string) ( $type->title ?? '' ),
			'category'   => (string) ( $type->category ?? '' ),
			'inserter'   => false !== ( $type->supports['inserter'] ?? true ),
			'variations' => $variations,
		);
	}

	$categories = array();
	if ( function_exists( 'get_block_categories' ) && class_exists( 'WP_Block_Editor_Context' ) ) {
		foreach ( get_block_categories( new \WP_Block_Editor_Context() ) as $cat ) {
			if ( ! empty( $cat['slug'] ) && ! empty( $cat['title'] ) ) {
				$categories[ $cat['slug'] ] = $cat['title'];
			}
		}
	}
	return array( $entries, $categories );
}
