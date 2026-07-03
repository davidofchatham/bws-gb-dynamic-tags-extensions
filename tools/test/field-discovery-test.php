<?php
/**
 * Standalone unit harness for the field-discovery pure transforms in
 * includes/rest/field-discovery.php.
 *
 * No WordPress required. The discovery derivation pipeline is pure PHP: the
 * helpers take ACF-shaped arrays as arguments and return plain arrays. Only the
 * live orchestrator (bws_field_discovery_collect) calls acf_get_* / core WP, and
 * it is NOT exercised here — the harness drives the pure helpers directly.
 *
 * SCOPE — pure transforms only (SPEC.md §V5/§V6/§V7/§V8):
 *   bws_field_discovery_derive_kind_scope()   location -> kind + candidate scope
 *   bws_field_discovery_flatten_fields()      sub-field recurse (group/repeater/flex)
 *   bws_field_discovery_group_entry()         group array -> envelope entry
 *   bws_field_discovery_registered_meta_group() register_meta map -> group entry
 *   bws_field_discovery_dedupe()              within-(kind,scope) dedupe, ACF wins
 *   bws_field_discovery_scopes_overlap()      scope overlap predicate
 *   bws_field_discovery_filter_disallowed()   DISALLOWED_KEYS gate
 *
 * EXCLUDED — REST route wiring, permission callback, live ACF/collect(), and the
 * JS control (no JS build/test pipeline in repo; covered by the manual matrix
 * tools/test/field-selector-test-matrix.md).
 *
 * Run:
 *   php tools/test/field-discovery-test.php
 *
 * Exit 0 = all pass, 1 = any failure.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

// field-discovery.php top-level is ABSPATH-guarded and makes no WP calls at parse
// (const + function defs only), so a bare define + the shims below suffice.
define( 'ABSPATH', __DIR__ );

// __() shim — identity. Only used inside collect() group titles, not exercised
// by the pure-helper tests, but defined so require does not warn if reached.
if ( ! function_exists( '__' ) ) {
	function __( $s, $d = 'default' ) { return $s; }
}

// Fake GB security class for the DISALLOWED_KEYS gate test. Two blocked keys.
if ( ! class_exists( 'GenerateBlocks_Dynamic_Tag_Security' ) ) {
	class GenerateBlocks_Dynamic_Tag_Security {
		const DISALLOWED_KEYS = array( 'user_pass', 'session_tokens' );
	}
}

require __DIR__ . '/../../includes/rest/field-discovery.php';

$failures = 0;
$count    = 0;

/**
 * Assert two values are deeply equal (order-sensitive).
 */
function assert_eq( $label, $expected, $actual ) {
	global $failures, $count;
	$count++;
	if ( $expected === $actual ) {
		echo "  PASS  {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL  {$label}\n";
	echo "        expected: " . var_export( $expected, true ) . "\n";
	echo "        actual:   " . var_export( $actual, true ) . "\n";
}

/**
 * Assert a boolean is true.
 */
function assert_true( $label, $actual ) {
	assert_eq( $label, true, $actual );
}

// -----------------------------------------------------------------------------
echo "\n== derive_kind_scope ==\n";

// post_type location -> kind post, scope = the slugs.
$loc_post = array(
	array(
		array( 'param' => 'post_type', 'operator' => '==', 'value' => 'event' ),
	),
);
assert_eq( 'post_type -> post kind', 'post', bws_field_discovery_derive_kind_scope( $loc_post )['kind'] );
assert_eq( 'post_type -> scope [event]', array( 'event' ), bws_field_discovery_derive_kind_scope( $loc_post )['scope'] );

// taxonomy location -> kind term.
$loc_term = array(
	array(
		array( 'param' => 'taxonomy', 'operator' => '==', 'value' => 'event_category' ),
	),
);
assert_eq( 'taxonomy -> term kind', 'term', bws_field_discovery_derive_kind_scope( $loc_term )['kind'] );
assert_eq( 'taxonomy -> scope [event_category]', array( 'event_category' ), bws_field_discovery_derive_kind_scope( $loc_term )['scope'] );

// options_page location -> kind site.
$loc_site = array(
	array(
		array( 'param' => 'options_page', 'operator' => '==', 'value' => 'theme-settings' ),
	),
);
assert_eq( 'options_page -> site kind', 'site', bws_field_discovery_derive_kind_scope( $loc_site )['kind'] );

// Multiple OR-groups of post_type -> scope collects both, deduped.
$loc_multi = array(
	array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'event' ) ),
	array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ) ),
	array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'event' ) ),
);
assert_eq( 'multi post_type -> deduped scope', array( 'event', 'page' ), bws_field_discovery_derive_kind_scope( $loc_multi )['scope'] );

