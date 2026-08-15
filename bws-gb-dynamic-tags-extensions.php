<?php
/**
 * Plugin Name: GenerateBlocks Dynamic Tag Extensions by BWS
 * Plugin URI: https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions
 * Description: Extends GenerateBlocks Pro with advanced tags for both standard and meta/option field data, including date/time field formatting tags and first-available tags to try multiple sources/fields.
 * Version: 1.17.0
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
define( 'BWS_DYNAMIC_TAGS_VERSION', '1.17.0' );
define( 'BWS_DYNAMIC_TAGS_FILE', __FILE__ );
define( 'BWS_DYNAMIC_TAGS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BWS_DYNAMIC_TAGS_URL', plugin_dir_url( __FILE__ ) );

// PSR-4 Autoloader for BWS\DynamicTags namespace.
require_once BWS_DYNAMIC_TAGS_PATH . 'autoload.php';

// {{call}} function-passthrough support is loaded at TOP LEVEL (not inside the
// dependency-gated init, nor the init:20 tag pass) so the public registration API
// `bws_register_call_function()` exists as soon as the plugin file loads — before
// `init` fires. Site snippets register their functions on `init`, commonly at the
// default priority 10, which is EARLIER than the plugin's own init:20 tag pass;
// if the function were only defined in that pass, an init:10 caller would hit a
// "Call to undefined function" fatal. The file is pure function definitions with
// no load-time side effects (the GB tag is registered later, by an explicit
// bws_register_call_tag() call in the init pass), so eager loading is safe and
// cheap even when GB is absent. [1.12.0 load-order fix]
require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/fn-tags.php';

/**
 * Wire the GitHub-based plugin update checker.
 *
 * Vendored library (Plugin Update Checker 5.7); tracks tagged GitHub releases.
 * `enableReleaseAssets()` makes PUC download the .zip attached to each release
 * rather than the source zipball, so dev files (SPEC.md, tools/, etc.) stay out
 * of installed copies. Runs unconditionally — independent of GB dependency check.
 */
function bws_dynamic_tags_init_update_checker() {
	require_once BWS_DYNAMIC_TAGS_PATH . 'libs/plugin-update-checker/load-v5p7.php';

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
	// Traversal pipeline engine + source factory (L1-full) — must load before the
	// seam (field-helpers) and modifier registry that call bws_resolve_base_source /
	// bws_run_traversal. No load-time side effects (all WP touched inside functions).
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/traversal-pipeline.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/field-helpers.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/link-helpers.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/preview-helpers.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/content-helpers.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/datetime-helpers.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/taxonomy-helpers.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/join-helpers.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/registration-helpers.php';
	// FW-52 canonical serialization-order model (pure; PHP mirror of the editor-JS
	// normalizer's ordering algorithm — the harness-tested spec of the ordering contract).
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/serialization-order.php';
	// Folded slot-value grammar (FW-56/57) — THE single PHP owner of the wire.
	// Loads AFTER serialization-order.php: its emitter ranks tokens through
	// bws_serialization_order_sort() rather than carrying its own copy of KEY_MAP.
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/slot-fold.php';
	// Chain COMPILER (FW-56) — the wire's source chain → traversal engine steps, plus
	// the two step assemblers that used to live in field-helpers.php / base-shared.php.
	// Grouped with the grammar it compiles rather than with the engine it feeds; every
	// call is at render time, so its position relative to either is immaterial.
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/slot-fold-compile.php';
	// The `try_` slot ARM TABLE (FW-71, retires FW-5) — pure data keyed by the resolved
	// source kind bws_fold_chain_resolution() answers, so it loads after the compiler that
	// owns that vocabulary. Read at render only; position relative to the tag files is
	// immaterial.
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/try-slot-arms.php';
	// Fold MIGRATOR (legacy flat slot keys → folded values). Consumed by the
	// type:'option' migration entries in deprecated-tags.php; loads after the grammar
	// it adapts and after serialization-order.php (it canonicalizes emitted key order).
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/helpers/slot-fold-migrate.php';

	// Migration data + the public migration-registration API. Loaded HERE, at
	// plugins_loaded, rather than only in the init:20 pass that calls its registrars:
	// bws_register_modifier_root_migrations() is integration surface an owning plugin
	// calls from its own init hook, and a public bws_register_* function defined inside a
	// deferred init pass fatals every caller that runs before it (SPEC §V VC-load). The
	// file declares functions only — no load-time side effects — so loading it early costs
	// nothing and the init pass's require_once is a no-op.
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/deprecated-tags.php';

	// Field-discovery REST service (backs the bws-field-combo editor control).
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/rest/field-discovery.php';
	add_action( 'rest_api_init', 'bws_register_field_discovery_route' );

	// Dev/testing CLI commands (never part of shipped runtime). Registered on
	// cli_init so it lands after tags register at init:20.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		add_action( 'cli_init', 'bws_dynamic_tags_register_cli' );
	}

	// Initialize source registry (registers built-in sources and fires hook for external sources).
	\BWS\DynamicTags\SourceRegistry::init();

	// Initialize admin settings page.
	if ( is_admin() ) {
		\BWS\DynamicTags\Admin\SettingsPage::init();
	}

	// Register dynamic tags.
	add_action( 'init', 'bws_dynamic_tags_register_all', 20 );

	// Rebuild the deprecated/removed scan allowlist once per version change (a fresh
	// install has no stored version either, so it falls through this same check on its
	// first request). Priority 25 — after tag registration (20) so MigrationRegistry is
	// fully populated before scan().
	if ( get_option( 'bws_dynamic_tags_installed_version' ) !== BWS_DYNAMIC_TAGS_VERSION ) {
		add_action( 'init', 'bws_dynamic_tags_rebuild_allowlist_on_upgrade', 25 );
	}
}

