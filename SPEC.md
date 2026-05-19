# SPEC — Link-Wrapping for Base/Modifier/Try_ Tags

## §G — Goal

Add `linkTo`/`linkKey`/`newTab` output options to eligible templates so any tag wraps its output in an `<a>` element whose href resolves via the same source context used to produce the value.

---

## §C — Constraints

- `link` reserved by GB (stripped from extraTagParams before custom controls see it; GB `with_link()` would fire). Cannot use as option key.
- GB `with_link()` hardcodes `get_id($opts,'post',$inst)` — unaware of our SourceRegistry / src:ref / term entities. Cannot delegate to GB.
- GB `with_link()` fires inside `::output()` gated only by `empty($options['link'])` — NOT by `supports`. Our deprecated callbacks don't call `::output()`, so `link` option is inert there today. No deprecated callback changes needed.
- `linkTo` first option value is the canonical token `'none'`; `bws_strip_default_select_values()` flips it to `''` at registration boundary so it is not serialized into saved tags.
- `linkKey` empty must never block tag output — only skips wrap.
- `linkKey` on term entity resolves via `get_term_meta()` — same behavior as post meta.
- Link wrap applies after fallback resolves (wraps fallback text too).
- Options land at **end of Group 1** (leading_options) for all eligible templates. Consistent position even where Group 1 is otherwise empty (e.g. `text`, `title`).
- try_ tags: single `linkKey` applies to winning slot's entity. No per-slot linkKey. Help text notes limitation.
- `newTab` included on all eligible templates including `datetime_*` for consistency.
- Excluded templates: `content` (long-form, may contain links), `permalink` (output is already a URL), all `image` templates (URL output nonsensical to wrap; text-mode image linking deferred).
- Eligible: `text`, `title`, `datetime_single`, `datetime_range` — base, `term_` modifier, and `try_` variants all included.
- T9 scope: only deprecated tags **with a migration path** need `link` remapped. No-migration-path tags (`second_related_post_*`, `post_term_related_post_*`) had `link` inert in their callbacks — no action needed.

---

## §I — Surfaces

- `includes/classes/class-tag-template-registry.php` — `generate_base_try_tags()`, `register_modifier()`, template descriptor shape
- `includes/tags/base-tags.php` — `register_modifier_template()` calls; base tag callbacks
- `includes/tags/taxonomy-tags.php` — `term_` modifier callbacks (if link entity differs per context)
- `includes/helpers/content-helpers.php` — shared resolution helpers
- `includes/tags/deprecated-tags.php` — `related_post_content` `transform_callback` (drops `link_to`/`link_field`/`new_window` today → must map); migration entries for `related_post_title`, `related_post_custom_text`, `post_term_title`, `term_related_post_title` + `custom_text` equivalents (had `supports:['link']` → GB `link` option in saved tags → must remap)
- `docs/deprecated-tags-options.md` — §N×M support matrix (`link` column on `title`, `custom_text`, `term_title` rows)
- `docs/tag-matrix.md` — §Base tag GB types table gains link-support column; §Option render order gains link group per template
- `assets/js/editor-conditional-options.js` — `show_if` conditions for `linkKey` and `newTab`

---

## §V — Invariants

**V1** `link` never used as option key or in `supports` array on any plugin-registered tag. All link-wrapping goes through `bws_wrap_with_link()`.

**V2** `linkTo` option definition uses canonical first value `'none'`; `bws_strip_default_select_values()` at registration boundary flips it to `''`. Serialized tag contains `linkTo:permalink` or `linkTo:meta` only; absence = no link. Callback reads `$opts['linkTo'] ?? 'none'` to recover canonical token.

**V3** `linkKey` empty (unset or blank) → wrap skipped, tag output returned unchanged. Never causes empty output.

**V4** `bws_resolve_link_url()` accepts entity type (`'post'`|`'term'`) and routes: `permalink` → `get_permalink()`/`get_term_link()`; `meta` → `get_post_meta()`/`get_term_meta()`. Returns `''` (not false/null) on failure.

**V5** Link wrap applied after fallback: output passed to `bws_wrap_with_link()` is the final resolved string (slot result or fallback). If `bws_resolve_link_url()` returns `''`, original output returned unchanged.

**V6** `newTab` only rendered in `<a>` when `linkTo` is non-empty and URL resolved non-empty. Never emits bare `target` attribute.

**V7** try_ link wrap uses **winning slot's** entity (`$post_id` or `$term_id` in scope at return point). Closure captures and passes entity type signal alongside entity ID.

**V8** `content` and `permalink` templates have no `supports_link_wrap` flag. `generate_base_try_tags()` and `register_modifier()` never inject link options onto `try_content`, `try_permalink`, `term_content`, `term_permalink`.

