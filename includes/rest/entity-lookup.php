<?php
/**
 * Entity-lookup REST service — backs the `bws-entity-picker` control (FW-39).
 *
 * DELIBERATELY NOT the field-discovery envelope shape (D14). Field discovery ships one
 * kind-keyed envelope up front because a field VOCABULARY is bounded — a taxonomy or post
 * type has a fixed, small set of fields, and enumerating all of it costs nothing. An
 * ENTITY vocabulary is not bounded the same way: a site can carry thousands of terms and
 * hundreds of thousands of posts, so this route is queried on demand (browse/search) and
 * resolves one id at a time on reopen, rather than shipping a list an author never scrolls
 * to the end of. Say so here, not only in the spec, because "make it consistent with field
 * discovery" is exactly the fix a future reader will reach for.
 *
 * Route: GET `bws-dynamic-tags/v1/entities`
 *
 * Two modes, one route (D14):
 *   - `mode=browse` (the default) — a flat, grouped list of entities. Supports `q`
 *     (free-text, matched against the entity's name/title — no minimum length, D17) and
 *     the KIND-NEUTRAL `group` param (narrow to one taxonomy for `term`, one post type for
 *     `post`; UI STATE ONLY, never part of the stored wire — D16).
 *   - `mode=resolve` — one id in, one row out (or null), for the picker's reopen label,
 *     which the preview text and the D22 field-scoping filter both need too.
 *
 * `kind` selects the entity vocabulary. `term` and `post` are both wired (FW-39 tickets 02
 * and 03); `user` is designed, not built (D12) — an unrecognized or unimplemented kind
 * answers an empty result rather than a REST error, the same "offering is not resolving"
 * posture the chain-root enum takes, so a kind this route does not yet serve degrades to
 * an empty picker instead of a broken one.
 *
 * PERMISSIONS (D19): the SAME plugin-wide trust seam field-discovery gates on
 * (`bws_gb_user_can_author_dynamic_data()`), plus a PER-KIND capability predicate this
 * route DISPATCHES TO (`bws_entity_lookup_kind_readable()` reads a table of named
 * functions, one per kind — `bws_entity_lookup_term_kind_readable()` /
 * `bws_entity_lookup_post_kind_readable()`) rather than one callback branching on `$kind`
 * internally. Terms and posts have genuinely different capability models — a taxonomy's
 * `assign_terms` cap narrows term candidates, a post type's `edit_posts`/
 * `read_private_posts` caps plus a QUERY-LEVEL status set narrow post candidates (D18) —
 * and a single branching callback would reduce two recorded facts to one site in the trust
 * census (gb-trust-boundary-test.php scans the whole tree for exactly this reason: two
 * named predicate functions are two census sites, one branching callback would be one).
 * EVERY REGISTERED taxonomy and post type is a candidate, not public-only (D19): a
 * public-only filter would hide the private editorial taxonomies and post types pinning
 * exists to reach.
 *
 * @since 1.20.0
 * @package BWS_Dynamic_Tags
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BWS_ENTITY_LOOKUP_REST_ROUTE = '/entities';

/**
 * Register the entity-lookup REST route.
 *
 * @since 1.20.0
 * @return void
 */
function bws_register_entity_lookup_route() {
	register_rest_route(
		BWS_FIELD_DISCOVERY_REST_NAMESPACE,
		BWS_ENTITY_LOOKUP_REST_ROUTE,
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'bws_entity_lookup_rest_response',
			'permission_callback' => 'bws_entity_lookup_permission_check',
			'args'                => array(),
		)
	);
}

/**
 * Permission callback — the plugin's trust seam, plus a PER-KIND capability predicate.
 *
 * `edit_posts` is deliberately NOT required here on its own: field discovery gates on it
 * because every field-bearing context is post-adjacent, but a term picker is reachable by
 * an editor who can manage terms and nothing else. The trust seam
 * (`bws_gb_user_can_author_dynamic_data()`) is the gate that actually matters — same
 * reasoning as field-discovery's V6 REACHABILITY note: a user who fails it cannot reach
 * the tag builder by any path GB exposes, dynamic-data controls included.
 *
 * @since 1.20.0
 * @param WP_REST_Request $request The REST request.
 * @return bool
 */
function bws_entity_lookup_permission_check( $request ) {
	if ( function_exists( 'bws_gb_user_can_author_dynamic_data' ) && ! bws_gb_user_can_author_dynamic_data() ) {
		return false;
	}

	$kind = (string) $request->get_param( 'kind' );
	if ( '' === $kind ) {
		$kind = 'term';
	}

	return bws_entity_lookup_kind_readable( $kind );
}

