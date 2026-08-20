# Archive: Ref-hop parity — FW-32 (RETIRED, never designed)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.

**Retired 2026-08-19 with the 1.17.0 release.** FW-32's three limits were discharged by that
release — a chain picks a root THEN steps, the fan-out is preserved rather than collapsed to
first, and multi-`refs` chains are authorable — and its `/`-vs-`+` separator question was settled
to `;` by FW-56. The one residue, a `refs` step off an ID-PINNED primary, was always FW-39's
scope by this file's own text, so the row retired rather than being re-cut.

**Read this as a record of how the problem was understood while it was open, not as a statement
of how anything currently works.** Everything below predates the release. Its `{{table}}`
PRODUCER seed (§the relationship-rows material) is the part still worth reading — that was input
to `{{table}}`, tracked as FW-53, and it never depended on FW-32 being built.

---

**Status: UNDESIGNED. No spec, no chosen shape.** This file is the FW-32 detail home
(promoted from the `traversal-pipeline.md` §Remaining stub + the archive §Problem
parity gap). It collects the concept + a SEED design lifted from the `{{table}}`
relationship-rows grill. The seed is INPUT, not a lock — FW-32's global shape is not
bound to the table draft. Blocked-by FW-9 / FW-10; unifies with the ID source (FW-39).

Tracker row: `docs/future-work.md` FW-32 (current authority per item). Rationale roots:
`docs/design-history/traversal-pipeline.md` §Problem; CONTEXT.md §Axis-2 (provenance,
the ID source); live soft-gate `includes/tags/base-shared.php` (permalink/user analogs
"soft-gated on FW-32/FW-39").

## Problem

`src:ref` today has three hard limits, all surfaced during the traversal-pipeline Phase 1
build (v1.14.0) and left unaddressed:

1. **Hardcoded origin.** The hop always starts from the ambient detected context —
   `RelatedPost::resolve_id` (superseded by `CurrentPost` + `ref` step) reads the current
   post. There is no "pin THIS specific primary, THEN hop" path. Pick-primary-then-hop is
   the deferred parity gap (archive §Problem; the N×M source classes
   `SecondRelatedPost` / `PostTermRelatedPost` were the old brute-force answer, now retired
   in favor of chained `ref` / `srcTermIn` steps — but the chaining is engine-internal,
   not author-exposed).

2. **Collapses fan-out to first.** The engine ALREADY fans out naturally (one source × step
   → N; archive §Engine fold). But `src:ref` at the tag seam still collapses the multi-post
   result to ONE target — the `src:ref` plural-source fix did NOT ship in Phase 1
   (archive L396: "ref still collapses to one target"). srcTermIn term-hop lists work
   post-#32; the ref arm's plural fix is the open next increment on the same seam.

3. **Single ref field, no author ref-step control.** No way to traverse more than one ref
   field, and no per-`src`-value ref-step option to say "hop off THIS source."

## Concept seed (systematic frame — NOT a spec)

The clean unification the refactor implies:

