# Archive: collapse `datetime_single` + `datetime_range` into one tag — FW-81 decisions (MERGED, UNRELEASED)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle and `docs/agents/issue-tracker.md` §When the work merges own that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md` (the `datetime` section), `docs/update-triggers.md#datetime-change`, `docs/editor-tag-previews.md`, or the PHPDoc at the enforcing site (`bws_normalize_datetime_options()` and `bws_datetime_legacy_reads_rewrite()` in `includes/tags/datetime-tags.php`).

**Provenance.** The decisions of record for FW-81: a user concept check on 2026-08-18, two grilling sessions (2026-10-12 and 2026-11-17), then the build described in the companion spec `docs/design-history/datetime-tag-collapse-spec.md`, built across sixteen tickets on the `feature/datetime-collapse` branch, 2026-11-17 to 2026-11-19, and merged to `main` as [PR #167](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/pull/167) on 2026-11-19 (merge commit `0e9f8e6`), not yet in a tagged release. Tickets and commits: 01 measure the two unknowns (`119f67b`), 02 `{{datetime}}` renders single and range (`5a35522`), 02b merge the resolve seams (`6dfeb62`), 02c unify the time display paths (filed as #164), 03 verbs and `use` ordering (`a534520`), 04 `try_datetime` (`13057ca`, `0625f17`), 05 editor preview text and warnings (`49fa462`), 06 text-family guards and the slot reroute (`d521f74`), 07 hide the old four tags (`b8273fa`, `006b921`), 08 read control for the `key` entries (`268b032`), 09 verbs in the read control (`1c5c944`), 10 mount migration (`6f93bbc`), 10b mount migration for the `try_` forms (`976aa90`), 11 converter migration (`b46ad6c`), 12 tag-string preview tool (`a606c31`), 13 docs, tracker and full sweep (`674dfd5`). The ticket files stayed private and were archived, not committed; each ticket's measurements are in its commit body or in `docs/gb-constraints.md`. The release delta is the CHANGELOG `[Unreleased]` section. Every row of §OPEN was resolved before the build; work left out of scope has its own `docs/future-work.md` row (FW-24, FW-35, FW-73, FW-104, FW-128), so nothing was extracted into a new live plan. It lived at `.scratch/plans/datetime-tag-collapse.md` and is committed whole and unchanged apart from the title line.

---

**Tracker row:** `docs/future-work.md` FW-81 (§Future possibilities).

**Four rows RETIRED into this one, 2026-08-19 (user: "all four retire or fold"):** **FW-40** (`use` enum), **FW-41** (key pair-combine), **FW-65** (inner start/end box), **FW-68** (the five datetime keys under the slot fold). All four now sit in the Closed/Retired ledger with their outcomes; what each carried forward is in §Absorbed. Still-live related rows: FW-60 (attempts on base tags), FW-13 / FW-14 / FW-20 (field discovery, FU-3 stacking, combined controls), FW-24, FW-64.

**Provenance.** User concept-check 2026-08-18, opened as: *"having two datetime tags has never felt right… no options survive switching from one tag to another, so you can't start with single and switch to range or vice versa without configuring the fields and format from scratch."* Grilled across one session; the encoding converged only after the user proposed the chain-shaped positional form and the render layer was actually read.

**Status: UN-PARKED 2026-10-12, heading for build-ready (D1).** Second grilling session 2026-10-12 re-read the code (evidence refresh in §Evidence refresh 2026-10-12), settled O1–O7 and a set of new questions (§Decisions 2026-10-12), and left the questions in §OPEN for the next session. Nothing committed, no ticket, no spec yet: the spec (`.scratch/datetime-tag-collapse/spec.md`) is written once §OPEN is empty.

---

## §SETTLED

Rows are pointers. The sections below stay authoritative.

| # | Decision | Section |
|---|---|---|
| S1 | The two tags collapse to ONE tag; mode is not stated | §Driver, §Encoding |
| S2 | Driver is a GB constraint, not elegance — a tag-type switch discards all options | §Driver |
| S3 | No GB-shape blocker: both register `type:'cross-source'`, `supports:[]`, link-wrap, same template shape | §Evidence |
| S4 | Position is ORDINAL, never nominal — no `start`/`end` in the vocabulary | §Encoding |
| S5 | One syntax at every cardinality; there is nothing to autoswitch — **REWORDING PENDING (Q17+18): becomes "one spelling per shape, across all families"** | §Encoding, §OPEN Q17+18 |
| S6 | ~~Encoding is chain-shaped positional: `;` between reads, `,` inside, leading `use` slug~~ — **SUPERSEDED 2026-11-17 by D19–D22: reads ride sibling options, `;` between entries, `+` composes operands, `use` only orders verbs** | §Encoding, §Decisions 2026-11-17 |
| S7 | Positions are (primary field, optional supplementary time field) — NOT (date, time) | §Why the positions are not date/time |
| S8 | The primary field is POLYMORPHIC — date, datetime, or time | §Evidence |
| S9 | Date inheritance for the uneven case is ALREADY SHIPPED and triggers off field SHAPE | §Evidence |
| S10 | Verbs ride the `use` slug (`key`, `now`, `modified`, `published`) | §Encoding |
| S11 | Third position is CLOSED — it would reintroduce holes | §Third position |
| S12 | Future optional axes go NAMED after the positional args, per the chain precedent | §Third position |
| S13 | Reuse the slot repeater's CONTROL CONCEPTS, not the slot MACHINERY | §Control, §Rejected R1 |
| S14 | Six options collapse to one; migration is converter-side and mechanical | §Migration |
| S15 | ~~`published` is NOT a token~~ — **WITHDRAWN 2026-08-19 (user: premature). Became O7, settled 2026-10-12 as D4.** | §Decisions 2026-10-12 D4 |
| S16 | FW-68's premise was FALSE — `try_datetime_*` has NO per-slot read axis, all six key axes are flat tag-level | §Absorbed (FW-68) |
| S17 | The "Separate time field?" toggle is OBSOLETE, not merely superseded | §Rejected R7 |
| S18 | FW-35's all-day flag is NOT a position in this fold — it is a separate top-level option | §The all-day flag is not a third position |
| D1 | Goal is build-ready: un-park, write the spec once §OPEN is empty | §Decisions 2026-10-12 |
| D2 | Merged tag is `{{datetime}}`; takes `datetime_single`'s registration position (after image, before join) — settles O5 | §Decisions 2026-10-12 |
| D3 | `use` stays the option name — settles O1. **Meaning narrowed 2026-11-17 (D20): it orders reads when a verb is present; it no longer carries a folded read list** | §Decisions 2026-10-12, §Decisions 2026-11-17 |
| D4 | `published` / `modified` are verbs, always spelled; absent read option = no read = fallback, as today — settles O7 | §Decisions 2026-10-12 |
| D5 | Arity violations: tolerant at render, editor-preview warning — settles O3 | §Decisions 2026-10-12 |
| D6 | `now` is valid at any position | §Decisions 2026-10-12 |
| D7 | Datetime builds its own read control; `bws_build_slot_read_options` unchanged — settles O2 | §Decisions 2026-10-12 |
| D8 | O4 was already settled by FW-40's retirement — the read list IS the enum | §Decisions 2026-10-12 |
| D9 | Old tags: hidden via GB `visibility => false`, keep rendering; unregistering only considered after widget reach (FW-73) + retiring-name report (FW-128) — settles O6 | §Decisions 2026-10-12 |
| D10 | Testbed-verify `visibility => false` as the spec's FIRST task. **CLOSED 2026-11-17:** measured 2026-10-23 on GB 2.5.0 (the hidden tag leaves the picker and still renders; the modal's select box is blank but saving leaves the tag unchanged); the "Deprecated" `type` fallback is not needed | §Decisions 2026-10-12 |
| D11 | `try_datetime_single` + `try_datetime_range` merge into `try_datetime` | §Decisions 2026-10-12 |
| D12 | ONE shared read form across FW-81 / FW-27 / FW-35 / FW-20; slot parser gains the read-list branch — **SUPERSEDED 2026-11-17 by D19** (folded form dropped; the parser consequences are re-derived at spec time) | §Decisions 2026-10-12, §Decisions 2026-11-17 |
| D13 | FW-81 scope pulls in: FW-110 datetime preview duplicate, FU-3, FW-116 tail carry-through, FW-27 stale Q1 line | §Decisions 2026-10-12 |
| D14 | FW-81 lands before FW-3 | §Decisions 2026-10-12 |
| D15 | Mount migration: six→one runs at mount inside FW-81; general mount coverage is a new tracker row | §Decisions 2026-10-12 |
| D16 | ~~BOTH read forms parse on every single-key tag: folded `use:key,x` is READ everywhere, never emitted outside datetime~~ — **MOOT 2026-11-17 (D19): there is no folded form to parse. The hand-rename workflow it protected is covered by `key:x` being the only spelling on every tag** | §Decisions 2026-10-12, §Decisions 2026-11-17 |
| D17 | ~~The folded form covers `key` only; `fixed` keeps its own token~~ — **MOOT 2026-11-17 (D19); the `fixed` half stands on its own: `fixed` keeps its token** | §Decisions 2026-10-12, §Decisions 2026-11-17 |
| D18 | Cross-family parsing (D16, D17, and whatever Q17+18 / Q20–Q21 settle) is FW-81 scope | §Decisions 2026-10-12 |
| D19 | No folded `use()` form. A read's slug names the option that carries its args (`key:` for `key` reads); the nth `key` read takes the nth `;` entry of `key:` | §Decisions 2026-11-17 |
| D20 | `use` appears only when a verb (`now`, `published`, `modified`) is mixed in, and then it is the full ORDERED list, `key` included; an all-`key` `use` is the stripped default and normalizes away on commit | §Decisions 2026-11-17 |
| D21 | `+` composes operands inside one `key` entry (date from A, time from B); at most two operands on datetime, extras ignored + preview warning. Spends reserved `+` (ADR 0006 read first) | §Decisions 2026-11-17 |
| D22 | A count mismatch between `use` and `key:` is guarded (tolerant render + preview warning), not unspellable; ordinals (`use:2(now)`, `1(key,…)`) rejected | §Decisions 2026-11-17 |
| D23 | Q17+18, Q20, Q21 closed as moot: the folded form never shipped, so no stored wire exists to parse or rewrite | §Decisions 2026-11-17 |
| D24 | Datetime reads on the text family (Q23: verbs AND the field combine) are NOT in FW-81; left with FW-24. Q24 moot | §Decisions 2026-11-17 |
| D25 | Q25 settled (first read only + warning, `key:a;b`); Q26 settled (b) + delete the `key`-reroute | §Decisions 2026-11-17 |
| D26 | An old range with no start migrates to NOTHING: it stays registered and rendering, and the migration report lists it. Measured 2026-11-17: an end-only range prints the fallback in every variant tried | §Decisions 2026-11-17 |
| D27 | The build updates `tools/preview/tag-string-preview.html` to the D19–D22 syntax (`key:a+b;c`, `use` for verbs) | §Decisions 2026-11-17 |
| D28 | Dropping the registered options of hidden or deprecated tags is NOT in FW-81: it belongs to FW-38 (`lifecycle`). FW-81 keeps the old four tags' options registered, because the mount migration (D15) converts them when the modal opens | §Decisions 2026-11-17 |
| D29 | The four old tags carry `type:'tag'` migration entries with `lifecycle: 'deprecated'` (a replacement exists and the tag still works), hidden in the same change; first population of the lifecycle harness's fixture-only assertions | §Decisions 2026-11-17 |

