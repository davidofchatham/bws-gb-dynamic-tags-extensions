# Archive: collapse the two datetime tags into one `{{datetime}}` — FW-81 spec (MERGED, UNRELEASED)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle and `docs/agents/issue-tracker.md` §When the work merges own that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md` (the `datetime` section), `docs/update-triggers.md#datetime-change`, `docs/editor-tag-previews.md`, or the PHPDoc at the enforcing site (`bws_normalize_datetime_options()` and `bws_datetime_legacy_reads_rewrite()` in `includes/tags/datetime-tags.php`).

**Provenance.** The build spec for FW-81, written 2026-11-17 from the plan's decisions of record (companion file: `docs/design-history/datetime-tag-collapse.md`), built across sixteen tickets on the `feature/datetime-collapse` branch, 2026-11-17 to 2026-11-19, and merged to `main` as [PR #167](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/pull/167) on 2026-11-19 (merge commit `0e9f8e6`), not yet in a tagged release. Tickets and commits: 01 measure the two unknowns (`119f67b`), 02 `{{datetime}}` renders single and range (`5a35522`), 02b merge the resolve seams (`6dfeb62`), 02c unify the time display paths (filed as #164), 03 verbs and `use` ordering (`a534520`), 04 `try_datetime` (`13057ca`, `0625f17`), 05 editor preview text and warnings (`49fa462`), 06 text-family guards and the slot reroute (`d521f74`), 07 hide the old four tags (`b8273fa`, `006b921`), 08 read control for the `key` entries (`268b032`), 09 verbs in the read control (`1c5c944`), 10 mount migration (`6f93bbc`), 10b mount migration for the `try_` forms (`976aa90`), 11 converter migration (`b46ad6c`), 12 tag-string preview tool (`a606c31`), 13 docs, tracker and full sweep (`674dfd5`). The ticket files stayed private and were archived, not committed; each ticket's measurements are in its commit body or in `docs/gb-constraints.md`. The release delta is the CHANGELOG `[Unreleased]` section. It lived at `.scratch/datetime-tag-collapse/spec.md`. The body below is unchanged apart from the title line and one repaired path (the decisions-of-record file moved here from `.scratch/plans/`).

---

Status: ready-for-agent

Decisions of record: `docs/design-history/datetime-tag-collapse.md` (§SETTLED S1–S18, D1–D27, §Decisions 2026-10-12 and 2026-11-17). This spec does not restate their reasoning; it cites them by id. Where the two disagree, the plan's §SETTLED index wins and the disagreement is surfaced, not resolved by default (CLAUDE.md, doc/code drift rule).

## Problem Statement

An author who wants an event date shown as a single value uses `{{datetime_single}}`. The moment the event gains an end, they must switch to `{{datetime_range}}`. GenerateBlocks discards every option when the tag type changes, so the author re-enters the source, the fields, the format, the link, and the fallback from scratch. The two tags also carry six differently named field options between them, which makes the picker, the editor controls, the previews, and the migrations twice as large as the concept.

Splitting a date and a time across two custom fields is awkward too: today it takes a separate "time key" option per end, and an event that starts at a date plus a time and ends at a time on the same day needs four options set in the right pairs.

## Solution

One tag, `{{datetime}}`, whose mode is never stated. The number of reads decides it: one read renders a single value, two reads render a range. Fields are written the way every other tag writes them, with a `key` option. A date field and a time field that make up one moment are joined with `+`. Two moments are separated with `;`.

```
{{datetime key:event_datetime}}                       single
{{datetime key:event_date+start_time}}                single, date and time in separate fields
{{datetime key:event_date+start_time;end_time}}       range, end is time-only so the date is inherited
{{datetime key:start_dt;end_dt}}                      range, two datetimes
{{datetime use:published}}                            the post's published date
{{datetime use:key;now|key:event_date+start_time}}    from a field until now
{{datetime use:now;key|key:end_date}}                 from now until a field
```

