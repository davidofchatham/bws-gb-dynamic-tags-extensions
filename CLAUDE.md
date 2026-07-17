# CLAUDE.md

See [README.md](README.md) and [`docs/tag-reference.md`](docs/tag-reference.md) for project overview and architecture.

## Dependencies

- WordPress (core APIs)
- GenerateBlocks plugin (`GenerateBlocks_Register_Dynamic_Tag`, `GenerateBlocks_Dynamic_Tags`, `GenerateBlocks_Dynamic_Tag_Callbacks`)
- Custom fields plugin (ACF or compatible — all calls guarded with `function_exists()`)

## Development

No build pipeline or linter. Edit PHP directly, test in a WordPress environment.

**Two test layers — run the pure harness always, route integration through the testbed.**

1. **Pure PHP harnesses** under `tools/test/` — no framework, no autoload; each copies the
pure functions it exercises inline (house pattern) and runs via `php tools/test/<name>.php`,
exiting non-zero on failure. Run the one whose domain you touched (see §Update triggers for the
key→harness map): e.g. `traversal-pipeline-test.php` (source factory + fold engine),
`phone-normalize-test.php`, `preview-label-test.php`, `field-discovery-test.php`,
`slot-options-build-test.php`, `try-join-seam-test.php`, `call-tag-test.php`,
`slot-qualify-show-if-test.php`. No CI runs these; run them locally before commit.

2. **WordPress integration — the fixture testbed.** The pure harnesses can't reach anything
WP-dependent (ambient context, ACF/meta reads, GB render, the editor React controls). For that
there is a seeded WP site on the local OpenLiteSpeed/Docker env at `D:/Environments/wp-litespeed`,
site `testbed`. **Prefer routing integration smoke tests through it over hand-built pages or
live-site probes.** Two entrypoints:
   - **Render a tag with real ambient context:**
     `bin/wp.sh testbed bws render-tag '{{...}}' --url=https://testbed.test/<context>/`
     (`--loop-item=<id>` for a synthetic query-loop row, `--porcelain` for output-only). Runs
     the real main query so `is_tax()`/queried-object/current-post are genuine. This is the
     cheap what-if / discovery-row engine — use it before building a page to answer "what does
     this tag do on context X".
   - **(Re)seed fixture state:** `bin/seed.sh testbed core-structures` (idempotent). The
     `core-structures` **blueprint** (`tools/fixtures/core-structures/`) seeds the state the
     two `*-test-matrix.md` files assume — matrix pages are split by source-state
     (`matrix-post-meta`, `matrix-terms-valid|mixed|junk`); tag families accrete rows into them.
     See `tools/fixtures/core-structures/README.md`. Full design: `.claude/plans/fixture-testbed.md`.

The manual `*-test-matrix.md` files (integration rows exercised by hand / via `render-tag`) are
noted per trigger — run against the testbed, never the live/cached site.

## Documentation ownership

Single source of truth per content type. Other files link, never duplicate.

