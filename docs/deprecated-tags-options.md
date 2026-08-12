# BWS Dynamic Tags — Deprecated Tag Names and Option Renames

This document is a **migration reference**. It records the N×M per-source tag names that were
replaced by the source-agnostic base tag architecture in v1.6.0, along with all template key
renames and option renames that drove the deprecated wrapper registrations.

See [`tag-reference.md`](tag-reference.md) for the current (v1.6.0+) architecture, and
[`gb-constraints.md`](gb-constraints.md) for the GB editor/runtime constraints that
have forced revisions to several approved renames listed below.

---

## Notation

Used across the former-matrix tables in this document.

| Symbol | Meaning |
|--------|---------|
| ✅ | Generated, **enabled** by default |
| ☐ | Generated, **opt-in** (disabled by default) |
| — | Not applicable — template context type did not match source |
| GB | Not generated — GB (or GB Pro) already registered this tag name; skipped by collision check |
| ★ | Was planned but never implemented |

Historical collision-check behavior: tags whose generated name matched GB built-ins (`post_title`, `post_excerpt`, `post_permalink`) were silently skipped at registration time. The check queried `GenerateBlocks_Register_Dynamic_Tag::get_tags()` dynamically, so any tag already registered by GB or another plugin was automatically avoided. The N×M generation loop was removed in v1.6.0; only deprecated-wrapper registrations remain.

---

## Former Tag Matrix — Post-context sources

> **Note (v1.6.0+):** These source prefixes are now **deprecated wrapper registrations** only.
> Base tags (`text`, `image`, `content`, etc.) handle all sources via the `src` option.
> All wrappers are registered in `includes/tags/deprecated-tags.php`.

The row label is the **template key**. The full tag name is `{prefix}{template_key}`,
e.g. `related_post_custom_text` = `related_post_` + `custom_text`.

| Template | Supports | Options | `post_` | `related_post_` | `second_related_post_` | `post_term_related_post_` | `portal_` |
|---|---|---|---|---|---|---|---|
| **title** | `link`, `source` | — | GB | ✅ | ✅ | ✅ | ☐ |
| **content** | `source` | `use`, `key`, `fallback` | ✅ | ✅ | ✅ | ✅ | ☐ |
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

