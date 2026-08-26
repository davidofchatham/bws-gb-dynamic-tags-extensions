<?php
/**
 * The boundary between this plugin's tag registration and GenerateBlocks' registrar.
 *
 * Every tag we ship reaches GB through one function here. GB's registrar is
 * `new GenerateBlocks_Register_Dynamic_Tag( $args )`, whose body ends in a bare
 * `self::$tags[ $args['tag'] ] = $args` -- no duplicate check, no warning, no return
 * value. A name another plugin already took is replaced silently, and a name it takes
 * after us replaces ours just as silently. This file owns what we do about that.
 *
 * The sibling file is gb-output-boundary.php: same kind of thing, the other GB entry
 * point we cross. One wrapper each, so the crossing has somewhere to be noticed.
 *
 * NOTHING HERE IS RE-INCLUDE GUARDED, matching that sibling. The plugin loads this once
 * with require_once, and the harness that loads it does the same; a `function_exists`
 * wrapper on each function would read as though the file were idempotent while saying
 * nothing about the two static stores below, which a second include would not duplicate
 * anyway.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.19.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register one dynamic tag with GenerateBlocks. THE plugin's only registration site.
 *
 * Call this instead of `new GenerateBlocks_Register_Dynamic_Tag( … )`. Routing every
 * registration through one function is what gives a name collision somewhere to be
 * noticed; the alternative is 13 call sites each of which could grow its own answer.
 *
 * AXIS — A TAKEN BASE-TAG NAME IS OVERWRITTEN, AND THE COLLISION IS REPORTED. Not "we
 * yield", and not "we overwrite quietly". Two separate decisions:
 *
 *   OVERWRITE, because for a base tag yielding is the worse failure. `{{text}}` is read by
 *   every block an author has pointed at it; if a co-resident plugin registered a `text`
 *   tag first and we stood down, every one of those blocks would render through someone
 *   else's callback, on a site where nothing changed but the plugin list, and nothing
 *   anywhere would say so. Overwriting breaks the newcomer's tag instead — the failure the
 *   person who just installed the newcomer can see and act on.
 *
 *   REPORT, because overwriting is the SAFER failure, not a harmless one. Until 1.19.0 it
 *   happened by accident: base tags register at init priority 20, later than most
 *   extensions, so we won every collision without anyone being told there was one.
 *
 * THE ASYMMETRY WITH THE OTHER TWO CONSTRUCTORS IS DELIBERATE — DO NOT "FIX" IT.
 * TagTemplateRegistry::register_modifier() (the `term_` half) and
 * TagTemplateRegistry::generate_base_try_tags() (the `try_` half) each read GB's registry
 * before building a tag and SKIP a name that is taken, yielding to the first registrar.
 * That is right for them and stays: those families are optional extras over the base tags,
 * so losing one to a name clash degrades the editor rather than breaking published pages.
 * Each of those two sites owns that rule and states it where it is enforced. Because they
 * skip before reaching here, a collision this function sees is a base-tag collision.
 *
 * THIS FUNCTION IS INTERNAL, AND STAYS INTERNAL. It has no row in
 * docs/plugin-integration.md §5, and §4 Option B still tells an integrator to construct
 * GB's registrar directly, which is correct for them: their tag is not one of ours to
 * overwrite or to report on. No caller outside this repo exists, and publishing an entry
 * point buys a compatibility obligation on speculation — the same reasoning that dropped
 * `bws_dynamic_tags_owned_tags` in this release. Revisit with a real external caller in
 * hand, not before.
 *
 * WHAT THE REPORT COSTS, AND WHY THAT IS ACCEPTED. `_doing_it_wrong()` fires inside the
 * registration pass, which runs on every request, so a site carrying a live collision
 * reports it on every request rather than once. That is the channel's own design: WordPress
 * gates the visible notice on WP_DEBUG (through the `doing_it_wrong_trigger_error` filter),
 * so a production site logs nothing while a development site logs the collision every time
 * anyone loads a page — which is exactly when someone is looking for it. Both alternatives
 * are worse: a once-a-day transient goes quiet at the moment a developer is debugging, and
 * an admin-only notice never sees the front end. The per-request cost when there is NO
 * collision is one `get_tags()` array read per tag.
 *
 * THE OTHER DIRECTION IS NOT SEEN HERE, and it is no longer unseen: something registering
 * our name AFTER init priority 20 overwrites us, and this function has already run. That
 * case is caught by bws_gb_recheck_tag_ownership() on `wp_loaded` — the reverse arm, and
 * the more dangerous of the two.
 *
 * @since 1.19.0
 * @param array $args Registration arguments, exactly as GB's constructor takes them.
 */
