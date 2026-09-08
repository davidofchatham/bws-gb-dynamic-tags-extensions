<?php
/**
 * Taxonomy Term source - resolves to a term in context (archive, source selector, or post assignment).
 *
 * @package BWS_Dynamic_Tags
 * @since 1.2.0
 * @since 1.2.0 Added format_id_for_acf().
 * @since 1.20.0 Offered as a PINNING chain root — `term,<ID>` (FW-39).
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
	 * @param array  $options  Tag options from GenerateBlocks.
	 * @param object $instance Block instance.
	 * @return int|false Term ID or false if unresolvable.
	 */
	public function resolve_id( array $options, $instance ) {
		if ( class_exists( 'GenerateBlocks_Dynamic_Tags' ) ) {
			$id = \GenerateBlocks_Dynamic_Tags::get_id( $options, 'term', $instance );
			if ( $id ) {
				return (int) $id;
			}
		}

		// Fallback: our multi-method detection (handles term_id option, queried object, taxonomy+post).
		if ( function_exists( 'bws_reliable_term_context_detection' ) ) {
			return bws_reliable_term_context_detection( $options );
		}

		return false;
	}

	/**
	 * Offered as a chain root — a PINNING one (FW-39).
	 *
	 * `term` has resolved through the factory since it was registered and has never been
	 * OFFERED: nothing authored `src:term`, which is why bare `src:term` can only exist in
	 * hand-edited wire. What is offered now is `term,<ID>`, and the argument below is
	 * REQUIRED — see get_root_argument(). A bare `term` root is what a bare base tag
	 * already does (the ambient term on a term archive), so there is no second meaning for
	 * it to carry, and the factory refuses it rather than resolving one.
	 *
	 * resolve_id() below is untouched by that refusal, and must stay that way: the
	 * `term_*` modifier family calls it on every request and reads the ambient term
	 * forever. The root policy is enforced at the factory seam, which the modifier family
	 * does not go through.
	 *
	 * @since 1.20.0
	 * @return bool
	 */
	public function is_selectable_root(): bool {
		return true;
	}

	/**
	 * `term,<ID>` — the pin, and it is required (FW-39).
	 *
	 * The label names what the ARGUMENT means to an author ("Term"), which here reads the
	 * same as the source's own label and will not on every root; the two are separate
	 * fields because a Site Views root's argument is a dimension, not a view.
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
	 * VERIFIED against the taxonomy store, not cast and trusted. A pin whose term has been
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
		// bws_strict_digit_id() (field-helpers.php) is the one validator both pinning
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
