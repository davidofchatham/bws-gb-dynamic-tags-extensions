<?php
/**
 * Pure harness for the page-snapshot normalizer, its differ, and its page-set derivation.
 *
 * Loads the REAL file (tools/test/page-snapshots.php) rather than a transcribed copy — the
 * newer house convention, whose whole point is that a test-local copy of a rule is the drift
 * the extraction removed. That file's CLI block is guarded on being the ENTRY script, so
 * requiring it here runs nothing.
 *
 * WHY THIS HARNESS EXISTS AT ALL, given that the instrument's real acceptance test is
 * empirical (capture twice, diff must be empty): that test needs a served fixture site, and
 * it answers only "is the current rule set sufficient TODAY". It cannot say WHICH rule
 * carries a case, so deleting a rule that has gone quiet — because the dependency stopped
 * emitting that churn this month — passes it. Every rule below is therefore pinned to the
 * shape it exists for.
 *
 * THE ONE RULE WORTH NAMING TWICE is the `bws-` <style> exemption (§P3). It is the only
 * place the normalizer deliberately PRESERVES generated content, and it is preserved because
 * {{table}}'s update trigger says to confirm on the front end that its footer CSS prints
 * exactly once. A normalizer that swallowed it would answer that question "yes" forever,
 * and nothing else in the tree would notice.
 *
 * Run:  php tools/test/page-snapshot-normalize-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

require_once __DIR__ . '/page-snapshots.php';

$fail = 0;

$check = function ( $label, $ok, $detail = '' ) use ( &$fail ) {
	printf( "[%s] %s%s\n", $ok ? 'PASS' : 'FAIL', $label, '' !== $detail ? " — {$detail}" : '' );
	if ( ! $ok ) {
		$fail++;
	}
};

$norm = static function ( $html ) {
	return bws_page_snapshot_normalize( $html, 'https://testbed.test' );
};

/* =========================================================================
 * §P1 — cache-plugin footprints
 * ====================================================================== */

$check(
	'P1.1 the LiteSpeed footer comment (timestamped every render) is stripped',
	false === strpos( $norm( "<p>x</p>\n<!-- Page cached by LiteSpeed Cache 7.9 on 2026-08-24 11:21:14 -->" ), 'LiteSpeed' )
);

// The two captures differ ONLY in that timestamp, which is the shape that made this rule
// necessary. Comparing them to each other rather than to a literal is the point: what must
// hold is that they AGREE, not what they agree on.
$check(
	'P1.2 two renders differing only in the cache timestamp normalize equal',
	$norm( "<p>x</p>\n<!-- Page cached by LiteSpeed Cache 7.9 on 2026-08-24 11:21:14 -->" )
		=== $norm( "<p>x</p>\n<!-- Page cached by LiteSpeed Cache 7.9 on 2026-08-24 11:59:02 -->" )
);

/* =========================================================================
 * §P2/§P3 — generated <style> bodies, and the one exemption
 * ====================================================================== */

$third_party = '<style id="generateblocks-inline-css">.gb-a{color:red}</style>';
$out         = $norm( $third_party );

$check( 'P2.1 a third-party generated stylesheet loses its BODY', false === strpos( $out, 'color:red' ) );
$check( 'P2.2 ...but keeps its tag and id, so the block VANISHING is still a diff', false !== strpos( $out, 'id="generateblocks-inline-css"' ) );

$ours = '<style id="bws-table-inline-css">.bws-table{border:0}</style>';

$check(
	'P3.1 OUR stylesheet is preserved verbatim — {{table}}\'s footer CSS is an assertion surface, not churn',
	false !== strpos( $norm( $ours ), 'border:0' )
);
$check(
	'P3.2 the exemption is keyed on the id PREFIX, so a future bws-* block inherits it',
	false !== strpos( $norm( '<style id="bws-something-new-inline-css">.z{a:b}</style>' ), 'a:b' )
);
$check(
	'P3.3 ...and does not leak to an id merely CONTAINING "bws-"',
	false === strpos( $norm( '<style id="theme-bws-ish-css">.z{a:b}</style>' ), 'a:b' )
);

/* =========================================================================
 * §P1b — post-lifecycle timestamps
 *
 * `post_modified` moves every time the seeder touches a post, so without this rule the
 * documented `bin/seed.sh` reseed invalidates every committed baseline at once and the
 * instrument fails on its own maintenance.
 *
 * THE RULE IS NAMED-CARRIER-ONLY, and §P1b.4 is the case that says why: rendered
 * {{datetime_*}} output sits on these same pages and IS the signal. A generic date pattern
 * would eat it.
 * ====================================================================== */

