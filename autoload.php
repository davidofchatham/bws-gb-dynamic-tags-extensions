<?php
/**
 * PSR-4 Autoloader for BWS Dynamic Tag Extensions.
 *
 * Maps BWS\DynamicTags namespace to includes/classes/ directory with WordPress file naming conventions.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register( function ( $class ) {
	// Only load BWS\DynamicTags classes.
	$prefix     = 'BWS\\DynamicTags\\';
	$prefix_len = strlen( $prefix );

	if ( strncmp( $prefix, $class, $prefix_len ) !== 0 ) {
		return;
	}

	// Get relative class name (e.g., "Sources\RelatedPost" or "SourceRegistry").
	$relative_class = substr( $class, $prefix_len );

	// Convert namespace separators to directory separators.
	$path_parts = explode( '\\', $relative_class );

	// Last part is class name - convert to WordPress file naming.
	// Examples: SourceRegistry -> class-source-registry.php, RelatedPost -> class-related-post.php
	$class_name = array_pop( $path_parts );
	$filename   = 'class-' . strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $class_name ) ) . '.php';

	// Build full path with lowercase directories.
	$directory = BWS_DYNAMIC_TAGS_PATH . 'includes/classes/';
	if ( ! empty( $path_parts ) ) {
		$directory .= strtolower( implode( '/', $path_parts ) ) . '/';
	}

	$file = $directory . $filename;

	// Load the file if it exists.
	if ( file_exists( $file ) ) {
		require $file;
	}
} );