**V9** `image` template has no `supports_link_wrap` flag. No link options on `image`, `term_image`, `try_image`.

**V10** `related_post_content` `transform_callback` maps old `link_to`/`link_field`/`new_window` → new option names: `link_to:post` → `linkTo:permalink`; `link_to:custom` + `link_field:X` → `linkTo:meta|linkKey:X`; `new_window` present → `newTab` bare key. `link_to:none` (or absent) → all three dropped.

**V10b** Tags that had `supports:['link']` (GB-native link) in the deprecated N×M matrix — `title`, `custom_text`, `term_title` columns — may have saved tags containing GB's `link` option (format: `link:post`, `link:post_meta,key`, `link:term`, `link:author_archive`, etc.). Migration transforms for affected deprecated tags must remap: `link:post` → `linkTo:permalink`; `link:post_meta,key` → `linkTo:meta|linkKey:key`; `link:term` → `linkTo:permalink` (term entity maps to permalink); other GB link destinations (`author_archive`, `author_meta`, `author_email`, `comments`) → dropped (out of scope, no equivalent). The `link` key is removed from the migrated tag string in all cases.

**V11** Link options appear at **end of `leading_options`** in template descriptor. For templates with no prior leading options, link options are the sole Group 1 content.

**V12** `show_if` conditions: `linkKey` visible only when `linkTo:meta`; `newTab` visible only when `linkTo` is `not_empty`.

---

## §T — Tasks

| id | status | task | cites |
|----|--------|------|-------|
| T1 | x | Add `supports_link_wrap` flag to `text`, `title`, `datetime_single`, `datetime_range` template descriptors in `base-tags.php` and `datetime-tags.php` | V8,V9,V11 |
| T2 | x | Implement `bws_resolve_link_url(string $link_to, string $link_key, int $id, string $entity_type): string` in `content-helpers.php` | V4 |
| T3 | x | Implement `bws_wrap_with_link(string $output, string $link_to, string $link_key, bool $new_tab, int $id, string $entity_type): string` in `content-helpers.php` — calls V4, applies V3, V5, V6 | V3,V4,V5,V6 |
| T4 | x | Build `bws_get_link_options(): array` helper returning the three option definitions (`linkTo` select, `linkKey` text, `newTab` bool) with correct labels, show_if, and `_strip_default` on `linkTo` | V2,V12 |
| T5 | x | Inject link options + wrap into **base tag callbacks**: for each eligible base tag (`text`, `title`, `datetime_single`, `datetime_range`) in `base-tags.php`/`datetime-tags.php`, append `bws_get_link_options()` to `leading_options` and wrap callback return via `bws_wrap_with_link()` | V1,V2,V3,V5,V11,I.base-tags |
| T6 | x | Inject link options + wrap into **`register_modifier()`** (term_ tags): read `supports_link_wrap` from template descriptor; append options to leading group; wrap `term_fn`/`post_fn` return with entity-type-aware call | V4,V7,V8,V9,I.taxonomy-tags |
| T7 | x | Inject link options + wrap into **`generate_base_try_tags()`**: read `supports_link_wrap`; append `bws_get_link_options()` to `$options` after leading group; at closure return-point (lines ~665, ~672) capture entity type + id from winning slot and call `bws_wrap_with_link()` | V5,V7,V8,V9,I.registry |
| T8 | . | Update `related_post_content` `transform_callback`: map `link_to`/`link_field`/`new_window` → `linkTo`/`linkKey`/`newTab` per V10; remove from `$drop` array | V10,I.deprecated-tags |
| T9 | . | Add GB-native `link` → `linkTo`/`linkKey` remapping to migration transforms for deprecated tags with migration paths that had `supports:['link']`: `related_post_title`→`title`, `related_post_custom_text`→`text`, `post_term_title`→`title`, `post_term_custom_text`→`text`, `term_related_post_title`→`term_title`, `term_related_post_custom_text`→`term_text`. Add `bws_map_gb_link_option(array $options): array` helper per V10b. No changes to no-migration-path callbacks. | V10b,I.deprecated-tags |
| T10 | . | Update `docs/tag-matrix.md`: add link-support column to §Base tag GB types table; add `linkTo`/`linkKey`/`newTab` rows to §Option render order for eligible templates; note Group 1 end placement | V11,I.tag-matrix |
| T11 | . | Update `docs/deprecated-tags-options.md` option rename tracker: add `link` (GB native, on title/custom_text/term_title cols) → `linkTo`/`linkKey` and `link_to`/`link_field`/`new_window` (related_post_content) → `linkTo`/`linkKey`/`newTab` rename rows | I.deprecated-tags-options |

---

## §B — Bugs

| id | date | cause | fix |
|----|------|-------|-----|
