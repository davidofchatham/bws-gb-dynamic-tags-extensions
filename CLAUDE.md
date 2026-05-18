# CLAUDE.md

See [README.md](README.md) and [`docs/tag-matrix.md`](docs/tag-matrix.md) for project overview and architecture.

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
| Current architecture (templates, sources, options, GB types, render order, preview labels) | `docs/tag-matrix.md` | Authoritative |
| Plugin's response to GB constraints (default-strip strategy, custom controls, etc.) | `docs/tag-matrix.md` | Lives alongside the architecture it shapes |
| GB-imposed constraints | `docs/gb-constraints.md` | Pure GB facts; our responses go in `tag-matrix.md` |
| External-plugin integration API | `docs/plugin-integration.md` | Code-level guide; link `tag-matrix.md` for schemas |
| Historical N×M tag names + rename trackers | `docs/deprecated-tags-options.md` | Migration reference only — no current-state info |
| Post-content pipeline (helpers + history) | `docs/post-content-processing-reference.md` | Implementation + standalone-era history |
| Shipped versions | `CHANGELOG.md` | Append-only |
| Pending plans | `.claude/plans/` + GitHub issues | Not under `docs/` |
| Claude session prefs / cross-session pointers | `memory/MEMORY.md` (gitignored) | Pointer index; don't duplicate doc content |
| Claude in-repo behavior + this policy | `CLAUDE.md` | Dependencies + dev workflow; all schema deferred to `docs/` |

### Update triggers

| Trigger | Update |
|---|---|
| New source class / template / option key | `tag-matrix.md` first; CHANGELOG entry |
| New option rename | `deprecated-tags-options.md` tracker + `tag-matrix.md` if it affects current names |
| New GB constraint discovered | `gb-constraints.md`; if it forces a design change, note the response in `tag-matrix.md` |
| New external-plugin API affordance | `plugin-integration.md`; CHANGELOG entry |
| Pipeline / helper internals change | `post-content-processing-reference.md` (if content-rendering) or PHPDoc only (if narrow) |
| User-visible feature ships | `README.md` overview update + CHANGELOG |
| Tag / source / option / default renamed | All four: `tag-matrix.md` (current state), `deprecated-tags-options.md` (rename row), CHANGELOG, any code references |

### Cross-link rules

- Reference by **link + section anchor**, never copy.
- README may paraphrase technical detail for end-user framing — must not contradict `tag-matrix.md`.
- MEMORY.md entries pointing at `docs/` are one-liners only.
- When a doc is no longer authoritative for a topic, replace the content with a forward-reference rather than leaving stale text.
