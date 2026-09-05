<?php
/**
 * gb-trust-boundary-test.php — the trust seam's behavior, and the census that keeps it one.
 *
 * REQUIRES THE REAL FILE. includes/helpers/gb-trust-boundary.php touches WordPress only
 * inside its functions, so it loads against no world at all and the probe order, the
 * short-circuit and the fallback derive are all reachable with two stubs. A test-local copy
 * of the rule is the exact drift the seam removed.
 *
 * THE PROBE-ORDER SECTIONS RUN IN A FIXED ORDER AND CANNOT BE REORDERED. PHP cannot
 * un-declare a class or a function, so the only way to exercise three fallback tiers in one
 * process is to walk them from the emptiest world outward, declaring the next tier as each
 * assertion finishes. A section moved above the one that declares nothing measures a
 * different tier and still passes, which is why this is stated rather than left to be
 * noticed.
 *
 * THE CENSUS (§T5) IS THE HALF THAT MAKES THE SEAM A BOUNDARY. The behavior sections say
 * what the seam does; only the census says it is still the one door. It scans `tools/` too,
 * unlike its sibling gb-output-boundary-test.php, because the pins in verify.php ask GB
 * DIRECTLY on purpose — a pin routed through our own helper stops measuring GB — and the
 * exemption list is where that intent is recorded rather than inferred.
 *
 * Run: php tools/test/gb-trust-boundary-test.php
 *
 * @package BWS_Dynamic_Tags
 */

define( 'ABSPATH', __DIR__ );

$root = dirname( __DIR__, 2 );

$failures = 0;
$count    = 0;

/** @var array<string,bool> Capabilities the stubbed current user holds. */
$GLOBALS['bws_caps'] = array();

/** @var array<string,callable> Filter callbacks, one per tag. */
$GLOBALS['bws_filters'] = array();

function current_user_can( $cap ) {
	return ! empty( $GLOBALS['bws_caps'][ $cap ] );
}

function apply_filters( $tag, $value, ...$args ) {
	if ( isset( $GLOBALS['bws_filters'][ $tag ] ) ) {
		return call_user_func( $GLOBALS['bws_filters'][ $tag ], $value, ...$args );
	}
	return $value;
}

require $root . '/includes/helpers/gb-trust-boundary.php';

