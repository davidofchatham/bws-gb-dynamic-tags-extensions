# Archive: the repeater-row read arm — FW-74 (SHIPPED 1.21.0)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.

**Provenance.** The build spec for FW-74, written 2026-09-04 and re-checked against the tree 2026-09-16. It lived at `.scratch/repeater-row-arm/spec.md` and died when [PR #137](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/pull/137) merged on 2026-09-21; that PR is its published form, and this file is the spec itself, committed whole. Its `Status:` and `DESIGNED, NOT STARTED` lines read as they did the day it was written — the work shipped in full, including the nested-repeater fixture its ticket 08 describes as outstanding. Its nine ticket files stayed private and were not committed. Decisions that PRODUCED this spec live in `.scratch/plans/table-tag.md` §D4 and §GRILLED 2026-09-04, a plan still live at the time of archiving.

Status: ready-for-agent

**DESIGNED, NOT STARTED.** Ships as its own change, ahead of `{{table}}` — a commit-ordering preference, not a prerequisite.

**Re-checked against the tree 2026-09-16**, at `ccf81ea`: every code fact below still holds (the string-only read seam, the two-key producer record, the missing arm, the `meta_row` arm-table row and its `branchable: false`, the three matrix pins, `{{table}}` still filter-gated). The rooting-modifier scoping in §Reach was the one thing that had moved, and it is rewritten there.

**Origin.** Recorded 2026-08-15 out of [#105](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/105), which needed the wire-vs-resolved distinction and had no place to leave the design; carried inside `.scratch/plans/table-tag.md` until 2026-09-04, when it was extracted to `.scratch/plans/repeater-row-arm.md` and relocated the same day to this spec home (`.scratch/<feature-slug>/spec.md` per `docs/agents/issue-tracker.md`) — one feature's in-flight work, not a plan outlasting many. That plan keeps the DECISIONS that produced this one — §D4 (support lands generally, not table-locally) and §GRILLED 2026-09-04 (the scoping below) — and they are decision records, not current-state sources. This file is the build design.

---

## The state today

`src(rows,<field>)` is well-formed wire. The chain compiles, the engine runs the step, and `bws_fold_chain_resolution()` answers kind `meta_row`. The READ layer already handles it — both `bws_read_resolved_source()` (`field-helpers.php`) and `traversal-pipeline.php` carry a live `case 'meta_row'`.

What is missing is the ARM: `bws_base_text_resolve_value()` runs a `site` gate, then the refusal test, then the ambient seam, then a `term` list branch, a `post` list branch and a singular `post` tail — and a `meta_row` kind falls past all of them into the singular tail, which resolves no id and reads nothing. So the tag renders empty, and `fold-test-matrix.md` §F9.5 and §F10.4 pin that emptiness. (§F9.5's own reason line still spells the dispatch in its pre-1.19.0 per-kind form — "`site`/ambient-term/ambient-user/`term`/`post`" — which names the same arms before `bws_base_ambient_analog()` collapsed the ambient two; that row is rewritten by this item anyway.)

**The refusal gate does not stand in the way.** `bws_base_read_refused()` refuses an empty chain kind and `BWS_SOURCE_KIND_UNRESOLVED` only, so a `meta_row` reaches the branch chain untouched, and the arm is one more `elseif` beside the two list branches.

**Unimplemented, not inert.** `{{table}}` wants a repeater row as the read CONTEXT for its cells, and on a fanning tag the same source would concatenate like every other fanning step. Nothing about the wire is an author error, which is why #105's inert-chain warning deliberately does NOT flag it: doing so would encode "no arm implements this yet", a per-template fact with a shelf life, and the tag would become correct without anyone touching the sentence calling it broken. `CONTEXT.md` §Language holds the term.

`rows` stays off the base chain control's step enum until the arm lands; authoring it requires a hand edit today.

## THE ONE DESIGN RULE, and it is the trap this area sets

Branch on the **WIRE** kind (`bws_fold_chain_resolution()`), never on `$base['kind']`. The two axes share a noun and need opposite answers:

| Axis | What a `meta_row` means | What the arm must do |
|---|---|---|
| **Wire** (`src(rows,…)`) | the author asked for repeater rows | consume them (this work) |
| **Resolved / ambient** | the query loop's repeater row, positioned by GB Pro | keep falling through to the POST arm, whose core re-infers the row through `bws_read_field`'s own loop inference |

Conflating them breaks the repeater row's loop fallthrough — a live, shipped path — while looking like the obvious implementation. §F9c is the pin, and **the mutation is to make the branch read `$base['kind']` and confirm §F9c fails** — run it, do not assume it.

**`branchable` on `BWS_TRY_SLOT_ARMS`'s `meta_row` row STAYS `false`** when the arm goes live. That row's own comment already carries the distinction in full, and `bws_try_slot_base_branch_kind()`'s PHPDoc says refusing a `meta_row` base there "would delete that path".

The same rule is what makes this item independent of FW-7: FW-7 proposed deleting `bws_read_field()`'s loop inference, and the ambient half of the fallthrough above IS that inference. FW-74 landing removes nothing from it.

## What is genuinely new — three things, one of them a mechanism

**The measured precedent is #108** (`46b613f`), which wired the `user` leg live: 106 insertions across 10 files, 4 of them code. Its commit records why it was that cheap — "asserting the row before anything could reach it is what made #108 a wiring change rather than a table one." `meta_row` is in the identical position.

1. **A sources-not-ids selector.** Every arm reads through `bws_base_source_ids_of_kind()`, which returns `int[]` and drops `id <= 0`. A repeater row has NO id — it carries its `row` array. So the arm needs a sibling returning the resolved sources themselves; the ids version becomes a map over it.
2. **A new `ids` mode on the arm table — a kind that is id-less AND fans.** `query_context` is the id-less precedent (`ids: 'none'`, `fn: 'query'`) but it is a SINGLETON (`list: false`). `meta_row` is id-less and PLURAL — the arm reads each resolved source of that kind, with the limit/`sep` list seam applying. No current kind has that combination, so it is one new column value (`ids: 'sources'` or similar) plus its dispatch leg. **This is the only new mechanism in the item.**
3. **Per-template row fn, on the KEYED templates only.** Analogs REFUSE on a row (table-tag.md §D4(a)): a row is not an entity and has no title, permalink, date or author, and the hop a `use:title` would imply is already spellable with no new vocabulary (`rows,team_members;refs,lead_ref` then `use:title`). So `title` / `permalink` / `excerpt` / `author` get no row fn and render empty on a row source, which is an already-supported state.

The base-tag half is a branch per arm, not a redesign: `bws_base_text_resolve_value()` already switches on `$res['kind']` with `site` / ambient / `term`-list / `post`-list / singular legs, and `meta_row` is one more list leg reading sources instead of ids. The other arms (`content`, `image`, `datetime`, `email`, `phone`) take the same shape.

## Reach

**Base tags + `try_` + `{{join}}`.** `{{join}}` is free — it absorbs its read through the text seam rather than through an arm of its own. `try_` is in by decision (table-tag.md Settled #11): `BWS_TRY_SLOT_ARMS`'s `meta_row` refusal comment is superseded on the CHAIN-kind half only.

**There is no longer a family left OUT.** This spec originally scoped `term_` and `view_` out, because a rooting modifier took `bws_base_traversal_options()` raw with no chain option and so could not spell `src(rows,…)` at all. Both families are gone from the tree — `view_` before this spec was written, `term_` with FW-129 in 1.21.0 — so the reach above is the whole authoring surface, and nothing here waits on a family gaining a chain option.

**The chain-step OFFER** goes to base tags and `try_` (table-tag.md Settled #12), at `base-tags.php` and `class-tag-template-registry.php`.

## The read seam DECOMPOSES — it does not gain a sibling

`bws_read_resolved_source()` is declared `: string`, and every case arm ends with the same coercion (`(is_scalar($raw) && '' !== (string)$raw) ? (string)$raw : ''`). It is already a raw read with one coercion repeated five times, so a sibling would duplicate the kind switch to remove a line each arm repeats.

- **`bws_read_resolved_source_value(): mixed`** owns the kind switch and returns raw.
- **`bws_read_resolved_source(): string`** becomes the coercion over it — signature unchanged, so every current caller is byte-identical.
- The `post` arm's raw read IS image's two-pass, so **`bws_get_meta_image_data()`'s two-pass migrates into the seam** (`bws_read_field(..., true)`, then `false` when pass 1 yields nothing, because GB `Meta_Handler::get_value` returns `''` for arrays). `bws_process_meta_image_value()` is unchanged — it is already a pure processor over `mixed`.

**This is not tidiness.** The string arm's `meta_row` case drops arrays, so **an ACF image sub-field inside a repeater row is only readable at all through the raw path.** Image-on-a-row needs this; it is not a bonus.

**Datetime is NOT part of it.** It reads scalars (`bws_parse_combined_date_time()`'s date and time reads both pass `single_only = true`) and already dispatches from a resolved-source-shaped payload — `bws_parse_combined_date_time()` takes the shim's `{kind,id,taxonomy}` and hand-rolls a three-kind read switch. It consumes the VALUE half here and keeps deriving its own `$acf_object_id`, which its shipped `@invariant` states is a separate arg from the value read.

## Provenance — four keys, recorded at the producer

`bws_pipeline_rows_to_sources()` records only `array( 'kind' => 'meta_row', 'row' => $row )`. No parent kind, no parent id, no repeater name, **no row index**. So a wire row cannot build `get_field_object()` for a sub-field, and custom ACF return formats fall through to format-agnostic parsing — issue #22's failure mode, reached by a new route.

- Record **`parent_kind`, `parent_id`, `repeater`, `index`**. `bws_run_step()`'s `rows` case holds the parent `$source` and the repeater `$field` at the moment it calls the coercer and passes only `$raw`; widening the signature costs no lookup and no new read. The index is captured in the coercer's loop.
- Four keys, not three: ACF's sub-field config is reachable by the flat meta-key convention (`{repeater}_{index}_{sub}`) against the parent selector, or by sub-field key. `have_rows` / `get_sub_field` is off the table (table-tag.md Locked decision #3 — a stateful cursor breaks the pure fold).
- **The field-object READ is NOT this item's.** It is FW-3's, whose own Open line is that same missing exposure ("the field-object-formats read the seam does not currently expose"). Datetime on a wire row therefore ships format-agnostic until FW-3 closes. FW-74 records the keys because the producer is the only place they are free, and a shipped source shape is expensive to widen after the fact — the same argument #108 recorded for asserting the arm row before anything could reach it.

## Matrix obligations

- **§F9.5 and §F10.4** stop being "renders empty" rows and become real reads. Their stated reasons already point here, and that is what says so.
- **§F9c.4 must be rewritten in this item's own commit.** `BWS_TRAVERSAL_STEP_INPUT_KINDS['rows']` accepts `meta_row`, so a slot stating `src(rows,…)` while standing in a repeater row is legal wire meaning a NESTED repeater. Today it is refused as a chain kind and the row passes for that reason; with the arm it becomes a real attempt, finds no nested `team_members`, and passes for a different one. The output never moves, so nothing goes red. **A row exercising a genuine nested repeater lands with the arm** and takes over what F9c.4 currently claims.
- New rows land under the visible-row + reseed + baseline mandate (`docs/testbed.md`).

## Not in scope

- The per-kind ANALOG dispatcher — `{{table}}`'s build item (FW-53). Analogs refuse on a repeater row, so this arm has no analog half to share; it becomes cross-container the day a second reader dispatches from a resolved source, which is FW-120.
- The field-object read (FW-3).
- Deleting `bws_read_field()`'s inference (FW-7) — see the trap above: nothing here unblocks it.
