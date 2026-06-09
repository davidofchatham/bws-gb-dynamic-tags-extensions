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
| Phone tag follow-ups (display format, ext/type affix, per-country rules, `use` enum, per-tag `cc:`, lenient passthrough, vanity/spelled display) | Deferred (mixed) | `.claude/plans/phone-tag-future.md` |
| try_email + try_phone modifiers (base-tag parity) | Likely | GH #32 |
| Derive try_ slot source options from base builders (kill duplication; prereq for src:site in try_ slots) | Planned | GH #26 + `.claude/plans/try-email-phone-and-slot-derivation.md` |
| Gate wrap-capable base tags (text/title/datetime) on img/picture via native visibility | Likely | GH #31 |
| Context-aware base tag resolution across all WP contexts (Q5/Q7) | Exploratory | GH #19 + `.claude/plans/context-aware-base-tags.md` |
| Configurable default field keys per source × tag-type | Deferred | GH #29 (memory `project_default_field_keys.md`) |
| src:site → ref: resolve entity from a site-stored relational field | Exploratory | GH #28 |
| datetime list mode (limit + sep) | Likely | GH #30 |
| Custom time format on two-ended as:time range | Deferred | GH #25 |
| Src-dynamic use-entry labels (V10a): relabel select options[] by active source | Exploratory | GH #33 |
| Per-value show_if gating for select options[] | Deferred | GH #27 |
| Combined option controls — `from:type,key` (field source + key) | Deferred | `.claude/plans/combined-option-controls.md` (srcTermIn part shipped v1.6.0) |
| Custom image controls — full `'cross-source'` image type | Deferred | `.claude/plans/custom-image-controls.md` (media-picker shipped v1.6.0) |
| Adding sources to GB core tags via JS filters | Concept | `.claude/plans/gb-tag-extension.md` |
| join_ tags | Concept | `.claude/plans/combine-text.md` (only same-model subset fits modifier slots) |
| Tag-in-slot composition (slots hold whole base tags → heterogeneous join/try) | Concept | memory `deferred_features.md` (north-star for #26) |
| Multislot-only field options (gate a `use` value to slot ≥2) | Concept | memory `deferred_features.md` (cheaper alt to composition) |
| Block editor sidebar migration tool | Concept | memory `deferred_features.md` |
| GB ↔ BWS tag cross-converter | Concept | memory `deferred_features.md` |
| Traversal pipeline refactor | Deferred | `.claude/plans/traversal-pipeline.md` |
| Deprecated tag removal (re-add as registry-only after) | Deferred | `.claude/plans/currently-deprecated-tags-work-quiet-snail.md` (branch `deprecated-tag-removal`) |
| Datetime option-key cleanup (gated on deprecated removal) | Deferred | memory `project_open_refactors.md` |

## Maintenance

- New non-bug idea → add a row here + put detail in its home (plan file / issue /
  memory). Don't let an item exist *only* in a hidden file with no tracker row.
- Item ships → strike its row + note the CHANGELOG version, or delete once
  CHANGELOG records it.
- Status drifts → update the tag; that's the point of the column.
