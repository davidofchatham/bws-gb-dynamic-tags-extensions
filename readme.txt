=== GenerateBlocks Dynamic Tag Extensions by BWS ===
Contributors: bridgewebsolutions
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.4.2
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

= 1.4.2 =
* Fix datetime fallback: bws_handle_date_time_fallback() now returns empty string when fallback_text is unset, enabling standard "required to render" block behavior; previously returned hardcoded "Date TBA" / "Time TBA" / "Date/time TBA" strings unconditionally

= 1.4.1 =
* Remove GB source picker from related_post_* tags (related variants always traverse from current post via rel; source selector was non-functional there)
* Remove GB source picker from second_related_post_* tags (traversal always from current post via rel+rel_2)
* Add Supports column to tag-matrix.md sources tables and tag matrix tables
* Add tag_default_enabled() to SourceInterface/AbstractSource: separates source toggle default (source_default_enabled) from per-tag default when source is on
* Fix is_source_enabled() to respect source_default_enabled() instead of hardcoding true
* Flip second_related_post_ tags to enabled-by-default when source is on (source toggle remains opt-in)
* Add post_term_related_post_ source: 3-hop traversal from current post → taxonomy term → term's related post (11 templates, source opt-in)

= 1.4.0 =
* Extend content template with Content Type option: choose between post content/description (default) or a custom ACF/meta field, with conditional Meta Key input and fallback text support
* Add try_content tag: per-slot type selection allows mixing content types across fallback slots (e.g. try a custom field, fall back to post content)
* Suppress term_content direct tag (content is not meaningful as a term direct tag; term_description is used instead via try_content)

= 1.3.3 =
* Add conditional field visibility system: `show_if` (AND) and `show_if_any` (OR) properties on PHP option definitions, evaluated by new `assets/js/editor-conditional-options.js` via the `generateblocks.editor.tagSpecificControls` filter
* Redesign try_* tags: 5 slots (was 3), source-first field order (src_N → rel_N → key_N), slots 1–2 always visible with slots 3–5 revealed progressively, "Same as Slot N" inherit option on slots 2+, relationship field gated on related source selection or previous slot's rel being set, auto-detect related source when rel_N set without src_N

= 1.3.2 =
* Refactor: extract 5 named callback factory methods from TagTemplateRegistry::generate_all_tags() (C3)
* Refactor: decouple SettingsPage::is_tag_enabled() from _registered_tags during tag generation — source context now passed inline (C2)
* Refactor: standardize resolve_id() on CurrentPost and RelatedPost sources; remove AbstractSource compat shim (C5)
* Convert PHP source files to LF line endings

= 1.3.1 =
* Fix custom_text fallback text not triggering when ACF returns empty string for a blank registered field

= 1.3.0 =
* Added fallback text option to custom_text template (post, term, and try_ variants)
* Added get_excluded_supports() to SourceInterface/AbstractSource for external sources to suppress inapplicable GB supports (e.g. post selector) on their tags

= 1.2.0 =
* Refactored to source × template architecture
* Added external plugin API for registering additional tag sources
* Added deprecated tag registry for backwards compatibility

= 1.0.0 =
* Initial release
