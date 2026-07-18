<?php
/**
 * Standalone unit harness for the editor preview-label builders in
 * includes/helpers/preview-helpers.php.
 *
 * No WordPress required — the label assembly is pure string logic. The only WP
 * symbols the covered paths touch are esc_html(), get_taxonomy(), and
 * apply_filters(), all shimmed below with deterministic stubs.
 *
 * SCOPE — deterministic label assembly only:
 *   bws_try_preview_template_label()      (template-name labels)
 *   bws_try_preview_field_part()          (mode-value / quoted-key field parts)
 *   bws_try_preview_source_part()         (source segments + tax hop)
 *   bws_wrap_preview_label_with_link()    (link annotation + <a> wrap)
 *   bws_build_preview_label()             (base/modifier tags, NON-datetime)
 *   bws_build_try_preview_label()         (try_ slot chains, NON-datetime)
 *
 * EXCLUDED — datetime templates (datetime_single / datetime_range). Those branches
 * call wp_date()/DateTime('now')/bws_format_date_range() against the live clock and
 * WP timezone, so their output is non-deterministic and not worth string-exact
 * asserts here. Cover datetime formatting separately if/when bws_format_date_range
 * gets its own harness.
 *
 * Run:
 *   php tools/test/preview-label-test.php
 *
 * Exit 0 = all pass, 1 = any failure.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

// preview-helpers.php top-level is ABSPATH-guarded and makes no WP calls at parse,
// so a bare define + the three runtime shims below are all it needs.
define( 'ABSPATH', __DIR__ );

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}

// apply_filters: identity passthrough. The only filtered value the covered paths
// read is the modifier map, and tests assert against the built-in default, so a
// passthrough (return $value) is exactly the production default behaviour.
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) { return $value; }
}

// get_taxonomy: return a stub object whose labels->singular_name is a Title-cased
// version of the slug ('event_category' → 'Event Category'). Returns false for the
// sentinel '__unknown__' so the "tax not registered → fall back to raw slug" branch
// is exercised.
if ( ! function_exists( 'get_taxonomy' ) ) {
	function get_taxonomy( $slug ) {
		if ( '__unknown__' === $slug ) {
			return false;
		}
		$singular = ucwords( str_replace( [ '_', '-' ], ' ', (string) $slug ) );
		return (object) [ 'labels' => (object) [ 'singular_name' => $singular ] ];
	}
}

require __DIR__ . '/../../includes/helpers/preview-helpers.php';

$failures = 0;
$count    = 0;

/**
 * Assert a builder's return equals an expected string.
 *
 * @param string $label  Case label.
 * @param string $got    Actual builder return.
 * @param string $expect Expected string.
 */
function check( string $label, string $got, string $expect ): void {
	global $failures, $count;
	$count++;
	if ( $got === $expect ) {
		echo "  ok   {$label}\n";
	} else {
		$failures++;
		echo "  FAIL {$label}\n";
		echo "       expected '{$expect}'\n       got      '{$got}'\n";
	}
}

// ---------------------------------------------------------------------------
echo "template_label — per-template name (text empty; image as-suffix)\n";
check( 'text → empty',        bws_try_preview_template_label( 'text', '' ),         '' );
check( 'content',             bws_try_preview_template_label( 'content', '' ),      'Content' );
check( 'image alt',           bws_try_preview_template_label( 'image', 'alt' ),     'Image Alt Text' );
check( 'image caption',       bws_try_preview_template_label( 'image', 'caption' ), 'Image Caption' );
check( 'title',               bws_try_preview_template_label( 'title', '' ),        'Title' );
check( 'permalink',           bws_try_preview_template_label( 'permalink', '' ),    'Permalink' );

