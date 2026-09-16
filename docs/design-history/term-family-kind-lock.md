# Archive: `term_*` was kind-LOCKED, a base tag is kind-AGNOSTIC — CONTEXT.md I20 (LIVE 1.20.0, RETIRED 1.21.0)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.

**What this record is for.** I20 was a live `CONTEXT.md` invariant from 1.20.0 to 1.21.0. It said outright that it retired with the `term_*` family, and the family went in 1.21.0 (FW-129), so it retires here. It is kept rather than deleted because the migration machinery that survives the family still turns on the capability difference it records — the D40 output-neutrality exemption exists for exactly the population this invariant describes, and a reader who finds that exemption needs to be able to reach the reason it was granted.

## The invariant, as it stood

> **I20 — `term_*` is kind-LOCKED; a base tag is kind-AGNOSTIC**
>
> A `{{term_*}}` tag can address a TERM and nothing else: every read routes through `TaxonomyTerm::resolve_id()`, and where the ambient entity is not a term the tag has nothing to address and renders empty. A bare base tag addresses whatever the page is about — post, term, user or query context, resolved by CONTEXT ([I9]). **The two therefore coincide only where the ambient entity IS a term.** On a singular page, an author or post-type archive, a date archive, a search or a 404, the `term_*` tag is empty and the base tag reads the ambient entity. Measured 2026-09-10 across all seven contexts, after the guard that stopped the family reading a colliding non-term id.
>
> Neither side is wrong and nothing closes the gap: it is the capability difference between a family locked to one kind and one that follows the page. It is recorded because the opposite reads as true from the code — a guard clause showing that one tier cannot fire on a tag with no taxonomy option supports a claim about that TIER, never about the two families, and the equivalence derived that way was refuted by the measurement above. Consequence for any rewrite of a `term_*` tag into a base tag: the difference surfaces as empty→value. Whether such a rewrite may ship is the migration's decision, recorded with that migration and not here. Retires with the family (FW-129), not before.
>
> The guard keeping the `term_*` half honest states its own rule at `bws_queried_object_is_term()`'s PHPDoc (`includes/helpers/taxonomy-helpers.php`); this invariant neither restates nor depends on it. Tests: `tools/test/context-test-matrix.md` §C-TERM/CT (the rows come in PAIRS, and the matrix owns why) + the seven `ctx-*` page snapshots. Related: [I9] (ambient resolution by context), [I15] (an ambient read is SPELLED, never reached by fallback), [I18].

## What the retirement did and did not change

**The migration entries are untouched, and so is the difference they disclose.** `term_*` → base is still convertible in 1.21.0 — the entries are registered off the modifier TEMPLATES, not off the withdrawn constructor — so stored wire still converts, and converting an argless `term_*` tag still surfaces as empty→value off a term page. That is the D40 exemption's whole population, and it outlives the invariant that named it.

**The guard the last paragraph pointed at is gone.** `bws_queried_object_is_term()` was removed in the same release. It had exactly one consumer, the family; with the family unregistered, `TaxonomyTerm::resolve_id()` stopped honoring GB's bare queried-object arm rather than type-gating it. The kind-lock the invariant describes was never the guard, though — a `term_*` read addressed a term because the family resolved through a term source, and the guard only stopped it addressing the WRONG term.

**What has no successor invariant.** There is no live rule that a base tag follows the page; that is [I9], and it never needed I20. What died with the family is the COMPARISON, because only one of the two spellings still exists.

## Pointers

- The measurement this invariant was derived from, and the exemption it bought: `docs/design-history/term-family-migration-output-neutrality.md`.
- Whether one stored tag is in the exempt population: `bws_modifier_argless_rewrite()` PHPDoc (`includes/tags/deprecated-tags.php`).
- Ambient resolution by context, still live: `CONTEXT.md` [I9].
- The removal that retired this: FW-129 in `docs/future-work.md`.
