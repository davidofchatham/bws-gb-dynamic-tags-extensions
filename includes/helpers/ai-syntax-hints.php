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
 * function returns, and the two datetime tags print only a field's date (`key`, `startKey`),
 * so both sit beside the field reads we do not teach: nothing is left to say about their source.
 * `try_email` and `try_phone` join them: a slot is a field read unless it types an address, and
 * a typed address in the first slot always wins, so a chain of them has nothing to fall back to.
 */
const BWS_AI_SYNTAX_UNHINTED_TAGS = array(
	'call',
	'datetime_single',
	'datetime_range',
	'try_datetime_single',
	'try_datetime_range',
	'try_email',
	'try_phone',
);

/**
 * The sentence on a later attempt's hint that says its read is the previous attempt's unless it states
 * its own. Only the three tags with a read axis carry it; the harness pins that.
 */
const BWS_AI_SYNTAX_TRY_CARRY = ' It reads the way the attempt before did, unless it states its own use.';

/**
 * The `try_*` slot hints, generated from one template so five tags cannot drift apart. A try_
 * tag registers its attempts as slot options `A` to `E`, each holding a source chain and, where
 * the tag has one, a read; there is no `src` option, so the chain is taught on `A` and the
 * attempts after it on `B`.
 *
 * Per tag: `site` says what `src(site)` reads there (null when it reads nothing, as on content),
 * `read` is what attempt A must state of its read, if anything (a bare attempt on text or image is a field
 * read, which renders empty; on content it is the full content), `carry` says a later attempt keeps the read
 * before it (absent on title and permalink, which have no read), `example` is one rendered chain, and `later`
 * one with three attempts for the hint on `B`.
 *
 * @since Unreleased
 * @return array<string,array<string,string>>
 */
function bws_ai_syntax_try_hints(): array {
	$tags = array(
		'try_text'      => array(
			'site'    => 'the site name',
			'read'    => ';use(title), needed in attempt A',
			'carry'   => BWS_AI_SYNTAX_TRY_CARRY,
			'example' => '{{try_text A:src(refs,related_posts);use(title)|B:src(site);use(title)}}',
			'later'   => '{{try_text A:use(title)|B:src(parent);use(title)|C:src(site);use(title)}}',
		),
		'try_title'     => array(
			'site'    => 'the site name',
			'read'    => '',
			'example' => '{{try_title A:src(refs,related_posts)|B:src(site)}}',
			'later'   => '{{try_title A:src(refs,related_posts)|B:src(parent)|C:src(site)}}',
		),
		'try_permalink' => array(
			'site'    => 'the home address',
			'read'    => '',
			'example' => '{{try_permalink A:src(refs,related_posts)|B:src(site)}}',
			'later'   => '{{try_permalink A:src(refs,related_posts)|B:src(parent)|C:src(site)}}',
		),
		'try_content'   => array(
			'site'    => null,
			'read'    => ';use(excerpt), or no use for the full content',
			'carry'   => BWS_AI_SYNTAX_TRY_CARRY,
			'example' => '{{try_content A:src(refs,related_posts);use(excerpt)|B:src(current);use(excerpt)}}',
			'later'   => '{{try_content A:src(refs,related_posts)|B:src(parent)|C:src(current)}}',
		),
		'try_image'     => array(
			'site'    => 'the site logo',
			'read'    => ';use(featured), needed in attempt A',
			'carry'   => BWS_AI_SYNTAX_TRY_CARRY,
			'example' => '{{try_image A:use(featured)|B:src(parent);use(featured)}}',
			'later'   => '{{try_image A:use(featured)|B:src(parent);use(featured)|C:src(site);use(featured)}}',
		),
	);

	$out = array();
	foreach ( $tags as $tag => $t ) {
		$sources = array_filter( array(
			null === $t['site'] ? null : "src(site) is {$t['site']}",
			'src(parent) the parent',
			'src(refs,related_posts) related posts',
		) );
		$out[ $tag ] = array(
			'A' => 'Attempts A to E, joined with the | character; the first with a result wins. An attempt is src(...)' . $t['read'] . '. No src means the current context: ' . implode( ', ', $sources ) . '. Example: ' . $t['example'],
			'B' => 'The next attempt, tried only when the one before gives nothing; C, D and E add more. Write its source out, because an attempt with nothing in it is skipped.' . ( $t['carry'] ?? '' ) . ' Example: ' . $t['later'],
		);
	}
	return $out;
}