$check(
	'P1b.1 article:modified_time is collapsed (post_modified moves on every reseed)',
	$norm( '<meta property="article:modified_time" content="2026-08-22T11:29:19-04:00">' )
		=== $norm( '<meta property="article:modified_time" content="2026-09-01T09:00:00-04:00">' )
);
$check(
	'P1b.2 article:published_time and og:updated_time go the same way',
	$norm( '<meta property="article:published_time" content="2026-08-22T11:29:19-04:00"><meta property="og:updated_time" content="2026-08-22T11:29:19-04:00">' )
		=== $norm( '<meta property="article:published_time" content="2020-01-01T00:00:00-04:00"><meta property="og:updated_time" content="2021-02-03T04:05:06-04:00">' )
);
$check(
	'P1b.3 the JSON-LD datePublished/dateModified pair is collapsed',
	$norm( '<script>{"datePublished":"2026-07-17T14:05:57-04:00","dateModified":"2026-08-22T11:29:19-04:00"}</script>' )
		=== $norm( '<script>{"datePublished":"2001-01-01T00:00:00-04:00","dateModified":"2002-02-02T00:00:00-04:00"}</script>' )
);
$check(
	'P1b.4 rendered {{datetime_*}} output is NOT collapsed — it is the signal, not chrome',
	$norm( '<p class="gb-text">D0.3: 2030-08-12 9:00 am</p>' ) !== $norm( '<p class="gb-text">D0.3: 2031-08-12 9:00 am</p>' )
);
$check(
	'P1b.5 ...including an ISO-shaped one a tag could legitimately render',
	$norm( '<p class="gb-text">D9: 2030-08-12T09:00:00-04:00</p>' ) !== $norm( '<p class="gb-text">D9: 2031-08-12T09:00:00-04:00</p>' )
);
$check(
	'P1b.6 the JSON-LD rule keeps the surrounding keys, so a structural change is still a diff',
	false !== strpos( $norm( '<script>{"datePublished":"2026-07-17T14:05:57-04:00","isPartOf":"x"}</script>' ), 'isPartOf' )
);

/* =========================================================================
 * §P4 — nonces
 *
 * These regenerate on a 12-hour tick, so without this rule a committed baseline goes stale
 * by the CLOCK rather than by anything having changed — the worst kind of red, because
 * re-running fixes it and teaches the operator that red means nothing.
 * ====================================================================== */

$check(
	'P4.1 a _wpnonce query arg is collapsed',
	$norm( '<a href="/x?_wpnonce=a1b2c3d4e5">n</a>' ) === $norm( '<a href="/x?_wpnonce=99887766ff">n</a>' )
);
$check(
	'P4.2 a JSON nonce field is collapsed',
	$norm( '<script>var s={"nonce":"a1b2c3d4e5"};</script>' ) === $norm( '<script>var s={"nonce":"5f4e3d2c1b"};</script>' )
);

/* =========================================================================
 * §P5 — cache-busting and asset-version query strings
 * ====================================================================== */

$check(
	'P5.1 the capture\'s own ?nocache= bust is collapsed (it differs on every single capture)',
	$norm( '<link href="/s.css?nocache=17771">' ) === $norm( '<link href="/s.css?nocache=48812">' )
);
$check(
	'P5.2 ?ver= is collapsed — it moves on every dependency upgrade, and env-versions.php reports that ONCE rather than as a hundred diff lines',
	$norm( '<link href="/s.css?ver=2.4.1">' ) === $norm( '<link href="/s.css?ver=2.5.0">' )
);
$check(
	'P5.3 ...and a ver= in the middle of a query string keeps the rest of it',
	false !== strpos( $norm( '<link href="/s.css?ver=1.0&amp;x=keep">' ), 'x=keep' )
);
// WordPress emits `&amp;` between query args inside an attribute, so a bare [?&] class
// reaches only the FIRST argument. Any asset carrying `ver` in second position would leak
// its dependency version into every diff — the exact noise P5.2 exists to keep out.
$check(
	'P5.4 a ver= behind an &amp;-encoded separator is collapsed too',
	$norm( '<link href="/s.css?x=1&amp;ver=2.4.1">' ) === $norm( '<link href="/s.css?x=1&amp;ver=9.9.9">' )
);
$check(
	'P5.5 ...and behind a numeric-entity separator',
	$norm( '<link href="/s.css?x=1&#038;ver=2.4.1">' ) === $norm( '<link href="/s.css?x=1&#038;ver=9.9.9">' )
);

/* =========================================================================
 * §P6 — absolute URLs
 *
 * The host is an operator's choice, not a property of the output. This is what lets a
 * baseline captured on the HOST diff clean against a comparison run from INSIDE the
 * container, which is exactly how the two entrypoints are used.
 * ====================================================================== */

