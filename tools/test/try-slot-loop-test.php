<?php
/**
 * Standalone unit harness for bws_try_run_attempts() — the `try_` ATTEMPT WALK
 * (includes/helpers/try-slot-loop.php).
 *
 * The real function is loaded, not copied (house pattern: limit-clamp-test.php,
 * slot-fold-test.php, read-bounded-sources-test.php). It is reachable with no
 * WordPress at all for exactly one reason — the per-attempt read arrives as a
 * CALLABLE rather than as a name the walk switches on (FW-107's fix shape, which
 * FW-136 lifted the walk into). A harness for the pre-1.21.0 closure could not
 * have existed: the walk and nine families' render arms were one anonymous
 * function inside a GB registration call.
 *
 * WHAT IS PINNED. The resolver is a RECORDER — it returns scripted values and
 * keeps every option set it was handed — so the walk's decisions are read off the
 * call list rather than inferred from a rendered string. Rows assert whole
 * structures, never a count reduced from one.
 *
 *   §L1 — FIRST NON-EMPTY WINS, and the walk stops there. Attempts past the
 *         winner are never resolved (asserted on the call list, invisible in the
 *         return). '' is the ONLY empty; a stored '0' is a real value and stops
 *         the walk.
 *   §L2 — THE PER-SLOT READ GATE. On a family that reads a field key per
 *         attempt, an attempt with no key and no no-key `use` is skipped BEFORE
 *         the resolver runs. A family with no per-slot read axis has no gate.
 *   §L3 — THE CARRY HAND-OFF. One accumulator, threaded through
 *         bws_fold_slot_chain_options() — `src(same)` carries the prior attempt's
 *         whole chain, an absent read carries the prior key, and slot 1 seeds
 *         from the family's own stripped `use` default. §L3.5 is the subtle one:
 *         the READ GATE fires after the seam has already taken its carry, so an
 *         attempt the gate skips still feeds the accumulator.
 *   §L4 — THE BOUND, resolved by the walk and written back EXPLICITLY, because
 *         the resolver re-derives its own default off chain wire and would
 *         answer unlimited for a slot that has always bounded at 1. A collapsing
 *         family forces 1 over anything the wire says (ADR 0007).
 *   §L5 — THE HAND-OFF SHAPE. Fallback keys never reach an attempt (they are the
 *         shell's, fired once on an all-empty walk); the winning triple comes back
 *         verbatim for the shell to link-wrap.
 *
 *   §L8 — PER-SLOT DATETIME READS (`try_datetime`, FW-193). A template that sets
 *         `try_per_slot_reads` gives each attempt its own `use`/`key`, read whole
 *         off the slot (carry-over included) and replacing any tag-level pair.
 *
 * The walk's own PHPDoc is the axis owner for every section. These rows check its
 * observable consequences.
 *
 * Run:  php tools/test/try-slot-loop-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

require __DIR__ . '/../../includes/helpers/serialization-order.php';
require __DIR__ . '/../../includes/helpers/slot-fold.php';
require __DIR__ . '/../../includes/helpers/slot-fold-compile.php';

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post { public $ID; public function __construct( $id ) { $this->ID = $id; } }
}
if ( ! class_exists( 'WP_Term' ) ) {
	class WP_Term { public $term_id; public function __construct( $id ) { $this->term_id = $id; } }
}
if ( ! function_exists( 'bws_extract_post_id' ) ) {
	function bws_extract_post_id( $post_data ) {
		if ( is_numeric( $post_data ) ) { return intval( $post_data ); }
		if ( $post_data instanceof WP_Post ) { return $post_data->ID; }
		if ( is_array( $post_data ) && isset( $post_data['ID'] ) ) { return $post_data['ID']; }
		return false;
	}
}
// bws_clamp_limit — the single limit INTERPRETER. §L4 compares what each slot
// RESOLVES to, so the harness must clamp exactly as the walk does; re-inlining the
// rule here is what the extraction removed.
require __DIR__ . '/../../includes/helpers/field-helpers.php';
// THE ENGINE'S INPUT-KIND LIST, because the seam's `same` merge derives from it
// (same requirement, same reason, as slot-fold-test.php's).
require __DIR__ . '/../../includes/helpers/traversal-pipeline.php';
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
}

if ( ! function_exists( 'wp_validate_boolean' ) ) {
	function wp_validate_boolean( $value ) {
		return is_string( $value ) && 'false' === strtolower( $value ) ? false : (bool) $value;
	}
}
// bws_normalize_datetime_options(): the real mode/read parse behind §L8's stand-in seam.
require __DIR__ . '/../../includes/helpers/registration-helpers.php';
require __DIR__ . '/../../includes/helpers/datetime-helpers.php';
require __DIR__ . '/../../includes/tags/datetime-tags.php';

require __DIR__ . '/../../includes/helpers/try-slot-loop.php';

$fails = 0;

/**
 * Structural equality assertion.
 *
 * @param string $label    Row label.
 * @param mixed  $expected Expected structure.
 * @param mixed  $actual   Actual structure.
 */
