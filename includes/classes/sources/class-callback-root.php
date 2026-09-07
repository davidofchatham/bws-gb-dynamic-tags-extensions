<?php
/**
 * A chain root declared from a filter, adapted into an ordinary registered source (#83).
 *
 * The FILTER ROUTE's whole implementation. `bws_dynamic_tags_chain_roots` returns
 * declarative specs (label, context, resolver); SourceRegistry::init() wraps each one in
 * this class and registers it normally, so it reaches the SAME registry the renderer
 * already consults. That is what makes an offered root always a root that resolves —
 * there is no second registry, no second resolution path, and no enum row that exists for
 * the editor and not for the render.
 *
 * ALWAYS a selectable root. The filter is named for roots, so declaring through it IS the
 * opt-in: there is no flag to forget and no default to disagree with the class route. A
 * plugin wanting a source that is NOT offerable uses the existing registration action.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.17.0
 * @since 1.20.0 Carries a declared root argument (FW-39).
 */

namespace BWS\DynamicTags\Sources;

use BWS\DynamicTags\AbstractSource;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CallbackRoot extends AbstractSource {

	/** @var string */
	private string $key;

	/** @var string */
	private string $label;

	/** @var string */
	private string $context;

	/** @var callable */
	private $resolver;

	/** @var array Root-argument declaration, empty when this root takes none. */
	private array $root_arg;

	/**
	 * @since 1.17.0
	 * @param string   $key      Source key — the `src` token authors get in their wire.
	 * @param string   $label    Author-facing dropdown label.
	 * @param string   $context  'post' or 'term' — decides the resolved-source KIND.
	 * @param callable $resolver callable( array $options, $instance ): int|string|false.
	 * @param array    $root_arg Root-argument declaration, or empty for none (FW-39).
	 */
	public function __construct( string $key, string $label, string $context, callable $resolver, array $root_arg = array() ) {
		$this->key      = $key;
		$this->label    = $label;
		$this->context  = ( 'term' === $context ) ? 'term' : 'post';
		$this->resolver = $resolver;
		$this->root_arg = $root_arg;
	}

	public function get_source_key(): string {
		return $this->key;
	}

	public function get_source_label(): string {
		return $this->label;
	}

	public function get_context_type(): string {
		return $this->context;
	}

	/**
	 * Term-context roots yield a term entity, so ACF wants the `term_` prefix — the same
	 * rule TaxonomyTerm states for itself. A post-context root passes through.
	 *
	 * @since 1.17.0
	 * @param int|string $id Resolved entity ID.
	 * @return int|string
	 */
	public function format_id_for_acf( $id ) {
		return ( 'term' === $this->context ) ? 'term_' . $id : $id;
	}

	public function get_gb_type(): string {
		return ( 'term' === $this->context ) ? 'term' : 'post';
	}

	/**
	 * Declaring through the roots filter IS the opt-in — see the class header.
	 *
	 * @since 1.17.0
	 * @return bool
	 */
	public function is_selectable_root(): bool {
		return true;
	}

	/**
	 * The declaration passed through from the filter spec, VERBATIM (FW-39).
	 *
	 * Not validated here. bws_registered_root_rows() is the one reader of every source's
	 * declaration and drops a malformed one there, so validating a second time in the
	 * filter route's adapter would put the same rule at two sites — and the class route
	 * would still reach the first one. The resolver and label ARE checked before
	 * construction, because a spec failing either has no row to be at all; a bad argument
	 * declaration leaves a perfectly good argless root.
	 *
	 * @since 1.20.0
	 * @return array
	 */
	public function get_root_argument(): array {
		return $this->root_arg;
	}

	/**
	 * @since 1.17.0
	 * @param array  $options  Tag options.
	 * @param object $instance Block instance.
	 * @return int|string|false
	 */
	public function resolve_id( array $options, $instance ) {
		return call_user_func( $this->resolver, $options, $instance );
	}

	/**
	 * No per-tag options. A filter-declared root is the CHEAP case by construction: it
	 * states an entity and nothing else. A source needing its own options writes a class.
	 *
	 * @since 1.17.0
	 * @return array
	 */
	public function get_source_options(): array {
		return array();
	}
}
