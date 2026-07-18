<?php
/**
 * Standalone unit harness for the {{join}} pure assembly algorithm in
 * includes/helpers/join-helpers.php:
 *   - bws_join_separator( array $values, string $sep ): string
 *   - bws_join_assemble( array $values, array $options ): string
 *   - bws_join_template( array $values, string $format ): string
 *   - bws_join_remove_empty_token( string, string, bool ): string
 *   - bws_join_strip_connective_separators( string ): string
 *
 * All pure string/array transforms — no WordPress required. join-helpers.php
 * is loaded inert (ABSPATH defined) per the try-join-seam-test.php pattern; we
 * call only the pure helpers (bws_join_resolve_slot is never invoked here).
 *
 * SCOPE — the template-mode smart-literal-removal contract (Steps 1–5) plus
 * separator-mode semantics:
 *   Step 1a  unit punct (. ' ") trailing-attached sheds with the empty token
 *   Step 1b  connective (, :) collapses only between TWO connectives (left one
 *            consumed); single-sided connective survives → Step 4 repairs
 *   Step 2   bracket pairs around empty tokens removed (outward through ws)
 *   Step 3   floating separators (· • / | - – —) adjacent removed; last token
 *            looks left
 *   Step 4   ws collapse + ws-before-connective repair + leading orphan strip
 *   Step 4b  trailing orphan : , . stripped unless format ends '.'; quotes kept
 *   Step 5   single survivor strips remaining connective separators
 *   '0' is a REAL value everywhere ('' is the only empty).
 *   All-empty tokens → '' (fallback fires at the callback layer).
 *
 * Owns the J23/J24 mid-string single-empty-part assertions (decided
 * 2026-07-17: these are pure string-transform edges — render-tag has no
 * per-field blanking, so they live here, NOT in join-test-matrix.md).
 *
 * Run:
 *   php tools/test/join-template-test.php
 *
 * Exit 0 = all pass, 1 = any failure.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

require __DIR__ . '/../../includes/helpers/join-helpers.php';

$failures = 0;
$count    = 0;

function assert_same( $label, $expected, $actual ): void {
	global $failures, $count;
	$count++;
	if ( $expected === $actual ) {
		echo "  ok   {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL {$label}\n";
	echo '       expected: ' . json_encode( $expected ) . "\n";
	echo '       actual:   ' . json_encode( $actual ) . "\n";
}

/** Template shorthand: values array is 1-based slot => value. */
function tpl( array $values, string $format ): string {
	return bws_join_template( $values, $format );
}

echo "bws_join_separator\n";

assert_same( 'two values, default-style sep', 'Jane, Smith', bws_join_separator( array( 1 => 'Jane', 2 => 'Smith' ), ', ' ) );
assert_same( 'space sep', 'Jane Smith', bws_join_separator( array( 1 => 'Jane', 2 => 'Smith' ), ' ' ) );
assert_same( 'empty middle slot dropped, no doubled sep', 'Tom, Associate', bws_join_separator( array( 1 => 'Tom', 2 => '', 3 => 'Associate' ), ', ' ) );
assert_same( 'all empty → empty string', '', bws_join_separator( array( 1 => '', 2 => '' ), ', ' ) );
assert_same( "'0' is a REAL value (survives the filter)", '0, Captain', bws_join_separator( array( 1 => '0', 2 => 'Captain' ), ', ' ) );
assert_same( 'single value → no separator', 'Jane', bws_join_separator( array( 1 => 'Jane', 2 => '' ), ', ' ) );

echo "bws_join_assemble (mode dispatch)\n";

assert_same( 'default mode = separator, default sep ", "', 'a, b', bws_join_assemble( array( 1 => 'a', 2 => 'b' ), array() ) );
assert_same( 'explicit sep carried (incl. spaces)', 'a / b', bws_join_assemble( array( 1 => 'a', 2 => 'b' ), array( 'sep' => ' / ' ) ) );
assert_same( 'template mode routes to format', 'a (b)', bws_join_assemble( array( 1 => 'a', 2 => 'b' ), array( 'mode' => 'template', 'format' => '{1} ({2})' ) ) );
assert_same( 'template mode, no format → empty', '', bws_join_assemble( array( 1 => 'a' ), array( 'mode' => 'template' ) ) );

echo "Step 1a — unit punct (. ' \") trailing-attached sheds with empty token\n";

