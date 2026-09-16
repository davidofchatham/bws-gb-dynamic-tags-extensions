<?php
/**
 * core-structures blueprint — the modifier→base MIGRATION, end to end (#86).
 *
 * What no pure harness can reach: the converter reading REAL stored content, rewriting it,
 * and the rewritten wire RENDERING through the real source factory against a real field
 * read. modifier-base-migration-test.php owns the transform and the generated entries as
 * strings; this owns "the page renders after the run".
 *
 * NO PRE-CONVERSION SIDE, since the fixture stood down from `register_modifier()` ahead of
 * FW-129 withdrawing it — the seeded
 * `fixture_*` wire is unregistered and renders its own braces, so there is no "before" to
 * compare against and byte-identity is not a property this can measure. That is the accepted
 * cost recorded on the registrar in schema.php. What is left is the shape that matters after
 * a prefix is retired: the converter still finds the wire, still rewrites it, and what it
 * writes still renders. The expected VALUES of the converted wire are pinned in verify.php,
 * which renders each FR3 target directly.
 *
 * MUTATES THE CORPUS ON PURPOSE. It converts /matrix-fixture-roots/ in place, exactly as an
 * admin clicking Migrate would — and its reach probe (§5) then runs over every OTHER post
 * the converter reports, which is what a real bulk run does. That is why the corpus is a
 * blueprint: reseed and the pre-conversion wire is back, so this is repeatable rather than
 * one-shot. RESEED AFTERWARDS; other matrices read those pages.
 *
 * Run (after seed.php, from the wp-litespeed env):
 *   bin/wp.sh <site> eval-file <mounted-repo>/tools/fixtures/core-structures/verify-migration.php \
 *     --url=https://<site-domain>/matrix-fixture-roots/
 *   bin/seed.sh <site> core-structures     # ← put the corpus back
 *
 * The three properties, in the order the converter meets them:
 *
 *   REPORT   scan() names this page, and names every `fixture_*` tag in it as having a
 *            migration path. A report that lists work the run will not do (or the reverse)
 *            is the failure mode the shared match rule exists to prevent.
 *   RUN      migrate_post() rewrites every one of them, and a SECOND scan no longer sees
 *            the page — report and run agreeing is stated as "nothing left to report".
 *   RENDER   every converted tag renders something, and the wire it replaced renders its own
 *            braces. Both halves are needed: the first is the non-vacuity the byte-identity
 *            check used to supply, and the second is the statement the whole FW-129 gate
 *            rests on — unconverted wire on a published page is visible breakage.
 *
 * Also pinned: the converted wire states its source ONCE — the retired flat controls
 * (`ref`, `srcTermIn`, the legacy `source` spelling) are gone, which is the authoring
 * promise the migration is for.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	echo "Run via wp-cli eval-file.\n";
	exit( 1 );
}

$fail  = 0;
$check = function ( $label, $ok, $detail = '' ) use ( &$fail ) {
	printf( "[%s] %s%s\n", $ok ? 'PASS' : 'FAIL', $label, '' !== $detail ? " — {$detail}" : '' );
	if ( ! $ok ) { $fail++; }
};

// The shipped converter always runs as an authenticated administrator, and WP-CLI runs as
// nobody, so this drives migrate_post() in a different capability context unless told
// otherwise (#99). See lib-admin-context.php for the measurement, and for why
// replay-tags.php is deliberately excluded from the same treatment.
require_once __DIR__ . '/lib-admin-context.php';
bws_fixture_assume_administrator( static function ( $m ) { printf( "[INFO] %s\n", $m ); } );

wp(); // real main query from --url

$instance          = new stdClass();
$instance->context = [];

$slug = 'matrix-fixture-roots';
$page = get_page_by_path( $slug );
$check( "page {$slug} exists", $page instanceof WP_Post );

// AMBIENT-CONTEXT GATE, same reasoning as verify.php: the FR rows that are NOT rooted read
// the current post, so without --url every comparison below is empty-vs-empty and passes
// vacuously. That is worse than failing.
if ( ! $page instanceof WP_Post || get_queried_object_id() !== $page->ID ) {
	printf(
		"\nABORT — no ambient context (queried object: %s, expected {$slug}: %s).\n"
		. "Re-run with the page URL:\n"
		. "  wp eval-file <repo>/tools/fixtures/core-structures/verify-migration.php --url=https://<site-domain>/{$slug}/\n",
		var_export( get_queried_object_id(), true ),
		$page instanceof WP_Post ? $page->ID : 'MISSING'
	);
	exit( 2 );
}

/** Every `{{fixture_… }}` tag string in a body of content, in document order. */
$modifier_tags = static function ( string $content ): array {
	preg_match_all( '/\{\{fixture_[a-z_]+(?:\s[^}]*)?\}\}/', $content, $m );
	return $m[0];
};

