# Changelog

## [1.6.0] — Unreleased

### Added
- `bws_build_preview_label( $options, $template )` in `content-helpers.php`: structured editor preview labels for unresolvable base and modifier tags (e.g. `[Text Field (body_text) from Ref (rel_post)]`, `[Date like "April 24, 2026"]`, `[⚠ No taxonomy set]`)
- `assets/js/editor-preview-context.js`: injects `bwsEditorPreview: true` into GB's dynamic tag preview context; activates structured preview labels in block editor
- `generateblocks_dynamic_tags_replacement_cache_duration` filter: disables GB's REST replacement cache for editor preview requests so labels stay live
- Base (source-agnostic) tag architecture: single `image`, `text`, `content`, `title`, `permalink`, `datetime_single`, `datetime_range` tags with `source` option replacing per-source N×M tag matrix
- `DeprecatedTagRegistry`: externally-registered deprecated tag wrappers with `transform_options()` migration pipeline (`source_inject`, `option_renames`, `value_renames`, `fixed_options`, `datetime_transforms`)
- `TagTemplateRegistry::register_modifier()` and `generate_base_try_tags()`: term_ and try_ modifier tags generated from modifier template descriptors
- `bws-img-size` (ComboboxControl) and `bws-media-picker` (`wp.media()`) custom editor controls for image tags (`assets/js/image-tag-controls.js`)
- `show_if` conditions `in:` and `not_in:` added to `editor-conditional-options.js`
- `srcTerm` modifier option: post→term hop decoupled from source selector (replaces `via:tax` value)
- Modifier toggle controls in admin settings page (term_, try_ enable/disable)
- `DeprecatedTagRegistry::has_migration_path( string $old_tag ): bool` for converter and admin UI use
- `MigrationRegistry` (`includes/classes/class-migration-registry.php`): unified transform registry supporting `type:'tag'` (deprecated tag name) and `type:'option'` (live base tag option-key) entries; shared 7-step `run_transform()` pipeline; public `parse_tag_string()`, `format_tag_string()`, `transform_tag()`, `apply_option_migration()`, `get_deprecated_tag_names()`, `get_option_migrations_by_tag()`
- `bws_register_option_migrations()` in `deprecated-tags.php`: registers `type:'option'` `MigrationRegistry` entries for all base tags carrying a `rel` option key — renames `rel` → `ref` and prepends `source:ref` (fixes broken converter output from the `via`→`source` rename cycle)
- In-editor deprecated tag preview warnings: all deprecated callbacks check `$instance->context['bwsEditorPreview']` and return `[⚠ {{old_tag}} deprecated — use {{new_tag_with_actual_options}}]`; `bws_build_deprecation_preview_label()` helper calls `MigrationRegistry::transform_tag()` to show actual replacement (early hand-written callbacks use hardcoded display strings)
- Suppress mode for deprecated tags: callback returns `''` immediately when `SettingsPage::is_deprecated_tag_suppressed()` is true, preventing unprocessed tag strings on the frontend
- Admin Migration Tool (`includes/classes/admin/class-tag-converter.php`): `scan()` queries all non-revision posts via multi-LIKE SQL then PHP-level regex+parse verification; `migrate_post()` calls `wp_save_post_revision()` for pre-migration snapshot, applies full deprecated tag and option-key transforms, writes via `$wpdb->update()` + `clean_post_cache()` to avoid hook side-effects and duplicate revisions
- `assets/js/admin-tag-scanner.js`: Scan button → paginated AJAX scan; results table with post title, type, issues list (deprecated tags + option migrations), per-row Migrate button; Select All / Bulk Migrate Selected with progress bar; per-row status shows tag and option fix counts; ⚠ note when post type has no revision support

