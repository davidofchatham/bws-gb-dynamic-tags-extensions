# Archive: the `try_` family hides instead of unregistering — FW-178 (SHIPPED, unreleased)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md` §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against, what a build actually did — never as a statement of how the code works now. For current state: `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site (`bws_gb_register_tag()` owns what a taken name gets).

**Provenance.** The build spec for FW-178, decided in a grilling session on 2026-11-24 and built on branch `feat/fw-178-try-hide` (PR #171, merged 2026-11-25). It lived at `.scratch/fw-178-try-hide/spec.md`; this file is the spec itself, committed whole. Its `Status:` line reads as it did before the merge.

**One thing the build settled differently from the text below, recorded here rather than edited into it:** story 10 and the testbed acceptance expect the settings screen to read "hidden" for each `try_` tag. The screen draws that marker only on deprecated and retired rows, so live `try_` tags have no row to show it; the marker computes `hidden`, and no rows were added.

Status: built, not merged, PR #171 (feat/fw-178-try-hide: cb8ac45, c9a9c56, 789fce4, d21965b)

Produced from a grilling session on 2026-11-24. Every decision below was put to the owner and answered; the Q numbers are the grilling's, kept so a reader can ask about one. Branch + PR (Q6). No version bump from the agent; the number is the owner's call at release.

## Problem Statement

A site owner unchecks "Enable multi-attempt tags" on the Tag Extensions settings screen to tidy the editor's tag picker. Every `{{try_…}}` tag already stored in their pages stops being registered, and GenerateBlocks replaces only registered tags, so those pages now print the literal tag string in braces to visitors. A switch meant to tidy the picker breaks published pages.

Separately, a `try_` tag stands down from its name when another plugin registered that name first, even with the family switched on. Every stored block that names it then renders through the other plugin's callback, and nothing on the site says the output changed. The base tags refuse that failure on purpose (they overwrite and report); the `try_` family accepts it on the reasoning that losing one "degrades the editor rather than breaking published pages", which stops being true the moment a site has stored `try_` content.

`docs/tag-reference.md` §Modifier group toggles already describes the toggle as removing the tags "from the GB editor picker". The code goes further and removes them from GB's registry. This spec resolves that drift toward the doc.

## Solution

Unchecking the box hides the `try_` family from the tag picker and nothing else: every `try_` tag stays registered (with the existing `hidden` registration form, which GB receives as `visibility: false`), so stored tags keep rendering on the front end and in the editor preview, and an existing block's tag modal keeps working. The settings row's registration marker reads "hidden". The checkbox is reworded to say it controls the picker.

A `try_` tag whose name another plugin already holds overwrites it and reports the collision, exactly as a base tag does, whether the tag is listed or hidden. With nothing left standing down from a name, the `yielded` collision outcome is removed from the plugin.

## User Stories

1. As a site owner, I want unchecking the multi-attempt setting to leave my published pages unchanged, so that tidying the picker never breaks a live page.
2. As a site owner who unchecked the setting before this release, I want my pages that print literal `{{try_…}}` braces to render their content again after upgrading, so that the damage the old switch did is undone without manual work.
3. As a site owner, I want the setting's label to say it controls the tag picker, so that I know what I am switching off.
4. As a site owner, I want the setting's description to tell me tags already on my pages keep working, so that I can uncheck it without worrying.
5. As a site owner, I want my existing choice (checked or unchecked) carried across the upgrade without a settings migration, so that nothing in my settings moves.
6. As a site owner on a new install, I want the setting checked by default, so that the multi-attempt tags are available until I choose otherwise.
7. As an editor author on a site with the setting unchecked, I want the `try_` tags absent from the tag picker, so that the picker offers only what the site owner wants offered.
8. As an editor author on a site with the setting unchecked, I want an existing block holding a `try_` tag to keep its preview, so that I can see what the page renders.
9. As an editor author on a site with the setting unchecked, I want an existing `try_` tag's modal to open with every control intact, so that I can still edit a tag already on the page.
10. As a site owner, I want the settings screen's per-tag registration marker to read "hidden" for each `try_` tag while the setting is unchecked, so that the screen says the tags are still registered.
11. As a site owner whose site runs another plugin registering a `try_` name, I want our tag to keep that name and render my stored blocks, so that my pages do not silently render through someone else's code.
12. As a site owner in that collision, I want the collision reported in the settings screen's Tag Name Conflicts block, so that I can see which plugin's tag lost its name and act on it.
13. As a developer debugging that collision, I want the `_doing_it_wrong()` notice the base tags already raise, so that a `WP_DEBUG` site tells me about it on every request.
14. As a site owner, I want a hidden `try_` tag to keep its name against another plugin too, so that hiding a tag from the picker never hands its stored blocks to a stranger.
15. As a site owner, I want `try_email` and `try_phone` to keep their element context gate while listed, so that the picker still refuses them inside a link, button or image block.
16. As a site owner, I want a `try_` tag whose template is itself registered hidden to stay hidden with the setting checked, so that a deprecated template stays out of the picker either way.
17. As a site owner reading the upgrade notice before updating, I want to be told that stored `try_` tags render again and that a `try_` name another plugin held is now ours, so that a changed page is not a surprise.
18. As a site owner reading the CHANGELOG, I want the change listed under Changed, so that the release record says what moved.
19. As a site owner running the Migration Tool, I want it to treat `try_` tags exactly as before, so that this change moves nothing in the scan or the run.
20. As a maintainer, I want the registration rule for every tag we register stated once, at the one registration site, so that the `try_` family and the base tags cannot drift apart again.
21. As a maintainer, I want the removed `yielded` outcome gone from code, docs and harnesses rather than left dormant, so that nothing tests a state the plugin can no longer reach.
22. As a maintainer, I want the converter's refusal of a name another plugin holds still covered by its harness after `yielded` goes, so that removing an outcome removes no protection.
23. As a maintainer, I want a harness to fail by name if the toggle-off arm stops registering the family hidden, so that the behavior this spec restores cannot regress unobserved.
24. As a maintainer, I want the page snapshots with the setting unchecked to match the committed baseline byte for byte, so that "hiding changes no output" is measured, not assumed.
25. As a maintainer, I want the update-trigger rules and the `CLAUDE.md` index to stop naming a duplicate-name skip that no longer exists, so that a future change is routed to the right harnesses.

## Implementation Decisions

- **Hide, not skip (Q1).** The `try_` constructor (`TagTemplateRegistry::generate_base_try_tags()`) no longer skips registration when `modifiers.try` is off. It registers every `try_` tag with `hidden` set when the setting is off OR the template itself declares `hidden`. The `hidden` form is the one `bws_gb_register_tag()` already owns (FW-81): GB receives `visibility: false`, the AI-syntax hints are withheld, and our registration record carries `hidden`. A template's own `visibility` context gate (the tagName gate on `try_email` and `try_phone`) is replaced by `false` only while hidden, which is moot for a tag nobody can pick; while listed the gate passes through unchanged.
- **One collision rule (Q2).** The constructor's duplicate-name check and its stand-down are deleted. A taken `try_` name reaches `bws_gb_register_tag()` like any other and takes the base-tag path there: overwrite, record a `kept` collision, report through the existing notice channel. The PHPDoc paragraph on `bws_gb_register_tag()` that defends the asymmetry ("DO NOT 'FIX' IT") is removed, and its axis paragraph is restated for every tag we register rather than for base tags. The late `wp_loaded` re-read already covers every name in our registration record, so it covers `try_` names with no change.
- **`yielded` removed outright (Q3).** `bws_gb_note_tag_yielded()` is deleted along with the `yielded` branch in the collision record (`bws_gb_tag_name_collisions()`), its handling in `bws_gb_collision_other_parties()`, the converter ownership predicate's `'yielded'` arm (the `lost` outcome and the live-registry witness remain and still refuse a name another plugin holds), the `yielded` entry in the settings page's Tag Name Conflicts map, the "third direction" PHPDoc, and the `yielded` rows and paragraphs in `docs/tag-reference.md`'s collision-outcome table.
- **Checkbox wording (Q4).** Label: "Show multi-attempt tags in the tag picker". Description: "Multi-attempt tags (`try_text`, `try_image`, etc.) return the first attempt with a value. If unchecked, they are hidden from the tag picker, but any tags already on your pages keep working." The stored key `modifiers.try`, its sanitizer and its default (absent means on) are unchanged, so no settings migration.
- **Disclosure (Q5).** A CHANGELOG `### Changed` entry under `## [Unreleased]` and a readme.txt Upgrade Notice under `= Unreleased =`, covering both groups whose output moves: sites with the setting unchecked (stored `try_` tags render again) and sites where another plugin held a `try_` name (now ours, collision reported). The owner judges the second group unlikely; it is disclosed anyway. Not routed through FW-66, which is unbuilt and not a prerequisite.
- **Docs that move with the code, no decision needed.** `docs/tag-reference.md` §Modifier group toggles gains that stored tags keep rendering. `docs/update-triggers.md` sections for the base-tag name-collision boundary, the modifier-family registration gate and the converter ownership guard are updated, as are the matching `CLAUDE.md` index rows, which name "the `try_` constructor's dup-check skip". The FW-178 tracker row's Detail home points here while in flight; FW-38's row, which lists FW-178 as holding the same hidden/stub question, notes FW-178's half answered.

## Testing Decisions

- **A good test here asserts what GB's registry and our registration record hold after the constructor runs, and what a page renders**, never which internal branch was taken.
- **Seam 1, the pure harness: `tools/test/control-order-test.php` (Q7).** It already drives the `try_` constructor (§C3) and a driven `try_text` collision against a stranger (§10, §F), and it is the harness the modifier-family registration-gate trigger already names, so no new harness. Assertions:
  - setting off: every `try_` tag is registered, each record carries `hidden`, and GB's args carry `visibility: false`;
  - setting on: every `try_` tag is listed, and `try_email` and `try_phone` keep their tagName context gate;
  - a template declaring `hidden` registers its `try_` tag hidden with the setting on;
  - the driven collision is REWRITTEN, not added to: the stranger's `try_text` is overwritten, the record's outcome is `kept`, it names the stranger as the previous registrar, and the notice fires; the sibling scan that asserted no other `try_` name yielded becomes an assertion that none was lost.
  - The harness stub already drives the toggle (`SettingsPage::$modifiers_enabled` in `lib-source-registry.php`), as `slot-options-build-test.php` and `traversal-pipeline-test.php` do for chain roots.
- **Converter coverage moves, it is not dropped.** `converter-ownership-test.php` O1.2, O2.x and O3.3 and `migrate-string-test.php`'s two held-name fixtures build synthetic `yielded` records; they move to `lost` records or to the live-registry witness so each refusal stays asserted.
- **Seam 2, acceptance on the testbed (Q8).** With `modifiers.try = 0`, `php tools/test/page-snapshots.php` matches the committed baseline byte for byte (the baseline was captured with the setting on, and hiding must move no output; any `try_` row that moves fails by name). With the setting back on, the same run also matches. Then, in the testbed editor with the setting off: the picker lists no `try_` tag, an existing `try_text` block's preview renders and its modal opens with every control, and the settings screen's marker for each `try_` row reads "hidden". Restore the setting afterward. Read `docs/testbed.md` before the run.
- **Other harnesses the touched trigger rows name** (`registrar-plugin-name-test.php`, `converter-ownership-test.php`, `migrate-string-test.php`, `drop-unrecognized-options-test.php`, `migration-report-test.php`, `report-run-agreement-census-test.php`) run green before commit.

## Out of Scope

- A per-tag on/off setting for base tags, or per-tag picker hiding within the `try_` family.
- Deleting the setting outright (Q1 option b, rejected: the setting keeps its reason, a tidier picker).
- FW-38's wider questions: the `stub` registration form, and whether a hidden or deprecated tag drops its registered options.
- FW-153's editor warning for a hidden tag, and any editor-side marker on a hidden tag.
- Building FW-66's advisory channel.
- Renaming the `modifiers` settings key or `is_modifier_enabled()`.

## Further Notes

- Measured 2026-10-23 on a real `try_text` registered with `visibility: false`: it renders on the front end and in the preview, its modal opens with every control intact, the picker box reads blank, and nothing in the editor marks it hidden. See `docs/gb-constraints.md` §`visibility: false` hides a tag from the picker only.
- The registration change itself is small (the `hidden` form already exists and the constructor already threads a template's `hidden`); most of the diff is the `yielded` removal and the docs that named the old asymmetry.
- At merge: lift this spec into `docs/design-history/`, write the PR body, close the FW-178 tracker row (rule: `docs/agents/issue-tracker.md` §When the work merges).

## Built

On `feat/fw-178-try-hide`, 2026-11-24. Commits cb8ac45 (code + harness), c9a9c56 (wording + docs), then a review-fix commit (tracker row, a stale harness comment).

- Beyond the decisions above: `bws_gb_register_tag()` treats re-registering a name GB still holds for us as no collision (`bws_gb_tag_is_still_ours()`). This replaces the re-entry guard the deleted yield carried, and applies to base tags too. The `kept` notice now says "a tag of ours that stood down" instead of "a base tag".
- `converter-ownership-test.php` O2.3 was retired rather than moved: with `yielded` gone it repeated O1.2.
- Harnesses: control-order 382/382, converter-ownership 86/86, migrate-string 10/10; every `tools/test/*-test.php` and `*-test.js` exits 0.
- Mutation-checked: dropping the family hide fails two; restoring a skip of a taken `try_` name fails twelve; dropping the boundary's re-entry guard fails nine.
- Testbed (Q8): page snapshots PASS (29 pages) with `modifiers.try` off and on; the 8025da4 build with it off FAILS with literal `{{try_…}}` braces. `wp eval` with it off: every `try_` tag registered with `visibility: false`, marker `hidden`, no collisions. Setting restored to on.
- Editor checks measured by the user: with "Show multi-attempt tags" off, the picker lacks `try_` tags, while an existing `try_` block's controls and preview still work.
- Acceptance wording gap: the settings screen draws a registration marker only on deprecated/retired rows (`tag_rows_html()`), so live `try_` tags have no row to read "hidden". The marker computes `hidden` for all of them (wp eval above).
