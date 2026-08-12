<?php
/**
 * Source Registry - manages post source registrations.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 * @since 1.17.0 Chain roots: get_selectable_roots() + the `bws_dynamic_tags_chain_roots`
 *               filter route (#83).
 */

namespace BWS\DynamicTags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SourceRegistry {

	/** @var SourceInterface[] */
	private static array $sources = [];

	/** @var bool */
	private static bool $initialized = false;

	/**
	 * Register a new source.
	 *
	 * @param SourceInterface $source Source implementation.
	 */
	public static function register_source( SourceInterface $source ): void {
		$key = $source->get_source_key();
		self::$sources[ $key ] = $source;

		if ( Admin\SettingsPage::is_registration_logging_enabled() ) {
			error_log( sprintf(
				'[BWS Dynamic Tags] Source registered: key="%s" class="%s"',
				$key,
				get_class( $source )
			) );
		}
	}

	/**
	 * Get a registered source by key.
	 *
	 * @param string $key Source key.
	 * @return SourceInterface|null
	 */
	public static function get_source( string $key ): ?SourceInterface {
		return self::$sources[ $key ] ?? null;
	}

	/**
	 * Get all registered sources.
	 *
	 * @return SourceInterface[]
	 */
	public static function get_all_sources(): array {
		return self::$sources;
	}

	/**
	 * Check if a source is enabled.
	 *
	 * Term-context sources are gated on the term_ modifier toggle.
	 * All other sources are always enabled.
	 *
	 * @since 1.6.0 Delegates term-context check to is_modifier_enabled('term').
	 * @param string $key Source key.
	 * @return bool
	 */
	public static function is_source_enabled( string $key ): bool {
		$source = self::get_source( $key );
		if ( $source && 'term' === $source->get_context_type() ) {
			return Admin\SettingsPage::is_modifier_enabled( 'term' );
		}
		return true;
	}

	/**
	 * The registered sources an author may CHOOSE as a chain root (#83).
	 *
	 * The ONE answer both authoring surfaces take their rows from — the base tag's root
	 * enum and the folded slot's source enum, via bws_registered_root_rows(). One accessor
	 * so a root cannot be offered in one surface and absent from the other.
	 *
	 * TWO gates, and they are different questions. `is_selectable_root()` is the source's
	 * own claim that it may be chosen; `is_source_enabled()` is this site's settings
	 * saying whether that whole context is switched on — a term-context root disappears
	 * with the term_ modifier toggle exactly as every other term surface does. Neither
	 * gate reaches RESOLUTION: wire naming any registered source resolves through
	 * bws_factory_registry_source() whichever way both answer.
	 *
	 * @since 1.17.0
	 * @return SourceInterface[] Keyed by source key, in registration order.
	 */
	public static function get_selectable_roots(): array {
		$roots = array();
		foreach ( self::$sources as $key => $source ) {
			if ( $source->is_selectable_root() && self::is_source_enabled( $key ) ) {
				$roots[ $key ] = $source;
			}
		}
		return $roots;
	}

	/**
	 * Adapt the declarative root specs from `bws_dynamic_tags_chain_roots` into sources.
	 *
	 * Fires at REGISTRATION, never at enum-build time. A row added when the enum is built
	 * would exist for the editor and not for the renderer, and the token would then fall
	 * through to the ambient entity — an offered source silently reading something else.
	 * Registering keeps both consumers correct by construction.
	 *
	 * Called AFTER the register_sources action, so every class-route source already
	 * exists and a colliding key is IGNORED rather than overwritten: a plugin that ships
	 * a real source class must not have it shadowed by a spec someone else declared.
	 *
	 * NO $context ARGUMENT, deliberately. There is no tag, block or container in
	 * existence when this fires, so the parameter would ship empty; WordPress passes
	 * arguments positionally by registered arity, so adding one later stays backward
	 * compatible.
	 *
	 * @since 1.17.0
	 */
	private static function register_filtered_roots(): void {
		if ( ! function_exists( 'apply_filters' ) ) {
			return;
		}

		/**
		 * Declare chain roots without writing a source class.
		 *
		 *     add_filter( 'bws_dynamic_tags_chain_roots', function( $roots ) {
		 *         $roots['view'] = array(
		 *             'label'   => __( 'View', 'my-plugin' ),      // required, author-facing
		 *             'context' => 'post',                         // 'post'|'term', default 'post'
		 *             'resolve' => 'my_plugin_current_view_id',    // callable( $options, $instance )
		 *         );
		 *         return $roots;
		 *     } );
		 *
		 * Everything declared here IS a root — that is what the filter is named for, so
		 * there is no offerability flag to set. A source that should NOT be offerable
		 * uses the `bws_dynamic_tags_register_sources` action instead.
		 *
		 * @since 1.17.0
		 * @param array $roots Source key => { label, context, resolve } spec.
		 */
		$specs = apply_filters( 'bws_dynamic_tags_chain_roots', array() );
		if ( ! is_array( $specs ) ) {
			return;
		}

		foreach ( $specs as $key => $spec ) {
			$key = is_string( $key ) ? trim( $key ) : '';
			if ( '' === $key || isset( self::$sources[ $key ] ) || ! is_array( $spec ) ) {
				continue;
			}
			$label   = isset( $spec['label'] ) ? (string) $spec['label'] : '';
			$resolve = $spec['resolve'] ?? null;
			// A spec with no label has no dropdown row to be, and one with no resolver
			// would offer a root that answers nothing. Both are skipped rather than
			// registered half-formed.
			if ( '' === $label || ! is_callable( $resolve ) ) {
				continue;
			}
			self::register_source( new Sources\CallbackRoot(
				$key,
				$label,
				(string) ( $spec['context'] ?? 'post' ),
				$resolve
			) );
		}
	}