function eq( string $label, $expected, $actual ): void {
	global $fails;
	if ( $expected === $actual ) {
		echo "PASS  {$label}\n";
		return;
	}
	$fails++;
	echo "FAIL  {$label}\n";
	echo '      expected: ' . var_export( $expected, true ) . "\n";
	echo '      actual:   ' . var_export( $actual, true ) . "\n";
}

/** An exhausted walk that read no single entity: the fallback, if any, prints plain. */
const EXHAUSTED = array( 'value' => '', 'link_id' => 0, 'link_type' => 'post' );

// ── Fixtures ────────────────────────────────────────────────────────────────

/**
 * The two family shapes the walk branches on. `text` reads a key per attempt;
 * `title` has no per-slot read axis at all, so its gate never fires.
 */
const CFG_KEYED   = array(
	'per_slot_key' => true,
	'per_slot_use' => true,
	'no_key_uses'  => array( 'title' ),
	'default_use'  => 'key',
	'collapse'     => false,
);
const CFG_NO_READ = array(
	'per_slot_key' => false,
	'per_slot_use' => false,
	'no_key_uses'  => array(),
	'default_use'  => '',
	'collapse'     => false,
);

/**
 * A recorder standing in for a family's resolve seam.
 *
 * @param array $script  Per-call returns, in call order. A string is a bare value;
 *                       an array is a whole triple.
 * @param array $calls   OUT — every $slot_opts the walk handed over, in order.
 * @return callable
 */
function recorder( array $script, array &$calls ): callable {
	return function ( array $slot_opts, $inst ) use ( $script, &$calls ) {
		$i       = count( $calls );
		$calls[] = $slot_opts;
		$out     = $script[ $i ] ?? '';
		return is_array( $out )
			? $out
			: array( 'value' => (string) $out, 'link_id' => 0, 'link_type' => 'post' );
	};
}

/** Wire helper: folded slot values keyed `A`..`E`. */
function slots( ...$values ): array {
	$out  = array();
	$keys = array( 'A', 'B', 'C', 'D', 'E' );
	foreach ( $values as $i => $v ) {
		$out[ $keys[ $i ] ] = $v;
	}
	return $out;
}

/** The `src` wire each recorded attempt resolved with. */
function srcs( array $calls ): array {
	return array_map( static fn( $o ) => $o['src'], $calls );
}

/** The `key`/`use`/`limit` each recorded attempt resolved with. */
function reads( array $calls ): array {
	return array_map(
		static fn( $o ) => array( $o['use'] ?? null, $o['key'] ?? null, $o['limit'] ?? null ),
		$calls
	);
}

// ── §L1 — first non-empty wins, and the walk stops there ────────────────────

$calls = array();
eq(
	'L1.1 first attempt empty, second reads → the SECOND value wins',
	array( 'value' => 'beta', 'link_id' => 0, 'link_type' => 'post' ),
	bws_try_run_attempts(
		slots( 'key(one)', 'key(two)', 'key(three)' ),
		null,
		CFG_KEYED,
		recorder( array( '', 'beta', 'gamma' ), $calls )
	)
);
eq( 'L1.1 exactly two attempts were resolved — the third never ran', array( 'one', 'two' ), array_column( $calls, 'key' ) );

$calls = array();
eq(
	'L1.2 first attempt reads → it wins',
	array( 'value' => 'alpha', 'link_id' => 0, 'link_type' => 'post' ),
	bws_try_run_attempts( slots( 'key(one)', 'key(two)' ), null, CFG_KEYED, recorder( array( 'alpha', 'beta' ), $calls ) )
);
eq( 'L1.2 the walk stopped at one resolve', array( 'one' ), array_column( $calls, 'key' ) );

