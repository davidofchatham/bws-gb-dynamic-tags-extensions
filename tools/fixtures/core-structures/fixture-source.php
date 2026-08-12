<?php
/**
 * core-structures blueprint — the fixture chain-root source (CLASS route, #85).
 *
 * The in-repo half of the external-source contract (#80 §Testing Decisions): a source
 * registered through `bws_dynamic_tags_register_sources`, opted in as a chain root, so
 * the seam is exercised with no second plugin installed.
 *
 * IT RESOLVES FROM SEEDED CONTENT, NEVER FROM REQUEST STATE, and that is the whole
 * reason it exists rather than the real external source being used. The real one reads
 * request context (cookie / query param / session), which a CLI render and a seeded page
 * may or may not carry — a row that cannot state its own expected value asserts nothing.
 * This one answers the same id on every request, so every row has one.
 *
 * Loaded lazily (require_once from inside the registration callback in schema.php): the
 * class extends a parent that only exists when the plugin is active, and a top-level
 * declaration would fatal the site the moment the plugin is deactivated.
 *
 * @package BWS_Dynamic_Tags_Fixtures
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	exit;
}

/**
 * A chain root that resolves to the seeded `fixture-root` staff post.
 *
 * The target carries its own field values, its own relationship (`related_staff` → Tom,
 * where the matrix page's leads with Jane) and its own department term, so every read
 * through this root is DISTINGUISHABLE from the same read off the ambient post. A root
 * whose values matched the current post would pass whether it resolved or not.
 */
class BWS_Fixture_Root_Source extends \BWS\DynamicTags\AbstractSource {

	/** Post slug of the seeded entity this root resolves to. */
	const TARGET_SLUG = 'fixture-root';

	public function get_source_key(): string {
		return 'fixture';
	}

	/**
	 * The dropdown label. It NAMES ITS ROUTE on purpose — the two fixture roots are
	 * eyeballed side by side in the editor, and "which one is the class route" is the
	 * question those rows exist to answer.
	 */
	public function get_source_label(): string {
		return 'Fixture Root (class)';
	}

	/**
	 * No GB entity picker. The root resolves its own id; a post picker would paint a
	 * control that changes nothing (the same reason the real external source excludes it).
	 */
	public function get_excluded_supports(): array {
		return array( 'source' );
	}

	public function get_source_options(): array {
		return array();
	}

	/**
	 * Opt in as a chain root — the CLASS route's entire declaration (#83).
	 */
	public function is_selectable_root(): bool {
		return true;
	}

	/**
	 * The seeded target's id. Both routes share one lookup (see the helper's note on why
	 * a MISS is not cached), so the two differ in how they REGISTER and nowhere else.
	 *
	 * @param array  $options  Tag options (unused — this root takes none).
	 * @param object $instance Block instance (unused — resolution is not request-state).
	 * @return int|false
	 */
	public function resolve_id( array $options, $instance ) {
		return bws_fixture_seeded_post_id( self::TARGET_SLUG, 'staff' );
	}
}
