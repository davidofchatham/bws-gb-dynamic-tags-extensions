# Editor Tag Configuration Previews

**Authoritative** for the editor-time preview text shown in place of an unresolved dynamic tag while the user is configuring it. This is NOT a front-end label and NOT the rendered output — it is the placeholder string GenerateBlocks shows in the editor when a tag can't yet resolve (no relationship configured, archive template, wrong post type). Schemas for the tags themselves live in [`tag-reference.md`](tag-reference.md).

When a base tag can't resolve in the editor, the callback returns this structured preview string instead of an empty string. Built by `bws_build_preview_label( $options, $template )` in `includes/helpers/preview-helpers.php`. (The function name retains the historical `_label` suffix; the text it builds is the configuration-preview described here.)

**Scope:** `text`, `content`, `title`, `email`, `datetime_single`, `datetime_range`, and their `term_` modifier equivalents. Image tags: only when `as:alt` or `as:caption` — excluded for `as:url` and `as:id` (attribute values; bracket string silently breaks the element). `permalink` excluded entirely (URL context — bracket string breaks `<a href>`). The slot-based composing tags have their own builders: `try_*` (§try_ tag previews) and `{{join}}` (§join preview), both below.

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
| `→` | Term-step traversal arrow |
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

Space-joined segments. The `→` separator precedes the term-step segment only.

