<?php
/**
 * tags-core blueprint — GB block markup generator.
 *
 * Builds GenerateBlocks page content for the matrix pages from tag strings.
 * Four shapes cover every flat matrix row (reference corpus:
 * tools/debug/matrix-page-blocks.html):
 *
 *  1. section wrapper — generateblocks/element div + wp:heading
 *  2. text row       — generateblocks/text p, tag string in body
 *  3. media row      — tag string duplicated in comment-JSON htmlAttributes.src
 *                      AND the rendered <img src>; the two copies MUST match,
 *                      and the per-block css string is keyed to the uniqueId
 *  4. query/looper   — query → looper → loop-item → text; fixed skeleton
 *
 * uniqueId is 8 hex chars, any unique value — derived deterministically from
 * content so reseeding is diff-stable.
 *
 * Complex styled/structural surfaces are OUT of scope — those stay
 * hand-build + snapshot (fixture-testbed plan, block-generation pin).
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	exit;
}

/** Deterministic 8-hex uniqueId. */
function bws_fixture_gb_uid( $seed ) {
	return substr( md5( 'bwsfx:' . $seed ), 0, 8 );
}

/** Shape 2 — text row. Body may be a tag string, label text, or both. */
function bws_fixture_gb_text_block( $body, $seed = '' ) {
	$uid = bws_fixture_gb_uid( $seed !== '' ? $seed : $body );
	return sprintf(
		"<!-- wp:generateblocks/text {\"uniqueId\":\"%s\",\"tagName\":\"p\",\"className\":\"\"} -->\n<p class=\"gb-text\">%s</p>\n<!-- /wp:generateblocks/text -->",
		$uid,
		$body
	);
}

/** Shape 3 — media row. Tag string duplicated in JSON attrs and rendered HTML. */
function bws_fixture_gb_media_block( $tag, $seed = '' ) {
	$uid  = bws_fixture_gb_uid( 'media:' . ( $seed !== '' ? $seed : $tag ) );
	$json = json_encode(
		array(
			'uniqueId'       => $uid,
			'tagName'        => 'img',
			'styles'         => array(
				'height'    => 'auto',
				'maxWidth'  => '100%',
				'objectFit' => 'cover',
				'width'     => 'auto',
			),
			'css'            => ".gb-media-{$uid}{height:auto;max-width:100%;object-fit:cover;width:auto}",
			'htmlAttributes' => array(
				'alt' => '',
				'src' => $tag,
			),
			'className'      => '',
		)
	);
	return sprintf(
		"<!-- wp:generateblocks/media %s -->\n<img class=\"gb-media-%s\" alt=\"\" src=\"%s\"/>\n<!-- /wp:generateblocks/media -->",
		$json,
		$uid,
		$tag
	);
}

/** Shape 4 — query/looper nest around one inner tag string. */
function bws_fixture_gb_query_loop( array $query, $inner_tag, $seed ) {
	$q_uid  = bws_fixture_gb_uid( 'query:' . $seed );
	$l_uid  = bws_fixture_gb_uid( 'looper:' . $seed );
	$i_uid  = bws_fixture_gb_uid( 'item:' . $seed );
	$q_json = json_encode(
		array(
			'uniqueId'  => $q_uid,
			'tagName'   => 'div',
			'queryType' => 'post_meta',
			'query'     => $query,
			'className' => '',
		)
	);
	$inner = bws_fixture_gb_text_block( $inner_tag, 'loop-inner:' . $seed );
	return "<!-- wp:generateblocks/query {$q_json} -->\n<div>"
		. "<!-- wp:generateblocks/looper {\"uniqueId\":\"{$l_uid}\",\"tagName\":\"ol\",\"className\":\"\"} -->\n<ol>"
		. "<!-- wp:generateblocks/loop-item {\"uniqueId\":\"{$i_uid}\",\"tagName\":\"li\",\"className\":\"\"} -->\n"
		. "<li class=\"gb-loop-item\">{$inner}</li>\n"
		. "<!-- /wp:generateblocks/loop-item --></ol>\n"
		. "<!-- /wp:generateblocks/looper --></div>\n"
		. "<!-- /wp:generateblocks/query -->";
}

/** Shape 1 — section wrapper. $rows = array of already-built block strings. */
function bws_fixture_gb_section( $title, array $rows ) {
	$uid     = bws_fixture_gb_uid( 'section:' . $title );
	$heading = "<!-- wp:heading {\"className\":\"\"} -->\n<h2 class=\"wp-block-heading\">{$title}</h2>\n<!-- /wp:heading -->";
	$inner   = $heading . "\n\n" . implode( "\n\n", $rows );
	return "<!-- wp:generateblocks/element {\"uniqueId\":\"{$uid}\",\"tagName\":\"div\",\"className\":\"\"} -->\n<div>{$inner}</div>\n<!-- /wp:generateblocks/element -->";
}

