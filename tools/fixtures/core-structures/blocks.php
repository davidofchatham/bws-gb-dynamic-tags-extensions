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

/**
 * Shape 2b — block-host row for a tag that outputs whole HTML (e.g. {{table}}).
 *
 * {{table}} emits a full <table>; it must NOT sit in a <p> (the tag warns of
 * this). Host it in a generateblocks/TEXT block with tagName:div — the SAME
 * dynamic-tag-resolving block as the <p> text row, just a div wrapper (the
 * "text-as-div" host, {{content}} precedent). NOT generateblocks/element (a
 * container that does NOT parse dynamic tags in its body → "invalid content").
 * A label line precedes it (own <p>) so the front-end output maps to a matrix id.
 */
function bws_fixture_gb_block_host_row( $label, $tag ) {
	$uid   = bws_fixture_gb_uid( 'blockhost:' . $label . $tag );
	$lbl   = bws_fixture_gb_text_block( $label . ':', 'blockhost-label:' . $label );
	$inner = $lbl . "\n\n<!-- wp:generateblocks/text {\"uniqueId\":\"{$uid}\",\"tagName\":\"div\",\"className\":\"\"} -->\n"
		. "<div class=\"gb-text\">{$tag}</div>\n<!-- /wp:generateblocks/text -->";
	return $inner;
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
	return bws_fixture_gb_query_loop_blocks(
		$query,
		bws_fixture_gb_text_block( $inner_tag, 'loop-inner:' . $seed ),
		$seed
	);
}

/**
 * Shape 4b — the same nest around ALREADY-BUILT inner blocks.
 *
 * Exists for the one case Shape 4 cannot express: a loop row whose tag is EXPECTED to
 * render empty. GB hides a text block whose tag resolves to nothing and hides the WHOLE
 * block, so a single-block row inside a loop takes its own static label with it and the
 * case reads as missing fixture — exactly what bws_fixture_gb_empty_row() solves outside
 * a loop, and it needs two blocks to do it.
 *
 * Shape 4 delegates here rather than the two carrying a copy of the nest each: the query
 * / looper / loop-item wrapper is one structure and GB's block grammar is where it is
 * defined, not here.
 *
 * @since 1.17.0
 */
function bws_fixture_gb_query_loop_blocks( array $query, $inner_blocks, $seed ) {
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
	$inner = $inner_blocks;
	return "<!-- wp:generateblocks/query {$q_json} -->\n<div>"
		. "<!-- wp:generateblocks/looper {\"uniqueId\":\"{$l_uid}\",\"tagName\":\"ol\",\"className\":\"\"} -->\n<ol>"
		. "<!-- wp:generateblocks/loop-item {\"uniqueId\":\"{$i_uid}\",\"tagName\":\"li\",\"className\":\"\"} -->\n"
		. "<li class=\"gb-loop-item\">{$inner}</li>\n"
		. "<!-- /wp:generateblocks/loop-item --></ol>\n"
		. "<!-- /wp:generateblocks/looper --></div>\n"
		. "<!-- /wp:generateblocks/query -->";
}

/**
 * A GB Pro POST_META query loop — one loop row per ACF repeater row (Mode 2b).
 *
 * The ONLY fixture shape that reaches Mode 2b, and it cannot be substituted. A loop row
 * here is a bare ACF sub-field ARRAY with no `ID` key, so `bws_get_loop_row_context()`
 * reports `in_loop` with `row_post_id` FALSE — no post entity to bind. That is what makes
 * the loop-fallthrough branch fire: the source factory resolves a `meta_row`, no post id
 * comes out of it, and the core fn reads the value off `$loop_item[$key]` instead.
 *
 * A `WP_Query` loop (bws_fixture_gb_query_loop) CANNOT stand in for this: its rows are
 * WP_Post objects, so a post id always resolves and the branch never runs. Neither can
 * `wp bws render-tag --loop-item=<id>`, which takes a post id by construction. So this is
 * the one place the branch is observable at all, on any tag family.
 *
 * `meta_key_id` empty = read the repeater off the CURRENT post, which is the page these
 * rows are seeded onto. `posts_per_page` is the string '-1' because GB Pro compares it
 * with `===` against a string before treating it as "all rows".
 *
 * @since 1.17.0
 */
