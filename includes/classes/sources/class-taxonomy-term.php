<?php
/**
 * Taxonomy Term source - resolves to a term in context (archive, source selector, or post assignment).
 *
 * @package BWS_Dynamic_Tags
 * @since 1.2.0
 * @since 1.2.0 Added format_id_for_acf().
 * @since 1.20.0 Offered as a DECLARING chain root — `term,<ID>` (FW-39).
 */

namespace BWS\DynamicTags\Sources;

use BWS\DynamicTags\AbstractSource;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxonomyTerm extends AbstractSource {

	public function get_source_key(): string {
		return 'term';
	}

	public function get_source_label(): string {
		return __( 'Term', 'generateblocks' );
	}

	public function get_tag_prefix(): string {
		return 'term';
	}

	public function get_gb_type(): string {
		return 'term';
	}

	public function get_context_type(): string {
		return 'term';
	}

	/**
	 * Format term ID as ACF object_id for relationship field traversal.
	 *
	 * ACF expects "term_{$id}" when querying fields on a term entity.
	 *
	 * @since 1.2.0
	 * @param int|string $id Resolved term ID.
	 * @return string
	 */
	public function format_id_for_acf( $id ) {
		return 'term_' . $id;
	}

	/**
	 * Resolve the term ID from tag options and context.
	 *
	 * Uses GB's canonical term resolver first (consistent with GB Pro's term_meta),
	 * then falls back to our multi-method detection for broader context support.
	 *
	 * NO AMBIENT ARM SINCE 1.21.0. GenerateBlocks_Dynamic_Tags::get_id( …, 'term' ) answers
	 * from an explicit `id` option, from the `generateblocks_dynamic_tag_id` filter (how a
	 * query loop hands down the row's term), or failing both from a bare
	 * `get_queried_object_id()` — and nothing in the answer says which of the three replied.
	 * That GB fact, and the measurement behind it, are docs/gb-constraints.md
	 * §"`generateblocks_dynamic_tag_id` is not told WHICH entity the id was a fallback for".
	 * The first two arms are somebody STATING a term. The third is a raw id for whatever WP
	 * queried, of whatever kind, and its ids share one number space with terms, so a page
	 * whose own id happens to be a real term's id reads that unrelated term's data. Through
	 * 1.20.0 that arm was gated on the queried object's TYPE; the gate had exactly one
	 * consumer, the `term_*` family, and went out with it in 1.21.0 (FW-129).
	 *
	 * WHAT REPLACED THE GATE IS NOT A WEAKER GATE — it is refusing the arm. An answer equal
	 * to `get_queried_object_id()` and not stated as an `id` option is treated as the bare
	 * arm and declined here, which drops the type question rather than answering it. The
	 * cost is the arm's one honest case: a stated term id that COINCIDES with the queried
	 * object's id now falls through instead of returning early, to the detector below.
	 * WHAT THAT COSTS IS NOT ASSERTED HERE, because nothing measures it — this method is
	 * unreachable through the factory (see is_selectable_root()), so no fixture row and no
	 * snapshot exercises the fall-through, and a sentence about what it yields would rest on
	 * the code's shape alone.
	 *
	 * EXISTENCE IS CHECKED TOO, as a SECOND and independent condition. A stated id still has
	 * to name a term that is there, and the old unguarded `if ( $id )` never asked. It is not
	 * a cheaper stand-in for the arm test above — both ids in the 1.20.0 collision named real
	 * terms — so it is stated separately. bws_get_validated_term() owns what valid means.
	 *
	 * FAILING THIS GUARD IS NOT THE END OF RESOLUTION — it falls through to the detector
	 * below, whose tiers still answer from the tag's own options (`term_id`, `id`), from a
	 * term archive, and from `tax` + the current post.
	 *
	 * @since 1.20.0 The ambient arm gates on the queried object's TYPE.
	 * @since 1.21.0 The ambient arm is refused outright; the type gate retired with `term_*`.
	 * @param array  $options  Tag options from GenerateBlocks.
	 * @param object $instance Block instance.
	 * @return int|false Term ID or false if unresolvable.
	 */
	public function resolve_id( array $options, $instance ) {
		if ( class_exists( 'GenerateBlocks_Dynamic_Tags' ) ) {
			$id = \GenerateBlocks_Dynamic_Tags::get_id( $options, 'term', $instance );

			$stated = ! empty( $options['id'] ) || (int) $id !== (int) get_queried_object_id();

			if ( $id && $stated && bws_get_validated_term( (int) $id ) ) {
				return (int) $id;
			}
		}

		// Fallback: our multi-method detection (handles term_id option, queried object, taxonomy+post).
		return bws_reliable_term_context_detection( $options );
	}

	/**
	 * Offered as a chain root — a DECLARING one (FW-39).
	 *
	 * `term` has resolved through the factory since it was registered and has never been
	 * OFFERED: nothing authored `src:term`, which is why bare `src:term` can only exist in
	 * hand-edited wire. What is offered now is `term,<ID>`, and the argument below is
	 * REQUIRED — see get_root_argument(). A bare `term` root is what a bare base tag
	 * already does (the ambient term on a term archive), so there is no second meaning for
	 * it to carry, and the factory refuses it rather than resolving one.
	 *
	 * THAT REFUSAL DOES NOT REACH resolve_id() ABOVE, and the two say different things. The
	 * root policy is enforced at the factory seam and is about wire that NAMES a root; what
	 * resolve_id() answers is a separate question that has moved twice since (1.20.0 gated
	 * the ambient arm on the queried object's type, 1.21.0 refused the arm outright). The
	 * `term_*` family was the caller that kept the two apart; with it gone the refusal makes
	 * resolve_id() unreachable for this source, which is a consequence of the declaration
	 * being REQUIRED and not a license to fold one site into the other.
	 *
	 * UNREACHABLE IS A MEASURED CLAIM, and here is what it rests on (2026-09-16): a census of
	 * every `->resolve_id(` call site under includes/ leaves two, the factory above and an
	 * orphan inside make_modifier_callback() that register_modifier() stopped feeding in
	 * 1.21.0; and on the testbed both `{{text src:term|use:title}}` and
	 * `{{text src:term,999999|use:title}}` render empty on /department/sales/, where reaching
	 * resolve_id() would have returned the page's own term.
	 *
	 * @since 1.20.0
	 * @return bool
	 */
	public function is_selectable_root(): bool {
		return true;
	}

	/**
	 * `term,<ID>` — the argument, and it is required (FW-39).
	 *
	 * The label names what the ARGUMENT means to an author ("Term"), which here reads the
	 * same as the source's own label and will not on every root; the two are separate
	 * fields because a Staff Roster root's argument is a department, not a roster.
	 *
	 * ROOT_ARGLESS_REFUSE is stated rather than left to the normalizer's default, because
	 * this is the declaration a reader will copy: refusal is the decision, not an
	 * omission.
	 *
	 * WHICH ENTITY KIND the picker browses is NOT declared here — bws_registered_root_rows()
	 * derives it from get_context_type(), so the list an author picks from cannot come to
	 * disagree with what this source resolves.
	 *
	 * @since 1.20.0
	 * @return array
	 */
	public function get_root_argument(): array {
		return array(
			'label'   => __( 'Term', 'generateblocks' ),
			'control' => 'bws-entity-picker',
			'argless' => \BWS\DynamicTags\SourceInterface::ROOT_ARGLESS_REFUSE,
		);
	}

	/**
	 * Resolve `term,<ID>` to that term, or to nothing (FW-39).
	 *
	 * VERIFIED against the taxonomy store, not cast and trusted. An argument whose term has been
	 * deleted must render blank and read `(missing)` in the editor, and an unchecked
	 * `(int)` would hand a dead id to the field read, where a missing term and a term with
	 * an empty field produce the same empty output — the failure the author is least able
	 * to see.
	 *
	 * A NON-NUMERIC argument resolves nothing rather than being reinterpreted. FW-56's
	 * designed slug-recovery affordance (a non-numeric `term,<arg>` recovering as a
	 * `terms` step) was dropped for the reason V9 retired the differently-spelled engine
	 * types: reinterpreting a near-miss is exactly what that removed, and unknown
	 * vocabulary is a preserved distinct answer everywhere else in this grammar.
	 *
	 * @since 1.20.0
	 * @param string $arg      Term id as authored.
	 * @param array  $options  Tag options.
	 * @param object $instance Block instance.
	 * @return int|false
	 */
	public function resolve_root_argument( string $arg, array $options, $instance ) {
		// bws_strict_digit_id() (field-helpers.php) is the one validator both declaring
		// roots share — see its own PHPDoc for why is_numeric()+cast is not enough.
		if ( ! function_exists( 'bws_strict_digit_id' ) || ! bws_strict_digit_id( $arg ) ) {
			return false;
		}
		if ( ! function_exists( 'bws_get_validated_term' ) ) {
			return false;
		}
		$term = bws_get_validated_term( (int) $arg );
		if ( ! $term ) {
			return false;
		}
		return (int) $term->term_id;
	}

	public function get_source_options(): array {
		return array();
	}
}
