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
	 *   @type bool    $datetime_transforms When true, apply datetime special-case transforms.
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
	 *
	 *   'option' type only:
	 *   @type array   $match_options   Option keys that must ALL be present in the tag string to trigger.
	 *   @type string  $label           Short description shown in admin UI (e.g. 'rel → ref fix').
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
	 * Applies the full transform pipeline: parse → option_renames → value_renames
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

		return self::run_transform( $entry, $tag_string );
	}

	// ===============================================
	// OPTION-TYPE METHODS
	// ===============================================

	/**
	 * Find an option migration entry matching a tag name and option keys present in a tag string.
	 *
	 * All keys listed in match_options must be present in $option_keys for an entry to match.
	 *
	 * @param string   $tag_name    Current (live) tag name.
	 * @param string[] $option_keys Keys present in the parsed tag string.
	 * @return array|null Matching entry, or null if none found.
	 */
	public static function find_option_migration( string $tag_name, array $option_keys ): ?array {
		foreach ( self::$entries as $entry ) {
			if ( 'option' !== ( $entry['type'] ?? 'tag' ) ) {
				continue;
			}
			if ( ( $entry['match_tag'] ?? '' ) !== $tag_name ) {
				continue;
			}
			$required = $entry['match_options'] ?? array();
			if ( empty( $required ) ) {
				continue;
			}
			foreach ( $required as $key ) {
				if ( ! in_array( $key, $option_keys, true ) ) {
					continue 2;
				}
			}
			return $entry;
		}
		return null;
	}

	/**
	 * Apply an option migration to a tag string if a matching entry exists.
	 *
	 * @param string $tag_name   Current (live) tag name.
	 * @param string $tag_string Full raw tag string.
	 * @return string Migrated string, or original if no matching entry found.
	 */
	public static function apply_option_migration( string $tag_name, string $tag_string ): string {
		[ , $options ] = self::parse_tag_string( $tag_string );
		$entry         = self::find_option_migration( $tag_name, array_keys( $options ) );

		if ( null === $entry ) {
			return $tag_string;
		}

		return self::run_transform( $entry, $tag_string );
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
		// Step 1: Parse.
		[ , $options ] = self::parse_tag_string( $tag_string );

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

		// Step 4: Apply value_renames (keys are in post-rename form).
		foreach ( $entry['value_renames'] ?? array() as $key => $map ) {
			if ( isset( $options[ $key ] ) && array_key_exists( $options[ $key ], $map ) ) {
				$options[ $key ] = $map[ $options[ $key ] ];
			}
		}

		// Step 5: Datetime special-case transforms (opt-in per entry).
		if ( ! empty( $entry['datetime_transforms'] ) ) {
			$options = self::apply_datetime_transforms( $options );
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
	 * Apply the five datetime option special-case transforms.
	 *
	 * Applied only when the registry entry sets `datetime_transforms: true`.
	 * See DeprecatedTagRegistry docblock for full transform description.
	 *
	 * @param array $options Options array after renames have been applied.
	 * @return array Transformed options array.
	 */
	private static function apply_datetime_transforms( array $options ): array {
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

		// 4. smart_time elimination.
		$has_time_output = ( ( $options['as'] ?? '' ) !== 'date' );
		if ( array_key_exists( 'smart_time', $options ) ) {
			unset( $options['smart_time'] );
		} elseif ( $has_time_output ) {
			$options['showMidnight'] = 'true';
		}

		// 5. omit_current_year → showCurrentYear (inverted boolean).
		if ( array_key_exists( 'omit_current_year', $options ) ) {
			unset( $options['omit_current_year'] );
		} else {
			$options['showCurrentYear'] = 'true';
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
		$inner = trim( $inner );

		$space = strpos( $inner, ' ' );
		if ( false === $space ) {
			return array( $inner, array() );
		}

		$tag_name    = substr( $inner, 0, $space );
		$options_str = trim( substr( $inner, $space + 1 ) );
		$options     = array();

		if ( '' !== $options_str ) {
			foreach ( explode( '|', $options_str ) as $pair ) {
				$colon = strpos( $pair, ':' );
				if ( false !== $colon ) {
					$key = substr( $pair, 0, $colon );
					if ( '' !== $key ) {
						$options[ $key ] = substr( $pair, $colon + 1 );
					}
				}
			}
		}

		return array( $tag_name, $options );
	}

	/**
	 * Serialize a tag name and options array into a GB tag string.
	 *
	 * Empty-string values are omitted per GB convention. Key order follows insertion order.
	 *
	 * @param string               $tag_name
	 * @param array<string,string> $options
	 * @return string e.g. `{{text src:ref|ref:X|key:Y}}`
	 */
	private static function serialize_tag_string( string $tag_name, array $options ): string {
		$pairs = array();
		foreach ( $options as $key => $value ) {
			if ( '' !== (string) $value ) {
				$pairs[] = $key . ':' . $value;
			}
		}

		if ( empty( $pairs ) ) {
			return '{{' . $tag_name . '}}';
		}

		return '{{' . $tag_name . ' ' . implode( '|', $pairs ) . '}}';
	}
}