/**
 * The hints. `*` holds the SHARED hints, keyed by option key: each lands on every hinted tag
 * that registers that option, so its wording must hold on all of them and names no tag. A
 * tag's own entry, keyed by tag name, adds options or replaces a shared hint for that tag; a
 * `null` there withholds a shared hint from a tag it would mislead (`email` and `phone` teach
 * only a typed address, which needs no source).
 *
 * Budget per hint: about 350 bytes (a tag's own `src` entry, which replaces the shared one whole
 * and so repeats its chain syntax, may reach 450; the whole set stays under 15000), and text `sanitize_text_field()` returns unchanged (no
 * `<`, `>`, `%` or line break), so what the AI client reads is what is written here.
 *
 * @since Unreleased
 * @return array<string,array<string,string|null>>
 */
function bws_ai_syntax_hints(): array {
	return array_merge( array(
		'*'         => array(
			'src'    => "Where the tag reads from; leave it out for the current context. Steps join with semicolons; a step's argument follows a comma. src:parent is the parent, src:post,42 is post 42, src:terms,category the post's categories, src:refs,related_posts the posts in that relationship, src:rows,team;refs,person each repeater row's linked post.",
			'linkTo' => "Wraps the tag's output in a link. linkTo:permalink links to the post or term the tag read; leave it out for no link.",
		),
		'text'      => array(
			'use' => "Set one of these: use:title prints the title or name of what src names; fixed:Your text prints text you write, with use left out. Examples: {{text use:title}}, {{text fixed:Read more}}.",
		),
		'title'     => array(
			'src' => "Where the tag reads from; leave it out for the current context. Steps join with semicolons; a step's argument follows a comma. src:site is the site itself (its name), src:parent the parent, src:post,42 post 42, src:terms,category the post's categories, src:refs,related_posts the posts in that relationship, src:rows,team;refs,person each repeater row's linked post.",
		),
		'permalink' => array(
			'src' => "Where the tag reads from; leave it out for the current context. Steps join with semicolons; a step's argument follows a comma. src:site is the site itself (its home address), src:parent the parent, src:post,42 post 42, src:terms,category the post's first category, src:refs,related_posts the posts in that relationship, src:rows,team;refs,person each repeater row's linked post.",
		),
		'content'   => array(
			'use' => "Leave use out for the full content of what src names; use:excerpt prints the excerpt. The output is block-level markup (paragraphs, headings), so put the tag in a div or other block container, not inside a paragraph or heading. Example: {{content use:excerpt}}.",
		),
		'image'     => array(
			'use' => "Set use:featured to print the featured image of what src names; with src:site it is the site logo. It prints the image address; as:alt, as:id, as:title or as:caption print that instead. Examples: {{image use:featured}}, {{image src:parent|use:featured|as:alt}}.",
		),
		'table'     => array(
			'src' => "Where the table's rows come from; leave it out for one row, the current context. Each result is a row: src:terms,category is a row per category, src:refs,related_posts a row per related post. Steps join with semicolons and a step's argument follows a comma; src:parent is the parent. The table is block-level markup, so put the tag in a div, not inside a paragraph.",
			'A'   => "The first column; B, C and so on add more. header(Name) is the heading cell and use(title) prints each row's title or name. Example: {{table src:terms,category|A:header(Category);use(title)}}.",
		),
		'email'     => array(
			'src'   => null,
			'fixed' => "Set fixed to the email address to print, shown as a mailto link. Example: {{email fixed:info@example.com}}.",
		),
		'phone'     => array(
			'src'   => null,
			'fixed' => "Set fixed to the phone number to print, shown as a tel link. Example: {{phone fixed:555-123-4567}}.",
		),
	), bws_ai_syntax_try_hints() );
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
	return array_filter( array_merge( $hints['*'], $hints[ $tag ] ?? array() ), 'is_string' );
}
