# Traversal Convergence — FW-49 + FW-3(b) + FW-48 seam halves — SHIPPED 1.16.0

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.

**Status: SHIPPED 2026-07-21, direct to `main`, one commit per step.** This is the second
traversal release; Phase 1 (v1.14.0, the source factory + step engine) archives separately in
[`traversal-pipeline.md`](traversal-pipeline.md). *(2026-08-19: the live tracking stub this line used
to point at, `.claude/plans/traversal-pipeline.md`, was consolidated into that same archived file's own
banner — it added nothing beyond what the banner now states.)*

## What shipped

One shared L3 combining fold, `bws_collect_value_list()` (`includes/helpers/field-helpers.php`),
replaces every hand-written list-mode loop: the four in base text/title (srcTermIn + src:ref
branches) and datetime's own extracted fold (`bws_datetime_collect_list`, deleted). It owns
slice-to-`limit`, per-item `fallback` suppression, render, drop-empties, per-value link capture,
the single-result link gate, and the `sep` join — so GH #51 is now structurally
un-reintroducible rather than convention-enforced.

Each collected value carries a **link identity** (`{kind,id}` or `null`) per CONTEXT.md **I12**,
which killed the `instanceof WP_Term` / `is_numeric` first-item kind sniff at datetime's four
call sites. The frozen SPEC §V3 seam (`bws_resolve_field_values`) took only an additive optional
`$links` out-param plus the pure `bws_source_link_identity()` mapper — it kept its own loop.

FW-48's seam halves landed too: `case 'user':` in `bws_read_resolved_source` (unreachable until
the factory `post→author` hop) and the text user arm, which gave `{{text}}` and `{{join}}` slots
the author-archive read through the ABSORB seam.

| Commit | Step |
|---|---|
| `c68c43b` | design lock + CONTEXT.md I12 (pre-build) |
| `b873a9c` | `bws_collect_value_list()` + 17 harness rows |
| `3c3f31d` | seam `$links` carry-out + `bws_source_link_identity()` |
| `bbe9e4c` | datetime's four call sites onto the fold (FW-3(b) payload half) |
| `77d3d39` | text/title's four loops onto the fold |
| `f6f8d1e` | FW-48 seam halves (seam user case + text user arm) |
| `9ad8d14`, `1215be8` | docs ship state + text-matrix T8 rows |

**Verification:** 10 pure harnesses green after every code step, plus a 45-case `render-tag`
byte-diff against a pre-build baseline, clean at steps 2, 3+4 and 5. Only WP `antispambot()`'s
randomized mailto entity encoding ever differed — compare with
`diff before after | grep '^[<>]' | grep -cv 'mailto:'` (must be 0).

**Outcomes in the tracker:** FW-49 → Closed/Retired ledger. FW-3 narrowed to the
field-object-formats residue. FW-48 reduced to the factory `post→author` hop. FW-43 gained the
observable try_-user gap (author-archive `try_text` renders empty while text/join resolve) as
motivation for the fork collapse.

---

Everything below is the pre-ship record: the 2026-07-21 rescan, the build composition, and the
grilled design with its locked decisions. Line references are as-of that date and have since
moved.

**The row this archives (FW-49, owner of the seam return-shape design):** grow `bws_resolve_field_values`' frozen
`string[]` return into one richer shape serving all pending consumers — text/title
callback convergence (link identity; deletes the hand-rolled traversal copies),
datetime **FW-3(b)** (value + field-object formats), user kind (**FW-48** adds the missing
`bws_read_resolved_source` user case). Constraint: existing `string[]` callers
(email/phone) stay byte-identical — additive shape or parallel entry.

## Assessment 2026-07-21 (pre-design rescan)

Read of the seam, the three base callbacks, datetime's clones, and the link helpers.
Findings, in the order they change the work:

1. **The kind data is already there and gets thrown away.** `bws_run_traversal` returns the
   source array unchanged in shape (`traversal-pipeline.php:66-80`); the seam holds it in
   `$sources` (`field-helpers.php:490`) and iterates with `$source` in hand
   (`:493`), then keeps only the string (`:494-499`). Carrying `{kind,id}` out is ~1 line —
   no signature change to `bws_read_resolved_source`, no extra reads. **Stop discarding,
   not go fetch.** This is the single biggest correction to the row's framing.
2. **The loop is already extracted — in datetime.** `bws_datetime_collect_list()`
   (`datetime-tags.php:1070-1089`) contains zero datetime: limit / `array_slice` / render /
   drop-empties / capture-first / `implode`. Its doc (`:1057-1060`) was already written
   anticipating FW-3's kind dispatch. text/title inline the identical logic four times
   (`base-tags.php:691-738`, `:1184-1225`), differing from each other only in the `use`
   ternary and `$opts` vs `$options`. So FW-49 **borrows** this shape rather than designing
   one; the FW-3(b) dependency partly inverts.
3. **`first: mixed` is the only thing that doesn't generalize.** It returns the *item*, so
   all four call sites hand-write a kind sniff (`instanceof WP_Term` /`is_numeric`,
   `datetime-tags.php:1176-1179`, `:1194-1195`). Adding `user` grows every call site;
   `site` has no item at all (sentinel id 1) and doesn't fit.
4. **Proposed payload** — `array{values: array<array{value:string, id:int, kind:string}>,
   count:int}`. `count === 1` → identity is `values[0]`, no sniff, no per-call-site branch;
   user/site slot in as kind values; email/phone get byte-identical `string[]` via
   `array_column($r['values'], 'value')`; `implode` stays at L3 where `sep` already lives
   (the seam deliberately excludes `sep` — `field-helpers.php:449`).
5. **`meta_row` needs an explicit no-identity case.** Traversal kinds are
   `post|term|site|meta_row|user`; `bws_resolve_link_url` switches on `post|term|user|site`
   (`link-helpers.php:51-81`). A repeater row is not an entity. `id => 0` suffices — the
   `count === 1` gate already requires a truthy id.
6. **Non-divergence worth recording:** seam, text, title and datetime all apply `limit` the
   same way — slice the SOURCE list, then drop empties. So `limit` means "consider N
   sources", not "emit N values", everywhere. The expected inconsistency isn't there.
7. **Don't carry datetime's call-site `function_exists()` guards** into the shared helper.
   They are dead branches (`base-shared.php` loads at main file `:203`, before
   `datetime-tags.php` at `:207`; both long before render), and the `get_the_ID()` fallback
   is a *different* resolution than the factory's — a silent behavior fork if it ever fired.
   Distinct from the redeclaration guards wrapping the helpers themselves
   (`field-helpers.php:394`, `traversal-pipeline.php:65`/`:104`), which are legitimate.

**Scope corrections:** the row's "five hand-rolled copies" undercounted — datetime clones
the pair twice more (`datetime-tags.php:1165-1184`, `:1288-1307`), plus partials in
`class-tag-template-registry.php:274-277`/`:762-763` and two hand-built `string[]` sites
(`email-tags.php:290-292`, `phone-tags.php:558-560`). **Content left this row for FW-43** —
its srcTermIn loop is first-wins-and-`return` (`base-tags.php:1094-1104`), a selecting fold.
And "first-wins" mislabelled text/title: they collect-all then gate identity on
`1 === count($out)` (`:709-713`), which is exactly why the payload should be per-value.