$check(
	'P6.1 the configured base URL is stripped to root-relative',
	$norm( '<a href="https://testbed.test/matrix-post-meta/">x</a>' ) === $norm( '<a href="/matrix-post-meta/">x</a>' )
);
$check(
	'P6.2 a protocol-relative form of the same host is stripped too',
	$norm( '<img src="//testbed.test/a.png">' ) === $norm( '<img src="/a.png">' )
);
$check(
	'P6.3 a DIFFERENTLY-NAMED local env normalizes onto the same bytes',
	$norm( '<a href="https://other-box.test/p/">x</a>' ) === $norm( '<a href="/p/">x</a>' )
);
$check(
	'P6.4 a genuine external URL is NOT stripped — it is content, and a tag emitting one that changed is exactly what this instrument is for',
	false !== strpos( $norm( '<a href="https://example.com/ext">x</a>' ), 'https://example.com/ext' )
);

/* =========================================================================
 * §P7 — generated block ids
 * ====================================================================== */

$check(
	'P7.1 GB per-block class suffixes are collapsed (a reseed regenerates them)',
	$norm( '<figure class="gb-media-2e07464f">x</figure>' ) === $norm( '<figure class="gb-media-9911aabb">x</figure>' )
);
$check(
	'P7.2 ...but the KIND is preserved, so gb-media turning into gb-element is still a diff',
	$norm( '<figure class="gb-media-2e07464f">x</figure>' ) !== $norm( '<figure class="gb-element-2e07464f">x</figure>' )
);
$check(
	'P7.3 a core per-block element id is collapsed',
	$norm( '<div id="block-a1b2c3d4-e5f6">x</div>' ) === $norm( '<div id="block-99887766-5544">x</div>' )
);

/* =========================================================================
 * §P8/§P9 — antispambot()'s per-character coin flip
 *
 * THE LARGEST CHURN SOURCE ON THE FIXTURE SITE, and the one that cannot be reasoned to:
 * WordPress's antispambot() calls mt_rand() per CHARACTER to decide between a literal and a
 * numeric entity, so every {{email}} renders differently on every request. The markup looks
 * perfectly stable until two captures are diffed.
 * ====================================================================== */

$check(
	'P9.1 two antispambot encodings of ONE address normalize to the same bytes',
	$norm( '<a href="mailto:&#106;a&#110;&#101;&#064;&#101;&#120;a&#109;&#112;l&#101;.&#116;e&#115;&#116;">m</a>' )
		=== $norm( '<a href="mailto:jane&#064;&#101;x&#097;mpl&#101;&#046;t&#101;st">m</a>' )
);
$check(
	'P9.2 ...and they normalize to the LITERAL address, so a diff on one is readable',
	false !== strpos( $norm( '<a href="mailto:&#106;a&#110;&#101;&#064;&#101;&#120;a&#109;&#112;l&#101;.&#116;e&#115;&#116;">m</a>' ), 'mailto:jane@example.test' )
);
$check(
	'P9.3 a DIFFERENT address still differs after normalizing (the rule collapses spelling, not identity)',
	$norm( '<a href="mailto:&#106;ane&#064;example.test">m</a>' ) !== $norm( '<a href="mailto:&#106;ohn&#064;example.test">m</a>' )
);
$check(
	'P8.1 hex numeric references in the ASCII range decode too',
	$norm( '<p>&#x6a;&#x40;a</p>' ) === $norm( '<p>j@a</p>' )
);
$check(
	'P8.2 a NON-ASCII numeric reference is left alone (&#8211; is an en dash the page really contains)',
	false !== strpos( $norm( '<p>a &#8211; b</p>' ), '&#8211;' )
);
$check(
	'P8.3 the five markup-significant characters are left encoded — antispambot cannot emit them, and a decoded &#60; would make a diff line unreadable',
	false !== strpos( $norm( '<p>&#60;&#62;&#38;&#34;&#39;</p>' ), '&#60;&#62;&#38;&#34;&#39;' )
);
$check(
	'P8.4 a named entity is untouched',
	false !== strpos( $norm( '<p>a &amp; b</p>' ), '&amp;' )
);

/* =========================================================================
 * §P10 — line-ending and whitespace agnosticism
 *
 * The baseline is committed on Windows and compared from a Linux container, so a rule that
 * did not do this would report every page as wholly changed depending on who ran it.
 * ====================================================================== */

$check( 'P10.1 CRLF and LF input normalize identically', $norm( "<p>a</p>\r\n<p>b</p>" ) === $norm( "<p>a</p>\n<p>b</p>" ) );
$check( 'P10.2 trailing whitespace is dropped', $norm( "<p>a</p>   \n<p>b</p>" ) === $norm( "<p>a</p>\n<p>b</p>" ) );
$check( 'P10.3 blank-line runs collapse', $norm( "<p>a</p>\n\n\n\n<p>b</p>" ) === $norm( "<p>a</p>\n\n<p>b</p>" ) );
$check( 'P10.4 output always ends in exactly one newline', "\n" === substr( $norm( '<p>a</p>' ), -1 ) && "\n" !== substr( $norm( '<p>a</p>' ), -2, 1 ) );

