# Archive: per-slot reads on `try_datetime` — FW-193 spec (MERGED, UNRELEASED)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle and `docs/agents/issue-tracker.md` §When the work merges own that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md` (the `try_` read-shape table and the `try_datetime` row), `docs/editor-controls.md` (§In a `try_datetime` slot), `docs/editor-tag-previews.md`, `docs/update-triggers.md#datetime-change`, or the PHPDoc at the enforcing site (`bws_datetime_reads()` and `bws_datetime_legacy_reads_rewrite()` in `includes/tags/datetime-tags.php`, `bws_try_run_attempts()` in `includes/helpers/try-slot-loop.php`).

**Provenance.** The build spec for FW-193, decided in a grilling session on 2026-11-19 on top of FW-81's merged `{{datetime}}` / `try_datetime` (companion record: `docs/design-history/datetime-tag-collapse-spec.md`), built across six tickets on the `feature/try-datetime-per-slot` branch, 2026-11-19 to 2026-11-20, and merged to `main` before the release that first ships `try_datetime`. Tickets and commits: 01 the slot grammar's datetime read kind (`129ae96`), 02 `try_datetime` renders per-slot reads (`84c80a1`, measured `e4c9978`), 03 preview mode and warnings per slot (`0b94518`, measured `bcff6ef`), 04 the read control in the slot card (`fa58417`), 05 the old `try_` tag rewrite writes the read into slot A (`7a80588`, measured `b6e2ab5`), 06 the release-gate check (`822e33c`, review fixes `fda4c15`, a doc correction on the editor mount rewrite `4725400`). The ticket files stayed private and were archived, not committed; each ticket's measurements are in its commit body or in `tools/test/datetime-test-matrix.md` (D12, D6.4a to D6.4k, D13). The release delta is the CHANGELOG `[Unreleased]` section. It lived at `.scratch/try-datetime-per-slot/spec.md`. The body below is unchanged apart from the title line.

---

Status: ready-for-agent

Decided in a grilling session on 2026-11-19 (Q1 to Q18, all accepted as proposed), on top of FW-81's merged `{{datetime}}` / `try_datetime` (PR #167, unreleased). FW-81's decisions of record: `docs/design-history/datetime-tag-collapse.md`.

## Problem Statement

An author building a date fallback chain with `try_datetime` can only vary the SOURCE between attempts. The read (which date fields, whether it is a single date or a range, which verb) is one tag-level `key`/`use` that every attempt shares. So "show the event's start and end range, and if the event has none, show the post's published date" or "read `event_datetime` here, or `start_date` off the related venue" cannot be written as one tag. Every other field-reading `try_` tag (`try_text`, `try_content`, `try_image`, `try_email`, `try_phone`) already lets each slot read its own field. The datetime read control is now compact enough to sit inside a slot card, so the editor cost that kept the read at tag level is gone.

## Solution

Each `try_datetime` slot carries its own complete datetime read, written with the same spelling the tag-level `key` and `use` use today, inside the slot value: `{{try_datetime A:key(event_date+start_time;end_time)|B:src(refs,venue);use(published)}}`. The tag-level `key` and `use` are no longer registered on `try_datetime`. A slot 2+ with no read reuses the previous slot's whole read, so the common "same fields, different source" chain stays as short as before. Formatting, link wrap, and fallback stay tag-level. In the editor every slot card embeds the full datetime read control the base `{{datetime}}` tag uses, plus a "Same as previous slot" choice at slot 2+. This lands before the release that first ships `try_datetime`, so the tag-level form never ships and needs no migration.

## User Stories

