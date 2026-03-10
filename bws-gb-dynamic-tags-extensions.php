<?php
/**
 * Plugin Name: GenerateBlocks Dynamic Tag Extensions by BWS
 * Description: Extends GenerateBlocks with custom dynamic tags for ACF integration, providing dynamic content from multiple post sources, date/time formatting, and taxonomy terms.
 * Version: 1.2.0
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
define( 'BWS_DYNAMIC_TAGS_VERSION', '1.2.0' );
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
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/date-helpers.php';
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
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/content-tags.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/image-tags.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/date-tags.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/datetime-tags.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/taxonomy-tags.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/deprecated-tags.php';

	// Register tag templates (order = GB editor display order within each gb_type group).
	bws_register_post_content_tag_templates();           // title, content, excerpt, permalink, description, custom_text
	bws_register_image_tag_templates();                  // featured_image, custom_image
	bws_register_date_tag_templates();                   // custom_date_single, custom_date_range
	bws_register_datetime_tag_templates();               // custom_datetime_single, custom_datetime_range
	bws_register_taxonomy_term_extraction_templates();   // term_title, term_permalink, term_description, term_custom_text, term_custom_image (post-context)

	// Generate all source × template combinations (direct + related variants).
	\BWS\DynamicTags\TagTemplateRegistry::generate_all_tags();

	// Generate try_ fallback-chain tags (Phase 7).
	\BWS\DynamicTags\TagTemplateRegistry::generate_try_tags();

	// Deprecated wrappers registered last (old tag names pointing to new core functions).
	bws_register_deprecated_tags();
}

// Initialize on plugins_loaded to ensure GenerateBlocks is available.
add_action( 'plugins_loaded', 'bws_dynamic_tags_init', 20 );
