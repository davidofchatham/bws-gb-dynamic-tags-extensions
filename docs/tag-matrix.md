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
control which editor controls appear for a tag. Values are `source` (entity picker), `link`,
`meta` (field key input), `image-size`. The source table's Supports column shows what the source
adds or removes from the template's base supports (`+source always` / `−source` / `as-is`).
The matrix Supports column shows the template's base array before any source modifier is applied.

---

## Sources

Sources are grouped by their **starting context** — what entity provides the initial ID for the tag.

> **Planned architecture note:** In the current N×M model, each source has a tag prefix that
> appears in every registered tag name. In the planned source-agnostic architecture, sources map
> to `via` option values on base tags (e.g. `via:ref` for a related post traversal). The "Tag prefix" column
> reflects current implementation; `via` values are listed in the Architecture overview section of
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

### Traversal registry and `via` values (planned architecture)

In the source-agnostic architecture, the `via` option names a **traversal** — a chain of relationship
hops applied to the current base entity. Traversal names are composed left-to-right,
underscore-separated, using relationship type indicators:

- `ref` — reference/relational field hop (one hop via a reference field; option key: `ref`, or `ref1`/`ref2` in multi-hop)
- `tax` — taxonomy term association (hop to a term; requires `HasTaxonomy` on base entity)
- `parent`, `child`, etc. — future WP hierarchy traversals

When the same type appears more than once in a chain, its associated options get a numeric hop
counter (e.g. `via:ref_ref` → options `ref1`, `ref2`). Single-occurrence traversal options are
unnumbered (e.g. `via:tax_ref` → options `tax`, `ref`).

`get_title_prefix()` is a **N×M-model artifact** and is removed in Step 2. Traversal labels come
from the traversal registry (a static `via` → label map), used in two places:

- **`via` option dropdown** — label for each selectable value in the base tag's traversal selector
- **Preview label segment** — the string inside `[…]` produced by `bws_build_preview_label()`

| `via` value | Traversal chain | Label | Required options | Notes |
|---|---|---|---|---|
| unset / `''` | direct (current entity) | Current (no traversal) | — | Default; never serialized. Preview label emits nothing for unset `via` (no source segment). |
| `'ref'` | reference/relational field hop | `Reference/Relational Field` | `ref` | |
| `'ref_ref'` | reference/relational field hop × 2 | `Ref/Rel Field → 2nd Ref/Rel Field` ⚠️ | `ref1`, `ref2` | see label fix below |
| `'tax'` | entity → taxonomy term | `Term` | `tax` | `HasTaxonomy` required on base entity |
| `'tax_ref'` | entity → term → reference/relational field | `Term → Ref/Rel Field` ⚠️ | `tax`, `ref` | `HasTaxonomy` required; see label fix below |

> **Portal source:** The portal plugin is a **context modifier**, not a traversal. It registers its own tag group externally (via `bws_dynamic_tags_register_sources` hook) with its own base tags. Those base tags support the same `via` traversals (`ref`, `ref_ref`, `tax_ref`) as standard base tags — portal is the starting context, not a hop. `portal` and `portal_rp` do not appear as `via` values. Deprecated wrappers for `portal_*` tags are handled by the portal plugin itself.

⚠️ **Pending traversal label fixes (prerequisite for Step 2):**
- `ref_ref` label: traversal registry must return `'Ref/Rel Field → 2nd Ref/Rel Field'`. Replaces `SecondRelatedPost.get_source_label()` which currently returns `'Second Related Post (ACF)'`.
- `tax_ref` label: traversal registry must return `'Term → Ref/Rel Field'`. Replaces `PostTermRelatedPost.get_source_label()` which currently returns `'Post → Term → Related Post'`.
- **Unset `via` label (SETTLED):** "Current (no traversal)". Parenthetical explains no configuration is needed. Never serialized. Preview label emits nothing for unset `via` — no source segment. See consolidation plan Q6 (CLOSED).

---

## Tag Matrix — Post-context sources

The row label is the **template key**. The full tag name is `{prefix}{template_key}`,
e.g. `related_post_custom_text` = `related_post_` + `custom_text`.

