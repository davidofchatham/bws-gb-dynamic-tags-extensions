# Changelog

## [1.6.0] — Unreleased

### Added
- Base (source-agnostic) tag architecture: single `image`, `text`, `content`, `title`, `permalink`, `datetime_single`, `datetime_range` tags with `source` option replacing per-source N×M tag matrix
- `DeprecatedTagRegistry`: externally-registered deprecated tag wrappers with `transform_options()` migration pipeline (`source_inject`, `option_renames`, `value_renames`, `fixed_options`, `datetime_transforms`)
- `TagTemplateRegistry::register_modifier()` and `generate_base_try_tags()`: term_ and try_ modifier tags generated from modifier template descriptors
- `bws-img-size` (ComboboxControl) and `bws-media-picker` (`wp.media()`) custom editor controls for image tags (`assets/js/image-tag-controls.js`)
- `show_if` conditions `in:` and `not_in:` added to `editor-conditional-options.js`
- `srcTerm` modifier option: post→term hop decoupled from source selector (replaces `via:tax` value)
- Tag converter utility (admin) with `TagConverter` class and settings page integration
- Modifier toggle controls in admin settings page (term_, try_ enable/disable)
- Deprecated tags section in admin settings page
- `DeprecatedTagRegistry::has_migration_path( string $old_tag ): bool` for converter and admin UI use

### Changed
- `via`/`from` option renamed to `source`; `from` (field selector) renamed to `use` across all base tags and modifier callbacks
- `image` base tag type changed from `'media'` to `'cross-source'`; `supports:['image-size']` removed in favor of explicit PHP options
- `try_image`: per-slot `use` added (`try_per_slot_use`); `psk` key-check skips `use:featured` slots via `try_use_no_key_values`
- `term_image` modifier: `use:featured` gated behind `source:ref` (term entities have no featured image)
- `show_if` / `show_if_any` support added to `editor-conditional-options.js` (OR conditions)
- `SourceInterface` and `AbstractSource` cleanup: removed related-variant methods post Pattern B
- `get_traversal_options()` removed from `SourceInterface`, `AbstractSource`, and all source classes; `register_modifier()` now hardcodes standardized `ref` traversal sub-option (Q8 resolution)
- `SecondRelatedPost` label: "Post → 2nd Rel. Post"; `PostTermRelatedPost` label: "Post → Term → Rel. Post"

### Fixed
- `DeprecatedTagRegistry` loop: undefined `$sk` variable
- Datetime converter: boolean injections use `'true'` string, not `'1'`

### Architecture (v1.5.0 → v1.6.0)
- Pattern B completed: related-variant mechanism replaced by standalone source classes (`RelatedPost`, `TermRelatedPost`)
- Source dispatch simplified to two values: `''` (current entity) and `'ref'` (relationship field hop)
- Option ordering standardized per three-group structure: global formatting → per-slot → global fallback

---

## [1.5.0]

- Pattern B: RelatedPost and TermRelatedPost promoted to standalone source classes; removes related-variant mechanism (~240 lines)
- New: TermRelatedPost source (Term → Rel. Post) — term context, post resolution, enabled by default
- Add `needs_relationship_field()` and `get_ui_group()` to SourceInterface/AbstractSource
- Remove `has_related_variant()` and 5 related-variant methods from SourceInterface/AbstractSource (breaking change for external sources)
- SecondRelatedPost and PostTermRelatedPost: source toggle now enabled by default
- All traversal sources now exclude `link` support
- try_ tags: traversal moved into source `resolve_id()`; `$last_rel` carry-forward preserved
- Fix: inject relationship field option on traversal-source direct tags (RelatedPost, TermRelatedPost)
- Fix: disabled sources no longer appear as options in try_ slot source dropdowns

## [1.4.2]

- Fix datetime fallback: `bws_handle_date_time_fallback()` returns empty string when `fallback_text` is unset; previously returned hardcoded strings unconditionally

## [1.4.1]

- Remove GB source picker from `related_post_*` and `second_related_post_*` tags (traversal always from current post)
- Add `tag_default_enabled()` to SourceInterface/AbstractSource
- Fix `is_source_enabled()` to respect `source_default_enabled()` instead of hardcoding true
- Flip `second_related_post_` tags to enabled-by-default when source is on
- Add `post_term_related_post_` source: 3-hop traversal (current post → taxonomy term → term's related post)

## [1.4.0]

- Extend `content` template with Content Type option (post content/description or ACF/meta field)
- Add `try_content` tag with per-slot type selection
- Suppress `term_content` direct tag

## [1.3.3]

- Add conditional field visibility: `show_if` (AND) and `show_if_any` (OR) on PHP option definitions, evaluated by `assets/js/editor-conditional-options.js`
- Redesign try_* tags: 5 slots (was 3), source-first field order, progressive slot disclosure

## [1.3.2]

- Refactor: extract 5 named callback factory methods from TagTemplateRegistry
- Refactor: decouple `SettingsPage::is_tag_enabled()` from `_registered_tags` during tag generation
- Refactor: standardize `resolve_id()` on CurrentPost and RelatedPost sources

## [1.3.1]

- Fix: custom_text fallback not triggering when ACF returns empty string for blank registered field

## [1.3.0]

- Add fallback text option to custom_text template (post, term, and try_ variants)
- Add `get_excluded_supports()` to SourceInterface/AbstractSource

## [1.2.0]

- Refactored to source × template architecture
- Added external plugin API for registering additional tag sources
- Added deprecated tag registry for backwards compatibility

## [1.0.0]

- Initial release