function bws_gb_register_tag( array $args ): void {
	$tag = isset( $args['tag'] ) ? (string) $args['tag'] : '';

	// GB's constructor drops an incomplete registration on the floor -- its first statement
	// returns unless `tag`, `return` and `title` are all set. Mirror that rather than
	// reporting a collision for, or claiming ownership of, a tag that will not exist.
	if ( '' === $tag || ! isset( $args['return'], $args['title'] ) ) {
		new \GenerateBlocks_Register_Dynamic_Tag( $args );
		return;
	}

	$existing = \GenerateBlocks_Register_Dynamic_Tag::get_tags();

	if ( isset( $existing[ $tag ] ) ) {
		bws_gb_tag_name_collisions( array(
			'tag'             => $tag,
			'title'           => (string) $args['title'],
			'outcome'         => 'kept',
			'previous_title'  => (string) ( $existing[ $tag ]['title'] ?? '' ),
			'previous_type'   => (string) ( $existing[ $tag ]['type'] ?? '' ),
			'previous_source' => bws_gb_tag_registrar_file( $existing[ $tag ]['return'] ?? null ),
		) );

		bws_gb_report_tag_collision( $tag );
	}

	new \GenerateBlocks_Register_Dynamic_Tag( $args );

	// What we handed GB, so the late pass can tell whether it is still what GB holds.
	bws_gb_tags_we_registered( array(
		'tag'    => $tag,
		'title'  => (string) $args['title'],
		'return' => $args['return'],
	) );
}

/**
 * The tags this request's registration pass handed to GB, and the callable each carried.
 *
 * Written by bws_gb_register_tag() only. Exists so bws_gb_recheck_tag_ownership() has
 * something to compare GB's registry against: GB keeps one entry per name and no memory of
 * what that name held before, so after someone overwrites us the registry alone cannot say
 * that anything happened.
 *
 * LAST WRITE WINS PER NAME, deliberately. If we register the same name twice in one pass,
 * the second is the one GB holds and therefore the one the late pass must compare against.
 *
 * @since 1.19.0
 * @internal
 * @param array $record A registration to record. Omit to read.
 * @return array<string,array{tag:string,title:string,return:mixed}>
 */
function bws_gb_tags_we_registered( array $record = array() ): array {
	static $ours = array();

	if ( isset( $record['tag'] ) ) {
		$ours[ $record['tag'] ] = $record;
	}

	return $ours;
}

