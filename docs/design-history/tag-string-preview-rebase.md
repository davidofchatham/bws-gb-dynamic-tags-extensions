# Archive: re-base the tag-string preview tool on shipped chain wire — FW-79 (CLOSED 2026-09-01)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — never as a statement of how the tool
> works now. For current state, read `tools/preview/tag-string-preview.html` itself.

**Closed 2026-09-01, commit `afe9fe7`.** Every task below shipped in that one commit: the
Configure tab (unused across several dev cycles, confirmed no other doc referenced it) was
removed entirely; the Permutations contrast groups were re-labelled so chain/folded wire reads as
current and the pre-1.17 flat sibling wire (confirmed read-only via `src-chain-control.js` —
never author-producible again) reads as legacy; two of the three stale "no shipped form" notes
were retired and the third's citation fixed FW-32 (retired) → FW-39; the FW-71/#104 `same`-merge
multi-step gap got a new row. Beyond the plan's own scope, the same pass also rebuilt the
`{{table}}` section against `table-tag.md`'s REOPENED 2026-08-30 decisions (D1-D4/Q1-Q8) — the
never-shipped flat-numeric prototype (scaffolding, not a migration source, per D1) was deleted,
and the surviving folded model got a D2a single-post example and a D4(a) analog-refusal note
(worded as citing the plan's decision, not a runtime claim — this tool only assembles wire
strings and never executes PHP, and `{{table}}` itself is still unbuilt). See
`docs/future-work.md`'s Closed/Retired ledger for the tracked outcome line.

**What this is.** Detail home for FW-79 (`docs/future-work.md`), while it was open. `tools/preview/tag-string-preview.html` was built contrasting shipped-flat wire against an FW-56/FW-57 proposal that has since shipped, so the contrast now reads as a live choice where none remains. This file holds the evicted tracker narrative; the tool itself is the live artifact being re-based.

## Evicted narrative (as of 2026-08-18, moved here 2026-09-01)

`tools/preview/tag-string-preview.html` (2841 lines, last touched 2026-08-05 at `dfcc140`) is built on an axis that has collapsed: nearly every Permutations block is `isContrast:true`, pairing SHIPPED-flat wire against a FW-56/FW-57 PROPOSAL — and the proposal shipped in 1.17.0, so both columns now show shipped forms and the contrast reads as a choice where none remains.

Re-base it: shipped chain wire becomes the baseline column, and the toggle/contrast machinery is retained for what is genuinely unbuilt.

**Scope is a re-base, not a rebuild** — the emitters, the toggle apparatus, the slot-key spelling switch (already `alpha`-default with `alphaOrdinal()` mirroring `bws_slot_ordinal()`) and the `%A` format-token rewrite all still work; what moves is which side is the default and which notes are true.

**Measured staleness (2026-08-18):** three `note:'no shipped form…'` rows, of which two now have one (`srcTermIn is terminal, nothing hops off the term` and `one ref key per tag` — multi-`refs` chains ship; the third, the pinned-entity source, is genuinely still FW-39); the retired `limit` sense of "cap" at line 2609 (the other four uses are the ceiling sense and are ordinary English, not D7 carriers); and **zero** coverage of FW-71 / #104 — the tool models a slot through the pre-#104 world, so it knows nothing of chain wire on slots or the `same` merge, which is the largest single gap and the one an author would most want to eyeball. Registered chain roots (FW-69) appear once.

**HELD, not archived — and the difference decides the whole shape of the work (user, 2026-08-18).** The tool was left un-rewritten only until 1.17.0 shipped, because the wire was still moving and re-basing against a moving target is wasted work; it was never preserved as a record of superseded reasoning. `docs/design-history/per-step-limit.md`'s vocabulary warning reads the other way — it files this file beside the shipped CHANGELOG entries as a dated artifact deliberately not rewritten — and that half of the sentence is an inference the author made, corrected in place. (The CHANGELOG half stands on its own: released prose is append-only.) So this is a live tool resuming maintenance, not an artifact changing status, and nothing needs a preservation note in its head. Ship is the gate: the moment 1.17.0 tagged, the target stopped moving and the re-base became startable.

**What stays behind a toggle** is the non-shipped set, and it is not one kind of thing: genuine futures (FW-61 per-step `sep`, FW-59 bracket free-form on base tags, FW-39 the pinned-entity source, FW-24 per-type option tokens in a slot, FW-27 `if:`, FW-53 table v1, FW-45 reorder, FW-81 the datetime tag collapse) and REJECTED look-backs kept deliberately (the `/` limit char, root-explicit chains, `use(...)` read parity) — both are non-shipped, but only one is a candidate, and a reader who cannot tell them apart will re-litigate a closed call. Label the axis, do not merge them.

## Task list — ALL DONE, commit `afe9fe7` (2026-09-01)

- [x] Re-measure current staleness against the tree (the above is the 2026-08-18 reading).
- [x] Swap the baseline column to shipped chain wire.
- [x] Retire the two now-resolved "no shipped form" notes; confirm the pinned-entity one is still accurate. (Confirmed still accurate — FW-39, citation fixed from the retired FW-32.)
- [x] Add FW-71/#104 chain-wire-on-slots and `same`-merge coverage — the largest gap.
- [x] Re-label the toggle set so genuine-future and rejected-lookback rows are visually distinguishable. (Already done pre-existing via the sidebar's `[REJECTED]`/`[future look]`/`[DEFERRED → FW-N]` labels — confirmed accurate, not re-touched.)
- [x] (Beyond original scope) Remove the Configure tab.
- [x] (Beyond original scope) Rebuild the `{{table}}` section against table-tag.md's REOPENED pass.
