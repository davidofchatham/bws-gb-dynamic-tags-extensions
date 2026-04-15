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
 *             'old_tag'        => 'portal_post_meta',
 *             'new_tag'        => 'text',
 *             'title'          => 'Portal Post Meta',
 *             'supports'       => array( 'source' ),
 *             'options'        => portal_get_text_options(),
 *             'callback'       => 'portal_deprecated_post_meta_callback',
 *             'since'          => '2.0.0',
 *             'via_inject'     => '',
 *             'option_renames' => array( 'field_key' => 'key' ),
 *             'value_renames'  => array(),
 *             'fixed_options'  => array( 'from' => 'key' ),
 *         ) );
 *     } );
 *
 * @package BWS_Dynamic_Tags
 * @since 1.3.0
 * @since 1.6.0 Added via_inject, option_renames, value_renames, fixed_options, datetime_transforms;
 *              enforced gb_type: 'deprecated'; removed $is_related; added transform_options().
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
	 * `gb_type` is always overwritten to `'deprecated'` regardless of caller input.
	 * This ensures deprecated tags appear in their own unlabeled group in GB's tag picker.
	 *
	 * @since 1.3.0
	 * @since 1.6.0 Enforces gb_type 'deprecated'; removed $is_related; added migration fields.
	 *
	 * @param array $args {
	 *     @type string          $old_tag              Required. Deprecated GB tag name.
	 *     @type string          $new_tag              Required. Replacement tag name (used by transform_options and notices).
	 *     @type string          $title                GB tag title shown in the editor.
	 *     @type array           $supports             GB supports array.
	 *     @type array           $options              GB options array (optional).
	 *     @type callable|string $callback             PHP callable that handles tag output.
	 *     @type string          $since                Version when old_tag was deprecated (for _doing_it_wrong notice).
	 *     @type string          $description          Override the auto-generated GB tag description (optional).
	 *     @type string          $via_inject           Abbreviation to inject as the `via` option on conversion.
	 *                                                 Empty string omits `via` (correct for current-entity sources).
	 *     @type array           $option_renames       Map of old option key → new option key.
	 *     @type array           $value_renames        Map of (post-rename) option key → [old value => new value].
	 *                                                 Applied after option_renames so keys are already in new form.
	 *     @type array           $fixed_options        Key/value pairs always injected on conversion regardless of
	 *                                                 user options (e.g. ['from' => 'excerpt'] for excerpt wrappers).
	 *     @type bool            $datetime_transforms  When true, apply the five special-case datetime option
	 *                                                 transforms during transform_options(). Default false.
	 * }
	 */
	public static function register( array $args ): void {
		$args['gb_type'] = 'deprecated';
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

	/**
	 * Transform a raw deprecated tag string into the migrated new-format tag string.
	 *
	 * Applies in order:
	 *   1. Parse the raw tag string into options array.
	 *   2. Apply option_renames (old key → new key).
	 *   3. Apply value_renames (new key → old value → new value).
	 *   4. Apply special-case datetime transforms (when datetime_transforms is true).
	 *   5. Inject via_inject (non-empty → add via option).
	 *   6. Inject fixed_options (always-on key/value pairs).
	 *   7. Serialize into {{new_tag ...}} string.
	 *
	 * Returns the original string unchanged when no registry entry is found.
	 *
	 * @since 1.6.0
	 * @param string $old_tag_name The deprecated tag name to look up.
	 * @param string $tag_string   The full raw tag string (e.g. `{{old_tag key:val|...}}`).
	 * @return string Migrated tag string using the new tag name and option format.
	 */
	public static function transform_options( string $old_tag_name, string $tag_string ): string {
		$entry = null;
		foreach ( self::$entries as $e ) {
			if ( ( $e['old_tag'] ?? '' ) === $old_tag_name ) {
				$entry = $e;
				break;
			}
		}

		if ( null === $entry ) {
			return $tag_string;
		}

		// Step 1: Parse.
		[ , $options ] = self::parse_tag_string( $tag_string );

		// Step 2: Apply option_renames.
		foreach ( $entry['option_renames'] ?? array() as $old_key => $new_key ) {
			if ( array_key_exists( $old_key, $options ) ) {
				$options[ $new_key ] = $options[ $old_key ];
				unset( $options[ $old_key ] );
			}
		}

		// Step 3: Apply value_renames (keys are now in their post-rename form).
		foreach ( $entry['value_renames'] ?? array() as $key => $map ) {
			if ( isset( $options[ $key ] ) && array_key_exists( $options[ $key ], $map ) ) {
				$options[ $key ] = $map[ $options[ $key ] ];
			}
		}

		// Step 4: Special-case datetime transforms (opt-in per entry).
		if ( ! empty( $entry['datetime_transforms'] ) ) {
			$options = self::apply_datetime_transforms( $options );
		}

		// Step 5: Inject via_inject.
		$via_inject = $entry['via_inject'] ?? '';
		if ( $via_inject !== '' ) {
			$options['via'] = $via_inject;
		}

		// Step 6: Inject fixed_options.
		foreach ( $entry['fixed_options'] ?? array() as $key => $value ) {
			$options[ $key ] = $value;
		}

		// Step 7: Serialize with new tag name.
		$new_tag = $entry['new_tag'] ?? $old_tag_name;
		return self::serialize_tag_string( $new_tag, $options );
	}

	// ===============================================
	// DATETIME SPECIAL-CASE TRANSFORMS
	// ===============================================

	/**
	 * Apply the five special-case datetime option transforms.
	 *
	 * These transforms handle migrations that cannot be expressed as simple key→key
	 * renames or value→value maps. Applied only when the registry entry sets
	 * `datetime_transforms: true`.
	 *
	 * Transform order:
	 *   1. format_type + custom_format → format (collapse or drop).
	 *   2. date_only → as:date (inject + drop key).
	 *   3. time_only → as:time (inject + drop key).
	 *   4. smart_time → drop; if absent and time output is possible, inject show_midnight:true.
	 *   5. omit_current_year → drop; if absent, inject show_current_year:true.
	 *
	 * @since 1.6.0
	 * @param array $options Options array after renames have been applied.
	 * @return array Transformed options array.
	 */
	private static function apply_datetime_transforms( array $options ): array {

		// 1. Collapse format_type + custom_format → format.
		if ( array_key_exists( 'format_type', $options ) ) {
			if ( 'custom' === $options['format_type'] && array_key_exists( 'custom_format', $options ) ) {
				$options['format'] = $options['custom_format'];
			}
			// Drop source keys regardless (whether custom or another preset type).
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
		// Tag "produces time output" when the as option is not 'date' after steps 2–3.
		$has_time_output = ( ( $options['as'] ?? '' ) !== 'date' );
		if ( array_key_exists( 'smart_time', $options ) ) {
			unset( $options['smart_time'] );
			// Do NOT inject show_midnight (smart_time presence means user opted in; drop it).
		} elseif ( $has_time_output ) {
			$options['show_midnight'] = 'true';
		}

		// 5. omit_current_year → show_current_year (inverted boolean).
		if ( array_key_exists( 'omit_current_year', $options ) ) {
			// omit_current_year was true → show_current_year is false → omit from output.
			unset( $options['omit_current_year'] );
		} else {
			// omit_current_year was absent (false) → show_current_year is true.
			$options['show_current_year'] = 'true';
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
	 * so values may themselves contain colons (e.g. `format:Y-m-d H:i`).
	 *
	 * @since 1.6.0
	 * @param string $tag_string Full tag string including `{{` / `}}`.
	 * @return array{0: string, 1: array<string,string>}
	 */
	private static function parse_tag_string( string $tag_string ): array {
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

		$options = array();
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
	 * Serialize a tag name and options array back into a GB tag string.
	 *
	 * Empty-string values are omitted per GB's tag string convention (only non-empty
	 * values are serialized). Key order follows insertion order of the array.
	 *
	 * @since 1.6.0
	 * @param string             $tag_name
	 * @param array<string,string> $options
	 * @return string e.g. `{{text via:ref|ref:body_text}}`
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
