<?php
/**
 * Datetime matrix baseline capture — renders every datetime-test-matrix.md row
 * through the real seam (like verify.php) and prints `label<TAB>output`.
 *
 * Run against each matrix page (rows resolve off the --url context):
 *   bin/wp.sh testbed eval-file <mounted-repo>/tools/debug/datetime-baseline-capture.php \
 *     --url=https://testbed.test/matrix-post-meta/
 *   ... --url=https://testbed.test/matrix-terms-valid/   (D4 term rows)
 *   ... --url=https://testbed.test/matrix-terms-mixed/
 *   ... --url=https://testbed.test/matrix-terms-junk/
 *   ... --url=https://testbed.test/department/support/   (D7 term-ambient rows, FW-3)
 *   ... --url=https://testbed.test/department/warehouse/ (D7.6 empty-term row)
 *
 * Used for the Stage 0b baseline (#48) and any later re-baseline.
 */
wp(); // real main query from --url

$instance          = new stdClass();
$instance->context = [];

$rows = array(
	// D0 — single basics (post-meta page)
	'D0.1'  => '{{datetime_single key:event_start_date}}',
	'D0.2'  => '{{datetime_single key:event_datetime}}',
	'D0.3'  => '{{datetime_single key:event_datetime|format:Y-m-d}}',
	'D0.4'  => '{{datetime_single key:event_thisyear}}',
	'D0.5'  => '{{datetime_single key:event_thisyear|showCurrentYear}}',
	'D0.6'  => '{{datetime_single key:event_midnight}}',
	'D0.7'  => '{{datetime_single key:event_midnight|showMidnight}}',
	'D0.8'  => '{{datetime_single key:event_start_date|timeKey:event_time}}',
	'D0.9'  => '{{datetime_single key:event_start_date|timeKey:event_time|timeSep: @ }}',
	'D0.10' => '{{datetime_single key:plain_meta_date}}',
	'D0.11' => '{{datetime_single key:event_date_dmy}}',
	// D1 — as: narrowing
	'D1.1'  => '{{datetime_single key:event_datetime|as:date}}',
	'D1.2'  => '{{datetime_single key:event_datetime|as:time}}',
	'D1.3'  => '{{datetime_single key:event_time|as:time}}',
	'D1.4'  => '{{datetime_range startKey:event_datetime|as:time}}',
	'D1.5'  => '{{datetime_range startKey:event_datetime|as:time|format:H:i}}',
	// D2 — range basics
	'D2.1'  => '{{datetime_range startKey:event_start_date|endKey:event_end_date}}',
	'D2.2'  => '{{datetime_range startKey:event_start_date|endKey:event_end_date|rangeSep: to }}',
	'D2.3'  => '{{datetime_range startKey:event_datetime|endKey:event_end_datetime}}',
	'D2.4'  => '{{datetime_range startKey:event_midnight|endKey:event_end_datetime}}',
	'D2.5'  => '{{datetime_range startKey:event_datetime|endKey:event_end_datetime|as:time}}',
	'D2.6'  => '{{datetime_range startKey:event_time|endKey:event_end_time|as:time}}',
	// D3 — #25 new behavior
	'D3.1'  => '{{datetime_range startKey:event_datetime|endKey:event_end_datetime|as:time|format:H:i}}',
	'D3.2'  => '{{datetime_range startKey:event_datetime|endKey:event_end_datetime|as:time|format:g:i}}',
	'D3.3'  => '{{datetime_range startKey:event_time|endKey:event_end_time|as:time|format:g:i A}}',
	'D3.4'  => '{{datetime_range startKey:event_midnight|endKey:event_end_datetime|as:time|format:H:i}}',
	// D4 — list mode (term rows only render on term pages; ref rows here)
	'D4.1'  => '{{datetime_single srcTermIn:department|key:event_date|limit:5}}',
	'D4.2'  => '{{datetime_single srcTermIn:department|key:event_date}}',
	'D4.3'  => '{{datetime_single srcTermIn:department|key:event_date|limit:5|sep: / }}',
	'D4.4'  => '{{datetime_single srcTermIn:department|key:event_date|limit:5|fallback:Dates TBA}}',
	'D4.5'  => '{{datetime_range srcTermIn:department|startKey:event_date|limit:5|sep:; }}',
	'D4.6'  => '{{datetime_single src:ref|ref:related_staff|key:event_datetime|limit:5}}',
	'D4.7'  => '{{datetime_range src:ref|ref:related_staff|startKey:event_datetime|endKey:event_end_datetime|limit:3|sep:; }}',
	'D4.8'  => '{{datetime_single src:ref|ref:related_staff|key:event_datetime|limit:5|linkTo:permalink}}',
	'D4.9'  => '{{datetime_single src:ref|ref:related_staff|key:event_datetime|linkTo:permalink}}',
	// D5 — sources + fallback
	'D5.1'  => '{{datetime_single src:site|key:organization_founded}}',
	'D5.2'  => '{{datetime_single src:site|key:organization_founded|format:F j, Y}}',
	'D5.3'  => '{{datetime_single src:site|key:org_party_datetime}}',
	'D5.4'  => '{{datetime_single src:ref|ref:related_staff|key:event_datetime}}',
	'D5.6'  => '{{datetime_single key:missing_dt_field|fallback:Date TBA}}',
	// D7 — FW-3(a) term-ambient parity (meaningful on the /department/* term archives)
	'D7.1'  => '{{datetime_single key:event_date}}',
	'D7.2'  => '{{datetime_single key:event_date|format:Y-m-d}}',
	'D7.3'  => '{{datetime_single key:event_date|fallback:Date TBA}}',
	'D7.4'  => '{{datetime_single key:event_date|as:date}}',
	'D7.5'  => '{{datetime_range startKey:event_date}}',
	'D7.6'  => '{{datetime_single key:event_date|fallback:Date TBA}}',
	'D7.7'  => '{{datetime_single key:event_date|as:time}}',
);

foreach ( $rows as $label => $tag ) {
	$out = GenerateBlocks_Register_Dynamic_Tag::replace_tags( $tag, [], $instance );
	echo $label . "\t" . var_export( (string) $out, true ) . "\n";
}
