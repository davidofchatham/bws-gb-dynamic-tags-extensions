<?php
/**
 * Tag migration converter for deprecated tags and option-key migrations.
 *
 * Provides:
 *   - scan()         Finds all posts (no revisions) containing any deprecated tag or
 *                    base tag with deprecated option keys, grouped by post.
 *   - migrate_post() Creates a WP revision (when supported), then applies all tag and
 *                    option migrations to a single post's content.
 *   - ajax_scan()    AJAX handler for the Scan button.
 *   - ajax_migrate() AJAX handler for per-post Migrate and paginated bulk Migrate.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.6.0
 * @since 1.6.0 Unified scan across tag and option migrations; per-post migrate with revision;
 *              paginated bulk AJAX; removed per-tag list/convert API.
 */

namespace BWS\DynamicTags\Admin;

use BWS\DynamicTags\MigrationRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TagConverter {

	// ===============================================
	// SCAN
	// ===============================================

	/**
	 * Scan all non-revision posts for deprecated tags and option migrations.
	 *
	 * Returns one result row per post. Each row includes the post's deprecated tag names
	 * and option migration labels found in the content, plus whether WP revision support
	 * is available for that post type.
	 *
	 * @since 1.6.0
	 * @return array[] {
	 *   @type int    $post_id              Post ID.
	 *   @type string $post_title           Post title (or "(no title)").
	 *   @type string $post_type            Post type slug.
	 *   @type string $edit_url             Edit link URL.
	 *   @type bool   $has_revision_support Whether wp_save_post_revision() can snapshot this post.
	 *   @type array  $deprecated_tags      List of { tag, has_migration } found in content.
	 *   @type array  $option_migrations    List of { tag, label } for base tags with deprecated option keys.
	 * }
	 */
	public static function scan(): array {
		global $wpdb;

		$tag_names        = MigrationRegistry::get_deprecated_tag_names();
		$option_migration_map = MigrationRegistry::get_option_migrations_by_tag();

		$all_scan_names = array_unique( array_merge( $tag_names, array_keys( $option_migration_map ) ) );

		if ( empty( $all_scan_names ) ) {
			return array();
		}

		$like_conditions = array();
		$like_values     = array();
		foreach ( $all_scan_names as $name ) {
			$like_conditions[] = 'post_content LIKE %s';
			$like_values[]     = '%' . $wpdb->esc_like( '{{' . $name ) . '%';
		}

		$where_likes = implode( ' OR ', $like_conditions );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT ID, post_title, post_type, post_content FROM {$wpdb->posts}
				 WHERE post_type != 'revision'
				 AND post_status NOT IN ('auto-draft', 'trash')
				 AND ({$where_likes})
				 ORDER BY post_title ASC",
				...$like_values
			)
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$revision_support_cache = array();
		$results                = array();

		foreach ( $rows as $row ) {
			$content = $row->post_content;

			// Deprecated tag names.
			$deprecated_found = array();
			foreach ( $tag_names as $tag ) {
				$pattern = '/\{\{' . preg_quote( $tag, '/' ) . '(?:\s[^}]*)?\}\}/';
				if ( preg_match( $pattern, $content ) ) {
					$deprecated_found[] = array(
						'tag'           => $tag,
						'has_migration' => MigrationRegistry::has_migration_path( $tag ),
					);
				}
			}

			// Option migrations.
			$option_migrations_found = array();
			foreach ( $option_migration_map as $base_tag => $entries ) {
				$tag_pattern = '/\{\{' . preg_quote( $base_tag, '/' ) . '(?:\s[^}]*)?\}\}/';
				preg_match_all( $tag_pattern, $content, $tag_matches );

				foreach ( $tag_matches[0] as $tag_string ) {
					[ , $options ] = MigrationRegistry::parse_tag_string( $tag_string );
					$option_keys   = array_keys( $options );

					foreach ( $entries as $entry ) {
						$required    = $entry['match_options'] ?? array();
						$any         = $entry['match_any_options'] ?? array();
						$all_present = ! empty( $required ) || ! empty( $any );
						foreach ( $required as $key ) {
							if ( ! in_array( $key, $option_keys, true ) ) {
								$all_present = false;
								break;
							}
						}
						if ( $all_present && ! empty( $any ) ) {
							$any_present = false;
							foreach ( $any as $key ) {
								if ( in_array( $key, $option_keys, true ) ) {
									$any_present = true;
									break;
								}
							}
							$all_present = $any_present;
						}
						if ( $all_present ) {
							$label           = $entry['label'] ?? $base_tag;
							$existing_labels = array_column( $option_migrations_found, 'label' );
							if ( ! in_array( $label, $existing_labels, true ) ) {
								$option_migrations_found[] = array(
									'tag'   => $base_tag,
									'label' => $label,
								);
							}
							break;
						}
					}
				}
			}

			if ( empty( $deprecated_found ) && empty( $option_migrations_found ) ) {
				continue; // LIKE false-positive.
			}

			$post_type = $row->post_type;
			if ( ! isset( $revision_support_cache[ $post_type ] ) ) {
				$revision_support_cache[ $post_type ] = post_type_supports( $post_type, 'revisions' )
					&& ( wp_revisions_to_keep( get_post( (int) $row->ID ) ) !== 0 );
			}

			$results[] = array(
				'post_id'              => (int) $row->ID,
				'post_title'           => $row->post_title ?: __( '(no title)', 'generateblocks' ),
				'post_type'            => $post_type,
				'edit_url'             => get_edit_post_link( (int) $row->ID, 'raw' ),
				'has_revision_support' => $revision_support_cache[ $post_type ],
				'deprecated_tags'      => $deprecated_found,
				'option_migrations'    => $option_migrations_found,
			);
		}

		return $results;
	}

	// ===============================================
	// SCAN ALLOWLIST (settings-page hide-when-unused)
	// ===============================================

	/** @var string Option name for the scan-derived allowlist. */
	const ALLOWLIST_OPTION_NAME = 'bws_dynamic_tags_scan_allowlist';

	/**
	 * Rebuild the scan allowlist from a fresh scan() pass.
	 *
	 * Called on activation, upgrade, and "Scan All Content" — anywhere a fresh
	 * scan() result isn't already in hand. Use rebuild_allowlist_from_scan()
	 * instead when a scan() result already exists (e.g. ajax_scan()'s own pass)
	 * to avoid scanning all content twice.
	 *
	 * @since 1.14.0
	 * @return array{tags: string[], option_labels: string[]} The rebuilt allowlist (also stored).
	 */
	public static function rebuild_allowlist(): array {
		return self::rebuild_allowlist_from_scan( self::scan() );
	}

	/**
	 * Rebuild the scan allowlist from an already-computed scan() result.
	 *
	 * Reduces scan() rows to the flat set of deprecated tag names and option
	 * migration labels actually found in content — the positive list the settings
	 * page uses to hide zero-match entries (V7). Also called after migrate_post()/
	 * bulk migrate (V7/V9 — a migrated post's old tag/option should drop off on
	 * the next rebuild).
	 *
	 * @since 1.14.0
	 * @param array[] $posts scan()'s return value.
	 * @return array{tags: string[], option_labels: string[]} The rebuilt allowlist (also stored).
	 */
	public static function rebuild_allowlist_from_scan( array $posts ): array {
		$tags          = array();
		$option_labels = array();

		foreach ( $posts as $row ) {
			foreach ( $row['deprecated_tags'] as $found ) {
				$tags[] = $found['tag'];
			}
			foreach ( $row['option_migrations'] as $found ) {
				$option_labels[] = $found['label'];
			}
		}

		$allowlist = array(
			'tags'          => array_values( array_unique( $tags ) ),
			'option_labels' => array_values( array_unique( $option_labels ) ),
		);

		update_option( self::ALLOWLIST_OPTION_NAME, $allowlist, false );

		return $allowlist;
	}

	/**
	 * Get the stored scan allowlist.
	 *
	 * Empty defaults (no scan run yet) hide everything until the first rebuild —
	 * matches V7's "positive list" semantics, not a denylist.
	 *
	 * @since 1.14.0
	 * @return array{tags: string[], option_labels: string[]}
	 */
	public static function get_allowlist(): array {
		$allowlist = get_option( self::ALLOWLIST_OPTION_NAME, array() );
		return array(
			'tags'          => $allowlist['tags'] ?? array(),
			'option_labels' => $allowlist['option_labels'] ?? array(),
		);
	}

	// ===============================================
	// PER-POST MIGRATE
	// ===============================================

	/**
	 * Migrate all deprecated tags and option migrations in a single post.
	 *
	 * 1. Reads current post content.
	 * 2. Calls wp_save_post_revision() — creates pre-migration snapshot when supported;
	 *    deduped by WP if content matches last revision.
	 * 3. Applies all deprecated tag transforms (full chain per match).
	 * 4. Applies all option migrations.
	 * 5. Writes directly to wp_posts if content changed (avoids hook side-effects and
	 *    duplicate revision from wp_update_post).
	 *
	 * @since 1.6.0
	 * @param int $post_id Post ID to migrate.
	 * @return array {
	 *   @type bool      $changed       Whether post content was modified.
	 *   @type int       $tag_count     Deprecated tag replacements made.
	 *   @type int       $option_count  Option migration replacements made.
	 *   @type int|false $revision_id   Revision ID, or false if unsupported / not needed.
	 * }
	 */
	public static function migrate_post( int $post_id ): array {
		global $wpdb;

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'changed' => false, 'tag_count' => 0, 'option_count' => 0, 'revision_id' => false );
		}

		$content = $post->post_content;

		// Step 2: Pre-migration snapshot.
		$revision_id = wp_save_post_revision( $post_id );

		// Step 3: Deprecated tag transforms.
		$tag_count = 0;
		foreach ( MigrationRegistry::get_deprecated_tag_names() as $old_tag ) {
			$pattern = '/\{\{' . preg_quote( $old_tag, '/' ) . '(\s[^}]*)?\}\}/';
			$content = preg_replace_callback(
				$pattern,
				static function ( array $matches ) use ( $old_tag, &$tag_count ): string {
					$transformed = self::resolve_full_chain( $old_tag, $matches[0] );
					if ( $transformed !== $matches[0] ) {
						++$tag_count;
					}
					return $transformed;
				},
				$content
			) ?? $content;
		}

		// Step 4: Option migrations.
		$option_count = 0;
		foreach ( MigrationRegistry::get_option_migrations_by_tag() as $base_tag => $entries ) {
			$pattern = '/\{\{' . preg_quote( $base_tag, '/' ) . '(\s[^}]*)?\}\}/';
			$content = preg_replace_callback(
				$pattern,
				static function ( array $matches ) use ( $base_tag, &$option_count ): string {
					$transformed = MigrationRegistry::apply_option_migration( $base_tag, $matches[0] );
					if ( $transformed !== $matches[0] ) {
						++$option_count;
					}
					return $transformed;
				},
				$content
			) ?? $content;
		}

		// Step 5: Write if changed.
		$changed = ( $content !== $post->post_content );
		if ( $changed ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->posts,
				array( 'post_content' => $content ),
				array( 'ID' => $post_id ),
				array( '%s' ),
				array( '%d' )
			);
			clean_post_cache( $post_id );
		}

		return array(
			'changed'      => $changed,
			'tag_count'    => $tag_count,
			'option_count' => $option_count,
			'revision_id'  => $revision_id,
		);
	}

	// ===============================================
	// AJAX HANDLERS
	// ===============================================

	/**
	 * AJAX: scan all posts for deprecated tags and option migrations.
	 *
	 * Returns JSON { success: true, data: { posts: [...], total: N } }
	 *
	 * @since 1.6.0
	 */
	public static function ajax_scan(): void {
		check_ajax_referer( 'bws_convert_tag', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'generateblocks' ) ), 403 );
		}

		$posts = self::scan();
		self::rebuild_allowlist_from_scan( $posts );
		wp_send_json_success( array( 'posts' => $posts, 'total' => count( $posts ) ) );
	}

	/**
	 * AJAX: migrate one or more posts (per-post or paginated bulk batch).
	 *
	 * POST fields:
	 *   nonce    — bws_convert_tag nonce.
	 *   post_ids — JSON-encoded array of post IDs to migrate in this batch.
	 *   is_final — "1" on the last batch of a bulk run (or a single-post migrate),
	 *              signalling the allowlist should be rebuilt now.
	 *
	 * The allowlist rebuild runs a full site scan, so it fires ONCE per bulk run
	 * (on the final batch) rather than once per batch — a 100-post/10-batch run
	 * would otherwise scan all content 10 times. A per-post migrate sends is_final
	 * on its single call, so it still rebuilds exactly once.
	 *
	 * Returns JSON { success: true, data: { results: [...], processed: N } }
	 * Each result: { post_id, changed, tag_count, option_count, has_revision }
	 *
	 * @since 1.6.0
	 * @since 1.14.0 Allowlist rebuild gated to the final batch via is_final.
	 */
	public static function ajax_migrate(): void {
		check_ajax_referer( 'bws_convert_tag', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'generateblocks' ) ), 403 );
		}

		$raw_ids  = wp_unslash( $_POST['post_ids'] ?? '[]' );
		$post_ids = json_decode( $raw_ids, true );

		if ( ! is_array( $post_ids ) || empty( $post_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No post IDs provided.', 'generateblocks' ) ), 400 );
		}

		$results = array();
		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id <= 0 ) {
				continue;
			}
			$result               = self::migrate_post( $post_id );
			$result['post_id']    = $post_id;
			$result['has_revision'] = ( false !== $result['revision_id'] );
			unset( $result['revision_id'] );
			$results[] = $result;
		}

		// Rebuild once at the end of a bulk run (or on a single-post migrate), not
		// per batch — the rebuild scans all content and would otherwise repeat per
		// batch. Absent is_final (older cached JS), fall back to rebuilding so the
		// allowlist never goes stale.
		$is_final = ! isset( $_POST['is_final'] ) || '1' === $_POST['is_final'];
		if ( $is_final ) {
			self::rebuild_allowlist();
		}

		wp_send_json_success( array(
			'results'   => $results,
			'processed' => count( $results ),
		) );
	}

	// ===============================================
	// PRIVATE HELPERS
	// ===============================================

	/**
	 * Resolve the full deprecated chain for a single tag match (max 10 hops).
	 *
	 * @since 1.6.0
	 * @param string $old_tag_name Starting deprecated tag name.
	 * @param string $tag_string   Full raw tag string.
	 * @return string Final migrated tag string.
	 */
	private static function resolve_full_chain( string $old_tag_name, string $tag_string ): string {
		$seen    = array();
		$current = $old_tag_name;
		$string  = $tag_string;
		$max     = 10;

		while ( $max-- > 0 ) {
			if ( in_array( $current, $seen, true ) ) {
				break;
			}
			$seen[] = $current;

			$transformed = MigrationRegistry::transform_tag( $current, $string );

			if ( $transformed === $string ) {
				break;
			}

			$string = $transformed;

			if ( ! preg_match( '/^\{\{(\S+)/', $string, $m ) ) {
				break;
			}
			$new_tag = $m[1];

			if ( $new_tag === $current ) {
				break;
			}
			$current = $new_tag;

			$probe = MigrationRegistry::transform_tag( $current, $string );
			if ( $probe === $string ) {
				break;
			}
		}

		return $string;
	}
}