$calls = array();
eq(
	'L1.3 a stored "0" is a VALUE and stops the walk (no emptiness re-decided here)',
	array( 'value' => '0', 'link_id' => 0, 'link_type' => 'post' ),
	bws_try_run_attempts( slots( 'key(one)', 'key(two)' ), null, CFG_KEYED, recorder( array( '0', 'beta' ), $calls ) )
);
eq( 'L1.3 the second attempt never ran', array( 'one' ), array_column( $calls, 'key' ) );

$calls = array();
eq(
	'L1.4 every attempt empty → the EXHAUSTED triple (the shell then runs the fallback or the label)',
	EXHAUSTED,
	bws_try_run_attempts( slots( 'key(one)', 'key(two)' ), null, CFG_KEYED, recorder( array( '', '' ), $calls ) )
);
eq( 'L1.4 both attempts were resolved before the walk gave up', array( 'one', 'two' ), array_column( $calls, 'key' ) );

$calls = array();
eq(
	'L1.5 no attempts configured at all → EXHAUSTED, resolver never called',
	EXHAUSTED,
	bws_try_run_attempts( array(), null, CFG_KEYED, recorder( array( 'alpha' ), $calls ) )
);
eq( 'L1.5 resolver never called', array(), $calls );

// ── §L2 — the per-slot read gate ────────────────────────────────────────────

$calls = array();
eq(
	'L2.1 keyed family: an attempt with a source but NO key is skipped before the resolver',
	array( 'value' => 'beta', 'link_id' => 0, 'link_type' => 'post' ),
	bws_try_run_attempts(
		slots( 'src(terms,category)', 'key(two)' ),
		null,
		CFG_KEYED,
		recorder( array( 'beta' ), $calls )
	)
);
eq( 'L2.1 only the keyed attempt reached the resolver', array( 'two' ), array_column( $calls, 'key' ) );

$calls = array();
eq(
	'L2.2 a no-key `use` value resolves WITHOUT a key',
	array( 'value' => 'alpha', 'link_id' => 0, 'link_type' => 'post' ),
	bws_try_run_attempts( slots( 'use(title)' ), null, CFG_KEYED, recorder( array( 'alpha' ), $calls ) )
);
eq( 'L2.2 the attempt carried use:title and no key at all', array( array( 'title', null, '1' ) ), reads( $calls ) );

$calls = array();
eq(
	'L2.3 a family with NO per-slot read axis has no gate — a keyless attempt still resolves',
	array( 'value' => 'alpha', 'link_id' => 0, 'link_type' => 'post' ),
	bws_try_run_attempts( slots( 'src(terms,category)' ), null, CFG_NO_READ, recorder( array( 'alpha' ), $calls ) )
);
eq( 'L2.3 the walk wrote neither use nor key onto it', array( array( null, null, '0' ) ), reads( $calls ) );

// ── §L3 — the carry hand-off ────────────────────────────────────────────────

$calls = array();
bws_try_run_attempts(
	slots( 'src(refs,office);key(one)', 'src(same);key(two)' ),
	null,
	CFG_KEYED,
	recorder( array( '', '' ), $calls )
);
eq(
	'L3.1 `src(same)` carries the prior attempt\'s WHOLE chain, not a root token',
	array( 'refs,office', 'refs,office' ),
	srcs( $calls )
);

$calls = array();
bws_try_run_attempts(
	slots( 'src(refs,office);key(one)', 'src(terms,category)' ),
	null,
	CFG_KEYED,
	recorder( array( '', '' ), $calls )
);
eq(
	'L3.2 an attempt stating no read carries the prior key (a selecting container)',
	array( array( 'key', 'one', '0' ), array( 'key', 'one', '0' ) ),
	reads( $calls )
);

$calls = array();
bws_try_run_attempts(
	slots( 'key(one)', 'src(terms,category)', 'src(refs,office);use(same)' ),
	null,
	CFG_KEYED,
	recorder( array( '', '', '' ), $calls )
);
eq(
	'L3.3 `use(same)` reaches back past an attempt that stated no read of its own',
	array( 'one', 'one', 'one' ),
	array_column( $calls, 'key' )
);

