# Archive: `{{table}}` tag — FW-53, FW-159 (SHIPPED, next release after 1.21.1)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.
>
> Committed whole at retirement. What was still open moved to live plans first (FW-158, FW-160 to FW-167, FW-168); the paths it cites under `.scratch/` were private when written and are not repointed.

Original title: Plan: `{{table}}` tag — repeater → HTML table

> **THIS FILE IS THE DECISION RECORD. The BUILD DESIGN is [`.scratch/table-tag/spec.md`](../table-tag/spec.md)** (written 2026-09-18, twelve tickets under `issues/` since 2026-10-15). Read the spec to build; read this to find out why something was decided. The spec inlines only four decisions from here (#4, #13, #7 and the wire-vs-resolved trap) and links the rest, so a row below is still the owner of its own answer. Three things the spec SETTLED that this file left open — the row-count rule's consequence for `limit`, the scope anchor's shape, and what a bare column serializes as — are stated there, not here.

## §SETTLED index

Pointers only — content stays in the sections named. Anchors are section titles (or, for a bullet
inside one, the bolded lead-in text to search for); line numbers are not the identifier and drift on
every edit. **Reach** marks whether a decision is table-only or crosses into base tags / `{{join}}` /
`try_`. Where a row supersedes an EARLIER numbered/lettered decision in this file, that decision's own
text is left in place with a strikethrough or a superseded-note at its own site — this index does not
duplicate the supersession story, only points at where it lives.

| # | Decision | Section | Reach | Current answer |
|---|---|---|---|---|
| 1 | Fanning step wire spelling | §SETTLED 2026-08-22 | cross-container (shared fold vocabulary) | `rows`, not `entries` — `entries` keeps the relationship-field sense |
| 2 | `{{table}}` back-compat posture | §D1 | table-only | Folded-only, no flat wire ever shipped or migrated; retires original Decision #8's migration-wording debt |
| 3 | Tag-level row producer | §D2 | table-only (mechanism); cross-container (consequence — base tags gain a `rows` chain step) | No tag-level `key`; the src chain IS the row producer, retires original Decision #11 |
| 4 | Which resolved kinds are valid rows | §D2a | cross-container (the read seam) | ANY resolved kind, no allowlist; supersedes original Decision #10's ref→title cell hop and the flat/repeater/relationship "three shapes" framing |
| 5 | Repeater-row READ ARM scope | §D4 | cross-container (base tags, `{{join}}`, `try_`) | Lands generally as FW-74, not table-local. SHIPPED 1.21.0; build record `docs/design-history/repeater-row-arm.md`, §D4 keeps the decision |
| 6 | Analog dispatch (`use:title` …) on a row | §D4 "THE ONE CALL D4 NEEDS" | cross-container | Analogs REFUSE on a row (option a) — no auto-hop; a hop is spelled explicitly in the chain |
| 7 | Column-slot default root | §GRILLED Q1 | table-only | A bare column roots at ITS ROW; `current`/`site`/registered roots stay explicit escapes |
| 8 | Column key/wire spelling | §GRILLED Q2 | table-only | Shipped letter ordinals (`A`,`B`,…); retires original Decision #8's `{N}:` numbering |
| 9 | Header-cell (`<th>`) content token | §GRILLED Q3 | cross-container (shared grammar token; table is first real consumer) | Reserved-but-unconsumed `label` renamed **`header`, PROVISIONAL** — final spelling rides the option-name pass. CONTROL scope settled separately by #27: token shared, control table-only |
| 10 | Per-column text alignment wire | §GRILLED Q4 | table-only (first container-specific token in the shared grammar) | Registered fold grammar token `align(…)`; needs `bws_serialization_order_key_map()` + JS `KEY_MAP` entries |
| 11 | `try_` coverage of the repeater-row arm | §GRILLED Q5 | cross-container | `try_` gets it too; `BWS_TRY_SLOT_ARMS`'s `meta_row` refusal comment is superseded |
| 12 | Base-tag chain step OFFER | §GRILLED Q6 | cross-container (two call sites: `base-tags.php:928`, `class-tag-template-registry.php:654`) | Base tags + `try_` offer `rows`; `term_`/`view_` unaffected (flat, no chain offer) |
| 13 | Empty-row rendering | §GRILLED Q7 | table-only | Always render — row count = chain cardinality exactly, no skip-if-all-empty |
| 14 | FW-74 shipping order | §GRILLED Q8 + §GRILLED 2026-09-03 fourth pass | cross-container (sequencing) | Ships as its own change, before `{{table}}` — **COMMIT-ordering, not a prerequisite** (fourth pass: nothing in `{{table}}` waits on the arm) |
| 15 | `src(same)` on a table column | §"The ONE question…" (src(same) bullet) | table-only | ~~Build it; never the default (Q1 owns the default)~~ **SUPERSEDED by #50** — the carry-over survives as copy-on-select, `same` is never written |
| 16 | Per-column source axis (`{N}-src`) + folded column wire | §What D1+D2 DISSOLVE | table-only, rides FW-71 | Collapse into one item — a folded column's chain IS its source axis; retires original Decisions #7 and #8 as separate items |
| 17 | Field-discovery scope anchor | §What D1+D2 DISSOLVE | table-only | Moves from the tag-level `key` option to the `rows` step's own chain arg; original Decision #12's discovery mechanism survives unchanged |
| 18 | Repeater-row READER mechanism | §Locked decisions #3 | table-only | ACF `get_field()` (never `have_rows`), kind→selector switch, raw-meta fallback — unchanged by the reopen |
| 19 | Fold vs. presentation ownership | §Locked decisions #4 | table-only | The fold stays dumb (empty-repeater short-circuit only); all presentation is tag-side L3 — unchanged |
| 20 | Output shape | §Locked decisions #9 | table-only | Atomic `<table>` string in a `text`-as-`div` GB block — unchanged |
| 21 | Accessibility pass | §pre/post-v1 uncertain (a11y sub-section) | table-only | BUILT — caption, caption-gated scroll region, no presentation classes, queued CSS; unaffected by the reopen |
| 22 | Serialization order model | §Options pass "Serialization-order model" | cross-container (join/try_ byte-identity is the invariant under test) | Slot-major recursion (A1), LOCKED 2026-07-24; the new `header`/`align` tokens (#9, #10) must register into it |
| 23 | Column cap | §Options pass "Column cap" + §GRILLED 2026-09-03 A12 | table-only | `BWS_TABLE_MAX_COLS = 10` — its OWN const, same number as `BWS_JOIN_MAX_SLOTS` by independent judgment, never derived from it. Driver = registration weight; the slot ordinal has no ceiling (`27 → AA`) |
| 24 | Which header modes ship in v1 (was §OPEN A) | §GRILLED 2026-09-03 | table-only | ONE wire — the fixed string. Another-field mode KILLED (not deferred, no wire reserved); field-label is an edit-time authoring affordance, not a mode |
| 25 | Header-only column + `<thead>` gate | §GRILLED 2026-09-03 | table-only | A column with a header and no chain is valid and renders empty `<td>`s; `<thead>` iff ANY column carries a header, blanks emit `<th></th>` |
| 26 | Field-label authoring affordance | §GRILLED 2026-09-03, presentation REVISED 2026-10-19 (A9/A10 note) | table-only | Click-to-apply, not a placeholder; agreement rule and "render nothing" cases inherited from the note. Now a lone action link under the field picker, shown only while the header differs from the label |
| 27 | Header CONTROL scope (was §OPEN B, dissolved) | §GRILLED 2026-09-03 | cross-container (the token; the control is table-only) | Control registers on `{{table}}` only, token stays shared. B dissolves — with one mode there is no header-mode key to strip |
| 28 | Presentation shape (was §OPEN C) | §GRILLED 2026-09-03 second pass C1 | table-only | **SUPERSEDED 2026-10-15 by #61 + #62** (§DECIDED 2026-10-15, presentation). Was: **Fork B**, bare-key boolean flags, no `style` key. Now valued keys only, one per property, no bare flags |
| 29 | v1 presets + CSS queue | §GRILLED 2026-09-03 second pass C2/C3 | table-only | **Presets part SUPERSEDED 2026-10-15 by #62** (§DECIDED 2026-10-15, presentation): no presets; `stripe` and `layout` are valued keys; `bordered` is FW-159 (`border`), `compact` is FW-161 (cell padding). **CSS-queue part stands:** CSS queues whenever a styled table renders (caption gate dropped), the static rules plus the per-table built rules |
| 30 | Stripe color validation | §GRILLED 2026-09-03 second pass C4 | table-only | STRICT ALLOWLIST (hex/rgb/rgba/hsl/hsla/`var()`/named, plus `currentColor` since 2026-10-15, #62), non-match falls back to default. Author text reaches a front-end `<style>` block; a denylist is not acceptable there. Applies to every color argument |
| 31 | Stripe selector + class prefix | §GRILLED 2026-09-03 second pass C5/C6 | table-only | **Selector part SUPERSEDED 2026-10-15 by #62** (§DECIDED 2026-10-15, presentation): parity is an author argument, the header row counts as row 1, default `even`. **Prefix part stands:** classes `bws-dte-table-*` (now a per-table `bws-dte-table-<hash>` scope), var `--bws-dte-table-stripe`; the front-end namespace is empty today |
| 32 | Display-group chrome (was §OPEN G, dissolved) | §GRILLED 2026-09-03 second pass G | table-only | Real grouping ships (`_group`/`_group_lead` + `option-group.js`); map is PHP-derived, ours-only, and `control-order-test.php` §1 already covers `{{table}}` |
| 33 | Bare-column root (was §OPEN H) | §GRILLED 2026-09-03 second pass H | cross-container (new root slug + a vocabulary split reaching join/try_) | **`src(inherit)`** = the parent's RESOLVED source, true whether or not the parent fans. `same` keeps its slug, re-described as keeping a previous SIBLING slot's selection; five help strings and the sibling-axis identifiers move in the same pass |
| 34 | Per-column control UI (was §OPEN I) | §GRILLED 2026-09-03 third pass I | table-only | Items (1) and (4) already settled by shipped code. Column field picker scopes off the chain's RESOLVED KIND, unfiltered when render-time. No accordion in v1 — density is the shared repeater's problem, not table's |
| 35 | Matrix axis for D2a (was §OPEN J) | §GRILLED 2026-09-03 third pass J | table-only | Rows mirror `bws_read_resolved_source()`'s branches, so a missing row reads as a missing branch. Rows themselves land with the code, under the visible-row + reseed + baseline mandate |
| 36 | FW-74 tracker re-cut (was §OPEN K) | §GRILLED 2026-09-03 third pass K | cross-cutting (tracker only) | Mechanical: FW-74 ships FIRST and is not gated on `{{table}}`; Blocked-by / Interacts-with re-cut to say so |
| 37 | Q7 analog dispatch — D2a's unchecked check, RUN | §GRILLED 2026-09-03 fourth pass | table-only (the check); cross-container (the seam it needs) | Q7 does NOT dissolve — `bws_build_slot_read_options()` is a picker, not a dispatcher. A column's keyed read routes to `bws_read_resolved_source()`, its analog read to a seam that did not exist |
| 38 | Analog columns in v1 | §GRILLED 2026-09-03 fourth pass; reach corrected §GRILLED 2026-09-04 | **table-only** (the reach claim is withdrawn: FW-74 does NOT reuse it) | YES — v1 supports analogs on term/user/site/post rows. Cost is ONE dispatcher (~30-40 lines) over analog readers that are already id-callable; no extraction from behind the ambient gate |
| 39 | FW-74's read seam | §GRILLED 2026-09-04 | cross-container | `bws_read_resolved_source()` DECOMPOSES — a `mixed` sibling owns the kind switch, the string one becomes the coercion; image's two-pass migrates in. NOT a duplicate switch |
| 40 | Datetime on a repeater row | §GRILLED 2026-09-04 | cross-container | Value read only (datetime reads scalars). The FIELD-OBJECT read is FW-3's; a wire row ships format-agnostic until FW-3 closes |
| 41 | Wire-row provenance | §GRILLED 2026-09-04 | cross-container | `bws_pipeline_rows_to_sources()` records `parent_kind`/`parent_id`/`repeater`/`index` — free at the producer, expensive to widen later |
| 42 | The analog dispatcher's owner | §GRILLED 2026-09-04 | table-only | Stays FW-53's build item. Promotes only when a SECOND reader dispatches from a resolved source, which is FW-120 |
| 43 | `inherit` ships with `{{table}}` | §GRILLED 2026-09-04 | cross-container (the token) | Table lands it, plus the root-grammar reservation in `SourceRegistry::is_selectable_root()`. `{{join}}` consumes it later (FW-120) |
| 44 | `same` at position 1 | §GRILLED 2026-09-04 | cross-container | **SUPERSEDED for table by #54** (expands to empty = the row, same intent in the stripped spelling). Original: Stays illegal as a sibling backreference, but compaction REWRITES it to `inherit` instead of stripping it — a strictly better repair than today's. Unblocked by #45 landing; built at `.scratch/table-tag/issues/02-the-inherit-root-token.md` |
| 45 | Sibling-rename timing | §GRILLED 2026-09-04 | cross-container | **LANDED — `d6b29ec`, 2026-09-04, shipped v1.19.2/v1.20.0.** Shipped exactly as decided: standalone and first, identifiers took the `same` stem (`sameOnEmpty`, `srcRowsWithSame`, `srcRows`), prose became "carries over". Zero `inherit`-stem identifiers remain, so the slug is FREE for #43. §GRILLED 2026-09-04's census paragraph still describes this as ~140 sites of pending work and is STALE |
| 46 | `parent` is RESERVED | §GRILLED 2026-09-04 | cross-container (naming) | Not available as a root slug — reserved for the real hierarchical relation (parent post / parent term), intended later |
| 47 | Layout flag name | §C2 (2026-09-29 amendment) | cross-container (naming) | **CLOSED 2026-10-15 by #62** (§DECIDED 2026-10-15, presentation): the valued key `layout`, values `auto` and `fixed`, absent = theme. `fixedLayout` and `fixedCols` are retired; FW-141's `fixed` key stays free because `fixed` here is a value |
| 48 | Fixed-text columns in v1 | user, 2026-10-03 (spec revision) | table-only | Yes. Columns pass `base_fixed`; the build design is `.scratch/table-tag/spec.md` §"A column offers the fixed read" |
| 49 | Strip table's default root | §GRILLED 2026-10-03 (The wire) | table-only | STRIP at every ordinal — an empty column chain is the row. Reverses the spec's 2026-09-18 "a bare column serializes its root" |
| 50 | Column `src` carry-over | §GRILLED 2026-10-03 (The control) | table-only (join waits on FW-120, `try_` decided separately) | COPY-ON-SELECT, never `same`; reselecting resets. Supersedes #15's live `same` |
| 51 | `use(same)` in table v1 | §GRILLED 2026-10-03 (The read axis) | table-only | Not offered; hand-written round-trips. Revisit with per-slot handlers |
| 52 | `inherit`'s role | §GRILLED 2026-10-03 (The wire) | table-only | Table's `defaultRoot`; never written root-only |
| 53 | New column's initial chain | §GRILLED 2026-10-03 (The control) | table-only | EMPTY (the row), not a copy; `sameOnEmpty` off |
| 54 | Hand-written `src(same)` | §GRILLED 2026-10-03 (The control) | table-only | Displayed expanded, written expanded on next edit; at column 1 expands to empty. Supersedes #44 for table |
| 55 | Path roots | §GRILLED 2026-10-03 (The wire) | table-only | Written explicitly when steps follow: `src(inherit;refs,x)` / `src(current;refs,x)` |
| 56 | Copy entry: placement + label | §GRILLED 2026-10-03 (The control) | table-only | Not on column 1; hidden when previous chain empty; "Copy Previous Column's Source" |
| 57 | Root-menu step group | §GRILLED 2026-10-03 (The control) | table-only | One group, writes `inherit;<step>`. Its label is superseded by #64 |
| 58 | Hand-written step-first chain | §GRILLED 2026-10-03 (The wire) | table-only | Reads as the row; rewritten to `inherit;…` on next edit |
| 59 | Ticket 00's preview toggle | §GRILLED 2026-10-03 (Consequences) | tooling only | Retired; tool shows the decided shape only |
| 60 | A slot's type token leads | §DECIDED 2026-10-14 (A type token leads the slot) | cross-container (slot token order; type tokens exist in the agnostic containers, `{{join}}` and `{{table}}`) | When a type token is present it is FIRST, ahead of everything, `header(…)` included. Where there is none (every v1 table column) `header(…)` still leads, so the label-first leaning stands there. Refines the 2026-10-05 column-token-order leaning |
| 61 | Styling-key encoding: text and alignment | §DECIDED 2026-10-14 (styling encodings) | table-only | Plain Merged: one key per property, value `base;header,x`. v1 keys `textAlign`, `color` (no opacity arg), `fontSize`, `fontWeight`, `fontStyle`, `textTransform`; background deferred. Column `textAlign` covers the header cell, color and font do not. Serializes in control order. #62 and #63 close the rest of the #28 reopen |
| 62 | Presentation wire and rendering | §DECIDED 2026-10-15 (presentation) | table-only | Keys `layout` (three states, `fixed` implies `width:100%`), `width` (`auto`, six units, `var()`), `stripe` (header is row 1, default `even`, default color `currentColor` at 5% held in two settings), column `width(…)`. One per-table hashed scope class, everything in that stylesheet, `color-mix` for stripes; front end via the queue, editor via an inline `<style>`. `border`, `background` deferred |
| 63 | Presentation editor controls | §DECIDED 2026-10-15 (presentation) | table-only | Three composites (Sizing + Width, Typography, Stripes) plus a display-only preview in one option group; combobox inputs with one hint row; natural default units; approved advisory copy; Live Formatting Preview is its own ticket in the same release |
| 64 | Root-menu labels: `inherit` and the step groups | §DECIDED 2026-10-15 (root-menu labels) | cross-container (the shipped base/slot menu, `{{join}}` when it adopts `inherit`) | `inherit` reads "Tag Source"; step groups read "Starting from <lead>": "Starting from Tag Source" (table), "Starting from Current Context" (shipped, renamed). Slot noun "Column" is a draft |
| 65 | Borders: `border` and `borderRadius` | §DECIDED 2026-10-19 (borders), **REOPENED 2026-10-20** → §REOPENED 2026-10-20 (borders) | table-only | **REOPENED, wire and rendering rewritten, prototype tuning pending.** Heads `outer`, `rows`, `cols`, `header` and the tag-level scope stand; the "key owns every cell edge", the built-in `1px solid currentColor` at 20% default and the forced separate model are replaced: the key touches only the lines it names, color and opacity are set once in a head-less base, `borderRadius:outer,8px`. Ticket 10 (spike gates the landing) |

## §OPEN index

Undecided. A row here is a real fork, not a to-do — resolving it changes what gets built, not just when.

**Letters are identifiers and are never reused.** A and B closed 2026-09-03 (§GRILLED 2026-09-03 → Settled #24-#27); C, G and H the same day (second pass → Settled #28-#33); I, J and K the same day (third pass → Settled #34-#36). The sequence resumes at the next unused letter.

**Only D, E and F remain, and all three are already-decided POST-V1 rows** — `orientation`'s shape, the `scope="col"`/`scope="row"` pair, and the responsive-overflow JS enhancer. L (opened 2026-09-23) is gated on FW-27. Nothing in this table blocks v1.

| # | Question | Section | Reach |
|---|---|---|---|
| D | `orientation` (row-headers / transpose) — name reserved, shape TBD, post-v1. Tracked as FW-165 | §pre/post-v1 uncertain | table-only |
| E | `scope="col"`/`scope="row"` header pair — deferred until row headers exist. Row headers tracked as FW-164 | §pre/post-v1 uncertain (a11y sub-section) | table-only |
| F | Responsive overflow JS enhancer — deferred, base-markup-only for v1 | §pre/post-v1 uncertain (a11y sub-section) | table-only |
| L | FW-27 `if` on `{{table}}`: build both TAG-level `if` (filters the rows, a failing row is dropped) and COLUMN `if` (tests the row, a fail empties that cell), or column only? Settled in the FW-27 grill 2026-09-23: `if` filters the sources of the chain it is stated beside, which fixes what each MEANS; whether table builds both is this row. Opened 2026-09-23 (user undecided). Not a v1 blocker, gated on FW-27 | `.scratch/plans/if-option.md` §Grill G2 | cross-container |


**⚠ REOPENED 2026-08-30 — the §SETTLED index above is the fast path; [§REOPENED](#reopened-2026-08-30--folded-only-and-the-src-chain-is-the-row-producer) at the end of this file is the section it points into.** Everything above §REOPENED was written against a world where the chain wire did not exist — the index rows marked "cross-container" or superseding an original Locked decision are where that shows.

**Status:** FEASIBILITY/PROTOTYPE BUILT + eyeball-confirmed on the testbed (2026-07-24, branch
`feat/table-tag`, committed `fb29a27`, version 1.17.0 unreleased). Architecture GRILLED + LOCKED +
feasibility-proven same day. **This is NOT v1 — it is the prototype that proves the reframe.** The
§Deferred items below were held OUT of the prototype to get to a working render fast; **most are v1
scope**, not post-v1. v1 = prototype + those held items (except the ones explicitly marked post-v1 /
separate-tag). Remaining before the prototype can even ship as a checkpoint: docs (matrix /
tag-reference / README), CHANGELOG finalize, PR to main.

**Origin:** user concept 2026-07-23 — "build tables or lists, especially definition lists, from a
list of fields, similar to join but with structured output." Split from the shared discovery handoff
(`.scratch/plans/structured-output-tags-handoff.md`, §1-§10) 2026-07-24: that file remains the
detail home for the BLOCK LEVERS (L1/L2m/L3), the SENTINEL route, `{{list}}`, and header-mode
questions — all still-open routes with their §7 gates intact. THIS file owns the tag route (T) for
`{{table}}` only.

**Scope boundary:** the block levers, sentinel, and `{{list}}` are NOT in this plan. The grill
covered the TAG route only. See the handoff for those.

---

## The reframe that unlocked it

Repeater-table is **NOT a new resolution level** ("repeater-as-scope", handoff §1). It is the
existing traversal fold (`bws_run_traversal`/`bws_run_step`, `traversal-pipeline.php`) plus **ONE new
producer step**. The pipeline ALREADY models a repeater row as `meta_row` — a first-class
traversable resolved-source kind (`traversal-pipeline.php:22`) with a working `ref` reader arm. What
was missing was only a STEP that PRODUCES `meta_row[]` from a repeater field. That step is
structurally identical to `srcTermIn` (`post → term[]`); repeater is `entity → meta_row[]`.

**The "new READ PRIMITIVE" the handoff §2 feared = one coercer + one reader arm. Not new
architecture.** Convergence onto the fold, not a bolt-on.

## Author config model → pipeline mapping

| Author sets | Pipeline layer | Status |
|---|---|---|
| src (where the repeater lives) | L1 base source (`bws_resolve_base_source`) | EXISTS, unchanged |
| repeater field to loop | **`rows` fold step** (`entity → meta_row[]`) | BUILT (1.17.0) |
| individual fields → cells | per-column mini-traversal off each `meta_row` | engine + `meta_row` reader EXIST |
| header cell config | tag-side L3 (split from fold) | ONE wire, the fixed string (Settled #24); the prototype's `{N}-label` is scaffolding, the v1 wire is the folded `header` token |

---

## Locked decisions

**Column of note:** "BUILT" = in the prototype (`fb29a27`). "v1 held" = a locked v1 decision held
OUT of the prototype for speed — still v1, still to build. "post-v1" = genuinely later. Full source
support etc. are DECIDED for v1 even where the prototype only exercised part of them.

1. **`rows` = new fold step**, reuses `bws_run_step` fan-out + existing `meta_row` kind. `case
   'rows':` (`traversal-pipeline.php`) + coercer `bws_pipeline_rows_to_sources` (mirrors
   `bws_pipeline_terms_to_sources`).
2. **Full source support = v1** — valid off post/term/user/**site** (ACF repeaters attach to all incl.
   options). site is TERMINAL for ref/srcTermIn but ALLOWED for `rows` (options repeater). Authoring
   trap (repeater-not-on-context) = author error, degrades to empty, unpreventable. (Engine BUILT for
   all kinds; prototype eyeballed post only — term/user/site paths need v1 fixture coverage.)
3. **Reader = ACF `get_field($repeater, $selector)`** (returns array-of-row-arrays = `meta_row`
   shape), kind→selector switch (`post_id`/`term_`+id/`user_`+id/`'option'`), raw-meta fallback for
   non-ACF (`get_*_meta`). **NOT `have_rows`/`the_row`** — stateful global-pointer cursor breaks the
   pure fold; its only unique capability (ambient-current-row) already covered by a passive repeater-row read
   `meta_row`. No source REQUIRES `have_rows`.
4. **Fold stays dumb** — empty-repeater short-circuits (`bws_run_traversal`); ALL presentation
   (empty-render policy, table-vs-list) is tag-side L3 (`bws_table_assemble`).
5. **Headers split OUT of the fold** — computed tag-side (per-column, not per-row = wrong axis for
   the fold). ~~Modes: fixed string (no read) | another field (base-source L1/L2 read) |
   **field-label** (schema read `acf_get_field(...).label`). Fixed-string + another-field = v1;
   label-mode is the one genuinely-undecided mode, so it is the only header piece that may slip past
   v1.~~ **The three-mode framing is SUPERSEDED by §GRILLED 2026-09-03 (Settled #24): there is ONE
   header wire, the fixed string.** The split itself stands and is what made the supersession cheap —
   quarantining the modes is why removing two of them touched no pipeline code. `{N}-label` is the
   prototype spelling; the v1 wire is the folded `header` token (Settled #9, provisional).
6. **Column = per-column mini-traversal (option B)** — NOT a flat field name. Each column carries its
   own step-list rooted at the row. v1 exposes {bare scalar read | `ref`-hop→`use:title` analog,
   limit-1}; both BUILT in the prototype (`bws_table_read_cell`). `use:title` REQUIRES a `ref` step
   first (a ref sub-field is a post id, needs the hop before the title analog — user caught this).
   Widens post-v1 by exposing more fold, not special cases.
7. **Column-src = restricted I4-filtered selector** (row-context scoped: `bare | ref`) — **v1**. The
   prototype keeps the column control set minimal (`{N}-use`/`{N}-key`/`{N}-label`) with the column-src
   IMPLICIT; the explicit I4 `bws_filter_*_from_src` column-src selector + per-column-src
   field-discovery scoping is a v1 held item (built with the discovery integration), not post-v1.
8. ~~**Wire = numbered-prefix** (join idiom) but **ALL columns `1-`-prefixed from N=1**~~ —
   **⚠ OBSOLETED by the FW-57 slot-payload fold (wire spec APPROVED 2026-07-31).** The decision
   existed to dodge a name collision: the tag has a src BEFORE columns exist, so column-1 could not
   inherit bare the way join slot-1 does, and uniform `1-` prefixing was option (i) for keeping
   tag-level and column-level keys apart. **The fold removes the collision at its root** — a column's
   whole chain+read rides inside ONE `{N}:` value, so column tokens (`src`/`use`/`key`) live in a
   different namespace from tag-level bare keys and cannot collide by construction. Nothing needs
   uniform prefixing to stay distinguishable.
   - **Table is greenfield** (nothing shipped), so it adopts the fold's numbering directly rather
     than migrating: `{N}:` per column, `N` from 1, no `1-`-prefix rule.
   - The **bare-`key` collision** this decision was defending against is analysed in
     §P4 FINDING 2 of [`src-chain-encoding.md`](src-chain-encoding.md) — the fold both caused a
     variant of it (row-scoped repeater name vs slot field) and cures it, via an explicit repeater
     name prop rather than state-smuggling. That prop seam is the live carry-in, not this decision.
   - **(ii) retrofit join/try_ to uniform prefixing = DROPPED, not deferred.** It was contingent on
     (i) being real; with the collision gone there is no retrofit to do. Deleted from Post-v1.
   - Slot-cap: `BWS_TABLE_MAX_COLS`, its own const — did NOT carry `BWS_JOIN_MAX_SLOTS` by name,
     join-specific. ~~=6, provisional; revisit with the option-name pass.~~ **VALUE SETTLED 2026-09-03
     at 10 (§GRILLED A12, Settled #23)** — same number as join, independently judged, and the const's
     own docblock owns the driver. The independence this bullet asserts stands; only the number moved.
   - ~~**Composite controls to strip inferrable options** (e.g. don't serialize a header-mode key
     beside the header value when mode is inferrable) — precedent `bws-as-size`.~~ **DISSOLVED by
     §GRILLED 2026-09-03 (Settled #27)** — with one header mode there is no mode key to strip, so this
     item has no referent. Not deferred. It returns only if the reserved `headerKey` (Settled #24's
     same-source variant) is ever built, and on new terms then.
9. **Output = atomic `<table>` string** (route T structural guarantee), hosted in a GB
   **`text`-as-`div`** block (precedent: `{{content}}`). **No enum patching** (sidesteps all §4/§4a
   foster-parenting/appender hazards — markup is a string, not editor blocks). Modal `description`
   warns: don't insert in `p`/`span`. wpautop/texturize on output verified clean at build.
10. **Cell content = scalar sub-fields + the ref→title case** (#6) for v1. Non-scalar (nested
    repeater, image, relationship-beyond-title) = coerced-empty / post-v1. A cell IS a scalar slot (I7
    attribute-destination shape) — scalar-only is a natural boundary, not a cripple. (Scalar + ref→title
    BUILT in the prototype.)
11. **Tag-level author option = `key`** (renames the provisional `rows`). `key` is the house word for
    "the primary field this tag targets" (`{{text key:...}}`); the table just targets a REPEATER
    instead of a scalar. Column sub-fields stay `{N}-key`; the `{N}-` prefix disambiguates level, same
    as every other column option. The FOLD STEP stays internally `rows` (`array('type'=>'rows',...)`) —
    engine-internal, correctly names the OUTPUT (row array); only the AUTHOR key renames. The
    `key`→`rows`-step mapping is table-ONLY — no other tag has a `rows` step, so no cross-tag leak;
    `bws_register_table_tag`/`bws_table_callback` own the mapping. **Rejected:** `repeater` (leaks ACF;
    the raw-meta fallback breaks the word), `field` (too generic — every option targets a field),
    `from` (collides with the `from:type,key` combined-control token), `repeaterKey` (long, still
    ACF-flavored). Pre-rename check: confirm no reader special-cases a bare `key` at tag level today
    (columns are `{N}-key`; bare `key` currently unused). v1 held (rides #8 option-name pass).
12. **`{N}-key` field-discovery = auto-scoped, filter controls HIDDEN.** The column sub-field pickers
    do NOT render the two "Filter fields by location / by type" `SelectControl`s (the generic
    `bws-field-combo` chrome). Instead the picker results are pre-filtered to the sub-fields of the
    repeater selected in the tag-level `key`, invisibly. Free-text entry stays allowed (manual sub-field
    name for non-ACF / undiscovered keys). Mechanism:
    - The discovery envelope ALREADY carries repeater sub-fields, stamped `context_hint => 'row'`
      (`field-discovery.php` repeater/flex recursion). That is the row-scope handle.
    - GAP: sub-field records carry `parent_path` (label breadcrumb, e.g. "Team Members") but NO
      machine-readable owning-repeater KEY. To scope to ONE specific repeater (not all row-context
      fields), stamp each sub-field record with its owning repeater's resolution key (`team_members`).
      Label-breadcrumb matching is fragile — add the key.
    - PHP `{N}-key` option def gains a scope flag (e.g. `'scope' => 'row'`), passed through
      `fieldComboFilter` → `FieldComboControl` as a prop (same wire as `dynamicLabel`/`labelPrefix`).
    - JS: when the scope prop is set, read the tag-level `key` from `state` (sibling-token read, same
      pattern as `presetKind`), find the matching repeater record, filter `records` to that repeater's
      `context_hint:'row'` sub-fields, and SUPPRESS the two-`SelectControl` Flex block. Reactive: change
      the repeater → column pickers rescope (tag-level `key` is already in the `state` flow).
    - Empty tag-level `key` (no repeater picked) → free-text-only mode (or an empty-state hint); manual
      typing still works. Non-ACF meta repeater (no discovered sub-fields) → free-text-only.
    - Tag-level `key` picker itself is scoped the OTHER way: to repeater-TYPE entries (`'repeater'===
      $type` is already known in discovery); free-text still allowed. Its filter controls MAY stay
      (author picking among repeaters) — decide with the build; the hard requirement is hiding them on
      `{N}-key`.
    v1 held (the "Field-discovery integration" Build item; couples with #7 column-src scoping + #8
    option names — do in one pass).

    **FW-13/FW-14 conflict avoidance (adjacent turf; do NOT stall on it, do NOT fork it).** #12 touches
    three field-selector seams. Build the THINNEST additive layer that FW-14 absorbs rather than
    rewrites:
    - **Scope handle = a NEW machine field, not the breadcrumb (rides FU-1, doesn't fight it).** Stamp
      each sub-field record with `repeater_key` (owning repeater's resolution key) at discovery time —
      additive, alongside `context_hint:'row'`. Table scopes by `rec.repeater_key === <tag-level key>`,
      NEVER by parsing `parent_path`. This sidesteps FU-1's `' › '` forgery + locale fragility entirely
      and lands exactly FU-1's "structured, not parsed-string" shape → FU-1 inherits it as prior art,
      no rework. **Load-bearing move.**
    - **Sibling-read = reuse the existing `presetKind` pattern, NOT FU-3's channel.** Reading tag-level
      `key` from `state` by slot-prefix is how `presetKind` already reads `src`/`srcTermIn`. NO new
      cross-control state channel (that is FU-3's heavy lift). Table ships without it; migrate onto
      FU-3's shared store later only if it generalizes.
    - **Filter-hide = local render conditional, NOT the shared-set machinery.** `if (scopeToRepeater)`
      skip the two-`SelectControl` Flex for THIS control. Per-instance; distinct from FU-3's
      render-the-pair-once-for-N problem. Doesn't block FU-3.
    - **Prop = accept +1 flip now, note it for FU-2.** `'scope' => 'row'` on `{N}-key` directly (ONE
      table option, not FU-2's 14). Flag it as a signature input on the FW-14 FU-2 row so the eventual
      `bws_field_key_option()` factory folds it in.
    Net: every piece additive + on-model for FW-14 (FU-1 inherits the structured handle, FU-3 inherits a
    proven 2nd instance, FU-2 inherits one prop). Zero rework debt; no stall.

---

## What shipped in the prototype (`fb29a27`)

- **Engine** (`includes/helpers/traversal-pipeline.php`): `rows` case in `bws_run_step` (accepts
  post/term/user/meta_row/site), `bws_pipeline_rows_to_sources` coercer, `rows` arm in
  `bws_pipeline_default_reader` (kind→selector `get_field` switch + `get_*_meta` fallback).
- **Tag** (`includes/tags/table-tags.php`): `bws_register_table_tag`, `bws_get_table_options`
  (tag-level src/ref/srcTermIn/`key` [was `rows`, renamed #11] + N `{N}-` columns from N=1), `bws_table_collect_columns`,
  `bws_table_read_cell`, `bws_table_assemble`, `bws_table_callback`. `BWS_TABLE_MAX_COLS=6`.
- **Loader** (`bws-gb-dynamic-tags-extensions.php`): require + `bws_register_table_tag()`.
- **Tests**: 21 `rows` cases in `tools/test/traversal-pipeline-test.php` — 127/127.
- **Fixtures** (`tools/fixtures/core-structures/`): `team_members` repeater on `page-matrix-post-meta`
  (manifest), `lead_ref` relationship sub-field (schema) + per-row slug→ID resolver (seed), visible
  TB1-TB3 rows via new `bws_fixture_gb_block_host_row` text-as-div helper (blocks). Eyeball-confirmed:
  TB1 scalar+headers, TB2 header-less, TB3 ref-title col (Jane Partner / empty on blank ref).
- **Version**: 1.16.0 → 1.17.0 (header + const). CHANGELOG 1.17.0 `— unreleased` Added entry.

## Feasibility record

Spike (scratchpad throwaway) proved 19/19 against the REAL fold engine before the wire-in: coercer,
input gate, bare column, ref column via real `bws_run_traversal`, 3×3 assembly, missing-subfield →
empty cell. Port landed as the 21 harness cases above.

---

## Roadmap — three phases

The prototype (`fb29a27`) was a working-render checkpoint, NOT v1. **Ship plan (user, 2026-07-24):
NOT shipping the prototype checkpoint alone — v1 ships the whole held-item set, with the docs/ship
steps as the FINAL phase.** The phases are an ORDER, not parallel buckets (phase names, not numbers —
they never carried meaning beyond sequence):

1. **Build (v1)** — the held items (build these; some already BUILT-unpushed — see status).
2. **Ship phase (last)** — docs + CHANGELOG finalize + PR, done once Build is complete.
3. **Post-v1** — genuinely later / separate.

Only Post-v1 is genuinely deferred past v1.

### Build (v1) — held items (the build work; ships as v1)

**Remaining to build** (as of 2026-08-04): #2 full-source fixtures, #7 column-src selector, #8
column option names + slot-cap value, #8 folded column wire (registration is still flat — see the
held item below). **Already BUILT-unpushed:** #11 (`key` rename), #12
(field-discovery integration), the a11y pass (caption + wrapper + CSS, committed `6f5a0ff`). The
`pre/post-v1 uncertain` sub-list below is per-item candidates, NOT locked v1.

- **Full-source fixture coverage** (#2) — term/user/site repeater rows on the testbed; engine already
  accepts them, prototype only eyeballed post.
- **Column-src explicit selector** (#7) — I4-filtered `bare|ref` column-src control + per-column-src
  field-discovery scoping. Prototype left column-src implicit.
- **Final option names** (#8, #11) — author-terms per nomenclature discipline. **#11 BUILT** (post-
  prototype, unpushed): tag-level `rows` → **`key`** (option key + `$rows_field` read in
  `bws_table_callback` + PHPDoc; internal fold step stays named `rows`; `key`→`rows`-step map is
  TABLE-ONLY, owned in table-tags.php; pre-rename check confirmed no generic reader special-cases bare
  `key` — `bws_wrapper_ref_steps`/`bws_resolve_base_source` read only src/ref/srcTermIn/tax/id).
  `{N}-label`/`{N}-use`/`{N}-key` still provisional placeholders (#8 open).
- **Slot-cap const value** (#8) — `BWS_TABLE_MAX_COLS=6` provisional; revisit with the option-name
  pass.
- **Field-discovery integration** (#12) — **BUILT** (post-prototype, unpushed), per the conflict-
  avoidance plan above:
    - `field-discovery.php`: additive `repeater_key` stamp on every row-context sub-field record
      (repeater branch → repeater's resolution key; flex branch → flex field's key; nested repeater
      guarded so grandchildren keep the INNER key). Record shape docblock + `@return` updated. +5
      harness cases (`field-discovery-test.php`, now 70/70).
    - `table-tags.php`: `{N}-key` options gain `'scope' => 'row'`.
    - `field-combo-control.js`: `repeaterKeys[]` accumulated onto merged records from the server stamp;
      `scope` prop wired through `fieldComboFilter`; when `scope==='row'` the control reads the bare
      tag-level `state.key`, filters records to `rec.repeaterKeys.includes(key)`, and HIDES the two
      filter `SelectControl`s. Empty/unknown repeater key → falls through to the full pool (never
      strands the author); free-text always commits. Per-instance render conditional, NOT the FU-3
      channel. Reactive: change the repeater → columns rescope (tag-level `key` is in the state flow).
    - **Tag-level `key` picker scoped to repeater-TYPE entries** (the OTHER-way scope) — **BUILT**
      (`abfe7c7`): additive `typeDefault` prop on the tag-level `key` option (`'typeDefault' =>
      'repeater'`), wired through `fieldComboFilter` like `scope`/`dynamicLabel`. Seeds the type-filter
      state via the same override-with-preset pattern as the location filter (`typeOverride` /
      `typeExists` guard mirrors `locOverride` / `presetExists`); undiscovered type → falls to All (no
      empty list). Filter controls STAY visible (a starting view, not a lock — author can widen / pick a
      plain-meta key). Reuses existing `typeVal` + `buildTypeOptions`; no FU-3 channel.
    - STILL OPEN: column-src `bare|ref` scoping (#7) still implicit.
- **Src-chain row model + the cell reader** (v1 scope, reframed 2026-07-26 — supersedes the 2026-07-25
  flat/repeater MODE framing). Row-count = src-chain cardinality (chain returns N → N rows; returns 1 →
  1-row table); NO mode control. See §Row-count is SRC-CHAIN CARDINALITY. Build items:
    - **⚠ BLOCKED-BY the multi-step src-selection encoding model.** Promoting repeater to an author-facing
      chain step token (parallel to `srcTermIn`) needs the chain's serialization + editor-surface model,
      which is UNSOLVED and cross-cutting (not table-only). Table's authoring surface can't be finalized
      until that lands. See the disentangle note + FW-32/traversal work. Re-plan captured; build gated.
    - **Consume the chain return, don't inject a terminal step** — remove the hardcoded `rows` step
      (table-tags.php:424); rows = `bws_run_traversal` return. (Code untouched this pass — fix target only.)
    - **Cell reader (Q1/Q6/Q7/Q9/Q10)** — the whole table-side read layer now. Branches on row `kind`
      (`meta_row`/`post`/`term`): explicit `post` branch (`bws_read_field`, not raw `get_post_meta`);
      `{key,title}` enum; row-loop guard widened to `{meta_row,post,term}`; empty-cell contract (`null|''`
      empty, `0` preserved, `use:title` guards `$pid>0`). The FW-32 fan-out consumers REUSE it
      ([`ref-hop-parity.md`](docs/design-history/ref-hop-parity.md) references, does not re-spec). Closes the code-review
      `0`-drop item.
    - **Fixtures** — TB5+ over `page-matrix-post-meta`: a single-result chain (1-row table, proves the
      `post` cell branch) + a repeater chain (N-row, proves `meta_row`) once the chain-token surface exists.
      Visible + self-describing.
- **Adopt the folded slot wire for COLUMNS** (the build item behind Decision #8; not started as of
  2026-08-04). #8 states the target — `{N}:` per column from N=1, no `1-`-prefix rule, greenfield so
  no migration. Registration has not followed it yet: `{{table}}` still registers flat
  `{N}-label` / `{N}-use` / `{N}-key` (`text` / `select` / `bws-field-combo`) and owns **no
  `bws-slot-fold` option at all**. Two things that reads on, both harmless today and both worth
  knowing before the build:
    - `includes/helpers/slot-fold-migrate.php` (docblock on `bws_fold_migration_multislot_tags`)
      already states the outcome in the PRESENT tense — *"`{{table}}` is deliberately absent and
      always will be: it ships folded, so no stored table tag ever carried flat slot keys."* True as
      an intent, not yet true of the code. The **conclusion** holds either way (table is unreleased,
      so nothing is stored and it must stay out of `bws_fold_migration_multislot_tags()`), so this is
      a wording debt to settle when the fold lands, not a migration to write.
    - Table is the only container that carries a **tag-level `key` beside a `{N}-key`** (the repeater
      name vs the column field). Under join/try_'s bare-slot-1 convention a migrator would fold the
      tag-level `key` into column 1 — but that is precisely the collision #8 dissolves by giving
      columns their own namespace, so it needs **no per-container prefix rule and no `tag_level`
      entry**. Recorded here only because the shape looks like the tag-level-`key` hazard
      `TagTemplateRegistry::try_slot_axes()` exists to guard, and is not one.
    - Anchor note for the editor side: the mount migrator anchors on a tag's first `bws-slot-fold`
      option. Table's first option is `src`, so it will not collide with the order normalizer's
      first-option anchor the way `{{join}}` and the six leading-option-less `try_` tags do.
- **Accessibility / W3C data-table standards** — sources: `D:/…/Accessibility/W3C Design
  System - Tables.md` (design-system.w3.org/styles/tables.html) + `Under-Engineered-Responsive-
  Tables.md` (Adrian Roselli — the CSS + attribute-selector recipe the W3C page cites).
  - **BUILT (post-prototype, unpushed):**
    - Tag-level `caption` text option → `<caption id="…">`. `wp_unique_id('bws-table-caption-')`
      supplies the id (per-request counter, no randomness). `bws_table_assemble` gained
      `$caption, $caption_id`; the caller generates the id.
    - **Scroll region is caption-GATED** (decision 2026-07-24, Roselli-driven): the wrapper
      `<div role="region" tabindex="0" aria-labelledby="$caption_id">` is emitted ONLY when a
      caption is set. Rationale: Roselli's `overflow:auto` CSS selector requires the full
      `[role="region"][aria-labelledby][tabindex]` triple, and a focusable region MUST have an
      accessible name (WCAG 4.1.2) — the caption IS that name. No caption → no accessible name →
      no valid focusable region → **bare `<table>`, no wrapper** (no scroll containment, but no
      violation). Supersedes the earlier "wrapper always emitted" prototype note.
    - **NO presentation classes.** Dropped both `bws-table-wrap` and `class="bws-table"` (the §9
      code-review creep). The CSS is attribute-scoped per Roselli
      (`[role="region"][aria-labelledby][tabindex]`), which self-enforces the a11y markup — a
      class hook is redundant.
    - **Frontend CSS SHIPS** (the attribute wrapper is inert without it — `overflow:auto` = WCAG
      1.4.10 Reflow; focus `outline` = 2.4.7 / 1.4.11). Plugin ships NO stylesheet asset; the
      6-line Roselli rule (`const BWS_TABLE_INLINE_CSS`, table-tags.php) is printed once per
      request via a NEW shared helper `bws_queue_inline_css($id,$css)` (content-helpers.php) that
      generalizes the `{{content}}` footer-queue idiom (ContentProcessor::output_queued_inline_css,
      `wp_footer:5`). Queued only when a captioned table actually renders (after the empty-html
      guard, so an all-skipped-rows table leaves no orphan CSS). Idempotent by id.
    - Pure harness `tools/test/table-assemble-test.php` (23 cases: caption-gated wrapper,
      no-class bare table, escaping, no-caption bare path). **§Update-trigger: table family =
      run `table-assemble-test.php`.**
  - **DEFERRED — `scope="col"` / `scope="row"` as a PAIR** (user call 2026-07-24): col scope
    only disambiguates axes once row headers also exist, so ship neither until both. Row headers
    = a per-column "this column is the row header" flag emitting `<th scope="row">` (+ the W3C
    "no empty leading `<th>`" rule). Header `<th>` stay bare for now.
  - **DEFERRED — responsive overflow JS enhancer** (user call: base-markup-only pass). W3C's
    optional script strips `tabindex`/`role`/`aria-labelledby` when the wrapper has no overflow.
    Not shipped: a fitting captioned table over-announces slightly (the fail-safe direction). Add
    later if the over-announce matters.
  - **v-future:** heading element inside `<caption>` (W3C allows `<h2>` in caption); v1 is a
    plain escaped string.

#### Options pass — decisions (2026-07-24)

Notes captured for the #8 option-name pass. Not built yet; these lock the option MODEL + grouping so
the name pass and finalization have a target.

##### Name decisions (user, 2026-07-24) — the #8 pass proper

- **Tag-level rows field — SUPERSEDED by the src-chain-cardinality reframe (2026-07-26).** This entry
  originally specced a tag-level `key` as the ROW-PRODUCER field with a mode-driven dynamic label
  (flat-gate / "Repeater Field" / "Reference Field"). The reframe below (§Row-count is SRC-CHAIN
  CARDINALITY) dissolves the row-production MODE entirely: repeater-selection moves INTO the src chain as
  a step token, so there is no tag-level "producer field" to label by mode. The dynamic-label-by-mode
  branch is DROPPED. Open in #8: whether the repeater chain-token's own picker wants a dynamic label from
  its discovered field kind (the EXISTING `dynamicLabel` location/kind derivation, field-combo-control.js:671
  — not a mode-token read). The tag-level `key` itself is retired-or-reused for table (see the reframe's
  "what this changes"). **All labels PLACEHOLDER** — #8 options pass.
- **Justification = whole-header + per-column body (user call).** ONE tag-level control aligns ALL
  `<th>`; per-column `{N}-align` aligns that column's body `<td>`s. Two axes (header block + column
  body). Mechanism = own-class + queued CSS (`bws-t-al-{start|center|end}`), NOT inline. `{N}-align` is
  a per-slot `format`-group key (rides the slot-major recursion; sorts front-of-column with `{N}-label`).
  The whole-header align control = tag-level `format` (slot 0).
  - **NAME = `align` / label "Align" (user, 2026-07-24)** — over `justify` (GB's vocab but ambiguous
    with full-justified text) and `textAlign` (verbose). Wire: header control **`headerAlign`**,
    per-column **`{N}-align`**; values `start | center | end` (CSS logical, RTL-safe). Header label
    "Header Align", column label "Column N Align".

- **Row-count is SRC-CHAIN CARDINALITY, not a MODE — the whole mode axis DISSOLVES (user reframe,
  2026-07-26).** There is no flat/repeater/relationship "row-production mode" and no mode discriminator
  token. A table runs the author's **src chain** (`bws_run_traversal`) and treats **each returned source
  as one row**. Cardinality falls out of the chain: **returns N → N-row table; returns 1 → 1-row table.**
  "Flat" is not a mode — it is simply a chain that returns one. This supersedes the 2026-07-25 flat+repeater
  mode framing entirely.
  - **The reframe's structural basis (CONFIRMED in code):** the shipped `rows` step (repeater → `meta_row[]`)
    is documented as the "structural twin of `bws_pipeline_terms_to_sources`" (traversal-pipeline.php:227);
    `rows` and `srcTermIn` are sibling `case`s in the same step dispatcher (L128/136, L539/548), both fan-out
    producers. Agents flagged this twin-ness before. So **repeater-selection is properly part of SRC-CHAIN
    selection, NOT a new intermediate table option.** The prototype misframed it by table-INJECTING a terminal
    `rows` step (table-tags.php:424 `array_merge($ref_steps, [['type'=>'rows','field'=>$rows_field]])`) and
    forcing tag-level `key` to mean "a repeater to hop."
  - **DECISION (user, 2026-07-26): repeater becomes a SRC-CHAIN STEP TOKEN**, author-facing, parallel to
    `srcTermIn`/`ref` — promoted from engine-internal (`rows` case) to a registered chain option with its
    own `show_if`-revealed surface (the `srcTermIn` pattern, base-tags.php:71-91 + 874-903). Table appends
    NOTHING; it consumes whatever the chain returns. **Cross-cutting, not table-only:** a promoted repeater
    fan-out step is reusable by any composer (a repeater list could later feed `{{join}}` etc.). Wire
    token name PLACEHOLDER (`rows:<field>` or renamed — #8 pass).
  - **Row `kind` = whatever the chain produces**, and the CELL READER branches on it — `meta_row` (a `rows`
    step), `post` (a `ref` fan-out / plural src), `term` (a `srcTermIn` step). The producer-agnostic cell
    reader (Q1/Q6/Q7/Q9/Q10) is now the WHOLE table-side read layer, not a "flat" rider — see §Cell reader.
    Guard set widens from `{meta_row,post}` to `{meta_row,post,term}` (srcTermIn rows are legit).
  - **What this changes vs the code / prior plan:**
    - Table STOPS injecting the terminal `rows` step (table-tags.php:424) — the fix target. It runs the src
      chain and rows = the return. NB: **re-plan only this pass; code untouched** (user, 2026-07-26).
    - The MODE control (flat/repeater) is GONE — no discriminator token to name in #8.
    - Tag-level `key`-as-repeater-driver is GONE — repeater moves into the chain as a step token; the
      tag-level `key` no longer means "the repeater." (Its fate: likely retired for table, or reused as
      something else — settle in #8 with the chain-token surface.)
  - **FW-32 relationship rows are NOT a special table mode — they are the chain's `ref` step FANNING OUT.**
    Same cell-reader `post` kind. What FW-32 owns is making the `ref` chain token fan out (drop
    collapse-to-first) + its foreign-PT `{N}-key` scope — a general chain fix feeding ALL fan-out consumers,
    table included ([`ref-hop-parity.md`](docs/design-history/ref-hop-parity.md)). QUERY rows = a chain that starts from a
    `src:query` base (`query-source.md`), same consume-the-return model.
  - **v1 reach:** any src chain the ENGINE already fans out feeds a table today — repeater (`rows`, needs the
    author-facing promotion) + `srcTermIn` (term list, already author-facing) + single-result chains (1-row).
    Plural `src:ref` rows wait on the FW-32 fan-out fix. **OPEN for the #8/build pass:** the chain-token
    option surface (promote `rows`), whether v1 ships the srcTermIn-term-row path or holds it, and the
    tag-level `key` retirement/reuse.
- **Per-column `{N}-src` = LOCKED (user, 2026-07-25).** Table columns get a per-column source selector
  `{N}-src`, mirroring try-slot source-completeness (try slots wire `src`/`ref`/`srcTermIn` per slot via
  `bws_build_slot_traversal_options`, base-tags.php:898-903). The prototype gave columns `{N}-use`/`{N}-key`/`{N}-label`
  only — NO source axis (table-tags.php:205-219) — so the cell's source was implicit off the row, which
  FORCED `use:title` to smuggle a source-hop (ref→post→title) into the `use` VALUE. That overload is the
  real root of the relationship-scope tangle (now moved to [`ref-hop-parity.md`](docs/design-history/ref-hop-parity.md)),
  NOT a `use`-vocabulary problem. Locking `{N}-src` dissolves it:
  - **`{N}-use` returns to pure analog-vs-key dispatch** (title/date/email analog vs raw meta key). Stops
    carrying source-hops. One `use` vocab across all row kinds.
  - **`{N}-key` = field name unconditionally** (when a keyed read).
  - **Presence gate flips** from "has `{N}-key`" (table-tags.php:207-211) to "column configured" (src+use
    present). A key-less intrinsic/hopped column (`{N}-src:ref | {N}-use:title`) is now legit-and-present —
    kills the `break`-eats-trailing-columns bug (old L322) and the header-only-column contiguity latent.
  - **Makes the "column = mini-tag" serialization principle (A1, §slot-major) LITERALLY true** — the source
    group is now actually present per column, not half-built.
  - **Orthogonal to the ROW src-chain (which owns row-count/kind).** Per-column `{N}-src` owns CELL-SOURCE
    (row-relative / ref-hop / intrinsic) independent of how rows are produced — exactly the try_ shape. NB
    the per-column `{N}-src` is ITSELF a mini src chain, so it shares the same UNSOLVED multi-step
    src-encoding model the row chain now depends on (see the reframe's ⚠ blocker) — the column-src menu
    shape can't finalize ahead of it.
  - **OPEN (UI bundle, NOT locked here) — the per-column-control UI pass.** Four items, all converging on ONE
    `bws-table-column(s)` composite (GB won't re-parent sibling options or grow the keyspace on click):
    (1) src-menu SHAPE — full try-style menu vs reduced table-specific values (likely `row`/`ref`/`site`, since a
    column reads relative to its ROW not the wide ambient menu); (2) filter slot option-values by row `kind`
    — the column value-set is shared across kinds (the READER branches on row `kind` at render, not the
    picker); a `{N}-key` FOREIGN-scope filter is only needed when a row is a `post`/`term` from a fan-out
    chain (rides FW-32 + the src-encoding model); (3) minimal-default slot view + advanced-options accordion;
    (4) slot-UX — click-to-add repeater vs the shipped `show_if` progressive reveal (try/join `base-tags.php:880-903`).
    **Seam-feasibility research for (2)/(3)/(4) — accordion=PanelBody-no-wire, value-filter=`presetKind` global read,
    repeater=one-composite-only + renumber-on-delete — lives in [`combined-option-controls.md`](combined-option-controls.md)
    §Return-value authority.** All four feasible with shipped mechanisms; none locked.
- **Column cap `BWS_TABLE_MAX_COLS` = 10** (was 6) — **match `BWS_JOIN_MAX_SLOTS = 10`**
  (join-helpers.php:68). Rationale (user): consistency with join, the closest analog (both standalone
  multislot assemblers); try_'s 5 is the weaker parallel. "Might as well be consistent if not raising
  it."
- **Column names KEPT (convention-driven, not asked):** `{N}-key` wire (every tag's field-key option),
  `{N}-use` enum `key`/`title` with labels "Meta/Option Field"/"Related Post Title" (already correct per
  [[feedback_field_nomenclature]]), `{N}-label` wire for header text, `fallback`/`{N}-fallback` wire.
  `{N}-label` group = `format` (header = display), front-of-column under slot-major.
- **NO row-production MODE control — DISSOLVED by the src-chain-cardinality reframe (2026-07-26).** The
  earlier 2026-07-25 "2-value FLAT/REPEATER mode control, ships v1" is RETRACTED. Row-count = src-chain
  cardinality, so there is no flat/repeater/relationship discriminator to serialize or name. What the #8
  pass owns instead is the CHAIN authoring surface (repeater promoted to a chain step token) — and that
  is BLOCKED on the unsolved multi-step src-selection encoding model (see §Row-count is SRC-CHAIN
  CARDINALITY ⚠). No `mode`/`type`/`rows-from` token.

- **Option grouping = ESTABLISH this pass** (user, 2026-07-24) — a prerequisite for finalization, not
  deferred. **TWO DISTINCT grouping axes — don't conflate:**
  - **(i) DISPLAY groups** (config-panel chrome, top-to-bottom sections). Proposed:
    **Source** (src/ref/srcTermIn/`key`) · **Columns** (`{N}-*`) · **Format** (caption, justification,
    header modes) · **Style** (`style`/`fixed`/stripe) · **A11y** (already folded into Format/Source as
    they land). **`caption` lives in the Format display group** (user call). Header modes (#5) +
    justification also land in Format. Wire however the config renderer supports grouping; if it
    doesn't, display-order-only for v1 — check the renderer before committing to real group chrome.
  - **(ii) SERIALIZATION-ORDER groups** (FW-52 `format/source/link/fallback` — the
    registration/control-order-vs-wire-order model in `serialization-order.php` + normalizer.js). This
    is the axis that governs how table options SORT on the wire. See the slot-major decision below.

- **Serialization-order model = SLOT-MAJOR recursion (GRILLED + LOCKED 2026-07-24; A1).** The FW-52
  four-group order (`format → source → link → fallback`) must recurse **inside each `{N}-` column slot**,
  not only at tag level. A table column is a **mini-tag**: its own format/source/link/fallback cluster.
  - **Change:** swap the sort comparator precedence from group-major (`grank → slot → within → idx`) to
    **slot-major (`slot → grank → within → idx`)** in BOTH mirrors (`serialization-order.php` +
    `serialization-order-normalizer.js`), then re-run `php tools/test/serialization-order-test.php`
    (CLAUDE.md trigger — the change touches the canonical sort). Slot 0 = tag-level; each slot N ≥ 1
    then repeats `format → source → link → fallback` internally.
  - **Blast radius = ZERO on shipped tags (grill-verified).** NO current multi-slot tag emits a
    non-`source`-group key with an `N-` prefix: **join** per-slot keys = src/ref/srcTermIn/use/key/limit
    (all source; `mode/valueSep/format` are tag-level slot 0); **all try_\*** per-slot keys =
    src/ref/srcTermIn/use/key (all source; `as`/`format`/`fallback`/`limit`/`sep` are
    `leading_options`/trailing = slot 0). **try_image has NO per-slot `N-as`** (confirmed: `as`/`size`
    are `leading_options`, tag-level; user confirmed 2026-07-24). The `2-as..5-as` loop in
    `bws_migrate_image_as_size_fold` (deprecated-tags.php:1024) is **dead legacy-wire defensiveness** —
    no era registered per-slot image `as`; harmless (guarded), trim opportunistically, not now. So
    slot-major is behavior-identical for every 1.16.0 tag and only activates recursion for table's
    mixed-group columns. The shipped "each `N-` slot contiguous" comment was already true for
    source-only slots; slot-major makes it true for ALL groups.
  - **A1 (locked over A2): pure slot-major, NO fallback-pinned-last exception.** Global tag-level
    `fallback` sorts in slot 0 (lands before column slots); per-column `{N}-fallback` rides **inside its
    own column cluster** (mini-tag self-contained), NOT pooled at the end. Supersedes the old §262
    "fallback once, after all slots" (written for the global-only era; per-column fallback is new).
  - **`{N}-label` group** = format (header cell = display), sorts to the FRONT of its column cluster
    (before `{N}-use`/`{N}-key`); **`{N}-align`** (if per-column) = format too. Each NEW bare name
    (`label`, `align`, `fallback` already mapped, the row src-chain step token(s), caption/style/fixed)
    MUST land in `bws_serialization_order_key_map()` AND the JS `KEY_MAP` mirror with a group + within-rank,
    else it defaults to `source`@100. Any row src-chain token (the promoted repeater step, `srcTermIn`, etc.)
    → source group. NB the ordering of a MULTI-STEP src chain is part of the unsolved src-encoding model
    (see the reframe ⚠) — this within-source ranking may need to become chain-position-aware, not fixed.
  - **Verify after build:** one harness case proving a multi-group slot recurses
    (`['2-label','2-key','1-key','as','fallback','2-fallback']` → `['as','1-key','fallback','2-label','2-key','2-fallback']`);
    plus a regression case proving slot-0-only tags (existing try_/join) are byte-identical to today.

- **Per-column fallback = global + `{N}-fallback` override** (user call) — DON'T drop the tag-level
  global fallback. Model: tag-level `fallback` (empty-cell text for the whole table) PLUS optional
  `{N}-fallback` per column that overrides it. Empty cell → `{N}-fallback ?? fallback ?? ''`. Mirrors
  join's global+slot idiom. Interacts with the code-review item "Cell empty test drops literal `0`"
  (L419) — the fallback only fires on genuine-empty, so settle the `0`-preservation contract FIRST,
  then fallback rides that definition of empty.

- **Justification (text-align) = v1, exact SCOPE TBD** (user, 2026-07-24) — build it in v1; the
  header-vs-column scope split is undecided. Two candidate shapes on the table:
  - whole-header option (aligns ALL `<th>`) + per-column `{N}-align` for that column's body cells
    (user's leaning in the note: "for entire header/all th + per-slot"), OR
  - single `{N}-align` per column aligning both its `<th>` and `<td>`s.
  NO global-everything (align-the-whole-table) option — user explicitly not decided on that, lean
  against. Decide the split with the build. Mechanism = same own-class + queued-CSS path as `style`
  (e.g. `bws-t-al-{start|center|end}` on the cells); NOT inline styles. Supersedes the styling note's
  "align … separate project" deferral for the text-align case (full wide/full/left/right block-align
  is still the deferred richer tier — that's block placement, not cell text-align).

#### pre/post-v1 uncertain

- ~~**Composite header control** (#8)~~ / ~~**Another-field header mode** (#5)~~ / ~~**Header label-mode** (#5)~~ — **ALL THREE CLOSED by §GRILLED 2026-09-03.** One header wire (the fixed string); another-field killed rather than deferred; label handled at edit time by an advisory with click-to-apply, so it is an authoring affordance and never a mode; the composite control dissolves for want of a mode key. Settled #24, #26, #27.
- **Styling options** — LOCKED design, build uncertain (2026-07-24). NOT "pick up WP core styling":
  the tag emits a bare `<table>` in a text-as-div host with no `wp-block-table` figure, so core's
  `is-style-stripes`/border CSS (scoped to `.wp-block-table`, shipped by `wp-block-library`) won't
  reach it, and adopting the figure wrapper both re-introduces core's `<figcaption>` vs our a11y
  `<caption id>` markup and makes styling depend on `wp-block-library` being enqueued on a possibly
  pure-GB page. Rejected. **Path B — own toggles + own minimal CSS** (same self-contained shape as the
  shipped Roselli a11y CSS). NB the §"NO presentation classes" decision was an *a11y-wrapper* call
  (attribute-scoping self-enforces the region markup), NOT a ban on a deliberate styling class — a
  styling class is fine here.
  - **`style` selector** (not a bool) — tag-level option, `type:'select'` (the config already renders
    selects), values `default | stripes`, extensible to future presets (`bordered`, `compact`, …).
    `stripes` → `class="bws-t-stripes"` on `<table>`; `default` → no class. Selector over bool so a 3rd
    preset adds no schema churn; mirrors WP's own Default/Stripes picker with our own set.
  - **`fixed` bool** — separate, orthogonal (layout not visual preset); adds `bws-t-fixed`, combines
    with any `style`.
  - **Stripe color = global setting + CSS-var escape hatch.** New `table` group in
    `bws_dynamic_tags_settings` (class-settings-page.php — mirror the existing group + sanitize +
    typed accessor pattern; own new "Table" settings section, first style-ish setting). Field = plain
    text (NOT `wp_color_picker` — must accept `rgba`/`var`/any CSS color for alpha over cell bg);
    default `rgba(0,0,0,.04)`; sanitize light, fall back to default on junk. Emit
    `background:var(--bws-table-stripe,<GLOBAL>)` so the global is the baked default AND a theme can
    override per-context via the var. (Dark-theme contrast solved by the theme setting the var/global,
    not a `prefers-color-scheme` guess by us.) Per-tag color = a later layer (#2 inline-style path) if
    ever wanted; not v1.
  - **CSS** — extend the queued table CSS:
    `.bws-t-stripes tbody tr:nth-child(even){background:var(--bws-table-stripe,<GLOBAL>)}` +
    `.bws-t-fixed{table-layout:fixed;width:100%}` (the `width:100%` is required or `table-layout:fixed`
    is inert). Because `<GLOBAL>` is a runtime setting, `BWS_TABLE_INLINE_CSS` can no longer be a bare
    `const`: keep the static a11y region rules as the const, build the style rules dynamically in the
    callback, concat at queue.
  - **Queue** — drop the caption-gate on the CSS queue: queue whenever a table renders (2a). The region
    rules sit inert when there's no captioned wrapper (few bytes, harmless).
  - **Deferred richer tiers** — BLOCK align (wide/full/left/right table placement) and full
    color/typography/border need an editor control surface (per-instance inline styles, not classes);
    separate project, not this note. (Cell/header text-align is NOT here — it's v1, see Options pass
    "Justification" above.)

  - **⚠ SETTLED 2026-09-03 (§GRILLED second pass C1-C6, Settled #28-#31): Fork B, flags only, `stripes` + `fixed` for v1.** The survey below stands as the record of what was weighed; the fork it left open is closed, and the `style` key it debates does not ship. Read it as history, not as a live question.
  - **⚠ NAMING + SHAPE DEFERRED to a dedicated presentation-options design pass (user, 2026-07-24).**
    The single-`style`-select design above is NOT settled. The options pass surfaced an unresolved
    FORK: stripes / bordered / compact are **ORTHOGONAL** (an author can want stripes AND bordered
    together) — unlike `as:url,medium` (coupled: size only means anything for url mode). A single-select
    `style` can't combine them. Three candidate shapes, name follows shape:
    - **Fork A — CSV multi-value `style`** (`style:stripes,bordered`): one key, orthogonal presets
      compose CSV-style (the "CSV-type option bundling" the user flagged); editor = checkbox group →
      CSV serialize; composes like the emitted classes (`bws-t-stripes bws-t-bordered`). `fixed` may
      fold in or stay separate.
    - **Fork B — separate presence-flag bools** (`stripes`, `bordered`, `compact`, `fixed` each a
      bare-key bool): most WP-idiomatic (matches the plugin's bool convention — true → BARE key, NOT
      `:1`; false → dropped, tag-reference.md:216). N keys, each self-descriptive on the wire. No
      `style` key. Under this fork the `style`-vs-`fixed` "orthogonal" split (above) dissolves — ALL
      presentation is orthogonal flags.
    - **Fork C — single-select `style`** (the current design): simplest, no per-preset schema churn,
      but CANNOT combine presets.
    The pass must ALSO settle: **borders scope** (dropped as "deferred" but belongs in this survey — a
    v1 preset or later?), which presets are v1 vs post-v1, and `fixed`'s relationship to the set. **NB
    booleans serialize as a BARE KEY, never `:1`** (presence-flag convention) — any bool option name
    here reads as `{{table … fixed}}`. Home for that pass: this section; feeds a `docs/future-work.md`
    row if it slips past v1.
  - **⚠ ORIENTATION — post-v1, but NAME RESERVED + BUNDLING-EXCLUDED now to avoid collision (user,
    2026-07-24).** Header cells as first ROW (column-headers, current + default) vs first COLUMN
    (row-headers). **STRUCTURAL, not cosmetic** — flips the assembled HTML (`<thead><tr>` of `<th
    scope="col">` vs per-row leading `<th scope="row">`), TRANSPOSES the matrix (repeater rows become
    columns → unbounded column count → heavy scroll-region pressure), and re-scopes a11y. Two
    reservations set now so this pass doesn't paint it into a corner:
    - **Name reserved: `orientation`** (Format group). Values TBD at build (`rows|columns` is ambiguous
      which is which — decide then). REJECT `headers:row|column` (collides with `{N}-label` header-text
      concept + `headerAlign`); `layout`/`direction` too vague.
    - **NOT a `style`-CSV-bundle member.** Orientation is structural; `style`/borders/stripes/`fixed` are
      cosmetic skins. Mixing structural + cosmetic in one CSV token (Fork A) muddies serialize + resolve.
      So Fork A's `style:` CSV stays cosmetic-only; orientation always its own Format key regardless of
      which presentation fork wins.
    - **Latent a11y gap surfaced (orientation-independent):** current assembly emits BARE `<th>`, no
      `scope=` at all (`table-tags.php:337-343`). `scope="col"` should be added for v1's first-row
      headers now; orientation later flips it to `scope="row"`. File as its own fix — not gated on
      orientation.

##### The cell reader — v1 (the whole table-side READ layer; row-kind-branched)

**⚠ RE-CUT BY §REOPENED D2a (2026-08-30) — read that first.** Q9's three-kind guard is deleted rather than widened, Q1/Q6/Q10 are already answered by the shipped `bws_read_resolved_source()`, and only Q7 stays open. What follows is the reasoning that produced those decisions, kept because the reasoning is still why they are right.

A table's rows come from the src chain (§Row-count is SRC-CHAIN CARDINALITY); a row's `kind` is whatever
the chain returned. The cell reader is producer-AGNOSTIC — it branches on `$row_source['kind']`, so it
serves every chain shape without a mode. A **single-result chain** gives one `post`/`term`/`meta_row` row
(the ex-"flat" case — a chain that returns 1, NOT a mode). A **repeater chain** gives `meta_row` rows; a
**`ref` fan-out** gives `post` rows; a **`srcTermIn`** gives `term` rows.

**All option names below are PLACEHOLDER** — `{N}-use`, `{N}-key`, value tokens `key`/`title` — #8 pass.
Grilled decisions (Q-numbers) lock MECHANISM, not spelling. The FW-32 fan-out consumers REUSE this reader
(ref-hop-parity.md references, does not re-spec). The seam split (grill Q1): row PRODUCTION and cell
READING are separate; the reader gains a `post`-kind branch because `use:key`/`use:title` MEAN different
reads on a `post` row vs a `meta_row` row.

- **Q1 — the CELL READER branches on `$row_source['kind']`.** `meta_row` (repeater row: row-array lookup;
  `use:title` hops a sub-field ref → post title) vs `post` (single-result chain / `ref` fan-out: read off
  the post entity; `use:title` = that post's OWN title, no hop) vs `term` (`srcTermIn` row: read term
  fields; `use:title` = the term name). Give each an EXPLICIT branch — do NOT lean on the accidental
  fall-through where the meta_row `ref` path reaches a raw `get_post_meta` (traversal-pipeline.php:605).
  The `ref` step stays the relationship-hop primitive; the cell reader stops mis-borrowing it for scalar reads.

- **Q6 — post `use:key` reads via `bws_read_field($key, $instance, $pid)`**, NOT raw `get_post_meta`.
  Honors the offered⟺resolvable gate (I8 corollary), handler-agnostic (I9 ACF-or-raw), explicit `$pid`
  wins over loop inference (feedback_loop_context_override). `$instance` is already threaded into
  `bws_table_read_cell` (reserved seam). `$pid` = the row source's entity id.

- **Q7 — column `use` enum stays `{key, title}` for v1**; `title` kind-branches (own title for post,
  ref-hop for meta_row, term name for term). Post-entity analogs (permalink/date/author/excerpt as cell
  values) are post-v1 — each a NEW analog through the I4 two-sided gate; permalink-in-cell reopens the I12
  link-in-cell question. Matches locked #10 (cell = scalar slot). **Tracked theme "in-slot option
  pressure":** Q7 is one of several deferrals (also #10 non-scalar cells) pushing toward richer in-slot
  reads; when it earns a build it's ONE "a cell can read more than {key,title}" pass through I4, not piecemeal.

- **Q9 — row-loop guard = the cell-reader's kind set.** With three branches the guard is `{meta_row, post,
  term}`: `bws_table_callback` skip = `! in_array($kind, ['meta_row','post','term'], true)`. (Was
  `{meta_row,post}` under the flat framing; term added because a `srcTermIn` chain is a legit row source.)
  Widen with the reader if a `user` producer ever lands. Guard set = cell-branch set — always move together.

- **Q10 — empty-cell contract: empty ⟺ `null | ''` ONLY; literal `0` PRESERVED.** One shared test across
  all branches: `is_scalar($v) && null !== $v && '' !== $v` → present; else empty (non-scalar → empty, the
  #10 boundary). `use:title` guards `$pid > 0` before `get_the_title` (I9 post/0). Closes the code-review
  `0`-drop item — the `post`/`term` branches are new read code that must pick a contract; the per-column
  `{N}-fallback` rides this definition of empty.

**Cell-reader fixtures/testing:** a single-result chain over `page-matrix-post-meta`'s own meta — columns
read base meta (`use:key`) + own title (`use:title`), proving the `post` cell branch on a 1-row table
(no fan-out chain needed). Visible **TB5+** rows (self-describing per feedback_fixture_row_label_expectation).
**Harness
split:** the `post` cell-branch reads are WP/ACF-dependent → testbed (`render-tag` + visible rows); the
pure `table-assemble-test.php` gains only pure cases (e.g. the Q10 empty-cell test if extracted as a pure
fn). Row grouping follows Catalog order (§Q13) — `{{table}}` rows sit AFTER `{{join}}`.

##### Relationship rows = a `ref` fan-out chain — NOT a table mode (reframed 2026-07-26)

> **This section SUPERSEDES `field-selector.md` §70-81 (noted 2026-09-18).** That paragraph, written two days earlier, instructed `{{table}}` to consolidate `repeater_key` into a two-axis scope-hint carrier because "relationship rows needs a SECOND scope axis". This section is what removed the premise: there is no relationship MODE, and the foreign-PT scope is a general chain fix, not table's. It routed to FW-32, which retired in 1.17.0 leaving "FW-39's scope" as the residue; that residue is FW-13's today. A superseded-note is in place at §70-81's own site.

Under the src-chain-cardinality model, relationship rows are just the chain's `ref` step FANNING OUT
(related posts → `post` rows), consumed like any other chain return. There is no relationship "mode." What
FW-32 owns ([`ref-hop-parity.md`](docs/design-history/ref-hop-parity.md)) is a GENERAL chain fix — make the `ref` chain token
fan out (drop collapse-to-first) + its foreign-PT `{N}-key` scope — which feeds ALL fan-out consumers, table
among them. The grill's PRODUCER-HOP half (Q2 ref-step-as-producer, Q4/Q5/Q8 union scope from the field's
live `post_type[]` — NOT a serialized PT, see [`src-chain-encoding.md`](src-chain-encoding.md) §ref post-type
scope; Q11 id-threading) lives there as FW-32 seed. The CELL-READER half
(Q1/Q6/Q7/Q9/Q10) stays HERE (§The cell reader) — it already handles the `post` kind a `ref` fan-out
produces. Relationship-row TB5+ fixtures land WITH the FW-32 fan-out fix; `{{table}}` row grouping follows
Catalog order (§Q13 below) — rows sit AFTER `{{join}}`.

**Query rows moved out.** A post-query row-source is NOT parallel to this — it is a new base-source
kind at L1 (`src:query`, cross-tag), with plural-base cardinality + live-preview + dynamic-scope costs
that make it a separate effort. Detail home: `.scratch/plans/query-source.md`.

Detail home for the block-lever / sentinel / `{{list}}` routes: the shared handoff §1-§10.

### Ship phase (last) — docs + finalize + PR, done once Build is complete

Runs at the END of v1, after every Build item lands — NOT as a separate prototype
checkpoint (user, 2026-07-24). These close out the release.

- `tools/test/table-test-matrix.md` (doc trigger — new tag family; matrix links, TB blocks show).
  Include the TB caption/wrapper rows (TB4) added in the a11y pass.
- `docs/tag-reference.md` entry (new tag/source/template → authoritative first) — cover the final
  column option names (#8), column-src (#7), and the caption option + scroll-region markup.
- `README.md` overview (user-facing prose — user review gate before release).
- CHANGELOG 1.17.0: finalize (date + curated Highlights, user-reviewed) at ship. Added entries for
  the tag + the a11y caption/scroll-region already drafted (`— unreleased`); still need the
  user-facing-prose review + Highlights selection.
- `readme.txt` Stable tag bump decision (stays 1.16.0 until actual release).
- PR `feat/table-tag` → main (branched deliberately; large/multi-commit path).

### Post-v1 — genuinely later / separate

- **Non-scalar cells** (#10) — nested repeater / image / relationship-beyond-title; coerced-empty for
  now.
- **`{{list}}`** — separate tag (header-content axis differs); own grill later. The handoff owns it.
- **Relationship rows (a `ref` fan-out chain)** — rides the FW-32 fan-out fix ([`ref-hop-parity.md`](docs/design-history/ref-hop-parity.md)).
  Not a mode; single-result + repeater + srcTermIn chains are the v1 reach (gated on the src-encoding model).
- **Query rows** — a chain starting from a `src:query` base (new L1 plural base-source); FW-54, separate
  effort (`query-source.md`). Same consume-the-return model, different base.

---

## FW-74 — the repeater-row arm → MOVED

**The arm SHIPPED in 1.21.0; the build record is [`docs/design-history/repeater-row-arm.md`](../../docs/design-history/repeater-row-arm.md)** ([PR #137](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/pull/137) is its published form). Extracted from this file 2026-09-04 into `.scratch/repeater-row-arm/spec.md`, which died at merge 2026-09-21: the arm lands generally and ahead of `{{table}}` (§D4), so a table-local plan was the wrong home for it, and `{{table}}` consumes nothing this file used to describe.

**What stays HERE, and it is decisions rather than design:** §D4 records why support lands generally rather than table-locally, including the analog-refusal call D4(a) that scopes which templates get a row fn; §GRILLED 2026-09-04 records the reach, read-seam and provenance scoping. Both are decision records — read them for why, not for how.

## Code-review follow-ups (`/code-review` of `fb29a27`, 2026-07-24)

Two-axis review, prototype scope. 0 hard standards violations, 0 wrong-implementation. Items to act on
before v1 (NOT blocking the prototype checkpoint):

**Cheap, in-branch now (no design dependency):**
- **Dead `$instance` param** — **RESOLVED 2026-07-24:** kept, documented as a reserved seam for the
  non-scalar cell reader (#10) in `bws_table_read_cell`'s PHPDoc (that reader needs the instance for a
  nested render). Not dropped.
- **Version-comment drift** — **RESOLVED 2026-07-24:** corrected the `1.16.0` comment strings →
  `1.17.0` in blocks.php + manifest.php + traversal-pipeline-test.php (schema/seed had none).

**Tie to a v1 held item (don't fix standalone):**
- **Header/use reveal coupled to `{prev}-key`** (Spec, latent) — a column with a header or `use` set
  but NO key is invisible past col 2 AND dropped by the contiguity `break` in
  `bws_table_collect_columns`. **DECIDED 2026-09-03 (Settled #25), still to BUILD:** a header-only
  column is valid and renders empty `<td>`s, so "column present" is slot-present, never `key`-present.
  Under folding the prototype's `bws_table_collect_columns` is scaffolding to delete (D1), so the fix
  is not a patch to that function — it is a constraint on the folded column collector that replaces
  it, plus the reveal trigger beside it.

**Conscious-call, low priority:**
- **`class="bws-table"`** (Spec, minor creep) — **RESOLVED 2026-07-24** (a11y pass): dropped. §9 bare
  `<table>` restored; the `bws-table-wrap` class dropped too. Frontend CSS hooks the attribute triple
  `[role="region"][aria-labelledby][tabindex]` (Roselli — self-enforcing, no class), so no presentation
  class survives on either element.
- **Cell empty test drops literal `0`** (Standards) — `( is_scalar($raw) && '' !== $raw )` treats `0`
  as present-but... check vs the "blank means blank" / '0'-preservation contract other cores honor
  (feedback: no_bare_default, zero-preservation). Decide consciously at v1.

**Non-findings (confirmed correct):** guard idiom, `bws_` prefix, text domain, reserved-key avoidance,
silent-empty-never-fatal, I11 id-threading, I6/I7 list order, §6 `use:title`-needs-ref, §2 full-source
engine, §3 get_field-over-have_rows, §9 text-as-div output.

---

## SETTLED 2026-08-22 — the step is `rows`; `entries` keeps the relationship-field sense

Raised 2026-08-19 while sweeping the 1.17.0 CHANGELOG for a different vocabulary collision
(ambient context was being called "the current entry"; swept to "the current context").

Two shipped senses competed for one word. 1.17.0's field-configuration note
(`includes/rest/field-discovery.php`) uses it for what a **relationship or post object field**
holds — `single-entry`, `multiple-entry`, *"can add entries"* — while the fanning step used it
for repeater rows. Both are *several things a field holds*, which is why both attracted it.

**Decided the opposite way to the 2026-08-19 lean.** That lean kept `entries` for the step on
the reasoning that a slug is the harder thing to move — wire is hand-editable by decision
(ADR 0004), so a rename is a migration. **That premise was false.** `entries` was on NO step
offer: both authoring surfaces pass `['refs','terms']`, with a comment at each site saying why,
so no editor ever wrote the token and only a hand edit could. Nothing in the wild carries it and
nothing migrates. The note strings, meanwhile, are genuinely shipped user-facing copy. The
cheap side and the dear side were the reverse of what the lean assumed.

**Locked decision 1 was STALE, not authoritative — and the history matters more than the
outcome.** It has said "`rows` = new fold step ... `case 'rows':`" since this plan was written,
but during prototyping (2026-07-24) the user decided to rename it to `entries` and that decision
WAS implemented. The reason was sound at the time: src options were not folded yet, so a step
needed a top-level option token (`rows:field`), and burning `rows` as an option name would have
cost `{{table}}` a word it might need. The plan simply never got updated, and `table-tags.php`'s
PHPDoc kept its old `rows` spelling for the same reason.

The 2026-08-22 reversal is a NEW decision on changed facts: with the src path folded, a step slug
no longer claims a top-level option name, so `rows` is free and `entries` can go back to the
relationship-field sense. The stale plan text is correct again by coincidence.

Worth recording because it nearly taught the wrong lesson: read off the artifacts alone, this
looks like code drifting from an authoritative doc, and the old "the owner doc wins" rule would
have produced the right edit for entirely the wrong reason. It was the second scenario — a
deliberate decision the doc never caught up with — and the rule would have been WRONG in
principle at any point between the prototype and this week. That is what amended CLAUDE.md
§Documentation ownership on 2026-08-22 to surface drift for a human decision instead.

Done in the same pass: the chain kind `base` became `render_time` and "static" became
"parse-time" (`CONTEXT.md` §Language, **Kind determination**). Neither touches this plan's
design, but the vocabulary a table reader meets is now the vocabulary the code uses.

**Still open for the table pass**, and NOT settled here: whether a `rows` step should be
writable at all outside a folded slot. A depth-0 `src:rows,<field>` on a base tag resolves to a
`meta_row` no arm reads — the FW-74 gap below — and its only present use is testbed rows.

Tracker row: **FW-83**, retired to the `docs/future-work.md` Closed / Retired ledger 2026-08-22.

---

## REOPENED 2026-08-30 — folded-only, and the src chain is the row producer

Planning-only pass (user: "without touching near-release code"). Nothing under `includes/` or `assets/` moves here. Three decisions from the user, then what each one dissolves and what it creates.

### D1 — `{{table}}` ships FOLDED-ONLY. No backward compatibility, at either level.

The prototype's flat registration (`bws_base_source_option()` + `bws_base_traversal_options()` at tag level, `{N}-label`/`{N}-use`/`{N}-key` per column) is not a starting point to migrate off — it is scaffolding to delete. The tag is gated behind `bws_dynamic_tags_register_table_tag` (default FALSE, 2026-08-12) and shipped in no release, so **no stored table tag exists anywhere and none ever will carry flat slot keys**. This settles the wording debt Decision #8's build item recorded: `bws_fold_migration_multislot_tags`'s docblock says `{{table}}` "is deliberately absent and always will be: it ships folded", which was an intent stated in the present tense; D1 makes it true of the code, and the migrator still needs no `{{table}}` entry and no `tag_level` axis surface. `TagTemplateRegistry::try_slot_axes()`'s tag-level-`key` hazard does not apply, and now cannot — see D2.

### D2 — NO tag-level `key`. The src chain delivers the read context.

The tag-level `key` (`'Repeater Field'`, `typeDefault => 'repeater'`, `table-tags.php:113-126`) is **RETIRED, not renamed or reused.** Its fate was left open by the 2026-07-26 reframe; this closes it. With it goes the terminal step injection at `table-tags.php:442` (`array_merge( $ref_steps, [ [ 'type' => 'rows', 'field' => $rows_field ] ] )`) — the fix target that reframe named. A table runs the author's chain and consumes the return; it appends nothing.

The row set is whatever the chain resolves to, and its kind is whatever the chain produced. **One mechanism, no mode discriminator:** a chain resolving to a single entity gives a one-row table; a chain resolving to multiple gives one row each; a chain whose last step is `rows` gives one row per repeater row. "Flat" was never a mode — it is a chain that returns one. **Those are examples, not a set — see D2a**, which corrects this paragraph's own framing: every resolved kind is a valid row, not three of them. This is the 2026-07-26 reframe (§Row-count is SRC-CHAIN CARDINALITY) restated, and it is now BUILDABLE rather than blocked: FW-56 shipped in 1.17.0 and GH #55 closed, so the multi-step src-selection encoding model the reframe hard-depended on exists.


### D2a — ANY chain that resolves is a valid row context (user, 2026-08-30)

Correcting D2's own wording. D2 named three shapes — single entity, multiple entities, repeater rows — and three is not the rule. **The rule is that a resolved source is a row, whatever kind it is.** A chain ending in `terms` gives a table of TERM fields, one row per term, and that is as first-class as the repeater case. So is a user, so is `site` (a singleton → a one-row table of site options), so is a registered root (FW-69). Cardinality is unchanged and applies to all of them alike: resolves to one → one row; resolves to N → N rows.

**The consequence is that every kind ALLOWLIST in this plan is a defect, not a scope.** Q9's row-loop guard (`! in_array( $kind, ['meta_row','post','term'], true )`) is **DELETED, not widened** — an enumerated set is exactly the shape that made "widen with the reader if a `user` producer ever lands" necessary, and there is nothing to widen if the guard never enumerates. Q1's "give each an EXPLICIT branch" framing goes with it: three branches was the flat-era count, and the number was never the point.

**And the kind-complete read layer already exists.** `bws_read_resolved_source()` (`field-helpers.php:696`) switches on `site` / `term` / `meta_row` / `user` / `post` — the exact five kinds `BWS_TRAVERSAL_STEP_INPUT_KINDS` names, including the `user` case written deliberately ahead of any producer for it precisely so the switch would not ship a hole. It already does what three of the grill's decisions asked a table-local reader to do: **Q6** (post reads via `bws_read_field($key,$instance,$pid)`, never raw `get_post_meta`, with the id-0 guard), **Q10** (`0` preserved — `'' !== (string) $raw` passes `'0'`), and **Q1**'s per-kind branching. So the cell reader **largely dissolves**: table stops owning a read switch and reads each row through the shared L2 seam. What stays table-owned is assembly — which value lands in which `<td>`, the empty-cell fallback chain, the header row.

**What table must NOT reuse, and the reason is specific.** `bws_resolve_field_values()` — the L1+L2 wrapper email/phone share — is the wrong layer despite looking like the right one: it **drops empties** (`if ( '' !== $value )`) and **slices to `limit`**. Both are correct for a value LIST and wrong for a table, where a blank cell must hold its column position and a row must not vanish for reading empty. Table runs the chain itself and reads per source through the L2 half only. Worth stating because the wrapper is the obvious call and the divergence is invisible until a fixture has a blank cell.

**Q7 (`use:{key,title}`) is the piece D2a sharpens rather than dissolves.** `key` is a keyed read and is answered above for every kind. `title` is an ANALOG, and with any kind as a row it needs a per-kind answer — a post's title, a term's NAME, a user's display name, the site name, and a repeater row's ref-hop (the one that is not intrinsic). That is analog-dispatch, which is `use`'s job everywhere else in the plugin (Model B), not a table concern. **Check before specifying anything table-local:** under D1 a column is a folded slot, so the slot READ vocabulary may already answer this per kind — if it does, Q7 dissolves into it the same way the source axis dissolved into the chain.

**Fixture consequence — the full-source item (#2) is re-cut.** It was "the engine accepts term/user/site repeaters; the prototype only eyeballed post", i.e. coverage of a repeater held on each kind. D2a makes the ROW ITSELF each kind, which is a different matrix: a term-rooted table (`src(terms,…)`, columns reading term fields), a site-rooted one-row table, a post-rooted one-row table, alongside the repeater-rows case. Visible TB rows for each, per the visible-row mandate.
### D3 — `entries` → `rows` revert is settled and already landed.

Confirmed against the tree: `rows` is the step slug throughout (`traversal-pipeline.php` `case 'rows'`, `BWS_FOLD_FANNING_SLUGS`, `BWS_FOLD_STEP_TYPES`/`BWS_FOLD_STEP_KINDS`, `bws_fold_wire_vocabulary()`, `slot-fold-grammar.js`), and `entries` survives only in its relationship-field sense in the field-configuration note. §SETTLED 2026-08-22 below is the record; nothing further to do. **A repeater row is a "row" in this plan's vocabulary, at every level** — the chain step, the table `<tr>`, and the row-scoped field picker all mean the same thing by it.


### D4 — repeater-row support lands GENERALLY (FW-74), not table-locally (user, 2026-08-30)

User: "depending on complexity, I'd like to build repeater row support for all tags rather than for table alone, to avoid owing that follow-up." **Complexity assessed 2026-08-30 and the answer is that the general build is the cheaper one.** Table-locally is not the smaller job — it is the same job plus a copy that FW-74 would later have to reconcile, which is the drift class this repo keeps naming.

**The scaffolding is already standing, and it was put there on purpose.** `BWS_TRY_SLOT_ARMS` (`try-slot-arms.php`) is a kind→arm table keyed on `bws_fold_chain_resolution()`'s vocabulary verbatim, and **`meta_row` is already a row in it** — an explicit refusal, carrying the wire-vs-resolved-base distinction in full ("`meta_row` NAMES TWO DIFFERENT THINGS and this table only ever means the first"). The read layer is kind-complete (`bws_read_resolved_source()`, D2a). What is missing is the same thing that was missing for `user`: the wiring.

**The measured precedent is #108** (`46b613f`), which wired the `user` leg live: **106 insertions across 10 files, 4 of them code.** Its own commit records why it was that cheap — "asserting the row before anything could reach it is what made #108 a wiring change rather than a table one." `meta_row` is in the identical position today.

**Three things are genuinely new, and only one is a mechanism.**

1. **A sources-not-ids selector.** Every arm reads through `bws_base_source_ids_of_kind()`, which returns `int[]` and drops `id <= 0`. A repeater row has NO id — it carries its `row` array. So the arm needs a sibling returning the resolved sources themselves. Small: the existing function is a dozen lines and already loops the sources; the ids version becomes a map over the sources version.
2. **A new `ids` mode on the arm table — a kind that is id-less AND fans.** `query_context` is the id-less precedent (`ids: 'none'`, `fn: 'query'`, `try_query_fn` takes the resolved BASE not an id) but it is a SINGLETON (`list: false`). `meta_row` is id-less and PLURAL — the arm reads each resolved source of that kind, with the limit/`sep` list seam applying. No current kind has that combination, so it is one new column value (`ids: 'sources'` or similar) plus its dispatch leg. **This is the only new mechanism in the whole item.**
3. **Per-template `try_row_fn`, on the KEYED templates only.** *(Scoped 2026-09-04: the keyed six split three ways, not one. text / content / email / phone read through the string seam; IMAGE needs the raw value — `bws_get_meta_image_data()` is the tree's only `single_only=false` caller and the string seam's `meta_row` arm drops arrays, so an ACF image sub-field in a row is unreadable without the `mixed` decomposition; DATETIME reads scalars but needs the ACF field-config object id, which a wire row cannot supply until provenance is recorded. See §GRILLED 2026-09-04.)* A keyed read has an obvious answer on a row (text/content/image/email/phone/datetime all read a sub-field by key, and `bws_read_resolved_source()`'s `meta_row` case already IS that read for the scalar ones). An intrinsic post analog has none. A template that omits the fn is an already-supported state — the `user` row's own comment says the six templates without a user fn "render empty there, as their base tags do" — so the omission needs no new handling.

**The base-tag half is a branch per arm, not a redesign.** `bws_base_text_resolve_value()` already switches on `$res['kind']` with `site` / ambient / `term`-list / `post`-list / singular legs; `meta_row` is one more list leg reading sources instead of ids. The other arms (`content`, `image`, `datetime`, `email`, `phone`) take the same shape. `{{join}}` gets it for free — it absorbs its read through the text seam.

**THE TRAP, and it is already written down twice.** `branchable` on the `meta_row` row **STAYS `false`** when the arm goes live. The wire kind (`src(rows,…)` — the author asked for repeater rows) gets the new arm; the RESOLVED base kind (GB Pro's flat repeater row, a query loop standing on one) must keep falling through to the post arm, whose core re-infers the row through `bws_read_field`'s own loop inference. Branch on `bws_fold_chain_resolution()`, never on `$base['kind']`. §F9c is the pin and **the mutation is to make the branch read `$base['kind']` and confirm §F9c fails** — run it, do not assume it. This is a live shipped path that breaks silently while the wrong implementation looks like the obvious one.

**What D4 buys `{{table}}`.** The column-slot root question (§REOPENED, "the ONE question") loses its hardest leg: a column slot rooted at a `meta_row` row has a read arm because every tag does. Table is then assembly over a read layer it does not own — which is what D2a already concluded from the other direction.

**THE ONE CALL D4 NEEDS, and it decides item 3's scope.** What does an ANALOG mean on a repeater row — `use:title` and its siblings? A row is not an entity and has no title, permalink, date or author. Two answers:

- **(a) Analogs REFUSE on a row.** Intrinsic reads are entity properties; a row has no entity. The `title` / `permalink` / `excerpt` / `author` templates then get no `try_row_fn` at all and render empty on a row source, exactly as they do on kinds they cannot read.
- **(b) Analogs hop.** `use:title` on a row means "the row's relationship sub-field → that post → its title" — the behaviour the prototype's `bws_table_read_cell` implemented and Q1/Q6 described.

**DECIDED (a) — user, 2026-08-30.** Analogs refuse on a repeater row; the `title` / `permalink` / `excerpt` / `author` templates get no `try_row_fn` and render empty on a row source. Reasoning as recommended below.

**Why (a).** (b) is a HOP wearing an analog's clothes, and the chain can already spell the hop with no new vocabulary — `rows,team_members;refs,lead_ref` then `use:title`, where the `refs` step produces a `post` and the ordinary post arm answers. Decision #6 already caught the same shape ("`use:title` REQUIRES a `ref` step first"), and §Per-column `{N}-src` locked the principle that a `use` value must not smuggle a source hop. (a) keeps `use` a pure read axis on every kind; (b) reintroduces the exact overload the fold removed. **Confirm before item 3 is scoped** — it decides which templates get a row fn.

**Tracker consequence, to apply once D4 is confirmed:** FW-74's row reads "Rides the `{{table}}` finalization, because that is where the kind first has a consumer worth having." D4 inverts that — FW-74 lands generally and `{{table}}` consumes it — so the row's framing and its `row:FW-53` blocker want re-cutting. Not edited yet; `docs/future-work.md` is committed and this is still planning.
### GRILLED 2026-08-30 — the reopen session's design tree, closed

Run via the grilling skill against D1/D2/D2a/D4. Eight questions, one round; recorded here rather than
re-derived, per the standing rule that a design decision belongs beside the thing it decided, not only
in a transcript.

- **Q1 — column-slot default root.** A bare column chain roots at **ITS ROW** — the container chain's
  output item is the column's default context, no new root token. `current`/`site`/registered roots
  stay available as explicit escapes. Restates D2 rather than adding to it. **Unchecked, flagged for
  the build:** whether the shipped `defaultRoot` derive (`current`, `slot-fold-control.js:1042`) takes
  a container-supplied default, or whether an empty column chain would wrongly serialize an explicit
  root; whether `src(same)` composes sanely with a row-default root (it inherits the PRIOR SLOT's
  chain, which is well-defined but new in combination).

- **Q2 — column key spelling.** Adopts the shipped fold ordinals (`A`, `B`, … via `bws_slot_ordinal()`)
  outright. **Decision #8's `{N}:` numbering is RETIRED** — it predates the fold shipping and a
  table-only numeric spelling would be a second ordinal system inside one grammar. Panel header
  "Column A"; format-token parity (`%A`) comes free.

- **Q3 — the reserved `label` grammar token, provisional rename `header`.** Verified 2026-08-30:
  `label` (`BWS_FOLD_FREEFORM`, both twins) has **zero consumers** anywhere — no PHP reader, no editor
  control (`slot-fold-control.js` only round-trips it) — and its own design-history examples
  (`label[Price: USD]`, `label[Note (TBD]`) read as an admin annotation, not rendered content, which
  collides in NAME (not mechanism) with this grammar's OTHER "label" — the control's own admin name
  ("Slot A", `$label_pattern`). User: not satisfied with `label` naming the `<th>` content, use-or-rename
  the SAME token (front-of-slot, freeform, escaped) rather than mint a second one. Renaming costs
  nothing — no migration, nothing round-trips under the old name for any live tag. **`header` is
  PROVISIONAL** (user, 2026-08-30: "continue with header as provisional") — final spelling rides the
  option-name pass like every other placeholder in this plan. Cross-container: join/try_ inherit the
  renamed token unused, same as they inherited `label` unused.

- **Q4 — per-column `align`.** A registered slot-opts grammar token (`align(start|center|end)`),
  format-group, own serialization rank — not a new mechanism, but **the first container-specific token
  in a grammar every other token in is shared**, worth building as a stated case rather than sliding in
  silently. `bws_serialization_order_key_map()` + the JS `KEY_MAP` mirror both need the entry (existing
  update-trigger rule already requires this for any new bare name).

- **Q5 — `try_` gets the repeater-row arm too; the arm table's `meta_row` comment is SUPERSEDED.**
  Reversed mid-grill on a correction to the premise: `takes_first_usable`/collapse is a BASE-TEMPLATE
  property (ADR 0007) that `try_` only *inherits* (`$collapse = ! empty( $tpl['takes_first_usable'] )`,
  `class-tag-template-registry.php:758`, "no separate arm") — it is not a `try_`-specific behaviour, so
  it supplied no reason to treat `try_` differently from a base tag here. Confirmed the real mechanism
  instead: within one attempt, `bws_read_bounded_sources()` reads the bound as SOURCES READ ([I19]),
  drops only empty VALUES, and `bws_try_join_items()` joins with `sep` — an attempt already renders a
  LIST exactly like its base tag; across attempts, first-POPULATED wins via the two `continue`s (`!$ids`,
  `!$shown`), never a first-usable selector. A `try_text` attempt ending in `rows` is structurally
  identical to one ending in `refs` or `terms` under this model — 1-D list, `sep`-joined, fall through
  if empty. `BWS_TRY_SLOT_ARMS`'s `meta_row` row text ("NO `try_` arm consumes a meta_row and none
  should — that is `{{table}}`'s assembly, not a fallback attempt's") is **superseded**: its argument
  is against `try_` doing TABLE's 2-D job, which nothing here proposes; it doesn't reach a 1-D read.
  `branchable` stays `false` regardless — untouched, different axis (resolved-base vs wire kind).

- **Q6 — base tags offer `rows` in their chain step set.** Both flat exclusion sites lose their stated
  reason once D4/Q5 land, so both widen: `base-tags.php:928` and, **confirmed as a SEPARATE site**,
  `class-tag-template-registry.php:654` — `try_`'s own `bws_build_fold_slot_options()` call, independent
  of the base-tag site, carrying its OWN copy of the same now-stale comment ("no `try_` arm assembles a
  repeater row … so offering it would author a chain that renders nothing"). Two call sites, two stale
  comments, one fix each. `term_`/`view_` modifiers are UNAFFECTED — confirmed they take
  `bws_base_traversal_options()` raw (flat, no chain step offer at all), so this axis doesn't reach them.

- **Q7 — row count = chain cardinality, EXACTLY, always render.** No skip-if-all-empty. The prototype's
  skip predates the cardinality reframe (D2) and reintroduces a second invisible rule ("cardinality
  minus rows that happened to read empty") that makes row count unpredictable from the wire. Per-column
  `fallback` is the author's tool for a blank cell, not a row-suppression mechanism.

- **Q8 — FW-74 ships as ITS OWN change, before `{{table}}`.** Has its own testable surface
  (`text-test-matrix.md` rows, `try-slot-arms-test.php`, the mandatory §F9c mutation-and-confirm-fail
  check), is independently user-visible (every source-reading tag gains a repeater-row source), and
  keeps the `branchable: false` regression risk on a diff that isn't simultaneously introducing a new
  tag. Near-release code moves once, deliberately — not as a side effect of table work, which is the
  outcome D4 exists to avoid in the first place.

**Net effect on this plan's earlier text:** the "ONE question D2 opens that nothing shipped answers"
(§REOPENED, column-slot root) is answered by Q1; its FW-74/arm sub-bullet is answered by D4+Q5 together
(the arm is general and covers `try_`, so table inherits it rather than building it). Decision #7/#8's
column-src-and-wire collapse (§What D1+D2 DISSOLVE) is unaffected by this round. Decision #8's `{N}:`
numbering is retired by Q2. The grammar's `label` token's eventual real name is `header`, provisional.

### What D1+D2 DISSOLVE

- **Decision #7 (explicit per-column `{N}-src` selector) and Decision #8 (folded column wire) COLLAPSE into one item.** They were two halves of the same gap: #7 wanted a source axis per column, #8 wanted the column's chain+read to ride in one `{N}:` value. FW-71 shipped exactly that — a slot's SOURCE *is* a base tag's source, emitted as depth-0 chain wire in `$slot_opts['src']`. A folded column gets its source axis by construction, so there is no separate `{N}-src` control to design.
- **Decision #12's field-discovery scoping loses its anchor and gains a better one.** The BUILT mechanism reads the sibling tag-level `key` from `state` and filters records by `rec.repeaterKeys.includes(key)`. With no tag-level `key` there is nothing to read. The replacement anchor is the **`rows` step's argument in the container chain** — the same repeater name, stated once where the author picks it. `bws_build_fold_slot_options()`'s `scope_state_key` parameter is documented as "Tag-level option whose value scopes the picker", and **that is the one shipped seam D2 invalidates**: the value now comes from a step inside a chain value, not from a sibling flat option. Everything else in the #12 stack survives untouched — the additive `repeater_key` stamp at discovery time, the `scope => 'row'` flag, the filter-hide render conditional, the free-text fallthrough.
- **The tag-level `key` picker's `typeDefault => 'repeater'` MOVES rather than dies.** Scoping a picker to repeater-type fields is what the `rows` step's own arg picker wants, and `bws_build_fold_slot_options()` already accepts `rows_option` (→ `rowsOption`) which the control already renders with `typeDefault` support (`slot-fold-control.js:775-792`). The scoping is a config value passed to a shipped picker, not table code.
- **The `bws-term-hop` carrier (FW-67) loses its last table-side hold.** That row states the carrier survives where the flat `srcTermIn` control still registers — the modifier families plus `{{table}}`, which take `bws_base_traversal_options()` raw. D1 stops table taking them raw, so after this the carrier survives on `register_modifier()` alone. **[Moot 2026-10-12: FW-67 deleted the carrier in 1.21.0 and `register_modifier()` itself is deleted (FW-129); nothing for D1 to release.]**

### What D1+D2 CREATE — and the seam that is already built

**The adoption is a CONFIG CALL, not new machinery.** `bws_build_fold_slot_options()` already accepts `rows_option`, `field_scope` and `scope_state_key`, and `slot-fold-control.js` already reads `rowsOption`, `fieldScope` and `scopeStateKey` — a seam built for this tag and **currently passed by nobody**. Verified 2026-08-30: no caller in `includes/` passes any of the three. So the column surface is a call with the right arguments plus the scope-anchor rework D2 forces, not a control to design.

Build items this pass leaves standing, re-cut against the shipped fold:

1. **Tag-level source = the chain control**, offering `rows` in its step set. The offer is a per-container argument (`bws_fold_step_offer`), and both current authoring surfaces pass `['refs','terms']` deliberately (`base-tags.php:928`, `class-tag-template-registry.php:654`) — `{{table}}` is the container that passes `['refs','terms','rows']`, which is the promotion the 2026-07-26 reframe called for. The vocabulary entry already exists, labelled and limit-labelled (`base-shared.php:187`).
2. **Columns = folded slots** via `bws_build_fold_slot_options()`, container `'table'`, `max` = `BWS_TABLE_MAX_COLS` (10, matching join), `noun` TBD in the option-name pass.
3. **Scope anchor rework** — ~~read the repeater name off the container chain's `rows` step arg instead of a tag-level option. Shape open: a new argument beside `scope_state_key`, or a generalization of it that names a chain step rather than an option key.~~ **RESOLVED 2026-09-18: NEITHER — the anchor DISSOLVES.** Both options assumed the anchor is a repeater named by something; under the chain-tail model the tail may be a `post`, `term`, `user` or `site`, so there is no repeater name to read. FW-74 shipped the mechanism one level down: `predecessorContext()` narrows a step's picker off what the preceding position names, and `kindAt()` answers what the chain resolved to by a position (`''` for a render-time root, so everything is offered — #34's rule already in code). A column slot's predecessor is the container chain's TAIL: same two functions, one level up. `rows_option` / `field_scope` / `scope_state_key` are DELETED, not called. Build design `.scratch/table-tag/issues/03-columns-become-folded-slots.md`.
4. **The cell reader mostly DISSOLVES into the shipped L2 seam** — see D2a. `bws_read_resolved_source()` is already kind-complete, so what stays table-owned is assembly (which value lands in which `<td>`, the fallback chain, the header row), not a read switch. §The cell reader's Q1/Q6/Q9/Q10 are re-cut there; Q7 is the one piece still open.

### The ONE question D2 opens that nothing shipped answers

**What does a column slot's chain root at?**

A `{{join}}` slot's chain roots at the tag's ambient context, and `src(same)` inherits the previous slot's chain (`bws_fold_chain_join()`). Neither is what a table column wants. A column reads **relative to its own row**, and the row is a different entity per `<tr>` — so the root is not a fixed context the slot can name.

**DECIDED — GRILLED 2026-08-30, Q1.** A column slot with no explicit root roots at **its row**. The container chain's output item *is* the column's context, which is exactly what "the src chain delivers the initial context for the field-read" says. `current`/`site`/registered roots stay available as explicit escapes for a column that deliberately reads off something other than its row. No new root token; the default root is redefined per container. See §GRILLED for the full record.

**What that still needs checked before the build** (none of it done this pass):

- ~~Whether the shipped default-root derive (`defaultRoot` from `current`, `slot-fold-control.js:1042`) can take a container-supplied value, or whether a column's empty chain would serialize as an explicit root it should not carry.~~ **ANSWERED 2026-09-18, and the question was the wrong way round.** Table sets `defaultRoot: 'inherit'` AND does not strip it, so a bare column serializes `src(inherit)` explicitly rather than as an empty chain — the opposite of what "a root it should not carry" assumed. The reason is the ambiguity `slot-fold.php:1467` already documents: a stripped default cannot be told from an unmade choice, and `src(same)` is the consumer that needs to tell them apart. Table is the only container greenfield enough to avoid it (D1: nothing stored, no migration); `{{join}}`'s own strip is now an Open line on FW-120. Also corrected: the slot ordinal namespace is SHARED across containers (`{{join A:…|B:…}}`), not distinct. Pending confirmation at the naming/preview gate (`.scratch/table-tag/issues/00-naming-pass-and-preview-toggle.md`), which carries a toggle rendering both shapes. **⚠ REVERSED 2026-10-03 (#49):** table strips; see §GRILLED 2026-10-03.
- ~~Whether a column slot's read arm handles a `meta_row` context.~~ **ANSWERED BY D4 + GRILLED Q5** — the arm lands generally and covers `try_` too, so a column slot rooted at a row reads the way every tag does. FW-71's "the `rows` refusal MOVED to the container that consumes the kind, so FW-53 waits on its own arm" is what D4 discharges, and it discharges it once for the whole plugin rather than inside this tag.
- **⚠ SUPERSEDED 2026-10-03 by #50** — the carry-over is copy-on-select, `same` is never written (§GRILLED 2026-10-03). The cost finding below still holds and is why the row seed is back. **`src(same)` on a table column — DECIDED (user, 2026-08-30): BUILD, don't default.** Empty slot → row (Q1) stays the only default; `same` is an explicit opt-in, identical in shape to join's. User's own framing: "not illogical… wouldn't make it the default if we enable it" — already guaranteed by construction, since Q1 owns the default and `same` is a separate token.
  **The cost finding that settled it:** `bws_fold_slot_chain_options()`'s carry seeds `chain: []` unconditionally, with its own comment stating why — "the empty chain is also how the ambient entity is spelled, so an inherit off a fresh accumulator is indistinguishable from an inherit off an ambient slot 1" (`slot-fold.php:1467`). For table this seam must seed **row**, not ambient, for ANY bare column to resolve correctly — that work is required by Q1 alone, with or without `same`. Once it lands, `src(same)` on the next slot inherits exactly that seed through the existing merge mechanism, no new code path. So `same`'s marginal cost rides on work Q1 already requires, near zero on top of it — refusing it would need a special-case exclusion, which is more code than allowing it.
  **The concrete use case:** two columns hopping the SAME relationship off the row — column A shows the related entity's name, column B its email, both need `refs,lead_ref` first. `same` removes the duplicate hop, exactly its job on join.
  **Forward marker, not a decision:** user flagged join MAY later follow table's row-as-primary-context model (tag-level context as primary rather than ambient-slot-1) — recorded here so a future join redesign has this pointer rather than re-deriving it. Table is not waiting on join, and join is not scoped by this pass.

### Status of the rest of this plan

Unchanged and still live: the a11y pass (BUILT), the serialization-order slot-major decision (A1 — still required, still zero blast radius, and a folded column makes it more relevant, not less), the cell reader (§Q1/Q6/Q7/Q9/Q10), per-column fallback, justification/`align`, the presentation-options fork (A/B/C, still unsettled), `orientation` (name reserved, post-v1), the Ship phase, and Post-v1.

Superseded by this section: Decision #7 as a standalone item, Decision #11 (the `key` rename — the option it renamed is retired), Decision #12's scope-anchor mechanism (its discovery half survives), and the Build item "Adopt the folded slot wire for COLUMNS" as separately-cut work.

---

## GRILLED 2026-09-03 — §OPEN A closed: one header wire, one authoring affordance

Grill of §OPEN A (which header modes ship in v1). Closes A, dissolves B, and reserves one name. Settled index rows #24-#27 point here. Everything below was decided with the folded model (D1/D2) already in force, which is what changed the answers: the three-mode framing in Locked decision #5 was priced against a FLAT column, and folding re-priced two of the three out of existence.

### A1 — "Another field" header mode is KILLED, not deferred

Under the flat prototype a header mode was a per-column enum beside a per-column key, so a second read was cheap. Under folding a column IS a chain, and "the header reads another field" means a **second chain inside one slot**, rooted at the base source rather than the row, in a grammar where a slot has exactly one chain. That is grammar surgery, not a mode flag.

Its payoff is also thin: a header is per-column and constant across rows, so reading a field buys nothing over typing the string except tracking that field's stored value.

**No wire is reserved for it.** It existed because the pre-fold model made it cheap; folding made it expensive, so it must be re-earned from a real request rather than inherited as a debt.

### A2 — Field-label is a TIMING fork, not a mode

The plan framed field-label as a render-time schema read (`acf_get_field(...).label`), and everything that made it "the one genuinely undecided mode" followed from that framing: admin-only labels leaking to the frontend, an undefined non-ACF fallback, and a new ACF-only dependency (verified 2026-09-03: **`acf_get_field()` is called nowhere in this codebase**).

But the editor already holds every field's ACF label. `bws_field_discovery_flatten_fields()` emits `label` per record (`includes/rest/field-discovery.php`), and the whole envelope is inlined on the page as `window.bwsFieldEnvelope` before any control hits REST. So the same author outcome is reachable **at edit time**, and that route has none of the four costs: no schema read, no ACF dependency, no non-ACF hole, and no admin-label leak, because nothing ships that the author did not look at and can edit.

Render-time label-mode is better on exactly one axis: the header would track a later ACF rename without re-saving the block. That is post-v1 and only on request.

### A3 — A header-only column is valid and renders

Column presence is **slot-present, never `key`-present**. A slot carrying only a header and no chain renders a column of empty `<td>`s.

This is Settled #13's rule on the other axis: row count is chain cardinality exactly, with no skip-if-empty, so a column is not dropped for being empty either. It is also the only way to author a spacer or annotation column, and it is what the code-review latent (§Code-review follow-ups, "Header/use reveal coupled to `{prev}-key`") was waiting on.

### A4 — `<thead>` gate keeps the built behavior

`<thead>` is emitted iff ANY column carries a header; a blank header in a table that has one emits `<th></th>`.

All-or-nothing was rejected: it is a validation rule whose reason the author cannot see. An empty `<th>` is a legitimate corner cell, and is exactly what a row-header column needs when `orientation` lands (§OPEN D).

### A5 — "Another field, same SOURCE" — name reserved, not built

The narrowed variant the kill in A1 does not cover: a per-column **bare field key, no chain, no steps**, read off the table's base source (`headerKey[venue_heading]`), reader = one `bws_read_field()` per column. It kills the second-ROOT objection but not the second-CHAIN one, which is why the narrowing to a bare key matters.

Its one real use case is **client-editable headings** — the author ships the table, the client edits heading text in a field without touching the block. Real, but hypothetical here, and a different customer from "I want the field's name as the heading", which A2 already serves.

**Reserved, not built.** It is strictly additive: the literal `header` token needs no change when it arrives. Building it now would also cost a precedence rule against the literal, a second control, serialization-order and `KEY_MAP` entries, and a preview-label case, all for an unrequested feature.

### A6 — The affordance is an advisory with click-to-apply, not a placeholder

A placeholder was rejected on author grounds, not cost: it vanishes the moment the author types, and an author who forgets what it said must clear the box to see it again. It also does not put the label on the wire, so a skipped placeholder yields no heading at all (A4).

The shape is the **field configuration note** (#96, `assets/js/slot-fold-control.js`) rather than the fanning advisory: the note is per-picked-field and looks up by key from the envelope, which is this problem exactly, where fanning is a property of a whole chain. The line persists while a labelled field is picked, survives typing, and needs no clearing.

**Click-to-apply is included, and is cheap.** The first framing of this decision treated applying as a control writing a SIBLING key — the codebase's first, and the reason to hesitate. That framing is wrong under folding: a slot's tokens all live in one option value, `label` is already a first-class field on the JS slot struct (parsed and serialized in `assets/js/slot-fold-grammar.js`), and applying it is the same whole-object `setState` the control already performs for chain and opts. No new precedent, no cross-control plumbing. Estimate: advisory component ~30 lines (the lookup is the note's, reused), apply handler ~10, PHP copy strings, one `slot-fold-repeater-test.js` case.

Auto-fill on field pick was rejected: it silently flips A4's default, so an author who wanted no heading must now clear one, and it fights re-picking a field.

### A7 — Uncertainty: render nothing, inheriting the note's rule

A column's chain serializes only the bare field key, and the field-combo's merge identity is (kind, key, label) — the same bare key with a DIFFERENT label in two groups is deliberately kept as two distinct records. So a label lookup by key can have more than one answer.

The note already solves this and the advisory inherits the rule verbatim: show only where **every** envelope entry holding that key agrees; disagreement, including one entry with a note and one without, yields nothing. Render nothing likewise when no field is picked yet, or when the key is a `register_meta` key (no ACF definition, so no label to claim).

Rejected: rendering all candidates (a second picker inside an advisory) and rendering the first (asserts a fact that may be false).

### A8 — Header CONTROL is table-only; the TOKEN stays shared

`{{table}}` is the `header` token's only consumer, and a `<th>` has no meaning in a `{{join}}` or `try_` slot, so a control there would offer an option that renders nothing.

The token stays in the shared grammar and round-trips everywhere, exactly as `label` does today with zero consumers. A future consumer then needs a control, not a grammar change.

### A9 — Link visibility

The line shows whenever a labelled field is picked. The apply link hides when the header already equals the label, and returns when the author edits away from it — which makes the link's presence a divergence signal rather than noise. Hiding the whole advisory once the header is non-empty was rejected: it removes the fact precisely when the author is comparing.

### A10 — Copy

> This field's label is "City". **Use as the header**

PHP-authored, per the note's rule that every user-facing string stays in PHP and the chain-step control hand-authors no vocabulary. Full sentence matches the field configuration note's register; "Use as the header" names the destination control rather than the action.

**REVISED 2026-10-19 (user decision), supersedes A9's line and A10's copy.** Once the field picker settled ambiguous keys by source (its reopened row names the label), the line restating the label became redundant, and A9's reason for keeping it ("removes the fact precisely when the author is comparing") no longer holds: the fact is in the picker row. The affordance is now one action link beneath the field picker, shown only while the header differs from the label: *Use "City" as the column header*. Placement moved from the header box to the field picker the same day, because the label is the field's. Mechanism owner: `docs/editor-controls.md` §Field-label action.

### A11 — §OPEN B dissolves

B was "strip the inferrable header-mode key beside the header value" (`bws-as-size` precedent). With one mode there is no mode key, so B has **no referent** — it is not deferred and not a v1 held item. Its site in Locked decision #8 carries the same note. If A5's reserved `headerKey` is ever built, a mode-inference question returns then, on its own terms.

### A12 — Column cap: 10, its own const, driver is registration weight

Two sites in this plan contradicted each other: Settled #23 said `BWS_TABLE_MAX_COLS = 10` "matching `BWS_JOIN_MAX_SLOTS`", Locked decision #8 said `=6`, "its own const, join-specific, did NOT carry the name". They disagreed on the number AND on whether the value derives from join's. The code said `6`.

**The cap is not addressing, and cannot be removed.** Verified 2026-09-03: `bws_slot_ordinal()` encodes spreadsheet-style (`27 → AA`) and both twins match `^[A-Z]+$` deliberately, with comments at both sites saying a single-letter pattern would be wrong (`includes/helpers/slot-fold.php`, `assets/js/slot-fold-grammar.js`). Every consumer routes through the helper; nothing hardcodes one character. Even `{{join}}`'s `%A` format token survives past 26, because its scan loops DESCENDING from max so `%AA` is replaced before `%A`.

So the ceiling exists for one reason: **GB options are static**. A container registers its slots up to a ceiling and renders one iff it holds a value (FW-45's "register-to-ceiling"), which makes the ceiling a count of option definitions in the editor payload — one per folded slot. Capless would need either GB-side dynamic options or all columns nested inside a single option value, a third grammar level. Neither is v1.

**Value = 10, in `{{table}}`'s own const, matched to join by independent judgment rather than derived from it.** Nothing about a table argues for fewer columns than join allows slots; one ceiling across the plugin is one number for an author to meet; and a table-specific outlier would be the straggler if FW-45 ever lifts ceilings globally. Decision #8's independence claim stands untouched — only the number moved, and the const's own docblock now owns the driver so neither plan site restates it.

Applied to code in the same pass: `includes/tags/table-tags.php` `6 → 10`, docblock rewritten to state the registration-weight driver and the absent ordinal ceiling.

### Build consequences

- Delete: nothing from the prototype specifically for this — the prototype's `{N}-label` registration is already scaffolding to delete under D1.
- Build: the `header` control on `{{table}}`'s folded slot (A8), its advisory + apply (A6/A7/A9/A10), and the folded column collector's slot-present rule (A3).
- Register `header` into `bws_serialization_order_key_map()` and the JS `KEY_MAP` (Settled #22 already requires this for `header` and `align` both).
- Test: `slot-fold-repeater-test.js` case for the advisory's agreement rule and the apply write; `table-assemble-test.php` cases for the header-only column and the `<thead>` gate.
- Docs on ship: `docs/editor-controls.md` owns the advisory (a control mechanism); `docs/tag-reference.md` owns the `header` option row; `docs/editor-tag-previews.md` if the header shows in preview text.

---

## GRILLED 2026-09-03 (second pass) — §OPEN C, G and H closed

Continuation of the same session. Closes C (presentation shape), dissolves G (display-group chrome), and closes H (the bare-column root) with a cross-container vocabulary decision that reaches `{{join}}` and `try_`. Settled index rows #28-#33 point here.

### C1 — Fork B: bare-key boolean flags, no `style` key

Fork A (CSV multi-value `style`) has **no precedent in this codebase**. The only comma-valued option is `as:url,medium` (`bws_parse_as_option`, `explode( ',', $raw, 2 )`), which is POSITIONAL and COUPLED, not a set. A set-valued option would be the plugin's first, and it would need a new custom control (checkbox group to CSV serialize) to author it.

Fork B is already paved end to end: `'checkbox'` is a shipped option type with eight registrations, and the boolean presence-flag convention is documented house style with shipped examples (`showCurrentYear`, `showMidnight`, `noLink` — `docs/tag-reference.md` §Boolean presence-flag convention). True serializes as a BARE KEY, false is dropped.

**Fork B's stated cost was panel clutter, and that objection is already answered.** The option-group wrapper (`bws_option_visual_groups()` stamping `_group`/`_group_lead`, joined by `assets/js/option-group.js`) boxes separately-registered options into one visual run. B gets `style`'s cohesion without a shared key. Fork A's other claimed advantage, "no per-preset schema churn", overstates the delta: a new preset under A still costs CSS plus an enum value plus control handling, so B's extra is one `KEY_MAP` entry and one serialization rank.

Fork C (single-select) was already dead on the merits: the presets are orthogonal and a single-select cannot combine them.

**CONSEQUENCE, AND THE CONDITION THE FORK RIDES ON (user, 2026-09-03): B holds only while every presentation option is ORTHOGONAL.** There is no `style` key under B, so the `style`-vs-`fixed` "orthogonal split" dissolves and ALL presentation is flags. The day a preset arrives that is mutually exclusive with another (two skins that cannot both apply), flags can no longer express it and this fork REOPENS. That is the tripwire: a new presentation option is checked for orthogonality first, and a non-orthogonal one is a fork question, not a registration.

### C2 — v1 presets: `stripes` and `fixed`. `bordered` and `compact` deferred

*(2026-09-29, user, from the FW-141 grill: the flag is RENAMED `fixedLayout`. FW-141 claims `fixed` for the fixed-text read, which a column reaches once it moves to the shared read leaf; one word meaning a table flag and a column read would also need two serialization ranks for one key-map name. Nothing shipped, so no migration. Settled #47. Class `bws-dte-table-fixed` may follow at build; not decided here.)*

`fixed` is near-free: `table-layout:fixed;width:100%` (the `width` is required or `table-layout:fixed` is inert), no setting, no color question, and it is the flag that decides whether a wide table is usable. `stripes` is the one authors ask for and its color path is already designed.

`bordered` needs decisions not yet made (which borders, what color, how it interacts with a theme's own table CSS). `compact` needs a padding baseline that does not exist: our CSS sets no cell padding at all, so `compact` would first have to establish what "normal" is. Both are deferred on missing prerequisites, not on priority.

### C3 — CSS queue: drop the caption gate

Queue whenever a table renders (the plan's own 2a). The a11y region rules sit inert without a captioned wrapper, a few bytes. A gate that has to track "caption OR any preset OR any future preset" is a defect waiting to be written.

This forces the const change the plan predicted: with a runtime stripe color, `BWS_TABLE_INLINE_CSS` stops being a bare `const`. Keep the static a11y rules as the const, build the preset rules in the callback, concat at queue.

### C4 — Stripe color: strict allowlist, not "sanitize light"

The stripe color is author-supplied text concatenated into a `<style>` block on the front end. The earlier spec ("plain text field, not `wp_color_picker`, so it accepts `rgba`/`var`; sanitize light, fall back to default on junk") is under-specified exactly where it matters: a value containing `}` or `</style>` breaks out of the declaration and injects arbitrary rules or markup into every page that renders a table. Admin-only input (`manage_options`), so not an unauthenticated hole, but stored input reaching every visitor.

**Validation is a STRICT ALLOWLIST**, accepting exactly: `#rgb` / `#rrggbb` / `#rrggbbaa`, `rgb()` / `rgba()`, `hsl()` / `hsla()`, a bare `var(--name)` or `var(--name, <fallback>)`, and CSS named colors. Everything else falls back to the default. This rejects `}`, `;`, `<`, quotes and comment syntax BY CONSTRUCTION, because none of them appear in an allowed form. Rejected: a character denylist (the pattern that keeps being wrong on a CSS sink — escapes, `\7d`, comment tricks), and `sanitize_hex_color` (safe but drops alpha and `var()`, which kills the reason for not using the color picker).

Run `wp_strip_all_tags` before matching, and treat a non-match as "use the default" rather than an error, so a junk value degrades to a working table. Settings shape is otherwise the established one: a new `table` group in `bws_dynamic_tags_settings`, per-key sanitize in `sanitize_settings()`, a typed accessor. Default `rgba(0,0,0,.04)`. Emit `background:var(--bws-dte-table-stripe,<GLOBAL>)` so the global is the baked default and a theme can still override per context.

### C5 — Stripe selector: `tbody tr:nth-child(odd)`

**`nth-child` counts within a PARENT element, and `<thead>`/`<tbody>` are separate parents.** So dropping the `tbody` scope does not produce one continuous count: `tr:nth-child(odd)` unscoped matches the header row (position 1 inside `<thead>`) AND body rows 1, 3, 5 — two shaded bands adjacent at the header/body boundary. That option was considered and rejected on this fact.

`tbody tr:nth-child(odd)` shades body rows 1, 3, 5 with the header untouched. It works because our CSS sets NO `<thead>` background, so the header is unshaded and alternation starts cleanly below it. Scoping to `tbody` also makes the count independent of whether a `<thead>` exists at all, which matters directly: under §GRILLED A4 the same table gains and loses its header depending on whether any column carries one.

The surveyed alternative for a shaded header is `thead tr` shaded plus `tbody tr:nth-child(even)` — true alternation across the whole table on one color. Not taken for v1.

**TRIPWIRE:** if a future preset ever paints the header band (a `bordered` skin, header shading), `odd` puts two shaded bands adjacent and this choice reopens.

### C6 — Class prefix: `bws-dte-`

`bws-` alone carries a small collision risk across this author's other projects (user, 2026-09-03). Classes are `bws-dte-table-stripes`, `bws-dte-table-fixed`, `bws-dte-table-align-*`; the CSS var is `--bws-dte-table-stripe`.

**The front-end class namespace is EMPTY today**, which is what makes this free: `bws-table` and `bws-table-wrap` were both dropped in the a11y pass (which hooks the attribute triple instead), and `bws-fanning-advisory` is editor-only. Nothing migrates.

Unabbreviated over the draft's `bws-t-*`: these are a public CSS API that themes target and that cannot be migrated after v1, and `bws-t-al-start` is not readable to the theme author who has to type it. The abbreviation saves bytes in a stylesheet we generate anyway.

### G — DISSOLVED: real option grouping ships

§OPEN G asked "whether the config renderer supports real grouping, or v1 is display-order-only". It was written before the option-group wrapper existed. It ships: `bws_option_visual_groups()` in PHP stamps `_group`/`_group_lead` at registration, `assets/js/option-group.js` joins consecutive members into one box by CSS, and a lone non-lead member stays deliberately bare.

Two live constraints come with it rather than a free hand. The group map is **PHP-derived and applies to our options only** (a JS-side map would wrap GB core's identically-named `key`/`source` too), and `{{table}}` is already named in `control-order-test.php` §1 as a flat-select family, so an ungrouped control spliced between two grouped ones is caught by the existing harness. Practical effect: C1's Style flags get a group row, and the harness checks it.

### H — the bare-column root is `src(inherit)`, and `same` is re-described

§OPEN H named one defect. There are two, and the second is the load-bearing one.

- **H1, the derive.** `defaultRoot` is `$src_rows[0]['value']` — the first offered root (`base-shared.php`). Whatever a bare column should root at has to lead that list.
- **H2, the scoping, which the plan did not name.** `stripDefaultRoot()` returns early on `ordinal >= 2` (`slot-fold-control.js`), because for `{{join}}` an absent slot 2+ is a RESET, not an inherit. For `{{table}}` EVERY column roots the same way, so table needs the strip and the implied-step display at every ordinal. `srcRows` and `srcRowsInherit` are assembled per container inside `bws_build_fold_slot_options()`, so the root leads BOTH lists for table, and a table column's absence spells the default at every ordinal instead of `same` at ordinal 2+. That is the container flag, and it is what keeps `src(same)` on a column an explicit, never-default choice as Settled #15 requires. (**Restored 2026-10-03 by #49** after the spec's 2026-09-18 reversal; #15 itself is superseded by #50.)

**THE NAME IS `inherit`, MEANING THE PARENT'S RESOLVED SOURCE.** The naming took five passes and every rejected candidate is recorded here because each was rejected on a different rule:

- **`row`** — rejected by D2a: any resolved kind is a valid row, so a slug asserting "row" claims a source kind the tag has not got. (The "a table row is a row whatever fed it" reading fails on the axis it sits on: the value lives in `src(...)`, so it is read as a statement about the source.)
- **`item`** — rejected by `CONTEXT.md` §Language: "A query-loop item is GIVEN; a fan is DERIVED ... A fan is what our own path produces from a root (`refs`/`terms`/`rows` steps), and what it yields are **sources**, never 'items'." A table's rows come from `rows`, so they are a fan, and naming a fan member `item` collapses the GIVEN/DERIVED distinction that paragraph exists to hold.
- **`context`, `this`** — `context` is on the same section's *Avoid* list (it means the #19 query-context specifically, a subset); `this` is vaguer still.
- **`entry`** — genuinely close, and rejected on the 2026-08-22 reversal: `entries` was kept for the relationship-field sense precisely so the word would not come to mean "repeater row" (shipped copy: `single-entry`, `multiple-entry`, "can add entries"). On a repeater-fed table `src(entry)` means the repeater row, which is the same claim through a different door, and a generic fan-member root also dilutes the reserved sense to "member of anything".
- **`each`** — a quantifier, not a noun. Every other root is a noun (`current`, `site`, `refs`, `terms`, `rows`), and `src(each)` leaves the sentence unfinished.
- **`fan`, `member`, `outer`** — all name a fan member (or the wiring), which is the flaw `inherit` does not have, below.
- **`parent`** — NOT rejected on the same axis as the others: it is **RESERVED** (user, 2026-09-04) for the real hierarchical relation, a parent post or parent term, which is intended as a later root. It is also ambiguous in `{{table}}`, where a column's parent is a row and the tag's parent is the source that produced the rows. Recorded here because it is the candidate the next person naming a root will reach for.

**Why `inherit` is not merely acceptable but correct: it is the only candidate that is TRUE when the parent chain does not fan.** Every fan-member name is meaningless on a `{{table}}` whose tag-level chain resolves to a single source with no `rows` step, which D2a explicitly permits. `src(inherit)` reads "whatever this tag's own chain resolved to", and that holds in both cases: a fanning parent hands the member being rendered, a non-fanning parent hands its single source. One rule, no shape claim, and no container has to teach the author what a fan is.

**The objection that blocked it, and its repair (user, 2026-09-03).** `inherit` looked taken, because `same` is DESCRIBED as inheriting. The repair is to fix the description, not to surrender the word: **inheriting from a PARENT and borrowing from a SIBLING are different relations**, and the grammar currently has one word doing both jobs badly. `inherit` becomes the parent relation on the wire; `same` keeps its slug and is re-described as keeping a previous sibling slot's selection.

Priced before deciding, not after:

- **Wire: zero.** `same` keeps its slug, `inherit` is new. Nothing migrates.
- **User-facing copy: five strings**, all in `assets/js/slot-fold-control.js`. The two shipped LABELS are already sibling-worded and stay: "Same as Previous Field" / "Same as Previous Source". The verb is **carries over** (user, 2026-09-04, revising the keeps/kept decision of 2026-09-03), and the replacements drop the em dashes and the internal phrase "both axes":
  - `Inherits the previous source — this slot repeats it until you change the source.` becomes `The previous source carries over until you change it.`
  - `Varies: source (field inherited)` becomes `Varies: source (field carries over)`
  - `Varies: field (source inherited)` becomes `Varies: field (source carries over)`
  - `Inherits the previous source — pick a field for this slot.` becomes `The previous source carries over. Pick a field for this slot.`
  - `Inherits both axes — this slot duplicates the previous one until you change something.` becomes `Both the source and the field carry over until you change one of them.`
- **WHY keeps/kept LOST, 2026-09-04.** Three reasons, in the order they decided it. (1) `keep` already ships twice in user-facing copy with other meanings — `class-settings-page.php:575` "Keep — tags work normally" (a collision-resolution choice) and `base-shared.php:239` "An item with an empty field keeps its place" (the `limit` step help, which an author reads in the SAME panel as the slot advisory). A third sense of "keep" inside one control is the defect this item exists to remove. (2) `carry` is already the incumbent internal vocabulary for this exact relation — `&$carry`, `$carried`, "carry-forward" — so one word now spans wire, code and screen with no translation rule for a future editor to not know about. (3) It fixes a mood clash: `Keeps the previous source. Pick a field for this slot.` puts an elided-subject indicative beside an imperative addressed to the author, where `The previous source carries over.` matches the descriptive register the rest of the advisory family already uses (`Varies: source`, `Varies: source and field`). The one argument the user explicitly did NOT find significant is (1)'s "keeps its place" collision; (2) and (3) carried the decision.
- **Internal identifiers on the SIBLING axis must move in the same pass.** **The stem is `same`** (2026-09-04) — an identifier that names the WIRE TOKEN is checkable against the wire, while one naming the prose verb goes stale the next time the copy is reworded, which is the drift that produced this question. **That rule was stress-tested and held in the session that applied it:** the prose verb moved TWICE (`inherit` → keeps/kept → carries over) while `same` never moved. The settled names:
  - `conf.srcRowsInherit` / `conf.readRowsInherit` → **`srcRowsWithSame`** / **`readRowsWithSame`** (PHP locals `$src_rows_with_same` / `$read_rows_with_same`). `WithSame`, not bare `Same`: the suffix means "the enum with the `same` row prepended", which is literally the one thing the `2` argument does at `base-shared.php:796-797`; bare `srcRowsSame` reads as "rows that are the same".
  - `props.inheritOnEmpty` → **`sameOnEmpty`**.
  - **A FOURTH identifier this census missed**: the skip-reason token `'inherit'` → **`'same'`**, three sites in lockstep — producer (`slot-fold.php:1543`), map key (`preview-helpers.php:109`, where it maps to the author-facing `no previous source`), and a test literal (`slot-fold-test.php`). It sits beside `'read'` and `'step:<slug>'`, which name the construct that failed to resolve, so `'same'` is the consistent formation. It adds `preview-label-test.php` and `slot-fold-test.php` to this item's trigger set.
  - `$inherited` (in `bws_fold_chain_join()`'s signature and the merge locals) → **`$carried`**, aligned to the shipped `&$carry` PARAMETER rather than to the prose verb, so a copy pass cannot restale it. Two PHP prose sites the five-string census missed, both sibling-axis and both in scope: `class-source-registry.php:112` ("`same` is the slot inherit sentinel") and `class-tag-template-registry.php:538` ("the inherit rules live in one place for every container"). Leaving them means `inherit` names the parent relation on the wire and the sibling relation in the file implementing both, which is precisely the drift that makes a word stop carrying signal. About 160 mentions of the stem exist across 21 files; only the ones naming the same-axis are in scope — **measured 2026-09-04 as ~140 sibling-axis sites across ~15 files under `includes/`+`assets/`, plus ~27 live-doc sites**, not the "roughly twenty comment sites across six files" priced elsewhere in this plan. **Internal prose uses `carries`/`carried`/`carry-over`, matching the user-facing verb** — no internal/external split, since `carry` was already the incumbent. Where a sentence would then hold BOTH the mechanism noun (`carry-forward`, the accumulator) and the relation verb, it names the SENTINEL instead ("slot 3 resolves `same` against slot 1"), which is the same move this rule makes for identifiers; ~5 such sites. That stem overlap is standing debt in its own right and is now tracked as **FW-122**. `docs/design-history/` is excluded by CLAUDE.md rule. `tools/fixtures/core-structures/blocks.php`'s ~20 fixture row labels are DEFERRED: they reach the committed page-snapshot baseline (measured — `page-matrix-post-meta.html` contains "INHERITS the ref hop"), so editing them forces a same-commit re-capture and destroys the empty-snapshot-diff proof that this rename moved nothing.
- **Out of scope, flagged**: datetime's `inherit_date` ("Time-only values inherit date from start") is a third sense, and start/end are siblings, so it reads against the new split. Different feature, no shared surface, low confusion risk, left alone.

**Cross-container reach.** `inherit` is a root any container can offer; `{{table}}` is its first consumer, labelling it **"This row"** per container. The parent/sibling distinction wants a `CONTEXT.md` §Language entry when this is built, since it is a vocabulary rule spanning join, try_ and table rather than a table decision.

---

## GRILLED 2026-09-03 (third pass) — §OPEN I, J and K closed

Closes the last three open rows. Settled index rows #34-#36 point here. §OPEN D, E and F are not grilled: all three were already decided post-v1 (orientation's shape, the `scope` pair, the responsive JS enhancer), and a post-v1 row is not a v1 fork.

### I — three of the four items are already settled by shipped code; two questions survive

The UI bundle was cut when a column was a flat option set and the src-encoding model did not exist. Re-checked item by item against 1.17.0:

- **(1) src-menu SHAPE — SETTLED.** Settled #7 (a bare column roots at its row) plus #33 (`src(inherit)` leads both root lists, `current`/`site`/registered roots stay explicit escapes). Its stated blocker, "the column-src menu shape can't finalize ahead of the src-encoding model", dissolved when FW-56 shipped.
- **(4) click-to-add vs `show_if` reveal — SETTLED.** The slot repeater shipped (`assets/js/slot-fold-control.js`, add + remove + compaction); FW-45's own progress line records it as answered.
- **(2) kind-filtered value-set — SURVIVES, and D2a sharpens it rather than answering it.** `bws-field-combo` ships a row auto-scope: `props.scope === 'row'` narrows the picker to a repeater's sub-fields through `scopeKey` (`assets/js/field-combo-control.js`). That is right when the fan came from `rows` and WRONG for every other kind D2a admits: on a term or user fan the author should see that kind's fields, and offering repeater sub-fields means every offered key resolves empty. **Decided: derive the picker's scope from the tag-level chain's RESOLVED KIND, falling back to unfiltered when the kind is only knowable at render time.** The kind-determination axis already exists (`CONTEXT.md` §Language) and is exactly the right instrument: a parse-time kind (`rows`, `terms`) filters correctly with no render, and a render-time kind degrades to the full list rather than to a wrong one. Keeping row-scope unconditionally was rejected as the only option that can actively mislead.
- **(3) minimal-default view + advanced accordion — SURVIVES, and is NOT table-local. Decided: no accordion for v1.** `{{join}}` ships ten slots with no accordion, so a table-only one makes two slot UIs that behave differently for the same reason. If ten fold slots are too long to scan, that is true of join today and the fix belongs to the shared slot repeater. Worth a `docs/future-work.md` row against the repeater, not an item in this plan.

### J — the matrix axis is RESOLVED KIND, and the rows land with the code

J's re-cut is right and its axis is now derivable rather than invented: the kinds it enumerates (term-rooted, site-rooted, post-rooted one-row, repeater-rows) are exactly the branches of `bws_read_resolved_source()` (`field-helpers.php`), which is the seam a table column now reads through. **Mirror that switch**, so a missing row is visible as a missing branch rather than as an omission nobody can see.

What is decided NOW is only the axis. The rows themselves are build-phase work that lands with the code making them pass, under the standing mandate: every new row group is also generated as VISIBLE GB blocks, then reseed, curl the front end, and re-capture the page-snapshot baseline in the same commit.

**Defect found while grilling this row, fixed in the same pass, no issue filed.** `tools/fixtures/core-structures/blocks.php` interpolated a row label RAW into `<p class="gb-text">%s</p>`, and TB4's label contains a literal `<caption id>`. That wrote an unclosed tag into the block's saved HTML, so the editor's saved-vs-parsed check failed with "Block contains unexpected or invalid content" — and because a host row's label block renders ABOVE the row it describes, the error appeared to belong to TB3. Fixed at the helper (`bws_fixture_gb_text_block`) by escaping `<` and `>` only, after the uid is seeded off the raw body so no id moves; full `esc_html()` would entity-encode the quotes in every other label and move the whole snapshot baseline for a defect only those two characters cause. It was the only label of nine containing angle brackets. The guard, not an issue, is the memory of it.

### K — the FW-74 tracker row re-cut

Mechanical and unchanged by this session's decisions: FW-74's "rides `{{table}}`'s finalization" framing inverts under D4 (the repeater-row arm lands generally and FIRST, per Settled #5 and #14). The row's Blocked-by / Interacts-with columns are re-cut to say so. Not a fork, and it stays open only until someone edits `docs/future-work.md`.

---

## GRILLED 2026-09-03 (fourth pass) — D2a's unchecked check, and what actually blocks `{{table}}`

Opened by a sequencing question (does FW-74 need to SHIP before `{{table}}`, or just commit first?) which turned out to rest on an unresolved fact. Settled index rows #37-#38 point here.

### The check D2a left, run: Q7 does NOT dissolve

D2a ended with an instruction rather than a decision: "**Check before specifying anything table-local:** under D1 a column is a folded slot, so the slot READ vocabulary may already answer this per kind — if it does, Q7 dissolves into it."

**Run 2026-09-03, and the answer is no.** `bws_build_slot_read_options()` (`base-shared.php`) is a PICKER, not a dispatcher: it re-labels the base template's own `use` enum per slot and adds the `same` row. It answers nothing per kind. So a column's analog read has no home yet, and Q7 stands.

What the check DID settle is that the two halves of a column's read route differently:

| A column's read | Path | Needs FW-74? |
|---|---|---|
| `use:key` on any kind | `bws_read_resolved_source()` — kind-complete, `meta_row` included | No |
| Analog on a repeater row | Refuses, per D4(a) | No, nothing to build |
| Analog on a term / user / site / post row | Per-kind analog dispatch, which nothing currently exposes | Its own seam, below |

### The v1 scope fork that fell out, and the cost that decided it

If v1 columns were keyed-only, `{{table}}` would need nothing that does not already ship. **DECIDED: v1 supports ANALOG columns on non-repeater rows (user, 2026-09-03)** — a term-rooted table can show term names, which is a plausible first thing an author tries, and it removes a "refuses on repeater rows, works on terms" split that an author would meet as a bug.

**The cost was measured before deciding, and it is one function.** The obvious route looked expensive: `bws_base_ambient_analog()` (`base-shared.php`) does switch analogs per kind, but it opens with `if ( 'render_time' !== bws_base_src_resolution( $options )['kind'] ) return null;` — the ambient gate — so a column's explicit chain returns null every time, and reaching it would mean extracting the switch out from behind that gate.

**No extraction is needed, because every analog reader underneath it is already public and takes an ID:**

- term: `bws_base_term_analog_read( $tag, $term_id, $options, $instance )`
- user: `bws_base_user_analog_read( $tag, $user_id, $options, $instance )`
- site: `bws_site_resolve_value( $tag, $options, $instance )` (no id)
- post: the tag's own core by id — the route the prototype's `bws_table_read_cell` already took for the ref-hop `use:title`
- `meta_row`: refuses (D4a)

So what v1 builds is an **analog twin of `bws_read_resolved_source()`**: a per-kind dispatcher taking a resolved source, getting its id from the existing `bws_source_link_identity()`, and calling the reader above. Roughly 30-40 lines over readers that already work, no behavior change to the ambient path, and no new analog semantics — the per-kind meaning of `use:title` is Model B's, already settled.

**Build it GENERAL, not table-local.** FW-74's item 3 (per-template `try_row_fn`) is this same dispatch on the arm side, so writing it once is what D4 argued for in the first place. A table-local copy is the drift class this repo keeps naming.

**Two things are inherited free rather than re-decided.** The user leg is `title`/`content`/`text` only (the measured image/permalink carve-out), so a `use:permalink` column on a user row renders empty exactly as the base tags do there. And `use:key` keeps its meaning on every kind, so the `use` axis stays a pure read axis, which is the principle D4(a) protected.

### The sequencing answer: commit-first, not ship-first

Settled #14 said FW-74 "ships as its own change, before `{{table}}`". Q8's three grounds for it are all diff-scoped, not release-scoped: its own testable surface, independent user-visibility, and keeping the `branchable: false` regression risk "on a diff that isn't simultaneously introducing a new tag". A separate commit or PR satisfies all three.

Release ordering is nearly vacuous here anyway: `{{table}}` is gated behind `bws_dynamic_tags_register_table_tag` (default FALSE) and is in no release, so "FW-74 ships first" binds only if the gate flips in the same release, and even then the risk Q8 names is per-commit.

**And with the analog seam above, FW-74 is not a prerequisite at all.** Table's keyed columns read through the shipped kind-complete seam; its analog columns read through the new dispatcher, which is table's to build and FW-74's to reuse — not FW-74's arm. Going first remains worth doing for the reasons Q8 gives; it is a preference, not a gate.

**Tracker applied in the same pass (closing §OPEN K):** FW-74's row loses "Rides the `{{table}}` finalization" and its `Blocked by: row:FW-53` — which meant FW-74 was blocked by `{{table}}` itself, the exact inversion D4 corrected. Its Open line now names the real question (whether its `use:`-dispatch half shares the analog seam). FW-53's row records that it does not wait on FW-74, and gains FW-74 in Interacts-with.

---

## GRILLED 2026-09-04 — FW-74's real shape, FW-7's false blocker, and the `inherit`/`same` split

Opened as a grill of FW-74 and FW-7 together. It closed both, corrected two claims in this plan, scoped FW-74's build, and spun a deferred `{{join}}` item out. Settled index rows #39-#46 point here. `{{join}}`'s own decisions live in `.scratch/plans/join-rebuild.md` (FW-120), not here.

### FW-7's blocker was a resemblance, not a code fact

FW-7 read `Blocked by: row:FW-74` — "deleting the inference is gated on FW-74 landing a `meta_row` base arm first." It cannot be. FW-74 lands a **wire-kind** arm and its own trap rule forbids branching on `$base['kind']`, so the ambient repeater row keeps falling through to the post arm and into `bws_read_field()` branch 3 — the very inference FW-7 proposed to delete. FW-74 landing removes nothing from branch 3's job. The two items share the noun `meta_row` and nothing else.

**And the loop half of FW-7 is already decided AGAINST, by #122.** `bws_loop_item_gated_post_id()`'s PHPDoc says the factory route and the read route "must not be unified": both call `bws_source_gate()` so the criterion is single-owned, but a refusal costs a different thing per layer — at the factory a refused post leaves the FAN, changing which ARM runs and dropping into the `meta_row` fallthrough the repeater-row rendering depends on; at the read the arm is already chosen and a refusal only stops the read. So branches 2 and 3 are load-bearing by decision. What survives in FW-7 is the term-archive branch plus the four falsy-id families, and its gate is `decision:cores take a resolved base` — a cores-signature question, which is a real fork rather than a `meta_row` resemblance.

### FW-74's reach, and the two boundaries that decide it

**Reach = base tags + `try_` + `{{join}}`.** `{{join}}` is free: it absorbs its read through the text seam. `term_`/`view_` are OUT — they take `bws_base_traversal_options()` raw with no chain offer, so they cannot spell `src(rows,…)` at all, and widening them means giving them a chain option, which is FW-70 phase C.

**The read seam DECOMPOSES rather than gaining a sibling.** `bws_read_resolved_source()` is already a raw read with one coercion repeated in every arm. So: `bws_read_resolved_source_value(): mixed` owns the kind switch, the string function becomes the coercion over it, and `bws_get_meta_image_data()`'s two-pass read (the tree's only `single_only=false` caller, needed because GB `Meta_Handler::get_value` returns `''` for arrays) migrates into the `post` arm. This is not tidiness: the string arm drops arrays, so **an ACF image sub-field inside a repeater row is only readable at all through the raw path**.

**Datetime was mis-grouped and is a different axis.** It reads scalars (`single_only=true`, `datetime-helpers.php:335/343`) and already dispatches from a resolved-source-shaped payload — `bws_parse_combined_date_time()` takes the shim's `{kind,id,taxonomy}` and hand-rolls a three-kind read switch. What it needs is the ACF **field-config object id**, which its own shipped `@invariant` states is a SEPARATE arg from the value read. So datetime consumes the value half now and leaves the config half to FW-3, whose Open line is that same missing exposure.

**Provenance is recorded at the producer.** `bws_pipeline_rows_to_sources()` records only `row` — no parent kind, parent id, repeater name or index — so `get_field_object()` cannot be built for a sub-field and a wire row loses ACF return-format fidelity (issue #22's failure mode, reached by a new route). `bws_run_step()`'s `rows` case holds the parent source and the field at that moment, so the four keys cost nothing there and are expensive to add to a shipped source shape later. FW-74 records them; FW-3 consumes them.

### §F9c.4 stops pinning what its label says

`BWS_TRAVERSAL_STEP_INPUT_KINDS['rows']` accepts `meta_row`, so a slot stating `src(rows,…)` while standing in a repeater row is legal wire meaning "nested repeater". Today F9c.4 passes because that slot is "refused as a chain kind"; once the arm lands it passes because the nested read finds nothing. Same output, different reason. The stated reason is rewritten in the arm's own commit and a row exercising a genuine nested repeater lands with it.

### The analog dispatcher is table-only, and this is why

Settled #38 claimed "cross-container (build the seam GENERAL, FW-74 reuses it)". FW-74 cannot reuse it — D4(a) refuses analogs on a repeater row. And the other two containers already have analog routes: `{{join}}` slots absorb through `bws_base_text_resolve_value()`'s term/post arms, `try_` slots dispatch through `try_term_fn` / `try_user_fn` / `try_site_fn`.

**The structural difference is the signature.** Both existing containers dispatch analogs FROM WIRE OPTIONS; a table column dispatches from an ALREADY-RESOLVED source, because the fold has produced the row before the cell reads. It cannot go back through the text seam without re-running the chain per cell and losing the row identity the seam has no argument for — which is why the dispatcher takes a resolved source and gets its id from `bws_source_link_identity()`.

It becomes cross-container the day a second reader dispatches from a resolved source. That day is FW-120, not FW-74. Promote it then.

### `inherit` and `same` are different relations, and the shipped code names the wrong one

`inherit` = the parent's RESOLVED source, non-positional, resolution-level. `same` = the previous resolving SIBLING's whole CHAIN, positional, wire-level. They compose: **`inherit` is resolution-level only at position 0**, so a slot may root at `inherit` and build a path on it (`src(inherit);refs(related_post)` — **mis-spelled: the chain is `src(inherit;refs,related_post)`**, see §GRILLED 2026-10-03), and a later `src(same)` carries root and steps both (**for table, superseded by #50's copy-on-select**) — `bws_fold_chain_join()` never trims a root, and its trim loop stops at any slug with no `BWS_FOLD_STEP_KINDS` entry, so an `inherit` root survives by construction with no new rule.

> **⚠ STALE 2026-09-18 — the paragraph below prices work that HAS SINCE SHIPPED.** The rename landed in `d6b29ec` on 2026-09-04, the same day this was written, and shipped in v1.19.2/v1.20.0. Zero `inherit`-stem identifiers remain in the tree: it is `sameOnEmpty`, `srcRowsWithSame`, `srcRows` throughout, and the only `inherit` left anywhere is datetime's unrelated `$inherit_date`. So the slug is FREE, #45 is discharged, and the census below is a record of what the work WAS, not of work outstanding. Kept rather than deleted because the "~140 sites, four identifiers, one-sided-rename hazard" measurement is the reason FW-115 exists.

The census behind §H's rename is wider than the five strings priced there, and wider again than this paragraph first said: **measured 2026-09-04 at ~140 sibling-axis sites across ~15 files** (not "roughly twenty comment sites across six files"), plus ~27 live-doc sites and a FOURTH identifier — the `'inherit'` skip-reason token. Every shipped use of the stem on this axis means SIBLING carry. `props.inheritOnEmpty`, `conf.srcRowsInherit` and `conf.readRowsInherit` are identifiers, and the last two ride the PHP-derived option config — each side pinned by its own harness (`slot-options-build-test.php` names the PHP keys, `slot-fold-repeater-test.js` the JS ones), so a one-sided rename fails loudly; what nothing compares is the key SETS, which is FW-115's hazard and is tracked there rather than fixed here. **The rename ships STANDALONE and FIRST** (user, 2026-09-04) — ahead of `inherit` rather than with it, so intervening sessions stop tripping over the collision. It is a user-visible copy change landing alone, so it takes its own CHANGELOG entry, and though it is a pure rename it trips both the Folded-slot REGISTRATION and Folded-slot CONTROL triggers.

### What went to `{{join}}`, and why it is deferred

A tag-level source for `{{join}}` with slots inheriting it was designed to a close in this session and then **deferred behind `{{table}}`** (user, 2026-09-04): table ships `inherit` first, and how much of `{{join}}` is rebuilt is decided after. FW-120 tracks it; `.scratch/plans/join-rebuild.md` holds the decisions.

Two findings from that half belong here because they correct claims made in this plan's fourth pass and in this session:

- **`stripDefaultRoot()` is OURS, not GB's** — a JS function, slot 1 only, dropping a lone root equal to `conf.defaultRoot` so that merely looking at a slot cannot change its wire. It is not GB's default-strip, so nothing forces a container to make its bare-slot meaning the default root. Root lists are per-container, so `{{table}}` can default to `inherit` while `{{join}}` keeps `current` — which is what protects every shipped join tag whose slot 1 states no root.
- **The read-path move has no standalone motivation.** Two of its three claimed payoffs do not survive pricing (the #104 `limit` write-back carries a wire-ERA fact that travels regardless; the `id` threading collapses only once one tag-level resolution exists to thread into), and the third is a cache. Without a tag-level source there is no parent resolved source to dispatch from at all.

## GRILLED 2026-10-03 — column `src`: strip the default, copy instead of `same`

Reopened by the naming pass (ticket 00), whose preview toggle put the explicit `src(inherit)` shape on real wire. The user rejected it, and with it the reason it existed: `src(same)` reads confusingly on the wire when the slot it points at is a stripped default, and it is positionally fragile — removal needs the materialization fixup, reorder would need a cascade, and a hand-edit that deletes a slot silently re-points it. Settled index rows #49-#59 point here. `{{join}}` is untouched: it is expected to be reworked after table proves this strategy (FW-120), and its tag-level source would give it the same uniform empty-chain meaning. `try_` has no tag-level source, so the same move there would have to write slot 1's default root explicitly whenever it copies; that is its own decision, taken later.

### The wire

- **Strip the default root (#49).** An empty column chain means the row (the container chain's tail) in EVERY column, so `stripDefaultRoot()` runs at every ordinal for table, not slot 1 only. This reverses the spec's 2026-09-18 "a bare column serializes its root" and restores what §GRILLED 2026-09-03 (second pass) H2 had already scoped. The shared-ordinal-namespace argument (`A:(key(name))` reads the same in a join) was weighed and dropped: the tag name always stands beside the slots, and join's rework converges the two meanings anyway.
- **`inherit` survives as table's `defaultRoot` (#52)** — the value the default menu entry selects, what the strip compares against, and what hand-written `src(inherit)` parses as. Never written root-only.
- **A path root is always written (#55).** When steps follow, the root is explicit: `A:src(inherit;refs,x)` or `A:src(current;refs,x)`. A root-only `current` is written as `src(current)` because it is not the default. Spelling note: steps are `;`-separated INSIDE one `src(...)` token and an arg follows `,` — `src(inherit);refs(x)`, as the 2026-09-04 section above and ticket 02 first wrote it, is two tokens, not a chain.
- **Hand-written step-first chain (#58).** Accepted, reads as the row, rewritten to `inherit;…` on the next edit of the tag.

### The control

- **Copy-on-select replaces live `same` for `src` (#50).** "Same as previous" writes a COPY of the previous column's current chain, visible and editable step by step; `same` is never written. Reselecting the entry is the RESET: it re-copies over local edits or a stale copy. Accepted cost: a copy does not follow later edits to the column it came from. The shared prefix belongs in the container chain, so a column copy is a hop or two, cheap to see and to fix. Supersedes #15's live `same`.
- **A new column starts EMPTY (#53)**, i.e. the row — not a copy. The tag's source stays the default; copying is two clicks away. `sameOnEmpty` is off for table: clearing a chain means the row.
- **The copy entry (#56):** not offered on column 1 (nothing precedes it; column 1 empty already means the container tail); hidden when the previous column's chain is empty (the copy would equal the default entry); labelled **"Copy Previous Column's Source"** — an action, honest about the copy not tracking edits.
- **Root menu step group (#57):** one group, retitled ~~**"From Row"**~~ (label superseded: §DECIDED 2026-10-15 — root-menu labels, #64), and picking a step from it writes `inherit;<step>`. Building off the current context is pick `current`, then append.
- **Hand-written `src(same)` (#54).** The grammar is shared, so it parses and renders live. The control displays it expanded and writes the expansion on the next edit (resolve back through `same` until a concrete chain). At column 1 it expands to empty — the row — which keeps #44's intent (the author meant the parent) in the stripped spelling.

### The read axis

- **No `use(same)` in table v1 (#51).** Follows the 2026-07-31 posture (`docs/design-history/src-chain-encoding.md` §"`use(same)` in combining containers"): legal, never the default, not built before per-slot handlers. Hand-written ones round-trip. Revisit with handlers; copy-on-select is the natural match then.

### Consequences, not decisions

- **The row seed is back.** The spec predicted writing the root would dissolve the `bws_fold_slot_chain_options()` row-seeding; with the strip, an empty column chain must resolve to the row, so the carry seeds row for table. This is the cost finding under §"The ONE question…" (the `src(same)` bullet), restored.
- **The preview toggle is retired (#59).** It existed to compare the two shapes; the comparison is decided. Its build commit, `d43c265`, is the record.

## DECIDED 2026-10-14 — a type token leads the slot

### A type token leads the slot (#60)

- **The rule (user):** when a folded slot carries a type token (`BWS_FOLD_TYPES`: `title`, `content`, `email`, `phone`, `permalink`, `image`, `datetime_single`, `datetime_range`), it comes before everything else in the slot value, a table column's `header(…)` included: `A:title;header(Team);key(…)`, not `A:header(Team);title`. The type is the primary format option and already leads the format group in the canonical order.
- **Why it was not here already:** the label-first order (the 2026-07-29 draft `2:label[Date];datetime_single;key[due]`, and the 2026-10-05 leaning in the reopen working note, item 22(iv), deleted 2026-10-15) was specified when the type token was not in view (user, 2026-10-14).
- **v1 reach:** the user does not expect a type token in table v1, so every v1 column still leads with `header(…)` and item 22(iv)'s order stands for it. The rule is recorded now regardless, so it governs the day a type token is present in a table column, and in `{{join}}`, whose slots already carry one and have no label.
- **Parser:** order-free. `bws_fold_parse_slot` takes a valueless type token wherever it sits in the slot (`slot-fold.php`, the `$agnostic` branch), so saved tags written label-first still read.
- **Code that still emits label first, NOT yet changed (decide before touching):** `bws_fold_emit_slot()` in `includes/helpers/slot-fold.php` (label by explicit code, not by rank), its JS twin, the `slot-fold-test.php` expectations and the page-snapshot baseline. Moving them is a real-code change; the tests and baseline move in the same commit. Until then the emitter and this row disagree for a slot that has both a type and a label, which no v1 control can produce.
- **Tooling:** `tools/preview/tag-string-preview.html` already emits the type first (`serializeSlot`).

## DECIDED 2026-10-14 — styling encodings: the text and alignment keys

Closes the "styling encodings" syntax review (the Targets, Pairs, Merged and M+head candidates in `tools/preview/tag-string-preview.html`) for the text and alignment keys only. Settled index row #61 points here. **It does not close the #28 reopen:** the rest of the reopen was still leanings here (see "What this does not settle"); §DECIDED 2026-10-15 (presentation) closes it.

### The wire (#61)

- **Encoding: plain Merged (user).** One key per property. A value is a base segment and, optionally, a header override: `fontWeight:normal;header,bold`. The bare segment is the base, a segment led by the header word is the override, and a base-unset value is just the override (`fontWeight:header,bold`). At tag level the base is the whole table; in a column it is the body cells. Targets (`align:table(center),header(left)`), Pairs (`font:weight,normal` plus `headerFont`) and M+head (the base's head written too) are out.
- **Why Merged:** it keeps one vocabulary (a key is a property, so `normal` never collides between weight and style), it is the same `head,args;head,args` shape as `stripe` and the Merged `when`, and a column nests no deeper than one bracket. Pairs would have needed three extra `headerX` keys and a second vocabulary inside `font`. M+head would have needed a base head word that differs per key once the column cascade below is applied.
- **Header word:** `header`, respellable through the `headerContext` rename id. It is the only head word.
- **`stripe` uses the same grammar:** `stripe:rows,odd,#eee,40%;cols`, items joined by `;`, the first token of an item the head, the rest args typed by value. The stripe's own questions (parity default, counting the header as row 1, color and opacity args) are NOT settled here.
- **A tag in the settled shape** (hand-built, not run through the PHP):

```
{{table src:rows,products|layout:fixed|width:100%|textAlign:left;header,center|fontWeight:normal;header,bold|textTransform:header,uppercase|stripe:rows
  |A:header(Product)|B:header(Price);width(8rem);textAlign(right);color(#c00)}}
```

### The properties

- **v1:** `textAlign`, `color`, `fontSize`, `fontWeight`, `fontStyle`, `textTransform`. `align` is renamed `textAlign` (the CSS property; it also leaves `align` free).
- **`color` is the text color and takes no opacity argument.** Alpha comes from the color value (`rgba()`, 8-digit hex). Stripes keep their own opacity argument.
- **Background is deferred, named `background` when it returns, with nothing reserved.** Its old id `fill` is retired in this section's wording (the preview tool's catalog still keys it `fill`, listing `background` first as a respell candidate).
- **`layout` keeps its name.** `tableLayout` is unnecessary inside a `{{table}}` tag (user, 2026-10-14, open to being persuaded).
- **Vertical alignment is tracked as FW-158, not v1.**

### The cascade for column styling

| Cell | `textAlign` | color and font |
|---|---|---|
| Body cell | column bare > table | column bare > table |
| Header cell | column Header > column bare > table Header > table | column Header > table Header > table |

A column's bare value covers the whole column for `textAlign` and the body cells only for color and the font keys. So the editor label differs ("Column" against "Body"; copy is a later pass). This **restores ticket 05's "header included"** for alignment and reverses the 2026-10-06 body-only rule in the reopen note for that one property.

### Values

- `textAlign`: `left`, `center`, `right` (no `justify`, user 2026-10-13).
- `color`: the #30 strict allowlist, plus the CSS globals.
- `fontWeight`: `normal`, `bold`, `100` to `900`, plus the CSS globals.
- `fontSize`: a number with `px`, `em`, `rem`, `%`, `ch` or `vw`; `var(--x)`; the keywords `larger`, `smaller`, `xx-small` to `xxx-large` and `medium`; plus the CSS globals. `calc()` stays unrecognized. The PHP sanitizer must accept `var()` wherever the editor does (the open note in the reopen file's round 63).
- `fontStyle`: `normal`, `italic`, `oblique`, plus the globals.
- `textTransform`: `none`, `uppercase`, `lowercase`, `capitalize`, plus the globals.
- **CSS globals are one shared allowlist on all six keys:** `inherit`, `initial`, `unset`, `revert`, `revert-layer`. `inherit` is therefore also a wire value outside the chain-root sense (user: that does not bother them). From the CSS spec, not measured: all six properties are inherited, so `unset` behaves as `inherit` on them, and `revert` returns to the browser's default and skips the theme (a header cell gets the browser's bold, centered `th`).

### Hand-edited values

The #17 and #23 rules extend unchanged. A header segment before the base is accepted and re-serialized base-first, with no flag. Two bases, a repeated header, an unknown head, an empty `header,` and a bad value each drop that segment, flag it, and offer a repair button. An unbalanced bracket drops the whole key. A valid sibling segment is never discarded.

### Order

Within a group, keys serialize in the order of their controls (user, leaning: "more intuitive for the options to serialize in the control order within the group"); the groups themselves may still be reordered. The controls follow GB's Typography sidebar: color, size, weight, style, text alignment, text transform, then background, stripe, border (border last, deferred, no rank). `layout` and `width` stay ahead. **The control order is not final**, so neither is the wire order. This ties the two together, where `docs/gb-constraints.md` §Serialization order is independent of control order: a control reorder is now a wire change.

### What this does not settle

Everything else in the reopen note stays a leaning: the three-state Column Sizing, width values and the `layout:fixed` implication, stripes (counting the header as row 1, the `even` default, color and opacity as separate args), column width, border, background's shape, the styled-table editor preview, the advisory copy, and the control shapes, labels and sticky preview. Ticket 07 stays on hold.

### Superseded in the reopen note

The body-only per-column alignment; the Targets-shaped values; the mock `font:` and `text:` keys, and the per-target text opacity the Fill + Text pair carried; and item 22(v)'s sequence for the text keys (`layout, width, align, border, fill, stripe`).

### Post-ship migration target (CONTEXT.md)

Not written yet, on purpose: CONTEXT.md holds invariants that bind code, and this binds none until it is built. Migrate it when BOTH hold: a shared `head,args` reader exists in code, and FW-27 has decided whether `when` takes the Merged encoding (it was adopted as W2 on 2026-09-30; Merged is only a candidate, so a `when` clause here would be a claim nobody has made). Until the second holds the grammar is table-only and belongs in `tag-reference.md` and the parser's PHPDoc instead. The next free invariant number is I21 (I20 is retired). Draft:

> ## I21 — Items of a valued option are `head,args`, read by ONE reader
>
> A valued option that carries more than one item (a stripe's targets, a property's header override, a `when` condition list) spells each item as a head word followed by comma-separated args typed by value, and joins items with `;`. The first item's head may be omitted when it is the base (`fontWeight:normal;header,bold`); a base-unset value is just the override.
>
> **Rule:** one tokenizer reads every such option, and a head word is a reserved word of its option. Malformed input follows one policy everywhere: an unknown head, a repeated head or a bad value drops that item and flags it, and the other items still render; an unbalanced bracket drops the whole key. Do not give an option its own splitter. Two consumers that split differently would read the same hand-edited value two ways.
>
> Enforced at: the shared reader's PHPDoc (TBD at build). Schema: `tag-reference.md` (TBD). Related: [I13] (malformed wire resolves to nothing, not a sibling), [I17] (three readers must agree).

### Tooling

`tools/preview/tag-string-preview.html` (uncommitted): the styling-encodings group shows the Merged line only, in control order (Targets, Pairs and M+head are cut). `textAlign` is the default respell for `align`. `RESPELL_KEY_MAP` and the older table-v1 rows carry the new order, with `stripe`, `fill` and `border` spelled in the decided `head,args` / Merged grammar (`border` shape only, deferred). `.scratch/table-tag/prototype/styling-group-3.html` generates the same wire (its round 78); since round 86 its `okSize` matches the values above, and only `okColor` still rejects the CSS globals on text `color`.

## DECIDED 2026-10-15 — presentation: the #28 reopen closes

Closes the reopen that began 2026-10-03. With #61 (text and alignment keys), the whole v1 presentation set is decided. Settled #62 (wire and rendering) and #63 (editor) point here, and #28, #29, #31 and #47 flip to this section. The grill ran against `.scratch/table-tag/prototype/styling-group-3.html` at round 87; the prototype is the reference for control shapes and stays gitignored. The working note `presentation-reopen.md` is deleted; the facts still needed from it are under "Measured facts" below.

### The wire (#62)

- **v1 tag-level keys:** `layout`, `width`, the six text keys of #61 (`color`, `fontSize`, `fontWeight`, `fontStyle`, `textAlign`, `textTransform`), and `stripe`. **Column tokens:** `width(…)` plus the six text tokens. No bare flags anywhere; #28's Fork B is retired with the `stripes` and `fixedLayout` names. Deferred, nothing reserved: `border` (FW-159), `background` (FW-160), `verticalAlign` (FW-158).
- **`layout` has three states.** Absent = the theme's own layout; `layout:auto`; `layout:fixed`. `auto` is written, not stripped, because it is not every theme's default: Twenty Sixteen sets `table-layout:fixed` (read off its GitHub master `style.css`, v1.3). #47 closes on the key `layout`; `fixedCols` and `fixedLayout` are retired. FW-141's `fixed` stays free because `fixed` here is a value, not a key.
- **`layout:fixed` with no `width` emits `width:100%`**, since fixed layout does nothing without a table width (core's `.has-fixed-layout` bundles the two). An explicit `width:auto` with `layout:fixed` renders as written and shows advisory 1.
- **Width values, one validator for the tag key and the column token:** `auto`, a number with `px`, `em`, `rem`, `%`, `ch` or `vw`, or `var(--x)` (optional fallback, no nested parentheses). `calc()` is rejected; `fit-content` and `max-content` are not offered until someone asks. Never run a width or a color through `sanitize_text_field`, which strips `%` followed by two hex digits.
- **Stripes** use #61's grammar, `stripe:rows,odd,#eee,40%;cols`. Targets `rows` and `cols`. **The header row is row 1:** `even` shades body rows 1, 3 and so on; `odd` shades the header row and body rows 2, 4 and so on. A bare target means `even`, for both targets. Column stripes run through the header cell. A table with no `<thead>` (every column label empty, `table-tags.php` `$has_header`) counts from its first emitted row. This supersedes C5 and #31's `tbody tr:nth-child(odd)`.
- **Stripe args are typed by value:** a parity word, a color, an opacity `N%`. No opacity means the default opacity, so a solid stripe needs `100%` written. A color that carries its own alpha (`rgba()`, `hsla()`, 4- or 8-digit hex, slash syntax) ignores the opacity.
- **The stripe default is `currentColor` at 5%.** Prior art, read 2026-10-15: Bootstrap 5.3.3 `$table-striped-bg-factor: .05` over `--bs-emphasis-color-rgb` (the text color, which flips in dark mode); Gutenberg trunk's Stripes style is a solid `$gray-100` (#f0f0f0), about 6% black on white and wrong on a dark theme. The text color keeps the stripe visible on any theme. Replaces the plan's earlier `rgba(0,0,0,.04)`.
- **Two settings hold the default:** Default stripe color and Default stripe opacity, on the existing settings page, through the same validators as the editor; an invalid saved value falls back to the built-in pair. They feed `window.bwsTableDefaults` (the editor placeholders, in the pattern of `window.bwsImageSizes`) and the rule `var(--bws-dte-table-stripe, <composed default>)`, so a theme can still override it in CSS.
- **#30's color allowlist gains `currentColor`** for every color argument. Text `color` also takes the shared CSS globals (#61); stripe colors do not.
- **Hand-edited values** follow #17, #23 and #61 unchanged, including the repair buttons.

### Rendering (#62)

- **One rule builder** serves the front end and the editor, so the two cannot disagree.
- **Scope: a class per styled table, `bws-dte-table-<hash>`**, where the hash is a short hash of that table's built rules; the rules queue under the same id, so identical tables share one `<style>` (the queue dedupes by id). A per-request counter was rejected: it cannot dedupe and would churn the page-snapshot baselines. A table with no styling gets no class. Settled #21's "no presentation classes" was the a11y wrapper call, not a ban on a styling class; the caption-gated region wrapper stands.
- **One CSS vehicle: the scoped stylesheet.** No inline `style`, no `<colgroup>`. Column rules are `:is(th,td):nth-child(n)`; fixed layout reads first-row cell widths, so nothing needs `<col>`. Why: kses strips `table-layout` and every function-valued color from an inline `style` (measured below), and one vehicle is one builder. This supersedes ticket 05's per-cell inline vehicle.
- **Stripe color is composed as `color-mix(in srgb, C N%, transparent)`**, except a color that carries its own alpha, which is emitted as written. Stripes paint as `background-image` gradients so row and column stripes compose where they cross (deliberate; Bootstrap's share one variable and do not compound).
- **Front end:** `bws_queue_inline_css()` at `wp_footer`, queued whenever a styled table renders (#29's queue half stands). **Editor (the preview REST path):** the replacement carries its `<style>` inline, just before the table. A trusted user without `unfiltered_html` gets that `<style>` stripped by kses and sees an unstyled table; accepted, not worked around. An untrusted author already gets an empty replacement (measured). Whether the canvas iframe applies an inline `<style>` from a replacement is a build-time measurement.
- **Canvas preview:** the real table when the repeater resolves; otherwise a skeleton, a header row from the column headers plus 3 body rows, each cell showing that column's editor preview text. Every configured string is escaped. Ticketed 2026-10-16 as `.scratch/table-tag/issues/07b-editor-preview-skeleton.md`: built in PHP behind `bwsEditorPreview`, zero rows is the real table not the skeleton, no separate bracketed label, untrusted authors see only GB's "Table" fallback.
- **Nothing emitted sets `display` on a table part** (Roselli: it strips table semantics from screen readers).

### The editor (#63)

- **Three composite controls in one option-group box**, plus the preview: Sizing and Width (`layout`, `width`), Typography (the six text keys at Table and Header), Stripes (`stripe`). Each commits its keys in one `setState` (precedent: `src-chain-control.js` `convertUpdate`), so a group reorder is a registration-order change that `control-order-test.php` sees. The Live Formatting Preview is a display-only lead member. Column styling lives in the column card of `slot-fold-control.js` (Column header and Column Width side by side; Custom Column Text Styles, None, Header, Body, Header and body; column Text Alignment in its own block), writing tokens inside the slot keys.
- **Control shapes are the prototype's round 80 to 87 state:** Column Sizing as side-by-side radios (Theme, Auto, Fixed) with the selected option's help line, beside Table Width; Stripes and Custom Column Text Styles pickers as radios; Typography as a matrix, Table first then Header, three-column grid with the heading above; GB icon button groups for Font Style, Text Alignment (no Justify) and Text Transform; an unset Text Color, Font Weight or Font Size shows the inherited value (`Bold (table)`, a header color placeholder showing the table's color), else Default or Theme default.
- **Inputs.** Color fields are a combobox: site colors as `var(--slug)`, then color names. Width and Font Size are a unit-flag field inside the same combobox; a keyword or `var()` hides the flag. **Every list opens with one hint row** (not an option, shown while typing too), then the pickable values; the same text is a visually hidden `aria-describedby` description, because screen readers skip presentation rows. Font Size lists the keywords, a separator, then the CSS globals, with no captions (GB's Font Weight look). Default units are "natural": `%` for both widths, `rem` for Font Size; the author can switch the unit on an empty field. Stripe color and opacity are one combined control (color field plus a GB-style alpha slider whose thumb is the swatch). The stripe color placeholder is the default value itself (`currentColor`).
- **Advisories (approved copy).** Column Sizing help: Theme, "Whatever your theme does. Some themes fix column sizes, others fit the content."; Auto, "Columns fit their content."; Fixed, "Columns share the table width equally, unless a Column Width is set." Advisory 1: "Fixed column sizing has no effect while *Table Width* is *Auto*. Column sizes follow the content instead." Column Width under Auto: "This width is a hint only. Columns can grow to fit their content unless *Column Sizing* is *Fixed*."; under Theme: "Whether this width is exact depends on your theme. Set *Column Sizing* to *Fixed* to make it exact." Errors: `"{v}" is not a recognized width. Use auto, var(), or a number with px, em, rem, %, ch, or vw.`; `"{v}" is not a recognized color. Use hex, rgb, rgba, hsl, hsla, var(), currentColor, or a color name.`; `"{v}" is not a recognized opacity. Use a number from 0 to 100.`; `"{v}" is not a recognized size. Use a keyword such as small or larger, var(), or a number with px, em, rem, %, ch, or vw.` The five stripe repair messages and buttons as in the prototype; "Opacity is set by the color"; Column Width placeholders Equal share, Fits content, Theme default. A reference to a control label or value is set in italics; the build needs a mechanism for it. Dropped: the Fill note, the header-band advisory (C5's tripwire cannot fire without a header fill) and the contrast advisory, which returns with `background` (FW-160).
- **Column cards stay expanded in v1.** Collapsible cards are FW-148. Feasibility, read 2026-10-06 and not built: a `useState` flag inside `SlotFoldControl` (`slot-fold-control.js`, about line 1169; the file uses no hooks today); a slot with a parse error or a recovered notice must force itself open; a closed slot needs a summary line.
- **Live Formatting Preview: same release, its own build ticket (08), after the controls and the renderer;** neither waits on it. Sticky by default, with a UI-only Sticky switch in its caption row (accessible name "Toggle sticky preview", persisted in `localStorage` with try/catch, default on). Real-modal requirements found in the prototype: `top:-4px` (the modal content's 4px top padding), an opaque background (the option group's is translucent), a z-index above every positioned control except open suggestion lists, which paint over it where they meet (it stays pinned while a combo has focus; unpinning dropped it back into flow, still compact, and left a gap), compact (two body rows) when pinned while keeping its flow height, a white box, bottom fade. Real-modal sticky behavior is still unmeasured.

### Measured facts carried over from the reopen note

- **Theme width:** GeneratePress 3.6.1 `main.min.css` sets `table{…;width:100%}`.
- **kses, 2026-10-07, testbed WP 7.1.2:** `wp_kses_post` on a `<table>`/`<th>` inline `style` drops the whole attribute for `color-mix()`, `rgb()`, `rgba()`, `hsl()`, `hsla()` and a `background:` shorthand carrying one, and for `table-layout` (not in `safe_style_css`); it keeps `#rrggbb`, `#rrggbbaa`, named colors, `var()`, `calc()`, `width`, `text-align`, `color`, `font-weight`, `font-size`. Cause: `safecss_filter_attr` allows only `var|calc|min|max|minmax|clamp|repeat`, transforms and basic shapes before its `[\\\(&=}]` test. GB 2.4.0 kses-es a callback's return only in `sanitize_preview_replacement()` (editor REST preview, users without `unfiltered_html`). Measured on that path: with `unfiltered_html` all fills arrive verbatim; trusted without it, `rgba()` and `color-mix()` styles are stripped and hex8 and `var()` kept; an untrusted author gets an empty replacement.
- **`var()` swatches** resolve in the modal (2026-10-08, testbed, admin and author): GP 3.6.1 writes `:root{--<slug>:<color>}` into the admin document and the canvas iframe. Not measured on a non-GP theme.
- **Escaping:** `%` is inert in GB's `parse_options` (verified GB 2.4.1) and in our grammar.
- **GB and WP control typography,** read 2026-10-07 from WP 7.1.2 `components.js` and `style.css` and the GB 2.4.0 zip, not in a browser: BaseControl label 11px, weight 600, line-height 1.4, uppercase, 8px below; help 12px #757575, 8px above (GB zeroes the margins in the modal); CheckboxControl 16px box, 1px #949494 border, 2px radius, checked in the admin theme color; RadioControl 16px circle, 1px #1e1e1e border, 8px dot; PanelBody title 13px, 600, #1e1e1e, sentence case. Which committed doc owns these once confirmed in a live editor (`docs/gb-constraints.md` or `docs/editor-controls.md`) is still the user's call.
- **Contrast inside an option group** (computed WCAG, 2026-10-15): #757575 help text is 4.41:1 on the group tint #fafafa, under AA for 12px; #50575e is 7.02:1. The build needs a rule in `option-group.js` `buildCss` darkening `.components-base-control__help` inside the group. Existing opacity-based plugin captions pass (5.1:1 to 7.3:1).
- **CSS facts:** CSS 2.1 §17.5.1 paint layers (table, column groups, columns, row groups, rows, cells); `nth-child` counts per parent; `table-layout:fixed` sizes from the table width plus first-row widths and is inert under `width:auto`.

### Follow-ups tracked

One concept per row (user), all in `docs/future-work.md`: FW-159 border, FW-160 background (with the contrast advisory), FW-161 cell padding, FW-162 sticky header row and pinned first column, FW-163 additional typography controls, FW-164 row headers, FW-165 orientation, FW-166 table content filters, FW-167 images in cells. FW-158 (vertical alignment) predates this section.

## DECIDED 2026-10-15 — root-menu labels

Closes the label half of ticket 00's naming pass. Settled #64 points here and supersedes the label in #57. Shown in `.scratch/table-tag/prototype/styling-group-3.html` (rounds 90-93).

- **`inherit` reads "Tag Source".** Generic on purpose: `{{join}}` is meant to adopt the same root, and "Row" is wrong whenever the tag's Source is posts or terms (a post is not row-shaped; CONTEXT.md "Query-loop item"). It stays "Tag Source" under a future orientation setting; only the slot noun follows orientation.
- **A step group reads "Starting from <lead>".** "Starting from Tag Source" in table; the shipped "From Current Context" group is renamed "Starting from Current Context" so the two menus stay parallel. "Starting" says the step begins at the lead rather than copying it. The shipped rename is a user-visible string, CHANGELOG `[Unreleased]`.
- **Slot noun: "Column", a draft.** One string drives "+ Add Column" and the "Column A" card header. It may become Row|Column with orientation (FW-165, §OPEN D).
- **Care with "row":** repeater rows and table rows are both real rows. The iteration over the tag's Source is named by neither; keep the two apart in copy.

## DECIDED 2026-10-19 — borders

> **REOPENED 2026-10-20: the wire, rendering and editor below are superseded wherever they conflict with [§REOPENED 2026-10-20 (borders)](#reopened-2026-10-20--borders-the-key-touches-only-what-it-names) at the end of this file.** What stands: the target heads, tag-level only, the key order, malformed input following #17/#23/#61, and the spike's measured facts in ticket 10 §Built.

Closes FW-159's open design and adds a border radius. Settled #65 points here. Decided in a grilling against GeneratePress 3.6.1's own table CSS and the existing stripe and text-key machinery; the landing is gated on a spike (ticket 10, below), so this records the design, not a commitment to ship it in v1. The earlier "no preset" and "border last, no rank" statements (#29, #61, #62) stand: the key is still tag-level, still deferred from the 2026-10-15 cut, and ranks last among the keys.

### The fact that shaped it

GeneratePress 3.6.1 `main.min.css` already draws a border on every cell: `table,td,th{border:1px solid rgba(0,0,0,.1)}`, `table{border-collapse:separate;border-spacing:0;border-width:1px 0 0 1px}`, `th,td{border-width:0 1px 1px 0}`. A `border` key cannot be additive on such a theme: `border:rows` would still show the theme's column lines. Read from the stylesheet, 2026-10-19; not measured in a browser.

### The wire (#65)

- **Three states, like `layout`.** Absent: the theme's borders are untouched. `border:none`: the theme's borders are stripped. Anything else: the key owns EVERY cell edge, so each edge it does not name is zeroed.
- **Heads:** `outer` (the table's four edges, header top included), `rows` (lines between adjacent body rows only), `cols` (lines between adjacent columns, through header and body), `header` (the one line under the header row), and `none` (valid only as the sole item). No `all`. `header` is the same word the text keys use and respells through `headerContext`. A table with no `<thead>` draws nothing for `header`, silently, as stripes count from the first emitted row.
- **Args typed by value:** a width (a length or `var()`), a style (`solid`, `dashed`, `dotted`; GB's picker list minus its own *None*, which is the radio below; `double` and the exotic styles are out), a color (the existing validator, `currentColor` included), and an optional `N%` opacity composed with `color-mix`, as stripes are. A bare head draws `1px solid currentColor` at 20%, built in, with a `var(--bws-dte-table-border, …)` hook so a theme can override it. No settings fields (stripe's default needs two; this adds none until someone asks) and no width keywords.
- **Tag-level only.** No column token, no header-override segment: the header's line is already a target, and a column that wants its own lines can follow later as a token without changing this wire.
- **`borderRadius` is its own key,** one length (`px`, `em`, `rem` or `var()`; no `%`, which on a table is a fraction of its box and comes out elliptical), serialized right after `border`. One shared value, no per-corner control and no link toggle. Why a key and not a `radius` head: #61's rule is "a key is a property", the four heads are all LINES and a radius is not, and a head would make `radius` a reserved word of `border`.
- **Order:** `border` after `stripe`, `borderRadius` after `border`. The ranks are assigned in ticket 10 and pinned by `serialization-order-test.php` and `control-order-test.php`.
- **Malformed input** follows #17, #23 and #61 unchanged (drop the item, flag it, repair button; an unbalanced bracket drops the key), plus two additions: `none` beside another item drops the `none` item and flags it, and two args of one type inside one item drop that item.

### Rendering (#65)

- **The separate border model, `border-collapse:separate;border-spacing:0`, whenever `border` is present.** An earlier answer in the grilling chose `collapse`; it was withdrawn because `border-radius` does not apply to a collapsed table (CSS Backgrounds, read from the spec and still to be measured), and because single lines are available in the separate model: the table's own border draws `outer`, `cols` is a right border on every cell but the last column, `rows` is a bottom border on body cells in every row but the last, `header` is a bottom border on the header cells. This is the model GeneratePress already uses. Sticky cells (FW-162) fare better in it than under collapse.
- **`borderRadius` alone emits only `border-radius` and the corner clipping,** never the model change: switching a collapse theme's table to the separate model would double its per-cell borders, so the switch stays tied to `border` being present. Without `border`, `borderRadius` renders as written and shows an advisory ("Rounded corners show cleanly only when *Borders* is set to *Lines*."), the way `layout:fixed` beside `width:auto` renders as written. Whether the clipping is `overflow:hidden` on the table or per-corner-cell radii is decided by the spike.
- **One rule builder** (`bws_table_rules()` and its JS twin) serves the front end and the editor. Everything rides the per-table scoped stylesheet, as the rest of presentation does; nothing sets `display` on a table part.

### The editor (#65)

One Borders composite in the existing option group, right after Stripes: a three-way radio *Theme / None / Lines* (Column Sizing's pattern). Under *Lines*, one row per target, each with an enable checkbox; enabled, it shows Width (the unit-flag combobox), Style (GB's icon dropdown: *Default* = solid, *Solid*, *Dashed*, *Dotted*) and the stripe composite's color-plus-opacity field. One Border Radius field sits beside the Outer row. One `setState` commits the keys; per-target args round-trip exactly, so a hand-edited value is never normalized away; with *Theme* or *None* selected the radius field is hidden and a hand-edited `borderRadius` is kept. The Live Formatting Preview keeps its theme-like baseline while `border` is absent and draws only from `bwsTableStyle.rules()` once present. All new copy is surfaced for review before it is final.

### Ticket 10, and the renumbering

A new build ticket, `10-borders.md`, runs the spike first and builds only if it is clean. Fixtures became 11 and ship 12 so the fixtures ticket, which "is written against the final wire", still follows every build ticket; the citations of both were repointed in the same edit. Border adds no matrix rows itself: ticket 11 writes the border rows and visible blocks, and re-captures the snapshot baseline once.

### Does not settle

The spike's four measurements (radius clipping, stripe paint against the border, single non-doubled lines on GP and a collapse-model block theme, `:not(:last-child)` on ragged rows). Whether the `var()` hook carries the default color only or the whole border value. The copy for every new error and advisory. `borderRadius` beside `border:none` is legal and only rounds the clipped fills.

## REOPENED 2026-10-20 — borders: the key touches only what it names

Settled #65 reopened by the user on 2026-10-20 and re-grilled to a shared understanding (twelve questions, all answered). **Status: design agreed; prototype tuning pending.** The user is working the details in the prototype (`.scratch/table-tag/prototype/styling-group-3.html`, ROUND 112 and 113) and this section is to be reopened next session, so nothing here is final copy or a commitment to ship in v1: the landing is still gated on a build spike (below). The target heads, tag-level scope, key order and malformed-input policy of §DECIDED 2026-10-19 (borders) stand. The spike results in ticket 10 §Built stand for the separate model.

### Why it was reopened

The 2026-10-19 design made a present `border` key own every cell edge and gave every drawn line a built-in `1px solid currentColor` at 20%. That pins the table to an opinionated look the author never asked for: turning off only the outer line meant restating a color for the rest. The author's intent is "keep the theme unless I say otherwise, and force something only where the theme cannot work with what I set".

### The wire (supersedes #65's "Wire")

- **The key touches only what it names.** Absent: the theme's borders. A line the key does not mention is never touched. `border:outer,none` turns the outer line off and nothing else.
- **The first head-less segment is the base,** as in `color:base;header,x`. It carries color, opacity, and optionally width and style. A base with a width or style draws all four lines. A color-only base recolors the theme's existing lines and draws nothing new. A first segment that opens with a head word is a line, anything else is the base.
- **A `head,args` segment is that line, carrying width and style only.** Color and opacity are set once, table-wide, in the base; a color or opacity inside a line segment is dropped, flagged and offered a repair like any bad value. Per-line color can be added later without breaking a stored wire. Opacity with no color uses `currentColor` as its base. Nothing else gets a default color.
- **`none` is a fourth style value,** valid in the base and in a line segment. A base style `none` turns every line off and a line segment then adds lines back (`border:none;rows`). There is no `none` head and no "sole item" rule. An empty base with no overrides is written `border:solid`, because an empty key means Theme.
- **Fallback:** an unset width or style on a named line that is not `none` falls back to `1px solid`. Color never gets a default. The reason is measured: a named line must be visible in a borderless theme, and a style with no width resolves to `medium` (3px) in CSS.
- **`borderRadius:outer,8px`,** its own key right after `border`, one length (`px`, `em`, `rem`, `var()`; no `%`), written in the same `head,args` grammar. `outer` is the only head in v1. **A bare `borderRadius:8px` is reserved for "every cell"** and is not built: it is dropped and flagged with no repair button, because the reader cannot tell whether the author meant cells or the table. The flag names the working spelling.

### Rendering (supersedes #65's "Rendering")

- **A named line owns both edges of its boundary, and `border` never changes the table's border model.** Each target uses longhand properties only (never the `border` shorthand), so the theme's style and color survive where we set nothing. The line is set on one side (right or bottom) and the opposite side is zeroed: `cols` sets the right edge on every cell but the last and zeroes the left edge on every cell but the first; `header` sets the bottom edge on header cells and zeroes the top edge on the first body row; `rows` sets the bottom edge on body rows but the last and zeroes the top edge on rows but the first; `outer` sets the table's own border and zeroes the perimeter cells' outward edges. A line turned off is the same selectors at width 0. Unnamed lines are never touched.
- **Radius is the one exception that forces a model:** it forces `border-collapse:separate;border-spacing:0` (radius does not apply to a collapsed table) and removes the duplicated cell edges that a collapse theme's per-cell borders would otherwise draw twice, by zeroing the left edge on every cell but the first and the top edge on every body row. In GeneratePress this changes nothing. The outer line follows the curve whenever it is drawn; the corner clipping is `clip-path` on the row groups, as the ticket 10 addendum decided.
- **Radius advisory,** shown whenever no outer line is drawn (no key, a color-only base, or an outer line whose style is `none`): "Fills are rounded. Draw the outer line to round the border too." The earlier "your theme's own borders stay square" is wrong in a separate-model theme, where the table's own top and left border takes the radius.

### The editor (supersedes #65's "The editor")

- **No Theme / None / Lines radios.** Nothing set is no key, which is the theme. A *Use theme borders* button clears it. A base block (Width, Style, Color, Opacity) sits above four line overrides, each with a checkbox. The checkbox means "override"; it names the line only when the base draws nothing.
- **A line override shows Width and Style only** (Color and Opacity are the base's).
- **Radius lives inside the Outer override,** a plain *Radius* field. Unticking Outer clears the radius. A hand-edited `borderRadius` with no outer line opens an unticked Outer row holding the field, so it can be cleared, with the advisory.
- **The Live Formatting Preview** keeps a theme baseline while the key is absent, now switchable between GeneratePress's real rules and a collapse theme (the prototype's *Theme borders* toggle, ROUND 112), and draws from `bwsTableStyle.rules()` once the key is present.
- Draft copy, all surfaced for review: Overrides help "Tick a line to override the settings above. To draw only some lines, set the style above to None, then tick them."; the radius advisory above; the bare-radius flag "\"8px\" on its own is reserved for cell corners, which aren't supported yet. To round the table's corners, use outer,8px."

### Measured facts carried over

All in headless Chrome 155 at 3x by a scripted pixel scan (agent-run, reported by the agent and summarized here, not re-run by hand); Firefox and Safari were not measured. Rigs, generator, analyzers and results are kept in `.scratch/table-tag/tools/spike-borders-2/` (`collapse/` = collapse-model cases, `edge-ownership/` = the scheme above).

- **GeneratePress 3.6.1 is a separate-model theme** (read from `main.min.css`, 2026-10-20): `table,td,th{border:1px solid rgba(0,0,0,.1)}`, `table{border-collapse:separate;border-spacing:0;border-width:1px 0 0 1px}`, `td,th{border-width:0 1px 1px 0}`. The table draws only top and left, cells only right and bottom. The prototype's old "collapse" baseline for GP was wrong.
- **In a collapse theme the outer line is the cells' perimeter, not the table's border.** `table{border-width:0}` removes nothing; the perimeter cells' outward edges must be zeroed. Zeroing only one side of a cell boundary leaves the neighbor's edge drawing the line (`:not(:last-child){border-right-width:0}` does not remove a column line). A `2px` table border over the theme's 1px perimeter wins cleanly. Forcing a collapse theme into the separate model doubles every inner line to 2px and grows a 156x142 auto table by about 2px wide and 3px tall.
- **The edge-ownership scheme works in both rigs:** each line off, each line drawn alone at 3px dashed red, all four off then `rows` at 2px, and a color-only change on every edge; unnamed lines were unchanged from the baseline, nothing doubled, and 3px lines met 1px theme lines without thickening. Style-only `outer` differed between rigs (GP drew only top and left; the collapse rig drew four 3px edges), which the `1px solid` fallback fixes. Radius applied in the separate rig and not in the collapse rig.
- **Cell radii:** per-cell `border-radius` works in the separate model (a filled pill needs `border-spacing` above 0) and does nothing in a collapsed table; at spacing 0 adjacent fills touch and corners leave wedge gaps, so cell radii do not combine with the single-line scheme. This is why the bare form is reserved, not built. Read from the spec and the earlier spike, not measured as pills.

### Not measured, and the build spike

Unmeasured: the base carrying width or style on all four lines at once (only single targets were run); radius in a collapse theme with the duplicated-edge cleanup, including how the outer line looks at the curve and with a caption; a radius-only table in GeneratePress (the user sees the corners round in the prototype, which is not a measurement); Firefox and Safari. The build spike runs these before ticket 10's step 2.

### Not settled

(The prototype's rule builder was moved onto this section's semantics on 2026-10-20, ROUND 117; the three build-spike cases above are measured in ticket 10 §Built, "Build spike".) The copy above. Whether ticking Outer just to reach Radius under a base that already draws all four lines is too much friction (it changes nothing visible until edited). The user's remaining prototype detail work.

### Prototype choices (2026-10-20, ROUND 114)

The three banner toggles of rounds 105 to 113 are decided. Each choice is what the plan section above already describes; the prototype's defaults were set to it, so nothing about the wire or the editor changes, and this subsection only closes the options.

- **Override Color / Opacity: "cut".** A line override carries Width and Style only; Color and Opacity are set once in the base. The per-line alternative is not built; per-line color can still be added later without breaking a stored wire.
- **Radius: "Inside Outer".** The Radius field lives in the Outer override; unticking Outer clears it. The "Above Overrides" placement is not built.
- **Lines shape: "Table-wide + specific lines".** The base (Table Borders) applies to every line and ticked lines override it, additively. The "Table checkbox + lines" and "All on, linked" shapes are not built.
- **Stripes and Custom Column Text Styles pickers: checkboxes** (prototype round 110). Stripes = None | Rows | Columns, Text Styles = None | Body | Header, three checkboxes each; None is checked exactly when neither other box is, ticking it clears the others, unticking it directly does nothing; both ticked is the old combined "Rows and columns" / "Body and header" option, which goes away as a separate choice. This is outside Borders and changes shipped control code (`table-style-control.js`, built under ticket 07 as radios), so it is its own ticket: `07c-picker-checkboxes.md`. The wire does not change.

- **Opacity suggests nothing** (2026-10-20, the user). The 20% was part of the rejected built-in `currentColor` at 20%; the reopened design keeps the theme's color where nothing is set, so there is no default opacity: an unset opacity means the color as-is (the readout is a dash), and the slider carries no default or start-position hint. A base opacity with no color rides `currentColor`. Not suggested, not applied, never on the wire.
- **`currentColor` leads every color list** (prototype round 119): the first option, directly under the format hint and ahead of Site colors. The built control (`table-style-control.js`, `colorOptions()`) has it inside the Color Names group; moving it is a one-line reorder for the build, owned by ticket 07c with the pickers since both are shipped ticket-07 control code.

- **No *Use theme borders* button** (2026-10-20, the user; supersedes the editor bullet above that has one). Clearing every Borders field is how you return to the theme; the note under the heading reads "Following your theme..." while nothing is set and "Replaces your theme's borders with the lines set below." otherwise.

- **A radius ticks Outer** (prototype round 120, 2026-10-20, the user; supersedes the editor bullet's "opens an unticked Outer row holding the field"). A radius, typed or hand-edited with no outer line, ticks Outer and shows its Width / Style / Radius controls, and Outer's style then goes to Solid if it was Default (own and base both blank). Unticking Outer clears the radius. So the control can no longer show a radius with Outer unticked, and a hand-edited `borderRadius:outer,12px` with no `border` key reads in the editor as `border:outer,solid` plus the radius (the key is written on the next edit). The "radius-only table" is still a legal wire the reader renders (fills rounded, advisory shown); only the editor no longer authors it. This also answers the earlier friction question about ticking Outer just to reach Radius.

- **Less help text** (prototype round 123, 2026-10-20, the user). No help line under *Borders* or under the overrides, and *Overrides* is retitled *Override Specific Borders*. GB carries very little help text, so the control follows it; the "set the style to None, then tick lines" hint is dropped, not relocated. This supersedes the editor bullet's draft Overrides help copy.

The prototype's losing variants were cut the same day (ROUND 115: code, state and banner toggles; the Theme borders toggle stays). ROUND 115 also tries three layout tweaks, none decided: Overrides as Stripes' matrix (one Width / Style header row, line names centered on the fields), Outer's Width / Style / Radius on one row, and no inset on the Stripes and Custom Column Text Styles sub-options.
