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
| try_email + try_phone modifiers (base-tag parity) | Likely | GH #32 |
| ~~Derive try_ slot source options from base builders~~ | **SHIPPED** | GH #26 (closed 2026-06-26) — option-DEFINITION derivation only |
| Verb-agnostic slot RESOLVER extraction (parameterize fold selecting\|combining + reveal-shape) — successor to #26, which did option-defs only; shaped by {{join}} as 2nd instance | Concept | `.claude/plans/combine-text.md` §residual-3 (file as issue once join ships) |
| Gate wrap-capable base tags (text/title/datetime) on img/picture via native visibility | Likely | GH #31 |
| Context-aware base tag resolution across all WP contexts (Q5/Q7) | Likely | GH #19 + `.claude/plans/context-aware-base-tags.md` |
| Configurable default field keys per source × tag-type | Concept | GH #29 (memory `project_default_field_keys.md`) |
| src:site → ref: resolve entity from a site-stored relational field | Exploratory | GH #28 |
| datetime list mode (limit + sep) | Likely | GH #30 |
| Custom time format on two-ended as:time range | Deferred | GH #25 |
| Src-dynamic use-entry labels (V10a): relabel select options[] by active source | Exploratory | GH #33 |
| Per-value show_if gating for select options[] | Concept | GH #27 |
| Combined option controls — `from:type,key` (field source + key) | Exploratory | `.claude/plans/combined-option-controls.md` (srcTermIn part shipped v1.6.0) |
| Adding sources to GB core tags via JS filters | Concept | `.claude/plans/gb-tag-extension.md` |
| `{{join}}` tag — standalone combining tag (NOT a modifier, NOT a base tag; absorbs base text per slot). v1 text-only, per-slot src/use, separator + template modes | Likely | `.claude/plans/combine-text.md` (grill-hardened 2026-06-26) |
| Base text tag: opt-in to treat `'0'` as empty (augments hooks.php:37 preserve-zero) — absorbed by `{{join}}`, yields athletics `5'` height suppression | Concept | `.claude/plans/combine-text.md` §Empty-value detection |
| Tag-in-slot composition (slots hold whole base tags → heterogeneous join/try) | Concept | memory `deferred_features.md` (north-star for #26) |
| Function passthrough tag (`{{call}}`) — developer-tool 4th structural position: reuses L1 post-resolution to inject loop-correct post_id into a site PHP fn (e.g. athletics `bws_get_game_result`), delegates to opaque PHP. Post-context-only by design (src:current/ref; NO site/term). Filter allowlist (Option A, associative storage) + `bws_register_call_function` + security-only gate (`isInternal`) + read-only admin mirror. Fixes `tct` `{{fn.x}}` (no context, no gate). Grilled 2026-06-26; v1 locked. Decision A; B/C deferred not excluded | Likely | `.claude/plans/fn-passthrough-tag.md` |
| `{{if}}` conditional tag — the THIRD composition verb (selecting=try / combining=join / conditional=if): branch a template/value on a READ field value. Driver: athletics `bws_get_game_result` (term-name → template). NOT a join requirement. | Concept | memory `deferred_features.md` (loose user concept, no plan) |
| Composition-of-composers (nest {{join}}/{{try}}/{{if}}) — runtime nesting trivial (composer callback resolves children from its options; GB sees only the outer tag). `@name` reference NOT viable (GB resolves tags statelessly, no shared scope — verified). Only nested RESOLUTION works; AUTHORING-UI is the gating cost. Models: true-recursive vs one-level. NO model chosen. | Concept | memory `deferred_features.md` (nesting tension) |
| Multislot-only field options (gate a `use` value to slot ≥2) | Concept | memory `deferred_features.md` (cheaper alt to composition) |
| Block editor sidebar migration tool | Concept | memory `deferred_features.md` |
| GB ↔ BWS tag cross-converter | Concept | memory `deferred_features.md` |
| Traversal pipeline refactor | Likely | `.claude/plans/traversal-pipeline.md` |
| Primary-source + ref-hop parity (`src:ref` hops only from ambient today; need pin-primary-then-hop). UX-open: likely a separate ref-step option per-`src`-value. Unifies with pinned-resource source | Exploratory | `.claude/plans/traversal-pipeline.md` §Problem (parity gap) |
| `term_` deprecation path (subsumed by base + context-aware #19 + pinned-resource source; re-add registry-only after). NB `view_` does NOT follow — external plugin, may stay even when `src:view` lands | Exploratory | memory `project_term_deprecation_path.md` |
| Deprecated tag removal (re-add as registry-only after) | Deferred | `.claude/plans/currently-deprecated-tags-work-quiet-snail.md` (branch `deprecated-tag-removal`) |
| Datetime option-key cleanup (gated on deprecated removal) | Deferred | memory `project_open_refactors.md` |

## Maintenance

- New non-bug idea → add a row here + put detail in its home (plan file / issue /
  memory). Don't let an item exist *only* in a hidden file with no tracker row.
- Item ships → strike its row + note the CHANGELOG version, or delete once
  CHANGELOG records it.
- Status drifts → update the tag; that's the point of the column.
