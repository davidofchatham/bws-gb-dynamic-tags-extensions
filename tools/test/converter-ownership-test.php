<?php
/**
 * Standalone unit harness for the converter's OWNERSHIP GUARD (FW-39, ticket 10).
 *
 * The real file is loaded, not copied: `includes/helpers/converter-ownership.php` declares
 * two consts and two functions behind an ABSPATH check and touches nothing at load, so it
 * loads on its own with no WordPress, no GenerateBlocks and no database. That it CAN is
 * half of what this file proves — the predicate every content rewrite passes has to be
 * drivable on every change, not only on a site. Nothing else is required until §O6, which
 * is deliberate: §O1-O5 running first is what makes "no WordPress present" a measurement
 * rather than a claim.
 *
 * WHY IT EXISTS — THE GUARD FAILS OPEN. bws_converter_tag_ownership() is the one mechanism
 * in this ship that edits live post content on a wrong answer, and every way it can break
 * breaks the same direction: a refusal that stops firing returns "ours", the converter
 * rewrites, the run reports success, and nothing is visibly different until an author opens
 * a page and finds a tag reading from a source that was never theirs. There is no crash to
 * catch and no diff to notice, so there is nothing but a test.
 *
 * §O4 IS A CENSUS, not more cases. The cases below cover the four states that exist today;
 * the census re-reads the shipped file and requires every reason it can return to be listed
 * in the enum, every listed reason to be reachable, and every one of them to be DRIVEN by a
 * case in this file. So a fifth reason added later fails here by name, with no case yet
 * written for it — which is the property the cases on their own do not have. The scan
 * report has one line of wording per reason (ticket 12), and a reason arriving there with
 * no wording prints nothing at all, which reads exactly like a tag that converted.
 *
 * WHAT A PASS HERE DOES NOT PROVE. §O1-O3 drive the PREDICATE with fabricated facts, and
 * nothing here calls bws_converter_rewrite_allowed() at all — it needs an options table and
 * the migration registry. §O5 reads its source, §O6 reads the real vocabulary it feeds, §O7
 * counts the converter's rewrite loops against the one place that asks; none of the three
 * runs a conversion. Whether the collision record itself is correct is
 * gb-registration-boundary.php's, pinned by control-order-test.php §9/§10.
 *
 * Run:  php tools/test/converter-ownership-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

$root   = dirname( __DIR__, 2 );
$shipped = $root . '/includes/helpers/converter-ownership.php';

require $shipped;

$failures = 0;
$count    = 0;

/** Every reason any drive below produced — §O4's census reads this. */
$reasons_driven = array();

