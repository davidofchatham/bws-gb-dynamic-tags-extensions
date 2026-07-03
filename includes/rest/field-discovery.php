<?php
/**
 * Field-discovery REST service.
 *
 * Backs the `bws-field-combo` editor control (assets/js/field-combo-control.js).
 * Exposes registered field DEFINITIONS (ACF groups/fields, ACF options-page
 * fields, taxonomy-located term meta, and core `register_meta` keys) so the
 * editor can offer the author a searchable list of fields to read, in ANY
 * editor context (including WP Patterns / GP Elements / templates where the
 * GB-native selector shows nothing because it reads the container post's meta).
 *
 * Route: GET `bws-dynamic-tags/v1/fields`
 *
 * Design invariants (SPEC.md §V, field-selector plan):
 * - V5: reads field DEFINITIONS only, never a value-time postmeta scan. The
 *   contexts this service most exists to fix (Patterns/Elements) have no bound
 *   post instance, so a value-time scan would be empty there anyway; a broad
 *   `$wpdb DISTINCT meta_key` scan is label-less/type-less key-soup and is
 *   rejected on quality. Unregistered-key gap is covered by the control's
 *   free-text entry (+ future Pie-Calendar injection).
 * - V6: offered ⟺ resolvable. `edit_posts` cap gates the route; output is
 *   filtered through the SAME DISALLOWED_KEYS gate `bws_read_field` enforces
 *   (field-helpers.php:235), so the endpoint never offers a key the resolver
 *   would refuse. It does NOT hide general `_`-protected meta (the resolver
 *   deliberately allows those on the frontend, field-helpers.php:233).
 * - V7: response envelope is keyed by resolved-source KIND (`post`/`term`/
 *   `site`); dedupe happens within a (kind, scope) bucket only.
 *
 * @since 1.13.0
 * @package BWS_Dynamic_Tags
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST namespace + route for field discovery.
 *
 * Plugin-scoped namespace (`bws-dynamic-tags/v1`) avoids collision with sibling
 * BWS plugins that share the `bws_` prefix. First REST route in this plugin.
 */
const BWS_FIELD_DISCOVERY_REST_NAMESPACE = 'bws-dynamic-tags/v1';
const BWS_FIELD_DISCOVERY_REST_ROUTE     = '/fields';

/**
 * Register the field-discovery REST route.
 *
 * Hooked on `rest_api_init` (fires per-request, after plugins_loaded), so the
 * route is available whenever a REST request comes in from the editor control.
 *
 * @since 1.13.0
 * @return void
 */
function bws_register_field_discovery_route() {
	register_rest_route(
		BWS_FIELD_DISCOVERY_REST_NAMESPACE,
		BWS_FIELD_DISCOVERY_REST_ROUTE,
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'bws_field_discovery_rest_response',
			'permission_callback' => 'bws_field_discovery_permission_check',
			'args'                => array(),
		)
	);
}

/**
 * Permission callback — `edit_posts` cap (V6).
 *
 * Field discovery exposes only field DEFINITIONS (never values), but the list of
 * registered fields is still author-only editor tooling, so it is gated to users
 * who can edit content. Matches the audience of the block editor itself.
 *
 * @since 1.13.0
 * @return bool True when the current user may edit posts.
 */
function bws_field_discovery_permission_check() {
	return current_user_can( 'edit_posts' );
}

/**
 * REST callback — assemble and return the kind-keyed field envelope.
 *
 * Assembles field definitions fresh (ACF enumeration measured ~13ms — no cache
 * needed) and runs the result through the DISALLOWED_KEYS gate (V6). This handler
 * is the SAME code path the editor consumes via `rest_preload_api_request` at
 * page render, so the field list is current on every editor load with no cache to
 * invalidate.
 *
 * @since 1.13.0
 * @param WP_REST_Request $request The REST request.
 * @return WP_REST_Response Kind-keyed envelope `{ post:[], term:[], site:[] }`.
 */
function bws_field_discovery_rest_response( $request ) {
	$envelope = bws_field_discovery_collect();
	$envelope = bws_field_discovery_filter_disallowed( $envelope );

	return rest_ensure_response( $envelope );
}

