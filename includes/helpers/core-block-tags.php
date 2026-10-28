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
 * Whether a string is a well-formed `namespace/name` block slug (lowercase letters, digits and
 * hyphens on each side). The one owner of the shape; it says nothing about registration.
 *
 * @since Unreleased
 * @param mixed $name Candidate.
 * @return bool
 */
function bws_core_blocks_is_block_name( $name ): bool {
	return is_string( $name ) && 1 === preg_match( '/^[a-z0-9][a-z0-9-]*\/[a-z0-9][a-z0-9-]*$/', $name );
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
		$name = is_string( $name ) ? trim( $name ) : $name;
		if ( bws_core_blocks_is_block_name( $name ) ) {
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
 * Also widens GB's restricted-user save gate, which reads the same list. Only a name whose block
 * type is registered AND already carries every BWS_CORE_BLOCKS_LOOP_CONTEXT key goes through: a
 * block without them would, inside a loop, read the page instead of the loop item. That holds a
 * block registered after the loop-key pass (init:99), or one a filter added after it, back until
 * its tags print literally, rather than allowlisting it without loop context. The enabled set
 * itself stays unpruned, so a deactivated plugin's selection comes back with it.
 *
 * @since Unreleased
 * @param mixed $allowed GB's allowlisted block names.
 * @return string[]
 */
function bws_core_blocks_allow( $allowed ): array {
	$registry = \WP_Block_Type_Registry::get_instance();
	$enabled  = array_filter(
		bws_core_blocks_enabled(),
		static function ( $name ) use ( $registry ) {
			$type = $registry->get_registered( $name );
			return $type && ! array_diff( BWS_CORE_BLOCKS_LOOP_CONTEXT, (array) $type->uses_context );
		}
	);
	return array_values( array_unique( array_merge( is_array( $allowed ) ? $allowed : array(), $enabled ) ) );
}

/**
 * Whether a block belongs to GenerateBlocks itself, by namespace: `generateblocks/` (core) or
 * `generateblocks-pro/`. The one owner of that question; GB allowlists its own blocks already, so
 * the settings list leaves them out.
 *
 * @since Unreleased
 * @param string $name Block name.
 * @return bool
 */
function bws_core_blocks_is_gb_own( string $name ): bool {
	return 1 === preg_match( '#^generateblocks(-pro)?/#', $name );
}

/**
 * The block names GB allowlists WITHOUT our addition: its own plus whatever GB Pro and any other
 * plugin add through the same filter. The settings list leaves these out, since selecting one
 * changes nothing.
 *
 * Asked of GB itself (so no GB block name is stated here), with bws_core_blocks_allow() lifted
 * off the filter for the call and put back at its own priority. Empty when GB is absent.
 *
 * @since Unreleased
 * @return string[]
 */
function bws_core_blocks_gb_native(): array {
	if ( ! class_exists( 'GenerateBlocks_Dynamic_Tags' ) ) {
		return array();
	}
	$hook     = 'generateblocks_dynamic_tags_allowed_blocks';
	$priority = has_filter( $hook, 'bws_core_blocks_allow' );
	if ( false !== $priority ) {
		remove_filter( $hook, 'bws_core_blocks_allow', $priority );
	}
	$list = \GenerateBlocks_Dynamic_Tags::get_instance()->get_allowed_blocks();
	if ( false !== $priority ) {
		add_filter( $hook, 'bws_core_blocks_allow', $priority );
	}
	return array_values( array_filter( (array) $list, 'is_string' ) );
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
 * silently read the page instead, which no tag of ours may do inside a loop. Existing keys are kept in place, missing
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
