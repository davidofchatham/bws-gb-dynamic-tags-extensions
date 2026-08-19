<?php
/**
 * Standalone unit harness for the MODIFIER → BASE tag migration (#84).
 *
 * Loads the SHIPPED transform (includes/tags/deprecated-tags.php), the shipped chain
 * grammar it emits through, and the shipped converter chaining loop — no transcribed
 * copies. A test-local copy of a migration rule is the exact drift these harnesses
 * exist to remove, and here it would be worse than usual: the property under test is
 * that a rewrite does not silently change what a stored tag reads.
 *
 * What this covers that no sibling harness does:
 *
 *   V1 MAPPING     — every row of the #84 mapping table, one assertion per stored shape.
 *                    Two of them exist only to prevent SILENT DAMAGE and are worth
 *                    naming: a relationship key must never be dropped (renaming the tag
 *                    and injecting a root token would leave `ref` unread and erase the
 *                    hop, which is the `rel` → `ref` defect arriving through the fix),
 *                    and the sidecars under `src:site` must be dropped (the modifier
 *                    callback returned before reading either, and a `refs` step now
 *                    ACCEPTS site input — so a survivor would compound into a hop that
 *                    has never once executed).
 *   V2 TARGET      — which tags the transform will and will not rename. The suffix is
 *                    looked up in the registered templates, so a prefix match on a tag
 *                    this plugin does not render is declined rather than renamed to a
 *                    tag that does not exist.
 *   V3 LIMIT       — a tag-level `limit` is LEFT for the base-tag chain entry, and the
 *                    cascade then absorbs it onto the fanning step. One implementation
 *                    of that rule, not two: the assertion is that the modifier transform
 *                    and the base entry between them produce the same wire a base tag
 *                    with the same flat triple produces.
 *   V4 CONVERTER   — report and run agree (prior art: fold-migration-test.php §M6 and
 *                    related-post-src-migration-test.php §R3), and a TRANSITIVE prefix
 *                    chain resolves in ONE converter run. The chaining is the shipped
 *                    TagConverter::resolve_full_chain(); this harness asserts it rather
 *                    than reimplementing the loop, which would assert nothing.
 *                    Both the option-CARRYING and the option-LESS shape, because the
 *                    name re-read after each rewrite used to stop at whitespace and a
 *                    bare tag has none (#111) — and bare is ordinary wire, not a corner.
 *
 * NOT covered here, deliberately: whether the migrated wire RESOLVES like the modifier
 * tag it replaced. That needs the source factory, a registered root and a real field
 * read — testbed territory (docs/testbed.md), and the fixture modifier family
 * that makes it reseedable is #85.
 *
 * Run:  php tools/test/modifier-base-migration-test.php   (exit 0 = pass, 1 = fail)
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

require __DIR__ . '/../../includes/helpers/serialization-order.php';
require __DIR__ . '/../../includes/helpers/slot-fold.php';
require __DIR__ . '/../../includes/helpers/slot-fold-compile.php';
require __DIR__ . '/../../includes/helpers/slot-fold-migrate.php';
require __DIR__ . '/../../includes/classes/class-migration-registry.php';
require __DIR__ . '/../../includes/classes/class-deprecated-tag-registry.php';
require __DIR__ . '/../../includes/classes/class-tag-template-registry.php';
require __DIR__ . '/../../includes/classes/admin/class-tag-converter.php';
require __DIR__ . '/../../includes/tags/deprecated-tags.php';

use BWS\DynamicTags\Admin\TagConverter;
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

// The transform reads the registered TEMPLATE list to decide what a prefix's tags are,
// so the harness registers templates rather than tag names. Five of the nine, covering
// the read shapes the mapping can meet: a field read, an image, a chain-only template,
// a datetime and a seam-routed one.
foreach ( array( 'text', 'image', 'permalink', 'datetime_single', 'email' ) as $key ) {
	TagTemplateRegistry::register_modifier_template( array( 'key' => $key ) );
}

// The family under test: prefix `view`, rooted at the registered source `view`. Prefix
// and root are bound per family (bws_modifier_root_transform) — this harness spells them
// out; enumerating templates into entries is #86.
$migrate = static function ( string $tag_string ): string {
	return bws_migrate_modifier_root_chain( $tag_string, 'view', 'view' );
};

// ===========================================================================
echo "V1 — the mapping table, one row at a time\n";

assert_eq( 'V1.1 no source stated → the base tag rooted at the source',
	'{{text src:view|key:bio}}',
	$migrate( '{{view_text key:bio}}' ) );

// SILENT-DAMAGE ROW. `src:ref` on a MODIFIER meant "relative to the modifier's root", and
// the base-tag reading of a bare `ref` key is nothing at all — the chain builder reads it
// only under the relationship token. So the hop has to become a real fanning STEP here,
// or the rename erases it.
assert_eq( 'V1.2 src:ref + ref → root, then a fanning refs step carrying the key',
	'{{text src:view;refs,office|key:bio}}',
	$migrate( '{{view_text src:ref|ref:office|key:bio}}' ) );

assert_eq( 'V1.3 srcTermIn → root, then a terms step',
	'{{text src:view;terms,genre|key:bio}}',
	$migrate( '{{view_text srcTermIn:genre|key:bio}}' ) );

// Wire order is the #44 order (a term step needs a POST input), which is also the order
// the modifier callback dispatched in: resolve the ref, then walk that post's terms.
assert_eq( 'V1.4 both → root, refs, terms in that order',
	'{{text src:view;refs,office;terms,genre|key:bio}}',
	$migrate( '{{view_text src:ref|ref:office|srcTermIn:genre|key:bio}}' ) );

// On a modifier tag "current" named ITS entity, not the ambient one. Carrying the token
// through would re-point the tag at the current post — a rendered-output change dressed
// as a faithful copy.
assert_eq( 'V1.5 src:current → rooted at the SOURCE, not at the ambient entity',
	'{{text src:view|key:bio}}',
	$migrate( '{{view_text src:current|key:bio}}' ) );

// SILENT-DAMAGE ROW, the other direction. Under `site` the modifier callback returned
// before reading either sidecar, so neither has ever run — and a `refs` step now accepts
// site input, so carrying one through would compound into a hop that never executed.
assert_eq( 'V1.6 src:site → the site root, with the inert sidecars dropped',
	'{{text src:site|key:bio}}',
	$migrate( '{{view_text src:site|ref:office|srcTermIn:genre|key:bio}}' ) );

// An ORPHAN `src:ref` keeps its step, spelled bare — the same shape and spelling
// bws_fold_chain_from_options() writes for flat base wire, and it compiles away to the
// root, so neither era invents a read the other does not have.
assert_eq( 'V1.7 src:ref with no field → the bare step, not a fabricated hop',
	'{{text src:view;refs}}',
	$migrate( '{{view_text src:ref}}' ) );

// GB eats an option literally named `source` before the editor ever sees it, so stored
// wire can hold either spelling. Both are the same axis and both must leave.
assert_eq( 'V1.8 the legacy `source` spelling is read and consumed',
	'{{text src:view;refs,office}}',
	$migrate( '{{view_text source:ref|ref:office}}' ) );

// Everything that is not the source axis is carried verbatim, and the result comes out in
// CANONICAL key order — a migrated tag that reorders itself the first time it is opened
// reads as a diff nobody made.
assert_eq( 'V1.9 unrelated options survive; canonical key order applied',
	'{{text src:view;refs,office|use:meta|key:bio|linkTo:post|newTab|fallback:none}}',
	$migrate( '{{view_text key:bio|newTab|src:ref|linkTo:post|ref:office|fallback:none|use:meta}}' ) );

// The image family carries `as` (the as+size fold's composite) and no link cluster. The
// source axis is the only thing this transform touches on any template.
assert_eq( 'V1.10 the image template migrates the same way, `as` untouched',
	'{{image as:url,large|src:view;refs,office|key:photo}}',
	$migrate( '{{view_image as:url,large|src:ref|ref:office|key:photo}}' ) );

// A chain-only template has no field read at all; the source is the entire tag.
assert_eq( 'V1.11 a chain-only template migrates to a bare rooted tag',
	'{{permalink src:view}}',
	$migrate( '{{view_permalink}}' ) );

// ===========================================================================
echo "\nV1b — the dead `rel` spelling, which this transform must settle itself\n";

// THE SILENT-ERASURE ROW, one spelling over. The sibling repair (bws_migrate_rel_to_ref)
// cannot reach a tag this transform renames: the converter runs every TAG rename before
// any OPTION entry, so a `rel` left unread here comes out of the cascade as a FLAT `ref`
// beside chain wire — which nothing consults, because a chain states its own steps. The
// hop is then gone with no key left to see it by. Asserted end to end (§V4.9) as well as
// here, since the shape only strands between two entries.
assert_eq( 'V1b.1 rel with no ref → the repair takes it, and the hop becomes a step',
	'{{text src:view;refs,office|key:bio}}',
	$migrate( '{{view_text src:ref|rel:office|key:bio}}' ) );

// The sibling's rule, unchanged: `rel` was never read under any token this transform
// sees, so a live `ref` wins outright.
assert_eq( 'V1b.2 ref wins over rel; the dead key is consumed',
	'{{text src:view;refs,live|key:bio}}',
	$migrate( '{{view_text src:ref|ref:live|rel:inert|key:bio}}' ) );

// A relationship key on a tag that states no source is evidence of a hop whose token the
// same bug dropped — so the repaired key brings `src:ref` with it, exactly as the sibling
// injects one.
assert_eq( 'V1b.3 no source stated + rel → repaired into a hop, not left flat',
	'{{text src:view;refs,office|key:bio}}',
	$migrate( '{{view_text rel:office|key:bio}}' ) );

// Under a NON-fanning token both spellings are inert and leave with the source axis.
// `src:current` named the ROOT, so a key beside it never hopped; carrying one into a step
// would invent a read rather than preserve one. Dropping it is the site row's rule
// applied to the token that shares its property.
assert_eq( 'V1b.4 src:current → the inert relationship key is consumed, not left flat',
	'{{text src:view|key:bio}}',
	$migrate( '{{view_text src:current|ref:office|rel:office|key:bio}}' ) );

assert_eq( 'V1b.5 src:site → rel is dropped with ref, and no repair fires under it',
	'{{text src:site|key:bio}}',
	$migrate( '{{view_text src:site|rel:office|key:bio}}' ) );

// An empty `rel:` names nothing to move, and the dead key still leaves — otherwise the
// converter reports a migration forever while changing nothing.
assert_eq( 'V1b.6 an empty rel is consumed without inventing a hop',
	'{{text src:view|key:bio}}',
	$migrate( '{{view_text rel:|key:bio}}' ) );

// ===========================================================================
echo "\nV2 — which tags are renamed at all\n";

assert_eq( 'V2.1 a different prefix is not this family and is left verbatim',
	'{{term_text src:ref|ref:office}}',
	$migrate( '{{term_text src:ref|ref:office}}' ) );

// The suffix is looked UP, not merely stripped: a prefix match on a tag this plugin does
// not render would otherwise be renamed to a tag that does not exist.
assert_eq( 'V2.2 an unknown suffix is declined rather than renamed to a missing tag',
	'{{view_something_else key:x}}',
	$migrate( '{{view_something_else key:x}}' ) );

assert_eq( 'V2.3 the base tag itself is not a modifier tag and is left verbatim',
	'{{text src:view|key:bio}}',
	$migrate( '{{text src:view|key:bio}}' ) );

assert_eq( 'V2.4 the prefix alone is not a tag name',
	'{{view key:x}}',
	$migrate( '{{view key:x}}' ) );

assert_eq( 'V2.5 target derivation: a registered suffix resolves to the base tag',
	'datetime_single', bws_modifier_base_target( 'view_datetime_single', 'view' ) );

assert_eq( 'V2.6 target derivation: the trailing underscore spelling is accepted',
	'text', bws_modifier_base_target( 'view_text', 'view_' ) );

assert_eq( 'V2.7 target derivation: an unregistered suffix has no target',
	'', bws_modifier_base_target( 'view_table', 'view' ) );

// An empty prefix would match every tag name, so it names no family.
assert_eq( 'V2.8 target derivation: an empty prefix matches nothing',
	'', bws_modifier_base_target( 'view_text', '' ) );

// A root is the one fact the transform cannot invent — without it there is no chain to
// state, and rewriting to a rootless base tag would re-point the tag at the ambient post.
assert_eq( 'V2.9 no root → the transform declines whole',
	'{{view_text key:bio}}',
	bws_migrate_modifier_root_chain( '{{view_text key:bio}}', 'view', '' ) );

// ===========================================================================
echo "\nV3 — the tag-level limit is left for the base-tag chain entry\n";

// The transform carries the key through UNTOUCHED. Modifier tags register a tag-level
// limit, and absorbing it here would be a second implementation of a rule the base entry
// already owns — two implementations of a limit mapping is how one tag comes to be stored
// two ways.
assert_eq( 'V3.1 the transform leaves `limit` where it is',
	'{{text src:view;refs,office|limit:3|key:bio}}',
	$migrate( '{{view_text src:ref|ref:office|limit:3|key:bio}}' ) );

// …and the base entry, on the renamed tag, absorbs it onto the fanning step. Run through
// the shipped cascade, not by calling the absorber directly.
$after_entries = static function ( string $tag_string ): string {
	[ $tag_name ] = MigrationRegistry::parse_tag_string( $tag_string );
	return MigrationRegistry::apply_option_migration( $tag_name, $tag_string );
};

bws_register_option_migrations();

assert_eq( 'V3.2 the cascade then absorbs it onto the step it bounds',
	'{{text src:view;refs,office,limit(3)|key:bio}}',
	$after_entries( $migrate( '{{view_text src:ref|ref:office|limit:3|key:bio}}' ) ) );

// A root that does not fan has nothing for a limit to bound, so the key survives rather
// than being written onto a step that resolves one entity.
assert_eq( 'V3.3 a non-fanning root leaves the limit alone',
	'{{text src:view|limit:3|key:bio}}',
	$after_entries( $migrate( '{{view_text limit:3|key:bio}}' ) ) );

// THE ONE-IMPLEMENTATION PROPERTY, stated as an equality rather than as a literal: the
// modifier route and the flat base route must land on the same wire for the same source
// triple, or a site with both shapes stores one read two ways.
assert_eq( 'V3.4 modifier route and flat base route agree, limit included',
	$after_entries( '{{text src:ref|ref:office|limit:3|key:bio}}' ),
	str_replace( 'src:view;', 'src:', $after_entries( $migrate( '{{view_text src:ref|ref:office|limit:3|key:bio}}' ) ) ) );

// ===========================================================================
echo "\nV4 — the converter: report/run agreement and transitive renames\n";

// The family's entries, one per registered template, against the shared transform. What
// #86 generates; spelled out here so this harness owns only what #84 builds.
foreach ( TagTemplateRegistry::get_modifier_templates() as $tpl ) {
	MigrationRegistry::register( array(
		'type'               => 'tag',
		'match_tag'          => 'view_' . $tpl['key'],
		'new_tag'            => $tpl['key'],
		'transform_callback' => bws_modifier_root_transform( 'view', 'view' ),
		'since'              => '1.17.0',
	) );
}

// REPORT: the scanner searches content for the names this returns, and the settings page
// groups a name by whether it has a migration path. Both must see the family.
$reported = MigrationRegistry::get_deprecated_tag_names();
assert_eq( 'V4.1 the scanner searches for the family tags',
	true, in_array( 'view_text', $reported, true ) && in_array( 'view_image', $reported, true ) );

assert_eq( 'V4.2 the family has a migration PATH (not a target-less entry)',
	true, MigrationRegistry::has_migration_path( 'view_text' ) );

assert_eq( 'V4.3 an unregistered sibling has none',
	false, MigrationRegistry::has_migration_path( 'view_table' ) );

// RUN: the registry's tag door. A transform_callback's result is returned verbatim, so
// the entry's declarative `new_tag` never runs — the transform renames the tag itself,
// and these two must not be able to name different tags.
assert_eq( 'V4.4 report and run agree: the reported tag is the one rewritten',
	'{{text src:view;refs,office|key:bio}}',
	MigrationRegistry::transform_tag( 'view_text', '{{view_text src:ref|ref:office|key:bio}}' ) );

assert_eq( 'V4.5 an unregistered tag name is returned verbatim',
	'{{view_table key:x}}',
	MigrationRegistry::transform_tag( 'view_table', '{{view_table key:x}}' ) );

// TRANSITIVE. An older prefix whose entry targets the still-registered modifier reaches
// the BASE tag in one converter run, because resolve_full_chain re-reads the tag name
// after each rewrite. Asserted against the shipped loop; no additional entry is built
// for the older prefix beyond the rename it already had.
MigrationRegistry::register( array(
	'type'      => 'tag',
	'match_tag' => 'portal_text',
	'new_tag'   => 'view_text',
	'since'     => '1.6.0',
) );

assert_eq( 'V4.6 an older prefix reaches the base tag in ONE run',
	'{{text src:view;refs,office|key:bio}}',
	TagConverter::resolve_full_chain( 'portal_text', '{{portal_text src:ref|ref:office|key:bio}}' ) );

assert_eq( 'V4.7 the chain terminates at the base tag (no further rewrite)',
	'{{text src:view|key:bio}}',
	TagConverter::resolve_full_chain( 'view_text', '{{view_text key:bio}}' ) );

// THE SAME TRANSITIVE PROPERTY ON A TAG THAT STATES NO OPTIONS (#111). Every case above
// carries at least one, and the chain loop's tag-name re-read used to stop at whitespace
// — which a bare tag has none of, so it swallowed the closing braces, matched no entry
// and stalled the chain one hop in. Bare is the ORDINARY shape, not a corner: `{{title}}`,
// `{{content}}` and `{{permalink}}` render with no options at all, and an option left at
// its default is never written into the tag (gb-constraints.md §Option Default
// Serialization). What is uncommon is the other half — one deprecated name renaming to
// another — which is why this survived. Not a breakage while it lasted: the tag landed on
// a still-registered name that still rendered, and a SECOND converter run finished it,
// which is exactly what makes plugin-integration.md §9's "in one run" the property to pin.
MigrationRegistry::register( array(
	'type'      => 'tag',
	'match_tag' => 'portal_permalink',
	'new_tag'   => 'view_permalink',
	'since'     => '1.6.0',
) );

assert_eq( 'V4.6b an older prefix on an OPTION-LESS tag reaches the base tag in ONE run',
	'{{permalink src:view}}',
	TagConverter::resolve_full_chain( 'portal_permalink', '{{portal_permalink}}' ) );

assert_eq( 'V4.7b …and its single-generation sibling still terminates',
	'{{permalink src:view}}',
	TagConverter::resolve_full_chain( 'view_permalink', '{{view_permalink}}' ) );

// END TO END, through BOTH converter steps: the rename (step 3) and then every option
// entry (step 4). The `rel` shape only strands BETWEEN the two — a flat `ref` beside
// chain wire reads as harmless in either half alone — so the pin has to run both.
$both_steps = static function ( string $tag_name, string $tag_string ): string {
	$renamed = TagConverter::resolve_full_chain( $tag_name, $tag_string );
	[ $current ] = MigrationRegistry::parse_tag_string( $renamed );
	return MigrationRegistry::apply_option_migration( $current, $renamed );
};

assert_eq( 'V4.9 a rel-spelled hop survives BOTH converter steps as a step, not a flat key',
	'{{text src:view;refs,office|key:bio}}',
	$both_steps( 'view_text', '{{view_text src:ref|rel:office|key:bio}}' ) );

// A no-op is byte-identical, which is what keeps the converter from reporting a change on
// every post it walks — and what apply_option_migration()'s cascade leans on.
assert_eq( 'V4.8 a declined tag comes back byte-identical',
	'{{view_something_else key:x}}',
	TagConverter::resolve_full_chain( 'view_something_else', '{{view_something_else key:x}}' ) );

// ===========================================================================
echo "\nV5 — the ENTRY GENERATOR (#86): one entry per registered template\n";

// V4 above spells its family's entries out by hand, which is what #84 shipped. #86 is the
// integration seam that generates them: a plugin owning a retired prefix calls ONE helper
// and gets one entry per registered modifier TEMPLATE, so nobody hand-maintains a list of
// tag names. That list has already drifted in the wild — the external plugin's alias table
// covers seven of nine templates because two register from elsewhere — which is why the
// generator reads the registry rather than accepting a list.
$made = bws_register_modifier_root_migrations( 'fixture', 'fixture', array( 'since' => '1.17.0' ) );

$expected_names = array_map(
	static fn( $tpl ) => 'fixture_' . $tpl['key'],
	TagTemplateRegistry::get_modifier_templates()
);

assert_eq( 'V5.1 one entry per REGISTERED TEMPLATE, in template order',
	$expected_names, $made );

assert_eq( 'V5.2 every generated name has a migration PATH',
	true, MigrationRegistry::has_migration_path( 'fixture_text' ) && MigrationRegistry::has_migration_path( 'fixture_datetime_single' ) );

// A suffix no template registers is not this family's tag and gets no entry — the same
// rule the transform applies (§V2.2), now visible at registration time.
assert_eq( 'V5.3 an unregistered suffix gets no entry',
	false, MigrationRegistry::has_migration_path( 'fixture_table' ) );

// THE BINDING. The entry carries the SHARED transform bound to this family's prefix and
// root, so the whole #84 mapping applies with no per-family rule — asserted through the
// registry's own door rather than by calling the transform directly.
assert_eq( 'V5.4 the generated entry rewrites through the shared transform, rooted at THIS family',
	'{{text src:fixture;refs,office|key:bio}}',
	MigrationRegistry::transform_tag( 'fixture_text', '{{fixture_text src:ref|ref:office|key:bio}}' ) );

// REPORT/RUN AGREEMENT for the generated half: the scanner searches for exactly the names
// the registry will rewrite. A generator that registered an entry the scanner never looks
// for would be invisible until someone read the code.
$names = MigrationRegistry::get_deprecated_tag_names();
assert_eq( 'V5.5 the scanner searches for every generated name',
	array(), array_values( array_diff( $expected_names, $names ) ) );

// NO CLOBBER. `view`'s entries were registered by hand above; a second pass must leave
// them alone rather than stacking a duplicate whose root could differ. An owner that
// hand-wrote one template's entry keeps it and gets the generator for the rest.
$again = bws_register_modifier_root_migrations( 'view', 'somewhere_else' );
assert_eq( 'V5.6 a name that already has a path is skipped whole',
	array(), $again );

assert_eq( 'V5.7 …and the hand-registered entry still governs it',
	'{{text src:view|key:bio}}',
	MigrationRegistry::transform_tag( 'view_text', '{{view_text key:bio}}' ) );

// THE SKIP TESTS ENTRY PRESENCE, NOT has_migration_path(), and the difference is a live
// shape rather than a hypothetical: this repo keeps REGISTRY-ONLY entries by standing
// policy (a `register()` call is never deleted for lacking migration data), and such an
// entry carries no `new_tag`. has_migration_path() answers FALSE for it, so a guard written
// that way would register a SECOND entry behind one that is already spoken for — and since
// every finder stops at the FIRST match, the generated one would be silently dead: the tag
// reports no path and never migrates, with nothing erroring.
MigrationRegistry::register( array(
	'type'      => 'tag',
	'match_tag' => 'legacy_text',
	'since'     => '1.0.0',
) );

assert_eq( 'V5.6b a registry-only entry (no new_tag) still claims its name',
	false, in_array( 'legacy_text', bws_register_modifier_root_migrations( 'legacy', 'fixture' ), true ) );

assert_eq( 'V5.6c …and no second entry was stacked behind it',
	1, count( array_filter( MigrationRegistry::get_by_type( 'tag' ), static fn( $e ) => 'legacy_text' === ( $e['match_tag'] ?? '' ) ) ) );

// The trailing-underscore spelling is the one an owner is likely to have in a constant.
$made_underscore = bws_register_modifier_root_migrations( 'older_', 'fixture', array( 'since' => '1.0.0' ) );
assert_eq( 'V5.8 the trailing-underscore prefix spelling is accepted',
	'older_text', $made_underscore[0] ?? '' );

// Neither fact can be invented, so an incomplete call registers NOTHING rather than
// something wrong — a rootless entry would re-point every tag at the ambient post.
assert_eq( 'V5.9 an empty prefix or root generates nothing',
	array( array(), array() ),
	array( bws_register_modifier_root_migrations( '', 'fixture' ), bws_register_modifier_root_migrations( 'nowhere', '' ) ) );

$entry_for = static function ( string $tag ): array {
	foreach ( MigrationRegistry::get_by_type( 'tag' ) as $e ) {
		if ( ( $e['match_tag'] ?? '' ) === $tag ) {
			return $e;
		}
	}
	return array();
};

$fixture_entry = $entry_for( 'fixture_text' );
assert_eq( 'V5.10 the entry names the BASE tag as its target',
	'text', $fixture_entry['new_tag'] ?? '' );

assert_eq( 'V5.11 the owner\'s `since` reaches the entry (the admin list shows it)',
	'1.17.0', $fixture_entry['since'] ?? '' );

// LIVENESS. Migrating does not retire the family — `register_modifier()` goes on minting
// its GB tags — so the entries belong in the settings page's Deprecated box. is_entry_live()
// still reads callback-presence as its interim proxy (FW-38 replaces it), so a generated
// entry carries the marker; filing a live family under "no longer registers with
// GenerateBlocks" would be false while it renders.
assert_eq( 'V5.12 a migrated-but-not-retired family is DEPRECATED, not removed',
	true, MigrationRegistry::is_entry_live( $fixture_entry ) );

// Retirement is the owner's decision on the owner's schedule, and the flag is what says so.
bws_register_modifier_root_migrations( 'retired', 'fixture', array( 'prefix_removed' => true ) );
assert_eq( 'V5.13 prefix_removed files the family under Removed instead',
	false, MigrationRegistry::is_entry_live( $entry_for( 'retired_text' ) ) );

// END TO END for a generated family, through BOTH converter steps — the same pin §V4.9
// makes for the hand-registered one. The `rel` shape strands only BETWEEN the two steps,
// so a generator that produced a subtly different entry shows up here.
assert_eq( 'V5.14 a generated entry survives both converter steps, limit absorbed',
	'{{text src:fixture;refs,office,limit(3)|key:bio}}',
	$both_steps( 'fixture_text', '{{fixture_text src:ref|rel:office|limit:3|key:bio}}' ) );

// ===========================================================================
echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures} of {$count} assertions\n";
	exit( 1 );
}
echo "PASSED: {$count} assertions\n";
exit( 0 );
