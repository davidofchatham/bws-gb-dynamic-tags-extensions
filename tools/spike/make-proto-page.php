<?php
/**
 * SPIKE B — create/update the throwaway "Proto Fold Lab" page on the testbed.
 *
 * Run: bash bin/wp.sh testbed eval-file /plugins/bws-gb-dynamic-tags-extensions/tools/spike/make-proto-page.php
 *
 * DELIBERATELY NOT a core-structures blueprint page: the spike is throwaway, so
 * its page must not accrete into the shipped fixture defs or the matrix pages
 * (the 2026-07-29 lockup happened while editing Matrix: Post Meta — the lab page
 * isolates proto experiments from the real matrix rows). Delete the page +
 * this script when the spike concludes. Markup mirrors the blueprint's
 * bws_fixture_gb_text_block/_section shapes (uid = md5 fragment).
 *
 * Row labels state the EXPECTED behavior (house rule: self-describing rows).
 */

$uid = static function ( $seed ) {
	return substr( md5( 'bwsproto:' . $seed ), 0, 8 );
};
$row = static function ( $label, $tag ) use ( $uid ) {
	// ESCAPE THE LABEL, never the tag. A label containing markup-looking text
	// (e.g. "<code>") lands raw in the saved block HTML; Gutenberg reparses it,
	// sees an unclosed element inside the <p>, and flags "Block contains
	// unexpected or invalid content" (hit 2026-07-29, PFa1). The tag string must
	// stay literal so GB's dynamic-tag parser still sees `{{...}}`.
	$body = esc_html( $label ) . ': ' . $tag;
	return sprintf(
		"<!-- wp:generateblocks/text {\"uniqueId\":\"%s\",\"tagName\":\"p\",\"className\":\"\"} -->\n<p class=\"gb-text\">%s</p>\n<!-- /wp:generateblocks/text -->",
		$uid( $label . $tag ),
		$body
	);
};
// Shape 2b (blueprint precedent, blocks.php:43-58): a tag whose OUTPUT is markup
// must NOT sit in a <p> — Gutenberg reparses the saved block and flags "invalid
// content". Host it in a generateblocks/TEXT block with tagName:div (still a
// dynamic-tag-resolving block; generateblocks/element would NOT resolve tags).
$row_host = static function ( $label, $tag ) use ( $uid ) {
	$lbl = sprintf(
		"<!-- wp:generateblocks/text {\"uniqueId\":\"%s\",\"tagName\":\"p\",\"className\":\"\"} -->\n<p class=\"gb-text\">%s:</p>\n<!-- /wp:generateblocks/text -->",
		$uid( 'hostlabel:' . $label ),
		esc_html( $label )
	);
	$body = sprintf(
		"<!-- wp:generateblocks/text {\"uniqueId\":\"%s\",\"tagName\":\"div\",\"className\":\"\"} -->\n<div class=\"gb-text\">%s</div>\n<!-- /wp:generateblocks/text -->",
		$uid( 'host:' . $label . $tag ),
		$tag
	);
	return $lbl . "\n\n" . $body;
};
$section = static function ( $title, array $rows ) use ( $uid ) {
	$heading = "<!-- wp:heading {\"className\":\"\"} -->\n<h2 class=\"wp-block-heading\">{$title}</h2>\n<!-- /wp:heading -->";
	$inner   = $heading . "\n\n" . implode( "\n\n", $rows );
	return "<!-- wp:generateblocks/element {\"uniqueId\":\"{$uid('section:' . $title)}\",\"tagName\":\"div\",\"className\":\"\"} -->\n<div>{$inner}</div>\n<!-- /wp:generateblocks/element -->";
};

$sections   = array();