// ---------------------------------------------------------------------------
echo "\nfield_part — mode-values capitalized; user keys single-quoted\n";
check( 'text title mode',     bws_try_preview_field_part( 'text', 'title', '', '' ),    'Title' );
check( 'text key mode',       bws_try_preview_field_part( 'text', 'key', 'sku', '' ),   "'sku'" );
check( 'content excerpt',     bws_try_preview_field_part( 'content', 'excerpt', '', '' ),'Excerpt' );
check( 'content key',         bws_try_preview_field_part( 'content', 'key', 'body', '' ),"'body'" );
check( 'content default',     bws_try_preview_field_part( 'content', 'content', '', '' ),'Content' );
check( 'image featured',      bws_try_preview_field_part( 'image', 'featured', '', '' ), 'Featured' );
check( 'image key',           bws_try_preview_field_part( 'image', 'key', 'hero', '' ),  "'hero'" );
check( 'title',               bws_try_preview_field_part( 'title', '', '', '' ),         'Title' );
check( 'permalink',           bws_try_preview_field_part( 'permalink', '', '', '' ),     'Permalink' );

// ---------------------------------------------------------------------------
echo "\nsource_part — ref quoting, named-current gate, tax hop arrow\n";
check( 'current unnamed → empty',  bws_try_preview_source_part( 'current', '', '', false ), '' );
check( 'current named',            bws_try_preview_source_part( 'current', '', '', true ),  'Current' );
check( 'ref quoted',               bws_try_preview_source_part( 'ref', 'rel_post', '', false ), "Ref 'rel_post'" );
check( 'ref + tax hop',            bws_try_preview_source_part( 'ref', 'rel_post', 'event_category', false ), "Ref 'rel_post' → Event Category Term" );
check( 'tax hop, current named',   bws_try_preview_source_part( 'current', '', 'event_category', true ),      'Current → Event Category Term' );
check( 'unknown tax → raw slug',   bws_try_preview_source_part( 'ref', 'r', '__unknown__', false ),           "Ref 'r' → __unknown__ Term" );

// ---------------------------------------------------------------------------
echo "\nwrap_link — annotation injection + <a> wrap, gated on linkTo\n";
check( 'empty in → empty out',  bws_wrap_preview_label_with_link( '', [ 'linkTo' => 'permalink' ] ), '' );
check( 'linkTo none → no wrap', bws_wrap_preview_label_with_link( '[Title]', [ 'linkTo' => 'none' ] ), '[Title]' );
check( 'linkTo missing → no wrap', bws_wrap_preview_label_with_link( '[Title]', [] ), '[Title]' );
check(
	'linkTo permalink',
	bws_wrap_preview_label_with_link( '[Title]', [ 'linkTo' => 'permalink' ] ),
	'<a href="#">[Title (link: permalink)]</a>'
);
check(
	'linkTo key w/ key',
	bws_wrap_preview_label_with_link( '[Title]', [ 'linkTo' => 'key', 'linkKey' => 'url_meta' ] ),
	'<a href="#">[Title (link: \'url_meta\')]</a>'
);
check(
	'linkTo key no key',
	bws_wrap_preview_label_with_link( '[Title]', [ 'linkTo' => 'key' ] ),
	'<a href="#">[Title (link: key)]</a>'
);
check(
	'newTab adds target/rel',
	bws_wrap_preview_label_with_link( '[Title]', [ 'linkTo' => 'permalink', 'newTab' => true ] ),
	'<a href="#" target="_blank" rel="noopener noreferrer">[Title (link: permalink)]</a>'
);

