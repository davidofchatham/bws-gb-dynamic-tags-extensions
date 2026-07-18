<?php
/**
 * core-structures blueprint — GB block markup generator.
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
 * Page content: matrix-post-meta (page-matrix-post-meta).
 * Split axis is SOURCE-STATE: this page = explicit reads off the current post
 * (+ src:site, src:ref). One section group per tag family — phone now; other
 * families append their sections here as they accrete (Deliverable B).
 * Settings-dependent rows (global CC / strip toggles) render the same tag; the
 * matrix says which setting state to view under.
 */
function bws_fixture_page_content_matrix_post_meta() {
	$sections = array();

	$sections[] = bws_fixture_gb_section( 'Phone R0 - href rebuild', array(
		bws_fixture_gb_row( 'R0.1', '{{phone key:main_line}}' ),
		bws_fixture_gb_row( 'R0.2', '{{phone key:booking_line}}' ),
		bws_fixture_gb_row( 'R0.3', '{{phone key:after_hours_line}}' ),
		bws_fixture_gb_row( 'R0.4', '{{phone key:sms_number}}' ),
		bws_fixture_gb_row( 'R0.5', '{{phone key:intl_desk}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Phone R1 - country code + trunk-0', array(
		bws_fixture_gb_row( 'R1.1', '{{phone key:us_toll_free}}' ),
		bws_fixture_gb_row( 'R1.2', '{{phone key:intl_exchange}}' ),
		bws_fixture_gb_row( 'R1.3/R1.4', '{{phone key:uk_mobile}}' ),
		bws_fixture_gb_row( 'R1.5', '{{phone key:sms_number}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Phone R2 - separated-CC dedupe', array(
		bws_fixture_gb_row( 'R2.1/R2.2/R2.6', '{{phone key:support_tollfree}}' ),
		bws_fixture_gb_row( 'R2.3', '{{phone key:sales_tollfree}}' ),
		bws_fixture_gb_row( 'R2.4', '{{phone key:fax_tollfree}}' ),
		bws_fixture_gb_row( 'R2.5', '{{phone key:intl_support}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Phone R2b - flat leading CC', array(
		bws_fixture_gb_row( 'R2b.1/R2b.2/R2b.4', '{{phone key:flat_tollfree}}' ),
		bws_fixture_gb_row( 'R2b.3', '{{phone key:flat_local}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Phone R3 - noLink / list / fallback', array(
		bws_fixture_gb_row( 'R3.1', '{{phone key:uk_mobile|noLink}}' ),
		bws_fixture_gb_row( 'R3.2', '{{phone srcTermIn:department|key:phone|limit:5}}' ),
		bws_fixture_gb_row( 'R3.5', '{{phone key:unused_line}}' ),
		bws_fixture_gb_row( 'R3.6', '{{phone key:short_code}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Phone R4 - extension + sources', array(
		bws_fixture_gb_row( 'R4.1', '{{phone key:front_desk_ext}}' ),
		bws_fixture_gb_row( 'R4.2', '{{phone src:site|key:org_phone}}' ),
		bws_fixture_gb_row( 'R4.3', '{{phone src:current|key:main_line}}' ),
		bws_fixture_gb_row( 'R4.4', '{{phone src:ref|ref:related_staff|key:main_line}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Phone R6 - security', array(
		bws_fixture_gb_row( 'R6.1', '{{phone key:hacked_line}}' ),
	) );

	// text read-seam matrix (text-test-matrix.md). Standing rendered rows for
	// bws_base_text_resolve_value + the shell wrap gate. Term-hop rows (T4) live
	// on the term-archive pages; the src:ref target order (jane first) is pinned
	// by the manifest.
	$sections[] = bws_fixture_gb_section( 'Text T1 - post arm + wrap gate', array(
		bws_fixture_gb_row( 'T1.1', '{{text key:main_line}}' ),
		bws_fixture_gb_row( 'T1.2', '{{text key:main_line|linkTo:permalink}}' ),
		bws_fixture_gb_row( 'T1.3', '{{text use:title|linkTo:permalink|newTab}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Text T3 - srcTermIn list: multi never wraps', array(
		bws_fixture_gb_row( 'T3.1', '{{text srcTermIn:department|use:title|limit:2}}' ),
		bws_fixture_gb_row( 'T3.2', '{{text srcTermIn:department|use:title|limit:2|linkTo:permalink}}' ),
		bws_fixture_gb_row( 'T3.3', '{{text srcTermIn:department|use:title|limit:1|linkTo:permalink}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Text T5 - zero preservation', array(
		bws_fixture_gb_row( 'T5.1', '{{text key:bws_zero_probe}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Text T7 - src:ref list mode', array(
		bws_fixture_gb_row( 'T7.1', '{{text src:ref|ref:related_staff|use:title}}' ),
		bws_fixture_gb_row( 'T7.2', '{{text src:ref|ref:related_staff|use:title|limit:5}}' ),
		bws_fixture_gb_row( 'T7.3', '{{text src:ref|ref:related_staff|use:title|limit:5|linkTo:permalink}}' ),
		bws_fixture_gb_row( 'T7.4', '{{text src:ref|ref:related_staff|use:title|linkTo:permalink}}' ),
	) );

	// join matrix (join-test-matrix.md) — the POST-ARM rows (height / role /
	// absorb: src:same, src:ref, src:site, srcTermIn limit). Name rows resolve
	// on the staff singles (staff_join builder), NOT here. J23/J24 stay in the
	// pure harness (no per-field blanking on a page). J20 (reveal) is visible
	// by opening any join block below in the editor.
	$sections[] = bws_fixture_gb_section( 'Join - separator / zero', array(
		bws_fixture_gb_row( 'J4', '{{join key:height_in_zero|2-key:role}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Join - unit suffix (height)', array(
		bws_fixture_gb_row( 'J11', '{{join mode:template|format:%1\'%2"|key:height_ft|2-key:height_in}}' ),
		bws_fixture_gb_row( 'J11b', '{{join mode:template|format:%1′%2″|key:height_ft|2-key:height_in}}' ),
		bws_fixture_gb_row( 'J12', '{{join mode:template|format:%1\'%2"|key:height_ft|2-key:height_in_blank}}' ),
		bws_fixture_gb_row( 'J13', '{{join mode:template|format:%1\'%2"|key:name_generation|2-key:height_in_blank|fallback_text:—}}' ),
		bws_fixture_gb_row( 'J14', '{{join mode:template|format:%1\'%2"|key:height_ft|2-key:height_in_zero}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Join - per-slot src / use / site / list (absorb)', array(
		bws_fixture_gb_row( 'J15', '{{join use:title|2-use:key|2-key:role|sep: / }}' ),
		bws_fixture_gb_row( 'J16', '{{join key:main_line|2-src:same|2-key:booking_line}}' ),
		bws_fixture_gb_row( 'J16b', '{{join src:ref|ref:related_staff|use:key|key:main_line|2-src:same|2-key:contact_email}}' ),
		bws_fixture_gb_row( 'J17', '{{join key:name_first|2-src:ref|2-ref:related_staff|2-use:title}}' ),
		bws_fixture_gb_row( 'J18', '{{join key:name_first|2-src:site|2-key:organization_email}}' ),
		bws_fixture_gb_row( 'J19', '{{join srcTermIn:department|use:title|limit:2}}' ),
	) );

	return implode( "\n\n", $sections );
}

/**
 * Page content: staff singles (staff-jane-partner / staff-tom-associate).
 * join NAME rows — same tag strings on both; the FIXTURE data makes the case
 * (tom dense / jane sparse), so one builder serves both staff. These resolve
 * off the current staff post (name_* fields live on the Staff Contact group).
 */
function bws_fixture_page_content_staff_join() {
	$sections = array();

	$sections[] = bws_fixture_gb_section( 'Join - separator mode (name)', array(
		bws_fixture_gb_row( 'J1', '{{join key:name_first|2-key:name_last}}' ),
		bws_fixture_gb_row( 'J1b', '{{join key:name_first|2-key:name_last|sep: }}' ),
		bws_fixture_gb_row( 'J2', '{{join key:name_first|2-key:name_generation|3-key:name_last}}' ),
		bws_fixture_gb_row( 'J3', '{{join key:name_generation|2-key:name_credential|fallback_text:—}}' ),
		bws_fixture_gb_row( 'J3b', '{{join key:name_generation|2-key:name_credential}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Join - template mode (name)', array(
		bws_fixture_gb_row( 'J5', '{{join mode:template|format:%1 (%2)|key:name_first|2-key:name_last}}' ),
		bws_fixture_gb_row( 'J6', '{{join mode:template|format:%1 (%2)|key:name_first|2-key:name_generation}}' ),
		bws_fixture_gb_row( 'J7', '{{join mode:template|format:%1 · %2|key:name_generation|2-key:name_last}}' ),
		bws_fixture_gb_row( 'J8', '{{join mode:template|format:%1 (%2.)|key:name_first|2-key:name_generation}}' ),
		bws_fixture_gb_row( 'J9', '{{join mode:template|format:%1 (%2.)|key:name_generation|2-key:name_first}}' ),
		bws_fixture_gb_row( 'J10', '{{join mode:template|format:%1 (%2)|key:name_generation|2-key:name_credential|fallback_text:—}}' ),
	) );

	// Full-name stress case — one format string, dense (tom) vs collapsed (jane).
	$sections[] = bws_fixture_gb_section( 'Join - full personal name', array(
		bws_fixture_gb_row( 'J21/J22', '{{join mode:template|format:%1 %2 %3. %4 %5, %6, %7|key:name_honorific|2-key:name_first|3-key:name_middle_initial|4-key:name_last|5-key:name_generation|6-key:name_credential|7-key:name_service}}' ),
	) );

	return implode( "\n\n", $sections );
}

/**
 * Page content: term-hop pages (page-matrix-terms-mixed / page-matrix-terms-junk).
 * Same tags; the page's assigned terms make the case (R3.3 mixed / R3.4 all-junk).
 */
function bws_fixture_page_content_matrix_term_hop() {
	return bws_fixture_gb_section( 'Phone term hop (R3.2-R3.4)', array(
		bws_fixture_gb_row( 'no fallback', '{{phone srcTermIn:department|key:phone|limit:5}}' ),
		bws_fixture_gb_row( 'with fallback', '{{phone srcTermIn:department|key:phone|limit:5|fallback:555-123-4567}}' ),
	) )
	// text srcTermIn hop, term ACF field (email) — a post-context read of its
	// terms' fields. Matrix T4 proper (BARE tag on a term ARCHIVE → term-analog
	// arm) is NOT page-embeddable — it needs the archive as ambient context;
	// run it via `render-tag --url=/department/support/` (matrix T4.1/T4.2).
	. "\n\n" . bws_fixture_gb_section( 'Text - term field via srcTermIn hop', array(
		bws_fixture_gb_row( 'text-term-hop', '{{text srcTermIn:department|key:email|limit:2}}' ),
	) );
}

/** Dispatcher: manifest content_builder name → page content. */
function bws_fixture_build_page_content( $builder ) {
	$map = array(
		'matrix_post_meta' => 'bws_fixture_page_content_matrix_post_meta',
		'matrix_term_hop'  => 'bws_fixture_page_content_matrix_term_hop',
		'staff_join'       => 'bws_fixture_page_content_staff_join',
	);
	if ( ! isset( $map[ $builder ] ) ) {
		return '';
	}
	return call_user_func( $map[ $builder ] );
}
