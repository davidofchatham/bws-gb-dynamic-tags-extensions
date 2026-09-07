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
 *   - `mode=browse` (the default) — a flat, taxonomy-grouped list of terms. Supports `q`
 *     (free-text, matched against the term name — no minimum length, D17) and `tax`
 *     (narrow to one taxonomy; UI STATE ONLY, never part of the stored wire — D16).
 *   - `mode=resolve` — one id in, one row out (or null), for the picker's reopen label,
 *     which the preview text and the D22 field-scoping filter both need too.
 *
 * `kind` selects the entity vocabulary. Only `term` is wired (FW-39 ticket 02); `post` is
 * D13's own ticket. An unrecognized or unimplemented kind answers an empty result rather
 * than a REST error — the same "offering is not resolving" posture the chain-root enum
 * takes, so a kind this route does not yet serve degrades to an empty picker instead of a
 * broken one.
 *
 * PERMISSIONS (D19): the SAME plugin-wide trust seam field-discovery gates on
 * (`bws_gb_user_can_author_dynamic_data()`), plus a PER-KIND capability predicate this
 * route dispatches to (`bws_entity_lookup_kind_readable()`) rather than one callback
 * branching on `$kind` internally — terms and posts have genuinely different capability
 * models, and a single branching callback would reduce two recorded facts to one site in
 * the trust census (gb-trust-boundary-test.php scans the whole tree for exactly this
 * reason). EVERY REGISTERED taxonomy is a candidate, not public-only (D19): a public-only
 * filter would hide the private editorial taxonomies pinning exists to reach.
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
 * ONE ROW PER KIND, dispatched to rather than branched on, so a kind added later (post,
 * D13) is a new case here rather than a new `if` inside a callback that already has one.
 * `term` is generous by DESIGN: `edit_posts` covers every ordinary editor, because a
 * pinned term is exactly as reachable to them as a pinned relationship field is — the
 * per-TAXONOMY filter below is what actually narrows the list to what they may see; this
 * predicate answers "may this user use the picker AT ALL", not "which rows".
 *
 * @since 1.20.0
 * @param string $kind Resolved-source kind ('term', eventually 'post').
 * @return bool
 */
function bws_entity_lookup_kind_readable( string $kind ): bool {
	switch ( $kind ) {
		case 'term':
			return current_user_can( 'edit_posts' );
		default:
			// An unimplemented or unrecognized kind offers nothing rather than erroring —
			// "offering is not resolving" run the other way: a kind not yet SERVED is not
			// a broken request, it is an empty one.
			return false;
	}
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

	$q    = (string) $request->get_param( 'q' );
	$tax  = (string) $request->get_param( 'tax' );
	$rows = $fns['browse'] ? call_user_func( $fns['browse'], $q, $tax ) : array();

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
