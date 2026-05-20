# Changelog

## [1.7.0] — 2026-05-20

### Added — Link wrapping for text/title/datetime tags
- `linkTo` / `linkKey` / `newTab` options on `text`, `title`, `datetime_single`, `datetime_range` (base tags, `term_` modifier tags, and `try_` variants). Excluded: `content`, `permalink`, `image`.
- `linkTo` values: `permalink` (entity permalink) or `key` (URL from `linkKey` meta field). Unset = no link.
- `newTab` presence-flag: adds `target="_blank" rel="noopener noreferrer"` when set.
- Link options appear after fallback text in each template's option list.
- Link wrap applied after fallback resolves; empty `linkKey` or unresolvable URL skips wrapping without affecting tag output.
- `try_` tags: single `linkTo`/`linkKey` applies to the winning slot's entity (post or term). No per-slot link key.
- `term_` modifier tags: entity type routed automatically (term for base-source dispatch; post for `src:ref` dispatch; term for `srcTermIn` hop).
- New helpers in `content-helpers.php`: `bws_resolve_link_url()`, `bws_wrap_with_link()`, `bws_get_link_options()`, `bws_map_gb_link_option()`.
- Editor preview labels for link-eligible templates now annotate the configured link destination (e.g. `[Title (link: permalink)]`) and wrap the bracket string in `<a href="#">` so the link treatment is visible in the block editor even when the tag can't resolve a real value.

### Changed — Docs
- `docs/tag-matrix.md` renamed to `docs/tag-reference.md`; title updated to "BWS Dynamic Tags — Tag & Option Reference". All cross-links updated.
- `linkTo` meta-field destination token renamed `'meta'` → `'key'` for consistency with the plugin-wide `key` convention. Saved tags using `linkTo:meta` will not be present in the wild (v1.7.0 not yet released).

### Fixed — Migration: link option remapping for deprecated tags
- `related_post_content` `transform_callback` now maps old `link_to`/`link_field`/`new_window` options → `linkTo`/`linkKey`/`newTab`. Previously these were silently dropped. Content/excerpt migration targets still drop link options (content tag excluded from link wrap).
- Six deprecated tags that had GB-native `link` support (`related_post_title`, `related_post_custom_text`, `post_term_title`, `post_term_custom_text`, `term_related_post_title`, `term_related_post_custom_text`) now remap `link:post` → `linkTo:permalink`, `link:post_meta,<key>` → `linkTo:key|linkKey:<key>`, `link:term` → `linkTo:permalink`. Other GB link destinations (`author_archive`, `author_meta`, `author_email`, `comments`) dropped (no equivalent). Handled via `gb_link_remap` flag added to `MigrationRegistry::run_transform()`.

### Fixed — Migration: `related_post_content` transform and preview label
- `related_post_content` was a multi-field tag in the original (pre-N×M) codebase whose `target_field` option selected what to extract (`post_title`, `post_content`, `post_excerpt`, `custom`). The migration entry incorrectly mapped all instances to `{{content}}` regardless of `target_field`. Now branches correctly: `post_title`/absent → `{{title src:ref|ref:…}}`; `post_content` → `{{content src:ref|ref:…}}`; `post_excerpt` → `{{content src:ref|ref:…|use:excerpt}}`; `custom` → `{{text src:ref|ref:…|key:{custom_field}}}`. Both `key` and `rel` accepted as the relationship field (old tag used `key`).
- `MigrationRegistry::transform_tag()`: added `transform_callback` support — when a registry entry includes a `transform_callback` callable, it is invoked instead of `run_transform()`, enabling branching transforms that can't be expressed as rename maps.
- `bws_build_deprecation_preview_label()`: strip GB-injected `tag_name` key from `$options` before reconstructing the tag string for migration preview. GB's `parse_options()` always prepends `tag_name` to every callback's options array; without this strip, every deprecated tag preview included a spurious `tag_name:…` option in the suggested replacement.

## [1.6.2] — 2026-05-19

### Added
- Plugin action links: "Settings" link now appears in the Plugins list, pointing to the Tag Extensions settings page.

### Fixed — Editor preview: resolve-then-label (#21)
- Base tag callbacks (`text`, `content`, `title`, `image`), modifier callbacks, try callbacks, and datetime callbacks now attempt resolution before falling back to a structured label; tags that can resolve in the editor (e.g. `{{title src:current}}` while editing a post) show live values instead of labels
- Removed `REST_REQUEST` short-circuits from `bws_post_title_core`, `bws_post_excerpt_core`, `bws_post_custom_text_core`, `bws_post_content_core` — those guards prevented resolution even when GB had already provided a valid post ID
- `bws_resolve_post_by_source`: Mode 2b flat-row bail now skipped when GB has injected an explicit `id:` option (editor REST context), allowing `src:current` tags inside query loops to resolve via the injected post ID
- `bws_read_field`: Mode 2b array read now skipped when a valid `$post_id` was passed in, allowing custom-field and datetime tags inside query loops to read post meta via the resolved ID rather than attempting a flat-row array lookup

### Fixed — Datetime range editor preview
- Default range-end offset for `datetime_range` / `datetime_single` (unset `as`) corrected from +1 day to +1 hour, matching `as:time` behavior
- Preview separator default changed from ` – ` (spaced) to `–` (bare en-dash), matching frontend output
- Range preview now routes through `bws_format_date_range()` instead of naïve string concatenation, so same-day smart AM/PM consolidation (e.g. `10:02–11:02 AM`) and year-omission apply correctly in the editor
- `showCurrentYear` / `showMidnight` options now respected in preview (previously both ignored; `smart_time` defaulted to `false`, causing midnight suppression to never apply)

## [1.6.1] — 2026-05-18

