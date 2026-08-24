<?php
/**
 * Pin the ROOT that `replay-tags.php` fingerprints as "our build".
 *
 * WHY THIS EXISTS. `replay-tags.php` records a build identity into every replay artifact —
 * the repo's HEAD commit plus a stat-only digest of its PHP files — and `diff-replays.php`
 * refuses to trust an empty diff without it. That check exists because the BUILD REPLAY's
 * pass condition IS an empty diff: a run where nobody actually swapped the build diffs empty
 * too, so the artifact has to prove the swap independently.
 *
 * A wrong root breaks that check by failing OPEN, which is invisible in every artifact a
 * reader is likely to look at. It happened: `72bb5f7` (2026-08-19) moved the script from
 * `tools/` into `tools/harvest-replay/` and left `dirname( __DIR__ )` behind, so `$root`
 * became `tools/` — no `.git` (commit null) and a digest over the 26 PHP files in `tools/`,
 * which cannot see `includes/` at all. The build could change with the digest fixed.
 *
 * WHAT THIS PINS, AND WHY IT IS STATIC. The identity closure runs under `wp eval-file` and the
 * script executes a replay on load, so it cannot be required. So the expression is resolved
 * the way PHP would resolve it — count the `dirname()` wrappers, apply that many to the
 * script's real directory — and the RESULT is asserted against the repo root this harness
 * derives from its own location. Two files that disagree about where the root is are the
 * defect, whichever one moved.
 *
 * MUTATION-VERIFIED against the historical break: restoring `dirname( __DIR__ )` fails S3.2,
 * S4.1, S4.2 and S5.1 by name and exits 1. S5.2 passes under that mutation and is not one of
 * the guards — it states a property of the walk, not the regression.
 *
 * Pure: no WordPress, no network, no WP-CLI.
 *
 * Run: php tools/test/replay-source-identity-test.php
 *
 * @package BWS_Dynamic_Tags
 */

$fails = 0;
$check = function ( $name, $ok, $detail = '' ) use ( &$fails ) {
	if ( $ok ) {
		printf( "[PASS] %s\n", $name );
		return;
	}
	$fails++;
	printf( "[FAIL] %s%s\n", $name, '' !== $detail ? " — {$detail}" : '' );
};

$repo_root   = dirname( dirname( __DIR__ ) );
$script_dir  = $repo_root . '/tools/harvest-replay';
$script_path = $script_dir . '/replay-tags.php';

$src = is_readable( $script_path ) ? (string) file_get_contents( $script_path ) : '';

$check( 'S1.1 replay-tags.php is readable', '' !== $src, $script_path );

if ( '' === $src ) {
	echo "\nREPLAY SOURCE IDENTITY TEST FAILED (1)\n";
	exit( 1 );
}

/*
 * S2 — the call is still shaped the way this harness can read.
 *
 * A regex that silently matches nothing would report every assertion below as passing on an
 * expression it never found, which is the same fail-open the harness exists to catch. So the
 * parse itself is an assertion.
 */
$matched = preg_match( '/\$source\s*=\s*\$source_identity\(\s*(.+?)\s*\)\s*;/s', $src, $m );

$check(
	'S2.1 the source-identity call is present and parseable',
	1 === $matched,
	'no `$source = $source_identity( … );` found — if the call was restructured, this harness must be too'
);

$expr = 1 === $matched ? $m[1] : '';

/*
 * S3 — resolve the expression the way PHP would.
 *
 * Only `dirname()` nesting around `__DIR__` is understood. Anything else (a variable, a
 * constant, a literal path) is refused rather than guessed at: a harness that assumes it knows
 * what an unfamiliar expression means is how a wrong root gets blessed.
 */
$normalized = preg_replace( '/\s+/', '', $expr );
$depth      = 0;
$cursor     = $normalized;

while ( 0 === strpos( $cursor, 'dirname(' ) && ')' === substr( $cursor, -1 ) ) {
	$depth++;
	$cursor = substr( $cursor, strlen( 'dirname(' ), -1 );
}

$check(
	'S3.1 the expression is dirname()-nesting over __DIR__',
	'__DIR__' === $cursor,
	"innermost term is `{$cursor}` — this harness only resolves dirname() over __DIR__"
);

$resolved = $script_dir;
for ( $i = 0; $i < $depth; $i++ ) {
	$resolved = dirname( $resolved );
}

$check(
	'S3.2 the resolved root IS the repo root',
	realpath( $resolved ) === realpath( $repo_root ),
	sprintf( 'depth %d resolves to %s, repo root is %s', $depth, $resolved, $repo_root )
);

/*
 * S4 — the two properties the root exists to provide. These are what fail open when the root
 * is wrong, and neither is implied by the other: `tools/` had neither, but a subtree with a
 * `.git` and no `includes/` would pass S4.1 and still fingerprint nothing that matters.
 */
$git = $resolved . '/.git';

$check(
	'S4.1 the resolved root carries git state, so a commit is derivable',
	is_dir( $git ) || is_file( $git ),
	"no .git at {$resolved} — `commit` would be recorded as null"
);

$check(
	'S4.2 the resolved root contains includes/, the build being attested',
	is_dir( $resolved . '/includes' ),
	"no includes/ under {$resolved} — the digest cannot see the build it exists to fingerprint"
);

/*
 * S5 — the digest actually reaches the build.
 *
 * Mirrors the closure's own walk (PHP files, minus the same four excluded directory names) and
 * asserts a file under `includes/` lands in it. S4.2 proves the directory is inside the root;
 * this proves the FILTER does not exclude it — the two failures look identical in an artifact,
 * since both produce a digest that never moves when the build does.
 */
// Windows hands back both separators depending on how a path was built, so compare in one
// spelling. DIRECTORY_SEPARATOR rather than a literal, which keeps this file free of escapes.
$slashes = static function ( $path ) {
	return strtr( (string) $path, DIRECTORY_SEPARATOR, '/' );
};

$seen_includes = 0;
$total         = 0;

$it = new RecursiveIteratorIterator(
	new RecursiveCallbackFilterIterator(
		new RecursiveDirectoryIterator( $resolved, FilesystemIterator::SKIP_DOTS ),
		static function ( $file ) {
			return ! in_array( $file->getFilename(), array( '.git', 'libs', 'node_modules', 'vendor' ), true );
		}
	)
);

foreach ( $it as $file ) {
	if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
		continue;
	}
	$total++;
	if ( 0 === strpos( $slashes( $file->getPathname() ), $slashes( $resolved ) . '/includes/' ) ) {
		$seen_includes++;
	}
}

$check(
	'S5.1 the digest walk reaches PHP files under includes/',
	$seen_includes > 0,
	"walked {$total} PHP file(s), none under includes/"
);

$check(
	'S5.2 the walk covers the instrument tree as well as the build',
	$total > $seen_includes,
	sprintf( 'walked %d file(s), %d of them under includes/', $total, $seen_includes )
);

printf(
	"\nresolved root: %s (dirname depth %d, %d PHP files, %d under includes/)\n",
	$resolved,
	$depth,
	$total,
	$seen_includes
);

echo $fails
	? "\nREPLAY SOURCE IDENTITY TEST FAILED ({$fails})\n"
	: "\nREPLAY SOURCE IDENTITY TEST PASSED\n";

exit( $fails ? 1 : 0 );
