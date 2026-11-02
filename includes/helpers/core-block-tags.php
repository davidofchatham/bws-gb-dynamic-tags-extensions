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
 * The `bws_dynamic_tags_core_blocks` filter result, raw: the saved list passed through the
 * filter. The one place the filter runs; the enabled set and the declared kinds both read it.
 *
 * @since Unreleased
 * @return mixed The filter result (a filter is outside input, so not necessarily an array).
 */
function bws_core_blocks_filter_result() {
	$saved = \BWS\DynamicTags\Admin\SettingsPage::get_settings()['core_blocks'] ?? array();

	/**
	 * Filters the block names whose dynamic tags are enabled, and optionally what they hold.
	 *
	 * An entry is a bare name (`'acme/note'`: enabled, content kind unclassified) or
	 * `name => kind` (`'acme/note' => 'inline'`: enabled AND declared to hold that kind).
	 *
	 * @since Unreleased
	 * @param array<int|string, string> $blocks Saved block names (empty by default).
	 */
	return apply_filters( 'bws_dynamic_tags_core_blocks', is_array( $saved ) ? $saved : array() );
}

/**
 * Read a filter result as its two parts: the enabled names and the declared kinds.
 *
 * An integer key holds a bare name; a string key is the name and its value the kind. A name
 * must be a well-formed block slug or the whole entry is dropped. A kind that is not one of the
 * five known values is dropped to unclassified, leaving the name enabled. Whether a declared
 * kind applies to a given block is not decided here (see bws_core_block_kind()).
 *
 * @since Unreleased
 * @param mixed $raw A bws_core_blocks_filter_result().
 * @return array{names: string[], kinds: array<string, string>}
 */
function bws_core_blocks_parse_filter_result( $raw ): array {
	$known = array( BWS_CORE_BLOCK_KIND_INLINE, BWS_CORE_BLOCK_KIND_PREFORMATTED, BWS_CORE_BLOCK_KIND_LINK, BWS_CORE_BLOCK_KIND_CONTAINER, BWS_CORE_BLOCK_KIND_UNRESTRICTED );
	$names = array();
	$kinds = array();
	foreach ( is_array( $raw ) ? $raw : array() as $key => $value ) {
		$name = is_string( $key ) ? $key : $value;
		$name = is_string( $name ) ? trim( $name ) : $name;
		if ( ! bws_core_blocks_is_block_name( $name ) ) {
			continue;
		}
		$names[] = $name;
		if ( is_string( $key ) && in_array( $value, $known, true ) ) {
			$kinds[ $name ] = $value;
		}
	}
	return array( 'names' => array_values( array_unique( $names ) ), 'kinds' => $kinds );
}

/**
 * The block names whose dynamic tags we enable: the saved list, passed through
 * `bws_dynamic_tags_core_blocks`, de-duplicated. Empty by default (off).
 *
 * The one owner of the set; every consumer reads this and none re-derives it. The saved
 * list is sanitized at save; the filter result is cleaned here because a filter is outside
 * input. The result is always a flat list of names, whatever shape the filter returned.
 *
 * The filter value is a MIXED array: a bare name (`'acme/note'`) or `name => kind`
 * (`'acme/note' => 'inline'`, see bws_core_blocks_declared_kinds()). Adding works as ever,
 * including `array_merge( $blocks, array( 'acme/note' ) )`. REMOVING is the footgun: a mapped
 * entry's VALUE is its kind, not its name, so `array_diff()` / `in_array()` / `array_search()`
 * by value miss it. Remove by KEY (`unset( $blocks['acme/note'] )`), and a bare entry by its
 * value. The saved list stays a list of names; a declared kind is never written back.
 *
 * @since Unreleased
 * @return string[] Block names, e.g. `core/paragraph`.
 */
function bws_core_blocks_enabled(): array {
	return bws_core_blocks_parse_filter_result( bws_core_blocks_filter_result() )['names'];
}

/**
 * The content kinds code declared for blocks (`name => kind`) through `bws_dynamic_tags_core_blocks`.
 *
 * Read once per request and memoized, because bws_core_block_kind() asks it for every block on
 * every tag render. The memo is why a callback added after the first tag render is not seen
 * until the next request. Not filtered against the built-in map: bws_core_block_kind() consults
 * the map first, so a declaration for a built-in name is simply never reached.
 *
 * @since Unreleased
 * @param bool $reset Drop the memo instead of reading it (see bws_core_blocks_reset_declared_kinds()).
 * @return array<string, string> Block name => a BWS_CORE_BLOCK_KIND_* constant.
 */
function bws_core_blocks_declared_kinds( bool $reset = false ): array {
	static $memo = null;
	if ( $reset ) {
		$memo = null;
		return array();
	}
	if ( null === $memo ) {
		$memo = bws_core_blocks_parse_filter_result( bws_core_blocks_filter_result() )['kinds'];
	}
	return $memo;
}

/**
 * Forget the memoized declared kinds, so the next read runs the filter again. For harnesses that
 * change the filter between assertions.
 *
 * @since Unreleased
 */
function bws_core_blocks_reset_declared_kinds(): void {
	bws_core_blocks_declared_kinds( true );
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
