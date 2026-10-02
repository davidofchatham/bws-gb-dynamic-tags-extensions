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
 *   bws_entity_lookup_kind_readable()      (D19 per-kind gate, dispatched not branched)
 *   bws_entity_lookup_taxonomy_readable()  (D19 per-taxonomy narrowing)
 *   bws_entity_lookup_post_type_readable() (D19 per-post-type narrowing, FW-39 ticket 03)
 *   bws_entity_lookup_post_type_statuses() (D18 query-level status derivation, ticket 03)
 *   bws_entity_lookup_term_row()           (the one row shaper — D15; `scope` slug, D22)
 *   bws_entity_lookup_post_row()           (the one row shaper for posts — D15/D18; `scope` slug, D22)
 *   bws_entity_lookup_browse_terms()       (browse/search mode — D17 no-gate, D15 grouping)
 *   bws_entity_lookup_browse_posts()       (browse/search mode for posts, ticket 03)
 *   bws_entity_lookup_resolve_term()       (resolve-by-id mode)
 *   bws_entity_lookup_resolve_post()       (resolve-by-id mode for posts, ticket 03)
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
		return isset( $args['number'] ) ? array_slice( $rows, 0, (int) $args['number'] ) : $rows;
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

// ── The POST fixture world (FW-39 ticket 03) ────────────────────────────────────────────

// A minimal WP_Post_Type stand-in: name, labels->singular_name, cap->edit_posts,
// cap->read_private_posts.
function bws_test_post_type( string $name, string $singular, string $edit_cap, string $private_cap = 'read_private_posts' ) {
	return (object) array(
		'name'   => $name,
		'labels' => (object) array( 'singular_name' => $singular ),
		'cap'    => (object) array( 'edit_posts' => $edit_cap, 'read_private_posts' => $private_cap ),
	);
}

// A minimal WP_Post stand-in.
function bws_test_post( int $id, string $title, string $post_type, string $status = 'publish' ) {
	return (object) array( 'ID' => $id, 'post_title' => $title, 'post_type' => $post_type, 'post_status' => $status );
}

// Two post types, one requiring a capability nobody in these tests holds by default —
// the load-bearing case for the per-post-type narrowing, on the same terms as the
// taxonomy fixture above. `page` also carries a distinct `read_private_posts` cap, so the
// private-status case can be driven independently of the edit-status cases.
$GLOBALS['bws_test_post_types'] = array(
	bws_test_post_type( 'post', 'Post', 'edit_posts', 'read_private_posts' ),
	bws_test_post_type( 'landing_page', 'Landing Page', 'edit_landing_pages', 'read_private_landing_pages' ),
);
$GLOBALS['bws_test_posts'] = array(
	'post' => array(
		bws_test_post( 5, 'Hello World', 'post', 'publish' ),
		bws_test_post( 3, 'A Draft', 'post', 'draft' ),
		bws_test_post( 7, 'Secret', 'post', 'private' ),
	),
	'landing_page' => array(
		bws_test_post( 34, 'Support', 'landing_page', 'publish' ),
	),
);

