# Issue tracker: hybrid — local specs, GitHub bugs

Two homes, split by ONE question: **must the record outlive the change?**

| Work | Home | Why |
|---|---|---|
| Bugs; anything someone outside waits on; a defect CLASS on its second instance | GitHub Issues (`gh` CLI) | Durable, externally visible. Already `CLAUDE.md` §Spec lifecycle's rule. |
| A spec (PRD) for one in-flight piece of work | `.scratch/<feature-slug>/spec.md` | Rewritable in place; readable during a GitHub outage; dies at merge |
| The build tickets it breaks into | `.scratch/<feature-slug>/issues/<NN>-<slug>.md` | Numbered per feature from `01`, so they never collide with `#N` |
| PUBLICATION of in-flight work | the pull request body | One durable public record, at the moment it is reviewable |

`.scratch/` is gitignored. Nothing under it is ever committed.

## Why this is not the retired `SPEC.md`

`CLAUDE.md` §Spec lifecycle retired a root `SPEC.md` and forbids creating one. This is not that.
`SPEC.md` was ONE file for ALL work, unbounded, with no death date. A `.scratch/<feature-slug>/`
directory has a named scope and dies at merge. Scope and a death date are what `SPEC.md` lacked.

## The local half

- One feature per directory: `.scratch/<feature-slug>/`
- Spec at `.scratch/<feature-slug>/spec.md`
- Tickets one file each at `issues/<NN>-<slug>.md`, `01` up, dependency order — never one combined file
- Triage state is a `Status:` line near the top (role strings in `triage-labels.md`)
- Blocking is a `Blocked by: NN, NN` line near the top
- Conversation appends under a `## Comments` heading
- **Cite a local ticket as `<feature-slug>/NN`, never a bare integer** — see the ID rule below

## The GitHub half

- Create: `gh issue create --title "..." --body "..."` (heredoc for multi-line)
- Read: `gh issue view <number> --comments`
- List: `gh issue list --state open --json number,title,body,labels,comments --jq '[.[] | {number, title, body, labels: [.labels[].name], comments: [.comments[].body]}]'`
- Comment: `gh issue comment <number> --body "..."`
- Label: `gh issue edit <number> --add-label "..."` / `--remove-label "..."`
- Close: `gh issue close <number> --comment "..."`
- Repo is inferred from `git remote -v`.

## IDs never appear bare

Three sequences coexist. Every citation carries its marker, always:

- `FW-84` — a `docs/future-work.md` tracker row
- `#84` — a GitHub issue
- `<feature-slug>/03` — a local ticket

A bare integer is ambiguous between all three. `FW-71` and `#71` are different things.

## When a skill says "publish to the issue tracker"

- A **spec** → write `.scratch/<feature-slug>/spec.md`.
- **Build tickets** → write one file each under `.scratch/<feature-slug>/issues/`.
- A **bug**, or anything whose record must outlive the change → `gh issue create`.
- Unsure → ask. Do not default to GitHub.

## When a skill says "fetch the relevant ticket"

A path → read the file. A bare `#42` → `gh issue view 42 --comments`.

## Wayfinding operations

Used by `/wayfinder`. The **map** is a file with one **child** per ticket. Local only —
wayfinding never runs against GitHub Issues here.

- **Map**: `.scratch/<effort>/map.md` — Notes / Decisions-so-far / Fog.
- **Child**: `.scratch/<effort>/issues/NN-<slug>.md`, from `01`, question in the body. `Type:`
  records `research`/`prototype`/`grilling`/`task`; `Status:` records `claimed`/`resolved`.
- **Blocking**: `Blocked by: NN, NN`. Unblocked when every listed file is `resolved`.
- **Frontier**: open, unblocked, unclaimed; lowest number wins.
- **Claim**: set `Status: claimed` and save before any work.
- **Resolve**: append the answer under `## Answer`, set `Status: resolved`, then append a
  context pointer to Decisions-so-far in `map.md`.