/* =========================================================================
 * §P17 — the document head is NOT asserted
 *
 * A snapshot of rendered tag output has no business asserting the shape of WordPress's
 * `<head>`: on the fixture pages it is ~81 of ~700 lines, and every co-resident stylesheet
 * `<link>` lives there. Measured 2026-08-28, two inactive co-resident BWS plugins accounted
 * for a 252-line, ten-page diff carrying no tag output at all — so activating or deactivating
 * ANY unrelated plugin re-flowed every committed baseline at once.
 *
 * NARROWING THE CAPTURE, NOT SUPPRESSING THE LINES. Dropping non-`bws-` `<link>` and
 * `<style id>` lines outright would have masked the noise at the cost of §P2.2 — "a block
 * vanishing entirely is still a visible diff" — across the whole document. Removing the
 * REGION those lines live in costs that property only inside the head, which is the region we
 * have just said we do not assert.
 *
 * THE WHITELIST IS WHY THIS SECTION IS LONG. `<title>`, `meta name="description"` and
 * `og:description` are built from the post excerpt, so a `{{...}}` inside an excerpt renders
 * into them. No fixture page does that today and nothing forbids one; a surface we silently
 * stopped watching is the exact failure this instrument exists to prevent.
 * ====================================================================== */

$doc = static function ( $head, $body = '<p class="gb-text">T1: value</p>' ) {
	return "<!DOCTYPE html>\n<html lang=\"en-US\">\n<head>\n{$head}\n</head>\n<body>\n{$body}\n</body>\n</html>";
};

$check(
	'P17.1 an unrelated head <link> is GONE, not blanked — the head is not an assertion surface',
	false === strpos( $norm( $doc( '<link rel="stylesheet" id="some-theme-css" href="/x.css">' ) ), 'some-theme-css' )
);

// THE REPRODUCTION, IN MINIATURE, and the reason the whole ticket exists: toggling a
// co-resident plugin changes what the head contains and nothing else. What must hold is that
// the two documents AGREE, not what they agree on.
$check(
	'P17.2 two documents differing ONLY in an unrelated head asset normalize equal',
	$norm( $doc( '<link rel="stylesheet" id="sticky-header-css" href="/a.css">' ) ) === $norm( $doc( '' ) )
);
$check(
	'P17.3 ...and a third-party head <style> goes with it, body and all',
	$norm( $doc( '<style id="theme-inline-css">.a{color:red}</style>' ) ) === $norm( $doc( '' ) )
);

$check(
	'P17.4 <title> survives — it is built from the post, and a tag in an excerpt reaches it',
	false !== strpos( $norm( $doc( '<title>Matrix: Post Meta &#8211; BWS Testbed</title>' ) ), 'Matrix: Post Meta' )
);
$check(
	'P17.5 meta name="description" survives, for the same reason',
	false !== strpos( $norm( $doc( '<meta name="description" content="R0.1: (987) 654-3210">' ) ), 'R0.1: (987) 654-3210' )
);
$check(
	'P17.6 og:description survives, for the same reason',
	false !== strpos( $norm( $doc( '<meta property="og:description" content="J1: Jane, Johnson">' ) ), 'J1: Jane, Johnson' )
);
// THE WHITELIST IS A WHITELIST, not "keep anything og:". og:title carries the same text as
// <title> and is dropped; if that ever needs watching it is added deliberately, here.
$check(
	'P17.7 og:title is NOT whitelisted — the list is enumerated, not pattern-guessed',
	false === strpos( $norm( $doc( '<meta property="og:title" content="Matrix: Post Meta">' ) ), 'og:title' )
);

// OUR OWN OUTPUT KEEPS ITS §P3 EXEMPTION WHEREVER IT LANDS. {{table}} emits at wp_footer:5
// today, so its <style> prints inside <body> — but that placement lives in another file, and
// an exemption that silently depended on it would answer the "prints exactly once" question
// "yes" forever if the emit ever moved.
$check(
	'P17.8 a bws-* <style> is kept even in the HEAD — the §P3 exemption does not depend on emit placement',
	false !== strpos( $norm( $doc( '<style id="bws-table-inline-css">.bws-table{border:0}</style>' ) ), 'border:0' )
);
$check(
	'P17.9 ...and a bws-* <style> in the BODY is untouched, which is where {{table}} actually emits',
	false !== strpos( $norm( $doc( '', '<style id="bws-table-inline-css">.bws-table{border:0}</style>' ) ), 'border:0' )
);

$check(
	'P17.10 the BODY is untouched — a rendered tag row is still the signal',
	false !== strpos( $norm( $doc( '<link rel="stylesheet" id="x-css" href="/x.css">' ) ), 'T1: value' )
);
$check(
	'P17.11 a body change is still a diff (narrowing removed noise, not the assertion)',
	$norm( $doc( '', '<p>A</p>' ) ) !== $norm( $doc( '', '<p>B</p>' ) )
);