/** Labelled matrix row: "R0.1: " prefix + tag, so front-end output maps to matrix rows. */
function bws_fixture_gb_row( $label, $tag ) {
	return bws_fixture_gb_text_block( $label . ': ' . $tag, $label . $tag );
}

/**
 * Page content: phone-matrix (page-phone-matrix).
 * One row per phone matrix R-row that reads THIS page's meta. Settings-dependent
 * rows (global CC / strip toggles) render the same tag; the matrix says which
 * setting state to view under.
 */
function bws_fixture_page_content_phone_matrix() {
	$sections = array();

	$sections[] = bws_fixture_gb_section( 'R0 - href rebuild', array(
		bws_fixture_gb_row( 'R0.1', '{{phone key:main_line}}' ),
		bws_fixture_gb_row( 'R0.2', '{{phone key:booking_line}}' ),
		bws_fixture_gb_row( 'R0.3', '{{phone key:after_hours_line}}' ),
		bws_fixture_gb_row( 'R0.4', '{{phone key:sms_number}}' ),
		bws_fixture_gb_row( 'R0.5', '{{phone key:intl_desk}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'R1 - country code + trunk-0', array(
		bws_fixture_gb_row( 'R1.1', '{{phone key:us_toll_free}}' ),
		bws_fixture_gb_row( 'R1.2', '{{phone key:intl_exchange}}' ),
		bws_fixture_gb_row( 'R1.3/R1.4', '{{phone key:uk_mobile}}' ),
		bws_fixture_gb_row( 'R1.5', '{{phone key:sms_number}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'R2 - separated-CC dedupe', array(
		bws_fixture_gb_row( 'R2.1/R2.2/R2.6', '{{phone key:support_tollfree}}' ),
		bws_fixture_gb_row( 'R2.3', '{{phone key:sales_tollfree}}' ),
		bws_fixture_gb_row( 'R2.4', '{{phone key:fax_tollfree}}' ),
		bws_fixture_gb_row( 'R2.5', '{{phone key:intl_support}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'R2b - flat leading CC', array(
		bws_fixture_gb_row( 'R2b.1/R2b.2/R2b.4', '{{phone key:flat_tollfree}}' ),
		bws_fixture_gb_row( 'R2b.3', '{{phone key:flat_local}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'R3 - noLink / list / fallback', array(
		bws_fixture_gb_row( 'R3.1', '{{phone key:uk_mobile|noLink}}' ),
		bws_fixture_gb_row( 'R3.2', '{{phone srcTermIn:department|key:phone|limit:5}}' ),
		bws_fixture_gb_row( 'R3.5', '{{phone key:unused_line}}' ),
		bws_fixture_gb_row( 'R3.6', '{{phone key:short_code}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'R4 - extension + sources', array(
		bws_fixture_gb_row( 'R4.1', '{{phone key:front_desk_ext}}' ),
		bws_fixture_gb_row( 'R4.2', '{{phone src:site|key:org_phone}}' ),
		bws_fixture_gb_row( 'R4.3', '{{phone src:current|key:main_line}}' ),
		bws_fixture_gb_row( 'R4.4', '{{phone src:ref|ref:related_staff|key:main_line}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'R6 - security', array(
		bws_fixture_gb_row( 'R6.1', '{{phone key:hacked_line}}' ),
	) );

	return implode( "\n\n", $sections );
}

/**
 * Page content: term-hop pages (page-phone-mixed-terms / page-phone-junk-terms).
 * Same tags; the page's assigned terms make the case (R3.3 mixed / R3.4 all-junk).
 */
function bws_fixture_page_content_phone_term_hop() {
	return bws_fixture_gb_section( 'Term hop (R3.2-R3.4)', array(
		bws_fixture_gb_row( 'no fallback', '{{phone srcTermIn:department|key:phone|limit:5}}' ),
		bws_fixture_gb_row( 'with fallback', '{{phone srcTermIn:department|key:phone|limit:5|fallback:555-123-4567}}' ),
	) );
}

/** Dispatcher: manifest content_builder name → page content. */
function bws_fixture_build_page_content( $builder ) {
	$map = array(
		'phone_matrix'   => 'bws_fixture_page_content_phone_matrix',
		'phone_term_hop' => 'bws_fixture_page_content_phone_term_hop',
	);
	if ( ! isset( $map[ $builder ] ) ) {
		return '';
	}
	return call_user_func( $map[ $builder ] );
}