/**
 * Register dev/testing WP-CLI commands.
 *
 * Loaded only under WP-CLI (guarded at the add_action site). The command files
 * self-guard on WP_CLI and register nothing shipped.
 */
function bws_dynamic_tags_register_cli() {
	// tools/ is excluded from distribution builds (.distignore), so the file is
	// absent in released packages — an unguarded require fatals WP-CLI bootstrap.
	$cli_cmd = BWS_DYNAMIC_TAGS_PATH . 'tools/cli/class-render-tag-command.php';
	if ( ! file_exists( $cli_cmd ) ) {
		return;
	}

	require_once $cli_cmd;
	WP_CLI::add_command( 'bws render-tag', 'BWS_Render_Tag_Command' );
}

/**
 * Rebuild the scan allowlist once after a version change, then record the new version.
 *
 * TagConverter::scan() walks all site content (a wpdb LIKE query plus per-post
 * regex), so it must never run inline on a frontend page load. This is gated to
 * admin, cron, and WP-CLI requests only: a frontend request that trips the version
 * check simply skips (leaving the version unbumped) so the hook re-arms cheaply on
 * later requests until an admin/cron/CLI request does the real rebuild. The stored
 * version is bumped only after the rebuild succeeds, so a request that errors
 * mid-rebuild retries next time instead of silently skipping the allowlist for the
 * rest of that version's life.
 *
 * ALSO RECONCILES THE GENERATEBLOCKS PRO PATTERN CACHE (#99), and this is the trigger that
 * matters most: a site converted by an EARLIER run has correct post content and a stale
 * cache, so there is no migration left for it to trigger on and scan() — which reads post
 * content only — cannot see it. This pass reaches it without anyone visiting a settings
 * screen. The admin/cron/CLI gate above is also what stands in for a capability check;
 * PatternCache deliberately carries none of its own (see its class docblock).
 *
 * @since 1.14.0
 * @since 1.17.0 Pattern-cache reconcile (#99).
 */
function bws_dynamic_tags_rebuild_allowlist_on_upgrade() {
	$is_cli = defined( 'WP_CLI' ) && WP_CLI;
	if ( ! is_admin() && ! wp_doing_cron() && ! $is_cli ) {
		return;
	}
	\BWS\DynamicTags\Admin\TagConverter::rebuild_allowlist();
	\BWS\DynamicTags\Admin\PatternCache::reconcile_site( 'upgrade' );
	update_option( 'bws_dynamic_tags_installed_version', BWS_DYNAMIC_TAGS_VERSION );
}

/**
 * Register all dynamic tags (respecting admin settings).
 *
 * Tag files are required here; their registration functions are called directly
 * rather than via add_action('init') to avoid re-hooking inside an init callback.
 */
