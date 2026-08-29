<?php
/**
 * replay-verdict.php — the two verdict rules `diff-replays.php` could not hold inline.
 *
 * PURE. No WordPress, no filesystem, no output, no side effects on load. That is the whole
 * reason it exists as a file: `diff-replays.php` is a SCRIPT — requiring it runs a diff — so
 * nothing under `tools/test/` can call its decisions. `replay-source-identity-test.php`
 * works around the same problem by reading a sibling's SOURCE rather than calling it, which
 * is as far as that technique goes. These two rules both LOOSEN a gate, and a loosened gate
 * whose failure mode is silence has to be assertable, so they were extracted instead.
 * `tools/test/replay-verdict-test.php` is what exercises them.
 *
 * Read `tools/harvest-replay/README.md` first — it owns what the instrument is, how a run is
 * driven, and what a clean diff proves. `docs/update-triggers.md#harvestreplay-verification-change`
 * states the rules a run rides on. Neither is restated here.
 *
 * @package BWS_Dynamic_Tags
 */

/**
 * Index a removal artifact by the tag strings it says were cleared.
 *
 * The artifact is written by `run-converter.php` from what `PatternCache::reconcile_site()`
 * reports as it repairs each cached pattern tree. Rows are `{"kind":"removed", "post_id":N,
 * "tag_string":"…"}`, preceded by one `{"kind":"meta", …}` row carrying the run's provenance.
 *
 * ONLY `removed` ROWS ARE INDEXED. The meta row holds a regex and a timestamp; indexing it
 * would put whatever it happens to carry into the forgiveness set, which is the difference
 * between reading evidence and reading a file.
 *
 * @param array $rows Decoded artifact rows, in file order.
 * @return array<string,int> tag string => how many patterns it was cleared from.
 */
function bws_replay_removed_wire_index( array $rows ): array {
	$index = array();

	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) || 'removed' !== ( $row['kind'] ?? '' ) ) {
			continue;
		}

		$tag = (string) ( $row['tag_string'] ?? '' );

		if ( '' === $tag ) {
			continue;
		}

		$index[ $tag ] = ( $index[ $tag ] ?? 0 ) + 1;
	}

	return $index;
}

/**
 * Split one-sided pairs into repairs and unexplained disappearances.
 *
 * THE AXIS: a pair is a REPAIR iff it is present on the A side only AND its tag string
 * appears in the removal set. Both halves carry weight and neither is negotiable.
 *
 * The DIRECTION is the whole asymmetry. `PatternCache::reconcile_site()` runs before the B
 * side is harvested and only ever REMOVES stale shadow wire from a cached pattern tree, so
 * it can subtract from B and can never add to it. A pair present on B alone is wire that
 * APPEARED, which no removal explains — forgiving it would let a migration invent rows and
 * report GATE HELD.
 *
 * The MEMBERSHIP half is what keeps this evidence rather than an exception list, which
 * `diff-replays.php`'s standing rule forbids ("a list assembled after seeing a diff is a
 * rationalisation, not a gate"). The set is produced BY the run that produced the diff. An
 * empty artifact therefore forgives nothing, which is the property that makes a hand-written
 * file useless here — pinned at §R2.6.
 *
 * Membership is a test, never a budget: one entry explains every pair carrying that string,
 * because a string cleared from one pattern can legitimately still live in another.
 *
 * @param array               $missing Rows of array( 'key' => "url\0tag", 'side' => 'A only'|'B only' ).
 * @param array<string,int>   $removed bws_replay_removed_wire_index() output.
 * @return array{repaired:array,unexplained:array} Each row array( url, tag, side ).
 */
function bws_replay_split_missing( array $missing, array $removed ): array {
	$out = array(
		'repaired'    => array(),
		'unexplained' => array(),
	);

	foreach ( $missing as $row ) {
		$key  = (string) ( $row['key'] ?? '' );
		$side = (string) ( $row['side'] ?? '' );
		$part = explode( "\x00", $key, 2 );
		$url  = $part[0] ?? '';
		$tag  = $part[1] ?? '';

		$entry = array(
			'url'  => $url,
			'tag'  => $tag,
			'side' => $side,
		);

		$bucket = ( 'A only' === $side && isset( $removed[ $tag ] ) ) ? 'repaired' : 'unexplained';

		$out[ $bucket ][] = $entry;
	}

	return $out;
}

