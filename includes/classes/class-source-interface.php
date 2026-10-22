<?php
/**
 * Source Interface for dynamic tag sources.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 * @since 1.2.0 Renamed resolve_post_id() to resolve_id(); added tag prefix, context type, and related variant methods.
 * @since 1.2.0 Added format_id_for_acf(), source_default_enabled(), related_variant_default_enabled().
 * @since 1.4.1 Added tag_default_enabled().
 * @since 1.5.0 Removed related-variant methods; added needs_relationship_field(), get_ui_group().
 * @since 1.6.0 Removed get_title_prefix() and get_traversal_options().
 * @since 1.17.0 Added is_selectable_root() (#83).
 * @since 1.20.0 Added get_root_argument() + the argless-policy constants (FW-39).
 * @since 1.20.0 Added resolve_root_argument() (FW-39).
 * @since Unreleased Removed ten methods nothing read (FW-176).
 */

namespace BWS\DynamicTags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface SourceInterface {

	/**
	 * A declared root argument is REQUIRED: an argless root resolves nothing (FW-39).
	 *
	 * The default, and what `term,<ID>` / `post,<ID>` declare. Bare `term` is already what
	 * a bare base tag does and bare `post` would be `current` under another name, so
	 * neither has an argless meaning left to carry.
	 *
	 * @since 1.20.0
	 * @var string
	 */
	public const ROOT_ARGLESS_REFUSE = 'refuse';

	/**
	 * The SOURCE answers a bare root by a rule it states (FW-39).
	 *
	 * The only other value, and it exists for a root that ships ARGLESS TODAY and gains an
	 * argument later — a sister plugin's Staff Roster `roster`, which answers a bare `roster`
	 * from its own priority ranking. Declaring it is not permission to fall back to the
	 * ambient entity: an argless root never degrades to whatever the page is about
	 * (CONTEXT.md I15 at the root layer), it resolves by the owner's stated rule or not at
	 * all.
	 *
	 * @since 1.20.0
	 * @var string
	 */
	public const ROOT_ARGLESS_OWNER_RESOLVES = 'owner-resolves';

	/**
	 * Get the unique source key (e.g. 'post', 'term', 'site').
	 *
	 * @return string
	 */
	public function get_source_key(): string;

	/**
	 * Get the human-readable label for admin display.
	 *
	 * @return string
	 */
	public function get_source_label(): string;

	/**
	 * Resolve the target entity ID from tag options and block instance.
	 *
	 * @param array  $options  Tag options from GenerateBlocks.
	 * @param object $instance Block instance.
	 * @return int|string|false Entity ID or false if unresolvable.
	 */
	public function resolve_id( array $options, $instance );

	/**
	 * Get the context type — determines which templates apply to this source.
	 *
	 * @return string 'post' or 'term'.
	 */
	public function get_context_type(): string;

	/**
	 * Whether an author may CHOOSE this source as a chain root (#83).
	 *
	 * Governs the DROPDOWN ONLY. Returning false does not stop wire naming this source
	 * from resolving — the factory's registry delegation is untouched, deliberately: wire
	 * is hand-editable by decision (ADR 0004), tags naming a root exist the moment a
	 * migration runs, and an integrator flipping this off must not blank stored content.
	 * A reader meeting a boolean called "selectable root" will be tempted to gate
	 * resolution on it; tools/test/traversal-pipeline-test.php pins that it is not.
	 *
	 * STATED, never inferred, because the registry accumulates non-offerable entries by
	 * policy and never sheds them: a register_source() call is never deleted for lacking
	 * resolve logic, so five inert entries exist already and each future retirement adds
	 * another. A registry that keeps its dead is the wrong shape to derive an authoring
	 * enum from — permanently, not just today.
	 *
	 * PRECONDITION for returning true: the source RESOLVES ITS OWN ID FROM AMBIENT
	 * CONTEXT.
	 *
	 * The dropdown label is get_source_label() — there is no second label method.
	 *
	 * @since 1.17.0
	 * @return bool
	 */
	public function is_selectable_root(): bool;