/**
 * Return the DISALLOWED-filtered field envelope as a JSON string.
 *
 * Consumed by the enqueue path (`wp_add_inline_script`) to inline the envelope
 * as `window.bwsFieldEnvelope`, so the editor control reads it synchronously with
 * NO runtime REST request. Assembly is ~13ms and runs once per editor load, so
 * the inlined list is current every load. The REST route remains registered as a
 * fallback (and for non-editor consumers).
 *
 * @since 1.13.0
 * @return string JSON-encoded kind-keyed envelope.
 */
function bws_field_discovery_get_envelope_json() {
	$envelope = bws_field_discovery_collect();
	$envelope = bws_field_discovery_filter_disallowed( $envelope );

	$json = wp_json_encode( $envelope );

	// wp_json_encode returns false on malformed UTF-8 (user-authored ACF labels /
	// group titles) or JSON depth overflow (deeply nested repeater / flex). Falling
	// back to an empty object keeps the inlined `window.bwsFieldEnvelope = {...};`
	// statement syntactically valid; the control then fetches via the REST route.
	return ( false === $json ) ? '{}' : $json;
}

/**
 * Derive resolved-source KIND + candidate SCOPE from an ACF group location (V5, V7).
 *
 * ACF `location` is an array of OR-groups; each OR-group is an array of AND-rules
 * `{ param, operator, value }`. We scan for the location-param FAMILY that names a
 * resolved-source kind:
 *   - `post_type`     → kind `post`, scope = the post-type slugs
 *   - `taxonomy`      → kind `term`, scope = the taxonomy slugs
 *   - `options_page`  → kind `site`, scope = the options-page slugs
 *
 * KIND is candidate-level, not an exact runtime match: ACF rules AND/OR across
 * many params (page_template, post_format, custom rules) that we do not resolve.
 * Only `!=` / `==` operators contribute scope values; other operators still fix
 * the kind but leave scope open (empty).
 *
 * A group with no kind-bearing param (e.g. located purely by page_template or a
 * custom rule) returns kind `post` with empty scope: post is the safe default
 * resolved-source kind, and an empty scope means "any post type" client-side.
 *
 * Pure — takes the location array, returns `{ kind, scope[] }`.
 *
 * @since 1.13.0
 * @param array $location ACF group `location` (array of OR-groups of AND-rules).
 * @return array{kind:string,scope:array<int,string>} Kind + candidate scope slugs.
 */
function bws_field_discovery_derive_kind_scope( $location ) {
	$kind_by_param = array(
		'post_type'    => 'post',
		'taxonomy'     => 'term',
		'options_page' => 'site',
	);
	// First kind-bearing param decides the kind (post-type wins ties, then term,
	// then site — matches the src: axis priority; ties are rare misconfigs).
	$priority = array( 'post_type', 'taxonomy', 'options_page' );

	$kind  = '';
	$scope = array();

	if ( is_array( $location ) ) {
		foreach ( $priority as $param ) {
			foreach ( $location as $or_group ) {
				if ( ! is_array( $or_group ) ) {
					continue;
				}
				foreach ( $or_group as $rule ) {
					if ( ! is_array( $rule ) || ! isset( $rule['param'] ) || $rule['param'] !== $param ) {
						continue;
					}
					$kind = $kind_by_param[ $param ];
					$op   = isset( $rule['operator'] ) ? $rule['operator'] : '==';
					if ( ( '==' === $op || '!=' === $op ) && isset( $rule['value'] ) && '' !== $rule['value'] ) {
						$scope[] = (string) $rule['value'];
					}
				}
			}
			if ( '' !== $kind ) {
				break;
			}
		}
	}

	if ( '' === $kind ) {
		$kind = 'post';
	}

	return array(
		'kind'  => $kind,
		'scope' => array_values( array_unique( $scope ) ),
	);
}