function bws_dynamic_tags_register_all() {
	// Shared base-tag foundation (source/traversal options, source dispatch,
	// term-ambient read, option remap) — required FIRST so base-tags.php and the
	// other tag families can call its builders/wrappers. See includes/tags/base-shared.php.
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/base-shared.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/base-tags.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/content-tags.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/image-tags.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/datetime-tags.php'; // merged: includes date-only templates
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/email-tags.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/phone-tags.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/table-tags.php';
	// fn-tags.php is already loaded in bws_dynamic_tags_init() (plugins_loaded) so
	// bws_register_call_function() is available to early init callers; only the GB
	// tag registration (bws_register_call_tag) happens here in the init pass.
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/taxonomy-tags.php';
	require_once BWS_DYNAMIC_TAGS_PATH . 'includes/tags/deprecated-tags.php';

	// Register base (source-agnostic) tags: text, content, title, permalink, image, datetime_single, datetime_range.
	bws_register_base_tags();

	// Register the email base tag (unconditional; first-class base tag).
	bws_register_email_tag();

	// Register the phone base tag (unconditional; first-class base tag).
	bws_register_phone_tag();

	// Register the {{table}} structured-output tag — GATED OFF by default (1.17.0).
	//
	// The tag is a working PROTOTYPE, not v1: its row-set comes from a src chain, so its
	// authoring surface waits on the table container taking a chain source (FW-53), and
	// until then it registers the FLAT traversal options every other family has retired.
	// Registering it unconditionally would ship a half-built tag to every install by
	// default, which is the one thing FW-53's row said not to let happen.
	//
	// A FILTER rather than deleting the call: the fixture testbed turns it on from its
	// mu-plugin, so the table matrix rows and fixture blocks keep running while v1 is
	// built. Deleting the call would leave that work with no coverage until it returned.
	// Flips to unconditional (or default-true) when v1 ships.
	if ( apply_filters( 'bws_dynamic_tags_register_table_tag', false ) ) {
		bws_register_table_tag();
	}

	// Register the {{call}} function-passthrough tag (unconditional; ships with
	// an EMPTY allowlist — produces nothing until the site allowlists a function).
	bws_register_call_tag();

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
	// Depends on the fold GRAMMAR for one condition: `chain_fans` asks whether a
	// `src` value states a chain that hops, which needs the chain parser. The
	// predicate reads window.bwsSlotFold at call time (the filter runs long after
	// every script has loaded), so the dependency is stated for the reader's sake
	// rather than to fix an order — but an undeclared dependency is how the next
	// person removes the wrong enqueue.
	wp_enqueue_script(
		'bws-dynamic-tags-conditional-options',
		BWS_DYNAMIC_TAGS_URL . 'assets/js/editor-conditional-options.js',
		array( 'wp-hooks', 'bws-dynamic-tags-slot-fold-grammar' ),
		BWS_DYNAMIC_TAGS_VERSION,
		true
	);
	// VISUAL GROUPING for the separately-rendered option controls (source / field /
	// link). Loads before every control that draws a box, because they take the class
	// names off it — one declaration of what a group box looks like, whether it is drawn
	// around a folded slot's axes or around a base tag's own controls.
	wp_enqueue_script(
		'bws-dynamic-tags-option-group',
		BWS_DYNAMIC_TAGS_URL . 'assets/js/option-group.js',
		array( 'wp-hooks', 'wp-element' ),
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
	// as+size composite (FW-52): folds the image return-mode + size into one `as` token,
	// replacing GB's native `as` select AND image-size control. Size enum + pretty labels
	// are localized from PHP (respects the image_size_names_choose filter GB ignored).
	wp_enqueue_script(
		'bws-dynamic-tags-as-size-control',
		BWS_DYNAMIC_TAGS_URL . 'assets/js/as-size-control.js',
		array( 'wp-hooks', 'wp-element', 'wp-components', 'wp-i18n' ),
		BWS_DYNAMIC_TAGS_VERSION,
		true
	);
	if ( function_exists( 'bws_get_image_size_options' ) ) {
		wp_add_inline_script(
			'bws-dynamic-tags-as-size-control',
			'window.bwsImageSizes = ' . wp_json_encode( bws_get_image_size_options() ) . ';',
			'before'
		);
	}
	// FW-52 serialization-order normalizer: rebuilds extraTagParams in canonical
	// serialization order (format → source → link → fallback) so the saved tag string
	// stays readable regardless of the order the author touched the controls.
	//
	// The normalizer and the fold grammar depend on each OTHER, and only one direction
	// needs a load-order guarantee. The normalizer decodes folded slot KEYS through
	// window.bwsSlotFold.slotOrdinal, and it runs on every setState — an absent decoder
	// there would rank every slot as slot 0, i.e. silently wrong. So the grammar loads
	// FIRST. The reverse (the grammar ranking its tokens through window.bwsReorderKeys)
	// happens only when a slot is EMITTED, long after both files are up, and degrades
	// visibly to insertion order.
	wp_enqueue_script(
		'bws-dynamic-tags-order-normalizer',
		BWS_DYNAMIC_TAGS_URL . 'assets/js/serialization-order-normalizer.js',
		array( 'wp-hooks', 'wp-element', 'bws-dynamic-tags-slot-fold-grammar' ),
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
	wp_enqueue_script(
		'bws-dynamic-tags-field-combo-control',
		BWS_DYNAMIC_TAGS_URL . 'assets/js/field-combo-control.js',
		// No wp-data: the control reads the inlined window.bwsFieldEnvelope + sibling
		// src tokens, never the data store (no getCurrentPostType prefill in v1).
		array( 'wp-hooks', 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
		BWS_DYNAMIC_TAGS_VERSION,
		true
	);
	// Inline the field envelope directly into the editor page as a JS global. This
	// sidesteps any runtime REST call for /fields entirely — the control reads
	// window.bwsFieldEnvelope synchronously, so it never queues behind GB's
	// dynamic-tag-replacement swarm (which was the 30-40s head-of-line block). The
	// assembly is ~13ms and runs once per editor load, so the list stays current.
	// Guarded by function_exists so it no-ops if the discovery include is absent.
	if ( function_exists( 'bws_field_discovery_get_envelope_json' ) ) {
		wp_add_inline_script(
			'bws-dynamic-tags-field-combo-control',
			'window.bwsFieldEnvelope = ' . bws_field_discovery_get_envelope_json() . ';',
			'before'
		);
	}
	// Folded slot wire (FW-56/57). The GRAMMAR is the tested twin of
	// includes/helpers/slot-fold.php and carries no decisions of its own; the CONTROL
	// is the repeater that owns one folded slot value. The control must load after the
	// field-combo control (the repeater renders it for field pickers) — hence the
	// explicit script handles in its dependency list, not just the wp-* ones. The
	// grammar deliberately depends on NOTHING of ours: the order normalizer depends on
	// IT (see that enqueue for which way round the mutual dependency has to load).
	wp_enqueue_script(
		'bws-dynamic-tags-slot-fold-grammar',
		BWS_DYNAMIC_TAGS_URL . 'assets/js/slot-fold-grammar.js',
		array(),
		BWS_DYNAMIC_TAGS_VERSION,
		true
	);
	// The MOUNT migration path (FW-56/57, build step 5f): the twin of the converter's
	// pure migrate layer, plus the invisible control that applies it on tag-modal mount.
	// It reaches what the scanner cannot — a block widget's tag lives in the
	// `widget_block` option, not in post content. Loads BEFORE the control, which is a
	// hard dependency on it for the per-container legacy key surface.
	wp_enqueue_script(
		'bws-dynamic-tags-slot-fold-migrate',
		BWS_DYNAMIC_TAGS_URL . 'assets/js/slot-fold-migrate.js',
		array( 'wp-hooks', 'wp-element', 'bws-dynamic-tags-slot-fold-grammar' ),
		BWS_DYNAMIC_TAGS_VERSION,
		true
	);
	wp_enqueue_script(
		'bws-dynamic-tags-slot-fold-control',
		BWS_DYNAMIC_TAGS_URL . 'assets/js/slot-fold-control.js',
		array(
			'wp-hooks',
			'wp-element',
			'wp-components',
			'wp-i18n',
			'bws-dynamic-tags-slot-fold-grammar',
			'bws-dynamic-tags-slot-fold-migrate',
			'bws-dynamic-tags-field-combo-control',
			'bws-dynamic-tags-option-group',
		),
		BWS_DYNAMIC_TAGS_VERSION,
		true
	);
	// The BASE tag's source chain (FW-56). Loads after the slot-fold CONTROL because
	// it renders that control's extracted step editor (window.bwsSlotFoldRepeater) —
	// one component for both surfaces, so the base tag and a {{join}} slot cannot
	// come to offer two vocabularies for one chain.
	wp_enqueue_script(
		'bws-dynamic-tags-src-chain-control',
		BWS_DYNAMIC_TAGS_URL . 'assets/js/src-chain-control.js',
		array(
			'wp-hooks',
			'wp-element',
			'wp-components',
			'wp-i18n',
			'bws-dynamic-tags-slot-fold-grammar',
			'bws-dynamic-tags-slot-fold-control',
			'bws-dynamic-tags-option-group',
		),
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
