# Archive: per-item link wrapping for list-mode values — FW-85 (SHIPPED 1.21.0)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.

**Provenance.** The build spec for FW-85, written 2026-09-21 and built out over the five tickets of branch `fw-85-per-item-links` the same day and the next. It lived at `.scratch/fw-85-per-item-links/spec.md` and dies when that branch merges; the pull request body is its published form, and this file is the spec itself, committed whole. Its `Status:` line reads as it did the day it was written. The five ticket files stayed private and were not committed. The decision this repair repays lives in `docs/design-history/deterministic-source-selection.md` §S26 — the Upgrade Notice that disclosed the loss rather than fixing it.

**Three things the build settled differently from the text below, recorded here rather than edited into it:**

1. **§Testing Decisions already carries its own correction**, marked *CORRECTED ON MEASUREMENT (ticket 01)*. The "inline copy of the fold" the seam section assumed did not exist as a live copy; ticket 01 deleted the dead one and the harness drove the shipped fold throughout.
2. **The help text shipped stating a different half of the behavior than §Implementation Decisions describes.** The spec said the copy "gains the per-item read"; the maintainer's wording states the observable instead — no link on an item whose URL field is empty — and adds the `try_` fanning ceiling the spec's §Out of Scope left undisclosed to authors.
3. **The README note was declined on review.** §Implementation Decisions budgets CHANGELOG plus a README note; the maintainer's call was that per-item linking fills a gap rather than adding unusual behavior, so the CHANGELOG entry carries the disclosure alone. User story 25 is satisfied by that entry.

---

Status: `ready-for-agent`

Tracker row: `docs/future-work.md` FW-85. Design record it defers to: `docs/design-history/deterministic-source-selection.md` §S26. Invariant it moves: `CONTEXT.md` [I12].

## Problem Statement

An author sets **Link To** on a `{{text}}`, `{{title}}`, `{{datetime_single}}` or `{{datetime_range}}` tag whose source chain fans out to several entities. The tag prints all of the values, joined by the separator, and prints none of them as a link. The author's Link To setting silently does nothing.

The behavior is not obviously wrong until the tag's population changes underneath it. A tag configured against a post that happened to have one related staff member links correctly; the same tag on the next post, which has three, prints three names as plain text. Nothing in the editor says why, and the tag's configuration is identical in both places.

1.21.0 made this more visible rather than less. The limit-usable-results fix means a tag that used to show one of three values now shows all three, so tags that were linking yesterday crossed the count threshold and stopped. That loss shipped disclosed through the Upgrade Notice rather than repaired, on the grounds that repairing it was a designed feature riding a bug fix. This spec is that repair.

## Solution

A list-mode value links **per item**. Each value in the list is wrapped in its own anchor, pointing at its own entity; the separator sits between the anchors as plain text.

Nothing new to configure. Link To keeps the meaning it already has — "link what this tag prints" — and simply becomes true in list mode, which is what an author setting it already believes. A tag printing one value renders exactly the markup it renders today. A tag printing three now renders three links where it rendered none.

Values with nothing to point at stay plain. A repeater row is not an entity and has no URL; a query-context value addresses nothing; a **URL Meta/Option Field** key can be empty on one entity and filled on the next. In every case that item prints as text beside its linked siblings, rather than the whole list dropping its links because one member could not resolve.

## User Stories

