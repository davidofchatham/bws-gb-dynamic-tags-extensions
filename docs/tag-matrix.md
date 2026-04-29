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

## Deprecated tag name reference

The N×M per-source tag matrices (`post_custom_text`, `related_post_title`, etc.) and the option rename
trackers have moved to [`docs/deprecated-tags-options.md`](deprecated-tags-options.md). All those tag
names are now deprecated wrapper registrations — base tags cover all sources via the `source` option.

---

## Default-enabled logic

In v1.6.0 the per-source×template matrix was removed from the admin settings page. Default-enabled state is now controlled at two levels:

**Modifier group toggles** — `term_` and `try_` each have an on/off toggle in the admin settings page. Disabling a modifier group removes all its tags from the GB editor picker. Both groups default to enabled.

**Deprecated wrapper tags** — each deprecated wrapper has an individual enable/disable toggle in the deprecated tags section of the settings page. Wrappers default to enabled. `SettingsPage::is_deprecated_tag_enabled( $tag_name )` reflects the current toggle state.

**Base tags** (`text`, `image`, `content`, `title`, `permalink`, `datetime_single`, `datetime_range`) are always registered with no admin toggle.

The ☐ cells in the matrices above reflect the former per-source opt-in defaults, preserved here as documentation of which deprecated wrappers were historically opt-in.

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

## Template key and option rename reference

Template key renames and option name renames have moved to [`docs/deprecated-tags-options.md`](deprecated-tags-options.md).

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
| Modifier tag (e.g. `term_`) | Modifier `label` value (e.g. `Term`) |
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

Option name renames have moved to [`docs/deprecated-tags-options.md`](deprecated-tags-options.md).

---

## Source options

| Option name | Option label | Context | Notes |
|---|---|---|---|
| `src` | Source | Base / Slot 1 | `source` avoided — GB unconditionally strips it from extraTagParams before our controls can read it |
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

Note: For context-modifier tags, the modifier label is prepended as a context segment. Examples: `[Title from Term]` for `{{term_title}}`, `[Content from Term Ref (rel_post)]` for `{{term_content src:ref|ref:rel_post}}`. See [§Editor preview label schema](#editor-preview-label-schema) for assembly rules.

### Secondary, conditional options

| Option name | Option label | Help text | Shown when | Notes |
|---|---|---|---|---|
| `ref` | Ref/Rel Field Meta Key | | `src` = `ref` | ACF relationship/relational field key for the traversal hop |
| `srcTerm` | Get from taxonomy term? | Field is in a taxonomy term on this source. | Always; hidden for `term_` modifier tags (entity already a term) | Boolean; unset by default. Term hop applied after source resolution as final step. |
| `tax` | Term Taxonomy | [Select/Enter] the taxonomy the term is in. | `srcTerm` is set | Taxonomy slug. Type TBD (text field or selector). |
| `limit` | Result Limit | This source type may return multiple results. By default, only the first result is used, but you may enter either a fixed limit, or “0” for no limit. | `src` = `ref` or `child`, or `srcTerm` set | `text`, `title`, `datetime_` only. Placeholder `1`; not serialized when unset. |
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

| Option label | Option name | `datetime_single` | `datetime_range` | Values/Notes |
|---|---|---|---|---|
| Return As | `as` | 1 | 1 | `datetime`; `date`; `time` |
| Start & End Separator | `rangeSep` | — | 2 | separator between start and end values within one result |
| Custom Format | `format` | 2 | 3 | PHP format string; empty = auto |
| Date & Time Separator | `timeSep` | 3 | 4 | shown when `as` ≠ `date` AND `as` ≠ `time` AND `format` empty |
| Show time when stored as midnight? | `showMidnight` | 4 | 5 | checkbox, false by default; shown when `as` ≠ `date` |
| Show current year in date? | `showCurrentYear` | 5 | 6 | checkbox, false by default; shown when `as` ≠ `time` |
| | `[source options]` | 6 | 7 | `limit`/`sep` included for this template |
| Date/Time Field | `key` | 7 | — | primary date/time field key |
| Time Field (Optional) | `timeKey` | 8 | — | separate time field — shown when `as` ≠ `date` |
| Start Date/Time Field | `startKey` | — | 8 | |
| Start Time Field (Optional) | `startTimeKey` | — | 9 | shown when `as` ≠ `date` |
| End Date/Time Field | `endKey` | — | 10 | |
| End Time Field (Optional) | `endTimeKey` | — | 11 | shown when `as` ≠ `date` |
| | `[fallback option]` | 9 | 12 | |

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
