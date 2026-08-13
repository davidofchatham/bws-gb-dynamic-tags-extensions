<?php
/**
 * Unified migration registry for tag-name and option-key migrations.
 *
 * Two entry types:
 *   type:'tag'    — deprecated tag name → replacement tag name (old DeprecatedTagRegistry entries).
 *   type:'option' — current tag with deprecated option keys → corrected options (tag name unchanged).
 *
 * DeprecatedTagRegistry is a thin backward-compat facade over this class. External plugins that
 * call DeprecatedTagRegistry::register() continue to work without modification.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.6.0
 */

namespace BWS\DynamicTags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MigrationRegistry {

	/** @var array[] All registered migration entries. */
	private static array $entries = array();

	// ===============================================
	// REGISTRATION
	// ===============================================

	/**
	 * Register a migration entry.
	 *
	 * Shared fields (both types):
	 * @param array $args {
	 *   @type string  $type            Required. 'tag' or 'option'.
	 *   @type string  $match_tag       Required. Tag name to scan for in post content.
	 *   @type string  $new_tag         Target tag name. Same as match_tag for 'option' entries.
	 *   @type string  $source_inject   Value injected as 'src' option (prepended). '' = omit.
	 *   @type array   $option_renames  Map old option key → new option key.
	 *   @type array   $value_renames   Map (post-rename) option key → [ old_val => new_val ].
	 *   @type array   $combine_options Map new_key → [ 'when_present' => bool_key, 'value_from' => value_key ].
	 *                                   Combines a presence-flag key + a value key into one new key. Both old
	 *                                   keys are dropped after combination. Applied before option_renames.
	 *   @type array   $fixed_options   Key/value pairs always injected on conversion.
	 *   @type callable $transform_callback Whole-string fn( string $tag_string ): string.
	 *                                   When set, OVERRIDES the entire declarative pipeline
	 *                                   (both 'tag' and 'option' entries) — use for shape
	 *                                   changes the declarative steps can't express, e.g. a
	 *                                   value-conditional fold (as+size). Must return the
	 *                                   original string unchanged when there is nothing to do
	 *                                   (apply_option_migration loops until stable).
	 *   @type array   $required_options Option keys (post-rename) required for the migrated
	 *                                   tag to reproduce the deprecated tag's default behavior.
	 *                                   Display-only metadata for admin migration preview;
	 *                                   does not affect transform pipeline.
	 *   @type bool    $datetime_transforms When true, apply datetime special-case transforms.
	 *   @type array   $datetime_era_options Option keys whose presence PROVES the tag was authored
	 *                                   before the 1.6 datetime rename. Read only by the datetime
	 *                                   transforms, and only on 'option' entries — a 'tag' entry
	 *                                   matches a pre-1.6 tag NAME, which proves era by itself.
	 *                                   Needed because the two inverted booleans are injected on
	 *                                   ABSENCE, so the transform must be able to tell legacy wire
	 *                                   with the key unchecked from modern wire that never had it.
	 *                                   NOT the same list as `match_any_options`: a key that also
	 *                                   appears on modern wire (`fallback_text`, renamed on every
	 *                                   base tag) triggers the entry but proves nothing about era.
	 *
	 *   'tag' type only:
	 *   @type string  $old_tag         Alias for match_tag (DeprecatedTagRegistry backward compat).
	 *   @type string  $title           GB tag title shown in editor.
	 *   @type array   $supports        GB supports array.
	 *   @type array   $options         GB options array.
	 *   @type callable $callback       PHP callback for GB tag execution.
	 *   @type string  $since           Plugin version when tag was deprecated.
	 *   @type string  $description     GB tag description (auto-generated if omitted).
	 *   @type string  $gb_type         Always overwritten to 'deprecated' for 'tag' entries.
	 *   @type bool    $prefix_removed  Hand-set. True once the external plugin that owns this
	 *                                   alias retires its prefix generation — moves the entry
	 *                                   from the Deprecated Tags box to Removed Tags (V9/V10).
	 *                                   Never inferred; the owning plugin sets it. Default
	 *                                   absent (still Deprecated). Interim mechanism pending
	 *                                   the explicit `lifecycle` field (V11 / FW-38).
	 *
	 *   'option' type only:
	 *   @type array   $match_options       Option keys that must ALL be present in the tag string to trigger.
	 *   @type array   $match_any_options   Option keys where ANY presence triggers the entry. Combined
	 *                                       with `match_options` via AND (both checks must pass). Use this
	 *                                       when multiple old keys signal the same deprecated state and
	 *                                       only one needs to be present to warrant migration.
	 *   @type string  $label               Short description shown in admin UI (e.g. 'rel → ref fix').
	 *   @type bool    $legacy_fallback_removed Hand-set. True once the runtime's legacy-key
	 *                                       fallback (e.g. `$options['old_key'] ?? $options['new_key']`)
	 *                                       is deleted from the reading code — the migration is then the
	 *                                       only path back, not an active safety net. Never inferred from
	 *                                       scan results; an author sets this by hand when the fallback
	 *                                       code is actually removed. Default false (fallback still live).
	 * }
	 */
	public static function register( array $args ): void {
		$type = $args['type'] ?? 'tag';

		// Support DeprecatedTagRegistry API: 'old_tag' as alias for 'match_tag'.
		if ( isset( $args['old_tag'] ) && ! isset( $args['match_tag'] ) ) {
			$args['match_tag'] = $args['old_tag'];
		}

		if ( 'tag' === $type ) {
			$args['gb_type'] = 'deprecated';
		}

		$args['type']     = $type;
		self::$entries[]  = $args;
	}

