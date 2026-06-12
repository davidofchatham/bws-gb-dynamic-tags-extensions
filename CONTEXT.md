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

Worked examples + verdicts: `tag-reference.md` §Qualifying test. Drove cutting `text use:tagline` (the site Tagline has a tag-less path — GB native `{{site_tagline}}` or `key:blogdescription`).

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

## Language

Terms for the **source-resolution model** (the L1/L2/L3 read pipeline shared by text/email/phone/datetime/join/try_). Provisional — being hardened in `.claude/plans/try-email-phone-and-slot-derivation.md`; not yet all built.

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
- **L2b — fetch value:** (resolved source, resolved field) → **field value**. Dispatches post/term → meta, site → option. Once per (source × field). Current code: `bws_read_field` / `bws_site_read_option`.
- **L3 — assemble:** per-tag compose over sources × fields (implode/`sep`, datetime range, join template, mailto/tel wrap), landing in an output destination. Per-tag; L1/L2 shared.

A tag reads **K fields × T sources** and assembles: text=1×1 (or 1×N via plural source), datetime/phone-ext=2×1, join=N×1, email-via-srcTermIn=1×N.

## Pointers

- **PHPDoc invariants in code** (single-class): `bws_site_allowlist_ok` (allowlist), `bws_site_read_option` (single-reader), `bws_resolve_link_url` (site link = permalink-analog), `bws_parse_combined_date_time` (datetime value-id sentinel), the email callback + settings accessor (VE1-VE4).
- **Architecture decision records:** [`docs/adr/`](docs/adr/).
