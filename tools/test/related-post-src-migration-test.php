<?php
/**
 * Standalone unit harness for the `related_post` SOURCE-TOKEN migration (#56).
 *
 * Loads the REAL files (no transcribed copies): includes/tags/deprecated-tags.php for
 * bws_migrate_related_post_src(), includes/classes/class-migration-registry.php for the
 * match rule and the cascade, plus the serialization-order pair so emitted key order is
 * the canonical one production writes.
 *
 * What this covers that no sibling harness does:
 *
 *   R1 REWRITE   — the per-slot transform: `src:related_post` → `src:ref`, with the
 *                  relationship field taken from `rel` (MOVED) or from `key` (COPIED).
 *                  The move/copy asymmetry is the correctness argument, not a detail:
 *                  the retired RelatedPost source consumed `key` as the relationship
 *                  field AND the downstream field read consumed the same `key` as the
 *                  field key, off one unmutated options array. Moving it would resolve
 *                  the hop and then read nothing.
 *   R2 GATE      — MigrationRegistry::entry_matches() on match_option_values: this entry
 *                  matches a legacy VALUE in a live KEY, so a key gate would fire on
 *                  every tag that names a source at all. The negative cases are the
 *                  point — `src:ref` and `src:current` must NOT match.
 *   R3 AGREEMENT — what the converter REPORTS (entry_matches) and what it RUNS
 *                  (apply_option_migration) are the same predicate. They were two
 *                  hand-kept copies of the rule before 1.17.0.
 *   R7 EVIDENCE  — the key-clobber (#73): the rel → ref repair DEFERS a related_post
 *                  slot whole rather than settle-then-delete, so the value-gated entry
 *                  sees `rel` intact and ranks it above `key`. Cascade-level rows,
 *                  because the defect only exists between two entries.
 *
 * NOT covered here, deliberately: whether migrated wire RESOLVES like the legacy wire it
 * replaced. That needs the source factory and a real field read — testbed territory
 * (docs/testbed.md), and the four source classes are inert as of 1.17.0 so the legacy half
 * of such a comparison no longer exists to run.
 *
 * Run:  php tools/test/related-post-src-migration-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

// The WP surface the loaded files touch when CALLED (nothing runs at load time).
if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $s ) ); }
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) { return $value; }
}

require __DIR__ . '/../../includes/helpers/slot-fold.php';
require __DIR__ . '/../../includes/helpers/serialization-order.php';
require __DIR__ . '/../../includes/helpers/slot-fold-migrate.php';
require __DIR__ . '/../../includes/classes/class-migration-registry.php';
require __DIR__ . '/../../includes/classes/class-deprecated-tag-registry.php';
require __DIR__ . '/../../includes/classes/class-tag-template-registry.php';
require __DIR__ . '/../../includes/tags/deprecated-tags.php';

use BWS\DynamicTags\MigrationRegistry;
use BWS\DynamicTags\TagTemplateRegistry;

$failures = 0;
$count    = 0;

function assert_eq( string $label, $expected, $actual ): void {
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

// ===========================================================================
echo "R1 — bws_migrate_related_post_src (rewrite)\n";

assert_eq( 'R1.1 rel is MOVED to ref; the orphan key goes',
	'{{text src:ref|ref:vendor}}',
	bws_migrate_related_post_src( '{{text src:related_post|rel:vendor}}' ) );

assert_eq( 'R1.2 key is COPIED to ref and KEPT (both readers consumed it)',
	'{{text src:ref|ref:vendor|key:vendor}}',
	bws_migrate_related_post_src( '{{text src:related_post|key:vendor}}' ) );

assert_eq( 'R1.3 rel wins over key, and key survives as the field key',
	'{{text src:ref|ref:vendor|key:title}}',
	bws_migrate_related_post_src( '{{text src:related_post|rel:vendor|key:title}}' ) );

// Under THIS src token the class read `rel` and never `ref`, so `rel` overwrites the inert
// `ref`. See §R5 for the criterion this is one face of.
assert_eq( 'R1.4 rel overwrites an inert ref (this src never read ref)',
	'{{text src:ref|ref:rel-wins|key:title}}',
	bws_migrate_related_post_src( '{{text src:related_post|ref:inert|rel:rel-wins|key:title}}' ) );

// The one named departure from strict preservation: with no `rel`/`key` the old tag
// resolved false and read the ambient entity, so preserving output would mean DELETING
// `ref`. The transform does not — its mandate is moving a key out of a dead vocabulary,
// not deleting one written in the live one.
assert_eq( 'R1.4b no rel and no key → an existing ref is left alone',
	'{{text src:ref|ref:live}}',
	bws_migrate_related_post_src( '{{text src:related_post|ref:live}}' ) );

assert_eq( 'R1.5 no relationship field anywhere → src:ref alone (no fabricated hop)',
	'{{text src:ref}}',
	bws_migrate_related_post_src( '{{text src:related_post}}' ) );

// Unrelated options survive, and the rewritten tag comes out in CANONICAL key order
// (src → ref → limit → key → fallback), not the order the legacy string happened to use:
// `limit` sorts ahead of the field keys because list length is a source property (FW-52).
assert_eq( 'R1.6 unrelated options survive, canonical order applied',
	'{{text src:ref|ref:vendor|limit:3|key:title|fallback:none}}',
	bws_migrate_related_post_src( '{{text src:related_post|rel:vendor|key:title|limit:3|fallback:none}}' ) );

// A legacy flat slot carries the same axes under an `N-` prefix. Each slot resolves
// independently — slot 2's `rel` must not leak into slot 3's `ref`.
assert_eq( 'R1.7 legacy flat slot: N-src rewrites with its OWN N-rel',
	'{{try_text src:ref|ref:a|2-src:ref|2-ref:b}}',
	bws_migrate_related_post_src( '{{try_text src:related_post|rel:a|2-src:related_post|2-rel:b}}' ) );

assert_eq( 'R1.8 one slot legacy, another already live → only the legacy one changes',
	'{{try_text src:ref|ref:live|2-src:ref|2-ref:b|2-key:b}}',
	bws_migrate_related_post_src( '{{try_text src:ref|ref:live|2-src:related_post|2-key:b}}' ) );

assert_eq( 'R1.9 no legacy token → byte-identical passthrough (a no-op must not reformat)',
	'{{text src:ref|ref:vendor|key:title}}',
	bws_migrate_related_post_src( '{{text src:ref|ref:vendor|key:title}}' ) );

assert_eq( 'R1.10 `related` is NOT migrated — it never resolved, so a hop would be new behaviour',
	'{{text src:related|key:title}}',
	bws_migrate_related_post_src( '{{text src:related|key:title}}' ) );

// Folded slot keys are CAPITALS by construction (bws_slot_ordinal). The `\d+-` pattern
// cannot reach one, so a tag the fold already migrated is inert here rather than
// half-rewritten. Belt and braces: the entry is also registered BEFORE the fold.
assert_eq( 'R1.11 a folded slot value is not a flat N-src and is left alone',
	'{{join A:src(related_post) key(x)|B:key(y)}}',
	bws_migrate_related_post_src( '{{join A:src(related_post) key(x)|B:key(y)}}' ) );

// ===========================================================================
echo "\nR2 — entry_matches: the value gate\n";

$entry = array(
	'type'                => 'option',
	'match_tag'           => 'text',
	'match_option_values' => array(
		'src'   => array( 'related_post' ),
		'2-src' => array( 'related_post' ),
	),
	'transform_callback'  => 'bws_migrate_related_post_src',
);

$matches = static function ( string $tag_string ) use ( $entry ): bool {
	[ , $options ] = MigrationRegistry::parse_tag_string( $tag_string );
	return MigrationRegistry::entry_matches( $entry, array_keys( $options ), $options );
};

assert_eq( 'R2.1 tag-level legacy value matches', true, $matches( '{{text src:related_post|rel:x}}' ) );
assert_eq( 'R2.2 slot-level legacy value matches', true, $matches( '{{text 2-src:related_post}}' ) );
assert_eq( 'R2.3 src:ref does NOT match (key present, value wrong)', false, $matches( '{{text src:ref|ref:x}}' ) );
assert_eq( 'R2.4 src:current does NOT match', false, $matches( '{{text src:current|key:x}}' ) );
assert_eq( 'R2.5 no src at all does NOT match', false, $matches( '{{text key:x}}' ) );

// The value is read from the option it is gated on, not from anywhere in the string.
// `related_post` is a plausible FIELD name, and a field key holding it must not trigger.
assert_eq( 'R2.6 the same value in a different key does NOT match',
	false, $matches( '{{text src:ref|ref:related_post}}' ) );

// Callers that only have the key list must not match a value-gated entry: reporting
// nothing is the safe direction, reporting everything is the failure being avoided.
assert_eq( 'R2.7 without values, a value-gated entry cannot match',
	false, MigrationRegistry::entry_matches( $entry, array( 'src', 'rel' ) ) );

// An entry declaring no gate at all matches nothing (unchanged pre-existing rule).
assert_eq( 'R2.8 an ungated entry never matches',
	false, MigrationRegistry::entry_matches( array( 'type' => 'option', 'match_tag' => 'text' ), array( 'src' ), array( 'src' => 'related_post' ) ) );

// ===========================================================================
echo "\nR3 — registered entry: report and run agree\n";

MigrationRegistry::register( $entry + array( 'new_tag' => 'text', 'label' => 'r3' ) );

$agrees = static function ( string $tag_string ): array {
	[ $tag_name, $options ] = MigrationRegistry::parse_tag_string( $tag_string );
	$found    = MigrationRegistry::find_option_migrations( $tag_name, array_keys( $options ), $options );
	$reported = ! empty( $found );
	$ran      = MigrationRegistry::apply_option_migration( $tag_name, $tag_string ) !== $tag_string;
	return array( $reported, $ran );
};

assert_eq( 'R3.1 legacy wire is both reported and changed', array( true, true ), $agrees( '{{text src:related_post|rel:x}}' ) );
assert_eq( 'R3.2 live wire is neither reported nor changed', array( false, false ), $agrees( '{{text src:ref|ref:x}}' ) );

// The cascade must terminate: the transform rewrites `src` to a value that no longer
// matches its own gate, so the second iteration finds nothing to do.
assert_eq( 'R3.3 one pass reaches a fixed point',
	'{{text src:ref|ref:x}}',
	MigrationRegistry::apply_option_migration( 'text', '{{text src:related_post|rel:x}}' ) );

// ===========================================================================
echo "\nR4 — rel → ref on the modifier and try_ families (the \$rel_fix extension)\n";

// The entries are DERIVED from the registered templates, so the harness registers the
// templates first. Registering after the R1-R3 sections keeps their assertions clear of
// the ~60 entries this pulls in.
TagTemplateRegistry::register_modifier_template( array(
	'key'              => 'text',
	'supports_try'     => true,
	'try_per_slot_use' => true,
	'try_per_slot_key' => true,
) );
TagTemplateRegistry::register_modifier_template( array( 'key' => 'permalink', 'supports_try' => true ) );

bws_register_option_migrations();
// The 25 N×M families are TAG-type entries, registered by the v1 wrapper pass. The
// harness ran only the option pass until 1.17.0, because those entries had no target
// and therefore nothing to assert.
bws_register_v1_deprecated_tag_wrappers();

$migrate = static function ( string $tag_string ): string {
	[ $tag_name ] = MigrationRegistry::parse_tag_string( $tag_string );
	return MigrationRegistry::apply_option_migration( $tag_name, $tag_string );
};

// The 25 N×M families are TAG-type entries, not option-type, so they run through the
// other half of the registry. Same converter, different door: a deprecated TAG NAME is
// rewritten whole, where an option entry rewrites keys in place.
$migrate_tag = static function ( string $tag_string ): string {
	[ $tag_name ] = MigrationRegistry::parse_tag_string( $tag_string );
	return MigrationRegistry::transform_tag( $tag_name, $tag_string );
};

// The base-tag half has shipped since 1.6.0. Emitted key order became CANONICAL when the
// declarative $rel_fix pair was replaced by the shared transform_callback (#57) — the
// deliberate canonicalization the previous pin here existed to force a decision on.
assert_eq( 'R4.1 base tag (pre-existing): rel → ref, src:ref injected',
	'{{text src:ref|ref:vendor|key:title}}',
	$migrate( '{{text rel:vendor|key:title}}' ) );

assert_eq( 'R4.2 term_ modifier: same repair, derived from the template list',
	'{{term_text src:ref|ref:vendor|key:title}}',
	$migrate( '{{term_text rel:vendor|key:title}}' ) );

assert_eq( 'R4.3 term_ modifier with no per-slot read still repairs',
	'{{term_permalink src:ref|ref:vendor}}',
	$migrate( '{{term_permalink rel:vendor}}' ) );

// An explicit src is never overwritten, so a tag that already states its source keeps it
// and only gains the renamed key. Faithful: `rel` was never read under src:current either.
assert_eq( 'R4.4 an explicit src is not overwritten by the inject',
	'{{term_text src:current|ref:vendor}}',
	$migrate( '{{term_text src:current|rel:vendor}}' ) );

// try_ — END TO END, i.e. through the fold entry that runs in the SAME cascade. These
// assert the folded result on purpose: a first draft renamed the keys and injected no
// `src`, reasoning that a bare `N-ref` is inert rather than wrong. It is not inert. The
// fold maps a legacy `ref` with no `src:ref` beside it to NO STEP, folds the slot without
// it and strips the legacy keys — erasing the relationship in the pass meant to rescue
// it. Every case below must show the field surviving into `src(refs,<field>)`.
assert_eq( 'R4.5 try_ slot 1: bare rel survives the fold as a refs step',
	'{{try_text A:src(refs,vendor,limit[1]);key(title)}}',
	$migrate( '{{try_text rel:vendor|key:title}}' ) );

assert_eq( 'R4.6 try_ slot 3: 3-rel lands on slot C, not on slot 1',
	'{{try_text A:key(a)|C:src(refs,vendor,limit[1]);use(same)}}',
	$migrate( '{{try_text key:a|3-src:ref|3-rel:vendor}}' ) );

assert_eq( 'R4.7 several slots migrate in one pass, each keeping its OWN field',
	'{{try_text A:src(refs,a,limit[1])|B:src(refs,b,limit[1]);use(same)|E:src(refs,c,limit[1]);use(same)}}',
	$migrate( '{{try_text rel:a|2-rel:b|5-rel:c}}' ) );

// `rel` is a registered option on NO current tag, so there is nothing live to collide
// with — but the transform must still be a strict no-op when none is present (the
// contract apply_option_migration leans on). Asserted against the transform directly,
// since end-to-end the fold entry legitimately rewrites the tag.
assert_eq( 'R4.8 no rel present → the transform returns the input verbatim',
	'{{try_text src:ref|ref:vendor|key:title}}',
	bws_migrate_rel_to_ref( '{{try_text src:ref|ref:vendor|key:title}}' ) );

assert_eq( 'R4.9 an explicit slot src is not overwritten',
	'{{try_text 2-src:current|2-ref:vendor}}',
	bws_migrate_rel_to_ref( '{{try_text 2-src:current|2-rel:vendor}}' ) );

assert_eq( 'R4.10 under 2-src:ref the ref is the live one and wins',
	'{{try_text 2-src:ref|2-ref:live}}',
	bws_migrate_rel_to_ref( '{{try_text 2-src:ref|2-ref:live|2-rel:inert}}' ) );

// ===========================================================================
echo "\nR5 — WHICH SPELLING WINS IS DECIDED BY src, NOT BY A RANKING\n";

// THE property, stated once. `ref` and `rel` are not competing candidates with a fixed
// precedence: each is live under exactly one source token and inert under the other.
//
//   src:ref           the chain compiler reads `ref`     → ref wins
//   src:related_post  the retired class read `rel`       → rel wins
//   src absent        NEITHER was read — no hop happened → repair, not preservation
//
// Getting this backwards does not error. It migrates a tag to hop somewhere the old tag
// never hopped, permanently, which is the defect #56 exists to stop — so the same
// ref+rel pair is run past every token here and each must pick a different winner.
$pair = static function ( string $src ) {
	$src_opt = '' === $src ? '' : 'src:' . $src . '|';
	return '{{try_text ' . $src_opt . 'ref:from-ref|rel:from-rel}}';
};

assert_eq( 'R5.1 src:ref → the ref is live, it wins',
	'{{try_text src:ref|ref:from-ref}}',
	bws_migrate_rel_to_ref( $pair( 'ref' ) ) );

// DEFER-WHOLE (#73): the rel → ref transform does not settle this token itself — settling
// meant writing `ref = rel` and deleting `rel`, which destroyed the evidence the later
// value-gated entry needs to rank `rel` above `key`. So the slot is SKIPPED, byte-identical,
// and the related_post entry (sole owner of the token) applies the whole rule. Mirrors the
// mount path's decline (BWS_FOLD_RETIRED_SRC_TOKENS).
assert_eq( 'R5.2 src:related_post → deferred whole: the transform leaves the slot verbatim',
	$pair( 'related_post' ),
	bws_migrate_rel_to_ref( $pair( 'related_post' ) ) );

// …and the cascade still lands the rel-derived value, end to end through the fold.
assert_eq( 'R5.2b src:related_post → cascade: rel wins via the related_post entry',
	'{{try_text A:src(refs,from-rel,limit[1])}}',
	$migrate( $pair( 'related_post' ) ) );

assert_eq( 'R5.3 src absent → neither was read; repair keeps ref and injects src:ref',
	'{{try_text src:ref|ref:from-ref}}',
	bws_migrate_rel_to_ref( $pair( '' ) ) );

// The two transforms must agree, because they run in one cascade over one tag: the rel →
// ref transform fires first and DEFERS the related_post slot whole, leaving the `rel` the
// second one needs. Slot 2 carries the same pair end to end, through the fold.
assert_eq( 'R5.4 cascade: a related_post slot keeps the rel-named field through the fold',
	'{{try_text A:key(x)|B:src(refs,from-rel,limit[1]);use(same)}}',
	$migrate( '{{try_text key:x|2-src:related_post|2-ref:inert|2-rel:from-rel}}' ) );

assert_eq( 'R5.5 cascade: a src:ref slot keeps the ref-named field through the fold',
	'{{try_text A:key(x)|B:src(refs,from-ref,limit[1]);use(same)}}',
	$migrate( '{{try_text key:x|2-src:ref|2-ref:from-ref|2-rel:inert}}' ) );

// {{join}} postdates the rel → ref rename by nine releases; no entry is registered for
// it, so a hand-typed `rel` there is left alone rather than given a meaning it never had.
assert_eq( 'R4.11 {{join}} has no rel entry — left alone',
	'{{join rel:vendor}}',
	$migrate( '{{join rel:vendor}}' ) );

// Nor a related_post entry, on the same argument. And the fold declines a retired token
// rather than folding it, so the tag comes through whole rather than half-rewritten.
assert_eq( 'R4.12 {{join}} has no related_post entry either, and the fold declines the token',
	'{{join src:related_post|key:x}}',
	$migrate( '{{join src:related_post|key:x}}' ) );

echo "\n";
// ── R6 — the 25 N×M entries that finally have targets (1.17.0) ─────────────
//
// They carried `old_tag` + `since` and nothing else for four releases: no current tag
// reached a second-hop relationship or a term-then-relationship chain. Source CHAINS
// state both shapes. RISK IS ONE-DIRECTIONAL — the renderers were stripped in 1.14.0,
// so these tags produce nothing today and migration moves them from broken to correct.
echo "
R6 — second_related_post_* / post_term_related_post_* get chain targets
";

// THE LIMIT IS PER STEP, AND BOTH STEPS CARRY ONE. These families are two fanning steps
// by construction, and per-step limits are per-input and MULTIPLY — bounding only the last
// would read one target per referenced post, where the old tag collapsed to one at every
// hop. The mapping is bws_fold_chain_apply_legacy_limit(), shared with the base source
// migration so a converted tag and a scanned one cannot disagree.
assert_eq( 'R6.1 second_related_post: two refs steps, BOTH limited to preserve the old single value',
	'{{text src:refs,office,limit(1);refs,manager,limit(1)|key:name}}',
	$migrate_tag( '{{second_related_post_custom_text rel:office|rel_2:manager|key:name}}' ) );

assert_eq( 'R6.2 post_term_related_post: a terms step, then a refs step',
	'{{title src:terms,department,limit(1);refs,lead,limit(1)}}',
	$migrate_tag( '{{post_term_related_post_title tax:department|rel:lead}}' ) );

// The option was renamed in 1.4.x and the retired class accepted both spellings, so
// stored wire can hold either.
assert_eq( 'R6.3 the pre-rename `taxonomy` spelling is read too',
	'{{title src:terms,department,limit(1);refs,lead,limit(1)}}',
	$migrate_tag( '{{post_term_related_post_title taxonomy:department|rel:lead}}' ) );

// The suffix decides the TAG; the family decides the CHAIN. A `*_term_*` suffix lands
// on the term_ modifier, because its last hop was post->term.
assert_eq( 'R6.4 a *_term_* suffix targets the term_ modifier',
	'{{term_text src:refs,office,limit(1);refs,manager,limit(1)}}',
	$migrate_tag( '{{second_related_post_term_custom_text rel:office|rel_2:manager}}' ) );

// A CHAIN WITH A HOLE IS NEVER WRITTEN, and the tag is not renamed either — it comes
// through byte-identical, still deprecated, still rendering nothing. Inventing a
// one-hop chain from a missing second key would silently make the tag read the FIRST
// relationship's target as though that were the author's intent, converting a broken
// tag into a permanently WRONG one; renaming it without the chain would strand it as a
// live tag with no source. Both are worse than leaving it for a human.
assert_eq( 'R6.5 a missing second relationship key leaves the tag ENTIRELY alone',
	'{{second_related_post_custom_text rel:office|key:name}}',
	$migrate_tag( '{{second_related_post_custom_text rel:office|key:name}}' ) );

// An author-stated limit is not overwritten — it MOVES onto the last fanning step, and
// the earlier one still holds at 1 so the ceiling stays the N the flat spelling meant
// rather than N per parent. Nothing is left at tag level: one limit, one place.
assert_eq( 'R6.7 an explicit limit moves onto the last step, the earlier one holding at 1',
	'{{text src:refs,office,limit(1);refs,manager,limit(3)|key:name}}',
	$migrate_tag( '{{second_related_post_custom_text rel:office|rel_2:manager|key:name|limit:3}}' ) );

// ===========================================================================
echo "\nR7 — the key-clobber (#73): settled evidence survives the whole cascade\n";

// The defect: settle-then-delete. The rel → ref repair wrote `ref = rel`, deleted `rel`,
// and the later related_post entry — finding no `rel` — fell to its key-COPY branch and
// overwrote the settled value with the field key. `rel` outranks `key` under this token
// (the retired class read `rel` first), so every case below must land the REL-named field
// in the hop, with `key` surviving as the field read it also was.

// The #57 headline shape first: under src:ref the `ref` is the LIVE key and the `rel`
// beside it inert — the declarative $rel_fix let the inert one overwrite it, silently
// re-pointing a WORKING tag at a different post.
assert_eq( 'R7.0 base src:ref: the live ref survives, the inert rel is dropped (#57)',
	'{{text src:ref|ref:A}}',
	$migrate( '{{text src:ref|ref:A|rel:B}}' ) );

assert_eq( 'R7.1 base: rel beats key through the full cascade',
	'{{text src:ref|ref:B|key:fld}}',
	$migrate( '{{text src:related_post|rel:B|key:fld}}' ) );

assert_eq( 'R7.2 base: an inert ref beside them changes nothing',
	'{{text src:ref|ref:B|key:fld}}',
	$migrate( '{{text src:related_post|ref:inert|rel:B|key:fld}}' ) );

assert_eq( 'R7.3 try_ slot 1: rel beats key, key folds as the field read',
	'{{try_text A:src(refs,B,limit[1]);key(fld)}}',
	$migrate( '{{try_text src:related_post|rel:B|key:fld}}' ) );

// Slot 2's bare key (no use) is not a configured read on a per-slot-use container, so it
// folds away — the assertion is about the HOP carrying B, not fld.
assert_eq( 'R7.4 try_ slot 2: rel beats key in the hop',
	'{{try_text A:key(x)|B:src(refs,B,limit[1]);use(same)}}',
	$migrate( '{{try_text key:x|2-src:related_post|2-rel:B|2-key:fld}}' ) );

// term_ gets its own related_post entry (derived from the template list, same as the rel
// repair) so a deferred slot always has a downstream owner — without it the skip would
// orphan the `rel` AND leave the converter reporting a migration that changes nothing.
assert_eq( 'R7.5 term_: the deferred slot is consumed by its own related_post entry',
	'{{term_text src:ref|ref:B|key:fld}}',
	$migrate( '{{term_text src:related_post|rel:B|key:fld}}' ) );

assert_eq( 'R7.6 term_: key-copy still fires when rel was never there',
	'{{term_text src:ref|ref:fld|key:fld}}',
	$migrate( '{{term_text src:related_post|key:fld}}' ) );

// Report/run agreement extends to the deferred shape: something is reported AND the
// cascade changes the string (via the related_post entry, not the deferring one).
assert_eq( 'R7.7 deferred shape: reported and changed agree',
	array( true, true ),
	$agrees( '{{text src:related_post|rel:B|key:fld}}' ) );

assert_eq( 'R7.7b …and on term_, where the downstream owner is the new entry',
	array( true, true ),
	$agrees( '{{term_text src:related_post|rel:B|key:fld}}' ) );

// A present-but-EMPTY rel names nothing to move, but the dead key is still consumed —
// leaving it would have the converter report this entry forever while changing nothing.
assert_eq( 'R7.8 an empty rel is dropped, not left to re-report',
	'{{text src:current|key:fld}}',
	$migrate( '{{text src:current|rel:|key:fld}}' ) );

if ( $failures > 0 ) {
	echo "FAILED — {$failures} of {$count} checks failed\n";
	exit( 1 );
}
echo "PASSED — {$count} checks\n";
exit( 0 );
