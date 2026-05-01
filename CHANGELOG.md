# Changelog

## [1.6.0] — Unreleased

### Added
- `bws_build_preview_label( $options, $template )` in `content-helpers.php`: structured editor preview labels for unresolvable base and modifier tags (e.g. `[Text Field (body_text) from Ref (rel_post)]`, `[Date like "April 24, 2026"]`, `[⚠ No taxonomy set]`)
- `assets/js/editor-preview-context.js`: injects `bwsEditorPreview: true` into GB's dynamic tag preview context; activates structured preview labels in block editor
- `generateblocks_dynamic_tags_replacement_cache_duration` filter: disables GB's REST replacement cache for editor preview requests so labels stay live
- Base (source-agnostic) tag architecture: single `image`, `text`, `content`, `title`, `permalink`, `datetime_single`, `datetime_range` tags with `src` option replacing per-source N×M tag matrix
- `DeprecatedTagRegistry`: externally-registered deprecated tag wrappers with `transform_options()` migration pipeline (`source_inject`, `option_renames`, `value_renames`, `fixed_options`, `datetime_transforms`)
- `TagTemplateRegistry::register_modifier()` and `generate_base_try_tags()`: term_ and try_ modifier tags generated from modifier template descriptors
- `bws-img-size` (ComboboxControl) and `bws-media-picker` (`wp.media()`) custom editor controls for image tags (`assets/js/image-tag-controls.js`)
- `show_if` conditions `in:` and `not_in:` added to `editor-conditional-options.js`
- `srcTermIn` term-hop option on base tags (`text`, `content`, `title`, `permalink`, `image`, `datetime_single`, `datetime_range`): single persisted key encodes "term hop enabled + taxonomy slug" — empty/absent = disabled, slug = enabled. Replaces the prior `srcTerm` (boolean) + `tax` (slug) pair. `bws-term-hop` custom control (`assets/js/term-hop-control.js`) renders sibling CheckboxControl + ComboboxControl (taxonomies sourced via `wp.data` `core`, public-only); checkbox is React-local state, only the slug round-trips through `extraTagParams`. Resolves GB-reserved-key conflict where `tax` was extracted and silently dropped on modal reopen for cross-source base tags. Term-modifier (`term_*`) tags continue to use GB's native `tax` selector. Legacy `srcTerm` boolean stripped from state on mount so existing tags re-serialize cleanly.
- `combine_options` `MigrationRegistry` primitive: maps `[when_present, value_from] → new_key`; both old keys always dropped; new key emitted only when presence-flag and value both present. Runs as Step 2 of the transform pipeline (before `option_renames`). Used to migrate hand-written `srcTerm` + `tax:<slug>` strings via the Migration Tool; reusable for future combined-option migrations.
- `bws_build_preview_label()` reads `srcTermIn` (with `tax` legacy fallback) when deriving term-hop missing-taxonomy warning so the new key no longer triggers a false "No taxonomy set" preview.
- `MigrationRegistry` option entries for all 7 base tags matching `tax` presence: combine `srcTerm` + `tax:<slug>` → `srcTermIn:<slug>` so the admin Migration Tool detects and converts legacy term-hop strings.
- Deprecated term-extraction tag entries (15 across `post_term_*`, `related_post_term_*`, `term_related_post_term_*` families): `$srcterm_fixed` injection removed; `tax` → `srcTermIn` rename merged into `option_renames` so migrator output matches the new key.
- Modifier toggle controls in admin settings page (term_, try_ enable/disable)
- `DeprecatedTagRegistry::has_migration_path( string $old_tag ): bool` for converter and admin UI use
- `MigrationRegistry` (`includes/classes/class-migration-registry.php`): unified transform registry supporting `type:'tag'` (deprecated tag name) and `type:'option'` (live base tag option-key) entries; shared 7-step `run_transform()` pipeline; public `parse_tag_string()`, `format_tag_string()`, `transform_tag()`, `apply_option_migration()`, `get_deprecated_tag_names()`, `get_option_migrations_by_tag()`
- `bws_register_option_migrations()` in `deprecated-tags.php`: registers `type:'option'` `MigrationRegistry` entries for all base tags carrying a `rel` option key — renames `rel` → `ref` and prepends `src:ref` (fixes broken converter output from the `via`→`src` rename cycle)
- In-editor deprecated tag preview warnings: all deprecated callbacks check `$instance->context['bwsEditorPreview']` and return `[⚠ {{old_tag}} deprecated — use {{new_tag_with_actual_options}}]`; `bws_build_deprecation_preview_label()` helper calls `MigrationRegistry::transform_tag()` to show actual replacement
- Suppress mode for deprecated tags: callback returns `''` immediately when `SettingsPage::is_deprecated_tag_suppressed()` is true, preventing unprocessed tag strings on the frontend
- Admin Migration Tool (`includes/classes/admin/class-tag-converter.php`): `scan()` queries all non-revision posts via multi-LIKE SQL then PHP-level regex+parse verification; `migrate_post()` calls `wp_save_post_revision()` for pre-migration snapshot, applies full deprecated tag and option-key transforms, writes via `$wpdb->update()` + `clean_post_cache()` to avoid hook side-effects and duplicate revisions
- `assets/js/admin-tag-scanner.js`: Scan button → paginated AJAX scan; results table with post title, type, issues list (deprecated tags + option migrations), per-row Migrate button; Select All / Bulk Migrate Selected with progress bar; per-row status shows tag and option fix counts; ⚠ note when post type has no revision support
- `bws_read_field( $key, $instance, $post_id, $single_only = true )` and `bws_read_term_field( $key, $term_id, $single_only = true )` in `content-helpers.php`: unified field-extraction helpers routing through `GenerateBlocks_Meta_Handler::get_meta()`. ACF reads now happen via GB Pro's `generateblocks_get_meta_pre_value` filter — no inline `get_field()` calls in helpers. Loop-row context detection cached on `$instance->context['bws/loopItemPostId']` (Mode 2a: row resolves to post → read post meta; Mode 2b: flat repeater row → read `$loop_item[$key]` directly). `DISALLOWED_KEYS` security guard mirrors GB native posture; protected meta allowed on frontend (matches `Meta_Handler::get_meta()` behavior, supports plugins like Pie Calendar that store data in `_`-prefixed keys).
- Plugin header `Requires Plugins: generateblocks-pro` declares GB Pro as a hard dependency. WP 6.5+ enforces this in `/wp-admin/plugins.php` (cross-references both directions, prevents deactivation while dependent active). Runtime check for `class_exists( 'GenerateBlocks_Meta_Handler' )` enforces GB 2.0+ minimum (since core `Requires Plugins` syntax does not support version constraints).
- Plugin header `Requires at least` bumped from 6.0 to 6.5 (matches `Requires Plugins` minimum).
- `bws_get_loop_row_context( $instance ): array` in `content-helpers.php`: single source of truth for GB Pro loop-row detection. Returns `['loop_item' => mixed, 'row_post_id' => int|false, 'in_loop' => bool]`. Caches `bws/loopItemPostId` on `$instance->context` so per-block detection runs once. Replaces 5 inlined detection blocks across `bws_read_field()`, `bws_resolve_post_by_source()`, `bws_get_meta_image_data()`, `bws_post_content_core()`, `bws_post_custom_text_core()`, and `bws_custom_image_core()`.

