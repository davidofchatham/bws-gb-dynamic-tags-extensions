<?php
/**
 * diff-replays.php — compare two replay artifacts and decide whether the gate holds.
 *
 * Third piece of the harvest/replay instrument (`.claude/plans/archive/multi-step-slot-sources.md`
 * §Verification). Harvest says what wire exists; replay says what it renders; this says
 * whether the two sides agree, and — where they do not — puts each disagreement in a bucket
 * a reviewer can act on.
 *
 * Plain PHP. No WordPress, no wp-cli: `php tools/diff-replays.php <a.jsonl> <b.jsonl>`.
 *
 * WHAT IT ASSERTS
 * ---------------
 *  1. Both sides rendered the SAME URL set. Two runs that sampled independently are not
 *     comparable, and a partial artifact is the easy way to a clean diff — so an asymmetric
 *     URL set is a hard failure, never a filtered-down comparison.
 *  2. Both sides rendered the SAME CENSUS, and it is the census supplied here. The corpus
 *     is overwritten in place by a re-harvest, in the directory the artifacts live in, so an
 *     artifact silently outlives the wire it was built from. Two sides on two corpora are
 *     not comparable; a supplied census that is not the rendered one buckets every pair off
 *     the wrong corpus. Differing sides are legal ONLY with --map, which is the migration
 *     run saying the divergence is intended.
 *  3. Every (url, tag_string) pair renders byte-identically.
 *
 * The exception list is CLOSED (§Verification: "a list assembled after seeing a diff is a
 * rationalisation, not a gate"). So ANY difference fails. Buckets exist to make triage
 * possible, not to excuse anything:
 *
 *   attested  — the tag really is stored in content reachable at that URL, so the change has
 *               genuine user exposure.
 *   synthetic — the pair comes from the cartesian of the census and the URL inventory. Most
 *               tags live in Elements, which have no URL of their own, so this is the bulk of
 *               the coverage. A change here is still a real behavioural change; it just has no
 *               proven front-end exposure. Classifying needs the census, so pass it as the
 *               third argument — without it everything is reported as unclassified.
 *   volatile  — the tag did not render the same twice within a single process, so it cannot
 *               be held to byte identity by anything. Reported and EXCLUDED from the verdict,
 *               because a "now"-reading tag differs across two runs for reasons that are not
 *               the change under test. A pair that is volatile on either side is excluded.
 *   errors    — a tag that threw. Counted on both sides; a change in the error text or a
 *               newly-throwing tag is a difference like any other.
 *
 * WHAT A CLEAN RESULT DOES NOT MEAN. Nobody in the wild has authored a multi-step slot,
 * because nothing could. This instrument is evidence about the population that EXISTS. New
 * capability rests on the matrices and the pure harnesses, and no amount of clean clone data
 * changes that.
 *
 * Usage:
 *   php tools/diff-replays.php <a.jsonl> <b.jsonl> [census.jsonl] [--full] [--max=20]
 *
 * Exit 0 iff the gate holds.
 */

$argv_in = $argv;
array_shift( $argv_in );

$paths    = array();
$full     = false;
$max      = 20;
$map_path = null;

foreach ( $argv_in as $arg ) {
	if ( '--full' === $arg ) {
		$full = true;
	} elseif ( 0 === strpos( $arg, '--max=' ) ) {
		$max = max( 1, (int) substr( $arg, 6 ) );
	} elseif ( 0 === strpos( $arg, '--map=' ) ) {
		$map_path = substr( $arg, 6 );
	} else {
		$paths[] = $arg;
	}
}

if ( count( $paths ) < 2 ) {
	fwrite( STDERR, "Usage: php tools/diff-replays.php <a.jsonl> <b.jsonl> [census.jsonl] [--full] [--max=N]\n" );
	exit( 2 );
}

list( $a_path, $b_path ) = $paths;
$census_path = $paths[2] ?? null;

/**
 * Read one replay artifact into { runs, renders } keyed for comparison.
 */
$load = static function ( string $path ): array {
	if ( ! is_readable( $path ) ) {
		fwrite( STDERR, "Not readable: {$path}\n" );
		exit( 2 );
	}
	$runs    = array();
	$renders = array();
	$fh      = fopen( $path, 'r' );
	while ( false !== ( $line = fgets( $fh ) ) ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$row = json_decode( $line, true );
		if ( ! is_array( $row ) ) {
			continue;
		}
		if ( 'run' === ( $row['kind'] ?? '' ) ) {
			$runs[ $row['url'] ] = $row;
		} elseif ( 'render' === ( $row['kind'] ?? '' ) ) {
			$renders[ $row['url'] . "\x00" . $row['tag_string'] ] = $row;
		}
	}
	fclose( $fh );
	return array( 'runs' => $runs, 'renders' => $renders );
};

