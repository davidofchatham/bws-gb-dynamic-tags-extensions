<?php
/**
 * Post source - resolves to a post in context (current loop post or specific post via 'source' support).
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 * @since 1.2.0 Renamed source key from 'current_post' to 'post'. Added tag prefix,
 *              related variant, and related effective source methods.
 * @since 1.20.0 Offered as a PINNING chain root — `post,<ID>` (FW-39, ticket 03).
 */

namespace BWS\DynamicTags\Sources;

use BWS\DynamicTags\AbstractSource;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CurrentPost extends AbstractSource {

	public function get_source_key(): string {
		return 'post';
	}

	public function get_source_label(): string {
		return __( 'Post', 'generateblocks' );
	}

	public function get_tag_prefix(): string {
		return 'post';
	}

	public function get_gb_type(): string {
		return 'post';
	}

	public function get_effective_source_id(): string {
		return 'post';
	}

	public function resolve_id( array $options, $instance ) {
		if ( ! class_exists( 'GenerateBlocks_Dynamic_Tags' ) ) {
			return false;
		}

		$post_id = \GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance );

		return $post_id ? (int) $post_id : false;
	}

	public function get_source_options(): array {
		return array();
	}

	/**
	 * Offered as a chain root — a PINNING one (FW-39, ticket 03).
	 *
	 * `post` has resolved through the factory since it was registered and has never been
	 * OFFERED — bare `post` would be `current` under another name (D1/D2), and `current`
	 * already reads whatever the page is about. What is offered now is `post,<ID>`, and the
	 * argument below is REQUIRED — see get_root_argument(). Mirrors TaxonomyTerm's own
	 * root offering exactly; see that class for the fuller rationale, which applies here
	 * unchanged.
	 *
	 * resolve_id() above is untouched by this and must stay that way — it answers the
	 * AMBIENT post on every ordinary base tag, a question this root's refusal policy does
	 * not touch (enforced at the factory seam, which resolve_id() does not go through).
	 *
	 * @since 1.20.0
	 * @return bool
	 */
	public function is_selectable_root(): bool {
		return true;
	}

	/**
	 * `post,<ID>` — the pin, and it is required (FW-39, ticket 03).
	 *
	 * D13: post has no migration half, so unlike `term` this argument has no legacy `id`
	 * read path to stay compatible with — it is authoring-only from day one.
	 *
	 * @since 1.20.0
	 * @return array
	 */
	public function get_root_argument(): array {
		return array(
			'label'   => __( 'Post', 'generateblocks' ),
			'control' => 'bws-entity-picker',
			'argless' => \BWS\DynamicTags\SourceInterface::ROOT_ARGLESS_REFUSE,
		);
	}

	/**
	 * Resolve `post,<ID>` to that post, or to nothing (FW-39, ticket 03).
	 *
	 * VERIFIED against the post store with `get_post()`, not cast and trusted — same
	 * posture as TaxonomyTerm::resolve_root_argument(). A post whose id has never existed,
	 * or that has been permanently deleted, must render blank and read `(missing)` in the
	 * editor rather than silently reading nothing through a dead id. A TRASHED post still
	 * exists in the store (trash is a status, not a deletion) and resolves — an author who
	 * pinned a post before moving it to trash is not the case this seam exists to catch.
	 *
	 * Shares its digit-string validation with TaxonomyTerm via `bws_strict_digit_id()`
	 * (field-helpers.php) — see that function's own PHPDoc for why `is_numeric()` + cast
	 * is not enough.
	 *
	 * @since 1.20.0
	 * @param string $arg      Post id as authored.
	 * @param array  $options  Tag options.
	 * @param object $instance Block instance.
	 * @return int|false
	 */
	public function resolve_root_argument( string $arg, array $options, $instance ) {
		if ( ! function_exists( 'bws_strict_digit_id' ) || ! bws_strict_digit_id( $arg ) ) {
			return false;
		}
		if ( ! function_exists( 'get_post' ) ) {
			return false;
		}
		$post = get_post( (int) $arg );
		if ( ! $post ) {
			return false;
		}
		return (int) $post->ID;
	}
}
