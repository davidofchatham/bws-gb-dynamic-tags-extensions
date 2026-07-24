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

### Reserved keys are destructured into GB-private state and re-serialized even when unsupported

**Dropping a `supports` value stops GB RENDERING that control, but does NOT stop GB owning and
re-emitting its reserved key.** Verified GB 2.2.1 (`DynamicTagSelect.jsx`):

- **Parse** (`:385-395`) destructures `id`, `source`, `key`, `link`, `required`, `tax`, `size`,
  `dateFormat` out of `parsedTag.params` **unconditionally** — before `extraParams` (which becomes
  `extraTagParams`) is formed. `size` then goes into GB's own `imageSize` state (`:443`, ungated).
- **Serialize** (`:541`) re-emits it from that private state — `if ( imageSize && 'full' !== imageSize )
  options.push('size:'+imageSize)` — also **ungated on `supports`**.
- **Render** (`:800`) is the ONLY support-gated step (`tagSupportsImageSize`).

So a tag that drops `'image-size'` support still round-trips a saved `size:` token: invisible in the
modal, absent from `extraTagParams`, yet re-serialized on every save. The `tagSpecificControls`
filter receives only `{ state: extraTagParams, setState: setExtraTagParams }` (`:112`), so **a custom
control can neither read nor clear these reserved keys** — there is no plugin-side lever.

**Consequence for migrations:** a legacy reserved-key token can only be rewritten by transforming the
**raw tag string before GB parses it** (our `TagConverter` path). An editor-open / control-mount fold
is impossible for reserved keys. (This is the stranded-reserved-token trap that forced the
`tax` → `srcTermIn` rename, in a different guise; hit again by the 1.16.0 image `as`+`size` fold —
see [`tag-reference.md` §`as` serialization opt-out + `as`+`size` fold](tag-reference.md).)

### Serialization order is independent of control (render) order — GB itself proves it

The order options **serialize** in the tag string is a separate axis from the order their controls **render** top-to-bottom in the modal. **GB's own `post_date` demonstrates the split:** its modal renders **Date Format ABOVE Link To**, yet it serializes `{{post_date id:100|link:author_archive|dateFormat:F j, Y}}` — **link before format** (render puts format first, serialization puts it last). Render order is fixed by the control-render sequence; serialization order is `extraTagParams` insertion order (above). The two need not agree, and for `post_date` they don't.

