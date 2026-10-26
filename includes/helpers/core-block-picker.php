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
		'heading'            => __( 'Tags in Other Blocks', 'generateblocks' ),
		'intro'              => array(
			__( 'GenerateBlocks only fills in dynamic tags inside its own blocks. Select a block here to have tags typed into it filled in too, such as a `text` tag in a core Paragraph. With nothing selected, this is off, and tags in these blocks show as typed.', 'generateblocks' ),
			__( 'This applies to whole block types, not single blocks. Blocks added only by a plugin\'s editor script do not appear in this list.', 'generateblocks' ),
		),
		'search_placeholder' => __( 'Search blocks', 'generateblocks' ),
		/* translators: %d: number of selected blocks. */
		'count'              => __( '%d selected', 'generateblocks' ),
		'empty'              => __( 'No blocks match your search.', 'generateblocks' ),
		/* translators: %s: a short list of variation names. */
		'variations'         => __( 'Includes its variations, such as %s.', 'generateblocks' ),
		'inactive_heading'   => __( 'Not active on this site now', 'generateblocks' ),
		'unregistered'       => __( 'Saved earlier. Kept so it works again if its plugin comes back.', 'generateblocks' ),
		'code_set'           => __( 'Turned on by code on this site.', 'generateblocks' ),
		'dirty'              => __( 'Unsaved changes', 'generateblocks' ),
		'not_tested'         => __( 'Not tested. Tags should work in this block\'s text, but we have not checked it.', 'generateblocks' ),
		'list_hint'          => __( 'Put tags in the List Item blocks inside the list, not in the List itself.', 'generateblocks' ),
		'kind_inline'        => __( 'Holds text only. A `table` tag here prints nothing, which hides the whole block. A `content` tag here keeps its text, bold, italics and links, and drops paragraph breaks, headings, lists and images, so long content runs together.', 'generateblocks' ),
		'kind_table'         => __( 'Table cells hold text only. A `table` tag here prints nothing, which hides the whole table. A `content` tag here keeps its text, bold, italics and links, and drops paragraph breaks, headings, lists and images.', 'generateblocks' ),
		'kind_button'        => __( 'Holds text only. A `table` tag here prints nothing, which hides the whole button. A `content` tag keeps its text and drops everything else, links included. Tags that normally print a link, such as `email`, `phone`, or any tag set to *Link To*, print just the address, number or text instead, in both the button\'s text and its link.', 'generateblocks' ),
		'survey_hint'        => __( 'No scan has been run yet. Run one to see which of these blocks already hold tags.', 'generateblocks' ),
		'survey_scan'        => __( 'Scan content for tags', 'generateblocks' ),
		'survey_running'     => __( 'Scanning…', 'generateblocks' ),
		/* translators: %s: date and time of the last scan. */
		'survey_last'        => __( 'Last scan: %s.', 'generateblocks' ),
		'survey_error'       => __( 'The scan could not finish. Try again.', 'generateblocks' ),
		/* translators: %d: number of tags. */
		'row_registered'     => __( 'Registered tags found here by the last scan: %d.', 'generateblocks' ),
		/* translators: %d: number of strings. */
		'row_unregistered'   => __( 'Other `{{…}}` text found here, which is not a registered tag and stays as typed: %d.', 'generateblocks' ),
		'confirm_intro'      => __( 'These newly selected blocks already hold registered tags. Once you save, those tags are filled in on the site instead of showing as typed.', 'generateblocks' ),
		'confirm_save'       => __( 'Save anyway', 'generateblocks' ),
		'confirm_back'       => __( 'Go back', 'generateblocks' ),
		'notes'              => array(
			__( 'If a tag in one of these blocks finds no value, GenerateBlocks hides the whole block, not just the tag. To keep the block, add `required:false` to the tag, as in `{{text key:subtitle|required:false}}`.', 'generateblocks' ),
			__( 'People who are not allowed to post unfiltered HTML (on most single sites, anyone below Editor) cannot save a post that contains `{{` once it has any block type selected here. GenerateBlocks refuses the save. Selecting Paragraph therefore affects nearly every post they edit.', 'generateblocks' ),
			__( 'Each block type has to be selected on its own. Selecting a Group, Columns or List block does not include the blocks inside it, and tags in a core block inside a GenerateBlocks container are not filled in unless that core block is selected here.', 'generateblocks' ),
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
 * Visible: every entry, except one that hides itself from the inserter (`inserter` false) or
 * that GenerateBlocks already allowlists on its own (`$native`, where selecting changes
 * nothing) and is not in the enabled set. Names in the enabled set that no entry matches (a deactivated
 * plugin's block) get their own last group, selected, so a save keeps them. A name enabled only
 * by code (in `$enabled`, not in `$saved`) is selected and locked: an unchecked box and a
 * disabled one both post nothing, so the page never writes it into the saved list.
 *
 * Row keys: name, title, selected, disabled, variations (titles), registered and unregistered
 * (the last scan's tag counts for the block: ints once a scan exists, null before), search (lowercase haystack),
 * notes (keys into bws_core_blocks_picker_strings(): one consequence note, then `unregistered`
 * and `code_set` when they apply).
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
		if ( ( empty( $e['inserter'] ) || in_array( $e['name'], $native, true ) ) && ! in_array( $e['name'], $enabled, true ) ) {
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

/** One row. The consequence note asks the classification owner by block name. */
function bws_core_blocks_picker_row( string $name, string $title, string $category_label, array $variations, bool $registered, array $saved, array $enabled, ?array $survey = null ): array {
	$notes = array();
	if ( $registered ) {
		$notes[] = array(
			BWS_CORE_BLOCK_KIND_INLINE => 'kind_inline',
			BWS_CORE_BLOCK_KIND_TABLE  => 'kind_table',
			BWS_CORE_BLOCK_KIND_BUTTON => 'kind_button',
			BWS_CORE_BLOCK_KIND_LIST   => 'list_hint',
		)[ bws_core_block_kind( array( 'blockName' => $name ) ) ] ?? 'not_tested';
	} else {
		$notes[] = 'unregistered';
	}
	$selected = in_array( $name, $enabled, true );
	$code_set = $selected && ! in_array( $name, $saved, true );
	if ( $code_set ) {
		$notes[] = 'code_set';
	}
	return array(
		'name'       => $name,
		'title'      => $title,
		'selected'   => $selected,
		'disabled'   => $code_set,
		'variations' => array_values( array_map( 'strval', $variations ) ),
		'registered'   => null === $survey ? null : (int) ( $survey[ $name ]['registered'] ?? 0 ),
		'unregistered' => null === $survey ? null : (int) ( $survey[ $name ]['unregistered'] ?? 0 ),
		'search'     => strtolower( implode( ' ', array_merge( array( $title, $name, $category_label ), $variations ) ) ),
		'notes'      => $notes,
	);
}

/**
 * The scan-count lines of one row, as HTML (empty when there is nothing to say). Single source
 * for both the first render and the AJAX reply that refreshes a row.
 *
 * @since Unreleased
 * @param array|null $counts `registered` / `unregistered` ints, or null before any scan.
 * @param array      $text   bws_core_blocks_picker_strings().
 * @return string HTML.
 */
function bws_core_blocks_survey_row_html( ?array $counts, array $text ): string {
	$html = '';
	foreach ( array( 'registered' => 'row_registered', 'unregistered' => 'row_unregistered' ) as $key => $string ) {
		$n = (int) ( $counts[ $key ] ?? 0 );
		if ( $n > 0 ) {
			$html .= '<p class="description">' . bws_core_blocks_picker_markup( sprintf( $text[ $string ], $n ) ) . '</p>';
		}
	}
	return $html;
}

/**
 * The "Last scan" line.
 *
 * @since Unreleased
 * @param int           $time   Unix time of the scan.
 * @param array         $text   bws_core_blocks_picker_strings().
 * @param callable|null $format Time formatter (tests); defaults to the site's date and time format.
 * @return string Plain text.
 */
function bws_core_blocks_survey_status( int $time, array $text, ?callable $format = null ): string {
	$when = $format ? $format( $time ) : wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time );
	return sprintf( $text['survey_last'], $when );
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