	/**
	 * This root's ARGUMENT, if it takes one (FW-39).
	 *
	 * A root that names an entity needs an author to say WHICH — `term,34`, `post,1692`.
	 * The token stays the source's bare key and the argument travels beside it, so every
	 * registry-name lookup and is_selectable_root() check stays a comparison rather than
	 * becoming a parse.
	 *
	 * ONE argument, arity fixed at one, and OPAQUE to everything that carries it. This
	 * declaration says what it means and which control edits it; nothing between here and
	 * that control interprets the value. It is not always an ID — a sister Staff Roster
	 * plugin wants `roster,<department-slug>` — so a numeric assumption anywhere in the carry
	 * path would be wrong the first time it is used. Plural arguments would be a grammar
	 * change touching every step type and the twin JS port, and nothing needs them.
	 *
	 * ON THE CONTRACT, not on a lookup table beside the roots enum, so a root registered
	 * through `bws_dynamic_tags_chain_roots` can declare one too. FW-69/70 made that route
	 * public integration surface, and an argument only in-repo sources could use would
	 * make it second-class.
	 *
	 * The returned shape, normalized for both authoring surfaces by
	 * bws_registered_root_rows() — which is where a malformed declaration is dropped:
	 *
	 *   label    string  what the argument MEANS, author-facing ("Term", "View").
	 *   control  string  the `bws-*` control that edits it. Required: a declared argument
	 *                    an author cannot fill is a root that can only refuse.
	 *   argless  string  ROOT_ARGLESS_REFUSE (the default) or
	 *                    ROOT_ARGLESS_OWNER_RESOLVES. STATED rather than inferred from
	 *                    whether some method exists — callback-presence as a proxy is the
	 *                    pattern FW-38 is retiring.
	 *
	 * @since 1.20.0
	 * @return array Empty when this root takes no argument (the default).
	 */
	public function get_root_argument(): array;

	/**
	 * Resolve this root's ARGUMENT to the entity it names (FW-39).
	 *
	 * The counterpart of get_root_argument(): that method declares an argument exists and
	 * says which control fills it, and this one is where the token finally MEANS
	 * something. Everything between the two — the grammar, the compiler, the root rows,
	 * both authoring surfaces, the picker's own value — carries the token opaquely,
	 * because it is not always an ID (a sister Staff Roster plugin wants
	 * `roster,<department-slug>`) and a numeric assumption in the carry path would be wrong
	 * the first time one shipped. The DECLARING SOURCE is the only thing that knows.
	 *
	 * Called ONLY with a non-empty argument, and only on a source that declared one, so an
	 * implementation never has to distinguish "no argument" from "an argument naming
	 * nothing". Both of those are answered before it is reached — the first by the argless
	 * policy at the factory seam (bws_factory_registry_source), the second by this method
	 * returning false.
	 *
	 * SEPARATE FROM resolve_id(), not a third parameter on it. resolve_id() answers from
	 * AMBIENT CONTEXT, which every source that does not declare an argument still relies on.
	 * A read from a named entity is a different question with a different input, and widening
	 * the ambient method's signature would put both behind one implementation that has to
	 * tell them apart.
	 *
	 * A FALSE RETURN IS TERMINAL. The factory does not then fall back to resolve_id():
	 * an argument naming a deleted entity renders nothing, it does not quietly become the
	 * current page's entity (CONTEXT.md I15 at the root layer).
	 *
	 * @since 1.20.0
	 * @param string $arg      The root argument as authored, verbatim.
	 * @param array  $options  Tag options.
	 * @param object $instance GB tag instance.
	 * @return int|string|false The named entity id, or false when the argument names none.
	 */
	public function resolve_root_argument( string $arg, array $options, $instance );
}
