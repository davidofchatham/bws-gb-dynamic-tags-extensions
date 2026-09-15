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
// register_source() logs through it. `$modifiers_enabled` is still driveable because the
// term_ toggle's NON-effect on an offered root is itself pinned (1.20.0, FW-39/D39) —
// nothing in the registry reads it any more. Declared through eval so this file can stay
// in the global namespace like every other harness.
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
 * A registered source that RESOLVES NOTHING HERE — the #76 category-2 fixture.
 *
 * Not a broken source and not an unregistered token: it is what a correctly-written
 * scope-bound source does off its scope. The measured population is a View-scoped source
 * on a page outside its View; the source runs, finds no entity, and has nothing to hand
 * back. Standing in for it deterministically is the point — the real one answers from
 * request state a pure harness has no way to set.
 */
class BWS_Test_Absent_Source extends \BWS\DynamicTags\AbstractSource {
	public function get_source_key(): string { return 'absentroot'; }
	public function get_source_label(): string { return 'Absent Root'; }
	public function is_selectable_root(): bool { return true; }
	public function resolve_id( array $options, $instance ) { return false; }
	public function get_source_options(): array { return array(); }
}

/**
 * A TERM-context opted-in root. Its context type used to decide whether it was offered;
 * since 1.20.0 it does not, and the fixture exists to hold that line — offered with the
 * term_ modifier toggle either way.
 */
class BWS_Test_Term_Root_Source extends \BWS\DynamicTags\AbstractSource {
	public function get_source_key(): string { return 'testtermroot'; }
	public function get_source_label(): string { return 'Test Term Root'; }
	public function get_context_type(): string { return 'term'; }
	public function is_selectable_root(): bool { return true; }
	public function resolve_id( array $options, $instance ) { return 99; }
	public function get_source_options(): array { return array(); }
}

/**
 * A PINNING root — it declares a root argument (FW-39).
 *
 * The declaration is what the seam carries; nothing here resolves off the argument yet,
 * which is the ticket's own boundary (no root in the plugin offers one). Its `argless`
 * is OMITTED deliberately, so the normalizer's default (refuse) is exercised by absence
 * rather than by a value that happens to match it.
 */
class BWS_Test_Pinned_Root_Source extends \BWS\DynamicTags\AbstractSource {
	public function get_source_key(): string { return 'pinroot'; }
	public function get_source_label(): string { return 'Pinned Root'; }
	public function is_selectable_root(): bool { return true; }
	public function get_root_argument(): array {
		return array( 'label' => 'Which One', 'control' => 'bws-test-picker' );
	}
	public function resolve_id( array $options, $instance ) { return 4242; }
	public function get_source_options(): array { return array(); }
}

/**
 * A PINNING root that actually RESOLVES its argument — a TERM-context stand-in for
 * `term,<ID>` that never calls out to WordPress (FW-39, D3/D8).
 *
 * The shipped TaxonomyTerm resolves through `get_term()`, and per this ticket's Testing
 * Decisions that live resolution rides testbed matrix rows, not a pure harness. What IS
 * pure is step admission off a pinned root's KIND — traversal-pipeline-test.php's D3/D8
 * coverage needs a term-kind pinning root that resolves deterministically without WP, so
 * this stands in for TaxonomyTerm the same way the fixtures above stand in for an
 * integrator's plugin.
 *
 * A NUMERIC argument resolves (id = the argument, doubled, so a wrong id is easy to spot
 * in a failing assertion); anything else refuses, matching D8's terminal rule that a pin
 * naming nothing does not fall back to resolve_id().
 */
class BWS_Test_Pinned_Term_Source extends \BWS\DynamicTags\AbstractSource {
	public function get_source_key(): string { return 'pinnedterm'; }
	public function get_source_label(): string { return 'Pinned Term'; }
	public function get_context_type(): string { return 'term'; }
	public function is_selectable_root(): bool { return true; }
	public function get_root_argument(): array {
		return array( 'label' => 'Term', 'control' => 'bws-test-picker' );
	}
	public function resolve_root_argument( string $arg, array $options, $instance ) {
		return is_numeric( $arg ) ? (int) $arg * 2 : false;
	}
	// TRUTHY, deliberately — not `false`. The point of the D8 refusal is that it never
	// REACHES this method for an argless declaring root, whatever it would have
	// answered; a fixture whose ambient path always fails could not tell "refused before
	// asking" apart from "asked and got nothing", which is exactly the shape a query
	// loop's own term item takes (FW-39, discovered building fold-test-matrix.md §F20 —
	// an explicit, argless `src:term` used to reach TaxonomyTerm::resolve_id() and
	// correctly read a loop's term; D8 now refuses before it is ever called).
	public function resolve_id( array $options, $instance ) { return 555; }
	public function get_source_options(): array { return array(); }
}

