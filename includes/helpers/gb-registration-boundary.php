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
 * WHAT CHANGED IN THE YIELD IS THAT IT SPEAKS, NOT THAT IT YIELDS. Each of those two sites
 * calls bws_gb_note_tag_yielded() at its dup-check, so a name we stand down from is recorded
 * and reported like the other two directions. Standing down silently meant a documented tag
 * simply did not exist with nothing anywhere saying why.
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
 * Record and report a tag name the modifier or fallback-chain constructor stood down from.
 *
 * THE THIRD DIRECTION, and the only one where nothing of ours ever runs. bws_gb_register_tag()
 * reports a name we took, bws_gb_recheck_tag_ownership() a name we were taken off; this
 * reports a name we never claimed. The YIELD ITSELF IS CORRECT AND IS NOT CHANGED BY THIS —
 * each constructor's own AXIS comment owns why standing down is right for its family. What
 * was wrong was that it was silent: `{{term_title}}` is documented as ours, a co-resident
 * plugin registering that name first means our tag does not exist, and until 1.19.0 the only
 * evidence anywhere was the tag missing from a dropdown.
 *
 * THE REMEDY IS THE THIRD ONE TOO, which is why the outcome is its own value rather than
 * folded into 'kept'. Nothing of ours is rendering wrongly and no page changed; the other
 * plugin's tag works. The only question is which of the two tags the site wants under that
 * name, and answering it means renaming or removing one of them.
 *
 * NOT A YIELD IF THE NAME IS ALREADY OURS. A constructor re-entered in the same request
 * finds its whole family taken — by itself — and would otherwise report every member as
 * yielded to a stranger. That an entry holding the callable we handed GB is still ours is a
 * consequence of the ownership rule; bws_gb_recheck_tag_ownership() states the axis.
 *
 * @since 1.19.0
 * @internal Called by the two template constructors at their dup-check, nowhere else.
 * @param string $tag      The name that was already taken.
 * @param mixed  $existing GB's registry entry for that name, or null when unavailable.
 * @return void
 */
function bws_gb_note_tag_yielded( string $tag, $existing = null ): void {
	$entry = is_array( $existing ) ? $existing : array();
	$ours  = bws_gb_tags_we_registered()[ $tag ] ?? null;

	if ( is_array( $ours ) && array_key_exists( 'return', $entry ) && $entry['return'] === $ours['return'] ) {
		return;
	}

	bws_gb_tag_name_collisions( array(
		'tag'             => $tag,
		'title'           => '',
		'outcome'         => 'yielded',
		'previous_title'  => (string) ( $entry['title'] ?? '' ),
		'previous_type'   => (string) ( $entry['type'] ?? '' ),
		'previous_source' => bws_gb_tag_registrar_file( $entry['return'] ?? null ),
	) );

	bws_gb_report_tag_collision( $tag );
}