// Kind-less location (page_template only) -> defaults to post, empty scope.
$loc_none = array(
	array( array( 'param' => 'page_template', 'operator' => '==', 'value' => 'full-width.php' ) ),
);
assert_eq( 'no kind param -> post default', 'post', bws_field_discovery_derive_kind_scope( $loc_none )['kind'] );
assert_eq( 'no kind param -> empty scope', array(), bws_field_discovery_derive_kind_scope( $loc_none )['scope'] );

// post_type + taxonomy in same location -> post wins (priority), scope from post.
$loc_mixed = array(
	array(
		array( 'param' => 'taxonomy', 'operator' => '==', 'value' => 'cat' ),
		array( 'param' => 'post_type', 'operator' => '==', 'value' => 'event' ),
	),
);
assert_eq( 'mixed params -> post priority', 'post', bws_field_discovery_derive_kind_scope( $loc_mixed )['kind'] );
assert_eq( 'mixed params -> post scope only', array( 'event' ), bws_field_discovery_derive_kind_scope( $loc_mixed )['scope'] );

// != operator still fixes kind + contributes scope value.
$loc_neq = array(
	array( array( 'param' => 'post_type', 'operator' => '!=', 'value' => 'page' ) ),
);
assert_eq( '!= operator fixes kind', 'post', bws_field_discovery_derive_kind_scope( $loc_neq )['kind'] );
assert_eq( '!= operator collects value', array( 'page' ), bws_field_discovery_derive_kind_scope( $loc_neq )['scope'] );

// -----------------------------------------------------------------------------
echo "\n== flatten_fields ==\n";

// Top-level fields -> bare name, context field.
$flat_top = bws_field_discovery_flatten_fields( array(
	array( 'name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text' ),
	array( 'name' => 'event_date', 'label' => 'Event Date', 'type' => 'date_picker', 'return_format' => 'Ymd' ),
) );
assert_eq( 'top-level count', 2, count( $flat_top ) );
assert_eq( 'top-level name = bare', 'subtitle', $flat_top[0]['name'] );
assert_eq( 'top-level context = field', 'field', $flat_top[0]['name'] === 'subtitle' ? $flat_top[0]['context_hint'] : 'X' );
assert_eq( 'top-level return_format carried', 'Ymd', $flat_top[1]['return_format'] );

// Missing label falls back to name.
$flat_nolabel = bws_field_discovery_flatten_fields( array(
	array( 'name' => 'raw_key', 'type' => 'text' ),
) );
assert_eq( 'missing label -> name', 'raw_key', $flat_nolabel[0]['label'] );

// GROUP child -> composite parent_child key, context field.
$flat_group = bws_field_discovery_flatten_fields( array(
	array(
		'name' => 'venue', 'label' => 'Venue', 'type' => 'group',
		'sub_fields' => array(
			array( 'name' => 'city', 'label' => 'City', 'type' => 'text' ),
		),
	),
) );
// [0] = the group itself, [1] = child composite.
assert_eq( 'group child count (parent+child)', 2, count( $flat_group ) );
assert_eq( 'group child composite name', 'venue_city', $flat_group[1]['name'] );
assert_eq( 'group child context = field', 'field', $flat_group[1]['context_hint'] );
assert_eq( 'group child breadcrumb', 'Venue', $flat_group[1]['parent_path'] );

// REPEATER child -> bare name, context row.
$flat_rep = bws_field_discovery_flatten_fields( array(
	array(
		'name' => 'sessions', 'label' => 'Sessions', 'type' => 'repeater',
		'sub_fields' => array(
			array( 'name' => 'session_title', 'label' => 'Title', 'type' => 'text' ),
		),
	),
) );
assert_eq( 'repeater child bare name', 'session_title', $flat_rep[1]['name'] );
assert_eq( 'repeater child context = row', 'row', $flat_rep[1]['context_hint'] );

// FLEXIBLE content layout child -> bare name, context row.
$flat_flex = bws_field_discovery_flatten_fields( array(
	array(
		'name' => 'blocks', 'label' => 'Blocks', 'type' => 'flexible_content',
		'layouts' => array(
			array(
				'label' => 'Hero',
				'sub_fields' => array(
					array( 'name' => 'headline', 'label' => 'Headline', 'type' => 'text' ),
				),
			),
		),
	),
) );
assert_eq( 'flex layout child bare name', 'headline', $flat_flex[1]['name'] );
assert_eq( 'flex layout child context = row', 'row', $flat_flex[1]['context_hint'] );

