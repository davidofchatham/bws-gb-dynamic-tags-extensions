# core-structures blueprint

First fixture blueprint (fixture-testbed FW-42). Seeds the state the two existing
manual matrices assume:

- [`tools/test/phone-test-matrix.md`](../../test/phone-test-matrix.md)
- [`tools/test/field-selector-test-matrix.md`](../../test/field-selector-test-matrix.md)
- [`tools/test/text-test-matrix.md`](../../test/text-test-matrix.md) (added 1.14.1 — read-seam rows; uses `staff-tom-associate` + `bws_zero_probe`)
- [`tools/test/join-test-matrix.md`](../../test/join-test-matrix.md) (added 1.15.0 — {{join}} assembly rows; `name_*` person parts dense on `tom-associate` / sparse on `jane-partner`, `role` + `height_*` on `matrix-post-meta`; manifest v2)
- [`tools/test/context-test-matrix.md`](../../test/context-test-matrix.md) (added 1.15.0 — context-aware base tags #19; author-archive C3/C13 via `fixture-author` user, date-archive rows via categoryless portal-visible `sample-event`, `department-sales` description for C17; manifest v4)

Holds the SHARED schema (CPTs, taxonomies, field groups) for the plugin family;
later blueprints (e.g. portal-system) compose on top and must not redefine keys
listed in `manifest.php` `defines` — reuse via composition instead.

## Files

| File | Role |
|---|---|
| `manifest.php` | Data contract — what the seeded site contains, keyed by stable fixture slugs. Consumers pin `version`. |
| `schema.php` | Code — CPT/taxonomy registration, ACF groups, options page, registered meta. Loaded at runtime by the mu-plugin stub `seed.php` installs. |
| `seed.php` | Idempotent applier — reads the manifest, upserts by fixture slug. `wp eval-file`-able. |
| `blocks.php` | GB block markup generator (4 shapes) — builds the matrix pages' content from tag strings. |
| `verify.php` | Post-seed smoke test — renders through the real seam against `/matrix-post-meta/`. Not a matrix replacement. |

## Seeding

Prereqs: a dedicated test site with GenerateBlocks (Pro) + ACF Pro active
(licensed baseline saved via the env's snapshot tool). From the wp-litespeed env:

```bash
bin/wp.sh <site> eval-file <mounted-repo-path>/tools/fixtures/core-structures/seed.php
```

Then smoke-test:

```bash
bin/wp.sh <site> eval-file <mounted-repo-path>/tools/fixtures/core-structures/verify.php \
  --url=https://<site-domain>/matrix-post-meta/
```

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

## Known gaps

- **§V17 tier-3b (degenerate term context)** — not seedable via a normal term
  archive; needs a deliberately broken one. Deferred, tracked in the
  fixture-testbed plan. Hardest fixture in the set.
- Complex styled/structural review surfaces are NOT generated — hand-build in the
  editor once, then snapshot (block-generation pin in the fixture-testbed plan).
