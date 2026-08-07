# Limits are stated where the source is stated

**Status:** accepted (2026-08-06 grill over the built UI, `.claude/plans/per-step-limit.md` §The grill; regrilled 2026-08-07 after #63's premise was withdrawn).

A tag's source is stated as an ordered chain — a root plus fanning steps — so a bound on how far
a source spreads is stated on the **step** it bounds. Each fanning step carries its own optional
`limit`; there is no tag-level one. The control that used to sit at tag level is unregistered on
every family (#62), and migration writes step limits and never a tag-level key (#59, #61).

The tag-level `limit` survives as a **read**. Stored wire still carries it, `bws_clamp_limit()`
still honours it, and a flat source spelling still selects the old default of `1`
(`bws_limit_default()`). That is not a second place a limit is stated — it is the compatibility
mechanism for wire no migration can reach, and it is the reason the first half of this decision
could be taken at all.

This is one ADR rather than two because the halves are not separable. The control can retire only
because the spelling-selected default keeps unreachable flat wire rendering correctly; the
spelling-selected default is only tolerable because retiring the control removes the field that
would otherwise display two different defaults for the same blank box. Written apart, each file
has to restate the other.

## The rule has one clause, and the two-clause version is rejected

The version taken to the 2026-08-06 grill had two clauses:

1. A chain states its source as steps, so limits go on steps.
2. A flat-select family states its source as one step, so the tag-level key **is** its limit.

Clause 2 is **not** part of this decision. It has zero instances and no prospect of any: after #62
nothing registers a `limit` control, `{{join}}`'s slots fold so their per-slot limits are step
limits (see ADR 0003 below), and `term_`'s limit — the one family clause 2 was written for —
belongs on its fanning step for the same reason every other family's does.

It never had an instance, including on the day it was written. It was inferred from `term_`'s
registration arrays, which correctly show no `limit` control, and the inference drawn from that
absence — *"so a `term_` tag with a fanning source silently returns one result for want of a
control"* — was false one layer below where the survey looked. `term_` honours `limit` nowhere:
its constructor hands a single entity id to cores that read one field off one entity, and the fan
its arm produces is discarded rather than bounded. Registering the pair would have shipped two
controls nothing reads. (#63, disputed and withdrawn 2026-08-07.)

Keeping clause 2 anyway was considered, on the grounds that it explains why the tag-level value is
still read. It was rejected because it explains that badly — the value is read because migration
cannot reach every tag, which is a compatibility fact about *storage*, not a claim about where an
author states anything. An ADR whose stated rule half-describes nothing invites exactly the
re-derivation that produced #63.

## Considered options

- **Limits on steps, no tag-level control, era-selected default for stored wire (accepted).**
  Costs: the same conceptual source is bounded differently by spelling, which is an
  [ADR 0004](0004-serialized-tag-string-human-readable.md) readability cost paid to avoid touching
  a stored row; and the top-level link gate is count-based, so link-wrapping differs by spelling
  too.
- **Keep the tag-level limit as a whole-list bound (rejected).** It fails in both directions and
  is never the useful knob. With ONE fanning step it is redundant — the same setting as that
  step's own limit, written twice. With TWO it is arbitrary: it slices the flattened walk at a
  position set by fan-out widths the author cannot see, parent-major only because the engine
  happens to iterate that way. That is the same objection a genuine "at most N overall" was
  rejected for, and it remains a possible future *designed* feature rather than this key.
- **Relocate an explicit limit to the FIRST fanning step (rejected).** Proposed and withdrawn
  inside the grill. Elegant, because the first step has one input so per-input and total coincide
  there — but it restates the author's number rather than preserving what the tag did. Migration
  puts `N` on the LAST fanning step and `1` on every earlier one (#59), because per-step limits
  are per-input and multiply.
- **Force upgrade-time migration (rejected).** Impossible, not merely expensive. The content
  scanner reads `post_content` only, and the site survey found live wire inside an ACF field.
  There is no pass that can visit every tag.
- **Accept the output change with an Upgrade Notice (rejected).** ~110 authored instances across
  the surveyed databases would begin rendering extra results silently, with no author present.
  The link gate is count-based, so those tags would also stop being links. A notice cannot
  substitute for serialization; it can only accompany it.

## Precedent: ADR 0003 decided this once already, for one tag

[ADR 0003](0003-join-per-slot-limit-not-sep.md) (July 2026) gave `{{join}}` a per-slot `limit`
rather than a tag-level one, because a join slot's source is the slot's, not the tag's. That is
this rule, reached independently and five months earlier, from a different direction — a defect
report about list slots truncating to one, rather than a design pass over chains.

A rule found twice is worth recording as such. It also forecloses a misreading: 0003 predates
chains entirely and describes a `{N}-limit` key that no longer registers, so a reader who meets it
after this ADR could take it as a counter-example — a family that put its limit somewhere other
than a step. It is the opposite. 0003 carries a 1.17.0 amendment saying where its carrier went.

## Consequences

- **Migration writes step limits and never a tag-level key.** One mapping,
  `bws_fold_chain_apply_legacy_limit()`, shared by the content scanner, the editor mount migrator
  and the author-initiated conversion, so a converted tag and a scanned one are byte-identical.
  Schema and the full value table: [`tag-reference.md` §List mode](../tag-reference.md#list-mode-limit--sep).

- **There is no deprecation path for the tag-level read, in 1.x.** Neither the explicit
  `limit:N` read nor the flat-spelling default of `1` is scheduled for removal. The population is
  unenumerable by construction — the same fact that made forced migration impossible above — so
  any removal is a permanent, silent output change on tags nobody can find, with no author
  present. It would be incoherent to reject forced migration for unreachability and then schedule
  a removal that assumes reachability. This is scope, not a promise: revisiting it is a
  major-version decision and would need its own ADR.

- **Removing the option did not remove the value — for the READER. Two places it stopped holding
  for the WIRE** (#62, found by eyeballing the shipped behaviour rather than predicted):

  - An explicit `limit:0` / `-1` is now **deleted** at depth 0 rather than left as written. The
    old rule was right while the control existed: `0` already means unlimited, which is what chain
    wire defaults to, so there was nothing to carry onto a step and the author could see the `0`
    and clear it. Retiring the field turned a visible value into a token on chain wire no editor
    surface can reach. Deleting is faithful because the chain spelling already selects unlimited.
    Scoped to depth 0 — a slot caller must not, since the same mapper also renders unmigrated flat
    wire, where absence takes the flat era's `1`.
  - A tag-level limit is legacy **by position, not by spelling**. Wire that was already a chain
    was skipped by both migration paths, which left the one shape where a bound is invisible:
    `{{text src:terms,department|use:title|limit:1}}` renders one term while the step's own Limit
    field reads unlimited and nothing in the panel can reach the number. Both halves now absorb it
    onto the step and delete the key. NUMERIC ONLY on that branch: the flat branch materializes
    the era's default of `1` when the key is absent or unreadable, but chain wire is not changing
    era and has no default to carry, so materializing one would bound a tag that renders unlimited
    today.

- **A family with no chain *control* still compiles chain wire — except one.** The question this
  rule invites is "so where does `{{term_text}}` state its limit?", and it has been answered
  wrongly once already, by a competent reader working from the registration arrays. The answer is
  not "flat-select families keep a tag-level key" (see above), and it is not "those families
  cannot chain":

  - **`{{table}}` and `{{call}}` compile chain wire today.** Both resolve through
    `bws_wrapper_ref_steps()`, which since 1.17.0 reads the compiled chain's leading run of `ref`
    steps, so `src:refs,a;refs,b` steps both. They lack the authoring control, not the capability,
    and a hand-written step limit is honoured — which is [ADR 0004](0004-serialized-tag-string-human-readable.md)
    doing exactly what it exists to do.
  - **`term_` is the single exception.** `TagTemplateRegistry::make_modifier_callback()` resolves
    its base from the template's `base_source_key` rather than from the wire, and dispatches on a
    literal `'ref' === $source`, so chain wire matches nothing and falls through to the
    current-term branch. Its fan is then collapsed unconditionally — `bws_first_post_id_from_sources()`
    for `src:ref`, first-non-empty for `srcTermIn` — so there is no list for any limit to bound,
    at either position. **That is a defect, not a design**, and it is why `term_` has no limit
    control rather than an oversight about which control to add.

    FW-63 swapped the base arms onto a kind query (`bws_base_src_resolution()`) and closed;
    `class-tag-template-registry.php` was not in its scope and never received it. So no tracker
    row currently owns the modifier arm. When one does, `term_` gets step limits with it, by this
    rule and with no exception needed.

- **The flat source SPELLING is closed to new authoring; the flat source READ is not deprecated.**
  Two different statements that will be read near each other. An author can no longer write flat
  `src:ref` / `srcTermIn` from any panel — the chain control replaced those siblings — but every
  stored instance is read forever under the bullet above.
