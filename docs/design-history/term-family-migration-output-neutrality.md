# Archive: output-neutrality of the `term_*` → base-tag migration — FW-39 (SHIPPED 1.20.0)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.
>
> **`Site P` is a pseudonym** for the real client clone the replay gate below was measured against, substituted before publication. The measurements are unchanged; only the name is.

**What this record is for.** The `{{term_*}}` → base-tag converter shipped in 1.20.0 with one
stated exception to a rule this repo had held without exception until then: a migration does not
change what a page renders. This is the measurement that bought the exception, the exact population
it covers, the reason a FIX in the same release needed no such exception, and the reason none of it
was promoted to a live rule. A later migration arguing from any of it is the promotion trigger, and
that argument should start here rather than from a summary of here.

## The rule the migration rides on

**Migration is output-neutral.** A converter rewrites stored wire; the page renders the same bytes
before and after. The rule is not aesthetic — it is what makes a site-wide content rewrite a
maintenance task rather than a redesign nobody asked for. Two instruments hold it: the
modifier→base and per-step-limit records assert byte-identical render end to end, and the
harvest/replay trio diffs a real site's rendered output across the migration boundary.

An exemption is therefore a silent output change on live pages, which is the thing the rule exists
to prevent. One was taken here, deliberately, after measurement, and bound as narrowly as the
evidence allowed.

## Measurement 1 — the equivalence that was asserted, then refuted

**The original reasoning (D29) was:** an unpinned `{{term_*}}` tag and a bare base tag are already
equivalent, so rewriting one into the other materializes nothing and the migration stays
output-neutral by equivalence rather than by construction. The argument ran: the family's
term-detection tier 5 cannot fire on a tag carrying no taxonomy option, tiers 3 and 4 answer from
the queried term, and a bare base tag has read the queried term since 1.14.0. Therefore the same
value, on every page.

**It was derived by reading a guard clause, and it was wrong.** A guard showing that one TIER
cannot fire supports a claim about that tier; it supports nothing about the two families. The
repo's standing rule — a claim about behavior rests on a run — is what caught it.

**Measured 2026-09-10, all seven page contexts.** A `{{term_*}}` tag addresses a term and nothing
else: every read routes through `TaxonomyTerm::resolve_id()`, so where the ambient entity is not a
term the tag has nothing to address and renders empty. A bare base tag addresses whatever the page
is about. The two coincide **only on a term archive**. On a singular page, an author archive, a
post-type archive, a date archive, a search and a 404, the `term_*` tag is empty and the base tag
reads the ambient entity.

The finding became `CONTEXT.md` [I20] as a live invariant, because it is a standing capability
difference between a kind-locked family and a kind-agnostic one and it outlives this migration.
What did NOT become a live rule is the migration's response to it, below.

## The exemption, and its two arms

**The DECISION to convert unpinned tags stood; its stated rationale did not.** Rather than withdraw
the conversion, 1.20.0 took one exemption from output-neutrality, bound to this migration and dated
2026-09-11.

**Arm 1 — UNPINNED, empty→value.** A `{{term_*}}` tag with no term picked converts to a bare base
tag with no source at all. Off a term archive it renders what it always rendered; on the six other
contexts it begins rendering the ambient entity where it rendered nothing. **Direction-bound: the
exemption forgives empty→value and nothing else.** A value becoming a different value is a
regression and still fails every gate.

**Arm 2 — A DEAD PIN, value→empty.** A `{{term_*}}` tag naming a deleted term falls through to the
ambient term today, so on a term archive it shows whatever term the page is about as though that
term had been picked. The converted wire renders nothing, and the editor reads `term 999999
(missing)`. Measured 2026-09-11 across all seven contexts: `Sales` on `/department/sales/` before
and empty after; empty on both sides everywhere else (`context-test-matrix.md` §C-CONV13/14).

