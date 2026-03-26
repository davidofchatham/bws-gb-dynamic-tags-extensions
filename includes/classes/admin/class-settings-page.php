<?php
/**
 * Admin Settings Page for BWS Dynamic Tag Extensions.
 *
 * Provides hierarchical toggles for sources and individual tags
 * under the GenerateBlocks submenu.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 * @since 1.2.0 Added register_tag_source(), updated is_tag_enabled() with tag-level defaults.
 */

namespace BWS\DynamicTags\Admin;

use BWS\DynamicTags\DeprecatedTagRegistry;
use BWS\DynamicTags\SourceInterface;
use BWS\DynamicTags\SourceRegistry;
use BWS\DynamicTags\TagTemplateRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SettingsPage {

	/** @var string Option name in wp_options. */
	const OPTION_NAME = 'bws_dynamic_tags_settings';

	/** @var array|null Cached settings. */
	private static ?array $settings = null;

	/**
	 * Tag registration map: tag_name => [ SourceInterface, is_related, ?bool tag_default ].
	 * Populated by TagTemplateRegistry during generate_all_tags().
	 *
	 * @since 1.2.0
	 * @var array<string, array{0: SourceInterface, 1: bool, 2: ?bool}>
	 */
	private static array $_registered_tags = [];

	/**
	 * Initialize admin hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( static::class, 'add_menu_page' ), 20 );
		add_action( 'admin_init', array( static::class, 'register_settings' ) );
	}

	/**
	 * Add submenu page under GenerateBlocks.
	 */
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

	/**
	 * Register settings with the WP Settings API.
	 */
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

	/**
	 * Sanitize settings on save.
	 *
	 * Unchecked checkboxes are absent from POST data, so we compare against
	 * the full list of known sources/tags to correctly set false values.
	 *
	 * @param array $input Raw settings input.
	 * @return array Sanitized settings.
	 */
	public static function sanitize_settings( $input ): array {
		$sanitized = array(
			'sources'          => array(),
			'related_variants' => array(),
			'tags'             => array(),
			'diagnostics'      => array(),
		);

		$groups = self::get_tag_groups();

		// Sources: checked = true, unchecked = false.
		foreach ( array_keys( $groups ) as $source_key ) {
			$sanitized['sources'][ $source_key ] = ! empty( $input['sources'][ $source_key ] );
		}

		// Related variants: only for sources that have a related variant sub-group.
		foreach ( $groups as $source_key => $group ) {
			if ( ! empty( $group['has_related_variant'] ) ) {
				$sanitized['related_variants'][ $source_key ] = ! empty( $input['related_variants'][ $source_key ] );
			}
		}

		// Tags (direct + related): checked = true, unchecked = false.
		$tag_map = self::get_tag_source_map();
		foreach ( array_keys( $tag_map ) as $tag_name ) {
			$sanitized['tags'][ $tag_name ] = ! empty( $input['tags'][ $tag_name ] );
		}

		// Try tags: individual tag enables (not tied to a source).
		foreach ( array_keys( self::get_try_tag_names() ) as $tag_name ) {
			$sanitized['tags'][ $tag_name ] = ! empty( $input['tags'][ $tag_name ] );
		}

		// Diagnostics: checked = true, unchecked = false.
		$sanitized['diagnostics']['benchmark_logging']    = ! empty( $input['diagnostics']['benchmark_logging'] );
		$sanitized['diagnostics']['benchmark_page']       = ! empty( $input['diagnostics']['benchmark_page'] );
		$sanitized['diagnostics']['registration_logging'] = ! empty( $input['diagnostics']['registration_logging'] );

		return $sanitized;
	}

	/**
	 * Check if benchmark logging is enabled.
	 *
	 * @return bool
	 */
	public static function is_benchmark_logging_enabled(): bool {
		$settings = self::get_settings();
		return $settings['diagnostics']['benchmark_logging'] ?? false;
	}

	/**
	 * Check if the benchmark admin page is enabled.
	 *
	 * @return bool
	 */
	public static function is_benchmark_page_enabled(): bool {
		$settings = self::get_settings();
		return $settings['diagnostics']['benchmark_page'] ?? false;
	}

	/**
	 * Check if source registration logging is enabled.
	 *
	 * @return bool
	 */
	public static function is_registration_logging_enabled(): bool {
		$settings = self::get_settings();
		return $settings['diagnostics']['registration_logging'] ?? false;
	}

	/**
	 * Get settings (cached).
	 *
	 * @return array
	 */
	public static function get_settings(): array {
		if ( null === self::$settings ) {
			self::$settings = get_option( self::OPTION_NAME, array() );
		}
		return self::$settings;
	}

	/**
	 * Register a tag with its source metadata for default-enabled resolution.
	 *
	 * Called by TagTemplateRegistry immediately before is_tag_enabled() during
	 * generate_all_tags(). The fourth argument carries the per-template default
	 * from default_enabled_map; null defers to the source-level default.
	 *
	 * @since 1.2.0
	 * @param string          $tag_name    Full tag name.
	 * @param SourceInterface $source      Source object.
	 * @param bool            $is_related  Whether this is a related-variant tag.
	 * @param ?bool           $tag_default Per-template default override (null = use source default).
	 */
	public static function register_tag_source( string $tag_name, SourceInterface $source, bool $is_related, ?bool $tag_default = null ): void {
		self::$_registered_tags[ $tag_name ] = [ $source, $is_related, $tag_default ];
	}

	/**
	 * Check if a source is enabled.
	 *
	 * Defaults to true (all sources enabled unless explicitly disabled).
	 *
	 * @param string $source_key Source key.
	 * @return bool
	 */
	public static function is_source_enabled( string $source_key ): bool {
		$settings = self::get_settings();
		return $settings['sources'][ $source_key ] ?? true;
	}

	/**
	 * Check if a tag is enabled.
	 *
	 * A tag is disabled if its source is disabled, its related variant group is disabled
	 * (when applicable), or it is individually disabled.
	 * Defaults to true (all tags enabled unless explicitly disabled).
	 *
	 * @param string              $tag_name    Tag name.
	 * @param string              $source_key  Source key (for deprecated-tag callers).
	 * @param SourceInterface|null $source_obj  Source object (for generation callers; bypasses _registered_tags for default resolution).
	 * @param bool                $is_related  Whether this is a related-variant tag (used with $source_obj).
	 * @param bool|null           $tag_default Per-template default override (used with $source_obj; null defers to source default).
	 * @return bool
	 */
	public static function is_tag_enabled(
		string $tag_name,
		string $source_key = '',
		?SourceInterface $source_obj = null,
		bool $is_related = false,
		?bool $tag_default = null
	): bool {
		// Resolve source key from object when not passed directly.
		$sk = $source_key ?: ( $source_obj ? $source_obj->get_source_key() : '' );

		// If source is disabled, tag is disabled.
		if ( $sk && ! self::is_source_enabled( $sk ) ) {
			return false;
		}

		// If tag belongs to a related variant group and that group is disabled, tag is disabled.
		$src_obj = $source_obj ?? ( $sk ? SourceRegistry::get_source( $sk ) : null );
		if ( $src_obj && $src_obj->has_related_variant() ) {
			$related_prefix = $src_obj->get_related_tag_prefix() . '_';
			if ( str_starts_with( $tag_name, $related_prefix ) ) {
				if ( ! self::is_related_variant_enabled( $sk ) ) {
					return false;
				}
			}
		}

		$settings = self::get_settings();
		if ( isset( $settings['tags'][ $tag_name ] ) ) {
			return (bool) $settings['tags'][ $tag_name ];
		}

		// No saved preference. If source context was passed inline, resolve default from it
		// directly without requiring _registered_tags to be populated first.
		if ( null !== $source_obj ) {
			if ( null !== $tag_default ) {
				return $tag_default;
			}
			return $is_related
				? $source_obj->related_variant_default_enabled()
				: $source_obj->source_default_enabled();
		}

		// Fall back to _registered_tags (used by deprecated-tag callers and try_ tags).
		$info = self::$_registered_tags[ $tag_name ] ?? null;
		if ( $info ) {
			[ $src, $rel, $def ] = $info;
			if ( null !== $def ) {
				return $def;
			}
			if ( $src ) {
				return $rel
					? $src->related_variant_default_enabled()
					: $src->source_default_enabled();
			}
		}

		return true;
	}

	/**
	 * Check if related variant tags are enabled for a source.
	 *
	 * Defaults to true for the 'post' source (backward compat — existing related_post_* tags
	 * are enabled by default). Defaults to false for all other sources (external sources start
	 * with related variants off).
	 *
	 * @param string $source_key Source key.
	 * @return bool
	 */
	public static function is_related_variant_enabled( string $source_key ): bool {
		$settings = self::get_settings();
		$source   = SourceRegistry::get_source( $source_key );
		$default  = $source ? $source->related_variant_default_enabled() : ( 'post' === $source_key );
		return $settings['related_variants'][ $source_key ] ?? $default;
	}

	/**
	 * Get the default enabled state for a tag from the registered tag map.
	 *
	 * Used by render_page() to correctly pre-check new opt-in tags before the
	 * user has ever visited the settings page.
	 *
	 * @since 1.2.0
	 * @param string $tag_name Tag name.
	 * @return bool
	 */
	private static function get_tag_default( string $tag_name ): bool {
		$info = self::$_registered_tags[ $tag_name ] ?? null;
		if ( $info ) {
			[ $src, $is_related, $tag_default ] = $info;
			if ( null !== $tag_default ) {
				return $tag_default;
			}
			if ( $src ) {
				return $is_related
					? $src->related_variant_default_enabled()
					: $src->source_default_enabled();
			}
		}
		return true;
	}

	/**
	 * Get tag metadata grouped by source for the settings UI.
	 *
	 * Dynamically computed from registered sources and template registry. Mirrors the logic of
	 * TagTemplateRegistry::generate_all_tags() to determine what tags each source would produce,
	 * without actually registering them.
	 *
	 * @return array {
	 *     source_key => [
	 *         'label'               => string,
	 *         'has_related_variant' => bool,
	 *         'tags'                => [ tag_name => label ],   // direct tags
	 *         'related_tags'        => [ tag_name => label ],   // related variant tags
	 *     ]
	 * }
	 */
	public static function get_tag_groups(): array {
		$groups = array();

		$sources   = SourceRegistry::get_all_sources();
		$templates = TagTemplateRegistry::get_templates();

		// Known GB built-in tag names that our templates would otherwise generate.
		// These are always skipped by generate_all_tags() dup-check.
		$skip_names = array( 'post_title', 'post_excerpt', 'post_permalink' );

		// Track computed names to prevent cross-source duplicates (mirrors generate_all_tags logic).
		$computed = $skip_names;

		foreach ( $sources as $source ) {
			$sk             = $source->get_source_key();
			$source_context = $source->get_context_type();

			$groups[ $sk ] = array(
				'label'               => $source->get_title_prefix(),
				'has_related_variant' => $source->has_related_variant(),
				'tags'                => array(),
				'related_tags'        => array(),
			);

			// --- Direct tags ---
			foreach ( $templates as $tpl ) {
				$supported_contexts = $tpl['context_types'] ?? array( 'post' );
				if ( ! in_array( $source_context, $supported_contexts, true ) ) {
					continue;
				}

				if ( 'term' === $source_context && ! empty( $tpl['term_requires_gb_pro'] ) ) {
					if ( ! class_exists( 'GenerateBlocks_Pro_Dynamic_Tags_Register' ) ) {
						continue;
					}
				}

				if ( in_array(
					$sk,
					array_merge(
						$tpl['excluded_source_keys'] ?? array(),
						$tpl['excluded_direct_source_keys'] ?? array()
					),
					true
				) ) {
					continue;
				}

				$tag_name = $source->get_tag_prefix() . '_' . $tpl['key'];

				if ( in_array( $tag_name, $computed, true ) ) {
					continue;
				}
				$computed[] = $tag_name;

				// Skip if no usable core function for this context.
				$cf = ( 'term' === $source_context )
					? ( $tpl['term_core_fn'] ?? null )
					: ( $tpl['core_fn'] ?? null );

				if ( ! $cf ) {
					continue;
				}

				$groups[ $sk ]['tags'][ $tag_name ] = $source->get_title_prefix() . ' ' . $tpl['title'];
			}

			// --- Related variant tags ---
			if ( ! $source->has_related_variant() ) {
				continue;
			}

			foreach ( $templates as $tpl ) {
				if ( in_array( $sk, $tpl['excluded_source_keys'] ?? array(), true ) ) {
					continue;
				}

				// Related variants always resolve to a post; only 'post'-context templates apply.
				$supported_contexts = $tpl['context_types'] ?? array( 'post' );
				if ( ! in_array( 'post', $supported_contexts, true ) ) {
					continue;
				}

				$tag_name = $source->get_related_tag_prefix() . '_' . $tpl['key'];

				if ( in_array( $tag_name, $computed, true ) ) {
					continue;
				}
				$computed[] = $tag_name;

				$cf = $tpl['core_fn'] ?? null;
				if ( ! $cf ) {
					continue;
				}

				$groups[ $sk ]['related_tags'][ $tag_name ] = $source->get_related_title_prefix() . ' ' . $tpl['title'];
			}
		}

		// --- Deprecated wrappers ---
		// These are registered via bws_register_deprecated_tags() (not the template system).
		// Appended to their respective source groups.
		$deprecated = array(
			'post'  => array(
				'tags'         => array(
					'current_post_featured_image' => __( 'current_post_featured_image (Deprecated)', 'generateblocks' ),
					'current_post_meta_image'     => __( 'current_post_meta_image (Deprecated)', 'generateblocks' ),
					'post_acf_date_time_single'   => __( 'post_acf_date_time_single (Deprecated)', 'generateblocks' ),
					'post_acf_date_time_range'    => __( 'post_acf_date_time_range (Deprecated)', 'generateblocks' ),
				),
				'related_tags' => array(
					'related_post_meta_image' => __( 'related_post_meta_image (Deprecated)', 'generateblocks' ),
					'related_post_url'        => __( 'related_post_url (Deprecated)', 'generateblocks' ),
				),
			),
			'term'  => array(
				'tags'         => array(
					'term_name'        => __( 'term_name (Deprecated)', 'generateblocks' ),
					'term_field_image' => __( 'term_field_image (Deprecated)', 'generateblocks' ),
				),
				'related_tags' => array(),
			),
		);

		foreach ( $deprecated as $sk => $dep ) {
			if ( ! isset( $groups[ $sk ] ) ) {
				continue;
			}
			foreach ( $dep['tags'] as $tag_name => $label ) {
				$groups[ $sk ]['tags'][ $tag_name ] = $label;
			}
			foreach ( $dep['related_tags'] as $tag_name => $label ) {
				$groups[ $sk ]['related_tags'][ $tag_name ] = $label;
			}
		}

		// External deprecated wrappers registered via DeprecatedTagRegistry.
		foreach ( DeprecatedTagRegistry::get_all() as $entry ) {
			$sk  = $entry['source_key'] ?? '';
			$tag = $entry['old_tag'] ?? '';
			if ( ! $sk || ! $tag || ! isset( $groups[ $sk ] ) ) {
				continue;
			}
			// Match built-in label style: tag name + " (Deprecated)".
			$label = $tag . ' (Deprecated)';
			if ( ! empty( $entry['is_related'] ) ) {
				$groups[ $sk ]['related_tags'][ $tag ] = $label;
			} else {
				$groups[ $sk ]['tags'][ $tag ] = $label;
			}
		}

		return $groups;
	}

	/**
	 * Get try_ tag names and labels, in template registration order.
	 *
	 * These are source-independent fallback-chain tags (try_featured_image, try_title, etc.)
	 * generated by TagTemplateRegistry::generate_try_tags(). Each defaults to enabled.
	 *
	 * @return array tag_name => label
	 */
	public static function get_try_tag_names(): array {
		$try_tags = array();
		foreach ( TagTemplateRegistry::get_templates() as $tpl ) {
			if ( empty( $tpl['supports_try'] ) ) {
				continue;
			}
			$tag_name = 'try_' . $tpl['key'];
			/* translators: %s: tag title e.g. "Featured Image" */
			$try_tags[ $tag_name ] = sprintf( __( 'Try %s', 'generateblocks' ), $tpl['title'] );
		}
		return $try_tags;
	}

	/**
	 * Get flat tag → source mapping (direct + related tags).
	 *
	 * @return array tag_name => source_key
	 */
	public static function get_tag_source_map(): array {
		$map    = array();
		$groups = self::get_tag_groups();

		foreach ( $groups as $source_key => $group ) {
			foreach ( array_keys( $group['tags'] ) as $tag_name ) {
				$map[ $tag_name ] = $source_key;
			}
			foreach ( array_keys( $group['related_tags'] ) as $tag_name ) {
				$map[ $tag_name ] = $source_key;
			}
		}

		return $map;
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();
		$groups   = self::get_tag_groups();
		?>
		<div class="wrap bws-dynamic-tags-settings">
			<h1><?php echo esc_html__( 'BWS Dynamic Tag Extensions', 'generateblocks' ); ?></h1>
			<p class="description">
				<?php echo esc_html__( 'Enable or disable tag sources and individual tags. Disabled tags will not appear in the GenerateBlocks editor.', 'generateblocks' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'bws_dynamic_tags_settings_group' ); ?>

				<?php foreach ( $groups as $source_key => $group ) :
					$source_enabled  = self::is_source_enabled( $source_key );
					$source_id       = 'bws-source-' . esc_attr( $source_key );
					$has_related     = ! empty( $group['has_related_variant'] );
					$related_enabled = $has_related ? self::is_related_variant_enabled( $source_key ) : false;
				?>
				<div class="bws-tag-group" data-source="<?php echo esc_attr( $source_key ); ?>">
					<h2 class="bws-source-header">
						<label for="<?php echo $source_id; ?>">
							<input
								type="checkbox"
								id="<?php echo $source_id; ?>"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[sources][<?php echo esc_attr( $source_key ); ?>]"
								value="1"
								class="bws-source-toggle"
								data-source="<?php echo esc_attr( $source_key ); ?>"
								<?php checked( $source_enabled ); ?>
							/>
							<?php echo esc_html( $group['label'] ); ?>
						</label>
					</h2>

					<?php if ( ! empty( $group['tags'] ) ) : ?>
					<table class="bws-tags-table bws-direct-tags widefat" data-source="<?php echo esc_attr( $source_key ); ?>">
						<tbody>
						<?php foreach ( $group['tags'] as $tag_name => $tag_label ) :
							$tag_enabled = isset( $settings['tags'][ $tag_name ] ) ? (bool) $settings['tags'][ $tag_name ] : self::get_tag_default( $tag_name );
							$tag_id      = 'bws-tag-' . esc_attr( $tag_name );
						?>
							<tr class="bws-tag-row <?php echo $source_enabled ? '' : 'bws-disabled'; ?>">
								<td class="bws-tag-checkbox">
									<input
										type="checkbox"
										id="<?php echo $tag_id; ?>"
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[tags][<?php echo esc_attr( $tag_name ); ?>]"
										value="1"
										class="bws-tag-toggle"
										<?php checked( $tag_enabled ); ?>
										<?php disabled( ! $source_enabled ); ?>
									/>
								</td>
								<td>
									<label for="<?php echo $tag_id; ?>">
										<?php echo esc_html( $tag_label ); ?>
									</label>
									<code class="bws-tag-name"><?php echo esc_html( $tag_name ); ?></code>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php endif; ?>

					<?php if ( $has_related && ! empty( $group['related_tags'] ) ) :
						$rv_toggle_id = 'bws-related-variants-' . esc_attr( $source_key );
						/* translators: %s: Source label, e.g. "Post" */
						$rv_label     = sprintf( __( '%s Related Variants', 'generateblocks' ), $group['label'] );
						$rv_disabled  = ! $source_enabled || ! $related_enabled;
					?>
					<div class="bws-related-variant-section <?php echo $source_enabled ? '' : 'bws-disabled'; ?>"
						data-source="<?php echo esc_attr( $source_key ); ?>">
						<h3 class="bws-related-variant-header">
							<label for="<?php echo $rv_toggle_id; ?>">
								<input
									type="checkbox"
									id="<?php echo $rv_toggle_id; ?>"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[related_variants][<?php echo esc_attr( $source_key ); ?>]"
									value="1"
									class="bws-related-variant-toggle"
									data-source="<?php echo esc_attr( $source_key ); ?>"
									<?php checked( $related_enabled ); ?>
									<?php disabled( ! $source_enabled ); ?>
								/>
								<?php echo esc_html( $rv_label ); ?>
							</label>
						</h3>

						<table class="bws-tags-table bws-related-tags widefat" data-source="<?php echo esc_attr( $source_key ); ?>">
							<tbody>
							<?php foreach ( $group['related_tags'] as $tag_name => $tag_label ) :
								$tag_enabled = isset( $settings['tags'][ $tag_name ] ) ? (bool) $settings['tags'][ $tag_name ] : self::get_tag_default( $tag_name );
								$tag_id      = 'bws-tag-' . esc_attr( $tag_name );
							?>
								<tr class="bws-tag-row <?php echo $rv_disabled ? 'bws-disabled' : ''; ?>">
									<td class="bws-tag-checkbox">
										<input
											type="checkbox"
											id="<?php echo $tag_id; ?>"
											name="<?php echo esc_attr( self::OPTION_NAME ); ?>[tags][<?php echo esc_attr( $tag_name ); ?>]"
											value="1"
											class="bws-tag-toggle"
											<?php checked( $tag_enabled ); ?>
											<?php disabled( $rv_disabled ); ?>
										/>
									</td>
									<td>
										<label for="<?php echo $tag_id; ?>">
											<?php echo esc_html( $tag_label ); ?>
										</label>
										<code class="bws-tag-name"><?php echo esc_html( $tag_name ); ?></code>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php endif; ?>

				</div>
				<?php endforeach; ?>

				<?php $try_tag_names = self::get_try_tag_names();
				if ( ! empty( $try_tag_names ) ) : ?>
				<div class="bws-tag-group">
					<h2 class="bws-source-header">
						<?php esc_html_e( 'Try Tags (Fallback Chain)', 'generateblocks' ); ?>
					</h2>
					<table class="bws-tags-table widefat">
						<tbody>
						<?php foreach ( $try_tag_names as $tag_name => $tag_label ) :
							$tag_enabled = isset( $settings['tags'][ $tag_name ] ) ? (bool) $settings['tags'][ $tag_name ] : self::get_tag_default( $tag_name );
							$tag_id      = 'bws-tag-' . esc_attr( $tag_name );
						?>
							<tr class="bws-tag-row">
								<td class="bws-tag-checkbox">
									<input
										type="checkbox"
										id="<?php echo $tag_id; ?>"
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[tags][<?php echo esc_attr( $tag_name ); ?>]"
										value="1"
										class="bws-tag-toggle"
										<?php checked( $tag_enabled ); ?>
									/>
								</td>
								<td>
									<label for="<?php echo $tag_id; ?>">
										<?php echo esc_html( $tag_label ); ?>
									</label>
									<code class="bws-tag-name"><?php echo esc_html( $tag_name ); ?></code>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>

				<div class="bws-tag-group">
				<h2 class="bws-source-header" style="border-bottom:1px solid #c3c4c7">
					<?php esc_html_e( 'Diagnostics', 'generateblocks' ); ?>
				</h2>
				<table class="bws-tags-table widefat">
					<tbody>
						<tr class="bws-tag-row">
							<td class="bws-tag-checkbox">
								<input
									type="checkbox"
									id="bws-diag-benchmark-logging"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[diagnostics][benchmark_logging]"
									value="1"
									<?php checked( self::is_benchmark_logging_enabled() ); ?>
								/>
							</td>
							<td>
								<label for="bws-diag-benchmark-logging">
									<?php esc_html_e( 'Enable benchmark logging', 'generateblocks' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Log post content processing time and memory usage to the PHP error log. Active even when WP_DEBUG is off.', 'generateblocks' ); ?>
								</p>
							</td>
						</tr>
						<tr class="bws-tag-row">
							<td class="bws-tag-checkbox">
								<input
									type="checkbox"
									id="bws-diag-benchmark-page"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[diagnostics][benchmark_page]"
									value="1"
									<?php checked( self::is_benchmark_page_enabled() ); ?>
								/>
							</td>
							<td>
								<label for="bws-diag-benchmark-page">
									<?php esc_html_e( 'Enable benchmark admin page', 'generateblocks' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Adds a Benchmark submenu under GenerateBlocks for testing post content processing performance.', 'generateblocks' ); ?>
								</p>
							</td>
						</tr>
						<tr class="bws-tag-row">
							<td class="bws-tag-checkbox">
								<input
									type="checkbox"
									id="bws-diag-registration-logging"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[diagnostics][registration_logging]"
									value="1"
									<?php checked( self::is_registration_logging_enabled() ); ?>
								/>
							</td>
							<td>
								<label for="bws-diag-registration-logging">
									<?php esc_html_e( 'Enable source registration logging', 'generateblocks' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Log source registration and the bws_dynamic_tags_register_sources action to the PHP error log. Disable after confirming external sources are loading correctly.', 'generateblocks' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<?php submit_button( __( 'Save Settings', 'generateblocks' ) ); ?>
			</form>
		</div>

		<style>
			.bws-dynamic-tags-settings .bws-tag-group {
				margin-bottom: 24px;
			}
			.bws-dynamic-tags-settings .bws-source-header {
				margin: 0 0 4px;
				padding: 10px 12px;
				background: #f0f0f1;
				border: 1px solid #c3c4c7;
				border-bottom: none;
				font-size: 14px;
			}
			.bws-dynamic-tags-settings .bws-source-header label {
				display: flex;
				align-items: center;
				gap: 8px;
				cursor: pointer;
				font-weight: 600;
			}
			.bws-dynamic-tags-settings .bws-tags-table {
				border-top: none;
			}
			.bws-dynamic-tags-settings .bws-tag-row td {
				padding: 6px 12px;
				vertical-align: middle;
			}
			.bws-dynamic-tags-settings .bws-tag-checkbox {
				width: 30px;
			}
			.bws-dynamic-tags-settings .bws-tag-row label {
				cursor: pointer;
			}
			.bws-dynamic-tags-settings .bws-tag-name {
				margin-left: 8px;
				font-size: 12px;
				color: #787c82;
			}
			.bws-dynamic-tags-settings .bws-tag-row.bws-disabled {
				opacity: 0.5;
			}
			.bws-dynamic-tags-settings .bws-tag-row.bws-disabled label {
				cursor: default;
			}
			.bws-dynamic-tags-settings .bws-related-variant-section {
				margin-left: 20px;
				margin-top: 8px;
			}
			.bws-dynamic-tags-settings .bws-related-variant-header {
				margin: 0 0 4px;
				padding: 8px 12px;
				background: #f6f7f7;
				border: 1px solid #c3c4c7;
				border-bottom: none;
				font-size: 13px;
				font-weight: normal;
			}
			.bws-dynamic-tags-settings .bws-related-variant-header label {
				display: flex;
				align-items: center;
				gap: 8px;
				cursor: pointer;
				font-weight: 500;
			}
			.bws-dynamic-tags-settings .bws-related-variant-section.bws-disabled .bws-related-variant-header {
				opacity: 0.5;
			}
		</style>

		<script>
			( function() {
				// Source master toggle: controls direct tags + related variant section.
				document.querySelectorAll( '.bws-source-toggle' ).forEach( function( toggle ) {
					toggle.addEventListener( 'change', function() {
						var source  = this.dataset.source;
						var enabled = this.checked;

						// Toggle direct tag rows.
						var directTable = document.querySelector( '.bws-direct-tags[data-source="' + source + '"]' );
						if ( directTable ) {
							directTable.querySelectorAll( '.bws-tag-row' ).forEach( function( row ) {
								var cb = row.querySelector( '.bws-tag-toggle' );
								if ( enabled ) {
									row.classList.remove( 'bws-disabled' );
									cb.disabled = false;
								} else {
									row.classList.add( 'bws-disabled' );
									cb.disabled = true;
								}
							} );
						}

						// Toggle related variant section.
						var rvSection = document.querySelector( '.bws-related-variant-section[data-source="' + source + '"]' );
						if ( rvSection ) {
							var rvToggle = rvSection.querySelector( '.bws-related-variant-toggle' );

							if ( enabled ) {
								rvSection.classList.remove( 'bws-disabled' );
								if ( rvToggle ) rvToggle.disabled = false;
							} else {
								rvSection.classList.add( 'bws-disabled' );
								if ( rvToggle ) rvToggle.disabled = true;
							}

							// Related tag rows depend on both source AND related variant toggle state.
							var rvEnabled = enabled && rvToggle && rvToggle.checked;
							var rvTable   = rvSection.querySelector( '.bws-related-tags' );
							if ( rvTable ) {
								rvTable.querySelectorAll( '.bws-tag-row' ).forEach( function( row ) {
									var cb = row.querySelector( '.bws-tag-toggle' );
									if ( rvEnabled ) {
										row.classList.remove( 'bws-disabled' );
										cb.disabled = false;
									} else {
										row.classList.add( 'bws-disabled' );
										cb.disabled = true;
									}
								} );
							}
						}
					} );
				} );

				// Related variant sub-group toggle: controls related tag rows only.
				document.querySelectorAll( '.bws-related-variant-toggle' ).forEach( function( toggle ) {
					toggle.addEventListener( 'change', function() {
						var source  = this.dataset.source;
						var enabled = this.checked;

						var rvTable = document.querySelector( '.bws-related-tags[data-source="' + source + '"]' );
						if ( ! rvTable ) return;

						rvTable.querySelectorAll( '.bws-tag-row' ).forEach( function( row ) {
							var cb = row.querySelector( '.bws-tag-toggle' );
							if ( enabled ) {
								row.classList.remove( 'bws-disabled' );
								cb.disabled = false;
							} else {
								row.classList.add( 'bws-disabled' );
								cb.disabled = true;
							}
						} );
					} );
				} );
			} )();
		</script>
		<?php
	}
}
