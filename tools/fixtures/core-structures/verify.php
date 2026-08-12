<?php
/**
 * core-structures blueprint — post-seed smoke test.
 *
 * Renders through the real seam (wp() + fake GB instance) against the seeded
 * /matrix-post-meta/ page. NOT a matrix replacement — the matrices own the full
 * assertion set; this proves the applier landed and the seam reads it.
 *
 * Run (after seed.php, from the wp-litespeed env):
 *   bin/wp.sh <site> eval-file <mounted-repo>/tools/fixtures/core-structures/verify.php \
 *     --url=https://<site-domain>/matrix-post-meta/
 *
 * Assumes the seeded settings baseline (global CC 1, strip OFF).
 */
$fail  = 0;
$check = function ( $label, $ok, $detail = '' ) use ( &$fail ) {
	printf( "[%s] %s%s\n", $ok ? 'PASS' : 'FAIL', $label, $detail !== '' ? " — {$detail}" : '' );
	if ( ! $ok ) { $fail++; }
};

wp(); // real main query from --url

$instance          = new stdClass();
$instance->context = [];

$page = get_page_by_path( 'matrix-post-meta' );
$check( 'page matrix-post-meta exists', $page instanceof WP_Post );
$check( 'page has generated GB content', $page && strpos( $page->post_content, 'wp:generateblocks/text' ) !== false );
$check( 'page meta main_line', get_post_meta( $page->ID, 'main_line', true ) === '(987) 654-3210', var_export( get_post_meta( $page->ID, 'main_line', true ), true ) );

$term = get_term_by( 'slug', 'support', 'department' );
$check( 'term support exists', $term instanceof WP_Term );
$check( 'term meta phone', $term && get_term_meta( $term->term_id, 'phone', true ) === '(987) 111-2222' );

$check( 'option org_phone', get_option( 'options_org_phone' ) === '(987) 555-0000', var_export( get_option( 'options_org_phone' ), true ) );

// AMBIENT-CONTEXT GATE. Everything below this line reads the current post, so
// without --url the main query resolves nothing, $post is null, and every
// context-rooted row fails with an empty render — five FAILs that look like a
// code regression and are not. Bail with the invocation instead.
if ( ! $page instanceof WP_Post || get_queried_object_id() !== $page->ID ) {
	printf(
		"\nABORT — no ambient context (queried object: %s, expected matrix-post-meta: %s).\n"
		. "Re-run with the page URL, or every context-rooted check below fails empty:\n"
		. "  wp eval-file <repo>/tools/fixtures/core-structures/verify.php --url=https://<site-domain>/matrix-post-meta/\n",
		var_export( get_queried_object_id(), true ),
		$page instanceof WP_Post ? $page->ID : 'MISSING'
	);
	exit( 2 );
}

// Render seam end-to-end: phone tag off the matrix-post-meta page context.
$out = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{phone key:main_line}}', [], $instance );
$check( 'render {{phone key:main_line}} on /matrix-post-meta/ (CC 1 baseline)', strpos( (string) $out, 'tel:+1-987-654-3210' ) !== false, 'out=' . var_export( $out, true ) );

$out2 = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{phone srcTermIn:department|key:phone|limit:5}}', [], $instance );
$check( 'term-hop renders both valid dept numbers', strpos( (string) $out2, '987-111-2222' ) !== false && strpos( (string) $out2, '987-333-4444' ) !== false, 'out=' . var_export( $out2, true ) );

$out3 = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{phone src:site|key:org_phone}}', [], $instance );
$check( 'src:site option renders', strpos( (string) $out3, '987-555-0000' ) !== false, 'out=' . var_export( $out3, true ) );

$out4 = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{phone src:ref|ref:related_staff|key:main_line}}', [], $instance );
$check( 'src:ref hops to jane-partner', strpos( (string) $out4, '555-200-3000' ) !== false, 'out=' . var_export( $out4, true ) );

// ref-hop RETURN-FORMAT equivalence (RF1/RF2). The reader type-guards
// relationship|post_object and the coercer handles WP_Post as well as ids, but
// until manifest v6 every fixture field returned an ID — so these arms were
// asserted only against a harness shim's guess at ACF's shape. Compared to
// $out4 rather than to a literal: the POINT is that the format is invisible.
$out4b = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{phone src:ref|ref:related_staff_obj|key:main_line}}', [], $instance );
$check( 'RF1 relationship return_format:object == id format', (string) $out4b === (string) $out4, 'obj=' . var_export( $out4b, true ) . ' id=' . var_export( $out4, true ) );

