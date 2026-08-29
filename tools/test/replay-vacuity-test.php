<?php
/**
 * Vacuity guard for the replay differ — every attestation, driven end to end.
 *
 * RUNS THE REAL SCRIPT, which nothing else under `tools/test/` does to `diff-replays.php`.
 * It shells out to `php tools/harvest-replay/diff-replays.php` with fixture artifacts and
 * reads the exit status and stdout. That is safe here and is not safe for its sibling:
 * `replay-tags.php` executes a replay on load, which is why `replay-source-identity-test.php`
 * reads that file's SOURCE instead of calling it. This one is a pure CLI diff with no world
 * behind it.
 *
 * WHY IT EXISTS — THE SHARED CONSEQUENCE, NOT A SHARED CAUSE. Two failures of this instrument
 * have now had nothing in common except their outcome:
 *
 *   - `8714324` (2026-08-24): a path argument did not follow a file move, so the build
 *     tripwire fingerprinted `tools/` and recorded no commit at all. A refactoring miss.
 *   - `d12a1b3` (2026-08-29): the dependency mode's call site reduced three recorded facts to
 *     a yes/no, discarding absent-versus-present, so two artifacts that recorded NOTHING
 *     compared equal. A seam that could not express the question.
 *
 * Different mechanisms; one outcome. **THE TRIPWIRE FAILED OPEN, AND AN EMPTY DIFF IS THIS
 * INSTRUMENT'S PASS CONDITION.** That is what makes the outcome worth guarding rather than the
 * cause worth tracking: a tracked row for "this class" would be recording a resemblance, and
 * the next instance will not resemble either of these either. A guard keyed on the consequence
 * does not need them to be alike.
 *
 * WHAT MAKES IT A GUARD RATHER THAN MORE CASES: §V3 is a CENSUS. It scans the two shipped
 * files for every fatal message they can emit and fails if any of them was never provoked by a
 * case above. So a mode, a check or a message added later is covered by a test nobody
 * remembered to update — which is the property the cases on their own do not have, and the
 * property a tracker row could never have.
 *
 * MUTATION-CHECKED 2026-08-29, because a guard that asserts the wrong things passes forever
 * and nobody looks again. Five rules were broken one at a time in the two shipped files and
 * every one failed here by name: restoring the unrecorded-identity fail-open (§V2.4, §V3.2),
 * collapsing the exit statuses back to one (fourteen §V2 assertions), removing the env
 * attestation (§V2.5, §V3.2), making the differ fail everything (§V1.1, §V1.2 — the positive
 * controls doing their job), and — the one that matters most — adding a fatal message no case
 * provokes, which failed §V3.2 alone. That last is the forward guard working on a change that
 * had not been written when this file was.
 *
 * Run:  php tools/test/replay-vacuity-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

$root   = dirname( dirname( __DIR__ ) );
$differ = $root . '/tools/harvest-replay/diff-replays.php';
$rules  = $root . '/tools/harvest-replay/replay-verdict.php';

foreach ( array( $differ, $rules ) as $needed ) {
	if ( ! is_readable( $needed ) ) {
		fwrite( STDERR, "Not readable: {$needed}\n" );
		exit( 2 );
	}
}

$fail  = 0;
$total = 0;

$check = function ( $label, $ok, $detail = '' ) use ( &$fail, &$total ) {
	$total++;
	printf( "[%s] %s%s\n", $ok ? 'PASS' : 'FAIL', $label, '' !== $detail ? " — {$detail}" : '' );
	if ( ! $ok ) {
		$fail++;
	}
};

/* -------------------------------------------------------------------------
 * Fixtures
 * ---------------------------------------------------------------------- */

$dir = sys_get_temp_dir() . '/bws-replay-vacuity-' . getmypid();

if ( ! is_dir( $dir ) && ! mkdir( $dir, 0777, true ) && ! is_dir( $dir ) ) {
	fwrite( STDERR, "Cannot create {$dir}\n" );
	exit( 2 );
}

register_shutdown_function(
	static function () use ( $dir ) {
		foreach ( (array) glob( $dir . '/*' ) as $f ) {
			@unlink( $f );
		}
		@rmdir( $dir );
	}
);

/**
 * One run row. Defaults describe a well-formed modern artifact; each case overrides only the
 * field its attestation is about, so a case cannot accidentally be testing two things.
 */
