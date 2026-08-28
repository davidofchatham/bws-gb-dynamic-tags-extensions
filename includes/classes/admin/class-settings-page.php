<?php
/**
 * Admin Settings Page for BWS Dynamic Tag Extensions.
 *
 * Settings schema (v1.6.0):
 *   {
 *     modifiers:   { term: bool, try: bool },
 *     deprecated:  {
 *       mode_with_path:    'keep'|'suppress'|'disable',
 *       mode_without_path: 'keep'|'suppress'|'disable',
 *     },
 *     diagnostics: { benchmark_logging: bool, registration_logging: bool },
 *     email:       { obfuscate: bool },
 *     phone:       { country_code: string, strip_leading_cc: bool },
 *   }
 *
 * Deprecated tag mode semantics:
 *   keep     — tags register and execute normally (default).
 *   suppress — tags register but callbacks return '' (prevents unprocessed tags on frontend).
 *   disable  — tags are not registered with GB (removed from tag picker).
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 * @since 1.6.0 Group-mode deprecated settings; scan + migrate tool; removed per-tag toggles.
 */

namespace BWS\DynamicTags\Admin;

use BWS\DynamicTags\MigrationRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SettingsPage {

	/** @var string Option name in wp_options. */
	const OPTION_NAME = 'bws_dynamic_tags_settings';

	/** @var array|null Cached settings. */
	private static ?array $settings = null;

	// ===============================================
	// INITIALIZATION
	// ===============================================

	public static function init(): void {
		add_action( 'admin_menu', array( static::class, 'add_menu_page' ), 20 );
		add_action( 'admin_init', array( static::class, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( static::class, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_bws_scan_tags', array( TagConverter::class, 'ajax_scan' ) );
		add_action( 'wp_ajax_bws_migrate_tags', array( TagConverter::class, 'ajax_migrate' ) );
	}

	public static function add_menu_page(): void {
		add_submenu_page(
			'generateblocks',
			__( 'Dynamic Tag Extensions', 'generateblocks' ),
			__( 'Tag Extensions', 'generateblocks' ),
			'manage_options',
			'bws-dynamic-tags',
			array( static::class, 'render_page' )
		);
	}

	public static function register_settings(): void {
		register_setting(
			'bws_dynamic_tags_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( static::class, 'sanitize_settings' ),
				'default'           => array(),
			)
		);
	}

	public static function enqueue_scripts( string $hook ): void {
		if ( false === strpos( $hook, 'bws-dynamic-tags' ) ) {
			return;
		}
		wp_enqueue_script(
			'bws-admin-tag-scanner',
			BWS_DYNAMIC_TAGS_URL . 'assets/js/admin-tag-scanner.js',
			array(),
			BWS_DYNAMIC_TAGS_VERSION,
			true
		);
		wp_localize_script(
			'bws-admin-tag-scanner',
			'bwsTagScanner',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'bws_convert_tag' ),
				'batchSize'   => 10,
				'i18n'        => array(
					'scanning'       => __( 'Scanning…', 'generateblocks' ),
					'migrating'      => __( 'Migrating…', 'generateblocks' ),
					'noIssues'       => __( 'No deprecated tags or option issues found.', 'generateblocks' ),
					'noRevision'     => __( '⚠ No undo — this post type does not support revisions.', 'generateblocks' ),
					'migrateAll'     => __( 'Migrate Selected', 'generateblocks' ),
					'done'           => __( 'Done', 'generateblocks' ),
					'errorPrefix'    => __( 'Error:', 'generateblocks' ),
					'tagsMigrated'   => __( 'tags migrated', 'generateblocks' ),
					'optsMigrated'   => __( 'option fixes applied', 'generateblocks' ),
					'noChange'       => __( 'No changes needed', 'generateblocks' ),
					'selectAll'      => __( 'Select all', 'generateblocks' ),
					'deselectAll'    => __( 'Deselect all', 'generateblocks' ),
					'progressLabel'  => __( 'Migrating post %1$d of %2$d…', 'generateblocks' ),
					'bulkDone'       => __( '%d posts processed.', 'generateblocks' ),
				),
			)
		);
	}

	// ===============================================
	// SETTINGS SCHEMA + SANITIZE
	// ===============================================

	public static function sanitize_settings( $input ): array {
		$sanitized = array(
			'modifiers'   => array(),
			'deprecated'  => array(),
			'diagnostics' => array(),
			'email'       => array(),
			'phone'       => array(),
		);

		// Modifier toggles.
		$sanitized['modifiers']['term'] = ! empty( $input['modifiers']['term'] );
		$sanitized['modifiers']['try']  = ! empty( $input['modifiers']['try'] );

		// Deprecated tag group modes.
		$valid_modes = array( 'keep', 'suppress', 'disable' );
		foreach ( array( 'mode_with_path', 'mode_without_path' ) as $key ) {
			$val = sanitize_key( $input['deprecated'][ $key ] ?? 'keep' );
			$sanitized['deprecated'][ $key ] = in_array( $val, $valid_modes, true ) ? $val : 'keep';
		}

		// Diagnostics.
		$sanitized['diagnostics']['benchmark_logging']    = ! empty( $input['diagnostics']['benchmark_logging'] );
		$sanitized['diagnostics']['registration_logging'] = ! empty( $input['diagnostics']['registration_logging'] );
		$sanitized['diagnostics']['show_all_deprecated']  = ! empty( $input['diagnostics']['show_all_deprecated'] );

		// Email. `obfuscate` defaults ON; the checkbox renders checked, so an
		// untouched first save submits 1. Unchecking it writes false.
		$sanitized['email']['obfuscate'] = ! empty( $input['email']['obfuscate'] );

		// Phone. country_code: digits only, no `+` (a wrong seed silently builds
		// wrong hrefs, so the default is empty). strip_leading_cc: opt-in, default
		// OFF — only strips a leading run matching this country_code.
		$sanitized['phone']['country_code']     = preg_replace( '/\D/', '', (string) ( $input['phone']['country_code'] ?? '' ) );
		$sanitized['phone']['strip_leading_cc'] = ! empty( $input['phone']['strip_leading_cc'] );

		return $sanitized;
	}

	// ===============================================
	// SETTINGS ACCESSORS
	// ===============================================

	public static function get_settings(): array {
		if ( null === self::$settings ) {
			self::$settings = get_option( self::OPTION_NAME, array() );
		}
		return self::$settings;
	}

	public static function is_modifier_enabled( string $modifier ): bool {
		$settings = self::get_settings();
		return isset( $settings['modifiers'][ $modifier ] )
			? (bool) $settings['modifiers'][ $modifier ]
			: true;
	}

	/**
	 * Get the deprecation mode for a tag name.
	 *
	 * Looks up which group the tag belongs to (with/without migration path) and returns
	 * the saved mode for that group. Defaults to 'keep'.
	 *
	 * @since 1.6.0
	 * @param string $tag_name Tag name to look up.
	 * @return string 'keep', 'suppress', or 'disable'.
	 */
	public static function get_deprecated_mode( string $tag_name ): string {
		$settings = self::get_settings();
		$group    = MigrationRegistry::has_migration_path( $tag_name ) ? 'mode_with_path' : 'mode_without_path';
		$mode     = $settings['deprecated'][ $group ] ?? 'keep';
		return in_array( $mode, array( 'keep', 'suppress', 'disable' ), true ) ? $mode : 'keep';
	}

	/**
	 * Whether a deprecated tag should be registered with GenerateBlocks.
	 *
	 * @since 1.6.0
	 * @param string $tag_name Deprecated tag name.
	 * @return bool False only when mode is 'disable'.
	 */
	public static function is_deprecated_tag_registered( string $tag_name ): bool {
		return 'disable' !== self::get_deprecated_mode( $tag_name );
	}

	/**
	 * Whether a deprecated tag callback should suppress its output (return '').
	 *
	 * @since 1.6.0
	 * @param string $tag_name Deprecated tag name.
	 * @return bool True only when mode is 'suppress'.
	 */
	public static function is_deprecated_tag_suppressed( string $tag_name ): bool {
		return 'suppress' === self::get_deprecated_mode( $tag_name );
	}

	/**
	 * Backward-compat alias — returns true when tag is registered (mode != 'disable').
	 *
	 * @since 1.0.0
	 * @deprecated 1.6.0 Use is_deprecated_tag_registered() instead.
	 */
	public static function is_deprecated_tag_enabled( string $tag_name ): bool {
		return self::is_deprecated_tag_registered( $tag_name );
	}

	public static function is_benchmark_logging_enabled(): bool {
		return (bool) ( self::get_settings()['diagnostics']['benchmark_logging'] ?? false );
	}

	public static function is_registration_logging_enabled(): bool {
		return (bool) ( self::get_settings()['diagnostics']['registration_logging'] ?? false );
	}

	/**
	 * Whether the scan-allowlist hide filter is bypassed on the Deprecated/Removed
	 * Tags+Options boxes — shows every registered entry regardless of scan match.
	 *
	 * @since 1.14.0
	 * @invariant V8 — permanent escape hatch; toggle state is never reset by a scan.
	 */
	public static function is_show_all_deprecated_enabled(): bool {
		return (bool) ( self::get_settings()['diagnostics']['show_all_deprecated'] ?? false );
	}

	/**
	 * Whether `{{email}}` addresses are obfuscated (antispambot) on output.
	 *
	 * Default ON (WP-parity); the global only ever DISABLES. Mirrors the
	 * default-true shape of is_modifier_enabled — absence means enabled.
	 *
	 * @invariant VE4 — default true; gates BOTH display text and the mailto href
	 *   local-part in bws_email_callback.
	 * @since 1.9.0
	 * @return bool
	 */
	public static function is_email_obfuscation_enabled(): bool {
		$settings = self::get_settings();
		return isset( $settings['email']['obfuscate'] )
			? (bool) $settings['email']['obfuscate']
			: true;
	}

	/**
	 * Default country code for `{{phone}}` tel: links (digits only, no `+`).
	 *
	 * Empty default — locale is not telephone country, so no country is assumed.
	 * When empty, a number with no in-field `+`/`00` prefix yields a national
	 * tel: link (no `+`).
	 *
	 * @invariant VP3 — empty default; consulted only when a number is not already
	 *   international.
	 * @since 1.10.0
	 * @return string Digits only, or ''.
	 */
	public static function get_phone_country_code(): string {
		$settings = self::get_settings();
		return preg_replace( '/\D/', '', (string) ( $settings['phone']['country_code'] ?? '' ) );
	}

	/**
	 * Whether a leading country code matching the configured default is stripped.
	 *
	 * Default OFF (opt-in). Guards the US `1-800-555-1212` + country-code-`1`
	 * double-prefix. Matches the GLOBAL country code only; no-ops when that code
	 * is empty.
	 *
	 * @invariant VP-strip — default false; matches the global country code only.
	 * @since 1.10.0
	 * @return bool
	 */
	public static function is_phone_strip_leading_cc_enabled(): bool {
		return ! empty( self::get_settings()['phone']['strip_leading_cc'] );
	}

	/**
	 * Build the read-only `{{call}}` allowlist mirror rows (diagnostic, NOT config).
	 *
	 * Surfaces the live `bws_fn_passthrough_functions` allowlist + per-entry status
	 * so an editor can discover which functions `{{call}}` will accept without the
	 * admin touching the trust boundary (the allowlist is file/code-access only,
	 * VC-empty/VC-allow). The same allowlist feeds the editor `fn:` select
	 * (VC-select — one allowlist, two consumers).
	 *
	 * @since 1.12.0
	 * @return array<int,array{fn:string,exists:bool,passes:bool}> Status rows.
	 */
	public static function get_call_allowlist_status(): array {
		if ( ! function_exists( 'bws_call_get_allowlist' ) || ! function_exists( 'bws_call_passes_gate' ) ) {
			return array();
		}
		$rows = array();
		foreach ( array_keys( bws_call_get_allowlist() ) as $fn ) {
			$rows[] = array(
				'fn'     => (string) $fn,
				'exists' => function_exists( $fn ),
				'passes' => bws_call_passes_gate( (string) $fn ),
			);
		}
		return $rows;
	}

	/**
	 * Build the read-only tag-name-conflict rows for the Tag Name Conflicts subsection.
	 *
	 * A MIRROR OF THIS REQUEST'S COLLISION RECORD, in the order the page shows it.
	 * bws_gb_tag_name_collisions() (includes/helpers/gb-registration-boundary.php) owns the
	 * record and what each outcome means, and bws_gb_collision_other_parties() owns which of
	 * its field pairs names which party. THIS FUNCTION DECIDES NEITHER. It used to answer the
	 * second question itself, in a copy of the mapping the report sentences also carried, and
	 * a mapping written twice is one an added outcome gets right in one place only.
	 *
	 * BOTH PARTIES TRAVEL, NOT THE RELEVANT ONE. A record where a takeover merged onto an
	 * earlier conflict names two strangers, and picking one here would decide, on the page's
	 * behalf, which half of a three-way contest a reader is allowed to see.
	 *
	 * READ AT RENDER, WHICH IS WHY THIS IS SAFE. The record is request-scoped: written by
	 * the init:20 registration pass and completed by bws_gb_recheck_tag_ownership() on
	 * `wp_loaded` at PHP_INT_MAX. An admin page body renders well after `wp_loaded`, so the
	 * record is complete here. A consumer running EARLIER would read a half-built one.
	 *
	 * THE OWNER NAME IS RESOLVED HERE, ONCE PER PARTY, so the markup below stays a template.
	 * It is DERIVED, not recorded — bws_gb_registrar_plugin_name() owns the derivation and
	 * what '' means — and it is added to the party pairs rather than replacing anything: the
	 * tag `title` is what a reader sees in the editor and the file is what they can open, so
	 * both survive as evidence behind an owner the page leads with.
	 *
	 * @since 1.19.0
	 * @return array<int,array{tag:string,outcome:string,before:?array{title:string,source:string,plugin:string},after:?array{title:string,source:string,plugin:string},subject:string}>
	 */
	public static function get_tag_collision_status(): array {
		if (
			! function_exists( 'bws_gb_tag_name_collisions' )
			|| ! function_exists( 'bws_gb_collision_other_parties' )
			|| ! function_exists( 'bws_gb_registrar_plugin_name' )
		) {
			return array();
		}

		$record = bws_gb_tag_name_collisions();
		ksort( $record );

		$rows = array();

		foreach ( $record as $tag => $entry ) {
			$parties = bws_gb_collision_other_parties( $entry );

			foreach ( array( 'before', 'after' ) as $role ) {
				if ( is_array( $parties[ $role ] ) ) {
					$parties[ $role ]['plugin'] = bws_gb_registrar_plugin_name( $parties[ $role ]['source'] );
				}
			}

			$rows[] = array_merge(
				array(
					'tag'     => (string) $tag,
					'outcome' => (string) ( $entry['outcome'] ?? '' ),
				),
				$parties
			);
		}

		return $rows;
	}

	/**
	 * Build a short Approach-A migration target string from a registry entry.
	 *
	 * Renders only the parts of the migration that are required for the migrated
	 * tag to reproduce the deprecated tag's default behavior: target tag,
	 * source_inject as `src:<value>`, fixed_options pairs, and any author-declared
	 * `required_options` keys (rendered as `<key>:…` placeholders). A trailing
	 * `…` segment indicates additional user options carry over via option_renames
	 * / value_renames / combine_options / datetime_transforms (full rename map is
	 * shown in the Deprecated Options section).
	 *
	 * @param array $entry Migration registry entry (tag- or option-type).
	 * @return string e.g. `{{title src:ref|srcTermIn:…|…}}` or `{{datetime_single as:date|…}}`
	 */
	public static function format_migration_target( array $entry ): string {
		$new_tag = $entry['new_tag'] ?? ( $entry['match_tag'] ?? '' );
		if ( '' === $new_tag ) {
			return '';
		}

		$pairs = array();
		$src   = $entry['source_inject'] ?? '';
		if ( '' !== $src ) {
			$pairs[] = 'src:' . $src;
		}
		foreach ( $entry['fixed_options'] ?? array() as $key => $value ) {
			if ( '' !== (string) $value ) {
				$pairs[] = $key . ':' . $value;
			}
		}
		// Required options: keys whose presence is required for the migrated tag to
		// reproduce the deprecated tag's default behavior. Author-declared per entry.
		// Rendered as `<key>:…` placeholders so users see the must-set options.
		foreach ( $entry['required_options'] ?? array() as $req_key ) {
			$pairs[] = $req_key . ':…';
		}

		// Ellipsis (inside braces) when the entry carries user options via renames/value_renames/combine/datetime.
		$has_carry = ! empty( $entry['option_renames'] )
			|| ! empty( $entry['value_renames'] )
			|| ! empty( $entry['combine_options'] )
			|| ! empty( $entry['datetime_transforms'] );
		if ( $has_carry ) {
			$pairs[] = '…';
		}

		return empty( $pairs )
			? '{{' . $new_tag . '}}'
			: '{{' . $new_tag . ' ' . implode( '|', $pairs ) . '}}';
	}

	/**
	 * Group option-type registry entries that share the same transform.
	 *
	 * Entries differ only in match_tag are collapsed into a single row keyed by a
	 * transform signature -- every registry field except match_tag, new_tag and label,
	 * so a gate added to the registry later cannot silently merge unrelated entries.
	 * Old/new option-key lists are derived from option_renames + combine_options;
	 * reason is the parenthetical trailing the first entry's label.
	 *
	 * @param array[] $option_entries Registry entries (type:'option').
	 * @return array[] List of groups: [ 'tags' => string[], 'match_options' => string[],
	 *                 'old_keys' => string[], 'new_keys' => string[],
	 *                 'reason' => string, 'sample_entry' => array ].
	 */
	public static function group_option_entries_by_transform( array $option_entries ): array {
		$groups = array();
		foreach ( $option_entries as $entry ) {
			$tag = $entry['match_tag'] ?? '';
			if ( '' === $tag ) {
				continue;
			}
			// THE SIGNATURE IS DERIVED, NOT ENUMERATED, and that is the rule: two entries
			// group together when everything except which TAG they match is identical.
			// The list this replaced named seven fields, and a gate added after it was
			// written (`match_option_values`, #56) was not among them — so every entry
			// whose only gate is a value map, or whose whole behaviour is a
			// `transform_callback`, hashed identical to every other one and the Settings
			// page merged unrelated migrations into a single mislabelled row. An
			// enumeration has to be revisited each time the registry grows a field, and
			// nothing fails when it is not; deriving cannot go stale.
			//
			// The three exclusions are the ones that vary WITHIN a group by construction:
			// `match_tag` is what the group collapses, `new_tag` mirrors it on every
			// entry that is not a rename, and `label` embeds the tag name via sprintf.
			$sig_fields = $entry;
			unset( $sig_fields['match_tag'], $sig_fields['new_tag'], $sig_fields['label'] );
			ksort( $sig_fields );
			$signature = md5( (string) wp_json_encode( $sig_fields ) );
			if ( ! isset( $groups[ $signature ] ) ) {
				$label  = $entry['label'] ?? '';
				$reason = '';
				if ( preg_match( '/\(([^)]+)\)\s*$/', $label, $m ) ) {
					$reason = $m[1];
				}

				// Build old-key and new-key lists from structured fields.
				$old_keys = array();
				$new_keys = array();
				foreach ( $entry['option_renames'] ?? array() as $old => $new ) {
					$old_keys[] = $old;
					if ( '' !== $new ) {
						$new_keys[] = $new;
					}
				}
				foreach ( $entry['combine_options'] ?? array() as $new_key => $spec ) {
					if ( ! empty( $spec['when_present'] ) ) {
						$old_keys[] = $spec['when_present'];
					}
					if ( ! empty( $spec['value_from'] ) ) {
						$old_keys[] = $spec['value_from'];
					}
					$new_keys[] = $new_key;
				}
				$old_keys = array_values( array_unique( $old_keys ) );
				$new_keys = array_values( array_unique( $new_keys ) );

				$groups[ $signature ] = array(
					'tags'              => array(),
					'match_options'     => $entry['match_options']     ?? array(),
					'match_any_options' => $entry['match_any_options'] ?? array(),
					'old_keys'          => $old_keys,
					'new_keys'          => $new_keys,
					'reason'            => $reason,
					'sample_entry'      => $entry,
				);
			}
			$groups[ $signature ]['tags'][] = $tag;
		}
		return array_values( $groups );
	}

	// ===============================================
	// RENDER
	// ===============================================

	/**
	 * Render the settings page.
	 *
	 * Deprecated/Removed box structural rules (were SPEC §V5/§V6; migrated here on ship):
	 * - **Empty-cascade is structural, not per-row.** A bucket (a K/S/D with-path/without-path
	 *   subgroup, or a Removed tag-list/option-list) with zero entries after the allowlist
	 *   filter hides its ENTIRE heading + description + control block, not just an empty
	 *   disclosure. If all four boxes (Deprecated/Removed × Tags/Options) end up empty, the
	 *   whole group renders nothing.
	 * - **The Migration Tool box has NO hide condition** — it always renders regardless of every
	 *   other box's item count, so an admin whose boxes all hid (e.g. after migrating the last
	 *   matching post) still has the scan/migrate entry point.
	 *
	 * Entry classification (Deprecated vs Removed) is MigrationRegistry::is_entry_live()
	 * (CONTEXT.md I10). The allowlist hide-filter (positive list) and the "show all" bypass are
	 * TagConverter::get_allowlist() / is_show_all_deprecated_enabled() (V7/V8, PHPDoc there).
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();

		$mode_with    = $settings['deprecated']['mode_with_path']    ?? 'keep';
		$mode_without = $settings['deprecated']['mode_without_path'] ?? 'keep';

		// Scan allowlist: hides zero-match entries unless "show all" is on (V7/V8).
		$show_all  = self::is_show_all_deprecated_enabled();
		$allowlist = TagConverter::get_allowlist();
		$tag_in_allowlist = fn( array $e ) => in_array( $e['old_tag'] ?? $e['match_tag'] ?? '', $allowlist['tags'], true );
		$option_in_allowlist = fn( array $e ) => in_array( $e['label'] ?? '', $allowlist['option_labels'], true );

		// Split tag-type entries by liveness first (still GB-registered vs removed/inert),
		// then by migration path within each liveness bucket.
		$live_entries       = MigrationRegistry::get_by_type_and_liveness( 'tag', true );
		$removed_entries    = MigrationRegistry::get_by_type_and_liveness( 'tag', false );
		if ( ! $show_all ) {
			$live_entries    = array_values( array_filter( $live_entries, $tag_in_allowlist ) );
			$removed_entries = array_values( array_filter( $removed_entries, $tag_in_allowlist ) );
		}
		$live_with          = array_values( array_filter( $live_entries, fn( $e ) => ! empty( $e['new_tag'] ) ) );
		$live_without       = array_values( array_filter( $live_entries, fn( $e ) => empty( $e['new_tag'] ) ) );
		$removed_with       = array_values( array_filter( $removed_entries, fn( $e ) => ! empty( $e['new_tag'] ) ) );
		$removed_without    = array_values( array_filter( $removed_entries, fn( $e ) => empty( $e['new_tag'] ) ) );

		// Deprecated option-key entries (separate registry type).
		$live_option_entries    = MigrationRegistry::get_by_type_and_liveness( 'option', true );
		$removed_option_entries = MigrationRegistry::get_by_type_and_liveness( 'option', false );
		if ( ! $show_all ) {
			$live_option_entries    = array_values( array_filter( $live_option_entries, $option_in_allowlist ) );
			$removed_option_entries = array_values( array_filter( $removed_option_entries, $option_in_allowlist ) );
		}

		$mode_options = array(
			'keep'     => __( 'Keep — tags work normally', 'generateblocks' ),
			'suppress' => __( 'Suppress — tags register but output nothing (safe frontend fallback)', 'generateblocks' ),
			'disable'  => __( 'Disable — tags are removed from GB (use only after migrating all content)', 'generateblocks' ),
		);
		?>
		<div class="wrap bws-dynamic-tags-settings">
			<h1><?php esc_html_e( 'BWS Dynamic Tag Extensions', 'generateblocks' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'bws_dynamic_tags_settings_group' ); ?>

				<?php /* ── Modifier Groups ── */ ?>
				<div class="bws-tag-group">
					<h2 class="bws-section-header"><?php esc_html_e( 'Modifier Groups', 'generateblocks' ); ?></h2>
					<table class="bws-tags-table widefat">
						<tbody>
							<tr class="bws-tag-row">
								<td class="bws-tag-checkbox">
									<input type="checkbox" id="bws-modifier-term"
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[modifiers][term]"
										value="1" <?php checked( self::is_modifier_enabled( 'term' ) ); ?> />
								</td>
								<td>
									<label for="bws-modifier-term"><?php esc_html_e( 'term_ tags', 'generateblocks' ); ?></label>
									<code class="bws-tag-name">term_</code>
									<p class="description"><?php esc_html_e( 'Term-context tags (term_text, term_image, term_title, etc.).', 'generateblocks' ); ?></p>
								</td>
							</tr>
							<tr class="bws-tag-row">
								<td class="bws-tag-checkbox">
									<input type="checkbox" id="bws-modifier-try"
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[modifiers][try]"
										value="1" <?php checked( self::is_modifier_enabled( 'try' ) ); ?> />
								</td>
								<td>
									<label for="bws-modifier-try"><?php esc_html_e( 'try_ tags', 'generateblocks' ); ?></label>
									<code class="bws-tag-name">try_</code>
									<p class="description"><?php esc_html_e( 'Fallback-chain tags (try_text, try_image, etc.).', 'generateblocks' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<?php /* ── Email ── */ ?>
				<div class="bws-tag-group">
					<h2 class="bws-section-header"><?php esc_html_e( 'Email', 'generateblocks' ); ?></h2>
					<table class="bws-tags-table widefat">
						<tbody>
							<tr class="bws-tag-row">
								<td class="bws-tag-checkbox">
									<input type="checkbox" id="bws-email-obfuscate"
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[email][obfuscate]"
										value="1" <?php checked( self::is_email_obfuscation_enabled() ); ?> />
								</td>
								<td>
									<label for="bws-email-obfuscate"><?php esc_html_e( 'Obfuscate email addresses (anti-harvest)', 'generateblocks' ); ?></label>
									<p class="description"><?php esc_html_e( 'Encode addresses output by the {{email}} tag with antispambot() to deter naive harvesters. Disable if a clean mailto: href is needed (e.g. analytics).', 'generateblocks' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<?php /* ── Phone ── */ ?>
				<div class="bws-tag-group">
					<h2 class="bws-section-header"><?php esc_html_e( 'Phone', 'generateblocks' ); ?></h2>
					<table class="bws-tags-table widefat">
						<tbody>
							<tr class="bws-tag-row">
								<td class="bws-tag-checkbox">
									<input type="text" id="bws-phone-country-code" style="width:14em"
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[phone][country_code]"
										value="<?php echo esc_attr( self::get_phone_country_code() ); ?>"
										inputmode="numeric" placeholder="<?php esc_attr_e( 'e.g. 1 (US), 44 (UK)', 'generateblocks' ); ?>" />
								</td>
								<td>
									<label for="bws-phone-country-code"><?php esc_html_e( 'Default country code', 'generateblocks' ); ?></label>
									<p class="description">
										<?php esc_html_e( 'Default country code (digits only, no +) for {{phone}} tel: links when a number has no international prefix. Leave empty for national-only tel: links.', 'generateblocks' ); ?>
										<a href="https://www.countrycode.org" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Country code reference', 'generateblocks' ); ?></a>
									</p>
								</td>
							</tr>
							<tr class="bws-tag-row">
								<td class="bws-tag-checkbox">
									<input type="checkbox" id="bws-phone-strip-cc"
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[phone][strip_leading_cc]"
										value="1" <?php checked( self::is_phone_strip_leading_cc_enabled() ); ?> />
								</td>
								<td>
									<label for="bws-phone-strip-cc"><?php esc_html_e( 'Strip unseparated leading digit(s) matching the default country code', 'generateblocks' ); ?></label>
									<p class="description"><?php esc_html_e( 'Numbers where the country code is set off by a separator (e.g. 1-800-555-1212, 1 (800) 555-1212, +1 800 555 1212) are already detected automatically — no setting needed. This option covers only the harder case: a country code run TOGETHER with the national number and no + (e.g. 18005551212 with a default code of 1), where there is no separator to mark it. Requires a country code above; only strips a leading run that exactly matches it.', 'generateblocks' ); ?>
										<strong><?php esc_html_e( 'Warning:', 'generateblocks' ); ?></strong> <?php esc_html_e( 'with no separator there is no way to tell a real country-code prefix from a national number that simply begins with the same digits, so this can strip a legitimate leading digit (e.g. a national number 1860… with default code 1). Leave off unless your stored numbers consistently carry a redundant, unseparated country-code prefix.', 'generateblocks' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<?php /* ── Call Custom Function (read-only allowlist mirror) ── */ ?>
				<?php $bws_call_rows = self::get_call_allowlist_status(); ?>
				<div class="bws-tag-group">
					<h2 class="bws-section-header"><?php esc_html_e( 'Call Custom Function', 'generateblocks' ); ?></h2>
					<div class="bws-section-desc">
						<p style="margin-top:0">
							<?php esc_html_e( 'Custom functions must take a post ID as their first argument, sanitize their own output (HTML is allowed in the return string), and be registered via `bws_register_call_function()`:', 'generateblocks' ); ?>
						</p>
						<pre style="margin:0 0 8px;padding:8px 10px;background:#f6f7f7;border:1px solid #dcdcde;overflow:auto"><code>add_action( 'init', function () {
    bws_register_call_function( 'my_result' );
} );

function my_result( $post_id, $arg = '' ) {
    return '&lt;span&gt;' . esc_html( get_the_title( $post_id ) ) . '&lt;/span&gt;';
}</code></pre>
						<p style="margin:0">
							<?php esc_html_e( 'Properly registered functions will appear in the {{call}} tag\'s Function dropdown for easy access. Manually inserting an unregistered function in a {{call}} tag will cause it to return the tag\'s fallback text, if available, or return empty.', 'generateblocks' ); ?>
						</p>
					</div>
					<h3 class="bws-subhead"><?php esc_html_e( 'Registered functions', 'generateblocks' ); ?></h3>
					<table class="bws-tags-table widefat">
						<tbody>
						<?php if ( empty( $bws_call_rows ) ) : ?>
							<tr class="bws-tag-row">
								<td colspan="2"><em><?php esc_html_e( 'No functions registered yet.', 'generateblocks' ); ?></em></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $bws_call_rows as $bws_call_row ) : ?>
								<?php
								$bws_call_ok = $bws_call_row['exists'] && $bws_call_row['passes'];
								// Glyph = at-a-glance status. Text shown ONLY for a failure (the
								// reason a glyph can't carry); an OK row needs no redundant "OK".
								$bws_call_problem = $bws_call_ok
									? ''
									: ( ! $bws_call_row['exists']
										? __( 'Not found (function does not exist)', 'generateblocks' )
										: __( 'Refused (PHP built-in)', 'generateblocks' ) );
								?>
								<tr class="bws-tag-row">
									<td class="bws-tag-checkbox" style="width:1.5em">
										<span class="<?php echo $bws_call_ok ? 'bws-call-ok' : 'bws-call-warn'; ?>" aria-hidden="true"><?php echo $bws_call_ok ? '✓' : '⚠'; ?></span>
										<span class="screen-reader-text"><?php echo $bws_call_ok ? esc_html__( 'OK', 'generateblocks' ) : esc_html( $bws_call_problem ); ?></span>
									</td>
									<td>
										<code><?php echo esc_html( $bws_call_row['fn'] ); ?></code>
										<?php if ( '' !== $bws_call_problem ) : ?>
											<span class="bws-tag-name"><?php echo esc_html( $bws_call_problem ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
						</tbody>
					</table>
				</div>

				<?php /* ── Deprecated Tags (still GB-registered; K/S/D applies) ── */ ?>
				<?php if ( ! empty( $live_with ) || ! empty( $live_without ) ) : ?>
				<div class="bws-tag-group">
					<h2 class="bws-section-header"><?php esc_html_e( 'Deprecated Tags', 'generateblocks' ); ?></h2>
					<p class="description bws-section-desc">
						<?php esc_html_e( 'Control how deprecated tags behave. Use the Migration Tool below to find and update content before disabling.', 'generateblocks' ); ?>
					</p>

					<?php foreach ( array(
						array(
							'key'     => 'mode_with_path',
							'label'   => __( 'Tags with migration path', 'generateblocks' ),
							'desc'    => __( 'These deprecated tags can be automatically converted to current equivalents.', 'generateblocks' ),
							'current' => $mode_with,
							'entries' => $live_with,
						),
						array(
							'key'     => 'mode_without_path',
							'label'   => __( 'Tags without migration path', 'generateblocks' ),
							'desc'    => __( 'These deprecated tags have no automatic conversion. Manual update required before disabling.', 'generateblocks' ),
							'current' => $mode_without,
							'entries' => $live_without,
						),
					) as $group ) :
						if ( empty( $group['entries'] ) ) { continue; }
					?>

					<div class="bws-dep-group">
						<h3 class="bws-dep-group-header"><?php echo esc_html( $group['label'] ); ?></h3>
						<p class="description"><?php echo esc_html( $group['desc'] ); ?></p>

						<div class="bws-mode-radios">
							<?php foreach ( $mode_options as $val => $label ) : ?>
							<label class="bws-mode-radio-label">
								<input type="radio"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[deprecated][<?php echo esc_attr( $group['key'] ); ?>]"
									value="<?php echo esc_attr( $val ); ?>"
									<?php checked( $group['current'], $val ); ?> />
								<?php echo esc_html( $label ); ?>
							</label>
							<?php endforeach; ?>
						</div>

						<details class="bws-dep-tag-list">
							<summary><?php
								echo esc_html( sprintf(
									/* translators: %d: count of deprecated tags */
									_n( '%d deprecated tag', '%d deprecated tags', count( $group['entries'] ), 'generateblocks' ),
									count( $group['entries'] )
								) );
							?></summary>
							<table class="bws-tags-table widefat bws-ref-table">
								<tbody>
								<?php foreach ( $group['entries'] as $entry ) :
									$old_tag = $entry['old_tag'] ?? $entry['match_tag'] ?? '';
									$new_tag = $entry['new_tag'] ?? '';
									$since   = $entry['since']   ?? '';
									if ( '' === $old_tag ) { continue; }
									$target_string = $new_tag ? self::format_migration_target( $entry ) : '';
								?>
									<tr class="bws-tag-row">
										<td>
											<code class="bws-tag-name"><?php echo esc_html( '{{' . $old_tag . '}}' ); ?></code>
											<?php if ( $target_string ) : ?>
											<span class="bws-dep-arrow">→</span>
											<code class="bws-tag-name bws-new-tag"><?php echo esc_html( $target_string ); ?></code>
											<?php endif; ?>
											<?php if ( $since ) : ?>
											<span class="bws-dep-since"><?php echo esc_html( sprintf(
												/* translators: %s: version */
												__( '(since %s)', 'generateblocks' ), $since
											) ); ?></span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</details>
					</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<?php /* ── Removed Tags (GB registration gone; informational only) ── */ ?>
				<?php if ( ! empty( $removed_with ) || ! empty( $removed_without ) ) : ?>
				<div class="bws-tag-group">
					<h2 class="bws-section-header"><?php esc_html_e( 'Removed Tags', 'generateblocks' ); ?></h2>
					<p class="description bws-section-desc">
						<?php esc_html_e( 'These tag names no longer register with GenerateBlocks. Use the Migration Tool below to find and update content still using them.', 'generateblocks' ); ?>
					</p>

					<?php foreach ( array(
						array(
							'label'   => __( 'Tags with migration path', 'generateblocks' ),
							'desc'    => __( 'These removed tags can be automatically converted to current equivalents.', 'generateblocks' ),
							'entries' => $removed_with,
						),
						array(
							'label'   => __( 'Tags without migration path', 'generateblocks' ),
							'desc'    => __( 'These removed tags have no automatic conversion. Manual update required.', 'generateblocks' ),
							'entries' => $removed_without,
						),
					) as $group ) :
						if ( empty( $group['entries'] ) ) { continue; }
					?>

					<div class="bws-dep-group">
						<h3 class="bws-dep-group-header"><?php echo esc_html( $group['label'] ); ?></h3>
						<p class="description"><?php echo esc_html( $group['desc'] ); ?></p>

						<details class="bws-dep-tag-list">
							<summary><?php
								echo esc_html( sprintf(
									/* translators: %d: count of removed tags */
									_n( '%d removed tag', '%d removed tags', count( $group['entries'] ), 'generateblocks' ),
									count( $group['entries'] )
								) );
							?></summary>
							<table class="bws-tags-table widefat bws-ref-table">
								<tbody>
								<?php foreach ( $group['entries'] as $entry ) :
									$old_tag = $entry['old_tag'] ?? $entry['match_tag'] ?? '';
									$new_tag = $entry['new_tag'] ?? '';
									$since   = $entry['since']   ?? '';
									if ( '' === $old_tag ) { continue; }
									$target_string = $new_tag ? self::format_migration_target( $entry ) : '';
								?>
									<tr class="bws-tag-row">
										<td>
											<code class="bws-tag-name"><?php echo esc_html( '{{' . $old_tag . '}}' ); ?></code>
											<?php if ( $target_string ) : ?>
											<span class="bws-dep-arrow">→</span>
											<code class="bws-tag-name bws-new-tag"><?php echo esc_html( $target_string ); ?></code>
											<?php endif; ?>
											<?php if ( $since ) : ?>
											<span class="bws-dep-since"><?php echo esc_html( sprintf(
												/* translators: %s: version */
												__( '(since %s)', 'generateblocks' ), $since
											) ); ?></span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</details>
					</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<?php /* ── Deprecated Options (fallback still live in reading code) ── */ ?>
				<?php $live_option_groups = self::group_option_entries_by_transform( $live_option_entries ); ?>
				<?php if ( ! empty( $live_option_groups ) ) : ?>
				<div class="bws-tag-group">
					<h2 class="bws-section-header"><?php esc_html_e( 'Deprecated Options', 'generateblocks' ); ?></h2>
					<p class="description bws-section-desc">
						<?php esc_html_e( 'Option-key migrations applied to current tags when stored content uses old option names. The Migration Tool below rewrites stored content; option-type migrations always have an automatic conversion path.', 'generateblocks' ); ?>
					</p>

					<div class="bws-dep-group">
						<details class="bws-dep-tag-list">
							<summary><?php
								echo esc_html( sprintf(
									/* translators: %d: count of deprecated option migrations */
									_n( '%d deprecated option migration', '%d deprecated option migrations', count( $live_option_groups ), 'generateblocks' ),
									count( $live_option_groups )
								) );
							?></summary>
							<table class="bws-tags-table widefat bws-ref-table">
								<tbody>
								<?php foreach ( $live_option_groups as $group_entry ) :
									$tags     = array_unique( $group_entry['tags'] );
									$old_keys = $group_entry['old_keys'];
									$new_keys = $group_entry['new_keys'];
									$reason   = $group_entry['reason'];
									$old_html = implode( ' + ', array_map( fn( $k ) => '<code>' . esc_html( $k ) . '</code>', $old_keys ) );
									$new_html = implode( ' + ', array_map( fn( $k ) => '<code>' . esc_html( $k ) . '</code>', $new_keys ) );
								?>
									<tr class="bws-tag-row">
										<td>
											<div class="bws-dep-rename">
												<?php echo $old_html; ?>
												<?php if ( '' !== $old_html && '' !== $new_html ) : ?>
												<span class="bws-dep-arrow">→</span>
												<?php endif; ?>
												<?php echo $new_html; ?>
												<?php if ( $reason ) : ?>
												<span class="bws-dep-reason"><?php echo esc_html( '(' . $reason . ')' ); ?></span>
												<?php endif; ?>
											</div>
											<?php if ( ! empty( $tags ) ) : ?>
											<div class="description bws-dep-applies">
												<?php esc_html_e( 'Applies to:', 'generateblocks' ); ?>
												<?php
												$pieces = array_map( fn( $t ) => '<code class="bws-tag-name">' . esc_html( $t ) . '</code>', $tags );
												echo implode( ', ', $pieces );
												?>
											</div>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</details>
					</div>
				</div>
				<?php endif; ?>

				<?php /* ── Removed Options (legacy-key fallback deleted from reading code) ── */ ?>
				<?php $removed_option_groups = self::group_option_entries_by_transform( $removed_option_entries ); ?>
				<?php if ( ! empty( $removed_option_groups ) ) : ?>
				<div class="bws-tag-group">
					<h2 class="bws-section-header"><?php esc_html_e( 'Removed Options', 'generateblocks' ); ?></h2>
					<p class="description bws-section-desc">
						<?php esc_html_e( 'These option-key corrections no longer have a live fallback in the reading code — the old key is no longer accepted at all. The Migration Tool below still finds and fixes stored content using the old key.', 'generateblocks' ); ?>
					</p>

					<div class="bws-dep-group">
						<details class="bws-dep-tag-list">
							<summary><?php
								echo esc_html( sprintf(
									/* translators: %d: count of removed option migrations */
									_n( '%d removed option migration', '%d removed option migrations', count( $removed_option_groups ), 'generateblocks' ),
									count( $removed_option_groups )
								) );
							?></summary>
							<table class="bws-tags-table widefat bws-ref-table">
								<tbody>
								<?php foreach ( $removed_option_groups as $group_entry ) :
									$tags     = array_unique( $group_entry['tags'] );
									$old_keys = $group_entry['old_keys'];
									$new_keys = $group_entry['new_keys'];
									$reason   = $group_entry['reason'];
									$old_html = implode( ' + ', array_map( fn( $k ) => '<code>' . esc_html( $k ) . '</code>', $old_keys ) );
									$new_html = implode( ' + ', array_map( fn( $k ) => '<code>' . esc_html( $k ) . '</code>', $new_keys ) );
								?>
									<tr class="bws-tag-row">
										<td>
											<div class="bws-dep-rename">
												<?php echo $old_html; ?>
												<?php if ( '' !== $old_html && '' !== $new_html ) : ?>
												<span class="bws-dep-arrow">→</span>
												<?php endif; ?>
												<?php echo $new_html; ?>
												<?php if ( $reason ) : ?>
												<span class="bws-dep-reason"><?php echo esc_html( '(' . $reason . ')' ); ?></span>
												<?php endif; ?>
											</div>
											<?php if ( ! empty( $tags ) ) : ?>
											<div class="description bws-dep-applies">
												<?php esc_html_e( 'Applies to:', 'generateblocks' ); ?>
												<?php
												$pieces = array_map( fn( $t ) => '<code class="bws-tag-name">' . esc_html( $t ) . '</code>', $tags );
												echo implode( ', ', $pieces );
												?>
											</div>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</details>
					</div>
				</div>
				<?php endif; ?>

				<?php /* ── Migration Tool (AJAX driven) ── */ ?>
				<div class="bws-tag-group bws-migration-tool" id="bws-migration-tool">
					<h2 class="bws-section-header"><?php esc_html_e( 'Migration Tool', 'generateblocks' ); ?></h2>
					<p class="description bws-section-desc">
						<?php esc_html_e( 'Scan all post content for deprecated tags and option issues, then migrate per post or in bulk. A revision is created before each migration when the post type supports it. Block pattern caches are reconciled on every scan and migration, so the pattern library stops handing out pre-migration tags.', 'generateblocks' ); ?>
					</p>
					<?php /* US 26 — DISCLOSURE, not enumeration. A reported count with no stated
					        boundary reads as a completeness claim it cannot back; an inventory
					        answers a different question ("so I can go fix them"), deferred to #100. */ ?>
					<p class="description bws-section-desc">
						<?php esc_html_e( 'Covers post content and the block pattern cache. Tag strings stored elsewhere, such as in custom field values or another plugin\'s cache, are not reached by this tool.', 'generateblocks' ); ?>
					</p>

					<div class="bws-scan-controls">
						<button type="button" id="bws-scan-btn" class="button button-primary">
							<?php esc_html_e( 'Scan All Content', 'generateblocks' ); ?>
						</button>
						<span id="bws-scan-status" class="bws-scan-status" aria-live="polite"></span>
					</div>

					<?php /* The pattern-cache result, PERSISTED rather than a transient status
					        line: the on-upgrade trigger has no interface at all and is the one
					        actually repairing already-converted sites. The element is rendered
					        even when empty so the AJAX handlers can fill it without a reload. */ ?>
					<p class="description bws-pattern-cache-status" id="bws-pattern-cache-status" aria-live="polite">
						<?php echo esc_html( PatternCache::format_status( PatternCache::get_status() ) ); ?>
					</p>

					<div id="bws-scan-results" style="display:none;">
						<div class="bws-results-toolbar">
							<label>
								<input type="checkbox" id="bws-select-all" />
								<span id="bws-select-all-label"><?php esc_html_e( 'Select all', 'generateblocks' ); ?></span>
							</label>
							<button type="button" id="bws-migrate-selected-btn" class="button" disabled>
								<?php esc_html_e( 'Migrate Selected', 'generateblocks' ); ?>
							</button>
							<div class="bws-progress-wrap" id="bws-progress-wrap" style="display:none;">
								<div class="bws-progress-bar"><div class="bws-progress-fill" id="bws-progress-fill"></div></div>
								<span id="bws-progress-label"></span>
							</div>
						</div>
						<table class="bws-tags-table widefat bws-results-table" id="bws-results-table">
							<thead>
								<tr>
									<th class="bws-cb-col"></th>
									<th><?php esc_html_e( 'Post', 'generateblocks' ); ?></th>
									<th><?php esc_html_e( 'Type', 'generateblocks' ); ?></th>
									<th><?php esc_html_e( 'Issues Found', 'generateblocks' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'generateblocks' ); ?></th>
								</tr>
							</thead>
							<tbody id="bws-results-tbody"></tbody>
						</table>
					</div>
				</div>

				<?php /* ── Diagnostics: settings, then read-only reports ── */ ?>
				<div class="bws-tag-group">
					<?php
					// ONE HEADING OVER TWO SUBSECTIONS, so the heading names the CATEGORY and each
					// subhead names its subject. The conflicts half changes nothing and only
					// reports; the settings half changes behaviour. Labelling only one of them
					// would leave the other orphaned, which is why "Settings" exists at all --
					// three checkboxes do not need a name until something else sits beside them.
					//
					// THE REPORT COMES FIRST, THE SWITCHES SECOND. A reader arriving at Diagnostics
					// is answering "did something break", and the answer is a finding, not a
					// checkbox; the settings here only decide what gets written to a log for a
					// LATER look. Ordering by what is read rather than by what is editable also
					// keeps the one subsection that can carry a warning above the fold.
					//
					// Tag Name Conflicts was its own H2 until this. It was `Integration Status` with
					// a subhead before that, narrowed on 2026-08-26 when its second subsection
					// (dependency-version drift) was cut and deferred as FW-99, on the rule that one
					// heading over one table names the subject rather than the category. The rule
					// holds; what changed is that the block is no longer alone. FW-99 lands here as
					// a third subhead rather than as a section of its own.
					//
					// The shape is not invented here: Call Custom Function is one H2 over its own
					// settings plus a `Registered functions` subhead labelling a read-only table.
					?>
					<h2 class="bws-section-header"><?php esc_html_e( 'Diagnostics', 'generateblocks' ); ?></h2>

					<?php /* ── Tag Name Conflicts (read-only; NOT config, changes nothing) ── */ ?>
					<?php
					// The read happens HERE, at render, and that is load-bearing: the collision
					// record is request-scoped and is only complete after `wp_loaded`. See
					// get_tag_collision_status().
					$bws_collisions = self::get_tag_collision_status();
					?>
					<h3 class="bws-subhead" id="bws-tag-name-conflicts"><?php esc_html_e( 'Tag Name Conflicts', 'generateblocks' ); ?></h3>
					<?php
					// TWO USER-FACING STATES OVER THE RECORD'S THREE OUTCOMES, and the map from one
					// to the other lives HERE, at the only surface that collapses them. The record
					// keeps 'kept' / 'lost' / 'yielded' apart because they are different events with
					// different DEVELOPER remedies, and its merge rule depends on the distinction --
					// see bws_gb_tag_name_collisions(). This page has a different reader. To a site
					// owner, 'lost' and 'yielded' say one thing: another plugin's code answers for
					// this name and ours does not. Which of the two put us there is invisible from
					// the editor, invisible on the front end, and asks for the same action.
					//
					// THE PROSE IS A PROPERTY OF THE STATE, SO IT PRINTS ONCE PER STATE, BELOW THE
					// TABLE. It used to print per ROW, which meant three tags standing down behind
					// one plugin printed the same paragraph three times.
					//
					// REMEDIES ARE OWNER ACTIONS. Renaming a tag, or moving a registration to a
					// different init priority, is something only whoever ships the plugin can do.
					// Those instructions stay in the _doing_it_wrong() notices, whose reader needs
					// WP_DEBUG and a log file and is a developer by construction.
					$bws_c_state_of = array(
						'kept'    => 'ours',
						'lost'    => 'theirs',
						'yielded' => 'theirs',
					);

					$bws_c_states = array(
						// Order is the order the legend prints in: the state that leaves this
						// plugin's tag inactive is the one a reader came here about.
						'theirs'  => array(
							'icon'    => 'warn',
							'heading' => __( 'Another plugin\'s tag is active', 'generateblocks' ),
							'long'    => __( 'Another plugin registered this tag name, so this plugin\'s version is inactive. If you previously inserted any instances of our version of the tag into your content, they still carry the settings you chose, but the other plugin likely does not recognize them, so their output may be different from what you intended.', 'generateblocks' ),
							'remedy'  => __( 'If you want this plugin\'s version, deactivate the other plugin, or ask either developer for help with the conflict.', 'generateblocks' ),
						),
						'ours'    => array(
							'icon'    => 'info',
							'heading' => __( 'This plugin\'s tag is active', 'generateblocks' ),
							'long'    => __( 'Another plugin registered this tag name first, but this plugin registered over it, so their version is inactive. If you previously inserted any instances of their version of the tag into your content, they still carry the settings you chose, but this plugin likely does not recognize them, so their output may be different from what you intended.', 'generateblocks' ),
							'remedy'  => __( 'If the other plugin\'s version is the one you want, deactivate this plugin, or ask either developer for help with the conflict.', 'generateblocks' ),
						),
						// UNREACHABLE BY CONSTRUCTION, AND KEPT ANYWAY. The three outcome values are
						// written at three sites in gb-registration-boundary.php and nowhere else,
						// and no filter reaches the record. What this state buys is the behaviour on
						// the day a FOURTH outcome is added there: a row saying a conflict exists,
						// rather than a blank status and an undefined-index notice, on a diagnostics
						// page whose whole job is to stay readable when something is wrong.
						'unknown' => array(
							'icon'    => 'warn',
							'heading' => __( 'Tag name conflict', 'generateblocks' ),
							'long'    => __( 'Two plugins registered this tag name. Which one is active could not be determined.', 'generateblocks' ),
							'remedy'  => __( 'Deactivate one of the two plugins, or ask either developer for help with the conflict.', 'generateblocks' ),
						),
					);

					// Only the states that actually occurred get prose printed for them.
					$bws_c_seen = array();
					?>
					<?php if ( empty( $bws_collisions ) ) : ?>
						<?php
						// THE EMPTY STATE CARRIES THE EXPLANATION, because nothing else is left to
						// carry it. Where there ARE conflicts the legend under the table says all of
						// this and says it per state, so an intro paragraph above the table would be
						// a third copy of the same warning. Where there are none there is no legend,
						// so this one sentence says both what the subsection is for and what it
						// found -- and a table with no columns holding a sentence is not a table.
						?>
						<div class="bws-section-desc bws-conflict-none">
							<p style="margin:0">
								<span class="bws-call-ok" aria-hidden="true">✓</span>
								<?php esc_html_e( 'If more than one plugin registers the same tag name, frontend output can change without warning. As of now, this plugin can register all its tags without conflict.', 'generateblocks' ); ?>
							</p>
						</div>
					<?php else : ?>
					<table class="bws-tags-table widefat">
						<thead>
								<tr>
									<th style="width:1.5em"></th>
									<th><?php esc_html_e( 'Tag', 'generateblocks' ); ?></th>
									<th><?php esc_html_e( 'Status', 'generateblocks' ); ?></th>
									<th><?php esc_html_e( 'Other plugin', 'generateblocks' ); ?></th>
									<th><?php esc_html_e( 'Registered in', 'generateblocks' ); ?></th>
								</tr>
							</thead>
						<tbody>
							<?php foreach ( $bws_collisions as $bws_collision ) : ?>
								<?php
								$bws_c_key   = $bws_c_state_of[ $bws_collision['outcome'] ] ?? 'unknown';
								$bws_c_state = $bws_c_states[ $bws_c_key ];

								$bws_c_seen[ $bws_c_key ] = true;

								// THE OWNER IS A PLUGIN, NEVER THE OTHER TAG'S LABEL: `title` says
								// "Term Title", which reads as a party while naming nothing anybody
								// can go and deactivate. The name is derived off the accessor above;
								// '' means it could not be told from the file, and the stand-in says
								// exactly that rather than putting a tag label in an owner's place.
								$bws_c_subject = $bws_collision[ $bws_collision['subject'] ];
								$bws_c_owner   = '' !== $bws_c_subject['plugin']
									? $bws_c_subject['plugin']
									: __( 'another plugin', 'generateblocks' );

								// BOTH PARTIES, IN THE ORDER THEY HELD THE NAME. A record that merged
								// a takeover onto an earlier conflict carries two strangers, and
								// showing one of them discards the half the merge exists to preserve.
								// The earlier one rides under the party column as a second line: it
								// is context for who is contesting the name, not a status of its own.
								// WHICH pair of record fields is which party is not decided here --
								// see bws_gb_collision_other_parties().
								$bws_c_other = $bws_collision[ 'before' === $bws_collision['subject'] ? 'after' : 'before' ];
								$bws_c_prior = null !== $bws_c_other && '' !== $bws_c_other['plugin'] ? $bws_c_other['plugin'] : '';
								?>
								<tr class="bws-tag-row">
									<td class="bws-tag-checkbox" style="width:1.5em">
										<?php
										// Decorative, and it carries NO screen-reader text: the status
										// is printed as visible text two cells along, so a span here
										// would have it read out twice in a row. The glyph varies by
										// state rather than marking every row the same way, which told
										// a reader only what the table's presence already told them.
										?>
										<span class="<?php echo 'warn' === $bws_c_state['icon'] ? 'bws-call-warn' : 'bws-call-info'; ?>" aria-hidden="true"><?php echo 'warn' === $bws_c_state['icon'] ? '⚠' : 'ℹ'; ?></span>
									</td>
									<td><code><?php echo esc_html( '{{' . $bws_collision['tag'] . '}}' ); ?></code></td>
									<?php
									// The status text IS the legend heading below, word for word. That
									// is what links a row to the paragraph explaining it, with no
									// cross-reference to write and nothing to keep in step by hand.
									?>
									<td><?php echo esc_html( $bws_c_state['heading'] ); ?></td>
									<td>
										<strong><?php echo esc_html( $bws_c_owner ); ?></strong>
										<?php if ( '' !== $bws_c_subject['title'] ) : ?>
											<span class="bws-tag-name"><?php echo esc_html( $bws_c_subject['title'] ); ?></span>
										<?php endif; ?>
										<?php if ( '' !== $bws_c_prior ) : ?>
											<p class="description" style="margin:2px 0 0">
												<?php
												/* translators: %s: name of a plugin that held the tag name before either of the current two */
												echo esc_html( sprintf( __( 'before that: %s', 'generateblocks' ), $bws_c_prior ) );
												?>
											</p>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( '' !== $bws_c_subject['source'] ) : ?>
											<?php
											// THE PATH IS RELATIVE TO THE WORDPRESS ROOT ONLY WHEN THE
											// FILE IS UNDER IT, and absolute otherwise -- see
											// bws_gb_tag_registrar_file(). A symlinked plugins directory
											// is enough to make the absolute form the normal one
											// (measured on the fixture site), so it is not an edge case
											// and the two forms can sit side by side in one table.
											// Neither is dressed up as the other: trimming an absolute
											// path to look relative would stop it naming a file the
											// reader can open, which is the whole of what this is for.
											?>
											<code><?php echo esc_html( $bws_c_subject['source'] ); ?></code>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

						<div class="bws-conflict-legend">
							<?php foreach ( $bws_c_states as $bws_c_key => $bws_c_state ) : ?>
								<?php if ( empty( $bws_c_seen[ $bws_c_key ] ) ) : ?>
									<?php continue; ?>
								<?php endif; ?>
								<h4>
									<span class="<?php echo 'warn' === $bws_c_state['icon'] ? 'bws-call-warn' : 'bws-call-info'; ?>" aria-hidden="true"><?php echo 'warn' === $bws_c_state['icon'] ? '⚠' : 'ℹ'; ?></span>
									<?php echo esc_html( $bws_c_state['heading'] ); ?>
								</h4>
								<p class="description"><?php echo esc_html( $bws_c_state['long'] ); ?></p>
								<p class="description"><?php echo esc_html( $bws_c_state['remedy'] ); ?></p>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<h3 class="bws-subhead"><?php esc_html_e( 'Settings', 'generateblocks' ); ?></h3>
					<table class="bws-tags-table widefat">
						<tbody>
							<tr class="bws-tag-row">
								<td class="bws-tag-checkbox">
									<input type="checkbox" id="bws-diag-benchmark-logging"
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[diagnostics][benchmark_logging]"
										value="1" <?php checked( self::is_benchmark_logging_enabled() ); ?> />
								</td>
								<td>
									<label for="bws-diag-benchmark-logging"><?php esc_html_e( 'Enable benchmark logging', 'generateblocks' ); ?></label>
									<p class="description"><?php esc_html_e( 'Log post content processing time and memory usage to the PHP error log.', 'generateblocks' ); ?></p>
								</td>
							</tr>
							<tr class="bws-tag-row">
								<td class="bws-tag-checkbox">
									<input type="checkbox" id="bws-diag-registration-logging"
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[diagnostics][registration_logging]"
										value="1" <?php checked( self::is_registration_logging_enabled() ); ?> />
								</td>
								<td>
									<label for="bws-diag-registration-logging"><?php esc_html_e( 'Enable source registration logging', 'generateblocks' ); ?></label>
									<p class="description"><?php esc_html_e( 'Log source registration and the bws_dynamic_tags_register_sources action to the PHP error log.', 'generateblocks' ); ?></p>
								</td>
							</tr>
							<tr class="bws-tag-row">
								<td class="bws-tag-checkbox">
									<input type="checkbox" id="bws-diag-show-all-deprecated"
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[diagnostics][show_all_deprecated]"
										value="1" <?php checked( self::is_show_all_deprecated_enabled() ); ?> />
								</td>
								<td>
									<label for="bws-diag-show-all-deprecated"><?php esc_html_e( 'Show all deprecated/removed tags and options', 'generateblocks' ); ?></label>
									<p class="description"><?php esc_html_e( 'By default, only deprecated or removed tags and options which were found in site content at the last plugin upgrade or Migration Tool scan are shown. Enable this to list every registered entry regardless of scan results.', 'generateblocks' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>

				</div>

				<?php submit_button( __( 'Save Settings', 'generateblocks' ) ); ?>
			</form>
		</div>

		<style>
			.bws-dynamic-tags-settings .bws-tag-group { margin-bottom: 24px; }
			.bws-dynamic-tags-settings .bws-section-header {
				margin: 0 0 0;
				padding: 10px 12px;
				background: #f0f0f1;
				border: 1px solid #c3c4c7;
				font-size: 14px;
			}
			.bws-dynamic-tags-settings .bws-section-desc {
				padding: 8px 12px;
				margin: 0;
				background: #fff;
				border: 1px solid #c3c4c7;
				border-top: none;
				border-bottom: none;
			}
			.bws-dynamic-tags-settings .bws-subhead {
				margin: 0;
				padding: 8px 12px;
				background: #fff;
				border: 1px solid #c3c4c7;
				border-bottom: none;
				font-size: 13px;
			}
			.bws-dynamic-tags-settings .bws-call-ok   { color: #008a20; font-weight: 600; }
			.bws-dynamic-tags-settings .bws-call-warn { color: #b32d2e; font-weight: 600; }
			.bws-dynamic-tags-settings .bws-call-info { color: #2271b1; font-weight: 600; }
			/* A subhead that FOLLOWS a subsection needs air, but the air goes INSIDE the box:
			   every element in a group shares one continuous border, so a margin here breaks
			   one section into two floating boxes instead of separating two subsections. The
			   preceding table's bottom border is the divider; this only adds room above the
			   heading and drops the doubled border line. */
			.bws-dynamic-tags-settings .bws-tags-table + .bws-subhead,
			.bws-dynamic-tags-settings .bws-conflict-none + .bws-subhead,
			.bws-dynamic-tags-settings .bws-conflict-legend + .bws-subhead {
				margin-top: 0;
				padding-top: 18px;
				border-top: none;
			}
			/* The empty state is the only subsection that ends in a paragraph rather than a
			   table or the legend, so it carries the divider its neighbours get for free. */
			.bws-dynamic-tags-settings .bws-conflict-none { border-bottom: 1px solid #c3c4c7; }
			.bws-dynamic-tags-settings .bws-conflict-legend {
				padding: 4px 12px 12px;
				background: #fff;
				border: 1px solid #c3c4c7;
				border-top: none;
			}
			.bws-dynamic-tags-settings .bws-conflict-legend h4 { font-size: 13px; margin: 12px 0 4px; }
			.bws-dynamic-tags-settings .bws-conflict-legend p  { margin: 0 0 6px; }
			.bws-dynamic-tags-settings .bws-tags-table { border-top: none; }
			.bws-dynamic-tags-settings .bws-tag-row td { padding: 6px 12px; vertical-align: middle; }
			.bws-dynamic-tags-settings .bws-tag-checkbox { width: 30px; }
			.bws-dynamic-tags-settings .bws-tag-name { margin-left: 4px; font-size: 12px; color: #787c82; }
			.bws-dynamic-tags-settings .bws-new-tag { color: #2271b1; }
			.bws-dynamic-tags-settings .bws-dep-arrow { margin: 0 2px; color: #787c82; }
			.bws-dynamic-tags-settings .bws-dep-since { margin-left: 8px; font-size: 12px; color: #a0a0a0; }

			/* Deprecated group */
			.bws-dynamic-tags-settings .bws-dep-group {
				padding: 12px;
				background: #fff;
				border: 1px solid #c3c4c7;
				border-top: none;
			}
			.bws-dynamic-tags-settings .bws-dep-group + .bws-dep-group { border-top: 1px solid #c3c4c7; }
			.bws-dynamic-tags-settings .bws-dep-group-header { margin: 0 0 4px; font-size: 13px; }
			.bws-dynamic-tags-settings .bws-mode-radios { display: flex; flex-direction: column; gap: 4px; margin: 8px 0; }
			.bws-dynamic-tags-settings .bws-mode-radio-label { display: flex; align-items: center; gap: 6px; cursor: pointer; }
			.bws-dynamic-tags-settings .bws-dep-tag-list { margin-top: 8px; }
			.bws-dynamic-tags-settings .bws-dep-tag-list summary { cursor: pointer; color: #2271b1; font-size: 13px; }
			.bws-dynamic-tags-settings .bws-ref-table { margin-top: 6px; }
			.bws-dynamic-tags-settings .bws-ref-table td { padding: 3px 10px; }
			.bws-dynamic-tags-settings .bws-dep-label { margin: 2px 0 0 4px; font-size: 12px; color: #646970; }
			.bws-dynamic-tags-settings .bws-dep-applies { margin: 2px 0 0 4px; font-size: 12px; color: #646970; }
			.bws-dynamic-tags-settings .bws-dep-rename { font-size: 13px; }
			.bws-dynamic-tags-settings .bws-dep-rename code { font-size: 12px; }
			.bws-dynamic-tags-settings .bws-dep-reason { margin-left: 6px; color: #646970; font-size: 12px; }

			/* Migration tool */
			.bws-dynamic-tags-settings .bws-migration-tool {
				background: #fff;
				border: 1px solid #c3c4c7;
			}
			.bws-dynamic-tags-settings .bws-scan-controls {
				display: flex;
				align-items: center;
				gap: 12px;
				padding: 12px;
				border-bottom: 1px solid #c3c4c7;
			}
			.bws-dynamic-tags-settings .bws-scan-status { font-size: 13px; color: #787c82; }
			.bws-dynamic-tags-settings .bws-results-toolbar {
				display: flex;
				align-items: center;
				gap: 12px;
				padding: 8px 12px;
				background: #f6f7f7;
				border-bottom: 1px solid #c3c4c7;
				flex-wrap: wrap;
			}
			.bws-dynamic-tags-settings .bws-results-table th,
			.bws-dynamic-tags-settings .bws-results-table td { padding: 6px 12px; vertical-align: middle; }
			.bws-dynamic-tags-settings .bws-cb-col { width: 28px; }
			.bws-dynamic-tags-settings .bws-issue-list { margin: 0; padding: 0; list-style: none; font-size: 12px; }
			.bws-dynamic-tags-settings .bws-issue-tag { color: #d63638; }
			.bws-dynamic-tags-settings .bws-issue-opt { color: #996800; }
			.bws-dynamic-tags-settings .bws-no-revision { font-size: 12px; color: #996800; }
			.bws-dynamic-tags-settings .bws-row-status { font-size: 12px; margin-left: 6px; }
			.bws-dynamic-tags-settings .bws-row-status.ok { color: #00a32a; }
			.bws-dynamic-tags-settings .bws-row-status.err { color: #d63638; }

			/* Progress bar */
			.bws-dynamic-tags-settings .bws-progress-wrap { display: flex; align-items: center; gap: 8px; flex: 1; min-width: 200px; }
			.bws-dynamic-tags-settings .bws-progress-bar { flex: 1; height: 8px; background: #dcdcde; border-radius: 4px; overflow: hidden; }
			.bws-dynamic-tags-settings .bws-progress-fill { height: 100%; background: #2271b1; width: 0; transition: width 0.2s; }
		</style>
		<?php
	}
}
