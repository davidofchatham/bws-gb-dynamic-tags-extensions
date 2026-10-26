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
 *     email:       { obfuscate: bool },
 *     phone:       { country_code: string, strip_leading_cc: bool },
 *     table:       { stripe_color: string, stripe_opacity: string },
 *     core_blocks: string[],  // block names, e.g. core/paragraph; empty = off
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
		add_filter( 'admin_body_class', array( static::class, 'add_body_class' ) );
		add_action( 'in_admin_header', array( static::class, 'render_page_header' ) );
		add_action( 'wp_ajax_bws_scan_tags', array( TagConverter::class, 'ajax_scan' ) );
		add_action( 'wp_ajax_bws_migrate_tags', array( TagConverter::class, 'ajax_migrate' ) );
		add_action( 'wp_ajax_bws_ownership_optin', array( TagConverter::class, 'ajax_ownership_optin' ) );
		add_action( 'wp_ajax_bws_core_blocks_survey', 'bws_core_blocks_survey_ajax' );
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

	private static function is_settings_screen(): bool {
		$screen = get_current_screen();
		return $screen && false !== strpos( $screen->id, 'bws-dynamic-tags' );
	}

	public static function add_body_class( string $classes ): string {
		if ( ! self::is_settings_screen() ) {
			return $classes;
		}
		// The edge-to-edge frame only makes sense when the design-system styles that
		// supply its spacing are loaded.
		return $classes . ( wp_style_is( 'generateblocks-pro-admin-ui', 'registered' ) ? ' bws-dynamic-tags-page bws-has-frame' : ' bws-dynamic-tags-page' );
	}

	/**
	 * The page header carries Save, so it sits outside the form and submits it by id.
	 */
	public static function render_page_header(): void {
		if ( ! self::is_settings_screen() ) {
			return;
		}
		?>
		<header class="gp-page-header">
			<div class="gp-page-header__identity">
				<h1 class="gp-page-header__title"><?php esc_html_e( 'BWS Dynamic Tag Extensions', 'generateblocks' ); ?></h1>
			</div>
			<div class="gp-page-header__actions">
				<button type="submit" form="bws-settings-form" class="gp-button gp-button--primary gp-button--medium"><?php esc_html_e( 'Save Settings', 'generateblocks' ); ?></button>
			</div>
		</header>
		<?php
	}

	public static function enqueue_scripts( string $hook ): void {
		if ( false === strpos( $hook, 'bws-dynamic-tags' ) ) {
			return;
		}
		// The page's own stylesheet reads the GB Pro design-system tokens when that
		// stylesheet is registered, and carries a fallback for every one when it is not.
		$bws_style_deps = array_values(
			array_filter(
				array( 'generateblocks-pro-gp-tokens', 'generateblocks-pro-admin-ui' ),
				static fn( string $handle ): bool => wp_style_is( $handle, 'registered' )
			)
		);
		wp_enqueue_style(
			'bws-settings-page',
			BWS_DYNAMIC_TAGS_URL . 'assets/css/settings-page.css',
			$bws_style_deps,
			BWS_DYNAMIC_TAGS_VERSION
		);
		wp_enqueue_script(
			'bws-admin-tag-scanner',
			BWS_DYNAMIC_TAGS_URL . 'assets/js/admin-tag-scanner.js',
			array(),
			BWS_DYNAMIC_TAGS_VERSION,
			true
		);
		wp_enqueue_script(
			'bws-block-picker',
			BWS_DYNAMIC_TAGS_URL . 'assets/js/block-picker.js',
			array(),
			BWS_DYNAMIC_TAGS_VERSION,
			true
		);
		$picker_text = bws_core_blocks_picker_strings();
		wp_localize_script(
			'bws-block-picker',
			'bwsBlockPickerConfig',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bws_core_blocks_survey' ),
				'strings' => array(
					'running' => $picker_text['survey_running'],
					'error'   => $picker_text['survey_error'],
				),
			)
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
					'noRevision'     => __( 'No undo: this post type does not support revisions.', 'generateblocks' ),
					'migrateAll'     => __( 'Migrate Selected', 'generateblocks' ),
					'done'           => __( 'Done', 'generateblocks' ),
					'errorPrefix'    => __( 'Error:', 'generateblocks' ),
					'tagsMigrated'   => __( 'tags migrated', 'generateblocks' ),
					'optsMigrated'   => __( 'option fixes applied', 'generateblocks' ),
					'noChange'       => __( 'No changes needed', 'generateblocks' ),
					'selectAll'      => __( 'Select all', 'generateblocks' ),
					/* translators: %s: post title */
					'selectPost'     => __( 'Select %s', 'generateblocks' ),
					/* translators: %s: post title */
					'migratePost'    => __( 'Migrate %s', 'generateblocks' ),
					'deselectAll'    => __( 'Deselect all', 'generateblocks' ),
					'progressLabel'  => __( 'Migrating post %1$d of %2$d…', 'generateblocks' ),
					'bulkDone'       => __( '%d posts processed.', 'generateblocks' ),
					// The report's two non-conversion channels (FW-39, D46). Every SENTENCE in
					// those channels is composed in PHP and keyed by reason; these are the
					// labels the layout itself authors.
					'nothingToConvert' => __( 'Nothing to convert. See the sections below for what was left alone, and why.', 'generateblocks' ),
					'storedExample'    => __( 'Example:', 'generateblocks' ),
					'claimLabel'       => __( 'These tags are mine, convert them', 'generateblocks' ),
					'claimSaved'       => __( 'Saved. Scan again to pick up the change.', 'generateblocks' ),
					'saving'           => __( 'Saving…', 'generateblocks' ),
				),
			)
		);
	}

	// ===============================================
	// SETTINGS SCHEMA + SANITIZE
	// ===============================================

	public static function sanitize_settings( $input ): array {
		$sanitized = array(
			'modifiers'  => array(),
			'deprecated' => array(),
			'email'      => array(),
			'phone'      => array(),
			'table'      => array(),
			'core_blocks' => array(),
		);

		// Modifier toggles. An orphaned `modifiers.term` key on an existing row is simply
		// not carried forward — nothing reads it since the family's removal (1.21.0).
		$sanitized['modifiers']['try'] = ! empty( $input['modifiers']['try'] );

		// Deprecated tag group modes.
		$valid_modes = array( 'keep', 'suppress', 'disable' );
		foreach ( array( 'mode_with_path', 'mode_without_path' ) as $key ) {
			$val = sanitize_key( $input['deprecated'][ $key ] ?? 'keep' );
			$sanitized['deprecated'][ $key ] = in_array( $val, $valid_modes, true ) ? $val : 'keep';
		}

		// Email. `obfuscate` defaults ON; the checkbox renders checked, so an
		// untouched first save submits 1. Unchecking it writes false.
		$sanitized['email']['obfuscate'] = ! empty( $input['email']['obfuscate'] );

		// Phone. country_code: digits only, no `+` (a wrong seed silently builds
		// wrong hrefs, so the default is empty). strip_leading_cc: opt-in, default
		// OFF — only strips a leading run matching this country_code.
		$sanitized['phone']['country_code']     = preg_replace( '/\D/', '', (string) ( $input['phone']['country_code'] ?? '' ) );
		$sanitized['phone']['strip_leading_cc'] = ! empty( $input['phone']['strip_leading_cc'] );

		// Table. Stripe default color + opacity go through the SAME validators the editor and the
		// renderer use (bws_table_color_value / bws_table_stripe_opacity_setting), and a value in
		// no allowed form is stored empty, meaning the built-in `currentColor` at 5%. Never
		// sanitize_text_field(): it strips `%` followed by two hex digits.
		$stripe_color   = bws_table_color_value( (string) ( $input['table']['stripe_color'] ?? '' ) );
		$stripe_opacity = bws_table_stripe_opacity_setting( (string) ( $input['table']['stripe_opacity'] ?? '' ) );
		$sanitized['table']['stripe_color']   = $stripe_color ?? '';
		$sanitized['table']['stripe_opacity'] = null === $stripe_opacity ? '' : bws_table_css_number( $stripe_opacity );

		// Core blocks that get dynamic tags (FW-156). Flat list of `namespace/name`; the key MUST
		// be rebuilt here or every save drops it.
		$sanitized['core_blocks'] = bws_core_blocks_clean( $input['core_blocks'] ?? array() );

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
	 * The default stripe color and opacity for `{{table}}` stripes that write neither.
	 *
	 * Each half falls back to its built-in (`currentColor`, 5) when unset or invalid, so
	 * a hand-edited option row can never reach the stylesheet.
	 *
	 * @since 1.22.0
	 * @return array{color:string,opacity:float}
	 */
	public static function get_table_stripe_defaults(): array {
		$table = (array) ( self::get_settings()['table'] ?? array() );
		return bws_table_stripe_defaults_resolve( (string) ( $table['stripe_color'] ?? '' ), (string) ( $table['stripe_opacity'] ?? '' ) );
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
	 * Deprecated/Retired box structural rules (were SPEC §V5/§V6; migrated here on ship):
	 * - **Empty-cascade is structural, not per-row.** A bucket (a K/S/D with-path/without-path
	 *   subgroup, or a Retired tag-list/option-list) with zero entries after the allowlist
	 *   filter hides its ENTIRE heading + description + control block, not just an empty
	 *   disclosure. If all four boxes (Deprecated/Retired × Tags/Options) end up empty, the
	 *   whole group renders nothing.
	 * - **The Migration Tool box has NO hide condition** — it always renders regardless of every
	 *   other box's item count, so an admin whose boxes all hid (e.g. after migrating the last
	 *   matching post) still has the scan/migrate entry point.
	 *
	 * Entry classification (Deprecated vs Retired) is MigrationRegistry::is_entry_live()
	 * (CONTEXT.md I10). A tag row's registration marker is display only and is never an input
	 * to classification, the scan-aware hiding or the allowlist (MigrationRegistry::register()'s
	 * PHPDoc owns what classifies). The allowlist hide-filter (positive list) and the "show all"
	 * bypass are TagConverter::get_allowlist() / the `bws_dynamic_tags_show_all_deprecated`
	 * filter (V7/V8, PHPDoc there).
	 */
	/**
	 * One list of migration entries. Flat by default; collapsed behind its count only when
	 * "show all" is on, since that is the one case where the list is long.
	 *
	 * @param bool   $collapse Whether to put the table behind a disclosure.
	 * @param string $summary  The disclosure's text (the count).
	 * @param string $rows     Table rows, already escaped.
	 */
	private static function render_entry_list( bool $collapse, string $summary, string $rows ): void {
		$table = '<table class="bws-tags-table widefat bws-ref-table"><tbody>' . $rows . '</tbody></table>';
		if ( $collapse ) {
			echo '<details class="bws-dep-tag-list"><summary>' . esc_html( $summary ) . '</summary>' . $table . '</details>'; // phpcs:ignore WordPress.Security.EscapeOutput -- rows are escaped where built.
			return;
		}
		echo $table; // phpcs:ignore WordPress.Security.EscapeOutput -- rows are escaped where built.
	}

	/**
	 * Rows for a list of deprecated or retired tags.
	 *
	 * Each row ends with the tag's CURRENT registration (bws_gb_registration_marker()). GB's
	 * registry is read once per list, and only when we registered something, since a name we
	 * never registered needs no read to be "migration only".
	 */
	private static function tag_rows_html( array $entries ): string {
		$have_marker = function_exists( 'bws_gb_tags_we_registered' ) && function_exists( 'bws_gb_registration_marker' );
		$ours        = $have_marker ? bws_gb_tags_we_registered() : array();
		$live        = $ours && class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ? \GenerateBlocks_Register_Dynamic_Tag::get_tags() : array();
		// One entry per state bws_gb_registration_marker() can return; every value is escaped here.
		$marker_text = array(
			'hidden'          => esc_html__( 'registered, hidden', 'generateblocks' ),
			'listed'          => esc_html__( 'registered, listed', 'generateblocks' ),
			'migration-only'  => esc_html__( 'migration only', 'generateblocks' ),
			'other-registrar' => sprintf(
				/* translators: %s: link to the Tag Name Conflicts section */
				esc_html__( 'another plugin holds this name, see %s', 'generateblocks' ),
				'<a href="#bws-tag-name-conflicts">' . esc_html__( 'Tag Name Conflicts', 'generateblocks' ) . '</a>'
			),
		);
		ob_start();
		foreach ( $entries as $entry ) :
			$old_tag = $entry['old_tag'] ?? $entry['match_tag'] ?? '';
			$new_tag = $entry['new_tag'] ?? '';
			$since   = $entry['since']   ?? '';
			if ( '' === $old_tag ) {
				continue;
			}
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
						<?php $state = $have_marker ? bws_gb_registration_marker( $ours[ $old_tag ] ?? null, $live[ $old_tag ] ?? null ) : 'migration-only'; ?>
						<span class="bws-dep-since bws-dep-registration"><?php echo '(' . $marker_text[ $state ] . ')'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped where built. ?></span>
				</td>
			</tr>
			<?php
		endforeach;
		return (string) ob_get_clean();
	}

	/**
	 * Rows for a list of option-key migrations, as grouped by group_option_entries_by_transform().
	 */
	private static function option_rows_html( array $groups ): string {
		ob_start();
		foreach ( $groups as $group_entry ) :
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
						<?php echo $old_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?>
						<?php if ( '' !== $old_html && '' !== $new_html ) : ?>
						<span class="bws-dep-arrow">→</span>
						<?php endif; ?>
						<?php echo $new_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?>
						<?php if ( $reason ) : ?>
						<span class="bws-dep-reason"><?php echo esc_html( '(' . $reason . ')' ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $tags ) ) : ?>
					<div class="description bws-dep-applies">
						<?php esc_html_e( 'Applies to:', 'generateblocks' ); ?>
						<?php
						$pieces = array_map( fn( $t ) => '<code class="bws-tag-name">' . esc_html( $t ) . '</code>', $tags );
						echo implode( ', ', $pieces ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
						?>
					</div>
					<?php endif; ?>
				</td>
			</tr>
			<?php
		endforeach;
		return (string) ob_get_clean();
	}

	/**
	 * The "Tags in Other Blocks" section (FW-156): one searchable list of the server-registered
	 * blocks. Rows come from bws_core_blocks_picker_rows(), words from
	 * bws_core_blocks_picker_strings(); assets/js/block-picker.js does the search, counter and
	 * unsaved-changes state. Each row carries `data-bws-block` (the block name) as the hook for
	 * per-row additions.
	 *
	 * @since Unreleased
	 * @param array $settings Saved settings.
	 */
	private static function render_core_blocks_section( array $settings ): void {
		$text    = bws_core_blocks_picker_strings();
		$saved   = bws_core_blocks_clean( $settings['core_blocks'] ?? array() );
		list( $entries, $categories ) = bws_core_blocks_picker_registry();
		$survey  = bws_core_blocks_survey_last();
		$groups  = bws_core_blocks_picker_rows( $entries, $categories, $saved, bws_core_blocks_enabled(), bws_core_blocks_gb_native(), $survey['blocks'] ?? null );
		$field   = self::OPTION_NAME . '[core_blocks][]';
		$n       = 0;
		?>
				<?php /* ── Tags in Other Blocks ── */ ?>
				<div class="bws-tag-group" id="bws-core-blocks">
					<h2 class="bws-section-header"><?php echo esc_html( $text['heading'] ); ?></h2>
					<div class="bws-card">
						<?php foreach ( $text['intro'] as $paragraph ) : ?>
							<p class="description bws-section-desc"><?php echo bws_core_blocks_picker_markup( $paragraph ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></p>
						<?php endforeach; ?>
						<ul class="bws-block-notes">
							<?php foreach ( $text['notes'] as $note ) : ?>
								<li><?php echo bws_core_blocks_picker_markup( $note ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></li>
							<?php endforeach; ?>
						</ul>
						<div class="bws-block-picker" data-count-template="<?php echo esc_attr( $text['count'] ); ?>">
							<div class="bws-block-picker__survey">
								<p class="description bws-block-picker__survey-status"><?php echo esc_html( $survey ? bws_core_blocks_survey_status( $survey['time'], $text ) : $text['survey_hint'] ); ?></p>
								<button type="button" class="gp-button gp-button--secondary gp-button--small bws-block-picker__scan"><?php echo esc_html( $text['survey_scan'] ); ?></button>
							</div>
							<div class="gp-notice gp-notice--warn bws-notice bws-block-picker__confirm" role="region" aria-live="polite" hidden>
								<div class="gp-notice__status"><span class="gp-status-dot gp-status-dot--warn" aria-hidden="true"></span></div>
								<div class="gp-notice__content">
									<p><?php echo esc_html( $text['confirm_intro'] ); ?></p>
									<ul class="bws-block-picker__confirm-list"></ul>
									<p>
										<button type="button" class="gp-button gp-button--primary gp-button--small bws-block-picker__confirm-save"><?php echo esc_html( $text['confirm_save'] ); ?></button>
										<button type="button" class="gp-button gp-button--secondary gp-button--small bws-block-picker__confirm-back"><?php echo esc_html( $text['confirm_back'] ); ?></button>
									</p>
								</div>
							</div>
							<div class="bws-block-picker__bar">
								<input type="search" class="gp-text-input bws-block-picker__search"
									placeholder="<?php echo esc_attr( $text['search_placeholder'] ); ?>"
									aria-label="<?php echo esc_attr( $text['search_placeholder'] ); ?>" autocomplete="off" />
								<span class="bws-block-picker__count" role="status" aria-live="polite"></span>
								<span class="bws-block-picker__dirty" hidden><?php echo esc_html( $text['dirty'] ); ?></span>
							</div>
							<div class="bws-block-picker__groups">
								<?php foreach ( $groups as $group ) : ?>
									<section class="bws-block-picker__group">
										<h3 class="bws-block-picker__group-label"><?php echo esc_html( $group['inactive'] ? $text['inactive_heading'] : $group['label'] ); ?></h3>
										<ul class="bws-block-picker__items">
											<?php foreach ( $group['rows'] as $row ) : ?>
												<?php $id = 'bws-core-block-' . ( ++$n ); ?>
												<li class="bws-block-picker__item" data-bws-block="<?php echo esc_attr( $row['name'] ); ?>" data-bws-title="<?php echo esc_attr( $row['title'] ); ?>" data-bws-registered="<?php echo (int) $row['registered']; ?>" data-search="<?php echo esc_attr( $row['search'] ); ?>">
													<input type="checkbox" id="<?php echo esc_attr( $id ); ?>"
														name="<?php echo esc_attr( $field ); ?>"
														value="<?php echo esc_attr( $row['name'] ); ?>"
														<?php checked( $row['selected'] ); ?> <?php disabled( $row['disabled'] ); ?> />
													<div>
														<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $row['title'] ); ?> <code><?php echo esc_html( $row['name'] ); ?></code></label>
														<?php foreach ( $row['notes'] as $key ) : ?>
															<p class="description"><?php echo bws_core_blocks_picker_markup( $text[ $key ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></p>
														<?php endforeach; ?>
														<?php if ( $row['variations'] ) : ?>
															<p class="description"><?php echo esc_html( sprintf( $text['variations'], implode( ', ', array_slice( $row['variations'], 0, 3 ) ) ) ); ?></p>
														<?php endif; ?>
														<div class="bws-block-picker__tags"><?php echo bws_core_blocks_survey_row_html( $row, $text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></div>
													</div>
												</li>
											<?php endforeach; ?>
										</ul>
									</section>
								<?php endforeach; ?>
							</div>
							<p class="bws-block-picker__empty" hidden><?php echo esc_html( $text['empty'] ); ?></p>
						</div>
					</div>
				</div>
		<?php
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();

		$mode_with    = $settings['deprecated']['mode_with_path']    ?? 'keep';
		$mode_without = $settings['deprecated']['mode_without_path'] ?? 'keep';

		// Scan allowlist: hides zero-match entries unless the show-all filter is on (V7/V8).
		$show_all  = (bool) apply_filters( 'bws_dynamic_tags_show_all_deprecated', false );
		$allowlist = TagConverter::get_allowlist();
		$tag_in_allowlist = fn( array $e ) => in_array( $e['old_tag'] ?? $e['match_tag'] ?? '', $allowlist['tags'], true );
		$option_in_allowlist = fn( array $e ) => in_array( $e['label'] ?? '', $allowlist['option_labels'], true );

		// Split tag-type entries by lifecycle first (deprecated vs retired),
		// then by migration path within each bucket.
		$live_entries       = MigrationRegistry::get_by_type_and_liveness( 'tag', true );
		$retired_entries    = MigrationRegistry::get_by_type_and_liveness( 'tag', false );
		if ( ! $show_all ) {
			$live_entries    = array_values( array_filter( $live_entries, $tag_in_allowlist ) );
			$retired_entries = array_values( array_filter( $retired_entries, $tag_in_allowlist ) );
		}
		$live_with          = array_values( array_filter( $live_entries, fn( $e ) => ! empty( $e['new_tag'] ) ) );
		$live_without       = array_values( array_filter( $live_entries, fn( $e ) => empty( $e['new_tag'] ) ) );
		$retired_with       = array_values( array_filter( $retired_entries, fn( $e ) => ! empty( $e['new_tag'] ) ) );
		$retired_without    = array_values( array_filter( $retired_entries, fn( $e ) => empty( $e['new_tag'] ) ) );

		// Deprecated option-key entries (separate registry type).
		$live_option_entries    = MigrationRegistry::get_by_type_and_liveness( 'option', true );
		$retired_option_entries = MigrationRegistry::get_by_type_and_liveness( 'option', false );
		if ( ! $show_all ) {
			$live_option_entries    = array_values( array_filter( $live_option_entries, $option_in_allowlist ) );
			$retired_option_entries = array_values( array_filter( $retired_option_entries, $option_in_allowlist ) );
		}

		$mode_options = array(
			'keep'     => __( 'Keep — tags work normally', 'generateblocks' ),
			'suppress' => __( 'Suppress — tags register but output nothing (safe frontend fallback)', 'generateblocks' ),
			'disable'  => __( 'Disable — tags are removed from GB (use only after migrating all content)', 'generateblocks' ),
		);
		?>
		<div class="gp-admin-page">
		<div class="wrap bws-dynamic-tags-settings gp-admin-page__main">
			<hr class="wp-header-end">

			<form method="post" action="options.php" id="bws-settings-form">
				<?php settings_fields( 'bws_dynamic_tags_settings_group' ); ?>

				<?php self::render_core_blocks_section( $settings ); ?>

				<?php /* ── Email ── */ ?>
				<div class="bws-tag-group">
					<h2 class="bws-section-header"><?php esc_html_e( 'Email', 'generateblocks' ); ?></h2>
					<div class="bws-card">
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
									<p class="description"><?php printf( /* translators: %s: the email tag name. */ esc_html__( 'Encode addresses output by the %s tag with antispambot() to deter naive harvesters. Disable if a clean mailto: href is needed (e.g. analytics).', 'generateblocks' ), '<code>email</code>' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
					</div>
				</div>

				<?php /* ── Phone ── */ ?>
				<div class="bws-tag-group">
					<h2 class="bws-section-header"><?php esc_html_e( 'Phone', 'generateblocks' ); ?></h2>
					<div class="bws-card">
					<table class="bws-tags-table widefat">
						<tbody>
							<tr class="bws-tag-row">
								<td class="bws-tag-checkbox">
									<input type="text" id="bws-phone-country-code" class="gp-text-input bws-input-wide"
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[phone][country_code]"
										value="<?php echo esc_attr( self::get_phone_country_code() ); ?>"
										inputmode="numeric" placeholder="<?php esc_attr_e( 'e.g. 44', 'generateblocks' ); ?>" />
								</td>
								<td>
									<label for="bws-phone-country-code"><?php esc_html_e( 'Default country code', 'generateblocks' ); ?></label>
									<p class="description">
										<?php printf( /* translators: %s: the phone tag name. */ esc_html__( 'Default country code (digits only, no +) for %s tel: links when a number has no international prefix. Leave empty for national-only tel: links.', 'generateblocks' ), '<code>phone</code>' ); ?>
										<a href="https://www.countrycode.org" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Country code reference', 'generateblocks' ); ?><span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'generateblocks' ); ?></span><span class="dashicons dashicons-external bws-external-icon" aria-hidden="true"></span></a>
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
									<p class="description"><?php esc_html_e( 'Requires a default country code above. Only applies to numbers stored with the country code run straight into the number without a +, such as 18005551212, not when the code is set off by a space, dash or +, as in +1 800 555 1212.', 'generateblocks' ); ?></p>
									<?php /* The warning is a separate element so it is not read as part of the description. */ ?>
									<div class="gp-notice gp-notice--warn bws-notice">
										<span class="gp-notice__status"><span class="gp-status-dot gp-status-dot--warn" aria-hidden="true"></span></span>
										<div class="gp-notice__content">
											<span class="screen-reader-text"><?php esc_html_e( 'Warning:', 'generateblocks' ); ?></span>
											<?php esc_html_e( 'A number stored without a country code that has an area code or subscriber number beginning with the same digits as the default country code will lose those digits. Turn this on only if your stored numbers consistently carry the country code.', 'generateblocks' ); ?>
										</div>
									</div>
								</td>
							</tr>
						</tbody>
					</table>
					</div>
				</div>

				<?php /* ── Table ── */ ?>
				<?php $bws_stripe = self::get_table_stripe_defaults(); ?>
				<div class="bws-tag-group">
					<h2 class="bws-section-header"><?php esc_html_e( 'Table', 'generateblocks' ); ?></h2>
					<div class="bws-card">
					<table class="bws-tags-table widefat">
						<tbody>
							<tr class="bws-tag-row">
								<td class="bws-tag-checkbox">
									<input type="text" id="bws-table-stripe-color" class="gp-text-input bws-input-wide"
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[table][stripe_color]"
										value="<?php echo esc_attr( $bws_stripe['color'] ); ?>"
										placeholder="<?php echo esc_attr( BWS_TABLE_STRIPE_DEFAULT_COLOR ); ?>" />
								</td>
								<td>
									<label for="bws-table-stripe-color"><?php esc_html_e( 'Default stripe color', 'generateblocks' ); ?></label>
									<p class="description"><?php printf( /* translators: %s: the table tag name. */ esc_html__( 'The color of a %s stripe that does not set its own. Use a hex value, rgb(), rgba(), hsl(), hsla(), var(), currentColor, or a color name. An unrecognized value falls back to currentColor, the text color, which stays visible on any theme.', 'generateblocks' ), '<code>table</code>' ); ?></p>
								</td>
							</tr>
							<tr class="bws-tag-row">
								<td class="bws-tag-checkbox">
									<span class="bws-input-unit">
										<input type="number" id="bws-table-stripe-opacity" class="gp-text-input bws-input-narrow" min="0" max="100" step="any"
											name="<?php echo esc_attr( self::OPTION_NAME ); ?>[table][stripe_opacity]"
											value="<?php echo esc_attr( bws_table_css_number( $bws_stripe['opacity'] ) ); ?>"
											placeholder="<?php echo esc_attr( bws_table_css_number( BWS_TABLE_STRIPE_DEFAULT_OPACITY ) ); ?>" />
										<span class="bws-input-unit__suffix">%</span>
									</span>
								</td>
								<td>
									<label for="bws-table-stripe-opacity"><?php esc_html_e( 'Default stripe opacity', 'generateblocks' ); ?></label>
									<p class="description"><?php printf( /* translators: %s: the table tag name. */ esc_html__( 'How strong a %s stripe is when it does not set its own, from 0 to 100. A color that carries its own transparency ignores this. A theme can still override the stripe in CSS with --bws-dte-table-stripe.', 'generateblocks' ), '<code>table</code>' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
					</div>
				</div>

				<?php /* ── Multi-attempt tags ── */ ?>
				<div class="bws-tag-group">
					<h2 class="bws-section-header"><?php esc_html_e( 'Multi-Attempt Tags', 'generateblocks' ); ?></h2>
					<div class="bws-card">
					<table class="bws-tags-table widefat">
						<tbody>
							<tr class="bws-tag-row">
								<td class="bws-tag-checkbox">
									<input type="checkbox" id="bws-modifier-try"
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[modifiers][try]"
										value="1" <?php checked( self::is_modifier_enabled( 'try' ) ); ?> />
								</td>
								<td>
									<label for="bws-modifier-try"><?php esc_html_e( 'Enable multi-attempt tags', 'generateblocks' ); ?></label>
									<p class="description"><?php printf( /* translators: 1: an example multi-attempt tag name, 2: another example tag name. */ esc_html__( 'Multi-attempt tags (%1$s, %2$s, etc.) that return the first attempt with a value.', 'generateblocks' ), '<code>try_text</code>', '<code>try_image</code>' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
					</div>
				</div>

				<?php /* ── Call Custom Function (read-only allowlist mirror) ── */ ?>
				<?php $bws_call_rows = self::get_call_allowlist_status(); ?>
				<div class="bws-tag-group">
					<h2 class="bws-section-header"><?php esc_html_e( 'Call Custom Function', 'generateblocks' ); ?></h2>
					<div class="bws-card">
					<div class="bws-section-desc">
						<p style="margin-top:0">
							<?php printf( /* translators: %s: the function name used to register a custom function. */ esc_html__( 'Custom functions must take a post ID as their first argument, sanitize their own output (HTML is allowed in the return string), and be registered via %s:', 'generateblocks' ), '<code>bws_register_call_function()</code>' ); ?>
						</p>
						<pre class="bws-code-block"><code>add_action( 'init', function () {
    bws_register_call_function( 'my_result' );
} );

function my_result( $post_id, $arg = '' ) {
    return '&lt;span&gt;' . esc_html( get_the_title( $post_id ) ) . '&lt;/span&gt;';
}</code></pre>
						<p style="margin:0">
							<?php printf( /* translators: %s: the call tag name. */ esc_html__( 'Properly registered functions will appear in the %1$s tag\'s Function dropdown for easy access. Manually inserting an unregistered function in a %1$s tag will cause it to return the tag\'s fallback text, if available, or return empty.', 'generateblocks' ), '<code>call</code>' ); ?>
						</p>
					</div>
					<h3 class="bws-subhead"><?php esc_html_e( 'Registered Functions', 'generateblocks' ); ?></h3>
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
								// Dot = at-a-glance status. Text shown ONLY for a failure (the
								// reason a dot can't carry); an OK row needs no redundant "OK".
								$bws_call_problem = $bws_call_ok
									? ''
									: ( ! $bws_call_row['exists']
										? __( 'Not found (function does not exist)', 'generateblocks' )
										: __( 'Refused (PHP built-in)', 'generateblocks' ) );
								?>
								<tr class="bws-tag-row">
									<td class="bws-tag-checkbox" style="width:1.5em">
										<span class="gp-status-dot <?php echo $bws_call_ok ? '' : 'gp-status-dot--error'; ?>" aria-hidden="true"></span>
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
				</div>

				<?php /* ── Deprecated Tags (lifecycle 'deprecated'; registered hidden or migration-only, never listed; the K/S/D controls are FW-81's to rewire) ── */ ?>
				<?php if ( ! empty( $live_with ) || ! empty( $live_without ) ) : ?>
				<div class="bws-tag-group">
					<h2 class="bws-section-header"><?php esc_html_e( 'Deprecated Tags', 'generateblocks' ); ?></h2>
					<div class="bws-card">
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

						<?php
						self::render_entry_list(
							$show_all,
							sprintf(
								/* translators: %d: count of deprecated tags */
								_n( '%d deprecated tag', '%d deprecated tags', count( $group['entries'] ), 'generateblocks' ),
								count( $group['entries'] )
							),
							self::tag_rows_html( $group['entries'] )
						);
						?>
					</div>
					<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>

				<?php /* ── Migration Tool (AJAX driven) ── */ ?>
				<div class="bws-tag-group bws-migration-tool" id="bws-migration-tool">
					<h2 class="bws-section-header"><?php esc_html_e( 'Migration Tool', 'generateblocks' ); ?></h2>
					<div class="bws-card">
					<p class="description bws-section-desc">
						<?php esc_html_e( 'Scan all post content for deprecated tags and option issues, then migrate per post or in bulk. A revision is created before each migration when the post type supports it.', 'generateblocks' ); ?>
					</p>
					<?php /* US 26 — DISCLOSURE, not enumeration. A reported count with no stated
					        boundary reads as a completeness claim it cannot back; an inventory
					        answers a different question ("so I can go fix them"), deferred to #100. */ ?>
					<div class="bws-section-desc">
						<div class="gp-notice bws-notice bws-notice--info">
							<span class="gp-notice__status"><span class="gp-status-dot" aria-hidden="true"></span></span>
							<div class="gp-notice__content">
								<span class="screen-reader-text"><?php esc_html_e( 'Note:', 'generateblocks' ); ?></span>
								<?php esc_html_e( 'Covers post content and the block pattern cache. Tag strings stored elsewhere, such as in custom field values or another plugin\'s cache, are not reached by this tool.', 'generateblocks' ); ?>
							</div>
						</div>
					</div>

					<?php
					$live_option_groups    = self::group_option_entries_by_transform( $live_option_entries );
					$retired_option_groups = self::group_option_entries_by_transform( $retired_option_entries );
					$bws_inventory         = array(
						array(
							'label'   => __( 'Retired tags with a migration path', 'generateblocks' ),
							'desc'    => __( 'These tags are no longer supported. The scan below can convert them to current equivalents.', 'generateblocks' ),
							'summary' => sprintf(
								/* translators: %d: count of retired tags */
								_n( '%d retired tag', '%d retired tags', count( $retired_with ), 'generateblocks' ),
								count( $retired_with )
							),
							'rows'    => $retired_with ? self::tag_rows_html( $retired_with ) : '',
						),
						array(
							'label'   => __( 'Retired tags without a migration path', 'generateblocks' ),
							'desc'    => __( 'These tags are no longer supported and have no automatic conversion. Manual update required.', 'generateblocks' ),
							'summary' => sprintf(
								/* translators: %d: count of retired tags */
								_n( '%d retired tag', '%d retired tags', count( $retired_without ), 'generateblocks' ),
								count( $retired_without )
							),
							'rows'    => $retired_without ? self::tag_rows_html( $retired_without ) : '',
						),
						array(
							'label'   => __( 'Deprecated options', 'generateblocks' ),
							'desc'    => __( 'Old option names on current tags. The scan below rewrites stored content to the current names.', 'generateblocks' ),
							'summary' => sprintf(
								/* translators: %d: count of deprecated option migrations */
								_n( '%d deprecated option migration', '%d deprecated option migrations', count( $live_option_groups ), 'generateblocks' ),
								count( $live_option_groups )
							),
							'rows'    => $live_option_groups ? self::option_rows_html( $live_option_groups ) : '',
						),
						array(
							'label'   => __( 'Retired options', 'generateblocks' ),
							'desc'    => __( 'The old option name is no longer accepted at all. The scan below still finds and fixes stored content that uses it.', 'generateblocks' ),
							'summary' => sprintf(
								/* translators: %d: count of retired option migrations */
								_n( '%d retired option migration', '%d retired option migrations', count( $retired_option_groups ), 'generateblocks' ),
								count( $retired_option_groups )
							),
							'rows'    => $retired_option_groups ? self::option_rows_html( $retired_option_groups ) : '',
						),
					);
					?>
					<h3 class="bws-subhead"><?php esc_html_e( 'Deprecated and retired entries', 'generateblocks' ); ?></h3>
					<?php foreach ( $bws_inventory as $bws_list ) : ?>
						<?php if ( '' === $bws_list['rows'] ) { continue; } ?>
					<div class="bws-dep-group">
						<h3 class="bws-dep-group-header"><?php echo esc_html( $bws_list['label'] ); ?></h3>
						<p class="description"><?php echo esc_html( $bws_list['desc'] ); ?></p>
						<?php self::render_entry_list( $show_all, $bws_list['summary'], $bws_list['rows'] ); ?>
					</div>
					<?php endforeach; ?>

					<div class="bws-scan-controls">
						<button type="button" id="bws-scan-btn" class="gp-button gp-button--primary gp-button--medium">
							<?php esc_html_e( 'Scan All Content', 'generateblocks' ); ?>
						</button>
						<span id="bws-scan-status" class="bws-scan-status" aria-live="polite"></span>
					</div>

					<?php /* The pattern-cache result, PERSISTED rather than a transient status
					        line: the on-upgrade trigger has no interface at all and is the one
					        actually repairing already-converted sites. The element is rendered
					        even when empty so the AJAX handlers can fill it without a reload. */ ?>
					<div class="bws-pattern-cache">
						<p class="description bws-pattern-cache-status" aria-live="polite">
							<?php esc_html_e( 'Block pattern caches are automatically reconciled on every scan and migration.', 'generateblocks' ); ?>
							<span id="bws-pattern-cache-status"><?php echo esc_html( PatternCache::format_status( PatternCache::get_status() ) ); ?></span>
						</p>
					</div>

					<?php /* ── The report's THREE sections (FW-39, D46) ──
					        To be converted (the table below), then DECLINED and SKIPPED. The
					        two extra sections are site-wide, not per post, because both facts
					        are properties of a tag NAME on this site — see
					        TagConverter::report_channels().

					        THE EXEMPTION IS A LINE, NOT A GATE (D40). It sits with the
					        conversion preview because those tags DO convert; what it discloses
					        is that this one conversion changes what a page shows. Putting it
					        behind a confirmation would be a second gate beside the opt-in, and
					        training click-through on the one control here that can damage
					        content is exactly what D46 refuses.

					        Rendered empty and filled by the scan, like the pattern-cache line
					        above: PHP composes every sentence, the script only places it. */ ?>
					<div id="bws-scan-results" style="display:none;">
						<h3 class="bws-channel-header"><?php esc_html_e( 'To be converted', 'generateblocks' ); ?></h3>
						<div id="bws-scan-exemption" class="bws-scan-exemption" style="display:none;">
							<div class="gp-notice gp-notice--warn bws-notice">
								<span class="gp-notice__status"><span class="gp-status-dot gp-status-dot--warn" aria-hidden="true"></span></span>
								<div class="gp-notice__content"><p id="bws-scan-exemption-line"></p></div>
							</div>
						</div>
						<div class="bws-results-toolbar">
							<label>
								<input type="checkbox" id="bws-select-all" />
								<span id="bws-select-all-label"><?php esc_html_e( 'Select all', 'generateblocks' ); ?></span>
							</label>
							<button type="button" id="bws-migrate-selected-btn" class="gp-button gp-button--secondary gp-button--small" disabled>
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
									<th class="bws-cb-col"><span class="screen-reader-text"><?php esc_html_e( 'Select', 'generateblocks' ); ?></span></th>
									<th><?php esc_html_e( 'Post', 'generateblocks' ); ?></th>
									<th><?php esc_html_e( 'Type', 'generateblocks' ); ?></th>
									<th><?php esc_html_e( 'Issues Found', 'generateblocks' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'generateblocks' ); ?></th>
								</tr>
							</thead>
							<tbody id="bws-results-tbody"></tbody>
						</table>
					</div>

					<?php /* DECLINED — the channel with an author action. Each row names one
					        contested tag name, what the record actually knows about it, and the
					        per-name claim that lifts the guard (D37). The claim is the ONE
					        control on this page that can rewrite content nobody here can prove
					        is ours, which is why the count and a stored example sit beside it:
					        a claim of ownership with nothing to recognize is a click, not a
					        decision. */ ?>
					<div id="bws-scan-declined" class="bws-scan-channel" style="display:none;">
						<h3 class="bws-channel-header"><?php esc_html_e( 'Declined: we cannot prove these tags are yours', 'generateblocks' ); ?></h3>
						<p class="description bws-section-desc">
							<?php esc_html_e( 'These tags are left exactly as they are and go on rendering. Claim a tag name only if you know the stored tags on this site were written for our version of it. Conversion keeps each tag\'s settings as written, so a setting that belonged to the other plugin\'s version of the tag may not carry over.', 'generateblocks' ); ?>
						</p>
						<ul class="bws-channel-list" id="bws-declined-list"></ul>
					</div>

					<?php /* SKIPPED — informational, no action, no gate (D33, D41). Separate from
					        the declined list rather than a second reason column on it: a skip has
					        no button, and merging the two would put a second gate beside the
					        opt-in. */ ?>
					<div id="bws-scan-skipped" class="bws-scan-channel" style="display:none;">
						<h3 class="bws-channel-header"><?php esc_html_e( 'Skipped: no equivalent tag to convert to', 'generateblocks' ); ?></h3>
						<p class="description bws-section-desc">
							<?php esc_html_e( 'Nothing to do. These tags are unchanged, still render what they render today, and will keep working. They are listed so you know they were seen and left alone.', 'generateblocks' ); ?>
						</p>
						<ul class="bws-channel-list" id="bws-skipped-list"></ul>
					</div>
					</div>
				</div>

				<?php /* ── Diagnostics: read-only reports ── */ ?>
				<div class="bws-tag-group">
					<?php
					// The heading names the CATEGORY and each subhead names its subject. Every
					// subsection here is a read-only report that changes nothing; Tag Name Conflicts
					// is the only one so far, and FW-99 lands here as a second subhead rather than
					// as a section of its own.
					?>
					<h2 class="bws-section-header"><?php esc_html_e( 'Diagnostics', 'generateblocks' ); ?></h2>
					<div class="bws-card">

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
								<span class="gp-status-dot" aria-hidden="true"></span>
								<?php esc_html_e( 'If more than one plugin registers the same tag name, frontend output can change without warning. As of now, this plugin can register all its tags without conflict.', 'generateblocks' ); ?>
							</p>
						</div>
					<?php else : ?>
					<table class="bws-tags-table widefat">
						<thead>
								<tr>
									<th style="width:1.5em"><span class="screen-reader-text"><?php esc_html_e( 'Severity', 'generateblocks' ); ?></span></th>
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
										// would have it read out twice in a row. The dot's color varies by
										// state rather than marking every row the same way, which told
										// a reader only what the table's presence already told them.
										?>
										<span class="gp-status-dot <?php echo 'warn' === $bws_c_state['icon'] ? 'gp-status-dot--error' : 'gp-status-dot--idle'; ?>" aria-hidden="true"></span>
									</td>
									<td><code><?php echo esc_html( $bws_collision['tag'] ); ?></code></td>
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
									<span class="gp-status-dot <?php echo 'warn' === $bws_c_state['icon'] ? 'gp-status-dot--error' : 'gp-status-dot--idle'; ?>" aria-hidden="true"></span>
									<?php echo esc_html( $bws_c_state['heading'] ); ?>
								</h4>
								<p class="description"><?php echo esc_html( $bws_c_state['long'] ); ?></p>
								<p class="description"><?php echo esc_html( $bws_c_state['remedy'] ); ?></p>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					</div>
				</div>

			</form>
		</div>
		</div>
		<?php
	}
}