$run = static function ( array $over = array() ) {
	return array_merge(
		array(
			'kind'             => 'run',
			'url'              => '/a/',
			'context_kind'     => 'singular',
			'observed'         => 'singular',
			'queried_id'       => 1,
			'context_mismatch' => false,
			'plugin_version'   => '1.19.0',
			'source_commit'    => 'aaaaaaaaaaaa',
			'source_digest'    => 'digA',
			'source_files'     => 40,
			'census_digest'    => 'cen1',
			'census_rows'      => 1,
			'tags'             => 1,
			'volatility_check' => true,
			'rendered_at'      => '2026-08-29T00:00:00+00:00',
		),
		$over
	);
};

$render = static function ( $tag, $output, $url = '/a/' ) {
	return array(
		'kind'         => 'render',
		'url'          => $url,
		'context_kind' => 'singular',
		'tag_string'   => $tag,
		'output'       => $output,
		'error'        => null,
		'volatile'     => false,
	);
};

$env = static function ( $digest ) {
	return array(
		'kind'         => 'env',
		'plugins'      => array(),
		'runtime'      => array(),
		'env_digest'   => $digest,
		'plugin_count' => 22,
		'wp_version'   => '6.9',
		'php_version'  => '8.3.0',
		'recorded_at'  => '2026-08-29T00:00:00+00:00',
	);
};

$write = static function ( $name, array $rows ) use ( $dir ) {
	$out = '';
	foreach ( $rows as $row ) {
		$out .= json_encode( $row ) . "\n";
	}
	file_put_contents( $dir . '/' . $name, $out );

	return $dir . '/' . $name;
};

$TAG = '{{text src:ref|ref:body}}';

/* -------------------------------------------------------------------------
 * Driving the script
 * ---------------------------------------------------------------------- */

$seen_messages = array();

/**
 * Run the differ and return array( status, output ). Every `[X]` line is recorded for the
 * census in §V3.
 */
$diff = static function ( array $args ) use ( $differ, &$seen_messages ) {
	$cmd = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $differ );
	foreach ( $args as $arg ) {
		$cmd .= ' ' . escapeshellarg( $arg );
	}

	$lines  = array();
	$status = 0;
	exec( $cmd . ' 2>&1', $lines, $status );

	foreach ( $lines as $l ) {
		if ( 0 === strpos( $l, '[X] ' ) ) {
			$seen_messages[] = substr( $l, 4 );
		}
	}

	return array( $status, implode( "\n", $lines ) );
};

/* =========================================================================
 * §V1 — the POSITIVE controls
 *
 * FIRST, AND NOT AS A COURTESY. A guard that only asserts failures passes just as well when
 * the script has stopped working entirely: everything fails, every expectation is met, and the
 * suite is green over an instrument that can no longer report anything. These three are what
 * make the rest of the file mean something.
 * ====================================================================== */

$a_clean = $write( 'v1-a.jsonl', array( $run(), $render( $TAG, 'body copy' ) ) );
$b_clean = $write( 'v1-b.jsonl', array( $run( array( 'source_commit' => 'bbbbbbbbbbbb', 'source_digest' => 'digB' ) ), $render( $TAG, 'body copy' ) ) );

list( $status ) = $diff( array( $a_clean, $b_clean ) );
$check( 'V1.1 a clean BUILD replay exits 0 — the swap is attested and nothing moved', 0 === $status, "exit {$status}" );

$a_dep = $write( 'v1-dep-a.jsonl', array( $run(), $render( $TAG, 'body copy' ), $env( 'envAAAAAAAAA' ) ) );
$b_dep = $write( 'v1-dep-b.jsonl', array( $run(), $render( $TAG, 'body copy' ), $env( 'envBBBBBBBBB' ) ) );

list( $status ) = $diff( array( $a_dep, $b_dep, '--dependency-replay' ) );
$check( 'V1.2 a clean DEPENDENCY replay exits 0 — build held fixed, environment moved', 0 === $status, "exit {$status}" );

$b_moved = $write( 'v1-moved.jsonl', array( $run( array( 'source_commit' => 'bbbbbbbbbbbb', 'source_digest' => 'digB' ) ), $render( $TAG, 'body copy CHANGED' ) ) );

list( $status, $out ) = $diff( array( $a_clean, $b_moved ) );
$check( 'V1.3 renders that MOVED exit 1 — the finding a run exists to produce', 1 === $status, "exit {$status}" );
$check( 'V1.4 ...and say so, so 1 is not read as a gate finding', false !== strpos( $out, 'Rendered output MOVED' ) );

