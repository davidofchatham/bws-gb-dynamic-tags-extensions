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
 * Also widens GB's restricted-user save gate, which reads the same list.
 *
 * @since Unreleased
 * @param mixed $allowed GB's allowlisted block names.
 * @return string[]
 */
function bws_core_blocks_allow( $allowed ): array {
	return array_values( array_unique( array_merge( is_array( $allowed ) ? $allowed : array(), bws_core_blocks_enabled() ) ) );
}
