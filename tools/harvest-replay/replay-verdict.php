<?php
/**
 * replay-verdict.php — the verdict rules the harvest/replay SCRIPTS cannot hold inline.
 *
 * PURE. No WordPress, no filesystem, no output, no side effects on load. That is the whole
 * reason it exists as a file: `diff-replays.php` and `run-converter.php` are SCRIPTS —
 * requiring either one runs it — so nothing under `tools/test/` can call their decisions.
 * `replay-source-identity-test.php` works around the same problem by reading a sibling's
 * SOURCE rather than calling it, which is as far as that technique goes. Every rule here
 * governs a gate whose failure mode is SILENCE — two loosen one, one refuses a run that
 * would produce a quietly incomplete artifact — so they were extracted to be assertable.
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
 * Whether the upgrade trigger already spent the population `run-converter.php` records.
 *
 * THE AXIS: the stored pattern-cache summary names the `upgrade` trigger, its `version` is
 * the build running now, and it does not positively record having reconciled nothing. All
 * three carry weight. The trigger alone says only that an upgrade repaired the cache at some
 * point, which is the ordinary state of every upgraded site; the VERSION is what says the
 * trigger fired for the build this run is using, so the strings it cleared are gone from the
 * trees with nothing written down. A summary that records `reconciled` as exactly 0 cleared
 * no strings and spent nothing, so it is the one shape that reads safe — absent or non-zero
 * both refuse, because a half-written record cannot show that nothing was lost.
 *
 * TIMESTAMPS ARE NOT THE AXIS, AND WERE, THROUGH 1.19.0. The first spelling asked whether the
 * reconcile happened during THIS request, which catches only the case where `run-converter.php`
 * is the very first WordPress boot after the build swap. It is not: the trigger fires on ANY
 * request — `bin/snapshot.sh --restore` ends with a wp-cli cache flush, and a bare
 * `wp option get` does it too — so on a real clone the population is routinely spent one
 * request before the run, and the old rule read that as a normal upgraded site and let the
 * partial artifact through with a warning that said "nothing was lost on this request", which
 * was true and irrelevant. Measured on a real corpus 2026-08-31 — numbers owned by
 * `tools/harvest-replay/README.md`'s migration-replay bullet, not restated here. The version
 * test subsumes the same-request case rather than extending it:
 * a trigger that fires at `init` 25 has already written its summary by the time this is read.
 *
 * A DEFERRED RUN IS NOT CAUGHT BY THIS, and that is what makes the version the right field.
 * `bws_dynamic_tags_rebuild_allowlist_on_upgrade()` stamps the installed version whether or
 * not it reconciles, so a deferred clone leaves whatever summary it already had — an older
 * build's, or none. Either way the version does not match and the run proceeds.
 *
 * WHY A RUN CANNOT CONTINUE PAST IT. The upgrade trigger runs before `run-converter.php`
 * ever gets to — see `bws_dynamic_tags_rebuild_allowlist_on_upgrade()`'s own PHPDoc for the
 * ordering fact and `PatternCache::upgrade_reconcile_deferred()`'s for why deferring it is
 * safe. `run-converter.php`'s own reconcile then finds nothing left of the wire that was
 * already stale when the run started, and writes an artifact holding ONLY the staleness
 * this run's `migrate_post()` loop created. That artifact is not empty, which is what makes
 * it dangerous: `bws_replay_split_missing()` forgives the pairs it names, and the ones it
 * cannot name stay hard failures reading exactly like a render regression.
 *
 * Empty forgives nothing and is safe by construction — that is the property §R2.6 pins.
 * PARTIAL is the state nothing else detects, and the reason this rule is a refusal rather
 * than a warning: the evidence is destroyed before the run can notice it is missing.
 * Reproduced on the fixture testbed 2026-08-31 (same staleness, same call, ordering the
 * only variable: cleared=0 after the trigger, cleared=1 without it) and on the Site H
 * corpus the same day, where the two strings lost were the ones that predated the run. The
 * axis that decides whether the trigger stands down is
 * `PatternCache::upgrade_reconcile_deferred()`'s, not this one's — this only observes the
 * outcome. GH #117 (FW-78).
 *
 * @param array  $status          `PatternCache::get_status()` output, or empty when never run.
 * @param string $running_version The build this run is using (`BWS_DYNAMIC_TAGS_VERSION`).
 * @return bool True when the run must refuse.
 */
