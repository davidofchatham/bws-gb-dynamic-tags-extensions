# Archive: the field picker's KIND axis — FW-13's in-flight half (SHIPPED 1.21.0)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.

**Provenance.** Two in-flight pieces of FW-13, extracted from `.scratch/plans/field-selector.md` and written 2026-09-18 against the tree at `61ac971`. It lived at `.scratch/field-selector-kind-axis/spec.md` and died when [PR #137](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/pull/137) merged on 2026-09-21. Sibling to [`field-selector-v1.md`](field-selector-v1.md), which records the v1 this one narrows. It is committed mainly for §Findings: three corrections that cost more to derive than the build work did, each restated at the site it corrected before this file went, but only as a conclusion. Its two ticket files stayed private and were not committed. Relative links below resolve from the spec's old home; `.scratch/` paths are gitignored and unreachable from a clone by design — per `docs/cross-link-rules.md`, a design-history file's dangling paths are the record working, not defects.

Status: **BOTH TICKETS DONE (2026-09-18)**, in the stated order, on branch `fw-74-repeater-row-arm`. This file dies at merge; the PR body publishes it.

**Written against the tree at `61ac971`** (branch `fw-74-repeater-row-arm`), 2026-09-18.

## What this is

Two in-flight pieces of FW-13, extracted from [`.scratch/plans/field-selector.md`](../plans/field-selector.md) because that file is a long-lived plan spanning shipped v1, v2, v3 Pie Calendar and the FU follow-ups — and dropping in-flight build design into it is what produced §70-81, a build instruction that went stale inside a plan nobody re-read.

`field-selector.md` remains FW-13's detail home for everything else. This file dies at merge.

**Both pieces are one axis:** the discovery endpoint knows something about a field's kind or type that the picker does not consume.

| # | Piece | State |
|---|---|---|
| 01 | An unscoped field group is offered under an entity of another KIND | **Done 2026-09-18** on the current branch, ahead of `{{table}}`'s column pickers. Finding 3 confirmed: §F13.7 did not move |
| 02 | A `refs` tail narrows to kind `post` but not to a post TYPE | **Done 2026-09-18**, immediately after 01. Ordering held: 02 was written against 01's `scopedRecords` and composes with it rather than replacing it |

**Order is fixed: 01 before 02, whichever else happens.** 01 changes what `scopedRecords` returns and 02 would filter on top of that result. Landing the kind axis first means 02 is written against final semantics instead of being rewritten when kind narrows underneath it. Reversing the order means doing 02 twice.

## Why 01 goes ahead of `{{table}}`

A `{{table}}` column picker anchors on the container chain's resolved tail. So the defect 01 fixes, which today affects one picker per tag, would affect **one per column** — ten times over on a wide table. Deciding after `{{table}}` ships its column surface means moving table's matrix rows a second time.

Settling it also unblocks something else: the Location preset currently stands down for a declaring root precisely because the two narrowings disagree about kind. Once they agree, the preset applies with no collision — and a column picker needs exactly that generalization.

---

# §Findings

Three corrections, each of the same shape: **a record describing a world that ended weeks earlier, with nothing in the tree flagging it.** They cost more to derive than the build work does, and they are the reason this file exists rather than a pair of commit messages.

## Finding 1 — `.scratch/plans/field-selector.md` §70-81 is SUPERSEDED

§70-81 commits `{{table}}` to consolidating `repeater_key` into a structured scope-hint carrier (`{via:'repeater'|'relationship', keys:[...]}`), **in-branch, before FU-1's location rewrite**. Its stated justification: "Relationship rows needs a SECOND scope axis (the relationship field's allowed post types)."

Both of its premises are dead. Chain of custody:

