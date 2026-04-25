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

			$callback = self::make_modifier_callback( $base_src_key, $traversal_src_key, $term_fn, $post_fn, $tag_name );

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
		callable $post_fn,
		string $tag_name = ''
	): callable {
		return static function ( $opts, $block, $inst ) use ( $base_src_key, $traversal_src_key, $term_fn, $post_fn, $tag_name ) {
			if ( $tag_name && ! empty( $inst->context['bwsEditorPreview'] ) ) {
				return function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $opts, $tag_name ) : '';
			}

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

}
