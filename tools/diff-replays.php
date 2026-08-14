<?php
/**
 * diff-replays.php — compare two replay artifacts and decide whether the gate holds.
 *
 * Third piece of the harvest/replay instrument (`.claude/plans/multi-step-slot-sources.md`
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
 *  2. Every (url, tag_string) pair renders byte-identically.
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

$paths = array();
$full  = false;
$max   = 20;

foreach ( $argv_in as $arg ) {
	if ( '--full' === $arg ) {
		$full = true;
	} elseif ( 0 === strpos( $arg, '--max=' ) ) {
		$max = max( 1, (int) substr( $arg, 6 ) );
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
if ( $a_version === $b_version ) {
	// Not fatal: Experiment R compares two builds of the same declared version on purpose.
	$line( "[!] both sides report plugin {$a_version} — expected for a same-version resolver run, wrong for a migration run." );
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
		$line( sprintf( '[!] %s: %d URL(s) 404d against a non-404 context kind — those renders are empty for the wrong reason:', $label, count( $mismatched ) ) );
		foreach ( array_slice( $mismatched, 0, $max ) as $r ) {
			$line( "      {$r['context_kind']}  {$r['url']}" );
		}
	}
}

// ---------------------------------------------------------------------------
// 3. Pair-by-pair comparison.
// ---------------------------------------------------------------------------
$keys = array_unique( array_merge( array_keys( $a['renders'] ), array_keys( $b['renders'] ) ) );
sort( $keys, SORT_STRING );

$buckets = array(
	'attested'     => array(),
	'synthetic'    => array(),
	'unclassified' => array(),
);
$missing  = array();
$volatile = 0;
$same     = 0;

foreach ( $keys as $key ) {
	$ra = $a['renders'][ $key ] ?? null;
	$rb = $b['renders'][ $key ] ?? null;

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

$changed = count( $buckets['attested'] ) + count( $buckets['synthetic'] ) + count( $buckets['unclassified'] );

$line( sprintf( 'identical : %d', $same ) );
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
if ( $same + $changed === 0 ) {
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
