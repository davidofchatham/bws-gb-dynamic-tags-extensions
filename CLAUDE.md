# CLAUDE.md

A WordPress plugin extending GenerateBlocks & GB Pro's Dynamic Tags with a concise set of tags for power users. See [README.md](README.md) and [`docs/tag-reference.md`](docs/tag-reference.md) for project overview and architecture.

## Dependencies

- WordPress (core APIs)
- GenerateBlocks plugin, required (`GenerateBlocks_Register_Dynamic_Tag`, `GenerateBlocks_Dynamic_Tags`, `GenerateBlocks_Dynamic_Tag_Callbacks`)
- GenerateBlocks Pro, optional (`GenerateBlocks_Pro_Dynamic_Tags_ACF`, `GenerateBlocks_Pro_Pattern_Library`) — plus two version-gated GB core classes present only on newer GB (`GenerateBlocks_Meta_Handler`, `GenerateBlocks_Dynamic_Tag_Security`); all four `class_exists()`-guarded, degrades gracefully when absent
- Custom fields plugin (ACF or compatible — all calls guarded with `function_exists()`)

## Development

No build pipeline or linter. Edit PHP directly, test in a WordPress environment.

**Two test layers — run the pure harness always, route integration through the testbed.** Pure
harnesses under `tools/test/` run via `php tools/test/<name>.php`; no CI runs these, run them
locally before commit. WordPress integration runs through a seeded WP site, the **testbed**, on the
local wp-litespeed OpenLiteSpeed/Docker env — the two entrypoints are `bin/wp.sh testbed bws
render-tag` and `bin/seed.sh testbed core-structures`. **Prefer routing integration smoke tests
through it over hand-built pages or live-site probes.**

Full harness catalog (which harness is pure vs. requires-the-real-file, the three impure ones, the
node-based ones): [`docs/testing.md`](docs/testing.md). Operating the testbed (the two staleness
layers, seeding, the visible-row mandate, running page snapshots): [`docs/testbed.md`](docs/testbed.md),
and reading it is not optional before an integration run.

## Documentation ownership

Single source of truth per content type. Other files link, never duplicate.

**DOC/CODE DRIFT IS SURFACED FOR A HUMAN DECISION, NEVER RESOLVED ON A DEFAULT.** Quote both
sides, say what `git log` shows about when each was written, and ask which one moves. The
direction is NOT derivable from the artifacts, because two opposite histories leave identical
evidence:

- **the code is UNFINISHED against a recorded decision** — the doc IS the decision, the code
  never caught up, and the repair is to finish the code;
- **the code changed deliberately and the doc was never updated** — the decision itself moved,
  and only the person who moved it knows that; rewriting the code would silently undo a
  considered change.

A decision taken in conversation that reached a plan but no commit looks exactly like the second
and is the first. Nothing in the tree distinguishes them.

**The PRESUMPTION still favors the doc** — documentation here usually precedes code, so that is
the way to bet — but a presumption is where the conversation starts, not a licence to edit.
Resolving toward the doc needs no note once decided. Resolving the other way — editing the doc
to describe what shipped — additionally gets a note at the site of the change explaining why it
was allowed there (see `tools/test/control-order-test.php`'s `showCurrentYear` comment, the one
standing instance). An exception is not precedent.

**A RULE'S AXIS IS OWNED ONCE; ITS CONSEQUENCE MAY BE RESTATED ANYWHERE.** The axis is *what
decides* — the predicate, the comparison, the derivation. The consequence is what an author or a
reader observes. Any doc, PHPDoc or comment may state the consequence; only the enforcing site may
name the axis, and a non-owner that names one is the defect, whether or not it currently agrees.
That is grep-detectable, which is the point. Live instance: the Update-triggers "SLOT SOURCE
HAND-OFF change" row and `bws_fold_chain_join()`'s own PHPDoc — the axis lives at the PHPDoc only,
derived after the `same` merge's rule changed axis three times and every site that had named one
went stale while every site that had named only the consequence stayed true.

**ONE NARROW EXEMPTION — A HARNESS MAY NAME AN AXIS IT MECHANICALLY PINS.** Where an assertion
beside the comment checks the statement, drift fails the suite by name and the prose cannot rot
unobserved; a test that cannot say what it tests is worse than one that repeats the owner. The
exemption is per-CLAUSE, not per-file, and the test is "does something below this sentence break if
it goes wrong" — so an UNPINNED clause in an otherwise-pinned block still takes a pointer (see
`slot-fold-test.php` §P16.4, whose DERIVATION SOURCE clause is pinned by nothing and points at
`bws_fold_chain_join()`'s PHPDoc instead). A stale comment never fails a suite; that is the whole
reason the exemption is this narrow, and why it is not a general licence for test files.

**A CLAIM ABOUT WHAT AN INSTRUMENT DOES IS MEASURED OR PINNED, NEVER INFERRED.** Prose describing
how an instrument behaves rests on a run, or on an assertion that holds the behavior. The code's
shape is not evidence for it, and neither is the code's own output: **a runtime message is an
instruction to the operator, not a description of the script.** The two rules above govern WHO may
state a rule and WHERE a pinned one may be repeated; this one governs what a sentence rests on, and
it is the rule the drift rule cannot reach — drift fires once two sides disagree, and a claim
nobody measured reads as agreement until someone checks. Where an instrument's doc and its code
already disagree, resolve it as drift; this clause is about not writing the sentence that way.