### Changed
- Base tag callbacks (`text`, `content`, `title`, `image`, `datetime_single`, `datetime_range`) and `term_` modifier callbacks: return `bws_build_preview_label()` in editor preview context instead of static REST placeholders (`[Custom Field]`, `[Title]`, etc.)
- `via`/`from` option renamed to `src`; `from` (field selector) renamed to `use` across all base tags and modifier callbacks
- Datetime option keys renamed to camelCase names: `time_sep` → `timeSep`, `range_sep` → `rangeSep`, `show_current_year` → `showCurrentYear`, `show_midnight` → `showMidnight`, `key2` → `timeKey` (single), `key`/`key2`/`end`/`end2` → `startKey`/`startTimeKey`/`endKey`/`endTimeKey` (range); mapper functions and migration rename targets updated accordingly
- `DeprecatedTagRegistry` refactored as thin 4-method facade over `MigrationRegistry`; external callers (e.g. `bws-portal-system`) unchanged; `transform_options()` delegates to `MigrationRegistry::transform_tag()`
- Admin deprecated tags settings redesigned: per-tag enable/disable replaced by two group-level radio sets — **Has migration path** and **No migration path** — each with three modes: Keep / Suppress / Disable; tag membership stored per-tag, toggled by group; collapsible `<details>` reference lists show tags in each group
- Migration Tool moved to a separate section outside the settings `<form>`; replaces per-tag List Posts / Convert buttons with a unified post-level scan and migrate workflow
- `image` base tag type changed from `'media'` to `'cross-source'`; `supports:['image-size']` removed in favor of explicit PHP options
- `try_image`: per-slot `use` added (`try_per_slot_use`); `psk` key-check skips `use:featured` slots via `try_use_no_key_values`
- `term_image` modifier: `use:featured` gated behind `src:ref` (term entities have no featured image)
- `show_if` / `show_if_any` support added to `editor-conditional-options.js` (OR conditions)
- `SourceInterface` and `AbstractSource` cleanup: removed related-variant methods post Pattern B
- `get_traversal_options()` removed from `SourceInterface`, `AbstractSource`, and all source classes; `register_modifier()` now hardcodes standardized `ref` traversal sub-option (Q8 resolution)
- `SecondRelatedPost` label: "Post → 2nd Rel. Post"; `PostTermRelatedPost` label: "Post → Term → Rel. Post"
- `date-helpers.php` renamed to `datetime-helpers.php`; `date-tags.php` deleted (content merged into `datetime-tags.php` in v1.6.0)
- `taxonomy` option key renamed to `tax` in post-context term-extraction templates (`bws_post_term_extraction_options`, `bws_post_term_image_options`, `PostTermRelatedPost::get_source_options()`); readers accept both `tax` and `taxonomy` for backward compatibility
- All 6 inline `get_field()/get_post_meta()/get_term_meta()` field-extraction call sites consolidated through `bws_read_field()` / `bws_read_term_field()`: `bws_get_meta_image_data()` (image-helpers), `bws_get_term_field_image_data()` (taxonomy-helpers, `$taxonomy` param dropped), `bws_post_custom_text_core()` and `bws_post_content_core()` custom_field branch (content-tags), `bws_term_custom_text_core()` (taxonomy-tags), and `bws_get_acf_field_value()` (datetime-helpers) — the latter retained as a thin shim that routes ACF term object_id syntax (`"{taxonomy}_{term_id}"`) to `bws_read_term_field()` and post IDs to `bws_read_field()`.
- `bws_parse_combined_date_time()`, `bws_get_acf_field_value()`, `bws_get_meta_image_data()`: `$instance` parameter threaded through so loop-row context detection works for datetime + image tags.
- `bws_post_custom_text_core()`, `bws_post_content_core()` (custom_field branch), `bws_get_meta_image_data()`: short-circuit on `! $post_id` relaxed when block instance is in a loop-row context (`generateblocks/loopItem` set), allowing field reads against the row entity.
- `bws_resolve_post_by_source()`: now Mode 2 aware. Mode 2a (loop row resolves to post): `src:''` returns row post ID, `src:ref` reads `ref` meta from row post. Mode 2b (flat repeater row): `src:''` returns `false` so callback can fall through to row data; `src:ref` reads `$loop_item[$ref]` directly. ACF Relationship/post_object subfields returning a list (no `ID` key) auto-unwrap to the first entry.
- `try_*` slot dispatch in `TagTemplateRegistry::generate_base_try_tags()`: Mode 2b (flat repeater row) skip-on-`! $post_id` was too aggressive — `bws_resolve_post_by_source()` correctly returns `false` for `src:''` in Mode 2b, but the slot's core function can still resolve via `$loop_item[$key]`. Now allows fallthrough when `$in_loop_row && '' === $last_src && ! empty( $last_key )`, so `try_text`, `try_content`, etc. can read flat-repeater row keys directly across slots.
- `bws_extract_post_id()`: handles list-of-posts return formats (Relationship/post_object subfield with no max_size limit). When passed an array without an `'ID'` key, takes the first entry and recurses. Lets `bws_resolve_post_by_source()` Mode 2 paths drop their inline list-unwrap workaround.
- `TermRelatedPost::resolve_id()` (`class-term-related-post.php`): inline `get_field( $rel, 'term_'.$term_id )` replaced with `bws_read_term_field( $rel, $term_id, false )`. Routes through `Meta_Handler` for ACF integration via filter; consistent with rest of field-extraction pipeline. Falls back to raw `get_field()` if helpers unavailable.

