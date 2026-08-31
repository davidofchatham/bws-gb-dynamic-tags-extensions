<?php
/**
 * Pure harness for the verdict rules the harvest/replay scripts cannot hold inline.
 *
 * Loads the REAL file (tools/harvest-replay/replay-verdict.php) rather than a transcribed
 * copy — the newer house convention, whose point is that a test-local copy of a rule is the
 * drift the extraction removed. That file is pure by construction and requiring it runs
 * nothing; `diff-replays.php` and `run-converter.php` cannot be loaded this way, which is why
 * the decisions were extracted rather than tested in place (the same reason
 * `replay-source-identity-test.php` reads a sibling's SOURCE instead of calling it).
 *
 * WHAT EVERY SHAPE HERE HAS IN COMMON IS A FAILURE MODE OF SILENCE, which is why they are
 * pinned rather than left at their call sites. §R1–§R4 LOOSEN a gate: `diff-replays.php` read
 * a legitimate run as a hard failure in two unrelated ways, and a rule that forgives too much
 * reports GATE HELD. §R5 is the inverse and arrived later (2026-08-31) — a REFUSAL, covering
 * a state that destroys the evidence it would take to notice the artifact came out partial.
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
	array( 'kind' => 'meta', 'recorded_at' => '2026-08-28T00:00:00+00:00', 'wire_pattern' => '/x/' ),
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

// The build identity as `diff-replays.php`'s $field_of() hands it over: three strings, each
// the literal '?' where the artifact recorded nothing, and comma-joined where the artifact
// saw more than one distinct value.
$build = static function ( $commit, $digest = 'dig1', $version = '1.19.0' ) {
	return array(
		'version' => $version,
		'commit'  => $commit,
		'digest'  => $digest,
	);
};

$held = $build( 'abc123abc123' );

$fatal = static function ( array $findings ) {
	return array_values( array_filter( $findings, static fn( $f ) => ! empty( $f['fatal'] ) ) );
};

$good = bws_replay_dependency_findings( $env_a, $env_b, $held, $held, false );
$check( 'R3.1 held-fixed build + a moved environment is a CLEAN dependency replay', array() === $fatal( $good ) );
$check( 'R3.2 ...and it still says something, so the run is not silently trusted', count( $good ) > 0 );

// THE SILENT FAILURE THIS MODE EXISTS TO CATCH, one axis over from the build replay's:
// "I forgot to run the upgrade" and "the upgrade changed nothing" are the same empty diff.
$same_env = bws_replay_dependency_findings( $env_a, $env_a, $held, $held, false );
$check( 'R3.3 an UNMOVED environment is fatal — the swap did not happen and the empty diff means nothing', 1 === count( $fatal( $same_env ) ) );
$check(
	'R3.4 ...and the message says so rather than reporting a bare digest',
	false !== strpos( $fatal( $same_env )[0]['message'], 'did not happen' )
);

// The build replay's own failure, inverted: here a MOVED build is the defect, because the
// replay is named for its one variable and our build is not it.
$moved_build = bws_replay_dependency_findings( $env_a, $env_b, $held, $build( 'def456def456' ), false );
$check( 'R3.5 a build that MOVED is fatal in this mode — two variables attribute nothing', 1 === count( $fatal( $moved_build ) ) );
$check(
	'R3.6 ...including one that moved only in its working tree, with the commit unchanged',
	1 === count( $fatal( bws_replay_dependency_findings( $env_a, $env_b, $held, $build( 'abc123abc123', 'dig2' ), false ) ) )
);

// An artifact from before dep-versions.php existed carries no env row. The honest verdict is
// "unattestable", never "attested" — the same call attest-deps.php makes.
$check( 'R3.7 a missing env row on the A side is fatal, not a warning', 1 === count( $fatal( bws_replay_dependency_findings( null, $env_b, $held, $held, false ) ) ) );
$check( 'R3.8 ...and on the B side', 1 === count( $fatal( bws_replay_dependency_findings( $env_a, null, $held, $held, false ) ) ) );
$check( 'R3.9 ...and on both, reported once rather than twice', 1 === count( $fatal( bws_replay_dependency_findings( null, null, $held, $held, false ) ) ) );

// --map says "the wire moved on purpose"; the dependency replay holds the wire fixed. Asking
// for both is asking for two different experiments in one run.
$check(
	'R3.10 --map alongside the dependency mode is fatal — they are different replays',
	1 === count(
		array_filter(
			$fatal( bws_replay_dependency_findings( $env_a, $env_b, $held, $held, true ) ),
			static fn( $f ) => false !== strpos( $f['message'], 'map' )
		)
	)
);

// A digest of '' or a missing key is not a comparison. Reading two absent digests as "equal"
// would report the swap as not having happened; reading them as "different" would attest one.
$blank = bws_replay_dependency_findings(
	array( 'kind' => 'env' ),
	array( 'kind' => 'env' ),
	$held,
	$held,
	false
);
$check( 'R3.11 an env row with no digest is fatal rather than compared', 1 === count( $fatal( $blank ) ) );

/* =========================================================================
 * §R4 — an UNRECORDED build identity is not a held-fixed one
 *
 * THE FAIL-OPEN THIS SECTION EXISTS FOR, reported from the ENV repo on 2026-08-29 after the
 * flag was wired up there. `diff-replays.php`'s $field_of() yields the literal '?' when a
 * field was never recorded, so two artifacts that said NOTHING about the build compared
 * equal, the held-fixed half passed having proved nothing, and the run reported GATE HELD.
 * Reproduced on the artifact pair the env repo held until 2026-08-29, written before
 * `8714324` taught replay-tags.php which tree to fingerprint.
 *
 * IT IS THE SAME SHAPE THE BUILD-REPLAY BRANCH ALREADY GUARDS — "SAME VERSION AND NO BUILD
 * IDENTITY RECORDED. Nothing here can show the swap happened" — one mode over, and it failed
 * open in the direction that matters, because an empty diff is this replay's pass condition
 * too.
 *
 * WHY THE RULE MOVED INSIDE THIS FUNCTION rather than being fixed at the call site: it used
 * to take a pre-computed `$build_identical` bool, so the question "was anything recorded at
 * all" could not be asked here and no assertion in this file could have caught the defect.
 * The caller now hands over the three strings and the rule owns the whole decision.
 * ====================================================================== */