/* =========================================================================
 * §V2 — every attestation, driven to its failure
 *
 * ONE CASE PER ATTESTATION, each overriding exactly the field its check is about. The
 * expectation is always **exit 3**: none of these is a statement about rendered output, and
 * the whole point of the status split is that a caller can tell that without parsing prose.
 * ====================================================================== */

$cases = array();

// --- build identity, the build replay --------------------------------------
$cases['V2.1 same version and NO build identity recorded'] = array(
	array(
		$write( 'v2-1-a.jsonl', array( $run( array( 'source_commit' => null, 'source_digest' => null ) ), $render( $TAG, 'x' ) ) ),
		$write( 'v2-1-b.jsonl', array( $run( array( 'source_commit' => null, 'source_digest' => null ) ), $render( $TAG, 'x' ) ) ),
	),
	'NO BUILD IDENTITY RECORDED',
);

$cases['V2.2 both sides rendered the SAME build — the swap did not happen'] = array(
	array(
		$write( 'v2-2-a.jsonl', array( $run(), $render( $TAG, 'x' ) ) ),
		$write( 'v2-2-b.jsonl', array( $run(), $render( $TAG, 'x' ) ) ),
	),
	'SAME BUILD',
);

$cases['V2.3 an artifact spanning MORE THAN ONE plugin version'] = array(
	array(
		$write(
			'v2-3-a.jsonl',
			array(
				$run(),
				$run( array( 'url' => '/b/', 'plugin_version' => '1.18.0' ) ),
				$render( $TAG, 'x' ),
				$render( $TAG, 'x', '/b/' ),
			)
		),
		$write(
			'v2-3-b.jsonl',
			array(
				$run( array( 'source_commit' => 'bbbbbbbbbbbb', 'source_digest' => 'digB' ) ),
				$run( array( 'url' => '/b/', 'source_commit' => 'bbbbbbbbbbbb', 'source_digest' => 'digB' ) ),
				$render( $TAG, 'x' ),
				$render( $TAG, 'x', '/b/' ),
			)
		),
	),
	'MORE THAN ONE plugin version',
);

// --- build + env identity, the dependency replay ---------------------------
// THE 2026-08-29 FAIL-OPEN. Before d12a1b3 this pair exited 0 with GATE HELD.
$cases['V2.4 dependency mode: an UNRECORDED build identity is not a held-fixed one'] = array(
	array(
		$write( 'v2-4-a.jsonl', array( $run( array( 'source_commit' => null, 'source_digest' => null ) ), $render( $TAG, 'x' ), $env( 'envAAAAAAAAA' ) ) ),
		$write( 'v2-4-b.jsonl', array( $run( array( 'source_commit' => null, 'source_digest' => null ) ), $render( $TAG, 'x' ), $env( 'envBBBBBBBBB' ) ) ),
		'--dependency-replay',
	),
	'NO BUILD IDENTITY RECORDED',
);

$cases['V2.5 dependency mode: no env row at all is UNATTESTABLE, never attested'] = array(
	array(
		$write( 'v2-5-a.jsonl', array( $run(), $render( $TAG, 'x' ) ) ),
		$write( 'v2-5-b.jsonl', array( $run(), $render( $TAG, 'x' ) ) ),
		'--dependency-replay',
	),
	'NO RECORDED DEPENDENCY ENVIRONMENT',
);

$cases['V2.6 dependency mode: an UNMOVED environment — the swap did not happen'] = array(
	array(
		$write( 'v2-6-a.jsonl', array( $run(), $render( $TAG, 'x' ), $env( 'envSAMEAAAAA' ) ) ),
		$write( 'v2-6-b.jsonl', array( $run(), $render( $TAG, 'x' ), $env( 'envSAMEAAAAA' ) ) ),
		'--dependency-replay',
	),
	'SAME DEPENDENCY ENVIRONMENT',
);

$cases['V2.7 dependency mode: OUR build moved too, so nothing is attributable'] = array(
	array(
		$write( 'v2-7-a.jsonl', array( $run(), $render( $TAG, 'x' ), $env( 'envAAAAAAAAA' ) ) ),
		$write( 'v2-7-b.jsonl', array( $run( array( 'source_commit' => 'bbbbbbbbbbbb', 'source_digest' => 'digB' ) ), $render( $TAG, 'x' ), $env( 'envBBBBBBBBB' ) ) ),
		'--dependency-replay',
	),
	'OUR BUILD MOVED TOO',
);

