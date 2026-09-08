# Cross-link rules

Referenced from [`CLAUDE.md`](../CLAUDE.md) §Documentation ownership §Cross-link rules, which keeps
a short summary and links here for the full rules.

- Reference by **link + section anchor**, never copy.
- README may paraphrase technical detail for end-user framing — must not contradict `tag-reference.md`.
- MEMORY.md entries pointing at `docs/` are one-liners only.
- When a doc is no longer authoritative for a topic, replace the content with a forward-reference rather than leaving stale text.
- **MOVING A PLAN REPOINTS WHAT CITES IT, IN THE SAME EDIT.** Archiving is the usual mover
  (a live plan moves to the archive, or out to `docs/design-history/`), renaming the other. Both leave
  every existing citation pointing at nothing, and nothing fails when they do — the pointer
  is prose. An ADR is why this is a rule rather than tidiness: its `Status:` line cites the plan the
  decision was **hardened against**, so an unresolvable pointer is an accepted decision whose
  evidence cannot be checked. `git grep '<old-path>'` before the move; repoint what it finds.
- **SHIPPED CODE CITES IDS, NOT PRIVATE PATHS — mechanically enforced.**
  [`.claude/hooks/block-private-path-citation.sh`](../.claude/hooks/block-private-path-citation.sh)
  blocks any `git commit` whose staged diff adds a `.scratch/` or `.claude/` path reference under
  `includes/`, `assets/`, `docs/adr/`, `README.md` or `CHANGELOG.md` — that hook is now the axis
  (the exact scope, the exact forbidden prefixes); this rule states the consequence and the part the
  hook can't check. A comment under `includes/` or `assets/` may instead name an ADR, an `FW-N` row,
  a GitHub `#N`, another code site, or a `docs/` path. **A bare `#N` in code means the GitHub issue**:
  a plan's own internal item numbering is a third sequence, and must be resolved to a committed
  handle before it is cited, or the reader resolves it against the wrong one and lands somewhere real
  and unrelated — the hook only catches the private-path case, not this one.
- **A COMMITTED FILE MAY POINT AT A PRIVATE PLAN ONLY AS A DETAIL HOME.** A detail home is a visible
  surface naming where the design lives — the tracker's own shape, and legitimate wherever a doc plays
  that role. What the hook above blocks is anything that becomes **unverifiable** without it: shipped
  code under `includes/` or `assets/`, an ADR `Status:` line, and `README.md` / `CHANGELOG.md`, whose
  reader is the one guaranteed not to have `.scratch/`. Those fail silently and have no other source;
  a prose detail home in `CONTEXT.md` has the doc itself, which is why the hook's scope excludes it.
  The plan commits when it is FINISHED (§Spec lifecycle), and its citations repoint in that same edit.
  For anything committed before the hook existed, audit with:
  `git grep '\.scratch/plans/' -- includes assets docs/adr README.md CHANGELOG.md`. Scoping the grep
  to the forbidden zones is what lets it drop the filename requirement — the earlier
  `[a-z0-9./-]*\.md` pattern silently missed a bare `.scratch/plans/archive/` citation.
- **`docs/design-history/` IS OUT OF THAT GREP'S SCOPE, AND ITS DANGLING PATHS ARE NOT DEFECTS.** Those
  files name the paths that were live when they were written; a record saying "was
  `.claude/plans/verb-agnostic-slot-resolver.md`" is the record WORKING (that spelling is the point —
  the tree moved to `.scratch/plans/` on 2026-08-20 and the record still names where it was). Publishing a record makes
  its dead paths grep-visible all at once, and the tidying reflex reads history as staleness.
  Repointing them deletes what they exist to say. Leave them.