**Arm 2 is bound to the SUBSTITUTION, not to the direction alone.** What is lost is a value the
author never asked the tag to render — a borrowed term that looked like a choice. That is why it
does not reopen the general rule, and why it reads as the better outcome: a dead pin showing as
broken is visible, a dead pin silently borrowing the page's term is not.

**A LIVE pin needs no exemption at all.** `{{term_text id:34}}` → `{{text src:term,34}}` measures
byte-identical in both directions on all seven contexts (§C-CONV10/11). The pinned arm is the one
part of this conversion that changes nothing.

## Why a FIX in the same release needed no exemption

**A FIX may change output; a MIGRATION may not.** 1.20.0 also shipped the ambient-term guard, which
changed rendered output on live pages — a `term_*` tag with no term picked had been taking
`get_queried_object_id()` as a term id without checking that the queried object was a term, and post
ids, term ids and user ids share one numbering. A page with ID 22 read a "Priority" flag term; an
author archive for user 2 read an "All Users" term. Nothing on the page showed it was wrong.

That change needed no exemption, and the distinction is not a convenience. **The old output was
wrong** — nobody authored a tag to read an unrelated term that happened to share a number, so
removing it takes away nothing anyone asked for. **A rewrite changes output the author never asked
to have changed**, and the author is not present to be asked. The two are different acts even where
the diff looks the same size.

**This sentence is stated once, here.** It is not in `CONTEXT.md` and not in `CLAUDE.md`, on one
instance. The line is real and it is load-bearing for this ship; what it is not yet is a rule the
repo has needed twice.

The sharpest instance of it is not the fix above but a rewrite nobody designed — 14 silently dropped links on a real site, measured, in §Measurement 2 run 1.

## What the replay gate could and could not reach

**The evidence is SPLIT by what each instrument can actually see (D44),** and the split matters more
than either half.

**The replay trio is the gate for the migration as a whole.** Harvest and replay pre-migration, run
the converter (which writes the old-to-new mapping), harvest and replay again, diff across the
mapping. A converter-string corpus would prove string-in and string-out and cannot speak to render;
output-neutrality is a claim about render.

**It cannot reach the exemption.** The corpus is a harvest of a real site, and that site's
unpinned-and-ours population is ZERO. A clean replay there proves the exemption was never
exercised — not that it is safe. The two outcomes produce the same clean diff, which is exactly the
shape of failure a gate expected to be clean stops catching.

**Seeding unpinned tags into the harvest was refused.** It would manufacture a corpus and then cite
it as evidence from a real site. The exemption's evidence is testbed fixture rows instead — unpinned
`term_*` before, converted base after, across all seven contexts — which is manufactured on purpose
and says so.

**An exempt row is classified MECHANICALLY, off the map's own wire**: the old tag is `term_`-prefixed
and carries no `id` (`bws_replay_migration_exempt_row()`). Deliberately not off a marker the
converter emits, because a marker would make the rule true only for runs that postdate it. The run
still exits clean, and a seeded value→different-value change still fails. Hand triage is the
fallback for anything the rule does not match, never the mechanism.

**A gate expected to be dirty stops being read**, which is why `replay-vacuity-test.php` exists:
it drives every attestation to failure and then censuses the source, so an instrument that has
quietly stopped being able to fail fails instead.

## Measurement 2 — the replay gate, on a `Site P` clone

**The gate was met before ship.** Harvest and replay pre-migration, run the converter, harvest and replay again, diff across the mapping the converter wrote. Two arms on the same clone, the same corpus and the same A-render: **run 2 is the gate**, run 1 is evidence about the ownership opt-in and is not the shipping configuration. Corpus: 270 census rows, 45 URLs (9 attested), 116 tags per URL = **5220 renders per arm**, 0 volatile, 0 errors, non-vacuity 1678/5220. Artifacts stayed in the ENV repo; nothing carrying client data left the clone.

### Run 2 — the shipping configuration, 2026-09-13. GATE HELD