if ( ! function_exists( 'get_post_types' ) ) {
	function get_post_types( $args = array(), $output = 'names' ) {
		return $GLOBALS['bws_test_post_types'];
	}
}
if ( ! function_exists( 'get_post_type_object' ) ) {
	function get_post_type_object( $name ) {
		foreach ( $GLOBALS['bws_test_post_types'] as $pt ) {
			if ( $pt->name === $name ) {
				return $pt;
			}
		}
		return null;
	}
}
// The post browse reads through a NARROWED WP_Query (bws_entity_lookup_query_posts()), so the
// stand-in records what the real one would be asked: the `posts_fields` filter live DURING the
// query, and the args that keep partial rows out of the post cache.
$GLOBALS['wpdb']            = (object) array( 'posts' => 'wp_posts' );
$GLOBALS['bws_test_filters'] = array();
$GLOBALS['bws_test_queries'] = array();
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $fn ) { $GLOBALS['bws_test_filters'][ $tag ][] = $fn; return true; }
}
if ( ! function_exists( 'remove_filter' ) ) {
	function remove_filter( $tag, $fn ) {
		$GLOBALS['bws_test_filters'][ $tag ] = array_values( array_filter( $GLOBALS['bws_test_filters'][ $tag ] ?? array(), function ( $f ) use ( $fn ) { return $f !== $fn; } ) );
		return true;
	}
}
if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		public $posts = array();
		public function __construct( $args = array() ) {
			$fields = '';
			foreach ( $GLOBALS['bws_test_filters']['posts_fields'] ?? array() as $fn ) {
				$fields = $fn( $fields );
			}
			$GLOBALS['bws_test_queries'][] = array( 'args' => $args, 'fields' => $fields );
			$this->posts = bws_test_get_posts( $args );
		}
	}
}
if ( ! function_exists( 'bws_test_get_posts' ) ) {
	function bws_test_get_posts( $args = array() ) {
		$post_type = $args['post_type'] ?? 'post';
		$statuses  = (array) ( $args['post_status'] ?? array( 'publish' ) );
		$search    = strtolower( (string) ( $args['s'] ?? '' ) );
		$pool      = $GLOBALS['bws_test_posts'][ $post_type ] ?? array();
		$rows      = array_values( array_filter( $pool, function ( $p ) use ( $statuses, $search ) {
			if ( ! in_array( $p->post_status, $statuses, true ) ) {
				return false;
			}
			return '' === $search || false !== strpos( strtolower( $p->post_title ), $search );
		} ) );
		usort( $rows, function ( $a, $b ) { return strcmp( $a->post_title, $b->post_title ); } );
		$limit = (int) ( $args['posts_per_page'] ?? -1 );
		return $limit >= 0 ? array_slice( $rows, 0, $limit ) : $rows;
	}
}
if ( ! function_exists( 'get_post' ) ) {
	function get_post( $id ) {
		foreach ( $GLOBALS['bws_test_posts'] as $pool ) {
			foreach ( $pool as $p ) {
				if ( (int) $p->ID === (int) $id ) {
					return $p;
				}
			}
		}
		return null;
	}
}

