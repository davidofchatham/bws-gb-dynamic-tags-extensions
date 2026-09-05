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

## Duplicate tag names: last write wins, silently
`GenerateBlocks_Register_Dynamic_Tag::__construct()` guards only for completeness (it returns unless `tag`, `return` and `title` are all set, and defaults `type` to `post`) and then ends in one assignment, `self::$tags[ $args['tag'] ] = $args`. No duplicate check, no warning, no return value: registering a name another plugin already registered replaces that plugin's whole entry, the `return` callback included. Whichever plugin registers LAST owns the tag.

Read from GenerateBlocks 2.4.1 (`includes/dynamic-tags/class-register-dynamic-tag.php`, re-read against the fixture site's own copy 2026-08-26) and measured there the same day, by registering a second `text` tag at `init` priority 5 and reading `get_tags()` back after this plugin's own pass at priority 20, then again with the stranger at priority 99 to see the collision go the other way.

Nothing in GB surfaces the event in either direction, so a site whose tag output changed after an unrelated plugin was installed has nothing to read. This plugin's response is at `bws_gb_register_tag()` and `bws_gb_recheck_tag_ownership()` ([`includes/helpers/gb-registration-boundary.php`](../includes/helpers/gb-registration-boundary.php)); its shape is summarized in [`tag-reference.md` §Tag name collisions](tag-reference.md#tag-name-collisions).

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

### Switching tag type in the modal DISCARDS all options — there is no carry-over

Picking a different tag in the modal's tag selector resets the option state: nothing the author had
configured on the previous tag survives the switch, even where the two tags share option keys
verbatim. GB tracks no per-tag option memory and offers no pre-switch hook, so a plugin cannot
observe the outgoing state to migrate it forward.

**Consequence for us:** a "convert this tag to that tag" affordance can never be built as a *tag-type
switch*. Any conversion between two of our tags that must preserve configuration has to happen
**inside a single tag's option set** — i.e. the target behavior must be reachable as an option on the
tag the author already has, not as a different registered tag. This is what forces the
absorb-into-base direction for the `try_` family (a per-tag "add another source" control rather than
a `{{text}}` → `{{try_text}}` switch); see [`future-work.md`](future-work.md) FW-60.

Today the author's workaround is hand-editing the tag string (retype the name, keep the options).
That path holds only while both tags spell their options identically — the FW-57 slot fold ends it
for base → `try_`, which is the trigger that surfaced this constraint.

### Serialization order is independent of control (render) order — GB itself proves it

The order options **serialize** in the tag string is a separate axis from the order their controls **render** top-to-bottom in the modal. **GB's own `post_date` demonstrates the split:** its modal renders **Date Format ABOVE Link To**, yet it serializes `{{post_date id:100|link:author_archive|dateFormat:F j, Y}}` — **link before format** (render puts format first, serialization puts it last). Render order is fixed by the control-render sequence; serialization order is `extraTagParams` insertion order (above). The two need not agree, and for `post_date` they don't.

This is the affordance the plugin's reorder normalizer stands on: a per-tag JS normalizer (gated by tag name via `generateblocks.editor.tagSpecificControls`) rebuilds `extraTagParams` in a canonical serialization order inside `setState`, WITHOUT touching control render order (which stays the registration/PHP option-definition order). The gate is per-tag-name so a tag with a value-writing composite and the order-normalizer can coexist: they converge iff their guards test **disjoint** properties — the normalizer touches key-ORDER only, a composite touches key-VALUE only (spread-preserve `setState`), so neither perturbs the other's axis. The plugin's canonical orders and the normalizer's status live in [`tag-reference.md` §Option order](tag-reference.md#option-order); this is the pure GB fact that makes the decoupling possible.

### Option controls are flat siblings in a 15px-gap flex column

`applyFilters( 'generateblocks.editor.tagSpecificControls', … )` is called once **per option**, and
the returned elements are spread as siblings into the modal's content column — there is no per-option
wrapper GB adds, and no seam that sees two options at once. The column is
`.gb-dynamic-tag-modal__content{display:flex;flex-direction:column;gap:15px}` (GB 2.3.0 editor CSS).

Two consequences the plugin depends on:

- **A filter cannot group options structurally.** Any visual grouping of separately-registered
  options has to be drawn per member and joined by CSS across siblings, or else one control has to
  swallow the others (and then own their `show_if` reveal). `assets/js/option-group.js` takes the
  first route; FW-64 tracks the second.
- **The 15px is a load-bearing number**, since closing the gap between two joined members means
  cancelling exactly it. It is a `--bws-optgroup-gap` custom property rather than a literal for that
  reason. The same gap is why controls inside our own boxes carry no `marginBottom`.

## Replacement is gated on block NAME — and the gate is filterable

GB hooks WP's `render_block` at priority 10 (`includes/dynamic-tags/class-dynamic-tags.php:25`), so
the callback fires for **every** block on the page — including core blocks. It then gates
immediately on block name alone (`:374-392`, re-read on GB 2.4.1):

```php
public function replace_tags( $content, $block, $instance ) {
    $block_name = $block['blockName'] ?? '';
    if ( $block_name && in_array( $block_name, $this->get_allowed_blocks(), true ) ) {
        // …then a second gate, on the post the markup came from, not on the block:
        // see §Dynamic data is suppressed by POST SOURCE.
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

## Dynamic data is suppressed by POST SOURCE, not by tag name (GB 2.4.0+)

GB 2.4.0 added a second gate under the block-name one above, and it is the reason this plugin's tags are covered by GB's newest security work without registering anything. Where the 2.2.0 per-key rules it REPLACED singled out `{{post_meta}}` / `{{term_meta}}` / `{{user_meta}}` **by name** (the last three blocks of this section, which is also where 2.4.0's removal of them is recorded), this model asks only who last saved the post the markup came from. Every registered dynamic tag is in scope, GB's and ours alike.

Read in GB **2.4.1** source on the fixture site (`includes/class-dynamic-tag-security.php`, `includes/class-save-gate.php`, `includes/dynamic-tags/class-dynamic-tags.php`, `includes/dynamic-tags/class-register-dynamic-tag.php`), 2026-09-04. **Measured against one of our tags 2026-09-05** — `verify.php`'s GB-trust-model section drives all three propositions on the fixture site: that GB's `applies` predicate scans a GB block carrying our tag, that a restricted user's save of it is refused, and that a tainted source frame renders it empty. Until then this section was read-only, and said so; the three pins exist because a claim about what an instrument does is measured or pinned, never inferred.

**GB ranks its own two layers, and the ranking is the load-bearing part.** `GenerateBlocks_Save_Gate`'s class doc: *"the gate is a convenience/authoring layer, not a security boundary on its own"* — saves can predate a rule or arrive with the gate disabled by filter, so *"every rule needs its own authoritative guard at output time"*. For dynamic data that guard is the render-time taint model. Read the table below in that order of authority, not top to bottom: a change to the save gate is an authoring-experience change, a change to suppression is a security one.

**Three layers, in save order.**

| Layer | Where | What it does |
|---|---|---|
| **Save gate** | `GenerateBlocks_Save_Gate`, rule id `dynamic_data` registered at `class-dynamic-tag-security.php:146` | Refuses the save outright when a user who cannot author dynamic data submits content the rule `applies` to. Covers classic `wp_insert_post_data`, REST `rest_pre_insert_*`, and the autosave route. |
| **Taint stamp** | `update_untrusted_dynamic_content_meta()` on `wp_after_insert_post` priority 0 (`:119`, body `:458`) | Writes post meta `_generateblocks_untrusted_dynamic_content` = `{user_id, time}` when a restricted user's save **changed the content**; a trusted user's save **clears** it. Revisions and autosaves are skipped so an autosave cannot flip the parent's state. |
| **Render suppression** | `should_suppress_dynamic_data()` (`:1080`) → `replace_dynamic_tags_with_empty()` (`:1101`) | While a tainted post is the innermost content source, every dynamic tag in the output is replaced with `''`. |

**The `applies` predicate never looks at tag names.** It is `should_validate_content()` (`:180`): `strpos( $content, '{{' )` plus `has_block()` against `get_dynamic_tag_enabled_blocks()` — the same allow-list §Replacement is gated on block NAME quotes. Legacy v1 dynamic attributes match independently of `{{`. So a restricted user saving a `{{text}}` of ours inside a `generateblocks/text` block is blocked exactly as a `{{post_meta}}` would be.

**The blanker enumerates the registry, not a pattern.** `replace_dynamic_tags_with_empty()` builds its replacement set from `GenerateBlocks_Register_Dynamic_Tag::find_matches( $block_html, GenerateBlocks_Register_Dynamic_Tag::get_tags() )` — `get_tags()` is the whole registry, which is where our tags live too.

**Both suppression checks sit ABOVE the per-tag callback layer**, so no callback of ours runs inside a suppressed frame and there is nothing for a tag author to opt into:

- `GenerateBlocks_Dynamic_Tags::replace_tags()` (`class-dynamic-tags.php:378-387`) — blanks on `should_suppress_dynamic_data()` **or** `is_non_trusted_block_renderer_rest_request()` (a `/wp/v2/block-renderer` request from a user who cannot author dynamic data), before it delegates.
- `GenerateBlocks_Register_Dynamic_Tag::replace_tags()` (`class-register-dynamic-tag.php:124-131`) — the same `should_suppress_dynamic_data()` check again, at the lowest replacement layer, so direct callers that resolve an attribute value without passing through `render_block` are covered too.

**How a source frame gets pushed.** `the_content` at `-PHP_INT_MAX` / `PHP_INT_MAX` and `render_block_data` / `render_block` push and pop frames (`:122-125`); `render_with_content_source( $post_id, $callback )` (`:1061`) declares one around a raw `do_blocks()` of stored content — GB Pro 2.7 wraps its overlay, form, classic-menu and pattern-preview renders in it via `generateblocks_pro_render_with_content_source()`. A frame with no known post is neutral: it resolves unless a tainted **parent** frame is on the stack, and `should_suppress_dynamic_data()` returns true if **any** frame in the stack is tainted.

**The trust predicate is one filterable call.** `GenerateBlocks_Dynamic_Tag_Security::user_can_author_dynamic_data()` (`:345`) defaults to `current_user_can( 'unfiltered_html' ) || current_user_can( 'manage_options' )` and is filtered by `generateblocks_user_can_author_dynamic_data`. GB's own docblock calls returning `true` a disclosure of protected meta, other users' meta, options and cross-post data — grant it deliberately. Enforcement of the whole save gate is separately filterable via `generateblocks_enforce_dynamic_data_save_gate`.

**The save gate is a RULE REGISTRY, and partner plugins are named registrants.** `GenerateBlocks_Save_Gate::register_rule()` takes `id` / `applies` / `user_can` / `message` / `error_code`, plus optional `enforced` (a rule-specific toggle) and `exempt` (a finer-grained safety proof checked after the shared no-new-exposure exemption fails). `dynamic_data` is one such rule, not the gate itself. The gate owns the entry points — `rest_pre_insert_{post_type}`, `rest_dispatch_request` for Gutenberg autosaves, `wp_insert_post_data`, and `wp_insert_attachment_data` (an attachment's description IS its `post_content`) — so *"a rule registered here inherits coverage of every entry point without knowing they exist"*. Its class doc states the registration protocol: partner plugins register directly on `plugins_loaded` or later behind a `class_exists( 'GenerateBlocks_Save_Gate' )` check, and there is deliberately **no registration action to miss**. Re-registering an id replaces that rule; a malformed rule is rejected via `_doing_it_wrong` rather than half-enforcing.

**A registrant's absence is undetectable, which is the trap worth naming.** Because the protocol is a `class_exists` check rather than an action, there is no hook to grep for and no missing callback to notice: nothing fires and nothing fails whether a given plugin registers a rule or not. What THIS plugin does about that is not a GB fact — [`tag-reference.md` §Response to GB's dynamic-data trust model](tag-reference.md#response-to-gbs-dynamic-data-trust-model) has it.

**The editor gates its own dynamic-data UI on a localized flag.** `generateBlocksEditor.canAuthorDynamicData` (`includes/general.php:302`, beside `dynamicTagsPreview` at `:299`, which is the same predicate ANDed with `generateblocks_dynamic_tags_preview`). GB's editor bundle uses it to null out the Dynamic Data panel and to gate the tag-insert toolbar on `canAuthorDynamicData || foundTags.length`. Measured consequence for extension authors, read from `dist/blocks/text/index.js` 2026-09-05: an untrusted user reaches the tag BUILDER on no path at all. With no tags in the content the modal returns `null`; with tags present it renders a found-tags list carrying *"Your account can't add or edit dynamic tags, but you can remove existing ones."*, the tag names as plain text rather than the buttons a trusted user gets, and a remove control each. Since `generateblocks.editor.tagSpecificControls` is applied inside the builder, **no custom tag control mounts for an untrusted user** — ours included.

**Existing content is grandfathered by construction, and recovery is a re-save.** The taint is post meta that only a restricted user's content-changing save writes, so nothing saved before 2.4.0 carries it. A trusted user re-saving a tainted post clears the marker — that is the documented way back, and it is why a no-op touch by a restricted user is deliberately made not to re-taint.

**What replaced the 2.2.0 per-key rules, and why there is nothing left to inherit.** 2.2.0 validated meta KEYS NAMED INSIDE TAG STRINGS at save time: a rule table keyed on literal tag names (`/\{\{post_meta…key:([^|}]+)…}}/i` and its `term_meta` / `user_meta|author_meta` siblings), each regex feeding the captured key to a validator — underscore-protected keys refused for post and term, and for user additionally `DISALLOWED_KEYS` plus anything outside `get_safe_user_meta_keys()`, that whole rule bypassed by `list_users`. A `link:post_meta,<field>`-shaped option inside ANY tag was scanned too. Violations were counted by a `type:field` signature and grandfathered against what the saved post already held, so an existing violation could be re-saved but not joined by a new one.

**2.4.0 removed that model rather than extending it.** `validate_content()` is now `unset( $content ); return true;`, carrying `@deprecated 2.4.0 No-op stub` — as do `validate_content_with_existing_signatures()`, `get_restricted_reference_signatures()`, `should_validate_user_meta_fields()` and `register_rest_validation_for_post_types()` (empty body). The rule table, the signature scanner and the `generateblocks_dynamic_tag_validation_rules` filter are gone from free; GB Pro 2.7 registers its `option` rule on that filter ONLY when free is 2.3.x or older, guarded by a `method_exists()` probe for the taint model. The three `validate_*_field_name()` methods survive with no caller. **`get_safe_user_meta_keys()` is the one piece still live**, and it moved to the read side: `Meta_Handler`'s REST-time user gate and `GenerateBlocks_Dynamic_Tags`' user-record REST field both consult it.

**So the name-scoping never mattered on this version.** It is worth stating anyway, because it is the reason a 2.2.x-era or 2.3.x-era GB gives this plugin's tags a different save-time treatment than GB's own: no pattern in that table could match a tag of ours, so `validate_content()` never scanned this plugin's output on any version — it declined to on the old ones and does not exist to on the new. Our read-layer response to the same exposure is enforced at `bws_field_key_disallowed()` ([`includes/helpers/field-helpers.php`](../includes/helpers/field-helpers.php)), whose PHPDoc states what it covers; post and term reads additionally inherit `GenerateBlocks_Meta_Handler::get_meta()`'s own REST-time checks by routing through it. The user kind does not route through it — [`future-work.md` FW-124](future-work.md).

## No escaping anywhere in the replacement path — tags may return raw HTML

There is no `esc_html`, no `wp_kses`, and no allowed-tags filter standing between a callback's
return value and the rendered page.

- `class-register-dynamic-tag.php:129` calls the callback; `:196` splices the result with a bare
  `str_replace( $full_tag, (string) $replacement, $content )`. Between those lines the value passes
  through only two `apply_filters` (`generateblocks_dynamic_tag_replacement` `:147`,
  `generateblocks_before_dynamic_tag_replace` `:181`).
- `GenerateBlocks_Dynamic_Tag_Callbacks::output()` (`class-dynamic-tag-callbacks.php:218-234`) is
  pure string transforms — trunc, replace, trim, case, wpautop, link. Nothing escapes. (The option
  keys that method CONSUMES are a different question from the transforms it applies, and are
  enumerated once, in `BWS_GB_TAG_OUTPUT_OPTIONS`
  — [`includes/helpers/gb-output-boundary.php`](../includes/helpers/gb-output-boundary.php).)
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

## An UNREGISTERED tag renders LITERALLY — braces and all

If no callback is registered under a tag's name, GB does not strip it, blank it, or comment it out.
The raw tag string reaches the page as text: a visitor sees `{{view_title src:current}}`.

Two independent mechanisms produce this, and the first is the one that actually fires
(`class-register-dynamic-tag.php`, GB 2.3.0):

```php
// find_matches() :91 — the pattern is BUILT FROM THE REGISTERED NAMES.
$pattern = '/\{{(' . implode( '|', array_keys( $availableTags ) ) . ')(\s+[^}]+)?}}/';

// replace_tags() :120 — and the loop re-checks, in case the caller passed a different list.
if ( ! isset( self::$tags[ $tag_name ] ) ) {
	continue;
}
```

Because the name is baked into the regex, an unregistered tag never becomes a match, so there is
nothing to replace and `str_replace` is never reached. The `isset` guard below it is belt-and-braces
(`find_matches()` takes the tag list as a parameter, so the two can in principle disagree).

**Consequence for us, and it is a retirement constraint rather than a curiosity:** deleting a tag
registration is not a silent no-op on content that still names it — every unconverted occurrence
turns into visible braced text on the front end. That is what makes the evidence asymmetry in a
family sunset real: retaining deprecated wrappers needs no proof, while DELETING requires the
unreachable surfaces to be empty first, because the failure mode is not a blank spot but literal
wire in the page. Our response to this constraint is the standing migration policy — registration
never retires ahead of migration (`tag-reference.md`).

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
`.scratch/plans/structured-output-tags-handoff.md` §4.

## The query block renders its inner blocks once before it iterates, and discards that render

WordPress renders a block's inner blocks into the `$block_content` string BEFORE calling that block's `render_callback`. GB's query and looper callbacks build their output from `$block->parsed_block['innerBlocks']` and never use that string, so **every dynamic tag inside a query loop runs once against the AMBIENT page before the first row exists**. The v1 block states it outright — `GenerateBlocks_Block_Query_Loop::render_block()` assigns `$content = ''` and then re-renders per post.

Measured on GB 2.4.1 (2026-09-04): a two-row `WP_Query` loop containing one `{{title}}`, driven through `do_blocks()` with a counter on `generateblocks_dynamic_tag_replacement`, produced FOUR calls — two resolving the surrounding page, then one per row. The pre-render's output is discarded, so nothing wrong is printed.

**What is not discarded is what a tag DOES.** A callback that only returns a string costs a wasted resolve; a callback with a side effect performs that side effect against the wrong entity, and the side effect outlives the string. That is the shape of [#133](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/133), where `{{content}}` queues the ambient page's extracted inline CSS from a render nobody sees. Our response is `bws_is_query_loop_setup_phase()` ([`content-helpers.php`](../includes/helpers/content-helpers.php)), which is currently ineffective for the reason that issue records.

## `inheritQuery` replaces the block's own query, and takes every query-args filter with it

`GenerateBlocks_Block_Query::get_query_data()` (GB 2.4.1, `includes/blocks/class-query.php`): when the block's `inheritQuery` attribute is set, GB clones the global `$wp_query`, takes `$data->query_vars` as its args, and **skips the branch that calls `GenerateBlocks_Query_Utils::get_wp_query_args()` entirely**.

Two consequences, and the second is the one that surprises. The block's own saved query — post type, `tax_query`, ordering — is discarded, which is what the setting says it does. And `generateblocks_query_wp_query_args` **never fires**, because that filter is applied inside `get_wp_query_args()`: every plugin extending query arguments through it is silently inert on an inheriting block, with no error and no empty result to notice.

The v1 block is NOT the same and the two are easy to conflate: `GenerateBlocks_Block_Query_Loop::render_block()` MERGES the global query vars into the block's own (`wp_parse_args( $wp_query->query_vars, $query_args )`) and still applies its own `generateblocks_query_loop_args` filter afterward.

Nothing of ours reads `inheritQuery` and there is no response to make. It is recorded because of how the failure presents: an inheriting loop nested inside another loop iterates the ambient page's main-query rows, so a tag of ours reads the containing page once per outer row and renders it faithfully. **A tag correctly reporting the wrong entity is indistinguishable from a broken tag**, and the cause is three levels up in another plugin's block attribute. Measured 2026-09-04 on a live site, where a `post_meta`-less nested post query under a GB Query Enhancements Term Query rendered the containing page once per term; the co-resident half is in [`coresident/gb-query-enhancements.md`](coresident/gb-query-enhancements.md#it-resolves-current-through-gbs-query-args-filter-and-plants-three-context-keys).

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
- A tag string stored in **post content** is live wherever that content renders — a listing's excerpt/body included — and a self-referencing `{{content}}` inside a post's own body resolves **empty** rather than recursing (measured 2026-08-29 on the fixture testbed: the block carrying it was hidden by the required-default rule below, on the post's own page and in listings alike). Fixture body text must therefore never carry literal tag syntax; `tools/test/context-test-matrix.md` records the incident.
- `required:false` — when the tag resolves empty, **still render the containing block**. The default is the other way: every tag is required unless this says otherwise, and GB returns `''` for the whole block when the replacement is falsy (`class-register-dynamic-tag.php` — `$required = ! isset( $options['required'] ) || 'false' !== $options['required']`, then `if ( $required && ! $replacement ) return '';`; read in GB 2.3.0 source, behavior measured on GB 2.4.1 2026-08-24). Useful for conditional layouts.

  **It keeps the BLOCK, not the VALUE, so it is not the remedy for a field holding `0`.** A GB callback is free to discard a falsy value before the required check is ever reached: `{{post_meta key:<field holding 0>}}` comes back `''` from `get_post_meta()`'s own `if ( ! $value )`, and `required:false` renders the block around nothing (measured 2026-08-24, GB 2.4.1 — text matrix T5.3). What preserves a real zero is the `generateblocks_dynamic_tag_replacement` filter below; the rule for what that covers is stated at [`includes/hooks.php`](../includes/hooks.php) and only there.

  **It DOES preserve a real `0` the callback returned** — the other half of the same rule, and the
  case that says where a zero is lost decides the remedy. When the callback hands back a genuine
  `'0'`, the required check is the only thing between that value and the page, so turning the check
  off renders the zero. Measured 2026-08-28 on GB 2.4.1 / GB Pro 2.7.0, **with this plugin
  deactivated** so its `'0'` guard could not confound the reading: text matrix T5.4's
  `{{loop_index zeroBased:1}}` on item 1 renders `0` with the editor's **Required to render**
  toggle unticked, and is suppressed with it ticked. Discarded inside the callback, nothing
  recovers it; discarded at the required check, either unticking or this plugin's guard does.
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

**Asymmetry with the JS parser.** The editor-side tag parser (minified in
`dist/blocks/element/index.js`, verified GB 2.3.0) is:

```js
r.split(/(?<!\\)\|/).forEach( e => {
    const [ t, n = !0 ] = e.split( /(?<!\\):/, 2 );   // ← String.split limit 2
    a[ t ] = n;
} );
```

It splits pairs on unescaped `|` and each pair on unescaped `:`, but does **not** unescape the
captured value, and GB's serializer writes `${key}:${value}` raw with no escaping. So any colon or
pipe in a custom control's stored state must be **pre-escaped on save and unescaped on display by the
control itself** for the round-trip to be clean. PHP render is fine either way.

**The limit-2 discard is the sharp edge — JS `split` ≠ PHP `explode`.** PHP's
`explode( ':', $pair, 2 )` keeps *everything* after the first colon in element `[1]`. JavaScript's
`String.split( regex, 2 )` **bounds the result to 2 elements and throws the rest away.** So a pair with
a **second** colon loses its tail on the JS side:

| Pair on the wire | PHP `explode(':',_,2)` → value | JS `split(/…:/,2)` → value | Round-trips? |
|---|---|---|---|
| `valueSep: ` (one colon) | `' '` | `' '` | ✅ |
| `fallback:https://x` (2 colons) | `'https://x'` | `'https'` (tail `//x` **discarded**) | ❌ |
| `valueSep:: ` (2 colons, empty middle) | `': '` | `''` (tail `' '` **discarded**) | ❌ |
| `format:l\:i` (escaped 2nd colon) | `'l:i'` | `'l:i'` (lookbehind skips `\:`) | ✅ |

Front-end render uses PHP only, so a `valueSep:: ` renders correctly *once*; the corruption surfaces
only when the editor **reopens** the tag and JS reparses the stored string (control repopulates
empty → re-serializes wrong). A render-only test (`render-tag`, front-end curl) never touches the JS
parser and will show a false pass — verify colon/pipe-bearing values by reopening the modal.

### Separator-safe vs unsafe characters (exhaustive)

Three parser layers stand between an authored option value and render; a character used **inside** a
value (a separator glue, a `format` literal, a subvalue delimiter) must clear **all three** to
survive a tag-string round-trip:

| Layer | Where | Rule | What it kills |
|---|---|---|---|
| Render matcher | PHP `find_matches()` | options captured `[^}]+` | `{` `}` — the tag never matches, renders literal |
| Pair split | JS + PHP | split on **unescaped** `\|` | `\|` |
| KV split | JS + PHP | split on **unescaped** `:` (JS **limit 2, discards tail**) | a **2nd** `:` in a pair |
| Editor field | Gutenberg RichText | entity-encodes `& < > " '` | those five round-trip as `&amp;` etc. |

Everything a parser doesn't name is **inert to it**. Netting the four layers:

**Serialization-safe (survive verbatim, no escaping):**
- Punctuation / symbols: `.` `,` `;` `/` `~` `!` `?` `-` `_` `=` `+` `*` `#` `@` `%` `^`
- Non-brace brackets: `(` `)` `[` `]`
- Whitespace — including a bare trailing space (`valueSep: `); PHP does **not** trim option values
  ([§Option Default Serialization](#option-default-serialization)).
- A **single** `:` that is the value's *only* colon (`valueSep: ` → value `' '`) — one colon is the
  legitimate key/value boundary, safe. Two-plus colons are unsafe (limit-2 discard, above).
- Escaped `\:` / `\|` — both parsers unescape; the JS lookbehind skips them at split time.
- Any non-ASCII / Unicode glyph (`›` `·` `•` `→` `∣`) — fully opaque to every layer. Safest, at
  legibility cost.

**Unsafe (never use raw as a separator / inside a value):**

| Char | Killed by | Failure mode |
|---|---|---|
| `\|` | pair split | truncates the pair at the pipe |
| `:` (2nd+ in one pair) | KV split, **JS limit 2** | **discards everything after the 2nd colon** on editor reopen |
| `}` | render matcher | **whole tag fails to match** — renders as literal `{{…}}` |
| `{` | render matcher | breaks `{{`/`}}` balance |
| `&` `<` `>` `"` `'` | RichText entity-encode | round-trip as `&amp;` / `&lt;` … in the editor field |

`\|` and `:` are **escapable** (`\|`, `\:` — both parsers unescape). `{` `}` have **no escape** and
are hard-unsafe — the reason `{{join}}` template mode ships `%1`…`%10` on the wire, not `{1}`…`{8}`
(see [Closing brace](#closing-brace) below).

**Choosing a separator glue.** Among the safe set, prefer characters **rare inside the values they
separate**: `,` and `;` almost never appear in field keys, so they split cleanly without escaping.
`.` is wire-safe but collides with dot-path field keys (`address.city`, `repeater.0.name`) — avoid it
as a subvalue delimiter. None of the safe separators **self-escape**: if a subvalue can itself contain
the chosen glue, you still need the escape/encode discipline in the workarounds below.

**No safe character carries parser scope.** GB's `parse_options()` is a flat key→value map with no
nesting. A wire-safe grouping character — brackets (`src[…]`), or any paired glyph — is **visual
only**: the parser still sees one opaque value string per key, and a `:` / `\|` / `}` *inside* a
"group" is fully live (still triggers the KV/pair split or kills the match). Any structured
sub-encoding (grouped, bracketed, CSV, positional) is therefore a **second parser you own on both
sides** (JS control for round-trip + PHP for render), including its own balancing/escaping — GB
contributes nothing beyond delivering the raw bytes.

### Tag-string-unsafe values

The classic failure is a value whose *own content* carries a **second** colon or a raw pipe — the KV
split's limit-2 discard (above) drops the tail on editor reopen. Affected:

- **Full URLs** (`https://...`) — the `:` after the scheme is the value's second colon, so JS keeps
  only `https` and discards `//host/path`. Symptom: tag re-opens with a truncated option
  (`fallback:https`). The slashes are inert; the **colon** is what breaks it.
- **Date/time literals with colons** (`12:30:00`) — same limit-2 discard.
- **Free-text user input** that may contain `:` or `|`.

**Workarounds (preference order):**
1. **Store an ID** referencing the value (attachment ID, term ID, post ID). Resolve at render. Used by `bws-media-picker` for image fallback (v1.7.3+). Use this when the value is a stable referenceable entity.
2. **Custom control with escape/unescape on save/display.** Control stores the escaped form (`\:` / `\|`) in option state; UI shows the unescaped form to the user. PHP `parse_options()` already unescapes both sequences before render. Used by `bws-format-input` for the `format` option on datetime tags (v1.7.4+). Use this when the value is free-text user input.
3. **Encode** (base64 / urlencode). Survives any chars but produces user-visible garbage in the tag string. Last resort.

Avoid storing raw URLs or colon-bearing free-text in default-text controls.

<a id="closing-brace"></a>

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
| `generateblocks_dynamic_tag_replacement` | `($replacement, $context)` — `$context` keys: `tag`, `full_tag`, `content`, `block`, `instance`, `options`, `supports` | [`includes/hooks.php`](../includes/hooks.php) — defeats the falsy-replacement block-kill for a bare `'0'` and for an empty `as:alt`, on every tag GB renders (the rule and its scope are stated there) |
| `generateblocks_before_dynamic_tag_replace` | `($content, $args)` — pre-replace HTML hook | not used |
| `generateblocks_dynamic_tag_id` | `($id, $options, $instance)` — override resolved entity ID. Applied in `GenerateBlocks_Dynamic_Tags::get_id()`. **IT DOES FIRE FOR OUR TAGS — measured 2026-08-26 against GB 2.4.1**, once per render for `{{title}}` and `{{text}}` alike, the same as for GB's own `{{post_title}}`. **The route is a call of OURS, not a GB one:** `CurrentPost::resolve_id()` calls `GenerateBlocks_Dynamic_Tags::get_id()` itself ([`class-current-post.php`](../includes/classes/sources/class-current-post.php) line 46, reached from `bws_resolve_base_source()`), so every base tag resolving a post source enters `get_id()` and the filter fires there. An earlier reading here said the hook could not fire for a BWS tag, on the argument that `get_id()` is reached only from GB's built-in callbacks and from `with_link()`, which early-returns unless `$options['link']` is set, and that we never set `link` (we link-wrap via our own `linkTo`/`linkKey` — see [`link-helpers.php`](../includes/helpers/link-helpers.php)). **Both of those GB facts still hold on 2.4.1** (measured 2026-08-26: every `Dynamic_Tags::get_id` call site inside GB is in its own `class-dynamic-tag-callbacks.php`, and `with_link()` still early-returns on an empty `link`). What that reading enumerated was GB's routes into `get_id()`, and it did not check ours — our source classes have called `get_id()` since v1.2.0 (`562d662`), predating the note. The conclusion was therefore wrong when it was recorded in 1.14.1, not overtaken by a GB change. It is what let #123 read as our loop detection alone. **GB does not pass `$fallback_type` to it**, so a callback cannot tell which entity kind the id it is overriding stood for — see [§`generateblocks_dynamic_tag_id` is not told WHICH entity the id was a fallback for](#generateblocks_dynamic_tag_id-is-not-told-which-entity-the-id-was-a-fallback-for). | not used by us (removed in 1.14.1; a filter here would silently defeat source resolution) — but a co-resident extension hooking it reaches our tags, which is [#123](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/123)'s upstream half |
| `generateblocks_dynamic_tag_output` | `($output, $options, $raw_output)` — final output transform | preserved as a third-party extension point on every tag, since 1.19.0 through the single seam [`bws_gb_tag_output()`](../includes/helpers/gb-output-boundary.php) that all of our output now ends at. The filter still fires; what reaches it is the options GB's own pipeline consumes, and which those are is decided at that seam. The content path layers its own unsets on top of it — [`bws_safe_content_output()`](../includes/helpers/content-helpers.php), see [`post-content-processing-reference.md`](post-content-processing-reference.md#L211) |
| `generateblocks.dynamicTags.sourceOptions` (JS) | `(options, context)` — add entries to source dropdown | not used; potential future hook for custom source contributions from third-party plugins |
| `generateblocks.editor.preview.context` (JS) | `(context, {props})` — the block context GB sends with an editor preview request. Applied DURING RENDER in the Looper, the Text block and the Media block, and the filtered object is passed on to consumers — see [§The editor preview context is filtered during render](#the-editor-preview-context-is-filtered-during-render-and-travels-into-consumers-dependency-arrays) | [`editor-preview-context.js`](../assets/js/editor-preview-context.js) — adds `bwsEditorPreview: true` so PHP callbacks return a preview label instead of an empty string. That file's docblock owns what its return value must satisfy |

### `generateblocks_dynamic_tag_id` is not told WHICH entity the id was a fallback for

`GenerateBlocks_Dynamic_Tags::get_id( $options, $fallback_type = 'post', $instance = null )` picks the
id from `$fallback_type` — `get_current_user_id()` for `'user'`, `get_queried_object_id()` for
`'term'`, `get_the_ID()` otherwise — and then filters it. **`$fallback_type` is not among the filter's
arguments**, which are `$id`, `$options` and `$instance` only (read 2026-08-26 in GB **2.4.1**, the
version `tools/fixtures/core-structures/env-versions.php` records on the fixture site —
`includes/dynamic-tags/class-dynamic-tags.php`, `get_id()` at lines 403–427 with the
`apply_filters()` call at 421–426; the docblock above it at 414–420 lists the same three).

A callback on this hook therefore receives a bare integer with nothing saying what kind of entity it
names, and cannot decline the cases it was not written for: a plugin that publishes its query loop's
TERM id through the hook overrides a **post** fallback with a **term** id, and no consumer downstream
of the filter can tell that it happened. The two id spaces collide constantly — every fresh install
has post 1 and term 1 — so the override lands on a real, wrong entity rather than on nothing.

There is no discriminating argument to read, and no other hook in GB's dynamic-tag surface carries
one either — **method:** all 13 `apply_filters()` calls under `includes/dynamic-tags/` were
enumerated on 2.4.1, 2026-08-26, and the only two naming an entity kind
(`generateblocks_dynamic_tags_post_record_response`, `generateblocks_dynamic_tags_user_record_response`)
name it in the hook name, are REST endpoints for the editor, and do not fire in a front-end render.
A filter callback can only look at `$options` and `$instance`, neither of which states the fallback
type. Anything that needs to know what an entity id *is* has to establish it some other way.

### The editor preview context is filtered during render, and travels into consumers' dependency arrays

`generateblocks.editor.preview.context` is not applied at request time. GB applies it in the body of a React component, so it runs on **every render** of that component, and the object a callback returns is what GB then uses. Measured 2026-09-04 against GB **2.4.1** on the fixture site: `dist/blocks/looper/index.js`, `dist/blocks/text/index.js` and `dist/blocks/media/index.js` all call `applyFilters( 'generateblocks.editor.preview.context', props.context, { props } )` at the top of the block's edit component.

In the Looper the filtered object does not stop there. It is passed straight on as the `context` argument of `generateblocks.editor.looper.query`, the hook a third-party plugin uses to contribute its own query types — so what a callback here returns becomes an input to somebody else's React code, which is free to treat it as a value or as an identity. GB's own `WP_Query` hook reads it as neither: that effect depends on the stringified query, the post id and the author id, and never on the context object.

A co-resident plugin's hook can and does depend on its identity, which makes a fresh object per call an every-render change downstream. GB Query Enhancements is the measured case, and [`coresident/gb-query-enhancements.md`](coresident/gb-query-enhancements.md#it-adds-query-types-through-generateblockseditorlooperquery) holds it, with the version it was measured at. What our own callback holds itself to is owned by [`editor-preview-context.js`](../assets/js/editor-preview-context.js)'s docblock and pinned by [`editor-preview-context-test.js`](../tools/test/editor-preview-context-test.js).

### Type values

Upstream lists `'post'`, `'author'`, `'user'`, `'term'`, `'media'`. We additionally use the **custom values** `'cross-source'` and `'first-available'` (not in upstream docs) — see [§Custom Tag Types](#custom-tag-types) and [`tag-reference.md`](tag-reference.md#modifier-prefixes).

## GB Pro pattern library: the cached copy of every pattern's content

Pure GB Pro facts. This plugin's response to them lives in
[`tag-reference.md` §Pattern cache reconcile](tag-reference.md#pattern-cache-reconcile).

Source: `generateblocks-pro/includes/pattern-library/class-pattern-library.php`, verified
against 2.7.0-beta.1 and measured on two production clones plus the testbed (2026-08-14).

| Fact | Detail |
|---|---|
| **Where it lives** | Post meta `generateblocks_patterns_tree` on each `wp_block` post. A list of entries; each entry is `id` (`pattern-<post_id>`), `label`, `pattern` (the content), `preview`, `scripts`, `styles`, `categories`, `globalStyleSelectors`, `formRefs`. |
| **When it is written** | `after_save()` on `wp_after_insert_post` priority 30, via `build_tree()`. Save time ONLY. |
| **It is NEVER rebuilt on read** | The pattern library's REST layer reads the meta and **drops** patterns that have no entry rather than regenerating one. A pattern with no entry disappears from the inserter. |
| **The listener is capability-gated** | `after_save()` bails on `! current_user_can( 'edit_post', $post_id )`. Measured: creating a `wp_block` with no current user (plain WP-CLI) produces **no meta row at all**; the same insert under `--user=1` produces a full entry. Any instrument or fixture that seeds patterns must therefore set an administrator, or it seeds nothing. |
| **Only the content field is slashed on write** | `build_tree()` stores `'pattern' => wp_slash( $post_content )` and every other field raw, then `update_post_meta()` unslashes the whole structure recursively. Two consequences: the **stored** `pattern` is byte-identical to `post_content` (so a raw `===` is the exact comparison, not an approximation), and every **other** field is stored already-unslashed and can never carry a backslash. A fixture rendering `/^\d+\s\w+/` has `/^d+sw+/` in its stored preview. |
| **A rebuild is not neutral** | `preview` is rendered at build time and depends on request context. Comparing a freshly built entry against the stored one across every pattern: 9 of 37 differed on one clone, 11 of 16 on the other; one query-loop pattern's preview fell from 8266 to 1232 bytes. `scripts`/`styles` are derived by string-matching that preview, so a degraded preview silently empties them (three patterns on one clone lost their one script and one style). Stored entries come from real editor saves, so they are the good ones. |
| **The content field is load-bearing beyond insertion** | The REST layer re-parses `pattern` to recover `formRefs` on entries written before that field existed, which is most of them on both clones. |
