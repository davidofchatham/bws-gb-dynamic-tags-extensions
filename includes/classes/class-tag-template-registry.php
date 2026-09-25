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
	 * @var array[] Base template descriptors used by generate_base_try_tags() (try_ constructor)
	 *              and, through get_modifier_templates(), by the converter's per-template
	 *              migration-entry generator. The `modifier` in the name is historical: the
	 *              term_ constructor was the second consumer until register_modifier() was
	 *              withdrawn in 1.21.0.
	 *
	 * Each entry shape:
	 *   key              string    Template key (e.g. 'text', 'image').
	 *   title            string    Display title fragment (e.g. 'Text Fields').
	 *   options          array     Template-specific options excluding via/traversal sub-options.
	 *   term_fn          callable  fn($term_id, $opts, $inst): string — term-entity handler.
	 *   post_fn          callable  fn($post_id, $opts, $inst): string — post-entity handler (term_ via:'ref').
	 *   resolve_fn       callable  fn(array $opts, $inst): array{value:string, link_id:int,
	 *                    link_type:string} — the BASE tag's own resolve seam (FW-136). A try_
	 *                    attempt reads through it, so it reads exactly as the base tag does
	 *                    and inherits every rule the base read holds rather than
	 *                    re-implementing it. REQUIRED for a try_ tag: a template without one
	 *                    gets none. Named explicitly rather than derived from a naming
	 *                    convention plus function_exists(), because a rename would then be a
	 *                    silent behavior change.
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
	 *                    the carry-over); neither → no per-slot read at all, the tag-level
	 *                    `use`/`key` govern every slot.
	 *   try_use_no_key_values array    use values where key is not required (e.g. ['featured'] for image).
	 *   is_image              bool     Image template — custom as/size/fallback controls.
	 *   takes_first_usable    bool     The tag emits at most ONE result: the read of the FIRST
	 *                    USABLE source its chain produces — usable is the engine gate's
	 *                    question and bws_source_gate() states it, NEVER field-populated,
	 *                    so the read may be empty and selection is field-independent (ADR 0007, the
	 *                    2026-08-21 reversal). THE AXIS, owned here: keyed on the template's
	 *                    DISPOSITION toward a plural resolved-source list — NOT on list mode
	 *                    and NOT on any list flag, because `{{table}}` (no list mode, the
	 *                    most load-bearing step limit in the plugin) is exactly the template
	 *                    a list-shaped proxy misclassifies. Named for the SELECTION rather
	 *                    than the cardinality on purpose: limits are inert on these tags
	 *                    precisely BECAUSE selection takes the first usable source, which a
	 *                    cardinality-shaped name would leave a reader expecting a limit to
	 *                    bound. Consequences (stated, not decided, elsewhere): the render
	 *                    path compiles the chain with every step limit stripped and reads
	 *                    at n = 1 (bws_read_bounded_sources, no predicate); the try_ constructor
	 *                    forces the slot bound to 1; the editor suppresses the limit control
	 *                    and the field note's several-results clause. Reaches render the way is_image
	 *                    does — captured at registration, no registry lookup — and is
	 *                    UNPREFIXED because both the base registration and the slot builder
	 *                    consume it (`try_` marks flags only the try_ constructor reads).
	 *                    True today of exactly content / permalink / image.
	 */
	private static array $modifier_templates = [];

	// ===
	// Registration
	// ===

	/**
	 * Register a base template descriptor for use by the try_ constructor.
	 *
	 * Called once per base template (from bws_register_base_tags()) after the GB tag is registered.
	 * Stores metadata needed by generate_base_try_tags() and by the converter's per-template
	 * migration-entry generator, which reads the same list through get_modifier_templates().
	 *
	 * NOT AFFECTED BY THE 1.21.0 WITHDRAWAL. register_modifier() was the second consumer and
	 * is now a stub; this one stays, and external callers registering a template still get a
	 * try_-prefixed tag out of it — provided the template sets `supports_try` and names its
	 * base tag's resolve seam as `resolve_fn` (see the descriptor shape above).
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
	 * Register a context modifier group — WITHDRAWN, registers nothing.
	 *
	 * Minted a prefixed family of GB tags (prefix + '_' + template_key) backed by one
	 * entity-resolution strategy. A registered chain root supersedes it: a source that
	 * wants to be a starting point registers a root and gets the whole base-tag surface
	 * (source paths, per-step limits, field pickers, previews) rather than a second copy
	 * of it that every new capability has to be built into by hand.
	 *
	 * NO GRACE FAMILY, DELIBERATELY. Minting a reduced family would keep alive the exact
	 * duplicate-capability surface the withdrawal exists to remove. The method survives
	 * as a warn-and-return only so a stale caller gets a notice instead of a fatal —
	 * five lines is a cheap price for that, and the hard delete lands in 1.22.0.
	 *
	 * Integrator-facing route out: docs/plugin-integration.md §1a (offer the source as a
	 * chain root) and §9 (convert stored prefixed tags to base tags in one converter run).
	 *
	 * TWO DIFFERENT VERSIONS RIDE THIS METHOD, and neither is a typo for the other.
	 * `@deprecated` takes the release that DEPRECATED it — 1.20.0, where the CHANGELOG
	 * announced it under `### Deprecated` and the method still minted a full family.
	 * `_doing_it_wrong()`'s third argument is WordPress's own `$version`, documented as
	 * the version the MESSAGE was added in, and the message is new in 1.21.0 because
	 * that is when the method stopped registering anything.
	 *
	 * @since 1.6.0
	 * @deprecated 1.20.0 Register a chain root instead. Mints nothing since 1.21.0; deleted in 1.22.0.
	 *
	 * @param array $config Former modifier config. Ignored.
	 */
	public static function register_modifier( array $config ): void {
		_doing_it_wrong(
			__METHOD__,
			'Context modifier families are withdrawn. Register a chain root instead (see docs/plugin-integration.md §1a), and use §9 to convert stored tags. No tags were registered.',
			'1.21.0'
		);
	}

	/**
	 * Build a modifier tag callback that dispatches to term_fn (via unset) or post_fn (via:'ref').
	 *
	 * ORPHANED SINCE 1.21.0 — register_modifier() was its only caller and now mints nothing,
	 * so nothing reaches this. It goes with the stub in 1.22.0, which is FW-129's remaining
	 * step. Left standing until then because its dispatch is cited by docs/adr/0005,
	 * docs/future-work.md's row on the base-source seam, and several in-tree comments — a
	 * deletion that has to repoint those is its own change, not a side effect of stubbing.
	 *
	 * Under the traversal pipeline (SPEC §T7/§V5) the modifier resolves its BASE
	 * source via base_source_key (term_ → TaxonomyTerm term-kind, view_ →
	 * an external post-kind source), then steps `src:ref` through the generic `ref` step —
	 * the per-combination traversal source class (TermRelatedPost and its external twin)
	 * is no longer invoked. `$traversal_src_key` is ACCEPTED-BUT-IGNORED: kept in the
	 * signature so register_modifier() (and external callers)
	 * pass it without change, but never read — the ref step does the traversal
	 * generically. An external family renders identically with zero changes on its side (SPEC §V5).
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
				if ( 'featured' === bws_use_effective( 'image', $opts ) && function_exists( 'bws_featured_image_core' ) ) {
					return bws_featured_image_core( $entity_id, $opts, $inst );
				}
				return $post_fn( $entity_id, $opts, $inst );
			};

			$srcterm_tax = sanitize_key( $opts['srcTermIn'] ?? '' );

			// srcTermIn dispatch: resolve target post (current or via ref), then call term_fn
			// against each taxonomy term on that post; first non-empty wins. Mirrors
			// bws_base_image_resolve_value's term-step loop. For term-context base sources, the
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
			// §V5): term_ → TaxonomyTerm (term kind), an external prefix → its own source (post kind).
			// The pipeline engine then steps it; traversal_src_key is accepted-but-
			// IGNORED (SPEC §V5 — an integrator still passes it, we never read it). The old
			// per-combination traversal source class (TermRelatedPost and its external twin)
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
	 * RESOLUTION runs through the shared render seam, so the carry-over rules live in one
	 * place for every container (bws_fold_slot_chain_options):
	 *   - An axis left unset CARRIES OVER from the previous resolving slot. Slot 1 seeds the
	 *     accumulator: ambient source, and the template's stripped first `use` value.
	 *   - A slot that does NOT resolve never feeds the accumulator, so a half-configured
	 *     slot cannot re-point a later slot's carry-over.
	 *   - A `same` source carries over the prior attempt's WHOLE CHAIN, hops and all: the
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

		// Snapshot existing tags for the dup-check inside the template loop.
		//
		// AXIS - A try_ TAG WHOSE NAME IS ALREADY TAKEN IS NOT REGISTERED, same yield as the
		// term_ constructor above and for the same reason: a fallback-chain tag is an extra
		// over the base tag it is built from, so a name clash costs an affordance rather
		// than an already-rendering page. The base half overwrites instead - the asymmetry
		// is deliberate and is explained at bws_gb_register_tag().
		//
		// AND THE YIELD IS REPORTED, same as the term_ half: bws_gb_note_tag_yielded() at the
		// dup-check below. The whole registry is kept rather than its keys because the report
		// names who holds the name.
		$existing_tags = \GenerateBlocks_Register_Dynamic_Tag::get_tags() ?? [];
		$existing      = array_keys( $existing_tags );

		foreach ( self::$modifier_templates as $tpl ) {
			if ( empty( $tpl['supports_try'] ) ) {
				continue;
			}

			$tag_name = 'try_' . $tpl['key'];

			if ( in_array( $tag_name, $existing, true ) ) {
				bws_gb_note_tag_yielded( $tag_name, $existing_tags[ $tag_name ] ?? null );
				continue;
			}
			$existing[] = $tag_name;

			if ( ! SettingsPage::is_modifier_enabled( 'try' ) ) {
				continue;
			}

			// The family's resolve seam is the one REQUIRED key (the gate below).
			$resolve         = $tpl['resolve_fn'] ?? null;
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
			$is_image        = ! empty( $tpl['is_image'] );
			$supports_link   = ! empty( $tpl['supports_link_wrap'] ) && ! $is_image;

			if ( ! $resolve ) {
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
			// chain on as depth-0 chain wire and the family's resolve seam reads it as
			// the base tag would, so neither half truncates it any more. It was
			// `['terms']` while the flatten stood — the triple had no spelling for a second
			// relationship step, so a wider offer would have authored wire that skipped.
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
						'steps'            => [ 'refs', 'terms', 'rows' ],
						// The axes that are TAG-level on this template, so the editor's
						// mount migrator and the control leave them alone at every slot
						// position — same split, same owner, as the trailing-option strip
						// below and the converter migrator.
						'tag_level'       => self::try_slot_axes( $tpl )['tag_level'],
						// The base template's collapsing capability, threaded per slot
						// (ADR 0007): the one step renderer then suppresses the Limit
						// results control on try_content/try_permalink/try_image slots
						// exactly as on their base tags.
						'takes_first_usable' => ! empty( $tpl['takes_first_usable'] ),
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
			// phone). A winning attempt whose source fans joins its values through the
			// family's resolve seam, exactly as the base tag does. `sep` is CHAIN-level (one
			// for the whole try_, not per-slot) — the walk hands it to every attempt.
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
			$slnk = $supports_link;
			// Media-block runtime backstop — templates whose output is a link-wrapping
			// contact tag (email/phone: mailto:/tel: <a>) must NOT render inside a GB media
			// block, whose empty tagName slips the native visibility gate (link-helpers.php).
			// Their default-on anchor would corrupt the <img src>. Mirrors the base
			// {{email}}/{{phone}} VE-vis/VP-vis backstop. [SPEC §32 V11]
			$media_guard = ! empty( $tpl['try_media_block_guard'] );
			// takes_first_usable, inherited from the base template (ADR 0007). Consumed at
			// the attempt bound.
			$collapse    = ! empty( $tpl['takes_first_usable'] );
			// Slot 1 default 'use' token = first option value in template's use definition.
			$default_use = $tpl_options['use']['options'][0]['value'] ?? '';

			$tpl_key = $tpl['key'];

			// The family facts the ATTEMPT WALK needs, and nothing else — cardinality, the
			// carry seed, the per-slot read gate, the collapsing bound. How an attempt reads
			// is $resolve's, the base tag's own seam (FW-136).
			$loop_cfg = [
				'per_slot_key' => $per_slot_key,
				'per_slot_use' => $per_slot_use,
				'no_key_uses'  => $no_key_uses,
				'default_use'  => $default_use,
				'collapse'     => $collapse,
			];

			$callback = static function ( $opts, $b, $inst ) use ( $resolve, $loop_cfg, $slnk, $media_guard, $tpl_key, $is_image ) {
				if ( $media_guard && function_exists( 'bws_tag_blocked_on_media_block' ) && bws_tag_blocked_on_media_block( $b ) ) {
					return '';
				}

				// THE WALK (includes/helpers/try-slot-loop.php). Null = every attempt read
				// nothing; the tail below is this shell's, exactly as a base tag's callback
				// owns its own fallback and label.
				$won = bws_try_run_attempts( (array) $opts, $inst, $loop_cfg, $resolve );

				if ( null !== $won ) {
					$value = $won['value'];
					// ONE link wrap, on the winning attempt's identity. A fanning read that
					// already wrapped its values per item reports link_id 0 and is left
					// alone — the same contract bws_base_text_callback() reads (FW-85).
					if ( $slnk && $won['link_id'] && function_exists( 'bws_wrap_with_link' ) ) {
						$value = bws_wrap_with_link(
							$value,
							$opts['linkTo'] ?? 'none',
							$opts['linkKey'] ?? '',
							! empty( $opts['newTab'] ),
							$won['link_id'],
							$won['link_type']
						);
					}
					return $value;
				}

				$is_preview = ! empty( $inst->context['bwsEditorPreview'] );
				$fallback   = sanitize_text_field( $opts['fallback'] ?? $opts['fallback_text'] ?? '' );

				// All attempts exhausted — apply the fallback, then label if in preview.
				// Image templates: `fallback` is a media-picker attachment id/URL, not
				// literal text — route it through the same resolver the base `{{image}}`
				// arm uses (bws_image_stated_fallback, shared owner per image-tags.php),
				// so `as`/size are honored instead of the raw id being echoed verbatim.
				if ( $is_image ) {
					if ( function_exists( 'bws_image_stated_fallback' ) ) {
						$img_fallback = bws_image_stated_fallback( $opts, $inst );
						if ( '' !== $img_fallback ) {
							return $img_fallback;
						}
					}
				} elseif ( '' !== $fallback ) {
					return bws_gb_tag_output( $fallback, $opts, $inst );
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
		// THE plugin's one registration site is bws_gb_register_tag(), in
		// includes/helpers/gb-registration-boundary.php. Both template constructors reach GB
		// through it. Neither can hand it a taken name: each skips one at its dup-check
		// (see the two AXIS comments above), so every collision that function reports is
		// a base-tag collision.
		bws_gb_register_tag( $args );
	}

}