// SUSPECT-2 PROBE LAYOUT (lockup triage 2026-07-29). Deliberately MINIMAL — the
// earlier 9-row page kept the 16-child LSAPI pool saturated, which masked the
// signal. Each pair renders the SAME wire twice: markup+non-ASCII vs `plain`
// (ASCII, no wrapper). If only the non-plain rows wedge GB's preview response
// path, the output is implicated; if both wedge equally, it is pool capacity.
// MARKUP rows use the div host (Shape 2b) — their <code> output would otherwise
// invalidate a <p> block for a reason unrelated to the probe. PLAIN rows return
// bare text, so a <p> row is correct for them (and shows the contrast).
$sections[] = $section( 'PF-P — suspect-2 A/B: markup+non-ASCII (div host) vs plain ASCII (p row)', array(
	$row_host( 'PFa1 [markup: 3 slots, code-wrapped + non-ASCII glyphs, div host]', '{{proto_fold 1:key(staff_name)|2:src(refs,office);use(same)|3:src(same);key(city)}}' ),
	$row( 'PFa2 [PLAIN: identical wire, ASCII only, no wrapper]', '{{proto_fold 1:key(staff_name)|2:src(refs,office);use(same)|3:src(same);key(city)|plain:1}}' ),
	$row_host( 'PFb1 [markup: malformed → non-ASCII warn glyph, div host]', '{{proto_fold 1:src(refs,office)+use(same)}}' ),
	$row( 'PFb2 [PLAIN: same malformed wire, ASCII warn]', '{{proto_fold 1:src(refs,office)+use(same)|plain:1}}' ),
) );
// MULTI-HOP rows (2026-07-30): saved multi-step src tokens survived the wire but
// had no controls — the single-hop control silently TRUNCATED hops 2+ on any
// source edit. These rows exercise the per-step controls (edit/remove/append).
$sections[] = $section( 'PF-M — multi-hop chains (per-step controls)', array(
	$row_host( 'PFm1 [2-hop: office->region, edit step 1 must KEEP step 2]', '{{proto_fold 1:src(refs,office+refs,region);key(name)}}' ),
	$row_host( 'PFm2 [3-hop + start-kind + limit: post,9999 -> refs,5 -> refs]', '{{proto_fold 1:src(post,9999+refs,related_staff,5+refs,office);use(title)}}' ),
	$row_host( 'PFm3 [slot 2 multi-hop w/ inherit read (Path A)]', '{{proto_fold 1:key(staff_name)|2:src(refs,office+refs,region);use(same)}}' ),
) );
// REPEATER rows (2026-07-30): add/remove slot cardinality replaced the show_if
// reveal chain + intent radio. The interesting cases are all in the EDITOR — open
// each block, use Remove/Add, and read the wire echo under each slot. The front
// end shows what the wire currently resolves to (and flags malformed shapes).
$sections[] = $section( 'PF-R — repeater: add / out-of-order remove with compaction', array(
	$row_host( 'PFr1 [4 slots, all distinct: remove slot 2 -> expect 3 slots, old 3+4 slide to 2+3, NO hole]',
		'{{proto_fold 1:key(staff_name)|2:src(refs,office);key(city)|3:src(refs,region);key(name)|4:src(post,9999);use(title)}}' ),
	$row_host( 'PFr2 [MATERIALIZATION: slot 3 inherits src from slot 2 — removing slot 2 must make slot 3 state refs,office explicitly, not re-point same at slot 1]',
		'{{proto_fold 1:key(staff_name)|2:src(refs,office);key(city)|3:src(same);key(region_name)}}' ),
	$row_host( 'PFr3 [PROMOTION: slot 2 inherits BOTH axes — removing slot 1 promotes it to position 1, where same is illegal; expect both axes materialized from old slot 1]',
		'{{proto_fold 1:src(refs,office);key(city)|2:src(same);use(same)}}' ),
	$row_host( 'PFr4 [SEED shape: what Add slot writes — inherits both axes, resolves same as slot 1, advisory should say so]',
		'{{proto_fold 1:key(staff_name)|2:src(same);use(same)}}' ),
	$row_host( 'PFr5 [MALFORMED: slot 2 has no src token (the old unset-means-current shape) — must FLAG, not silently resolve to ambient]',
		'{{proto_fold 1:key(staff_name)|2:key(city)}}' ),
	$row_host( 'PFr6 [FLOOR: single serialized slot still renders 2 slots in editor; slot 2 empty until Add/configure]',
		'{{proto_fold 1:key(staff_name)}}' ),
	$row_host( 'PFr7 [INCOMPLETE HOP: refs with no reference field — must FLAG, not render a bare refs that looks configured]',
		'{{proto_fold 1:key(staff_name)|2:src(refs);use(same)}}' ),
	$row_host( 'PFr8 [INCOMPLETE HOP mid-chain: hop 1 complete, hop 2 argless — flag must attach to hop 2 only]',
		'{{proto_fold 1:key(staff_name)|2:src(refs,office+refs);key(city)}}' ),
) );
$sections[] = $section( 'PF-C — editor playground (insert/edit proto_fold blocks here)', array(
	$row( 'PF9 [start empty: open editor, add a Text block, insert Proto Fold tag; expect 2 slots + Add slot]', '-' ),
) );

$content = implode( "\n\n", $sections );

$existing = get_page_by_path( 'proto-fold-lab' );
$postarr  = array(
	'post_title'   => 'Proto Fold Lab (SPIKE — throwaway)',
	'post_name'    => 'proto-fold-lab',
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_content' => $content,
);
if ( $existing ) {
	$postarr['ID'] = $existing->ID;
	$id            = wp_update_post( $postarr, true );
} else {
	$id = wp_insert_post( $postarr, true );
}
if ( is_wp_error( $id ) ) {
	WP_CLI::error( $id->get_error_message() );
}
WP_CLI::success( ( $existing ? 'Updated' : 'Created' ) . " proto-fold-lab (ID $id): " . get_permalink( $id ) );
