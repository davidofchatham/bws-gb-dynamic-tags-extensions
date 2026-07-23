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
			'queryType' => 'WP_Query',
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

	// src:site matrix (src-site-test-matrix.md) — R7 try_ site-slot rows (FW-4,
	// 1.15.0). R7.8 (WYSIWYG option), R7.9-positive (site logo) and R7.12
	// (org_email) need [SUB] state the fixture doesn't seed — matrix notes them;
	// R7.11 is editor-only (open any try_ block below, check slot src dropdowns).
	$sections[] = bws_fixture_gb_section( 'Site R7 - try_ site slots', array(
		bws_fixture_gb_row( 'R7.1', '{{try_title src:site}}' ),
		bws_fixture_gb_row( 'R7.2', '{{try_permalink src:site}}' ),
		bws_fixture_gb_row( 'R7.3', '{{try_text src:site|use:title}}' ),
		bws_fixture_gb_row( 'R7.4', '{{try_text src:site|use:key|key:blogname}}' ),
		bws_fixture_gb_row( 'R7.5', '{{try_text key:no_such_meta|2-src:site|2-use:key|2-key:blogname}}' ),
		bws_fixture_gb_row( 'R7.6', '{{try_title src:site|linkTo:permalink}}' ),
		bws_fixture_gb_row( 'R7.7', '{{try_content src:site}}' ),
		bws_fixture_gb_row( 'R7.10', '{{try_image as:url|src:site}}' ),
	) );

	// FW-52 serialization-order editor rows (fw52-order-test-matrix.md). These are
	// EDITOR-EYEBALL fixtures: the point is to OPEN each block in the GB editor and
	// read the reordered tag string (as-front, source contiguous, N- slots grouped),
	// not the front-end render. The tag strings below are intentionally authored in
	// a NON-canonical key order so the normalizer visibly re-sorts them on open.
	// Rendered output is unchanged and secondary; feature_image (matrix-post-meta)
	// backs the image reads with a real seeded attachment.
	$sections[] = bws_fixture_gb_section( 'FW-52 O1 - image as-front (open in editor)', array(
		// O1.1 media block: {{image as:url}} → real <img src>. On open the string
		// should lead with `as:url` (format-front), then src/use/key.
		bws_fixture_gb_media_block( '{{image use:key|key:feature_image|as:url}}', 'fw52-o1-1' ),
		// O1.2-O1.4 nullary return modes (text blocks — output is the raw datum).
		bws_fixture_gb_row( 'O1.2', '{{image key:feature_image|use:key|as:alt}}' ),
		bws_fixture_gb_row( 'O1.3', '{{image key:feature_image|use:key|as:id}}' ),
		bws_fixture_gb_row( 'O1.4', '{{image key:feature_image|use:key|as:caption}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'FW-52 O2 - multi-slot try_text contiguity (open in editor)', array(
		// O2.1 three slots authored scrambled: on open each slot's keys should
		// group contiguously and ascend (1- keys, then 2-, then 3-).
		bws_fixture_gb_row( 'O2.1', '{{try_text 3-use:title|key:name_first|use:key|2-src:site|2-use:key|2-key:blogname|3-src:current}}' ),
		// O2.2 reset-scatter: slot-1 key added last (globally-last in the string)
		// should rejoin its slot-1 siblings on open.
		bws_fixture_gb_row( 'O2.2', '{{try_text use:key|2-src:site|2-use:title|key:name_last}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'FW-52 O3 - datetime format-front + link (open in editor)', array(
		// O3.1 single: authored source-first + link before format; on open the
		// string should lead with the format block (as/format), then source, link,
		// fallback.
		bws_fixture_gb_row( 'O3.1', '{{datetime_single key:event_datetime|linkTo:permalink|as:date|format:F j, Y|fallback:TBA}}' ),
		// O3.2 range: format block (as/rangeSep/format) leads, start/end keys in
		// source, link after.
		bws_fixture_gb_row( 'O3.2', '{{datetime_range startKey:event_start_date|endKey:event_end_date|linkTo:permalink|as:date|rangeSep:–}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'FW-52 O4 - image as+size fold (open in editor)', array(
		// O4.1 media block: {{image as:url,medium}} → real <img src>. Authored
		// scrambled (key/use before as); on open the folded `as:url,medium` token
		// should lead (format group). In the modal, flip Return type URL->alt->URL:
		// the size dropdown hides on nullary and RESTORES `medium` back on URL.
		bws_fixture_gb_media_block( '{{image use:key|key:feature_image|as:url,medium}}', 'fw52-o4-1' ),
		// O4.2 size arg absent: composite writes the default (`full`) on open, so
		// the string should read `as:url,full` (default size always-serialized).
		bws_fixture_gb_row( 'O4.2', '{{image use:key|key:feature_image|as:url}}' ),
		// O4.3 nullary mode: NO size sub-slot (bare `as:alt`); size dropdown hidden
		// in the modal. String must carry no interior `,,`.
		bws_fixture_gb_row( 'O4.3', '{{image use:key|key:feature_image|as:alt}}' ),
		// O4.4 migration round-trip: LEGACY split wire (`size:` separate) — on open
		// the transform folds it into `as:url,medium`; orphan `size:` token gone.
		bws_fixture_gb_row( 'O4.4', '{{image as:url|size:medium|use:key|key:feature_image}}' ),
		// O4.5 migration: legacy `size:` on a nullary mode is DROPPED (was dead at
		// render) — on open the string is a bare `as:alt`, no size token.
		bws_fixture_gb_row( 'O4.5', '{{image as:alt|size:large|key:feature_image|use:key}}' ),
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

	// J11c/J11d — NEGATIVE control for the wptexturize surface. Same two formats
	// as J11/J11b but inside a query-loop item, to pin that being loop-generated
	// does NOT exempt a row from wptexturize: do_blocks runs on the_content at
	// priority 9 and wptexturize at 10, so rows are already inline in the string
	// when texturize sweeps it. EXPECT J11c === J11 (both `5’11”`) — an inequality
	// here means the ordering assumption broke. The real exempt path is a GP
	// Element (no the_content at all), which a page fixture cannot reach; see the
	// matrix note. Loop is over this page itself so the height meta is in row
	// scope and placement is the only variable vs J11.
	$sections[] = bws_fixture_gb_section( 'Join - unit suffix in a query loop (texturize control)', array(
		bws_fixture_gb_query_loop(
			array(
				'post_type'      => 'page',
				'post_name__in'  => array( 'matrix-post-meta' ),
				'posts_per_page' => 1,
			),
			'J11c: {{join mode:template|format:%1\'%2"|key:height_ft|2-key:height_in}}',
			'j11c-loop-straight'
		),
		bws_fixture_gb_query_loop(
			array(
				'post_type'      => 'page',
				'post_name__in'  => array( 'matrix-post-meta' ),
				'posts_per_page' => 1,
			),
			'J11d: {{join mode:template|format:%1′%2″|key:height_ft|2-key:height_in}}',
			'j11d-loop-prime'
		),
	) );

	// ~…~ unit groups (Step 0, 1.15.0) — group + separator shed vs unwrap vs
	// literal tilde. Wire round-trip surface: ~ rides the GB tag string raw.
	$sections[] = bws_fixture_gb_section( 'Join - unit groups (~…~)', array(
		bws_fixture_gb_row( 'J25', '{{join mode:template|format:%1 ~(%2)~|key:name_first|2-key:role}}' ),
		bws_fixture_gb_row( 'J26', '{{join mode:template|format:%1′ / ~%2 in~|key:height_ft|2-key:height_in_blank}}' ),
		bws_fixture_gb_row( 'J27', '{{join mode:template|format:~%1 ft~ / ~%2 in~|key:name_generation|2-key:height_in_blank|fallback_text:—}}' ),
		bws_fixture_gb_row( 'J28', '{{join mode:template|format:%1 ~~ %2|key:height_ft|2-key:height_in}}' ),
		bws_fixture_gb_row( 'J28b', '{{join mode:template|format:~%1 in~ ~~ ~%2 cm~|key:height_ft|2-key:height_in}}' ),
		bws_fixture_gb_row( 'J28c', '{{join mode:template|format:~%1 ft~ ~~ ~%2 in~|key:height_ft|2-key:height_in_blank}}' ),
	) );

	// datetime matrix (datetime-test-matrix.md) — D0/D1/D2 baseline rows, D3
	// (#25) + D4 src:ref (#30) new-behavior rows, D5 sources. Term-hop D4 rows
	// live on the term pages (matrix_term_hop); D5.5 loop-item is
	// render-tag-only (stated exception in the matrix).
	$sections[] = bws_fixture_gb_section( 'Datetime D0 - single basics', array(
		bws_fixture_gb_row( 'D0.1', '{{datetime_single key:event_start_date}}' ),
		bws_fixture_gb_row( 'D0.2', '{{datetime_single key:event_datetime}}' ),
		bws_fixture_gb_row( 'D0.3', '{{datetime_single key:event_datetime|format:Y-m-d}}' ),
		bws_fixture_gb_row( 'D0.4', '{{datetime_single key:event_thisyear}}' ),
		bws_fixture_gb_row( 'D0.5', '{{datetime_single key:event_thisyear|showCurrentYear}}' ),
		bws_fixture_gb_row( 'D0.6', '{{datetime_single key:event_midnight}}' ),
		bws_fixture_gb_row( 'D0.7', '{{datetime_single key:event_midnight|showMidnight}}' ),
		bws_fixture_gb_row( 'D0.8', '{{datetime_single key:event_start_date|timeKey:event_time}}' ),
		bws_fixture_gb_row( 'D0.9', '{{datetime_single key:event_start_date|timeKey:event_time|timeSep: @ }}' ),
		bws_fixture_gb_row( 'D0.10', '{{datetime_single key:plain_meta_date}}' ),
		bws_fixture_gb_row( 'D0.11', '{{datetime_single key:event_date_dmy}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Datetime D1 - as: narrowing', array(
		bws_fixture_gb_row( 'D1.1', '{{datetime_single key:event_datetime|as:date}}' ),
		bws_fixture_gb_row( 'D1.2', '{{datetime_single key:event_datetime|as:time}}' ),
		bws_fixture_gb_row( 'D1.3', '{{datetime_single key:event_time|as:time}}' ),
		bws_fixture_gb_row( 'D1.4', '{{datetime_range startKey:event_datetime|as:time}}' ),
		bws_fixture_gb_row( 'D1.5', '{{datetime_range startKey:event_datetime|as:time|format:H:i}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Datetime D2 - range basics', array(
		bws_fixture_gb_row( 'D2.1', '{{datetime_range startKey:event_start_date|endKey:event_end_date}}' ),
		bws_fixture_gb_row( 'D2.2', '{{datetime_range startKey:event_start_date|endKey:event_end_date|rangeSep: to }}' ),
		bws_fixture_gb_row( 'D2.3', '{{datetime_range startKey:event_datetime|endKey:event_end_datetime}}' ),
		bws_fixture_gb_row( 'D2.4', '{{datetime_range startKey:event_midnight|endKey:event_end_datetime}}' ),
		bws_fixture_gb_row( 'D2.5', '{{datetime_range startKey:event_datetime|endKey:event_end_datetime|as:time}}' ),
		bws_fixture_gb_row( 'D2.6', '{{datetime_range startKey:event_time|endKey:event_end_time|as:time}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Datetime D3 - #25 two-ended as:time custom format', array(
		bws_fixture_gb_row( 'D3.1', '{{datetime_range startKey:event_datetime|endKey:event_end_datetime|as:time|format:H:i}}' ),
		bws_fixture_gb_row( 'D3.2', '{{datetime_range startKey:event_datetime|endKey:event_end_datetime|as:time|format:g:i}}' ),
		bws_fixture_gb_row( 'D3.3', '{{datetime_range startKey:event_time|endKey:event_end_time|as:time|format:g:i A}}' ),
		bws_fixture_gb_row( 'D3.4', '{{datetime_range startKey:event_midnight|endKey:event_end_datetime|as:time|format:H:i}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Datetime D4 - src:ref list mode (#30)', array(
		bws_fixture_gb_row( 'D4.6', '{{datetime_single src:ref|ref:related_staff|key:event_datetime|limit:5}}' ),
		bws_fixture_gb_row( 'D4.7', '{{datetime_range src:ref|ref:related_staff|startKey:event_datetime|endKey:event_end_datetime|limit:3|sep:; }}' ),
		bws_fixture_gb_row( 'D4.8', '{{datetime_single src:ref|ref:related_staff|key:event_datetime|limit:5|linkTo:permalink}}' ),
		bws_fixture_gb_row( 'D4.9', '{{datetime_single src:ref|ref:related_staff|key:event_datetime|linkTo:permalink}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Datetime D5 - sources + fallback', array(
		bws_fixture_gb_row( 'D5.1', '{{datetime_single src:site|key:organization_founded}}' ),
		bws_fixture_gb_row( 'D5.2', '{{datetime_single src:site|key:organization_founded|format:F j, Y}}' ),
		bws_fixture_gb_row( 'D5.3', '{{datetime_single src:site|key:org_party_datetime}}' ),
		bws_fixture_gb_row( 'D5.4', '{{datetime_single src:ref|ref:related_staff|key:event_datetime}}' ),
		bws_fixture_gb_row( 'D5.6', '{{datetime_single key:missing_dt_field|fallback:Date TBA}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Join - per-slot src / use / site / list (absorb)', array(
		bws_fixture_gb_row( 'J15', '{{join use:title|2-use:key|2-key:role|valueSep: / }}' ),
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
		bws_fixture_gb_row( 'J1b', '{{join key:name_first|2-key:name_last|valueSep: }}' ),
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
	) )
	// datetime matrix D4 (#30) — srcTermIn list rows. The page's assigned terms
	// make the case (valid / mixed-junk / all-junk), same as the phone rows.
	. "\n\n" . bws_fixture_gb_section( 'Datetime D4 - srcTermIn list (#30)', array(
		bws_fixture_gb_row( 'D4.1', '{{datetime_single srcTermIn:department|key:event_date|limit:5}}' ),
		bws_fixture_gb_row( 'D4.2', '{{datetime_single srcTermIn:department|key:event_date}}' ),
		bws_fixture_gb_row( 'D4.3', '{{datetime_single srcTermIn:department|key:event_date|limit:5|sep: / }}' ),
		bws_fixture_gb_row( 'D4.4', '{{datetime_single srcTermIn:department|key:event_date|limit:5|fallback:Dates TBA}}' ),
		bws_fixture_gb_row( 'D4.5', '{{datetime_range srcTermIn:department|startKey:event_date|limit:5|sep:; }}' ),
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