/**
 * Flatten ACF fields (recursing sub-fields) into resolvable entries (V8).
 *
 * Surfaces sub-fields with the CORRECT resolution key:
 *   - top-level field      → `name`, context_hint `field`
 *   - GROUP child          → `parent_child` composite (stable, resolves via
 *     get_post_meta everywhere), context_hint `field`
 *   - REPEATER / FLEXIBLE child → BARE child `name`, context_hint `row`
 *     (resolves only inside a query loop over that repeater, Mode 2b,
 *     field-helpers.php:253-255)
 *
 * Recurses `sub_fields` (group + repeater) and flexible-content
 * `layouts[].sub_fields`. `parent_path` accumulates a human breadcrumb for the
 * UI ("Event Details › Sessions › Title"); it is NOT the resolution key.
 *
 * Pure — takes the ACF field array, returns a flat list of entries.
 *
 * @since 1.13.0
 * @param array  $fields      ACF fields (from `acf_get_fields`, or a `sub_fields`).
 * @param string $parent_path Breadcrumb prefix (UI only), '' at top level.
 * @param string $group_key   ACF group name of the enclosing GROUP field, or '' if
 *                            the parent is not a group (top level, repeater, flex).
 * @return array<int,array{name:string,label:string,type:string,return_format:?string,context_hint:string,parent_path:string}>
 */
function bws_field_discovery_flatten_fields( $fields, $parent_path = '', $group_key = '' ) {
	$out = array();
	if ( ! is_array( $fields ) ) {
		return $out;
	}

	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) || ! isset( $field['name'] ) || '' === $field['name'] ) {
			continue;
		}

		$name  = (string) $field['name'];
		$type  = isset( $field['type'] ) ? (string) $field['type'] : '';
		$label = isset( $field['label'] ) && '' !== $field['label'] ? (string) $field['label'] : $name;
		$rf    = isset( $field['return_format'] ) ? (string) $field['return_format'] : null;

		// A GROUP child resolves as the ACF composite `group_bare` key; carry the
		// enclosing group name down so the child emits the composite.
		$resolution_key = ( '' !== $group_key ) ? $group_key . '_' . $name : $name;

		// context_hint: a group child is a stable meta key (`field`); a repeater/flex
		// child only resolves in row context (`row`). A field is a "row" child when
		// its parent WAS a repeater/flex — signalled by group_key === '' AND we are
		// below top level. We track that via the recursion below, not here, so the
		// context_hint is set by the caller of the recursive branch (see below).
		$context_hint = ( '' !== $group_key ) ? 'field' : 'field';

		$out[] = array(
			'name'          => $resolution_key,
			'label'         => $label,
			'type'          => $type,
			'return_format' => $rf,
			'context_hint'  => $context_hint,
			'parent_path'   => $parent_path,
		);

		$child_path = ( '' === $parent_path ) ? $label : $parent_path . ' › ' . $label;

		// GROUP → children resolve as composite keys, stable everywhere.
		if ( 'group' === $type && ! empty( $field['sub_fields'] ) ) {
			foreach ( bws_field_discovery_flatten_fields( $field['sub_fields'], $child_path, $resolution_key ) as $child ) {
				$out[] = $child;
			}
		}

		// REPEATER → children resolve by bare name in row context only.
		if ( 'repeater' === $type && ! empty( $field['sub_fields'] ) ) {
			foreach ( bws_field_discovery_flatten_fields( $field['sub_fields'], $child_path, '' ) as $child ) {
				$child['context_hint'] = 'row';
				$out[]                 = $child;
			}
		}

		// FLEXIBLE CONTENT → each layout's sub_fields, bare name in row context.
		if ( 'flexible_content' === $type && ! empty( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
			foreach ( $field['layouts'] as $layout ) {
				if ( empty( $layout['sub_fields'] ) ) {
					continue;
				}
				$layout_label = isset( $layout['label'] ) && '' !== $layout['label'] ? (string) $layout['label'] : $child_path;
				$layout_path  = ( '' === $parent_path ) ? $layout_label : $parent_path . ' › ' . $layout_label;
				foreach ( bws_field_discovery_flatten_fields( $layout['sub_fields'], $layout_path, '' ) as $child ) {
					$child['context_hint'] = 'row';
					$out[]                 = $child;
				}
			}
		}
	}

	return $out;
}

/**
 * Build one group envelope entry from an ACF group + its flattened fields.
 *
 * Pure — takes the group array (for title + location) and the field list.
 *
 * @since 1.13.0
 * @param array $group  ACF group array (`title`, `location`).
 * @param array $fields Flattened field entries (from bws_field_discovery_flatten_fields).
 * @return array{group_title:string,kind:string,scope:array,fields:array}
 */