The converter with the ownership guard at its default rewrote **2 posts, 4 tags, 2 distinct strings** — the `term_content` pair, the whole of what that clone holds that we can prove is ours. `term_title` was left alone, as designed.

```
identical : 5220      CHANGED : 0      MISSING/ADDED : 0
exempt    : 0         volatile: 0      GATE HELD — every comparable pair is byte-identical.   (exit 0)
```

**`exempt: 0` is the measured form of the prediction above**, not a second proof of safety. The clone's unpinned-and-ours population is zero, so the exemption was never exercised — exactly what §What the replay gate could and could not reach says a clean diff at that row means. The exemption's evidence remains the testbed fixture rows.

### Run 1 — the arm the guard refuses by default, 2026-09-12. GATE FAILED, as designed

`term_title` was claimed in the per-name opt-in before the converter ran, so this measures what lifting the guard DOES. Converter: 3 posts, 22 rewrites, 13 distinct strings — 2 `term_content`, 11 `term_title`.

| Bucket | n |
|---|---|
| identical | 4725 |
| exempt (unpinned `term_*`, empty→value) | 31 |
| CHANGED | 464 |
| rescued / volatile | 0 / 0 |

**Every one of the 464 is `term_title`** — 450 empty→value (14 attested, 436 synthetic) and 14 value→different. `term_content` → `content src:term,N`, the only wire on that clone we can prove is ours, produced **zero** changed pairs.

**The exemption rule held and its limiting clause fired unseeded.** The 31 were classified mechanically off the map's own wire, no hand triage. The 14 value→different are the SAME stored string as those 31 — unpinned `{{term_title link:term}}`, separated only by URL — and the differ refused to forgive them because the A side was not empty. That is better evidence than the seeded change the acceptance criterion asked for: nobody built the case, and a rule that over-forgave would have swallowed it.

