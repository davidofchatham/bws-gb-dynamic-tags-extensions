<?php
/**
 * Plugin Name: GenerateBlocks Dynamic Tag Extensions by BWS
 * Description: Extends GenerateBlocks with custom dynamic tags for ACF integration, providing dynamic content from multiple post sources, date/time formatting, and taxonomy terms.
 * Version: 1.6.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: generateblocks-pro
 * Author: Bridge Web Solutions
 * Text Domain: generateblocks
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package BWS_Dynamic_Tags
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BWS_DYNAMIC_TAGS_VERSION', '1.6.0' );
define( 'BWS_DYNAMIC_TAGS_FILE', __FILE__ );
define( 'BWS_DYNAMIC_TAGS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BWS_DYNAMIC_TAGS_URL', plugin_dir_url( __FILE__ ) );

// PSR-4 Autoloader for BWS\DynamicTags namespace.
require_once BWS_DYNAMIC_TAGS_PATH . 'autoload.php';

/**
 * Check if GenerateBlocks is active and has dynamic tag support.
 *
 * @return bool
 */
function bws_dynamic_tags_check_dependencies() {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'BWS Dynamic Tag Extensions requires GenerateBlocks Pro with dynamic tag support.', 'generateblocks' );
			echo '</p></div>';
		} );
		return false;
	}
	// GB 2.0+ ships GenerateBlocks_Meta_Handler. Field-extraction helpers route through it.
	if ( ! class_exists( 'GenerateBlocks_Meta_Handler' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'BWS Dynamic Tag Extensions requires GenerateBlocks 2.0 or later (Meta_Handler API).', 'generateblocks' );
			echo '</p></div>';
		} );
		return false;
	}
	return true;
}

/**
 * Initialize the plugin.
 */
function bws_dynamic_tags_init() {
	if ( ! bws_dynamic_tags_check_dependencies() ) {
		return;
	}

	// Load helper functions.
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/image-helpers.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/content-helpers.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/datetime-helpers.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/taxonomy-helpers.php';

	// Initialize source registry (registers built-in sources and fires hook for external sources).
	\BWS\DynamicTags\SourceRegistry::init();

	// Initialize admin settings page.
	if ( is_admin() ) {
		\BWS\DynamicTags\Admin\SettingsPage::init();
	}

	// Register dynamic tags.
	add_action( 'init', 'bws_dynamic_tags_register_all', 20 );
}

/**
 * Register all dynamic tags (respecting admin settings).
 *
 * Tag files are required here; their registration functions are called directly
 * rather than via add_action('init') to avoid re-hooking inside an init callback.
 */
function bws_dynamic_tags_register_all() {
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/base-tags.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/content-tags.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/image-tags.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/datetime-tags.php'; // merged: includes date-only templates
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/taxonomy-tags.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/deprecated-tags.php';

	// Register base (source-agnostic) tags: text, content, title, permalink, image, datetime_single, datetime_range.
	bws_register_base_tags();

	// Generate try_ fallback-chain tags from modifier templates.
	\BWS\DynamicTags\TagTemplateRegistry::generate_base_try_tags();

	// Populate DeprecatedTagRegistry with N×M wrappers (must run before bws_register_deprecated_tags).
	bws_register_v1_deprecated_tag_wrappers();

	// Register migration entries for the eight pre-v1.6 hardcoded deprecated tags.
	bws_register_early_deprecated_tag_migrations();

	// Register option-key migrations for base tags with deprecated option names.
	bws_register_option_migrations();

	// Deprecated wrappers registered last (old tag names pointing to new core functions).
	bws_register_deprecated_tags();
}

/**
 * Enqueue block editor assets.
 *
 * Loads conditional option visibility JS when the block editor is active.
 * Only runs when GenerateBlocks is available.
 *
 * @since 1.4.0
 */
function bws_dynamic_tags_enqueue_editor_assets() {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}
	wp_enqueue_script(
		'bws-dynamic-tags-conditional-options',
		BWS_DYNAMIC_TAGS_URL . 'assets/js/editor-conditional-options.js',
		array( 'wp-hooks' ),
		BWS_DYNAMIC_TAGS_VERSION,
		true
	);
	wp_enqueue_script(
		'bws-dynamic-tags-image-controls',
		BWS_DYNAMIC_TAGS_URL . 'assets/js/image-tag-controls.js',
		array( 'wp-hooks', 'wp-element', 'wp-components' ),
		BWS_DYNAMIC_TAGS_VERSION,
		true
	);
	wp_enqueue_script(
		'bws-dynamic-tags-term-hop-control',
		BWS_DYNAMIC_TAGS_URL . 'assets/js/term-hop-control.js',
		array( 'wp-hooks', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-i18n' ),
		BWS_DYNAMIC_TAGS_VERSION,
		true
	);
	wp_enqueue_script(
		'bws-dynamic-tags-editor-preview',
		BWS_DYNAMIC_TAGS_URL . 'assets/js/editor-preview-context.js',
		array( 'wp-hooks' ),
		BWS_DYNAMIC_TAGS_VERSION,
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'bws_dynamic_tags_enqueue_editor_assets' );

// Initialize on plugins_loaded to ensure GenerateBlocks is available.
add_action( 'plugins_loaded', 'bws_dynamic_tags_init', 20 );

/**
 * Seed default settings on fresh activation.
 *
 * New installs default deprecated tag groups to 'disable' so legacy N×M tags
 * are removed from GB out of the box. Existing installs (option row already
 * present) are left untouched to avoid silently breaking live content.
 *
 * @since 1.6.1
 */
function bws_dynamic_tags_activate() {
	if ( null !== get_option( 'bws_dynamic_tags_settings', null ) ) {
		return;
	}
	add_option( 'bws_dynamic_tags_settings', array(
		'modifiers'   => array(
			'term' => true,
			'try'  => true,
		),
		'deprecated'  => array(
			'mode_with_path'    => 'disable',
			'mode_without_path' => 'disable',
		),
		'diagnostics' => array(
			'benchmark_logging'    => false,
			'registration_logging' => false,
		),
	) );
}
register_activation_hook( __FILE__, 'bws_dynamic_tags_activate' );