// The group lists skip a group with nothing in it, asked through WP's own counters.
if ( ! function_exists( 'wp_count_terms' ) ) {
	function wp_count_terms( $args = array() ) {
		return count( $GLOBALS['bws_test_terms'][ $args['taxonomy'] ?? '' ] ?? array() );
	}
}
if ( ! function_exists( 'wp_count_posts' ) ) {
	function wp_count_posts( $type ) {
		$counts = array();
		foreach ( $GLOBALS['bws_test_posts'][ $type ] ?? array() as $p ) {
			$counts[ $p->post_status ] = ( $counts[ $p->post_status ] ?? 0 ) + 1;
		}
		return (object) $counts;
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

echo "bws_entity_lookup_kind_readable — per-kind DISPATCH, not branching (D19)\n";
$GLOBALS['bws_test_caps'] = array( 'edit_posts' => true );
check( 'term kind readable when edit_posts is granted', true, bws_entity_lookup_kind_readable( 'term' ) );
check( 'post kind readable when edit_posts is granted', true, bws_entity_lookup_kind_readable( 'post' ) );
$GLOBALS['bws_test_caps'] = array();
check( 'term kind NOT readable without edit_posts', false, bws_entity_lookup_kind_readable( 'term' ) );
check( 'post kind NOT readable without edit_posts', false, bws_entity_lookup_kind_readable( 'post' ) );
check(
	'an unrecognized/unimplemented kind offers nothing — never a REST error',
	false,
	bws_entity_lookup_kind_readable( 'user' )
);
// D19's OWN framing: "dispatches to a per-kind predicate rather than branching inside one
// callback" — asserted structurally, not just by behavior, so a future collapse back into
// one branching callback is caught even if it happens to answer these same cases right.
$kind_predicates = bws_entity_lookup_kind_readable_predicates();
check(
	'term and post dispatch to two DISTINCT named predicate functions, not one branching callback',
	true,
	isset( $kind_predicates['term'], $kind_predicates['post'] )
		&& $kind_predicates['term'] !== $kind_predicates['post']
		&& function_exists( $kind_predicates['term'] )
		&& function_exists( $kind_predicates['post'] )
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
	array( 'id' => 34, 'label' => '#34 Support', 'group' => 'Benefit Tier', 'scope' => 'benefit_tier' ),
	bws_entity_lookup_term_row( bws_test_term( 34, 'Support', 'benefit_tier' ), 'Benefit Tier' )
);
// `scope` is the field picker's machine handle (D22) and `group` is a heading an author
// reads; the two are separate because a taxonomy's label and its slug are separate, and a
// shaper that handed the label over as the scope would match no discovery group at all.
// Driven with a label that is NOT the slug so the distinction cannot pass by coincidence.
check(
	'`scope` is the taxonomy SLUG, derived from the term itself rather than from the group label',
	'benefit_tier',
	bws_entity_lookup_term_row( bws_test_term( 34, 'Support', 'benefit_tier' ), 'Benefit Tier' )['scope']
);

echo "\nbws_entity_lookup_browse_terms — browse/search mode (D15, D17)\n";
$GLOBALS['bws_test_caps'] = array( 'edit_posts' => true );
check(
	'opens BROWSABLE with no search — every readable taxonomy, alphabetical within group (D17)',
	array(
		array( 'id' => 3, 'label' => '#3 Announcements', 'group' => 'Category', 'scope' => 'category' ),
		array( 'id' => 5, 'label' => '#5 News', 'group' => 'Category', 'scope' => 'category' ),
	),
	bws_entity_lookup_browse_terms()
);
check(
	'a taxonomy the user cannot read is silently absent, not an error',
	array(
		array( 'id' => 3, 'label' => '#3 Announcements', 'group' => 'Category', 'scope' => 'category' ),
		array( 'id' => 5, 'label' => '#5 News', 'group' => 'Category', 'scope' => 'category' ),
	),
	bws_entity_lookup_browse_terms( '', '' )
);
check(
	'typing narrows the list (D17)',
	array( array( 'id' => 5, 'label' => '#5 News', 'group' => 'Category', 'scope' => 'category' ) ),
	bws_entity_lookup_browse_terms( 'new' )
);
check(
	'the taxonomy FILTER narrows to one group — never serialized, UI state only (D16)',
	array( array( 'id' => 5, 'label' => '#5 News', 'group' => 'Category', 'scope' => 'category' ) ),
	bws_entity_lookup_browse_terms( 'news', 'category' )
);
$GLOBALS['bws_test_caps'] = array( 'edit_posts' => true, 'assign_benefit_tier' => true );
check(
	'a second taxonomy the user CAN read joins the list',
	array(
		array( 'id' => 3, 'label' => '#3 Announcements', 'group' => 'Category', 'scope' => 'category' ),
		array( 'id' => 5, 'label' => '#5 News', 'group' => 'Category', 'scope' => 'category' ),
		array( 'id' => 34, 'label' => '#34 Support', 'group' => 'Benefit Tier', 'scope' => 'benefit_tier' ),
	),
	bws_entity_lookup_browse_terms()
);
$GLOBALS['bws_test_caps'] = array( 'edit_posts' => true );

echo "\nbws_entity_lookup_resolve_term — resolve-by-id mode\n";
check(
	'a real, readable term resolves',
	array( 'id' => 5, 'label' => '#5 News', 'group' => 'Category', 'scope' => 'category' ),
	bws_entity_lookup_resolve_term( 5 )
);
check( 'a deleted term resolves to null (the picker\'s "(missing)" case)', null, bws_entity_lookup_resolve_term( 999 ) );
check(
	'a term in a taxonomy the user cannot read resolves to null too',
	null,
	bws_entity_lookup_resolve_term( 34 )
);
check( 'id <= 0 resolves to null without querying anything', null, bws_entity_lookup_resolve_term( 0 ) );

echo "\nbws_entity_lookup_post_type_readable — per-post-type narrowing (D19, ticket 03)\n";
$GLOBALS['bws_test_caps'] = array( 'edit_posts' => true );
check(
	'a post type whose edit_posts cap the user HOLDS is readable',
	true,
	bws_entity_lookup_post_type_readable( bws_test_post_type( 'post', 'Post', 'edit_posts' ) )
);
check(
	'…and one whose cap the user LACKS is not, even with the generic edit_posts',
	false,
	bws_entity_lookup_post_type_readable( bws_test_post_type( 'landing_page', 'Landing Page', 'edit_landing_pages' ) )
);

echo "\nbws_entity_lookup_post_type_statuses — QUERY-LEVEL status derivation, never per-post (D18, ticket 03)\n";
$GLOBALS['bws_test_caps'] = array();
check(
	'no capability at all: publish only',
	array( 'publish' ),
	bws_entity_lookup_post_type_statuses( bws_test_post_type( 'post', 'Post', 'edit_posts', 'read_private_posts' ) )
);
$GLOBALS['bws_test_caps'] = array( 'edit_posts' => true );
check(
	'edit_posts: publish + draft/pending/future, still no private',
	array( 'publish', 'draft', 'pending', 'future' ),
	bws_entity_lookup_post_type_statuses( bws_test_post_type( 'post', 'Post', 'edit_posts', 'read_private_posts' ) )
);
$GLOBALS['bws_test_caps'] = array( 'edit_posts' => true, 'read_private_posts' => true );
check(
	'edit_posts + read_private_posts: every status but trash',
	array( 'publish', 'draft', 'pending', 'future', 'private' ),
	bws_entity_lookup_post_type_statuses( bws_test_post_type( 'post', 'Post', 'edit_posts', 'read_private_posts' ) )
);
$GLOBALS['bws_test_caps'] = array( 'edit_posts' => true );

echo "\nbws_entity_lookup_post_row — the one shaper (D15, D18)\n";
check(
	'"#<id> <title>", grouped by the post type\'s singular label — no status suffix when published',
	array( 'id' => 5, 'label' => '#5 Hello World', 'group' => 'Post', 'scope' => 'post' ),
	bws_entity_lookup_post_row( bws_test_post( 5, 'Hello World', 'post', 'publish' ), 'Post' )
);
// The post half of the same distinction — slug, not label (D22).
check(
	'`scope` is the post-type SLUG, derived from the post itself rather than from the group label',
	'landing_page',
	bws_entity_lookup_post_row( bws_test_post( 34, 'Support', 'landing_page', 'publish' ), 'Landing Page' )['scope']
);
check(
	'a non-published status is shown in the row (D18: "pinning a draft is a real authoring case")',
	array( 'id' => 3, 'label' => '#3 A Draft (draft)', 'group' => 'Post', 'scope' => 'post' ),
	bws_entity_lookup_post_row( bws_test_post( 3, 'A Draft', 'post', 'draft' ), 'Post' )
);

echo "\nbws_entity_lookup_browse_posts — browse/search mode (D15, D17, D18)\n";
$GLOBALS['bws_test_caps'] = array( 'edit_posts' => true );
check(
	'opens BROWSABLE with no search — draft included (edit_posts held), private excluded, alphabetical (D17)',
	array(
		array( 'id' => 3, 'label' => '#3 A Draft (draft)', 'group' => 'Post', 'scope' => 'post' ),
		array( 'id' => 5, 'label' => '#5 Hello World', 'group' => 'Post', 'scope' => 'post' ),
	),
	bws_entity_lookup_browse_posts()
);
check(
	'a post type the user cannot read is silently absent, not an error',
	array(
		array( 'id' => 3, 'label' => '#3 A Draft (draft)', 'group' => 'Post', 'scope' => 'post' ),
		array( 'id' => 5, 'label' => '#5 Hello World', 'group' => 'Post', 'scope' => 'post' ),
	),
	bws_entity_lookup_browse_posts( '', '' )
);
check(
	'typing narrows the list (D17)',
	array( array( 'id' => 5, 'label' => '#5 Hello World', 'group' => 'Post', 'scope' => 'post' ) ),
	bws_entity_lookup_browse_posts( 'hello' )
);
check(
	'the post-type FILTER narrows to one group — never serialized, UI state only (D16)',
	array( array( 'id' => 5, 'label' => '#5 Hello World', 'group' => 'Post', 'scope' => 'post' ) ),
	bws_entity_lookup_browse_posts( 'hello', 'post' )
);
$GLOBALS['bws_test_caps'] = array( 'edit_posts' => true, 'edit_landing_pages' => true );
check(
	'a second post type the user CAN read joins the list',
	array(
		array( 'id' => 3, 'label' => '#3 A Draft (draft)', 'group' => 'Post', 'scope' => 'post' ),
		array( 'id' => 5, 'label' => '#5 Hello World', 'group' => 'Post', 'scope' => 'post' ),
		array( 'id' => 34, 'label' => '#34 Support', 'group' => 'Landing Page', 'scope' => 'landing_page' ),
	),
	bws_entity_lookup_browse_posts()
);
$GLOBALS['bws_test_caps'] = array( 'edit_posts' => true, 'edit_landing_pages' => true, 'read_private_posts' => true );
check(
	'read_private_posts additionally surfaces the private post — NO PER-POST CHECK, the status set widened at query level (D18)',
	array(
		array( 'id' => 3, 'label' => '#3 A Draft (draft)', 'group' => 'Post', 'scope' => 'post' ),
		array( 'id' => 5, 'label' => '#5 Hello World', 'group' => 'Post', 'scope' => 'post' ),
		array( 'id' => 7, 'label' => '#7 Secret (private)', 'group' => 'Post', 'scope' => 'post' ),
		array( 'id' => 34, 'label' => '#34 Support', 'group' => 'Landing Page', 'scope' => 'landing_page' ),
	),
	bws_entity_lookup_browse_posts()
);
$GLOBALS['bws_test_caps'] = array( 'edit_posts' => true );

echo "\nbws_entity_lookup_resolve_post — resolve-by-id mode (ticket 03)\n";
check(
	'a real, readable, published post resolves',
	array( 'id' => 5, 'label' => '#5 Hello World', 'group' => 'Post', 'scope' => 'post' ),
	bws_entity_lookup_resolve_post( 5 )
);
check(
	'a draft resolves too — a pin is not disturbed by the status it was made against (edit_posts held)',
	array( 'id' => 3, 'label' => '#3 A Draft (draft)', 'group' => 'Post', 'scope' => 'post' ),
	bws_entity_lookup_resolve_post( 3 )
);
check(
	'a private post resolves to null when the CURRENT user lacks read_private_posts — even though the post exists',
	null,
	bws_entity_lookup_resolve_post( 7 )
);
check( 'a deleted post resolves to null (the picker\'s "(missing)" case)', null, bws_entity_lookup_resolve_post( 999 ) );
check(
	'a post in a post type the user cannot read resolves to null too',
	null,
	bws_entity_lookup_resolve_post( 34 )
);
check( 'id <= 0 resolves to null without querying anything', null, bws_entity_lookup_resolve_post( 0 ) );

echo "\nbws_entity_lookup_permission_check — the trust seam + per-kind dispatch (D19)\n";
$GLOBALS['bws_test_trusted'] = true;
$GLOBALS['bws_test_caps']    = array( 'edit_posts' => true );
check(
	'trusted + kind-readable → true',
	true,
	bws_entity_lookup_permission_check( new BWS_Test_Request( array( 'kind' => 'term' ) ) )
);
check( 'trusted + post kind-readable → true', true, bws_entity_lookup_permission_check( new BWS_Test_Request( array( 'kind' => 'post' ) ) ) );
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
			array( 'id' => 3, 'label' => '#3 Announcements', 'group' => 'Category', 'scope' => 'category' ),
			array( 'id' => 5, 'label' => '#5 News', 'group' => 'Category', 'scope' => 'category' ),
		),
		'groups' => array(
			array( 'scope' => 'category', 'label' => 'Category' ),
		),
		'truncated' => false,
	),
	bws_entity_lookup_rest_response( new BWS_Test_Request( array( 'kind' => 'term' ) ) )
);
check(
	'mode=resolve returns a single row',
	array( 'row' => array( 'id' => 5, 'label' => '#5 News', 'group' => 'Category', 'scope' => 'category' ) ),
	bws_entity_lookup_rest_response( new BWS_Test_Request( array( 'kind' => 'term', 'mode' => 'resolve', 'id' => 5 ) ) )
);
check(
	'mode=browse for kind=post returns rows too (ticket 03)',
	array(
		'rows' => array(
			array( 'id' => 3, 'label' => '#3 A Draft (draft)', 'group' => 'Post', 'scope' => 'post' ),
			array( 'id' => 5, 'label' => '#5 Hello World', 'group' => 'Post', 'scope' => 'post' ),
		),
		'groups' => array(
			array( 'scope' => 'post', 'label' => 'Post' ),
		),
		'truncated' => false,
	),
	bws_entity_lookup_rest_response( new BWS_Test_Request( array( 'kind' => 'post' ) ) )
);
check(
	'mode=resolve for kind=post returns a single row',
	array( 'row' => array( 'id' => 5, 'label' => '#5 Hello World', 'group' => 'Post', 'scope' => 'post' ) ),
	bws_entity_lookup_rest_response( new BWS_Test_Request( array( 'kind' => 'post', 'mode' => 'resolve', 'id' => 5 ) ) )
);
check(
	'an unimplemented kind (user, D12) answers empty, never a REST error',
	array( 'rows' => array(), 'groups' => array(), 'truncated' => false ),
	bws_entity_lookup_rest_response( new BWS_Test_Request( array( 'kind' => 'user' ) ) )
);

