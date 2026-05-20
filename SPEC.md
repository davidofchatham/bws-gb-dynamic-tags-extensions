# SPEC — Remove deprecated tag registration + callbacks

> Note: prior release's SPEC (Traversal Pipeline Phase 1, 1.14.0) already post-ship-migrated — see CONTEXT.md §I9, docs/tag-reference.md, `.claude/plans/archive/traversal-pipeline.md`, CHANGELOG 1.14.0, docs/future-work.md. This SPEC supersedes that stub as the active in-flight spec.

## §G Goal

Remove GB registration + runtime callbacks for all current deprecated tags. Keep migration data (MigrationRegistry entries, option_renames, etc.) so admin Tag Converter + settings page stay functional. Keep infrastructure (factories, helpers, registry classes) for future deprecated tag use.

## §C Constraints

- C1: No changes to `bws_dynamic_tags_register_all()` or any external caller.
- C2: `TagConverter::scan()` + `migrate_post()` must continue to find and transform all deprecated tags in post content after change.
- C3: Settings page deprecated-tag list must still render all ~130 entries with migration targets.
- C4: `bws_register_option_migrations()` untouched — fixes current base tags, unrelated to this removal.
- C5: 4 callback factories + `bws_build_deprecation_preview_label()` + `bws_deprecated_tag_notice()` kept for future use.
- C6: Translation layer cleanup (`bws_base_map_datetime_options()` etc.) deferred to follow-on refactor.

## §I Interfaces

- `includes/tags/deprecated-tags.php` — primary edit target
- `includes/classes/class-migration-registry.php` — read-only; provides `get_deprecated_tag_names()`, `transform_tag()`, `apply_option_migration()`
- `includes/classes/class-deprecated-tag-registry.php` — read-only facade; `get_all()` → `MigrationRegistry::get_by_type('tag')`
- `includes/classes/admin/class-tag-converter.php` — read-only; consumes MigrationRegistry directly
- `includes/classes/admin/class-settings-page.php` — read-only; renders via `DeprecatedTagRegistry::get_all()`

## §V Invariants

- V1: After change, no deprecated tag name appears in GB tag picker (`GenerateBlocks_Register_Dynamic_Tag::get_tags()` returns none with `type:'deprecated'`).
- V2: `MigrationRegistry::get_deprecated_tag_names()` returns same set before and after — all `type:'tag'` entries intact.
- V3: `bws_register_v1_deprecated_tag_wrappers()` entries contain only migration keys (`old_tag`, `new_tag`, `source_inject`, `option_renames`, `value_renames`, `fixed_options`, `datetime_transforms`, `combine_options`, `required_options`, `since`, `transform_callback`, `gb_link_remap`) — no `callback`, `options`, `title`, `description`, `supports`, `gb_type`. Note: `transform_callback` ≠ `callback` — it is a migration-pipeline hook (invoked by `MigrationRegistry::run_transform()`) not a GB renderer; must be kept.
- V4: `bws_register_early_deprecated_tag_migrations()` entries contain no `callback` key.
- V5: No `bws_deprecated_*_callback()` function exists (8 early hardcoded callbacks deleted).
- V6: No `bws_make_deprecated_*_callback()` function exists (4 factories deleted).
- V7: `bws_register_deprecated_tags()` body is empty — function exists, registers nothing.
- V8: GB-only option helper vars deleted from `bws_register_v1_deprecated_tag_wrappers()`: `$content_opts`, `$ct_opts`, `$fi_opts`, `$ci_opts`, `$cds_opts`, `$cdr_opts`, `$cdts_opts`, `$cdtr_opts`, `$te_opts`, `$ti_opts`, `$rel_opts`, `$rel2_opts`, `$srp_src_opts`, `$ptrp_src_opts`.
- V9: Migration rename-map vars kept: `$content_renames`, `$content_values`, `$ct_renames`, `$fi_renames`, `$ci_renames`, `$cds_renames`, `$cdr_renames`, `$cdts_renames`, `$cdtr_renames`, `$rel_renames` and merged variants, `$try_src_renames`.

## §T Tasks

| id  | st | task                                                                                                      | cites         |
|-----|----|-----------------------------------------------------------------------------------------------------------|---------------|
| T1  | x  | Delete 8 `bws_deprecated_*_callback()` functions (~lines 270–428 in deprecated-tags.php)                 | V5            |
| T2  | x  | Delete 4 `bws_make_deprecated_*_callback()` factories (~lines 430–565)                                   | V6            |
| T3  | x  | Empty `bws_register_deprecated_tags()` body — keep declaration + docblock                                | V7,C1         |
| T4  | x  | Strip GB-only fields from all `register()` calls in `bws_register_v1_deprecated_tag_wrappers()`          | V3,V8         |
| T5  | x  | Delete GB-only option vars from `bws_register_v1_deprecated_tag_wrappers()` preamble                     | V8            |
| T6  | x  | Strip `callback` key from 8 entries in `bws_register_early_deprecated_tag_migrations()`                  | V4            |
| T7  | .  | Verify: activate plugin, confirm no PHP errors, no deprecated tags in GB picker                          | V1            |
| T8  | .  | Verify: Tag Converter scan detects deprecated tags in test post; migrate produces correct output          | V2,C2         |
| T9  | .  | Verify: Settings page deprecated-tag section lists all ~130 entries with migration targets               | C3            |

## §B Bugs

| id | date | cause | fix |
|----|------|-------|-----|