function bws_field_discovery_group_entry( $group, $fields ) {
	$location    = isset( $group['location'] ) ? $group['location'] : array();
	$kind_scope  = bws_field_discovery_derive_kind_scope( $location );
	$group_title = isset( $group['title'] ) && '' !== $group['title'] ? (string) $group['title'] : '';

	return array(
		'group_title' => $group_title,
		'kind'        => $kind_scope['kind'],
		'scope'       => $kind_scope['scope'],
		'source'      => 'acf',
		'fields'      => array_values( $fields ),
	);
}

/**
 * Convert core `register_meta` keys into a synthetic group entry (V5).
 *
 * `get_registered_meta_keys()` returns a map of `key => args`; ACF fields are NOT
 * registered there by default, so this is a COMPLEMENTARY source for non-ACF
 * registered meta. All entries land in one synthetic group so the client can
 * render them together; label falls back to the key.
 *
 * Pure — takes the registered-meta map, returns a group entry (or null if empty).
 *
 * @since 1.13.0
 * @param array  $meta_map     `key => args` from get_registered_meta_keys.
 * @param string $kind         Resolved-source kind (`post`/`term`).
 * @param string $scope        Single scope slug (post type or taxonomy), or ''.
 * @param string $group_title  Heading for the synthetic group.
 * @return array|null Group entry, or null when the map has no usable keys.
 */
function bws_field_discovery_registered_meta_group( $meta_map, $kind, $scope, $group_title ) {
	if ( ! is_array( $meta_map ) || empty( $meta_map ) ) {
		return null;
	}

	$fields = array();
	foreach ( $meta_map as $key => $args ) {
		$key = (string) $key;
		if ( '' === $key ) {
			continue;
		}
		$label = $key;
		if ( is_array( $args ) && isset( $args['description'] ) && '' !== $args['description'] ) {
			$label = (string) $args['description'];
		}
		$fields[] = array(
			'name'          => $key,
			'label'         => $label,
			'type'          => '',
			'return_format' => null,
			'context_hint'  => 'field',
			'parent_path'   => '',
		);
	}

	if ( empty( $fields ) ) {
		return null;
	}

	return array(
		'group_title' => $group_title,
		'kind'        => $kind,
		'scope'       => ( '' === $scope ) ? array() : array( $scope ),
		'source'      => 'registered',
		'fields'      => $fields,
	);
}

/**
 * Collect field definitions into a kind-keyed envelope.
 *
 * Envelope shape (V7): `array( 'post' => [], 'term' => [], 'site' => [] )` where
 * each kind holds group entries `{ group_title, kind, scope, fields:[...] }`.
 *
 * Orchestration only — the pure per-group transforms (kind/scope derivation,
 * sub-field flatten, group-entry assembly) live in the helpers above so the T11
 * harness can drive them without ACF/WP. This function reads the live ACF +
 * core-meta sources (all `function_exists`-guarded, C5) and routes each group
 * entry into its kind bucket. Dedupe within (kind, scope) is applied in T3.
 *
 * @since 1.13.0
 * @return array<string,array<int,array<string,mixed>>> Kind-keyed envelope.
 */
function bws_field_discovery_collect() {
	$envelope = array(
		'post' => array(),
		'term' => array(),
		'site' => array(),
	);

	// ACF field groups (post, term, and options-page/site all arrive here — the
	// group location determines the kind). No post_type filter: fetch ALL, the
	// client filters by kind + scope (field-selector plan §Filter location).
	if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
		$groups = acf_get_field_groups();
		if ( is_array( $groups ) ) {
			foreach ( $groups as $group ) {
				if ( ! is_array( $group ) || empty( $group['key'] ) ) {
					continue;
				}
				$acf_fields = acf_get_fields( $group['key'] );
				$flattened  = bws_field_discovery_flatten_fields( is_array( $acf_fields ) ? $acf_fields : array() );
				$entry      = bws_field_discovery_group_entry( $group, $flattened );
				if ( empty( $entry['fields'] ) ) {
					continue;
				}
				$kind = $entry['kind'];
				if ( isset( $envelope[ $kind ] ) ) {
					$envelope[ $kind ][] = $entry;
				}
			}
		}
	}

	// Core registered post meta (non-ACF). Complementary source only.
	if ( function_exists( 'get_registered_meta_keys' ) ) {
		$post_meta = get_registered_meta_keys( 'post' );
		$reg_group = bws_field_discovery_registered_meta_group(
			is_array( $post_meta ) ? $post_meta : array(),
			'post',
			'',
			__( 'Registered post meta', 'generateblocks' )
		);
		if ( $reg_group ) {
			$envelope['post'][] = $reg_group;
		}

		$term_meta      = get_registered_meta_keys( 'term' );
		$reg_term_group = bws_field_discovery_registered_meta_group(
			is_array( $term_meta ) ? $term_meta : array(),
			'term',
			'',
			__( 'Registered term meta', 'generateblocks' )
		);
		if ( $reg_term_group ) {
			$envelope['term'][] = $reg_term_group;
		}
	}

	// Dedupe within (kind, scope) — ACF metadata wins (T3).
	$envelope = bws_field_discovery_dedupe( $envelope );

	return $envelope;
}