echo "
the per-group cap and the group list
";
$GLOBALS['bws_test_caps'] = array( 'edit_posts' => true, 'assign_benefit_tier' => true, 'edit_landing_pages' => true );
for ( $i = 100; $i < 100 + BWS_ENTITY_LOOKUP_LIMIT_ONE_GROUP + 5; $i++ ) {
	$GLOBALS['bws_test_terms']['category'][] = bws_test_term( $i, 'Bulk ' . $i, 'category' );
	$GLOBALS['bws_test_posts']['post'][]     = bws_test_post( $i, 'Bulk ' . $i, 'post' );
}
$cat = array_filter( bws_entity_lookup_browse_terms(), function ( $r ) { return 'category' === $r['scope']; } );
check( 'unfiltered, a term group contributes at most the ALL-groups cap', BWS_ENTITY_LOOKUP_LIMIT_ALL_GROUPS, count( $cat ) );
check( 'with the group chosen, it gets the larger cap', BWS_ENTITY_LOOKUP_LIMIT_ONE_GROUP, count( bws_entity_lookup_browse_terms( '', 'category' ) ) );
check( 'a small group is untouched by the cap', 1, count( bws_entity_lookup_browse_terms( '', 'benefit_tier' ) ) );
$posts = array_filter( bws_entity_lookup_browse_posts(), function ( $r ) { return 'post' === $r['scope']; } );
check( 'unfiltered, a post-type group contributes at most the ALL-groups cap', BWS_ENTITY_LOOKUP_LIMIT_ALL_GROUPS, count( $posts ) );
check( 'with the type chosen, it gets the larger cap', BWS_ENTITY_LOOKUP_LIMIT_ONE_GROUP, count( bws_entity_lookup_browse_posts( '', 'post' ) ) );
$cut = false;
bws_entity_lookup_browse_terms( '', '', $cut );
check( 'a term group over the cap reports truncated', true, $cut );
$cut = false;
bws_entity_lookup_browse_terms( '', 'benefit_tier', $cut );
check( 'a group under the cap does not', false, $cut );
$cut = false;
bws_entity_lookup_browse_posts( '', '', $cut );
check( 'a post-type group over the cap reports truncated', true, $cut );
$cut = false;
bws_entity_lookup_browse_terms( '', 'category', $cut );
check( 'a chosen group over the larger cap still reports truncated', true, $cut );
check( 'the REST response carries truncated', true, bws_entity_lookup_rest_response( new BWS_Test_Request( array( 'kind' => 'term' ) ) )['truncated'] );
check(
	'...and clears it once the filter narrows to a group under the cap',
	false,
	bws_entity_lookup_rest_response( new BWS_Test_Request( array( 'kind' => 'term', 'group' => 'benefit_tier' ) ) )['truncated']
);
$q = end( $GLOBALS['bws_test_queries'] );
check(
	'the post browse selects ONLY the four row columns, never post_content',
	'wp_posts.ID, wp_posts.post_title, wp_posts.post_status, wp_posts.post_type',
	$q['fields']
);
check( 'the narrowed rows are kept out of the post cache (partial rows must not poison get_post)', false, $q['args']['cache_results'] );
check( 'post search matches the title only, never body or excerpt', array( 'post_title' ), $q['args']['search_columns'] );
check( 'the narrowing filter is removed once the query ran', array(), $GLOBALS['bws_test_filters']['posts_fields'] );
check(
	'the term group list is every readable taxonomy, whatever the rows hold',
	array(
		array( 'scope' => 'category', 'label' => 'Category' ),
		array( 'scope' => 'benefit_tier', 'label' => 'Benefit Tier' ),
	),
	bws_entity_lookup_rest_response( new BWS_Test_Request( array( 'kind' => 'term', 'q' => 'Support', 'group' => 'benefit_tier' ) ) )['groups']
);
// A registered-but-EMPTY type and taxonomy: readable, nothing in them, so nothing to filter to.
$GLOBALS['bws_test_post_types'][] = bws_test_post_type( 'revision', 'Revision', 'edit_posts' );
$GLOBALS['bws_test_taxonomies'][] = bws_test_tax( 'empty_tax', 'Empty Tax', 'edit_posts' );
check( 'an empty taxonomy is left out of the term group list', false, in_array( 'empty_tax', array_column( bws_entity_lookup_term_groups(), 'scope' ), true ) );
check( 'a post type holding nothing this user can browse is left out of the post group list', false, in_array( 'revision', array_column( bws_entity_lookup_post_groups(), 'scope' ), true ) );
check(
	'the post group list is every readable post type that holds posts',
	array(
		array( 'scope' => 'post', 'label' => 'Post' ),
		array( 'scope' => 'landing_page', 'label' => 'Landing Page' ),
	),
	bws_entity_lookup_post_groups()
);
check(
	'the inlined groups are one entry per served kind',
	array( 'term', 'post' ),
	array_keys( bws_entity_lookup_groups_by_kind() )
);
check( 'the inlined term groups match the route groups', bws_entity_lookup_term_groups(), bws_entity_lookup_groups_by_kind()['term'] );
$GLOBALS['bws_test_caps'] = array();
check(
	'a kind the user may not browse is PRESENT and empty, never omitted',
	array( 'term' => array(), 'post' => array() ),
	bws_entity_lookup_groups_by_kind()
);
$GLOBALS['bws_test_caps'] = array( 'edit_posts' => true );

echo "\n" . ( $failures ? "FAILED {$failures}/{$count}\n" : "PASSED {$count}/{$count}\n" );
exit( $failures ? 1 : 0 );
