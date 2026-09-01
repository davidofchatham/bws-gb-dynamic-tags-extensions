# Future work tracker

**Not a roadmap for future work.** No committed timeline on anything below. One exception: an **In-flight** row names a target version, but even that holds no progress detail — the row points at the branch / plan / unreleased CHANGELOG, which own the real build state. This is a single visible index of non-bug work — future AND in-flight — one heading block per **`FW-N` id**.

## Row shape

Each item is a `### FW-N — <title>` heading followed by a fixed set of labeled lines:

- A **description** paragraph (1-3 sentences): what the item IS. Stable, rarely re-edited — not current state, not history.
- **Detail home:** where the design/rationale + implicit certainty (concept vs planned) live — a GH issue, a `.scratch/plans/*.md` file, a `docs/design-history/*.md` file, or a memory note. Never duplicated here; open the link for the full story.
- **Target:** *(In-flight rows only)* the version the work is landing in, or `—` where no release carries it (tooling/instrument work has no landing version).
- **Progress:** fact-based, present-tense, permanent-once-true statements only — "half X shipped", "measured on Y", "condition Z is met". Always present, even if just "Not started."
- **Open:** what's still undecided or unbuilt, when there's a real done/open split. Omitted when there's nothing beyond Progress worth stating separately.
- **Blocked by: / Interacts with:** unchanged in meaning from the old table columns — see below.

## The no-status-column rule, revised

The old rule banned tracking phase, commit, or percent-done in a cell, because that surface drifts — a phase name or a remaining-task count needs re-touching on every build session, and the FW-52 staleness this rule was written against is what happens when nobody does. **That reasoning still holds and still bans exactly that kind of statement.** What changes is the conclusion: a `Progress:` line is now allowed, but only for statements that are true FOREVER once true — a measurement taken, a threshold met, a half shipped. It may NOT hold a live estimate, a phase name, a percent-done figure, or a remaining-task count; those still drift, still need re-touching, and still belong in the branch / plan / unreleased CHANGELOG that owns the real build state. The crux: "half (a) shipped 1.15.0" is a fact that stays true forever — nothing about it goes stale. "Phase 2 of 3, ~60% done" is not — it goes stale the moment work continues, whether or not anyone edits the line. That distinction, not the presence of a Progress line, is what keeps this from repeating the FW-52 mistake.

- **`FW-N` ids are permanent.** Cross-refs use the id, never prose, so a reworded row never orphans a reference. A shipped/cut row's id retires to the Closed/Retired ledger and is never reused or reassigned.
- **Bugs do NOT go here** → GitHub Issues (`bug` label).
- **No detail duplication.** A row states that something exists, what gates it, what it touches, and where to read more — it does not carry the design itself. Certainty (concept vs planned) is read from the detail home, not stated here.
- **Lifecycle is the SECTION, not a line.** A row starts in a future section, moves to `### In flight` when committed build work begins, and moves to Closed / Retired on ship. It keeps its `FW-N` and its Detail-home line through all three — only the section changes. This coarse move is the only progress signal a row's SECTION carries.
- Some homes are local/hidden (`.scratch/plans/` is gitignored, memory files sit outside the working dir). This tracker is the tracked, reviewable surface over them. Migrate detail into `docs/` opportunistically; until then the link still points home.

> **Agent pickup:** a future-section row is startable when its `Blocked by:` line is `—` or every `row:`/`ship:` gate it names is satisfied. `decision:`/`code:` gates are human-resolved — don't auto-start those. `Interacts with:` never blocks. An **In-flight** row is already being built — do NOT pick it up as new work; read its Detail home for real state before touching it.

`Blocked by:` uses the same typed vocabulary as before: `row:FW-N` (another row) · `ship:X.Y.Z` (a version — satisfied once shipped) · `decision:<what>` (an open choice) · `code:<condition>` (a code state) · `—` (unblocked). A blocker states a CODE FACT, never a scheduling preference — "this cannot land until X", not "do this after X" — so a rescan may re-derive one from the code and swap it without asking what the ordering was meant to achieve. `Interacts with:` is softer coupling (reshapes / reshaped-by / ship-near) as `FW-N` ids + external `#issue` refs — never a gate.

## Trackers

### In flight

Committed build work. **Pointer-only, like every other row** — the branch / plan / unreleased CHANGELOG own the real build state; a row here names only that the work is live, what it touches, where to read it, and its target version. A row lands here from a future section when build starts and leaves for Closed / Retired on ship. **A row may sit here with no Target** — no release carries a harness or an instrument fix, so `Target: —` is the honest statement ("no release carries this"), not a gap.

### FW-78 — Migration-replay diff can't tell a repaired row from a vanished one

Pattern-cache repair removes stale shadow wire, so a migration run's B-side census legitimately holds fewer rows than the A side rendered — the diff reads every removed string as a hard failure rather than a repair.

Detail home: GH #117; `harvest-replay/README.md` §The replays

Target: —

Progress: Removal-artifact half fixed, measured on real wire (Site H: fewer strings recorded with the trigger firing than deferred, confirming the defect).

Open: Render half needs a full two-clone replay re-run (replay-A, converter, replay-C, diff --map --removed) — ENV-repo work, not yet driven.

Blocked by: —  •  Interacts with: FW-96

### FW-96 — Dependency replay over the harvest corpus

The third replay axis: our build and the wire both held fixed, one DEPENDENCY's version varied between the two renders (`tools/harvest-replay/README.md` §The replays).

Detail home: `tools/harvest-replay/README.md` §The replays

Target: —

Progress: Both halves shipped 2026-08 — the env half (built 2026-08-24) records which dependency version was installed on each arm and asserts the two sides disagree before any diff is read; this repo's half (landed 2026-08-28), `diff-replays.php --dependency-replay`, requires identical build identity on both sides and moves the varying axis to that record. Exercised live only for GenerateBlocks so far (2.4.1 vs 2.4.0, 9962 renders per arm, CHANGED 0).

Open: GB Pro, GB Query Enhancements and ACF Pro are supported by construction but never run; the licensed add-a-version path is unexercised. Inherits the harvest-side stratification caveat — a clean diff says nothing about a context-kind stratum the sample never drew.

Blocked by: —  •  Interacts with: FW-78 (the other half of the same change), FW-99 (the other consumer of a version record)

### Correctness, Consistency, Architecture

### FW-3 — Route datetime through the L1/L2 seam

Route datetime reads through the same L1/L2 source-resolution seam as text/title, retiring the id-arg param-overload contradiction across the four datetime cores.

Detail home: `.scratch/plans/fw3-datetime-seam.md` (half (a) shipped record + half (b) framing); payload half's record `docs/design-history/traversal-convergence-fw49.md`

Progress: Half (a) shipped 1.15.0 — term-ambient parity, the resolved-source rethread, and the `bws_datetime_coerce_read_target()` compat shim for legacy scalars; bare datetime tags on a term archive read the term's date field. The payload half of (b) shipped 1.16.0 as part of FW-49 — the four datetime call sites ride the shared `bws_collect_value_list()` fold and `bws_datetime_collect_list()` is deleted.

Open: Full seam routing (datetime VALUE reads going through `bws_resolve_field_values` rather than the cores) still needs the field-object-formats read the seam does not currently expose.

Blocked by: decision:field-object formats through the seam  •  Interacts with: FW-43, FW-35

### FW-7 — Collapse bws_read_field's internal loop/term-archive resolution

`bws_read_field()`'s internal loop/term-archive inference (`field-helpers.php:269-296`) duplicates resolution the source factory already does, for the four families still on the falsy-id path (content/text cores, image, datetime, try_'s arms).

Detail home: `docs/design-history/traversal-pipeline.md` §Post-Phase-1 convergence