/**
 * Dedupe fields within each (kind, scope) bucket (V7).
 *
 * Dedupe key = field resolution NAME, within one KIND, where two entries' scopes
 * OVERLAP (share a scope slug, or either scope is empty = "any scope", which
 * overlaps everything). A `post` field and a `term` field of the same name are
 * NEVER merged (different kind = different storage/read path = different field).
 *
 * Precedence when two overlapping entries collide on name:
 *   - ACF beats registered-meta (`source:'acf'` > `source:'registered'`). Both
 *     read the identical raw key at runtime, so it IS one field; ACF's label +
 *     type describe it better, so the ACF entry is kept and the registered one
 *     dropped.
 *   - ACF-vs-ACF (rare misconfig) → first-seen wins (deterministic ACF group
 *     order).
 *   - registered-vs-registered → first-seen wins.
 *
 * Dedupe removes the losing field from its group; groups emptied by dedupe are
 * pruned. Group order and non-duplicate fields are otherwise preserved.
 *
 * Pure — takes the envelope, returns a deduped copy.
 *
 * @since 1.13.0
 * @param array $envelope Kind-keyed envelope.
 * @return array Deduped envelope.
 */
function bws_field_discovery_dedupe( array $envelope ) {
	foreach ( $envelope as $kind => $groups ) {
		if ( ! is_array( $groups ) ) {
			continue;
		}

		// Winners seen so far in this kind: name => list of { scope[], source }.
		$seen = array();

		foreach ( $groups as $gi => $group ) {
			if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
				continue;
			}
			$group_scope  = isset( $group['scope'] ) && is_array( $group['scope'] ) ? $group['scope'] : array();
			$group_source = isset( $group['source'] ) ? (string) $group['source'] : 'acf';

			$kept = array();
			foreach ( $group['fields'] as $field ) {
				$name = isset( $field['name'] ) ? (string) $field['name'] : '';
				if ( '' === $name ) {
					$kept[] = $field;
					continue;
				}

				// Dedupe collapses ONLY an ACF-vs-registered-meta collision (same raw key
				// described by both an ACF field and a bare register_meta entry — ACF
				// wins). Two ACF fields sharing a bare name are DISTINCT fields (e.g. a
				// `description` sub-field in two different repeaters — ACF stores repeater
				// children by bare name, so the collision is expected and both must
				// survive; the client merges/labels them). So NEVER drop ACF-vs-ACF.
				$dropped = false;
				if ( isset( $seen[ $name ] ) ) {
					foreach ( $seen[ $name ] as $prior ) {
						if ( ! bws_field_discovery_scopes_overlap( $group_scope, $prior['scope'] ) ) {
							continue;
						}
						if ( 'registered' === $group_source && 'acf' === $prior['source'] ) {
							// Current registered-meta duplicate of a prior ACF field → drop it.
							$dropped = true;
							break;
						}
						if ( 'acf' === $group_source && 'registered' === $prior['source'] ) {
							// Current ACF displaces a prior registered-meta entry.
							bws_field_discovery_remove_field( $envelope, $kind, $name, $prior['scope'] );
							continue;
						}
						if ( 'registered' === $group_source && 'registered' === $prior['source'] ) {
							// registered-vs-registered → first-seen wins.
							$dropped = true;
							break;
						}
						// acf-vs-acf → keep both (distinct fields).
					}
				}

				if ( $dropped ) {
					continue;
				}

				$kept[]          = $field;
				$seen[ $name ][] = array(
					'scope'  => $group_scope,
					'source' => $group_source,
				);
			}

			$group['fields']            = array_values( $kept );
			$envelope[ $kind ][ $gi ]   = $group;
		}

		// Prune groups emptied by dedupe.
		$envelope[ $kind ] = array_values(
			array_filter(
				$envelope[ $kind ],
				static function ( $g ) {
					return ! empty( $g['fields'] );
				}
			)
		);
	}

	return $envelope;
}

