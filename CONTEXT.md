# CONTEXT — load-bearing cross-cutting invariants

**What this is:** the small set of design principles that span many tags/callbacks and are **currently binding**, but belong to no single class (so they can't live as one `@invariant` PHPDoc). An agent should read this first — it's the "constitution" the per-doc schemas assume.

**What this is NOT:**
- Not schemas or current-state tables → [`docs/tag-reference.md`](docs/tag-reference.md).
- Not GB API facts → [`docs/gb-constraints.md`](docs/gb-constraints.md).
- Not single-class invariants → `@invariant` PHPDoc on the enforcing method.
- Not design *narrative / how-we-got-here* → the per-feature plan in `.scratch/plans/archive/` (linked per principle below).
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

Schema/per-tag detail: `tag-reference.md` §Source-analog resolution. Narrative: `.scratch/plans/archive/...` (source-analog handoff) + `src-site-unified-source.md`.

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

**Applies at the SOURCE level too, not just `use` values.** Offering a *source* on a tag is the same gate: a source qualifies on a tag iff it's either uniquely useful there or fills a cross-source slot the tag's purpose implies. `src:site` on a single-slot **rooting modifier** (`term_*`, `view_*`) fails both — the site datum is the identical unrooted base read (`{{email src:site}}`) and site is entity-blind so it fills no entity-distinct slot → filtered from the modifier's `src` dropdown. If the author actually wants "read the term field, else fall back to the site field," that is a **fallback across two sources** — its home is a **`try_` tag's attempts** (attempt 1 reads the term, attempt 2 reads site; attempts keep a site slot via `try_allow_site_slot`), NOT `src:site` on a `term_` tag and NOT a `try_term_` form. (`term_` is transitional, on a deprecation glide-path.) A separate future affordance — falling back to *one specific pinned entity* rather than the global site — is what the **ID source** (`src:<type>,<ID>`; §Language "Source binding", FW-39) would add as its own slot flavor; that is a distinct flavor from the global site rung, not what the site rung is.

Worked examples + verdicts: `tag-reference.md` §Qualifying test (incl. the source-level `term_ src:site` row). Drove cutting `text use:tagline` (the site Tagline has a tag-less path — GB native `{{site_tagline}}` or `key:blogdescription`).

## I5 — Label scope tracks source scope

Adding a source type that routes through an EXISTING shared control (the `use:key` field-key path, the `key` control) widens what that control covers → its LABEL must be reconsidered. A stale label that omits a now-valid source is a defect. Every new-source addition includes a label-review pass over shared `use`/`key` controls.

(Future: src-dynamic per-entry labels — #33 / V10a; entry filtering — #27 / V10b. Both share the `cloneElement(options)` JS seam.)

## I6 — try_ is a transparent fallback wrapper over single-string slot outputs

A `try_` tag resolves its **attempts** in order and surfaces the first non-empty one; it does not compose, decompose, or transform that result. (_Avoid_: "try_ chain" — **chain** names a source chain, and an attempt HOLDS one, so the two nest. _Avoid_: "fallback" for the mechanism — `fallback` is a live option key.) A slot resolves **identically to the same underlying tag used standalone** with the same options — full parity. Whatever the slot's own resolve produces is what try_ surfaces.

- **Output unit is one finished string per slot.** Value-count- and field-count-agnostic: one field (`{{email}}` one address), many fields composited (`datetime_range` → `start–end`), or one field enumerated to many values list-joined (`text` + `sep`) — all legal slot outputs. ALL composition (link-wrap, extension append, range formatting, list-join) happens INSIDE the slot's own resolve/core, NOT in try_ machinery.
- **try_ machinery does exactly two things:** pick the first non-empty slot, and (when that slot is itself in list mode) `implode($sep)` its already-finished per-item strings. No per-item transform hook — slot items arrive fully composed (`try_item_fn` was considered and cut; composition-in-resolve is the rule).
- **Scope boundary = the list-mode divider (I7), NOT value-count or entity-count.** try_ list-joins a slot's items iff each item is inline-level (I7). Block-level output (staff card, `<img>` figure, `{{content}}`) ∉ list mode → not joined. The old "repeated markup over N entities / query-loop" framing was wrong-axis: a staff card is excluded because it is **block**, not because it is N entities. I7 subsumes the query-loop case.

Consequence: a `try_` tag that truncates a list its base tag would join is a **parity defect** (try_ must be transparent to the slot's own list mode). Enforced at: `generate_base_try_tags()` slot resolver PHPDoc. Schema (list mode / composite per tag): `tag-reference.md` §List mode, §datetime. Narrative: `docs/design-history/try-email-phone-and-slot-derivation.md`.

## I7 — List mode gated by output DESTINATION (where the value lands), not output structure

Whether a tag's output participates in **list mode** (a fanning source, read once per source, items joined with `sep`) is gated by **where the produced value is consumed**, NOT by whether the source fans, key count, or inline/block structure. Three destinations:

- **Text-flow value → list-joinable.** text, email address, phone number, datetime — produce a value that lands in free text flow. N such values join with `sep` into one string. ✓ list mode.
- **Attribute slot → singular.** `{{image}}` returns a **URL string** (or attachment id) that GB injects into an `<img src>` / attribute — the tag never emits `<img>` itself (base-tags.php:1027 returns a string). An attribute holds ONE value; `url1, url2` breaks it. Plural target collapses to first. ∉ list mode — because of the **destination**, not because the output is "block".
- **Body/document → not `sep`-joinable.** `{{content}}` returns post-body markup. Joining two documents with `, ` is incoherent (they are bodies, not values). ∉ list mode — its own exclusion reason, distinct from attribute.

**Key correction:** an earlier framing said "inline-level joinable / block-level not." Wrong — `{{image}}` is a plain URL string (not block markup), yet is excluded because its **destination is an attribute slot**. The gate is destination, not structure. (Superseded framings: entity-count "query-loop" boundary; inline/block structure.)

Single divider for list-joinability everywhere (base list mode + try_ I6 + read-target model): **does the value land in text flow (joinable) or a single-value slot / document (not)?** Narrative: `docs/design-history/try-email-phone-and-slot-derivation.md`. Schema: `tag-reference.md` §List mode.

---

## I8 — Field discovery projects L1's resolved-source KIND to editor time; never the runtime id, never the current post

The `bws-field-combo` field selector (shipped 1.13.0) is **editor-time discovery**, orthogonal to the runtime L1/L2/L3 read. Its scoping axis is the resolved-source **KIND** (post/term/site) — the ONE half of L1 that is knowable at editor time, projected from the sibling `src`/`srcTermIn` tokens by a **static map** (`presetKind`), with **no L1 call and no runtime id** (id is a runtime-only L1 output). So the selector's location filter presets ONLY from safe source tokens (`srcTermIn`→term, `src:site`→site; `src:ref`→unscoped, since the post type a `refs` step lands on is not statically known); it **NEVER assumes the editor's current post is the read target** — that assumption is exactly the GB-native selector's blind spot (it reads the container post's meta in Patterns/Elements/templates) that this feature exists to escape.

Two corollaries bind:
- **Offered ⟺ resolvable.** The endpoint offers only keys the runtime resolver would accept (one shared `bws_field_key_disallowed()` gate; `_`-protected allowed, `DISALLOWED_KEYS` refused) — a discovery/runtime contract, so an offered key never silent-empties.
- **Bare key is the only serialized identity.** The control serializes the plain key exactly as the old text input did (pure render swap); a key can map to many fields (same key, different labels), so on reopen an ambiguous key shows RAW and asserts no specific field. Discovery labels are display-only, never part of the wire format.

Load-bearing detail lives as PHPDoc on the enforcers: `field-combo-control.js` (kind projection, merge-by-`(kind,key,label)`, ambiguous-key display) + `field-discovery.php` (`scopes_equal` keep-both, per-subtype registered meta, the DISALLOWED gate). Schema: `tag-reference.md` §Custom control types. Rationale + follow-ups: `.scratch/plans/field-selector.md`.

---

## I9 — L1 ambient resolution: the factory picks the base source by CONTEXT, never by `$post`

The traversal pipeline (shipped 1.14.0) resolves *where a bare tag reads from* through a single **source factory** (`bws_resolve_base_source`), by a fixed precedence — the load-bearing rule the whole context-aware feature rests on:

1. **Explicit `src`** (site / registry source / `ref` as a step off the base) — author intent always wins.
2. **Loop row** (`bws_get_loop_row_context`) — a bare tag inside a query loop reads the ROW (post or Mode-2b meta_row), not the archive.
3. **Ambient queried object** — `get_queried_object()` is a `WP_Term` → the **term** (the #19 term-archive kind, shipped 1.14.0); a `WP_User` → the **user** (the #19 author-archive kind, shipped 1.15.0).
4. **Current post** — else the singular post.

**`$post` / `get_the_ID()` is NEVER an ambient fallback.** Probe-proven: `$post` carries the main query's FIRST row on every results-bearing non-singular context (term archive, search, empty-search), so a `$post` fallback renders a plausible-but-wrong entity exactly where context-awareness matters. Only a loop row (rule 2) or an explicit id feeds a post source. This is why the factory reads `get_queried_object()` (hook- and loop-stable), not `$post`.

Two guards keep the leak dead at the edges:
- **A claimed-taxonomy-context with no resolvable term yields EMPTY, never the leaked post.** When `is_tax`/`is_category`/`is_tag` fire but `get_queried_object()` is not a `WP_Term` (deleted term, malformed query), the factory short-circuits to empty rather than falling to the current post.
- **A `{kind:post, id:0}` reads EMPTY at the seam** — a post/0 means "no post found"; the read never falls through to `bws_read_field`'s own inference (which would re-derive a rejected context).

**Term as a first-class read source** (I1 applied by context): on a term archive a bare base tag reads the term's analog — `title`→name, `content`→description, `permalink`→term URL, `text key:`→term meta; `image` has no intrinsic term analog (#29 gap) but a configured fallback still applies. A `try_` slot resolves **identically** to the same base tag standalone (I6 transparency), because both run the same term cores.

**User as a read source** (I1 applied by context; author kind, 1.15.0): on an author archive a bare base tag reads the user's analog — `title`→display name, `content`→biographical info (the `description` user meta), `text`→display name under `use:title`, else the author's user meta field (1.16.0). A `try_` slot resolves identically (I6), on the same three tags, since 1.17.0; `permalink`/`image`/datetime author analogs are deferred (they render empty, not wrong). The user kind is an ENTITY kind (carries an id; field reads via `'user_' . $id`) but reuses no post/term reader — its two analogs read `get_the_author_meta()` directly (`bws_base_user_analog_read`). Link-wrap resolves the author-archive URL (`get_author_posts_url`, the user permalink-analog in `bws_resolve_link_url`). Unlike the term kind there is NO degenerate guard — a zero-result author archive still resolves the `WP_User` and does not leak `$post`.

**Query-context (entity-LESS) kinds are still Phase 2.** Search / date / post-type archive / 404 / latest-home carry query/date/search payload with no field to read (PTA's `queried_object` is a `WP_Post_Type` with `queried_id` 0 — captured 2026-07-18), and each needs an option surface (search format, 404 fallback, home title-source) before shipping. They fall through to current behavior (still `$post`) until then. Detail: `#19`, `.scratch/plans/context-aware-base-tags.md`; baseline rows `tools/test/context-test-matrix.md`.

**Source reads are ACF-or-compatible, not ACF-mandatory.** A `src:ref` post step tries the ACF relationship reader (type-validated, returns an array) first, then falls back to a raw meta read, so non-ACF handlers (Pods/Carbon/core) storing a post id in plain meta still resolve — honoring the plugin's ACF-or-compatible contract.

Single-class detail is PHPDoc on the enforcers: `bws_resolve_base_source` / `bws_capture_ambient_signals` (precedence + degenerate-term guard), `bws_run_traversal` (resolved-source typedef, pure fold), `bws_read_resolved_source` (kind dispatch + post/0 guard), `bws_pipeline_default_reader` (ACF-compatible ref), `bws_base_term_analog_read` (term analog + image fallback), `bws_base_ambient_user_id` / `bws_base_user_analog_read` (author kind gate + analogs). Schema: `tag-reference.md` §Source-analog resolution, §List mode. Rationale + probe: `docs/design-history/traversal-pipeline.md`.

---

## I10 — A deprecated tag's lifecycle status is a HAND-SET fact; external aliases take our status as authoritative

The settings page files a deprecated tag under **Deprecated** (still registers/renders) or **Removed** (inert, migration-data-only) by a hand-set fact per entry, **never derived** from GB runtime state (`get_tags()`) — deriving it would make box placement depend on load-order timing and let a settings toggle silently reclassify entries. Two axes, both hand-set:

- **Options** — Removed once `legacy_fallback_removed` is set, i.e. when the runtime's legacy-key fallback (`$options['old_key'] ?? $options['new_key']`) is deleted from the reading code. Absent = still live.
- **Tags** — Removed when `prefix_removed` is set; else the interim default reads `callback`-presence. **`callback`-presence is a proxy, NOT a render guarantee**: post-FW-1 the GB dispatch loop is gone, so a present `callback` (carried by an external alias) no longer means the tag renders. The proxy survives only because our own removed N×M entries had their `callback` stripped (→ Removed) while external aliases still pass one (→ Deprecated) — two populations with opposite natural defaults that a single global-default flag would split wrong.

**External context-modifier aliases take their authoritative status from THIS plugin.** An external plugin's alias (e.g. portal-system `portal_title → view_title`) is a *modifier over a tag this plugin owns*; its target's live/removed status is authoritative here, and the external additionally owns a `prefix_removed` flag it sets when it retires that prefix generation. Either target-removed OR prefix-removed ⇒ the alias files under Removed. Rebuilding these aliases inside the external plugin is rejected — they are modifiers of our tags, not standalone tags the external owns (revisit only if base tags ever become a drop-in module). Today all external targets (`view_*`) are live and no external prefix is flagged, so every external alias sits in Deprecated.

The `prefix_removed`/`callback` proxy is **interim**: a later release (FW-38) replaces it with explicit `registered_by` + `lifecycle` (`unset=active | deprecated | removed`) fields recorded at `register()` time, so box placement reads `lifecycle` only and `callback` becomes irrelevant to classification.

Enforced at: `MigrationRegistry::is_entry_live()` PHPDoc (the classifier + why-not-callback rationale) + `register()` `@param` docs (`prefix_removed`, `legacy_fallback_removed`). Integrator guidance: `docs/plugin-integration.md` §"Alias status and retiring a prefix". Future direction: `docs/future-work.md` FW-38.

---

## I11 — A composing tag must thread the editor-injected `id` into every post-based sub-read

GB's editor **preview REST route** resolves the edited post by appending `id:<postId>` to the tag string — because `GenerateBlocks_Dynamic_Tags::get_id()`'s post fallback is `get_the_ID()`, which is **`false` in the REST context** (no main query runs). A single-read tag (`text`, `phone`, …) receives that `id` in its OWN options and resolves. But a **composing tag** — one that builds a fresh option set per sub-read (`join`'s per-slot `$slot_opts`, and any future multi-slot absorber) — **drops the tag-level `id`** unless it explicitly copies it down, so its current/ref sub-reads resolve against a nonexistent post → empty-in-editor (the tag then shows only its configuration-preview label, never the real data the sibling `{{text}}` shows).

**Rule:** a composing tag MUST thread its tag-level `id` into every **post-based** sub-read it delegates. `src:ref` sub-reads carry it too (the current post is where the `refs` step starts). Only **entity-blind** sources skip it — `src:site` reads a `wp_options` datum, never a post, so passing `id` there is meaningless.

**Front-end safety by construction:** GB injects `id` ONLY on the editor REST route. On the front end the tag-level `id` is empty → nothing is threaded → the loop-row / ambient context (I9) resolves each sub-read, and [[feedback_loop_context_override]]'s "explicit `$post_id` wins over loop inference" is not disturbed (no explicit id exists to win). So this is an editor-only correction.

This is the composing-tag corollary to I9 (L1 ambient resolution) and I6 (a slot resolves identically to the same tag standalone — which fails silently in the editor if the id isn't propagated). Enforced at: `bws_join_callback` `$explicit_id` PHPDoc (base-tags.php). Schema/behavior: `tag-reference.md` §join (editor preview) + `tools/test/join-test-matrix.md` §Editor preview.

**Front-end analog — a RENDERED entity's inner tags.** The same "a sub-read with no id falls back to ambient" mechanism bites on the front end wherever we render another entity's block markup: `{{content}}`'s inner dynamic tags go through `do_blocks()` carrying no block context, so they read the global `$post`. On a hopped read that is the viewing page, not the post being rendered — the hopped post's structure filled with the ambient page's values. The correction is the same shape (make the target reachable to the sub-read) with a different mechanism: swap the global post for the duration, `bws_with_post_context()`. Enforced at: that function's PHPDoc; mechanism: [`post-content-processing-reference.md` §Post-context swap](docs/post-content-processing-reference.md#post-context-swap-1170); coverage: `tools/test/content-test-matrix.md`.

---

## I12 — Link-wrappability is a property of the VALUE, not of the source kind

A collected value carries a **link identity** — the `(kind, id)` pair `bws_resolve_link_url` consumes (`post|term|user|site`) — or it carries **none**. "None" is `null`, never a sentinel id. A value with no link identity is not "an entity whose id happens to be 0"; it is a datum that is not addressable.

**Why this is not ADR 0002's rejected `{kind,id}`:** that decision governs L1's **resolved source** (the bound *where* a read happens — payload varies by read mechanism). This is an L3 **collected value** and its consumer, `bws_resolve_link_url`, is genuinely a typed four-kind switch. The two live at different layers with different consumers, so a typed link identity here does not re-import the shape ADR 0002 rejected there — provided the no-identity case stays `null`. The moment it becomes `id => 0`, every unaddressable kind starts special-casing at every consumer, which is exactly what ADR 0002 predicted.

Kinds with no link identity are **normal, not exceptional**: `meta_row` (a repeater row is not an entity — it has no URL) today; the [I9]/#19 query-context kinds (date archive, search, 404) as they land — the tracker calls them "entity-less ... no field to read". A new source kind's link obligation is therefore ONE question: does it address something? If not, `null`, and no consumer changes.

**Corollary — the single-result link gate is a JOIN constraint, not a linking one.** A list whose values are `sep`-joined into one string cannot be wrapped in one link (the link would span unrelated entities, and a lone fallback string would satisfy a naive gate — GH #51). Hence "wrap iff exactly one value survived". This does NOT say list items are unlinkable individually: per-value link identities are retained precisely so a future per-item link mode is available without reshaping the payload.

Enforced at: `bws_collect_value_list()` PHPDoc (field-helpers.php). Consumer contract: `bws_resolve_link_url` (link-helpers.php). Rationale + build record: `docs/design-history/traversal-convergence-fw49.md` §Design (FW-49 is built there, not in `traversal-pipeline.md`, which is Phase 1's own archived design doc). Related: [ADR 0002](docs/adr/0002-resolved-source-variable-payload.md), [I7] (list mode), [I9] (ambient kinds).

---

## I13 — Wire ERA is decided PER SLOT, never per tag

A multislot tag's slots are stored either in the **folded** form (one option key per slot, spelled as a capital ordinal) or in the **flat** form (six keys per slot). **Form purity is a property of a MIGRATED tag, not of the reader.** A half-applied migration, a hand-edit, or a block widget the converter never scanned can leave slot 2 folded between flat slots 1 and 3 — so a reader that picked one form for the whole tag would silently drop half of it.

**Rule:** decide the form per slot. Folded value present ⇒ parse it; absent ⇒ recover that slot from its flat keys. Both paths feed the same caller-held carry-forward accumulator, which is what makes a mixed-form tag resolve as its author last saw it. Two corollaries that are easy to get backwards:

- **Malformed folded wire resolves to NOTHING, not to the flat sibling.** The folded value is the author's intent; rendering a stale flat key instead is a wrong answer dressed as a working one.
- **A folded value WINS over surviving flat siblings** rather than merging with them — merging invents a configuration neither side wrote. This is also why the migration's equivalence property is blind to whether the flat keys were stripped.

(`flat`/`folded` name STRUCTURE, and are the right words here — the flat form is still what every unmigrated tag holds. Reserve **legacy** for LIFECYCLE: a spelling we read but no longer write. _Avoid_: "unfolded", "non-folded", "pre-fold".)

The same rule is why the two migration paths are complementary rather than redundant: the converter reaches stored `post_content` a scanner can walk, and tag-modal mount reaches everything it cannot. Neither is a fallback for the other, and both must write the same tag the same way.

Spans: the render seam, both previews, both migrators, the editor control, and their JS twins — four files in two languages, which is why it is here and not in one PHPDoc. Enforced at: `bws_fold_slot_struct()` PHPDoc (slot-fold.php). Schema: [`tag-reference.md` §Folded slot wire](docs/tag-reference.md#folded-slot-wire-multislot-containers). Rationale + build record: `docs/design-history/src-chain-encoding.md`. Related: [I6] (a slot resolves as the same tag standalone), [I11].

---

## I14 — A source chain's ROOT is not a step

A folded slot's source is an ordered CHAIN: a **root** plus N **steps**. Its first token is either an **entity root** (`current`, `site`, a registry source) or already a **fanning step** (`refs`, `terms`, `entries`), and which one it is is decidable **from the slug alone** — root slugs are singular, step slugs plural. **The plural spelling is a CATEGORY marker, never a count claim:** a fanning step *may* resolve many, and routinely resolves one (a relationship field limited to 1, a single-term taxonomy, a one-row repeater). The root binds *where* traversal starts, which is the factory's job ([I9] ambient resolution); the steps are what the engine folds.

**Rule:** compile a chain by consuming the root into the source factory and the remaining steps into the engine. Do not model the root as a step-zero, and do not let a step stand in for it. Three consequences worth stating because each has a wrong-looking-right alternative:

- **An argless fanning step KEEPS its step and loses only its argument.** A field-less step short-circuits to empty, which is the wire's own meaning. Dropping it leaves the chain with no steps, and a chain with no steps resolves the AMBIENT entity — so a tag saying "follow a relationship" read the entry it sat on. Through 1.16.x it was dropped, matching the flat shape it comes from; [I15] is why that inverted.
- **An unknown step slug compiles to an unknown engine TYPE, never to nothing.** The engine answers empty and the chain short-circuits, which is the wire's own meaning. Dropping the step would read a *different* source than the wire states.
- **A step `limit` and the tag-level `limit` are different quantities.** The first bounds one fanning step's spread, **per input source**; the second bounds the resolved-source list once, before the read. Neither bounds values — the read is 1:1. A step carries a limit only when it actually bounds the step, since absent is how the engine spells no limit. Only the first is stated by an author: no tag has registered a tag-level `limit` since v1.17.0, and the key survives as a read for stored wire. Schema + the full value table: [`tag-reference.md` §List mode](docs/tag-reference.md#list-mode-limit--sep); the decision: [ADR 0005](docs/adr/0005-limits-are-stated-where-the-source-is-stated.md).

**Partly contradicted, and tracked as a refactor rather than an exception** (per the reading posture above). The BASE half is fixed: FW-63 (1.17.0) made every base arm dispatch by the chain's **resolved-source KIND** rather than by flat `src`/`srcTermIn` tokens. Note that kind is a property of the CHAIN, not of its last step: a root-only chain (`src:site`) has one and has no steps at all.

The SLOT half closed in 1.17.0, and **not by the mechanism this paragraph used to predict.** A slot's chain was collapsed back to a flat triple before any container arm saw it, so a chain with no flat spelling was skipped rather than run — and the fix was not more kind dispatch in the containers. `{{join}}`'s slot read already dispatched by kind one layer down, and the base reader already accepted a chain-spelled `src`; what had to change was that the seam stopped re-spelling ([#104](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/104), [I16]). `try_`'s four arms were the one genuine dispatch rewrite ([#103](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/103)).

Enforced at: `bws_fold_chain_root()` / `bws_fold_chain_to_steps()` PHPDoc (slot-fold-compile.php). Vocabulary: [`deprecated-tags-options.md` §Folded slot wire](docs/deprecated-tags-options.md). Measured divergences: `tools/test/fold-test-matrix.md` §F9. Related: [I9] (the factory picks the base source by context), FW-39.

---

## I15 — An ambient read must be SPELLED, never reached by fallback

The ambient entity is what a bare tag resolves, so "ambient" is a legitimate answer — but only where the wire *says* so. At slot 1 and on a base tag an **absent** source spells it: absence there has no competing meaning, and `defaultRoot`/`stripDefaultRoot` make it the displayed default. At **slot ≥2** absence means **inherit**, so ambient is not a default the slot can fall back to. It has to be stated (`src:current`) or inherited from something that stated it.

**Rule:** a slot that cannot resolve the source its wire names says NOTHING. It never degrades to the ambient entity. The failure mode this forbids is specific and worth naming: an ambient fallback returns a *plausible value from the wrong entity*, which is strictly worse than empty because nothing looks broken — and in a selecting container an ambient read that succeeds *stops the fallback chain*, so later attempts never run.

Four shapes it decides, each of which used to fall back:

- **A step with no argument** (`src:ref` with no `ref`, a `terms` step with no taxonomy) is unfinished, not "no step". On a slot it skips, naming which step is unfinished so the preview can say what is missing; on a base tag the argument-less step reaches the engine and empties the chain. The one asymmetry: an argless `refs` on a slot is COMPLETE when the carry supplies its field ([I13]'s carry), and skips only when nothing ever did.
- **`src(same)` with nothing ever carried** — every preceding slot skipped, so the accumulator still holds its initialiser, which spells ambient. Reachable in a combining container, where an unconfigured read skips a slot without feeding the carry.
- **A `src` token the factory cannot identify** — unregistered, or a registered source whose `resolve_id()` finds nothing (an inert one, or a scope-bound one off its scope). Both refuse, unconditionally and with no per-source opt-out; the source factory answers a NAMED refusal kind rather than a null the caller would read as "no source stated" ([#75](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/75), [#76](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/76)). **Not every way the factory declines is a refusal** — which ones are, and why the odd one out is not, is `bws_factory_registry_source()`'s.
- **A STEP whose slug is unknown vocabulary**, at any non-leading position. Refuses too, and it is a DIFFERENT read from the one above rather than the same leak twice — disjoint by POSITION, since an unknown slug at position 0 is a ROOT and goes to the shape above, because a root is not a step ([I14]). What made it a leak: the base arms fell to a catch-all singular read with no case for the `''` kind such a chain resolves to, so the unknown step was silently DROPPED and the chain's PREFIX was read, collapsed to its first result ([#109](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/109)).

**Where the two are caught, and why it is not the obvious place.** Both are refused ABOVE the render cores, at the arm — never by handing a core no id and trusting it to stop. It does not stop, and it must not be changed to, because the read it falls through to is load-bearing elsewhere. `bws_base_read_refused()` owns what that costs and why the guard therefore sits where it does.

**Corollary — an inherited source carries what it IS, not merely its root.** `src(same)` names the same *source*, so its steps travel with it: a leading `terms` step leaves the root unset by design, and an inheriting slot that took only the root landed on the ambient entity. Contrast `limit`, which is a *parameter of* a source and is container-sensitive for that reason. **HALF OF IT IS STRUCTURAL since [#104](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/104)** — the carry holds a chain, and a chain holds its own hops, so the `$tax_inherit` scalar that carried the hop is gone. The DISPLACE half is not structural and is still a rule: part of the inherited chain can GIVE WAY to a step this slot states of its own, and the ROOT never does, because a slot inherits the SOURCE. It is a rule about what `same` means — a slot sentinel the container resolves before compiling — not about the chain grammar, so a base tag's `terms,a;terms,b` still hops twice.

**WHAT DECIDES HOW MUCH GIVES WAY IS OWNED BY `bws_fold_chain_join()` AND IS STATED NOWHERE ELSE, THIS INVARIANT INCLUDED.** It changed axis three times during #104, each reading correct on every legacy shape and wrong on a different hand-written one, and every site that had named an axis went stale while every site naming only the consequence stayed true — which is where the ownership rule in `CLAUDE.md` §Documentation ownership came from. The three readings and what each cost are in `docs/design-history/multi-step-slot-sources.md` §Corrections.

Enforced at: `bws_fold_slot_chain_options()` PHPDoc (slot-fold.php), `bws_fold_chain_to_steps()` and **`bws_fold_chain_join()`** (slot-fold-compile.php — the corollary's displace half, and the owner of what decides it); for the last two shapes, **`bws_factory_registry_source()`** (traversal-pipeline.php — which of its three declines refuse), `BWS_SOURCE_KIND_UNRESOLVED` (the refusal kind itself), **`bws_base_read_refused()`** (base-shared.php — the arms' one test) and `bws_try_slot_base_branch_kind()` (try-slot-arms.php — the selecting container's). Tests: `tools/test/slot-fold-test.php` §P16, `tools/test/fold-chain-compile-test.php` §C3/§C6, `tools/test/traversal-pipeline-test.php` (the two terminal refusals, the load-fallthrough staying non-terminal, and the refusal reaching each consumer), `tools/test/try-slot-arms-test.php` §A4.11–13. Rendered: `tools/test/fold-test-matrix.md` §F11. Rationale: [#74](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/74), [#112](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/112). Related: [I13] (wire era is per slot), [I14] (root is not a step; the fourth shape above is its unknown-step case), [I16] (the corollary becomes structural once the carry holds a chain).

---

## I16 — A slot's SOURCE *is* a base tag's source; the fold is storage, not a second model

A slot and a base tag do not have two kinds of source that resemble each other. They have **one** — one wire language, one parser, one resolution path. What differs is STORAGE: a slot folds its source into a single option value nested one level down, a base tag holds it in a bare `src` option. That is [I13]'s axis (`flat`/`folded` name STRUCTURE), not a second model.

**Rule:** a container hands a slot's resolved source on **as chain wire**, in the same option key a base tag uses. It never re-spells the chain into a flat `src`/`ref`/`srcTermIn` triple. A triple cannot express a chain that steps twice, so re-spelling is what made a slot's source a weaker thing than a base tag's — the limiter was never the offer list.

Four consequences, each with a wrong-looking-right alternative:

- **Wire, not a parsed structure, and the reason is not aesthetics.** `src` is already read by several things that are not the chain parser (the factory's root read, the `limit`-era selection, the preview namer). A parsed chain in a side key must be taught to every one of them — a second way to state a source, which is the two-writer divergence the twin migrators exist to prevent. Corollary of [ADR 0004](docs/adr/0004-serialized-tag-string-human-readable.md), not an independent trade-off.
- **Re-leveling only ever goes shallower, which is why it is safe.** Bracket alternation is by depth with two pairs (`()` level 1, `[]` level 2); a slot's chain sits one level in, so emitting it at depth 0 converts `[`→`(` and can never run out of pairs. The emitted string therefore DIFFERS from the stored slot value by design — an idempotence check must be a fixed point on the re-leveled form, never a comparison against stored wire.
- **The seam SUPERSEDES the legacy source axes.** Its output carries explicit empties for `ref`/`srcTermIn`, because the chain reader appends a `terms` step for any surviving `srcTermIn` — so a tag-level leftover would grow a step on every slot's chain. Left to each container this is one caller carrying a rule for another's sake: a combining container builds its slot options from the seam alone, a selecting one merges them over the tag's.
- **An inherited source carries its steps for free.** With a chain-shaped carry, [I15]'s corollary stops needing a special case: `src(same)` copies a chain, and a chain holds its own hops.

**The identity is SOURCE-only, and the read is NOT part of it — decided, twice.** Read-as-chain-terminal was superseded 2026-07-31 (the read is a sibling token, `src(chain);use(x)`); folding the read on base tags was locked out 2026-07-28 as all cost and no payoff. A slot's read and a base tag's read are the same two axes (`use`/`key`) stored two ways, exactly as the source is. Do not read this invariant as licence to revisit either.

**SHIPPED 1.17.0** ([#104](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/104)). Two things it moved that a reader will meet before they meet this invariant: a slot's registered step OFFER is now the base tag's (`refs` + `terms`, `entries` on neither, because no arm assembles a repeater row), and the `limit`-era write-back in both container loops became load-bearing — `src` is chain wire on every slot, including one recovered from legacy flat keys, so a container re-deriving the default from it answers *unlimited* where the slot has always bounded at 1.

Enforced at: `bws_fold_slot_chain_options()` PHPDoc (slot-fold.php), which replaced `bws_fold_slot_flat_options()`. Tests: `tools/test/slot-fold-test.php` §P18 (the emit fixed point, the chain-shaped carry, the named leak, the per-step bounds) and §P13.5, whose four inexpressible-chain SKIPS inverted to resolves; `tools/test/control-order-test.php` §7 (the offer). Measured divergences: `tools/test/fold-test-matrix.md` §F9d. Schema: [`tag-reference.md` §Folded slot wire](docs/tag-reference.md#folded-slot-wire-multislot-containers). Rationale + the rejected alternatives: `docs/design-history/multi-step-slot-sources.md`. Related: [I13] (storage is the other axis), [I14] (root is not a step; its slot half resolves here), [I15] (the inherit corollary becomes structural), [ADR 0004].

---

## I17 — A tag's serialized option order has THREE readers, and they must agree

A tag's option keys are canonically ordered by one ranking (`format` → per-slot `source` → `link` → `fallback`), but three independent code paths each apply it: the **editor normalizer** (`serialization-order-normalizer.js`, sorts on every `setState`), the **fold grammar** (`slot-fold-grammar.js`, ranks folded-slot tokens at emit), and the **converter** (`MigrationRegistry::run_transform()` step 8, canonicalizes migrated wire). A `transform_callback` entry is exempt by construction — it overrides the pipeline and owns its own output order (as+size, related_post, modifier→base).

**Rule:** the three must never diverge. If they did, a migrated tag's key order would disagree with what the editor writes on save, and opening a migrated tag would show a spurious whole-tag diff the first time an author touches it — reading as "the migration changed something else too" even though nothing about the tag's meaning moved.

Enforced at: `bws_serialization_order_sort()` / `bws_serialization_order_sort_map()` PHPDoc (`includes/helpers/serialization-order.php` — the single PHP owner both the fold grammar and the converter's step 8 call through) + its JS port `assets/js/serialization-order-normalizer.js`. Tests: `tools/test/serialization-order-test.php` (pure sort + a JS-port twin block, `node`-required) + `tools/test/datetime-migration-test.php` §D6 (ties the migration harness's literal key-order expectations back to this ranking, so a `KEY_MAP` change fails there by name). Schema: [`tag-reference.md` §Option order](docs/tag-reference.md#option-order).

## I18 — Offering a source is not resolving it

A source's opt-in to the chain-**root** enum (`is_selectable_root()`, #83) governs ONLY the authoring-surface dropdown — which rows an editor control renders. It must never gate resolution. Stored wire naming a source is hand-editable by decision ([ADR 0004](docs/adr/0004-serialized-tag-string-human-readable.md)), and an integrator can stop offering a root after tags already name it (FW-70's migration writes such wire the moment it runs) — gating resolution on the flag would blank every one of those tags. `bws_factory_registry_source()` resolves ANY registered source unconditionally, whether or not it opted into the root enum; the flag is read nowhere in the resolution path.

**Corollary — offering is STATED, never derived, and stays that way permanently.** The registry keeps its dead by policy: `register_source()` is never deleted for lacking resolve logic, so four retired traversal-substitute classes plus the internal `post`/`term` keys remain registered right now. None of them promote to the root enum, because `bws_registered_root_rows()` is the ONE appender both authoring surfaces — the base tag's root enum and the folded slot's source enum — take their rows from: "offered here, absent there" cannot happen, and a dead-by-policy registration cannot leak into either dropdown just by existing.

Enforced at: `bws_registered_root_rows()` PHPDoc (`includes/tags/base-shared.php` — the one-appender rule) + `bws_factory_registry_source()` PHPDoc (`includes/helpers/traversal-pipeline.php` — the unconditional resolve). Pinned BY MUTATION: gating `bws_factory_registry_source()` on `is_selectable_root()` fails `tools/test/traversal-pipeline-test.php`'s registry-delegation section by name. Tests: `tools/test/slot-options-build-test.php` (registration output — which rows each enum contains), `tools/test/traversal-pipeline-test.php` (resolution, unaffected by offering), `tools/test/registered-roots-test-matrix.md` §FR1/§FR2/§FR5. Related: [I14] (a chain's root), [I15] (an unidentifiable `src` refuses, one of `bws_factory_registry_source()`'s three declines).

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

Terms for the **source-resolution model** (the L1/L2/L3 read pipeline shared by text/email/phone/datetime/join/try_). The L1/L2 seam is **built for email/phone** as the shared `bws_resolve_field_values` (field-helpers.php, 1.11.0 — retired the per-tag clones); other tags still inline their own L1/L2. Full unification is incremental (see `docs/design-history/try-email-phone-and-slot-derivation.md`): the datetime param-overload retire + `src:ref` fanning landed 1.15.0 (FW-3a — datetime cores take resolved-source payloads, kind-dispatched, though still off-seam pending its format-aware read arm); #19 context kinds remain.

**Read target** (casual shorthand: **target**):
The **declared read intent** of a tag — its (source + key) specification. `{src:ref|key:email}` is one read target. Either part may be **explicit** (written token) or **implicit** (stripped default / recovered: source unset → current/context-default; both unset on `{{title}}` → analog). The resolved *intent*, NOT the literal token string. (Implicit/explicit/unset axis: handoff source-analog mode terminology. **#19 = read targets with an implicit source resolved by WP context.**) "target" alone always means read target — NOT resolved source. _Avoid_: "entity", `{kind,id}`.

**Source binding** (two orthogonal axes describing WHERE a source's read-entity comes from — classifies every `src` flavor, drives the [I4] qualifying gate at the source level):

*Axis 1 — invocation (is a source serialized?):*
- **implicit** — no `src` token; the tag infers its entity from WP context. The bare queried tag ONLY (`{{title}}` on a singular/term archive, #19).
- **explicit** — a source is serialized (author-selected). EVERY other flavor, incl. `src:current` (same OUTCOME as implicit — reads the queried item — but explicit once written, e.g. a serialized try_ slot 2+). "selected" is an informal synonym for explicit (the author selected a Source); it is NOT a pole name — all three axis-2 flavors below are "selected" in this loose sense.

*Axis 2 — entity provenance (who supplies the read-entity — the meaningful split among explicit sources; the implicit tag's hidden provenance is always `detected`):*
- **detected** — an ambient signal supplies the entity, so it varies per render: WP query object / loop row (`src:current`, term-archive), a related-post step (`src:ref`), or the active Site View / user session (`view_`). `view_` is detected-yet-explicit — detection is NOT the same axis as implicit/explicit.
- **global** — no per-entity read; a site-wide datum (`src:site`). A Site View may ALSO act site-wide, but `view_` stays **detected** because a signal (the active view) selects it; `src:site` consults nothing.
- **ID** — the author identifies ONE specific entity and its id is serialized into the token (probable `src:<type>,<ID>` shape, **not final**). The **ID source** — the only flavor carrying a serialized entity id. This is the "pinned/specific resource" concept the qualifying gate points at (FW-39 ID source, FW-33 `term_` deprecation). Names the mechanism (serialized id) = the provenance (author supplies it).

Grid: `implicit`→bare queried (detected). `explicit`→ detected (`current`/`term`/`ref`/`view_`) | global (`site`) | ID (`src:<type>,<ID>`). Prefer **global** over "fixed" for `site` — "fixed" also fits ID sources (fixed-per-render), so it under-discriminates. _Avoid_: "contextual"/"context source" as the NAME of this axis-2 pole — say **detected** (the pole spans query, a `refs` step, AND session/view; "context" in the doc means specifically the #19 *query*-context, a subset, so naming the whole pole "contextual" blurs it with that subset). "context modifier"/"context-aware"/"context kind" elsewhere are unaffected. Also avoid "entityless" for `global` (collides with unresolvable-read / post/0 empty).

**Resolved source**:
L1's output executing a target — the **bound *where*** a read happens, key not yet applied. post/term carry an id (meta-read needs one); **site** carries the `wp_options` namespace; future ones (#19 date/search, possible external Site-Views option-set source) carry their own payload. id is a post/term implementation detail, not universal. **Payload may legitimately vary by read mechanism within one kind:** site-datetime reads via ACF `get_field(key,'option')`, site-text via plain `get_option` — same `site` kind, different L2b read path. Frame-B variable payload (ADR 0002). **Distinguish legitimate payload-variance from a contradiction-to-refactor:** today datetime overloads the *post_id parameter slot* by passing the literal string `'option'` through it (datetime-tags.php:1005) — that param-overload is a contradiction of this model (a resolved-source payload smuggled through an id arg), REFACTORABLE, not canonical. Likewise `ref` collapsing to one target (`bws_extract_post_id`) contradicts the fanning-source model → fix the code, don't model around it.

**Resolved field**:
L2a's output — **WHICH field to read**, determined by (resolved-source TYPE × implicit/explicit key options). Author-perspective: the field worked out before the fetch. Where the **analog** lives — `use:default` on a term resolves the field to "term name"; **I2 Model-B `use`-dispatch operates here** (use × source-type → field/analog). _Avoid_: confusing with field value (the datum).

**Field value**:
L2b's output — the **fetched datum** off the resolved field. The raw value before L3 assembly.

**Singular vs fanning sources**:
A resolved source is **`ResolvedSource[]`** — a list, usually length 1. A root (`current`, `site`) is **singular**: exactly one, always. A **fanning** step (`refs` — ACF relationship/post-object array; `terms` — taxonomy term set; `entries` — repeater rows) **may** resolve many. List mode originates here — *a fanning source, read once per source* — NOT a read-time loop.

**`fanning` is a STATIC property, read off the wire: capacity, not outcome.** A fanning step routinely resolves exactly one (a relationship field limited to 1, a single-term taxonomy, a one-row repeater), and that is not a different kind of source. The runtime count is a **length**, and needs no adjective. This split is what FW-63's dispatch depends on — I8 forbids a live probe, so "does it fan" must be answerable from the wire alone.

(Scoped: the RETIRED source-class path collapsed `refs` to the first via `bws_extract_post_id`. The ENGINE's step has never collapsed, and the flat assemblers retired behind the chain compiler in 1.17.0. What survives of that defect is the tag-level `limit` default of 1, which is now selected by the source SPELLING — flat wire keeps it, chain wire does not; see `bws_limit_default`. **A SLOT's default is selected the same way, by the SLOT's own spelling**, which cost a seam change: the flatten collapses a slot's chain to a flat triple before any container arm resolves a limit, so the spelling is gone by the time the question is asked and the seam has to report the era it erased. Only where the slot's own chain FANS, though — a slot spelling `src(same)`, or an argless `refs`, fans by INHERITING a source another slot already bounded, and a limit does not carry forward.)

_Avoid_: "target cardinality" (the property is the SOURCE's, and :236 reserves "target" for read target); "plural source" as a claim about a given render (says outcome where only capacity is known); "multi-valued" (a step produces resolved *sources*, not **field values**).

**Inert chain**:
A source chain that resolves to **nothing on every tag, for a reason readable off the wire alone**: an unknown step slug (the engine short-circuits, [I14]), an unregistered root token, or a retired source token. Statically decidable with no per-template knowledge, which is what makes it sayable in an editor preview — see [`editor-tag-previews.md` §Inert-chain warning](docs/editor-tag-previews.md).

Three neighbours it is NOT, each of which renders empty too:
- **Unfinished** — a fanning step with no argument (`src:terms` with no taxonomy). Expressible and half-written; the author's next act is to finish it, not to replace it.
- **Unconfigured** — no read stated yet. A normal in-progress state.
- **Unimplemented** — well-formed wire no arm consumes *yet* (an `entries` step outside `{{table}}`, FW-74). **Unimplemented is not inert**, and conflating them is the mistake this term exists to prevent: it encodes a per-template fact with a shelf life, and the tag becomes correct without anyone touching the sentence that called it broken.

_Avoid_: "unsupported source" (retired in 1.17.0 with the flatten — it named a limit of the *storage*, not of the source); "invalid" (reserved for the `src:site`-on-a-modifier combo, which is invalid *for that tag* and resolves fine on the base one).

**Output destination** (list-mode divider — see I7):
WHERE a tag's produced value lands, gating list-joinability. **Text-flow value** (text/email/phone/datetime) → joinable. **Attribute slot** (image URL → GB `<img src>`; tag returns string, GB injects) → singular. **Body/document** (content) → not `sep`-joinable. _Avoid_: "inline/block structure", "query-loop boundary", "entity-count" (wrong-axis — superseded). Image proves destination ≠ structure: a plain URL string excluded by its attribute destination, not by being "block".

**L1 / L2 / L3** (layers executing a read target):
- **L1 — resolve source:** source options → `ResolvedSource[]`. The *where*; no key. Recovers implicit/unset source (→ #19 context resolution).
- **L2a — resolve field:** (resolved-source type × key options) → **resolved field** (which field/analog). I2 Model-B dispatch.
- **L2b — fetch value:** (resolved source, resolved field) → **field value**. Dispatches post/term → meta, site → option. Once per (source × field). Current code: `bws_read_field` / `bws_read_term_field` / `bws_site_read_option`; email/phone wrap L1+L2 as the shared `bws_resolve_field_values` (the seam — handles src:site, srcTermIn list mode, single post/term).
- **L3 — assemble:** per-tag compose over sources × fields (implode/`sep`, datetime range, join template, mailto/tel wrap), landing in an output destination. Per-tag; L1/L2 shared.

A tag reads **K fields × T sources** and assembles: text=1×1 (or 1×N via a fanning source), datetime/phone-ext=2×1, join=N×1, email-via-srcTermIn=1×N.

## Pointers

- **PHPDoc invariants in code** (single-class): `bws_site_allowlist_ok` (allowlist), `bws_site_read_option` (single-reader), `bws_resolve_link_url` (site link = permalink-analog), `bws_parse_combined_date_time` (datetime value-id sentinel), the email callback + settings accessor (VE1-VE4).
- **Field discovery (1.13.0, I8):** `includes/rest/field-discovery.php` (offered⟺resolvable gate, `scopes_equal` keep-both dedupe, per-subtype registered meta, `<script>`-safe JSON encode) + `assets/js/field-combo-control.js` (editor-time kind projection, `(kind,key,label)` merge, flat filters, ambiguous-key raw display). Schema: `tag-reference.md` §Custom control types. Design/follow-ups: `.scratch/plans/field-selector.md`.
- **Architecture decision records:** [`docs/adr/`](docs/adr/).
