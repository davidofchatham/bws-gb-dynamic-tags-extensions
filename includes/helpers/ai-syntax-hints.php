<?php
/**
 * The `aiSyntax` hints: tag-string syntax for AI clients, each on the option it describes.
 *
 * An option's definition carries the editor's control config, which an AI client cannot turn
 * into a tag string, so an option may also carry an `aiSyntax` string saying what to write
 * for that option. bws_gb_register_tag() adds it, and only when GeneratePress AI is active
 * (GB sends every tag definition to every editor load, so the text is kept out of the editor
 * otherwise). Without it the definitions are unchanged.
 *
 * A HINT STATES THE CONSEQUENCE AND AN EXAMPLE, never a rule's axis; the code that owns a
 * grammar owns what decides it. Examples avoid `key` and `use:meta|option` reads, because
 * this is what we choose to teach, not a block. The text is for a machine, so it is not
 * wrapped in `__()`.
 *
 * tools/test/ai-syntax-hint-test.php holds every example against the registered option keys
 * and values, so a renamed option cannot leave a stale example behind.
 *
 * @package BWS_Dynamic_Tags
 * @since Unreleased
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tags that carry no hint at all, shared or their own. `call` returns whatever a registered
 * function returns, which sits beside the field reads we do not teach.
 */
const BWS_AI_SYNTAX_UNHINTED_TAGS = array( 'call' );

/**
 * The hints. `*` holds the SHARED hints, keyed by option key: each lands on every hinted tag
 * that registers that option, so its wording must hold on all of them and names no tag. A
 * tag's own entry, keyed by tag name, adds options or replaces a shared hint for that tag.
 *
 * Budget per hint: about 350 bytes, and text `sanitize_text_field()` returns unchanged (no
 * `<`, `>`, `%` or line break), so what the AI client reads is what is written here.
 *
 * @since Unreleased
 * @return array<string,array<string,string>>
 */
function bws_ai_syntax_hints(): array {
	return array(
		'*'    => array(
			'src'    => "Where the tag reads from; leave it out for the current context. Steps join with semicolons; a step's argument follows a comma. src:parent is the parent, src:post,42 is post 42, src:terms,category the post's categories, src:refs,related_posts the posts in that relationship, src:rows,team;refs,person each repeater row's linked post.",
			'linkTo' => "Wraps the tag's output in a link. linkTo:permalink links to the post or term the tag read; leave it out for no link.",
		),
		'text' => array(
			'use' => "Set one of these: use:title prints the title or name of what src names; fixed:Your text prints text you write, with use left out. Examples: {{text use:title}}, {{text fixed:Read more}}.",
		),
	);
}

/**
 * The hints one tag receives, option key to text: the shared hints overlaid by its own.
 * Whether an option exists on the tag is the caller's to check.
 *
 * @since Unreleased
 * @param string $tag Tag name.
 * @return array<string,string>
 */
function bws_ai_syntax_hints_for( string $tag ): array {
	if ( in_array( $tag, BWS_AI_SYNTAX_UNHINTED_TAGS, true ) ) {
		return array();
	}
	$hints = bws_ai_syntax_hints();
	return array_merge( $hints['*'], $hints[ $tag ] ?? array() );
}
