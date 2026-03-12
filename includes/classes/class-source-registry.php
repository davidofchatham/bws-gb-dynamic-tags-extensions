<?php
/**
 * Source Registry - manages post source registrations.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
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
	 * Check if a source is enabled in admin settings.
	 *
	 * @param string $key Source key.
	 * @return bool
	 */
	public static function is_source_enabled( string $key ): bool {
		return Admin\SettingsPage::is_source_enabled( $key );
	}

	/**
	 * Get all resolvable "effective sources" — direct sources plus their related variants.
	 *
	 * Returns a flat map keyed by single-word effective source ID (e.g., 'post', 'related',
	 * 'term') used as `src_N` option values in try_ tags. Only currently-enabled related
	 * variants appear as selectable sources.
	 *
	 * @return array {
	 *     effective_source_id => [
	 *         'source'     => SourceInterface,
	 *         'is_related' => bool,
	 *         'label'      => string,
	 *     ]
	 * }
	 */
	public static function get_effective_sources(): array {
		$effective = array();

		foreach ( self::get_all_sources() as $source ) {
			// Direct source.
			$effective[ $source->get_effective_source_id() ] = array(
				'source'     => $source,
				'is_related' => false,
				'label'      => $source->get_title_prefix(),
			);

			// Related variant (only if source supports it and variants are enabled in settings).
			if ( $source->has_related_variant()
				&& Admin\SettingsPage::is_related_variant_enabled( $source->get_source_key() )
			) {
				$effective[ $source->get_related_effective_source_id() ] = array(
					'source'     => $source,
					'is_related' => true,
					'label'      => $source->get_related_title_prefix(),
				);
			}
		}

		return $effective;
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
		self::register_source( new Sources\TaxonomyTerm() );
		self::register_source( new Sources\SecondRelatedPost() );

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