$calls = array();
bws_try_run_attempts(
	slots( 'src(terms,category)' ),
	null,
	array( 'per_slot_key' => true, 'per_slot_use' => true, 'no_key_uses' => array( 'title' ), 'default_use' => 'title', 'collapse' => false ),
	recorder( array( '' ), $calls )
);
eq(
	'L3.4 slot 1 seeds the accumulator from the family\'s own default `use`',
	array( array( 'title', null, '0' ) ),
	reads( $calls )
);

$calls = array();
bws_try_run_attempts(
	slots( 'src(refs,office)', 'src(same);key(two)' ),
	null,
	CFG_KEYED,
	recorder( array( '' ), $calls )
);
eq(
	'L3.5 THE GATE FIRES AFTER THE SEAM TOOK ITS CARRY — a skipped attempt still fed the chain',
	array( 'refs,office' ),
	srcs( $calls )
);

// ── §L4 — the bound, resolved by the walk and written back explicitly ───────

$calls = array();
bws_try_run_attempts( slots( 'key(one)' ), null, CFG_KEYED, recorder( array( '' ), $calls ) );
eq( 'L4.1 a non-fanning attempt bounds at 1', array( array( 'key', 'one', '1' ) ), reads( $calls ) );

$calls = array();
bws_try_run_attempts( slots( 'src(terms,category);key(one)' ), null, CFG_KEYED, recorder( array( '' ), $calls ) );
eq( 'L4.2 a FANNING chain takes the unlimited default', array( array( 'key', 'one', '0' ) ), reads( $calls ) );

$calls = array();
bws_try_run_attempts( slots( 'src(terms,category,limit[3]);key(one)' ), null, CFG_KEYED, recorder( array( '' ), $calls ) );
eq( 'L4.3 a slot-stated limit governs its own attempt', array( array( 'key', 'one', '3' ) ), reads( $calls ) );

$calls = array();
bws_try_run_attempts(
	slots( 'src(terms,category,limit[3]);key(one)' ),
	null,
	array( 'per_slot_key' => true, 'per_slot_use' => true, 'no_key_uses' => array(), 'default_use' => 'key', 'collapse' => true ),
	recorder( array( '' ), $calls )
);
eq( 'L4.4 a COLLAPSING family forces 1 over anything the wire says (ADR 0007)', array( array( 'key', 'one', '1' ) ), reads( $calls ) );

$calls = array();
bws_try_run_attempts(
	slots( 'src(terms,category);key(one)' ) + array( 'limit' => '4' ),
	null,
	CFG_KEYED,
	recorder( array( '' ), $calls )
);
eq( 'L4.5 the RETIRED tag-level `limit` is still read for wire that still carries one (#61/#62)', array( array( 'key', 'one', '4' ) ), reads( $calls ) );

// ── §L5 — the hand-off shape ────────────────────────────────────────────────

$calls = array();
bws_try_run_attempts(
	slots( 'key(one)' ) + array( 'fallback' => 'nope', 'fallback_text' => 'also nope', 'linkTo' => 'post', 'sep' => ' / ' ),
	null,
	CFG_KEYED,
	recorder( array( '' ), $calls )
);
eq(
	// The slot keys themselves ride along untouched, as every other tag-level option
	// does — only the source/read/limit axes are superseded. Stripping them would be a
	// second rule about what an attempt may see, with nothing asking for one.
	'L5.1 the FALLBACK keys never reach an attempt; everything else does',
	array( 'A' => 'key(one)', 'linkTo' => 'post', 'sep' => ' / ', 'src' => '', 'ref' => '', 'srcTermIn' => '', 'key' => 'one', 'use' => 'key', 'limit' => '1' ),
	$calls[0]
);

$calls = array();
eq(
	'L5.2 the winning triple comes back verbatim for the shell to link-wrap',
	array( 'value' => 'alpha', 'link_id' => 42, 'link_type' => 'term' ),
	bws_try_run_attempts(
		slots( 'key(one)' ),
		null,
		CFG_KEYED,
		recorder( array( array( 'value' => 'alpha', 'link_id' => 42, 'link_type' => 'term' ) ), $calls )
	)
);

$calls = array();
eq(
	'L5.3 a resolver returning a bare value takes link_id 0 / link_type post',
	array( 'value' => 'alpha', 'link_id' => 0, 'link_type' => 'post' ),
	bws_try_run_attempts(
		slots( 'key(one)' ),
		null,
		CFG_KEYED,
		recorder( array( array( 'value' => 'alpha' ) ), $calls )
	)
);