// Nested GROUP inside GROUP -> double composite.
$flat_nested = bws_field_discovery_flatten_fields( array(
	array(
		'name' => 'a', 'label' => 'A', 'type' => 'group',
		'sub_fields' => array(
			array(
				'name' => 'b', 'label' => 'B', 'type' => 'group',
				'sub_fields' => array(
					array( 'name' => 'c', 'label' => 'C', 'type' => 'text' ),
				),
			),
		),
	),
) );
$names = array_column( $flat_nested, 'name' );
assert_true( 'nested group composite a_b_c present', in_array( 'a_b_c', $names, true ) );

// -----------------------------------------------------------------------------
echo "\n== group_entry ==\n";

$entry = bws_field_discovery_group_entry(
	array( 'title' => 'Event Details', 'location' => $loc_post ),
	$flat_top
);
assert_eq( 'group_entry title', 'Event Details', $entry['title'] ?? $entry['group_title'] );
assert_eq( 'group_entry kind', 'post', $entry['kind'] );
assert_eq( 'group_entry scope', array( 'event' ), $entry['scope'] );
assert_eq( 'group_entry source = acf', 'acf', $entry['source'] );
assert_eq( 'group_entry field count', 2, count( $entry['fields'] ) );

// -----------------------------------------------------------------------------
echo "\n== registered_meta_group ==\n";

$rmg = bws_field_discovery_registered_meta_group(
	array(
		'featured'  => array( 'description' => 'Featured flag' ),
		'raw_only'  => array(),
	),
	'post',
	'event',
	'Registered post meta'
);
assert_eq( 'reg group source', 'registered', $rmg['source'] );
assert_eq( 'reg group kind', 'post', $rmg['kind'] );
assert_eq( 'reg group scope', array( 'event' ), $rmg['scope'] );
assert_eq( 'reg field label from description', 'Featured flag', $rmg['fields'][0]['label'] );
assert_eq( 'reg field label fallback to key', 'raw_only', $rmg['fields'][1]['label'] );
assert_eq( 'empty map -> null', null, bws_field_discovery_registered_meta_group( array(), 'post', '', 'X' ) );

// -----------------------------------------------------------------------------
echo "\n== scopes_overlap ==\n";

assert_true( 'empty a overlaps anything', bws_field_discovery_scopes_overlap( array(), array( 'event' ) ) );
assert_true( 'empty b overlaps anything', bws_field_discovery_scopes_overlap( array( 'event' ), array() ) );
assert_true( 'shared slug overlaps', bws_field_discovery_scopes_overlap( array( 'event', 'page' ), array( 'page' ) ) );
assert_eq( 'disjoint no overlap', false, bws_field_discovery_scopes_overlap( array( 'event' ), array( 'page' ) ) );

// -----------------------------------------------------------------------------
echo "\n== dedupe (within kind,scope; ACF wins) ==\n";

// Same name, same kind, overlapping scope: ACF entry wins over registered.
// Registered group listed FIRST, ACF SECOND -> ACF must displace registered.
$env_dupe = array(
	'post' => array(
		array(
			'group_title' => 'Registered post meta', 'kind' => 'post',
			'scope' => array( 'event' ), 'source' => 'registered',
			'fields' => array(
				array( 'name' => 'event_date', 'label' => 'event_date', 'type' => '', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ),
			),
		),
		array(
			'group_title' => 'Event Details', 'kind' => 'post',
			'scope' => array( 'event' ), 'source' => 'acf',
			'fields' => array(
				array( 'name' => 'event_date', 'label' => 'Event Date', 'type' => 'date_picker', 'return_format' => 'Ymd', 'context_hint' => 'field', 'parent_path' => '' ),
			),
		),
	),
	'term' => array(),
	'site' => array(),
);
$deduped = bws_field_discovery_dedupe( $env_dupe );
// Registered group emptied + pruned; only the ACF group remains.
assert_eq( 'ACF-wins: one group left', 1, count( $deduped['post'] ) );
assert_eq( 'ACF-wins: kept group is ACF', 'acf', $deduped['post'][0]['source'] );
assert_eq( 'ACF-wins: kept label is ACF', 'Event Date', $deduped['post'][0]['fields'][0]['label'] );