// ---------------------------------------------------------------------------
echo "\nbuild_preview_label — base & modifier tags (non-datetime)\n";
// Text base, current source, key set → bare quoted key, no 'from' segment.
check(
	'text current key',
	bws_build_preview_label( [ 'key' => 'sku' ], 'text' ),
	"['sku']"
);
// Text title mode, no key needed.
check(
	'text title mode',
	bws_build_preview_label( [ 'use' => 'title' ], 'text' ),
	'[Title]'
);
// Content default (use defaults to 'content') → bare 'Content'.
check(
	'content default',
	bws_build_preview_label( [], 'content' ),
	'[Content]'
);
// Content key mode.
check(
	'content key mode',
	bws_build_preview_label( [ 'use' => 'key', 'key' => 'body' ], 'content' ),
	"[Content: 'body']"
);
// Ref source appends quoted ref as context.
check(
	'text ref source',
	bws_build_preview_label( [ 'src' => 'ref', 'ref' => 'rel', 'key' => 'sku' ], 'text' ),
	"['sku' from Ref 'rel']"
);
// term_ modifier prefix → base resolves to text, modifier label 'Term'.
check(
	'term_text current',
	bws_build_preview_label( [ 'key' => 'sku' ], 'term_text' ),
	"['sku' from Term]"
);
// term_ modifier with explicit tax → tax name merged into Term segment, no arrow.
check(
	'term_text w/ tax',
	bws_build_preview_label( [ 'key' => 'sku', 'tax' => 'event_category' ], 'term_text' ),
	"['sku' from Event Category Term]"
);
// #37: hand-typed src:site on a term_ modifier → invalid-combo warning (matches the
// empty frontend; the src dropdown filters site, but a hand-typed value slips it).
check(
	'term_text src:site → invalid-combo warning',
	bws_build_preview_label( [ 'src' => 'site', 'use' => 'key', 'key' => 'blogdescription' ], 'term_text' ),
	'[⚠ Site source not valid on Term tag — use the base tag]'
);
// Warning still appends fallback note when one is set.
check(
	'term_text src:site warning + fallback',
	bws_build_preview_label( [ 'src' => 'site', 'key' => 'x', 'fallback' => 'N/A' ], 'term_text' ),
	'[⚠ Site source not valid on Term tag — use the base tag (fallback: “N/A”)]'
);
// Base (non-modifier) text src:site is VALID — no warning, normal label.
check(
	'text src:site (base) → from Site, no warning',
	bws_build_preview_label( [ 'src' => 'site', 'use' => 'key', 'key' => 'blogdescription' ], 'text' ),
	"['blogdescription' from Site]"
);
// Cross-source base with srcTermIn, no other context → tax segment WITHOUT arrow
// (the '→' prefix is added only when the hop follows another segment; standalone
// current-post→term drops it — see bws_build_preview_label line ~599).
check(
	'text srcTermIn hop (standalone, no arrow)',
	bws_build_preview_label( [ 'key' => 'sku', 'srcTermIn' => 'event_category' ], 'text' ),
	"['sku' from Event Category Term]"
);
// With a ref segment ahead of it, the hop DOES take the arrow.
check(
	'text ref + srcTermIn hop (arrow)',
	bws_build_preview_label( [ 'src' => 'ref', 'ref' => 'rel', 'key' => 'sku', 'srcTermIn' => 'event_category' ], 'text' ),
	"['sku' from Ref 'rel' → Event Category Term]"
);
// Email/phone key-required warning.
check(
	'email no key → warn',
	bws_build_preview_label( [], 'email' ),
	'[⚠ No field key set]'
);
check(
	'email w/ key',
	bws_build_preview_label( [ 'key' => 'work_email' ], 'email' ),
	"[Email: 'work_email']"
);
check(
	'phone w/ key',
	bws_build_preview_label( [ 'key' => 'mobile' ], 'phone' ),
	"[Phone: 'mobile']"
);
// Base email src:site → 'from Site' context segment (#37 preview parity).
check(
	'email src:site (base) → from Site',
	bws_build_preview_label( [ 'src' => 'site', 'key' => 'org_email' ], 'email' ),
	"[Email: 'org_email' from Site]"
);
// {{call}} INERT config-describing preview (VC-inert) — never executes the fn.
check(
	'call no fn → warn',
	bws_build_preview_label( [], 'call' ),
	'[⚠ No function set]'
);
check(
	'call w/ fn',
	bws_build_preview_label( [ 'fn' => 'bws_get_game_result' ], 'call' ),
	'[Function: bws_get_game_result]'
);
check(
	'call w/ fn + arg',
	bws_build_preview_label( [ 'fn' => 'get_game_date_for_display', 'arg' => 'short' ], 'call' ),
	'[Function: get_game_date_for_display (short)]'
);
check(
	'call w/ fn from ref source',
	bws_build_preview_label( [ 'src' => 'ref', 'ref' => 'games', 'fn' => 'bws_get_game_result' ], 'call' ),
	"[Function: bws_get_game_result from Ref 'games']"
);
// Missing-required warnings (text needs key unless title mode).
check(
	'text no key → warn',
	bws_build_preview_label( [], 'text' ),
	'[⚠ No meta key set]'
);
// ref source missing ref + missing key → two-item warning.
check(
	'two missing → "or"',
	bws_build_preview_label( [ 'src' => 'ref' ], 'text' ),
	'[⚠ No ref key or meta key set]'
);
// Fallback annotation appended inside brackets.
check(
	'fallback annotation',
	bws_build_preview_label( [ 'key' => 'sku', 'fallback' => 'N/A' ], 'text' ),
	"['sku' (fallback: “N/A”)]"
);
// Image non-attribute mode (no as) → excluded, empty string.
check(
	'image no-as → excluded',
	bws_build_preview_label( [ 'key' => 'hero' ], 'image' ),
	''
);
// Image alt mode → label.
check(
	'image alt featured',
	bws_build_preview_label( [ 'use' => 'featured', 'as' => 'alt' ], 'image' ),
	'[Image Alt Text: Featured]'
);
// Link wrap composes over the assembled label.
check(
	'text + link wrap',
	bws_build_preview_label( [ 'key' => 'sku', 'linkTo' => 'permalink' ], 'text' ),
	"<a href=\"#\">['sku' (link: permalink)]</a>"
);

