# CLAUDE.md

See [README.md](README.md) and [`docs/tag-reference.md`](docs/tag-reference.md) for project overview and architecture.

## Dependencies

- WordPress (core APIs)
- GenerateBlocks plugin, required (`GenerateBlocks_Register_Dynamic_Tag`, `GenerateBlocks_Dynamic_Tags`, `GenerateBlocks_Dynamic_Tag_Callbacks`)
- GenerateBlocks Pro, optional (`GenerateBlocks_Pro_Dynamic_Tags_ACF`, `GenerateBlocks_Pro_Pattern_Library`) — plus two version-gated GB core classes present only on newer GB (`GenerateBlocks_Meta_Handler`, `GenerateBlocks_Dynamic_Tag_Security`); all four `class_exists()`-guarded, degrades gracefully when absent
- Custom fields plugin (ACF or compatible — all calls guarded with `function_exists()`)

## Development

No build pipeline or linter. Edit PHP directly, test in a WordPress environment.

**Two test layers — run the pure harness always, route integration through the testbed.**

1. **Pure harnesses** under `tools/test/` — no framework, no autoload; each runs via
`php tools/test/<name>.php`, exiting non-zero on failure. Older ones copy the pure functions they
exercise inline (house pattern); newer ones **require the real file** when it is pure, because a
test-local copy of the rule is the exact drift the extraction removed (`limit-clamp-test.php`,
`slot-options-build-test.php`, `slot-fold-test.php`, `fold-migration-test.php`,
`related-post-src-migration-test.php`, `pattern-cache-test.php`). Run the one whose domain you
touched — see §Update triggers for the key→harness map, or `ls tools/test/` for the full set. No
CI runs these; run them locally before commit.

   **One is not pure and cannot be** — `control-order-test.php`, the only harness that sees all
   three registration constructors at once (its own header has the full rationale).

   **Some run under `node`, not `php`** — `slot-fold-repeater-test.js`, `editor-filter-chain-test.js`
   (pure JS, reach editor-only logic no PHP harness can), and three PHP harnesses that shell out to
   `node` for a twin-language check (`slot-fold-twin-test.php`, `serialization-order-test.php`,
   `fold-migration-test.php`) — a missing `node` FAILS these rather than skipping, since a silent
   pass would hide exactly the drift each exists to catch. Each file's own header has its mechanism.

2. **WordPress integration — the fixture testbed.** The pure harnesses can't reach anything
WP-dependent (ambient context, ACF/meta reads, GB render, the editor React controls). For that
there is a seeded WP site on the local **wp-litespeed** OpenLiteSpeed/Docker env, site `testbed`.
**Prefer routing integration smoke tests through it over hand-built pages or live-site probes.**
The two entrypoints are `bin/wp.sh testbed bws render-tag` (renders against real ambient context —
the cheap what-if engine) and `bin/seed.sh testbed core-structures` (reseeds fixture state).

   **[`docs/testbed.md`](docs/testbed.md) owns operating it, and reading it is not optional before
   an integration run.** Two layers of staleness sit between an edit and what you read (the page
   cache, and a bytecode cache that makes front-end mutation testing silently vacuous), the
   `bin/*.sh` commands live in the ENV repo rather than here, and the **mandatory rule that every
   new matrix row group is also generated as VISIBLE GB blocks** is stated there.

## Documentation ownership

Single source of truth per content type. Other files link, never duplicate.

**On doc/code drift the OWNER DOC wins and the CODE moves to match it.** The doc is where
a decision was recorded; shipped code is only evidence of what happened to get written.
Resolving the other way — editing the doc to describe what shipped — is an EXCEPTION, needs
saying out loud, and gets a note at the site of the change explaining why it was allowed
there (see `tools/test/control-order-test.php`'s `showCurrentYear` comment, the one
standing instance). An exception is not precedent: the next drift moves the code.

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