### Fixed — Migration pipeline
- `MigrationRegistry::serialize_tag_string()`: PHP `true` values now serialize as bare keys (e.g. `showMidnight`, not `showMidnight:true`) matching GB's boolean serialization convention
- `apply_datetime_transforms()`: `smart_time` and `omit_current_year` no longer auto-injected based on absence of old key (old defaults serialized as bare keys, so absence is ambiguous). Only explicit `:false` override maps definitively to new boolean: `smart_time:false` → `showMidnight` bare; `omit_current_year:false` → `showCurrentYear` bare
- `apply_option_migration()` now loops until stable (cap 16) so overlapping/cascading option-migration entries all apply in one converter call
- `MigrationRegistry`: added `match_any_options` entry field (OR semantics) alongside existing `match_options` (AND semantics); `find_option_migration()` and scanner in `class-tag-converter.php` honor it; `group_option_entries_by_transform()` includes it in signature + group data
- Added `type:'option'` `MigrationRegistry` entries for live `datetime_single` and `datetime_range` tags carrying pre-v1.6 option keys (`date_time_field`, `time_field`, `start_field`, `start_time_field`, `end_field`, `end_time_field`, `separator`, `date_time_separator`, `fallback_text`, `format_type`, `custom_format`, `date_only`, `time_only`, `smart_time`, `omit_current_year`) — covers partially-migrated tags where tag name was renamed but option keys were not
- Added `type:'option'` entries for remaining live base-tag legacy keys: `fallback_text` → `fallback` (text, content, title, permalink, image); `via`/`from` → `src` (all 7 base tags); `type` → `use` + `custom_field` value → `key` (content); `return_type`/`fallback_url`/`field_key` → `as`/`fallback`/`key` (image, term_image, try_image); legacy slot keys `src_N`/`rel_N`/`key_N` → v1.6 slot syntax (all try_ tags)

## [1.6.0] — 2026-05-18

### Architecture (v1.5.0 → v1.6.0)
- Pattern B completed: related-variant mechanism replaced by standalone source classes (`RelatedPost`, `TermRelatedPost`)
- N×M per-source tag matrix replaced by base (source-agnostic) tags + context-modifier registry. Single `image`, `text`, `content`, `title`, `permalink`, `datetime_single`, `datetime_range` tags with `src` option (rename pipeline `via`→`source`→`src`; intermediate `source` rejected as GB-reserved key — see Fixed). Old per-source tags become deprecated wrappers via the migration registry below.
- Source dispatch simplified to two values: `''` (current entity) and `'ref'` (relationship field hop)
- Option ordering standardized per three-group structure: global formatting → per-slot → global fallback

### Added — Migration / registry infrastructure
- `MigrationRegistry` (`includes/classes/class-migration-registry.php`): unified transform registry supporting `type:'tag'` (deprecated tag name) and `type:'option'` (live base tag option-key) entries; shared 7-step `run_transform()` pipeline; public `parse_tag_string()`, `format_tag_string()`, `transform_tag()`, `apply_option_migration()`, `get_deprecated_tag_names()`, `get_option_migrations_by_tag()`
- `DeprecatedTagRegistry`: externally-registered deprecated tag wrappers with `transform_options()` migration pipeline (`source_inject`, `option_renames`, `value_renames`, `fixed_options`, `datetime_transforms`). Refactored as thin 4-method facade over `MigrationRegistry` (see Changed).
- `DeprecatedTagRegistry::has_migration_path( string $old_tag ): bool` for converter and admin UI use
- `combine_options` `MigrationRegistry` primitive: maps `[when_present, value_from] → new_key`; both old keys always dropped; new key emitted only when presence-flag and value both present. Runs as Step 2 of the transform pipeline (before `option_renames`). Used to migrate hand-written `srcTerm` + `tax:<slug>` strings via the Migration Tool; reusable for future combined-option migrations.
- `MigrationRegistry` option entries for all 7 base tags matching `tax` presence: combine `srcTerm` + `tax:<slug>` → `srcTermIn:<slug>` so the admin Migration Tool detects and converts legacy term-hop strings.
- Deprecated term-extraction tag entries (15 across `post_term_*`, `related_post_term_*`, `term_related_post_term_*` families): `$srcterm_fixed` injection removed; `tax` → `srcTermIn` rename merged into `option_renames` so migrator output matches the new key.
- `bws_register_option_migrations()` in `deprecated-tags.php`: registers `type:'option'` `MigrationRegistry` entries for all base tags carrying a `rel` option key — renames `rel` → `ref` and prepends `src:ref` (fixes broken converter output from the `via`→`src` rename cycle)
- `TagTemplateRegistry::register_modifier()` and `generate_base_try_tags()`: term_ and try_ modifier tags generated from modifier template descriptors

### Added — Editor preview labels
- `bws_build_preview_label( $options, $template )` in `content-helpers.php`: structured editor preview labels for unresolvable base and modifier tags (e.g. `['body_text' from Ref 'rel_post']`, `[Date like “April 24, 2026”]`, `[⚠ No taxonomy set]`)
- `bws_build_try_preview_label( $options, $base_template )` in `content-helpers.php`: structured editor preview labels for try_ tags. Walks slots 1-5, applies carry-forward, emits `[Try Text: 'a', 'b', Title]`-style summaries with per-slot source segments when source differs from slot 1. Per-slot warnings (`[⚠ Try: slot 2 no key, slot 3 no ref]`); empty-config warning (`[⚠ Try: no slots configured]`); image excluded for `as:url`/`as:id` modes. Helpers `bws_try_preview_prefix`, `bws_try_preview_field_part`, `bws_try_preview_source_part` for shape pieces. Try callback short-circuits on `$inst->context['bwsEditorPreview']` to call this builder.
- `assets/js/editor-preview-context.js`: injects `bwsEditorPreview: true` into GB's dynamic tag preview context; activates structured preview labels in block editor
- In-editor deprecated tag preview warnings: all deprecated callbacks check `$instance->context['bwsEditorPreview']` and return `[⚠ {{old_tag}} deprecated — use {{new_tag_with_actual_options}}]`; `bws_build_deprecation_preview_label()` helper calls `MigrationRegistry::transform_tag()` to show actual replacement
- `bws_build_preview_label()` reads `srcTermIn` (with `tax` legacy fallback) when deriving term-hop missing-taxonomy warning so the new key no longer triggers a false "No taxonomy set" preview.