// ── §L6 — the fixed read (FW-141 03) ────────────────────────────────────────
// The fixed text itself is the resolver's (the dispatchers, ticket 01); the walk's
// part is to let a keyless fixed attempt through its gate and hand the text over.

const CFG_KEYED_FIXED = array(
	'per_slot_key' => true,
	'per_slot_use' => true,
	'no_key_uses'  => array( 'title', 'fixed' ),
	'default_use'  => 'key',
	'collapse'     => false,
);

$calls = array();
eq(
	'L6.1 a fixed attempt resolves WITHOUT a key, carrying its text',
	array( 'value' => 'Varsity', 'link_id' => 0, 'link_type' => 'post' ),
	bws_try_run_attempts( slots( 'fixed(Varsity)' ), null, CFG_KEYED_FIXED, recorder( array( 'Varsity' ), $calls ) )
);
eq( 'L6.1 use:fixed, no key, the text in `fixed`', array( array( 'fixed', null, '1' ) ), reads( $calls ) );
eq( 'L6.1 the text reached the resolver unescaped', 'Varsity', $calls[0]['fixed'] ?? null );

$calls = array();
eq(
	'L6.2 a field attempt WITH a value wins over a later fixed attempt',
	array( 'value' => 'alpha', 'link_id' => 0, 'link_type' => 'post' ),
	bws_try_run_attempts( slots( 'key(one)', 'fixed(Varsity)' ), null, CFG_KEYED_FIXED, recorder( array( 'alpha', 'Varsity' ), $calls ) )
);
eq( 'L6.2 the fixed attempt never ran', 1, count( $calls ) );

$calls = array();
eq(
	'L6.3 an EMPTY field attempt falls through to the fixed attempt',
	array( 'value' => 'Varsity', 'link_id' => 0, 'link_type' => 'post' ),
	bws_try_run_attempts( slots( 'key(one)', 'fixed(Varsity)' ), null, CFG_KEYED_FIXED, recorder( array( '', 'Varsity' ), $calls ) )
);
eq( 'L6.3 the field attempt carried no fixed text', false, isset( $calls[0]['fixed'] ) );

$calls = array();
eq(
	'L6.4 a fixed attempt whose chain resolves nothing (resolver empty) falls through',
	array( 'value' => 'beta', 'link_id' => 0, 'link_type' => 'post' ),
	bws_try_run_attempts( slots( 'src(refs,missing_rel);fixed(Varsity)', 'key(two)' ), null, CFG_KEYED_FIXED, recorder( array( '', 'beta' ), $calls ) )
);

$calls = array();
eq(
	'L6.5 `use(fixed)` with no text entered still reaches the resolver (which reads nothing)',
	EXHAUSTED,
	bws_try_run_attempts( slots( 'use(fixed)' ), null, CFG_KEYED_FIXED, recorder( array( '' ), $calls ) )
);
eq( 'L6.5 its fixed text is empty', '', $calls[0]['fixed'] ?? null );

$calls = array();
bws_try_run_attempts( slots( 'fixed(Varsity)', 'src(terms,category)' ), null, CFG_KEYED_FIXED, recorder( array( '', '' ), $calls ) );
eq( 'L6.6 an attempt stating no read carries the prior fixed read and its text', array( 'Varsity', 'Varsity' ), array_column( $calls, 'fixed' ) );

$calls = array();
bws_try_run_attempts( slots( 'fixed(Varsity)', 'src(refs,office);use(same)' ), null, CFG_KEYED_FIXED, recorder( array( '', '' ), $calls ) );
eq( 'L6.6b `use(same)` reaches back to a fixed attempt and takes its text', array( 'Varsity', 'Varsity' ), array_column( $calls, 'fixed' ) );

$calls = array();
bws_try_run_attempts( slots( 'use(title);fixed(Varsity)' ), null, CFG_KEYED_FIXED, recorder( array( '' ), $calls ) );
eq( 'L6.7 an explicit `use` wins over a stale fixed token (D2), which never reaches the resolver', array( array( 'title', null, '1' ), false ), array( reads( $calls )[0], isset( $calls[0]['fixed'] ) ) );

