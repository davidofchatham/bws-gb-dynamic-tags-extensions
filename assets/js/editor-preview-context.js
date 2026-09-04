/**
 * Editor preview context flag for BWS dynamic tags.
 *
 * Injects bwsEditorPreview: true into the context sent to the
 * dynamic tag replacement endpoint. PHP callbacks use this flag
 * to return structured preview labels instead of empty string
 * when a tag can't resolve in the editor.
 *
 * THE RETURNED OBJECT IS IDENTITY-STABLE PER INPUT CONTEXT — same context in,
 * same object out. GB applies this filter during the Looper's render and passes
 * the result on to `generateblocks.editor.looper.query`, where a consumer may
 * put it straight into a React dependency array (GB Query Enhancements 1.3.0
 * does, in its term and user query hooks). A freshly built object per call is a
 * changed dependency on every render, so such a consumer refetches, sets state,
 * and renders again without end. Building a new object only when the input
 * context itself is a new object is what keeps that loop from existing.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.6.0
 */
( function () {
	'use strict';

	var flagged = new WeakMap();
	// A non-object context cannot key a WeakMap; one shared object keeps that path stable too.
	var bare = { bwsEditorPreview: true };

	wp.hooks.addFilter(
		'generateblocks.editor.preview.context',
		'bws/preview-flag',
		function ( context ) {
			if ( ! context || 'object' !== typeof context ) {
				return bare;
			}

			var flag = flagged.get( context );
			if ( ! flag ) {
				flag = Object.assign( {}, context, { bwsEditorPreview: true } );
				flagged.set( context, flag );
			}

			return flag;
		}
	);

} )();