$unrecorded = $build( '?', '?' );

$check(
	'R4.1 an unrecorded build identity on BOTH sides is fatal, not "held fixed"',
	1 === count( $fatal( bws_replay_dependency_findings( $env_a, $env_b, $unrecorded, $unrecorded, false ) ) )
);
$check(
	'R4.2 ...and the message says nothing was recorded, rather than reporting agreement',
	false !== strpos( $fatal( bws_replay_dependency_findings( $env_a, $env_b, $unrecorded, $unrecorded, false ) )[0]['message'], 'NO BUILD IDENTITY' )
);
$check(
	'R4.3 an unrecorded identity on ONE side is fatal too — half an attestation is none',
	1 === count( $fatal( bws_replay_dependency_findings( $env_a, $env_b, $held, $unrecorded, false ) ) )
);
$check(
	'R4.4 a recorded commit with an unrecorded DIGEST is fatal — both halves or neither',
	1 === count( $fatal( bws_replay_dependency_findings( $env_a, $env_b, $held, $build( 'abc123abc123', '?' ), false ) ) )
);

// $field_of() comma-joins the distinct values it saw, so an artifact that spans a swap prints
// neither a commit nor a bare '?'. That is the case the env-side stopgap could not test for by
// matching on 'source ?', and it is the shape a mid-run rebuild produces.
$check(
	'R4.5 an artifact spanning TWO builds is fatal — a comma-joined identity attests neither',
	1 === count( $fatal( bws_replay_dependency_findings( $env_a, $env_b, $build( '?,abc123abc123' ), $held, false ) ) )
);
$check(
	'R4.6 ...including when both sides span, so they compare equal',
	1 === count( $fatal( bws_replay_dependency_findings( $env_a, $env_b, $build( 'aaa,bbb' ), $build( 'aaa,bbb' ), false ) ) )
);