function bws_fixture_gb_post_meta_loop( $meta_key, $inner_tag, $seed ) {
	$q_uid  = bws_fixture_gb_uid( 'metaquery:' . $seed );
	$l_uid  = bws_fixture_gb_uid( 'metalooper:' . $seed );
	$i_uid  = bws_fixture_gb_uid( 'metaitem:' . $seed );
	$q_json = json_encode(
		array(
			'uniqueId'  => $q_uid,
			'tagName'   => 'div',
			'queryType' => 'post_meta',
			'query'     => array(
				'meta_key'       => $meta_key,
				'meta_key_id'    => '',
				'posts_per_page' => '-1',
			),
			'className' => '',
		)
	);
	$inner = bws_fixture_gb_text_block( $inner_tag, 'metaloop-inner:' . $seed );
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
 * Shape 2c — row whose tag is EXPECTED to render empty.
 *
 * GB hides a text block whose dynamic tag resolves to nothing, and it hides the
 * WHOLE block — so a one-block row takes its own static label down with it and
 * the case reads as MISSING FIXTURE rather than as the asserted empty. Splitting
 * the label into its own block keeps it visible with nothing after it, which is
 * what "renders empty" should look like on the page.
 *
 * Use ONLY where empty is the expectation; a row that empties unexpectedly should
 * disappear, because that is the signal.
 */
function bws_fixture_gb_empty_row( $label, $tag ) {
	return bws_fixture_gb_text_block( $label . ':', 'emptyrow-label:' . $label )
		. "\n\n" . bws_fixture_gb_text_block( $tag, 'emptyrow:' . $label . $tag );
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

	// src:site matrix — R8 datetime try_ site slots (FW-84). The two datetime_
	// templates were the last families without a site slot; FW-4 wired five and
	// left these out. Their keys are TAG-LEVEL, not per-slot, so the fallthrough
	// rows vary only the SOURCE and read one key from whichever store the winning
	// slot names. The property under test is byte-parity with the base tag.
	$sections[] = bws_fixture_gb_section( 'Site R8 - datetime try_ site slots', array(
		bws_fixture_gb_row( 'R8.1 (expect September 20, 2030 6:00 PM)', '{{try_datetime_single src:site|key:org_party_datetime}}' ),
		bws_fixture_gb_row( 'R8.2 (expect January 15, 2020 - custom format over the ACF return format)', '{{try_datetime_single src:site|key:organization_founded|format:F j, Y}}' ),
		bws_fixture_gb_row( 'R8.3 (expect September 20 9:00 am-September 22, 2030 5:00 pm - site range pair, times included)', '{{try_datetime_range src:site|startKey:org_conference_start|endKey:org_conference_end}}' ),
		bws_fixture_gb_row( 'R8.4 (expect the date LINKED to the home URL - site sentinel wrap)', '{{try_datetime_single src:site|key:org_party_datetime|linkTo:key|linkKey:home}}' ),
		bws_fixture_gb_row( 'R8.5 (expect September 20, 2030 6:00 PM - slot A misses on this page, site slot wins)', '{{try_datetime_single key:org_party_datetime|2-src:site}}' ),
		bws_fixture_gb_row( 'R8.6 (expect August 12, 2030 9:00 AM - slot A hits, site slot never reached)', '{{try_datetime_single key:event_datetime|2-src:site}}' ),
		bws_fixture_gb_row( 'R8.7 (expect September 20 9:00 am-September 22, 2030 5:00 pm - range falls through to the site slot)', '{{try_datetime_range startKey:org_conference_start|endKey:org_conference_end|2-src:site}}' ),
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
		// should lead (format group). In the modal, flip Return As URL->alt->URL:
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

	// limit-default matrix (limit-default-test-matrix.md, 1.17.0). Cross-cutting
	// like the FW-52 rows above — named for the PROPERTY, not a tag family. L1
	// pins the regression floor (unset limit stays 1 AND the count-based link gate
	// still wraps); L2 pins explicit values; L3 exercises the new `0` = UNLIMITED
	// semantics. Fixture state: this page carries two valid department terms
	// (Support, Sales) and related_staff = jane, tom (jane FIRST), so every list
	// row has ≥2 candidates and a 1→many flip is VISIBLE.
	// The L1 rows are the ones to read first after any limit-touching change: a
	// silent flip drops the <a> while the text still reads fine.
	$sections[] = bws_fixture_gb_section( 'Limit L1 - unset limit MUST stay 1 (one value AND link present)', array(
		bws_fixture_gb_row( 'L1.1 (expect ONE dept name, linked)', '{{text srcTermIn:department|use:title|linkTo:permalink}}' ),
		bws_fixture_gb_row( 'L1.2 (expect Jane Partner only, linked)', '{{text src:ref|ref:related_staff|use:title|linkTo:permalink}}' ),
		bws_fixture_gb_row( 'L1.3 (expect Jane Partner only, linked)', '{{title src:ref|ref:related_staff|linkTo:permalink}}' ),
		bws_fixture_gb_row( 'L1.4 (expect ONE dept name, linked)', '{{title srcTermIn:department|linkTo:permalink}}' ),
		bws_fixture_gb_row( "L1.5 (expect ONE date - jane's - linked)", '{{datetime_single src:ref|ref:related_staff|key:event_datetime|linkTo:permalink}}' ),
		bws_fixture_gb_row( 'L1.6 (expect ONE date, no separator)', '{{datetime_range srcTermIn:department|startKey:event_date}}' ),
		bws_fixture_gb_row( 'L1.7 (expect ONE dept name, no separator)', '{{try_text srcTermIn:department|use:title}}' ),
		bws_fixture_gb_row( 'L1.8 (expect ONE mailto anchor)', '{{email src:ref|ref:related_staff|key:contact_email}}' ),
		bws_fixture_gb_row( 'L1.9 (expect ONE tel anchor)', '{{phone src:ref|ref:related_staff|key:main_line}}' ),
		bws_fixture_gb_row( 'L1.10 (expect ONE dept name joined to the role)', '{{join srcTermIn:department|use:title|2-key:role}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Limit L2 - explicit values still behave', array(
		bws_fixture_gb_row( 'L2.1 (expect BOTH names comma-joined, NO link)', '{{text src:ref|ref:related_staff|use:title|limit:2|linkTo:permalink}}' ),
		bws_fixture_gb_row( 'L2.2 (expect Jane Partner linked - explicit 1 === unset 1)', '{{text src:ref|ref:related_staff|use:title|limit:1|linkTo:permalink}}' ),
		bws_fixture_gb_row( 'L2.3 (expect both dept names - no ceiling)', '{{text srcTermIn:department|use:title|limit:99}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Limit L3 - 0 = UNLIMITED (1.17.0 semantics change)', array(
		bws_fixture_gb_row( 'L3.1 (expect BOTH names - rendered ONE before 1.17.0)', '{{text src:ref|ref:related_staff|use:title|limit:0}}' ),
		bws_fixture_gb_row( 'L3.2 (expect BOTH names - -1 parsed tolerantly)', '{{text src:ref|ref:related_staff|use:title|limit:-1}}' ),
		bws_fixture_gb_row( 'L3.3 (expect Jane Partner linked - is_numeric guard, garbage is NOT unlimited)', '{{text src:ref|ref:related_staff|use:title|limit:abc|linkTo:permalink}}' ),
		bws_fixture_gb_row( 'L3.4 (expect both dept names - try_ dispatch does not break early at 0)', '{{try_text srcTermIn:department|use:title|limit:0}}' ),
		bws_fixture_gb_row( 'L3.5 (expect both dept event dates)', '{{datetime_single srcTermIn:department|key:event_date|limit:0}}' ),
		bws_fixture_gb_row( 'L3.6 (expect both names, NO link - unlimited feeds the same count gate)', '{{text srcTermIn:department|use:title|limit:0|linkTo:permalink}}' ),
	) );

	// L4 — the unset default is no longer ONE number. Flat wire bounds at 1, chain
	// wire does not (bws_limit_default), which is the whole compatibility
	// mechanism for base-tag source chains: it works on wire no migration can
	// reach. Rows are PAIRS OF SPELLINGS for the same source, and each asserts the
	// link too, because the gate is count-based — chain wire defaulting to many
	// changes link-wrapping as well as output.
	$sections[] = bws_fixture_gb_section( 'Limit L4 - the SPELLING selects the unset default (1.17.0)', array(
		bws_fixture_gb_row( 'L4.1 FLAT unset (expect Jane Partner only, linked - the floor)', '{{text src:ref|ref:related_staff|use:title|linkTo:permalink}}' ),
		bws_fixture_gb_row( 'L4.2 CHAIN unset (expect BOTH names, NO link - unlimited, and the anchor is legitimately gone)', '{{text src:refs,related_staff|use:title|linkTo:permalink}}' ),
		bws_fixture_gb_row( 'L4.3 CHAIN + explicit 1 (expect Jane Partner only, linked - HAND-WRITTEN wire; a converted tag is L4.10, and OPENING this row absorbs the 1 onto the step)', '{{text src:refs,related_staff|use:title|limit:1|linkTo:permalink}}' ),
		bws_fixture_gb_row( 'L4.4 FLAT term hop unset (expect ONE dept name)', '{{text srcTermIn:department|use:title}}' ),
		bws_fixture_gb_row( 'L4.5 CHAIN term hop unset (expect Sales, Support)', '{{text src:terms,department|use:title}}' ),
		bws_fixture_gb_row( 'L4.6 CHAIN + garbage limit (expect BOTH names - the is_numeric guard falls to the CHAIN default, not to 1)', '{{text src:refs,related_staff|use:title|limit:abc}}' ),
		bws_fixture_gb_row( 'L4.7 root-only chain does not fan (expect Captain, linked)', '{{text src:current|key:role|linkTo:permalink}}' ),
		bws_fixture_gb_row( 'L4.8 PER-STEP limit is a different quantity (expect Jane Partner only, linked)', '{{text src:refs,related_staff,limit(1)|use:title|linkTo:permalink}}' ),
		bws_fixture_gb_row( 'L4.9 L4.8-s partner - without it a per-step limit that did nothing would still pass (expect BOTH names)', '{{text src:refs,related_staff,limit(2)|use:title}}' ),
		// L4.10/L4.11 — what MIGRATION writes, as opposed to what a hand-authored
		// limit does. Each is paired with its flat original, and L4.11 additionally
		// with an unlimited partner, so a mapping that did nothing cannot pass.
		bws_fixture_gb_row( 'L4.10 what MIGRATION writes for L4.1 (expect Jane Partner only, linked - identical to the flat row it replaces)', '{{text src:refs,related_staff,limit(1)|use:title|linkTo:permalink}}' ),
		bws_fixture_gb_row( 'L4.11a TWO fanning steps, FLAT original (expect All Users)', '{{text src:ref|ref:related_staff|srcTermIn:portal_visibility|use:title}}' ),
		bws_fixture_gb_row( 'L4.11b the same source MIGRATED - both steps limited (expect All Users, same as L4.11a)', '{{text src:refs,related_staff,limit(1);terms,portal_visibility,limit(1)|use:title}}' ),
		bws_fixture_gb_row( 'L4.11c L4.11b-s partner, no step limits (expect All Users, All Users - this is what makes the pair non-vacuous)', '{{text src:refs,related_staff;terms,portal_visibility|use:title}}' ),
		// L5.8's SUBJECT: a flat tag carrying an author-stated limit, there to be
		// converted in the editor. Front-end expectation is the pre-conversion one.
		bws_fixture_gb_row( 'L5.8 subject - FLAT with an author-stated limit:3 (expect BOTH names; convert it in the editor and the 3 moves onto the last step)', '{{text src:ref|ref:related_staff|use:title|limit:3}}' ),
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

	// --- Folded slot wire + src chain (FW-56/57, 1.17.0) ---------------------
	// Rows: tools/test/fold-test-matrix.md. PLACEMENT: this cluster sits between
	// the Join group and the Table group deliberately. The fold is a WIRE-FORM
	// axis, not a tag family, so Catalog order has no slot of its own for it —
	// but its containers are join and try_ (both multislot) and {{table}} arrives
	// folded, so "multislot wire" reads correctly exactly here.
	//
	// Every F1/F2 row is a PAIR: the legacy spelling then the folded one, adjacent
	// on the page, because the assertion is that they render IDENTICALLY. An
	// eyeball comparison is the whole point, so do NOT split the pairs.
	$sections[] = bws_fixture_gb_section( 'Fold F1 - join folded == legacy (pairs must match)', array(
		bws_fixture_gb_row( 'F1.1 legacy (-> Jane)', '{{join key:name_first|2-key:name_last}}' ),
		bws_fixture_gb_row( 'F1.1 folded (-> Jane, same as above)', '{{join A:key(name_first)|B:key(name_last)}}' ),
		bws_fixture_gb_row( 'F1.2 legacy (-> Matrix: Post Meta / Captain)', '{{join use:title|2-use:key|2-key:role|valueSep: / }}' ),
		bws_fixture_gb_row( 'F1.2 folded (-> same)', '{{join A:use(title)|B:use(key);key(role)|valueSep: / }}' ),
		bws_fixture_gb_row( 'F1.3 legacy (-> (987) 654-3210, 987.654.3210)', '{{join key:main_line|2-src:same|2-key:booking_line}}' ),
		bws_fixture_gb_row( 'F1.3 folded (-> same)', '{{join A:key(main_line)|B:src(same);key(booking_line)}}' ),
		bws_fixture_gb_row( 'F1.4 legacy (-> (555) 200-3000, jane@example.test; slot 2 INHERITS the ref hop)', '{{join src:ref|ref:related_staff|use:key|key:main_line|2-src:same|2-key:contact_email}}' ),
		bws_fixture_gb_row( 'F1.4 folded, as MIGRATION writes it - limit[1] states what the flat spelling implied (-> same)', '{{join A:src(refs,related_staff,limit[1]);use(key);key(main_line)|B:src(same);key(contact_email)}}' ),
		bws_fixture_gb_row( 'F1.5 legacy (-> Jane, Jane Partner)', '{{join key:name_first|2-src:ref|2-ref:related_staff|2-use:title}}' ),
		bws_fixture_gb_row( 'F1.5 folded, as MIGRATION writes it (-> same; drop the limit[1] and it reads Jane, Jane Partner, Tom Associate)', '{{join A:key(name_first)|B:src(refs,related_staff,limit[1]);use(title)}}' ),
		bws_fixture_gb_row( 'F1.6 legacy (-> Jane, info@example.test)', '{{join key:name_first|2-src:site|2-key:organization_email}}' ),
		bws_fixture_gb_row( 'F1.6 folded (-> same)', '{{join A:key(name_first)|B:src(site);key(organization_email)}}' ),
		bws_fixture_gb_row( 'F1.7 legacy (-> Sales, Support)', '{{join srcTermIn:department|use:title|limit:2}}' ),
		bws_fixture_gb_row( 'F1.7 folded (-> same; the term hop WORKS in a slot - contrast F9.1)', '{{join A:src(terms,department);use(title);limit(2)}}' ),
		bws_fixture_gb_row( 'F1.10 folded (-> em dash: both slots empty on this page, fallback fires)', '{{join A:key(name_generation)|B:key(name_credential)|fallback:—}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Fold F2 - mixed-era wire (era is per SLOT, not per tag)', array(
		bws_fixture_gb_row( 'F2.1 folded slot 1 + legacy slot 2 inheriting from it (-> (987) 654-3210, 987.654.3210)', '{{join A:key(main_line)|2-src:same|2-key:booking_line}}' ),
		bws_fixture_gb_row( 'F2.2 legacy slot 1 + folded slot 2 inheriting from it (-> same)', '{{join key:main_line|B:src(same);key(booking_line)}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Fold F3-F5 - try_ slots, all three read shapes', array(
		bws_fixture_gb_row( 'F3.1 enum+picker: slot 1 empty, slot 2 wins (-> Captain)', '{{try_text A:key(missing_field)|B:key(role)}}' ),
		bws_fixture_gb_row( 'F3.2 slot 1 resolves, slot 2 never runs (-> Captain)', '{{try_text A:key(role)|B:key(name_first)}}' ),
		bws_fixture_gb_row( 'F3.4 slot 2 hops a relationship (-> Jane Partner)', '{{try_text A:key(missing_field)|B:src(refs,related_staff);use(title)}}' ),
		bws_fixture_gb_row( 'F3.6 legacy twin of F3.1 (-> Captain)', '{{try_text key:missing_field|2-use:key|2-key:role}}' ),
		bws_fixture_gb_row( 'F4.2 picker-alone shape: unused_line is EMPTY so slot 1 is a real skip (-> (987) 654-3210)', '{{try_phone A:key(unused_line)|B:key(main_line)}}' ),
		bws_fixture_gb_row( 'F4.4 legacy twin of F4.2 (-> same)', '{{try_phone key:unused_line|2-key:main_line}}' ),
		bws_fixture_gb_empty_row( 'F4.5 EMPTY AND CORRECT: key is a SLOT axis on try_phone, so a tag-level key configures nothing', '{{try_phone A:src(refs,related_staff)|B:src(current)|key:main_line}}' ),
		bws_fixture_gb_row( 'F5.1 no-read shape: an EMPTY slot 1 value is the default attempt (-> Matrix: Post Meta)', '{{try_title 1:|B:src(site)}}' ),
		bws_fixture_gb_row( 'F5.2 same, via an explicit src(current) - the 5f bug was this rendering NOTHING', '{{try_title A:src(current)|B:src(site)}}' ),
		bws_fixture_gb_row( 'F5.3 (-> https://testbed.test/staff/jane-partner/)', '{{try_permalink A:src(refs,related_staff)|B:src(site)}}' ),
		bws_fixture_gb_row( 'F5.5 slot 1 genuinely wins - jane\'s date, not the page\'s (-> May 1, 2030 10:00 AM)', '{{try_datetime_single A:src(refs,related_staff)|B:src(current)|key:event_datetime}}' ),
		bws_fixture_gb_row( 'F5.6 legacy twin of F5.4 - slot 1 hop misses, slot 2 reads current (-> August 12, 2030 9:00 AM)', '{{try_datetime_single src:ref|ref:missing_rel|2-src:current|key:event_datetime}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Fold F6-F7 - inherit vs RESET, and slot-level limit', array(
		bws_fixture_gb_row( 'F6.1 absent chain at slot 2 = RESET to the page, NOT an inherit (-> (987) 654-3210)', '{{try_text A:src(refs,related_staff);key(missing)|B:key(main_line)}}' ),
		bws_fixture_gb_row( 'F6.2 explicit src(same) inherits jane (-> (555) 200-3000)', '{{try_text A:src(refs,related_staff);key(missing)|B:src(same);key(main_line)}}' ),
		bws_fixture_gb_row( 'F6.3 slot 2 RESETS to the page, which has no contact_email, so it drops (-> (555) 200-3000, (555) 200-4000)', '{{join A:src(refs,related_staff);use(key);key(main_line)|B:key(contact_email)}}' ),
		bws_fixture_gb_row( 'F6.4 slot 1 chain-spelled = UNBOUNDED, slot 2 inherits both axes and keeps the flat 1 (-> Jane Partner, Tom Associate, Jane Partner)', '{{join A:src(refs,related_staff);use(title)|B:src(same);use(same)}}' ),
		// F7a - a slot's own spelling decides its own limit (#60). The refs half; the
		// terms half lives on the term-hop pages, where the fixture has real terms.
		bws_fixture_gb_row( 'F7a.4 a refs-spelled slot returns EVERY target - no limit anywhere (-> (555) 200-3000, (555) 200-4000)', '{{join A:src(refs,related_staff);use(key);key(main_line)}}' ),
		bws_fixture_gb_row( 'F7a.4b the legacy spelling still bounds at 1 - the row that makes F7a.4 non-vacuous (-> (555) 200-3000)', '{{join src:ref|ref:related_staff|use:key|key:main_line}}' ),
		bws_fixture_gb_row( 'F7a.12 legacy: no fanning step, so limit:4 bounds nothing (-> Captain)', '{{try_text key:role|limit:4}}' ),
		bws_fixture_gb_row( 'F7b.3 MIGRATED twin - nothing to bound, so nothing is pushed and the tag-level key still goes (-> same)', '{{try_text A:key(role)}}' ),
		bws_fixture_gb_row( 'F7a.13 join legacy: join owns limit PER SLOT, so slot 1 bare key IS its own (-> (987) 654-3210, 987.654.3210)', '{{join key:main_line|limit:4|2-key:booking_line}}' ),
		bws_fixture_gb_row( 'F7a.13b MIGRATED twin - it stays a slot-level token (-> same)', '{{join A:limit(4);key(main_line)|B:src(same);key(booking_line)}}' ),
		// F7b (#61) - the refs half of the inheriting-slot pair. Both sides render
		// `Jane Partner` because the try_ refs arm is first-only, so the carried bound
		// is invisible here until FW-63; the row exists to show the pair does not MOVE.
		bws_fixture_gb_row( 'F7b.6 legacy: slot 1 fans on refs and misses, slot 2 inherits the source (-> Jane Partner)', '{{try_text src:ref|ref:related_staff|use:key|key:no_such|2-src:same|2-use:title|limit:2}}' ),
		bws_fixture_gb_row( 'F7b.6b MIGRATED twin - the tag-level key is gone and slot 2 inherits the bound with the source (-> same)', '{{try_text A:src(refs,related_staff,limit[2]);key(no_such)|B:src(same);use(title)}}' ),
		// The PAIRS CROSS here, and these four rows are the cheapest way to see it:
		// legacy absence means INHERIT (it materializes to src(same) through the
		// mapper), folded absence means RESET. So folded `2:key(x)` twins legacy
		// `2-src:current|2-key:x`, and folded `2:src(same);key(x)` twins legacy
		// `2-key:x`. jane carries no `role`, so the two readings differ VISIBLY:
		// reset reads the page (Captain), inherit reads jane (nothing).
		bws_fixture_gb_row( 'F7.1 folded, slot-level limit(2), slot 2 RESETS to the page (-> Jane Partner, Tom Associate, Captain)', '{{join A:src(refs,related_staff);use(title);limit(2)|B:key(role)}}' ),
		bws_fixture_gb_row( 'F7.1 legacy twin - needs an EXPLICIT 2-src:current to mean reset (-> same)', '{{join src:ref|ref:related_staff|use:title|limit:2|2-src:current|2-key:role}}' ),
		bws_fixture_gb_row( 'F7.2 legacy absence = INHERIT jane, who has no role -> slot 2 drops (-> Jane Partner, Tom Associate)', '{{join src:ref|ref:related_staff|use:title|limit:2|2-key:role}}' ),
		bws_fixture_gb_row( 'F7.2 folded twin - src(same) is how the fold spells that inherit (-> same)', '{{join A:src(refs,related_staff);use(title);limit(2)|B:src(same);key(role)}}' ),
	) );

	// F7c (#62) - the tag-level Result Limit CONTROL is unregistered on every
	// chain-authoring base tag, and the VALUE is still read. Removing an option never
	// removes its value: GB seeds state from the parsed tag string, not the registry.
	// F7c.2 is the non-vacuous partner - unset still bounds at 1 on flat wire, so a row
	// printing two terms is the stored limit being honored rather than a default fan-out.
	$sections[] = bws_fixture_gb_section( 'Fold F7c - stored limit renders with NO control (#62)', array(
		bws_fixture_gb_row( 'F7c.1 text, stored limit:2 (-> Sales, Support)', '{{text srcTermIn:department|use:title|limit:2}}' ),
		bws_fixture_gb_row( 'F7c.2 text, limit UNSET - still 1 on flat wire, which makes F7c.1 mean something (-> Sales)', '{{text srcTermIn:department|use:title}}' ),
		bws_fixture_gb_row( 'F7c.3 text, stored limit:0 is still UNLIMITED (-> Sales, Support)', '{{text srcTermIn:department|use:title|limit:0}}' ),
		bws_fixture_gb_row( 'F7c.4 title, stored limit:2 (-> Sales, Support)', '{{title srcTermIn:department|limit:2}}' ),
		bws_fixture_gb_row( 'F7c.5 email, stored limit:2 (-> jane@example.test, tom@example.test)', '{{email src:ref|ref:related_staff|key:contact_email|limit:2|noLink}}' ),
		bws_fixture_gb_row( 'F7c.6 phone, stored limit:2 (-> (555) 200-3000, (555) 200-4000)', '{{phone src:ref|ref:related_staff|key:main_line|limit:2|noLink}}' ),
		bws_fixture_gb_row( 'F7c.7 datetime_single, stored limit:2 (-> May 1, 2030, June 1, 2030)', '{{datetime_single src:ref|ref:related_staff|key:event_datetime|limit:2|as:date}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Fold F8 - src CHAIN on base tags (5h compiler)', array(
		bws_fixture_gb_row( 'F8.2 legacy (-> (555) 200-3000)', '{{phone src:ref|ref:related_staff|key:main_line}}' ),
		bws_fixture_gb_row( 'F8.2 chain (-> same)', '{{phone src:refs,related_staff|key:main_line}}' ),
		bws_fixture_gb_row( 'F8.3 legacy (-> Jane Partner)', '{{text src:ref|ref:related_staff|use:title}}' ),
		bws_fixture_gb_row( 'F8.3 chain (-> same)', '{{text src:refs,related_staff|use:title}}' ),
		bws_fixture_gb_row( 'F8.4 chain, no hop limit (-> BOTH numbers)', '{{phone src:refs,related_staff|key:main_line|limit:0}}' ),
		bws_fixture_gb_row( 'F8.5 PER-HOP limit(1) bounds the fan-out (-> ONE number, despite limit:0)', '{{phone src:refs,related_staff,limit(1)|key:main_line|limit:0}}' ),
		bws_fixture_gb_row( 'F8.6 per-hop limit(2) (-> both numbers again)', '{{phone src:refs,related_staff,limit(2)|key:main_line|limit:0}}' ),
		bws_fixture_gb_row( 'F8.7 TWO relationship steps - the spec headline case, data two relationships away (-> Jane Partner, who is the only reports_to target; Jane herself has none, so that branch drops)', '{{text src:refs,related_staff;refs,reports_to|use:title}}' ),
		bws_fixture_gb_empty_row( 'F8.8 F8.7 with step 1 bounded to Jane, who reports to nobody (-> EMPTY: the step limit bounds step 1 and the chain short-circuits)', '{{text src:refs,related_staff,limit(1);refs,reports_to|use:title}}' ),
		bws_fixture_gb_row( 'F8.9 F8.7 reading a FIELD off the second-degree post, not its title (-> (555) 200-3000, Jane\'s line)', '{{text src:refs,related_staff;refs,reports_to|use:key|key:main_line}}' ),
		bws_fixture_gb_row( 'F8.10 a LATER step\'s limit is PER-INPUT (#72): one term from EACH ref\'d staff (-> All Users, All Users; whole-output rendered one)', '{{text src:refs,related_staff;terms,portal_visibility,limit(1)|use:title}}' ),
		// F8b (#74) - an ARGLESS step on a base tag reads EMPTY, not the ambient entity.
		// Through 1.16.x the compiler DROPPED it, leaving a chain with no steps, and a
		// chain with no steps resolves the ambient entity - so these returned THIS page's
		// own main_line from a tag naming a relationship. Empty rows: GB hides a block
		// whose tag renders nothing, taking a single-block row's label with it.
		bws_fixture_gb_empty_row( 'F8b.1 argless src:ref reads EMPTY, not this page\'s own main_line', '{{text src:ref|use:key|key:main_line}}' ),
		bws_fixture_gb_empty_row( 'F8b.2 same in chain spelling - argless refs step, EMPTY', '{{text src:refs|use:key|key:main_line}}' ),
		bws_fixture_gb_row( 'F8b.3 NEGATIVE CONTROL - a step WITH its argument is untouched (-> (555) 200-3000)', '{{text src:ref|ref:related_staff|use:key|key:main_line}}' ),
		bws_fixture_gb_empty_row( 'F11.1 unknown hop slug SHORT-CIRCUITS (-> empty, and that is correct)', '{{phone src:refs,related_staff;bogus,x|key:main_line}}' ),
		bws_fixture_gb_empty_row( 'F11.2 a ROOT slug at a HOP position (-> empty)', '{{phone src:refs,related_staff;site|key:main_line}}' ),
	) );

	// F11a/F11b (#112) - a source that is PRESENT BUT UNUSABLE renders nothing.
	//
	// EVERY ROW HERE NAMES A KEY THIS PAGE ACTUALLY CARRIES, and that is the point.
	// Until 1.17.0 these shapes rendered a real value read off the ambient entity, so a
	// row keyed on a real field could not tell a refusal from a leak; the rows that
	// predate the fix (F14.18-22) all name `bio`, which is on no fixture. The pre-fix
	// answer is in each label - if one comes back, the refusal regressed.
	//
	// {{email}}/{{phone}} are deliberately absent: they read through the engine seam
	// (F11.1/.2 above), which refused these already, so they cannot show a regression.
	$sections[] = bws_fixture_gb_section( 'Fold F11a - a present-but-unusable source renders nothing (#75/#76/#109)', array(
		bws_fixture_gb_empty_row( 'F11a.1 unregistered token - a source the wire NAMES, not an absent one (-> EMPTY; was the page title, Matrix: Post Meta)', '{{title src:currnet}}' ),
		bws_fixture_gb_empty_row( 'F11a.2 same token, a field read (-> EMPTY; was Captain, this page\'s own role)', '{{text src:currnet|use:key|key:role}}' ),
		bws_fixture_gb_empty_row( 'F11a.3 a REGISTERED source whose resolve is inert - the standing pre-migration state of every tag the related_post entry repairs (-> EMPTY; was Captain)', '{{text src:related_post|use:key|key:role}}' ),
		bws_fixture_gb_empty_row( 'F11a.4 a registered source, correctly written, resolving nothing on THIS page - the measured real-wire shape (-> EMPTY; was Captain)', '{{text src:fixture_scoped|use:key|key:role}}' ),
		bws_fixture_gb_empty_row( 'F11a.4b the image arm, which is one of the seven and reads on its own seam (-> EMPTY; was this page\'s feature_image)', '{{image src:currnet|use:key|key:feature_image}}' ),
		bws_fixture_gb_empty_row( 'F11a.6 the unknown-STEP door, disjoint from the rows above by POSITION (-> EMPTY; was Jane Partner, the chain prefix collapsed to its first result)', '{{title src:refs,related_staff;bogus,x}}' ),
		// THE CONTROL. Absence is not refusal: a bare tag still reads the ambient entity,
		// which is what a source being unset SPELLS. If this row goes empty the refusal
		// over-reached and every bare tag on every site is blank.
		bws_fixture_gb_row( 'F11a.7 CONTROL - an ABSENT source still means the ambient entity (-> Captain)', '{{text use:key|key:role}}' ),
		// The unregistered-ROOT door on the engine-seam families. The unknown-STEP door
		// already reached them (F11.1/.2 above), but this one does not: the token never
		// gets as far as the engine, and these two take none of the seven arms.
		bws_fixture_gb_empty_row( 'F11a.8 phone, unregistered root - the door F11.1/.2 do not reach (-> EMPTY; was this page\'s own main_line)', '{{phone src:currnet|key:main_line}}' ),
		bws_fixture_gb_empty_row( 'F11a.9 email, same door - its own row because the two families wrap a value differently (-> EMPTY)', '{{email src:currnet|key:contact_email}}' ),
		bws_fixture_gb_row( 'F11a.10 CONTROL for F11a.8/.9 on this seam (-> the page number, tel:-wrapped)', '{{phone key:main_line}}' ),
	) );

	// F11c - IN A QUERY LOOP, and it is the ONLY place the root-door guard is observable.
	//
	// Found by mutation: removing the text arm's guard leaves every F11a row green. Off
	// loop the singular cores return before reading anything (! $post_id && ! $is_loop_row),
	// so the arm guard and the core's guard produce the same empty output. The guard earns
	// its place only where the core would have gone on to read something - a loop ROW, or
	// the queried TERM on an archive. F11a still pins the unknown-STEP door, which IS
	// observable off loop; the two doors simply need different rows.
	//
	// Loop over `staff`, so each row's ambient entity carries values of its own.
	$sections[] = bws_fixture_gb_section( 'Fold F11c - the root door in a QUERY LOOP (where the arm guard is observable at all)', array(
		bws_fixture_gb_query_loop(
			array( 'post_type' => 'staff', 'posts_per_page' => 2, 'orderby' => 'title', 'order' => 'ASC' ),
			'F11c.1 CONTROL - a bare tag in a loop reads the ROW, and must go on doing so (-> each row\'s own number): {{text use:key|key:main_line}}',
			'f11c1-loop-control'
		),
		// Split-label form, inside the loop: an empty tag takes its whole block down, so
		// a one-block loop row would read as missing fixture rather than as the asserted
		// empty. Two rows print, each a label with nothing after it.
		bws_fixture_gb_query_loop_blocks(
			array( 'post_type' => 'staff', 'posts_per_page' => 2, 'orderby' => 'title', 'order' => 'ASC' ),
			bws_fixture_gb_empty_row(
				'F11c.2 THE ROW THAT FAILS IF THE ARM GUARD GOES - unregistered token in a loop row (-> EMPTY; was this row\'s own number)',
				'{{text src:currnet|use:key|key:main_line}}'
			),
			'f11c2-loop-unregistered'
		),
		bws_fixture_gb_query_loop_blocks(
			array( 'post_type' => 'staff', 'posts_per_page' => 2, 'orderby' => 'title', 'order' => 'ASC' ),
			bws_fixture_gb_empty_row(
				'F11c.3 registered-but-inert token, same door, same exposure (-> EMPTY)',
				'{{text src:related_post|use:key|key:main_line}}'
			),
			'f11c3-loop-inert'
		),
	) );

	// Asserted separately from F11a because "renders empty" passes whether or not the
	// fallback fired - and a refusal that suppressed the fallback would be one
	// pre-emption traded for another, which is not a fix. Three of these route past a
	// core that OWNS the family's fallback emit, so they are the rows that catch a guard
	// which returned early instead of landing on the arm's own empty path.
	$sections[] = bws_fixture_gb_section( 'Fold F11b - the stated fallback fires; the other two containers do not blank', array(
		bws_fixture_gb_row( 'F11b.1 text: the fallback fires, not empty (-> No source)', '{{text src:currnet|use:key|key:role|fallback:No source}}' ),
		bws_fixture_gb_row( 'F11b.2 content: its fallback lives INSIDE the core a refusal must not run (-> No source)', '{{content src:currnet|use:key|key:role|fallback:No source}}' ),
		bws_fixture_gb_row( 'F11b.4 datetime: its all-empty fallback is gated on the chain FANNING, which a root-refused tag does not (-> No date)', '{{datetime_single src:currnet|key:event_datetime|fallback:No date}}' ),
		bws_fixture_gb_row( 'F11b.5 join DROPS the field from the composite (-> Jane; was Captain, Jane - the misattribution the whole fix is about)', '{{join A:src(bogus,x);key(role)|B:key(name_first)}}' ),
		bws_fixture_gb_row( 'F11b.6 try_ ADVANCES to attempt B (-> Jane; was Captain - attempt A read the ambient entity, SUCCEEDED, and stopped the chain so B never ran)', '{{try_text A:src(currnet);use(key);key(role)|B:use(key);key(name_first)}}' ),
	) );

	// F9 — the recorded DIVERGENCES. On the page deliberately: a reader who
	// eyeballs the fold rows must see that chain wire on a BASE tag is not yet a
	// supported authoring surface, and see it beside F1.7, which is the same term
	// hop working in a slot. Do not "fix" these with a guard — the fix is the
	// verb-agnostic resolver refactor (arms dispatching by terminal step KIND).
	// Was the DIVERGENCE section; the arm refactor (FW-63) turned it into
	// acceptance criteria. Every arm now asks bws_fold_src_resolution() — what the
	// chain RESOLVES TO plus whether it fans — instead of comparing src to
	// 'ref'/'site' or reading flat srcTermIn, so the two spellings take the same
	// arm. Read the PAIRS: a wrong arm renders a PLAUSIBLE value, not an empty
	// one, so a row that "looks fine" on its own is not evidence.
	// NB the chain rows carry NO limit — flat wire bounds at 1, chain wire does not
	// (bws_limit_default), which is the whole compatibility mechanism.
	$sections[] = bws_fixture_gb_section( 'Fold F9 - arm dispatch: chain wire on a BASE tag (pairs must match)', array(
		bws_fixture_gb_row( 'F9.1 legacy term hop (-> Sales, Support)', '{{text srcTermIn:department|use:title|limit:0}}' ),
		bws_fixture_gb_row( 'F9.1 chain term hop, no limit needed (-> Sales, Support; rendered the PAGE TITLE before FW-63)', '{{text src:terms,department|use:title}}' ),
		bws_fixture_gb_row( 'F9.2 legacy list mode (-> Jane Partner, Tom Associate)', '{{text src:ref|ref:related_staff|use:title|limit:0}}' ),
		bws_fixture_gb_row( 'F9.2 chain list mode, no limit needed (-> Jane Partner, Tom Associate; gave ONE before FW-63)', '{{text src:refs,related_staff|use:title}}' ),
		// F9.3's expectation is EMPTY, so it needs the split label form AND a
		// non-vacuity twin: an empty read and a dropped hop both print nothing, and
		// before FW-63 this rendered "Jane Partner" (the wrapper took the leading run
		// of ref steps and stopped, so the term hop vanished).
		bws_fixture_gb_empty_row( 'F9.3 a NON-LEADING hop now runs (-> empty; jane and tom carry no department terms). Rendered Jane Partner before FW-63', '{{text src:refs,related_staff;terms,department|use:title}}' ),
		bws_fixture_gb_row( 'F9.3b non-vacuity control for F9.3 (-> NOHOP; proves the chain resolved and found nothing)', '{{text src:refs,related_staff;terms,department|use:title|fallback:NOHOP}}' ),
		bws_fixture_gb_row( 'F9.4 site read (-> info@example.test)', '{{text src:site|key:organization_email}}' ),
		bws_fixture_gb_row( 'F9.4 site read still WINS over a hand-edited term hop - the pair is hand-edit only, and every arm has always let site win (-> info@example.test, the same value)', '{{text src:site|srcTermIn:department|key:organization_email}}' ),
		// NB the label says "the table tag", not the tag SPELLING — a `{{…}}` inside
		// a label is live wire, and GB renders it. Spelled out here, the empty
		// {{table}} it produced hid this row's whole label block.
		bws_fixture_gb_empty_row( 'F9.5 STILL DIVERGENT by decision: no base arm consumes a meta_row source (-> empty; the table tag fills this gap, not the text arm)', '{{text src:entries,team_members|use:key|key:name|limit:0}}' ),
		// The one flat-wire behaviour change, shown rather than hidden. It uses
		// portal_visibility, NOT department: jane and tom carry no department terms,
		// so that taxonomy makes the row empty either way and it asserts nothing.
		// Drop the limit:0 and this reads All Users on both eras - the floor holding.
		bws_fixture_gb_row( 'F9.6 flat ref+term with an EXPLICIT limit now fans across every ref-d post (-> All Users, All Users; was All Users)', '{{text src:ref|ref:related_staff|srcTermIn:portal_visibility|use:title|limit:0}}' ),
		bws_fixture_gb_row( 'F9.6b the compatibility FLOOR: same tag, limit unset (-> All Users, unchanged from before)', '{{text src:ref|ref:related_staff|srcTermIn:portal_visibility|use:title}}' ),
		// Matches on the AMBIENT page's content, not jane's: {{content}} ignores the
		// relationship step entirely (issue #58, measured identically on main). The
		// PAIR agreeing is the arm-dispatch property; it is not proof the hop works.
		bws_fixture_gb_row( "F9a.3 legacy content ref (-> jane's excerpt, HER values: Jane, Johnson - the pair must MATCH; entity correctness is CT1-CT3 on /matrix-content/)", '{{content src:ref|ref:related_staff|use:excerpt}}' ),
		bws_fixture_gb_row( 'F9a.3 chain content ref (-> same as the legacy row above)', '{{content src:refs,related_staff|use:excerpt}}' ),
	) );

	// F9b — the same arm-dispatch question, one release later, on the try_ SLOT
	// arms (#103). FW-63 converted the BASE arms and left try_'s four testing the
	// flat tokens, which is why the F7a note predicting they would clear with FW-63
	// was wrong. Read the rows in PAIRS again: the two behaviour changes are both
	// reachable only from wire that states an explicit unlimited or spells its
	// source as a chain, and the FLOOR rows beside them are what show that ordinary
	// saved wire did not move. F9b.5 is the odd one out and worth understanding:
	// nothing fans there, the PRECEDENCE between two arms changed.
	$sections[] = bws_fixture_gb_section( 'Fold F9b - arm dispatch: the try_ SLOT arms (#103)', array(
		bws_fixture_gb_row( 'F9b.1 chain-spelled ref attempt now fans (-> Jane Partner, Tom Associate; gave Jane Partner alone before #103)', '{{try_text A:src(refs,related_staff);use(title)}}' ),
		bws_fixture_gb_row( 'F9b.2 flat ref attempt with an EXPLICIT unlimited (-> Jane Partner, Tom Associate; was Jane Partner)', '{{try_text src:ref|ref:related_staff|use:title|limit:0}}' ),
		bws_fixture_gb_row( 'F9b.6 the compatibility FLOOR for both rows above: same tag, limit unset (-> Jane Partner, unchanged)', '{{try_text src:ref|ref:related_staff|use:title}}' ),
		bws_fixture_gb_row( 'F9b.3 the same change in another family, where each value self-wraps (-> both numbers; was one)', '{{try_phone src:ref|ref:related_staff|key:main_line|limit:0}}' ),
		bws_fixture_gb_row( 'F9b.4 the try_ twin of F9.6: ref+term with an explicit unlimited (-> All Users, All Users; was All Users)', '{{try_text src:ref|ref:related_staff|srcTermIn:portal_visibility|use:title|limit:0}}' ),
		// A PRECEDENCE change, not a fanning one. The old arms tested srcTermIn
		// FIRST, so this attempt took the term arm, resolved no post from a site
		// source, and skipped whole. Kind dispatch answers `site`, which is what the
		// base tag has always done — F9.4 above is the unchanged control for it.
		bws_fixture_gb_row( 'F9b.5 site read now WINS over a hand-edited term hop, as it always has on the base tag (-> the org phone; rendered NOTHING before #103)', '{{try_phone src:site|srcTermIn:department|key:org_phone}}' ),
		bws_fixture_gb_row( 'F9b.7 control for F9b.5: the plain site attempt, which never broke (-> the same number)', '{{try_phone src:site|key:org_phone}}' ),
		bws_fixture_gb_row( 'F9b.8 the ambient-TERM attempt is a branch off the root-only kind now, not a src:current test (-> the ambient page title here; Sales on /department/sales/)', '{{try_title}}' ),
		bws_fixture_gb_row( 'F9b.9 per-arm link-wrap survived the merge into one emit (-> the term title, LINKED)', '{{try_text srcTermIn:department|use:title|linkTo:term}}' ),
		bws_fixture_gb_row( 'F9b.11 a repeater-row source is still refused, and the next attempt still runs (-> Captain)', '{{try_text A:src(entries,team_members);use(key);key(name)|B:key(role)}}' ),
		bws_fixture_gb_row( 'F9b.12 an inexpressible chain still skips at the SEAM, which #103 did not touch (-> Captain)', '{{try_text A:src(refs,related_staff;terms,department);use(title)|B:key(role)}}' ),
		// F9b.13 IS NOT HERE, and the omission is the stated exception: the I6 parity
		// defect needs an AUTHOR ARCHIVE as ambient context, which has no page
		// content to hang a fixture row on — the same exception text T4 takes for a
		// term archive. It is render-tag-only, and the matrix says so.
	) );

	// F9c — MODE 2b, the flat ACF repeater row, which had NO rendered coverage on any
	// tag family until #103. It matters here for two reasons and the second is the
	// bigger one.
	//
	// (1) `meta_row` names ONE resolved-source kind and a slot can arrive at it two
	// ways. Off the WIRE (`src(entries,…)`) the author asked for repeater rows, and
	// no try_ arm assembles those — refuse, {{table}} owns it. Off the AMBIENT
	// CONTEXT (silent wire, standing inside a repeater row) the author asked for
	// "here", and refusing would blank an ordinary tag — so it continues to the post
	// arm, resolves no id, and the core fn reads $loop_item[$key] instead. F9c.4 is
	// the row where both meet on one page and stay apart.
	//
	// (2) The gate that keeps (1) working is `'current' === $last_src` — a literal
	// FLAT-TOKEN test, of exactly the kind #104 replaces when the seam starts handing
	// a slot's source on as chain wire. These rows exist so that change has something
	// to break. Written after it, they would be worth much less.
	//
	// NOTHING ELSE REACHES THIS. A WP_Query loop's rows are WP_Post objects, so a post
	// id always resolves and the branch never runs; `render-tag --loop-item` takes a
	// post id by construction. The fixture builder's docblock carries the detail.
	$sections[] = bws_fixture_gb_section( 'Fold F9c - MODE 2b: the flat ACF repeater row (loop fallthrough)', array(
		bws_fixture_gb_post_meta_loop( 'team_members', 'F9c.1 base tag in a repeater row (-> Alice Adams / Bob Brown, one per row): {{text key:name}}', 'f9c1-base' ),
		bws_fixture_gb_post_meta_loop( 'team_members', 'F9c.2 the try_ twin, which is the loop fallthrough (-> the SAME two names): {{try_text A:key(name)}}', 'f9c2-try' ),
		bws_fixture_gb_post_meta_loop( 'team_members', 'F9c.3 the attempt chain still advances inside a row: slot 1 misses, slot 2 hits (-> Engineering / Operations): {{try_text A:key(nope)|B:key(role)}}', 'f9c3-advance' ),
		// The row that proves the two arrival routes do not collide. Slot 1 names a
		// repeater source ON THE WIRE while STANDING IN a repeater row: it is refused
		// as a chain kind, and slot 2's ambient read still takes the fallthrough.
		bws_fixture_gb_post_meta_loop( 'team_members', 'F9c.4 a WIRE-stated repeater source is refused even from inside a repeater row, and the ambient attempt still resolves (-> Engineering / Operations): {{try_text A:src(entries,team_members);use(key);key(name)|B:key(role)}}', 'f9c4-axes' ),
		bws_fixture_gb_post_meta_loop( 'team_members', 'F9c.5 a sub-field that is a relationship still hops out of the row (-> Jane Partner / empty; row 2 leaves lead_ref blank): {{try_text A:src(refs,lead_ref);use(title)}}', 'f9c5-hop' ),
	) );

	// F10 INVERTED at #104: the seam stopped re-spelling a slot's chain as a flat
	// triple, so a multi-step slot source RESOLVES. Read the rows in PAIRS - the
	// BASE twin is the property, not decoration: identical wire in a slot and on a
	// base tag must render identically ([I16]), and a slot row on its own cannot
	// tell "resolved correctly" from "resolved plausibly".
	//
	// F10.1/2 need portal_visibility and NOT department: jane and tom carry no
	// department terms, so that taxonomy makes the row empty either way and asserts
	// nothing. That exact vacuity is what the old F10.3 negative control was for.
	//
	// F10.3/4 still print what they printed as SKIPS, and that is expected: a skip
	// and an empty read are indistinguishable on the front end. slot-fold-test.php
	// §P13.5 is what says the chain is now run rather than refused.
	$sections[] = bws_fixture_gb_section( 'Fold F10 - a MULTI-STEP slot source resolves (#104; pairs must match)', array(
		bws_fixture_gb_row( 'F10.1 join slot, two hops (-> All Users, All Users; printed NOTHING before #104)', '{{join A:src(refs,related_staff;terms,portal_visibility);use(title)}}' ),
		bws_fixture_gb_row( 'F10.1 BASE twin - the identity under test (-> same)', '{{text src:refs,related_staff;terms,portal_visibility|use:title}}' ),
		bws_fixture_gb_row( 'F10.2 try_ slot, same chain; slot 2 must NOT run because slot 1 resolved (-> All Users, All Users)', '{{try_text A:src(refs,related_staff;terms,portal_visibility);use(title)|B:key(role)}}' ),
		bws_fixture_gb_row( 'F10.3 a SECOND ref hop now runs and finds nothing (staff carry no related_staff of their own), so slot 2 answers (-> Captain, as it did when slot 1 was SKIPPED)', '{{join A:src(refs,related_staff;refs,related_staff);use(title)|B:key(role)}}' ),
		bws_fixture_gb_row( 'F10.4 entries resolves and renders nothing - no join/try_ arm assembles a repeater row, which is why it is on no step offer (-> Captain)', '{{join A:src(entries,team_members);use(key);key(name)|B:key(role)}}' ),
		bws_fixture_gb_empty_row( 'F10.4 BASE twin - the refusal belongs to the CONTAINER, not to the slot spelling, so the plain tag is empty too (-> EMPTY; F9.5 is the same fact stated on the base tag)', '{{text src:entries,team_members|use:key|key:name}}' ),
		bws_fixture_gb_row( 'F10.5 ref+term on department - expressible before #104 too, empty then and now (-> Captain)', '{{join A:src(refs,related_staff;terms,department);use(title)|B:key(role)}}' ),
		bws_fixture_gb_row( 'F10.6 a SITE root never takes the legacy term step, on the slot as on the base tag (-> the org number)', '{{join src:site|srcTermIn:department|key:org_phone}}' ),
		// F10.6b - the shape #104's first draft deleted. The old editor authored this pair
		// directly: leave slot 2's source alone, pick a different taxonomy. An inherited hop
		// is a DEFAULT, so slot 2 REPLACES it; appending hops off a TERM input, which has no
		// post to read, and the slot vanishes from the join. This page carries no department
		// terms, hence the pair reads on /matrix-terms-valid/ - the rows below are the
		// FOLDED twin, which is the one that renders here.
		bws_fixture_gb_row( 'F10.6b an inherited hop is REPLACED by the slot own hop, not followed by it (-> Sales, Support, All Users)', '{{join A:src(terms,department);use(title)|B:src(same;terms,portal_visibility);use(title)}}' ),
		bws_fixture_gb_row( 'F10.6c the shape the rule exists for: a rooted BASE plus two different taxonomies, slot 2 inheriting (-> All Users; slot 2 keeps the inherited base and replaces only the taxonomy, so the wire needs no duplicate of the base)', '{{join src:ref|ref:related_staff|srcTermIn:department|use:title|2-src:same|2-srcTermIn:portal_visibility|2-use:title}}' ),
		bws_fixture_gb_row( 'F10.6d the row that BOUNDS the rule: refs is post-to-post, so an inherited ref hop must NOT be dropped (-> Jane Partner, Tom Associate, Jane Partner; slot B equals the base twin below)', '{{join A:src(refs,related_staff);use(title)|B:src(same;refs,reports_to);use(title)}}' ),
		bws_fixture_gb_row( 'F10.6d BASE twin for slot B (-> Jane Partner)', '{{text src:refs,related_staff;refs,reports_to|use:title}}' ),
		bws_fixture_gb_row( 'F10.6b LEGACY twin - the pair the old editor authored (-> Sales, All Users; ONE department term, because flat wire bounds at 1 and chain wire does not - the SLOT, not the count, is what must match)', '{{join srcTermIn:department|use:title|2-src:same|2-srcTermIn:portal_visibility|2-use:title}}' ),
		bws_fixture_gb_row( 'F10.6 BASE twin (-> the same number)', '{{phone src:site|srcTermIn:department|key:org_phone}}' ),
		bws_fixture_gb_empty_row( 'F10.7 hand-written chain wire SAYS the term step, so it keeps it and resolves nothing (-> EMPTY; the deliberate contrast to F10.6)', '{{join A:src(site;terms,department);key(org_phone)}}' ),
		bws_fixture_gb_empty_row( 'F10.7 BASE twin (-> EMPTY too; a term step needs a post input)', '{{phone src:site;terms,department|key:org_phone}}' ),
	) );

	// F9d - the one STATED behaviour change #104 makes to wire that already
	// existed. A slot's per-step limits used to collapse into the ONE number the
	// flat triple could hold (every hop unbounded, items sliced at the end); they
	// ride the wire now and bound each hop, as they do on the base tag. Reachable
	// ONLY where a slot fans TWICE, which is refs+terms - the one two-fanning-step
	// shape the flat triple could express.
	$sections[] = bws_fixture_gb_section( 'Fold F9d - per-step bounds now reach the ENGINE (stated change, #104)', array(
		bws_fixture_gb_row( 'F9d.1 flat ref+term with an explicit limit: terms of the FIRST ref-d post, first 2 (-> All Users; was terms of ALL ref-d posts, first 2 = All Users, All Users)', '{{join src:ref|ref:related_staff|srcTermIn:portal_visibility|use:title|limit:2}}' ),
		bws_fixture_gb_row( 'F9d.2 the MIGRATED twin of F9d.1 - the wire that says what now happens (-> identical to F9d.1; that agreement is the property)', '{{join A:src(refs,related_staff,limit[1];terms,portal_visibility,limit[2]);use(title)}}' ),
		bws_fixture_gb_row( 'F9d.3 the compatibility FLOOR: ONE fanning step, so per-hop and total coincide (-> Jane Partner, Tom Associate; unchanged)', '{{join src:ref|ref:related_staff|use:title|limit:0}}' ),
	) );

	// F14b - the INERT-CHAIN warnings (#105). EDITOR-ONLY evidence, and doubly so:
	// an inert chain is indistinguishable from an ordinary empty read in output,
	// AND the preview only exists where the tag resolves EMPTY.
	//
	// THE FIELD KEY IS `bio`, WHICH NOTHING ON THIS PAGE CARRIES, AND THAT IS
	// FORCED. A base tag with an unresolvable source does NOT render nothing - it
	// renders a plausible WRONG value (measured 2026-08-17: an unidentifiable
	// source token reads the AMBIENT entity, #75; an unknown STEP slug is dropped
	// and the chain's PREFIX is read, #109). Both are [I15] leaks. A row keyed on a field the page HAS would
	// therefore render a plausible wrong value and show no preview at all, which
	// is the opposite of what these rows are for. Once the leak is fixed the key
	// can go back to a real one and these rows keep working.
	$sections[] = bws_fixture_gb_section( 'Fold F14b - INERT chain warnings (EDITOR evidence; all resolve empty)', array(
		bws_fixture_gb_empty_row( 'F14.18 unregistered ROOT: was invisible before #105 - previewed exactly like a bare tag (-> EMPTY; preview must read [warn Unknown source "currnet"])', '{{text src:currnet|use:key|key:bio}}' ),
		bws_fixture_gb_empty_row( 'F14.19 unknown STEP behind a good one: only a WALK sees it - the kind here is blank, and mid-chain it reads post off the TAIL while nothing resolves (-> EMPTY; preview [warn Unknown source step "bogus"])', '{{text src:refs,related_staff;bogus,x|use:key|key:bio}}' ),
		bws_fixture_gb_empty_row( 'F14.20 RETIRED token: registered, so not unknown - its own sentence, and the only warning with a named repair (-> EMPTY; preview [warn Source "related_post" is no longer supported - run the Tag Converter])', '{{text src:related_post|use:key|key:bio}}' ),
		bws_fixture_gb_empty_row( 'F14.21 slot form: the LETTER names the slot and the detail is KEPT while only one slot has a problem (-> EMPTY; preview [warn Join: A unknown source "bogus"] - slot B is fine and says nothing)', '{{join A:src(bogus,x);key(bio)|B:key(bio)}}' ),
		bws_fixture_gb_empty_row( 'F14.22 two slots, DIFFERENT problems: the collapse rule drops the detail (-> EMPTY; preview [warn Try: A, B misconfigured])', '{{try_text A:src(bogus,x);use(key);key(bio)|B:src(currnet);use(key);key(bio)}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Fold F12 - ref-hop RETURN FORMATS (blueprint v6; all three must agree)', array(
		bws_fixture_gb_row( 'F12.1 relationship + return_format id (-> both numbers)', '{{phone src:refs,related_staff|key:main_line|limit:0}}' ),
		bws_fixture_gb_row( 'F12.2 relationship + object = WP_Post[] (-> IDENTICAL to F12.1)', '{{phone src:refs,related_staff_obj|key:main_line|limit:0}}' ),
		bws_fixture_gb_row( 'F12.3 post_object + object = ONE WP_Post, the non-array wrap (-> (555) 200-3000 alone)', '{{phone src:refs,lead_staff_obj|key:main_line|limit:0}}' ),
		bws_fixture_gb_row( 'F12.4 the format is invisible to the flat spelling too (-> identical to F12.2)', '{{phone src:ref|ref:related_staff_obj|key:main_line|limit:0}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Fold F13 - TAG-level axes must survive the fold', array(
		bws_fixture_gb_row( 'F13.1 tag-level key on try_datetime_* is NOT slot 1\'s read (-> May 1, 2030 10:00 AM)', '{{try_datetime_single A:src(refs,related_staff)|B:src(current)|key:event_datetime}}' ),
		bws_fixture_gb_row( 'F13.2 tag-level limit on a try_ list template is the TAG limit (-> (555) 200-3000)', '{{try_phone A:src(refs,related_staff);key(main_line)|B:src(current);key(main_line)|limit:2}}' ),
		bws_fixture_gb_row( 'F13.3 legacy twin of F13.2 (-> same output; that agreement IS the property)', '{{try_phone src:ref|ref:related_staff|key:main_line|2-key:main_line|limit:2}}' ),
	) );

	// F15/F16 — the first-usable slice (ADR 0007, slice A). Rows:
	// tools/test/fold-test-matrix.md §F15/§F16. Post-route rows here; the term-route
	// rows live in the term-hop builder (they need the department terms as fixture).
	// Needs blueprint v12 (feature_image on Tom alone — Jane deliberately has none).
	$sections[] = bws_fixture_gb_section( 'Fold F15 - collapsing tags take the first USABLE result (ADR 0007)', array(
		bws_fixture_gb_row( 'F15.3 POST route first-usable: Jane has no honorific, Tom does (-> Dr.; was EMPTY - first source read once)', '{{content src:refs,related_staff|use:key|key:name_honorific}}' ),
		bws_fixture_gb_row( 'F15.4 the headline case - the picture from whichever related post has one (-> fixture photo URL; was EMPTY)', '{{image src:refs,related_staff|use:key|key:feature_image|as:url,full}}' ),
		bws_fixture_gb_row( 'F15.5 first target HAS a value - unchanged, the fix widens the search, never reorders it (-> Jane)', '{{content src:refs,related_staff|use:key|key:name_first}}' ),
		bws_fixture_gb_row( 'F15.2 an INTERMEDIATE limit is ignored too: past Jane (no reports_to) to Tom, whose reports_to is Jane (-> Jane staff URL; was EMPTY)', '{{permalink src:refs,related_staff,limit(1);refs,reports_to}}' ),
		bws_fixture_gb_row( 'F15.6 try_ inherits from the base template, no separate arm (-> Dr.)', '{{try_content A:src(refs,related_staff);use(key);key(name_honorific)}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'Fold F16 - the site branch is taken by RESOLVED KIND (email/phone)', array(
		bws_fixture_gb_row( 'F16.1 flat spelling - the previously-working one, must not move (-> site email, decoded info@example.test)', '{{email src:site|key:organization_email}}' ),
		bws_fixture_gb_row( 'F16.2 DECORATED root-only site chain - the compare-miss shape that read the AMBIENT entity before (-> same as F16.1 decoded)', '{{email src:site,limit(2)|key:organization_email}}' ),
		bws_fixture_gb_row( 'F16.3 flat spelling (-> (987) 555-0000)', '{{phone src:site|key:org_phone}}' ),
		bws_fixture_gb_row( 'F16.4 decorated twin (-> same as F16.3)', '{{phone src:site,limit(2)|key:org_phone}}' ),
		bws_fixture_gb_row( 'F16.5 an attempt whose source cannot resolve is SKIPPED - B renders, and WHICH attempt won is the assertion (-> site email)', '{{try_email A:src(fixture_scoped);key(contact_email)|B:src(site);key(organization_email)}}' ),
		bws_fixture_gb_row( 'F16.6 the decorated site SLOT takes the site arm read - the [I15] wrong-entity leak, closed (-> site phone)', '{{try_phone A:src(site,limit[2]);key(org_phone)}}' ),
	) );

	// {{table}} structured-output (1.17.0, feat/table-tag). team_members repeater
	// on this page (name/description/role, 2 rows: Alice/Bob) → a <table>. Hosted
	// in a DIV (block-host row), NOT a <p> — {{table}} emits whole-table HTML.
	// TB1 scalar columns + headers; TB2 header-less; TB3 ref-title column
	// (lead_ref sub-field, none seeded → the use:title path reads empty, proving
	// it degrades to a blank cell not a crash). TB4 caption + responsive wrapper
	// (W3C data-table a11y): <caption id> + <div.bws-table-wrap role=region
	// tabindex=0 aria-labelledby=that-id>.
	$sections[] = bws_fixture_gb_section( 'Table TB - repeater to table', array(
		bws_fixture_gb_block_host_row( 'TB1 (3 scalar cols + headers -> 2-row table: Alice/Engineering/Founding partner, Bob/Operations/Support lead)', '{{table key:team_members|1-label:Name|1-key:name|2-label:Role|2-key:role|3-label:Note|3-key:description}}' ),
		bws_fixture_gb_block_host_row( 'TB2 (no labels -> header-less table, no thead row)', '{{table key:team_members|1-key:name|2-key:role}}' ),
		bws_fixture_gb_block_host_row( 'TB3 (use:title ref-hop col -> row1 Lead = Jane Partner, row2 blank lead_ref = empty cell)', '{{table key:team_members|1-label:Name|1-key:name|2-label:Lead|2-use:title|2-key:lead_ref}}' ),
		bws_fixture_gb_block_host_row( 'TB4 (caption "Our Team" -> <caption id> + wrapper aria-labelledby that id, role=region tabindex=0)', '{{table key:team_members|caption:Our Team|1-label:Name|1-key:name|2-label:Role|2-key:role}}' ),
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
		// `fallback`, NOT `fallback_text` — renamed 1.16.0 (FW-50). This row carried
		// the dead key and so rendered EMPTY where the matrix claimed the em dash.
		bws_fixture_gb_row( 'J3 (jane: both slots empty -> em dash fallback; tom: Jr., PhD)', '{{join key:name_generation|2-key:name_credential|fallback:—}}' ),
		bws_fixture_gb_row( 'J3b (same without a fallback -> empty on jane, so GB hides the block)', '{{join key:name_generation|2-key:name_credential}}' ),
	) );

	// Folded twins of the name rows above (FW-56/57, fold-test-matrix.md §F1).
	// Same fixture data, so each row must equal its legacy twin on BOTH staff
	// singles — jane collapsed, tom dense. Pairs stay adjacent for the eyeball.
	$sections[] = bws_fixture_gb_section( 'Fold F1 - join folded == legacy (name rows)', array(
		bws_fixture_gb_row( 'F1.1 legacy (jane: Jane, Johnson / tom: Tom, Smith)', '{{join key:name_first|2-key:name_last}}' ),
		bws_fixture_gb_row( 'F1.1 folded (-> same)', '{{join A:key(name_first)|B:key(name_last)}}' ),
		bws_fixture_gb_row( 'F1.8 legacy template mode (jane: Jane (Johnson) / tom: Tom (Smith))', '{{join mode:template|format:%1 (%2)|key:name_first|2-key:name_last}}' ),
		bws_fixture_gb_row( 'F1.8 folded, canonical %A tokens (-> same)', '{{join mode:template|format:%A (%B)|A:key(name_first)|B:key(name_last)}}' ),
		bws_fixture_gb_row( 'F1.8b folded wire, DIGIT tokens still read (-> same)', '{{join mode:template|format:%1 (%2)|A:key(name_first)|B:key(name_last)}}' ),
		bws_fixture_gb_row( 'F1.8c %%B is a LITERAL, so slot 2 is never read (jane: Jane (%B) / tom: Tom (%B))', '{{join mode:template|format:%A (%%B)|A:key(name_first)}}' ),
		bws_fixture_gb_row( 'F1.10 legacy fallback (jane: em dash / tom: Jr., PhD)', '{{join key:name_generation|2-key:name_credential|fallback:—}}' ),
		bws_fixture_gb_row( 'F1.10 folded (-> same)', '{{join A:key(name_generation)|B:key(name_credential)|fallback:—}}' ),
		bws_fixture_gb_row( 'F1.9 folded 7-slot full name (jane: Jane Johnson / tom: Dr. Tom M. Smith Jr., PhD, USN (Ret.))', '{{join mode:template|format:%A %B %C. %D %E, %F, %G|A:key(name_honorific)|B:key(name_first)|C:key(name_middle_initial)|D:key(name_last)|E:key(name_generation)|F:key(name_credential)|G:key(name_service)}}' ),
		bws_fixture_gb_row( 'F5.7 try_permalink, no-read shape (-> this staff single\'s URL)', '{{try_permalink A:src(current)|B:src(site)}}' ),
		bws_fixture_gb_row( 'N6 try_text fallback on empty slots (jane: None / tom: Jr.)', '{{try_text A:key(name_generation)|B:key(name_credential)|fallback:None}}' ),
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
	// F7a (#60) — a slot's own source spelling decides its own limit, exactly as a
	// base tag's does. The base tag is the reference; the two containers must match
	// it; the flat row is what stops the set being vacuous. Only meaningful where the
	// page actually carries department terms, which is why it lives here.
	. "

" . bws_fixture_gb_section( 'Fold F7a - a slot spelling decides its own limit (#60)', array(
		bws_fixture_gb_row( 'F7a.1 BASE tag, chain-spelled - the reference (-> every department term)', '{{text src:terms,department|use:title}}' ),
		bws_fixture_gb_row( 'F7a.2 try_ slot, same spelling - must MATCH F7a.1 (was one term)', '{{try_text A:src(terms,department);use(title)}}' ),
		bws_fixture_gb_row( 'F7a.3 join slot, same spelling - must MATCH F7a.1', '{{join A:src(terms,department);use(title)}}' ),
		bws_fixture_gb_row( 'F7a.5 the FLAT spelling still bounds at 1 - makes the three above non-vacuous (-> ONE term)', '{{try_text srcTermIn:department|use:title}}' ),
		bws_fixture_gb_row( 'F7a.7 MIGRATED twin of F7a.5 - limit[1] states what the flat spelling implied (-> same as F7a.5)', '{{try_text A:src(terms,department,limit[1]);use(title)}}' ),
		bws_fixture_gb_row( 'F7a.8 legacy tag-level limit:2 (-> two terms)', '{{try_text srcTermIn:department|use:title|limit:2}}' ),
		bws_fixture_gb_row( 'F7b.1 MIGRATED twin - the number lands on the slot own fanning step and the tag-level key is RETIRED (-> same as F7a.8)', '{{try_text A:src(terms,department,limit[2]);use(title)}}' ),
		// F7b (#61) - the shape the ticket names: slots ALREADY folded, only the
		// retiring key left. It carries no legacy slot key, so it reaches the entry
		// only because `limit` is on the MATCH surface.
		bws_fixture_gb_row( 'F7b.2 already-folded slot beside the retiring key - pre-#61 storage (-> two terms)', '{{try_text A:src(terms,department);use(title)|limit:2}}' ),
		bws_fixture_gb_row( 'F7b.2b MIGRATED twin - the number moved onto the slot own last fanning step (-> same as F7b.2)', '{{try_text A:src(terms,department,limit[2]);use(title)}}' ),
		bws_fixture_gb_row( 'F7b.5 join CONTRAST - a bare limit is slot 1 own axis, never pushed into a folded slot, and join has no tag-level fallback to read it with (-> every term)', '{{join A:src(terms,department);use(title)|limit:3}}' ),
		bws_fixture_gb_row( 'F7b.7 join inheriting slot - inherits the HOP but not the BOUND, so ONE blurb after the two titles', '{{join A:src(terms,department,limit[2]);use(title)|B:src(same);key(blurb)}}' ),
		// F7d (#74) - `src(same)` inherits the term hop. Before the fix slot B inherited
		// an empty source (a leading `terms` step leaves `src` unset) and read the PAGE,
		// which is why .2 rendered the page title and looked like a working tag.
		bws_fixture_gb_row( 'F7d.1 join inheriting slot reads the TERM phone, not the page (-> two titles then (987) 333-4444)', '{{join A:src(terms,department,limit[2]);use(title)|B:src(same);key(phone)}}' ),
		bws_fixture_gb_row( 'F7d.2 same-read inheriting slot reads the TERM title, not the page title (-> Sales, Support, Sales)', '{{join A:src(terms,department,limit[2]);use(title)|B:src(same);use(same)}}' ),
		bws_fixture_gb_row( 'F7d.2b LEGACY twin of F7d.2 - the fix is uniform across eras (-> same as F7d.2)', '{{join srcTermIn:department|use:title|limit:2|2-src:same|2-use:same}}' ),
		bws_fixture_gb_row( 'F7d.3 a slot stating its OWN root does not acquire the carried hop (-> two titles only, page has no phone)', '{{join A:src(terms,department,limit[2]);use(title)|B:src(current);key(phone)}}' ),
		bws_fixture_gb_row( 'F7d.4 an inherited hop is a DEFAULT - the slot own terms step REPLACES it (-> Sales, Support, Warehouse)', '{{join A:src(terms,department);use(title)|B:src(same;terms,office);use(title)}}' ),
		bws_fixture_gb_row( 'F7a.9 join legacy (-> ONE term)', '{{join srcTermIn:department|use:title}}' ),
		bws_fixture_gb_row( 'F7a.9b join MIGRATED twin (-> same as F7a.9)', '{{join A:src(terms,department,limit[1]);use(title)}}' ),
		bws_fixture_gb_row( 'F7a.10 join legacy limit:2 - join owns limit PER SLOT, so it is slot 1 own (-> two terms)', '{{join srcTermIn:department|use:title|limit:2}}' ),
		bws_fixture_gb_row( 'F7a.10b join MIGRATED twin - the 2 lands on the slot own fanning step (-> same as F7a.10)', '{{join A:src(terms,department,limit[2]);use(title)}}' ),
		bws_fixture_gb_row( 'F7a.11 an explicit legacy limit:0 KEEPS its carrier - unmigrated wire takes the flat default (-> every term)', '{{try_text srcTermIn:department|use:title|limit:0}}' ),
		bws_fixture_gb_row( 'F7b.4 MIGRATED twin - an explicit unlimited moves onto the step like any other number (-> same as F7a.11)', '{{try_text A:src(terms,department,limit[0]);use(title)}}' ),
		// The LINK GATE half (limit-default-test-matrix.md L4a). It is count-based, so a
		// slot that starts returning several values stops being wrappable - eyeball the
		// anchors, not just the text.
		bws_fixture_gb_row( 'L4a.1 flat slot, unset - ONE term, and it IS a link', '{{try_text srcTermIn:department|use:title|linkTo:permalink}}' ),
		bws_fixture_gb_row( 'L4a.2 chain slot, unset - every term, and NO link (multi-value is not wrappable)', '{{try_text A:src(terms,department);use(title)|linkTo:permalink}}' ),
		bws_fixture_gb_row( 'L4a.3 MIGRATED twin of L4a.1 - ONE term, link back (-> same as L4a.1)', '{{try_text A:src(terms,department,limit[1]);use(title)|linkTo:permalink}}' ),
	) )
	// datetime matrix D4 (#30) — srcTermIn list rows. The page's assigned terms
	// make the case (valid / mixed-junk / all-junk), same as the phone rows.
	. "\n\n" . bws_fixture_gb_section( 'Datetime D4 - srcTermIn list (#30)', array(
		bws_fixture_gb_row( 'D4.1', '{{datetime_single srcTermIn:department|key:event_date|limit:5}}' ),
		bws_fixture_gb_row( 'D4.2', '{{datetime_single srcTermIn:department|key:event_date}}' ),
		bws_fixture_gb_row( 'D4.3', '{{datetime_single srcTermIn:department|key:event_date|limit:5|sep: / }}' ),
		bws_fixture_gb_row( 'D4.4', '{{datetime_single srcTermIn:department|key:event_date|limit:5|fallback:Dates TBA}}' ),
		bws_fixture_gb_row( 'D4.5', '{{datetime_range srcTermIn:department|startKey:event_date|limit:5|sep:; }}' ),
	) )
	// F15 TERM route — the first-usable slice (ADR 0007). Rows:
	// tools/test/fold-test-matrix.md §F15. Expectations stated for
	// /matrix-terms-mixed/ (Sales, Support, Warehouse alphabetically; only
	// Warehouse carries `charter`, blueprint v12) — on the valid page the walk has
	// no Warehouse term and the rows read EMPTY, which the split labels survive.
	. "

" . bws_fixture_gb_section( 'Fold F15 - collapsing TERM route: first USABLE result (ADR 0007; expectations on /matrix-terms-mixed/)', array(
		bws_fixture_gb_empty_row( 'F15.1 the 1.17.0 stamped limit(1) is IGNORED - two empty terms searched past (-> mixed: Warehouse charter...; was EMPTY; valid page: EMPTY, no warehouse term)', '{{content src:terms,department,limit(1)|use:key|key:charter}}' ),
		bws_fixture_gb_empty_row( 'F15.1b a limit the author TYPED is ignored on the same terms - one rule (-> same as F15.1)', '{{content src:terms,department,limit(2)|use:key|key:charter}}' ),
		bws_fixture_gb_empty_row( 'F15.1c the unlimited twin - F15.1/.1b must equal THIS row, not merely be non-empty', '{{content src:terms,department|use:key|key:charter}}' ),
		bws_fixture_gb_empty_row( 'F15.7 DISCLOSURE, not a defect: list-mode {{text}} still slices before it reads - NOT fixed this release (-> EMPTY everywhere)', '{{text src:terms,department,limit(1)|use:key|key:charter}}' ),
	) );
}

/**
 * Page content: matrix-content (page-matrix-content) — content-test-matrix.md.
 *
 * Its own page because the property under test is WHICH ENTITY the read lands
 * on, and a hopped {{content}} renders a whole other page's blocks inline. The
 * matrix pages carry values that would then appear twice from two entities on
 * one screen; here the page's own field values are deliberately distinct from
 * the staff singles' so ambient-vs-hopped is legible at a glance (#58).
 *
 * A BARE {{content}} row is absent by design: it would recurse into the page
 * being rendered, which the ContentProcessor stack guard answers with '' — the
 * recursion guard's own coverage, not this page's.
 */
function bws_fixture_page_content_matrix_content() {
	$sections = array();

	// CT1-CT3 — the hop reads the TARGET post, inner tags included (#58). Jane's
	// content is the join NAME rows off her own name_* fields, so her rows read
	// `Jane, Johnson` here; before the context swap they read this page's values.
	$sections[] = bws_fixture_gb_section( 'Content CT1-CT3 - a hopped read lands on the TARGET entity (#58)', array(
		bws_fixture_gb_row( "CT1 plain hop (-> jane's blocks, and her J1 row reads Jane, Johnson - NOT this page's values)", '{{content src:ref|ref:related_staff}}' ),
		bws_fixture_gb_row( "CT2 chain-spelled twin (-> same as CT1)", '{{content src:refs,related_staff}}' ),
		bws_fixture_gb_row( "CT3 excerpt hop (-> jane's content trimmed, her values, and the read-more link points at /staff/jane-partner/)", '{{content src:ref|ref:related_staff|use:excerpt}}' ),
	) );

	// CT4-CT5 — the other two arms of the same callback. Neither renders blocks,
	// so neither can drift on entity the way CT1-CT3 did; they are here so the
	// content family's arms are covered in one place.
	$sections[] = bws_fixture_gb_section( 'Content CT4-CT5 - the field-read arms', array(
		bws_fixture_gb_row( "CT4 post arm, use:key (-> jane's main line, (555) 200-3000)", '{{content src:ref|ref:related_staff|use:key|key:main_line}}' ),
		bws_fixture_gb_row( 'CT5 term arm, use:key - support carries no blurb, so the walk skips to sales (-> the Sales blurb)', '{{content srcTermIn:department|use:key|key:blurb}}' ),
	) );

	// CT6 — the ambient read is the contrast that makes CT1 non-vacuous: same tag,
	// no hop, so it must show THIS page's own values.
	$sections[] = bws_fixture_gb_section( 'Content CT6 - the ambient contrast', array(
		bws_fixture_gb_row( "CT6 no hop, this page's own field (-> (321) 555-0100, this page's line, NOT jane's)", '{{text key:main_line}}' ),
	) );

	return implode( "\n\n", $sections );
}

/**
 * matrix-fixture-roots — the external-source contract's corpus page (#85).
 *
 * Three things live here, and they read against each other on purpose:
 *
 *  FR1/FR2  the two REGISTERED ROOTS (class route `fixture`, filter route `fixture_alt`)
 *           on a base tag and inside a folded slot. Open any of these in the editor to
 *           see both rows in the Source control — the enum is the other half of the
 *           assertion, and only the editor shows it.
 *  FR3      the fixture MODIFIER family in the six shapes #84's transform maps. This is
 *           the reseedable corpus the converter runs against (#86); after a run the rows
 *           are base tags, and a reseed puts them back.
 *  FR4      each modifier shape beside the base-tag wire it must become, so the property
 *           the migration promises — same bytes out — is eyeballable BEFORE any converter
 *           exists to be trusted.
 *
 * The page carries its own ambient values (role, main_line, Support term) that are unlike
 * the root's (Sales) and unlike the hop target's (Warehouse). A rooted row landing on the
 * wrong entity therefore prints the wrong words rather than the right ones.
 *
 * Limits are stated EXPLICITLY on both sides of every FR4 pair. Since 1.17.0 an unset
 * limit is resolved by the source SPELLING — flat wire bounds at 1, chain wire does not —
 * so a bare-versus-bare comparison compares two different quantities and diverges by
 * design (the same reason verify.php pins limit:1 on its chain-vs-flat check).
 */
function bws_fixture_page_content_matrix_fixture_roots() {
	$sections = array();

	$sections[] = bws_fixture_gb_section( 'FR1 - registered roots on a base tag', array(
		bws_fixture_gb_row( 'FR1.1 class-route root, bare read (-> Fixture Root Role)', '{{text src:fixture|key:role}}' ),
		bws_fixture_gb_row( 'FR1.2 the ambient contrast, same key, no root (-> Ambient Page Role)', '{{text key:role}}' ),
		bws_fixture_gb_row( 'FR1.3 filter-route root, bare read (-> Chatham, sample-event\'s venue_city)', '{{text src:fixture_alt|key:venue_city}}' ),
		bws_fixture_gb_row( 'FR1.4 root then a relationship step (-> Fixture Ref Target)', '{{text src:fixture;refs,related_staff|use:title|limit:1}}' ),
		bws_fixture_gb_row( 'FR1.5 root then a taxonomy step (-> Sales, the ROOT\'s term, NOT this page\'s Support)', '{{text src:fixture;terms,department|use:title|limit:1}}' ),
		bws_fixture_gb_row( 'FR1.6 root, relationship, taxonomy (-> Warehouse, the hop TARGET\'s term)', '{{text src:fixture;refs,related_staff;terms,department|use:title|limit:1}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'FR2 - registered roots inside a folded slot', array(
		bws_fixture_gb_row( 'FR2.1 try_ attempt rooted at the class route (-> Fixture Root Role)', '{{try_text A:src(fixture);use(key);key(role)}}' ),
		bws_fixture_gb_row( 'FR2.2 try_ attempt rooted at the filter route (-> Chatham)', '{{try_text A:src(fixture_alt);use(key);key(venue_city)}}' ),
		bws_fixture_gb_row( 'FR2.3 try_ falls PAST an empty rooted attempt to the ambient one (-> Ambient Page Role)', '{{try_text A:src(fixture);use(key);key(no_such_field)|B:use(key);key(role)}}' ),
		bws_fixture_gb_row( 'FR2.4 join mixes both roots in one string (-> Fixture Root Role of Chatham)', '{{join mode:template|A:src(fixture);use(key);key(role)|B:src(fixture_alt);use(key);key(venue_city)|format:%A of %B}}' ),
	) );

	// EVERY ROW READS A FIELD KEY, and none of them reads `use:title`. That is not a
	// stylistic choice: a modifier template's text core reads `key` and ignores `use`
	// entirely (bws_post_custom_text_core), so `use:title` renders empty on `fixture_`,
	// `term_` and `view_` alike — a control that is offered and does nothing (issue #88;
	// PRE-EXISTING, not this fixture's). Rows here must read something the
	// modifier family actually renders, or an equivalence pair would compare '' to ''
	// and pass whatever the migration did.
	// FR2b (#112) - THE NON-VACUITY HALF of the fold matrix's F11a.4.
	//
	// `fixture_scoped` refuses on /matrix-post-meta/, where F11a.4 reads it and expects
	// nothing. A source that could never resolve anywhere would satisfy that row while
	// asserting nothing at all, so the row that makes it mean something is HERE, on the
	// one page the root is scoped to. Read the two together: same tag, two pages, and the
	// difference is the whole behaviour under test.
	$sections[] = bws_fixture_gb_section( 'FR2b - the SCOPE-BOUND root, on its own page (non-vacuity for fold F11a.4)', array(
		bws_fixture_gb_row( 'FR2b.1 scoped root resolves HERE (-> Fixture Root Role). The same tag on /matrix-post-meta/ is fold F11a.4 and renders NOTHING', '{{text src:fixture_scoped|use:key|key:role}}' ),
		bws_fixture_gb_row( 'FR2b.2 the ambient contrast, same key, no root (-> Ambient Page Role)', '{{text key:role}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'FR3 - fixture_ MODIFIER corpus (the migration\'s six shapes, pre-conversion)', array(
		bws_fixture_gb_row( 'FR3.1 bare, no source stated (-> Fixture Root Role)', '{{fixture_text key:role}}' ),
		bws_fixture_gb_row( 'FR3.2 src:current — on a modifier that named ITS entity, so same as FR3.1 (-> Fixture Root Role)', '{{fixture_text src:current|key:role}}' ),
		bws_fixture_gb_row( 'FR3.3 relationship hop (-> Fixture Ref Role, the hop target\'s own field)', '{{fixture_text src:ref|ref:related_staff|key:role|limit:1}}' ),
		bws_fixture_gb_row( 'FR3.4 taxonomy sidecar (-> sales@example.test, the ROOT\'s term)', '{{fixture_text srcTermIn:department|key:email}}' ),
		bws_fixture_gb_row( 'FR3.5 both sidecars: hop then drop into terms (-> warehouse@example.test, the hop TARGET\'s term)', '{{fixture_text src:ref|ref:related_staff|srcTermIn:department|key:email}}' ),
		// HAND-WIRE ONLY, and that is not an oversight: `site` is filtered out of every
		// rooting-modifier Source dropdown (#37), so this shape cannot be re-authored
		// through the control the rest of the page exercises. It is stored wire a site
		// can hold, which is exactly the population a migration has to answer for.
		//
		// The ONE shape whose rendered output the migration CHANGES, and the row exists
		// to make that visible rather than to assert a value. The modifier callback
		// returns on `site` BEFORE reading either sidecar (the #37 guard), so this
		// renders EMPTY today; its migrated form ({{text src:site|use:key|
		// key:organization_email}}, sidecars dropped) renders the org email. Empty is the
		// expectation here, so it uses the split-label row — a single block would take
		// its own label down with it and read as missing fixture.
		bws_fixture_gb_empty_row( 'FR3.6 src:site with inert sidecars — EMPTY today (the modifier returns before reading them); migrates to a site read that renders', '{{fixture_text src:site|ref:related_staff|srcTermIn:department|use:key|key:organization_email}}' ),
	) );

	$sections[] = bws_fixture_gb_section( 'FR4 - each shape beside the base-tag wire it must become', array(
		// Numbered to MATCH its FR3 partner, so a divergent pair is read off one digit.
		// There is deliberately no FR4.2: src:current and a stated-nothing source map to
		// the same wire, which is FR4.1.
		bws_fixture_gb_row( 'FR4.1 == FR3.1 and FR3.2 (-> Fixture Root Role)', '{{text src:fixture|key:role}}' ),
		bws_fixture_gb_row( 'FR4.3 == FR3.3 (-> Fixture Ref Role)', '{{text src:fixture;refs,related_staff|key:role|limit:1}}' ),
		bws_fixture_gb_row( 'FR4.4 == FR3.4 (-> sales@example.test)', '{{text src:fixture;terms,department|key:email|limit:1}}' ),
		bws_fixture_gb_row( 'FR4.5 == FR3.5 (-> warehouse@example.test)', '{{text src:fixture;refs,related_staff;terms,department|key:email|limit:1}}' ),
		bws_fixture_gb_row( 'FR4.6 what FR3.6 becomes — sidecars DROPPED (-> info@example.test)', '{{text src:site|use:key|key:organization_email}}' ),
	) );

	return implode( "\n\n", $sections );
}

/** Dispatcher: manifest content_builder name → page content. */
function bws_fixture_build_page_content( $builder ) {
	$map = array(
		'matrix_post_meta'     => 'bws_fixture_page_content_matrix_post_meta',
		'matrix_term_hop'      => 'bws_fixture_page_content_matrix_term_hop',
		'matrix_content'       => 'bws_fixture_page_content_matrix_content',
		'staff_join'           => 'bws_fixture_page_content_staff_join',
		'matrix_fixture_roots' => 'bws_fixture_page_content_matrix_fixture_roots',
		'pattern_legacy_wire'  => 'bws_fixture_pattern_content_legacy_wire',
	);
	if ( ! isset( $map[ $builder ] ) ) {
		return '';
	}
	return call_user_func( $map[ $builder ] );
}

/**
 * Block pattern content for the GB Pro pattern-cache fixture (#99).
 *
 * Carries a MIGRATABLE tag (`post_custom_datetime_single` is a pre-1.6 tag name, so its era
 * is established by construction and the converter always rewrites it) — without one, the
 * fixture cannot be made to diverge and the reconcile has nothing to repair.
 *
 * IT ALSO CARRIES LITERAL BACKSLASHES, in two places, and that is the point of the shape
 * rather than decoration. The escaping hazard this fixture exists to pin lives in the meta
 * layer: update_post_meta() calls wp_unslash(), which strips slashes RECURSIVELY through
 * arrays, so a read-modify-write that does not slash the whole structure back strips one
 * level from every string in the entry on every pass. A corpus with no backslash in it
 * survives that bug intact and the assertion passes while testing nothing.
 *
 *   - the block-comment JSON (`metadata.name` holds an escaped quote) — damages the
 *     `pattern` field, which is what the reconcile writes;
 *   - the rendered code block (a regex, i.e. the ordinary reason real pattern content holds
 *     backslashes) — damages `preview`, which the reconcile must leave alone.
 *
 * verify-pattern-cache.php asserts the backslashes are actually present before relying on
 * them, so a later edit that flattens this string fails loudly instead of going quiet.
 *
 * NOWDOC, not heredoc: every backslash here is literal wire, and PHP interpolation would
 * silently consume some of them.
 *
 * @since 1.17.0
 * @return string
 */
function bws_fixture_pattern_content_legacy_wire() {
	return <<<'HTML'
<!-- wp:paragraph {"metadata":{"name":"He said \"hello\""}} -->
<p>Pattern-cache fixture (#99). Pre-migration wire: {{post_custom_datetime_single date_time_field:event_midnight}}</p>
<!-- /wp:paragraph -->

<!-- wp:code -->
<pre class="wp-block-code"><code>preg_match( '/^\d+\s\w+/', $subject )</code></pre>
<!-- /wp:code -->
HTML;
}