$a = $load( $a_path );
$b = $load( $b_path );

// Attested pairs, if a census was supplied: (url, tag_string) where the tag is genuinely
// stored in content reachable at that URL.
$attested = array();
if ( $census_path ) {
	if ( ! is_readable( $census_path ) ) {
		fwrite( STDERR, "Census not readable: {$census_path}\n" );
		exit( 2 );
	}
	$fh = fopen( $census_path, 'r' );
	while ( false !== ( $line = fgets( $fh ) ) ) {
		$row = json_decode( trim( $line ), true );
		if ( is_array( $row ) && ! empty( $row['attested'] ) && ! empty( $row['url'] ) ) {
			$attested[ $row['url'] . "\x00" . $row['tag_string'] ] = true;
		}
	}
	fclose( $fh );
}

$fail = 0;
$line = static function ( string $s = '' ) { echo $s . "\n"; };

$line( '=== replay diff ===' );
$line( "A: {$a_path}" );
$line( "B: {$b_path}" );
$line( $census_path ? "census: {$census_path}" : 'census: (none — nothing can be classified as attested)' );
$line();

// ---------------------------------------------------------------------------
// 1. Provenance. A diff between two runs of the SAME build proves nothing about a change,
//    and is the shape a botched file swap produces.
// ---------------------------------------------------------------------------
$version_of = static function ( array $side ): string {
	$v = array();
	foreach ( $side['runs'] as $run ) {
		$v[ (string) ( $run['plugin_version'] ?? '?' ) ] = true;
	}
	return implode( ',', array_keys( $v ) );
};

$a_version = $version_of( $a );
$b_version = $version_of( $b );

$line( sprintf( 'A: %d URLs, %d renders, plugin %s', count( $a['runs'] ), count( $a['renders'] ), $a_version ) );
$line( sprintf( 'B: %d URLs, %d renders, plugin %s', count( $b['runs'] ), count( $b['renders'] ), $b_version ) );

if ( false !== strpos( $a_version, ',' ) || false !== strpos( $b_version, ',' ) ) {
	$line( '[X] A side rendered under MORE THAN ONE plugin version — the artifact spans a swap.' );
	$fail++;
}
// ACROSS A MIGRATION BOUNDARY THE TAG STRINGS THEMSELVES CHANGE, so the two sides cannot be
// keyed against each other directly — the pairing has to come from the converter, which emits
// it as mapping.jsonl. Without --map the comparison is same-wire-both-sides (Experiment R).
//
// It is READ HERE, ahead of the build-identity guard below, because that guard consults it:
// a non-empty mapping is evidence that a migration happened, whoever shipped it.
//
// The translation is BEST-EFFORT ON PURPOSE. A tag string can live both in a post (migrated)
// and in an option or postmeta (unreachable by the converter, so still in its old form), and
// the same string can therefore appear on the B side twice over. Falling back to the untouched
// key when the mapped one is absent is what keeps that case from reading as a vanished tag.
$map = array();
if ( null !== $map_path ) {
	if ( ! is_readable( $map_path ) ) {
		fwrite( STDERR, "Mapping not readable: {$map_path}\n" );
		exit( 2 );
	}
	$fh = fopen( $map_path, 'r' );
	// NB: not $line — that name holds the output closure by this point in the file.
	while ( false !== ( $map_line = fgets( $fh ) ) ) {
		$row = json_decode( trim( $map_line ), true );
		if ( is_array( $row ) && isset( $row['old'], $row['new'] ) ) {
			$map[ $row['old'] ] = $row['new'];
		}
	}
	fclose( $fh );
	$line( sprintf( 'mapping: %s (%d tag strings rewritten by the converter)', $map_path, count( $map ) ) );
	$line();
}

// BUILD IDENTITY. Two builds of one declared version is Experiment R's normal shape, so the
// version alone cannot say whether the swap happened — and if it did not, the diff comes back
// EMPTY, which is R's pass condition. Asserted only when the versions match, because that is
// precisely R's signature: in a migration run the versions differ and the dev mount legitimately
// sits at one commit for both sides.
$field_of = static function ( array $side, string $field ): string {
	$v = array();
	foreach ( $side['runs'] as $run ) {
		$v[ (string) ( $run[ $field ] ?? '?' ) ] = true;
	}
	return implode( ',', array_keys( $v ) );
};

