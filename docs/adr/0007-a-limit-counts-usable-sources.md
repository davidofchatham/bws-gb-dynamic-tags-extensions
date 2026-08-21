# A limit counts usable results

**Status:** accepted (2026-08-20, the grill session opened on
[#118](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/118) — which arrived
as an editor-surface cleanup and turned out to be the visible corner of this decision).

A limit bounds **usable results** — candidates that survive to output — never candidates examined.
Anything filtered out on the way (an entity the viewer cannot read, an empty read) never consumed
budget. The invariant is [`CONTEXT.md` I19](../../CONTEXT.md#i19--a-limit-bounds-usable-results);
the schema term `usable`, and its avoid-note, live at
[`tag-reference.md` §List mode](../tag-reference.md#list-mode-limit--sep).

**Companion to [ADR 0005](0005-limits-are-stated-where-the-source-is-stated.md), not an amendment.**
0005 owns WHERE a limit is stated (on the step it bounds); this ADR owns WHAT the stated number
COUNTS. Nothing in 0005 moves: the control placement, the migration mapping, the era-selected
default and the permanent tag-level read all stand.

## The rule

A step's limit bounds its usable outputs, where "usable" is the strongest test computable at that
step. These are two different QUANTITIES, not one quantity measured at two precisions:

- **An intermediate step's limit** bounds how far a fan SPREADS — a resolution bound over valid
  entities. Applied per input, by the engine, as today.
- **The last step's limit, and the tag-level `limit`,** bound how many results RENDER — an output
  bound over non-empty (eventually: visible and non-empty) reads. Applied by the collector, after
  reading.

Stated the other way round, which is the clearer form: the bound counts only what will be output.

**A stated limit applies to the step it is stated on, not to the chain.** That is ADR 0005's own
sentence applied one level down, and it settles what happens when a step is appended after a
bounded one: the number stays where the author put it and goes on bounding that step — which now
spreads rather than renders. The bound's observable meaning changing there is correct, not a
defect.

**A tag that can only output one result ignores every limit at every position** and outputs the
first usable result its whole chain produces. A bound that can only ever subtract usable results
from a tag that returns one result is not a control, it is a trap. This is why `content`,
`permalink` and `image` carry the `takes_first_usable` template capability and offer no
**Limit results** control.

## Why this is a restoration, not a redesign

Both controls have always been labelled in terms of results — the retired tag-level control was
*Result Limit*, the step control is *Limit results*, helped by "Maximum number of results". The
control has promised an output count on both spellings since the beginning; the CODE drifted from
the LABEL, not the other way round. Consequence: the behaviour changes ship as CHANGELOG **Fixed**
entries, not **Changed**.

## Considered options

- **Count usable results (accepted).** Cost: the meaning of a stored number changes with no wire
  change to mark it — a `limit:3` that showed one result starts showing three. Accepted because
  every such change renders MORE where something rendered less (or the right entity where it read
  the wrong one), and because the number finally does what its label always said.
- **Keep counting candidates examined, document it (rejected).** Freezes a drift as a contract.
  It is also the shape of the 1.17.0 collapsing-tag regression: the Migration Tool stamped a
  `limit(1)` whose candidate-counting read narrowed a search the flat tag ran in full.
- **A second token (`take(N)`) for the new counting, keeping `limit(N)` as-is (rejected).** Two
  numbers for one author-visible quantity, and the old one would remain wrong under its own label.
  One token, consumer-dependent meaning: `limit(N)` = N usable.

**On the Upgrade Notice — and how this differs from 0005's rejected-options list.** 0005 rejected
"accept the output change with an Upgrade Notice" because a notice cannot SUBSTITUTE for
serialization — it can only accompany it; the 1.17.0 notice ACCOMPANIED a serialization change,
which 0005 explicitly permits. Here there is **no serialization change to accompany**: this
decision changes how stored wire is READ, writes nothing, migrates nothing, and re-serializes
nothing. The additive slice (the collapsing tags) therefore ships with no notice at all; the later
slices carry their own notice for their one subtractive consequence, alongside nothing, because
there is still nothing being rewritten.

## What this does not touch

- **The resolved-source payload decision ([ADR 0002](0002-resolved-source-variable-payload.md)) is
  untouched.** A sharpening that would have made validity part of resolved-source-hood was
  examined and declined: the engine emitting a bound for a deleted post is correct — that source
  simply fails a later test. A reader should not go looking for a payload change here.
- **Where a limit is stated (ADR 0005), and every migration mapping it describes.**
- **`{{table}}`'s `rows` step limit**, which bounds repeater rows and is the most load-bearing
  limit in the plugin — `{{table}}` declares `takes_first_usable` explicitly false.

## Consequences

- "Usable" currently means **a non-empty read** and TIGHTENS to **visible and non-empty** when the
  visibility gate ships. Regression rows written against today's meaning are known to need
  revising; the release notes say so rather than letting it be discovered.
- `tag-reference.md` §List mode keeps describing the shipped slice-before-read order for
  list-mode tags until their slice lands; I19 names the disagreement out loud.
- The glossary terms this ADR depends on (`resolvable`, `visible`) ship with it, in
  [`CONTEXT.md` §Language](../../CONTEXT.md#language).
