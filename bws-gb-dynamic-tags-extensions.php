<?php
/**
 * Plugin Name: GenerateBlocks Dynamic Tag Extensions by BWS
 * Description: Extends GenerateBlocks with custom dynamic tags for ACF integration, providing dynamic content from multiple post sources, date/time formatting, and taxonomy terms.
 * Version: 1.6.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Bridge Web Solutions
 * Text Domain: generateblocks
 * Domain Path: /languages
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

	// Register tag templates (order = GB editor display order within each gb_type group).
	bws_register_post_content_tag_templates();           // title, content, permalink, description, custom_text
	bws_register_image_tag_templates();                  // featured_image, custom_image
	bws_register_date_tag_templates();                   // custom_date_single, custom_date_range
	bws_register_datetime_tag_templates();               // custom_datetime_single, custom_datetime_range
	bws_register_taxonomy_term_extraction_templates();   // term_title, term_permalink, term_description, term_custom_text, term_custom_image (post-context)

	// Generate all source × template combinations (direct + related variants).
	\BWS\DynamicTags\TagTemplateRegistry::generate_all_tags();

	// Generate try_ fallback-chain tags from modifier templates (base-tag system, v1.6.0).
	// Runs BEFORE generate_try_tags() so that shared names (try_title, try_content, try_permalink,
	// try_datetime_single, try_datetime_range) are claimed by the new sN-via system. The N×M
	// generate_try_tags() call below then skips those names via dup-check and only registers
	// names that have no modifier-template equivalent (try_custom_text, try_custom_image, etc.).
	\BWS\DynamicTags\TagTemplateRegistry::generate_base_try_tags();

	// Generate try_ fallback-chain tags from N×M templates (legacy system, Phase 7).
	// Remaining N×M try_ names not yet registered above are generated here.
	\BWS\DynamicTags\TagTemplateRegistry::generate_try_tags();

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
}
add_action( 'enqueue_block_editor_assets', 'bws_dynamic_tags_enqueue_editor_assets' );

// Initialize on plugins_loaded to ensure GenerateBlocks is available.
add_action( 'plugins_loaded', 'bws_dynamic_tags_init', 20 );
