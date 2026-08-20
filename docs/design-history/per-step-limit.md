# Archive: Per-step `limit` semantics + base-tag chain landing — FW-56 sub-plan (SHIPPED 1.17.0)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.
>
> **SITE-A / SITE-B are pseudonyms** for the two real client clones this record was measured
> against, substituted before publication. The measurements are unchanged; only the names are.

**Archived 2026-08-19** (CLAUDE.md §Spec lifecycle post-ship migration, `.claude/plans/release-1.17.0-queue.md`
B6). Shipped as [#55](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/55) and its
six tickets, #59–#64.

**Read this as the record of how the work was decided and built, not as a statement of how anything
currently works.** Live homes now: [ADR 0005](../../../docs/adr/0005-limits-are-stated-where-the-source-is-stated.md)
(the era-marker rule, D1's tag-level control retirement, no-deprecation-path); `docs/tag-reference.md`
§List mode (per-input/product schema, the full migration mapping table, the residual two-fanning-step
gap — carried forward from this file's §Residual holes on archive); PHPDoc on `bws_run_traversal()`
(`includes/helpers/traversal-pipeline.php`), `bws_limit_default()` / `bws_clamp_limit()`
(`includes/helpers/field-helpers.php`), and `bws_fold_chain_apply_legacy_limit()`
(`includes/helpers/slot-fold.php`, twinned in `assets/js/slot-fold-grammar.js`).

**One open question outlived the ship and travels elsewhere, not here:** whether `term_*` tags get
step limits by acquiring the chain control or by the arm learning to fan with its flat tokens (§D5's
premise, corrected). Closed on its merits as "neither, for now" (§OPEN) and now rides **FW-33**, not
FW-43 as this file's body states in several places — FW-43 was re-cut 2026-08-13 and the `term_`
collapse moved out of it. Trust the tracker over this file on that pointer.

---

Sub-plan of [`src-chain-encoding.md`](../src-chain-encoding.md) (FW-56). Split out 2026-08-05 because the
reopen of that plan's §Per-step `limit` produced ~12 decisions, four corrections to claims the master
plan had recorded as settled, and a release-scope change — none of which fit as edits to a section
whose surrounding text is now partly superseded.

**Tracker rows:** `docs/future-work.md` FW-56 (the chain), **FW-63** (arm dispatch — filed by this
plan; **closed 2026-08-05, row now in the Closed / Retired ledger** — it covered the BASE arms only,
the slot/`term_` half is FW-43). Related: FW-57 (slot fold, the container this lands beside), FW-61
(per-step `sep`, re-blocked here), FW-32 (ref-hop parity), FW-43 (slot-resolver extraction — the
successor gate).

**Provenance.** Reopened by the user 2026-08-04: *"limit over a chain with more than one fanning step
is undefined. This should integrate with the src-step controls. We also haven't started the src-chain
implementation on the base tags."* Grilled 2026-08-04/05.

---

## ⚠ STATUS — 2026-08-06: BUILT, then GRILLED and partly REVERSED. Work is ticketed.

The 2026-08-05 build below is real and shipped to the branch. On 2026-08-06 a hands-on pass over
the built UI reopened the limit question and **reversed several rows that section and §SETTLED had
recorded as closed** — including one correction the build itself had made. **Read §The grill before
anything else here**; it is the authoritative record, and the sections above it are kept only so
the supersession is visible rather than silent.

The remaining work is six GitHub tickets under **#55**, in dependency order:
~~**#59** (limit lands on the last fanning step; "cap" → "limit")~~ **BUILT 2026-08-07** →
~~**#60** (chain-spelled slots stop inheriting the flat default)~~ **BUILT 2026-08-07** →
~~**#61** (`try_` tag-level limit pushes into slots)~~ **BUILT 2026-08-07** → **#62** (the
tag-level control retires); ~~**#63** (`term_` gains the control) is independent~~ — **#63's premise
was disputed and withdrawn 2026-08-07 (user); read §D5's premise, corrected before touching it. It is
a renderer question joined to FW-63 arm dispatch, and it is NOT independent and NOT startable as
filed**; **#64** (ADR 0005, vocabulary, release prose) ~~is blocked by all five~~ **dropped #63 from
its blocker list 2026-08-07 — that dependency was content-only and the withdrawal discharges it. #64
is blocked by #62, which is itself blocked only on the branch merging.**

**Ticket state 2026-08-12:** #59/#60/#61 built; **#62 built** (all five acceptance criteria met —
no `limit` control on the six base tags or `try_`, stored `limit` still renders, `sep` untouched,
control-order harness updated with contiguity holding, editor eyeballed via fold matrix F14.14/b);
**#63 CLOSED `not planned`** (withdrawn premise — the surviving question moved to FW-43);
**#64's substance complete** (ADR 0005 + ADR 0003 amendment + doc/CONTEXT/CHANGELOG repairs, suite
green), with only the release-prose GATE outstanding, and that moved to **#81** because the release
lands in two parts and the gate runs once over the finished section.

**Vocabulary warning for everything below §The grill:** this file says "cap" ~60 times. That word is
RETIRED (D7) — it is "limit". The older sections are left in their original wording because they are
a record of superseded reasoning and rewriting them would disguise what was actually thought at the
time; the rename applies to the REPO, and #59 owns it. Read "cap" as "limit" throughout.

~~**The working tree is mid-edit and internally inconsistent** as of the grill~~ — **RESOLVED by
#59, 2026-08-07.** The helper is `bws_fold_chain_apply_legacy_limit()` / `applyLegacyLimit`, its
body relocates an explicit `N` onto the LAST fanning step as D2 states, and the noun rename
landed across the repo. Two carriers of "cap" are left standing on purpose and are NOT oversights:
the shipped CHANGELOG entries for 1.6.x/1.14.x (append-only — editing released prose to fit a
later vocabulary rewrites the record), and `tools/preview/tag-string-preview.html`. **The second
reason was WRONG and is corrected here (user, 2026-08-18):** the preview tool is not a dated
artifact of the same class as this file. It was HELD un-rewritten only until 1.17.0 ships, because
the wire it models was still moving and re-basing against a moving target is wasted work — a
pragmatic pause on a live tool, not a record of superseded reasoning being preserved. It resumes
maintenance on ship (`docs/future-work.md` FW-79). Reading the pause as an archival decision is
what would have made the re-base look like a policy reversal needing justification. The `limit` predicates are still
gated to flat wire; **#62** removes them outright.

---

## ✅ BUILT — 2026-08-05, branch `feat/base-tag-src-chains` (10 commits, unreleased)

**Every item on §Implementation list landed, in order, with the gate honoured.** The suite is green
(22 harnesses) and the integration matrices were RUN on the testbed rather than only written — which
is what released items 5 and 5b, since those were gated on arm-dispatch coverage being *complete*,
not merely authored.

What the RUN found that the reasoning had not — recorded here because three of the four are
corrections to this plan or to the matrices, not merely results:

1. **The SLOT half of the limit migration needs nothing** — §SETTLED's Q8 row now carries the
   correction and the measurement. Q8 reasoned from the wire the author sees; the flat seam
   re-spells it before the default is chosen.