// ORDER MATTERS: unrecorded is reported as unrecorded, never as "your build moved". The two
// send an operator to different places — one to the replay that wrote the artifact, the other
// to the branch they are sitting on.
$mixed = $fatal( bws_replay_dependency_findings( $env_a, $env_b, $held, $unrecorded, false ) );
$check(
	'R4.7 an unrecorded side is reported as unrecorded, not as a moved build',
	false !== strpos( $mixed[0]['message'], 'NO BUILD IDENTITY' ) && false === strpos( $mixed[0]['message'], 'MOVED' )
);

// The version alone is not identity. A replay has always recorded plugin_version, so treating
// it as sufficient would leave the defect exactly where it was.
$check(
	'R4.8 a recorded VERSION does not rescue an unrecorded commit and digest',
	1 === count( $fatal( bws_replay_dependency_findings( $env_a, $env_b, $build( '?', '?', '1.19.0' ), $build( '?', '?', '1.19.0' ), false ) ) )
);

// ---------------------------------------------------------------------------
// §R5 — the run refuses when the upgrade trigger already spent the population
//
// The other rules here loosen a gate. This one REFUSES a run, and it is pinned for the
// mirror-image reason: the state it detects destroys its own evidence. The upgrade trigger
// clears stale shadow wire without recording which strings, so `run-converter.php`'s
// artifact ends up naming only what this run's own migration created — non-empty, partially
// forgiving, and indistinguishable downstream from a real render regression. An empty
// artifact is safe (§R2.6); a PARTIAL one is what nothing else catches.
//
// BOTH HALVES OF THE AXIS ARE PINNED SEPARATELY, because either alone is the common case:
// an ordinary upgraded site has an `upgrade` summary from some earlier day, and a summary
// written this request under any other trigger is this run's own work.
$status = function ( $trigger, $time ) {
	return array( 'trigger' => $trigger, 'time' => $time, 'checked' => 3, 'reconciled' => 1 );
};

$now = 1756600000;

$check(
	'R5.1 an upgrade reconcile during THIS request is fatal — the strings are gone unrecorded',
	true === bws_replay_upgrade_reconcile_consumed( $status( 'upgrade', $now ), $now )
);

$check(
	'R5.2 ...and one a second later too, since the trigger runs after the request began',
	true === bws_replay_upgrade_reconcile_consumed( $status( 'upgrade', $now + 1 ), $now )
);

// The ordinary state of every upgraded site. Reading this as fatal would refuse every run.
$check(
	'R5.3 an upgrade reconcile from BEFORE this request is fine — that is a normal site',
	false === bws_replay_upgrade_reconcile_consumed( $status( 'upgrade', $now - 1 ), $now )
);

// This run's own reconcile writes a `migrate` summary. Keying on the timestamp alone would
// make the check fire on the run it is meant to protect.
$check(
	'R5.4 a MIGRATE reconcile this request is not it — that is this script doing its job',
	false === bws_replay_upgrade_reconcile_consumed( $status( 'migrate', $now ), $now )
);

$check(
	'R5.5 a SCAN reconcile this request is not it either',
	false === bws_replay_upgrade_reconcile_consumed( $status( 'scan', $now ), $now )
);

// A site where the reconcile has never run has nothing to have spent. Absent must read as
// safe rather than as unknown-so-refuse, or a fresh clone could never be converted.
$check(
	'R5.6 a site with no stored summary at all is not it',
	false === bws_replay_upgrade_reconcile_consumed( array(), $now )
);

$check(
	'R5.7 a summary missing its timestamp is not it — a half-written record attests nothing',
	false === bws_replay_upgrade_reconcile_consumed( array( 'trigger' => 'upgrade' ), $now )
);

$check(
	'R5.8 ...nor one missing its trigger',
	false === bws_replay_upgrade_reconcile_consumed( array( 'time' => $now ), $now )
);

echo $fail ? "\nREPLAY VERDICT TEST FAILED ({$fail})\n" : "\nREPLAY VERDICT TEST PASSED\n";
exit( $fail ? 1 : 0 );
