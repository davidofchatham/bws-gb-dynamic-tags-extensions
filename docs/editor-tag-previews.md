# Editor Tag Configuration Previews

**Authoritative** for the editor-time preview text shown in place of an unresolved dynamic tag while the user is configuring it. This is NOT a front-end label and NOT the rendered output — it is the placeholder string GenerateBlocks shows in the editor when a tag can't yet resolve (no relationship configured, archive template, wrong post type). Schemas for the tags themselves live in [`tag-reference.md`](tag-reference.md).

When a base tag can't resolve in the editor, the callback returns this structured preview string instead of an empty string. Built by `bws_build_preview_label( $options, $template )` in `includes/helpers/preview-helpers.php`. (The function name retains the historical `_label` suffix; the text it builds is the configuration-preview described here.)

**Scope:** `text`, `content`, `title`, `email`, `datetime_single`, `datetime_range`, and their `term_` modifier equivalents. Image tags: only when `as:alt` or `as:caption` — excluded for `as:url` and `as:id` (attribute values; bracket string silently breaks the element). `permalink` excluded entirely (URL context — bracket string breaks `<a href>`).

Not on front end — gated by `$instance->context['bwsEditorPreview']`, injected only by the editor JS filter.

## Marker conventions

| Marker | Meaning |
|---|---|
| `[ ]` | Preview placeholder envelope (always wraps the full preview) |
| `'X'` | Literal user-supplied identifier (meta key, ref name, taxonomy slug). Straight single quotes |
| `“X”` | Display value (fallback string, formatted datetime). Curly double quotes — attribute-safe for `image as:alt`/`as:caption` slots, no collision with `<img alt="...">` |
| `( )` | Auxiliary append — reserved for `(fallback: …)` |
| `:` | Separates template label from mode/key (`Content: Excerpt`, `Image Alt Text: 'hero'`, `Try Content: 'a', 'b'`); never after a preposition |
| `,` | List item delimiter |
| ` from ` | Field-to-source binding |
| ` like ` | Datetime formatted-value preview |
| `→` | Term-hop traversal arrow |
| `⚠` | Warning prefix (replaces the full preview) |

## Assembly

```
[{field part} from {context part}]   — both present
[{field part}]                        — field only
[{context part}]                      — context only (e.g. title, permalink)
[⚠ {warning}]                        — misconfigured: replaces entire preview
```

Fallback appended when set: ` (fallback: “{value}”)`.

## Context part

Space-joined segments. The `→` separator precedes the term-hop segment only.

| Condition | Segment |
|---|---|
| Modifier tag (e.g. `term_`) | Modifier `label` value (e.g. `Term`) |
| `src:site` (base tag only) | `Site` (yields `… from Site`; never combines with ref/term-hop — site has no entity. On a modifier it is the invalid-combo warning instead) |
| `src:ref` + `ref:X` set | `Ref 'X'` |
| `src:ref` + `ref` unset | *(triggers warning — see below)* |
| `srcTermIn:X` set | `→ {taxonomy singular label} Term` (live `get_taxonomy()->labels->singular_name`; fallback: `{tax} Term`) |
| `srcTermIn` set with empty value (legacy `srcTerm` without `tax`) | *(triggers warning — see below)* |
| No modifier, `src` unset, no term-hop | *(omit — no `from` clause)* |

## Field part

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
| `email` | `key:X` set | `Email: 'X'` |
| `email` | `key` unset | *(missing — triggers warning: `field key`)* |
| `datetime_` | — | *(see datetime section below)* |

## Warnings

Warnings replace the **entire** preview. Collect all missing required items; join with `, `; last two items use ` or `. Fallback append still applies after the warning.

| Missing items | Warning |
|---|---|
| `ref` only | `⚠ No ref key set` |
| `key` only | `⚠ No meta key set` |
| `tax` only | `⚠ No taxonomy set` |
| `field key` only (`email`) | `⚠ No field key set` |
| `ref` + `key` | `⚠ No ref key or meta key set` |
| `ref` + `tax` | `⚠ No ref key or taxonomy set` |
| `tax` + `key` | `⚠ No taxonomy or meta key set` |
| `ref` + `tax` + `key` | `⚠ No ref key, taxonomy, or meta key set` |

### Invalid-combo warning (`src:site` on a modifier tag)