2. **`{{content}}` ignores a relationship step entirely** and reads the ambient entity, on `main`
   as well — filed as [#58](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/58).
   The equivalence rows still pass, ON THE WRONG ENTITY, which is the sharpest available reminder
   that a green pair proves the two spellings AGREE and never that either is right.
3. **A matrix row was vacuous.** §F9.6 used a taxonomy the fixture's staff do not carry, so both
   eras rendered empty and it asserted nothing. On `portal_visibility` it is real and measured.
4. **`verify.php`'s RF1 check was stale by construction** — it compared a bare chain against a bare
   flat tag, which after the era rule compares two different quantities.

Two things deliberately NOT done, both surfaced by a harness rather than by review: `bws-term-hop`
stays (a control `type` is a registered identifier, so renaming it alone unregisters the control),
and the engine step types (`ref`/`srcTermIn`/`rows` → `refs`/`terms`/`entries`, V9) want their own
pass — they are internal-only and mechanical, but they touch the engine, the reader, the compiler map
and four harnesses at once.

**What is now hands-on** rather than decided on paper: the per-step `limit` control (§OPEN row 1) is
BUILT, so the interaction the user held it open for is available.

---

## UI/UX pass — 2026-08-05

First hands-on pass over the built control. Three findings, all reported from the editor, plus one
noticed while fixing them.

**1. The group box did not survive the move from slot to base tag.** `src-chain-control.js` had
declared its OWN `GROUP_BOX`/`GROUP_CAP` — untinted, with an absolutely-positioned notch caption —
beside the folded slot's tinted box with a block caption. Two declarations of one thing, which is the
drift the derived config exists to prevent, arrived here because the box was a *style object* rather
than a named thing either file could own. Fixed by giving the box an owner: `assets/js/option-group.js`
declares it once, both controls take the class names off it, and the two CSS fixes that used to be
scoped to `.bws-slot-fold` (a ComboboxControl's suggestions container showing the tint through, and
its label sitting flush) moved with it — they are properties of being INSIDE a box, and base tags
have boxes now.

**2. `Add step` never appeared until some other source had been picked, and `Current` could not be
picked at all.** One root cause, and it is a good one to remember: `src` is `_strip_default`, so an
unset tag holds no `src`, so `chainFromOptions()` (faithfully) answers with an EMPTY chain, so the
step editor rendered its "no chain yet" seed picker whose `value` is `''`. No option matches `''`, so
the browser displays the first row — "Current" — while the control believes nothing is selected.
Picking the row already on screen fires no change event; and with no step in hand there was nothing
to append to. Fixed by DISPLAYING the root that absence spells (`displayChain()`, PHP-derived
`defaultRoot` off the very row `_strip_default` blanks) and stripping a lone default root back out on
commit, so the wire is untouched. The display seam is deliberately NOT in `chainFromOptions()`, which
is the twin of a PHP reader and must keep answering what the renderer would.

**3. `entries` (repeater) missing from the base-tag source steps** — WORKING AS INTENDED, and the
reason is recorded on `bws_build_src_chain_option()`: no base-tag arm consumes a `meta_row`, so
offering the step would author a chain that renders nothing. Deferred to the table authoring pass
(user confirmed 2026-08-05).

**Grouping, and what it deliberately is not.** The user asked for the FIELD controls to be grouped as
well, for `limit`/`sep` to sit with Source (list length is a source property), and for the link group
to hold `linkTo` and its dependents but not the email/phone own-anchor pair. Built as a PRESENTATION
wrapper (`_group`/`_group_lead` stamped at registration by `bws_option_visual_groups()`, joined by
CSS) rather than as a composite control, because a composite swallows its members and would have to
re-implement the `show_if` reveal that `key` depends on. **The composite is a follow-up, not a
dropped idea — the user said so explicitly. Tracked as FW-64**, which also names the prerequisite:
export the predicate from `editor-conditional-options.js` rather than writing a second copy of it.

**`key` is a group LEAD as well as `use` (user, 2026-08-05, after seeing `{{phone}}`).** The
lone-member opt-out otherwise gave exactly the tags whose read is SIMPLEST no field group at all —
`{{email}}`, `{{phone}}`, `{{table}}` and the `term_` twins read a field key with no `use` enum in
front of it — while `{{text}}` beside them had one. The opt-out is for a control with no group to
show (a lone `linkTo` reading "No Link", a `try_` template's tag-level `limit`), not for a group with
one member. **Datetime's other key axes followed the same day (user): `timeKey` and the range's
`startKey`/`startTimeKey`/`endKey`/`endTimeKey` all join the ONE field box.** A boxed `key` with a
loose `timeKey` under it read as two groups where there is one. Left open: whether a range wants an
inner start/end split — that is a second box, not a different map.

**The FORMAT group followed (user, 2026-08-05):** `as`, `rangeSep`, `format`, `timeSep`,
`showCurrentYear`, `showMidnight`, plus `{{join}}`'s assembly pair `mode` + `valueSep` — one group
answering "how is the value rendered". Two lead decisions inside it, and together they are the
mechanism worth remembering:

- **`as` LEADS**, because it is not one control: `bws-as-size` renders a return type AND an image
  size from a single option key, so on `{{image}}` it is already a group of two. The user's rule for
  it — *"I don't think it makes sense for the control to lose its box if it started with one"* — is
  what the lead flag buys, since the CSS wrapper cannot see inside a composite and would otherwise
  drop the box the moment the size control hid.
- **`format` does NOT lead**, and no longer needs to: `mode` sits beside it on `{{join}}` and reveals
  exactly one of `valueSep`/`format`, so that group always has two visible members.

**Link group: NO lead — tried both, settled 2026-08-05 (user).** *"Leaves it compact and less
prominent until activated."* A tag left on "No Link" keeps a bare select; the box appears when a link
is configured and `linkKey`/`newTab` reveal. The principle that falls out and generalizes: **the link
is the one OPTIONAL group** — a source and a field read are what every tag does, so those boxes stand
whether or not they are configured, while a group an author may never use should not announce itself.
That is also the test for any group added later.

**The flat source controls a chain replaced are now retired (user, 2026-08-05).** Flagged first as
"noticed while fixing", then investigated on the user's question *"still registered to catch the old
option on mount, or just a leftover?"* — leftover, and the investigation is the part worth keeping:

- **Registration is not what carries a legacy value.** GB seeds `extraTagParams` from the PARSED TAG
  STRING, not from the option registry (`{id, source, key, link, required, tax, size, dateFormat,
  ...rest} = params`), and re-serializes the whole state object (`Object.entries`). So an
  unregistered key still reaches the mount migrator and still round-trips on a tag nobody touches.
  Registration decides only whether a CONTROL renders. Verified in GB 2.3.0's shipped bundle;
  recorded in `gb-constraints.md` §Option controls are flat siblings.
- **Neither flat control was hidden by anything.** `ref` LOOKED hidden because `show_if src:'ref'` is
  a literal equality that chain wire fails — a pre-chain condition happening to miss, so it still
  rendered on exactly the LEGACY tags where the duplication costs most (a flat picker beside a chain
  control already showing the same key as a `refs` step, each writing somewhere the other's next
  commit deletes). `srcTermIn`'s `not:site` passes for every chain spelling, so it always rendered.
  The contrast that makes this a defect rather than a style: `limit`/`sep`, which the chain did NOT
  replace, were updated deliberately via `chain_fans`.

Fixed by `bws_drop_chain_flat_options()` on the registration pass: gated on the control TYPE, reading
the chain option's own `flatAxes`, so `term_*`/`{{table}}`/`{{call}}` (plain-select sources) keep the
pair and a change to what the chain absorbs cannot strand a control. Mutation-verified in
`slot-options-build-test.php` §The REGISTRATION pass. **Residual risk, accepted:** if the chain
control ever fails to mount, those nine tags have no source UI at all, where they previously fell
back to the flat controls.

**CONTROL order was never lost — it was never enforced (user, 2026-08-06: *"Did we lose the
established difference between control and serialization order? try_ tags seem quite muddled"*).**
The two axes are intact and independent: control order IS registration order, serialization order is
the normalizer's, and FW-52 decoupled them on purpose. What FW-52 never reached was the two
CONSTRUCTORS. `generate_base_try_tags()` registered its format cluster FIRST — i.e. in
*serialization* order, on the one family that renders one — put `fallback` ahead of `link`, and
appended the chain-level `limit`/`sep` dead last. `register_modifier()` carried the
`fallback`-before-`link` half. One habit produced all three: appending a group at the END of the
assembly.

Harmless while every control was a bare sibling. **Grouping turned control order from taste into a
correctness property**, because a group boxes as ONE box only where its members are ADJACENT
siblings — the CSS can see nothing else. So the stranded `limit`/`sep` did not merely sit low, they
drew a captionless source box at the foot of a `try_` panel, describing a source thirty controls
away. That is the box flagged at the end of the grouping pass and left for "shortly"; the cause was
never the map.

Fixed by assembling both constructors in canonical control order (`source → field → format → link →
fallback`, `fallback` lifted out and re-appended last in both). `limit`/`sep` stay in the SOURCE
group on `try_` (user, 2026-08-06) — they are the chain-level cap, and list length is a source
property on every other tag; a `try_`-specific group name would have been a second vocabulary for
one idea.

**The lasting artifact is `tools/test/control-order-test.php`**, and its shape is the point: it stubs
GB's registrar, boots the plugin's own files in the plugin's own order, runs both constructors and
reads all three families' arrays at once — which is why it caught the `term_` half, invisible from
inside the `try_` constructor where the investigation started. It asserts CONTIGUITY rather than a
fixed sequence, since the constructors legitimately differ in where a group SITS (`term_` leads with
format) but none may SPLIT one. It also read the datetime format cluster and found
`showCurrentYear`/`showMidnight` documented in the wrong order since 1.16.0 — corrected in the DOC,
which inverts the standing rule (`tag-reference.md` is authoritative; drift normally resolves by
changing the code). **Allowed as a one-off, not precedent** (user, 2026-08-06): the doc row stated no
preference between the two, so there was nothing to honour. Mutation-verified both ways (fallback behind link → 6 failures;
`limit`/`sep` re-stranded → 6 failures).

---

## SETTLED — check here before recording any claim

Line numbers drift; the section TITLE is the anchor.

| Decision | Section | State |
|---|---|---|
| **A per-step `limit` is PER-INPUT** — `limit(1)` on a terms step = one term *per post*, not one overall | §Per-input, not whole-output | ✅ 2026-08-04 (user) — supersedes master row 113. **The ENGINE only caught up 2026-08-10 (#72):** `bws_run_traversal` had kept the whole-output slice, its PHPDoc restated the superseded justification, and `fold-chain-compile-test.php` §C7 PINNED the wrong semantic — so everything downstream (D2's stamps, the control's help text) described behaviour the renderer did not have. Migrated wire escaped it (stamped `1`s give every limited step one input, where the semantics coincide); only a hand-authored limit on a later step over an unbounded fan diverged. Fixed: slice inside the per-source loop, pins flipped + order-asymmetry pair added (mutation-verified), matrix row F8.10 (`All Users, All Users` where whole-output rendered one) |
| **There is no "value cap"** — every `limit` in this design caps `ResolvedSource[]`; the read is 1:1 | §The read is 1:1 | ✅ 2026-08-04 (verified in code + glossary) |
| **TOTAL is NOT the migration target** | §TOTAL, proposed and rejected | ✅ 2026-08-04 (user) — authorially arbitrary once a chain fans twice |
| **Per-step default is UNCAPPED, for every step type** | §Uncapped defaults | ✅ 2026-08-04 (user) — capping a plural step to 1 enshrines a defect the glossary already names |
| **No admin setting for per-step-type defaults** | §Uncapped defaults | ✅ 2026-08-04 — ADR 0004: wire meaning must not depend on site config |
| ~~**Legacy cap is materialized at COMPILE**~~ | §The spelling is the era marker | ⚠ **WITHDRAWN 2026-08-05** — the terminal default already preserves it; no step-cap materialization is needed, and the mapping it proposed was never faithful anyway |
| **The unfolded/flat `src` spelling IS the era marker** | §The spelling is the era marker | ✅ 2026-08-04 (user), **re-based 2026-08-05** — it governs the TERMINAL DEFAULT (flat = 1, chain = uncapped), not a step mapping |
| **`bws_clamp_limit`'s default-1 is the single-read defect at the terminal position** | §The spelling is the era marker | ✅ 2026-08-05 (user) — *"the same error as before enshrined in another place"*; it only ever bites on plural sources |
| **The era default is resolved from OPTIONS, once — call sites never pick a literal** | §Where the default is resolved | ✅ 2026-08-05 (user, Q11) — no call site is new-or-old; all five serve both eras |
| **`bws_clamp_limit`'s `$default` is a REQUIRED param** | §Where the default is resolved | ✅ 2026-08-05 (user) — omission must be `ArgumentCountError`, not silent legacy behaviour |
| **Author-initiated flat→chain conversion writes the limit AND shows it** | §Two surfaces, one rule | ✅ 2026-08-05 (user, Q12) — one stored shape across all three paths; visible rather than silent. **POSITION CHANGED 2026-08-06** (D2): it lands in the STEP's own field, which strengthens Q12's reason rather than weakening it — the number arrives in the row the author just built, and after D1 there is no tag-level field for it to arrive in |
| **`refs` from a SITE root gets wired** (engine allowlist + reader case) | §Implementation list, item 3 | ✅ 2026-08-05 (user, Q25) — an options store holds relationship fields; `case 'rows'` already accepts site, so the refusal is Stage A wiring, not a rule |
| **The 25 target-less N×M entries get migration targets** | §Implementation list, item 5b | ✅ 2026-08-05 (user) — the code's own `@invariant` names chain wire as the missing prerequisite; one-directional risk (they render nothing today) |
| **Base-tag depth-0 src migration IS registered, this release** | §Two surfaces, one rule | ✅ 2026-08-05 (user) — **corrects an earlier row in this same plan** that had it permanently unregistered |
| **Base-tag chain AUTHORING is separable from base-tag MIGRATION** | §Two surfaces, two answers | ✅ 2026-08-05 (user) — conflating them was the error that produced a wrong scope answer |
| **Arm dispatch + base-tag chain authoring land in THIS release** | §Arm dispatch, sized | ✅ 2026-08-05 (user) — *"together"* |
| **Matrix coverage is the arm-refactor deliverable, not the swaps** | §Arm dispatch, sized | ✅ 2026-08-05 — a wrong arm renders a plausible value, not nothing |
| **`sep` conditional display + per-step `sep` are deferrable UX** | §Deferred UX | ✅ 2026-08-05 (user) — not architecture; deferring is not foreclosing. ⚠ `limit` visibility was in this row and is NOT deferrable — Q12 pulled it into scope |
| **Per-step `limit` gets a control on every fanning step** | §Deferred UX | ✅ 2026-08-05 (user) — dropping the default is what makes it the author's instrument |
| **A LIMIT IS STATED WHERE THE SOURCE IS STATED** — the rule the other six hang off | §The grill | ✅ 2026-08-06 (user, grilled) — chain states its source as steps ⇒ limits on steps; a flat-select family states one step ⇒ the tag-level key IS its limit. ADR 0005 (#64) |
| **D1 — the tag-level `limit` CONTROL is retired** (six base tags + `try_`), value still READ | §The grill | ✅ 2026-08-06 (user) — unregistered, not gated: a flat-wire predicate is unreachable because mount migration fires before the panel paints. #62 |
| **D2 — migration relocates an explicit `limit:N` onto the LAST fanning step**, `1` on earlier ones, tag-level key deleted | §The grill | ✅ 2026-08-06 (user) — POSITIONAL, not "the terms step": `refs` takes the `N` when `refs` is last. Two rejected mappings recorded in §The grill. **BUILT #59, 2026-08-07** — one owner (`bws_fold_chain_apply_legacy_limit()` + `applyLegacyLimit`) read by all three writers; mutation-verified on each, and dropping the earlier-step `1` in PHP alone fails the M7 twin |
| **D3 — `try_`'s tag-level `limit` pushes into its fanning slots** | §The grill | ✅ 2026-08-06 (user) — faithful, since the number WAS each slot's default. **BUILT #61, 2026-08-07.** #60 had already pushed the VALUE (it could not be output-neutral otherwise); what landed here is retiring the KEY, and three things the decision did not state: (1) an ALREADY-FOLDED slot takes the number too, which is the shape the ticket's own acceptance criterion names and which carries no legacy slot key — so the entry's MATCH surface and its MAPPER surface stop being one list (`bws_fold_migration_match_keys` vs `bws_fold_migration_slot_keys`); (2) the one shape where output could have moved is a slot that fans only by INHERITING, and it does not, because **`src(same)` means the same SOURCE and a limit is one of a source's parameters** (user, 2026-08-07, answering the ticket's open question) — so the flatten seam carries the resolved QUANTITY, on a SELECTING container only, since `{{join}}` owns `limit` per slot and carrying it there moves shipped output (§P13.1 `term hop with limit` is the case that says so); (3) the container arm's `?? $opts['limit']` fallback STAYS — the value outlives the key, for wire in ACF meta neither migration path reaches. Measured on the testbed: every legacy→migrated pair renders identically (`fold-test-matrix.md` §F7b) |
| **D4 — chain wire defaults uncapped EVERYWHERE, slots included** | §The grill | ✅ 2026-08-06 (user) — **REVERSES the Q8 correction two rows below.** The `1` is only the flat era's implied default, materialized at migration or supplied at read time. Free only because zero folded wire exists and 1.17.0 is unreleased. **BUILT #60, 2026-08-07** — the flatten seam reports the era it erases (`$limit_default` out-param beside `$skip_reason`), and `bws_fold_from_flat()` materializes through the SAME `bws_fold_chain_apply_legacy_limit()` the base half uses, so the two depths cannot drift. Three things the build found that the decision did not state: (1) the chain default applies only where the slot's OWN chain fans — a `src(same)` or an argless `refs` fans by INHERITING a source another slot already bounded, and giving it the chain default unbounds a migrated join slot, so the fanning predicate is SHARED with the migrator's stamp; (2) an explicit legacy `0` still needs its carrier, because the same mapper renders UNMIGRATED flat wire, which takes the flat era's 1; (3) `try_`'s TAG-LEVEL `limit` has to be READ at every slot position or the materialized `1` overwrites the author's number — so the mapper's VIEW gained the key while the delete list did not, which is a slice of #61's mechanism landed here as the thing that keeps #60 output-neutral. #61 still owns retiring the key. Measured on the testbed (§F7a): `{{try_text A:src(terms,department);use(title)}}` now returns every term, and every legacy→migrated pair renders identically |
| **A LIMIT IS STATED WHERE THE SOURCE IS STATED — stated with ONE clause** | §The grill / ADR 0005 | ✅ 2026-08-07 (user, grilled) — the flat-select second clause is DROPPED: zero instances and no prospect of any, and it never had one, since it was inferred from `term_`'s registration arrays. The tag-level position is a legacy READ, with `bws_limit_default()` as the compatibility mechanism. **BUILT #64, 2026-08-07** — `docs/adr/0005-limits-are-stated-where-the-source-is-stated.md` records the two-clause shape as considered-and-rejected |
| **No deprecation path for the tag-level read** (explicit `limit:N` OR the flat default of 1) | ADR 0005 §Consequences | ✅ 2026-08-07 (user) — *"forever until redecided — v2, perhaps"*. Written as 1.x scope, not an eternal promise: the population is unenumerable, which is the same fact that killed forced migration, so scheduling a removal would be incoherent. Revisiting needs its own ADR |
| **ADR 0003 is AMENDED, and 0005 cites it as precedent** | ADR 0003 / ADR 0005 | ✅ 2026-08-07 (user) — `{{join}}`'s per-slot limit (July 2026) is this rule reached independently, from a defect report rather than a design pass. Not superseded: 0005 says nothing about the inner `sep` (FW-44). The 1.17.0 Update records that the carrier moved from `{N}-limit` into the slot's chain |
| **ADR 0005 is WRITTEN, not dropped** — the held plan deliverable | §ADR candidate / ADR 0005 | ✅ **2026-08-11 (#64)** — `docs/adr/0005-limits-are-stated-where-the-source-is-stated.md`, status accepted. The hold ("decide at build, not now") paid off as intended: the era rule shipped exactly as §The spelling is the era marker states it, so the ADR describes what HAPPENED rather than what was intended. Carries the one-clause rule with the two-clause shape recorded as considered-and-rejected, the four rejected alternatives with their reasons, no deprecation path for either the explicit read or the flat default, ADR 0003 as the precedent it generalizes, and #62's two wire findings (an explicit `limit:0`/`-1` is DELETED at depth 0 once the field is gone; a tag-level limit is legacy by POSITION, not by spelling). **#64's remaining item is the release-prose GATE, which is not this** — moved to **#81**, because the release lands in two parts (this branch, then #80) and the gate runs once over the finished section |
| **Four visual groups, and which of them box when ALONE** | §UI/UX pass — 2026-08-05 | ✅ 2026-08-05 (user, by trying it) — source/format/field box always (leads `src`, `as`, `use`+`key`+`startKey`); LINK does not, because it is the only optional one. `as` leads because it is a COMPOSITE that would otherwise lose the box it started with |
| **A chain source retires the flat controls it absorbed** (`ref`, `srcTermIn`) | §UI/UX pass — 2026-08-05 | ✅ 2026-08-05 (user) — `bws_drop_chain_flat_options()`, type-gated and `flatAxes`-derived, never a second list. The value still round-trips: GB seeds state from the tag string, not the registry |
| **Both constructors register in canonical CONTROL order**, and `limit`/`sep` stay SOURCE-group on `try_` | §CONTROL order was never lost | ✅ 2026-08-06 (user) — control order vs serialization order is intact and independent; FW-52 simply never reached `generate_base_try_tags()` / `register_modifier()`. Grouping made contiguity a CORRECTNESS property, which is what exposed it. Pinned by `control-order-test.php` with all three families in one process — that is how the `term_` half was found |
| **Engine step types ARE the wire slugs (V9)** | `src-chain-encoding.md` §VOCABULARY | ✅ **BUILT 2026-08-07 (#69)** as its own commit, as the row asked. `BWS_FOLD_STEP_TYPES` narrowed to `slug => arg-key` with it — no translation step survives (the master's rename-table row carries the grill note) |
| **`limit` vocabulary stays owned by `tag-reference.md` §List mode** | CLAUDE.md §Documentation ownership | ✅ 2026-08-07 (user) — CONTEXT.md I14 gets a forward-link and NO definition. The distinction was already stated three times; a §Language headword would have been the fourth, and #64's AC as filed would have been marked done by writing it |
| **`'limit'` deleted from `bws_option_visual_groups()`** | §The grill follow-through | ✅ 2026-08-07 (user) — held open for #63 alone. The serialization rank STAYS (stored wire still sorts). **Mutation-verified, and the result corrected the comment first written:** a CHAIN tag registering `limit` is caught by `control-order-test.php` §5, but a FLAT family is caught by NOTHING — §1's contiguity check filters to options that already carry a `_group`, so an ungrouped one spliced mid-run is dropped before the check and passes 122/122 while breaking the box in the panel. Harness gap, recorded at the site |
| ~~**D5 — `term_` gains `limit` + `sep`**~~ | §The grill | ⚠ **REOPENED 2026-08-07 (user)** — the decision's PREMISE is false in code: `term_` honours `limit`/`sep` nowhere, and its fan is discarded by the modifier arm rather than bounded at 1. See §D5's premise, corrected. The RULE it was derived from (a limit is stated where the source is stated) is untouched and now points the other way. Row moved to OPEN; #63 needs re-filing |
| **Per-step `limit` control SHAPE — empty = uncapped, `0`/`-1` normalize away, placeholder `0 (all)`** | §Per-step `limit` control | ✅ **2026-08-11 (user) — APPROVED AS BUILT**, after the hands-on the provisional Q13 decision was explicitly held for. Built 2026-08-05 (`slot-fold-control.js`, every fanning step; round trip walked by matrix row L5.7). Nothing moved between the provisional shape and the approved one, which is the outcome the hold was testing for rather than a sign the hold was unnecessary — after D1 this is the ONLY limit control on the six base tags and `try_` |
| **D7 — "cap" is not a noun here; it is "limit"** | §The grill | ✅ 2026-08-06 (user) — per-step limit / tag-level limit / total limit. All affected identifiers unreleased. **BUILT #59, 2026-08-07** — repo-wide, verb use reworded ("bounds") rather than swapped; the caption abbreviation `CLS.cap` and the `edit_posts` capability went with it. Deliberately untouched: shipped CHANGELOG entries and `tools/preview/tag-string-preview.html` |
| ~~**Migration carries the old limit onto the STEPS, never as a tag-level `limit`**~~ | §Limits are stated where the source is stated | ⚠ **Rewritten same day by the grill.** Its rule SURVIVES as D2; its two supporting claims did NOT — it argued the tag-level control should stay as "the only whole-list bound" (reversed by D1) and that the slot half needed nothing (reversed by D4). ✅ 2026-08-06 (user) |
| ~~**Migration DOES serialize `limit:1` on a fanning source that had no `limit`**~~ | §Two surfaces, one rule | ⚠ **SUPERSEDED twice.** First by D2 (still written, different position), then its SLOT half **REVERSED by D4** — the correction below was true only because the flatten handed the default back as 1, and once a slot honours its own spelling that prop is gone. Do not reason from it. ✅ 2026-08-05 (user, Q8) — **BASE TAGS ONLY. Corrected at build 2026-08-05: the SLOT half needs nothing.** A folded slot's chain is flattened back to a flat `src`/`ref`/`srcTermIn` triple by `bws_fold_slot_flat_options()` before any container arm resolves a limit, so `bws_limit_default()` sees FLAT wire on a folded slot exactly as on a legacy one and returns 1 either way. Q8 reasoned from the wire the author sees; the seam re-spells it before the default is chosen. **Measured on the testbed:** `{{join srcTermIn:department\|use:title}}` and `{{join A:src(terms,department);use(title)}}` both render `Sales`, as do the `try_text` and `refs` twins. So the fold-migration change is depth-0 only; `bws_fold_from_flat()` is untouched |

**OPEN — do not treat as settled:**

> **RECHECKED 2026-08-12, ahead of the merge.** Of the ten rows below, **one is still genuinely
> open** — *Where `term_`'s limit goes* — and it does not gate the merge (it rides FW-43, and
> `term_` renders as it did before the branch). Two MOVED to tracker rows rather than closing
> (the composite control → FW-64, `bws-term-hop` → FW-67); the rest closed. Struck titles are the
> audit trail of what was open, per this file's convention — the notes say which way each went.
> **Five closed rows were PROMOTED into §SETTLED in the same pass** (ADR 0005 written, the four
> visual groups, the flat-control retirement, canonical control order, V9 engine slugs), leaving
> one-line pointers here. A closed row's CONTENT belongs in §SETTLED: leaving it sitting under an
> OPEN heading is the supersession-in-place this index exists to prevent.

| Open item | Section | Note |
|---|---|---|
| ~~Option-control GROUPING: wrapper now, composite later~~ | §UI/UX pass — 2026-08-05 | ✅ **MOVED OUT 2026-08-11 — tracked as FW-64**, not open here. Wrapper BUILT 2026-08-05 (user chose it, with the composite explicitly kept as a follow-up rather than dropped). The composite's prerequisite is exporting the `show_if` predicate from `editor-conditional-options.js`, never re-implementing it. Detail home is the tracker row; nothing in this plan gates it |
| ~~Four groups, and which of them box when ALONE~~ | §UI/UX pass — 2026-08-05 | ✅ CLOSED 2026-08-05 (user, by trying it). **Row moved to §SETTLED** |
| ~~**A chain source retires the flat controls it absorbed** (`ref`, `srcTermIn`)~~ | §UI/UX pass — 2026-08-05 | ✅ CLOSED 2026-08-05 (user) — `bws_drop_chain_flat_options()`. **Row moved to §SETTLED** |
| ~~**Both constructors register in canonical CONTROL order**, and `limit`/`sep` stay SOURCE-group on `try_`~~ | §CONTROL order was never lost | ✅ CLOSED 2026-08-06 (user). **Row moved to §SETTLED** |
| **Where `term_`'s limit goes, if anywhere** (was D5) | §D5's premise, corrected | ⚠ **REOPENED 2026-08-07 (user).** Not "register the pair" — that ships two controls nothing reads. The only fan on a `term_` tag is a `refs`/`terms` STEP, and the rule puts the limit there, which makes this an arm-dispatch item (§Arm dispatch, sized) rather than a registration one. Undecided: whether `term_` gets step limits by acquiring the chain control, or by the arm learning to fan with the flat tokens it has. #63 must be re-filed either way. **Re-pointed 2026-08-11: the arm-dispatch row this waits on is FW-43, not FW-63** — FW-63 closed 2026-08-05 having swapped the BASE arms only, and the collapse here lives one level down in `bws_fold_slot_flat_options()` + the container arms, which is FW-43's stated deliverable. #63's `Blocked by` now reads FW-43. **STILL OPEN, and #63 is NOT the tracker for it — #63 closed `not planned` 2026-08-11** (its premise was the withdrawn one). The undecided question survives the closure and now rides **FW-43** (verb-agnostic resolver / arm dispatch one level down), which is where a `term_` fan becomes bounded at all. Does not gate the merge: `term_` renders exactly as it did before this branch. **ANSWERED 2026-08-18 (user) — NEITHER, for now: the deferral is a PREFERENCE, not a blocker.** The user would rather build term-SELECTION capability on the BASE tags than extend `term_`, so neither candidate shape gets built: `term_` does not acquire the chain control, and its arm is not taught to fan with the flat tokens it has. That is consistent with the family’s glide path (FW-33 / FW-69-70) — spending build on a family on its way out buys a control that retires with it. **The question is not closed on its merits, it is closed by preference of ORDER**, so it reopens on its own if base-tag term selection lands and `term_` is still registered and still unbounded. Track it there, not here |
| ~~Per-step `limit` control shape~~ | §Per-step `limit` control | ✅ **CLOSED 2026-08-11 (user) — APPROVED AS BUILT** after the hands-on the provisional Q13 shape was explicitly held for. **Row moved to §SETTLED** |
| ~~**ADR 0005 — write it, or drop it?**~~ | §ADR candidate | ✅ **WRITTEN 2026-08-11 (#64). Row moved to §SETTLED.** The prose GATE that remains on #64 is a separate thing and moved to **#81** |
| ~~Engine step types → wire slugs (V9)~~ | `src-chain-encoding.md` §VOCABULARY | ✅ **BUILT 2026-08-07 (#69)**, as its own commit. **Row moved to §SETTLED** |
| ~~`bws-term-hop` control type~~ | — | ✅ **MOVED OUT 2026-08-11 — tracked as FW-67.** Unchanged reasoning: a control `type` is a registered identifier the JS matches on, so it is INTERFACE rather than prose, and renaming it alone silently unregisters the control — it moves in lockstep with `term-hop-control.js` and every registration naming it, or not at all. **The tracker RE-CUT it and the likely outcome is DELETE, not rename:** the carrier survives only where the flat `srcTermIn` control still registers (the modifier families via `register_modifier()`, plus `{{table}}` — they take `bws_base_traversal_options()` raw, so `bws_drop_chain_flat_options()` has no chain option to gate on). Every base tag already retired it, so it dies with `register_modifier()` (FW-70) or with `{{table}}` taking a chain source (FW-53) |
| ~~`{{table}}`'s ceiling / noun~~ | `table-tag.md` | Unaffected by this plan then and now. **2026-08-12: `{{table}}` registration is gated OFF by default** (`bws_dynamic_tags_register_table_tag`, default FALSE) and v1 deferred to 1.18.0, so nothing here rides the merge |

---

## The frame: every `limit` caps `ResolvedSource[]`

`CONTEXT.md` §Language already owns this and it is the sharpest available framing —
**target cardinality**: *"A resolved source is `ResolvedSource[]` — a list, usually length 1. …
List mode originates here — plural resolved-source, **read once per source** — NOT a read-time loop."*

So there is exactly one KIND of quantity in this design — a cap on a resolved-source list — appearing
at two POSITIONS:

| position | applied | by |
|---|---|---|
| **per-step** | between steps, inside the engine | `traversal-pipeline.php:92-93` |
| **terminal** | to the final list, after traversal, before the read | `field-helpers.php:566-568`, `:648` |

### The read is 1:1

`bws_read_resolved_source(): string` returns one value per source, empties dropped. Multiplicity lives
entirely in the chain (`entries` turns a repeater into meta_row *sources*), never in the read. All four
`bws_collect_value_list()` callers (`base-tags.php:710,733,1176,1195`; `datetime-tags.php` ×4) are step
branches, so a plural item list only ever comes from a fanning step.

**Consequences, both of which corrected earlier claims:**

- **"Value cap" does not exist.** An earlier draft of this analysis posited a value-list cap distinct
  from a source cap. There is none, and any example needing a multi-valued read is fictional.
- **Master plan row 77's RATIONALE is wrong.** It says a legacy `limit` with no fanning step *"caps a
  multi-value READ"*. There is no multi-value read. Its BEHAVIOUR (leave the token where it is) is
  harmless either way, because with no fanning step the source list has one element and the cap is a
  no-op — but the reason recorded for it does not hold, and should not be reasoned from again.

One property worth pinning because it surprises and is **already true today**: the terminal cap applies
*before* the read, and empty reads are dropped after (`field-helpers.php:575-581`). So `limit:3` can
print 2 items. It caps sources, never printed values.

---

## Per-input, not whole-output

**The question.** Given `refs,field,limit(2);terms,category,limit(1)` — is the terms cap "1 term
overall" or "1 term per post"?

**Shipped behaviour ~~is~~ WAS whole-output** (⚠ stale as of 2026-08-10 — the engine now slices
per-input, #72; the demonstration below records what it did before): `bws_run_traversal` fans every
input into one `$next`, *then* slices. Demonstrated against the then-shipped engine with an injected
reader (fixture world: ambient post → posts 1,2,3; each post → 2 terms):

```
A  refs,field,limit(2);terms,category,limit(1)  => [t11]                          (1 source)
B  refs,field,limit(1);terms,category,limit(2)  => [t11, t12]                     (2 sources)
U  refs,field;terms,category   (both uncapped)  => [t11,t12,t21,t22,t31,t32]      (6 sources)

per-INPUT reading of the same two chains:
A                                               => [t11, t21]
B                                               => [t11, t12]
```

**Decision: per-input.** `limit(1)` on the terms step means one term from each of the ref'd posts.

Three mechanics that follow, and are worth keeping because a reader will otherwise re-derive them:

1. **A cap slices the step's output per input source, then concatenates.** Under the old whole-output
   rule, A visited post 2, produced 2 terms and discarded all of them — work with no output — and post
   3 was never reached.
2. **Order is not symmetric.** An earlier cap bounds what later steps can *see*; a later cap only
   bounds what survives. A's `limit(2)` on step 1 makes terms 31/32 unreachable regardless of step 2.
3. **Ceiling is the product**, `∏ limitₙ` (whole-output's ceiling was the last cap alone, which made
   every earlier cap a work-bound rather than a shaping tool).

**Why whole-output loses.** Its only recorded justification (master row 113 / 5h) is legacy
equivalence — *"it is what the legacy flat `limit` sliced when the fan-out was the last step."* That
justification does not bind, and the code says so:

- `bws_fold_chain_from_options()` builds every legacy-derived step with `'limit' => null`
  (`slot-fold-compile.php:207`, `:225`) — a legacy base tag's flat `limit` stays the terminal cap and
  never becomes a step cap.
- In a slot, `bws_fold_slot_flat_options()` collapses any step limit back into the one flat `limit` the
  container arm consumes (`slot-fold.php:95-120`).

So **no stored tag anywhere reaches `bws_run_traversal` with a step cap.** Proven live: with the
terminal default unset (=1), `{{join A:src(refs,related_staff,limit[2]);key(name_last)}}` printed
**two** values. A genuine step cap would have been re-clamped to one by the terminal default; it wasn't,
because the step's `2` *became* the terminal cap.

Whole-output existed to protect a migration step that is itself the dubious move: **legacy `limit` was
a terminal cap, and mapping it onto a step changes its kind.**

---

## TOTAL, proposed and rejected

Proposed (by me) as the lossless migration target for legacy `limit:N`, on the grounds that a flat
slice of the final list *is* a total cap and reproduces today's output for every chain shape.

**Rejected (user, 2026-08-04): TOTAL is mechanically deterministic but authorially arbitrary once a
chain fans twice.** `refs(3 posts);terms(2 each)` with TOTAL 3 yields `[p1t1, p1t2, p2t1]` — both of
post 1's terms, one of post 2's, nothing from post 3. The cut lands mid-parent at a position set by
fan-out widths the author cannot see, and it is parent-major only because `bws_run_traversal` happens
to iterate that way — an implementation artifact, not a modelled property. No authorial intent has that
shape.

Second reason: it preserves a semantic the user had already contradicted (the `[t11, t21]`
expectation). Lossless preservation of a rule judged wrong is not a virtue.

**TOTAL is not dead as a FEATURE** — a genuine "at most N overall" bound is inexpressible with per-step
caps (product semantics), and remains a legitimate future addition. It is dead as a *migration target*.

---

## Uncapped defaults

**Decision: a per-step `limit` defaults to uncapped, for `refs`, `terms` and `entries` alike.**

**Reach extended 2026-08-05:** the same reasoning applies to the TERMINAL default, which was left at 1 by
this section and only caught later — see §The spelling is the era marker. Step-uncapped with the terminal
still clamping to 1 would have been no change at all.

The path here is worth recording because two intermediate positions were taken and dropped:

1. First position (mine): uniform `1`, on the reasoning that a tag renders one value unless told
   otherwise, and per-step-1 reproduces that at any chain depth.
2. **Withdrawn (user, 2026-08-04)** on two grounds that are both stronger than uniformity:
   - **`refs`** — an ACF relationship field carries its own authored max. The author already stated how
     many they want when they configured the field; a default-1 overrides it with a number they never
     wrote.
   - **`terms`** — `get_the_terms` orders by name/term_id, not authorship, so `limit 1` returns an
     arbitrary term the author cannot predict or control. **A cap whose content is inscrutable is not a
     sensible default for a source the model calls plural.** And terms are usually wanted as a list.

**The glossary already agreed.** `CONTEXT.md` §Language, target cardinality: *"`ref` … `srcTermIn`
**plural** (N)"*, then *"(Today `ref` is collapsed to the first by `bws_extract_post_id` — a latent
single-read **defect** the plural model exposes.)"* Defaulting a plural step to 1 enshrines the defect
the model names; `feedback_contradictions_refactorable` says a code-vs-model contradiction is a
refactor candidate, not an exception to enshrine.

**No admin setting.** Raised (user) as a way to make defaults per-step-type and site-configurable.
Rejected:

1. It makes stored wire's meaning depend on site config. ADR 0004 binds the wire to being readable and
   hand-editable; a reader could not tell what `refs,x;entries,y` does without a settings page.
2. It is a global lever for a per-tag concern — one fix moves every tag on the site, silently, on tags
   nobody opened.
3. The escape hatch is already per-tag, explicit and on the wire (`limit(1)` on the step).
4. The job it existed to do — preserving `refs` behaviour — is done by the compile-time era rule below.

If per-type defaults are ever wanted, the honest form is a code-level table we own, not admin config.

---

## The spelling is the era marker

⚠ **Re-based 2026-08-05.** This section first proposed materializing the legacy cap as STEP caps in the
compiler's legacy branch. That is **withdrawn**: it was unnecessary, and it was never faithful. The
era marker survives with a different and simpler job — selecting the **terminal default**.

**The problem (user, 2026-08-04): migration has never been forced.** The scanner reads `post_content`
only; the mount migrator fires when a tag modal opens. The site survey found a live case neither
reaches: SITE-A ACF meta `philosophy_overview` on posts 76064/76694/78062 holding
`{{post_term_title taxonomy:teams}}`. So whatever preserves old behaviour must work on wire no
migration ever touches.

### What the withdrawn version got wrong

**The terminal cap is spelling-agnostic and already defaults to 1.** `field-helpers.php:567-568` runs
`bws_clamp_limit( $options['limit'] ?? null )` on the traversal output without inspecting `src`, and
`bws_clamp_limit` returns **1** for absent/non-numeric (`field-helpers.php:490`). Chain wire reaches the
same line through the same function. So respelling a source flat→chain never changed how many sources
survived, and the proposed step caps were a no-op everywhere except one shape:

| tag-level `limit` | flat | chain, uncapped steps | same? |
|---|---|---|---|
| absent | 1 | terminal 1 | ✅ same element (first-of-first) |
| `0` / `-1` | all | all | ✅ |
| `N>1`, ONE fanning step | N | N | ✅ |
| `N>1`, TWO fanning steps | first N of the parent-major flat walk | per-input, stops inside parent 1 | ❌ §Residual holes — zero surveyed instances |

And even for that shape the proposed mapping (`1` on earlier steps, `N` on the last) only reproduces the
flat walk when parent 1 has ≥ N children — so it bought exactness it did not deliver.

### What it is actually for

**`bws_clamp_limit`'s default-1 is the single-read defect at the terminal position** (user, 2026-08-05:
*"the same error as before enshrined in another place"*). It is the identical rule §Uncapped defaults
rejected one position earlier, and it bites only on plural sources — on a singular source the slice is a
no-op. Leaving it universal would have made that decision's reach arbitrary: steps uncapped while the
terminal quietly re-clamps every tag to 1. `feedback_contradictions_refactorable` — refactor candidate,
not an exception to enshrine.

It cannot simply be flipped: ~110 authored instances depend on it, with no author present. So the era
marker's job is to say **which spelling is entitled to the defective default**:

> **Flat wire defaults its terminal cap to 1. Chain wire defaults its terminal cap to uncapped.**

`bws_fold_chain_is_wire( string $value )` (`slot-fold-compile.php:162`) decides it from `src` alone, and
the two vocabularies are disjoint — `ref` is a legacy token, `refs` a step slug — so the test is exact,
not heuristic.

Five consequences:

1. **Forced migration is unnecessary.** An unmigrated tag gets its default from its own spelling,
   wherever it lives — `post_content`, `postmeta`, an ACF field, a widget nobody opens.
2. **The compiler's legacy branch is untouched.** It keeps emitting `'limit' => null`
   (`slot-fold-compile.php:207`, `:225`). No mapping table, no second owner.
3. **Migration serializes ONE tag-level `limit:1`**, not per-step caps — see §Two surfaces, one rule.
4. **`{{table}}` needs nothing** — it authors chain wire, so it is uncapped by default and its caller
   stops materializing `limit => 0`. **Supersedes master row 72.**
5. **The step default and the terminal default now agree.** Uncapped at both positions, on new wire.

**Two costs, both real and both accepted:**

- **ADR-0004 readability** — the same conceptual source has a different default cardinality depending on
  spelling. Same shape as the fold's existing legacy-vs-folded absence divergence; applies only to a
  deprecated spelling; and it is what avoids touching a stored row.
- **The link gate is count-based.** CLAUDE.md's `limit`-default trigger pins it: *"a silent 1→many flip
  drops anchors while the text still reads fine."* Chain wire defaulting to many means link-wrapping
  differs by spelling. New wire only, but the L-matrix needs rows per SPELLING, not just per `limit`
  value.

### Where the default is resolved

**No call site is new-or-old — all five serve both eras**, so "new sites pass `0`, old sites pass `1`"
has no referent (user's question, 2026-08-05; answer: neither).

| site | serves |
|---|---|
| `field-helpers.php:567` | every base tag, both spellings |
| `field-helpers.php:641` | same, list-mode path |
| `class-tag-template-registry.php:732`/`:734` | try_ slots, folded or legacy |
| `base-shared.php:592` (`bws_try_join_items`) | **has no options at all** — structurally cannot know the era |

The era is a property of the tag's WIRE. Resolve it once:

```php
bws_limit_default( array $options ): int    // 0 if bws_fold_chain_is_wire( src ), else 1
bws_clamp_limit( $raw, int $default ): int  // pure interpreter, rule unchanged
```

Options-holding sites become `bws_clamp_limit( $options['limit'] ?? null, bws_limit_default( $options ) )`.
`bws_try_join_items` takes an already-resolved int from its caller and drops its internal clamp — it is
the one site that cannot ask, and it must never receive `null` again.

This is what keeps CLAUDE.md's standing rule intact (*"THE single interpreter … never re-inline the rule
at a call site"*). Letting each site choose `0` or `1` would re-inline half of it — every site growing
its own spelling test is exactly the copy-drift the extraction removed.

**`$default` is REQUIRED, not defaulted to 1** (user, 2026-08-05). A defaulted param means a site that
forgets it silently renders legacy behaviour on chain wire — wrong output, looks normal, invisible in
review. Required means `ArgumentCountError` at the call. Same posture as the twin harnesses FAILING on a
missing `node` rather than skipping. Safe to require: `bws_clamp_limit` is `@since 1.17.0` and
unreleased, so no external caller exists. After this, literals appear only in `limit-clamp-test.php`.

---

## Limits are stated where the source is stated — 2026-08-06, GRILLED

⚠ **This section was rewritten after the 2026-08-06 grill.** Its first draft (same day) kept the
tag-level `limit` as a live control and argued it was "the only way to state a whole-list bound".
That is withdrawn — see §The grill, below, which is the authoritative record of what was decided.
Kept visible rather than overwritten, because supersession-in-place is the failure this plan's
§SETTLED index exists to catch, and this section superseded itself inside one day.

⚠ **Supersedes the POSITION Q8 and Q12 chose, not their reason.** Both said migration must write
the limit the old spelling implied; both put it at tag level. It goes on the steps.

**Reopened by the user on the shipped UI**, against a premise this plan had not stated: with one
fanning step — every tag in the surveyed corpus — the tag-level field and the step field are the
same knob. They diverge only when a chain fans twice, and the survey found no such chain. So the
panel showed two adjacent number boxes with the same units and the same visible effect.

**The rule** (`bws_fold_chain_apply_legacy_limit()`, slot-fold.php, + `applyLegacyLimit` in
slot-fold-grammar.js):

| legacy `limit` | result |
|---|---|
| absent | `limit(1)` on every fanning step; no tag-level key |
| explicit `N > 0` | `1` on every earlier fanning step, `N` on the LAST; tag-level key DELETED |
| explicit `0` / `-1` | nothing — unlimited is what chain wire already defaults to; the key stays as written |
| non-numeric | treated as absent (the `is_numeric` guard already gives it the default), but NOT consumed |

Four things a reader would otherwise re-derive:

1. **The earlier steps are not optional.** Per-step caps are per-input and multiply, so `N` on the
   last fanning step alone yields `N` per parent × every parent. `1` on the earlier ones bounds the
   product at `N`, which is what the flat cap meant.
2. **It is faithful for the absent case at any depth.** Product 1 picks first-of-first at each
   level — the same element terminal-1 takes out of the parent-major flat walk. Only an explicit
   `N > 1` over two fanning steps diverges (flat spills into parent 2 when parent 1 is short; this
   stops), which is §Residual holes, zero surveyed instances, and `limit` is used zero times in
   either database.
3. **This is NOT the withdrawn "materialize at COMPILE" row.** That ran at every read of legacy
   wire and mapped an authored `N` as `1`-on-earlier/`N`-on-last unconditionally. This runs once,
   at migration. Materializing an IMPLIED default is not relocating an authored value, so nothing
   an author stated changes kind — and the one authored value that does move (`N` → last step)
   moves because the user chose that over leaving it behind (2026-08-06), against the
   recommendation to keep it tag-level.
4. **An argless fanning step gets nothing.** The compiler drops it, so the chain does not fan and a
   cap there is inert noise; the old tag rendered one value because its source resolved one entity.

**Reach.** `bws_fold_migrate_base_src()` + its JS twin `baseSrcState()`, `bws_nxm_migrate_chain()`
(the 25 N×M entries — both families are two-fanning-step chains, so both steps carry a cap), and
`convertUpdate()` in the base chain control. Pinned by `fold-migration-test.php` §M9 (behaviour;
the M7 twin proves the two languages agree and carries no expectations by design),
`related-post-src-migration-test.php` §R6 (the N×M wire), `editor-filter-chain-test.js` (the
conversion), matrix rows L4.10 / L5.2 / L5.8 / F14.13. Mutation-verified: dropping the earlier-step
cap fails six checks, the twin among them.

**Unchanged by this.** The era rule (`bws_limit_default`) still selects the unset default and is
still the only thing covering wire no migration reaches.

⚠ Two claims that stood here for a few hours on 2026-08-06 and did NOT survive the grill: *"the
slot half still needs nothing"* (reversed — see §The grill, D4) and *"the tag-level control still
renders under chain wire"* (reversed — D1).

---

## The grill — 2026-08-06

Six decisions, all the user's, taken in one session against the built UI. **This is the
authoritative record**; every section above it that disagrees is superseded, and the ticket bodies
(#59–#64) are the executable form. The reasoning is kept because several of these reversed an
earlier answer *in the same session*, and a reader who finds only the outcome will re-open them.

**The unifying rule, which is what the ADR is named for: A LIMIT IS STATED WHERE THE SOURCE IS
STATED.** A chain states its source as steps, so limits go on steps. A flat-select family states
its source as one step, so its limit is the tag-level key. That is why `{{text}}` losing the
control is not an inconsistency — its source stopped being tag-level. ~~and why `term_` gaining one
is the same rule, not an exception.~~ **The `term_` clause is withdrawn (2026-08-07): `term_` does
not state its source as one step — it states a singular root plus a fanning step, and the rule sends
the limit to the step. §D5's premise, corrected.** The rule itself is unaffected; it was the reading
of `term_` that was wrong.

| | decision | ticket |
|---|---|---|
| **D1** | **The tag-level `limit` CONTROL is retired** — unregistered (not gated) on the six chain-authoring base tags (`text`, `title`, `email`, `phone`, `datetime_single`, `datetime_range`) and on `try_`. The VALUE is still read everywhere: GB seeds state from the tag string rather than the registry, unmigrated flat wire has no other limit, and ADR 0004 makes hand-edited wire mean what it says. Gating it to flat wire was considered and rejected as unreachable — the mount migrator fires before the panel paints, so the only way to see a gated control is for the chain control to fail to mount | #62 |
| **D2** | **Migration relocates an explicit `limit:N` onto the LAST fanning step**, `1` on every earlier one, tag-level key deleted. POSITIONAL — `refs` takes the `N` when `refs` is last; the terms step is merely what "last" always is in legacy wire, since `srcTermIn` follows `ref` | #59 |
| **D3** | **`try_`'s tag-level `limit` is pushed into its slots** — into each FANNING slot's chain, aimed by D2 within the slot. Faithful rather than approximate: the number genuinely WAS each slot's default, so copying it preserves the semantics exactly | #61 |
| **D4** | **Chain wire defaults uncapped EVERYWHERE, slots included.** The `1` exists only as the flat era's implied default — materialized into steps at migration, or supplied at read time by `bws_limit_default()` for wire migration cannot reach. **REVERSES Q8's "the slot half needs nothing"** | #60 |
| **D5** | ~~**`term_` gains `limit` + `sep`**~~ on the tags whose base twin has them, gated on the flat fanning predicate. Purely additive — the unset default there is already 1 | ⚠ **REOPENED 2026-08-07** — premise false, see §D5's premise, corrected. #63 re-files |
| **D6** | **One ADR**, `0005-limits-are-stated-where-the-source-is-stated.md`, with the era rule as its mechanism rather than as a second ADR | #64 |
| **D7** | **VOCABULARY: "cap" is not a noun in this codebase — it is "limit".** Per-step limit / tag-level limit / total limit. Every affected identifier was 1.17.0-unreleased, so the rename costs nothing | #59 |

**Why D1 rather than keeping a whole-list bound.** The tag-level limit fails in both directions and
is never useful: with ONE fanning step it is redundant (the same knob as that step's own limit),
and with TWO it is arbitrary — it slices the flattened walk at a position set by fan-out widths the
author cannot see, parent-major only because `bws_run_traversal` happens to iterate that way. That
is the identical objection §TOTAL was rejected for. A genuine "at most N overall" remains a future
DESIGNED feature; it is not this key.

**Why D4 is free, and only now.** Making slots honour their own spelling changes what a folded slot
renders. The survey found ZERO capital-letter slot keys in either database and 1.17.0 is
unreleased, so no folded wire exists anywhere — nothing to re-migrate. After release it would be an
output change on live pages.

**What D4 fixes beyond consistency.** The step Limit control's `0 (all)` placeholder was lying
inside a slot: blank meant "all" on a base tag and "1" in a slot, same control, same placeholder.
The cause was structural — the dispatch reads its default off the FLATTENED triple
(`$slot_opts['src']`), which cannot see the slot's real spelling. Measured before the fix:
`{{text src:terms,department|use:title}}` → `Sales, Support`; `{{try_text
A:src(terms,department);use(title)}}` → `Sales`.

**Two mappings examined and rejected for D2**, both recorded because each looked right for a while:
`N` on the FIRST fanning step (proposed by the user, then withdrawn — the first step has one input
so per-input and total coincide there, which is elegant, but it restates the author's number rather
than preserving what the tag did); and leaving an explicit `N` at tag level untouched (proposed by
me on the grounds that the tag-level limit is spelling-agnostic in the reader, so output is
preserved with no step limits at all — true, but it keeps the incoherent object alive on exactly
the tags that have one).

**One factual correction the grill produced**, worth keeping because it inverted an argument: I
asserted that `{{term_text}}` had a Result Limit and `{{text}}` would lose one. The registered
arrays say the opposite — `text` has `limit`+`sep`, `term_text` has neither. ~~So `term_` tags could
already carry a fanning source with no way to ask for more than one result, which is what D5 fixes
and what makes D1 read as a rule rather than a removal.~~ **The second sentence does not follow from
the first — see the section below.**

---

## D5's premise, corrected — 2026-08-07

D5 was derived from the registered option ARRAYS and never from the render path. The arrays are
reported correctly above: `term_text` registers no `limit` and no `sep`. The inference drawn from
that — *"so a `term_` tag with a fanning source silently returns one result for want of a control"* —
is false, and it is false one layer below where the survey looked.

**`limit` and `sep` are honoured NOWHERE on `term_`.** Their only two render readers are
`bws_resolve_field_values()` and `bws_collect_value_list()` (`field-helpers.php`). The `term_`
constructor calls neither. `make_modifier_callback()` hands a SINGLE entity id to `term_fn`/`post_fn`,
and those cores read one field off one entity and return a string — `bws_term_custom_text_core()`
(`taxonomy-tags.php`), `bws_post_custom_text_core()` (`content-tags.php`). Neither ever reads either
key. Registering the pair as filed would ship two dead controls, and #63's own acceptance criterion
*"`limit:0` returns the full list"* could not pass.

**The root is singular, always.** `TaxonomyTerm::resolve_id()` returns GB's picked term id or the
ambient detection's — one term, whichever arm of the entity picker the author uses.

**Where a fan does exist, the ARM discards it — it is not bounded at 1, it is collapsed at 1:**

- `src:ref` — `bws_run_traversal()` returns the plural post list, then
  `bws_first_post_id_from_sources()` keeps `reset()` and drops the rest.
- `srcTermIn` — the dispatch loops the terms and returns on the first non-empty read.

Both are unconditional. No option value reaches either.

**What this does to the rule.** *A limit is stated where the source is stated* is untouched, and
applying it honestly gives the opposite answer to D5: a `term_` tag states a singular root plus at
most one fanning STEP, so the limit belongs on that step, exactly as it does on every other `refs`
step. There is no whole-tag quantity for a tag-level key to name. D5 read `term_` as a flat-select
family whose single step IS the tag, which is true of the SOURCE SELECTOR and not of the resolution
— the root and the step are two things there, and only the second one fans.

**So #63 is a renderer ticket, not a registration ticket**, and it is not independent of the rest:
the collapse lives in the same container arms that gate on flat `src`/`srcTermIn` tokens instead of
dispatching by terminal-step kind, i.e. §Arm dispatch, sized. Either that lands and `term_`
gets step limits with it, or `term_` acquires the chain control. Both are out of scope for a ticket
whose stated blocker list is *"None — can start immediately."*

**Which arm-dispatch row (2026-08-11).** FW-63 CLOSED 2026-08-05 and it is not the one — it swapped
the **base** callbacks onto `bws_fold_chain_resolution()` / `bws_fold_src_resolution()` and stopped
there. What still collapses `term_`'s fan is a level down: `bws_fold_slot_flat_options()` flattens a
slot's chain back to a flat `src`/`ref`/`srcTermIn` triple for the container arms, and those arms are
the ones that `reset()` under `src:ref` and return on the first non-empty `srcTermIn` read. **FW-43**
owns exactly that — arms dispatch by terminal-step kind, the `$skip_reason:'chain'` branches go, each
container's `steps` list widens — so #63 is blocked by FW-43, and its issue body now says so. A
closed blocker is not a cleared one; that is the whole reason this paragraph exists.

**Method note, which is the transferable part.** D5 was decided from a registration survey — the
same instrument that produced the (correct) factual correction directly above it. A registration
survey can prove a control is ABSENT. It cannot prove what the absence costs, because that lives in
the callback. The grill's other five decisions all concerned wire the readers demonstrably read;
this one concerned wire nothing reads, and the difference was invisible from the arrays.

---

## Two surfaces, one rule

⚠ **This section was rewritten 2026-08-05 after a user correction: *"we are NOT deferring base tag src
chain migration."*** An earlier draft of it recorded base-tag depth-0 migration as permanently
unregistered and built a deliberate slot-vs-base asymmetry on top of that. Both are withdrawn. Kept
visible rather than silently overwritten, because the master plan's §SETTLED practice exists to catch
exactly this shape of supersession-in-place.

**Base-tag depth-0 src migration is registered and lands this release**, beside the slot fold's.

The decisive constraint on anything that rewrites: **every migration path must write identical output.**
A divergence does not surface as one path being wrong — it surfaces as one tag stored two ways depending
on which path found it first. The scanner runs with no author present, so **a warning cannot substitute
for serialization; it can only accompany it.**

| surface | migration | cap handling |
|---|---|---|
| **slots** (`try_*`, `join`) | built, unreleased 1.17.0 — scanner + mount | serializes `limit[1]` on slots with a fanning step |
| **base tags** (depth-0 `src`) | **this release** — new registration, scanner + mount | serializes `limit:1` on tags with a fanning step |

**Q8 — decided: serialize, on both surfaces.** Re-based 2026-08-05 with the era marker: migration
changes the SPELLING, the spelling selects the terminal default, so migration must write the default it
is leaving behind. Five points that fix the shape of the edit:

- **It is ONE tag-level `limit`, not per-step caps.** The step-materialization draft is withdrawn
  (§The spelling is the era marker); what a migrated tag needs is the terminal cap its old spelling
  implied.
- **Only where there is a fanning step.** A source with no step has nothing to cap — the source list has
  one element and the cap is a no-op — so a `limit` there would be noise a reader must decide is
  meaningless.
- **Every path writes it, identically.** Scanner and mount produce byte-identical output or the same tag
  is stored two ways — `fold-migration-test.php` §M7's twin block is what proves it, so the cases belong
  in the shared corpus (`fold-migration-corpus.json`), where key ORDER is half the property. The base-tag
  entry needs its own corpus coverage, not a borrowed slot case.
- **Slot half lands in `bws_fold_from_flat()`** (`slot-fold.php:902`), which today emits a limit only
  when the legacy slot carried one; this adds the unset case. Mount half mirrors it in
  `slot-fold-migrate.js`. **Base half is a new `type:'option'` entry** in
  `bws_register_option_migrations()` plus its mount counterpart.
- **No re-migration.** The slot edit touches unreleased 1.17.0 code, and the survey found zero folded
  slot keys in either database — nothing has been migrated by the shipped rule yet.

**Why serialize rather than warn.** An unmigrated flat source defaults to 1; the same source respelled as
chain defaults to uncapped. So a migration that writes nothing silently fans out exactly the tags it
touches — extra values, dropped anchors (the link gate is count-based), on live pages. A warning cannot
cover it: the scanner has no author to warn. Serializing keeps migration a pure rewrite with no output
delta — the equivalence property the harness already asserts.

### The era marker is the safety net, not the substitute

Registering base migration does **not** retire §The spelling is the era marker — it demotes it from
*the* answer to the cover for wire no migration can reach. Both are needed, and they are complementary
in the same way the scanner and the mount migrator are:

| reaches | scanner | mount | compile-time era rule |
|---|---|---|---|
| a draft nobody opens | ✅ | ✗ | ✅ |
| a block widget | ✗ | ✅ | ✅ |
| ACF meta holding a tag (SITE-A `philosophy_overview`, posts 76064/76694/78062) | ✗ | ✗ | **✅ — only this** |

**One mapping, two consumers.** The migrator writes what the compiler's legacy branch already computes
(§The spelling is the era marker, the three-row table). That was recorded as consequence 2 of the era
rule; with base migration registered it becomes load-bearing rather than incidental — a divergence
between them means a tag renders differently before and after it is migrated, which is the one thing
migration must never do.

**Authoring is still separable from migration**, and the distinction still matters even though both now
land together (user, mid-grill: *"I started this as a preliminary for src chain encoding on the base
tags. we don't have another way to encode multi-step relationships."*). Authoring is what makes chains
expressible; migration is what moves stored wire onto them. They fail differently and are tested
differently — authoring by the matrix's flat-vs-chain equivalence rows, migration by the corpus twin.

**FW-63 hardens from a gate on authoring to a gate on migration.** Migrating flat→chain puts *every*
stored base tag through chain arms at once, on pages nobody opened. Under the old scoping a broken arm
affected only tags an author converted by hand; now it affects the whole corpus on upgrade. The arm
refactor must land, and its matrix coverage must be complete, **before** the migration entry registers —
they cannot go in the same commit in either order without a window where one is live and the other is
not.

**Base-tag chain AUTHORING is separable from base-tag MIGRATION**, and conflating them is what produced
a wrong scope answer mid-grill (user: *"I started this as a preliminary for src chain encoding on the
base tags. we don't have another way to encode multi-step relationships."*). A base tag can author chain
wire with zero existing tags migrated: new and edited tags get chain wire, old tags keep flat wire, the
compiler reads both.

**Author-initiated conversion writes the cap too, and SHOWS it** (user, 2026-08-05, Q12). A base tag
becomes chain wire when an author uses a chain control on it — the one rewrite that is genuinely
reviewed. An earlier draft of this paragraph gave that case inline help INSTEAD of serialization; that
was written when conversion changed nothing about the cap, and is withdrawn now that it flips the tag's
default under the author's hands.

On commit the source control serializes `limit:1` (same rule as migration: *changing the spelling writes
the default you are leaving*) **and** leaves the `limit` control populated with a visible `1` the author
can clear to get the list.

Two reasons this beats help-text-only:

- **One stored shape.** Scanner, mount and hand-conversion all produce the identical tag. Help text
  produces a third variant on any author who does not act on it, and dismissible guidance is exactly the
  mechanism this plan rejected for the scanner.
- **The change becomes visible rather than silent.** The author watches the number arrive in a field
  they own, which is wire evidence — the property §The spelling is the era marker pays an ADR-0004 cost
  to preserve everywhere else.

**Cost, stated:** the source control writes a DIFFERENT option's key on commit. That is the
"two controls, one key" coupling `docs/editor-controls.md` is the reserved owner of; `bws-as-size` is the
shipped precedent, so it is a known shape rather than a new mechanism.

Net effect on the ~110 authored instances the survey found: **zero base-tag rewrites, zero base-tag
`limit` tokens.** Only fanning slots pick up an explicit `limit[1]`, and those are being rewritten by
the fold regardless.

---

## Site survey — SITE-B + SITE-A (2026-08-04)

Agent review of both local clones, `post_content` + `postmeta` + `options`. 6878 raw `{{` occurrences,
6878 regex matches, 0 mismatches.

**1. `limit` is used ZERO times.** Both sites, all three locations, tag-level and slot-prefixed.
Verified two independent ways (string scan of every extracted tag; direct `LIKE '%limit:%'` and
`REGEXP '[0-9]+-limit'` probes). SITE-A's 9 `limit:` hits are all third-party widget JS config and
prose. **So no stored value's meaning can change — the entire exposure is what an unset `limit` does.**

**2. The ref + `srcTermIn` combination exists once, and not in the shape that mattered.** One string,
SITE-A only, 2 live authored instances (one `gp_elements`, one `wp_block`) + 1 patterns-tree
mirror + 3 revisions:

```
{{join srcTermIn:event-location-type|2-src:ref|2-ref:location_cpt|2-use:title|use:title|sep:: }}
```

The two fanning options are in **different slots** — `srcTermIn` is slot 1's read, `2-src:ref` is slot
2's. **No single chain in either database carries two fanning steps.** So the one shape with no faithful
per-input mapping has zero instances; it survives only as a hand-authored possibility and wants a
fixture, not a mechanism.

**3. Blast radius.** 1084 stored occurrences carry a fanning source with no `limit`; 940 are revisions,
and many of the rest are `generateblocks_patterns_tree` mirrors of the same `wp_block`. Deduped:
**~110 authored instances across ~10 container posts** — 5 on SITE-B (`wp_block` 49882/49765/49955/49331,
one `gp_elements`), 6 on SITE-A (two `gp_elements`, one `page`, three `wp_block`).

**4. Both databases are entirely pre-fold.** Zero capital-letter slot keys. Corroborated independently:
every stored `{{join … mode:template}}` uses **digit** format tokens (`%1 / %2 / %3′%4″ / %5 lbs.`),
never the `%A` alphabet — which also confirms the 5i `%%`-escape migration gates correctly on wire era.

**5. `srcTermIn` is SITE-A-only** — 5 distinct strings, 94 occurrences; SITE-B has zero. Legacy
digit-prefixed slot keys are the only slot form in use (SITE-B 19 distinct, SITE-A 27; indices `2-`
through `5-`, axes `src`/`ref`/`use`/`key`; no `N-limit` anywhere).

**Incidental finding, NOT part of this plan — needs its own check.** Two live SITE-B tags name the
relationship through `key:` rather than `ref:`:

```
{{image src:ref|key:benefit_vendor|required:false|as:alt|use:featured}}
{{image src:ref|key:benefit_vendor|as:url|use:featured}}
```

`bws_fold_chain_from_options()` reads `$options['ref']` only (`slot-fold-compile.php:210`), so under the
compiler this is an argless fanning step and is **dropped**, falling back to the ambient entity. Whether
that matches pre-compiler behaviour is unverified. **If it does not, it is a live 5h regression on real
stored wire**, independent of everything this plan decides.

✅ **RESOLVED 2026-08-11 — by #74, and the question turned out not to need answering.** The DROP is
gone: `bws_fold_chain_to_steps()` now keeps an argument-less fanning step and loses only its argument,
so the engine's reader answers empty, the chain short-circuits, and the tag renders nothing. Dropping
the step left a chain with NO steps, and a chain with no steps resolves the ambient entity — a
plausible value from the wrong entity, which is strictly worse than an empty one because nothing
looks broken. So "does the drop match pre-compiler behaviour" is moot; the answer is that it did
match (both read the ambient post) and both were wrong.

**Live consequence for the two SITE-B tags above, which is NOT covered by the `related_post` key→ref
copy** (that entry is gated on the `related_post` src token and these are `src:ref`): they will render
NOTHING after this release instead of the current post's featured image. Deliberate and disclosed —
CHANGELOG 1.17.0 carries the author-facing warning *"If a spot on your site goes blank after this
update, open that tag and finish its source: it was reading something it was never pointed at."* The
fix on those sites is `key:benefit_vendor` → `ref:benefit_vendor`.

---

## Arm dispatch, sized — FW-63

The gate on base-tag chain authoring is not migration, it is **arm dispatch**. Measured live (5h, and
re-confirmed here): a base tag carrying chain wire renders a **different value**, not an empty one.

```
{{text srcTermIn:department|use:title|limit:0}}  => Sales, Support
{{text src:terms,department|use:title|limit:0}}  => Matrix: Terms (all valid)   ← wrong arm, page title
{{text src:ref|ref:related_staff|key:name_last|limit:0}}  => Johnson, Smith
{{text src:refs,related_staff|key:name_last|limit:0}}     => Johnson            ← list mode not offered
```

**The sites, classified by what they actually test** — ≈19 on the render path across five files:

| pattern | sites | really asking |
|---|---|---|
| `'site' === $options['src']` | `base-shared.php:856,951`; `base-tags.php:658,1052,1126,1238,1286`; `datetime-tags.php:822` | where the chain **ROOTS** |
| `'ref' === $src` / `$is_ref` | `base-tags.php:688,1164`; `base-shared.php:856,951` | does it **fan** (⇒ offer list mode) |
| `sanitize_key( $options['srcTermIn'] )` | `base-shared.php:851,946`; `base-tags.php:655,1047,1120,1235,1283`; `datetime-tags.php:809,940` | does it **TERMINATE at a term** |

**The refactor is smaller than "rewrite dispatch": the arms conflate two axes the compiler has already
separated.** Replace two token tests with two chain queries —

- `bws_fold_src_root_token()` — **exists**, and `field-helpers.php:548` already uses it for exactly this
  reason (a `src:site;entries,rows` chain roots at site but terminates at a meta_row). Shipped precedent
  for the whole `site` column.
- a **terminal-kind query** — new. Answers `post | term | meta_row | site`, plus "does it fan". Covers
  the `ref` and `srcTermIn` columns.

Then ~19 mechanical swaps.

**Scope decision (user, 2026-08-05): lands in THIS release, together with base-tag chain authoring.**
There is no partial landing that delivers the capability — without arms, `src:terms,…` renders the wrong
value and `src:refs,…` silently loses list mode. Multi-step *ref* chains reading a single value would
work today, but the survey shows that shape is authored as `try_` slots, not chains, so it isn't the use
case.

**The condition attached: matrix coverage is the deliverable, not the swaps.** Every base family
(`text`, `title`, `permalink`, `content`, `image`, `email`, `phone`, `datetime_*`) needs a row asserting
flat-vs-chain equivalence per arm, on the testbed, **because a wrong arm renders a plausible value
rather than nothing**. A half-covered arm refactor is worse than deferring.

**What is and is not gated:**

| | needed for base-tag chain authoring? | state |
|---|---|---|
| chain wire + compiler + engine | yes | **built** (5h) |
| legacy cap rule in the compiler's flat branch | yes | ✅ built — landed as `bws_limit_default()`, which selects the DEFAULT rather than materializing caps (the materialization draft is withdrawn; see §The spelling is the era marker) |
| **arm dispatch refactor (FW-63)** | **yes — the only real gate** | ✅ built + matrix RUN on the testbed 2026-08-05 |
| base-tag chain control | yes | ✅ built — `bws-src-chain`, rendering the fold control's EXTRACTED step editor rather than a second copy of it |
| base depth-0 *migration* | separable, but lands **this release** | ✅ built, in the commit AFTER the matrix run. The gate held: the two could not share a window in either order, so the run is what released it |

---

## Deferred UX

Explicitly deferrable, and **deferring is not foreclosing** (user, 2026-08-05: *"if we defer conditional
display of limit/sep and/or per-step sep, that doesn't mean we never clean up the UX, either"*):

- **`sep` conditional display.** Registered with `show_if_any: { srcTermIn: 'not_empty', src: 'ref' }`
  (`base-tags.php:182-194`), a literal equality test chain wire defeats, so `sep` hides — wrong, since
  `sep` still joins a fanning chain's output. Fix whenever; row 91's precedent (*"a list axis inside a
  slot value has no honest `show_if` predicate"* ⇒ unconditional) is available if wanted.
  **Not architecture.**

⚠ **`limit` visibility is NO LONGER deferrable — moved into scope 2026-08-05 by Q12.** It carries the
identical predicate (`base-tags.php:81-92`) and hides under chain wire for the same accidental reason.
That read as the right outcome while chain wire had no cap of its own. Q12 requires the opposite: the
author must SEE the serialized `1` and be able to clear it, and a control that does not render cannot be
cleared. So the predicate must admit chain wire — which, since a chain's fanning-ness is a property of
the wire rather than a token comparison, is the same terminal-kind query FW-63 introduces, not a new
mechanism.
- **Per-step `sep` (FW-61).** Re-blocked here on a structural ground the tracker does not record: a sep
  joins printed values, a step produces sources, so a per-step sep only means something if output is a
  TREE (group by step-1 source, join inner, then outer) — and `bws_run_traversal` explicitly flattens
  (`$next[] = $produced`). It presupposes preserved fan-out structure the engine discards, which is
  FW-32 territory, not a control-shape question.

**NOT deferred: per-step `limit` gets a control on every fanning step.** Dropping the default is exactly
what makes that input the author's instrument rather than a hidden value. The invisible-but-preserved
state the spike shipped (`slot-fold-control.js` threads `step.limit` with no input; editor trial PFm2
flagged it as *"a standing hazard, not a solved problem"*) is what this closes.

---

## Per-step `limit` control — PROVISIONAL (2026-08-05)

**Held revisable by the user** (*"I want to interact with it before I decide this permanently"*), so it
stays in §OPEN. Build to this; expect to revisit after hands-on.

Scoped to **step** caps. The tag-level `limit` keeps its own separate control: terminal and last-step are
different quantities (whole-list vs per-input), so one field would misstate both.

**Decision (provisional): empty = uncapped; `0` is never serialized; placeholder reads `0 (all)`.**

- Nothing but the author writes step caps now, so the control is the only producer.
- Matches the `delete`-omit idiom the controls already use for defaults, and needs no change to the
  step emitter — the grammar already emits a step limit only when one exists (`slot-fold.php:902`).
- The engine agrees by construction: it reads `$step['limit']` directly and treats `>0` as a cap,
  anything else as none (`traversal-pipeline.php:92-93`), so absent and `0` are already one behaviour
  under two spellings. Serializing both would put a distinction on the wire that means nothing — an
  ADR-0004 cost paid for no reader benefit.

**`-1` is a synonym for `0` here** (user, 2026-08-05), at both positions and for the same reason — the
tests are `>0`, so anything else falls through to uncapped: `bws_clamp_limit` returns `$n > 0 ? $n : 0`
(`field-helpers.php:495`) and the engine slices only when `$step_limit > 0`
(`traversal-pipeline.php:92-93`). `-1` is the older spelling of unlimited (CLAUDE.md's `limit`-default
trigger pins both: *"L3 rows pin `0`/`-1` = unlimited"*). So the control normalizes `0` **and** `-1` to
absence, identically; `0` is the canonical spelling it teaches, `-1` stays parseable for hand-edited and
pre-existing wire. Neither is ever serialized.

**Placeholder is `0 (all)`, NOT "all"** (user). It names the value that produces the behaviour, in the
same vocabulary the tag-level `limit` help already uses for its `0` affordance — so the two fields teach
one rule instead of two. It names `0` rather than `-1` because a placeholder teaches one spelling.

It also **dissolves the hazard** that argued against this option. A hand-typed `0` normalizing away is
invisible: the field shows `0 (all)` as placeholder before the author types, and shows `0 (all)` again
after the `0` is dropped. Same glyphs, same meaning, no state silently lost — the author cannot tell the
difference, because there is none.

**What is NOT settled by this:** whether an empty box should *inherit* a visible value from somewhere
(it does not today), and whether the placeholder is enough to teach per-input semantics — `limit 1` on a
terms step means one term *per post*, which no placeholder states. Both are hands-on questions.

---

## Implementation list

Ordered. The first four are strictly sequenced — the rest can land beside them.

| # | Item | Gate | Landed 2026-08-05 |
|---|---|---|---|
| 1 | **FW-63 arm dispatch** — two chain queries (`bws_fold_src_root_token()` exists; resolved-source-kind query is new), then ~19 mechanical swaps. **Deliverable is the matrix coverage**, per base family, flat-vs-chain equivalence per arm | — | ✅ `bws_fold_chain_resolution()` / `bws_fold_src_resolution()`. Reads the chain as PARSED, not compiled — the compiler drops an argless fanning step, so dispatching off the compiled list sends `src:ref` with no `ref` down the ambient arm when the flat spelling has always sent it down the post arm. Mutation-verified. Matrix §F9 + §F9a (per family), RUN |
| 2 | **`bws_limit_default( array $options ): int`** + `bws_clamp_limit( $raw, int $default )` with `$default` REQUIRED. Update all five call sites; `bws_try_join_items` takes a resolved int and drops its internal clamp | — | ✅ Four clamp sites, not five: `bws_try_join_items` stopped being one |
| 3 | **Wire `refs` from a site root** — see below | — | ✅ Engine allowlist + reader `case 'site'`, one twin harness row each |
| 4 | **Base-tag chain authoring control** — reuses 5c's step repeater, bound to a flat `src` rather than a folded slot value | 1 | ✅ `bws-src-chain`. The repeater was EXTRACTED (`chainSteps`) rather than reused by copy, so both surfaces render one component |
| 5 | **Migration registration** — base depth-0 `type:'option'` entry + mount half; slot half adds the unset-`limit` case to `bws_fold_from_flat()`. Both surfaces serialize one tag-level `limit:1` where the source fans | 1, 4 | ✅ base half + mount half + `baseSrc` corpus twin. **The slot half was NOT needed** — see §SETTLED Q8. `bws_fold_from_flat()` untouched |
| 5b | **The 25 N×M entries that had no migration TARGET get one** — see below | 1, 4 | ✅ Both families, `bws_nxm_chain_target()` deriving the tag from the SUFFIX. An incomplete chain leaves the tag byte-identical rather than half-converting it |
| 6 | **Author-conversion path** — source control writes `limit:1` on flat→chain commit AND leaves the `limit` control populated with a visible `1` (Q12) | 4 | ✅ `convertUpdate()`, asserted at the pure function — the decision is not observable in what the control renders |
| 7 | **`limit` control visibility under chain wire** — its `show_if_any` predicate is a literal token test chain wire defeats; needed for 6 to be true. Uses 1's kind query | 1, 6 | ✅ `chain_fans` predicate. Array condition ENTRIES became full conditions, which is what lets one key carry `[ 'ref', 'chain_fans' ]` — `show_if_any` is keyed by option, so two rules about `src` cannot be two entries |
| 8 | **Per-step `limit` input** on every fanning step (§Per-step `limit` control — provisional) | 4 | ✅ Built to the provisional shape; the row stays OPEN pending hands-on |
| 9 | **Vocabulary renames** — the eight identifiers in [`src-chain-encoding.md` §VOCABULARY](../src-chain-encoding.md), plus the `bws-term-hop` control-type decision | — | ✅ **V1–V9 all done.** V9 **BUILT 2026-08-07 (#69)** as its own commit, `BWS_FOLD_STEP_TYPES` narrowed to `slug => arg-key`; the §SETTLED index has carried that since, while this cell still read "V1–V8 done, V9 NOT" — supersession in place, in a table the index does not cover (corrected 2026-08-18). `bws-term-hop` moved to tracker row FW-67. `$link` → `$step` surfaced a live trap: the loop variable and the value it compiles to would both have been `$step`, making the per-step `limit` read the ENGINE step (always absent) instead of the wire's, dropping every cap. Now `$step` / `$engine_step` |
| 10 | **Harness + corpus** — `fold-migration-corpus.json` gains base-tag cases; `limit-clamp-test.php` covers the two-arg signature; matrices per §Arm dispatch | with each | ✅ No new harness files, as specified. `slot-fold-corpus.json` also gained a `srcOptions` section — the base chain control made `bws_fold_chain_from_options` a TWINNED rule for the first time, and the twin found a `''`-vs-`null` divergence on its first run |

### Item 3 — `refs` from a site root (added 2026-08-05, user)

**Found while fixing a doc claim** (`editor-tag-previews.md:44` said `src:site` *"never combines"* with a
`refs`/`terms` step because *"site has no entity"*). That reason is wrong for `refs`, and the code
already said so: `base-shared.php:155` suppresses `src:ref` for site in Stage A and records it as
*"not 'never applies'"*. An ACF options page holds relationship fields like any other field store.

**The asymmetry is in one allowlist.** `bws_run_step()`'s `case 'ref'` accepts
`post|term|user|meta_row` and returns `array()` for site, while `case 'rows'` four lines later
DELIBERATELY accepts site — *"a site-option repeater is read directly by the reader's `'option'`
selector"*. So the engine already reads a **repeater** out of the options store and refuses a
**relationship** out of the same store.

**Why it lands now rather than staying a comment:** base-tag chain authoring (item 4) makes
`src:site;refs,x` AUTHORABLE for the first time. The suppression at `base-shared.php:155` keeps
`src:ref` off the site dropdown today, so the gap is unreachable; a chain control that offers steps
after a site root makes it reachable, and it fails SILENTLY (empty chain, no warning). Safe failure,
invisible failure.

**Scope:** one kind in the allowlist, one `case 'site':` in the reader's `ref` switch using the same
`'option'` selector `rows` uses. Rejected alternative: gate `refs` out of the control after a site root
— that puts a rule in the editor the engine does not share, which is the shape of the arm-dispatch
problem this release is fixing.

**Doc consequence:** `tag-reference.md:120`'s *"no site→ref wiring in Stage A — tracked as a future
enhancement"* becomes stale on landing, as does the `base-shared.php:155` comment.

### Item 5b — the 25 N×M entries that had no migration target (added 2026-08-05, user)

**The blocker was named in the code, and this release dissolves it.**
`bws_register_v1_deprecated_tag_wrappers()` carries it as an `@invariant`:

> `second_related_post_*` (15) and `post_term_related_post_*` (10) — **no current tag reaches a
> second-step relationship or a term-then-relationship chain**, so these carry `old_tag`+`since` only,
> no `new_tag`.

Both shapes are exactly what chain wire states:

| family | count | why it had no target | target |
|---|---|---|---|
| `second_related_post_*` | 15 | two relationship steps | `src:refs,<ref1>;refs,<ref2>` |
| `post_term_related_post_*` | 10 | term, then relationship | `src:terms,<tax>;refs,<ref>` |

`term_related_post_*` is **unaffected** — those already carry a `new_tag`.

**This is the enduring rule paying off**, and it should be recorded as such: *never delete a
`register()` call just for lacking migration data* (memory
`project_deprecated_tags_no_migration_path`). The rows were kept in 1.14.0 precisely so a later
release could give them targets — after being dropped once by mistake (SPEC B1), which silently
emptied the settings page's "no migration path" list and starved the Tag Converter of ~25 entries.

**Why it is cheap here:**

- **Regression risk is one-directional.** The renderers were stripped in 1.14.0, so these tags produce
  nothing today. Migration moves them from broken to correct; there is no working output to break.
- **Declarative.** Not a key rename — two option values compose into one chain value — but
  `MigrationRegistry` already supports `transform_callback` for exactly this, and the shape is
  mechanical.
- **No new surface.** The entries exist and the converter already lists them as "no migration path";
  they move to convertible.

**Two things it does NOT share with the rest of the release:**

1. **It is a VISIBLE change.** Everything else here is deliberately invisible — same output, different
   spelling. This one makes a blank spot start printing content. CHANGELOG-worthy, and the one
   candidate in this release for an `== Upgrade Notice ==` line.
2. **Live instances are unknown.** Not surveyed (user, 2026-08-05: *"No survey"*). Announce it as a
   capability restored rather than as a fix for a known population.

**Sequencing:** same gate as item 5 — the target is base-tag chain wire, so arms and authoring land
first. Needs its own matrix rows; the old N×M option shapes are the input, the chain wire is the
expectation.

---

## Residual holes

- **Two-step chain with an explicit `limit:N > 1`** has no faithful per-input mapping — today it is
  *first N of the parent-major flat walk*, which spills from parent 1 into parent 2 when parent 1 is
  short; `limit(1);limit(N)` reproduces it only when parent 1 has ≥ N children. **Zero instances in the
  surveyed corpus.** Wants a fixture and a stated divergence, not a mechanism.
- ~~**Mixed wire**~~ — chain wire in `src` beside a flat `limit`. **RESOLVED 2026-08-05, by the era
  marker re-basing.** It is not an anomaly: it is what every migrated or converted tag looks like. The
  explicit value beats the spelling-selected default, which is ordinary option precedence and needs no
  additional rule.
- **Unsurveyed sites.** The survey covers two clones. Other installs may hold shapes neither has —
  notably a single chain with two fanning steps, which is the one shape with no faithful mapping.

---

## Corrections this plan makes to `src-chain-encoding.md`

Recorded here because the master plan's §Per-step `limit` sections are now partly superseded in place —
the exact failure mode its own §SETTLED practice exists to catch.

| master claim | status |
|---|---|
| row 113 — *"per-step `limit` caps the STEP'S WHOLE OUTPUT"* | **SUPERSEDED** → per-input |
| row 72 — *"`{{table}}` terminal step defaults UNLIMITED; caller materializes `limit => 0`"* | **SUPERSEDED** → table authors chain wire; its rows step is uncapped by default and the caller stops materializing |
| row 77 rationale — *"that case caps a multi-value READ"* | **WRONG PREMISE** (read is 1:1). Behaviour unaffected; do not reason from the stated reason |
| row 116 — *"base depth-0 MIGRATION still held"* | **CHANGED** → registered and landing this release (user, 2026-08-05), gated on FW-63 completing first. NB an intermediate draft of THIS plan had it permanently unregistered; that draft is withdrawn |
| OPEN row 144 — *"base-tag ARMS gate on flat `src`/`srcTermIn` tokens … NOT started, NOT part of step 6"* | **CLOSED as open** → filed FW-63, lands this release |
| §Default is TAG-CONTEXTUAL — *"LAST src-chain step defaults 0/all, preceding steps default 1"* | **SUPERSEDED.** Written in the vocabulary of the cut read-as-terminal-step model; per-step defaults are uncapped and the legacy cap is compile-time |
| §Default's ⚠ OPEN — *"'last step' is POSITIONAL, so growing a chain re-assigns the terminal"* | **DISSOLVED** — nothing positional survives; neither dynamic nor materialize-on-append is needed |
| unchanged | rows 37, 38 (bracket-kv form, depth alternation), 69–71 (`0` = unlimited, parse `0`+`-1`, `is_numeric` guard) |

### Corrections the BUILD makes to THIS plan (2026-08-05)

Kept as a separate table, and separate from §SETTLED's inline notes, because these were not
re-decisions — they are places the plan's reasoning was sound and its premise was not. Each was
found by running something, never by re-reading.

| this plan's claim | status |
|---|---|
| §Two surfaces, one rule / Q8 — *"serialize `limit[1]` … slots **and** base tags"* | **HALF WRONG** → base tags only. `bws_fold_slot_flat_options()` collapses a folded slot's chain back to a flat triple before any container arm resolves a limit, so folding a slot cannot change its cap. Q8 reasoned from the wire the author sees; the seam re-spells it before the default is chosen. Measured, both containers, both axes |
| §Arm dispatch, sized — *"a NEW resolved-source-kind query … `post\|term\|meta_row\|site`"* | **ONE KIND SHORT.** A root-only chain rooted at the ambient entity or a registry source has a kind nothing static can name — the FACTORY decides it at render. `'base'` is that answer, and without it the ambient term/user gates have no predicate. The plan half-saw this (*"kind is a property of the CHAIN … a root-only chain has one"*) and then listed four kinds |
| §Deferred UX — *"`sep` conditional display … fix whenever"* | **STILL TRUE, and now cheaper than stated.** `sep` carries the identical predicate to `limit` and was fixed in the same edit, because the reveal rule is registered once and both options read it. The row's judgement (not architecture) stands; its cost estimate does not |
| §Residual holes — the two-step-chain-with-explicit-`limit` hole | **UNCHANGED, but it acquired a SIBLING the plan did not predict.** A `refs` step followed by a `terms` step now fans where the arm used to collapse to the first ref'd post — reachable only with an explicit `limit` above one, same as the recorded hole, same zero surveyed instances. Stated as `fold-test-matrix.md` §F9.6 rather than fixed |

---

## ADR candidate — HELD as a plan deliverable (2026-08-05)

Offered and **held, not written** (user). Recorded here so the case does not have to be rebuilt; decide
at build time, when the shipped shape is known and the ADR can describe what happened rather than what
was intended.

**Candidate:** `docs/adr/0005-flat-src-spelling-is-the-limit-era-marker.md`

**Scope — the era-marker decision ONLY**, not the whole plan. Per-input semantics and uncapped defaults
are ordinary design that `tag-reference.md` and PHPDoc can carry; the era marker is the one piece a
future reader will trip over.

**It clears all three bars** (`domain-modeling` §Offer ADRs sparingly):

1. **Hard to reverse** — it is wire semantics. Once `src:ref|ref:x` is defined to default to 1 while
   `src:refs,x` defaults to uncapped, every stored tag on every site depends on that reading, and no
   migration exists to change it later (which is the entire point of the decision).
2. **Surprising without context** — *"why does `src:ref|ref:x` cap at 1 but `src:refs,x` not, when they
   name the same source?"* is exactly the question a reader asks, and nothing in the code answers it.
   A required `$default` param whose value comes from a spelling test will look like a bug to anyone who
   has not read this plan.
3. **A real trade-off with genuine alternatives** — forced upgrade-time migration (rejected: the
   scanner reads `post_content` only, and the survey found live ACF-meta wire it structurally cannot
   reach); materialize-at-migration (rejected: protects only what a migration reaches); accept the
   change with an Upgrade Notice (rejected: ~110 authored instances, silent extra output on live pages,
   and the `srcTermIn` cases return an arbitrary term). The chosen option pays a stated ADR-0004
   readability cost — the same conceptual chain caps differently by spelling — deliberately.

**What it should NOT re-litigate:** ADR 0004 (wire readability) is the constraint this one spends
against, not a party to it. Link, don't restate.

## Vocabulary

⚠ **Rewritten 2026-08-05 by the terminology pass** —
[`src-chain-encoding.md` §VOCABULARY](../src-chain-encoding.md) is the owner of the cross-cutting terms
(V1–V9) and wins on any conflict. Two of this section's three original entries were coinages over terms
§Language already owned, and are gone: *"step cap"* died with `step` (V1), *"terminal cap"* died with
`terminal` (V5). What is left is limit-specific:

- **Step `limit`** — a per-step cap, **PER-INPUT**: at most N resolved sources per input source. Caps
  `ResolvedSource[]` mid-chain. Defaults to uncapped. Only a **fanning** step can carry one.
- **Tag-level `limit`** — caps the final `ResolvedSource[]` before the read. Applies to **both
  spellings** — `field-helpers.php:567` never inspects `src`. Its DEFAULT is what the spelling selects:
  1 on flat wire, uncapped on chain wire. *(An earlier draft said "legacy spelling only" — factually
  wrong, and the error that made this position look untouched.)*
- **Total limit** — "at most N overall", inexpressible with per-step limits (product semantics). Not
  built; the only name here still worth reserving. It is NOT the retired tag-level key — that key
  bounded the flattened walk at a position set by fan-out widths the author could not see, which is
  why it went (D1); a real total limit would be a designed feature.

⚠ **"cap" is not a noun in this codebase (D7, 2026-08-06).** Say limit. The word reached identifiers
only in 1.17.0-unreleased code and is renamed out (#59); if it reappears in a docblock, that
docblock predates this line or was written without it.

**Filename:** ✅ DONE 2026-08-05 — renamed with the V1 retirement, and CLAUDE.md's temporary trigger
row moved with it (it points at this plan by name).

**One correction owed to `CONTEXT.md` §Language on ship — ✅ DONE 2026-08-05**, and scoped further
than this paragraph asked: the parenthetical now names the tag-level `limit` default as what actually
survives of the defect, since that is the piece the era rule had to work around.

The original note read *"(Today `ref` is collapsed to the first by `bws_extract_post_id` — a latent
single-read defect the plural model exposes.)"*, which is true only of the legacy source-class path
(`RelatedPost::resolve_id`, `class-related-post.php:80`). The **engine's `ref` step has never
collapsed** (SPEC §V6 plural fix), and the flat assemblers retired behind the compiler in 5h.
