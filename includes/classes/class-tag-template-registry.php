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
			if ( ! SettingsPage::is_source_enabled( $source->get_source_key() ) ) {
				continue;
			}

			$source_context = $source->get_context_type(); // 'post' | 'term'

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

				// Register tag→source mapping (must precede is_tag_enabled check).
				$tag_default = self::compute_tag_default( $tpl, $source->get_tag_prefix() );
				SettingsPage::register_tag_source( $tag_name, $source, false, $tag_default );

				if ( ! SettingsPage::is_tag_enabled( $tag_name ) ) {
					continue;
				}

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
					$cf              = $tpl['core_fn'] ?? null;
					$options_fn_name = $tpl['options_fn'] ?? null;
					$effective_gb_type  = $tpl['gb_type'] ?? $source->get_gb_type();
					$effective_supports = $tpl['supports'] ?? [];
					$has_source_support = in_array( 'source', $effective_supports, true );
				}

				if ( ! $cf ) {
					continue; // No core function available for this context — skip.
				}

				$options = [];
				if ( ! empty( $options_fn_name ) && is_callable( $options_fn_name ) ) {
					$options = call_user_func( $options_fn_name );
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
					// Case 1 — sub-entity iteration (e.g. post → terms in taxonomy).
					$callback = static function ( $o, $b, $i ) use ( $sk, $cf, $gef ) {
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
				} else {
					// Case 0 — standard single-entity direct tag.
					$callback = static function ( $o, $b, $i ) use ( $sk, $cf, $has_source_support ) {
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

				self::register_gb_tag(
					$source->get_title_prefix() . ' ' . $tpl['title'],
					$tag_name,
					$effective_gb_type,
					$effective_supports,
					$options,
					$callback
				);
			}

			// ===
			// Related-variant tags
			// ===

			if ( ! $source->has_related_variant() ) {
				continue;
			}

			if ( ! SettingsPage::is_related_variant_enabled( $source->get_source_key() ) ) {
				continue;
			}

			foreach ( self::$templates as $tpl ) {
				// All-variant exclusion check (excludes both direct and related).
				if ( in_array( $source->get_source_key(), $tpl['excluded_source_keys'] ?? [], true ) ) {
					continue;
				}

				// Related variants always resolve to a post (via relationship traversal),
				// so only templates that support 'post' context apply. Skips term-only
				// templates like 'description' (which has core_fn = null).
				$supported_contexts = $tpl['context_types'] ?? [ 'post' ];
				if ( ! in_array( 'post', $supported_contexts, true ) ) {
					continue;
				}

				$tag_name = $source->get_related_tag_prefix() . '_' . $tpl['key'];

				if ( in_array( $tag_name, $existing, true ) ) {
					continue;
				}
				$existing[] = $tag_name;

				// Register tag→source mapping (must precede is_tag_enabled check).
				$tag_default = self::compute_tag_default( $tpl, $source->get_related_tag_prefix() );
				SettingsPage::register_tag_source( $tag_name, $source, true, $tag_default );

				if ( ! SettingsPage::is_tag_enabled( $tag_name ) ) {
					continue;
				}

				$cf = $tpl['core_fn'] ?? null;
				if ( ! $cf ) {
					continue;
				}

				// Related variant always gets relationship field options first, then template options.
				$options = array_merge(
					bws_get_relationship_field_options(),
					! empty( $tpl['options_fn'] ) && is_callable( $tpl['options_fn'] )
						? call_user_func( $tpl['options_fn'] ) : [],
					$source->get_source_options()
				);

				// List mode options for templates that support multi-result output.
				$supports_list = ! empty( $tpl['supports_list'] );
				$gef           = $tpl['get_entities_fn'] ?? null;
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

				$sk = $source->get_source_key();

				if ( $gef && is_callable( $gef ) ) {
					// Case 1 (related) — resolve one related post, then iterate sub-entities.
					$callback = static function ( $o, $b, $i ) use ( $sk, $cf, $gef ) {
						$src     = SourceRegistry::get_source( $sk );
						$base_id = $src ? $src->resolve_id( $o, $i ) : false;
						if ( ! $base_id ) {
							return '';
						}
						$rel_key = $o['rel'] ?? '';
						if ( empty( $rel_key ) ) {
							return '';
						}
						$acf_id  = $src->format_id_for_acf( $base_id );
						$related = bws_get_related_posts_data( $acf_id, $rel_key );
						$post_id = ! empty( $related ) ? bws_extract_post_id( $related[0] ) : false;
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
				} elseif ( $supports_list ) {
					// Case 2 — iterate multiple related posts (list mode).
					$callback = static function ( $o, $b, $i ) use ( $sk, $cf ) {
						$src     = SourceRegistry::get_source( $sk );
						$base_id = $src ? $src->resolve_id( $o, $i ) : false;
						if ( ! $base_id ) {
							return $cf( false, $o, $i );
						}
						$rel_key = $o['rel'] ?? '';
						if ( empty( $rel_key ) ) {
							return $cf( false, $o, $i );
						}
						$acf_id  = $src->format_id_for_acf( $base_id );
						$related = bws_get_related_posts_data( $acf_id, $rel_key );
						if ( empty( $related ) ) {
							return $cf( false, $o, $i );
						}
						$limit   = max( 1, (int) ( $o['limit'] ?? 1 ) );
						$sep     = $o['sep'] ?? ', ';
						$slice   = array_slice( $related, 0, $limit );
						$results = [];
						foreach ( $slice as $rel_item ) {
							$post_id = bws_extract_post_id( $rel_item );
							if ( ! $post_id ) {
								continue;
							}
							$val = $cf( $post_id, $o, $i );
							if ( '' !== $val ) {
								$results[] = $val;
							}
						}
						// Condition seam: a future filter here can return false to suppress output.
						// apply_filters( 'bws_dynamic_tag_condition', $results, $sk, $o, $i )
						return empty( $results ) ? $cf( false, $o, $i ) : implode( $sep, $results );
					};
				} else {
					// Standard single related post.
					$callback = static function ( $o, $b, $i ) use ( $sk, $cf ) {
						$src     = SourceRegistry::get_source( $sk );
						$base_id = $src ? $src->resolve_id( $o, $i ) : false;
						if ( ! $base_id ) {
							return $cf( false, $o, $i );
						}
						$rel_key = $o['rel'] ?? '';
						if ( empty( $rel_key ) ) {
							return $cf( false, $o, $i );
						}
						$acf_id  = $src->format_id_for_acf( $base_id );
						$related = bws_get_related_posts_data( $acf_id, $rel_key );
						$post_id = ! empty( $related ) ? bws_extract_post_id( $related[0] ) : false;
						// Condition seam: a future filter here can return false to suppress output.
						// apply_filters( 'bws_dynamic_tag_condition', $post_id, $sk, $o, $i )
						return $cf( $post_id ?: false, $o, $i );
					};
				}

				self::register_gb_tag(
					$source->get_related_title_prefix() . ' ' . $tpl['title'],
					$tag_name,
					$tpl['gb_type'] ?? $source->get_related_gb_type(),
					$tpl['supports'] ?? [],
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

		// Build source select options for src_N dropdowns.
		$src_options = array( array( 'value' => '', 'label' => __( '— None —', 'generateblocks' ) ) );
		foreach ( $effective_sources as $src_id => $entry ) {
			$src_options[] = array( 'value' => $src_id, 'label' => $entry['label'] );
		}

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

			if ( ! SettingsPage::is_tag_enabled( $tag_name ) ) {
				continue;
			}

			$core_fn      = $tpl['core_fn'] ?? null;
			$term_core_fn = $tpl['term_core_fn'] ?? null;

			if ( ! $core_fn ) {
				continue;
			}

			$per_slot_key  = ! empty( $tpl['try_per_slot_key'] );
			$context_types = $tpl['context_types'] ?? array( 'post' );

			// --- Build options array ---
			$options = array();
			for ( $n = 1; $n <= 3; $n++ ) {
				if ( $per_slot_key ) {
					$options[ "key_{$n}" ] = array(
						'type'        => 'text',
						'label'       => sprintf( __( 'Meta Key %d', 'generateblocks' ), $n ),
						'help'        => $n === 1
							? __( 'ACF or meta field key for this slot.', 'generateblocks' )
							: __( 'ACF or meta field key for this slot. Leave blank to fall through from the previous slot.', 'generateblocks' ),
						'placeholder' => 'field_name',
					);
				};
				$options[ "src_{$n}" ] = array(
					'type'    => 'select',
					'label'   => sprintf( __( 'Source %d', 'generateblocks' ), $n ),
					'help'    => __( 'Defaults to Post if blank. If blank and a relationship field is set, defaults to Related Post.', 'generateblocks' ),
					'options' => $src_options,
				);
				$options[ "rel_{$n}" ] = array(
					'type'        => 'text',
					'label'       => sprintf( __( 'Relationship Field %d', 'generateblocks' ), $n ),
					'help'        => __( 'ACF relationship field key. When set without a source, automatically uses Related Post.', 'generateblocks' ),
					'placeholder' => 'related_post',
				);
			}

			// Merge template's standard options.
			$tpl_options = ! empty( $tpl['options_fn'] ) && is_callable( $tpl['options_fn'] )
				? call_user_func( $tpl['options_fn'] )
				: array();

			// For per-slot-key templates, remove primary field key option (replaced by key_N).
			if ( $per_slot_key ) {
				unset( $tpl_options['field_key'], $tpl_options['meta_key'] );
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
			$cf     = $core_fn;
			$tcf    = $term_core_fn;
			$ctypes = $context_types;
			$psk    = $per_slot_key;
			$esrc   = $effective_sources;

			$callback = static function ( $opts, $b, $inst ) use ( $cf, $tcf, $ctypes, $psk, $esrc ) {
				// Default source is 'post'; overridden per slot by src_N or auto-detection.
				$last_entry = $esrc['post'] ?? null;
				$last_key   = null;

				foreach ( array( 1, 2, 3 ) as $n ) {
					$src_key      = $opts[ "src_{$n}" ] ?? '';
					$rel_for_slot = $opts[ "rel_{$n}" ] ?? '';

					if ( ! empty( $src_key ) ) {
						// Explicit source — update last_entry for fall-through.
						$entry = $esrc[ $src_key ] ?? null;
						if ( ! $entry ) {
							continue; // Unknown source key — skip slot.
						}
						$last_entry = $entry;
					} elseif ( ! empty( $rel_for_slot ) ) {
						// rel_N set without src_N → auto-detect 'related' source.
						$entry = $esrc['related'] ?? null;
						if ( ! $entry ) {
							continue; // No related source available — skip slot.
						}
						// Don't update $last_entry — this was inferred, not explicitly set.
					} else {
						// Fall through to $last_entry (defaults to 'post' for the first slot).
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

					// Select appropriate core function for this context type.
					$fn = ( 'term' === $ctx && $tcf ) ? $tcf : $cf;

					$base_id = $entry['source']->resolve_id( $opts, $inst );
					if ( ! $base_id ) {
						continue;
					}

					if ( $entry['is_related'] ) {
						$rel_key = $rel_for_slot;
						if ( empty( $rel_key ) ) {
							continue;
						}
						$acf_id    = $entry['source']->format_id_for_acf( $base_id );
						$related   = bws_get_related_posts_data( $acf_id, $rel_key );
						$entity_id = ! empty( $related ) ? bws_extract_post_id( $related[0] ) : false;
					} else {
						$entity_id = $base_id;
					}

					if ( ! $entity_id ) {
						continue;
					}

					if ( $psk ) {
						// Inject slot-specific key into opts, with fall-through from previous slot.
						$slot_key = $opts[ "key_{$n}" ] ?? '';
						if ( ! empty( $slot_key ) ) {
							$last_key = $slot_key;
						} else {
							$slot_key = $last_key; // Fall through from previous slot.
						}
						if ( empty( $slot_key ) ) {
							continue; // No key available for this slot — skip.
						}
						$slot_opts        = $opts;
						$slot_opts['key'] = $slot_key;
						$result = $fn( $entity_id, $slot_opts, $inst );
					} else {
						$result = $fn( $entity_id, $opts, $inst );
					}

					if ( ! empty( $result ) ) {
						return $result;
					}
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