// A FRAGMENT HAS NO HEAD, and every other section of this harness feeds fragments. A rule
// that matched loosely here would quietly eat the inputs the rest of the file is written on.
$check(
	'P17.12 a head-less fragment is untouched',
	false !== strpos( $norm( '<p class="gb-text">D0.3: 2030-08-12 9:00 am</p>' ), 'D0.3: 2030-08-12 9:00 am' )
);

// SAY SO IN THE ARTIFACT, not only in the docblock. A reader landing on a baseline file — or
// on a 250-line deletion in `git log` — has to be able to tell "we chose not to look" from
// "we looked and it was fine".
$check(
	'P17.13 the narrowed head says in the file that it is not asserted',
	false !== strpos( $norm( $doc( '<link rel="stylesheet" id="x-css" href="/x.css">' ) ), 'head not asserted' )
);
$check(
	'P17.14 ...and the <head> element itself is still there, so the document shape did not change',
	false !== strpos( $norm( $doc( '' ) ), '<head>' ) && false !== strpos( $norm( $doc( '' ) ), '</head>' )
);

/* =========================================================================
 * §P11/§P12 — the differ
 * ====================================================================== */

$check( 'P11.1 identical input reports no differences', array() === bws_page_snapshot_diff( "a\nb\nc", "a\nb\nc" ) );

$one = bws_page_snapshot_diff( "a\nb\nc", "a\nX\nc" );
$check( 'P11.2 one changed line reports exactly one difference', 1 === count( $one ) );
$check( 'P11.3 ...at the right 1-indexed line, carrying both sides', 2 === $one[0]['line'] && 'b' === $one[0]['baseline'] && 'X' === $one[0]['current'] );

$short = bws_page_snapshot_diff( "a\nb\nc", 'a' );
$check( 'P11.4 a SHORTER current side marks the vanished lines rather than silently ending', 2 === count( $short ) && '<<absent>>' === $short[0]['current'] );

$long = bws_page_snapshot_diff( 'a', "a\nb" );
$check( 'P11.5 a LONGER current side marks the added lines the same way', 1 === count( $long ) && '<<absent>>' === $long[0]['baseline'] );

$many = bws_page_snapshot_diff( implode( "\n", array_fill( 0, 40, 'a' ) ), implode( "\n", array_fill( 0, 40, 'b' ) ), 5 );
$check( 'P12.1 every difference past the limit is still COUNTED', 40 === count( $many ) );
$check( 'P12.2 ...but carries no text, so a wholesale change does not print a book', null !== $many[4]['baseline'] && null === $many[5]['baseline'] );

/* =========================================================================
 * §P13 — the page set derived from a manifest
 *
 * The selector is `content_builder`, which marks an entry whose content was generated to be
 * READ. Entries without one render none of our tags, so they would contribute a page of
 * theme chrome to every diff and nothing else.
 * ====================================================================== */

$fake = array(
	'posts' => array(
		'z-page'      => array( 'post_type' => 'page', 'post_name' => 'zeta', 'content_builder' => 'x' ),
		'a-staff'     => array( 'post_type' => 'staff', 'post_name' => 'alpha', 'content_builder' => 'x' ),
		'no-builder'  => array( 'post_type' => 'page', 'post_name' => 'plain' ),
		'no-slug'     => array( 'post_type' => 'page', 'content_builder' => 'x' ),
	),
);

$set = bws_page_snapshot_pages( $fake );

$check( 'P13.1 only entries with a content_builder AND a slug are selected', array( 'a-staff', 'z-page' ) === array_keys( $set ) );
$check( 'P13.2 a `page` derives /<slug>/', '/zeta/' === $set['z-page']['path'] );
$check( 'P13.3 any other post type derives /<post_type>/<slug>/', '/staff/alpha/' === $set['a-staff']['path'] );
// Sorted so the baseline filenames and the report are stable against manifest reordering —
// otherwise a cosmetic move in the blueprint reads as a changed instrument.
$check( 'P13.4 the set is key-sorted, so manifest reordering does not move the report', array_keys( $set ) === array( 'a-staff', 'z-page' ) );
$check( 'P13.5 an empty manifest yields an empty set rather than a notice', array() === bws_page_snapshot_pages( array() ) );

/* =========================================================================
 * §P14 — the real manifest still yields a set
 *
 * A guard against the selector silently emptying: bws_page_snapshot_pages() returning
 * nothing would make the comparison pass trivially, on zero pages, forever.
 * ====================================================================== */

$real = bws_page_snapshot_pages();
$check( 'P14.1 the shipped manifest yields a non-empty page set', count( $real ) > 0, count( $real ) . ' page(s)' );
$check( 'P14.2 every derived path is absolute-rooted and slash-terminated', $real === array_filter( $real, static function ( $p ) {
	return '/' === substr( $p['path'], 0, 1 ) && '/' === substr( $p['path'], -1 );
} ) );

