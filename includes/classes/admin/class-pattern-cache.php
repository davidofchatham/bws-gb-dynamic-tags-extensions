<?php
/**
 * GenerateBlocks Pro pattern-cache reconcile (#99).
 *
 * GB Pro keeps a copy of each block pattern's content in post meta, written once when the
 * pattern is saved and NEVER rebuilt on read. The Tag Converter writes migrated content
 * straight to the posts table (deliberately — see TagConverter::migrate_post()), which
 * fires no hooks, so the cached copy is never told. The pattern library's inserter then
 * goes on handing out pre-migration wire indefinitely, which re-seeds the old population
 * into fresh content while a second scan reports nothing left to do.
 *
 * This class reconciles the cached content against the post's real content and rewrites
 * ONLY the content field, leaving preview, scripts, styles, categories, global style
 * selectors and form references exactly as found. That field is the one whose correct
 * value is known exactly and independently — it IS the post's content, with no rendering,
 * no request context and no dependence on who is logged in. Rebuilding the whole entry was
 * measured on two production clones and rejected: it degrades previews (one fell from 8266
 * to 1232 bytes), silently empties script/style lists derived from those previews, evicts
 * revisions, bumps modified dates and fires every third-party save listener.
 *
 * CONTENT-AGNOSTIC, NOT MIGRATION-TRIGGERED. It compares cache to content and acts on
 * divergence; it does not gate on "a migration fired for this post in this run". That is
 * what makes it repair as well as prevention — a post migrated by an EARLIER run has
 * correct content, a stale cache, and no migration left to trigger on, and is invisible to
 * the scanner, which reads post content only.
 *
 * NO CAPABILITY CHECK LIVES IN THIS CLASS, and that is deliberate — do not "harden" it.
 * Gating happens at the callers, exactly as TagConverter::rebuild_allowlist() does: both
 * AJAX handlers verify manage_options first, and the upgrade pass relies on its own
 * admin/cron/CLI gate. A self-gate fails SILENTLY in the one place that matters: the
 * upgrade trigger can run under wp_doing_cron(), where there is no user, so
 * current_user_can() is false, the reconcile does nothing, and it reports checked: 0 —
 * which renders as "No pattern cache found." It would disable exactly the trigger that
 * repairs already-converted sites, on exactly the sites nobody is watching. Nothing is
 * traded away, because the reconcile can only ever write a value already equal to
 * post_content; it cannot introduce anything the user did not already have.
 *
 * MULTISITE: current site only. The plugin has no is_multisite()/switch_to_blog() anywhere
 * and this inherits that posture rather than deciding otherwise.
 *
 * COUPLING: to a meta key and a documented array shape, not to any GB Pro class. It cannot
 * fatal when GB Pro is absent, and has exactly one string to change if GB Pro renames the
 * field.
 *
 * Tests: tools/test/pattern-cache-test.php (pure decision logic) and
 * tools/fixtures/core-structures/verify-pattern-cache.php (the round trip through real
 * meta storage, where the escaping hazard lives). Neither substitutes for the other,
 * and the split is structural, not stylistic: the hazard lives inside WordPress's own
 * meta write (update_post_meta() calls wp_unslash(), which recurses through arrays), so
 * a pure harness cannot observe it at all — only a real meta round trip can.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.17.0
 */

namespace BWS\DynamicTags\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PatternCache {

	/** @var string GB Pro's per-pattern cache meta key. The single coupling point. */
	const META_KEY = 'generateblocks_patterns_tree';

	/** @var string Option holding the last reconcile summary (see record_status()). */
	const STATUS_OPTION_NAME = 'bws_dynamic_tags_pattern_cache_status';

	// ===============================================
	// PURE DECISION LOGIC
	// ===============================================

	/**
	 * The identifier GB Pro gives a pattern's cache entry.
	 *
	 * @since 1.17.0
	 * @param int $post_id Pattern post ID.
	 * @return string
	 */
	public static function entry_id( int $post_id ): string {
		return 'pattern-' . $post_id;
	}

	/**
	 * Whether this list member is the cache entry for a given identifier.
	 *
	 * Identity ONLY — it says nothing about whether the entry is well-formed enough to
	 * rewrite. The two questions are asked by different callers for different reasons and
	 * are kept apart on purpose: reconcile_tree() treats "mine but malformed" as a reason to
	 * abandon the whole tree, while tree_has_entry() treats it as a plain no.
	 *
	 * @since 1.17.0
	 * @param mixed  $entry List member of any shape.
	 * @param string $id    Result of entry_id().
	 * @return bool
	 */
	private static function is_entry_for( $entry, string $id ): bool {
		return is_array( $entry ) && ( $entry['id'] ?? null ) === $id;
	}