$map_file = $write( 'v2-map.jsonl', array( array( 'old' => $TAG, 'new' => '{{text src:related_post|ref:body}}' ) ) );

$cases['V2.8 dependency mode with --map: two different experiments in one run'] = array(
	array(
		$write( 'v2-8-a.jsonl', array( $run(), $render( $TAG, 'x' ), $env( 'envAAAAAAAAA' ) ) ),
		$write( 'v2-8-b.jsonl', array( $run(), $render( $TAG, 'x' ), $env( 'envBBBBBBBBB' ) ) ),
		'--dependency-replay',
		'--map=' . $map_file,
	),
	'different replays',
);

// --- corpus identity -------------------------------------------------------
$cases['V2.9 the two sides rendered DIFFERENT censuses'] = array(
	array(
		$write( 'v2-9-a.jsonl', array( $run(), $render( $TAG, 'x' ) ) ),
		$write( 'v2-9-b.jsonl', array( $run( array( 'source_commit' => 'bbbbbbbbbbbb', 'source_digest' => 'digB', 'census_digest' => 'cen2' ) ), $render( $TAG, 'x' ) ) ),
	),
	'DIFFERENT CENSUSES',
);

$census_file = $write( 'v2-census.jsonl', array( array( 'tag_string' => $TAG, 'url' => '/a/', 'attested' => true ) ) );

$cases['V2.10 the census SUPPLIED here is not the one that was rendered'] = array(
	array(
		$write( 'v2-10-a.jsonl', array( $run(), $render( $TAG, 'x' ) ) ),
		$write( 'v2-10-b.jsonl', array( $run( array( 'source_commit' => 'bbbbbbbbbbbb', 'source_digest' => 'digB' ) ), $render( $TAG, 'x' ) ) ),
		$census_file,
	),
	'IS NOT THE ONE THAT WAS RENDERED',
);

// --- comparability ---------------------------------------------------------
$cases['V2.11 the URL sets differ, so the two runs are not comparable'] = array(
	array(
		$write( 'v2-11-a.jsonl', array( $run(), $render( $TAG, 'x' ) ) ),
		$write( 'v2-11-b.jsonl', array( $run( array( 'url' => '/z/', 'source_commit' => 'bbbbbbbbbbbb', 'source_digest' => 'digB' ) ), $render( $TAG, 'x', '/z/' ) ) ),
	),
	'URL SETS DIFFER',
);

$cases['V2.12 a pair present on only one side, with no removal artifact to explain it'] = array(
	array(
		$write( 'v2-12-a.jsonl', array( $run(), $render( $TAG, 'x' ), $render( '{{email}}', 'a@b.test' ) ) ),
		$write( 'v2-12-b.jsonl', array( $run( array( 'source_commit' => 'bbbbbbbbbbbb', 'source_digest' => 'digB' ) ), $render( $TAG, 'x' ) ) ),
	),
	'present on only one side',
);

// --- non-vacuity -----------------------------------------------------------
$cases['V2.13 every tag on side A rendered EMPTY — nothing was exercised'] = array(
	array(
		$write( 'v2-13-a.jsonl', array( $run(), $render( $TAG, '' ) ) ),
		$write( 'v2-13-b.jsonl', array( $run( array( 'source_commit' => 'bbbbbbbbbbbb', 'source_digest' => 'digB' ) ), $render( $TAG, '' ) ) ),
	),
	'rendered EMPTY',
);

$cases['V2.14 the artifacts hold no overlapping renders at all'] = array(
	array(
		$write( 'v2-14-a.jsonl', array( $run(), $render( '{{title}}', 'A title' ) ) ),
		$write( 'v2-14-b.jsonl', array( $run( array( 'source_commit' => 'bbbbbbbbbbbb', 'source_digest' => 'digB' ) ), $render( '{{email}}', 'a@b.test' ) ) ),
	),
	'nothing was compared',
);

foreach ( $cases as $label => $case ) {
	list( $args, $signature ) = $case;
	list( $status, $out )     = $diff( $args );

	$check( $label . ' → exit 3', 3 === $status, "exit {$status}" );
	$check( $label . ' → names it', false !== strpos( $out, $signature ), "expected to see: {$signature}" );
}

