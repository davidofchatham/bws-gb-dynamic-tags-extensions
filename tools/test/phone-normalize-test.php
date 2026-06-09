<?php
/**
 * Standalone unit harness for bws_phone_normalize_tel() and its sub-helpers.
 *
 * No WordPress required — normalize is a pure function; the two settings wrappers
 * are bypassed by passing $cc / $stripCc directly. Run:
 *   php tools/test/phone-normalize-test.php
 *
 * Covers SPEC §V VP-hyphen, VP3, VP-cc-dedupe, VP-strip, VP4, VP-href-safe (one
 * case-group each). Exit 0 = all pass, 1 = any failure.
 *
 * @package BWS_Dynamic_Tags
 */

// Pull in ONLY the pure functions. The file's register/callback paths reference
// GB/WP symbols, but normalize + sub-helpers are self-contained, so guard ABSPATH
// and define the WP shims the file's top-level needs (none are called at parse).
define( 'ABSPATH', __DIR__ );
if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}

require __DIR__ . '/../../includes/tags/phone-tags.php';

$failures = 0;
$count    = 0;

/**
 * @param string $label   Case label.
 * @param string $raw     Raw input.
 * @param string $cc      Country code.
 * @param bool   $strip   strip-leading-cc flag.
 * @param string $expect  Expected normalize() return.
 */
function check( string $label, string $raw, string $cc, bool $strip, string $expect ): void {
	global $failures, $count;
	$count++;
	$got = bws_phone_normalize_tel( $raw, $cc, $strip );
	if ( $got === $expect ) {
		echo "  ok   {$label}\n";
	} else {
		$failures++;
		echo "  FAIL {$label}\n       raw='{$raw}' cc='{$cc}' strip=" . ( $strip ? '1' : '0' ) . "\n";
		echo "       expected '{$expect}'\n       got      '{$got}'\n";
	}
}

echo "VP-hyphen — author separators reused; bare digits get none\n";
check( 'parens+space+dash, CC',   '(987) 654-3210', '1', false, '+1-987-654-3210' );
check( 'dots, CC',                '987.654.3210',   '1', false, '+1-987-654-3210' );
check( 'parens no space, CC',     '(987)654-3210',  '1', false, '+1-987-654-3210' );
check( 'bare digits + CC → E164', '9876543210',     '1', false, '+19876543210' );
check( 'bare digits no CC → natl','9876543210',     '',  false, '9876543210' );

echo "\nVP3 — international detection + trunk-0 + national fallback\n";
check( 'in-field +, CC ignored',  '+1 987 654 3210','44', false, '+1-987-654-3210' );
check( '00 → +',                  '0011 22 3333',   '',   false, '+11-22-3333' );
check( 'UK trunk-0 strip on CC',  '07911 123456',   '44', false, '+44-7911-123456' );
check( 'no CC keeps trunk 0',     '07911 123456',   '',   false, '07911-123456' );

echo "\nVP-cc-dedupe — separated first group == CC, structure-confident, flag-agnostic\n";
// Structured `1-800…`: first split group '1' == cc → dedupe, never doubled,
// SAME result whether the strip flag is on or off (dedupe short-circuits strip).
check( 'sep CC dedupe (strip OFF)','1-800-555-1212', '1', false, '+1-800-555-1212' );
check( 'sep CC dedupe (strip ON)', '1-800-555-1212', '1', true,  '+1-800-555-1212' );
check( 'sep CC w/ parens',         '1 (800) 555-1212','1', false,'+1-800-555-1212' );
check( 'sep CC w/ dot',            '1.800.555.1212', '1', false, '+1-800-555-1212' );
check( 'first group != cc → no dedupe','12-800-5551', '1', false, '+1-12-800-5551' );

echo "\nVP-strip — leading-CC strip (global), gated, FLAT digits only\n";
// Flat (no separator) → dedupe cannot fire (structure-gated); strip is the only
// path that can collapse a doubled flat CC, and only when opted in.
check( 'flat 1+natl strip ON',    '18005551212',    '1', true,  '+18005551212' );
check( 'flat 1+natl strip OFF',   '18005551212',    '1', false, '+118005551212' );
check( 'strip ON but no CC match','447911123456',   '1', true,  '+1447911123456' );
check( 'flat strip ON, empty CC', '18005551212',    '',  true,  '18005551212' );

echo "\nVP4 — length gate + extension sever\n";
check( 'too short → empty',       '12345',          '1', false, '' );
check( 'too long → empty',        '1234567890123456','',  false, '' );
check( 'sever x99 ext',           '555-867-5309 x99','1', false, '+1-555-867-5309' );
check( 'ext word severed',        '5558675309 ext 4','1', false, '+15558675309' );
check( 'empty raw → empty',       '',               '1', false, '' );

echo "\nVP-href-safe — no raw text survives into value\n";
// `"><script>` is non-digit → a separator run; preg_split yields the same
// digit groups [1,987,654,3210], so the value is digits+hyphens only. The
// point: NO raw text reaches the output (verified by the regex assert below).
check( 'injection → digits only',  '+1-987"><script>654-3210', '1', false, '+1-987-654-3210' );
$count++;
$inj = bws_phone_normalize_tel( '+1-987"><script>654-3210', '1', false );
if ( preg_match( '/^\+?[\d-]+$/', $inj ) ) {
	echo "  ok   value matches ^\\+?[\\d-]+$ (no raw text)\n";
} else {
	$failures++;
	echo "  FAIL value leaked non-digit/hyphen text: '{$inj}'\n";
}

echo "\n" . ( $failures ? "FAILED {$failures}/{$count}\n" : "PASSED {$count}/{$count}\n" );
exit( $failures ? 1 : 0 );
