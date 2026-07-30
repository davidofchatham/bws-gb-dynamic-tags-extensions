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
$sections[] = $section( 'PF-A — folded wire (dump renderer echoes parsed structure)', array(
	$row( 'PF1 [slot1 key read → "key:staff_name"]', '{{proto_fold 1:key(staff_name)}}' ),
	$row( 'PF2 [3 slots: key / Path A refs+use(same) / Path B src(same)+key]', '{{proto_fold 1:key(staff_name)|2:src(refs,office);use(same)|3:src(same);key(city)}}' ),
	$row( 'PF3 [Option-R type token → "type: title"]', '{{proto_fold 1:title;key(x)}}' ),
	$row( 'PF4 [malformed close-then-reopen → ⚠ flagged, not mis-parsed]', '{{proto_fold 1:src(refs,office)+use(same)}}' ),
	$row( 'PF5 [lenient separators ,/[] → same parse as PF2 slot2-3]', '{{proto_fold 1:key[staff_name]|2:src(refs;office),use[same]}}' ),
) );
$sections[] = $section( 'PF-B — legacy recovery (mount-reconcile + dual-read)', array(
	$row( 'PF6 [legacy wire → both slots "(recovered from legacy)"]', '{{proto_fold key:staff_name|2-src:ref|2-ref:office|2-use:title}}' ),
	$row( 'PF7 [FW-51 shape → slot 2 "⚑ needs author review", never guessed]', '{{proto_fold key:a|2-key:x}}' ),
	$row( 'PF8 [mixed: legacy slots 1-2 + folded slot 3 coexist]', '{{proto_fold key:a|2-src:ref|2-ref:office|3:src(same);key(city)}}' ),
) );
$sections[] = $section( 'PF-C — editor playground (insert/edit proto_fold blocks here)', array(
	$row( 'PF9 [start empty: open editor, add a Text block, insert Proto Fold tag]', '—' ),
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