	/**
	 * Initialize built-in sources and fire registration hook.
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;

		// Capture any sources already registered before init() runs (direct calls or
		// plugins_loaded priority < 20). These are external sources registered early.
		$pre_init_keys = array_keys( self::$sources );

		// Register built-in sources.
		self::register_source( new Sources\CurrentPost() );
		// Post → Rel. Post: promoted to standalone source (Pattern B).
		self::register_source( new Sources\RelatedPost() );
		self::register_source( new Sources\SecondRelatedPost() );
		self::register_source( new Sources\PostTermRelatedPost() );
		self::register_source( new Sources\TaxonomyTerm() );
		// Term → Rel. Post: new standalone source (Pattern B).
		self::register_source( new Sources\TermRelatedPost() );

		$count_before = count( self::$sources );

		if ( Admin\SettingsPage::is_registration_logging_enabled() ) {
			// WP_Hook doesn't implement Countable on PHP 8.x — count its callbacks array directly.
			$wp_hook      = $GLOBALS['wp_filter']['bws_dynamic_tags_register_sources'] ?? null;
			$listeners    = $wp_hook instanceof \WP_Hook ? count( $wp_hook->callbacks ) : 0;
			$pre_init_desc = empty( $pre_init_keys ) ? 'none' : implode( ', ', $pre_init_keys );
			error_log( sprintf(
				'[BWS Dynamic Tags] Firing bws_dynamic_tags_register_sources (%d listener group(s); pre-init externals: %s)',
				$listeners,
				$pre_init_desc
			) );
		}

		/**
		 * Fires after built-in sources are registered.
		 *
		 * External plugins can register sources in two ways:
		 *
		 * A) Hook this action (listener must be registered before plugins_loaded priority 20):
		 *       add_action( 'bws_dynamic_tags_register_sources', function() {
		 *           SourceRegistry::register_source( new MySource() );
		 *       } );
		 *    Add the add_action() call at plugin file-load time (not inside a plugins_loaded
		 *    callback), so the listener is in place before this action fires.
		 *
		 * B) Call register_source() directly at plugins_loaded priority < 20:
		 *       add_action( 'plugins_loaded', function() {
		 *           SourceRegistry::register_source( new MySource() );
		 *       }, 15 );
		 *    Safe on all PHP versions. Preferred when the external plugin has complex init
		 *    timing or when the source may already be registered before this action fires.
		 *
		 * @since 1.0.0
		 * @param SourceRegistry $registry The registry instance (for static method access).
		 */
		do_action( 'bws_dynamic_tags_register_sources', new self() );

		// The FILTER route, last: class-route registrations win a key collision (#83).
		self::register_filtered_roots();

		if ( Admin\SettingsPage::is_registration_logging_enabled() ) {
			$action_added = count( self::$sources ) - $count_before;
			error_log( sprintf(
				'[BWS Dynamic Tags] bws_dynamic_tags_register_sources complete: %d pre-init + %d action-registered external source(s), total=%d (keys: %s)',
				count( $pre_init_keys ),
				$action_added,
				count( self::$sources ),
				implode( ', ', array_keys( self::$sources ) )
			) );
		}
	}
}
