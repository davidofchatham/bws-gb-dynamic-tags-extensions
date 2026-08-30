<?php
/**
 * BWS_USE_STRIPPED_DEFAULTS — the map, its leaves, and every read site that asserts it.
 *
 * Registration blanks the first value of each `use` enum so the saved tag string never
 * carries it (bws_prepare_registration_options), and every read site recovers it with
 * `?? '<value>'`. BOTH HALVES ARE DELIBERATE AND NEITHER IS UNDER TEST HERE. What was
 * missing is an owner for the VALUE: it was asserted at some twenty read sites and
 * derived at one. BWS_USE_STRIPPED_DEFAULTS is that owner, and a map nothing checks is
 * just a twenty-first assertion — so this file is what makes it authoritative.
 *
 * §1 pins the map against the three field-option LEAVES (base-shared.php), which are what
 * registration actually reads, so the map cannot drift from the enums it describes.
 *
 * §2 is a CENSUS of includes/, not a case list: it finds every `use`-default literal in
 * the tree and checks it against the map, so a read site added later is covered by a check
 * nobody wrote. Comments are stripped through token_get_all() rather than by regex —
 * four of the tree's nineteen `?? 'key'` occurrences are prose inside docblocks, and one
 * of those lines matches both patterns at once.
 *
 * §4 holds the shapes that were CONVERTED away. Six read sites stated the canon in forms
 * no single pattern could see — a tag→default ternary, two inline tag=>default array maps,
 * a paired equality in a display path — and §2 is a one-pattern census only for as long as
 * they stay converted. Their return is what §4 fails on.
 *
 * WHAT THIS CANNOT PROVE, and the rules section says so too: the census reads a literal,
 * not the tag it belongs to. `?? 'key'` inside a content-shaped function is a wrong value
 * this file will pass, because nothing on that line names a tag. §1 is what makes the
 * VALUES right; per-SITE correctness rests on the render harnesses and the tag matrices.
 *
 * Run:  php tools/test/use-stripped-default-test.php
 * Exit 0 = pass, 1 = fail.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

$root = dirname( __DIR__, 2 );

if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}

require $root . '/includes/helpers/registration-helpers.php';
require $root . '/includes/tags/base-shared.php';

$failures = 0;
$count    = 0;

function ok( string $label ): void {
	global $count;
	$count++;
	echo "  ok   {$label}\n";
}

function fail( string $label, $expected, $actual ): void {
	global $failures, $count;
	$count++;
	$failures++;
	echo "  FAIL {$label}\n";
	echo "       expected: " . json_encode( $expected ) . "\n";
	echo "       actual:   " . json_encode( $actual ) . "\n";
}

function assert_same( string $label, $expected, $actual ): void {
	if ( $expected === $actual ) {
		ok( $label );
		return;
	}
	fail( $label, $expected, $actual );
}

/**
 * A file's source with every comment blanked, line numbers preserved.
 *
 * A comment's newlines are kept so a match's line number still points at the real line.
 * This is the false-positive control for §2: `includes/tags/base-tags.php` carries a
 * docblock line reading "the per-callback `?? 'key'` / `?? 'content'` defaults", which a
 * line regex counts as two assertions that do not exist.
 */
function bws_strip_php_comments( string $src ): string {
	$out = '';
	foreach ( token_get_all( $src ) as $tok ) {
		if ( is_array( $tok ) ) {
			if ( T_COMMENT === $tok[0] || T_DOC_COMMENT === $tok[0] ) {
				$out .= str_repeat( "\n", substr_count( $tok[1], "\n" ) );
				continue;
			}
			$out .= $tok[1];
			continue;
		}
		$out .= $tok;
	}
	return $out;
}

/** Every .php file under includes/, relative to the repo root. */
function bws_include_files( string $root ): array {
	$out = array();
	$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/includes' ) );
	foreach ( $it as $f ) {
		if ( $f->isFile() && 'php' === strtolower( $f->getExtension() ) ) {
			$out[] = str_replace( '\\', '/', substr( $f->getPathname(), strlen( $root ) + 1 ) );
		}
	}
	sort( $out );
	return $out;
}