/**
 * Re-read GB's registry late and record any of our tag names that is no longer ours.
 *
 * THE CASE bws_gb_register_tag() CANNOT SEE. Our pass runs at init priority 20; anything
 * registering one of our names after that overwrites us, and the overwrite is invisible at
 * the moment it happens — GB neither checks nor announces, and our registration function
 * has long returned. This is the reverse of the collision that function reports and the
 * worse one: `{{text}}` keeps rendering, on every page that already used it, through
 * somebody else's callback.
 *
 * AXIS — A TAG IS STILL OURS IFF THE CALLABLE GB HOLDS FOR IT IS IDENTICALLY THE ONE WE
 * HANDED OVER. Not the title, not the option array: the callable is what runs at render, so
 * it is the thing whose replacement changes what a page outputs, and it is the only field
 * of the registration a stranger cannot coincidentally match. Identity is `===` against the
 * value recorded at registration, which is exact for every shape we pass — our tags
 * register either a function-name string (`'bws_base_text_callback'`) or a Closure built
 * once during the pass, and `===` compares a string by value and a Closure by object
 * identity. Nothing mutates GB's entries in place: its registry is a private static array
 * written only by the constructor, so a differing callable means a re-registration and
 * nothing else. A name that has vanished from the registry entirely counts as lost too.
 *
 * ONE PASS OVER WHAT WE REGISTERED, on `wp_loaded` at PHP_INT_MAX. No transient: the
 * finding is about the plugin set live in THIS request, and a stored copy would outlive the
 * collision and keep reporting one after the other plugin was deactivated.
 *
 * WHY `wp_loaded`. It is the last hook that fires on every request type — front end, admin,
 * AJAX, REST, cron and WP-CLI alike — and it fires after `init` has run to completion, so
 * it sees every registrar that hooked `init` at any priority above ours as well as anything
 * registering on `wp_loaded` itself below PHP_INT_MAX. `init` at PHP_INT_MAX would be
 * marginally earlier and blind to the second group; `admin_init` and `template_redirect`
 * are later but do not fire on every request. The remaining blind spot is a plugin that
 * registers a tag later still (on `template_redirect`, say). Its tag would work — GB reads
 * the registry at render — and we would not report it. Catching that needs a check at
 * render time, per tag, per request, which is not worth what it costs.
 *
 * @since 1.19.0
 * @return void
 */
function bws_gb_recheck_tag_ownership(): void {
	$ours = bws_gb_tags_we_registered();

	if ( ! $ours || ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}

	$live = \GenerateBlocks_Register_Dynamic_Tag::get_tags();

	foreach ( $ours as $tag => $mine ) {
		$now = isset( $live[ $tag ] ) && is_array( $live[ $tag ] ) ? $live[ $tag ] : null;

		if ( null !== $now && array_key_exists( 'return', $now ) && $now['return'] === $mine['return'] ) {
			continue;
		}

		bws_gb_tag_name_collisions( array(
			'tag'          => (string) $tag,
			'title'        => (string) ( $mine['title'] ?? '' ),
			'outcome'      => 'lost',
			'later_title'  => null === $now ? '' : (string) ( $now['title'] ?? '' ),
			'later_type'   => null === $now ? '' : (string) ( $now['type'] ?? '' ),
			'later_source' => null === $now ? '' : bws_gb_tag_registrar_file( $now['return'] ?? null ),
		) );

		bws_gb_report_tag_collision( (string) $tag );
	}
}

/**
 * The base-tag name collisions this request ran into, in both directions.
 *
 * Keyed by tag name, so a name is reported once however many times it is re-registered.
 * Every record carries `tag`, `title` (ours) and `outcome`:
 *
 *   'kept' — a stranger held the name when our pass reached it and we registered over
 *            them. Adds `previous_title`, `previous_type`, `previous_source`.
 *   'lost' — the name was ours after our pass and is not ours now. Adds `later_title`,
 *            `later_type`, `later_source`.
 *
 * `previous_source` / `later_source` is the file the other registration's callback is
 * defined in, relative to ABSPATH where that is known, or '' when reflection could not
 * answer.
 *
 * THE TWO OUTCOMES ARE NOT ONE EVENT SEEN TWICE, and a reader must be able to tell them
 * apart because the remedies differ. On 'kept' the stranger's tag is the one that stopped
 * working, and whoever installed the stranger is the person to tell. On 'lost' OUR tag
 * stopped working, silently, on pages already using it — that is the failure the whole
 * boundary exists for, and the one a status surface must not round off to "a collision
 * happened".
 *
 * A 'lost' FINDING REFINES AN EXISTING RECORD RATHER THAN BEING DROPPED. Three plugins can
 * contest one name: we register over the first, a third registers over us. The final owner
 * is what a reader needs, so the late pass merges onto whatever the registration pass
 * recorded and the earlier `previous_*` fields survive beside the new `later_*` ones. In
 * the other direction the first write stands, so re-registering a name we already hold
 * cannot overwrite a real collision record with ourselves as the "previous" registrar.
 *
 * A QUERYABLE RECORD RATHER THAN ONLY A FIRED NOTICE, because the two report surfaces have
 * different audiences and only one of them is a log. `_doing_it_wrong()` reaches a
 * developer with WP_DEBUG on; the settings page reaches whoever is asking why their site's
 * output changed, on a production install where nothing is logged at all. Reading GB's
 * registry back later cannot answer it — the registry holds one entry per name and no
 * memory of the other.
 *
 * REQUEST-SCOPED ON PURPOSE, no transient. Registration runs on init:20 of every request
 * including admin ones, and the late pass on `wp_loaded` of the same request, so anything
 * rendered after that (the settings page included) reads a record built by the plugin set
 * that is live right now. A stored copy would outlive the collision.
 *
 * NO FILTER OVER THIS. Consumers are in-repo; a public filter with no caller outside is a
 * maintenance obligation bought on speculation (the same reasoning that dropped
 * `bws_dynamic_tags_owned_tags` in this release).
 *
 * @since 1.19.0
 * @internal $record is how the two detection sites write; readers call with no arguments.
 * @param array $record A collision to record. Omit to read.
 * @return array<string,array<string,string>>
 */