1. As an author, I want a fanning text tag with Link To set to render each value as a link, so that a visitor can reach every entity the tag names instead of none of them.
2. As an author, I want the value I see and the link I get to be the same entity, so that clicking "Jane Doe" in a list of three staff names goes to Jane Doe and not to whoever happened to be first.
3. As an author, I want a tag that prints one value to render exactly as it does today, so that upgrading does not move pages where nothing about my configuration changed.
4. As an author, I want my separator to stay plain text between the links, so that a comma is not underlined and clickable as part of the name beside it.
5. As an author, I want a list where only some values have a URL to link the ones that do, so that one staff member without a profile page does not cost the other two their links.
6. As an author, I want **URL Meta/Option Field** to be read from each entity in the list, so that a per-entity external URL field links each value to its own destination.
7. As an author, I want a value whose URL field is empty to print as plain text, so that an incomplete field on one record does not print a broken or wrong-target link.
8. As an author, I want **Open in new tab** to apply to every link in the list, so that the setting means one thing rather than applying to whichever value won a gate.
9. As an author, I want a term list (departments, categories) to link each term to its own archive, so that a comma-separated taxonomy list behaves the way a taxonomy list is expected to behave.
10. As an author, I want a related-posts list to link each post to its own permalink, so that a "related staff" or "related offices" line is navigable.
11. As an author, I want a fanning date list with Link To set to link each date to the entity that date came from, so that dates behave like every other list-mode value rather than being a second class of output.
12. As an author, I want the repeater-row list to keep printing plain text, so that rows — which address nothing — do not grow links to some unrelated entity.
13. As an author reading my tag in the editor, I want the rendered preview to show the links, so that I can see the change took effect without publishing.
14. As an author who set Link To before this release, I want it to start working without my touching the tag, so that I do not have to find and re-save every tag on the site.
15. As an author, I want no new control to appear in the tag modal, so that a setting I would never turn off does not become another thing to understand.
16. As an author, I want my stored tag string unchanged, so that nothing has to be migrated, scanned or converted for this to work.
17. As an author using a `{{join}}` slot that fans, I want that slot to keep printing plain text, so that a slot I never gave a link setting to does not silently start emitting markup into an assembled string.
18. As an author using a `try_` tag, I want its current linking behavior unchanged in this release, so that a partially-delivered change does not make two families behave differently for reasons I cannot see.
19. As a maintainer, I want per-item wrapping to live at one seam, so that adding a list arm later inherits it rather than re-deriving it.
20. As a maintainer, I want the count-based gate gone rather than kept alongside the new path, so that one case is not served by two pieces of code that must agree.
21. As a maintainer, I want the invariant that currently states the gate to state the new rule, so that the enforcing site and the design record do not disagree the day this ships.
22. As a maintainer, I want the behavior pinned by a harness that fails by name, so that a later change to the fold cannot quietly reinstate the gate.
23. As a maintainer, I want the change visible on a fixture page, so that the markup can be read off a rendered page rather than inferred from assertions.
24. As a maintainer, I want the deferred `try_` half recorded as its own tracked row, so that closing FW-85 does not bury the remaining work.
25. As a site owner, I want release notes that say links now appear on multi-value tags, so that a markup change on my pages is something I read about rather than discover.

## Seams

**One seam, and it already exists.** `bws_collect_value_list()` is the shared L3 combining fold every list arm routes through: it owns slice, per-item fallback suppression, render, per-value link capture, the count gate, and the separator join. Per-item wrapping is a step inside the sequence it already owns, between link capture and join. Putting it there means every list arm — base text's term and post branches, the datetime single and range branches — inherits it with no per-caller change, and a list arm added later inherits it by construction.

The seam is reached in tests through the harness's own inline copy of the fold, the existing house pattern for it. No new seam is proposed and none is needed.

## Implementation Decisions

**Per-item wrapping is the behavior of Link To in list mode, not a mode.** No new option, no new option value, no wire token, no migration, no editor control, no preview-label case. The author's stored configuration already expresses the intent; only the code moved.

**The wrap happens inside the shared list fold**, between per-value link capture and the separator join, guarded by the same `function_exists` idiom every other link-wrap call site in the plugin uses. Each value is wrapped against its own captured link identity; the separator joins already-wrapped strings, so it never falls inside an anchor. This answers the open question the tracker recorded — *which value receives the link once the separator has joined several into one string* — by ordering rather than by grammar: the join never sees an unwrapped list, so the question does not arise, and the separator grammar is untouched.

**The single-result count gate is deleted, not retained beside the new path.** For exactly one value the two produce identical markup — the whole string *is* the item — so keeping both would mean suppressing one to avoid a double anchor. The list branches' reads of the fold's top-level link identity are removed with it. Singular (non-list) arms keep their existing top-level wrap unchanged; this spec does not touch them.

**The fold's return shape loses its top-level link identity key** and keeps its per-value entries **raw** — unwrapped value plus identity. Only the top-level joined value carries markup. A documented-but-unconsumed key is the thing a later caller wires to by mistake, so it goes rather than being kept dead.

**Unresolvable items print bare.** The wrap helper already returns its input unchanged when no URL resolves, so a null link identity, an empty URL-field read, or a term whose archive link fails all produce plain text beside linked siblings at zero cost. There is no all-or-nothing pre-pass: it would need new code and produce the worse output.

**Scope is the fold-routed list arms** — base text's term and post branches, datetime single and range. The repeater-row branch routes through the fold and is covered by the null-identity rule; it prints plain text as it does now.

`{{join}}` is unaffected **by construction, not by a guard**: it registers no link options at tag level and its slot options carry none, so the new step finds nothing to wrap in a slot's list. This is worth stating because it is easy to mistake for an oversight.

`try_` is **out of scope for this change** and keeps its own count gate. Its bounded reader returns rendered strings and discards per-item entity ids, keeping only the first, so per-item wrapping there requires threading identity alongside the reads or reshaping that reader's return. That is a separate change with its own risk, and it gets its own tracker row rather than riding this one.

**GenerateBlocks' own output transforms are accepted as-is.** `trunc`, `case`, `replace` and `wpautop` run after our wrap, over the whole string, so a truncation can now cut inside one of several anchors instead of inside one. Identical in kind to what already ships for a whole-string wrap; guarding it would mean re-implementing GB's transforms on our side of the output boundary.

