# BWS Dynamic Tags — Tag × Source Matrix

This document is the **authoritative living reference** for which dynamic tag variants exist, which
source generates each one, and whether it is enabled by default. Update this file whenever sources
or templates are added, removed, or change default-enabled status. Duplicative information in other
docs should defer to this file with a cross-reference rather than maintaining parallel tables.

---

## Notation

| Symbol | Meaning |
|--------|---------|
| ✅ | Generated, **enabled** by default |
| ☐ | Generated, **opt-in** (disabled by default in admin settings) |
| — | Not applicable — template context type does not match source |
| GB | Not generated — GB (or GB Pro) already registers this tag name; skipped by collision check |
| ★ | Planned but not yet implemented |

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

### Post-context sources

These sources resolve to a post. Use them in post loops, single-post templates, and anywhere a
post is in scope.

| Source key | Tag prefix | Traversal | Supports | Registered by | Notes |
|---|---|---|---|---|---|
| `post` | `post_` | Current post (direct) | Template as-is | Built-in | |
| `related_post` | `related_post_` | Current post → related post (ACF rel field on post) | Template − `source` | Built-in | Requires `rel` option |
| `second_related_post` | `second_related_post_` | Current post → related post → 2nd related post | Template − `source` | Built-in | Requires `rel` + `rel_2`; opt-in |
| `post_term_related_post` | `post_term_related_post_` | Current post → post's term (via `taxonomy`) → term's related post (via `rel` on term). First term only. | Template − `source` | Built-in | Requires `taxonomy` + `rel`; opt-in. |
| `portal` | `portal_` | Current post (portal context) | Template as-is | `bws-portal-system` | External; registered via `bws_dynamic_tags_register_sources` hook |

### Term-context sources

These sources resolve to a term. Use them on archive pages and in term loops.

| Source key | Tag prefix | Traversal | Supports | Registered by | Notes |
|---|---|---|---|---|---|
| `term` | `term_` | Current term (direct) | Template + `source` (always) | Built-in | Archive pages + term loops |
| `term_related_post` | `term_related_post_` | Current term → related post (ACF rel field on term) | Template − `source` | Built-in | Requires `rel` option on the term entity; opt-in. ⚠️ Starts from term context — see note. |

> ⚠️ **`term_related_post_` vs `post_term_related_post_`:** Both involve a term's related post,
> but they start from different contexts. `term_related_post_` starts on an **archive or term loop
> page** (current term is already in scope). `post_term_related_post_` starts from a **current
> post**, resolves the post's term via `taxonomy`, then hops to that term's related post via `rel` —
> a 3-hop traversal from post context. See Post-context sources above.

---

## Tag Matrix — Post-context sources

The row label is the **template key**. The full tag name is `{prefix}{template_key}`,
e.g. `related_post_custom_text` = `related_post_` + `custom_text`.

| Template | Supports | `post_` | `related_post_` | `second_related_post_` | `post_term_related_post_` | `portal_` |
|---|---|---|---|---|---|---|
| **title** | `link`, `source` | GB | ✅ | ☐ | ✅ | ☐ |
| **content** | `source` | ✅ | ✅ | ☐ | ✅ | ☐ |
| **excerpt** | `source` | GB | ✅ | ☐ | ✅ | ☐ |
| **permalink** | `source` | GB | ✅ | ☐ | ✅ | ☐ |
| **custom_text** | `meta`, `link`, `source` | ✅ | ✅ | ☐ | ✅ | ☐ |
| **featured_image** | `image-size` | ✅ | ✅ | ☐ | ✅ | ☐ |
| **custom_image** | `image-size` | ✅ | ✅ | ☐ | ✅ | ☐ |
| **custom_date_single** | `source` | ✅ | ☐ | ☐ | ✅ | ☐ |
| **custom_date_range** | `source` | ✅ | ☐ | ☐ | ✅ | ☐ |
| **custom_datetime_single** | `source` | ✅ | ☐ | ☐ | ✅ | ☐ |
| **custom_datetime_range** | `source` | ✅ | ☐ | ☐ | ✅ | ☐ |
| **term_title** | `link`, `source` | ✅ | ✅ | ☐ | — | ☐ |
| **term_permalink** | `source` | ✅ | ✅ | ☐ | — | ☐ |
| **term_description** | `source` | ✅ | ✅ | ☐ | — | ☐ |
| **term_custom_text** | `meta`, `source` | ✅ | ✅ | ☐ | — | ☐ |
| **term_custom_image** | `image-size`, `source` | ✅ | ✅ | ☐ | — | ☐ |

