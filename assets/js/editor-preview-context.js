/**
 * Editor preview context flag for BWS dynamic tags.
 *
 * Injects bwsEditorPreview: true into the context sent to the
 * dynamic tag replacement endpoint. PHP callbacks use this flag
 * to return structured preview labels instead of empty string
 * when a tag can't resolve in the editor.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.6.0
 */
wp.hooks.addFilter(
	'generateblocks.editor.preview.context',
	'bws/preview-flag',
	( context ) => ( { ...context, bwsEditorPreview: true } )
);