| Content type | Owner | Notes |
|---|---|---|
| User-facing tag overview / quickstart | `README.md` | Repo-visitor framing; don't replicate technical schemas |
| Current architecture (templates, sources, options, GB types, render order) | `docs/tag-reference.md` | Authoritative |
| Cross-cutting vocabulary (output-shape terms: single-result, composite string, list mode, query loop; etc.) | Owning schema doc (e.g. `docs/tag-reference.md` §Output shape) | Defined ONCE beside the schema it describes — NO standalone glossary (avoids schema/glossary drift). `CONTEXT.md` invariants *use* terms, never define them. |
| Cross-cutting LIVE invariants / design models (source-analog, `use`-dispatch Model B, strip-default, qualifying gate, label-scope) | `CONTEXT.md` | Principles that span many callbacks + bind now. Links schemas in `tag-reference.md`, rationale in `.claude/plans/archive/`. NOT schemas/state-tables/narrative. Post-ship target for cross-cutting §V invariants. |
| Editor-time tag configuration preview text (markers, assembly, warnings, per-template + try_ shapes, examples) | `docs/editor-tag-previews.md` | Authoritative; `tag-reference.md` keeps a one-line forward-ref. Built by `bws_build_preview_label()` in `preview-helpers.php`. |
| Plugin's response to GB constraints (default-strip strategy, etc.) | `docs/tag-reference.md` | Lives alongside the architecture it shapes; editor-JS control *mechanism* now owned by `docs/editor-controls.md` |
| GB-imposed constraints | `docs/gb-constraints.md` | Pure GB facts; our responses go in `tag-reference.md` |
| External-plugin integration API | `docs/plugin-integration.md` | Code-level guide; link `tag-reference.md` for schemas |
| Custom editor-control architecture (`bws-*` control pattern, `tagSpecificControls` seam, `setState` param authority + `delete`-omit idiom, composite "two controls one key", dynamic labels / entry filter / reconcile-on-src-change) | `docs/editor-controls.md` | **Reserved owner — doc not yet created.** Content migrates here when the `use`+`key` combine (Phase 2) ships and `.claude/plans/combined-option-controls.md` archives. Schemas stay in `tag-reference.md`; GB facts in `gb-constraints.md`; load-bearing invariants → PHPDoc on the control classes. **Field discovery NOT here — decoupled to `field-selector.md` (own ship/lifecycle); its `bws-field-combo` control + REST endpoint own their invariants via SPEC.md → PHPDoc on ship.** |
| Historical N×M tag names + **completed** rename trackers | `docs/deprecated-tags-options.md` | Migration reference only — no current-state info. In-progress / under-consideration renames stay in `tag-reference.md` until completed, then move here. |
| Post-content pipeline (helpers + history) | `docs/post-content-processing-reference.md` | Implementation + standalone-era history |
| Shipped versions | `CHANGELOG.md` | Append-only |
| Non-bug future-work TRACKER (visible index: item + blockers + interactions + pointer to detail home) | `docs/future-work.md` | Tracked/reviewable surface over hidden detail homes. Indexes, never duplicates detail. Columns: **Blocked by** (hard prereq), **Interacts with** (soft coupling), **Detail home** (design + implicit certainty). No status column — certainty is read from the detail home. **Bugs → GitHub Issues only, never here.** Avoid one GH issue per speculative enhancement. When unsure where work belongs, ASK. |
| Pending-plan / enhancement DETAIL (homes the tracker points at) | `.claude/plans/*.md`, GitHub `enhancement` issues, or `memory/` (cross-cutting concepts) | Not under `docs/` (except when migrated). Every item also gets a `docs/future-work.md` tracker row — don't leave work tracked only in a hidden file. |
| Claude session prefs / cross-session pointers | `memory/MEMORY.md` (gitignored) | Pointer index; don't duplicate doc content |
| Claude in-repo behavior + this policy | `CLAUDE.md` | Dependencies + dev workflow; all schema deferred to `docs/` |
| Agent-skill config (issue tracker, triage labels, domain doc layout) | `docs/agents/*.md` | Consumed by Pocock engineering skills; set via `/setup-matt-pocock-skills` |

### Update triggers