Progress: 11 call sites in `includes/`, of which 4 families still depend on the inference. The factory already resolves both halves this would replace; deleting the inference before a `meta_row` base arm exists would blank the repeater-row path (§F9c.1 pins this). 1.18.0 turned this from tidiness into correctness — two resolvers now answer "which entity does this tag read" and only one is gated, producing a reachable bug on the un-migrated path (filed as #122).

Open: Deleting the inference is gated on FW-74 landing a `meta_row` base arm first.

Blocked by: row:FW-74  •  Interacts with: FW-8, FW-74, #122

### FW-8 — Fold bws_reliable_term_context_detection into bws_capture_ambient_signals

Two term-detection implementations coexist — a 5-tier one in taxonomy-helpers and the ambient-signal factory — and the factory is the intended single home.

Detail home: `docs/design-history/traversal-pipeline.md` §Post-Phase-1 convergence

Progress: Not started; excluded from Phase 1 because `TaxonomyTerm::resolve_id` and the `term_` modifiers depend on it, which would have widened the blast radius mid-refactor.

Blocked by: row:FW-7  •  Interacts with: FW-33

### FW-9 — Context-aware base tags — the deferred residue

The residue of the context-aware base-tag work after the five query-context kinds (date / search / PTA / 404 / latest-home) shipped: the deferred per-kind option surface and the datetime archive-context semantics.

Detail home: GH #19 + `.scratch/plans/context-aware-base-tags.md`

Progress: The five query-context kinds shipped 1.19.0 (term kind 1.14.0, author kind 1.15.0 — see FW-47 for its residue). The per-kind option-surface gate was dropped in the 2026-08-29 grilling and the kinds shipped on core's values.

Open: The deferred option surface (404 title/text override, search format — also FW-105's home, taxonomy-label prefix, date format, latest-home title source) and the datetime archive-context semantics (`use:archive_range` era).

Blocked by: decision:option surface scope  •  Interacts with: FW-33, FW-47, FW-105

### FW-38 — Explicit registered_by + lifecycle entry fields (retire the callback proxy)

Replace the callback-presence proxy that box-placement leans on today (plus its `prefix_removed` bolt-on) with explicit `registered_by` (internal vs external plugin id) and `lifecycle` (`active` | `deprecated` | `removed`) fields recorded at `register()` time.

Detail home: memory `project_registered_by_lifecycle.md`; CONTEXT.md I10 (interim state it replaces)

Progress: Not started. Feeds portal-system coordination (external declares its own `registered_by`/`lifecycle`; handoff in bws-portal-system's `.claude/plans/prefix-removed-handoff.md`).

Blocked by: —  •  Interacts with: —

### FW-43 — Selecting half of the shared value fold

`bws_select_first_value()` — the first-non-empty-wins selecting half of the shared value fold, paired with the already-shipped combining half (`bws_collect_value_list()`).

Detail home: `docs/design-history/multi-step-slot-sources.md` §What FW-43 keeps; build-locality decision `docs/design-history/combine-text.md` §Build locality; memory `project_open_refactors.md`

Progress: Shipped 1.18.0 as `bws_read_bounded_sources()` (field-helpers.php), extracted from try_'s emit loop; both the content/permalink/image term loops and the try_ slot emit consume it. The content callback's post-side collapse to one id is confirmed intentional (`{{content}}` is not list mode because a value carries no identity of its own — an assembly decision, not a resolver one; recorded on #118, a separate editor-surface concern). The #108 coverage gap is closed — arm wiring lives in `includes/helpers/try-slot-arms.php`, pinned under mutation by `try-slot-arms-test.php`.

Open: Un-hardcoding the `same`-use prepend. The shared emit the arms feed has no pure-harness coverage of its own; pinned only by `fold-test-matrix.md` and `text-test-matrix.md` §T8.

Blocked by: —  •  Interacts with: FW-49 (the combining half), FW-71

### FW-47 — Author-kind permalink + image analogs

The 1.15.0 author kind shipped `title`/`content` only; `{{permalink}}`/`{{image}}` on an author archive render empty (honest gap) pending two design calls.

Detail home: `.scratch/plans/context-aware-base-tags.md` §Tag Dispatch (author rows) + `bws_base_user_analog_read` PHPDoc (the two deferred cases)

Progress: The permalink soft gate — a non-ambient user source — is MET since 1.19.0: query-loop item recognition now reads a user item as a user (#123), and inside such a loop `{{permalink}}` is no longer circular (it used to resolve as the POST'S permalink instead, per loop-item-wins-over-ambient). The 1.19.0 ambient-analog collapse (`bws_base_ambient_analog`) also dropped the build cost for either analog to one reader case plus a carve-out flip in `bws_base_user_analog_read()`. **Why this row states the new fact rather than the old wait condition:** the doc/code drift here was resolved CODE-ward, and the code change WAS the decision — `3ed3ce1` deliberately made a user loop item resolve as a user, which is what satisfied the gate this row was written to wait on. Nothing was left unfinished against this row's text; `git log` answers the question the drift rule exists to ask.

Open: image — no clean intrinsic analog (parity with the #29 term-image gap); the avatar (`get_avatar_url`) candidate adds external Gravatar HTTP + privacy surface and isn't "featured-image" semantics (a `use:key` ACF user-image field already covers key-mode). permalink — whether a user query loop alone is enough to ship on, or it still waits for a user source the wire can NAME (FW-48's `src:author` hop, `src:ref`→user, or FW-39's ID source).

Blocked by: `decision:image-avatar-analog` (the permalink soft gate is satisfied since 1.19.0; FW-48/FW-39 would add more non-ambient user sources)  •  Interacts with: FW-9, FW-48, FW-39, FW-101, FW-113, #123 (user loop items made the first non-ambient user source)

### FW-67 — Retire the bws-term-hop control-type carrier (the last retired word)

A control `type` string is a registered identifier the editor JS matches on, so `bws-term-hop` cannot simply be renamed like prose — it moves in lockstep with its file and every registration naming it, or it stays.

Detail home: GH #80 §Out of Scope (Phase C, parked); `docs/design-history/per-step-limit.md` §OPEN (`bws-term-hop` control type); vocabulary decision V1 in `docs/design-history/src-chain-encoding.md` §VOCABULARY

Progress: Every base tag has already retired the carrier. It survives only where the flat `srcTermIn` control still registers — the `term_`/`view_` modifier families and `{{table}}` — because they take `bws_base_traversal_options()` raw with no chain option to gate `bws_drop_chain_flat_options()` on.

Open: The likely outcome is deletion, not rename — the carrier dies with `register_modifier()` (FW-70's phase C) or with `{{table}}` taking a chain source (FW-53).

Blocked by: row:FW-70, row:FW-53  •  Interacts with: FW-33

### FW-74 — A base-tag arm that consumes a REPEATER-ROW source

`src(rows,<field>)` resolves to a repeater ROW, and no base-tag arm reads one — `bws_base_text_resolve_value()` dispatches on site / ambient-term / ambient-user / term / post and a `meta_row` kind falls through, rendering empty. Unimplemented wire, not inert wire (`CONTEXT.md` §Language) — `{{table}}` wants exactly this as its cell-read context.

Detail home: `.scratch/plans/table-tag.md` §FW-74 — the repeater-row arm

Progress: The read layer already carries a live `case 'meta_row'` (`bws_read_resolved_source()`, `traversal-pipeline.php`); only the arm is missing. Design recorded ahead of the build: branch on the WIRE kind (`bws_fold_chain_resolution()`), never on `$base['kind']` — a resolved `meta_row` off the ambient context is GB Pro's flat repeater row and must keep falling through to the post arm; §F9c pins the distinction. #105 deliberately does not flag such a chain as broken, since the tag becomes correct without the warning needing to change.

Open: Rides the `{{table}}` finalization, where the kind gets a consumer worth having.

Blocked by: row:FW-53  •  Interacts with: FW-53, FW-71, FW-7, #105

### FW-98 — The stated-fallback emit is written out ten times

`'' !== $fallback ? bws_gb_tag_output( $fallback, $options, $instance ) : ''` recurs verbatim at ten sites, though an owner for exactly that shape already exists (`bws_base_stated_fallback()`, extracted 1.17.0 for the same reason).

Detail home: `.scratch/plans/fw98-fallback-emit-consolidation.md` (new — site list + per-site read notes)

Progress: Not started.

Blocked by: —  •  Interacts with: —

### FW-102 — Report the fallback re-application upstream to GB Query Enhancements and GB Pro

An extension re-applying a tag's `fallback` when its output looks empty re-applies one the owning tag already consumed, and tests `empty()` rather than `'' ===` so a bare `0` is replaced too — a defect not unique to this plugin. GB Pro's `loop_item` carries the identical exposure.

Detail home: `docs/design-history/query-extension-interop.md` §Owed by you

Progress: This plugin's own output boundary (1.19.0) strips the consumed options before they leave, so nothing here blocks or is blocked on the report going out. The user chose to send the report after the 1.19.0 release rather than before.

Blocked by: —  •  Interacts with: FW-98

### FW-104 — The deprecation-mode radio is dead UI, and wiring it up would disable externally-registered families

The settings page's Keep / Suppress / Disable radio stores a value nothing reads; finishing it is a content-blanking trap because the stored mode applies per GROUP, and since 1.17.0 an external plugin can enroll a live family into that group's pool (bws-portal-system's nine `view_*` tags already do).

Detail home: GH #110

Progress: Fresh installs seed both groups to `disable` (the read-path fallback is `keep`), so finishing the radio would take externally-registered families dark on upgrade with no conversion run and no warning. Four directions were identified at filing, none chosen.

Open: Which of the four directions (exempt externally-registered entries, go per-family/per-entry, treat a stored `disable` as applying only to entries present when it was saved, or delete the dead accessors); whichever lands, `docs/plugin-integration.md` §9 must state what registering an entry enrolls tags in.

Blocked by: decision:which of the four directions  •  Interacts with: FW-38 (`registered_by` — the registrar identity a gated version needs), FW-33

### FW-106 — The resolved chain is re-derived, not passed

"Which entity does this wire read from" is answered repeatedly within one render, from the same string, by three differently-named, non-memoizing functions (`bws_base_src_resolution`, `bws_fold_src_root_token`, `bws_resolve_base_source`).

Detail home: `.scratch/plans/architecture-review-2026-08.md` §FW-106

Progress: Traced 2026-08-29 — a bare `{{text}}` on a term archive re-parses the chain 3x across 9 files / 14 hops; a folded `try_` slot re-parses 5x per slot, including once on a string the seam had just emitted. Does not re-open ADR 0002, which governs L1's resolved SOURCE (a binding); this concerns the CHAIN (a parse result).

Open: Fix shape is parse-once-pass-down, with the three names becoming accessors on the record. Largest blast radius of the ten review candidates, and reads better after FW-113.

Blocked by: —  •  Interacts with: FW-113 (removes dispatch sites that would otherwise each need threading), FW-107, ADR 0002 (scope, not conflict)

### FW-107 — The try_ slot resolver has no seam under it

`BWS_TRY_SLOT_ARMS` is deep and mutation-verified; its only consumer is a large anonymous closure with no harness of its own, and one branch overwrites the table's refusal with the post arm, defeating the contract the table exists to state.

Detail home: `.scratch/plans/architecture-review-2026-08.md` §FW-107

Progress: Not started. Fix shape: the arm carries a callable rather than a string another file switches on, with per-slot resolution lifted into a named function. Any fix must absorb `try_query_fn`'s shape asymmetry (it takes `$base`, not an entity id).

Blocked by: —  •  Interacts with: FW-106, FW-113

### FW-108 — deprecated-tags.php: split the public API, declare the migration order

Seven unrelated concerns share one file, with three independently landable parts: (a) the documented third-party migration-root API forces eager loading of the whole legacy wire corpus; (b) `bws_register_option_migrations()` encodes a total order over its entries in comments only, with `TagConverter::scan()` independently re-deriving the same order; (c) a wide entry record with several mutually-exclusive shapes and rules the shape can't express.

Detail home: `.scratch/plans/architecture-review-2026-08.md` §FW-108

Progress: Not started. A wrong migration order can silently downgrade an image on customer content — the file already describes that failure mode.

Blocked by: —  •  Interacts with: FW-38 (`registered_by`/`lifecycle` reshapes the same entry record)

### FW-109 — The slot seam answers through three channels

`bws_fold_slot_chain_options()` holds real, single-owned rules behind an interface that leaks three ways — a return array, a `$skip_reason` out-param, and a `$limit_default` the caller must clamp and write back.

Detail home: `.scratch/plans/architecture-review-2026-08.md` §FW-109

Progress: The docblock says the write-back "must not be removed or simplified"; re-verified 2026-08-30 that two of the four callers (`preview-helpers.php:233`, `:414`) do not do it. `bws_fold_is_combining()` exists to avoid inline container-name comparisons, yet all four production callers pass a hardcoded bool.

Open: Deletion test for any fix — the rules must stay, the out-params concentrate.

Blocked by: —  •  Interacts with: FW-110 (both callers that drop the write-back are previews)

### FW-110 — The base-tag preview re-derives what the render seam decides

The container previews already walk the render seam and delegate skip wording to its owner; the base-tag preview never got that treatment and still re-derives several rules the seam already answers.

Detail home: `.scratch/plans/architecture-review-2026-08.md` §FW-110

Progress: Two of six re-derivations closed in 1.19.0 (the duplicate `use`-default maps and the paired-equality default test now read `bws_use_stripped_default()`).

Open: Remaining — the datetime preview block duplicated verbatim within one file (whose own docblock names the duplication rather than removing it); the join format-token grammar re-implemented against `bws_join_wire_format`; I7's output-destination rule stated as a literal `[ alt, caption ]` at two sites; key-required rules stated per template twice; the preview-outranks-fallback guard repeated in prose at every base callback. NOT in scope: `bws_preview_source_segments()`'s inert-chain detection, a legitimate second reading bounded by design.

Blocked by: —  •  Interacts with: FW-109

### FW-111 — Three registration constructors, one panel, held only by a harness

No "assemble a tag's panel" module exists; each of the three registration constructors open-codes the canonical order, and the contiguity property lives only in `control-order-test.php`.

Detail home: `.scratch/plans/architecture-review-2026-08.md` §FW-111

Progress: Divergences held: fallback-last written three ways, link placement inline vs four-branch vs group-loop, `leading_options` ordered differently per constructor, a hardcoded four-name exception list for an unwritten rule, `[ refs, terms ]` written literally in three files. `bws_prepare_registration_options()` is applied by `register_gb_tag()` but not by `bws_gb_register_tag()`, and §9 scans for a second registrar door while nothing scans for a registration that skipped the pass.

Open: Touches every registered tag; FW-112 is the risk-free slice of the same surface.

Blocked by: —  •  Interacts with: FW-112 (same surface, no risk), FW-115

### FW-112 — Dead option-builder surface, kept alive by its own tests

Three helpers still produce a surface the slot fold made unreachable — `bws_slot_qualify_show_if()` has no live consumer at all, and `bws_build_slot_read_options()` / `bws_build_slot_traversal_options()` each return more dead surface than live.

Detail home: `.scratch/plans/architecture-review-2026-08.md` §FW-112

Progress: Re-verified 2026-08-30. A deletion test run against every registration site shows zero registered options change; one whole harness and part of another fail because they test the dead surface. `base-shared.php:570-571` argues against deleting a label prefix because "join's shipped registration reads it" — join's registration has been folded since 1.17.0, so the comment is itself drifted. Highest value per unit of risk of the ten review candidates.

Blocked by: —  •  Interacts with: FW-111 (same surface)

### FW-113 — Kind dispatch that never joined the ambient-analog seam

`bws_base_ambient_analog()` (1.19.0, FW-9) is the one place a base callback should ask whether an ambient kind answers a tag; three sites still answer that question outside it.

Detail home: `.scratch/plans/architecture-review-2026-08.md` §FW-113

Progress: `class-tag-template-registry.php:400-424` decides term-vs-post for all nine modifier families and does not die with `term_` (`view_*` shares the constructor and is live in bws-portal-system 5.7.0); `datetime-tags.php:865`/`:1013` re-decide the reader after the seam has already answered; `base-shared.php:1742`'s user carve-out is a measured, deliberate (tag × kind) guard rather than a defect. Filed as one row because the three sites share a consequence.

Blocked by: —  •  Interacts with: FW-47 (widening the user arm dissolves the third site), FW-33, FW-3, FW-106

### FW-114 — Two PHP↔JS twins, two standards of proof

The fold-grammar PHP↔JS twin diffs its constants field by field with coverage floors; the serialization-order twin compares output only over a hand-picked corpus, never the `KEY_MAP` structurally.

Detail home: `.scratch/plans/architecture-review-2026-08.md` §FW-114

Progress: Load-bearing gap in the fold twin — `chainRoot` answers one question off two different lists (PHP reads `BWS_FOLD_STEP_TYPES`, JS reads `FANNING_SLUGS`), so a fourth step slug added to one makes PHP call it a step and JS call it a root; `slotKeyRe` is exported but never compared; `chainFanningSteps` is never compared directly. The `show_if` DSL is one-sided — PHP authors condition strings a JS-only grammar reads, with no enum and no validator — `registration-helpers.php:171-174` already records a shipped failure from exactly that.

Blocked by: —  •  Interacts with: FW-115 (both are "two languages agree by convention")

### FW-115 — bws-* control type strings are interface with no census

`bws-*` control types are declared in PHP and matched by string equality in JS, with nothing asserting every registered type has a control or every control's type is registered.

Detail home: `.scratch/plans/architecture-review-2026-08.md` §FW-115

Progress: `base-shared.php:486-489` already names the hazard. The same shape has already fired one level down — the fold config's camelCase keys are written in PHP and read from a hardcoded list in `foldConfig()`, and the control's own comment records two consumers silently reverting to the non-collapsing branch while the feeding config was correct end to end.

Open: Fix shape is a tree census in the mould of `control-order-test.php` §9. Lowest confidence of the ten review candidates — it adds an instrument rather than deepening a module.

Blocked by: —  •  Interacts with: FW-114, FW-111

### Feature follow-ups & UX

### FW-13 — Smart field selector v2/v3

Follow-on work to the shipped field-selector v1 (discovery-backed `bws-field-combo` control replacing GB's raw key/ref/linkKey/datetime-key text inputs).

Detail home: `.scratch/plans/field-selector.md`

Progress: v1 shipped 1.13.0 — ACF + sub-fields + options-page + term-meta + registered meta, flat searchable list with two filters, kind/group-dynamic label, free-text + clear, a REST endpoint inlined per editor load, offered-iff-resolvable.

Open: v2 type-priority (recommend-divider or multi-select Filter 2; `ref`→relationship+post_object; `src:ref` stepped-to-PT scope; smarter Filter-1 preset; dynamic label on `ref`; custom combobox widget for reopen-highlight); v3 Pie Calendar; v-future pick-a-post-to-scan.

Blocked by: —  •  Interacts with: FW-14, FW-20

### FW-14 — Field-selector post-v1 follow-ups

Three low-severity items from the v1 ultra review: FU-1 (location filter as structured data, not a parsed display string), FU-2 (a `bws_field_key_option()` factory for the ~14 hand-copied option flips), FU-3 (a shared filter set for the four stacked datetime_ key-control filter pairs).

Detail home: `.scratch/plans/field-selector.md` §Post-v1 follow-ups

Progress: Not started.

Blocked by: —  •  Interacts with: FW-13, FW-81 (the datetime collapse more than halves FU-3's stacking), FW-53 ({{table}} lands FU-1 prior art + FU-3 second instance + FU-2 proposal)

### FW-15 — {{phone}} follow-ups

Display format, ext/type affix, per-country rules, a `use` enum, per-tag `cc:`, lenient passthrough, vanity/spelled display.

Detail home: `.scratch/plans/phone-tag-future.md`

Progress: Not started.

Blocked by: —  •  Interacts with: —

### FW-16 — {{call}} v2 ergonomics cluster

A cluster of non-breaking ergonomic additions to `{{call}}` — pretty `$meta['label']` in select/mirror, `post_id_arg` registration-repoint, a multi-arg `args:` single control, `arg:` enum-constraint, allowlist shape B/C, and a shortcode-replacement ambition.

Detail home: `docs/design-history/fn-passthrough-tag.md` §Deferred

Progress: Not started. All items are non-breaking, since v1 storage is already associative.

Blocked by: —  •  Interacts with: FW-24 (multi-arg CSV shares the same technique)

### FW-17 — Src-dynamic use-entry labels (V10a)

Relabel a select's `options[]` by the tag's active source.

Detail home: GH #33

Progress: Not started.

Blocked by: —  •  Interacts with: FW-18

### FW-18 — Per-value show_if gating for select options[]

Gate individual `options[]` entries visible/hidden by another option's value.

Detail home: GH #27

Progress: Not started.

Blocked by: —  •  Interacts with: FW-17

### FW-19 — Base-tag distinguishing suffixes

A suffix such as "Text (cross-source)" to distinguish same-named base tags with different source reach.

Detail home: Under consideration

Progress: Not started.

Blocked by: —  •  Interacts with: —

### FW-55 — Warn + escape UI for tag-string-unsafe chars in free-text options

The separator/format free-text options (`{{join}}`'s `valueSep`/`sep`/`format`, datetime `format`, any future glue/subvalue delimiter) let an author type a value that silently corrupts on editor reopen — a second `:` in a pair loses its tail, `{`/`}` fail the render matcher outright.

Detail home: `docs/gb-constraints.md` §Separator-safe vs unsafe characters (the constraint); `.scratch/plans/combined-option-controls.md` (controls rework home)

Progress: `bws-format-input` already escapes `\:`/`\|` for the datetime `format` case (v1.7.4+).

Open: A help-text note naming the safe set on each free-text option, and extending escape-on-save/unescape-on-display coverage to the join glue options. Authoring-UX gap, not a runtime bug — take with the controls/options rework, not standalone.

Blocked by: —  •  Interacts with: FW-20, FW-44, FW-15, FW-16

### FW-58 — Title tag does not suppress GP's native content title

A DTE title tag inside a GP Page Hero / Page Header renders a duplicate, because GP self-suppresses only for its own literal `{{post_title}}` — a `strpos()` check against the raw stored element content that DTE's tag syntax never matches.

Detail home: `bws-generate-layout-conditions/docs/architecture.md` §Content title: complete writer survey

Progress: Two suppression levers identified: (a) hooking `generate_show_title` false at priority 20+ (no filter on the underlying decision itself, so this must land after Page Hero's own `wp`:100 write); (b) rewriting the substituted value in `generate_page_hero_post_title`. A Page Hero Block Element's own "Disable title" checkbox already performs both removals correctly, so `{{title}}` inside such a hero is clean today — this only concerns use outside one. GP's 404 (`content-404.php`) and search-results (`generate_do_search_results_title`) headings duplicate too, and `generate_show_title` reaches neither.

Open: Whether suppression should be opt-in per tag or unconditional (GLC's condition-side pairing, T15/T16, is a separate seam).

Blocked by: decision:opt-in vs unconditional suppression  •  Interacts with: FW-9 (query-context kinds shipped 1.19.0; the duplication pair arrived with them)

### FW-73 — Converter coverage: enumerate unreachable tag wire, or keep disclosing the boundary

The Tag Converter reaches `post_content` and, with #99, the GB Pro pattern cache; tag wire in custom field values, other plugins' caches, and other page builders' stored data are unreachable by the scanner.

Detail home: GH #100

Progress: Settled for #99 as DISCLOSURE, not enumeration — the Migration Tool states its reach boundary in its own section copy rather than sweeping for unreachable wire. Detection already exists in maintainer tooling (`tools/harvest-replay/replay-tags.php` + the env repo's harvest script), which is `.distignore`d. #99's reconcile reports through a persisted settings-page summary line rather than an upgrade-time notice; a notice was deferred to FW-66/#77.

Open: The enumeration half (a sweep of postmeta/options/termmeta reporting what unreachable wire it finds) stays deferred as undesigned. Whether the deferred notice rides FW-66/#77's `announcement` lifecycle is undecided — its remit (a release CHANGED output) does not cleanly cover "there is maintenance work to run".

Blocked by: decision:disclosure vs enumeration (reopened)  •  Interacts with: #99, #98, FW-66 (notice deferral)

### FW-85 — Per-item link wrapping for list-mode values

A fanning tag's top-level link is gated on count (`bws_collect_value_list()` only returns a link when exactly one value rendered), so each value's own link identity is collected and then discarded — `CONTEXT.md` [I12] already names per-item wrapping as the intended successor.

Detail home: `docs/design-history/deterministic-source-selection.md` §S26 (the disclosure it defers) + `CONTEXT.md` [I12] (the invariant that anticipates it)

Progress: The limit-usable-results fix (FW-87) makes the loss more visible — a tag that used to show 1 of 3 values can now show all 3 and cross the count gate, losing its link on exactly the population that fix targets; that loss is disclosed via `== Upgrade Notice ==` rather than repaired.

Open: Which value receives the link once `sep` has joined several into one string — per-value wrapping means the join must emit markup, which its grammar does not currently do.

Blocked by: —  •  Interacts with: FW-49 (established the per-value payload this consumes), ADR 0003/0005, [I12]

### FW-86 — Whether a fanning chain deduplicates its resolved sources

Nothing on the fan path removes repeats, so two inputs sharing a target (e.g. two offices in the same region) yield that target twice — ordinary, not exotic, for shared terms like regions/brands/categories.

Detail home: `docs/design-history/deterministic-source-selection.md` §O12 (the structural argument)

Progress: Ruled out of scope for the limit work 2026-08-20 on a structural argument: dedupe across inputs destroys the provenance grouping the per-input terminal limit depends on, and the only safe form (dedupe within one input) cannot reach the motivating case.

Open: Needs a design, not a patch. Revisit when a per-input bound is shipped and someone reports the duplicate, or if per-item link wrapping (FW-85) lands and makes each repeat separately clickable.

Blocked by: —  •  Interacts with: FW-85, ADR 0005, [I12]

### FW-88 — Opt-in "search past empty fields" for collapsing tags

The dormant "show me the picture, whichever candidate has one" behaviour, removed from source selection by the 2026-08-21 reversal — preserved as an unused optional read-predicate parameter on `bws_read_bounded_sources()` plus a pinned pure predicate function, awaiting a possible tag-level opt-in.

Detail home: `docs/design-history/deterministic-source-selection.md` §S47

Progress: Term chains are the constituency this matters most for, since WP term order is a pass-through (alphabetical by default) rather than an author choice.

Open: Whether the option is useful at all (user, 2026-08-21); if so, the whole authoring surface (control, wire token, label/help, placement, an era-stamping migration for flat-era wire).

Blocked by: decision:whether the option is useful at all  •  Interacts with: FW-87, ADR 0007 (§Why the read-based axis was reversed), [I19]

### FW-89 — The source-visibility filter hook

`bws_source_gate()` is filterable by construction, but the one-line `apply_filters` is deliberately unshipped until a real consumer asks — the contract is restrict-only and AND-composed so a filter can refuse a source and never admit one the gate already refused.

Detail home: `docs/design-history/deterministic-source-selection.md` §S20 corrected + §S27

Progress: Grounded in the one known consumer, Portal System's `VisibilityChecker::is_post_visible()`, which derives everything but the id from ambient context.

Blocked by: `decision:a consumer needs it` (unshipped by design)  •  Interacts with: FW-88, ADR 0007, [I19]

### FW-90 — The per-step limit HELP names the same noun its LABEL does

1.18.0 gave each step's limit control a label naming what that step produces, but its help text still says the generic "items" — a small seam, and closing it is not free (the control must not compose the string itself).

Detail home: `.scratch/plans/per-step-limit-help-noun-match.md` (new)

Progress: Not started.

Open: Right shape is a second authored pair per slug (`limitHelp`/`limitHelpFanning`) beside `limitLabel` in `bws_fold_wire_vocabulary()` — worth doing when something else already has that vocabulary open.

Blocked by: —  •  Interacts with: FW-88 (the same vocabulary), the deferred tag-description work

### FW-91 — The two grey notes in the source group are visually indistinguishable

The field-configuration note (storage capability) and the group-end fanning advisory (this tag's read behaviour) render as byte-identical grey boxes, reading as one repeated element when they stack.

Detail home: `docs/editor-controls.md` §Group-end fanning advisory + §Field configuration note

Progress: Not started.

Open: A visual treatment that separates them (rule colour, icon, unboxed italic) plus a rewording pass so they complement rather than compete; a shared style constant is not the goal. Constraints: copy stays PHP-authored, the advisory must keep saying a chain *can* match several rather than that it did, and "return" is unavailable (taken by the Return As/Return Type controls).

Blocked by: —  •  Interacts with: FW-90 (the same copy pass), the deferred tag-description work

### FW-92 — The try_ variants of the collapsing tags show no fanning advisory

1.18.0 suppressed the per-step limit control on every `takes_first_usable` tag, base and `try_` alike (inherited off the base template record), but the replacement advisory lives on the base registration only, so a `try_` slot loses the control and gets nothing in its place.

Detail home: `docs/editor-controls.md` §Group-end fanning advisory

Progress: The registration-vs-template asymmetry is the cause: a capability-gated surface reaches `try_` for free when it rides the template record, and silently skips it when it rides a registration. CHANGELOG 1.18.0 states the absence rather than hiding it.

Open: Placement — a `try_` tag has several slots each with its own chain, so the advisory is either per-slot (often repetitive) or once at the group end (mute about which slot fans).

Blocked by: —  •  Interacts with: FW-90 (the same copy pass), FW-91 (a slot-level advisory turns the two-grey-notes collision into a three-way one)

### FW-99 — A dependency-version record a released build can read

A dependency-version record the Diagnostics section could read to tell a site owner their dependency versions differ from the ones last validated.

Detail home: `.scratch/plans/dependency-version-record.md` (new)

Progress: Not shipped — cut 2026-08-26, structurally: the only version record (`tools/fixtures/core-structures/env-versions.php`) lives under `.distignore`d `tools/`, so it cannot render for a released install. `env-versions.php` remains untouched as the fixture baseline `verify.php` reads. The Diagnostics section now has a home for this as a third subhead beside Tag Name Conflicts and Settings.

Open: Whether to carry a second version record outside `tools/` (a drift pair) or generate one at build time from `env-versions.php` (adds a build step to a plugin that deliberately has none).

Blocked by: decision:second record vs generate from env-versions.php  •  Interacts with: FW-96 (the other consumer of the same version record)

### FW-100 — Product-shaped loop items are unrecognized, and WooCommerce loops now render empty

Query-loop item-shape recognition (1.19.0) reads four shapes — post, term, user, repeater row — and refuses everything else, so a tag inside a WooCommerce product loop now renders nothing where it used to render something by coincidence.

Detail home: `.scratch/plans/product-loop-item-recognition.md` (new) + `bws_classify_loop_item()` PHPDoc (the axis)

Progress: Accepted as a regression, not hidden: measured 2026-08-26 that `WooCommerce_Query` emits a bare anonymous `(object)['id' => …, 'name' => …]` record with no class or marker, satisfying no recognition arm. The old behaviour worked only because a WooCommerce product id happens to equal a post id — the same coincidence that hid the term-id leak (#123) — so an unrecognized shape must say nothing ([I15]). No fixture site carries WooCommerce, so no matrix row exists; `loop-item-classify-test.php` §C1.13 pins only that the shape is refused.

Open: What marker identifies a product record, given a bare `id` is the weakest marker there is. Whatever ships must stay SHAPE-keyed, never vendor-keyed.

Blocked by: decision:what marker identifies a product record  •  Interacts with: FW-97 (a Woo fixture would move every baseline it lands on), [I15]

### FW-105 — The raw search query is unreachable on a core-only site

Since 1.19.0 `{{title}}` on search results returns core's formatted heading rather than the bare query string, and no new tag route to the bare query shipped with that pass.

Detail home: `.scratch/plans/context-aware-base-tags.md` §Decisions #8 + §Option Surface

Progress: Filed so the gap is not rediscovered as a bug — the formatted heading is decided behaviour (following `wp_get_document_title()`), not an oversight. GB Pro's `{{archive_title}}` returns the bare query as a side effect where Pro is present, which is a workaround, not a reason to match it.

Open: Designated home is the deferred search-format option (FW-9's option surface) set to a bare `%s`-style placeholder; a `{{search_query}}` companion tag stays the fallback if that option surface stalls.

Blocked by: row:FW-9 (its option surface)  •  Interacts with: FW-9

### Testing & infrastructure

### FW-79 — Re-base the tag-string preview tool on shipped chain wire

`tools/preview/tag-string-preview.html` was built contrasting shipped-flat wire against an FW-56/FW-57 proposal that has since shipped, so the contrast now reads as a live choice where none remains.

Detail home: `.scratch/plans/tag-string-preview-rebase.md` (new)

Progress: Held, not archived, since 2026-08-05 — deliberately left un-rewritten only until 1.17.0 shipped so the re-base target would stop moving; 1.17.0 has shipped, so the re-base is startable. Measured staleness (2026-08-18): two of three "no shipped form" notes now have one; zero coverage of FW-71/#104's chain-wire-on-slots and `same` merge, the largest single gap.

Open: Scope is a re-base, not a rebuild — emitters, toggle apparatus, slot-key spelling, and the `%A` format-token rewrite still work; what moves is which side is the default and which notes are true. What stays behind a toggle divides into genuine open futures (FW-61, FW-59, FW-39, FW-24, FW-27, FW-53, FW-45, FW-81) and deliberately-kept rejected candidates (the `/` limit char, root-explicit chains, `use(...)` read parity) — label the axis so a reader can tell them apart.

Blocked by: —  •  Interacts with: FW-75 (both are "an artifact whose readers have changed"), FW-77, FW-61, FW-59, FW-39, FW-53

### FW-97 — Fixture-page reorganization

The fixture pages are cut by source-state (`matrix-post-meta`, `matrix-terms-*`, `matrix-content`, `matrix-gate`, `matrix-fixture-roots`) and tag families have accreted into them since, so which page a row group lands on is now part convention, part history.

Detail home: `.scratch/plans/fixture-page-reorganization.md` (new)

Progress: Reviewed 2026-08-28 — narrower than a failed cut: most `*-test-matrix.md` files already name their pages inline, so the "which page" lookup is largely already answered. `/matrix-post-meta/` has become the catch-all (more matrices name it than any other page, and its baseline is by far the largest) — a size complaint, not a findability one. A move re-captures only the pages it touches, not every baseline, since snapshots are per-page files.

Open: Split the catch-all, or accept it — re-measure before acting. Any reorganization must land in the SAME commit as the page-snapshot re-capture, or the pages fail for a reason no diff explains.

Blocked by: —  •  Interacts with: FW-96, FW-103

### Docs & vocabulary

Repairs to the documentation corpus itself: prose that has outgrown its reader, pointers that no longer resolve, and vocabulary the docs use inconsistently. Split out of §Testing & infrastructure 2026-08-28 — those rows had nothing in common with a fixture site beyond "not a feature and not a bug".

### FW-75 — tag-reference.md navigability — trim rationale, index Part I, point trigger rows at sections

Trim rationale, index Part I, and point trigger rows at sections rather than at the whole ~1,300-line file, so an agent following a "see tag-reference.md" pointer reads a section instead of the whole doc.

Detail home: `.scratch/plans/tag-reference-navigability.md`

Progress: Decided 2026-08-17 not to split the file — `CLAUDE.md` §Long-lived plan files already rules length isn't the mechanism, discoverability is, and prescribes an index. Since that reading, the file has already shrunk (173.5KB→158.7KB, 1284 lines) as a side effect of unrelated `CLAUDE.md` de-bloat work. Three sections are confirmed on the wrong side of the Part I / Part II line and move regardless (§Shared option groups, §Try_ tags, §Folded slot wire).

Open: Order is trim rationale first, then index, then the three boundary moves, then roughly 40 trigger rows. Re-measure current size before scoping.

Blocked by: —  •  Interacts with: FW-53 + `docs/editor-controls.md` (created 2026-08-19, still owes §Option layout & visibility once the `use`+`key` combine ships)

### FW-76 — Repoint the dangling in-code SPEC §V<n> citations (both spellings)

Roughly 81 in-code citations of the retired root `SPEC.md` artifact (`SPEC §V<n>` and `SPEC.md §V<n>`), across 12 source files and 3 harnesses, each needing to repoint to wherever that invariant actually lives now (a CONTEXT.md I-number, a PHPDoc, or a tag-reference.md section).

Detail home: this row + `CLAUDE.md` §Spec lifecycle

Progress: Counted 2026-08-17 (largest concentrations: `traversal-pipeline.php` 26, `base-shared.php` 15, `base-tags.php` 10, `field-helpers.php` 9, `class-tag-template-registry.php` 6, `datetime-tags.php` 5). The citations already dangled before `SPEC.md` was deleted — it was truncated to a stub after 1.14.0.

Open: Opportunistic, not a sweep — none is load-bearing, and each is cheapest to fix while already reading the function it sits in.

Blocked by: —  •  Interacts with: FW-75 (both are "a pointer that no longer resolves"; same habit, different artifact)

### FW-77 — Reexamine the docs/future-work.md trackers themselves

How a row reads, in three parts: row length, where prose goes when a row becomes a record with no detail home to push it into, and rows whose Detail home is "this row" itself.

Detail home: this row

Progress: Narrowed 2026-08-28 — the taxonomy half of the original scope is done (FW-66 moved section, §Docs & vocabulary split out of §Testing & infrastructure, FW-42 retired, the in-flight-spec home landed as `.scratch/<feature-slug>/spec.md`). Addressed by the 2026-09-01 heading-block rewrite: rows now carry Description/Progress/Open in place of accreted narrative, and every "this row" home either got a new `.scratch/plans/*.md` file or was confirmed as a genuine self-reference (this row, and FW-79/FW-97, whose subject IS the tracker/fixture surface itself).

Open: Whether the Closed / Retired ledger belongs in this file at all.

Blocked by: —  •  Interacts with: FW-75 (same class of work, different doc), FW-38 (`registered_by`/`lifecycle` — the other place tracker rows stand in for structure that doesn't exist yet)

### FW-82 — Whether README carries a "Recently added" section

A recurring release-time debate, filed to stop it being re-argued from scratch each version. README's scope axis is CAPABILITIES ("can it do X?"), never authoring mechanics ("how do I write X?") — a "Recently added" section would be a discovery aid for a returning reader, not a home for new features (which the existing capability structure already houses).

Detail home: this row + memory `feedback_user_facing_prose_style.md` (the scope axis it records)

Progress: Deferred again 2026-08-19 (user: not today). The do-nothing option is coherent and ships today — release-fresh copy already lives in CHANGELOG Highlights.

Open: If built, needs a retention rule (how many versions before an entry drops, what happens to one that never graduates into the capability prose) or it becomes a second changelog drifting from the first.

Blocked by: decision:recently-added vs nothing  •  Interacts with: FW-75 (the other "an artifact's readers have changed" row)

### FW-93 — Whether "wire" becomes "tag string"

The largest remaining term of art after the 2026-08-22 vocabulary pass — 557 live sites (355 code, 202 docs).

Detail home: `.scratch/plans/vocabulary-pass.md` §OPEN 1 + §OPEN 2

Progress: "Tag string" is not a coinage — it is GB's own noun for the same artifact (`parse_tag_string()`), with 98 uses already. Against: "wire" reads well in compounds, and ADR 0004 names it in a record that is never corrected. How many of the 557 sites are compounds needing a rephrase rather than a swap is unmeasured. The arrival-route pair (§F9c, one kind reached two ways) rides whichever noun is chosen.

Blocked by: decision:which noun  •  Interacts with: FW-95

### FW-95 — The remaining term-of-art inventory

An unstarted inventory pass over `arm`, `seam`, and `absorber` (unassessed, undefined anywhere) against `fanning`/`fan` and `fold`/`flat` (probably keep — both defined in `CONTEXT.md` §Language).

Detail home: `.scratch/plans/vocabulary-pass.md` §OPEN 4

Progress: Not started. Method: count live sites per term, check whether it's defined at an owning site, then apply the plain-English rule.

Blocked by: —  •  Interacts with: FW-93, FW-67 (`hop`'s last carrier, not part of this inventory)

### FW-101 — "Author" and "user" name three different relations and the docs blur them

Author (a relation to content), user (a WordPress user record), and "user" as shorthand for "the logged-in viewer" are three different things sharing two overloaded plain-English words — the third reading is the dangerous one, since a reader could take per-row copy as describing a per-visitor value.

Detail home: `.scratch/plans/vocabulary-pass.md` §OPEN 6

Progress: Raised 2026-08-28 while holding FW-47 — its soft gate being met by user query loops means these analogs are about to be described in user-facing copy for the first time, making this cheapest to fix now.

Open: Whether the repair is a definition pair in `CONTEXT.md` §Language, a rename, or disambiguation only where the docs currently blur them.

Blocked by: —  •  Interacts with: FW-47, FW-48, FW-93, FW-95

### Future possibilities

### FW-20 — Combined option controls

`use:key,field` serialization combine (selector+field fold) — the wire change, separate from FW-13's discovery work. The `link` cluster unification is the surviving scope after the serialization-order half shipped as FW-52.

Detail home: `.scratch/plans/combined-option-controls.md` (srcTermIn part shipped v1.6.0)

Progress: Not started on the remaining `link` cluster.

Blocked by: —  •  Interacts with: FW-13, FW-81

### FW-21 — Add sources to GB core tags via JS filters

Extend GB's own core tags with additional sources through JS filters.

Detail home: `.scratch/plans/gb-tag-extension.md`

Progress: Not started.

Blocked by: —  •  Interacts with: —

### FW-23 — Base text tag: treat '0' as empty (opt-in)

An opt-in augmenting the site-wide preserve-zero guard, absorbed by `{{join}}` slots for free.

Detail home: `tag-reference.md` §join "'0' is a real value" (context); `docs/design-history/combine-text.md` §Empty-value detection

Progress: The site-wide `'0'`-preservation guard (`includes/hooks.php`) is confirmed rewriting first-party GB output too (a zero-based loop index, GB's own comment count, a GB Pro loop item) — pinned at `text-test-matrix.md` §T5 — so this opt-in cannot be built by narrowing or conditioning that filter.

Open: Must be built in this plugin's own text read, before the replacement reaches the shared filter.

Blocked by: —  •  Interacts with: the site-wide '0' guard (`includes/hooks.php`)

### FW-24 — Tag-in-slot composition

Slots holding whole base tags for heterogeneous join/try composition. Nested-braces syntax can never ride the wire (GB kills any `}`), so encoding must stay flat.

Detail home: memory `deferred_features.md` (north-star for #26); sandbox → `docs/design-history/src-chain-encoding.md` §2026-07-29

Progress: Mostly satisfied by the approved FW-57 fold wire (2026-07-31 assessment, not a build) — the fold's slot value already carries a whole per-slot source chain plus its read, and Option R lets that read name its own processing tag on format-agnostic containers, which is heterogeneous-tag-per-slot arriving for free. The step splitter is confirmed bracket-aware, which this row's remaining scope depends on.

Open: Remaining scope is narrow — per-type OPTION tokens inside a slot (a `datetime_single` slot cannot yet carry that tag's `format`, an image slot cannot carry `as`/`size`).

Blocked by: —  •  Interacts with: FW-25, FW-16, FW-56, FW-57

### FW-25 — Multislot-only field options

Gate a `use` value to slot ≥2 only.

Detail home: memory `deferred_features.md`

Progress: Not started.

Blocked by: —  •  Interacts with: FW-24 (cheaper alternative)

### FW-26 — {{if}} conditional tag

A third composition verb (selecting = try, combining = join, conditional = if) as a separate tag set, branching a template/value on a read field value.

Detail home: memory `deferred_features.md` (loose concept, no plan)

Progress: Parked in favor of FW-27 (user, 2026-08-01) — the `if` concept has settled as an embedded per-slot option on base tags rather than a separate tag set. Kept for the tag-shaped alternative and the athletics driver case.

Blocked by: —  •  Interacts with: FW-27, FW-28

### FW-27 — if: as a BASE-TAG OPTION

A lighter alternative to FW-26 — a `show_if`-style predicate grammar that self-gates one tag's output. `if` composes with the slot chain rather than replacing it: `try_` is functionally an if-has-value chain, so generalizing the predicate makes `if` a second, author-set condition per slot alongside the existing has-value check.

Detail home: memory `deferred_features.md` (spitball, no design); wire → `docs/design-history/src-chain-encoding.md`

Progress: Direction confirmed and sharpened (user, 2026-08-01): embedded option, not a separate tag set; FW-26 is the parked alternative.

Open: The condition's subject — the useful cases test a DIFFERENT source/field than the slot reads, which means a condition needs its own src-chain per slot on top of the read chain, roughly doubling per-slot state. A same-subject fallback (condition tests the slot's own read) covers has-value/simple truthiness with no second chain, at the cost of the cases that motivate the feature. Must decide before any wire work.

Blocked by: decision:condition subject — decoupled chain vs same-subject  •  Interacts with: FW-26, FW-57, FW-56, FW-60, FW-43

### FW-28 — Composition-of-composers

Nesting {{join}}/{{try}}/{{if}}. Runtime nesting is trivial (a composer callback resolves children from its own options); an `@name` reference model is not viable since GB is stateless.

Detail home: memory `deferred_features.md` (nesting tension)

Progress: Not started. Only nested RESOLUTION is solved; authoring UI is the gating cost.

Open: True-recursive vs one-level authoring model.

Blocked by: decision:authoring-UI model  •  Interacts with: FW-26, FW-29

### FW-29 — Admin-built composite tag

A `{{custom}}` tag plus a template selector — build an over-complex tag in an admin UI, persist it server-side, and reference it via `{{custom tpl:name}}`, sidestepping the flat-options serialization wall. May be the authoring substrate for heterogeneous join/if/try via a `tpl:` option.

Detail home: memory `deferred_features.md` (counter-concept + substrate spitball, no design)

Progress: Not started.

Blocked by: —  •  Interacts with: FW-28 (substrate)

### FW-30 — Block editor sidebar migration tool

A sidebar tool for migrating tags.

Detail home: memory `deferred_features.md`

Progress: Not started.

Blocked by: —  •  Interacts with: FW-31

### FW-31 — GB ↔ BWS tag cross-converter

A converter between GB core tags and BWS dynamic tags.

Detail home: memory `deferred_features.md`

Progress: Not started.

Blocked by: —  •  Interacts with: FW-30

### FW-33 — term_ deprecation path

Subsumed by base tags + context-aware kinds (#19) + the ID source (FW-39); registry-only re-add expected after. `view_` does not follow this path — it is external and may stay even when `src:view` lands. This row also homes `term_`'s collapsed-fan gap (a `term_` tag with a fanning source silently returns one result).

Detail home: memory `project_term_deprecation_path.md`; the de-scoping decision `docs/design-history/multi-step-slot-sources.md` §History

Progress: `view_` now runs ahead of this row on its own path (FW-70) — registrations never retire (an unregistered tag stops rendering entirely), so `view_*` wire migrates to `src:view` while `term_*` wire does not. Both families keep the flat `srcTermIn` control until `register_modifier()` itself retires (FW-67). The collapsed-fan gap (GH #63) resolves for free once migrated, since ADR 0005's per-step limits already apply to a base tag.

Blocked by: row:FW-9, code:ID source lands  •  Interacts with: FW-8, FW-67, FW-70

### FW-34 — Configurable default field keys per source × tag-type

Let an author configure the default field key read per source and tag type.

Detail home: GH #29 (memory `project_default_field_keys.md`)

Progress: Not started.

Blocked by: —  •  Interacts with: —

### FW-35 — datetime_ all-day affordance — a flag field, or midnight

One option holding a single exclusive predicate — `allDay:midnight` (00:00 means all-day) or `allDay:key,<field>` (a boolean field decides) — rather than a single boolean field option, because an ordered fallback needs `false` distinguishable from `absent` and the motivating Pie Calendar field can't supply that distinction.

Detail home: `.scratch/plans/all-day-flag.md` (design + the Pie Calendar evidence); GH #41

Progress: Designed 2026-08-24. Not a position in FW-81's read fold — a boolean read is a different kind from a date read, so the two rows are independent. `showMidnight` does not retire: with an authoritative flag a 00:00 on a not-all-day event is a real midnight time and should still print. Zero migration either way (`allDay` absent = today exactly).

Open: The whole row waits on FW-59/FW-61's bracketed free-form value escape discipline, since the note is a bracketed free-form value and blocks the whole feature (shipping `key,<field>` alone would need a migration once `midnight` later joins).

Blocked by: row:FW-59, row:FW-61  •  Interacts with: FW-3, FW-81, FW-13 (the flag field is itself a discovered field)

### FW-39 — ID source

A new source flavor where the author identifies one specific entity, its id serialized into the token — `<kind>,<ID>` (e.g. `post,9999`) as the first step of a chain.

Detail home: CONTEXT.md §Language "Source binding" (concept + two-axis model); no plan/issue yet

Progress: Encoding decided via FW-56 Decision 3 (2026-07-27) — the kind is forced onto the wire by the editor-UX static-computability floor (no live ID resolution in Patterns/Elements), matching the engine's `{kind,id}` shape 1:1. A parser-recovery affordance is designed: on `term,<arg>`, a non-numeric `<arg>` recovers as a `terms` step, since term IDs and taxonomy slugs are disjoint value-spaces.

Open: Ref-step decoupling (a per-`src` ref option). Home for the "specific-resource + site fallback" case as a `try_` attempt (`try_allow_site_slot`), not a `try_term_` form.

Blocked by: —  •  Interacts with: FW-33, FW-9

### FW-44 — join per-slot inner list sep ({N}-sep)

A list-mode join slot joins its own items with text's default `', '`; this would give each slot its own inner separator.

Detail home: `docs/adr/0003-join-per-slot-limit-not-sep.md`; `docs/design-history/join-sep-rename-handoff.md`

Progress: The prerequisite blocker dissolved 2026-07-23 when the tag-level assembly `sep` was renamed to `valueSep`, removing the collision. Overlaps FW-61 (per-step `sep` on a fan-out chain) — under the FW-57 fold a list slot's items come from its chain's terminal step, so FW-61's step-scoped `sep(…)` would deliver the same affordance without a new key.

Open: Still an edge affordance; add only on evidence it's wanted, and decide together with FW-61 rather than building `{N}-sep` first.

Blocked by: —  •  Interacts with: FW-43, FW-61 (overlaps)

### FW-45 — join dynamic slot count

Drop the fixed `BWS_JOIN_MAX_SLOTS` (10) ceiling for an add-slot editor control, and support reordering slots.

Detail home: `docs/design-history/combine-text.md` §Slot count; repeater → `docs/design-history/src-chain-encoding.md` §Slot repeater

Progress: The control question is largely answered by the FW-57 slot repeater, shipped 1.17.0 (`assets/js/slot-fold-control.js`) — register-to-ceiling, render-a-slot-iff-it-holds-a-value. Reorder specifically stays in scope here and is NOT discharged by the repeater, which builds add + remove only.

Open: Whether the ceiling stays finite or the registration itself goes dynamic; applying the repeater to flat `{N}-src`/`{N}-use`/`{N}-key` join slots if the fold doesn't land first; reorder, which is strictly harder than removal since it re-points every intervening `same` reference rather than touching one immediate successor. The repeater would also serve FW-60's add-slot need if that lands, at no new control cost.

Blocked by: `code:custom editor-control work` (docs/editor-controls.md owner)  •  Interacts with: FW-24, FW-57, FW-60

### FW-46 — Name-format preset over join

A canned "Full name" preset pre-filling `mode:template` plus the 7-part format and slot keys — pure config sugar, no new resolve path.

Detail home: `docs/design-history/combine-text.md` §Open/deferred (both ends recorded)

Progress: Not started. Leaned toward over a dedicated `{{name}}` tag, which is parked (name collision with term-name/post-name/repeater-subfield semantics).

Blocked by: —  •  Interacts with: FW-29 (preset-authoring substrate)

### FW-48 — src:author — the current post's author as a user source

A new source reach to a USER source by post→author hop (`{{title src:author}}` → the post author's display name), distinct from the ambient FW-9 author kind (an author archive).

Detail home: `docs/design-history/traversal-convergence-fw49.md` (seam halves' record); readers exist (`bws_base_user_analog_read`), factory hop + seam user arm are the new code

Progress: The seam halves shipped 1.16.0 (FW-49 build) — `case 'user':` in `bws_read_resolved_source` and the resolve-value user arm, so `{{text}}`/`{{join}}` slots resolve on author archives. `try_` slots followed in 1.17.0 (#108) with their own dispatcher cases. The 1.19.0 ambient-analog collapse means the remaining hop's output lands on one seam every former arm site already asks.

Open: Remaining scope is only the factory post→author hop itself, which makes `src:author` user-facing. Opens the same permalink/image analog questions FW-47 tracks — out of scope here.

Blocked by: —  •  Interacts with: FW-47, FW-39, FW-9, FW-49

### FW-53 — {{table}} repeater→HTML table tag

A repeater fold (`rows` step) into a `<table>` string. A table's row-set is whatever its source chain returns — not a flat/repeater/relationship "mode" — which hard-depends on the multi-step source-selection encoding (FW-56, shipped).

Detail home: `.scratch/plans/table-tag.md` §Roadmap

Progress: Prototype built and committed, gated behind the `bws_dynamic_tags_register_table_tag` filter (default false) since 2026-08-12, so no install gets it by default; the fixture blueprint enables it from its mu-plugin so matrix rows and fixture blocks keep running while v1 is built. Its blocker cleared 2026-08-20 when GH #55 (base-tag source chains) shipped — v1 can now assume both a chain source and registered roots (FW-69).

Open: v1 finalization detail lives entirely in the plan's §Roadmap. Flip the filter to unconditional when v1 ships.

Blocked by: —  •  Interacts with: FW-13, FW-14 (#12 discovery scoping), FW-20, FW-54, FW-56, FW-69, FW-67

### FW-54 — src:query — cross-tag post-query base source

A base source running a `WP_Query` from author filters (post type, tax, meta, orderby, limit) and rooting the tag on the result set — a new fanning base source at L1, parallel to current/ref/site, ignoring ambient context.

Detail home: `.scratch/plans/query-source.md`

Progress: Concept only, not fleshed. Split out of FW-53 because it is a new fanning L1 source rather than an L2 field-read.

Open: Three structural costs beyond the query-filter UI — every scalar tag consuming `src:query` needs a collapse rule, editor preview must run a live `WP_Query` (ignoring the ambient id [I11] threads), and field-discovery scope depends on a still-building filter rather than a static restriction.

Blocked by: —  •  Interacts with: FW-53, FW-13

### FW-59 — Bracket free-form values on BASE tags

Wrap base-tag free-form option values in a bracket (`format[g:i A]`) so they're structurally isolated from option separators, matching the rule the FW-56/57 slot work already established, rather than relying only on `\:`/`\|` escaping.

Detail home: `docs/design-history/src-chain-encoding.md` (escape-hazard finding); `gb-constraints.md` §Tag string escape syntax + §Separator-safe

Progress: Validated in the 2026-07-29 sandbox — brackets are inert to GB, a balance-aware sub-parser handles balanced inner brackets, `\:`/`\|` still escapes the two GB-structural characters, `{`/`}` remain hard-unsafe.

Open: Which option keys count as free-form (the `RESPELL_FREEFORM` seed set); migration vs read-tolerant; interaction with `bws-format-input`'s existing escape control. Should land with or before FW-56/57 so base and slot free-form emission share one rule.

Blocked by: decision:migration-vs-read-tolerant  •  Interacts with: FW-56, FW-57

### FW-60 — Absorb try_ into base tags via an add-slot control (one-way fold)

Make "try another source" reachable as a control on a base tag (`{{text}}` growing slot 2) rather than a separate `try_` tag family, since switching tag type in the GB modal discards all options — the FW-57 fold ended the old hand-edit workaround that made this unnecessary before.

Detail home: `.scratch/plans/absorb-try-into-base.md` (new); constraint → `gb-constraints.md` §Switching tag type; label collision → `docs/design-history/src-chain-encoding.md` §Pass 5

Progress: Assessed premature (user, 2026-08-01) — recorded as a possibility, not a plan. Encoding falls out cleanly as a one-way fold (base tags serialize unprefixed until slot-scoped state appears, then fold once and stay folded), which avoids the destructive-collapse and history-dependent-wire costs a bidirectional auto-switch would carry.

Open: Whether `try_` is structurally "base + N slots" or carries real structural difference (verify against `generate_base_try_tags()`'s inline resolve + `show_if_any` reveal, which FW-43 targets); the add-slot control (largely answered by FW-45's repeater spike); the slot-noun label ("Add fallback" rejected — collides with the shipped `fallback` option).

Blocked by: `decision:premature — absorb try_ into base at all`  •  Interacts with: FW-57, FW-45, FW-27, FW-43, FW-24, FW-33

### FW-61 — Per-step sep on a fanning chain

Split the single tag-level `sep` joiner per fanning STEP, so a two-fanning-step chain can render `A; B / C` — an ordinary bracket-kv token beside `limit`.

Detail home: `docs/design-history/src-chain-encoding.md` §SLASH candidate (per-step sep subsection); the structural block is `docs/design-history/per-step-limit.md` §Deferred UX

Progress: Deferred 2026-08-01 (user) — modelled and rendered in the preview tool (which is what let the rejected slash-`limit` candidate be judged), not adopted. Wire shape and escape behaviour are settled by that render. Overlaps FW-44 (a join slot's list comes from its chain's last step, so a per-step `sep` on that step subsumes FW-44's per-slot inner sep; FW-61 additionally reaches intermediate steps).

Open: Whether the authoring surface is worth it at all, and how it interacts with the tag-level `sep` it would not replace.

Blocked by: `code:fan-out structure preservation — bws_run_traversal flattens rather than keeping a tree a per-step sep could join`  •  Interacts with: FW-44 (overlaps), FW-56, FW-57, FW-55, FW-20, FW-53

### FW-62 — Move the fold control's remaining authored LABELS onto the option definition

Four strings the fold control still authors itself (`__('Source')` x3, `__('Taxonomy')`) instead of reading them derived off the PHP option definition, the standing rule everything else in the control already follows.

Detail home: `.scratch/plans/fold-control-label-migration.md` (new); migrates to `docs/editor-controls.md`, the owner doc for the bws-* control pattern

Progress: Found by code review 2026-08-04. Not urgent — a drifted label misleads an author rather than corrupting stored wire, unlike the axis-list drift this pattern also guards against.

Open: Do it with the surface that's already going to touch these strings — `bws_build_fold_slot_options()` gains label keys, the control reads them, and `slot-options-build-test.php` gains a case.

Blocked by: —  •  Interacts with: FW-20 (the combine that re-touches this control), FW-45, FW-53

### FW-64 — Composite field-group control (the option-group wrapper's successor)

Whether the shipped presentation-only option-group wrapper (`_group`/`_group_lead` + CSS-joined boxes) should become a real `bws-field-group` control owning `use` + the field picker, the way the folded slot's read group does.

Detail home: `.scratch/plans/composite-field-group-control.md` (new); migrates to `docs/editor-controls.md`, the owner doc for the bws-* control pattern

Progress: The wrapper was the deliberate v1, kept tracked at the user's request rather than dropped. A real composite swallows its members' elements, so `show_if` reveal stops running unless the composite evaluates it — the prerequisite is exporting `editor-conditional-options.js`'s predicate rather than re-implementing it. Accepted for v1.17.0: the wrapper renders no caption, so a group reads captioned on chain-sourced tags and bare on `term_*`/`try_*`/`{{table}}`/`{{call}}`.

Open: Whether to build the real composite at all; if so, it also needs to read lead-control state since the chain control's caption is dynamic.

Blocked by: —  •  Interacts with: FW-62 (the same control's authored labels), FW-20, FW-45, FW-13

### FW-66 — Reusable advisory channel for disclosed behavior changes

A channel for telling authors a release CHANGED what a tag renders, distinct from telling them there is migration work to run — two surfaces (editor preview, upgrade-scan list) and two lifecycles (`standing`, self-resolving; `announcement`, dismissible and from-version gated).

Detail home: GH #77

Progress: Not a prerequisite for anything (user, 2026-08-19) — FW-69/FW-70 shipped without it. Known callers at filing: #74's taxonomy carry (`standing`) and the 1.6-era converter-dropped switches (`announcement`).

Open: Entries need a `match_callback` beside declarative fields, and must contribute names to the scan's LIKE set or an advisory would be blind to its own tags.

Blocked by: —  •  Interacts with: #74, #75, #76

### FW-80 — The default rename — per-tag analogs collapse to one default value

One consistent `default` value across every tag's own analog token (`title`/`content`/`permalink`/…), instead of each tag naming its own.

Detail home: `.scratch/plans/combined-option-controls.md` §Source-analog resolution — open decisions 2 + 3; the "costs the fold nothing" assessment is `docs/design-history/src-chain-encoding.md` (2026-07-31)

Progress: Not definite (user, 2026-08-18) — filed to give it a tracked home, not to commit to it. `use:default` has zero production occurrences, so no deprecation row is due yet. The rename depends on the fold's anti-drift obligation (a slot's read enum is derived from the base tag's read definition), not the other way around, so it costs the fold nothing whenever it lands.

Open: Whether a `try_` slot ≥2 forces its analog with an explicit token; the value `featured`/`logo`/`avatar` render under (a relabelled `featured` entry vs a neutral `default`).

Blocked by: decision:whether analogs unify at all  •  Interacts with: FW-20, FW-13, FW-57, FW-34, FW-81

### FW-81 — Collapse datetime_single + datetime_range into one tag

Absorbs FW-40/41/65/68 (all retired into it 2026-08-19). Two datetime tags is a catalog-level leak of the GB "switching tag type discards all options" constraint; the fix collapses to one tag whose mode is the read COUNT rather than a stated option, encoded with the same chain-shaped `use` grammar a source chain already uses (`use:key,event_date,start_time;key,end_time`) with position as ordinal meaning.

Detail home: `.scratch/plans/datetime-tag-collapse.md`

Progress: Design converged 2026-08-18, parked — nothing committed, no ticket. No brackets needed inside the value, which is what beat four earlier candidates. Date inheritance for the common start-date-plus-time/end-time-only shape already ships independent of this row. Six options collapse to one, mechanically, with an ordinary migration (`key` on `datetime_range` is dead wire today). FW-35 confirmed independent (a boolean-flag option, not a third position). The third positional slot is closed (would reintroduce silent holes); future optional axes go named after the positional args, timezone being the plausible candidate.

Open: Whether the verb enum (`key`/`modified`/`now`) also needs `published` — a scalar default can be stripped, but a list position with siblings may force a token; if so, whether that token appears only above cardinality 1 or always.

Blocked by: —  •  Interacts with: FW-60, FW-13, FW-14 (FU-3 stacking), FW-20, FW-24, FW-64, FW-35

## Closed / Retired

Append-only ledger of closed, shipped, or cut work — both `FW-N` rows deleted from the live trackers above AND pre-tracker refactors (the legacy `C#` / GitHub-`#issue` handles from the old `project_open_refactors` memory, folded in here so there is ONE closed record). IDs are **permanent** — a retired `FW-N` is never reused or reassigned. This ledger is the only record of the FW high-water mark once shipped rows are deleted; **"next unused id" = (max `FW-N` here ∪ max `FW-N` in the live trackers) + 1**. One line per item: outcome + where it landed. Not a tracker (no blockers/interactions) — just the closed record + a pointer to detail. Kept as a table, not heading blocks, since a closed row carries none of the fields (Blocked by / Interacts with / Open) that shape earns; the linked detail home (mostly `docs/design-history/*.md`, itself the committed rationale-of-record) already carries the full build story, so entries here stay to a sentence or two of durable outcome.

| ID | Item | Outcome | Landed / detail home |
|---|---|---|---|
| FW-1 | Deprecated tag removal | Shipped 1.14.0 | CHANGELOG 1.14.0; `deprecated-tags.php` PHPDoc; memory `project_deprecated_tags_no_migration_path` |
| FW-2 | Datetime option-key cleanup | Shipped 1.15.0: single normalizer `bws_normalize_datetime_options()` — the ONLY parse point; mappers kept as portal-system compat wrappers | CHANGELOG 1.15.0; normalizer PHPDoc (datetime-tags.php); `tools/test/datetime-format-test.php` N-group |
| FW-4 | `src:site` slot for the remaining `try_` tags | Shipped 1.15.0 — pure wiring: a `try_site_fn` descriptor leg, five thin closures over `bws_site_resolve_value`, single-result site link-wrap for I6/C9 parity | CHANGELOG 1.15.0; registry PHPDoc (`try_site_fn`); `src-site-test-matrix.md` R7 |
| FW-5 | Collapse the `try_core_fn`/`try_term_fn` fork | Retired 2026-08-15 by #103 — the four hand-written `try_` arms collapsed onto one dispatch keyed by resolved source kind, through the pure table in `includes/helpers/try-slot-arms.php` | FW-71 |
| FW-6 | Datetime list mode | Shipped 1.15.0: `limit`/`sep` on both datetime tags, text/title V14 parity, `src:ref` plural fan-out | CHANGELOG 1.15.0; `tag-reference.md` §List mode; `tools/test/datetime-test-matrix.md` D4 |
| FW-10 | `src:site` → ref | Shipped 1.17.0 — the engine's `ref` step accepts a site source; a site-rooted relationship is a CHAIN (`src:site;refs,x`), not a re-exposed control | CHANGELOG 1.17.0; `bws_run_step()` PHPDoc; GH #28 |
| FW-11 | Gate wrap-capable base tags on img/picture | Cut 2026-07-21 — inert, no code shipped. No editor-reachable GB block presents `tagName` img/picture to the picker's compare | GH #31 (closed inert); `gb-constraints.md` §visibility blind spot |
| FW-12 | Custom time format on two-ended `as:time` range | Shipped 1.15.0: per-side format via the single-ended resolver chain | CHANGELOG 1.15.0; `bws_format_time_range()` PHPDoc; matrix D3 |
| FW-22 | `{{join}}` tag | Shipped 1.15.0: standalone combining tag, 10 text slots, separator + template modes, %N wire tokens. Spawned FW-43/44/45/46 | CHANGELOG 1.15.0; `tag-reference.md` §join; plan archived `docs/design-history/combine-text.md` |
| FW-32 | Primary-source + ref-hop parity | Retired 1.17.0 — its limits are discharged by chain-then-step rooting, preserved (not collapsed-to-first) fan-out, and multi-`refs` chains; the one residue is FW-39's scope | CHANGELOG 1.17.0; design record `docs/design-history/ref-hop-parity.md` |
| FW-36 | Deprecated vs Removed settings split (tags AND options) | Shipped 1.14.0 (absorbed FW-37) | CHANGELOG 1.14.0; `MigrationRegistry::is_entry_live()` PHPDoc + CONTEXT.md I10; FW-38 is the principled successor |
| FW-37 | Settings-split sub-item | Merged into FW-36 before ship | see FW-36 |
| FW-40 | `datetime_single` `use` addition | Folded into FW-81 2026-08-19 — the collapsed tag's read list IS this enum; the analog corollary carries forward as FW-81 §OPEN O7 | FW-81; `.scratch/plans/datetime-tag-collapse.md` §Encoding |
| FW-41 | Datetime key pair-combine | Overtaken by FW-81 2026-08-19 — FW-81 goes 6→1 at the same single parse site rather than 6→3 | FW-81; `.scratch/plans/datetime-tag-collapse.md` §Migration |
| FW-42 | Fixture testbed (seeded WP site + render seam) | Shipped, retired 2026-08-28. Deliverable A landed with the `core-structures` blueprint and the `wp bws render-tag --url` seam; Deliverable B got no successor row (the visible-blocks trigger enforces the same need more strongly) | `docs/testbed.md`; `tools/fixtures/core-structures/`; `CLAUDE.md` §Development |
| FW-49 | Base text/title list collection convergence + seam return-shape (link identity) | Shipped 1.16.0: shared L3 combining fold `bws_collect_value_list()` replaces four separate list loops; per-value link identity `{kind,id}\|null` + single-result link gate | CHANGELOG 1.16.0; CONTEXT.md I12; `docs/design-history/traversal-convergence-fw49.md` |
| FW-50 | Remove the `fallback_text` active read path | Shipped 1.16.0: cores read `fallback` directly; both reverse-mappers deleted; also carried GH #51's fix | CHANGELOG 1.16.0; rename row `docs/deprecated-tags-options.md`; GH #51 |
| FW-51 | try_ slot 2+ silently empty without `use` | Closed by construction, 1.17.0 — FW-57's slot fold makes the broken shape unexpressible | CHANGELOG 1.17.0; closure rationale `docs/design-history/src-chain-encoding.md` §The FLAG surface |
| FW-52 | Serialization-order decoupling (reorder normalizer + registration-unwind) | Shipped 1.16.0: canonical control order + canonical serialize order via a per-tag JS normalizer; as+size composite co-shipped | CHANGELOG 1.16.0; `serialization-order-normalizer.js` PHPDoc; `.scratch/plans/combined-option-controls.md` §Grill outcomes 2 |
| FW-56 | Multi-step src-selection encoding + authoring model | Shipped 1.17.0: wire + compile (`slot-fold.php`/`slot-fold-compile.php`), then authoring + migration on every base tag (`bws-src-chain` control) | CHANGELOG 1.17.0; `docs/design-history/src-chain-encoding.md` §SETTLED index; ADR 0005 (limit semantics); `docs/tag-reference.md` §List mode |
| FW-57 | Slot-payload fold — read-step + slot repeater | Shipped 1.17.0: one folded value per slot with the read as a sibling bracket-kv token, the repeater replacing the reveal chain, both migration paths, across `{{join}}` and all nine `try_` tags. Closes FW-51 by construction | CHANGELOG 1.17.0; `docs/design-history/src-chain-encoding.md` §SETTLED index + OPEN table |
| FW-63 | Verb-agnostic arm dispatch — base callbacks branch on resolved-source KIND, not flat src/srcTermIn tokens | Closed 2026-08-05, shipped 1.17.0: ~19 render-path arm sites across five files stopped comparing flat tokens; matrix coverage confirmed the swaps rather than assuming them. Gave BASE tags kind dispatch only — slot chains waited on FW-71 | CHANGELOG 1.17.0; `bws_fold_chain_resolution()` PHPDoc; `docs/design-history/per-step-limit.md` §Arm dispatch, sized |
| FW-65 | Whether `datetime_range` wants an inner start/end split inside its field box | Dissolved into FW-81 2026-08-19, on this row's own reasoning — FW-81 collapses six key names to one, leaving nothing to subdivide | FW-81; `bws_option_visual_groups()` PHPDoc |
| FW-68 | The five datetime keys under the slot fold | Retired into FW-81 2026-08-19, premise corrected — `try_datetime_*` has NO per-slot read axis at all (verified false that it folded source+key while four axes stayed flat); all six key axes are flat tag-level | FW-81; `.scratch/plans/datetime-tag-collapse.md` §Reframings |
| FW-69 | External sources selectable as chain ROOTS (opt-in + registration filter) | Shipped 1.17.0 (2026-08-12): `is_selectable_root()` on the source contract (default false), the `bws_dynamic_tags_chain_roots` filter route, one appender feeding both the base root enum and the slot source enum | GH #80; `docs/design-history/external-source-roots.md` |
| FW-70 | Migrate `view_*` modifier tags to `{{<base> src:view}}` | Shipped 1.17.0 (2026-08-12): `bws_migrate_modifier_root_chain()` as a WHOLE-STRING transform; registration never retires ahead of migration, so both spellings render indefinitely. External half shipped in bws-portal-system 5.7.0 | GH #80; `docs/deprecated-tags-options.md` §Modifier prefix → base tag; `docs/design-history/external-source-roots.md` |
| FW-71 | Multi-step slot sources — a slot's SOURCE *is* a base tag's source | Shipped 1.17.0 (2026-08-15, #104): `bws_fold_slot_flat_options()` deleted, replaced by chain wire in `$slot_opts['src']`. Both containers converted in the same move. Two replay-driven catches beyond the design (a legacy term-step parity gap and an inherited-hop default) were fixed before ship; the full replay obligation (build + migration + #112) discharged 2026-08-18 with numbers matching prediction exactly | CHANGELOG 1.17.0; invariant `CONTEXT.md` I16; `docs/design-history/multi-step-slot-sources.md` |
| FW-72 | Pure harness for the field-selector control | Shipped, closed 2026-08-28: `tools/test/field-combo-control-test.js` — 41 assertions over the display layer, reached with no new exports, mutation-checked | `tools/test/field-combo-control-test.js`; `docs/update-triggers.md` §Field-discovery change |
| FW-83 | `entries` carries two senses, and one has shipped | Decided 2026-08-22: the STEP slug renamed `entries`→`rows`, freeing the word for the relationship-field copy that shipped in 1.17.0. No CHANGELOG entry (no shipped control could write the old token, so the delta is zero) | `docs/deprecated-tags-options.md` §Option name renaming; `.scratch/plans/table-tag.md` §SETTLED 2026-08-22 |
| FW-84 | `src:site` slot for the two `datetime_` try_ tags | Shipped 1.18.0 as a FIX: 1.15.0's own CHANGELOG entry claimed this and silently omitted the two datetime `try_` tags. Byte-parity with the base tag confirmed on all five probed shapes | CHANGELOG 1.18.0; `src-site-test-matrix.md` §R8; `docs/design-history/src-site-stage-bc.md` |
| FW-87 | Limits bound usable results — the remaining slices | Shipped 1.18.0, reshaped in the build: the 2026-08-21 determinism reversal redefined "usable" as a source property (resolvable × exists × visible, field population removed), retiring slice C outright and folding slice B in. Open residue went to FW-88/FW-89 | CHANGELOG 1.18.0; ADR 0007; [I19]; `docs/design-history/deterministic-source-selection.md` |
| FW-94 | Loop-context identifiers follow the vocabulary | Shipped 1.19.0: `bws_get_loop_row_context()`→`bws_get_loop_item_context()`, `row_post_id`→`item_post_id`, across 64 sites in one change. Acknowledged break, no shim. Old names deliberately survive in CHANGELOG, design-history, debug-probe transcripts, and this ledger's own record | CHANGELOG 1.19.0; `bws_get_loop_item_context()` PHPDoc; `docs/plugin-integration.md` §Field helpers |
| FW-103 | Page snapshots shift when a co-resident plugin toggles | Fixed, closed 2026-08-28: the normalizer stopped capturing the document head; `env-versions.php` now records the active plugin set so a toggle is reported as a warning instead of silently absorbed | `tools/test/page-snapshots.php` rule 8; `docs/update-triggers.md` §Page-snapshot instrument |
| #21 | Editor preview: resolve-then-label | Closed 2026-05-19 (commit 9f4fa96), shipped v1.6.2 | Resolve-then-label on all base/modifier/try/datetime callbacks; CHANGELOG v1.6.2 |
| #26 | Derive try_ slot option DEFS from base builders | Closed 2026-06-26 | `bws_build_slot_traversal_options`; option-DEFINITION derivation only — see memory `project_open_refactors` residual note |
| C1 (#2) | Consolidate field extraction logic | Closed 2026-05-01, shipped v1.6.0 | `bws_read_field()`/`bws_read_term_field()` in `content-helpers.php`; CHANGELOG v1.6.0 |
| C4 (#3) | Extract post-content rendering pipeline | Closed 2026-06-01, shipped v1.8.0 | `ContentProcessor`; `bws_render_block_content()`; CHANGELOG v1.8.0 + `docs/post-content-processing-reference.md` |
| Traversal pipeline Phase 1 | Ambient-context source factory + term-kind base tags | Shipped 1.14.0 | CHANGELOG 1.14.0; `docs/design-history/traversal-pipeline.md` (later phases = FW-3/4/5/7/8) |

## Maintenance

- New non-bug idea → add a `### FW-N` heading block with the next unused id — **(highest id in the live trackers ∪ highest id in Retired IDs) + 1**; never reuse a retired id + put detail in its home (plan file / issue / memory). Don't let an item exist *only* in a hidden file with no tracker row.
- **Build starts** (branch + committed work) → **move the row to `### In flight`** and add a **Target:** line — the landing version, or `—` where no release carries the work (tooling, instruments). The row keeps its `FW-N`, its description, and all pointer/gate lines — only its section changes. Do NOT start recording phase/commit/remaining-tasks in the row; that state stays in the branch / plan / unreleased CHANGELOG (the FW-52 staleness rule). The section IS the lifecycle signal.
- Item ships (or is cut/merged) → delete its heading block (from wherever it sits, In-flight included) once CHANGELOG records it, **and append a line to the Closed/Retired table** (id + outcome + where it landed). Its `FW-N` retires — do not reassign it. Update any surviving row that referenced it (`row:FW-N` → satisfied gate can be dropped; `Interacts with` id removed).
- Blocker clears or a new interaction surfaces → update the `Blocked by:` / `Interacts with:` line; that's the point of those lines. Certainty (concept → planned) is read from the detail home, not tracked here. **Lifecycle** (future → in-flight → shipped) is read from the section, not a line.
- **A row may carry a COUNT only when the count IS the deliverable.** An *inventory* count is a worklist the row exists to hand over — FW-76's per-file citation tallies; you re-run the grep anyway, so a stale figure costs a re-count and nothing else. An *argument* count is evidence for a claim the row is making ("40 of 74 rows sit in one section, so the split has failed"), and it decays into a false statement that still reads as current — the same failure as the phase / percent-done lines the preamble bans, wearing different clothes. Strip the second kind. **Date-stamping does not rescue it:** both of the two counts FW-77 once carried were stamped, and both were read as current anyway.