| Condition | Segment |
|---|---|
| Modifier tag (e.g. `term_`) | Modifier `label` value (e.g. `Term`) |
| `src:site` (base tags + try_ slots) | `Site` (yields `… from Site`). It COMBINES with a `refs` step since 1.17.0 — an options store holds relationship fields like any other field store, and the engine's `rows` step had always accepted a site source for exactly that reason, so the old refusal was an asymmetry in one allowlist rather than a rule. It still does not combine with `terms`, for a different and permanent reason: a `terms` step names *the terms attached to this entity*, and a site is not an object terms attach to. A taxonomy field on an options page would be a field READ that yields terms, not a `terms` step. Since 1.15.0 every try_ tag offers a site slot (FW-4), previously only try_email/try_phone. On a modifier it is the invalid-combo warning instead |
| `src:ref` + `ref:X` set | `Ref 'X'` |
| `src:ref` + `ref` unset | *(triggers warning — see below)* |
| `srcTermIn:X` set | `→ {taxonomy singular label} Term` (live `get_taxonomy()->labels->singular_name`; fallback: `{tax} Term`) |
| `srcTermIn` set with empty value (legacy `srcTerm` without `tax`) | *(triggers warning — see below)* |
| `src` names a REGISTERED SOURCE as the chain's root (1.17.0, [#83]) | That source's `get_source_label()` — e.g. `External Post`, yielding `… from External Post`. **Author terms, never the token.** Independent of whether the source is currently OFFERED in the dropdown: the tag is stored, so the preview describes what it says. An UNREGISTERED token adds no segment and, since 1.17.0 ([#105]), raises the inert-chain warning instead — it resolves to nothing, and previewing it as a bare tag hid the likeliest hand-authored fault there is. **The keys the ROOT ENUM refuses are refused here too**, though they ARE registered sources and a bare lookup would name every one: `post`/`term` are internal spellings of the ambient entity (`src:post` would read `from Post`, which is what a bare tag already is), and the four retired traversal-substitute tokens are what the `related_post` migration exists to remove from wire. This is the whole editor experience for such a tag — a source resolving from request state cannot resolve in the editor, and the prefix-keyed modifier map is keyed on TAG NAME so it can never fire for a base tag |
| No modifier, `src` unset, no `terms` step | *(omit — no `from` clause)* |

**The source is read as a CHAIN, not as a token** (1.17.0, [#83]). `src` may hold a bare legacy
token or chain wire (`view;refs,office`), and a chain's root sits *inside* the value, so a raw
token compare structurally could not find it. `bws_build_preview_label()` reads through
`bws_fold_chain_from_options()` — the single reading the factory, the render arms and the
list-mode reveal all take — so the preview cannot come to disagree with what renders. Three
consequences:

- A legacy flat option set and its chain-wire twin preview **identically**
  (`src:ref|ref:rel|srcTermIn:cat` ≡ `src:refs,rel;terms,cat`).
- A chain that hops more than once emits **one segment per step**, in wire order:
  `['phone' from Ref 'staff' Ref 'office']`. The flat spelling could hold only one of each, so
  there is no legacy twin for this shape.
- An **argless** step warns rather than being described. `src:terms` with no taxonomy renders
  nothing (the engine short-circuits), so it reports `⚠ No taxonomy set` — the same answer its
  flat sibling gives.

**One namer builds these segments for all three previews** (1.17.0, [#102]).
`bws_preview_source_segments()` takes the chain and returns the ordered segments; the base
builder, the join slot walk and the try_ slot walk all read it (the slot walks through
`bws_try_preview_source_part()`, the SLOT door onto it, which took a flat triple until the slot seam
hands over chain wire — FW-71). A source is one concept, so a vocabulary change lands in one
place rather than reaching the three previews at three different times.

**A container wanting different text takes a PARAMETER, never its own literal** — the
`$allow_same` precedent on the slot read builder. Five switches, each a real difference above
rather than a preference:

| Switch | Off for | Why |
|---|---|---|
| `named_current` | base tags (on for slots) | A slot's source appears in a LIST and needs a visible anchor (`Current, Ref 'rel'`); a base tag's bare source is exactly what "no `from` clause" means |
| `lead` | a source with nothing before it | The `→` means *hopped from*, so a term step that opens the whole label has nothing to point back at and drops the arrow. A modifier segment preceding it turns it on |
| `roots` | slots | A slot's source cannot BE a registered root yet (FW-71), so naming one would print a segment for wire no slot can hold |
| `site` | rooting modifiers, slots | On a modifier a site root is already the invalid-combo warning; on a `try_email` site slot the site read IS the whole slot, so the segment is noise |
| `terms` | `term_*` modifiers | A modifier reads GB's native `tax` (the term's OWN taxonomy — descriptive, not a hop) and builds that segment itself |

**The switches gate NAMING, never CHECKING.** The inert-chain detection below runs above all five,
`roots` included — the slot door turns that one off, so a check placed under it would silently stop
flagging on every slot.

A **missing** step argument is REPORTED, not rendered: the namer hands back the step's slug and
each caller words it (`⚠ No ref key set` on a base tag, `B no ref` in a slot).

**One slot shape reads differently for the convergence**, deliberately: a slot with `src:site`
AND a `srcTermIn` previewed `→ <Tax> Term` before 1.17.0 and previews no source segment now.
Reading through the shared chain means the preview inherits what the render arms do with that
pair — the site read wins and the term step is dropped — so the old text described a hop that
has never happened. The pair is hand-edit-only (`srcTermIn` registers `show_if src: not:site`),
and a preview that disagrees with what renders is worth less than no segment at all.

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
| `rows` step with no repeater field | `⚠ No repeater field set` (1.17.0) |

### Inert-chain warning (a source that cannot resolve)

Distinct again from the two above: the source is neither missing an input nor invalid *for this
tag* — it names vocabulary that resolves to **nothing anywhere**, so the tag renders empty
whatever else is set. Added in 1.17.0
([#105](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/105)); base tags
had no signal for this at all before, because an unnamed root emits no segment and
`{{text src:currnet|key:x}}` previewed exactly like a bare `{{text key:x}}`. [ADR 0004](adr/0004-serialized-tag-string-human-readable.md)
makes that wire hand-authorable, which is exactly how an author produces it.

Checked **after** the invalid-combo warning (which is more specific and names its remedy) and
**before** the missing-input pass, which it replaces: listing the other gaps beside an inert
source sends the author to the wrong repair first. Fallback still appends. Wording owner:
`bws_preview_inert_warning()`; detection: `bws_preview_source_segments()`'s `$inert` out-param.

| Condition | Base warning | Slot fragment |
|---|---|---|
| Unknown step slug, any position (first one named) | `⚠ Unknown source step 'bogus'` | `B unknown source step 'bogus'` |
| Unregistered root token | `⚠ Unknown source 'currnet'` | `B unknown source 'currnet'` |
| Retired source token (`BWS_FOLD_RETIRED_SRC_TOKENS`) | `⚠ Source 'related_post' is no longer supported — run the Tag Converter` | `B source no longer supported — run the Tag Converter` |

A root outranks a step behind it: the factory consumes the root first, so a chain with both
fails there, and naming the step would send the author past the fault. On a slot the inert
warning reports **alone**, as a skipped slot's reason does — the slot reads nothing whatever its
key says.

**All three are decidable from the WIRE alone, with no per-template knowledge**, and that bound
is what the list is built to. Three things therefore **never** flag:

- **An ambient root.** What the ambient entity is on a given request is not knowable at parse time, so
  guessing would cry wolf on every ordinary tag.
- **A registered but UNOFFERED root.** Offering is not resolving; a source an integrator stopped
  offering still renders. (Gating this on `is_selectable_root()` is the named trap, pinned by
  mutation in the harness.)
- **A well-formed source no arm consumes YET** — a `rows` step with its repeater field set,
  today. Unimplemented is not inert: `{{table}}` wants a repeater row as its read context, and on
  a fanning tag it would concatenate like any other step. Flagging it would encode a per-template
  fact with a shelf life. The arm is FW-74.

The internal tokens `current`, `site`, `ref`, `post` and `term` never flag either — they resolve.

**A fourth silence, which is a PRECEDENCE not a rule about sources:** on a COMBINING container a
slot that states a source and no read is UNCONFIGURED, and the seam skips it before anything looks
at its chain — so `{{join A:src(bogus,x)}}` says nothing at all. That is the in-progress silence
holding, and the same slot warns the moment a read is stated. A SELECTING container has no such
state (an absent read inherits), so `try_text` flags it immediately.

**Detection sits OUTSIDE every display switch**, including `roots`. That switch means "do not
*name* a registered root", not "do not *check* one", and the slot door turns it off — a check
placed under it would silently stop flagging on every slot.

**A second, larger hole is not this warning's to fix.** The preview is built ONLY where resolution
came back empty, and an unresolvable source on a base `{{text}}` does **not** resolve empty today —
it falls through to the ambient entity and reads it (measured 2026-08-15: `{{text src:currnet|use:key|key:name_first}}`
renders the current post's `name_first`, and so do the retired-token and unknown-step spellings;
`{{phone}}` on the same chain correctly renders nothing). So on those tags the author sees a
plausible wrong value and no warning at all. Those are two [I15] leaks at two layers, and they are different reads — an unidentifiable source
token reads the AMBIENT entity ([#75](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/75)), while an unknown step slug is silently DROPPED and the
chain's PREFIX is read ([#109](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/109)); this warning is correct about the wire and appears wherever the tag previews at all,
which is every tag whose read is genuinely empty. `fold-test-matrix.md` §F14.18–22 keys its rows on
a field nothing carries for exactly this reason.

**Accepted hole:** `{{image}}` in an output-attribute mode (`as:url` / `as:id`) carries no
warning, because the preview text *is* the attribute value and a bracket string breaks the
element. Pre-existing and unchanged — image already misses `No meta key set` the same way. Only
an editor-side control notice could reach it, which is a different mechanism.

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
| `{{text src:refs,rel_post;terms,category\|key:body_text}}` | `['body_text' from Ref 'rel_post' → Category Term]` *(the chain-wire twin of the row above — identical by construction)* |
| `{{text src:refs,staff;refs,office\|key:phone}}` | `['phone' from Ref 'staff' Ref 'office']` *(one segment per step; inexpressible in the flat spelling)* |
| `{{text src:external\|key:sku}}` | `['sku' from External Post]` *(a registered source as the root, named by its own label)* |
| `{{text src:external;refs,office\|key:phone}}` | `['phone' from External Post Ref 'office']` |
| `{{text}}` | `[⚠ No meta key set]` |
| `{{text src:terms\|key:sku}}` | `[⚠ No taxonomy set]` |
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

`bws_build_try_preview_label()` walks slots 1-5, applies carry-forward (slot ≥2 empty fields resolve `same` against the prior slot's canonical value), then detects uniformity across two dimensions (field-part, source-part) and renders one of four shapes.

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
| `try_email` / `try_phone` empty key | n/a | `[⚠ Try: A no key]` (always needs a key — no no-key values) |
| All slots empty | `[⚠ Try: no slots configured]` | same |
| Per-slot warnings | `[⚠ Try: A, C misconfigured]` | same |
| Slot with an incomplete step | `[⚠ Try: B no taxonomy]` / `[⚠ Try: B no ref]` / `[⚠ Try: B no repeater field]` (1.17.0 — a step with no argument; the seam skips it rather than reading the un-stepped entity, and names which step is unfinished). When it is the ONLY slot, the reason replaces `no slots configured`, which would otherwise be actively misleading | same |
| Slot inheriting with nothing to inherit | `[⚠ Try: B no previous source]` (1.17.0 — a `same` root where no earlier slot resolved) | same |
| Image `as:url` / `as:id` | *(no preview — excluded)* | — |
| `try_permalink` | *(no preview — excluded)* | — |

Trailing `(fallback: "X")` appended whenever `fallback` option is set, matching base preview behavior.

`try_email` / `try_phone` ([#32](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/32), 1.11.0) are text-like with `$needs_key = true` and no no-key values (single key-mode, no `use` enum) — so an empty-key slot always warns `⚠ <L> no key`, and a configured slot renders `Email: 'key'` / `Phone: 'key'`. This is the [#24](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/24)-correct shape (warn on a genuinely unconfigured slot, unlike `content` whose default `use` needs no key).

## join preview

`{{join}}` is the standalone COMBINING tag (up to `BWS_JOIN_MAX_SLOTS` text slots, all non-empty values assembled into one string). Unlike `try_` (a fallback chain — first non-empty wins), join combines **every** slot, so its preview lists all configured slot fields rather than describing a chain. Built by `bws_build_join_preview_label()`.

**Slot walk** matches `bws_join_callback()`: a slot is "real" iff it has a `key` OR a non-default `use`; `src`/`ref` carry forward (`same`/'' takes the prior resolved source), `key`/`use` never do. Each real slot contributes a quoted key (`'name_first'`) or `Title`; a non-current source is appended per-slot (` from Ref 'rel'`).

**Assembly annotation.** The `Join` prefix leads. Then:

| Mode | Shape |
|---|---|
| Separator, default `', '` | `[Join {field list}]` (default separator unremarkable — not shown) |
| Separator, custom separator | `[Join {field list} (sep: “X”)]` (read from the `valueSep` wire key, renamed from `sep` in 1.16.0/FW-52; the annotation label word is a build-session decision — see the join-sep-rename handoff) |
| Template (`format` set) | `[Join “{substituted format}”]` — the format quoted with each `%N` replaced by its slot's field part (source annotation inline: `'full_name' from Ref 'student'`); an unbound `%N` stays literal (visible mistake, matches render); `%%` and `~…~` group delimiters shown as typed |

**Warnings** replace the field list, prefixed `⚠ Join:`, joined with `, `:

| Condition | Warning fragment |
|---|---|
| `src:ref` slot, no `ref` | `<L> no ref` |
| key-mode slot, no `key` | `<L> no key` |
| Template mode, no `format` | `no format set` |
| Slot with an INCOMPLETE `terms` step (no taxonomy) | `<L> no taxonomy` (1.17.0) |
| Slot with an INCOMPLETE `refs` step (no relationship field, and nothing carried to inherit one from) | `<L> no ref` (1.17.0) |
| Slot with an INCOMPLETE `rows` step (no repeater field) | `<L> no repeater field` (1.17.0) |
| Slot whose source is `same` with no earlier slot resolved | `<L> no previous source` (1.17.0) |

### Slots are named by LETTER, and details collapse when slots disagree

`<L>` above is the slot's **letter** — `A`, `B`, `C`, … — on BOTH multislot brackets. **`slot N`
retired in 1.17.0** ([#105](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/105)):
the letter is the wire key the author reads in `A:src(…)` and the header the control configured the
slot under, so a number was a third spelling of one thing. No container NOUN either (`Field A` /
`Attempt B` as the control headers say) — the bracket prefix already says `⚠ Join:` / `⚠ Try:`, and a
noun would be the second time one label names the container. `{{table}}` takes the same letters-only
form and gets another pass when its columns convert to folded slots.

The **collapse rule** — list every slot with a problem, keep the detail only while there is ONE
problem to describe. Owner: `bws_slot_warnings_text()`.

| Shape | Preview |
|---|---|
| One slot | `[⚠ Try: B no taxonomy]` |
| Several slots, one distinct issue | `[⚠ Join: A, C no key]` |
| Several slots, two or more distinct issues | `[⚠ Try: A, B, C misconfigured]` |
| Tag-level warning beside slot warnings | `[⚠ Join: A, C no key, no format set]` |

Detail survives only while there is one act to name; spelling out disagreeing details reads as a
wall on a five-slot tag, and the author opens the slots either way. Letters are listed in WIRE
order, not the order the walk raised them (skips come from the walk pass, per-slot gaps from a
second pass over the resolved slots).

**Tag-level warnings** (join's `no format set`) append unchanged and never count toward the
distinct-detail test. The rule is about slots disagreeing with each other, and a one-press fix
should not cost the author the other diagnosis.

The rule applies to EVERY slot warning, old and new. Splitting it would give one bracket two
grammars.

**`slot N source not supported` was RETIRED in 1.17.0** ([#104](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/104)),
and it is worth knowing why rather than merely that. It flagged a slot whose source chain had no
flat spelling — a SECOND step on one axis (`src(refs,a;refs,b)`), or a repeater `rows` step —
because the render seam re-spelled every slot as a flat `src`/`ref`/`srcTermIn` triple and refused
what it could not hold. The seam hands the whole chain on now, so such a slot RESOLVES and its
source is named the way any other slot's is, one segment per step. The wording is gone from the
plugin: an author who saw it before 1.17.0 and comes back to the same tag now sees the source
described instead.

What that did NOT retire is the general shape of the signal. A skipped slot and a slot that resolves
to nothing both print nothing, so the preview stays the only place an author can see which happened —
which is why the four reasons below still speak. FIVE skip reasons remain — four survived the seam
change and `no repeater field` arrived with it — but only FOUR of the five SPEAK, so a count has to
say WHICH: a "four" is stale where it counts REASONS and right where it counts what the preview
PRINTS. Each comes from `bws_fold_slot_chain_options()`'s `$skip_reason` out-param, never from a
second copy of the skip rule in the preview. An UNCONFIGURED slot (no read yet, combining container)
is the silent fifth: that is a normal in-progress state, and flagging it would fire on every
half-built join.

**`<L> no taxonomy`**, **`<L> no ref`** and **`<L> no repeater field`** name an INCOMPLETE step, and are separate from
"unsupported" because they name what is MISSING rather than what cannot be expressed. Such a step is
expressible — it just is not finished — and the seam skips it deliberately: an empty argument is how
"no step" is spelled, so flattening it would make the slot read the UN-STEPPED entity and return a
plausible WRONG value rather than nothing. The slot's own control warns in parallel ("This
*&lt;noun&gt;* will be skipped unless a field/taxonomy is set"), so the two surfaces agree on the
promise.

One asymmetry between the two, and it is the reason the seam reports the step's SLUG rather than a
bare `step`: an argless `refs` step is COMPLETE when the carry supplies its field (`3-src:ref` with
no `3-ref` steps through an earlier slot's relationship), so it is flagged only when nothing was ever
carried. An argless `terms` step has no such inheritance and is always unfinished. Deriving which
noun to print from the slot's chain would be a second copy of the skip rule, which is what routing
both previews through the seam exists to prevent.

**`<L> no previous source`** covers a `same` root with nothing to be the same AS — every earlier
slot skipped, so there is no carried source. Kept apart from the incomplete-step reasons because
what is missing is not in THIS slot: the author-facing answer is finish an earlier slot, not finish
this one. Reachable in a COMBINING container, where an unconfigured read skips a slot
without feeding the carry.

A SKIPPED slot reports its skip reason ALONE — the per-slot warnings above (`no key`, `no ref` for a
resolved slot) are not also emitted for it. The slot will not read anything, so its field key is not
what the author should go and fix.

Nothing configured at all → **no preview** (empty string; GB shows its own placeholder). Trailing `(fallback: “X”)` appended whenever `fallback` is set.

| Tag config | Preview |
|---|---|
| `{{join key:name_first\|2-key:name_last}}` | `[Join 'name_first', 'name_last']` |
| `{{join key:name_first\|2-key:name_last\|valueSep: }}` | `[Join 'name_first', 'name_last' (sep: “ ”)]` |
| `{{join use:title\|2-key:role}}` | `[Join Title, 'role']` |
| `{{join mode:template\|format:%1 (%2)\|key:name_first\|2-key:name_last}}` | `[Join “'name_first' ('name_last')”]` |
| `{{join mode:template\|format:%1 / %2\|src:ref\|ref:student\|key:full_name\|2-src:current\|2-key:role}}` | `[Join “'full_name' from Ref 'student' / 'role'”]` |
| `{{join key:name_first\|2-src:ref\|2-ref:rel_post\|2-key:role}}` | `[Join 'name_first', 'role' from Ref 'rel_post']` |
| `{{join mode:template\|key:name_first}}` | `[⚠ Join: no format set]` |
| `{{join src:ref\|key:name_first}}` | `[⚠ Join: A no ref]` |
| `{{join key:name_first\|2-key:name_last\|fallback:—}}` | `[Join 'name_first', 'name_last' (fallback: “—”)]` |

The rows above are **legacy flat wire** (`2-key`), which is why their format tokens are `%1`. The same tags in FOLDED form preview identically — both preview builders walk the same seam the renderer does, and the seam dual-reads eras:

| Tag config (folded) | Preview |
|---|---|
| `{{join A:key(name_first)\|B:key(name_last)}}` | `[Join 'name_first', 'name_last']` |
| `{{join mode:template\|format:%A (%B)\|A:key(name_first)\|B:key(name_last)}}` | `[Join “'name_first' ('name_last')”]` |
| `{{join mode:template\|format:%1 (%2)\|A:key(name_first)\|B:key(name_last)}}` | same — the DIGIT token spelling is read forever, on folded wire too |
| `{{join mode:template\|format:%A %%B %K\|A:key(name_first)}}` | `[Join “'name_first' %%B %K”]` — `%%` shows as typed, and a letter past the container's slot maximum is not a token |

## `{{call}}` preview — intentionally inert (does NOT execute the function)

`{{call}}` (1.12.0) is the **deliberate exception** to the plugin's normal value-preview behavior. Most tags resolve a real value in the editor preview (outside template context). `{{call}}` does **NOT** — it never runs the allowlisted function to build its preview. This is a **safety refusal**, not an oversight:

1. Allowlisted functions are vetted for `isInternal`-safety, **not** purity / idempotency — running them on every editor load / keystroke is unacceptable (a function may write, send mail, hit an API).
2. The loop-correct post id does not exist at editor time, so a run would resolve against the wrong (or no) post and mislead anyway.

So the preview is **config-describing only**, built by the `call` branch in `bws_build_preview_label`:

| Tag config | Preview |
|---|---|
| `{{call fn:my_fn}}` | `[Function: my_fn]` |
| `{{call fn:my_fn\|arg:short}}` | `[Function: my_fn (short)]` |
| `{{call src:ref\|ref:games\|fn:my_fn}}` | `[Function: my_fn from Ref 'games']` |
| `{{call}}` (no `fn`) | `[⚠ No function set]` |

`(arg)` is appended when `arg` is set; the `from <source>` segment reuses the shared context-part machinery (`Current` / `Ref '…'`). A missing `fn` is the bucket-A drift warning ([tag-reference.md §Call tag] failure taxonomy). The live allowlist-membership warning is client-side (the allowlist is JS-available); this PHP path catches the empty-`fn` case. **A contributor must not "fix" this to match other tags by executing the function — the inert behavior is intentional.**

## Tests

Non-datetime label assembly is pinned by a standalone harness — **no WordPress, no PHPUnit**:

```
php tools/test/preview-label-test.php
```

**Run it after any change to `preview-helpers.php` or to a label shape in this doc.** It asserts `bws_build_preview_label`, `bws_build_try_preview_label`, `bws_build_join_preview_label`, and the sub-builders against the marker/assembly rules above. Datetime templates are excluded (live clock + `wp_date` → non-deterministic).

Behaviors the harness locks in (correct-by-design, easy to regress):

- **`→` step arrow is positional** — emitted only when the term-step segment *follows* another (modifier label or `Ref 'x'`). Standalone current-post→term drops it: `['sku' from Event Category Term]`, not `… from → …`.
- **Slot ≥2 key-only override is discarded** — an empty `use` on slot N≥2 wipes that slot's `key` (the `use:same` UI hides the key field). A key override only registers when its `N-use` is also sent.
- **`text` try "no slots configured" is unreachable** — slot 1 is always default-filled, so a misconfigured slot-1 trips the missing-key warning first.
