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
| FW-1 | Deprecated tag removal | Drop the three deprecated tag families; re-add as registry-only after. | — | FW-2, FW-33 | `.claude/plans/currently-deprecated-tags-work-quiet-snail.md` (branch `deprecated-tag-removal`) |
| FW-2 | Datetime option-key cleanup | Rename/normalize the datetime option keys. | `row:FW-1` | FW-3 | memory `project_open_refactors.md` |
| FW-3 | Route datetime through the L1/L2 seam | `bws_resolve_field_values` — retire the `'option'`-in-post_id param-overload (datetime-tags.php:~1005, the resolved-source-smuggled-through-id-arg contradiction, CONTEXT §Language). Closes the datetime **term-ambient gap** deferred in Phase 1 (T6): datetime base tags stay post-only today (honest-empty on a term archive, no stale-post leak) and gain term/site kind-awareness for free once they ride the seam's kind dispatch (V12). | `code:seam gains content/image/analog arms` | FW-2, FW-4, FW-6, FW-35 | `.claude/plans/traversal-pipeline.md` §Post-Phase-1 convergence |
| FW-4 | `src:site` slot for the remaining `try_` tags | `try_text`/`try_title`/`try_content`/`try_image`/`try_permalink` (today only `try_email`/`try_phone` set `try_allow_site_slot` — they route their `try_core_fn` through the SEAM, which has a site arm; the others route through site-blind post/term cores). Reachable ONLY when those templates' try dispatch rides a kind-aware seam that reads site — but `content`, `image`, `title`-analog are NOT value-list reads, so gated on the SAME seam expansion as FW-3. Surfaced Phase 1 T8. NOT reachable by the fork-collapse alone. | `code:seam gains content/image/analog arms` | FW-3, FW-5 | `.claude/plans/traversal-pipeline.md` §Post-Phase-1 convergence |
| FW-5 | Collapse the `try_core_fn`/`try_term_fn` fork | Paired post-core/term-core dispatch fns per template → ONE kind-dispatching try handler. DEFERRED from Phase 1 T8 (scope narrowed to term-ambient parity, the reachable I6/C9 fix). Pure structural cleanup (deletes ~7 paired `bws_try_*_post_dispatch`/`bws_try_*_term_dispatch` fns), high V8 byte-identity risk in the densest callback, zero behavior gain on its own. Best AFTER the seam expansion proves the unified kind-dispatch shape — the collapse target is a seam-routed handler, not a hand-merged leaf-fn pair. | `code:seam expansion proves unified kind-dispatch shape` | FW-4, FW-3 | `.claude/plans/traversal-pipeline.md` §Post-Phase-1 convergence |
| FW-6 | datetime list mode | `limit` + `sep` on datetime. | — | FW-3 | GH #30 |
| FW-7 | Collapse `bws_read_field`'s internal loop/term-archive resolution | field-helpers.php:271-296 → into the source factory. Surfaced during Phase 1 (T4/V12): the seam already bypasses it via explicit id; once the wrapper routes all ~30 callers through the factory, that inference duplicates the factory everywhere. NOT Phase 1 (touches the 30-caller read path). | `code:wrapper routes all callers through factory` | FW-8 | `.claude/plans/traversal-pipeline.md` §Post-Phase-1 convergence |
| FW-8 | Fold `bws_reliable_term_context_detection` into `bws_capture_ambient_signals` | Two term-detection impls coexist (5-tier in taxonomy-helpers + factory signals; surfaced Phase 1); factory is the intended home. NOT Phase 1 (used by `TaxonomyTerm::resolve_id` + `term_` modifiers → widens blast radius mid-refactor). | `row:FW-7` | FW-33 | `.claude/plans/traversal-pipeline.md` §Post-Phase-1 convergence |
| FW-9 | Context-aware base tags across remaining WP contexts | author / date / search / PTA / 404 / latest-home (Q5/Q7). Term kind SHIPPED 1.14.0 with Phase 1. | `decision:per-kind option surface` | FW-33 | GH #19 + `.claude/plans/context-aware-base-tags.md` |
| FW-10 | `src:site` → ref | Resolve an entity from a site-stored relational field. | — | FW-32 | GH #28 |
| FW-11 | Gate wrap-capable base tags on img/picture | text/title/datetime via native visibility. | — | — | GH #31 |
| FW-12 | Custom time format on two-ended `as:time` range | | — | — | GH #25 |

