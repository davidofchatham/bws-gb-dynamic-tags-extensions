<?php
/**
 * Link-wrap helpers.
 *
 * Resolves link URLs from linkTo/linkKey options and wraps output in <a> elements.
 * Also handles back-compat mapping from GB-native `link` option (deprecated N×M tags).
 *
 * @package BWS_Dynamic_Tags
 * @since 1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve a link URL for output wrapping.
 *
 * Routes by $link_to destination and $entity_type:
 *   'permalink' → get_permalink() for posts, get_term_link() for terms,
 *                 home_url() for site (the site permalink-analog; $id is a sentinel).
 *   'key'       → get_post_meta() for posts, get_term_meta() for terms, using $link_key.
 *                 For $entity_type 'site' → bws_site_read_option($link_key), the SAME
 *                 canonical gated reader the site value path uses (V2: reads MUST agree).
 * Always returns '' (never false/null) so callers can unconditionally skip wrapping
 * without blocking tag output. Empty $link_key with linkTo:'key' returns '' immediately.
 *
 * INVARIANT: 'link' must never be used as a GB option key by this plugin — GB strips it
 * before custom controls see it and fires its own with_link(). All link-wrapping routes
 * through this function and bws_wrap_with_link().
 *
 * @invariant Site link-wrap = permalink-analog (SPEC V-link). 'site' is an $entity_type,
 *            NOT a $link_to value — there is no 'site' linkTo token. linkTo:permalink under
 *            $entity_type 'site' resolves home_url(), identical to the bare {{permalink
 *            src:site}} analog in bws_site_resolve_value (the two reads MUST agree). The
 *            'site' guard MUST sit BEFORE the post/term permalink reads: site callbacks pass
 *            sentinel $id=1, so an unguarded fall-through hits get_permalink(1) = wrong post.
 *            No separate linkTo:site value (V9 corollary: permalink already IS the site
 *            canonical URL — no duplicate path per datum).
 *
 * @since 1.7.0
 * @param string $link_to     Destination token: 'permalink' | 'key'.
 * @param string $link_key    Meta key (used when $link_to = 'key').
 * @param int    $id          Entity ID (post ID or term ID; sentinel 1 for site).
 * @param string $entity_type Entity type: 'post' | 'term' | 'site'.
 * @return string Resolved URL, or empty string on failure.
 */
if ( ! function_exists( 'bws_resolve_link_url' ) ) {
function bws_resolve_link_url( string $link_to, string $link_key, int $id, string $entity_type ): string {
	if ( ! $id || 'none' === $link_to || '' === $link_to ) {
		return '';
	}

	if ( 'permalink' === $link_to ) {
		// Site permalink-analog IS the home URL — no entity to resolve (sentinel $id).
		if ( 'site' === $entity_type ) {
			return (string) home_url();
		}
		if ( 'term' === $entity_type ) {
			$url = get_term_link( $id );
			return ( ! is_wp_error( $url ) && $url ) ? (string) $url : '';
		}
		$url = get_permalink( $id );
		return $url ? (string) $url : '';
	}

	if ( 'key' === $link_to ) {
		if ( '' === $link_key ) {
			return '';
		}
		if ( 'site' === $entity_type ) {
			// Option-stored URL. Route through the SAME canonical reader as the
			// site key-mode value read (V2 — the two reads MUST agree): allowlist
			// gate (ADR 0001) + dot-path traversal + ACF get_field filter. Raw
			// get_option() reaches none of those, so ACF-group subfields
			// (e.g. organization_social.facebook) would resolve empty here.
			// bws_site_read_option lives in field-helpers.php (loaded first).
			return bws_site_read_option( $link_key );
		}
		if ( 'term' === $entity_type ) {
			$url = get_term_meta( $id, $link_key, true );
		} else {
			$url = get_post_meta( $id, $link_key, true );
		}
		return ( $url && is_string( $url ) ) ? $url : '';
	}

	return '';
}
}

/**
 * Wrap output string in an anchor element when a link URL resolves.
 *
 * Invariants enforced here:
 *   - Empty $output, linkTo 'none'/'' → return $output unchanged (never blocks output).
 *   - Empty linkKey with linkTo:'key' → bws_resolve_link_url returns '' → no wrap.
 *   - target="_blank" only emitted when URL resolves non-empty (never a bare target attr).
 *   - Applied to the final resolved string — fallback text is also wrapped when present.
 *
 * @since 1.7.0
 * @param string $output      Tag output to wrap (final resolved string including fallback).
 * @param string $link_to     Destination token from linkTo option ('none'|'permalink'|'key').
 * @param string $link_key    Meta key from linkKey option (used when link_to='key').
 * @param bool   $new_tab     Whether to add target="_blank" rel="noopener noreferrer".
 * @param int    $id          Resolved entity ID.
 * @param string $entity_type 'post' | 'term'.
 * @return string Wrapped output, or original $output if no URL resolved.
 */
