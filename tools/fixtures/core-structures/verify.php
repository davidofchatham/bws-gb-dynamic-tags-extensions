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

// ---------------------------------------------------------------------------
// SOURCE GATE corpus (v13, ADR 0007) — fold matrix §F17.
// ---------------------------------------------------------------------------
// The only fixture in the blueprint whose rows READ DIFFERENTLY PER VIEWER, which is
// why it is asserted here rather than left to the matrix alone: a hand run measures
// whichever viewer the operator happened to be, and the two arms are the property.
// Each shape is rendered TWICE off one ambient post, as administrator (this script's
// own context) and as nobody, and the assertions are on the DIVERGENCE.
//
// Fixture integrity first. Every render below reads `Grace` when the gate works and
// also when `gate_staff` is empty, so an unseeded field would pass the anonymous arm
// silently — these four checks are what make the arms mean something.
$gate_page = get_page_by_path( 'matrix-gate' );
$check( 'page matrix-gate exists', $gate_page instanceof WP_Post );
if ( $gate_page instanceof WP_Post ) {
	$gate_refs   = (array) get_post_meta( $gate_page->ID, 'gate_staff', true );
	$gate_status = array_map(
		function ( $id ) {
			$p = get_post( (int) $id );
			return $p ? $p->post_status : 'MISSING';
		},
		$gate_refs
	);
	$check(
		'gate_staff names a draft, then a private, then a published staff single',
		array( 'draft', 'private', 'publish' ) === array_values( $gate_status ),
		'statuses=' . implode( ',', $gate_status )
	);

	$stale = (array) get_post_meta( $gate_page->ID, 'stale_ref', true );
	$check(
		'stale_ref leads with a genuinely DELETED id and follows with a live one',
		2 === count( $stale ) && null === get_post( (int) $stale[0] ) && null !== get_post( (int) ( $stale[1] ?? 0 ) ),
		'stale_ref=' . implode( ',', $stale )
	);

	$draft_ref = (array) get_post_meta( $gate_page->ID, 'via_draft', true );
	$draft_id  = (int) ( $draft_ref[0] ?? 0 );
	$check(
		'via_draft names the draft, whose own reports_to is PUBLISHED (so §F17.4 is about the stone)',
		$draft_id && 'draft' === get_post_status( $draft_id )
			&& 'publish' === get_post_status( (int) ( ( (array) get_post_meta( $draft_id, 'reports_to', true ) )[0] ?? 0 ) ),
		'via_draft=' . $draft_id
	);

	// Both arms, one ambient post. The user swap is restored before the summary.
	$prev_post       = $GLOBALS['post'] ?? null;
	$prev_user       = get_current_user_id();
	$GLOBALS['post'] = $gate_page;
	setup_postdata( $gate_page );

	$render = function ( $tag ) use ( $instance ) {
		return trim( (string) GenerateBlocks_Register_Dynamic_Tag::replace_tags( $tag, [], $instance ) );
	};
	$first_tag = '{{content src:refs,gate_staff|use:key|key:name_first}}';
	$hop_tag   = '{{content src:refs,via_draft;refs,reports_to|use:key|key:name_first}}';
	$stale_tag = '{{text src:refs,stale_ref|use:key|key:name_first|limit:1}}';
	$trash_tag = '{{text src:refs,trash_ref|use:key|key:name_first|limit:1}}';
	$att_tag   = '{{text src:refs,feature_image|use:title}}';

	// WP-CLI runs with NO current user unless --user is passed, and this script does
	// not assume one the way seed.php does — so the administrator arm has to set one
	// explicitly. Without it BOTH arms are anonymous, they agree, and the divergence
	// this section exists to assert reads as a code regression.
	$admins   = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	$admin_id = (int) ( $admins[0] ?? 0 );
	$check( 'an administrator exists to run the viewer-relative arm as', $admin_id > 0, 'user=' . $admin_id );

	// The two AUTHOR arms (v14, §F17.6/§F17.7). Both are logged in and neither can
	// read every draft, so what separates them is ownership alone — which is the
	// difference between a viewer-relative gate and a logged-in-relative one.
	$owner_user = get_user_by( 'login', 'fixture-author' );
	$owner_id   = $owner_user ? (int) $owner_user->ID : 0;
	$other_user = get_user_by( 'login', 'fixture-other-author' );
	$other_id   = $other_user ? (int) $other_user->ID : 0;
	$check( 'the gate draft is OWNED by fixture-author (an admin-authored draft cannot measure ownership)', $owner_id > 0 && $draft_id && (int) get_post_field( 'post_author', $draft_id ) === $owner_id, 'owner=' . $owner_id . ' draft_author=' . ( $draft_id ? get_post_field( 'post_author', $draft_id ) : 'n/a' ) );
	$check( 'a SECOND author-role user exists for the negative arm', $other_id > 0 && in_array( 'author', (array) ( $other_user->roles ?? array() ), true ), 'user=' . $other_id );

	$trash_ref = (array) get_post_meta( $gate_page->ID, 'trash_ref', true );
	$check(
		'trash_ref leads with a TRASHED post and follows with a live one',
		2 === count( $trash_ref ) && 'trash' === get_post_status( (int) $trash_ref[0] ) && 'publish' === get_post_status( (int) ( $trash_ref[1] ?? 0 ) ),
		'statuses=' . get_post_status( (int) ( $trash_ref[0] ?? 0 ) ) . ',' . get_post_status( (int) ( $trash_ref[1] ?? 0 ) )
	);
	$att_id = (int) get_post_meta( $gate_page->ID, 'feature_image', true );
	$check(
		'feature_image on the gate page names an ATTACHMENT whose raw status is the internal `inherit`',
		$att_id && 'attachment' === get_post_type( $att_id ) && 'inherit' === get_post_field( 'post_status', $att_id ),
		'att=' . $att_id . ' raw=' . ( $att_id ? get_post_field( 'post_status', $att_id ) : 'n/a' )
	);

	wp_set_current_user( $admin_id );
	$admin_first = $render( $first_tag );
	$admin_hop   = $render( $hop_tag );
	$admin_stale = $render( $stale_tag );
	$admin_trash = $render( $trash_tag );
	$admin_att   = $render( $att_tag );

	wp_set_current_user( $owner_id );
	$owner_first = $render( $first_tag );

	wp_set_current_user( $other_id );
	$other_first = $render( $first_tag );

	wp_set_current_user( 0 );
	$anon_first = $render( $first_tag );
	$anon_hop   = $render( $hop_tag );
	$anon_stale = $render( $stale_tag );
	$anon_trash = $render( $trash_tag );
	$anon_att   = $render( $att_tag );

	wp_set_current_user( $prev_user );
	$GLOBALS['post'] = $prev_post;
	if ( $prev_post ) {
		setup_postdata( $prev_post );
	}

	$check( 'F17.1 anonymous reads the first VISIBLE source, past the draft and the private one', 'Grace' === $anon_first, 'out=' . var_export( $anon_first, true ) );
	$check( 'F17.2 an administrator reads the DRAFT — the visible level is viewer-relative', 'Dana' === $admin_first, 'out=' . var_export( $admin_first, true ) );
	$check( 'F17.4 the chain through the draft is CUT for a visitor', '' === $anon_hop, 'out=' . var_export( $anon_hop, true ) );
	$check( 'F17.5 the same chain resolves for a viewer who may read the stone (so F17.4 is not a missing hop)', 'Grace' === $admin_hop, 'out=' . var_export( $admin_hop, true ) );
	// EXISTS is not viewer-relative, so this pair must AGREE. A divergence here means
	// the deleted id started being answered by a capability check.
	$check( 'F17.3 a deleted id fails for BOTH viewers and spends no limit budget', 'Grace' === $anon_stale && $anon_stale === $admin_stale, 'anon=' . var_export( $anon_stale, true ) . ' admin=' . var_export( $admin_stale, true ) );
	$check( 'F17.6 the draft OWNER reads their own draft', 'Dana' === $owner_first, 'out=' . var_export( $owner_first, true ) );
	// The pair is the assertion, not either half: 'Grace' alone would also be
	// printed by a gate that refused every logged-in non-admin, and 'Dana' alone by
	// one that resolved any draft for anyone signed in.
	$check( 'F17.7 a DIFFERENT author, equally logged in, does not', 'Grace' === $other_first, 'out=' . var_export( $other_first, true ) );
	// Trash is the one status where EXISTS passes and VISIBLE fails for everyone, so
	// this pair must AGREE while F17.1/F17.2 diverge on the same page.
	$check( 'F17.8 a TRASHED source is refused for both viewers and spends no limit budget', 'Grace' === $anon_trash && $anon_trash === $admin_trash, 'anon=' . var_export( $anon_trash, true ) . ' admin=' . var_export( $admin_trash, true ) );
	$check( 'F17.9 an ATTACHMENT source resolves for a visitor (its `inherit` is the PARENT status, not an internal refusal)', 'Fixture Photo' === $anon_att && $anon_att === $admin_att, 'anon=' . var_export( $anon_att, true ) . ' admin=' . var_export( $admin_att, true ) );
}

echo $fail ? "\nVERIFY FAILED ({$fail})\n" : "\nVERIFY PASSED\n";
exit( $fail ? 1 : 0 );
