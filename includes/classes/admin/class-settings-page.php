<?php
/**
 * Admin Settings Page for BWS Dynamic Tag Extensions.
 *
 * Settings schema (v1.6.0):
 *   {
 *     modifiers:   { term: bool, try: bool },
 *     deprecated:  { [old_tag_name]: bool },
 *     diagnostics: { benchmark_logging: bool, benchmark_page: bool, registration_logging: bool },
 *   }
 *
 * Removed in v1.6.0: source×template matrix, source enable/disable controls,
 * register_tag_source(), get_tag_groups(), is_tag_enabled(), is_source_enabled(),
 * default_enabled_map resolution logic.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 * @since 1.6.0 Simplified to modifier-toggle + deprecated-section model.
 */

namespace BWS\DynamicTags\Admin;

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

	/**
	 * Initialize admin hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( static::class, 'add_menu_page' ), 20 );
		add_action( 'admin_init', array( static::class, 'register_settings' ) );
		add_action( 'wp_ajax_bws_convert_deprecated_tag', array( TagConverter::class, 'ajax_handler' ) );
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

	// ===============================================
	// SETTINGS SCHEMA + SANITIZE
	// ===============================================

	/**
	 * Sanitize settings on save.
	 *
	 * @param array $input Raw POST input.
	 * @return array Sanitized settings.
	 */
	public static function sanitize_settings( $input ): array {
		$sanitized = array(
			'modifiers'   => array(),
			'deprecated'  => array(),
			'diagnostics' => array(),
		);

		// Modifier toggles.
		$sanitized['modifiers']['term'] = ! empty( $input['modifiers']['term'] );
		$sanitized['modifiers']['try']  = ! empty( $input['modifiers']['try'] );

		// Deprecated tag enable/disable checkboxes.
		foreach ( DeprecatedTagRegistry::get_all() as $entry ) {
			$tag_name = $entry['old_tag'] ?? '';
			if ( '' === $tag_name ) {
				continue;
			}
			$sanitized['deprecated'][ $tag_name ] = ! empty( $input['deprecated'][ $tag_name ] );
		}

		// Diagnostics.
		$sanitized['diagnostics']['benchmark_logging']    = ! empty( $input['diagnostics']['benchmark_logging'] );
		$sanitized['diagnostics']['benchmark_page']       = ! empty( $input['diagnostics']['benchmark_page'] );
		$sanitized['diagnostics']['registration_logging'] = ! empty( $input['diagnostics']['registration_logging'] );

		return $sanitized;
	}

	// ===============================================
	// SETTINGS ACCESSORS
	// ===============================================

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
	 * Check if a modifier group is enabled.
	 *
	 * Supported modifiers: 'term', 'try'.
	 * Defaults to true when no saved preference exists.
	 *
	 * @since 1.6.0
	 * @param string $modifier Modifier key ('term' or 'try').
	 * @return bool
	 */
	public static function is_modifier_enabled( string $modifier ): bool {
		$settings = self::get_settings();
		if ( isset( $settings['modifiers'][ $modifier ] ) ) {
			return (bool) $settings['modifiers'][ $modifier ];
		}
		return true; // Default on.
	}

	/**
	 * Check if a deprecated tag is enabled.
	 *
	 * Returns true when no saved preference exists (default on).
	 *
	 * @since 1.6.0
	 * @param string $tag_name Deprecated GB tag name.
	 * @return bool
	 */
	public static function is_deprecated_tag_enabled( string $tag_name ): bool {
		$settings = self::get_settings();
		if ( isset( $settings['deprecated'][ $tag_name ] ) ) {
			return (bool) $settings['deprecated'][ $tag_name ];
		}
		return true; // Default on.
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

	// ===============================================
	// RENDER
	// ===============================================

	/**
	 * Render the settings page.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings           = self::get_settings();
		$deprecated_entries = DeprecatedTagRegistry::get_all();
		$has_deprecated     = ! empty( $deprecated_entries );
		$convert_nonce      = wp_create_nonce( 'bws_convert_tag' );
		?>
		<div class="wrap bws-dynamic-tags-settings">
			<h1><?php echo esc_html__( 'BWS Dynamic Tag Extensions', 'generateblocks' ); ?></h1>
			<p class="description">
				<?php echo esc_html__( 'Enable or disable modifier tag groups and manage deprecated tag migration.', 'generateblocks' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'bws_dynamic_tags_settings_group' ); ?>

				<?php /* ── Modifier Groups ── */ ?>
				<div class="bws-tag-group">
					<h2 class="bws-source-header">
						<?php esc_html_e( 'Modifier Groups', 'generateblocks' ); ?>
					</h2>
					<table class="bws-tags-table widefat">
						<tbody>
							<tr class="bws-tag-row">
								<td class="bws-tag-checkbox">
									<input
										type="checkbox"
										id="bws-modifier-term"
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[modifiers][term]"
										value="1"
										<?php checked( self::is_modifier_enabled( 'term' ) ); ?>
									/>
								</td>
								<td>
									<label for="bws-modifier-term">
										<?php esc_html_e( 'term_ tags', 'generateblocks' ); ?>
									</label>
									<code class="bws-tag-name">term_</code>
									<p class="description">
										<?php esc_html_e( 'Term-context tags (term_text, term_image, term_title, etc.). Disable to remove them from the tag picker.', 'generateblocks' ); ?>
									</p>
								</td>
							</tr>
							<tr class="bws-tag-row">
								<td class="bws-tag-checkbox">
									<input
										type="checkbox"
										id="bws-modifier-try"
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[modifiers][try]"
										value="1"
										<?php checked( self::is_modifier_enabled( 'try' ) ); ?>
									/>
								</td>
								<td>
									<label for="bws-modifier-try">
										<?php esc_html_e( 'try_ tags', 'generateblocks' ); ?>
									</label>
									<code class="bws-tag-name">try_</code>
									<p class="description">
										<?php esc_html_e( 'Fallback-chain tags (try_text, try_image, etc.). Disable to remove them from the tag picker.', 'generateblocks' ); ?>
									</p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<?php /* ── Deprecated Tags ── */ ?>
				<?php if ( $has_deprecated ) : ?>
				<div class="bws-tag-group" id="bws-deprecated-section">
					<h2 class="bws-source-header">
						<?php esc_html_e( 'Deprecated Tags', 'generateblocks' ); ?>
					</h2>
					<p class="description" style="padding: 8px 12px; margin: 0; background: #fff; border: 1px solid #c3c4c7; border-top: none; border-bottom: none;">
						<?php esc_html_e( 'These tags still work but have been replaced. Use the Convert button to migrate saved post content to the current tag format.', 'generateblocks' ); ?>
					</p>
					<table class="bws-tags-table bws-deprecated-table widefat">
						<tbody>
						<?php foreach ( $deprecated_entries as $entry ) :
							$tag_name   = $entry['old_tag'] ?? '';
							$new_tag    = $entry['new_tag'] ?? '';
							$since      = $entry['since']   ?? '';
							if ( '' === $tag_name ) {
								continue;
							}
							$enabled = isset( $settings['deprecated'][ $tag_name ] )
								? (bool) $settings['deprecated'][ $tag_name ]
								: true;
							$cb_id = 'bws-dep-' . esc_attr( $tag_name );
						?>
							<tr class="bws-tag-row" data-tag="<?php echo esc_attr( $tag_name ); ?>">
								<td class="bws-tag-checkbox">
									<input
										type="checkbox"
										id="<?php echo $cb_id; ?>"
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[deprecated][<?php echo esc_attr( $tag_name ); ?>]"
										value="1"
										<?php checked( $enabled ); ?>
									/>
								</td>
								<td>
									<label for="<?php echo $cb_id; ?>">
										<?php echo esc_html( $entry['title'] ?? $tag_name ); ?>
									</label>
									<code class="bws-tag-name"><?php echo esc_html( $tag_name ); ?></code>
									<?php if ( $new_tag ) : ?>
									<span class="bws-dep-arrow">→</span>
									<code class="bws-tag-name bws-new-tag"><?php echo esc_html( $new_tag ); ?></code>
									<?php endif; ?>
									<?php if ( $since ) : ?>
									<span class="bws-dep-since">
										<?php
										echo esc_html(
											/* translators: %s: plugin version number */
											sprintf( __( '(since %s)', 'generateblocks' ), $since )
										);
										?>
									</span>
									<?php endif; ?>
								</td>
								<td class="bws-convert-cell">
									<div class="bws-convert-actions">
										<button
											type="button"
											class="button bws-list-btn"
											data-tag="<?php echo esc_attr( $tag_name ); ?>"
											data-nonce="<?php echo esc_attr( $convert_nonce ); ?>"
										>
											<?php esc_html_e( 'List Posts', 'generateblocks' ); ?>
										</button>
										<?php if ( DeprecatedTagRegistry::has_migration_path( $tag_name ) ) : ?>
										<button
											type="button"
											class="button bws-convert-btn"
											data-tag="<?php echo esc_attr( $tag_name ); ?>"
											data-nonce="<?php echo esc_attr( $convert_nonce ); ?>"
										>
											<?php esc_html_e( 'Convert', 'generateblocks' ); ?>
										</button>
										<?php endif; ?>
										<span class="bws-convert-result" aria-live="polite"></span>
									</div>
									<div class="bws-list-result" style="display:none;"></div>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>

				<?php /* ── Diagnostics ── */ ?>
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
			.bws-dynamic-tags-settings .bws-new-tag {
				color: #2271b1;
			}
			.bws-dynamic-tags-settings .bws-dep-arrow {
				margin-left: 6px;
				color: #787c82;
			}
			.bws-dynamic-tags-settings .bws-dep-since {
				margin-left: 8px;
				font-size: 12px;
				color: #a0a0a0;
			}
			.bws-dynamic-tags-settings .bws-deprecated-table .bws-convert-cell {
				width: 240px;
			}
			.bws-dynamic-tags-settings .bws-convert-actions {
				display: flex;
				align-items: center;
				gap: 6px;
				flex-wrap: wrap;
			}
			.bws-dynamic-tags-settings .bws-convert-result {
				font-size: 13px;
			}
			.bws-dynamic-tags-settings .bws-convert-result.success {
				color: #00a32a;
			}
			.bws-dynamic-tags-settings .bws-convert-result.error {
				color: #d63638;
			}
			.bws-dynamic-tags-settings .bws-list-result {
				margin-top: 8px;
				padding: 6px 10px;
				background: #f6f7f7;
				border: 1px solid #dcdcde;
				font-size: 13px;
			}
			.bws-dynamic-tags-settings .bws-list-count {
				margin: 0 0 4px;
				color: #787c82;
			}
			.bws-dynamic-tags-settings .bws-post-list {
				margin: 0;
				padding: 0 0 0 16px;
				max-height: 180px;
				overflow-y: auto;
			}
			.bws-dynamic-tags-settings .bws-post-list li {
				margin: 2px 0;
			}
			.bws-dynamic-tags-settings .bws-list-error {
				color: #d63638;
			}
		</style>

		<script>
			( function() {
				var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

				function escHtml( str ) {
					return String( str )
						.replace( /&/g, '&amp;' )
						.replace( /</g, '&lt;' )
						.replace( />/g, '&gt;' )
						.replace( /"/g, '&quot;' );
				}

				// List Posts button.
				document.querySelectorAll( '.bws-list-btn' ).forEach( function( btn ) {
					btn.addEventListener( 'click', function() {
						var tagName = this.dataset.tag;
						var nonce   = this.dataset.nonce;
						var cell    = this.closest( 'td' );
						var panel   = cell.querySelector( '.bws-list-result' );

						// Toggle if results already loaded.
						if ( panel.dataset.loaded ) {
							panel.style.display = ( 'none' === panel.style.display ) ? 'block' : 'none';
							return;
						}

						btn.disabled = true;
						panel.innerHTML = '<em><?php echo esc_js( __( 'Searching…', 'generateblocks' ) ); ?></em>';
						panel.style.display = 'block';

						var data = new FormData();
						data.append( 'action',           'bws_convert_deprecated_tag' );
						data.append( 'nonce',            nonce );
						data.append( 'tag_name',         tagName );
						data.append( 'converter_action', 'list' );

						fetch( ajaxUrl, { method: 'POST', body: data } )
							.then( function( r ) { return r.json(); } )
							.then( function( json ) {
								panel.dataset.loaded = '1';
								if ( json.success ) {
									var posts = json.data.posts;
									var total = json.data.total;
									if ( 0 === total ) {
										panel.innerHTML = '<em><?php echo esc_js( __( 'No posts found.', 'generateblocks' ) ); ?></em>';
										return;
									}
									var html = '<p class="bws-list-count">';
									html += total + ' <?php echo esc_js( __( 'post(s) found:', 'generateblocks' ) ); ?>';
									html += '</p><ul class="bws-post-list">';
									posts.forEach( function( p ) {
										html += '<li><a href="' + escHtml( p.edit_url ) + '" target="_blank">' + escHtml( p.post_title ) + '</a></li>';
									} );
									html += '</ul>';
									panel.innerHTML = html;
								} else {
									var msg = ( json.data && json.data.message )
										? json.data.message
										: <?php echo wp_json_encode( __( 'Request failed.', 'generateblocks' ) ); ?>;
									panel.innerHTML = '<em class="bws-list-error">' + escHtml( msg ) + '</em>';
								}
							} )
							.catch( function() {
								panel.innerHTML = '<em class="bws-list-error"><?php echo esc_js( __( 'Request error.', 'generateblocks' ) ); ?></em>';
							} )
							.finally( function() {
								btn.disabled = false;
							} );
					} );
				} );

				// Convert button.
				document.querySelectorAll( '.bws-convert-btn' ).forEach( function( btn ) {
					btn.addEventListener( 'click', function() {
						var tagName = this.dataset.tag;
						var nonce   = this.dataset.nonce;
						var cell    = this.closest( 'td' );
						var result  = cell.querySelector( '.bws-convert-result' );
						var panel   = cell.querySelector( '.bws-list-result' );

						btn.disabled       = true;
						result.textContent = <?php echo wp_json_encode( __( 'Converting…', 'generateblocks' ) ); ?>;
						result.className   = 'bws-convert-result';

						var data = new FormData();
						data.append( 'action',           'bws_convert_deprecated_tag' );
						data.append( 'nonce',            nonce );
						data.append( 'tag_name',         tagName );
						data.append( 'converter_action', 'convert' );

						fetch( ajaxUrl, { method: 'POST', body: data } )
							.then( function( r ) { return r.json(); } )
							.then( function( json ) {
								if ( json.success ) {
									var count = json.data.count;
									result.textContent = count === 1
										? <?php echo wp_json_encode( __( 'Updated 1 post.', 'generateblocks' ) ); ?>
										: count.toString() + ' ' + <?php echo wp_json_encode( __( 'posts updated.', 'generateblocks' ) ); ?>;
									result.className = 'bws-convert-result success';
									// Invalidate cached list so next List Posts click re-fetches.
									if ( panel ) {
										delete panel.dataset.loaded;
										panel.style.display = 'none';
										panel.innerHTML = '';
									}
								} else {
									result.textContent = ( json.data && json.data.message )
										? json.data.message
										: <?php echo wp_json_encode( __( 'Conversion failed.', 'generateblocks' ) ); ?>;
									result.className = 'bws-convert-result error';
								}
							} )
							.catch( function() {
								result.textContent = <?php echo wp_json_encode( __( 'Request error.', 'generateblocks' ) ); ?>;
								result.className   = 'bws-convert-result error';
							} )
							.finally( function() {
								btn.disabled = false;
							} );
					} );
				} );
			} )();
		</script>
		<?php
	}
}