/**
 * Whether the current user may browse ANY entity of the given kind — the per-kind half
 * of D19's two-gate rule.
 *
 * DISPATCHES to a named predicate per kind (bws_entity_lookup_kind_readable_predicates()),
 * never branches on `$kind` itself — the same shape bws_entity_lookup_kind_functions()
 * already takes for the data-fetch half, and for the same reason (FW-39 ticket 03, D19):
 * "two capability models" wants two FUNCTIONS, not two cases inside one, so the trust
 * census (gb-trust-boundary-test.php) sees two sites rather than one that happens to
 * branch. An unrecognized or unimplemented kind offers nothing rather than erroring —
 * "offering is not resolving" run the other way: a kind not yet SERVED is not a broken
 * request, it is an empty one.
 *
 * @since 1.20.0
 * @param string $kind Resolved-source kind ('term', 'post').
 * @return bool
 */
function bws_entity_lookup_kind_readable( string $kind ): bool {
	$fn = bws_entity_lookup_kind_readable_predicates()[ $kind ] ?? null;
	return $fn ? (bool) call_user_func( $fn ) : false;
}

/**
 * Resolved-source KIND → the named predicate answering "may this user use the picker AT
 * ALL for this kind" (D19's kind-level gate, as opposed to the per-taxonomy/per-post-type
 * row-level narrowing below it).
 *
 * ONE ROW PER KIND, on the same terms as bws_entity_lookup_kind_functions()'s dispatch
 * table just below — a kind added later is a row here, never a new branch inside
 * bws_entity_lookup_kind_readable().
 *
 * @since 1.20.0
 * @return array<string,callable> Kind => predicate function name.
 */
function bws_entity_lookup_kind_readable_predicates(): array {
	return array(
		'term' => 'bws_entity_lookup_term_kind_readable',
		'post' => 'bws_entity_lookup_post_kind_readable',
	);
}

/**
 * `term` kind-level gate (D19). `edit_posts` is generous by DESIGN: it covers every
 * ordinary editor, because a pinned term is exactly as reachable to them as a pinned
 * relationship field is — the per-TAXONOMY filter (`bws_entity_lookup_taxonomy_readable()`)
 * is what actually narrows the list to what they may see; this predicate answers "may this
 * user use the picker AT ALL", not "which rows".
 *
 * @since 1.20.0
 * @return bool
 */
function bws_entity_lookup_term_kind_readable(): bool {
	return current_user_can( 'edit_posts' );
}

/**
 * `post` kind-level gate (D19, FW-39 ticket 03). Same floor as the term gate — `edit_posts`
 * — for the same reason: it answers "may this user use the picker at all", and the real
 * narrowing (which post types, which statuses) happens per-post-type at query level
 * (`bws_entity_lookup_post_type_readable()`, `bws_entity_lookup_post_type_statuses()`), not
 * here. A DIFFERENT floor is exactly what would make this a second, needlessly divergent
 * capability model for the same "may this user open the picker" question both kinds ask.
 *
 * @since 1.20.0
 * @return bool
 */
function bws_entity_lookup_post_kind_readable(): bool {
	return current_user_can( 'edit_posts' );
}

/**
 * Whether the current user may see terms of ONE taxonomy — the row-level narrowing D19
 * asks for on top of the kind-level gate above.
 *
 * `assign_terms` rather than `edit_terms`/`manage_terms`: pinning a term is closer to
 * assigning one to content than to administering the taxonomy itself, and a contributor
 * who may tag a post with a term is the intended floor for browsing it here. A taxonomy
 * with no registered capabilities (the common case — most custom taxonomies never
 * customize `capabilities`) falls back to WordPress's own default meta caps, which
 * `current_user_can()` resolves the normal way.
 *
 * @since 1.20.0
 * @param WP_Taxonomy $taxonomy Registered taxonomy object.
 * @return bool
 */
function bws_entity_lookup_taxonomy_readable( $taxonomy ): bool {
	$cap = $taxonomy->cap->assign_terms ?? 'edit_posts';
	return current_user_can( $cap );
}

/**
 * Whether the current user may see posts of ONE post type AT ALL — the post-type-level
 * half of D19's row narrowing, on the same terms as bws_entity_lookup_taxonomy_readable()
 * above. `edit_posts` (the post type's own, not the generic capability) rather than
 * `assign_terms`'s posture: a post type with no customized `capabilities` (the common
 * case) falls back to WordPress's own default meta caps, which `current_user_can()`
 * resolves the normal way — identical shape to the taxonomy check, different capability
 * because posts and terms genuinely have different capability models (D19's own framing).
 *
 * @since 1.20.0
 * @param WP_Post_Type $post_type Registered post type object.
 * @return bool
 */
