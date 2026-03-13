=== GenerateBlocks Dynamic Tag Extensions by BWS ===
Contributors: bridgewebsolutions
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.3.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Extends GenerateBlocks with custom dynamic tags for ACF integration, providing dynamic content from multiple post sources, date/time formatting, and taxonomy terms.

== Description ==

Extends GenerateBlocks Pro with custom dynamic tags powered by Advanced Custom Fields (ACF). Provides dynamic content insertion for post content, taxonomy terms, and date/time fields across multiple post sources.

**Features:**

* Custom text and image tags from ACF fields on the current post, related posts, and taxonomy terms
* Date and date/time tags with smart timezone handling and locale-aware output
* Taxonomy term tags (name, permalink, description, field image)
* Settings page to enable/disable individual tags
* External plugin API for registering additional sources

**Requirements:**

* GenerateBlocks Pro (with dynamic tag support)
* Advanced Custom Fields (ACF) or ACF Pro

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. Ensure GenerateBlocks Pro and ACF are active

== Changelog ==

= 1.3.0 =
* Added fallback text option to custom_text template (post, term, and try_ variants)
* Added get_excluded_supports() to SourceInterface/AbstractSource for external sources to suppress inapplicable GB supports (e.g. post selector) on their tags

= 1.2.0 =
* Refactored to source × template architecture
* Added external plugin API for registering additional tag sources
* Added deprecated tag registry for backwards compatibility

= 1.0.0 =
* Initial release
