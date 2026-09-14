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

// PatternCache needs no import — same namespace (BWS\DynamicTags\Admin).
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
	 * EVERY DEPRECATED TAG CARRIES ITS VERDICT (FW-39, D46). A scan that only reported
	 * presence promised work on every row it printed, and two kinds of tag were quietly
	 * going to be left alone — one we must not rewrite and one we cannot express. Both now
	 * say so, through the two channels report_channels() assembles.
	 *
	 * @since 1.6.0
	 * @since 1.20.0 Deprecated tag rows carry status / reason / count / sample (FW-39).
	 * @return array[] {
	 *   @type int    $post_id              Post ID.
	 *   @type string $post_title           Post title (or "(no title)").
	 *   @type string $post_type            Post type slug.
	 *   @type string $edit_url             Edit link URL.
	 *   @type bool   $has_revision_support Whether wp_save_post_revision() can snapshot this post.
	 *   @type array  $deprecated_tags      One row per tag name PER VERDICT:
	 *                                      { tag, has_migration, status, reason, exempt, count, sample }.
	 *                                      See classify_tag() for the three statuses.
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

			// Deprecated tag names, each stored string CLASSIFIED into one of the report's
			// three channels — see classify_tag(). Grouped by name + verdict rather than by
			// name alone, because one post can hold two strings of the same name that fall
			// different ways (a pinned `term_text` converts, a bare one beside it is
			// skipped), and a single row per name would have to pick one and lose the other.
			$deprecated_found = array();
			foreach ( $tag_names as $tag ) {
				$pattern = '/\{\{' . preg_quote( $tag, '/' ) . '(?:\s[^}]*)?\}\}/';
				if ( ! preg_match_all( $pattern, $content, $tag_matches ) ) {
					continue;
				}

				$has_migration = MigrationRegistry::has_migration_path( $tag );

				foreach ( $tag_matches[0] as $stored ) {
					$verdict = self::classify_tag( $tag, $stored );
					$group   = $verdict['status'] . '|' . $verdict['reason'];

					if ( ! isset( $deprecated_found[ $group . '|' . $tag ] ) ) {
						$deprecated_found[ $group . '|' . $tag ] = array(
							'tag'           => $tag,
							'has_migration' => $has_migration,
							'status'        => $verdict['status'],
							'reason'        => $verdict['reason'],
							'exempt'        => $verdict['exempt'],
							'count'         => 0,
							'sample'        => $stored,
						);
					}

					++$deprecated_found[ $group . '|' . $tag ]['count'];
				}
			}
			$deprecated_found = array_values( $deprecated_found );

			// Option migrations.
			$option_migrations_found = array();
			foreach ( $option_migration_map as $base_tag => $entries ) {
				$tag_pattern = '/\{\{' . preg_quote( $base_tag, '/' ) . '(?:\s[^}]*)?\}\}/';
				preg_match_all( $tag_pattern, $content, $tag_matches );

				foreach ( $tag_matches[0] as $tag_string ) {
					[ , $options ] = MigrationRegistry::parse_tag_string( $tag_string );
					$option_keys   = array_keys( $options );

					foreach ( $entries as $entry ) {
						// Match rule owned by MigrationRegistry::entry_matches() — this
						// used to be a second, hand-kept copy of it. What the converter
						// REPORTS and what apply_option_migration() RUNS must be the same
						// predicate, or the list promises work that never happens.
						if ( MigrationRegistry::entry_matches( $entry, $option_keys, $options ) ) {
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
	// SCAN REPORT CHANNELS (FW-39, D46)
	// ===============================================

	/**
	 * Which of the report's three channels one stored tag string belongs to.
	 *
	 * THE ORDER IS THE DECISION, not a convenience. A shape the migration entry cannot
	 * express is SKIPPED, and that is settled before ownership is ever asked — the entry has
	 * already declined the shape, so asking the guard would put a second reason on one tag
	 * and file an informational outcome under a channel that offers an action.
	 * apply_if_owned() states the same ordering from the rewrite side.
	 *
	 * A TRANSFORM THAT CHANGES NOTHING IS NOT A DECLINE. There is no rewrite to refuse, so
	 * the row stays in the default channel exactly as it did before this method existed;
	 * only a real rewrite can be declined.
	 *
	 * `exempt` IS A DISCLOSURE RIDING A CONVERSION, NEVER A FOURTH STATUS. The tag converts;
	 * what the flag says is that the conversion is the one place this migration is not
	 * output-neutral (D40), which the report states as a line beside the conversion preview
	 * and not as a second gate. bws_modifier_unpinned_rewrite() owns the population.
	 *
	 * @since 1.20.0
	 * @param string $tag    Tag name as stored in post content.
	 * @param string $stored The matched tag string, exactly as content holds it.
	 * @return array{status:string, reason:string, exempt:bool} `status` is one of
	 *         'convert', 'declined' or 'skipped'; `reason` is a member of
	 *         BWS_MODIFIER_SKIP_REASONS or BWS_CONVERTER_OWNERSHIP_REASONS, or ''.
	 */
	private static function classify_tag( string $tag, string $stored ): array {
		$skip = function_exists( 'bws_modifier_skip_reason_for_tag' )
			? bws_modifier_skip_reason_for_tag( $stored )
			: '';

		if ( '' !== $skip ) {
			return array( 'status' => 'skipped', 'reason' => $skip, 'exempt' => false );
		}

		$exempt = function_exists( 'bws_modifier_unpinned_rewrite' )
			&& bws_modifier_unpinned_rewrite( $stored );

		$transformed = self::resolve_full_chain( $tag, $stored );
		if ( $transformed === $stored ) {
			return array( 'status' => 'convert', 'reason' => '', 'exempt' => false );
		}

		$decision = bws_converter_rewrite_allowed( $tag, $transformed );

		return $decision['rewrite']
			? array( 'status' => 'convert', 'reason' => '', 'exempt' => $exempt )
			: array( 'status' => 'declined', 'reason' => $decision['reason'], 'exempt' => false );
	}

	/**
	 * The two non-conversion channels, site-wide, plus the exemption disclosure.
	 *
	 * TWO CHANNELS AND NOT ONE LIST (D46). A DECLINE has an author action and gates a
	 * rewrite; a SKIP has neither and exists so an owner knows a shape was met and left
	 * alone. Merging them would make one census question apply to a set with two unrelated
	 * halves, and would put a second gate beside the opt-in — training click-through on the
	 * one control here that can damage content.
	 *
	 * SITE-WIDE RATHER THAN PER POST, because both facts are properties of a TAG NAME on
	 * this site and not of a post. "Which plugin wrote these strings" has one answer however
	 * many posts hold them, and an opt-in is a claim about the name; repeating either per row
	 * would ask the same question forty times.
	 *
	 * THE EXEMPTION IS A COUNT AND A LINE, NOT A GATE (D40). It rides the conversion channel
	 * — those tags convert — and the report states it once beside the preview.
	 *
	 * @since 1.20.0
	 * @param array[] $posts A scan() result.
	 * @return array{declined:array[], skipped:array[], exemptCount:int, optedIn:string[]}
	 */
	public static function report_channels( array $posts ): array {
		$channels = array( 'declined' => array(), 'skipped' => array() );
		$exempt   = 0;

		foreach ( $posts as $post ) {
			foreach ( $post['deprecated_tags'] ?? array() as $row ) {
				$count  = (int) ( $row['count'] ?? 0 );
				$status = (string) ( $row['status'] ?? '' );

				if ( ! empty( $row['exempt'] ) ) {
					$exempt += $count;
				}

				if ( ! isset( $channels[ $status ] ) ) {
					continue;
				}

				$key = $row['tag'] . '|' . $row['reason'];
				if ( ! isset( $channels[ $status ][ $key ] ) ) {
					$channels[ $status ][ $key ] = array(
						'tag'    => $row['tag'],
						'reason' => $row['reason'],
						'count'  => 0,
						// THE PREVIEW IS WHAT MAKES THE OPT-IN MEANINGFUL (D37): a claim of
						// ownership next to a count and nothing else asks an owner to
						// recognize strings they cannot see.
						'sample' => (string) ( $row['sample'] ?? '' ),
						'posts'  => 0,
					);
				}

				$channels[ $status ][ $key ]['count'] += $count;
				++$channels[ $status ][ $key ]['posts'];
			}
		}

		// PHP OWNS THE WORDING; THE SCRIPT ONLY PLACES IT. Same rule the pattern-cache line
		// rides on (PatternCache::format_status) and for the same reason: composing these
		// sentences in the browser would put a second copy of each channel's vocabulary in a
		// second language, untranslated, and outside the census that keeps them complete.
		$ownership = bws_converter_ownership_report_lines();
		$skips     = bws_modifier_skip_report_lines();

		$declined = array_values( array_map(
			static function ( array $row ) use ( $ownership ): array {
				$wording       = $ownership[ $row['reason'] ] ?? array( 'line' => '', 'action' => '' );
				$row['line']   = '' === $wording['line'] ? '' : sprintf(
					$wording['line'],
					$row['tag'],
					$row['count'],
					self::other_registrar_phrase( $row['tag'] )
				);
				$row['action'] = $wording['action'];
				return $row;
			},
			$channels['declined']
		) );

		$skipped = array_values( array_map(
			static function ( array $row ) use ( $skips ): array {
				$line        = $skips[ $row['reason'] ] ?? '';
				$row['line'] = '' === $line ? '' : sprintf( $line, $row['tag'], $row['count'] );
				return $row;
			},
			$channels['skipped']
		) );

		return array(
			'declined'    => $declined,
			'skipped'     => $skipped,
			'exemptCount' => $exempt,
			'exemptLine'  => $exempt > 0 ? sprintf(
				/* translators: %d: number of stored tag strings. */
				_n(
					'%d of these tags reads the current context only when that context is a term. After conversion it reads the current context whatever kind it is, so on a page that is not a term archive it will show content where it shows nothing today. This is the one place conversion changes what a page displays.',
					'%d of these tags read the current context only when that context is a term. After conversion they read the current context whatever kind it is, so on a page that is not a term archive they will show content where they show nothing today. This is the one place conversion changes what a page displays.',
					$exempt,
					'generateblocks'
				),
				$exempt
			) : '',
			'optedIn'     => array_values( (array) get_option( BWS_CONVERTER_OWNERSHIP_OPTIN_OPTION, array() ) ),
		);
	}

	/**
	 * The other plugin answering for a contested tag name, as a phrase, or a stand-in.
	 *
	 * Thin: bws_gb_collision_other_parties() decides WHICH party is the other one and
	 * bws_gb_other_registrar_phrase() words it — both already own their halves for the
	 * settings page's conflicts block. This only picks the record out by name, so the decline
	 * line names the same plugin that block does.
	 *
	 * @since 1.20.0
	 * @param string $tag Tag name as stored in post content.
	 * @return string An escaped phrase, or a translated stand-in when nothing is recorded.
	 */
	private static function other_registrar_phrase( string $tag ): string {
		$collisions = function_exists( 'bws_gb_tag_name_collisions' ) ? bws_gb_tag_name_collisions() : array();
		$record     = $collisions[ $tag ] ?? array();

		if ( ! $record || ! function_exists( 'bws_gb_collision_other_parties' ) ) {
			return __( 'another plugin', 'generateblocks' );
		}

		$parties = bws_gb_collision_other_parties( $record );
		$other   = $parties[ $parties['subject'] ] ?? array();

		return bws_gb_other_registrar_phrase( (string) ( $other['title'] ?? '' ), (string) ( $other['source'] ?? '' ) );
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
	 * 6. Fires bws_dynamic_tags_content_written so downstream caches over post content can
	 *    refresh — step 5 fires no save hooks, so nothing else is told (#99).
	 *
	 * STEPS 3 AND 4 ARE BOTH GATED, and that is not belt-and-braces. Step 4 rewrites BASE
	 * tag names, and a base name is as takeable as a deprecated one — whichever loop the
	 * next rewrite is added to, it goes through the guard, because the thing being protected
	 * is somebody's post content and not one migration entry.
	 *
	 * @since 1.6.0
	 * @since 1.20.0 Every rewrite passes bws_converter_rewrite_allowed() (FW-39).
	 * @param int $post_id Post ID to migrate.
	 * @return array {
	 *   @type bool      $changed       Whether post content was modified.
	 *   @type int       $tag_count     Deprecated tag replacements made.
	 *   @type int       $option_count  Option migration replacements made.
	 *   @type int|false $revision_id   Revision ID, or false if unsupported / not needed.
	 *   @type array     $declined      Tag name → ownership reason, for tags left alone. ONE
	 *                                  ENTRY PER NAME, not per match: the reason is a property
	 *                                  of the name on this site, so a post using a contested
	 *                                  tag forty times has one thing to say about it.
	 * }
	 */
	public static function migrate_post( int $post_id ): array {
		global $wpdb;

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'changed' => false, 'tag_count' => 0, 'option_count' => 0, 'revision_id' => false, 'declined' => array() );
		}

		$content = $post->post_content;

		// Step 2: Pre-migration snapshot.
		$revision_id = wp_save_post_revision( $post_id );

		// Both loops below hand their rewrite to apply_if_owned(), which is where the
		// ownership guard is asked — see bws_converter_tag_ownership() for the invariant,
		// and the $declined note on this method's return for why a name is reported once.
		$declined = array();

		// Step 3: Deprecated tag transforms.
		$tag_count = 0;
		foreach ( MigrationRegistry::get_deprecated_tag_names() as $old_tag ) {
			$pattern = '/\{\{' . preg_quote( $old_tag, '/' ) . '(\s[^}]*)?\}\}/';
			$content = preg_replace_callback(
				$pattern,
				static function ( array $matches ) use ( $old_tag, &$tag_count, &$declined ): string {
					return self::apply_if_owned(
						$old_tag,
						$matches[0],
						self::resolve_full_chain( $old_tag, $matches[0] ),
						$tag_count,
						$declined
					);
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
				static function ( array $matches ) use ( $base_tag, &$option_count, &$declined ): string {
					return self::apply_if_owned(
						$base_tag,
						$matches[0],
						MigrationRegistry::apply_option_migration( $base_tag, $matches[0] ),
						$option_count,
						$declined
					);
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

			/**
			 * Fires after post content is written WITHOUT firing WordPress's save hooks.
			 *
			 * The write above goes straight to the posts table on purpose — it avoids a
			 * duplicate revision, a bumped modified date, and every third-party save
			 * listener reacting to a maintenance task as though a human edited content.
			 * The cost is that nothing downstream is told, which is how GenerateBlocks
			 * Pro's pattern cache went stale (#99). This action publishes the FACT so a
			 * third-party cache over post content can refresh itself.
			 *
			 * It names the fact, not the cause, so a future direct write elsewhere in the
			 * plugin fires it truthfully.
			 *
			 * NO WP_Post IS PASSED, DELIBERATELY. The object in scope here holds
			 * PRE-migration content — it was fetched at the top of this method, before the
			 * rewrite — and clean_post_cache() has already run. A listener reaching for the
			 * obvious $post->post_content would receive exactly the stale wire this action
			 * exists to warn about. The ID plus the new string makes the fresh value the
			 * only value available.
			 *
			 * This plugin's own pattern-cache repair does NOT depend on this action: the
			 * reconcile is content-agnostic and site-wide (see PatternCache). This is
			 * integration surface, not the mechanism.
			 *
			 * @since 1.17.0
			 * @param int    $post_id Post whose content was rewritten.
			 * @param string $content The new post content, as written.
			 */
			do_action( 'bws_dynamic_tags_content_written', (int) $post_id, (string) $content );
		}

		return array(
			'changed'      => $changed,
			'tag_count'    => $tag_count,
			'option_count' => $option_count,
			'revision_id'  => $revision_id,
			'declined'     => $declined,
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

		// The manual forcing route for the pattern-cache reconcile (#99). Content-agnostic,
		// so it repairs whether or not the scan found anything — which is the case that
		// matters, since a site converted by an earlier run has clean content and a stale
		// cache and is therefore invisible to scan().
		$pattern_cache = PatternCache::reconcile_site( 'scan' );

		wp_send_json_success(
			array(
				'posts'             => $posts,
				'total'             => count( $posts ),
				'channels'          => self::report_channels( $posts ),
				'patternCache'      => $pattern_cache,
				'patternCacheLine'  => PatternCache::format_status( PatternCache::get_status() ),
			)
		);
	}

	/**
	 * AJAX: claim, or un-claim, one contested tag name as this site's own.
	 *
	 * POST fields:
	 *   nonce — bws_convert_tag nonce.
	 *   tag   — the tag name being claimed.
	 *   claim — "1" to claim, "0" to withdraw.
	 *
	 * THE ONLY WRITER OF THE OPT-IN SET (BWS_CONVERTER_OWNERSHIP_OPTIN_OPTION), and it lives
	 * beside the report because the count and the preview that justify the click are there.
	 * A claim is PER NAME: the answer for one contested name says nothing about the next, so
	 * there is no site-wide form and no "claim all".
	 *
	 * WITHDRAWAL IS AS AVAILABLE AS CLAIMING. A claim lifts the one guard standing between
	 * this tool and somebody else's content, so an owner who ticks it by mistake must be able
	 * to untick it before running a migration — not only after.
	 *
	 * THE NAME IS NOT VALIDATED AGAINST THE SCAN. A claim is about a tag name on this site,
	 * and re-deriving the report to check the name was in it would make the write depend on a
	 * second full scan; `manage_options` plus the nonce is the boundary, and the guard itself
	 * re-reads the option on every rewrite.
	 *
	 * @since 1.20.0
	 */
	public static function ajax_ownership_optin(): void {
		check_ajax_referer( 'bws_convert_tag', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'generateblocks' ) ), 403 );
		}

		$tag = sanitize_text_field( wp_unslash( $_POST['tag'] ?? '' ) );
		if ( '' === $tag ) {
			wp_send_json_error( array( 'message' => __( 'No tag name given.', 'generateblocks' ) ), 400 );
		}

		$claimed = array_values( array_unique( array_filter( array_map(
			'strval',
			(array) get_option( BWS_CONVERTER_OWNERSHIP_OPTIN_OPTION, array() )
		) ) ) );

		$claimed = '1' === (string) ( $_POST['claim'] ?? '' )
			? array_values( array_unique( array_merge( $claimed, array( $tag ) ) ) )
			: array_values( array_diff( $claimed, array( $tag ) ) );

		update_option( BWS_CONVERTER_OWNERSHIP_OPTIN_OPTION, $claimed );

		wp_send_json_success( array( 'optedIn' => $claimed ) );
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
		$is_final      = ! isset( $_POST['is_final'] ) || '1' === $_POST['is_final'];
		$pattern_cache = null;
		if ( $is_final ) {
			self::rebuild_allowlist();

			// Same "once at the end of a run" rhythm as the allowlist rebuild. It is
			// site-wide rather than limited to the posts in this batch, deliberately: the
			// reconcile is content-agnostic, so a pattern nobody migrated today still gets
			// repaired (#99).
			$pattern_cache = PatternCache::reconcile_site( 'migrate' );
		}

		wp_send_json_success( array(
			'results'          => $results,
			'processed'        => count( $results ),
			'patternCache'     => $pattern_cache,
			'patternCacheLine' => $is_final ? PatternCache::format_status( PatternCache::get_status() ) : '',
		) );
	}

	// ===============================================
	// PRIVATE HELPERS
	// ===============================================

	/**
	 * Accept one rewrite, or leave the stored string alone and record why.
	 *
	 * THE ONE PLACE THIS CLASS ASKS THE OWNERSHIP GUARD, and that is the point of it
	 * existing rather than the five lines sitting in each loop. What the guard protects is
	 * somebody's post content, so what has to be true is not "both loops call it" but "no
	 * loop can fail to" — a property a reader checks by counting rewrite loops against
	 * callers of this method, and one `converter-ownership-test.php` §O7 checks on every
	 * run. Two hand-kept copies could only ever be checked by reading them.
	 *
	 * A TRANSFORM THAT CHANGED NOTHING IS NOT A REWRITE and never reaches the guard. There
	 * is no content to damage and no decision to record: an entry that declined a shape has
	 * already said so through the skip channel, and reporting it again as an ownership
	 * decline would put a second reason on one tag.
	 *
	 * @since 1.20.0
	 * @param string $tag         Tag name as stored in post content.
	 * @param string $stored      The matched tag string, exactly as content holds it.
	 * @param string $transformed What the migration entry produced from it.
	 * @param int    $count       Running count of accepted rewrites, incremented in place.
	 * @param array  $declined    Tag name → reason, written in place. One entry per name.
	 * @return string The string to put back in the content.
	 */
	private static function apply_if_owned(
		string $tag,
		string $stored,
		string $transformed,
		int &$count,
		array &$declined
	): string {
		if ( $transformed === $stored ) {
			return $stored;
		}

		$decision = bws_converter_rewrite_allowed( $tag, $transformed );

		if ( ! $decision['rewrite'] ) {
			$declined[ $tag ] = $decision['reason'];
			return $stored;
		}

		++$count;

		return $transformed;
	}

	/**
	 * Resolve the full deprecated chain for a single tag match (max 10 hops).
	 *
	 * PUBLIC since 1.17.0 (#84) so a harness can drive the SHIPPED chaining rather than a
	 * copy of it. Transitive renames are asserted rather than built — an older prefix
	 * whose entry targets a still-registered modifier reaches the base tag in one run
	 * because this re-reads the tag name after each rewrite — and a test-local
	 * reimplementation of the loop would assert nothing about the converter. Pure string
	 * in, string out; no WP surface is touched.
	 *
	 * @since 1.6.0
	 * @since 1.17.0 Public (#84).
	 * @since 1.17.0 The name re-read goes through MigrationRegistry::parse_tag_string()
	 *               rather than a local pattern, so an option-less tag chains (#111).
	 * @param string $old_tag_name Starting deprecated tag name.
	 * @param string $tag_string   Full raw tag string.
	 * @return string Final migrated tag string.
	 */
	public static function resolve_full_chain( string $old_tag_name, string $tag_string ): string {
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

			// Re-READ the name from the rewritten string rather than trusting the entry's
			// declared `new_tag`: a transform_callback's result is returned verbatim
			// (MigrationRegistry::transform_tag), so what the content now says is the only
			// authoritative answer to what the next hop must match on.
			//
			// Through parse_tag_string(), which owns where a tag name ends. A local pattern
			// here stopped at whitespace, and a tag stating no options has none — so it ate
			// the closing braces, matched no entry, and stalled every option-less chain one
			// hop in (#111). Bare is the ordinary shape, so the stall was not exotic; what
			// made it rare, and what let it survive, is that it also needs one deprecated
			// name renaming to another. plugin-integration.md §9 promises that reaches the
			// base tag in one run, and spells its own example bare.
			// The pattern this replaced gave TWO guards free, and both are kept: a
			// non-empty name, and the `{{` anchor. parse_tag_string() strips braces only
			// if they are there, and transform_tag() matches on the NAME alone — so a
			// callback returning something that is no longer a tag string would otherwise
			// keep chaining, where the pattern stopped.
			[ $new_tag ] = MigrationRegistry::parse_tag_string( $string );

			if ( '' === $new_tag || ! str_starts_with( $string, '{{' ) ) {
				break;
			}

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