	// ===============================================
	// RETRIEVAL
	// ===============================================

	/**
	 * Get all registered migration entries.
	 *
	 * @return array[]
	 */
	public static function get_all(): array {
		return self::$entries;
	}

	/**
	 * Get entries filtered by type.
	 *
	 * @param string $type 'tag' or 'option'.
	 * @return array[]
	 */
	public static function get_by_type( string $type ): array {
		return array_values(
			array_filter( self::$entries, fn( $e ) => ( $e['type'] ?? 'tag' ) === $type )
		);
	}

	/**
	 * Whether an entry is still "live" (Deprecated) vs "removed" (inert, migration-data-only).
	 *
	 * Hand-set per entry, never inferred (SPEC B1/V9):
	 *   'tag'    — Removed iff a hand-set `prefix_removed` flag is true; otherwise falls back
	 *              to the callback-presence default. The `prefix_removed` override exists
	 *              because the two tag populations have OPPOSITE natural defaults: our own
	 *              N×M entries are Removed (FW-1 stripped their `callback`) while external
	 *              context-modifier aliases are Deprecated (they carry a `callback`). A
	 *              single flag with one global default would split them wrong. So today the
	 *              callback-presence test remains the default "internal-removed vs
	 *              external-still-registered" marker, and `prefix_removed` is the retirement
	 *              axis an external plugin sets to push a specific alias generation to
	 *              Removed (V10). NOTE: callback-presence here is an interim proxy, NOT a
	 *              render guarantee — post-FW-1 nothing dispatches these callbacks to GB.
	 *              A future release (V11 / FW-38) replaces it with explicit `registered_by`
	 *              + `lifecycle` fields.
	 *   'option' — live when `legacy_fallback_removed` is NOT true (the runtime code still
	 *              accepts the old option key as a fallback; false/absent = still live).
	 *
	 * @since 1.14.0
	 * @since 1.14.0 B1: tag branch adds a `prefix_removed` override above the callback default.
	 * @param array $entry Registry entry.
	 * @return bool
	 */
	public static function is_entry_live( array $entry ): bool {
		if ( 'option' === ( $entry['type'] ?? 'tag' ) ) {
			return empty( $entry['legacy_fallback_removed'] );
		}
		if ( ! empty( $entry['prefix_removed'] ) ) {
			return false;
		}
		return isset( $entry['callback'] ) && is_callable( $entry['callback'] );
	}

