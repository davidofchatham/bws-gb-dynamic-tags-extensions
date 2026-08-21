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
 * - V6: offered ⟺ resolvable. The `edit_posts` capability gates the route; output is
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
 * Permission callback — the `edit_posts` capability (V6).
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

	// JSON_HEX_TAG (+ AMP/APOS/QUOT) escapes <, >, &, ', " so an author-controlled
	// ACF label / group title containing `</script>` cannot break out of the inline
	// <script> block this JSON is embedded in (wp_add_inline_script). Without it,
	// `</script>` in a label terminates the tag early = stored-injection vector.
	$json = wp_json_encode(
		$envelope,
		JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
	);

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
 * Which bidirectional IMPLEMENTATION is enabled on a field: `native`, `acfe` or ''.
 *
 * A BOOLEAN CANNOT SELECT THE RIGHT SENTENCE. On a single-entry post object the two
 * implementations describe OPPOSITE behaviours — ACF native appends on the reciprocal
 * write and collapses to the first entry only at format time (accumulate-and-hide),
 * ACF Extended collapses before writing (replace-and-discard) — so the flavour is
 * carried rather than flattened. Where BOTH are enabled the native description applies,
 * because silent retention is the harder condition to diagnose.
 *
 * Each flavour is read from ITS OWN settings, and both require the target list as well
 * as the toggle, exactly as each plugin's own writer gates itself
 * (`acf_update_bidirectional_values()`; `acfe_bidirectional::is_enabled()`).
 *
 * OPTIONS-PAGE FIELDS ARE NEVER BIDIRECTIONAL. ACF resolves valid bidirectional targets
 * BY OBJECT TYPE and has no case for options, so such a field never receives a reciprocal
 * write even with the setting ticked. The envelope is already keyed by resolved-source
 * kind, so the discriminator costs nothing — and suppression here (rather than a seventh
 * note case) is what makes an options-page field take the corresponding NON-bidirectional
 * wording.
 *
 * Pure — definitions only, no value read (V5).
 *
 * @since 1.17.0
 * @param array  $field ACF field definition array.
 * @param string $kind  Resolved-source kind the field sits under (`post`/`term`/`site`).
 * @return string 'native' | 'acfe' | '' (not bidirectional, or suppressed).
 */
function bws_field_discovery_bidi_flavour( array $field, string $kind ): string {
	if ( 'site' === $kind ) {
		return '';
	}
	if ( ! empty( $field['bidirectional'] ) && ! empty( $field['bidirectional_target'] ) ) {
		return 'native';
	}
	$acfe = ( isset( $field['acfe_bidirectional'] ) && is_array( $field['acfe_bidirectional'] ) )
		? $field['acfe_bidirectional']
		: array();
	if ( ! empty( $acfe['acfe_bidirectional_enabled'] ) && ! empty( $acfe['acfe_bidirectional_related'] ) ) {
		return 'acfe';
	}
	return '';
}