/**
 * The tag name collisions this request ran into, in all three directions.
 *
 * Keyed by tag name, so a name holds ONE record however many times it is re-registered. The
 * notice channel matches that — bws_gb_report_tag_collision() owns the rule and states it.
 * Every record carries `tag`, `title` (ours) and `outcome`:
 *
 *   'kept'    — a stranger held the name when our pass reached it and we registered over
 *               them. Adds `previous_title`, `previous_type`, `previous_source`.
 *   'lost'    — the name was ours after our pass and is not ours now. Adds `later_title`,
 *               `later_type`, `later_source`.
 *   'yielded' — a stranger held the name and we stood down, so no tag of ours by that name
 *               exists. Adds the same `previous_*` fields as 'kept': the situation those
 *               describe is identical (who was here first), only our answer to it differs.
 *
 * `previous_source` / `later_source` is the file the other registration's callback is
 * defined in, relative to ABSPATH where that is known, or '' when reflection could not
 * answer.
 *
 * `title` IS OURS, AND IS '' ON A 'yielded' RECORD. On the other two outcomes we built a
 * registration and it has a title; on a yield we never built one, so there is no title of
 * ours to report and the tag name is the whole of what a reader has.
 *
 * THE THREE OUTCOMES ARE NOT ONE EVENT SEEN THREE TIMES, and a DEVELOPER must be able to
 * tell them apart because the remedies differ. On 'kept' the stranger's tag is the one that
 * stopped working, and whoever installed the stranger is the person to tell. On 'lost' OUR
 * tag stopped working, silently, on pages already using it — that is the failure the whole
 * boundary exists for, and the one a status surface must not round off to "a collision
 * happened". On 'yielded' nothing of ours rendered THIS REQUEST: a tag simply is not there,
 * which no other signal on the site says at all.
 *
 * AXIS — AN OUTCOME IS THIS REQUEST'S REGISTRATION ORDER, NOT THE SITE'S HISTORY. 'yielded'
 * says a stranger held the name when our pass reached it. It does NOT say that no block ever
 * rendered through our tag: a site that used {{term_title}} and then installed the stranger
 * records 'yielded' on every request afterwards, while its author lived through exactly the
 * takeover 'lost' describes. Nothing in the record separates the two, and nothing can — the
 * evidence that a takeover happened was the PREVIOUS request's registry, and it is gone.
 *
 * So a yield is not a guarantee that nothing broke; it is silence about whether anything
 * did. A surface reporting one to an AUTHOR carries the same warning 'lost' carries, and for
 * the same reason: if blocks already used this name, their output has moved and no other
 * signal says so. What stays true of a yield alone is the rest — no tag of ours exists, it
 * is absent from the editor, and documentation describing it does not apply here.
 *
 * WHICH IS WHY A SURFACE MAY SHOW FEWER STATES THAN THIS RECORDS. The remedies that separate
 * the outcomes are DEVELOPER remedies: rename a tag, move a registration off init priority
 * 20. A site owner has neither, and to them 'lost' and 'yielded' say one thing — another
 * plugin's code answers for this name and ours does not. The notice channel here needs
 * WP_DEBUG and a log file, so it reports all three; the settings page reports two and owns
 * that map (see SettingsPage's Tag Name Conflicts block). The record keeps three regardless:
 * the merge rule depends on the distinction, and a surface can always collapse what it is
 * given, while nothing can recover a distinction the record threw away.
 *
 * A 'yielded' RECORD IS EXCLUSIVE PER NAME BY CONSTRUCTION, so it needs no merge rule of
 * its own. A name we stood down from was never handed to GB, so bws_gb_register_tag() never
 * saw it ('kept' is impossible) and the late pass never looks at it ('lost' is impossible —
 * it iterates what we registered). First-write-wins is the whole of it.
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
 * ONE MESSAGE PER OUTCOME, NOT ONE PARAMETERIZED SENTENCE. The three outcomes are different
 * situations with different consequences, and the only clause they share is that a name was
 * claimed twice. A single sentence covering all of them would have to describe none: "a tag
 * name is registered twice" is true of every one and actionable for none.
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
 * AXIS — ONE NOTICE PER TAG PER OUTCOME, FOR THE LIFE OF THE REQUEST. Not once per call:
 * the yield sites call this from inside a constructor, and a constructor re-entered in the
 * same request meets the same taken name again, so an undeduped channel says the same
 * sentence twice about one event. A duplicate is pure noise in the one channel a developer
 * is reading, and noise there is what teaches someone to stop reading it.
 *
 * THE KEY IS TAG PLUS OUTCOME, AND THE OUTCOME HALF IS LOAD-BEARING. A name can legitimately
 * be reported twice in one request — we register OVER a stranger ('kept'), then a third
 * plugin takes the name from us ('lost'). Those are two events, the second is the dangerous
 * one, and a key of tag alone would swallow it. What repeats is a direction, and a direction
 * is what this suppresses. The record's own first-write-wins-except-the-'lost'-merge rule
 * makes the pair exactly "one report per distinct state the record has held".
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

	static $said = array();

	$outcome = (string) ( $collision['outcome'] ?? '' );
	$once    = $tag . '|' . $outcome;

	if ( isset( $said[ $once ] ) ) {
		return;
	}
	$said[ $once ] = true;

	// The other party the sentence names is chosen ONCE, at the accessor, for every surface
	// that reports a collision. Which field pair holds it is the record's business, not each
	// message's — this file used to answer it here and the settings page answered it again.
	$parties = bws_gb_collision_other_parties( $collision );
	$other   = $parties[ $parties['subject'] ];

	if ( 'lost' === $outcome ) {
		_doing_it_wrong(
			// Not a function name: WordPress prints this verbatim, so it says what happened.
			sprintf( "BWS GB dynamic tag '%s' taken over", $tag ),
			sprintf(
				/* translators: 1: dynamic tag name, 2: title and file of the code that took it over */
				__( 'The dynamic tag name "%1$s" is registered by BWS Dynamic Tag Extensions, and %2$s registered over it afterwards. Every block already using this tag now renders through that code instead, with nothing else to show that anything changed. Rename one of the two tags, or have the other plugin register before init priority 20 so the conflict resolves the other way.', 'generateblocks' ),
				$tag,
				bws_gb_other_registrar_phrase( $other['title'], $other['source'] )
			),
			'1.19.0'
		);

		return;
	}

	if ( 'yielded' === $outcome ) {
		_doing_it_wrong(
			// Says the consequence, not the mechanism: what a reader needs on line one is
			// that a tag they expected does not exist, which "collision" would not tell them.
			// The body carries the takeover warning as well, restating what the record's own
			// axis note says a yield is silent about — see bws_gb_tag_name_collisions().
			sprintf( "BWS GB dynamic tag '%s' not registered", $tag ),
			sprintf(
				/* translators: 1: dynamic tag name, 2: title and file of the code that registered it first */
				__( 'The dynamic tag name "%1$s" was already registered by %2$s, so BWS Dynamic Tag Extensions did not register its own tag of that name. The other tag is unaffected and keeps working; the BWS tag does not exist on this site and will not appear in the editor. If blocks here used this tag name before the other code was installed, they now render through it instead and their output may have changed. Rename or remove the other tag if you want the BWS one instead.', 'generateblocks' ),
				$tag,
				bws_gb_other_registrar_phrase( $other['title'], $other['source'] )
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
			bws_gb_other_registrar_phrase( $other['title'], $other['source'] )
		),
		'1.19.0'
	);
}

