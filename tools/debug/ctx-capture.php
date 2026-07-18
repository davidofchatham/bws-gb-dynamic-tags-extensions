<?php
/**
 * Context payload capture — P6/P7 rows of tools/debug/bws-ctx-probe-matrix.md.
 * Run: bin/wp.sh testbed eval-file /plugins/bws-gb-dynamic-tags-extensions/tools/debug/ctx-capture.php --url=<url>
 * Temporary; delete with the probe matrix when the sweep distills into the plans.
 */
wp(); // --url only sets $_SERVER; run the real main query (same mechanism as class-render-tag-command.php).

$qo = get_queried_object();
$qv = $GLOBALS['wp_query']->query_vars;
$out = array(
	'cond' => array(
		'date'   => is_date(),
		'year'   => is_year(),
		'month'  => is_month(),
		'pta'    => is_post_type_archive(),
		'author' => is_author(),
		'search' => is_search(),
		'404'    => is_404(),
		'home'   => is_home(),
		'front'  => is_front_page(),
	),
	'qo_class' => $qo === null ? null : get_class( $qo ),
	'qo'       => $qo === null ? null : (array) $qo,
	'qid'      => get_queried_object_id(),
	'qv'       => array_intersect_key( $qv, array_flip( array( 'year', 'monthnum', 'day', 'm', 'post_type', 'author', 'author_name', 's' ) ) ),
	'post_global' => ( isset( $GLOBALS['post'] ) && $GLOBALS['post'] ) ? array( $GLOBALS['post']->ID, $GLOBALS['post']->post_type ) : null,
);
echo json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