> **`src:ref` becomes a general hop-off-base that CAN fan out.** Pick a primary source
> (current / ref / site / ID / view), THEN apply a ref-step off it; the step may return
> one post or many. Consumers choose how to treat the fan-out: single-result semantics
> collapse to first (today's behavior, preserved); a list consumer keeps all.

Consequence for `{{table}}`: **any source chain that fans out is table-loopable.** A table's
row-set = "consume the src-chain return as rows" instead of "collapse to first." Under this
model (locked for table 2026-07-26, `table-tag.md` §Row-count is SRC-CHAIN CARDINALITY) there is
**no row-production MODE at all** — no flat/repeater/relationship discriminator. Row-COUNT falls out
of the chain: returns N → N rows, returns 1 → 1-row table. A relationship row-set is just the chain's
`ref` step FANNING OUT (`post` rows); relationship is NOT a table mode. That dissolves the entire
standalone relationship-producer apparatus the earlier table drafts carried (a relationship-specific
producer, a mode control, dual scope-hint). What remains genuinely FW-32's is the GENERAL chain fix:
make the `ref` chain token fan out (drop collapse-to-first) + its foreign-PT `{N}-key` scope, feeding
ALL fan-out consumers. The cell READER (row-kind-branched) is table-owned (`table-tag.md` §The cell
reader) and producer-agnostic — FW-32 reuses it, does not own it. So the seed below is the PRODUCER
HOP + its foreign-PT scope only.

**Both this (FW-32) and the table authoring surface now depend on the multi-step src-selection
encoding model (FW-56):** promoting `ref`/repeater to author-facing ordered chain steps needs the
chain's serialize + editor-surface model, which is unsolved and cross-cutting. FW-32's fan-out
RESOLUTION (engine) is separable from that ENCODING (authoring) — but the author-facing "pick a
fan-out chain" experience is gated on FW-56.

UX-open (tracker): likely a separate ref-step option per-`src`-value. Unifies with the ID
source (FW-39: `src:<type>,<ID>`, the pinned/specific-resource concept the qualifying gate
already points at — CONTEXT.md Axis-2).

---

## SEED — `{{table}}` relationship rows (moved from `table-tag.md`, 2026-07-25)

**Provenance + status: this is the PRODUCER-HOP half of the `{{table}}` relationship-rows grill
(GRILLED + LOCKED for TABLE 2026-07-24), MOVED here because table re-deferred the relationship
PRODUCER to FW-32. It is FW-32 SEED / acceptance criteria — NOT binding on FW-32's global design.**
Under the src-chain-cardinality reframe (table, 2026-07-26) there is **no table row-production
mode** — relationship rows are just the `ref` chain step fanning out. This file keeps only the
producer HOP + its foreign-post-type scope. **The CELL-READER half of the grill did NOT move — it is
table-owned** (`table-tag.md` §The cell reader — a row-kind-branched reader over `meta_row`/`post`/
`term`, producer-agnostic); FW-32's fan-out reuses it. When FW-32 runs, this is the first real design
content it has — richer than the one-line tracker. Read it as "what a table `ref`-fan-out PRODUCER
MUST satisfy." **NB the parts below still using flat/repeater-MODE or `Q3`-mode-control language are
STALE framing** from the 2026-07-25 pass — the mechanism they lock (ref-step-as-producer, foreign-PT
scope, id-threading) is still valid; only the "mode control ships v1" wrapper is retracted. Both this
and the table authoring surface are gated on the FW-56 multi-step src-encoding model.

### The scoping tangle that motivated the move

The reason relationship rows grew a heavy standalone apparatus: `src:ref|ref:` (a hop from the
ambient base, single hop / single field / collapse-to-first) and a global relationship `key` in
row-looping mode are TWO NAMES FOR THE SAME PRIMITIVE — hop a relationship field → post(s). The
`ref` step (`bws_pipeline_ref_to_posts`, sequential-list arm) is literally what relationship mode
reuses VERBATIM (Q2). The only reason a distinct "mode" appeared is that `src:ref` collapses the
fan-out to one while table wants it preserved — which is exactly the FW-32 fan-out fix. So the
mode apparatus is a bet FW-32 cleans up (same smell as Q5's "two ad-hoc flats betting FU-1 cleans
up later = unshipped debt," one level up).

### MODE-scoping apparatus (SEED — the load-bearing scope problem)

- **The MODE control was LOAD-BEARING for `{N}-key` scoping, not just a picker of read-strategy.**
  A global loop-type would be the discriminator selecting WHICH field-discovery scope the
  per-column `{N}-key`/`{N}-use` pickers resolve against. Two scope axes, mode-selected:
  - **Repeater mode → `repeater_key` stamp** (field-discovery.php:286/311; consumed by
    field-combo-control.js `scope:'row'`, L529-541). BUILT. Picker keeps only records whose
    `repeaterKeys` include the sibling row-repeater handle. **This is the ONLY scope table v1 ships.**
  - **Relationship / query mode → target POST-TYPE field set.** A related/queried row is a whole
    foreign post — its fields are NOT sub-fields of anything, so `repeater_key` is the WRONG axis.
    The picker must load that post-type's field set. field-discovery does NOT emit this scope.

- **TWO STACKED REF HOPS — do not conflate.** Relationship rows and per-column `{N}-src:ref` are
  DIFFERENT hops, stacked, not alternatives:
  - **GLOBAL relationship field = the ROW PRODUCER.** Names a relationship ON the current post;
    each related post becomes one ROW. Tag-level, never per-column. Its return post-type is the
    scope the row-post columns read against. (In the FW-32 frame this "global rel field" is just
    the fan-out ref-step off the base — not a separate control.)
  - **`{N}-src:ref` = a per-column DOWNSTREAM hop, off each row-post.** From the row-post, hop to
    ITS referenced post, read a field there — one column's cell only. A second hop beyond the row
    producer. Its target is a different post-type again (the row-post's own relationship
    return-type), a per-column scoping problem of its own.

  ```
  current post ─(row-producing ref-step)→ row-posts ─┬ {1}-src:current: field ON row-post
                                                      └ {2}-src:ref: hop row-post→referenced post→field there
  ```

- **AVOIDANCE PATH — self-derive the discovery SCOPE (not avoid the row-producer).** The
  row-producing ref-step is NOT avoidable — it IS the fan-out that produces rows. What a
  `returns_post_type` stamp buys is scope derivation WITHOUT new discovery infra:
  - Foreign post-type fields are ALREADY in the discovery payload — discovery fetches ALL field
    groups, no post_type filter, each group carrying `kind_scope` slugs
    (field-discovery.php:463-466, L336); the client ingests every kind's groups + full location
    paths (field-combo-control.js:274-327). Missing piece is NOT a second discovery pass — only
    WHICH scope to filter `{N}-key` to.
  - That scope = the row-producing ref field's return post-type. NOT stamped today: `repeater_key`
    is stamped (L286/311) but no `returns_post_type` (schema L227 — absent). ACF stores it in
    `field['post_type']`.
  - **If field-discovery stamps `returns_post_type` on relationship/post_object fields** (cheap,
    same additive pattern as `repeater_key`), the `{N}-key` picker sibling-reads the tag-level
    row-source field value → finds that field's record → reads its `returns_post_type` stamp →
    filters the payload to that scope. Same sibling-read shape as shipped `scope:'row'` (reads
    `state.key`), just a different sibling. No NEW discovery pass, no NEW post-type picker.
  - **`{N}-src:ref` columns need their OWN scope derivation** — hop from row-post to referenced
    post → that referenced post-type's fields for the column key. Same `returns_post_type`
    mechanism, read off the row-post's relationship field named by the column's ref selection.
    Second, independent instance of the pattern.
  - **BOUNDARY — QUERY has no field to carry the target.** A query row-source's target post-type
    is a QUERY ARG the author picks; nothing to self-discover, an explicit tag-level target
    selection is irreducible. (Query is separately deferred: it is a new L1 base-source kind
    `src:query`, home `.claude/plans/query-source.md`, NOT part of ref-hop parity.)

### The grilled MECHANISM — the PRODUCER-HOP half (Q2/Q4/Q5/Q8/Q11 + Q3 mode)

These lock MECHANISM, not spelling; all option names PLACEHOLDER (settled in the table #8 options pass).

**The CELL-READER half (Q1/Q6/Q7/Q9/Q10) is NOT here — it SHIPS in table v1 via flat mode.** Row
PRODUCTION and cell READING are separate seams (grill Q1). The reader items — the explicit `post` cell
branch, `bws_read_field` post reads, the `{key,title}` enum, the `{meta_row,post}` row-loop guard, the
empty-cell contract — are producer-agnostic: flat mode reaches the `post` cell branch without any hop, so
table v1 builds and OWNS them. See `table-tag.md` §Flat mode + the cell reader. The relationship producer
below REUSES that reader verbatim (a related post IS a `post` row); it adds only the PRODUCER hop + its
editor-side scope. Do NOT re-spec the reader here.

- **Q3 — the row-production mode is an EXPLICIT, SERIALIZED author control.** Table v1 already ships the
  control for FLAT (stripped-default) vs REPEATER (see `table-tag.md` row-production disentangle note).
  RELATIONSHIP is a THIRD value on that same control, added by FW-32. Forced by remount stability (I8),
  not just render: the editor needs the mode at config time to scope the `{N}-key` pickers + drive
  preview; an INFERRED mode (`get_field_object`) is ACF-only, empty in Patterns/Elements (the I8 blind
  spot), and not stable across remount. Serializing makes it reconstructable from the wire alone. No
  first-touch inference autofill. **FW-32 caveat: if `src:ref` becomes a general fan-out ref-step, the
  relationship VALUE may be subsumed by the source axis rather than a distinct mode value — re-grill in
  the FW-32 pass. The flat/repeater control itself is settled (ships in table v1).**

- **Q2 — relationship producer reuses the `ref` step VERBATIM; NO type-gate.** Callback appends
  `['type'=>'ref','field'=>$key]` (relationship) vs `['type'=>'rows','field'=>$key]` (repeater). Same
  trust model as `rows` (locked #2: "wrong field → empty/garbage, unpreventable, author error").
  Rejected a relationship-only fail-closed producer: breaks the §V16 / I9 handler-agnostic contract (a
  Pods/Carbon relationship in plain meta must still resolve). **Open note:** guarding BOTH producers
  (`rows` AND `ref`) on DISCOVERED field type is a future symmetry option; if built, gate on discovered
  type ONLY, never reject a plain-meta relationship. The `ref` step already returns `post[]`
  (`bws_pipeline_ref_to_posts`, sequential-list arm) — no engine change, only the collapse-to-first
  removal that IS the FW-32 fan-out fix.

**Field-discovery scope (the real editor-side cost):**

> **CORRECTION 2026-07-27 (supersedes the `returns_post_type` framing in Q4/Q5/Q8 below AND the
> "TWO STACKED REF HOPS" avoidance block above).** An ACF relationship field's allowed post types are
> an ARRAY (0/1/many; empty = all) — NOT a scalar "return post type," so there is NO `returns_post_type`
> stamp to add. Scope = the UNION of the field's `post_type[]`, read LIVE from the discovery payload
> (already present, field-discovery.php:463-466) each editor load — **never serialized** (a baked PT
> goes stale when the field's config changes). Author narrowing/override = a VISIBLE ephemeral filter,
> not serialized (author-intent persistence is an open sub-decision). Full model + worked example:
> [`src-chain-encoding.md`](src-chain-encoding.md) §The `ref` post-type scope (FW-56). Read Q4/Q5/Q8
> for the union-scope MECHANISM; ignore their `returns_post_type`/serialized-PT spelling.

A `ref`-fan-out row reads a FOREIGN post-type's field set (the ref-hop target), so the scope work below
is FW-32's (mechanism valid; PT-derivation corrected per the note above):

- **Q4 — `{N}-key` relationship scope = UNION across the relationship field's allowed post types;
  unrestricted → full post pool; free-text always open.** Generalizes #12's fallthrough philosophy.
  Union may surface a field on PT-A on a row that's PT-B → empty cell = the same honest gap (I1/#29).
  **Deferred follow-up (Q4): a PT filter integrated into the Location filter** — a GENERIC
  field-selector enhancement, home `field-selector.md` (folds into v2 PT-scoping + FU-1 structured
  location value). Build-now ships on union scope.

- **Q5 — ONE structured scope-hint field, REFACTORED from the unshipped `repeater_key`** (serves
  BOTH repeater sub-field scope AND relationship related-PT scope). `repeater_key` is NOT on
  origin/main — lives only on the table branch — so it is NOT a shipped precedent to "ride." Adding
  a SECOND flat field (`ref_post_types`) beside it = unshipped debt. Since all unreleased, the
  refactor is FREE: consolidate into one structured carrier (shape e.g. `{via:'repeater'|
  'relationship', keys:[...]}` — settle at build) that both scope modes read. Lands FU-1's
  "structured, not parsed-string" shape for the SCOPE axis only. Touches THREE in-branch pieces:
  server stamp (`field-discovery.php`) + client accumulator (`repeaterKeys[]` → structured consumer,
  `field-combo-control.js`) + the `{N}-key` filter predicate.

- **Q8 / Q8b / Q8c — tag-level `key` picker: mode-reactive scope, Type filter SUPPRESSED, Location
  kept full-width; NO author-facing multi-select.** In relationship mode the picker's TYPE preset =
  union `relationship`+`post_object` (vs flat/repeater's existing presets) via the `presetKind`
  sibling-token read. Because the mode already declares the type, the Type filter is REDUNDANT →
  suppress it (the #12 `scope:'row'` precedent). Union becomes a pure internal SCOPE, never a
  control state → the single-vs-multi-select control question never arises for table. True
  author-operated multi-select deferred to v2. Q8b: Location filter STAYS. Q8c: NEW third layout
  state — when Type suppressed, render Location FULL-WIDTH (new prop `hideTypeFilter` or
  mode-derived), distinct from `scope:'row'`.

- **Q11 — existing base-source id-threading suffices; NO new I11 code.** The only editor-fragile
  read is the BASE source (already threaded, I11). Ref-hop + cell reads operate on CONCRETE
  related-post ids → resolve in any context. One build-time SMOKE TEST: testbed editor preview of a
  relationship-row table resolves related posts.

**Fixtures + testing (Q12):** the `post` cell branch is already proven by FLAT fixtures in table v1
(`table-tag.md` §Flat). What relationship testing ADDS: a NEW tag-level relationship field on the matrix
host (`page-matrix-post-meta`) → 2-3 existing `staff` singles, proving row PRODUCTION via the ref-hop
(the fan-out that flat/repeater don't exercise). Visible **TB5+** rows (self-describing per
feedback_fixture_row_label_expectation). Union-scope case (Q4): a 2nd relationship field allowing 2 PTs
if cheap, else render-tag-only with a stated matrix exception. **Harness split:** relationship row
PRODUCTION is WP/ACF-dependent → testbed (`render-tag` + visible rows), NOT the pure
`table-assemble-test.php`.