echo "\n§1 — the map against the field-option leaves\n";

// The leaves are what registration reads; the map describes their first values. Keyed by
// tag so a leaf added without a row (or a row without a leaf) is named, not just counted.
$leaves = array(
	'text'    => bws_get_text_field_options(),
	'content' => bws_get_content_field_options(),
	'image'   => bws_get_image_field_options(),
);

$from_leaves = array();
foreach ( $leaves as $tag => $leaf ) {
	$use = $leaf['use'] ?? array();
	// A leaf whose `use` is NOT strip-marked has no stripped default to state, and the
	// map must not claim one for it — so the marker is part of what qualifies a row.
	if ( empty( $use['_strip_default'] ) || ! isset( $use['options'][0]['value'] ) ) {
		continue;
	}
	$from_leaves[ $tag ] = (string) $use['options'][0]['value'];
}

assert_same(
	'the map IS the leaves\' first values — same tags, same order, same values',
	$from_leaves,
	BWS_USE_STRIPPED_DEFAULTS
);

foreach ( $leaves as $tag => $leaf ) {
	assert_same(
		"{$tag}: bws_use_stripped_default() answers the leaf's first value",
		$from_leaves[ $tag ] ?? '',
		bws_use_stripped_default( $tag )
	);
}

// Absence is a STATEMENT, not a gap: a tag with no `use` enum has no read axis, and a
// dispatcher asking for its default must get '' rather than a plausible 'key'. This is
// the assertion that makes bws_site_resolve_value's title/permalink arms correct.
foreach ( array( 'title', 'permalink', 'email', 'phone', 'datetime_single', 'table' ) as $tag ) {
	assert_same( "{$tag}: no read axis, so no default", '', bws_use_stripped_default( $tag ) );
}

echo "\n§2 — census: every `use`-default literal in includes/\n";

// The one shape §4 keeps the tree in: a `use`-ish subscript recovered with `??` and a
// literal. Covers `$options['use']`, `( $options['use'] ?? 'key' )` inline in a
// comparison, `$col['use']`, and `$options[ "{$n}-use" ]`.
$pattern = '/\[\s*[^\]\[]*use[^\]\[]*\]\s*\?\?\s*\'([^\']*)\'/i';

// `{{table}}`'s per-column `{N}-use` is its OWN enum, registered in table-tags.php, and it
// is deliberately NOT in the map: the option is being replaced by a source chain ending in
// a fanning step, so enrolling it now would tie a shipped map to a surface on its way out.
// Excluded by FILE, with the count pinned — a third site appearing in there is a decision
// someone should make on purpose, not a row that quietly joins the census.
$excluded       = array( 'includes/tags/table-tags.php' => 2 );
$excluded_found = array();

$sites = array();
foreach ( bws_include_files( $root ) as $rel ) {
	$src   = bws_strip_php_comments( (string) file_get_contents( $root . '/' . $rel ) );
	$lines = explode( "\n", $src );
	foreach ( $lines as $i => $line ) {
		if ( ! preg_match_all( $pattern, $line, $m, PREG_SET_ORDER ) ) {
			continue;
		}
		foreach ( $m as $hit ) {
			// An empty literal asserts no default — it spells "absent", which is a
			// legitimate read and is not this census's subject.
			if ( '' === $hit[1] ) {
				continue;
			}
			if ( isset( $excluded[ $rel ] ) ) {
				$excluded_found[ $rel ] = ( $excluded_found[ $rel ] ?? 0 ) + 1;
				continue;
			}
			$sites[] = array( 'file' => $rel, 'line' => $i + 1, 'value' => $hit[1] );
		}
	}
}

$values = array_values( BWS_USE_STRIPPED_DEFAULTS );

// NON-VACUITY FIRST. A census that finds nothing passes every check below it, and the
// pattern going stale is exactly how that happens. The floor is deliberately well under
// the fifteen sites present, so an ordinary conversion does not trip it.
assert_same(
	'the census found read sites at all (pattern still matches the tree)',
	true,
	count( $sites ) >= 8
);