**Prerequisite — FW-50.** The per-item fallback contract must be uniform before these
callbacks converge, or an accident freezes into the shared path: datetime suppresses per
item deliberately (`unset($item_opts['fallback_text'])`, `:1144`/`:1267`, contract at
`:1100`), text arms it and leaks the fallback into list output (GH #51), title is
uncorrelated (`bws_post_title_core` has no fallback logic — `content-tags.php:158-168`).
FW-50 removes the deprecated `fallback_text` active read path, which is the same edit.

## Build composition (2026-07-21)

**One build: FW-50 (leads) → FW-49 + FW-3(b) + FW-48.** These touch the same functions;
splitting them means writing throwaway compatibility code and re-verifying byte-parity on
the same callbacks twice.

> **STATUS 2026-07-21 (later): BUILD COMPLETE.** All five steps landed on main, one commit per
> step (b873a9c helper → 3c3f31d seam carry-out → bbe9e4c datetime → 77d3d39 text/title →
> step-5 user arms). Byte-parity held at every gate (45-case render-tag diff clean; mailto
> antispambot noise only). Outcomes: FW-49 → ledger; FW-3 narrowed to the field-object-formats
> residue; FW-48 seam halves shipped, factory post→author hop remains; try_'s missing user leg
> noted on FW-43 (author-archive try_text renders empty while text/join resolve). Tracker rows
> updated 2026-07-21. Release still gated on CHANGELOG prose review + the 1.16.0-vs-1.15.2 call.
>
> **STATUS 2026-07-21: the lead has LANDED.** FW-50 is work-complete in the tree, shipping in
> 1.16.0 (unreleased). **Remaining in this build: FW-49 + FW-3(b) + FW-48**, still to be done
> together per the reasoning below. FW-49's tracker gate is `ship:1.16.0` and clears on release.
> What FW-50 actually delivered, vs what this section assumed:
> - Cores read `fallback`; `bws_base_map_options()` is **deleted outright** (with the reverse
>   rename gone its body was an identity function) — so the `$opts` vs `$options` divergence
>   noted in finding 2 above is now moot: both are `$options` everywhere.
> - **Scope was larger than framed.** Six option builders registering `fallback_text` had zero
>   call sites (deleted); `bws_post_term_extraction_options()` was published-but-doc-drifted
>   (kept, key renamed). Neither was visible from the FW-50 row.
> - **The per-item fallback contract is now uniform** across text / datetime / try_ — the
>   precondition this build composition exists to establish. GH #51 fixed as part of it;
>   `bws_base_text_callback()` gained the all-empty fallback path it lacked.
> - Verified: 10/10 pure harnesses, testbed render checks on join / datetime / content / try_,
>   and the #51 mechanism proven at the core level (fallback present → `MISSING` per empty
>   item; suppressed → `''`).

- **FW-50 leads, not co-ships.** Cores read `fallback` directly, reverse-mappers delete. If
  convergence lands first, the shared collect helper must accept a contract that disagrees
  three ways, then get re-edited. *(Done — see STATUS above.)*
- **FW-49 + FW-3(b) are one edit, not two.** Datetime *owns* `bws_datetime_collect_list`
  today (4 call sites). Changing its return shape while leaving datetime on the old one
  means keeping `first: mixed` alive as a compat field alongside `values[]`, across a
  release boundary, in the densest callback file. Together it is one shape change with its
  call sites updated in place.
- **FW-48 belongs here on two counts.** (1) Its stated gate — the seam L2 `user` arm — is
  one `case 'user':` in the *same switch* FW-49 is already editing
  (`bws_read_resolved_source`, `field-helpers.php:398-426`, handles `site|term|meta_row|post`
  only). Everything else is already user-aware: traversal typedef (`traversal-pipeline.php:20`),
  step-engine input kinds (`:120`), ambient resolve (`:301-302`), ref-step user-meta read
  (`:533`), and `bws_resolve_link_url` (`link-helpers.php:90`). (2) **Its constraint (2) is an
  FW-49 bug in waiting** — user-analog reads live ONLY in the title/content callbacks
  (`base-tags.php:1084-1086`, `:1163-1165`); `bws_base_text_resolve_value()` has NO user arm.
  That function is the ABSORB seam join (`join-helpers.php:90-96`) and try_text read through,
  so converging text onto a seam with no user case ships a hole, then re-opens the same
  function to fill it. Landing the user arm *as part of* the convergence gives join/try
  inheritance for free.
- **Bonus: FW-48 proves the payload rather than assuming it.** The per-value
  `{value, id, kind}` design claims user/site slot in without new branches. Shipping `user`
  in the same build is the test of that claim.
