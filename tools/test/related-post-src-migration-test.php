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
 *
 * NOT covered here, deliberately: whether migrated wire RESOLVES like the legacy wire it
 * replaced. That needs the source factory and a real field read — testbed territory
 * (§Development), and the four source classes are inert as of 1.17.0 so the legacy half
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

assert_eq( 'R1.4 an explicit ref wins; a stale rel beside it is dropped',
	'{{text src:ref|ref:live|key:title}}',
	bws_migrate_related_post_src( '{{text src:related_post|ref:live|rel:stale|key:title}}' ) );

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

$migrate = static function ( string $tag_string ): string {
	[ $tag_name ] = MigrationRegistry::parse_tag_string( $tag_string );
	return MigrationRegistry::apply_option_migration( $tag_name, $tag_string );
};

// The base-tag half has shipped since 1.6.0 — pinned here as the reference behaviour the
// two new families are brought into line with, not as new coverage. Note the emitted key
// order is NOT canonical: the declarative pipeline appends a renamed key rather than
// re-sorting. Pre-existing, unchanged here, and pinned so a future canonicalization is a
// deliberate edit rather than a surprise.
assert_eq( 'R4.1 base tag (pre-existing): rel → ref, src:ref injected',
	'{{text src:ref|key:title|ref:vendor}}',
	$migrate( '{{text rel:vendor|key:title}}' ) );

assert_eq( 'R4.2 term_ modifier: same repair, derived from the template list',
	'{{term_text src:ref|key:title|ref:vendor}}',
	$migrate( '{{term_text rel:vendor|key:title}}' ) );

assert_eq( 'R4.3 term_ modifier with no per-slot read still repairs',
	'{{term_permalink src:ref|ref:vendor}}',
	$migrate( '{{term_permalink rel:vendor}}' ) );

// An existing src WINS over source_inject (array_merge order), so a tag that already
// states its source keeps it and only gains the renamed key. Faithful: `rel` was never
// read under src:current either.
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
	'{{try_text A:src(refs,vendor);key(title)}}',
	$migrate( '{{try_text rel:vendor|key:title}}' ) );

assert_eq( 'R4.6 try_ slot 3: 3-rel lands on slot C, not on slot 1',
	'{{try_text A:key(a)|C:src(refs,vendor);use(same)}}',
	$migrate( '{{try_text key:a|3-src:ref|3-rel:vendor}}' ) );

assert_eq( 'R4.7 several slots migrate in one pass, each keeping its OWN field',
	'{{try_text A:src(refs,a)|B:src(refs,b);use(same)|E:src(refs,c);use(same)}}',
	$migrate( '{{try_text rel:a|2-rel:b|5-rel:c}}' ) );

// `rel` is a registered option on NO current tag, so there is nothing live to collide
// with — but the transform must still be a strict no-op when none is present (the
// contract apply_option_migration leans on). Asserted against the transform directly,
// since end-to-end the fold entry legitimately rewrites the tag.
assert_eq( 'R4.8 no rel present → the transform returns the input verbatim',
	'{{try_text src:ref|ref:vendor|key:title}}',
	bws_migrate_slot_rel_to_ref( '{{try_text src:ref|ref:vendor|key:title}}' ) );

assert_eq( 'R4.9 an explicit slot src is not overwritten',
	'{{try_text 2-src:current|2-ref:vendor}}',
	bws_migrate_slot_rel_to_ref( '{{try_text 2-src:current|2-rel:vendor}}' ) );

assert_eq( 'R4.10 an existing slot ref wins; the stale rel is dropped',
	'{{try_text 2-src:ref|2-ref:live}}',
	bws_migrate_slot_rel_to_ref( '{{try_text 2-src:ref|2-ref:live|2-rel:stale}}' ) );

// {{join}} postdates the rel → ref rename by nine releases; no entry is registered for
// it, so a hand-typed `rel` there is left alone rather than given a meaning it never had.
assert_eq( 'R4.11 {{join}} has no rel entry — left alone',
	'{{join rel:vendor}}',
	$migrate( '{{join rel:vendor}}' ) );

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED — {$failures} of {$count} checks failed\n";
	exit( 1 );
}
echo "PASSED — {$count} checks\n";
exit( 0 );