/* ======================================================================
 * §P15 — a capture with any unreachable page writes NOTHING
 *
 * The rule the CLI's capture branch reads before it touches disk. Pinned because the
 * alternative failure is invisible: a partial baseline is 8 fresh files beside 1 stale one,
 * committable by accident, and clean-diffing forever after. Documented in
 * docs/testbed.md and docs/update-triggers.md, which state the residual limit — a write
 * failing partway through still leaves a mixed set — that this rule cannot cover.
 * ====================================================================== */

$check(
	'P15.1 a fully successful capture blocks nothing',
	array() === bws_page_snapshot_capture_blockers(
		array(
			'shots'  => array( 'a' => '<html>a</html>', 'b' => '<html>b</html>' ),
			'errors' => array(),
		)
	)
);

$check(
	'P15.2 one unreachable page blocks the whole write',
	array( 'b' ) === bws_page_snapshot_capture_blockers(
		array(
			'shots'  => array( 'a' => '<html>a</html>', 'b' => null ),
			'errors' => array( 'b' => 'HTTP 404' ),
		)
	)
);

$check(
	'P15.3 a null shot with no error blocks too — an unexplained empty capture is not a baseline',
	array( 'c' ) === bws_page_snapshot_capture_blockers(
		array(
			'shots'  => array( 'a' => '<html>a</html>', 'c' => null ),
			'errors' => array(),
		)
	)
);

$check(
	'P15.4 blockers are deduplicated and ordered, so the report does not vary with page order',
	array( 'a', 'b' ) === bws_page_snapshot_capture_blockers(
		array(
			'shots'  => array( 'b' => null, 'a' => null ),
			'errors' => array( 'b' => 'HTTP 500', 'a' => 'timeout' ),
		)
	)
);

$check(
	'P15.5 an empty capture reports no blockers rather than erroring — the empty-set guard is P14.1 job',
	array() === bws_page_snapshot_capture_blockers( array() )
);

/* ======================================================================
 * §P16 — the dependency record: what is a warning, what is a failure
 *
 * `bws_page_snapshot_env_compare()` is the pure half of the env check, split out of
 * `bws_page_snapshot_env_drift()` so this can exist: the WordPress half is a get_plugins()
 * lookup, and a lookup was never the part that could be wrong. What is pinned here is the
 * SPLIT — a required dependency being unusable blocks, a version change does not — because
 * getting it wrong fails silently in the expensive direction. A missing dependency demoted
 * to a warning yields a full page comparison against a baseline captured on a different
 * site, and every diff it prints is attributed to the change under review.
 *
 * The rule itself is owned by `env-versions.php`'s header (which axis is answered how) and
 * enforced in `verify.php` (which turns a required absence into a failed check).
 * ====================================================================== */

$env_record = array(
	'captured' => '2026-01-01',
	'plugins'  => array(
		'a/a.php' => array( 'label' => 'Aye', 'version' => '1.0.0', 'required' => true ),
		'b/b.php' => array( 'label' => 'Bee', 'version' => '2.0.0', 'required' => false ),
		'c/c.php' => array( 'label' => 'Cee', 'version' => '3.0.0' ),
	),
);

$env_present = static function ( $version, $active = true ) {
	return array( 'version' => $version, 'active' => $active );
};

$env_clean = bws_page_snapshot_env_compare(
	$env_record,
	array(
		'a/a.php' => $env_present( '1.0.0' ),
		'b/b.php' => $env_present( '2.0.0' ),
		'c/c.php' => $env_present( '3.0.0' ),
	)
);

$check(
	'P16.1 a matching environment reports no drift and nothing missing',
	array() === $env_clean['drift'] && array() === $env_clean['missing']
);
$check(
	'P16.2 every recorded entry is counted, so an empty record is distinguishable from a clean one',
	3 === $env_clean['checked']
);
$check( 'P16.3 the capture date is carried through for the reader', '2026-01-01' === $env_clean['captured'] );

// BOTH AXES IN ONE CALL: Aye is gone (blocking), Bee moved version (a warning, never
// blocking). Asserting them together is the point — the split is the property, not either
// half on its own.
$env_mixed = bws_page_snapshot_env_compare(
	$env_record,
	array(
		'b/b.php' => $env_present( '2.1.0' ),
		'c/c.php' => $env_present( '3.0.0' ),
	)
);

$check( 'P16.4 an absent REQUIRED dependency blocks', 1 === $env_mixed['blocking'] );
$check(
	'P16.5 it is named and its reason given, so the operator is told which one and why',
	'Aye' === $env_mixed['missing'][0]['label'] && 'NOT INSTALLED' === $env_mixed['missing'][0]['reason']
);
$check(
	'P16.6 a version change is reported and does NOT block',
	array( 'Bee: recorded 2.0.0, installed 2.1.0' ) === $env_mixed['drift'] && 1 === $env_mixed['blocking']
);

