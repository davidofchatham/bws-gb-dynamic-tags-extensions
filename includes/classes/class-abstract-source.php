<?php
/**
 * Abstract base class for sources.
 *
 * Provides sensible defaults for all SourceInterface methods. Concrete sources
 * override only what differs from the defaults.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 * @since 1.2.0 Added defaults for tag prefix, context type, and related variant methods.
 * @since 1.2.0 Added format_id_for_acf(), source_default_enabled(), related_variant_default_enabled().
 * @since 1.4.1 Added tag_default_enabled().
 * @since 1.5.0 Removed related-variant method defaults; added needs_relationship_field(), get_ui_group().
 * @since 1.6.0 Removed get_title_prefix() and get_traversal_options().
 * @since 1.17.0 Added is_selectable_root() default (false) (#83).
 * @since 1.20.0 Added get_root_argument() default (none) + resolve_root_argument() default (FW-39).
 * @since Unreleased Removed the defaults for the ten methods SourceInterface no longer declares.
 */

namespace BWS\DynamicTags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractSource implements SourceInterface {

	public function get_context_type(): string {
		return 'post';
	}

	/**
	 * Not offerable as a chain root unless a source says otherwise (#83).
	 *
	 * FALSE is the default because the registry keeps its dead: the four in-repo
	 * traversal-substitute sources are inert by decision, and `post`/`term` would promote
	 * to roots that duplicate Current and collide with the planned specific-entity spelling.
	 * Opting in is a claim that this source resolves its own id from ambient context —
	 * see SourceInterface::is_selectable_root() for the full precondition.
	 *
	 * @since 1.17.0
	 * @return bool
	 */
	public function is_selectable_root(): bool {
		return false;
	}

	/**
	 * No root argument unless a source declares one (FW-39).
	 *
	 * NONE is the default for the same reason `false` is the default above: nearly every
	 * source resolves its own id from ambient context and has nothing for an author to
	 * name. A root that takes an argument is the exception, and it says so.
	 *
	 * @since 1.20.0
	 * @return array
	 */
	public function get_root_argument(): array {
		return array();
	}

	/**
	 * A source that declares no argument is never asked to resolve one (FW-39).
	 *
	 * FALSE rather than a fallback to resolve_id(), for the reason the seam exists: a
	 * declared-but-unimplemented argument would otherwise resolve the AMBIENT entity while
	 * the wire names a specific one, which is the degradation [I15] prohibits and the hardest
	 * kind to notice — the page renders something plausible. Refusing gives the author the
	 * blank a half-configured root is supposed to produce.
	 *
	 * @since 1.20.0
	 * @param string $arg      The root argument as authored.
	 * @param array  $options  Tag options.
	 * @param object $instance GB tag instance.
	 * @return int|string|false
	 */
	public function resolve_root_argument( string $arg, array $options, $instance ) {
		return false;
	}

}
