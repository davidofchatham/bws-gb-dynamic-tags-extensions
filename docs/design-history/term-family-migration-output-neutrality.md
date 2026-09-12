# Archive: output-neutrality of the `term_*` → base-tag migration — FW-39 (SHIPPED 1.20.0)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.

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