// INSTALLED BUT NOT ACTIVE renders exactly like absent — no filters, no tags — so it counts
// as missing. A check that only asked whether the files were on disk passes here, and then
// compares against a baseline captured with the plugin running.
$env_inactive = bws_page_snapshot_env_compare(
	$env_record,
	array(
		'a/a.php' => $env_present( '1.0.0', false ),
		'b/b.php' => $env_present( '2.0.0' ),
		'c/c.php' => $env_present( '3.0.0' ),
	)
);

$check(
	'P16.7 installed but INACTIVE counts as missing, and blocks',
	1 === $env_inactive['blocking'] && 'installed but NOT ACTIVE' === $env_inactive['missing'][0]['reason']
);
$check( 'P16.8 an inactive plugin gets no version line quietly contradicting that', array() === $env_inactive['drift'] );

// Bee opted out explicitly. Still reported so the operator can see it; the run continues.
// This is the only shape that distinguishes `required => false` from silence.
$env_optional = bws_page_snapshot_env_compare(
	$env_record,
	array(
		'a/a.php' => $env_present( '1.0.0' ),
		'c/c.php' => $env_present( '3.0.0' ),
	)
);

$check(
	'P16.9 an absent OPTIONAL dependency is reported but does not block',
	0 === $env_optional['blocking'] && 1 === count( $env_optional['missing'] )
);

// SILENCE MEANS REQUIRED. Cee carries no `required` key at all. A dependency added to the
// record without one must fail loudly rather than join it as an optional extra nobody reads.
$env_silent = bws_page_snapshot_env_compare(
	$env_record,
	array(
		'a/a.php' => $env_present( '1.0.0' ),
		'b/b.php' => $env_present( '2.0.0' ),
	)
);

$check(
	'P16.10 an entry with NO required key defaults to required',
	1 === $env_silent['blocking'] && 'Cee' === $env_silent['missing'][0]['label']
);

$env_unpinned = bws_page_snapshot_env_compare(
	array( 'plugins' => array( 'a/a.php' => array( 'label' => 'Aye' ) ) ),
	array( 'a/a.php' => $env_present( '1.0.0' ) )
);

$check(
	'P16.11 an entry with no recorded version says it pins nothing, once, rather than drifting against the empty string',
	1 === count( $env_unpinned['drift'] ) && false !== strpos( $env_unpinned['drift'][0], 'pins nothing' )
);
$check(
	'P16.12 an empty record compares nothing rather than erroring',
	0 === bws_page_snapshot_env_compare( array(), array() )['checked']
);

/* ----------------------------------------------------------------------
 * The SHIPPED record, not a fixture.
 *
 * Pinned because nothing else in the tree fails if the query extension's entry is deleted
 * from the record: no page moves without it today, which is the measurement
 * `docs/testbed.md` carries and the reason the declaration has to be checked rather than
 * relied on.
 * -------------------------------------------------------------------- */

$env_shipped = require dirname( __DIR__ ) . '/fixtures/core-structures/env-versions.php';
$env_gbqe    = 'gb-query-enhancements/gb-query-enhancements.php';

$check( 'P16.13 the shipped record declares the query extension', isset( $env_shipped['plugins'][ $env_gbqe ] ) );
$check( 'P16.14 it is declared REQUIRED', ! empty( $env_shipped['plugins'][ $env_gbqe ]['required'] ) );
$check(
	'P16.15 every shipped entry pins a version, so none of them silently pins nothing',
	array() === array_filter(
		$env_shipped['plugins'],
		static function ( $spec ) {
			return empty( $spec['version'] );
		}
	)
);

/* ======================================================================
 * §P18 — the ACTIVE SET, a provenance axis that never blocks
 *
 * §P17 stops an unrelated plugin toggle from re-flowing the baselines. It does not make the
 * toggle VISIBLE, and the two are different jobs: the 2026-08-28 re-capture was attributed
 * only because someone went looking for what had changed on the box. Recording which plugins
 * were running turns that into a statement the tooling makes.
 *
 * A WARNING, NEVER A FAILURE, and the split is the same one §P16 pins for versions: only a
 * human can judge whether a co-resident plugin matters, and the four `required` entries are
 * where "must be running" is already enforced. An unexpected active plugin failing the run
 * would make the fixture site unusable for anything else.
 * ====================================================================== */

$env_active_record = array(
	'captured' => '2026-01-01',
	'active'   => array( 'a/a.php', 'b/b.php' ),
	'plugins'  => array( 'a/a.php' => array( 'label' => 'Aye', 'version' => '1.0.0' ) ),
);