// ── §L7 — unmigrated flat wire is read under the era it was authored in (FW-141 05) ──
// try_email / try_phone gained a per-slot `use` after their flat wire shipped without one, so
// a legacy `2-key` is a key read there, where the live rule would discard it.

$flat = array( 'key' => 'unused_line', '2-key' => 'main_line' );
$calls = array();
bws_try_run_attempts( $flat, null, array_merge( CFG_KEYED_FIXED, array( 'flat_per_slot_use' => false ) ), recorder( array( '', 'x' ), $calls ) );
eq( 'L7.1 a pre-`use` flat era reads a legacy slot 2 key', array( 'unused_line', 'main_line' ), array_column( $calls, 'key' ) );

$calls = array();
bws_try_run_attempts( $flat, null, CFG_KEYED_FIXED, recorder( array( '', 'x' ), $calls ) );
eq( 'L7.2 no era override = the live rule: slot 2 key with no `use` is discarded (FW-51)', array( 'unused_line' ), array_column( $calls, 'key' ) );

// ── §L8 — per-slot datetime reads (`try_datetime`, FW-193) ─────────────────────────────
// The merged datetime template sets `try_per_slot_reads`: each slot's `key(...)` / `use(...)` IS
// that attempt's whole read, and the tag-level pair is not registered. The resolver here is a
// stand-in for the real datetime seam (bws_base_datetime_resolve_value, which needs WordPress):
// it takes its MODE and its reads from bws_normalize_datetime_options() with the family `null`,
// exactly as the real seam does, and looks each field up in a fake post-meta table.

const CFG_DATETIME = array(
	'per_slot_key'   => false,
	'per_slot_use'   => false,
	'per_slot_reads' => true,
	'no_key_uses'    => array(),
	'default_use'    => '',
	'collapse'       => false,
);

/**
 * A datetime resolve seam over a fake meta table. Single: the one read's value. Range: both
 * reads' values joined with ` - `, and nothing when either is empty (a range with no start
 * prints nothing, as the real core does). A verb read is its name upper-cased.
 *
 * @param array $meta  Field name => value.
 * @param array $calls OUT: every option set the walk handed over.
 */
function dt_seam( array $meta, array &$calls ): callable {
	return function ( array $slot_opts, $inst ) use ( $meta, &$calls ) {
		$calls[] = $slot_opts;
		$m       = bws_normalize_datetime_options( $slot_opts, null );
		$read    = static function ( $verb, $field ) use ( $meta ) {
			return '' !== (string) $verb ? strtoupper( $verb ) : (string) ( $meta[ (string) $field ] ?? '' );
		};
		if ( $m['is_range'] ) {
			$a = $read( $m['start_verb'] ?? '', $m['start_field'] ?? '' );
			$b = $read( $m['end_verb'] ?? '', $m['end_field'] ?? '' );
			$v = ( '' !== $a && '' !== $b ) ? $a . ' - ' . $b : '';
		} else {
			$v = $read( $m['verb'] ?? '', $m['date_time_field'] ?? '' );
		}
		return array( 'value' => $v, 'link_id' => 0, 'link_type' => 'post' );
	};
}

/** The value a walk returned, or null when it exhausted. */
function won( array $w ): ?string {
	return '' === $w['value'] ? null : $w['value'];
}

$meta = array( 'a' => '', 'b' => 'Sep 2', 's' => 'Aug 1', 'e' => 'Aug 3', 'x' => 'Oct 9' );

$calls = array();
eq(
	'L8.1 each slot reads its own field: A is empty, B reads b',
	'Sep 2',
	won( bws_try_run_attempts( slots( 'key(a)', 'key(b)' ), null, CFG_DATETIME, dt_seam( $meta, $calls ) ) )
);
eq( 'L8.1 each attempt resolved with its own key', array( 'a', 'b' ), array_column( $calls, 'key' ) );

$calls = array();
eq(
	'L8.2 slots of different modes: a range that reads wins over the single behind it',
	'Aug 1 - Aug 3',
	won( bws_try_run_attempts( slots( 'key(s;e)', 'key(b)' ), null, CFG_DATETIME, dt_seam( $meta, $calls ) ) )
);
eq( 'L8.2 one resolve', 1, count( $calls ) );

