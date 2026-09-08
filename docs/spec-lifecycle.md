# Spec lifecycle

Referenced from [`CLAUDE.md`](../CLAUDE.md) §Spec lifecycle, which keeps the one-paragraph summary
and links here for the full rules: post-ship migration, the plan-commits-when-finished /
retirement-split mechanics, the §SETTLED index practice, and the bug-filing criteria.

**A SPEC IS A LOCAL FILE — `.scratch/<feature-slug>/spec.md`.** It owns the problem statement, the
interfaces, the invariants, the tasks and the scope for one in-flight piece of work. One per piece
of work, not per release: several can be open, each dying when its own work merges. The PR body is
where the decided spec becomes public — `.scratch/` is gitignored and nothing under it is committed.
Bugs stay GitHub Issues; `docs/agents/issue-tracker.md` owns the split, the ticket conventions, and
why a `.scratch/` directory is not the retired root `SPEC.md` returning.

**A spec issue closed before the tracker changed stays on GitHub.** It is already the record of how
something came to be — migrating it would rewrite that record, not preserve it.

**The root `SPEC.md` artifact is RETIRED.** Do not create one. In-code citations of the form
`SPEC §V<n>` predate the retirement and dangle — repoint them to a real home when you touch one;
none is load-bearing. Two spellings exist (`SPEC §V<n>` and `SPEC.md §V<n>`) — grep BOTH when
sweeping.

**AN ARCHIVED PLAN IS NOT CORRECTED WHEN POLICY CHANGES.** `.scratch/plans/archive/` records what was
true when it was written — `handoff-3-state-and-pickup.md` says "SPEC.md at repo root is the live
spec" and that is EVIDENCE of how the repo used to work, not a stale pointer to fix. Editing it
deletes the record. Same posture this section already takes on a closed spec issue: a record of how
something came to be, not a statement of how it currently works. A LIVE plan is the opposite case and
gets corrected, present tense being a claim about now.

**Post-ship migration is mandatory and is UNCHANGED by that** — it never depended on the artifact:

- **Load-bearing invariants** migrate to:
  - **PHPDoc on the class/method that enforces them** (primary — for any invariant a single class/method enforces), OR
  - **`CONTEXT.md`** (for cross-cutting invariants / design models spanning many callbacks — the source-analog model, dispatch rules, qualifying gate; principles, not schemas), OR
  - **`docs/tag-reference.md`** (for current-state schema detail an invariant references).
  - A migrating invariant typically lands a one-line principle in CONTEXT.md that links its schema in tag-reference and its rationale in the plan — `.scratch/plans/<feature>.md` while that plan is live, `docs/design-history/` once it is finished, and the citation repoints in the same edit as the move. Per `CLAUDE.md` §Documentation ownership, an invariant's AXIS lands at ONE of these and the others state its consequence.
- Closed/deferred task rows: delete them from the spec's task list, or delete the spec directory (on GitHub, close the issue).
- Bugs found on the way: file per the rule below, cross-referencing the invariant they produced if one was added.

## A plan commits when it is finished, not when it ships

A plan that ships in phases is not finished; committing it whole at a phase boundary freezes a draft
as a record and states in-progress design as history. Two events, two moves — and nothing is judged,
because the event says which applies:

| Event | Move | Where it lands |
|---|---|---|
| **A phase ships**, plan continues | Lift that phase out. The boundary is clean by construction — the phase is done and the rest is not. | The lifted file commits to `docs/design-history/` **immediately**: it is a finished record on its own. The live plan stays private. |
| **The plan retires** — every phase done, or abandoned, or superseded | **Extract what is still OPEN into a new live plan; commit the ORIGINAL whole.** | `docs/design-history/`. |

**The retirement split runs backwards from the old archive rhythm, deliberately.** Lifting the
SHIPPED half assembles a NEW file by pulling prose out, and lifting is where a record gets falsified —
someone decides what to take. Extracting the OPEN half instead leaves the record byte-for-byte
original, so nothing can be lifted wrong. It is also the bounded side: what is open is enumerated by
the plan's own §OPEN index, while what shipped is everything else. Entanglement then never has to be
resolved — it stays together, which is where entangled reasoning belongs — and a §SETTLED index
survives whole instead of being shredded across two files. `docs/design-history/src-chain-encoding.md`
is the build record of this working on the one plan that resisted splitting.

