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
 * The walk's own PHPDoc is the axis owner for all five. These rows check its
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
 * @param array $reads   OUT — every $slot_read (the third argument) likewise.
 * @return callable
 */
function recorder( array $script, array &$calls, array &$reads ): callable {
	return function ( array $slot_opts, $inst, array $slot_read ) use ( $script, &$calls, &$reads ) {
		$i       = count( $calls );
		$calls[] = $slot_opts;
		$reads[] = $slot_read;
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
$reads = array();
eq(
	'L1.1 first attempt empty, second reads → the SECOND value wins',
	array( 'value' => 'beta', 'link_id' => 0, 'link_type' => 'post' ),
	bws_try_run_attempts(
		slots( 'key(one)', 'key(two)', 'key(three)' ),
		null,
		CFG_KEYED,
		recorder( array( '', 'beta', 'gamma' ), $calls, $reads )
	)
);
eq( 'L1.1 exactly two attempts were resolved — the third never ran', array( 'one', 'two' ), array_column( $calls, 'key' ) );

$calls = array();
$reads = array();
eq(
	'L1.2 first attempt reads → it wins',
	array( 'value' => 'alpha', 'link_id' => 0, 'link_type' => 'post' ),
	bws_try_run_attempts( slots( 'key(one)', 'key(two)' ), null, CFG_KEYED, recorder( array( 'alpha', 'beta' ), $calls, $reads ) )
);
eq( 'L1.2 the walk stopped at one resolve', array( 'one' ), array_column( $calls, 'key' ) );

$calls = array();
$reads = array();
eq(
	'L1.3 a stored "0" is a VALUE and stops the walk (no emptiness re-decided here)',
	array( 'value' => '0', 'link_id' => 0, 'link_type' => 'post' ),
	bws_try_run_attempts( slots( 'key(one)', 'key(two)' ), null, CFG_KEYED, recorder( array( '0', 'beta' ), $calls, $reads ) )
);
eq( 'L1.3 the second attempt never ran', array( 'one' ), array_column( $calls, 'key' ) );

$calls = array();
$reads = array();
eq(
	'L1.4 every attempt empty → NULL (the shell then runs the fallback or the label)',
	null,
	bws_try_run_attempts( slots( 'key(one)', 'key(two)' ), null, CFG_KEYED, recorder( array( '', '' ), $calls, $reads ) )
);
eq( 'L1.4 both attempts were resolved before the walk gave up', array( 'one', 'two' ), array_column( $calls, 'key' ) );

$calls = array();
$reads = array();
eq(
	'L1.5 no attempts configured at all → NULL, resolver never called',
	null,
	bws_try_run_attempts( array(), null, CFG_KEYED, recorder( array( 'alpha' ), $calls, $reads ) )
);
eq( 'L1.5 resolver never called', array(), $calls );

// ── §L2 — the per-slot read gate ────────────────────────────────────────────

$calls = array();
$reads = array();
eq(
	'L2.1 keyed family: an attempt with a source but NO key is skipped before the resolver',
	array( 'value' => 'beta', 'link_id' => 0, 'link_type' => 'post' ),
	bws_try_run_attempts(
		slots( 'src(terms,category)', 'key(two)' ),
		null,
		CFG_KEYED,
		recorder( array( 'beta' ), $calls, $reads )
	)
);
eq( 'L2.1 only the keyed attempt reached the resolver', array( 'two' ), array_column( $calls, 'key' ) );

$calls = array();
$reads = array();
eq(
	'L2.2 a no-key `use` value resolves WITHOUT a key',
	array( 'value' => 'alpha', 'link_id' => 0, 'link_type' => 'post' ),
	bws_try_run_attempts( slots( 'use(title)' ), null, CFG_KEYED, recorder( array( 'alpha' ), $calls, $reads ) )
);
eq( 'L2.2 the attempt carried use:title and no key at all', array( array( 'title', null, '1' ) ), reads( $calls ) );

$calls = array();
$reads = array();
eq(
	'L2.3 a family with NO per-slot read axis has no gate — a keyless attempt still resolves',
	array( 'value' => 'alpha', 'link_id' => 0, 'link_type' => 'post' ),
	bws_try_run_attempts( slots( 'src(terms,category)' ), null, CFG_NO_READ, recorder( array( 'alpha' ), $calls, $reads ) )
);
eq( 'L2.3 the walk wrote neither use nor key onto it', array( array( null, null, '0' ) ), reads( $calls ) );

// ── §L3 — the carry hand-off ────────────────────────────────────────────────

$calls = array();
$reads = array();
bws_try_run_attempts(
	slots( 'src(refs,office);key(one)', 'src(same);key(two)' ),
	null,
	CFG_KEYED,
	recorder( array( '', '' ), $calls, $reads )
);
eq(
	'L3.1 `src(same)` carries the prior attempt\'s WHOLE chain, not a root token',
	array( 'refs,office', 'refs,office' ),
	srcs( $calls )
);

$calls = array();
$reads = array();
bws_try_run_attempts(
	slots( 'src(refs,office);key(one)', 'src(terms,category)' ),
	null,
	CFG_KEYED,
	recorder( array( '', '' ), $calls, $reads )
);
eq(
	'L3.2 an attempt stating no read carries the prior key (a selecting container)',
	array( array( 'key', 'one', '0' ), array( 'key', 'one', '0' ) ),
	reads( $calls )
);

$calls = array();
$reads = array();
bws_try_run_attempts(
	slots( 'key(one)', 'src(terms,category)', 'src(refs,office);use(same)' ),
	null,
	CFG_KEYED,
	recorder( array( '', '', '' ), $calls, $reads )
);
eq(
	'L3.3 `use(same)` reaches back past an attempt that stated no read of its own',
	array( 'one', 'one', 'one' ),
	array_column( $calls, 'key' )
);

$calls = array();
$reads = array();
bws_try_run_attempts(
	slots( 'src(terms,category)' ),
	null,
	array( 'per_slot_key' => true, 'per_slot_use' => true, 'no_key_uses' => array( 'title' ), 'default_use' => 'title', 'collapse' => false ),
	recorder( array( '' ), $calls, $reads )
);
eq(
	'L3.4 slot 1 seeds the accumulator from the family\'s own default `use`',
	array( array( 'title', null, '0' ) ),
	reads( $calls )
);

$calls = array();
$reads = array();
bws_try_run_attempts(
	slots( 'src(refs,office)', 'src(same);key(two)' ),
	null,
	CFG_KEYED,
	recorder( array( '' ), $calls, $reads )
);
eq(
	'L3.5 THE GATE FIRES AFTER THE SEAM TOOK ITS CARRY — a skipped attempt still fed the chain',
	array( 'refs,office' ),
	srcs( $calls )
);

// ── §L4 — the bound, resolved by the walk and written back explicitly ───────

$calls = array();
$reads = array();
bws_try_run_attempts( slots( 'key(one)' ), null, CFG_KEYED, recorder( array( '' ), $calls, $reads ) );
eq( 'L4.1 a non-fanning attempt bounds at 1', array( array( 'key', 'one', '1' ) ), reads( $calls ) );

$calls = array();
$reads = array();
bws_try_run_attempts( slots( 'src(terms,category);key(one)' ), null, CFG_KEYED, recorder( array( '' ), $calls, $reads ) );
eq( 'L4.2 a FANNING chain takes the unlimited default', array( array( 'key', 'one', '0' ) ), reads( $calls ) );

$calls = array();
$reads = array();
bws_try_run_attempts( slots( 'src(terms,category,limit[3]);key(one)' ), null, CFG_KEYED, recorder( array( '' ), $calls, $reads ) );
eq( 'L4.3 a slot-stated limit governs its own attempt', array( array( 'key', 'one', '3' ) ), reads( $calls ) );

$calls = array();
$reads = array();
bws_try_run_attempts(
	slots( 'src(terms,category,limit[3]);key(one)' ),
	null,
	array( 'per_slot_key' => true, 'per_slot_use' => true, 'no_key_uses' => array(), 'default_use' => 'key', 'collapse' => true ),
	recorder( array( '' ), $calls, $reads )
);
eq( 'L4.4 a COLLAPSING family forces 1 over anything the wire says (ADR 0007)', array( array( 'key', 'one', '1' ) ), reads( $calls ) );

$calls = array();
$reads = array();
bws_try_run_attempts(
	slots( 'src(terms,category);key(one)' ) + array( 'limit' => '4' ),
	null,
	CFG_KEYED,
	recorder( array( '' ), $calls, $reads )
);
eq( 'L4.5 the RETIRED tag-level `limit` is still read for wire that still carries one (#61/#62)', array( array( 'key', 'one', '4' ) ), reads( $calls ) );

// ── §L5 — the hand-off shape ────────────────────────────────────────────────

$calls = array();
$reads = array();
bws_try_run_attempts(
	slots( 'key(one)' ) + array( 'fallback' => 'nope', 'fallback_text' => 'also nope', 'linkTo' => 'post', 'sep' => ' / ' ),
	null,
	CFG_KEYED,
	recorder( array( '' ), $calls, $reads )
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
$reads = array();
eq(
	'L5.2 the winning triple comes back verbatim for the shell to link-wrap',
	array( 'value' => 'alpha', 'link_id' => 42, 'link_type' => 'term' ),
	bws_try_run_attempts(
		slots( 'key(one)' ),
		null,
		CFG_KEYED,
		recorder( array( array( 'value' => 'alpha', 'link_id' => 42, 'link_type' => 'term' ) ), $calls, $reads )
	)
);

$calls = array();
$reads = array();
eq(
	'L5.3 a resolver returning a bare value takes link_id 0 / link_type post',
	array( 'value' => 'alpha', 'link_id' => 0, 'link_type' => 'post' ),
	bws_try_run_attempts(
		slots( 'key(one)' ),
		null,
		CFG_KEYED,
		recorder( array( array( 'value' => 'alpha' ) ), $calls, $reads )
	)
);

$calls = array();
$reads = array();
bws_try_run_attempts( slots( 'src(terms,category);key(one)' ), null, CFG_KEYED, recorder( array( '' ), $calls, $reads ) );
eq(
	'L5.4 the THIRD argument is the fold seam\'s own return — what the ATTEMPT named, not what the tag did',
	array( 'key' => 'one', 'use' => 'key' ),
	array( 'key' => $reads[0]['key'], 'use' => $reads[0]['use'] )
);

echo $fails ? "\n{$fails} FAILURE(S)\n" : "\nALL PASS\n";
exit( $fails ? 1 : 0 );