`use` appears only when a verb (`now`, `published`, `modified`) is mixed in. It then lists the reads in order, `key` included, because position is what distinguishes "from now until a field" from "from a field until now". A `use` made only of `key` reads is redundant and is removed automatically.

The two old tags and their `try_` forms keep working and keep rendering existing content. They disappear from the tag picker, and a one-time rewrite (in the editor and in the Migration Tool) moves existing tags onto the new form.

## User Stories

1. As a site author, I want one datetime tag for both single dates and ranges, so that adding an end date to an event does not make me re-enter every other option.
2. As a site author, I want the tag to decide single versus range from how many dates I supplied, so that I never choose a mode.
3. As a site author, I want to pick a date field and a separate time field for one moment, so that events stored as two custom fields render as one date and time.
4. As a site author, I want an end that is only a time to take the start's date, so that "March 3, 6 PM to 9 PM" does not need the date entered twice.
5. As a site author, I want "from this field until now" and "from now until this field", so that ongoing and upcoming displays need no custom code.
6. As a site author, I want the post's published or modified date as a read, so that I can show it without a custom field.
7. As a site author, I want to add a second date with an Add control and remove it again, so that I can move between single and range without editing text.
8. As a site author, I want removing the first of two dates to shift the second into its place, so that no empty slot is left behind.
9. As a site author, I want the editor preview to show what the tag will print, so that I can check a configuration without saving.
10. As a site author, I want a preview warning when my configuration is inconsistent, so that I find the problem before a visitor does.
11. As a site author, I want a tag with a missing or empty read to show its fallback, so that a page never prints a broken value.
12. As a site author, I want the existing options (format, separators, show current year, show midnight, link, fallback, source) to behave exactly as before (apart from the 02c time-display fixes, which correct defects), so that merging the tags changes nothing I already tuned.
13. As a site author with existing `datetime_single` and `datetime_range` tags, I want them to keep rendering unchanged after the update (apart from the 02c time-display fixes, which correct defects), so that nothing on my live site breaks.
14. As a site author with existing tags, I want the Migration Tool to rewrite them to `{{datetime}}`, so that I can adopt the new form without hand-editing.
15. As a site author opening an old tag in the editor, I want its options converted to the new form when the control mounts, so that the control shows my reads instead of blank fields.
16. As a site author, I want a range whose start was never set to be left alone and reported, so that the migration never silently changes what a tag prints.
17. As a developer hand-editing a tag string, I want `key:a;b` to mean two reads and `a+b` to mean one read from two fields, so that the wire is readable and writable by hand.
18. As a developer, I want an unrecognized or surplus read to be ignored, not to break the page, so that a typo costs me one value and not the whole render.
19. As a developer, I want a third operand (`a+b+c`) ignored with a warning (the preview's when the tag reads empty, the read control's notice otherwise), so that the two-field rule is visible without crashing anything.
20. As a developer, I want `use:key;key` removed automatically when saved, so that equivalent spellings do not accumulate.
21. As a developer, I want `use` and `key` entry counts that disagree to degrade predictably (a missing entry is an empty read and takes the fallback, a surplus entry is ignored) with a warning (the preview label's when the tag reads empty, since the preview channel shows a label only then; otherwise the read control's notice, which names the surplus field before an edit removes it), so that hand edits fail visibly and safely.
22. As a developer, I want the old six option names to keep being read, so that a custom field of old content or a stored widget still renders until it is migrated.
23. As a developer of an integrating plugin, I want the merged tag to be registered through the same boundary as every other tag, so that a name collision is detected and reported the way it already is.
24. As a maintainer, I want one place that parses a datetime tag's options, so that the single and range cores cannot drift apart again.
25. As a maintainer, I want the tag-string preview tool to show the new syntax, so that the grammar is demonstrable the day it ships.
26. As a maintainer, I want the README, CHANGELOG, and reference docs to describe the merged tag, so that nobody reads about two tags that no longer appear in the picker.
27. As a visitor, I want dates and times to render exactly as before for content that was not edited (apart from the 02c time-display fixes, which correct defects), so that the update is invisible to me.