assert_same( 'mid-string period sheds: {1}. {2}, {1} empty', 'Smith', tpl( array( 1 => '', 2 => 'Smith' ), '{1}. {2}' ) );
assert_same( 'height: dangling \" sheds with empty inches', "5'", tpl( array( 1 => '5', 2 => '' ), '{1}\'{2}"' ) );
assert_same( 'height: dangling \' sheds with empty feet', '11"', tpl( array( 1 => '', 2 => '11' ), '{1}\'{2}"' ) );
assert_same( 'height: both empty → empty string', '', tpl( array( 1 => '', 2 => '' ), '{1}\'{2}"' ) );
assert_same( "height: '0' inches renders 5'0\" (absorbed '0' rule)", '5\'0"', tpl( array( 1 => '5', 2 => '0' ), '{1}\'{2}"' ) );
assert_same( 'middle-initial period sheds when initial empty', 'Jane Smith', tpl( array( 1 => 'Jane', 2 => '', 3 => 'Smith' ), '{1} {2}. {3}' ) );

echo "Step 1b — connective (, :) collapse\n";

assert_same( '{1}, {2} — {1} empty → {2} (comma orphaned at start, stripped)', 'Smith', tpl( array( 1 => '', 2 => 'Smith' ), '{1}, {2}' ) );
assert_same( '{1}, {2} — {2} empty → {1} (comma orphaned at end, stripped)', 'Smith', tpl( array( 1 => 'Smith', 2 => '' ), '{1}, {2}' ) );
assert_same( '{1}, {2}, {3} — middle empty keeps ONE comma (Gap-2 fix)', 'Jane, Smith', tpl( array( 1 => 'Jane', 2 => '', 3 => 'Smith' ), '{1}, {2}, {3}' ) );
assert_same( '{1}: {2} — {1} empty → {2}', 'Smith', tpl( array( 1 => '', 2 => 'Smith' ), '{1}: {2}' ) );
assert_same( '{1}: {2} — {2} empty → {1}', 'Smith', tpl( array( 1 => 'Smith', 2 => '' ), '{1}: {2}' ) );
assert_same( 'single-sided comma survives as separator: {1} {2}, {3} — {2} empty', 'Smith, PhD', tpl( array( 1 => 'Smith', 2 => '', 3 => 'PhD' ), '{1} {2}, {3}' ) );
assert_same( 'two adjacent empties cascade: {1}, {2}, {3} — {1},{2} empty', 'Smith', tpl( array( 1 => '', 2 => '', 3 => 'Smith' ), '{1}, {2}, {3}' ) );

echo "Step 2 — bracket pairs around empty tokens\n";

assert_same( '{1} ({2}) — {2} empty → brackets removed', 'Tom', tpl( array( 1 => 'Tom', 2 => '' ), '{1} ({2})' ) );
assert_same( '{1} ({2}.) — {2} empty → punct + brackets removed', 'Tom', tpl( array( 1 => 'Tom', 2 => '' ), '{1} ({2}.)' ) );
assert_same( '{1} ({2}.) — {1} empty → bracket KEPT around survivor', '(Tom.)', tpl( array( 1 => '', 2 => 'Tom' ), '{1} ({2}.)' ) );
assert_same( 'square brackets removed around empty token', 'Tom', tpl( array( 1 => 'Tom', 2 => '' ), '{1} [{2}]' ) );
assert_same( 'both tokens empty in bracketed format → empty', '', tpl( array( 1 => '', 2 => '' ), '{1} ({2})' ) );

echo "Step 3 — floating separators adjacent to empty tokens\n";

assert_same( '{1} · {2} — {2} empty (last → look left)', 'Jane', tpl( array( 1 => 'Jane', 2 => '' ), '{1} · {2}' ) );
assert_same( '{1} · {2} — {1} empty (look right)', 'Associate', tpl( array( 1 => '', 2 => 'Associate' ), '{1} · {2}' ) );
assert_same( '{1} · {2} – {3} — {2} empty → look right', 'a · c', tpl( array( 1 => 'a', 2 => '', 3 => 'c' ), '{1} · {2} – {3}' ) );
assert_same( '{1} · {2} – {3} — {3} empty → last, look left', 'a · b', tpl( array( 1 => 'a', 2 => 'b', 3 => '' ), '{1} · {2} – {3}' ) );
assert_same( '{1} · {2} · {3} — {1},{3} empty → {2}', 'b', tpl( array( 1 => '', 2 => 'b', 3 => '' ), '{1} · {2} · {3}' ) );
assert_same( 'pipe separator: {1} | {2} — {1} empty', 'b', tpl( array( 1 => '', 2 => 'b' ), '{1} | {2}' ) );
assert_same( 'slash separator: {1} / {2} — {2} empty', 'a', tpl( array( 1 => 'a', 2 => '' ), '{1} / {2}' ) );
assert_same( 'em dash: {1} — {2} — {2} empty', 'a', tpl( array( 1 => 'a', 2 => '' ), '{1} — {2}' ) );