function bws_gb_tag_name_collisions( array $record = array() ): array {
	static $collisions = array();

	if ( isset( $record['tag'] ) ) {
		$tag = (string) $record['tag'];

		if ( ! isset( $collisions[ $tag ] ) ) {
			$collisions[ $tag ] = $record;
		} elseif ( 'lost' === ( $record['outcome'] ?? '' ) ) {
			$collisions[ $tag ] = array_merge( $collisions[ $tag ], $record );
		}
	}

	return $collisions;
}

/**
 * Report one recorded collision through WordPress's incorrect-usage channel.
 *
 * TWO MESSAGES, NOT ONE PARAMETERIZED SENTENCE. The outcomes are opposite situations with
 * opposite consequences, and the only clause they share is the remedy. A single sentence
 * covering both would have to describe neither: "a tag name is registered twice" is true of
 * both and actionable for neither.
 *
 * THE SUBJECT NAMES THE COLLISION, NOT US. WordPress renders the first argument as
 * "Function %s was called incorrectly", so passing __FUNCTION__ puts `bws_gb_register_tag`
 * in the headline of a log line about another plugin's registration — an accusation against
 * our own code for an event we are only the observer of. WP accepts any string there and
 * nothing resolves it as a symbol, so it carries the situation and the tag name instead,
 * which is what someone scanning a debug log needs on line one.
 *
 * THE ONLY WORDPRESS FUNCTION THIS DEPENDS ON IS `_doing_it_wrong` ITSELF, and the guard
 * below now says so truthfully. Registration also runs in tools/test/control-order-test.php,
 * which boots no WordPress; `__()` is already stubbed there because every option label in
 * the registration pass goes through it, so using it here adds no dependency. esc_html()
 * would have added one — nothing else in registration calls it — so the escaping is
 * htmlspecialchars(), a PHP builtin. That also keeps the `esc_html` filter and the
 * blog_charset option off the path of a string headed for a log file, and neither of those
 * is something this line wants.
 *
 * @since 1.19.0
 * @internal
 * @param string $tag The tag name whose recorded collision to report.
 * @return void
 */
function bws_gb_report_tag_collision( string $tag ): void {
	if ( ! function_exists( '_doing_it_wrong' ) ) {
		return;
	}

	$collision = bws_gb_tag_name_collisions()[ $tag ] ?? null;

	if ( ! is_array( $collision ) ) {
		return;
	}

	if ( 'lost' === ( $collision['outcome'] ?? '' ) ) {
		_doing_it_wrong(
			// Not a function name: WordPress prints this verbatim, so it says what happened.
			sprintf( "BWS GB dynamic tag '%s' taken over", $tag ),
			sprintf(
				/* translators: 1: dynamic tag name, 2: title and file of the code that took it over */
				__( 'The dynamic tag name "%1$s" is registered by BWS Dynamic Tag Extensions, and %2$s registered over it afterwards. Every block already using this tag now renders through that code instead, with nothing else to show that anything changed. Rename one of the two tags, or have the other plugin register before init priority 20 so the conflict resolves the other way.', 'generateblocks' ),
				$tag,
				bws_gb_other_registrar_phrase( (string) ( $collision['later_title'] ?? '' ), (string) ( $collision['later_source'] ?? '' ) )
			),
			'1.19.0'
		);

		return;
	}

	_doing_it_wrong(
		sprintf( "BWS GB dynamic tag '%s' collision", $tag ),
		sprintf(
			/* translators: 1: dynamic tag name, 2: title and file of the code that registered it first */
			__( 'The dynamic tag name "%1$s" was already registered by %2$s. BWS Dynamic Tag Extensions has registered over it, because a base tag that stood down would stop rendering everywhere it is already used. The other tag of this name will not render. Rename one of the two tags to clear this.', 'generateblocks' ),
			$tag,
			bws_gb_other_registrar_phrase( (string) ( $collision['previous_title'] ?? '' ), (string) ( $collision['previous_source'] ?? '' ) )
		),
		'1.19.0'
	);
}

