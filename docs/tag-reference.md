# BWS Dynamic Tags — Tag & Option Reference

**Authoritative living reference** for template keys, source keys, option names, and which dynamic tag variants exist. Also owns this plugin's response to GB constraints (default-strip strategy, custom editor controls). Update this file whenever sources, templates, options, or controls are added, removed, renamed, or change default-enabled status. Other docs cross-reference here rather than maintaining parallel tables.

See [`CLAUDE.md` §Documentation ownership](../CLAUDE.md#documentation-ownership) for the full doc ownership policy and update triggers.

**How this doc is organized.** Three parts, each a different reader-mode:

- **[Part I — Concepts](#part-i--concepts)** — read once. The vocabulary and design models that make the catalog legible: output shapes, the source model & `src` values & analog resolution, the site source, modifier prefixes, list mode, the default-strip/serialization strategy, default-enabled logic, custom editor controls, the option layout & visibility model.
- **[Part II — Catalog](#part-ii--catalog)** — browse daily. A per-tag section for every base tag (`text`/`content`/`title`/`permalink`/`image`/`datetime_*`/`email`/`phone`) — prose + that tag's own options + its panel order — plus the try_ chains. The options common to most tags are defined once in [§Shared option groups](#shared-option-groups); each per-tag section lists only what's tag-specific and links there.
- **[Part III — Trackers](#part-iii--trackers)** — read on change. Potential future templates; how to keep this document current.

---

# Part I — Concepts

*Read once. The model behind the catalog — vocabulary, source resolution, serialization. The Part II tables assume these.*

## Output shape — terminology

Four output shapes, deliberately distinguished (the word "scalar" is retired — it conflated "one result" with "one value"):

| Term | Shape | Plain meaning | Example |
|---|---|---|---|
| **single-result** | one result → one string | one result (the result may itself be a composite string); NOT list mode | `{{email}}` one address; `{{permalink}}` one URL |
| **composite string** | many fields → one string | different pieces combined into one piece | `datetime_range` → `Jan 1 – Jan 5`; phone+ext → `555-1234 ext. 200` |
| **list mode** | one field → many values → one joined string | many of the same thing, glued with `sep` | every email across a term's posts → `a@x, b@x` |
| **query loop** | many entities → repeated markup | a row/card per entity, each with its own fields | staff directory (photo+name+phone block per person) — **GB query-loop territory, NOT a dynamic tag** |

A **single-result** output can be a **composite string** (`datetime_range` is both: one result, built from start+end fields). These are independent axes — composite-vs-not describes *how the one string is built*; list-mode-vs-not describes *how many strings are joined*. `try_` is transparent to both (see [CONTEXT.md](../CONTEXT.md) I6); query loop is out of dynamic-tag scope entirely.

---

## Sources (v1.6.0+ architecture)

Source resolution is split between **`src` option values** (traversal within a base tag), **modifier prefixes** (context-shifting wrappers), and **source classes** (PHP entity resolvers behind both).

### `src` option values

Traversal selector on every base tag. Serializes as `src:<value>` in the tag string. This table is the **authoritative definition of what each `src` value resolves to**; the per-slot UI/serialization mechanics (slot-2+ `same`/`current` distinction, editor-preview segment, labels) live in [§Source group](#source-group).

| `src` value | Resolves to | Status |
|---|---|---|
| unset (default) | Current entity (post or term per template context) | Implemented |
| `ref` | Reference/relational field hop — requires `ref` sub-option (field key) | Implemented |
| `site` | Site-wide data (no entity) — an implicit-mode tag resolves the site analog, `key` reads an option. See [§Site Source](#site-source-srcsite). | Implemented (v1.9.0, Stage A) |
| `parent` | WP parent post/term | Planned |
| `ancestor` | WP top-level ancestor | To be considered |
| `child` | WP child posts/terms (list output) | To be considered |
| `sibling` | WP same-parent posts/terms (list output) | To be considered |

See [§Source group](#source-group) for label/UI details and the per-slot serialization mechanics.

### Source-analog resolution

**Design principle.** Each base tag at its **implicit mode** (no explicit `use`/`key` — the stripped per-template default, recovered via `?? '<canonical>'` on read) resolves to the **best intrinsic analog datum for the active source — where one exists**. A tag should "just work" per context; named `use:`/`key` are **explicit-mode** overrides and escape hatches, not the primary path.

*Mode terminology:* a `use`/`key` selection is **explicit** (written in the string), **implicit** (absent but recoverable — the stripped default, or a mode implied by a present `key`/`ref`; a selection IS in effect even though the selector's default isn't serialized), or **unset** (no choice and nothing to recover, e.g. no `src` → current entity). "Implicit" ≠ "unset": the panel always shows a default selection. Implicit mode resolves the analog only at **base / slot 1** — inside a try_ slot, the same wire-absence means *inherit* (the implicit-in-slot collision), not analog.

| Base tag | post | term | site |
|---|---|---|---|
| `title` | post title | term name | site name |
| `content` | post content | term description | *(none — site has no long-form body datum; the tagline is short, and has no `content`-tag path — see note)* |
| `permalink` | post URL | term URL | site home URL |
| `image` | featured image | *(none — terms have no native image; key required)* | site logo *(via explicit `use:featured` — see note)* |
| `text` | *(keyed — no intrinsic analog; key required in all contexts)* | | |
| `datetime_single` / `datetime_range` | *(field-keyed — no intrinsic analog; key/field required in all contexts)* | | |

Where a source has **no** intrinsic analog for a tag (term image, site content-body), the implicit-mode tag resolves empty and a `key`/field is required — the gap is honest, not papered over. (Site has no long-form content datum: its "Tagline" is a short string — WordPress itself frames it "In a few words…" — so it is *not* forced into the `content` slot. It also gets no dedicated `text` value, because it fails *both* sides of the gate — no unique affordance over GB's native `{{site_tagline}}`, and no strong cross-source analog (see the [qualifying test](#qualifying-test-for-new-use-values) below).) A *corollary*: a named `use:` value that would duplicate a datum already reachable elsewhere must not exist (e.g. no `use:home_url` when `permalink src:site` already = home URL). This keeps one canonical path per datum.

**Strip-default caveat.** The analog is reached by the tag's *stripped-default* `use` value, which is its **first enum value** — and that first value is **key-mode** for `text` and `image` (so their analog is NOT the empty-wire default). An empty `use` therefore resolves to key-mode for text/image (read a `key`), to `content` for `content` (which, under `src:site`, has no analog → empty). The site **logo** is the explicit `use:featured` value, *not* the implicit-mode `{{image src:site}}` (which is key-mode → empty without a key). `featured` is always serialized so the empty wire stays an unambiguous key-mode signal — there is no reliable way to tell a stale `key` from intentional key-mode if `featured` were the stripped default. Auto-unsetting the stale `key` when `use` leaves key-mode needs token authority that depends on the custom-control work (deferred — [docs/editor-controls.md](editor-controls.md) reserved owner). While that is unbuilt, **the stripped default is always key-mode**, named analogs are always explicit.

This principle governs `src:site` below and should guide every future source (`parent`, `ancestor`, external) and any new base tag.

#### Qualifying test for new `use:` values

Before adding a named `use:` value (or a per-source analog) for a new field target, it must clear this gate. A value that fails it is *noise* — it grows the enum, the label surface, and the per-source dispatch table without earning its place. Until **cross-token filtering** lands (the JS seam that shows only source-valid `use` entries — V10b/[#27](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/27)), every value hangs on the base tag in *every* source, so the cost is real.

**A new `use:` value qualifies if it satisfies *either* of two independent tests — reject only when it fails BOTH:**

1. **Uniqueness** — it offers an affordance no existing path gives: a datum unreachable elsewhere (e.g. an excerpt of a *related* post via `src:ref` — no native GB path), or a transform/traversal that adds value (the datetime format chain).
2. **Strong cross-source analog** — it fills the *same conceptual slot* as the tag's analogs in other sources, so the implicit-mode tag "just works" per context (`{{title}}` → post title / term name / site name; `{{content}}` → post content / term description). An analog can qualify **even if the datum is also reachable via `key` or a GB-native tag** — the value is the *consistent mental model*, not a path the user lacked. This is the design principle at the top of this section, restated as a gate.

| New value | Unique? | Strong analog? | Verdict |
|---|---|---|---|
| `content` `use:excerpt` (`src:ref`) | **Yes** — related-post excerpt has no native GB path | — | **keep** |
| `datetime_*` site field | **Yes** — format chain (custom → ACF return-format → site default) | — | **keep** |
| `title` site → site name | No — GB `{{site_title}}` exists | **Yes** — the title/name slot across post/term/site | **keep** |
| `text` `use:tagline` (site) | **No** — GB `{{site_tagline}}` covers it, nothing to format | **No** — site has no title/content-shaped slot the tagline fills; it's a one-off datum, not a cross-source parallel | **rejected** (fails both tests) |
| `src:site` on a **single-slot rooted modifier** (`term_*`, `view_*`) | **No** — the site datum is the *identical* read the unrooted base tag gives (`{{email src:site}}`); the term/view rooting is discarded | **No** — site is entity-blind, so it fills no term-/view-distinct slot; a rooting modifier exists to surface entity-distinct data | **rejected** — `site` is filtered from the modifier's `src` dropdown. *Likely future home for "pinned resource + site fallback" is a pinned-resource source (a probable `src:term,<ID>`-style construct — **not final**) inside a try_ chain (which keeps its site rung via `try_allow_site_slot`), NOT a `try_term_` form — `term_` is a transitional N×M surface on a deprecation glide-path (base tags + context-awareness + a pinned-resource source subsume it).* |
| `src:site` / `srcTermIn` on `{{call}}` | **No** — neither yields a post id, and a `$post_id`-contract function cannot consume a wp_options namespace or a term set | **No** — `{{call}}` exists to bind a POST for a post-shaped function; a non-post source fills no post-binding slot | **rejected** — `{{call}}` offers `src:current` + `src:ref` ONLY (both post-yielding). Same I4 gate applied at the **source** level rather than the `use:`-value level. Post-context-only is a stated design non-goal, not a gap to close. See [§Call tag](#call-tag). |

The tagline is the cautionary case because it fails **both** tests: the datum is reachable (GB native, or `key:blogdescription`), there is no traversal/format value-add, *and* it is not a strong analog — site has no conceptual slot it parallels (unlike `title`'s name-slot or `content`'s body-slot). A datum that is "just there" for one source, with no cross-source shape, is not an analog.

The one place tagline *might* earn its keep is as **feedstock for a multi-slot tag** (`try_text` and future multi-slot tags inherit the base `text` enum). That is a weak reason — it overloads the base tag as a feeder rather than the multi-slot tag drawing from a per-source capability set. The right fix is to **decouple the multi-slot feed from the base tag's own enum** ([#26](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/26) + the cross-token work) so we stop hanging values on `text` just to pipe them into `try_text`. Until that exists, a value that *only* clears the gate as multi-slot feedstock is **deferred, not added** — re-evaluate it when the decouple ships.

### Site Source (`src:site`)

**v1.9.0, Stage A.** `src:site` resolves site-wide data behind the existing base tags — one source and one mental model instead of GB Pro's separate `{{site_title}}`/`{{site_tagline}}`/`{{site_logo_url}}`/`{{site_url}}`/`{{option}}` tags. Site has no entity ID, so each base callback **early-gates** on `src:site` (short-circuiting before `bws_resolve_post_by_source`) into `bws_site_resolve_value()` (non-datetime tags) or the datetime `_core('option', …)` path. No `Site` source class and no registry registration exist in Stage A — `site` is a dropdown value + early-gate resolver only.

**`src:site` is uniform with every other source: `use` is the analog-vs-option lever (Model B), not key-presence.** `use:key` reads a wp_options value; named `use` values resolve their datum; the empty wire is the tag's **stripped first-enum value** (key-mode for text/image, `content` for content — see the [strip-default caveat](#source-analog-resolution)). `src:site` selects the wp_options namespace the way `src:current` selects post meta; there is no `use:option` value (option is reached by `use:key`). The existing per-tag `use` values dispatch under site (image `use:featured` → logo, text `use:title` → name). **New** `use` values must clear the [qualifying test](#qualifying-test-for-new-use-values) — be either uniquely useful or a strong cross-source analog. Barred when they are neither: e.g. no `use:logo` (logo already = image `use:featured`) or `use:home_url` (already = permalink) — duplicate data, not analogs; and no `use:tagline` (GB's `{{site_tagline}}` covers it, nothing to format, AND it fills no cross-source slot). `content` has **no** site analog — its default resolves empty (tagline is short, not body text, and has no `content`-tag path).

| Base tag | empty-wire default under `src:site` | explicit `use` values |
|---|---|---|
| `text` | `key` (stripped default) → read wp_options key `X` (`{{text src:site\|key:X}}`); empty key → '' | `use:title` → site name; `use:key` (explicit) → same option read |
| `title` | site name (`get_bloginfo('name')`) | *(no `use`/`key` enum — always name)* |
| `permalink` | site home URL (`home_url()`) | *(no `use`/`key` — ALWAYS `home_url()`; permalink names the site's own URL, never an option read. For a URL stored in an option use `{{text src:site\|key:X}}`.)* |
| `image` | `key` (stripped default) → attachment-ID wp_options key `X`; **implicit mode / no key → '' (NOT the logo)** | `use:featured` (explicit) → site logo (`get_theme_mod('custom_logo')`, full `as:`/`size:`); `use:key` → same option read |
| `content` | `content` (stripped default) → **'' (no site content analog)** — site has no long-form body; the tagline is short and has no `content`-tag path (use GB `{{site_tagline}}`) | `use:key` → wp_options value `X` through the content pipeline (`bws_render_block_content`, keyed `'option:X'`; block/HTML markup executes); `use:excerpt` → empty (no site excerpt) |
| `datetime_single` / `datetime_range` | *(n/a — always field-keyed)* | `key`/`end` read ACF options-page date fields via `get_field($key,'option')`, recovering ACF return format |

> **Site tagline (= blogdescription).** WordPress's "Tagline" (Settings → General) is the same value as the API's `get_bloginfo('description')` / the `blogdescription` option. It is a **short** string (WP's own help: "In a few words, explain what this site is about"), so it is *not* a content analog — `{{content src:site}}` (no key) resolves empty. It also has **no dedicated `use:` value** — it fails both sides of the [qualifying test](#qualifying-test-for-new-use-values) — GB's native `{{site_tagline}}` already exposes it (nothing unique to format or traverse), and it is not a strong cross-source analog (site has no slot it parallels). Reach it via `{{site_tagline}}` or, if you need the wp_options path, `{{text src:site\|key:blogdescription}}`.
>
> **`site_url()` is not exposed.** Bare permalink resolves `home_url()` (the front-facing site address); `site_url()` (the WP-install address, differs only when WP lives in a subdirectory) has no tag path — add one if a real need appears.

**`key` control** (wp_options / ACF-options key; dot-path supported for wp_options arrays via `Meta_Handler::get_option` — e.g. `key:my_settings.colors.primary`): shown when the tag is in key-mode (`use:key` on text/image/content); on datetime it is the always-visible direct field key (meta or option). **`permalink` is the exception — it has no `use` enum and no `key` control under `src:site`** (it names the site's own URL, not a field): implicit mode = `home_url()`, no option read.

**Suppressed for site:** `srcTermIn` (no entity to hop terms from); `ref` (no site→ref wiring in Stage A — tracked as a future enhancement, not a permanent exclusion).

**Link wrapping** (text/title/datetime_* only): `linkTo:permalink` → `home_url()` under `src:site` (the site permalink-analog — no separate `linkTo:site`); `linkTo:key` → option-stored URL (allowlist-gated).

**Allowlist (option reads).** Every option read — site option key-mode, site `linkTo:key`, and datetime `get_field(…,'option')` — passes through the `generateblocks_dynamic_tags_allowed_options` filter, **seeded to GB Pro parity**: the six WP defaults (`siteurl`, `blogname`, `blogdescription`, `home`, `time_format`, `user_count`) plus every registered ACF options-page field (registration is the opt-in — ACF option fields read with no manual filter). The gate is ours, not the handler's. See [`docs/adr/0001-site-option-read-allowlist.md`](adr/0001-site-option-read-allowlist.md) and [`docs/plugin-integration.md`](plugin-integration.md) for the filter usage.

**Coexistence with GB Pro.** GB Pro's site tags still work; `src:site` is additive. Common site data is best fetched via the named `use:` values (`use:title`, etc.) — no key, no allowlist. A migrator's `{{text src:site|key:blogname}}` also resolves (`blogname` is in the parity seed).

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

## List mode (`limit` + `sep`)

Selected templates support outputting multiple results as a delimited list. `limit` defaults to 1 (single result). When `limit > 1`, results are joined with `sep` (default: `, `).

`limit` applies to the **final traversal step**: terms when `srcTermIn:<tax>` is set; related posts at `src:ref`.

| Template | List mode | What is iterated |
|---|---|---|
| `text` | ✅ | Terms (when `srcTermIn`) or related posts (when `src:ref`) |
| `title` | ✅ | Same as above |
| `content` | ❌ | Long-form prose (single-result) |
| `permalink` | ❌ | Single-result URL |
| `image` | ❌ | Single-result media |
| `datetime_single` | ❌ | Single-result date/time (see note) |
| `datetime_range` | ❌ | Single-result composite string (start–end; see note) |
| `email` | ✅ | Terms (when `srcTermIn`) or related posts (when `src:ref`) — each valid address wrapped individually, joined by `sep` |
| `phone` | ✅ | Terms (when `srcTermIn`) or related posts (when `src:ref`) — each valid number wrapped individually, joined by `sep` |

Term-modifier tags (`term_text`, `term_title`, etc.) inherit the same list-mode rule applied at their `src:ref` traversal.

**`datetime_*` — single-result only; list mode is a planned extension ([#30](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/30)).** The base `datetime_single` / `datetime_range` callbacks iterate `srcTermIn` terms but short-circuit to the **first** non-empty result; no `limit` / `sep` controls are registered. A multi-result date list (e.g. every event date on a term's posts) would land under #30 — until it does, these tags return a single value.

---

## Default-enabled logic

In v1.6.0 the per-source×template matrix was removed from the admin settings page. Default-enabled state is now controlled at two levels:

**Modifier group toggles** — `term_` and `try_` each have an on/off toggle in the admin settings page. Disabling a modifier group removes all its tags from the GB editor picker. Both groups default to enabled. Externally registered modifier groups (e.g. `view_`) are not surfaced in the toggle UI.

**Deprecated wrapper tags** — GB registration and runtime callbacks for all current deprecated tags were removed entirely (no longer conditional on any setting). Migration data (`MigrationRegistry` entries) and the admin Tag Converter / settings-page list stay intact for detection and migration of old content. The settings page still shows a Keep/Suppress/Disable radio per group (Has migration path, No migration path), but it no longer has any effect — pending a settings-page redesign to reflect that these are removed, not merely deprecated (tracked `docs/future-work.md`).

**Base tags** (`text`, `image`, `content`, `title`, `permalink`, `datetime_single`, `datetime_range`, `email`, `phone`) are always registered with no admin toggle.

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

## Custom editor controls registered

Registered via the `generateblocks.editor.tagSpecificControls` JS filter. Each entry maps a custom option `type` string (referenced in PHP option definitions) to a React control:

| Control type | Renders | Source file | Used by |
|---|---|---|---|
| `bws-media-picker` | `wp.media()` modal; persists attachment ID (re-fetches preview URL via `wp.data` `core` `getMedia(id)`) | `assets/js/image-tag-controls.js` | `image`, `term_image`, `try_image` fallback |
| `bws-term-hop` | CheckboxControl + ComboboxControl over public taxonomies (via `wp.data` `core`). Reads `pickLabel` / `pickHelp` from PHP option config in addition to `label` / `help` | `assets/js/term-hop-control.js` | `srcTermIn` option on base + modifier tags + per-slot in try_ tags |
| `bws-format-input` | TextControl that escapes `:` / `\|` on save and unescapes for display, so format strings containing colons (e.g. `g:i A` time tokens) survive GB's JS `parseTag()` round-trip | `assets/js/format-input-control.js` | `format` option on `datetime_single`, `datetime_range` |
| `bws-field-combo` | Discovery-backed field picker: a searchable `ComboboxControl` over the field envelope inlined as `window.bwsFieldEnvelope` (assembled once per editor load from the REST route `bws-dynamic-tags/v1/fields`, no runtime fetch), plus two `SelectControl` filters above it (**location** — a path tree `Post/Term/Site fields › group › container`, container fields flagged `(repeater)`/`(group)`; **type** — ACF type or "Loop fields"). Flat list, one row per `(kind, key, label)`; a key in several groups collapses and shows under each, distinct labels stay separate. Serializes the **bare key** as a plain string (option `value` is a private merge key; the `valueToKey` map strips it in `onChange`), so it is a pure render swap for the old `text` input. Free-text via a synthetic "Use custom key" option; clear via `allowReset`. Reads optional `dynamicLabel` (label tracks the active location's group/kind) and `labelPrefix` from PHP option config. Composes with the conditional-options filter (`if (!element) return element`). Offered keys are filtered through `GenerateBlocks_Dynamic_Tag_Security::DISALLOWED_KEYS` server-side (offered ⟺ resolvable). | `assets/js/field-combo-control.js` + `includes/rest/field-discovery.php` | `key` (base/content/email/phone), `ref`, `linkKey` (`labelPrefix:'URL'`), datetime `key`/`timeKey`/`startKey`/`startTimeKey`/`endKey`/`endTimeKey`, and their `N-` per-slot try_ equivalents |

GB image-size selection uses GB's native `image-size` support (not a custom control). The earlier `bws-img-size` ComboboxControl was retired mid-1.6.0 cycle once GB's native support was confirmed to handle the reserved `size` key correctly — see CHANGELOG 1.6.0.

---

## Option layout & visibility

The cross-tag model for **how options are ordered in the editor panel** and **how show/hide conditions are expressed**. Each per-tag section in Part II gives its own ordered list; this section is the shared schema those lists follow.

**Three-group structure (applies to all templates):**
- **Group 1 — global formatting:** `as`, format options, separators, link-wrap. Not per-slot; applies to the assembled result.
- **Group 2 — per-slot:** source selector → source secondary options (`ref`, `srcTermIn`, `limit`, `sep`) → field options (`use`, `key`). Repeated for each try_ slot.
- **Group 3 — global fallback:** `fallback`. Once, after all slots.

Show/hide conditions are noted inline in each per-tag list; all other options are always visible.

**`show_if` condition types** (implemented in `assets/js/editor-conditional-options.js`):
- `'not_empty'` — passes when option has any value
- `'empty'` — passes when option is unset/blank
- `'not:value'` — passes when option does not equal `value`
- `'value'` (literal string) — passes when option equals that exact string
- `'in:v1,v2,...'` — passes when option equals any listed value *(new)*
- `'not_in:v1,v2,...'` — passes when option equals none of the listed values *(new)*

Multiple conditions in one `show_if` map are AND'd. Array-of-conditions per key is not implemented.

---

# Part II — Catalog

*Browse. The exhaustive flat tables — every tag, every option, the daily interface. The concepts in Part I explain the vocabulary used here. `email` and `phone` are first-class **base tags** (cross-source, registered unconditionally like `text`), not modifiers — they get their own sections here only because their link mechanics differ from the `linkTo` family.*

## Base tag GB types

In the source-agnostic architecture, each template has one GB tag registration. Type names settled (2026-04-14): base tags use `'cross-source'`; try_ tags use `'first-available'`. Both are hyphenated English compounds confirmed valid as GB type strings.

**Tag title** (`title`) is shown in the GB tag picker and is the last-resort editor fallback when a tag can't resolve and no preview label is available. The term_ modifier appends `'(term-based)'` to the base title.

| Template key | Tag title | Term modifier title | GB type | Link wrap | Notes |
|---|---|---|---|---|---|
| `text` | `'Text Fields'` | `+ '(term-based)'` | `'cross-source'` | ✅ | |
| `content` | `'Content/Description'` | `+ '(term-based)'` | `'cross-source'` | ❌ | Long-form; may already contain links |
| `title` | `'Title/Name'` | `+ '(term-based)'` | `'cross-source'` | ✅ | Zero options aside from link; shares pipeline with `text use:title`. |
| `permalink` | `'Permalink'` | `+ '(term-based)'` | `'cross-source'` | ❌ | Output is already a URL |
| `image` | `'Image Fields'` | `+ '(term-based)'` | `'cross-source'` | ❌ | URL output nonsensical to wrap; image linking deferred |
| `datetime_single` | `'Format Date/Time Fields'` | `+ '(term-based)'` | `'cross-source'` | ✅ | |
| `datetime_range` | `'Format Date/Time Fields as Range'` | `+ '(term-based)'` | `'cross-source'` | ✅ | |
| `email` | `'Email'` | *(no term_ variant)* | `'cross-source'` | `mailto:` (own anchor, not `linkTo`) | Default-ON mailto wrap toggled by `noLink`; `visibility`-gated off `a`/`button`/`img`/`picture`. See [§Email tag](#email-tag). |
| `phone` | `'Phone'` | *(no term_ variant)* | `'cross-source'` | `tel:` (own anchor, not `linkTo`) | Default-ON tel wrap toggled by `noLink`; href rebuilt from stored value (author separators preserved); 2-tier country code; `visibility`-gated off `a`/`button`/`img`/`picture`. See [§Phone tag](#phone-tag). |
| `call` | `'Call Custom Function'` | *(no term_ variant)* | `'post'` | ❌ | **Structural outlier — not a base tag.** Binds the loop-correct post (L1 only), then delegates to an allowlisted site PHP function; output is the function's return string, verbatim + unescaped. Type `'post'` (NOT `'cross-source'`) — no term/site/media/taxonomy features; `src` offers Current + Ref only. Ships with an empty allowlist. See [§Call tag](#call-tag). |

The term_ modifier produces additional tags with GB type `'term'`: `term_text`, `term_image`, `term_title`, `term_permalink`. `src` unset = user-selected term (never serialized); `src:'ref'` = term→related post traversal. `term_image` uses GB type `'term'`; `as` and `size` registered as custom options (same pattern as base `image` — `'media'` type not used on any image tag). `as` serialization exception applies to `term_image` as well — default `as:url` is always written to the tag string.

**`term_image use:featured` gating:** `use:featured` only valid on `term_image` when `src:ref` set. Term entities have no featured image; gate hides the option until a post-context traversal is selected.

**try_ modifier** produces `try_text`, `try_image`, etc. with GB type `'first-available'`. Up to 5 slots (s1–s5); slots revealed progressively as earlier slots are configured.

See [§Default serialization strategy](#default-serialization-strategy) for the registration-boundary mechanism that controls which option defaults survive into the saved tag string (and the intentional `as` opt-out for `image` / `term_image`).

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

4. **`srcTermIn`** — Combined `bws-term-hop` control. Per-slot, no carry-forward (each slot independently chooses term-hop). Slot 1 option name: `srcTermIn`; slots 2+: `N-srcTermIn`. Empty/unset = disabled; slug = enabled with that taxonomy. See [§Source group](#source-group).

**Progressive disclosure:** Slot N+1 is hidden until at least one of slot N's controls is set to a non-default value. Within a slot, sub-controls (e.g. `ref` key, `key`) appear only when their parent control is active.

**Per-slot `use`** is available on templates that support content mode selection per slot (e.g. "try ACF/meta field, fall back to post content").

### Per-slot label scheme

Applies to **try_ tags** (multi-slot). Every slot-tied control front-loads the slot ordinal as an `N: ` prefix (legibility — the number leads); each slot is numbered, including slot 1 (`1: …`). Base / term_ tags (single slot) use the bare labels in [§Source group](#source-group) and [§Field group](#field-group) — no ordinal.

| Control | Label (`N` = slot number) |
|---|---|
| `src` (source selector) | `N: Source` |
| `ref` (relationship field) | `N: Relationship Field Key` |
| `srcTermIn` checkbox | `N: Get from taxonomy term?` |
| `srcTermIn` taxonomy combobox | `N: Taxonomy` |
| `use` (field-type selector) | `N: Text Field` / `N: Content Field` / `N: Image Field` |
| `key` (field key) | `N: Meta/Option Field Key` |

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
| `try_email` | `email` | **Yes** | No | Single key-mode (no `use` enum). Each slot resolves an email field → finished mailto/plain string, exactly as `{{email}}`. Slot `src:site` allowed (canonical contact fallback). `subject`/`noLink` chain-level |
| `try_phone` | `phone` | **Yes** | No | Single key-mode (no `use` enum). Each slot resolves a phone field → finished tel/plain string, as `{{phone}}`. Slot `src:site` allowed. `noLink` chain-level |

---

## Shared option groups

Options common to most base tags, defined **once** here. Each per-tag section below lists only its tag-specific options and links back to these groups. The panel order these slot into is the [Option layout & visibility](#option-layout--visibility) model in Part I.

Option / required-option rules for deprecated N×M wrappers (e.g. `related_post_*`, `term_related_post_*`, `custom_text`, `custom_image`, `term_custom_*`) live in [`docs/deprecated-tags-options.md`](deprecated-tags-options.md), not here.

### Source group

The source selector and its conditional sub-options. Present on every base tag (and per-slot in try_, with the `N-` prefix).

| Option name | Option label | Context | Notes |
|---|---|---|---|
| `src` | Source | Base / Slot 1 | `source` avoided — GB unconditionally strips it from extraTagParams before our controls can read it |
| `N-src` | [N]: Source | Slot 2+ | Abbreviated to reduce tag length |

**`src` option values — per-slot UI/serialization mechanics** (labels, the slot-2+ `same`/`current` distinction, the editor-preview context segment each value produces). For **what each value resolves to** (and implementation status), see [§`src` option values](#src-option-values) in Part I.

| Option label | Option value | Base / Slot 1 | Slot 2+ | Context segment in editor preview label | Notes |
|---|---|---|---|---|---|
| Same as Previous Source | `same` | Current entity — not serialized | Inherit slot N−1 | N/A | Slot 2+: prepended entry, not in template definition |
| Current | `current` | stripped → unset | `current` | *(omitted)* | Slot 2+ only: explicit override back to current |
| In Reference/Relational Field | `ref` | `ref` | `ref` | `Ref 'X'` where X = `ref` field value | Triggers `ref` sub-option |
| Parent | `parent` | `parent` | `parent` | — | Future |
| Ancestor | `ancestor` | `ancestor` | `ancestor` | — | Future |
| Child(ren) | `child` | `child` | `child` | — | Future |

Note: For context-modifier tags, the modifier label is prepended as a context segment. Examples: `[Title from Term]` for `{{term_title}}`, `[Content from Term Ref 'rel_post']` for `{{term_content src:ref|ref:rel_post}}`. See [`editor-tag-previews.md`](editor-tag-previews.md) for assembly rules.

**Source secondary, conditional options:**

| Option name | Option label | Help text | Shown when | Notes |
|---|---|---|---|---|
| `ref` | Relationship Field Key | ACF relationship or post object field key. | `src` = `ref` | ACF relationship/relational field key for the traversal hop. **Required** when `src:ref` selected. |
| `srcTermIn` | Get from taxonomy term? | Field is in a taxonomy term on this source. | Always; hidden for `term_` modifier tags (entity already a term) at `src:current`; shown at `src:ref` | Combined `bws-term-hop` control (CheckboxControl + ComboboxControl). Empty/unset = disabled; slug = enabled with that taxonomy (the slug encodes both "term hop on" and the taxonomy — **required** when hop is on). Replaced prior `srcTerm` + `tax` pair (v1.6.0). |
| `limit` | Result Limit | This source type may return multiple results. By default, only the first result is used, but you may enter either a fixed limit, or “0” for no limit. | `src` = `ref` or `child` *(future)*, or `srcTermIn` set | `text`, `title`, `email`, `phone` only (list-mode tags). Placeholder `1`; not serialized when unset. (`datetime_*` list mode intended but unimplemented — [#30](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/30).) |
| `sep` | Result Separator | Separator between results (defaults to “, “). | `limit > 1` | List-mode separator, same tag set as `limit`. |

### Field group

The field-type selector (`use`) + field key (`key`). Present on `text`, `image`, `content` (and per-slot in try_). `title`/`permalink` have no field options (their datum is the analog); `email`/`phone` have no `use` enum (key-required, no analog); `datetime_*` use direct field keys (see their section).

| Option name | Option label | Context | Notes |
|---|---|---|---|
| `use` | [Text/Image/Content] Field | Base / Slot 1 | |
| `N-use` | [N]: [Text/Image/Content] Field | Slot 2+ | |

**`use` field-selector values (where applicable):**

| Applicable tags | Option name | Option label | Conditionals | Notes |
|---|---|---|---|---|
| `text`, `image`, `content` | `same` *(prepended, slot 2+)* | Same as Previous Field | Hides additional fields | Slot 2+ only, not in template; stripped to '' per standard rules for default option |
| `text`, `image`, `content` | `key` | Meta/Option Field | Shows/enables field key | — |
| `text` | `title` | Title/Name | Disables field key | Term name if source is term; site name if `src:site` |
| `content` | `content` | Post Content/Term Description | Disables field key | Term description if source is term; **empty if `src:site`** (no site content analog) |
| `content` | `excerpt` | Post Excerpt | Disables field key | Empty under `src:site` (no site excerpt) |
| `image` | `featured` | Featured Image/Site Logo | Disables field key | Site logo (`custom_logo` theme mod) if `src:site` |

**`key` field key:**

| Applicable tags | Option name | Option label | Context | Notes |
|---|---|---|---|---|
| `text`, `image`, `content` | `key` | Meta/Option Field Key | Base / Slot 1 | Aligns with and substitutes for GB native `key` option name generated by `supports => ['meta']`, to avoid issues with GB's filtering and set our own order. Reads post/term meta normally, or a wp_options / ACF-options value under `src:site` (the field-type prefix tracks source scope — V10). **Required** when `use:key` (or the stripped key-mode default for text/image). |
| `text`, `image`, `content` | `N-key` | [N]: Meta/Option Field Key | Slot 2+ | |

See [`datetime_*` section](#datetime_single-and-datetime_range) for the datetime-context label and keys.

### Link wrap group

Available on `text`, `title`, `datetime_single`, `datetime_range` (base, `term_` modifier, and `try_` variants). Excluded: `content`, `permalink`, `image`. (`email`/`phone` have their own `mailto:`/`tel:` link mechanism — `noLink` — NOT the `linkTo` family; see their sections.) Placed at **end of Group 1** in all eligible templates.

| Option name | Option label | Notes |
|---|---|---|
| `linkTo` | Link To | Link-destination selector. Values enumerated below. First value `none` is the canonical token, stripped at registration per default-strip strategy. |
| `linkKey` | URL Meta/Option Field Key | Meta or option field key whose value is the URL (post/term meta, or a wp_options / ACF-options key under `src:site`). Shown when `linkTo:key`. If empty, link wrap skipped (never blocks tag output). For `try_` tags, this field is read from the entity that produced the winning slot's output — no per-slot `linkKey`. |
| `newTab` | Open in new tab | Boolean presence-flag. Shown when `linkTo` not empty. Emits `target=”_blank” rel=”noopener noreferrer”` on the anchor. |

**`linkTo` values:**

| Value | Label | Resolves to |
|---|---|---|
| `none` *(unset)* | No Link | No wrap. Canonical default, stripped at registration. |
| `permalink` | Permalink | Entity permalink (`get_permalink` / `get_term_link`); under `src:site` → `home_url()` (the site permalink-analog — there is no separate `linkTo:site`). |
| `key` | URL Meta/Option Field | URL read from the meta/option field named in `linkKey` (allowlist-gated under `src:site`). |

Link wrap is applied **after fallback resolves** — fallback text is also wrapped if a link resolves. On `try_` tags, the single `linkTo`/`linkKey`/`newTab` applies to the winning slot's entity (post or term). `term_` modifier tags resolve entity type from dispatch path (term entity for base-source dispatch; post entity for `src:ref` dispatch).

**`email`/`phone` are the exception — their link is NOT a `linkTo` option.** They do not participate in the `linkTo`/`linkKey`/`newTab` family above (those wrap an *entity URL*). Their only link is the `mailto:`/`tel:` for the address/number itself, **default-ON** and toggled by the inverted `noLink` bare key (absent = wrap, present = plain text). Note the **opposite polarity**: `linkTo` defaults to *no* wrap, whereas `noLink` defaults to *wrapped* — because the email's/phone's own address is the only sensible link. The anchor is built directly (no class/target), not via `bws_wrap_with_link`. `newTab` does not apply to `mailto:` (opening a mail client does not navigate). See [§Email tag](#email-tag) / [§Phone tag](#phone-tag).

### Fallback group

The `fallback` option (Group 3 — global, after all slots).

| Applicable tags | Option type | Notes |
|---|---|---|
| `text`, `content`, `title`, `datetime_single`, `datetime_range` | Text field | |
| `image` | Media library selector → image ID (see `custom-image-controls.md`) | |
| `email` | Text field → a fallback **email address** | Validated with `is_email()` + wrapped like a real address (not arbitrary text). Fires only when no valid address resolves. |
| `phone` | Text field → a fallback **phone number** | Normalized + wrapped like a real number (length-gated, not arbitrary text). Fires only when no valid number resolves. |
| `permalink` | TBD — can be text field initially | Add page/post selector? |

---

## `text`

Reads a text field (ACF/meta) or the source's **title/name** analog (`use:title`). Cross-source, link-wrappable, list-mode capable. GB type `'cross-source'`; picker title `'Text Fields'`.

**Tag-specific options:** none beyond the shared groups — `text` is the canonical user of [Source](#source-group) + [Field](#field-group) + [Link wrap](#link-wrap-group) + [Fallback](#fallback-group). `use` values: `key` (default, key-mode — **`key` required**) or `title` (the analog).

**Panel order:**
- **Group 1:** `linkTo` → `linkKey` (shown when `linkTo:key`) → `newTab` (shown when `linkTo` not empty)
- **Group 2:** `[source options]` → `use` (`key` (unset default in single-slot tags); `title`) → `key` (shown when `use` unset [in single-slot tags] or `use:key`)
- **Group 3:** `fallback`

---

## `content`

Long-form prose: post content / term description (the analog), an excerpt, or a keyed field. Single-result (not list-mode); **not** link-wrappable (may already contain links). GB type `'cross-source'`; picker title `'Content/Description'`.

**Tag-specific options:** `use` values are `content` (default analog — post content / term description; **empty under `src:site`**), `excerpt` (post excerpt; empty under `src:site`), or `key` (**`key` required**). Uses [Source](#source-group) + [Field](#field-group) + [Fallback](#fallback-group); no [Link wrap](#link-wrap-group).

**Panel order:** `[source options]` → `use` (`content` (unset default in single-slot tags); `excerpt`; `key`) → `key` (shown when `use:key`) → `fallback`

---

## `title`

The source's title/name analog — post title / term name / site name. Zero options aside from link-wrap; shares its pipeline with `text use:title`. Cross-source, link-wrappable, list-mode capable. GB type `'cross-source'`; picker title `'Title/Name'`.

**Tag-specific options:** none — no [Field group](#field-group) (the datum is always the analog, no `use`/`key`). Uses [Source](#source-group) + [Link wrap](#link-wrap-group) + [Fallback](#fallback-group).

**Panel order:**
- **Group 1:** `linkTo` → `linkKey` (shown when `linkTo:key`) → `newTab` (shown when `linkTo` not empty)
- **Group 2:** `[source options]`
- **Group 3:** `fallback`

---

## `permalink`

The source's URL analog — post URL / term URL / site `home_url()`. Output is already a URL, so it is **not** link-wrappable and has no field options. Single-result. GB type `'cross-source'`; picker title `'Permalink'`.

**Tag-specific options:** none — no [Field group](#field-group), no [Link wrap group](#link-wrap-group). Uses [Source](#source-group) + [Fallback](#fallback-group) (fallback is TBD — see [Fallback group](#fallback-group)).

**Panel order:** `[source options]` → `fallback`

---

## `image`

A media field: the source's **featured image / site logo** analog (`use:featured`) or a keyed image field. Returns a URL, alt text, caption, or attachment ID per `as`. URL output is nonsensical to wrap, so **no** link-wrap; image-linking deferred. Single-result. GB type `'cross-source'`; picker title `'Image Fields'`.

**Tag-specific options:**

| # | Option label | Option name | Notes |
|---|---|---|---|
| 1 | Return As | `as` | return type: `url` / `alt` / `id` / `caption` — **always serialized** (see [§Default serialization strategy](#default-serialization-strategy) for the `as` opt-out) |
| 2 | Image Size | `size` | image size (URL or ID returns) — GB native `image-size` support; see `custom-image-controls.md` |
| 3 | | `[source options]` | [Source group](#source-group); no `limit`/`sep` for image |
| 4 | | `use` | `key` (unset default in single-slot tags); `featured` — `featured` disabled for term-context entities unless `src` = `ref`; under `src:site` `use:featured` = logo |
| 5 | | `key` | shown when `use` unset [in single-slot tags] or `use:key` — **`key` required** in key-mode |
| 6 | | `[fallback option]` | media picker → image ID; see [Fallback group](#fallback-group) + `custom-image-controls.md` |

---

## `datetime_single` and `datetime_range`

Format a date/datetime/time field (`datetime_single`) or a start–end **composite string** (`datetime_range`). Single-result (list mode intended but unimplemented — [#30](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/30)). Link-wrappable. GB types `'cross-source'`; picker titles `'Format Date/Time Fields'` / `'Format Date/Time Fields as Range'`.

**Required:** `datetime_single` needs `key`; `datetime_range` needs `startKey` (`endKey` optional). Under `src:site` the keys read ACF options-page date fields via `get_field($key,'option')`. Uses [Source](#source-group) (no `limit`/`sep`) + [Link wrap](#link-wrap-group) + [Fallback](#fallback-group).

**Tag-specific options + panel order** (numbers = position per template):

| Option label | Option name | `datetime_single` | `datetime_range` | Values/Notes |
|---|---|---|---|---|
| Return As | `as` | 1 | 1 | `datetime`; `date`; `time` |
| Start & End Separator | `rangeSep` | — | 2 | separator between start and end values within one result |
| Custom Format | `format` | 2 | 3 | PHP format string; empty = auto |
| Date & Time Separator | `timeSep` | 3 | 4 | shown when `as` ≠ `date` AND `as` ≠ `time` AND `format` empty |
| Show time when stored as midnight? | `showMidnight` | 4 | 5 | checkbox, false by default; shown when `as` ≠ `date` |
| Show current year in date? | `showCurrentYear` | 5 | 6 | checkbox, false by default; shown when `as` ≠ `time` |
| Link To | `linkTo` | 6 | 7 | End of Group 1. `permalink`; `key`; unset = no link |
| Link URL Field Key | `linkKey` | 7 | 8 | shown when `linkTo:key` |
| Open in new tab | `newTab` | 8 | 9 | checkbox; shown when `linkTo` not empty |
| | `[source options]` | 9 | 10 | `src` / `srcTermIn` / `ref` — no `limit`/`sep` (datetime is single-result, see §List mode + [#30](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/30)) |
| Date/Time Field Key | `key` | 10 | — | primary date/time field key |
| Time Field Key (optional) | `timeKey` | 11 | — | separate time field — shown when `as` ≠ `date` |
| Start Date/Time Field Key | `startKey` | — | 11 | |
| Start Time Field Key (optional) | `startTimeKey` | — | 12 | shown when `as` ≠ `date` |
| End Date/Time Field Key | `endKey` | — | 13 | |
| End Time Field Key (optional) | `endTimeKey` | — | 14 | shown when `as` ≠ `date` |
| | `[fallback option]` | 12 | 15 | |

**Design rationale:** Global formatting options (`as`, `rangeSep`, `format`, `timeSep`, `showMidnight`, `showCurrentYear`) lead as group 1 — not per-slot. Source selector follows as group 2 (`src` / `srcTermIn` / `ref`; no `limit`/`sep` — datetime is single-result, see §List mode). Field keys close as group 3. `fallback` last.

---

## Email tag

`{{email}}` (1.9.0) outputs a stored email address, by default wrapped in a `mailto:` link. It is a first-class base tag — registered unconditionally, cross-source like `text` — living in `includes/tags/email-tags.php`.

**Source / field read.** The address is read from a meta/option field via the shared source-resolution pipeline (`bws_resolve_field_values`, the L1/L2 seam email/phone both consume — unified in 1.11.0), so it works in every source: `src:site` → wp_options / ACF-options (allowlist-gated via `bws_site_read_option`, dot-path supported); `src:current`/unset → post/term meta; `src:ref` / `srcTermIn` → traversed-entity meta (list mode). Email is **key-required in every source** — it has no intrinsic analog, so there is **no `use` enum** and `key` is always required. (A future `use:author` / `use:admin` enum is additive and gated by the [qualifying test](#qualifying-test-for-new-use-values).)

**`mailto:` wrap (default-ON) + `noLink`.** The address is wrapped in `<a href="mailto:…">` UNLESS the `noLink` bare key is present (`noLink` = plain text). This is an **inverted bare-key boolean**: absence = wrap, present = off. Modeled this way because GB's serializer drops `false`, so "default-on, serialize-when-off" is only reachable via an inverted-name presence flag (same pattern as `showCurrentYear` / `showMidnight`). The anchor is built directly (minimal, no class/target) — it does NOT use the `linkTo` / `bws_wrap_with_link` entity-link machinery (those are for entity URLs; email's link is the address itself). WP emits no standard class on mailto anchors — target them via `a[href^="mailto:"]` in CSS.

**`subject` — two-layer encoding.** Optional `subject` for the `mailto:?subject=` query, entered via the `bws-format-input` control. Two distinct encoding layers with different owners: (1) the control escapes `:` / `|` so the value survives GB's `parseTag`; GB's server-side `parse_options()` then **unescapes** before the callback. (2) the callback `rawurlencode()`s the (already-clean) subject into the query — its only render step. The callback does NOT unescape (GB already did). `subject` is hidden when `noLink` is set (no query to carry it).

**Obfuscation (anti-harvest).** Addresses are run through `antispambot()` on BOTH display text and the `mailto:` href local-part, controlled by the global **Settings → Tag Extensions → Email → "Obfuscate email addresses"** toggle (default ON; WP-parity — disable for a clean `mailto:` href, e.g. analytics). `antispambot()` output is already entity-encoded and is emitted raw (never re-`esc_html`'d, which would double-encode).

**Validation + fallback.** The resolved value is validated with `is_email()` on the raw string — only a valid address is ever wrapped. Invalid (incl. empty) → the `fallback` option, which is itself a **fallback email address** (validated, wrapped like a real address — like `{{image}}`'s fallback = attachment ID, not text). In list mode, only `is_email()`-valid addresses are kept and wrapped individually; the fallback fires ONLY when zero valid addresses resolve (whole-result-empty), and if it too is invalid the tag returns empty.

**`visibility` gate.** `{{email}}` registers with native GB `visibility` `tagName NOT_IN ['a','button','img','picture']` — mirroring GB core's own `term_list`. The default-ON `<a>` wrap makes the tag invalid inside anchor/button (nested interactive markup) or img/picture (text in a void/replaced element), so it is hidden in the selector on those elements. This is the plugin's first native `visibility` use (see [`gb-constraints.md` §visibility](gb-constraints.md)). Wrap-capable text/title/datetime tags get an `img`/`picture`-only gate later ([#31](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/31)). `try_email`/`try_phone` ([#32](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/32)) thread this same `visibility` gate (and a runtime media-block backstop, since a media block's empty `tagName` slips the native gate — see [`gb-constraints.md` §visibility](gb-constraints.md)).

**Options:**

| Option | Type / control | Label | Shown when | Notes |
|---|---|---|---|---|
| `src` | select | Source | always | `current` / `ref` / `site`; default `current` (stripped). Shares `bws_base_source_option`. |
| `ref` | `bws-field-combo` | Relationship Field Key | `src:ref` | Traversal hop key. |
| `srcTermIn` | `bws-term-hop` | Get from taxonomy term? | not `src:site` | Post→term hop (list mode). |
| `key` | `bws-field-combo` | Meta/Option Field | always | **Required** — email field key. wp_options / ACF-options (dot-path) under `src:site`; post/term meta otherwise. |
| `subject` | `bws-format-input` | Subject | `noLink` empty | Optional `mailto:?subject=`; escaped editor-side, `rawurlencode`d at render (see two-layer encoding above). |
| `noLink` | checkbox (bare key) | Disable email link (plain text) | always | Inverted presence flag: absent = mailto wrap (default), present = plain text. |
| `limit` | number | Result Limit | `srcTermIn` set or `src:ref` | List mode; default 1. |
| `sep` | text | Result Separator | `srcTermIn` set or `src:ref` | List-mode join; default `, `. |
| `fallback` | text | Fallback Email | always | A fallback **email address** (validated, wrapped). Fires only when no valid address resolves. |

Plus the global **Settings → Tag Extensions → Email → "Obfuscate email addresses"** toggle (default ON) — not a per-tag option; gates `antispambot()` for all `{{email}}` output.

**Wire-format examples:**

```
{{email src:site|key:org_email}}                      → <a href="mailto:VALUE">VALUE</a>   (default wrap)
{{email src:site|key:org_email|noLink}}               → VALUE                                (plain)
{{email src:site|key:org_email|subject:Hello there}}  → <a href="mailto:VALUE?subject=Hello%20there">VALUE</a>
{{email key:contact_email}}                           → post/term meta email, wrapped
```

---

## Phone tag

`{{phone}}` (1.10.0) outputs a stored phone number, by default wrapped in a `tel:` link. It is a first-class base tag — registered unconditionally, cross-source like `text`/`email` — living in `includes/tags/phone-tags.php`.

**Source / field read.** The number is read from a meta/option field via the shared source-resolution pipeline (`bws_resolve_field_values`, the L1/L2 seam email/phone both consume — formerly a per-tag clone, unified in 1.11.0), so it works in every source: `src:site` → wp_options / ACF-options; `src:current`/unset → post/term meta; `src:ref` / `srcTermIn` → traversed-entity meta (list mode). Phone is **key-required in every source** — no intrinsic analog, so **no `use` enum**.

**`tel:` href rebuild — author separators preserved (model C).** Unlike `email` (href = address verbatim), the `tel:` href is rebuilt from the stored value into a canonical dial value by `bws_phone_normalize_tel()`. The key rule: **hyphens in the href appear ONLY where the author wrote a separator.** `(987) 654-3210` → `tel:+1-987-654-3210`; bare `9876543210` → `tel:+19876543210` (no fabricated grouping — segmentation is unknowable from raw digits without locale rules, so it is never guessed). No libphonenumber dependency. The **display** text stays the stored value verbatim (`esc_html`); display and href may differ. (Display-side reformatting is a planned follow-up.)

**Country code — 2-tier.** Resolution, first match wins: (1) an **in-field international prefix** (`+…` or `00…`, the latter rewritten to `+`) wins and is used as-is; (2) otherwise the global **Settings → Tag Extensions → Phone → "Default country code"** (digits only, empty default) is prepended. With no country code and no in-field prefix, a **national `tel:`** link (no `+`) is emitted (single-country sites still dial). A leading national **trunk `0`** is stripped when a country code is applied (UK `07911…` → `+44-7911…`); the national-fallback case keeps the `0`. *Per-tag `cc:` is intentionally out of scope* — see [§Phone deferred](#phone-deferred).

**Strip-leading-CC (optional, global, default OFF).** **Settings → Tag Extensions → Phone → "Strip a leading country code matching the default"** guards numbers stored *with* a country code but *without* a `+` (e.g. US `1-800-555-1212` with default country code `1`) from a doubled prefix. It strips a single leading run **only when it exactly matches the configured global country code** and ≥7 digits remain. Matches the global code only (the reason per-tag `cc:` is deferred: an arbitrary per-tag country has no equivalent safety proof).

**`tel:` wrap (default-ON) + `noLink`.** Wrapped in `<a href="tel:…">` UNLESS the `noLink` bare key is present — same **inverted bare-key boolean** as `email`. The anchor is built directly (minimal, no class/target), NOT via the `linkTo` / `bws_wrap_with_link` entity-link machinery.

**Validation + fallback.** Validity is a loose **length gate**: the final assembled digit count (country code + national, post-strip) must be **7–15** (E.164 max 15). A number that fails is **skipped** (strict — never rendered as plain text; lenient passthrough is a [deferred](#phone-deferred) option). In list mode each valid number is wrapped individually and joined by `sep`; the `fallback` (itself a **fallback phone number**, normalized the same way) fires only when zero valid numbers resolve, then returns empty if it too is invalid. Inline extension junk (`x99` / `ext 99`) is severed and ignored (the raw stored value is preserved for a [future extension feature](#phone-deferred)).

**Security (`VP-href-safe`).** The `tel:` href is digits + boundary hyphens **by construction** — groups are digit-runs only and every non-digit is a discarded separator, so no raw field text reaches the href (`esc_attr` is defense-in-depth). The display side carries raw field text, defended by `esc_html`.

**`visibility` gate.** Registers with native GB `visibility` `tagName NOT_IN ['a','button','img','picture']`, mirroring `email` and GB core `term_list` — the default-ON `<a>` wrap is invalid inside anchor/button or img/picture.

**Options:**

| Option | Type / control | Label | Shown when | Notes |
|---|---|---|---|---|
| `src` | select | Source | always | `current` / `ref` / `site`; default `current` (stripped). Shares `bws_base_source_option`. |
| `ref` | `bws-field-combo` | Relationship Field Key | `src:ref` | Traversal hop key. |
| `srcTermIn` | `bws-term-hop` | Get from taxonomy term? | not `src:site` | Post→term hop (list mode). |
| `key` | `bws-field-combo` | Meta/Option Field | always | **Required** — phone field key. wp_options / ACF-options (dot-path) under `src:site`; post/term meta otherwise. |
| `noLink` | checkbox (bare key) | Disable phone link (plain text) | always | Inverted presence flag: absent = tel wrap (default), present = plain text. |
| `limit` | number | Result Limit | `srcTermIn` set or `src:ref` | List mode; default 1. |
| `sep` | text | Result Separator | `srcTermIn` set or `src:ref` | List-mode join; default `, `. |
| `fallback` | text | Fallback Phone Number | always | A fallback **phone number** (normalized, wrapped). Fires only when no valid number resolves. |

Plus two global **Settings → Tag Extensions → Phone** options (not per-tag): **Default country code** (digits, empty default) and **Strip a leading country code matching the default** (default OFF).

**Wire-format examples:**

```
{{phone src:site|key:org_phone}}   field "(987) 654-3210"  → <a href="tel:+1-987-654-3210">(987) 654-3210</a>  (CC 1, author groups)
{{phone key:mobile}}               field "07911 123456"     → <a href="tel:+44-7911-123456">07911 123456</a>     (CC 44, trunk 0 stripped)
{{phone key:mobile|noLink}}        field "07911 123456"     → 07911 123456                                        (plain)
{{phone key:phone}}                field "9876543210" no CC → <a href="tel:9876543210">9876543210</a>             (national, no hyphens)
{{phone key:us}}    field "1-800-555-1212" CC 1, strip ON   → <a href="tel:+1-800-555-1212">1-800-555-1212</a>    (leading CC stripped)
```

**Tests.** Normalization (`bws_phone_normalize_tel` + sub-helpers) is pinned by a standalone, WP-free harness: `php tools/test/phone-normalize-test.php` (run on any change to normalize/trunk-strip/length-gate/strip-CC). End-to-end source/list/render/settings coverage is the standing manual matrix [`tools/test/phone-test-matrix.md`](../tools/test/phone-test-matrix.md), which carries its own re-run trigger.

---

## Call tag

`{{call}}` (1.12.0) runs an allowlisted, site-defined PHP function and outputs its return string. It is **NOT a base tag** — it is a structural outlier living in `includes/tags/fn-tags.php` (GB type `'post'`, not `'cross-source'`). It exists for display values too conditional for base tags to assemble (a function that branches on a term name, formats a score, looks up an indicator from a table). Rather than rig a fragile multi-tag composition, `{{call}}` hands the work to one PHP function.

**A fourth structural position.** Beyond base / modifier / join-absorber, `{{call}}` reuses **L1 post-resolution ONLY** — it binds the loop-correct post entity via `bws_resolve_post_by_source`, then **delegates to an opaque PHP function**. There is no L2 resolve-field, no L2b fetch, no L3 assemble; no resolved field, no field value. The output is opaque to the read pipeline: a single string, no list mode, no composite, no analog. It sits outside the try_ transparency / list-mode destination model.

**Post-context-only — a stated design non-goal, not a gap.** The source menu offers **Current** + **In Reference/Relational Field** ONLY; both resolve to a post id, exactly what a `$post_id`-contract function consumes. `src:site` (a wp_options namespace) and `srcTermIn` (terms) are deliberately **not offered** — neither is a post id, a `$post_id` function cannot consume them, and they add no post-binding affordance (the [qualifying test](#qualifying-test-for-new-use-values) applied at the source level). A future reader must not "fix" this by adding term/site sources: the post binding is the entire purpose. The GB type is `'post'` precisely because `{{call}}` has none of the term/site/media/taxonomy editor features `'cross-source'` implies.

**Known limit — flat repeater rows.** `bws_resolve_post_by_source` resolves a post id. Mode 2a loops (relationship / post-object — the row IS a post) resolve and are the driver. Mode 2b (a flat ACF repeater row with no underlying post) returns false for `src:current`; there is no post to bind, and the `$post_id` function contract cannot consume a bag of row fields. Passing current-repeater-row fields into a function needs a different fn contract + a new src mode — a separate, deferred design, not a bug.

**Allowlist — code, not the database.** The source of truth is the `bws_fn_passthrough_functions` filter (default empty). Register either way: the raw filter (power users / bulk), or the `bws_register_call_function( 'my_fn', $meta = [] )` helper (runs the gate at registration and fails fast via `_doing_it_wrong`). Storage is associative from v1 (`[ 'my_fn' => [] ]`); bare-string filter entries are normalized to that shape on read; re-registering a name overwrites its meta (last-write-wins, so a richer v2 meta update sticks). `$meta` is stored but unused in v1 (pretty labels / `post_id_arg` are future). The trust boundary is **file/code access only** — no DB-write widening — so `{{call}}` grants editors no capability a developer didn't already hold in PHP. It is a routing convenience, not privilege escalation.

**Register on `init` (any priority).** `bws_register_call_function()` is defined at plugin load (a top-level `require`, before `init`), so an `init` callback can call it at any priority without a "Call to undefined function" fatal — including the default priority 10, which is earlier than the plugin's own init:20 tag pass. The editor `fn:` dropdown is built from the allowlist during that init:20 pass (GB snapshots a tag's option list at registration), so a function registered on init *before* :20 (default 10 included) appears in the dropdown; one registered at a *later* hook still **resolves at render** (the callback re-reads the live allowlist) but won't appear in the dropdown until the next pass. The read-only admin mirror reads the live allowlist and so always shows a late-registered function, making it the escape hatch for an "it runs but isn't pickable" case.

**Security gate — security-only, NOT a contract check.** Every candidate clears two checks at registration AND defensively at resolve: (1) `function_exists`; (2) `ReflectionFunction::isInternal() === false` — the hard gate, which blocks every PHP builtin (`system` / `exec` / `unlink` / eval-likes), reducing the surface to site-defined functions.

**What "built-in" means here (`isInternal`).** `isInternal()` is true ONLY for functions compiled into the PHP runtime itself or a loaded C extension (the standard library: `strlen`, `array_map`, `system`, `file_get_contents`, …). It is **false for all userland PHP** — functions defined in `.php` source at runtime. So **WordPress core functions (`get_the_title`, `get_post_meta`, `wp_kses`, …), plugin/theme functions, and your own `{{call}}` functions all PASS the gate** — they are userland, not built-ins. The gate's sole job is to keep a raw C-level primitive (a shell/eval/filesystem call) off the allowlist; it does not, and is not meant to, judge WordPress or site code. (Allowlisting `get_the_title` directly would pass the gate but is pointless — `{{title}}` already covers it; the gate is a safety floor, not a usefulness filter.)

There is **no machine contract check**: site functions are untyped, so reflection cannot tell `my_result($post_id)` from `my_format($date_format)`. **post_id-first is a developer convention** upheld when allowlisting (the same act as vouching the function is safe to call), never machine-verified; a mis-signatured function mis-receives the post id, which is the file-access developer's responsibility.

**Argument.** post_id is **always position 0** (hardcoded). The optional single **Argument** is passed as position 1 only when non-empty (sanitized with `sanitize_text_field`); left empty, the function's own default fires (e.g. `$format = 'full'`). This collapses behavior variants (`full` / `short`) into argument values instead of separate named functions. (A multi-arg control is future; tag-level repointing of the post-id position is a future registration-level seam, never a tag option.)

**Output — verbatim, unescaped.** The function MUST return a string, surfaced raw. The **function owns its own escaping** — real functions return trusted display HTML (`<span>`, `&nbsp;`, `—`); the allowlist (developer-vetted) is the trust boundary, and double-escaping would break every real use.

**Failure taxonomy — 3 buckets.** **Bucket A** (function not allowlisted / `function_exists` false / fails `isInternal`) → fallback, plus an editor ⚠ warning (config/safety drift). **Bucket B** (post unresolvable / non-string-or-empty return) → fallback, silent (legitimate data-absence). **Throw/fatal** → caught (`\Throwable`), **always** logged to `error_log` (never debug-gated — a function fataling is a real error every time), output is the fallback, and **the exception message never reaches the page** (no leaking internals or paths). The catch exists because of the opacity — no base tag try/catches a field read, but `{{call}}` runs arbitrary site code.

**Editor preview — intentionally inert.** `{{call}}` is the **exception** to the plugin's normal value-preview behavior: most tags resolve a real value in the editor, but `{{call}}` deliberately does **NOT** execute the function to preview. This is a safety refusal — allowlisted functions are vetted for `isInternal`-safety, not purity/idempotency, so running them on every editor keystroke is unacceptable; and the loop-correct post id does not exist at editor time, so a run would mislead anyway. The preview is config-describing only (`Function: my_fn (arg) from Ref '…'`), with an empty-function warning. See [`editor-tag-previews.md`](editor-tag-previews.md).

**Distribution.** Pure developer tool: the plugin ships the tag, resolver wiring, security gate, failure handling, editor select, admin mirror, and the `bws_register_call_function` helper, but an **empty** allowlist and **no built-in functions** — it produces nothing until the site supplies both the function and an allowlist entry. The editor `fn:` select and a **read-only allowlist mirror** (Settings → Tag Extensions → Call Custom Function — function name + exists/passes-gate status) are the allowlist's two consumers.

**Options:**

| Option | Type / control | Label | Shown when | Notes |
|---|---|---|---|---|
| `src` | select | Source | always | `current` / `ref` ONLY (no `site`/`srcTermIn`); default `current` (stripped). Bespoke 2-value menu (`bws_call_source_option`). |
| `ref` | `bws-field-combo` | Relationship Field Key | `src:ref` | The related post the function runs on. |
| `fn` | select | Function | always | Allowlisted function name; options populated in PHP from the allowlist (`bws_call_fn_select_options`). Default empty (stripped). |
| `arg` | text | Argument | always | Optional single argument (position 1); sanitized; absent → the function's own default. |
| `fallback` | text | Fallback | always | Text output when the function is unavailable, returns nothing, or errors. |

**Wire-format examples:**

```
{{call fn:bws_get_game_result}}                                  → function output (current post)
{{call src:ref|ref:games|fn:bws_get_game_result}}                → output for the related "games" post
{{call fn:get_game_date_for_display|arg:short}}                  → output with arg "short" (else the fn default)
{{call fn:bws_get_game_result|fallback:—}}                       → "—" if unavailable / empty / errors
```

**Tests.** The pure helpers (allowlist read+normalize, security gate, argument builder) are pinned by a standalone, WP-free harness: `php tools/test/call-tag-test.php`. The GB-bound register/callback paths and the editor preview are exercised manually in a WordPress environment.

<a id="phone-deferred"></a>
**Deferred (not in 1.10.0):** display-side number formatting; an extension field (`ext`/`extKey` + separator) outside the link; a number-type label ("cell"/"office"); per-country trunk/length rules; per-tag `cc:` override (strip-flag safety); lenient passthrough of unparseable numbers as plain text. Tracked in the project deferred-features backlog.

---

## Email/phone modifier tags — `try_` and `term_` (1.11.0)

`email` and `phone` are registered as modifier templates, so the shared machinery generates the `try_` and `term_` variants for both — full parity with the standalone tags.

**`try_email` / `try_phone`** — fallback chains (up to 5 slots, first non-empty wins). Each slot resolves an email/phone field **exactly as the standalone tag would** and returns the finished `mailto:`/`tel:` (or plain) string; the chain surfaces the first slot that produces output. Per [I6 transparency](../CONTEXT.md), all composition (link-wrap, obfuscation, `tel:` rebuild, list-join) happens inside the slot's own resolve — the chain only picks the winning slot and joins its list.

- **Per-slot field key**, no per-slot `use` (single key-mode — no `use` enum, mirroring the base tags).
- **`src:site` slot is allowed** (re-allowed past the generic [#26 slot-src filter](#src-option-values)) — site is the canonical contact-fallback slot (personal address → site-wide address). The slot resolver has a `src:site` arm that reads the option (not current-post meta). datetime/text/image try_ slots still filter `site` (their site arm is deferred).
- **`subject`/`noLink`** (email) and **`noLink`** (phone) are chain-level options (inputs to each slot's own compose).
- **List mode** (`limit`/`sep`) — a slot in list mode (term-hop, or `src:ref`) joins its finished per-item strings, same as the base tag's list mode.
- **`visibility`** — the same `tagName NOT_IN ['a','button','img','picture']` gate plus a **runtime media-block backstop**: a media block's empty `tagName` slips the native gate, so the `try_` callback returns `''` inside a media block rather than corrupting the `<img src>` with a `mailto:`/`tel:` anchor. (The tag still *appears* in the media source picker — same documented limitation as the base tags.)

**`term_email` / `term_phone`** — read the email/phone field off a taxonomy-term entity (the term itself at `src:current`, or a related post at `src:ref`). Same compose path as `{{email}}`/`{{phone}}`.

> **No `src:site` on `term_*`:** the `term_` source dropdown deliberately omits `site`. A rooting modifier exists to surface entity-distinct data; a site read is entity-blind, so `term_email src:site` would just duplicate `{{email src:site}}` while discarding the term rooting (fails the [qualifying test](#qualifying-test-for-new-use-values) on both arms). For a site-option read use the base tag (`{{email src:site}}`) or a `try_email`/`try_phone` site slot. (`site` was filtered before 1.11.0 was tagged — it never shipped as an offered `term_` source. [#37](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/37).)

---

# Part III — Trackers

*Read on change. Cross-doc pointers, future templates under consideration, and how to keep this document current.*

## Editor tag configuration previews

The editor-time preview text shown in place of an unresolved tag while configuring it (markers, assembly, context/field parts, warnings, datetime + try_ shapes, examples) has its own authoritative doc: **[`editor-tag-previews.md`](editor-tag-previews.md)**. Built by `bws_build_preview_label()` in `includes/helpers/preview-helpers.php`.

---

## Potential future templates

These template types require their own option sets and formatting logic that `combine_text` cannot
replicate. Each would add a row to all applicable source matrices. The naming pattern follows
`datetime_single` / `datetime_range` — no special prefix; the template key is the type name.

| Template key | Description | Link support | Status |
|---|---|---|---|
| `number` | Format a raw numeric field: decimal places, thousands separator, currency symbol + position, optional prefix/suffix | No | To be considered |
| `phone` | Output a stored phone number; rebuild a `tel:` href from messy input (author separators preserved); 2-tier country code | `tel:` | **Built, unreleased** (slated 1.10.0, in testing) — see [Phone tag](#phone-tag) |
| `email` | Output a stored email address; can wrap output in a `mailto:` link | `mailto:` | **Implemented (1.9.0)** — see [Email tag](#email-tag) |

Image tags are excluded: multiple return formats are already built into image tag mechanics.

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

**When adding a new `src` value:** add a row to §Sources `src` option values (Part I) and the §Source group value table (Part II); document the traversal in §Source classes if a new resolver class is needed; update the §Source group secondary-options labels; note the new required sub-option in the affected per-tag section(s) if it brings one.

**When adding a new modifier prefix:** add a row to §Modifier prefixes; update §Base tag GB types if a new GB type string is introduced; document the registration call in [`docs/plugin-integration.md`](plugin-integration.md).

**When adding a new template:** add a row to §Base tag GB types (including the Link wrap, Tag title, and Term modifier title columns); **add a per-tag section** (prose + tag-specific options + panel order, linking the §Shared option groups it uses) — note required options + list-mode support there; if `supports_try`, add a row to §Available try_ tags; if it introduces a new shared option, add it to the relevant §Shared option group; **add its editor preview-text rows (field part, warning, example) to [`editor-tag-previews.md`](editor-tag-previews.md)** — preview text is no longer owned here.

**Deprecated wrappers:** never edit this doc for N×M deprecated wrappers — those go in [`docs/deprecated-tags-options.md`](deprecated-tags-options.md).

**In-progress / under-consideration renames** stay in this doc (in the relevant catalog section) until completed; on completion they move to [`docs/deprecated-tags-options.md`](deprecated-tags-options.md). Only **completed** renames live there.

For ownership boundaries against other docs, see [`CLAUDE.md` §Documentation ownership](../CLAUDE.md#documentation-ownership).
