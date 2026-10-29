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

	/** @var int How many posts a non-conversion report row names; the rest are counted. */
	private const FOUND_IN_LIMIT = 3;

	// ===============================================
	// SCAN
	// ===============================================

	/**
	 * Scan all non-revision posts, then every block widget, for deprecated tags and option migrations.
	 *
	 * Returns one result row per post, then one per block widget instance (scan_widgets(); the
	 * `kind` key tells them apart). Each row includes the deprecated tag names and option
	 * migration labels found in the content; a post row adds whether WP revision support is
	 * available for its post type. Reading only: nothing here writes a post or a widget.
	 *
	 * EVERY DEPRECATED TAG CARRIES ITS VERDICT (FW-39, D46). A scan that only reported
	 * presence promised work on every row it printed, and two kinds of tag were quietly
	 * going to be left alone — one we must not rewrite and one we cannot express. Both now
	 * say so, through the two channels report_channels() assembles.
	 *
	 * @since 1.6.0
	 * @since 1.20.0 Deprecated tag rows carry status / reason / count / sample (FW-39).
	 * @since Unreleased Widget rows follow the post rows; post rows carry `kind` (FW-73).
	 * @return array[] {
	 *   @type string $kind                 `post` here; `widget` on the rows scan_widgets() adds.
	 *   @type int    $post_id              Post ID.
	 *   @type string $post_title           Post title (or "(no title)").
	 *   @type string $post_type            Post type slug.
	 *   @type string $edit_url             Edit link URL.
	 *   @type bool   $has_revision_support Whether wp_save_post_revision() can snapshot this post.
	 *   @type array  $deprecated_tags      One row per tag name PER VERDICT:
	 *                                      { tag, has_migration, status, reason, exempt, count, sample }.
	 *                                      See classify_tag() for the three statuses.
	 *   @type array  $option_migrations    List of { tag, label } for base tags with deprecated option keys.
	 *   @type array  $locations            List of { label, stored } for each occurrence the row converts, label being block type and ordinal ("2nd core/paragraph"); empty when the occurrence is outside any block (FW-73).
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

		$revision_support_cache = array();
		$results                = array();

		foreach ( (array) $rows as $row ) {
			$found = self::scan_content( $row->post_content, $tag_names, $option_migration_map );
			if ( empty( $found['deprecated_tags'] ) && empty( $found['option_migrations'] ) ) {
				continue; // LIKE false-positive.
			}

			$post_type = $row->post_type;
			if ( ! isset( $revision_support_cache[ $post_type ] ) ) {
				$revision_support_cache[ $post_type ] = post_type_supports( $post_type, 'revisions' )
					&& ( wp_revisions_to_keep( get_post( (int) $row->ID ) ) !== 0 );
			}

			$results[] = array(
				'kind'                 => 'post',
				'post_id'              => (int) $row->ID,
				'post_title'           => $row->post_title ?: __( '(no title)', 'generateblocks' ),
				'post_type'            => $post_type,
				'edit_url'             => get_edit_post_link( (int) $row->ID, 'raw' ),
				'has_revision_support' => $revision_support_cache[ $post_type ],
				'deprecated_tags'      => $found['deprecated_tags'],
				'option_migrations'    => $found['option_migrations'],
				'locations'            => $found['locations'],
			);
		}

		// Block widgets (FW-73) follow the post rows. Their placement is read here, where
		// WordPress is, so scan_widgets() stays a pure function of what it is handed.
		$names = array();
		if ( function_exists( 'wp_get_sidebars_widgets' ) ) {
			global $wp_registered_sidebars;
			foreach ( bws_block_widget_placements( wp_get_sidebars_widgets() ) as $number => $sidebar ) {
				$names[ $number ] = (string) ( $wp_registered_sidebars[ $sidebar ]['name'] ?? $sidebar );
			}
		}

		return array_merge( $results, self::scan_widgets( bws_block_widget_rows(), $names ) );
	}

	/**
	 * One scan row per block widget instance that holds a convertible tag (FW-73).
	 *
	 * The row is a post row's twin: the same `deprecated_tags` / `option_migrations` keys, so
	 * rebuild_allowlist_from_scan() and report_channels() take it unchanged (a tag only a widget
	 * still uses must not drop off the settings page). What it does not carry is a post: there is
	 * no `post_id`, `post_type` or `has_revision_support` (options have no revisions, so there is
	 * no revision note to make), and `kind` is `widget`. Inactive instances are listed. The
	 * enumeration is bws_block_widget_rows(); this reads nothing and writes nothing.
	 *
	 * @since Unreleased
	 * @param array<int, string> $widgets Instance number => block markup.
	 * @param array<int, string> $names   Instance number => display name of the active sidebar holding it; absent means inactive.
	 * @return array[] {
	 *   @type string $kind              Always `widget`.
	 *   @type int    $widget_number     Instance number.
	 *   @type string $row_id            `widget:<number>`, the id the migrate path takes.
	 *   @type string $post_title        "Block widget #N".
	 *   @type string $edit_url          The Widgets screen.
	 *   @type string $placement         The sidebar's name, or "Inactive".
	 *   @type bool   $inactive          Whether no active sidebar holds it.
	 *   @type array  $deprecated_tags   As scan().
	 *   @type array  $option_migrations As scan().
	 *   @type array  $locations         As scan().
	 * }
	 */
	public static function scan_widgets( array $widgets, array $names ): array {
		$tag_names            = MigrationRegistry::get_deprecated_tag_names();
		$option_migration_map = MigrationRegistry::get_option_migrations_by_tag();

		$results = array();
		foreach ( $widgets as $number => $content ) {
			$found = self::scan_content( (string) $content, $tag_names, $option_migration_map );
			if ( empty( $found['deprecated_tags'] ) && empty( $found['option_migrations'] ) ) {
				continue;
			}

			$number    = (int) $number;
			$inactive  = ! isset( $names[ $number ] );
			$results[] = array(
				'kind'              => 'widget',
				'widget_number'     => $number,
				'row_id'            => 'widget:' . $number,
				/* translators: %d: block widget instance number. */
				'post_title'        => sprintf( __( 'Block widget #%d', 'generateblocks' ), $number ),
				'edit_url'          => admin_url( 'widgets.php' ),
				'placement'         => $inactive ? __( 'Inactive', 'generateblocks' ) : $names[ $number ],
				'inactive'          => $inactive,
				'deprecated_tags'   => $found['deprecated_tags'],
				'option_migrations' => $found['option_migrations'],
				'locations'         => $found['locations'],
			);
		}

		return $results;
	}

	/**
	 * The deprecated tags and option migrations one content string holds, as a scan row reports them.
	 *
	 * Shared by the post and widget rows, so the two cannot report differently.
	 *
	 * @since Unreleased
	 * @param string  $content              Post content or a widget's block markup.
	 * @param string[] $tag_names           MigrationRegistry::get_deprecated_tag_names().
	 * @param array   $option_migration_map MigrationRegistry::get_option_migrations_by_tag().
	 * @return array{deprecated_tags: array[], option_migrations: array[], locations: array[]} `locations` is a list of { label, stored }, label being "2nd core/paragraph".
	 */
	private static function scan_content( string $content, array $tag_names, array $option_migration_map ): array {
		// Deprecated tag names, each stored string CLASSIFIED into one of the report's
		// three channels — see classify_tag(). Grouped by name + verdict rather than by
		// name alone, because one post can hold two strings of the same name that fall
		// different ways (a `term_text` naming a term converts, a bare one beside it is
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

		// Option migrations.
		$option_migrations_found = array();
		foreach ( $option_migration_map as $base_tag => $entries ) {
			$tag_pattern = '/\{\{' . preg_quote( $base_tag, '/' ) . '(?:\s[^}]*)?\}\}/';
			preg_match_all( $tag_pattern, $content, $tag_matches );

			foreach ( $tag_matches[0] as $tag_string ) {
				$entry = self::first_matching_option_entry( $tag_string, $entries );
				if ( null !== $entry ) {
					$label           = $entry['label'] ?? $base_tag;
					$existing_labels = array_column( $option_migrations_found, 'label' );
					if ( ! in_array( $label, $existing_labels, true ) ) {
						$option_migrations_found[] = array(
							'tag'   => $base_tag,
							'label' => $label,
						);
					}
				}
			}
		}

		// WHERE TO LOOK (FW-73): one entry per occurrence this row will convert, read off the
		// located walk, so the block type and ordinal are the survey's and not a second reading.
		// A tag the walk cannot place (text outside any block, or a block's comment attributes)
		// has no entry; the row is listed all the same.
		$locations = array();
		$walk      = bws_core_blocks_survey_classifier();
		$classify  = static function ( string $tag, string $stored ) use ( $walk, $option_migration_map ): ?string {
			if ( null !== $walk && 'convert' === $walk( $tag, $stored ) ) {
				return 'convert';
			}
			return isset( $option_migration_map[ $tag ] ) && null !== self::first_matching_option_entry( $stored, $option_migration_map[ $tag ] ) ? 'convert' : null;
		};
		foreach ( bws_core_blocks_survey_walk( $content, static fn(): bool => true, array(), $classify ) as $record ) {
			if ( $record['convertible'] ) {
				$locations[] = array(
					'label'  => bws_ordinal_label( $record['ordinal'] ) . ' ' . $record['block_name'],
					'stored' => $record['stored'],
				);
			}
		}

		return array(
			'deprecated_tags'   => array_values( $deprecated_found ),
			'option_migrations' => $option_migrations_found,
			'locations'         => $locations,
		);
	}

	/**
	 * The first option-migration entry that matches one stored tag string, or null.
	 *
	 * Match rule owned by MigrationRegistry::entry_matches(); this used to be a second,
	 * hand-kept copy of it. What the converter REPORTS and what apply_option_migration() RUNS
	 * must be the same predicate, or the list promises work that never happens.
	 *
	 * @since Unreleased
	 * @param string  $tag_string The stored tag.
	 * @param array[] $entries    The option migrations registered for its tag.
	 * @return array|null
	 */
	private static function first_matching_option_entry( string $tag_string, array $entries ): ?array {
		[ , $options ] = MigrationRegistry::parse_tag_string( $tag_string );
		$option_keys   = array_keys( $options );

		foreach ( $entries as $entry ) {
			if ( MigrationRegistry::entry_matches( $entry, $option_keys, $options ) ) {
				return $entry;
			}
		}
		return null;
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
	 * and not as a second gate. bws_modifier_argless_rewrite() owns the population.
	 *
	 * PUBLIC SO THE LOCATED WALK CAN ASK IT (FW-73): the walk's Convertible flag is this
	 * method's `convert`, read through bws_core_blocks_survey_classifier(); nothing else
	 * decides it. Ask it only of a deprecated tag name, since a name no entry transforms
	 * also comes back `convert` (the no-op case above).
	 *
	 * @since 1.20.0
	 * @param string $tag    Tag name as stored in post content.
	 * @param string $stored The matched tag string, exactly as content holds it.
	 * @return array{status:string, reason:string, exempt:bool} `status` is one of
	 *         'convert', 'declined' or 'skipped'; `reason` is a member of
	 *         BWS_MODIFIER_SKIP_REASONS or BWS_CONVERTER_OWNERSHIP_REASONS, or ''.
	 */
	public static function classify_tag( string $tag, string $stored ): array {
		$skip = function_exists( 'bws_modifier_skip_reason_for_tag' )
			? bws_modifier_skip_reason_for_tag( $stored )
			: '';

		if ( '' !== $skip ) {
			return array( 'status' => 'skipped', 'reason' => $skip, 'exempt' => false );
		}

		$exempt = function_exists( 'bws_modifier_argless_rewrite' )
			&& bws_modifier_argless_rewrite( $stored );

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
	 * The wording around a report row's `found` list, so the script only places it.
	 *
	 * The stored-tag COUNT lives here and not in the row's sentence: a count inside a
	 * sentence cannot agree in number with its noun, and this is where one can.
	 *
	 * @param array $row A report row carrying `count`, `posts` and `found`.
	 * @return array The row plus `foundLabel`, `moreLabel` and `countLabel`.
	 */
	private static function found_in_wording( array $row ): array {
		$posts = (int) ( $row['posts'] ?? 0 );
		$more  = $posts - count( $row['found'] ?? array() );

		$row['foundLabel'] = __( 'Found in:', 'generateblocks' );
		$row['moreLabel']  = $more > 0 ? sprintf(
			/* translators: %d: number of further posts not listed. */
			__( 'and %d more', 'generateblocks' ),
			$more
		) : '';
		// Said only when it adds something: one stored tag per post is what "found in" already says.
		$row['countLabel'] = (int) $row['count'] > $posts ? sprintf(
			/* translators: %d: number of stored tag strings. */
			_n( '%d stored tag', '%d stored tags', (int) $row['count'], 'generateblocks' ),
			(int) $row['count']
		) : '';

		return $row;
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
	 * would ask the same question forty times. Each row still names the first few posts it
	 * was found in (`found`), because "where" is a different question from "whose".
	 *
	 * THE EXEMPTION IS A COUNT AND A LINE, NOT A GATE (D40). It rides the conversion channel
	 * — those tags convert — and the report states it once beside the preview.
	 *
	 * @since 1.20.0
	 * @param array[] $posts A scan() result.
	 * @return array{declined:array[], skipped:array[], exemptCount:int, optedIn:string[]} Each row also carries found (title and url, first few posts) and the three label strings from found_in_wording().
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
						'found'  => array(),
					);
				}

				$channels[ $status ][ $key ]['count'] += $count;
				++$channels[ $status ][ $key ]['posts'];

				// WHERE IT LIVES: the first few posts, so an owner can go and look. Site-wide
				// grouping stays; this only adds the instances the group was built from.
				if ( count( $channels[ $status ][ $key ]['found'] ) < self::FOUND_IN_LIMIT ) {
					$channels[ $status ][ $key ]['found'][] = array(
						'title' => (string) ( $post['post_title'] ?? '' ),
						'url'   => (string) ( $post['edit_url'] ?? '' ),
					);
				}
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
				return self::found_in_wording( $row );
			},
			$channels['declined']
		) );

		$skipped = array_values( array_map(
			static function ( array $row ) use ( $skips ): array {
				$line        = $skips[ $row['reason'] ] ?? '';
				$row['line'] = '' === $line ? '' : sprintf( $line, $row['tag'] );
				return self::found_in_wording( $row );
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
	 * Rewrite every deprecated tag and option migration in a content string.
	 *
	 * THE SHARED REWRITER: migrate_post() runs it over post content, and anything else that
	 * holds tag strings (a widget's stored block, FW-155's gated walk) calls the same
	 * function, so the two loops and the ownership guard have exactly one home. Nothing is
	 * read from or written to a post here — string in, string out — though the guard itself
	 * reads site state (the collision record and the per-name opt-in).
	 *
	 * 1. Deprecated tag transforms (full chain per match).
	 * 2. Option migrations.
	 *
	 * BOTH LOOPS ARE GATED, and that is not belt-and-braces. The second rewrites BASE tag
	 * names, and a base name is as takeable as a deprecated one — whichever loop the next
	 * rewrite is added to, it goes through the guard, because the thing being protected is
	 * somebody's content and not one migration entry. Both hand their rewrite to
	 * apply_if_owned(), which is where the guard is asked — see
	 * bws_converter_tag_ownership() for the invariant.
	 *
	 * @since Unreleased
	 * @param string $content Content holding tag strings.
	 * @return array {
	 *   @type string $content      The rewritten content (the input, when nothing applied).
	 *   @type int    $tag_count    Deprecated tag replacements made.
	 *   @type int    $option_count Option migration replacements made.
	 *   @type array  $declined     Tag name → ownership reason, for tags left alone. ONE
	 *                              ENTRY PER NAME, not per match: the reason is a property
	 *                              of the name on this site, so a string using a contested
	 *                              tag forty times has one thing to say about it.
	 * }
	 */
	public static function migrate_string( string $content ): array {
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

		return array(
			'content'      => $content,
			'tag_count'    => $tag_count,
			'option_count' => $option_count,
			'declined'     => $declined,
		);
	}

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
	 * Steps 3 and 4 are migrate_string(); the ownership guard lives there.
	 *
	 * @since 1.6.0
	 * @since 1.20.0 Every rewrite passes bws_converter_rewrite_allowed() (FW-39).
	 * @since Unreleased Steps 3 and 4 are migrate_string() (FW-73).
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

		// Steps 3 and 4: both rewrite loops, behind the ownership guard — see migrate_string().
		$rewrite      = self::migrate_string( $content );
		$content      = $rewrite['content'];
		$tag_count    = $rewrite['tag_count'];
		$option_count = $rewrite['option_count'];
		$declined     = $rewrite['declined'];

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
	// BLOCK WIDGETS (FW-73)
	// ===============================================

	/**
	 * The option that holds the last widget run's before/after backup. Not autoloaded.
	 *
	 * @since Unreleased
	 */
	public const WIDGET_BACKUP_OPTION = 'bws_widget_block_backup';

	/**
	 * Split the ids a migrate request carries into post ids and widget instance numbers.
	 *
	 * A widget row's id is the string `widget:<number>` (scan_widgets()'s `row_id`); a post row's is
	 * its integer ID. Anything else, and any non-positive number, is dropped.
	 *
	 * @since Unreleased
	 * @param array $raw Decoded `post_ids` field.
	 * @return array{posts: int[], widgets: int[]}
	 */
	public static function parse_row_ids( array $raw ): array {
		$out = array( 'posts' => array(), 'widgets' => array() );
		foreach ( $raw as $id ) {
			if ( is_string( $id ) && 1 === preg_match( '/^widget:(\d+)$/', $id, $m ) ) {
				if ( (int) $m[1] > 0 ) {
					$out['widgets'][] = (int) $m[1];
				}
			} elseif ( ( is_int( $id ) || ( is_string( $id ) && ctype_digit( $id ) ) ) && (int) $id > 0 ) {
				$out['posts'][] = (int) $id;
			}
		}
		return $out;
	}

	/**
	 * Rewrite the tags in a `widget_block` option value; pure.
	 *
	 * Only an instance's `content` is replaced, through migrate_string() (the same rewriter and
	 * ownership guard as post content). Every other key, `_multiwidget` included, comes back as it
	 * went in.
	 *
	 * @since Unreleased
	 * @param array      $option  The option value.
	 * @param int[]|null $numbers Instances to migrate; null for all.
	 * @return array {
	 *   @type array $option  The option value to store.
	 *   @type array $changed Number => { before, after }, for instances whose content moved.
	 *   @type array $results Number => { changed, tag_count, option_count, declined } per instance looked at.
	 * }
	 */
	public static function migrate_widget_option( array $option, ?array $numbers ): array {
		$changed = array();
		$results = array();
		foreach ( bws_block_widget_rows_of( $option ) as $number => $content ) {
			if ( null !== $numbers && ! in_array( $number, $numbers, true ) ) {
				continue;
			}
			$rewrite = self::migrate_string( $content );
			$moved   = $rewrite['content'] !== $content;
			if ( $moved ) {
				$option[ $number ]['content'] = $rewrite['content'];
				$changed[ $number ]           = array( 'before' => $content, 'after' => $rewrite['content'] );
			}
			$results[ $number ] = array(
				'changed'      => $moved,
				'tag_count'    => $rewrite['tag_count'],
				'option_count' => $rewrite['option_count'],
				'declined'     => $rewrite['declined'],
			);
		}
		return array( 'option' => $option, 'changed' => $changed, 'results' => $results );
	}

	/**
	 * Revert instances to their backed-up content; pure. THE RESTORE EQUALITY RULE.
	 *
	 * An instance reverts only while its content still equals what the migrate wrote (`after`).
	 * One that differs was edited since and is skipped as `edited`; one that is gone is skipped as
	 * `missing` and is not re-created. Only `content` is written, so nothing else on an instance moves.
	 *
	 * @since Unreleased
	 * @param array $option    The option value.
	 * @param array $instances Number => { before, after }, the backup's `instances`.
	 * @return array{option: array, restored: int[], skipped: array<int, string>}
	 */
	public static function restore_widget_option( array $option, array $instances ): array {
		$current  = bws_block_widget_rows_of( $option );
		$restored = array();
		$skipped  = array();
		foreach ( $instances as $number => $pair ) {
			if ( ! isset( $current[ $number ] ) ) {
				$skipped[ $number ] = 'missing';
			} elseif ( $current[ $number ] !== $pair['after'] ) {
				$skipped[ $number ] = 'edited';
			} else {
				$option[ $number ]['content'] = $pair['before'];
				$restored[]                   = $number;
			}
		}
		return array( 'option' => $option, 'restored' => $restored, 'skipped' => $skipped );
	}

	/**
	 * Migrate block widget instances: one fresh read, one write (spec decision 5).
	 *
	 * When anything changed, the backup is stored first and the widget option second, so a failure
	 * between the two leaves a backup of a write that did not happen rather than a write with no
	 * backup. A run that changes nothing writes nothing and leaves the
	 * previous backup alone. `$run_id` names a bulk run: a later batch carrying the same id adds to
	 * the backup, any other id replaces it. No hook fires beyond WordPress's own
	 * `update_option_widget_block`; `bws_dynamic_tags_content_written` stays post-only.
	 *
	 * @since Unreleased
	 * @param int[]|null $numbers Instances to migrate; null for all.
	 * @param string     $run_id  Identifier shared by every batch of one run.
	 * @return array<int, array> Number => { changed, tag_count, option_count, declined }.
	 */
	public static function migrate_widgets( ?array $numbers, string $run_id ): array {
		$option = get_option( 'widget_block', array() );
		$r      = self::migrate_widget_option( is_array( $option ) ? $option : array(), $numbers );

		if ( $r['changed'] ) {
			$backup = get_option( self::WIDGET_BACKUP_OPTION, array() );
			$kept   = ( is_array( $backup ) && ( $backup['run'] ?? null ) === $run_id ) ? (array) ( $backup['instances'] ?? array() ) : array();
			foreach ( $r['changed'] as $number => $pair ) {
				// A later batch of the same run keeps the ORIGINAL before.
				$kept[ $number ] = array( 'before' => $kept[ $number ]['before'] ?? $pair['before'], 'after' => $pair['after'] );
			}
			ksort( $kept );
			update_option( self::WIDGET_BACKUP_OPTION, array( 'date' => time(), 'run' => $run_id, 'instances' => $kept ), false );
			update_option( 'widget_block', $r['option'] );
		}

		return $r['results'];
	}

	/**
	 * Restore the backed-up block widgets, then drop the backup.
	 *
	 * @since Unreleased
	 * @return array{restored: int[], skipped: array<int, string>} Skipped is number => `edited`|`missing`.
	 */
	public static function restore_widgets(): array {
		$backup = get_option( self::WIDGET_BACKUP_OPTION, array() );
		if ( ! is_array( $backup ) || empty( $backup['instances'] ) ) {
			return array( 'restored' => array(), 'skipped' => array() );
		}

		$option = get_option( 'widget_block', array() );
		$r      = self::restore_widget_option( is_array( $option ) ? $option : array(), (array) $backup['instances'] );
		if ( $r['restored'] ) {
			update_option( 'widget_block', $r['option'] );
		}
		delete_option( self::WIDGET_BACKUP_OPTION );

		return array( 'restored' => $r['restored'], 'skipped' => $r['skipped'] );
	}

	/**
	 * The line shown beside the Restore button, or '' when no backup exists (and so no button).
	 *
	 * @since Unreleased
	 */
	public static function widget_backup_line(): string {
		$backup = get_option( self::WIDGET_BACKUP_OPTION, array() );
		if ( ! is_array( $backup ) || empty( $backup['instances'] ) ) {
			return '';
		}
		return sprintf(
			/* translators: 1: date and time, 2: number of block widgets. */
			_n( 'Block widgets were migrated on %1$s (%2$d widget backed up).', 'Block widgets were migrated on %1$s (%2$d widgets backed up).', count( $backup['instances'] ), 'generateblocks' ),
			wp_date( 'Y-m-d H:i', (int) ( $backup['date'] ?? 0 ) ),
			count( $backup['instances'] )
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
	 *   post_ids — JSON-encoded array of row ids to migrate in this batch: integer post IDs and
	 *              `widget:<number>` block widget ids (parse_row_ids()).
	 *   run_id   — names a bulk run, so its batches share one block widget backup.
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
	 * @since Unreleased Takes `widget:<number>` ids and a run_id (FW-73).
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

		$ids     = self::parse_row_ids( $post_ids );
		$results = array();
		foreach ( $ids['posts'] as $post_id ) {
			$result               = self::migrate_post( $post_id );
			$result['post_id']    = $post_id;
			$result['has_revision'] = ( false !== $result['revision_id'] );
			unset( $result['revision_id'] );
			$results[] = $result;
		}

		// Widgets: this request's instances in ONE read and ONE write (migrate_widgets()). The
		// client names the run so the batches of one bulk run share a backup.
		if ( $ids['widgets'] ) {
			$run_id = sanitize_text_field( wp_unslash( $_POST['run_id'] ?? '' ) );
			foreach ( self::migrate_widgets( $ids['widgets'], '' !== $run_id ? $run_id : uniqid( 'req', true ) ) as $number => $result ) {
				$results[] = array( 'post_id' => 'widget:' . $number, 'kind' => 'widget', 'has_revision' => false ) + $result;
			}
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
			'widgetBackupLine' => self::widget_backup_line(),
		) );
	}

	/**
	 * AJAX: restore the block widgets the last migrate run changed (FW-73).
	 *
	 * Returns JSON { restored: [N...], skipped: [ { number, label, reason } ] } where every skipped
	 * instance is named, because an instance edited since the run is the author's to look at.
	 *
	 * @since Unreleased
	 */
	public static function ajax_restore_widgets(): void {
		check_ajax_referer( 'bws_convert_tag', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'generateblocks' ) ), 403 );
		}

		$r       = self::restore_widgets();
		$skipped = array();
		foreach ( $r['skipped'] as $number => $reason ) {
			$skipped[] = array(
				'number' => $number,
				/* translators: %d: block widget instance number. */
				'label'  => sprintf( __( 'Block widget #%d', 'generateblocks' ), $number ),
				'reason' => $reason,
			);
		}

		self::rebuild_allowlist();

		wp_send_json_success( array( 'restored' => $r['restored'], 'skipped' => $skipped ) );
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
			// name renaming to another. deprecated-tags-options.md (§Modifier prefix → base
			// tag) says that reaches the base tag in one run, and spells its own rows bare.
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