### Changed
- Base tag callbacks (`text`, `content`, `title`, `image`, `datetime_single`, `datetime_range`) and `term_` modifier callbacks: return `bws_build_preview_label()` in editor preview context instead of static REST placeholders (`[Custom Field]`, `[Title]`, etc.)
- `via`/`from` option renamed to `source`; `from` (field selector) renamed to `use` across all base tags and modifier callbacks
- Datetime option keys renamed to camelCase names: `time_sep` → `timeSep`, `range_sep` → `rangeSep`, `show_current_year` → `showCurrentYear`, `show_midnight` → `showMidnight`, `key2` → `timeKey` (single), `key`/`key2`/`end`/`end2` → `startKey`/`startTimeKey`/`endKey`/`endTimeKey` (range); mapper functions and migration rename targets updated accordingly
- `DeprecatedTagRegistry` refactored as thin 4-method facade over `MigrationRegistry`; external callers (e.g. `bws-portal-system`) unchanged; `transform_options()` delegates to `MigrationRegistry::transform_tag()`
- Admin deprecated tags settings redesigned: per-tag enable/disable replaced by two group-level radio sets — **Has migration path** and **No migration path** — each with three modes: Keep / Suppress / Disable; tag membership stored per-tag, toggled by group; collapsible `<details>` reference lists show tags in each group
- Migration Tool moved to a separate section outside the settings `<form>`; replaces per-tag List Posts / Convert buttons with a unified post-level scan and migrate workflow
- `image` base tag type changed from `'media'` to `'cross-source'`; `supports:['image-size']` removed in favor of explicit PHP options
- `try_image`: per-slot `use` added (`try_per_slot_use`); `psk` key-check skips `use:featured` slots via `try_use_no_key_values`
- `term_image` modifier: `use:featured` gated behind `source:ref` (term entities have no featured image)
- `show_if` / `show_if_any` support added to `editor-conditional-options.js` (OR conditions)
- `SourceInterface` and `AbstractSource` cleanup: removed related-variant methods post Pattern B
- `get_traversal_options()` removed from `SourceInterface`, `AbstractSource`, and all source classes; `register_modifier()` now hardcodes standardized `ref` traversal sub-option (Q8 resolution)
- `SecondRelatedPost` label: "Post → 2nd Rel. Post"; `PostTermRelatedPost` label: "Post → Term → Rel. Post"
- `date-helpers.php` renamed to `datetime-helpers.php`; `date-tags.php` deleted (content merged into `datetime-tags.php` in v1.6.0)
- `taxonomy` option key renamed to `tax` in post-context term-extraction templates (`bws_post_term_extraction_options`, `bws_post_term_image_options`, `PostTermRelatedPost::get_source_options()`); readers accept both `tax` and `taxonomy` for backward compatibility

### Removed
- `generate_all_tags()` and `generate_try_tags()` from `TagTemplateRegistry` — N×M loop eliminated; deprecated wrappers now active for all old per-source tag names
- `register_template()`, `get_templates()`, `make_direct_callback()`, `make_entities_callback()`, `compute_tag_default()` from `TagTemplateRegistry` (N×M support methods)
- N×M template registration functions from tag files: `bws_register_post_content_tag_templates()`, `bws_register_image_tag_templates()`, `bws_register_date_tag_templates()`, `bws_register_datetime_tag_templates()`, `bws_register_taxonomy_term_extraction_templates()`
- `$templates` static property from `TagTemplateRegistry`
- `bws_extract_text_field()`, `bws_extract_url_field()`, `bws_get_link_url()` from `content-helpers.php` (dead code — no callers in active files)
- `TagConverter::list()` and `TagConverter::convert()` — replaced by unified `scan()` + `migrate_post()` + paginated batch AJAX
- Per-tag List Posts / Convert buttons in admin deprecated section — replaced by Migration Tool

### Documentation
- `docs/deprecated-tags-options.md` (new): migration reference containing all deprecated N×M tag name tables, template key renaming tracker, and option name renaming tracker; moved from `docs/tag-matrix.md`
- `docs/tag-matrix.md`: removed N×M matrix tables and rename trackers; replaced with forward references to `docs/deprecated-tags-options.md`; default-enabled logic section updated for v1.6.0 modifier group + deprecated wrapper toggles
- `docs/plugin-integration.md`: new §2 (Registering a Context Modifier with `register_modifier()` example and parameter reference); new §8 (Renaming a Modifier Prefix — converter-based migration pattern); §5 helper table corrected; §6 admin settings rewritten for v1.6.0; §7 deprecated wrapper parameter table updated (removed `source_key`/`is_related`, added all new fields)
- `CLAUDE.md`: simplified to dependency + development summary; defers to `README.md` and `docs/tag-matrix.md`
- `README.md`: expanded from one-liner to proper overview with requirements and architecture pointer