function assert_same( $label, $expected, $actual ): void {
	global $failures, $count;
	$count++;
	if ( $expected === $actual ) {
		echo "  ok   {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL {$label}\n";
	echo "       expected: " . var_export( $expected, true ) . "\n";
	echo "       actual:   " . var_export( $actual, true ) . "\n";
}

echo "§T1 — the predicate with NO GenerateBlocks present (the last-resort tier)\n";

// Unreachable on a real install — the plugin header declares Requires Plugins:
// generateblocks-pro — but a guard has to return something, and what it returns is a
// policy decision either way. It returns GB's OWN documented default, so the branch most
// likely to be reached by an upstream rename keeps behaving the way GB would.
$GLOBALS['bws_caps'] = array();
assert_same( 'nobody in particular cannot author dynamic data', false, bws_gb_user_can_author_dynamic_data() );

$GLOBALS['bws_caps'] = array( 'manage_options' => true );
assert_same( 'manage_options alone is enough (GB default, second disjunct)', true, bws_gb_user_can_author_dynamic_data() );

$GLOBALS['bws_caps'] = array( 'unfiltered_html' => true );
assert_same( 'unfiltered_html alone is enough (GB default, first disjunct)', true, bws_gb_user_can_author_dynamic_data() );

// The default is FILTERED, not returned raw — a site that hooks GB's filter must reach the
// answer even on the tier where GB itself is absent, or the seam quietly becomes a second
// policy that ignores the site's own.
$GLOBALS['bws_filters']['generateblocks_user_can_author_dynamic_data'] = static fn( $v ) => false;
assert_same( 'GB\'s filter still governs the last-resort default', false, bws_gb_user_can_author_dynamic_data() );
unset( $GLOBALS['bws_filters']['generateblocks_user_can_author_dynamic_data'] );

echo "\n§T2 — the option-key question with no GB Pro present (the fallback derive)\n";

// Pro's rule, copied: the shared allowlist minus every registered ACF options-page key.
// BWS_GB_TRUST_FALLBACK_READ_FROM records what it was copied from; §T4 pins that against
// the fixture environment.
$GLOBALS['bws_caps'] = array();

assert_same( 'an empty key is never allowed', false, bws_gb_option_allowed_for_current_user( '' ) );
assert_same( 'a whitespace-only key is never allowed', false, bws_gb_option_allowed_for_current_user( '   ' ) );
assert_same( 'a core option stays readable for an untrusted user', true, bws_gb_option_allowed_for_current_user( 'blogname' ) );
assert_same( 'an unlisted option is refused', false, bws_gb_option_allowed_for_current_user( 'some_plugin_secret' ) );

// The dot-notated form resolves to its ROOT segment, the same split Pro applies, so a
// sub-path of a readable option is readable and a sub-path of an unlisted one is not.
assert_same( 'a dot-notated core option resolves on its root', true, bws_gb_option_allowed_for_current_user( 'blogname.sub' ) );
assert_same( 'a dot-notated unlisted option resolves on its root', false, bws_gb_option_allowed_for_current_user( 'some_plugin_secret.sub' ) );

// THE CARVE-OUT IS THE POINT OF THE WHOLE FUNCTION. An ACF options-page key reaches the
// shared allowlist because registration is the opt-in (ADR 0001) — and an untrusted user is
// exactly who that opt-in was not made for.
//
// A KEY IS ACF-SHAPED BECAUSE ACF REGISTERED IT, NOT BECAUSE IT IS ALLOWLISTED, and this
// stub is what makes the difference visible. An earlier draft of this section injected the
// key through the allowlist filter alone and expected it carved out; it was not, correctly —
// with no ACF present there is nothing to subtract, which is exactly what Pro's own derive
// does in the same situation. The two sources have to be stubbed separately or the
// assertion cannot tell a carve-out from an allowlist miss.
if ( ! class_exists( 'GenerateBlocks_Pro_Dynamic_Tags_ACF' ) ) {
	class GenerateBlocks_Pro_Dynamic_Tags_ACF {
		/** @var array<string,mixed> */
		public static array $fields = array();

		public static function get_instance() {
			return new self();
		}

		public function get_acf_option_fields() {
			return self::$fields;
		}
	}
}

GenerateBlocks_Pro_Dynamic_Tags_ACF::$fields = array( 'options_org_phone' => array( 'label' => 'Org phone' ) );

// Registration is the opt-in, and GB Pro's allowlist auto-merges every registered ACF
// options-page key — mirrored here so the key is genuinely in the shared allowlist before
// the per-user subtraction runs. Without this the assertion below would pass on an
// allowlist miss and prove nothing.
$GLOBALS['bws_filters']['generateblocks_dynamic_tags_allowed_options'] = static fn( $v ) => array_merge( (array) $v, array( 'options_org_phone' ) );

// The allowlist half first, so the carve-out assertion cannot be satisfied by the key
// simply never having been readable.
$GLOBALS['bws_caps'] = array( 'manage_options' => true );
assert_same( 'the ACF options key IS readable by a trusted user', true, bws_gb_option_allowed_for_current_user( 'options_org_phone' ) );
$GLOBALS['bws_caps'] = array();

assert_same(
	'an ACF options-page key allowed for everyone is still refused for an untrusted user',
	false,
	bws_gb_option_allowed_for_current_user( 'options_org_phone' )
);
assert_same( 'and the core keys beside it are unaffected', true, bws_gb_option_allowed_for_current_user( 'home' ) );

// Pro's per-user filter is the documented way back for one key. It runs AFTER the
// subtraction, so a site can restore a specific ACF key without restoring the class.
$GLOBALS['bws_filters']['generateblocks_dynamic_tags_allowed_options_for_current_user'] = static fn( $v ) => array_merge( (array) $v, array( 'options_org_phone' ) );
assert_same(
	'Pro\'s per-user filter can put one carved-out key back',
	true,
	bws_gb_option_allowed_for_current_user( 'options_org_phone' )
);
unset( $GLOBALS['bws_filters']['generateblocks_dynamic_tags_allowed_options_for_current_user'] );

// A TRUSTED USER NEVER REACHES ANY OF THAT. Asked of a trusted user the key question can
// only answer true, so the derive is skipped — asserted against the same key that was just
// refused, which is what makes this a short-circuit rather than a coincidence.
$GLOBALS['bws_caps'] = array( 'manage_options' => true );
assert_same(
	'a trusted user short-circuits the key question entirely',
	true,
	bws_gb_option_allowed_for_current_user( 'options_org_phone' )
);
$GLOBALS['bws_caps'] = array();
unset( $GLOBALS['bws_filters']['generateblocks_dynamic_tags_allowed_options'] );

echo "\n§T3 — GB present: the class tier, then the function tier, each taking precedence\n";

// From here the world only gains symbols. See the header: this order is load-bearing.
if ( ! class_exists( 'GenerateBlocks_Dynamic_Tag_Security' ) ) {
	class GenerateBlocks_Dynamic_Tag_Security {
		/** @var bool */
		public static $answer = true;

		public static function user_can_author_dynamic_data() {
			return self::$answer;
		}
	}
}

GenerateBlocks_Dynamic_Tag_Security::$answer = true;
assert_same( 'GB\'s class answer is taken over our default', true, bws_gb_user_can_author_dynamic_data() );

// The stub holds NO capabilities, so a `true` here cannot have come from the default path.
GenerateBlocks_Dynamic_Tag_Security::$answer = false;
$GLOBALS['bws_caps'] = array( 'manage_options' => true );
assert_same( 'GB\'s class answer WINS over our default, it is not ORed with it', false, bws_gb_user_can_author_dynamic_data() );
$GLOBALS['bws_caps'] = array();

if ( ! function_exists( 'generateblocks_user_can_author_dynamic_data' ) ) {
	function generateblocks_user_can_author_dynamic_data() {
		return $GLOBALS['bws_gb_fn_answer'];
	}
}

// GB's global wrapper is the most direct probe and outranks the class — asserted by making
// the two DISAGREE, which is the only way to see which one was consulted.
$GLOBALS['bws_gb_fn_answer'] = true;
GenerateBlocks_Dynamic_Tag_Security::$answer = false;
assert_same( 'GB\'s global function outranks the class', true, bws_gb_user_can_author_dynamic_data() );

$GLOBALS['bws_gb_fn_answer'] = false;
GenerateBlocks_Dynamic_Tag_Security::$answer = true;
assert_same( 'and it outranks it in the other direction too', false, bws_gb_user_can_author_dynamic_data() );

echo "\n§T4 — GB Pro present: its own method is used, and the copy records its source\n";

if ( ! class_exists( 'GenerateBlocks_Pro_Dynamic_Tags_Register' ) ) {
	class GenerateBlocks_Pro_Dynamic_Tags_Register {
		/** @var array<int,string> */
		public static array $asked = array();

		public static function is_option_allowed_for_current_user( $key ) {
			self::$asked[] = $key;
			return 'pro_says_yes' === $key;
		}
	}
}

// Untrusted, so the short-circuit does not hide which path ran.
$GLOBALS['bws_gb_fn_answer'] = false;
GenerateBlocks_Pro_Dynamic_Tags_Register::$asked = array();

assert_same( 'Pro\'s own answer is taken', true, bws_gb_option_allowed_for_current_user( 'pro_says_yes' ) );
assert_same( 'including when Pro says no to a key our fallback would have allowed', false, bws_gb_option_allowed_for_current_user( 'blogname' ) );
assert_same( 'and Pro was actually asked, with the key unmangled', array( 'pro_says_yes', 'blogname' ), GenerateBlocks_Pro_Dynamic_Tags_Register::$asked );

// The dot-notated key goes to Pro WHOLE. Pro resolves the root segment itself, and splitting
// it here would be a second copy of a grammar rule we deliberately left at Pro.
GenerateBlocks_Pro_Dynamic_Tags_Register::$asked = array();
bws_gb_option_allowed_for_current_user( 'parent.child' );
assert_same( 'a dot-notated key reaches Pro unsplit', array( 'parent.child' ), GenerateBlocks_Pro_Dynamic_Tags_Register::$asked );

// Cross-file: the fallback copy says which Pro it was read from; the blueprint's env record
// says which Pro the fixture baseline was captured against. Disagreement means the copy
// mirrors a GB Pro nothing is being tested on. Only the FALLBACK is at stake — the primary
// path calls Pro and cannot drift.
$env = require $root . '/tools/fixtures/core-structures/env-versions.php';
$pro = $env['plugins']['generateblocks-pro/plugin.php']['version'] ?? null;

assert_same( 'BWS_GB_TRUST_FALLBACK_READ_FROM is defined', true, defined( 'BWS_GB_TRUST_FALLBACK_READ_FROM' ) );
assert_same( 'env-versions.php records a GB Pro version', true, is_string( $pro ) && '' !== $pro );
assert_same(
	"the fallback derive was read from the GB Pro the baseline was captured under ({$pro})",
	$pro,
	BWS_GB_TRUST_FALLBACK_READ_FROM
);

echo "\n§T5 — NOTHING outside the boundary asks GenerateBlocks the trust question\n";

// The sections above hold what the seam DOES. This one holds that it is still the only
// door. A site that probes GB itself gets a second, independently drifting copy of the
// guard order and the fallback — a regression that renders identically everywhere and that
// no behavior assertion above can see.
//
// THE PATTERN NAMES GB'S SYMBOLS, NOT THE BARE PREDICATE NAME. Our own wrapper is
// bws_gb_user_can_author_dynamic_data(), so a substring match would flag every legitimate
// CALLER of the seam — the opposite of the intent.
//
// COMMENTS COUNT, as they do in the sibling census. Naming GB's symbols belongs to the
// boundary file; anywhere else a mention is either a call or a sentence that has outlived
// the call it described. The fix for a comment that genuinely needs to say it is a new
// $exempt row with the reason written out, never a looser pattern.
//
// SCOPE INCLUDES tools/, unlike gb-output-boundary-test.php's census. That is the whole
// reason the exemption list below is interesting rather than bookkeeping: the pins ask GB
// directly ON PURPOSE, and this is the one place that intent is written down.

$skip_dirs = array(
	'.git',
	'.scratch',
	'.claude',
	'deprecated-files',
	'docs',          // prose and design history; records what WAS true, by design.
	'libs',          // vendored third party (PUC); not ours to route.
	'node_modules',
);

/** Files that may name GB's trust symbols, with the reason. */
$exempt = array(
	// The boundary itself: the one site that is supposed to ask GB, and the one PHPDoc
	// that explains why the others must not.
	'includes/helpers/gb-trust-boundary.php',

	// The pins. verify.php drives GB's own predicate and its save gate to measure that
	// GB still covers our tags; routed through the seam they would measure the seam
	// instead, which is not the property. Its P2 arm also FILTERS the predicate, which
	// is the documented seam for making a trusted user untrusted.
	'tools/fixtures/core-structures/verify.php',

	// This file: the stubs above impersonate GB's symbols in order to test the seam
	// against them, and the census pattern itself is spelled out here.
	'tools/test/gb-trust-boundary-test.php',
);

$pattern = '~generateblocks_user_can_author_dynamic_data'
	. '|GenerateBlocks_Dynamic_Tag_Security\s*::\s*user_can_author_dynamic_data'
	. '|GenerateBlocks_Pro_Dynamic_Tags_Register\s*::\s*(?:is_option_allowed|get_allowed_options)_for_current_user~i';

$scan = static function ( string $dir ) use ( $skip_dirs, $pattern ): array {
	$hits  = array();
	$files = 0;
	$it    = new RecursiveIteratorIterator(
		new RecursiveCallbackFilterIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			static function ( $current ) use ( $skip_dirs ) {
				return ! ( $current->isDir() && in_array( $current->getFilename(), $skip_dirs, true ) );
			}
		)
	);
	foreach ( $it as $file ) {
		if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
			continue;
		}
		$files++;
		$rel   = strtr( substr( $file->getPathname(), strlen( $dir ) + 1 ), DIRECTORY_SEPARATOR, '/' );
		$lines = preg_split( '~\R~', (string) file_get_contents( $file->getPathname() ) );
		foreach ( $lines as $n => $line ) {
			if ( preg_match( $pattern, $line ) ) {
				$hits[] = array( $rel, $n + 1, trim( $line ) );
			}
		}
	}
	return array( $hits, $files );
};

