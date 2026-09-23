# Archive: the try_ → base renderer merge — FW-136 (SHIPPED 1.21.0)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site (`bws_try_run_attempts()` in `includes/helpers/try-slot-loop.php`, and each family's `bws_base_<tpl>_resolve_value()`).

**Provenance.** The build spec for FW-136, decided by grilling on 2026-09-22 and built across eleven tickets on the `fw-136-try-base-renderer-merge` branch, 2026-09-22 to 2026-09-23. It lived at `.scratch/try-base-renderer-merge/spec.md` and was committed whole the day its last ticket (11, the release-gate snapshot sweep) passed, before the branch merged, because a plan commits when it is finished rather than when it ships; the PR that merges that branch is its published form. Its three §What the build changed amendments were written into the live spec during the build and stand as written. Its eleven ticket files stayed private and were not committed; each landed flip's measurements are in its commit body and in the FW-136 ledger row's predecessor, the tracker item's Progress line, which `git log -S'FW-136' -- docs/future-work.md` finds.

---

**What this is.** Spec for the in-flight work that makes a `try_` tag a loop over the BASE resolve seam instead of a second renderer. Detail home for FW-136 (`docs/future-work.md`). Decided by grilling on 2026-09-22; every decision below is settled, not proposed.

## Problem

Base and `try_` are not two configurations of one renderer. They are two renderers.

- Base: `bws_base_text_resolve_value()` (`base-tags.php`) → per-kind branch → `bws_collect_value_list()` (the combining fold, where FW-85 put per-item link wrap) → `{value, link_id, link_type}`. The callback is a thin shell: link-wrap the singular arms, preview label, fallback.
- `try_`: a ~350-line anonymous closure inside `TagTemplateRegistry::generate_base_try_tags()` that re-does arm dispatch, ids selection and emit itself, through `bws_read_bounded_sources()` + `bws_try_join_items()` + its own single-result link gate, over ~40 per-family `try_*_fn` per-entity renderers.

Every base/`try_` parity defect is the second renderer failing to inherit something the first got. FW-135 is the current instance, FW-84 was one, FW-92 is the editor-side twin. The base code states the split outright at `base-tags.php:762`: *"Closing it HERE closes it for every ABSORB-seam reader — which is `{{join}}`'s slots, and NOT try_text: a try_ slot runs its own dispatcher."*

**The merge closes the drift CLASS, not just its instances.** That is the reason for it. FW-135, FW-107 and FW-43's open residue dissolve as side effects.

## The seam

Per family: `bws_base_<tpl>_resolve_value( array $options, $instance ): array{ value, link_id, link_type }`. Text's shipped shape, generalized to the nine `supports_try` templates.

- `'' === value` means "read nothing". That is the ONLY predicate the `try_` loop tests to move to the next attempt.
- The seam emits NO preview label. That half held on all nine: the label lives in the shell, and getting it there was the actual per-family work — `bws_base_content_callback()` alone returned `bws_build_preview_label()` at three points mid-resolution (site arm, refusal arm, ambient arm), and title/permalink/image/email/phone/datetime had the same shape. **The STATED FALLBACK half did not hold, and is amended to what shipped** (see §What the build changed about this spec): text, title, permalink, email and phone keep it in the shell as designed, while image, content, datetime_single and datetime_range emit it inside the seam. The rule is not per-family taste — a family keeps the fallback in the seam exactly when its per-entity cores emit the fallback THEMSELVES on a read that found nothing, because then the only thing left to state is which arms ran no core at all, and that is a fact no shell holds. It costs the caller nothing either way: `bws_try_run_attempts()` strips `fallback` before calling the resolver, so a seam-side emit resolves to `''` under a `try_` attempt and the walk advances.
- Uniform triple on every family, including the five that register no link options — `link_id` is a constant `0` there. One shape, one loop, no branch in the caller.
- The media-block guard stays in the SHELL, not the seam: it takes `$block`, the seam takes `($options, $instance)`. Email and phone already keep theirs in their callbacks; `try_media_block_guard` keeps its own copy. Two shells, two guards, no hoist.