/** Every `{{<base tag> …}}` string in document order, for the same tag list. */
$base_tags = static function ( string $content, array $names ): array {
	$pattern = '/\{\{(?:' . implode( '|', array_map( 'preg_quote', $names ) ) . ')(?:\s[^}]*)?\}\}/';
	preg_match_all( $pattern, $content, $m );
	return $m[0];
};

$render = static function ( string $tag ) use ( $instance ): string {
	return trim( (string) GenerateBlocks_Register_Dynamic_Tag::replace_tags( $tag, [], $instance ) );
};

// ---------------------------------------------------------------------------
// 0. The corpus, before anything runs.
// ---------------------------------------------------------------------------
$before      = $page->post_content;
$before_tags = $modifier_tags( $before );

$check( 'corpus holds the FR3 modifier tags', count( $before_tags ) >= 6, 'found=' . count( $before_tags ) );

// THE STATE THE CONVERTER IS FOR, asserted at the top so nothing below can be read as
// measuring a registered family: unconverted `fixture_*` wire renders its own braces. GB
// hands an unknown tag back untouched, so "renders literally" is the tag string itself.
$before_render = array();
foreach ( $before_tags as $tag ) {
	$before_render[ $tag ] = $render( $tag );
}
$rendered_live = array_keys( array_filter( $before_render, static fn( $v, $t ) => $v !== $t, ARRAY_FILTER_USE_BOTH ) );
$check( 'every seeded modifier tag renders LITERALLY before conversion', array() === $rendered_live, 'not literal: ' . implode( ' ', $rendered_live ) );

// ---------------------------------------------------------------------------
// 1. REPORT — the converter's scan.
// ---------------------------------------------------------------------------
$scan = \BWS\DynamicTags\Admin\TagConverter::scan();
$row  = null;
foreach ( $scan as $r ) {
	if ( (int) $r['post_id'] === (int) $page->ID ) {
		$row = $r;
		break;
	}
}

$check( 'scan reports the corpus page', null !== $row, 'scanned=' . count( $scan ) . ' posts' );

$reported = $row ? wp_list_pluck( $row['deprecated_tags'], 'tag' ) : array();
$expected = array_values( array_unique( array_map(
	static function ( $t ) {
		[ $name ] = \BWS\DynamicTags\MigrationRegistry::parse_tag_string( $t );
		return $name;
	},
	$before_tags
) ) );
sort( $reported );
sort( $expected );
$check( 'scan names every modifier tag the page holds', $reported === $expected, 'reported=' . implode( ',', $reported ) . ' expected=' . implode( ',', $expected ) );

$with_path = $row ? array_filter( $row['deprecated_tags'], static fn( $d ) => ! empty( $d['has_migration'] ) ) : array();
$check( 'every reported tag has a migration PATH (the generated entries carry new_tag)', $row && count( $with_path ) === count( $row['deprecated_tags'] ), 'with_path=' . count( $with_path ) . '/' . ( $row ? count( $row['deprecated_tags'] ) : 0 ) );

// ---------------------------------------------------------------------------
// 2. RUN — migrate the page, exactly as the admin button does.
// ---------------------------------------------------------------------------
$result = \BWS\DynamicTags\Admin\TagConverter::migrate_post( (int) $page->ID );
$check( 'migrate_post rewrote the page', ! empty( $result['changed'] ), 'result=' . wp_json_encode( $result ) );
$check( 'one rewrite per modifier tag', (int) $result['tag_count'] === count( $before_tags ), 'tag_count=' . $result['tag_count'] . ' tags=' . count( $before_tags ) );

$after = get_post( $page->ID )->post_content;
$check( 'no modifier tag survives the run', array() === $modifier_tags( $after ), 'left=' . implode( ' ', $modifier_tags( $after ) ) );

// REPORT/RUN AGREEMENT, stated as the converter's own next answer: a second scan has
// nothing left to say about this page. A report that outlived its run would mean the two
// halves disagree about what a migration IS.
$rescan = \BWS\DynamicTags\Admin\TagConverter::scan();
$still  = false;
foreach ( $rescan as $r ) {
	if ( (int) $r['post_id'] === (int) $page->ID && ! empty( $r['deprecated_tags'] ) ) {
		$still = true;
	}
}
$check( 'a second scan reports no deprecated tag on the page', ! $still );

// ---------------------------------------------------------------------------
// 3. RENDER — byte identity, tag by tag, in document order.
// ---------------------------------------------------------------------------
$base_names = array();
foreach ( \BWS\DynamicTags\TagTemplateRegistry::get_modifier_templates() as $tpl ) {
	$base_names[] = $tpl['key'];
}
$after_tags = $base_tags( $after, $base_names );