function bws_replay_upgrade_reconcile_consumed( array $status, string $running_version ): bool {
	if ( ! isset( $status['trigger'], $status['version'] ) ) {
		return false;
	}

	if ( 'upgrade' !== $status['trigger'] ) {
		return false;
	}

	if ( '' === $running_version || (string) $status['version'] !== $running_version ) {
		return false;
	}

	// PRESENT AND ZERO is the only shape that proves nothing was spent. Absent does not: a
	// summary that cannot say what it repaired cannot say it repaired nothing, and this gate
	// resolves what it cannot see toward refusing.
	return ! ( isset( $status['reconciled'] ) && 0 === (int) $status['reconciled'] );
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
 * UNRECORDED IS NOT HELD-FIXED, and this rule lives HERE rather than at the call site for a
 * reason worth keeping: it used to take a pre-computed `$build_identical` bool, and
 * `diff-replays.php`'s `$field_of()` yields the literal '?' for a field no artifact recorded
 * — so two sides that said NOTHING about the build compared equal, the held-fixed half passed
 * having proved nothing, and the run reported GATE HELD. That is the same fail-open the
 * build-replay branch already guards ("SAME VERSION AND NO BUILD IDENTITY RECORDED"), one
 * mode over, and it failed open in the direction that matters because an empty diff is this
 * replay's pass condition too. Reported from the ENV repo on 2026-08-29, reproduced on an
 * artifact pair written before `8714324`. Taking the three strings instead of a bool is what
 * lets the rule ask "was anything recorded at all", and what lets §R4 of
 * `tools/test/replay-verdict-test.php` hold it.
 *
 * @param array|null $a_env   A side's decoded `env` row, or null if it has none.
 * @param array|null $b_env   B side's.
 * @param array      $a_build A side's build identity as `$field_of()` produced it:
 *                            array( version, commit, digest ), each the literal '?' where
 *                            nothing was recorded and comma-joined where the artifact spans
 *                            more than one distinct value.
 * @param array      $b_build B side's.
 * @param bool       $has_map Whether --map was supplied.
 * @return array List of array( 'fatal' => bool, 'message' => string ).
 */
function bws_replay_dependency_findings( $a_env, $b_env, array $a_build, array $b_build, bool $has_map ): array {
	$findings = array();

	// A field is USABLE only if it names exactly one recorded thing. '?' is $field_of()'s
	// nothing-was-recorded, and a comma means the artifact saw several — a mid-run rebuild, or
	// a corpus whose runs disagree. Neither attests anything, and the second cannot be spotted
	// by looking for a bare '?'.
	$recorded = static function ( $value ) {
		$value = (string) $value;

		return '' !== $value && '?' !== $value && false === strpos( $value, ',' );
	};

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

	// UNRECORDED BEFORE COMPARED, and the order is the point: two unrecorded sides compare
	// EQUAL, so a comparison made first would report them as held-fixed — the fail-open. It
	// also has to be reported as unrecorded rather than as a moved build, because the two send
	// an operator to different places: one to the replay that wrote the artifact, the other to
	// the branch they are sitting on.
	$unusable = array();

	foreach ( array( 'A' => $a_build, 'B' => $b_build ) as $side => $build ) {
		if ( ! $recorded( $build['commit'] ?? '' ) || ! $recorded( $build['digest'] ?? '' ) ) {
			$unusable[] = $side;
		}
	}

	if ( $unusable ) {
		// The VERSION is deliberately not enough on its own. Every replay has always recorded
		// plugin_version, so accepting it would leave the hole exactly where it was.
		$findings[] = array(
			'fatal'   => true,
			'message' => sprintf(
				'NO BUILD IDENTITY RECORDED on %s. This replay holds our build fixed, and nothing in the artifact can show that it was — two sides that recorded nothing compare EQUAL, so the held-fixed half would pass having proved nothing. Re-run with a replay that records source_commit and source_digest.',
				2 === count( $unusable ) ? 'either side' : 'the ' . $unusable[0] . ' side'
			),
		);

		return $findings;
	}

	if ( $a_digest === $b_digest ) {
		$findings[] = array(
			'fatal'   => true,
			'message' => sprintf( 'BOTH SIDES RENDERED THE SAME DEPENDENCY ENVIRONMENT (%s). The swap did not happen, and an empty diff here means nothing.', $a_digest ),
		);
	}

	$build_identical = ( ( $a_build['version'] ?? '' ) === ( $b_build['version'] ?? '' ) )
		&& ( $a_build['commit'] ?? '' ) === ( $b_build['commit'] ?? '' )
		&& ( $a_build['digest'] ?? '' ) === ( $b_build['digest'] ?? '' );

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
