<?php
/**
 * Backward-compatibility facade over MigrationRegistry for deprecated tag entries.
 *
 * External plugins (e.g. bws-portal-system) call DeprecatedTagRegistry::register() to push
 * deprecated tag wrappers. This facade forwards all calls to MigrationRegistry with type:'tag'
 * so the registry is unified without breaking any existing callers.
 *
 * ## Usage (unchanged from before)
 *
 *     add_action( 'bws_dynamic_tags_register_sources', function () {
 *         \BWS\DynamicTags\DeprecatedTagRegistry::register( array(
 *             'old_tag'        => 'portal_post_meta',
 *             'new_tag'        => 'text',
 *             'title'          => 'Portal Post Meta',
 *             'supports'       => array( 'source' ),
 *             'options'        => portal_get_text_options(),
 *             'callback'       => 'portal_deprecated_post_meta_callback',
 *             'since'          => '2.0.0',
 *             'source_inject'  => '',
 *             'option_renames' => array( 'field_key' => 'key' ),
 *             'value_renames'  => array(),
 *             'fixed_options'  => array( 'use' => 'key' ),
 *         ) );
 *     } );
 *
 * @package BWS_Dynamic_Tags
 * @since 1.3.0
 * @since 1.6.0 Refactored to thin facade over MigrationRegistry.
 */

namespace BWS\DynamicTags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DeprecatedTagRegistry {

	/**
	 * Register a deprecated tag wrapper.
	 *
	 * Forwards to MigrationRegistry::register() with type:'tag'. All existing $args fields
	 * are supported unchanged. 'old_tag' maps to 'match_tag' automatically.
	 *
	 * @since 1.3.0
	 * @param array $args See MigrationRegistry::register() for full field documentation.
	 */
	public static function register( array $args ): void {
		$args['type'] = 'tag';
		MigrationRegistry::register( $args );
	}

	/**
	 * Get all registered deprecated tag entries (type:'tag' only).
	 *
	 * @since 1.3.0
	 * @return array[]
	 */
	public static function get_all(): array {
		return MigrationRegistry::get_by_type( 'tag' );
	}

	/**
	 * Check whether a deprecated tag has a registered migration path.
	 *
	 * @since 1.6.0
	 * @param string $old_tag Deprecated tag name.
	 * @return bool True when a migration entry with a non-empty new_tag exists.
	 */
	public static function has_migration_path( string $old_tag ): bool {
		return MigrationRegistry::has_migration_path( $old_tag );
	}

	/**
	 * Transform a deprecated tag string into the migrated format.
	 *
	 * @since 1.6.0
	 * @param string $old_tag_name Deprecated tag name.
	 * @param string $tag_string   Full raw tag string.
	 * @return string Migrated tag string, or original if no entry found.
	 */
	public static function transform_options( string $old_tag_name, string $tag_string ): string {
		return MigrationRegistry::transform_tag( $old_tag_name, $tag_string );
	}
}