| Template | Supports | Options | `post_` | `related_post_` | `second_related_post_` | `post_term_related_post_` | `portal_` |
|---|---|---|---|---|---|---|---|
| **title** | `link`, `source` | — | GB | ✅ | ✅ | ✅ | ☐ |
| **content** | `source` | `from`, `key`, `fallback` | ✅ | ✅ | ✅ | ✅ | ☐ |
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

† `excerpt` is approved for consolidation into `content` as `from:excerpt`. `featured_image` and `custom_image` are approved for consolidation into `image` (`from` unset = meta field (uses `key`); `from:featured` = Featured Image). See template key renaming tracker.

---

## Tag Matrix — Term-context sources

| Template | Supports | Options | `term_` | `term_related_post_` |
|---|---|---|---|---|
| **title** | `link`, `source` | — | ✅ | ✅ |
| **content** | `source` | `from`, `key`, `fallback` | — | ✅ |
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

† `excerpt` is approved for consolidation into `content` as `from:excerpt`. `featured_image` and `custom_image` are approved for consolidation into `image` (`from` unset = meta field; `from:featured` = Featured Image). `description` is approved for consolidation into `content` — for term-context, `content` with `from` unset outputs the term description; no `fixed_options` needed. See template key renaming tracker.

---

## Default-enabled logic

> **Planned architecture note:** The resolution order below applies to the current N×M model where
> each source×template combination produces a separately-registered tag. In the planned
> source-agnostic architecture, per-source-tag defaults are eliminated — there is one tag
> registration per template, and all `via` values are always available. The `default_enabled_map`
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

Slots use the prefix `sN-` where N is the slot number (s1–s5). Each slot's options are prefixed
accordingly: `s1-via`, `s1-key`, `s1-ref`, etc. Slots s3–s5 are hidden in the editor until the
previous slot is configured.

The `sN-` prefix was confirmed safe: GB's `parse_options()` splits on `|` and `:` only; hyphens
in option keys are treated as plain characters.

**Traversal option naming within a slot:** When a traversal type appears only once in the slot's
`sN-via` value, its options are unnumbered: `s1-ref`, `s1-tax`. When the same traversal type
repeats (e.g. `via:ref_ref`, `via:tax_ref_ref_tax`), options for each occurrence carry a
numeric counter: `s1-ref1`, `s1-ref2`, `s1-tax1`, `s1-tax2`. The numeric counter is the hop
counter within the traversal chain; the `sN-` prefix is the slot counter. These are different
scopes, separated by the hyphen. Example:

```
{{try_text s1-via:tax_ref_ref_tax|s1-tax1:category|s1-ref1:field_a|s1-ref2:field_b|s1-tax2:tag|s1-key:body_text|s2-via:ref|s2-ref:field_a|s2-key:body_text}}
```

**Per-slot `sN-from`** is available on templates that support mode selection per slot, allowing each
slot to draw from a different content type — e.g. "try ACF/meta field, fall back to post content".

### Available try_ tags

| Tag name | Based on template | Per-slot `sN-key`? | Per-slot `sN-from`? | Notes |
|---|---|---|---|---|
| `try_content` | `content` | No | **Yes** | Each slot can be Content/Description or ACF/Custom Field |
| `try_title` | `title` | No | No | |
| `try_permalink` | `permalink` | No | No | |
| `try_text` | `text` | **Yes** | No | Each slot's field key can differ |
| `try_image` | `image` | **Yes** | **Yes** | `sN-from` unset = ACF/meta field (uses `sN-key`); `sN-from:featured` = Featured Image. |
| `try_datetime_single` | `datetime_single` | No | No | Shared `key` across slots |
| `try_datetime_range` | `datetime_range` | No | No | Shared `start_key`/`end_key` across slots |

Templates without `supports_try`: `description` (long-form prose; no meaningful "first non-empty"
logic). `excerpt` is excluded as it consolidates into `content`; use `try_content` with
`sN-from:excerpt` for excerpt fallback chains. `featured_image` is excluded as it consolidates into
`image`; use `try_image` with `sN-from:featured` for featured image slots.

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
| `content` (all sources, `from:key`) | `key` — ACF or meta field key | Required when `from` is set to `key` |
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