$bad = array();
foreach ( $sites as $s ) {
	if ( ! in_array( $s['value'], $values, true ) ) {
		$bad[] = "{$s['file']}:{$s['line']} => '{$s['value']}'";
	}
}
assert_same( 'every read site recovers a value the map states', array(), $bad );

// No orphan rows: a map row nothing recovers is either a dead tag or a wrong value, and
// both are worth naming. Read against the census, so it fails the moment a tag's last
// read site is converted away without the row going with it.
$seen   = array_values( array_unique( array_column( $sites, 'value' ) ) );
$unused = array_values( array_diff( $values, $seen ) );
assert_same( 'every map value is recovered somewhere in includes/', array(), $unused );

foreach ( $excluded as $rel => $expected ) {
	assert_same(
		"excluded by design: {$rel} still has exactly {$expected} own-enum read sites",
		$expected,
		$excluded_found[ $rel ] ?? 0
	);
}

echo "\n§3 — comments are stripped, not matched\n";

// The control for §2's false positives, on a real line rather than an invented one:
// slot-fold.php's header and its parse-side comment both QUOTE the dispatch they mirror,
// in the exact shape §2 matches. Stripped, they assert nothing; unstripped, they are two
// read sites that do not exist.
$prose = " *   mirrors the shipped \$use = \$options['use'] ?? 'key' dispatch, so no tag that\n";
assert_same(
	'a docblock line quoting the dispatch contributes no site',
	0,
	preg_match_all( $pattern, bws_strip_php_comments( "<?php\n/**\n" . $prose . " */\n" ), $m )
);
assert_same(
	'...and the same line UNSTRIPPED would have been counted (the control is doing work)',
	true,
	preg_match_all( $pattern, $prose, $m ) > 0
);
// The quoting comments are really there — if they were ever reworded out, the control
// above would still pass on its own copy while the risk it models had gone.
assert_same(
	'slot-fold.php still carries the comments that motivate the stripping',
	true,
	substr_count( (string) file_get_contents( $root . '/includes/helpers/slot-fold.php' ), "\$options['use'] ?? 'key'" ) >= 2
);

echo "\n§4 — the converted shapes stay converted\n";

// §2 is a one-pattern census only because these six shapes were rewritten. Each states the
// canon in a form the pattern cannot see, so a returning one is invisible drift rather than
// a failing check. Named individually: a count would say "something came back".
$retired = array(
	'tag-ternary'    => '/\(\s*\'content\'\s*===\s*\$\w+\s*\)\s*\?\s*\'content\'\s*:\s*\'key\'/',
	'inline-map'     => '/\'text\'\s*=>\s*\'key\'\s*,\s*\'image\'\s*=>\s*\'key\'/',
	'empty-ternary'  => '/\'\'\s*===\s*\$use\s*\?\s*\'(?:key|content)\'/',
);
foreach ( $retired as $label => $re ) {
	$hits = array();
	foreach ( bws_include_files( $root ) as $rel ) {
		$src   = bws_strip_php_comments( (string) file_get_contents( $root . '/' . $rel ) );
		foreach ( explode( "\n", $src ) as $i => $line ) {
			if ( preg_match( $re, $line ) ) {
				$hits[] = "{$rel}:" . ( $i + 1 );
			}
		}
	}
	assert_same( "{$label} has not returned", array(), $hits );
}

// And the patterns above must be able to fail — a typo'd regex that matches nothing
// passes §4 forever while the shapes creep back.
foreach ( array(
	'tag-ternary'   => "\$d = ( 'content' === \$tag ) ? 'content' : 'key';",
	'inline-map'    => "\$u = array( 'text' => 'key', 'image' => 'key', 'content' => 'content' );",
	'empty-ternary' => "'use' => '' === \$use ? 'key' : \$use,",
) as $label => $sample ) {
	assert_same( "…and the {$label} pattern still recognizes one", 1, preg_match( $retired[ $label ], $sample ) );
}

echo "\n";
if ( $failures ) {
	echo "FAILED: {$failures} of {$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
