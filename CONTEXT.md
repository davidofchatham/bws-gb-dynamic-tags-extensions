# CONTEXT — load-bearing cross-cutting invariants

**What this is:** the small set of design principles that span many tags/callbacks and are **currently binding**, but belong to no single class (so they can't live as one `@invariant` PHPDoc). An agent should read this first — it's the "constitution" the per-doc schemas assume.

**What this is NOT:**
- Not schemas or current-state tables → [`docs/tag-reference.md`](docs/tag-reference.md).
- Not GB API facts → [`docs/gb-constraints.md`](docs/gb-constraints.md).
- Not single-class invariants → `@invariant` PHPDoc on the enforcing method.
- Not design *narrative / how-we-got-here* → the per-feature plan in `.claude/plans/archive/` (linked per principle below).
- Not shipped history → [`CHANGELOG.md`](CHANGELOG.md).

**Rule:** a line that could live in `tag-reference.md` (a schema, a label, a current-state row) goes there and is *linked* from here — never duplicated. This doc holds only invariants and the model behind them.

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
- **Scope boundary — the line is one-string-output vs repeated markup, NOT value-count.** Producing **repeated markup over multiple entities** (the "fallback query" — e.g. staff cards with photo+name+phone each) is the **query-loop layer**, OUT of try_ scope. try_ never iterates entities into repeated markup. A scenario wanting that is `fallback ∘ query-loop` composition — a separate unbuilt capability (#NN), not a try_ concern.

Consequence: a `try_` tag that truncates a list its base tag would join is a **parity defect** (try_ must be transparent to the slot's own list mode). Enforced at: `generate_base_try_tags()` slot resolver PHPDoc. Schema (list mode / composite per tag): `tag-reference.md` §List mode, §datetime. Narrative: `.claude/plans/try-email-phone-and-slot-derivation.md`.

---

## Pointers

- **PHPDoc invariants in code** (single-class): `bws_site_allowlist_ok` (allowlist), `bws_site_read_option` (single-reader), `bws_resolve_link_url` (site link = permalink-analog), `bws_parse_combined_date_time` (datetime value-id sentinel), the email callback + settings accessor (VE1-VE4).
- **Architecture decision records:** [`docs/adr/`](docs/adr/).