## §OPEN

**Every row below was resolved 2026-11-17 by D23–D26** (the first cell of each row says how). The text-family meaning of `+` (concatenate? sum on a numeric tag?) is explored, not decided, and not FW-81 scope: D21 binds datetime only.

O1–O7 all settled 2026-10-12 (O1→D3, O2→D7, O3→D5, O4→D8, O5→D2, O6→D9, O7→D4). Held for the next session, each with the recommendation it was asked with:

| # | Question | Recommendation as asked |
|---|---|---|
| Q17+18 — CLOSED, MOOT (D23) | **Precedence + emission (needs confirming).** `key` beats a folded `use:key,…` list on EVERY tag, flat and in slots — this REVERSES the "list beats a stray `key(x)`" sub-rule asked under D12. `{{datetime}}` emits `key:x` exactly when the read list is one `key` read with one field (the shape every other family writes as `key:x`); anything richer emits the `use:` list. | Confirm. Rationale (user's correction): once `key:x` is canonical for a single field, the folded form is the alternate, and the explicit token wins — FW-142's token-implies-read rule. No stale-`key` trap: our control can `delete` `key` (non-reserved on `supports:[]` tags), so the Add path rewrites `key:x` → list cleanly; a conflict arises only from hand edits. Mirrors the slot emitter's existing rule (field chosen → bare `key(x)`, `use` redundant). S5 rewords to "one spelling per shape, across all families"; existing `datetime_single key:x` wire then needs only the tag rename. |
| Q20 — CLOSED, MOOT (D23) | Datetime-shaped input on a single-value tag: `{{text use:key,date,time}}` (extra args) | **Extra args: ignored + preview warning — SETTLED by user.** The not-offered-verb half was reopened by the user and became Q23. |
| Q21 — CLOSED, MOOT (D23) | Editor canonicalizes on open: a single-key tag holding `use:key,x` is rewritten to `key:x` by the mount normalize; anything Q20 ignores drops on commit | Yes — in D15's mount scope, `key` non-reserved on our tags; the preview already warned |
| Q23 — OUT OF FW-81, left with FW-24 (D24) | **Datetime reads on `{{text}}` and family (user's proposal).** Add `published` / `modified` / `now` to the text read list and render `key,date,time` as a combined value, in the text read seam (`bws_base_text_resolve_value`) — `{{join}}` slots and `try_text` already absorb that seam, so they get datetime values with NO tag-in-slot mechanics; `{{table}}` picks it up when built | Yes, in the seam. Triggers the `use:` qualifying gate (`now` source-independent; `published`/`modified` post-only, fall through to empty on term/site as under D4). FW-24 interaction note: this is the cheap partial answer to "a datetime slot cannot carry `format`" |
| Q24 — MOOT with Q23 (D24) | Format on `{{text}}` with no format option. A `key` read keeps the field's own format (today's behavior — ACF hands back its return-format value). For a verb or a combined date+time read: (a) WP `date_format` + `time_format` when a time is present, space-joined; (b) ISO 8601; (c) `date_format` only | (a); a combined read whose fields carry their own formats uses each field's own, space-joined |
| Q25 — SETTLED (D25): first read only + preview warning, now spelled `key:a;b` | Two reads on `{{text}}` (a range): first read only + preview warning, or render both with a default separator? | First read only — ranges are `{{datetime}}`'s job, and `{{join}}` already composes two text slots with its `format` |
| Q26 — SETTLED (D25): (b), plus the reroute deletion; the "parser branch needed regardless" clause is void with the folded form | **Does D11-era "add a `datetime` slot type token" still earn its place?** See §Slot type tokens. (a) keep: add `datetime` to `BWS_FOLD_TYPES`, old tokens stay accepted; (b) drop from FW-81: old tokens stay accepted (inert as today), the merged token waits for FW-24 | (b) — nothing renders by slot type. EITHER WAY: delete the `datetime…` `key`-reroute (`slot-fold.php:714`), which contradicts Q17+18 and would make a hand-written `datetime_single;key(x)` slot silently read nothing. The D12 parser branch is needed regardless (for `use(key,date,time)` in text slots) |

---

## Decisions 2026-10-12

Second grilling session. Each D-row in §SETTLED points here.

- **D1 — Goal.** Build-ready, not just decided: the spec goes to `.scratch/datetime-tag-collapse/spec.md` once §OPEN is empty, and the sequencing below (D14, the must-follow list) is part of it.
- **D2 — Name + position (O5).** `{{datetime}}`: cardinality-neutral (S1/S5); `{{date}}` undersells time-only primaries (S8); keeping `datetime_single` would leave "single" naming something no longer single. Position: `datetime_single`'s existing slot in `bws_register_base_tags` (after image, before join) — no reorder.
- **D3 — Option name (O1).** The folded read list stays under `use`. The user modelled a rename to `read` in `tools/preview/tag-string-preview.html` and liked it less; FW-27 already spells FW-81's read as `use(…)`, so a neutral name would also have respelled FW-27. Open to a better suggestion, none on the table.
- **D4 — `published` / `modified` / absent read (O7).** Both are verbs, always spelled in any list, offered only when the source is a post. An ABSENT read option means NO read → the tag's fallback, exactly as today. This rests on the 2026-10-12 evidence that the FW-40 corollary was false (§Absorbed FW-40 correction): there is no published-date default to preserve, and an absent-means-published rule would make every keyless datetime tag start printing a date it never printed, with no converter lever (it cannot write "nothing" differently from nothing).
- **D5 — Arity (O3).** `now,x` / bare `key`: tolerant at render (extra args dropped; bare `key` = empty read → fallback as a missing key today) PLUS an editor-preview warning through `bws_build_preview_label`'s warning channel. The control cannot author either shape; this is hand-edit territory (ADR 0004).
- **D6 — `now` anywhere.** Including `use:now` alone and `use:now;key,end`. Verbs are uniform and positions ordinal (S4); a position rule would be the first nominal one. Page-cache freezing of `now` output is the author's concern, as with any time-relative content — no guard.
- **D7 — Read surface (O2).** Datetime builds its own read-list control. `bws_build_slot_read_options` has ONE production caller (the fold builder, `base-shared.php:788`) and no arg concept; widening it for a family with no slot read axis is speculative. Revisit only if FW-60 needs it.
- **D8 — O4.** Already settled when FW-40 retired into FW-81 2026-08-19 ("the collapsed tag's read list IS this enum").
- **D9 — Old tags (O6).** `datetime_single`, `datetime_range` and their `try_` pair stay REGISTERED and RENDERING (through the merged seam — `bws_normalize_datetime_options` maps the six old keys to the read list), HIDDEN from the picker with GB's `visibility => false` arg. The converter rewrites post content. Unregistering is only considered after BOTH: the converter extended to `widget_block` (FW-73, which rewrites them) AND a retiring-name report over postmeta/options/termmeta showing nothing left (FW-128, which reports what no migrator reaches). Widget reach alone is not enough, since custom fields stay unreachable (gate restated 2026-10-12; the earlier wording named FW-73 only). The user did not know of a picker-hiding mechanism; §Evidence refresh 2026-10-12 has the GB facts.
- **D10 — Verify first.** The spec's FIRST task is a testbed check of `visibility => false`: (1) a hidden tag drops from the picker; (2) existing old-tag content still renders; (3) opening an existing old tag in the modal — does the tag-select go blank, and does saving corrupt or drop the tag? Record the result in `docs/gb-constraints.md` (new GB fact). Fallback if (3) fails: a "Deprecated" `type` group (the picker groups by `type`) — free, but visible. **CLOSED 2026-11-17.** Already measured 2026-10-23 on GB 2.5.0 and recorded in `docs/gb-constraints.md` §`visibility: false` hides a tag from the picker only: (1) the hidden tag leaves the picker; (2) existing content renders on the front end and in the preview; (3) the select box is blank, but the modal opens the tag with every option filled and "Insert dynamic tag" saves it back unchanged. The fallback is not needed. The 2.5.1 text-block filter and PHP non-read were confirmed unchanged by reading the shipped files (2026-11-17); the modal behavior itself was not re-driven on 2.5.1.
- **D11 — `try_`.** `try_datetime_single` + `try_datetime_range` merge into `try_datetime` (same tag-switch constraint as the base pair). The slot-type-token half that was asked alongside is reopened as Q26.
- **D12 — One shared read form.** A read is `verb,args`; several reads are `;`-joined; at tag level the list is the option's value (`use:…`, FW-35's `allDay:…`); nested in a slot or `when` it sits inside `use(…)`; the shipped `key(x)` / `key:x` stays the one-field shorthand (FW-141/142: a token naming its read implies it). Parser cost, as presented and accepted: tag level is free (`src:` already ships `;`/`,` in a GB option value); in a slot the splitter is already balance-aware and no new chars enter an accept class (ADR 0006 satisfied); `bws_fold_parse_slot` (`slot-fold.php:721`) gains a branch — a `use` value containing `,` or `;` is a read list split with the chain's own separators (today it would parse as one garbage analog slug and render empty), bare `use(key)` keeps meaning "keyed, field not chosen yet", the comma disambiguates; the emitter gains the matching arm; the JS twin `slot-fold-grammar.js` gets the same branch (pinned by `slot-fold-twin-test.php`); the "Read axis is single-valued" invariant (`slot-fold.php:40`) is rewritten. Harness rows: `slot-fold-test`, `slot-fold-twin-test`, `fold-migration-test`. The precedence sub-rule asked here ("a list beats a stray `key(x)`") is SUPERSEDED pending Q17+18.
- **D13 — Scope pull-ins.** (a) FW-110's datetime preview duplicate (the branch is rewritten anyway); (b) FU-3 (FW-14) — the read-list control owns all its pickers, so one shared filter is a design choice, not plumbing; (c) FW-116's two empty-value fixes at the tails of both resolve seams carry into the merged seam (required; page-snapshot rows C-DT1/C-DT2 guard them); (d) FW-27 `if-option.md` Q1's stale step-shaped `key,event_date,before` line cleaned up (doc-only).
- **D14 — Order vs FW-3.** FW-81 first; FW-3 re-reads after. FW-3 is blocked on its own `return_format` decision, and its plan already says to check FW-81's state before touching key handling.
- **D15 — Mount migration.** User: expand mount coverage as far as possible; only option names GB captures are off-limits. None of the six datetime keys is GB-captured on our tags (`key` is reserved only on a tag declaring `meta` support — `docs/gb-constraints.md` §Reserved Option Keys — and every BWS tag is `supports => []`). So the six→one rewrite runs AT MOUNT inside FW-81: an old hidden tag then carries a `use` list under its old name, which renders correctly (shared seam, range derived from read count); the converter later does only the rename. The rename itself stays converter-only (GB owns the tag name; mount rewrites options). GENERAL mount coverage — every `MigrationRegistry` option migration at mount, measured first — is a NEW tracker row, and is where the `docs/plugin-integration.md:676` question ("Option migrations also run when a tag modal opens" — possibly overstated; only fold, base-`src` and serialization order were found on the mount path, unmeasured) gets settled. That doc/code question is surfaced, not resolved.
- **D16 — Both forms parse everywhere (user).** The folded `use:key,x` is READ on every single-key tag (`{{text}}` / `content` / `image` / `email` / `phone`, slots included), never emitted outside datetime — so an author who copies `{{datetime}}`'s syntax onto another tag, or hand-replaces the tag name to keep source/field config (a workflow the user uses), keeps a working read. `bws_use_effective()` gains the comma branch; its editor twin `use-read-control.js` the same rule (the stripped-`use` trigger row's harnesses apply). The reverse direction (`key:x` on `{{datetime}}`) is covered by Q17+18.
- **D17 — `key` only.** `use:fixed,Varsity` is NOT read as `fixed:Varsity`: fixed text is free-form, so a comma inside it would split as an arg separator (the hazard FW-61's escape rule exists for). `fixed` keeps its token as the only spelling.
- **D18 — Scope.** The cross-family parsing is FW-81 scope: it is the shared read form's other half, and what makes hand-renaming `{{datetime}}` safe the day FW-81 ships.

## Decisions 2026-11-17

Syntax session. Supersedes the folded read list (S6, D12, D16, D17); D3's name stands, its meaning narrows.

- **D19 — No folded `use()` form.** The user was never satisfied with `use(…)` carrying args, and no other tag writes the two together: `use` picks the read, a sibling option carries its arguments. A read's slug names that option (`key:`), and the nth `key` read takes the nth `;`-separated entry. Verbs take no entry, so no holes appear in the `key:` list. A future verb that takes an arg gets an option named for it by the same rule. In a slot the same two options are sibling tokens (`use(key;now);key(a+b)`); that the `;` and `+` at depth 1 stay clear of the slot's own separators is a spec-time check against `bws_fold_grammar_validate()`.
- **D20 — `use` only for verbs, then fully ordered.** With no verb, `use` is absent: `key:` alone means every entry is a `key` read, and the read count is the entry count, so it cannot mismatch. With a verb present `use` is the full ordered list, `key` included, because `key` then carries POSITION: `use:key;now` and `use:now;key` are different ranges, and "start now, end at a field" must stay spellable. `use` made only of `key` reads (`use:key;key`, `use:key`) is the strip-default and normalizes away on commit. Mount normalization lands in `bws_use_stripped_default()` / `bws_use_effective()` and the editor twin `use-read-control.js`; this resolves the §Absorbed O7 worry, since a verb's position needs no hole (`use:;now` never appears).
- **D21 — `+` composes operands within one entry.** `key:event_date+start_time;end_time` is two reads, the first composed of two fields. Replaces the comma field list of the folded form, so a step keeps exactly one arg and `,` stays the slug/arg/limit separator. Datetime accepts at most two operands (S11 holds: no third position); extras are ignored with a preview warning, the D5 shape. `+` is in `BWS_FOLD_RESERVED` (held unspent so no saved wire changes meaning); ADR 0006 is read BEFORE it is allocated, and `bws_fold_grammar_validate()` cannot check that allocation for the spec. Per-operand modifiers are NOT part of FW-81 (nothing in datetime needs one) but are NOT blocked by depth: bracket alternation is unbounded (`bws_fold_bracket_pair()` takes the level modulo the pair count, so level 3 is `()` again) and measured 2026-11-17 to split correctly, e.g. a slot's `key(a[pre($)]+b[sep(.)];c)` yields operands `a[pre($)]` and `b[sep(.)]` and a second entry `c`. A modifier would ride a bracket directly after its operand, never a bare `,` (which already splits a step's parts). Standing hazard, pre-existing and not specific to this: `bws_fold_split_depth()` fixes the pair on the first open char and counts only that pair, so a lone unbalanced `(` in free text inside a level-2 `[...]` breaks the enclosing `()` (measured: `key(a[note (x]+b)` returns `unbalanced open bracket`). Free-form modifier values (a `pre`/`sep` string) would meet it; plain field names do not.
- **D22 — Mismatch is guarded, not unspellable; ordinals rejected.** The old fold made the count single-sourced. Now `use` (when present) and `key:` can disagree (`use:key;now` with no `key:`, or two `key` entries): missing entry = empty read, which takes the fallback; extra entries ignored; both raise the preview warning (D5's channel). S4's "ordinal, never nominal" holds, since position is order in `use`. Rejected: `use:2(now)|key:1(…)` (reintroduces start/end by number, makes holes and duplicate ordinals spellable) and `use:1(key,…);2(now)` (the folded form again).
- **D23 — Folded-form questions closed.** Q17+18 (precedence and emission), Q20 (extra args on a single-value tag) and Q21 (canonicalize on open) all concerned parsing a folded `use:key,x`. That form never shipped on any tag (§Evidence refresh: "`use` with args: none shipped"), so there is no stored wire to read or rewrite. S5 stands as "one spelling per shape": `key:x` on every tag.
- **D24 — Text-family datetime reads stay out (user: "no verbs into the text family as of now").** Q23 is out of FW-81 in BOTH halves, the verbs (`published` / `modified` / `now`) and the two-field combine; a second `+` operand on a text tag is ignored with a preview warning, the D5 shape. It is left with FW-24 (user's call, over a new tracker row), where the "a datetime slot cannot carry `format`" interaction already lives. Q24's format question has no subject without Q23. This file is not the FW-24 detail home, so FW-24's own row/home is NOT yet annotated; do that when FW-24 is next touched.
- **D25 — Q25 and Q26.** Two reads on `{{text}}` (`key:a;b`): the first only, plus a preview warning; ranges are `{{datetime}}`'s job and `{{join}}` composes two text slots with its `format`. Q26 (b): the `datetime` type token is NOT added to `BWS_FOLD_TYPES` in FW-81, and the `datetime…` `key`-reroute at `slot-fold.php:714` is deleted regardless.
- **D26 — Range with no start migrates to nothing.** Read from `bws_datetime_range_core()` (measured 2026-11-17 on the testbed, ticket datetime-tag-collapse/01: `endKey`, `endTimeKey`, both together and an `as:time` variant all print the fallback, empty when none is set): when the start field and start-time field are both empty the core returns the FALLBACK before the end is looked at (`datetime-tags.php:505-507`), and the normalizer does not promote an end to a start. So an end-only range renders its fallback today, and `key:<end>` would CHANGE that output. User: an author would not intend a tag that does nothing either way, and skipping is acceptable. So the converter and the mount migration both skip such a tag, it keeps its old name and keeps rendering (D9), and the migration report lists it as needing a hand fix. A migration must not change output.
- **D27 — The tag-string preview is part of the build (user).** `tools/preview/tag-string-preview.html` is updated to the D19–D22 syntax as a task of FW-81, not left to drift: it currently models the folded read list (D3 records the `read`-rename trial run in it). It is the instrument the grammar is eyeballed in, so it ships with the grammar it demonstrates. FW-79 (closed 2026-09-01) is the precedent for keeping it on the shipped wire.
- **D28 — Dropping controls from deprecated tags is FW-38's (user, 2026-11-17).** The idea (parked 2026-10-24, never written down): a hidden old tag registers no options, since the 2026-10-23 measurement shows the modal still opens it with its tag string editable and unregistered options in the stored string surviving a save. It would apply to deprecated tags in general, not only the datetime four, and it sits with `lifecycle` and the `stub` registration form in FW-38's open part (FW-104's mode radio is a neighbor, not the owner). So FW-81 does not decide it. Consequence for this build: the old four tags keep their registered options, because the mount migration (D15, ticket 10) rewrites the six datetime options when the modal opens, and an option-less tag would show no controls to rewrite. If FW-38 later drops the controls, ticket 10's mount half for the old tag names becomes moot and only the converter half remains.
- **D29 — The old four tags are `deprecated`, not `retired` (user, 2026-11-17).** FW-38 names FW-81 as the first consumer of `lifecycle: 'deprecated'`, but the spec and tickets never said so; this makes it so. `MigrationRegistry::register()` owns the meaning: `deprecated` is "a replacement exists and the tag still works as before, so the converter offers the migration"; `retired` is the unsupported default. That is these tags exactly. Each of `datetime_single`, `datetime_range`, `try_datetime_single` and `try_datetime_range` gets a `type:'tag'` entry (old name to new) with `lifecycle` set explicitly, because absent means `retired`. The harness forbids `deprecated` on a LISTED tag, so the entries and the `hidden` registration (D9) land together (tickets 07 and 11). Open, not decided: whether the registrations move into `bws_register_deprecated_tags()` or stay in `base-tags.php` hidden. This does not open a deprecation window in FW-104's sense (the radio stays unwired).
- **Explored, not adopted.** (1) A text-joining list `key:a;b;c|joinSep:, ` fits the grammar (a read list) but overlaps `{{join}}` and would reverse Q25; (2) a numeric tag where `+` means sum is coherent only as "compose operands, the family defines how", with the reserved `/` hinting at division. Neither is FW-81 scope; revisit when a second family wants operand composition.

## Evidence refresh 2026-10-12

Re-read against the tree at `21951d3`. Line numbers in §Evidence (2026-08-18) have moved; the FACTS there still hold except where noted.

- **Registration** — `datetime_single` `base-tags.php:277-284`, `datetime_range` `:290-297`; both `type:'cross-source'`, `supports:[]`. Option builders `datetime-tags.php:109-131` (single: src group, `key`/`timeKey`, `as`/`format`/`timeSep`/`showCurrentYear`/`showMidnight`, link, `fallback`) and `:270-291` (range: `startKey`/`startTimeKey`/`endKey`/`endTimeKey`, `as`/`rangeSep`/…). Neither has a `use` option. `try_` templates `base-tags.php:451-481`: no `try_per_slot_key`/`try_per_slot_use` (S16 still true).
- **Parse site** — `bws_normalize_datetime_options( array $options, bool $range = false )` at `datetime-tags.php:664`; the "ONLY place" invariant at `:642-649` already names FW-81. `$range` passed by callers: the single seam (`:786`, default), the range seam (`:1021`), `preview-helpers.php:1278,1474`, tests `datetime-format-test.php:103,108`, `datetime-migration-test.php:138`. `key` on range still dead wire (`:704-723`).
- **Seams (FW-136)** — `bws_base_datetime_single_resolve_value` `:784`, `bws_base_datetime_range_resolve_value` `:1019`; shells `:947`, `:1150`; cores `:421`, `:490`, term variants `:593`, `:615`.
- **No published-date default** — no `post_date` analog, no `modified`, no `use` row: `BWS_USE_STRIPPED_DEFAULTS` (`registration-helpers.php:306-314`) has no datetime entry; the single core returns `bws_handle_date_time_fallback` when both fields are empty (`datetime-tags.php:442-444`). `now` exists only as preview sample dates (`preview-helpers.php:1270,1466`). This FALSIFIES §Absorbed FW-40's corollary.
- **`$inherit_date`** still present — `bws_parse_combined_date_time` `datetime-helpers.php:303`, used only for `'end'` (`:410-412`, `:457-459`); the range core passes `$start_result['date']` (`datetime-tags.php:513`).
- **`use` with args: none shipped.** Slot `use(x)` is single-valued (`slot-fold.php:723-797`); `BWS_USE_IMPLIED_BY_TOKEN` = `key`, `fixed` (`registration-helpers.php:347-352`).
- **Slot type tokens hard-code the old names** — see §Slot type tokens.
- **Converter reach** — `TagConverter::scan()` (`class-tag-converter.php:60-92`) is one SQL query over `wp_posts.post_content`, all post types except revisions, statuses except auto-draft/trash: reaches pages/posts/CPTs, `wp_block`, DB-stored `wp_template`/`wp_template_part`, `wp_navigation`, `gp_elements`; plus the GB Pro pattern cache (`class-pattern-cache.php`). OUT: postmeta, termmeta, the options table (incl. `widget_block`), on-disk theme templates. Pinned by `tools/fixtures/core-structures/verify-migration.php` §5. Unverified: `customize_changeset` JSON is swept as a post. A `widget_block` extension is feasible — extract a pure `migrate_string()` from `migrate_post()`'s two loops (`:507-543`), add `migrate_widget_option()` via `update_option` (keep `_multiwidget`), teach the scan rows + `admin-tag-scanner.js` a non-post target, feed widget results into `rebuild_allowlist()` (or the settings page hides entries widgets still use), and add a backup (options have no revisions); the ownership guard needs no change; `bws_dynamic_tags_content_written` takes a `post_id`, so a widget write needs its own action or none.
- **Picker hiding (GB)** — GB 2.5.0-rc.2 `includes/general.php:278-308` localizes every registered tag (args minus `return`) as `generateBlocksEditor.dynamicTags`; no PHP filter, no unregister, no REST list. The picker (minified `dist/blocks/text/index.js`) filters on a `visibility` arg: `false` drops the tag; the object form gates on `context` keys / `attributes` (GB's own `term_list` uses `NOT_IN` on `tagName`, `class-dynamic-tags.php:282-296`). Same check in 2.4.1. PHP never reads `visibility`, so a hidden tag still renders. Was unverified (minified only; measured since, see D10): the modal looks an existing tag up in the UNFILTERED list but builds the tag-select options from the FILTERED one, so a hidden tag may show no matching option — D10 check (3). The picker does NOT hide by `type`; it groups by it. In this plugin every registration passes `bws_gb_register_tag` (`gb-registration-boundary.php:96`).
- **Deprecation modes are inert** — `MigrationRegistry` stamps `gb_type = 'deprecated'` on tag entries (`class-migration-registry.php:108-110`) but nothing passes it to GB; `is_deprecated_tag_suppressed()` / `is_deprecated_tag_registered()` have no callers, so keep/suppress/disable do nothing at runtime (FW-104's gap). Even as designed, `suppress` blanks output and hides nothing.
- **Mount path** — editor scripts enqueue on `enqueue_block_editor_assets` with no screen check (inferred to load in the widgets and site editors, not run-tested); the mount filters are `slot-fold-migrate.js:540-552` (fold + base-`src`) plus the serialization-order normalizer; it never renames a tag.

## Slot type tokens

Inside `{{join}}` (and the future `{{table}}`) a slot value may start with a bare type word naming which tag family the slot behaves as — `BWS_FOLD_TYPES` (`slot-fold.php:86`): `title`, `content`, `email`, `phone`, `permalink`, `image`, `datetime_single`, `datetime_range`. No word = a text slot. Today the word is parsed and kept on the wire but NOTHING at render reads it (no consumer of a slot's `type` outside the grammar file; matches `docs/design-history/fixed-read.md` D4) — every `{{join}}` slot renders as text. Its one effect is the parser's `key` reroute (`slot-fold.php:714`): on a `datetime…`-typed slot, `key(x)` is stored as an ordinary option instead of the read, and since nothing renders by type, that option goes nowhere. A merged `datetime` word earns a place only once a typed slot renders differently from a text slot (per-slot `format`/`rangeSep`) — FW-24's open problem. Q26 holds the choice.

## Must-follow / interactions found 2026-10-12

- **Must follow FW-81:** FW-113 (its site-2 seam join lands on the merged seam; expected to resolve for free), FW-134 (its target control is the one FW-81 collapses), FU-3 (pulled in, D13).
- **Bound by D3/D12:** FW-27 `when` (already spells FW-81's read as `use(…)`; v1 ships one-field reads, FW-81 widens additively), FW-35 `allDay:key,<field>` (one-read list under its own option).
- **Same core/seam area, independent:** FW-3 (D14), FW-116 (D13c).
- **FW-104:** the deprecation-mode radio. D9 keeps the old tags rendering, so FW-81 opens no deprecation window that would need it wired. Whether a hidden or deprecated tag should drop its registered options (a hidden tag needs none, measured 2026-10-23, `docs/gb-constraints.md`) is FW-38 territory, see D28.
- **FW-20:** its contingent "`use:key,field` parseable but never written" landing is answered by D16 + Q17+18.
- **`docs/plugin-integration.md:676` vs the mount path** — surfaced under D15, unresolved.
- **Stale docs to correct at spec time:** this plan's §Absorbed FW-40 corollary (corrected inline below) and `.scratch/plans/all-day-flag.md:62-63`, written on the same false assumption.

---

## Driver

Not elegance. `docs/gb-constraints.md` §Switching tag type: switching tag type in the modal discards all options. So a single→range change costs the author their format, source, link, fallback and both checkboxes. Two tags that cannot exchange configuration is that constraint leaking into the tag catalog.

Same constraint that forces FW-60 (absorb `try_` into base), and the same answer shape: the distinction must live INSIDE one tag's option set.

## Evidence (read 2026-08-18 — do not re-derive)

- **Identical GB registration shape** — `base-tags.php:319-337`. Both `type:'cross-source'`, `supports:[]`; both templates `supports_link_wrap:true` (`:550`, `:588`).
- **One parse point already** — `bws_normalize_datetime_options()` (`datetime-tags.php:691`). Its PHPDoc already promises a folded key value lands here and nowhere else. The `$range` bool becomes DERIVED from the read count instead of passed by the caller.
- **`key` on `datetime_range` is DEAD WIRE** — the normalizer explicitly declines to map it (`datetime-tags.php:731-733`). Nothing is displaced by reusing the field-read option.
- **Either field alone suffices** — the single core bails only on `empty($date_time_field) && empty($time_field)` (`datetime-tags.php:476`); the range core the same for start (`:538`).
- **The primary field is polymorphic** — `$date_is_time_only` is detected from the ACF `return_format`, with a raw-value shape fallback `bws_value_looks_time_only()` (`datetime-helpers.php:360-370`). Added deliberately for the #22 follow-up. ⚠ **Not new, and this session's first write-up wrongly implied it was.** It was already recorded 2026-07-09 in `combined-option-controls.md` §The `,,` interior-empty problem: *"a lone time-only field correctly goes in the primary slot and the parser sorts it out."* What that pass treated as a render-time FALLBACK behind a UI toggle, this design promotes to the mechanism the encoding rests on (see §Rejected R7). The genuinely new finding is the inheritance one below.
- **Date inheritance is SHIPPED** — `bws_parse_combined_date_time()` takes `$inherit_date`, and the range core already passes `$start_result['date']` as the end's (`datetime-tags.php:546`). The branch is `if ( $date_is_time_only && $date_obj ) { if ( $inherit_date && $context === 'end' ) }` (`datetime-helpers.php:378-395`). **It triggers off the PRIMARY field being time-shaped, not off a field being absent.**
- **Seconds already flow** — `setTime( H, i, s )` off the parsed object (`datetime-helpers.php:382-386`).

## Encoding

**REWRITTEN 2026-11-17 (D19–D22).** The folded encoding (`use:key,a,b;key,c`, a read list under one option) is withdrawn; §Decisions 2026-11-17 has why. Reads are written the way every other tag writes them: `use` names the read, a sibling option carries its args. `;` separates reads (the existing `BWS_FOLD_STEP_SEP` class), `+` composes operands inside one entry (reserved today, D21).

```
key:event_datetime                          single, one datetime field
key:event_date+start_time                   single, split across two fields
key:event_date+start_time;end_time          range — end time-only, date INHERITS
key:start_dt;end_dt                         range, two datetimes
use:key;now|key:event_date+start_time       ongoing (key start, end now)
use:now;key|key:end_date                    start now, end a field
use:published                               verb read, no args
```

Read count is the mode: the `key:` entry count when `use` is absent, the length of `use` when a verb is present. No `start` / `end` names exist, so `startKey`-without-`endKey` and `key`-plus-`endKey` are both UNSPELLABLE (S4). The one spellable inconsistency left is a `use`/`key:` count mismatch in the verb-mixed case, which is guarded instead (D22).

**No folded value.** At tag level `use:` and `key:` are plain GB option values. Inside a slot (FW-60) they are two sibling tokens, `use(key;now);key(a+b)`, each bracketed like any slot token; whether `src-chain-encoding.md`'s §Forward guard (the depth-0 `,`≡`;` acceptance) still holds for `;` at depth 1 in `key(...)` is a spec-time check, not asserted here.

On a text tag `use:title` is this grammar degenerate — one read, bare slug, no args.

## Why the positions are not date/time

They are **(primary field, optional supplementary time field)**. The primary accepts date, datetime OR time (S8), and that polymorphism is load-bearing: putting a time-only field in the PRIMARY position is what triggers the shipped date inheritance (S9). The second position exists only for the split-across-two-fields case.

Consequence for the control: position 1 labels as **"Date/Time field"**, never "Date field".

## Third position

CLOSED. Not because seconds are useless — because **a third position reintroduces holes.** At two positions every omission is trailing, so `key,a` is unambiguous; at three, `key,a,,c` becomes expressible and the hole problem this design exists to avoid returns.

Seconds specifically need nothing: they already flow through the parse, and whether they DISPLAY is the `format` axis, tag-level, already present.

The chain's precedent for optionals is a NAMED kv token after the positional args (`refs,office,limit(3)`). So a future axis is `key,a,b,tz(x)` — no hole possible, and no reader needs to know a position they never use. Timezone is the plausible candidate, not seconds.

## The all-day flag is not a third position

FW-35 (GH #41 — read a boolean all-day field, trim to date-only and/or show a note) was opened against this plan as the "missed third key need", on the premise that the flag is a THIRD position in the read list. **Premise withdrawn 2026-08-24.** The flag is a separate top-level option (`allDay`), so this encoding's two positions stand untouched and the two rows are independent — neither blocks the other.

Three reasons, all of which are this plan's own rules applied: a boolean read is a different KIND from a date read, so a member inside the list forces the mode derive to filter by slug kind (the sniff that killed R5); the flag is per-EVENT, so a separate option makes per-end UNSPELLABLE, which is S4's win by S4's own standard; and riding read 1 would make FW-35 a forcing function on O7, since a tag with an absent `use` has no read to hang the token on.

**S11 gains an independent confirmation from that work.** `bws_fold_parse_chain`'s own PHPDoc gives the same rationale for `limit` being NAMED rather than positional — argless-with-token is a reachable state, so a positional encoding needs an interior hole that fails SILENTLY. S11 closed the third position for the hole it creates; the chain closed it for the hole it needs. Same conclusion, reached twice, independently.

Detail home: `.scratch/plans/all-day-flag.md`. It also carries two shipped-render defects found while reading this family, both independent of both rows.

## Control

Reuse the slot repeater's CONCEPTS, not its machinery (S13). An "add" button takes the tag from single- to double-ended. Four behaviours lifted verbatim from `assets/js/slot-fold-control.js`, each of which was a shipped bug once:

1. **Add writes an explicit seed value.** No reveal chain.
2. **An absent value DISPLAYS as the default it spells.** A `SelectControl` whose value matches no row paints its first row while believing nothing is selected, so that row cannot be picked and Add never appears. Shipped twice, once per caller.
3. **Removal compacts** — no holes.
4. **Add gates on availability, not registration** — it disappears at two reads, same shape as `+ Add step` gating on offerable steps.

Vocabulary arrives on the PHP option definition, never typed in the control (the rule FW-62 is still cleaning up after).

## Migration

Mechanical, converter-side, deterministic:

*(Respelled 2026-11-17, D19–D21.)*

- `key` / `timeKey` → `key:<key>+<timeKey>` (just `key:<key>` when `timeKey` is empty)
- `startKey` / `startTimeKey` / `endKey` / `endTimeKey` → two `;` entries, each `<key>+<timeKey>`, a lone field when its partner is empty
- trailing empties stripped
- a range with no start (both `startKey` and `startTimeKey` empty) is NOT migrated and is reported (D26)

Six options collapse to one. The tag RENAME cannot run on the mount path (that path rewrites OPTIONS; the tag name belongs to the block's parsed tag) — so it is converter-only by construction, the same as the modifier→base migration, and O6 follows.

## Absorbed

The four rows retired into FW-81 on 2026-08-19. What each carried that this design must not lose:

### FW-40 — `datetime_single` `use` addition

The enum (`modified` | `key`, plus this design's `now`) and the **analog corollary**: the post source resolves the `post_date` analog BARE as a stripped default recovered with `??`, so on a scalar `use` an absent value already IS the published date, and a `published` token would be a second spelling of the default. Term and site have no native date, so a key is required there.

**⚠ CORRECTED 2026-10-12: the corollary above is FALSE against the code.** Datetime has no `use` option, no `post_date` analog and no `BWS_USE_STRIPPED_DEFAULTS` row; with no key the single core shows its fallback (`datetime-tags.php:442-444`). It described FW-40's proposed design, not shipped behavior. O7 below was settled on the corrected fact as D4.

**⚠ O7 — the corollary may not survive the list, and treating it as settled was premature (user, 2026-08-19).** A stripped default works at the OPTION level. FW-81 makes `use` a LIST, and a default cannot be stripped at a list position that has siblings: "start = published date, end = now" has no absent-spelling, because `use:;now` is precisely the hole this encoding exists to eliminate. So a second read appears to FORCE a token for the analog. Two sub-questions if it does:

1. Does the token appear only when cardinality > 1, or always? Only-when-plural means one concept has two spellings across cardinalities (absent option at 1, `published` at 2) — the kind of split S5 ("one syntax at every cardinality") was adopted to avoid.
2. If it is always spelled, the wholly-absent `use` option still has to mean something. Absent = single published read is the obvious answer and costs nothing, but it is then a THIRD spelling.

Not resolvable from the encoding alone; it is a decision about what an absent option means. The term/site half is unaffected either way.

### FW-41 — datetime key pair-combine

Its 6→3 is FW-81's 6→1, landing at the same single parse site (`bws_normalize_datetime_options`, which FW-2 was shaped path-B to provide). Carried: the public-key rename still produces ordinary migration rows, and the FU-3 filter stacking FW-14 tracks is more than halved rather than halved, since one option means one field control. Its detail home (`combined-option-controls.md` §Lighter variant) is annotated, not deleted.

### FW-65 — inner start/end split inside the field box

Dissolved on its own reasoning: it framed the question as "a second box, not a different map", the group map being keyed by option NAME. Six names collapsing to one leaves one control and nothing to subdivide — no map change, and the FW-64 composite dependency is discharged rather than inherited. `bws_option_visual_groups()`'s datetime block comment records this.

### FW-68 — the five datetime keys under the slot fold

**Premise CORRECTED.** The row held that `try_datetime_*` slots fold their source and their primary `key` while four other axes stay flat. False: the `datetime_single` / `datetime_range` templates register NEITHER `try_per_slot_key` NOR `try_per_slot_use` (`base-tags.php:553`, `:591` — cf. `text` / `content` / `image` at `:402` / `:444` / `:546`, and `email` / `phone`, which set key-only). So `try_datetime_*` has no per-slot read axis at all and ALL SIX key axes are flat tag-level. Scope was therefore entirely datetime and entirely tag-level, which is what let it retire rather than narrow. Its blocker (`decision:whether a slot can carry more than one read`) does not transfer — FW-81's read list is one option VALUE, not a slot carrying several reads.

## Reframings this produced

- **FW-65 dissolves.** The inner start/end box was asking for what a two-read list is.
- **FW-68 is reframed.** Not "can a slot carry more than one read" but "reads are a LIST, and every family except datetime sits at cardinality 1." The bracket-depth risk that row implied is void under this encoding (no brackets).
- **FW-41 is overtaken.** It proposed 6→3; this is 6→1 and lands at the same single parse site.
- **FW-40 widens.** Its enum is `modified` | `key`; this adds `now` and probably `published`, and `now` is COMPUTED rather than read, so it bypasses the field path entirely.

## Rejected

- **R1 — the range as a MULTISLOT tag (2 slots, ≤2 keys each).** Attractive: near-total reuse (fold grammar, repeater, both migration halves, serialization order, preview seam), ordinality native, and it solves the verb and inherit problems with shipped vocabulary. Rejected because it **spends the slot axis**, and FW-60 wants that axis for attempts — attempts × ends is a 2D structure in a flat `A`..`Z` namespace. Decided by the test: *does an author ever want the two ends of one range to come from different sources?* Answer: no — a range's ends are two fields on one entity. So the slot apparatus would be paid for a distinction that is not a source distinction, on the author's screen (a source chain control on slot B that always says `same`) as well as in the axis budget.
- **R2 — `use:key(a),time(b);now` (bracket-kv reads).** Heavier, and it burns a bracket level: inside a slot the inner tokens land at level 3, where the two-pair alternation repeats. Withdrawn in favour of the positional form, which needs no brackets at all.
- **R3 — `key(same)` / `same` in the field position for the uneven case.** Withdrawn: the runtime already INFERS inheritance from field shape (S9), so an explicit token would be a second way to spell one thing.
- **R4 — `time` promoted to a whole read.** Non-parallel (the same token meaning a component in one read and a whole read in another). Moot under the final encoding.
- **R5 — labelled `key:start(a,b);end(c,d)`, brackets only when both ends are set.** Three costs: burns a bracket depth level; two grammars for one option key, so the parser must SNIFF which form it holds (disjointness resting on "a meta key never contains `(`"); and it introduces NAMED positions where the single form is already positional. It also conflates structural grouping with FW-59's free-form value wrapper — under FW-59 brackets are unconditional and mean "free-form value", so a conditional bracket would be indistinguishable from one.
- **R7 — the "Separate time field?" toggle** (`combined-option-controls.md` §The `,,` interior-empty problem, 2026-07-09). A checkbox gating the second field slot, so an interior `,,` could never be written. **OBSOLETE rather than superseded:** under the `;`-separated read list an omitted time is always trailing WITHIN its read, so interior emptiness cannot arise and there is nothing for a toggle to prevent. That pass also treated the primary field's polymorphism as a render-time FALLBACK beneath the toggle; here it is the mechanism the encoding rests on (S8, S9).
- **R6 — pure positional with no leading slug (`a,b;c,d`).** Dies on both of the user's cases: a verb read has no cell (`a,b;now` cannot distinguish the verb from a meta key named `now`), and the uneven case needs a hole (`a,b;,d`). The leading `use` slug is exactly what rescues positional.

## Vocabulary note

An earlier pass in this session used "arity axis" for what the codebase already calls a step's **`arg`** — `bws_fold_wire_vocabulary()` builds a per-slug record carrying `label` / `arg` / `accepts` / `produces` (`base-shared.php:162-193`), with `arg` from `BWS_FOLD_STEP_TYPES` (`slot-fold-compile.php:78`). Say `arg`. The genuinely new part is that a datetime read takes a primary arg PLUS an optional modifier, where a step takes one arg — that is O2's whole content.
