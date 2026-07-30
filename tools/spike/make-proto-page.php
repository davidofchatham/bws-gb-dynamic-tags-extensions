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
	$body = $label . ': ' . $tag;
	return sprintf(
		"<!-- wp:generateblocks/text {\"uniqueId\":\"%s\",\"tagName\":\"p\",\"className\":\"\"} -->\n<p class=\"gb-text\">%s</p>\n<!-- /wp:generateblocks/text -->",
		$uid( $label . $tag ),
		$body
	);
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
$sections[] = $section( 'PF-P — suspect-2 A/B: markup+non-ASCII vs plain ASCII', array(
	$row( 'PFa1 [markup: 3 slots, <code> + non-ASCII glyphs]', '{{proto_fold 1:key(staff_name)|2:src(refs,office);use(same)|3:src(same);key(city)}}' ),
	$row( 'PFa2 [PLAIN: identical wire, ASCII only, no wrapper]', '{{proto_fold 1:key(staff_name)|2:src(refs,office);use(same)|3:src(same);key(city)|plain:1}}' ),
	$row( 'PFb1 [markup: malformed → non-ASCII warn glyph]', '{{proto_fold 1:src(refs,office)+use(same)}}' ),
	$row( 'PFb2 [PLAIN: same malformed wire, ASCII warn]', '{{proto_fold 1:src(refs,office)+use(same)|plain:1}}' ),
) );
$sections[] = $section( 'PF-C — editor playground (insert/edit proto_fold blocks here)', array(
	$row( 'PF9 [start empty: open editor, add a Text block, insert Proto Fold tag]', '-' ),
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