/**
 * Findings for a DEPENDENCY replay — the one where our build is the held-fixed half.
 *
 * THE AXIS: the environment must have MOVED and our build must NOT have. That is the exact
 * inverse of the build replay's rule, which is why it needs its own mode rather than a
 * loosened check: for a build replay, same-version-plus-same-commit means the swap did not
 * happen and the empty diff behind it means nothing, and that check stays untouched.
 *
 * THE SILENT FAILURE, one axis over from the build replay's: "I forgot to run the upgrade"
 * and "the upgrade changed nothing" produce the same two artifacts and the same clean diff.
 * The `env` row is what tells them apart — appended by the ENV repo's `dep-versions.php`,
 * and skipped by this instrument's loader until now.
 *
 * WHAT THIS DELIBERATELY DOES NOT ASSERT: which dependency moved, and whether anything else
 * moved with it. The ENV repo's `attest-deps.php` owns those, because the staging that
 * produced them lives there and a second copy of that rule here would be a drift pair. This
 * asks only whether the environment moved at all while our build did not.
 *
 * @param array|null $a_env          A side's decoded `env` row, or null if it has none.
 * @param array|null $b_env          B side's.
 * @param bool       $build_identical Whether both sides recorded the same version, commit and tree digest.
 * @param bool       $has_map        Whether --map was supplied.
 * @return array List of array( 'fatal' => bool, 'message' => string ).
 */
function bws_replay_dependency_findings( $a_env, $b_env, bool $build_identical, bool $has_map ): array {
	$findings = array();

	if ( $has_map ) {
		$findings[] = array(
			'fatal'   => true,
			'message' => '--map and the dependency mode are different replays: --map says the wire moved on purpose, and a dependency replay holds the wire fixed. Run one or the other.',
		);
	}

	$a_digest = is_array( $a_env ) ? (string) ( $a_env['env_digest'] ?? '' ) : '';
	$b_digest = is_array( $b_env ) ? (string) ( $b_env['env_digest'] ?? '' ) : '';

	// UNATTESTABLE IS NOT ATTESTED. An artifact from before dep-versions.php existed carries
	// no env row, and one carrying a row with no digest compares nothing: reading two absent
	// digests as equal would report the swap as not having happened, and reading them as
	// different would attest one that nothing recorded.
	if ( '' === $a_digest || '' === $b_digest ) {
		$findings[] = array(
			'fatal'   => true,
			'message' => 'NO RECORDED DEPENDENCY ENVIRONMENT on ' . ( '' === $a_digest && '' === $b_digest ? 'either side' : ( '' === $a_digest ? 'the A side' : 'the B side' ) ) . '. A dependency replay that cannot show the two sides ran against different dependencies has nothing to say, and its empty diff is the most misleading output this instrument produces. Re-run with the ENV repo\'s dep-versions.php recording an env row.',
		);

		return $findings;
	}

	if ( $a_digest === $b_digest ) {
		$findings[] = array(
			'fatal'   => true,
			'message' => sprintf( 'BOTH SIDES RENDERED THE SAME DEPENDENCY ENVIRONMENT (%s). The swap did not happen, and an empty diff here means nothing.', $a_digest ),
		);
	}

	if ( ! $build_identical ) {
		$findings[] = array(
			'fatal'   => true,
			'message' => 'OUR BUILD MOVED TOO. A replay is named for its one variable; with two of them moving, a diff attributes nothing and an empty one is worse still, because two changes can cancel. Hold this plugin fixed, or run this as a build replay instead.',
		);
	}

	if ( ! $findings ) {
		$findings[] = array(
			'fatal'   => false,
			'message' => sprintf( 'dependency environment moved (%s -> %s) and this build did not — WHICH dependency moved, and that nothing else did, is attest-deps.php\'s call in the ENV repo.', $a_digest, $b_digest ),
		);
	}

	return $findings;
}
