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
 *   bws_try_preview_source_part()         (source segments off a slot's chain wire)
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

// sanitize_key: the chain read normalises a `terms` step's slug exactly as the retired
// flat assembler did. Lower-cased alphanumerics/underscore/dash, matching WP.
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
}

// The join preview walks slots through the FOLDED-SLOT seam (FW-56/57) rather than its
// own copy of join's slot walk, so the grammar owner and the canonical order it emits
// through are real dependencies here. Both are pure.
//
// The BASE preview reads its source as a CHAIN (#83) through the same compile seam every
// other consumer takes, so slot-fold-compile.php is a real dependency too: without it the
// builder's function_exists guard degrades to "no source segments at all", which would
// pass a preview with every `from …` clause silently missing.
require __DIR__ . '/../../includes/helpers/serialization-order.php';
require __DIR__ . '/../../includes/helpers/slot-fold.php';
require __DIR__ . '/../../includes/helpers/slot-fold-compile.php';
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
// The door takes DEPTH-0 CHAIN WIRE since #104 — what bws_fold_slot_chain_options() emits.
// Every row below states the wire the seam produces for the flat triple it replaced, and
// the expected TEXT is unchanged, which is the property: the hand-off changed shape and the
// author-facing naming did not.
check( 'ambient unnamed → empty',  bws_try_preview_source_part( '', false ), '' );
check( 'ambient named',            bws_try_preview_source_part( '', true ),  'Current' );
check( 'explicit current named',   bws_try_preview_source_part( 'current', true ),  'Current' );
check( 'ref quoted',               bws_try_preview_source_part( 'refs,rel_post', false ), "Ref 'rel_post'" );
check( 'ref + tax hop',            bws_try_preview_source_part( 'refs,rel_post;terms,event_category', false ), "Ref 'rel_post' → Event Category Term" );
check( 'tax hop, current named',   bws_try_preview_source_part( 'terms,event_category', true ),      'Current → Event Category Term' );
check( 'unknown tax → raw slug',   bws_try_preview_source_part( 'refs,r;terms,__unknown__', false ),           "Ref 'r' → __unknown__ Term" );
// The AMBIENT ROOT is restored only where the flat door named it: NEVER in front of a
// leading relationship step, because the entity a `refs` hop starts from is not the source
// the segment describes. `src:ref` was never `current` in the retired triple either.
check( 'a leading ref hop is NOT anchored to Current', bws_try_preview_source_part( 'refs,rel_post', true ), "Ref 'rel_post'" );
// MULTI-STEP, the capability this door exists to describe: one segment per hop, in wire
// order. Inexpressible in the retired triple, so no row could state it.
check( 'two ref hops name both, in order', bws_try_preview_source_part( 'refs,a;refs,b', false ), "Ref 'a' Ref 'b'" );
check( 'a bound rides the wire and is not named', bws_try_preview_source_part( 'refs,a,limit(2);terms,event_category', false ), "Ref 'a' → Event Category Term" );

// ---------------------------------------------------------------------------
// The SHARED namer all three previews read (#102). The cases above run through it by the
// flat-triple door; these state the PARAMETERS directly, because that is where a container
// that wants different text has to express the difference. A call site that grew its own
// literal instead is exactly what this section is here to make fail.
echo "\nsource_segments — the one namer: per-container switches + missing report\n";
$chain_ref  = bws_fold_chain_from_options( [ 'src' => 'ref', 'ref' => 'rel' ] );
$chain_term = bws_fold_chain_from_options( [ 'srcTermIn' => 'event_category' ] );
$chain_site = bws_fold_chain_from_options( [ 'src' => 'site' ] );

