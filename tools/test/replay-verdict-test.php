<?php
/**
 * Pure harness for the replay differ's two new verdict shapes.
 *
 * Loads the REAL file (tools/harvest-replay/replay-verdict.php) rather than a transcribed
 * copy — the newer house convention, whose point is that a test-local copy of a rule is the
 * drift the extraction removed. That file is pure by construction and requiring it runs
 * nothing; `diff-replays.php` itself cannot be loaded this way, which is why the decisions
 * were extracted rather than tested in place (the same reason
 * `replay-source-identity-test.php` reads a sibling's SOURCE instead of calling it).
 *
 * WHAT THESE TWO SHAPES HAVE IN COMMON, and why they arrived together: `diff-replays.php`
 * read a legitimate run as a hard failure in two unrelated ways, and both fixes LOOSEN a
 * gate. A loosened gate is the change that has to be pinned hardest, because its failure
 * mode is silence — a rule that forgives too much reports GATE HELD.
 *
 * THE FORGIVENESS RULE IS EVIDENCE-SHAPED, NOT LIST-SHAPED. `diff-replays.php`'s standing
 * rule is that "a list assembled after seeing a diff is a rationalisation, not a gate". What
 * makes the repaired bucket legal is that the removal artifact is PRODUCED BY THE RUN — the
 * reconcile reports which strings it cleared as it clears them. §R2.6 is the assertion that
 * says an empty artifact forgives nothing, which is the property keeping a hand-written file
 * from being usable as an exception list.
 *
 * Run:  php tools/test/replay-verdict-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

require_once __DIR__ . '/../harvest-replay/replay-verdict.php';

$fail = 0;

$check = function ( $label, $ok, $detail = '' ) use ( &$fail ) {
	printf( "[%s] %s%s\n", $ok ? 'PASS' : 'FAIL', $label, '' !== $detail ? " — {$detail}" : '' );
	if ( ! $ok ) {
		$fail++;
	}
};

// The differ keys a pair as url \0 tag_string. Building keys through a helper keeps every
// case below reading as (url, tag) rather than as a literal with a control character in it.
$pair = static function ( $url, $tag ) {
	return $url . "\x00" . $tag;
};

/* =========================================================================
 * §R1 — reading the removal artifact
 * ====================================================================== */

$rows = array(
	array( 'kind' => 'meta', 'recorded_at' => '2026-08-28T00:00:00+00:00', 'wire_regex' => '/x/' ),
	array( 'kind' => 'removed', 'post_id' => 12, 'tag_string' => '{{text src:ref|ref:body}}' ),
	array( 'kind' => 'removed', 'post_id' => 13, 'tag_string' => '{{text src:ref|ref:body}}' ),
	array( 'kind' => 'removed', 'post_id' => 13, 'tag_string' => '{{email}}' ),
);

$index = bws_replay_removed_wire_index( $rows );

$check( 'R1.1 every removed tag string is indexed', 2 === count( $index ) );
$check(
	'R1.2 a string cleared from two patterns is counted twice, not collapsed',
	2 === $index['{{text src:ref|ref:body}}'] && 1 === $index['{{email}}']
);
// A `meta` row carries the artifact's provenance, not a string to forgive. Indexing it would
// put whatever it happens to hold into the forgiveness set.
$check( 'R1.3 the artifact meta row contributes nothing to the set', ! isset( $index['/x/'] ) );
$check(
	'R1.4 a row with no tag_string is skipped rather than indexing the empty string',
	array() === bws_replay_removed_wire_index( array( array( 'kind' => 'removed', 'post_id' => 9 ) ) )
);
$check( 'R1.5 an empty artifact yields an empty set', array() === bws_replay_removed_wire_index( array() ) );

/* =========================================================================
 * §R2 — a repaired row is not a vanished one
 *
 * `PatternCache::reconcile_site()` REMOVES stale shadow wire from cached pattern trees, so a
 * migration run's B-side census legitimately holds fewer rows than the A side rendered.
 * Every removed string lands in the differ as a pair present on only one side, which is a
 * hard failure today: 212 such pairs beside 13,445 identical and CHANGED 0 on one measured
 * run. The rule below is what tells the two apart.
 * ====================================================================== */

$missing = array(
	array( 'key' => $pair( '/a/', '{{text src:ref|ref:body}}' ), 'side' => 'A only' ),
	array( 'key' => $pair( '/b/', '{{title}}' ), 'side' => 'A only' ),
	array( 'key' => $pair( '/c/', '{{email}}' ), 'side' => 'B only' ),
);

$split = bws_replay_split_missing( $missing, $index );

$check(
	'R2.1 an A-only pair whose string the reconcile cleared is a REPAIR',
	1 === count( $split['repaired'] ) && '/a/' === $split['repaired'][0]['url']
);
$check(
	'R2.2 an A-only pair the reconcile did not clear stays unexplained — the gate is unchanged for it',
	1 === count( array_filter( $split['unexplained'], static fn( $r ) => '{{title}}' === $r['tag'] ) )
);

// DIRECTION MATTERS, AND IT IS THE WHOLE ASYMMETRY. The repair runs BEFORE the B side is
// harvested, so it can only ever subtract from B. A pair present on B alone is wire that
// APPEARED, which no removal can explain — forgiving it would let a migration invent rows.
$check(
	'R2.3 a B-only pair is never repaired, even when its string is in the removal set',
	1 === count( array_filter( $split['unexplained'], static fn( $r ) => '{{email}}' === $r['tag'] ) )
);
$check( 'R2.4 ...so exactly two of the three stay unexplained', 2 === count( $split['unexplained'] ) );