function check( string $label, bool $ok, string $detail = '' ): void {
	global $failures, $count;
	$count++;
	if ( $ok ) {
		echo "  ok   {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL {$label}" . ( '' !== $detail ? " — {$detail}" : '' ) . "\n";
}

/**
 * Drive the guard and record the reason, so the census knows this case ran.
 *
 * Facts default to the innocent shape — nothing contested, nothing unknown, nobody opted
 * in — and each case overrides only what it is about. A case that reads as one line is a
 * case whose intent survives the next person editing it.
 */
function decide( array $facts ): array {
	global $reasons_driven;

	$out = bws_converter_tag_ownership(
		$facts['tag'] ?? 'term_title',
		$facts['collisions'] ?? array(),
		$facts['option_keys'] ?? array(),
		$facts['known_options'] ?? array( 'src', 'use', 'key', 'fallback' ),
		$facts['opted_in'] ?? array()
	);

	$reasons_driven[ $out['reason'] ] = true;

	return $out;
}

/** A collision record in bws_gb_tag_name_collisions() shape, for one name. */
function record( string $tag, string $outcome ): array {
	return array( $tag => array( 'tag' => $tag, 'outcome' => $outcome, 'title' => '' ) );
}

// ---------------------------------------------------------------------------
echo "§O1 — the four states\n";
// ---------------------------------------------------------------------------

$ours = decide( array( 'option_keys' => array( 'use', 'key' ) ) );
check( 'O1.1 nothing contested and every key known — rewrite, reason `ours`', true === $ours['rewrite'] && 'ours' === $ours['reason'] );

$yielded = decide( array( 'collisions' => record( 'term_title', 'yielded' ), 'option_keys' => array( 'use' ) ) );
check( 'O1.2 a name we stood down from — refuse, reason `name_not_ours`', false === $yielded['rewrite'] && 'name_not_ours' === $yielded['reason'] );

$foreign = decide( array( 'option_keys' => array( 'use', 'qe_mode' ) ) );
check( 'O1.3 a key the target template does not accept — refuse, reason `unknown_options`', false === $foreign['rewrite'] && 'unknown_options' === $foreign['reason'] );
check( 'O1.4 the refusal names the offending key, not every key', array( 'qe_mode' ) === $foreign['unknown_options'] );

$claimed = decide( array( 'collisions' => record( 'term_title', 'yielded' ), 'opted_in' => array( 'term_title' ) ) );
check( 'O1.5 the owner claimed the name — rewrite, reason `opted_in`', true === $claimed['rewrite'] && 'opted_in' === $claimed['reason'] );

// ---------------------------------------------------------------------------
echo "\n§O2 — the refusals are absolute, and the opt-in is the only override\n";
// ---------------------------------------------------------------------------
//
// THE INVARIANT, driven from both sides: the converter never rewrites a name we cannot
// prove we own without an explicit per-name opt-in. Each pair below breaks one fact and
// holds the rest innocent, so a failure names which half of the guard stopped working.

$clean_wire = decide( array( 'collisions' => record( 'term_title', 'yielded' ), 'option_keys' => array( 'use', 'key' ) ) );
check( 'O2.1 a yielded name refuses even when every option key is ours', false === $clean_wire['rewrite'] && 'name_not_ours' === $clean_wire['reason'] );

$bare = decide( array( 'collisions' => record( 'term_title', 'yielded' ), 'option_keys' => array() ) );
check( 'O2.2 a yielded name refuses even with no options at all', false === $bare['rewrite'] );

$lost = decide( array( 'collisions' => record( 'term_title', 'lost' ), 'option_keys' => array( 'use' ) ) );
check( 'O2.3 a name TAKEN FROM US refuses on the same reason as a name we yielded', false === $lost['rewrite'] && 'name_not_ours' === $lost['reason'] );

// 'kept' IS THE AXIS'S OTHER SIDE. We registered over a stranger, so our tag is the one
// rendering now and the record is not evidence against us. The content may still predate
// us, and nothing but the vocabulary net can see that — which is the whole reason the net
// exists rather than the record being the only gate.
$kept = decide( array( 'collisions' => record( 'term_title', 'kept' ), 'option_keys' => array( 'use' ) ) );
check( 'O2.4 a name we registered OVER a stranger is not refused by the record', true === $kept['rewrite'] && 'ours' === $kept['reason'] );

$kept_foreign = decide( array( 'collisions' => record( 'term_title', 'kept' ), 'option_keys' => array( 'qe_mode' ) ) );
check( 'O2.5 …and is still caught by the vocabulary net when the wire is foreign', false === $kept_foreign['rewrite'] && 'unknown_options' === $kept_foreign['reason'] );

// UNKNOWN VOCABULARY ONLY EVER REFUSES. No path reads "all keys known" as evidence of
// anything — O1.1 passes on the absence of a refusal, not on the presence of clean keys —
// so there is no arrangement of option keys that lifts the record's refusal. O2.1 is that
// assertion; this one is its mirror, that a clean record plus clean keys is still only
// 'ours' and never some stronger claim the report would have to word differently.
$net_alone = decide( array( 'option_keys' => array( 'src', 'use', 'key', 'fallback' ) ) );
check( 'O2.6 a fully known wire authorizes nothing beyond `ours`', 'ours' === $net_alone['reason'] );

$other_name = decide( array( 'collisions' => record( 'term_title', 'yielded' ), 'opted_in' => array( 'term_content' ) ) );
check( 'O2.7 the opt-in is PER NAME — claiming term_content does not release term_title', false === $other_name['rewrite'] && 'name_not_ours' === $other_name['reason'] );

$optin_vocab = decide( array( 'option_keys' => array( 'qe_mode' ), 'opted_in' => array( 'term_title' ) ) );
check( 'O2.8 the opt-in releases the vocabulary refusal too — one claim, both gates', true === $optin_vocab['rewrite'] && 'opted_in' === $optin_vocab['reason'] );

$optin_idle = decide( array( 'option_keys' => array( 'use' ), 'opted_in' => array( 'term_title' ) ) );
check( 'O2.9 an opt-in on an UNCONTESTED name reports `ours`, not `opted_in`', 'ours' === $optin_idle['reason'], 'the reason names the fact that decided, and here nothing was overridden' );

// ---------------------------------------------------------------------------
echo "\n§O3 — the fail-closed direction\n";
// ---------------------------------------------------------------------------
//
// An EMPTY known-option set is what an absent GB, an unregistered target tag or a disabled
// tag family produces. Every key is then unknown, so the guard refuses. The opposite
// reading — "we know of no options, so nothing can be foreign" — is the fail-open shape
// this whole file exists to catch, and it is one `if ( $known_options )` away.
$no_vocab = decide( array( 'option_keys' => array( 'use' ), 'known_options' => array() ) );
check( 'O3.1 no known vocabulary refuses rather than waves through', false === $no_vocab['rewrite'] && 'unknown_options' === $no_vocab['reason'] );

// The one shape where an empty known set is silent, and correctly so: there are no keys to
// be foreign. The record is still the gate, so this is not a hole.
$nothing_at_all = decide( array( 'known_options' => array(), 'option_keys' => array() ) );
check( 'O3.2 a bare tag with no vocabulary either way is decided by the record alone', true === $nothing_at_all['rewrite'] && 'ours' === $nothing_at_all['reason'] );

$nothing_yielded = decide( array( 'collisions' => record( 'term_title', 'yielded' ), 'known_options' => array(), 'option_keys' => array() ) );
check( 'O3.3 …and that record still refuses', false === $nothing_yielded['rewrite'] );

// A record about a DIFFERENT name says nothing about this one. The record is keyed by tag
// name; reading it as "any collision anywhere" would refuse every migration on a site with
// one contested tag.
$unrelated = decide( array( 'collisions' => record( 'term_content', 'yielded' ), 'option_keys' => array( 'use' ) ) );
check( 'O3.4 another name\'s collision does not refuse this one', true === $unrelated['rewrite'] && 'ours' === $unrelated['reason'] );

// ---------------------------------------------------------------------------
echo "\n§O4 — the reason enum is closed, reachable and driven (census)\n";
// ---------------------------------------------------------------------------
//
// Three questions, and the third is the forward guard: what the list says, what the
// function can return, and whether anything above actually drives each member. The first
// two are a source read because the enum is a const and the returns are literals; the
// third reads what the cases produced, so it fails on a reason added with no case rather
// than on a reason added with no assertion about it.

$src = file_get_contents( $shipped );

preg_match( '/const BWS_CONVERTER_OWNERSHIP_REASONS = array\((.*?)\);/s', $src, $const_m );
preg_match_all( "/'([a-z_]+)'/", $const_m[1] ?? '', $listed_m );
$listed = $listed_m[1] ?? array();

preg_match( '/function bws_converter_tag_ownership\(.*?\n\}/s', $src, $fn_m );
preg_match_all( "/'reason'\s*=>\s*'([a-z_]+)'/", $fn_m[0] ?? '', $returned_m );
// The two refusals are returned through a variable, so the literals that feed it count too.
preg_match_all( "/\\\$refusal\s*=\s*'([a-z_]+)'/", $fn_m[0] ?? '', $refusal_m );
$returned = array_values( array_unique( array_merge( $returned_m[1] ?? array(), $refusal_m[1] ?? array() ) ) );
$returned = array_values( array_diff( $returned, array( 'reason' ) ) );

check( 'O4.1 the enum is non-empty and the predicate returns only members of it', array() !== $listed && array() !== $returned && array() === array_diff( $returned, $listed ), 'listed: ' . implode( ',', $listed ) . '  returned: ' . implode( ',', $returned ) );

check( 'O4.2 every listed reason is one the predicate can actually return', array() === array_diff( $listed, $returned ), 'unreachable: ' . implode( ',', array_diff( $listed, $returned ) ) );

$driven = array_keys( $reasons_driven );
sort( $driven );
$expected = $listed;
sort( $expected );
check( 'O4.3 every listed reason is DRIVEN by a case in this file', $expected === $driven, 'undriven: ' . implode( ',', array_diff( $listed, $driven ) ) );

check( 'O4.4 the enum holds exactly the four states the ship decided on', array( 'ours', 'opted_in', 'name_not_ours', 'unknown_options' ) === $listed, 'a fifth state is a report surface change, not a drive-by — see D38' );

// ---------------------------------------------------------------------------
echo "\n§O5 — the predicate is pure, and the gatherer is the only half that is not\n";
// ---------------------------------------------------------------------------
//
// The load above is the real proof of purity: this file defines ABSPATH and nothing else,
// and every §O1-O4 drive ran. What a source read adds is the FORWARD half — a WordPress
// call added to the predicate later would still pass every case above, because the cases
// never reach the branch that would need one. So the body is read for the shapes that
// would make it unreachable from here.

$body = $fn_m[0] ?? '';
preg_match_all( '/\b(get_option|update_option|apply_filters|do_action|get_post|__|esc_html|wp_[a-z_]+)\s*\(/', $body, $wp_m );
check( 'O5.1 the predicate calls no WordPress function', array() === ( $wp_m[1] ?? array() ), 'found: ' . implode( ',', array_unique( $wp_m[1] ?? array() ) ) );

check( 'O5.2 the predicate reads no global or superglobal', 1 !== preg_match( '/\bglobal\s+\$|\$GLOBALS|\$_(GET|POST|SERVER|REQUEST)/', $body ) );

// THE GATHERER'S TWO STATIC FACTS. It needs GB and an options table, so nothing here drives
// it — but both of these are one deletion away from silently widening what gets rewritten,
// and both are readable without running anything.
preg_match( '/function bws_converter_rewrite_allowed\(.*?\n\}/s', $src, $gather_m );
$gatherer = $gather_m[0] ?? '';

check( 'O5.3 the gatherer checks the STORED name against the collision record, not the migrated one', 1 === preg_match( '/bws_converter_tag_ownership\(\s*\$old_tag,/s', $gatherer ), 'ownership is a claim about strings already in content, and those bear the old name' );

check( 'O5.4 known vocabulary includes the keys GB\'s own output pipeline consumes', 1 === preg_match( '/BWS_GB_TAG_OUTPUT_OPTIONS/', $gatherer ), 'without them an ordinary {{text …|link:post}} reads as foreign wire and refuses' );

check( 'O5.5 the opt-in is read from the named option, never inferred', 1 === preg_match( '/get_option\(\s*BWS_CONVERTER_OWNERSHIP_OPTIN_OPTION/', $gatherer ) );

check( 'O5.6 known vocabulary is the CANONICAL KEY MAP, not a tag\'s registered options', 1 === preg_match( '/bws_serialization_order_key_map\(\)/', $gatherer ) && 1 !== preg_match( '/GenerateBlocks_Register_Dynamic_Tag/', $gatherer ), 'a registered option set answers which controls the editor paints, which is narrower — see the function\'s own PHPDoc' );

check( 'O5.7 the slot prefix is stripped before the keys are weighed', 1 === preg_match( '/bws_serialization_order_parse_slot\(/', $gatherer ), '`2-key` is `key` at slot 2' );

// ---------------------------------------------------------------------------
echo "\n§O6 — the REAL known set, against wire the converter actually emits\n";
// ---------------------------------------------------------------------------
//
// §O1-O3 drive the predicate with a fabricated vocabulary, which holds the RULE and can say
// nothing about the set the gatherer feeds it. This section loads the real one.
//
// WHAT IT GUARDS IS A FALSE REFUSAL, the failure running opposite to every other assertion
// here: not content rewritten wrongly, but a scanner that quietly stops converting anything.
// The keys below are all on wire the shipped transforms EMIT, measured 2026-09-11 against
// the real registration pass — and `limit` is why the set is the key map rather than a tag's
// registered options, because ADR 0004 (removing a control never removes an option) left it
// legal, read and unregistered on the base tags since 1.17.0 (#62). Four of nine ordinary
// conversions refused before the set moved. Drop any of these from the key map and the
// converter starts declining wire it wrote itself.

// Loaded HERE rather than beside the guard at the top, so §O1-O5 prove what they claim:
// the predicate ran with nothing but its own file on disk.
require $root . '/includes/helpers/gb-output-boundary.php';
require $root . '/includes/helpers/serialization-order.php';
require $root . '/includes/helpers/slot-fold.php';

$real_known = array_merge( array_keys( bws_serialization_order_key_map() ), BWS_GB_TAG_OUTPUT_OPTIONS );

foreach ( array(
	'limit' => '{{term_text key:phone|limit:2}} converts carrying it',
	'size'  => '{{term_image size:large}} converts carrying it',
	'as'    => 'the image as/size fold emits it',
	'src'   => 'every modifier conversion emits it',
	'key'   => 'the field read',
	'link'  => 'GB paints the control; no option array of ours holds it',
	'id'    => 'GB resolves the entity from it',
	'sep'   => 'a list joiner survives conversion untouched',
) as $key => $why ) {
	check( "O6.1 `{$key}` is known vocabulary — {$why}", in_array( $key, $real_known, true ) );
}

// The slot forms, through the same strip the gatherer uses. A folded slot key decodes to
// the empty bare name, which the key map carries an entry for; without the strip every
// join and try_ tag on the site would read as foreign wire.
foreach ( array( '2-key', '10-src', 'A', 'B' ) as $slot_key ) {
	[ , $bare ] = bws_serialization_order_parse_slot( $slot_key );
	check( "O6.2 slot key `{$slot_key}` strips to known vocabulary", in_array( $bare, $real_known, true ), "bare: '{$bare}'" );
}

// The other direction, so O6.1 cannot pass by the set having grown to everything: a key
// from nobody's vocabulary is still caught.
check( 'O6.3 a foreign key is still unknown against the real set', false === in_array( 'qe_mode', $real_known, true ) );

// ---------------------------------------------------------------------------
echo "\n§O7 — no rewrite path in the converter bypasses the guard\n";
// ---------------------------------------------------------------------------
//
// THE ONE JOB, checked structurally. Every assertion above holds what the guard ANSWERS; a
// loop that never asks it passes all of them, and that loop is the whole failure. So this
// counts rewrite loops in migrate_post() against calls to the method that asks, the same
// shape gb-output-boundary-test.php §B6 uses on GB's output call — a zero re-checked on
// every run rather than a zero someone read once.
//
// BY SOURCE SCAN, because the alternative is a live site. migrate_post() needs $wpdb, a
// real post and the whole migration registry; what is being held here is reachable in the
// text, and it fails on the edit that matters (a new rewrite loop) rather than on
// reformatting nearby.

$converter = file_get_contents( $root . '/includes/classes/admin/class-tag-converter.php' );

// COMMENTS STRIPPED FIRST, through PHP's own tokenizer. The names below are the subject of
// prose one line above the code that uses them — migrate_post()'s `@since` says the guard is
// asked, apply_if_owned()'s docblock says where — so counting raw text counts sentences as
// call sites and §O7.3 reads 2 for one call. A regex that excluded the mentions would be
// guessing at spacing; the tokenizer knows which bytes are code.
$code_only = implode(
	'',
	array_map(
		static function ( $token ) {
			if ( ! is_array( $token ) ) {
				return $token;
			}
			return in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $token[1];
		},
		token_get_all( $converter )
	)
);

preg_match( '/public static function migrate_post\(.*?\n\t\}/s', $code_only, $mp_m );
$migrate = $mp_m[0] ?? '';

check( 'O7.1 the scan reached migrate_post()', '' !== $migrate && false !== strpos( $migrate, 'wpdb->update' ) );

$loops  = preg_match_all( '/preg_replace_callback\(/', $migrate );
$gated  = preg_match_all( '/self::apply_if_owned\(/', $migrate );

check( 'O7.2 every content-rewriting loop routes through the guard', $loops > 0 && $loops === $gated, "rewrite loops: {$loops}, gated: {$gated}" );

// The guard is asked in ONE place in this class. A second caller is not wrong in itself,
// but it is a second thing to keep correct, and §O7.2 counts loops rather than callers —
// so a rewrite added beside an existing gated one would slip past it.
check( 'O7.3 the class asks the guard from exactly one place', 1 === preg_match_all( '/bws_converter_rewrite_allowed\(/', $code_only ), 'found: ' . preg_match_all( '/bws_converter_rewrite_allowed\(/', $code_only ) );

// scan() is read-only and correctly ungated (D46 splits the report surface off to its own
// ticket). Pinned so "ungated" stays a decision rather than becoming an oversight.
preg_match( '/public static function scan\(\).*?\n\t\}/s', $code_only, $scan_m );
check( 'O7.4 scan() writes no content, so it is ungated by design', false === strpos( $scan_m[0] ?? '', 'wpdb->update' ) );

// ---------------------------------------------------------------------------
echo "\n";
if ( $failures ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