$out4c = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{phone src:ref|ref:lead_staff_obj|key:main_line}}', [], $instance );
$check( 'RF2 post_object return_format:object == id format', (string) $out4c === (string) $out4, 'obj=' . var_export( $out4c, true ) . ' id=' . var_export( $out4, true ) );

// Same two fields through the CHAIN spelling (5h): `refs,<field>` compiles to
// the same ref step, so all four spellings above must agree.
//
// BOTH SIDES STATE THE LIMIT EXPLICITLY, and that is not tidiness. Since 1.17.0 the
// UNSET tag-level default is selected by the source SPELLING — flat wire bounds at 1,
// chain wire does not (bws_limit_default) — so comparing a bare chain against a bare
// flat tag compares two different quantities and fails by design. Pinning `limit:1`
// on both asks the question this check exists to ask: do the two spellings reach the
// same SOURCE. The differing default is pinned separately, in
// tools/test/limit-default-test-matrix.md §L4.
$out4e = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{phone src:ref|ref:related_staff_obj|key:main_line|limit:1}}', [], $instance );
$out4d = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{phone src:refs,related_staff_obj|key:main_line|limit:1}}', [], $instance );
$check( 'RF1 chain spelling == flat spelling (limit stated on both)', (string) $out4d === (string) $out4e, 'chain=' . var_export( $out4d, true ) . ' flat=' . var_export( $out4e, true ) );

// And the DIFFERING default is itself asserted, so a future change that quietly
// re-unified the two spellings shows up here rather than only in the matrix.
$out4f = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{phone src:refs,related_staff_obj|key:main_line}}', [], $instance );
$check(
	'unset limit: chain wire fans where flat wire bounds at 1',
	(string) $out4f !== (string) $out4 && '' !== (string) $out4f,
	'chain=' . var_export( $out4f, true ) . ' flat=' . var_export( $out4, true )
);

// datetime surface (manifest v3): ACF datetime pair + term date + site datetime.
$check( 'page field event_datetime seeded', get_post_meta( $page->ID, 'event_datetime', true ) === '2030-08-12 09:00:00', var_export( get_post_meta( $page->ID, 'event_datetime', true ), true ) );
$check( 'page field event_thisyear in current year', 0 === strpos( (string) get_post_meta( $page->ID, 'event_thisyear', true ), wp_date( 'Y' ) ), var_export( get_post_meta( $page->ID, 'event_thisyear', true ), true ) );
$check( 'term event_date seeded', $term && get_term_meta( $term->term_id, 'event_date', true ) === '20301005', $term ? var_export( get_term_meta( $term->term_id, 'event_date', true ), true ) : '' );

$out5 = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{datetime_single key:event_datetime}}', [], $instance );
$check( 'render {{datetime_single key:event_datetime}} on /matrix-post-meta/', strpos( (string) $out5, '2030' ) !== false, 'out=' . var_export( $out5, true ) );

$out6 = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{datetime_single src:site|key:org_party_datetime}}', [], $instance );
$check( 'src:site datetime option renders', strpos( (string) $out6, '2030' ) !== false, 'out=' . var_export( $out6, true ) );

