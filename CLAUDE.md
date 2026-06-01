# CLAUDE.md

See [README.md](README.md) and [`docs/tag-reference.md`](docs/tag-reference.md) for project overview and architecture.

## Dependencies

- WordPress (core APIs)
- GenerateBlocks plugin (`GenerateBlocks_Register_Dynamic_Tag`, `GenerateBlocks_Dynamic_Tags`, `GenerateBlocks_Dynamic_Tag_Callbacks`)
- Custom fields plugin (ACF or compatible — all calls guarded with `function_exists()`)

## Development

No build pipeline, test suite, or linter. Edit PHP directly, test in WordPress environment.

## Documentation ownership

Single source of truth per content type. Other files link, never duplicate.

| Content type | Owner | Notes |
|---|---|---|
| User-facing tag overview / quickstart | `README.md` | Repo-visitor framing; don't replicate technical schemas |
| Current architecture (templates, sources, options, GB types, render order, preview labels) | `docs/tag-reference.md` | Authoritative |
| Plugin's response to GB constraints (default-strip strategy, custom controls, etc.) | `docs/tag-reference.md` | Lives alongside the architecture it shapes |
| GB-imposed constraints | `docs/gb-constraints.md` | Pure GB facts; our responses go in `tag-reference.md` |
| External-plugin integration API | `docs/plugin-integration.md` | Code-level guide; link `tag-reference.md` for schemas |
| Historical N×M tag names + rename trackers | `docs/deprecated-tags-options.md` | Migration reference only — no current-state info |
| Post-content pipeline (helpers + history) | `docs/post-content-processing-reference.md` | Implementation + standalone-era history |
| Shipped versions | `CHANGELOG.md` | Append-only |
| Pending plans | `.claude/plans/` + GitHub issues | Not under `docs/` |
| Claude session prefs / cross-session pointers | `memory/MEMORY.md` (gitignored) | Pointer index; don't duplicate doc content |
| Claude in-repo behavior + this policy | `CLAUDE.md` | Dependencies + dev workflow; all schema deferred to `docs/` |
| Agent-skill config (issue tracker, triage labels, domain doc layout) | `docs/agents/*.md` | Consumed by Pocock engineering skills; set via `/setup-matt-pocock-skills` |

### Update triggers

| Trigger | Update |
|---|---|
| New source class / template / option key | `tag-reference.md` first; CHANGELOG entry |
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
  - **PHPDoc on the class/method that enforces them** (primary), OR
  - **`docs/tag-reference.md`** (secondary — for cross-cutting invariants that don't fit one class).
- §T rows: delete all closed/deferred rows.
- §B bugs: file as GitHub Issues; cross-reference the §V they produced if one was added.
- Truncate `SPEC.md` to: `# SPEC — No active spec. See CHANGELOG, docs/tag-reference.md, Issues.`

**SPEC.md is source of truth only while the release is in flight.** Once shipped: `docs/tag-reference.md` + PHPDoc + CHANGELOG + Issues take over.

**Bugs:** new bugs → GitHub Issues by default. SPEC §B reserved for bugs that drove a spec change (new invariant). §B cross-references the §V it produced. Not a general bug tracker.

## Agent skills

### Issue tracker

Issues live in GitHub Issues for `davidofchatham/bws-gb-dynamic-tags-extensions` (uses the `gh` CLI). See `docs/agents/issue-tracker.md`.

### Triage labels

Canonical default label strings; `wontfix` already exists in the repo, the other four are created on first use. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context — `CONTEXT.md` + `docs/adr/` at the repo root. See `docs/agents/domain.md`.
