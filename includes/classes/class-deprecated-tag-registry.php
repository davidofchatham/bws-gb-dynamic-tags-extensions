<?php
/**
 * Registry for externally-registered deprecated tag wrappers.
 *
 * External plugins (e.g. bws-portal-system) push entries here so their
 * deprecated tag names are automatically registered with GenerateBlocks
 * and surface in the BWS settings page UI alongside the built-in deprecated tags.
 *
 * ## Usage
 *
 * Register your deprecated wrapper on the `bws_dynamic_tags_register_sources` action
 * (or any hook that fires before `init` priority 20):
 *
 *     add_action( 'bws_dynamic_tags_register_sources', function () {
 *         \BWS\DynamicTags\DeprecatedTagRegistry::register( array(
 *             'old_tag'    => 'portal_post_meta',
 *             'new_tag'    => 'portal_custom_text',
 *             'source_key' => 'portal',
 *             'title'      => 'Portal Post Meta',
 *             'gb_type'    => 'post',
 *             'supports'   => array( 'source' ),
 *             'options'    => portal_get_text_options(),
 *             'callback'   => 'portal_deprecated_post_meta_callback',
 *             'since'      => '2.0.0',
 *         ) );
 *     } );
 *
 * Your callback should emit a deprecation notice and delegate to the new implementation:
 *
 *     function portal_deprecated_post_meta_callback( $options, $block, $instance ) {
 *         bws_deprecated_tag_notice( 'portal_post_meta', 'portal_custom_text', '2.0.0' );
 *         $source = \BWS\DynamicTags\SourceRegistry::get_source( 'portal' );
 *         $id     = $source ? $source->resolve_id( $options, $instance ) : false;
 *         return portal_custom_text_core( $id, $options, $instance );
 *     }
 *
 * @package BWS_Dynamic_Tags
 * @since 1.3.0
 */

namespace BWS\DynamicTags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DeprecatedTagRegistry {

	/** @var array[] Registered deprecated tag entries. */
	private static array $entries = array();

	/**
	 * Register a deprecated tag wrapper.
	 *
	 * The `source_key` must match a source registered via `SourceRegistry::register_source()`
	 * for the deprecated tag to appear under the correct group in the settings UI. If no
	 * matching source exists, the entry is silently omitted from the UI but still registered
	 * with GenerateBlocks so existing content continues to render.
	 *
	 * @since 1.3.0
	 *
	 * @param array $args {
	 *     @type string          $old_tag     Required. Deprecated GB tag name.
	 *     @type string          $new_tag     Required. Replacement tag name (shown in notice).
	 *     @type string          $source_key  Required. Source group key for settings UI.
	 *     @type string          $title       GB tag title shown in the editor.
	 *     @type string          $gb_type     GB tag type. Defaults to `$source_key`. Use 'media' for
	 *                                       image tags (required for GB to show the media library controls).
	 *     @type array           $supports    GB supports array.
	 *     @type array           $options     GB options array (optional).
	 *     @type callable|string $callback    PHP callable that handles tag output.
	 *     @type bool            $is_related  Whether to list under related_tags in the UI. Default false.
	 *     @type string          $since       Version when old_tag was deprecated (for _doing_it_wrong notice).
	 *     @type string          $description Override the auto-generated GB tag description (optional).
	 * }
	 */
	public static function register( array $args ): void {
		self::$entries[] = $args;
	}

	/**
	 * Get all registered deprecated tag entries.
	 *
	 * @since 1.3.0
	 * @return array[]
	 */
	public static function get_all(): array {
		return self::$entries;
	}
}