function bws_entity_lookup_post_type_readable( $post_type ): bool {
	$cap = $post_type->cap->edit_posts ?? 'edit_posts';
	return current_user_can( $cap );
}

/**
 * The STATUS SET a post type's candidates are queried against, derived from the current
 * user's capabilities ONCE per post type — never a per-post capability check over a browse
 * page (D18's explicit rule: "no per-post check over a browse page").
 *
 * `publish` is always in the set — it is what a reader with no special capability sees
 * anywhere else in the admin. `draft`/`pending`/`future` ride the SAME `edit_posts` cap
 * that already gates the whole post type (bws_entity_lookup_post_type_readable()): a user
 * who may edit this post type's posts may see its unpublished ones, matching WordPress's
 * own list-table posture. `private` rides its own, stricter `read_private_posts` cap,
 * because a private post is deliberately hidden from ordinary editors, not merely
 * unfinished. `trash` is EXCLUDED outright — a trashed post is not a candidate to pin,
 * which is a narrower design choice than "still exists" (resolve_root_argument() on
 * CurrentPost resolves a trashed post that was already pinned; this function governs only
 * what the BROWSE list offers going forward).
 *
 * A DIFFERENT AXIS FROM bws_source_gate() (traversal-pipeline.php), deliberately, not a
 * second uncoordinated statement of the one that already exists. bws_source_gate() answers
 * "may THIS request's VIEWER see this entity right now" at render time, for any visitor
 * including an anonymous one — a per-post EXISTS/VISIBLE question keyed on `read_post` plus
 * internal-status handling. This function answers "may THIS EDITOR, already past the
 * plugin-wide trust seam, browse candidates of this post type at all" — a per-POST-TYPE
 * capability question, asked once per browse/resolve call rather than once per candidate
 * (D18's explicit rule: no per-post check over a browse page). Routing this through
 * bws_source_gate() would mean a capability check per row on every browse request, which is
 * the exact cost D18 rules out.
 *
 * @since 1.20.0
 * @param WP_Post_Type $post_type Registered post type object.
 * @return string[] Post statuses to query.
 */
function bws_entity_lookup_post_type_statuses( $post_type ): array {
	$statuses = array( 'publish' );
	$edit_cap = $post_type->cap->edit_posts ?? 'edit_posts';
	if ( current_user_can( $edit_cap ) ) {
		$statuses[] = 'draft';
		$statuses[] = 'pending';
		$statuses[] = 'future';
	}
	$private_cap = $post_type->cap->read_private_posts ?? 'read_private_posts';
	if ( current_user_can( $private_cap ) ) {
		$statuses[] = 'private';
	}
	return $statuses;
}

/**
 * REST callback — dispatch to the requested mode.
 *
 * @since 1.20.0
 * @param WP_REST_Request $request The REST request.
 * @return WP_REST_Response
 */
function bws_entity_lookup_rest_response( $request ) {
	$kind = (string) $request->get_param( 'kind' );
	$kind = '' === $kind ? 'term' : $kind;
	$mode = (string) $request->get_param( 'mode' );
	$mode = '' === $mode ? 'browse' : $mode;

	$fns = bws_entity_lookup_kind_functions( $kind );

	if ( 'resolve' === $mode ) {
		$id  = (int) $request->get_param( 'id' );
		$row = $fns['resolve'] ? call_user_func( $fns['resolve'], $id ) : null;
		return rest_ensure_response( array( 'row' => $row ) );
	}

	$q      = (string) $request->get_param( 'q' );
	// A KIND-NEUTRAL param name, deliberately not `tax`: it narrows to one taxonomy for
	// `term` and one post type for `post` (D16 — UI state, never sent by the shipped
	// picker either way, but the REST contract itself must not promise a taxonomy answer
	// to an integrator reading it for `kind=post`).
	$group  = (string) $request->get_param( 'group' );
	$rows   = $fns['browse'] ? call_user_func( $fns['browse'], $q, $group ) : array();

	return rest_ensure_response( array( 'rows' => $rows ) );
}

/**
 * Resolved-source KIND → its browse/resolve functions — the data-fetch half's dispatch
 * table, on the same terms as bws_entity_lookup_kind_readable()'s permission dispatch.
 *
 * ONE ROW PER KIND, so a kind added later (`post`, D13) is a new row here rather than a
 * third `? :` branch threaded through the response callback — the shape
 * bws_entity_lookup_kind_readable()'s own docblock already commits to for the same
 * reason. A kind with no row (or a row missing a function) answers `null` for that
 * function, which the caller reads the same way an unrecognized kind reads: nothing,
 * never a REST error.
 *
 * @since 1.20.0
 * @param string $kind Resolved-source kind.
 * @return array{browse:?callable,resolve:?callable}
 */
