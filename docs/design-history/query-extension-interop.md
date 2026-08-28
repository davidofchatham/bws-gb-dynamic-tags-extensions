# Archive: third-party query-extension interop (SHIPPED 1.19.0)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md` §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against, what a build actually did — never as a statement of how the code works now. For current state: `docs/tag-reference.md`, `docs/gb-constraints.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.

**Lifted 2026-08-28** under `CLAUDE.md` §Spec lifecycle, the RETIREMENT case: every ticket closed, so what was still open was extracted and the original committed whole. One thing was open — reporting the fallback re-application upstream — and it is now **FW-102** in `docs/future-work.md`. Nothing here is a to-do.

**What is NOT here.** The build records — 17 ticket files under `issues/` and the driver's `run-log.md` — stayed private and die with `.scratch/`. This is the decision record; the PR body carries the build narrative. Four items the spec listed as owed by the user were taken during release preparation (the version bump, the Upgrade Notice prose, confirming the cut of fix C from it, and closing #123); only the upstream report survived, as FW-102.

**Where the load-bearing parts live now**, and check these first — `bws_gb_tag_output()`'s PHPDoc (`includes/helpers/gb-output-boundary.php`) owns the output-boundary rule, `bws_gb_register_tag()` and its neighbours in `includes/helpers/gb-registration-boundary.php` own the collision rules, `bws_classify_loop_item()` (`includes/helpers/field-helpers.php`) owns item-shape recognition, `docs/gb-constraints.md` owns the GB facts this pass measured, and `docs/update-triggers.md` owns what each harness run does and does not prove.

**Dead paths below are the record working, not staleness.** Citations of `.claude/plans/…` and `.scratch/…` name where things were when this was written; per §Cross-link rules they are left alone.

---

**Below is the spec verbatim as it stood at retirement, including its own heading and its opening `Status: decided, not started` line.** Nothing in it was edited, reordered or brought up to date — that is the point of a record, and the banner above is how to read it.

# Spec — third-party query-extension interop (1.19.0)

Status: decided, not started. Grilled to an empty frontier 2026-08-24.
Trigger: GB Query Enhancements 1.3.0 installed on `testbed`; GH #123.

**Framing (D1): unconditional.** Nothing here names GB Query Enhancements in code. Every
defect below is a true statement about our boundary with any co-resident extension; GBQE
only made them observable.

---

## Problem — four measured findings

### F1 — base tags read the wrong entity inside a term or user query loop (#123)

`bws_get_loop_row_context()` reports `in_loop=false` for a `WP_Term` item, as #123 predicted.
That is **not** where the damage comes from. GBQE's `Term_Query::set_dynamic_tag_id()` hooks
`generateblocks_dynamic_tag_id` (priority 99) and GB does **not** pass `$fallback_type` to that
filter, so they cannot discriminate: the term id overrides the **post** fallback.

Measured via `do_blocks()` on a real term loop:

```
bare-title=[Hello world!]                <- post #1. WRONG
their-term_title=[Uncategorized]         OK
our  {{title src:term}}=[Uncategorized]  OK
our  {{term_permalink}}=[.../category/uncategorized/]  OK
```

Term ids and post ids collide constantly (every fresh install has term 1 = post 1).
`User_Query::set_dynamic_tag_id()` (priority 15, no queryType gate) leaks identically —
measured: user #1 → `{{title}}` = `Hello world!`. WooCommerce is safe by luck (a product id
*is* a post id).

Editor and front end diverge: GB injects `id:<edited post>` on the editor REST route and their
filter bails on `$options['id']`, so the editor shows the edited post. Both wrong, differently.

### F2 — `fallback` option-key collision. Live on every install, no loop needed

GBQE's `filter_output()` hooks `generateblocks_dynamic_tag_output` at 99. We route every tag
through `GenerateBlocks_Dynamic_Tag_Callbacks::output()`, so it runs on our output and
re-applies `$options['fallback']` whenever output is empty. 37 of our tags carry that option.

```
{{image key:missing|fallback:999999}}  with GBQE => '999999'   without => ''
{{image key:missing|fallback:avatar}}  with GBQE => 'https://secure.gravatar.com/avatar/...'
```

