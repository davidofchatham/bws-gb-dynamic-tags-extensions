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
use BWS\DynamicTags\DeprecatedTagRegistry;

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
	 * transform signature (option_renames + value_renames + combine_options +
	 * source_inject + fixed_options + match_options). Old/new option-key lists are
	 * derived from option_renames + combine_options; reason is the parenthetical
	 * trailing the first entry's label.
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
			$signature = md5( wp_json_encode( array(
				'option_renames'    => $entry['option_renames']    ?? array(),
				'value_renames'     => $entry['value_renames']     ?? array(),
				'combine_options'   => $entry['combine_options']   ?? array(),
				'source_inject'     => $entry['source_inject']     ?? '',
				'fixed_options'     => $entry['fixed_options']     ?? array(),
				'match_options'     => $entry['match_options']     ?? array(),
				'match_any_options' => $entry['match_any_options'] ?? array(),
			) ) );
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

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();

		$mode_with    = $settings['deprecated']['mode_with_path']    ?? 'keep';
		$mode_without = $settings['deprecated']['mode_without_path'] ?? 'keep';

		// Split registry entries by migration path.
		$all_entries      = DeprecatedTagRegistry::get_all();
		$entries_with     = array_values( array_filter( $all_entries, fn( $e ) => ! empty( $e['new_tag'] ) ) );
		$entries_without  = array_values( array_filter( $all_entries, fn( $e ) => empty( $e['new_tag'] ) ) );

		// Deprecated option-key entries (separate registry type).
		$option_entries = MigrationRegistry::get_by_type( 'option' );

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

				<?php /* ── Deprecated Tag Mode ── */ ?>
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
							'entries' => $entries_with,
						),
						array(
							'key'     => 'mode_without_path',
							'label'   => __( 'Tags without migration path', 'generateblocks' ),
							'desc'    => __( 'These deprecated tags have no automatic conversion. Manual update required before disabling.', 'generateblocks' ),
							'current' => $mode_without,
							'entries' => $entries_without,
						),
					) as $group ) : ?>

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

						<?php if ( ! empty( $group['entries'] ) ) : ?>
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
						<?php endif; ?>
					</div>
					<?php endforeach; ?>
				</div>

<!-- 				<?php /* ── Deprecated Options ── */ ?>
 -->				<div class="bws-tag-group">
					<h2 class="bws-section-header"><?php esc_html_e( 'Deprecated Options', 'generateblocks' ); ?></h2>
					<p class="description bws-section-desc">
						<?php esc_html_e( 'Option-key migrations applied to current tags when stored content uses old option names. The Migration Tool below rewrites stored content; option-type migrations always have an automatic conversion path.', 'generateblocks' ); ?>
					</p>

					<div class="bws-dep-group">
						<?php
						$option_groups = self::group_option_entries_by_transform( $option_entries );
						?>
						<?php if ( empty( $option_groups ) ) : ?>
							<p class="description"><?php esc_html_e( 'No deprecated options registered.', 'generateblocks' ); ?></p>
						<?php else : ?>
						<details class="bws-dep-tag-list">
							<summary><?php
								echo esc_html( sprintf(
									/* translators: %d: count of deprecated option migrations */
									_n( '%d deprecated option migration', '%d deprecated option migrations', count( $option_groups ), 'generateblocks' ),
									count( $option_groups )
								) );
							?></summary>
							<table class="bws-tags-table widefat bws-ref-table">
								<tbody>
								<?php foreach ( $option_groups as $group_entry ) :
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
						<?php endif; ?>
					</div>
				</div>

				<?php /* ── Migration Tool (AJAX driven) ── */ ?>
				<div class="bws-tag-group bws-migration-tool" id="bws-migration-tool">
					<h2 class="bws-section-header"><?php esc_html_e( 'Migration Tool', 'generateblocks' ); ?></h2>
					<p class="description bws-section-desc">
						<?php esc_html_e( 'Scan all post content for deprecated tags and option issues, then migrate per post or in bulk. A revision is created before each migration when the post type supports it.', 'generateblocks' ); ?>
					</p>

					<div class="bws-scan-controls">
						<button type="button" id="bws-scan-btn" class="button button-primary">
							<?php esc_html_e( 'Scan All Content', 'generateblocks' ); ?>
						</button>
						<span id="bws-scan-status" class="bws-scan-status" aria-live="polite"></span>
					</div>

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

				<?php /* ── Diagnostics ── */ ?>
				<div class="bws-tag-group">
					<h2 class="bws-section-header"><?php esc_html_e( 'Diagnostics', 'generateblocks' ); ?></h2>
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