1. As a site builder, I want each `try_datetime` slot to read its own date fields, so that a fallback attempt can read a different field from the one before it.
2. As a site builder, I want a slot to read a range while another slot reads a single date, so that I can fall back from an event's start-to-end range to a single date.
3. As a site builder, I want a slot to read a verb (`now`, `published`, `modified`), so that the last attempt can be the post's published date when no event field is set.
4. As a site builder, I want a slot to combine a verb and a field (`use(key;now)` with `key(start_date)`), so that one attempt can read "from a field until now" exactly as `{{datetime}}` does.
5. As a site builder, I want a slot with no read to reuse the previous slot's read, so that "same fields, different source" chains need no repeated typing.
6. As a site builder, I want a newly added slot to start out reusing the previous read, so that adding an attempt for a different source is one step.
7. As a site builder, I want a "Same as previous slot" choice in each slot 2+ read select, so that I can see and switch whether a slot reuses the read.
8. As a site builder, I want the field pickers and the Add Time Field / Add End Date/Time buttons hidden while a slot reuses the previous read, so that the card shows only what that slot actually configures.
9. As a site builder, I want removing a slot to write out the read its successor was reusing, so that removing a slot never silently changes what the next slot reads.
10. As a site builder, I want each slot card to show the same read control as the base `{{datetime}}` tag, so that I configure a slot's read the way I already know.
11. As a site builder, I want each slot's Location filter to follow that slot's own source chain, so that the field list offers the fields of the entity that slot actually reads.
12. As a site builder, I want each slot's Location and Type filters to be its own, so that narrowing one slot's picker does not disturb another's.
13. As a site builder, I want the format, `as`, separators, year and midnight options, link wrap, and fallback set once for the whole tag, so that every attempt prints in one consistent style.
14. As a site builder, I want slot A with no read to print nothing and let the next attempt or the fallback take over, so that an unfinished first slot is not an error.
15. As a site builder, I want a slot that states a verb list needing a field but no field to read empty and raise the missing-key warning, so that a half-configured slot is visible rather than silently borrowing another slot's field.
16. As a site builder, I want the editor tag configuration preview to say single or range according to slot A, so that the preview matches the attempt I expect to print.
17. As a site builder, I want read warnings to name the slot they come from (`[⚠ Try: B: 2 field reads but only 1 field key set]`), so that I know which slot to fix.
18. As a site builder, I want read warnings raised only when the tag reads empty, as today, so that a working chain is not cluttered with warnings for attempts that never ran.
19. As a site builder with stored `try_datetime_single` or `try_datetime_range` content, I want the Migration Tool to move it to `try_datetime` with the old shared key in slot A and later slots reusing it, so that the migrated tag renders what the old one did.
20. As a site owner running the Migration Tool, I want the same rewrite applied site-wide, so that stored content matches what the editor would write.
21. As a site builder hand-writing a tag, I want the slot read spelled exactly like the tag-level `key` / `use` value of `{{datetime}}`, so that I learn one spelling.
22. As a site builder, I want `+` inside a `{{join}}` or `{{table}}` slot or another `try_` slot to keep meaning nothing special (ordinary field-name content, as before), so that the datetime spelling does not leak into grammars that give it no meaning.
23. As a site builder who wrote `try_datetime` with a tag-level `key:` on a dev site since the FW-81 merge, I accept that it is ignored after this change, because that form never shipped.
24. As an AI agent authoring tags through GeneratePress AI, I want the syntax hint for `try_datetime` to show the per-slot read, so that generated tags use the shipped wire.
25. As a maintainer, I want the slot read parsed by the one datetime read parser, so that the base tag and the slot cannot drift into two grammars.
26. As a maintainer, I want the slot parser to choose the datetime read kind from a registration flag, never by spotting `+` or `;` in a value, so that what a slot means is decided by its template.

## Implementation Decisions

- **The axis is the whole read.** A `try_datetime` slot carries both `key(…)` and `use(…)`; neither stays tag-level. The tag-level `key` and `use` options are removed from the `try_datetime` template's registration (the base `{{datetime}}` keeps them). (Q1, Q2)
- **New slot read kind `datetime`.** The fold slot parser gains a read kind that holds the parsed read list, produced by running the slot's `key(…)` / `use(…)` arguments through `bws_datetime_reads()` (the owner of that grammar). Today a slot read is one value of kind `analog`, `key`, `fixed`, or `same`, and `use(key;now)` would parse as an analog named `key;now`; the new kind exists so no other reader of a slot read sees a list in a single-field slot. The parser chooses it from a new template registration flag (working name `try_per_slot_reads`), alongside `try_per_slot_key` / `try_per_slot_use`; it never infers it from the value. The JS grammar twin mirrors this. (Q9)
- **Wire spelling is the tag-level spelling, verbatim.** `key(event_date+start_time;end_time)`, `use(key;now)`, `use(published)`. The tokenizer already keeps a `;` inside brackets in one token; `+` is ordinary content in slot values today (ADR 0006). ADR 0006 gets a second allocation entry for `+`: inside the `key(…)` token of a `try_datetime` slot only. `{{join}}`, `{{table}}`, and other `try_` slots keep treating it as ordinary content (corrected 2026-11-19: it was never rejected; user chose to fix the wording, not add a rejection); a datetime slot type in the combining containers stays FW-24's. `+` stays in the reserved list. (Q6, Q15)
- **Carry-over is the whole read, spelled `use(same)`.** A slot 2+ with no read reuses the previous resolving slot's whole read (the selecting-container rule, unchanged). A slot that states any read part states its own complete read and borrows nothing; `use(key;now)` with no `key(…)` is an empty read plus the missing-key warning. `same` is not a datetime verb, so it is unambiguous inside `use(…)`. Slot A cannot carry over; with no read it is a read-less attempt and prints nothing. (Q3, Q10, Q11)
- **Mode per slot, formatting per tag.** A slot's single/range mode comes from its own read count. `as`, `rangeSep`, `format`, `timeSep`, `showCurrentYear`, `showMidnight`, link wrap, and fallback remain tag-level and apply to whichever attempt wins. (Q4)
- **Render.** The `try_` slot loop hands each attempt its own read through the slot source hand-off, and the attempt reads exactly as `{{datetime}}` does with those reads (the existing resolve seam with the mode derived from options). First non-empty attempt wins, unchanged.
- **Editor.** The fold slot control embeds the datetime read control in each slot card in place of the plain field picker, when the fold config says the template's slot read is datetime. The slot's source chain reaches the read control through the existing seam that hands a slot's chain to its picker, so Location filters by that slot's entity. Location and Type filter state is per slot, editor-only, never on the wire; the Type filter's existing sharing between the reads inside one control is unchanged. At slot 2+ the read control's first select gains "Same as previous slot" (label from PHP, as for the `use`-enum slots), which writes `use(same)` and hides the field pickers and the Add buttons. Adding a slot seeds `use(same)`. Removing a slot materializes the removed slot's read into a successor that was reusing it, through the existing removal path. (Q7, Q14, Q17, Q18)
- **Preview and warnings.** The editor tag configuration preview takes its single/range wording from slot A's read. The three datetime read warnings (missing key, surplus key, a third `+` operand) are computed per slot and prefixed with the slot letter, raised only when the tag reads empty, joined with `; ` as today. `docs/editor-tag-previews.md` rows change to match. (Q12, Q13)
- **Old-tag rewrite retargets.** The shared rewrite rule for `try_datetime_single` / `try_datetime_range` → `try_datetime` (PHP, used by the Migration Tool, and a pure JS twin held to it over the mount corpus) writes the old tag-level read into slot A as `key(…)` / `use(…)`; slots 2+ carry nothing and so reuse it. `datetime_single` / `datetime_range` → `{{datetime}}` is unchanged. The four hidden old tags themselves are unchanged. (Q5) (Revised 2026-11-19, user decision: the live editor mount on the hidden old `try_` tags keeps tag-level placement, because those templates still read the tag-level `key`.)
- **No migration for tag-level `try_datetime` reads.** The form never shipped; after this change a tag-level `key:` / `use:` on `try_datetime` is an unregistered option and is ignored. (Q16)
- **Release gate.** This blocks tagging the release that first ships `try_datetime`. The CHANGELOG `[Unreleased]` FW-81 entry and README overview are edited in place (net delta from the last shipped release), not given a new entry. `docs/tag-reference.md`'s `try_` read-shape table and `try_datetime` row move `try_datetime` to its own shape. The AI syntax hint for `try_datetime` is updated.

