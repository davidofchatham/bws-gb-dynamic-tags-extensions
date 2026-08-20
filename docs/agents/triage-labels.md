# Triage Labels

The skills speak in terms of five canonical triage roles. This file maps those roles to the actual
label strings used in this repo's issue tracker.

| Label in mattpocock/skills | Label in our tracker | Meaning                                  |
| -------------------------- | -------------------- | ---------------------------------------- |
| `needs-triage`             | `needs-triage`       | Maintainer needs to evaluate this issue  |
| `needs-info`               | `needs-info`         | Waiting on reporter for more information |
| `ready-for-agent`          | `ready-for-agent`    | Fully specified, ready for an AFK agent  |
| `ready-for-human`          | `ready-for-human`    | Requires human implementation            |
| `wontfix`                  | `wontfix`            | Will not be actioned                     |

When a skill mentions a role (e.g. "apply the AFK-ready triage label"), use the corresponding label
string from this table. Create a label on first use if it does not exist yet; do not record here
which ones currently do.

## Two carriers, one vocabulary

The tracker is hybrid (see `issue-tracker.md`), so a role reaches an item one of two ways:

- **GitHub half** — a real GitHub label, applied with `gh issue edit --add-label`.
- **Local half** — a `Status:` line near the top of the ticket file under `.scratch/`, carrying the
  same five role names verbatim.

Same vocabulary either way. A ticket that moves from one half to the other keeps its role name.