	/**
	 * Get entries filtered by type AND liveness.
	 *
	 * @since 1.14.0
	 * @param string $type  'tag' or 'option'.
	 * @param bool   $live  True for Deprecated (still live), false for Removed (inert).
	 * @return array[]
	 */
	public static function get_by_type_and_liveness( string $type, bool $live ): array {
		return array_values(
			array_filter(
				self::get_by_type( $type ),
				fn( $e ) => self::is_entry_live( $e ) === $live
			)
		);
	}

	// ===============================================
	// TAG-TYPE METHODS
	// ===============================================

	/**
	 * Check whether a deprecated tag has a registered migration path.
	 *
	 * @param string $old_tag Deprecated tag name.
	 * @return bool True when a 'tag' entry with a non-empty new_tag exists.
	 */
	public static function has_migration_path( string $old_tag ): bool {
		foreach ( self::$entries as $entry ) {
			if ( 'tag' !== ( $entry['type'] ?? 'tag' ) ) {
				continue;
			}
			if ( ( $entry['match_tag'] ?? '' ) === $old_tag ) {
				return ! empty( $entry['new_tag'] );
			}
		}
		return false;
	}

	/**
	 * Transform a deprecated tag string into the migrated format.
	 *
	 * When the entry has a 'transform_callback' callable, delegates to it entirely.
	 * Otherwise applies the full transform pipeline: parse → option_renames → value_renames
	 * → datetime_transforms → source_inject (prepend) → fixed_options → serialize.
	 *
	 * Returns the original string unchanged when no 'tag' entry matches.
	 *
	 * @param string $old_tag_name Deprecated tag name.
	 * @param string $tag_string   Full raw tag string (e.g. `{{old_tag rel:X|key:Y}}`).
	 * @return string Migrated tag string.
	 */
	public static function transform_tag( string $old_tag_name, string $tag_string ): string {
		$entry = null;
		foreach ( self::$entries as $e ) {
			if ( 'tag' !== ( $e['type'] ?? 'tag' ) ) {
				continue;
			}
			if ( ( $e['match_tag'] ?? '' ) === $old_tag_name ) {
				$entry = $e;
				break;
			}
		}

		if ( null === $entry ) {
			return $tag_string;
		}

		if ( isset( $entry['transform_callback'] ) && is_callable( $entry['transform_callback'] ) ) {
			return ( $entry['transform_callback'] )( $tag_string );
		}

		return self::run_transform( $entry, $tag_string );
	}

	// ===============================================
	// OPTION-TYPE METHODS
	// ===============================================