list( $all_hits, $files_scanned ) = $scan( $root );

// NON-VACUITY FIRST. A scanner that read nothing, or a pattern that stopped matching, would
// report a clean tree — the one failure mode a "found nothing" assertion cannot distinguish
// from success. Both are pinned against files guaranteed to contain a real match.
assert_same( 'the scan reached the plugin source', true, $files_scanned > 20 );
assert_same(
	'the pattern still matches real code (the boundary file is found)',
	true,
	(bool) array_filter( $all_hits, static fn( $h ) => 'includes/helpers/gb-trust-boundary.php' === $h[0] )
);
assert_same(
	'the pattern still reaches tools/ (verify.php\'s pins are found)',
	true,
	(bool) array_filter( $all_hits, static fn( $h ) => 'tools/fixtures/core-structures/verify.php' === $h[0] )
);

// An exemption for a file that no longer exists is a hole nobody can see: the pattern it
// excused is gone, but the row stays and would excuse a NEW file that took the same path.
foreach ( $exempt as $path ) {
	assert_same( "the exemption `{$path}` still names a real file", true, is_file( $root . '/' . $path ) );
}

$offenders = array_values( array_filter( $all_hits, static fn( $h ) => ! in_array( $h[0], $exempt, true ) ) );

assert_same( 'no file outside the boundary asks GB the trust question', 0, count( $offenders ) );

foreach ( $offenders as $h ) {
	echo "       {$h[0]}:{$h[1]}  {$h[2]}\n";
}
if ( $offenders ) {
	echo "       route each of these through bws_gb_user_can_author_dynamic_data() or\n";
	echo "       bws_gb_option_allowed_for_current_user(), or add the file to \$exempt\n";
	echo "       above WITH the reason written out.\n";
	echo "       ({$files_scanned} php files scanned.)\n";
}

echo "\n";
if ( $failures ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
