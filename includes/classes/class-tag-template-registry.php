<?php
/**
 * Tag Template Registry — base tag, modifier, and try_ tag generation.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.2.0
 */

namespace BWS\DynamicTags;

use BWS\DynamicTags\Admin\SettingsPage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TagTemplateRegistry {

	/**
	 * @var array[] Modifier template descriptors used by register_modifier() (term_ constructor)
	 *              and generate_base_try_tags() (try_ constructor).
	 *
	 * Each entry shape:
	 *   key              string    Template key (e.g. 'text', 'image').
	 *   title            string    Display title fragment (e.g. 'Text Fields').
	 *   options          array     Template-specific options excluding via/traversal sub-options.
	 *   term_fn          callable  fn($term_id, $opts, $inst): string — term-entity handler.
	 *   post_fn          callable  fn($post_id, $opts, $inst): string — post-entity handler (term_ via:'ref').
	 *   try_core_fn      callable  fn($post_id, $opts, $inst): string — try_ post-slot handler.
	 *   try_term_fn      callable|null  fn($term_id, $opts, $inst): string — try_ via:tax slot handler.
	 *   supports_try     bool      Whether this template generates a try_ tag.
	 *   leading_options       array    Group 1 options (global formatting: as, size, format, etc.) prepended before slots in try_ tags.
	 *   try_per_slot_key      bool     Each try_ slot gets its own N-key.
	 *   try_per_slot_use      bool     Each try_ slot gets its own use/N-use selector.
	 *   try_use_no_key_values array    use values where key is not required (e.g. ['featured'] for image).
	 *   is_image              bool     Image template — custom as/size/fallback controls; register_modifier() builds own option set.
	 */
	private static array $modifier_templates = [];

	// ===
	// Registration
	// ===

	/**
	 * Register a base template descriptor for use by modifier + try_ constructors.
	 *
	 * Called once per base template (from bws_register_base_tags()) after the GB tag is registered.
	 * Stores metadata needed by register_modifier() and generate_base_try_tags().
	 *
	 * @since 1.6.0
	 */
	public static function register_modifier_template( array $config ): void {
		self::$modifier_templates[] = $config;
	}

	/**
	 * Get all registered modifier templates (read-only).
	 *
	 * @since 1.6.0
	 * @return array[]
	 */
	public static function get_modifier_templates(): array {
		return self::$modifier_templates;
	}

	/**
	 * Register a context modifier group (e.g. the term_ modifier).
	 *
	 * Generates one GB tag per modifier template: prefix + '_' + template_key.
	 * The modifier entity is resolved by the base_source_key source (via unset) or by the
	 * traversal_source_key source (via:'ref'). Modifier tags include 'source' support unless
	 * excluded_supports contains 'source'.
	 *
	 * @since 1.6.0
	 *
	 * @param array $config {
	 *     @type string $prefix               Tag prefix, e.g. 'term' → produces 'term_text'.
	 *     @type string $gb_type              GB type for all modifier tags, e.g. 'term'.
	 *     @type string $modifier_label       Parenthetical appended to the tag title, e.g. 'term-based'.
	 *     @type string $traversal_source_key Source key for the 'ref' traversal (e.g. 'term_related_post').
	 *     @type string $base_source_key      Source key for direct entity resolution (e.g. 'term').
	 *     @type array  $excluded_supports    Supports to exclude; omit to keep 'source' (GB entity picker).
	 * }
	 */
	public static function register_modifier( array $config ): void {
		if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
			return;
		}

		$prefix            = $config['prefix']               ?? '';
		$gb_type           = $config['gb_type']              ?? 'post';
		$modifier_label    = $config['modifier_label']        ?? '';
		$traversal_src_key = $config['traversal_source_key'] ?? '';
		$base_src_key      = $config['base_source_key']      ?? '';
		$excl              = $config['excluded_supports']     ?? [];

		// Include 'source' support (GB entity picker) unless explicitly excluded.
		$base_supports = in_array( 'source', $excl, true ) ? [] : [ 'source' ];

		// Snapshot existing tags for dup-check.
		$existing = array_keys( \GenerateBlocks_Register_Dynamic_Tag::get_tags() ?? [] );

		// Reuse canonical source + traversal definitions from base-tags.php so labels stay
		// unified across base and modifier tags. Option key 'src' (not 'source') — GB's
		// DynamicTagSelect destructures 'source' before spreading into extraTagParams.
		$source_opt     = function_exists( 'bws_base_source_option' )
			? bws_base_source_option()
			: array();
		$traversal_opts = function_exists( 'bws_base_traversal_options' )
			? bws_base_traversal_options()
			: array();

		// Detect term-context base source. Term entities are themselves terms — `srcTermIn`
		// (term-hop on the resolved post) only makes sense after a post traversal (src=ref),
		// not when the entity already IS the term (src=current).
		$base_src_obj         = $base_src_key ? SourceRegistry::get_source( $base_src_key ) : null;
		$base_is_term_context = $base_src_obj && 'term' === $base_src_obj->get_context_type();

		// For term-context base sources, gate srcTermIn visibility to src=ref only.
		// Default (post or unknown context): srcTermIn always visible.
		$tag_traversal_opts = $traversal_opts;
		if ( $base_is_term_context && isset( $tag_traversal_opts['srcTermIn'] ) ) {
			$tag_traversal_opts['srcTermIn']['show_if'] = array( 'src' => 'ref' );
		}

		foreach ( self::$modifier_templates as $tpl ) {
			$tag_name = $prefix . '_' . $tpl['key'];

			if ( in_array( $tag_name, $existing, true ) ) {
				continue;
			}
			$existing[] = $tag_name;

			$term_fn  = $tpl['term_fn'];
			$post_fn  = $tpl['post_fn'];
			$is_image = ! empty( $tpl['is_image'] );

			// Inject source + traversal after the leading `as` option so source/traversal sit
			// between formatting controls and field-selection controls. For non-image templates
			// (no `as`), source/traversal lead the option list.
			$tpl_options = $tpl['options'] ?? [];
			if ( $is_image && isset( $tpl_options['as'] ) ) {
				$as_opt = [ 'as' => $tpl_options['as'] ];
				unset( $tpl_options['as'] );
				$options = array_merge( $as_opt, $source_opt, $tag_traversal_opts, $tpl_options );
			} else {
				$options = array_merge( $source_opt, $tag_traversal_opts, $tpl_options );
			}

			// Per-tag supports (do not mutate the shared $base_supports across templates).
			// Image template adds native 'image-size': GB renders the ComboboxControl, handles
			// 'size:' parsing/serialization, and strips the 'full' default automatically.
			$tag_supports = $base_supports;
			if ( $is_image && ! in_array( 'image-size', $tag_supports, true ) ) {
				$tag_supports[] = 'image-size';
			}

			$callback = self::make_modifier_callback( $base_src_key, $traversal_src_key, $term_fn, $post_fn, $tag_name, $is_image );

			// Title: plain label when in its own gb_type group (modifier tags appear under their
			// own group in GB's picker, identified by gb_type). No cross-source parenthetical needed
			// because the type already distinguishes the group.
			$title = $modifier_label
				? ( $tpl['title'] ?? $tag_name ) . ' (' . $modifier_label . ')'
				: ( $tpl['title'] ?? $tag_name );

			self::register_gb_tag( $title, $tag_name, $gb_type, $tag_supports, $options, $callback );
		}
	}

	/**
	 * Build a modifier tag callback that dispatches to term_fn (via unset) or post_fn (via:'ref').
	 *
	 * @since 1.6.0
	 */
	private static function make_modifier_callback(
		string $base_src_key,
		string $traversal_src_key,
		callable $term_fn,
		callable $post_fn,
		string $tag_name = '',
		bool $is_image = false
	): callable {
		return static function ( $opts, $block, $inst ) use ( $base_src_key, $traversal_src_key, $term_fn, $post_fn, $tag_name, $is_image ) {
			$is_preview = $tag_name && ! empty( $inst->context['bwsEditorPreview'] );

			$source = $opts['src'] ?? $opts['source'] ?? 'current';
			if ( '' === $source ) {
				$source = 'current';
			}

			// Image template: post-context paths dispatch by `use` (featured vs custom field).
			// `post_fn` (= bws_custom_image_core) only handles custom-field path; featured needs bws_featured_image_core.
			$image_post_dispatch = static function ( $entity_id, $opts, $inst ) use ( $post_fn ) {
				$use = $opts['use'] ?? '';
				if ( 'featured' === $use && function_exists( 'bws_featured_image_core' ) ) {
					return bws_featured_image_core( $entity_id, $opts, $inst );
				}
				return $post_fn( $entity_id, $opts, $inst );
			};

			$srcterm_tax = sanitize_key( $opts['srcTermIn'] ?? '' );

			// srcTermIn dispatch: resolve target post (current or via ref), then call term_fn
			// against each taxonomy term on that post; first non-empty wins. Mirrors
			// bws_base_image_callback's term-hop loop. For term-context base sources, the
			// option is hidden when src=current (UI gating), so this only runs when src=ref.
			$srcterm_dispatch = static function ( $post_id, $opts, $inst, $tax ) use ( $term_fn ) {
				if ( ! $post_id || '' === $tax ) {
					return '';
				}
				if ( ! function_exists( 'bws_get_srcterm_terms' ) ) {
					return '';
				}
				$terms = bws_get_srcterm_terms( (int) $post_id, $tax );
				foreach ( $terms as $term ) {
					$result = $term_fn( $term->term_id, $opts, $inst );
					if ( '' !== $result && false !== $result ) {
						return $result;
					}
				}
				return '';
			};

			if ( 'ref' === $source ) {
				// Traversal from modifier entity → related post.
				// register_modifier() hardcodes 'ref' option; resolve_id() expects 'rel' internally.
				$src = SourceRegistry::get_source( $traversal_src_key );
				if ( ! $src ) {
					$value = '';
				} else {
					$mapped        = $opts;
					$mapped['rel'] = $opts['ref'] ?? '';
					$entity_id     = $src->resolve_id( $mapped, $inst );

					if ( '' !== $srcterm_tax ) {
						$value = $srcterm_dispatch( $entity_id, $opts, $inst, $srcterm_tax );
					} elseif ( $is_image ) {
						$value = $image_post_dispatch( $entity_id, $opts, $inst );
					} else {
						$value = $post_fn( $entity_id, $opts, $inst );
					}
				}
			} else {
				// Source unset — resolve entity directly via base source.
				// Dispatch to term_fn or post_fn based on the base source's context type.
				// term_ modifier uses TaxonomyTerm (term context); views_ modifier uses PortalSource (post context).
				$src = SourceRegistry::get_source( $base_src_key );
				if ( ! $src ) {
					$value = '';
				} else {
					$entity_id = $src->resolve_id( $opts, $inst );
					$context   = $src->get_context_type();

					// srcTermIn at src=current is only meaningful for post-context base sources.
					// Term-context bases hide the control via show_if=src:ref (UI gating).
					if ( '' !== $srcterm_tax && 'term' !== $context ) {
						$value = $srcterm_dispatch( $entity_id, $opts, $inst, $srcterm_tax );
					} elseif ( 'term' === $context ) {
						$value = $term_fn( $entity_id, $opts, $inst );
					} elseif ( $is_image ) {
						$value = $image_post_dispatch( $entity_id, $opts, $inst );
					} else {
						$value = $post_fn( $entity_id, $opts, $inst );
					}
				}
			}

			if ( '' !== $value ) {
				return $value;
			}

			return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $opts, $tag_name ) : '';
		};
	}

	/**
	 * Generate try_ fallback-chain tags from modifier templates (base-tag system).
	 *
	 * One try_ tag per eligible modifier template (supports_try = true).
	 * Each tag accepts up to five source slots; each slot specifies a source
	 * traversal and returns the first non-empty result across all slots.
	 * Tags are registered with GB type 'first-available'.
	 *
	 * Source options per slot:
	 *   ''    Current post context (no traversal).
	 *   'ref' Post → Related Post (requires N-ref relationship field key).
	 *
	 * srcTerm modifier per slot (N-srcTerm checkbox + N-tax):
	 *   When set, the resolved post's first matching term is used as the entity.
	 *   Uses try_term_fn for dispatch; no srcTerm carry-forward between slots.
	 *
	 * Slot option naming (slot 1 un-prefixed; slots 2–5 use N- prefix):
	 *   src, 2-src … 5-src   — source selector.
	 *   ref, 2-ref … 5-ref   — relationship field key (shown when src = 'ref').
	 *   srcTerm, 2-srcTerm … — term hop modifier (no carry-forward).
	 *   tax, 2-tax …         — taxonomy slug (shown when srcTerm set).
	 *   use, 2-use …         — source type (try_per_slot_use templates only).
	 *   key, 2-key …         — field key (try_per_slot_key / try_per_slot_use templates).
	 *
	 * Option order follows the three-group structure from tag-matrix.md:
	 *   Group 1 — leading_options (global formatting: as, size, format, etc.) before slots.
	 *   Group 2 — per-slot options (src/N-src, ref/N-ref, srcTerm/N-srcTerm, tax/N-tax, use/N-use, key/N-key) × 5 slots.
	 *   Group 3 — trailing options from tpl['options'] minus leading and per-slot keys (field keys, fallback).
	 *
	 * Sub-options carry forward from slot to slot when left blank (inherit semantics).
	 * srcTerm does NOT carry forward — each slot independently chooses entity type.
	 * For try_per_slot_key templates, N-key carries the field key per slot.
	 * For try_per_slot_use templates, N-use carries the source-type selector per slot.
	 *
	 * @since 1.6.0
	 */
	public static function generate_base_try_tags(): void {
		if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
			return;
		}

		// Snapshot existing tags for dup-check.
		$existing = array_keys( \GenerateBlocks_Register_Dynamic_Tag::get_tags() ?? [] );

		// Base source dropdown values (slot 1 — first option strip applies).
		// Slot ≥2 prepends 'same' entry; strip then sets that to '' (= inherit).
		$base_source_options = [
			[ 'value' => 'current', 'label' => __( 'Current', 'generateblocks' ) ],
			[ 'value' => 'ref',     'label' => __( 'In Reference/Relational Field', 'generateblocks' ) ],
		];

		// Options that take a "Source N: " prefix instead of " N" suffix in slot labels.
		$prefix_label_keys = [ 'srcTermIn' ];

		foreach ( self::$modifier_templates as $tpl ) {
			if ( empty( $tpl['supports_try'] ) ) {
				continue;
			}

			$tag_name = 'try_' . $tpl['key'];

			if ( in_array( $tag_name, $existing, true ) ) {
				continue;
			}
			$existing[] = $tag_name;

			if ( ! SettingsPage::is_modifier_enabled( 'try' ) ) {
				continue;
			}

			$try_core_fn     = $tpl['try_core_fn'] ?? null;
			$try_term_fn     = $tpl['try_term_fn'] ?? null;
			$per_slot_key    = ! empty( $tpl['try_per_slot_key'] );
			$per_slot_use    = ! empty( $tpl['try_per_slot_use'] );
			$no_key_uses     = $tpl['try_use_no_key_values'] ?? [];
			$tpl_options     = $tpl['options'] ?? [];
			$leading_options = $tpl['leading_options'] ?? [];

			if ( ! $try_core_fn ) {
				continue;
			}

			// Per-template use option label (drives slot N "X Field N" labels).
			$use_label = $tpl_options['use']['label'] ?? __( 'Field', 'generateblocks' );

			// Group 1 — global formatting (as, size, format, etc.) before all slots.
			$options = $leading_options;

			for ( $n = 1; $n <= 5; $n++ ) {
				$prev    = $n - 1;
				$src_key = ( 1 === $n ) ? 'src'       : "{$n}-src";
				$use_key = ( 1 === $n ) ? 'use'       : "{$n}-use";
				$ref_key = ( 1 === $n ) ? 'ref'       : "{$n}-ref";
				$stm_key = ( 1 === $n ) ? 'srcTermIn' : "{$n}-srcTermIn";
				$key_key = ( 1 === $n ) ? 'key'       : "{$n}-key";

				// Slot visibility trigger:
				//   Slot 1 — always visible.
				//   Slot 2 — always visible (matrix shape: at least two slots configurable up front).
				//   Slot 3+ — visible when any prior slot's option carries a non-default value.
				if ( $n <= 2 ) {
					$slot_trigger = [];
				} else {
					$prev_src = ( 1 === $prev ) ? 'src'       : "{$prev}-src";
					$prev_ref = ( 1 === $prev ) ? 'ref'       : "{$prev}-ref";
					$prev_stm = ( 1 === $prev ) ? 'srcTermIn' : "{$prev}-srcTermIn";
					$prev_any = [
						$prev_src => 'not_empty',
						$prev_ref => 'not_empty',
						$prev_stm => 'not_empty',
					];
					if ( $per_slot_key || $per_slot_use ) {
						$prev_key              = ( 1 === $prev ) ? 'key' : "{$prev}-key";
						$prev_any[ $prev_key ] = 'not_empty';
					}
					if ( $per_slot_use ) {
						$prev_use              = ( 1 === $prev ) ? 'use' : "{$prev}-use";
						$prev_any[ $prev_use ] = 'not_empty';
					}
					$slot_trigger = [ 'show_if_any' => $prev_any ];
				}

				// Source dropdown values: slot 1 = base; slot ≥2 = prepend 'same' inherit row.
				$slot_src_options = ( 1 === $n )
					? $base_source_options
					: array_merge(
						[ [ 'value' => 'same', 'label' => __( 'Same as Previous Source', 'generateblocks' ) ] ],
						$base_source_options
					);

				$options[ $src_key ] = array_merge(
					[
						'type'           => 'select',
						/* translators: %d: slot number */
						'label'          => sprintf( __( 'Source %d', 'generateblocks' ), $n ),
						'options'        => $slot_src_options,
						'_strip_default' => true,
					],
					$slot_trigger
				);

				// ref — relationship field key (shown when src = 'ref').
				$options[ $ref_key ] = [
					'type'        => 'text',
					/* translators: %d: slot number */
					'label'       => sprintf( __( 'Relationship Field %d', 'generateblocks' ), $n ),
					'help'        => __( 'ACF relationship field key.', 'generateblocks' ),
					'placeholder' => 'related_post',
					'show_if'     => [ $src_key => 'ref' ],
				];

				// srcTermIn — combined term-hop control (no carry-forward).
				$srcterm_in_label = sprintf(
					/* translators: %d: slot number */
					__( 'Source %d: Get from taxonomy term?', 'generateblocks' ),
					$n
				);
				$srcterm_pick_label = sprintf(
					/* translators: %d: slot number */
					__( 'Source %d: Taxonomy', 'generateblocks' ),
					$n
				);
				$options[ $stm_key ] = array_merge(
					[
						'type'      => 'bws-term-hop',
						'label'     => $srcterm_in_label,
						'help'      => __( 'Field is in a taxonomy term on this source.', 'generateblocks' ),
						'pickLabel' => $srcterm_pick_label,
						'pickHelp'  => __( 'Pick the taxonomy.', 'generateblocks' ),
					],
					$slot_trigger
				);

				// N-use — per-slot field-type selector (try_per_slot_use templates only).
				if ( $per_slot_use && ! empty( $tpl_options['use']['options'] ) ) {
					$slot_use_options = ( 1 === $n )
						? $tpl_options['use']['options']
						: array_merge(
							[ [ 'value' => 'same', 'label' => __( 'Same as Previous Field', 'generateblocks' ) ] ],
							$tpl_options['use']['options']
						);

					$options[ $use_key ] = array_merge(
						[
							'type'           => 'select',
							/* translators: 1: use option label (e.g. "Text Field"), 2: slot number */
							'label'          => sprintf( '%1$s %2$d', $use_label, $n ),
							'options'        => $slot_use_options,
							'_strip_default' => true,
						],
						$slot_trigger
					);
				}

				// key — per-slot field key.
				// try_per_slot_key (no use): always shown within slot.
				// try_per_slot_use: shown only when current slot's effective use mode needs a key.
				//   Effective mode for slot ≥2 includes "same" (inherit) — visibility for inherit
				//   case is approximated by show_if_any across this slot's use values that need keys.
				if ( $per_slot_key && ! $per_slot_use ) {
					$options[ $key_key ] = array_merge(
						[
							'type'        => 'text',
							/* translators: %d: slot number */
							'label'       => sprintf( __( 'Field Key %d', 'generateblocks' ), $n ),
							'help'        => __( 'ACF or meta field key for this slot.', 'generateblocks' ),
							'placeholder' => 'field_name',
						],
						$slot_trigger
					);
				} elseif ( $per_slot_use ) {
					// Slot 1: '' wire = template's first 'use' value (key-mode for text/image, content for content).
					//   Show key when current 'use' is NOT in no-key list (use === '' implies key-mode for text/image).
					//   Builds 'show_if' = { use_key => 'not_in:<no-key values>' }.
					// Slot ≥2: '' wire = 'same' (inherit prior carry-forward — inherits BOTH use AND key).
					//   Show key only when user explicitly picks a key-needing 'use' value (override mode).
					//   Same-as-previous keeps key field hidden because the inherited key is reused.
					$use_values  = array_column( $tpl_options['use']['options'], 'value' );
					$key_values  = array_values( array_diff( $use_values, $no_key_uses ) );
					$key_show_if = ( 1 === $n )
						? ( ! empty( $no_key_uses ) ? [ $use_key => 'not_in:' . implode( ',', $no_key_uses ) ] : [] )
						: ( ! empty( $key_values ) ? [ $use_key => 'in:' . implode( ',', $key_values ) ] : [] );

					$options[ $key_key ] = array_merge(
						[
							'type'        => 'text',
							/* translators: %d: slot number */
							'label'       => sprintf( __( 'Field Key %d', 'generateblocks' ), $n ),
							'help'        => __( 'ACF or meta field key for this slot.', 'generateblocks' ),
							'placeholder' => 'field_name',
						],
						$key_show_if ? [ 'show_if' => $key_show_if ] : [],
						$slot_trigger
					);
				}
			}

			// Append template-level trailing options (fallback, etc.).
			// Strip options already emitted as leading (Group 1) or replaced by per-slot equivalents.
			$trailing_opts = $tpl_options;
			foreach ( array_keys( $leading_options ) as $leading_key ) {
				unset( $trailing_opts[ $leading_key ] );
			}
			if ( $per_slot_key ) {
				unset( $trailing_opts['key'] );
			}
			if ( $per_slot_use ) {
				unset( $trailing_opts['use'], $trailing_opts['key'] );
			}
			$options = array_merge( $options, $trailing_opts );

			// --- Build callback ---
			$cf  = $try_core_fn;
			$tcf = $try_term_fn;
			$psk = $per_slot_key;
			$psu = $per_slot_use;
			$nku = $no_key_uses;
			// Slot 1 default 'use' token = first option value in template's use definition.
			$default_use = $tpl_options['use']['options'][0]['value'] ?? '';

			$tpl_key = $tpl['key'];

			$callback = static function ( $opts, $b, $inst ) use ( $cf, $tcf, $psk, $psu, $nku, $default_use, $tpl_key ) {
				$is_preview = ! empty( $inst->context['bwsEditorPreview'] );

				$fallback  = sanitize_text_field( $opts['fallback'] ?? $opts['fallback_text'] ?? '' );
				$eval_opts = array_diff_key( $opts, [ 'fallback' => null, 'fallback_text' => null ] );

				// Carry-forward state across slots. Canonical tokens stored:
				// $last_src: 'current' | 'ref' (never empty after slot 1 normalize).
				// $last_use: per-template default ('key' | 'content') | other tokens.
				$last_src = 'current';
				$last_ref = '';
				$last_key = '';
				$last_use = ''; // populated from slot 1 default below.

				foreach ( range( 1, 5 ) as $n ) {
					$src_k = ( 1 === $n ) ? 'src'       : "{$n}-src";
					$ref_k = ( 1 === $n ) ? 'ref'       : "{$n}-ref";
					$stm_k = ( 1 === $n ) ? 'srcTermIn' : "{$n}-srcTermIn";
					$key_k = ( 1 === $n ) ? 'key'       : "{$n}-key";
					$use_k = ( 1 === $n ) ? 'use'       : "{$n}-use";

					$src_raw = $opts[ $src_k ] ?? '';
					$ref_raw = $opts[ $ref_k ] ?? '';
					$stm_raw = sanitize_key( $opts[ $stm_k ] ?? '' );
					$key_raw = $opts[ $key_k ] ?? '';
					$use_raw = $opts[ $use_k ] ?? '';

					// 'same' is the inherit sentinel for slot ≥2 dropdowns; strip-at-registration
					// normally renders this as '', but normalize defensively for hand-written tags.
					if ( $n > 1 ) {
						if ( 'same' === $src_raw ) { $src_raw = ''; }
						if ( 'same' === $use_raw ) { $use_raw = ''; }
					}

					// Slot 1: '' = stripped first-option default (= 'current' for src, per-template for use).
					// Slot ≥2: '' = 'same' (inherit prior carry-forward).
					if ( 1 === $n ) {
						$last_src = ( '' === $src_raw ) ? 'current' : $src_raw;
						$last_ref = $ref_raw;
						$last_key = $key_raw;
						if ( $psu ) {
							$last_use = ( '' === $use_raw ) ? $default_use : $use_raw;
						}
					} else {
						// Slot ≥2 — when use=same (raw empty under psu), the slot fully inherits
						// both `use` and `key` from prior carry-forward. UI hides the key field
						// in this case; discard any stale `key_raw` left over from a prior
						// explicit-use selection so it doesn't bleed through as an override.
						if ( $psu && '' === $use_raw ) {
							$key_raw = '';
						}

						// Skip slot if entirely empty (no override anywhere).
						$has_new = '' !== $src_raw
							|| '' !== $ref_raw
							|| '' !== $stm_raw
							|| ( ( $psk || $psu ) && '' !== $key_raw )
							|| ( $psu && '' !== $use_raw );
						if ( ! $has_new ) {
							continue;
						}

						// Carry-forward semantics: '' = same/inherit, anything else = override.
						if ( '' !== $src_raw ) { $last_src = $src_raw; }
						if ( '' !== $ref_raw ) { $last_ref = $ref_raw; }
						if ( '' !== $key_raw ) { $last_key = $key_raw; }
						if ( $psu && '' !== $use_raw ) { $last_use = $use_raw; }
					}

					// Build slot-specific options (merged into core fn call).
					$slot_opts          = $eval_opts;
					$slot_opts['src']   = $last_src;
					$slot_opts['ref']   = $last_ref;

					if ( $psk || $psu ) {
						$in_no_key_mode = $psu && in_array( $last_use, $nku, true );
						if ( ! $in_no_key_mode && '' === $last_key ) {
							continue; // No field key and not in no-key mode — skip slot.
						}
						if ( '' !== $last_key ) {
							$slot_opts['key'] = $last_key;
						}
					}

					if ( $psu ) {
						$slot_opts['use'] = $last_use;
					}

					// srcTermIn dispatch: resolve post → get terms → call try_term_fn.
					// srcTermIn is read from this slot only (no carry-forward).
					if ( '' !== $stm_raw && $tcf ) {
						$slot_opts['srcTermIn'] = $stm_raw;
						$post_id = function_exists( 'bws_resolve_post_by_source' )
							? bws_resolve_post_by_source( $slot_opts, $inst )
							: get_the_ID();
						if ( $post_id && function_exists( 'bws_get_srcterm_terms' ) ) {
							$terms = bws_get_srcterm_terms( (int) $post_id, $stm_raw );
							foreach ( $terms as $term ) {
								$result = $tcf( $term->term_id, $slot_opts, $inst );
								if ( '' !== $result && false !== $result ) {
									return $result;
								}
							}
						}
						continue; // All terms empty — try next slot.
					}

					// Post-based paths: 'current' | 'ref'.
					$post_id = function_exists( 'bws_resolve_post_by_source' )
						? bws_resolve_post_by_source( $slot_opts, $inst )
						: ( 'current' === $last_src ? get_the_ID() : false );

					// Mode 2b: bws_resolve_post_by_source returns false for src:'current' on a flat
					// repeater row, but core fn can still resolve via $loop_item[$key].
					$in_loop_row = function_exists( 'bws_get_loop_row_context' )
						&& bws_get_loop_row_context( $inst )['in_loop'];
					$allow_loop_fallthrough = ! $post_id
						&& $in_loop_row
						&& 'current' === $last_src
						&& '' !== $last_key;

					if ( ! $post_id && ! $allow_loop_fallthrough ) {
						continue;
					}

					$result = $cf( $post_id, $slot_opts, $inst );
					if ( '' !== $result && false !== $result ) {
						return $result;
					}
				}

				// All slots exhausted — apply fallback_text, then label if in preview.
				if ( '' !== $fallback ) {
					return \GenerateBlocks_Dynamic_Tag_Callbacks::output( $fallback, $opts, $inst );
				}

				return $is_preview && function_exists( 'bws_build_try_preview_label' )
					? bws_build_try_preview_label( $opts, $tpl_key )
					: '';
			};

			/* translators: %s: tag title e.g. "Text Fields" */
			$title = sprintf( __( 'Try %s', 'generateblocks' ), $tpl['title'] ?? $tag_name );

			// Image template uses native 'image-size' support; other templates have no native supports.
			$supports = ! empty( $tpl['is_image'] ) ? [ 'image-size' ] : [];

			self::register_gb_tag( $title, $tag_name, 'first-available', $supports, $options, $callback );
		}
	}

	/**
	 * Register a single dynamic tag with GenerateBlocks.
	 *
	 * @param string   $title    Full tag title shown in the GB editor.
	 * @param string   $tag_name Tag name (e.g., 'post_custom_image').
	 * @param string   $gb_type  GB type string ('post', 'media', 'term', 'related', …).
	 * @param array    $supports GB supports array.
	 * @param array    $options  Options array (passed to options_callback).
	 * @param callable $callback Return callback: fn( $options, $block, $instance ): string.
	 */
	public static function register_gb_tag(
		string $title,
		string $tag_name,
		string $gb_type,
		array $supports,
		array $options,
		callable $callback
	): void {
		if ( function_exists( 'bws_strip_default_select_values' ) ) {
			$options = bws_strip_default_select_values( $options );
		}
		new \GenerateBlocks_Register_Dynamic_Tag(
			[
				'title'    => $title,
				'tag'      => $tag_name,
				'type'     => $gb_type,
				'supports' => $supports,
				'options'  => $options,
				'return'   => $callback,
			]
		);
	}

}