// content surface (#58): the fixture state the CT rows need. The hop's own
// correctness is the matrix's job — this asserts only that the state exists,
// i.e. that a CT row failing means the CODE moved and not the seed.
$content_page = get_page_by_path( 'matrix-content' );
$check( 'page matrix-content exists', $content_page instanceof WP_Post );
$check(
	'matrix-content field values differ from jane\'s (CT rows need the contrast)',
	$content_page && '(321) 555-0100' === get_post_meta( $content_page->ID, 'main_line', true ),
	$content_page ? var_export( get_post_meta( $content_page->ID, 'main_line', true ), true ) : ''
);
// CT3 asserts the excerpt's read-more link points at the EXCERPTED post, which
// is only readable if the two permalinks differ — cheap to assert, and a seed
// change that collapsed them would make the row silently vacuous.
$jane = get_page_by_path( 'jane-partner', OBJECT, 'staff' );
$check(
	'jane\'s permalink differs from matrix-content\'s (CT3 read-more target)',
	$jane && $content_page && get_permalink( $jane->ID ) !== get_permalink( $content_page->ID ),
	$jane && $content_page ? get_permalink( $jane->ID ) . ' vs ' . get_permalink( $content_page->ID ) : 'missing fixture'
);
$sales = get_term_by( 'slug', 'sales', 'department' );
$check(
	'term blurb seeded on sales, absent on support (CT5 walk)',
	$sales && '' !== (string) get_term_meta( $sales->term_id, 'blurb', true )
		&& $term && '' === (string) get_term_meta( $term->term_id, 'blurb', true ),
	'sales=' . ( $sales ? var_export( get_term_meta( $sales->term_id, 'blurb', true ), true ) : 'no term' )
		. ' support=' . ( $term ? var_export( get_term_meta( $term->term_id, 'blurb', true ), true ) : 'no term' )
);

// -----------------------------------------------------------------------------
// External-source contract (#85): the two registered roots, the fixture source's
// deterministic resolution, and the modifier family.
//
// These read through the ROOT, not through the ambient page, so they are valid
// under this file's --url even though that url is matrix-post-meta: a registered
// root resolves its own entity. That independence is the property.
// -----------------------------------------------------------------------------
$root_target = get_page_by_path( 'fixture-root', OBJECT, 'staff' );
$check( 'fixture root target staff/fixture-root exists', $root_target instanceof WP_Post );
$check(
	'fixture root target carries its own role (the ambient contrast)',
	$root_target && 'Fixture Root Role' === get_post_meta( $root_target->ID, 'role', true ),
	$root_target ? var_export( get_post_meta( $root_target->ID, 'role', true ), true ) : ''
);

$root_rows = function_exists( 'bws_registered_root_rows' ) ? bws_registered_root_rows() : array();
$root_keys = wp_list_pluck( $root_rows, 'value' );
$check( 'class-route root offered in the chain-root rows', in_array( 'fixture', $root_keys, true ), 'rows=' . implode( ',', $root_keys ) );
$check( 'filter-route root offered in the chain-root rows', in_array( 'fixture_alt', $root_keys, true ), 'rows=' . implode( ',', $root_keys ) );

$fx1 = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{text src:fixture|key:role}}', [], $instance );
$check( 'class-route root resolves from seeded content', 'Fixture Root Role' === trim( (string) $fx1 ), 'out=' . var_export( $fx1, true ) );

$fx2 = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{text src:fixture_alt|key:venue_city}}', [], $instance );
$check( 'filter-route root resolves from seeded content', 'Chatham' === trim( (string) $fx2 ), 'out=' . var_export( $fx2, true ) );

// NOT REQUEST STATE: the same tag renders the same value with a DIFFERENT ambient
// post queried. A root that quietly fell through to the ambient entity would
// answer this page's 'Ambient Page Role' on one of the two.
$corpus_page = get_page_by_path( 'matrix-fixture-roots' );
$check( 'page matrix-fixture-roots exists', $corpus_page instanceof WP_Post );
$check(
	'corpus page carries the ambient contrast value',
	$corpus_page && 'Ambient Page Role' === get_post_meta( $corpus_page->ID, 'role', true ),
	$corpus_page ? var_export( get_post_meta( $corpus_page->ID, 'role', true ), true ) : ''
);
if ( $corpus_page instanceof WP_Post ) {
	$prev_post = $GLOBALS['post'] ?? null;
	$GLOBALS['post'] = $corpus_page;
	setup_postdata( $corpus_page );
	$fx3 = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{text src:fixture|key:role}}', [], $instance );
	$fx4 = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{text key:role}}', [], $instance );
	$GLOBALS['post'] = $prev_post;
	if ( $prev_post ) {
		setup_postdata( $prev_post );
	}
	$check( 'rooted read is INVARIANT under a different ambient post', trim( (string) $fx3 ) === trim( (string) $fx1 ), 'here=' . var_export( $fx3, true ) . ' there=' . var_export( $fx1, true ) );
	$check( 'the unrooted read on that same post is DIFFERENT (so the row is not vacuous)', 'Ambient Page Role' === trim( (string) $fx4 ), 'out=' . var_export( $fx4, true ) );
}

