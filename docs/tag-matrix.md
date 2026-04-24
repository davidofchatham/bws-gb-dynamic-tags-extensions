# BWS Dynamic Tags — Tag × Source Matrix

This document is the **authoritative living reference** for template keys, source keys, option names,
and which dynamic tag variants exist. Update this file whenever sources, templates, or options are
added, removed, renamed, or change default-enabled status. Other docs should cross-reference here
rather than maintaining parallel tables.

Option names shown throughout are the **approved names** (current or approved-but-pending). See
the option renaming tracker for the transition from old names.

---

## Notation

| Symbol | Meaning |
|--------|---------|
| ✅ | Generated, **enabled** by default |
| ☐ | Generated, **opt-in** (disabled by default in admin settings) |
| — | Not applicable — template context type does not match source |
| GB | Not generated — GB (or GB Pro) already registers this tag name; skipped by collision check |
| ★ | Planned but not yet implemented |
| † | Template being consolidated into another template — see template key renaming tracker |

Tags generated with GB's built-in `post_title`, `post_excerpt`, and `post_permalink` names are
silently skipped at registration time. The collision check queries
`GenerateBlocks_Register_Dynamic_Tag::get_tags()` dynamically, so any tag already registered by
GB or another plugin is automatically avoided.

**Supports column** (in source tables and tag matrices): lists the GB `supports` values that
control which editor controls appear for a tag. Values include `source` (entity picker), `link`,
`meta` (field key input), `image-size`, `taxonomy` (taxonomy picker). The source table's Supports column shows what the source
adds or removes from the template's base supports (`+source always` / `−source` / `as-is`).
The matrix Supports column shows the template's base array before any source modifier is applied.

---

## Sources

Sources are grouped by their **starting context** — what entity provides the initial ID for the tag.

> **Planned architecture note:** In the current N×M model, each source has a tag prefix that
> appears in every registered tag name. In the planned source-agnostic architecture, sources map
> to `source` option values on base tags (e.g. `source:ref` for a related post traversal). The "Tag prefix" column
> reflects current implementation; `source` values are listed in the Architecture overview section of
> the consolidation plan.

### Post-context sources

These sources resolve to a post. Use them in post loops, single-post templates, and anywhere a
post is in scope.

| Source key | Tag prefix | Traversal | Supports | Registered by | Notes |
|---|---|---|---|---|---|
| `post` | `post_` | Current post (direct) | Template as-is | Built-in | |
| `related_post` | `related_post_` | Current post → related post (reference field on post) | Template − `source` | Built-in | Requires `ref` option |
| `second_related_post` | `second_related_post_` | Current post → related post → 2nd related post | Template − `source` | Built-in | Requires `ref1` + `ref2` (legacy: `rel` + `rel_2`) |
| `post_term_related_post` | `post_term_related_post_` | Current post → post's term (via `tax`) → term's related post (via `ref` on term). First term only. | Template − `source` | Built-in | Requires `tax` + `ref` |
| `portal` | `portal_` | Current post (portal context) | Template as-is | `bws-portal-system` | External; registered via `bws_dynamic_tags_register_sources` hook |

### Term-context sources

These sources resolve to a term. Use them on archive pages and in term loops.

| Source key | Tag prefix | Traversal | Supports | Registered by | Notes |
|---|---|---|---|---|---|
| `term` | `term_` | Current term (direct) | Template + `source` (always) | Built-in | Archive pages + term loops |
| `term_related_post` | `term_related_post_` | Current term → related post (reference field on term) | Template − `source` | Built-in | Requires `ref` option on the term entity. ⚠️ Starts from term context — see note. |

> ⚠️ **`term_related_post_` vs `post_term_related_post_`:** Both involve a term's related post,
> but they start from different contexts. `term_related_post_` starts on an **archive or term loop
> page** (current term is already in scope). `post_term_related_post_` starts from a **current
> post**, resolves the post's term via `tax`, then hops to that term's related post via `ref` —
> a 3-hop traversal from post context. See Post-context sources above.

### Source traversal options (revised architecture)

Base tags drop native `supports: ['source']` — no clean `show_if` path for the `ref` sub-option when using GB's native source selector (`dynamicSource` inaccessible from `tagSpecificControls` filter). The `source` option is registered as a plain PHP `select` in the standard `options` array; existing `editor-conditional-options.js` handles all `show_if` conditions for sub-options. No new JS required for `source` itself. A richer custom combobox (icons, labels, entity search) is a UI enhancement — deferred.

The selected value serializes as `source:ref` in the tag string. PHP callbacks read `$options['source']` and dispatch accordingly.