† `excerpt` folds into `content use:excerpt`. `featured_image` and `custom_image` fold into `image` (`use:featured` / unset). See [Template key renaming tracker](#template-key-renaming-tracker) below.

---

## Former Tag Matrix — Term-context sources

| Template | Supports | Options | `term_` | `term_related_post_` |
|---|---|---|---|---|
| **title** | `link`, `source` | — | ✅ | ✅ |
| **content** | `source` | `use`, `key`, `fallback` | — | ✅ |
| **excerpt** † | `source` | — | — | ✅ |
| **permalink** | `source` | — | ✅ | ✅ |
| **description** † | — | — | ✅ | — |
| **custom_text** | `meta`, `link`, `source` | `key`, `fallback` | ✅ | ✅ |
| **featured_image** † | `image-size` | `as` | — | ✅ |
| **custom_image** † | `image-size` | `key`, `fallback`, `as` | ✅ | ✅ |
| **datetime_single** | `source` | `key`, `time_key`, `as`, `format`, `show_current_year`, `show_midnight`, `time_sep`, `fallback` | ☐ | ☐ |
| **datetime_range** | `source` | `start_key`, `end_key`, `start_time_key`, `end_time_key`, `as`, `format`, `show_current_year`, `show_midnight`, `range_sep`, `time_sep`, `sep`, `fallback` | ☐ | ☐ |

`term_*` (term-extraction) templates are not listed — they extract terms FROM a post and have no meaning in a term-context source.

† `excerpt` folds into `content use:excerpt`. `featured_image` and `custom_image` fold into `image`. `description` folds into `content` (term-context, `use` unset outputs term description). See [Template key renaming tracker](#template-key-renaming-tracker) below.

The ☐ cells reflect the former per-source opt-in defaults, preserved here as documentation of which deprecated wrappers were historically opt-in.

---

## Template key renaming tracker

Records planned template key renames and consolidations. When a template key changes, the generated
tag names for all sources change with it. Deprecated wrappers are registered via `DeprecatedTagRegistry`
for each old tag name and appear in the editor picker under the "Deprecated" group.

In the source-agnostic architecture, the "New tag" column shows the single registered base tag.
Each per-source old tag becomes a deprecated wrapper with `source_inject` set to the source's `src`
value and `option_renames` as listed below.

| Current key | New key | Old tag example | New tag (base) | `source_inject` | `option_renames` | Status | Notes |
|---|---|---|---|---|---|---|---|
| `excerpt` | *(folded into `content`)* | `post_excerpt` | `{{content use:excerpt}}` | source abbrev. | none; `fixed_options: ['use' => 'excerpt']` | Approved | `use:excerpt` added as third value on `content`'s `use` option. `bws_post_excerpt_core()` retained as internal function. GB's `post_excerpt` is not a conflict (never registered by us). |
| `featured_image` | *(folded into `image`)* | `post_featured_image` | `{{image use:featured}}` | source abbrev. | `as → as` (no rename); `fixed_options: ['use' => 'featured']` | Approved | Deprecated wrappers inject `use:featured` — unset now means ACF/meta field (custom image). `image-size` support carried over. |
| `custom_text` | `text` | `post_custom_text` | `{{text}}` | source abbrev. | `fallback_text → fallback`, `type → use` | Approved | Removes `meta` from supports; own `key` input replaces GB pass-through. |
| `custom_image` | *(folded into `image`)* | `post_custom_image` | `{{image key:…}}` | source abbrev. | `fallback_url → fallback`, `return_type → as`, `field_key → key` | Approved | No `fixed_options` or `type → use` rename needed — `custom_image` never had a `type` option, and `use` unset is now the ACF/meta field default. Term-source `image` variants also default to ACF/meta field — no featured image concept applies to terms. |
| `custom_date_single` | `datetime_single` | `post_custom_date_single` | `{{datetime_single as:date}}` | source abbrev. | see §Date-time option names; `fixed_options: ['as' => 'date']` | Approved | Merged with datetime_single via `as` option. |
| `custom_date_range` | `datetime_range` | `post_custom_date_range` | `{{datetime_range as:date}}` | source abbrev. | see §Date-time option names; `fixed_options: ['as' => 'date']` | Approved | Merged with datetime_range via `as` option. |
| `custom_datetime_single` | *(merged into `datetime_single`)* | `post_custom_datetime_single` | `{{datetime_single}}` | source abbrev. | see §Date-time option names | Approved | Default mode (unset) = datetime. |
| `custom_datetime_range` | *(merged into `datetime_range`)* | `post_custom_datetime_range` | `{{datetime_range}}` | source abbrev. | see §Date-time option names | Approved | |
| `description` | *(folded into `content`)* | `term_description` | `{{content}}` | source abbrev. | none | Approved | Term-context only — no post-context variant exists. `content` with `use` unset for a term entity outputs term description; no `fixed_options` needed. `bws_term_description_core()` retained as internal function. |

---

## Option name renaming tracker

Tracks renames from the naming pass. Status values: **Approved** (decision made, not yet
implemented), **Implemented** (already applied to current files — deprecated wrapper still needed for
migrating saved tags), **Superseded** (replaced by later rename due to discovered constraint — see
[`gb-constraints.md`](gb-constraints.md)), **Under consideration** (needs more research or discussion),
**Pending** (not yet looked at).

Scope notation: `[image]` = image tags only; no scope = applies to all templates that have the option.

| Current name | Proposed name | Scope | Status | Notes |
|---|---|---|---|---|
| `fallback_text` | `fallback` | all text templates | Implemented | Aligns with GB Pro `loop_item`; shorter; no per-tag conflict. **Completed 1.16.0** — the active read path is gone: cores read `fallback` directly, both reverse-mappers (`bws_base_map_options()`, the datetime normalizer's fallback leg) deleted, and `{{join}}`'s registration corrected (it shipped 1.15.0 registering the legacy key — renamed clean, no migration entry, one day old and unused). `fallback_text` now survives ONLY as an inbound key in the `deprecated-tags.php` rename tables plus two read shims (`class-tag-template-registry.php`, `preview-helpers.php`). |
| `fallback_url` | `fallback` | image templates | Approved | Same key as `fallback_text` rename; no template has both simultaneously |
| `type` (field/mode selector) | `use` | content, text, image | Approved | Each tag defaults to its primary source (unset = default); `use` only appears when overriding. `content`: unset=post content/term description, `use:excerpt`, `use:key` (ACF WYSIWYG/content-area field, incl. ACF Extended block editor areas). `text`: unset=ACF/meta field (uses `key`), `use:title`. `image` (post sources): see table below. Note: `use:key` is never written on `text` or `image` (post sources) since ACF/meta field is the unset default for both. Current PHP name is `type`; `field` and `in` were doc-only intermediates, never in PHP. Shim: `$options['from'] ?? $options['type'] ?? ''`. |
| value `custom_field` | `key` | `use` option | Approved | Selects the ACF/meta field mode: "use the field named in `key`". Supersedes intermediate rename `custom_field` → `meta` (approved but never implemented). Not applicable to `text` (ACF/meta field is its unset default). |
| `taxonomy` | `tax` | term extraction | Approved | Aligns with GB's `tax` (used by `term_list`); consistency reduces risk if GB ever registers conflicting tag names |
| `via`/`from` (traversal selector) | `source` | all templates | Superseded | `source` is a GB-reserved option key — destructured out of `extraParams` before custom controls receive it (see [`gb-constraints.md`](gb-constraints.md) Reserved Option Keys). Replaced by `src`; see row below. |
| `via`/`from` (traversal selector) | `src` | all templates | Implemented | Final name after `source` rejected (GB reserved). Custom JS control. Registered as option migration so saved tags using older names round-trip. |
| `via:tax` traversal value | `srcTerm` (boolean) | all templates | Superseded | Pair `srcTerm` (bool) + `tax` (slug) collapsed into single `srcTermIn` slug on cross-source base tags after `tax` reserved-key behavior discovered (re-emits only on `'term'` type or `tagSupportsTaxonomy`; silently dropped on cross-source base tags like `text`, `image`). See row below. |
| `srcTerm` + `tax` pair | `srcTermIn` (slug) | cross-source base tags (e.g. `text`, `image` with `gb_type:'cross-source'`) | Implemented | Single non-reserved key encodes both signals: slug = enabled + slug, empty = disabled. Implemented via `bws-term-hop` custom control type (CheckboxControl + ComboboxControl). Avoids GB dropping `tax` on modal reopen. |
| `rel` | `ref` | `src:ref` traversals (deprecated `related_post_*`, `term_related_post_*`, `post_term_related_post_*`) | Implemented | Reference/relational field key — the option that stores the field name used to traverse. Renamed from `rel` for vendor-agnostic clarity (2026-04-13). **Completed 1.17.0 ([#56](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/56))** — the last readers of `rel` were the four related-post source classes, and they are now inert, so no runtime shim survives: `ref` is the only spelling anything honours. `rel` remains an inbound key in the `deprecated-tags.php` rename tables, whose repair coverage widened in the same release from the seven base tags to the `term_` family (derived from the registered templates) and the `try_` family (per-slot) — all three via one `transform_callback`, `bws_migrate_rel_to_ref()`, which applies the src-decides rule ([#57](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/57): the earlier declarative rename let an inert `rel` overwrite a live `ref`) and defers `src:related_post` slots whole to the token's own entry ([#73](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/73)). A renamed `ref` is always emitted with its matching `src:ref` — without one the fold, running in the same cascade, maps it to no step and strips it. Not covered: an external modifier prefix (`view_`), which registers after option migrations; and `{{join}}`/`{{table}}`/`{{call}}`/`{{email}}`/`{{phone}}`, which all postdate the rename. |
| `rel` | `ref1` | `second_related_post_*` deprecated wrappers | Approved (never implemented) | First-hop reference field key. Numeric counter because `ref` type repeats. Supersedes intermediate rename `1st_rel`. **Moot as of 1.17.0** — `SecondRelatedPost::resolve_id()` is inert (#56), so nothing reads `rel`/`rel_2` at RENDER under either spelling. The rename was never needed: a second-degree hop is a two-step chain, not a numbered option pair. **But the legacy spellings are now READ ONCE, at migration**: `second_related_post_*` gained a target in 1.17.0 (`src:refs,<rel>;refs,<rel_2>`), so the converter reads `rel`/`rel_2` to build the chain and then drops them. `ref1`/`ref2` were never written to wire and are not read. |
| `rel_2` | `ref2` | `second_related_post_*` deprecated wrappers | Approved (never implemented) | Second-hop reference field key. Supersedes intermediate rename `2nd_rel`. See `ref1` row. |
| value `related_post` (source token) | value `ref` | `src` / `N-src` on any tag | Implemented (v1.17.0) | The `related_post` SOURCE token resolved through `RelatedPost::resolve_id()`, which read the relationship field from `rel` (or legacy `key`) instead of `ref` — the disjoint-vocabulary half of [#56](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/56). Migrated by `bws_migrate_related_post_src()`: `src:ref` plus `ref` taken from `rel` (moved) or from `key` (**copied**, because the field read consumed the same key downstream). Where a tag carries both `ref` and `rel`, the **`src` decides which one wins, not a ranking of the keys** — each is live under exactly one token and inert under the other, so under `related_post` the `rel` overwrites the inert `ref`, while under `src:ref` the `ref` stands. Registered VALUE-gated (`match_option_values`), before the fold entry. The editor's mount path cannot apply the rewrite (no entry chain, and `rel` is not a fold axis), so both halves of the fold migrator DECLINE a slot naming any of the four retired tokens — not folded, not stripped — leaving the converter able to reach it. Sibling value `related` is deliberately NOT migrated — no source ever registered under it, so it already resolves the ambient entity and a rewrite would change output. |
| pair `src:ref` + `ref:<field>` | step `refs,<field>` inside the `src` value | `src` / `N-src` and their `ref` siblings, on any tag | Implemented (v1.17.0) | **Not a rename — a SHAPE change.** A source used to be one token plus sidecar option keys; it is now an ordered chain (a root plus fanning steps), so the relationship moves INTO the `src` value as a step. The `ref` key is retained as a READ forever: `bws_fold_chain_from_options()` builds a `refs` step from a flat `src:ref` + `ref:<field>` pair, so unmigrated and hand-written flat wire keeps rendering (ADR 0004). Rewritten by the converter entry `bws_migrate_base_src_chain` (base tags) and by both halves of the fold migrator (slots), which strip the flat keys once the value carries them — removing the OPTION does not remove a stored VALUE, so the strip is what makes the source stated in one place. **The flat SPELLING is closed to new authoring; the flat READ has no deprecation path** — see ADR 0005 for the same distinction drawn about `limit`. |
| `srcTermIn` (slug) | step `terms,<tax>` inside the `src` value | `srcTermIn` / `N-srcTermIn`, on any tag | Implemented (v1.17.0) | **Second rename for this key** (`srcTerm`+`tax` → `srcTermIn` → a chain step), and a shape change like the `refs` row above, so a migrator must handle both eras. A legacy `srcTermIn` is APPENDED to a chain that has no `terms` step of its own — it is a separate option KEY describing a step, so dropping it would lose a configured step, and appending it beside an existing one would double-hop. **A SITE root never takes the legacy term step:** the pair is hand-edit-only (`srcTermIn` registers `show_if src: not:site`) and every arm has always let the site read win, so appending there would flip a stored tag from "the site value" to empty. The `bws-term-hop` control that authored the flat key still ships for the families that never took a chain source (`term_`, `view_`, `{{table}}`) — tracked as FW-67. |

### Link option renames

| Old name(s) | New name(s) | Scope | Status | Notes |
|---|---|---|---|---|
| `link` (GB-native, via `supports:['link']`) | `linkTo`, `linkKey` | `title`, `custom_text`, `term_title` columns in N×M tables (where `link` was in supports) | Implemented (v1.7.0) | GB-native `link` option format: `link:post` → `linkTo:permalink`; `link:post_meta,<key>` → `linkTo:key\|linkKey:<key>`; `link:term` → `linkTo:permalink`; `link:author_archive`, `link:author_meta`, `link:author_email`, `link:comments` → dropped (no equivalent). Handled by `bws_map_gb_link_option()` via `gb_link_remap` flag on 6 deprecated entries: `related_post_title`, `related_post_custom_text`, `post_term_title`, `post_term_custom_text`, `term_related_post_title`, `term_related_post_custom_text`. |
| `link_to`, `link_field`, `new_window` | `linkTo`, `linkKey`, `newTab` | `related_post_content` deprecated tag only | Implemented (v1.7.0) | Custom link options on `related_post_content`. `link_to:post` → `linkTo:permalink`; `link_to:custom` + `link_field:<key>` → `linkTo:key\|linkKey:<key>`; `new_window` presence → `newTab` bare key; `link_to:none` or absent → all dropped. Content/excerpt migration targets (`post_content`, `post_excerpt`) drop link options (content tag excluded from link wrap). Handled by `transform_callback` on the `related_post_content` deprecated entry. |

### Multi-source–specific option names

| Current name | Proposed name | Scope | Status | Notes |
|---|---|---|---|---|
| `src_N` (try_ slots) | slot 1: `src`; slot N>1: `N-src` | Multi-source templates | Approved | Revised from approved `sN-via`. Slot 1: unprefixed `src` (custom control, aligns with base tag). Slots 2+: numeric prefix (e.g. `2-src`, `3-src`). Shim slot 1: `$options['src'] ?? $options['via'] ?? $options['s1-via'] ?? $options['src_1'] ?? ''`; shim slot N>1: `$options["{$n}-src"] ?? $options["{$n}-via"] ?? $options["s{$n}-via"] ?? $options["src_{$n}"] ?? ''`. (`sN-from` and `from_N` were doc-only names, never in PHP.) |
| `type_N` (try_ slots) | slot 1: `use`; slot N>1: `N-use` | Multi-source templates | Approved | Revised from approved `sN-from`. Mirrors `src` revision: slot 1 unprefixed, slots 2+ numeric prefix (e.g. `2-use`, `3-use`). New option — `type_N`, `field_N`, `in_N`, `sN-field` were all doc-only names, never in PHP. No shim needed. |

### Folded slot wire + source-chain vocabulary (FW-56/57, 1.17.0)

The fold replaced a multislot container's per-slot option KEYS with one key per slot, and replaced the
flat source triple with an ordered CHAIN inside that value. Both halves are vocabulary changes, so
they belong here — but note the unusual status column: **the slot keys are migrated in two places
(the `post_content` converter and the tag-modal mount), while the base-tag chain spelling resolves
without being migrated at all**, because nothing authors it yet. See
[`tag-reference.md` §Folded slot wire](tag-reference.md#folded-slot-wire-multislot-containers) for the
grammar and `.claude/plans/src-chain-encoding.md` for the decisions.

| Current name | Proposed name | Scope | Status | Notes |
|---|---|---|---|---|
| `{N}-src`, `{N}-ref`, `{N}-srcTermIn`, `{N}-use`, `{N}-key`, `{N}-limit` (six per-slot axes; unprefixed for slot 1) | ONE folded key per slot, spelled as a CAPITAL spreadsheet ordinal (`A`, `B`, … `Z`, `AA`), whose VALUE carries the slot | `{{join}}`, the nine `try_*` tags; `{{table}}` arrives folded | Implemented (1.17.0) | A tag is folded **iff any CAPITAL key is present** (`bws_slot_ordinal_num()` decodes; the flat `{N}-` sibling prefixes above stay DIGITS, since that wire was written with digits) — forms do not mix per key, though they DO mix per slot (the renderer dual-reads, so a folded slot 1 beside a flat slot 2 resolves). Migrated by BOTH paths: `MigrationRegistry` for stored `post_content`, and an invisible editor control on tag-modal mount, because a block widget is unreachable by the scanner and an unopened draft is unreachable by the mount. A container's TAG-level axes are excluded at every slot position (`limit` on a `try_` list template, `key` on `try_datetime_*`) — folding one of those would swallow the option the resolver reads. |
| `src:ref` + `ref:<field>` | chain step `refs,<field>` | folded slot values; depth-0 `src:` on base tags | Implemented in slots; base-tag migration lands 1.17.0, gated on FW-63 | Plural slug because a relationship step FANS — the singular/plural split is what makes a chain's leading token decidable as an entity ROOT (`site`, `current`, a registry source) versus a fanning STEP, from the slug alone. **The plural spelling is a CATEGORY marker, not a count claim:** a fanning step routinely resolves exactly one (a relationship field limited to 1). An ARGLESS `refs` keeps the carried relationship field rather than blanking it (shipped `$last_ref` behaviour). The base-tag migration waits on render ARMS dispatching by the chain's **resolved-source kind** rather than on the flat `src`/`srcTermIn` tokens (FW-63) — until then chain wire on a base tag can read a *different* entity, so migrating tags onto it would corrupt them (`tools/test/fold-test-matrix.md` §F9 measures all four divergences). |
| `srcTermIn:<tax>` | chain step `terms,<tax>` | same | same | Same plural rule (a term step fans). The slug is `sanitize_key`-normalized at compile. A flat `srcTermIn` sitting BESIDE chain wire APPENDS a step; a chain that already steps to terms WINS, so the pair never adds the same step twice. |
| *(no predecessor)* | chain step `entries,<field>` | `{{table}}` (step 6); legal wire elsewhere | New vocabulary (1.17.0) | Promotes a repeater to a traversal step. No flat spelling exists, so a `try_`/join slot holding one is SKIPPED rather than partly resolved, and the editor preview flags it `slot N source not supported`. |
| slot-level `limit:<n>` | per-step `limit[<n>]` inside `src(...)` when the slot has a fanning step | folded slot values | Implemented (1.17.0) | Two different quantities that used to share one key: a **step `limit`** bounds one fanning step's spread **per input source**, while the **tag-level `limit`** bounds the resolved-source list once, before the read. Neither bounds values — the read is 1:1, so there is no "value list" to bound. With NO fanning step the token stays slot-level (there is nothing to attach it to, and it has exactly one meaning there). `0`/`-1` mean unlimited, and a step carries `limit` only when it actually BOUNDS the step — absent is how the engine spells no limit. |
| `%1`…`%10` (Format template tokens) | `%A`…`%J` — the slot's own ordinal | `{{join}}` template mode | Implemented (1.17.0); **the digit read is retained PERMANENTLY, not deprecated** | A token was never an independent alphabet: `%N` is the slot ordinal spelled for a `}`-free wire (GB's parser rejects `}`), so it FOLLOWS the key spelling and an author reads `A:` beside `%A`. Both alphabets collapse to one internal `{N}` at a single translation point (`bws_join_wire_format`), which is what makes the move migration-free — every stored `format:%1 (%2)` keeps rendering, mixed formats resolve, and hand-pasted wire stays renderable (ADR 0004). **One behaviour change needed a converter entry:** a `%` before a capital A-J used to pass through untouched, so `10%APR` was legal stored wire whose meaning changes once letters tokenize. `bws_migrate_join_format_escape()` rewrites those to `%%APR`, **gated on wire ERA (no folded slot key ⇒ pre-letters), never on content** — literal-or-token is undecidable from the format string, and a content test destroys an INTENDED token, i.e. breaks working output instead of fixing broken output. That gate is also why the entry must register BEFORE the fold entry. |
| `sep` (folded-slot spelling) | *(held)* | folded slot values | Under consideration | Deferred with the five datetime keys; see the plan's OPEN table. The tag-level `sep`/`valueSep` names are unaffected. Already half-built and worth knowing before the rest lands: a slot `sep(...)` **parses into the struct's `opts` and round-trips through emit today** — ADR 0003 is enforced in ONE place, `bws_fold_slot_flat_options()`, which drops it on the way to the renderer in both modes. Shipping it is a seam change, not a wire change. |
| `sep` (join tag-level assembly) | `valueSep` | `{{join}}` only | Implemented 1.16.0 — **deliberately NOT migrated** | The rename itself is recorded in FW-52; this row exists for the migration decision. A stored `{{join …\|sep: / }}` from 1.15.x renders `a, b` instead of `a / b` (`bws_join_assemble` reads `valueSep` alone) and keeps an orphan `sep` token that no control displays — join registers no flat `sep` at any level, and `sep` is not GB-reserved, so it round-trips invisibly. **Not migrated because the exposure is three days** (v1.15.0/v1.15.1, 2026-07-20 → v1.16.0, 2026-07-23) on a tag that was three days old, which does not justify permanently claiming flat `sep` on join in the entry table. **NOT deferred over a collision with the per-slot `sep` above — there is none:** that one is an axis INSIDE a folded value (`A:…;sep( / )`), so `parse_tag_string` on a folded tag yields the key `A` and never `sep`. The two live in different namespaces. If a report of a mis-rendered 1.15.x join separator ever arrives, the fix is a one-line declarative entry (`option_renames => array( 'sep' => 'valueSep' )`, `match_tag` join), unambiguous today and blocked by nothing. |

### Image-specific option names

| Current name | Proposed name | Scope | Status | Notes |
|---|---|---|---|---|
| `return_type` | `as` | image templates | Implemented | Aligns with datetime `as` option (same role: selects output mode). Callbacks read `$options['as'] ?? $options['return_type']` for backward compat. |
| `field_key` | `key` | image templates | Implemented | `as` freed up `key` — field name now aligns with GB's own `post_meta`/`term_meta` convention. Callbacks read `$options['key'] ?? $options['field_key'] ?? $options['meta_key']`. |
| `type` (field/mode selector) | `use` | content, text, image | Approved | Each tag defaults to its primary source (unset = default); `use` only appears when overriding. `image` (post sources): unset=ACF/meta field (uses `key`), `use:featured`. `image` (term sources): `use` not offered — always ACF/meta field. Current PHP name is `type`; `field` and `in` were doc-only intermediates. Shim: `$options['from'] ?? $options['type'] ?? ''`. |

### Date-time–specific option names

| Current name | New name | Scope | Status | Notes |
|---|---|---|---|---|
| `omit_current_year` | `showCurrentYear` | date/datetime | Approved | Flip boolean: unset = omit current year (default smart behavior); presence = always show year. Fixes `default:true` serialization problem. Revised from `show_current_year` → camelCase to align with GB JS option convention. Shim: `$options['showCurrentYear'] ?? $options['show_current_year'] ?? ''`. Converter: if `omit_current_year` absent from old tag, inject `showCurrentYear` (bare key) to preserve "always show year" behavior; if present, drop it. |
| `separator` | `rangeSep` | datetime_range | Approved | Renamed to avoid collision with list-mode `sep` — `datetime_range` supports list mode, `sep` reserved for list separator. Revised from `range_sep` → camelCase. Shim: `$options['rangeSep'] ?? $options['range_sep'] ?? $options['separator'] ?? ''`. |
| `date_time_separator` | `timeSep` | datetime | Approved | Applies only when date and time assembled separately (separate field keys, or no combined-field format). Hidden when `format` set or `as` is `date` or `time`. Show condition: `{ format: 'empty', as: 'not_in:date,time' }`. Default if unset: `', '` (not serialized). Revised from `time_sep` → camelCase. Shim: `$options['timeSep'] ?? $options['time_sep'] ?? $options['date_time_separator'] ?? ''`. |
| `as` (new option) | — | date/datetime | Approved | Mode selector: unset = datetime (never serialized); `as:date`; `as:time`. Controls output filtering regardless of field format. Replaces `date_only` and `time_only` flags entirely. `as:date` condition in `show_if`: `as: 'not:date'`. |
| `date_time_field` | `key` | datetime_single | Approved | Primary field key; holds date, datetime, or time value per `as`. Consistent with all non-image templates. |
| `time_field` | `timeKey` | datetime_single | Approved | Secondary time field for when date+time split across separate meta fields; hidden when `as:date`. Revised from `time_key` → camelCase. Shim: `$options['timeKey'] ?? $options['time_key'] ?? $options['time_field'] ?? ''`. |
| `start_field` | `startKey` | datetime_range | Approved | Primary start field key. Revised from `start_key` → camelCase. Shim: `$options['startKey'] ?? $options['start_key'] ?? $options['start_field'] ?? ''`. |
| `end_field` | `endKey` | datetime_range | Approved | Primary end field key. Revised from `end_key` → camelCase. Shim: `$options['endKey'] ?? $options['end_key'] ?? $options['end_field'] ?? ''`. |
| `start_time_field` | `startTimeKey` | datetime_range | Approved | Secondary start time field; hidden when `as:date`. Revised from `start_time_key` → camelCase. Shim: `$options['startTimeKey'] ?? $options['start_time_key'] ?? $options['start_time_field'] ?? ''`. |
| `end_time_field` | `endTimeKey` | datetime_range | Approved | Secondary end time field; hidden when `as:date`. Revised from `end_time_key` → camelCase. Shim: `$options['endTimeKey'] ?? $options['end_time_key'] ?? $options['end_time_field'] ?? ''`. |
| `format_type` + `custom_format` | `format` | date/datetime | Approved | Single field: empty = auto (use field handler's return format, or WP date/time settings as fallback); non-empty = custom PHP date format string. Help text: "When unset, the format returned by the field handler is used; if unavailable, your WordPress date and time settings are used." Converter: if `format_type:custom`, rename `custom_format` → `format` and drop `format_type`; otherwise drop both. |
| `smart_time` | *(eliminated)* | datetime | Approved | AM/PM consolidation becomes always-on (ungated). Midnight suppression becomes `showMidnight` option. `smart_time` eliminated entirely. Converter: drop `smart_time`; if it was absent and tag would have produced time output, inject `showMidnight` (bare key) to preserve old "always show midnight" behavior. |
| `show_midnight` (new option) | — | datetime | Approved | Renamed to `showMidnight` — camelCase alignment. Unset = hide 00:00 times (default); presence = display midnight explicitly. Shown only when `as` not `date` (`show_if: { as: 'not:date' }`). |
| `date_only` | *(eliminated)* | datetime | Approved | Replaced by `as:date`. `custom_date_*` deprecated wrappers inject `as:date` via `fixed_options`. `custom_datetime_*` wrappers with `date_only` set inject `as:date` via converter. |
| `time_only` | *(eliminated)* | datetime | Approved | Replaced by `as:time`. Converter injects `as:time` when `time_only` is present. |

---

## Historical N×M source classes

Source classes used by the v1.5 N×M tag-generation model. In v1.6.0+ the N×M loop is gone; these classes back deprecated wrapper callbacks only. New base/modifier tags route through a smaller subset (see [`tag-reference.md`](tag-reference.md) §Source classes).

Notation:
- ☐ — wrapper opt-in by default in former matrix
- `Template − source` / `Template + source` — GB native `source` support modifier (entity-picker control); historical only

### Post-context sources

| Source key | Tag prefix | Traversal | Supports modifier | Registered by | Notes |
|---|---|---|---|---|---|
| `post` | `post_` | Current post (direct) | Template as-is | Built-in | |
| `related_post` | `related_post_` | Current post → related post (reference field on post) | Template − `source` | Built-in | Requires `ref` option |
| `second_related_post` | `second_related_post_` | Current post → related post → 2nd related post | Template − `source` | Built-in | Requires `ref1` + `ref2` (legacy: `rel` + `rel_2`). **Migrates to `src:refs,<rel>,limit(1);refs,<rel_2>,limit(1)` since 1.17.0** — the 15 entries carried no target for four releases because nothing reached a second relationship. Both steps carry a limit of 1: the old tag collapsed to the first post at every hop, and per-step limits multiply |
| `post_term_related_post` | `post_term_related_post_` | Current post → post's term (via `tax`) → term's related post (via `ref` on term). First term only. | Template − `source` | Built-in | Requires `tax` + `ref`. **Migrates to `src:terms,<tax>,limit(1);refs,<rel>,limit(1)` since 1.17.0** — the 10 entries carried no target for four releases because nothing reached a term-then-relationship chain. The chain does NOT collapse the term hop, so BOTH steps carry a limit of 1 to preserve the old first-term-only output: per-step limits are per-input and multiply, so bounding only the last would read one target per term |
| `portal` | `portal_` | Current post (portal context) | Template as-is | `bws-portal-system` | External; historical name — modifier prefix renamed `view_` in v1.6.0 |

### Term-context sources

| Source key | Tag prefix | Traversal | Supports modifier | Registered by | Notes |
|---|---|---|---|---|---|
| `term` | `term_` | Current term (direct) | Template + `source` (always) | Built-in | Archive pages + term loops |
| `term_related_post` | `term_related_post_` | Current term → related post (reference field on term) | Template − `source` | Built-in | Requires `ref` option on the term entity |

> ⚠️ **`term_related_post_` vs `post_term_related_post_`:** Both involve a term's related post but start from different contexts. `term_related_post_` starts on an **archive or term loop page** (current term in scope). `post_term_related_post_` starts from a **current post**, resolves the post's term via `tax`, then hops to that term's related post via `ref` — a 3-hop traversal from post context.

---

## Historical required-options table (N×M wrappers)

| Template | Required option(s) | Notes |
|---|---|---|
| All `related_post_` variants | `ref` | Identifies which reference field to traverse |
| All `term_related_post_` variants | `ref` (on term entity) | Traverses from current term to related post |
| All `second_related_post_` variants | `ref1` + `ref2` (legacy `rel` + `rel_2`) | First hop then second hop |
| All `post_term_related_post_` variants | `tax` + `ref` (on term) | First term in taxonomy used; `ref` field on term, not post |
| `custom_text` (all sources) | `key` | Via GB's `meta` support (deprecated wrapper era) |
| `custom_image` (all sources) | `key` | |
| `term_*` extraction templates | `tax` | First term of taxonomy on resolved post |
| `term_custom_text`, `term_custom_image` | `tax` + `key` | |

---

## Historical list-mode applicability (N×M wrappers)

`limit` was applied to the final traversal step — terms for `term_*` extraction; related posts for traversal sources.

| Template | List mode | What was iterated |
|---|---|---|
| `title` (traversal sources) | ✅ | Related posts |
| `content` | ❌ | Long-form prose |
| `excerpt` (consolidated → `content use:excerpt`) | ❌ | Long-form prose |
| `permalink` | ❌ | Scalar URL |
| `description` (consolidated → `content`) | ❌ | Long-form prose |
| `custom_text` (traversal sources) | ✅ | Related posts |
| `featured_image` (consolidated → `image use:featured`) | ❌ | Scalar media |
| `custom_image` (consolidated → `image`) | ❌ | Scalar media |
| `datetime_single` (traversal sources) | ✅ | Related posts |
| `datetime_range` (traversal sources) | ✅ | Related posts |
| `term_title` (all sources) | ✅ | Terms in taxonomy |
| `term_permalink` | ❌ | Scalar URL |
| `term_description` (consolidated → `content` term-context) | ❌ | Long-form prose |
| `term_custom_text` (all sources) | ✅ | Terms in taxonomy |
| `term_custom_image` | ❌ | Scalar media |