- **Scope guard on FW-48:** seam user arm + factory `post→author` hop + resolve-value user
  arm ONLY. It also opens permalink/image analog questions (FW-47 is soft-gated on it —
  `src:author` is the non-ambient user source FW-47's permalink was waiting for). Leave
  those to FW-47 as its own decision; do not let them into this build.

**FW-43 stays separate.** It is a SELECTING fold (first-wins) where this is COMBINING
(collect-all); its parameterization over both lands in `generate_base_try_tags()`, the
densest callback with V8 byte-identity risk. Sequencing it after means it generalizes a
combining shape already proven in production, with the seam payload a fixed input rather
than a moving one. **FW-7/FW-8 further out** — FW-7's `code:` gate is a ~30-caller read
path, its own release; interaction with FW-49 is soft (the seam already bypasses
`bws_read_field`'s inference via explicit id).

## Design — GRILLED 2026-07-21 (decisions locked, ready to build)

Line refs are POST-FW-50 (`datetime-tags.php` shifted ~280 lines; `bws_datetime_collect_list`
now `:791`, call sites `:889`/`:907`/`:1012`/`:1030`).

**Target shape** — `bws_collect_value_list()` in `field-helpers.php`:

```php
bws_collect_value_list( array $items, callable $render, array $options ): array
// array{
//   value:  string,                                          // implode(sep, non-empty)
//   values: array<array{value:string, link:array{kind,id}|null}>,
//   count:  int,
//   link:   array{kind:string,id:int}|null,                   // values[0]['link'] iff count===1
// }
```

Helper owns: slice-to-`limit`, **per-item `fallback` suppression**, render, drop empties,
per-value link capture, the `count===1` gate, and the `sep` join. `$render` is
`fn($item, array $item_opts): array{value,link}|''`. Callers keep raw `$options` for
`linkTo`/`linkKey`/`newTab`/preview-label.

**Decisions from the grill (each supersedes the pre-grill analysis above):**

1. **Link identity, not entity reference.** The per-value payload carries `link` — the thing
   `bws_resolve_link_url` consumes (`post|term|user|site`, link-helpers.php:51-98) — NOT a
   resolved source. `null` (not `id => 0`) marks "no link identity", so `meta_row` and FW-9's
   entity-less kinds (date/search/404 — the tracker calls them "no field to read") need no
   sentinel. This is what keeps the shape clear of ADR 0002's rejected `{kind,id}`: link-
   wrappability is a property of the VALUE, not of the source kind.
2. **Both the gate and the per-value links are exposed.** `link` resolves the `count===1`
   rule once (it is a domain rule — a joined multi-result string is unwrappable as ONE link —
   not call-site policy); `values[]` keeps per-item links because individually-linked list
   items are plausible (a future join/list-item link mode). Note the rule is a `sep`-JOIN
   constraint, not a linking constraint.
3. **Suppression moves INSIDE the helper.** FW-50 left `unset($item_opts['fallback'])` written
   three times (`base-tags.php:695`, `datetime-tags.php:864`/`:987`). Safe to fold in because
   `bws_normalize_datetime_options()` is **purely additive** (`$mapped = $options` then only
   guarded assignments, no `unset` — datetime-tags.php:682-745), so `$mapped ⊇ $options` and
   `limit`/`sep` survive normalization untouched. Datetime call sites therefore pass `$mapped`
   as the helper's `$options` (today they pass raw `$options` while closing over a `$mapped`-
   derived `$item_opts` — two objects for one job). The three `unset` copies delete; #51
   becomes structurally un-reintroducible rather than convention-enforced.
4. **The seam does NOT ride the fold.** `bws_resolve_field_values` keeps its own plain loop
   (`field-helpers.php:493-499`) — it has no render callable, no `sep`, no suppression, and no
   `limit` slice at that point (already sliced `:490`). Routing it through the helper would
   mean building a closure to produce a payload it immediately flattens, on the FROZEN §V3
   function email/phone depend on byte-identically. It takes the ~1-line carry-out ONLY: stop
   discarding the `{kind,id}` already held in `$sources` (`:490`).
5. **Home = `field-helpers.php`, not `base-shared.php`.** Load order decides it: field-helpers
   is main-file `:113`, base-shared `:203`. The seam must not reach FORWARD; the tags-block
   callers reaching back to `:113` is the safe direction. (`bws_base_user_analog_read` lives in
   base-shared `:687` — which is why the seam's user arm is a plain `get_user_meta`, not the
   analog reader. Different concerns: seam reads meta, callbacks read analogs.)
6. **FW-48 SPLIT.** In this build: the seam `case 'user':` (one case in the
   `bws_read_resolved_source` switch, `field-helpers.php:398-426`) + the user arm in
   `bws_base_text_resolve_value()` (which has none today, while title/content do —
   `base-tags.php:1118`/`:1197`). Deferred to FW-48 proper: the factory `post→author` hop that
   makes `src:author` a user-facing feature, plus FW-47's permalink/image questions. **This
   makes the build a PURE REFACTOR — zero user-visible behavior change.** Consequence to own:
   the `case 'user':` is unreachable at runtime until the hop lands (ambient author goes
   through the callbacks, not the seam), so it is closed-hole-by-inspection, not tested.
   Its purpose is that converging text onto a user-less seam would ship a hole in the ABSORB
   seam (`join-helpers.php:90-96`, try_text) and force re-opening the same function.

**Order of work — one commit per step, direct to main (1.16.0 unreleased, already carries
FW-50; each step leaves a working tree):**

1. `bws_collect_value_list()` into `field-helpers.php` (payload + suppression + join).
2. Seam carries `{kind,id}` out of `$sources`; `bws_resolve_field_values` keeps its loop and
   its `string[]` return.
3. Datetime's four call sites → the new helper (pass `$mapped`), `first`-sniff → `link`,
   the two `unset` copies delete (FW-3(b)).
4. Text/title loops (`base-tags.php:701-748` + the title callback) → the helper; third `unset`
   deletes.
5. Seam `case 'user':` + `bws_base_text_resolve_value()` user arm.

**Verification — before/after byte-diff, not eyeballing.** A pure refactor must produce
byte-identical `render-tag` output, so the diff IS the assertion. Baseline captured on the
pre-change tree (45 cases, 44 non-empty) covering: text/title × (srcTermIn | src:ref) ×
(link | no-link) × (limit 1 | N), the #51 all-empty fallback path, datetime single+range in
both list modes, join (separator + template + ref-slot), try_, the ambient term archive, and
**email/phone as the frozen-§V3 control that must not move**. Script + baseline in the session
scratchpad. Plus the 10 pure harnesses after each of steps (2)/(3)/(4). No new matrix rows →
no "make them VISIBLE" obligation (that rule fires on new rows; this build adds none).

**Fixture facts the baseline depends on** (from `tools/fixtures/core-structures/manifest.php`
— do NOT guess these): `related_staff` lives on **page-matrix-post-meta**, not on staff (jane
FIRST, then tom); staff carry `main_line`/`contact_email`/`name_*`/`event_datetime`; department
terms are on the **matrix-terms-\*** pages, NOT on staff (staff have none); join wire syntax is
slot-prefixed `key:X|2-key:Y` (the `1:text,key,X` CSV form is FW-24's PROPOSAL, not shipped);
`try_` slots 2+ **require `use`** (bare `N-key:` renders empty — FW-51, not a fallthrough bug).

**Deliberately NOT carried:** datetime's call-site `function_exists()` guards. Dead branches
(`base-shared.php` loads at main file `:203`, `datetime-tags.php` at `:207`, both long before
render) whose `get_the_ID()` fallback is a DIFFERENT resolution than the factory's — a silent
behavior fork if it ever fired. The redeclaration guards wrapping the helpers themselves
(`field-helpers.php:394`, `traversal-pipeline.php:65`/`:104`) are legitimate and stay.