/**
 * The other parties to one collision record, and which of them its outcome is about.
 *
 * AXIS — WHICH FIELD PAIR NAMES A PARTY IS THE RECORD'S BUSINESS, AND IS ANSWERED HERE ONLY.
 * `previous_*` is whoever held the name when our registration pass reached it; `later_*` is
 * whoever took it after our pass. Every consumer wants one or both of those and none of them
 * should re-derive the mapping: the report sentences did, the settings page did it a second
 * time, and two copies of a mapping is where a fourth outcome quietly gets one of them wrong.
 *
 * BOTH PARTIES ARE RETURNED, BECAUSE A MERGED RECORD HAS TWO. Three plugins can contest one
 * name — a stranger holds it, we register over them, a third registers over us — and
 * bws_gb_tag_name_collisions() merges the `lost` finding onto the `kept` record precisely so
 * that both survive. A consumer that reads one pair and drops the other discards the half the
 * merge exists to preserve. `before`/`after` are null when the record does not carry that
 * pair, which is what distinguishes a merged record from a plain one; an entry that carries
 * the pair but could not name anybody is a pair of empty strings, not a null.
 *
 * `subject` NAMES THE PARTY THE OUTCOME'S SENTENCE IS ABOUT — the one in direct contest with
 * us. On 'kept' and 'yielded' that is who was here first; on 'lost' it is who took the name.
 * It is a key into this same array and its entry is never null, so a caller wanting one party
 * takes $parties[ $parties['subject'] ] with no branch of its own.
 *
 * @since 1.19.0
 * @internal
 * @param array $collision One entry from bws_gb_tag_name_collisions().
 * @return array{before:?array{title:string,source:string},after:?array{title:string,source:string},subject:string}
 */