/**
 * The FIELD CONFIGURATION NOTE for one field definition, or null (#96).
 *
 * What an author cannot see from the tag modal: ACF's configured entry limit is not
 * enforced on ANY write path (`validate_value()` on the relationship field has a `min`
 * branch and no `max` branch; `max` appears only as a browser data attribute, two
 * settings lines and a REST schema entry), and a single-entry post object can silently
 * hold several entries. Field group settings live in the ACF admin, which is unreachable
 * from the contexts field discovery exists to serve — Patterns, Elements, templates,
 * where there is often no bound post at all.
 *
 * DESCRIBES, NEVER GATES: nothing here changes wire, blocks a save, or moves rendered
 * output. The note is emitted HERE rather than raw settings so every user-facing string
 * stays in PHP and the editor control continues to hand-author no vocabulary.
 *
 * Pure — definitions only, no value read (V5), which is what makes it work identically
 * in a Pattern with no post in scope.
 *
 * THE SIX CASES AND THEIR DERIVATION RULES ARE OWNED BY
 * `docs/tag-reference.md` §Field configuration note — the wording is settled and lives
 * there once, so a seventh case is added by rule rather than by taste. What is recorded
 * here is only what a reader of THIS function would otherwise re-derive from the
 * branches: the two rules the cascade's shape depends on, and the fall-through.
 *
 * THE CONSEQUENCE CLAUSE RIDES SINGLE-ENTRY, NOT BIDIRECTIONALITY, which is why it is
 * attached to the post-object arm rather than to the bidirectional ones: the
 * hiding-then-resurrecting it describes follows from the format-time collapse, and bidi
 * is only the likeliest writer.
 *
 * THE FALL-THROUGH IS THE TAXONOMY/USER RULE, and it is deliberately not a case of its
 * own: every remaining valid bidirectional TARGET has no limit setting to report, so a
 * taxonomy field, a user field and a MULTIPLE-entry post object all land on the same
 * sentence by the same rule.
 *
 * SEGMENTS, NOT A STRING PLUS A TRAILING EMPHASIS FIELD. A trailing field would encode
 * "emphasis always falls last", which is true only of the cases that have any today.
 * Position lives in the structure, so a second emphasised fragment or one mid-note needs
 * no shape change. A case with no emphasis emits ONE segment, never an empty second one.
 *
 * @since 1.17.0
 * @param array  $field ACF field definition array.
 * @param string $kind  Resolved-source kind the field sits under (`post`/`term`/`site`).
 * @return array<int,array{text:string,emph:bool}>|null Ordered segments, or null.
 */