$a_commit = $field_of( $a, 'source_commit' );
$b_commit = $field_of( $b, 'source_commit' );
$a_digest = $field_of( $a, 'source_digest' );
$b_digest = $field_of( $b, 'source_digest' );

$line( sprintf( 'A: source %s (%s)', substr( $a_commit, 0, 12 ) ?: '?', $a_digest ) );
$line( sprintf( 'B: source %s (%s)', substr( $b_commit, 0, 12 ) ?: '?', $b_digest ) );

if ( $a_version === $b_version ) {
	$line( "[!] both sides report plugin {$a_version} — expected for a same-version resolver run, wrong for a migration run." );

	if ( '?' === $a_commit || '?' === $b_commit ) {
		$line( '[X] SAME VERSION AND NO BUILD IDENTITY RECORDED. Nothing here can show the swap happened, and an unswapped run diffs EMPTY — which is this run\'s pass condition. Re-run with a replay that records source_commit.' );
		$fail++;
	} elseif ( $a_commit === $b_commit && $a_digest === $b_digest && $map ) {
		// A MIGRATION BOUNDARY NEED NOT BE THIS PLUGIN'S. The guard below assumes the wire moved
		// because THIS build moved, which is true of Experiment M and false the moment a second
		// plugin migrates tags through this converter — bws-portal-system 5.7.0 rewrites its own
		// view_* tags onto base tags, so a staged run holds this build fixed on both sides while
		// the wire changes underneath it. A non-empty mapping is the evidence that distinguishes
		// the two: an unswapped run has no rewrites to show, because nothing converted anything.
		$line( sprintf( '[!] both sides rendered build %s, but the mapping records %d rewrite(s) — a migration by something other than this plugin\'s version bump. Not vacuous; the swap that matters happened elsewhere.', substr( $a_commit, 0, 12 ), count( $map ) ) );
	} elseif ( $a_commit === $b_commit && $a_digest === $b_digest ) {
		$line( sprintf( '[X] BOTH SIDES RENDERED THE SAME BUILD (%s / %s). The swap did not happen — branch not switched, or the worktree symlink not repointed. An empty diff here means nothing.', substr( $a_commit, 0, 12 ), $a_digest ) );
		$fail++;
	} elseif ( $a_commit === $b_commit ) {
		$line( '[!] same commit, different working tree — one side carries uncommitted edits. Intended for a quick iteration, wrong for a recorded result.' );
	}
}

// CORPUS IDENTITY. Same argument as build identity, one axis over: an artifact says nothing
// unless you know WHICH census it rendered. `bin/harvest-tags.sh` overwrites the census in
// place, in the directory the artifacts already sit in, so a re-harvest orphans every earlier
// artifact beside it — silently, because the files still parse and still diff.
//
// Two DIFFERENT failures, and conflating them hides the second:
//   1. The two sides rendered different corpora. They are not comparable at all.
//   2. The sides agree with each other but not with the census passed here, so every
//      attested/synthetic classification below is being read off the wrong corpus.
// Live case for (2) on 2026-08-17: Experiment R's portals pair outlived its census by four
// hours and would still have produced a confident, wrongly-bucketed verdict.
$a_census = $field_of( $a, 'census_digest' );
$b_census = $field_of( $b, 'census_digest' );

if ( '?' === $a_census || '?' === $b_census ) {
	// A WARNING, NOT A FAILURE, and deliberately weaker than the build-identity rule above.
	// An unrecorded corpus is merely unverifiable; an unrecorded BUILD makes an R run
	// worthless, because the empty diff it produces is the pass condition. Artifacts
	// predating this field are otherwise perfectly good and must stay diffable.
	$line( '[!] census digest not recorded on one or both sides (pre-2026-08-17 replay) — corpus identity cannot be verified.' );
} else {
	$line( sprintf( 'A: census %s | B: census %s', $a_census, $b_census ) );

	if ( $a_census !== $b_census ) {
		if ( null === $map_path ) {
			$line( '[X] THE TWO SIDES RENDERED DIFFERENT CENSUSES. Same-version runs share one corpus by construction; two corpora are not comparable, and the pairs that exist on only one side silently vanish from the verdict. Re-run both sides against one census.' );
			$fail++;
		} else {
			// Experiment M's normal shape: migration rewrites the wire, so B is harvested
			// afterwards and the two corpora differ BY DESIGN. That is what --map exists to
			// bridge, so its presence is the statement that a divergence is intended.
			$line( '[!] censuses differ, and --map was supplied — expected for a migration run, wrong for a same-version resolver run.' );
		}
	}

	if ( null !== $census_path ) {
		$supplied = substr( (string) md5_file( $census_path ), 0, 12 );
		if ( $supplied !== $a_census ) {
			$line( sprintf( '[X] THE CENSUS SUPPLIED HERE (%s) IS NOT THE ONE THAT WAS RENDERED (%s). It has been overwritten since — a re-harvest writes in place. Every attested/synthetic bucket below would be read off the wrong corpus. These artifacts cannot be re-diffed with classification; re-run rather than re-diff.', $supplied, $a_census ) );
			$fail++;
		}
	}
}
$line();

