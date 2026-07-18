# Future work tracker

**Not a roadmap.** No committed timeline. This is a single visible index of
non-bug future work. Each row has a stable **`FW-N` id**, a short **item** title,
a **description**, its hard **blockers** (what must land first, typed), its
**interactions** (what it reshapes or is reshaped by, by id), and a pointer to
wherever the detail actually lives. It duplicates no detail — open the linked home
for the full design/rationale. Cross-refs use ids, never prose, so a reworded row
never orphans a reference.

- **Bugs do NOT go here** → GitHub Issues (`bug` label).
- **Enhancement detail** lives in its home (a `.claude/plans/*.md` plan file, a
  GitHub `enhancement` issue, or a memory note) — this table only tracks *that it
  exists, what gates it, what it touches, and where to read more*. The detail home
  also carries certainty (a `Concept` plan vs a locked one) — that's why there is
  no separate status column.
- Some homes are local/hidden (`.claude/plans/` is gitignored, memory files sit
  outside the working dir). This tracker is the tracked, reviewable surface over
  them. Migrate detail into `docs/` opportunistically; until then the link still
  points home.

## Column meaning

| Column | Holds |
|---|---|
| **ID** | Stable `FW-N` handle. Cross-refs use this, never prose. IDs are permanent — a shipped/cut row's ID is retired, never reused. |
| **Item** | Short title. |
| **Description** | The detail + rationale hooks. |
| **Blocked by** | Hard prerequisite, typed: `row:FW-N` (another row) · `ship:X.Y.Z` (a version — satisfied once shipped) · `decision:<what>` (an open choice) · `code:<condition>` (a code state). `—` = unblocked. |
| **Interacts with** | Softer coupling (reshapes / reshaped-by / ship-near) as `FW-N` ids + external `#issue`. Not a gate. |
| **Detail home** | Where the design/rationale + implicit certainty (concept vs planned) live. |

> **Agent pickup:** a row is startable when `Blocked by` is `—` or every `row:`/`ship:` gate it names is satisfied. `decision:`/`code:` gates are human-resolved — don't auto-start those. `Interacts with` never blocks.

## Trackers

### Correctness, Consistency, Architecture