### Feature follow-ups & UX

| ID | Item | Description | Blocked by | Interacts with | Detail home |
|---|---|---|---|---|---|
| FW-13 | Smart field selector v2/v3 | **v1 SHIPPED 1.13.0** (discovery-backed `bws-field-combo` replacing GB `key`/`ref`/`linkKey`/datetime-key text inputs; ACF + sub-fields + options-page + term-meta + registered meta; flat searchable list, two filters [location path + type], kind/group-dynamic label, free-text + clear; REST `bws-dynamic-tags/v1/fields` inlined per editor load, registration-time not value-time; offered⟺resolvable). **Remaining:** v2 type-priority (recommend-divider OR multi-select Filter 2; `ref`→relationship+post_object; `src:ref` hopped-PT scope; smarter Filter-1 preset; dynamic label on `ref`; custom combobox widget for reopen-highlight); v3 Pie Calendar; v-future pick-a-post-to-scan. | — | FW-14, FW-20 | `.claude/plans/field-selector.md` |
| FW-14 | Field-selector post-v1 follow-ups | Ultra review 2026-07-06: **FU-1** location filter carries kind/group as structured data, not a parsed display string (fixes `' › '` delimiter forgery + locale-fragile kind inference; G2 parenthetical-strip already band-aided); **FU-2** `bws_field_key_option()` factory for the ~14 hand-copied option flips (fold into v2); **FU-3** shared filter set for the datetime_ key controls (4 stacked filter pairs on `datetime_range` → one tag-level set; needs a cross-control state channel since GB renders each option control independently). All low-severity. | — | FW-13 | `.claude/plans/field-selector.md` §Post-v1 follow-ups |
| FW-15 | `{{phone}}` follow-ups | display format, ext/type affix, per-country rules, `use` enum, per-tag `cc:`, lenient passthrough, vanity/spelled display. | — | — | `.claude/plans/phone-tag-future.md` |
| FW-16 | `{{call}}` v2 ergonomics cluster | pretty `$meta['label']` in select/mirror; `post_id_arg` registration-repoint (post_id-aware-but-not-first fns); multi-arg `args:` single-control (CSV/typed serialization, comma-in-value escaping); `arg:` enum-constraint (`$meta` whitelist); allowlist shape B/C (DB allowlist gated by the filter; A extends non-breakingly); shortcode-replacement ambition. All non-breaking — v1 storage is already associative. | — | FW-24 (multi-arg CSV shares tech) | `.claude/plans/archive/fn-passthrough-tag.md` §Deferred |
| FW-17 | Src-dynamic use-entry labels (V10a) | Relabel select `options[]` by active source. | — | FW-18 | GH #33 |
| FW-18 | Per-value `show_if` gating for select `options[]` | | — | FW-17 | GH #27 |
| FW-19 | Base-tag distinguishing suffixes | e.g. "Text (cross-source)". | — | — | Under consideration |

### Future possibilities