The `try_` loop is lifted OUT of the closure into a named function that **takes the family's resolve callable as a parameter**, so it is pure and harnessable without WordPress. That is FW-107's stated fix shape verbatim ("the arm carries a callable rather than a string another file switches on"), and it is what lets its harness cover the loop rather than a map beside it.

`resolve_fn` is a new template descriptor key — absent means the old arm path. It exists only to keep every intermediate commit shippable and is deleted in the final commit. Not a naming convention + `function_exists()`, because a rename would then be a silent behavior change, which is the drift mode being closed.

## Family groups

Measured, and they do not behave alike:

| Group | Families | `supports_link_wrap` | `takes_first_usable` | `try_list_options` | Callback lines |
|---|---|---|---|---|---|
| List + link | text, title | yes | no | yes | 129+28 (split), 94 |
| List, self-wrapping | email, phone | no | no | yes | 33, 33 |
| Collapsing | content, permalink, image | no | yes | no | 109, 45, 104 |
| Datetime | datetime_single, datetime_range | yes | no | no | 157, 124 |

**Only four families register link options** — text, title, datetime_single, datetime_range (`supports_link_wrap` at `base-tags.php:390`, `:467`, `:568`, `:631`). Email and phone declare it nowhere; they self-wrap `mailto:`/`tel:` inside their cores. Content, permalink and image are excluded by the constructor. So FW-135 is observable on four families, and five of the nine cannot be half-done on that axis.

## Order

Text → email, phone, permalink → title → image → content → datetime_single, datetime_range.

Text first because nothing lands before the loop works and its seam already exists, so its commit IS the loop. Then the three trivial flips (33/33/45 lines) exercise the `resolve_fn` fallthrough on cheap subjects before the ~100-line extractions start. Content and the datetimes last because they carry the FW-116 tails.

## Parity assertions

Measured 2026-09-22. Each non-link family's commit has to carry these, because "nothing moved" is its whole verification:

- **`sep`.** `bws_try_join_items()` resolves `( null === $sep ) ? ', ' : $sep`; `bws_collect_value_list()` resolves `$options['sep'] ?? ', '`. The closure passes `$opts['sep'] ?? null`, so the two agree, including on an author's empty-string separator.
- **Bound.** Both slice the id list BEFORE rendering and drop empty values from the result — `array_slice( $items, 0, $limit ?: null )` in the fold, the count-sources-read contract [I19] in `bws_read_bounded_sources()`. The loop already writes `$slot_opts['limit']` explicitly, so the fold's own `bws_clamp_limit()` reads a resolved number.
- **Limit default.** Not a delta: `bws_fold_slot_chain_options()` already returns `0` (unlimited) for a chain-era fanning slot, same as base.

Expected output movement is therefore confined to the four link-registering families, and is exactly FW-135.

## FW-116 stance

The seam's empty-triple-vs-null contract is a FIXED INPUT to this work. After the merge every `try_` slot passes through `bws_base_ambient_analog()` for the first time, so its per-tag carve-outs govern `try_` output where today the arms bypass the seam entirely.

