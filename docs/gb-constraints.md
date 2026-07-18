# GenerateBlocks Dynamic Tag Constraints

Catalogs GB editor/runtime constraints discovered while building this plugin.
Several discoveries here invalidated or revised approved option renames —
see [`deprecated-tags-options.md`](deprecated-tags-options.md) for the active
rename tracker. The "Already-renamed keys to avoid GB conflicts" section below
cross-references the constraints to the rename decisions they forced.

**Upstream developer doc:** [Dynamic Tag Registration (learn.generatepress.com)](https://learn.generatepress.com/developer-doc/dynamic-tag-registration/) — official registration API reference. Treat as canonical for `GenerateBlocks_Register_Dynamic_Tag` params, built-in filters, and tag parameter syntax. This file records *constraints and behaviors* not in the upstream doc; the [§Upstream-documented affordances](#upstream-documented-affordances) section below summarizes facts pulled from upstream that aren't otherwise referenced in our code.

## Tag Name Prefix Rule
GB does not allow one dynamic tag to start with the same string as another existing tag.

**Example conflicts:**
- `post_meta` + `post_meta_related` → CONFLICT (second starts with first)
- `related_post_meta` + `related_post_meta_image` → CONFLICT
- `current_post_acf_date` + `current_post_acf_date_range` → CONFLICT

**Solution pattern:** Use suffixes that differentiate immediately:
- `current_post_acf_date_single` + `current_post_acf_date_range` → OK (neither is prefix of other)
- `related_post_meta_field` + `related_post_meta_image` → OK

## Custom Tag Types
- Single-word names confirmed working; **hyphenated names also confirmed usable** in the latest GB version (e.g. "select-a-source") — 2026-04-10
- Can define custom types beyond GB's built-in set
- Type determines available features:
  - `media` → built-in media library selector in editor. **Not used on any image tag in this plugin** (v1.6.0+): `type:'media'` blocks the source selector. Image tags use `'cross-source'` (or `'term'` for term-modifier image) with a custom `bws-media-picker` control for fallback selection.
  - `post` → standard post context
  - `term` → term context
  - `author` → author context

## GB Built-in Tags (for conflict checking)
post_title, post_excerpt, post_permalink, post_date, featured_image, post_meta, author_meta, comments_count, comments_url, author_archives_url, author_avatar_url, term_list, media, archive_title, archive_description, option, site_title, site_tagline, site_logo_url, site_url, current_year, term_meta, user_meta, loop_index, loop_item

## Supports Array Options
link, source, meta, date, image-size, taxonomy, comments, properties, instant-pagination

**`image-size` supports key:** activates GB's native image size control. GB destructures the reserved `size` key from `extraTagParams` before custom controls receive it, so a custom image-size control cannot read its own stored `size:` value reliably — use GB's native `image-size` support instead. Image tags in this plugin (`image`, `term_image`, `view_image`, `try_image`) declare `image-size` in `supports`; the native control handles parse/serialize and strips the `'full'` default automatically.

## Reserved Option Keys (extracted from extraTagParams)

GB destructures these keys out of `extraParams` in `DynamicTagSelect.jsx:385-395` BEFORE spreading into `extraTagParams`. Custom controls never receive their values; they round-trip only when GB's own re-emit logic fires.

**Reserved:** `id`, `source`, `key`, `link`, `required`, `tax`, `size`, `dateFormat`

Re-emit conditions:
- `tax` → only re-serialized when `'term' === dynamicTagType || tagSupportsTaxonomy` (line 553). Cross-source base tags (e.g. `text`, `image` with `gb_type:'cross-source'`) meet neither → `tax` is silently dropped on modal reopen.
- `tagSupportsTaxonomy` requires `'taxonomy'` in the tag's `supports` array.
- `source` → tag-type-specific re-emit; safer to use a non-reserved name (`src`).

**Workaround pattern — "two controls, one key":** present multiple UI controls (checkbox + selector) but persist a single non-reserved key whose presence/value encodes both signals. See `srcTermIn` (slug = enabled + slug, empty = disabled) implemented via `bws-term-hop` custom control type.

**Already-renamed keys to avoid GB conflicts:**
- `source` → `src` (registered as option migration)
- `tax` → `srcTermIn` on cross-source base tags (replaces `srcTerm` + `tax` pair)

## Option Default Serialization

GB editor serializes named default values into the stored tag string even when the user never changed them. A PHP option definition like `'default' => 'none'` results in `{{tag key:none}}` on save, even for untouched options — creating unwieldy tags. Empty-string defaults (`''`) are dropped from the serialized tag.

GB's `parse_options()` only reads keys literally present in the tag string. Options absent from the string are absent from `$options` in the callback.

**Option values are NOT trimmed.** `parse_options()` splits each `key:value` pair with `explode( ':', $pair, 2 )` and stores the raw remainder — no `trim()`. So surrounding whitespace in a value survives to the callback: `sep: ` yields `' '` (a single space), `sep: / ` yields `' / '`. (Only the whole options blob's leading space after the tag name is `ltrim`'d, once, in `replace_tags()` — never per-value.) A value's trailing space before the closing `}}` is captured too (the option regex `[^}]+` stops at `}`, keeping the space). Load-bearing for any whitespace-significant option — `{{join}}`'s `sep`/`format`, datetime `format`.

**Boolean serialization:**
- `true` serializes as a bare key only (e.g. `showCurrentYear`, NOT `showCurrentYear:true`).
- `false` = option dropped entirely — never appears in the tag string.

See [`tag-reference.md` §Default serialization strategy](tag-reference.md#default-serialization-strategy) for how this plugin works with the constraint (canonical-token first values, registration-boundary strip, intentional `as` opt-out).

## Option Serialization Order

The serialized tag string orders options by **`extraTagParams` object insertion order**, not by PHP option-definition order and not by editor render (display) order. The serializer (`DynamicTagSelect.jsx:557-571`) is a plain `Object.entries( extraTagParams ).forEach( … options.push( ... ) )`, and JS preserves string-key insertion order.

Built-in options (`source`, `id`, `key`, `link`, `size`, `dateFormat`, `required:false`, `tax`) are pushed **first**, in the fixed code order at `DynamicTagSelect.jsx:514-555`, before any `extraTagParams` entries. So custom (non-reserved) options always serialize after the built-ins, among themselves in insertion order.

**When a key gets its insertion slot:**

- **Default values** seed `extraTagParams` at tag-select time (`updateDynamicTag`, `DynamicTagSelect.jsx:348-361`) — every option with a non-empty `default` is inserted up front, in PHP option-definition iteration order. So defaulted keys take a stable, definition-ordered slot.
- **Non-default options** get their slot on the **author's first edit** of that control — `handleChange` does `newState[ key ] = newValue` (`:46`). The key did not exist before, so it is appended at the end.
- **Re-editing an existing key** (`{ ...prev, key: val }`) updates the value but **keeps the original slot** — spread preserves position. So reordering only ever happens when a key is first introduced.

**Consequences:**

- For a tag where the field controls have no defaults, an author who fills (e.g.) a time field before a date field produces `timeKey:…|dateKey:…` — reversed from render order. Render is unaffected (PHP `parse_options()` reads by name), but the stored string order is author-action-dependent.
- A custom control **can force canonical order** by rebuilding the whole `extraTagParams` object (re-inserting keys in the desired order) inside its `setState`, instead of spreading-and-appending. The stock `TextControl` path cannot — it only spreads-and-appends.
- **Folding multiple fields into one composite-owned key** (e.g. `start:date,time`) makes intra-field order structural — the control builds the comma string itself, so GB never orders those sub-values. This is the only way to *guarantee* a fixed order between two values without owning every control that might touch the object. (Comma is opaque to GB's `parseTag()` — see §Tag string escape syntax.)

## Upstream-documented affordances

Pulled from the [upstream registration doc](https://learn.generatepress.com/developer-doc/dynamic-tag-registration/). Facts here are GB-owned API surface — if upstream changes, that doc wins. Listed here as known extension points; most are not exercised in this plugin (the exception is `visibility`, first used by `{{email}}` in 1.9.0 — see below).

### Registration params rarely / not yet used

- **`visibility`** — controls when a tag appears in the editor selector. Accepts `true` (default), `false`, `[ 'context' => [...] ]`, or `[ 'attributes' => [ [ 'name' => ..., 'value' => ..., 'compare' => ... ] ] ]`. Compare operators: `===`, `!==`, `IN`, `NOT_IN`. **Distinct from our JS `show_if` layer** (which gates *option* visibility inside an open modal). `visibility` gates the tag itself in the selector list. Prefer native `visibility` over JS when the gate depends on block attributes (`tagName`, etc.) rather than sibling option values. **First plugin use: `{{email}}` (1.9.0)** registers `tagName NOT_IN ['a','button','img','picture']`, mirroring GB core's own `term_list` registration — its default-ON `mailto:` wrap is invalid inside anchor/button (nested interactive) or img/picture (void/replaced). See `tag-reference.md` §Email tag.
- **`description`** — help text shown below tag in selector UI. None of our tags set this; consider adding to clarify ambiguous tags (e.g. `term_*` selector).

#### `visibility` blind spot — the media block (empty `tagName`)

`visibility` with `tagName NOT_IN [...]` is a **value-compare on a block attribute**, so it only matches blocks that serialize a real `tagName`. The **GB media block does not**: `dist/blocks/media/block.json` declares `tagName` as `{ "type":"string", "default":"", "enum":["img"] }` — default empty, with no editor control to set it (the enum's single value is implicit). The block still renders `<img>` at runtime.

Consequence: `'' NOT_IN ['a','button','img','picture']` evaluates **true**, so a tag gated to hide on `img` is **still offered on the media block**. The element block serializes its chosen tag (`tagName:"button"` etc.), so the gate works there — media is the sole hole. (GB core's own `term_list` registration has the identical hole; it's simply never exercised on a media block.)

Worse, on a media block GB injects the tag's replacement into the `<img src>` attribute (`class-dynamic-tags.php` — `'generateblocks/media' === $block_name`), so any tag emitting markup (e.g. the default-on `<a>` wrap of `{{phone}}` / `{{email}}`) corrupts `src`.

**Plugin response:** a **runtime backstop** keyed on block *name* (the only reliable media signal — same key GB uses), not `tagName`. `bws_tag_blocked_on_media_block()` ([`link-helpers.php`](../includes/helpers/link-helpers.php)) returns true for `generateblocks/media`; the `{{phone}}` and `{{email}}` callbacks call it and return `''` early. The native `visibility` gate stays as the cosmetic editor-list filter for the element-block cases it *can* see; correctness is guaranteed at render regardless. Editor-picker UX (hiding the tag on a media block in the selector) is tracked separately — native `visibility` cannot express a blockName condition, so it would need an undocumented selector hook.

### Built-in tag parameters

Available on every registered tag without declaring `supports`:

- `id:N` — explicit entity ID.
- `required:false` — when tag resolves empty, **don't render the containing block** (otherwise GB renders block with empty tag output). Useful for conditional layouts.
- `link:post|term|author|comments` — wraps output in link (requires `'link'` in `supports`).
- `trunc:N`, `trunc:N,words` — truncate by chars or words.
- `case:lower|upper|title` — case transform.
- `trim`, `trim:left`, `trim:right` — whitespace strip.
- `wpautop` — paragraph wrap.
- `replace:"old","new"` — string replace.

### Tag string escape syntax

GB's PHP parser (`class-register-dynamic-tag.php` `parse_options()`) recognizes two escapes inside an option value:

- `\|` — literal pipe (`|` is the option-pair separator).
- `\:` — literal colon (`:` separates key from value; only the first colon in a pair is the separator, but earlier versions of this doc reported no escape — that was wrong).

Both are unescaped before the key/value split, so on the render side `format:l\:i` arrives as `format` → `l:i`.

**Asymmetry with the JS parser:** GB's editor-side `parseTag()` (in their `src/dynamic-tags/utils.js`) splits on unescaped `|` and `:` but does **not** unescape the captured value, and GB's tag-string serializer writes `${key}:${value}` raw with no escaping. So any colon or pipe in a custom control's stored state must be **pre-escaped on save and unescaped on display by the control itself** for the round-trip to be clean. PHP render is fine either way.

### Tag-string-unsafe values

Option values containing raw `:` or `|` cannot survive a tag-string round-trip unless the control escapes them — GB's JS `parseTag()` will truncate the value at the first unescaped colon. Affected:

- **Full URLs** (`https://...`) — colon after scheme + slashes in path corrupt the parse on reopen. Symptom: tag re-opens with truncated/wrong options (e.g. `fallback:https` only).
- **Date/time literals with colons** (`12:30:00`) — same failure mode.
- **Free-text user input** that may contain `:` or `|`.

**Workarounds (preference order):**
1. **Store an ID** referencing the value (attachment ID, term ID, post ID). Resolve at render. Used by `bws-media-picker` for image fallback (v1.7.3+). Use this when the value is a stable referenceable entity.
2. **Custom control with escape/unescape on save/display.** Control stores the escaped form (`\:` / `\|`) in option state; UI shows the unescaped form to the user. PHP `parse_options()` already unescapes both sequences before render. Used by `bws-format-input` for the `format` option on datetime tags (v1.7.4+). Use this when the value is free-text user input.
3. **Encode** (base64 / urlencode). Survives any chars but produces user-visible garbage in the tag string. Last resort.

Avoid storing raw URLs or colon-bearing free-text in default-text controls.

**Closing brace `}` — kills the whole tag match (harder failure than `:`/`|`).** GB's render-side
matcher (`class-register-dynamic-tag.php` `find_matches()`) captures a tag's options as `[^}]+`:

```php
$pattern = '/\{{(' . implode( '|', array_keys( $availableTags ) ) . ')(\s+[^}]+)?}}/';
```

A `}` anywhere inside the options doesn't truncate a value — the tag never matches at all and
renders as its raw literal string. There is NO escape sequence for it (`parse_options()` handles
only `\|`/`\:`). Verified against 2.2.1 and 2.3.0-beta.2 (same pattern). Consequence: option
values must be designed brace-free — e.g. `{{join}}` template mode uses `%1`…`%8` positional
tokens on the wire instead of `{1}`…`{8}` (translated internally; response documented in
[`tag-reference.md` §join](tag-reference.md#join)). Also the reason a nested-braces tag-in-slot
syntax can never ride the wire (`{{join 1:{{text …}}}}` is unparseable by construction).

### Filter hooks

| Hook | Signature | This plugin's use |
|---|---|---|
| `generateblocks_dynamic_tag_replacement` | `($replacement, $context)` — `$context` keys: `tag`, `full_tag`, `content`, `block`, `instance`, `options`, `supports` | [`includes/hooks.php:30`](../includes/hooks.php) — falsy-replacement block-kill |
| `generateblocks_before_dynamic_tag_replace` | `($content, $args)` — pre-replace HTML hook | not used |
| `generateblocks_dynamic_tag_id` | `($id, $options, $instance)` — override resolved entity ID. Applied only in `GenerateBlocks_Dynamic_Tags::get_id()`, which our tags never reach: GB calls it from its own built-in callbacks and from `with_link()`, and `with_link()` early-returns unless `$options['link']` is set. We never set `link` (we link-wrap via our own `linkTo`/`linkKey` — see [`link-helpers.php`](../includes/helpers/link-helpers.php)), so this hook cannot fire for a BWS tag. | not used (removed in 1.14.1; a filter here would silently defeat §V1 source resolution) |
| `generateblocks_dynamic_tag_output` | `($output, $options, $raw_output)` — final output transform | preserved as third-party extension point by [`bws_safe_content_output()`](../includes/helpers/content-helpers.php) (see [`post-content-processing-reference.md`](post-content-processing-reference.md#L211)) |
| `generateblocks.dynamicTags.sourceOptions` (JS) | `(options, context)` — add entries to source dropdown | not used; potential future hook for custom source contributions from third-party plugins |

### Type values

Upstream lists `'post'`, `'author'`, `'user'`, `'term'`, `'media'`. We additionally use the **custom values** `'cross-source'` and `'first-available'` (not in upstream docs) — see [§Custom Tag Types](#custom-tag-types) and [`tag-reference.md`](tag-reference.md#modifier-prefixes).