// ---------------------------------------------------------------------------
// 2. The URL set must match exactly.
// ---------------------------------------------------------------------------
$a_urls = array_keys( $a['runs'] );
$b_urls = array_keys( $b['runs'] );
sort( $a_urls );
sort( $b_urls );

$only_a = array_diff( $a_urls, $b_urls );
$only_b = array_diff( $b_urls, $a_urls );

if ( $only_a || $only_b ) {
	$line( '[X] URL SETS DIFFER — the two runs are not comparable.' );
	foreach ( array_slice( $only_a, 0, $max ) as $u ) {
		$line( "      only in A: {$u}" );
	}
	foreach ( array_slice( $only_b, 0, $max ) as $u ) {
		$line( "      only in B: {$u}" );
	}
	$fail++;
	$line();
}

// A URL that 404'd when the inventory said otherwise means a whole stratum went missing, and
// N empty renders look like agreement.
foreach ( array( 'A' => $a, 'B' => $b ) as $label => $side ) {
	$mismatched = array_filter( $side['runs'], static fn( $r ) => ! empty( $r['context_mismatch'] ) );
	if ( $mismatched ) {
		$line( sprintf( '[!] %s: %d URL(s) did not resolve to the context kind the inventory promised — that stratum is testing something else on BOTH sides, so its agreement means nothing:', $label, count( $mismatched ) ) );
		foreach ( array_slice( $mismatched, 0, $max ) as $r ) {
			$line( "      {$r['context_kind']}  →  {$r['observed']}   {$r['url']}" );
		}
	}
}

// ---------------------------------------------------------------------------
// 3. Pair-by-pair comparison.
// ---------------------------------------------------------------------------
// The mapping that pairs the two sides is loaded further up, beside the build-identity guard
// that reads it.

$translate = static function ( string $key ) use ( $map, $b ): string {
	if ( ! $map ) {
		return $key;
	}
	list( $url, $tag ) = explode( "\x00", $key, 2 );
	if ( isset( $map[ $tag ] ) ) {
		$candidate = $url . "\x00" . $map[ $tag ];
		if ( isset( $b['renders'][ $candidate ] ) ) {
			return $candidate;
		}
	}
	return $key;
};

// With a mapping, the A side is the subject: every tag that existed before must have a
// counterpart after. Wire that exists only on the B side is new, and is reported as a count
// rather than as a failure — a migrated page can legitimately hold both forms.
$keys = $map
	? array_keys( $a['renders'] )
	: array_unique( array_merge( array_keys( $a['renders'] ), array_keys( $b['renders'] ) ) );
sort( $keys, SORT_STRING );

$consumed = array();

$buckets = array(
	'attested'     => array(),
	'synthetic'    => array(),
	'unclassified' => array(),
);
$missing  = array();
$volatile = 0;
$rescued  = 0;
$same     = 0;

