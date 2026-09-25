<?php
/**
 * BWS_USE_STRIPPED_DEFAULTS — the map, its leaves, and the one read rule over them.
 *
 * Registration blanks the first value of each `use` enum so the saved tag string never
 * carries it (bws_prepare_registration_options). Read sites used to recover it with
 * `?? '<value>'`, at some twenty sites; since FW-142 every one asks bws_use_effective()
 * instead, which also answers for a `use` IMPLIED by a field token (`{{content key:foo}}`
 * is the keyed read). The map is the VALUE's owner, the helper is the RULE's.
 *
 * §1 pins the map against the three field-option LEAVES (base-shared.php), which are what
 * registration actually reads, so the map cannot drift from the enums it describes.
 *
 * §2 is a CENSUS of includes/, not a case list. It holds the literal-recovery shape
 * RETIRED (a read site added later with its own `?? 'key'` fails by name, because it
 * would state step 3 of the rule and skip step 2), and it reads every helper call's tag
 * argument against the map. Comments are stripped through token_get_all() rather than by
 * regex — docblocks still quote the retired shape as prose.
 *
 * §4 holds the other shapes that were CONVERTED away before FW-142: forms that stated the
 * canon where no `??` pattern could see them. A `??` whose right operand is a VARIABLE is
 * not among them — it is how a site fed by the map spells itself.
 *
 * §5 pins bws_use_effective() itself: implied, explicit-wins, and both empty cases, per tag.
 *
 * WHAT THIS CANNOT PROVE: a helper call names a tag, and §2 checks that tag is a map row,
 * not that it is the RIGHT row for the function it sits in. Per-SITE correctness rests on
 * the render harnesses and the tag matrices.
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
 * This is the false-positive control for §2: bws_use_effective()'s own PHPDoc quotes the
 * retired `$options['use'] ?? 'key'` shape, which a line regex counts as a read site that
 * does not exist.
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

echo "\n§2 — census: no read site recovers the default itself; every helper call names a row\n";

// The RETIRED shape: a `use`-ish subscript recovered with `??` and a literal. Covers
// `$options['use']`, `( $options['use'] ?? 'key' )` inline in a comparison, `$col['use']`,
// and `$options[ "{$n}-use" ]`. Retired by FW-142 — such a site states the stripped
// default but not a `use` implied by a field token, so it reads `{{content key:foo}}` as
// the analog while every other site reads it as the keyed read.
$pattern = '/\[\s*[^\]\[]*use[^\]\[]*\]\s*\?\?\s*\'([^\']*)\'/i';

// `{{table}}`'s per-column `{N}-use` is its OWN enum, registered in table-tags.php, and it
// is deliberately NOT in the map: the option is being replaced by a source chain ending in
// a fanning step, so enrolling it now would tie a shipped map to a surface on its way out.
// Excluded by FILE, with the count pinned — a third site appearing in there is a decision
// someone should make on purpose, not a row that quietly joins the census. The pinned
// count is also this census's NON-VACUITY check: a pattern gone stale finds 0 there too.
$excluded       = array( 'includes/tags/table-tags.php' => 2 );
$excluded_found = array();

// Every call of the helper with a LITERAL tag; a variable tag (the site dispatcher, the
// preview) is fed by a caller and is not this census's subject.
$call_pattern = '/bws_use_effective\(\s*\'([^\']*)\'/';

$sites = array();
$calls = array();
foreach ( bws_include_files( $root ) as $rel ) {
	$src   = bws_strip_php_comments( (string) file_get_contents( $root . '/' . $rel ) );
	$lines = explode( "\n", $src );
	foreach ( $lines as $i => $line ) {
		if ( preg_match_all( $call_pattern, $line, $cm, PREG_SET_ORDER ) ) {
			foreach ( $cm as $hit ) {
				$calls[] = array( 'file' => $rel, 'line' => $i + 1, 'tag' => $hit[1] );
			}
		}
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
			$sites[] = "{$rel}:" . ( $i + 1 ) . " => '{$hit[1]}'";
		}
	}
}

foreach ( $excluded as $rel => $expected ) {
	assert_same(
		"excluded by design: {$rel} still has exactly {$expected} own-enum read sites",
		$expected,
		$excluded_found[ $rel ] ?? 0
	);
}

assert_same( 'no read site recovers a `use` default with its own literal', array(), $sites );

// NON-VACUITY for the call census. The floor is deliberately well under the sixteen
// literal-tag calls present, so an ordinary refactor does not trip it.
assert_same( 'the call census found helper calls at all', true, count( $calls ) >= 8 );

$bad = array();
foreach ( $calls as $c ) {
	if ( ! isset( BWS_USE_STRIPPED_DEFAULTS[ $c['tag'] ] ) ) {
		$bad[] = "{$c['file']}:{$c['line']} => '{$c['tag']}'";
	}
}
assert_same( 'every literal-tag helper call names a tag the map has a row for', array(), $bad );

// No orphan rows: a map row no read site asks about is a dead tag worth naming.
$unused = array_values( array_diff( array_keys( BWS_USE_STRIPPED_DEFAULTS ), array_column( $calls, 'tag' ) ) );
assert_same( 'every map row is asked about somewhere in includes/', array(), $unused );

echo "\n§3 — comments are stripped, not matched\n";

// The control for §2's false positives: docblocks still QUOTE the retired shape (the
// helper's own PHPDoc names what it replaced). Stripped, a quote asserts nothing;
// unstripped, it is a read site that does not exist.
$prose = " * Replaces the per-site `\$options['use'] ?? 'key'` / `?? 'content'` literals, which could\n";
assert_same(
	'a docblock line quoting the retired shape contributes no site',
	0,
	preg_match_all( $pattern, bws_strip_php_comments( "<?php\n/**\n" . $prose . " */\n" ), $m )
);
assert_same(
	'...and the same line UNSTRIPPED would have been counted (the control is doing work)',
	true,
	preg_match_all( $pattern, $prose, $m ) > 0
);
// The quoting comment is really there — if it were ever reworded out, the control
// above would still pass on its own copy while the risk it models had gone.
assert_same(
	'registration-helpers.php still carries the comment that motivates the stripping',
	true,
	substr_count( (string) file_get_contents( $root . '/includes/helpers/registration-helpers.php' ), "\$options['use'] ?? 'key'" ) >= 1
);