	/**
	 * Whether an entry carries a content field this class is willing to rewrite.
	 *
	 * @since 1.17.0
	 * @param array $entry A member is_entry_for() has already claimed.
	 * @return bool
	 */
	private static function entry_has_content( array $entry ): bool {
		return isset( $entry['pattern'] ) && is_string( $entry['pattern'] );
	}

	/**
	 * Decide what a pattern's cache tree should become.
	 *
	 * PURE — no WP surface touched. Matches the entry by its OWN identifier rather than by
	 * position: the live shape is a one-entry list, so a positional implementation passes
	 * every ordinary case and corrupts the unexpected one.
	 *
	 * THE COMPARISON IS A RAW `===`, with no slash normalisation. GB Pro writes
	 * wp_slash( $post_content ) into the entry and update_post_meta() then unslashes
	 * recursively, so the STORED value is byte-identical to post_content. An escaping-only
	 * difference is therefore a DIVERGENCE that repairs once and agrees thereafter, not
	 * agreement: normalising would declare a genuinely over-slashed entry (older GB Pro
	 * write, bad restore, earlier buggy pass) "agreeing" and leave it permanently
	 * unrepairable and invisible while the reconcile reports zero.
	 *
	 * @since 1.17.0
	 * @param mixed  $tree    Decoded cache value. Any shape; unrecognised ones return null.
	 * @param string $content The post's current content — the known-correct value.
	 * @param int    $post_id Pattern post ID.
	 * @return array|null The updated tree, or null meaning "nothing to write" (which covers
	 *                    both agreement and an unrecognised shape — see tree_has_entry()).
	 */
	public static function reconcile_tree( $tree, string $content, int $post_id ): ?array {
		if ( ! is_array( $tree ) ) {
			return null;
		}

		$id      = self::entry_id( $post_id );
		$changed = false;

		foreach ( $tree as $key => $entry ) {
			// A neighbour that is not a recognisable entry cannot match, so it passes
			// through verbatim. Bailing on the whole tree here would let one bad row
			// disable the repair for a pattern that is perfectly well-formed.
			if ( ! self::is_entry_for( $entry, $id ) ) {
				continue;
			}

			// The MATCHING entry being malformed is different: there is nowhere correct to
			// write, so the whole tree is left alone rather than repaired to a guess.
			if ( ! self::entry_has_content( $entry ) ) {
				return null;
			}

			if ( $entry['pattern'] === $content ) {
				continue;
			}

			$tree[ $key ]['pattern'] = $content;
			$changed                 = true;
		}

		return $changed ? $tree : null;
	}

