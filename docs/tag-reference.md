# BWS Dynamic Tags — Tag & Option Reference

**Authoritative living reference** for template keys, source keys, option names, and which dynamic tag variants exist. Also owns this plugin's response to GB constraints (default-strip strategy, custom editor controls). Update this file whenever sources, templates, options, or controls are added, removed, renamed, or change default-enabled status. Other docs cross-reference here rather than maintaining parallel tables.

See [`CLAUDE.md` §Documentation ownership](../CLAUDE.md#documentation-ownership) for the full doc ownership policy and update triggers.

**How this doc is organized.** Three parts, each a different reader-mode:

- **[Part I — Concepts](#part-i--concepts)** — read once. The vocabulary and design models that make the catalog legible: output shapes, the source model & `src` values & analog resolution, the site source, modifier prefixes, list mode, the default-strip/serialization strategy, default-enabled logic, custom editor controls, the option layout & visibility model.
- **[Part II — Catalog](#part-ii--catalog)** — browse daily. A per-tag section for every base tag (`text`/`content`/`title`/`permalink`/`image`/`datetime_*`/`email`/`phone`) — prose + that tag's own options + its control order — plus the try_ tags. The options common to most tags are defined once in [§Shared option groups](#shared-option-groups); each per-tag section lists only what's tag-specific and links there.
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

Traversal selector on every base tag. Serializes as `src:<value>` in the tag string. This table is the **authoritative definition of what each `src` value resolves to**; the per-slot UI/serialization mechanics (slot-2+ `same`/`current` distinction, editor-preview segment, labels) live in [§Source control](editor-controls.md#source-control--per-slot-ui-and-serialization).

| `src` value | Resolves to | Status |
|---|---|---|
| unset (default) | Current entity (post or term per template context) | Implemented |
| `ref` | Reference/relational field step — requires `ref` sub-option (field key) | Implemented |
| `site` | Site-wide data (no entity) — an implicit-mode tag resolves the site analog, `key` reads an option. See [§Site Source](#site-source-srcsite). | Implemented (v1.9.0, Stage A) |
| *(a registered source key)* | Whatever that source's `resolve_id()` returns — post or term per its context type. Resolves whether or not the source is OFFERED (below). | Implemented |
| `parent` | WP parent post/term | Planned |
| `ancestor` | WP top-level ancestor | To be considered |
| `child` | WP child posts/terms (list output) | To be considered |
| `sibling` | WP same-parent posts/terms (list output) | To be considered |

See [§Source control](editor-controls.md#source-control--per-slot-ui-and-serialization) for label/UI details and the per-slot serialization mechanics; the option keys themselves are [§Source group](#source-group).

#### Root enum membership (1.17.0, [#83](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/83))

What an author can CHOOSE as a root is a shorter list than what RESOLVES as one, and the two are
deliberately not the same rule.

- **Offering is stated, never inferred.** A registered source appears in the root enum iff
  `is_selectable_root()` returns true (default false) *and* `is_source_enabled()` passes — the
  latter is the settings gate, so a term-context root follows the `term_` modifier toggle. The
  precondition for opting in is that the source **resolves its own id from ambient context**.
- **Opt-in rather than derived, permanently.** The registry accumulates non-offerable entries by
  policy and never sheds them (a `register_source()` call is never deleted for lacking resolve
  logic), so the four retired traversal-substitute sources and the internal `post`/`term` keys are
  registered right now and must stay out. A registry that keeps its dead is the wrong shape to
  derive an authoring enum from.
- **Offering is not resolving.** The flag governs the dropdown alone; the factory's registry
  delegation is untouched, so wire naming any registered source resolves either way. Load-bearing
  rather than incidental: wire is hand-editable by decision (ADR 0004), and an integrator flipping
  the flag off must not blank stored content. Pinned by mutation in
  `tools/test/traversal-pipeline-test.php`.
- **One appender, two surfaces.** `bws_registered_root_rows()` feeds both the base tag's root enum
  and the folded slot's source enum, so a root cannot be offered on `{{text}}` and be silently
  absent from a `{{join}}` field or a `try_` attempt. Rows APPEND after the built-ins, keeping
  `current` as the row an absent `src` stands for. Slots are ungated: the filter that keeps `site`
  off a rooting modifier is a judgement about an ENTITY-BLIND source, which a registered entity
  root is not.
- **Never on the shared base builder.** `bws_base_source_option()` does not grow these rows — the
  derived families (`term_*`, `try_*`, `{{table}}`, `{{call}}`) build their own surfaces from its
  rows, so a leak there would offer a root inside its own modifier family's Source dropdown and
  widen `{{call}}`'s deliberate allowlist.
- **Registered roots declare no static kind.** `BWS_FOLD_STATIC_ROOT_KINDS` stays as it is (only
  `site` is static); a chain rooted at a registered source resolves to the kind the factory
  determines at render, and the editor's step-offer filter stays permissive there.
- **A root key must be writable as a `src` token.** The filter route refuses a key that is a chain
  step slug (`refs`/`terms`/`entries`, read from `BWS_FOLD_STEP_TYPES` rather than re-typed), the
  slot inherit sentinel `same`, or one carrying a grammar character — each would parse back as
  something other than a root, offering a row that cannot resolve. Route A cannot hit this; its
  keys are class-authored.

Two registration routes, one registry — a source class overriding the flag, or a declarative spec
through the `bws_dynamic_tags_chain_roots` filter (adapted into a `Sources\CallbackRoot` and
registered normally, at registry init rather than at enum-build time). See
[`docs/plugin-integration.md` §1a](plugin-integration.md#1a-offering-your-source-as-a-chain-root).

#### Source CHAINS (1.17.0)

A source is a **chain**: a ROOT plus zero or more ordered **fanning** steps. The table above is the
root vocabulary; a step continues from wherever the previous one landed.

| Step slug | Follows | Resolves to |
|---|---|---|
| `refs,<field>` | a relationship / post-object field | `post` |
| `terms,<taxonomy>` | the entity's terms in that taxonomy | `term` |
| `entries,<field>` | a repeater field's rows | `meta_row` |

Chain wire lives in the same `src` option, `;`-separated, each step `slug[,arg][,limit(N)]` —
`src:refs,office;terms,category`. The flat spelling (`src:ref` + `ref:` + `srcTermIn:`) is READ
FOREVER and maps to the same chain, so nothing stored has to change; a tag is chain-spelled only
once an author converts it or the Tag Converter rewrites it.

- **The root is not a step.** A chain's first segment is either an entity root the source FACTORY
  consumes (`site`, `current`, a registry source) or already a step off the ambient entity.
- **A step `limit` is PER-INPUT** — at most N results from EACH incoming result, so limiting a
  `terms` step to one yields one term per referenced post rather than one term overall. Product
  semantics across a chain. Unlimited by default, for every step type. Distinct from the tag-level
  `limit`, which bounds the resolved-source list ONCE, before the read — see
  [§List mode](#list-mode-limit--sep). Its control is **Limit results**, and it is a position in
  this value rather than an option key — [§Chain step controls](editor-controls.md#chain-step-controls).
- **What a chain RESOLVES TO is the render path's dispatch axis** (`bws_fold_src_resolution()`:
  kind ∈ `post|term|meta_row|site|base`, plus whether it fans). Pure and static, from the wire
  alone — the editor's pickers and the list-mode reveal both scope themselves before anything
  resolves. Every base arm asks that one question instead of comparing `src` to `'ref'`/`'site'` or
  reading `srcTermIn`, which is what makes the two spellings take the same arm.
- **A source that is present but unusable makes the tag render NOTHING, and a stated fallback
  fires.** An unregistered root token, a registered source that resolves nothing in this context,
  and an unknown step slug all take this path; a base tag renders empty, a `try_` attempt is
  skipped, a `{{join}}` field is dropped. An **absent** source is not this case and still means the
  ambient entity. The rule and what decides it are [I15]'s (`CONTEXT.md`); the author-facing
  consequence for an integrator is in
  [`plugin-integration.md`](plugin-integration.md#what-a-non-resolving-source-renders).
- **`entries` is not offered on a base tag.** The step type exists and runs, but no base arm
  consumes a `meta_row` — that is the gap `{{table}}` fills with its own assembly. Authoring one
  needs a hand edit, and it renders nothing.
- **The derived families keep the flat select.** `term_*`, `try_*` and `{{table}}` build their own
  surfaces from the root enum's rows; a slot authors its chain inside its folded value instead
  (see [§Folded slot wire](#folded-slot-wire-multislot-containers)).

### Source-analog resolution

**Design principle.** Each base tag at its **implicit mode** (no explicit `use`/`key` — the stripped per-template default, recovered via `?? '<canonical>'` on read) resolves to the **best intrinsic analog datum for the active source — where one exists**. A tag should "just work" per context; named `use:`/`key` are **explicit-mode** overrides and escape hatches, not the primary path.

*Mode terminology:* a `use`/`key` selection is **explicit** (written in the string), **implicit** (absent but recoverable — the stripped default, or a mode implied by a present `key`/`ref`; a selection IS in effect even though the selector's default isn't serialized), or **unset** (no choice and nothing to recover, e.g. no `src` → current entity). "Implicit" ≠ "unset": the panel always shows a default selection. Implicit mode resolves the analog only at **base / slot 1** — inside a try_ slot, the same wire-absence means *inherit* (the implicit-in-slot collision), not analog.

| Base tag | post | term | user (author archive) | site |
|---|---|---|---|---|
| `title` | post title | term name | author display name | site name |
| `content` | post content | term description | author biographical info (`description`) | *(none — site has no long-form body datum; the tagline is short, and has no `content`-tag path — see note)* |
| `permalink` | post URL | term URL | *(not yet author-aware — deferred)* | site home URL |
| `image` | featured image | *(none — terms have no native image; key required)* | *(not yet author-aware — deferred)* | site logo *(via explicit `use:featured` — see note)* |
| `text` | *(keyed — no intrinsic analog; key required in all contexts)* | | | |
| `datetime_single` / `datetime_range` | *(field-keyed — no intrinsic analog; key/field required in all contexts)* | | | |

The **user** column resolves on an author archive only (ambient `WP_User`, #19 author kind, 1.15.0). Scope is `title`/`content` (1.15.0) + `text` (1.16.0: `use:title` → display name, key-mode → the author's user meta field). `{{join}}` slots inherit it through the text read seam; `try_text`/`try_title`/`try_content` slots resolve it too since 1.17.0, on their own dispatcher arm — the other six `try_` families follow their base tag and render empty there. `permalink`/`image`/datetime author analogs are deferred and render empty (not wrong) there. An explicit source (`src:site`/`src:ref`/`srcTermIn`) or a query-loop row overrides the author ambient, exactly as with the term column. `linkTo:permalink` on the author `title` links the author's archive URL.

Where a source has **no** intrinsic analog for a tag (term image, site content-body), the implicit-mode tag resolves empty and a `key`/field is required — the gap is honest, not papered over. (Site has no long-form content datum: its "Tagline" is a short string — WordPress itself frames it "In a few words…" — so it is *not* forced into the `content` slot. It also gets no dedicated `text` value, because it fails *both* sides of the gate — no unique affordance over GB's native `{{site_tagline}}`, and no strong cross-source analog (see the [qualifying test](#qualifying-test-for-new-use-values) below).) A *corollary*: a named `use:` value that would duplicate a datum already reachable elsewhere must not exist (e.g. no `use:home_url` when `permalink src:site` already = home URL). This keeps one canonical path per datum.

**Strip-default caveat.** The analog is reached by the tag's *stripped-default* `use` value, which is its **first enum value** — and that first value is **key-mode** for `text` and `image` (so their analog is NOT the empty-wire default). An empty `use` therefore resolves to key-mode for text/image (read a `key`), to `content` for `content` (which, under `src:site`, has no analog → empty). The site **logo** is the explicit `use:featured` value, *not* the implicit-mode `{{image src:site}}` (which is key-mode → empty without a key). `featured` is always serialized so the empty wire stays an unambiguous key-mode signal — there is no reliable way to tell a stale `key` from intentional key-mode if `featured` were the stripped default. Auto-unsetting the stale `key` when `use` leaves key-mode needs token authority that depends on custom-control work not yet built (see [`docs/editor-controls.md`](editor-controls.md), the owner doc for this control's mechanics). While that is unbuilt, **the stripped default is always key-mode**, named analogs are always explicit.

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
| `src:site` on a **single-slot rooted modifier** (`term_*`, `view_*`) | **No** — the site datum is the *identical* read the unrooted base tag gives (`{{email src:site}}`); the term/view rooting is discarded | **No** — site is entity-blind, so it fills no term-/view-distinct slot; a rooting modifier exists to surface entity-distinct data | **rejected** — `site` is filtered from the modifier's `src` dropdown. *Likely future home for "specific resource + site fallback" is an ID source (a probable `src:<type>,<ID>`-style construct — **not final**; the author-serialized-entity-id flavor, see CONTEXT.md §Language "Source binding") as a try_ attempt (which keeps its site attempt via `try_allow_site_slot`), NOT a `try_term_` form — `term_` is a transitional N×M surface on a deprecation glide-path (base tags + context-awareness + a pinned-resource source subsume it).* |
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

**Suppressed for site:** `srcTermIn` (no entity to step to terms from); `ref` — but only in the FLAT spelling, where a tag holds one `src` and `site` and `ref` are alternative values of it. A site-rooted relationship is a CHAIN (`src:site;refs,x`), which the engine has read since 1.17.0 and the source-chain control authors: an options page holds relationship fields like any other field store.

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

**A modifier family is the pre-chain way of spelling one option value (1.17.0).** Once its source is
offered as a [chain root](#root-enum-membership-1170-83), `{{view_text key:x}}` and
`{{text src:view|key:x}}` are the same read, and the second gets every capability added to base tags.
The Tag Converter rewrites the first into the second where the owning plugin registers entries for
its prefix. The mapping table and its rules live in
[`deprecated-tags-options.md` §Modifier prefix → base tag](deprecated-tags-options.md#modifier-prefix--base-tag-with-a-registered-root-1170);
the registration call is [`plugin-integration.md` §9](plugin-integration.md#9-migrating-a-modifier-family-to-a-base-tag).
No in-repo family is migrated — `term_` is deferred.

### Source classes

PHP entity resolvers used by base tag callbacks and modifier dispatch. Not surfaced directly in tag names.

| Source class | Context | Use |
|---|---|---|
| `CurrentPost` | post | the resolved starting entity in post context (`src:''` / `src:current`) |
| `TaxonomyTerm` | term | term_ modifier base; the term the factory resolves on a term archive |
| *(external source class)* | post or term | External modifier base, registered via `SourceRegistry::register_source()` |

**A source resolves a STARTING entity; it never traverses.** Since 1.14.0 the relationship hop
(`src:ref`) and the term hop (`srcTermIn`) are generic traversal STEPS the engine runs off whatever
entity the factory resolved (`bws_resolve_base_source()` → `bws_run_traversal()`, `includes/helpers/traversal-pipeline.php`; the L1/L2/L3 read model is in [`CONTEXT.md`](../CONTEXT.md)).

The four related-post classes — `RelatedPost`, `SecondRelatedPost`, `PostTermRelatedPost`,
`TermRelatedPost` — are **registered but INERT as of 1.17.0** ([#56](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/56)).
Their `resolve_id()` traversed a relationship field named by `rel` (or legacy `key`), a vocabulary no
other reader honours: the chain compiler builds its `refs` step from `ref` alone. They return `false`,
so the factory falls through to the ambient entity. Registration is kept (entries carry the source key
and context type, and both admin surfaces read the registry). Stored wire naming `src:related_post` is
rewritten to `src:ref` + `ref` — see the value row in
[`deprecated-tags-options.md`](deprecated-tags-options.md#option-name-renaming-tracker).

---

## List mode (`limit` + `sep`)

Selected templates support outputting multiple results as a delimited list. When more than one result renders, they are joined with `sep` (default: `, `).

`limit` bounds the **resolved-source list**, once, before the read — the last step's output. It never bounds values: the read is one value per resolved source with empties dropped afterwards, so `limit:3` can print two.

**`usable`** — what a limit is decided to COUNT ([ADR 0007](adr/0007-a-limit-counts-usable-results.md)): a candidate that survives to output. In this release that means **a non-empty read**; it tightens to **visible and non-empty** when the visibility gate ships ([`CONTEXT.md` §Language](../CONTEXT.md#language) owns `resolvable`/`visible`), so regression rows written against today's meaning are known to need revising. The paragraph above still describes what LIST-MODE tags ship today — the two statements disagree on purpose while the code migrates, and [`CONTEXT.md` I19](../CONTEXT.md#i19--a-limit-bounds-usable-results) is the invariant the code is moving toward. The collapsing tags are already there — see [§Collapsing tags](#collapsing-tags-first-usable-result).

_Avoid_: `usable` in USER-FACING copy (help text, labels, notices, README). The author-facing word is **results**, which both limit controls already use — and the shipped 1.17.0 Upgrade Notice already uses "unusable" in a different, resolution-level sense ("unusable sources output nothing"). The two senses never meet on one surface only for as long as this one stays model vocabulary.

**`limit` is interpreted in ONE place — `bws_clamp_limit( $raw, int $default )` (field-helpers.php).** Three call sites route through it: the seam (`bws_resolve_field_values`), the shared list fold (`bws_collect_value_list`), and try_ slot dispatch (`class-tag-template-registry.php`). `bws_try_join_items` takes an already-resolved int — it holds no options, so it structurally cannot know which default applies. The rule, as of 1.17.0:

| Value | Effective limit |
|---|---|
| unset / `''` | **the default for the tag's source SPELLING** — see below (not serialized when unset) |
| non-numeric (`abc`) | **the spelling's default**, exactly as unset — the `is_numeric()` gate. `(int)'abc'` is `0`, so without it a typo would read as *unlimited* on flat wire, where the default is 1 |
| `0` | **UNLIMITED** — no slice |
| `-1`, any negative | UNLIMITED. Parsed tolerantly (GB's *Posts Per Page* uses `-1`); `0` is what the plugin emits, matching WP's own Query Loop, which blocks `-1` and documents `0` |
| `1`, `5`, `999` | that value; there is no ceiling |

The number controls carry **no `min`** deliberately: a control that fights a hand-typed `-1` works against [ADR 0004](adr/0004-serialized-tag-string-human-readable.md), and the parse already tolerates it.

**The unset default is selected by the source SPELLING** (`bws_limit_default()`, 1.17.0). Flat wire — `src:ref`, `srcTermIn`, a bare tag — bounds its list at **1**, as it always has. Chain wire (`src:refs,x`) is **unlimited (`0`)**.

That one rule is the whole compatibility mechanism for [source chains](#source-chains-1170), and it is chosen because it works on wire **no migration can reach**: a draft nobody opens, a block widget the content scanner never sees, a tag stored inside an ACF field. An unmigrated tag gets its default from its own spelling, wherever it lives.

Two costs, both deliberate. The same conceptual source is bounded differently by spelling — an [ADR 0004](adr/0004-serialized-tag-string-human-readable.md) readability cost, paid to avoid touching a stored row, and confined to a spelling no panel can author any more. And the top-level link gate is COUNT-BASED, so link-wrapping differs by spelling too; that is why the regression matrix carries rows per SPELLING, not only per `limit` value.

**The flat SPELLING is closed; the flat READ is not deprecated.** Two statements that sit next to each other and say different things. An author can no longer write flat `src:ref` / `srcTermIn` from any panel — the chain control absorbed those siblings — but every stored instance is read forever, and so is an explicit tag-level `limit:N`. Neither has a deprecation path in 1.x: the population is unenumerable (a draft nobody opens, a block widget the scanner never sees, a tag inside an ACF field), so removing either read would be a permanent silent output change on tags nobody can find. Revisiting that is a major-version decision. See [ADR 0005](adr/0005-limits-are-stated-where-the-source-is-stated.md).

The `default` parameter is **required**. A site that omitted it would silently render legacy behaviour on chain wire — wrong output that looks normal in review — so omission is an `ArgumentCountError`, the same posture the cross-language twin harnesses take on a missing `node`.

**A SLOT'S OWN SPELLING DECIDES ITS OWN DEFAULT** (1.17.0, #60), on `{{join}}` and every `try_` template, exactly as a base tag's does. `{{try_text A:src(terms,department);use(title)}}` returns every term, matching `{{text src:terms,department|use:title}}`; the flat `{{try_text srcTermIn:department|use:title}}` still returns one.

Getting there needed a seam change, because the question was not answerable where it used to be asked. Through 1.16.x a container's slot loop resolved against the FLAT triple the since-deleted `bws_fold_slot_flat_options()` returned, and flattening is what ERASED the spelling — `bws_limit_default()` read off that triple saw a legacy token on every slot and answered 1 whatever the slot was spelled as. So the seam **reports the era it erased**, through a `$limit_default` out-param beside its existing `$skip_reason` one, and the loop uses that rather than inferring. The out-param outlived the flatten deliberately: `bws_fold_slot_chain_options()` now emits chain wire on every slot, including one recovered from legacy flat keys, so a loop re-deriving the default from `src` would answer *unlimited* where the slot has always bounded at 1.

Two qualifications, each of which is a real case rather than a hedge:

- **Only where the slot's OWN chain fans.** A slot spelling `src(same)`, or an argless `src(refs)`, fans solely by INHERITING an earlier slot's source. Giving it the chain default would make a migrated `{{join A:src(refs,office,limit[1])…|B:src(same)…}}` return every related post at B where the flat wire it replaced returned one. The predicate (`bws_fold_chain_fanning_steps()`) is shared with the migrator's stamp, so a slot can never take a default the migration declined to state.
- **A SELECTING container's inheriting slot inherits the BOUND with the source** (1.17.0, #61). `src(same)` names the same source and a limit is one of a source's parameters, so `bws_fold_slot_chain_options()` carries the quantity the earlier slot resolved. That is what let `try_`'s tag-level `limit` be retired without moving output. `{{join}}`/`{{table}}` are the deliberate contrast: they own `limit` PER SLOT (`{N}-limit`), so an absent one there is a slot saying "I state none" and must take the default — carrying it would move shipped `{{join}}` output.

Migration states what the old spelling implied, so no stored tag changes output — the slot half writes through the same `bws_fold_chain_apply_legacy_limit()` the base half does (`{{join srcTermIn:department|use:title}}` → `{{join A:src(terms,department,limit[1]);use(title)}}`). One case is NOT redundant with the chain default and must keep its carrier: an explicit `limit:0`. The same mapper renders UNMIGRATED flat wire, which takes the flat era's 1, so dropping the token would re-bound a tag its author deliberately unbounded. Rows: [`tools/test/fold-test-matrix.md`](../tools/test/fold-test-matrix.md) §F7a and §F7b.

An EXPLICIT value beats the spelling-selected default in both directions. That is ordinary option precedence and needs no extra rule.

**A tag-level `limit` is legacy by POSITION, not by spelling** (v1.17.0, [#62](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/62)). Wire that is ALREADY a chain used to be skipped whole — there is no spelling to respell — which left the one shape where a bound is invisible: `{{text src:terms,department|use:title|limit:1}}` renders one term while the step's own Limit field reads unlimited and no control can reach the key. Both migration paths now absorb such a number onto the step it bounds and delete the key. **NUMERIC ONLY on that branch**, which is the whole difference from the flat one: the flat branch materializes the flat era's default of 1 when the key is absent or unreadable, because the spelling it leaves behind meant 1, where chain wire is not changing era and has no default to carry — materializing one would bound a tag that renders unlimited today. A stand-down (non-numeric, a chain that states its own step limits, a chain that does not fan) is a NO-OP rather than an identical rewrite, so a tag nobody can improve is not re-serialized every time it is opened.

**Migration does not write one, though** — A LIMIT IS STATED WHERE THE SOURCE IS STATED ([ADR 0005](adr/0005-limits-are-stated-where-the-source-is-stated.md)), so a rewritten tag carries its limit on the STEPS (`src:refs,x,limit(1)`) and no tag-level `limit` at all. One table, both depths — a base tag's `src` and a folded slot's chain go through the same owner:

| legacy `limit` | what migration writes |
|---|---|
| absent, or non-numeric | `limit(1)` on EVERY fanning step; the tag-level key is untouched (a non-numeric value is left as the author typed it) |
| explicit `N > 0` | `N` on the LAST fanning step, `1` on every earlier one; the tag-level key is DELETED. In a SELECTING container `limit` is TAG-level (`try_slot_axes`) and is each attempt's own default rather than a bound across attempts, so the number is COPIED onto EACH fanning slot's own last step — a slot already folded included — and the key is retired with the rest (#61) |
| explicit `0` / `-1` | no STEP limit — unlimited is what chain wire already means — and **at depth 0 the KEY is deleted too** ([#62](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/62)). It survived until the tag-level control retired, when it stopped being a value an author could see and clear and became a token on chain wire nothing in the editor can reach; deleting it is faithful because the chain spelling already selects unlimited. Only the EXPLICIT value goes — an absent or non-numeric one is not the author's token to delete — and only where the chain fans, since a stated per-step limit stands the whole mapping down. In a SELECTING container the key is retired anyway (#61): a slot recovered from LEGACY keys keeps an explicit `limit[0]` carrier, because that same mapper renders unmigrated flat wire; an already-folded slot needs none, since its chain already defaults to unlimited |

Positional, not `terms`-specific: `refs` takes the `N` when `refs` is the last fanning step. The earlier steps are not decoration — per-step limits are per-input and MULTIPLY, so `N` on the last step alone would yield `N` per parent rather than the `N` total the flat spelling meant. An argless fanning step gets nothing — it resolves to nothing at all, so there is no spread to bound — and a chain that already carries a step limit is left entirely alone. See [`bws_fold_chain_apply_legacy_limit()`](../includes/helpers/slot-fold.php) — one mapping, shared by the converter, the editor mount migrator and the author-conversion commit.

**Known migration-fidelity gap: two fanning steps, explicit `limit:N > 1`.** The `1`-on-earlier/`N`-on-last mapping is exact for a chain with one fanning step at any depth, and for the absent/non-numeric case at any depth. It is NOT exact when the legacy source fanned through TWO steps (e.g. a relationship hop followed by a terms hop) — the old flat code sliced the first `N` of a parent-major FLATTENED walk (all of parent 1's results, then parent 2's, …), which can spill across parents when parent 1 has fewer than `N` children. Per-step limits are per-input and multiply rather than accumulate across parents, so no assignment of step numbers reproduces that spill — `1` on the earlier step visits only the first parent, so a migrated tag can render FEWER items than the flat tag did whenever that parent alone has fewer than `N`. Confirmed **zero live instances** across the two sites surveyed for the 1.17.0 migration (`docs/design-history/per-step-limit.md` §Site survey); left as a known incompleteness rather than a mechanism, since no fixture exists to design one against.

**Behavior change in 1.17.0.** Before, `0` and negatives were silently clamped to 1 by a `max( 1, … )` at each call site. A saved `limit:0` therefore rendered one result; from 1.17.0 it fans out. That was a clamp discarding a written value, not a designed semantic — an author wanting one result leaves `limit` unset or types `1` — so the change honors the wire rather than freezing the clamp. Regression rows: [`tools/test/limit-default-test-matrix.md`](../tools/test/limit-default-test-matrix.md).

| Template | List mode | What is iterated |
|---|---|---|
| `text` | ✅ | Terms (when `srcTermIn`) or related posts (when `src:ref`) |
| `title` | ✅ | Same as above |
| `content` | ❌ | Long-form prose (single-result) |
| `permalink` | ❌ | Single-result URL |
| `image` | ❌ | Single-result media |
| `datetime_single` | ✅ | Terms (when `srcTermIn`) or related posts (when `src:ref`) — each date formatted individually, joined by `sep` (see note) |
| `datetime_range` | ✅ | Same — each **whole formatted range** is one result; `sep` joins ranges, `rangeSep` stays the intra-range start–end separator (see note) |
| `email` | ✅ | Terms (when `srcTermIn`) or related posts (when `src:ref`) — each valid address wrapped individually, joined by `sep` |
| `phone` | ✅ | Terms (when `srcTermIn`) or related posts (when `src:ref`) — each valid number wrapped individually, joined by `sep` |

Term-modifier tags (`term_text`, `term_title`, etc.) inherit the same list-mode rule applied at their `src:ref` traversal.

**List collection is ONE fold (FW-49, 1.16.0):** every list-mode branch — text/title srcTermIn + src:ref, `datetime_single`/`datetime_range` per-term / per-ref-target (shipped with [#30](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/30) via a datetime-local fold, converged 1.16.0) — collects through `bws_collect_value_list()` (field-helpers.php): empty items are skipped, the list is sliced to `limit` and joined with `sep`, the `fallback` is suppressed per item and fires once on all-empty output, and link-wrap applies only when exactly one result renders — each collected value carries a link identity (`{kind,id}` or none; CONTEXT.md I12), and the single-result rule is a join constraint, not a linking one. Two separators on the range tag: `sep` between whole ranges, `rangeSep` between each start and end.

### Collapsing tags (first-usable result)

`{{content}}`, `{{permalink}}` and `{{image}}` have no list mode — there is nowhere for a second
result to go. Each declares the **`takes_first_usable`** template capability
([`class-tag-template-registry.php`](../includes/classes/class-tag-template-registry.php) descriptor
docblock owns the axis; [ADR 0007](adr/0007-a-limit-counts-usable-results.md) the decision): the tag
searches every candidate its chain resolves — whether the chain resolves to terms or to posts — and
outputs the first usable one. `try_content`, `try_permalink` and `try_image` inherit it from their
base templates, per attempt.

Consequences, each stated once here:

- **Every `limit` on the chain is inert, at every position** — stamped by migration or typed by
  hand, step-level or the tag-level key. A bound on how many results to show is meaningless where
  only one can be shown, so the number is ignored rather than narrowing the search. Enforced where
  the chain compiles to steps (`bws_fold_chain_to_steps()` `$ignore_limits`), so both step
  assemblers behave identically and neither the engine nor any callback carries a special case.
- **Stored wire is untouched.** A saved `limit` stays exactly as authored and starts applying again
  if the tag type changes. The tag-level `limit` read is control-retired, not deprecated
  ([ADR 0005](adr/0005-limits-are-stated-where-the-source-is-stated.md)) — ignored here, never
  removed.
- **The editor offers no Limit results control on these tags** (base chain and `try_` slots both),
  and the field configuration note stops predicting several results there —
  [`editor-controls.md` §Chain step controls](editor-controls.md#chain-step-controls).
- **`{{table}}` declares the capability explicitly false** (its registration in
  `table-tags.php` says why silence would be wrong there).

Selection runs through `bws_collect_usable()` (field-helpers.php) at n = 1 — the same selector the
`try_` emit loop donated — so the rule exists once and the next tag that needs it inherits it.

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

Option definitions declare semantic tokens (`current`, `key`, `content`, etc.) as their first value so the source files read naturally. `bws_prepare_registration_options()` (in [`registration-helpers.php`](../includes/helpers/registration-helpers.php); named `bws_strip_default_select_values()` before v1.17.0) runs at registration time and flips the first option's `value` to `''` for any option we want stripped from the saved tag string. GB drops `''` values from serialization; callbacks then apply `?? '<canonical>'` defaults on read to recover the semantic token.

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

### `as` serialization opt-out + `as`+`size` fold (`image`, `term_image`, `try_image`)

For image tags, the `as` option is **always serialized** — `{{image as:url,full|...}}` even when unmodified. Not stripped at registration. Justification: `as` controls the output mode (image src vs. alt text vs. caption vs. ID). Surfacing it in the saved tag makes the return mode immediately visible when copying a tag instance between fields, so a user can change `as:url` → `as:alt` in one edit instead of inspecting the option panel.

**The mechanism is `'default' => 'url,full'`, and it is the whole mechanism.** Not stripping is necessary but not sufficient: GB writes an untouched option to the wire only if it was seeded, and it seeds from non-empty `default`s at tag-**select** time ([`gb-constraints.md` §Option Serialization Order](gb-constraints.md#option-serialization-order) — `updateDynamicTag`). v1.16.0's fold dropped the `default` on the theory that the composite would write `url,full` on mount; it writes on change only, so `{{image}}` carried no `as` at all until v1.17.0 restored it. Mount-**seeding** would have been the wrong repair — a tag with no `as` at all was never legacy wire, so writing one on open would mean opening a tag's panel edits it (the rule the folded-slot control's `stripDefaultRoot` exists to hold). GB does not validate a `default` against the option rows, so the folded `url,full` seeds even though it is not one of them. Pre-existing tags with **no** `as` keep it absent and render identically via the read seam below.

**A pre-existing bare `as:url` is a different case, and it IS completed (v1.17.1) — by the Tag Converter.** An `as` that is present but partial is legacy wire: the token exists and does not say what the rule above says it must say. `bws_migrate_image_as_bare_url()` rewrites it to `as:url,full`. It leaves an absent `as`, an already-sized `url` and every nullary mode alone. **Render equivalence is not the test** — a bare `as:url` renders exactly as `as:url,full` does, and the opt-out exists for legibility in a copied tag string, not for output; a rewrite that changes no output is what an always-serialize rule is for.

**And it is Tag-Converter-ONLY.** The fold is unreachable in the editor because it must read and clear `size`; the completion touches only `as`, so it looks reachable and is not. A mount effect was built and backed out; the mechanism is owned by [`editor-controls.md` §Why the image composite does NOT migrate on mount](editor-controls.md). What makes the converter safe is ORDER: its fold entry is registered first, so a tag carrying both a legacy `size:` and a bare `as:url` is folded to `as:url,<authored size>` and never reaches the completion.

One consequence to know when editing a pre-1.16.0 image tag: the composite writes the whole `as` token on any change, so touching a control on a legacy split tag writes `as:url,full`, and from that moment the tag renders at full size — a size carried inside `as` wins over a separate `size:` in the read seam above. The `size:medium` token itself survives in the string (GB re-emits it from private state) until the converter folds it, by which time it is genuinely stale. **Run the Migration Tool before opening such tags.** A tag in a block widget is out of the scanner's reach and keeps its bare `as:url` until an author edits it.

**`as` is a parameterized return-type selector; size folds into its value (as+size fold, v1.16.0).** Most return modes are nullary (`id`/`alt`/`title`/`caption` — no argument); `url` is unary — it takes a **size** argument (size changes *which* url). So size rides inside the `as` value as a comma second slot rather than a separate `size:` option:

```
as:url,<size>    // url(size) — always serialized, default full included: as:url,full
as:<mode>        // nullary return (id/alt/title/caption) — bare mode, NO size arg
```

- **Wire schema:** `as:<mode>[,<size>]`. Size sub-slot present **iff** mode is `url`. Size enum = registered sizes (see `bws_get_image_size_options()`), default `full`, never stripped (rides the `as` opt-out). No interior `,,` possible — a nullary mode has no arg slot.
- **GB `image-size` support DROPPED.** Image tags no longer register `'image-size'`; the `bws-as-size` composite control (`assets/js/as-size-control.js`) renders the mode dropdown + a size dropdown (shown only under `url`) and owns the whole `as` token. GB's native size control is retired — the composite ships pretty size labels GB never had (respects the `image_size_names_choose` filter).
- **Read seam:** cores parse `as` via `bws_parse_as_option()` (`includes/helpers/image-helpers.php`), which splits `mode,size` and falls back to a separate `size:` then `full`, so a tag still holding the flat `size:` key renders at its size.
- **`size` fold migration — Tag Converter ONLY:** `bws_migrate_image_as_size_fold()` (a `transform_callback` migration, `includes/tags/deprecated-tags.php`) folds a legacy `size:` (bare or per-slot `N-size:`) into `as` value-conditionally — into `url,<size>` for url, dropped for a nullary mode (size was dead there). It runs **through the Tag Converter, which rewrites the raw tag string before GB parses it**. It does **NOT** run on editor-open, and no control-side fold can replace it: `size` is GB-reserved, so GB destructures it into its own private `imageSize` state and re-serializes it even though the tag no longer registers `image-size` support — unreachable from `extraTagParams` (see [`gb-constraints.md` §Reserved keys are destructured into GB-private state](gb-constraints.md#reserved-keys-are-destructured-into-gb-private-state-and-re-serialized-even-when-unsupported)). Opening such a tag in the editor therefore leaves its `size:` token in place (reordered, not folded); the read seam above keeps it rendering correctly meanwhile. Its entry matches on the **`size` key only** — matching `as` too (through v1.17.0) reported every image tag for work the callback declines, a list no author action could clear.
- **Bare-`as:url` completion — Tag Converter ONLY too:** `bws_migrate_image_as_bare_url()`, gated on an `as` value of exactly `url` (or the hand-edited `url,`). No mount path, and the paragraph above says why the one that looks reachable is not. Registered **after** the `size` fold, and the order decides the outcome: a tag carrying both a legacy `size:` and a bare `as:url` matches both entries, and the cascade takes the first that changes, so the fold wins and keeps the authored size. Reversed, this entry would write `url,full` first and the fold would then read an `as` that already carries a size and drop the legacy `size:` as stale. Pinned by `tools/test/as-size-fold-test.php` §A4-A5. A legacy per-slot `N-as` is left alone: post-fold the tag-level token governs every attempt, so completing one would carry dead wire forward looking live.

All other image options follow the standard rule. `as` is the documented exception.

---

## Custom editor controls registered

Registered via the `generateblocks.editor.tagSpecificControls` JS filter. Each entry maps a custom option `type` string (referenced in PHP option definitions) to a React control:

| Control type | Renders | Source file | Used by |
|---|---|---|---|
| `bws-media-picker` | `wp.media()` modal; persists attachment ID (re-fetches preview URL via `wp.data` `core` `getMedia(id)`) | `assets/js/image-tag-controls.js` | `image`, `term_image`, `try_image` fallback |
| `bws-as-size` | Composite: return-mode `SelectControl` + a size `SelectControl` shown only under `url`. Owns the whole `as` token, folding size into its value (`as:url,medium`; nullary modes serialize bare). Size enum + pretty labels localized from PHP (`window.bwsImageSizes` ← `bws_get_image_size_options()`). Last-picked size stashed in React state so `url→alt→url` restores it (wire stays model-pure). Replaces GB's native `as` select AND `image-size` control. | `assets/js/as-size-control.js` | `as` on `image`, `term_image`, `try_image` |
| `bws-term-hop` | CheckboxControl + ComboboxControl over public taxonomies (via `wp.data` `core`). Reads `pickLabel` / `pickHelp` from PHP option config in addition to `label` / `help` | `assets/js/term-hop-control.js` | `srcTermIn` option on base + modifier tags + per-slot in try_ tags |
| `bws-format-input` | TextControl that escapes `:` / `\|` on save and unescapes for display, so format strings containing colons (e.g. `g:i A` time tokens) survive GB's JS `parseTag()` round-trip | `assets/js/format-input-control.js` | `format` option on `datetime_single`, `datetime_range` |
| `bws-slot-fold` | The slot REPEATER: owns one folded slot value whole (source chain + field read + per-slot options), parsing and emitting it only through the grammar twin `assets/js/slot-fold-grammar.js`. Explicit add/remove slot count; removal compacts, materializing inherited axes first so a `same` backreference cannot silently re-point. Renders `bws-field-combo` against a synthetic context for the field pickers (the shipped control is unmodified; it takes the repeater scope as an explicit `scopeKey` prop). Every enum, label and noun arrives on the PHP option definition's `fold` sub-array from `bws_build_fold_slot_options()` — the control hand-authors no vocabulary. Recovers legacy flat keys at mount and rewrites the slot on first commit. | `assets/js/slot-fold-control.js` | folded slot keys `A`, `B`, … on `{{join}}` and `try_*` (v1.17.0); `{{table}}` arrives folded. Three read SHAPES, all read off the derived config rather than the container name: kind enum + picker, picker alone (a key axis with no `use` enum), or no read at all. See [§Folded slot wire](#folded-slot-wire-multislot-containers) |
| `bws-src-chain` | The BASE tag's source chain: a root plus ordered fanning steps, each with an optional per-step limit. Renders the SAME step component the folded-slot control uses (`window.bwsSlotFoldRepeater.chainSteps`) — a second renderer is where a hand-authored third spelling of `terms` gets in. Every enum, label, noun and slug map arrives on the PHP option definition's `fold` sub-array from `bws_build_src_chain_option()`. Reads a legacy tag's flat `src`/`ref`/`srcTermIn` as the chain they describe (display only, so a cancelled modal leaves stored wire untouched); the first commit writes chain wire, deletes the flat siblings, and carries the limit the old spelling implied onto the STEPS (`limit(1)` on each fanning step, visible in the step's own Limit field; an author's tag-level `limit` MOVES onto the last fanning step rather than staying behind) — because chain wire defaults to unlimited and a conversion that wrote nothing would fan the tag out under the author's hands. One mapping, `bws_fold_chain_apply_legacy_limit()`, shared with both migration paths so a converted tag and a scanned one are byte-identical. Edits the SOURCE only; the base tag keeps its own `use`/`key`. | `assets/js/src-chain-control.js` | `src` on `text`, `content`, `title`, `permalink`, `image`, `email`, `phone`, `datetime_single`, `datetime_range`. NOT on `term_*`/`try_*`/`{{table}}` — those derive their own surfaces from the root enum, and a slot authors its chain inside its folded value |
| `bws-field-combo` | Discovery-backed field picker: a searchable `ComboboxControl` over the field envelope inlined as `window.bwsFieldEnvelope` (assembled once per editor load from the REST route `bws-dynamic-tags/v1/fields`, no runtime fetch), plus two `SelectControl` filters above it (**location** — a path tree `Post/Term/Site fields › group › container`, container fields flagged `(repeater)`/`(group)`; **type** — ACF type or "Loop fields"). Flat list, one row per `(kind, key, label)`; a key in several groups collapses and shows under each, distinct labels stay separate. Serializes the **bare key** as a plain string (option `value` is a private merge key; the `valueToKey` map strips it in `onChange`), so it is a pure render swap for the old `text` input. Free-text via a synthetic "Use custom key" option; clear via `allowReset`. Reads optional `dynamicLabel` (label tracks the active location's group/kind) and `labelPrefix` from PHP option config. Composes with the conditional-options filter (`if (!element) return element`). Offered keys are filtered through `GenerateBlocks_Dynamic_Tag_Security::DISALLOWED_KEYS` server-side (offered ⟺ resolvable). | `assets/js/field-combo-control.js` + `includes/rest/field-discovery.php` | `key` (base/content/email/phone), `ref`, `linkKey` (`labelPrefix:'URL'`), datetime `key`/`timeKey`/`startKey`/`startTimeKey`/`endKey`/`endTimeKey`, and their `N-` per-slot try_ equivalents |

Image size selection is the `bws-as-size` composite (above) as of v1.16.0 — GB's native `image-size` support is dropped and size folds into the `as` value (see [§`as` serialization opt-out + `as`+`size` fold](#as-serialization-opt-out--assize-fold-image-term_image-try_image)). (History: a `bws-img-size` ComboboxControl was tried then retired mid-1.6.0 for GB's native support; the fold now retires the GB control in turn.)

---

## Option layout & visibility

The cross-tag model for **how options are ordered in the editor panel** and **how show/hide conditions are expressed**. Each per-tag section in Part II gives its own ordered list; this section is the shared schema those lists follow.

**Four groups (descriptive names; the legacy `Group 1/2/3` placeholders are retired):**
- **`source` — per-slot**: source selector → source secondary options (`ref`, `srcTermIn`, `sep`) → field options (`use`, `key`). Within-source order is `src → ref → srcTermIn → limit → sep → use → key` — `sep` precedes the field options because list length is a property of whether the **source** can return multiple results, not of the field read. Repeated for each try_ slot. **`limit` appears in the SERIALIZATION order only, and holds no control on either axis** — since v1.17.0 no tag registers one ([ADR 0005](adr/0005-limits-are-stated-where-the-source-is-stated.md): a limit is stated on the step it bounds), but stored wire still carries the key and the normalizer still has to rank it, so it keeps rank 3 in `serialization-order.php` and no row in the visual-group map.
- **`format` — global formatting**: `as`, format options, separators. Not per-slot; applies to the assembled result.
- **`link` — link-wrap**: `linkTo` + dependent `linkKey` + `newTab` (or, on email/phone, the own-anchor `subject → noLink` set). A contiguous, role-defined cluster; treated as its own group so ordering can move it as a block.
- **`fallback` — global fallback**: `fallback`. Once, after all slots.

**Two canonical orders (see [§Option order](#option-order) for the full model):**
- **Control order** (panel top-to-bottom) = `source → format → link → fallback`.
- **Serialization order** (tag string left-to-right) = `format → source → link → fallback`.

The per-tag control lists in Part II are stated in **control order**. Show/hide conditions are noted inline in each per-tag list; all other options are always visible.

### Option order

**Two orders, defined against two references.** The **control order** — how controls stack top-to-bottom in the editor panel — is independent of the **serialization order** — how options appear left-to-right in the saved tag string. GB drives both from the same `extraTagParams` object, and the plugin decouples them with a per-tag reorder normalizer (built v1.16.0 — FW-52). The GB fact that makes this possible (GB's own `post_date` renders format above link yet serializes link before format) lives in [`gb-constraints.md` §Serialization order is independent of control order](gb-constraints.md#serialization-order-is-independent-of-control-render-order--gb-itself-proves-it). This section defines the canonical orders both axes aim at.

**Which GB-native controls the plugin actually uses — only ONE.** GB gates every native control on a registered `supports` value (`tagSupports(tagData, X)`, `DynamicTagSelect.jsx:193`, `:269-274`): a control renders (and its reserved key serializes) ONLY when the tag registers support `X`. GB reserves the key names `source`, `id`, `key`, `link`, `size`, `dateFormat`, `required`, `tax` — but reserving a name and *using* the machinery are different. The plugin's tags register **empty `supports` arrays** (`base-tags.php:69,131,177,211,290,303,320`) except `image`/`term_image` = `['image-size']` (`:234`); and it does not register any source into GB's `sourcesInOptions` filter (default `[]`, so GB never serializes `source:`/`id:` for our tags either). So:

| GB-native mechanism | Gated by | Plugin uses it? | What the plugin uses instead |
|---|---|---|---|
| Source selector, `source:` / `id:` serialize | `'source'` support / `sourcesInOptions` | **No** | custom `src` control |
| Meta key control, `key:` serialize | `'meta'` support | **No** | custom `key` option |
| Link controls, `link:` serialize | `'link'` support | **No** | custom `linkTo` / `linkKey` / `newTab` |
| Taxonomy control, `tax:` serialize | `'taxonomy'` support | **No** | custom `srcTermIn` |
| Date Format control, `dateFormat:` serialize | `'date'` support | **No** | custom `as:date\|time\|both` + format tokens |
| Image Size control, `size:` serialize | `'image-size'` support | **No** (as of v1.16.0) | custom `bws-as-size` (`size` folded into `as`) |

Verified 2026-07-22 (`base-tags.php` supports arrays + `DynamicTagSelect.jsx:269-274`, `:513`, `:695-817`; no plugin reference to `sourcesInOptions`). **As of v1.16.0 the plugin uses NO GB-native reserved control at all** — the last one, Image Size, was retired by the `as`+`size` fold (below). **Every option this plugin uses is a CUSTOM option** → all within the reorder normalizer's reach. The reorder governs 100% of the custom-option surface (source/field/link/format/fallback). Everything below is the canonical order for those custom options.

**`size` folded into `as` (v1.16.0).** The `as`+`size` composite (`bws-as-size`) removed `'image-size'` from image's supports arrays and folded size into the custom `as:url,medium` value — so `size` is now a custom option and no GB-native reserved control remains anywhere. See [§`as` serialization opt-out + `as`+`size` fold](#as-serialization-opt-out--assize-fold-image-term_image-try_image).

**One transient source exception — `term_*` tags.** The `term_*` modifier tags register with GB **type `'term'`**, which triggers GB's native term source + taxonomy machinery (`'term' === dynamicTagType` paths serialize `id:`/`tax:` and render the native term/taxonomy pickers without a `supports` entry). So on `term_*` specifically, source IS partly GB-native. This is the lone remaining GB-native usage, and it is **temporary — `term_*` is on the deprecation glide-path** (base tags + context modifiers subsume it; see [`docs/future-work.md`](future-work.md) term_ deprecation). Dropping `term_*` removes the native term source, after which the plugin uses NO GB-native controls at all.

**Control order — author custom controls at GB's single injection point.** Since the plugin registers almost no GB-native supports (table above), all the plugin's own controls inject together at GB's single `tagSpecificControls` slot (`DynamicTagSelect.jsx:819`). So the control order is essentially the plugin's to define wholesale — GB contributes only the tag selector (top) and the Required checkbox + Insert button (bottom). **Canonical control order: `source → format → link → fallback`** — the author picks *what to read* (source/field) before *how to display it* (format), then link, then fallback. Format renders early in the panel, matching GB's own `post_date` (Date Format renders ABOVE Link To). Note this is the INVERSE of the serialization order below, where format leads and link precedes it.

**Control order IS registration order, and since v1.17.0 it is a correctness property.** GB renders `options` as declared; nothing reorders them (the FW-52 normalizer moves the *serialized* key order only, inside `setState`). So the registration arrays in [`base-tags.php`](../includes/tags/base-tags.php) and the two constructors in [`class-tag-template-registry.php`](../includes/classes/class-tag-template-registry.php) *are* the panel. [Option grouping](editor-controls.md#option-grouping-visual) draws a box around the controls that describe one decision, and a group boxes as ONE box only where its members register **contiguously** — the CSS joins adjacent siblings and can see nothing else. A group registered in two pieces draws two boxes for one decision; a member stranded away from its group draws a box of its own with nothing to name it. Both are pinned by [`tools/test/control-order-test.php`](../tools/test/control-order-test.php), which asserts contiguity across every registered tag.

The three constructors may legitimately place a group differently — `term_*` leads with its format cluster, base and `try_*` do not — but none may split one. Until v1.17.0 the `try_` constructor did: it registered format FIRST (i.e. in *serialization* order, on the one family that renders a format cluster), put `fallback` ahead of `link`, and appended the chain-level `limit`/`sep` last of all, where — being source-group options — they drew a captionless box at the foot of the panel. `term_*` carried the `fallback`-before-`link` half of the same bug. Both fixed in v1.17.0; the harness is what keeps them fixed.

<a id="option-grouping-visual"></a>
**Option grouping (visual)** — moved to [`docs/editor-controls.md` §Option grouping (visual)](editor-controls.md#option-grouping-visual): the four visual groups, their owners (`bws_option_visual_groups()`, `option-group.js`), the lead-boxes-alone rule, and captions-belong-to-controls.

**Serialization order — `format` group leads, among custom options only.** The canonical serialized order for the custom options is (corrected 2026-07-23 — link moved after source, see below):

1. **`format`** group — `as` (image: the folded `as:url,<size>` — see [§`as` serialization opt-out + `as`+`size` fold](#as-serialization-opt-out--assize-fold-image-term_image-try_image)), format/separator tokens (serialize-early so the return mode is visible up front when copying a tag).
2. **`source`** group, **per slot, contiguous** — for slot *N*: `N-src`, `N-ref`, `N-srcTermIn`, `N-limit`, `N-sep`, `N-use`, `N-key`, then the datetime field keys (`N-timeKey`, `N-startKey`, `N-startTimeKey`, `N-endKey`, `N-endTimeKey`) — canonical within-slot order (`limit`/`sep` precede the field keys: list length is a source property). Each slot's keys stay adjacent; slots ascend `1-…`, `2-…`, … Global (non-`N-`) source keys sort as slot 0. A **folded slot key** (`A`, `B`, … — [§Folded slot wire](#folded-slot-wire-multislot-containers)) ranks here too, at its own slot and ahead of every named source key: the folded value IS the slot's whole source-and-read, so it holds the position `src` would. Tag-level source keys are slot 0, so they still precede it.
3. **`link`** group — the `linkTo`/`linkKey`/`newTab` cluster (custom — NOT GB's reserved `link`) OR, on email/phone, the own-anchor set `subject → noLink`. A role-based group (link-affecting controls, whichever mechanism); one set per tag. Placed **after source** because link is source-relative (`linkTo:post/term` links the resolved entity; `linkKey` reads a field off it) — matching GB's own `post_meta`/`post_date` `source → field → link` serialize chain.
4. **`fallback`** group — `fallback`, last.

**Format-front is a deliberate departure from GB.** GB's own `post_date` serializes format LAST (link before `dateFormat`); we invert to format-FIRST for manual-edit copy-visibility. Everything else (`source → field → link`) mirrors GB's chain. (The `post_date` render≠serialize proof is a pure GB fact — see [`gb-constraints.md`](gb-constraints.md#serialization-order-is-independent-of-control-render-order--gb-itself-proves-it).)

(No exceptions as of v1.16.0: image's former GB-native `size:` was folded into custom `as` by the `as`+`size` fold, so every option in the list above is a custom option the normalizer controls.)

**Serialization order ≠ control order is intentional** (GB itself does this — see [`gb-constraints.md`](gb-constraints.md#serialization-order-is-independent-of-control-render-order--gb-itself-proves-it)). Our control order is `source → format → link → fallback` (format-early in the panel); our serialization order lifts `format` to front (`format → source → link → fallback`). The two are reconciled by a per-tag JS normalizer ([`serialization-order-normalizer.js`](../assets/js/serialization-order-normalizer.js), canonical map mirrored PHP-side in [`serialization-order.php`](../includes/helpers/serialization-order.php)) that rebuilds `extraTagParams` in the canonical serialization order inside `setState` (transform = lift `format` to front + keep each `N-` slot contiguous), gated per-tag-name via `tagSpecificControls`. Built v1.16.0 (FW-52); design rationale + build constraints (Strategy-1 `N-`-prefix block detection, registration-standardization unwind, two-writer coexistence) in [`.scratch/plans/combined-option-controls.md` §Phase 3+ + §Grill outcomes 2](../.scratch/plans/combined-option-controls.md).

**`show_if` condition types** (implemented in `assets/js/editor-conditional-options.js`):
- `'not_empty'` — passes when option has any value
- `'empty'` — passes when option is unset/blank
- `'not:value'` — passes when option does not equal `value`
- `'value'` (literal string) — passes when option equals that exact string
- `'in:v1,v2,...'` — passes when option equals any listed value *(new)*
- `'not_in:v1,v2,...'` — passes when option equals none of the listed values *(new)*

Multiple conditions in one `show_if` map are AND'd. Array-of-conditions per key is not implemented.

## Folded slot wire (multislot containers)

**One option key per slot** (FW-56/57, v1.17.0). A multislot container registers keys `A`, `B`, … — each of type `bws-slot-fold` — and the whole slot lives in that key's **value**: its source chain, its field read, and its per-slot options. This replaces the six flat keys per slot (`{N}-src`/`ref`/`srcTermIn`/`use`/`key`/`limit`, slot 1 bare) the same containers registered through v1.16.x.

**Shipped in:** `{{join}}` and `try_*` (v1.17.0). `{{table}}` arrives folded.

```
{{join A:key(name_first)|B:src(same);key(name_last)}}
{{join mode:template|format:%A (%B)|A:src(refs,office,limit[2]);key(name)|B:src(same);use(title)}}
```

**The key IS the slot ordinal, spelled `A`…`Z`** (`AA` at 27, spreadsheet-style). One owner, `bws_slot_ordinal()` / `bws_slot_ordinal_num()` in the grammar file, because the same answer is needed by two registrations, both migration paths, the editor control, both order parsers, the panel labels and `{{join}}`'s format tokens. Three consequences:

- **No maximum in the grammar.** The pattern is `^[A-Z]+$`, not `^[A-Z]$` — the slot count is a CONTAINER property (`{{join}}` 10, `try_*` 5), and a single-letter pattern could reject wire its own encoder produced.
- **Nothing may compare these keys as strings.** `AA` sorts after `Z` numerically and before it lexically; both order parsers DECODE to an int.
- **Collision-free by construction.** Every option key in the plugin and every GB reserved key is lowercase or lowercase-initial camelCase, so an all-caps key can only be a slot.

The **legacy `N-` sibling prefixes stay digits** (`2-src`, `2-key`). That wire was already written with digits; re-spelling its reader would orphan every pre-1.17.0 tag. Only the folded key moved.

**Grammar** (owner: [`includes/helpers/slot-fold.php`](../includes/helpers/slot-fold.php); JS twin `assets/js/slot-fold-grammar.js`):

| Construct | Separator / bracket | Notes |
|---|---|---|
| tokens in a slot value | `;` | `,` also accepted on parse, never emitted |
| steps in a chain | `;` | inside `src(…)` |
| slug / arg / limit within a step | `,` | `refs,office,limit[2]` |
| value brackets | `()` at level 1, `[]` at level 2 | **alternation by depth**, never a pinned char: `limit(3)` on a base tag's own `src:`, `limit[3]` inside a slot's `src(…)` |
| `+` `/` | — | **RESERVED**, unspent: never separators, ordinary content inside a value |

`}` never appears (GB's tag parser rejects it anywhere in a tag's options — [`gb-constraints.md`](gb-constraints.md)), which is why the wire is brace-free and the format control's tokens are `%A`…`%J` (following the slot keys; `%1`…`%10` is still read and always will be — both alphabets collapse to one internal token, so nothing downstream can tell them apart).

**Step slugs are wire vocabulary, and the engine's step types follow them:** `refs` (relationship), `terms` (taxonomy), `entries` (repeater rows), plus `same` (inherit) and the base `src` values (`current`, `site`, …). One map holds the correspondence (`BWS_FOLD_STEP_TYPES`), so the layers *can* diverge; the values are deliberately identical so a reader has no translation to hold.

**A chain is a ROOT plus N STEPS**, and which one the leading token is is decidable from the slug alone — root slugs singular, step slugs plural. The plural spelling is a **category marker, never a count claim**: a **fanning** step *may* resolve many and routinely resolves one (a relationship field limited to 1, a single-term taxonomy). See [`CONTEXT.md`](../CONTEXT.md) I14.

**Read axis is resolved by NAME, never by token order:** `use` wins unless it is `key`; otherwise `key(…)` supplies the read. This mirrors the shipped `$use = $options['use'] ?? 'key'` dispatch, so no tag changes meaning under the fold. With a field chosen the canonical spelling of a keyed read is the bare `key(x)` — `use(key)` is emitted only for the **field-pending** state (keyed read, no field yet), which the editor needs a wire spelling for because the control re-parses the value it just wrote to drive the read select.

**Container sensitivity is on the READ axis, and only on what ABSENCE means.** An explicit `use(same)` inherits everywhere. An absent read is **unconfigured** in a combining container (`{{join}}`, `{{table}}` — the slot is skipped, and skipped *before* it can feed the carry-forward) and **inherit** in a selecting one (`try_*`). Source absence is not container-sensitive: `src(same)` inherits, an empty chain resolves against the ambient entity.

**`src(same)` inherits the whole SOURCE, taxonomy step included** ([#74](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/74)). A term step is part of what a source IS, unlike `limit`, which is a *parameter of* a source and stays container-sensitive (above). Uniform across both containers and both wire eras — there is no era gate, so a legacy tag and its folded twin inherit identically. Two qualifications: an inherited hop is a DEFAULT, so a slot that states its own `terms` step REPLACES it rather than colliding with it; and the hop travels with the source, so a slot stating its own root does not acquire it. Invariant: [`CONTEXT.md` I15](../CONTEXT.md).

**An INCOMPLETE step skips the slot.** A step with no argument is authorable only under the fold (flat wire could not state a step without its argument), and flattening it would be silently wrong: an empty argument is how *no step* is spelled, so the slot would read the un-stepped entity and return a plausible wrong value. The slot is skipped instead, with its own skip reason naming which step is unfinished, so the editor preview can say what is missing ([`editor-tag-previews.md`](editor-tag-previews.md)). One asymmetry: an argless `refs` step is COMPLETE when the carry supplies its field, and incomplete only when nothing was ever carried; an argless `terms` step has no such inheritance.

**A `same` root with nothing to be the same AS skips too.** At slot 1 an absent source means the ambient entity; at slot ≥2 absence means inherit, so ambient is not a default a slot can fall back to. Where every earlier slot skipped, the carry holds nothing and the slot says nothing rather than resolving against the ambient entity ([`CONTEXT.md` I15](../CONTEXT.md)).

**`limit` folds onto the step it bounds** (a chain can fan more than once, so a slot-level limit has no single meaning); a flat limit with no fanning step stays a slot-level token. A **step `limit` is PER-INPUT** — at most N resolved sources per input source — which is a different quantity from the **tag-level `limit`**, bounding the resolved-source list once before the read. `0` = unlimited, as everywhere else ([§List mode](#list-mode-limit--sep)).

**Both FORMS render.** Slot configuration is read per slot: folded value present ⇒ parsed; absent ⇒ recovered from the flat keys through the same mapping the migrator and the editor use. So a half-migrated tag (folded slot 2 between flat slots 1 and 3) resolves as its author last saw it, threading **one** carry-forward accumulator. The editor rewrites a slot to folded form the first time it is touched.

**Serialization: a folded key ranks as its slot's source** — after the `format` group, after any TAG-level (slot 0) source key, before `link`/`fallback`, slots ascending. So [§Option order](#option-order) needs no qualification: `format` leads on every tag, folded or not.

**Why that is worth stating.** Until 2026-08-04 the slot keys were all DIGITS, and digits are not an ordinary key: an all-digit key is a JS array-index property, which ECMAScript enumerates before every string key regardless of insertion order, and GB serializes with `Object.entries( extraTagParams )`. Neither `bws_serialization_order_sort()` nor its JS port could move them by rebuilding the object, so both had to STATE that forced order — which cost `format`-first on `{{join}}`/`try_*` and tag-level-first on `{{table}}`. Capitals are ordinary string keys, so ordering came back to the sort. The spelling is otherwise a wash (a digit prefix reads marginally better than a letter, the `%A` format tokens read better than `%1`), and the ordering is what decided it.

**The slot count is explicit.** The `bws-slot-fold` repeater adds and removes slots, and removal compacts (closing the hole re-points `same` backreferences, so inherited axes are materialized against the removed slot first). Nothing is inferred from how far configuration got, which is why the old combining reveal predicates are gone.

**A SLOT'S SOURCE IS HANDED ON AS CHAIN WIRE**, in the same option key a base tag states its source in, and that is the whole of why a slot's source is not a weaker thing than a base tag's ([`CONTEXT.md` I16](../CONTEXT.md)). Through 1.16.x the seam re-spelled every slot as a flat `src`/`ref`/`srcTermIn` triple, which holds ONE `refs` step and ONE `terms` step — so a second step on either axis, or a repeater `entries` step, was SKIPPED rather than resolved, because resolving the expressible PREFIX would silently read a different source than the wire states. The re-spelling was deleted in 1.17.0 ([#104](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/104)); the slot's registered step OFFER is the base tag's, and every slot's chain resolves whole.

Three things ride on that and are easy to get wrong from the code alone:

- **The emitted string differs from the stored slot value by design.** Brackets alternate by depth, and a slot's chain sits one level in, so emitting it at depth 0 turns `limit[3]` into `limit(3)`. Re-leveling only ever goes shallower, so it cannot run out of pairs.
- **The seam SUPERSEDES the legacy source axes**, returning explicit empties for `ref`/`srcTermIn`. A surviving tag-level `srcTermIn` would otherwise grow a term step onto EVERY slot's chain, since that is exactly what the chain reader does with one.
- **A `same` source inherits the prior slot's WHOLE chain**, hops included, and part of that inherited chain can give way to a step this slot states of its own. What decides how much is owned by `bws_fold_chain_join()` (slot-fold-compile.php) and stated nowhere else — see [`CONTEXT.md` I15](../CONTEXT.md).

**FIVE SKIPS REMAIN**, and they need different author-facing answers, so the seam reports which one it was rather than each preview re-deriving it: an unconfigured combining slot stays silent (a normal in-progress state), while an unfinished step (`no ref` / `no taxonomy` / `no repeater field`) and a `same` with nothing to inherit (`no previous source`) are flagged in the editor tag configuration preview ([`editor-tag-previews.md`](editor-tag-previews.md)). Rendered output cannot tell a skip from an empty read, because both print nothing. A separate INERT-chain warning covers a source that resolves nowhere (1.17.0, [#105](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/105)): [`editor-tag-previews.md` §Inert-chain warning](editor-tag-previews.md).

Harnesses: `php tools/test/slot-fold-test.php` (grammar + legacy mapping + render seam), `php tools/test/slot-fold-twin-test.php` (PHP↔JS agreement; needs `node`), `node tools/test/slot-fold-repeater-test.js` (repeater/compaction). Integration + editor rows: [`tools/test/fold-test-matrix.md`](../tools/test/fold-test-matrix.md).

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
| `join` | `'Join Fields'` | *(no term_ variant)* | `'cross-source'` | ❌ | **Structural outlier — not a base tag.** Standalone COMBINING tag: absorbs up to 10 base `text` reads as slots and assembles all non-empty values (separator or template mode). No read of its own; no per-slot link-wrap. See [§join](#join). |
| `call` | `'Call Custom Function'` | *(no term_ variant)* | `'post'` | ❌ | **Structural outlier — not a base tag.** Binds the loop-correct post (L1 only), then delegates to an allowlisted site PHP function; output is the function's return string, verbatim + unescaped. Type `'post'` (NOT `'cross-source'`) — no term/site/media/taxonomy features; `src` offers Current + Ref only. Ships with an empty allowlist. See [§Call tag](#call-tag). |

The term_ modifier produces additional tags with GB type `'term'`: `term_text`, `term_image`, `term_title`, `term_permalink`. `src` unset = user-selected term (never serialized); `src:'ref'` = term→related post traversal. `term_image` uses GB type `'term'`; `as` and `size` registered as custom options (same pattern as base `image` — `'media'` type not used on any image tag). `as` serialization exception applies to `term_image` as well — default `as:url` is always written to the tag string.

**`term_image use:featured` gating:** `use:featured` only valid on `term_image` when `src:ref` set. Term entities have no featured image; gate hides the option until a post-context traversal is selected.

**try_ modifier** produces `try_text`, `try_image`, etc. with GB type `'first-available'`. Up to 5 slots, one folded option key each; the slot count is explicit (add/remove), not revealed by configuration progress.

See [§Default serialization strategy](#default-serialization-strategy) for the registration-boundary mechanism that controls which option defaults survive into the saved tag string (and the intentional `as` opt-out for `image` / `term_image`).

---

## Try_ tags

`try_` tags are **entity-agnostic fallback chains**. A single tag tries up to 5 slots in sequence
and returns the first non-empty result. The user configures which traversal each slot uses at the
tag instance level — there is no source prefix in the tag name.

### Per-slot configuration

**One option key per slot** (v1.17.0) — `A`…`E`, folded values on the shared multislot wire. Grammar, era handling, carry-forward and serialization: [§Folded slot wire](#folded-slot-wire-multislot-containers). What is specific to `try_`:

```
{{try_text A:key(sku)|B:src(refs,rel_post);key(alt_sku)}}
{{try_title A:|B:src(refs,rel_post)|C:src(site)}}
```

Each slot holds a **source chain** and — depending on the template — a **field read**:

1. **Source chain** — `src(…)`, or absent. Slot 1 absent = the ambient entity; slot 2+ absent inherits the previous resolving slot, and `src(same)` says so explicitly. The `terms` step is offered as a chain step (`src(refs,rel;terms,category)`); it names **this** slot's entity and never carries forward.

2. **Field read** — three shapes, set by the template's `try_per_slot_use` / `try_per_slot_key` pair, and the control renders whichever the registration describes:

| Shape | Templates | Slot read UI | Absent read means |
|---|---|---|---|
| `use` enum + key picker | `try_text`, `try_content`, `try_image` | mode select, plus the field picker when the mode needs a key | inherit (materialized as `use(same)`) |
| key picker alone | `try_email`, `try_phone` | field picker only — no mode axis exists | inherit (an empty picker IS the inherit) |
| none | `try_title`, `try_permalink`, `try_datetime_single`, `try_datetime_range` | nothing — the read is a **tag-level** option | n/a |

**Slot 1 is never absent.** Every axis unset there is the default attempt — ambient source, template's default read — which is what a bare `{{try_title}}` renders. Slots 2+ are absent when they hold nothing, and are skipped.

A slot still needs a key to produce output where its read mode requires one; a keyless slot in a key-needing mode is skipped, not an error.

**The slot count is explicit** (add/remove in the repeater), replacing the progressive-disclosure cascade that revealed slot N+1 once slot N was configured. `sep` on list-mode templates is likewise unconditional now: a list axis lives inside a slot value, and `show_if` compares whole option values, so no honest reveal predicate exists. (It used to be the `limit`/`sep` pair; the tag-level `limit` control retired in v1.17.0 — [#62](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/62).)

### Available try_ tags

| Tag name | Based on template | Per-slot field key? | Per-slot `use`? | Notes |
|---|---|---|---|---|
| `try_content` | `content` | **Yes** | **Yes** | Each slot: Content/Description, Excerpt, or ACF/Custom Field (with per-slot key when `use:key`). Slot `src:site` allowed (1.15.0): `use:key` → option rich-render; content/excerpt analogs resolve empty under site (no site content analog) |
| `try_title` | `title` | No | No | Slot `src:site` allowed (1.15.0): site name; single-result link-wrap uses the site sentinel (home URL) |
| `try_permalink` | `permalink` | No | No | Slot `src:site` allowed (1.15.0): home URL |
| `try_text` | `text` | **Yes** | **Yes** | Each slot: Title/Name or ACF/Custom Field (with per-slot key when `use:key`). Slot `src:site` allowed (1.15.0): `use:title` → site name, `use:key` → option value |
| `try_image` | `image` | **Yes** | **Yes** | Each slot: Featured Image or ACF/Custom Field (with per-slot key when `use:key`). Slot `src:site` allowed (1.15.0): `use:featured` → site logo, `use:key` → option attachment |
| `try_datetime_single` | `datetime_single` | No | No | Shared `key` across slots |
| `try_datetime_range` | `datetime_range` | No | No | Shared `startKey`/`endKey` across slots |
| `try_email` | `email` | **Yes** | No | Single key-mode (no `use` enum). Each slot resolves an email field → finished mailto/plain string, exactly as `{{email}}`. Slot `src:site` allowed (canonical contact fallback). `subject`/`noLink` chain-level |
| `try_phone` | `phone` | **Yes** | No | Single key-mode (no `use` enum). Each slot resolves a phone field → finished tel/plain string, as `{{phone}}`. Slot `src:site` allowed. `noLink` chain-level |

---

## Shared option groups

Options common to most base tags, defined **once** here: names, labels, help text, values and
conditionals. Each per-tag section below lists only its tag-specific options and links back to these
groups. The control ORDER they slot into is [§Option layout &
visibility](#option-layout--visibility). What the editor RENDERS for them — which component owns a
group, the per-slot UI, the chain step positions, the field configuration note — is
[`docs/editor-controls.md` §Source and field control
mechanics](editor-controls.md#source-and-field-control-mechanics).

Option / required-option rules for deprecated N×M wrappers (e.g. `related_post_*`,
`term_related_post_*`, `custom_text`, `custom_image`, `term_custom_*`) live in
[`docs/deprecated-tags-options.md`](deprecated-tags-options.md), not here.

### Source group

The source selector and its conditional sub-options. Present on every base tag. In a **multislot**
container (`try_*`, `{{join}}`, `{{table}}`) the source axis lives inside the folded slot value as a
chain instead ([§Folded slot wire](#folded-slot-wire-multislot-containers)), so the `N-`prefixed keys
below are **legacy wire** — registered through v1.16.x, still read by the renderer, never written.

| Option name | Option label | Context | Notes |
|---|---|---|---|
| `src` | Source | Base / Slot 1 | `source` avoided — GB unconditionally strips it from extraTagParams before our controls can read it |
| `N-src` | [N]: Source | Slot 2+ *(legacy wire)* | Pre-1.17.0 multislot spelling; the folded chain replaces it |

For what each `src` VALUE resolves to, see [§`src` option values](#src-option-values). For its
per-slot labels and serialization mechanics, see [§Source control — per-slot UI and
serialization](editor-controls.md#source-control--per-slot-ui-and-serialization).

**Source secondary, conditional options:**

| Option name | Option label | Help text | Shown when | Notes |
|---|---|---|---|---|
| `ref` | Relationship Field Key | ACF relationship or post object field key. | `src` = `ref` | ACF relationship/relational field key for the traversal step. **Required** when `src:ref` selected. |
| `srcTermIn` | Get from taxonomy term? | Field is in a taxonomy term on this source. | Always; hidden for `term_` modifier tags (entity already a term) at `src:current`; shown at `src:ref` | The VALUE is a taxonomy slug and encodes both facts: empty/unset = no term step, a slug = term step on, in that taxonomy (so the slug is **required** when the step is on). Rendered by a combined control — [§Source control](editor-controls.md#source-control--per-slot-ui-and-serialization). |
| `limit` | ~~Result Limit~~ *(HISTORICAL label — no control registers it)* | Maximum number of results to return. Default: 1 on flat wire, unlimited on a source chain. Enter 0 for no limit. | **No control on any tag** as of v1.17.0 ([#62](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/62)) — the key is still READ wherever it is written, so unmigrated and hand-edited wire keeps rendering. Why the control was retired rather than gated: [§Source control](editor-controls.md#source-control--per-slot-ui-and-serialization) | Placeholder `1`; not serialized when unset; **`0` (or a hand-typed `-1`) = UNLIMITED** since 1.17.0, non-numeric reads as unset — see [§List mode](#list-mode-limit--sep). Bounds the WHOLE list; a chain's per-step limits are a different quantity (per-input) and live in the source value, not in an option key — [§Chain step controls](editor-controls.md#chain-step-controls). |
| `sep` | Result Separator | Separator between results (defaults to “, “). | `srcTermIn` set, or `src` = `ref` or a fanning chain (`chain_fans`) — unlike `limit` it DOES ask `chain_fans`, because it joins printed output whatever the source spelling; unconditional on a multislot container, where the list axis sits inside a slot value that `show_if` cannot inspect | `text`, `title`, `email`, `phone`, `datetime_single`, `datetime_range` and the `try_` list templates (`try_text`, `try_title`, `try_email`, `try_phone`) |

### Field group

The field-type selector (`use`) + field key (`key`). Present on `text`, `image`, `content`.
`title`/`permalink` have no field options (their datum is the analog); `email`/`phone` have no
`use` enum (key-required, no analog); `datetime_*` use direct field keys (see their section). In a
**multislot** container the read axis lives inside the folded slot value (`use(title)` /
`key(sku)` / `use(same)`), so the `N-`prefixed keys below are **legacy wire**.

| Option name | Option label | Context | Notes |
|---|---|---|---|
| `use` | [Text/Image/Content] Field | Base / Slot 1 | |
| `N-use` | [N]: [Text/Image/Content] Field | Slot 2+ *(legacy wire)* | Pre-1.17.0 multislot spelling |

**`use` field-selector values (where applicable):**

| Applicable tags | Option name | Option label | Conditionals | Notes |
|---|---|---|---|---|
| `text`, `image`, `content` | `same` *(prepended, slot 2+)* | Same as Previous Field | Hides additional fields | Slot 2+ only, not in template. Folded spelling `use(same)` — written explicitly there, where the flat wire left it absent |
| `text`, `image`, `content` | `key` | Meta/Option Field | Shows/enables field key | — |
| `text` | `title` | Title/Name | Disables field key | Term name if source is term; site name if `src:site` |
| `content` | `content` | Post Content/Term Description | Disables field key | Term description if source is term; **empty if `src:site`** (no site content analog) |
| `content` | `excerpt` | Post Excerpt | Disables field key | Empty under `src:site` (no site excerpt) |
| `image` | `featured` | Featured Image/Site Logo | Disables field key | Site logo (`custom_logo` theme mod) if `src:site` |

**`key` field key:**

| Applicable tags | Option name | Option label | Context | Notes |
|---|---|---|---|---|
| `text`, `image`, `content` | `key` | Meta/Option Field Key | Base / Slot 1 | Aligns with and substitutes for GB native `key` option name generated by `supports => ['meta']`, to avoid issues with GB's filtering and set our own order. Reads post/term meta normally, or a wp_options / ACF-options value under `src:site` (the field-type prefix tracks source scope — V10). **Required** when `use:key` (or the stripped key-mode default for text/image). |
| `text`, `image`, `content` | `N-key` | [N]: Meta/Option Field Key | Slot 2+ *(legacy wire)* | Pre-1.17.0 multislot spelling; a folded slot spells it `key(sku)` |

See [`datetime_*` section](#datetime_single-and-datetime_range) for the datetime-context label and
keys.

### Link wrap group

Available on `text`, `title`, `datetime_single`, `datetime_range` (base, `term_` modifier, and
`try_` variants). Excluded: `content`, `permalink`, `image`. (`email`/`phone` have their own
`mailto:`/`tel:` link mechanism — `noLink` — NOT the `linkTo` family; see their sections.) The
`link` group renders after `format` in control order, after `source` in serialization order.

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

Link wrap is applied **after fallback resolves** — fallback text is also wrapped if a link resolves.
On `try_` tags, the single `linkTo`/`linkKey`/`newTab` applies to the winning slot's entity (post or
term). `term_` modifier tags resolve entity type from dispatch path (term entity for base-source
dispatch; post entity for `src:ref` dispatch).

**`email`/`phone` are the exception — their link is NOT a `linkTo` option.** They do not participate
in the `linkTo`/`linkKey`/`newTab` family above (those wrap an *entity URL*). Their only link is the
`mailto:`/`tel:` for the address/number itself, **default-ON** and toggled by the inverted `noLink`
bare key (absent = wrap, present = plain text). Note the **opposite polarity**: `linkTo` defaults to
*no* wrap, whereas `noLink` defaults to *wrapped* — because the email's/phone's own address is the
only sensible link. The anchor is built directly (no class/target), not via `bws_wrap_with_link`.
`newTab` does not apply to `mailto:` (opening a mail client does not navigate). See [§Email
tag](#email-tag) / [§Phone tag](#phone-tag).

### Fallback group

The `fallback` option (the `fallback` group — global, last in both control and serialization
order).

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

**Control order** (`source → link → fallback` — no `format` group on `text`):
- **`source`:** `[source options]` → `use` (`key` (unset default in single-slot tags); `title`) → `key` (shown when `use` unset [in single-slot tags] or `use:key`)
- **`link`:** `linkTo` → `linkKey` (shown when `linkTo:key`) → `newTab` (shown when `linkTo` not empty)
- **`fallback`:** `fallback`

---

## `content`

Long-form prose: post content / term description (the analog), an excerpt, or a keyed field. Single-result (not list-mode); **not** link-wrappable (may already contain links). GB type `'cross-source'`; picker title `'Content/Description'`.

**Tag-specific options:** `use` values are `content` (default analog — post content / term description; **empty under `src:site`**), `excerpt` (post excerpt; empty under `src:site`), or `key` (**`key` required**). Uses [Source](#source-group) + [Field](#field-group) + [Fallback](#fallback-group); no [Link wrap](#link-wrap-group).

**Control order:** `[source options]` → `use` (`content` (unset default in single-slot tags); `excerpt`; `key`) → `key` (shown when `use:key`) → `fallback`

---

## `title`

The source's title/name analog — post title / term name / site name. Zero options aside from link-wrap; shares its pipeline with `text use:title`. Cross-source, link-wrappable, list-mode capable. GB type `'cross-source'`; picker title `'Title/Name'`.

**Tag-specific options:** none — no [Field group](#field-group) (the datum is always the analog, no `use`/`key`). Uses [Source](#source-group) + [Link wrap](#link-wrap-group) + [Fallback](#fallback-group).

**Control order** (`source → link → fallback` — no `format` group on `title`):
- **`source`:** `[source options]`
- **`link`:** `linkTo` → `linkKey` (shown when `linkTo:key`) → `newTab` (shown when `linkTo` not empty)
- **`fallback`:** `fallback`

---

## `permalink`

The source's URL analog — post URL / term URL / site `home_url()`. Output is already a URL, so it is **not** link-wrappable and has no field options. Single-result. GB type `'cross-source'`; picker title `'Permalink'`.

**Tag-specific options:** none — no [Field group](#field-group), no [Link wrap group](#link-wrap-group). Uses [Source](#source-group) + [Fallback](#fallback-group) (fallback is TBD — see [Fallback group](#fallback-group)).

**Control order:** `[source options]` → `fallback`

---

## `image`

A media field: the source's **featured image / site logo** analog (`use:featured`) or a keyed image field. Returns a URL, alt text, caption, or attachment ID per `as`. URL output is nonsensical to wrap, so **no** link-wrap; image-linking deferred. Single-result. GB type `'cross-source'`; picker title `'Image Fields'`.

**Tag-specific options:**

Control order `source → format → fallback` (no `link` group on `image`; `format` = the folded `as`+`size` composite). As of v1.16.0 the `bws-as-size` composite renders the return-mode dropdown and (under `url` only) a size dropdown as one control in the format group — GB's native image-size control is gone, so the whole format group is now under the plugin's control ordering.

| # | Group | Option label | Option name | Notes |
|---|---|---|---|---|
| 1 | source | | `[source options]` | [Source group](#source-group); no `sep` for image (and no tag-level `limit` control on any of them since v1.17.0) |
| 2 | source | | `use` | `key` (unset default in single-slot tags); `featured` — `featured` disabled for term-context entities unless `src` = `ref`; under `src:site` `use:featured` = logo |
| 3 | source | | `key` | shown when `use` unset [in single-slot tags] or `use:key` — **`key` required** in key-mode |
| 4 | format | Return As | `as` | folded return-mode + size (`bws-as-size` composite): `url,<size>` / `id` / `alt` / `title` / `caption`. Size sub-slot shown/serialized only under `url`. **Always serialized** (see [§`as` serialization opt-out + `as`+`size` fold](#as-serialization-opt-out--assize-fold-image-term_image-try_image)) |
| — | format | Image Size | *(sub-slot of `as`)* | Rendered by the same composite under Return As when it is `url`; folds into the `as` value (`as:url,medium`). Not a separate option key as of v1.16.0. |
| 6 | fallback | | `[fallback option]` | media picker → image ID; see [Fallback group](#fallback-group) + `custom-image-controls.md` |

---

## `datetime_single` and `datetime_range`

Format a date/datetime/time field (`datetime_single`) or a start–end **composite string** (`datetime_range`). List mode on `srcTermIn` / `src:ref` (shipped with [#30](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/30) — see [§List mode](#list-mode-limit--sep)). Link-wrappable (single result only). GB types `'cross-source'`; picker titles `'Format Date/Time Fields'` / `'Format Date/Time Fields as Range'`.

**Required:** `datetime_single` needs `key`; `datetime_range` needs `startKey` (`endKey` optional). Under `src:site` the keys read ACF options-page date fields via `get_field($key,'option')`. On a taxonomy archive a bare tag reads the ambient **term's** date field (1.15.0, FW-3a — same current-entity rule as text/title; previously post-only, honest-empty there). Uses [Source](#source-group) + `limit`/`sep` + [Link wrap](#link-wrap-group) + [Fallback](#fallback-group).

**Tag-specific options + control order** (rows in canonical control order `source → format → link → fallback`; numbers = panel position per template):

| Group | Option label | Option name | `datetime_single` | `datetime_range` | Values/Notes |
|---|---|---|---|---|---|
| source | | `[source options]` | 1 | 1 | `src` / `srcTermIn` / `ref` |
| source | Result Separator | `sep` | 2 | 2 | between results, default `, `; on the range tag joins **whole ranges** (`rangeSep` stays intra-range) |
| source | Date/Time Field Key | `key` | 3 | — | primary date/time field key |
| source | Time Field Key (optional) | `timeKey` | 4 | — | separate time field |
| source | Start Date/Time Field Key | `startKey` | — | 3 | |
| source | Start Time Field Key (optional) | `startTimeKey` | — | 4 | |
| source | End Date/Time Field Key | `endKey` | — | 5 | |
| source | End Time Field Key (optional) | `endTimeKey` | — | 6 | |
| format | Return As | `as` | 5 | 7 | `datetime`; `date`; `time` |
| format | Start & End Separator | `rangeSep` | — | 8 | separator between start and end values within one result |
| format | Custom Format | `format` | 6 | 9 | PHP format string; empty = auto |
| format | Date & Time Separator | `timeSep` | 7 | 10 | shown when `as` ≠ `date` AND `as` ≠ `time` AND `format` empty |
| format | Show current year in date? | `showCurrentYear` | 8 | 11 | checkbox, false by default; shown when `as` ≠ `time` |
| format | Show time when stored as midnight? | `showMidnight` | 9 | 12 | checkbox, false by default; shown when `as` ≠ `date` |
| link | Link To | `linkTo` | 10 | 13 | `permalink`; `key`; unset = no link |
| link | Link URL Field Key | `linkKey` | 11 | 14 | shown when `linkTo:key` |
| link | Open in new tab | `newTab` | 12 | 15 | checkbox; shown when `linkTo` not empty |
| fallback | | `[fallback option]` | 13 | 16 | |

**Design rationale:** Canonical control order `source → format → link → fallback`. Source selector + list-mode `sep` + per-slot field keys (`src`/`srcTermIn`/`ref` → `sep` → `key`/`startKey`/…) lead — the author picks *what to read* first. `sep` precedes the field keys (list length is a source property, not a field one). **No tag-level `limit` control** since v1.17.0 ([#62](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/62)) — these tags author their source as a chain, so a limit rides the fanning step; a stored `limit` is still read (see [§List mode](#list-mode-limit--sep)). Global formatting (`as`, `rangeSep`, `format`, `timeSep`, `showCurrentYear`, `showMidnight`) follows. Link cluster, then `fallback`, close. **NB the serialization order differs** (`format → source → link → fallback` — format lifts to front for copy-visibility); the reorder normalizer reconciles the two (built v1.16.0, FW-52).

> **⚠ CODE PENDING (FW-52 build).** The time-field keys (`timeKey`/`startTimeKey`/`endTimeKey`) shed their `as ≠ date` `show_if` reveal — they now render unconditionally with the source group (a field key is *what to read*, independent of *how to format*). The option definitions in [`base-tags.php`](../includes/tags/base-tags.php) still carry the old `show_if`; this doc reflects the FW-52 target. Reconcile on build.

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
| `src` | `bws-src-chain` | Source | always | The source CHAIN: a root (`current` / `site` / a registry source) plus ordered fanning steps, each with its own optional limit. Absorbs the flat `ref` / `srcTermIn` controls, which are no longer registered (v1.17.0) though a stored value still reads and shows as a step. |
| `sep` | text | Result Separator | `srcTermIn` set, `src:ref`, or a fanning chain | List-mode join; default `, `. |
| `key` | `bws-field-combo` | Meta/Option Field | always | **Required** — email field key. wp_options / ACF-options (dot-path) under `src:site`; post/term meta otherwise. |
| `subject` | `bws-format-input` | Subject | `noLink` empty | Optional `mailto:?subject=`; escaped editor-side, `rawurlencode`d at render (see two-layer encoding above). |
| `noLink` | checkbox (bare key) | Disable email link (plain text) | always | Inverted presence flag: absent = mailto wrap (default), present = plain text. |
| `fallback` | text | Fallback Email | always | A fallback **email address** (validated, wrapped). Fires only when no valid address resolves. |

No tag-level `limit` control since v1.17.0 ([#62](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/62)): the source is a chain, so a limit rides the fanning step that it bounds. A stored `limit` still bounds the list — see [§List mode](#list-mode-limit--sep).

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
| `src` | `bws-src-chain` | Source | always | The source CHAIN: a root (`current` / `site` / a registry source) plus ordered fanning steps, each with its own optional limit. Absorbs the flat `ref` / `srcTermIn` controls, which are no longer registered (v1.17.0) though a stored value still reads and shows as a step. |
| `sep` | text | Result Separator | `srcTermIn` set, `src:ref`, or a fanning chain | List-mode join; default `, `. |
| `key` | `bws-field-combo` | Meta/Option Field | always | **Required** — phone field key. wp_options / ACF-options (dot-path) under `src:site`; post/term meta otherwise. |
| `noLink` | checkbox (bare key) | Disable phone link (plain text) | always | Inverted presence flag: absent = tel wrap (default), present = plain text. |
| `fallback` | text | Fallback Phone Number | always | A fallback **phone number** (normalized, wrapped). Fires only when no valid number resolves. |

No tag-level `limit` control since v1.17.0 ([#62](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/62)): the source is a chain, so a limit rides the fanning step that it bounds. A stored `limit` still bounds the list — see [§List mode](#list-mode-limit--sep).

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

## `join`

**Standalone COMBINING tag (1.15.0)** — the counterpart to `try_`'s *selecting*. Where a `try_`
chain returns the FIRST non-empty slot, `{{join}}` visits EVERY slot (collect-all, never
short-circuits), keeps all non-empty values, and assembles them into one output string. It is
neither a base tag nor a modifier: it resolves no read of its own — each slot **absorbs** a full
base `text` read via the extracted seam (`bws_base_text_resolve_value`, 1.14.1), so every current
and future text behavior (the `'0'`-is-a-real-value rule, the site arm, term/ref list modes,
loop-row context, term-analog arm) works inside a join slot by construction. One GB tag
(`'Join Fields'`, type `'cross-source'`), no prefix fan-out, no per-source variants.

**Slots.** Up to **10** (`BWS_JOIN_MAX_SLOTS`), on the **folded slot wire** (v1.17.0 — one option
key per slot, [§Folded slot wire](#folded-slot-wire-multislot-containers)). Per slot: a source
chain (base `src` values with **site allowed** — the `try_text` site-slot gap is not repeated —
plus a `terms` taxonomy step), the field read (text's key/title enum, **no "Same as Previous Field"
row** because per-slot handlers are not built yet — a hand-written `use(same)` still resolves), and
a per-step `limit` (list-mode bound so a term/ref slot reads >1 target; no control surface yet, but
migrated and hand-written values round-trip). Slot ≥2 offers `src(same)` — weave several fields off
one entity (see J16b in the matrix for real ref carry-forward). A list-mode slot joins its own
items with text's default inner `', '` — no per-slot inner separator in v1
([ADR 0003](adr/0003-join-per-slot-limit-not-sep.md): the v1 decision was to thread the slot limit
only). NB the wire-collision that ADR 0003 cited (a slot-1 bare `sep` clashing with the tag-level
assembly `sep`) dissolved twice over — first when the assembly key was renamed to `valueSep`
(1.16.0, FW-52), then under the fold, where a slot's options live inside its own value. Still
deferred.

**Slot count (v1.17.0).** Explicit add/remove in the slot repeater; removal compacts. Through
1.16.x the count was inferred from configuration and slot N ≥ 3 revealed when the previous slot
had a `key` or a non-default `use` (the combining-shaped reveal — never its `src`, which is
default-empty in combining); those predicates are gone with the flat keys they tested. Legacy wire
still renders unchanged, and touching a slot rewrites it to folded form.

**Tag-level options.**

| Option | Type | Notes |
|---|---|---|
| `mode` | select | `''` = Separator (default, stripped) / `template`. **Format group** (serialization). |
| `valueSep` | text | Assembly separator between non-empty slot values, default `', '`. Shown in separator mode. Values are not trimmed — `valueSep: ` is a literal space. **Format group** (serialization). Renamed from `sep` (1.16.0, FW-52) to free the key name for the list-mode source-group `sep` — a slot-value joiner is a format concern, not a source one. |
| `format` | text | Template-mode format string with **`%A`…`%J` positional tokens** (the slot keys) and optional **`~…~` unit groups**. `%1`…`%10` also read. Shown in template mode. **Format group** (serialization). |
| `fallback` | text | Renders when ALL slots resolve empty; absent → `''` (GB hides the block). **Fallback group.** |

`mode`/`valueSep`/`format` are join's **format group** — they sort serialize-early per the canonical serialization order (see [§Option order](#option-order)). They are NOT the source-group list-mode `sep`, which stays `sep` and joins repeated results of one field.

**Wire token syntax `%A` (GB constraint response).** GB's tag matcher rejects `}` anywhere in a
tag's options (captured as `[^}]+` — kills the whole tag match, no escape;
[`gb-constraints.md` §Tag-string-unsafe values](gb-constraints.md#tag-string-unsafe-values)), so
brace tokens `{A}` can never ride the wire. Authors write `%A`…`%J`; `%%` escapes a literal
percent directly before a slot letter. Internally `bws_join_wire_format()` translates to the
canonical `{N}` form the pure algorithm uses.

**Tokens follow the slot KEY spelling (1.17.0)**, so `{{join A:key(x)|format:%A}}` reads as one
statement rather than two. `%1`…`%10` is **still read and always will be** — both alphabets
collapse to the same internal token at that one translation point, so nothing downstream can tell
which the author typed, and hand-pasted pre-1.17.0 wire keeps working ([ADR 0004](adr/): the wire
is hand-editable and paste-portable). Stored digit tokens are deliberately NOT re-spelled.

What DID have to migrate is the **literal**: before 1.17.0 a `%` not followed by a slot DIGIT
passed through untouched, so `Up 10%APR, paid %1` was legal stored wire whose meaning changes the
moment letters tokenize. `bws_migrate_join_format_escape()` escapes it to `%%`, gated on wire ERA
(a folded slot key cannot predate the letters) because literal-or-token is undecidable from the
format string alone — and a wrong guess would destroy an intended token.

**Template mode — smart literal removal.** After token substitution, punctuation attached to
EMPTY tokens is removed with them (ordered steps; full contract + edge cases pinned by
`php tools/test/join-template-test.php`):

0. **`~…~` unit groups (1.15.0)** — wrap a token and its unit text in tildes so they live or die
   together: `~%E lbs.~` sheds whole (including adjacent separators, Step 3 rules) when `%E` is
   empty; with any token inside non-empty the delimiters unwrap invisibly and the contents run
   Steps 1–5 normally. Covers what Steps 1–3 can't: space-separated literal words belonging to a
   token (`%E lbs.` with empty `%E` keeps `lbs.` — bind it: `~%E lbs.~`). `~~` = literal tilde; a
   lone unpaired `~` and a token-less group render literally. `~` rides the GB wire unescaped
   (verified: both GB parsers pass it through raw).
1. **Attached punctuation** — unit punct (`.` `'` `"`) trailing-attached sheds with the empty
   token (`%A'%B"` with empty inches → `5'`); connective punct (`,` `:`) collapses only when the
   empty token sits BETWEEN two connectives (`%D %E, %F` with empty generation keeps ONE comma:
   `Smith, PhD`), else survives as the neighbors' separator.
2. **Bracket pairs** around an empty token removed (`%A (%B)` → `Jane`); kept around a survivor.
3. **Floating separators** (`·` `•` `/` `|` `-` `–` `—`) adjacent to an empty token removed
   (look right; the format's last token looks left).
4. **Whitespace collapse** + edge-orphan cleanup; a trailing `.` survives only when the format
   intentionally ends with one. Trailing quote marks never stripped (a surviving `5'` is intent).
5. **Single survivor** sheds remaining connective separators; literal text stays (`Mr. %A · %B`
   → `Mr. Smith`).

**Unit marks and `wptexturize`.** WordPress's `wptexturize` converts straight `'`/`"` to curly
quotes (`5'11"` → `5’11”`). It is **not** a global output filter: core registers it on
`the_content`, `the_title`, and `the_excerpt` (priority 10) only. Whether join's output hits it
therefore depends on **which render path the block sits in**, not on the tag:

| Render path | `the_content` runs? | `format:%A'%B"` renders |
|---|---|---|
| Page/post body — static block **or** a GB query loop inside it | yes — the whole rendered body is filtered | `5’11”` (texturized) |
| GP Element, hooked layout, block template | no — content is rendered via `do_blocks()` without the filter | `5'11"` (straight) |

**Being inside a query loop does not matter.** `do_blocks` runs on `the_content` at priority 9 and
`wptexturize` at 10, so blocks render *first* and texturize then sweeps the whole resulting string,
loop-generated rows included. A loop row is built by a direct `WP_Block::render()` in
`GenerateBlocks_Block_Looper::render_wp_query()` (which applies the `render_block` filter, never
`the_content`), but it is already inline in the output before `wptexturize` runs — so it is
texturized exactly like a static block. Verified: J11/J11c both render `5’11”` on a normal page.

What actually decides it is whether *anything* in the chain applies `the_content`. A GP Element
renders its blocks and echoes them on a hook, so no content filter ever touches the string.
GenerateBlocks never calls `the_content` or `wptexturize` itself (verified: zero references in the
plugin), so it neither adds nor suppresses this. The consequence is that one format string renders
two different ways depending on whether the same block lives in page content or in an Element.

For height/dimension formats use the **prime marks** `′` (U+2032, feet) and `″` (U+2033, inches):
`format:%A′%B″` renders `5′11″` identically in **both** paths (they are not quote characters, so
`wptexturize` leaves them alone), and they are the typographically correct glyphs. That consistency
is the main reason to prefer them, beyond avoiding the curling. Numeric entities `&#39;`/`&#34;` in
the format also survive both paths (rendering literal straight marks) if straight quotes are
required.

Hooking `wptexturize` onto the callback's return to force uniform behavior is **not** done: the
callback cannot tell which path it is in (so page content would double-texturize), it would
diverge from every other GB dynamic tag in the same Element, and `wptexturize` also rewrites `--`,
`...`, and `(c)`, which mangles field values like part numbers and codes.

**`'0'` is a real value.** "Empty" is exactly `''` everywhere; a stored `0` renders (`5'0"`), by
absorb — join never re-decides emptiness. Suppressing a zero needs the author to store `''`, or
the tracked base-text zero-as-empty opt-in (absorbed by join when it lands).

**No link-wrap.** Output composes raw slot values — link identities from the seam are ignored
(no per-slot anchors, no nested/broken markup). If wrap ever arrives it is once at the join
layer, single-value output only (tracked).

**Editor preview.** In the editor a join resolves its slots against the post being edited — GB's
preview REST route injects `id:<postId>`, and the callback threads that id into each post-based
slot (only `src:site` slots skip it), so a join reading the edited post's own fields shows the
real assembled value just like `{{text}}`/`{{phone}}`. When the slots still resolve empty (fields
absent, misconfigured slot), it shows a configuration preview (target fields + assembly mode)
instead of an empty block, built by `bws_build_join_preview_label()`. Shape + examples:
[`editor-tag-previews.md` §join](editor-tag-previews.md#join-preview).

```
{{join A:key(name_first)|B:src(same);key(name_last)}}                    → Jane, Smith
{{join valueSep: |A:key(name_first)|B:src(same);key(name_last)}}         → Jane Smith
{{join mode:template|format:%A (%B)|A:key(name_first)|B:src(same);key(nickname)}} → Jane (Nick) / Jane when empty
{{join mode:template|format:%A′%B″|A:key(height_ft)|B:src(same);key(height_in)}}  → 5′11″ / 5′ / 5′0″ (prime marks — texturize-safe)
{{join mode:template|format:%A / ~%B lbs.~|A:key(position)|B:src(same);key(weight)}} → Center / 185 lbs. / Center when weight empty
{{join valueSep: / |A:use(title)|B:src(same);key(role)}}                 → Page Title / Captain
{{join A:key(fname)|B:src(site);key(organization_email)}}                → Jane, info@example.test
```

Pre-1.17.0 wire (`{{join key:name_first|2-key:name_last}}`) still renders — see
[§Folded slot wire](#folded-slot-wire-multislot-containers) "Both eras render".

**Tests.** Pure algorithm: `php tools/test/join-template-test.php` (Steps 0–5, wire-token
translation, `'0'`, full-name dense/sparse collapse). Integration: standing matrix
[`tools/test/join-test-matrix.md`](../tools/test/join-test-matrix.md) against the seeded testbed
(assembly modes, absorb-visible behavior: site arm, per-slot `limit`, carry-forward, `'0'`).

---

## Email/phone modifier tags — `try_` and `term_` (1.11.0)

`email` and `phone` are registered as modifier templates, so the shared machinery generates the `try_` and `term_` variants for both — full parity with the standalone tags.

**`try_email` / `try_phone`** — fallback chains (up to 5 slots, first non-empty wins). Each slot resolves an email/phone field **exactly as the standalone tag would** and returns the finished `mailto:`/`tel:` (or plain) string; the chain surfaces the first slot that produces output. Per [I6 transparency](../CONTEXT.md), all composition (link-wrap, obfuscation, `tel:` rebuild, list-join) happens inside the slot's own resolve — the chain only picks the winning slot and joins its list.

- **Per-slot field key**, no per-slot `use` (single key-mode — no `use` enum, mirroring the base tags).
- **`src:site` slot is allowed** (re-allowed past the generic [#26 slot-src filter](#src-option-values)) — site is the canonical contact-fallback slot (personal address → site-wide address). The slot resolver has a `src:site` arm that reads the option (not current-post meta). datetime/text/image try_ slots still filter `site` (their site arm is deferred).
- **`subject`/`noLink`** (email) and **`noLink`** (phone) are chain-level options (inputs to each slot's own compose).
- **List mode** (`limit`/`sep`) — a slot in list mode (a `terms` step, or `src:ref`) joins its finished per-item strings, same as the base tag's list mode.
- **`visibility`** — the same `tagName NOT_IN ['a','button','img','picture']` gate plus a **runtime media-block backstop**: a media block's empty `tagName` slips the native gate, so the `try_` callback returns `''` inside a media block rather than corrupting the `<img src>` with a `mailto:`/`tel:` anchor. (The tag still *appears* in the media source picker — same documented limitation as the base tags.)

**`term_email` / `term_phone`** — read the email/phone field off a taxonomy-term entity (the term itself at `src:current`, or a related post at `src:ref`). Same compose path as `{{email}}`/`{{phone}}`.

> **No `src:site` on `term_*`:** the `term_` source dropdown deliberately omits `site`. A rooting modifier exists to surface entity-distinct data; a site read is entity-blind, so `term_email src:site` would just duplicate `{{email src:site}}` while discarding the term rooting (fails the [qualifying test](#qualifying-test-for-new-use-values) on both arms). For a site-option read use the base tag (`{{email src:site}}`) or a `try_email`/`try_phone` site slot. (`site` was filtered before 1.11.0 was tagged — it never shipped as an offered `term_` source. [#37](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/37).)

---

# Part III — Trackers

*Read on change. Cross-doc pointers, future templates under consideration, and how to keep this document current.*

## Editor tag configuration previews

The editor-time preview text shown in place of an unresolved tag while configuring it (markers, assembly, context/field parts, warnings, datetime + try_ shapes, examples) has its own authoritative doc: **[`editor-tag-previews.md`](editor-tag-previews.md)**. Built by `bws_build_preview_label()` in `includes/helpers/preview-helpers.php`.

---

## Pattern cache reconcile

Our response to the GB Pro pattern-library facts in
[`gb-constraints.md` §GB Pro pattern library](gb-constraints.md#gb-pro-pattern-library-the-cached-copy-of-every-patterns-content).
Owner: `\BWS\DynamicTags\Admin\PatternCache` (`includes/classes/admin/class-pattern-cache.php`),
whose PHPDoc carries the invariants. Shipped 1.17.0 (#99).

**The problem.** `TagConverter::migrate_post()` writes migrated content straight to the posts
table, which fires no hooks, so GB Pro's cached copy of a pattern's content is never told and the
inserter goes on serving pre-migration wire. Measured on two production clones: a full converter
run left 30 of 31 rewritten tag strings alive in that cache. Nothing renders wrong, and the second
scan reports nothing left to do because it reads post content only.

**The response.** Rewrite **only** the cached content field, leaving preview, scripts, styles,
categories, global style selectors and form references exactly as found. Rebuilding the entry was
rejected on measurement (see the GB constraint table). The content field is the one whose correct
value is known exactly and independently — it *is* the post's content — so the rewrite is
deterministic and idempotent, which a rebuild is not.

| Rule | Detail |
|---|---|
| **Agreement is a raw byte compare** | `$entry['pattern'] === $post->post_content`, no slash normalisation. GB Pro's stored value is byte-identical to `post_content` (see the constraint table), so `===` is exact. An escaping-only difference is a **divergence that repairs once and agrees thereafter** — normalising would declare a genuinely over-slashed entry "agreeing" and leave it permanently unrepairable while the reconcile reports zero. |
| **Content-agnostic, not migration-triggered** | It does not gate on "a migration fired for this post in this run". That is what makes it repair as well as prevention: a post migrated by an earlier run has correct content, a stale cache, and nothing left to trigger on. |
| **Matched by identifier, never by position** | The live shape is a one-entry list, so a positional implementation passes every ordinary case and corrupts the unexpected one. |
| **An unrecognised shape is left alone** | Every shape the matcher does not recognise resolves to "no write". The reconcile can never make a bad cache worse. |
| **The escaping defence is `wp_slash()` on write** | `update_post_meta()` unslashes recursively through arrays, so a read-modify-write would strip one level from every string in the entry on every pass. |
| **Duplicate meta rows converge** | `update_post_meta()` has no previous-value constraint, so it writes every row holding the key. An accepted side effect, not a goal; de-duplication is out of scope. |
| **No capability check inside the class** | Gated at the callers, as `TagConverter::rebuild_allowlist()` is. A self-gate fails silently under `wp_doing_cron()`, disabling the one trigger that repairs already-converted sites. |
| **Multisite** | Current site only, inheriting the plugin's posture (no `is_multisite`/`switch_to_blog` anywhere). |
| **Trashed and auto-draft patterns are skipped** | So "every pattern" means every one actually in the library. A trashed pattern is not offered by the inserter and cannot seed stale wire; it self-heals on the next run after restore, because the reconcile is content-agnostic. The scanner excludes the same two statuses, so the two operate on one population. |
| **The first meta row decides** | `get_post_meta( …, true )` returns the first row, so that is what every reader of this cache gets, GB Pro's REST layer included. Where row 0 is an unrecognised shape and a later row holds a well-formed entry, the pattern is skipped whole rather than repairing a copy nobody reads. |

**Triggers.** Three, all running the same reconcile: the final step of a migrate run (beside the
existing allowlist rebuild), the on-upgrade pass in
`bws_dynamic_tags_rebuild_allowlist_on_upgrade()`, and "Scan All Content".

**Reporting.** A persisted summary in option `bws_dynamic_tags_pattern_cache_status`, shape
`{ checked, reconciled, time, trigger, version }`, written on **every** run including zero-count
ones — writing only on non-zero would make absence ambiguous again, which is this defect in
miniature. Two numbers rather than one because a bare reconciled-count cannot distinguish "nothing
needed fixing" from "nothing was checked". `trigger` and `version` are untranslated slugs, stored
but never rendered: the upgrade trigger fires once per version change and cannot be replayed, so
they are the only retroactive evidence that it ran. Rendered by `PatternCache::format_status()` as
a persistent line in the Migration Tool box, and refreshed from both AJAX handlers so an
un-reloaded page shows the run that just ran.

**Scope, stated to the user.** The Migration Tool section names its boundary (post content and the
block pattern cache; not custom field values or other plugins' caches). Enumerating those is
deferred — [#100](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/100),
tracker row FW-73.

**Integration surface.** `bws_dynamic_tags_content_written` — see
[`plugin-integration.md` §10](plugin-integration.md#10-reacting-to-a-direct-content-write).

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

**When adding a new template:** add a row to §Base tag GB types (including the Link wrap, Tag title, and Term modifier title columns); **add a per-tag section** (prose + tag-specific options + control order, linking the §Shared option groups it uses) — note required options + list-mode support there; if `supports_try`, add a row to §Available try_ tags; if it introduces a new shared option, add it to the relevant §Shared option group; **add its editor preview-text rows (field part, warning, example) to [`editor-tag-previews.md`](editor-tag-previews.md)** — preview text is no longer owned here.

**Deprecated wrappers:** never edit this doc for N×M deprecated wrappers — those go in [`docs/deprecated-tags-options.md`](deprecated-tags-options.md).

**In-progress / under-consideration renames** stay in this doc (in the relevant catalog section) until completed; on completion they move to [`docs/deprecated-tags-options.md`](deprecated-tags-options.md). Only **completed** renames live there.

For ownership boundaries against other docs, see [`CLAUDE.md` §Documentation ownership](../CLAUDE.md#documentation-ownership).
