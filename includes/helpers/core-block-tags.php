<?php
/**
 * Dynamic tags in core blocks (FW-156) — the enabled set.
 *
 * GenerateBlocks replaces dynamic tags only inside its own allowlisted blocks. The block
 * names a site opts in to are saved under `core_blocks` in the plugin's settings option;
 * this file owns reading them back as one list that every consumer shares.
 *
 * @package BWS_Dynamic_Tags
 * @since Unreleased
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reduce a raw value to a clean, de-duplicated list of `namespace/name` block names.
 *
 * Names are NOT checked against the block registry, so the selection of a plugin that is
 * deactivated today survives a save. Anything that is not a lowercase `namespace/name`
 * slug is dropped.
 *
 * @since Unreleased
 * @param mixed $raw Candidate list.
 * @return string[]
 */
function bws_core_blocks_clean( $raw ): array {
	$out = array();
	foreach ( is_array( $raw ) ? $raw : array() as $name ) {
		if ( ! is_string( $name ) ) {
			continue;
		}
		$name = trim( $name );
		if ( preg_match( '/^[a-z0-9][a-z0-9-]*\/[a-z0-9][a-z0-9-]*$/', $name ) ) {
			$out[] = $name;
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * The block names whose dynamic tags we enable: the saved list, passed through
 * `bws_dynamic_tags_core_blocks`, de-duplicated. Empty by default (off).
 *
 * The one owner of the set; every consumer reads this and none re-derives it. The saved
 * list is sanitized at save; the filter result is cleaned here because a filter is outside
 * input.
 *
 * @since Unreleased
 * @return string[] Block names, e.g. `core/paragraph`.
 */
function bws_core_blocks_enabled(): array {
	$saved = \BWS\DynamicTags\Admin\SettingsPage::get_settings()['core_blocks'] ?? array();

	/**
	 * Filters the block names whose dynamic tags are enabled.
	 *
	 * @since Unreleased
	 * @param string[] $blocks Saved block names (empty by default).
	 */
	$blocks = apply_filters( 'bws_dynamic_tags_core_blocks', is_array( $saved ) ? $saved : array() );

	return bws_core_blocks_clean( $blocks );
}

/**
 * `generateblocks_dynamic_tags_allowed_blocks` callback: append the enabled set to GB's list.
 *
 * Also widens GB's restricted-user save gate, which reads the same list. Only names with a
 * REGISTERED block type go through: an unregistered block gets no loop keys from
 * bws_core_blocks_add_loop_context(), so a tag in it inside a loop would read the page. The
 * enabled set itself stays unpruned, so a deactivated plugin's selection comes back with it.
 *
 * @since Unreleased
 * @param mixed $allowed GB's allowlisted block names.
 * @return string[]
 */
function bws_core_blocks_allow( $allowed ): array {
	$registry = \WP_Block_Type_Registry::get_instance();
	$enabled  = array_filter( bws_core_blocks_enabled(), array( $registry, 'is_registered' ) );
	return array_values( array_unique( array_merge( is_array( $allowed ) ? $allowed : array(), $enabled ) ) );
}

/**
 * GB's block-context keys a tag reads to see the loop item it sits in. Not a block's to
 * declare for itself: a core block does not use them unless we add them.
 *
 * @since Unreleased
 */
const BWS_CORE_BLOCKS_LOOP_CONTEXT = array(
	'generateblocks/loopItem',
	'generateblocks/loopIndex',
	'generateblocks/queryType',
	'generateblocks/queryId',
	'generateblocks/queryData',
	'postId',
	'postType',
);

/**
 * `init` pass: add GB's loop keys to each enabled block type's registered `uses_context`.
 *
 * Without them a core block never receives the loop item, so a row-reading tag in it would
 * silently read the page instead (spec invariant 2). Existing keys are kept in place, missing
 * ones appended; an unregistered name is skipped; an empty set writes nothing. Runs after every
 * block has registered, so it is one late write rather than a `register_block_type_args` hook.
 *
 * @since Unreleased
 */
function bws_core_blocks_add_loop_context(): void {
	$registry = \WP_Block_Type_Registry::get_instance();
	foreach ( bws_core_blocks_enabled() as $name ) {
		$type = $registry->get_registered( $name );
		if ( $type ) {
			$type->uses_context = array_values( array_unique( array_merge( (array) $type->uses_context, BWS_CORE_BLOCKS_LOOP_CONTEXT ) ) );
		}
	}
}