// ---------------------------------------------------------------------------
echo "\nbuild_try_preview_label — slot chains (non-datetime)\n";
// Empty options on text: slot 1 is always processed (default-filled), so for text
// the "no slots configured" branch is unreachable — slot 1 with no key (and use!=title)
// trips the missing-key warning first. This asserts that actual fired warning.
check(
	'text empty → slot 1 no key warn',
	bws_build_try_preview_label( [], 'text' ),
	'[⚠ Try: slot 1 no key]'
);
// Single text slot, key set → bare field part (text has no template label).
check(
	'single text slot',
	bws_build_try_preview_label( [ 'key' => 'sku' ], 'text' ),
	"[Try 'sku']"
);
// Two slots, same field, varying source → 'from <list>'.
check(
	'2 slots vary source',
	bws_build_try_preview_label(
		[ 'key' => 'sku', '2-src' => 'ref', '2-ref' => 'rel' ],
		'text'
	),
	"[Try 'sku' from Current, Ref 'rel']"
);
// Two slots, same source, varying field → field list. NOTE: slot ≥2 with an empty
// `use` discards its `key` (use=same hides the key field), so a key-only override is
// wiped and the slot collapses. The override must carry an explicit `2-use` to count.
check(
	'2 slots vary field',
	bws_build_try_preview_label(
		[ 'key' => 'sku', '2-use' => 'key', '2-key' => 'alt_sku' ],
		'text'
	),
	"[Try 'sku', 'alt_sku']"
);
// Carry-forward: slot 2 omits key (use=same hides key), inherits slot 1.
// Only a source override on slot 2 → uniform field, varying source.
check(
	'carry-forward field',
	bws_build_try_preview_label(
		[ 'key' => 'sku', '2-src' => 'ref', '2-ref' => 'rel' ],
		'text'
	),
	"[Try 'sku' from Current, Ref 'rel']"
);
// Content single slot at template default → collapses to bare label.
check(
	'content default collapse',
	bws_build_try_preview_label( [], 'content' ),
	'[Try Content]'
);
// Title single → always-uniform bare label.
check(
	'try title',
	bws_build_try_preview_label( [ 'key' => 'x' ], 'title' ),
	'[Try Title]'
);
// Permalink excluded (URL context).
check(
	'try permalink → excluded',
	bws_build_try_preview_label( [], 'permalink' ),
	''
);
// Mixed: slot1 text key, slot2 ref + DIFFERENT key (with explicit 2-use so the key
// survives the use=same discard) → field AND source both vary → per-slot enumeration.
check(
	'mixed enumeration',
	bws_build_try_preview_label(
		[ 'key' => 'sku', '2-src' => 'ref', '2-ref' => 'rel', '2-use' => 'key', '2-key' => 'alt' ],
		'text'
	),
	"[Try 'sku' from Current, 'alt' from Ref 'rel']"
);
// Slot ref with no ref key → warning.
check(
	'slot ref no ref → warn',
	bws_build_try_preview_label(
		[ 'src' => 'ref' ],
		'text'
	),
	'[⚠ Try: slot 1 no ref, slot 1 no key]'
);
// Fallback annotation on try.
check(
	'try fallback',
	bws_build_try_preview_label( [ 'key' => 'sku', 'fallback' => 'N/A' ], 'text' ),
	"[Try 'sku' (fallback: “N/A”)]"
);

