<?php
/**
 * Taxonomy Term source - resolves `term,<ID>` to that term; it has no ambient answer.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.2.0
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
		return __( 'Specific Term', 'generateblocks' );
	}

	public function get_context_type(): string {
		return 'term';
	}

	/**
	 * No ambient term: this source always answers false.
	 *
	 * The root argument is REQUIRED (see get_root_argument()), so the factory refuses a bare
	 * `term` and resolves `term,<ID>` through resolve_root_argument(); neither path asks this
	 * method. The term a term archive is about is the factory's own ambient capture
	 * (bws_capture_ambient_signals()), not this source's. `false` is the contract's "no entity
	 * in this context", and it is also what a direct caller through
	 * SourceRegistry::get_source( 'term' ) gets.
	 *
	 * @since Unreleased Always false; the ambient detector behind it is deleted (FW-8).
	 * @param array  $options  Tag options from GenerateBlocks.
	 * @param object $instance Block instance.
	 * @return false
	 */
	public function resolve_id( array $options, $instance ) {
		return false;
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
	 * THE REFUSAL LIVES AT THE FACTORY SEAM, NOT IN resolve_id() ABOVE. The root policy is
	 * about wire that NAMES a root; resolve_id() answers for a tag that names none. The
	 * refusal makes resolve_id() unreachable for this source, which is a consequence of the
	 * declaration being REQUIRED and not a license to fold one site into the other.
	 *
	 * UNREACHABLE IS A MEASURED CLAIM, and here is what it rests on (2026-09-16): a census of
	 * every `->resolve_id(` call site under includes/ left only the factory above; and on
	 * the testbed both
	 * `{{text src:term|use:title}}` and `{{text src:term,999999|use:title}}` render empty on /department/sales/, where reaching
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
}
