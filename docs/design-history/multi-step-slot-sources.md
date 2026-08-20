# Archive: Multi-step slot sources — FW-71 (SHIPPED 1.17.0)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.
>
> **SITE-A / SITE-B are pseudonyms** for the two real client clones this record was measured
> against, substituted before publication. The measurements are unchanged; only the names are.

**Archived 2026-08-19 with the 1.17.0 release.** Shipped as [#101](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/101)
and the issues under it: a slot's SOURCE *is* a base tag's source ([I16]), the seam emits chain
wire instead of a flat triple, and `bws_fold_slot_flat_options()` was deleted ([#104](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/104)).
Verification closed with Experiments M/M2/R and #112's replay.

**Read this as the record of how the work was decided and built, not as a statement of how
anything currently works.** Live homes now: `CONTEXT.md` [I16] and the `same`-merge axis owned by
`bws_fold_chain_join()`; schemas in `docs/tag-reference.md`; CHANGELOG 1.17.0.

**Two things here outlived the ship and are still live** — §Verification owns the harvest/replay
instrument's design, which `tools/replay-tags.php`, `tools/diff-replays.php` and
`tools/run-converter.php` cite by path; and its §OPEN row on the content callback's post-side
collapse plus the hardcoded `same`-use prepend is still undecided, riding FW-43.

---

**Status: BUILT 2026-08-15** ([#104](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/104)),
on the 1.17.0 tree. Scoped 2026-08-13 in a grilling pass that reversed the framing this file
previously carried (see §History), and the scoping HELD: it was an emit change, not an arm rewrite.

**THE GATE IS HELD AND THE TWO ITEMS THIS BANNER USED TO HOLD OPEN ARE CLOSED** (corrected
2026-08-18 — the banner said "the USER leg has not shipped" and "Experiment R has not been run"
while §SETTLED row 44 and §Tier 2 already recorded otherwise, which is the supersession-in-place
this file's index exists to catch, in the file's own banner):

- **Experiment R RAN 2026-08-17 and the diff came back EMPTY on both clones** — [#107](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/107),
  0 changed of 11,155 (SITE-B) and 13,568 (SITE-A). §Experiment R records it.
- **The USER leg SHIPPED 2026-08-17** ([#108](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/108)),
  after R, with its own replay run and a positive control on a real clone. §Tier 2 records it.

**One run is still owed by the RELEASE, and it is not this work's:** [#112](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/112)
(the unusable-source refusal) is a deliberate behaviour change queued into the same release and was
on NEITHER side of R by design, so it takes its own replay run against a RE-HARVESTED census — R's
corpus predates the `view_*` conversion and holds no `src:view` at all, so replaying #112 against it
comes back empty and proves nothing. Expected diff written before the run: exactly the `src:view`
population on SITE-B, EMPTY on SITE-A. **The re-harvest goes into a FRESH directory** — `bin/harvest-tags.sh`
overwrites in place, so harvesting over `SITE-B-R/` would orphan the artifacts R's verdict rests on
(§Instruments, and it has already happened once). **The population was RE-COUNTED on the clones
2026-08-18 and the prediction holds**: SITE-B carries 15 occurrences across 5 posts — `Site Footer`
(4) and `Site Header` (2), both `gp_elements`, plus `Home` (5), `Page A`
(3) and `Page B` (1) — with 0 in postmeta, 0 in options and **0 `{{view_` prefix wire
left**, so the clone is post-conversion and a fresh harvest reaches the shape under test. SITE-A is
0/0, structurally rather than by sampling. **Size the expected diff before the run:** the two Elements
evaluate on every page, so their 6 occurrences multiply across the URL set — hundreds of changed
pairs, not 15, and a large diff must not be readable afterwards as over-reach. A side is `6fc9335`
(the merge's first parent, the last commit below #112), C side `main`; both declare 1.17.0, so the
build-identity digest guard is what separates them, exactly as in R.

What ALSO ran, and is evidence rather than the gate: a 35-case `render-tag` before/after diff on
`testbed` across four context kinds — 34 byte-identical, the one difference being `{{try_email}}`'s
per-render `antispambot` encoding (artifact #4 in §The seven things).

**Tracker row:** `docs/future-work.md` FW-71. That row is pointer-only; this is the detail home.

**Was:** `.claude/plans/verb-agnostic-slot-resolver.md` (FW-43). The file re-heads here because the
work moved, not the number — most of its content describes FW-71. What FW-43 retains is one small
extraction, recorded in §What FW-43 keeps.

**Absorbed:** FW-5 (the `try_core_fn`/`try_term_fn`/`try_site_fn` fork collapse — moved here with the
arm collapse that delivers it).
**Interacts:** FW-49 (shipped 1.16.0 — the combining half of the fold), FW-63 (closed 2026-08-05 —
did the BASE arms), FW-53 (`{{table}}` — its `entries` refusal relocates, see §What happens to the
five skips), FW-60 (slots on base tags — strengthened by this, not blocked on it).
**De-scoped:** `term_` (GH #63's collapsed fan) — takes route (3), the base-tag collapse. Its home is
now the FW-33 tracker row.

---

## §SETTLED index

Pointers, never content. The sections stay authoritative.

| Decision | Section | Settled |
|---|---|---|
| **A slot's SOURCE *is* a base tag's source — one concept, one language, one parser** | §The identity | ✅ 2026-08-13 (user) — the alternative was a translating seam; rejected. Storage still differs, and that is [I13]'s axis |
| **The seam EMITS CHAIN WIRE; it does not gain a kind-dispatch layer** | §The identity | ✅ 2026-08-13 — the factory and every FW-63 arm already read a chain-spelled `src`, so the slot half is an EMIT change |
| **`{{join}}` needs NO arm work — kind dispatch is inherited from the absorb seam** | §Tier 1 | ✅ 2026-08-13 — `bws_join_resolve_slot` → `bws_base_text_resolve_value`, converted by FW-63 |
| **`bws_fold_slot_flat_options()` is DELETED, not adapted** | §Tier 1, §What happens to the five skips | ✅ 2026-08-13 (user) — an adapter is the shape that guarantees the half-shipped divergence, and makes the carry serve flat and chain at once |
| **The resolved source is handed on as WIRE, not as a parsed structure** | §Wire, not a parsed structure | ✅ 2026-08-13 (user) — corollary of ADR 0004; NOT its own ADR |
| **No ADR. One CONTEXT invariant (I16) + an ADR 0004 corollary pointer** | §Where this is written down | ✅ 2026-08-13 (user) — the enduring thing is the identity, which is an invariant; "wire not parsed" is how it is implemented |
| **The seam SUPERSEDES the legacy source axes by contract** (explicit empties for `ref`/`srcTermIn`) | §The srcTermIn leak | ✅ 2026-08-13 (user) — the rule belongs to the seam that owns the source axis, not to each container |
| **The inert-chain warning is RAISED to base tags, not dropped from slots** | §The inert-chain warning | ✅ 2026-08-13 (user) — `meta_row` and unknown-slug only; no per-template fact |
| **The user leg ships LAST, as its own commit and its own replay run** | §Tier 2, §Verification | ✅ 2026-08-13 (user) — buys a genuinely empty diff for the refactor itself. **BUILT 2026-08-17 (#108)**; replay is the merge gate, not a build step |
| **Rows re-cut: FW-71 new, FW-43 retitled and shrunk** | §What FW-43 keeps | ✅ 2026-08-13 (user) — the plan file follows the work, not the number |
| **SHIPS IN 1.17.0** | §Verification → the gate | ✅ **2026-08-18 — decided BY the gate, on evidence, which is why it sat in §OPEN until the runs were in.** Experiment M + M2 passed, Experiment R passed on both clones (#107, 0 changed of 11,155 / 13,568), the user leg shipped with its own run (#108), and #112's owed run completed and was independently re-diffed. The exception list was closed BEFORE the runs so the gate could not be rationalised afterwards; nothing needed excepting. Row moved from §OPEN.
| **`term_` is NOT carried here — it exits via the base-tag collapse** | §History | ✅ 2026-08-13 (user) — `term_` wants a term picker and a base-tag collapse regardless |
| **Two clones get DIFFERENT treatment: SITE-B = depth, SITE-A = breadth** | §Verification | ✅ 2026-08-13 (user) — demanding full replay from both is what made SITE-A infeasible |
| **Experiment M runs FIRST and INDEPENDENT of this work** | §Verification | ✅ 2026-08-13 (user) — a real-wire migrator defect is a 1.17.0 blocker either way |
| **The harvest/replay seam is TWO artifacts (census + URL inventory), never one manifest** | §Instruments | ✅ 2026-08-14 — CORRECTS the `{tag_string, url, post_id, post_type}` row this plan first proposed: an Element has no URL of its own, so a census row has no `url` to hold. Replay is their product |
| **Replay covers the CARTESIAN and does not read Element display rules** | §Instruments | ✅ 2026-08-14 (user) — the cartesian is a SUPERSET of what display-rule reading yields, so nothing is missed and narrowing stays a filter over this output. Attestation (a container with its own permalink) splits the diff for free, with no GB coupling |
| **Instruments BUILT and smoke-tested** | §Instruments | ✅ 2026-08-14 — `bin/harvest-tags.sh` + `fixtures/harvest/harvest-tags.php` (env), `tools/replay-tags.php` + `tools/run-converter.php` + `tools/diff-replays.php` (here). Green end to end on `testbed` |
| **The converter is ADMIN-TRIGGERED — swapping the build migrates nothing** | §Verification step 2 | ✅ 2026-08-14 — CORRECTS "upgrade to 1.17.0; the converter runs". That is `bws-portal-system`'s behaviour, which `dev-plugin.sh`'s header describes; reading it as this plugin's is how the step got written. `tools/run-converter.php` invokes it |
| **A and C hold DIFFERENT tag strings, so the diff pairs through the converter's own mapping** | §Verification step 4 | ✅ 2026-08-14 — migration rewrites the wire; nothing else can supply the pairing. Best-effort by design, because a string can be migrated in a post and unmigrated in postmeta at the same time |
| **EXPERIMENT M PASSED on both clones** | §Experiment M — RUN | ✅ 2026-08-14 — 0 changed of 5,104 (SITE-B) and 13,657 (SITE-A) pairs. The 1.17.0 migrator is safe on real wire |
| **The `patterns_tree` shadow copy is a COMPLETENESS bug — FOUND here, FIXED elsewhere** | §Experiment M — RUN | ✅ 2026-08-15 — GH #98 → spec #99 → `1aad875`, both CLOSED. Orthogonal to FW-71; it never gated this work, and the claim that a migrated tag is done now holds |
| **Both clones are REPAIRED, so their pre-fix shadow population is spent** | §Experiment M — RUN | ✅ 2026-08-15 — fine for Experiment R (same version both sides, no migration boundary). A future M A-side needs a fresh pull |
| **EXPERIMENT M2 PASSED on both clones** | §Experiment M2 — RUN | ✅ 2026-08-18 — 0 changed of 5,104 (SITE-B, staged over TWO plugins' boundaries) and 13,445 (SITE-A). Re-run because the migrator moved after M: FW-71's converter half, the pattern-cache repair and #111 had none of them been seen against real wire |
| **A fresh pull was NOT needed — `--live` restores the paired predev snapshot** | §Experiment M2 — RUN | ✅ 2026-08-18 — CORRECTS the row above. `dev-plugin.sh` pairs the file swap with a DB snapshot in both directions, so the 08-14 predev is a valid A-side baseline for as long as it exists. Verified before starting: zero migrated-chain rows either clone |
| **The pattern-cache repair is NOT separable from the upgrade, so an M census LOSES rows** | §Experiment M2 — RUN | ✅ 2026-08-18 — it fires from the on-upgrade trigger before any script runs. Proved by building a skip flag, watching it isolate nothing, and deleting it. A row that ceases to exist is not a render change, and the diff currently reports one as a hard failure |
| **R needs no new instrument work, but DOES need build identity — closed before the build** | §Experiment R | ✅ 2026-08-15 — two same-version sides made "the swap never happened" indistinguishable from a pass. Runs now record the dev mount's commit + a tree digest; the diff refuses a build diffed against itself. Mutation-verified both directions |
| **BUILT: the seam emits chain wire, the flatten is deleted, both containers converted in one move** | §Tier 1, §Tier 2 | ✅ 2026-08-15 — `bws_fold_slot_chain_options()`. Three corrections the build made to this file are in §Corrections the build made |
| **BUILT: the inert-chain warning, on base tags as well as slots** | §The inert-chain warning | ✅ 2026-08-17 — GH #105 → `df145fe`, verified in the editor. This REVERSES the §Corrections row that recorded it as parked: the raised chain the seam supplies is what unblocked it, and #102's namer took the `$inert` out-param. Retired `source not supported`; added `no repeater field` |
| **The `same` merge's axis is OWNED by `bws_fold_chain_join()` and restated nowhere** | §Corrections | ✅ 2026-08-17 — GH #106. Three sites had gone stale naming a superseded axis (two of them inside `slot-fold.php`), which is where `CLAUDE.md` §Documentation ownership's axis-ownership rule came from. **The review of that same pass found THREE MORE, including the largest copy in the repo — `CLAUDE.md`'s own SLOT SOURCE HAND-OFF trigger row, in the file the rule was being written into.** The other two were `slot-fold-test.php`'s derivation clause and this plan's §Corrections row. Writing a rule is not applying it; the grep that finds the fourth site has to include the file stating the rule |
| **A HARNESS may name an axis it MECHANICALLY PINS — per clause, not per file** | — (repo policy) | ✅ 2026-08-17 (user) — `CLAUDE.md` §Documentation ownership. `slot-fold-test.php` §P16.4 states the axis and calls `bws_fold_chain_join()` case by case beneath it, so drift fails by name; its DERIVATION-SOURCE clause is pinned by nothing (add a third map, every assertion still passes) and took a pointer instead. The reason the exemption is this narrow: a stale comment never fails a suite |
| **An ARCHIVED plan is not corrected when policy changes; a LIVE one is** | — (repo policy) | ✅ 2026-08-17 (user) — `CLAUDE.md` §Spec lifecycle. `archive/handoff-3…md`'s "SPEC.md at repo root is the live spec" is EVIDENCE of how the repo worked, not a stale pointer. `combined-option-controls.md`'s present-tense "SPEC.md active `feat/field-selector`" was false about now (no artifact, no branch) and was corrected |
| **A `§Corrections` row states its axis in the PAST tense, dated** | §Corrections | ✅ 2026-08-17 (user) — the row records which reading was taken when; the live rule is at the owner. A fourth axis change adds a row rather than editing one, which is what kept readings 1 and 2 readable after they were withdrawn. No second carve-out on the ownership rule: present tense is a claim about now, so the fix is to stop using it |
| **A spec is a GitHub issue; the root `SPEC.md` artifact is retired** | — (repo policy) | ✅ 2026-08-17 (user) — deleted; `CLAUDE.md` §Spec lifecycle. Post-ship invariant migration unchanged. This plan's own spec is #101 |
| **The shared source namer is EXTRACTED and SHIPPED — ahead of the seam, on its own** | §The inert-chain warning | ✅ 2026-08-15 — GH #102 → `d45a5c7`. `bws_preview_source_segments()`; base calls it, both slot walks reach it through the flat-triple door that goes when the seam lands. Five per-container switches, no literals at a call site. One shape moved deliberately: a slot with `src:site` AND `srcTermIn` previewed a term hop the arms have never taken and now previews no source. The inert-chain WARNING is not in it — it needs the raised chain the seam supplies |
| **Opcache does not reach the replay path** | §Experiment R | ✅ 2026-08-15 — CORRECTS this section's own "most dangerous failure": opcache is disabled in the wp-cli container, so replay compiles from disk every run. The hazard is real for front-end curl checks and not for this |
| **EXPERIMENT R PASSED on both clones — the gate is HELD and FW-71 ships in 1.17.0** | §Experiment R — RUN | ✅ 2026-08-17 ([#107](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/107)) — 0 changed of 11,155 (SITE-B) and 13,568 (SITE-A), `5b0b98b` → `993cf16`, both declaring 1.17.0. Both silent-failure guards were OBSERVED FIRING rather than assumed (a self-diff refused, a wrong-census diff refused by the digest guard). The ship decision was conditional on #76 landing, and it landed |
| **The BUILT ZIP was activated on a clone, which no symlinked run can substitute for** | §Experiment R — RUN | ✅ 2026-08-17 — 205 entries, no `tools/`/`docs/` leak, six URLs loaded, no fatal, dev symlink restored. That is the only check that catches an unguarded `require` of a `.distignore`d path (bit once in 1.15.0) |
| **#108's run is DONE and its role was RE-CUT before it ran** | §Tier 2, [#108](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/108) | ✅ 2026-08-17/18 — neither corpus holds ambient-rooted `use(title)`/`use(content)` wire (every one sits behind a `refs` step; every ambient-rooted slot is a `key(...)` read), so the run CANNOT demonstrate the fix and was not written as though it could: **SITE-B = control, diff MUST be empty; SITE-A = observation, diff READ and triaged**, an empty one recorded as *"no exposed wire in the surveyed population"* and never as confirmation. Fix evidence is §T8 + the fixture. A positive control on a real clone kept the empty diff non-vacuous (`{{try_text use:title}}` empty on A, `Webmaster` on C, same author URL). Mechanics worth reusing: SITE-A's author stratum raised to all 9 URLs while other strata stayed at 3 (raising globally inflates both sides of pure-control strata), URL set drawn ONCE across all four runs, R's censuses REUSED with digest recorded rather than re-harvested. `try_title` is absent from both corpora — stated in the prediction, not discovered after |
| **#111 needed an EXTERNAL plugin to get any real-wire exposure at all** | — ([#111](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/111)) | ✅ 2026-08-18 — verified during M2 rather than by its own run, and recorded as such: no `type:'tag'` entry this plugin ships targets another deprecated name, and neither census holds a two-generation shape, so the fix shipped with ZERO in-repo exposure. `bws-portal-system`'s `portal_*`/`views_*` → `view_*` → base chain is exactly the shape; four cases through one `migrate_post()` on the SITE-B clone, second run changed nothing |
| **#112's replay RAN and matched its prediction — the release's replay obligation is discharged** | §Experiment R — RUN | ✅ 2026-08-18 ([#112](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/112)) — A `6fc9335` → C `a4ea34a`, censuses re-harvested into FRESH `SITE-B-112/` / `SITE-A-112/`. **SITE-B: 62 changed of 5,060** (2 attested, 60 synthetic), six distinct tag strings, all decomposing into the `src:view` population — 43 × `{{title src:view}}`, 14 × the image tag (all 14 landing on ONE 109-byte fallback string, which is what a fallback looks like), 2 × `{{text src:view|key:…}}`, and 3 CASCADING through `{{content}}`/`{{post_excerpt}}`, verified word-level as byte-for-byte the vanished view-tag output. **SITE-A: CHANGED 0 of 13,568, GATE HELD** — the structural control. `GATE FAILED (2 findings)` on SITE-B is the tool counting non-empty buckets; it cannot express an expected non-empty diff without `--map`. **The one finding is against the PREDICTION, not the result:** "exactly the `src:view` population" did not allow for container tags carrying it, so the reusable form is that a blanking change reaches `{{content}}` and `{{post_excerpt}}` wherever the blanked tag sits inside post content |

## §OPEN

Treating these as decided is the more common failure, so they get their own table.

| Open item | Section | Note |
|---|---|---|
| **Is the content callback's post-side collapse intended, or a latent gap?** | §What FW-43 keeps | Under a fanning post chain `bws_base_post_id_from_source()` takes the first target and drops the rest. Inherited from the old row; belongs with the selecting-fold extraction, not here |
| **Un-hardcoding the `same`-use prepend** | §What FW-43 keeps | Named as a natural fit for the fold extraction; never scoped. `{{join}}` could not take `per_slot_use` from the shared emit because of it |

---

## The identity

**A slot's source and a base tag's source are ONE concept.** Same wire language, same parser, same
resolution path. What differs is STORAGE — a slot folds its source into one option value nested one
level down; a base tag holds it in a bare `src` option — and storage is [I13]'s axis, exactly as
flat-vs-folded is.

This was already true of the code and only the flatten pretended otherwise. Three facts, all shipped
in 1.17.0:

- **The factory takes chain wire.** `bws_resolve_base_source()` routes `src` through
  `bws_fold_src_root_token()` (`traversal-pipeline.php`), which returns a legacy token unchanged and
  `''` for a chain that leads with a step.
- **One reader, both spellings.** `bws_fold_chain_from_options()` (`slot-fold-compile.php`) parses
  `$options['src']` as chain wire and falls back to the flat triple — so `src:ref|ref:x` and
  `src:refs,x` answer identically *on the same options array*.
- **Every FW-63 base arm asks `bws_base_src_resolution()`**, never a token comparison.

So a container does not need to learn kind dispatch. It needs to stop RE-SPELLING the slot's chain
as a flat triple:

> `bws_fold_slot_flat_options()` is replaced by `bws_fold_slot_chain_options()`, which emits the
> slot's resolved chain as **depth-0 chain wire in `$slot_opts['src']`** — no `ref`, no `srcTermIn`.
> Carry-forward, `same` resolution, the read axis and the #60 limit-era report all stay where they
> are; only the source axis changes shape. The emitter already exists (`bws_fold_emit_chain()`).

**The identity is SOURCE-only, and the read is NOT part of it — by decision, twice recorded.**
`src-chain-encoding.md` §The read folds into the chain as a TERMINAL step: read-as-chain-terminal was
SUPERSEDED 2026-07-31 (the read is a sibling bracket-kv token, `src(chain);use(x)`), and folding the
read on base tags was locked out 2026-07-28 as all cost and no payoff (`src:site/key(X)` reads worse
than `key:X`). Do not read the identity as licence to revisit either.

### The carry becomes chain-shaped

`src(same)` copies the prior slot's RESOLVED chain rather than four scalars. That deletes a branch
[I15] currently has to explain in prose: `$tax_inherit` exists only because a flat triple cannot
carry a step, so an inheriting slot that took only the root landed on the ambient entity. A chain
carries its steps by construction, so [I15]'s corollary ("an inherited source carries what it IS,
not merely its root") stops being enforced by a special case.

### Wire, not a parsed structure

The seam hands on a **chain wire string**, which `bws_fold_chain_from_options()` re-parses. The
alternative — a parsed chain in a side key — was rejected: `$options['src']` is already read by
several things that are not the chain parser (`bws_fold_src_root_token()`, `bws_limit_default()`'s
era selection, the preview namer), so a side key must be taught to each of them, which is the copy
problem the leaf/twin rule exists to stop, arriving as a second way to state a source.

**Re-leveling is lossless and goes the safe direction.** Bracket alternation is by depth with two
pairs only (`()` at level 1, `[]` at level 2 — `BWS_FOLD_BR_PAIRS`), and `bws_fold_emit_chain()`
takes the pair at `$enclosing_level + 1`. A slot's chain sits at enclosing level 1; re-emitting at
enclosing level 0 converts `[` → `(`. It only ever goes SHALLOWER, so it cannot run out of pairs —
had the direction been the other way, a deep chain would have had nowhere to go and this decision
would be wrong.

**Consequence for the required assertion:** the emitted depth-0 string DIFFERS from the stored slot
value by design. The idempotence check is a fixed point on the re-leveled form —
`emit₀(parse(emit₀(chain))) === emit₀(chain)` — **never** a comparison against the stored slot wire.
Written the naive way it fails spuriously on every chain carrying a `limit`, and the fix someone
reaches for is to loosen it. Seed it with `same`-RESOLVED synthetic chains, not only the author-wire
corpus: the chain the seam emits is one no author ever wrote, so the existing corpus does not cover
the shapes most at risk.

## Tier 1 — `{{join}}`

**No arm work.** `bws_join_resolve_slot()` (`join-helpers.php`) delegates to
`bws_base_text_resolve_value()`, converted by FW-63, whose list branches run the whole compiled chain
via `bws_base_post_ids_from_source()` / `bws_base_term_ids_from_source()`.

| Site | Work |
|---|---|
| `bws_fold_slot_chain_options()`, `slot-fold.php` | new; same body, chain-wire emit, both `chain` returns gone |
| `bws_fold_slot_flat_options()` | deleted |
| `bws_join_callback()`, `base-tags.php` | swap the call |
| `base-tags.php`, `$explicit_id` threading | `'site' !== $slot_opts['src']` → `'site' !== bws_base_src_resolution( $slot_opts )['kind']` ([I11]) |
| join's `'steps' => ['terms']`, `base-tags.php` | → `['refs','terms']` |
| `bws_build_join_preview_label()` walk | onto the shared source namer (below) |

## Tier 2 — `try_` (and it IS the FW-5 collapse)

The four hand arms in `generate_base_try_tags()`'s callback (`class-tag-template-registry.php` —
anchor on the arms, not line numbers) test `$last_src` / `$stm_raw` directly. They collapse to one
`bws_base_src_resolution()` switch feeding the fn table. `try_` KEEPS the fn table: only `text` has
an absorb-seam resolver, so dispatching to base resolvers would need eight more, which is FW-60
territory.

| Today | Becomes |
|---|---|
| srcTermIn arm — `bws_get_srcterm_terms()` + hand break-on-limit | `kind:'term'` → `bws_base_term_ids_from_source()` → `$tcf` |
| site arm | `kind:'site'` → `$sf ?? $cf( 0, … )` |
| term-ambient arm | folds into `kind:'base'` → resolve the factory once, branch `$base['kind']` |
| post arm (`current` \| `ref`) | `kind:'post'` → `bws_base_post_ids_from_source()` → `$cf` |
| — | **`kind:'user'` — the fn table's missing leg** (see below) |
| — | `kind:'meta_row'` → skip; the table tag owns that consumer |

Also: `email-tags.php` / `phone-tags.php` compare `'site' === $options['src']` on the slot options
they are handed → kind test. `try_`'s `'steps'` registration widens. Its preview walk takes the same
shared namer as join's.

**The user leg is a documented parity defect, and it ships LAST.** [I6] says a `try_` slot must
resolve identically to the same base tag standalone; today `{{try_text use:title}}` on an author
archive renders empty while `{{text}}` and `{{join}}` resolve (`text-test-matrix.md` T8.6, verified
2026-08-11). One function serves it — `bws_base_user_analog_read( $tpl_key, … )`, whose analogs exist
for `text`/`title`/`content` only ([I9]). It is the ONE thing here that is not byte-identical, so it
lands as its own commit after the refactor's replay comes back empty, with its own run whose expected
diff is exactly the author-archive rows.

**BUILT 2026-08-17 (#108)**, on `fix/i6-author-archive-try-parity` after #107's diff came back empty
on both clones. A `try_user_fn` on the three templates plus `case 'user':` in both dispatcher
switches; the arm table was NOT edited, which is what asserting the row ahead of its wire bought.
Two corrections the build makes to the paragraph above, both found by reading code rather than
re-reading the plan: (1) **the renderer is per-template, not `bws_base_user_analog_read( $tpl_key, … )`
derived in the dispatcher** — deriving it there hands the other six families a `user` arm returning
`''`, which routes them off the fn-absent fallthrough and therefore off the mode-2b gate, a silent
change to six families inside the release whose claim is that nothing else moved; (2) **the run's
expected diff is NOT "exactly the author-archive rows"** — neither corpus holds ambient-rooted
`use(title)` wire, so the run is a REGRESSION CONTROL (SITE-B must be empty; SITE-A is read and
triaged) and the fix's evidence is `text-test-matrix.md` §T8. Key-mode user-meta reads come with the
leg necessarily, since its renderer already performs them.

**Byte-identity risks, all concentrated in Tier 2:**

- srcTermIn's break-early-while-hopping-terms vs `bws_collect_value_list()`'s slice — same intent,
  different shape.
- Per-arm link-wrap entity type (`term`/`site`/`post`) and the single-result gate taken AFTER the
  limit slice.
- `$allow_loop_fallthrough` (mode 2b, flat repeater row) has no kind and must survive as a post-arm
  special case.
- **The #60 limit-era write-back becomes load-bearing.** The emitted `src` is chain wire on EVERY
  slot, so `bws_limit_default()` read off it answers *unlimited* on legacy flat slots. Both
  containers already write the resolved number back explicitly; that line stops being a nicety.

## The `srcTermIn` leak

`bws_fold_chain_from_options()` reads `$options['srcTermIn']` and **appends a `terms` step** whenever
the root isn't `site`. So a tag-level `srcTermIn` surviving in `$slot_opts` grows a step on every
slot's chain — and now it composes with whatever the slot itself said. This is the same leak try_
already closes by writing `srcTermIn` always-even-empty; it returns through a different door.
(`ref` is safe: that read is gated on `$raw === 'ref'`, and `$raw` is chain wire.)

**The seam supersedes.** Its returned array always includes explicit empties for every legacy source
axis it supersedes, so merging it over anything is sufficient by contract. Containers merge and stop
thinking about it — which matters because join builds `$slot_opts` from the seam alone while try_
merges into `$eval_opts` (it needs tag-level `as`/`size`/`linkTo` down), so a container-side rule
would be one caller carrying it for the other's sake.

This is an [I15]-class failure: a leaked step returns a **plausible value from the wrong entity**,
not an empty. The harness assertion must name the leak, not merely render green.

## What happens to the five skips

Only `chain` goes, and it goes by **dissolving** — there is no flat spelling left to fail at, so the
branch has nothing to test. `read` / `inherit` / `step:refs` / `step:terms` are correct at any emit
shape and stay; the three non-`chain` refusals exist because the author-facing answer differs per
case, and deleting them wholesale (as the old row's wording implied) would remove three correct
refusals.

`entries` is the one shape still refused, and the refusal MOVES: it belongs to the container that
consumes a `meta_row`, not to the flattening. `{{table}}` therefore stops waiting on this row and
waits on its own arm.

## The inert-chain warning

Deleting the flatten deletes `'chain' => 'source not supported'` — today the only author-facing
signal that a chain is inert. `base-shared.php` states the base tag has always had this hole:
`entries` is not offered on base tags because *"no base-tag arm consumes a meta_row, so offering it
would author a chain that renders nothing"* — but ADR 0004 makes hand-authored wire reachable, and
it fails silently there.

**So the warning is raised, not dropped.** The shared source namer flags any chain whose statically
known resolved kind no arm consumes. Two values, decidable with NO per-template knowledge:

- `meta_row` — no arm consumes it, on any tag
- `''` — unknown slug; the engine answers empty and the chain short-circuits ([I14])

`'base'` (root-only, ambient) is statically unknowable and never flags. The richer version — "this
template doesn't consume terms" — needs a per-template fact, and a hand-kept list of that shape is
the drift the leaf/twin rule exists to stop. Out of scope until there is a reason.

**The namer is EXTRACTED, not written.** `bws_build_preview_label()` already builds source segments
inline from `bws_fold_chain_from_options()` — one segment per `refs`/`terms` step, a registered root
named in author terms (#83). That comes out into one function all three previews call; a container
wanting different text takes a PARAMETER (the `$allow_same` precedent), never its own literal.

**SHIPPED AHEAD OF THE SEAM** (GH #102, `d45a5c7`, 2026-08-15). `bws_preview_source_segments()` in
`preview-helpers.php`; the base builder calls it, and both slot walks reach it through
`bws_try_preview_source_part()`, now the flat-triple door onto it, which goes when the seam hands over
chain wire. Five switches carry the per-container differences (`named_current`, `lead`, `roots`,
`site`, `terms`) and the table lives in `docs/editor-tag-previews.md` §Context part. Splitting it out
early is what keeps the preview off the seam's diff: when a slot's source becomes chain wire, the two
slot walks lose their door and gain nothing else.

**SHIPPED 2026-08-17** (GH #105, `df145fe`, verified in the editor). It was not in #104 — it needs a
statically resolved kind, which a slot's flat triple cannot answer, so it landed after the seam rather
than with the namer. Two departures from the sketch above: `meta_row` was CUT (an `entries` step with
its field set is UNIMPLEMENTED, not inert — see §Corrections), and an unregistered or RETIRED root
token was added, which the sketch had not counted. Owner: `bws_preview_inert_warning()`; detection is
`bws_preview_source_segments()`'s `$inert` out-param. Author-facing text lives in
[`editor-tag-previews.md` §Inert-chain warning](../../docs/editor-tag-previews.md). One shape moved with the extraction,
deliberately and recorded in the harness: a slot with `src:site` AND a `srcTermIn` previewed
`→ <Tax> Term` and now previews no source segment. Reading through the shared chain inherits what the
arms do with that pair (the site read wins, the term step is dropped), so the old text described a hop
that has never happened.

## Verification

**Superseded 2026-08-19 by `tools/harvest-replay/README.md` + the three tool files' own
docblocks** (the instrument moved from flat `tools/` to `tools/harvest-replay/` the same day).
This section is left exactly as written — historical record of the original design, not a live
reference — per this doc's own rule that an archived plan is not corrected when policy changes.
Read the README for the current, maintained description.

Two experiments, different baselines, run in that order. **Experiment M is independent of this work**
— it tests 1.17.0 as it stands, so a real-wire migrator defect is a 1.17.0 blocker whether or not any
of this gets built, and it should run before the build starts.

**Experiment M — the migrator, against wire nobody designed.** Baseline pre-1.17.0.
1. Harvest wire + render on the pre-1.17.0 clone → `A-wire`, `A-render`
2. Upgrade to 1.17.0 **and RUN the converter — it does not run itself**
3. Harvest again → `B-wire` (changed — that is the point), replay → `C-render`
4. **Assert `A-render ≡ C-render`.** Diff `A-wire` vs `B-wire` as reviewable output.

**Step 2 was written wrong and the correction matters.** This plugin's converter is
admin-triggered (`wp_ajax_bws_scan_tags` / `wp_ajax_bws_migrate_tags`); there is no
version-gated upgrade routine and NOTHING fires on a page load. Swapping in a newer build and
loading a page migrates nothing — that is `bws-portal-system`'s behaviour, which is what
`dev-plugin.sh`'s header describes, and reading it as this plugin's is how the step got
written. `tools/run-converter.php` invokes it the way the admin button does.

**Step 4 needs a translation the plan did not have.** Migration REWRITES the wire, so `A-render`
and `C-render` hold different tag strings and cannot be keyed against each other at all. The
pairing has to come from the converter itself: `run-converter.php` emits `mapping.jsonl`
(old → new, derived the way `verify-migration.php` derives it — the same two shipped calls
`migrate_post()` makes, in that order, never by pairing before/after lists by position), and
the diff takes `--map`. The translation is BEST-EFFORT by design, because a tag string can live
both in a post (migrated) and in postmeta (out of reach, still old), so the same string appears
on the B side twice over; falling back to the untouched key is what stops that reading as a
vanished tag.

This is `verify-migration.php`'s property extended to real wire, and it is the only place five
migration entries meet wire nobody wrote. A bad migration is permanent: a migrated tag is done, and a
later correction cannot reach wire an earlier run already rewrote.

**Experiment R — the resolver.** 1.17.0-current vs 1.17.0+change, same wire both sides. Crosses no
migration boundary, so no DB restore is needed at all.

**R IS M MINUS THREE STEPS AND NEEDS NO NEW INSTRUMENT WORK** — no converter run, no `B-wire`
harvest (the wire is identical both sides), no `--map`. One census, one `urls.tsv`, both reused:
`replay --label=before` → swap the dev source → `replay --label=after` → diff, expecting EMPTY.
It cannot run before the build exists, because the build is one side of the diff.

**It does have one failure mode M did not, and it was closed BEFORE the build (2026-08-15).** Both
sides declare 1.17.0, so the version tripwire passes trivially and nothing said the swap had
happened — a branch not switched, or a worktree symlink not repointed, diffs EMPTY, which is R's
PASS condition. Each run now records the dev mount's git HEAD plus a stat-only digest of its PHP
tree, and the diff FAILS when two same-version sides report the same commit AND digest. Verified
in both directions: two runs of one build report 232 identical pairs and CHANGED 0 and are now
refused by name; a touched working tree warns instead.

**Correction while closing it: opcache is DISABLED in the wp-cli container replay runs in.** The
stale-bytecode hazard this section calls "the most dangerous failure this instrument has" governs
LSPHP, which serves front-end requests; replay renders in-process and compiles from disk every
time. The version tripwire stays — it is cheap and still answers the M question — but it was not
earning what was claimed for it, and the hazard it was aimed at reaches the front-end curl checks,
not this path.

### Prereqs, in order

**M's baseline is perishable and nothing else here is.** Confirmed 2026-08-14: SITE-B and SITE-A
both still run a pre-1.17.0 build. The moment either upgrades, its converter runs on the real DB and
that site's `A-wire` is unrecoverable — a migrated tag is done. Pull before anything else.

One pull serves both experiments: run M, and the post-M clone (now 1.17.0, migrated) IS R's input,
since R is same-version-both-sides and crosses no migration boundary.

Per clone, and the order is load-bearing:

1. `bin/pull.sh` (both sites are `readonly` in `sites.conf`)
2. Confirm the clone boots the SITE's own plugin, not the dev symlink — the swap is step 6, not step 0
3. `bin/harvest-tags.sh <site>` → census + the URL set, **chosen ONCE and reused on both sides**
4. `bin/replay-tags.sh <site> --label=A`
5. `bin/snapshot.sh --site <site> --save pre-upgrade` — **before the FILE SWAP**, per
   `dev-plugin.sh`'s own header: the newer build migrates on the next page load, not on activation,
   so a snapshot taken after the swap has already lost the baseline
6. `bin/dev-plugin.sh --site <site> bws-gb-dynamic-tags-extensions --dev`, then one page load
7. `bin/harvest-tags.sh <site> --out=fixtures/harvest/<site>-B` → `B-wire` for the reviewable diff
8. `bin/replay-tags.sh <site> --label=C` — same `urls.tsv` as A
9. `php tools/diff-replays.php <A> <C> <census>`

### Instruments

`bin/pull.sh` / `bin/snapshot.sh` / `bin/dev-plugin.sh` in the env repo already do the clone
lifecycle; `sites.conf` has both live sites (**SITE-B** and **SITE-A**, both
`readonly`). `dev-plugin.sh`'s header already analyses M's exact hazard: a newer
build migrates the cloned DB on the next page load, not on activation, so the snapshot must precede
the FILE SWAP.

**BUILT 2026-08-14**, smoke-tested end to end on `testbed` (4 URLs × 293 tags, all byte-identical):

- **Harvest — env repo.** `bin/harvest-tags.sh` + `fixtures/harvest/harvest-tags.php`.
  Plugin-agnostic; `bws-portal-system` wants it too.
- **Replay — this repo.** `tools/replay-tags.php`, one URL per invocation.
- **Diff — this repo.** `tools/diff-replays.php`, plain PHP, no WP. Exits non-zero on any change.

**THE SEAM IS TWO ARTIFACTS, NOT ONE MANIFEST.** The `{tag_string, url, post_id, post_type}` shape
this plan first proposed cannot exist: most tags live in Elements, and an Element has no URL of its
own, so a census row has no url to hold. It splits into `census.jsonl` (every occurrence + its
container — exhaustive, cheap) and `urls.jsonl` / `urls.tsv` (the inventory, stratified and sampled
deterministically). Replay is the PRODUCT of the two. That over-covers — it renders tags against
contexts they never appear on — and the over-coverage is deliberate: it is a superset of what an
Element-display-rule reader would produce, so narrowing later is a filter over this output rather
than a different instrument.

**Attestation falls out for free.** Where a container IS ordinary public content its URL is just
that post's permalink — exact, no display-rule parsing, no GB coupling. Those pairs are `attested`;
everything else is `synthetic`. The diff buckets by that, so the gate can bind hardest where there
is proven front-end exposure. It does not soften the gate: any change fails.

**Migratability is recorded, not assumed.** The converter scans `wp_posts` only, so the census also
sweeps options, postmeta and termmeta and flags those rows `migratable: false`. How much unreachable
wire exists is itself an Experiment M finding (`verify-migration.php` §5 proves the reach limit;
this measures it).

Four constraints the build turned up, each of which had a wrong obvious answer:

- **`tools/` is `.distignore`d, so a clone running the site's RELEASED build has no
  `wp bws render-tag`** — and M's A-side is exactly that build. Replay is reached instead at
  `/plugins/bws-gb-dynamic-tags-extensions/tools/replay-tags.php`: the dev repo is bind-mounted
  read-only into both containers, so ONE replay implementation renders both sides whatever the site
  has installed. Two implementations would produce a clean diff for the wrong reason.
- **One process per URL, by construction.** `wp()` runs the main query once; ambient context is a
  property of the process. What batches is the CONTAINER — `bin/replay-tags.sh` opens one and loops
  `wp` inside it, so the cost is a WP bootstrap per URL, not a Docker start per URL.
- **The opcache tripwire is a RUNTIME observation, not a file hash.** Stale bytecode means the bytes
  on disk are already the new ones; only the executed code is old. Replay compares runtime
  `BWS_DYNAMIC_TAGS_VERSION` against the `Version:` header read fresh from the plugin file and
  aborts on mismatch.
- **Volatility has to be measured, not reasoned about.** A and C render at different wall-clock
  times, so a "now"-reading tag differs for reasons that are not the migration. Each tag renders
  TWICE in process and is flagged `volatile` on disagreement; the diff excludes those from the
  verdict and counts them separately. Run headers carry a timestamp so a day boundary between sides
  is visible.
- **An artifact outlives its corpus, and nothing said so** (added 2026-08-17, after it cost real
  evidence). `bin/harvest-tags.sh` overwrites the census IN PLACE, in the directory the labelled
  artifacts already sit in, so a re-harvest silently orphans every earlier artifact beside it: the
  files still parse, still diff, and still report a verdict, but against a corpus that no longer
  exists. Experiment R's SITE-B pair was orphaned four hours after it ran, by #112's census step
  (`fixtures/harvest/SITE-B-B/_superseded/`). The run header now records a CONTENT digest of the
  census, and the diff refuses both shapes — two sides on two corpora, and a supplied census that
  is not the rendered one — exactly as it already refuses a build diffed against itself. Differing
  sides stay legal under `--map`, which is Experiment M's normal shape and the statement that the
  divergence is intended. A row count would not have caught it: the variant corpus used to
  mutation-verify this has the SAME `census_rows` and a different digest, because occurrences and
  distinct tag strings are different quantities.

Non-vacuity is asserted in the diff, because the failure this instrument invites is a clean result
from an unbootable clone or a botched swap: two empty artifacts pass every pairwise check while
proving nothing. A run where no A-side render produced output FAILS.

### Experiment M — RUN 2026-08-14, PASSED on both clones

Both live sites were still pre-1.17.0 (1.16.0), so the baseline was intact. Clones pulled, A
harvested and replayed, converter run, B harvested, C replayed at A's URL set, diffed through the
converter's own mapping.

| | SITE-B | SITE-A |
|---|---|---|
| A → B plugin | 1.16.0 → 1.17.0 | 1.16.0 → 1.17.0 |
| census rows | 250 (post 158, meta 92) | 342 (post 208, meta 131, option 3) |
| URLs replayed | 44/44, 0 context mismatches | 106/106, 0 context mismatches |
| tag strings rewritten | 31, across 5 posts | 26, across 8 posts |
| pairs compared | 5,104 | 13,657 |
| volatile (excluded) | 0 | 123 |
| **CHANGED** | **0** | **0** |
| non-vacuity | 2,122 non-empty | 6,341 non-empty |
| verdict | **GATE HELD** | **GATE HELD** |

Every rewrite was the chain migration (`{{text src:ref\|key:x\|ref:vendor}}` →
`{{text src:refs,vendor,limit(1)\|key:x}}`), with `as` canonicalised to the front on image tags —
i.e. step 8's serialization ordering, visible on real wire. Report and run agree on both sites: a
second scan finds nothing left.

**The one substantive finding was a COMPLETENESS bug, not a correctness one — GH #98, now FIXED
(spec #99, `1aad875`, 2026-08-15).** Of SITE-B' 31 rewritten tag strings, all 31 new forms were in
`post_content` and **30 old forms survived in `generateblocks_patterns_tree` postmeta**.
`migrate_post()` step 5 writes with `$wpdb->update()` deliberately ("avoids hook side-effects and
duplicate revision from `wp_update_post`"), and GB Pro rebuilds that tree on `wp_after_insert_post`
— so no hook fired and the cached tree kept pre-migration wire, which the pattern inserter then
re-seeded into fresh content. "A migrated tag is DONE" did not hold while a shadow copy existed.

The shipped fix rewrites ONLY the cached content field and is content-agnostic rather than
migration-gated (a post migrated by an EARLIER run has correct content, a stale tree, and is
invisible to `scan()`). Rebuilding the whole entry was rejected on measurement, not principle:
previews depend on request context and `scripts`/`styles` are derived from them by string-matching,
so a rebuild silently emptied three SITE-A patterns. Owner doc is CLAUDE.md's pattern-cache
trigger row; harnesses `tools/test/pattern-cache-test.php` + `verify-pattern-cache.php`.

**Both clones were repaired by that verification run, so the pre-fix shadow population is gone.**
That costs nothing for Experiment R (same version both sides, no migration boundary) but a future
M A-side needs a fresh pull.

**The census's `migratable: false` on non-post containers is still CORRECT and should not be
"fixed".** It describes what `scan()` sweeps, which is `post_content` only; the pattern-cache
reconcile is a targeted repair of one known meta key, not a scan. Container-granularity is the right
granularity for that flag.

**What this does NOT establish.** Nobody in the wild has authored a multi-step slot, because nothing
could. A clean M is evidence about the population that EXISTS. It says the 1.17.0 migrator is safe
on real wire; it says nothing about the new capability, which still rests entirely on the matrices
and pure harnesses.

### Experiment M2 — RUN 2026-08-18, PASSED on both clones

Re-run because the migrator MOVED after M: `11b57a8` (FW-71) changed what the converter writes,
`1aad875` added the pattern-cache repair, and `9874ede` (#111) changed how a rename chain resolves.
Experiment R crosses no migration boundary, so none of the three had been seen against real wire.

No fresh pull — `dev-plugin.sh --live` restores the paired `predev` snapshot as it swaps files back,
and both clones still held theirs from 2026-08-14. Confirmed genuinely pre-1.17.0 before starting:
zero rows of migrated chain wire, 40 (SITE-B) and 34 (SITE-A) legacy flat.

**SITE-B ran STAGED, because two plugins migrate here.** `bws-portal-system` 5.7.0 registers
`view_*` → base root migrations (its #71), so an unstaged run would cross two boundaries at once and
a failure would need bisecting afterwards.

| | SITE-B C1 (this plugin) | SITE-B C2 (+ portal-system) | SITE-A |
|---|---|---|---|
| A → B | 1.16.0 → 1.17.0 | 1.17.0 both, PS 5.6.0 → 5.7.0 | 1.16.0 → 1.17.0 |
| tag strings rewritten | 31 | 10 | 26 |
| pairs compared | 5,104 | 5,060 | 13,445 |
| volatile (excluded) | 0 | 0 | 123 |
| **CHANGED** | **0** | **0** | **0** |
| non-vacuity | 2,122 non-empty | 2,078 | 6,341 |
| verdict | **GATE HELD** | **GATE HELD** | see below |

End to end (A → C2, mappings composed) SITE-B is 5,104 identical, CHANGED 0. Every `view_*` rewrite
was what #71 promised, `{{view_title}}` → `{{title src:view}}` included.

**#111 got its first real-wire exercise here, and it needed an external plugin to get one.** No
`portal_*` or `views_*` occurrence exists in either census, and nothing this plugin ships chains two
renames — so the fix stayed vacuous on stored wire. But portal-system registers `portal_*` and
`views_*` → `view_*` as plain renames and 5.7.0 registers `view_*` → base, which makes
`{{portal_title}}` exactly #111's shape: an OPTION-LESS intermediate. A throwaway probe on the
SITE-B clone put all four cases through one `migrate_post()` — `{{portal_title}}` and
`{{views_title}}` both reached `{{title src:view}}`, the optioned sibling reached
`{{text src:view|key:home_introduction}}`, and a second run changed nothing. That closes the item
portal-system's own commit left open ("asserted upstream but NOT against this repo's alias table,
marked unverified in situ").

**SITE-A: 13,445 comparable pairs identical, CHANGED 0, and 212 pairs the diff could not compare.**
All 212 are one shape — two pre-1.6 `{{post_acf_date_time_range …}}` strings × 106 URLs — and the
cause is not the migrator. Post 74510 is a `wp_block` whose `post_content` already held modern
`datetime_range` wire while its `generateblocks_patterns_tree` meta still held the pre-1.6 forms,
from a migration predating the clone. Those tags live in postmeta ONLY, so the converter never
reached them (`unreached_by_converter` classified them correctly, both in this run and on 08-14).
What removed them is the pattern-cache repair — `bws_dynamic_tags_pattern_cache_status` names
`trigger: upgrade`, one entry reconciled. The A side rendered them as literal tag text, because the
pre-1.6 renderers were deleted in the 1.6 consolidation and 1.16.0 has nothing to render them with.

**THE REPAIR IS NOT SEPARABLE FROM THE UPGRADE, AND A SKIP FLAG PROVED IT.** `run-converter.php`
briefly grew a `no-reconcile` token to isolate the migrator from the repair; the arm run under it
produced the same 212. The repair also fires from `bws_dynamic_tags_rebuild_allowlist_on_upgrade`,
on the first request after the version moves — before the script runs at all. The flag was deleted
and the mechanism recorded where it was built. Any future M must expect the B-side census to LOSE
rows on this axis, and a row that ceases to exist is not a render change.

**Two instrument defects, both found by running it, both fixed in `9874ede`'s successor commit.**

| # | What it did | What it was |
|---|---|---|
| 1 | SITE-B' B-side reported 30 surviving legacy strings after a clean migration | `run-converter.php` called `scan()` + `migrate_post()` but NOT the pattern-cache reconcile the admin button ends with, so the driver under-reported the repair and read exactly like the bug #98 fixed four days earlier. Running the reconcile by hand took `generateblocks_patterns_tree` legacy wire 32 → 0 |
| 2 | SITE-B C2 failed as "the swap did not happen" | the build-identity guard assumes the only migration boundary is THIS plugin's version bump. Staging portal-system holds this build fixed on both sides while the wire moves underneath it. A non-empty mapping now distinguishes the two — evidence, not an operator assertion. Mutation-verified: same build with no mapping, and with an empty one, still fail |

**A third read as a defect and was not.** The derived new strings for those two datetime tags exist
nowhere in the site, which looks like a derivation that does not mirror `migrate_post()`. It is the
opposite: the tags were never in `post_content`, so nothing was ever written for them, and the
`unreached` bucket is exactly right. Checking the census's container column is what settled it.

### Experiment R — RUN 2026-08-17, PASSED on both clones

`5b0b98b` (`main`) → `993cf16` (`feat/fw-71-slot-source-identity`), both declaring 1.17.0. Full
record on [#107](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/107); the
ship decision on [#101](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/101).

| | SITE-B (depth) | SITE-A (breadth) |
|---|---|---|
| pairs | 11,155 | 13,568 |
| **CHANGED** | **0** | **0** |
| volatile (excluded) | 1 | 123 |
| non-vacuity | 4,962 non-empty | 6,129 non-empty |
| attested / synthetic | 47 / 11,108 | 39 / 13,529 |
| verdict | **GATE HELD** | **GATE HELD** |

SITE-B full replay (`--sample-n=24`, 98/98 URLs, 16 strata, 0 dropped); SITE-A full census with
sampled replay (`--sample-n=3`, 342 rows at 106 URLs across 36 strata, 17,180 of 17,278 URLs dropped
in four large homogeneous strata — **logged, because a bounded sweep that does not say what it
dropped reads as "covered everything"**).

**Both silent-failure guards were OBSERVED FIRING, not assumed** — a deliberate self-diff was
refused (`BOTH SIDES RENDERED THE SAME BUILD`) and a wrong-census diff was refused by the digest
guard (`THE CENSUS SUPPLIED HERE … IS NOT THE ONE THAT WAS RENDERED`). Fresh harvests went into new
`SITE-B-R/` / `SITE-A-R/` directories, so nothing beside them was orphaned; the previously
orphaned SITE-B pair was NOT salvaged, as required. §F9.1–F9.4 pass on `testbed` at blueprint v10
(reseeded from the branch under test), all 27 pure harnesses green.

**The BUILT ZIP was activated on SITE-B** — 205 entries, no `tools/`/`docs/` leak, six URLs loaded,
no fatal, dev symlink restored. The dev symlink structurally cannot catch an unguarded `require` of
a `.distignore`d path, which is the whole reason that check is separate.

**What the release still owes: [#112](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/112)'s
own replay run.** It was on NEITHER side of R by design — a deliberate behaviour change bundled into
a run whose pass condition is an empty diff makes that diff unreadable — and it merged in #114 with
no recorded run. Its expected diff was written before the fact and stands: exactly the `src:view`
population on SITE-B (15 occurrences, 5 posts, 2 site-wide Elements, so their rows multiply across
the URL set), **EMPTY on SITE-A**, which registers no selectable roots and is therefore the cheap
strong control that fails loudly if the refusal over-reached. It needs a RE-HARVESTED census: R's
corpus predates the `view_*` conversion and holds no `src:view` at all.

### The seven things that read as results and were artifacts

Every one of these was found by pointing the instrument at real clones, and every one produced
output that looked like a finding about the plugin. They are listed because the shape recurs:
**something agrees with itself, or differs from itself, for a reason that has nothing to do with
the change under test.** All seven are fixed and committed.

| # | What it looked like | What it was |
|---|---|---|
| 1 | A `/author/` URL rendering everything empty | a `post_author` id with no user row → empty nicename → 404 |
| 2 | Whole strata quietly agreeing | `$_GET` never populated by WP-CLI, so every `?var=value` URL — the ONLY way to reach a `rewrite=false` post type, and the entire `search` stratum — collapsed to the front page |
| 3 | 13 of 51 SITE-B URLs agreeing | `public` but not `publicly_queryable` taxonomies and post types have no archive; WP serves the front page at their permalink. 404 was not the only way to miss |
| 4 | ~120 SITE-A pairs "changed" | `antispambot()` randomises entity encoding PER CHARACTER, so `{{email}}` is byte-different every render — and it travels into `{{content}}`/`{{post_excerpt}}` on any page embedding one |
| 5 | `{{try_title}}` rendering on 44 URLs before the migration and none after | it lived only in `bws_dynamic_tags_scan_allowlist`, which stores the settings screen's own UI LABELS; the new build regenerates that list. The census was eating plugin machinery |
| 6 | A clean 37/37 replay | the default regex was inlined in `${VAR:-…}` and the `}` inside its character class ended the expansion — truncated pattern, EMPTY census, and everything downstream reported clean runs over nothing |
| 7 | 5 changed pairs on one SITE-A URL | eight event posts share one identical `post_date`, so `ORDER BY post_date DESC` is a total tie and MySQL returns any of them first. The archive's first row moved between processes and five GB CORE tags faithfully reported a different post |

Two of these are worth carrying as principles rather than as history:

- **The volatility check is a WITHIN-PROCESS instrument and structurally cannot see #7.** It renders
  each tag twice in one process, where the query result is already fixed. Cross-process
  nondeterminism needs a different answer — a pinned sort tiebreak, applied identically to both
  sides. That makes the INSTRUMENT deterministic and deliberately does not claim the SITE is: a
  front end with tied sort keys genuinely varies, which is the site's data to fix.
- **#5 and #6 are the same failure wearing different clothes.** A sweep that picks up too much and a
  sweep that picks up nothing both report success. The fixes are symmetrical: scope the option
  sweep by declared name pattern AND record what was skipped, and refuse an empty census outright.

**Back-to-back builds.** Replay writes a dump, so the two builds never need to be live at once.
Either serial `git checkout` in the dev source, or two worktrees under the bind mount swapped with
one `ln -sfn` (what `dev-plugin.sh` already does, minus a `--source` flag). **Opcache is the real
hazard, not git:** swapping files under LSPHP can serve stale bytecode, and a stale-bytecode replay
produces a clean diff for the wrong reason — the most dangerous failure mode this instrument has,
because it looks like success. The replay asserts the plugin version/SHA from INSIDE the render.

### Sampling — the axis is URLs, not tags

**Most tags on both clones live in Elements**, so sources are few and rendered instances are many.
That inverts the cost model:

- **The census is EXHAUSTIVE and cheap on both sites.** "Which tag shapes exist in the wild" gets a
  definitive answer, not a sample — the input for fixture seeding and for narrowing the
  byte-identity surface.
- **Replay cost is the URL multiplier.** Stratify by **(tag × context-kind)** —
  `singular:<post_type>` | `tax:<taxonomy>` | `author` | `date` | `search` | `home` | `404` — and
  replay `min(3, count)` per pair. Ambient context is per-URL ([I9]) and the same tag string on a
  singular post, a term archive and an author archive takes DIFFERENT arms, which is exactly what
  this change rewrites; a shape key computed from the tag string alone collapses the cases under
  test.
- **SITE-B = depth** (full replay, volume evidence). **SITE-A = breadth** (full census, sampled
  replay). Demanding full replay from both is what made SITE-A infeasible, and is not what
  SITE-A is for.
- **The URL set is chosen ONCE, deterministically, up front, and reused on both sides of every
  diff.** Two runs that sampled independently are not comparable. It becomes a durable artifact,
  re-runnable next release — better than a full scan, which would never be repeated.
- **Sampling parameters are recorded in the run.** A bounded sweep that does not log what it dropped
  reads as "covered everything" when it did not.

Checked, because this made it load-bearing: `class-tag-converter.php` scans raw `wp_posts` with only
`post_type != 'revision'` and `post_status NOT IN ('auto-draft','trash')` — **no public-post-type
filter**, so Elements are reached. Had it filtered to public types, the migration would miss nearly
every tag on both sites.

### What neither instrument covers

**The new capability.** Nobody in the wild has authored a multi-step slot, because nothing could. A
clean replay across two real sites is evidence about the population that EXISTS; multi-step slot
behaviour rests entirely on the matrices and pure harnesses, and no amount of clean clone data
changes that.

### Fixture residue

The census feeds `core-structures`, but seeded rows need **human-decided** expectations. A row whose
expectation is "whatever it rendered" is a characterization test, and
`feedback_fixture_row_label_expectation` requires a row to state the expected output AND the property
under test. Twenty considered rows beat two hundred golden ones; the long tail goes into a corpus
file for the pure harnesses instead of onto testbed pages.

### The gate

> **1.17.0 ships this iff, on both clones:**
> - **M**: `A-render ≡ C-render` byte-identical; `A-wire`/`B-wire` diff reviewed, every change
>   attributable to a named migration entry.
> - **R**: replay diff **empty** — SITE-B full, SITE-A shape-sampled with Elements exhaustive.
> - **User-leg run** (separate commit, after R passes): diff contains ONLY author-archive rows for
>   `try_text`/`try_title`/`try_content`.
> - `fold-test-matrix.md` §F9's four divergences flip to passing.
> - Every pure harness green, plus the `emit₀` fixed-point assertion.
>
> **Any other diff is a finding, not an exception. The exception list is closed NOW**, before the
> runs — a list assembled after seeing a diff is a rationalisation, not a gate.
>
> **Fallback:** slips to 1.18.0; 1.17.0 ships with the base/slot divergence documented as a known
> limitation.

Separate two-minute check, outside the replay loop: activate the BUILT ZIP once on a clone and load
a page. That is the only thing that catches an unguarded `require` of a `.distignore`d path
(`feedback_guard_distignored_requires`; bit once in 1.15.0), and the dev symlink structurally cannot.

## Corrections the build made

Each was found by running something, not by re-reading.

| What this file said | What the build found |
|---|---|
| §What happens to the five skips — *"`entries` is the one shape still refused, and the refusal MOVES"* | **Already moved.** #103's arm table refuses the `meta_row` kind, so the seam needed no relocation work at all; deleting the `chain` branch was the whole of it. `{{table}}` was already off this row's critical path. |
| §The carry becomes chain-shaped — *"deletes a branch [I15] currently has to explain in prose"* | **HALF TRUE, and the first build read it as all of it.** The `$tax_inherit` SCALAR goes — that is the branch, and it is structural. [I15]'s corollary has a second half ("an inherited hop is a DEFAULT, not a step this chain took"), which is a rule about what `same` MEANS and survives the carry becoming a chain. Dropping it appended instead of replacing, so `2-src:same\|2-srcTermIn:office` behind a term hop became two term steps, hopped off a TERM input, resolved empty, and the slot VANISHED from a `{{join}}` that rendered it — on wire the old editor authored directly. §P16.4 has pinned the shape since #74 but read only the LAST term step, so it stayed green through the defect. Caught by the user asking what the behaviour decisions were, measured both eras, fixed with the merge at the end of the source axis. **Its first cut then broke the other direction** — dropping EVERY matching slug read `src(same;refs,manager)` off the ambient entity, i.e. destroyed the two-relationships-away chain FW-56 exists for. Scoping that by "can this slug repeat" fixed the symptom and was still the wrong axis: it also killed an inherited `terms` step in front of an own `refs;terms` pair, where `refs` ACCEPTS a term input and the chain runs as written. **The third reading, adopted 2026-08-15, moved the test off the SLUG and onto whether the join RUNS**, and extracted it to `bws_fold_chain_join()`. Stated in the PAST tense on purpose: this row records which reading was taken when, and the LIVE rule is at that function and nowhere else — a fourth change adds a row here rather than editing this one, which is what kept the two earlier readings readable after they were withdrawn. Three readings, each right on every legacy shape and wrong on a different hand-written one; the user's question named the axis each time. |
| — (unanticipated) | **Per-step bounds now reach the ENGINE.** `bws_fold_from_flat()` materializes `limit(1)` on every earlier fanning step so a migrated slot keeps the flat product; the flatten then discarded all but the LAST one. Emitting the chain whole means each hop is bounded, which again is what the identically-spelled base tag does. Reachable only on a twice-fanning slot (`refs`+`terms`, the one such shape the flat triple could express). `fold-test-matrix.md` §F9d + `slot-fold-test.php` §P18.4. |
| §Tier 1 — *"`$explicit_id` threading: `'site' !== $slot_opts['src']` → kind test"* | Done, and the same class of test turned up in TWO more places the plan did not list: `try_`'s mode-2b loop-fallthrough gate (`'current' === $last_src`, flagged on the issue by #103) and the preview walks' `'ref' === $eff_src` warning, which was already dead — an unfinished relationship step never reaches it. |
| — (unanticipated) | **The wire needs no duplicated base.** The alternative encoding considered for the `same`-plus-own-taxonomy shape was for the migrator to write the inherited base out explicitly (`src(refs,office;terms,x)` rather than `src(same;terms,x)`), making the slot self-describing. It is not needed: the merge rule supplies the base, both eras resolve identically, and `same` keeps its live link to whatever the earlier slot becomes — which duplication would sever at migration time. It would also not have helped the UNMIGRATED dual-read, since `bws_fold_from_flat()` maps one slot in isolation and cannot see the previous one. |
| §The inert-chain warning — *"the warning is raised, not dropped"* | **Correct, and BUILT two days after this row said otherwise** (#105 → `df145fe`, 2026-08-17, verified in the editor). The row as first written said "NOT built … still parked", which was true only of #104's scope: the warning needs a statically resolved kind, and the raised chain the seam supplies IS that kind, so shipping the seam is what unblocked it. Reached the base tags too, which is where the hole was worst — `{{text src:currnet\|key:x}}` previewed exactly like a bare tag. Retired `source not supported`; added `no repeater field`. **The lesson is about scope words, not about the warning:** "parked" and "not in this issue's scope" read identically in a §Corrections table and age differently. |
| §The inert-chain warning — *the two statically-decidable values are `meta_row` and `''`* | **`meta_row` was CUT, and cutting it was right.** An `entries` step with its field set is UNIMPLEMENTED, not inert: `{{table}}` wants a repeater row as its read context, and flagging it would encode a per-template fact with a shelf life — the tag becomes correct without anyone touching the sentence calling it broken. What shipped instead is unknown step slug / unregistered root / retired token, all three decidable with no per-template knowledge. `CONTEXT.md` §Language now carries **inert chain** as a term with its three not-neighbours (unfinished, unconfigured, unimplemented). |
| — (unanticipated, found by verifying rather than building) | **Three sites had gone stale naming a superseded axis** — [tag-reference.md](../../docs/tag-reference.md) said the own step "appends", and `slot-fold.php` said "replaces … of the same slug" TWICE, in the PHPDoc header and in an inline comment ~120 lines above the merge it describes. All three were written or last touched before `1d4dcef`, which corrected the axis and moved only `CONTEXT.md` and the code. Fixed under #106, and generalised into `CLAUDE.md` §Documentation ownership: **a rule's AXIS is owned once at the enforcing site; any doc may state the CONSEQUENCE, and a non-owner naming an axis is the defect.** Grep-detectable, which is the point — the three drifts share one signature. |

## Tests

`slot-fold-test.php` (new emit shape; §P13/§P14 equivalence now compares two chain-wire strings,
which is better than comparing two triples; §P13.5's four "inexpressible chain SKIPS" assertions
**invert to resolve** — that is the harness-side acceptance signal), `slot-fold-twin-test.php`,
`fold-chain-compile-test.php`, `preview-label-test.php` (all three previews converge on one namer —
that convergence is the point, so a namer change shows up there first),
`node slot-fold-repeater-test.js` (the offer widens), `control-order-test.php`. Then
`fold-test-matrix.md` on the testbed, where §F9's four rows are the acceptance signal, with the
`try_`/join matrices carrying byte-identity.

## What FW-43 keeps

`bws_collect_value_list()` is the COMBINING half of the parameterized fold, shipped under FW-49. The
SELECTING half was never written. That is the whole remainder:

- Write `bws_select_first_value()` beside it.
- Route the base CONTENT callback's term loop through it — `base-tags.php`, the `'term' === $res['kind']`
  branch, still a hand-written `foreach … return` first-wins after FW-63 kind-dispatched the branch
  selection around it.
- Route `try_`'s slot loop through it, once Tier 2 has collapsed the arms.

Two §OPEN items travel with it: the content callback's post-side collapse to one id, and
un-hardcoding the `same`-use prepend.

## Where this is written down

- **`CONTEXT.md` I16** — the identity, the wire handoff, and the recorded negative (the read is not
  part of the source).
- **`CONTEXT.md` I14** — its "Currently contradicted" paragraph amended: the fix arrives by a
  different mechanism than it predicts for the slot half.
- **ADR 0004** — a one-line corollary pointer. No new ADR: "wire not parsed" is downstream of 0004,
  and nothing was found that would rework it (FW-60 strengthens it, perf reworks the implementation
  not the interface, deeper nesting is already anticipated by `$enclosing_level`).
- **`src-chain-encoding.md` §SETTLED** — two rows that were missing and whose absence caused a
  re-derivation during this very pass: read-as-sibling (superseded-in-place 2026-07-31) and
  slots-only (locked 2026-07-28).

## History

**2026-08-13 — the reframe.** This file previously priced the work as an arm rewrite in every
container ("the container arms dispatch by terminal-step KIND, the expressibility skips go, and each
container's `steps` list widens"). Two thirds of that was already paid: `{{join}}` needs no arm work
at all, and the slot half is an EMIT change. The framing is what made the row read expensive.

**2026-08-11 — two corrections that predated the reframe**, both from FW-63 landing underneath a row
written against code a later ship rewrote:

1. The skip vocabulary widened past `'chain'` to five reasons, only one about expressibility.
2. The content callback's `$is_ref` framing stopped describing the code — FW-63 replaced the token
   tests, and `$post_id` is computed inside the non-term arm.

**`term_`, inherited then shed.** GH #63 (`term_` gains a limit) closed as wontfix 2026-08-11, and
this row became the gap's only written home. De-scoped 2026-08-13: `term_` wants a term picker and a
collapse into base tags regardless, so route (3) — follow `view_` into a root source (FW-33/FW-70),
making the family deprecated wrappers over base tags that already have step limits — retires the
question instead of answering it. The gap's home is the FW-33 tracker row.