// The differ prints url + tag + side; the split has to carry them or the report loses detail
// it already had.
$check(
	'R2.5 each row keeps its url, tag and side, so the report is not degraded by bucketing',
	'/a/' === $split['repaired'][0]['url']
		&& '{{text src:ref|ref:body}}' === $split['repaired'][0]['tag']
		&& 'A only' === $split['repaired'][0]['side']
);

// THE PROPERTY THAT KEEPS THIS FROM BEING AN EXCEPTION LIST. An artifact that names nothing
// forgives nothing, so supplying an empty file cannot turn a failing run green.
$empty = bws_replay_split_missing( $missing, array() );
$check(
	'R2.6 an EMPTY removal set forgives nothing — every pair stays unexplained',
	array() === $empty['repaired'] && 3 === count( $empty['unexplained'] )
);
$check(
	'R2.7 no missing pairs at all yields two empty buckets',
	array(
		'repaired'    => array(),
		'unexplained' => array(),
	) === bws_replay_split_missing( array(), $index )
);

// A string can be cleared from one pattern and still live in another, so the same string
// legitimately appears as a repair on one URL and as a real difference on another. The rule
// is per-PAIR and must not consume the set entry.
$twice = bws_replay_split_missing(
	array(
		array( 'key' => $pair( '/a/', '{{email}}' ), 'side' => 'A only' ),
		array( 'key' => $pair( '/b/', '{{email}}' ), 'side' => 'A only' ),
	),
	$index
);
$check( 'R2.8 the set is a membership test, not a budget — one entry explains every pair carrying that string', 2 === count( $twice['repaired'] ) );

/* =========================================================================
 * §R3 — held-fixed build identity is the POINT of a dependency replay
 *
 * For a BUILD replay, same-version-plus-same-commit means the swap did not happen and the
 * empty diff behind it means nothing — that check is load-bearing and stays. For a
 * DEPENDENCY replay the same pair is the held-fixed half, so a correct run is rejected by
 * construction. The varying axis moves to the `env` row, which the ENV repo's
 * dep-versions.php appends and which the loader used to skip.
 *
 * WHAT THIS DOES NOT DUPLICATE: which dependency moved, and whether anything else moved with
 * it, are asserted by the ENV repo's attest-deps.php, where the staging that produced them
 * lives. This asks only whether the environment moved at all while our build did not.
 * ====================================================================== */

$env_a = array(
	'kind'         => 'env',
	'env_digest'   => 'aaaaaaaaaaaa',
	'plugin_count' => 22,
);
$env_b = array(
	'kind'         => 'env',
	'env_digest'   => 'bbbbbbbbbbbb',
	'plugin_count' => 22,
);

$fatal = static function ( array $findings ) {
	return array_values( array_filter( $findings, static fn( $f ) => ! empty( $f['fatal'] ) ) );
};

$good = bws_replay_dependency_findings( $env_a, $env_b, true, false );
$check( 'R3.1 held-fixed build + a moved environment is a CLEAN dependency replay', array() === $fatal( $good ) );
$check( 'R3.2 ...and it still says something, so the run is not silently trusted', count( $good ) > 0 );

// THE SILENT FAILURE THIS MODE EXISTS TO CATCH, one axis over from the build replay's:
// "I forgot to run the upgrade" and "the upgrade changed nothing" are the same empty diff.
$same_env = bws_replay_dependency_findings( $env_a, $env_a, true, false );
$check( 'R3.3 an UNMOVED environment is fatal — the swap did not happen and the empty diff means nothing', 1 === count( $fatal( $same_env ) ) );
$check(
	'R3.4 ...and the message says so rather than reporting a bare digest',
	false !== strpos( $fatal( $same_env )[0]['message'], 'did not happen' )
);

// The build replay's own failure, inverted: here a MOVED build is the defect, because the
// replay is named for its one variable and our build is not it.
$moved_build = bws_replay_dependency_findings( $env_a, $env_b, false, false );
$check( 'R3.5 a build that MOVED is fatal in this mode — two variables attribute nothing', 1 === count( $fatal( $moved_build ) ) );

// An artifact from before dep-versions.php existed carries no env row. The honest verdict is
// "unattestable", never "attested" — the same call attest-deps.php makes.
$check( 'R3.6 a missing env row on the A side is fatal, not a warning', 1 === count( $fatal( bws_replay_dependency_findings( null, $env_b, true, false ) ) ) );
$check( 'R3.7 ...and on the B side', 1 === count( $fatal( bws_replay_dependency_findings( $env_a, null, true, false ) ) ) );
$check( 'R3.8 ...and on both, reported once rather than twice', 1 === count( $fatal( bws_replay_dependency_findings( null, null, true, false ) ) ) );

// --map says "the wire moved on purpose"; the dependency replay holds the wire fixed. Asking
// for both is asking for two different experiments in one run.
$check(
	'R3.9 --map alongside the dependency mode is fatal — they are different replays',
	1 === count(
		array_filter(
			$fatal( bws_replay_dependency_findings( $env_a, $env_b, true, true ) ),
			static fn( $f ) => false !== strpos( $f['message'], 'map' )
		)
	)
);

// A digest of '' or a missing key is not a comparison. Reading two absent digests as "equal"
// would report the swap as not having happened; reading them as "different" would attest one.
$blank = bws_replay_dependency_findings(
	array( 'kind' => 'env' ),
	array( 'kind' => 'env' ),
	true,
	false
);
$check( 'R3.10 an env row with no digest is fatal rather than compared', 1 === count( $fatal( $blank ) ) );

echo $fail ? "\nREPLAY VERDICT TEST FAILED ({$fail})\n" : "\nREPLAY VERDICT TEST PASSED\n";
exit( $fail ? 1 : 0 );