function bws_gb_collision_other_parties( array $collision ): array {
	$pair = static function ( $title, $source ): array {
		return array(
			'title'  => (string) $title,
			'source' => (string) $source,
		);
	};

	$has_before = array_key_exists( 'previous_title', $collision ) || array_key_exists( 'previous_source', $collision );
	$has_after  = array_key_exists( 'later_title', $collision ) || array_key_exists( 'later_source', $collision );

	$parties = array(
		'before'  => $has_before ? $pair( $collision['previous_title'] ?? '', $collision['previous_source'] ?? '' ) : null,
		'after'   => $has_after ? $pair( $collision['later_title'] ?? '', $collision['later_source'] ?? '' ) : null,
		'subject' => 'lost' === ( $collision['outcome'] ?? '' ) ? 'after' : 'before',
	);

	// The subject entry is never null, so a caller can index it unguarded. Reaching this line
	// means a record was written without the pair its own outcome names, which no site here
	// does; an empty pair still produces the "another plugin" stand-in downstream.
	if ( null === $parties[ $parties['subject'] ] ) {
		$parties[ $parties['subject'] ] = $pair( '', '' );
	}

	return $parties;
}

/**
 * Best-effort: the human name of the plugin, theme or core directory a registrar file is in.
 *
 * AXIS — WHO OWNS A CONTESTED NAME IS DERIVED FROM THE FILE, AND ONLY HERE. GB's registry
 * cannot answer it: what it holds per tag is `title` — the other TAG's editor label, e.g.
 * "Term Title" — and the callable. A tag label in an owner slot reads as an owner
 * ("registered by Term Title") while naming nothing a reader can go and uninstall, so every
 * surface names the owner from bws_gb_tag_registrar_file()'s path instead, through this.
 *
 * BEST-EFFORT MEANS '', NEVER A GUESS. A path under no root below, a folder with no readable
 * plugin header, a registrar reflection could not open — all return '', and the caller falls
 * back to the file itself, which at least names something openable. A WRONG plugin name is
 * worse than none: it sends a reader to deactivate the wrong plugin.
 *
 * ONE FOLDER'S HEADERS, NOT get_plugins() ENTIRE. This runs on the notice path as well as
 * the settings page, and that path is a front-end request. `get_plugins( '/<folder>' )`
 * reads the headers of one directory. Results memoize per source path, because a settings
 * page renders several rows and a merged record names two parties.
 *
 * @since 1.19.0
 * @internal
 * @param string $source A path from bws_gb_tag_registrar_file(), or ''.
 * @return string A human name ("GB Query Enhancements"), or '' when it cannot be told.
 */