| Content type | Owner | Notes |
|---|---|---|
| User-facing tag overview / quickstart | `README.md` | Repo-visitor framing; don't replicate technical schemas |
| Current architecture (templates, sources, options, GB types, render order) | `docs/tag-reference.md` | Authoritative |
| Cross-cutting vocabulary (output-shape terms: single-result, composite string, list mode, query loop; etc.) | Owning schema doc (e.g. `docs/tag-reference.md` §Output shape) | Defined ONCE beside the schema it describes — NO standalone glossary (avoids schema/glossary drift). `CONTEXT.md` invariants *use* terms, never define them. |
| Cross-cutting LIVE invariants / design models (source-analog, `use`-dispatch Model B, strip-default, qualifying gate, label-scope) | `CONTEXT.md` | Principles that span many callbacks + bind now. Links schemas in `tag-reference.md`, rationale in `.claude/plans/archive/`. NOT schemas/state-tables/narrative. Post-ship migration target — see §Spec lifecycle. |
| Editor-time tag configuration preview text (markers, assembly, warnings, per-template + try_ shapes, examples) | `docs/editor-tag-previews.md` | Authoritative; `tag-reference.md` keeps a one-line forward-ref. Built by `bws_build_preview_label()` in `preview-helpers.php`. |
| Plugin's response to GB constraints (default-strip strategy, etc.) | `docs/tag-reference.md` | Lives alongside the architecture it shapes; editor-JS control *mechanism* owned by `docs/editor-controls.md` |
| GB-imposed constraints | `docs/gb-constraints.md` | Pure GB facts; our responses go in `tag-reference.md` |
| External-plugin integration API | `docs/plugin-integration.md` | Code-level guide; link `tag-reference.md` for schemas |
| Custom editor-control architecture (`bws-*` control pattern, `tagSpecificControls` seam, `setState` param authority + `delete`-omit idiom, composite "two controls one key", dynamic labels / entry filter / reconcile-on-src-change, **the option-group WRAPPER** = `_group`/`_group_lead` + `option-group.js`'s CSS-joined run, lead-boxes-alone, captions-belong-to-controls) | `docs/editor-controls.md` | GB facts in `gb-constraints.md`; load-bearing invariants → PHPDoc on the control classes. **The split against `tag-reference.md` is by OPTION KEY:** a row with a key is catalog (name, label, help, values, conditionals) and stays there; a row describing a control, or a POSITION INSIDE A VALUE (a chain step's slug/arg/`limit(N)`), is mechanism and lives here. **The field-discovery MECHANISM is not here** — decoupled to `field-selector.md` (own ship/lifecycle); its `bws-field-combo` control + REST endpoint own their invariants via the spec issue → PHPDoc on ship. The field configuration NOTE that mechanism emits is here, beside the chain-step control that renders it. |
| Historical N×M tag names + **completed** rename trackers | `docs/deprecated-tags-options.md` | Migration reference only — no current-state info. In-progress / under-consideration renames stay in `tag-reference.md` until completed, then move here. |
| Post-content pipeline (helpers + history) | `docs/post-content-processing-reference.md` | Implementation + standalone-era history |
| Harvest/replay verification instrument (what it is, how a run is driven, what a clean diff proves) | `tools/harvest-replay/README.md` | Read FIRST when touching any of the three scripts. `docs/update-triggers.md` states the rules a run rides on; the CHANGELOG-facing outcome is not its business. `bin/harvest-tags.sh` + the harvest fixture live in the ENV repo. |
| Update-trigger RULES (what a harness run does and does not prove, per trigger) | `docs/update-triggers.md` | One section per trigger. **`CLAUDE.md` §Update triggers is the INDEX** — trigger + harnesses to run + link; a trigger is at full length in exactly one of the two, never both. A trigger with nothing to say past "update that doc" has no section here. |
| Operating the fixture testbed (entrypoints, the two staleness layers, seeding, visible-row mandate) | `docs/testbed.md` | `CLAUDE.md` §Development owns the two-LAYER rule and points here; this owns the operation. `bin/*.sh` live in the ENV repo. Blueprint specifics stay in `tools/fixtures/core-structures/README.md`. |
| Shipped versions | `CHANGELOG.md` | Append-only |
| Non-bug future-work TRACKER (visible index: item + blockers + interactions + pointer to detail home) | `docs/future-work.md` | Tracked/reviewable surface over hidden detail homes. Indexes, never duplicates detail. Columns: **Blocked by** (hard prereq), **Interacts with** (soft coupling), **Detail home** (design + implicit certainty). No status column — certainty is read from the detail home. **Bugs → GitHub Issues only, never here.** Avoid one GH issue per speculative enhancement. When unsure where work belongs, ASK. |
| Pending-plan / enhancement DETAIL (homes the tracker points at) | `.claude/plans/*.md`, GitHub `enhancement` issues, or `memory/` (cross-cutting concepts) | Not under `docs/` (except when migrated). Every item also gets a `docs/future-work.md` tracker row — don't leave work tracked only in a hidden file. |
| Claude session prefs / cross-session pointers | `memory/MEMORY.md` (external — Claude Code's per-project config dir, not in this repo) | Pointer index; don't duplicate doc content |
| Claude in-repo behavior + this policy | `CLAUDE.md` | Dependencies, dev workflow, and the §Update triggers INDEX (last section — trigger + harnesses + link); all schema and all trigger RULES deferred to `docs/` |
| Agent-skill config (issue tracker, triage labels, domain doc layout) | `docs/agents/*.md` | Consumed by Pocock engineering skills; set via `/setup-matt-pocock-skills` |

### Cross-link rules

- Reference by **link + section anchor**, never copy.
- README may paraphrase technical detail for end-user framing — must not contradict `tag-reference.md`.
- MEMORY.md entries pointing at `docs/` are one-liners only.
- When a doc is no longer authoritative for a topic, replace the content with a forward-reference rather than leaving stale text.

## Spec lifecycle

**A SPEC IS A GITHUB ISSUE, labelled `ready-for-agent`.** It owns the problem statement, the
interfaces, the invariants, the tasks and the scope for one in-flight piece of work — see
`gh issue list --label ready-for-agent` for what's currently open. One in flight per piece of
work, not per release: several can be open, each closing on its own.

**The root `SPEC.md` artifact is RETIRED.** Do not create one. In-code citations of the form
`SPEC §V<n>` predate the retirement and dangle — repoint them to a real home when you touch one;
none is load-bearing. Two spellings exist (`SPEC §V<n>` and `SPEC.md §V<n>`) — grep BOTH when
sweeping.

**AN ARCHIVED PLAN IS NOT CORRECTED WHEN POLICY CHANGES.** `.claude/plans/archive/` records what was
true when it was written — `handoff-3-state-and-pickup.md` says "SPEC.md at repo root is the live
spec" and that is EVIDENCE of how the repo used to work, not a stale pointer to fix. Editing it
deletes the record. Same posture this section already takes on a closed spec issue: a record of how
something came to be, not a statement of how it currently works. A LIVE plan is the opposite case and
gets corrected, present tense being a claim about now.

**Post-ship migration is mandatory and is UNCHANGED by that** — it never depended on the artifact:

- **Load-bearing invariants** migrate to:
  - **PHPDoc on the class/method that enforces them** (primary — for any invariant a single class/method enforces), OR
  - **`CONTEXT.md`** (for cross-cutting invariants / design models spanning many callbacks — the source-analog model, dispatch rules, qualifying gate; principles, not schemas), OR
  - **`docs/tag-reference.md`** (for current-state schema detail an invariant references).
  - A migrating invariant typically lands a one-line principle in CONTEXT.md that links its schema in tag-reference and its rationale in `.claude/plans/<feature>.md` (or its `archive/`). Per §Documentation ownership, an invariant's AXIS lands at ONE of these and the others state its consequence.
- Closed/deferred task rows: delete them from the issue's checklist, or close the issue.
- Bugs found on the way: file per the rule below, cross-referencing the invariant they produced if one was added.

**An issue is source of truth only while the work is in flight.** A closed spec issue is a record
of how something came to be, not a statement of how it currently works — the same reading posture
`CONTEXT.md` opens with.

**Bugs:** there is no in-repo bug file, and a bug never becomes a row in `docs/future-work.md`.
A bug that needs TRACKING is a GitHub Issue (`bug` label). **A bug found and FIXED in the same
change does NOT need one** — the CHANGELOG carries the user-visible delta, the commit body the
cause, and the regression pin the rule; an issue opened and closed in one motion is a fourth copy
of a record three places already hold, and the one least likely to stay accurate. File one when the
record must OUTLIVE the change:

- the fix is deferred or partial;
- someone outside is waiting on status;
- **nothing pins it** — if no test fails when it regresses, the issue is the only memory;
- it is the **SECOND instance of a defect class**. The class is then what wants tracking, not the
  instance: no comment at either enforcing site can see the other, and only a tracked row makes the
  third instance recognisable as one. Live example: [#119](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/119),
  opened over #111, #116 and the 1.17.1 as+size over-match — three doors into "the scan reports what
  the run declines".

The rule that was here through 1.17.0 said "always", and the cost of that spelling is why it
changed: a rule broken routinely stops carrying signal, and a review that flags every same-session
fix trains you to stop reading its flags.

### Long-lived plan files — the §SETTLED index

A plan that accrues decisions across many passes fails a specific way: **supersession in place.** Live
decisions and withdrawn drafts sit interleaved, and both read as authoritative unless the reader
catches the banner. Length is not the mechanism — discoverability is. Symptom to watch for: an agent
re-deriving from code a question the plan already closed.

When a plan reaches that state, give it a **§SETTLED index at the top**: one row per decision, with
the section title as the anchor (line numbers drift on every edit — record them as a convenience
only, never as the identifier), a container-sensitivity column where the domain has one, and a
**separate OPEN table**, which matters as much as the settled one — treating undecided things as
decided is the more common failure.

The index is pointers, never content; the sections stay authoritative. On archive, the index goes
with the plan and its trigger row in §Update triggers is deleted — this section stays, because the
practice is reusable and the next long-lived plan will need it.

## Agent skills

### Issue tracker

Issues live in GitHub Issues for `davidofchatham/bws-gb-dynamic-tags-extensions` (uses the `gh` CLI). See `docs/agents/issue-tracker.md`.

### Triage labels

Canonical default label strings; `wontfix` already exists in the repo, the other four are created on first use. See `docs/agents/triage-labels.md`.

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
| Field-discovery change (`includes/rest/field-discovery.php` transforms, `assets/js/field-combo-control.js`, the enqueue/inline block, or a flip of any option to/from `bws-field-combo`) | `field-discovery-test.php`, `slot-fold-repeater-test.js`, `field-selector-test-matrix.md` | [rules](docs/update-triggers.md#field-discovery-change) |
| Text read-seam / link-wrap change (`bws_base_text_resolve_value`, `bws_base_text_callback`, `bws_wrap_with_link` / `bws_resolve_link_url`, or a new seam absorber e.g. `{{join}}` slots) | `tools/test/text-test-matrix.md` rows against the testbed (`bws render-tag`; [docs/testbed.md](docs/testbed.md)) | — |
| Join assembly / slot change (`bws_join_callback`, `bws_get_join_options`, or any `includes/helpers/join-helpers.php` fn) | run `php tools/test/join-template-test.php` (pure Steps 1–5 + wire tokens) + `tools/test/join-test-matrix.md` rows against the testbed; a seam-implicating failure routes to the text matrix | — |
| Format-TOKEN change (`bws_join_wire_format`, its preview twin `bws_join_preview_format`, the Format control's help/placeholder, or `bws_migrate_join_format_escape`) | `join-template-test.php`, `fold-migration-test.php`, `preview-label-test.php`, `fold-test-matrix.md` | [rules](docs/update-triggers.md#format-token-change) |
| Datetime change (any `includes/helpers/datetime-helpers.php` fn, `bws_normalize_datetime_options` or its compat wrappers, the datetime cores/callbacks/option builders, or the datetime preview branch) | `datetime-format-test.php`, `datetime-migration-test.php`, `datetime-test-matrix.md` | [rules](docs/update-triggers.md#datetime-change) |
| Pre-1.6 DATETIME migration change — `MigrationRegistry::apply_datetime_transforms()` / `datetime_legacy_era()`, the `datetime_era_options` on either `type:'option'` datetime entry, or the datetime rename maps in `bws_register_v1_deprecated_tag_wrappers()` | `datetime-migration-test.php`, `datetime-format-test.php` | [rules](docs/update-triggers.md#pre-16-datetime-migration-change) |
| Image `as`/`size` change (`bws_parse_as_option`, `bws_get_image_size_options`, the `bws-as-size` control JS, either image migration (`bws_migrate_image_as_size_fold` = the `as`+`size` fold, `bws_migrate_image_as_bare_url` = the bare-`as:url` completion), or the image cores' `as`/size read) | `as-size-fold-test.php`, `fw52-order-test-matrix.md` | [rules](docs/update-triggers.md#image-assize-change) |
| Invisible per-tag editor control added/changed, or any change to how one WRAPS its anchor element (`serialization-order-normalizer.js`, `slot-fold-migrate.js`, or a new filter on `generateblocks.editor.tagSpecificControls`) | `editor-filter-chain-test.js` | [rules](docs/update-triggers.md#invisible-per-tag-editor-control-or-its-anchor-wrapper) |
| Serialization-order change (`bws_serialization_order_sort` or its map-shaped sibling `bws_serialization_order_sort_map` in `includes/helpers/serialization-order.php`, its JS port `serialization-order-normalizer.js`, or the canonical KEY_MAP/group ranks) | `serialization-order-test.php`, `datetime-migration-test.php`, `fw52-order-test-matrix.md` | [rules](docs/update-triggers.md#serialization-order-change) |
| New/changed fixture state a matrix or discovery row assumes | update the `core-structures` blueprint (`tools/fixtures/core-structures/` — manifest = data, schema = code, blocks = page markup), reseed (`bin/seed.sh testbed core-structures`), re-run `verify.php`; keep matrices linking, not duplicating | — |
| New `*-test-matrix.md` rows for a tag family | ALSO generate them as visible GB blocks in `blocks.php` (see [docs/testbed.md](docs/testbed.md) "make them VISIBLE" — mandatory, missed twice); reseed + curl the front end; matrix links, page shows | — |
| New option rename | `deprecated-tags-options.md` tracker + `tag-reference.md` if it affects current names | — |
| New GB constraint discovered | `gb-constraints.md`; if it forces a design change, note the response in `tag-reference.md` | — |
| New external-plugin API affordance | `plugin-integration.md`; CHANGELOG entry | — |
| `{{table}}` assembly / a11y change (`bws_table_assemble`, `bws_table_read_cell`, `bws_table_collect_columns`, the caption/wrapper markup, or `BWS_TABLE_INLINE_CSS`) | run `php tools/test/table-assemble-test.php` (pure assembly + caption-gated wrapper) + `tools/test/table-test-matrix.md` TB rows against the testbed ([docs/testbed.md](docs/testbed.md)); front-end curl to confirm the footer `<style>` prints once | — |
| Option CONTROL order — the ORDER options are registered in, on any of the three constructors (`bws_register_base_tags()`, `TagTemplateRegistry::register_modifier()` = the `term_` half, `TagTemplateRegistry::generate_base_try_tags()` = the `try_` half) | `control-order-test.php` | [rules](docs/update-triggers.md#option-control-order) |
| Registration-pass change (`bws_prepare_registration_options` in `registration-helpers.php` and the two rules riding it — `bws_option_visual_groups` = which options share a visual box, `bws_drop_chain_flat_options` = which flat controls a chain source retires) | `slot-options-build-test.php`, `control-order-test.php`, `editor-filter-chain-test.js` | [rules](docs/update-triggers.md#registration-pass-change) |
| Chain-ROOT offering change (`is_selectable_root()` on the source contract, `SourceRegistry::get_selectable_roots()`, the `bws_dynamic_tags_chain_roots` filter route + `Sources\CallbackRoot`, or `bws_registered_root_rows()` = the single appender both authoring surfaces read) | `slot-options-build-test.php`, `traversal-pipeline-test.php`, `preview-label-test.php`, `registered-roots-test-matrix.md` | [rules](docs/update-triggers.md#chain-root-offering-change) |
| Text field-option or slot-read change (`bws_get_text_field_options` = the text `use`+`key` LEAF, `bws_build_slot_read_options` = the slot READ twin, both `base-shared.php`; or any of their consumers — base `{{text}}` registration, the `text` modifier template, the try_ slot loop, `bws_get_join_options`) | `slot-options-build-test.php` | [rules](docs/update-triggers.md#text-field-option-or-slot-read-change) |
| `limit` interpretation change (`bws_clamp_limit` in `field-helpers.php` — THE single interpreter; its four call sites are `bws_resolve_field_values`, `bws_collect_value_list`, try_ slot dispatch in `class-tag-template-registry.php`, `bws_try_join_items`) | `limit-clamp-test.php`, `try-join-seam-test.php` | [rules](docs/update-triggers.md#limit-interpretation-change) |
| Inline-CSS queue / content-extraction change (`bws_queue_inline_css` in `content-helpers.php`, or the `ContentProcessor` pure transforms `extract_and_queue_inline_styles` / `strip_block_comments` / `extract_css_from_block_comments` / `strip_dynamic_tags`) | run `php tools/test/inline-css-pipeline-test.php` (pure dedupe + extraction regexes) | — |
| Folded-slot CONTROL change (`assets/js/slot-fold-control.js` — repeater cardinality, the container seed, removal/compaction + `same` materialization, the `inferIntent` advisory, the field-picker bridges) | `slot-fold-repeater-test.js` | [rules](docs/update-triggers.md#folded-slot-control-change) |
| Folded-slot REGISTRATION or RESOLUTION change (`bws_build_fold_slot_options` in `base-shared.php` = the `fold` config every container's control reads; `bws_fold_slot_struct` / `bws_fold_slot_chain_options` in `slot-fold.php` = the render seam; or a container's slot loop — `bws_join_callback`, `generate_base_try_tags()`'s slot loop, `bws_build_join_preview_label`, `bws_build_try_preview_label`) | `slot-options-build-test.php`, `slot-fold-test.php`, `preview-label-test.php`, `slot-fold-repeater-test.js` | [rules](docs/update-triggers.md#folded-slot-registration-or-resolution-change) |
| Fold MIGRATION change — CONVERTER half (`includes/helpers/slot-fold-migrate.php`, `TagTemplateRegistry::try_slot_axes()`, or the `type:'option'` fold registrations at the end of `bws_register_option_migrations()`), MOUNT half (`assets/js/slot-fold-migrate.js`), or the per-slot axis surface both read (`bws_fold_slot_flat_axes` in `slot-fold.php`, `flatAxes` on the fold config, either `tag_level` caller) — or an option-migration ENGINE change (`MigrationRegistry::entry_matches` / `apply_option_migration` / `find_option_migrations` / `parse_tag_string`) | `fold-migration-test.php`, `slot-options-build-test.php`, `slot-fold-repeater-test.js`, `slot-fold-test.php` | [rules](docs/update-triggers.md#fold-migration-change) |
| Related-post SOURCE-CLASS or `related_post` SRC-TOKEN change — `resolve_id()` on any of `RelatedPost` / `SecondRelatedPost` / `PostTermRelatedPost` / `TermRelatedPost`, `bws_migrate_related_post_src()` in `deprecated-tags.php` or its registration, or the `match_option_values` gate (`MigrationRegistry::entry_matches`, the single owner the converter's detection now shares) | `related-post-src-migration-test.php`, `fold-migration-test.php` | [rules](docs/update-triggers.md#related-post-source-class-or-related_post-src-token-change) |
| `rel` → `ref` repair change — `bws_migrate_rel_to_ref()` (ONE callback, all three families since #57 — the base foreach, its `term_` sibling loop, the try_ loop all register it), or the `related_post`-entry tag list it defers to, all in `bws_register_option_migrations()` | `related-post-src-migration-test.php` | [rules](docs/update-triggers.md#rel--ref-repair-change) |
| MODIFIER → BASE migration change — `bws_migrate_modifier_root_chain()` / `bws_modifier_base_options()` / `bws_modifier_base_target()` / `bws_modifier_root_transform()` / the ENTRY GENERATOR `bws_register_modifier_root_migrations()` in `deprecated-tags.php`, or `TagConverter::resolve_full_chain()` | `modifier-base-migration-test.php`, `fold-migration-test.php`, `registered-roots-test-matrix.md` | [rules](docs/update-triggers.md#modifier--base-migration-change) |
| Folded slot-value grammar change (anything in `includes/helpers/slot-fold.php` — separators, brackets, tokenizer/splitters, chain steps, read precedence, `bws_fold_from_flat`) | `slot-fold-test.php`, `slot-fold-twin-test.php` | [rules](docs/update-triggers.md#folded-slot-value-grammar-change) |
| Step INPUT-KIND change — `BWS_TRAVERSAL_STEP_INPUT_KINDS` in `traversal-pipeline.php` (which kinds each step type accepts), or the `accepts` derive in `bws_fold_wire_vocabulary()` (`base-shared.php` — since #70 the vocabulary helper assembles the per-slug step record the editor filters by; since V9 the engine list is keyed by the wire slugs themselves — no translation step) | `traversal-pipeline-test.php`, `slot-options-build-test.php`, `slot-fold-repeater-test.js` | [rules](docs/update-triggers.md#step-input-kind-change) |
| Chain COMPILE change (`includes/helpers/slot-fold-compile.php` — the `BWS_FOLD_STEP_TYPES` slug→arg-key map (V9 narrowed it: engine types ARE the wire slugs, the map carries only which key a step's argument rides), the root-vs-hop split, the depth-0 chain read, or either assembler that now lives there: `bws_field_values_assemble_steps` / `bws_wrapper_ref_steps`) — or the engine's PER-HOP limit in `bws_run_traversal` | `fold-chain-compile-test.php`, `traversal-pipeline-test.php`, `slot-fold-test.php` | [rules](docs/update-triggers.md#chain-compile-change) |
| `try_` slot ARM change — `includes/helpers/try-slot-arms.php` (the kind→arm table plus `bws_try_slot_arm()` / `bws_try_slot_base_branch_kind()`), or the dispatch and shared emit in `TagTemplateRegistry::generate_base_try_tags()`'s callback | `try-slot-arms-test.php`, `try-join-seam-test.php`, `limit-clamp-test.php`, `control-order-test.php`, `fold-test-matrix.md` | [rules](docs/update-triggers.md#try_-slot-arm-change) |
| ANY fold change that can move rendered output or the editor's slot UI (i.e. any of the five fold rows above) | `fold-test-matrix.md` | [rules](docs/update-triggers.md#any-fold-change-that-moves-rendered-output-or-the-editor-slot-ui) |
| SLOT SOURCE HAND-OFF change — `bws_fold_slot_chain_options()` in `slot-fold.php` (the seam that replaced the deleted `bws_fold_slot_flat_options()`, FW-71), the `same` merge it delegates to (`bws_fold_chain_join()` in `slot-fold-compile.php`), either container's slot loop, or the shared preview source namer extracted out of `bws_build_preview_label()` | `slot-fold-test.php`, `slot-fold-twin-test.php`, `preview-label-test.php`, `fold-test-matrix.md` | [rules](docs/update-triggers.md#slot-source-hand-off-change) |
| Harvest/replay verification change — `tools/harvest-replay/replay-tags.php`, `diff-replays.php` or `run-converter.php` (this repo), or `bin/harvest-tags.sh` + `fixtures/harvest/harvest-tags.php` (the ENV repo) | *see detail* | [rules](docs/update-triggers.md#harvestreplay-verification-change) |
| GB Pro PATTERN-CACHE reconcile change — anything in `includes/classes/admin/class-pattern-cache.php`, the triggers that call it (`TagConverter::ajax_scan`/`ajax_migrate`, `bws_dynamic_tags_rebuild_allowlist_on_upgrade`, and `tools/harvest-replay/run-converter.php` — the harvest/replay driver, which mirrors the admin button and is not a trigger the plugin ships), the `bws_dynamic_tags_content_written` action in `migrate_post()`, or the reported line (`format_status` + `#bws-pattern-cache-status` + `setPatternCacheLine` in `admin-tag-scanner.js`) | `pattern-cache-test.php` | [rules](docs/update-triggers.md#gb-pro-pattern-cache-reconcile-change) |
| Pipeline / helper internals change | `post-content-processing-reference.md` (if content-rendering) or PHPDoc only (if narrow) | — |
| User-visible feature ships | `README.md` overview update + CHANGELOG | — |
| Tag / source / option / default renamed | All four: `tag-reference.md` (current state), `deprecated-tags-options.md` (rename row), CHANGELOG, any code references | — |
| `limit`-default / list-slice change (`bws_clamp_limit` or any of its four call sites; also the `limit` help text, which states the `0` affordance) | `limit-default-test-matrix.md` | [rules](docs/update-triggers.md#limit-default--list-slice-change) |
| Decision recorded in a plan file that carries a §SETTLED index (closed OR reopened) | add/flip its row in that plan's §SETTLED index **in the same edit**; rows are pointers, never content. See §Long-lived plan files under §Spec lifecycle | — |
| ⏳ **TEMPORARY — delete this row when `.claude/plans/src-chain-encoding.md` archives** | *see detail* | [rules](docs/update-triggers.md#temporary--plan-file-settled-precedence) |