function bws_entity_lookup_kind_functions( string $kind ): array {
	$table = array(
		'term' => array(
			'browse'  => 'bws_entity_lookup_browse_terms',
			'resolve' => 'bws_entity_lookup_resolve_term',
		),
		'post' => array(
			'browse'  => 'bws_entity_lookup_browse_posts',
			'resolve' => 'bws_entity_lookup_resolve_post',
		),
	);
	return $table[ $kind ] ?? array(
		'browse'  => null,
		'resolve' => null,
	);
}

/**
 * BROWSE/SEARCH mode for `term` — every readable taxonomy's terms, grouped, matched.
 *
 * NO MINIMUM-CHARACTER GATE (D17): an empty `$search` returns everything readable, which
 * is what "opens browsable" means. Matching is a plain case-insensitive substring against
 * the term NAME — this is a picker, not a search engine, and the population it serves
 * (an author who does not remember the exact name) is best served by the widest match
 * that still narrows.
 *
 * ALPHABETICAL WITHIN GROUP (D15's "opens browsable" reading) — `get_terms()`'s own
 * `orderby => 'name'` does this without a second sort here.
 *
 * @since 1.20.0
 * @param string $search Free-text filter, '' = none.
 * @param string $tax    Narrow to one taxonomy slug, '' = every readable one.
 * @return array[] `{ id, label, group }` rows. `label` is "#<id> <name>" (D15).
 */
function bws_entity_lookup_browse_terms( string $search = '', string $tax = '' ): array {
	if ( ! function_exists( 'get_taxonomies' ) ) {
		return array();
	}

	$taxonomies = array();
	foreach ( get_taxonomies( array(), 'objects' ) as $taxonomy ) {
		if ( '' !== $tax && $taxonomy->name !== $tax ) {
			continue;
		}
		if ( ! bws_entity_lookup_taxonomy_readable( $taxonomy ) ) {
			continue;
		}
		$taxonomies[] = $taxonomy;
	}

	$rows = array();
	foreach ( $taxonomies as $taxonomy ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy->name,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'search'     => $search,
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			continue;
		}
		foreach ( $terms as $term ) {
			$rows[] = bws_entity_lookup_term_row( $term, $taxonomy->labels->singular_name ?? $taxonomy->name );
		}
	}

	return $rows;
}

/**
 * RESOLVE mode for `term` — one id in, one row (or null) out.
 *
 * Deliberately re-derives the row through the SAME shaper the browse list uses
 * (bws_entity_lookup_term_row()), so the reopen label reads identically to the row an
 * author would have clicked — a second, hand-typed label format here is exactly the drift
 * the "one appender" rule exists to prevent elsewhere in this codebase.
 *
 * The existence check is `bws_get_validated_term()` (taxonomy-helpers.php) — the plugin's
 * ONE "get + validate a term by id" helper, already at 9+ call sites — not a second
 * hand-rolled `get_term()` / `is_wp_error()` pair. A term this plugin's other reads treat
 * as gone (a future WP falsy-but-not-WP_Error shape, say) must read as gone here too.
 *
 * @since 1.20.0
 * @param int $id Term id.
 * @return array|null `{ id, label, group }`, or null when the term does not exist or its
 *                    taxonomy is not readable by the current user.
 */
function bws_entity_lookup_resolve_term( int $id ) {
	if ( $id <= 0 || ! function_exists( 'bws_get_validated_term' ) ) {
		return null;
	}
	$term = bws_get_validated_term( $id );
	if ( ! $term ) {
		return null;
	}
	$taxonomy = get_taxonomy( $term->taxonomy );
	if ( ! $taxonomy || ! bws_entity_lookup_taxonomy_readable( $taxonomy ) ) {
		return null;
	}
	return bws_entity_lookup_term_row( $term, $taxonomy->labels->singular_name ?? $taxonomy->name );
}

/**
 * The ONE shaper of a term into a picker row — "#34 Support" (D15), grouped by taxonomy.
 *
 * @since 1.20.0
 * @param WP_Term $term  A term.
 * @param string  $group The taxonomy's singular label, for the group heading.
 * @return array{id:int,label:string,group:string}
 */
function bws_entity_lookup_term_row( $term, string $group ): array {
	return array(
		'id'    => (int) $term->term_id,
		'label' => '#' . $term->term_id . ' ' . $term->name,
		'group' => $group,
	);
}

