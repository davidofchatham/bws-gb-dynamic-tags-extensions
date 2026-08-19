# core-structures blueprint

First fixture blueprint (fixture-testbed FW-42). Seeds the state the two existing
manual matrices assume:

- [`tools/test/phone-test-matrix.md`](../../test/phone-test-matrix.md)
- [`tools/test/field-selector-test-matrix.md`](../../test/field-selector-test-matrix.md)
- [`tools/test/text-test-matrix.md`](../../test/text-test-matrix.md) (added 1.14.1 — read-seam rows; uses `staff-tom-associate` + `bws_zero_probe`)
- [`tools/test/join-test-matrix.md`](../../test/join-test-matrix.md) (added 1.15.0 — {{join}} assembly rows; `name_*` person parts dense on `tom-associate` / sparse on `jane-partner`, `role` + `height_*` on `matrix-post-meta`; manifest v2)
- [`tools/test/context-test-matrix.md`](../../test/context-test-matrix.md) (added 1.15.0 — context-aware base tags #19; author-archive C3/C13 via `fixture-author` user, date-archive rows via categoryless portal-visible `sample-event`, `department-sales` description for C17; manifest v4)
- [`tools/test/fw52-order-test-matrix.md`](../../test/fw52-order-test-matrix.md) (added for FW-52 — EDITOR-EYEBALL serialization-order rows on `matrix-post-meta`; a seeded `fixture-photo` attachment + `feature_image` image field back the `{{image}}` reads; manifest v5, additive)
- [`tools/test/registered-roots-test-matrix.md`](../../test/registered-roots-test-matrix.md) (added 1.17.0, #87 — the FR rows below as a runnable matrix: §FR1/§FR2 render-tag, §FR5 editor-eyeball for the enum, §FR6 the converter run)
- the external-source contract rows on `matrix-fixture-roots` (added 1.17.0, #85 — two
  registered chain roots plus the `fixture_*` modifier corpus the migration rehearses
  against; see §External-source contract below; manifest v8)

Holds the SHARED schema (CPTs, taxonomies, field groups) for the plugin family;
later blueprints (e.g. portal-system) compose on top and must not redefine keys
listed in `manifest.php` `defines` — reuse via composition instead.

## External consumers (check before bumping `version`)

Composing blueprints pin the manifest `version` and enforce the pin at seed
time (their seed.php errors on mismatch). When bumping, verify these still
seed + verify clean, or coordinate a pin bump with them:

| Consumer | Blueprint | Pins |
|---|---|---|
| bws-portal-system | `tools/fixtures/view-structures/` | v4+ |
| meta-conductor | `tools/fixtures/mc-rules/` | v4+ |
| bws-generate-layout-conditions | `tools/fixtures/layout-states/` | v4+ |

`layout-states` asserts against **`department:sales` specifically**: it needs a
populated non-singular archive for a featured-image detection test, and an
empty term archive 404s (the test would then pass without running). If that
term ever loses its posts, that blueprint's verify reports it.

Orchestrated seeding (order + pins + verify in one command):
wp-litespeed env `bin/seed-all.sh <site>`.

## Files

| File | Role |
|---|---|
| `manifest.php` | Data contract — what the seeded site contains, keyed by stable fixture slugs. Consumers pin `version`. |
| `schema.php` | Code — CPT/taxonomy registration, ACF groups, options page, registered meta. Loaded at runtime by the mu-plugin stub `seed.php` installs. |
| `seed.php` | Idempotent applier — reads the manifest, upserts by fixture slug. `wp eval-file`-able. |
| `blocks.php` | GB block markup generator (4 shapes) — builds the matrix pages' content from tag strings. |
| `fixture-source.php` | The class-route chain-root source (#85). Required lazily from `schema.php`'s registration callback — it extends a plugin class, so a top-level declaration would fatal a site with the plugin off. |
| `verify.php` | Post-seed smoke test — renders through the real seam against `/matrix-post-meta/`. Not a matrix replacement. |
| `verify-migration.php` | The modifier→base migration end to end (#86) — report, run, byte-identical render. **Converts the corpus in place; reseed after.** |
| `verify-datetime-migration.php` | The pre-1.6 datetime migration end to end (#90) — report, run, and whether the injected flags move the RENDERED axes. Self-cleaning: it converts its own throwaway draft, so no reseed. |
| `verify-pattern-cache.php` | The GB Pro pattern-cache reconcile end to end (#99) — the defect reproduced, the repair, idempotence, duplicate-row convergence, and the escaping round trip through real meta storage. Self-cleaning: its destructive work happens on throwaway `wp_block` posts, so no reseed. |
| `lib-admin-context.php` | `bws_fixture_assume_administrator()` — sets an administrator when the CLI has no current user. Required by `seed.php` and the three verify scripts that drive converter code. Carries the measurement and the rationale, including why `tools/harvest-replay/replay-tags.php` is deliberately excluded. |

## Seeding

Prereqs: a dedicated test site with GenerateBlocks (Pro) + ACF Pro active
(licensed baseline saved via the env's snapshot tool). From the wp-litespeed env:

> **Seeding runs as an ADMINISTRATOR, and that is load-bearing (#99).** `seed.php` calls
> `bws_fixture_assume_administrator()` before anything else, because WP-CLI runs with no
> current user and capability-gated listeners then do not fire. Concretely: GB Pro's pattern
> cache bails on `! current_user_can( 'edit_post' )`, so a capability-less seed creates the
> `wp_block` fixture with **no cache entry at all** and `verify-pattern-cache.php` fails in a
> way that reads like a code regression. Perturbation was measured before adopting this —
> identical row counts, byte-identical `wp_postmeta` MD5, `verify.php` clean. You do not need
> to pass `--user`; the helper finds one. Passing one explicitly is respected.

```bash
bin/wp.sh <site> eval-file <mounted-repo-path>/tools/fixtures/core-structures/seed.php
```

Then smoke-test:

```bash
bin/wp.sh <site> eval-file <mounted-repo-path>/tools/fixtures/core-structures/verify.php \
  --url=https://<site-domain>/matrix-post-meta/
```

The migration end-to-end run is separate because it MUTATES the corpus — it converts
`/matrix-fixture-roots/` exactly as the admin Migrate button does, then a reseed puts the
pre-conversion `fixture_*` wire back, which is what makes it repeatable rather than
one-shot:

```bash
bin/wp.sh <site> eval-file <mounted-repo-path>/tools/fixtures/core-structures/verify-migration.php \
  --url=https://<site-domain>/matrix-fixture-roots/
bin/seed.sh <site> core-structures        # restore the corpus
```

The datetime end-to-end run needs no reseed — it builds and deletes its own draft rather
than converting corpus wire, because the property it asserts is about the CONVERTER and the
renderer, not about the corpus:

```bash
bin/wp.sh <site> eval-file <mounted-repo-path>/tools/fixtures/core-structures/verify-datetime-migration.php \
  --url=https://<site-domain>/matrix-post-meta/
```

It cannot assert byte-identical before/after the way `verify-migration.php` does: the
pre-1.6 datetime renderers were deleted in the 1.6 consolidation, so the legacy tag has
nothing left to render with. That half of the property lives in
`tools/test/datetime-migration-test.php`, which mirrors the deleted read.

### Pattern cache (#99)

```bash
bin/wp.sh <site> eval-file <mounted-repo-path>/tools/fixtures/core-structures/verify-pattern-cache.php
```

No `--url` is needed: nothing here renders a tag, so there is no ambient context to be wrong
about. The reconcile is deliberately context-free, which is the property that made it
preferable to rebuilding the cache entry.

It needs GenerateBlocks **Pro** active and refuses to run without it, rather than reporting
vacuous passes. Its destructive work happens on throwaway `wp_block` posts it creates and
deletes, so the seeded `bws-fixture-legacy-wire` pattern keeps its pre-migration wire and no
reseed is needed. That seeded pattern is browsable in the block editor's pattern inserter and
editable at **Appearance → Patterns**: inserting it before a reconcile is the defect demo,
since it seeds pre-migration wire into fresh content.

> **Cache-busting from `docker exec sh -c` needs a LITERAL token.** The env's usual
> `?nocache=$RANDOM` expands in bash; the container's `sh` leaves it EMPTY, so the URL is
> constant and LiteSpeed serves the pre-conversion page. That reads as "the migration
> changed nothing" when it changed everything. Use `?nocache=<something-unique>`.

Safe to re-run — upserts by slug; page content is regenerated every run.
Seeding also merges a plugin-settings baseline (phone: global CC `1`, strip OFF —
the phone matrix's default state) into `bws_dynamic_tags_settings`.

> **Reseed is additive — it never DELETES a key removed from the manifest.** If a
> fixture edit *drops* a field (e.g. the join dense↔sparse swap that moved the full
> `name_*` set from `jane-partner` to `tom-associate`), the removed keys stay on the
> old post as orphaned meta until cleared by hand (`wp post meta delete <id> <key>`,
> plus the ACF `_<key>` companion) or a fresh reseed on an empty DB. Verify with
> `wp post meta list <id>` after any field-removing change.
`seed.php` also installs `mu-plugins/bws-fixture-core-structures.php`, a loader stub
whose include path is computed at seed time (nothing machine-specific committed),
so the schema survives snapshot restores.

## Seeded surface (summary — manifest.php is authoritative)

- CPT `staff`, taxonomy `department` (post/page/staff).
- Matrix pages split **by source-state** (tag families accrete rows INTO each):
  `matrix-post-meta` (explicit reads: full field value set + src:site + src:ref),
  `matrix-terms-valid`, `matrix-terms-mixed` (one junk term), `matrix-terms-junk`
  (all junk → fallback); post `sample-event` (discovery edge cases); staff
  `jane-partner` (src:ref target).
- External-source corpus (#85): staff `fixture-root` (the `fixture` root's target) +
  staff `fixture-ref` (its relationship target, the only staff single with a department
  term) + page `matrix-fixture-roots` (the FR rows). See §External-source contract.
- Block pattern (`wp_block`) `bws-fixture-legacy-wire` (#99) — carries a pre-1.6 tag name so
  the converter always rewrites it, plus literal backslashes in both the block-comment JSON
  and a rendered code block so the meta layer's recursive unslash has something to damage.
  Browsable with no `blocks.php` row by construction.
- Options page **Site Settings** with `organization_*` fields.
- Fixture user `fixture-author` (display name + bio) authoring `sample-event`
  → the author-archive context fixture (`/author/fixture-author/`, C3/C13).
- `sample-event` doubles as the date-archive fixture: kept categoryless +
  portal-visible so `/2026/07/` has results under the portal-system anonymous
  query filter (else 404). `department-sales` carries a description (C17).
- join person-name surface: `name_*` parts (Staff Contact group) — dense on
  `tom-associate`, sparse (first+last) on `jane-partner`; `role` + `height_*`
  (incl. blank + zero probes) + a slot-1 `name_first` on `matrix-post-meta`.
  Both staff singles carry a `staff_join` content builder (the name J-rows as
  visible/editable GB blocks — full set on each, tom dense vs jane sparse);
  the post-arm J-rows (height/role/absorb) render in a Join section group on
  `matrix-post-meta`.
- Collision repeaters (Team / Product Features), two flex fields (Page Builder),
  registered-meta set (`bws_global_note`, `bws_page_only`, `subtitle`, `bws_cat_note`),
  `Break </script><b>x</b>` label probe, empty **Scratch** ACF group in the DB.
- Media: one seeded image attachment `fixture-photo` (deterministic solid-color PNG,
  idempotent by `_bws_fixture_slug` meta) + a `feature_image` ACF image field
  (return_format `id`) on `matrix-post-meta` — backs the FW-52 `{{image}}` editor rows.
- Ref-hop return formats, all THREE shapes ACF can hand back (manifest v6):
  `related_staff` (relationship, `id`), `related_staff_obj` (relationship,
  `object` → `WP_Post[]`) and `lead_staff_obj` (post_object, `object` → ONE
  `WP_Post`, the only shape that reaches the reader's non-array wrap). All carry
  the SAME targets, so the hop's output is an equivalence assertion with no new
  expected values (fold matrix RF1/RF2).

## External-source contract (#85, manifest v8)

The in-repo corpus for the registered-chain-root seam (spec
[#80](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/80)), so the
contract is exercised with **no second plugin installed** and the modifier migration has
something reseedable to run against more than once.

| Piece | Where | What it is |
|---|---|---|
| `fixture` root | `fixture-source.php` + `schema.php` | The **class route**: a source registered on `bws_dynamic_tags_register_sources`, opted in with `is_selectable_root()`. Resolves the seeded `staff/fixture-root` post **by slug**. |
| `fixture_alt` root | `schema.php` | The **filter route**: a spec on `bws_dynamic_tags_chain_roots`, no source class. Resolves the seeded `sample-event` post by slug. |
| `fixture_*` tags | `schema.php` (init:21) | A **modifier family** rooted at `fixture`, registered exactly as the external one is — prefix and root key are the same string, which is what makes the seeded tags a faithful rehearsal of the rewrite. |
| Corpus rows | `blocks.php` → `/matrix-fixture-roots/` | FR1/FR2 the roots on a base tag and in a folded slot, FR3 the six migration shapes as `fixture_*` tags, FR4 each shape beside the base wire it must become. |

**Both roots resolve from seeded content, never from request state.** That is the whole
reason they exist rather than the real external source being used here: that one reads
request context, so a row through it cannot state its own expected value. These answer the
same entity on every request, so every row has one.

**Three entities, three distinct values, on purpose.** The corpus page carries its own
`role`/`main_line` and the **Support** term; the root target `fixture-root` carries
different ones and **Sales** alone; its relationship target `fixture-ref` carries a third
set and **Warehouse**. A rooted row that quietly fell through to the ambient entity
therefore prints the wrong words rather than the right ones. (`fixture-ref` exists because
the "relationship **and** taxonomy" shape needs a hop target that carries a term, and
giving one to an existing staff single would have moved state other matrices read.)

Two rows behave in ways that look like faults and are not:

- **FR3.6 renders empty.** The modifier callback returns on `src:site` *before* reading
  either sidecar, so the shape has never rendered; its migrated form (FR4.6, sidecars
  dropped) does. This is the one mapping row that changes rendered output, and `verify.php`
  asserts the divergence so it cannot later be read as a regression.
- **No row reads `use:title`.** A modifier template's text core reads `key` and ignores
  `use` entirely, so `use:title` renders empty on `fixture_`, `term_` and `view_` alike.
  Pre-existing (issue #88); the rows read field keys so an equivalence pair
  cannot pass by comparing two empties.

Running the Tag Converter over the site rewrites the FR3 rows into base tags — that is
what they are for. A reseed puts them back.

## Known gaps

- **§V17 tier-3b (degenerate term context)** — not seedable via a normal term
  archive; needs a deliberately broken one. Deferred, tracked in the
  fixture-testbed plan. Hardest fixture in the set.
- Complex styled/structural review surfaces are NOT generated — hand-build in the
  editor once, then snapshot (block-generation pin in the fixture-testbed plan).
