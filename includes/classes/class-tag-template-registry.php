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
	 *   try_site_fn      callable|null  fn($opts, $inst): string — try_ src:site slot handler (FW-4).
	 *                    When present the registry site arm dispatches here instead of $cf(0,…);
	 *                    templates whose try_core_fn is site-blind (post cores) set a thin closure
	 *                    over bws_site_resolve_value('<tag>',…). Absent → $cf(0,…) fallback keeps
	 *                    seam-routed templates (email/phone) byte-identical.
	 *   supports_try     bool      Whether this template generates a try_ tag.
	 *   leading_options       array    Global formatting options (as, size, the datetime format
	 *                    cluster). Named for the term_ constructor, where they LEAD; the try_
	 *                    constructor registers them in canonical control order, i.e. after the
	 *                    attempts and their field reads. Both read the same key.
	 *   try_per_slot_key      bool     Each try_ slot reads its own field key.
	 *   try_per_slot_use      bool     Each try_ slot gets its own read (`use`) selector.
	 *                    The PAIR names the slot's READ SHAPE, which is what the folded
	 *                    slot control renders and what an absent read means: both →
	 *                    enum + key picker; key only → picker alone (an empty picker is
	 *                    the inherit); neither → no per-slot read at all, the tag-level
	 *                    `use`/`key` govern every slot.
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
	 * Link wrap: templates with supports_link_wrap=true get linkTo/linkKey/newTab appended
	 * after trailing field/fallback options. Entity type for URL resolution is determined by
	 * dispatch path: term for base-source, post for src:ref traversal, term for srcTermIn step.
	 * Templates without supports_link_wrap (content, permalink, image) never receive link options.
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

		// Filter `site` out of the rooting-modifier source list — a rooting modifier
		// (term_*, view_*) surfaces ENTITY-DISTINCT data; an entity-blind site read
		// there just duplicates the unrooted base tag (fails the I4 gate both arms).
		// Unconditional; no template re-allows it. Helper sits beside the option
		// builder in base-tags.php (testable + parallel to the slot-side filter). [#37]
		if ( function_exists( 'bws_filter_site_from_src' ) ) {
			$source_opt = bws_filter_site_from_src( $source_opt );
		}
		$traversal_opts = function_exists( 'bws_base_traversal_options' )
			? bws_base_traversal_options()
			: array();

		$link_options = function_exists( 'bws_get_link_options' ) ? bws_get_link_options() : array();

		// Detect term-context base source. Term entities are themselves terms — `srcTermIn`
		// (term-step on the resolved post) only makes sense after a post traversal (src=ref),
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

			$term_fn          = $tpl['term_fn'];
			$post_fn          = $tpl['post_fn'];
			$is_image         = ! empty( $tpl['is_image'] );
			$supports_link    = ! $is_image && ! empty( $tpl['supports_link_wrap'] ) && ! empty( $link_options );

			// Inject source + traversal after leading format controls. `fallback` is lifted
			// out of the template's options and re-appended LAST — it is global and closes
			// every panel (canonical control order, and what base tags register). It rode the
			// trailing part until 1.17.0, which put it ahead of the link cluster on exactly
			// the templates that have both (term_text, term_datetime_*); the try_ constructor
			// had the same bug from the same cause.
			$tpl_options  = $tpl['options'] ?? [];
			$leading_keys = array_keys( $tpl['leading_options'] ?? [] );

			$fallback_part = array_intersect_key( $tpl_options, [ 'fallback' => null, 'fallback_text' => null ] );
			$tpl_options   = array_diff_key( $tpl_options, $fallback_part );

			if ( $is_image && isset( $tpl_options['as'] ) ) {
				$as_opt = [ 'as' => $tpl_options['as'] ];
				unset( $tpl_options['as'] );
				$options = array_merge( $as_opt, $source_opt, $tag_traversal_opts, $tpl_options );
			} elseif ( $supports_link && ! empty( $leading_keys ) ) {
				// Split tpl_options into leading and trailing; link options appended after trailing.
				$leading_part  = array_intersect_key( $tpl_options, array_flip( $leading_keys ) );
				$trailing_part = array_diff_key( $tpl_options, array_flip( $leading_keys ) );
				$options = array_merge( $leading_part, $source_opt, $tag_traversal_opts, $trailing_part, $link_options );
			} elseif ( $supports_link ) {
				// No leading options — source/traversal then field options then link options.
				$options = array_merge( $source_opt, $tag_traversal_opts, $tpl_options, $link_options );
			} else {
				$options = array_merge( $source_opt, $tag_traversal_opts, $tpl_options );
			}

			$options = array_merge( $options, $fallback_part );

			// Per-tag supports (do not mutate the shared $base_supports across templates).
			// Image tags no longer declare native 'image-size' (as+size fold, FW-52):
			// size folds into the `as` value (bws-as-size composite), so GB renders no
			// native size control. No image-family tag carries extra supports now.
			$tag_supports = $base_supports;

			$callback = self::make_modifier_callback( $base_src_key, $traversal_src_key, $term_fn, $post_fn, $tag_name, $is_image, $supports_link );

			// Title: plain label when in its own gb_type group (modifier tags appear under their
			// own group in GB's picker, identified by gb_type). No cross-source parenthetical needed
			// because the type already distinguishes the group.
			$title = $modifier_label
				? ( $tpl['title'] ?? $tag_name ) . ' (' . $modifier_label . ')'
				: ( $tpl['title'] ?? $tag_name );

			// Thread the template's visibility gate to the modifier (term_*) tag — same
			// VE3/VP-vis gate the standalone email/phone tags carry. Empty otherwise.
			$visibility = $tpl['visibility'] ?? [];

			self::register_gb_tag( $title, $tag_name, $gb_type, $tag_supports, $options, $callback, $visibility );
		}
	}

	/**
	 * Build a modifier tag callback that dispatches to term_fn (via unset) or post_fn (via:'ref').
	 *
	 * Under the traversal pipeline (SPEC §T7/§V5) the modifier resolves its BASE
	 * source via base_source_key (term_ → TaxonomyTerm term-kind, view_ →
	 * PortalSource post-kind), then steps `src:ref` through the generic `ref` step —
	 * the per-combination traversal source class (TermRelatedPost / PortalRelatedPost)
	 * is no longer invoked. `$traversal_src_key` is ACCEPTED-BUT-IGNORED: kept in the
	 * signature so register_modifier() (and external callers like bws-portal-system)
	 * pass it without change, but never read — the ref step does the traversal
	 * generically. Portal renders identically with zero portal changes (SPEC §V5).
	 *
	 * @since 1.6.0
	 * @since 1.14.0 Pipeline-assembled; traversal_source_key accept-but-ignore (§V5).
	 */
	private static function make_modifier_callback(
		string $base_src_key,
		string $traversal_src_key,
		callable $term_fn,
		callable $post_fn,
		string $tag_name = '',
		bool $is_image = false,
		bool $supports_link = false
	): callable {
		unset( $traversal_src_key ); // Accept-but-ignore (SPEC §V5); ref step replaces it.
		return static function ( $opts, $block, $inst ) use ( $base_src_key, $term_fn, $post_fn, $tag_name, $is_image, $supports_link ) {
			$is_preview = $tag_name && ! empty( $inst->context['bwsEditorPreview'] );

			$source = $opts['src'] ?? $opts['source'] ?? 'current';
			if ( '' === $source ) {
				$source = 'current';
			}

			// `site` is filtered from the rooting-modifier src dropdown (see register_modifier
			// + CONTEXT.md I4). The UI filter can't stop a hand-typed `src:site`; guard it
			// here so it resolves EMPTY rather than silently reading term meta under the
			// option key (the #37 wrong-read). A site read belongs on the base tag. [#37]
			if ( 'site' === $source ) {
				return $is_preview && function_exists( 'bws_build_preview_label' )
					? bws_build_preview_label( $opts, $tag_name )
					: '';
			}

			$link_to  = $supports_link ? ( $opts['linkTo'] ?? 'none' ) : 'none';
			$link_key = $supports_link ? ( $opts['linkKey'] ?? '' ) : '';
			$new_tab  = $supports_link && ! empty( $opts['newTab'] );

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
			// bws_base_image_callback's term-step loop. For term-context base sources, the
			// option is hidden when src=current (UI gating), so this only runs when src=ref.
			// Returns [ 'value' => string, 'term_id' => int ] so caller can apply link wrap.
			$srcterm_dispatch = static function ( $post_id, $opts, $inst, $tax ) use ( $term_fn ) {
				if ( ! $post_id || '' === $tax ) {
					return [ 'value' => '', 'term_id' => 0 ];
				}
				if ( ! function_exists( 'bws_get_srcterm_terms' ) ) {
					return [ 'value' => '', 'term_id' => 0 ];
				}
				$terms = bws_get_srcterm_terms( (int) $post_id, $tax );
				foreach ( $terms as $term ) {
					$result = $term_fn( $term->term_id, $opts, $inst );
					if ( '' !== $result && false !== $result ) {
						return [ 'value' => $result, 'term_id' => (int) $term->term_id ];
					}
				}
				return [ 'value' => '', 'term_id' => 0 ];
			};

			$link_entity_id   = 0;
			$link_entity_type = 'post';

			// L1 — resolve the modifier's BASE resolved source via base_src_key (SPEC
			// §V5): term_ → TaxonomyTerm (term kind), view_ → PortalSource (post kind).
			// The pipeline engine then steps it; traversal_src_key is accepted-but-
			// IGNORED (SPEC §V5 — portal still passes it, we never read it). The old
			// per-combination traversal source class (TermRelatedPost / PortalRelatedPost)
			// is replaced by the generic `ref` step off this base source.
			$base_src   = SourceRegistry::get_source( $base_src_key );
			$base_kind  = ( $base_src && 'term' === $base_src->get_context_type() ) ? 'term' : 'post';
			$base_id    = $base_src ? (int) $base_src->resolve_id( $opts, $inst ) : 0;
			$base_source = $base_id ? array( 'kind' => $base_kind, 'id' => $base_id ) : array();

			if ( 'ref' === $source ) {
				// Traversal: step the base source's relationship field → post[] via the
				// generic ref step (SPEC §V5/§V6). Modifier link semantics are single-
				// valued, so collapse to the first post id after the step.
				$ref_field = $opts['ref'] ?? '';
				$entity_id = 0;
				if ( $base_source && '' !== $ref_field && function_exists( 'bws_run_traversal' ) ) {
					$stepped   = bws_run_traversal(
						array( $base_source ),
						array( array( 'type' => 'refs', 'field' => $ref_field ) )
					);
					$entity_id = function_exists( 'bws_first_post_id_from_sources' )
						? (int) bws_first_post_id_from_sources( $stepped )
						: 0;
				}

				if ( '' !== $srcterm_tax ) {
					$dispatch         = $srcterm_dispatch( $entity_id, $opts, $inst, $srcterm_tax );
					$value            = $dispatch['value'];
					$link_entity_id   = $dispatch['term_id'];
					$link_entity_type = 'term';
				} elseif ( $is_image ) {
					$value = $image_post_dispatch( $entity_id, $opts, $inst );
				} else {
					$value            = $post_fn( $entity_id, $opts, $inst );
					$link_entity_id   = (int) $entity_id;
					$link_entity_type = 'post';
				}
			} else {
				// Source unset — read the base resolved source directly, dispatching by
				// its KIND (term → term_fn, post → post_fn/image). Mirrors the base-tag
				// kind dispatch (SPEC §V7 posture). term_ modifier bases a term; view_
				// modifier bases a post.
				$entity_id = $base_id;

				// srcTermIn at src=current is only meaningful for post-context base sources.
				// Term-context bases hide the control via show_if=src:ref (UI gating).
				if ( '' !== $srcterm_tax && 'term' !== $base_kind ) {
					$dispatch         = $srcterm_dispatch( $entity_id, $opts, $inst, $srcterm_tax );
					$value            = $dispatch['value'];
					$link_entity_id   = $dispatch['term_id'];
					$link_entity_type = 'term';
				} elseif ( 'term' === $base_kind ) {
					$value            = $term_fn( $entity_id, $opts, $inst );
					$link_entity_id   = (int) $entity_id;
					$link_entity_type = 'term';
				} elseif ( $is_image ) {
					$value = $image_post_dispatch( $entity_id, $opts, $inst );
				} else {
					$value            = $post_fn( $entity_id, $opts, $inst );
					$link_entity_id   = (int) $entity_id;
					$link_entity_type = 'post';
				}
			}

			if ( '' !== $value ) {
				if ( $supports_link && $link_entity_id && function_exists( 'bws_wrap_with_link' ) ) {
					$value = bws_wrap_with_link( $value, $link_to, $link_key, $new_tab, $link_entity_id, $link_entity_type );
				}
				return $value;
			}

			return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $opts, $tag_name ) : '';
		};
	}

	/**
	 * Which read axes a try_ template owns PER SLOT, and which legacy source-group keys it
	 * does NOT own per slot at any position.
	 *
	 * The `try_per_slot_use`/`try_per_slot_key` pair names the slot's READ SHAPE, and the
	 * shape decides where `use`/`key` live. This method is that mapping, and it has TWO
	 * consumers that must agree: generate_base_try_tags() strips `slot_read` from the
	 * trailing tag-level options, and the FW-56/57 fold migrator refuses to fold a
	 * `tag_level` key into a slot value (bws_fold_migrate_slots). Both consumers call this
	 * so the split exists once.
	 *
	 * `tag_level` is an EXCLUSION AT EVERY POSITION, not just slot 1, and both directions
	 * are output changes:
	 *   - Slot 1's axis IS the bare key, so folding a tag-level `key` into `1:` relocates a
	 *     working option into a slot value nothing reads. `{{try_datetime_single
	 *     key:event_date}}` is that shape — its read is tag-level, and the four chain-only
	 *     templates (title/permalink/datetime_*) never registered a per-slot `use`/`key`.
	 *   - A prefixed `N-use`/`N-key` on such a template is DEAD wire (the resolver's psk/psu
	 *     gate ignores it), so folding it would make it live.
	 *
	 * `src`/`ref`/`srcTermIn` are always slot-level. `limit` NEVER is: try_ has never
	 * registered `N-limit`, and the resolver reads a bare `limit` as every slot's default
	 * limit (`$slot_max`), list template or not — so folding it into slot 1 would take that
	 * bound away from slots 2+. It is TAG-level on every template for as long as it exists
	 * in wire, which is what this list states. The CONTROL is gone (#62, 1.17.0) and the key
	 * is retired by migration (#61) — neither changes the axis split: an unmigrated or
	 * hand-written `limit` still arrives here and must still be kept out of a slot value.
	 *
	 * @since 1.17.0
	 * @param array $tpl Modifier template descriptor.
	 * @return array{slot_read:string[],tag_level:string[]}
	 */
	public static function try_slot_axes( array $tpl ): array {
		$per_slot_use = ! empty( $tpl['try_per_slot_use'] );
		$per_slot_key = ! empty( $tpl['try_per_slot_key'] );

		$slot_read = [];
		if ( $per_slot_use ) {
			$slot_read[] = 'use';
		}
		if ( $per_slot_use || $per_slot_key ) {
			$slot_read[] = 'key';
		}

		$tag_level   = array_values( array_diff( [ 'use', 'key' ], $slot_read ) );
		$tag_level[] = 'limit';

		return [
			'slot_read' => $slot_read,
			'tag_level' => $tag_level,
		];
	}

	/**
	 * Generate try_ fallback-chain tags from modifier templates (base-tag system).
	 *
	 * One try_ tag per eligible modifier template (supports_try = true).
	 * Each tag accepts up to five source slots; each slot specifies a source
	 * traversal and returns the first non-empty result across all slots.
	 * Tags are registered with GB type 'first-available'.
	 *
	 * FOLDED SLOT WIRE (FW-56/57). Each slot is ONE option key — `A`, `B`, … — of type
	 * `bws-slot-fold`, whose value carries that slot's whole configuration: a source
	 * chain (`src(refs,office;terms,category)`) plus a field read (`key(x)` / `use(title)`
	 * / `use(same)`) plus per-slot options. Grammar and vocabulary:
	 * includes/helpers/slot-fold.php (the PHP owner) and bws_build_fold_slot_options().
	 *
	 * CONTROL order is REGISTRATION order (GB renders `options` as declared, and nothing
	 * reorders it — the FW-52 normalizer moves the SERIALIZED key order only). So this
	 * assembly is the panel, and since 1.17.0 it follows the same canonical control order
	 * every base tag registers in — `source → format → link → fallback`:
	 *   1. the folded slot keys `A`..`E`   — the attempt chain, i.e. this tag's source
	 *   2. chain-level `sep`               — list length is a source property (FW-52).
	 *                                        `limit` stood beside it until #62 retired the
	 *                                        tag-level control from every chain-authoring tag
	 *   3. tag-level field reads           — tpl['options'] minus format, per-slot and fallback
	 *   4. format options                  — as/size, the datetime format cluster
	 *   5. link options                    — linkTo/linkKey/newTab
	 *   6. fallback                        — last, as on every base tag
	 *
	 * It did NOT until then: format led (the SERIALIZATION order, on the one tag family
	 * that renders its format cluster), fallback preceded link, and `limit`/`sep` were
	 * appended dead last. Harmless while every control was a bare sibling; visible the
	 * moment 1.17.0 started boxing options by group, because a group draws as one box only
	 * where its members register CONTIGUOUSLY (assets/js/option-group.js). Registering out
	 * of canonical order does not just look wrong — it splits a group into two boxes, or
	 * strands one member in a box of its own with nothing to name it.
	 *
	 * RESOLUTION runs through the shared render seam, so the inherit rules live in one
	 * place for every container (bws_fold_slot_chain_options):
	 *   - An axis left unset INHERITS from the previous resolving slot. Slot 1 seeds the
	 *     accumulator: ambient source, and the template's stripped first `use` value.
	 *   - A slot that does NOT resolve never feeds the accumulator, so a half-configured
	 *     slot cannot re-point a later slot's inherit.
	 *   - A `same` source inherits the prior attempt's WHOLE CHAIN, hops and all: the
	 *     source IS a chain, so what it is travels with it (#104).
	 *   - Wire era is decided PER SLOT — a folded value parses, an absent one is
	 *     recovered from that slot's legacy keys — so a half-migrated tag resolves.
	 *
	 * Slot 1 is never absent here: every axis unset IS the default attempt (the shape a
	 * bare `{{try_title}}` renders). Slots ≥2 are absent when they hold nothing.
	 *
	 * Link wrap: templates with supports_link_wrap=true get linkTo/linkKey/newTab appended
	 * after trailing options. The single linkTo/linkKey applies to the winning slot's entity —
	 * post or term depending on which slot dispatched. content/permalink/image are excluded
	 * (no supports_link_wrap flag) so try_content, try_permalink, try_image never get link options.
	 *
	 * @since 1.6.0
	 */
	public static function generate_base_try_tags(): void {
		if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
			return;
		}

		// Snapshot existing tags for dup-check.
		$existing = array_keys( \GenerateBlocks_Register_Dynamic_Tag::get_tags() ?? [] );

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
			$try_site_fn     = $tpl['try_site_fn'] ?? null;
			$per_slot_key    = ! empty( $tpl['try_per_slot_key'] );
			$per_slot_use    = ! empty( $tpl['try_per_slot_use'] );
			$no_key_uses     = $tpl['try_use_no_key_values'] ?? [];
			$list_options    = ! empty( $tpl['try_list_options'] );
			$allow_site_slot = ! empty( $tpl['try_allow_site_slot'] );
			$tpl_options     = $tpl['options'] ?? [];
			// The descriptor key still reads `leading_options` — it is the term_ constructor's
			// too, where these DO lead. On try_ they no longer do (canonical control order,
			// 1.17.0), so the local name states what they are rather than where they sit.
			$format_options  = $tpl['leading_options'] ?? [];
			$supports_link   = ! empty( $tpl['supports_link_wrap'] ) && ! empty( $tpl['is_image'] ) === false;

			if ( ! $try_core_fn ) {
				continue;
			}

			$link_opts_try = ( $supports_link && function_exists( 'bws_get_link_options' ) )
				? bws_get_link_options()
				: [];
			$options = [];

			// 1 — FOLDED slot keys `A`..`E` (FW-56/57). One `bws-slot-fold` option
			// per slot, whose VALUE carries that slot's whole configuration. Replaces the
			// six flat keys per slot (src/ref/srcTermIn/use/key ×5 plus their show_if_any
			// reveal cascade) this loop used to register. Every enum, label and noun the
			// control renders is DERIVED inside bws_build_fold_slot_options from the same
			// builders the flat registration read (the source twin, the read twin, the
			// template's own key definition) — nothing is re-typed for the fold.
			//
			// The three READ shapes are the template flags, unchanged in meaning:
			//   per_slot_use  — a `use` enum plus a key picker (text/content/image).
			//   per_slot_key  — a key picker alone (email/phone: no `use` axis exists).
			//   neither       — no per-slot read at all (title/permalink/datetime_*),
			//                   whose `use`/`key`, where they exist, stay TAG-level
			//                   options in the trailing group below.
			// `base_read`/`base_key` are handed over empty for the shapes that lack the
			// axis, and the control renders whichever shape the derived config describes.
			//
			// `steps` is a CAPABILITY list, and since #104 it is the BASE TAG'S. An
			// attempt's source is a base tag's source ([I16]): the seam hands the whole
			// chain on as depth-0 chain wire and the arms dispatch on what it resolves to
			// (#103), so neither half truncates it any more. It was `['terms']` while the
			// flatten stood — the triple had no spelling for a second relationship step,
			// so a wider offer would have authored wire that skipped.
			//
			// `entries` is still absent, for the same reason it is absent from the base
			// offer: no `try_` arm assembles a repeater row (try-slot-arms.php refuses the
			// `meta_row` kind), so offering it would author a chain that renders nothing.
			//
			// The append-by-key below is a habit worth keeping, but no longer a trap:
			// while slot keys were all-digit, PHP stored them as INTEGERS and
			// array_merge RENUMBERED them (`1`..`5` → `0`..`4`, registering a slot 0 the
			// grammar has no ordinal for and dropping slot 5). Capitals are ordinary
			// string keys, so that failure mode retired with the digits.
			$fold_slots = function_exists( 'bws_build_fold_slot_options' )
				? bws_build_fold_slot_options(
					[
						'container'       => 'try',
						'combining'       => bws_fold_is_combining( 'try' ),
						'per_slot_use'    => $per_slot_use,
						'min'             => 2,
						'max'             => 5,
						'base_read'       => $per_slot_use ? ( $tpl_options['use'] ?? [] ) : [],
						'base_key'        => ( $per_slot_use || $per_slot_key ) ? ( $tpl_options['key'] ?? [] ) : [],
						'allow_site'      => $allow_site_slot,
						'allow_same_read' => true,
						'steps'            => [ 'refs', 'terms' ],
						// The axes that are TAG-level on this template, so the editor's
						// mount migrator and the control leave them alone at every slot
						// position — same split, same owner, as the trailing-option strip
						// below and the converter migrator.
						'tag_level'       => self::try_slot_axes( $tpl )['tag_level'],
						// "attempt" nouns ONE RUNG of the fallback chain, which is what a
						// try_ slot is (src-chain-encoding.md §5.1a, user 2026-08-01).
						// One noun, both surfaces: "+ Add attempt" and the header
						// "Attempt A" — the header is derived, never registered beside it.
						// The editor tag configuration PREVIEW names a slot by LETTER alone
						// (#105) — the bracket prefix `⚠ Try:` already names the container,
						// so this noun would be its second mention there.
						'noun'            => __( 'attempt', 'generateblocks' ),
					]
				)
				: [];
			foreach ( $fold_slots as $slot_key => $slot_def ) {
				$options[ $slot_key ] = $slot_def;
			}

			// 2 — List-mode chain option (try_list_options templates: text, title, email,
			// phone). A winning slot in list mode (any slot with a srcTermIn term-step, or
			// src:ref once the Phase-5 plural resolver lands) joins its finished items via
			// the seam (bws_try_join_items). `sep` is CHAIN-level (one for the whole try_,
			// not per-slot) — the seam reads it off $opts. [SPEC §32 V4,V5 / I6 parity]
			//
			// NO TAG-LEVEL `limit` since 1.17.0 (#62). A LIMIT IS STATED WHERE THE SOURCE
			// IS STATED, and a try_ attempt authors its source as a CHAIN, so the limit
			// belongs to the fanning STEP inside the slot value. The tag-level key was also
			// the one limit an author could set for FIVE different sources at once, which
			// #61 retired into the slots that consumed it — the value is still read here
			// ($slot_max, below) so unmigrated and hand-edited wire keeps rendering.
			//
			// `sep` is registered HERE, right behind the attempts, because it is a
			// SOURCE-group option (list length is a property of the source, FW-52) and a
			// group boxes only where its members are contiguous. Appended last — which is
			// where the pair sat until 1.17.0 — it drew its own captionless box at the foot
			// of the panel, below link and fallback, describing a source nowhere near it.
			// Alone in the group now, it renders BARE (option-group.js's lone-non-lead
			// opt-out), which is right: a try_ tag's source is its attempts, and those draw
			// their own boxes inside each slot value.
			//
			// UNCONDITIONAL under the fold. Its reveal predicate used to be a show_if_any
			// over every slot's `N-srcTermIn`/`N-src` — keys the fold removed. A list axis
			// now lives INSIDE a slot value (`src(terms[category])`), and show_if compares
			// whole option values, so no honest predicate exists: a `not_empty` on slot `A`
			// fires for every configured slot, list axis or not. A control always visible
			// beats a condition that lies about when it matters — same call as join's
			// reveal rows (5d).
			if ( $list_options ) {
				$options['sep'] = [
					'type'        => 'text',
					'label'       => __( 'Result Separator', 'generateblocks' ),
					'help'        => __( 'Text to place between results. Default: ", ".', 'generateblocks' ),
					'placeholder' => ', ',
				];
			}

			// 3 — Template-level options that are neither format nor per-slot: the TAG-level
			// field reads (`use`/`key` on templates with no per-slot axis, the datetime key
			// family) plus `fallback`, which is split back out and re-appended LAST below.
			$trailing_opts = $tpl_options;
			foreach ( array_keys( $format_options ) as $format_key ) {
				unset( $trailing_opts[ $format_key ] );
			}
			// Whichever read axes this template owns PER SLOT are not tag-level options.
			// self::try_slot_axes() is the single owner of that split; the fold migrator
			// reads its complement so a tag-level `use`/`key` is never folded into slot 1.
			foreach ( self::try_slot_axes( $tpl )['slot_read'] as $slot_axis ) {
				unset( $trailing_opts[ $slot_axis ] );
			}
			$fallback_opts = array_intersect_key( $trailing_opts, [ 'fallback' => null, 'fallback_text' => null ] );
			$trailing_opts = array_diff_key( $trailing_opts, $fallback_opts );

			// 4 → 6 — format, then link, then fallback. Each group contiguous, and the
			// sequence is the canonical control order (see the docblock).
			foreach ( [ $trailing_opts, $format_options, $link_opts_try, $fallback_opts ] as $group ) {
				foreach ( $group as $opt_key => $opt_def ) {
					$options[ $opt_key ] = $opt_def;   // see the array_merge warning above.
				}
			}

			// --- Build callback ---
			$cf   = $try_core_fn;
			$tcf  = $try_term_fn;
			$sf   = $try_site_fn;
			$psk  = $per_slot_key;
			$psu  = $per_slot_use;
			$nku  = $no_key_uses;
			$slnk = $supports_link;
			// Media-block runtime backstop — templates whose output is a link-wrapping
			// contact tag (email/phone: mailto:/tel: <a>) must NOT render inside a GB media
			// block, whose empty tagName slips the native visibility gate (link-helpers.php).
			// Their default-on anchor would corrupt the <img src>. Mirrors the base
			// {{email}}/{{phone}} VE-vis/VP-vis backstop. [SPEC §32 V11]
			$media_guard = ! empty( $tpl['try_media_block_guard'] );
			// Slot 1 default 'use' token = first option value in template's use definition.
			$default_use = $tpl_options['use']['options'][0]['value'] ?? '';

			$tpl_key = $tpl['key'];

			$callback = static function ( $opts, $b, $inst ) use ( $cf, $tcf, $sf, $psk, $psu, $nku, $slnk, $media_guard, $default_use, $tpl_key ) {
				if ( $media_guard && function_exists( 'bws_tag_blocked_on_media_block' ) && bws_tag_blocked_on_media_block( $b ) ) {
					return '';
				}

				$is_preview = ! empty( $inst->context['bwsEditorPreview'] );

				$fallback  = sanitize_text_field( $opts['fallback'] ?? $opts['fallback_text'] ?? '' );
				$eval_opts = array_diff_key( $opts, [ 'fallback' => null, 'fallback_text' => null ] );

				$link_to  = $slnk ? ( $opts['linkTo'] ?? 'none' ) : 'none';
				$link_key = $slnk ? ( $opts['linkKey'] ?? '' ) : '';
				$new_tab  = $slnk && ! empty( $opts['newTab'] );

				// ONE carry-forward accumulator for the whole chain, threaded through the
				// fold seam (bws_fold_slot_chain_options), which owns the inherit rules for
				// every container. Seeded with what slot 1's ABSENT axes mean here: an EMPTY
				// CHAIN is the ambient entity, and the read seeds the template's stripped
				// first `use` value, so an unset slot-1 read inherits the same token the flat
				// resolver derived at slot 1 — and so does a later `use(same)` that reaches
				// back past a slot which never set one.
				//
				// The source axis is a CHAIN and not a token (#104): `src(same)` inherits the
				// prior attempt's whole chain, hops included, which is what deleted the
				// inherited-taxonomy special case the flat triple needed.
				$carry = bws_fold_empty_carry( $psu ? $default_use : '' );

				foreach ( range( 1, 5 ) as $n ) {
					// Era per SLOT, not per tag: a folded value parses, an absent one is
					// recovered from this slot's legacy keys, and both feed one accumulator
					// (so a half-migrated tag resolves as its author last saw it).
					$slot = function_exists( 'bws_fold_slot_struct' )
						? bws_fold_slot_struct( $n, (array) $opts, 'try', $psu )
						: null;
					if ( null === $slot ) {
						continue;   // nothing in either era, or the shipped resolver's own skip.
					}
					$skip_reason   = '';
					$limit_default = 1;
					$slot_read     = bws_fold_slot_chain_options( $slot, $carry, false, $skip_reason, $limit_default );
					if ( null === $slot_read ) {
						continue;   // unconfigured, nothing to inherit, or an unfinished step.
					}

					$last_key = $slot_read['key'];
					$last_use = $slot_read['use'];

					// Build slot-specific options (merged into core fn call). The SOURCE arrives
					// as depth-0 CHAIN WIRE in `src` — the key and the language a base tag states
					// its source in (CONTEXT.md I16) — and the seam supersedes the legacy axes by
					// returning explicit empties for them. Merging that over $eval_opts is what
					// closes the tag-level leak: $eval_opts still carries any bare legacy
					// `srcTermIn` off a half-migrated tag, and bws_fold_chain_from_options()
					// APPENDS a term step for whatever it finds there — which would now grow a
					// step on every slot's own chain rather than merely leaking one taxonomy.
					$slot_opts              = $eval_opts;
					$slot_opts['src']       = $slot_read['src'];
					$slot_opts['ref']       = $slot_read['ref'];
					$slot_opts['srcTermIn'] = $slot_read['srcTermIn'];

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

					// List-join seam (CONTEXT.md I6 / SPEC §32): a slot's dispatch returns
					// finished string(s). Collect them into $items, slice to `limit`, then
					// the winning slot (first non-empty) joins via bws_try_join_items.
					// Link-wrap applies to a SINGLE-result item only — count is taken AFTER
					// the limit slice (mirrors the base text core, base-tags.php:888-901:
					// slice-then-count, so a limit:1 chain over many non-empty terms still
					// wraps the lone shown item). sep/limit read off the chain options;
					// default limit 1 keeps existing try_ output byte-identical.
					// `limit` is interpreted in ONE place (bws_clamp_limit, field-helpers) —
					// this site read it via `?: 1` while the seam used `?? 1`, which agree
					// today and diverge the moment 0 stops meaning 1. Unguarded: field-helpers
					// is required at plugin init, this dispatch runs at render.
					//
					// A folded slot may PIN its own limit (`src(terms[category] limit[3])`
					// or a slot-level `limit(3)`), which then governs this slot only and is
					// threaded into the core call too, so the seam's slice and the core's
					// own read agree.
					//
					// THE TAG-LEVEL `limit` IS RETIRED (#61) AND STILL READ. Migration
					// pushes an author's number into the slots that consumed it and deletes
					// the key, so on migrated wire this fallback resolves to nothing. It
					// stays because the value outlives the key: neither migration path
					// reaches a tag stored in ACF meta, and ADR 0004 makes hand-edited wire
					// mean what it says. #62 retires the CONTROL, never this read.
					$sep = $opts['sep'] ?? null;
					// THE DEFAULT IS THE SLOT'S OWN, and only the seam can say what it is:
					// $slot_opts['src'] is CHAIN WIRE on every slot now, including one recovered
					// from legacy flat keys, so bws_limit_default() read off it answers UNLIMITED
					// whatever the slot was spelled as — the #60 defect with its sign flipped.
					// The seam reports the era, because only the seam still sees it.
					//
					// The resolved value is written BACK into $slot_opts, not left implicit:
					// the core call below resolves its own limit through the same flat-blind
					// bws_limit_default(), so an absent key there would re-introduce the 1 this
					// line just decided against. An explicit number is spelling-independent.
					$slot_max           = bws_clamp_limit( $slot_read['limit'] ?? $opts['limit'] ?? null, $limit_default );
					$slot_opts['limit'] = (string) $slot_max;

					// ── ARM DISPATCH (FW-71, retires the FW-5 fork) ────────────────
					// FOUR hand-written arms stood here, each testing the flat source
					// token directly (`'' !== $stm_raw`, `'site' === $last_src`,
					// `'current' === $last_src`, else post). One question now: what does
					// this slot's source RESOLVE TO? The answer indexes the shared arm
					// table (includes/helpers/try-slot-arms.php), which is what makes a
					// chain-spelled slot and a flat-spelled one take the SAME arm — the
					// identity CONTEXT.md I16 states and the base tags already have
					// (FW-63). The table is a pure seam precisely because every
					// byte-identity risk in the collapse lives here.
					$kind = bws_base_src_resolution( $slot_opts )['kind'];
					$arm  = bws_try_slot_arm( $kind );
					if ( null === $arm || '' === $arm['fn'] ) {
						// No `try_` arm consumes this kind — an unknown step slug (the
						// engine answers empty for it) or a repeater row, which is
						// {{table}}'s assembly and not a fallback attempt's. SKIP, never
						// guess: the nearest consumable arm would read the ambient entity
						// and hand back a plausible WRONG value instead of an empty one.
						continue;
					}

					// A ROOT-ONLY chain resolves to whatever the factory finds at render —
					// post on a singular page, term on a term archive, user on an author
					// archive, meta_row in a flat repeater row. Resolve it ONCE (SPEC §V1),
					// then branch. This is where the old term-ambient arm went.
					$base = null;
					if ( 'branch' === $arm['fn'] ) {
						$base = bws_base_resolve_source_for_callback( $slot_opts, $inst );
						$kind = bws_try_slot_base_branch_kind( (string) ( $base['kind'] ?? '' ) );
						$arm  = null === $kind ? null : bws_try_slot_arm( $kind );

						// RE-CHECK, and the check above does not cover it: the first one ran
						// against the CHAIN's kind, this one against what the factory actually
						// resolved, which is a second question with a second refusal (a source
						// the wire names but this render cannot use — GH #75 / #76). Skipping
						// here is what makes the attempt chain move on to the next attempt
						// instead of reading the ambient entity, which is the point rather
						// than a side effect: an ambient read that SUCCEEDS stops the chain,
						// so the later attempts never ran. Without it the branch also
						// dereferences a null arm two lines down — today unreachable only
						// because the branch never refused.
						if ( null === $arm || '' === $arm['fn'] ) {
							continue;
						}
					}

					// The template's renderer for this arm, normalized to fn($id,$opts,$inst).
					// The site arm's TWO legs are unchanged (FW-4): try_site_fn where the
					// template has one, else try_core_fn( 0, … ) — whose own resolve reads
					// the option and self-wraps (email/phone mailto:/tel:), so that leg
					// takes no link identity.
					$render_fn = null;
					$link_kind = $arm['link'];
					switch ( $arm['fn'] ) {
						case 'term':
							$render_fn = $tcf;
							break;
						case 'site':
							$render_fn = $sf
								? static fn( $id, $o, $i ) => $sf( $o, $i )
								: static fn( $id, $o, $i ) => $cf( 0, $o, $i );
							if ( ! $sf ) {
								$link_kind = '';
							}
							break;
						case 'core':
							$render_fn = $cf;
							break;
					}
					if ( null === $render_fn ) {
						// This TEMPLATE has no function for the arm — a family with no
						// try_term_fn, and the `user` leg on every family until #108 wires
						// it. Falling through to the post arm is not a fallback invented
						// here: it is exactly what the token arms did, since both the
						// term-ambient arm and the srcTermIn arm were gated on `$tcf`.
						// Absence of a CONSUMER is the table's answer above (skip); absence
						// of this template's IMPLEMENTATION is this one.
						$kind      = 'post';
						$arm       = bws_try_slot_arm( 'post' );
						$link_kind = $arm['link'];
						$render_fn = $cf;
					}

					// The entity ids this arm reads off the slot's source. Both plural
					// reads run the WHOLE compiled chain rather than a leading run of ref
					// steps, so a term step behind a relationship step is no longer
					// silently dropped (the §F9.3 hole, closed on base tags by FW-63).
					if ( null === $base && 'none' !== $arm['ids'] ) {
						$base = bws_base_resolve_source_for_callback( $slot_opts, $inst );
					}
					switch ( $arm['ids'] ) {
						case 'term':
							$ids = bws_base_term_ids_from_source( $base, $slot_opts );
							break;
						case 'none':
							$ids = [ 0 ];   // the site store carries a namespace, not an id (ADR 0002).
							break;
						default:
							$ids = bws_base_post_ids_from_source( $base, $slot_opts );
					}

					// Mode 2b — the flat repeater row. It has NO kind: the factory resolves
					// a meta_row, no post id comes out of it, and the core fn still reads
					// the value off $loop_item[$key]. Survives as a post-arm special case,
					// gated exactly as before.
					if ( ! $ids && 'post' === $arm['ids'] ) {
						$in_loop_row = function_exists( 'bws_get_loop_row_context' )
							&& bws_get_loop_row_context( $inst )['in_loop'];
						// THE GATE IS "THIS SLOT STATES NO SOURCE OF ITS OWN", and it used to be
						// spelled `'current' === $last_src` off the flat triple. That token is gone
						// (#104), and re-deriving it from the chain's root would be WRONG rather
						// than merely different: a chain leading with a step has no root token
						// either, so a `refs` slot that resolved nothing would take the loop row —
						// a plausible value from the wrong entity. Ask the resolution instead.
						$src_res    = bws_base_src_resolution( $slot_opts );
						$is_ambient = ! $src_res['fans'] && in_array( $src_res['root'], [ '', 'current' ], true );
						if ( $in_loop_row && $is_ambient && '' !== $last_key ) {
							$ids = [ false ];
						}
					}
					if ( ! $ids ) {
						continue;   // nothing to read — try the next attempt.
					}

					// ── ONE EMIT for every arm ─────────────────────────────────────
					// COLLECT-then-slice, not slice-then-collect: one entity may return
					// several finished items, so the bound is on ITEMS. That is the shipped
					// srcTermIn arm's shape (break early while hopping, then slice), kept
					// verbatim rather than routed through bws_collect_value_list(), whose
					// slice lands on the ITEM LIST and would move output wherever a single
					// entity yields more than one value.
					//
					// Link-wrap applies to a SINGLE-result item only, and the count is taken
					// AFTER the slice (mirrors the base text core: a limit:1 chain over many
					// non-empty entities still wraps the lone shown item).
					$items    = [];
					$first_id = 0;
					foreach ( $ids as $entity_id ) {
						$rendered = function_exists( 'bws_try_normalize_items' )
							? bws_try_normalize_items( $render_fn( $entity_id, $slot_opts, $inst ) )
							: array_filter( [ $render_fn( $entity_id, $slot_opts, $inst ) ], static fn( $v ) => '' !== $v && false !== $v );
						foreach ( $rendered as $it ) {
							$items[] = $it;
							if ( ! $first_id ) {
								$first_id = (int) $entity_id;
							}
						}
						if ( $slot_max && count( $items ) >= $slot_max ) {
							break; // Enough to satisfy the limit — stop stepping entities.
							// $slot_max 0 = UNLIMITED: never break early, step every one.
						}
					}
					if ( ! $items ) {
						continue;   // this attempt resolved and found nothing — try the next.
					}

					$shown  = array_slice( $items, 0, $slot_max ?: null );
					$joined = function_exists( 'bws_try_join_items' )
						? bws_try_join_items( $shown, $sep, $slot_max )
						: (string) reset( $shown );
					if ( $slnk && '' !== $link_kind && 1 === count( $shown ) && function_exists( 'bws_wrap_with_link' ) ) {
						// The site sentinel is an identity, not an entity (link-helpers.php
						// V-link: a site link-wrap is the permalink analog).
						$link_id = 'site' === $link_kind ? 1 : $first_id;
						if ( $link_id ) {
							$joined = bws_wrap_with_link( $joined, $link_to, $link_key, $new_tab, $link_id, $link_kind );
						}
					}
					return $joined;
				}

				// All slots exhausted — apply the fallback, then label if in preview.
				if ( '' !== $fallback ) {
					return \GenerateBlocks_Dynamic_Tag_Callbacks::output( $fallback, $opts, $inst );
				}

				return $is_preview && function_exists( 'bws_build_try_preview_label' )
					? bws_build_try_preview_label( $opts, $tpl_key )
					: '';
			};

			/* translators: %s: tag title e.g. "Text Fields" */
			$title = sprintf( __( 'Try %s', 'generateblocks' ), $tpl['title'] ?? $tag_name );

			// No native supports on try_ tags. Image size folds into the `as` value
			// (as+size fold, FW-52) — GB's native 'image-size' control is retired.
			$supports = [];

			// Thread the template's visibility gate to the try_ tag (VP-vis: try_email /
			// try_phone MUST keep the tagName NOT_IN [a,button,img,picture] gate their
			// standalone tags carry). Empty for gateless templates (text/content/image).
			$visibility = $tpl['visibility'] ?? [];

			self::register_gb_tag( $title, $tag_name, 'first-available', $supports, $options, $callback, $visibility );
		}
	}

	/**
	 * Register a single dynamic tag with GenerateBlocks.
	 *
	 * @param string   $title    Full tag title shown in the GB editor.
	 * @param string   $tag_name Tag name (e.g., 'post_custom_image').
	 * @param string   $gb_type  GB type string ('post', 'media', 'term', 'related', …).
	 * @param array    $supports   GB supports array.
	 * @param array    $options    Options array (passed to options_callback).
	 * @param callable $callback   Return callback: fn( $options, $block, $instance ): string.
	 * @param array    $visibility Optional GB `visibility` block-attribute gate
	 *                             (e.g. tagName NOT_IN ['a','button','img','picture']).
	 *                             Threaded through so template-registered tags (term_*, try_*)
	 *                             can carry the same gate the standalone tags register
	 *                             directly. Omitted from the registration array when empty
	 *                             (preserves byte-identical registration for gateless tags).
	 *                             [SPEC §32 V11 / VE3 / VP-vis]
	 */
	public static function register_gb_tag(
		string $title,
		string $tag_name,
		string $gb_type,
		array $supports,
		array $options,
		callable $callback,
		array $visibility = []
	): void {
		if ( function_exists( 'bws_prepare_registration_options' ) ) {
			$options = bws_prepare_registration_options( $options );
		}
		$args = [
			'title'    => $title,
			'tag'      => $tag_name,
			'type'     => $gb_type,
			'supports' => $supports,
			'options'  => $options,
			'return'   => $callback,
		];
		if ( ! empty( $visibility ) ) {
			$args['visibility'] = $visibility;
		}
		new \GenerateBlocks_Register_Dynamic_Tag( $args );
	}

}