**The 14 are a lost link, and they root-cause to the opt-in rather than to the migration.** On term archives the A side rendered an anchor with empty text (GB Query Enhancements' `get_term_title()` needs a loop item) and the C side renders the bare term name with no anchor at all. GBQE registers those names with `'supports' => ['link','source']`, so **GB** serialized its own native `link:term`; the transform carried the key through verbatim into `{{title link:term}}`; our base tags read `linkTo`/`linkKey`. The key is inert on the target and the link is dropped silently. Not a defect in the modifier→base path: `register_modifier()` appends `bws_get_link_options()`, so our own `term_` family writes `linkTo`/`linkKey`/`newTab` and never `link`. That wire exists only because it was authored against somebody else's tag — which is the whole of why the converter now refuses a name it cannot prove is ours, and why this is the instance the fix-vs-migration sentence rests on.

**What it measured for the ownership guard:** the decline was RIGHT on this clone, measured rather than argued, and both refusal reasons apply independently — `name_not_ours` (GBQE holds the name) and `unknown_options` (the `link` key). Lifting it moved 464 renders and dropped 14 links.

### What these numbers do not hold

- **Run 2's figures were produced by the pre-fix instrument.** `run-converter.php` did not model the ownership guard sitting between the two calls it mirrors, so the run reported `derivation unverified: 11` against a 13-row mapping. The verdict is unaffected by construction, and that was measured rather than assumed: the diff was run both ways, raw 13-row mapping and filtered 2-row mapping, **identical both times**. On a re-run the same clone reads `ownership declined: 11`, `derivation unverified: 0` and the same 5220/5220.
- **The A side was the clone's live 1.19.1, not the shipped 1.19.2**, so anything 1.19.2 moved in rendering folds into `identical` rather than being held fixed. It touches neither the `term_title` finding nor the gate.
- **A clean gate is a statement about our resolver over real wire, never about what a visitor sees.** `replay-tags.php` calls `replace_tags()` with an empty `$block`, no query loop and no `the_content` filters. And the harvest sampled 45 URLs across its strata, so nothing here speaks to a context-kind stratum the sample never drew.

## Measurement 3 — the `link:term` fix, re-run on the same clone, 2026-09-14

The fix the run-1 finding produced (translate the foreign option VALUE, not just the key) was verified by re-running run 1's arm against the fixed build. Same clone, same opt-in (`term_title` claimed), same 45-URL set, same converter numbers (3 posts, 22 rewrites, 13 distinct strings).

**One mapping row moved, and it is the one the fix names.** `{{term_title link:term}}` mapped to `{{title link:term}}` before and maps to `{{title linkTo:permalink}}` after. The other twelve rows are byte-identical.

| Bucket | run 1 | run 3 |
|---|---|---|
| identical | 4725 | 4725 |
| exempt (unpinned `term_*`, empty→value) | 31 | 31 |
| CHANGED | 464 | 464 |
| rescued / volatile | 0 / 0 | 0 / 0 |

**The buckets did not move; what is INSIDE the 14 did.** Those 14 pairs carried an anchor on the A side and none on the B side in run 1; in run 3 they carry an anchor on both. The link is back, and because the pair was already counted as changed for the empty→value reason it shares with the other 31, restoring it does not change a single bucket count. **A run read by exit code alone therefore reports this fix as no change at all** — the difference is one level below the summary, and the tally that shows it is anchors per side, per tag, on the changed pairs.

**Nothing else moved, measured directly rather than inferred from the buckets.** Run 1's post-migration render compared against run 3's, pairing on (URL, tag string): 5175 shared renders, all byte-identical, zero moved. The B-side census drops 5220 → 5175 because the fixed rewrite now emits a string another rewrite already emitted (`{{title linkTo:permalink}}`), so 45 rows dedupe into one that was already there and already correct.

**The §C-CONV half of the trigger row was answered by probing the wire, not by re-rendering the matrix.** Every `term_*` "before" string in `tools/test/context-test-matrix.md` §C-CONV was pushed through the shipped transform at this commit and at its parent; the only string whose emitted wire differs is a control row carrying `link:`. The matrix's "After (base)" column is unmoved, so its render column — measured 2026-09-10/11 — cannot have moved with it.

### The A side was re-rendered, not reused, and that was not optional

Run 2 reused run 1's A-render, and the reuse was sound then. It was not sound here: between 2026-09-12 and 2026-09-14 the ENV repo stopped writing dynamic `WP_HOME`/`WP_SITEURL`, so the clone that had been emitting a literal `DOMAIN_PLACEHOLDER` host now emits its real one. Diffed against the stale A-render, the run reported **1051 changed pairs, 582 of them nothing but that host string**. Re-rendering the A arm on the current env brought it back to 464 with no normalization applied anywhere.

**A normalizer would have produced the same number and been worth less.** The instrument's whole claim is that it compares bytes; a hand-written collapse applied to its output on the way past is an unpinned rule invented for one run, and the next operator has no way to know it was applied. `tools/harvest-replay/README.md` carries the operating rule this cost.

## Not promoted, and what would promote it

Neither the exemption nor the fix-vs-migration line is a live rule. Both are decisions about one
migration, recorded here so the next person has the reasoning rather than the conclusion.

**The promotion trigger is a second migration arguing from either arm.** At that point the question
stops being "was this one conversion worth it" and becomes "what is our rule", which is a different
question with a different owner (`CONTEXT.md`, or `CLAUDE.md` if it governs process). One instance
is a record. Two is a pattern, and a pattern gets a rule.

## Pointers

- Invariant the measurement became: `CONTEXT.md` [I20].
- The skip channel's shapes and why each is not converted: `bws_modifier_skip_reason()` PHPDoc
  (`includes/tags/deprecated-tags.php`).
- The exemption's population, as the scan report counts it: `bws_modifier_unpinned_rewrite()` PHPDoc,
  same file.
- The replay instrument, and what a clean diff proves: `tools/harvest-replay/README.md`.
- Rendered rows: `tools/test/context-test-matrix.md` §C-CONV, `tools/test/fold-test-matrix.md`.