/* =========================================================================
 * §V3 — THE CENSUS
 *
 * The half that makes this a guard rather than fourteen more cases. Every fatal message the
 * two shipped files can emit is scanned out of their SOURCE and matched against what the runs
 * above actually provoked. A check, a mode or a message added later is therefore covered by a
 * test nobody remembered to update — and if it is genuinely unreachable from a fixture, that
 * is a finding about the check, not about this file.
 *
 * IT MUST NOT BE ABLE TO PASS BY FINDING NOTHING. §V3.1 is the guard on the guard: a scan
 * that silently stopped matching would otherwise report full coverage over an empty set,
 * which is the same shape as every failure this file exists to catch.
 * ====================================================================== */

/**
 * The longest literal run in a message, ignoring anything a placeholder will replace.
 *
 * SIGNATURES ARE DERIVED, NOT TAKEN FROM THE HEAD. `'[X] %d (url, tag) pair(s) present on only
 * one side.'` starts with a placeholder, so a head-of-string signature would be empty and the
 * message would drop silently out of the census — a hole in the guard, in the guard's own
 * shape. Splitting on the placeholder and keeping the longest fragment gives every message a
 * stable handle regardless of where its values sit.
 */
$signature_of = static function ( $text ) {
	$best = '';
	foreach ( preg_split( '/%[-+ 0#\']*[0-9]*(?:\.[0-9]+)?[a-zA-Z]/', $text ) as $fragment ) {
		$fragment = trim( $fragment );
		if ( strlen( $fragment ) > strlen( $best ) ) {
			$best = $fragment;
		}
	}

	return $best;
};

$declared = array();

// `[X] …` STRING LITERALS in the differ, and only those. An earlier pass matched on the token
// alone and picked up a code COMMENT that mentions `[X]` lines — a message the file cannot
// emit, reported as coverage it did not have.
if ( preg_match_all( '/[\'"]\[X\]\s+([^\'"]{12,140})/', (string) file_get_contents( $differ ), $m ) ) {
	foreach ( $m[1] as $text ) {
		$declared[] = $signature_of( $text );
	}
}

// Fatal findings in the rules file carry their text in a `'message' =>` beside `'fatal' => true`.
// Read structurally rather than by pattern: an informational message must never be counted as
// a fatal one, and there is exactly one of those today.
$rules_src = (string) file_get_contents( $rules );

if ( preg_match_all( "/'fatal'\s*=>\s*true,\s*'message'\s*=>\s*(?:sprintf\(\s*)?'((?:[^'\\\\]|\\\\.){12,140})/", $rules_src, $m ) ) {
	foreach ( $m[1] as $text ) {
		$declared[] = $signature_of( str_replace( "\\'", "'", $text ) );
	}
}

$declared = array_values(
	array_unique(
		array_filter(
			$declared,
			static function ( $text ) {
				// A signature too short to be distinctive would match half the output and
				// report coverage it does not have.
				return strlen( $text ) >= 12;
			}
		)
	)
);

$check(
	'V3.1 the source scan found fatal messages at all — a scan that quietly matched nothing would report full coverage over an empty set',
	count( $declared ) >= 10,
	count( $declared ) . ' declared'
);

$unprovoked = array();

foreach ( $declared as $text ) {
	$hit = false;
	foreach ( $seen_messages as $seen ) {
		// Compare on a leading run of the declared text: the emitted line carries interpolated
		// values the source cannot know.
		$head = substr( $text, 0, 24 );
		if ( '' !== $head && false !== strpos( $seen, $head ) ) {
			$hit = true;
			break;
		}
	}
	if ( ! $hit ) {
		$unprovoked[] = $text;
	}
}

$check(
	'V3.2 every fatal message the two files can emit was provoked by a case above',
	array() === $unprovoked,
	$unprovoked ? "never reached: \n        - " . implode( "\n        - ", array_map( static function ( $t ) {
		return substr( $t, 0, 60 );
	}, $unprovoked ) ) : ''
);

$check(
	'V3.3 ...and the runs above emitted fatal lines at all, so V3.2 cannot pass on an empty comparison',
	count( $seen_messages ) >= 10,
	count( $seen_messages ) . ' emitted'
);

echo "\n";
if ( $fail ) {
	echo "REPLAY VACUITY TEST FAILED ({$fail} of {$total})\n";
	exit( 1 );
}
echo "REPLAY VACUITY TEST PASSED ({$total} assertions)\n";
exit( 0 );