function bws_field_discovery_field_note( array $field, string $kind = 'post' ) {
	$type    = isset( $field['type'] ) ? (string) $field['type'] : '';
	$flavour = bws_field_discovery_bidi_flavour( $field, $kind );

	// Each case is ONE whole translatable sentence-pair rather than clauses concatenated
	// at runtime. The wording was arrived at by iteration and is settled; assembling it
	// from fragments would save a few repeated words and cost the translator the sentence.
	//
	// The unenforcement clause is stated POSITIVELY — naming direct ACF editing as the
	// only enforcement point correctly implies that imports, WP-CLI and every other
	// programmatic write bypass it too, rather than pinning the bypass on
	// bidirectionality alone.
	// `consequence` MARKS the segment, it does not render: the REST envelope is
	// tag-blind by construction (no tag identity reaches this route), so which tags
	// may show this clause is decided client-side, off the consuming tag's
	// takes_first_usable capability (ADR 0007) — on a collapsing tag the prediction
	// "all entries will be results" is false, the multi-value FACT above it stays
	// true, and the note must lose only the false half. Marking here keeps the drop
	// structural rather than keyed on `emph`, which is presentation.
	$consequence = array(
		'text'        => __( 'The first stored entry will be the only result while this field is single-entry; all entries will be results if it is reconfigured as multiple-entry.', 'generateblocks' ),
		'emph'        => true,
		'consequence' => true,
	);

	// Case 2 — a bidirectional field with no limit SETTING to report. Reached by a
	// relationship with `max` unset, by a taxonomy or user field (neither has the
	// setting), and by a MULTIPLE-entry post object, all by the same rule.
	$no_limit = array(
		array(
			'text' => __( 'Bidirectional field with no configured limit. Edits to its bidirectional target field(s) on other posts, terms, or users can add entries.', 'generateblocks' ),
			'emph' => false,
		),
	);

	if ( 'relationship' === $type ) {
		$max = isset( $field['max'] ) ? (int) $field['max'] : 0;

		if ( $max > 0 ) {
			$text = ( '' !== $flavour )
				? sprintf(
					/* translators: %d: the field's configured maximum number of entries. */
					__( 'Bidirectional field with a configured limit of %d. Edits to its bidirectional target field(s) on other posts, terms, or users can add more entries; the limit is enforced only when this field is edited directly, using ACF.', 'generateblocks' ),
					$max
				)
				: sprintf(
					/* translators: %d: the field's configured maximum number of entries. */
					__( 'Field with a configured limit of %d. The limit is enforced only when this field is edited directly, using ACF.', 'generateblocks' ),
					$max
				);

			return array( array( 'text' => $text, 'emph' => false ) );
		}

		// No limit to state, and no format-time collapse — so a non-bidirectional
		// relationship field has nothing noteworthy at all.
		return ( '' === $flavour ) ? null : $no_limit;
	}

	if ( 'post_object' === $type && empty( $field['multiple'] ) ) {
		if ( 'native' === $flavour ) {
			return array(
				array(
					'text' => __( 'Bidirectional field configured as single-entry. Edits to its bidirectional target field(s) on other posts, terms, or users can add more entries; the limit is enforced only when this field is edited directly, using ACF.', 'generateblocks' ),
					'emph' => false,
				),
				$consequence,
			);
		}
		if ( 'acfe' === $flavour ) {
			// NO unenforcement clause, deliberately: ACF Extended honours the
			// single-value setting at write, so nothing accumulates and nothing hides.
			return array(
				array(
					'text' => __( 'Bidirectional field configured as single-entry. Edits to its bidirectional target field(s) on other posts, terms, or users replace an existing entry.', 'generateblocks' ),
					'emph' => false,
				),
			);
		}
		return array(
			array(
				'text' => __( 'Field configured as single-entry. The limit is enforced only when this field is edited directly, using ACF.', 'generateblocks' ),
				'emph' => false,
			),
			$consequence,
		);
	}

	// Every other valid bidirectional TARGET type: a taxonomy or user field, or a
	// multiple-entry post object. Nothing else can receive a reciprocal write, so
	// nothing else has anything to say.
	if ( '' !== $flavour && in_array( $type, array( 'post_object', 'taxonomy', 'user' ), true ) ) {
		return $no_limit;
	}

	return null;
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
 * @param string $kind        Resolved-source kind the enclosing GROUP sits under
 *                            (`post`/`term`/`site`). Only the field-configuration NOTE
 *                            reads it — an options-page field is never bidirectional
 *                            (#96) — so `post` is a safe default for a caller that has
 *                            no group in hand.
 * @return array<int,array{name:string,label:string,type:string,return_format:?string,context_hint:string,parent_path:string,repeater_key:string,note:?array}>
 */
function bws_field_discovery_flatten_fields( $fields, $parent_path = '', $group_key = '', $kind = 'post' ) {
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

		// context_hint defaults to `field` (a stable meta key). The `row` hint (a key
		// that only resolves inside a repeater/flex row) is NOT decided here: it is
		// stamped by the repeater/flex recursion branches below, which override each
		// child's context_hint to `row`. So every field emitted at this level is
		// `field`; row-ness comes from the parent, not from group_key.
		$context_hint = 'field';

		$out[] = array(
			'name'          => $resolution_key,
			'label'         => $label,
			'type'          => $type,
			'return_format' => $rf,
			'context_hint'  => $context_hint,
			'parent_path'   => $parent_path,
			// Machine-readable owning-repeater/flex key. '' at this level; the
			// repeater/flex branches below stamp it on their children (the resolution
			// key of the container field) so a consumer can scope a picker to one
			// repeater's sub-fields WITHOUT parsing the display breadcrumb (parent_path).
			// Additive / structured — the shape FU-1 should absorb (FW-14's FU-1),
			// stamped here for FW-53's row scoping.
			'repeater_key'  => '',
			// The FIELD CONFIGURATION NOTE (#96) — ordered segments, or null when the
			// field has nothing noteworthy. Emitted as TEXT rather than as raw `max` /
			// `multiple` / bidirectional settings so every user-facing string stays in
			// PHP and the chain-step control continues to hand-author no vocabulary.
			'note'          => bws_field_discovery_field_note( $field, (string) $kind ),
		);

		$child_path = ( '' === $parent_path ) ? $label : $parent_path . ' › ' . $label;

		// GROUP → children resolve as composite keys, stable everywhere.
		if ( 'group' === $type && ! empty( $field['sub_fields'] ) ) {
			foreach ( bws_field_discovery_flatten_fields( $field['sub_fields'], $child_path, $resolution_key, $kind ) as $child ) {
				$out[] = $child;
			}
		}

		// REPEATER → children resolve by bare name in row context only. Stamp the
		// repeater's resolution key on each child so a consumer can scope to exactly
		// this repeater's sub-fields (table {N}-key auto-scope, #12). Only stamp the
		// DIRECT children (a nested repeater's own children keep their own key from
		// the recursion) — an already-set repeater_key is not overwritten.
		if ( 'repeater' === $type && ! empty( $field['sub_fields'] ) ) {
			foreach ( bws_field_discovery_flatten_fields( $field['sub_fields'], $child_path, '', $kind ) as $child ) {
				$child['context_hint'] = 'row';
				if ( '' === ( $child['repeater_key'] ?? '' ) ) {
					$child['repeater_key'] = $resolution_key;
				}
				$out[] = $child;
			}
		}

		// FLEXIBLE CONTENT → each layout's sub_fields, bare name in row context.
		// Path nests under THIS flex field ($child_path already = parent › this label),
		// then the layout, so two flex fields sharing a layout name (e.g. `Blocks` and
		// `Sidebar` both with a `Hero` layout) stay under distinct location paths
		// instead of collapsing to a bare `Hero` (matches the group/repeater breadcrumb).
		if ( 'flexible_content' === $type && ! empty( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
			foreach ( $field['layouts'] as $layout ) {
				if ( empty( $layout['sub_fields'] ) ) {
					continue;
				}
				$layout_label = isset( $layout['label'] ) && '' !== $layout['label'] ? (string) $layout['label'] : '';
				$layout_path  = ( '' === $layout_label ) ? $child_path : $child_path . ' › ' . $layout_label;
				foreach ( bws_field_discovery_flatten_fields( $layout['sub_fields'], $layout_path, '', $kind ) as $child ) {
					$child['context_hint'] = 'row';
					// Flex children scope to the FLEX field's key (its rows are the flex
					// layouts). Same additive stamp as the repeater branch (#12).
					if ( '' === ( $child['repeater_key'] ?? '' ) ) {
						$child['repeater_key'] = $resolution_key;
					}
					$out[] = $child;
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
			// A `register_meta` key carries no ACF field definition, so there is nothing
			// to derive a note from. Stated rather than omitted so every entry in the
			// envelope has the same shape.
			'note'          => null,
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
 * Subtypes to enumerate for registered meta of a kind: '' (global) + every
 * post type / taxonomy. Empty string first so global keys are seen before their
 * subtype-scoped duplicates in dedupe (global [] and subtype scopes never merge,
 * but ordering keeps the global group's title/position deterministic).
 *
 * @since 1.13.0
 * @param string $kind 'post' or 'term'.
 * @return array<int,string> Subtype slugs, '' included first.
 */
function bws_field_discovery_registered_meta_subtypes( $kind ) {
	$subtypes = array( '' );
	if ( 'post' === $kind && function_exists( 'get_post_types' ) ) {
		$subtypes = array_merge( $subtypes, array_values( get_post_types( array(), 'names' ) ) );
	} elseif ( 'term' === $kind && function_exists( 'get_taxonomies' ) ) {
		$subtypes = array_merge( $subtypes, array_values( get_taxonomies( array(), 'names' ) ) );
	}
	return array_values( array_unique( $subtypes ) );
}

/**
 * Registered meta map for one (object type, subtype). Thin wrapper over
 * get_registered_meta_keys so collect() and the test harness share one call site.
 *
 * @since 1.13.0
 * @param string $object_type 'post' or 'term'.
 * @param string $subtype     Post type / taxonomy slug, or '' for global.
 * @return array `key => args`, or empty array.
 */
function bws_field_discovery_get_registered_meta( $object_type, $subtype ) {
	if ( ! function_exists( 'get_registered_meta_keys' ) ) {
		return array();
	}
	$map = get_registered_meta_keys( $object_type, $subtype );
	return is_array( $map ) ? $map : array();
}

/**
 * Collect field definitions into a kind-keyed envelope.
 *
 * Envelope shape (V7): `array( 'post' => [], 'term' => [], 'site' => [] )` where
 * each kind holds group entries `{ group_title, kind, scope, fields:[...] }`.
 *
 * THIS FUNCTION IS THE SHAPE'S ONLY WRITER, AND TWO JS READERS WALK IT
 * INDEPENDENTLY — `envelopeToRecords()` in `assets/js/field-combo-control.js`
 * (which MERGES entries sharing a key) and `fieldNote()` in
 * `assets/js/slot-fold-control.js` (which exists to notice that such entries
 * DISAGREE). They are deliberately not one walk: a merged view is the wrong
 * input for the second question, and the picker bails early on older component
 * stacks in a way the note has no reason to inherit. So a change to the four
 * levels above starts here and lands in both.
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
				// The kind is derived BEFORE the flatten, because the field-configuration
				// note reads it (an options-page field is never bidirectional, #96).
				// group_entry() derives it again from the same location array — one
				// cheap array walk, against threading a second parameter through a
				// function whose whole job is to answer that question.
				$acf_fields = acf_get_fields( $group['key'] );
				$kind_scope = bws_field_discovery_derive_kind_scope( $group['location'] ?? array() );
				$flattened  = bws_field_discovery_flatten_fields(
					is_array( $acf_fields ) ? $acf_fields : array(),
					'',
					'',
					$kind_scope['kind']
				);
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

	// Core registered meta (non-ACF). Complementary source. get_registered_meta_keys
	// with an EMPTY subtype returns ONLY globally-registered keys; subtype-registered
	// keys (register_post_meta( 'event', ... )) are invisible there, so enumerate the
	// global set AND each subtype, scoping each group to its subtype. A key resolves
	// the same via get_(post|term)_meta regardless of subtype, so scope is a reach
	// hint (dedupe uses it), not a read path.
	if ( function_exists( 'get_registered_meta_keys' ) ) {
		foreach ( bws_field_discovery_registered_meta_subtypes( 'post' ) as $subtype ) {
			$reg_group = bws_field_discovery_registered_meta_group(
				bws_field_discovery_get_registered_meta( 'post', $subtype ),
				'post',
				$subtype,
				__( 'Registered post meta', 'generateblocks' )
			);
			if ( $reg_group ) {
				$envelope['post'][] = $reg_group;
			}
		}

		foreach ( bws_field_discovery_registered_meta_subtypes( 'term' ) as $subtype ) {
			$reg_group = bws_field_discovery_registered_meta_group(
				bws_field_discovery_get_registered_meta( 'term', $subtype ),
				'term',
				$subtype,
				__( 'Registered term meta', 'generateblocks' )
			);
			if ( $reg_group ) {
				$envelope['term'][] = $reg_group;
			}
		}
	}

	// Dedupe within (kind, scope) — ACF metadata wins (T3).
	$envelope = bws_field_discovery_dedupe( $envelope );

	return $envelope;
}

/**
 * Dedupe fields within each (kind, scope) bucket (V7).
 *
 * Dedupe key = field resolution NAME, within one KIND, where two entries have the
 * SAME scope (equal slug sets, or both empty = both global). A `post` field and a
 * `term` field of the same name are NEVER merged (different kind = different
 * storage/read path = different field).
 *
 * SCOPE-EQUALITY, not overlap: two same-name fields merge only when their reach is
 * IDENTICAL (truly one field). When reach DIFFERS — e.g. a global registered
 * `subtitle` (scope []) and an `event`-scoped ACF `subtitle` (scope ['event']) —
 * they are kept as SEPARATE records: on an `event` post both apply (one is a dup
 * the client's V12 ambiguity handling covers), but on a `page` ONLY the global
 * registered key applies, so dropping it would hide a resolvable key. The flat
 * per-kind envelope can't partition by post type, so keep-both is the only lossless
 * answer. (Overlap-based merge dropped the global key envelope-wide — the B4-class bug.)
 *
 * Precedence when two SAME-SCOPE entries collide on name:
 *   - ACF beats registered-meta (`source:'acf'` > `source:'registered'`). Same key,
 *     same reach → it IS one field; ACF's label + type describe it better, so the
 *     ACF entry is kept and the registered one dropped.
 *   - ACF-vs-ACF (rare misconfig) → first-seen wins (deterministic ACF group order).
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
						// Merge only at IDENTICAL scope (same reach = same field). Different
						// reach → keep both; the global key must survive where the scoped
						// one does not apply.
						if ( ! bws_field_discovery_scopes_equal( $group_scope, $prior['scope'] ) ) {
							continue;
						}
						if ( 'registered' === $group_source && 'acf' === $prior['source'] ) {
							// Current registered-meta duplicate of a prior same-scope ACF field → drop it.
							$dropped = true;
							break;
						}
						if ( 'acf' === $group_source && 'registered' === $prior['source'] ) {
							// Current ACF displaces a prior same-scope registered-meta entry.
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
 * Two candidate scopes are EQUAL when they name the same reach: both empty (both
 * global / all subtypes) or the same set of slugs.
 *
 * Equality, not overlap, is the merge gate: same-name fields collapse only when
 * they apply to exactly the same set of post types / taxonomies (truly one field).
 * A global scope ([]) and a scoped one (['event']) are NOT equal, so they are kept
 * distinct — the global key must survive on post types the scoped one does not
 * reach. See bws_field_discovery_dedupe for the rationale.
 *
 * @since 1.13.0
 * @param array $a Scope slugs.
 * @param array $b Scope slugs.
 * @return bool True when the scopes name the same reach.
 */
function bws_field_discovery_scopes_equal( $a, $b ) {
	$a = is_array( $a ) ? $a : array();
	$b = is_array( $b ) ? $b : array();
	if ( count( $a ) !== count( $b ) ) {
		return false;
	}
	// Order-insensitive set equality (both are slug lists, no duplicates expected).
	return array() === array_diff( $a, $b ) && array() === array_diff( $b, $a );
}

/**
 * Remove a field by name from all SAME-SCOPE groups of one kind.
 *
 * Used by dedupe when a later ACF field must displace an earlier registered-meta
 * field of equal scope already kept. Operates in place on the envelope. Scope
 * equality mirrors the dedupe merge gate: only the registered entry that shares
 * the ACF winner's exact reach is removed; a differently-scoped same-name entry
 * is a distinct field and is left alone.
 *
 * @since 1.13.0
 * @param array  $envelope    Kind-keyed envelope (by reference).
 * @param string $kind        Kind bucket to edit.
 * @param string $name        Field resolution name to remove.
 * @param array  $prior_scope Scope of the winner (equality gate).
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
		if ( ! bws_field_discovery_scopes_equal( $group_scope, $prior_scope ) ) {
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
 * Offered ⟺ resolvable: strips any field the resolver would refuse, via the
 * shared `bws_field_key_disallowed()` predicate (field-helpers.php) that
 * `bws_read_field` / `bws_read_term_field` also call — one authority, no drift.
 * Does NOT strip general `_`-protected meta; the resolver allows those on the
 * frontend, so Pie Calendar `_piecal_*` etc. stay offerable.
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
	// Same gate the resolver enforces (bws_field_key_disallowed, field-helpers.php)
	// so offered ⟺ resolvable (V6). Cheap early-out when GB (hence the gate) is
	// absent: nothing is blocked, so the envelope passes through unchanged.
	if ( ! function_exists( 'bws_field_key_disallowed' )
		|| ! class_exists( 'GenerateBlocks_Dynamic_Tag_Security' )
	) {
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
					static function ( $field ) {
						$key = isset( $field['name'] ) ? (string) $field['name'] : '';
						return '' === $key || ! bws_field_key_disallowed( $key );
					}
				)
			);
			$envelope[ $kind ][ $g ] = $group;
		}
	}

	return $envelope;
}
