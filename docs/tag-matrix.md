# BWS Dynamic Tags — Tag × Source Matrix

This document is the authoritative reference for which dynamic tag variants exist, which source
generates each one, and whether it is enabled by default. Update this file whenever sources or
templates are added or default-enabled status changes.

---

## Notation

| Symbol | Meaning |
|--------|---------|
| ✅ | Generated, **enabled** by default |
| ☐ | Generated, **opt-in** (disabled by default in admin settings) |
| — | Not applicable — template context type does not match source |
| GB | Not generated — GB (or GB Pro) already registers this tag name; skipped by collision check |
| ★ | New — added by source-stacking plan, not yet implemented |

Tags generated with GB's built-in `post_title`, `post_excerpt`, and `post_permalink` names are
silently skipped at registration time. The collision check queries
`GenerateBlocks_Register_Dynamic_Tag::get_tags()` dynamically, so any tag already registered by
GB or another plugin is automatically avoided.

---

## Sources

| Source key | Tag prefix | Context | Registered by | Notes |
|---|---|---|---|---|
| `post` | `post_` | Post (current) | Built-in | CurrentPost source; direct tags |
| `related_post` | `related_post_` | Post (related) | Built-in | CurrentPost related-variant; requires `rel` option |
| `term` | `term_` | Term (current) | Built-in | TaxonomyTerm source; archive pages + term loops |
| ★`term_related_post` | `term_related_post_` | Post (related from term) | Built-in ★ | TaxonomyTerm related-variant; requires `rel` option on term entity |
| ★`second_related_post` | `second_related_post_` | Post (2nd-degree) | Built-in ★ | Two-hop ACF traversal; requires both `rel` and `rel_2` options |
| `portal` | `portal_` | Post (portal) | `bws-portal-system` | External source; registered via `bws_dynamic_tags_register_sources` hook |

---

## Tag Matrix

The row label is the **template key**. The full tag name for any cell is `{prefix}{template_key}`,
e.g. `related_post_custom_text` = `related_post_` + `custom_text`.

| Template | `post_` | `related_post_` | `term_` | ★`term_related_post_` | ★`second_related_post_` | `portal_` |
|---|---|---|---|---|---|---|
| **title** | GB | ✅ | ✅ | ★☐ | ★☐ | ☐ |
| **content** | ✅ | ✅ | — | ★☐ | ★☐ | ☐ |
| **excerpt** | GB | ✅ | — | ★☐ | ★☐ | ☐ |
| **permalink** | GB | ✅ | ✅ | ★☐ | ★☐ | ☐ |
| **description** | — | — | ✅ | — | — | — |
| **custom_text** | ✅ | ✅ | ✅ | ★☐ | ★☐ | ☐ |
| **featured_image** | ✅ | ✅ | — | ★☐ | ★☐ | ☐ |
| **custom_image** | ✅ | ✅ | ✅ | ★☐ | ★☐ | ☐ |
| **custom_date_single** | ✅ | ☐ | ☐ | ★☐ | ★☐ | ☐ |
| **custom_date_range** | ✅ | ☐ | ☐ | ★☐ | ★☐ | ☐ |
| **custom_datetime_single** | ✅ | ☐ | ☐ | ★☐ | ★☐ | ☐ |
| **custom_datetime_range** | ✅ | ☐ | ☐ | ★☐ | ★☐ | ☐ |
| ★**term_title** | ★✅ | ★✅ | — | ★☐ | ★☐ | ★☐ |
| ★**term_permalink** | ★✅ | ★✅ | — | ★☐ | ★☐ | ★☐ |
| ★**term_description** | ★✅ | ★✅ | — | ★☐ | ★☐ | ★☐ |
| ★**term_custom_text** | ★✅ | ★✅ | — | ★☐ | ★☐ | ★☐ |
| ★**term_custom_image** | ★✅ | ★✅ | — | ★☐ | ★☐ | ★☐ |

---

## Options required per template/source combination

Some tag variants require specific options to be configured in the GB editor before they produce
output. Missing required options cause the tag to return empty string (no error).

| Template | Required option(s) | Notes |
|---|---|---|
| All `related_post_` variants | `rel` — ACF relationship/post_object field key | Identifies which relationship field to traverse |
| All `term_related_post_` variants | `rel` — ACF relationship/post_object field key on the term entity | Traverses from term to related post |
| All `second_related_post_` variants | `rel` + `rel_2` — two ACF relationship field keys | First hop (`rel`) then second hop (`rel_2`) |
| `custom_text` (all sources) | `key` — ACF or meta field key | Via GB's `meta` support |
| `custom_image` (all sources) | `field_key` — ACF image field key | |
| `custom_date_single` (all sources) | `date_time_field` — ACF date field key | |
| `custom_date_range` (all sources) | `start_field` — ACF start date field key | `end_field` optional |
| `custom_datetime_single` (all sources) | `date_time_field` — ACF datetime field key | |
| `custom_datetime_range` (all sources) | `start_field` — ACF start datetime field key | `end_field` optional |
| ★`term_*` templates (all sources) | `taxonomy` — which taxonomy to look up | Post-referenced; gets first term of this taxonomy on the resolved post |
| ★`term_custom_text` | `taxonomy` + `key` | |
| ★`term_custom_image` | `taxonomy` + `field_key` | |

---

## List mode (`limit` + `sep`)

Selected templates support outputting multiple results as a delimited list. `limit` defaults to 1
(single result). When `limit > 1`, results are joined with `sep` (default: `, `).

`limit` applies to the **final traversal step**: terms for `term_*` extraction templates; related
posts for other `related_post_` / `term_related_post_` templates.

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
| ★`term_title` (all sources) | ★✅ | Terms in taxonomy |
| ★`term_permalink` | ❌ | Scalar URL |
| ★`term_description` | ❌ | Long-form prose |
| ★`term_custom_text` (all sources) | ★✅ | Terms in taxonomy |
| ★`term_custom_image` | ❌ | Scalar media |

---

## Potential future sources

These sources are architecturally compatible with the current system and will extend the matrix
with additional columns when implemented.

| Source key | Tag prefix | Description | Status |
|---|---|---|---|
| `post_term_related_post` | `post_term_related_post_` (?) | Post found in a relationship field of a taxonomy term applied to the current post | Under consideration |
| `ancestor_post` | `ancestor_` | WP top-level ancestor (hierarchical post types) | To be considered |
| `parent_post` | `parent_` | WP parent post (hierarchical post types) | Planned |
| `sibling_post` | `sibling_` | WP same-level, same-parent posts (hierarchical post types) | To be considered |
| `child_post` | `child_` | WP child posts (hierarchical post types) | To be considered |
| `user` | `user_` | Current user / post author | To be considered |

Each new source adds one column to the matrix above. Default-enabled status is set per source via
`source_default_enabled()` and `related_variant_default_enabled()` on the source class.

---

## Updating this document

When adding a new source:
1. Add a row to the Sources table above.
2. Add a column to the Tag Matrix table.
3. Fill in each cell using the notation above (✅ / ☐ / —).
4. Note any required options in the Options table.
5. Note list-mode applicability.

When adding a new template:
1. Add a row to the Tag Matrix table.
2. Fill in each source column.
3. Note required options and list-mode support in the respective tables.
4. Remove the ★ once the template is implemented and released.