$tags = class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ? array_keys( GenerateBlocks_Register_Dynamic_Tag::get_tags() ?? [] ) : array();
$check( 'fixture modifier family registered (fixture_text)', in_array( 'fixture_text', $tags, true ) );
// The WHOLE family, counted against the TEMPLATE LIST it is generated from rather than
// against a literal nine — a template added later must mint a fixture tag too, and a
// hardcoded count would go stale silently. Not counted against the `term_` family, which
// carries two pre-template legacy tags (`term_list`, `term_meta`) that no template makes.
$fx_count  = count( preg_grep( '/^fixture_/', $tags ) );
$tpl_count = class_exists( 'BWS\DynamicTags\TagTemplateRegistry' )
	? count( \BWS\DynamicTags\TagTemplateRegistry::get_modifier_templates() )
	: 0;
$check( 'fixture family has one tag per modifier template', $tpl_count > 0 && $fx_count === $tpl_count, "fixture_={$fx_count} templates={$tpl_count}" );

// The migration's promise, assertable before any converter runs: the modifier tag
// and the base tag it must become render the same bytes.
$mod  = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{fixture_text key:role}}', [], $instance );
$check( 'fixture_text renders == its migrated base-tag wire', trim( (string) $mod ) === trim( (string) $fx1 ), 'modifier=' . var_export( $mod, true ) . ' base=' . var_export( $fx1, true ) );

// The pairs read a FIELD KEY rather than `use:title`, because a modifier template's
// text core ignores `use` entirely — `use:title` renders empty on every modifier
// family (pre-existing, issue #88), and a pair of empties would agree
// whatever the migration did.
$mod2  = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{fixture_text src:ref|ref:related_staff|key:role|limit:1}}', [], $instance );
$base2 = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{text src:fixture;refs,related_staff|key:role|limit:1}}', [], $instance );
$check( 'relationship-hop shape: modifier == chain wire', trim( (string) $mod2 ) === trim( (string) $base2 ) && 'Fixture Ref Role' === trim( (string) $mod2 ), 'modifier=' . var_export( $mod2, true ) . ' chain=' . var_export( $base2, true ) );

$mod3  = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{fixture_text srcTermIn:department|key:email}}', [], $instance );
$base3 = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{text src:fixture;terms,department|key:email|limit:1}}', [], $instance );
$check( 'taxonomy-sidecar shape: modifier == chain wire (-> the ROOT\'s term)', trim( (string) $mod3 ) === trim( (string) $base3 ) && 'sales@example.test' === trim( (string) $mod3 ), 'modifier=' . var_export( $mod3, true ) . ' chain=' . var_export( $base3, true ) );

$mod4  = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{fixture_text src:ref|ref:related_staff|srcTermIn:department|key:email}}', [], $instance );
$base4 = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{text src:fixture;refs,related_staff;terms,department|key:email|limit:1}}', [], $instance );
$check( 'both-sidecars shape: modifier == chain wire (-> the hop TARGET\'s term)', trim( (string) $mod4 ) === trim( (string) $base4 ) && 'warehouse@example.test' === trim( (string) $mod4 ), 'modifier=' . var_export( $mod4, true ) . ' chain=' . var_export( $base4, true ) );

// The one shape whose OUTPUT the migration changes, asserted as a DIVERGENCE so it
// cannot be mistaken for a regression later: the modifier returns on `site` before
// reading either sidecar, while the wire it migrates to renders the site read.
$mod5  = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{fixture_text src:site|ref:related_staff|srcTermIn:department|use:key|key:organization_email}}', [], $instance );
$base5 = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{text src:site|use:key|key:organization_email}}', [], $instance );
$check( 'site shape renders EMPTY today and its migrated wire renders (a KNOWN divergence)', '' === trim( (string) $mod5 ) && 'info@example.test' === trim( (string) $base5 ), 'modifier=' . var_export( $mod5, true ) . ' migrated=' . var_export( $base5, true ) );

echo $fail ? "\nVERIFY FAILED ({$fail})\n" : "\nVERIFY PASSED\n";
exit( $fail ? 1 : 0 );