/**
 * A root that answers a bare token BY ITS OWN RULE — the second argless policy.
 *
 * Stands in for a sister plugin's Staff Roster `roster`, which ships argless today and gains
 * an argument later. It is not a licence to fall back to the ambient entity; it means the
 * SOURCE decides, which is why the policy is stated rather than inferred.
 */
class BWS_Test_Owner_Resolves_Root_Source extends \BWS\DynamicTags\AbstractSource {
	public function get_source_key(): string { return 'ownerroot'; }
	public function get_source_label(): string { return 'Owner Resolves Root'; }
	public function is_selectable_root(): bool { return true; }
	public function get_root_argument(): array {
		return array(
			'label'   => 'Dimension',
			'control' => 'bws-test-roster-picker',
			'argless' => \BWS\DynamicTags\SourceInterface::ROOT_ARGLESS_OWNER_RESOLVES,
		);
	}
	public function resolve_id( array $options, $instance ) { return 11; }
	public function get_source_options(): array { return array(); }
}

/**
 * A root whose declaration NAMES NO CONTROL — the load-bearing malformed case.
 *
 * It must still be an offered root, with no argument. Dropping the row entirely would
 * retire a working source over a bad optional declaration; keeping the argument would
 * paint a picker with no control behind it, leaving a root whose only behaviour is
 * refusing.
 */
class BWS_Test_Half_Declared_Root_Source extends \BWS\DynamicTags\AbstractSource {
	public function get_source_key(): string { return 'halfroot'; }
	public function get_source_label(): string { return 'Half Declared Root'; }
	public function is_selectable_root(): bool { return true; }
	public function get_root_argument(): array {
		return array( 'label' => 'Which One' );
	}
	public function resolve_id( array $options, $instance ) { return 12; }
	public function get_source_options(): array { return array(); }
}

/**
 * A root whose declaration is the WRONG SHAPE — an array where a string belongs, and an
 * object with no `__toString` beside it.
 *
 * The gate reads an integrator's array, so this is a shape to expect rather than one to
 * rule out. Cast without checking, the array passes as the literal "Array" and paints a
 * picker captioned that; the object throws an uncaught Error and takes the option build
 * down for every base tag and every folded slot on the site.
 */
class BWS_Test_Nonscalar_Arg_Root_Source extends \BWS\DynamicTags\AbstractSource {
	public function get_source_key(): string { return 'nonscalarroot'; }
	public function get_source_label(): string { return 'Nonscalar Arg Root'; }
	public function is_selectable_root(): bool { return true; }
	public function get_root_argument(): array {
		return array( 'label' => array( 'Which One' ), 'control' => new \stdClass() );
	}
	public function resolve_id( array $options, $instance ) { return 13; }
	public function get_source_options(): array { return array(); }
}

/**
 * A declaration whose ARGLESS POLICY is the wrong shape. The two axes are independent:
 * a broken policy must not delete the argument the author is being asked to fill, so it
 * lands on the conservative value exactly as an unrecognized string does.
 */
class BWS_Test_Nonscalar_Policy_Root_Source extends \BWS\DynamicTags\AbstractSource {
	public function get_source_key(): string { return 'badpolicyroot'; }
	public function get_source_label(): string { return 'Bad Policy Root'; }
	public function is_selectable_root(): bool { return true; }
	public function get_root_argument(): array {
		return array( 'label' => 'Which One', 'control' => 'bws-test-picker', 'argless' => array( 'owner-resolves' ) );
	}
	public function resolve_id( array $options, $instance ) { return 14; }
	public function get_source_options(): array { return array(); }
}