- Do NOT take FW-116's structural flip as a prerequisite. Its own row says the flip needs a leak-safety check per `(tag, kind)` pair before it is safe to land; blocking on that converts a standalone merge into a two-item chain.
- Content, datetime_single and datetime_range carry their empty-vs-null fix in the CALLBACK TAIL being split (FW-116's Progress records this). Carry those VERBATIM, re-pinned by the existing `context-test-matrix.md` C-C2/C-DT1/C-DT2 snapshot rows. **Verbatim is the binding word; "into the shell" was the original wording and is amended** — all three fixes stayed in the seam, because the tail they ride is the same tail that emits the stated fallback and it could not be split from it. Content's is the ambient arm's preview-terminates / front-end-falls-through pair (ticket 06); both datetimes' is the tail's `null !== $ambient` term (tickets 07, 08). Measured, not assumed: C-C2, C-DT1 and C-DT2 each re-rendered on every one of their contexts after the flip, and their snapshot pages did not move.
- If a family reads wrong under the seam's contract, that is evidence FOR FW-116's structural flip and gets recorded on its row. It does not get fixed here.

## Release

**All nine in 1.21.0, closure and arm table deleted before the release tag.** Splitting across releases would ship a dual render path — five families on the closure, four on the seam, in released code for a cycle — which is the drift condition the merge exists to close, kept alive deliberately. The only arguments for splitting were size and risk concentration; the counter-argument is stronger.

1.21.0 is unreleased (CHANGELOG header `— unreleased`, no `v1.21.0` tag, plugin header still `1.20.0`), so FW-135 is an inconsistency inside the branch, not shipped debt.

## Verification

- Per family as it lands: matrices re-run and the page-snapshot baseline re-captured **in the same commit as that family's flip**. Not once at the end — each flip moves output by design, and a single end-of-branch re-capture makes nine changes indistinguishable in one diff.
- Before tagging: a FULL page-snapshot sweep across every committed baseline, not only the pages the matrices name. The merge can move any page rendering a `try_` tag.
- `tools/harvest-replay/` has nothing to say here: it verifies converter output, not rendering. Page snapshots are the render instrument.
- Matrix surfaces: `fold-test-matrix.md`, `text-test-matrix.md` §T8, `context-test-matrix.md` C-C2/C-DT1/C-DT2.

## Tasks

Broken into build tickets under `issues/`, numbered in dependency order — 01 is the expand step and the only one with no blockers, 02–08 are the migrate batches (each blocked only by 01, though the ones sharing a file serialize in practice), 09 and 10 are the doc retirement and the contract, 11 is the release gate. The list below is the same work in flat form.

- [x] Lift the slot loop out of `generate_base_try_tags()`'s closure into a named function taking the resolve callable; new pure harness covering it.
- [x] Add the `resolve_fn` descriptor key; loop falls through to the arm path when absent.
- [x] Flip `text` (seam exists — this commit is the loop).
- [x] Flip `email`, `phone`, `permalink`.
- [x] Flip `title`.
- [x] Flip `image`.
- [x] Flip `content` (three mid-resolution preview returns move to the tail; FW-116 tail carried verbatim).
- [x] Flip `datetime_single`, `datetime_range` (FW-116 tails carried verbatim). Both keep the stated fallback in the SEAM — see tickets 07/08 for why it could not move to the shell.
- [x] Revert the three FW-135 disclosure sites: the `linkKey` help text, `docs/tag-reference.md`'s `linkKey` row, its `linkTo` prose. The sentence must never reach an author.
- [x] CHANGELOG: drop the `try_` carve-out from the existing 1.21.0 per-item-link entries so they read unqualified. No separate entry for the merge — net-delta rule, the refactor moves no user-visible behavior of its own.
- [x] Final commit: delete the closure's remains, `BWS_TRY_SLOT_ARMS`, `bws_try_slot_arm()`, `bws_try_slot_base_branch_kind()`, `try-slot-arms-test.php`, `bws_try_join_items()`, `try-join-seam-test.php`, and the `resolve_fn` key. Repoint `fold-test-matrix.md:556`, `limit-clamp-test.php`'s site enumeration and `limit-default-test-matrix.md`'s row in the same commit.
- [x] Rewrite CLAUDE.md's `try_` slot ARM trigger row in place as a `try_` slot LOOP trigger naming the lifted function and its harness. The trigger did not go away, its surface moved.
- [x] Full page-snapshot sweep before the release tag. All 23 pages clean against the baseline, and every baseline line the branch moved traced to the commit that explains it (ticket 11).

## `try_*_fn` fate

Keep the ~40 functions; delete only the `try_*_fn` DESCRIPTOR keys and the arm table. They are plain per-entity cores and base already calls one directly (`bws_try_text_row_dispatch()` from the `meta_row` branch). Renaming them is the vocabulary pass's job, not this one — a rename mid-merge churns every harness that names them in the same commit as a behavior change.

`try_query_fn` dissolves: its query-context arm becomes the seam's `query_context` case. Per family, CONFIRM the base route reaches the query context before deleting the descriptor. Where it does not, that is an FW-9 gap on the base tag and gets filed there, never papered over by keeping a `try_`-only arm alive.

## Not in scope

- **FW-92** (no fanning advisory on `try_` slots). Registration-side editor work; the placement question is unchanged by anything here. Bundling it would hide a decision behind a refactor.
- **FW-116's structural flip.** See stance above.
- **FW-60** (absorb `try_` into base tags via an add-slot control). Standalone from this work by decision — FW-60 is an editor + encoding change, and after the merge it has no renderer work left in it. The merge also answers FW-60's open question 1 ("is `try_` structurally base + N slots?") as a build artifact rather than a research task.
- **FW-81** (collapse the two datetime tags). Parked, no ticket. The merge extracts two datetime seams; FW-81 later merges two seams instead of two callbacks.

## What the build changed about this spec

**One sentence in this spec was amended toward the code rather than the other way round, on 2026-09-23, by the user's decision.** CLAUDE.md's drift rule presumes the doc and requires a note at the site of any resolution that goes the other way; this is that note, and the amendment is the two clauses above marked **amended** — §The seam's "NO stated fallback… lives in the shell", and §FW-116 stance's "into the shell verbatim".

Why it was allowed here. The sentence was written on 2026-09-22 from ONE measured family: `bws_base_text_resolve_value()`, whose cores do not emit a stated fallback, so its callback owned one and the split put it in the shell — correctly, and it is still there. Four families then turned out not to share that property. Their cores emit the fallback per read, and what the callback tail adds is the compensating emit for the arms where NO core ran (fanning, refused, ambient-claimed). Which arms those are is derived inside the resolve function, from `$res['fans']`, the refusal test and the ambient claim — none of which the shell receives. Moving the tail up would not have relocated a rule; it would have required re-deriving three facts the seam already holds, or widening the seam's return past the agreed triple to carry them. So the sentence was not a decision the code failed to catch up with. It was a generalization from a single sample, and the build is what measured the other eight.

What did NOT change, and is the reason the amendment costs the merge nothing: the seam contract the `try_` loop reads is untouched. `'' === value` still means "read nothing", the triple is still uniform, and a seam-side fallback is inert under `try_` because `bws_try_run_attempts()` strips `fallback` before calling the resolver. The amendment moves where a family's own fallback text is stated, not what any caller can rely on.

This is not precedent. The next sentence in this file that disagrees with the code is drift until someone measures which side is wrong.

**A second amendment, 2026-09-23, same route: ticket 09's "all three revert" holds for the two doc sites and NOT for the help text.** The help text does not go back to its pre-disclosure sentence. That one named "the source that produced the output", singular, and `dd070b9` records the plural being refused when it was written — saying the field is read from the successful attempt's sources would have promised per-source links the code did not produce. The merge produces them, so what ships is the sentence the ceiling blocked: "For try_ tags, this field is read from the successful attempt's source(s)." Wording is the maintainer's, per the house rule on author-facing copy. The ticket's deliverable is unaffected either way — no site states the ceiling now, which is what "revert" was standing in for.

**A third amendment, 2026-09-23, same route: `resolve_fn` is NOT deleted.** This file said it "exists only to keep every intermediate commit shippable and is deleted in the final commit", and ticket 10 listed it among the deletions. The key did two jobs during the migration: ABSENT meant "stand on the arm path", and PRESENT named the base resolve function a family reads through. The first job died with the ninth flip and was deleted in ticket 10 (a template without the key now gets no `try_` tag). The second did not die: it is the only link from a template to its seam, and this file itself rejects the one alternative carrier it names ("Not a naming convention + `function_exists()`"). Nothing in the plan named another, so the sentence described the first job and was read as if it covered both. Kept as the one required key, by the user's decision. Not precedent either.

## Tracker edits owed

- FW-135: DONE 2026-09-23 (ticket 09) — the heading block is deleted and the ledger row reads as "dissolved by FW-136 before release", with the disclosure's retirement recorded in it. No CHANGELOG entry of its own.
- FW-107: gains the FW-136 interaction; stays visible until it dissolves, then closes with a pointer. DONE 2026-09-23 (ticket 10): closed in the ledger, dissolved, pointing at FW-136.
- FW-60: blocker string drops "premature" — it is a decision, not a maturity judgment (user, 2026-09-22). DONE: the blocker reads `decision:absorb try_ into base at all`.
- FW-81: gains a line saying it will find two seams rather than two callbacks. DONE: its FW-136 interaction says so.
- FW-136: DONE 2026-09-23 (after ticket 11) — the heading block is deleted, the ledger row names this file's committed copy as the build record.