| Trigger | Update |
|---|---|
| New source class / template / option key | `tag-reference.md` first; CHANGELOG entry |
| New/changed editor preview text (a `bws_build_preview_label` case) | `editor-tag-previews.md` (markers/field-part/warning/example rows) + run `php tools/test/preview-label-test.php` |
| Phone normalize / render / settings change (`bws_phone_normalize_tel` + sub-helpers, `bws_phone_callback`, `bws_phone_render_one`, phone settings/preview) | run `php tools/test/phone-normalize-test.php` (algorithm) + `tools/test/phone-test-matrix.md` rows against the testbed (`bws render-tag` / matrix pages; §Development) |
| Field-discovery change (`includes/rest/field-discovery.php` transforms, `assets/js/field-combo-control.js`, the enqueue/inline block, or a flip of any option to/from `bws-field-combo`) | run `php tools/test/field-discovery-test.php` (pure discovery logic) + `tools/test/field-selector-test-matrix.md` rows against the testbed editor (§Development) |
| Text read-seam / link-wrap change (`bws_base_text_resolve_value`, `bws_base_text_callback`, `bws_wrap_with_link` / `bws_resolve_link_url`, or a new seam absorber e.g. `{{join}}` slots) | `tools/test/text-test-matrix.md` rows against the testbed (`bws render-tag`; §Development) |
| New/changed fixture state a matrix or discovery row assumes | update the `core-structures` blueprint (`tools/fixtures/core-structures/` — manifest = data, schema = code, blocks = page markup), reseed (`bin/seed.sh testbed core-structures`), re-run `verify.php`; keep matrices linking, not duplicating |
| New option rename | `deprecated-tags-options.md` tracker + `tag-reference.md` if it affects current names |
| New GB constraint discovered | `gb-constraints.md`; if it forces a design change, note the response in `tag-reference.md` |
| New external-plugin API affordance | `plugin-integration.md`; CHANGELOG entry |
| Pipeline / helper internals change | `post-content-processing-reference.md` (if content-rendering) or PHPDoc only (if narrow) |
| User-visible feature ships | `README.md` overview update + CHANGELOG |
| Tag / source / option / default renamed | All four: `tag-reference.md` (current state), `deprecated-tags-options.md` (rename row), CHANGELOG, any code references |

### Cross-link rules

- Reference by **link + section anchor**, never copy.
- README may paraphrase technical detail for end-user framing — must not contradict `tag-reference.md`.
- MEMORY.md entries pointing at `docs/` are one-liners only.
- When a doc is no longer authoritative for a topic, replace the content with a forward-reference rather than leaving stale text.

## SPEC.md lifecycle

`SPEC.md` is the **active spec** for the current in-flight release — §G goals, §C constraints, §I interfaces, §V invariants, §T tasks, §B bugs. One active SPEC at a time. Created only when a release introduces spec-level change.

**Post-ship cleanup is mandatory:**

- **Load-bearing §V invariants** migrate to:
  - **PHPDoc on the class/method that enforces them** (primary — for any invariant a single class/method enforces), OR
  - **`CONTEXT.md`** (for cross-cutting invariants / design models spanning many callbacks — the source-analog model, dispatch rules, qualifying gate; principles, not schemas), OR
  - **`docs/tag-reference.md`** (for current-state schema detail an invariant references).
  - A migrating invariant typically lands a one-line principle in CONTEXT.md that links its schema in tag-reference and its rationale in `.claude/plans/archive/<feature>.md`.
- §T rows: delete all closed/deferred rows.
- §B bugs: file as GitHub Issues; cross-reference the §V they produced if one was added.
- Truncate `SPEC.md` to: `# SPEC — No active spec. See CHANGELOG, docs/tag-reference.md, Issues.`

**SPEC.md is source of truth only while the release is in flight.** Once shipped: `CONTEXT.md` (cross-cutting invariants) + `docs/tag-reference.md` (schemas) + PHPDoc (single-class invariants) + CHANGELOG + Issues take over.

**Bugs:** new bugs → GitHub Issues by default. SPEC §B reserved for bugs that drove a spec change (new invariant). §B cross-references the §V it produced. Not a general bug tracker.

## Agent skills

### Issue tracker

Issues live in GitHub Issues for `davidofchatham/bws-gb-dynamic-tags-extensions` (uses the `gh` CLI). See `docs/agents/issue-tracker.md`.

### Triage labels

Canonical default label strings; `wontfix` already exists in the repo, the other four are created on first use. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context — `CONTEXT.md` + `docs/adr/` at the repo root. See `docs/agents/domain.md`.