Distinct from the missing-input warnings: a hand-typed `src:site` on a rooting modifier (`term_*`, `view_*`) is **invalid, not missing**. The `src` dropdown filters `site` out ([tag-reference §Qualifying test](tag-reference.md#qualifying-test-for-new-use-values)), but a hand-typed value slips the UI. A site read is entity-blind, so the runtime resolves **empty** — the preview warns to match, instead of showing a normal label. Checked before the missing-input pass; fallback still appends. (See [#37](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/37).)

| Condition | Warning |
|---|---|
| `src:site` on any modifier tag (`{{modifierLabel}}` = `Term`, …) | `⚠ Site source not valid on {ModifierLabel} tag — use the base tag` |

## Datetime preview

Datetime tags compute a live preview from the current time rather than a static label. The `as` option controls label prefix and range-end offset. The preview value is formatted using the same formatter and options (`format`, `timeSep`, `rangeSep`, etc.) the tag uses at render time.

| `as` | Single prefix | Range prefix | Range end offset |
|---|---|---|---|
| unset | `Date-Time` | `Date-Time Range` | +1 hour |
| `date` | `Date` | `Date Range` | +1 day |
| `time` | `Time` | `Time Range` | +1 hour |

```
[{prefix} like “{formatted value}” from {context part}]
[{prefix} like “{formatted value}”]   — no context
```

## Examples

| Tag | Preview |
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
| `{{term_text src:site\|key:blogdescription}}` | `[⚠ Site source not valid on Term tag — use the base tag]` |
| `{{title src:ref\|ref:rel_post}}` | `[Title from Ref 'rel_post']` |
| `{{content}}` | `[Content]` |
| `{{content use:excerpt}}` | `[Content: Excerpt]` |
| `{{content use:key\|key:body_text}}` | `[Content: 'body_text']` |
| `{{content use:key\|key:body_text\|src:ref\|ref:rel_post}}` | `[Content: 'body_text' from Ref 'rel_post']` |
| `{{image as:alt\|key:hero}}` | `[Image Alt Text: 'hero']` |
| `{{image as:caption\|use:featured}}` | `[Image Caption: Featured]` |
| `{{image as:url\|key:hero}}` | *(no preview — excluded)* |
| `{{email key:contact_email}}` | `[Email: 'contact_email']` |
| `{{email src:site\|key:org_email}}` | `[Email: 'org_email' from Site]` |
| `{{email}}` | `[⚠ No field key set]` |
| `{{datetime_single as:date}}` | `[Date like “April 24, 2026”]` |
| `{{datetime_single as:time\|src:ref\|ref:event_date}}` | `[Time like “2:20 PM” from Ref 'event_date']` |
| `{{datetime_range as:date\|src:ref\|ref:event}}` | `[Date Range like “April 24 – April 25” from Ref 'event']` |
| `{{text src:ref\|ref:rel_post\|key:body_text\|fallback:Untitled}}` | `['body_text' from Ref 'rel_post' (fallback: “Untitled”)]` |

## try_ tag previews

`bws_build_try_preview_label()` walks slots 1-5, applies carry-forward (slot ≥2 empty fields inherit prior slot's canonical value), then detects uniformity across two dimensions (field-part, source-part) and renders one of four shapes.

**Conventions** (consistent with base previews):
- Template-name labels: `text` has no label (default). `content`/`image`/`email`/`phone` always include label. `image` appends ` Alt Text` / ` Caption` per `as`. `title`/`permalink` use bare template name. `email`/`phone` use bare `Email` / `Phone`.
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
| `try_email` / `try_phone` configured | n/a | `[Try Email: 'contact_email']` / `[Try Phone: 'tel']` (key-required, no `use` enum) |
| `try_email` / `try_phone` empty key | n/a | `[⚠ Try: slot 1 no key]` (always needs a key — no no-key values) |
| All slots empty | `[⚠ Try: no slots configured]` | same |
| Per-slot warnings | `[⚠ Try: slot 1 no key, slot 3 no ref]` | same |
| Image `as:url` / `as:id` | *(no preview — excluded)* | — |
| `try_permalink` | *(no preview — excluded)* | — |

Trailing `(fallback: "X")` appended whenever `fallback` option is set, matching base preview behavior.

`try_email` / `try_phone` ([#32](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/32), 1.11.0) are text-like with `$needs_key = true` and no no-key values (single key-mode, no `use` enum) — so an empty-key slot always warns `⚠ slot N no key`, and a configured slot renders `Email: 'key'` / `Phone: 'key'`. This is the [#24](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/24)-correct shape (warn on a genuinely unconfigured slot, unlike `content` whose default `use` needs no key).

## Tests

Non-datetime label assembly is pinned by a standalone harness — **no WordPress, no PHPUnit**:

```
php tools/test/preview-label-test.php
```

**Run it after any change to `preview-helpers.php` or to a label shape in this doc.** It asserts `bws_build_preview_label`, `bws_build_try_preview_label`, and the four sub-builders against the marker/assembly rules above. Datetime templates are excluded (live clock + `wp_date` → non-deterministic).

Behaviors the harness locks in (correct-by-design, easy to regress):

- **`→` hop arrow is positional** — emitted only when the term-hop segment *follows* another (modifier label or `Ref 'x'`). Standalone current-post→term drops it: `['sku' from Event Category Term]`, not `… from → …`.
- **Slot ≥2 key-only override is discarded** — an empty `use` on slot N≥2 wipes that slot's `key` (the `use:same` UI hides the key field). A key override only registers when its `N-use` is also sent.
- **`text` try "no slots configured" is unreachable** — slot 1 is always default-filled, so a misconfigured slot-1 trips the missing-key warning first.