echo "\n§4 — the converted shapes stay converted\n";

// §2 is a one-pattern census only because these six shapes were rewritten. Each states the
// canon in a form the pattern cannot see, so a returning one is invisible drift rather than
// a failing check. Named individually: a count would say "something came back".
$retired = array(
	'tag-ternary'     => '/\(\s*\'content\'\s*===\s*\$\w+\s*\)\s*\?\s*\'content\'\s*:\s*\'key\'/',
	'inline-map'      => '/\'text\'\s*=>\s*\'key\'\s*,\s*\'image\'\s*=>\s*\'key\'/',
	'empty-ternary'   => '/\'\'\s*===\s*\$use\s*\?\s*\'(?:key|content)\'/',
	// A CARRY SEED IS A CANON ASSERTION IN A SHAPE §2 CANNOT SEE. Since the fold seam
	// stopped writing a read default of its own, what a read-less slot resolves to IS
	// this argument — so a literal here states a tag's stripped default just as much as
	// a `??` does, and states it somewhere no `use` appears on the line. Found by
	// mutation: seeding {{join}} with 'content' left the whole suite green.
	'literal-carry-seed' => '/bws_fold_empty_carry\(\s*\'/',
);

// The paired equality is DERIVED from the map, not hand-written, and that is what makes it
// precise enough to be safe. The retired shape tested a template against its OWN default
// ("is this slot at the template default?"); a mode test that happens to pair a tag with
// some other enum value is ordinary code — preview-helpers' key-required block pairs
// `content` with `key`, and a hand-written alternation flagged it. Building the pattern
// from BWS_USE_STRIPPED_DEFAULTS means only the (tag, ITS default) pairing matches, and a
// map edit moves the pattern with it.
$paired = array();
foreach ( BWS_USE_STRIPPED_DEFAULTS as $tag => $default ) {
	$paired[] = preg_quote( "'{$tag}'", '/' ) . '\s*===\s*\$\w+\s*&&\s*' . preg_quote( "'{$default}'", '/' ) . '\s*===';
}
$retired['paired-equality'] = '/(?:' . implode( '|', $paired ) . ')/';
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
	'tag-ternary'     => "\$d = ( 'content' === \$tag ) ? 'content' : 'key';",
	'inline-map'      => "\$u = array( 'text' => 'key', 'image' => 'key', 'content' => 'content' );",
	'empty-ternary'   => "'use' => '' === \$use ? 'key' : \$use,",
	'paired-equality' => "\$d = ( 'content' === \$base_template && 'content' === \$slot['use'] );",
	'literal-carry-seed' => "\$carry = bws_fold_empty_carry( 'content' );",
) as $label => $sample ) {
	assert_same( "…and the {$label} pattern still recognizes one", 1, preg_match( $retired[ $label ], $sample ) );
}

echo "\n§5 — bws_use_effective(): the read rule (FW-142)\n";

// Per tag, because the implied mode only MOVES a render where the stripped default is an
// analog (content); on text/image it agrees with the default, and that agreement is
// itself the "no behavior change" claim for those two.
$explicit = array( 'text' => 'title', 'content' => 'excerpt', 'image' => 'featured' );
foreach ( BWS_USE_STRIPPED_DEFAULTS as $tag => $default ) {
	assert_same( "{$tag}: nothing set → the stripped default", $default, bws_use_effective( $tag, array() ) );
	assert_same( "{$tag}: key alone → the keyed read", 'key', bws_use_effective( $tag, array( 'key' => 'foo' ) ) );
	assert_same( "{$tag}: use:key|key → key (the old redundant wire)", 'key', bws_use_effective( $tag, array( 'use' => 'key', 'key' => 'foo' ) ) );
	assert_same(
		"{$tag}: explicit use:{$explicit[ $tag ]} wins over a stale key",
		$explicit[ $tag ],
		bws_use_effective( $tag, array( 'use' => $explicit[ $tag ], 'key' => 'foo' ) )
	);
	assert_same( "{$tag}: use '' counts as absent (key implies)", 'key', bws_use_effective( $tag, array( 'use' => '', 'key' => 'foo' ) ) );
	assert_same( "{$tag}: use '' and no key → the stripped default", $default, bws_use_effective( $tag, array( 'use' => '' ) ) );
	assert_same( "{$tag}: key '' counts as absent → the stripped default", $default, bws_use_effective( $tag, array( 'key' => '' ) ) );
	assert_same( "{$tag}: use:key with no key → key (keyed, pending)", 'key', bws_use_effective( $tag, array( 'use' => 'key' ) ) );
}

// No read axis → no inference. permalink ignores `key` by design; a mode here would be
// a read axis the tag does not have.
foreach ( array( 'title', 'permalink' ) as $tag ) {
	assert_same( "{$tag}: a key implies nothing on a tag with no read axis", '', bws_use_effective( $tag, array( 'key' => 'foo' ) ) );
}

echo "\n";
if ( $failures ) {
	echo "FAILED: {$failures} of {$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
