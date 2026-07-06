# Future work — tracker

**Not a roadmap.** No committed timeline. This is a single visible index of
non-bug future work, with a certainty marker per item and a pointer to wherever
the detail actually lives. It duplicates no detail — open the linked home for the
full design/rationale.

- **Bugs do NOT go here** → GitHub Issues (`bug` label).
- **Enhancement detail** lives in its home (a `.claude/plans/*.md` plan file, a
  GitHub `enhancement` issue, or a memory note) — this table only tracks *that it
  exists, how certain it is, and where to read more*.
- Some homes are local/hidden (`.claude/plans/` is gitignored, memory files sit
  outside the working dir). This tracker is the tracked, reviewable surface over
  them. Migrate detail into `docs/` opportunistically; until then the link still
  points home.

## Status vocabulary

| Tag | Meaning |
|---|---|
| **Planned** | Design locked; intend to build. |
| **Likely** | Wanted, design mostly there, not locked. |
| **Exploratory** | Being figured out; shape still moving. |
| **Concept** | Idea + direction only; shape unclear. |
| **Deferred** | Sound, but no near-term intent (may stay here a long time). |

> A plan file existing does **not** mean it will ship as designed — status is the
> certainty signal, not the presence of a plan.

## Tracker

| Item | Status | Detail home |
|---|---|---|
| Phone tag follow-ups (display format, ext/type affix, per-country rules, `use` enum, per-tag `cc:`, lenient passthrough, vanity/spelled display) | Exploratory (mixed) | `.claude/plans/phone-tag-future.md` |
| Verb-agnostic slot RESOLVER extraction (parameterize fold selecting\|combining + reveal-shape) — successor to #26, which did option-defs only; shaped by {{join}} as 2nd instance | Concept | `.claude/plans/combine-text.md` §residual-3 (file as issue once join ships) |
| Gate wrap-capable base tags (text/title/datetime) on img/picture via native visibility | Likely | GH #31 |
| Context-aware base tag resolution across all WP contexts (Q5/Q7) | Likely | GH #19 + `.claude/plans/context-aware-base-tags.md` |
| Configurable default field keys per source × tag-type | Concept | GH #29 (memory `project_default_field_keys.md`) |
| src:site → ref: resolve entity from a site-stored relational field | Exploratory | GH #28 |
| datetime list mode (limit + sep) | Likely | GH #30 |
| Custom time format on two-ended as:time range | Deferred | GH #25 |
| datetime_ all-day boolean field: read `_piecal_is_allday` / ACF `true_false`, trim to date-only and/or show a note (each toggleable) | Concept | GH #41 |
| Src-dynamic use-entry labels (V10a): relabel select options[] by active source | Exploratory | GH #33 |
| Per-value show_if gating for select options[] | Concept | GH #27 |
| Smart field selector — **v1 SHIPPED 1.13.0** (discovery-backed `bws-field-combo` replacing GB `key`/`ref`/`linkKey`/datetime-key text inputs; ACF + sub-fields + options-page + term-meta + registered meta; flat searchable list, two filters [location path + type], kind/group-dynamic label, free-text + clear; REST `bws-dynamic-tags/v1/fields` inlined per editor load, registration-time not value-time; offered⟺resolvable). **Remaining:** v2 type-priority (recommend-divider OR multi-select Filter 2; `ref`→relationship+post_object; `src:ref` hopped-PT scope; smarter Filter-1 preset; dynamic label on `ref`; custom combobox widget for reopen-highlight); v3 Pie Calendar; v-future pick-a-post-to-scan. | v2/v3 Deferred | `.claude/plans/field-selector.md` |
| Field-selector post-v1 follow-ups (ultra review 2026-07-06): **FU-1** location filter carries kind/group as structured data, not a parsed display string (fixes `' › '` delimiter forgery + locale-fragile kind inference; G2 parenthetical-strip already band-aided); **FU-2** `bws_field_key_option()` factory for the ~14 hand-copied option flips (fold into v2); **FU-3** shared filter set for the datetime_ key controls (4 stacked filter pairs on `datetime_range` → one tag-level set; needs a cross-control state channel since GB renders each option control independently). All low-severity | Deferred | `.claude/plans/field-selector.md` §Post-v1 follow-ups |
| Combined option controls — `use:key,field` serialization combine (`from:type,key` selector+field fold) — SEPARATE from field selector above (this is the wire change, that is discovery) | Exploratory | `.claude/plans/combined-option-controls.md` (srcTermIn part shipped v1.6.0) |
| Adding sources to GB core tags via JS filters | Concept | `.claude/plans/gb-tag-extension.md` |
| `{{join}}` tag — standalone combining tag (NOT a modifier, NOT a base tag; absorbs base text per slot). v1 text-only, per-slot src/use, separator + template modes | Likely | `.claude/plans/combine-text.md` (grill-hardened 2026-06-26) |
| Base text tag: opt-in to treat `'0'` as empty (augments hooks.php:37 preserve-zero) — absorbed by `{{join}}`, yields athletics `5'` height suppression | Concept | `.claude/plans/combine-text.md` §Empty-value detection |
| Tag-in-slot composition (slots hold whole base tags → heterogeneous join/try) | Concept | memory `deferred_features.md` (north-star for #26) |
| `{{call}}` v2 ergonomics cluster — pretty `$meta['label']` in select/mirror; `post_id_arg` registration-repoint (post_id-aware-but-not-first fns); multi-arg `args:` single-control (CSV/typed serialization, comma-in-value escaping); `arg:` enum-constraint (`$meta` whitelist); allowlist shape B/C (DB allowlist gated by the filter; A extends non-breakingly); shortcode-replacement ambition. All non-breaking — v1 storage is already associative | Deferred | `.claude/plans/archive/fn-passthrough-tag.md` §Deferred |
| `{{if}}` conditional tag — the THIRD composition verb (selecting=try / combining=join / conditional=if): branch a template/value on a READ field value. Driver: athletics `bws_get_game_result` (term-name → template). NOT a join requirement. | Concept | memory `deferred_features.md` (loose user concept, no plan) |
| `if:` as a BASE-TAG OPTION (lighter alt to `{{if}}`-the-tag) — `srcTermIn`-style trigger + `show_if` predicate grammar evaluated server-side: self-gate one tag's output, OR adjacency-exclusion (mutually-exclusive `if:` on adjacent tags → distributed branch, no wrapper). Propagates: try inherits per-tag free, join per-slot FROM v1 (rides the same N-option slot encoding `try_` uses now, NOT gated on heterogeneous nesting; detail = join plan domain). No-ELSE = parity w/ GB Conditions + Block Visibility. Doesn't cover compute / group-gate / guaranteed-exactly-one | Concept | memory `deferred_features.md` (base-tag-primitive spitball, no design) |
| Composition-of-composers (nest {{join}}/{{try}}/{{if}}) — runtime nesting trivial (composer callback resolves children from its options; GB sees only the outer tag). `@name` reference NOT viable (GB resolves tags statelessly, no shared scope — verified). Only nested RESOLUTION works; AUTHORING-UI is the gating cost. Models: true-recursive vs one-level. NO model chosen. | Concept | memory `deferred_features.md` (nesting tension) |
| Multislot-only field options (gate a `use` value to slot ≥2) | Concept | memory `deferred_features.md` (cheaper alt to composition) |
| Admin-built composite tag — `{{custom}}` + template selector. Counter to in-block nesting: build the over-complex tag in an admin UI, persist server-side (named template, `{{call}}`-store precedent), reference via `{{custom tpl:name}}` (selector UX like `{{call}}`). Sidesteps the flat-options serialization wall (no tree in the block). Sibling to `{{call}}` (call=opaque PHP fn; custom=data-built composite); may be the authoring SUBSTRATE for heterogeneous join/if/try (composition-of-composers) via a `tpl:` option on those tags — permanently sidesteps in-block per-slot-different-tag serialization; intent-scoped selectors justify separate access tags even under identical resolution | Concept | memory `deferred_features.md` (counter-concept + builder-as-substrate spitball, no design) |
| Block editor sidebar migration tool | Concept | memory `deferred_features.md` |
| GB ↔ BWS tag cross-converter | Concept | memory `deferred_features.md` |
| Traversal pipeline refactor | Likely | `.claude/plans/traversal-pipeline.md` |
| Primary-source + ref-hop parity (`src:ref` hops only from ambient today; need pin-primary-then-hop). UX-open: likely a separate ref-step option per-`src`-value. Unifies with pinned-resource source | Exploratory | `.claude/plans/traversal-pipeline.md` §Problem (parity gap) |
| `term_` deprecation path (subsumed by base + context-aware #19 + pinned-resource source; re-add registry-only after). NB `view_` does NOT follow — external plugin, may stay even when `src:view` lands | Exploratory | memory `project_term_deprecation_path.md` |
| Deprecated tag removal (re-add as registry-only after) | Deferred | `.claude/plans/currently-deprecated-tags-work-quiet-snail.md` (branch `deprecated-tag-removal`) |
| Datetime option-key cleanup (gated on deprecated removal) | Deferred | memory `project_open_refactors.md` |
| Collapse `bws_read_field`'s internal loop/term-archive resolution (field-helpers.php:271-296) into the source factory — surfaced during traversal Phase 1 build (T4/V12): the seam already bypasses it via explicit id; once the wrapper routes all ~30 callers through the factory, that inference duplicates the factory everywhere. NOT Phase 1 (touches the 30-caller read path). | Likely | `.claude/plans/traversal-pipeline.md` §Post-Phase-1 convergence |
| Fold `bws_reliable_term_context_detection` (taxonomy-helpers, 5-tier) into the factory's `bws_capture_ambient_signals` — two term-detection impls now coexist (surfaced Phase 1 build); factory is the intended home. NOT Phase 1 (used by `TaxonomyTerm::resolve_id` + `term_` modifiers → widens blast radius mid-refactor). | Likely | `.claude/plans/traversal-pipeline.md` §Post-Phase-1 convergence |
| Route datetime through the L1/L2 seam (`bws_resolve_field_values`) — retire the `'option'`-in-post_id param-overload (datetime-tags.php:~1005, the flagged resolved-source-smuggled-through-id-arg contradiction, CONTEXT §Language). CLOSES the datetime **term-ambient gap** deferred in traversal Phase 1 (T6): datetime base tags stay post-only this phase (honest-empty on a term archive rather than leaking a stale post's date), and gain term/site kind-awareness for free once they ride the seam's kind dispatch (V12). Ties to the datetime option-key cleanup row above. | Likely | `.claude/plans/traversal-pipeline.md` §Post-Phase-1 convergence |

## Maintenance

- New non-bug idea → add a row here + put detail in its home (plan file / issue /
  memory). Don't let an item exist *only* in a hidden file with no tracker row.
- Item ships → strike its row + note the CHANGELOG version, or delete once
  CHANGELOG records it.
- Status drifts → update the tag; that's the point of the column.