/**
 * Two candidate scopes overlap when they share a slug, or either is empty.
 *
 * Empty scope = "any scope" (candidate-level unknown), which overlaps every
 * scope — so an unscoped ACF field dedupes against a scoped one of the same name.
 *
 * @since 1.13.0
 * @param array $a Scope slugs.
 * @param array $b Scope slugs.
 * @return bool True when the scopes overlap.
 */
function bws_field_discovery_scopes_overlap( $a, $b ) {
	if ( empty( $a ) || empty( $b ) ) {
		return true;
	}
	return array_intersect( $a, $b ) !== array();
}

/**
 * Remove a field by name from all overlapping-scope groups of one kind.
 *
 * Used by dedupe when a later ACF field must displace an earlier registered-meta
 * field already kept. Operates in place on the envelope.
 *
 * @since 1.13.0
 * @param array  $envelope    Kind-keyed envelope (by reference).
 * @param string $kind        Kind bucket to edit.
 * @param string $name        Field resolution name to remove.
 * @param array  $prior_scope Scope of the winner (overlap gate).
 * @return void
 */
function bws_field_discovery_remove_field( array &$envelope, $kind, $name, $prior_scope ) {
	if ( empty( $envelope[ $kind ] ) ) {
		return;
	}
	foreach ( $envelope[ $kind ] as $gi => $group ) {
		if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
			continue;
		}
		$group_scope = isset( $group['scope'] ) && is_array( $group['scope'] ) ? $group['scope'] : array();
		if ( ! bws_field_discovery_scopes_overlap( $group_scope, $prior_scope ) ) {
			continue;
		}
		$envelope[ $kind ][ $gi ]['fields'] = array_values(
			array_filter(
				$group['fields'],
				static function ( $f ) use ( $name ) {
					return ( isset( $f['name'] ) ? (string) $f['name'] : '' ) !== $name;
				}
			)
		);
	}
}

/**
 * Filter a kind-keyed envelope through the DISALLOWED_KEYS gate (V6).
 *
 * Offered ⟺ resolvable: strips any field whose resolution key is in
 * `GenerateBlocks_Dynamic_Tag_Security::DISALLOWED_KEYS`, the same list
 * `bws_read_field` refuses (field-helpers.php:235). Does NOT strip general
 * `_`-protected meta — the resolver allows those on the frontend
 * (field-helpers.php:233), so Pie Calendar `_piecal_*` etc. stay offerable.
 *
 * Pure function (takes the envelope, returns a filtered copy) so the T11 harness
 * can assert the gate without a live GB install; when the Security class is
 * absent the envelope passes through unchanged.
 *
 * @since 1.13.0
 * @param array<string,array<int,array<string,mixed>>> $envelope Kind-keyed envelope.
 * @return array<string,array<int,array<string,mixed>>> Filtered envelope.
 */
function bws_field_discovery_filter_disallowed( array $envelope ) {
	if ( ! class_exists( 'GenerateBlocks_Dynamic_Tag_Security' ) ) {
		return $envelope;
	}

	$disallowed = GenerateBlocks_Dynamic_Tag_Security::DISALLOWED_KEYS;
	if ( empty( $disallowed ) ) {
		return $envelope;
	}

	foreach ( $envelope as $kind => $groups ) {
		foreach ( $groups as $g => $group ) {
			if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
				continue;
			}
			$group['fields'] = array_values(
				array_filter(
					$group['fields'],
					static function ( $field ) use ( $disallowed ) {
						$key = isset( $field['name'] ) ? (string) $field['name'] : '';
						return '' === $key || ! in_array( $key, $disallowed, true );
					}
				)
			);
			$envelope[ $kind ][ $g ] = $group;
		}
	}

	return $envelope;
}