// --- email / phone try_ cases (#32 Phase 8 / #24: always key-mode, no no-key values) ---
// Empty key → warn (default key-mode, no native default field → unconfigured).
check(
	'try email empty key → warn',
	bws_build_try_preview_label( [], 'email' ),
	'[⚠ Try: slot 1 no key]'
);
check(
	'try phone empty key → warn',
	bws_build_try_preview_label( [], 'phone' ),
	'[⚠ Try: slot 1 no key]'
);
// Configured single slot → Try Email/Phone: 'key'.
check(
	'try email configured',
	bws_build_try_preview_label( [ 'key' => 'contact_email' ], 'email' ),
	"[Try Email: 'contact_email']"
);
check(
	'try phone configured',
	bws_build_try_preview_label( [ 'key' => 'tel' ], 'phone' ),
	"[Try Phone: 'tel']"
);
// Site slot resolves a key (site re-allowed for email/phone). Single uniform slot
// → source-part omitted, only the field shown (same shape as a current-source slot).
check(
	'try email site slot',
	bws_build_try_preview_label( [ 'src' => 'site', 'key' => 'admin_email' ], 'email' ),
	"[Try Email: 'admin_email']"
);

// ---------------------------------------------------------------------------
echo "\nbuild_join_preview_label — {{join}} combining tag\n";
// Separator mode, two key slots, default sep → bare field list, no sep note.
check(
	'sep mode two keys',
	bws_build_join_preview_label( [ 'key' => 'name_first', '2-key' => 'name_last' ] ),
	"[Join 'name_first', 'name_last']"
);
// Custom separator noted.
check(
	'sep mode custom sep',
	bws_build_join_preview_label( [ 'key' => 'name_first', '2-key' => 'name_last', 'sep' => ' ' ] ),
	"[Join 'name_first', 'name_last' (sep: “ ”)]"
);
// Title mode slot needs no key.
check(
	'title slot needs no key',
	bws_build_join_preview_label( [ 'use' => 'title', '2-key' => 'role' ] ),
	"[Join Title, 'role']"
);
// Template mode: format quoted, then field list.
check(
	'template mode',
	bws_build_join_preview_label( [ 'mode' => 'template', 'format' => '%1 (%2)', 'key' => 'name_first', '2-key' => 'name_last' ] ),
	"[Join “%1 (%2)”: 'name_first', 'name_last']"
);
// Template mode, no format → warning.
check(
	'template mode no format warns',
	bws_build_join_preview_label( [ 'mode' => 'template', 'key' => 'name_first' ] ),
	'[⚠ Join: no format set]'
);
// Slot missing key (key-mode) → warning.
check(
	'slot no key warns',
	bws_build_join_preview_label( [ 'key' => 'name_first', '2-use' => 'key' ] ),
	'[⚠ Join: slot 2 no key]'
);
// src:ref slot with no ref → warning.
check(
	'slot no ref warns',
	bws_build_join_preview_label( [ 'src' => 'ref', 'key' => 'name_first' ] ),
	'[⚠ Join: slot 1 no ref]'
);
// Non-current source appended per-slot.
check(
	'ref source appended',
	bws_build_join_preview_label( [ 'key' => 'name_first', '2-src' => 'ref', '2-ref' => 'rel_post', '2-key' => 'role' ] ),
	"[Join 'name_first', 'role' from Ref 'rel_post']"
);
// Fallback text appended.
check(
	'fallback appended',
	bws_build_join_preview_label( [ 'key' => 'name_first', '2-key' => 'name_last', 'fallback_text' => 'N/A' ] ),
	"[Join 'name_first', 'name_last' (fallback: “N/A”)]"
);
// Nothing configured → no preview.
check(
	'empty → no preview',
	bws_build_join_preview_label( [] ),
	''
);

echo "\n" . ( $failures ? "FAILED {$failures}/{$count}\n" : "PASSED {$count}/{$count}\n" );
exit( $failures ? 1 : 0 );