// The FR4 section already held base tags before the run, so the after-list is longer than
// the modifier list and cannot be paired off by index. Re-derive each converted string
// instead — the SAME two shipped calls migrate_post() makes, in that order (rename step,
// then the option cascade). That mirroring is a trip-hazard on its own, so it is never
// trusted: the very next check requires each derived string to be present in the stored
// page, which fails loudly if this order ever stops matching the converter's.
$converted = array();
foreach ( $before_tags as $tag ) {
	[ $name ]        = \BWS\DynamicTags\MigrationRegistry::parse_tag_string( $tag );
	$renamed         = \BWS\DynamicTags\Admin\TagConverter::resolve_full_chain( $name, $tag );
	[ $now ]         = \BWS\DynamicTags\MigrationRegistry::parse_tag_string( $renamed );
	$converted[$tag] = \BWS\DynamicTags\MigrationRegistry::apply_option_migration( $now, $renamed );
}

$check(
	'every converted string is what the stored page now holds',
	0 === count( array_filter( $converted, static fn( $c ) => false === strpos( $after, $c ) ) ),
	'missing=' . implode( ' ', array_filter( $converted, static fn( $c ) => false === strpos( $after, $c ) ) )
);

// NON-VACUITY, and the only render property left once there is no "before" to compare with:
// every converted row renders a value, and none of them renders braces. A conversion that
// produced wire the renderer does not recognize would otherwise pass §2 and §4 untouched.
// The VALUES are verify.php's, which renders each of these targets directly.
foreach ( $converted as $old => $new ) {
	$out   = $render( $new );
	$label = "{$old}  →  {$new}";
	$check( 'the converted wire renders: ' . $label, '' !== $out && $out !== $new, 'after=' . var_export( $out, true ) );
}

// ---------------------------------------------------------------------------
// 4. The AUTHORING promise: the source is stated in exactly one place.
// ---------------------------------------------------------------------------
$retired = array();
foreach ( $converted as $new ) {
	[ , $options ] = \BWS\DynamicTags\MigrationRegistry::parse_tag_string( $new );
	foreach ( array( 'ref', 'rel', 'srcTermIn', 'source' ) as $flat ) {
		if ( array_key_exists( $flat, $options ) ) {
			$retired[] = "{$flat} in {$new}";
		}
	}
}
$check( 'the retired flat source controls are gone from every converted tag', array() === $retired, implode( '; ', $retired ) );

// ---------------------------------------------------------------------------
// 5. REACH — asserted, not merely stated. scan() is a POSTS-table query, so a tag living
// in the OPTIONS table (a block widget) is out of reach: it is neither reported nor
// rewritten, and once the prefix is retired it renders its own braces there forever.
// Probed with a real option rather than described in a comment, because "the converter
// does not reach it" and "the converter reached it and left it alone" are the same picture
// from the outside and only one of them is true.
// ---------------------------------------------------------------------------
$widget_option = 'bws_fixture_widget_probe';
$widget_wire   = '{{fixture_text key:role}}';
update_option( $widget_option, $widget_wire, false );

$widget_scan = \BWS\DynamicTags\Admin\TagConverter::scan();
$widget_ids  = wp_list_pluck( $widget_scan, 'post_id' );
foreach ( $widget_ids as $id ) {
	\BWS\DynamicTags\Admin\TagConverter::migrate_post( (int) $id );
}

// The scan can only ever name POSTS, so the probe's immunity is the assertion: a full run
// over everything the converter reports leaves it byte-identical.
$check( 'a modifier tag in the OPTIONS table survives a full converter run byte-identical', $widget_wire === get_option( $widget_option ), 'stored=' . var_export( get_option( $widget_option ), true ) . ' ran over ' . count( $widget_ids ) . ' reported posts' );

// THE COST OF BEING OUT OF REACH, now that the prefix is retired: it does not go on
// rendering, it renders its own braces on a published surface with nothing left to fix it.
// This is FW-129's gate stated as a measurement rather than as a paragraph.
$widget_render = $render( get_option( $widget_option ) );
$check( 'and it renders its own braces — out of reach is permanent, and after retirement it is visible', $widget_wire === $widget_render, 'out=' . var_export( $widget_render, true ) );

delete_option( $widget_option );

echo $fail
	? "\nMIGRATION VERIFY FAILED ({$fail})\n"
	: "\nMIGRATION VERIFY PASSED — reseed to restore the pre-conversion corpus:\n  bin/seed.sh <site> core-structures\n";
exit( $fail ? 1 : 0 );