| When | Where | What it says |
|---|---|---|
| 2026-07-24 | `field-selector.md` §70-81 | Table consolidates `repeater_key` into a two-axis carrier, **because relationship rows needs the PT axis** |
| 2026-07-26 | `table-tag.md` §718-725 | "Relationship rows = a `ref` fan-out chain — **NOT a table mode**." The foreign-PT scope is **FW-32's**, a general chain fix, not table's |
| 1.17.0 | future-work Closed ledger | FW-32 retired — "the one residue is FW-39's scope" |
| 1.20.0 | — | FW-39's root-argument scope shipped (a specific term or post narrows the list) |
| today | FW-13 Open list | the PT residue still carried, unbuilt |

So: **table does not need the second axis, and the item that does need it is FW-13** — this file's ticket 02. §70-81 was superseded two days after it was written, by the other file, and the tracker independently routed the residue to FW-39 and then FW-13.

Its "FREE because unshipped (`d642a3f`, NOT on origin/main)" premise is separately stale — `repeater_key` is on `main`. The freeness survives anyway (it is a REST response field rebuilt per editor load, never stored wire), but the stated reason for it does not.

**Consequence: `repeater_key` stays FLAT. FU-1 absorbs it as originally written**, dropping `kindFromLocation` / `locationGroupLabel` string round-tripping, and FU-1 does **not** narrow to "only the location-VALUE rewrite" the way §77-81 claims.

A superseded-note is going in at §70-81's own site; this table is the evidence behind it.

## Finding 2 — "`src:ref` stepped-to-PT scope" names retired wire

FW-13's Open list carries the residue as "`src:ref` stepped-to-PT scope". Flat `src:ref` has not been authorable since 1.17.0. It survives only as a migration axis — [`base-shared.php:469`](../../includes/tags/base-shared.php) lists `ref` in `flatAxes` beside `srcTermIn`, and `bws_wrapper_ref_steps()` does not read it at all, reading `refs`-typed steps off the *assembled chain* so that even a stored flat `ref` is normalized before anything looks at it.

**This is the same defect class as the Filter-1 preset bug just fixed in 1.21.0** — a derivation reading `srcTermIn` plus a literal `src === 'site'`, so the only term preset sat on wire nobody could author while `terms,<tax>` presetted nothing. A rule stated in retired vocabulary looks satisfied and does nothing.

Live statement of the same item: **a `refs` step's argument names a relationship or post_object field; the post it lands on has an unknown type, so its successor's picker cannot narrow past kind `post`.** The Open line gets reworded to that.

## Finding 3 — the §F13.7 claim in the tracker looks wrong

FW-13's Open item says fixing the unscoped-kind question moves "§F13.1/13.2/13.3/13.7's lists", with "F13.2's RULE survives; its example stops being cross-kind."

Read against the harness, three of those four are about the narrowing and plausibly move:

- **F13.1** — "narrows taxonomy's fields, plus every unscoped one". Moves: "every unscoped one" is exactly the clause under decision.
- **F13.2** — "field one unscoped home survives, its other home excludes". Rule survives as the tracker says.
- **F13.3** — "narrows post type's fields — different list, same rule". Moves with F13.1.
- **F13.7** — "will not resolve leaves list UNNARROWED, never empty". **This is about a FAILED RESOLVE, not about unscoped groups.** Kind-binding does not obviously touch it.

**Confirm before writing the fix; do not carry the claim forward unchecked.** The tracker line was written 2026-09-17 against the code as it stood before the two picker commits on this branch landed.

Separately: those case names use "pin" for the entity sense, which was retired in `7124193` in favor of "select"/"specific". Not this item's job, but worth knowing the names read as stale.

## Finding 4 — an open check inherited by `{{table}}`

FW-14's tracker line says `{{table}}` lands "FU-1 prior art + FU-3 second instance + **FU-2 proposal**". FU-2 is a factory for the hand-copied `bws-field-combo` option flips — 17 of them across 7 files today, `table-tags.php` among them. But the folded model **deletes** table's, since the fold control registers its own field picker.

So table probably SUBTRACTS from FU-2's population rather than adding an instance. Not verified either way. Recorded here and in `.scratch/table-tag/spec.md` §Open, to be checked rather than assumed.