### Added — Custom editor controls
- `bws-media-picker` (`wp.media()`) custom editor control for image-tag fallback (`assets/js/image-tag-controls.js`). Initial release also shipped a `bws-img-size` ComboboxControl; superseded mid-cycle by GB's native `image-size` support — see Changed.
- `srcTermIn` term-hop option on base tags (`text`, `content`, `title`, `permalink`, `image`, `datetime_single`, `datetime_range`): single persisted key encodes "term hop enabled + taxonomy slug" — empty/absent = disabled, slug = enabled. Replaces the prior `srcTerm` (boolean) + `tax` (slug) pair. `bws-term-hop` custom control (`assets/js/term-hop-control.js`) renders sibling CheckboxControl + ComboboxControl (taxonomies sourced via `wp.data` `core`, public-only); checkbox is React-local state, only the slug round-trips through `extraTagParams`. Resolves GB-reserved-key conflict where `tax` was extracted and silently dropped on modal reopen for cross-source base tags. Term-modifier (`term_*`) tags continue to use GB's native `tax` selector. Legacy `srcTerm` boolean stripped from state on mount so existing tags re-serialize cleanly.
- `srcTermIn` term-hop control on modifier tags: `register_modifier()` now reuses `bws_base_traversal_options()` so all modifier prefixes (`term_*`, `view_*`, etc.) get the term-hop control. Term-context base sources (e.g. `term_*` from `TaxonomyTerm`) gate visibility to `src:ref` only — at `src:current` the entity already IS a term, so inner-term-hop is meaningless. Post/unknown-context base sources (e.g. `view_*` from `PortalSource`) show the control unconditionally. Modifier callback dispatches term-hop via `bws_get_srcterm_terms( $post_id, $tax )` loop calling `term_fn` per term, returning the first non-empty result (mirrors `bws_base_image_callback`).
- `show_if` conditions `in:` and `not_in:` added to `editor-conditional-options.js`

### Added — Admin Migration Tool
- Admin Migration Tool (`includes/classes/admin/class-tag-converter.php`): `scan()` queries all non-revision posts via multi-LIKE SQL then PHP-level regex+parse verification; `migrate_post()` calls `wp_save_post_revision()` for pre-migration snapshot, applies full deprecated tag and option-key transforms, writes via `$wpdb->update()` + `clean_post_cache()` to avoid hook side-effects and duplicate revisions
- `assets/js/admin-tag-scanner.js`: Scan button → paginated AJAX scan; results table with post title, type, issues list (deprecated tags + option migrations), per-row Migrate button; Select All / Bulk Migrate Selected with progress bar; per-row status shows tag and option fix counts; ⚠ note when post type has no revision support
- Suppress mode for deprecated tags: callback returns `''` immediately when `SettingsPage::is_deprecated_tag_suppressed()` is true, preventing unprocessed tag strings on the frontend
- Modifier toggle controls in admin settings page (term_, try_ enable/disable)

### Added — Field-extraction helpers
- `bws_read_field( $key, $instance, $post_id, $single_only = true )` and `bws_read_term_field( $key, $term_id, $single_only = true )` in `content-helpers.php`: unified field-extraction helpers routing through `GenerateBlocks_Meta_Handler::get_meta()`. ACF reads now happen via GB Pro's `generateblocks_get_meta_pre_value` filter — no inline `get_field()` calls in helpers. Loop-row context detection cached on `$instance->context['bws/loopItemPostId']` (Mode 2a: row resolves to post → read post meta; Mode 2b: flat repeater row → read `$loop_item[$key]` directly). `DISALLOWED_KEYS` security guard mirrors GB native posture; protected meta allowed on frontend (matches `Meta_Handler::get_meta()` behavior, supports plugins like Pie Calendar that store data in `_`-prefixed keys).
- `bws_get_loop_row_context( $instance ): array` in `content-helpers.php`: single source of truth for GB Pro loop-row detection. Returns `['loop_item' => mixed, 'row_post_id' => int|false, 'in_loop' => bool]`. Caches `bws/loopItemPostId` on `$instance->context` so per-block detection runs once. Consolidates 5 prior inlined detection blocks (see Changed).

### Added — Plugin metadata
- Plugin header `Requires Plugins: generateblocks-pro` declares GB Pro as a hard dependency. WP 6.5+ enforces this in `/wp-admin/plugins.php` (cross-references both directions, prevents deactivation while dependent active). Runtime check for `class_exists( 'GenerateBlocks_Meta_Handler' )` enforces GB 2.0+ minimum (since core `Requires Plugins` syntax does not support version constraints).
- Plugin header `Requires at least` bumped from 6.0 to 6.5 (matches `Requires Plugins` minimum).

### Changed — Option key renames
- `via`/`from` option renamed to `src`; `from` (field selector) renamed to `use` across all base tags and modifier callbacks
- Datetime option keys renamed to camelCase names: `time_sep` → `timeSep`, `range_sep` → `rangeSep`, `show_current_year` → `showCurrentYear`, `show_midnight` → `showMidnight`, `key2` → `timeKey` (single), `key`/`key2`/`end`/`end2` → `startKey`/`startTimeKey`/`endKey`/`endTimeKey` (range); mapper functions and migration rename targets updated accordingly
- `taxonomy` option key renamed to `tax` in post-context term-extraction templates (`bws_post_term_extraction_options`, `bws_post_term_image_options`, `PostTermRelatedPost::get_source_options()`); readers accept both `tax` and `taxonomy` for backward compatibility
- Canonical-token refactor for `src` and `use` options across base + modifier tags: source files now declare semantic tokens (`current`, `key`, `content`) as first option values; `bws_strip_default_select_values()` (in `content-helpers.php`) flips first option's value to `''` at registration boundary so wire format stays clean (GB drops empty values). Read sites apply `?? '<canonical>'` defaults: `src` → `'current'`, text/image `use` → `'key'`, content `use` → `'content'`. Content `use` reordered per matrix (content, key, excerpt). Required for try_ slot 2+ "Same as Previous" semantic to disambiguate "inherit" from "explicitly default". Wire format unchanged — existing saved tags continue working.