### Fixed
- `try_image`, `try_datetime_single`, `try_datetime_range`: Group 1 formatting options (`as`, `size`, `format`, `timeSep`, `rangeSep`, `showCurrentYear`, `showMidnight`) were appended after per-slot options instead of preceding them; corrected via `leading_options` on modifier template descriptors
- `datetime_single`, `datetime_range` base tags: source block appeared before formatting options; reordered to formatting → source → field keys → fallback per spec
- `image`, `term_image`, `try_image` tags: `fallback` option (set by `bws-media-picker`) was ignored at runtime; core functions read `id` (legacy GB media-type key) instead of `fallback`; now read `fallback ?? id` with backward compat for pre-v1.6.0 saved tags
- `bws_term_custom_image_core`: read `fallback_url` instead of `fallback`; now reads `fallback ?? fallback_url`
- `bws_handle_media_fallback`: only accepted numeric attachment IDs; now also resolves attachment URL via `bws_get_attachment_id_from_url()` to support `bws-media-picker` output (stores URL, not ID)
- `bws_register_option_migrations()`: added `type:'option'` entries for `image`, `term_image`, `try_image` to rename `id → fallback` on tags saved in v1.5.x when those tags still used `type:'media'`
- `$fi_renames` / `$ci_renames` in `bws_register_v1_deprecated_tag_wrappers()`: `id → fallback` rename now included so deprecated-tag converter migrations carry the rename through to the target tag
- `ImageSizeControl` (`image-tag-controls.js`): `generateBlocksInfo.imageSizes` array items not normalized to `{ value, label }` objects; `ComboboxControl` crashed with `Cannot read properties of undefined (reading 'replace')` when items were strings or lacked a string `label` property
- `DeprecatedTagRegistry` loop: undefined `$sk` variable
- Datetime converter: boolean injections use `'true'` string, not `'1'`
- `DeprecatedTagRegistry::has_migration_path()` returned `true` for all entries; now checks `new_tag` non-empty
- Converter output for related-source tags: `rel` option key was not renamed to `ref` and `source:ref` was not prepended; caused tags like `{{text rel:field|key:val}}` instead of `{{text source:ref|ref:field|key:val}}`; fixed via `MigrationRegistry` `type:'option'` entries registered by `bws_register_option_migrations()`
- 22 deprecated tag registrations missing `new_tag` (and migration config) caused admin scanner to show them as having no auto-convert path despite approved migration specs: `post_term_description/custom_text/custom_image`, `related_post_term_description/custom_text/custom_image`, `term_related_post_term_description/custom_text/custom_image`, `term_custom_text/image/date_single/date_range/datetime_single/datetime_range`, `try_custom_text/featured_image/custom_image/date_single/date_range/datetime_single/datetime_range`; all now carry `new_tag`, `source_inject`, `option_renames`, `value_renames`, `fixed_options`, and `datetime_transforms` as appropriate
- `MigrationRegistry::run_transform()`: empty-string `new_key` in `option_renames` now drops the option (unsets without creating new key); enables `src_1 => ''` pattern used by `try_*` slot migrations to suppress the slot-1 source (which defaults to `post`)
- Scanner falsely counted post revisions as separate posts; `scan()` now excludes `post_type = 'revision'` and `post_status IN ('auto-draft','trash')` at SQL level

### Deprecated (N×M → base-tag wrappers, Commit C1)
- 75 N×M source × template generated tag names deprecated with `DeprecatedTagRegistry` entries covering all post-context, term-context, and term-extraction combinations
- Three callback factories added (`bws_make_deprecated_post_callback`, `bws_make_deprecated_term_callback`, `bws_make_deprecated_term_extraction_callback`) for runtime resolution via `SourceRegistry`
- All migration-capable entries include `source_inject`, `option_renames`, `value_renames`, `fixed_options`, and `datetime_transforms` for converter use
- Pre-C2 dup-check in `bws_register_deprecated_tags()`: skips deprecated entries whose `old_tag` is still live in GB's registry (N×M active); wrappers activate automatically once C2 removes `generate_all_tags()`

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
