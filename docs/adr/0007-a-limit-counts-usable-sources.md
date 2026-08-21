# A limit counts usable sources

**Status:** accepted (2026-08-20, the grill session opened on
[#118](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/118)); **revised in
place 2026-08-21** before any release carried it — the original axis ("a limit counts usable
RESULTS", where usable included a non-empty read) proved non-deterministic and was replaced.
Hardened against
[`docs/design-history/deterministic-source-selection.md`](../design-history/deterministic-source-selection.md),
the whole grill record — including the argument for the axis this one replaced. Nothing published
ever stated the old axis.

A limit bounds **usable sources** — candidates that pass the source gate — never candidates merely
emitted, and never a count of non-empty reads. `usable` is a property of a SOURCE:
**resolvable × exists × visible**. A source failing the gate never consumes budget; an EMPTY READ
of a usable source consumes its slot and outputs nothing. Field population is no part of the test,
anywhere. The invariant is [`CONTEXT.md` I19](../../CONTEXT.md#i19--a-limit-bounds-usable-sources);
the schema term `usable` lives at
[`tag-reference.md` §List mode](../tag-reference.md#list-mode-limit--sep); the level terms
(`resolvable`, `exists`, `visible`) at [`CONTEXT.md` §Language](../../CONTEXT.md#language).

**Companion to [ADR 0005](0005-limits-are-stated-where-the-source-is-stated.md), not an amendment.**
0005 owns WHERE a limit is stated (on the step it bounds); this ADR owns WHAT the stated number
COUNTS. Nothing in 0005 moves: the control placement, the migration mapping, the era-selected
default and the permanent tag-level read all stand.

## The rule

Every step's limit is the SAME kind of bound: a resolution bound over usable sources, applied per
input, in the engine, AFTER the source gate has dropped what fails it and BEFORE the next step
runs. There is no separate "output bound" at the terminal position. The tag-level `limit` is the
one exception in SCOPE only — a global slice over the whole resolved list, a preserved holdover
(ADR 0005), not a design position — and it, too, slices usable sources.

The gate applies at every position uniformly. Consequence, decided rather than incidental: **an
entity that fails the gate cannot be a stepping stone** — a chain routed through a post the viewer
may not read is cut at that hop, even when the hop's targets are public.

**A stated limit applies to the step it is stated on, not to the chain.** That is ADR 0005's own
sentence applied one level down, and it settles what happens when a step is appended after a
bounded one: the number stays where the author put it and goes on bounding that step.

**A tag that can only output one result ignores every limit at every position** and reads the
FIRST usable source its whole chain produces — outputting that source's read even when the read is
empty. A bound that can only ever subtract from a tag that returns one result is not a control, it
is a trap. This is why `content`, `permalink` and `image` carry the `takes_first_usable` template
capability and offer no per-step limit control at all. (This ADR names the ABSENCE; the control's
own label is owned by [`editor-controls.md`](../editor-controls.md) and has changed since — it was
**Limit results** when this was accepted.)

## Why the read-based axis was reversed

The 2026-08-20 form counted non-empty reads: a limit "never spent budget on an empty read", and a
collapsing tag searched its fan for the first POPULATED value. That makes source selection depend
on WHICH FIELD the tag asks for — two adjacent tags on the same source path can read different
entities (`{{title}}` from post A, `{{image}}` from post B), and no configuration can pin which
source is used. The pattern it breaks (a `limit(1)` refs step feeding several one-field tags) is
the plugin's most common composition. Determinism won: selection is field-independent, so the same
path always reads the same entity, and an author's `limit(1)` means "the first stored reference,
only".

The populated-search is preserved as a dormant, caller-less predicate seam on the selector, for a
possible future tag-level OPT-IN (tracked in `docs/future-work.md`); it is not a default anywhere.

## Considered options

- **Count usable sources (accepted, 2026-08-21).** Deterministic; one rule at every position; the
  number pins sources the author can enumerate.
- **Count non-empty reads (the 2026-08-20 acceptance — reversed).** Non-deterministic across
  adjacent tags; see above. Its one real virtue — "show me the picture, wherever it is" — returns
  as the opt-in.
- **Keep counting candidates examined WITHOUT a gate, document it (rejected 2026-08-20, stands).**
  Freezes a drift as a contract; a deleted or unreadable entity spending budget is the 1.17.0
  regression's shape.
- **A second token (`take(N)`) (rejected 2026-08-20, stands).** Two numbers for one author-visible
  quantity.

## What this does not touch

- **The resolved-source payload decision ([ADR 0002](0002-resolved-source-variable-payload.md)).**
  "Resolved" keeps its mechanical meaning — a bound was emitted, whatever it names. The gate is a
  LATER test; existence is its own level (`exists`), not part of resolved-source-hood. The
  sharpening remains declined.
- **Where a limit is stated (ADR 0005), and every migration mapping it describes.**
- **`{{table}}`'s `rows` step limit** — `{{table}}` declares `takes_first_usable` explicitly false.

## Consequences

- The gate ships WITH this release, both arms live (exists and visible), so "usable" reaches its
  final meaning once: no interim tightening, no matrix rows written to be revised. What each kind
  is tested against is the gate's rule, not this decision's — `bws_source_gate()`'s PHPDoc.
- Two subtractive author-visible changes ride one Upgrade Notice: the term route of collapsing
  tags no longer searches past the first term, and unpublished content stops resolving for viewers
  who cannot read it. Everything else renders the same or more.
- The visibility filter HOOK does not ship; the predicate is filterable by construction and the
  hook lands when a consumer asks (restrict-only, AND-composed).