/**
 * Name the other party in a collision, as far as GB's registry lets us.
 *
 * Shared by both report directions: the identity of the other registrar is the one thing
 * they have in common, which is why this is the piece that was extracted and the sentences
 * were not.
 *
 * The title and the file both come from another plugin, so both are escaped here, where
 * they enter our message. The finished sentence is deliberately NOT escaped as a whole —
 * that would turn the quotation marks framing them into entities, and this message's
 * likeliest reader is a log file rather than a rendered admin notice.
 *
 * @since 1.19.0
 * @internal
 * @param string $title  The other registration's title, or ''.
 * @param string $source The file its callback is defined in, or ''.
 * @return string A phrase naming them, or a translated stand-in when neither is known.
 */
function bws_gb_other_registrar_phrase( string $title, string $source ): string {
	$phrase = '' !== $title ? '"' . htmlspecialchars( $title, ENT_QUOTES, 'UTF-8' ) . '"' : '';

	if ( '' !== $source ) {
		$where  = htmlspecialchars( $source, ENT_QUOTES, 'UTF-8' );
		$phrase = '' !== $phrase ? $phrase . ' (' . $where . ')' : $where;
	}

	return '' !== $phrase ? $phrase : __( 'another plugin', 'generateblocks' );
}

/**
 * Best-effort: the file a registered tag's return callback is defined in.
 *
 * Answers "which plugin claimed this name" with the one piece of evidence GB's registry
 * actually holds — the callable. Runs on a collision only, so the reflection is never on a
 * normal request's path. Returns '' rather than guessing when the callable is not a shape
 * reflection can open.
 *
 * EVERY BRANCH VERIFIES ITS OWN SHAPE BEFORE INDEXING. The callable comes from another
 * plugin's registration array, so `is_array()` plus a count of 2 is not enough to reach
 * `$callback[0]`: a two-element string-keyed array satisfies both and warns on the index,
 * outside anything the try/catch can absorb — a PHP warning is not a Throwable.
 *
 * @since 1.19.0
 * @internal
 * @param mixed $callback The `return` entry of a GB tag registration.
 * @return string Path relative to ABSPATH where possible, else absolute, else ''.
 */
function bws_gb_tag_registrar_file( $callback ): string {
	try {
		if ( is_array( $callback ) && 2 === count( $callback ) && isset( $callback[0], $callback[1] ) && is_string( $callback[1] ) ) {
			$ref = new \ReflectionMethod( $callback[0], $callback[1] );
		} elseif ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
			$ref = new \ReflectionMethod( $callback );
		} elseif ( $callback instanceof \Closure || ( is_string( $callback ) && function_exists( $callback ) ) ) {
			$ref = new \ReflectionFunction( $callback );
		} else {
			return '';
		}
		$file = (string) $ref->getFileName();
	} catch ( \Throwable $e ) {
		return '';
	}

	if ( '' === $file ) {
		return '';
	}
	$file = strtr( $file, '\\', '/' );

	if ( defined( 'ABSPATH' ) ) {
		$root = strtr( (string) ABSPATH, '\\', '/' );
		if ( '' !== $root && 0 === strpos( $file, $root ) ) {
			return substr( $file, strlen( $root ) );
		}
	}

	return $file;
}