| ID | Item | Description | Blocked by | Interacts with | Detail home |
|---|---|---|---|---|---|
| FW-20 | Combined option controls | `use:key,field` serialization combine (`from:type,key` selector+field fold) — SEPARATE from FW-13 (this is the wire change, that is discovery). | — | FW-13 | `.claude/plans/combined-option-controls.md` (srcTermIn part shipped v1.6.0) |
| FW-21 | Add sources to GB core tags via JS filters | | — | — | `.claude/plans/gb-tag-extension.md` |
| FW-22 | `{{join}}` tag | standalone combining tag (NOT a modifier, NOT a base tag; absorbs base text per slot). v1 text-only, per-slot src/use, separator + template modes. On ship, spawns verb-agnostic resolver-extraction (`combine-text.md` §residual-3 — file as issue then). | — | FW-23, FW-24, FW-27 | `.claude/plans/combine-text.md` (grill-hardened 2026-06-26) |
| FW-23 | Base text tag: treat `'0'` as empty (opt-in) | Augments hooks.php:37 preserve-zero — absorbed by `{{join}}`, yields athletics `5'` height suppression. | — | FW-22 | `.claude/plans/combine-text.md` §Empty-value detection |
| FW-24 | Tag-in-slot composition | Slots hold whole base tags → heterogeneous join/try. | — | FW-22, FW-25, FW-16 | memory `deferred_features.md` (north-star for #26) |
| FW-25 | Multislot-only field options | Gate a `use` value to slot ≥2. | — | FW-24 (cheaper alt) | memory `deferred_features.md` |
| FW-26 | `{{if}}` conditional tag | THIRD composition verb (selecting=try / combining=join / conditional=if): branch a template/value on a READ field value. Driver: athletics `bws_get_game_result` (term-name → template). NOT a join requirement. | — | FW-22, FW-27, FW-28 | memory `deferred_features.md` (loose concept, no plan) |
| FW-27 | `if:` as a BASE-TAG OPTION | Lighter alt to FW-26 — `srcTermIn`-style trigger + `show_if` predicate grammar server-side: self-gate one tag's output, OR adjacency-exclusion. Propagates: try inherits per-tag free, join per-slot from v1 (rides the same N-option slot encoding `try_` uses now). No-ELSE = parity w/ GB Conditions + Block Visibility. | — | FW-26, FW-22 | memory `deferred_features.md` (spitball, no design) |
| FW-28 | Composition-of-composers | Nest {{join}}/{{try}}/{{if}}. Runtime nesting trivial (composer callback resolves children from its options; GB sees only outer tag). `@name` reference NOT viable (GB stateless — verified). Only nested RESOLUTION works; AUTHORING-UI is the gating cost. Models: true-recursive vs one-level. | `decision:authoring-UI model` | FW-22, FW-26, FW-29 | memory `deferred_features.md` (nesting tension) |
| FW-29 | Admin-built composite tag | `{{custom}}` + template selector. Counter to in-block nesting: build the over-complex tag in an admin UI, persist server-side (named template, `{{call}}`-store precedent), reference via `{{custom tpl:name}}`. Sidesteps flat-options serialization wall. May be the authoring SUBSTRATE for heterogeneous join/if/try via a `tpl:` option. | — | FW-28 (substrate) | memory `deferred_features.md` (counter-concept + substrate spitball, no design) |
| FW-30 | Block editor sidebar migration tool | | — | FW-31 | memory `deferred_features.md` |
| FW-31 | GB ↔ BWS tag cross-converter | | — | FW-30 | memory `deferred_features.md` |
| FW-32 | Primary-source + ref-hop parity | `src:ref` hops only from ambient today; need pin-primary-then-hop. UX-open: likely a separate ref-step option per-`src`-value. Unifies with pinned-resource source. | — | FW-10, FW-9 | `.claude/plans/traversal-pipeline.md` §Problem (parity gap) |
| FW-33 | `term_` deprecation path | Subsumed by base + context-aware #19 + pinned-resource source; re-add registry-only after. NB `view_` does NOT follow — external plugin, may stay even when `src:view` lands. | `row:FW-9`, `code:pinned-resource source lands` | FW-8, FW-1 | memory `project_term_deprecation_path.md` |
| FW-34 | Configurable default field keys per source × tag-type | | — | — | GH #29 (memory `project_default_field_keys.md`) |
| FW-35 | datetime_ all-day boolean field | Read `_piecal_is_allday` / ACF `true_false`, trim to date-only and/or show a note (each toggleable). | — | FW-3 | GH #41 |

## Maintenance

- New non-bug idea → add a row with the next unused `FW-N` (highest id + 1; never
  reuse a retired id) + put detail in its home (plan file / issue / memory). Don't
  let an item exist *only* in a hidden file with no tracker row.
- Item ships → delete its row once CHANGELOG records it. Its `FW-N` retires — do
  not reassign it. Update any surviving row that referenced it (`row:FW-N` →
  satisfied gate can be dropped; `Interacts with` id removed).
- Blocker clears or a new interaction surfaces → update the cell; that's the point
  of those columns. Certainty (concept → planned) is read from the detail home,
  not tracked here.
