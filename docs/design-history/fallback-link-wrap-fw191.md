# Archive: a stated fallback links to the one entity the tag read — FW-191 (SHIPPED, unreleased)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site (`bws_sole_link_identity()` owns the rule).

**Provenance.** The build spec for FW-191, decided in a grilling session on 2026-11-24 and built the same day as four tickets on branch `feat/fw-191-fallback-link-wrap` (PR #170, merged 2026-11-24). It lived at `.scratch/fw-191-fallback-link-wrap/spec.md`; this file is the spec itself, committed whole. Its `Status:` line reads as it did the day it was written. The four ticket files stayed private and were not committed.

**Three things the build settled differently from the text below, recorded here rather than edited into it:**

1. **`src:site` did not already link its fallback.** Problem Statement and story 6 say the site was treated like any other single entity; measured before the build, a `{{text}}` `src:site` fallback printed plain. The build links it to the home page, and the CHANGELOG entry says so.
2. **`{{title}}` registers no `fallback` option**, so its shell is unchanged and it has no visible rows. Its resolver reports read identities for the `try_title` walk only.
3. **On an author archive, `{{datetime}}` prints its fallback plain while `{{text}}` links it.** The ambient user arm serves only title, content, text and image, so datetime reads no entity there. Unchanged design, not a defect of this rule.

**Build record.** Four commits: `c20af53` (the fold reports `read_identities`, `bws_sole_link_identity()` added), `ce46910` (text and title), `39f5899` (datetime), `5c54e93` (`try_`: `bws_try_run_attempts()` returns the reduced identity on exhaustion in place of `null`). Pinned by `traversal-pipeline-test.php` §FW-85, `try-slot-loop-test.php` §L9 and rows LK.11 to LK.36 on the `/matrix-links/` fixture page (`text-test-matrix.md` §T11).

---

# FW-191: a stated fallback is link-wrapped when the tag's link source is unambiguous

Status: ready-for-agent

Tracker: FW-191 in `docs/future-work.md`. Decided in a grilling session with the user, 2026-11-24 (D1 to D10 below).

## Problem Statement

With `linkTo` set, whether a tag's stated fallback prints as a link depends on which code path emitted it, not on anything the author can see. A tag with no `src`, or on an ambient term, wraps its fallback in the link. The same tag with `src:site`, or pointed at a pinned post or term, a related post, `srcTermIn`, or a repeater row prints the fallback as plain text, although a pinned post has exactly one permalink. Every `try_` tag prints its fallback plain, even when every slot reads the current post. The documented rule (`tag-reference.md`, §link wrap) says the fallback is wrapped "if a link resolves", which the pinned cases contradict; that page currently states the split as an exception and points at FW-191.

The cause is structural. A singular arm's core emits the fallback itself, so the resolver still reports the entity's link identity and the shell wraps it. A list arm (a pinned post is a list of one), a refusal, and datetime's resolver tail report `link_id` 0, which the shells read as "already wrapped or nothing to link", so the fallback the shell then emits is never wrapped. The `try_` walk strips the fallback from every attempt and returns nothing on exhaustion, so the `try_` shell has no identity to wrap against.

## Solution

One rule for every tag that takes `linkTo` (`{{text}}`, `{{title}}`, `{{datetime}}` and the two old datetime tags, `try_text`, `try_title`, `try_datetime` and the old `try_` datetime tags): the stated fallback is link-wrapped when the entities the tag read point at exactly one linkable entity, and prints plain otherwise. The link source and the field source must agree; a fallback stands in for that one entity's missing value. Values are untouched: list values still wrap per item against their own identity (FW-85).

## User Stories

1. As an author, I want a `{{text}}` tag pinned to one post to link its fallback to that post, so that the fallback behaves like the value it replaces.
2. As an author, I want the same for a pinned term, so that term-sourced tags match post-sourced ones.
3. As an author, I want a related-post or `srcTermIn` source that lands on exactly one entity to link its fallback, so that the source spelling does not change the output.
4. As an author, I want `{{title}}` and `{{datetime}}` to follow the same rule as `{{text}}`, so that I learn the rule once.
5. As an author, I want a tag with no `src` to keep linking its fallback as it does today, so that existing pages do not lose links.
6. As an author, I want `src:site` to link its fallback to the site, as it links a site value, so that the site is treated like any other single entity. (Corrected 2026-11-24: measured, a `{{text}}` `src:site` fallback printed plain before FW-191; it did not already link.)
7. As an author, I want a source that fans out to two or more entities (for example `limit:0` over three empty posts) to print its fallback plain, so that one fallback string never claims to belong to one of several entities.
8. As an author, I want a repeater-row source to print its fallback plain, so that nothing links to a row, which has no URL.
9. As an author, I want a source that resolves to nothing (no entity, a refused read, a gated or deleted pinned post) to print its fallback plain, so that a link never points nowhere.
10. As an author, I want `linkTo:key` with an empty URL field on the entity to print the fallback plain, as it prints a value plain today, so that the URL rule is the same for values and fallbacks.
11. As an author, I want `newTab` to apply to a linked fallback exactly as to a linked value, so that the setting means one thing.
12. As an author of a `try_` tag whose slots try different fields of one entity, I want the fallback linked to that entity, so that it behaves like the base tag.
13. As an author of a `try_` tag whose slots try the same field across different sources (current post, then parent, then site), I want the fallback printed plain, so that no single source is falsely credited with it.
14. As an author of a `try_` tag, I want a slot that resolved no entity to be ignored when deciding the fallback's link, so that an empty slot does not cost the fallback its link.
15. As an author of a `try_` tag, I want a slot that fanned to two or more entities to make the fallback plain, so that the rule matches the base tag's.
16. As an author of a `{{join}}` tag, I want the tag-level fallback to stay plain, so that a fallback standing in for the whole composite is not linked to any one slot's entity.
17. As an author working in the editor, I want the preview label to keep showing in place of the fallback, so that the editor still shows the configuration.
18. As a site owner upgrading, I want the CHANGELOG to say which fallbacks now print as links, so that a changed front end is not a surprise.
19. As a maintainer, I want the decision of which identity a fallback addresses to live in one helper, so that the base tags and `try_` cannot drift apart.
20. As a maintainer, I want the reasoning in CONTEXT.md [I12] and the shell comments to state the new rule, so that no committed text says a fallback addresses nothing.
21. As a maintainer, I want a named assertion that the fallback's identity count is not FW-85's deleted value gate, so that nobody reinstates the gate on values while reading this.
22. As a maintainer, I want `tag-reference.md`'s link-wrap paragraph to state one rule with no exception, in the same commit as the code, so that the doc never claims behavior the code does not have.

## Implementation Decisions

- **D1, the base-tag rule.** The fallback is wrapped when the identities the tag read contain exactly one linkable identity (post, term, user, or the site sentinel). "Read" means the targets after the `limit` slice, the singular arm's entity, the site sentinel, or the ambient entity. Zero identities, row or query-context reads (no identity under [I12]), and two or more distinct identities all print plain. `bws_wrap_with_link()` still decides the final step, so a `linkTo:key` whose URL field is empty prints plain.
- **D2, the `try_` rule.** The same reduction over the union of every attempt's read identities. An attempt that resolved nothing contributes nothing; an attempt that fanned contributes every identity it read, so it makes the result ambiguous. Slots on different sources print plain (the user's common case); slots sharing one entity link.
- **D3, where the rule lives.**
  - The resolvers (text, title, datetime) report, on an empty value, the set of identities they read, as [I12] `(kind, id)` pairs. The list fold (`bws_collect_value_list()`) reports the identities of the targets it rendered, whether or not any value survived; today it captures identity only per kept value.
  - One pure helper reduces a set of identities to its sole member, or to none, deduplicating the same entity read twice. It is the single owner of the axis; its PHPDoc states it.
  - The base shells (text, title) wrap the stated fallback with the reduced identity, through the `bws_wrap_with_link()` call they already make. Datetime's resolver tail sets `link_id`/`link_type` from the reduced identity instead of zeroing `link_id`, and its shell wraps as it already does.
  - The `try_` walk (`bws_try_run_attempts()`) unions each empty attempt's identity set and, on exhaustion, returns a result carrying the reduced identity in place of today's bare `null`. The `try_` shell wraps the fallback with it when the template supports link wrap. The image templates are unaffected (no `linkTo`).