foreach ( $keys as $key ) {
	$b_key = $translate( $key );
	$ra    = $a['renders'][ $key ] ?? null;
	$rb    = $b['renders'][ $b_key ] ?? null;

	if ( null !== $rb ) {
		$consumed[ $b_key ] = true;
	}

	if ( null === $ra || null === $rb ) {
		$missing[] = array( 'key' => $key, 'side' => null === $ra ? 'B only' : 'A only' );
		continue;
	}

	// Volatile on EITHER side: nothing can hold it to byte identity, so it is excluded from
	// the verdict rather than counted as agreement.
	if ( ! empty( $ra['volatile'] ) || ! empty( $rb['volatile'] ) ) {
		$volatile++;
		continue;
	}

	$oa = array( $ra['output'], $ra['error'] );
	$ob = array( $rb['output'], $rb['error'] );

	if ( $oa === $ob ) {
		$same++;
		continue;
	}

	// THE antispambot RESCUE. WordPress obfuscates an email address by choosing per CHARACTER,
	// at random, whether to emit it raw, as a decimal entity or as a hex one — so {{email}} is
	// byte-different on every single render and can never be compared raw. It also travels:
	// a page whose content embeds an email tag makes {{content}} intermittently different too,
	// and intermittent is the dangerous kind, because a pair that happens to be stable within
	// both runs would otherwise be reported as a genuine change.
	//
	// Decoding entities collapses exactly that randomness and nothing else: two genuinely
	// different outputs stay different, since decode only equates a character with its own
	// entity spelling. Rescued pairs are reported rather than folded into `identical`, because
	// "equal only after normalisation" is a weaker statement than byte identity and the
	// reviewer is entitled to see which pairs rest on it.
	$decode = static function ( $v ) {
		return is_string( $v ) ? html_entity_decode( $v, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : $v;
	};

	if ( array_map( $decode, $oa ) === array_map( $decode, $ob ) ) {
		$rescued++;
		continue;
	}

	list( $url, $tag ) = explode( "\x00", $key, 2 );
	$bucket = $census_path
		? ( isset( $attested[ $key ] ) ? 'attested' : 'synthetic' )
		: 'unclassified';

	$buckets[ $bucket ][] = array(
		'url'          => $url,
		'tag'          => $tag,
		'context_kind' => $ra['context_kind'] ?? '?',
		'a'            => $ra['error'] ?? $ra['output'],
		'b'            => $rb['error'] ?? $rb['output'],
	);
}

if ( $missing ) {
	$line( sprintf( '[X] %d (url, tag) pair(s) present on only one side.', count( $missing ) ) );
	foreach ( array_slice( $missing, 0, $max ) as $m ) {
		list( $url, $tag ) = explode( "\x00", $m['key'], 2 );
		$line( "      {$m['side']}: {$tag}  @ {$url}" );
	}
	$fail++;
	$line();
}

if ( $map ) {
	$leftover = count( $b['renders'] ) - count( $consumed );
	if ( $leftover > 0 ) {
		$line( sprintf( '[i] %d B-side render(s) had no A-side counterpart — wire the migration introduced, or an old form left behind where the converter could not reach it.', $leftover ) );
		$line();
	}
}

$changed = count( $buckets['attested'] ) + count( $buckets['synthetic'] ) + count( $buckets['unclassified'] );

$line( sprintf( 'identical : %d', $same ) );
$line( sprintf( 'rescued   : %d  (equal only after entity decode — antispambot randomises {{email}} per render)', $rescued ) );
$line( sprintf( 'volatile  : %d  (excluded — did not render the same twice in one process)', $volatile ) );
$line( sprintf( 'CHANGED   : %d', $changed ) );
$line();

foreach ( $buckets as $name => $rows ) {
	if ( ! $rows ) {
		continue;
	}
	$fail++;
	$line( sprintf( '--- %s (%d) ---', strtoupper( $name ), count( $rows ) ) );
	$show = $full ? $rows : array_slice( $rows, 0, $max );
	foreach ( $show as $r ) {
		$line( "  {$r['tag']}" );
		$line( "    @ {$r['url']}  [{$r['context_kind']}]" );
		$line( '    A: ' . var_export( $r['a'], true ) );
		$line( '    B: ' . var_export( $r['b'], true ) );
	}
	if ( ! $full && count( $rows ) > $max ) {
		$line( sprintf( '  ... %d more (--full to show all)', count( $rows ) - $max ) );
	}
	$line();
}

// Non-vacuity. Comparing two empty artifacts, or two runs where every tag rendered nothing,
// passes every check above while proving nothing — and that is exactly what a botched file
// swap or an unbootable clone produces.
$non_empty = 0;
foreach ( $a['renders'] as $r ) {
	if ( '' !== (string) ( $r['output'] ?? '' ) ) {
		$non_empty++;
	}
}
if ( $same + $changed + $rescued === 0 ) {
	$line( '[X] nothing was compared — the artifacts hold no overlapping renders.' );
	$fail++;
} elseif ( 0 === $non_empty ) {
	$line( '[X] every tag on side A rendered EMPTY — a clean diff here means nothing was exercised.' );
	$fail++;
} else {
	$line( sprintf( '[i] non-vacuity: %d of %d A-side renders produced output.', $non_empty, count( $a['renders'] ) ) );
}

$line();
$line( $fail ? "GATE FAILED ({$fail} finding" . ( 1 === $fail ? '' : 's' ) . ')' : 'GATE HELD — every comparable pair is byte-identical.' );
exit( $fail ? 1 : 0 );