This is the affordance the plugin's reorder normalizer stands on: a per-tag JS normalizer (gated by tag name via `generateblocks.editor.tagSpecificControls`) rebuilds `extraTagParams` in a canonical serialization order inside `setState`, WITHOUT touching control render order (which stays the registration/PHP option-definition order). The gate is per-tag-name so a tag with a value-writing composite and the order-normalizer can coexist: they converge iff their guards test **disjoint** properties — the normalizer touches key-ORDER only, a composite touches key-VALUE only (spread-preserve `setState`), so neither perturbs the other's axis. The plugin's canonical orders and the normalizer's status live in [`tag-reference.md` §Option order](tag-reference.md#option-order); this is the pure GB fact that makes the decoupling possible.

## Replacement is gated on block NAME — and the gate is filterable

GB hooks WP's `render_block` at priority 10 (`includes/dynamic-tags/class-dynamic-tags.php:25`), so
the callback fires for **every** block on the page — including core blocks. It then gates
immediately on block name alone (`:374-382`):

```php
public function replace_tags( $content, $block, $instance ) {
    $block_name = $block['blockName'] ?? '';
    if ( $block_name && in_array( $block_name, $this->get_allowed_blocks(), true ) ) {
        return GenerateBlocks_Register_Dynamic_Tag::replace_tags( $content, $block, $instance );
    }
    return $content;   // every non-GB block exits here, untouched
}
```

Allow-list (`:350-364`): `generateblocks/element`, `loop-item`, `looper`, `media`, `query`,
`query-page-numbers`, `shape`, `text`. **Gated on NAME only** — not on attributes, not on content.
A `{{tag}}` inside any other block renders as its literal string.

**The list is filterable: `generateblocks_dynamic_tags_allowed_blocks`.** Adding a core block name
to it makes GB process tags inside that block's rendered output. This plugin does not currently hook
it. What makes the extension viable on the GB side: the replacement is a pure regex over `$content`,
markup-agnostic and fast-pathed by a `generateblocks_str_contains( $block_html, '{{' )` guard
(`class-register-dynamic-tag.php:111`) — it neither knows nor cares which block produced the HTML.
Whether a given core block can actually *hold* a tag is a WP-core question, not a GB one.

Caveats when extending: the tag must be a **registered** GB dynamic tag (the pattern is built from
`array_keys( $availableTags )`), dynamic-tag security/context still applies, and a non-GB block
supplies **no loop context** — only ambient-entity tags resolve there.

## No escaping anywhere in the replacement path — tags may return raw HTML

There is no `esc_html`, no `wp_kses`, and no allowed-tags filter standing between a callback's
return value and the rendered page.

- `class-register-dynamic-tag.php:129` calls the callback; `:196` splices the result with a bare
  `str_replace( $full_tag, (string) $replacement, $content )`. Between those lines the value passes
  through only two `apply_filters` (`generateblocks_dynamic_tag_replacement` `:147`,
  `generateblocks_before_dynamic_tag_replace` `:181`).
- `GenerateBlocks_Dynamic_Tag_Callbacks::output()` (`class-dynamic-tag-callbacks.php:218-234`) is
  pure string transforms — trunc, replace, trim, case, wpautop, link. Nothing escapes.
- `wp_kses_post` appears only **inside three specific GB callbacks**, applied to untrusted stored
  meta (`:387-389` `get_post_meta`, `:495-497` `get_author_meta`, `:665,672` excerpt read-more) —
  never as a pipeline gate. Each wraps the call in a filter that **widens** the allowlist
  (`class-dynamic-tags.php:1030-1045` adds `<iframe>` and exposes
  `generateblocks_dynamic_tags_allowed_html`). `wp_kses_post` permits `<ul>/<li>/<table>/<tr>/<td>`
  regardless.

**GB's own tags rely on this** — `get_term_list` (`class-dynamic-tag-callbacks.php:600-616`) builds
raw `<a rel="tag">` / `<span>` strings and returns them through `output()`.

**So does this plugin** — `bws_wrap_with_link` ([`link-helpers.php`](../includes/helpers/link-helpers.php))
emits a raw `<a>` with the resolved value interpolated unescaped; `bws_phone_render_one`
([`phone-tags.php`](../includes/tags/phone-tags.php)) and the email tag do the same. Sanitization in
this plugin is **opt-in per site** (`bws_sanitize_rich_content`,
[`content-helpers.php`](../includes/helpers/content-helpers.php)), called only for WYSIWYG-sourced
content (author bio, term description) — it is not a global gate.

Image tags are **not** evidence of this affordance: they return URLs/IDs/alt strings and GB's
`media` block builds the `<img>`.

**Consequence:** a tag returning structured markup (`<ul>…</ul>`, `<table>…</table>`) reaches the
page live, with no GB-side change required. The corollary responsibility is ours — a tag that
interpolates stored field values into markup must escape them itself.

## `tagName` enums: editor-restricted, render-permissive

Each block declares a `tagName` enum in its `block.json`, and **that enum drives the editor dropdown
at runtime.** `TagNameControl` reads it live rather than carrying its own list
(`src/components/tagname-control/TagNameControl.jsx:5-25`, minified as `Oe` in
`dist/blocks/element/index.js`):

```jsx
const tagNames = getBlockType( blockName )?.attributes?.tagName?.enum ?? [];
const tagNameOptions = options.length ? options : tagNames.map( ( tag ) => ( { label: tag, value: tag } ) );
```

GB **Pro** does the same and additionally *intersects* configured options with the enum
(`generateblocks-pro-*/dist/editor-access.js`).

Enums as of 2.3.0 (identical in 2.2.1 and 2.3.0-beta.2):

| Block | `tagName` enum |
|---|---|
| `element` | `div, section, article, aside, header, footer, nav, main, figure, a, ul, ol, li, dl, dt, dd` |
| `looper` | `div, section, article, aside, header, footer, nav, main, ul, ol` |
| `loop-item` | `div, li, a, article, section, aside` |
| `text` | `p, span, div, h1`–`h6, a, button, figcaption, li` |
| `query` | `div, section, article, aside, header, footer, nav, main` |
| `query-page-numbers` | `div, section, nav` |
| `media` | `img` |

**No table tags anywhere.** This is the entire cause of the observed "a GB query loop can build
`ul`/`ol` but not `dl` or `table`" — pure enum omission in `looper`/`loop-item`. There is no logic,
no validation, and no comment behind it; GB appears never to have considered table output (a
repo-wide sweep of GB + GB Pro finds no table handling and no `display:table` CSS — every
`"table"`/`tbody`/`td` string hit in `dist/*.js` belongs to bundled DOMPurify).

**PHP render performs no validation.** `element` is a save-based (static) block:
`includes/blocks/class-element.php:37-49` runs `generateblocks_maybe_add_block_css()` and returns
`$block_content` — no `$allowedTagNames`, no `in_array` fallback, no `wp_kses`. Contrast the
**legacy** Container block, which does enforce one (`class-container.php:1022-1040`, filter
`generateblocks_container_allowed_tagnames`) — `element` has no equivalent. WP core does not enforce
JSON-Schema `enum` on attributes parsed from `post_content`, and the JS `save()` interpolates the
attribute raw as the React element type.

**Extension point:** there is no GB-specific filter for the tag list (verified — no
`generateblocks_element_tag_names` or JS equivalent). WP core's `blocks.registerBlockType` JS filter
is the lever: pushing entries onto the enum at registration adds them to the dropdown, and they
render. GB Pro already uses that idiom for other purposes.

**Caution:** this is unversioned coupling to another plugin's `block.json`. If GB adds its own table
tags or changes the attribute schema, an enum patch silently conflicts or breaks.

## Block appender is suppressible via JS filter

GB renders the innerblocks appender behind
`applyFilters( 'generateblocks.editor.showBlockAppender', <default>, { clientId, isSelected, attributes } )`,
passing a `renderAppender` that supplies the appender's *contents* (an `Inserter`) but not its
wrapper tag. Returning `false` from the filter suppresses it entirely.

Noted here as an available lever: the appender's wrapper is a `<div>` emitted by WP core as a direct
child of the innerblocks container, which matters for any `element` `tagName` whose content model
rejects a `<div>` child. That platform behavior is out of scope for this doc — see
`.claude/plans/structured-output-tags-handoff.md` §4.

## Upstream-documented affordances

Pulled from the [upstream registration doc](https://learn.generatepress.com/developer-doc/dynamic-tag-registration/). Facts here are GB-owned API surface — if upstream changes, that doc wins. Listed here as known extension points; most are not exercised in this plugin (the exception is `visibility`, first used by `{{email}}` in 1.9.0 — see below).

### Registration params rarely / not yet used

- **`visibility`** — controls when a tag appears in the editor selector. Accepts `true` (default), `false`, `[ 'context' => [...] ]`, or `[ 'attributes' => [ [ 'name' => ..., 'value' => ..., 'compare' => ... ] ] ]`. Compare operators: `===`, `!==`, `IN`, `NOT_IN`. **Distinct from our JS `show_if` layer** (which gates *option* visibility inside an open modal). `visibility` gates the tag itself in the selector list. Prefer native `visibility` over JS when the gate depends on block attributes (`tagName`, etc.) rather than sibling option values. **First plugin use: `{{email}}` (1.9.0)** registers `tagName NOT_IN ['a','button','img','picture']`, mirroring GB core's own `term_list` registration — its default-ON `mailto:` wrap is invalid inside anchor/button (nested interactive) or img/picture (void/replaced). Note only the **`a`/`button` half of that gate actually fires**; `img`/`picture` is unreachable — see the blind-spot section below before designing a new gate. See `tag-reference.md` §Email tag.
- **`description`** — help text shown below tag in selector UI. None of our tags set this; consider adding to clarify ambiguous tags (e.g. `term_*` selector).

#### `visibility` blind spot — `img`/`picture` is unreachable (verified GB 2.3.0, 2026-07-21)

**A `tagName` gate naming `img`/`picture` can never fire.** No editor-reachable GB block
presents a `tagName` of `img` or `picture` to the picker's compare. Verified two ways:

1. **The Container block's enum excludes void tags.** `dist/blocks/element/block.json`
   declares `tagName` with enum `div, section, article, aside, header, footer, nav, main,
   figure, a, ul, ol, li, dl, dt, dd` (full enum table: [§`tagName` enums](#tagname-enums-editor-restricted-render-permissive)).
   No `img`, no `picture`. The block that *does* serialize a real, compared `tagName` cannot be
   set to a void element.
2. **The media block serializes `img` but the picker never sees it.** Its
   `dist/blocks/media/block.json` declares `tagName` as `{"type":"string","default":"",
   "enum":["img"]}`. A saved block *does* carry `"tagName":"img"` in its markup — but the
   picker's filter call site (`dist/blocks/media/index.js`) passes a `tagName` **prop** that
   is not populated from the saved attribute, and the comparator falls back to `""` via
   `const o = r?.[a] ?? ""`. So `!['a','button','img','picture'].includes("")` → **true** →
   every tag stays offered.

> **Correction (2026-07-21).** This section previously attributed the hole to the media
> block's `tagName` "never serializing." That is wrong — it serializes fine; the picker
> just doesn't read it. The observable consequence (tags still offered on media) was and
> remains correct. Confirmed empirically: `{{email}}`, which has carried the
> `['a','button','img','picture']` gate since 1.9.0, is still listed in the picker on a
> media block.

**Consequences for gate design:**

- The `img`/`picture` half of any `tagName` gate is **decorative** — it costs nothing but
  protects nothing. Only the `a`/`button` half does real work, on Container blocks set to `a`.
- A gate is therefore only worth registering for the **anchor/button** case. Gating a tag
  *solely* on `img`/`picture` is inert. ([#31](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/31)
  proposed exactly that for `text`/`title`/`datetime_*`/`join` and was closed as inert on
  this finding — see `future-work.md` Closed/Retired FW-11.)
- Anything needing real media-block protection must use the **runtime backstop** below.

**Media-block `src` injection (separate, still live).** On a media block GB injects the
tag's replacement into the `<img src>` attribute (`class-dynamic-tags.php` —
`'generateblocks/media' === $block_name`), so any tag emitting markup (e.g. the default-on
`<a>` wrap of `{{phone}}` / `{{email}}`) corrupts `src`. Bare-text tags are fine there, and
`{{image as:url}}` populating `src` is the intended pattern.

**Plugin response:** a **runtime backstop** keyed on block *name* — the only reliable media
signal, and the same key GB itself uses. `bws_tag_blocked_on_media_block()`
([`link-helpers.php`](../includes/helpers/link-helpers.php)) returns true for
`generateblocks/media`; the `{{phone}}`, `{{email}}`, and `{{call}}` callbacks call it and
return `''` early, and the template registry threads it to `term_`/`try_` variants behind its
`$media_guard` flag. This backstop is **load-bearing, not redundant** — it is the only thing
standing between a media block and a corrupt `src`, precisely because the native gate cannot
fire there. Tags whose output is bare text by default (`text`/`title`/`datetime_*`/`join`)
deliberately do NOT take it: their output in `src` is valid, and blocking it would break a
legitimate use.

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
values must be designed brace-free — e.g. `{{join}}` template mode uses `%1`…`%10` positional
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