| Content type | Owner | Notes |
|---|---|---|
| User-facing tag overview / quickstart | `README.md` | Repo-visitor framing; don't replicate technical schemas. Prose describing work that is merged but not yet in a tagged release carries an `**[UNRELEASED]**` marker (heading suffix for a whole new section, paragraph prefix for a new paragraph, inline `**[UNRELEASED: <what>]**` for a clause inside existing prose), stripped when the release is tagged — see §Update triggers |
| Current architecture (templates, sources, options, GB types, render order) | `docs/tag-reference.md` | Authoritative |
| Cross-cutting vocabulary (output-shape terms: single-result, composite string, list mode, query loop; etc.) | Owning schema doc (e.g. `docs/tag-reference.md` §Output shape) | Defined ONCE beside the schema it describes — NO standalone glossary (avoids schema/glossary drift). `CONTEXT.md` invariants *use* terms, never define them. |
| Cross-cutting LIVE invariants / design models (source-analog, `use`-dispatch Model B, strip-default, qualifying gate, label-scope) | `CONTEXT.md` | Principles that span many callbacks + bind now. Links schemas in `tag-reference.md`, rationale in `docs/design-history/` once the plan is finished (a live plan until then). NOT schemas/state-tables/narrative. Post-ship migration target — see §Spec lifecycle. |
| Editor-time tag configuration preview text (markers, assembly, warnings, per-template + try_ shapes, examples) | `docs/editor-tag-previews.md` | Authoritative; `tag-reference.md` keeps a one-line forward-ref. Built by `bws_build_preview_label()` in `preview-helpers.php`. |
| Plugin's response to GB constraints (default-strip strategy, etc.) | `docs/tag-reference.md` | Lives alongside the architecture it shapes; editor-JS control *mechanism* owned by `docs/editor-controls.md` |
| GB-imposed constraints | `docs/gb-constraints.md` | Pure GB facts; our responses go in `tag-reference.md` |
| What a CO-RESIDENT plugin's own code does where it meets ours (hooks it registers, names it claims, what it does with a value we hand it) | `docs/coresident/<plugin>.md` | **One file per plugin** (`gb-query-enhancements.md` and `woocommerce.md` today; ACF and GB Pro when something is measured worth keeping). Not GB itself — pure GB facts stay in `gb-constraints.md`, which links across. Every claim dated and version-stamped, because none of it is ours to keep true. States the foreign fact and names where OUR response is enforced; never states the response's axis |
| External-plugin integration API | `docs/plugin-integration.md` | Code-level guide; link `tag-reference.md` for schemas |
| Custom editor-control architecture (`bws-*` control pattern, `tagSpecificControls` seam, `setState` param authority + `delete`-omit idiom, composite "two controls one key", dynamic labels / entry filter / reconcile-on-src-change, **the option-group WRAPPER** = `_group`/`_group_lead` + `option-group.js`'s CSS-joined run, lead-boxes-alone, captions-belong-to-controls) | `docs/editor-controls.md` | GB facts in `gb-constraints.md`; load-bearing invariants → PHPDoc on the control classes. **The split against `tag-reference.md` is by OPTION KEY:** a row with a key is catalog (name, label, help, values, conditionals) and stays there; a row describing a control, or a POSITION INSIDE A VALUE (a chain step's slug/arg/`limit(N)`), is mechanism and lives here. **The field-discovery MECHANISM is not here** — decoupled to `field-selector.md` (own ship/lifecycle); its `bws-field-combo` control + REST endpoint own their invariants via the spec issue → PHPDoc on ship. The field configuration NOTE that mechanism emits is here, beside the chain-step control that renders it. |
| Historical N×M tag names + **completed** rename trackers | `docs/deprecated-tags-options.md` | Migration reference only — no current-state info. In-progress / under-consideration renames stay in `tag-reference.md` until completed, then move here. |
| Post-content pipeline (helpers + history) | `docs/post-content-processing-reference.md` | Implementation + standalone-era history |
| Harvest/replay verification instrument (what it is, how a run is driven, what a clean diff proves) | `tools/harvest-replay/README.md` | Read FIRST when touching any of the three scripts. `docs/update-triggers.md` states the rules a run rides on; the CHANGELOG-facing outcome is not its business. `bin/harvest-tags.sh` + the harvest fixture live in the ENV repo. |
| Update-trigger RULES (what a harness run does and does not prove, per trigger) | `docs/update-triggers.md` | One section per trigger. **`CLAUDE.md` §Update triggers is the INDEX** — trigger + harnesses to run + link; a trigger is at full length in exactly one of the two, never both. A trigger with nothing to say past "update that doc" has no section here. |
| Full test-harness catalog (which is pure vs. requires-the-real-file, the three impure ones, the node-based ones) | `docs/testing.md` | `CLAUDE.md` §Development keeps the one-paragraph summary and the two-layer rule, points here for the catalog |
| Operating the fixture testbed (entrypoints, the two staleness layers, seeding, visible-row mandate, running page snapshots) | `docs/testbed.md` | `CLAUDE.md` §Development owns the two-LAYER rule and points here; this owns the operation. `bin/*.sh` live in the ENV repo. Blueprint specifics stay in `tools/fixtures/core-structures/README.md`. |
| Shipped versions | `CHANGELOG.md` | Append-only |
| Non-bug future-work TRACKER (visible index: item + blockers + interactions + pointer to detail home) | `docs/future-work.md` | Tracked/reviewable surface over hidden detail homes. Indexes, never duplicates detail. Columns: **Blocked by** (hard prereq), **Interacts with** (soft coupling), **Detail home** (design + implicit certainty). No status column — certainty is read from the detail home. **Bugs → GitHub Issues only, never here.** Avoid one GH issue per speculative enhancement. When unsure where work belongs, ASK. |
| SPEC for one in-flight piece of work (problem, interfaces, invariants, tasks, scope) | `.scratch/<feature-slug>/spec.md` | Gitignored, dies at merge; the PR body publishes it. Bugs are GitHub Issues and never a spec file. Split + conventions: `docs/agents/issue-tracker.md`. Lifecycle + post-ship migration: §Spec lifecycle. |
| Spec-lifecycle RULES (post-ship migration, plan-commits-when-finished / retirement mechanics, §SETTLED index practice, bug-filing criteria) | `docs/spec-lifecycle.md` | `CLAUDE.md` §Spec lifecycle keeps the one-paragraph summary, points here for the rules |
| Cross-link RULES (README-paraphrase limits, MEMORY.md one-liners, ADR-unverifiable-evidence rationale, detail-home exemption, bare-`#N` rule, design-history dangling-path exemption) | `docs/cross-link-rules.md` | `CLAUDE.md` §Cross-link rules keeps the summary + the hook pointer, points here for the rules |
| Pending-plan / enhancement DETAIL (homes the tracker points at) | `.scratch/plans/*.md`, GitHub `enhancement` issues, or `memory/` (cross-cutting concepts) | Not under `docs/` (except when migrated). Every item also gets a `docs/future-work.md` tracker row — don't leave work tracked only in a hidden file. |
| Rationale of record for a SHIPPED or RETIRED decision ("design history") | `docs/design-history/*.md` | Committed, historical, and **never corrected** — §Spec lifecycle owns that rule; the per-file banner restates it. An archived plan MOVES here the first time a committed file cites it (see §Cross-link rules); the rest stay private under `.scratch/plans/archive/`. **Cite one with a provenance verb** — "hardened against", "build record", "the decision that produced" — never "see X for how this works". That phrasing is the whole line between a record and a false current-state source, and it is the only one of these guards a diff can catch. |
| Claude session prefs / cross-session pointers | `memory/MEMORY.md` (external — Claude Code's per-project config dir, not in this repo) | Pointer index; don't duplicate doc content |
| Claude in-repo behavior + this policy | `CLAUDE.md` | Dependencies, dev workflow, and the §Update triggers INDEX (last section — trigger + harnesses + link); all schema and all trigger RULES deferred to `docs/` |
| Agent-skill config (issue tracker, triage labels, domain doc layout) | `docs/agents/*.md` | Consumed by Pocock engineering skills; set via `/setup-matt-pocock-skills` |

### Line wrapping

**Markdown prose is NOT hard-wrapped — one paragraph, one line.** Table rows, list items and code blocks keep their own lines. No exceptions by destination: a repo doc and a GitHub issue body take the same treatment. Commit messages are the one surface that stays wrapped (not Markdown, never reflowed, `git log` does not soft-wrap). What the rule costs on a POSTED surface is in `docs/agents/issue-tracker.md`.

**Going forward only.** Unwrap a file's prose when you are already editing it; a whole-repo sweep only when asked.

### This file's own budget

**CLAUDE.md carries RULES; the commit body carries the INSTANCE that produced them.** Test: would the
sentence still be true if the instance never happened? If the sentence IS the instance, it belongs in
the commit message — permanent, dated by construction, and found by `git log -S'<phrase>' -- CLAUDE.md`
exactly when someone asks why a rule is here. This file has been exempting itself from a rule it
states one level up (§Spec lifecycle: the CHANGELOG carries the delta, the commit body the cause).
An instance that IS a rule's boundary is not an example, and stays.

**NO SIZE CEILING, DELIBERATELY.** Most of this file is INDEX — ownership rows and trigger rows — and
an index grows with the repo by design: new owner doc, new row. A byte target points a trimmer at that
majority, where deleting a row removes no obligation, only its discoverability. Two clauses instead of
a number:

- **Never delete an ownership row whose doc still owns something, or a trigger row whose trigger still
  exists.** The safe operation is to move a row's DETAIL into the doc it links and leave the row short.
- **Only the PROSE has a budget, because only the prose accretes.** Watch it with
  `git log --stat -- CLAUDE.md`; nothing authorizes a cut on size alone. Evidence too big for a commit
  body goes to `docs/design-history/`, exempt from tidying by construction.

### Cross-link rules

Reference by link + section anchor, never copy; a doc no longer authoritative for a topic gets a
forward-reference, not stale text; moving a plan repoints every citation to it in the same edit.
Shipped code cites IDs, not private `.scratch/`/`.claude/` paths — mechanically enforced by
[`.claude/hooks/block-private-path-citation.sh`](.claude/hooks/block-private-path-citation.sh) for
`includes/`, `assets/`, `docs/adr/`, `README.md` and `CHANGELOG.md`; that hook is now the axis for
the exact scope, this section states the consequence.

Full rules (README-paraphrase limits, MEMORY.md one-liners, the ADR-unverifiable-evidence rationale,
the detail-home exemption, the bare-`#N`-means-GitHub-issue rule the hook can't check, the
design-history dangling-path exemption): [`docs/cross-link-rules.md`](docs/cross-link-rules.md).

## Spec lifecycle

**A SPEC IS A LOCAL FILE — `.scratch/<feature-slug>/spec.md`.** It owns the problem statement, the
interfaces, the invariants, the tasks and the scope for one in-flight piece of work. One per piece
of work, not per release. The PR body is where the decided spec becomes public — `.scratch/` is
gitignored and nothing under it is committed. Bugs stay GitHub Issues; `docs/agents/issue-tracker.md`
owns the split and the ticket conventions.

**The root `SPEC.md` artifact is RETIRED.** Do not create one. In-code citations of the form
`SPEC §V<n>` (or `SPEC.md §V<n>`) predate the retirement and dangle — repoint them to a real home
when you touch one; none is load-bearing.

Post-ship migration rules (where a finished plan's load-bearing invariants land), the
plan-commits-when-finished / retirement-split mechanics, the §SETTLED index practice for long-lived
plans, and the bug-filing criteria (deferred fix, external waiter, unpinned regression, second
instance of a defect class — never a same-session fix) are all in
[`docs/spec-lifecycle.md`](docs/spec-lifecycle.md).

## Agent skills

### Issue tracker

Hybrid: specs + build tickets are local markdown under `.scratch/<feature-slug>/` (gitignored, never committed — the PR body publishes); bugs and any record that must outlive the change are GitHub Issues for `davidofchatham/bws-gb-dynamic-tags-extensions` (`gh` CLI). See `docs/agents/issue-tracker.md`.

### Triage labels

Canonical default label strings, carried as GitHub labels on the GitHub half and as a `Status:` line on the local half. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context — `CONTEXT.md` + `docs/adr/` at the repo root. See `docs/agents/domain.md`.

## Update triggers

Touched something in the Trigger column? Run what the Run column names, then read the linked
**rules** section in [`docs/update-triggers.md`](docs/update-triggers.md) — that is where what the
run does and does not prove is stated, and it is not optional reading. A row with `—` under Rules
has nothing to add beyond what it says here.

| Trigger | Run | Rules |
|---|---|---|
| New source class / template / option key | `tag-reference.md` first; CHANGELOG entry | — |
| New/changed editor preview text (a `bws_build_preview_label` case) | `editor-tag-previews.md` (markers/field-part/warning/example rows) + run `php tools/test/preview-label-test.php` | — |
| Phone normalize / render / settings change (`bws_phone_normalize_tel` + sub-helpers, `bws_phone_callback`, `bws_phone_render_one`, phone settings/preview) | run `php tools/test/phone-normalize-test.php` (algorithm) + `tools/test/phone-test-matrix.md` rows against the testbed (`bws render-tag` / matrix pages; [docs/testbed.md](docs/testbed.md)) | — |
| Field-discovery change (`includes/rest/field-discovery.php` transforms, `assets/js/field-combo-control.js`, the enqueue/inline block, a flip of any option to/from `bws-field-combo`, or either half of the root-argument narrowing — the `scope` slug on `bws_entity_lookup_term_row()` / `bws_entity_lookup_post_row()`, and the `window.bwsRootArgKinds` inline; also the Location-filter PRESET and the two inlines it rides — `window.bwsChainKinds` off `bws_fold_wire_vocabulary()`, and `predecessorContext()` in `slot-fold-control.js`, the seam a chain step hands its successor's picker through) | `field-discovery-test.php`, `field-combo-control-test.js`, `entity-lookup-test.php`, `slot-fold-repeater-test.js`, `slot-fold-picker-seam-test.js`, `field-selector-test-matrix.md` | [rules](docs/update-triggers.md#field-discovery-change) |
| Text read-seam / link-wrap change (`bws_base_text_resolve_value`, `bws_base_text_callback`, `bws_wrap_with_link` / `bws_resolve_link_url`, or a new seam absorber e.g. `{{join}}` slots) | `tools/test/text-test-matrix.md` rows against the testbed (`bws render-tag`; [docs/testbed.md](docs/testbed.md)) | — |
| L2 read-seam change — `bws_read_resolved_source_value` in `field-helpers.php` (THE kind dispatch and the post/0 guard), `bws_read_resolved_source` (the string coercion over it, signature fixed since 1.14.0), or `bws_read_field_preserving_arrays` (the array-preserving post read the seam and `bws_get_meta_image_data()` share) | `read-resolved-source-test.php`, `loop-item-classify-test.php`, `traversal-pipeline-test.php` | — |
| Join assembly / slot change (`bws_join_callback`, `bws_get_join_options`, or any `includes/helpers/join-helpers.php` fn) | run `php tools/test/join-template-test.php` (pure Steps 1–5 + wire tokens) + `tools/test/join-test-matrix.md` rows against the testbed; a seam-implicating failure routes to the text matrix | — |
| Format-TOKEN change (`bws_join_wire_format`, its preview twin `bws_join_preview_format`, the Format control's help/placeholder, or `bws_migrate_join_format_escape`) | `join-template-test.php`, `fold-migration-test.php`, `preview-label-test.php`, `fold-test-matrix.md` | [rules](docs/update-triggers.md#format-token-change) |
| Datetime change (any `includes/helpers/datetime-helpers.php` fn, `bws_normalize_datetime_options` or its compat wrappers, the datetime cores/callbacks/option builders, or the datetime preview branch) | `datetime-format-test.php`, `datetime-migration-test.php`, `datetime-test-matrix.md` | [rules](docs/update-triggers.md#datetime-change) |
| Pre-1.6 DATETIME migration change — `MigrationRegistry::apply_datetime_transforms()` / `datetime_legacy_era()`, the `datetime_era_options` on either `type:'option'` datetime entry, or the datetime rename maps in `bws_register_v1_deprecated_tag_wrappers()` | `datetime-migration-test.php`, `datetime-format-test.php` | [rules](docs/update-triggers.md#pre-16-datetime-migration-change) |
| Image `as`/`size` change (`bws_parse_as_option`, `bws_get_image_size_options`, the `bws-as-size` control JS, either image migration (`bws_migrate_image_as_size_fold` = the `as`+`size` fold, `bws_migrate_image_as_bare_url` = the bare-`as:url` completion), or the image cores' `as`/size read) | `as-size-fold-test.php`, `fw52-order-test-matrix.md` | [rules](docs/update-triggers.md#image-assize-change) |
| Invisible per-tag editor control added/changed, or any change to how one WRAPS its anchor element (`serialization-order-normalizer.js`, `slot-fold-migrate.js`, or a new filter on `generateblocks.editor.tagSpecificControls`) | `editor-filter-chain-test.js` | [rules](docs/update-triggers.md#invisible-per-tag-editor-control-or-its-anchor-wrapper) |
| Serialization-order change (`bws_serialization_order_sort` or its map-shaped sibling `bws_serialization_order_sort_map` in `includes/helpers/serialization-order.php`, its JS port `serialization-order-normalizer.js`, or the canonical KEY_MAP/group ranks) | `serialization-order-test.php`, `datetime-migration-test.php`, `fw52-order-test-matrix.md` | [rules](docs/update-triggers.md#serialization-order-change) |
| New/changed fixture state a matrix or discovery row assumes | update the `core-structures` blueprint (`tools/fixtures/core-structures/` — manifest = data, schema = code, blocks = page markup), reseed (`bin/seed.sh testbed core-structures`), re-run `verify.php`, and re-capture the page-snapshot baseline in the SAME commit (new fixture output moves pages by design); keep matrices linking, not duplicating | — |
| New `*-test-matrix.md` rows for a tag family | ALSO generate them as visible GB blocks in `blocks.php` (see [docs/testbed.md](docs/testbed.md) "make them VISIBLE" — mandatory, missed twice); reseed + curl the front end + re-capture the page-snapshot baseline; matrix links, page shows | — |
| Page-snapshot instrument or baseline change (`tools/test/page-snapshots.php`, the committed baseline under `tools/test/snapshots/`, `tools/fixtures/core-structures/env-versions.php`, or `verify.php`'s snapshot section) | `php tools/test/page-snapshot-normalize-test.php`, then `php tools/test/page-snapshots.php` against the testbed | [rules](docs/update-triggers.md#page-snapshot-instrument-or-baseline-change) |
| Dependency version change on the fixture site — GenerateBlocks, GB Pro, GB Query Enhancements or ACF Pro moving version (the set `env-versions.php` records) | `php tools/test/page-snapshots.php`; re-capture the baseline + re-record `env-versions.php` in the SAME commit when the new output is right | [rules](docs/update-triggers.md#dependency-version-change) |
| New option rename | `deprecated-tags-options.md` tracker + `tag-reference.md` if it affects current names | — |
| New GB constraint discovered | `gb-constraints.md`; if it forces a design change, note the response in `tag-reference.md` | — |
| Editor preview-CONTEXT change — `assets/js/editor-preview-context.js`, or any new filter on `generateblocks.editor.preview.context` | `editor-preview-context-test.js` | [rules](docs/update-triggers.md#editor-preview-context-change) |
| New external-plugin API affordance | `plugin-integration.md`; CHANGELOG entry | — |
| `{{table}}` assembly / a11y change (`bws_table_assemble`, `bws_table_read_cell`, `bws_table_collect_columns`, the caption/wrapper markup, or `BWS_TABLE_INLINE_CSS`) | run `php tools/test/table-assemble-test.php` (pure assembly + caption-gated wrapper) + `tools/test/table-test-matrix.md` TB rows against the testbed ([docs/testbed.md](docs/testbed.md)); front-end curl to confirm the footer `<style>` prints once | — |
| Option CONTROL order — the ORDER options are registered in, on any of the three constructors (`bws_register_base_tags()`, `TagTemplateRegistry::register_modifier()` = the `term_` half, `TagTemplateRegistry::generate_base_try_tags()` = the `try_` half) | `control-order-test.php` | [rules](docs/update-triggers.md#option-control-order) |
| Modifier-family REGISTRATION GATE or GB-TYPE STAMP — the settings gate and the migration-registry `gb_type` read at the top of `TagTemplateRegistry::register_modifier()`, the `bws_register_modifier_root_migrations()` call in the init pass, or the `modifiers` array in `bws_dynamic_tags_activate()`'s seed | `control-order-test.php` §C3, then the testbed for the seed arm | [rules](docs/update-triggers.md#modifier-family-registration-gate-or-gb-type-stamp) |
| Registration-pass change (`bws_prepare_registration_options` in `registration-helpers.php` and the two rules riding it — `bws_option_visual_groups` = which options share a visual box, `bws_drop_chain_flat_options` = which flat controls a chain source retires) | `slot-options-build-test.php`, `control-order-test.php`, `editor-filter-chain-test.js` | [rules](docs/update-triggers.md#registration-pass-change) |
| Base-tag name-COLLISION boundary change (the yield arm reaches the `term_`/`try_` families too) — anything in `includes/helpers/gb-registration-boundary.php` (`bws_gb_register_tag` = the plugin's ONE registration site, `bws_gb_recheck_tag_ownership` = the late `wp_loaded` re-read that catches the reverse collision, `bws_gb_note_tag_yielded` = the third direction, a name the template constructors stand down from, `bws_gb_tags_we_registered`, `bws_gb_tag_name_collisions` = the per-request record every report surface reads, `bws_gb_report_tag_collision` = the notice channel + its per-tag-per-outcome dedupe, `bws_gb_collision_other_parties` = THE one place the previous/later field pairs are read as parties, `bws_gb_other_registrar_phrase`, `bws_gb_registrar_plugin_name` = the owner name derived off the registrar's file, `bws_gb_tag_registrar_file`), the `wp_loaded` hook in the plugin bootstrap, either template constructor's dup-check skip in `class-tag-template-registry.php`, or the settings page's read of the record (`SettingsPage::get_tag_collision_status()` + the Tag Name Conflicts block it feeds) | `control-order-test.php` (§9 tree scan + §10 driven collision, all three directions), `registrar-plugin-name-test.php` | [rules](docs/update-triggers.md#base-tag-name-collision-boundary-change) |
| Chain-ROOT offering change (`is_selectable_root()` on the source contract, `SourceRegistry::get_selectable_roots()`, the `bws_dynamic_tags_chain_roots` filter route + `Sources\CallbackRoot`, or `bws_registered_root_rows()` = the single appender both authoring surfaces read) | `slot-options-build-test.php`, `traversal-pipeline-test.php`, `preview-label-test.php`, `registered-roots-test-matrix.md` | [rules](docs/update-triggers.md#chain-root-offering-change) |
| Field-option LEAF or slot-read change (`bws_get_text_field_options` / `bws_get_content_field_options` / `bws_get_image_field_options` = the three `use`+`key` LEAVES, `bws_build_slot_read_options` = the slot READ twin, all `base-shared.php`; or any of their consumers — the base `{{text}}`/`{{content}}`/`{{image}}` registrations, their modifier templates, the try_ slot loop, `bws_get_join_options`) | `slot-options-build-test.php`, `use-stripped-default-test.php` | [rules](docs/update-triggers.md#field-option-leaf-or-slot-read-change) |
| Stripped-`use`-default change — `BWS_USE_STRIPPED_DEFAULTS` / `bws_use_stripped_default()` (`registration-helpers.php`), the first value of any leaf's `use` enum, or a read site recovering an absent `use` | `use-stripped-default-test.php`, `preview-label-test.php`, `slot-fold-test.php` | [rules](docs/update-triggers.md#stripped-use-default-change) |
| `limit` interpretation change (`bws_clamp_limit` in `field-helpers.php` — THE single interpreter; its four call sites are `bws_resolve_field_values`, `bws_collect_value_list`, try_ slot dispatch in `class-tag-template-registry.php`, `bws_try_join_items`) | `limit-clamp-test.php`, `try-join-seam-test.php` | [rules](docs/update-triggers.md#limit-interpretation-change) |
| Inline-CSS queue / content-extraction change (`bws_queue_inline_css` in `content-helpers.php`, or the `ContentProcessor` pure transforms `extract_and_queue_inline_styles` / `strip_block_comments` / `extract_css_from_block_comments` / `strip_dynamic_tags`) | run `php tools/test/inline-css-pipeline-test.php` (pure dedupe + extraction regexes) | — |
| Folded-slot CONTROL change (`assets/js/slot-fold-control.js` — repeater cardinality, the container seed, removal/compaction + `same` materialization, the `inferIntent` advisory, the field-picker bridges — `stepContext` / `fieldContext`, whose hand-off to the REAL picker only `slot-fold-picker-seam-test.js` sees) | `slot-fold-repeater-test.js`, `slot-fold-picker-seam-test.js` | [rules](docs/update-triggers.md#folded-slot-control-change) |
| Folded-slot REGISTRATION or RESOLUTION change (`bws_build_fold_slot_options` in `base-shared.php` = the `fold` config every container's control reads; `bws_fold_slot_struct` / `bws_fold_slot_chain_options` in `slot-fold.php` = the render seam; or a container's slot loop — `bws_join_callback`, `generate_base_try_tags()`'s slot loop, `bws_build_join_preview_label`, `bws_build_try_preview_label`) | `slot-options-build-test.php`, `slot-fold-test.php`, `preview-label-test.php`, `slot-fold-repeater-test.js` | [rules](docs/update-triggers.md#folded-slot-registration-or-resolution-change) |
| Fold MIGRATION change — CONVERTER half (`includes/helpers/slot-fold-migrate.php`, `TagTemplateRegistry::try_slot_axes()`, or the `type:'option'` fold registrations at the end of `bws_register_option_migrations()`), MOUNT half (`assets/js/slot-fold-migrate.js`), or the per-slot axis surface both read (`bws_fold_slot_flat_axes` in `slot-fold.php`, `flatAxes` on the fold config, either `tag_level` caller) — or an option-migration ENGINE change (`MigrationRegistry::entry_matches` / `apply_option_migration` / `find_option_migrations` / `parse_tag_string`) | `fold-migration-test.php`, `slot-options-build-test.php`, `slot-fold-repeater-test.js`, `slot-fold-test.php` | [rules](docs/update-triggers.md#fold-migration-change) |
| Related-post SOURCE-CLASS or `related_post` SRC-TOKEN change — `resolve_id()` on any of `RelatedPost` / `SecondRelatedPost` / `PostTermRelatedPost` / `TermRelatedPost`, `bws_migrate_related_post_src()` in `deprecated-tags.php` or its registration, or the `match_option_values` gate (`MigrationRegistry::entry_matches`, the single owner the converter's detection now shares) | `related-post-src-migration-test.php`, `fold-migration-test.php` | [rules](docs/update-triggers.md#related-post-source-class-or-related_post-src-token-change) |
| `rel` → `ref` repair change — `bws_migrate_rel_to_ref()` (ONE callback, all three families since #57 — the base foreach, its `term_` sibling loop, the try_ loop all register it), or the `related_post`-entry tag list it defers to, all in `bws_register_option_migrations()` | `related-post-src-migration-test.php` | [rules](docs/update-triggers.md#rel--ref-repair-change) |
| MODIFIER → BASE migration change — `bws_migrate_modifier_root_chain()` / `bws_modifier_base_options()` / `bws_modifier_base_target()` / `bws_modifier_root_transform()` / the ENTRY GENERATOR `bws_register_modifier_root_migrations()` in `deprecated-tags.php`, or `TagConverter::resolve_full_chain()`; also the two rules the transform reads a family's ROOT through — `bws_modifier_root_facts()` (takes_arg / term-context, both off the source contract) and `bws_modifier_skip_reason()` + `BWS_MODIFIER_SKIP_REASONS` (the skip channel's closed reason set) + `bws_modifier_skip_report_lines()` (its wording, censused against the enum); also the scan report's two READERS of that machinery and the root they both ride — `bws_modifier_skip_reason_for_tag()`, `bws_modifier_argless_rewrite()` (the D40 exemption population), `bws_modifier_entry_root()`, or the `modifier_root` key the entry generator records for them | `modifier-base-migration-test.php`, `fold-migration-test.php`, `registered-roots-test-matrix.md`, `context-test-matrix.md` §C-CONV | [rules](docs/update-triggers.md#modifier--base-migration-change) |
| Folded slot-value grammar change (anything in `includes/helpers/slot-fold.php` — separators, brackets, tokenizer/splitters, chain steps, read precedence, `bws_fold_from_flat`) | `slot-fold-test.php`, `slot-fold-twin-test.php` | [rules](docs/update-triggers.md#folded-slot-value-grammar-change) |
| Step INPUT-KIND change — `BWS_TRAVERSAL_STEP_INPUT_KINDS` in `traversal-pipeline.php` (which kinds each step type accepts), or the `accepts` derive in `bws_fold_wire_vocabulary()` (`base-shared.php` — since #70 the vocabulary helper assembles the per-slug step record the editor filters by; since V9 the engine list is keyed by the wire slugs themselves — no translation step) | `traversal-pipeline-test.php`, `slot-options-build-test.php`, `slot-fold-repeater-test.js` | [rules](docs/update-triggers.md#step-input-kind-change) |
| Resolved-source SHAPE change — a key added to or removed from what a step's coercer produces: `bws_pipeline_rows_to_sources` (the four PROVENANCE keys a repeater row carries — `parent_kind`/`parent_id`/`repeater`/`index`, unconsumed until FW-3), `bws_pipeline_ref_to_posts` or `bws_pipeline_terms_to_sources`, all `traversal-pipeline.php`, plus the file-header typedef that states the shape | `traversal-pipeline-test.php` (its expectations ARE the shape — a key added, removed or reordered fails it by name), `fold-chain-compile-test.php` | — |
| Chain COMPILE change (`includes/helpers/slot-fold-compile.php` — the `BWS_FOLD_STEP_TYPES` slug→arg-key map (V9 narrowed it: engine types ARE the wire slugs, the map carries only which key a step's argument rides), the root-vs-hop split, the depth-0 chain read, or either assembler that now lives there: `bws_field_values_assemble_steps` / `bws_wrapper_ref_steps`) — or the engine's PER-HOP limit in `bws_run_traversal` | `fold-chain-compile-test.php`, `traversal-pipeline-test.php`, `slot-fold-test.php` | [rules](docs/update-triggers.md#chain-compile-change) |
| `try_` slot ARM change — `includes/helpers/try-slot-arms.php` (the kind→arm table plus `bws_try_slot_arm()` / `bws_try_slot_base_branch_kind()`), or the dispatch and shared emit inside `TagTemplateRegistry::try_arm_resolver()` | `try-slot-arms-test.php`, `try-join-seam-test.php`, `limit-clamp-test.php`, `control-order-test.php`, `fold-test-matrix.md` | [rules](docs/update-triggers.md#try_-slot-arm-change) |
| `try_` ATTEMPT WALK change — `bws_try_run_attempts()` in `includes/helpers/try-slot-loop.php` (attempt cardinality, the carry hand-off, the per-slot read gate, the bound written back, first-non-empty-wins), or a family's `resolve_fn` descriptor key moving on or off a base resolve seam | `try-slot-loop-test.php`, `slot-fold-test.php`, `control-order-test.php`, then against the testbed `fold-test-matrix.md`, `text-test-matrix.md` §T8 and `php tools/test/page-snapshots.php` | [rules](docs/update-triggers.md#try_-attempt-walk-change) |
| Base-tag REFUSAL TEST change — `bws_base_read_refused()` in `base-shared.php` (the three refusals, of which the UNSERVED-KIND one is the newest), `BWS_BASE_WIRE_KINDS_ALWAYS_SERVED` beside it, or the `$serves` list at any of its seven call sites | `traversal-pipeline-test.php`, then against the testbed `fold-test-matrix.md` §F9.5j–n and `php tools/test/page-snapshots.php` | [rules](docs/update-triggers.md#base-tag-refusal-test-change) |
| ANY fold change that can move rendered output or the editor's slot UI (i.e. any of the five fold rows above) | `fold-test-matrix.md` | [rules](docs/update-triggers.md#any-fold-change-that-moves-rendered-output-or-the-editor-slot-ui) |
| SLOT SOURCE HAND-OFF change — `bws_fold_slot_chain_options()` in `slot-fold.php` (the seam that replaced the deleted `bws_fold_slot_flat_options()`, FW-71), the `same` merge it delegates to (`bws_fold_chain_join()` in `slot-fold-compile.php`), either container's slot loop, or the shared preview source namer extracted out of `bws_build_preview_label()` | `slot-fold-test.php`, `slot-fold-twin-test.php`, `preview-label-test.php`, `fold-test-matrix.md` | [rules](docs/update-triggers.md#slot-source-hand-off-change) |
| Harvest/replay verification change — `tools/harvest-replay/replay-tags.php`, `diff-replays.php`, `run-converter.php` or `replay-verdict.php` = the verdict rules extracted so a harness can reach them (this repo), or `bin/harvest-tags.sh` + `fixtures/harvest/harvest-tags.php` (the ENV repo) | `replay-source-identity-test.php`, `replay-verdict-test.php`, `replay-vacuity-test.php`, then *see detail* | [rules](docs/update-triggers.md#harvestreplay-verification-change) |
| GB Pro PATTERN-CACHE reconcile change — anything in `includes/classes/admin/class-pattern-cache.php`, the triggers that call it (`TagConverter::ajax_scan`/`ajax_migrate`, `bws_dynamic_tags_rebuild_allowlist_on_upgrade`, and `tools/harvest-replay/run-converter.php` — the harvest/replay driver, which mirrors the admin button and is not a trigger the plugin ships), the `bws_dynamic_tags_content_written` action in `migrate_post()`, or the reported line (`format_status` + `#bws-pattern-cache-status` + `setPatternCacheLine` in `admin-tag-scanner.js`) | `pattern-cache-test.php`, `replay-verdict-test.php` | [rules](docs/update-triggers.md#gb-pro-pattern-cache-reconcile-change) |
| Converter OWNERSHIP-GUARD change — `includes/helpers/converter-ownership.php` (`bws_converter_tag_ownership` = the pure predicate every content rewrite passes, `bws_converter_rewrite_allowed` = the WordPress-side gatherer, `BWS_CONVERTER_OWNERSHIP_REASONS` = the closed reason enum, `BWS_CONVERTER_OWNERSHIP_OPTIN_OPTION` = the per-name opt-in, `bws_converter_ownership_report_lines()` = the decline channel's wording + its `action` flag, censused against the enum), `TagConverter::apply_if_owned()` = the REWRITE asker, `TagConverter::classify_tag()` = the REPORT asker (the only other call site, and it writes nothing), or a rewrite loop in `TagConverter::migrate_post()` | `converter-ownership-test.php` | [rules](docs/update-triggers.md#converter-ownership-guard-change) |
| Scan-report CHANNEL change — `TagConverter::report_channels()` (the two channels' site-wide assembly + the D40 exemption line), `TagConverter::classify_tag()` (which channel a stored tag lands in, and the skip-before-ownership ORDER), `TagConverter::ajax_ownership_optin()` = the ONLY writer of the opt-in set, either channel's wording map, or the render half (`renderChannels` / `declinedRow` / `hasConvertibleWork` in `admin-tag-scanner.js`, the three section wrappers in `class-settings-page.php`) | `converter-ownership-test.php` §O4/§O7, `fold-migration-test.php` §M13, `modifier-base-migration-test.php` §V7 | — |
| Pipeline / helper internals change | `post-content-processing-reference.md` (if content-rendering) or PHPDoc only (if narrow) | — |
| User-visible feature ships | `README.md` overview update, marked `**[UNRELEASED]**` (see §Documentation ownership, README row) + CHANGELOG | — |
| Release finalized (a CHANGELOG version header loses `— unreleased`) | `grep -n 'UNRELEASED' README.md` and strip every marker, in the same commit as the version bump | — |
| Tag / source / option / default renamed | All four: `tag-reference.md` (current state), `deprecated-tags-options.md` (rename row), CHANGELOG, any code references | — |
| `limit`-default / list-slice change (`bws_clamp_limit` or any of its four call sites; also the `limit` help text, which states the `0` affordance) | `limit-default-test-matrix.md` | [rules](docs/update-triggers.md#limit-default--list-slice-change) |
| Source-gate change (`bws_source_gate` in `traversal-pipeline.php`, or the `$gate` thread through `bws_run_traversal` — the initial list and every hop, before the limit slice), or its SECOND application site (`bws_loop_item_gated_post_id` in `field-helpers.php` + the `bws_read_field` loop branch calling it — the same criterion applied to a query-loop item) | `traversal-pipeline-test.php`, `loop-item-classify-test.php`, `fold-test-matrix.md` §F17 + §F18, `verify.php`'s gate section | [rules](docs/update-triggers.md#source-gate-change) |
| Term-archive CRITERION change — `bws_wp_is_term_archive()` in `taxonomy-helpers.php` (THE one place we ask whether WP queried a term archive), or a call site moving on or off it (`bws_capture_ambient_signals`'s term arm; `bws_read_field`'s term-archive branch until FW-7 deletes it) | `loop-item-classify-test.php`, `traversal-pipeline-test.php`, `text-test-matrix.md` §T4 against the testbed | [rules](docs/update-triggers.md#term-archive-criterion-change) |
| Block-CONTEXT key change — `bws_is_query_loop_setup_phase()` in `content-helpers.php` (the setup-phase guard), or ANY new read of a key off `$instance->context` in shipped PHP | `block-context-keys-test.php` | [rules](docs/update-triggers.md#block-context-key-change) |
| Query-loop ITEM RECOGNITION change — `bws_classify_loop_item` and the three shape readers beside it (`bws_loop_item_term_id` / `bws_loop_item_user_id` / `bws_loop_item_post_id`, the last with its `bws_loop_item_url_key` comparison) in `field-helpers.php`, `bws_loop_item_is_post_or_row` (the predicate six render cores gate on, in place of bare `in_loop`), the shape returned by `bws_get_loop_item_context`, or step 2 of `bws_resolve_base_source` (which maps a recognized kind onto a source kind) | `loop-item-classify-test.php`, `traversal-pipeline-test.php`, `loop-test-matrix.md` §QLP + `context-test-matrix.md` §C-PROD rows against the testbed | — |
| Falsy-replacement guard change — either branch of the `generateblocks_dynamic_tag_replacement` filter in `includes/hooks.php` (the bare `'0'` pad, the `as:alt` pad), or its scope | `verify.php`'s zero-guard section (the two-arm byte pin + the T5.2 no-comments assumption), then `php tools/test/page-snapshots.php` against the testbed — the guard's visible rows are text matrix §T5, all on `/matrix-post-meta/` | — |
| GB trust-model consumption change — anything in `includes/helpers/gb-trust-boundary.php` (`bws_gb_user_can_author_dynamic_data` = THE one place we ask GB whether the current user may author dynamic data, `bws_gb_option_allowed_for_current_user` = the key-scoped second axis, `BWS_GB_TRUST_FALLBACK_READ_FROM`), a call site moving on or off it, the `verify.php` GB-trust-model section (P1 `applies` / P2 restricted save / P3 taint suppression) or its census exemptions, or the per-user gates riding the seam (`bws_site_read_option`, the datetime `'option'` branch in `bws_read_field`, the field-discovery REST `permission_callback` + envelope enqueue) | `gb-trust-boundary-test.php`, then against the testbed `verify.php`'s GB-trust-model section | [rules](docs/update-triggers.md#gb-trust-model-consumption-change) |
| GB output-BOUNDARY change — `BWS_GB_TAG_OUTPUT_OPTIONS` (the option keys GB's own output pipeline consumes), its recorded `BWS_GB_TAG_OUTPUT_OPTIONS_READ_FROM`, or `bws_gb_tag_output()` in `includes/helpers/gb-output-boundary.php`; also a call site moving on or off it, or a change to `bws_safe_content_output()` in `content-helpers.php` (the one caller that LAYERS its own unsets on top of the boundary) | `gb-output-boundary-test.php`, then against the testbed `fold-test-matrix.md` §F11b (visible row F11b.3b) and `text-test-matrix.md` §T5 (visible row T5.1b, the zero-plus-fallback pin), both on `/matrix-post-meta/` | — |
| Collapsing-capability change — `takes_first_usable` on a template record, the selector (`bws_read_bounded_sources` in `field-helpers.php`) or a consumer swap, the `$ignore_limits` thread (`bws_fold_chain_to_steps` → both assemblers → `bws_base_source_ids_of_kind`), the editor suppression conditional, or the `bws-fanning-advisory` control | `read-bounded-sources-test.php`, `fold-chain-compile-test.php`, `control-order-test.php`, `editor-filter-chain-test.js`, `fold-test-matrix.md` §F15 | — |
| Decision recorded in a plan file that carries a §SETTLED index (closed OR reopened) | add/flip its row in that plan's §SETTLED index **in the same edit**; rows are pointers, never content. See [`docs/spec-lifecycle.md`](docs/spec-lifecycle.md) §Long-lived plan files | — |