## Implementation Decisions

**Tag and registration.**
- The merged tag is `{{datetime}}` (D2), registered at `datetime_single`'s existing position in the base-tag order. `try_datetime` replaces the two `try_` forms (D11). The old four tags stay registered and rendering, hidden from the picker with GenerateBlocks's `visibility => false` argument (D9). Unregistering them is not part of this spec. Each of the four also has a `type:'tag'` migration entry (old name to new) with `lifecycle: 'deprecated'` (D29), so a registered, hidden tag is also listed in the settings page's Deprecated box.
- The merged tag keeps `type: cross-source`, no supports, and link-wrap, the same shape both old tags already share (S3).

**Reads and the wire (D19 to D22, S4, S10, S11).**
- A read's slug names the option that carries its arguments. The nth `key` read takes the nth `;`-separated entry of the `key` option. Verbs take no entry.
- `+` joins at most two operands inside one entry: a primary field (date, datetime, or time, S8) and an optional supplementary time field. The primary may be time-only, which triggers the already-shipped date inheritance (S9). Extra operands are ignored with a preview warning. `+` is currently reserved in the fold grammar, so ADR 0006 is read before it is allocated.
- Positions are ordinal and never named (S4). No third position exists (S11). Future optional axes are named tokens after the positional arguments (S12).
- `use` is present only when a verb is present, and is then the complete ordered list. A `use` made only of `key` reads is the stripped default and is normalized away on commit, in the same rule everywhere stripped defaults live (and the editor's twin of that rule).
- `published` and `modified` are verbs offered only for a post source and are always spelled. An absent read option means no read, so the tag shows its fallback, as today (D4). `now` is valid at any position, including alone (D6).
- Mismatch handling (D22): a `key` in `use` with no `key` entry is an empty read and takes the fallback; surplus `key` entries are ignored; both raise a preview warning. Arity violations such as a bare `key` are tolerant at render plus warn (D5).
- The old six datetime keys are mapped onto the read list at the single option-parse site, so the old tags render through the merged resolve seam (D9, D15).

**Control (S13, D7).**
- The merged tag builds its own read control, reusing the slot repeater's concepts (an explicit seed on Add, an absent value displays as the default it spells, removal compacts, Add gates on availability) and not its machinery. Vocabulary arrives from the PHP option definition. The shared slot-read builder is unchanged.
- The "Separate time field?" toggle is retired (S17). The all-day flag stays a separate top-level option and is not part of this spec (S18).

**Migration (S14, D15, D26).**
- Converter side: tag rename plus option rewrite, mechanical and deterministic. Mount side: the six-to-one option rewrite runs when the control mounts inside this work, so an old hidden tag carries the new options under its old name and renders correctly. The rename itself is converter-only, because GenerateBlocks owns the tag name.
- Rewrites: `key`/`timeKey` become one entry; the four range keys become two `;` entries, each a lone field or a `+` pair; trailing empties are stripped.
- A range with no start (both start fields empty) is not migrated by either path. It keeps its old name, keeps rendering, and the migration report lists it as needing a hand fix. A migration must not change output.
- The existing ownership guard, scan-report channels, and pattern-cache reconcile apply to the new rewrites unchanged.
- The rename is carried by four `type:'tag'` entries, one per old tag, each stamped `lifecycle: 'deprecated'` (D29): a replacement exists and the tag still works as before, which is `deprecated`'s definition, and the default `retired` would file them in the wrong settings box. The registered tag is hidden in the same change, because the lifecycle harness forbids `deprecated` together with a listed registration.

**Scope pulled in (D13).** The datetime preview duplicate (the branch is rewritten anyway), the shared field-filter question for the read control's pickers, the two empty-value fixes at the tails of the resolve seams, and the stale line in the FW-27 `if-option` plan. Sequencing: this lands before FW-3 (D14). FW-27's `when` read already spells reads as `use(...)`, so its one-field reads keep working and this widens them additively.

**Text family and slots (D24, D25).** No datetime reads or `+` composition are added to `{{text}}` or its family. A second `+` operand on a text tag is ignored with a preview warning. Two reads on `{{text}}` render the first only, with a warning. The `datetime` slot type token is not added; the existing `datetime`-to-`key` reroute in the slot parser is deleted.

**Preview tool (D27).** The tag-string preview tool is updated to the new syntax as part of the build.

**Docs.** README gets the merged tag with an `**[UNRELEASED]**` marker, the reference doc gets the current architecture, the editor-preview doc gets the new preview cases, the CHANGELOG gets entries under Unreleased, and the old tag names go to the deprecated-names doc when their rename completes. Per CLAUDE.md §Update triggers: the Datetime, Fold, Stripped-`use`, Serialization-order, and Option-control-order rows all apply.

## Testing Decisions

A good test here drives a wire string in and checks what comes out (rendered value, rewritten wire, or preview text), never which internal helper ran. Three seams, each already covered by existing harnesses (CLAUDE.md §Development, `docs/testing.md`):

1. **Render seam** (wire to rendered value, through the merged resolve seam that `try_datetime` also uses): extend the pure datetime format harness, plus the datetime matrix rows against the testbed. Covers entry count as mode, `+` operands, date inheritance, verbs and their position, the mismatch behavior, `use:key;key` normalizing out, empty reads, and the fallback.
2. **Migration seam** (old six-key wire to new wire): extend the datetime migration harness and the fold-migration harness. Covers every old key combination, trailing empties, the end-only skip and its report row, ownership-guard interplay, and mount-versus-converter agreement.
3. **Preview seam** (wire to editor preview text): extend the preview-label harness for the new cases and warnings (extra operand, count mismatch, bare `key`, surplus read on a text tag).

Also run, per the trigger rows: the stripped-`use` harnesses, the editor filter-chain harness, the control-order and registration-pass harnesses, the page-snapshot instrument against the testbed (new fixture output moves pages by design; re-capture the baseline in the same commit), and new matrix rows with their visible blocks in the testbed fixture (the visible-row mandate, `docs/testbed.md`).

Two measurements are tasks, not assumptions: (a) the `visibility => false` check (D10): a hidden tag leaves the picker, existing content still renders, and opening or saving an old tag in the modal neither goes blank nor corrupts it; record the result as a new GenerateBlocks fact. (b) Render an end-only range on the testbed to confirm it prints the fallback (read from the range core, not yet rendered), which D26 rests on.

## Out of Scope

- Datetime reads, verbs, or `+` composition on `{{text}}`, `{{join}}`, `{{table}}`, or any other family; left with FW-24 (D24).
- Per-operand modifiers (prefix, separator, format on one operand). Bracket depth does not block them later, but nothing in datetime needs one.
- A third position (seconds, timezone) and the all-day flag (FW-35, S11, S18).
- Ordinal position syntax, a folded `use(...)` form, or any `start`/`end` naming (rejected, D19 to D22).
- Numeric or text-joining tags built on `+` or on `;` read lists.
- Unregistering the old tags (needs widget reach via FW-73 and the retiring-name report via FW-128, D9), and the deprecation-mode radio (FW-104).
- General mount-time coverage for every option migration (a separate tracker row, D15).
- Adding `datetime` to the slot type tokens (waits for FW-24).

## Further Notes

- First tasks, in order: the `visibility => false` verification (D10), then the end-only range render (D26).
- Cross-cutting: ADR 0004 (the wire stays human-readable and hand-editable) binds the final grammar; ADR 0006 governs allocating `+`.
- The plan file stays as the decisions record this spec cites. It retires when the work ships (CLAUDE.md §Spec lifecycle).
- The plan's §Evidence and §Absorbed sections still carry the old comma spellings in places; the §SETTLED index and §Decisions 2026-11-17 are authoritative for the grammar.
- FW-24's tracker row and detail home are not yet annotated that they now carry the text-family datetime reads (D24). Do that when FW-24 is next touched.