check(
	'segments are ordered, not joined',
	implode( '|', bws_preview_source_segments( $chain_ref ) ),
	"Ref 'rel'"
);
// The arrow means "hopped FROM"; a term step that opens the whole label has nothing to
// point back at, and a caller whose own segment precedes says so with `lead`.
check(
	'term step alone → no arrow',
	implode( ' ', bws_preview_source_segments( $chain_term ) ),
	'Event Category Term'
);
check(
	'term step behind a caller segment → arrow',
	implode( ' ', bws_preview_source_segments( $chain_term, [ 'lead' => true ] ) ),
	'→ Event Category Term'
);
// `site`/`terms` off: a rooting modifier short-circuits a site root to its own warning, and
// a term_* tag builds its taxonomy segment from GB's native `tax` instead.
check( 'site root named',        implode( ' ', bws_preview_source_segments( $chain_site ) ),                    'Site' );
check( 'site off → no segment',  implode( ' ', bws_preview_source_segments( $chain_site, [ 'site' => false ] ) ), '' );
check( 'terms off → no segment', implode( ' ', bws_preview_source_segments( $chain_term, [ 'terms' => false ] ) ), '' );
// (`roots` is exercised against the real registry in the #83 section below, where one is
// bootstrapped.)
// An argless fanning step is REPORTED, never rendered: the engine answers empty for it, so
// the chain short-circuits. The words are the caller's ('No ref key set' / 'B no ref'),
// so the namer reports the SLUG.
$missing = [];
check(
	'argless refs → reported, not rendered',
	implode( ' ', bws_preview_source_segments( bws_fold_chain_from_options( [ 'src' => 'ref' ] ), [], $missing ) ),
	''
);
check( '…and the slug is the report', implode( ',', array_keys( $missing ) ), 'refs' );
$missing = [];
bws_preview_source_segments( bws_fold_parse_chain( 'refs;terms' ), [], $missing );
check( 'both fanning steps report', implode( ',', array_keys( $missing ) ), 'refs,terms' );
// A step the caller silenced reports nothing either — a term_* tag must not warn about a
// taxonomy it was never going to name.
$missing = [];
bws_preview_source_segments( bws_fold_parse_chain( 'refs;terms' ), [ 'terms' => false ], $missing );
check( 'silenced step does not report', implode( ',', array_keys( $missing ) ), 'refs' );
// The ambient entity is named from the TOKEN that spells it, never from a merely absent
// root: a chain LEADING with a step has no root token either, and naming that would anchor
// the hop it makes. This is the one shape the flat door could not produce and chain wire can.
check(
	'a chain leading with a step is not anchored to Current',
	implode( ' ', bws_preview_source_segments( bws_fold_parse_chain( 'refs,rel' ), [ 'named_current' => true ] ) ),
	"Ref 'rel'"
);
// TWO slot shapes read differently for the convergence, in OPPOSITE directions, and both
// are the preview following what renders rather than describing it independently:
//
//   - a FLAT `src:site` + `srcTermIn` previews no source (#102).
//     bws_fold_chain_from_options() refuses to append the legacy term step to a site root,
//     every arm has always let the site read win, and §F9b.5 closed the last container
//     where it did not.
//   - the same pair spelled as CHAIN WIRE (`site;terms,x` — what bws_fold_from_flat() maps
//     that legacy pair to, so it is also what a migrated slot holds) previews the term step
//     (#104), because the chain SAYS the term step and the identically-spelled base tag
//     resolves it too (to empty: a term step needs a post input). Wire means what it says
//     (ADR 0004), and a slot's source is a base tag's source ([I16]).
//
// Hand-edit-only wire either way (`srcTermIn` registers `show_if src: not:site`).
check(
	'slot chain site;terms names the term step, as it is resolved',
	bws_try_preview_source_part( 'site;terms,event_category', true ),
	'Event Category Term'
);

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
// The same tag written as CHAIN WIRE previews identically — the whole reason the preview
// reads its source through the compile seam rather than comparing the raw token (#83).
// A chain's root sits INSIDE the value, so a token compare could not have found it.
check(
	'…and its chain-wire twin previews identically',
	bws_build_preview_label( [ 'src' => 'refs,rel;terms,event_category', 'key' => 'sku' ], 'text' ),
	"['sku' from Ref 'rel' → Event Category Term]"
);
// A chain hopping TWICE names both hops. The flat spelling could not express this, so
// there is no legacy twin to compare against — the point is that the preview describes
// the wire rather than the one step the old shape could hold.
check(
	'text: a two-hop chain names both hops',
	bws_build_preview_label( [ 'src' => 'refs,staff;refs,office', 'key' => 'phone' ], 'text' ),
	"['phone' from Ref 'staff' Ref 'office']"
);
// An ARGLESS step renders nothing at all (the engine short-circuits), so the preview
// reports the missing thing rather than describing a source that cannot resolve.
check(
	'text: an argless chain term step warns, like its flat sibling',
	bws_build_preview_label( [ 'src' => 'terms', 'key' => 'sku' ], 'text' ),
	'[⚠ No taxonomy set]'
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

// ── A tag rooted at a REGISTERED SOURCE (#83) ────────────────────────────────────────
//
// This is the WHOLE editor experience for such a tag, not a nicety: a source that resolves
// from request context cannot resolve in the editor, so the tag previews rather than
// renders every time. The existing prefix-keyed modifier map is keyed on TAG NAME and can
// never fire for a base tag, which is why the label comes off the registry instead.
require_once __DIR__ . '/lib-source-registry.php';
\BWS\DynamicTags\SourceRegistry::register_source( new BWS_Test_Offered_Source() );
\BWS\DynamicTags\SourceRegistry::register_source( new BWS_Test_Unoffered_Source() );

check(
	'text rooted at a registered source → named by its REGISTERED LABEL, not its token',
	bws_build_preview_label( [ 'src' => 'testroot', 'use' => 'key', 'key' => 'sku' ], 'text' ),
	"['sku' from Test Root]"
);
// The `roots` switch on the shared namer (#102): a SLOT turns it off, because a slot's
// source cannot be a registered root yet (FW-71) and naming one would print a segment for
// wire no slot can hold. Asserted here rather than beside the other switches because it is
// the only one that needs a registry.
check(
	'roots on → the registered label is a segment',
	implode( ' ', bws_preview_source_segments( bws_fold_chain_from_options( [ 'src' => 'testroot' ] ) ) ),
	'Test Root'
);
check(
	'roots off → no segment (the slot walks\' setting)',
	implode( ' ', bws_preview_source_segments( bws_fold_chain_from_options( [ 'src' => 'testroot' ] ), [ 'roots' => false ] ) ),
	''
);
// …and the SLOT door the two multislot walks actually call is what sets it, so a slot rooted
// at a registered source stays silent rather than growing a segment its container cannot
// resolve.
check(
	'…and the slot door sets it that way',
	bws_try_preview_source_part( 'testroot', true ),
	''
);
// A rooted CHAIN names the root and then its steps, in wire order.
check(
	'…and a rooted chain names the root, then each step',
	bws_build_preview_label( [ 'src' => 'testroot;refs,office', 'use' => 'key', 'key' => 'phone' ], 'text' ),
	"['phone' from Test Root Ref 'office']"
);
// OFFERED is irrelevant to the preview: the tag is STORED, so the preview describes what
// it says. A source an integrator stopped offering still renders, so it must still read.
check(
	'a source that never opted in still previews by label (offering ≠ describing)',
	bws_build_preview_label( [ 'src' => 'quietsource', 'key' => 'org_email' ], 'email' ),
	"[Email: 'org_email' from Quiet Source]"
);
// An UNREGISTERED token is FLAGGED (#105). It used to add no segment and nothing else, so
// `{{text src:nosuchsource|key:sku}}` previewed exactly like a bare `{{text key:sku}}` and
// rendered empty — the likeliest hand-authored fault, and wholly invisible.
check(
	'an unregistered token is flagged, not silently unnamed',
	bws_build_preview_label( [ 'src' => 'nosuchsource', 'use' => 'key', 'key' => 'sku' ], 'text' ),
	"[⚠ Unknown source 'nosuchsource']"
);
// The INTERNAL spellings of the ambient entity name nothing AND never flag: they resolve,
// and `src:post` would read "from Post", which is what a bare tag already is. Registered
// below so the assertion cannot pass vacuously.
\BWS\DynamicTags\SourceRegistry::init();
foreach ( [ 'post', 'term' ] as $internal_key ) {
	check(
		"an INTERNAL registry key adds no segment and no warning: `{$internal_key}`",
		bws_build_preview_label( [ 'src' => $internal_key, 'use' => 'key', 'key' => 'sku' ], 'text' ),
		"['sku']"
	);
	check(
		"…and it IS registered, so the row above is not vacuous: `{$internal_key}`",
		null !== \BWS\DynamicTags\SourceRegistry::get_source( $internal_key ),
		true
	);
}
// The four RETIRED traversal-substitute tokens get their OWN sentence, not "unknown": they
// ARE registered (the registry keeps its dead by policy), so "unknown" would be false — and
// this is the one warning here with a NAMED REPAIR, since the converter rewrites the token.
foreach ( [ 'related_post', 'second_related_post', 'post_term_related_post', 'term_related_post' ] as $retired_key ) {
	check(
		"a RETIRED registry key names its repair: `{$retired_key}`",
		bws_build_preview_label( [ 'src' => $retired_key, 'use' => 'key', 'key' => 'sku' ], 'text' ),
		"[⚠ Source '{$retired_key}' is no longer supported — run the Tag Converter]"
	);
	check(
		"…and it IS registered, so 'unknown' would have been a false statement: `{$retired_key}`",
		null !== \BWS\DynamicTags\SourceRegistry::get_source( $retired_key ),
		true
	);
}
// A registered but UNOFFERED root still renders, so it must not flag — offering is not
// resolving, and gating this on is_selectable_root() is the named trap. (`quietsource`
// above is that source; this pins the NEGATIVE explicitly, since a passing happy-path row
// would read the same either way.)
check(
	'a registered-but-unoffered root does NOT flag',
	bws_build_preview_label( [ 'src' => 'quietsource', 'use' => 'key', 'key' => 'sku' ], 'text' ),
	"['sku' from Quiet Source]"
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
// Empty options on text: A is always processed (default-filled), so for text
// the "no slots configured" branch is unreachable — slot 1 with no key (and use!=title)
// trips the missing-key warning first. This asserts that actual fired warning.
check(
	'text empty → A no key warn',
	bws_build_try_preview_label( [], 'text' ),
	'[⚠ Try: A no key]'
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
// Carry-forward: B omits key (use=same hides key), inherits slot 1.
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
// Slot ref with no ref key → warning. Since #74 the slot is SKIPPED rather than resolved
// with an empty ref, so the skip warning is the whole message: the per-slot "no key" check
// never runs, and complaining about the key of a slot that will not read anything would
// send the author after the wrong thing.
check(
	'slot ref no ref → warn',
	bws_build_try_preview_label(
		[ 'src' => 'ref' ],
		'text'
	),
	'[⚠ Try: A no ref]'
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
	'[⚠ Try: A no key]'
);
check(
	'try phone empty key → warn',
	bws_build_try_preview_label( [], 'phone' ),
	'[⚠ Try: A no key]'
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
// Custom separator noted (assembly separator = valueSep, renamed from sep in FW-52).
check(
	'sep mode custom valueSep',
	bws_build_join_preview_label( [ 'key' => 'name_first', '2-key' => 'name_last', 'valueSep' => ' ' ] ),
	"[Join 'name_first', 'name_last' (sep: “ ”)]"
);
// Title mode slot needs no key.
check(
	'title slot needs no key',
	bws_build_join_preview_label( [ 'use' => 'title', '2-key' => 'role' ] ),
	"[Join Title, 'role']"
);
// Template mode: format quoted with %N substituted by slot field parts.
check(
	'template mode substitutes tokens',
	bws_build_join_preview_label( [ 'mode' => 'template', 'format' => '%1 (%2)', 'key' => 'name_first', '2-key' => 'name_last' ] ),
	"[Join “'name_first' ('name_last')”]"
);
// Non-current source rides inline on its slot's part.
check(
	'template mode inline ref source',
	bws_build_join_preview_label( [ 'mode' => 'template', 'format' => '%1 / %2', 'src' => 'ref', 'ref' => 'student', 'key' => 'full_name', '2-src' => 'current', '2-key' => 'role' ] ),
	"[Join “'full_name' from Ref 'student' / 'role'”]"
);
// Unbound %N stays literal (visible mistake); %% shown as typed; ~ groups raw.
check(
	'template mode unbound token + escapes literal',
	bws_build_join_preview_label( [ 'mode' => 'template', 'format' => '%1 ~%7 lbs.~ 100%%2', 'key' => 'height', '2-key' => 'pct' ] ),
	"[Join “'height' ~%7 lbs.~ 100%%2”]"
);
// Two-digit token: %10 must not be eaten by %1's substitution.
check(
	'template mode %10 before %1',
	bws_build_join_preview_label( [ 'mode' => 'template', 'format' => '%1 %10', 'key' => 'a', '10-key' => 'j' ] ),
	"[Join “'a' 'j'”]"
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
	'[⚠ Join: B no key]'
);
// src:ref slot with no ref → warning.
check(
	'slot no ref warns',
	bws_build_join_preview_label( [ 'src' => 'ref', 'key' => 'name_first' ] ),
	'[⚠ Join: A no ref]'
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
	bws_build_join_preview_label( [ 'key' => 'name_first', '2-key' => 'name_last', 'fallback' => 'N/A' ] ),
	"[Join 'name_first', 'name_last' (fallback: “N/A”)]"
);
// Nothing configured → no preview.
check(
	'empty → no preview',
	bws_build_join_preview_label( [] ),
	''
);

// --- FOLDED wire (FW-56/57): same previews, one option key per slot -------------
// Each case is the folded twin of a legacy case above, asserted at the SAME expected
// string: the preview reads both eras through the render seam, so an author's label
// must not change when a tag migrates.
check(
	'folded: two slots, separator mode',
	bws_build_join_preview_label( [ 'A' => 'key(name_first)', 'B' => 'src(same);key(name_last)' ] ),
	"[Join 'name_first', 'name_last']"
);
check(
	'folded: analog read + keyed read',
	bws_build_join_preview_label( [ 'A' => 'use(title)', 'B' => 'src(same);key(role)' ] ),
	'[Join Title, ' . "'role']"
);
check(
	'folded: ref source appended',
	bws_build_join_preview_label( [ 'A' => 'key(name_first)', 'B' => 'src(refs,rel_post);key(role)' ] ),
	"[Join 'name_first', 'role' from Ref 'rel_post']"
);
// CANONICAL token alphabet (1.17.0): the token letter IS the slot key, so a folded tag
// reads as one statement. The preview substitutes through the same dual read the
// renderer uses — a preview that knew only one alphabet would show an author their own
// format string with half its tokens unsubstituted.
check(
	'folded: template mode substitutes LETTER tokens',
	bws_build_join_preview_label( [ 'mode' => 'template', 'format' => '%A (%B)', 'A' => 'key(name_first)', 'B' => 'src(same);key(name_last)' ] ),
	"[Join “'name_first' ('name_last')”]"
);
check(
	'folded: the DIGIT token fallback previews identically',
	bws_build_join_preview_label( [ 'mode' => 'template', 'format' => '%1 (%2)', 'A' => 'key(name_first)', 'B' => 'src(same);key(name_last)' ] ),
	"[Join “'name_first' ('name_last')”]"
);
// `%%` shows as typed, and a `%` before a letter past the container maximum is not a token.
check(
	'folded: an escaped % and an out-of-range letter both stay literal',
	bws_build_join_preview_label( [ 'mode' => 'template', 'format' => '%A %%B %K', 'A' => 'key(name_first)' ] ),
	"[Join “'name_first' %%B %K”]"
);
check(
	'folded: an argless ref hop still warns',
	bws_build_join_preview_label( [ 'A' => 'src(refs);key(name_first)' ] ),
	'[⚠ Join: A no ref]'
);
// A read-less combining slot is UNCONFIGURED, so it is skipped rather than warned
// about — the repeater shows it with an empty field select, which says more than a
// warning could. The legacy `2-use:key` twin above warns because that wire states a
// keyed read with no key.
check(
	'folded: read-less slot 2 is skipped, not warned',
	bws_build_join_preview_label( [ 'A' => 'key(name_first)', 'B' => 'src(site)' ] ),
	"[Join 'name_first']"
);
// MIXED era (half-applied migration): folded slot 2 between legacy slots 1 and 3, with
// slot 3 inheriting slot 2's folded source through the shared accumulator.
check(
	'folded: mixed-era wire previews with one carry-forward',
	bws_build_join_preview_label( [ 'key' => 'a', 'B' => 'src(refs,rel_post);key(b)', '3-key' => 'c' ] ),
	"[Join 'a', 'b' from Ref 'rel_post', 'c' from Ref 'rel_post']"
);

// ---------------------------------------------------------------------------
echo "\nbuild_try_preview_label — FOLDED wire (FW-56/57)\n";
// The selecting twin of the block above. Same expected strings as the legacy cases
// earlier in this file: the try_ preview now walks the SAME seam the callback resolves
// with, so a label must not change when a tag migrates. It also inherits the seam's
// container rules, which is what the last three cases pin.
check(
	'folded: single text slot',
	bws_build_try_preview_label( [ 'A' => 'key(sku)' ], 'text' ),
	"[Try 'sku']"
);
check(
	'folded: two slots vary source',
	bws_build_try_preview_label( [ 'A' => 'key(sku)', 'B' => 'src(refs,rel)' ], 'text' ),
	"[Try 'sku' from Current, Ref 'rel']"
);
check(
	'folded: two slots vary field',
	bws_build_try_preview_label( [ 'A' => 'key(sku)', 'B' => 'src(same);key(alt_sku)' ], 'text' ),
	"[Try 'sku', 'alt_sku']"
);
check(
	'folded: bare tag still warns on the missing slot-1 key',
	bws_build_try_preview_label( [], 'text' ),
	'[⚠ Try: A no key]'
);
// A read-less slot INHERITS in a selecting container (the mirror of join's skip), so
// slot 2 previews with slot 1's field rather than vanishing — here with its own term
// hop, which does NOT carry forward. Asserted at the legacy twin's exact string
// (`2-srcTermIn:category` with no read of its own).
check(
	'folded: read-less slot 2 inherits the field, keeps its own term hop',
	bws_build_try_preview_label( [ 'A' => 'key(sku)', 'B' => 'src(terms,category)' ], 'text' ),
	"[Try 'sku' from Current, Current → Category Term]"
);
check(
	'…and its legacy twin previews identically',
	bws_build_try_preview_label( [ 'key' => 'sku', '2-srcTermIn' => 'category' ], 'text' ),
	"[Try 'sku' from Current, Current → Category Term]"
);
// A container with no per-slot read axis: slots are source chains, and the label is
// the template's own.
check(
	'folded: chain-only container (title) previews its sources',
	bws_build_try_preview_label( [ 'A' => '', 'B' => 'src(refs,rel)' ], 'title' ),
	"[Try Title from Current, Ref 'rel']"
);
// MIXED era: folded slot 2 between legacy slots 1 and 3, one accumulator.
check(
	'folded: mixed-era try_ wire previews with one carry-forward',
	bws_build_try_preview_label( [ 'key' => 'a', 'B' => 'src(refs,rel);key(b)', '3-use' => 'key', '3-key' => 'c' ], 'text' ),
	"[Try 'a' from Current, 'b' from Ref 'rel', 'c' from Ref 'rel']"
);

// ── The inexpressible-chain flag, INVERTED (#104) ────────────────────────────
// These four rows asserted a WARNING through 1.16.x: a slot whose chain had no flat
// spelling was skipped by the seam, and the preview had to say so or it read as "this tag
// has one slot fewer than the author configured". The flatten is gone, so there is no flat
// spelling left to fail at and the refusal dissolved with it — these are now the acceptance
// signal for FW-71, and they assert the slots RESOLVE and are NAMED.
//
// FIVE reasons remain (the four that survived, plus `step:rows` which arrived with the emit
// change), and FOUR of them fire with their own wording immediately below — `read`, an
// unconfigured combining slot, is silent by design because it is a resting state. Deleting the
// refusal wholesale would have removed correct refusals along with the dissolved one.
check(
	'join: ref+term chain previews normally (the row that always resolved)',
	bws_build_join_preview_label( [ 'A' => 'src(refs,rel;terms,dept);use(title)', 'B' => 'key(role)' ] ),
	"[Join Title from Ref 'rel' → Dept Term, 'role']"
);
check(
	'join: a SECOND ref hop RESOLVES and names both steps',
	bws_build_join_preview_label( [ 'A' => 'src(refs,a;refs,b);use(title)', 'B' => 'key(role)' ] ),
	"[Join Title from Ref 'a' Ref 'b', 'role']"
);
check(
	'join: a `rows` step resolves at the seam (the container refuses the kind, not the wire)',
	bws_build_join_preview_label( [ 'A' => 'src(rows,rows);key(name)' ] ),
	"[Join 'name']"
);
check(
	'try_: a second ref hop resolves on a selecting container too',
	bws_build_try_preview_label( [ 'A' => 'key(sku)', 'B' => 'src(refs,a;refs,b);key(x)' ], 'text' ),
	"[Try 'sku' from Current, 'x' from Ref 'a' Ref 'b']"
);
check(
	'try_: a lone `rows` slot is a slot, not "no slots configured"',
	bws_build_try_preview_label( [ 'A' => 'src(rows,rows);key(name)' ], 'text' ),
	"[Try 'name']"
);
// A `same` root with nothing to be the same AS gets its OWN wording (#74). Reusing
// "source not supported" would send the author after the wrong thing: the chain IS
// expressible, and what is missing is an earlier slot that resolves. Reachable in a
// COMBINING container, where an unconfigured read skips slot A without feeding the carry.
check(
	'join: `same` with nothing carried says what to fix, not "unsupported"',
	bws_build_join_preview_label( [ 'A' => 'src(site)', 'B' => 'src(same);key(x)' ] ),
	'[⚠ Join: B no previous source]'
);
check(
	'…and an argless `refs` names the RIGHT missing thing, not the term hop\'s noun',
	bws_build_join_preview_label( [ 'A' => 'src(refs);key(x)' ] ),
	'[⚠ Join: A no ref]'
);
// An INCOMPLETE step is flagged too, but NAMED for what is missing rather than as an
// unsupported source: the seam can express a term hop, this one just has no taxonomy
// yet, and it skips rather than reading the un-hopped entity. Silence here would leave
// the author hunting for why a fully-sourced slot vanished.
check(
	'join: a `terms` hop with no taxonomy is flagged as MISSING, not unsupported',
	bws_build_join_preview_label( [ 'A' => 'src(terms);key(role)', 'B' => 'key(name)' ] ),
	"[⚠ Join: A no taxonomy]"
);
check(
	'try_: same, on a selecting container',
	bws_build_try_preview_label( [ 'A' => 'key(sku)', 'B' => 'src(terms);key(x)' ], 'text' ),
	'[⚠ Try: B no taxonomy]'
);
// An UNCONFIGURED combining slot stays SILENT — it is a normal in-progress state,
// and flagging it would fire on every half-built join.
check(
	'join: an unconfigured slot is silent (no read = in progress, not broken)',
	bws_build_join_preview_label( [ 'A' => 'key(a)', 'B' => 'src(site)' ] ),
	"[Join 'a']"
);

// ── Slot warning GRAMMAR: letters, and the collapse rule (#105) ──────────────────────
//
// Slots are named by LETTER on both containers — the wire key the author reads in
// `A:src(…)` and the header the control configured it under. `slot N` was a third
// spelling of one thing and retires here; every warning expectation above is its pin.
//
// The COLLAPSE rule: list every slot with a problem, keep the detail only while there is
// ONE problem to describe. A bracket that spelled out disagreeing details reads as a wall
// on a five-slot tag, and the author opens the slots either way.
// MUTATIONS pinned here, each confirmed failing when this landed — a green count over
// a grammar this small proves very little on its own:
//   - print `$n` instead of bws_slot_ordinal( $n )     → 16 fail (numbers return)
//   - drop the distinct-detail branch (always print it) → 2 fail (detail on disagreeing slots)
echo "\nslot warning grammar — letters + collapse (#105)\n";

check(
	'join: two slots, one distinct issue → letters listed, detail kept',
	bws_build_join_preview_label( [ 'A' => 'use(key)', 'B' => 'key(ok)', 'C' => 'use(key)' ] ),
	'[⚠ Join: A, C no key]'
);
// TWO distinct issues collapse to the letters alone. Details survive only while there is
// one act to name; dropping this branch is a pinned mutation.
check(
	'try_: two slots, distinct issues → letters and no detail',
	bws_build_try_preview_label( [ 'A' => 'use(key)', 'B' => 'src(terms);key(x)' ], 'text' ),
	'[⚠ Try: A, B misconfigured]'
);
// …and that same case pins the SORT. The two walks raise skips (walk pass) and per-slot
// gaps (second pass) into one list, so B's warning is raised BEFORE A's — a letter list in
// raise order would read as a different tag.
check(
	'join: skips and per-slot gaps sort into wire order, not raise order',
	bws_build_join_preview_label( [ 'A' => 'use(key)', 'B' => 'src(terms);key(x)' ] ),
	'[⚠ Join: A, B misconfigured]'
);
// TAG-LEVEL warnings append unchanged and never count toward the distinct test: the rule
// is about slots disagreeing with each other, and a one-press fix should not cost the
// author the other diagnosis.
check(
	'join: a tag-level warning appends and does not trigger the collapse',
	bws_build_join_preview_label(
		[ 'mode' => 'template', 'A' => 'use(key)', 'B' => 'key(ok)', 'C' => 'use(key)' ]
	),
	'[⚠ Join: A, C no key, no format set]'
);
check(
	'join: a tag-level warning alone keeps its own words',
	bws_build_join_preview_label( [ 'mode' => 'template', 'A' => 'key(a)' ] ),
	'[⚠ Join: no format set]'
);


// ── INERT CHAINS are flagged, on base tags as well as slots (#105) ───────────────────
//
// Deleting the flatten deleted the only author-facing signal that a chain resolves to
// nothing, and base tags never had one at all. Four conditions, all decidable from the
// WIRE with no per-template knowledge — an unknown step slug, an unregistered root, a
// retired token, and an argless `rows` step. What is NOT here is as load-bearing: an
// ambient root (not knowable at parse time), an unoffered root (offering ≠ resolving), and a
// well-formed source no arm consumes YET (unimplemented ≠ inert — that is FW-74).
//
// MUTATIONS pinned here, each confirmed failing when this landed:
//   - blind the step walk, i.e. flag on bws_fold_chain_resolution()'s `kind` alone → 3 fail;
//     `kind` reports the chain's TAIL, so a short-circuit upstream still reads 'post'
//   - move the root check inside the `roots` display switch → 4 fail (every SLOT row)
//   - gate the root check on is_selectable_root()          → 2 fail (the unoffered root)
echo "\ninert chains — base and slot (#105)\n";

// UNKNOWN STEP. The chain short-circuits at the engine ([I14]).
check(
	'base: an unknown step slug is flagged',
	bws_build_preview_label( [ 'src' => 'refs,rel;bogus,x', 'use' => 'key', 'key' => 'name' ], 'text' ),
	"[⚠ Unknown source step 'bogus']"
);
// The MIDDLE-step row is the one the `kind` query cannot answer: the chain's TAIL is a
// `refs` step, so bws_fold_chain_resolution() reports kind `post` while nothing resolves.
check(
	'base: an unknown step MID-chain is flagged (the tail still reports a kind)',
	bws_build_preview_label( [ 'src' => 'testroot;bogus,x;refs,y', 'use' => 'key', 'key' => 'name' ], 'text' ),
	"[⚠ Unknown source step 'bogus']"
);
check(
	'…and the tail DOES still report a kind, so the row above is not vacuous',
	bws_fold_chain_resolution( bws_fold_parse_chain( 'testroot;bogus,x;refs,y' ) )['kind'],
	'post'
);
// ROOT before STEP when a chain has both: the factory consumes the root first, so naming
// the step would send the author past the fault.
check(
	'base: an unknown ROOT outranks an unknown step behind it',
	bws_build_preview_label( [ 'src' => 'currnet;bogus,x', 'use' => 'key', 'key' => 'name' ], 'text' ),
	"[⚠ Unknown source 'currnet']"
);
// ARGLESS `rows` joins the MISSING list rather than the inert one — an unfinished step
// is unfinished under any future arm, so it speaks now even though the arm is FW-74.
check(
	'base: an argless `rows` step reports a missing repeater field',
	bws_build_preview_label( [ 'src' => 'rows', 'use' => 'key', 'key' => 'name' ], 'text' ),
	'[⚠ No repeater field set]'
);
check(
	'…and it joins the other missing items in one sentence',
	bws_build_preview_label( [ 'src' => 'rows', 'use' => 'key' ], 'text' ),
	'[⚠ No repeater field or meta key set]'
);
// A COMPLETE `rows` step is NOT flagged. It is well-formed wire the base arms do not
// consume yet (FW-74) — flagging it would encode a per-template fact with a shelf life.
check(
	'base: a COMPLETE `rows` step is silent (unimplemented ≠ inert)',
	bws_build_preview_label( [ 'src' => 'rows,team_members', 'use' => 'key', 'key' => 'name' ], 'text' ),
	"['name']"
);
// NEGATIVES — the internal spellings of the ambient entity and of a relationship hop all
// resolve, so none of them flags.
check( 'negative: bare tag',   bws_build_preview_label( [ 'use' => 'key', 'key' => 'sku' ], 'text' ), "['sku']" );
check( 'negative: src:current', bws_build_preview_label( [ 'src' => 'current', 'use' => 'key', 'key' => 'sku' ], 'text' ), "['sku']" );
check( 'negative: src:site',    bws_build_preview_label( [ 'src' => 'site', 'use' => 'key', 'key' => 'sku' ], 'text' ), "['sku' from Site]" );
check( 'negative: src:ref',     bws_build_preview_label( [ 'src' => 'ref', 'ref' => 'rel', 'use' => 'key', 'key' => 'sku' ], 'text' ), "['sku' from Ref 'rel']" );

// SLOTS take the same four conditions, in the slot phrasing — the letter and the bracket
// prefix already supply the capital and the noun.
check(
	'join: an unknown root on a slot',
	bws_build_join_preview_label( [ 'A' => 'src(bogus,x);key(name)', 'B' => 'key(role)' ] ),
	"[⚠ Join: A unknown source 'bogus']"
);
check(
	'join: an unknown step on a slot',
	bws_build_join_preview_label( [ 'A' => 'src(refs,rel;bogus,x);key(name)' ] ),
	"[⚠ Join: A unknown source step 'bogus']"
);
check(
	'try_: a retired token on a slot names its repair',
	bws_build_try_preview_label( [ 'A' => 'src(related_post);use(key);key(name)' ], 'text' ),
	'[⚠ Try: A source no longer supported — run the Tag Converter]'
);
// An inert source reports ALONE — the slot reads nothing whatever its key says, so `no
// key` beside it would send the author to the wrong field. (Without this the two details
// would disagree and the whole thing would collapse to `A misconfigured`.)
// (Shown on a SELECTING container: a combining slot with a source and no read is
// UNCONFIGURED and stays silent by decision, so join cannot reach the shape at all.)
check(
	'try_: an inert slot reports its source, not its missing key',
	bws_build_try_preview_label( [ 'A' => 'src(bogus,x);use(key)' ], 'text' ),
	"[⚠ Try: A unknown source 'bogus']"
);
// Two slots, two DIFFERENT unknown tokens → distinct details → the collapse rule fires.
check(
	'try_: two inert slots with different tokens collapse',
	bws_build_try_preview_label(
		[ 'A' => 'src(bogus,x);use(key);key(name)', 'B' => 'src(currnet);use(key);key(role)' ],
		'text'
	),
	'[⚠ Try: A, B misconfigured]'
);

echo "\n" . ( $failures ? "FAILED {$failures}/{$count}\n" : "PASSED {$count}/{$count}\n" );
exit( $failures ? 1 : 0 );