| ID | Item | Description | Blocked by | Interacts with | Detail home |
|---|---|---|---|---|---|
| FW-3 | Route datetime through the L1/L2 seam | `bws_resolve_field_values` — retire the `'option'`-in-post_id param-overload (datetime-tags.php:~1005, the resolved-source-smuggled-through-id-arg contradiction, CONTEXT §Language). Closes the datetime **term-ambient gap** deferred in Phase 1 (T6): datetime base tags stay post-only today (honest-empty on a term archive, no stale-post leak). Two separable halves (assessed 2026-07-09): **(a)** term-ambient parity via callback-level kind dispatch riding the shipped factory (base text/title pattern; UNBLOCKED since 1.14.0) — but done cheaply it would smuggle `term_X` through the same id arg, DEEPENING the contradiction; done right it rethreads a resolved source through the datetime cores + parse helpers (ACF object-id resolver exists, field-helpers.php:529) ≈ most of the local work of (b). **(b)** full seam routing — gated: datetime reads value + field-object formats per key, seam return is frozen `string[]` → needs a format-aware read arm. Gate corrected 2026-07-09 — was wrongly listed as FW-4's content/image/analog arms. | `code:seam gains format-aware (value + field-object) read arm` — half (b) only; half (a) unblocked but see contradiction note | FW-4, FW-35 | `.claude/plans/traversal-pipeline.md` §Post-Phase-1 convergence |
| FW-4 | `src:site` slot for the remaining `try_` tags | `try_text`/`try_title`/`try_content`/`try_image`/`try_permalink` (today only `try_email`/`try_phone` set `try_allow_site_slot` — they route their `try_core_fn` through the SEAM, which has a site arm; the others route through site-blind post/term cores). Reachable ONLY when those templates' try dispatch rides a kind-aware seam that reads site — but `content`, `image`, `title`-analog are NOT value-list reads, so gated on the content/image/analog seam expansion (its OWN expansion — distinct from FW-3's format-aware arm; wording corrected 2026-07-09). Surfaced Phase 1 T8. NOT reachable by the fork-collapse alone. | `code:seam gains content/image/analog arms` | FW-3, FW-5 | `.claude/plans/traversal-pipeline.md` §Post-Phase-1 convergence |
| FW-5 | Collapse the `try_core_fn`/`try_term_fn` fork | Paired post-core/term-core dispatch fns per template → ONE kind-dispatching try handler. DEFERRED from Phase 1 T8 (scope narrowed to term-ambient parity, the reachable I6/C9 fix). Pure structural cleanup (deletes ~7 paired `bws_try_*_post_dispatch`/`bws_try_*_term_dispatch` fns), high V8 byte-identity risk in the densest callback, zero behavior gain on its own. Best AFTER the seam expansion proves the unified kind-dispatch shape — the collapse target is a seam-routed handler, not a hand-merged leaf-fn pair. | `code:seam expansion proves unified kind-dispatch shape` | FW-4, FW-3 | `.claude/plans/traversal-pipeline.md` §Post-Phase-1 convergence |
| FW-7 | Collapse `bws_read_field`'s internal loop/term-archive resolution | field-helpers.php:271-296 → into the source factory. Surfaced during Phase 1 (T4/V12): the seam already bypasses it via explicit id; once the wrapper routes all ~30 callers through the factory, that inference duplicates the factory everywhere. NOT Phase 1 (touches the 30-caller read path). | `code:wrapper routes all callers through factory` | FW-8 | `.claude/plans/traversal-pipeline.md` §Post-Phase-1 convergence |
| FW-8 | Fold `bws_reliable_term_context_detection` into `bws_capture_ambient_signals` | Two term-detection impls coexist (5-tier in taxonomy-helpers + factory signals; surfaced Phase 1); factory is the intended home. NOT Phase 1 (used by `TaxonomyTerm::resolve_id` + `term_` modifiers → widens blast radius mid-refactor). | `row:FW-7` | FW-33 | `.claude/plans/traversal-pipeline.md` §Post-Phase-1 convergence |
| FW-43 | Verb-agnostic slot-resolver extraction | Parameterized fold (selecting=first-wins \| combining=collect-all) + reveal-shape over the shared slot machinery. try_'s resolve loop + `show_if_any` reveal remain inline/selecting-shaped in `generate_base_try_tags()` (#26 extracted only option DEFINITIONS); `{{join}}` (1.15.0) is the second instance — its own collect-all loop + combining reveal in `bws_join_callback()`/`bws_get_join_options()` now shape the extraction target. Third instance: the datetime list collect loop (`bws_datetime_collect_list()`, 1.15.0 #30) — already extracted and datetime-agnostic in shape (slice/skip/join/first), a natural seed for the shared fold. Also the natural home for un-hardcoding the `same`-use prepend (join couldn't take `per_slot_use` from the shared emit because of it). | — | FW-4, FW-5 | `.claude/plans/archive/combine-text.md` §Build locality; memory `project_open_refactors.md` |
| FW-9 | Context-aware base tags across remaining WP contexts | author / date / search / PTA / 404 / latest-home (Q5/Q7). Term kind SHIPPED 1.14.0 with Phase 1. | `decision:per-kind option surface` | FW-33 | GH #19 + `.claude/plans/context-aware-base-tags.md` |
| FW-10 | `src:site` → ref | Resolve an entity from a site-stored relational field. | — | FW-32 | GH #28 |
| FW-11 | Gate wrap-capable base tags on img/picture | text/title/datetime via native visibility. | — | — | GH #31 |

### Feature follow-ups & UX