These WP hierarchy relationships are candidates for `via` values in the traversal registry (see
[Traversal registry and `via` values](#traversal-registry-and-via-values-planned-architecture)
above). They do **not** create new source classes or new matrix columns — they are additional
`via` options selectable on the existing base tags.

| `via` value | Traversal | Description | Status |
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
| `image` | `'media'` | Media picker for fallback; `via` option coexists — type governs UI chrome only |
| `datetime_single` | `'cross-source'` | |
| `datetime_range` | `'cross-source'` | |

The term_ modifier produces additional tags with GB type `'term'`: `term_text`, `term_image`, `term_title`, `term_permalink`. `via` unset = user-selected term (never serialized); `via:'ref'` = term→related post traversal. `term_image` uses GB type `'term'` (not `'media'`), so `as` and `size` must be registered as custom options (GB's native media controls require `'media'` type). `as` serialization exception applies to `term_image` as well — default `as:url` is always written to the tag string.

**try_ modifier** produces `try_text`, `try_image`, etc. with GB type `'first-available'`. Up to 5 slots (s1–s5); slots revealed progressively as earlier slots are configured.

**`as` serialization exception:** For `image` and `term_image` tags, the `as` option default (`url`) is always serialized into the tag string even when unmodified — `{{image as:url|...}}`. This makes the return mode immediately visible when copying a tag instance between fields (e.g. image src → alt text). All other defaults follow the standard rule (never serialize defaults).

---

## Template key renaming tracker

Tracks planned template key renames and consolidations. When a template key changes, the generated
tag names for all sources change with it. Deprecated wrappers are registered via `DeprecatedTagRegistry`
for each old tag name and appear in the editor picker under the "Deprecated" group. Status values
match the option renaming tracker below.

In the source-agnostic architecture, the "New tag" column shows the single registered base tag.
Each per-source old tag becomes a deprecated wrapper with `via_inject` set to the source's `via`
value (see §Source `via` values above) and `option_renames` as listed below.

| Current key | New key | Old tag example | New tag (base) | `via_inject` | `option_renames` | Status | Notes |
|---|---|---|---|---|---|---|---|
| `excerpt` | *(folded into `content`)* | `post_excerpt` | `{{content from:excerpt}}` | source abbrev. | none; `fixed_options: ['from' => 'excerpt']` | Approved | `from:excerpt` added as third value on `content`'s `from` option. `bws_post_excerpt_core()` retained as internal function. GB's `post_excerpt` is not a conflict (never registered by us). |
| `featured_image` | *(folded into `image`)* | `post_featured_image` | `{{image from:featured}}` | source abbrev. | `as → as` (no rename); `fixed_options: ['from' => 'featured']` | Approved | Deprecated wrappers inject `from:featured` — unset now means ACF/meta field (custom image). `image-size` support carried over. |
| `custom_text` | `text` | `post_custom_text` | `{{text}}` | source abbrev. | `fallback_text → fallback`, `type → from` | Approved | Removes `meta` from supports; own `key` input replaces GB pass-through. |
| `custom_image` | *(folded into `image`)* | `post_custom_image` | `{{image key:…}}` | source abbrev. | `fallback_url → fallback`, `return_type → as`, `field_key → key` | Approved | No `fixed_options` or `type → from` rename needed — `custom_image` never had a `type` option, and `from` unset is now the ACF/meta field default. Term-source `image` variants also default to ACF/meta field — no featured image concept applies to terms. |
| `custom_date_single` | `datetime_single` | `post_custom_date_single` | `{{datetime_single as:date}}` | source abbrev. | see §Date-time option names; `fixed_options: ['as' => 'date']` | Approved | Merged with datetime_single via `as` option. |
| `custom_date_range` | `datetime_range` | `post_custom_date_range` | `{{datetime_range as:date}}` | source abbrev. | see §Date-time option names; `fixed_options: ['as' => 'date']` | Approved | Merged with datetime_range via `as` option. |
| `custom_datetime_single` | *(merged into `datetime_single`)* | `post_custom_datetime_single` | `{{datetime_single}}` | source abbrev. | see §Date-time option names | Approved | Default mode (unset) = datetime. |
| `custom_datetime_range` | *(merged into `datetime_range`)* | `post_custom_datetime_range` | `{{datetime_range}}` | source abbrev. | see §Date-time option names | Approved | |
| `description` | *(folded into `content`)* | `term_description` | `{{content}}` | source abbrev. | none | Approved | Term-context only — no post-context variant exists. `content` with `from` unset for a term entity outputs term description; no `fixed_options` needed. `bws_term_description_core()` retained as internal function. |

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
| `src_N` (try_ slots) | `sN-via` | try_ templates | Approved | try_ uses `sN-` prefix style (e.g. `s1-via`, `s2-via`). Shim: `$options["s{$n}-via"] ?? $options["src_{$n}"] ?? ''`. (`sN-from` and `from_N` were doc-only names, never in PHP.) |
| `type` (field/mode selector) | `from` | content, text, image | Approved | Each tag defaults to its primary source (unset = default); `from` only appears when overriding. `content`: unset=post content/term description, `from:excerpt`, `from:key` (ACF WYSIWYG/content-area field, incl. ACF Extended block editor areas). `text`: unset=ACF/meta field (uses `key`), `from:title`. `image` (post sources): see table below. Note: `from:key` is never written on `text` or `image` (post sources) since ACF/meta field is the unset default for both. Current PHP name is `type`; `field` and `in` were doc-only intermediates, never in PHP. Shim: `$options['from'] ?? $options['type'] ?? ''`. |
| value `custom_field` | `key` | `from` option | Approved | Selects the ACF/meta field mode: "use the field named in `key`". Supersedes intermediate rename `custom_field` → `meta` (approved but never implemented). Not applicable to `text` (ACF/meta field is its unset default). |
| `type_N` (try_ slots) | `sN-from` | try_ templates | Approved | try_ uses `sN-` prefix style (e.g. `s1-from`, `s2-from`). New option — `type_N`, `field_N`, `in_N`, `sN-field` were all doc-only names, never in PHP. No shim needed. |
| `taxonomy` | `tax` | term extraction | Approved | Aligns with GB's `tax` (used by `term_list`); consistency reduces risk if GB ever registers conflicting tag names |
| `from` (traversal selector) | `via` | all templates | Approved | Traversal selector — names the chain of hops to reach the target entity. Replaces docs-only `from` which itself replaced early proposed `src`. Never implemented in PHP under any name. No shim needed. GB/GB Pro collision check required before implementation. |
| traversal value `term` | `tax` | `via` option | Approved | Traversal type indicator for taxonomy term hop. Renamed `term` → `tax` for consistency: `via` value now matches the option key it generates (`tax`). Composed values: `term_ref` → `tax_ref`. Migrator: `via:term` → `via:tax`; `via:term_*` → `via:tax_*`. |
| `rel` | `ref` | all traversals with a single `ref` hop (`via:ref`, `via:tax_ref`) | Approved | Reference/relational field key — the option that stores the field name used to traverse. Renamed from `rel` (option key) and traversal value `rel` → `ref` for vendor-agnostic clarity (2026-04-13). Shim: `$options['ref'] ?? $options['rel'] ?? ''`. |
| `rel` | `ref1` | `ref_ref` traversal | Approved | First-hop reference field key. Numeric counter because `ref` type repeats. Supersedes intermediate rename `1st_rel` (approved but never implemented). Deprecated wrappers for `second_related_post_*` tags use `option_renames: {rel → ref1, rel_2 → ref2}` and `via_renames: {rel_rel → ref_ref}`. |
| `rel_2` | `ref2` | `ref_ref` traversal | Approved | Second-hop reference field key. Supersedes intermediate rename `2nd_rel` (approved but never implemented). |

### Image-related option names

| Current name | Proposed name | Scope | Status | Notes |
|---|---|---|---|---|
| `return_type` | `as` | image templates | Implemented | Aligns with datetime `as` option (same role: selects output mode). Callbacks read `$options['as'] ?? $options['return_type']` for backward compat. |
| `field_key` | `key` | image templates | Implemented | `as` freed up `key` — field name now aligns with GB's own `post_meta`/`term_meta` convention. Callbacks read `$options['key'] ?? $options['field_key'] ?? $options['meta_key']`. |
| `type` (field/mode selector) | `from` | content, text, image | Approved | Each tag defaults to its primary source (unset = default); `from` only appears when overriding. `image` (post sources): unset=ACF/meta field (uses `key`), `from:featured`. `image` (term sources): `from` not offered — always ACF/meta field. Current PHP name is `type`; `field` and `in` were doc-only intermediates. Shim: `$options['from'] ?? $options['type'] ?? ''`. |

### Date-time–related option names

| Current name | New name | Scope | Status | Notes |
|---|---|---|---|---|
| `omit_current_year` | `show_current_year` | date/datetime | Approved | Flip boolean: unset = omit current year (default smart behavior); set = always show year. Fixes `default:true` serialization problem. Converter: if `omit_current_year` absent from old tag, inject `show_current_year:true` to preserve "always show year" behavior; if present, drop it. |
| `separator` | `range_sep` | datetime_range | Approved | Renamed to `range_sep` (not `sep`) to avoid collision with the list-mode `sep` option — `datetime_range` supports list mode, and `sep` is reserved for the list separator. |
| `date_time_separator` | `time_sep` | datetime | Approved | Applies only when date and time are assembled separately (separate field keys, or no combined-field format available). Hidden when `format` is set or `as` is `date` or `time`. Show condition: `{ format: 'empty', as: 'not_in:date,time' }` (uses `not_in:` condition type — see `show_if` extension). Default if unset: `', '` (not serialized). |
| `as` (new option) | — | date/datetime | Approved | Mode selector: unset = datetime (never serialized); `as:date`; `as:time`. Controls output filtering regardless of field format. Replaces `date_only` and `time_only` flags entirely. `as:date` condition in `show_if`: `as: 'not:date'`. |
| `date_time_field` | `key` | datetime_single | Approved | Primary field key; holds date, datetime, or time value per `as`. Consistent with all non-image templates. |
| `time_field` | `time_key` | datetime_single | Approved | Secondary time field for when date+time are split across separate meta fields; hidden when `as:date`. |
| `start_field` | `start_key` | datetime_range | Approved | Primary start field key; consistent with `key` rename. |
| `end_field` | `end_key` | datetime_range | Approved | Primary end field key. |
| `start_time_field` | `start_time_key` | datetime_range | Approved | Secondary start time field; hidden when `as:date`. |
| `end_time_field` | `end_time_key` | datetime_range | Approved | Secondary end time field; hidden when `as:date`. |
| `format_type` + `custom_format` | `format` | date/datetime | Approved | Single field: empty = auto (use field handler's return format, or WP date/time settings as fallback — no hardcoded default needed); non-empty = custom PHP date format string. Help text: "When unset, the format returned by the field handler is used; if unavailable, your WordPress date and time settings are used." Converter: if `format_type:custom`, rename `custom_format` → `format` and drop `format_type`; otherwise drop both. |
| `smart_time` | *(eliminated)* | datetime | Approved | AM/PM consolidation becomes always-on (ungated). Midnight suppression becomes the `show_midnight` option. `smart_time` eliminated entirely. Converter: drop `smart_time`; if it was absent and the tag would have produced time output, inject `show_midnight:true` to preserve old "always show midnight" behavior. |
| `show_midnight` (new option) | — | datetime | Approved | Unset = hide 00:00 times (default smart behavior); set = display midnight explicitly. Shown only when `as` is not `date` (`show_if: { as: 'not:date' }`). |
| `date_only` | *(eliminated)* | datetime | Approved | Replaced by `as:date`. `custom_date_*` deprecated wrappers inject `as:date` via `fixed_options`. `custom_datetime_*` wrappers with `date_only` set inject `as:date` via converter. |
| `time_only` | *(eliminated)* | datetime | Approved | Replaced by `as:time`. Converter injects `as:time` when `time_only` is present. |

---

## Option render order (per template)

Option order as proposed after all approved renames. `[via + source options]` is a placeholder
for the traversal source selector and any options it exposes (e.g. `ref`, `tax` on traversal
sources). Template-specific options follow in the order listed below.

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

| # | Option | Notes |
|---|---|---|
| 1 | `[via + source options]` | |
| 2 | `from` | unset = ACF/meta field; `title` = Post Title |
| 3 | `key` | ACF/meta field key — shown when `from` is unset |
| 4 | `fallback` | |

### `image` — post sources

| # | Option | Notes |
|---|---|---|
| 1 | `as` | return type: url / alt / id / caption |
| 2 | `[via + source options]` | |
| 3 | `from` | unset = ACF/meta field; `featured` = Featured Image |
| 4 | `key` | ACF/meta field key — shown when `from` is unset |

No `fallback` for post sources — media picker fills that role in GB.

**`as` serialization exception:** `as` default (`url`) is always serialized — `{{image as:url|...}}` even when unmodified. Enables copy-paste between image-src and alt-text fields with minimal editing.

### `image` — term sources

| # | Option | Notes |
|---|---|---|
| 1 | `as` | return type: url / alt / id / caption |
| 2 | `[via + source options]` | |
| 3 | `key` | ACF/meta field key — no `from` offered; always ACF/meta field for term sources |
| 4 | `fallback` | fallback image URL |

### `content`

| # | Option | Notes |
|---|---|---|
| 1 | `[via + source options]` | |
| 2 | `from` | unset = Content/Description; `excerpt` = post excerpt / term description; `key` = ACF/Custom Field (WYSIWYG, content area, ACF Extended block editor area) |
| 3 | `key` | ACF/meta field key — shown when `from:key` |
| 4 | `fallback` | |

### `datetime_single` (covers former `custom_date_single` via `as:date`)

| # | Option | Notes |
|---|---|---|
| 1 | `[via + source options]` | |
| 2 | `key` | primary date/time field key |
| 3 | `as` | unset = datetime; `date`; `time` |
| 4 | `time_key` | separate time field — shown when `as` ≠ `date` |
| 5 | `format` | PHP format string; empty = auto |
| 6 | `time_sep` | shown when `as` ≠ `date` AND `as` ≠ `time` AND `format` empty |
| 7 | `show_midnight` | shown when `as` ≠ `date` |
| 8 | `show_current_year` | shown when `as` ≠ `time` |
| 9 | `fallback` | |

When used as a date-only tag (former `custom_date_single`), `as:date` is injected by the deprecated
wrapper; `time_key`, `show_midnight`, and `time_sep` are therefore hidden.

### `datetime_range` (covers former `custom_date_range` via `as:date`)

| # | Option | Notes |
|---|---|---|
| 1 | `[via + source options]` | |
| 2 | `start_key` | |
| 3 | `end_key` | |
| 4 | `as` | unset = datetime; `date`; `time` |
| 5 | `start_time_key` | shown when `as` ≠ `date` |
| 6 | `end_time_key` | shown when `as` ≠ `date` |
| 7 | `range_sep` | separator between start and end values within one result |
| 8 | `sep` | list separator — shown when `limit > 1` (list mode) |
| 9 | `format` | PHP format string; empty = auto |
| 10 | `time_sep` | shown when `as` ≠ `date` AND `as` ≠ `time` AND `format` empty |
| 11 | `show_midnight` | shown when `as` ≠ `date` |
| 12 | `show_current_year` | shown when `as` ≠ `time` |
| 13 | `fallback` | |

**Design rationale:** `start_key`/`end_key` lead as always-required fields. `as` follows so the user
sets mode before conditional time-key fields appear below it. `range_sep` precedes `format` because it applies
with or without custom format. `sep` (list separator) follows `range_sep` — only relevant in list mode.
`time_sep` follows `format` since it's a supplementary formatting option.
`show_midnight`, `show_current_year`, and `fallback` close.

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
