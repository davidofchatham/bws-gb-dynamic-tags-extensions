<?php
/**
 * Plugin Name: GenerateBlocks Dynamic Tag Extensions by BWS
 * Plugin URI: https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions
 * Description: Extends GenerateBlocks Pro with advanced tags for both standard and meta/option field data, including date/time field formatting tags and first-available tags to try multiple sources/fields.
 * Version: 1.10.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: generateblocks-pro
 * Author: David Mitchell (Bridge Web Solutions) and Claude AI
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
define( 'BWS_DYNAMIC_TAGS_VERSION', '1.10.0' );
define( 'BWS_DYNAMIC_TAGS_FILE', __FILE__ );
define( 'BWS_DYNAMIC_TAGS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BWS_DYNAMIC_TAGS_URL', plugin_dir_url( __FILE__ ) );

// PSR-4 Autoloader for BWS\DynamicTags namespace.
require_once BWS_DYNAMIC_TAGS_PATH . 'autoload.php';

/**
 * Wire the GitHub-based plugin update checker.
 *
 * Vendored library (Plugin Update Checker 5.7); tracks tagged GitHub releases.
 * `enableReleaseAssets()` makes PUC download the .zip attached to each release
 * rather than the source zipball, so dev files (SPEC.md, tools/, etc.) stay out
 * of installed copies. Runs unconditionally — independent of GB dependency check.
 */
function bws_dynamic_tags_init_update_checker() {
	require_once BWS_DYNAMIC_TAGS_PATH . 'vendor/plugin-update-checker/load-v5p7.php';

	$update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/',
		BWS_DYNAMIC_TAGS_FILE,
		'bws-gb-dynamic-tags-extensions'
	);

	// Track GitHub Releases (not the latest commit/tag), and download the
	// attached release asset rather than the auto-generated source zip.
	$api = $update_checker->getVcsApi();
	if ( $api ) {
		$api->enableReleaseAssets();
	}
}
bws_dynamic_tags_init_update_checker();

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

	// GB constraint workaround filters.
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/hooks.php';

	// Load helper functions.
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/image-helpers.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/field-helpers.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/link-helpers.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/preview-helpers.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/content-helpers.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/datetime-helpers.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/taxonomy-helpers.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/registration-helpers.php';

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
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/email-tags.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/phone-tags.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/taxonomy-tags.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/deprecated-tags.php';

	// Register base (source-agnostic) tags: text, content, title, permalink, image, datetime_single, datetime_range.
	bws_register_base_tags();

	// Register the email base tag (unconditional; first-class base tag).
	bws_register_email_tag();

	// Register the phone base tag (unconditional; first-class base tag).
	bws_register_phone_tag();

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
		'bws-dynamic-tags-format-input-control',
		BWS_DYNAMIC_TAGS_URL . 'assets/js/format-input-control.js',
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
		'email'       => array(
			'obfuscate' => true,
		),
	) );
}
register_activation_hook( __FILE__, 'bws_dynamic_tags_activate' );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'bws_dynamic_tags_action_links' );

function bws_dynamic_tags_action_links( array $links ): array {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		admin_url( 'admin.php?page=bws-dynamic-tags' ),
		__( 'Settings', 'generateblocks' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