$env_same_set = bws_page_snapshot_env_compare(
	$env_active_record,
	array(
		'a/a.php' => $env_present( '1.0.0' ),
		'b/b.php' => $env_present( '2.0.0' ),
		'z/z.php' => $env_present( '9.0.0', false ),
	)
);

$check(
	'P18.1 an unchanged active set reports nothing — an INSTALLED-but-inactive extra is not a change',
	array() === $env_same_set['active_drift']
);
$check( 'P18.2 ...and it does not block, because nothing on this axis ever does', 0 === $env_same_set['blocking'] );

$env_added = bws_page_snapshot_env_compare(
	$env_active_record,
	array(
		'a/a.php' => $env_present( '1.0.0' ),
		'b/b.php' => $env_present( '2.0.0' ),
		'c/c.php' => $env_present( '3.0.0' ),
	)
);

$check(
	'P18.3 a plugin activated since capture is named',
	1 === count( $env_added['active_drift'] ) && false !== strpos( $env_added['active_drift'][0], 'c/c.php' )
);
$check( 'P18.4 ...and does not block', 0 === $env_added['blocking'] );

// THE DIRECTION HAS TO BE READABLE. "Something differs" sends the operator to the plugins
// screen to work out which way; the baseline was captured under one of these two sets and the
// line has to say which.
$env_removed = bws_page_snapshot_env_compare(
	$env_active_record,
	array(
		'a/a.php' => $env_present( '1.0.0' ),
		'b/b.php' => $env_present( '2.0.0', false ),
	)
);

$check(
	'P18.5 a plugin deactivated since capture is named, and the DIRECTION is stated',
	1 === count( $env_removed['active_drift'] )
		&& false !== strpos( $env_removed['active_drift'][0], 'b/b.php' )
		&& $env_added['active_drift'][0] !== $env_removed['active_drift'][0]
);

// THIS IS THE 2026-08-28 SHAPE. Deactivating b and activating c at once is one operator
// action on the plugins screen and two lines here; reporting only the first would attribute
// the diff to half its cause.
$env_both = bws_page_snapshot_env_compare(
	$env_active_record,
	array(
		'a/a.php' => $env_present( '1.0.0' ),
		'b/b.php' => $env_present( '2.0.0', false ),
		'c/c.php' => $env_present( '3.0.0' ),
	)
);

$check( 'P18.6 both directions are reported together, not the first one found', 2 === count( $env_both['active_drift'] ) );

// SILENCE PINS NOTHING, said ONCE — the same shape §P16.11 gives an entry with no recorded
// version. Comparing against an absent key would report every running plugin as newly active,
// which is the loudest possible output for the one reason that says nothing about the site.
$env_no_set = bws_page_snapshot_env_compare(
	array( 'plugins' => array( 'a/a.php' => array( 'label' => 'Aye', 'version' => '1.0.0' ) ) ),
	array( 'a/a.php' => $env_present( '1.0.0' ), 'q/q.php' => $env_present( '1.0.0' ) )
);

$check(
	'P18.7 a record with no active set says it pins nothing, once, rather than listing the whole site',
	1 === count( $env_no_set['active_drift'] ) && false !== strpos( $env_no_set['active_drift'][0], 'pins nothing' )
);
$check( 'P18.8 ...and the VERSION axis is unaffected by that', array() === $env_no_set['drift'] );

$check(
	'P18.9 an empty record reports the unpinned line rather than erroring',
	1 === count( bws_page_snapshot_env_compare( array(), array() )['active_drift'] )
);

/* ----------------------------------------------------------------------
 * The SHIPPED record again. `active` is what makes a moved baseline attributable, so an
 * entry list that emptied — or was never re-recorded after a capture — has to fail here
 * rather than go quiet: an unpinned axis reports one tidy line forever.
 * -------------------------------------------------------------------- */

$check( 'P18.10 the shipped record pins an active set', ! empty( $env_shipped['active'] ) );
$check(
	'P18.11 ...containing this plugin, which is running whenever a baseline is captured',
	in_array( 'bws-gb-dynamic-tags-extensions/bws-gb-dynamic-tags-extensions.php', (array) $env_shipped['active'], true )
);
// The four `required` entries are a claim about what must be RUNNING. A required plugin
// absent from the active set would be the record contradicting itself.
$check(
	'P18.12 every REQUIRED dependency also appears in the active set',
	array() === array_filter(
		array_keys( $env_shipped['plugins'] ),
		static function ( $file ) use ( $env_shipped ) {
			$spec = $env_shipped['plugins'][ $file ];

			return ( ! isset( $spec['required'] ) || $spec['required'] )
				&& ! in_array( $file, (array) $env_shipped['active'], true );
		}
	)
);

echo $fail ? "\nPAGE-SNAPSHOT NORMALIZE TEST FAILED ({$fail})\n" : "\nPAGE-SNAPSHOT NORMALIZE TEST PASSED\n";
exit( $fail ? 1 : 0 );