See [Source options](#source-options).

`try_` tags use the same custom source selector for all slots. Slot 1 option name: `source`; slots 2+ use `N-src`. Slot 2+ prepends "Same as Previous Source" (unset). See [Try_ tags](#try_-tags).

> **Portal source:** The portal plugin is a **context modifier**, not a traversal hop. It registers its own tag group externally (via `bws_dynamic_tags_register_sources` PHP hook and `generateblocks.editor.tagSpecificControls` JS filter). Portal is the starting context — `portal` does not appear as a `source` value on standard base tags. Deprecated wrappers for `portal_*` tags are registered with this plugin and should also be handled by the migrator.

---

## Tag Matrix — Post-context sources

The row label is the **template key**. The full tag name is `{prefix}{template_key}`,
e.g. `related_post_custom_text` = `related_post_` + `custom_text`.

| Template | Supports | Options | `post_` | `related_post_` | `second_related_post_` | `post_term_related_post_` | `portal_` |
|---|---|---|---|---|---|---|---|
| **title** | `link`, `source` | — | GB | ✅ | ✅ | ✅ | ☐ |
| **content** | `source` | `use`, `key`, `fallback` | ✅ | ✅ | ✅ | ✅ | ☐ |
| **excerpt** † | `source` | — | GB | ✅ | ✅ | ✅ | ☐ |
| **permalink** | `source` | — | GB | ✅ | ✅ | ✅ | ☐ |
| **custom_text** | `meta`, `link`, `source` | `key`, `fallback` | ✅ | ✅ | ✅ | ✅ | ☐ |
| **featured_image** † | `image-size` | `as` | ✅ | ✅ | ✅ | ✅ | ☐ |
| **custom_image** † | `image-size` | `key`, `as` | ✅ | ✅ | ✅ | ✅ | ☐ |
| **datetime_single** | `source` | `key`, `time_key`, `as`, `format`, `show_current_year`, `show_midnight`, `time_sep`, `fallback` | ✅ | ☐ | ✅ | ✅ | ☐ |
| **datetime_range** | `source` | `start_key`, `end_key`, `start_time_key`, `end_time_key`, `as`, `format`, `show_current_year`, `show_midnight`, `range_sep`, `time_sep`, `sep`, `fallback` | ✅ | ☐ | ✅ | ✅ | ☐ |
| **term_title** | `link`, `source` | `tax`, `fallback` | ✅ | ✅ | ✅ | — | ☐ |
| **term_permalink** | `source` | `tax`, `fallback` | ✅ | ✅ | ✅ | — | ☐ |
| **term_description** | `source` | `tax`, `fallback` | ✅ | ✅ | ✅ | — | ☐ |
| **term_custom_text** | `meta`, `source` | `tax`, `key`, `fallback` | ✅ | ✅ | ✅ | — | ☐ |
| **term_custom_image** | `image-size`, `source` | `tax`, `key` | ✅ | ✅ | ✅ | — | ☐ |

`description` is not listed — it is a term-context-only template with no post-context implementation.

† `excerpt` is approved for consolidation into `content` as `use:excerpt`. `featured_image` and `custom_image` are approved for consolidation into `image` (`use` unset = meta field (uses `key`); `use:featured` = Featured Image). See template key renaming tracker.

---

## Tag Matrix — Term-context sources

| Template | Supports | Options | `term_` | `term_related_post_` |
|---|---|---|---|---|
| **title** | `link`, `source` | — | ✅ | ✅ |
| **content** | `source` | `use`, `key`, `fallback` | — | ✅ |
| **excerpt** † | `source` | — | — | ✅ |
| **permalink** | `source` | — | ✅ | ✅ |
| **description** † | — | — | ✅ | — |
| **custom_text** | `meta`, `link`, `source` | `key`, `fallback` | ✅ | ✅ |
| **featured_image** † | `image-size` | `as` | — | ✅ |
| **custom_image** † | `image-size` | `key`, `fallback`, `as` | ✅ | ✅ |
| **datetime_single** | `source` | `key`, `time_key`, `as`, `format`, `show_current_year`, `show_midnight`, `time_sep`, `fallback` | ☐ | ☐ |
| **datetime_range** | `source` | `start_key`, `end_key`, `start_time_key`, `end_time_key`, `as`, `format`, `show_current_year`, `show_midnight`, `range_sep`, `time_sep`, `sep`, `fallback` | ☐ | ☐ |

`term_*` (term-extraction) templates are not listed — they extract terms FROM a post and have no
meaning in a term-context source.

† `excerpt` is approved for consolidation into `content` as `use:excerpt`. `featured_image` and `custom_image` are approved for consolidation into `image` (`use` unset = meta field; `use:featured` = Featured Image). `description` is approved for consolidation into `content` — for term-context, `content` with `use` unset outputs the term description; no `fixed_options` needed. See template key renaming tracker.

---

## Default-enabled logic

> **Planned architecture note:** The resolution order below applies to the current N×M model where
> each source×template combination produces a separately-registered tag. In the planned
> source-agnostic architecture, per-source-tag defaults are eliminated — there is one tag
> registration per template, and all `source` values are always available. The `default_enabled_map`
> mechanism becomes obsolete.

Resolution order for each cell in the current model (first match wins):

1. Explicit admin setting saved by the user
2. `default_enabled_map` on the template (keyed by source prefix without trailing `_`)
3. `tag_default_enabled()` on the source class
4. Fallback: `true` (enabled)

The source toggle itself defaults to `source_default_enabled()` on the source class (true for all built-in sources).

Drivers of the ☐ cells above:

- **date/datetime × `related_post_` and `term_`** — `default_enabled_map: [related_post => false, term => false]` on each date template
- **date/datetime × `second_related_post_` and `post_term_related_post_`** — `default_enabled_map` on each date template does not include these sources; they are ✅ per `tag_default_enabled() = true`. This asymmetry with `related_post_` (☐) is a known transitional state — the entire per-source default mechanism is made obsolete by Step 2 consolidation (step 2g). Per-traversal toggling may be revisited post-consolidation if needed.
- **`portal_` (all)** — external source; assumed opt-in pending portal plugin declaration

---

## Try_ tags

`try_` tags are **entity-agnostic fallback chains**. A single tag tries up to 5 slots in sequence
and returns the first non-empty result. The user configures which traversal each slot uses at the
tag instance level — there is no source prefix in the tag name.

### Per-slot controls

Each slot exposes up to three controls:

1. **Source** — Custom selector (same control as base tags). Slot 1 option name: `source`; slots 2+: `N-src`. Slot 1 default: current entity (not serialized). Slots 2+ default: "Same as Previous Source" (unset/inherit). `ref` sub-option shown when source = `ref`.

2. **Field key** (text, image, datetime templates with per-slot key) — Slot 1: must be set for the slot to produce output. Slots 2+: "Same as Previous Field" (unset/inherit). Label adapts to template: "Same Text Field", "Same Image Field", "Same Date-Time Field", etc.

3. **`srcTerm`** — Slots 2+: "Same as Previous" (unset/inherit). Shown below source selector when applicable (hidden for term-context entities). `tax` sub-option shown when `srcTerm` is set. See [Secondary, conditional options](#secondary-conditional-options).

**Progressive disclosure:** Slot N+1 is hidden until at least one of slot N's three controls is set to a non-default value. Within a slot, sub-controls (e.g. `ref` key, `tax`) appear only when their parent control is active.

**Per-slot `use`** is available on templates that support content mode selection per slot (e.g. "try ACF/meta field, fall back to post content").

### Available try_ tags

| Tag name | Based on template | Per-slot field key? | Per-slot `use`? | Notes |
|---|---|---|---|---|
| `try_content` | `content` | No | **Yes** | Each slot: Content/Description or ACF/Custom Field |
| `try_title` | `title` | No | No | |
| `try_permalink` | `permalink` | No | No | |
| `try_text` | `text` | **Yes** | No | Each slot's field key can differ |
| `try_image` | `image` | **Yes** | **Yes** | `use` unset = ACF/meta field (uses per-slot key); `use:featured` = Featured Image |
| `try_datetime_single` | `datetime_single` | No | No | Shared `key` across slots |
| `try_datetime_range` | `datetime_range` | No | No | Shared `start_key`/`end_key` across slots |

---

## Options required per template/source combination

Some tag variants require specific options to be configured in the GB editor before they produce
output. Missing required options cause the tag to return empty string (no error).

| Template | Required option(s) | Notes |
|---|---|---|
| All `related_post_` variants | `ref` — reference/relational field key | Identifies which reference field to traverse |
| All `term_related_post_` variants | `ref` — reference/relational field key on the term entity | Traverses from current term to related post |
| All `second_related_post_` variants | `ref1` + `ref2` — two reference field keys (legacy: `rel` + `rel_2`) | First hop (`ref1`) then second hop (`ref2`) |
| All `post_term_related_post_` variants | `tax` — taxonomy slug; `ref` — reference field key on the term entity | First term in the taxonomy is used; the `ref` field is on the term, not the post. |
| `content` (all sources, `use:key`) | `key` — ACF or meta field key | Required when `use` is set to `key` |
| `custom_text` (all sources) | `key` — ACF or meta field key | Via GB's `meta` support |
| `custom_image` (all sources) | `key` — meta image field key | |
| `datetime_single` (all sources) | `key` — date, datetime, or time field key | |
| `datetime_range` (all sources) | `start_key` — start date/datetime/time field key | `end_key` optional |
| `term_*` templates (all sources) | `tax` — which taxonomy to look up | Post-context extraction; gets first term of this taxonomy on the resolved post |
| `term_custom_text` | `tax` + `key` | |
| `term_custom_image` | `tax` + `key` | |

---

## List mode (`limit` + `sep`)

Selected templates support outputting multiple results as a delimited list. `limit` defaults to 1
(single result). When `limit > 1`, results are joined with `sep` (default: `, `).

`limit` applies to the **final traversal step**: terms for `term_*` extraction templates; related
posts for traversal sources (`related_post_`, `term_related_post_`, etc.).

| Template | List mode | What is iterated |
|---|---|---|
| `title` (traversal sources) | ✅ | Related posts |
| `content` | ❌ | Long-form prose |
| `excerpt` † | ❌ | Long-form prose |
| `permalink` | ❌ | Scalar URL |
| `description` † | ❌ | Long-form prose |
| `custom_text` (traversal sources) | ✅ | Related posts |
| `featured_image` | ❌ | Scalar media |
| `custom_image` | ❌ | Scalar media |
| `datetime_single` (traversal sources) | ✅ | Related posts |
| `datetime_range` (traversal sources) | ✅ | Related posts |
| `term_title` (all sources) | ✅ | Terms in taxonomy |
| `term_permalink` | ❌ | Scalar URL |
| `term_description` | ❌ | Long-form prose |
| `term_custom_text` (all sources) | ✅ | Terms in taxonomy |
| `term_custom_image` | ❌ | Scalar media |

† `description` and `excerpt` consolidating into `content` — see template key renaming tracker.

---

## Potential future traversals

These WP hierarchy relationships are candidates for `source` values in the traversal registry (see
[Traversal registry and `source` values](#traversal-registry-and-via-values-planned-architecture)
above). They do **not** create new source classes or new matrix columns — they are additional
`source` options selectable on the existing base tags.

| `source` value | Traversal | Description | Status |
|---|---|---|---|
| `parent` | current post → WP parent post | Hierarchical post types | Planned |
| `ancestor` | current post → WP top-level ancestor | Hierarchical post types | To be considered |
| `child` | current post → WP child posts | Hierarchical post types; implies list/loop output | To be considered |
| `sibling` | current post → WP same-parent posts | Hierarchical post types; implies list/loop output | To be considered |

---

## Potential future templates

These template types require their own option sets and formatting logic that `combine_text` cannot
replicate. Each would add a row to all applicable source matrices. The naming pattern follows
`datetime_single` / `datetime_range` — no special prefix; the template key is the type name.

| Template key | Description | Link support | Status |
|---|---|---|---|
| `number` | Format a raw numeric field: decimal places, thousands separator, currency symbol + position, optional prefix/suffix | No | To be considered |
| `phone` | Format a raw stored phone number with regional pattern and optional country code; can wrap output in a `tel:` link | `tel:` | To be considered |
| `email` | Output a stored email address; can wrap output in a `mailto:` link | `mailto:` | To be considered |

Image tags are excluded: multiple return formats are already built into image tag mechanics.

---

## Base tag GB types (planned architecture)

In the source-agnostic architecture, each template has one GB tag registration. Type names settled (2026-04-14): base tags use `'cross-source'`; try_ tags use `'first-available'`. Both are hyphenated English compounds confirmed valid as GB type strings.

| Template key | GB type | Notes |
|---|---|---|
| `text` | `'cross-source'` | |
| `content` | `'cross-source'` | |
| `title` | `'cross-source'` | Bare `title` confirmed safe — no GB/GB Pro tag starts with `title` and `title` does not start with any registered GB/GB Pro tag name (verified 2026-04-13). Zero options; shares internal pipeline with `text from:title`. |
| `permalink` | `'cross-source'` | Zero options in current scope. |
| `image` | `'cross-source'` | Custom image controls in scope for this plan phase: `as` + `size` (combobox) + `fallback` (media picker) registered via JS filter. No `'media'` type — all controls custom. See `custom-image-controls.md`. |
| `datetime_single` | `'cross-source'` | |
| `datetime_range` | `'cross-source'` | |

The term_ modifier produces additional tags with GB type `'term'`: `term_text`, `term_image`, `term_title`, `term_permalink`. `source` unset = user-selected term (never serialized); `source:'ref'` = term→related post traversal. `term_image` uses GB type `'term'`; `as` and `size` registered as custom options (same pattern as base `image` — `'media'` type not used on any image tag). `as` serialization exception applies to `term_image` as well — default `as:url` is always written to the tag string.

**try_ modifier** produces `try_text`, `try_image`, etc. with GB type `'first-available'`. Up to 5 slots (s1–s5); slots revealed progressively as earlier slots are configured.

**`as` serialization exception:** For `image` and `term_image` tags, the `as` option default (`url`) is always serialized into the tag string even when unmodified — `{{image as:url|...}}`. This makes the return mode immediately visible when copying a tag instance between fields (e.g. image src → alt text). All other defaults follow the standard rule (never serialize defaults).

---

## Template key renaming tracker

Tracks planned template key renames and consolidations. When a template key changes, the generated
tag names for all sources change with it. Deprecated wrappers are registered via `DeprecatedTagRegistry`
for each old tag name and appear in the editor picker under the "Deprecated" group. Status values
match the option renaming tracker below.

In the source-agnostic architecture, the "New tag" column shows the single registered base tag.
Each per-source old tag becomes a deprecated wrapper with `source_inject` set to the source's `source`
value (see §Source `source` values above) and `option_renames` as listed below.

| Current key | New key | Old tag example | New tag (base) | `source_inject` | `option_renames` | Status | Notes |
|---|---|---|---|---|---|---|---|
| `excerpt` | *(folded into `content`)* | `post_excerpt` | `{{content from:excerpt}}` | source abbrev. | none; `fixed_options: ['from' => 'excerpt']` | Approved | `use:excerpt` added as third value on `content`'s `use` option. `bws_post_excerpt_core()` retained as internal function. GB's `post_excerpt` is not a conflict (never registered by us). |
| `featured_image` | *(folded into `image`)* | `post_featured_image` | `{{image from:featured}}` | source abbrev. | `as → as` (no rename); `fixed_options: ['from' => 'featured']` | Approved | Deprecated wrappers inject `use:featured` — unset now means ACF/meta field (custom image). `image-size` support carried over. |
| `custom_text` | `text` | `post_custom_text` | `{{text}}` | source abbrev. | `fallback_text → fallback`, `type → use` | Approved | Removes `meta` from supports; own `key` input replaces GB pass-through. |
| `custom_image` | *(folded into `image`)* | `post_custom_image` | `{{image key:…}}` | source abbrev. | `fallback_url → fallback`, `return_type → as`, `field_key → key` | Approved | No `fixed_options` or `type → use` rename needed — `custom_image` never had a `type` option, and `use` unset is now the ACF/meta field default. Term-source `image` variants also default to ACF/meta field — no featured image concept applies to terms. |
| `custom_date_single` | `datetime_single` | `post_custom_date_single` | `{{datetime_single as:date}}` | source abbrev. | see §Date-time option names; `fixed_options: ['as' => 'date']` | Approved | Merged with datetime_single via `as` option. |
| `custom_date_range` | `datetime_range` | `post_custom_date_range` | `{{datetime_range as:date}}` | source abbrev. | see §Date-time option names; `fixed_options: ['as' => 'date']` | Approved | Merged with datetime_range via `as` option. |
| `custom_datetime_single` | *(merged into `datetime_single`)* | `post_custom_datetime_single` | `{{datetime_single}}` | source abbrev. | see §Date-time option names | Approved | Default mode (unset) = datetime. |
| `custom_datetime_range` | *(merged into `datetime_range`)* | `post_custom_datetime_range` | `{{datetime_range}}` | source abbrev. | see §Date-time option names | Approved | |
| `description` | *(folded into `content`)* | `term_description` | `{{content}}` | source abbrev. | none | Approved | Term-context only — no post-context variant exists. `content` with `use` unset for a term entity outputs term description; no `fixed_options` needed. `bws_term_description_core()` retained as internal function. |

---

## Base tag title strings

`title` is displayed in the GB tag picker and used as the last-resort editor fallback when a tag can't resolve and no preview label is available. The "Current title (N×M)" column shows the template `title` field as it appears in the current per-source tag registrations. The "Base tag title" column is the proposed title for the single registered base tag in the source-agnostic architecture.

| Template key | Current title (N×M) | Base tag title | Term modifier title | Status |
|---|---|---|---|---|
| `text` (was `custom_text`) | `'Custom Text'` | `'Text Fields'` | Base title + `'(term-based)'` | Approved |
| `content` | `'Content'` | `'Content/Description'` | Base title + `'(term-based)'` | Approved |
| `title` | `'Title'` | `'Title/Name'` | Base title + `'(term-based)'` | Approved |
| `permalink` | `'Permalink'` | `'Permalink'` | Base title + `'(term-based)'` | Approved |
| `image` (was `custom_image`) | `'Custom Image'` | `'Image Fields'` | Base title + `'(term-based)'` | Approved |
| `datetime_single` | `'Custom Date/Time'` | `'Format Date/Time Fields'` | Base title + `'(term-based)'`| Approved |
| `datetime_range` | `'Custom Date/Time Range'` | `'Format Date/Time Fields as Range'` | Base title + `'(term-based)'` | Approved |
| `description` | `'Description'` | — | — | Approved — folds into `content` tag |

---

## Editor preview label schema

When a base tag can't resolve in the editor (no relationship configured, archive template, wrong post type), the callback returns a structured preview label instead of empty string. Built by `bws_build_preview_label( $options, $template )` in `includes/helpers/content-helpers.php`.

**Scope:** `text`, `content`, `title`, `datetime_single`, `datetime_range`, and their `term_` modifier equivalents. Image tags: only when `as:alt` or `as:caption` — excluded for `as:url` and `as:id` (attribute values; bracket string silently breaks the element). `permalink` excluded entirely.

Not on front end — gated by `$instance->context['bwsEditorPreview']`, injected only by the editor JS filter.

### Assembly

```
[{field part} from {context part}]   — both present
[{field part}]                        — field only
[{context part}]                      — context only (e.g. title, permalink)
[⚠ {warning}]                        — misconfigured: replaces entire label
```

Fallback appended when set: ` · fallback: "{value}"`.

### Context part

Space-joined segments. The `→` separator precedes the term-hop segment only.

| Condition | Segment |
|---|---|
| Modifier tag (e.g. `term_`, `views_`) | Modifier `label` value (e.g. `Term`, `Views`) |
| `source:ref` + `ref:X` set | `Ref (X)` |
| `source:ref` + `ref` unset | *(triggers warning — see below)* |
| `srcTerm` + `tax:X` set | `→ {taxonomy singular label} Term` (live `get_taxonomy()->labels->singular_name`; fallback: `{tax} Term`) |
| `srcTerm` + `tax` unset | *(triggers warning — see below)* |
| No modifier, `source` unset, no `srcTerm` | *(omit — no `from` clause)* |

### Field part

Template-specific. Missing required input triggers a warning instead of the field part.

| Template | Condition | Field part |
|---|---|---|
| `text` | `key:X` set | `Text Field (X)` |
| `text` | `use:title` | `Title` |
| `text` | `key` unset + `use` unset | *(missing — triggers warning)* |
| `content` | `use:key` + `key:X` | `Content Field (X)` |
| `content` | `use:excerpt` | `Excerpt` |
| `content` | `use` unset | `Content` |
| `content` | `use:key` + `key` unset | *(missing — triggers warning)* |
| `image` (`as:alt`) | `key:X` set | `Image Field (X) Alt Text` |
| `image` (`as:alt`) | `use:featured` | `Featured Image Alt Text` |
| `image` (`as:caption`) | `key:X` set | `Image Field (X) Caption` |
| `image` (`as:caption`) | `use:featured` | `Featured Image Caption` |
| `title` | — | `Title` (always) |
| `datetime_` | — | *(see datetime section below)* |

### Warnings

Warnings replace the **entire** label. Collect all missing required items; join with `, `; last two items use ` or `.

| Missing items | Warning |
|---|---|
| `ref` only | `⚠ No ref key set` |
| `key` only | `⚠ No meta key set` |
| `tax` only | `⚠ No taxonomy set` |
| `ref` + `key` | `⚠ No ref key or meta key set` |
| `ref` + `tax` | `⚠ No ref key or taxonomy set` |
| `tax` + `key` | `⚠ No taxonomy or meta key set` |
| `ref` + `tax` + `key` | `⚠ No ref key, taxonomy, or meta key set` |

### Datetime preview

Datetime tags compute a live preview from the current time rather than a static label. The `as` option controls label prefix and range-end offset. The preview value is formatted using the same formatter and options (`format`, `timeSep`, `rangeSep`, etc.) the tag uses at render time.

| `as` | Single prefix | Range prefix | Range end offset |
|---|---|---|---|
| unset | `Date-Time` | `Date-Time Range` | +1 day |
| `date` | `Date` | `Date Range` | +1 day |
| `time` | `Time` | `Time Range` | +1 hour |

```
[{prefix} like "{formatted value}" from {context part}]
[{prefix} like "{formatted value}"]   — no context
```

### Examples

| Tag | Preview label |
|---|---|
| `{{text key:body_text}}` | `[Text Field (body_text)]` |
| `{{text source:ref\|ref:rel_post\|key:body_text}}` | `[Text Field (body_text) from Ref (rel_post)]` |
| `{{text use:title}}` | `[Title]` |
| `{{text source:ref\|ref:rel_post\|use:title}}` | `[Title from Ref (rel_post)]` |
| `{{text srcTerm\|tax:category\|key:body_text}}` | `[Text Field (body_text) from Category Term]` |
| `{{text source:ref\|ref:rel_post\|srcTerm\|tax:category\|key:body_text}}` | `[Text Field (body_text) from Ref (rel_post) → Category Term]` |
| `{{text}}` | `[⚠ No meta key set]` |
| `{{text srcTerm\|key:body_text}}` | `[⚠ No taxonomy set]` |
| `{{text source:ref\|srcTerm\|key:body_text}}` | `[⚠ No ref key or taxonomy set]` |
| `{{term_text key:body_text}}` | `[Text Field (body_text) from Term]` |
| `{{term_text source:ref\|ref:rel_post\|key:body_text}}` | `[Text Field (body_text) from Term Ref (rel_post)]` |
| `{{title source:ref\|ref:rel_post}}` | `[Title from Ref (rel_post)]` |
| `{{content}}` | `[Content]` |
| `{{content use:excerpt}}` | `[Excerpt]` |
| `{{image as:alt\|key:hero}}` | `[Image Field (hero) Alt Text]` |
| `{{image as:url\|key:hero}}` | *(no label — excluded)* |
| `{{datetime_single as:date}}` | `[Date like "April 24, 2026"]` |
| `{{datetime_single as:time\|source:ref\|ref:event_date}}` | `[Time like "2:20 PM" from Ref (event_date)]` |
| `{{datetime_range as:date\|source:ref\|ref:event}}` | `[Date Range like "April 24 – April 25" from Ref (event)]` |
| `{{text source:ref\|ref:rel_post\|key:body_text\|fallback:Untitled}}` | `[Text Field (body_text) from Ref (rel_post) · fallback: "Untitled"]` |

---

## Option name renaming tracker

Tracks proposed renames from the naming pass. Status values: **Approved** (decision made, not yet
implemented), **Implemented** (already applied to current files — deprecated wrapper still needed for
migrating saved tags), **Under consideration** (needs more research or discussion), **Pending** (not yet
looked at).

Scope notation: `[image]` = image tags only; no scope = applies to all templates that have the option.

| Current name | Proposed name | Scope | Status | Notes |
|---|---|---|---|---|
| `fallback_text` | `fallback` | all text templates | Approved | Aligns with GB Pro `loop_item`; shorter; no per-tag conflict |
| `fallback_url` | `fallback` | image templates | Approved | Same key as `fallback_text` rename; no template has both simultaneously |
| `type` (field/mode selector) | `use` | content, text, image | Approved | Each tag defaults to its primary source (unset = default); `use` only appears when overriding. `content`: unset=post content/term description, `use:excerpt`, `use:key` (ACF WYSIWYG/content-area field, incl. ACF Extended block editor areas). `text`: unset=ACF/meta field (uses `key`), `use:title`. `image` (post sources): see table below. Note: `use:key` is never written on `text` or `image` (post sources) since ACF/meta field is the unset default for both. Current PHP name is `type`; `field` and `in` were doc-only intermediates, never in PHP. Shim: `$options['from'] ?? $options['type'] ?? ''`. |
| value `custom_field` | `key` | `use` option | Approved | Selects the ACF/meta field mode: "use the field named in `key`". Supersedes intermediate rename `custom_field` → `meta` (approved but never implemented). Not applicable to `text` (ACF/meta field is its unset default). |
| `taxonomy` | `tax` | term extraction | Approved | Aligns with GB's `tax` (used by `term_list`); consistency reduces risk if GB ever registers conflicting tag names |
| `via`/`from` (traversal selector) | `source` | all templates | Approved | Custom source selector option (fully custom JS control, not GB native). Replaces unshipped `via`, docs-only `from`, early proposed `src`. |
| `via:tax` traversal value | `srcTerm` (boolean) | all templates | Approved | Taxonomy hop formerly modeled as a `via` option value; now boolean toggle `srcTerm`. Allows post-resolution term hop independently of source selection. `tax` option still supplies the taxonomy slug. No tag string migration needed — `via` never shipped. |
| `rel` | `ref` | `source:ref` traversals (deprecated `related_post_*`, `term_related_post_*`, `post_term_related_post_*`) | Approved | Reference/relational field key — the option that stores the field name used to traverse. Renamed from `rel` for vendor-agnostic clarity (2026-04-13). Shim: `$options['ref'] ?? $options['rel'] ?? ''`. |
| `rel` | `ref1` | `second_related_post_*` deprecated wrappers | Approved | First-hop reference field key. Numeric counter because `ref` type repeats. Supersedes intermediate rename `1st_rel` (approved but never implemented). `second_related_post` traversal dropped — deprecated wrappers register with no functional equivalent pending architecture revisit. |
| `rel_2` | `ref2` | `second_related_post_*` deprecated wrappers | Approved | Second-hop reference field key. Supersedes intermediate rename `2nd_rel` (approved but never implemented). See `ref1` row. |

### Multi-source–specific option names

| Current name | Proposed name | Scope | Status | Notes |
|---|---|---|---|---|
| `src_N` (try_ slots) | slot 1: `source`; slot N>1: `N-src` | Multi-source templates | Approved | Revised from approved `sN-via`. Slot 1: unprefixed `source` (custom control, aligns with base tag). Slots 2+: numeric prefix (e.g. `2-src`, `3-src`). Shim slot 1: `$options['via'] ?? $options['s1-via'] ?? $options['src_1'] ?? ''`; shim slot N>1: `$options["{$n}-src"] ?? $options["{$n}-via"] ?? $options["s{$n}-via"] ?? $options["src_{$n}"] ?? ''`. (`sN-from` and `from_N` were doc-only names, never in PHP.) |
| `type_N` (try_ slots) | slot 1: `use`; slot N>1: `N-use` | Multi-source templates | Approved | Revised from approved `sN-from`. Mirrors `source` revision: slot 1 unprefixed, slots 2+ numeric prefix (e.g. `2-use`, `3-use`). New option — `type_N`, `field_N`, `in_N`, `sN-field` were all doc-only names, never in PHP. No shim needed. |

### Image-specific option names

| Current name | Proposed name | Scope | Status | Notes |
|---|---|---|---|---|
| `return_type` | `as` | image templates | Implemented | Aligns with datetime `as` option (same role: selects output mode). Callbacks read `$options['as'] ?? $options['return_type']` for backward compat. |
| `field_key` | `key` | image templates | Implemented | `as` freed up `key` — field name now aligns with GB's own `post_meta`/`term_meta` convention. Callbacks read `$options['key'] ?? $options['field_key'] ?? $options['meta_key']`. |
| `type` (field/mode selector) | `use` | content, text, image | Approved | Each tag defaults to its primary source (unset = default); `use` only appears when overriding. `image` (post sources): unset=ACF/meta field (uses `key`), `use:featured`. `image` (term sources): `use` not offered — always ACF/meta field. Current PHP name is `type`; `field` and `in` were doc-only intermediates. Shim: `$options['from'] ?? $options['type'] ?? ''`. |

### Date-time–specific option names

| Current name | New name | Scope | Status | Notes |
|---|---|---|---|---|
| `omit_current_year` | `showCurrentYear` | date/datetime | Approved | Flip boolean: unset = omit current year (default smart behavior); presence = always show year. Fixes `default:true` serialization problem. Revised from `show_current_year` → camelCase to align with GB JS option convention. Shim: `$options['showCurrentYear'] ?? $options['show_current_year'] ?? ''`. Converter: if `omit_current_year` absent from old tag, inject `showCurrentYear` (bare key) to preserve "always show year" behavior; if present, drop it. |
| `separator` | `rangeSep` | datetime_range | Approved | Renamed to avoid collision with list-mode `sep` — `datetime_range` supports list mode, `sep` reserved for list separator. Revised from `range_sep` → camelCase. Shim: `$options['rangeSep'] ?? $options['range_sep'] ?? $options['separator'] ?? ''`. |
| `date_time_separator` | `timeSep` | datetime | Approved | Applies only when date and time assembled separately (separate field keys, or no combined-field format). Hidden when `format` set or `as` is `date` or `time`. Show condition: `{ format: 'empty', as: 'not_in:date,time' }`. Default if unset: `', '` (not serialized). Revised from `time_sep` → camelCase. Shim: `$options['timeSep'] ?? $options['time_sep'] ?? $options['date_time_separator'] ?? ''`. |
| `as` (new option) | — | date/datetime | Approved | Mode selector: unset = datetime (never serialized); `as:date`; `as:time`. Controls output filtering regardless of field format. Replaces `date_only` and `time_only` flags entirely. `as:date` condition in `show_if`: `as: 'not:date'`. |
| `date_time_field` | `key` | datetime_single | Approved | Primary field key; holds date, datetime, or time value per `as`. Consistent with all non-image templates. |
| `time_field` | `timeKey` | datetime_single | Approved | Secondary time field for when date+time split across separate meta fields; hidden when `as:date`. Revised from `time_key` → camelCase. Shim: `$options['timeKey'] ?? $options['time_key'] ?? $options['time_field'] ?? ''`. |
| `start_field` | `startKey` | datetime_range | Approved | Primary start field key. Revised from `start_key` → camelCase. Shim: `$options['startKey'] ?? $options['start_key'] ?? $options['start_field'] ?? ''`. |
| `end_field` | `endKey` | datetime_range | Approved | Primary end field key. Revised from `end_key` → camelCase. Shim: `$options['endKey'] ?? $options['end_key'] ?? $options['end_field'] ?? ''`. |
| `start_time_field` | `startTimeKey` | datetime_range | Approved | Secondary start time field; hidden when `as:date`. Revised from `start_time_key` → camelCase. Shim: `$options['startTimeKey'] ?? $options['start_time_key'] ?? $options['start_time_field'] ?? ''`. |
| `end_time_field` | `endTimeKey` | datetime_range | Approved | Secondary end time field; hidden when `as:date`. Revised from `end_time_key` → camelCase. Shim: `$options['endTimeKey'] ?? $options['end_time_key'] ?? $options['end_time_field'] ?? ''`. |
| `format_type` + `custom_format` | `format` | date/datetime | Approved | Single field: empty = auto (use field handler's return format, or WP date/time settings as fallback); non-empty = custom PHP date format string. Help text: "When unset, the format returned by the field handler is used; if unavailable, your WordPress date and time settings are used." Converter: if `format_type:custom`, rename `custom_format` → `format` and drop `format_type`; otherwise drop both. |
| `smart_time` | *(eliminated)* | datetime | Approved | AM/PM consolidation becomes always-on (ungated). Midnight suppression becomes `showMidnight` option. `smart_time` eliminated entirely. Converter: drop `smart_time`; if it was absent and tag would have produced time output, inject `showMidnight` (bare key) to preserve old "always show midnight" behavior. |
| `show_midnight` (new option) | — | datetime | Approved | Renamed to `showMidnight` — camelCase alignment. Unset = hide 00:00 times (default); presence = display midnight explicitly. Shown only when `as` not `date` (`show_if: { as: 'not:date' }`). |
| `date_only` | *(eliminated)* | datetime | Approved | Replaced by `as:date`. `custom_date_*` deprecated wrappers inject `as:date` via `fixed_options`. `custom_datetime_*` wrappers with `date_only` set inject `as:date` via converter. |
| `time_only` | *(eliminated)* | datetime | Approved | Replaced by `as:time`. Converter injects `as:time` when `time_only` is present. |

---

## Source options

| Option name | Option label | Context | Notes |
|---|---|---|---|
| `source` | Source | Base / Slot 1 | Aligns with GB native `source` option name |
| `N-src` | Source [N] | Slot 2+ | Abbreviated to reduce tag length |

### Source option values

| Option label | Option value | Base / Slot 1 | Slot 2+ | Context segment in editor preview label | Notes |
|---|---|---|---|---|---|
| Same as Previous Source | `same` | Current entity — not serialized | Inherit slot N−1 | N/A | Slot 2+: prepended entry, not in template definition |
| Current | `current` | stripped → unset | `current` | *(omitted)* | Slot 2+ only: explicit override back to current |
| In Reference/Relational Field | `ref` | `ref` | `ref` | `Ref (X)` where X = `ref` field value | Triggers `ref` sub-option |
| Parent | `parent` | `parent` | `parent` | — | Future |
| Ancestor | `ancestor` | `ancestor` | `ancestor` | — | Future |
| Child(ren) | `child` | `child` | `child` | — | Future |

Note: For context-modifier tags, the modifier label is prepended as a context segment. Examples: `[Title from Term]` for `{{term_title}}`, `[Content from Term Ref (rel_post)]` for `{{term_content source:ref|ref:rel_post}}`. See [§Editor preview label schema](#editor-preview-label-schema) for assembly rules.

### Secondary, conditional options

| Option name | Option label | Help text | Shown when | Notes |
|---|---|---|---|---|
| `ref` | Ref/Rel Field Meta Key | | `source` = `ref` | ACF relationship/relational field key for the traversal hop |
| `srcTerm` | Get from taxonomy term? | Field is in a taxonomy term on this source. | Always; hidden for `term_` modifier tags (entity already a term) | Boolean; unset by default. Term hop applied after source resolution as final step. |
| `tax` | Term Taxonomy | [Select/Enter] the taxonomy the term is in. | `srcTerm` is set | Taxonomy slug. Type TBD (text field or selector). |
| `limit` | Result Limit | This source type may return multiple results. By default, only the first result is used, but you may enter either a fixed limit, or “0” for no limit. | `source` = `ref` or `child`, or `srcTerm` set | `text`, `title`, `datetime_` only. Placeholder `1`; not serialized when unset. |
| `sep` | Result Separator | Separator between results (defaults to “, ”). | `limit > 1` | `text`, `title`, `datetime_single`, `datetime_range` only. List-mode separator. |

## Field options

| Option name | Option label | Context | Notes |
|---|---|---|---|
| `use` | [Text/Image/Content] Field | Base / Slot 1  | |
| `N-use` | [Text/Image/Content] Field [N] | Slot 2+  | |

#### Field selector option values (where applicable)

| Applicable tags | Option name | Option label | Conditionals | Notes |
|---|---|---|---|---|
| `text`, `image`, `content` | `same` *(prepended, slot 2+)* | Same as Previous Field | Hides additional fields | Slot 2+ only, not in template; stripped to '' per standard rules for default option |
| `text`, `image`, `content` | `key` | Meta/Custom Field | Shows/enables meta key field | — |
| `text` | `title` | Title/Name | Disables meta key field | Term name if source is term |
| `content` | `content` | Post Content/Term Description | Disables meta key field | Term description if source is term |
| `content` | `excerpt` | Post Excerpt | Disables meta key field | — |
| `image` | `featured` | Featured Image | Disables meta key field | — |

### Secondary options

| Applicable tags | Option name | Option label | Context | Notes |
|---|---|---|---|---|
| `text`, `image`, `content` | `key` | Field Meta Key | Base / Slot 1 | Aligns with and substitutes for GB native `key` option name generated by `supports => ['meta']`, to avoid issues with GB's filtering and set our own order. |
| `text`, `image`, `content` | `N-key` | Field [N] Meta Key | Slot 2+ | |

See [`datetime_` options](#datetime_single-and-datetime_range) for datetime-context label for `key`.

## Fallback option

| Applicable tags |  Option type | Notes |
|---|---|---|
| `text`, `content`, `title`, `datetime_single`, `datetime_range` | Text field | |
| `image` | Media library selector → image ID (see `custom-image-controls.md`) | |
| `permalink` | TBD — can be text field initially | Add page/post selector? |

---

## Option render order (per template)

Option order as proposed after all approved renames. `[source options]` is a placeholder for the source selector block and its conditional sub-options (`ref`, `srcTerm`, `tax`; plus `limit`/`sep` for applicable templates). Template-specific options follow in the order listed below.

**Three-group structure (applies to all templates):**
- **Group 1 — global formatting:** `as`, format options, separators. Not per-slot; applies to the assembled result.
- **Group 2 — per-slot:** source selector → source secondary options (`ref`, `srcTerm`, `tax`, `limit`, `sep`) → field options (`use`, `key`). Repeated for each try_ slot.
- **Group 3 — global fallback:** `fallback`. Once, after all slots.

Show/hide conditions are noted inline; all other options are always visible.

**`show_if` condition types** (implemented in `assets/js/editor-conditional-options.js`):
- `'not_empty'` — passes when option has any value
- `'empty'` — passes when option is unset/blank
- `'not:value'` — passes when option does not equal `value`
- `'value'` (literal string) — passes when option equals that exact string
- `'in:v1,v2,...'` — passes when option equals any listed value *(new)*
- `'not_in:v1,v2,...'` — passes when option equals none of the listed values *(new)*

Multiple conditions in one `show_if` map are AND'd. Array-of-conditions per key is not implemented.

### `text`

`[source options]` → `use` (`key` (unset default in single-slot tags); `title`) → `key` (shown when `use` unset [in single-slot tags] or `use:key`) → `fallback`

### `image`

| # | Option label | Option name | Notes |
|---|---|---|---|
| 1 | Return As | `as` | return type: `url` / `alt` / `id` / `caption` — always serialized |
| 2 | Image Size | `size` | image size (URL or ID returns) — see `custom-image-controls.md` |
| 3 | | `[source options]` | no `limit`/`sep` for image |
| 4 | | `use` | `key` (unset default in single-slot tags); `featured` | `featured` disabled for term-context entities unless `source` = `ref`. |
| 5 | | `key` | shown when `use` unset [in single-slot tags] or `use:key` |
| 6 | | `[fallback option]` | media picker — see `custom-image-controls.md` |

**`as` serialization exception:** `as` default (`url`) always serialized — `{{image as:url|...}}` even when unmodified. Enables copy-paste between image-src and alt-text fields with minimal editing.

### `content`

`[source options]` → `use` (`content` (unset default in single-slot tags); `excerpt`; `key`) → `key` (shown when `use:key`) → `fallback`

### `datetime_single` and `datetime_range`

| # | Option label | Option name | `datetime_single` | `datetime_range` | Values/Notes |
|---|---|---|---|---|---|
| 1 | Return As | `as` | ✓ | ✓ | `datetime`; `date`; `time` |
| 2 | Start & End Separator | `rangeSep` | — | ✓ | separator between start and end values within one result |
| 3 | Custom Format | `format` | ✓ | ✓ | PHP format string; empty = auto |
| 4 | Date & Time Separator | `timeSep` | ✓ | ✓ | shown when `as` ≠ `date` AND `as` ≠ `time` AND `format` empty |
| 5 | Show time when stored as midnight? | `showMidnight` | ✓ | ✓ | checkbox, false by default; shown when `as` ≠ `date` |
| 6 | Show current year in date? | `showCurrentYear` | ✓ | ✓ | checkbox, false by default; shown when `as` ≠ `time` |
| 7 | | `[source options]` | ✓ | ✓ | `limit`/`sep` included for this template |
| 8a | Date/Time Field | `key` | ✓ | — | primary date/time field key |
| 9a | Time Field (Optional) | `timeKey` | ✓ | — | separate time field — shown when `as` ≠ `date` |
| 8b | Start Date/Time Field | `startKey` | — | ✓ | |
| 9b | Start Time Field (Optional) | `startTimeKey` | — | ✓ | shown when `as` ≠ `date` |
| 10 | End Date/Time Field | `endKey` | — | ✓ | |
| 11 | End Time Field (Optional) | `endTimeKey` | — | ✓ | shown when `as` ≠ `date` |
| 12 | | `[fallback option]` | ✓ | ✓ | |

**Design rationale:** Global formatting options (`as`, `rangeSep`, `format`, `timeSep`, `showMidnight`, `showCurrentYear`) lead as group 1 — not per-slot. Source selector follows as group 2 (includes `limit`/`sep` for list-mode templates). Field keys close as group 3. `fallback` last.

---

## Updating this document

This is a **living reference**. Update it immediately when any of the following change:

- A new source is added or removed
- A new template is added or removed
- A default-enabled status changes
- A required option is added, removed, or renamed
- List mode support changes for a template
- A try_ tag is added or its slot behavior changes
- An option rename moves from "Under consideration" to "Approved" or "Implemented"

**When adding a new source:**
1. Determine its starting context (post or term) and add a row to the appropriate Sources table.
2. Add a column to the appropriate Tag Matrix table.
3. Fill in each cell (✅ / ☐ / — / GB).
4. Note any required options in the Options table.
5. Note list-mode applicability.
6. Remove ★ once released.

**When adding a new template:**
1. Add a row to both Tag Matrix tables (with — where context doesn't apply).
2. Note required options, list-mode support, and whether `supports_try` applies.
3. If `supports_try = true`, add a row to the Try_ tags table.
4. Remove ★ once released.

**Deduplication rule:** Other docs (CLAUDE.md, plugin-integration.md, etc.) should reference this
file rather than maintaining their own source, template, or option name tables.