$calls = array();
eq(
	'L8.2 range with an empty end falls to the single behind it',
	'Sep 2',
	won( bws_try_run_attempts( slots( 'key(s;a)', 'key(b)' ), null, CFG_DATETIME, dt_seam( $meta, $calls ) ) )
);

$calls = array();
eq(
	'L8.3 a verb slot as the last attempt',
	'PUBLISHED',
	won( bws_try_run_attempts( slots( 'key(a)', 'use(published)' ), null, CFG_DATETIME, dt_seam( $meta, $calls ) ) )
);
eq( 'L8.3 the verb attempt carried no field', array( array( 'published', '' ) ), array_slice( array_map( static fn( $o ) => array( $o['use'], $o['key'] ), $calls ), 1 ) );

$calls = array();
bws_try_run_attempts( slots( 'src(current);key(a)', 'src(site)' ), null, CFG_DATETIME, dt_seam( $meta, $calls ) );
eq(
	'L8.4 slot B (no read) resolved with slot A\'s read, and its own source',
	array( array( '', 'a', 'current' ), array( '', 'a', 'site' ) ),
	array_map( static fn( $o ) => array( $o['use'], $o['key'], $o['src'] ), $calls )
);

$calls = array();
bws_try_run_attempts( slots( 'use(published)', 'src(site);use(same)' ), null, CFG_DATETIME, static function ( $o, $i ) use ( &$calls ) { $calls[] = $o; return array( 'value' => '' ); } );
eq(
	'L8.5 an explicit `use(same)` carries a verb read too',
	array( array( 'published', '' ), array( 'published', '' ) ),
	array_map( static fn( $o ) => array( $o['use'], $o['key'] ), $calls )
);

$calls = array();
bws_try_run_attempts( slots( 'use(published)', 'key(b)' ), null, CFG_DATETIME, dt_seam( $meta, $calls ) );
eq(
	'L8.5 a slot stating any read part borrows nothing: B states a key, so A\'s verb is not carried',
	array( array( 'published', '' ) ),
	array_map( static fn( $o ) => array( $o['use'], $o['key'] ), array_slice( $calls, 0, 1 ) )
);

$calls = array();
eq(
	'L8.6 slot A with no read is a read-less attempt: prints nothing, B takes over',
	'Sep 2',
	won( bws_try_run_attempts( slots( 'src(current)', 'key(b)' ), null, CFG_DATETIME, dt_seam( $meta, $calls ) ) )
);
eq( 'L8.6 A resolved with no read at all', array( array( '', '' ) ), array_map( static fn( $o ) => array( $o['use'], $o['key'] ), array_slice( $calls, 0, 1 ) ) );

$calls = array();
eq(
	'L8.6 nothing configured at all: one read-less attempt, empty walk',
	null,
	won( bws_try_run_attempts( array(), null, CFG_DATETIME, dt_seam( $meta, $calls ) ) )
);

$calls = array();
eq(
	'L8.7 a verb list that needs a field but states none reads empty, and B takes over',
	'Sep 2',
	won( bws_try_run_attempts( slots( 'use(key;now)', 'key(b)' ), null, CFG_DATETIME, dt_seam( $meta, $calls ) ) )
);

eq(
	'L8.7 ... and B does not borrow A\'s verb list (it states a read of its own)',
	array( 'key;now', '' ),
	array( $calls[0]['use'] ?? null, $calls[1]['use'] ?? null )
);

$calls = array();
bws_try_run_attempts( array( 'key' => 'tagkey', 'use' => 'published', 'A' => 'key(a)', 'B' => 'src(site)' ), null, CFG_DATETIME, dt_seam( $meta, $calls ) );
eq(
	'L8.8 a tag-level `key`/`use` never reaches an attempt: the slot\'s read replaces it',
	array( array( '', 'a' ), array( '', 'a' ) ),
	array_map( static fn( $o ) => array( $o['use'], $o['key'] ), $calls )
);

$calls = array();
bws_try_run_attempts( array( 'key' => 'tagkey', 'use' => 'published' ), null, CFG_DATETIME, dt_seam( $meta, $calls ) );
eq(
	'L8.8 with no slot A, a tag-level read is not recovered into slot 1 either',
	array( array( '', '' ) ),
	array_map( static fn( $o ) => array( $o['use'], $o['key'] ), $calls )
);