**The invariant that currently states the gate moves in the same change.** [I12]'s corollary presently reads that the single-result gate is a join constraint and names per-item wrapping as a future affordance. It is rewritten in place to state the per-item rule — each anchor spans exactly one entity — retaining the reasoning behind per-item fallback suppression, which is unchanged and still load-bearing: a per-item fallback would pollute the list, and under the old gate would also have satisfied it as though it were a real value. The invariant's enforcement site stays the fold's PHPDoc. The corollary is rewritten rather than deleted, because the reasoning it carries still binds.

**The URL-field key's help text gains the per-item read.** It currently describes only the `try_` case. New user-facing copy is surfaced for review before it ships.

**Disclosure is CHANGELOG plus a README note marked unreleased. No Upgrade Notice.** The Upgrade Notice channel is 300 characters, Updates-page-only, fires before the upgrade, and was already spent disclosing this exact loss. This change repays that disclosure rather than making a new one — it turns on what an author already asked for — and spending the channel again on "your links came back" burns it for a case that needs it.

## Testing Decisions

**A good test here observes the rendered string and the tag's stored configuration, nothing else.** Given a list of values and a Link To setting, what markup comes out. It does not assert on the order of internal steps, on which helper produced a value, or on the presence of a return key — those are the parts this change is free to move. The one exception is the return shape's published keys, which are an interface the callers read.

**The fold is pinned in `traversal-pipeline-test.php`'s existing FW-49 section**, extended rather than replaced by a new harness — the cases are the fold's own steps, and the section already holds the gate's cases, which now become the per-item cases.

**CORRECTED ON MEASUREMENT (ticket 01, 2026-09-21).** This section assumed the house pattern there was an inline copy of the shipped function, which would gain the wrap step plus a small stub for the wrap helper. It also recorded that the copy had drifted — clamping the limit inline instead of calling the shared clamp, slicing without the unlimited arm — while its comment claimed byte-equivalence. The drift was real; the copy was not. The harness requires the real `field-helpers.php` near its top (for `bws_source_link_identity`), so the copy's `function_exists` guard never fired and the section's rows were already driving the shipped fold. Measured by reflection: `bws_collect_value_list` resolved to `field-helpers.php`, as did `bws_clamp_limit` and `bws_limit_default`.

So ticket 01 deleted the dead copy rather than repairing it, and the wrap step goes into the **shipped** fold with nothing to mirror. The harness reaches it the way it already reaches the source gate: a `function_exists`-guarded stub for the wrap helper defined BEFORE the require, which the real file then yields to. The harness still stays pure and still requires no WordPress; only the stub's position moved.

Cases to pin: several values with identity all wrap and the separator stays outside the anchors; one value produces today's markup unchanged; a mix of identity-carrying and null-identity values wraps only the former; no Link To set produces no markup at all; the top-level identity key is absent from the return; per-value entries are raw.

**The visible half goes on a new fixture page.** The existing post-meta matrix page is saturated at roughly 380 blocks and carries every family's rows; per-item linking is a new concern needing term-list, post-list and URL-field arms together, which is exactly the case the page-split rule covers. Text matrix rows link to the new page rather than duplicating it. Row labels state the expected output, not just the row id.

**The fixture blueprint, the seed, the front-end capture and the page-snapshot baseline move in one commit**, because new fixture output moves pages by design and a baseline captured separately proves nothing about the change that moved it.

Prior art for all of this: the FW-49 section of the traversal-pipeline harness for the pure half; the fold and text matrices plus their visible blocks for the rendered half; the repeaters and products pages for the new-page-per-new-concern shape.

## Out of Scope

**`try_` per-item wrapping.** Blocked on its bounded reader discarding per-item ids. Recorded as its own tracker row, interacting with FW-85, FW-86 and [I12].

**Deduplication of a fanning chain (FW-86).** Two inputs sharing a target still yield that target twice, and after this change the repeat is two identical links rather than two identical strings — which reads more clearly as a defect without being a new one. FW-86 stays deferred on its structural argument; the interaction is noted on its row.

**Per-slot separators inside `{{join}}` (ADR 0003).** Untouched. The separator grammar emits no markup and is not being asked to.

**Any change to singular-arm link wrapping**, to the link URL resolver's routing, or to the set of templates that support link wrapping.

**A control to turn per-item wrapping off.** Considered and rejected: it is a setting nobody would sensibly change, and it would cost a control, a wire token, help copy, preview text, a migration and a matrix row.

**The `#77` announcement channel.** Unbuilt, and this change does not need it.

## Further Notes

The three ID sequences are distinct and cited with their markers throughout: `FW-85` is the tracker row, `#N` would be a GitHub issue, `fw-85-per-item-links/NN` a local build ticket.

This is multi-commit work that spans sessions, so it takes its own branch off the default branch and publishes through a pull request body. The current working branch belongs to a different effort.

One sequencing note: the invariant rewrite, the help-text change and the harness repair are each small enough to be tempting to fold into the code commit, and each is independently reviewable. Splitting them keeps the commit that moves rendered output narrow enough to read.
