# `{{join}}` threads `limit` per-slot but not the inner list `sep` (v1)

**Status:** accepted (grill 2026-07-17; pre-build, hardening `.claude/plans/combine-text.md`).

> **Update (1.16.0):** the tag-level assembly separator was renamed `sep` → `valueSep` under FW-52
> (serialization-group correctness — a bare tag-level `sep` scattered into the source group and
> clashed with the list-mode `sep`). The rename happened for that reason, not for this ADR's
> per-slot concern, but its consequence lands here: the wire collision that blocked a per-slot
> `{N}-sep` (§Considered options, first bullet) **no longer exists** — the tag-level key is now
> `valueSep`, so a slot-1 bare `sep` is free. Per-slot inner `sep` stays deferred (still an edge
> affordance, no evidence it's wanted; tracked `docs/future-work.md` FW-44), but its blocker
> dissolved. The v1 decision below (`{N}-limit` only, inner sep = text default) is unchanged and
> still accurate.

A `{{join}}` slot that resolves in list mode (`srcTermIn` / `src:ref` reading N targets) absorbs
the base `text` list resolve, which reads BOTH `$options['limit']` and `$options['sep']` off the
slot's own option array (base-tags.php:662-663, 688-689). v1 threads a per-slot `{N}-limit` (slot 1
bare `limit`) into the slot's text resolve, but does NOT expose a per-slot inner `sep` — a list slot
joins its items with text's default inner separator `', '`. This is a deliberate asymmetry: a future
reader sees join absorb text's list mode and will ask why only one of the two list knobs is tunable.

## Considered options

- **Thread both `{N}-limit` and `{N}-sep` (rejected for v1).** Full absorb of text's list knobs. The
  slot-1 form of the inner-sep key is a bare `sep`, which **collides on the wire with join's
  tag-level `sep`** (the assembly separator between slots). GB's `parse_options()` is a flat key map
  (no scoping), so two `sep` meanings can't coexist under the same key. Resolving it requires either
  a slot-1-only naming exception (irregular) or renaming the tag-level assembly key (`sep` → `glue`),
  a wire-visible change not worth taking for an edge affordance.
- **Rename the tag-level assembly `sep` up front to free `sep` per-slot (rejected for v1).**
  Pre-emptively pays the wire-rename cost before any evidence the per-slot inner sep is wanted.
- **Thread `{N}-limit` only, inner sep = text default (accepted).** Fixes the load-bearing defect
  (without `limit`, a list slot returns text's default 1 target — a `srcTermIn`/`ref` join slot
  silently truncates to one item) while sidestepping the collision. The per-slot inner sep is a
  genuine edge (a term/ref list slot INSIDE a join wanting a non-default inner separator).

## Consequences

- A list-mode join slot renders >1 target (driven by `{N}-limit`) but always joins those items with
  `', '`. If per-slot inner-sep tuning is ever needed, the tag-level assembly key gets renamed then
  and `{N}-sep` is added — tracked as a `docs/future-work.md` row (detail home: combine-text.md
  §Open/deferred).
- The absorb claim narrows precisely: join absorbs text's list `limit` per-slot; the inner `sep` is
  absorbed at text's DEFAULT, not author-tunable per-slot in v1. Plan prose and the J19 row state
  this exactly rather than the broader "text's own sep/limit" the earlier draft promised.
