<?php
/**
 * Shared harness bootstrap for the SOURCE REGISTRY (#83) — chain roots.
 *
 * Three harnesses need a live registry and would otherwise each hand-roll one:
 * slot-options-build-test.php (does an opted-in source reach both enums?),
 * traversal-pipeline-test.php (does a source that has NOT opted in still resolve?) and
 * preview-label-test.php (does a rooted tag preview by label?). A per-harness copy of the
 * bootstrap is the drift this file removes — the point of all three is that ONE registry
 * feeds the editor and the renderer, so a second stub registry per harness would be a
 * second answer to the question under test.
 *
 * It loads the SHIPPED classes rather than porting them. The only fabrications are:
 *   - a minimal WP hook implementation, because the filter ROUTE cannot be exercised
 *     against an `apply_filters` that ignores its listeners;
 *   - a stub Admin\SettingsPage, the one WP-facing dependency register_source() has;
 *   - the fixture sources below, which stand in for an integrator's plugin.
 *
 * Require this BEFORE a harness's own shim block: every definition is guarded, and the
 * harnesses' `function_exists` loops then skip what is already real.
 *
 * @package BWS_Dynamic_Tags
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}

// ── A minimal hook implementation ────────────────────────────────────────────────────
//
// Priority-ordered, arguments passed through. Enough for the registry's one action and
// one filter; not a WP emulation. Defined only when the harness has not already shimmed
// them — but a harness testing the FILTER ROUTE must let these win, or its listener is
// registered into a void and the route silently reports "no roots declared".
$GLOBALS['bws_test_hooks'] = $GLOBALS['bws_test_hooks'] ?? array();

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) {
		$GLOBALS['bws_test_hooks'][ $hook ][ $priority ][] = $cb;
		return true;
	}
}
// Guarded INDIVIDUALLY, not as a block: a harness that shimmed only `apply_filters` (the
// preview harness does — an identity passthrough is production behaviour for the one
// filter it reads) must not trip a redeclare on the three it did not.
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		$extra = array_slice( func_get_args(), 2 );
		$all   = $GLOBALS['bws_test_hooks'][ $hook ] ?? array();
		ksort( $all );
		foreach ( $all as $callbacks ) {
			foreach ( $callbacks as $cb ) {
				$value = call_user_func_array( $cb, array_merge( array( $value ), $extra ) );
			}
		}
		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $cb, $priority = 10, $args = 1 ) {
		return add_filter( $hook, $cb, $priority, $args );
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook ) {
		$extra = array_slice( func_get_args(), 1 );
		$all   = $GLOBALS['bws_test_hooks'][ $hook ] ?? array();
		ksort( $all );
		foreach ( $all as $callbacks ) {
			foreach ( $callbacks as $cb ) {
				call_user_func_array( $cb, $extra );
			}
		}
	}
}

// ── The registry's one WP-facing dependency ──────────────────────────────────────────
//
// register_source() logs through it, and is_source_enabled() asks it whether the term_
// modifier is switched on — the second gate on an offered root. Declared through eval so
// this file can stay in the global namespace like every other harness.
if ( ! class_exists( '\BWS\DynamicTags\Admin\SettingsPage' ) ) {
	eval( 'namespace BWS\DynamicTags\Admin; class SettingsPage {
		public static $modifiers_enabled = true;
		public static function is_registration_logging_enabled() { return false; }
		public static function is_modifier_enabled( $key ) { return self::$modifiers_enabled; }
	}' );
}

require_once __DIR__ . '/../../includes/classes/class-source-interface.php';
require_once __DIR__ . '/../../includes/classes/class-abstract-source.php';
require_once __DIR__ . '/../../includes/classes/class-source-registry.php';
foreach ( glob( __DIR__ . '/../../includes/classes/sources/*.php' ) as $bws_source_file ) {
	require_once $bws_source_file;
}

// ── Fixture sources — an integrator's plugin, standing in ────────────────────────────

/**
 * A source that OPTS IN. Resolves deterministically (no ambient request state), which is
 * what makes it assertable: the real external source resolves from a cookie or a query
 * param and could not carry this seam's coverage.
 */
class BWS_Test_Offered_Source extends \BWS\DynamicTags\AbstractSource {
	public function get_source_key(): string { return 'testroot'; }
	public function get_source_label(): string { return 'Test Root'; }
	public function is_selectable_root(): bool { return true; }
	public function resolve_id( array $options, $instance ) { return 4242; }
	public function get_source_options(): array { return array(); }
}

/**
 * A registered source that does NOT opt in — the load-bearing negative. It must never
 * appear in an enum, and must still RESOLVE when named in wire (offering is not
 * resolving; ADR 0004 makes the wire hand-editable and a migration puts these tokens in
 * content the moment it runs).
 */
class BWS_Test_Unoffered_Source extends \BWS\DynamicTags\AbstractSource {
	public function get_source_key(): string { return 'quietsource'; }
	public function get_source_label(): string { return 'Quiet Source'; }
	public function resolve_id( array $options, $instance ) { return 777; }
	public function get_source_options(): array { return array(); }
}

/**
 * A TERM-context opted-in root, for the settings gate: it is offered while the term_
 * modifier toggle is on and disappears with it, exactly as every other term surface does.
 */
class BWS_Test_Term_Root_Source extends \BWS\DynamicTags\AbstractSource {
	public function get_source_key(): string { return 'testtermroot'; }
	public function get_source_label(): string { return 'Test Term Root'; }
	public function get_context_type(): string { return 'term'; }
	public function is_selectable_root(): bool { return true; }
	public function resolve_id( array $options, $instance ) { return 99; }
	public function get_source_options(): array { return array(); }
}