// Same name across DIFFERENT kinds is NOT merged.
$env_cross = array(
	'post' => array(
		array( 'group_title' => 'P', 'kind' => 'post', 'scope' => array( 'event' ), 'source' => 'acf',
			'fields' => array( array( 'name' => 'date', 'label' => 'Post Date', 'type' => 'text', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ) ) ),
	),
	'term' => array(
		array( 'group_title' => 'T', 'kind' => 'term', 'scope' => array( 'cat' ), 'source' => 'acf',
			'fields' => array( array( 'name' => 'date', 'label' => 'Term Date', 'type' => 'text', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ) ) ),
	),
	'site' => array(),
);
$dc = bws_field_discovery_dedupe( $env_cross );
assert_eq( 'cross-kind kept in post', 1, count( $dc['post'][0]['fields'] ) );
assert_eq( 'cross-kind kept in term', 1, count( $dc['term'][0]['fields'] ) );

// Disjoint scope, same name, same kind: NOT merged (different buckets).
$env_disjoint = array(
	'post' => array(
		array( 'group_title' => 'A', 'kind' => 'post', 'scope' => array( 'event' ), 'source' => 'acf',
			'fields' => array( array( 'name' => 'x', 'label' => 'X-event', 'type' => 'text', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ) ) ),
		array( 'group_title' => 'B', 'kind' => 'post', 'scope' => array( 'page' ), 'source' => 'acf',
			'fields' => array( array( 'name' => 'x', 'label' => 'X-page', 'type' => 'text', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ) ) ),
	),
	'term' => array(), 'site' => array(),
);
$dd = bws_field_discovery_dedupe( $env_disjoint );
assert_eq( 'disjoint scope: both groups kept', 2, count( $dd['post'] ) );

// ACF-vs-ACF same bucket: first-seen wins.
$env_acfacf = array(
	'post' => array(
		array( 'group_title' => 'First', 'kind' => 'post', 'scope' => array( 'event' ), 'source' => 'acf',
			'fields' => array( array( 'name' => 'y', 'label' => 'First Y', 'type' => 'text', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ) ) ),
		array( 'group_title' => 'Second', 'kind' => 'post', 'scope' => array( 'event' ), 'source' => 'acf',
			'fields' => array( array( 'name' => 'y', 'label' => 'Second Y', 'type' => 'text', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ) ) ),
	),
	'term' => array(), 'site' => array(),
);
$da = bws_field_discovery_dedupe( $env_acfacf );
assert_eq( 'ACF-vs-ACF: one field kept', 1, count( $da['post'][0]['fields'] ) );
assert_eq( 'ACF-vs-ACF: first-seen label', 'First Y', $da['post'][0]['fields'][0]['label'] );

// -----------------------------------------------------------------------------
echo "\n== filter_disallowed (V6) ==\n";

$env_dis = array(
	'post' => array(
		array( 'group_title' => 'G', 'kind' => 'post', 'scope' => array(), 'source' => 'acf',
			'fields' => array(
				array( 'name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ),
				array( 'name' => 'user_pass', 'label' => 'Pass', 'type' => 'text', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ),
				array( 'name' => '_piecal_start_date', 'label' => 'Start', 'type' => 'text', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ),
			),
		),
	),
	'term' => array(), 'site' => array(),
);
$filtered = bws_field_discovery_filter_disallowed( $env_dis );
$fnames   = array_column( $filtered['post'][0]['fields'], 'name' );
assert_true( 'DISALLOWED user_pass removed', ! in_array( 'user_pass', $fnames, true ) );
assert_true( 'allowed subtitle kept', in_array( 'subtitle', $fnames, true ) );
assert_true( 'underscore-protected _piecal kept (resolver allows)', in_array( '_piecal_start_date', $fnames, true ) );

// -----------------------------------------------------------------------------
echo "\n== envelope shape ==\n";

$empty = bws_field_discovery_filter_disallowed( array( 'post' => array(), 'term' => array(), 'site' => array() ) );
assert_true( 'envelope has post key', array_key_exists( 'post', $empty ) );
assert_true( 'envelope has term key', array_key_exists( 'term', $empty ) );
assert_true( 'envelope has site key', array_key_exists( 'site', $empty ) );

// -----------------------------------------------------------------------------
echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures} of {$count} assertions failed.\n";
	exit( 1 );
}
echo "OK: all {$count} assertions passed.\n";
exit( 0 );