### Changed — Try-tag overhaul
- Try-tag use-mode dispatch wrappers added (`bws_try_text_post_dispatch`, `bws_try_text_term_dispatch`, `bws_try_content_post_dispatch`, `bws_try_content_term_dispatch`, `bws_try_image_post_dispatch` in `base-tags.php`); template `try_core_fn` / `try_term_fn` now point to these so each slot routes by its resolved `use` value (e.g. slot use=`title` → `bws_post_title_core`, slot use=`featured` → `bws_featured_image_core`). Previous direct pointers to the custom-field core functions ignored `use`, causing all non-key modes to fail.
- Try tag generator (`generate_base_try_tags()`) overhauled: per-slot `use` selector added for `try_text` and `try_content` (in addition to `try_image`); slot 2+ src + use dropdowns prepend "Same as Previous" inherit row (`same` value, stripped to `''` per `_strip_default` semantics); slot ≥2 raw `''` = inherit prior carry-forward, explicit `current`/`key`/etc. tokens flow through as explicit overrides. Slot N labels: `Source N`, `Relationship Field N`, `Field Key N`, `[Text/Image/Content] Field N` (suffix); `Source N: Get from taxonomy term?`, `Source N: Taxonomy` (prefix for `srcTermIn` term-hop control). `srcTerm` (boolean) + `tax` (slug) per-slot pair replaced by `srcTermIn` combined `bws-term-hop` control (matches base tag pattern post-v1.6.0). Slot ≥2 key field hidden when use is `same` (inherits both `use` and `key` from prior slot); shown only when user explicitly picks a key-needing `use` value (override mode).
- `text` + `content` modifier templates: `use` option added (text: `key`, `title`; content: `content`, `key`, `excerpt`); `try_per_slot_use` + `try_use_no_key_values` flags set so try_ slot 2+ slots can independently choose field type.
- `try_image`: per-slot `use` added (`try_per_slot_use`); `psk` key-check skips `use:featured` slots via `try_use_no_key_values`

### Changed — Label / source-option unification
- Base + modifier tag labels updated to matrix-prescribed forms: `src` → `Source`, `ref` → `Relationship Field`, `use` → `Text Field`/`Content Field`/`Image Field` (was verbose `Get text from:`/etc.).
- Source-option labels unified across base, modifier, and try_ tags: `src:current` → "Current", `src:ref` → "In Reference/Relational Field". `register_modifier()` reuses `bws_base_source_option()` and `bws_base_traversal_options()` directly so labels stay synchronized with base tags. Drops the prior modifier-specific labels ("Current (no traversal)", dynamic source-label for ref) and try_ slot labels ("Current Post", "Related Post (ref field)").
- Image `as:title` option label changed from "Title" to "Image Title" to disambiguate from text/content `use:title` ("Title/Name") in the same UI surface.
- `term_*` modifier tag titles now suffixed "(term-based)" (e.g. "Image (term-based)") matching the `view_*` "(View-based)" pattern; `register_modifier()` `modifier_label` parameter set on the term modifier registration.
- `SecondRelatedPost` label: "Post → 2nd Rel. Post"; `PostTermRelatedPost` label: "Post → Term → Rel. Post"

### Changed — Editor preview labels
- `bws_build_preview_label()` shape redesigned: literal user-supplied identifiers (meta keys, ref names) now wrapped in straight single quotes (`'X'`); display values (fallback strings, formatted datetimes) keep curly double quotes (`“X”`); fallback append moved from `· fallback: …` to `(fallback: …)`. Field-part shape: `text` uses bare key (`['body_text']`), `content` uses key + type-noun (`['body_text' Content]`), `image` uses key + type-noun + as-suffix (`['hero' Image Alt Text]`). Ref segment renders as `Ref 'rel_post'` (was `Ref (rel_post)`). Marker conventions documented in `docs/tag-reference.md` §Editor preview label schema.
- Base tag callbacks (`text`, `content`, `title`, `image`, `datetime_single`, `datetime_range`) and `term_` modifier callbacks: return `bws_build_preview_label()` in editor preview context instead of static REST placeholders (`[Custom Field]`, `[Title]`, etc.)

### Changed — Registry refactor
- `DeprecatedTagRegistry` refactored as thin 4-method facade over `MigrationRegistry`; external callers (e.g. `bws-portal-system`) unchanged; `transform_options()` delegates to `MigrationRegistry::transform_tag()`
- `required_options` field on `MigrationRegistry` entries: array of post-rename option keys whose presence is required for the migrated tag to reproduce the deprecated tag's default behavior. Display-only metadata for the admin migration preview — does not affect transform pipeline. Rendered by `SettingsPage::format_migration_target()` as `<key>:…` placeholder segments alongside `src:<inject>` and `fixed_options`. Populated with `srcTermIn` on all 15 term-extraction deprecated tag entries (`post_term_*`, `related_post_term_*`, `term_related_post_term_*` families) so the term-hop key shows in migration previews where it's required for the same output as the deprecated tag.
- Eight pre-NxM hand-written deprecated wrappers (`current_post_featured_image`, `current_post_meta_image`, `related_post_meta_image`, `related_post_url`, `post_acf_date_time_single`, `post_acf_date_time_range`, `term_name`, `term_field_image`) flipped from their original GB tag types (`'media'`/`'post'`/`'related'`/`'term'`) to `'deprecated'`, matching the type used by NxM `MigrationRegistry`-driven wrappers. Aligns editor grouping for all deprecated entries.

