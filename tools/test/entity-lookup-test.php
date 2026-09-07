<?php
/**
 * Standalone unit harness for the entity-lookup REST service (FW-39, D14).
 *
 * SEPARATE FROM field-discovery-test.php DELIBERATELY (per the ticket and the route's own
 * file header): the bounded/unbounded axis is the whole reason the route exists apart, and
 * putting its tests in the bounded route's harness would be the first step of the merge
 * D14 forbids.
 *
 * No WordPress required. Every WP symbol the covered functions touch — get_taxonomies(),
 * get_terms(), get_term(), get_taxonomy(), current_user_can(), is_wp_error(),
 * rest_ensure_response() — is shimmed below with a deterministic stub, in the house
 * pattern (tools/test/lib-source-registry.php, preview-label-test.php).
 *
 * SCOPE:
 *   bws_entity_lookup_kind_readable()      (D19 per-kind gate)
 *   bws_entity_lookup_taxonomy_readable()  (D19 per-taxonomy narrowing)
 *   bws_entity_lookup_term_row()           (the one row shaper — D15)
 *   bws_entity_lookup_browse_terms()       (browse/search mode — D17 no-gate, D15 grouping)
 *   bws_entity_lookup_resolve_term()       (resolve-by-id mode)
 *   bws_entity_lookup_permission_check()   (the trust seam + per-kind dispatch, D19)
 *   bws_entity_lookup_rest_response()      (mode dispatch)
 *
 * Run:
 *   php tools/test/entity-lookup-test.php
 *
 * Exit 0 = all pass, 1 = any failure.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

// ── WP shims ──────────────────────────────────────────────────────────────────────────

$GLOBALS['bws_test_caps'] = array();
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap ) {
		return ! empty( $GLOBALS['bws_test_caps'][ $cap ] );
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $message;
		public function __construct( $message = '' ) { $this->message = $message; }
	}
}
if ( ! function_exists( 'rest_ensure_response' ) ) {
	function rest_ensure_response( $data ) { return $data; }
}

// A minimal WP_Taxonomy stand-in: name, labels->singular_name, cap->assign_terms.
function bws_test_tax( string $name, string $singular, string $assign_cap ) {
	return (object) array(
		'name'   => $name,
		'labels' => (object) array( 'singular_name' => $singular ),
		'cap'    => (object) array( 'assign_terms' => $assign_cap ),
	);
}

// A minimal WP_Term stand-in.
function bws_test_term( int $id, string $name, string $taxonomy ) {
	return (object) array( 'term_id' => $id, 'name' => $name, 'taxonomy' => $taxonomy );
}

// The fixture WORLD: two taxonomies, one requiring a capability nobody in these tests
// holds by default — the load-bearing case for the per-taxonomy narrowing.
$GLOBALS['bws_test_taxonomies'] = array(
	bws_test_tax( 'category', 'Category', 'edit_posts' ),
	bws_test_tax( 'benefit_tier', 'Benefit Tier', 'assign_benefit_tier' ),
);
$GLOBALS['bws_test_terms'] = array(
	'category'     => array(
		bws_test_term( 5, 'News', 'category' ),
		bws_test_term( 3, 'Announcements', 'category' ),
	),
	'benefit_tier' => array(
		bws_test_term( 34, 'Support', 'benefit_tier' ),
	),
);

if ( ! function_exists( 'get_taxonomies' ) ) {
	function get_taxonomies( $args = array(), $output = 'names' ) {
		return $GLOBALS['bws_test_taxonomies'];
	}
}
if ( ! function_exists( 'get_taxonomy' ) ) {
	function get_taxonomy( $name ) {
		foreach ( $GLOBALS['bws_test_taxonomies'] as $tax ) {
			if ( $tax->name === $name ) {
				return $tax;
			}
		}
		return false;
	}
}
if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( $args = array() ) {
		$tax    = $args['taxonomy'] ?? '';
		$search = strtolower( (string) ( $args['search'] ?? '' ) );
		$pool   = $GLOBALS['bws_test_terms'][ $tax ] ?? array();
		if ( '' === $search ) {
			$rows = $pool;
		} else {
			$rows = array_values( array_filter( $pool, function ( $t ) use ( $search ) {
				return false !== strpos( strtolower( $t->name ), $search );
			} ) );
		}
		usort( $rows, function ( $a, $b ) { return strcmp( $a->name, $b->name ); } );
		return $rows;
	}
}
if ( ! function_exists( 'get_term' ) ) {
	function get_term( $id ) {
		foreach ( $GLOBALS['bws_test_terms'] as $pool ) {
			foreach ( $pool as $t ) {
				if ( (int) $t->term_id === (int) $id ) {
					return $t;
				}
			}
		}
		return null;
	}
}
// bws_entity_lookup_resolve_term() reads THROUGH bws_get_validated_term()
// (taxonomy-helpers.php) rather than re-deriving "is this term real" itself — this
// shim stands in for that shared helper's real WP-backed shape.
if ( ! function_exists( 'bws_get_validated_term' ) ) {
	function bws_get_validated_term( $id ) {
		if ( ! $id ) { return false; }
		$term = get_term( $id );
		return ( ! $term || is_wp_error( $term ) ) ? false : $term;
	}
}

// A minimal WP_REST_Request stand-in: get_param() over a plain array.
class BWS_Test_Request {
	private $params;
	public function __construct( array $params = array() ) { $this->params = $params; }
	public function get_param( $key ) { return $this->params[ $key ] ?? ''; }
}

require __DIR__ . '/../../includes/rest/entity-lookup.php';

// bws_gb_user_can_author_dynamic_data() is a real plugin function this route consults;
// stub it deterministically rather than loading gb-trust-boundary.php's whole GB probe
// chain, which needs live GB classes this harness has no reason to fake.
$GLOBALS['bws_test_trusted'] = true;
if ( ! function_exists( 'bws_gb_user_can_author_dynamic_data' ) ) {
	function bws_gb_user_can_author_dynamic_data() { return $GLOBALS['bws_test_trusted']; }
}

$failures = 0;
$count    = 0;
function check( $label, $got, $expect ) {
	global $failures, $count;
	$count++;
	if ( $got === $expect ) {
		echo "  ok   {$label}\n";
	} else {
		$failures++;
		echo "  FAIL {$label}\n";
		echo '       expected: ' . json_encode( $expect ) . "\n";
		echo '       actual:   ' . json_encode( $got ) . "\n";
	}
}

echo "bws_entity_lookup_kind_readable — per-kind dispatch (D19)\n";
$GLOBALS['bws_test_caps'] = array( 'edit_posts' => true );
check( 'term kind readable when edit_posts is granted', true, bws_entity_lookup_kind_readable( 'term' ) );
$GLOBALS['bws_test_caps'] = array();
check( 'term kind NOT readable without edit_posts', false, bws_entity_lookup_kind_readable( 'term' ) );
check(
	'an unrecognized/unimplemented kind offers nothing — never a REST error',
	false,
	bws_entity_lookup_kind_readable( 'post' )
);

echo "\nbws_entity_lookup_taxonomy_readable — per-taxonomy narrowing (D19)\n";
$GLOBALS['bws_test_caps'] = array( 'edit_posts' => true );
check(
	'a taxonomy whose assign_terms cap the user HOLDS is readable',
	true,
	bws_entity_lookup_taxonomy_readable( bws_test_tax( 'category', 'Category', 'edit_posts' ) )
);
check(
	'…and one whose cap the user LACKS is not, even with edit_posts',
	false,
	bws_entity_lookup_taxonomy_readable( bws_test_tax( 'benefit_tier', 'Benefit Tier', 'assign_benefit_tier' ) )
);

echo "\nbws_entity_lookup_term_row — the one shaper (D15)\n";
check(
	'"#<id> <name>", grouped by the taxonomy\'s singular label',
	array( 'id' => 34, 'label' => '#34 Support', 'group' => 'Benefit Tier' ),
	bws_entity_lookup_term_row( bws_test_term( 34, 'Support', 'benefit_tier' ), 'Benefit Tier' )
);

echo "\nbws_entity_lookup_browse_terms — browse/search mode (D15, D17)\n";
$GLOBALS['bws_test_caps'] = array( 'edit_posts' => true );
check(
	'opens BROWSABLE with no search — every readable taxonomy, alphabetical within group (D17)',
	array(
		array( 'id' => 3, 'label' => '#3 Announcements', 'group' => 'Category' ),
		array( 'id' => 5, 'label' => '#5 News', 'group' => 'Category' ),
	),
	bws_entity_lookup_browse_terms()
);
check(
	'a taxonomy the user cannot read is silently absent, not an error',
	array(
		array( 'id' => 3, 'label' => '#3 Announcements', 'group' => 'Category' ),
		array( 'id' => 5, 'label' => '#5 News', 'group' => 'Category' ),
	),
	bws_entity_lookup_browse_terms( '', '' )
);
check(
	'typing narrows the list (D17)',
	array( array( 'id' => 5, 'label' => '#5 News', 'group' => 'Category' ) ),
	bws_entity_lookup_browse_terms( 'new' )
);
check(
	'the taxonomy FILTER narrows to one group — never serialized, UI state only (D16)',
	array( array( 'id' => 5, 'label' => '#5 News', 'group' => 'Category' ) ),
	bws_entity_lookup_browse_terms( 'news', 'category' )
);
$GLOBALS['bws_test_caps'] = array( 'edit_posts' => true, 'assign_benefit_tier' => true );
check(
	'a second taxonomy the user CAN read joins the list',
	array(
		array( 'id' => 3, 'label' => '#3 Announcements', 'group' => 'Category' ),
		array( 'id' => 5, 'label' => '#5 News', 'group' => 'Category' ),
		array( 'id' => 34, 'label' => '#34 Support', 'group' => 'Benefit Tier' ),
	),
	bws_entity_lookup_browse_terms()
);
$GLOBALS['bws_test_caps'] = array( 'edit_posts' => true );

echo "\nbws_entity_lookup_resolve_term — resolve-by-id mode\n";
check(
	'a real, readable term resolves',
	array( 'id' => 5, 'label' => '#5 News', 'group' => 'Category' ),
	bws_entity_lookup_resolve_term( 5 )
);
check( 'a deleted term resolves to null (the picker\'s "(missing)" case)', null, bws_entity_lookup_resolve_term( 999 ) );
check(
	'a term in a taxonomy the user cannot read resolves to null too',
	null,
	bws_entity_lookup_resolve_term( 34 )
);
check( 'id <= 0 resolves to null without querying anything', null, bws_entity_lookup_resolve_term( 0 ) );

echo "\nbws_entity_lookup_permission_check — the trust seam + per-kind dispatch (D19)\n";
$GLOBALS['bws_test_trusted'] = true;
$GLOBALS['bws_test_caps']    = array( 'edit_posts' => true );
check(
	'trusted + kind-readable → true',
	true,
	bws_entity_lookup_permission_check( new BWS_Test_Request( array( 'kind' => 'term' ) ) )
);
check( 'kind defaults to term when absent', true, bws_entity_lookup_permission_check( new BWS_Test_Request( array() ) ) );
$GLOBALS['bws_test_trusted'] = false;
check(
	'…a user failing the TRUST SEAM gets nothing, whatever the kind gate would say',
	false,
	bws_entity_lookup_permission_check( new BWS_Test_Request( array( 'kind' => 'term' ) ) )
);
$GLOBALS['bws_test_trusted'] = true;
$GLOBALS['bws_test_caps']    = array();
check(
	'…and a user passing trust but failing the KIND gate gets nothing either',
	false,
	bws_entity_lookup_permission_check( new BWS_Test_Request( array( 'kind' => 'term' ) ) )
);
$GLOBALS['bws_test_caps'] = array( 'edit_posts' => true );

echo "\nbws_entity_lookup_rest_response — mode dispatch\n";
check(
	'mode=browse (default) returns rows',
	array(
		'rows' => array(
			array( 'id' => 3, 'label' => '#3 Announcements', 'group' => 'Category' ),
			array( 'id' => 5, 'label' => '#5 News', 'group' => 'Category' ),
		),
	),
	bws_entity_lookup_rest_response( new BWS_Test_Request( array( 'kind' => 'term' ) ) )
);
check(
	'mode=resolve returns a single row',
	array( 'row' => array( 'id' => 5, 'label' => '#5 News', 'group' => 'Category' ) ),
	bws_entity_lookup_rest_response( new BWS_Test_Request( array( 'kind' => 'term', 'mode' => 'resolve', 'id' => 5 ) ) )
);
check(
	'an unimplemented kind (post) answers empty, never a REST error',
	array( 'rows' => array() ),
	bws_entity_lookup_rest_response( new BWS_Test_Request( array( 'kind' => 'post' ) ) )
);

echo "\n" . ( $failures ? "FAILED {$failures}/{$count}\n" : "PASSED {$count}/{$count}\n" );
exit( $failures ? 1 : 0 );