function bws_gb_registrar_plugin_name( string $source ): string {
	static $named = array();

	if ( '' === $source ) {
		return '';
	}

	if ( isset( $named[ $source ] ) ) {
		return $named[ $source ];
	}

	$named[ $source ] = '';
	$path             = strtr( $source, '\\', '/' );

	// bws_gb_tag_registrar_file() returns an ABSPATH-relative path when the file is under the
	// root and an absolute one otherwise, so re-absolutize before comparing against any root.
	$is_absolute = 0 === strpos( $path, '/' ) || 1 === preg_match( '#^[A-Za-z]:/#', $path );

	if ( ! $is_absolute ) {
		if ( ! defined( 'ABSPATH' ) ) {
			return '';
		}
		$path = rtrim( strtr( (string) ABSPATH, '\\', '/' ), '/' ) . '/' . ltrim( $path, '/' );
	}

	$roots = array();

	if ( defined( 'WP_PLUGIN_DIR' ) ) {
		$roots[] = array( 'kind' => 'plugin', 'dir' => (string) WP_PLUGIN_DIR );
	}
	if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
		$roots[] = array( 'kind' => 'mu', 'dir' => (string) WPMU_PLUGIN_DIR );
	}
	if ( function_exists( 'get_theme_root' ) ) {
		$roots[] = array( 'kind' => 'theme', 'dir' => (string) get_theme_root() );
	}
	if ( defined( 'ABSPATH' ) ) {
		$core    = rtrim( strtr( (string) ABSPATH, '\\', '/' ), '/' );
		$roots[] = array( 'kind' => 'core', 'dir' => $core . '/wp-includes' );
		$roots[] = array( 'kind' => 'core', 'dir' => $core . '/wp-admin' );
	}

	foreach ( $roots as $root ) {
		$rest = null;

		// THE REAL PATH IS TRIED TOO, because reflection reports the file it opened. A
		// symlinked plugins directory — normal enough that bws_gb_tag_registrar_file() already
		// returns absolute paths because of it — makes the constant and the file disagree.
		foreach ( array( $root['dir'], (string) @realpath( $root['dir'] ) ) as $candidate ) {
			$candidate = rtrim( strtr( (string) $candidate, '\\', '/' ), '/' );

			if ( '' === $candidate || 0 !== strpos( $path, $candidate . '/' ) ) {
				continue;
			}

			$rest = substr( $path, strlen( $candidate ) + 1 );
			break;
		}

		if ( null === $rest || '' === $rest ) {
			continue;
		}

		if ( 'core' === $root['kind'] ) {
			$named[ $source ] = __( 'WordPress core', 'generateblocks' );
			return $named[ $source ];
		}

		$slash  = strpos( $rest, '/' );
		$folder = false !== $slash ? substr( $rest, 0, $slash ) : '';

		if ( 'theme' === $root['kind'] ) {
			$theme = '' !== $folder && function_exists( 'wp_get_theme' ) ? wp_get_theme( $folder ) : null;
			$name  = null !== $theme ? (string) $theme->get( 'Name' ) : '';

			if ( '' !== $name ) {
				/* translators: %s: theme name */
				$named[ $source ] = sprintf( __( '%s (theme)', 'generateblocks' ), $name );
			}

			return $named[ $source ];
		}

		if ( ! function_exists( 'get_plugins' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$name = '';

		if ( '' !== $folder && 'plugin' === $root['kind'] && function_exists( 'get_plugins' ) ) {
			// The FIRST readable header in the folder. A plugin directory normally holds one
			// file with a Plugin Name; where it holds more, any of them names the same plugin
			// to a reader, and choosing between them would be a guess dressed as precision.
			foreach ( get_plugins( '/' . $folder ) as $data ) {
				$name = (string) ( $data['Name'] ?? '' );

				if ( '' !== $name ) {
					break;
				}
			}
		} elseif ( '' === $folder && function_exists( 'get_plugin_data' ) ) {
			// A single-file plugin, or a must-use file, sitting directly in the root.
			$data = get_plugin_data( rtrim( strtr( $root['dir'], '\\', '/' ), '/' ) . '/' . $rest, false, false );
			$name = (string) ( $data['Name'] ?? '' );
		}

		if ( '' !== $name && 'mu' === $root['kind'] ) {
			/* translators: %s: must-use plugin name */
			$name = sprintf( __( '%s (must-use)', 'generateblocks' ), $name );
		}

		$named[ $source ] = $name;

		return $named[ $source ];
	}

	return $named[ $source ];
}

/**
 * Name the other party in a collision, as far as GB's registry lets us.
 *
 * Shared by all three report directions: the identity of the other registrar is the one
 * thing they have in common, which is why this is the piece that was extracted and the
 * sentences were not. WHICH party that is comes from bws_gb_collision_other_parties().
 *
 * THE OWNER LEADS, THE TAG LABEL IS EVIDENCE BEHIND IT. `$title` is the other TAG's label,
 * not its owner, and this phrase used to lead with it — naming "Term Title" as the party
 * that registered a tag. bws_gb_registrar_plugin_name() answers the owner question from the
 * file; both remain in the sentence, because the label is what a reader sees in the editor
 * and the file is what they can open when the name resolves to nothing.
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
	$escape = static function ( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	};

	$evidence = array();

	if ( '' !== $title ) {
		$evidence[] = '"' . $escape( $title ) . '"';
	}
	if ( '' !== $source ) {
		$evidence[] = $escape( $source );
	}

	$owner = bws_gb_registrar_plugin_name( $source );

	if ( '' !== $owner ) {
		$owner = $escape( $owner );

		return $evidence ? $owner . ' (' . implode( ', ', $evidence ) . ')' : $owner;
	}

	if ( ! $evidence ) {
		return __( 'another plugin', 'generateblocks' );
	}

	// Owner unknown: the label and the file, in that order, exactly as this read before an
	// owner could be named at all.
	return count( $evidence ) > 1 ? $evidence[0] . ' (' . $evidence[1] . ')' : $evidence[0];
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