## Testing Decisions

A good test drives the feature through the highest existing seam and asserts what an author sees: rendered output for a tag string, the wire a control writes, the preview text for a configuration. It never asserts an intermediate struct that only exists to get there. No new seams.

- **Render (highest seam).** On the testbed, `bws render-tag` over the D12 rows of `datetime-test-matrix.md`, rewritten in the per-slot form, with new rows for: slots of different modes, a verb slot as the last attempt, carry-over from slot A, slot A with no read, and a slot with a verb list but no key. The rows are also generated as visible blocks and the page snapshot is re-captured in the same commit. Pure: `try-slot-loop-test.php` drives `bws_try_run_attempts()` with the datetime resolve seam for the same cases. Prior art: D12 rows; the `try_text` cases in `try-slot-loop-test.php`.
- **Wire grammar.** `slot-fold-test.php` round-trips the new read kind (parse then emit is identity), accepts `+` only in a `try_datetime` slot's `key(…)` and keeps it ordinary content in `{{join}}` / `{{table}}` / other `try_` slots, and parses `use(same)`. `slot-fold-twin-test.php` holds the JS twin to the same answers. Prior art: the `use`/`key`/`fixed` precedence cases there.
- **Editor.** `slot-fold-repeater-test.js` over the rendered control tree: the embedded read control per slot, "Same as previous slot" at slot 2+ only, a new slot seeded `use(same)`, removal materializing a reused read, and filter state staying per slot. `slot-fold-picker-seam-test.js` proves the slot's chain reaches the real read control's Location filter. Prior art: the `use`-enum slot cases and the `same` materialization cases.
- **Preview.** `preview-label-test.php` for slot-A-driven wording and per-slot warning prefixes. Prior art: the D6.4 `try_datetime` warning cases.
- **Old-tag rewrite.** `datetime-migration-test.php` over the mount corpus, PHP and JS twin agreeing, asserting the slot-A placement for both old `try_` forms. Then `verify-migration.php` §4b on the testbed. Prior art: the existing FW-81 rewrite cases.
- **Triggers.** The CLAUDE.md Update-triggers rows this touches (folded slot grammar, folded slot control, folded slot registration or resolution, slot source hand-off, `try_` slot loop, datetime read control, old-datetime-tag rewrite, editor preview text, AI syntax hint) each name further harnesses; run those as listed there.

## Out of Scope

- A `datetime` slot type token for `{{join}}` / `{{table}}`, and any datetime read in a combining container (FW-24).
- Per-slot formatting options (`as`, `format`, separators, year, midnight), per-slot link wrap, per-slot fallback.
- Changes to `{{datetime}}` itself, or to the four hidden old tags (unregistering them is FW-192).
- Compatibility for a tag-level `key` / `use` on `try_datetime`.

## Further Notes

- The ADR 0006 amendment written for FW-81 already anticipated this as "a second allocation"; that entry is written as part of this work, not deferred.
- `same` must stay out of the datetime verb vocabulary for as long as slots carry datetime reads; the new verb list's owner should say so.
- At merge: this spec lifts to `docs/design-history/` and the PR body is written, per `docs/agents/issue-tracker.md` §When the work merges.