`description` is not listed — it is a term-context-only template with no post-context implementation.

---

## Tag Matrix — Term-context sources

| Template | Supports | `term_` | `term_related_post_` |
|---|---|---|---|
| **title** | `link`, `source` | ✅ | ☐ |
| **content** | `source` | — | ☐ |
| **excerpt** | `source` | — | ☐ |
| **permalink** | `source` | ✅ | ☐ |
| **description** | — | ✅ | — |
| **custom_text** | `meta`, `link`, `source` | ✅ | ☐ |
| **featured_image** | `image-size` | — | ☐ |
| **custom_image** | `image-size` | ✅ | ☐ |
| **custom_date_single** | `source` | ☐ | ☐ |
| **custom_date_range** | `source` | ☐ | ☐ |
| **custom_datetime_single** | `source` | ☐ | ☐ |
| **custom_datetime_range** | `source` | ☐ | ☐ |

`term_*` (term-extraction) templates are not listed — they extract terms FROM a post and have no
meaning in a term-context source.

---

## Default-enabled logic

Resolution order for each cell (first match wins):

1. Explicit admin setting saved by the user
2. `default_enabled_map` on the template (keyed by source prefix without trailing `_`)
3. `tag_default_enabled()` / `related_variant_default_enabled()` on the source class
4. Fallback: `true` (enabled)

The source toggle itself defaults to `source_default_enabled()` on the source class (true for all built-in sources except `second_related_post` and `post_term_related_post`).

Drivers of the ☐ cells above:

- **`second_related_post_` (all)** — source off by default (`source_default_enabled() = false`); tags ✅ when source enabled (`tag_default_enabled() = true`)
- **`post_term_related_post_` (all)** — source off by default (`source_default_enabled() = false`); tags ✅ when source enabled (`tag_default_enabled() = true`)
- **`term_related_post_` (all)** — `related_variant_default_enabled() = false` on TaxonomyTerm
- **date/datetime × `related_post_` and `term_`** — `default_enabled_map: [related_post => false, term => false]` on each date template
- **`portal_` (all)** — external source; assumed opt-in pending portal plugin declaration

---

## Try_ tags

`try_` tags are **source-agnostic fallback chains**. A single tag tries up to 5 slots in sequence
and returns the first non-empty result. The user configures which source each slot uses at the
tag instance level — there is no source prefix in the tag name.

Slots are configured via options `src_N`, `rel_N`, and (for per-slot-key templates) `key_N`,
where N is 1–5. Slots 3–5 are hidden in the editor until the previous slot is configured.

**Per-slot-type** templates (`try_per_slot_type: true`) add a `type_N` select (Content/Description vs
Custom Field) and a conditional `key_N` input per slot, allowing each slot to draw from a different
content type — e.g. "try ACF field, fall back to post content".

