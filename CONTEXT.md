# CONTEXT — load-bearing cross-cutting invariants

**What this is:** the small set of design principles that span many tags/callbacks and are **currently binding**, but belong to no single class (so they can't live as one `@invariant` PHPDoc). An agent should read this first — it's the "constitution" the per-doc schemas assume.

**What this is NOT:**
- Not schemas or current-state tables → [`docs/tag-reference.md`](docs/tag-reference.md).
- Not GB API facts → [`docs/gb-constraints.md`](docs/gb-constraints.md).
- Not single-class invariants → `@invariant` PHPDoc on the enforcing method.
- Not design *narrative / how-we-got-here* → the per-feature plan in `.claude/plans/archive/` (linked per principle below).
- Not shipped history → [`CHANGELOG.md`](CHANGELOG.md).

**Rule:** a line that could live in `tag-reference.md` (a schema, a label, a current-state row) goes there and is *linked* from here — never duplicated. This doc holds only invariants and the model behind them.

**Reading posture — contradictions are refactorable.** Where current code contradicts a model or invariant below, that is a **tracked refactor candidate**, NOT a documented exception to enshrine. Don't bend the model to fit the code, and don't carve a special case to "explain" the contradiction — name it as a refactor and point the fix at the model. (Worked instances: the resolved-source §Language note — datetime's `'option'`-in-post_id param-overload and `ref`'s single-target collapse are both flagged refactorable, not canonical. The N×M source-class explosion and the resolve-clones are the same "model not yet shared" smell, not facts to preserve.)

---

## I1 — Source-analog resolution (the base-tag mental model)

Each base tag, when it resolves to its DEFAULT `use` token, yields the best intrinsic **analog** datum for the active source, where one exists:

| Tag | post | term | site |
|---|---|---|---|
| `title` | post title | term name | site name |
| `content` | post content | term description | — (no body datum) |
| `permalink` | post URL | term URL | `home_url()` |
| `image` | featured | — (gap, #29) | logo (via explicit `use:featured`) |
| `text` | keyed by nature in ALL contexts (default = key, key required) |

Where a source has no intrinsic analog (term image, site content-body), the default resolves to empty + key required — an **honest gap**, not a fabricated value.

**Scope:** base-tag callbacks + try_ **slot 1** only (the strip-default-first-value position). Try_ slot ≥2 empty wire = "same as previous slot" (carry-forward), NOT analog re-derivation.

Schema/per-tag detail: `tag-reference.md` §Source-analog resolution. Narrative: `.claude/plans/archive/...` (source-analog handoff) + `src-site-unified-source.md`.

## I2 — `use` dispatches analog-vs-option, UNIFORMLY across all sources (Model B)

`use` is the analog-vs-option selector — the same lever in every source, including `src:site`. `use:key` (or the stripped key-mode default) → field/option read; a named analog `use` value → that source's analog datum. The lever is the `use` VALUE, never key-presence. `src:site` selects the wp_options namespace the way `src:current` selects post meta — it does not branch independently of `use`.

- NO `use:option` value exists anywhere — option IS a key-read reached by `use:key`, namespaced by `src`.
- Each base tag's `use` default is its own (text/image → `key`; content → `content`; permalink/title → none). "`use` unset" does NOT universally mean key-mode.

Enforced at: `bws_site_resolve_value` PHPDoc (base-tags.php). Detail: `tag-reference.md` §Field options.

## I3 — Empty `use` is the stripped default, not a third state

A `use`-dispatcher MUST canonicalize an empty wire `use` to the tag's FIRST enum value before dispatching (`content`→`'content'`, text/image→`'key'`) — mirroring the callbacks' `?? 'key'` / `?? 'content'`. Dispatching on the literal `''` silently drops the option read for tags whose default IS key-mode. The stripped default MUST remain key-mode (never a named analog) until token authority can auto-unset a stale `key`.

Enforced at: `bws_site_resolve_value` `@invariant` PHPDoc. Convention detail: `tag-reference.md` §Default serialization strategy.

## I4 — Qualifying gate for a NEW `use:` value (two-sided)

A new named `use:` value (or per-source analog) MUST satisfy AT LEAST ONE of two tests; reject ONLY if it fails BOTH:

1. **Uniqueness** — offers an affordance no existing path gives: a datum unreachable elsewhere, OR a transform/traversal that adds value.
2. **Strong cross-source analog** — fills the SAME conceptual slot as the tag's analogs in other sources (so the bare tag "just works" per context), even if the datum is also reachable via `key`/GB-native.

A value failing both (datum already reachable AND no transform AND no cross-source slot) is proliferation → reject. "Feeds a multi-slot tag" is NOT sufficient (decouple via #26). This is a **decision-time process gate**, not a runtime invariant.

**Applies at the SOURCE level too, not just `use` values.** Offering a *source* on a tag is the same gate: a source qualifies on a tag iff it's either uniquely useful there or fills a cross-source slot the tag's purpose implies. `src:site` on a single-slot **rooting modifier** (`term_*`, `view_*`) fails both — the site datum is the identical unrooted base read (`{{email src:site}}`) and site is entity-blind so it fills no entity-distinct slot → filtered from the modifier's `src` dropdown. The site rung's likely future home is a **pinned-resource source** (a probable `src:term,<ID>`-style construct, NOT final) inside a try_ chain (chains keep their site rung via `try_allow_site_slot`), NOT a `try_term_` form; `term_` is transitional, on a deprecation glide-path.

Worked examples + verdicts: `tag-reference.md` §Qualifying test (incl. the source-level `term_ src:site` row). Drove cutting `text use:tagline` (the site Tagline has a tag-less path — GB native `{{site_tagline}}` or `key:blogdescription`).

## I5 — Label scope tracks source scope

Adding a source type that routes through an EXISTING shared control (the `use:key` field-key path, the `key` control) widens what that control covers → its LABEL must be reconsidered. A stale label that omits a now-valid source is a defect. Every new-source addition includes a label-review pass over shared `use`/`key` controls.

(Future: src-dynamic per-entry labels — #33 / V10a; entry filtering — #27 / V10b. Both share the `cloneElement(options)` JS seam.)

## I6 — try_ is a transparent fallback wrapper over single-string slot outputs

A `try_` chain selects WHICH slot's result surfaces (first non-empty slot wins); it does not compose, decompose, or transform that result. A slot resolves **identically to the same underlying tag used standalone** with the same options — full parity. Whatever the slot's own resolve produces is what try_ surfaces.

- **Output unit is one finished string per slot.** Value-count- and field-count-agnostic: one field (`{{email}}` one address), many fields composited (`datetime_range` → `start–end`), or one field enumerated to many values list-joined (`text` + `sep`) — all legal slot outputs. ALL composition (link-wrap, extension append, range formatting, list-join) happens INSIDE the slot's own resolve/core, NOT in try_ machinery.
- **try_ machinery does exactly two things:** pick the first non-empty slot, and (when that slot is itself in list mode) `implode($sep)` its already-finished per-item strings. No per-item transform hook — slot items arrive fully composed (`try_item_fn` was considered and cut; composition-in-resolve is the rule).
- **Scope boundary = the list-mode divider (I7), NOT value-count or entity-count.** try_ list-joins a slot's items iff each item is inline-level (I7). Block-level output (staff card, `<img>` figure, `{{content}}`) ∉ list mode → not joined. The old "repeated markup over N entities / query-loop" framing was wrong-axis: a staff card is excluded because it is **block**, not because it is N entities. I7 subsumes the query-loop case.

Consequence: a `try_` tag that truncates a list its base tag would join is a **parity defect** (try_ must be transparent to the slot's own list mode). Enforced at: `generate_base_try_tags()` slot resolver PHPDoc. Schema (list mode / composite per tag): `tag-reference.md` §List mode, §datetime. Narrative: `.claude/plans/try-email-phone-and-slot-derivation.md`.

## I7 — List mode gated by output DESTINATION (where the value lands), not output structure

Whether a tag's output participates in **list mode** (plural target read once per target, items joined with `sep`) is gated by **where the produced value is consumed**, NOT by target cardinality, key count, or inline/block structure. Three destinations:

- **Text-flow value → list-joinable.** text, email address, phone number, datetime — produce a value that lands in free text flow. N such values join with `sep` into one string. ✓ list mode.
- **Attribute slot → singular.** `{{image}}` returns a **URL string** (or attachment id) that GB injects into an `<img src>` / attribute — the tag never emits `<img>` itself (base-tags.php:1027 returns a string). An attribute holds ONE value; `url1, url2` breaks it. Plural target collapses to first. ∉ list mode — because of the **destination**, not because the output is "block".
- **Body/document → not `sep`-joinable.** `{{content}}` returns post-body markup. Joining two documents with `, ` is incoherent (they are bodies, not values). ∉ list mode — its own exclusion reason, distinct from attribute.

**Key correction:** an earlier framing said "inline-level joinable / block-level not." Wrong — `{{image}}` is a plain URL string (not block markup), yet is excluded because its **destination is an attribute slot**. The gate is destination, not structure. (Superseded framings: entity-count "query-loop" boundary; inline/block structure.)

Single divider for list-joinability everywhere (base list mode + try_ I6 + read-target model): **does the value land in text flow (joinable) or a single-value slot / document (not)?** Narrative: `.claude/plans/try-email-phone-and-slot-derivation.md`. Schema: `tag-reference.md` §List mode.

---

## I8 — Field discovery projects L1's resolved-source KIND to editor time; never the runtime id, never the current post

The `bws-field-combo` field selector (shipped 1.13.0) is **editor-time discovery**, orthogonal to the runtime L1/L2/L3 read. Its scoping axis is the resolved-source **KIND** (post/term/site) — the ONE half of L1 that is knowable at editor time, projected from the sibling `src`/`srcTermIn` tokens by a **static map** (`presetKind`), with **no L1 call and no runtime id** (id is a runtime-only L1 output). So the selector's location filter presets ONLY from safe source tokens (`srcTermIn`→term, `src:site`→site; `src:ref`→unscoped, since ref-hop target PT is not statically known); it **NEVER assumes the editor's current post is the read target** — that assumption is exactly the GB-native selector's blind spot (it reads the container post's meta in Patterns/Elements/templates) that this feature exists to escape.

Two corollaries bind:
- **Offered ⟺ resolvable.** The endpoint offers only keys the runtime resolver would accept (one shared `bws_field_key_disallowed()` gate; `_`-protected allowed, `DISALLOWED_KEYS` refused) — a discovery/runtime contract, so an offered key never silent-empties.
- **Bare key is the only serialized identity.** The control serializes the plain key exactly as the old text input did (pure render swap); a key can map to many fields (same key, different labels), so on reopen an ambiguous key shows RAW and asserts no specific field. Discovery labels are display-only, never part of the wire format.

Load-bearing detail lives as PHPDoc on the enforcers: `field-combo-control.js` (kind projection, merge-by-`(kind,key,label)`, ambiguous-key display) + `field-discovery.php` (`scopes_equal` keep-both, per-subtype registered meta, the DISALLOWED gate). Schema: `tag-reference.md` §Custom control types. Rationale + follow-ups: `.claude/plans/field-selector.md`.

---

## I9 — L1 ambient resolution: the factory picks the base source by CONTEXT, never by `$post`

The traversal pipeline (shipped 1.14.0) resolves *where a bare tag reads from* through a single **source factory** (`bws_resolve_base_source`), by a fixed precedence — the load-bearing rule the whole context-aware feature rests on:

1. **Explicit `src`** (site / registry source / `ref` as a step off the base) — author intent always wins.
2. **Loop row** (`bws_get_loop_row_context`) — a bare tag inside a query loop reads the ROW (post or Mode-2b meta_row), not the archive.
3. **Ambient queried object** — `get_queried_object()` is a `WP_Term` → the **term** (the #19 term-archive kind; the first context kind shipped).
4. **Current post** — else the singular post.

**`$post` / `get_the_ID()` is NEVER an ambient fallback.** Probe-proven: `$post` carries the main query's FIRST row on every results-bearing non-singular context (term archive, search, empty-search), so a `$post` fallback renders a plausible-but-wrong entity exactly where context-awareness matters. Only a loop row (rule 2) or an explicit id feeds a post source. This is why the factory reads `get_queried_object()` (hook- and loop-stable), not `$post`.

Two guards keep the leak dead at the edges:
- **A claimed-taxonomy-context with no resolvable term yields EMPTY, never the leaked post.** When `is_tax`/`is_category`/`is_tag` fire but `get_queried_object()` is not a `WP_Term` (deleted term, malformed query), the factory short-circuits to empty rather than falling to the current post.
- **A `{kind:post, id:0}` reads EMPTY at the seam** — a post/0 means "no post found"; the read never falls through to `bws_read_field`'s own inference (which would re-derive a rejected context).

**Term as a first-class read source** (I1 applied by context): on a term archive a bare base tag reads the term's analog — `title`→name, `content`→description, `permalink`→term URL, `text key:`→term meta; `image` has no intrinsic term analog (#29 gap) but a configured fallback still applies. A `try_` slot resolves **identically** to the same base tag standalone (I6 transparency), because both run the same term cores.

**Only taxonomy term archives are context-aware in 1.14.0.** Blog / search / date / author / post-type archives fall through to current behavior (still `$post`) — their kinds are Phase 2 (`#19`, `.claude/plans/context-aware-base-tags.md`).

**Source reads are ACF-or-compatible, not ACF-mandatory.** A `src:ref` post hop tries the ACF relationship reader (type-validated, plural) first, then falls back to a raw meta read, so non-ACF handlers (Pods/Carbon/core) storing a post id in plain meta still resolve — honoring the plugin's ACF-or-compatible contract.

Single-class detail is PHPDoc on the enforcers: `bws_resolve_base_source` / `bws_capture_ambient_signals` (precedence + degenerate-term guard), `bws_run_traversal` (resolved-source typedef, pure fold), `bws_read_resolved_source` (kind dispatch + post/0 guard), `bws_pipeline_default_reader` (ACF-compatible ref), `bws_base_term_analog_read` (term analog + image fallback). Schema: `tag-reference.md` §Source-analog resolution, §List mode. Rationale + probe: `.claude/plans/archive/traversal-pipeline.md`.

---

## I10 — A deprecated tag's lifecycle status is a HAND-SET fact; external aliases take our status as authoritative

The settings page files a deprecated tag under **Deprecated** (still registers/renders) or **Removed** (inert, migration-data-only) by a hand-set fact per entry, **never derived** from GB runtime state (`get_tags()`) — deriving it would make box placement depend on load-order timing and let a settings toggle silently reclassify entries. Two axes, both hand-set:

- **Options** — Removed once `legacy_fallback_removed` is set, i.e. when the runtime's legacy-key fallback (`$options['old_key'] ?? $options['new_key']`) is deleted from the reading code. Absent = still live.
- **Tags** — Removed when `prefix_removed` is set; else the interim default reads `callback`-presence. **`callback`-presence is a proxy, NOT a render guarantee**: post-FW-1 the GB dispatch loop is gone, so a present `callback` (carried by an external alias) no longer means the tag renders. The proxy survives only because our own removed N×M entries had their `callback` stripped (→ Removed) while external aliases still pass one (→ Deprecated) — two populations with opposite natural defaults that a single global-default flag would split wrong.

**External context-modifier aliases take their authoritative status from THIS plugin.** An external plugin's alias (e.g. portal-system `portal_title → view_title`) is a *modifier over a tag this plugin owns*; its target's live/removed status is authoritative here, and the external additionally owns a `prefix_removed` flag it sets when it retires that prefix generation. Either target-removed OR prefix-removed ⇒ the alias files under Removed. Rebuilding these aliases inside the external plugin is rejected — they are modifiers of our tags, not standalone tags the external owns (revisit only if base tags ever become a drop-in module). Today all external targets (`view_*`) are live and no external prefix is flagged, so every external alias sits in Deprecated.

The `prefix_removed`/`callback` proxy is **interim**: a later release (FW-38) replaces it with explicit `registered_by` + `lifecycle` (`unset=active | deprecated | removed`) fields recorded at `register()` time, so box placement reads `lifecycle` only and `callback` becomes irrelevant to classification.

Enforced at: `MigrationRegistry::is_entry_live()` PHPDoc (the classifier + why-not-callback rationale) + `register()` `@param` docs (`prefix_removed`, `legacy_fallback_removed`). Integrator guidance: `docs/plugin-integration.md` §"Alias status and retiring a prefix". Future direction: `docs/future-work.md` FW-38.

---

## Tag structural vocabulary

How a tag is *constructed*, independent of what it DOES with reads (rooting/selecting/combining behavior is a separate, not-yet-canonical axis — don't coin a genus until a second instance earns it).

**base tag**:
An atomic tag that resolves ONE read target of its own (`text`, `title`, `image`, `email`, `phone`, `datetime_*`). The read-pipeline atom. `email`/`phone` are first-class base tags despite their own link mechanics ([tag-reference.md §Email/phone]).

**modifier** (originally, and canonically, the **prefix** sense):
A `register_modifier()`-generated **prefix** that fans ONE base tag out into a variant — `{{text}}` → `term_text`, `try_text`. "modifier" names the **prefix/fan-out registration topology**, NOT a behavior. The adjective says what the prefix does to its single wrapped base tag: **context modifier** (`term_`, `view_` — re-anchors the entity/source; [I4] "rooting") vs **functional modifier** (`try_` — alters the composition function over slots). Both fan out; both wrap.

**Not a modifier ≠ a base tag.** A standalone tag that **absorbs multiple base tags as slots and assembles** their reads (`join`) is neither: not a prefix/fan-out (no `join_*`), and not an atom (resolves no read of its own — it composes base-tag reads). It occupies a THIRD structural position. ("**absorb**" is the house verb — base-tag *enhancements flow through automatically* to the slots: when `text` gains a feature, join's text slots absorb it. The behavioral-inheritance property, distinct from the moment-in-time structural containment.) A genus noun for the structural position is **deliberately deferred** — `join` is the only instance today; a standalone `{{try}}` collapse would be the second that earns the abstraction. Until then: describe it ("standalone, absorbs base tags, assembles"), don't name a genus.

**A FOURTH position — opaque delegation (`{{call}}`).** A standalone tag that reuses **L1 post-resolution ONLY** (binds the loop-correct post entity) then **delegates to an opaque PHP function** — no L2 resolve-field, no L2b fetch, no L3 assemble; no resolved field, no field value, output is whatever PHP returns ([tag-reference.md §Call tag]). It is neither an atom (resolves no read of its own — it binds a post, then a function reads), nor an absorber (`join` — composes *base-tag* reads; `{{call}}` composes nothing, the function is opaque), nor a prefix/fan-out. It sits OUTSIDE [I6]/[I7] (no list mode, no composite, no analog — single string). It is **deliberately post-context-only, NOT source-agnostic** — the inverse of the [I1]/[I4] "just works across post/term/site" base-tag spirit: it offers `src:current`/`src:ref` only (both post-yielding), filtering `src:site`/`srcTermIn` at the source level ([I4] applied to sources, not `use:` values). Genus still **deferred** (describe-don't-name): the position is now distinct from base/modifier/absorber, but one instance does not earn an abstraction.

**Editor grouping ≠ structural class.** A consuming tag may share the base-tag GROUP in the GB picker for UX (precedent: `email`/`phone` sectioned with base tags for presentation, [tag-reference.md §base tags]) without BEING a base tag. Presentation grouping never implies structural identity.

---

## Registration-API load order

**A public developer registration API must be DEFINED before the hook on which callers invoke it.** A `bws_register_*()` function that site code calls (snippets, theme `functions.php`) has to exist by the time those callers run. Site code conventionally registers on `init` at the default priority 10; the plugin's own tag pass runs at `init:20` (later). So the file *defining* the API must load at **plugin top level** (before `init`), NOT inside the deferred init pass that *uses* it — otherwise an `init:10` caller hits a "Call to undefined function" fatal. Only the function DEFINITIONS load early; the GB tag REGISTRATION that consumes them still runs in the init pass. The early-loaded file must therefore have **no load-time side effects** (the WP/GB symbols it references are touched only inside functions that run at/after the init pass, never at `require`).

Drove the `{{call}}` B1 fix (`fn-tags.php` top-level require, 1.12.0): `bws_register_call_function` was trapped in the init:20 pass and fataled an init:10 snippet. Generalizes to any future registration API. Enforced at: the top-level `require` in `bws-gb-dynamic-tags-extensions.php` + its PHPDoc. Schema/usage: [tag-reference.md §Call tag] (register-on-init note).

---

## Language

Terms for the **source-resolution model** (the L1/L2/L3 read pipeline shared by text/email/phone/datetime/join/try_). The L1/L2 seam is **built for email/phone** as the shared `bws_resolve_field_values` (field-helpers.php, 1.11.0 — retired the per-tag clones); other tags still inline their own L1/L2. Full unification (datetime param-overload retire, `src:ref` plural, #19 context kinds) is incremental — see `.claude/plans/try-email-phone-and-slot-derivation.md`.

**Read target** (casual shorthand: **target**):
The **declared read intent** of a tag — its (source + key) specification. `{src:ref|key:email}` is one read target. Either part may be **explicit** (written token) or **implicit** (stripped default / recovered: source unset → current/context-default; both unset on `{{title}}` → analog). The resolved *intent*, NOT the literal token string. (Implicit/explicit/unset axis: handoff source-analog mode terminology. **#19 = read targets with an implicit source resolved by WP context.**) "target" alone always means read target — NOT resolved source. _Avoid_: "entity", `{kind,id}`.

**Resolved source**:
L1's output executing a target — the **bound *where*** a read happens, key not yet applied. post/term carry an id (meta-read needs one); **site** carries the `wp_options` namespace; future ones (#19 date/search, possible external Site-Views option-set source) carry their own payload. id is a post/term implementation detail, not universal. **Payload may legitimately vary by read mechanism within one kind:** site-datetime reads via ACF `get_field(key,'option')`, site-text via plain `get_option` — same `site` kind, different L2b read path. Frame-B variable payload (ADR 0002). **Distinguish legitimate payload-variance from a contradiction-to-refactor:** today datetime overloads the *post_id parameter slot* by passing the literal string `'option'` through it (datetime-tags.php:1005) — that param-overload is a contradiction of this model (a resolved-source payload smuggled through an id arg), REFACTORABLE, not canonical. Likewise `ref` collapsing to one target (`bws_extract_post_id`) contradicts the plural-source model → fix the code, don't model around it.

**Resolved field**:
L2a's output — **WHICH field to read**, determined by (resolved-source TYPE × implicit/explicit key options). Author-perspective: the field worked out before the fetch. Where the **analog** lives — `use:default` on a term resolves the field to "term name"; **I2 Model-B `use`-dispatch operates here** (use × source-type → field/analog). _Avoid_: confusing with field value (the datum).

**Field value**:
L2b's output — the **fetched datum** off the resolved field. The raw value before L3 assembly.

**Target cardinality**:
A resolved source is **`ResolvedSource[]`** — a list, usually length 1. `current`, `site` **singular**. `ref` (ACF relationship/post-object array), `srcTermIn` (taxonomy term set) **plural** (N). List mode originates here — *plural resolved-source, read once per source* — NOT a read-time loop. (Today `ref` is collapsed to the first by `bws_extract_post_id` — a latent single-read defect the plural model exposes.)

**Output destination** (list-mode divider — see I7):
WHERE a tag's produced value lands, gating list-joinability. **Text-flow value** (text/email/phone/datetime) → joinable. **Attribute slot** (image URL → GB `<img src>`; tag returns string, GB injects) → singular. **Body/document** (content) → not `sep`-joinable. _Avoid_: "inline/block structure", "query-loop boundary", "entity-count" (wrong-axis — superseded). Image proves destination ≠ structure: a plain URL string excluded by its attribute destination, not by being "block".

**L1 / L2 / L3** (layers executing a read target):
- **L1 — resolve source:** source options → `ResolvedSource[]`. The *where*; no key. Recovers implicit/unset source (→ #19 context resolution).
- **L2a — resolve field:** (resolved-source type × key options) → **resolved field** (which field/analog). I2 Model-B dispatch.
- **L2b — fetch value:** (resolved source, resolved field) → **field value**. Dispatches post/term → meta, site → option. Once per (source × field). Current code: `bws_read_field` / `bws_read_term_field` / `bws_site_read_option`; email/phone wrap L1+L2 as the shared `bws_resolve_field_values` (the seam — handles src:site, srcTermIn list mode, single post/term).
- **L3 — assemble:** per-tag compose over sources × fields (implode/`sep`, datetime range, join template, mailto/tel wrap), landing in an output destination. Per-tag; L1/L2 shared.

A tag reads **K fields × T sources** and assembles: text=1×1 (or 1×N via plural source), datetime/phone-ext=2×1, join=N×1, email-via-srcTermIn=1×N.

## Pointers

- **PHPDoc invariants in code** (single-class): `bws_site_allowlist_ok` (allowlist), `bws_site_read_option` (single-reader), `bws_resolve_link_url` (site link = permalink-analog), `bws_parse_combined_date_time` (datetime value-id sentinel), the email callback + settings accessor (VE1-VE4).
- **Field discovery (1.13.0, I8):** `includes/rest/field-discovery.php` (offered⟺resolvable gate, `scopes_equal` keep-both dedupe, per-subtype registered meta, `<script>`-safe JSON encode) + `assets/js/field-combo-control.js` (editor-time kind projection, `(kind,key,label)` merge, flat filters, ambiguous-key raw display). Schema: `tag-reference.md` §Custom control types. Design/follow-ups: `.claude/plans/field-selector.md`.
- **Architecture decision records:** [`docs/adr/`](docs/adr/).