$calls = array();
bws_try_run_attempts( array( 'startKey' => 'x', 'timeKey' => 'x', 'A' => 'key(b)' ), null, CFG_DATETIME, dt_seam( $meta, $calls ) );
eq(
	'L8.8 the old datetime field options are unregistered on try_datetime and never reach an attempt',
	array( array( false, false, false ) ),
	array_map( static fn( $o ) => array( isset( $o['startKey'] ), isset( $o['timeKey'] ), isset( $o['is_range'] ) ), $calls )
);

$calls = array();
eq(
	'L8.9 no per-slot gate: a verb attempt with no field key still resolves',
	'NOW',
	won( bws_try_run_attempts( slots( 'use(now)' ), null, CFG_DATETIME, dt_seam( $meta, $calls ) ) )
);

// ── §L9 — the exhausted walk's link identity (FW-191 D2) ────────────────────
// An empty attempt reports the identities its read reached; the walk reduces their
// union through bws_sole_link_identity(), and the shell links the fallback to it.

/** An empty attempt that read the given (kind, id) pairs. */
function empty_read( array ...$ids ): array {
	return array(
		'value'           => '',
		'link_id'         => 0,
		'link_type'       => 'post',
		'read_identities' => array_map( static fn( $p ) => array( 'kind' => $p[0], 'id' => $p[1] ), $ids ),
	);
}

$calls = array();
eq(
	'L9.1 two slots reading different fields of ONE post → that post',
	array( 'value' => '', 'link_id' => 11, 'link_type' => 'post' ),
	bws_try_run_attempts( slots( 'key(one)', 'key(two)' ), null, CFG_KEYED, recorder( array( empty_read( array( 'post', 11 ) ), empty_read( array( 'post', 11 ) ) ), $calls ) )
);

$calls = array();
eq(
	'L9.2 slots on DIFFERENT sources (post, then site) → none, the fallback prints plain',
	EXHAUSTED,
	bws_try_run_attempts( slots( 'key(one)', 'src(site);key(one)' ), null, CFG_KEYED, recorder( array( empty_read( array( 'post', 11 ) ), empty_read( array( 'site', 1 ) ) ), $calls ) )
);

$calls = array();
eq(
	'L9.3 a slot that resolved NO entity is ignored → the other slot\'s term',
	array( 'value' => '', 'link_id' => 7, 'link_type' => 'term' ),
	bws_try_run_attempts( slots( 'src(refs,none);key(one)', 'key(two)' ), null, CFG_KEYED, recorder( array( empty_read(), empty_read( array( 'term', 7 ) ) ), $calls ) )
);

$calls = array();
eq(
	'L9.4 a FANNING slot (two posts) breaks agreement → none',
	EXHAUSTED,
	bws_try_run_attempts( slots( 'key(one)', 'key(two)' ), null, CFG_KEYED, recorder( array( empty_read( array( 'post', 11 ) ), empty_read( array( 'post', 11 ), array( 'post', 12 ) ) ), $calls ) )
);

$calls = array();
eq(
	'L9.5 same id, different kinds (post 7, term 7) are two entities → none',
	EXHAUSTED,
	bws_try_run_attempts( slots( 'key(one)', 'key(two)' ), null, CFG_KEYED, recorder( array( empty_read( array( 'post', 7 ) ), empty_read( array( 'term', 7 ) ) ), $calls ) )
);

$calls = array();
eq(
	'L9.6 a WINNER is unchanged: its own triple, no read_identities, earlier empty reads ignored',
	array( 'value' => 'beta', 'link_id' => 12, 'link_type' => 'post' ),
	bws_try_run_attempts(
		slots( 'key(one)', 'key(two)' ),
		null,
		CFG_KEYED,
		recorder( array( empty_read( array( 'term', 7 ) ), array( 'value' => 'beta', 'link_id' => 12, 'link_type' => 'post' ) ), $calls )
	)
);

$calls = array();
eq(
	'L9.7 a resolver that reports no read_identities contributes nothing',
	EXHAUSTED,
	bws_try_run_attempts( slots( 'key(one)' ), null, CFG_KEYED, recorder( array( array( 'value' => '', 'link_id' => 11, 'link_type' => 'post' ) ), $calls ) )
);

echo $fails ? "\n{$fails} FAILURE(S)\n" : "\nALL PASS\n";
exit( $fails ? 1 : 0 );
