# BWS Dynamic Tags — Tag × Source Matrix

**Authoritative living reference** for template keys, source keys, option names, and which dynamic tag variants exist. Also owns this plugin's response to GB constraints (default-strip strategy, custom editor controls). Update this file whenever sources, templates, options, or controls are added, removed, renamed, or change default-enabled status. Other docs cross-reference here rather than maintaining parallel tables.

See [`CLAUDE.md` §Documentation ownership](../CLAUDE.md#documentation-ownership) for the full doc ownership policy and update triggers.

---

## Sources (v1.6.0+ architecture)

Source resolution is split between **`src` option values** (traversal within a base tag), **modifier prefixes** (context-shifting wrappers), and **source classes** (PHP entity resolvers behind both).

### `src` option values

Traversal selector on every base tag. Serializes as `src:<value>` in the tag string.

| `src` value | Resolves to | Status |
|---|---|---|
| unset (default) | Current entity (post or term per template context) | Implemented |
| `ref` | Reference/relational field hop — requires `ref` sub-option (field key) | Implemented |
| `parent` | WP parent post/term | Planned |
| `ancestor` | WP top-level ancestor | To be considered |
| `child` | WP child posts/terms (list output) | To be considered |
| `sibling` | WP same-parent posts/terms (list output) | To be considered |

See [§Source options](#source-options) for label/UI details.

### Modifier prefixes

Modifiers wrap base tag templates with a context-shifting prefix. Registered via `TagTemplateRegistry::register_modifier()`. See [`docs/plugin-integration.md`](plugin-integration.md) §2 for the registration API.

| Prefix | GB type | Modifier label | Starting context | Registered by |
|---|---|---|---|---|
| (no prefix — base) | `'cross-source'` | — | Current entity (post in post loop, term on term archive) | Built-in |
| `term_` | `'term'` | (term-based) | User-selected term via GB native taxonomy/term picker | Built-in |
| `try_` | `'first-available'` | — | Per-slot — see [§Try_ tags](#try_-tags) | Built-in |
| *(external prefix)* | *(plugin-defined)* | *(plugin-defined)* | External entity | External plugin via `register_modifier()` |

### Source classes

PHP entity resolvers used by base tag callbacks and modifier dispatch. Not surfaced directly in tag names.

| Source class | Context | Use |
|---|---|---|
| `CurrentPost` | post | base tag callbacks at `src:''` in post context |
| `RelatedPost` | post | base tag callbacks at `src:ref` in post context |
| `TaxonomyTerm` | term | term_ modifier base; base tag callbacks when `srcTermIn:<tax>` set |
| `TermRelatedPost` | post | term_ modifier at `src:ref` |
| *(external source class)* | post or term | External modifier base, registered via `SourceRegistry::register_source()` |

`SecondRelatedPost` and `PostTermRelatedPost` retained for deprecated wrapper callbacks only — no `src` value in v1.6.0 model.

---

## Deprecated tag name reference

The N×M per-source tag matrices (`post_custom_text`, `related_post_title`, etc.) and the option rename
trackers have moved to [`docs/deprecated-tags-options.md`](deprecated-tags-options.md). All those tag
names are now deprecated wrapper registrations — base tags cover all sources via the `src` option.

---

## Default-enabled logic

In v1.6.0 the per-source×template matrix was removed from the admin settings page. Default-enabled state is now controlled at two levels:

**Modifier group toggles** — `term_` and `try_` each have an on/off toggle in the admin settings page. Disabling a modifier group removes all its tags from the GB editor picker. Both groups default to enabled. Externally registered modifier groups (e.g. `view_`) are not yet surfaced in the toggle UI.

**Deprecated wrapper tags** — settings page exposes two group-level radio sets (Has migration path, No migration path); each tag toggles individually but state is keyed off group selection (Keep / Suppress / Disable). `SettingsPage::is_deprecated_tag_enabled( $tag_name )` reflects the current state.

**Base tags** (`text`, `image`, `content`, `title`, `permalink`, `datetime_single`, `datetime_range`) are always registered with no admin toggle.

---

## Try_ tags

`try_` tags are **entity-agnostic fallback chains**. A single tag tries up to 5 slots in sequence
and returns the first non-empty result. The user configures which traversal each slot uses at the
tag instance level — there is no source prefix in the tag name.

### Per-slot controls

Each slot exposes up to three controls:

1. **Source** — Slot 1 option name: `src`; slots 2+: `N-src`. Slot 1 default: `current` (stripped to `''` at registration; not serialized). Slots 2+ default: "Same as Previous Source" (`''` after strip = inherit prior slot's source). Explicit `current` value reachable slot 2+ as override. `ref` sub-option (relationship field key) shown when src = `ref`.

2. **Field key** (text, image, datetime templates with per-slot key) — Slot 1: must be set for the slot to produce output (when `use` mode requires a key). Slots 2+: hidden when slot's `use` is "Same as Previous Field" (inherits both `use` and `key` from prior slot). Visible only when slot's `use` is set to a key-needing mode (e.g. `key` for text/image/content); typing in the field overrides inherited key.

3. **`use`** (per-slot field-type selector; `try_text`, `try_content`, `try_image`) — Slot 1 option name: `use`; slots 2+: `N-use`. Slot 1 default per template (`key` for text/image, `content` for content). Slots 2+ default: "Same as Previous Field" (`''` after strip = inherit). Explicit mode token (e.g. `title`, `featured`, `excerpt`) reachable slot 2+ as override.

4. **`srcTermIn`** — Combined `bws-term-hop` control. Per-slot, no carry-forward (each slot independently chooses term-hop). Slot 1 option name: `srcTermIn`; slots 2+: `N-srcTermIn`. Empty/unset = disabled; slug = enabled with that taxonomy. See [Secondary, conditional options](#secondary-conditional-options).

**Progressive disclosure:** Slot N+1 is hidden until at least one of slot N's controls is set to a non-default value. Within a slot, sub-controls (e.g. `ref` key, `key`) appear only when their parent control is active.

**Per-slot `use`** is available on templates that support content mode selection per slot (e.g. "try ACF/meta field, fall back to post content").

### Per-slot label scheme

Per-slot controls follow a "Source N: ..." prefix for `srcTermIn` sub-controls and "Field N" / "[Type] Field N" suffix for slot-tied controls. Slot 1 uses bare labels (no slot suffix).

| Control | Slot 1 label | Slot N>1 label |
|---|---|---|
| `src` (source selector) | `Source` | `Source N` |
| `ref` (relationship field) | `Relationship Field` | `Relationship Field N` |
| `srcTermIn` checkbox | `Get from taxonomy term?` | `Source N: Get from taxonomy term?` |
| `srcTermIn` taxonomy combobox | `Taxonomy` | `Source N: Taxonomy` |
| `use` (field-type selector) | `Text Field` / `Content Field` / `Image Field` | `Text Field N` / `Content Field N` / `Image Field N` |
| `key` (meta key) | `Field Meta Key` | `Field N Meta Key` |

### Available try_ tags

| Tag name | Based on template | Per-slot field key? | Per-slot `use`? | Notes |
|---|---|---|---|---|
| `try_content` | `content` | **Yes** | **Yes** | Each slot: Content/Description, Excerpt, or ACF/Custom Field (with per-slot key when `use:key`) |
| `try_title` | `title` | No | No | |
| `try_permalink` | `permalink` | No | No | |
| `try_text` | `text` | **Yes** | **Yes** | Each slot: Title/Name or ACF/Custom Field (with per-slot key when `use:key`) |
| `try_image` | `image` | **Yes** | **Yes** | Each slot: Featured Image or ACF/Custom Field (with per-slot key when `use:key`) |
| `try_datetime_single` | `datetime_single` | No | No | Shared `key` across slots |
| `try_datetime_range` | `datetime_range` | No | No | Shared `startKey`/`endKey` across slots |

---

## Options required per template

Some tag variants require specific options before producing output. Missing required options cause the tag to return empty string (no error).

| Template | Required option(s) | Notes |
|---|---|---|
| Any base tag at `src:ref` | `ref` — reference/relational field key | Required when `src:ref` selected |
| Any base/modifier tag with `srcTermIn:<tax>` | `srcTermIn` — taxonomy slug | Slug encodes both "term hop on" and the taxonomy |
| `text` (`use:key` or unset) | `key` — ACF or meta field key | Default mode reads field at `key` |
| `content` (`use:key`) | `key` — ACF or meta field key | Required when `use:key` |
| `image` (`use:key` or unset) | `key` — meta image field key | Default mode reads field at `key` |
| `datetime_single` | `key` — date/datetime/time field key | |
| `datetime_range` | `startKey` — start field key | `endKey` optional |

Required-option rules for deprecated N×M wrappers (e.g. `related_post_*`, `term_related_post_*`, `custom_text`, `custom_image`, `term_custom_*`) live in [`docs/deprecated-tags-options.md`](deprecated-tags-options.md).

---

## List mode (`limit` + `sep`)

Selected templates support outputting multiple results as a delimited list. `limit` defaults to 1 (single result). When `limit > 1`, results are joined with `sep` (default: `, `).

`limit` applies to the **final traversal step**: terms when `srcTermIn:<tax>` is set; related posts at `src:ref`.

| Template | List mode | What is iterated |
|---|---|---|
| `text` | ✅ | Terms (when `srcTermIn`) or related posts (when `src:ref`) |
| `title` | ✅ | Same as above |
| `content` | ❌ | Long-form prose |
| `permalink` | ❌ | Scalar URL |
| `image` | ❌ | Scalar media |
| `datetime_single` | ✅ | Terms or related posts |
| `datetime_range` | ✅ | Terms or related posts |

Term-modifier tags (`term_text`, `term_title`, etc.) inherit the same list-mode rule applied at their `src:ref` traversal.

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

## Base tag GB types

In the source-agnostic architecture, each template has one GB tag registration. Type names settled (2026-04-14): base tags use `'cross-source'`; try_ tags use `'first-available'`. Both are hyphenated English compounds confirmed valid as GB type strings.

| Template key | GB type | Notes |
|---|---|---|
| `text` | `'cross-source'` | |
| `content` | `'cross-source'` | |
| `title` | `'cross-source'` | Zero options; shares internal pipeline with `text use:title`. |
| `permalink` | `'cross-source'` | Zero options in current scope. |
| `image` | `'cross-source'` | Custom image controls in scope for this plan phase: `as` + `size` (combobox) + `fallback` (media picker) registered via JS filter. No `'media'` type — all controls custom. See `custom-image-controls.md`. |
| `datetime_single` | `'cross-source'` | |
| `datetime_range` | `'cross-source'` | |

The term_ modifier produces additional tags with GB type `'term'`: `term_text`, `term_image`, `term_title`, `term_permalink`. `src` unset = user-selected term (never serialized); `src:'ref'` = term→related post traversal. `term_image` uses GB type `'term'`; `as` and `size` registered as custom options (same pattern as base `image` — `'media'` type not used on any image tag). `as` serialization exception applies to `term_image` as well — default `as:url` is always written to the tag string.

**`term_image use:featured` gating:** `use:featured` only valid on `term_image` when `src:ref` set. Term entities have no featured image; gate hides the option until a post-context traversal is selected.

**try_ modifier** produces `try_text`, `try_image`, etc. with GB type `'first-available'`. Up to 5 slots (s1–s5); slots revealed progressively as earlier slots are configured.

See [§Default serialization strategy](#default-serialization-strategy) for the registration-boundary mechanism that controls which option defaults survive into the saved tag string (and the intentional `as` opt-out for `image` / `term_image`).

---

## Custom editor controls registered

Registered via the `generateblocks.editor.tagSpecificControls` JS filter. Each entry maps a custom option `type` string (referenced in PHP option definitions) to a React control:

| Control type | Renders | Source file | Used by |
|---|---|---|---|
| `bws-media-picker` | `wp.media()` modal; persists media URL | `assets/js/image-tag-controls.js` | `image`, `term_image`, `try_image` fallback |
| `bws-term-hop` | CheckboxControl + ComboboxControl over public taxonomies (via `wp.data` `core`). Reads `pickLabel` / `pickHelp` from PHP option config in addition to `label` / `help` | `assets/js/term-hop-control.js` | `srcTermIn` option on base + modifier tags + per-slot in try_ tags |

GB image-size selection uses GB's native `image-size` support (not a custom control). The earlier `bws-img-size` ComboboxControl was retired mid-1.6.0 cycle once GB's native support was confirmed to handle the reserved `size` key correctly — see CHANGELOG 1.6.0.

---

## Default serialization strategy

Context: GB serializes named option defaults verbatim into the saved tag string (see [`gb-constraints.md` §Option Default Serialization](gb-constraints.md#option-default-serialization)). Empty-string values are dropped. Our goal is **clean, readable saved tags** — defaults should not bloat the tag string unless the default carries semantic value.

**Our rule:** For options where the default carries no information a reader needs, the default must not appear in the serialized tag. For options where the default *does* carry information (e.g. distinguishes a real choice from "unset"), keep it serialized.

**Mechanism — canonical tokens + registration-boundary strip:**

Option definitions declare semantic tokens (`current`, `key`, `content`, etc.) as their first value so the source files read naturally. `bws_strip_default_select_values()` (in `content-helpers.php`) runs at registration time and flips the first option's `value` to `''` for any option we want stripped from the saved tag string. GB drops `''` values from serialization; callbacks then apply `?? '<canonical>'` defaults on read to recover the semantic token.

Result:
- Source code reads `'value' => 'current'` (intent is obvious).
- Saved tags omit the default (clean wire format).
- Callbacks see the canonical token (no `null`/empty-string special-casing).

Canonical defaults applied on read:

| Option | Templates | Canonical default | Why stripped |
|---|---|---|---|
| `src` | all base + modifier + try_ slot 1 | `'current'` | Default is "current entity" — no value to surface |
| `use` | `text`, `image` | `'key'` | Default is ACF/meta field — only `key` value matters |
| `use` | `content` | `'content'` | Default is post content / term description |

**Required for try_ slot 2+:** the slot-2+ "Same as Previous" semantic must be distinguishable from "explicit default". By stripping the slot-1 default to `''` and reserving an explicit `current` token, slot 2+ can use `''` for inherit and `current` for "override back to current".

**Boolean presence-flag convention:** Boolean options designed so unset = false / default behavior, present (as bare key) = true / non-default. Fits GB's boolean serialization (true → bare key, false → dropped) and the no-serialize-defaults rule simultaneously. Examples: `showCurrentYear`, `showMidnight`, `srcTermIn` (checkbox half of the combined control).

### `as` serialization opt-out (`image`, `term_image`, `try_image`)

For image tags, the `as` option default (`url`) is **always serialized** — `{{image as:url|...}}` even when unmodified. Not stripped at registration. Justification: `as` controls the output mode (image src vs. alt text vs. caption vs. ID). Surfacing it in the saved tag makes the return mode immediately visible when copying a tag instance between fields, so a user can change `as:url` → `as:alt` in one edit instead of inspecting the option panel.

All other image options follow the standard rule. `as` is the documented exception.

---

## Template key and option rename reference

Template key renames and option name renames have moved to [`docs/deprecated-tags-options.md`](deprecated-tags-options.md).

---

## Base tag title strings

`title` is displayed in the GB tag picker and used as the last-resort editor fallback when a tag can't resolve and no preview label is available.

| Template key | Base tag title | Term modifier title |
|---|---|---|
| `text` | `'Text Fields'` | Base title + `'(term-based)'` |
| `content` | `'Content/Description'` | Base title + `'(term-based)'` |
| `title` | `'Title/Name'` | Base title + `'(term-based)'` |
| `permalink` | `'Permalink'` | Base title + `'(term-based)'` |
| `image` | `'Image Fields'` | Base title + `'(term-based)'` |
| `datetime_single` | `'Format Date/Time Fields'` | Base title + `'(term-based)'` |
| `datetime_range` | `'Format Date/Time Fields as Range'` | Base title + `'(term-based)'` |

---

## Editor preview label schema

When a base tag can't resolve in the editor (no relationship configured, archive template, wrong post type), the callback returns a structured preview label instead of empty string. Built by `bws_build_preview_label( $options, $template )` in `includes/helpers/content-helpers.php`.

**Scope:** `text`, `content`, `title`, `datetime_single`, `datetime_range`, and their `term_` modifier equivalents. Image tags: only when `as:alt` or `as:caption` — excluded for `as:url` and `as:id` (attribute values; bracket string silently breaks the element). `permalink` excluded entirely (URL context — bracket string breaks `<a href>`).

Not on front end — gated by `$instance->context['bwsEditorPreview']`, injected only by the editor JS filter.

### Marker conventions

| Marker | Meaning |
|---|---|
| `[ ]` | Preview placeholder envelope (always wraps full label) |
| `'X'` | Literal user-supplied identifier (meta key, ref name, taxonomy slug). Straight single quotes |
| `“X”` | Display value (fallback string, formatted datetime). Curly double quotes — attribute-safe for `image as:alt`/`as:caption` slots, no collision with `<img alt="...">` |
| `( )` | Auxiliary append — reserved for `(fallback: …)` |
| `:` | Separates template label from mode/key (`Content: Excerpt`, `Image Alt Text: 'hero'`, `Try Content: 'a', 'b'`); never after a preposition |
| `,` | List item delimiter |
| ` from ` | Field-to-source binding |
| ` like ` | Datetime formatted-value preview |
| `→` | Term-hop traversal arrow |
| `⚠` | Warning prefix (replaces full label) |

### Assembly

```
[{field part} from {context part}]   — both present
[{field part}]                        — field only
[{context part}]                      — context only (e.g. title, permalink)
[⚠ {warning}]                        — misconfigured: replaces entire label
```

Fallback appended when set: ` (fallback: “{value}”)`.

### Context part

Space-joined segments. The `→` separator precedes the term-hop segment only.

| Condition | Segment |
|---|---|
| Modifier tag (e.g. `term_`) | Modifier `label` value (e.g. `Term`) |
| `src:ref` + `ref:X` set | `Ref 'X'` |
| `src:ref` + `ref` unset | *(triggers warning — see below)* |
| `srcTermIn:X` set | `→ {taxonomy singular label} Term` (live `get_taxonomy()->labels->singular_name`; fallback: `{tax} Term`) |
| `srcTermIn` set with empty value (legacy `srcTerm` without `tax`) | *(triggers warning — see below)* |
| No modifier, `src` unset, no term-hop | *(omit — no `from` clause)* |

### Field part

Template-specific. Missing required input triggers a warning instead of the field part.

**Convention** (consistent across base + try_ previews):
- Template label leads when the template has multiple modes that need disambiguation (`Content`, `Image Alt Text`, `Image Caption`).
- Mode-value or quoted user identifier follows after a colon (`: Excerpt`, `: 'body_text'`, `: Featured`).
- Default-mode collapse: when the only configured mode is the template default, drop the colon segment (e.g. `[Content]` for `use:content`).
- `text` has no template label — bare key (`'X'`) or `Title` is unambiguous on its own.
- Mode-value keywords capitalized: `Title`, `Excerpt`, `Content`, `Featured`. User identifiers wrapped in straight single quotes.

| Template | Condition | Field part |
|---|---|---|
| `text` | `key:X` set | `'X'` |
| `text` | `use:title` | `Title` |
| `text` | `key` unset + `use` unset | *(missing — triggers warning)* |
| `content` | `use` unset (default) | `Content` |
| `content` | `use:excerpt` | `Content: Excerpt` |
| `content` | `use:key` + `key:X` | `Content: 'X'` |
| `content` | `use:key` + `key` unset | *(missing — triggers warning)* |
| `image` (`as:alt`) | `use:featured` | `Image Alt Text: Featured` |
| `image` (`as:alt`) | `key:X` set | `Image Alt Text: 'X'` |
| `image` (`as:caption`) | `use:featured` | `Image Caption: Featured` |
| `image` (`as:caption`) | `key:X` set | `Image Caption: 'X'` |
| `title` | — | `Title` (always) |
| `datetime_` | — | *(see datetime section below)* |

### Warnings

Warnings replace the **entire** label. Collect all missing required items; join with `, `; last two items use ` or `. Fallback append still applies after the warning.

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
[{prefix} like “{formatted value}” from {context part}]
[{prefix} like “{formatted value}”]   — no context
```

### Examples

| Tag | Preview label |
|---|---|
| `{{text key:body_text}}` | `['body_text']` |
| `{{text src:ref\|ref:rel_post\|key:body_text}}` | `['body_text' from Ref 'rel_post']` |
| `{{text use:title}}` | `[Title]` |
| `{{text src:ref\|ref:rel_post\|use:title}}` | `[Title from Ref 'rel_post']` |
| `{{text srcTermIn:category\|key:body_text}}` | `['body_text' from Category Term]` |
| `{{text src:ref\|ref:rel_post\|srcTermIn:category\|key:body_text}}` | `['body_text' from Ref 'rel_post' → Category Term]` |
| `{{text}}` | `[⚠ No meta key set]` |
| `{{text srcTermIn\|key:body_text}}` | `[⚠ No taxonomy set]` |
| `{{text src:ref\|srcTermIn\|key:body_text}}` | `[⚠ No ref key or taxonomy set]` |
| `{{term_text key:bio}}` | `['bio' from Term]` |
| `{{term_text src:ref\|ref:rel_post\|key:bio}}` | `['bio' from Term Ref 'rel_post']` |
| `{{title src:ref\|ref:rel_post}}` | `[Title from Ref 'rel_post']` |
| `{{content}}` | `[Content]` |
| `{{content use:excerpt}}` | `[Content: Excerpt]` |
| `{{content use:key\|key:body_text}}` | `[Content: 'body_text']` |
| `{{content use:key\|key:body_text\|src:ref\|ref:rel_post}}` | `[Content: 'body_text' from Ref 'rel_post']` |
| `{{image as:alt\|key:hero}}` | `[Image Alt Text: 'hero']` |
| `{{image as:caption\|use:featured}}` | `[Image Caption: Featured]` |
| `{{image as:url\|key:hero}}` | *(no label — excluded)* |
| `{{datetime_single as:date}}` | `[Date like “April 24, 2026”]` |
| `{{datetime_single as:time\|src:ref\|ref:event_date}}` | `[Time like “2:20 PM” from Ref 'event_date']` |
| `{{datetime_range as:date\|src:ref\|ref:event}}` | `[Date Range like “April 24 – April 25” from Ref 'event']` |
| `{{text src:ref\|ref:rel_post\|key:body_text\|fallback:Untitled}}` | `['body_text' from Ref 'rel_post' (fallback: “Untitled”)]` |

### try_ tag previews

`bws_build_try_preview_label()` walks slots 1-5, applies carry-forward (slot ≥2 empty fields inherit prior slot's canonical value), then detects uniformity across two dimensions (field-part, source-part) and renders one of four shapes.

**Conventions** (consistent with base preview labels):
- Template-name labels: `text` has no label (default). `content`/`image` always include label. `image` appends ` Alt Text` / ` Caption` per `as`. `title`/`permalink` use bare template name.
- Mode-value keywords capitalized: `Title`, `Excerpt`, `Content`, `Featured`.
- User-supplied identifiers wrapped in straight single quotes: `'meta_key'`, `'rel_post'`.
- `from` precedes source segments. `Current` rendered explicitly only when source list contains a varying mix that needs the anchor.
- Datetime templates render base shape (`<Date|Time|Date-Time> like "X"`) then optional source list.
- Single slot at template default for `content`/`image` collapses to bare `[Try Content]` / `[Try Image Alt Text]`.
- Image excluded for `as:url` / `as:id` (bracket string would break HTML attribute). Permalink excluded entirely (URL context).

| Slot pattern | Preview shape (text) | Preview shape (content/image) |
|---|---|---|
| Single slot, template default (no override) | n/a (text needs key) | `[Try Content]` (content `use:content` default) |
| Uniform field, uniform source | `[Try 'body_text']` | `[Try Content: 'body_text']` |
| Uniform field, varying sources | `[Try 'body_text' from Current, Ref 'rel_post']` | `[Try Content: 'body_text' from Current, Ref 'rel_post']` |
| Uniform source, varying fields | `[Try 'a', 'b', 'c']` | `[Try Content: Excerpt, 'body_text', 'summary']` |
| Mixed (both vary) | `[Try 'a' from Current, Title from Ref 'rel']` | `[Try Image Alt Text: 'hero', Featured from Ref 'rel']` |
| Datetime varying sources | n/a | `[Try Date like "April 24, 2026" from Current, Ref 'event_date']` |
| `try_title` (always) | n/a | `[Try Title]` (with optional ` from <source list>`) |
| All slots empty | `[⚠ Try: no slots configured]` | same |
| Per-slot warnings | `[⚠ Try: slot 1 no key, slot 3 no ref]` | same |
| Image `as:url` / `as:id` | *(no label — excluded)* | — |
| `try_permalink` | *(no label — excluded)* | — |

Trailing `(fallback: "X")` appended whenever `fallback` option is set, matching base preview behavior.

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
| In Reference/Relational Field | `ref` | `ref` | `ref` | `Ref 'X'` where X = `ref` field value | Triggers `ref` sub-option |
| Parent | `parent` | `parent` | `parent` | — | Future |
| Ancestor | `ancestor` | `ancestor` | `ancestor` | — | Future |
| Child(ren) | `child` | `child` | `child` | — | Future |

Note: For context-modifier tags, the modifier label is prepended as a context segment. Examples: `[Title from Term]` for `{{term_title}}`, `[Content from Term Ref 'rel_post']` for `{{term_content src:ref|ref:rel_post}}`. See [§Editor preview label schema](#editor-preview-label-schema) for assembly rules.

### Secondary, conditional options

| Option name | Option label | Help text | Shown when | Notes |
|---|---|---|---|---|
| `ref` | Relationship Field | ACF relationship or post object field key. | `src` = `ref` | ACF relationship/relational field key for the traversal hop |
| `srcTermIn` | Get from taxonomy term? | Field is in a taxonomy term on this source. | Always; hidden for `term_` modifier tags (entity already a term) at `src:current`; shown at `src:ref` | Combined `bws-term-hop` control (CheckboxControl + ComboboxControl). Empty/unset = disabled; slug = enabled with that taxonomy. Replaced prior `srcTerm` + `tax` pair (v1.6.0). |
| `limit` | Result Limit | This source type may return multiple results. By default, only the first result is used, but you may enter either a fixed limit, or “0” for no limit. | `src` = `ref` or `child` *(future)*, or `srcTermIn` set | `text`, `title`, `datetime_` only. Placeholder `1`; not serialized when unset. |
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

Option order as proposed after all approved renames. `[source options]` is a placeholder for the source selector block and its conditional sub-options (`ref`, `srcTermIn`; plus `limit`/`sep` for applicable templates). Template-specific options follow in the order listed below.

**Three-group structure (applies to all templates):**
- **Group 1 — global formatting:** `as`, format options, separators. Not per-slot; applies to the assembled result.
- **Group 2 — per-slot:** source selector → source secondary options (`ref`, `srcTermIn`, `limit`, `sep`) → field options (`use`, `key`). Repeated for each try_ slot.
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
| 4 | | `use` | `key` (unset default in single-slot tags); `featured` | `featured` disabled for term-context entities unless `src` = `ref`. |
| 5 | | `key` | shown when `use` unset [in single-slot tags] or `use:key` |
| 6 | | `[fallback option]` | media picker — see `custom-image-controls.md` |

See [§Default serialization strategy](#default-serialization-strategy) for the `as` opt-out from the strip-defaults rule.

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

Living reference. Update immediately when any of the following change:

- A new `src` value, modifier prefix, or source class is added/removed
- A new base or modifier template is added/removed
- A default-enabled status changes
- A required option is added/removed/renamed
- List mode support changes for a template
- A try_ tag is added or its slot behavior changes
- An option rename moves from "Under consideration" to "Approved" or "Implemented"
- A custom editor control is added/retired
- The default-strip strategy changes (canonical defaults, opt-outs)

**When adding a new `src` value:** add a row to §Sources `src` option values; document the traversal in §Source classes if a new resolver class is needed; update §Source options + §Secondary, conditional options labels; add a row in §Required options if it brings new required sub-options.

**When adding a new modifier prefix:** add a row to §Modifier prefixes; update §Base tag GB types if a new GB type string is introduced; document the registration call in [`docs/plugin-integration.md`](plugin-integration.md).

**When adding a new template:** add a row to §Base tag GB types and §Base tag title strings; note required options + list-mode support; if `supports_try`, add a row to §Available try_ tags; document option render order in §Option render order.

**Deprecated wrappers:** never edit this doc for N×M deprecated wrappers — those go in [`docs/deprecated-tags-options.md`](deprecated-tags-options.md).

For ownership boundaries against other docs, see [`CLAUDE.md` §Documentation ownership](../CLAUDE.md#documentation-ownership).