**Migration is copy-and-own, so the committed record is NOT drained first.** Load-bearing substance
lands at its owner per the list above and the plan text stays put; what makes the record safe is a
header pointer naming that owner ("check THOSE first — this file is decision history"), not a
disentangling operation.

**A spec is source of truth only while the work is in flight.** Once merged it is a record of how
something came to be, not a statement of how it currently works — the same reading posture
`CONTEXT.md` opens with, and the same one `docs/design-history/` carries in its banner. This holds
whichever carrier the spec had: a deleted `.scratch/` directory leaves the PR body as the record, a
closed spec issue is that record already.

**Bugs:** there is no in-repo bug file, and a bug never becomes a row in `docs/future-work.md`.
A bug that needs TRACKING is a GitHub Issue (`bug` label). **A bug found and FIXED in the same
change does NOT need one** — the CHANGELOG carries the user-visible delta, the commit body the
cause, and the regression pin the rule; an issue opened and closed in one motion is a fourth copy
of a record three places already hold, and the one least likely to stay accurate. File one when the
record must OUTLIVE the change:

- the fix is deferred or partial;
- someone outside is waiting on status;
- **nothing pins it** — if no test fails when it regresses, the issue is the only memory;
- it is the **SECOND instance of a defect class**. The class is then what wants tracking, not the
  instance: no comment at either enforcing site can see the other, and only a tracked row makes the
  third instance recognisable as one. Live example: [#119](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/119),
  opened over #111, #116 and the 1.17.1 as+size over-match — three doors into "the scan reports what
  the run declines".

The rule that was here through 1.17.0 said "always", and the cost of that spelling is why it
changed: a rule broken routinely stops carrying signal, and a review that flags every same-session
fix trains you to stop reading its flags.

**A RECURRENCE WHOSE INSTANCES ARE ALL FIXED WANTS A GUARD, NOT A ROW.** The second-instance bullet above routes a repeated defect to an issue, and that is right wherever something remains to be done. It is wrong where every instance is already fixed and what recurs is the way the mistake gets made — because filing a bug says a fix is owed, and none is. **Two failures can share a CONSEQUENCE without sharing a cause:** `8714324` was a path argument that did not follow a file move, `d12a1b3` was a seam that reduced three recorded facts to a yes/no, and the only thing they have in common is that a tripwire failed open. An issue for "that class" would be tracking a resemblance, and the bullet's own justification assumes a reader who can recognize the third instance as one — where the causes differ, nobody can, and the row ages into a curiosity. **The artifact that carries the memory is then a test keyed on the shared consequence**, which needs no shared cause and fires at the moment of recurrence instead of waiting to be noticed. `tools/test/replay-vacuity-test.php` is the standing example: it drives every attestation in its subject to failure, then CENSUSES the source so a check added later is covered by a case nobody wrote. Build the guard; where one genuinely cannot be built, say so in the commit body rather than opening an issue to stand in for it.

## Long-lived plan files — the §SETTLED index

A plan that accrues decisions across many passes fails a specific way: **supersession in place.** Live
decisions and withdrawn drafts sit interleaved, and both read as authoritative unless the reader
catches the banner. Length is not the mechanism — discoverability is. Symptom to watch for: an agent
re-deriving from code a question the plan already closed.

When a plan reaches that state, give it a **§SETTLED index at the top**: one row per decision, with
the section title as the anchor (line numbers drift on every edit — record them as a convenience
only, never as the identifier), a container-sensitivity column where the domain has one, and a
**separate OPEN table**, which matters as much as the settled one — treating undecided things as
decided is the more common failure.

The index is pointers, never content; the sections stay authoritative. On archive, the index goes
with the plan and its trigger row in `CLAUDE.md` §Update triggers is deleted — this section stays,
because the practice is reusable and the next long-lived plan will need it.