- **D4, measurement.** `{{title}}` and the `try_` tags are measured on the testbed at build time as the before-state; the decision does not wait on them. Visible matrix rows become the after-state. `{{join}}` registers no `linkTo` and is not in scope.
- **D5, no toggle.** The changed front end ships as a CHANGELOG "Changed" entry. No option keeps a fallback plain while the value links.
- **D6, the editor.** The preview label still outranks the fallback in the editor; nothing changes there.
- **D7, `{{join}}`.** If `{{join}}` gains linking (FW-120 or later), it is per slot, and its tag-level fallback stays plain: it stands in for the composite, not for any slot. This rule does not reach it.
- **D8, cross-references.** FW-191's row adds FW-120 to `Interacts with`; FW-120's plan gets a one-line pointer to D7.
- **D9, records.** The tracker row's `Blocked by` becomes `—` with the decision recorded under Progress. `tag-reference.md`'s link-wrap paragraph changes in the same commit as the code, dropping the date/time exception sentence and the FW-191 pointer.
- **D10, the reasoning moves with the code.** In the build commit: [I12]'s corollary is rewritten in place to say the stated fallback addresses the sole identity the tag read when there is one, with per-item fallback suppression unchanged (the fallback still fires once, in the caller, never per item). The text shell's "never link-wrapped, no entity to link to" comment and datetime's tail comment are restated to the new reason and point at the helper's PHPDoc. The fold's PHPDoc states that the fallback's identity count is not FW-85's deleted value-count gate.
- Not changed: per-item value wrapping, the singular arm's wrap of a core-emitted fallback, `bws_wrap_with_link()` and `bws_resolve_link_url()`, `{{content}}`, `{{email}}`, `{{phone}}`, `{{image}}` and `{{join}}`.