`SecondRelatedPost` is excluded from try_ slot sources (its two-hop traversal requires its own
dedicated options that don't fit the slot model).

### Available try_ tags

| Tag name | Based on template | Per-slot `key_N`? | Per-slot `type_N`? | Notes |
|---|---|---|---|---|
| `try_content` | `content` | No | **Yes** | Each slot can be Content/Description or Custom Field |
| `try_title` | `title` | No | No | |
| `try_permalink` | `permalink` | No | No | |
| `try_custom_text` | `custom_text` | **Yes** | No | Each slot's field key can differ |
| `try_featured_image` | `featured_image` | No | No | |
| `try_custom_image` | `custom_image` | **Yes** | No | Each slot's field key can differ |
| `try_custom_date_single` | `custom_date_single` | No | No | Shared `date_time_field` across slots |
| `try_custom_date_range` | `custom_date_range` | No | No | Shared `start_field`/`end_field` across slots |
| `try_custom_datetime_single` | `custom_datetime_single` | No | No | Shared `date_time_field` across slots |
| `try_custom_datetime_range` | `custom_datetime_range` | No | No | Shared `start_field`/`end_field` across slots |

Templates without `supports_try`: `excerpt`, `description`, and all `term_*` extraction
templates. These are either long-form prose (no meaningful "first non-empty" logic) or require
entity-iteration that doesn't fit the slot model.

---

## Options required per template/source combination

Some tag variants require specific options to be configured in the GB editor before they produce
output. Missing required options cause the tag to return empty string (no error).

| Template | Required option(s) | Notes |
|---|---|---|
| All `related_post_` variants | `rel` — ACF relationship/post_object field key | Identifies which relationship field to traverse |
| All `term_related_post_` variants | `rel` — ACF relationship/post_object field key on the term entity | Traverses from current term to related post |
| All `second_related_post_` variants | `rel` + `rel_2` — two ACF relationship field keys | First hop (`rel`) then second hop (`rel_2`) |
| All `post_term_related_post_` variants | `taxonomy` — taxonomy slug; `rel` — relationship field key on the term entity | First term in the taxonomy is used; the `rel` field is on the term, not the post. |
| `content` (all sources, `type = custom_field`) | `key` — ACF or meta field key | Required when Content Type is set to Custom Field |
| `custom_text` (all sources) | `key` — ACF or meta field key | Via GB's `meta` support |
| `custom_image` (all sources) | `field_key` — ACF image field key | |
| `custom_date_single` (all sources) | `date_time_field` — ACF date field key | |
| `custom_date_range` (all sources) | `start_field` — ACF start date field key | `end_field` optional |
| `custom_datetime_single` (all sources) | `date_time_field` — ACF datetime field key | |
| `custom_datetime_range` (all sources) | `start_field` — ACF start datetime field key | `end_field` optional |
| `term_*` templates (all sources) | `taxonomy` — which taxonomy to look up | Post-context extraction; gets first term of this taxonomy on the resolved post |
| `term_custom_text` | `taxonomy` + `key` | |
| `term_custom_image` | `taxonomy` + `field_key` | |

---

## List mode (`limit` + `sep`)

Selected templates support outputting multiple results as a delimited list. `limit` defaults to 1
(single result). When `limit > 1`, results are joined with `sep` (default: `, `).

`limit` applies to the **final traversal step**: terms for `term_*` extraction templates; related
posts for `related_post_` / `term_related_post_` variants.

| Template | List mode | What is iterated |
|---|---|---|
| `title` (related-variant only) | ✅ | Related posts |
| `content` | ❌ | Long-form prose |
| `excerpt` (related-variant only) | ❌ | Long-form prose |
| `permalink` | ❌ | Scalar URL |
| `description` | ❌ | Long-form prose |
| `custom_text` (related-variant only) | ✅ | Related posts |
| `featured_image` | ❌ | Scalar media |
| `custom_image` | ❌ | Scalar media |
| `custom_date_single` (related-variant only) | ✅ | Related posts |
| `custom_date_range` (related-variant only) | ✅ | Related posts |
| `custom_datetime_single` (related-variant only) | ✅ | Related posts |
| `custom_datetime_range` (related-variant only) | ✅ | Related posts |
| `term_title` (all sources) | ✅ | Terms in taxonomy |
| `term_permalink` | ❌ | Scalar URL |
| `term_description` | ❌ | Long-form prose |
| `term_custom_text` (all sources) | ✅ | Terms in taxonomy |
| `term_custom_image` | ❌ | Scalar media |

---

## Potential future sources

These sources are architecturally compatible with the current system and will extend the matrix
with additional columns when implemented.

| Source key | Tag prefix | Description | Status |
|---|---|---|---|
| `ancestor_post` | `ancestor_` | WP top-level ancestor (hierarchical post types) | To be considered |
| `parent_post` | `parent_` | WP parent post (hierarchical post types) | Planned |
| `sibling_post` | `sibling_` | WP same-level, same-parent posts (hierarchical post types) | To be considered |
| `child_post` | `child_` | WP child posts (hierarchical post types) | To be considered |
| `user` | `user_` | Current user / post author | To be considered |

Each new post-context source adds a column to the post-context matrix. Each new term-context source
adds a column to the term-context matrix. Default-enabled status is set per source via
`source_default_enabled()` and `related_variant_default_enabled()` on the source class.

---

## Updating this document

This is a **living reference**. Update it immediately when any of the following change:

- A new source is added or removed
- A new template is added or removed
- A default-enabled status changes (via `default_enabled_map`, `source_default_enabled()`, or `related_variant_default_enabled()`)
- A required option is added, removed, or renamed
- List mode support changes for a template
- A try_ tag is added or its slot behavior changes

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
file rather than maintaining their own source or template tables.