if ( ! function_exists( 'bws_wrap_with_link' ) ) {
function bws_wrap_with_link( string $output, string $link_to, string $link_key, bool $new_tab, int $id, string $entity_type ): string {
	if ( '' === $output || 'none' === $link_to || '' === $link_to ) {
		return $output;
	}

	$url = bws_resolve_link_url( $link_to, $link_key, $id, $entity_type );
	if ( '' === $url ) {
		return $output;
	}

	$attrs = ' href="' . esc_url( $url ) . '"';
	if ( $new_tab ) {
		$attrs .= ' target="_blank" rel="noopener noreferrer"';
	}

	return '<a' . $attrs . '>' . $output . '</a>';
}
}

/**
 * Return the three link-wrap option definitions for eligible templates.
 *
 * Eligible templates: text, title, datetime_single, datetime_range (base, term_, try_ variants).
 * Excluded: content, permalink, image (no supports_link_wrap flag → never injected).
 *
 * linkTo  — select; canonical first value 'none' stripped at registration via _strip_default
 *           so absence = no link. Callbacks recover canonical token via ?? 'none'.
 * linkKey — text; shown only when linkTo:'key'. Empty → wrap skipped, output unchanged.
 * newTab  — boolean presence-flag; shown only when linkTo not empty.
 *           Emits target="_blank" rel="noopener noreferrer" only when URL resolves.
 *
 * try_ tags: single linkTo/linkKey applies to winning slot's entity (post or term).
 * term_ modifier tags: entity type routed from dispatch path — term for base-source,
 * post for src:ref traversal, term for srcTermIn hop.
 *
 * @since 1.7.0
 * @return array Option definitions keyed by option name.
 */
if ( ! function_exists( 'bws_get_link_options' ) ) {
function bws_get_link_options(): array {
	return array(
		'linkTo'  => array(
			'type'           => 'select',
			'label'          => __( 'Link To', 'generateblocks' ),
			'options'        => array(
				array( 'value' => 'none',      'label' => __( 'No Link', 'generateblocks' ) ),
				array( 'value' => 'permalink', 'label' => __( 'Permalink', 'generateblocks' ) ),
				array( 'value' => 'key',       'label' => __( 'URL Meta Field', 'generateblocks' ) ),
			),
			'_strip_default' => true,
		),
		'linkKey' => array(
			'type'    => 'text',
			'label'   => __( 'Link URL Field', 'generateblocks' ),
			'help'    => __( 'Meta field key whose value is used as the link URL. For try_ tags, this field is read from the source that produced the output.', 'generateblocks' ),
			'show_if' => array( 'linkTo' => 'key' ),
		),
		'newTab'  => array(
			'type'    => 'checkbox',
			'label'   => __( 'Open in new tab', 'generateblocks' ),
			'show_if' => array( 'linkTo' => 'not_empty' ),
		),
	);
}
}

/**
 * Remap a GB-native `link` option (from deprecated N×M tags) to linkTo/linkKey (V10b).
 *
 * GB saved `link` option values:
 *   link:post           → linkTo:permalink
 *   link:term           → linkTo:permalink  (term permalink)
 *   link:post_meta,key  → linkTo:key, linkKey:key
 *   link:author_archive, link:author_meta, link:author_email, link:comments → dropped
 *
 * The `link` key is always removed. Returns options array with mapping applied.
 *
 * @since 1.7.0
 * @param array $options Tag options array (post-rename).
 * @return array Options with `link` remapped and removed.
 */
if ( ! function_exists( 'bws_map_gb_link_option' ) ) {
function bws_map_gb_link_option( array $options ): array {
	if ( ! array_key_exists( 'link', $options ) ) {
		return $options;
	}

	$value = (string) $options['link'];
	unset( $options['link'] );

	if ( 'post' === $value || 'term' === $value ) {
		$options['linkTo'] = 'permalink';
	} elseif ( str_starts_with( $value, 'post_meta,' ) ) {
		$meta_key = substr( $value, strlen( 'post_meta,' ) );
		$options['linkTo'] = 'key';
		if ( '' !== $meta_key ) {
			$options['linkKey'] = $meta_key;
		}
	}
	// author_archive, author_meta, author_email, comments → dropped (no equivalent).

	return $options;
}
}