	/**
	 * THE match rule for an 'option' entry — does this entry apply to these options?
	 *
	 * Single owner, because two places need the same answer and used to each compute it:
	 * find_option_migrations() (which decides what RUNS) and the admin scanner's
	 * per-tag-string detection (which decides what the converter REPORTS). Two copies of
	 * a match rule diverge quietly, and the two halves disagreeing is worse than either
	 * being wrong — the converter then lists work it will not do, or does work it never
	 * listed.
	 *
	 * Three independent gates, AND-ed, and an entry must declare at least one:
	 *   - match_options           every listed key must be present
	 *   - match_any_options       at least one listed key must be present
	 *   - match_option_values     at least one listed key must HOLD one of its listed
	 *                             values (map of key → accepted values)
	 *
	 * The value gate exists because key presence alone is far too coarse for a legacy
	 * VALUE. `src:related_post` (#56) can only be recognised by its value — gating on the
	 * key `src` would flag every tag that names a source at all, so the converter would
	 * report a migration on virtually every post and run nothing. Value matching keeps
	 * detection and application saying the same thing.
	 *
	 * Callers that have only the key list may omit $option_values; a value-gated entry
	 * then cannot match, which is the safe direction (report nothing rather than
	 * everything).
	 *
	 * @since 1.17.0
	 * @param array               $entry         Registry entry (any type; non-'option' never matches).
	 * @param string[]            $option_keys   Keys present in the parsed tag string.
	 * @param array<string,mixed> $option_values Parsed options (key → value), when available.
	 * @return bool
	 */
	public static function entry_matches( array $entry, array $option_keys, array $option_values = array() ): bool {
		if ( 'option' !== ( $entry['type'] ?? 'tag' ) ) {
			return false;
		}

		$required = $entry['match_options'] ?? array();
		$any      = $entry['match_any_options'] ?? array();
		$values   = $entry['match_option_values'] ?? array();

		if ( empty( $required ) && empty( $any ) && empty( $values ) ) {
			return false;
		}

		foreach ( $required as $key ) {
			if ( ! in_array( $key, $option_keys, true ) ) {
				return false;
			}
		}

		if ( ! empty( $any ) ) {
			$has_any = false;
			foreach ( $any as $key ) {
				if ( in_array( $key, $option_keys, true ) ) {
					$has_any = true;
					break;
				}
			}
			if ( ! $has_any ) {
				return false;
			}
		}

		if ( ! empty( $values ) ) {
			$has_value = false;
			foreach ( $values as $key => $accepted ) {
				if ( ! array_key_exists( $key, $option_values ) ) {
					continue;
				}
				if ( in_array( trim( (string) $option_values[ $key ] ), (array) $accepted, true ) ) {
					$has_value = true;
					break;
				}
			}
			if ( ! $has_value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Find ALL option migration entries matching a tag name and the options present.
	 *
	 * Matching is delegated to entry_matches(); matches come back in registration order.
	 * apply_option_migration() needs the full list because a matching entry that produces
	 * NO CHANGE must not end the cascade — see that method's docblock.
	 *
	 * There is deliberately no singular first-match sibling. One shipped through 1.16.x
	 * and 1.17.0 removed it: after the plural landed it was `find_option_migrations()[0]`
	 * with no caller in this plugin or in bws-portal-system, and the admin scanner reads
	 * get_option_migrations_by_tag() for its labels rather than either finder.
	 *
	 * @since 1.17.0
	 * @since 1.17.0 #56: optional $option_values enables the match_option_values gate.
	 * @param string              $tag_name      Current (live) tag name.
	 * @param string[]            $option_keys   Keys present in the parsed tag string.
	 * @param array<string,mixed> $option_values Parsed options (key → value), when available.
	 * @return array[] Matching entries, registration order.
	 */
	public static function find_option_migrations( string $tag_name, array $option_keys, array $option_values = array() ): array {
		$found = array();
		foreach ( self::$entries as $entry ) {
			if ( ( $entry['match_tag'] ?? '' ) !== $tag_name ) {
				continue;
			}
			if ( self::entry_matches( $entry, $option_keys, $option_values ) ) {
				$found[] = $entry;
			}
		}
		return $found;
	}

	/**
	 * Apply option migrations to a tag string. Loops until no matching entry produces a
	 * change, so overlapping/cascading migrations all apply in one call.
	 *
	 * A NO-OP ENTRY DOES NOT END THE CASCADE (fixed 1.17.0). Multiple 'option' entries per
	 * live tag is normal — `text` has ~5, `image` ~7 — and before this fix they only all
	 * fired because every matched entry happened to change something: the loop asked for
	 * the FIRST match and returned the moment that one produced no change, so a later
	 * entry never ran and REGISTRATION ORDER silently decided whether that mattered. The
	 * as+size fold is the live trigger: it matches on `as`, which survives its own
	 * transform, so `{{try_image as:url,large|src:ref|…}}` no-ops there and everything
	 * registered after it (including the FW-56/57 slot fold) was unreachable. So: walk
	 * every matching entry, take the first that CHANGES the string, then re-derive the
	 * matches from the new string (a transform can change which entries match) and go
	 * again. Terminates when no matching entry changes anything.
	 *
	 * Bounded by a hard iteration ceiling as a safety against pathological registrations. Each
	 * iteration applies at most one change, so the ceiling must exceed the longest legitimate
	 * chain (entries-per-tag, ~7 today) rather than merely the number of distinct entries
	 * that fire.
	 *
	 * @since 1.17.0 No-op entries are skipped instead of halting the cascade.
	 * @param string $tag_name   Current (live) tag name.
	 * @param string $tag_string Full raw tag string.
	 * @return string Migrated string, or original if nothing fired.
	 */
	public static function apply_option_migration( string $tag_name, string $tag_string ): string {
		$max_iterations = 32;
		for ( $i = 0; $i < $max_iterations; $i++ ) {
			[ , $options ] = self::parse_tag_string( $tag_string );
			$entries       = self::find_option_migrations( $tag_name, array_keys( $options ), $options );

			$changed = false;
			foreach ( $entries as $entry ) {
				$next = self::run_transform( $entry, $tag_string );
				if ( $next !== $tag_string ) {
					$tag_string = $next;
					$changed    = true;
					break;
				}
			}

			if ( ! $changed ) {
				return $tag_string;
			}
		}
		return $tag_string;
	}

	// ===============================================
	// PREVIEW / FORMATTING HELPERS
	// ===============================================

	/**
	 * Serialize a tag name and options into a GB tag string.
	 *
	 * Public so preview label builders can reconstruct old and new tag strings from options arrays.
	 * Empty-string values are omitted per GB tag string convention.
	 *
	 * @param string               $tag_name
	 * @param array<string,string> $options
	 * @return string e.g. `{{text src:ref|ref:X|key:Y}}`
	 */
	public static function format_tag_string( string $tag_name, array $options ): string {
		return self::serialize_tag_string( $tag_name, $options );
	}

	/**
	 * Get all match_tag values for 'tag' type entries (deprecated tag names).
	 *
	 * Used by the scanner to build the post content search query.
	 *
	 * @return string[]
	 */
	public static function get_deprecated_tag_names(): array {
		$names = array();
		foreach ( self::$entries as $entry ) {
			if ( 'tag' === ( $entry['type'] ?? 'tag' ) ) {
				$name = $entry['match_tag'] ?? '';
				if ( '' !== $name ) {
					$names[] = $name;
				}
			}
		}
		return array_unique( $names );
	}

	/**
	 * Get all 'option' type entries grouped by match_tag.
	 *
	 * Used by the scanner to detect base tags with deprecated option keys.
	 *
	 * @return array<string, array[]> Map of tag_name → list of option migration entries.
	 */
	public static function get_option_migrations_by_tag(): array {
		$grouped = array();
		foreach ( self::$entries as $entry ) {
			if ( 'option' !== ( $entry['type'] ?? 'tag' ) ) {
				continue;
			}
			$tag = $entry['match_tag'] ?? '';
			if ( '' !== $tag ) {
				$grouped[ $tag ][] = $entry;
			}
		}
		return $grouped;
	}

	// ===============================================
	// SHARED TRANSFORM PIPELINE
	// ===============================================

	/**
	 * Run the full transform pipeline on a tag string using a migration entry.
	 *
	 * Steps:
	 *   1. Parse tag string into options array.
	 *   2. Apply combine_options (presence-flag + value key → single new key).
	 *   3. Apply option_renames (old key → new key).
	 *   4. Apply value_renames (post-rename key → old value → new value).
	 *   5. Apply datetime special-case transforms (opt-in via 'datetime_transforms').
	 *   6. Inject source_inject — prepended so it serializes first.
	 *   7. Inject fixed_options (always-on key/value pairs).
	 *   8. Serialize with new_tag (or match_tag for option-type entries).
	 *
	 * @param array  $entry      Migration registry entry.
	 * @param string $tag_string Full raw tag string.
	 * @return string Transformed tag string.
	 */
	public static function run_transform( array $entry, string $tag_string ): string {
		// Step 0: a whole-string transform_callback overrides the declarative pipeline.
		// Used for shape changes the declarative steps can't express — e.g. the as+size
		// value-conditional fold (append size into `as`'s value, or drop a dead size on a
		// nullary mode). transform_tag() already honors this for tag-type entries; option
		// entries reach it through here.
		if ( isset( $entry['transform_callback'] ) && is_callable( $entry['transform_callback'] ) ) {
			return ( $entry['transform_callback'] )( $tag_string );
		}

		// Step 1: Parse. The pre-rename snapshot is kept because the datetime era test (step 5)
		// reads legacy FIELD keys, and step 3 renames those away before step 5 runs.
		[ , $options ]    = self::parse_tag_string( $tag_string );
		$original_options = $options;

		// Step 2: Apply combine_options. Combines a presence-flag key + a value key into one new key.
		// If both old keys present and value_from has a non-empty string, emit new_key = that value.
		// Either old key (or both) always dropped — incomplete configs are silently discarded.
		foreach ( $entry['combine_options'] ?? array() as $new_key => $spec ) {
			$bool_key  = $spec['when_present'] ?? '';
			$value_key = $spec['value_from'] ?? '';
			if ( '' === $bool_key || '' === $value_key ) {
				continue;
			}
			$has_flag  = array_key_exists( $bool_key, $options );
			$value     = $options[ $value_key ] ?? '';
			if ( $has_flag && is_string( $value ) && '' !== trim( $value ) ) {
				$options[ $new_key ] = $value;
			}
			unset( $options[ $bool_key ], $options[ $value_key ] );
		}

		// Step 3: Apply option_renames. Empty-string new_key = drop the option.
		foreach ( $entry['option_renames'] ?? array() as $old_key => $new_key ) {
			if ( array_key_exists( $old_key, $options ) ) {
				if ( '' !== $new_key ) {
					$options[ $new_key ] = $options[ $old_key ];
				}
				unset( $options[ $old_key ] );
			}
		}

		// Step 3.5: Remap GB-native `link` option for deprecated tags that had supports:['link'] (V10b).
		if ( ! empty( $entry['gb_link_remap'] ) && function_exists( 'bws_map_gb_link_option' ) ) {
			$options = bws_map_gb_link_option( $options );
		}

		// Step 4: Apply value_renames (keys are in post-rename form).
		foreach ( $entry['value_renames'] ?? array() as $key => $map ) {
			if ( isset( $options[ $key ] ) && array_key_exists( $options[ $key ], $map ) ) {
				$options[ $key ] = $map[ $options[ $key ] ];
			}
		}

		// Step 5: Datetime special-case transforms (opt-in per entry).
		if ( ! empty( $entry['datetime_transforms'] ) ) {
			$options = self::apply_datetime_transforms(
				$options,
				self::datetime_legacy_era( $entry, $original_options ),
				(string) ( $entry['fixed_options']['as'] ?? '' )
			);
		}

		// Step 6: Inject source_inject — prepended so it serializes first.
		$source_inject = $entry['source_inject'] ?? '';
		if ( '' !== $source_inject ) {
			$options = array_merge( array( 'src' => $source_inject ), $options );
		}

		// Step 7: Inject fixed_options.
		foreach ( $entry['fixed_options'] ?? array() as $key => $value ) {
			$options[ $key ] = $value;
		}

		// Step 8: Serialize. For 'option' type, new_tag equals match_tag.
		$new_tag = $entry['new_tag'] ?? ( $entry['match_tag'] ?? '' );
		return self::serialize_tag_string( $new_tag, $options );
	}

	// ===============================================
	// DATETIME SPECIAL-CASE TRANSFORMS
	// ===============================================

	/**
	 * Decide whether a tag being transformed is pre-1.6 datetime wire.
	 *
	 * A 'tag' entry matched a pre-1.6 tag NAME, so era is certain. An 'option' entry
	 * matched a live name (`datetime_single` / `datetime_range`) on the strength of a
	 * legacy option key, and the trigger list is deliberately wider than the era list:
	 * `fallback_text` is renamed on EVERY base tag, so it can sit on an otherwise-modern
	 * datetime tag. Injecting the inverted booleans on the strength of that one key would
	 * change the rendered output of a tag that was already modern.
	 *
	 * @param array $entry            Migration registry entry.
	 * @param array $original_options Options as parsed, BEFORE renames.
	 * @return bool True when the wire predates the 1.6 datetime rename.
	 */
	private static function datetime_legacy_era( array $entry, array $original_options ): bool {
		if ( 'option' !== ( $entry['type'] ?? 'tag' ) ) {
			return true;
		}
		$era_keys = $entry['datetime_era_options'] ?? array();
		if ( empty( $era_keys ) ) {
			return false;
		}
		return (bool) array_intersect_key( $original_options, array_flip( $era_keys ) );
	}

	/**
	 * Apply the five datetime option special-case transforms.
	 *
	 * Applied only when the registry entry sets `datetime_transforms: true`.
	 * See DeprecatedTagRegistry docblock for full transform description.
	 *
	 * INVERTED BOOLEANS ARE INJECTED ON ABSENCE, NOT ON `:false`. The pre-1.6 renderer read
	 * `! empty( $options['smart_time'] )` with no default merge — GB's parse_options() only
	 * reports keys literally present (docs/gb-constraints.md §Option Default Serialization),
	 * so the option definition's `'default' => true` never reached the callback. Absent, `''`
	 * and `'0'` therefore ALL rendered smart-time OFF (midnight shown), while the modern
	 * default is the opposite: absent `showMidnight` maps to `smart_time = true`, which HIDES
	 * midnight (bws_normalize_datetime_options). Holding output steady means injecting the
	 * modern flag exactly where the old read was falsy. Same rule, same reason, for
	 * `omit_current_year` → `showCurrentYear`.
	 *
	 * The `'false' === …` test this replaced was reachable only from hand-edited wire (GB
	 * never serializes a false boolean) and was INVERTED even there: `'false'` is a non-empty
	 * string, so `! empty( 'false' )` is true and the old renderer treated it as ON. See #90.
	 *
	 * @param array  $options     Options array after renames have been applied.
	 * @param bool   $legacy_era  True when the wire predates the 1.6 rename. False suppresses
	 *                            injection entirely: without era evidence an absent boolean is
	 *                            a modern default, not an old author's unchecked box.
	 * @param string $fixed_as    The entry's fixed_options `as` value, if any. Read because
	 *                            fixed_options are injected at step 7 — AFTER this runs — so a
	 *                            date-only entry's `as` is not yet in $options.
	 * @return array Transformed options array.
	 */
	private static function apply_datetime_transforms( array $options, bool $legacy_era = true, string $fixed_as = '' ): array {
		// 1. Collapse format_type + custom_format → format.
		if ( array_key_exists( 'format_type', $options ) ) {
			if ( 'custom' === $options['format_type'] && array_key_exists( 'custom_format', $options ) ) {
				$options['format'] = $options['custom_format'];
			}
			unset( $options['format_type'], $options['custom_format'] );
		}

		// 2. date_only → as:date.
		if ( array_key_exists( 'date_only', $options ) ) {
			$options['as'] = 'date';
			unset( $options['date_only'] );
		}

		// 3. time_only → as:time.
		if ( array_key_exists( 'time_only', $options ) ) {
			$options['as'] = 'time';
			unset( $options['time_only'] );
		}

		// The output shape the migrated tag will have. Steps 2 and 3 above already turned
		// date_only/time_only into `as`; a tag that states neither takes the entry's
		// fixed_options value, which has not been injected yet (step 7).
		$as = (string) ( $options['as'] ?? $fixed_as );

		// 4. smart_time → showMidnight (inverted). Inject where the OLD read was falsy —
		//    absent, '' or '0' all rendered midnight SHOWN, which is not the modern default.
		//    A bare key parses as PHP true and is simply dropped: hidden then, hidden now.
		//    Skipped on a date-only tag, where the flag would render nothing (both date cores
		//    force smart_time => false) but would read as a live setting in hand-edited wire.
		$show_midnight = $legacy_era && empty( $options['smart_time'] ) && 'date' !== $as;
		unset( $options['smart_time'] );

		// 5. omit_current_year → showCurrentYear (inverted). Same rule, mirrored gate: a
		//    time-only tag renders no year, so the flag is noise there.
		$show_current_year = $legacy_era && empty( $options['omit_current_year'] ) && 'time' !== $as;
		unset( $options['omit_current_year'] );

		// Emitted in CANONICAL order (serialization-order.php ranks showCurrentYear 7,
		// showMidnight 8) rather than in the order the steps above decide them. Migrated wire
		// is re-serialized from insertion order, and the editor's FW-52 normalizer sorts on
		// setState — so emitting them the other way round costs a spurious diff the first
		// time an author opens a migrated tag.
		if ( $show_current_year ) {
			$options['showCurrentYear'] = true;
		}
		if ( $show_midnight ) {
			$options['showMidnight'] = true;
		}

		return $options;
	}

	// ===============================================
	// TAG STRING PARSING + SERIALIZATION
	// ===============================================

	/**
	 * Parse a GB tag string into [tag_name, options_array].
	 *
	 * Format: `{{tag_name key1:val1|key2:val2}}`. Each pair splits on the first colon
	 * so values may contain colons (e.g. `format:Y-m-d H:i`).
	 *
	 * TRAILING WHITESPACE INSIDE THE BRACES IS PART OF THE LAST VALUE, and is preserved
	 * (fixed 1.17.0). GB's own parser does not trim option values — `parse_options()`
	 * splits on `|` then `:` and stores the remainder verbatim — so `{{text sep:, }}`
	 * renders with ", " and rtrimming here turned an authored separator into ",". Harmless
	 * while option migrations were narrow single-key renames; the FW-56/57 fold entry
	 * matches nearly every join/try_ tag, which made it reachable at scale.
	 *
	 * @since 1.17.0 The last option value keeps its trailing whitespace.
	 * @param string $tag_string Full tag string including `{{` / `}}`.
	 * @return array{0: string, 1: array<string,string>}
	 */
	public static function parse_tag_string( string $tag_string ): array {
		$inner = trim( $tag_string );

		if ( str_starts_with( $inner, '{{' ) ) {
			$inner = substr( $inner, 2 );
		}
		if ( str_ends_with( $inner, '}}' ) ) {
			$inner = substr( $inner, 0, -2 );
		}
		$inner = ltrim( $inner );

		$space = strpos( $inner, ' ' );
		if ( false === $space ) {
			return array( $inner, array() );
		}

		$tag_name    = substr( $inner, 0, $space );
		$options_str = ltrim( substr( $inner, $space + 1 ) );
		$options     = array();

		if ( '' !== $options_str ) {
			foreach ( explode( '|', $options_str ) as $pair ) {
				$colon = strpos( $pair, ':' );
				if ( false !== $colon ) {
					$key = substr( $pair, 0, $colon );
					if ( '' !== $key ) {
						$options[ $key ] = substr( $pair, $colon + 1 );
					}
				} elseif ( '' !== $pair ) {
					// A VALUELESS option is GB's bare-key boolean, and it must survive the
					// round trip. serialize_tag_string() has always emitted `true` as a bare
					// key — its docblock names `showMidnight` as the example — but this half
					// never produced one, so the branch was unreachable and every migration
					// DELETED the flag it parsed: `noLink`, `newTab`, `showCurrentYear`,
					// `showMidnight`. For `noLink` that turned the mailto wrap back on and
					// removed the setting that said otherwise, i.e. a migration changing
					// rendered output, which is the one thing migration promises not to do.
					// Shipped from 1.6.x until 1.17.0 (#67).
					$options[ $pair ] = true;
				}
			}
		}

		return array( $tag_name, $options );
	}

	/**
	 * Serialize a tag name and options array into a GB tag string.
	 *
	 * Empty-string values are omitted per GB convention. PHP `true` values serialize
	 * as bare keys (no colon) — GB's boolean convention for `true`. Key order follows
	 * insertion order.
	 *
	 * @param string                    $tag_name
	 * @param array<string,string|true> $options
	 * @return string e.g. `{{text src:ref|ref:X|key:Y|showMidnight}}`
	 */
	private static function serialize_tag_string( string $tag_name, array $options ): string {
		$pairs = array();
		foreach ( $options as $key => $value ) {
			if ( true === $value ) {
				$pairs[] = $key;
			} elseif ( '' !== (string) $value ) {
				$pairs[] = $key . ':' . $value;
			}
		}

		if ( empty( $pairs ) ) {
			return '{{' . $tag_name . '}}';
		}

		return '{{' . $tag_name . ' ' . implode( '|', $pairs ) . '}}';
	}
}
