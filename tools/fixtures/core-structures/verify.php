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
$out4d = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{phone src:refs,related_staff_obj|key:main_line}}', [], $instance );
$check( 'RF1 chain spelling == flat spelling', (string) $out4d === (string) $out4, 'chain=' . var_export( $out4d, true ) . ' flat=' . var_export( $out4, true ) );

// datetime surface (manifest v3): ACF datetime pair + term date + site datetime.
$check( 'page field event_datetime seeded', get_post_meta( $page->ID, 'event_datetime', true ) === '2030-08-12 09:00:00', var_export( get_post_meta( $page->ID, 'event_datetime', true ), true ) );
$check( 'page field event_thisyear in current year', 0 === strpos( (string) get_post_meta( $page->ID, 'event_thisyear', true ), wp_date( 'Y' ) ), var_export( get_post_meta( $page->ID, 'event_thisyear', true ), true ) );
$check( 'term event_date seeded', $term && get_term_meta( $term->term_id, 'event_date', true ) === '20301005', $term ? var_export( get_term_meta( $term->term_id, 'event_date', true ), true ) : '' );

$out5 = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{datetime_single key:event_datetime}}', [], $instance );
$check( 'render {{datetime_single key:event_datetime}} on /matrix-post-meta/', strpos( (string) $out5, '2030' ) !== false, 'out=' . var_export( $out5, true ) );

$out6 = GenerateBlocks_Register_Dynamic_Tag::replace_tags( '{{datetime_single src:site|key:org_party_datetime}}', [], $instance );
$check( 'src:site datetime option renders', strpos( (string) $out6, '2030' ) !== false, 'out=' . var_export( $out6, true ) );

echo $fail ? "\nVERIFY FAILED ({$fail})\n" : "\nVERIFY PASSED\n";
exit( $fail ? 1 : 0 );