## Testing Decisions

- A good test drives the fold, the helper, or the walk through its public call and asserts on what it returns, never on intermediate state. The rendered anchor on a real page is the final check.
- **Seam 1, the fold and the helper**, in `traversal-pipeline-test.php` §FW-85 (pure, extended, no new harness): an all-empty list of one reports that identity; an all-empty list of two or more reduces to none; a row list reports none; the helper dedupes the same entity read twice; a named assertion that values still wrap per item whatever the count (not the deleted gate).
- **Seam 2, the `try_` walk's exhaustion result**, in `try-slot-loop-test.php` (pure, stub resolvers): slots sharing an entity yield that identity; slots on different sources yield none; a zero-target slot is ignored; a fanning slot breaks agreement; a winning attempt's return is unchanged.
- **Seam 3, rendered output on the testbed.** Visible rows on `/matrix-links/` (`text-test-matrix.md` §T11, which already pins where each anchor points), one per family (`text`, `title`, `datetime`, `try_text`, `try_datetime`) for: pinned post, pinned term, related post, `srcTermIn`, no `src`, `src:site`, a fan of two, rows, and `try_` with shared and with mixed sources. Rows are generated as visible blocks in the blueprint, reseeded, and the page-snapshot baseline re-captured in the same commit; pages that moved are the intended change and are named in the commit body.
- Update triggers this touches: text read-seam/link-wrap, datetime, `try_` slot loop, L2 read-seam (only if a resolver's read path moves, which it should not). Run each row's named harnesses.
- Prior art: FW-85's per-item cases in §FW-85, the stubbed wrap helper defined before the require; `try-slot-loop-test.php`'s stub resolvers.

## Out of Scope

- `{{join}}`'s fallback and any `{{join}}` linking (D7, FW-120).
- The editor preview's ordering of fallback and label (FW-149).
- Moving fallback emission out of the singular cores (FW-98).
- A per-tag option to keep the fallback plain (D5).
- `{{image}}`/`try_image` fallbacks, which take no `linkTo`.

## Further Notes

- The behavior split was left as found when GH #163 made the pinned-source fallback print at all; FW-3 (shipped, unreleased) left fallback emission where it was (its D3), so nothing waits on it.
- Edge to watch in the build: a `try_` attempt whose resolver is datetime receives options with the fallback stripped, so datetime's tail must still report its read identities on an empty value rather than returning the fallback.
- At merge, the spec lifts to `docs/design-history/` per `docs/agents/issue-tracker.md` §When the work merges.