### Removed
- `bws_get_acf_field_value()` from `datetime-helpers.php`: thin shim retained through Phase 2 of the field-extraction consolidation. Replaced by inline `bws_read_field()` / `bws_read_term_field()` calls in `bws_parse_combined_date_time()` with ACF term object_id (`"{taxonomy}_{term_id}"`) detection inlined.
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
- `bws_build_preview_label()`: replaced straight double quotes (`"..."`) around `$fallback` value and datetime `$formatted` value with curly quotes (`“...”`, U+201C/U+201D). Straight quotes broke `<img alt="...">` attribute when `image as:alt`/`as:caption` rendered preview labels containing user-controlled fallback strings; curly quotes are attribute-safe. Affects three call sites (warning branch, datetime branch, final-assembly branch). Doc examples in `tag-matrix.md` updated to match.
- Loop-row context detection only matched `is_array( $loopItem )` rows, but GB Pro's post_meta loop hands rows as `WP_Post` objects (ACF Relationship field with return_format=object) or numeric IDs (return_format=id). All Mode 2 detection sites now accept `array | WP_Post | numeric` so `{{title}}`, `{{text key:...}}`, `{{datetime_*}}` tags inside relationship loops correctly resolve to row entities instead of falling back to the outer post.
- `TagTemplateRegistry::register_modifier()`: option key `source` renamed to `src` (and `show_if` references updated). The earlier `source`→`src` rename in v1.6.0 was applied to base tags but missed `register_modifier()`, so all generated modifier tags (e.g. `term_*`, `views_*`) had their source dropdown silently eaten by GB's `DynamicTagSelect` destructure — users could not pick the "ref" traversal option in any modifier tag.
- `TagTemplateRegistry::make_modifier_callback()`: unset-`src` branch hardcoded `term_fn` dispatch, which assumed the modifier prefix entity was always a term. Broke post-context modifiers (e.g. `views_*` from `bws-portal-system`): bare `{{views_content}}` resolved a post ID via `PortalSource` then called `bws_term_description_core` with that post ID. Now dispatches by base source's `get_context_type()` — `term` → `term_fn`, `post` → `post_fn`. `term_*` modifier behavior unchanged; `views_*` modifier tags now render correctly.
- `bws_base_source_option()`: option key renamed `source` → `src`; labels corrected to "Current" and "In Reference/Relational Field". GB's `DynamicTagSelect` unconditionally destructures `'source'` from parsed tag params before spreading into `extraTagParams`, so any PHP option named `source` is silently eaten — the editor control never receives the value and the option is dropped on save. `src` avoids the conflict. PHP callbacks read `src ?? source` for backward compatibility. C7 `type:'option'` migration entries registered for all 7 base tags to rename `source` → `src` in saved content. `source_inject` in `MigrationRegistry` updated to emit `src` key.
- `bws_base_traversal_options()`: `show_if` key updated `source` → `src` to match renamed option
- `generate_base_try_tags()`: slot 1 option keys were `source`/`use`/`1-ref`/`1-srcTerm`/`1-tax`/`1-key`; same GB destructure bug caused `source` to be eaten, and `1-` prefix on remaining slot-1 keys diverged from spec. All slot-1 keys now un-prefixed: `src`, `ref`, `srcTerm`, `tax`, `use`, `key`. Slots 2–5 unchanged (`N-src`, `N-ref`, etc.). `$src_opts` merges in callback updated to pass `src` key. Slot trigger `prev_any` refs corrected for when `$prev = 1`.
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
- Converter output for related-source tags: `rel` option key was not renamed to `ref` and `src:ref` was not prepended; caused tags like `{{text rel:field|key:val}}` instead of `{{text src:ref|ref:field|key:val}}`; fixed via `MigrationRegistry` `type:'option'` entries registered by `bws_register_option_migrations()`
- 22 deprecated tag registrations missing `new_tag` (and migration config) caused admin scanner to show them as having no auto-convert path despite approved migration specs: `post_term_description/custom_text/custom_image`, `related_post_term_description/custom_text/custom_image`, `term_related_post_term_description/custom_text/custom_image`, `term_custom_text/image/date_single/date_range/datetime_single/datetime_range`, `try_custom_text/featured_image/custom_image/date_single/date_range/datetime_single/datetime_range`; all now carry `new_tag`, `source_inject`, `option_renames`, `value_renames`, `fixed_options`, and `datetime_transforms` as appropriate
- `MigrationRegistry::run_transform()`: empty-string `new_key` in `option_renames` now drops the option (unsets without creating new key); enables `src_1 => ''` pattern used by `try_*` slot migrations to suppress the slot-1 source (which defaults to `post`)
- `bws_register_v1_deprecated_tag_wrappers()`: six `term_custom_*` migration entries had wrong `new_tag` and a spurious `source_inject:'term'` — `src:term` is not a valid src value; term modifier tags (`term_text`, `term_image`, `term_datetime_single`, etc.) are a separate GB tag family that do not accept a `src` option. Corrected: `term_custom_text` → `term_text`, `term_custom_image` → `term_image`, `term_custom_date_single/range` → `term_datetime_single/range`, `term_custom_datetime_single/range` → `term_datetime_single/range`; `source_inject` removed from all six.
- `bws_register_early_deprecated_tag_migrations()`: `term_name` migration entry had `new_tag:'title'` + `source_inject:'term'` (invalid); corrected to `new_tag:'term_title'` with no source inject. `term_field_image` had `new_tag:'image'` + `source_inject:'term'` (invalid); corrected to `new_tag:'term_image'` with no source inject.
- Eight pre-v1.6 hand-written deprecated callbacks (`current_post_featured_image`, `current_post_meta_image`, `related_post_meta_image`, `related_post_url`, `post_acf_date_time_single`, `post_acf_date_time_range`, `term_name`, `term_field_image`) used hardcoded override strings in `bws_build_deprecation_preview_label()` instead of computing the replacement from actual options; override arg removed from all eight — preview labels now show the real migrated tag string. `bws_register_early_deprecated_tag_migrations()` added to register `MigrationRegistry` entries for all eight, enabling the admin converter and live preview labels.
- Scanner falsely counted post revisions as separate posts; `scan()` now excludes `post_type = 'revision'` and `post_status IN ('auto-draft','trash')` at SQL level
- Datetime tags failed for non-ACF meta fields (e.g. Pie Calendar's `_piecal_start_date` / `_piecal_end_date`): inline `get_field()` only path returned null for non-ACF keys, and even with `get_post_meta()` fallback, GB Pro's filter never fired. Field-extraction consolidation via `bws_read_field()` routes through `GenerateBlocks_Meta_Handler` — both ACF and raw post-meta keys now resolve correctly.

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