	/**
	 * Whether a tree carries a well-formed entry for this post.
	 *
	 * The WRITE guard, and separate from the change decision on purpose. reconcile_tree()
	 * answers null for both "agrees" and "unrecognised", which is right for its own caller
	 * but cannot gate the duplicate-row convergence in reconcile_post(): converging means
	 * writing a value even though nothing changed, and that must never put an unrecognised
	 * shape over another plugin's rows.
	 *
	 * @since 1.17.0
	 * @param mixed $tree    Decoded cache value.
	 * @param int   $post_id Pattern post ID.
	 * @return bool
	 */
	public static function tree_has_entry( $tree, int $post_id ): bool {
		if ( ! is_array( $tree ) ) {
			return false;
		}

		$id = self::entry_id( $post_id );

		foreach ( $tree as $entry ) {
			if ( self::is_entry_for( $entry, $id ) && self::entry_has_content( $entry ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Render the stored summary as one line of user-facing text.
	 *
	 * TWO NUMBERS, not one. A bare reconciled-count structurally cannot answer the question
	 * the reporting exists for: 0 is identical for a healthy site with 37 consistent
	 * patterns and for a site where the meta key was never found. `checked` is the
	 * denominator that makes the claim falsifiable.
	 *
	 * `trigger` and `version` are STORED but never rendered — they are retroactivity, not
	 * display (see record_status()).
	 *
	 * @since 1.17.0
	 * @param array $status A record_status() shape, or an empty array when never run.
	 * @return string Plain text; the caller escapes. Empty string means "render nothing".
	 */
	public static function format_status( array $status ): string {
		if ( ! isset( $status['time'] ) ) {
			return '';
		}

		$checked    = (int) ( $status['checked'] ?? 0 );
		$reconciled = (int) ( $status['reconciled'] ?? 0 );

		// Honest for BOTH "GB Pro is not installed" and "no patterns exist yet" — it says
		// what was observed rather than claiming work that was not done.
		if ( $checked <= 0 ) {
			return __( 'No pattern cache found.', 'generateblocks' );
		}

		$when = wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			(int) $status['time']
		);

		if ( $reconciled <= 0 ) {
			return sprintf(
				/* translators: 1: date and time of the check, 2: number of patterns checked. */
				_n(
					'Pattern cache checked %1$s: %2$d pattern, all current.',
					'Pattern cache checked %1$s: %2$d patterns, all current.',
					$checked,
					'generateblocks'
				),
				$when,
				$checked
			);
		}

		return sprintf(
			/* translators: 1: date and time of the check, 2: patterns updated, 3: patterns checked. */
			__( 'Pattern cache checked %1$s: %2$d of %3$d patterns updated.', 'generateblocks' ),
			$when,
			$reconciled,
			$checked
		);
	}

	/**
	 * The wire pattern a removal report is keyed by.
	 *
	 * DELIBERATELY THE HARVEST DEFAULT, byte for byte. `fixtures/harvest/harvest-tags.php` in
	 * the ENV repo builds the census — and therefore every `tag_string` a replay artifact
	 * carries — with this expression, so a removal report written under a different one names
	 * strings that can never match. That failure is SAFE (an unmatched pair stays the hard
	 * failure it is today) and SILENT, which is why the report records the pattern it used and
	 * why `cleared_wire()` takes it as an argument: harvest accepts an override on the command
	 * line, so a run that used one has to be able to say so.
	 *
	 * @since 1.19.0
	 */
	const WIRE_PATTERN = '/\{\{[a-z0-9_]+(?:\s[^{}]*)?\}\}/i';

	/**
	 * Which tag strings stopped existing between two versions of one content string.
	 *
	 * PURE. The reconcile overwrites a cached copy of `post_content` with the current one, so
	 * anything the old copy held and the new content does not has left the site's renderable
	 * wire. Counting those was not enough: a migration replay reads each of them as a
	 * (url, tag) pair present on only one side — a hard failure that is not a render change —
	 * and only the strings themselves distinguish a repair from a disappearance. How often
	 * that happens on a real corpus is measured in `tools/harvest-replay/README.md`.
	 *
	 * REMOVALS ONLY, AND EXACT STRINGS. Wire the repair ADDED is not this question; and
	 * reporting a tag NAME where a whole tag string was removed would forgive nothing while
	 * looking like it had, because two configurations of one tag are two different pieces of
	 * wire and the replay keys on the whole string.
	 *
	 * @since 1.19.0
	 * @param string $before  The content as the cache held it.
	 * @param string $after   The content as it now is.
	 * @param string $pattern Wire pattern; see WIRE_PATTERN for why it is an argument.
	 * @return string[] Distinct tag strings present in $before and absent from $after.
	 * @throws \InvalidArgumentException If $pattern is not a usable expression — reporting an
	 *                                  empty set would read as "nothing was cleared", which is
	 *                                  the answer that silently turns every repair back into an
	 *                                  unexplained disappearance downstream.
	 */
	public static function cleared_wire( string $before, string $after, string $pattern = self::WIRE_PATTERN ): array {
		if ( false === strpos( $before, '{{' ) && $pattern === self::WIRE_PATTERN ) {
			return array();
		}

		$found = @preg_match_all( $pattern, $before, $m );

		if ( false === $found ) {
			throw new \InvalidArgumentException( 'cleared_wire(): unusable wire pattern ' . $pattern );
		}

		if ( ! $found ) {
			return array();
		}

		$gone = array();

		foreach ( array_unique( $m[0] ) as $tag ) {
			if ( false === strpos( $after, $tag ) ) {
				$gone[] = $tag;
			}
		}

		return array_values( $gone );
	}

	// ===============================================
	// SITE-WIDE RECONCILE
	// ===============================================

	/**
	 * Reconcile every cached pattern on the site.
	 *
	 * One query finds the work, so a site without GenerateBlocks Pro costs exactly that
	 * query and returns zero — the meta key simply is not there. Cost is otherwise
	 * proportional to the number of patterns, which is what lets this run on every upgrade
	 * without a performance review.
	 *
	 * Records the summary on EVERY run, including zero-count ones. Writing only on non-zero
	 * would make absence ambiguous again, which is this bug in miniature.
	 *
	 * TRASHED AND AUTO-DRAFT PATTERNS ARE SKIPPED, so "every pattern" means every one that is
	 * actually in the library. A trashed pattern is not offered by the inserter, so it cannot
	 * seed stale wire into anything; and it self-heals on the next run after it is restored,
	 * because the reconcile is content-agnostic rather than migration-triggered. The scanner
	 * excludes the same two statuses, so what the converter rewrites and what this repairs
	 * stay the same population.
	 *
	 * `cleared` IS RETURNED BUT NOT RECORDED. The stored status stays two counts and a
	 * timestamp: it is an option read on admin screens, and a per-string list on a large
	 * pattern library has no bound. The strings go to the caller that asked for them —
	 * `tools/harvest-replay/run-converter.php` writes them to a file beside its mapping — and
	 * a removal is deliberately NOT folded into `mapping.jsonl`, which means "this became
	 * that".
	 *
	 * @since 1.17.0
	 * @param string $trigger Untranslated slug: 'upgrade', 'scan' or 'migrate'.
	 * @return array{checked:int, reconciled:int, cleared:array<int,array{post_id:int,tag_string:string}>}
	 */
	public static function reconcile_site( string $trigger ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT DISTINCT p.ID, p.post_content
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
				 WHERE m.meta_key = %s
				   AND p.post_type = 'wp_block'
				   AND p.post_status NOT IN ('trash', 'auto-draft')",
				self::META_KEY
			)
		);

		$checked    = 0;
		$reconciled = 0;
		$cleared    = array();

		if ( ! empty( $rows ) ) {
			// Prime once rather than letting each get_post_meta() prime its own post.
			update_meta_cache( 'post', wp_list_pluck( $rows, 'ID' ) );

			foreach ( $rows as $row ) {
				++$checked;

				$gone = null;

				if ( self::reconcile_post( (int) $row->ID, (string) $row->post_content, $gone ) ) {
					++$reconciled;

					foreach ( (array) $gone as $tag ) {
						$cleared[] = array(
							'post_id'    => (int) $row->ID,
							'tag_string' => (string) $tag,
						);
					}
				}
			}
		}

		self::record_status( $checked, $reconciled, $trigger );

		return array(
			'checked'    => $checked,
			'reconciled' => $reconciled,
			'cleared'    => $cleared,
		);
	}

	/**
	 * Reconcile one pattern's cache against its content.
	 *
	 * THE ESCAPING DEFENCE IS THE wp_slash() ON WRITE. WordPress's meta layer calls
	 * wp_unslash() on the value being written and that strips slashes RECURSIVELY through
	 * arrays. GB Pro is unaffected because it slashes the content field and rebuilds every
	 * other field fresh each time, so nothing accumulates. A read-modify-write gets no such
	 * protection: each pass would strip one more level of backslashes from the preview and
	 * from every other string in the entry — silent, cumulative, and invisible until a
	 * preview containing escaped content breaks. Slashing the whole structure makes the
	 * platform's unslash a no-op. This is the property the integration seam pins.
	 *
	 * DUPLICATE META ROWS. update_post_meta() carries no previous-value constraint, so it
	 * writes every row holding the key; a pattern with several divergent copies therefore
	 * converges onto one value. That is an accepted side effect rather than a goal, and it
	 * is why a run can write when nothing "changed" — hence the tree_has_entry() guard,
	 * which keeps an unrecognised shape from being spread across the rows.
	 *
	 * THE DECISION IS TAKEN FROM THE FIRST ROW, and that is a choice rather than an oversight.
	 * get_post_meta( …, true ) returns the first row, so the first row is what every reader of
	 * this cache — GB Pro's REST layer included — actually gets. Deciding from it means the
	 * reconcile repairs what is being read. The consequence is worth stating: where row 0 is
	 * an unrecognised shape and a LATER row holds a well-formed entry, the pattern is skipped
	 * whole. That is the conservative answer, because the alternative is to promote a row
	 * nobody reads over the one everybody does; and the skip is not permanent, since it
	 * resolves the moment the unreadable row goes.
	 *
	 * WHAT `$cleared` IS FOR, and why it is an out-parameter rather than a wider return: the
	 * bool is what every caller acts on, and three of them do not care which strings moved.
	 * The one that does is the harvest/replay driver, which needs them because a migration
	 * replay reads each removed string as a (url, tag) pair present on only one side. It is
	 * reported only where a write actually happened — a run that changed nothing cleared
	 * nothing, and saying so with an empty array beats saying it with a null nobody checks.
	 *
	 * @since 1.17.0
	 * @param int        $post_id Pattern post ID.
	 * @param string     $content The post's current content.
	 * @param array|null $cleared Receives the tag strings this repair removed from the cached
	 *                            copy. Always set: an empty array where nothing left.
	 * @return bool Whether meta was written.
	 */
	public static function reconcile_post( int $post_id, string $content, ?array &$cleared = null ): bool {
		$cleared = array();
		$rows    = get_post_meta( $post_id, self::META_KEY, false );

		if ( empty( $rows ) || ! is_array( $rows ) ) {
			return false;
		}

		// Two distinct questions, so two names. `$repaired` is what the decision logic
		// returned — null meaning "no rewrite needed here". `$write` is the value that would
		// go to the database, which is the repaired tree where there is one and the row as
		// read where there is not.
		$repaired = self::reconcile_tree( $rows[0], $content, $post_id );
		$write    = $repaired ?? $rows[0];

		// A second, independent reason to write: the rows disagree with each other, so a
		// reader gets a different answer depending on which one it lands on.
		$copies_differ = false;
		foreach ( $rows as $row ) {
			if ( $row !== $write ) {
				$copies_differ = true;
				break;
			}
		}

		if ( null === $repaired && ! $copies_differ ) {
			return false;
		}

		if ( ! self::tree_has_entry( $write, $post_id ) ) {
			return false;
		}

		// READ THE OLD COPY BEFORE THE WRITE, and read it through the same entry matcher the
		// decision used — a positional read would name the wrong entry in exactly the tree
		// shape reconcile_tree() is careful about.
		$before = self::cached_pattern( $rows[0], $post_id );

		update_post_meta( $post_id, self::META_KEY, wp_slash( $write ) );

		if ( null !== $before ) {
			$cleared = self::cleared_wire( $before, $content );
		}

		return true;
	}

	/**
	 * The content this post's own cache entry was holding, or null if it has none.
	 *
	 * PURE, and matching by the entry's OWN identifier rather than by position for the reason
	 * reconcile_tree() gives: the live shape is a one-entry list, so a positional read passes
	 * every ordinary case and names the wrong entry in the unexpected one.
	 *
	 * @since 1.19.0
	 * @param mixed $tree    Decoded cache value.
	 * @param int   $post_id Pattern post ID.
	 * @return string|null
	 */
	public static function cached_pattern( $tree, int $post_id ): ?string {
		if ( ! is_array( $tree ) ) {
			return null;
		}

		$id = self::entry_id( $post_id );

		foreach ( $tree as $entry ) {
			if ( self::is_entry_for( $entry, $id ) && self::entry_has_content( $entry ) ) {
				return (string) $entry['pattern'];
			}
		}

		return null;
	}

	// ===============================================
	// REPORTED RESULT
	// ===============================================

	/**
	 * Store the summary of a reconcile run.
	 *
	 * PERSISTED rather than a transient status line, because the on-upgrade trigger has no
	 * interface at all and is the one actually repairing already-converted sites. A JS
	 * status line satisfies the reporting requirement for the two interactive triggers and
	 * fails it for the one that matters.
	 *
	 * `trigger` and `version` are raw untranslated slugs, stored but never rendered. The
	 * justification is RETROACTIVITY, not display: this plugin debugs through gated
	 * error_log() toggles, which must be enabled BEFORE the event, and the upgrade trigger
	 * fires once per version change and cannot be replayed. These two fields are the only
	 * way to answer "did the upgrade path ever actually run in the wild, or has pressing
	 * Scan been masking it" after the fact.
	 *
	 * @since 1.17.0
	 * @param int    $checked    Patterns examined.
	 * @param int    $reconciled Patterns written.
	 * @param string $trigger    Untranslated slug: 'upgrade', 'scan' or 'migrate'.
	 * @return array The stored status.
	 */
	public static function record_status( int $checked, int $reconciled, string $trigger ): array {
		$status = array(
			'checked'    => $checked,
			'reconciled' => $reconciled,
			'time'       => time(),
			'trigger'    => $trigger,
			'version'    => defined( 'BWS_DYNAMIC_TAGS_VERSION' ) ? BWS_DYNAMIC_TAGS_VERSION : '',
		);

		update_option( self::STATUS_OPTION_NAME, $status, false );

		return $status;
	}

	/**
	 * Get the stored summary.
	 *
	 * @since 1.17.0
	 * @return array A record_status() shape, or an empty array when no run has happened.
	 */
	public static function get_status(): array {
		$status = get_option( self::STATUS_OPTION_NAME, array() );
		return is_array( $status ) ? $status : array();
	}
}