echo "Step 4/4b — whitespace + trailing orphan punctuation\n";

assert_same( 'trailing colon stripped: {1}: {2} — {2} empty', 'Author', tpl( array( 1 => 'Author', 2 => '' ), '{1}: {2}' ) );
assert_same( 'format ending "." keeps sentence terminator', 'Smith.', tpl( array( 1 => '', 2 => '', 3 => 'Smith' ), '{1} {2}, {3}.' ) );
assert_same( '{1}. {2}, {3} — {1},{3} empty → {2} (period+comma both gone)', 'Smith', tpl( array( 1 => '', 2 => 'Smith', 3 => '' ), '{1}. {2}, {3}' ) );
assert_same( "trailing quote NEVER stripped (surviving 5')", "5'", tpl( array( 1 => '5', 2 => '' ), '{1}\'{2}"' ) );

echo "Step 5 — single survivor strips connective separators\n";

assert_same( 'literal text kept beside survivor: Mr. {1} · {2} — {2} empty', 'Mr. Smith', tpl( array( 1 => 'Smith', 2 => '' ), 'Mr. {1} · {2}' ) );
assert_same( 'survivor keeps its brackets: {1} · ({2}.) — {1} empty', '(Tom.)', tpl( array( 1 => '', 2 => 'Tom' ), '{1} · ({2}.)' ) );
assert_same( 'hyphen inside a word untouched', 'e-mail', tpl( array( 1 => 'e-mail', 2 => '' ), '{1} · {2}' ) );

echo "Full-name stress case — {1} {2} {3}. {4} {5}, {6}, {7}\n";

$name_format = '{1} {2} {3}. {4} {5}, {6}, {7}';
$dense       = array( 1 => 'Dr.', 2 => 'Jane', 3 => 'M', 4 => 'Smith', 5 => 'Jr.', 6 => 'PhD', 7 => 'USN (Ret.)' );

assert_same( 'J21 dense: every part rendered', 'Dr. Jane M. Smith Jr., PhD, USN (Ret.)', tpl( $dense, $name_format ) );
assert_same(
	'J22 sparse (first+last only): full collapse',
	'Tom Associate',
	tpl( array( 1 => '', 2 => 'Tom', 3 => '', 4 => 'Associate', 5 => '', 6 => '', 7 => '' ), $name_format )
);
assert_same(
	'J23 empty generation ({5}): surrounding ", " collapses cleanly',
	'Dr. Jane M. Smith, PhD, USN (Ret.)',
	tpl( array_replace( $dense, array( 5 => '' ) ), $name_format )
);
assert_same(
	'J24 empty credential ({6}): sheds ONE comma, not both (Gap-2 core)',
	'Dr. Jane M. Smith Jr., USN (Ret.)',
	tpl( array_replace( $dense, array( 6 => '' ) ), $name_format )
);
assert_same(
	'empty honorific ({1}): leading part drops cleanly',
	'Jane M. Smith Jr., PhD, USN (Ret.)',
	tpl( array_replace( $dense, array( 1 => '' ) ), $name_format )
);
assert_same(
	'empty middle initial ({3}): its "." sheds',
	'Dr. Jane Smith Jr., PhD, USN (Ret.)',
	tpl( array_replace( $dense, array( 3 => '' ) ), $name_format )
);
assert_same(
	'empty service ({7}): trailing comma stripped',
	'Dr. Jane M. Smith Jr., PhD',
	tpl( array_replace( $dense, array( 7 => '' ) ), $name_format )
);
assert_same(
	'only last name survives: all separators gone',
	'Smith',
	tpl( array( 1 => '', 2 => '', 3 => '', 4 => 'Smith', 5 => '', 6 => '', 7 => '' ), $name_format )
);

echo "All-empty / degenerate formats\n";

assert_same( 'all tokens empty → empty string (fallback fires upstream)', '', tpl( array( 1 => '', 2 => '' ), '{1} ({2})' ) );
assert_same( 'all tokens empty with literals → still empty (no residue)', '', tpl( array( 1 => '' ), 'Mr. {1}' ) );
assert_same( 'format with NO tokens returned verbatim', 'literal only', tpl( array( 1 => 'x' ), 'literal only' ) );
assert_same( 'token beyond configured slots treated as empty', 'a', tpl( array( 1 => 'a' ), '{1} {8}' ) );
assert_same( "template '0' value is non-empty (no all-empty short-circuit)", '0', tpl( array( 1 => '0' ), '{1}' ) );

if ( $failures ) {
	echo "\n{$failures} of {$count} assertions FAILED\n";
	exit( 1 );
}
echo "\nAll {$count} assertions passed.\n";
exit( 0 );