> **CORRECTION, 2026-08-26 — the two tag strings above were written space-separated
> (`key:missing fallback:999999`) and have been rewritten with the `|` in.** GB's
> `parse_options` splits on `|` only, so the original spelling parses as ONE option,
> `key => 'missing fallback:999999'`; no `fallback` option exists on that tag and it
> renders empty whether or not the boundary is in place. The quoted outputs belong to the
> corrected strings, re-measured on `testbed`: `999999` and the gravatar URL before the
> fix, EMPTY for both after it. The finding is unchanged — only the string that
> reproduces it was wrong, and the same typo was in ticket 08's pre-check line and in
> §Acceptance below.

A missing fallback attachment prints its raw ID as the image src. `fallback` is established
GB-ecosystem vocabulary (GB Pro's `loop_item` uses it identically), so the defect is
*re-applying a fallback the owning tag already consumed* — not the key choice.

### F3 — our `'0'` guard rewrites first-party GB output, and the PHPDoc denies it

`includes/hooks.php:30` claims *"no GB-native tag uses `as:alt` or returns bare `'0'`"*.
False. Measured:

```
{{loop_index zeroBased:1}} row 1 (GB PRO)      raw='0'  -> '0 '
{{comments_count none:0}} 0 comments (GB CORE) raw='0'  -> '0 '
{{loop_item key:qty}} row value 0 (GB PRO)     raw='0'  -> '0 '
{{term_count}} in a term loop, empty term      raw='0'  -> '0 '
```

Two separate drops exist and only the second is gated by `required`:

- **in the callback** — GB's `get_post_meta()` does `if ( ! $value ) return self::output( '' )`,
  so `'0'` is lost at source. `required:false` does not recover it (measured: `''`).
- **the required-bail** — `class-register-dynamic-tag.php:189`, disabled by `required:false`
  (`$required` computed line 159).

`docs/gb-constraints.md:376` states `required:false` **backwards** (says it suppresses the
block; it preserves it). Miswritten, not drift.

### F4 — no instrument we own can see the filter layer

```
$ wp bws render-tag '{{text key:bws_zero_probe}}' --porcelain | od -c
0000000   0  \n        # bare '0' — no trailing space
```

`replay-tags.php` renders through the same path, so the whole harvest/replay instrument is
blind to `generateblocks_dynamic_tag_replacement`. Only a real block render sees it. The
mandatory-visible-blocks rule is therefore load-bearing, not hygiene.

> **CORRECTION, 2026-08-24 — F4 AS WRITTEN ABOVE IS FALSE. The measurement did not reproduce.**
> Re-run on the same tag, same site:
>
> ```
> $ docker exec … wp bws render-tag "{{text key:bws_zero_probe}}" --url=…/matrix-post-meta/ --porcelain | od -c
> 0000000   0      \n
> 0000003
> ```
>
> Three bytes. That trailing space is `includes/hooks.php:30`'s guard, so
> `generateblocks_dynamic_tag_replacement` DID fire under `render-tag`. Same result for
> `{{comments_count none:0}}`. The probe meta exists and holds `0` (post 22), so the tag
> resolved. Most likely origin of the original reading: `od -c` column spacing — `0   \n` (two bytes) and `0      \n` (three) differ only by gap width in a pasted snippet.
>
> Source agrees with the re-measurement. In `class-register-dynamic-tag.php` the filter is
> applied INSIDE `replace_tags()` — callback at `:158`, `apply_filters(
> 'generateblocks_dynamic_tag_replacement', … )` at `:175`, required-bail at `:187` — so every
> direct caller reaches it, and both of ours are direct callers
> (`tools/cli/class-render-tag-command.php:107`, `tools/harvest-replay/replay-tags.php:358`).
> `docs/gb-constraints.md:191` already said so, before any of this was written.
>
> **What actually holds, and it reaches the same conclusion.** `render-tag` passes `$block` as
> `array()`, so anything keyed on real block markup or GB's block-name gate is unreachable;
> `--loop-item` only fakes `queryType: 'WP_Query'`, so the TERM and USER loops F1 is about
> cannot be produced at any flag combination; and `the_content` filters, `wptexturize` included,
> never run. The term/user gap is the load-bearing one: A/B's acceptance evidence cannot come
> from `render-tag`, which is what D24 concluded by a route that turned out to be wrong.
>
> **Decisions this touches, and where they land.** D24 STANDS on its second reason (658 rows
> with prose expectations would become a second source of truth); its headline reason changed.
> D28's three placements stand, with corrected content. `tools/test/page-snapshots.php`'s
> `WHY IT EXISTS` header and `verify.php`'s snapshot docblock were written to the false claim
> (ticket 02, `bee4b36`) and are corrected under ticket 03, whose own checklist item — *"someone
> reading a green result knows what it does and does not prove"* — is what a false
> does-not-prove fails. A `verify.php` assertion now pins the real behaviour: `replace_tags()`
> versus `do_blocks()` on the same tag, both arms' bytes printed on failure.

---

## Decisions

| # | Decision |
|---|---|
| D1 | Unconditional framing. No `class_exists()` on GBQE anywhere. |
| D2 | Fix F2 by **stripping at our boundary** — one wrapper around the 53 `output()` call sites. Report upstream to GBQE + GB Pro regardless. |
| D3 | **Leave the `'0'` guard global.** It is a workaround for a GB constraint that hits every tag equally, and GB's own tags are among its victims. Scoping it would kill `{{loop_index zeroBased:1}}` on row 1 of every zero-based loop. |
| D4 | Base-tag name collisions **overwrite, visibly**. Yielding is right for `term_*` (an optional extra) and catastrophic for base tags (losing `{{text}}` silently breaks every page using it). |
| D5 | A, B and C ship **together** in one release. |
| D6 | GBQE becomes a **declared blueprint dependency**. |
| D7 | Fix C uses an **allowlist**: GB's `output()` consumes exactly `trunc`, `replace`, `trim`, `case`, `wpautop`, `link`, `id`. Constant beside the wrapper, recording the GB version read. |
| D8 | *(superseded by D13)* |
| D9 | Both zero-value fixture rows: `{{loop_index zeroBased:1}}` (the pin) and `{{comments_count none:0}}` beside `{{text key:bws_zero_probe}}` (the explanation). **CORRECTED 2026-08-24 on measurement, shipped as THREE rows (`938456e`).** The explanation pairing is wrong: `{{comments_count none:0}}` and our text tag both render `'0 '` — they AGREE, and F3's own table above says so. The disagreeing pair is `{{text key:bws_zero_probe}}` vs `{{post_meta key:bws_zero_probe}}` (empty), which is F3's first bullet. Comments_count still earns its row as a GB CORE tag the guard covers; post_meta is the third. **D15/D22's enumeration must be read against this, not against the row list here** — `issues/04` carries the measurements. |
| D10 | Collision report goes to **both** `_doing_it_wrong()` and a settings-page Diagnostics row. |
| D11 | **One** new fixture page `matrix-loops` (term + user rows). `verify.php` **FAILS** when GBQE is absent — same rule the node-dependent harnesses carry. |
| D12 | Ship an `== Upgrade Notice ==` entry. |
| D13 | **Drop** the `bws_dynamic_tags_owned_tags` filter. D3 removed its only consumer; a public filter with no caller is a maintenance obligation bought on speculation. |
| D14 | *(reframed as D23)* |
| D15 | `hooks.php` PHPDoc: state the **rule** (fires for any tag returning `'0'`, ours or not, deliberately) plus a **dated enumeration** of the four measured tags. Rule is load-bearing; enumeration is an observation, marked as one. |
| D16 | `gb-constraints.md` gains the `required:false` correction — see D21. |
| D17 | The "we deliberately rewrite other plugins' output" reasoning lives in that PHPDoc only. **No FW row, no ADR** — it constrains one closure. Add it to FW-23's `Interacts with`. |
| D19 | Upgrade Notice: lead with A/B, one clause for C, nothing about the dup guard. |
| D20 | Bundle takes **1.19.0**; FW-53 `{{table}}` v1 moves to **1.20.0**. A correctness fix does not ship behind a feature. |
| D21 | Fix the inverted `gb-constraints.md:376` entry toward the measurement and add the Drop-1 limitation in the same edit. Not drift — a doc about external behaviour is simply right or wrong about it. |
| D22 | The zero-output fact lands in **all three**: `gb-constraints.md` (the GB fact + the four tags), `tag-reference.md` (our response is not scoped to our tags), `README.md:27` (loses its "a field holding" framing). PHPDoc owns the axis; all three state consequence. |
| D23 | Env versions recorded in `tools/fixtures/core-structures/env-versions.php` (data, beside `manifest.php`); `verify.php` compares live vs recorded. Set: **GB, GB Pro, GBQE, ACF Pro**. Our own version is excluded — the record answers "what environment were these results true in". |
| D24 | Automated retesting = **page snapshots** (curl the fixture pages, normalize, diff). The only instrument that sees the filter layer (F4). A matrix-row runner is rejected: 658 rows with prose expectations would become a second source of truth. |
| D25 | Snapshots **committed** to `tools/test/snapshots/`, normalized at capture (strip `?nocache=`, absolute URLs, `uniqueId` churn, GB `<style>` blocks) so drift is a reviewable git diff. |
| D26 | `verify.php` compares on **every** run; when `env-versions.php` also drifted it says so explicitly — that reframes a diff from "we broke it" to "GB changed under us". |
| D27 | **Split**: capture + compare + `env-versions.php` ship in the bundle (they are A/B's acceptance instrument). The dependency replay over the *harvest* corpus becomes an FW row. |
| D28 | F4 is written down in three places, **`docs/update-triggers.md` owning the rule** (its stated job is the does-and-does-not-prove axis). `testbed.md` widens its `wptexturize` sentence; `harvest-replay/README.md` names the blind spot under the build replay. |
| D29 | **Experiment M/R/E retired.** New names, each naming its variable: **migration replay** (the wire changed), **build replay** (our build changed), **dependency replay** (a dependency's version changed). `M2` → "the second migration replay". Page snapshots deliberately stay outside the family — a capture-and-diff of whole pages is not a replay of a tag corpus. |
| D30 | Rename the **15 live sites**; leave the **23 in `docs/design-history/multi-step-slot-sources.md`** untouched (a record of runs that happened under those names — the ADR-0004 posture). README notes the rename and its date. Rename ships **in-bundle**, since the third member arriving is the trigger. |
| D31 | Fixture-page reorganization gets its **own FW row**, with the hard interaction recorded: reorg invalidates every page snapshot, so landing it means regenerating the baseline in the same commit. |
| D32 | Page snapshots live at `tools/test/page-snapshots.php`; **`docs/testbed.md`** owns operating them, `docs/update-triggers.md` owns what a clean diff proves. |
| D33 | **FW-94 lands here: acknowledged break, no shim** (2026-08-24). `bws_get_loop_row_context()` → `bws_get_loop_item_context()`, `row_post_id` → `item_post_id`, ~39 sites. **In scope, not merely adjacent** — it shares the `plugin-integration.md` row, the CHANGELOG entry, the Upgrade Notice and a sequence slot, so tracking it as external work would be a fiction. Lands **after A/B** so the behaviour diff is reviewable against the names every existing doc and matrix row uses; the mechanical rename follows. Verified: no caller in `bws-portal-system`; `plugin-integration.md:460` is the sole published reference. **Expand–contract is deliberately not used**: adding the new name beside the old IS a shim, which this decision rejected, and ~39 in-repo sites land green in one change. *(Correction to an earlier draft: `plugin-integration.md:460` is touched TWICE — contract in ticket 13, name in ticket 14. The "one edit" claim was about the INTEGRATOR absorbing one break, which stands.)* |
| D34 | **The break is announced in the Upgrade Notice** (supersedes D19's exclusion). On a self-hosted site the person calling a `bws_` helper is usually the owner, or the person the owner forwards the Updates page to — the "site owners vs developers" split does not hold for this audience. See §Upgrade Notice budget for what that displaces. |

---

## Scope

**In:** fixes A, B, C; the base-tag dup guard + its two report surfaces; page snapshots +
`env-versions.php` + `verify.php` comparison; the `matrix-loops` fixture page; the replay
rename; **FW-94's loop-context rename (D33)**; the doc corrections (D15/D21/D22/D28);
CHANGELOG + Upgrade Notice draft.

**Out:** the dependency replay over the harvest corpus (FW row); fixture-page reorganization
(FW row); FW-47's author permalink/image analogs; `{{table}}` v1 (→ 1.20.0).

Two independent renames ship here and should not be conflated: the **replay names** (D29/D30,
step 0) and the **loop-context identifiers** (D33, step 6). Different subjects, different
commits.

---

## Sequence

**Superseded by `issues/01`–`issues/15` (2026-08-24).** The 8 steps below were re-cut into 15
tracer-bullet tickets with explicit blocking edges; two changes came out of that re-cut and are
improvements on what is written here:

- **The horizontal "Docs" step is gone.** Each doc edit moved into the ticket whose behaviour
  causes it — the zero-output corrections ride the zero pins, the glossary edits ride the
  loop-item recognition, and so on.
- **Fix C splits on the OBSERVABLE seam, not on file batches.** Only the image path has a
  demoable behaviour change; the other ~48 call sites change nothing, and proving *that* is a
  separate deliverable (ticket 09) rather than 48 no-op edits buried beside a real fix.

The frontier after ticket 02 is wide: **04, 06, 08 and 10 all unblock at once**, and 01 and 02
themselves can start in parallel.

The step list is kept below as the reasoning trail. Order matters in one place: **the snapshot
baseline must exist before anything changes output, and the loop fixture page must exist before
A/B** — so A/B's diff shows exactly the corrected cells as its own acceptance evidence.

0. **Rename the replays** (15 sites). Mechanical, no behaviour.
1. **Page snapshots**: `tools/test/page-snapshots.php` (capture + normalize + diff),
   `env-versions.php`, `verify.php` comparison. Capture the baseline on current code.
2. **`matrix-loops` fixture page** — term + user loop rows, visible GB blocks per the mandate.
   Capture its baseline *with the leak present*.
3. **Fix C** — the output wrapper + allowlist constant. Review the snapshot diff.
4. **Dup guard** + `_doing_it_wrong()` + the Diagnostics block (collision report and the
   env-version drift line share one "Integration status" section).
5. **Fixes A + B** — `bws_get_loop_row_context()` gains `WP_Term`/`WP_User` shapes (keyed on
   item shape, never on their `queryType` constants); `bws_resolve_base_source()` step 2 gains
   term/user branches; unknown shapes refuse. Snapshot diff = the acceptance evidence.
6. **FW-94 rename** (D33) — ~39 sites, mechanical, no behaviour. After step 5 by design.
7. **Docs** (below). `plugin-integration.md:460` takes ONE edit carrying both the new contract
   (step 5) and the new name (step 6).
8. **Release prep** — CHANGELOG, Upgrade Notice draft for review, `Stable tag`.

### Upgrade Notice budget

300 chars, three candidates, and D34 put a third in. Ranked by what a reader must know
**before** updating rather than by size of change:

1. **The break** — the only item that can take a site down, and the only one with a
   pre-upgrade action (search your code for `bws_get_loop_row_context`). Leads.
2. **A/B** — changes what working pages show, inside term/user loops. One clause.
3. **C** — **CUT, confirmed 2026-08-24.** It only alters pages that were already rendering
   garbage (a raw attachment id as an image src); nobody needs warning ahead of time to stop
   seeing that.

That reverses D19's ordering. House style confirmed against 1.14.0–1.18.0: `⚠` prefix,
question-lead, plain text (no backticks — the Updates page does not render them), no em dashes.
No Migration Tool mention: the wire does not change, so nothing migrates.

**Chosen 2026-08-24, PROVISIONAL ("for now") — draft B, 257 chars.** Re-read it at step 8
before it goes in; it is the one line here whose wording the user has not finalized.

```
⚠ Calling bws_get_loop_row_context() from custom code? Renamed to bws_get_loop_item_context(), no compatibility shim; search your code before updating. Separately, tags in a query loop over terms or users now read that term or user, not an unrelated post.
```

### Why A needs no arm changes

`bws_base_ambient_term_id()` / `bws_base_ambient_user_id()` already gate on *wire kind is
`render_time`* + *resolved `$base['kind']`*, not on `is_tax()`. FW-63 (1.17.0) pre-paid for
this. A is confined to two helpers.

Corollary: A is cheap **only** for `term` and `user`, because those arms exist. That is exactly
why B is not optional — any later shape has no arm.

FW-74's rule *"branch on the WIRE kind, NEVER on `$base['kind']`"* is scoped to `meta_row` and
does not forbid A; the term/user gates read both axes and are shipped.

### Known consequence, not a defect

In a user loop, `{{permalink}}` and `{{image}}` go from *wrong post* to *empty* — FW-47's
honest gap, reached by a new route. A's user half is also the non-ambient user source FW-47
says the analogs are waiting on.

---

## Doc + tracker edits

| Artifact | Edit |
|---|---|
| `includes/hooks.php` PHPDoc | D15 — rule + dated enumeration + the D17 reasoning |
| `docs/gb-constraints.md` | D21 the inverted `required:false` entry + Drop-1 limitation; D22 the four zero-returning tags; the `generateblocks_dynamic_tag_id` fact (no `$fallback_type` passed) |
| `docs/tag-reference.md` | D22 — our zero response is not scoped to our tags |
| `README.md:27` | D22 — drop the "a field holding" framing |
| `CONTEXT.md` §Language | "Query-loop item" gains term + user; retire *"no third is recognized"* and its #123 sentence |
| `CONTEXT.md` I15 | retire the #123 reference |
| `docs/plugin-integration.md` | line 460 — contract (new reachable states: term + user items) AND, in the same edit, the FW-94 rename |
| `docs/future-work.md` FW-94 | both blockers clear (`decision:shim or break` taken = break; `code:published API` satisfied by the acknowledged break). Row records that A/B **weakens its second deferral reason** — the helper becomes the single owner of item-shape recognition, so FW-7 deleting `bws_read_field`'s inference no longer threatens it. Row moves to Closed/Retired on ship |
| `docs/update-triggers.md` | D28 owns the F4 rule; **new trigger row**: dependency version change → run page snapshots |
| `docs/testbed.md` | D32 operating page snapshots; D6 GBQE declared; widen the `wptexturize` sentence |
| `tools/harvest-replay/README.md` | D29 rename + D30 history note; D28 blind spot under the build replay |
| `docs/future-work.md` | FW-53 → 1.20.0; FW-23 `Interacts with` += D17; FW-47 `Interacts with` += the user-loop source; FW-78 title reworded (D29); **two new rows** — dependency replay, fixture-page reorg |
| `CHANGELOG.md` | 1.19.0 — Highlights + Added (diagnostics) + Changed (the FW-94 helper rename, old→new named, no shim) + Fixed (A/B, C) |
| `readme.txt` | Upgrade Notice (D19), `Stable tag` |

---

## Acceptance

- Page-snapshot diff after step 5 shows **only** `matrix-loops` cells changing, each from the
  leaked entity to the loop's own.
- `verify.php` fails with GBQE absent; reports version drift when `env-versions.php` is stale.
- `{{loop_index zeroBased:1}}` row 1 still renders `0` (D3 held — the guard was not scoped).
- `{{image key:missing|fallback:<bad id>}}` renders empty, not the raw id.
- Existing harnesses green: `slot-options-build-test.php`, `control-order-test.php`,
  `editor-filter-chain-test.js` (registration-pass trigger, fired by D4).
- After step 6, `git grep -n 'bws_get_loop_row_context\|row_post_id'` returns matches **only where a
  RECORD preserves the old name** — no live site, no shim. *(Corrected 2026-08-26 on measurement:
  as written this said only `docs/design-history/`, and there are four such places, because the
  criterion named one class of record and did not anticipate the others. `CHANGELOG.md` describes
  what past versions actually shipped and is append-only; `tools/debug/bws-ctx-probe-matrix.md`
  quotes log fields under the names emitted on the run date; and FW-94's own ledger row has to
  spell both names to say which moved. Scoped to executables — `-- '*.php' '*.js'` — the grep does
  return nothing, and that is the check with teeth.)*
- Page-snapshot diff across step 6 is **empty**. A mechanical rename that moves rendered output
  means it was not mechanical.

## Owed by you, not by the build

- The version bump itself (1.19.0) — flagged, never taken.
- Upgrade Notice prose — drafted for review, user-facing.
- The email to the GBQE author. Pure upside; GB Pro's `loop_item` carries the identical
  exposure, so agreement may travel upstream.
- **Confirm cutting C from the Upgrade Notice** (§Upgrade Notice budget). Three items, 300
  chars; C is the one whose absence costs least.