| ID | Item | Description | Blocked by | Interacts with | Detail home |
|---|---|---|---|---|---|
| FW-13 | Smart field selector v2/v3 | **v1 SHIPPED 1.13.0** (discovery-backed `bws-field-combo` replacing GB `key`/`ref`/`linkKey`/datetime-key text inputs; ACF + sub-fields + options-page + term-meta + registered meta; flat searchable list, two filters [location path + type], kind/group-dynamic label, free-text + clear; REST `bws-dynamic-tags/v1/fields` inlined per editor load, registration-time not value-time; offered⟺resolvable). **Remaining:** v2 type-priority (recommend-divider OR multi-select Filter 2; `ref`→relationship+post_object; `src:ref` hopped-PT scope; smarter Filter-1 preset; dynamic label on `ref`; custom combobox widget for reopen-highlight); v3 Pie Calendar; v-future pick-a-post-to-scan. | — | FW-14, FW-20 | `.claude/plans/field-selector.md` |
| FW-14 | Field-selector post-v1 follow-ups | Ultra review 2026-07-06: **FU-1** location filter carries kind/group as structured data, not a parsed display string (fixes `' › '` delimiter forgery + locale-fragile kind inference; G2 parenthetical-strip already band-aided); **FU-2** `bws_field_key_option()` factory for the ~14 hand-copied option flips (fold into v2); **FU-3** shared filter set for the datetime_ key controls (4 stacked filter pairs on `datetime_range` → one tag-level set; needs a cross-control state channel since GB renders each option control independently). All low-severity. | — | FW-13, FW-41 (pair-combine halves FU-3 stacking) | `.claude/plans/field-selector.md` §Post-v1 follow-ups |
| FW-15 | `{{phone}}` follow-ups | display format, ext/type affix, per-country rules, `use` enum, per-tag `cc:`, lenient passthrough, vanity/spelled display. | — | — | `.claude/plans/phone-tag-future.md` |
| FW-16 | `{{call}}` v2 ergonomics cluster | pretty `$meta['label']` in select/mirror; `post_id_arg` registration-repoint (post_id-aware-but-not-first fns); multi-arg `args:` single-control (CSV/typed serialization, comma-in-value escaping); `arg:` enum-constraint (`$meta` whitelist); allowlist shape B/C (DB allowlist gated by the filter; A extends non-breakingly); shortcode-replacement ambition. All non-breaking — v1 storage is already associative. | — | FW-24 (multi-arg CSV shares tech) | `.claude/plans/archive/fn-passthrough-tag.md` §Deferred |
| FW-17 | Src-dynamic use-entry labels (V10a) | Relabel select `options[]` by active source. | — | FW-18 | GH #33 |
| FW-18 | Per-value `show_if` gating for select `options[]` | | — | FW-17 | GH #27 |
| FW-19 | Base-tag distinguishing suffixes | e.g. "Text (cross-source)". | — | — | Under consideration |
| FW-38 | Explicit `registered_by` + `lifecycle` entry fields (retire the callback proxy) | Path Y follow-on to the FW-36 split (shipped 1.14.0) / its §B1 correction. Today tag box-placement leans on callback-presence as a de-facto "internal-removed vs external-still-registered" marker, with a `prefix_removed` override bolted on (interim; `MigrationRegistry::is_entry_live()` PHPDoc + CONTEXT.md I10). Principled replacement: record `registered_by` (internal vs external plugin id) and `lifecycle` (`unset=active` \| `deprecated` \| `removed`) at `register()` time; box placement reads `lifecycle` only, callback becomes irrelevant to classification, internal-removed entries carry an explicit `removed` marker. Feeds portal-system coordination (external declares its own `registered_by` + `lifecycle`; handoff in `bws-portal-system/.claude/plans/prefix-removed-handoff.md`). NOT this release. | — | — | memory `project_registered_by_lifecycle.md`; CONTEXT.md I10 (interim state it replaces) |

### Testing & infrastructure

| ID | Item | Description | Blocked by | Interacts with | Detail home |
|---|---|---|---|---|---|
| FW-42 | Fixture testbed (seeded WP site + render seam) | Dedicated browsable fixture site on the wp-litespeed env; blueprint-composing seed (`tools/fixtures/<blueprint>/` = manifest.php data contract + schema.php + seed.php; env `bin/seed.sh` runner) so sibling plugins (portal-system, Meta Conductor) compose shared infrastructure while owning their fixtures; `wp bws render-tag --url` seam (spike PROVEN 2026-07-16, `tools/debug/spike-render-seam.php`) makes discovery rows + manual matrix rows one CLI call. Deliverable A = seed the two existing matrices' state; B accretes per-tag manifests (spec work). | — | FW-3 (datetime term-ambient row is FW-3(a)'s pre-written acceptance test), FW-13 (editor surface eyeball env) | `.claude/plans/fixture-testbed.md` |