/**
 * BROWSE/SEARCH mode for `post` — every readable post type's posts, grouped, matched
 * (FW-39 ticket 03).
 *
 * NO MINIMUM-CHARACTER GATE (D17), same as the term browse. Matching is WP's own `s`
 * search parameter — title and content — which is the same "widest match that still
 * narrows" posture the term browse takes with a plain substring, adapted to what
 * `get_posts()` already does well rather than a hand-rolled title-only filter.
 *
 * THE STATUS SET IS DERIVED PER POST TYPE, ONCE (D18) — bws_entity_lookup_post_type_statuses()
 * — and handed to `get_posts()` as its `post_status` argument, so nothing here inspects an
 * individual post's status to decide whether to keep it. `trash` is never in that set, so
 * trashed posts never reach this list (see that function's own doc for why).
 *
 * ALPHABETICAL WITHIN GROUP (D15), by title, matching the term browse's `orderby => name`.
 *
 * @since 1.20.0
 * @param string $search    Free-text filter, '' = none.
 * @param string $post_type_filter Narrow to one post type slug, '' = every readable one.
 * @return array[] `{ id, label, group }` rows. `label` is "#<id> <title>" with a
 *                 ` — <status>` suffix on anything not published (D18).
 */
function bws_entity_lookup_browse_posts( string $search = '', string $post_type_filter = '' ): array {
	if ( ! function_exists( 'get_post_types' ) ) {
		return array();
	}

	$post_types = array();
	foreach ( get_post_types( array(), 'objects' ) as $post_type ) {
		if ( '' !== $post_type_filter && $post_type->name !== $post_type_filter ) {
			continue;
		}
		if ( ! bws_entity_lookup_post_type_readable( $post_type ) ) {
			continue;
		}
		$post_types[] = $post_type;
	}

	$rows = array();
	foreach ( $post_types as $post_type ) {
		$posts = get_posts(
			array(
				'post_type'      => $post_type->name,
				'post_status'    => bws_entity_lookup_post_type_statuses( $post_type ),
				's'              => $search,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			)
		);
		foreach ( (array) $posts as $post ) {
			$rows[] = bws_entity_lookup_post_row( $post, $post_type->labels->singular_name ?? $post_type->name );
		}
	}

	return $rows;
}

/**
 * RESOLVE mode for `post` — one id in, one row (or null) out. Mirrors
 * bws_entity_lookup_resolve_term()'s shape exactly: re-derives the row through the SAME
 * shaper the browse list uses (bws_entity_lookup_post_row()), and re-checks BOTH gates a
 * pin outside the current browse could have moved past — its post type's readability, and
 * its OWN status against that post type's current status set — so a post whose type was
 * un-shared, or whose status a capability change no longer covers, resolves to null
 * exactly as a deleted one does, rather than showing a reopen label for something already
 * unreachable.
 *
 * @since 1.20.0
 * @param int $id Post id.
 * @return array|null `{ id, label, group }`, or null when the post does not exist, its
 *                     post type is not readable, or its status is outside the current
 *                     status set for that post type.
 */
function bws_entity_lookup_resolve_post( int $id ) {
	if ( $id <= 0 || ! function_exists( 'get_post' ) ) {
		return null;
	}
	$post = get_post( $id );
	if ( ! $post ) {
		return null;
	}
	$post_type = function_exists( 'get_post_type_object' ) ? get_post_type_object( $post->post_type ) : null;
	if ( ! $post_type || ! bws_entity_lookup_post_type_readable( $post_type ) ) {
		return null;
	}
	if ( ! in_array( $post->post_status, bws_entity_lookup_post_type_statuses( $post_type ), true ) ) {
		return null;
	}
	return bws_entity_lookup_post_row( $post, $post_type->labels->singular_name ?? $post_type->name );
}

/**
 * The ONE shaper of a post into a picker row — "#1692 Hello world!" (D15), grouped by post
 * type, with a ` (<status>)` suffix on anything not published (D18: "pinning a draft is a
 * real authoring case; doing it unknowingly is not").
 *
 * @since 1.20.0
 * @param WP_Post $post  A post.
 * @param string  $group The post type's singular label, for the group heading.
 * @return array{id:int,label:string,group:string}
 */
function bws_entity_lookup_post_row( $post, string $group ): array {
	$label = '#' . $post->ID . ' ' . $post->post_title;
	if ( 'publish' !== $post->post_status ) {
		$label .= ' (' . $post->post_status . ')';
	}
	return array(
		'id'    => (int) $post->ID,
		'label' => $label,
		'group' => $group,
	);
}