### Changed — Admin UI redesign
- Admin deprecated tags settings redesigned: per-tag enable/disable replaced by two group-level radio sets — **Has migration path** and **No migration path** — each with three modes: Keep / Suppress / Disable; tag membership stored per-tag, toggled by group; collapsible `<details>` reference lists show tags in each group
- Migration Tool moved to a separate section outside the settings `<form>`; replaces per-tag List Posts / Convert buttons with a unified post-level scan and migrate workflow
- Admin settings page reorganized: Migration Tool moved into the main settings form between Deprecated Tags and Diagnostics so the deprecated-tags reference, deprecated-options reference, and Migration Tool now sit adjacent (issue #4). New "Deprecated Options" section lists `type:'option'` migrations grouped by transform signature so each unique rename appears once with an "Applies to:" tag list rather than repeating per match_tag (issue #3).
- Deprecated tag list rendering: per-row migration target now reconstructed via new `SettingsPage::format_migration_target()` helper (Approach A) — shows `{{<new_tag>[ src:<inject>][|<fixed_options>][|…]}}` with the ellipsis serialized as a final pipe segment inside the braces to indicate user options carry over via `option_renames` / `value_renames` / `combine_options` / `datetime_transforms`. Old tag wrapped as `{{<old_tag>}}` for symmetry (issue #2).
- Deprecated option rows render structured rename description (`<old_keys>` → `<new_keys>` *(reason)*) plus "Applies to:" tag list. Old/new keys derived from `option_renames` + `combine_options`; reason extracted from the trailing parenthetical of the entry's `label`. Tag preview line dropped for option rows (not informative when grouped). New `SettingsPage::group_option_entries_by_transform()` collapses duplicates by signature (`option_renames` + `value_renames` + `combine_options` + `source_inject` + `fixed_options` + `match_options`).

### Changed — Image consolidation
- `image` base tag type changed from `'media'` to `'cross-source'`; `supports:['image-size']` removed in favor of explicit PHP options
- `term_image` modifier: `use:featured` gated behind `src:ref` (term entities have no featured image)
- Image template option definitions consolidated to single source of truth: `register_modifier()` no longer rebuilds `as`/`use`/`key`/`fallback` for image tags — modifier tags now consume the same template descriptor `options` array as `try_image`. Drift between `image`, `term_image`, `view_image`, `try_image` field labels eliminated. `key` option added to image template descriptor `options` (was previously only declared in modifier rebuild).
- Image tags now use GenerateBlocks' native `image-size` support instead of custom `bws-img-size` ComboboxControl. The custom control couldn't recognize stored `size:` values because GB's `DynamicTagSelect` destructures the reserved `size` key from `extraTagParams` before custom controls receive it. Native control handles `size:` parsing/serialization correctly and strips the `'full'` default automatically. Affects base `image`, `term_image`, modifier image tags (e.g. `view_image`), and `try_image`. Per-tag `$tag_supports` now built from a copy of `$base_supports` to avoid mutation across template iterations (prevented `image-size` support leaking to non-image tags like `view_datetime_*`).
- Modifier callback (`make_modifier_callback`) now dispatches image template by `use` option on post-context paths: `use:featured` → `bws_featured_image_core`, otherwise → `post_fn` (`bws_custom_image_core`). Previously the post-context path always called `bws_custom_image_core` regardless of `use`, so `view_image use:featured` (and any post-context modifier image with `use:featured`) returned empty.
- `bws_get_meta_image_data()` (image-helpers.php) now performs a two-pass meta read: pass 1 with `single_only=true` (returns scalar for ACF URL/ID return formats), pass 2 with `single_only=false` only when pass 1 yields nothing (returns array for ACF Image Array return format). Works around a `GenerateBlocks_Meta_Handler::get_value()` behavior where `single_only=false` returns the fallback (`''`) for plain scalars when an upstream filter (e.g. ACF `generateblocks_get_meta_pre_value`) populates the value, causing URL/ID-format ACF image fields to return empty. Provider-agnostic — any meta provider hooking the GB filter benefits.

### Changed — Field-extraction consolidation
- All 6 inline `get_field()/get_post_meta()/get_term_meta()` field-extraction call sites consolidated through `bws_read_field()` / `bws_read_term_field()`: `bws_get_meta_image_data()` (image-helpers), `bws_get_term_field_image_data()` (taxonomy-helpers, `$taxonomy` param dropped), `bws_post_custom_text_core()` and `bws_post_content_core()` custom_field branch (content-tags), `bws_term_custom_text_core()` (taxonomy-tags), and `bws_get_acf_field_value()` (datetime-helpers) — the latter retained as a thin shim that routes ACF term object_id syntax (`"{taxonomy}_{term_id}"`) to `bws_read_term_field()` and post IDs to `bws_read_field()`.
- `bws_parse_combined_date_time()`, `bws_get_acf_field_value()`, `bws_get_meta_image_data()`: `$instance` parameter threaded through so loop-row context detection works for datetime + image tags.
- `bws_post_custom_text_core()`, `bws_post_content_core()` (custom_field branch), `bws_get_meta_image_data()`: short-circuit on `! $post_id` relaxed when block instance is in a loop-row context (`generateblocks/loopItem` set), allowing field reads against the row entity.
- `bws_resolve_post_by_source()`: now Mode 2 aware. Mode 2a (loop row resolves to post): `src:''` returns row post ID, `src:ref` reads `ref` meta from row post. Mode 2b (flat repeater row): `src:''` returns `false` so callback can fall through to row data; `src:ref` reads `$loop_item[$ref]` directly. ACF Relationship/post_object subfields returning a list (no `ID` key) auto-unwrap to the first entry.
- `try_*` slot dispatch in `TagTemplateRegistry::generate_base_try_tags()`: Mode 2b (flat repeater row) skip-on-`! $post_id` was too aggressive — `bws_resolve_post_by_source()` correctly returns `false` for `src:''` in Mode 2b, but the slot's core function can still resolve via `$loop_item[$key]`. Now allows fallthrough when `$in_loop_row && '' === $last_src && ! empty( $last_key )`, so `try_text`, `try_content`, etc. can read flat-repeater row keys directly across slots.
- `bws_extract_post_id()`: handles list-of-posts return formats (Relationship/post_object subfield with no max_size limit). When passed an array without an `'ID'` key, takes the first entry and recurses. Lets `bws_resolve_post_by_source()` Mode 2 paths drop their inline list-unwrap workaround.
- `TermRelatedPost::resolve_id()` (`class-term-related-post.php`): inline `get_field( $rel, 'term_'.$term_id )` replaced with `bws_read_term_field( $rel, $term_id, false )`. Routes through `Meta_Handler` for ACF integration via filter; consistent with rest of field-extraction pipeline. Falls back to raw `get_field()` if helpers unavailable.
- `bws_get_loop_row_context( $instance )` extracted as single source of truth — replaces 5 inlined detection blocks across `bws_read_field()`, `bws_resolve_post_by_source()`, `bws_get_meta_image_data()`, `bws_post_content_core()`, `bws_post_custom_text_core()`, and `bws_custom_image_core()`.

### Changed — show_if extension / source cleanup
- `show_if` / `show_if_any` support added to `editor-conditional-options.js` (OR conditions)
- `SourceInterface` and `AbstractSource` cleanup: removed related-variant methods post Pattern B
- `get_traversal_options()` removed from `SourceInterface`, `AbstractSource`, and all source classes; `register_modifier()` now hardcodes standardized `ref` traversal sub-option (Q8 resolution)
- `date-helpers.php` renamed to `datetime-helpers.php`; `date-tags.php` deleted (content merged into `datetime-tags.php` in v1.6.0)

### Removed
- `bws_get_acf_field_value()` from `datetime-helpers.php`: thin shim retained through Phase 2 of the field-extraction consolidation. Replaced by inline `bws_read_field()` / `bws_read_term_field()` calls in `bws_parse_combined_date_time()` with ACF term object_id (`"{taxonomy}_{term_id}"`) detection inlined.
- `generate_all_tags()` and `generate_try_tags()` from `TagTemplateRegistry` — N×M loop eliminated; deprecated wrappers now active for all old per-source tag names
- `register_template()`, `get_templates()`, `make_direct_callback()`, `make_entities_callback()`, `compute_tag_default()` from `TagTemplateRegistry` (N×M support methods)
- N×M template registration functions from tag files: `bws_register_post_content_tag_templates()`, `bws_register_image_tag_templates()`, `bws_register_date_tag_templates()`, `bws_register_datetime_tag_templates()`, `bws_register_taxonomy_term_extraction_templates()`
- `$templates` static property from `TagTemplateRegistry`
- `bws_extract_text_field()`, `bws_extract_url_field()`, `bws_get_link_url()` from `content-helpers.php` (dead code — no callers in active files)
- `TagConverter::list()` and `TagConverter::convert()` — replaced by unified `scan()` + `migrate_post()` + paginated batch AJAX
- Per-tag List Posts / Convert buttons in admin deprecated section — replaced by Migration Tool
- "Enable benchmark admin page" diagnostics toggle, `is_benchmark_page_enabled()` accessor, sanitizer entry, and activation-seed key — dead UI; benchmark page never wired up. Stale `benchmark_page` key in saved options is harmless and ignored.

### Added — Activation defaults
- `register_activation_hook` (`bws_dynamic_tags_activate()`) seeds default settings on fresh activation when no option row exists. Deprecated tag groups (`mode_with_path`, `mode_without_path`) default to `'disable'` so legacy N×M tags are removed from GB out of the box on new installs. Existing installs (option row present) are left untouched.

### Changed — Admin UI polish
- Deprecated Options reference list collapses by default (matches Deprecated Tags list); `<details open>` → `<details>` in `SettingsPage::render_page()`.

### Documentation
- `docs/gb-constraints.md` (promoted from memory): GB editor/runtime constraints catalog (tag prefix rule, custom tag types, supports keys, reserved option keys, custom controls registered) moved from local memory into the tracked project docs. Bidirectionally cross-linked with `docs/deprecated-tags-options.md` so future renames driven by GB constraints have a documented justification path.
- `docs/deprecated-tags-options.md`: new **Superseded** status added to the option rename tracker legend. `via`/`from` → `source` rename marked **Superseded** (GB-reserved key); replacement row `via`/`from` → `src` added as **Implemented**. `via:tax` → `srcTerm` boolean marked **Superseded** (cross-source base tags drop reserved `tax` on modal reopen); replacement row `srcTerm` + `tax` pair → `srcTermIn` slug added as **Implemented**. Cross-link to `gb-constraints.md` added near the top.
- `docs/deprecated-tags-options.md` (new): migration reference containing all deprecated N×M tag name tables, template key renaming tracker, and option name renaming tracker; moved from `docs/tag-reference.md`
- `docs/tag-reference.md`: removed N×M matrix tables and rename trackers; replaced with forward references to `docs/deprecated-tags-options.md`; default-enabled logic section updated for v1.6.0 modifier group + deprecated wrapper toggles
- `docs/plugin-integration.md`: new §2 (Registering a Context Modifier with `register_modifier()` example and parameter reference); new §8 (Renaming a Modifier Prefix — converter-based migration pattern); §5 helper table corrected; §6 admin settings rewritten for v1.6.0; §7 deprecated wrapper parameter table updated (removed `source_key`/`is_related`, added all new fields)
- `CLAUDE.md`: simplified to dependency + development summary; defers to `README.md` and `docs/tag-reference.md`
- `README.md`: expanded from one-liner to proper overview with requirements and architecture pointer
- `docs/post-content-processing-reference.md`: rewritten against current implementation. Removed stale three-tier processing-mode documentation (Basic/Limited/Full), Query Monitor auto-downgrade, `processing_level` tag option, shortcode-toggle, and self-reference recursion check — none survive in plugin-era code. Documented current pipeline: single `bws_process_post_content()` entry, automatic `bws_process_post_content_fallback()` on low-memory, `bws_extract_and_queue_inline_styles()` + `bws_queue_inline_css()` / `bws_output_queued_inline_css()` deferral of cross-post GB-inlined `<style>` elements to `wp_footer`, `bws_safe_content_output()` strip of destructive GB options (`trunc`/`case`/`wpautop`/`link`). Standalone-era version log preserved at the bottom under a "Pre-Plugin-Integration History" header.
- Docs ownership split between `gb-constraints.md` and `tag-reference.md` clarified: `gb-constraints.md` now contains only GB-imposed behaviors (default serialization, boolean shape, `parse_options()` semantics, reserved keys, tag prefix rule, supports keys). Our plugin's response to those constraints (registration-boundary default-strip mechanism `bws_strip_default_select_values()`, canonical-token first values, `image`/`term_image`/`try_image` `as:url` always-serialized opt-out) consolidated into a new `tag-reference.md` §Default serialization strategy section. Removed duplicate `as` exception paragraph from §Base tag GB types and §Option render order — both now defer to the strategy section. Custom editor control registry (`bws-media-picker`, `bws-term-hop`) moved from `gb-constraints.md` into new `tag-reference.md` §Custom editor controls registered section. `gb-constraints.md` `image-size` reserved-supports advice flipped from "use a prefixed name" to "use GB's native control" (matches v1.6.0 retirement of `bws-img-size`). `gb-constraints.md` `media` type entry updated from "planned for removal" to past-tense statement of v1.6.0 behavior.
- `docs/tag-reference.md` simplification: Notation table (✅, —, GB, ★, ☐) and GB built-in collision-check paragraph moved to `docs/deprecated-tags-options.md` where the symbols are actually used; outdated "approved names" caveat removed (option names are implemented in v1.6.0); duplicate "Potential future traversals" section dropped (statuses already in §`src` option values table); plugin-specific external-modifier subsection removed and external-prefix rows in §Modifier prefixes and §Source classes neutralized to generic external-plugin descriptors; "(planned architecture)" qualifier dropped from §Base tag GB types heading.
- `docs/plugin-integration.md`: example identifiers neutralized — all example prefixes and class/function names in §2, §7, §8 walkthroughs renamed so the doc reads as generic guidance rather than referencing any specific third-party plugin.
- `README.md`: overview table added (one row per base tag — `text`, `image`, `content`, `datetime_single`, `datetime_range`, `title`, `permalink`) describing each tag's user-facing purpose. Footnote flags term-context behavior for tags marked with `*` as not yet tested without `term_` prefix. Note added about custom field names being supplied manually (no dropdown selector yet). `content` tag description revised to describe block-CSS-for-embedded-post-content consolidation into the page footer rather than fallback-pipeline specifics.
- `CLAUDE.md`: documentation ownership policy added — content-type-to-doc ownership table, update triggers per change type, cross-link rules. Single source of truth: each content type has one owner doc; other docs link rather than duplicate. `docs/tag-reference.md` opening paragraph + §Updating this document forward-reference the policy. `MEMORY.md` trimmed to one-line pointers per the cross-link rule (removed inlined option-key lists, GB-type assignments, architecture-shift narrative — all derivable from the docs they point at).

### Fixed — `source` → `src` GB-destructure rename (cross-cutting)
- `bws_base_source_option()`: option key renamed `source` → `src`; labels corrected to "Current" and "In Reference/Relational Field". GB's `DynamicTagSelect` unconditionally destructures `'source'` from parsed tag params before spreading into `extraTagParams`, so any PHP option named `source` is silently eaten — the editor control never receives the value and the option is dropped on save. `src` avoids the conflict. PHP callbacks read `src ?? source` for backward compatibility. C7 `type:'option'` migration entries registered for all 7 base tags to rename `source` → `src` in saved content. `source_inject` in `MigrationRegistry` updated to emit `src` key.
- `bws_base_traversal_options()`: `show_if` key updated `source` → `src` to match renamed option
- `TagTemplateRegistry::register_modifier()`: option key `source` renamed to `src` (and `show_if` references updated). The earlier `source`→`src` rename in v1.6.0 was applied to base tags but missed `register_modifier()`, so all generated modifier tags (e.g. `term_*`, `views_*`) had their source dropdown silently eaten by GB's `DynamicTagSelect` destructure — users could not pick the "ref" traversal option in any modifier tag.
- `generate_base_try_tags()`: slot 1 option keys were `source`/`use`/`1-ref`/`1-srcTerm`/`1-tax`/`1-key`; same GB destructure bug caused `source` to be eaten, and `1-` prefix on remaining slot-1 keys diverged from spec. All slot-1 keys now un-prefixed: `src`, `ref`, `srcTerm`, `tax`, `use`, `key`. Slots 2–5 unchanged (`N-src`, `N-ref`, etc.). `$src_opts` merges in callback updated to pass `src` key. Slot trigger `prev_any` refs corrected for when `$prev = 1`.
- `TagTemplateRegistry::make_modifier_callback()`: unset-`src` branch hardcoded `term_fn` dispatch, which assumed the modifier prefix entity was always a term. Broke post-context modifiers (e.g. `views_*` from `bws-portal-system`): bare `{{views_content}}` resolved a post ID via `PortalSource` then called `bws_term_description_core` with that post ID. Now dispatches by base source's `get_context_type()` — `term` → `term_fn`, `post` → `post_fn`. `term_*` modifier behavior unchanged; `views_*` modifier tags now render correctly.

### Fixed — Loop-row resolution
- `bws_get_loop_row_context()`: `row_post_id` resolution was gated on `generateblocks/queryType === 'post_meta'`, so standard `WP_Query` post loops left `row_post_id = false` while `in_loop = true`. `bws_resolve_post_by_source()` for `src:'current'` then hit its Mode 2b guard and returned `false`, breaking any base tag inside a regular query loop (e.g. `{{text key:foo|srcTermIn:bar}}` rendered empty). Now extracts a row post id whenever `loop_item` is non-array (`WP_Post` / numeric — covers standard query loops and post-meta relationship loops GB Pro materializes into `WP_Post` instances), or under `post_meta` queryType when the array carries an explicit `ID` key. Flat repeater rows (Mode 2b) still fall through correctly because `bws_extract_post_id()`'s list-of-posts fallback no longer runs on array `loop_item`s without `ID`.
- Loop-row context detection only matched `is_array( $loopItem )` rows, but GB Pro's post_meta loop hands rows as `WP_Post` objects (ACF Relationship field with return_format=object) or numeric IDs (return_format=id). All Mode 2 detection sites now accept `array | WP_Post | numeric` so `{{title}}`, `{{text key:...}}`, `{{datetime_*}}` tags inside relationship loops correctly resolve to row entities instead of falling back to the outer post.

### Fixed — Preview-label safety
- `bws_build_preview_label()`: replaced straight double quotes (`"..."`) around `$fallback` value and datetime `$formatted` value with curly quotes (`“...”`, U+201C/U+201D). Straight quotes broke `<img alt="...">` attribute when `image as:alt`/`as:caption` rendered preview labels containing user-controlled fallback strings; curly quotes are attribute-safe. Affects three call sites (warning branch, datetime branch, final-assembly branch). Doc examples in `tag-reference.md` updated to match.

### Fixed — Try-tag option ordering
- `try_image`, `try_datetime_single`, `try_datetime_range`: Group 1 formatting options (`as`, `size`, `format`, `timeSep`, `rangeSep`, `showCurrentYear`, `showMidnight`) were appended after per-slot options instead of preceding them; corrected via `leading_options` on modifier template descriptors
- `datetime_single`, `datetime_range` base tags: source block appeared before formatting options; reordered to formatting → source → field keys → fallback per spec

### Fixed — Image fallback
- `image`, `term_image`, `try_image` tags: `fallback` option (set by `bws-media-picker`) was ignored at runtime; core functions read `id` (legacy GB media-type key) instead of `fallback`; now read `fallback ?? id` with backward compat for pre-v1.6.0 saved tags
- `bws_term_custom_image_core`: read `fallback_url` instead of `fallback`; now reads `fallback ?? fallback_url`
- `bws_handle_media_fallback`: only accepted numeric attachment IDs; now also resolves attachment URL via `bws_get_attachment_id_from_url()` to support `bws-media-picker` output (stores URL, not ID)
- `bws_register_option_migrations()`: added `type:'option'` entries for `image`, `term_image`, `try_image` to rename `id → fallback` on tags saved in v1.5.x when those tags still used `type:'media'`
- `$fi_renames` / `$ci_renames` in `bws_register_v1_deprecated_tag_wrappers()`: `id → fallback` rename now included so deprecated-tag converter migrations carry the rename through to the target tag
- `ImageSizeControl` (`image-tag-controls.js`): `generateBlocksInfo.imageSizes` array items not normalized to `{ value, label }` objects; `ComboboxControl` crashed with `Cannot read properties of undefined (reading 'replace')` when items were strings or lacked a string `label` property

### Fixed — Migration entry corrections
- `DeprecatedTagRegistry::has_migration_path()` returned `true` for all entries; now checks `new_tag` non-empty
- Converter output for related-source tags: `rel` option key was not renamed to `ref` and `src:ref` was not prepended; caused tags like `{{text rel:field|key:val}}` instead of `{{text src:ref|ref:field|key:val}}`; fixed via `MigrationRegistry` `type:'option'` entries registered by `bws_register_option_migrations()`
- 22 deprecated tag registrations missing `new_tag` (and migration config) caused admin scanner to show them as having no auto-convert path despite approved migration specs: `post_term_description/custom_text/custom_image`, `related_post_term_description/custom_text/custom_image`, `term_related_post_term_description/custom_text/custom_image`, `term_custom_text/image/date_single/date_range/datetime_single/datetime_range`, `try_custom_text/featured_image/custom_image/date_single/date_range/datetime_single/datetime_range`; all now carry `new_tag`, `source_inject`, `option_renames`, `value_renames`, `fixed_options`, and `datetime_transforms` as appropriate
- `MigrationRegistry::run_transform()`: empty-string `new_key` in `option_renames` now drops the option (unsets without creating new key); enables `src_1 => ''` pattern used by `try_*` slot migrations to suppress the slot-1 source (which defaults to `post`)
- `bws_register_v1_deprecated_tag_wrappers()`: six `term_custom_*` migration entries had wrong `new_tag` and a spurious `source_inject:'term'` — `src:term` is not a valid src value; term modifier tags (`term_text`, `term_image`, `term_datetime_single`, etc.) are a separate GB tag family that do not accept a `src` option. Corrected: `term_custom_text` → `term_text`, `term_custom_image` → `term_image`, `term_custom_date_single/range` → `term_datetime_single/range`, `term_custom_datetime_single/range` → `term_datetime_single/range`; `source_inject` removed from all six.
- `bws_register_early_deprecated_tag_migrations()`: `term_name` migration entry had `new_tag:'title'` + `source_inject:'term'` (invalid); corrected to `new_tag:'term_title'` with no source inject. `term_field_image` had `new_tag:'image'` + `source_inject:'term'` (invalid); corrected to `new_tag:'term_image'` with no source inject.
- All 16 `term_related_post_*` deprecated entries (`deprecated-tags.php`): `new_tag` flipped from base post tags (`title`, `text`, `image`, etc.) to term-modifier equivalents (`term_title`, `term_text`, `term_image`, etc.). Convention: any tag starting with `term_` starts on a term; the modifier tag's `src:ref` traversal handles the term→post hop. Term-extraction subset (`term_related_post_term_*`) carries the second hop via existing `tax → srcTermIn` rename in `option_renames` (issue #1).
- Eight pre-v1.6 hand-written deprecated callbacks (`current_post_featured_image`, `current_post_meta_image`, `related_post_meta_image`, `related_post_url`, `post_acf_date_time_single`, `post_acf_date_time_range`, `term_name`, `term_field_image`) used hardcoded override strings in `bws_build_deprecation_preview_label()` instead of computing the replacement from actual options; override arg removed from all eight — preview labels now show the real migrated tag string. `bws_register_early_deprecated_tag_migrations()` added to register `MigrationRegistry` entries for all eight, enabling the admin converter and live preview labels.

### Fixed — Misc
- `DeprecatedTagRegistry` loop: undefined `$sk` variable
- Datetime converter: boolean injections use `'true'` string, not `'1'`
- Scanner falsely counted post revisions as separate posts; `scan()` now excludes `post_type = 'revision'` and `post_status IN ('auto-draft','trash')` at SQL level
- Datetime tags failed for non-ACF meta fields (e.g. Pie Calendar's `_piecal_start_date` / `_piecal_end_date`): inline `get_field()` only path returned null for non-ACF keys, and even with `get_post_meta()` fallback, GB Pro's filter never fired. Field-extraction consolidation via `bws_read_field()` routes through `GenerateBlocks_Meta_Handler` — both ACF and raw post-meta keys now resolve correctly.
- `bws_content_debug()` and `bws_content_debug_start()` (content-helpers.php) now gated solely by the admin "Enable benchmark logging" setting; previously also activated by `WP_DEBUG`, which bypassed the user-facing toggle. Per-request post content benchmark output (`[BWS Content] post_id=… time=… mem_delta=…`) now respects the setting in all environments.

### Deprecated (N×M → base-tag wrappers, Commit C1)
- 75 N×M source × template generated tag names deprecated with `DeprecatedTagRegistry` entries covering all post-context, term-context, and term-extraction combinations
- Three callback factories added (`bws_make_deprecated_post_callback`, `bws_make_deprecated_term_callback`, `bws_make_deprecated_term_extraction_callback`) for runtime resolution via `SourceRegistry`
- All migration-capable entries include `source_inject`, `option_renames`, `value_renames`, `fixed_options`, and `datetime_transforms` for converter use
- Pre-C2 dup-check in `bws_register_deprecated_tags()`: skips deprecated entries whose `old_tag` is still live in GB's registry (N×M active); wrappers activate automatically once C2 removes `generate_all_tags()`

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