### Future possibilities

| ID | Item | Description | Blocked by | Interacts with | Detail home |
|---|---|---|---|---|---|
| FW-20 | Combined option controls | `use:key,field` serialization combine (`from:type,key` selector+field fold) — SEPARATE from FW-13 (this is the wire change, that is discovery). | — | FW-13, FW-40, FW-41 | `.claude/plans/combined-option-controls.md` (srcTermIn part shipped v1.6.0) |
| FW-21 | Add sources to GB core tags via JS filters | | — | — | `.claude/plans/gb-tag-extension.md` |
| FW-23 | Base text tag: treat `'0'` as empty (opt-in) | Augments hooks.php:37 preserve-zero — absorbed by `{{join}}` slots for free (yields the athletics `5'` height suppression). | — | — | `tag-reference.md` §join "'0' is a real value" (context); `.claude/plans/archive/combine-text.md` §Empty-value detection |
| FW-24 | Tag-in-slot composition | Slots hold whole base tags → heterogeneous join/try. NB the nested-braces syntax can never ride the wire (GB kills any `}` in options — `gb-constraints.md` §Tag-string-unsafe values); encoding must stay flat. | — | FW-25, FW-16 | memory `deferred_features.md` (north-star for #26) |
| FW-25 | Multislot-only field options | Gate a `use` value to slot ≥2. | — | FW-24 (cheaper alt) | memory `deferred_features.md` |
| FW-26 | `{{if}}` conditional tag | THIRD composition verb (selecting=try / combining=join / conditional=if): branch a template/value on a READ field value. Driver: athletics `bws_get_game_result` (term-name → template). NOT a join requirement. | — | FW-27, FW-28 | memory `deferred_features.md` (loose concept, no plan) |
| FW-27 | `if:` as a BASE-TAG OPTION | Lighter alt to FW-26 — `srcTermIn`-style trigger + `show_if` predicate grammar server-side: self-gate one tag's output, OR adjacency-exclusion. Propagates: try inherits per-tag free, join per-slot (rides the same `{N}-` slot encoding join/try_ use now; an IF'd-out join slot should likely collapse like an empty one). No-ELSE = parity w/ GB Conditions + Block Visibility. | — | FW-26 | memory `deferred_features.md` (spitball, no design) |
| FW-28 | Composition-of-composers | Nest {{join}}/{{try}}/{{if}}. Runtime nesting trivial (composer callback resolves children from its options; GB sees only outer tag). `@name` reference NOT viable (GB stateless — verified). Only nested RESOLUTION works; AUTHORING-UI is the gating cost. Models: true-recursive vs one-level. | `decision:authoring-UI model` | FW-26, FW-29 | memory `deferred_features.md` (nesting tension) |
| FW-29 | Admin-built composite tag | `{{custom}}` + template selector. Counter to in-block nesting: build the over-complex tag in an admin UI, persist server-side (named template, `{{call}}`-store precedent), reference via `{{custom tpl:name}}`. Sidesteps flat-options serialization wall. May be the authoring SUBSTRATE for heterogeneous join/if/try via a `tpl:` option. | — | FW-28 (substrate) | memory `deferred_features.md` (counter-concept + substrate spitball, no design) |
| FW-30 | Block editor sidebar migration tool | | — | FW-31 | memory `deferred_features.md` |
| FW-31 | GB ↔ BWS tag cross-converter | | — | FW-30 | memory `deferred_features.md` |
| FW-32 | Primary-source + ref-hop parity | `src:ref` hops only from a detected origin today; need pick-primary-then-hop, incl. ref-hop off an ID source. UX-open: likely a separate ref-step option per-`src`-value. Unifies with the ID source (FW-39). | — | FW-10, FW-9 | `.claude/plans/traversal-pipeline.md` §Problem (parity gap) |
| FW-33 | `term_` deprecation path | Subsumed by base + context-aware #19 + the ID source (FW-39); re-add registry-only after. NB `view_` does NOT follow — external plugin, may stay even when `src:view` lands. | `row:FW-9`, `code:ID source lands` | FW-8 | memory `project_term_deprecation_path.md` |
| FW-34 | Configurable default field keys per source × tag-type | | — | — | GH #29 (memory `project_default_field_keys.md`) |
| FW-35 | datetime_ all-day boolean field | Read `_piecal_is_allday` / ACF `true_false`, trim to date-only and/or show a note (each toggleable). | — | FW-3 | GH #41 |
| FW-39 | ID source | New source flavor: author identifies ONE specific entity, its id serialized into the token (probable `src:<type>,<ID>`, NOT final). The author-supplied-entity-id pole of the source-binding model. Editor = pick-a-post/term. Home for the "specific-resource + site fallback" the #37 modifier filter left homeless; belongs in a try_ chain slot (`try_allow_site_slot`), NOT a `try_term_` form. UX-open: ref-step decoupling (per-`src` ref option). | — | FW-32, FW-33, FW-9 | CONTEXT.md §Language "Source binding" (concept + two-axis model); no plan/issue yet |
| FW-40 | `datetime_single` `use` addition | `use` enum `modified` \| `key`; post source resolves the `post_date` analog bare (stripped default, `??` recovery — `published` is NOT a token, analog corollary); term/site have no native date → key required. Wire shape gated on the global-vs-scoped combine decision. Lowest-priority of the datetime cluster. | `decision:global-vs-scoped combine` | FW-41, FW-20, FW-3 | `.claude/plans/combined-option-controls.md` §Datetime application (single) |
| FW-44 | join per-slot inner list `sep` (`{N}-sep`) | A list-mode join slot joins its own items with text's default `', '` (ADR 0003); tuning it per slot requires first renaming join's tag-level assembly `sep` (slot-1 bare `sep` collides on GB's flat option map — likely `glue`), a wire-visible rename. Edge affordance; take only on evidence. | `decision:assembly-sep rename` | FW-43 | `docs/adr/0003-join-per-slot-limit-not-sep.md` |
| FW-45 | join dynamic slot count | Drop the fixed `BWS_JOIN_MAX_SLOTS` (10) for an add-slot editor control (arbitrary N). The constant is threaded through resolve loop / token scan / option emit / reveal chain so the cap raise itself is one change; the CONTROL is the blocker. | `code:custom editor-control work (docs/editor-controls.md reserved owner)` | FW-24 | `.claude/plans/archive/combine-text.md` §Slot count |
| FW-46 | Name-format preset over join | Canned "Full name" preset pre-filling `mode:template` + the 7-part format + slot keys — pure config sugar, no new resolve path. LEANED direction vs a dedicated `{{name}}` tag (parked: "name" collides with term-name/post-name/repeater-subfield semantics; revisit only on persistent mis-wiring AND a collision-free name, e.g. `{{person}}`). | — | FW-29 (preset-authoring substrate) | `.claude/plans/archive/combine-text.md` §Open/deferred (both ends recorded) |
| FW-41 | Datetime key pair-combine | Fold the time key into its partner date key, comma-appended: `key:date,time` (single), `startKey`/`endKey` each `date,time` (range). Six key options → three; halves FU-3 filter stacking; composite control = srcTermIn pattern instance #3; public-key rename → migration rows. Independent of the `use` fold (FW-40) and of global-vs-scoped. FW-2 (shipped 1.15.0) delivered the single normalizer (`bws_normalize_datetime_options`), so this lands as a one-site parse change there. | — | FW-14, FW-20, FW-40, FW-13 | `.claude/plans/combined-option-controls.md` §Lighter variant — keys-only pair-combine |

## Closed / Retired

Append-only ledger of closed, shipped, or cut work — both `FW-N` rows deleted from the live
table above AND pre-tracker refactors (the legacy `C#` / GitHub-`#issue` handles from the old
`project_open_refactors` memory, folded in here so there is ONE closed record). IDs are
**permanent** — a retired `FW-N` is never reused or reassigned. This ledger is the only record
of the FW high-water mark once shipped rows are deleted; **"next unused id" = (max `FW-N` here ∪
max `FW-N` in the live table) + 1**. One line per item: outcome + where it landed. Not a tracker
(no blockers/interactions) — just the closed record + a pointer to detail.

| ID | Item | Outcome | Landed / detail home |
|---|---|---|---|
| FW-1 | Deprecated tag removal | Shipped 1.14.0 | CHANGELOG 1.14.0; `deprecated-tags.php` PHPDoc; memory `project_deprecated_tags_no_migration_path` |
| FW-22 | `{{join}}` tag | Shipped 1.15.0 (standalone combining tag, 10 text slots, separator + template modes, %N wire tokens). Spawned FW-43/44/45/46. | CHANGELOG 1.15.0; `tag-reference.md` §join; `tools/test/join-test-matrix.md` + `join-template-test.php`; plan archived `.claude/plans/archive/combine-text.md` |
| FW-36 | Deprecated vs Removed settings split (tags AND options) | Shipped 1.14.0 (absorbed FW-37) | CHANGELOG 1.14.0; `MigrationRegistry::is_entry_live()` PHPDoc + CONTEXT.md I10; FW-38 is the principled successor |
| FW-37 | Settings-split sub-item | Merged into FW-36 before ship | see FW-36 |
| C1 (#2) | Consolidate field extraction logic | Closed 2026-05-01, shipped v1.6.0 | `bws_read_field()`/`bws_read_term_field()` in `content-helpers.php` (route through `GenerateBlocks_Meta_Handler`); CHANGELOG v1.6.0 |
| C4 (#3) | Extract post-content rendering pipeline | Closed 2026-06-01, shipped v1.8.0 | `ContentProcessor` (`includes/classes/content/class-content-processor.php`); `bws_render_block_content()`; CHANGELOG v1.8.0 + `docs/post-content-processing-reference.md` |
| #21 | Editor preview: resolve-then-label | Closed 2026-05-19 (commit 9f4fa96), shipped v1.6.2 | Resolve-then-label on all base/modifier/try/datetime callbacks; CHANGELOG v1.6.2 |
| #26 | Derive try_ slot option DEFS from base builders | Closed 2026-06-26 | `bws_build_slot_traversal_options`; option-DEFINITION derivation only (NOT the resolve loop / `show_if_any`) — see memory `project_open_refactors` residual note |
| Traversal pipeline Phase 1 | Ambient-context source factory + term-kind base tags | Shipped 1.14.0 | CHANGELOG 1.14.0; `.claude/plans/traversal-pipeline.md` (later phases = FW-3/4/5/7/8) |
| FW-2 | Datetime option-key cleanup | Shipped 1.15.0 (datetime pass, #48): single normalizer `bws_normalize_datetime_options()` — the ONLY parse point; mappers kept as portal-system compat wrappers; FW-41 lands as a one-site parse change | CHANGELOG 1.15.0; normalizer PHPDoc (datetime-tags.php); `tools/test/datetime-format-test.php` N-group |
| FW-6 | Datetime list mode | Shipped 1.15.0 (GH #30, #48): `limit`/`sep` on both datetime tags, text/title V14 parity, src:ref plural fan-out; collect loop FW-3-shaped (ACF-object-id items) | CHANGELOG 1.15.0; `tag-reference.md` §List mode; `tools/test/datetime-test-matrix.md` D4 |
| FW-12 | Custom time format on two-ended `as:time` range | Shipped 1.15.0 (GH #25, #48): per-side format via the single-ended resolver chain; consolidation gated to 12-hour formats | CHANGELOG 1.15.0; `bws_format_time_range()` PHPDoc; matrix D3 |

## Maintenance

- New non-bug idea → add a row with the next unused `FW-N` — **(highest id in the live table
  ∪ highest id in Retired IDs) + 1**; never reuse a retired id + put detail in its home (plan
  file / issue / memory). Don't let an item exist *only* in a hidden file with no tracker row.
- Item ships (or is cut/merged) → delete its row once CHANGELOG records it, **and append a line
  to Retired IDs** (id + outcome + where it landed). Its `FW-N` retires — do not reassign it.
  Update any surviving row that referenced it (`row:FW-N` → satisfied gate can be dropped;
  `Interacts with` id removed).
- Blocker clears or a new interaction surfaces → update the cell; that's the point
  of those columns. Certainty (concept → planned) is read from the detail home,
  not tracked here.
