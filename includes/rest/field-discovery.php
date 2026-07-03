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
 * Collects field definitions (T2), dedupes within (kind, scope) (T3), then runs
 * the whole envelope through the DISALLOWED_KEYS gate (V6) before returning.
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
 * Collect field definitions into a kind-keyed envelope.
 *
 * Envelope shape (V7): `array( 'post' => [], 'term' => [], 'site' => [] )` where
 * each kind holds group entries `{ group_title, kind, scope, fields:[...] }`.
 *
 * NOTE (T1 scaffold): returns the empty envelope shape only. ACF/options-page/
 * term-meta/`register_meta` discovery + sub-field recursion + kind/scope
 * derivation land in T2; dedupe lands in T3. Discovery helpers are authored as
 * PURE functions that take the ACF arrays as arguments (not calling `acf_get_*`
 * inline) so `tools/test/field-discovery-test.php` (T11) can drive them.
 *
 * @since 1.13.0
 * @return array<string,array<int,array<string,mixed>>> Kind-keyed envelope.
 */
function bws_field_discovery_collect() {
	return array(
		'post' => array(),
		'term' => array(),
		'site' => array(),
	);
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
