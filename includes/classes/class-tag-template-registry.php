<?php
/**
 * Tag Template Registry — generates dynamic tags from a source × template matrix.
 *
 * Call TagTemplateRegistry::register_template() for each template config, then
 * TagTemplateRegistry::generate_all_tags() to produce all source × template
 * combinations (direct tags + related-variant tags where the source supports them).
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

	/** @var array[] Registered template configs, in registration order. */
	private static array $templates = [];

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
	 * Register a tag template.
	 *
	 * @param array $config {
	 *     Template configuration. All keys are optional unless noted.
	 *
	 *     @type string        $key                         Required. Template key; appended to source prefix to form the tag name.
	 *     @type string        $title                       Required. Human-readable label fragment; prepended with source title prefix.
	 *     @type string|null   $gb_type                     GB type for post context. null = use source->get_gb_type().
	 *     @type array         $supports                    GB supports array for post context (e.g. ['link', 'source']).
	 *     @type string|null   $options_fn                  Callable name returning options array for post context.
	 *     @type callable|null $core_fn                     fn( $post_id, $options, $instance ) for post context.
	 *     @type array         $context_types               Source context types that may use this template. Default: ['post'].
	 *     @type callable|null $term_core_fn                fn( $term_id, $options, $instance ) for term context.
	 *     @type string|null   $term_options_fn             Options callable for term context. null = use $options_fn.
	 *     @type bool          $term_requires_gb_pro        If true, term context tag only generated when GB Pro is active.
	 *     @type array         $excluded_source_keys        Skip both direct and related-variant tags for these source keys.
	 *     @type array         $excluded_direct_source_keys Skip only the direct tag for these source keys.
	 *     @type bool          $supports_try                Include in generate_try_tags(). Default: false.
	 *     @type bool          $try_per_slot_key            true = key_N per slot (replaces shared 'meta' key in try_ variant).
	 *     @type callable|null $get_entities_fn             fn( $post_id, $options ) → array of sub-entities (e.g. WP_Term[]). When
	 *                                                      present, the callback iterates sub-entities; core_fn receives one entity ID
	 *                                                      at a time. Used for post-referenced term-extraction templates.
	 *     @type bool          $supports_list               When true, `limit` and `sep` options are added to the tag, and the callback
	 *                                                      iterates multiple entities (sub-entities for get_entities_fn, related posts
	 *                                                      otherwise). Default: false.
	 *     @type array         $default_enabled_map         Optional per-prefix default-enabled overrides. Keys are tag prefixes without
	 *                                                      trailing underscore (e.g. 'post', 'related_post', 'term'). Values are bool.
	 *                                                      Missing keys fall back to source-level default_enabled() methods.
	 * }
	 */
	public static function register_template( array $config ): void {
		self::$templates[] = $config;
	}

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

		// Build source dropdown for modifier tags: two entries (current entity, ref traversal).
		$traversal_src = $traversal_src_key ? SourceRegistry::get_source( $traversal_src_key ) : null;
		$source_opt    = array(
			'source' => array(
				'type'    => 'select',
				'label'   => __( 'Source:', 'generateblocks' ),
				'options' => array(
					array( 'value' => '',    'label' => __( 'Current (no traversal)', 'generateblocks' ) ),
					array(
						'value' => 'ref',
						'label' => $traversal_src
							? $traversal_src->get_source_label()
							: __( 'Ref/Rel Field', 'generateblocks' ),
					),
				),
			),
		);

		// Traversal sub-option: relationship field key shown when source:'ref'.
		$traversal_opts = array();
		if ( $traversal_src ) {
			$traversal_opts = array(
				'ref' => array(
					'type'        => 'text',
					'label'       => __( 'Traverse by meta key:', 'generateblocks' ),
					'help'        => __( 'ACF relationship or post object field key on the entity that links to the related post.', 'generateblocks' ),
					'placeholder' => 'related_posts',
					'show_if'     => array( 'source' => 'ref' ),
				),
			);
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

			if ( $is_image ) {
				// Image modifier: as (serialized) + size + source + traversal + use (ref only) + key + fallback.
				// `use:featured` shown only when source:ref — term entities have no featured image.
				$options = array_merge(
					array(
						'as'   => array(
							'type'    => 'select',
							'label'   => __( 'Return image as:', 'generateblocks' ),
							'default' => 'url',
							'options' => array(
								array( 'value' => 'url',     'label' => __( 'URL', 'generateblocks' ) ),
								array( 'value' => 'id',      'label' => __( 'ID', 'generateblocks' ) ),
								array( 'value' => 'title',   'label' => __( 'Title', 'generateblocks' ) ),
								array( 'value' => 'alt',     'label' => __( 'Alt Text', 'generateblocks' ) ),
								array( 'value' => 'caption', 'label' => __( 'Caption', 'generateblocks' ) ),
							),
						),
						'size' => array(
							'type'  => 'bws-img-size',
							'label' => __( 'Image Size', 'generateblocks' ),
						),
					),
					$source_opt,
					$traversal_opts,
					array(
						'use'      => array(
							'type'    => 'select',
							'label'   => __( 'Get image from:', 'generateblocks' ),
							'options' => array(
								array( 'value' => '',         'label' => __( 'Custom field (ACF / meta)', 'generateblocks' ) ),
								array( 'value' => 'featured', 'label' => __( 'Featured Image', 'generateblocks' ) ),
							),
							'show_if' => array( 'source' => 'ref' ),
						),
						'key'      => array(
							'type'        => 'text',
							'label'       => __( 'Field Key', 'generateblocks' ),
							'help'        => __( 'ACF or meta field key for the image.', 'generateblocks' ),
							'placeholder' => 'image_field',
							'show_if'     => array( 'use' => 'not:featured' ),
						),
						'fallback' => array(
							'type'  => 'bws-media-picker',
							'label' => __( 'Fallback Image', 'generateblocks' ),
						),
					)
				);
			} else {
				$options = array_merge( $source_opt, $traversal_opts, $tpl['options'] ?? [] );
			}

			$callback = self::make_modifier_callback( $base_src_key, $traversal_src_key, $term_fn, $post_fn );

			// Title: plain label when in its own gb_type group (modifier tags appear under their
			// own group in GB's picker, identified by gb_type). No cross-source parenthetical needed
			// because the type already distinguishes the group.
			$title = $modifier_label
				? ( $tpl['title'] ?? $tag_name ) . ' (' . $modifier_label . ')'
				: ( $tpl['title'] ?? $tag_name );

			self::register_gb_tag( $title, $tag_name, $gb_type, $base_supports, $options, $callback );
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
		callable $post_fn
	): callable {
		return static function ( $opts, $block, $inst ) use ( $base_src_key, $traversal_src_key, $term_fn, $post_fn ) {
			$source = $opts['source'] ?? '';

			if ( 'ref' === $source ) {
				// Traversal from modifier entity (term) → related post.
				// register_modifier() hardcodes 'ref' option; resolve_id() expects 'rel' internally.
				$src = SourceRegistry::get_source( $traversal_src_key );
				if ( ! $src ) {
					return '';
				}
				$mapped        = $opts;
				$mapped['rel'] = $opts['ref'] ?? '';
				$entity_id     = $src->resolve_id( $mapped, $inst );
				return $post_fn( $entity_id, $opts, $inst );
			}

			// Source unset — resolve entity directly (e.g. TaxonomyTerm via GB term picker + context).
			$src       = SourceRegistry::get_source( $base_src_key );
			$entity_id = $src ? $src->resolve_id( $opts, $inst ) : false;
			return $term_fn( $entity_id, $opts, $inst );
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
	 * Slot option naming:
	 *   Slot 1 source: 'source'; slots 2–5: 'N-src'.
	 *   Slot 1 use:    'use';    slots 2–5: 'N-use'.
	 *   All slots: 'N-ref', 'N-srcTerm', 'N-tax', 'N-key'.
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

		// Source dropdown options shared across all slots.
		$source_options = [
			[ 'value' => '',    'label' => __( 'Current Post', 'generateblocks' ) ],
			[ 'value' => 'ref', 'label' => __( 'Related Post (ref field)', 'generateblocks' ) ],
		];

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

			$try_core_fn  = $tpl['try_core_fn'] ?? null;
			$try_term_fn  = $tpl['try_term_fn'] ?? null;
			$per_slot_key = ! empty( $tpl['try_per_slot_key'] );
			$per_slot_use = ! empty( $tpl['try_per_slot_use'] );
			$no_key_uses  = $tpl['try_use_no_key_values'] ?? [];
			$tpl_options  = $tpl['options'] ?? [];

			if ( ! $try_core_fn ) {
				continue;
			}

			// --- Build per-slot options ---
			$options = [];

			for ( $n = 1; $n <= 5; $n++ ) {
				$prev    = $n - 1;
				$src_key = ( 1 === $n ) ? 'source'  : "{$n}-src";
				$use_key = ( 1 === $n ) ? 'use'     : "{$n}-use";

				// Slot trigger: slots 3-5 appear when any of the prior slot's options are set.
				// The source key is always the slot's primary control and carries the trigger.
				if ( $n <= 2 ) {
					$slot_trigger = [];
				} else {
					$prev_src = ( 1 === $prev ) ? 'source'  : "{$prev}-src";
					$prev_any = [
						$prev_src         => 'not_empty',
						"{$prev}-ref"     => 'not_empty',
						"{$prev}-srcTerm" => 'not_empty',
					];
					if ( $per_slot_key || $per_slot_use ) {
						$prev_any["{$prev}-key"] = 'not_empty';
					}
					if ( $per_slot_use ) {
						$prev_use = ( 1 === $prev ) ? 'use' : "{$prev}-use";
						$prev_any[ $prev_use ] = 'not_empty';
					}
					$slot_trigger = [ 'show_if_any' => $prev_any ];
				}

				// Source selector — always first; governs which sub-options appear.
				$options[ $src_key ] = array_merge(
					[
						'type'    => 'select',
						/* translators: %d: slot number */
						'label'   => sprintf( __( 'Slot %d: Source', 'generateblocks' ), $n ),
						'options' => $source_options,
					],
					$slot_trigger
				);

				// N-ref — relationship field key (source = 'ref').
				$options[ "{$n}-ref" ] = [
					'type'        => 'text',
					/* translators: %d: slot number */
					'label'       => sprintf( __( 'Slot %d: Relationship Field', 'generateblocks' ), $n ),
					'help'        => __( 'ACF relationship field key.', 'generateblocks' ),
					'placeholder' => 'related_post',
					'show_if'     => [ $src_key => 'ref' ],
				];

				// N-srcTerm — term hop modifier (no carry-forward).
				$options[ "{$n}-srcTerm" ] = array_merge(
					[
						'type'  => 'checkbox',
						/* translators: %d: slot number */
						'label' => sprintf( __( 'Slot %d: Get from taxonomy term?', 'generateblocks' ), $n ),
						'help'  => __( 'Field is in a taxonomy term on this source.', 'generateblocks' ),
					],
					$slot_trigger
				);

				// N-tax — taxonomy slug (shown when N-srcTerm set).
				$options[ "{$n}-tax" ] = [
					'type'        => 'text',
					/* translators: %d: slot number */
					'label'       => sprintf( __( 'Slot %d: Taxonomy', 'generateblocks' ), $n ),
					'help'        => __( 'Taxonomy slug (e.g. category, post_tag).', 'generateblocks' ),
					'placeholder' => 'category',
					'show_if'     => [ "{$n}-srcTerm" => 'not_empty' ],
				];

				// N-use — per-slot source type (try_per_slot_use templates only).
				if ( $per_slot_use && ! empty( $tpl_options['use']['options'] ) ) {
					$options[ $use_key ] = array_merge(
						[
							'type'    => 'select',
							/* translators: %d: slot number */
							'label'   => sprintf( __( 'Slot %d: Source Type', 'generateblocks' ), $n ),
							'options' => $tpl_options['use']['options'],
						],
						$slot_trigger
					);
				}

				// N-key — per-slot field key.
				// try_per_slot_key: always shown within slot (same trigger as source key).
				// try_per_slot_use: shown only when N-use = 'key' (custom-field mode).
				if ( $per_slot_key ) {
					$options[ "{$n}-key" ] = array_merge(
						[
							'type'        => 'text',
							/* translators: %d: slot number */
							'label'       => sprintf( __( 'Slot %d: Field Key', 'generateblocks' ), $n ),
							'help'        => __( 'ACF or meta field key for this slot.', 'generateblocks' ),
							'placeholder' => 'field_name',
						],
						$slot_trigger
					);
				} elseif ( $per_slot_use ) {
					$options[ "{$n}-key" ] = array_merge(
						[
							'type'        => 'text',
							/* translators: %d: slot number */
							'label'       => sprintf( __( 'Slot %d: Field Key', 'generateblocks' ), $n ),
							'help'        => __( 'ACF or meta field key. Required when Source Type is Custom Field.', 'generateblocks' ),
							'placeholder' => 'field_name',
						],
						[ 'show_if' => [ $use_key => 'key' ] ]
					);
				}
			}

			// Append template-level trailing options (fallback, image size, etc.).
			// Strip options replaced by per-slot equivalents.
			$trailing_opts = $tpl_options;
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

			$callback = static function ( $opts, $b, $inst ) use ( $cf, $tcf, $psk, $psu, $nku ) {
				$fallback  = sanitize_text_field( $opts['fallback'] ?? $opts['fallback_text'] ?? '' );
				$eval_opts = array_diff_key( $opts, [ 'fallback' => null, 'fallback_text' => null ] );

				// Carry-forward state across slots.
				$last_src = '';  // '' = current post context.
				$last_ref = '';
				$last_tax = '';
				$last_key = '';
				$last_use = '';

				foreach ( range( 1, 5 ) as $n ) {
					$src_k = ( 1 === $n ) ? 'source' : "{$n}-src";
					$use_k = ( 1 === $n ) ? 'use'    : "{$n}-use";

					$src_raw = $opts[ $src_k ]          ?? '';
					$ref_raw = $opts[ "{$n}-ref" ]      ?? '';
					$stm_raw = $opts[ "{$n}-srcTerm" ]  ?? '';
					$tax_raw = $opts[ "{$n}-tax" ]      ?? '';
					$key_raw = $opts[ "{$n}-key" ]      ?? '';
					$use_raw = $opts[ $use_k ]          ?? '';

					// Skip slot if it contributes no new configuration relative to the previous slot.
					if ( $n > 1 ) {
						$has_new = '' !== $src_raw
							|| '' !== $ref_raw
							|| '' !== $stm_raw
							|| '' !== $tax_raw
							|| ( ( $psk || $psu ) && '' !== $key_raw )
							|| ( $psu && '' !== $use_raw );
						if ( ! $has_new ) {
							continue;
						}
					}

					// Update carry-forward values (srcTerm does NOT carry forward).
					if ( '' !== $src_raw ) { $last_src = $src_raw; }
					if ( '' !== $ref_raw ) { $last_ref = $ref_raw; }
					if ( '' !== $tax_raw ) { $last_tax = $tax_raw; }
					if ( '' !== $key_raw ) { $last_key = $key_raw; }
					if ( '' !== $use_raw ) { $last_use = $use_raw; }

					// Build slot-specific options (injected into core fn call).
					$slot_opts = $eval_opts;

					if ( $psk ) {
						// When psu is also active, certain use values (e.g. 'featured') don't need a key.
						$in_no_key_mode = $psu && in_array( $last_use, $nku, true );
						if ( ! $in_no_key_mode && empty( $last_key ) ) {
							continue; // No field key — skip slot.
						}
						if ( ! empty( $last_key ) ) {
							$slot_opts['key'] = $last_key;
						}
					}

					if ( $psu ) {
						$slot_opts['use'] = $last_use;
						if ( 'key' === $last_use ) {
							if ( empty( $last_key ) ) {
								continue; // Custom-field mode but no key — skip slot.
							}
							$slot_opts['key'] = $last_key;
						}
					}

					// srcTerm dispatch: resolve post → get terms → call try_term_fn.
					// srcTerm is read from this slot only (no carry-forward).
					if ( '' !== $stm_raw && $tcf ) {
						$src_opts = array_merge( $slot_opts, [
							'source' => $last_src,
							'ref'    => $last_ref,
						] );
						$post_id = function_exists( 'bws_resolve_post_by_source' )
							? bws_resolve_post_by_source( $src_opts, $inst )
							: get_the_ID();
						if ( $post_id && function_exists( 'bws_get_srcterm_terms' ) ) {
							$terms = bws_get_srcterm_terms( (int) $post_id, sanitize_key( $last_tax ) );
							foreach ( $terms as $term ) {
								$result = $tcf( $term->term_id, $slot_opts, $inst );
								if ( '' !== $result && false !== $result ) {
									return $result;
								}
							}
						}
						continue; // All terms empty — try next slot.
					}

					// Post-based paths: '' | 'ref'.
					$src_opts = array_merge( $slot_opts, [
						'source' => $last_src,
						'ref'    => $last_ref,
					] );
					$post_id = function_exists( 'bws_resolve_post_by_source' )
						? bws_resolve_post_by_source( $src_opts, $inst )
						: ( '' === $last_src ? get_the_ID() : false );

					if ( ! $post_id ) {
						continue;
					}

					$result = $cf( $post_id, $slot_opts, $inst );
					if ( '' !== $result && false !== $result ) {
						return $result;
					}
				}

				// All slots exhausted — apply fallback_text.
				if ( '' !== $fallback ) {
					return \GenerateBlocks_Dynamic_Tag_Callbacks::output( $fallback, $opts, $inst );
				}

				return '';
			};

			/* translators: %s: tag title e.g. "Text Fields" */
			$title = sprintf( __( 'Try %s', 'generateblocks' ), $tpl['title'] ?? $tag_name );

			// No native supports — all controls registered as custom options.
			$supports = [];

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

	// ===
	// Tag generation
	// ===

	/**
	 * Generate all source × template tag combinations.
	 *
	 * Iterates each registered source. For each source:
	 * 1. Generates direct tags (source prefix + template key) for matching context types.
	 * 2. If the source has a related variant, generates related-variant tags (related prefix + template key)
	 *    for templates that support 'post' context.
	 *
	 * A GB duplicate-check guard skips any tag name already registered.
	 */
	public static function generate_all_tags(): void {
		if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
			return;
		}

		// Snapshot of already-registered GB tag names for the dup-check guard.
		$existing = array_keys( \GenerateBlocks_Register_Dynamic_Tag::get_tags() ?? [] );

		foreach ( SourceRegistry::get_all_sources() as $source ) {
			$source_context = $source->get_context_type(); // 'post' | 'term'

			// Gate term-context sources on the term_ modifier toggle.
			if ( 'term' === $source_context && ! SettingsPage::is_modifier_enabled( 'term' ) ) {
				continue;
			}

			// ===
			// Direct tags
			// ===

			foreach ( self::$templates as $tpl ) {
				// Context type filter: skip templates that don't support this source's context.
				$supported_contexts = $tpl['context_types'] ?? [ 'post' ];
				if ( ! in_array( $source_context, $supported_contexts, true ) ) {
					continue;
				}

				// GB Pro guard for term-context tags that require it.
				if ( 'term' === $source_context && ! empty( $tpl['term_requires_gb_pro'] ) ) {
					if ( ! class_exists( 'GenerateBlocks_Pro_Dynamic_Tags_Register' ) ) {
						continue;
					}
				}

				// Source exclusion check (both direct and all-variant exclusions).
				if ( in_array(
					$source->get_source_key(),
					array_merge(
						$tpl['excluded_source_keys'] ?? [],
						$tpl['excluded_direct_source_keys'] ?? []
					),
					true
				) ) {
					continue;
				}

				$tag_name = $source->get_tag_prefix() . '_' . $tpl['key'];

				// GB duplicate-check guard: skip if already registered (e.g., GB's own post_title).
				if ( in_array( $tag_name, $existing, true ) ) {
					continue;
				}
				$existing[] = $tag_name;

				// Determine effective core fn, options fn, gb_type, and supports based on context.
				if ( 'term' === $source_context ) {
					// Term context: use term_core_fn. gb_type always comes from source ('term').
					// 'source' support is always added — GB's term source selector stores term ID
					// as $options['id'], which resolve_id() uses to pick the correct term.
					$cf              = $tpl['term_core_fn'] ?? null;
					$options_fn_name = $tpl['term_options_fn'] ?? $tpl['options_fn'] ?? null;
					$effective_gb_type  = $source->get_gb_type();
					$effective_supports = array_unique( array_merge( $tpl['supports'] ?? [], [ 'source' ] ) );
					$has_source_support = true;
				} else {
					// Post context: use core_fn. 'source' support means $options['id'] is a
					// post ID override (set by the GB source selector). Media-type tags intentionally
					// omit 'source' — for those, $options['id'] is the fallback attachment ID and
					// must not be used for post resolution.
					// get_excluded_supports() lets a source strip supports irrelevant to its ID
					// resolution strategy (e.g. a source with its own detector can exclude 'source').
					$cf              = $tpl['core_fn'] ?? null;
					$options_fn_name = $tpl['options_fn'] ?? null;
					$effective_gb_type  = $tpl['gb_type'] ?? $source->get_gb_type();
					$excluded_supports  = $source->get_excluded_supports();
					$effective_supports = $excluded_supports
						? array_values( array_diff( $tpl['supports'] ?? [], $excluded_supports ) )
						: $tpl['supports'] ?? [];
					$has_source_support = in_array( 'source', $effective_supports, true );
				}

				if ( ! $cf ) {
					continue; // No core function available for this context — skip.
				}

				$options = [];
				// Traversal sources with no source-level options need the relationship field
				// injected here (e.g. RelatedPost, TermRelatedPost). Sources that already include
				// 'rel' in get_source_options() (e.g. SecondRelatedPost) are skipped.
				if ( $source->needs_relationship_field()
					&& empty( $source->get_source_options() )
					&& function_exists( 'bws_get_relationship_field_options' ) ) {
					$options = bws_get_relationship_field_options();
				}
				if ( ! empty( $options_fn_name ) && is_callable( $options_fn_name ) ) {
					$options = array_merge( $options, call_user_func( $options_fn_name ) );
				}
				$options = array_merge( $options, $source->get_source_options() );

				// List mode: add limit/sep options for templates that support multi-result output.
				$supports_list = ! empty( $tpl['supports_list'] );
				if ( $supports_list ) {
					$options['limit'] = array(
						'type'  => 'number',
						'label' => __( 'Limit', 'generateblocks' ),
						'help'  => __( 'Maximum number of results to return. Default: 1.', 'generateblocks' ),
					);
					$options['sep'] = array(
						'type'        => 'text',
						'label'       => __( 'Separator', 'generateblocks' ),
						'help'        => __( 'Text to place between results. Default: ", ".', 'generateblocks' ),
						'placeholder' => ', ',
					);
				}

				$gef = $tpl['get_entities_fn'] ?? null;
				$sk  = $source->get_source_key();

				if ( $gef && is_callable( $gef ) ) {
					$callback = self::make_entities_callback( $sk, $cf, $gef );
				} else {
					$callback = self::make_direct_callback( $sk, $cf, $has_source_support );
				}

				self::register_gb_tag(
					$source->get_source_label() . ' ' . $tpl['title'],
					$tag_name,
					$effective_gb_type,
					$effective_supports,
					$options,
					$callback
				);
			}
		}
	}

	/**
	 * Get all registered templates (read-only).
	 *
	 * Used by the settings page to compute expected tag groups without triggering registration.
	 *
	 * @return array[]
	 */
	public static function get_templates(): array {
		return self::$templates;
	}

	// ===
	// Callback factories
	// ===

	/**
	 * Direct single-entity callback.
	 * Resolves the source entity ID and calls the core function.
	 */
	private static function make_direct_callback( string $sk, callable $cf, bool $has_source_support ): callable {
		return static function ( $o, $b, $i ) use ( $sk, $cf, $has_source_support ) {
			$src = SourceRegistry::get_source( $sk );
			// Strip 'id' from opts before resolving entity so attachment IDs don't pollute
			// resolution on media-type post tags. For term-type tags has_source_support=true,
			// so id is preserved (used as term ID by the GB source selector).
			$resolve_opts = $has_source_support ? $o : array_diff_key( $o, [ 'id' => null ] );
			$entity_id    = $src ? $src->resolve_id( $resolve_opts, $i ) : false;
			// Condition seam: a future filter here can return false to suppress output.
			// apply_filters( 'bws_dynamic_tag_condition', $entity_id, $sk, $o, $i )
			return $cf( $entity_id, $o, $i );
		};
	}

	/**
	 * Direct sub-entity iteration callback (e.g. post -> terms in taxonomy).
	 * Resolves the source entity ID, fetches sub-entities via get_entities_fn, iterates them.
	 */
	private static function make_entities_callback( string $sk, callable $cf, callable $gef ): callable {
		return static function ( $o, $b, $i ) use ( $sk, $cf, $gef ) {
			$src     = SourceRegistry::get_source( $sk );
			$post_id = $src ? $src->resolve_id( $o, $i ) : false;
			if ( ! $post_id ) {
				return '';
			}
			$entities = $gef( $post_id, $o );
			if ( empty( $entities ) ) {
				return '';
			}
			$limit   = max( 1, (int) ( $o['limit'] ?? 1 ) );
			$sep     = $o['sep'] ?? ', ';
			$slice   = array_slice( $entities, 0, $limit );
			$results = [];
			foreach ( $slice as $entity ) {
				$val = $cf( $entity->term_id, $o, $i );
				if ( '' !== $val ) {
					$results[] = $val;
				}
			}
			return implode( $sep, $results );
		};
	}

	// ===
	// Try-tag generation
	// ===

	/**
	 * Generate try_ fallback-chain tags.
	 *
	 * One try_ tag per eligible template (supports_try = true). Each tag accepts up to three
	 * source slots (src_1/2/3) and returns the first non-empty result across them.
	 *
	 * Slot options:
	 *   src_N — effective source key (e.g., 'post', 'related', 'term'). Defaults to Post if
	 *            blank. Falls through from the previous slot when a prior source was explicit.
	 *            If blank and rel_N is set, auto-selects Related Post.
	 *   rel_N — ACF relationship field key. When set without src_N, auto-selects Related Post.
	 *   key_N — field key per slot (only for templates with try_per_slot_key = true).
	 *            Blank key_N falls through from the previous slot's key.
	 */
	public static function generate_try_tags(): void {
		if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
			return;
		}

		$effective_sources = SourceRegistry::get_effective_sources();
		if ( empty( $effective_sources ) ) {
			return;
		}

		// Sources eligible for try_ slot selection: exclude any source that requires its own
		// global relationship options (e.g. SecondRelatedPost uses 'rel'/'rel_2' at the tag
		// level, not per-slot, so it cannot participate in the slot-based source system).
		// Also exclude term-context sources when the term_ modifier is disabled.
		$try_slot_sources = array_filter(
			$effective_sources,
			static fn( $entry ) => empty( $entry['source']->get_source_options() )
				&& SourceRegistry::is_source_enabled( $entry['source']->get_source_key() )
		);

		// Snapshot existing tags for dup-check.
		$existing = array_keys( \GenerateBlocks_Register_Dynamic_Tag::get_tags() ?? array() );

		foreach ( self::$templates as $tpl ) {
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

			$core_fn      = $tpl['core_fn'] ?? null;
			$term_core_fn = $tpl['term_core_fn'] ?? null;

			if ( ! $core_fn ) {
				continue;
			}

			$per_slot_key  = ! empty( $tpl['try_per_slot_key'] );
			$per_slot_type = ! empty( $tpl['try_per_slot_type'] );
			$context_types = $tpl['context_types'] ?? array( 'post' );

			// --- Build options array ---

			// Source IDs whose related variant requires a relationship field (rel_N).
			// Computed from try_slot_sources only (SecondRelatedPost already excluded).
			$related_src_ids = array_keys( array_filter(
				$try_slot_sources,
				static fn( $e ) => $e['needs_rel']
			) );

			// Slot 1 source options: no inherit entry — slot 1 must have an explicit source.
			// First entry is the visual default (post context direct source where available).
			$src_options_base = array();
			foreach ( $try_slot_sources as $src_id => $entry ) {
				$src_options_base[] = array( 'value' => $src_id, 'label' => $entry['label'] );
			}

			$options = array();
			for ( $n = 1; $n <= 5; $n++ ) {
				$prev = $n - 1;

				// Slots 1–2 always visible. Slots 3–5 appear when the previous slot is
				// configured: its source was changed, a field key was entered, or a relationship
				// field was set (rel_N set implies a related source is active or will be used).
				// show_if_any = OR logic; show_if = AND logic (both in editor-conditional-options.js).
				if ( $n <= 2 ) {
					$slot_trigger = array();
				} elseif ( $per_slot_key ) {
					$slot_trigger = array(
						'show_if_any' => array(
							"src_{$prev}" => 'not_empty',
							"key_{$prev}" => 'not_empty',
							"rel_{$prev}" => 'not_empty',
						),
					);
				} elseif ( $per_slot_type ) {
					$slot_trigger = array(
						'show_if_any' => array(
							"src_{$prev}"  => 'not_empty',
							"type_{$prev}" => 'not_empty',
							"key_{$prev}"  => 'not_empty',
							"rel_{$prev}"  => 'not_empty',
						),
					);
				} else {
					$slot_trigger = array( 'show_if_any' => array( "src_{$prev}" => 'not_empty', "rel_{$prev}" => 'not_empty' ) );
				}

				// Source select: slot 1 uses the base list; slots 2+ prepend the inherit entry.
				if ( $n === 1 ) {
					$src_opts = $src_options_base;
					$src_help = __( 'Source for this slot.', 'generateblocks' );
				} else {
					$src_opts = array_merge(
						array( array(
							'value' => '',
							/* translators: %d: previous slot number */
							'label' => sprintf( __( '— Same as Slot %d —', 'generateblocks' ), $prev ),
						) ),
						$src_options_base
					);
					$src_help = __( "Leave as 'Same as…' to inherit the previous slot's source.", 'generateblocks' );
				}

				// src_N — always first within the slot.
				$options[ "src_{$n}" ] = array_merge(
					array(
						'type'    => 'select',
						'label'   => sprintf( __( 'Source %d', 'generateblocks' ), $n ),
						'help'    => $src_help,
						'options' => $src_opts,
					),
					$slot_trigger
				);

				// rel_N — shown when src_N is a relationship-type source, or when the previous
				// slot had a relationship field set (carry-forward: user may want a different rel key).
				// Slot-level gating is not duplicated: hidden slots cannot set src_N to a related source.
				if ( ! empty( $related_src_ids ) ) {
					if ( $n === 1 ) {
						$rel_visibility = array( 'show_if' => array( "src_{$n}" => $related_src_ids ) );
					} else {
						$rel_visibility = array( 'show_if_any' => array( "src_{$n}" => $related_src_ids, "rel_{$prev}" => 'not_empty' ) );
					}
					$options[ "rel_{$n}" ] = array_merge( array(
						'type'        => 'text',
						'label'       => sprintf( __( 'Relationship Field %d', 'generateblocks' ), $n ),
						'help'        => __( 'ACF relationship field key. Leave blank to inherit from the previous slot.', 'generateblocks' ),
						'placeholder' => 'related_post',
					), $rel_visibility );
				}

				// key_N — per-slot-key templates only; same slot trigger as src_N.
				if ( $per_slot_key ) {
					$options[ "key_{$n}" ] = array_merge(
						array(
							'type'        => 'text',
							'label'       => sprintf( __( 'Meta Key %d', 'generateblocks' ), $n ),
							'help'        => $n === 1
								? __( 'ACF or meta field key for this slot.', 'generateblocks' )
								: __( 'Leave blank to use the same key as the previous slot.', 'generateblocks' ),
							'placeholder' => 'field_name',
						),
						$slot_trigger
					);
				}

				// type_N + key_N — per-slot-type templates only.
				if ( $per_slot_type ) {
					$type_options = array(
						array( 'value' => '',             'label' => __( 'Content / Description', 'generateblocks' ) ),
						array( 'value' => 'custom_field', 'label' => __( 'Custom Field', 'generateblocks' ) ),
					);
					$options[ "type_{$n}" ] = array_merge(
						array(
							'type'    => 'select',
							'label'   => sprintf( __( 'Content Type %d', 'generateblocks' ), $n ),
							'options' => $type_options,
						),
						$slot_trigger
					);
					// key_N is shown only when type_N is explicitly set to 'custom_field'.
					$options[ "key_{$n}" ] = array_merge(
						array(
							'type'        => 'text',
							'label'       => sprintf( __( 'Meta Key %d', 'generateblocks' ), $n ),
							'help'        => $n === 1
								? __( 'ACF or meta field key for this slot.', 'generateblocks' )
								: __( 'Leave blank to use the same key as the previous slot.', 'generateblocks' ),
							'placeholder' => 'field_name',
						),
						array( 'show_if' => array( "type_{$n}" => 'custom_field' ) )
					);
				}
			}

			// Merge template's standard options.
			$tpl_options = ! empty( $tpl['options_fn'] ) && is_callable( $tpl['options_fn'] )
				? call_user_func( $tpl['options_fn'] )
				: array();

			// For per-slot-key templates, remove primary field key option (replaced by key_N).
			if ( $per_slot_key ) {
				unset( $tpl_options['key'], $tpl_options['field_key'], $tpl_options['meta_key'] );
			}

			// For per-slot-type templates, remove shared type/key options (replaced by type_N/key_N).
			if ( $per_slot_type ) {
				unset( $tpl_options['type'], $tpl_options['key'] );
			}

			$options = array_merge( $options, $tpl_options );

			// --- Adjust supports ---
			// Always strip 'source' — try_ tags use src_N for source selection.
			$supports = array_values( array_diff( $tpl['supports'] ?? array(), array( 'source' ) ) );
			// Strip 'meta' for per-slot-key templates (key_N replaces shared meta key).
			if ( $per_slot_key ) {
				$supports = array_values( array_diff( $supports, array( 'meta' ) ) );
			}

			// All try_ tags use a custom gb_type so they are listed separately (since they are cross-source, standard source groups are not applicable).
			$gb_type = 'try';

			// Capture values for closure.
			$cf    = $core_fn;
			$tcf   = $term_core_fn;
			$ctypes = $context_types;
			$psk   = $per_slot_key;
			$esrc  = $try_slot_sources; // Only slot-eligible sources in the callback.
			$pst        = $per_slot_type;
			$cf_custom  = 'bws_post_custom_text_core';
			$tcf_custom = 'bws_term_custom_text_core';

			$callback = static function ( $opts, $b, $inst ) use ( $cf, $tcf, $ctypes, $psk, $pst, $cf_custom, $tcf_custom, $esrc ) {
				// Strip fallback_text from opts used in slot evaluation so it doesn't short-circuit
				// the try_ chain — a slot returning fallback_text would look like a real value and
				// prevent later slots from being tried. Apply fallback_text only after all slots fail.
				$fallback  = sanitize_text_field( $opts['fallback_text'] ?? '' );
				$eval_opts = array_diff_key( $opts, array( 'fallback_text' => null ) );

				// Slot state carries forward when a slot's src/key/rel is left blank (inherit).
				// Slot 1 defaults to the first eligible source (typically 'post').
				reset( $esrc );
				$first_src_id = key( $esrc );
				$last_entry   = $first_src_id ? ( $esrc[ $first_src_id ] ?? null ) : null;
				$last_key     = null;
				$last_rel     = null;
				$last_type    = ''; // '' = content/description (default).

				foreach ( range( 1, 5 ) as $n ) {
					$src_key      = $opts[ "src_{$n}" ] ?? '';
					$rel_for_slot = $opts[ "rel_{$n}" ] ?? '';

					// Skip slot if it contributes no new configuration — all of src, rel, and key
					// (for per-slot-key templates) are blank. An unset slot would produce the same
					// result as the previous slot, so there is nothing new to try.
					if ( $n > 1 ) {
						$has_new_config = ! empty( $src_key )
							|| ! empty( $rel_for_slot )
							|| ( $psk && ! empty( $opts[ "key_{$n}" ] ?? '' ) )
							|| ( $pst && ! empty( $opts[ "type_{$n}" ] ?? '' ) )
							|| ( $pst && ! empty( $opts[ "key_{$n}" ] ?? '' ) );
						if ( ! $has_new_config ) {
							continue;
						}
					}

					if ( ! empty( $src_key ) ) {
						// Explicit source — update last_entry for carry-forward.
						$entry = $esrc[ $src_key ] ?? null;
						if ( ! $entry ) {
							continue; // Unknown or excluded source key — skip slot.
						}
						$last_entry = $entry;
					} elseif ( ! empty( $rel_for_slot ) && isset( $esrc['related'] ) ) {
						// rel_N set without src_N — auto-select the related post source.
						$entry      = $esrc['related'];
						$last_entry = $entry;
					} else {
						// Blank src_N = inherit ('Same as…') — carry forward last_entry.
						$entry = $last_entry;
						if ( ! $entry ) {
							continue;
						}
					}

					// Skip if source context type isn't supported by this template.
					$ctx = $entry['source']->get_context_type();
					if ( ! in_array( $ctx, $ctypes, true ) ) {
						continue;
					}

					// For $pst templates, dispatch happens inside the elseif branch below; $fn is used only
					// for standard (non-psk, non-pst) templates in the final else.
					// Select appropriate core function for this context type.
					$fn = ( 'term' === $ctx && $tcf ) ? $tcf : $cf;

					if ( $entry['needs_rel'] ) {
						if ( ! empty( $rel_for_slot ) ) {
							$last_rel = $rel_for_slot;
						}
						if ( empty( $last_rel ) ) {
							continue; // No rel available for this slot.
						}
						$eval_opts['rel'] = $last_rel; // Inject for resolve_id().
					}
					$entity_id = $entry['source']->resolve_id( $eval_opts, $inst );

					if ( ! $entity_id ) {
						continue;
					}

					if ( $psk ) {
						// Per-slot-key: use this slot's key, or carry forward last_key.
						$slot_key = $opts[ "key_{$n}" ] ?? '';
						if ( ! empty( $slot_key ) ) {
							$last_key = $slot_key;
						} else {
							$slot_key = $last_key;
						}
						if ( empty( $slot_key ) ) {
							continue; // No field key available — skip slot.
						}
						$slot_opts        = $eval_opts;
						$slot_opts['key'] = $slot_key;
						$result = $fn( $entity_id, $slot_opts, $inst );
					} elseif ( $pst ) {
						// Per-slot-type: use this slot's type (carry forward) and dispatch to
						// the appropriate core function for this type × context combination.
						$slot_type = $opts[ "type_{$n}" ] ?? '';
						if ( ! empty( $slot_type ) ) {
							$last_type = $slot_type;
						} else {
							$slot_type = $last_type;
						}

						if ( 'custom_field' === $slot_type ) {
							// Custom field: also carry forward key_N.
							$slot_key = $opts[ "key_{$n}" ] ?? '';
							if ( ! empty( $slot_key ) ) {
								$last_key = $slot_key;
							} else {
								$slot_key = $last_key;
							}
							if ( empty( $slot_key ) ) {
								continue; // No field key — skip slot.
							}
							$dispatch_fn      = ( 'term' === $ctx && $tcf_custom ) ? $tcf_custom : $cf_custom;
							$slot_opts        = $eval_opts;
							$slot_opts['key'] = $slot_key;
							$result = $dispatch_fn( $entity_id, $slot_opts, $inst );
						} else {
							// Content/description type — use the template's own core functions.
							$dispatch_fn = ( 'term' === $ctx && $tcf ) ? $tcf : $cf;
							$result = $dispatch_fn( $entity_id, $eval_opts, $inst );
						}
					} else {
						$result = $fn( $entity_id, $eval_opts, $inst );
					}

					if ( ! empty( $result ) ) {
						return $result;
					}
				}

				// All slots exhausted — apply fallback_text if set.
				if ( '' !== $fallback ) {
					return \GenerateBlocks_Dynamic_Tag_Callbacks::output( $fallback, $opts, $inst );
				}

				return '';
			};

			/* translators: %s: tag title e.g. "Featured Image" */
			$title = sprintf( __( 'Try %s', 'generateblocks' ), $tpl['title'] );

			self::register_gb_tag( $title, $tag_name, $gb_type, $supports, $options, $callback );
		}
	}

	// ===
	// Helpers
	// ===

	/**
	 * Look up a per-tag default-enabled override from a template's `default_enabled_map`.
	 *
	 * @param array  $tpl    Template config.
	 * @param string $prefix Tag prefix (without trailing underscore) for the current source context,
	 *                       e.g. 'post', 'related_post', 'term', 'term_related_post'.
	 * @return bool|null bool if the map has an explicit entry; null to defer to source-level default.
	 */
	private static function compute_tag_default( array $tpl, string $prefix ): ?bool {
		$map = $tpl['default_enabled_map'] ?? [];
		$key = rtrim( $prefix, '_' );
		return isset( $map[ $key ] ) ? (bool) $map[ $key ] : null;
	}
}
